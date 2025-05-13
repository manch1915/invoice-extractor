from decimal import Decimal
from typing import Any

import pytest

from invoice_worker.engines.base import EngineContext
from invoice_worker.engines.rules import RulesEngine
from invoice_worker.pipeline import Pipeline
from invoice_worker.schemas import FieldKey
from tests.conftest import demo_file

pipeline = Pipeline(RulesEngine(), RulesEngine(), "rus+eng", 5)


def clean_docs(manifest: list[dict[str, Any]]) -> list[dict[str, Any]]:
    return [m for m in manifest if m["quality"] == "clean"]


def test_reads_every_header_field_of_clean_pdfs(manifest: list[dict[str, Any]]) -> None:
    misses = []
    for entry in clean_docs(manifest):
        result = pipeline.run(demo_file(entry["file"]), entry["file"], EngineContext())
        expected = entry["expected"]
        for key in ("document_number", "issue_date", "due_date", "currency", "total", "tax_amount"):
            got = result.fields[FieldKey(key)].value
            want = expected[key]
            money = key in ("total", "tax_amount")
            ok = (got is not None and Decimal(got) == Decimal(want)) if money else got == want
            if not ok:
                misses.append(f"{entry['file']} {key}: {got!r} != {want!r}")
    assert misses == []


def test_reads_line_items_with_quantities(manifest: list[dict[str, Any]]) -> None:
    entry = next(m for m in manifest if m["file"] == "kanc-0715.pdf")
    result = pipeline.run(demo_file(entry["file"]), entry["file"], EngineContext())
    first = result.line_items[0]
    assert first.description.startswith("Бумага офисная А4")
    assert (first.quantity, first.unit_price, first.amount) == ("10", "389.00", "3890.00")
    assert first.bbox is not None


def test_fields_point_at_the_page(manifest: list[dict[str, Any]]) -> None:
    result = pipeline.run(demo_file("bakeware-0728.pdf"), "bakeware-0728.pdf", EngineContext())
    total = result.fields[FieldKey.TOTAL]
    assert total.value == "1317.00"
    assert total.page == 1
    assert total.bbox is not None
    x, y, w, h = total.bbox
    # totals block sits in the right half, under the table
    assert x > 0.5 and 0.3 < y < 0.8 and 0 < w < 0.3 and 0 < h < 0.05


def test_sole_trader_without_vat(manifest: list[dict[str, Any]]) -> None:
    result = pipeline.run(demo_file("sokolova-0803.pdf"), "sokolova-0803.pdf", EngineContext())
    assert result.fields[FieldKey.TAX_AMOUNT].value == "0.00"
    assert result.fields[FieldKey.TAX_RATE].value == "0"
    assert result.fields[FieldKey.SUBTOTAL].value == result.fields[FieldKey.TOTAL].value


@pytest.mark.parametrize(
    ("name", "vendor"), [("hyper-1002.pdf", "ООО «Гипермаркет Восток»"), ("paperco-1008.pdf", "PAPER & CO STATIONERS")]
)
def test_receipts(name: str, vendor: str) -> None:
    result = pipeline.run(demo_file(name), name, EngineContext())
    assert result.document.doc_type == "receipt"
    assert result.fields[FieldKey.VENDOR_NAME].value == vendor
    assert len(result.line_items) == 2

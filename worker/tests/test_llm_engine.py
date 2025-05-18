import json
from pathlib import Path
from typing import Any

import httpx
import pytest

from invoice_worker.budget import BudgetExceededError, TokenBudget
from invoice_worker.engines.base import Category, EngineContext
from invoice_worker.engines.llm import LlmEngine
from invoice_worker.engines.rules import RulesEngine
from invoice_worker.pipeline import Pipeline
from invoice_worker.schemas import FieldKey
from tests.conftest import demo_file


def completion(payload: dict[str, Any], tokens: int = 1200) -> dict[str, Any]:
    return {
        "choices": [{"message": {"role": "assistant", "content": json.dumps(payload, ensure_ascii=False)}}],
        "usage": {"total_tokens": tokens},
    }


def model_answer() -> dict[str, Any]:
    fields = {key.value: {"value": None, "line": None} for key in FieldKey}
    fields.update(
        {
            "vendor_name": {"value": "Bakeware Direct Ltd", "line": 0},
            "vendor_tax_id": {"value": "GB 284 6175 39", "line": 3},
            "document_number": {"value": "BD-24-1187", "line": 1},
            "issue_date": {"value": "2026-07-28", "line": 2},
            "currency": {"value": "gbp", "line": None},
            "total": {"value": "1,317.00", "line": 17},
            # a value that is not on the page at all
            "buyer_tax_id": {"value": "7700000000", "line": 9},
        }
    )
    return {
        "doc_type": "invoice",
        "fields": fields,
        "line_items": [
            {
                "description": "Perforated baguette tray, 5 channels",
                "quantity": "12",
                "unit_price": "38.50",
                "amount": "462.00",
                "line": 12,
            },
        ],
        "category": "equipment",
    }


def engine_with(handler: Any, tmp_path: Path, limit: int = 100_000) -> LlmEngine:
    client = httpx.Client(base_url="https://llm.test/v1", transport=httpx.MockTransport(handler))
    return LlmEngine(client, "test-model", TokenBudget(str(tmp_path / "budget.json"), limit))


def run(engine: LlmEngine) -> Any:
    pipeline = Pipeline(engine, RulesEngine(), "rus+eng", 5)
    context = EngineContext(categories=[Category("equipment", "Equipment"), Category("software", "Software")])
    return pipeline.run(demo_file("bakeware-0728.pdf"), "bakeware-0728.pdf", context)


def test_normalizes_values_and_finds_them_on_the_page(tmp_path: Path) -> None:
    seen: list[dict[str, Any]] = []

    def handler(request: httpx.Request) -> httpx.Response:
        seen.append(json.loads(request.content))
        return httpx.Response(200, json=completion(model_answer()))

    result = run(engine_with(handler, tmp_path))

    assert result.document.engine == "llm"
    assert seen[0]["response_format"]["type"] == "json_schema"
    assert result.fields[FieldKey.TOTAL].value == "1317.00"
    assert result.fields[FieldKey.TOTAL].bbox is not None
    assert result.fields[FieldKey.CURRENCY].value == "GBP"
    assert result.fields[FieldKey.VENDOR_TAX_ID].value == "GB284617539"
    assert result.fields[FieldKey.ISSUE_DATE].bbox is not None
    assert result.category is not None and result.category.code == "equipment"
    assert result.line_items[0].bbox is not None


def test_value_missing_from_page_gets_low_confidence(tmp_path: Path) -> None:
    result = run(engine_with(lambda r: httpx.Response(200, json=completion(model_answer())), tmp_path))
    invented = result.fields[FieldKey.BUYER_TAX_ID]
    assert invented.value == "7700000000"
    assert invented.bbox is None
    assert invented.confidence <= 0.5


def test_falls_back_to_json_object_mode(tmp_path: Path) -> None:
    formats: list[str] = []

    def handler(request: httpx.Request) -> httpx.Response:
        body = json.loads(request.content)
        formats.append(body["response_format"]["type"])
        if body["response_format"]["type"] == "json_schema":
            return httpx.Response(400, json={"error": {"message": "response_format json_schema is not supported"}})
        return httpx.Response(200, json=completion(model_answer()))

    result = run(engine_with(handler, tmp_path))
    assert formats == ["json_schema", "json_object"]
    assert result.fields[FieldKey.DOCUMENT_NUMBER].value == "BD-24-1187"


def test_provider_error_falls_back_to_rules(tmp_path: Path) -> None:
    result = run(engine_with(lambda r: httpx.Response(503, text="overloaded"), tmp_path))
    assert result.document.engine == "rules"
    assert result.fields[FieldKey.TOTAL].value == "1317.00"
    assert any("llm engine unavailable" in w for w in result.warnings)


def test_daily_budget_stops_calls(tmp_path: Path) -> None:
    calls = 0

    def handler(request: httpx.Request) -> httpx.Response:
        nonlocal calls
        calls += 1
        return httpx.Response(200, json=completion(model_answer(), tokens=19_000))

    engine = engine_with(handler, tmp_path, limit=20_000)
    assert run(engine).document.engine == "llm"
    assert engine.budget.used_today() == 19_000
    # the next call would need more than the 1 000 tokens left, so it is not made at all
    assert run(engine).document.engine == "rules"
    assert calls == 1
    with pytest.raises(BudgetExceededError):
        engine.budget.reserve(2_000)


def test_failed_or_unmetered_calls_still_count(tmp_path: Path) -> None:
    engine = engine_with(lambda r: httpx.Response(503, text="overloaded"), tmp_path)
    run(engine)
    after_failure = engine.budget.used_today()
    assert after_failure > 2_000  # the reservation stays: the provider may still bill a failed call

    no_usage = completion(model_answer())
    del no_usage["usage"]
    engine = engine_with(lambda r: httpx.Response(200, json=no_usage), tmp_path)
    run(engine)
    assert engine.budget.used_today() > after_failure


def test_damaged_ledger_fails_closed(tmp_path: Path) -> None:
    budget = TokenBudget(str(tmp_path / "budget.json"), 100_000)
    (tmp_path / "budget.json").write_text("{not json", encoding="utf-8")
    with pytest.raises(BudgetExceededError):
        budget.reserve(10)

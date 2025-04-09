from decimal import Decimal

import pytest

from invoice_worker.parsing.amounts import detect_decimal_separator, find_amounts, parse_amount


@pytest.mark.parametrize(
    ("raw", "hint", "expected"),
    [
        ("1 234,56", None, "1234.56"),
        ("1\u00a0234,56 руб.", None, "1234.56"),
        ("1.234,56", None, "1234.56"),
        ("1,234.56", None, "1234.56"),
        ("$1,234", None, "1234"),
        ("1,234", ",", "1.234"),
        ("12,5", None, "12.5"),
        ("99.90", None, "99.90"),
        ("1 000 000", None, "1000000"),
        ("-15,00", None, "-15.00"),
        ("abc", None, None),
    ],
)
def test_parse_amount(raw: str, hint: str | None, expected: str | None) -> None:
    result = parse_amount(raw, hint)
    assert (str(result) if result is not None else None) == expected


def test_find_amounts_keeps_grouped_thousands_together() -> None:
    tokens = [raw for _, _, raw in find_amounts("Итого: 12 450,00   НДС 20%: 2 075,00")]
    assert tokens == ["12 450,00", "20", "2 075,00"]


def test_find_amounts_skips_parts_of_identifiers() -> None:
    assert [raw for *_, raw in find_amounts("INV-2026-0042 total 99.50")] == ["99.50"]


def test_detect_decimal_separator() -> None:
    assert detect_decimal_separator("Сумма 1 200,00 и 300,50") == ","
    assert detect_decimal_separator("Total 1,200.00 and 300.50") == "."


def test_parse_amount_with_currency_glyph() -> None:
    assert parse_amount("€ 2.450,90") == Decimal("2450.90")

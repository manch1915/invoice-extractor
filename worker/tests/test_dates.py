from datetime import date

from invoice_worker.parsing.dates import find_dates


def values(text: str, month_first: bool = False) -> list[date]:
    return [d.value for d in find_dates(text, month_first)]


def test_numeric_formats() -> None:
    assert values("от 12.10.2026") == [date(2026, 10, 12)]
    assert values("Date: 2026-09-03") == [date(2026, 9, 3)]
    assert values("Issued 03/09/2026") == [date(2026, 9, 3)]
    assert values("Issued 03/09/2026", month_first=True) == [date(2026, 3, 9)]


def test_day_over_twelve_falls_back_to_us_order() -> None:
    assert values("09/23/2026") == [date(2026, 9, 23)]


def test_russian_month_names() -> None:
    assert values("Счет № 118 от 7 октября 2026 г.") == [date(2026, 10, 7)]
    assert values("до 1 мая 2026") == [date(2026, 5, 1)]


def test_english_month_names() -> None:
    assert values("Invoice date: October 14, 2026") == [date(2026, 10, 14)]
    assert values("Due 28 Nov 2026") == [date(2026, 11, 28)]


def test_invalid_dates_are_skipped() -> None:
    assert values("31.02.2026") == []

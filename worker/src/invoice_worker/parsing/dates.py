"""Dates in the formats that show up on RU and EN invoices and receipts."""

import re
from dataclasses import dataclass
from datetime import date

RU_MONTHS = {
    "янв": 1, "фев": 2, "мар": 3, "апр": 4, "мая": 5, "май": 5, "июн": 6,
    "июл": 7, "авг": 8, "сен": 9, "окт": 10, "ноя": 11, "дек": 12,
}  # fmt: skip
EN_MONTHS = {
    "jan": 1, "feb": 2, "mar": 3, "apr": 4, "may": 5, "jun": 6,
    "jul": 7, "aug": 8, "sep": 9, "oct": 10, "nov": 11, "dec": 12,
}  # fmt: skip

_NUMERIC = re.compile(r"\b(\d{1,4})([./-])(\d{1,2})\2(\d{2,4})\b")
_TEXT_DMY = re.compile(r"\b(\d{1,2})\s+([A-Za-zА-Яа-яЁё]{3,10})\.?,?\s+(\d{4})(?:\s*г\.?)?", re.UNICODE)
_TEXT_MDY = re.compile(r"\b([A-Za-z]{3,9})\.?\s+(\d{1,2})(?:st|nd|rd|th)?,?\s+(\d{4})\b")


@dataclass(frozen=True)
class DateMatch:
    value: date
    start: int
    end: int
    raw: str


def _month(name: str) -> int | None:
    key = name.lower()
    for table in (RU_MONTHS, EN_MONTHS):
        for prefix, number in table.items():
            if key.startswith(prefix):
                return number
    return None


def _safe(year: int, month: int, day: int) -> date | None:
    if year < 100:
        year += 2000
    try:
        return date(year, month, day)
    except ValueError:
        return None


def find_dates(text: str, month_first: bool = False) -> list[DateMatch]:
    """All dates in the text, in order. month_first=True reads 10/12/2026 as Oct 12 (US)."""
    found: list[DateMatch] = []

    for m in _NUMERIC.finditer(text):
        a, sep, b, c = m.group(1), m.group(2), m.group(3), m.group(4)
        if len(a) == 4:
            parsed = _safe(int(a), int(b), int(c))
        elif sep == "/" and month_first:
            parsed = _safe(int(c), int(a), int(b))
        else:
            parsed = _safe(int(c), int(b), int(a))
            if parsed is None and sep == "/":
                parsed = _safe(int(c), int(a), int(b))
        if parsed:
            found.append(DateMatch(parsed, m.start(), m.end(), m.group(0)))

    for m in _TEXT_DMY.finditer(text):
        month = _month(m.group(2))
        parsed = _safe(int(m.group(3)), month, int(m.group(1))) if month else None
        if parsed:
            found.append(DateMatch(parsed, m.start(), m.end(), m.group(0)))

    for m in _TEXT_MDY.finditer(text):
        month = _month(m.group(1))
        parsed = _safe(int(m.group(3)), month, int(m.group(2))) if month else None
        if parsed:
            found.append(DateMatch(parsed, m.start(), m.end(), m.group(0)))

    found.sort(key=lambda d: d.start)
    return found

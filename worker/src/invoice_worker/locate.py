"""Find where a value given by the LLM sits on the page, so the reviewer can see it highlighted."""

from dataclasses import dataclass
from datetime import date
from decimal import Decimal, InvalidOperation

from rapidfuzz import fuzz

from invoice_worker.layout import DocumentLayout, Line
from invoice_worker.parsing.amounts import find_amounts, parse_amount
from invoice_worker.parsing.dates import find_dates


@dataclass(frozen=True)
class Location:
    line: Line
    start: int
    end: int
    exact: bool


def _candidates(layout: DocumentLayout, hint: int | None) -> list[Line]:
    lines = list(layout.lines())
    if hint is None or not 0 <= hint < len(lines):
        return lines
    # the model's line number is usually right or off by one; search there first, then everywhere
    near = [lines[i] for i in (hint, hint - 1, hint + 1) if 0 <= i < len(lines)]
    return near + [ln for ln in lines if ln not in near]


def locate_amount(layout: DocumentLayout, value: str, hint: int | None, decimal_hint: str | None) -> Location | None:
    try:
        target = Decimal(value)
    except InvalidOperation:
        return None
    for line in _candidates(layout, hint):
        for start, end, raw in reversed(find_amounts(line.text)):
            if parse_amount(raw, decimal_hint) == target:
                return Location(line, start, end, True)
    return None


def locate_date(layout: DocumentLayout, value: str, hint: int | None) -> Location | None:
    try:
        target = date.fromisoformat(value)
    except ValueError:
        return None
    for line in _candidates(layout, hint):
        for month_first in (False, True):
            for found in find_dates(line.text, month_first):
                if found.value == target:
                    return Location(line, found.start, found.end, True)
    return None


def locate_text(layout: DocumentLayout, value: str, hint: int | None, threshold: float = 82) -> Location | None:
    needle = value.strip()
    if not needle:
        return None
    best: Location | None = None
    best_score = threshold
    for line in _candidates(layout, hint):
        idx = line.text.find(needle)
        if idx >= 0:
            return Location(line, idx, idx + len(needle), True)
        if len(line.text) < len(needle) * 0.6:
            continue
        alignment = fuzz.partial_ratio_alignment(needle.lower(), line.text.lower())
        if alignment and alignment.score > best_score:
            best_score = alignment.score
            best = Location(line, alignment.dest_start, alignment.dest_end, False)
    return best

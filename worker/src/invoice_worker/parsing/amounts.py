"""Money and quantity parsing for RU, EN and EU number formats."""

import re
from decimal import Decimal, InvalidOperation

# Thousands may be grouped with space, nbsp, thin space, dot, comma or apostrophe.
AMOUNT_RE = re.compile(
    r"(?<![\w.,/-])-?(?:\d{1,3}(?:[ \u00a0\u202f.,'](?=\d{3}\b)\d{3})+|\d+)(?:[.,]\d{1,2})?(?![\w/-]|[.,]\d)"
)


def parse_amount(text: str, decimal_hint: str | None = None) -> Decimal | None:
    """Turn '1 234,56', '1.234,56', '1,234.56' or '$1,234' into a Decimal.

    decimal_hint is ',' or '.' when the document's convention is known; it only matters
    for ambiguous inputs like '1,234' (one group of three digits after a single separator).
    """
    cleaned = re.sub(r"[^\d.,\-]", "", text).strip(".,")
    if not cleaned or not re.search(r"\d", cleaned):
        return None

    negative = cleaned.startswith("-")
    cleaned = cleaned.lstrip("-")

    last_dot, last_comma = cleaned.rfind("."), cleaned.rfind(",")
    if last_dot >= 0 and last_comma >= 0:
        decimal_sep = "." if last_dot > last_comma else ","
    elif last_dot >= 0 or last_comma >= 0:
        sep = "." if last_dot >= 0 else ","
        tail = cleaned.rsplit(sep, 1)[1]
        if cleaned.count(sep) > 1:
            decimal_sep = None
        elif len(tail) == 3:
            decimal_sep = sep if decimal_hint == sep else None
        else:
            decimal_sep = sep
    else:
        decimal_sep = None

    if decimal_sep is None:
        digits = cleaned.replace(".", "").replace(",", "")
    else:
        whole, frac = cleaned.rsplit(decimal_sep, 1)
        digits = whole.replace(".", "").replace(",", "") + "." + frac

    try:
        value = Decimal(digits)
    except InvalidOperation:
        return None
    return -value if negative else value


def find_amounts(text: str) -> list[tuple[int, int, str]]:
    """Return (start, end, raw) for every number-looking token in the text."""
    return [(m.start(), m.end(), m.group(0)) for m in AMOUNT_RE.finditer(text)]


def format_decimal(value: Decimal, places: int = 2) -> str:
    quant = Decimal(1).scaleb(-places)
    return str(value.quantize(quant))


def detect_decimal_separator(text: str) -> str | None:
    """Look at clear money tokens ('12,50', '1.234,00', '1,234.00') and vote."""
    comma = len(re.findall(r"\d[ .\u00a0]?\d{3},\d{2}\b|\b\d+,\d{2}\b", text))
    dot = len(re.findall(r"\d,\d{3}\.\d{2}\b|\b\d+\.\d{2}\b", text))
    if comma == dot:
        return None
    return "," if comma > dot else "."

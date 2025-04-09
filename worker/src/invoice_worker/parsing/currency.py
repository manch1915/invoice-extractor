import re
from collections import Counter

_PATTERNS: list[tuple[str, re.Pattern[str]]] = [
    ("RUB", re.compile(r"₽|\bруб(?:\.|л[ея]й?)?|\bRUB\b|\bр\.(?=\s|$)", re.IGNORECASE)),
    ("USD", re.compile(r"\$|\bUSD\b|US\s?dollars?", re.IGNORECASE)),
    ("EUR", re.compile(r"€|\bEUR\b|\beuros?\b", re.IGNORECASE)),
    ("GBP", re.compile(r"£|\bGBP\b|pounds? sterling", re.IGNORECASE)),
    ("AMD", re.compile(r"֏|\bAMD\b|\bдрам", re.IGNORECASE)),
    ("KZT", re.compile(r"₸|\bKZT\b|\bтенге\b", re.IGNORECASE)),
]


def detect_currency(text: str) -> tuple[str | None, int]:
    """Most mentioned currency and how many times it was seen."""
    counts: Counter[str] = Counter()
    for code, pattern in _PATTERNS:
        counts[code] = len(pattern.findall(text))
    if not counts or counts.most_common(1)[0][1] == 0:
        return None, 0
    code, hits = counts.most_common(1)[0]
    return code, hits

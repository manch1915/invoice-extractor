"""Words grouped into reading-order lines, with character offsets back to word boxes."""

from collections.abc import Iterable, Iterator
from dataclasses import dataclass, field

from invoice_worker.schemas import BBox


@dataclass(frozen=True)
class RawWord:
    text: str
    page: int
    x0: float
    y0: float
    x1: float
    y1: float
    conf: float = 1.0

    @property
    def cy(self) -> float:
        return (self.y0 + self.y1) / 2

    @property
    def height(self) -> float:
        return self.y1 - self.y0


@dataclass
class Line:
    page: int
    words: list[RawWord]
    text: str = ""
    spans: list[tuple[int, int]] = field(default_factory=list)
    index: int = 0

    def __post_init__(self) -> None:
        parts: list[str] = []
        pos = 0
        prev: RawWord | None = None
        for word in self.words:
            if prev is not None:
                # wide gaps usually separate table columns, keep them visible to the parsers
                gap = word.x0 - prev.x1
                sep = "   " if gap > max(word.height * 1.6, 12) else " "
                parts.append(sep)
                pos += len(sep)
            self.spans.append((pos, pos + len(word.text)))
            parts.append(word.text)
            pos += len(word.text)
            prev = word
        self.text = "".join(parts)

    def words_in(self, start: int, end: int) -> list[RawWord]:
        return [w for w, (s, e) in zip(self.words, self.spans, strict=True) if s < end and e > start]

    @property
    def y0(self) -> float:
        return min(w.y0 for w in self.words)

    @property
    def x0(self) -> float:
        return min(w.x0 for w in self.words)


@dataclass
class PageLayout:
    number: int
    width: float
    height: float
    lines: list[Line]

    def normalize(self, words: Iterable[RawWord]) -> BBox | None:
        items = list(words)
        if not items:
            return None
        x0 = min(w.x0 for w in items)
        y0 = min(w.y0 for w in items)
        x1 = max(w.x1 for w in items)
        y1 = max(w.y1 for w in items)
        pad = 2.0
        return (
            round(max(x0 - pad, 0) / self.width, 5),
            round(max(y0 - pad, 0) / self.height, 5),
            round(min(x1 - x0 + pad * 2, self.width) / self.width, 5),
            round(min(y1 - y0 + pad * 2, self.height) / self.height, 5),
        )


@dataclass
class DocumentLayout:
    pages: list[PageLayout]

    def lines(self) -> Iterator[Line]:
        for page in self.pages:
            yield from page.lines

    def page(self, number: int) -> PageLayout:
        return self.pages[number - 1]

    @property
    def text(self) -> str:
        return "\n".join(line.text for line in self.lines())

    def bbox(self, line: Line, start: int, end: int) -> BBox | None:
        return self.page(line.page).normalize(line.words_in(start, end))

    def mean_conf(self, line: Line, start: int, end: int) -> float:
        words = line.words_in(start, end)
        return sum(w.conf for w in words) / len(words) if words else 0.0


def group_lines(words: list[RawWord], page: int) -> list[Line]:
    """Cluster words into lines by vertical overlap, then order left to right."""
    rows: list[list[RawWord]] = []
    for word in sorted(words, key=lambda w: (w.cy, w.x0)):
        for row in rows:
            ref = row[-1]
            tolerance = min(ref.height, word.height) * 0.55
            if abs(ref.cy - word.cy) <= tolerance:
                row.append(word)
                break
        else:
            rows.append([word])

    lines = [Line(page=page, words=sorted(row, key=lambda w: w.x0)) for row in rows]
    lines.sort(key=lambda ln: (ln.y0, ln.x0))
    return lines


def build_layout(pages: list[tuple[float, float, list[RawWord]]]) -> DocumentLayout:
    result: list[PageLayout] = []
    index = 0
    for number, (width, height, words) in enumerate(pages, start=1):
        lines = group_lines([w for w in words if w.text.strip()], number)
        for line in lines:
            line.index = index
            index += 1
        result.append(PageLayout(number=number, width=width, height=height, lines=lines))
    return DocumentLayout(pages=result)

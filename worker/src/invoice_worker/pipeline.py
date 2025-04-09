import logging
import time

from invoice_worker import documents
from invoice_worker.engines.base import Engine, EngineContext, EngineResult
from invoice_worker.layout import build_layout
from invoice_worker.schemas import DocumentMeta, ExtractionResult, Page, Word

log = logging.getLogger(__name__)


class Pipeline:
    def __init__(self, primary: Engine, fallback: Engine, languages: str, max_pages: int) -> None:
        self.primary = primary
        self.fallback = fallback
        self.languages = languages
        self.max_pages = max_pages

    def run(self, data: bytes, filename: str, context: EngineContext) -> ExtractionResult:
        started = time.perf_counter()
        sources = documents.load(data, filename, self.languages, self.max_pages)
        layout = build_layout([(float(p.width), float(p.height), p.words) for p in sources])

        warnings: list[str] = []
        engine_name = self.primary.name
        try:
            result: EngineResult = self.primary.extract(layout, context)
        except Exception as exc:
            if self.primary is self.fallback:
                raise
            log.warning("primary engine failed, falling back to rules: %s", exc)
            warnings.append(f"{self.primary.name} engine unavailable: {type(exc).__name__}")
            engine_name = self.fallback.name
            result = self.fallback.extract(layout, context)

        ocr_words = [w for p in sources if not p.from_text_layer for w in p.words]
        quality = sum(w.conf for w in ocr_words) / len(ocr_words) if ocr_words else 1.0
        if not any(p.words for p in sources):
            warnings.append("no text found on the page")
        if any(p.photo for p in sources):
            warnings.append("photo was perspective-corrected")

        pages = [
            Page(
                number=p.number,
                width=p.width,
                height=p.height,
                image=p.encoded(),
                words=[
                    Word(text=w.text, bbox=bbox, conf=round(w.conf, 3))
                    for w in p.words
                    if (bbox := layout.page(p.number).normalize([w])) is not None
                ],
            )
            for p in sources
        ]

        return ExtractionResult(
            document=DocumentMeta(
                page_count=len(sources),
                source="text_layer" if all(p.from_text_layer for p in sources) else "ocr",
                language=result.language,
                doc_type=result.doc_type,
                engine=engine_name,
                ocr_quality=round(quality, 3),
                duration_ms=int((time.perf_counter() - started) * 1000),
            ),
            pages=pages,
            fields=result.fields,
            line_items=result.line_items,
            category=result.category,
            warnings=warnings + result.warnings,
        )

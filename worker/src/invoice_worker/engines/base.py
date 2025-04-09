from dataclasses import dataclass, field
from typing import Protocol

from invoice_worker.layout import DocumentLayout
from invoice_worker.schemas import CategorySuggestion, DocType, ExtractedField, FieldKey, LineItem


@dataclass(frozen=True)
class Category:
    code: str
    name: str


@dataclass
class EngineContext:
    categories: list[Category] = field(default_factory=list)


@dataclass
class EngineResult:
    doc_type: DocType
    language: str
    fields: dict[FieldKey, ExtractedField]
    line_items: list[LineItem]
    category: CategorySuggestion | None = None
    warnings: list[str] = field(default_factory=list)


class Engine(Protocol):
    name: str

    def extract(self, layout: DocumentLayout, context: EngineContext) -> EngineResult: ...


def detect_language(text: str) -> str:
    cyrillic = sum(1 for ch in text if "а" <= ch.lower() <= "я" or ch in "ёЁ")
    latin = sum(1 for ch in text if "a" <= ch.lower() <= "z")
    return "ru" if cyrillic >= latin * 0.5 else "en"

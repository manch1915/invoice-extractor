from enum import StrEnum

from pydantic import BaseModel, Field

# x, y, width, height relative to the page image, 0..1
BBox = tuple[float, float, float, float]


class FieldKey(StrEnum):
    VENDOR_NAME = "vendor_name"
    VENDOR_TAX_ID = "vendor_tax_id"
    BUYER_NAME = "buyer_name"
    BUYER_TAX_ID = "buyer_tax_id"
    DOCUMENT_NUMBER = "document_number"
    ISSUE_DATE = "issue_date"
    DUE_DATE = "due_date"
    CURRENCY = "currency"
    SUBTOTAL = "subtotal"
    TAX_RATE = "tax_rate"
    TAX_AMOUNT = "tax_amount"
    TOTAL = "total"


class DocType(StrEnum):
    INVOICE = "invoice"
    RECEIPT = "receipt"
    WAYBILL = "waybill"
    ACT = "act"


class Word(BaseModel):
    text: str
    bbox: BBox
    conf: float = Field(ge=0, le=1)


class Page(BaseModel):
    number: int
    width: int
    height: int
    image: str = Field(description="base64 WebP of the page as shown to the reviewer")
    words: list[Word]


class ExtractedField(BaseModel):
    value: str | None
    raw: str | None = None
    confidence: float = Field(ge=0, le=1)
    page: int | None = None
    bbox: BBox | None = None


class LineItem(BaseModel):
    description: str
    quantity: str | None = None
    unit_price: str | None = None
    amount: str | None = None
    confidence: float = Field(ge=0, le=1)
    page: int | None = None
    bbox: BBox | None = None


class CategorySuggestion(BaseModel):
    code: str
    confidence: float = Field(ge=0, le=1)


class DocumentMeta(BaseModel):
    page_count: int
    source: str = Field(description="text_layer or ocr")
    language: str
    doc_type: DocType
    engine: str
    ocr_quality: float = Field(ge=0, le=1)
    duration_ms: int


class ExtractionResult(BaseModel):
    document: DocumentMeta
    pages: list[Page]
    fields: dict[FieldKey, ExtractedField]
    line_items: list[LineItem]
    category: CategorySuggestion | None = None
    warnings: list[str] = []

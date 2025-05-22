"""Extraction through any OpenAI-compatible chat completions API with a strict JSON schema."""

import json
import logging
from datetime import date
from decimal import Decimal
from typing import Any

import httpx

from invoice_worker.budget import TokenBudget
from invoice_worker.config import Settings
from invoice_worker.engines.base import EngineContext, EngineResult, detect_language
from invoice_worker.layout import DocumentLayout
from invoice_worker.locate import Location, locate_amount, locate_date, locate_text
from invoice_worker.parsing.amounts import detect_decimal_separator, parse_amount
from invoice_worker.schemas import CategorySuggestion, DocType, ExtractedField, FieldKey, LineItem

log = logging.getLogger(__name__)

MAX_PROMPT_CHARS = 14_000
MAX_ANSWER_TOKENS = 2_000
MAX_CATEGORIES = 60
AMOUNT_KEYS = {FieldKey.SUBTOTAL, FieldKey.TAX_AMOUNT, FieldKey.TOTAL, FieldKey.TAX_RATE}
DATE_KEYS = {FieldKey.ISSUE_DATE, FieldKey.DUE_DATE}

SYSTEM_PROMPT = """You read invoices, receipts, waybills and acts of completed work for a bookkeeper.
The document is given as numbered lines of OCR text, so expect small recognition errors.
Return only what is printed on the document. Use null when a value is absent; never guess.
Formats: dates YYYY-MM-DD; money and quantities as plain decimals with a dot and no thousands separators;
currency as an ISO 4217 code; tax_rate as a percent number like 20; tax ids without spaces.
For every field also return the number of the line where you found it.
The vendor is the party that issued the document and gets paid; the buyer is who pays.
Pick category only from the given codes, or null if none fits."""


def _nullable(kind: str) -> dict[str, Any]:
    return {"type": [kind, "null"]}


def response_schema(category_codes: list[str]) -> dict[str, Any]:
    field = {
        "type": "object",
        "properties": {"value": _nullable("string"), "line": _nullable("integer")},
        "required": ["value", "line"],
        "additionalProperties": False,
    }
    item = {
        "type": "object",
        "properties": {
            "description": {"type": "string"},
            "quantity": _nullable("string"),
            "unit_price": _nullable("string"),
            "amount": _nullable("string"),
            "line": _nullable("integer"),
        },
        "required": ["description", "quantity", "unit_price", "amount", "line"],
        "additionalProperties": False,
    }
    return {
        "type": "object",
        "properties": {
            "doc_type": {"type": "string", "enum": [t.value for t in DocType]},
            "fields": {
                "type": "object",
                "properties": {key.value: field for key in FieldKey},
                "required": [key.value for key in FieldKey],
                "additionalProperties": False,
            },
            "line_items": {"type": "array", "items": item},
            "category": {"type": ["string", "null"], "enum": [*category_codes, None]},
        },
        "required": ["doc_type", "fields", "line_items", "category"],
        "additionalProperties": False,
    }


class LlmEngine:
    name = "llm"

    def __init__(self, client: httpx.Client, model: str, budget: TokenBudget) -> None:
        self.client = client
        self.model = model
        self.budget = budget

    @classmethod
    def from_settings(cls, settings: Settings) -> "LlmEngine":
        client = httpx.Client(
            base_url=settings.llm_base_url.rstrip("/"),
            headers={"Authorization": f"Bearer {settings.llm_api_key}"},
            timeout=settings.llm_timeout,
        )
        return cls(client, settings.llm_model, TokenBudget(settings.llm_budget_file, settings.llm_daily_token_limit))

    def extract(self, layout: DocumentLayout, context: EngineContext) -> EngineResult:
        categories = context.categories[:MAX_CATEGORIES]
        listing = "\n".join(f"- {c.code[:40]}: {c.name[:80]}" for c in categories) or "(none)"
        document = "\n".join(f"[{ln.index}] {ln.text}" for ln in layout.lines())
        user = f"Expense categories:\n{listing}\n\nDocument:\n{document}"[:MAX_PROMPT_CHARS]
        messages = [
            {"role": "system", "content": SYSTEM_PROMPT},
            {"role": "user", "content": user},
        ]
        codes = [c.code[:40] for c in categories]
        payload = self._complete(messages, codes)
        return self._to_result(payload, layout, set(codes))

    def _complete(self, messages: list[dict[str, str]], codes: list[str]) -> dict[str, Any]:
        schema = response_schema(codes)
        request: dict[str, Any] = {
            "model": self.model,
            "messages": messages,
            "temperature": 0,
            "max_tokens": MAX_ANSWER_TOKENS,
            "response_format": {
                "type": "json_schema",
                "json_schema": {"name": "document", "strict": True, "schema": schema},
            },
        }
        response = self._post(request)
        if response.status_code == 400 and "response_format" in response.text:
            # some compatible providers only know json_object mode
            request["response_format"] = {"type": "json_object"}
            request["messages"] = [
                *messages[:-1],
                {"role": "user", "content": messages[-1]["content"] + "\n\nReply with JSON matching this schema:\n"
                 + json.dumps(schema)},
            ]  # fmt: skip
            response = self._post(request)
        response.raise_for_status()
        content = response.json()["choices"][0]["message"]["content"]
        parsed = json.loads(content)
        if not isinstance(parsed, dict):
            raise ValueError("model returned a non-object")
        return parsed

    def _post(self, request: dict[str, Any]) -> httpx.Response:
        """One billed call: book the worst case first, then replace it with the reported usage."""
        # Cyrillic averages two to three characters per token, so half the length is an upper bound
        reservation = self.budget.reserve(len(json.dumps(request, ensure_ascii=False)) // 2 + MAX_ANSWER_TOKENS)
        used: int | None = None
        try:
            response = self.client.post("/chat/completions", json=request)
            if response.is_success:
                usage = response.json().get("usage") or {}
                used = usage["total_tokens"] if isinstance(usage.get("total_tokens"), int) else None
            return response
        finally:
            self.budget.settle(reservation, used)

    def _to_result(self, payload: dict[str, Any], layout: DocumentLayout, codes: set[str]) -> EngineResult:
        decimal_hint = detect_decimal_separator(layout.text)
        fields: dict[FieldKey, ExtractedField] = {}
        raw_fields = payload.get("fields") or {}

        for key in FieldKey:
            entry = raw_fields.get(key.value) or {}
            value = _clean(key, entry.get("value"))
            hint = entry.get("line") if isinstance(entry.get("line"), int) else None
            if value is None:
                fields[key] = ExtractedField(value=None, confidence=0.0)
                continue
            if key in AMOUNT_KEYS:
                where = locate_amount(layout, value, hint, decimal_hint)
            elif key in DATE_KEYS:
                where = locate_date(layout, value, hint)
            elif key == FieldKey.CURRENCY:
                where = None
            else:
                where = locate_text(layout, value, hint)
            fields[key] = self._field(layout, value, where, key == FieldKey.CURRENCY)

        items = []
        for raw in payload.get("line_items") or []:
            desc = str(raw.get("description") or "").strip()
            if not desc:
                continue
            hint = raw.get("line") if isinstance(raw.get("line"), int) else None
            where = locate_text(layout, desc, hint, threshold=70)
            line = where.line if where else None
            ocr = layout.mean_conf(line, 0, len(line.text)) if line else 0.5
            amounts = [_decimal(raw.get(k)) for k in ("quantity", "unit_price", "amount")]
            qty, price, amount = amounts
            consistent = None not in amounts and abs(qty * price - amount) <= Decimal("0.05")  # type: ignore[operator]
            items.append(
                LineItem(
                    description=desc,
                    quantity=_fmt(qty),
                    unit_price=_fmt(price, 2),
                    amount=_fmt(amount, 2),
                    confidence=round((0.9 if consistent else 0.65) * (0.4 + 0.6 * ocr), 3),
                    page=line.page if line else None,
                    bbox=layout.bbox(line, 0, len(line.text)) if line else None,
                )
            )

        doc_type = str(payload.get("doc_type"))
        category = payload.get("category")
        return EngineResult(
            doc_type=DocType(doc_type) if doc_type in DocType._value2member_map_ else DocType.INVOICE,
            language=detect_language(layout.text),
            fields=fields,
            line_items=items,
            category=CategorySuggestion(code=category, confidence=0.7) if category in codes else None,
        )

    @staticmethod
    def _field(layout: DocumentLayout, value: str, where: Location | None, trusted: bool) -> ExtractedField:
        if where is None:
            # Not found on the page: either reformatted beyond recognition or invented. Ask a human.
            return ExtractedField(value=value, confidence=0.8 if trusted else 0.5)
        ocr = layout.mean_conf(where.line, where.start, where.end)
        base = 0.93 if where.exact else 0.8
        return ExtractedField(
            value=value,
            raw=where.line.text[where.start : where.end],
            confidence=round(base * (0.4 + 0.6 * ocr), 3),
            page=where.line.page,
            bbox=layout.bbox(where.line, where.start, where.end),
        )


def _decimal(value: object) -> Decimal | None:
    if value is None:
        return None
    # asked for plain decimals with a dot, but models sometimes copy the printed "1,317.00"
    return parse_amount(str(value), ".")


def _fmt(value: Decimal | None, places: int | None = None) -> str | None:
    if value is None:
        return None
    if places is not None:
        return str(value.quantize(Decimal(1).scaleb(-places)))
    return format(value.normalize(), "f")


def _clean(key: FieldKey, value: object) -> str | None:
    if value is None:
        return None
    text = str(value).strip()
    if not text:
        return None
    if key in AMOUNT_KEYS:
        number = _decimal(text.rstrip("%"))
        if number is None:
            return None
        return _fmt(number, 2) if key != FieldKey.TAX_RATE else _fmt(number)
    if key in DATE_KEYS:
        try:
            return date.fromisoformat(text).isoformat()
        except ValueError:
            return None
    if key in (FieldKey.VENDOR_TAX_ID, FieldKey.BUYER_TAX_ID):
        return text.replace(" ", "").replace("-", "")
    if key == FieldKey.CURRENCY:
        return text.upper()[:3]
    return text

"""Keyword and layout heuristics. Works offline and is the fallback when no LLM is configured."""

import re
from dataclasses import dataclass
from decimal import Decimal

from invoice_worker.engines.base import EngineContext, EngineResult, detect_language
from invoice_worker.layout import DocumentLayout, Line
from invoice_worker.parsing.amounts import (
    detect_decimal_separator,
    find_amounts,
    format_decimal,
    parse_amount,
)
from invoice_worker.parsing.currency import detect_currency
from invoice_worker.parsing.dates import find_dates
from invoice_worker.schemas import DocType, ExtractedField, FieldKey, LineItem

I = re.IGNORECASE  # noqa: E741

DOC_TYPE_PATTERNS: list[tuple[DocType, re.Pattern[str]]] = [
    (
        DocType.WAYBILL,
        re.compile(r"\bнакладная\b|торг-12|\bупд\b|передаточный документ|delivery note|packing slip|waybill", I),
    ),
    (DocType.ACT, re.compile(r"\bакт\b(?!\s+сверки)|certificate of completion|work completion", I)),
    (DocType.RECEIPT, re.compile(r"кассовый чек|\bчек\b|фискальн|\breceipt\b|sales slip", I)),
    (DocType.INVOICE, re.compile(r"сч[её]т|invoice|\bbill\b", I)),
]  # fmt: skip

NUMBER_RE = re.compile(
    r"(?:сч[её]т(?:-фактура)?(?:\s+на\s+оплату)?|накладная|\bупд\b|\bакт\b[а-я ]{0,40}?|\bчек\b|\bзаказ\b"
    r"|invoice|receipt|\bbill\b|delivery note|order)\s*(?:№|N[°o]?\.?|No\.?|number|num\.?|#)\s*[:.]?\s*"
    r"([A-Za-zА-Яа-я0-9][\w\-/.]*\d[\w\-/]*|\d+)",
    I,
)
LABELLED_NUMBER_RE = re.compile(
    r"(?:invoice|receipt|document|номер документа)\s*(?:no\.?|number|#|№)\s*[:.]?\s+(\S+)", I
)
BARE_NUMBER_RE = re.compile(r"(?:№|#)\s*([A-Za-zА-Яа-я0-9][\w\-/]*)")

ISSUE_LABEL_RE = re.compile(
    r"invoice date|date of issue|issue date|issued(?: on)?|\bdate\b|дата(?: документа| составления| выдачи)?|\bот\b", I
)
DUE_LABEL_RE = re.compile(
    r"due date|payment due|due by|due on|pay by|\bdue\b|оплатить до|не позднее|срок оплаты|оплата до", I
)

_ROLE_TAIL = r"\b(?:\s*\([^)]{0,30}\))?\s*[:\-]?\s*"
VENDOR_LABEL_RE = re.compile(
    r"^\s*(?:поставщик|продавец|исполнитель|получатель платежа|from|supplier|seller|vendor|billed by|sold by|payee)"
    + _ROLE_TAIL,
    I,
)
BUYER_LABEL_RE = re.compile(
    r"^\s*(?:покупатель|заказчик|плательщик|грузополучатель|bill(?:ed)? to|invoice to|customer|sold to|client)"
    + _ROLE_TAIL,
    I,
)
COMPANY_RE = re.compile(
    r"\b(?:ООО|ОАО|ЗАО|ПАО|АО|ИП|LLC|Ltd\.?|Limited|Inc\.?|GmbH|plc|S\.?A\.?S\.?|B\.?V\.?|Co\.|Corp\.?)(?=\s|$|[»\",.])"
)
INN_RE = re.compile(r"ИНН(?:\s*/\s*КПП)?\s*[:№]?\s*(\d{10}|\d{12})\b", I)
VAT_RE = re.compile(
    r"(?:VAT(?:\s*Reg(?:istration)?)?(?:\s*(?:No\.?|number|ID))?|USt-?IdNr\.?|Tax\s*ID|TIN|EIN|ABN|GST\s*No\.?)\s*[:#.]?\s*"
    r"((?:[A-Z]{2}\s?)?\d[\d \-]{6,14}\d|[A-Z]{2}[A-Z0-9]{8,12})",
)

TAX_EXEMPT_RE = re.compile(
    r"без\s+(?:налога\s*\(?)?ндс|ндс\s+не\s+облагается|не\s+облагается\s+ндс|vat exempt|no vat|zero[- ]rated", I
)
TOTAL_LABELS: list[tuple[int, re.Pattern[str]]] = [
    (
        5,
        re.compile(r"(?:итого|всего|сумма)\s+к\s+оплате|amount due|balance due|total due|grand total|total payable", I),
    ),
    (4, re.compile(r"\bк\s+оплате\b|\bитого\b(?!\s+без)|\bитог\b|\btotal\b(?!\s+(?:vat|tax|net|excl))", I)),
    (2, re.compile(r"\bвсего\b(?!\s+наименований)|на\s+сумму", I)),
]  # fmt: skip
SUBTOTAL_RE = re.compile(
    r"sub-?total|net amount|total net|net total|total excl|сумма без ндс|итого без ндс|всего без ндс", I
)
TAX_RE = re.compile(r"(?:в\s+том\s+числе|в\s+т\.\s*ч\.?)?\s*\bндс\b|\bvat\b|sales tax|\btax\b|\bgst\b|\bmwst\b", I)
RATE_RE = re.compile(r"(\d{1,2}(?:[.,]\d{1,2})?)\s*%")

COLUMN_KEYWORDS: dict[str, re.Pattern[str]] = {
    "description": re.compile(r"наименование|товар|работ|услуг|description|item|product|service|details", I),
    "quantity": re.compile(r"кол-?во|количество|qty|quantity|units|hours|hrs", I),
    "unit_price": re.compile(r"цена|price|rate|unit cost", I),
    "amount": re.compile(r"сумма|стоимость|amount|line total|\btotal\b", I),
}
ROW_INDEX_RE = re.compile(r"^\d{1,3}[.)]?$")
RECEIPT_ITEM_RE = re.compile(
    r"^(?:(?P<desc>.*?)\s+)?(?P<qty>\d+(?:[.,]\d+)?)\s*[xх×*]\s*"
    r"(?P<price>\d[\d .,]*?)\s*=?\s+(?P<amount>\d[\d .,]*\d)$",
    I,
)


@dataclass
class Hit:
    line: Line
    start: int
    end: int
    value: str
    raw: str
    strength: float


class RulesEngine:
    name = "rules"

    def extract(self, layout: DocumentLayout, context: EngineContext) -> EngineResult:
        self.layout = layout
        self.lines = list(layout.lines())
        text = layout.text
        self.language = detect_language(text)
        self.decimal_hint = detect_decimal_separator(text) or ("," if self.language == "ru" else ".")
        currency, currency_hits = detect_currency(text)

        fields: dict[FieldKey, ExtractedField] = {}
        doc_type = self._doc_type()

        number = self._document_number()
        fields[FieldKey.DOCUMENT_NUMBER] = self._field(number)
        issue, due = self._dates(number)
        fields[FieldKey.ISSUE_DATE] = self._field(issue)
        fields[FieldKey.DUE_DATE] = self._field(due)

        vendor, vendor_tax, buyer, buyer_tax = self._parties(doc_type)
        fields[FieldKey.VENDOR_NAME] = self._field(vendor)
        fields[FieldKey.VENDOR_TAX_ID] = self._field(vendor_tax)
        fields[FieldKey.BUYER_NAME] = self._field(buyer)
        fields[FieldKey.BUYER_TAX_ID] = self._field(buyer_tax)

        if currency:
            fields[FieldKey.CURRENCY] = ExtractedField(
                value=currency, raw=currency, confidence=0.95 if currency_hits > 1 else 0.8
            )
        else:
            fallback = "RUB" if self.language == "ru" else None
            fields[FieldKey.CURRENCY] = ExtractedField(value=fallback, confidence=0.55 if fallback else 0.0)

        totals = self._totals()
        for key in (FieldKey.SUBTOTAL, FieldKey.TAX_RATE, FieldKey.TAX_AMOUNT, FieldKey.TOTAL):
            fields[key] = self._field(totals.get(key))

        items = self._line_items()
        self._cross_check(fields, items)

        return EngineResult(doc_type=doc_type, language=self.language, fields=fields, line_items=items)

    # --- helpers -----------------------------------------------------------------

    def _field(self, hit: Hit | None) -> ExtractedField:
        if hit is None:
            return ExtractedField(value=None, confidence=0.0)
        ocr = self.layout.mean_conf(hit.line, hit.start, hit.end)
        return ExtractedField(
            value=hit.value,
            raw=hit.raw,
            confidence=round(min(hit.strength * (0.35 + 0.65 * ocr), 0.99), 3),
            page=hit.line.page,
            bbox=self.layout.bbox(hit.line, hit.start, hit.end),
        )

    def _top_lines(self, count: int = 12) -> list[Line]:
        return [ln for ln in self.lines if ln.page == 1][:count]

    def _doc_type(self) -> DocType:
        top = " ".join(ln.text for ln in self._top_lines(10))
        for doc_type, pattern in DOC_TYPE_PATTERNS:
            if pattern.search(top):
                return doc_type
        return DocType.INVOICE

    def _document_number(self) -> Hit | None:
        for pattern, strength in ((NUMBER_RE, 0.95), (LABELLED_NUMBER_RE, 0.9)):
            for line in self.lines[:40]:
                m = pattern.search(line.text)
                if m:
                    value = m.group(1).rstrip(".,")
                    return Hit(line, m.start(1), m.start(1) + len(value), value, value, strength)
        for line in self._top_lines(15):
            m = BARE_NUMBER_RE.search(line.text)
            if m:
                return Hit(line, m.start(1), m.end(1), m.group(1), m.group(1), 0.6)
        return None

    def _dates(self, number: Hit | None) -> tuple[Hit | None, Hit | None]:
        month_first = bool(re.search(r"\$|USD|\b(?:CA|NY|TX)\s+\d{5}\b", self.layout.text))
        issue: Hit | None = None
        due: Hit | None = None

        for line in self.lines:
            dates = find_dates(line.text, month_first)
            if not dates:
                continue
            due_label = DUE_LABEL_RE.search(line.text)
            if due_label and due is None:
                after = [d for d in dates if d.start >= due_label.start()]
                if after:
                    d = after[0]
                    due = Hit(line, d.start, d.end, d.value.isoformat(), d.raw, 0.92)
                    continue
            if issue is None:
                label = ISSUE_LABEL_RE.search(line.text)
                same_as_number = number is not None and number.line is line
                if same_as_number or (label and label.start() <= dates[0].start):
                    d = dates[0]
                    issue = Hit(line, d.start, d.end, d.value.isoformat(), d.raw, 0.93 if same_as_number else 0.88)

        if issue is None:
            for line in self.lines[:25]:
                dates = find_dates(line.text, month_first)
                if dates and not DUE_LABEL_RE.search(line.text):
                    d = dates[0]
                    issue = Hit(line, d.start, d.end, d.value.isoformat(), d.raw, 0.6)
                    break
        return issue, due

    def _party_from(self, idx: int, label_end: int) -> Hit | None:
        line = self.lines[idx]
        rest = line.text[label_end:].strip()
        if rest:
            name = re.split(r",\s*(?:ИНН|КПП|ОГРН|адрес|\d{6})|\s{3,}|\s+ИНН", rest, maxsplit=1, flags=I)[0].strip(
                " ,;"
            )
            start = line.text.find(name, label_end)
            if name and start >= 0:
                return Hit(line, start, start + len(name), name, name, 0.9)
        if idx + 1 < len(self.lines):
            nxt = self.lines[idx + 1]
            name = re.split(r"\s{3,}|,\s*ИНН", nxt.text, maxsplit=1)[0].strip(" ,;")
            if name:
                return Hit(nxt, 0, len(name), name, name, 0.85)
        return None

    def _tax_id_near(self, idx: int, span: int = 3) -> Hit | None:
        for line in self.lines[idx : idx + span]:
            for pattern in (INN_RE, VAT_RE):
                m = pattern.search(line.text)
                if m:
                    value = re.sub(r"[\s\-]", "", m.group(1))
                    return Hit(line, m.start(1), m.end(1), value, m.group(1), 0.92)
        return None

    def _parties(self, doc_type: DocType) -> tuple[Hit | None, Hit | None, Hit | None, Hit | None]:
        vendor = vendor_tax = buyer = buyer_tax = None
        vendor_idx = buyer_idx = None

        for idx, line in enumerate(self.lines):
            if vendor is None and (m := VENDOR_LABEL_RE.search(line.text)):
                vendor, vendor_idx = self._party_from(idx, m.end()), idx
            elif buyer is None and (m := BUYER_LABEL_RE.search(line.text)):
                buyer, buyer_idx = self._party_from(idx, m.end()), idx

        if vendor is None:
            # Letterhead: first company-looking line at the top, otherwise the very first line on receipts.
            for idx, line in enumerate(self._top_lines(8)):
                if buyer_idx is not None and idx >= buyer_idx:
                    break
                m = COMPANY_RE.search(line.text)
                if m:
                    name = re.split(r"\s{3,}", line.text)[0].strip()
                    vendor, vendor_idx = Hit(line, 0, len(name), name, name, 0.75), idx
                    break
            if vendor is None and doc_type == DocType.RECEIPT and self.lines:
                first = self.lines[0]
                vendor, vendor_idx = Hit(first, 0, len(first.text), first.text.strip(), first.text, 0.6), 0

        if vendor_idx is not None:
            vendor_tax = self._tax_id_near(vendor_idx, 4)
        if buyer_idx is not None:
            buyer_tax = self._tax_id_near(buyer_idx, 3)
            if vendor_tax and buyer_tax and vendor_tax.line is buyer_tax.line:
                vendor_tax = None

        if vendor_tax is None:
            # One tax id on the page that is not the buyer's most likely belongs to the seller.
            for idx in range(len(self.lines)):
                found = self._tax_id_near(idx, 1)
                if found and (buyer_tax is None or found.value != buyer_tax.value):
                    found.strength = 0.7
                    vendor_tax = found
                    break
        return vendor, vendor_tax, buyer, buyer_tax

    def _amount_in(self, line: Line, after: int = 0) -> Hit | None:
        candidates = [
            (s, e, raw)
            for s, e, raw in find_amounts(line.text)
            if s >= after and not line.text[e : e + 2].lstrip().startswith("%")
        ]
        if not candidates:
            return None
        s, e, raw = candidates[-1]
        value = parse_amount(raw, self.decimal_hint)
        if value is None:
            return None
        return Hit(line, s, e, format_decimal(value), raw, 0.92)

    def _totals(self) -> dict[FieldKey, Hit]:
        found: dict[FieldKey, Hit] = {}
        best_total: tuple[int, int] | None = None

        for idx, line in enumerate(self.lines):
            low = line.text
            if SUBTOTAL_RE.search(low):
                hit = self._amount_in(line, SUBTOTAL_RE.search(low).end())  # type: ignore[union-attr]
                if hit:
                    found[FieldKey.SUBTOTAL] = hit
                continue

            tax_label = TAX_RE.search(low)
            if tax_label and not TOTAL_LABELS[0][1].search(low):
                if TAX_EXEMPT_RE.search(low):
                    found[FieldKey.TAX_AMOUNT] = Hit(line, tax_label.start(), tax_label.end(), "0.00", line.text, 0.85)
                    found[FieldKey.TAX_RATE] = Hit(line, tax_label.start(), tax_label.end(), "0", line.text, 0.85)
                    continue
                rate = RATE_RE.search(low)
                if rate:
                    value = parse_amount(rate.group(1), ",") or Decimal(0)
                    found[FieldKey.TAX_RATE] = Hit(
                        line,
                        rate.start(),
                        rate.end(),
                        format_decimal(value, 2).rstrip("0").rstrip("."),
                        rate.group(0),
                        0.9,
                    )
                hit = self._amount_in(line, rate.end() if rate else tax_label.end())
                if hit:
                    found[FieldKey.TAX_AMOUNT] = hit
                    continue

            for priority, pattern in TOTAL_LABELS:
                label = pattern.search(low)
                if not label:
                    continue
                hit = self._amount_in(line, label.end())
                if hit is None and idx + 1 < len(self.lines):
                    hit = self._amount_in(self.lines[idx + 1])
                if hit and (best_total is None or (priority, idx) >= best_total):
                    hit.strength = 0.9 + priority * 0.01
                    found[FieldKey.TOTAL] = hit
                    best_total = (priority, idx)
                break
        return found

    def _header(self) -> tuple[int, dict[str, tuple[float, float]]] | None:
        for idx, line in enumerate(self.lines):
            columns: dict[str, tuple[float, float]] = {}
            for word in line.words:
                name = next(
                    (n for n, p in COLUMN_KEYWORDS.items() if n not in columns and p.search(word.text)),
                    None,
                )
                if name:
                    columns[name] = (word.x0, word.x1)
            if len(columns) >= 3 and "amount" in columns:
                return idx, columns
        return None

    def _line_items(self) -> list[LineItem]:
        header = self._header()
        if header is None:
            return self._receipt_items()

        start, columns = header
        numeric = {k: (x0 + x1) / 2 for k, (x0, x1) in columns.items() if k != "description"}
        page_width = self.layout.page(self.lines[start].page).width
        tolerance = page_width * 0.02
        numbers_from = min(columns[k][0] for k in numeric) - tolerance

        items: list[LineItem] = []
        for line in self.lines[start + 1 :]:
            if any(p.search(line.text) for _, p in TOTAL_LABELS) or SUBTOTAL_RE.search(line.text):
                break
            candidates = []
            for s, e, raw in find_amounts(line.text):
                words = line.words_in(s, e)
                if not words or line.text[e : e + 1] == "%":
                    continue
                cx = (words[0].x0 + words[-1].x1) / 2
                if cx >= numbers_from:
                    candidates.append((s, e, raw, cx))
            desc_words = [w for w in line.words if w.x1 < numbers_from]
            if desc_words and ROW_INDEX_RE.match(desc_words[0].text):
                desc_words = desc_words[1:]

            if len(candidates) < 2:
                if items and desc_words and not candidates and line.page == items[-1].page:
                    items[-1].description += " " + " ".join(w.text for w in desc_words)
                continue

            # each column takes the closest number, each number is used once
            assigned: dict[str, str] = {}
            pairs = sorted(
                (
                    (abs(cx - center), name, i)
                    for name, center in numeric.items()
                    for i, (*_, cx) in enumerate(candidates)
                ),
            )
            used: set[int] = set()
            for _, name, i in pairs:
                if name in assigned or i in used:
                    continue
                assigned[name] = candidates[i][2]
                used.add(i)

            desc = " ".join(w.text for w in desc_words).strip(" |")
            if not desc:
                continue

            values = {k: parse_amount(raw, self.decimal_hint) for k, raw in assigned.items()}
            items.append(self._item(line, desc, values.get("quantity"), values.get("unit_price"), values.get("amount")))
        return items

    def _item(
        self, line: Line, desc: str, qty: Decimal | None, price: Decimal | None, amount: Decimal | None
    ) -> LineItem:
        consistent = (
            qty is not None
            and price is not None
            and amount is not None
            and abs(qty * price - amount) <= Decimal("0.05")
        )
        ocr = self.layout.mean_conf(line, 0, len(line.text))
        return LineItem(
            description=desc,
            quantity=_num(qty),
            unit_price=_num(price, 2),
            amount=_num(amount, 2),
            confidence=round((0.92 if consistent else 0.6) * (0.35 + 0.65 * ocr), 3),
            page=line.page,
            bbox=self.layout.bbox(line, 0, len(line.text)),
        )

    def _receipt_items(self) -> list[LineItem]:
        items: list[LineItem] = []
        for idx, line in enumerate(self.lines):
            text = re.sub(r"\s{2,}", " ", line.text.strip())
            m = RECEIPT_ITEM_RE.match(text)
            if not m:
                continue
            desc = (m.group("desc") or "").strip(" .:")
            if not desc and idx > 0:
                # "Item name" on one line, "2 x 149,00 = 298,00" on the next
                desc = self.lines[idx - 1].text.strip()
            if not desc:
                continue
            items.append(
                self._item(
                    line,
                    desc,
                    parse_amount(m.group("qty"), self.decimal_hint),
                    parse_amount(m.group("price"), self.decimal_hint),
                    parse_amount(m.group("amount"), self.decimal_hint),
                )
            )
        return items

    def _cross_check(self, fields: dict[FieldKey, ExtractedField], items: list[LineItem]) -> None:
        """Numbers that agree with each other are more likely read correctly."""

        def dec(key: FieldKey) -> Decimal | None:
            value = fields[key].value
            return Decimal(value) if value is not None else None

        total, subtotal, tax = dec(FieldKey.TOTAL), dec(FieldKey.SUBTOTAL), dec(FieldKey.TAX_AMOUNT)
        if (
            total is not None
            and subtotal is not None
            and tax is not None
            and abs(subtotal + tax - total) <= Decimal("0.02")
        ):
            for key in (FieldKey.TOTAL, FieldKey.SUBTOTAL, FieldKey.TAX_AMOUNT):
                fields[key].confidence = max(fields[key].confidence, 0.96)

        items_sum = sum((Decimal(i.amount) for i in items if i.amount), Decimal(0))
        matches_total = total is not None and abs(items_sum - total) <= Decimal("0.02")
        matches_net = subtotal is not None and abs(items_sum - subtotal) <= Decimal("0.02")
        if items and (matches_total or matches_net):
            fields[FieldKey.TOTAL].confidence = max(fields[FieldKey.TOTAL].confidence, 0.95)

        # Prices include VAT and only "в т.ч. НДС" is printed: derive the net amount.
        # It is exactly as reliable as the two numbers it comes from.
        if subtotal is None and total is not None and tax is not None:
            confidence = min(fields[FieldKey.TOTAL].confidence, fields[FieldKey.TAX_AMOUNT].confidence)
            fields[FieldKey.SUBTOTAL] = ExtractedField(value=format_decimal(total - tax), confidence=confidence)


def _num(value: Decimal | None, places: int | None = None) -> str | None:
    if value is None:
        return None
    if places is not None:
        return format_decimal(value, places)
    normalized = value.normalize()
    return format(normalized, "f")

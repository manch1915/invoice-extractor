"""Render the demo document set: clean PDFs, scans and phone photos.

uv run python demo/generate.py ../database/demo
"""

import io
import json
import random
import sys
from dataclasses import dataclass
from datetime import date
from decimal import ROUND_HALF_UP, Decimal
from pathlib import Path

import cv2
import matplotlib
import numpy as np
import pymupdf
from data import BUYER_EN, BUYER_RU, DOCS, Doc, Item, Party, V
from num2words import num2words
from reportlab.lib.pagesizes import A4
from reportlab.lib.units import mm
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.pdfgen.canvas import Canvas

FONT_DIR = Path(matplotlib.get_data_path()) / "fonts" / "ttf"
for name, file in {
    "Sans": "DejaVuSans.ttf",
    "Sans-Bold": "DejaVuSans-Bold.ttf",
    "Serif": "DejaVuSerif.ttf",
    "Serif-Bold": "DejaVuSerif-Bold.ttf",
    "Mono": "DejaVuSansMono.ttf",
    "Mono-Bold": "DejaVuSansMono-Bold.ttf",
}.items():
    pdfmetrics.registerFont(TTFont(name, str(FONT_DIR / file)))

RU_MONTHS_GEN = ["января", "февраля", "марта", "апреля", "мая", "июня", "июля", "августа",
                 "сентября", "октября", "ноября", "декабря"]  # fmt: skip
EN_MONTHS = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"]
SYMBOL = {"GBP": "£", "USD": "$", "EUR": "€", "RUB": "₽"}
CENT = Decimal("0.01")


def ru_money(value: Decimal) -> str:
    whole, frac = f"{value.quantize(CENT):.2f}".split(".")
    groups = f"{int(whole):,}".replace(",", " ")
    return f"{groups},{frac}"


def en_money(value: Decimal, eu: bool = False) -> str:
    text = f"{value.quantize(CENT):,.2f}"
    if eu:
        text = text.replace(",", "_").replace(".", ",").replace("_", ".")
    return text


def ru_date_long(d: date) -> str:
    return f"{d.day} {RU_MONTHS_GEN[d.month - 1]} {d.year} г."


def amount_words_ru(value: Decimal) -> str:
    rub = int(value)
    kop = int((value - rub) * 100)
    words = num2words(rub, lang="ru")
    return f"{words[0].upper()}{words[1:]} рублей {kop:02d} копеек"


@dataclass
class Totals:
    subtotal: Decimal
    vat: Decimal
    total: Decimal
    lines_sum: Decimal


def waybill_net_price(item: Item, rate: Decimal) -> Decimal:
    return (item.price * 100 / (100 + rate)).quantize(CENT, ROUND_HALF_UP)


def waybill_net(item: Item, rate: Decimal) -> Decimal:
    return (waybill_net_price(item, rate) * item.qty).quantize(CENT, ROUND_HALF_UP)


def totals_for(doc: Doc) -> Totals:
    lines_sum = sum((i.amount for i in doc.items), Decimal(0))
    rate = doc.vat_rate
    if doc.template == "ru_waybill":
        # a waybill sums its net and VAT columns, exactly as printed line by line
        nets = [waybill_net(i, rate) for i in doc.items]
        subtotal = sum(nets, Decimal(0))
        vat = lines_sum - subtotal
        return Totals(subtotal, vat, lines_sum, lines_sum)
    if doc.vat_included:
        total = lines_sum
        vat = (total * rate / (100 + rate)).quantize(CENT, ROUND_HALF_UP)
        subtotal = total - vat
    else:
        subtotal = lines_sum
        vat = (subtotal * rate / 100).quantize(CENT, ROUND_HALF_UP)
        total = subtotal + vat
    if doc.vat_override is not None:
        vat = doc.vat_override
        subtotal = total - vat
    if doc.total_override is not None:
        total = doc.total_override
    return Totals(subtotal, vat, total, lines_sum)


class Page:
    def __init__(self, canvas: Canvas, width: float, height: float) -> None:
        self.c = canvas
        self.w = width
        self.h = height

    def text(self, x: float, y: float, s: str, font: str = "Sans", size: float = 9, align: str = "left") -> None:
        self.c.setFont(font, size)
        top = self.h - y
        if align == "right":
            self.c.drawRightString(x, top, s)
        elif align == "center":
            self.c.drawCentredString(x, top, s)
        else:
            self.c.drawString(x, top, s)

    def line(self, x0: float, y0: float, x1: float, y1: float, width: float = 0.6) -> None:
        self.c.setLineWidth(width)
        self.c.line(x0, self.h - y0, x1, self.h - y1)

    def rect(self, x: float, y: float, w: float, h: float, width: float = 0.6) -> None:
        self.c.setLineWidth(width)
        self.c.rect(x, self.h - y - h, w, h)

    def wrap(
        self, x: float, y: float, s: str, max_width: float, font: str = "Sans", size: float = 9, leading: float = 11
    ) -> float:
        words = s.split()
        line = ""
        for word in words:
            trial = f"{line} {word}".strip()
            if pdfmetrics.stringWidth(trial, font, size) > max_width and line:
                self.text(x, y, line, font, size)
                y += leading
                line = word
            else:
                line = trial
        if line:
            self.text(x, y, line, font, size)
            y += leading
        return y


def ru_table(p: Page, y: float, doc: Doc, money: bool = True, waybill: bool = False) -> float:
    left, right = 15 * mm, p.w - 15 * mm
    if waybill:
        cols = [("№", 8 * mm), ("Наименование товара", 62 * mm), ("Ед.", 10 * mm), ("Количество", 18 * mm),
                ("Цена", 20 * mm), ("Сумма без НДС", 24 * mm), ("Ставка", 13 * mm), ("Сумма НДС", 18 * mm)]  # fmt: skip
    else:
        cols = [("№", 8 * mm), ("Товары (работы, услуги)", 92 * mm), ("Кол-во", 18 * mm), ("Ед.", 12 * mm),
                ("Цена", 25 * mm), ("Сумма", 25 * mm)]  # fmt: skip
    scale = (right - left) / sum(w for _, w in cols)
    xs = [left]
    for _, w in cols:
        xs.append(xs[-1] + w * scale)

    p.rect(left, y, right - left, 7 * mm, 0.8)
    for i, (title, _) in enumerate(cols):
        p.text((xs[i] + xs[i + 1]) / 2, y + 4.8 * mm, title, "Sans-Bold", 7.5, "center")
    for x in xs[1:-1]:
        p.line(x, y, x, y + 7 * mm)
    y += 7 * mm

    rate = doc.vat_rate
    for n, it in enumerate(doc.items, start=1):
        name_lines = []
        line = ""
        for word in it.name.split():
            trial = f"{line} {word}".strip()
            if pdfmetrics.stringWidth(trial, "Sans", 8) > (xs[2] - xs[1]) - 4 and line:
                name_lines.append(line)
                line = word
            else:
                line = trial
        name_lines.append(line)
        row_h = max(6 * mm, (len(name_lines) * 3.6 + 2.4) * mm)
        p.rect(left, y, right - left, row_h)
        for x in xs[1:-1]:
            p.line(x, y, x, y + row_h)
        base = y + 4.2 * mm
        p.text(xs[0] + 2, base, str(n), size=8)
        for k, ln in enumerate(name_lines):
            p.text(xs[1] + 2, base + k * 3.6 * mm, ln, size=8)
        qty = f"{it.qty.normalize():f}".replace(".", ",")
        if waybill:
            net_price = waybill_net_price(it, rate)
            net = waybill_net(it, rate)
            vat = (it.amount - net).quantize(CENT)
            cells = [it.unit, qty, ru_money(net_price), ru_money(net), f"{rate.normalize():f}%", ru_money(vat)]
            for i, val in enumerate(cells, start=2):
                p.text(xs[i + 1] - 2, base, val, size=8, align="right")
        else:
            p.text(xs[3] - 2, base, qty, size=8, align="right")
            p.text(xs[3] + 2, base, it.unit, size=8)
            p.text(xs[5] - 2, base, ru_money(it.price), size=8, align="right")
            p.text(xs[6] - 2, base, ru_money(it.amount), size=8, align="right")
        y += row_h
    return y + 3 * mm


def ru_totals(p: Page, y: float, doc: Doc, t: Totals, kind: str) -> float:
    right = p.w - 15 * mm
    label_x = right - 45 * mm
    p.text(label_x, y + 4 * mm, "Итого:", "Sans-Bold", 9, "right")
    p.text(right, y + 4 * mm, ru_money(t.lines_sum if doc.total_override is None else t.total), "Sans-Bold", 9, "right")
    y += 5 * mm
    if doc.vat_rate == 0:
        p.text(label_x, y + 4 * mm, "Без налога (НДС)", "Sans-Bold", 9, "right")
        p.text(right, y + 4 * mm, "-", "Sans-Bold", 9, "right")
    else:
        p.text(label_x, y + 4 * mm, f"В том числе НДС ({doc.vat_rate.normalize():f}%):", "Sans-Bold", 9, "right")
        p.text(right, y + 4 * mm, ru_money(t.vat), "Sans-Bold", 9, "right")
    y += 5 * mm
    if kind != "act":
        p.text(label_x, y + 4 * mm, "Всего к оплате:", "Sans-Bold", 9, "right")
        p.text(right, y + 4 * mm, ru_money(t.total), "Sans-Bold", 9, "right")
        y += 5 * mm
    y += 4 * mm
    noun = "оказано услуг" if kind == "act" else "наименований"
    p.text(15 * mm, y, f"Всего {noun} {len(doc.items)}, на сумму {ru_money(t.total)} руб.", size=9)
    y += 5 * mm
    p.text(15 * mm, y, amount_words_ru(t.total), "Sans-Bold", 9)
    return y + 6 * mm


def party_line(prefix: str, party: Party) -> str:
    bits = [party.name, f"ИНН {party.tax_id}"]
    if party.kpp:
        bits.append(f"КПП {party.kpp}")
    bits.append(party.address)
    if party.phone:
        bits.append(f"тел.: {party.phone}")
    return f"{prefix}: " + ", ".join(bits)


def render_ru_invoice(c: Canvas, doc: Doc) -> None:
    p = Page(c, *A4)
    v = V[doc.vendor]
    t = totals_for(doc)
    left = 15 * mm
    right = p.w - 15 * mm

    if doc.template == "ru_invoice":
        # bank details block as on every Russian invoice
        top = 14 * mm
        p.rect(left, top, right - left, 24 * mm)
        p.line(left, top + 12 * mm, right, top + 12 * mm)
        p.line(left + 110 * mm, top, left + 110 * mm, top + 24 * mm)
        p.text(left + 2, top + 4 * mm, v.bank + ", г. Москва", size=8)
        p.text(left + 2, top + 10 * mm, "Банк получателя", size=7)
        p.text(left + 112 * mm, top + 4 * mm, "БИК  044525" + v.account[-3:], size=8)
        p.text(left + 112 * mm, top + 10 * mm, "Сч. № " + v.account, size=8)
        p.text(left + 2, top + 16 * mm, f"ИНН {v.tax_id}   КПП {v.kpp or '—'}", size=8)
        p.text(left + 2, top + 21 * mm, v.name, size=8)
        p.text(left + 112 * mm, top + 16 * mm, "Получатель", size=7)
        y = top + 34 * mm
        title = f"Счет на оплату № {doc.number} от {ru_date_long(doc.issued)}"
    elif doc.template == "ru_waybill":
        y = 22 * mm
        title = f"Товарная накладная № {doc.number} от {doc.issued:%d.%m.%Y}"
    else:
        y = 22 * mm
        title = f"Акт № {doc.number} от {ru_date_long(doc.issued)}"

    p.text(left, y, title, "Sans-Bold", 14)
    y += 4 * mm
    p.line(left, y, right, y, 1.6)
    y += 7 * mm

    roles = {
        "ru_invoice": ("Поставщик (Исполнитель)", "Покупатель (Заказчик)"),
        "ru_waybill": ("Поставщик", "Плательщик"),
        "ru_act": ("Исполнитель", "Заказчик"),
    }[doc.template]
    y = p.wrap(left, y, party_line(roles[0], v), right - left)
    y += 2 * mm
    y = p.wrap(left, y, party_line(roles[1], BUYER_RU), right - left)
    if doc.template == "ru_act":
        y += 2 * mm
        y = p.wrap(left, y, f"Основание: Договор № {doc.number[-3:]}/26 от 10.01.2026", right - left)
    y += 4 * mm

    y = ru_table(p, y, doc, waybill=doc.template == "ru_waybill")
    y = ru_totals(p, y, doc, t, "act" if doc.template == "ru_act" else "invoice")

    if doc.template == "ru_act":
        y = p.wrap(left, y, "Вышеперечисленные услуги выполнены полностью и в срок. Заказчик претензий по объему, "
                   "качеству и срокам оказания услуг не имеет.", right - left)  # fmt: skip
        y += 10 * mm
        p.text(left, y, "Исполнитель ______________", size=9)
        p.text(left + 95 * mm, y, "Заказчик ______________", size=9)
        return

    if doc.due:
        p.text(left, y, f"Оплатить не позднее {doc.due:%d.%m.%Y}", size=9)
        y += 5 * mm
    p.wrap(left, y, "Оплата данного счета означает согласие с условиями поставки товара. Товар отпускается по факту "
           "прихода денег на р/с Поставщика.", right - left, size=7.5, leading=9)  # fmt: skip
    y += 18 * mm
    p.line(left, y, right, y, 1.2)
    y += 8 * mm
    p.text(left, y, "Руководитель ______________ / Морозов С.А. /", size=9)
    p.text(left + 100 * mm, y, "Бухгалтер ______________ / Лебедева Е.Н. /", size=9)


def render_en_invoice(c: Canvas, doc: Doc) -> None:
    p = Page(c, *A4)
    v = V[doc.vendor]
    t = totals_for(doc)
    left, right = 18 * mm, p.w - 18 * mm
    sym = SYMBOL[doc.currency]
    eu = doc.eu_numbers

    def money(x: Decimal) -> str:
        return f"{en_money(x, eu)} {sym}" if eu else f"{sym}{en_money(x)}"

    def when(d: date) -> str:
        return (
            f"{d.day} {EN_MONTHS[d.month - 1]} {d.year}"
            if doc.currency != "USD"
            else f"{EN_MONTHS[d.month - 1]} {d.day}, {d.year}"
        )

    p.text(left, 26 * mm, v.name, "Sans-Bold", 15)
    p.text(right, 26 * mm, "INVOICE", "Sans-Bold", 20, "right")
    y = 33 * mm
    for ln in v.address.split(", ", 1):
        p.text(left, y, ln, size=8.5)
        y += 4.2 * mm
    if v.email:
        p.text(left, y, v.email, size=8.5)
        y += 4.2 * mm
    if v.tax_id:
        label = "VAT No" if v.tax_id.startswith("GB") else ("USt-IdNr" if v.tax_id.startswith("DE") else "")
        p.text(left, y, f"{label}: {v.tax_id}" if label else v.tax_id, size=8.5)

    meta_y = 36 * mm
    meta = [("Invoice number", doc.number), ("Invoice date", when(doc.issued))]
    if doc.due:
        meta.append(("Due date", when(doc.due)))
    for label, value in meta:
        p.text(right - 38 * mm, meta_y, f"{label}:", "Sans", 8.5, "right")
        p.text(right, meta_y, value, "Sans-Bold", 8.5, "right")
        meta_y += 5 * mm

    y = 66 * mm
    p.text(left, y, "Bill to:", "Sans-Bold", 9)
    y += 5 * mm
    p.text(left, y, BUYER_EN.name, size=9)
    y += 4.3 * mm
    p.text(left, y, BUYER_EN.address, size=8.5)
    y += 4.3 * mm
    p.text(left, y, BUYER_EN.email, size=8.5)
    y += 12 * mm

    xs = [left, left + 98 * mm, left + 118 * mm, left + 146 * mm, right]
    p.c.setFillColorRGB(0.93, 0.93, 0.93)
    p.c.rect(left, p.h - y - 7 * mm, right - left, 7 * mm, stroke=0, fill=1)
    p.c.setFillColorRGB(0, 0, 0)
    for i, title in enumerate(["Description", "Qty", "Unit price", "Amount"]):
        if i == 0:
            p.text(xs[0] + 2, y + 4.8 * mm, title, "Sans-Bold", 8.5)
        else:
            p.text(xs[i + 1] - 2, y + 4.8 * mm, title, "Sans-Bold", 8.5, "right")
    y += 7 * mm
    for it in doc.items:
        y += 6.5 * mm
        p.text(xs[0] + 2, y, it.name, size=8.5)
        p.text(xs[2] - 2, y, f"{it.qty.normalize():f}", size=8.5, align="right")
        p.text(xs[3] - 2, y, en_money(it.price, eu), size=8.5, align="right")
        p.text(xs[4] - 2, y, en_money(it.amount, eu), size=8.5, align="right")
        p.line(left, y + 2.5 * mm, right, y + 2.5 * mm, 0.3)
    y += 10 * mm

    label_x = right - 40 * mm
    rows = [("Subtotal", money(t.subtotal))]
    if doc.vat_rate == 0:
        rows.append(
            ("VAT 0% (export, zero-rated)" if doc.currency in ("EUR", "GBP") else "Sales tax", money(Decimal(0)))
        )
    else:
        tax_label = "VAT" if doc.currency in ("GBP", "EUR") else "Sales tax"
        rows.append((f"{tax_label} {doc.vat_rate.normalize():f}%", money(t.vat)))
    rows.append(("Total due" if doc.due else "Total", money(t.total)))
    for i, (label, value) in enumerate(rows):
        bold = i == len(rows) - 1
        p.text(label_x, y, label, "Sans-Bold" if bold else "Sans", 9.5 if bold else 9, "right")
        p.text(right, y, value, "Sans-Bold" if bold else "Sans", 9.5 if bold else 9, "right")
        y += 6 * mm

    y += 14 * mm
    p.text(left, y, "Payment details", "Sans-Bold", 8.5)
    y += 4.5 * mm
    bank = {
        "GBP": "Sort code 40-11-62  ·  Account 71839204",
        "EUR": "IBAN DE89 6005 0101 0004 2219 87  ·  BIC SOLADEST600",
        "USD": "Paid by card on file. No action needed.",
    }[doc.currency]
    p.text(left, y, bank, size=8.5)
    y += 10 * mm
    p.text(left, y, "Thank you for your business.", size=8.5)


def receipt_height(doc: Doc) -> float:
    return (95 + len(doc.items) * 9) * mm


def render_receipt(c: Canvas, doc: Doc) -> None:
    v = V[doc.vendor]
    t = totals_for(doc)
    width = 80 * mm
    p = Page(c, width, receipt_height(doc))
    cx = width / 2
    ru = doc.template == "ru_receipt"
    font, bold = "Mono", "Mono-Bold"
    y = 10 * mm
    lm, rm = 5 * mm, width - 5 * mm

    def fmt(x: Decimal) -> str:
        return ru_money(x) if ru else en_money(x)

    p.text(cx, y, v.name if ru else v.name.upper(), bold, 8.5, "center")
    y += 4 * mm
    p.text(cx, y, v.address, font, 6.5, "center")
    y += 4 * mm
    if v.tax_id:
        p.text(cx, y, f"ИНН {v.tax_id}" if ru else f"VAT No {v.tax_id}", font, 7, "center")
        y += 5 * mm
    if ru:
        p.text(cx, y, f"КАССОВЫЙ ЧЕК № {doc.number}", bold, 8.5, "center")
        y += 4 * mm
        p.text(cx, y, "ПРИХОД", font, 8, "center")
        y += 4 * mm
        p.text(lm, y, f"{doc.issued:%d.%m.%Y} 14:32", font, 7.5)
    else:
        p.text(cx, y, f"Receipt #{doc.number}", bold, 8.5, "center")
        y += 4 * mm
        stamp = f"{doc.issued:%m/%d/%Y} 08:42 AM" if doc.currency == "USD" else f"{doc.issued:%d/%m/%Y} 11:05"
        p.text(lm, y, stamp, font, 7.5)
    y += 3 * mm
    p.text(lm, y, "-" * 42, font, 7)
    y += 4 * mm

    for it in doc.items:
        qty = f"{it.qty.normalize():f}"
        if ru:
            p.text(lm, y, it.name, font, 7.5)
            y += 3.6 * mm
            p.text(rm, y, f"{qty.replace('.', ',')} x {fmt(it.price)} = {fmt(it.amount)}", font, 7.5, "right")
        else:
            p.text(lm, y, it.name, font, 7.5)
            p.text(lm + 40 * mm, y, f"{qty} x {fmt(it.price)}", font, 7.5)
            p.text(rm, y, fmt(it.amount), font, 7.5, "right")
        y += 5 * mm

    p.text(lm, y, "-" * 42, font, 7)
    y += 5 * mm
    if ru:
        p.text(lm, y, "ИТОГ", bold, 10)
        p.text(rm, y, f"={fmt(t.total)}", bold, 10, "right")
        y += 5 * mm
        p.text(lm, y, "БЕЗНАЛИЧНЫМИ", font, 7.5)
        p.text(rm, y, f"={fmt(t.total)}", font, 7.5, "right")
        y += 4 * mm
        p.text(lm, y, f"В т.ч. НДС {doc.vat_rate.normalize():f}%", font, 7.5)
        p.text(rm, y, f"={fmt(t.vat)}", font, 7.5, "right")
        y += 6 * mm
        p.text(lm, y, "ФН 7380440700123456   ФД 51823", font, 6.5)
        y += 3.5 * mm
        p.text(lm, y, "ФПД 2981234411   СНО: ОСН", font, 6.5)
        y += 6 * mm
        p.text(cx, y, "СПАСИБО ЗА ПОКУПКУ!", bold, 7.5, "center")
    else:
        sym = SYMBOL[doc.currency]
        if doc.vat_included:
            p.text(lm, y, "TOTAL", bold, 10)
            p.text(rm, y, f"{sym}{fmt(t.total)}", bold, 10, "right")
            y += 5 * mm
            p.text(lm, y, f"incl. VAT {doc.vat_rate.normalize():f}%", font, 7.5)
            p.text(rm, y, f"{sym}{fmt(t.vat)}", font, 7.5, "right")
        else:
            for label, value in (("Subtotal", t.subtotal), (f"Sales tax {doc.vat_rate.normalize():f}%", t.vat)):
                p.text(lm, y, label, font, 7.5)
                p.text(rm, y, f"{sym}{fmt(value)}", font, 7.5, "right")
                y += 4 * mm
            y += 1 * mm
            p.text(lm, y, "TOTAL", bold, 10)
            p.text(rm, y, f"{sym}{fmt(t.total)}", bold, 10, "right")
        y += 6 * mm
        p.text(lm, y, "VISA **** 4417   APPROVED", font, 7)
        y += 6 * mm
        p.text(cx, y, "Thank you!", bold, 8, "center")


def render_pdf(doc: Doc) -> bytes:
    buf = io.BytesIO()
    size = (80 * mm, receipt_height(doc)) if doc.template.endswith("receipt") else A4
    c = Canvas(buf, pagesize=size)
    c.setTitle(f"{doc.template} {doc.number}")
    c.setAuthor(V[doc.vendor].name)
    if doc.template.startswith("ru_") and not doc.template.endswith("receipt"):
        render_ru_invoice(c, doc)
    elif doc.template == "en_invoice":
        render_en_invoice(c, doc)
    else:
        render_receipt(c, doc)
    c.showPage()
    c.save()
    return buf.getvalue()


# --- degradations ------------------------------------------------------------------


def rasterize(pdf: bytes, dpi: int) -> np.ndarray:
    with pymupdf.open(stream=pdf, filetype="pdf") as d:
        pix = d[0].get_pixmap(dpi=dpi, alpha=False)
        arr = np.frombuffer(pix.samples, dtype=np.uint8).reshape(pix.height, pix.width, pix.n)
    return cv2.cvtColor(arr, cv2.COLOR_RGB2BGR)


def as_scan(pdf: bytes, rng: random.Random) -> bytes:
    img = rasterize(pdf, 200)
    gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY).astype(np.float32)
    gray = gray * rng.uniform(0.86, 0.94) + rng.uniform(10, 22)  # greyish paper, weaker blacks
    noise = np.random.default_rng(rng.randint(0, 10_000)).normal(0, 7, gray.shape)
    gray = np.clip(gray + noise, 0, 255).astype(np.uint8)
    h, w = gray.shape
    angle = rng.uniform(-1.4, 1.4)
    m = cv2.getRotationMatrix2D((w / 2, h / 2), angle, 1.0)
    gray = cv2.warpAffine(gray, m, (w, h), borderValue=235)
    gray = cv2.GaussianBlur(gray, (3, 3), 0.6)
    ok, jpg = cv2.imencode(".jpg", gray, [cv2.IMWRITE_JPEG_QUALITY, 55])
    assert ok
    out = pymupdf.open()
    page = out.new_page(width=A4[0], height=A4[1])
    page.insert_image(page.rect, stream=jpg.tobytes())
    data = out.tobytes(deflate=True)
    out.close()
    return data


def as_photo(pdf: bytes, rng: random.Random, blur: float = 0.0) -> bytes:
    page = rasterize(pdf, 170)
    page = (page.astype(np.float32) * np.array([0.93, 0.97, 1.0])).clip(0, 255).astype(np.uint8)  # warm light
    h, w = page.shape[:2]
    pad = int(max(h, w) * 0.16)
    canvas_h, canvas_w = h + pad * 2, w + pad * 2

    gen = np.random.default_rng(rng.randint(0, 10_000))
    base = np.array([rng.randint(45, 75), rng.randint(65, 95), rng.randint(95, 130)], dtype=np.float32)
    grain = gen.normal(0, 9, (canvas_h, canvas_w, 1)).astype(np.float32)
    streaks = np.sin(np.linspace(0, rng.uniform(25, 60), canvas_w))[None, :, None] * 10
    table = np.clip(base + grain + streaks, 0, 255).astype(np.uint8)

    jitter = lambda: rng.uniform(0.0, 0.07)  # noqa: E731
    src = np.array([[0, 0], [w, 0], [w, h], [0, h]], dtype=np.float32)
    dst = np.array([
        [pad + w * jitter(), pad + h * jitter() * 0.6],
        [pad + w - w * jitter(), pad + h * jitter() * 0.6],
        [pad + w - w * jitter() * 0.4, pad + h - h * jitter() * 0.3],
        [pad + w * jitter() * 0.4, pad + h - h * jitter() * 0.3],
    ], dtype=np.float32)  # fmt: skip
    m = cv2.getPerspectiveTransform(src, dst)
    warped = cv2.warpPerspective(page, m, (canvas_w, canvas_h), borderMode=cv2.BORDER_CONSTANT, borderValue=(0, 0, 0))
    mask = cv2.warpPerspective(np.full((h, w), 255, np.uint8), m, (canvas_w, canvas_h))
    photo = np.where(mask[..., None] > 0, warped, table)

    # soft shadow from a phone or a hand across one side
    xx = np.linspace(0, 1, canvas_w)[None, :]
    yy = np.linspace(0, 1, canvas_h)[:, None]
    shade = 1 - 0.32 * np.clip(1 - np.hypot(xx - rng.uniform(0.6, 1.0), yy - rng.uniform(0, 0.4)) * 1.7, 0, 1)
    photo = (photo.astype(np.float32) * shade[..., None]).clip(0, 255).astype(np.uint8)

    if blur:
        photo = cv2.GaussianBlur(photo, (0, 0), blur)
    photo = cv2.resize(photo, None, fx=0.75, fy=0.75, interpolation=cv2.INTER_AREA)
    ok, jpg = cv2.imencode(".jpg", photo, [cv2.IMWRITE_JPEG_QUALITY, 82])
    assert ok
    return jpg.tobytes()


def expected(doc: Doc) -> dict[str, object]:
    t = totals_for(doc)
    v = V[doc.vendor]
    return {
        "doc_type": {"ru_invoice": "invoice", "ru_waybill": "waybill", "ru_act": "act"}.get(
            doc.template, "receipt" if doc.template.endswith("receipt") else "invoice"
        ),
        "vendor_name": v.name,
        "vendor_tax_id": v.tax_id.replace(" ", "").replace("EIN", "").replace("-", "") or None,
        "document_number": doc.number,
        "issue_date": doc.issued.isoformat(),
        "due_date": doc.due.isoformat() if doc.due else None,
        "currency": doc.currency,
        "subtotal": f"{t.subtotal:.2f}",
        "tax_amount": f"{t.vat:.2f}",
        "total": f"{t.total:.2f}",
        "line_items": len(doc.items),
        "category": doc.category,
    }


def main(out_dir: Path) -> None:
    docs_dir = out_dir / "documents"
    docs_dir.mkdir(parents=True, exist_ok=True)
    for old in docs_dir.iterdir():
        old.unlink()
    rng = random.Random(20261019)
    manifest = []
    for doc in DOCS:
        pdf = render_pdf(doc)
        if doc.quality == "clean":
            name, data = f"{doc.slug}.pdf", pdf
        elif doc.quality == "scan":
            name, data = f"{doc.slug}-scan.pdf", as_scan(pdf, rng)
        elif doc.quality == "photo":
            name, data = f"{doc.slug}-photo.jpg", as_photo(pdf, rng, blur=rng.uniform(0.4, 0.9))
        else:
            name, data = f"{doc.slug}-photo.jpg", as_photo(pdf, rng, blur=2.6)
        (docs_dir / name).write_bytes(data)
        manifest.append({"file": name, "quality": doc.quality, "notes": doc.notes, "expected": expected(doc)})
    (out_dir / "manifest.json").write_text(json.dumps(manifest, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    print(f"{len(manifest)} documents written to {docs_dir}")


if __name__ == "__main__":
    main(Path(sys.argv[1] if len(sys.argv) > 1 else "../database/demo"))

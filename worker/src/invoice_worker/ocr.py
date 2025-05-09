import re
from dataclasses import replace

import pytesseract

from invoice_worker.imaging import Image
from invoice_worker.layout import RawWord

_TIMES = {"x", "х", "×", "*"}
_COMPANY_FORMS = {"000": "ООО", "0О0": "ООО", "О0О": "ООО", "0OO": "ООО", "OOO": "ООО"}


def read_words(gray: Image, page: int, languages: str, scale: float) -> list[RawWord]:
    """Run Tesseract and return words in the coordinate space of the display image."""
    data = pytesseract.image_to_data(
        gray,
        lang=languages,
        config="--oem 1 --psm 3",
        output_type=pytesseract.Output.DICT,
    )
    words: list[RawWord] = []
    for i, text in enumerate(data["text"]):
        conf = float(data["conf"][i])
        text = text.strip()
        if not text or conf < 0:
            continue
        x, y, w, h = (data[k][i] * scale for k in ("left", "top", "width", "height"))
        words.append(RawWord(text=text, page=page, x0=x, y0=y, x1=x + w, y1=y + h, conf=conf / 100))
    return fix_confusions(words)


def fix_confusions(words: list[RawWord]) -> list[RawWord]:
    """Undo the look-alike swaps that cost us most: ООО read as zeros, 3 read as Cyrillic З."""
    fixed: list[RawWord] = []
    for i, word in enumerate(words):
        following = words[i + 1].text if i + 1 < len(words) else ""
        text = word.text
        if text in _COMPANY_FORMS and following[:1] in "«\"'":
            text = _COMPANY_FORMS[text]
        elif re.fullmatch(r"[Зз]", text) and following in _TIMES:
            text = "3"
        elif m := re.fullmatch(r"([Зз0-9]+)([xх×])", text):
            text = m.group(1).replace("З", "3").replace("з", "3") + m.group(2)
        fixed.append(replace(word, text=text) if text != word.text else word)
    return fixed


def available() -> bool:
    try:
        pytesseract.get_tesseract_version()
    except (pytesseract.TesseractNotFoundError, OSError):
        return False
    return True

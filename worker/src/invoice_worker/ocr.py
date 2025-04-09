import pytesseract

from invoice_worker.imaging import Image
from invoice_worker.layout import RawWord


def read_words(gray: Image, page: int, languages: str, scale: float) -> list[RawWord]:
    """Run Tesseract and return words in the coordinate space of the display image."""
    data = pytesseract.image_to_data(
        gray,
        lang=languages,
        config="--oem 1 --psm 4 -c preserve_interword_spaces=1",
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
    return words


def available() -> bool:
    try:
        pytesseract.get_tesseract_version()
    except (pytesseract.TesseractNotFoundError, OSError):
        return False
    return True

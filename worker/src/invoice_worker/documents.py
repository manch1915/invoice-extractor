"""Turn an uploaded file into page images plus positioned words."""

import base64
import io
from dataclasses import dataclass

import cv2
import numpy as np
import pymupdf
from PIL import Image as PILImage
from PIL import ImageOps

from invoice_worker import imaging, ocr
from invoice_worker.imaging import Image
from invoice_worker.layout import RawWord

DISPLAY_WIDTH = 1400
PDF_DISPLAY_DPI = 150
PDF_OCR_DPI = 300
MIN_TEXT_WORDS = 8


class UnsupportedDocumentError(ValueError):
    pass


@dataclass
class PageSource:
    number: int
    image: Image
    words: list[RawWord]
    from_text_layer: bool
    photo: bool = False

    @property
    def width(self) -> int:
        return int(self.image.shape[1])

    @property
    def height(self) -> int:
        return int(self.image.shape[0])

    def encoded(self, quality: int = 78) -> str:
        ok, buf = cv2.imencode(".webp", self.image, [cv2.IMWRITE_WEBP_QUALITY, quality])
        if not ok:
            raise RuntimeError("could not encode page image")
        return base64.b64encode(buf.tobytes()).decode("ascii")


def _pixmap_to_bgr(pix: "pymupdf.Pixmap") -> Image:
    arr = np.frombuffer(pix.samples, dtype=np.uint8).reshape(pix.height, pix.width, pix.n)
    if pix.n == 4:
        return cv2.cvtColor(arr, cv2.COLOR_RGBA2BGR)
    if pix.n == 1:
        return cv2.cvtColor(arr, cv2.COLOR_GRAY2BGR)
    return cv2.cvtColor(arr, cv2.COLOR_RGB2BGR)


def _ocr_page(img: Image, number: int, languages: str) -> PageSource:
    straight, photo = imaging.straighten(img)
    display, _ = imaging.fit_width(straight, DISPLAY_WIDTH)
    prepared = imaging.for_ocr(straight)
    # words come back in the prepared image's pixels; map them onto the display image
    scale = display.shape[1] / prepared.shape[1]
    words = ocr.read_words(prepared, number, languages, scale)
    return PageSource(number=number, image=display, words=words, from_text_layer=False, photo=photo)


def load_pdf(data: bytes, languages: str, max_pages: int) -> list[PageSource]:
    pages: list[PageSource] = []
    with pymupdf.open(stream=data, filetype="pdf") as doc:
        if doc.page_count == 0:
            raise UnsupportedDocumentError("PDF has no pages")
        for index, page in enumerate(doc):
            if index >= max_pages:
                break
            number = index + 1
            raw_words = page.get_text("words")
            if len(raw_words) >= MIN_TEXT_WORDS:
                scale = PDF_DISPLAY_DPI / 72
                image = _pixmap_to_bgr(page.get_pixmap(dpi=PDF_DISPLAY_DPI, alpha=False))
                words = [
                    RawWord(text=w[4], page=number, x0=w[0] * scale, y0=w[1] * scale, x1=w[2] * scale, y1=w[3] * scale)
                    for w in raw_words
                ]
                pages.append(PageSource(number=number, image=image, words=words, from_text_layer=True))
            else:
                image = _pixmap_to_bgr(page.get_pixmap(dpi=PDF_OCR_DPI, alpha=False))
                pages.append(_ocr_page(image, number, languages))
    return pages


def load_image(data: bytes, languages: str) -> list[PageSource]:
    try:
        with PILImage.open(io.BytesIO(data)) as raw:
            img = ImageOps.exif_transpose(raw).convert("RGB")
    except (OSError, PILImage.DecompressionBombError) as exc:
        raise UnsupportedDocumentError("not a readable image") from exc
    bgr = cv2.cvtColor(np.asarray(img), cv2.COLOR_RGB2BGR)
    return [_ocr_page(bgr, 1, languages)]


def load(data: bytes, filename: str, languages: str, max_pages: int) -> list[PageSource]:
    if data[:5] == b"%PDF-" or filename.lower().endswith(".pdf"):
        try:
            return load_pdf(data, languages, max_pages)
        except (pymupdf.FileDataError, RuntimeError) as exc:
            raise UnsupportedDocumentError("damaged PDF") from exc
    return load_image(data, languages)

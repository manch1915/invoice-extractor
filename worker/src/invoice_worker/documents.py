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
# A4 at 300 dpi is ~8.7 MP. Anything far above that is a poster or a crafted file, not an invoice.
MAX_PAGE_PIXELS = 36_000_000
MAX_ASPECT_RATIO = 12

PILImage.MAX_IMAGE_PIXELS = MAX_PAGE_PIXELS


class UnsupportedDocumentError(ValueError):
    pass


def _dpi_within_budget(page: "pymupdf.Page", wanted: int) -> int:
    width_in, height_in = page.rect.width / 72, page.rect.height / 72
    if width_in <= 0 or height_in <= 0:
        raise UnsupportedDocumentError("PDF page has no size")
    if max(width_in, height_in) / min(width_in, height_in) > MAX_ASPECT_RATIO:
        raise UnsupportedDocumentError("page proportions are not those of a document")
    budget = int((MAX_PAGE_PIXELS / (width_in * height_in)) ** 0.5)
    if budget < 72:
        raise UnsupportedDocumentError("PDF page is too large")
    return min(wanted, budget)


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
                dpi = _dpi_within_budget(page, PDF_DISPLAY_DPI)
                scale = dpi / 72
                image = _pixmap_to_bgr(page.get_pixmap(dpi=dpi, alpha=False))
                words = [
                    RawWord(text=w[4], page=number, x0=w[0] * scale, y0=w[1] * scale, x1=w[2] * scale, y1=w[3] * scale)
                    for w in raw_words
                ]
                pages.append(PageSource(number=number, image=image, words=words, from_text_layer=True))
            else:
                dpi = _dpi_within_budget(page, PDF_OCR_DPI)
                image = _pixmap_to_bgr(page.get_pixmap(dpi=dpi, alpha=False))
                pages.append(_ocr_page(image, number, languages))
    return pages


def load_image(data: bytes, languages: str) -> list[PageSource]:
    try:
        with PILImage.open(io.BytesIO(data)) as raw:
            # the header is read lazily, so the size is known before any pixels are decoded
            width, height = raw.size
            if width * height > MAX_PAGE_PIXELS or max(width, height) > min(width, height) * MAX_ASPECT_RATIO:
                raise UnsupportedDocumentError("image is too large or too narrow for a document")
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

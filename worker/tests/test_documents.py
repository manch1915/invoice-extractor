import io

import pymupdf
import pytest
from PIL import Image

from invoice_worker.documents import UnsupportedDocumentError, load


def pdf_with_page(width_pt: float, height_pt: float) -> bytes:
    doc = pymupdf.open()
    doc.new_page(width=width_pt, height=height_pt)
    data: bytes = doc.tobytes()
    doc.close()
    return data


def test_refuses_poster_sized_pdf_pages() -> None:
    with pytest.raises(UnsupportedDocumentError):
        load(pdf_with_page(72 * 200, 72 * 200), "poster.pdf", "eng", 5)


def test_refuses_absurdly_narrow_pages() -> None:
    with pytest.raises(UnsupportedDocumentError):
        load(pdf_with_page(72, 72 * 40), "strip.pdf", "eng", 5)


def test_refuses_images_that_are_too_large_before_decoding() -> None:
    buf = io.BytesIO()
    Image.new("L", (8000, 6000), 255).save(buf, format="PNG")
    with pytest.raises(UnsupportedDocumentError):
        load(buf.getvalue(), "huge.png", "eng", 5)


def test_refuses_files_that_are_not_documents() -> None:
    with pytest.raises(UnsupportedDocumentError):
        load(b"hello, not an image", "notes.txt", "eng", 5)

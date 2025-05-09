"""Image clean-up for scans and phone photos before OCR."""

from typing import Any

import cv2
import numpy as np
from numpy.typing import NDArray

Image = NDArray[Any]

MAX_OCR_PIXELS = 40_000_000
OCR_WIDTH = 2000


def order_corners(pts: NDArray[np.float32]) -> NDArray[np.float32]:
    s = pts.sum(axis=1)
    d = np.diff(pts, axis=1).ravel()
    return np.array([pts[np.argmin(s)], pts[np.argmin(d)], pts[np.argmax(s)], pts[np.argmax(d)]], dtype=np.float32)


def find_page_quad(img: Image) -> NDArray[np.float32] | None:
    """Corners of a sheet of paper photographed on a darker background, if there is one."""
    scale = 1000 / max(img.shape[:2])
    small = cv2.resize(img, None, fx=scale, fy=scale, interpolation=cv2.INTER_AREA)
    gray = cv2.cvtColor(small, cv2.COLOR_BGR2GRAY)
    gray = cv2.GaussianBlur(gray, (5, 5), 0)
    _, mask = cv2.threshold(gray, 0, 255, cv2.THRESH_BINARY + cv2.THRESH_OTSU)
    mask = cv2.morphologyEx(mask, cv2.MORPH_CLOSE, np.ones((15, 15), np.uint8))
    contours, _ = cv2.findContours(mask, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)
    if not contours:
        return None

    page = max(contours, key=cv2.contourArea)
    area = cv2.contourArea(page)
    frame = small.shape[0] * small.shape[1]
    # Paper that already fills the frame is a scan, not a photo.
    if area < frame * 0.25 or area > frame * 0.97:
        return None

    approx = cv2.approxPolyDP(page, 0.02 * cv2.arcLength(page, True), True)
    if len(approx) != 4:
        hull = cv2.convexHull(page)
        approx = cv2.approxPolyDP(hull, 0.04 * cv2.arcLength(hull, True), True)
        if len(approx) != 4:
            return None
    return order_corners(approx.reshape(4, 2).astype(np.float32) / scale)


def warp_to_quad(img: Image, quad: NDArray[np.float32]) -> Image:
    tl, tr, br, bl = quad
    width = int(max(np.linalg.norm(br - bl), np.linalg.norm(tr - tl)))
    height = int(max(np.linalg.norm(tr - br), np.linalg.norm(tl - bl)))
    target = np.array([[0, 0], [width - 1, 0], [width - 1, height - 1], [0, height - 1]], dtype=np.float32)
    matrix = cv2.getPerspectiveTransform(quad, target)
    return cv2.warpPerspective(img, matrix, (width, height), flags=cv2.INTER_CUBIC, borderValue=(255, 255, 255))


def skew_angle(gray: Image, limit: float = 6.0, step: float = 0.25) -> float:
    """Angle that makes text rows most horizontal, via the projection profile."""
    scale = 900 / max(gray.shape[:2])
    small = cv2.resize(gray, None, fx=scale, fy=scale, interpolation=cv2.INTER_AREA)
    ink = cv2.threshold(small, 0, 255, cv2.THRESH_BINARY_INV + cv2.THRESH_OTSU)[1]
    h, w = ink.shape
    center = (w / 2, h / 2)

    best_angle, best_score = 0.0, -1.0
    for angle in np.arange(-limit, limit + step, step):
        matrix = cv2.getRotationMatrix2D(center, float(angle), 1.0)
        rotated = cv2.warpAffine(ink, matrix, (w, h), flags=cv2.INTER_NEAREST)
        profile = rotated.sum(axis=1, dtype=np.float64)
        score = float(np.var(np.diff(profile)))
        if score > best_score:
            best_angle, best_score = float(angle), score
    return best_angle


def rotate(img: Image, angle: float) -> Image:
    if abs(angle) < 0.1:
        return img
    h, w = img.shape[:2]
    matrix = cv2.getRotationMatrix2D((w / 2, h / 2), angle, 1.0)
    return cv2.warpAffine(img, matrix, (w, h), flags=cv2.INTER_CUBIC, borderValue=(255, 255, 255))


def flatten_light(gray: Image) -> Image:
    """Divide out uneven lighting and shadows so the page background becomes white."""
    # the background varies slowly, so estimate it on a small copy and scale it back up
    small = cv2.resize(gray, None, fx=0.25, fy=0.25, interpolation=cv2.INTER_AREA)
    kernel = max(9, (min(small.shape[:2]) // 25) | 1)
    background = cv2.medianBlur(small, kernel)
    background = cv2.resize(background, (gray.shape[1], gray.shape[0]), interpolation=cv2.INTER_LINEAR)
    return cv2.divide(gray, background, scale=255)


def straighten(img: Image) -> tuple[Image, bool]:
    """Perspective-correct a photo and remove small rotation. Returns (image, was_photo)."""
    quad = find_page_quad(img)
    photo = quad is not None
    if quad is not None:
        img = warp_to_quad(img, quad)
    gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
    return rotate(img, skew_angle(gray)), photo


def for_ocr(img: Image) -> Image:
    gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
    # About 2000 px across an A4 page gives Tesseract ~30 px capitals: smaller loses letters,
    # larger only costs time.
    factor = min(OCR_WIDTH / gray.shape[1], 3.0, (MAX_OCR_PIXELS / gray.size) ** 0.5)
    if abs(factor - 1) > 0.05:
        interpolation = cv2.INTER_CUBIC if factor > 1 else cv2.INTER_AREA
        gray = cv2.resize(gray, None, fx=factor, fy=factor, interpolation=interpolation)
    gray = cv2.medianBlur(flatten_light(gray), 3)
    # scanner noise turns into thousands of tiny blobs; a clean black-and-white page reads ~3x faster
    binary = cv2.threshold(gray, 0, 255, cv2.THRESH_BINARY + cv2.THRESH_OTSU)[1]
    return remove_rules(binary)


def remove_rules(binary: Image) -> Image:
    """Erase table borders. Tesseract reads them as | [ ] and loses the header row next to them."""
    ink = cv2.bitwise_not(binary)
    h, w = ink.shape
    # kernels longer than any letter stroke (text is ~30-50 px tall here), shorter than a table border
    horizontal = cv2.morphologyEx(ink, cv2.MORPH_OPEN, cv2.getStructuringElement(cv2.MORPH_RECT, (max(80, w // 20), 1)))
    vertical = cv2.morphologyEx(ink, cv2.MORPH_OPEN, cv2.getStructuringElement(cv2.MORPH_RECT, (1, max(80, h // 30))))
    rules = cv2.dilate(cv2.bitwise_or(horizontal, vertical), np.ones((3, 3), np.uint8))
    return cv2.bitwise_not(cv2.bitwise_and(ink, cv2.bitwise_not(rules)))


def fit_width(img: Image, width: int) -> tuple[Image, float]:
    if img.shape[1] <= width:
        return img, 1.0
    factor = width / img.shape[1]
    return cv2.resize(img, None, fx=factor, fy=factor, interpolation=cv2.INTER_AREA), factor

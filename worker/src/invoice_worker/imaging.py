"""Image clean-up for scans and phone photos before OCR."""

from typing import Any

import cv2
import numpy as np
from numpy.typing import NDArray

Image = NDArray[Any]


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
    kernel = max(31, (min(gray.shape[:2]) // 25) | 1)
    background = cv2.medianBlur(gray, kernel)
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
    gray = flatten_light(gray)
    # Tesseract likes capital letters around 30 px tall; phone photos of A4 are often smaller.
    if gray.shape[1] < 1800:
        factor = 1800 / gray.shape[1]
        gray = cv2.resize(gray, None, fx=factor, fy=factor, interpolation=cv2.INTER_CUBIC)
    return cv2.fastNlMeansDenoising(gray, h=12)


def fit_width(img: Image, width: int) -> tuple[Image, float]:
    if img.shape[1] <= width:
        return img, 1.0
    factor = width / img.shape[1]
    return cv2.resize(img, None, fx=factor, fy=factor, interpolation=cv2.INTER_AREA), factor

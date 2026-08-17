from io import BytesIO

try:
    import pytesseract  # noqa: F401
    from PIL import Image  # noqa: F401

    OCR_AVAILABLE = True
except ImportError:
    OCR_AVAILABLE = False


class OcrUnavailableError(RuntimeError):
    pass


def ocr_image_bytes(data: bytes) -> str:
    if not OCR_AVAILABLE:
        raise OcrUnavailableError("OCR engine unavailable (install pytesseract + tesseract)")
    import pytesseract
    from PIL import Image

    return pytesseract.image_to_string(Image.open(BytesIO(data)))
from io import BytesIO

from pypdf import PdfReader

from app.schemas.extraction import ExtractionMethod
from app.services.extraction.ocr import OCR_AVAILABLE, OcrUnavailableError, ocr_image_bytes


class ExtractionResult:
    def __init__(self, text: str, method: ExtractionMethod, warnings: list[str] | None = None):
        self.text = text
        self.method = method
        self.warnings = warnings or []


def extract_pdf(data: bytes) -> ExtractionResult:
    reader = PdfReader(BytesIO(data))
    text = "\n".join(_safe_page_text(page) for page in reader.pages).strip()
    if text:
        return ExtractionResult(text, ExtractionMethod.PDF_TEXT)

    warnings = ["No extractable text layer in PDF"]
    images = [img.data for page in reader.pages for img in (getattr(page, "images", None) or [])]
    if not images:
        warnings.append("PDF contains no embedded images")
        return ExtractionResult("", ExtractionMethod.NONE, warnings)
    if not OCR_AVAILABLE:
        warnings.append("OCR engine unavailable (install tesseract)")
        return ExtractionResult("", ExtractionMethod.NONE, warnings)
    try:
        text = "\n".join(ocr_image_bytes(img) for img in images).strip()
    except OcrUnavailableError:
        warnings.append("OCR engine unavailable (install tesseract)")
        return ExtractionResult("", ExtractionMethod.NONE, warnings)
    if not text:
        warnings.append("OCR produced no text")
        return ExtractionResult("", ExtractionMethod.NONE, warnings)
    return ExtractionResult(text, ExtractionMethod.OCR, warnings)


def extract_image(data: bytes) -> ExtractionResult:
    if not OCR_AVAILABLE:
        return ExtractionResult("", ExtractionMethod.NONE, ["OCR engine unavailable (install tesseract)"])
    try:
        text = ocr_image_bytes(data).strip()
    except OcrUnavailableError:
        return ExtractionResult("", ExtractionMethod.NONE, ["OCR engine unavailable (install tesseract)"])
    if not text:
        return ExtractionResult("", ExtractionMethod.NONE, ["OCR produced no text"])
    return ExtractionResult(text, ExtractionMethod.OCR, [])


def _safe_page_text(page) -> str:
    try:
        return page.extract_text() or ""
    except Exception:
        return ""
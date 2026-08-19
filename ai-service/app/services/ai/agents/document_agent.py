"""Document Agent — wraps the existing text/OCR extractors.

Deterministic on the happy path; no LLM required for extraction.
"""

from dataclasses import dataclass
from pathlib import Path

from app.schemas.extraction import DocumentType, ExtractionMethod
from app.services.extraction.lab_parser import detect_document_type
from app.services.extraction.text_extractor import extract_image, extract_pdf

_IMAGE_SUFFIXES = {".png", ".jpg", ".jpeg", ".webp", ".bmp", ".tif", ".tiff"}


class UnsupportedFileTypeError(ValueError):
    """The uploaded file is neither a PDF nor a supported image type."""


@dataclass
class DocumentExtraction:
    text: str
    method: ExtractionMethod
    warnings: list[str]
    document_type: DocumentType


class DocumentAgent:
    def extract(self, data: bytes, filename: str | None) -> DocumentExtraction:
        suffix = Path(filename or "").suffix.lower()
        if suffix == ".pdf":
            result = extract_pdf(data)
        elif suffix in _IMAGE_SUFFIXES:
            result = extract_image(data)
        else:
            raise UnsupportedFileTypeError("Unsupported file type; expected a PDF or image")

        return DocumentExtraction(
            text=result.text,
            method=result.method,
            warnings=result.warnings,
            document_type=detect_document_type(result.text, filename),
        )
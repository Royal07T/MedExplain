from pathlib import Path

from fastapi import APIRouter, Depends, File, HTTPException, UploadFile, status

from app.core.config import get_settings
from app.core.security import require_service_key
from app.schemas.extraction import ExtractionMethod, ExtractionResponse, ParseLabReportRequest
from app.services.extraction.lab_parser import detect_document_type, parse_lab_report
from app.services.extraction.text_extractor import extract_image, extract_pdf

router = APIRouter(dependencies=[Depends(require_service_key)])

_IMAGE_SUFFIXES = {".png", ".jpg", ".jpeg", ".webp", ".bmp", ".tif", ".tiff"}


@router.post("/documents/extract", response_model=ExtractionResponse)
async def extract_document(file: UploadFile = File(...)) -> ExtractionResponse:
    settings = get_settings()
    data = await file.read()
    if len(data) > settings.max_upload_mb * 1024 * 1024:
        raise HTTPException(
            status_code=status.HTTP_413_CONTENT_TOO_LARGE,
            detail="File exceeds the size limit",
        )

    suffix = Path(file.filename or "").suffix.lower()
    if suffix == ".pdf":
        result = extract_pdf(data)
    elif suffix in _IMAGE_SUFFIXES:
        result = extract_image(data)
    else:
        raise HTTPException(
            status_code=status.HTTP_415_UNSUPPORTED_MEDIA_TYPE,
            detail="Unsupported file type; expected a PDF or image",
        )

    return ExtractionResponse(
        document_type=detect_document_type(result.text, file.filename),
        extraction_method=result.method,
        raw_text=result.text,
        lab_tests=[],
        warnings=result.warnings,
    )


@router.post("/documents/parse-lab-report", response_model=ExtractionResponse)
def parse_lab_report_endpoint(request: ParseLabReportRequest) -> ExtractionResponse:
    return ExtractionResponse(
        document_type=request.document_type or detect_document_type(request.raw_text),
        extraction_method=request.extraction_method or ExtractionMethod.NONE,
        raw_text=request.raw_text,
        lab_tests=parse_lab_report(request.raw_text),
    )
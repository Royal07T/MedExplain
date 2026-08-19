from fastapi import APIRouter, Depends, File, HTTPException, UploadFile, status

from app.core.config import get_settings
from app.core.security import require_service_key
from app.schemas.extraction import ExtractionResponse, ParseLabReportRequest
from app.services.ai.agents import UnsupportedFileTypeError
from app.services.ai.orchestrator import get_orchestrator

router = APIRouter(dependencies=[Depends(require_service_key)])


@router.post("/documents/extract", response_model=ExtractionResponse)
async def extract_document(file: UploadFile = File(...)) -> ExtractionResponse:
    settings = get_settings()
    data = await file.read()
    if len(data) > settings.max_upload_mb * 1024 * 1024:
        raise HTTPException(
            status_code=status.HTTP_413_CONTENT_TOO_LARGE,
            detail="File exceeds the size limit",
        )

    orchestrator = get_orchestrator()
    try:
        return orchestrator.extract_document(data, file.filename)
    except UnsupportedFileTypeError as exc:
        raise HTTPException(
            status_code=status.HTTP_415_UNSUPPORTED_MEDIA_TYPE,
            detail=str(exc),
        ) from exc


@router.post("/documents/parse-lab-report", response_model=ExtractionResponse)
def parse_lab_report_endpoint(request: ParseLabReportRequest) -> ExtractionResponse:
    orchestrator = get_orchestrator()
    return orchestrator.parse_lab_report(
        request.raw_text,
        document_type=request.document_type,
        extraction_method=request.extraction_method,
    )
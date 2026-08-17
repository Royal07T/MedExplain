from fastapi import APIRouter

from app.core.config import get_settings
from app.services.extraction.ocr import OCR_AVAILABLE

router = APIRouter()


@router.get("/health")
def health() -> dict:
    settings = get_settings()
    return {
        "status": "ok",
        "version": settings.service_version,
        "dependencies": {
            "ocr": OCR_AVAILABLE,
            "llm_provider": settings.llm_provider,
        },
    }
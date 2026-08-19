from fastapi import APIRouter, Depends

from app.core.security import require_service_key
from app.schemas.medication import MedicationExtraction, MedicationExtractionRequest
from app.services.ai.orchestrator import get_orchestrator

router = APIRouter(dependencies=[Depends(require_service_key)])


@router.post("/medications/extract", response_model=MedicationExtraction)
async def extract_medications(payload: MedicationExtractionRequest) -> MedicationExtraction:
    orchestrator = get_orchestrator()
    return await orchestrator.extract_medications(
        payload.raw_text,
        llm_fallback=payload.llm_fallback,
    )
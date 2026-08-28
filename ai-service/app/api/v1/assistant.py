from fastapi import APIRouter, Depends

from app.core.security import require_service_key
from app.schemas.assistant import SymptomCheckRequest, SymptomCheckResponse
from app.services.ai.symptom_service import get_symptom_service

router = APIRouter(dependencies=[Depends(require_service_key)])


@router.post("/assistant/symptom-check", response_model=SymptomCheckResponse)
def symptom_check(request: SymptomCheckRequest) -> SymptomCheckResponse:
    return get_symptom_service().check(request)

from fastapi import APIRouter, Depends

from app.core.security import require_service_key
from app.schemas.predictive import (
    DeteriorationRequest,
    DeteriorationResponse,
    LengthOfStayRequest,
    LengthOfStayResponse,
    ReadmissionRequest,
    ReadmissionResponse,
)
from app.services.ai.predictive_service import PredictiveService

router = APIRouter(dependencies=[Depends(require_service_key)])


@router.post("/predictive/readmission", response_model=ReadmissionResponse)
def predict_readmission(request: ReadmissionRequest) -> ReadmissionResponse:
    return PredictiveService().readmission_risk(request)


@router.post("/predictive/length-of-stay", response_model=LengthOfStayResponse)
def predict_length_of_stay(request: LengthOfStayRequest) -> LengthOfStayResponse:
    return PredictiveService().predict_length_of_stay(request)


@router.post("/predictive/deterioration", response_model=DeteriorationResponse)
def predict_deterioration(request: DeteriorationRequest) -> DeteriorationResponse:
    return PredictiveService().deterioration_risk(request)

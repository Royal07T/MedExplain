from fastapi import APIRouter, Depends

from app.core.security import require_service_key
from app.schemas.imaging import ImagingAnalysisRequest, ImagingAnalysisResponse
from app.services.ai.imaging_service import ImagingService

router = APIRouter(dependencies=[Depends(require_service_key)])


@router.post("/imaging/analyze", response_model=ImagingAnalysisResponse)
def analyze_imaging_order(request: ImagingAnalysisRequest) -> ImagingAnalysisResponse:
    return ImagingService().analyze(request)

from fastapi import APIRouter, Depends

from app.core.security import require_service_key
from app.schemas.health_query import HealthQueryRequest, HealthQueryResponse
from app.services.ai.health_intelligence import get_health_intelligence_service

router = APIRouter(dependencies=[Depends(require_service_key)])


@router.post("/health/query", response_model=HealthQueryResponse)
async def health_query(request: HealthQueryRequest) -> HealthQueryResponse:
    service = get_health_intelligence_service()
    return await service.answer(request)

from fastapi import APIRouter, Depends

from app.core.security import require_service_key
from app.schemas.analysis import AiAnalysis, ExplainRequest
from app.services.ai.orchestrator import get_orchestrator

router = APIRouter(dependencies=[Depends(require_service_key)])


@router.post("/analysis/explain", response_model=AiAnalysis)
async def explain(request: ExplainRequest) -> AiAnalysis:
    orchestrator = get_orchestrator()
    return await orchestrator.explain(request)
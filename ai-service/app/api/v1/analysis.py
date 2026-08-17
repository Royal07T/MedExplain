from fastapi import APIRouter, Depends

from app.core.security import require_service_key
from app.schemas.analysis import AiAnalysis, ExplainRequest
from app.services.llm.factory import get_llm_provider

router = APIRouter(dependencies=[Depends(require_service_key)])


@router.post("/analysis/explain", response_model=AiAnalysis)
async def explain(request: ExplainRequest) -> AiAnalysis:
    provider = get_llm_provider()
    return await provider.explain(request.document_type, request.raw_text, request.lab_tests)
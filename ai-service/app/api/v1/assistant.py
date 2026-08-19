from fastapi import APIRouter, Depends

from app.core.security import require_service_key
from app.schemas.assistant import AssistantRequest, AssistantResponse
from app.services.ai.orchestrator import get_orchestrator

router = APIRouter(dependencies=[Depends(require_service_key)])


@router.post("/assistant/chat", response_model=AssistantResponse)
async def chat(request: AssistantRequest) -> AssistantResponse:
    orchestrator = get_orchestrator()
    return await orchestrator.chat(request)
from fastapi import APIRouter

from app.api.v1 import (
    analysis,
    assistant,
    documents,
    health,
    health_query,
    medications,
    nlp,
    predictive,
)

api_router = APIRouter()
api_router.include_router(health.router, tags=["health"])
api_router.include_router(documents.router, tags=["documents"])
api_router.include_router(analysis.router, tags=["analysis"])
api_router.include_router(medications.router, tags=["medications"])
api_router.include_router(health_query.router, tags=["health-query"])
api_router.include_router(nlp.router, tags=["nlp"])
api_router.include_router(predictive.router, tags=["predictive"])
api_router.include_router(assistant.router, tags=["assistant"])
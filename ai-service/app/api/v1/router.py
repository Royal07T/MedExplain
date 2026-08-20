from fastapi import APIRouter

from app.api.v1 import analysis, documents, health, health_query, medications

api_router = APIRouter()
api_router.include_router(health.router, tags=["health"])
api_router.include_router(documents.router, tags=["documents"])
api_router.include_router(analysis.router, tags=["analysis"])
api_router.include_router(medications.router, tags=["medications"])
api_router.include_router(health_query.router, tags=["health-query"])
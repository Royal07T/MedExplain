from fastapi import APIRouter

from app.api.v1 import analysis, documents, health

api_router = APIRouter()
api_router.include_router(health.router, tags=["health"])
api_router.include_router(documents.router, tags=["documents"])
api_router.include_router(analysis.router, tags=["analysis"])
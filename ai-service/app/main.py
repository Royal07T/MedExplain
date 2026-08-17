from fastapi import FastAPI

from app.api.v1.router import api_router
from app.core.config import get_settings

settings = get_settings()

app = FastAPI(title="MedExplain AI Service", version=settings.service_version)
app.include_router(api_router, prefix="/api/v1")
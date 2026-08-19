from fastapi import FastAPI, Request
from fastapi.responses import JSONResponse

from app.api.v1.router import api_router
from app.core.config import get_settings
from app.services.llm.gateway.errors import (
    GatewayFallbackError,
    ProviderAuthError,
    ProviderError,
)

settings = get_settings()

app = FastAPI(title="MedExplain AI Service", version=settings.service_version)
app.include_router(api_router, prefix="/api/v1")


@app.exception_handler(GatewayFallbackError)
async def gateway_fallback_handler(request: Request, exc: GatewayFallbackError) -> JSONResponse:
    return JSONResponse(status_code=503, content={"detail": "AI service is temporarily unavailable"})


@app.exception_handler(ProviderAuthError)
async def provider_auth_handler(request: Request, exc: ProviderAuthError) -> JSONResponse:
    return JSONResponse(status_code=502, content={"detail": "AI provider configuration error"})


@app.exception_handler(ProviderError)
async def provider_error_handler(request: Request, exc: ProviderError) -> JSONResponse:
    return JSONResponse(status_code=502, content={"detail": "AI provider request failed"})
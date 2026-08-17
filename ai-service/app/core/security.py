import secrets

from fastapi import Header, HTTPException, status

from app.core.config import get_settings


async def require_service_key(
    x_service_key: str | None = Header(default=None, alias="X-Service-Key"),
) -> None:
    settings = get_settings()
    if x_service_key is None or not secrets.compare_digest(x_service_key, settings.fastapi_api_key):
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Invalid service key",
        )
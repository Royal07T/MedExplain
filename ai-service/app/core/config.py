from functools import lru_cache

from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    model_config = SettingsConfigDict(
        env_file=".env",
        env_file_encoding="utf-8",
        extra="ignore",
    )

    fastapi_api_key: str = "dev-secret-change-me"
    llm_provider: str = "stub"
    openai_api_key: str | None = None
    openai_model: str = "gpt-4o-mini"
    max_upload_mb: int = 10
    service_version: str = "0.1.0"


@lru_cache
def get_settings() -> Settings:
    return Settings()
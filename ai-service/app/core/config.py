from functools import lru_cache

from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    model_config = SettingsConfigDict(
        env_file=".env",
        env_file_encoding="utf-8",
        extra="ignore",
    )

    fastapi_api_key: str = "dev-secret-change-me"

    # LLM provider selection ("stub" | "openai" | "openrouter" | "agentrouter").
    llm_provider: str = "stub"

    # Provider credentials and endpoints. Provider-specific values fall back to
    # the openai_* equivalents for backward compatibility.
    openai_api_key: str | None = None
    openai_model: str = "gpt-4o-mini"
    openai_base_url: str = "https://api.openai.com/v1"

    openrouter_api_key: str | None = None
    openrouter_model: str | None = None
    openrouter_base_url: str | None = None

    agentrouter_api_key: str | None = None
    agentrouter_model: str | None = None
    agentrouter_base_url: str | None = None

    # Gateway tuning (see docs/ai-architecture-blueprint.md §4.7).
    llm_timeout: float = 30.0
    llm_max_retries: int = 2
    llm_retry_backoff: float = 1.5
    llm_fallback: str = "stub"
    llm_routing: str = ""

    max_upload_mb: int = 10
    service_version: str = "0.1.0"


@lru_cache
def get_settings() -> Settings:
    return Settings()
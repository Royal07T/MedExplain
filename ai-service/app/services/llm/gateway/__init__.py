"""Generic LLM gateway — provider-agnostic chat + structured output.

Public surface:
- :class:`LLMGateway` — routing, retry, fallback entry point.
- :func:`build_gateway_config` — assemble config from settings/env.
- Typed errors in :mod:`app.services.llm.gateway.errors`.
"""

from app.services.llm.gateway.client import LLMGateway
from app.services.llm.gateway.config import (
    GatewayConfig,
    ProviderConfig,
    build_gateway_config,
)
from app.services.llm.gateway.errors import (
    GatewayConfigError,
    GatewayFallbackError,
    ProviderAuthError,
    ProviderConnectionError,
    ProviderError,
    ProviderRateLimitError,
    ProviderResponseError,
    SchemaValidationError,
)
from app.services.llm.gateway.types import (
    ChatMessage,
    ChatModel,
    ChatResponse,
    GatewayEvent,
    Role,
    Usage,
)

__all__ = [
    "GatewayConfig",
    "ProviderConfig",
    "build_gateway_config",
    "LLMGateway",
    "GatewayConfigError",
    "GatewayFallbackError",
    "ProviderAuthError",
    "ProviderConnectionError",
    "ProviderError",
    "ProviderRateLimitError",
    "ProviderResponseError",
    "SchemaValidationError",
    "ChatMessage",
    "ChatModel",
    "ChatResponse",
    "GatewayEvent",
    "Role",
    "Usage",
]
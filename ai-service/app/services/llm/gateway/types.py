"""Core gateway types.

These are plain dataclasses shared by providers and the client. API keys are
never logged, persisted, or included in any emitted event metadata.
"""

from dataclasses import dataclass
from enum import Enum


class Role(str, Enum):
    SYSTEM = "system"
    USER = "user"
    ASSISTANT = "assistant"


@dataclass(frozen=True)
class ChatMessage:
    role: Role
    content: str

    def to_dict(self) -> dict[str, str]:
        return {"role": self.role.value, "content": self.content}


@dataclass(frozen=True)
class ChatModel:
    provider: str
    model: str
    base_url: str = ""
    api_key: str | None = None
    temperature: float = 0.2
    max_tokens: int | None = None


@dataclass(frozen=True)
class Usage:
    prompt_tokens: int = 0
    completion_tokens: int = 0


@dataclass(frozen=True)
class ChatResponse:
    content: str
    model: str
    usage: Usage | None = None


@dataclass(frozen=True)
class GatewayEvent:
    """Observability metadata. Never contains message content or API keys."""

    provider: str
    model: str
    task: str
    latency_ms: int
    usage: Usage | None = None
    error: str | None = None
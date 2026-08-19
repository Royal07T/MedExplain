"""Provider protocol shared by every LLM backend."""

from abc import ABC, abstractmethod

from pydantic import BaseModel

from app.services.llm.gateway.types import ChatMessage, ChatModel, ChatResponse


class LLMProvider(ABC):
    @abstractmethod
    async def chat(
        self,
        messages: list[ChatMessage],
        model: ChatModel,
    ) -> ChatResponse:
        """Plain-text chat completion."""

    @abstractmethod
    async def generate_json(
        self,
        messages: list[ChatMessage],
        model: ChatModel,
        response_schema: type[BaseModel],
    ) -> BaseModel:
        """Structured output validated against ``response_schema``."""
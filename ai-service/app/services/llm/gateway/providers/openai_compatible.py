"""OpenAI-compatible chat completions provider.

Serves OpenAI, OpenRouter, and AgentRouter from configuration alone. Errors are
mapped to typed exceptions; request bodies, keys, and contents are never logged.
"""

import json

import httpx
from pydantic import BaseModel, ValidationError

from app.services.llm.gateway.errors import (
    ProviderAuthError,
    ProviderConnectionError,
    ProviderRateLimitError,
    ProviderResponseError,
    SchemaValidationError,
)
from app.services.llm.gateway.schemas import WireChatResponse
from app.services.llm.gateway.types import ChatMessage, ChatModel, ChatResponse, Usage
from app.services.llm.gateway.providers.base import LLMProvider


class OpenAICompatibleProvider(LLMProvider):
    def __init__(
        self,
        *,
        timeout: float = 30.0,
        supports_json: bool = True,
        transport: httpx.AsyncBaseTransport | None = None,
    ):
        self._timeout = timeout
        self._supports_json = supports_json
        self._transport = transport

    @staticmethod
    def _endpoint(model: ChatModel) -> str:
        return f"{model.base_url.rstrip('/')}/chat/completions"

    async def _post(
        self,
        messages: list[ChatMessage],
        model: ChatModel,
        *,
        json_mode: bool,
    ) -> WireChatResponse:
        payload = {
            "model": model.model,
            "temperature": model.temperature,
            "messages": [message.to_dict() for message in messages],
        }
        if model.max_tokens is not None:
            payload["max_tokens"] = model.max_tokens
        if json_mode and self._supports_json:
            payload["response_format"] = {"type": "json_object"}

        headers = {}
        if model.api_key:
            headers["Authorization"] = f"Bearer {model.api_key}"

        try:
            async with httpx.AsyncClient(timeout=self._timeout, transport=self._transport) as client:
                response = await client.post(
                    self._endpoint(model),
                    json=payload,
                    headers=headers,
                )
        except httpx.TimeoutException as exc:
            raise ProviderConnectionError(f"timeout calling {model.provider}") from exc
        except httpx.TransportError as exc:
            raise ProviderConnectionError(f"connection error calling {model.provider}") from exc

        if response.status_code in (401, 403):
            raise ProviderAuthError(f"{model.provider} rejected the request credentials")
        if response.status_code == 429:
            raise ProviderRateLimitError(f"{model.provider} rate limited the request")
        if response.status_code >= 400:
            raise ProviderResponseError(
                f"{model.provider} returned HTTP {response.status_code}"
            )

        try:
            return WireChatResponse.model_validate(response.json())
        except (ValueError, ValidationError) as exc:
            raise ProviderResponseError(f"{model.provider} returned a malformed response") from exc

    async def chat(
        self,
        messages: list[ChatMessage],
        model: ChatModel,
    ) -> ChatResponse:
        wire = await self._post(messages, model, json_mode=False)
        content = wire.choices[0].message.content
        usage = None
        if wire.usage is not None:
            usage = Usage(
                prompt_tokens=wire.usage.prompt_tokens,
                completion_tokens=wire.usage.completion_tokens,
            )
        return ChatResponse(
            content=content,
            model=wire.model or model.model,
            usage=usage,
        )

    async def generate_json(
        self,
        messages: list[ChatMessage],
        model: ChatModel,
        response_schema: type[BaseModel],
    ) -> BaseModel:
        wire = await self._post(messages, model, json_mode=True)
        content = wire.choices[0].message.content

        try:
            data = json.loads(content)
        except json.JSONDecodeError as exc:
            raise SchemaValidationError(
                raw_content=content,
                errors=["response was not valid JSON"],
            ) from exc

        try:
            return response_schema.model_validate(data)
        except ValidationError as exc:
            raise SchemaValidationError(
                raw_content=content,
                errors=[str(error) for error in exc.errors()],
            ) from exc
"""LLMGateway — the single entry point for all model calls.

Responsible for task routing, transient-retry with exponential backoff, ordered
provider fallback, and bounded re-request on schema-validation failure. Emits
observability metadata (never content or keys).
"""

import asyncio
import time

from pydantic import BaseModel

from app.services.llm.gateway.config import GatewayConfig
from app.services.llm.gateway.errors import (
    GatewayConfigError,
    GatewayFallbackError,
    ProviderConnectionError,
    ProviderRateLimitError,
    ProviderResponseError,
    SchemaValidationError,
)
from app.services.llm.gateway.providers.base import LLMProvider
from app.services.llm.gateway.types import (
    ChatMessage,
    ChatModel,
    ChatResponse,
    GatewayEvent,
    Role,
)

TRANSIENT_ERRORS = (ProviderConnectionError, ProviderRateLimitError)


class LLMGateway:
    def __init__(self, config: GatewayConfig):
        self._config = config
        self._providers: dict[str, LLMProvider] = {
            name: self._build_provider(name) for name in self._all_provider_names()
        }

    @property
    def config(self) -> GatewayConfig:
        return self._config

    def _all_provider_names(self) -> set[str]:
        names = {self._config.default_provider, *self._config.fallback_order}
        names.update(model.provider for model in self._config.routing.values())
        return names

    def _build_provider(self, name: str) -> LLMProvider:
        from app.services.llm.gateway.providers.openai_compatible import (
            OpenAICompatibleProvider,
        )
        from app.services.llm.gateway.providers.stub import StubProvider

        if name == "stub":
            return StubProvider()
        provider_cfg = self._config.providers.get(name)
        if provider_cfg is None:
            raise GatewayConfigError(f"gateway references unknown provider: {name}")
        return OpenAICompatibleProvider(
            timeout=self._config.timeout,
            supports_json=provider_cfg.supports_json,
        )

    def _resolve_model(self, task: str) -> ChatModel:
        routed = self._config.routing.get(task)
        if routed is not None:
            return routed
        provider_cfg = self._config.providers[self._config.default_provider]
        return ChatModel(
            provider=self._config.default_provider,
            model=provider_cfg.model or "stub",
            base_url=provider_cfg.base_url,
            api_key=provider_cfg.api_key,
            temperature=provider_cfg.temperature,
        )

    def _ordered(self, primary_name: str) -> list[str]:
        order = [primary_name]
        for name in self._config.fallback_order:
            if name not in order and name in self._providers:
                order.append(name)
        return order

    def _model_for(self, name: str, primary: ChatModel) -> ChatModel:
        if name == primary.provider:
            return primary
        provider_cfg = self._config.providers.get(name)
        if provider_cfg is None:
            return primary
        return ChatModel(
            provider=name,
            model=provider_cfg.model or primary.model,
            base_url=provider_cfg.base_url,
            api_key=provider_cfg.api_key,
            temperature=provider_cfg.temperature or primary.temperature,
        )

    def _emit(self, event: GatewayEvent) -> None:
        self._config.emit(event)

    async def chat(self, messages: list[ChatMessage], *, task: str = "default") -> ChatResponse:
        primary = self._resolve_model(task)
        last_error: BaseException | None = None
        for name in self._ordered(primary.provider):
            provider = self._providers[name]
            model = self._model_for(name, primary)
            attempts = 0
            while True:
                attempts += 1
                started = time.monotonic()
                try:
                    response = await provider.chat(messages, model)
                    self._emit(
                        GatewayEvent(
                            provider=name,
                            model=model.model,
                            task=task,
                            latency_ms=self._latency_ms(started),
                            usage=response.usage,
                        )
                    )
                    return response
                except TRANSIENT_ERRORS as exc:
                    last_error = exc
                    self._emit(self._failure(name, model, task, started, exc))
                    if attempts > self._config.max_retries:
                        break
                    await self._backoff(attempts)
                except ProviderResponseError as exc:
                    last_error = exc
                    self._emit(self._failure(name, model, task, started, exc))
                    break
        raise GatewayFallbackError(
            f"all providers failed for task '{task}'"
        ) from last_error

    async def generate_json(
        self,
        messages: list[ChatMessage],
        *,
        task: str = "default",
        response_schema: type[BaseModel],
    ) -> BaseModel:
        primary = self._resolve_model(task)
        last_error: BaseException | None = None
        for name in self._ordered(primary.provider):
            provider = self._providers[name]
            model = self._model_for(name, primary)
            current = list(messages)
            attempts = 0
            while True:
                attempts += 1
                started = time.monotonic()
                try:
                    result = await provider.generate_json(current, model, response_schema)
                    self._emit(
                        GatewayEvent(
                            provider=name,
                            model=model.model,
                            task=task,
                            latency_ms=self._latency_ms(started),
                        )
                    )
                    return result
                except SchemaValidationError as exc:
                    last_error = exc
                    self._emit(self._failure(name, model, task, started, exc))
                    if attempts > self._config.max_retries:
                        break
                    current = current + self._schema_feedback(exc)
                except TRANSIENT_ERRORS as exc:
                    last_error = exc
                    self._emit(self._failure(name, model, task, started, exc))
                    if attempts > self._config.max_retries:
                        break
                    await self._backoff(attempts)
                except ProviderResponseError as exc:
                    last_error = exc
                    self._emit(self._failure(name, model, task, started, exc))
                    break
        raise GatewayFallbackError(
            f"all providers failed for task '{task}'"
        ) from last_error

    @staticmethod
    def _latency_ms(started: float) -> int:
        return int((time.monotonic() - started) * 1000)

    @staticmethod
    def _failure(
        name: str,
        model: ChatModel,
        task: str,
        started: float,
        error: BaseException,
    ) -> GatewayEvent:
        return GatewayEvent(
            provider=name,
            model=model.model,
            task=task,
            latency_ms=int((time.monotonic() - started) * 1000),
            error=type(error).__name__,
        )

    @staticmethod
    def _schema_feedback(exc: SchemaValidationError) -> list[ChatMessage]:
        messages: list[ChatMessage] = []
        if exc.raw_content:
            messages.append(ChatMessage(role=Role.ASSISTANT, content=exc.raw_content))
        errors = ", ".join(exc.errors) if exc.errors else "unknown schema mismatch"
        messages.append(
            ChatMessage(
                role=Role.USER,
                content=(
                    "Your previous response did not match the required schema "
                    f"(errors: {errors}). Return strict JSON matching the schema exactly."
                ),
            )
        )
        return messages

    async def _backoff(self, attempt: int) -> None:
        await asyncio.sleep(self._config.retry_backoff ** attempt)
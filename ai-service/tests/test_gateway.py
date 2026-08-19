import asyncio

import pytest
from pydantic import BaseModel

from app.services.llm.gateway import (
    GatewayConfig,
    GatewayFallbackError,
    LLMGateway,
    ProviderConfig,
)
from app.services.llm.gateway.errors import (
    ProviderConnectionError,
    ProviderResponseError,
    SchemaValidationError,
)
from app.services.llm.gateway.providers.base import LLMProvider
from app.services.llm.gateway.types import ChatMessage, ChatModel, ChatResponse, Role


class Demo(BaseModel):
    summary: str
    notes: list[str] = []


class FakeProvider(LLMProvider):
    """Scripted provider for gateway routing/retry/fallback tests."""

    def __init__(self, *, chat=None, json=None):
        self.chat_script = list(chat or [])
        self.json_script = list(json or [])
        self.chat_calls: list[ChatModel] = []
        self.json_calls: list[tuple[list[ChatMessage], ChatModel]] = []

    async def chat(self, messages, model):
        self.chat_calls.append(model)
        step = self.chat_script.pop(0)
        if isinstance(step, BaseException):
            raise step
        return ChatResponse(content=step, model=model.model)

    async def generate_json(self, messages, model, response_schema):
        self.json_calls.append((list(messages), model))
        step = self.json_script.pop(0)
        if isinstance(step, BaseException):
            raise step
        return response_schema.model_validate(step)


def _gateway(
    *,
    default_provider: str = "openai",
    fallback: list[str] | None = None,
    routing: dict[str, ChatModel] | None = None,
    max_retries: int = 0,
    observer_cb=None,
) -> tuple[LLMGateway, dict[str, FakeProvider]]:
    providers: dict[str, ProviderConfig] = {
        "openai": ProviderConfig(name="openai", base_url="https://a/v1", api_key="k", model="m1"),
        "backup": ProviderConfig(name="backup", base_url="https://b/v1", api_key="k", model="m2"),
        "stub": ProviderConfig(name="stub", model="stub"),
    }
    config = GatewayConfig(
        default_provider=default_provider,
        max_retries=max_retries,
        retry_backoff=0.0,
        fallback_order=list(fallback) if fallback is not None else ["stub"],
        routing=routing or {},
        providers=providers,
        observer=observer_cb,
    )
    gateway = LLMGateway(config)
    fakes: dict[str, FakeProvider] = {
        name: FakeProvider() for name in ("openai", "backup")
    }
    gateway._providers = {**gateway._providers, **fakes}  # type: ignore[attr-defined]
    return gateway, fakes


def run(coro):
    return asyncio.run(coro)


class TestRouting:
    def test_unknown_task_uses_default_provider(self):
        gateway, fakes = _gateway()
        fake = fakes["openai"]
        fake.chat_script = ["ok"]
        result = run(gateway.chat([ChatMessage(role=Role.USER, content="x")], task="explain"))
        assert result.content == "ok"
        assert fake.chat_calls[0].provider == "openai"
        assert fake.chat_calls[0].model == "m1"

    def test_routed_task_uses_configured_model(self):
        routing = {
            "explain": ChatModel(
                provider="openai", model="gpt-special", base_url="https://a/v1", api_key="k"
            )
        }
        gateway, fakes = _gateway(routing=routing, default_provider="stub")
        fake = fakes["openai"]
        fake.chat_script = ["ok"]
        run(gateway.chat([ChatMessage(role=Role.USER, content="x")], task="explain"))
        assert fake.chat_calls[0].model == "gpt-special"


class TestRetry:
    def test_retries_transient_failure_then_succeeds(self):
        gateway, fakes = _gateway(max_retries=2)
        fake = fakes["openai"]
        fake.chat_script = [ProviderConnectionError("boom"), "recovered"]
        result = run(gateway.chat([ChatMessage(role=Role.USER, content="x")], task="t"))
        assert result.content == "recovered"
        assert len(fake.chat_calls) == 2

    def test_moves_to_fallback_after_retries_exhausted(self):
        gateway, fakes = _gateway(max_retries=1, fallback=["backup", "stub"])
        primary = fakes["openai"]
        backup = fakes["backup"]
        primary.chat_script = [
            ProviderConnectionError("c1"),
            ProviderConnectionError("c2"),
        ]
        backup.chat_script = ["from backup"]
        result = run(gateway.chat([ChatMessage(role=Role.USER, content="x")], task="t"))
        assert result.content == "from backup"
        assert backup.chat_calls[0].provider == "backup"

    def test_permanent_error_skips_to_fallback(self):
        gateway, fakes = _gateway(fallback=["backup", "stub"])
        fakes["openai"].chat_script = [ProviderResponseError("permanent")]
        fakes["backup"].chat_script = ["backup wins"]
        result = run(gateway.chat([ChatMessage(role=Role.USER, content="x")], task="t"))
        assert result.content == "backup wins"

    def test_all_providers_fail_raises_fallback_error(self):
        gateway, fakes = _gateway(fallback=["backup"])
        fakes["openai"].chat_script = [ProviderConnectionError("c")]
        fakes["backup"].chat_script = [ProviderResponseError("p")]
        with pytest.raises(GatewayFallbackError):
            run(gateway.chat([ChatMessage(role=Role.USER, content="x")], task="t"))


class TestStructuredOutput:
    def test_generate_json_rerquests_on_schema_failure(self):
        gateway, fakes = _gateway(max_retries=2)
        fake = fakes["openai"]
        fake.json_script = [
            SchemaValidationError(raw_content="bad", errors=["summary missing"]),
            {"summary": "fixed", "notes": ["a"]},
        ]
        result = run(
            gateway.generate_json(
                [ChatMessage(role=Role.USER, content="x")],
                task="explain",
                response_schema=Demo,
            )
        )
        assert result.summary == "fixed"
        # Second attempt must include corrective assistant + user feedback messages.
        second_messages, _ = fake.json_calls[1]
        roles = [m.role for m in second_messages]
        assert Role.ASSISTANT in roles
        assert Role.USER in roles
        assert len(second_messages) > len(fake.json_calls[0][0])

    def test_generate_json_with_stub_fallback(self):
        gateway, fakes = _gateway(default_provider="stub")
        result = run(
            gateway.generate_json(
                [ChatMessage(role=Role.USER, content="x")],
                task="explain",
                response_schema=Demo,
            )
        )
        assert isinstance(result, Demo)


class TestObservability:
    def test_emits_events_without_content(self):
        events = []

        def observer(event):
            events.append(event)

        gateway, fakes = _gateway(observer_cb=observer)
        fakes["openai"].chat_script = ["ok"]
        run(gateway.chat([ChatMessage(role=Role.USER, content="x")], task="t"))
        assert len(events) == 1
        event = events[0]
        assert event.provider == "openai"
        assert event.error is None
        assert "content" not in event.__dict__

    def test_emits_failure_event(self):
        events = []

        def observer(event):
            events.append(event)

        gateway, fakes = _gateway(observer_cb=observer, fallback=[])
        fakes["openai"].chat_script = [ProviderResponseError("bad")]
        with pytest.raises(GatewayFallbackError):
            run(gateway.chat([ChatMessage(role=Role.USER, content="x")], task="t"))
        assert any(event.error == "ProviderResponseError" for event in events)
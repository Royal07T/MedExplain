import asyncio
import json

import httpx
import pytest
from pydantic import BaseModel

from app.schemas.analysis import AiAnalysis
from app.services.llm.gateway.errors import (
    ProviderAuthError,
    ProviderConnectionError,
    ProviderRateLimitError,
    ProviderResponseError,
    SchemaValidationError,
)
from app.services.llm.gateway.providers.openai_compatible import OpenAICompatibleProvider
from app.services.llm.gateway.providers.stub import StubProvider
from app.services.llm.gateway.types import ChatMessage, ChatModel, Role


class Demo(BaseModel):
    summary: str
    notes: list[str] = []


def _model() -> ChatModel:
    return ChatModel(
        provider="openai",
        model="gpt-test",
        base_url="https://api.openai.com/v1",
        api_key="sk-test",
    )


def _ok_response(content: str) -> httpx.Response:
    body = {
        "model": "gpt-test",
        "choices": [{"message": {"content": content}}],
        "usage": {"prompt_tokens": 10, "completion_tokens": 5},
    }
    return httpx.Response(200, json=body)


def run(coro):
    return asyncio.run(coro)


class TestStubProvider:
    def test_chat_is_deterministic(self):
        provider = StubProvider()
        response = run(
            provider.chat(
                [ChatMessage(role=Role.USER, content="hello")],
                _model(),
            )
        )
        assert response.content.startswith("[stub:gpt-test]")
        assert response.model == "stub"

    def test_generate_json_produces_schema_instance(self):
        provider = StubProvider()
        result = run(
            provider.generate_json(
                [ChatMessage(role=Role.USER, content="x")],
                _model(),
                Demo,
            )
        )
        assert isinstance(result, Demo)
        assert result.summary == ""

    def test_generate_json_for_analysis_schema(self):
        provider = StubProvider()
        result = run(
            provider.generate_json(
                [ChatMessage(role=Role.USER, content="x")],
                _model(),
                AiAnalysis,
            )
        )
        assert isinstance(result, AiAnalysis)


class TestOpenAICompatibleProvider:
    def test_chat_returns_content_and_usage(self):
        async def handler(request: httpx.Request) -> httpx.Response:
            assert request.headers["Authorization"] == "Bearer sk-test"
            assert request.url.path == "/v1/chat/completions"
            payload = json.loads(request.content)
            assert payload["model"] == "gpt-test"
            assert "response_format" not in payload
            return _ok_response("plain text")

        provider = OpenAICompatibleProvider(transport=httpx.MockTransport(handler))
        response = run(provider.chat([ChatMessage(role=Role.USER, content="hi")], _model()))
        assert response.content == "plain text"
        assert response.usage is not None
        assert response.usage.prompt_tokens == 10
        assert response.usage.completion_tokens == 5

    def test_generate_json_returns_validated_model(self):
        async def handler(request: httpx.Request) -> httpx.Response:
            payload = json.loads(request.content)
            assert payload["response_format"] == {"type": "json_object"}
            return _ok_response(json.dumps({"summary": "hello", "notes": ["a"]}))

        provider = OpenAICompatibleProvider(transport=httpx.MockTransport(handler))
        result = run(
            provider.generate_json(
                [ChatMessage(role=Role.USER, content="hi")],
                _model(),
                Demo,
            )
        )
        assert isinstance(result, Demo)
        assert result.summary == "hello"
        assert result.notes == ["a"]

    def test_generate_json_raises_on_invalid_json(self):
        async def handler(request: httpx.Request) -> httpx.Response:
            return _ok_response("not json at all")

        provider = OpenAICompatibleProvider(transport=httpx.MockTransport(handler))
        with pytest.raises(SchemaValidationError) as exc:
            run(
                provider.generate_json(
                    [ChatMessage(role=Role.USER, content="hi")],
                    _model(),
                    Demo,
                )
            )
        assert exc.value.raw_content == "not json at all"

    def test_generate_json_raises_on_schema_mismatch(self):
        async def handler(request: httpx.Request) -> httpx.Response:
            return _ok_response(json.dumps({"summary": 123}))

        provider = OpenAICompatibleProvider(transport=httpx.MockTransport(handler))
        with pytest.raises(SchemaValidationError):
            run(
                provider.generate_json(
                    [ChatMessage(role=Role.USER, content="hi")],
                    _model(),
                    Demo,
                )
            )

    def test_rate_limit_maps_to_rate_limit_error(self):
        async def handler(request: httpx.Request) -> httpx.Response:
            return httpx.Response(429, json={"error": "rate limited"})

        provider = OpenAICompatibleProvider(transport=httpx.MockTransport(handler))
        with pytest.raises(ProviderRateLimitError):
            run(provider.chat([ChatMessage(role=Role.USER, content="hi")], _model()))

    def test_auth_error_maps_to_auth_error(self):
        async def handler(request: httpx.Request) -> httpx.Response:
            return httpx.Response(401, json={"error": "unauthorized"})

        provider = OpenAICompatibleProvider(transport=httpx.MockTransport(handler))
        with pytest.raises(ProviderAuthError):
            run(provider.chat([ChatMessage(role=Role.USER, content="hi")], _model()))

    def test_server_error_maps_to_response_error(self):
        async def handler(request: httpx.Request) -> httpx.Response:
            return httpx.Response(500, json={"error": "boom"})

        provider = OpenAICompatibleProvider(transport=httpx.MockTransport(handler))
        with pytest.raises(ProviderResponseError):
            run(provider.chat([ChatMessage(role=Role.USER, content="hi")], _model()))

    def test_transport_failure_maps_to_connection_error(self):
        async def handler(request: httpx.Request) -> httpx.Response:
            raise httpx.ConnectError("connection refused")

        provider = OpenAICompatibleProvider(transport=httpx.MockTransport(handler))
        with pytest.raises(ProviderConnectionError):
            run(provider.chat([ChatMessage(role=Role.USER, content="hi")], _model()))

    def test_does_not_send_api_key_when_none(self):
        captured: dict[str, str] = {}

        async def handler(request: httpx.Request) -> httpx.Response:
            captured["authorization"] = request.headers.get("Authorization", "")
            return _ok_response("ok")

        provider = OpenAICompatibleProvider(transport=httpx.MockTransport(handler))
        model = ChatModel(
            provider="stub",
            model="local",
            base_url="https://local.example/v1",
            api_key=None,
        )
        run(provider.chat([ChatMessage(role=Role.USER, content="hi")], model))
        assert captured["authorization"] == ""
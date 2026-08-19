import asyncio

import pytest
from fastapi.testclient import TestClient

from app.main import app
from app.schemas.assistant import AssistantRequest
from app.services.ai.assistant import AssistantService
from app.services.llm.factory import DISCLAIMER
from app.services.llm.gateway import GatewayConfig, ProviderConfig, LLMGateway

KEY = "dev-secret-change-me"
client = TestClient(app)


def run(coro):
    return asyncio.run(coro)


def _stub_gateway() -> LLMGateway:
    config = GatewayConfig(
        default_provider="stub",
        providers={"stub": ProviderConfig(name="stub", model="stub")},
    )
    return LLMGateway(config)


class TestAssistantService:
    def test_returns_stub_reply_with_disclaimer_and_sources(self):
        service = AssistantService(gateway=_stub_gateway())
        result = run(service.chat(AssistantRequest(message="What is cholesterol?")))
        assert result.reply
        assert result.disclaimer == DISCLAIMER
        assert any("cholesterol" in source.lower() for source in result.sources)

    def test_urgent_question_is_deferred_without_calling_llm(self):
        service = AssistantService(gateway=_stub_gateway())
        result = run(
            service.chat(AssistantRequest(message="I have chest pain right now"))
        )
        assert "immediate medical care" in result.reply
        assert result.sources == []

    def test_grounding_context_limits_sources(self):
        service = AssistantService(gateway=_stub_gateway())
        result = run(
            service.chat(AssistantRequest(message="tell me about thyroid hormones"))
        )
        assert any("thyroid" in source.lower() for source in result.sources)


class TestAssistantEndpoint:
    def test_requires_service_key(self):
        resp = client.post(
            "/api/v1/assistant/chat",
            json={"message": "hello"},
        )
        assert resp.status_code == 401

    def test_validates_message_required(self):
        resp = client.post(
            "/api/v1/assistant/chat",
            json={},
            headers={"X-Service-Key": KEY},
        )
        assert resp.status_code == 422

    def test_chats_with_stub_gateway(self):
        resp = client.post(
            "/api/v1/assistant/chat",
            json={"message": "explain glucose", "lab_tests": []},
            headers={"X-Service-Key": KEY},
        )
        assert resp.status_code == 200
        body = resp.json()
        assert body["reply"]
        assert body["disclaimer"]


class TestMedicationsEndpoint:
    def test_requires_service_key(self):
        resp = client.post(
            "/api/v1/medications/extract",
            json={"raw_text": "Metformin 500 mg"},
        )
        assert resp.status_code == 401

    def test_validates_raw_text_required(self):
        resp = client.post(
            "/api/v1/medications/extract",
            json={},
            headers={"X-Service-Key": KEY},
        )
        assert resp.status_code == 422

    def test_extracts_medications(self):
        resp = client.post(
            "/api/v1/medications/extract",
            json={"raw_text": "Metformin 500 mg tablet twice daily\nLisinopril 10 mg oral"},
            headers={"X-Service-Key": KEY},
        )
        assert resp.status_code == 200
        body = resp.json()
        names = {m["name"].lower() for m in body["medications"]}
        assert "metformin" in names
        assert "lisinopril" in names

    def test_empty_text_returns_empty_extraction(self):
        resp = client.post(
            "/api/v1/medications/extract",
            json={"raw_text": "no medication-like lines here"},
            headers={"X-Service-Key": KEY},
        )
        assert resp.status_code == 200
        assert resp.json()["medications"] == []
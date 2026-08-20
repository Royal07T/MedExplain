import asyncio

import pytest
from fastapi.testclient import TestClient
from pydantic import BaseModel

from app.main import app
from app.schemas.health_query import HealthQueryRequest, HealthQueryResponse
from app.services.ai.health_intelligence import HealthIntelligenceService
from app.services.llm.factory import DISCLAIMER
from app.services.llm.gateway import ChatMessage, ChatModel, ChatResponse, GatewayConfig, LLMGateway, ProviderConfig
from app.services.llm.gateway.errors import SchemaValidationError
from app.services.llm.gateway.providers.base import LLMProvider

KEY = "dev-secret-change-me"
client = TestClient(app)


def run(coro):
    return asyncio.run(coro)


class CannedProvider(LLMProvider):
    def __init__(self, response: BaseModel | None = None, raise_error: bool = False):
        self._response = response
        self._raise_error = raise_error

    async def chat(self, messages, model):
        return ChatResponse(content="", model=model.model)

    async def generate_json(self, messages, model, response_schema):
        if self._raise_error:
            raise SchemaValidationError()
        return self._response or response_schema()


def _stub_gateway() -> LLMGateway:
    config = GatewayConfig(
        default_provider="stub",
        providers={"stub": ProviderConfig(name="stub", model="stub")},
    )
    return LLMGateway(config)


def _canned_gateway(response: HealthQueryResponse) -> LLMGateway:
    gateway = _stub_gateway()
    gateway._providers = {"stub": CannedProvider(response)}
    return gateway


def _request(**overrides) -> HealthQueryRequest:
    base = HealthQueryRequest(
        query_id="q-1",
        question="How did my glucose change between my last two reports?",
        intent="REPORT_COMPARISON",
        comparison={
            "first_report": "2026-06-01",
            "last_report": "2026-08-20",
            "changes": [
                {"test": "Glucose", "change_type": "changed", "first": 5.2, "last": 6.4}
            ],
        },
        data_used=[
            {"type": "lab_result", "label": "Glucose (2026-08-20)", "reference": "lab:101"},
        ],
    )
    return base.model_copy(update=overrides)


class TestHealthIntelligenceService:
    def test_urgent_question_is_deferred_without_calling_llm(self):
        service = HealthIntelligenceService(gateway=_stub_gateway())
        result = run(service.answer(_request(question="I have chest pain right now")))
        assert "immediate medical care" in result.summary
        assert result.sources == []

    def test_missing_required_data_returns_unavailable_without_llm(self):
        service = HealthIntelligenceService(gateway=_stub_gateway())
        result = run(service.answer(_request(comparison=None)))
        assert "unavailable" in result.summary.lower()
        assert result.data_used == _request(comparison=None).data_used

    def test_returns_structured_response_with_grounded_sources(self):
        canned = HealthQueryResponse(
            summary="Your glucose rose from 5.2 to 6.4 mmol/L.",
            facts=["Your glucose rose between the two reports."],
            disclaimer="",
        )
        service = HealthIntelligenceService(gateway=_canned_gateway(canned))
        result = run(service.answer(_request()))
        assert result.summary == "Your glucose rose from 5.2 to 6.4 mmol/L."
        assert result.facts == ["Your glucose rose between the two reports."]
        assert result.disclaimer == DISCLAIMER
        assert result.data_used == _request().data_used
        assert any("glucose" in source.lower() for source in result.sources)

    def test_facts_referencing_unknown_terms_are_dropped(self):
        canned = HealthQueryResponse(
            summary="Your glucose changed.",
            facts=[
                "Your glucose rose between the two reports.",
                "Imaginary hormone levels dropped sharply.",
            ],
        )
        service = HealthIntelligenceService(gateway=_canned_gateway(canned))
        result = run(service.answer(_request()))
        assert result.facts == ["Your glucose rose between the two reports."]

    def test_facts_with_invented_numbers_are_dropped(self):
        canned = HealthQueryResponse(
            summary="Your glucose changed.",
            facts=[
                "Your glucose rose from 5.2 to 6.4 mmol/L.",
                "Your glucose reached 999 mg/dL.",
            ],
        )
        service = HealthIntelligenceService(gateway=_canned_gateway(canned))
        result = run(service.answer(_request()))
        assert result.facts == ["Your glucose rose from 5.2 to 6.4 mmol/L."]

    def test_empty_summary_uses_deterministic_fallback(self):
        canned = HealthQueryResponse(summary="   ")
        service = HealthIntelligenceService(gateway=_canned_gateway(canned))
        result = run(service.answer(_request()))
        assert "1 test(s) changed" in result.summary
        assert result.disclaimer == DISCLAIMER

    def test_malformed_output_returns_graceful_failure(self):
        gateway = _stub_gateway()
        gateway._providers = {"stub": CannedProvider(raise_error=True)}
        service = HealthIntelligenceService(gateway=gateway)
        result = run(service.answer(_request()))
        assert "could not generate" in result.summary
        assert result.disclaimer == DISCLAIMER

    def test_lab_trend_insufficient_when_no_trend(self):
        service = HealthIntelligenceService(gateway=_stub_gateway())
        result = run(
            service.answer(
                _request(intent="LAB_TREND", question="Trend for glucose?", trend=None)
            )
        )
        assert "unavailable" in result.summary.lower()

    def test_general_question_retrieves_education(self):
        canned = HealthQueryResponse(summary="Educational answer about cholesterol.")
        service = HealthIntelligenceService(gateway=_canned_gateway(canned))
        result = run(
            service.answer(
                _request(
                    intent="GENERAL_HEALTH_QUESTION",
                    question="What is cholesterol?",
                    comparison=None,
                )
            )
        )
        assert result.summary == "Educational answer about cholesterol."
        assert any("cholesterol" in source.lower() for source in result.sources)


class TestHealthQueryEndpoint:
    def test_requires_service_key(self):
        resp = client.post(
            "/api/v1/health/query",
            json={"question": "how is my glucose?"},
        )
        assert resp.status_code == 401

    def test_validates_question_required(self):
        resp = client.post(
            "/api/v1/health/query",
            json={"intent": "REPORT_COMPARISON"},
            headers={"X-Service-Key": KEY},
        )
        assert resp.status_code == 422

    def test_answers_with_stub_gateway(self):
        resp = client.post(
            "/api/v1/health/query",
            json={
                "query_id": "q-1",
                "question": "How did my glucose change?",
                "intent": "REPORT_COMPARISON",
                "comparison": {
                    "changes": [
                        {"test": "Glucose", "change_type": "changed", "first": 5.2, "last": 6.4}
                    ]
                },
                "data_used": [{"type": "lab_result", "label": "Glucose", "reference": "lab:101"}],
            },
            headers={"X-Service-Key": KEY},
        )
        assert resp.status_code == 200
        body = resp.json()
        assert body["summary"]
        assert body["disclaimer"]
        assert body["data_used"] == [{"type": "lab_result", "label": "Glucose", "reference": "lab:101"}]

    def test_returns_unavailable_for_missing_data(self):
        resp = client.post(
            "/api/v1/health/query",
            json={
                "question": "How did my glucose change?",
                "intent": "REPORT_COMPARISON",
                "comparison": None,
            },
            headers={"X-Service-Key": KEY},
        )
        assert resp.status_code == 200
        assert "unavailable" in resp.json()["summary"].lower()
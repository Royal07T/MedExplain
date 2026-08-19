"""HTTP mapping of gateway errors to clean, safe responses."""

from fastapi.testclient import TestClient

from app.main import app
from app.schemas.analysis import AiAnalysis
from app.services.llm.gateway.errors import (
    GatewayFallbackError,
    ProviderAuthError,
    ProviderConnectionError,
)

client = TestClient(app)

KEY = "dev-secret-change-me"


class _FakeProvider:
    def __init__(self, error):
        self._error = error

    async def explain(self, document_type, raw_text, lab_tests, knowledge_context=None) -> AiAnalysis:
        raise self._error


def _patch_provider(monkeypatch, error):
    from app.services.llm import factory as llm_factory

    monkeypatch.setattr(llm_factory, "get_llm_provider", lambda: _FakeProvider(error))


PAYLOAD = {
    "document_type": "lab_report",
    "raw_text": "Glucose 95 mg/dL",
    "lab_tests": [],
}


class TestGatewayErrorHandlers:
    def test_connection_error_is_502(self, monkeypatch):
        _patch_provider(monkeypatch, ProviderConnectionError("down"))
        resp = client.post("/api/v1/analysis/explain", json=PAYLOAD, headers={"X-Service-Key": KEY})
        assert resp.status_code == 502

    def test_auth_error_is_502(self, monkeypatch):
        _patch_provider(monkeypatch, ProviderAuthError("bad key"))
        resp = client.post("/api/v1/analysis/explain", json=PAYLOAD, headers={"X-Service-Key": KEY})
        assert resp.status_code == 502

    def test_fallback_error_is_503(self, monkeypatch):
        _patch_provider(monkeypatch, GatewayFallbackError("all providers failed"))
        resp = client.post("/api/v1/analysis/explain", json=PAYLOAD, headers={"X-Service-Key": KEY})
        assert resp.status_code == 503

    def test_error_bodies_never_include_details(self, monkeypatch):
        _patch_provider(monkeypatch, GatewayFallbackError("all providers failed"))
        resp = client.post("/api/v1/analysis/explain", json=PAYLOAD, headers={"X-Service-Key": KEY})
        body = resp.json()
        assert "all providers failed" not in str(body)
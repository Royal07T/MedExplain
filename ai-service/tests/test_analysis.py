import pytest
from fastapi import HTTPException
from fastapi.testclient import TestClient
from pydantic import ValidationError

from app.main import app
from app.schemas.analysis import AiAnalysis, AnalysisCategory, AnalysisItem
from app.schemas.extraction import DocumentType, LabResultStatus, LabTest
from app.services.llm import factory as llm_factory

KEY = "dev-secret-change-me"
client = TestClient(app)

PAYLOAD = {
    "document_type": DocumentType.LAB_REPORT.value,
    "raw_text": "Glucose 95 mg/dL 70-99 Normal\nCholesterol 240 mg/dL < 200 High\n",
    "lab_tests": [
        {
            "name": "Glucose",
            "value": "95",
            "unit": "mg/dL",
            "reference_range": "70-99",
            "status": LabResultStatus.WITHIN_RANGE.value,
        },
        {
            "name": "Cholesterol",
            "value": "240",
            "unit": "mg/dL",
            "reference_range": "< 200",
            "status": LabResultStatus.ABOVE_RANGE.value,
        },
    ],
}


class TestExplainEndpoint:
    def test_explains_with_stub_provider(self):
        resp = client.post(
            "/api/v1/analysis/explain",
            json=PAYLOAD,
            headers={"X-Service-Key": KEY},
        )
        assert resp.status_code == 200
        body = resp.json()
        assert body["summary"]
        assert body["disclaimer"]
        assert len(body["items"]) == 2
        categories = {item["category"] for item in body["items"]}
        assert AnalysisCategory.REFERENCE_COMPARISON.value in categories
        assert AnalysisCategory.POSSIBLE_CONTEXT.value in categories

    def test_concerns_list_out_of_range_results(self):
        resp = client.post(
            "/api/v1/analysis/explain",
            json=PAYLOAD,
            headers={"X-Service-Key": KEY},
        )
        body = resp.json()
        assert any("Cholesterol" in c for c in body["concerns"])

    def test_requires_service_key(self):
        resp = client.post("/api/v1/analysis/explain", json=PAYLOAD)
        assert resp.status_code == 401


def test_openai_provider_requires_key(monkeypatch):
    class FakeSettings:
        llm_provider = "openai"
        openai_api_key = None

    monkeypatch.setattr(llm_factory, "get_settings", lambda: FakeSettings())
    with pytest.raises(HTTPException) as exc:
        llm_factory.get_llm_provider()
    assert exc.value.status_code == 503


def test_factory_defaults_to_stub():
    assert isinstance(llm_factory.get_llm_provider(), llm_factory.StubProvider)


def test_lab_test_round_trip_via_request_model():
    from app.schemas.analysis import ExplainRequest

    req = ExplainRequest.model_validate(PAYLOAD)
    assert req.document_type == DocumentType.LAB_REPORT
    assert req.lab_tests[1].status == LabResultStatus.ABOVE_RANGE


def test_ai_analysis_requires_summary_and_disclaimer():
    with pytest.raises(ValidationError):
        AiAnalysis(summary="x")


def test_ai_analysis_item_category_is_enum():
    item = AnalysisItem(test_name="Glucose", explanation="...", category="education")
    analysis = AiAnalysis(summary="s", disclaimer="d", items=[item])
    assert analysis.items[0].category == "education"
    assert analysis.items[0].test_name == "Glucose"
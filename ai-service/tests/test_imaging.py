from fastapi.testclient import TestClient

from app.main import app
from app.schemas.imaging import ImagingAnalysisRequest
from app.services.ai.imaging_service import ImagingService

KEY = "dev-secret-change-me"
client = TestClient(app)


def _request(**kw):
    defaults = {
        "modality": "ct",
        "body_region": "Head",
        "clinical_indication": "Routine follow-up scan",
        "priority": "routine",
        "status": "pending",
        "icd_code": "G93.9",
    }
    defaults.update(kw)
    return ImagingAnalysisRequest(**defaults)


class TestImagingService:
    def test_routine_scan_recommends_routine(self):
        result = ImagingService().analyze(_request())
        assert result.priority_level == "routine"
        assert result.analyzed_modality == "ct"
        assert result.disclaimer

    def test_stat_indication_escalates(self):
        result = ImagingService().analyze(_request(clinical_indication="Acute stroke, suspected CVA"))
        assert result.priority_level == "stat"
        assert any(r.title == "Escalate reading" for r in result.recommendations)

    def test_urgent_indication(self):
        result = ImagingService().analyze(_request(clinical_indication="Rule out DVT"))
        assert result.priority_level == "urgent"
        assert any(r.title == "Prioritize within the session" for r in result.recommendations)

    def test_explicit_stat_priority(self):
        result = ImagingService().analyze(_request(priority="stat", clinical_indication="Routine"))
        assert result.priority_level == "stat"

    def test_missing_indication_flagged(self):
        result = ImagingService().analyze(_request(clinical_indication=None))
        assert any(r.title == "Capture clinical indication" for r in result.recommendations)

    def test_quality_hint_zero_images(self):
        result = ImagingService().analyze(_request(image_count=0))
        assert any("zero" in h.lower() for h in result.quality_hints)

    def test_high_radiation_dose_hint(self):
        result = ImagingService().analyze(_request(modality="xray", radiation_dose_mgy=35.0))
        assert any("dose" in r.title.lower() for r in result.recommendations)


class TestImagingEndpoints:
    def test_requires_service_key(self):
        resp = client.post("/api/v1/imaging/analyze", json={})
        assert resp.status_code == 401

    def test_analyze_endpoint(self):
        resp = client.post(
            "/api/v1/imaging/analyze",
            json=_request().model_dump(),
            headers={"X-Service-Key": KEY},
        )
        assert resp.status_code == 200
        body = resp.json()
        assert body["priority_level"] in ("routine", "urgent", "stat")
        assert "recommendations" in body
        assert "quality_hints" in body
        assert "disclaimer" in body

    def test_stat_endpoint(self):
        resp = client.post(
            "/api/v1/imaging/analyze",
            json=_request(clinical_indication="Suspected aortic dissection").model_dump(),
            headers={"X-Service-Key": KEY},
        )
        assert resp.status_code == 200
        assert resp.json()["priority_level"] == "stat"

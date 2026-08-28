from fastapi.testclient import TestClient

from app.main import app
from app.services.ai.predictive_service import PredictiveService

KEY = "dev-secret-change-me"
client = TestClient(app)


class TestPredictiveService:
    def test_readmission_high_contributors(self):
        result = PredictiveService().readmission_risk(
            RequestReadmission(
                prior_admissions_90d=2,
                comorbidities=["type 2 diabetes", "heart failure", "copd"],
                length_of_stay_days=9,
                polypharmacy=True,
            )
        )
        assert result.level == "high"
        assert result.contributors

    def test_readmission_low(self):
        result = PredictiveService().readmission_risk(
            RequestReadmission(prior_admissions_90d=0, comorbidities=[], length_of_stay_days=2)
        )
        assert result.level == "low"
        assert result.score == 0

    def test_length_of_stay_scales_with_acuity(self):
        low = PredictiveService().predict_length_of_stay(
            RequestLOS(admission_type="elective", acuity="non-urgent")
        )
        high = PredictiveService().predict_length_of_stay(
            RequestLOS(admission_type="emergency", acuity="emergent", icu_required=True)
        )
        assert high.predicted_days > low.predicted_days
        assert low.range_min <= low.predicted_days <= low.range_max

    def test_deterioration_low_vitals(self):
        result = PredictiveService().deterioration_risk(
            RequestDeterioration(
                vitals=dict(
                    heart_rate=72,
                    respiratory_rate=16,
                    temperature_c=36.8,
                    systolic_bp=120,
                    spo2=98,
                    conscious=True,
                )
            )
        )
        assert result.score == 0
        assert result.level == "low"

    def test_deterioration_critical_from_bad_vitals(self):
        result = PredictiveService().deterioration_risk(
            RequestDeterioration(
                vitals=dict(
                    heart_rate=135,
                    respiratory_rate=8,
                    temperature_c=34.2,
                    systolic_bp=85,
                    spo2=88,
                    conscious=False,
                )
            )
        )
        assert result.score >= 7
        assert result.level == "critical"
        assert result.red_flags


class TestPredictiveEndpoints:
    def test_requires_service_key(self):
        resp = client.post("/api/v1/predictive/readmission", json={})
        assert resp.status_code == 401

    def test_readmission_endpoint(self):
        resp = client.post(
            "/api/v1/predictive/readmission",
            json={
                "prior_admissions_90d": 2,
                "comorbidities": ["heart failure"],
                "length_of_stay_days": 8,
            },
            headers={"X-Service-Key": KEY},
        )
        assert resp.status_code == 200
        body = resp.json()
        assert "score" in body
        assert body["level"] in ("low", "moderate", "high")

    def test_length_of_stay_endpoint(self):
        resp = client.post(
            "/api/v1/predictive/length-of-stay",
            json={"admission_type": "emergency", "acuity": "urgent"},
            headers={"X-Service-Key": KEY},
        )
        assert resp.status_code == 200
        assert resp.json()["predicted_days"] > 0

    def test_deterioration_endpoint(self):
        resp = client.post(
            "/api/v1/predictive/deterioration",
            json={"vitals": {"heart_rate": 135, "respiratory_rate": 8, "spo2": 88, "conscious": False}},
            headers={"X-Service-Key": KEY},
        )
        assert resp.status_code == 200
        assert resp.json()["score"] >= 7


# Small request builders to avoid repeating verbose pydantic values.
from app.schemas.predictive import (  # noqa: E402
    DeteriorationRequest,
    LengthOfStayRequest,
    ReadmissionRequest,
)


def RequestReadmission(**kw):
    return ReadmissionRequest(**kw)


def RequestLOS(**kw):
    return LengthOfStayRequest(**kw)


def RequestDeterioration(**kw):
    return DeteriorationRequest(**kw)

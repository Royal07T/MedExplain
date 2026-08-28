from fastapi.testclient import TestClient

from app.main import app
from app.schemas.assistant import SymptomCheckRequest
from app.services.ai.symptom_service import get_symptom_service

KEY = "dev-secret-change-me"
client = TestClient(app)

svc = get_symptom_service()


def run(text: str):
    return svc.check(SymptomCheckRequest(text=text))


class TestSymptomService:
    def test_emergency_symptoms_put_first(self):
        result = run("I have chest pain and can't breathe.")
        assert result.urgency == "emergency"
        assert result.red_flags
        assert result.disclaimer

    def test_urgent_symptom(self):
        result = run("I have a high fever and severe headache.")
        assert result.urgency == "urgent"

    def test_moderate_symptom_considerate(self):
        result = run("I have been feeling stress lately.")
        assert result.urgency == "moderate"
        assert "clinician" in result.message.lower()

    def test_no_symptoms_returns_general(self):
        result = run("How do I book an appointment?")
        assert result.urgency == "general"
        assert result.matched == []


class TestSymptomEndpoint:
    def test_requires_service_key(self):
        resp = client.post("/api/v1/assistant/symptom-check", json={"text": "chest pain"})
        assert resp.status_code == 401

    def test_checks_symptoms(self):
        resp = client.post(
            "/api/v1/assistant/symptom-check",
            json={"text": "chest pain"},
            headers={"X-Service-Key": KEY},
        )
        assert resp.status_code == 200
        body = resp.json()
        assert body["urgency"] == "emergency"
        assert body["disclaimer"]

    def test_validates_text_required(self):
        resp = client.post(
            "/api/v1/assistant/symptom-check",
            json={},
            headers={"X-Service-Key": KEY},
        )
        assert resp.status_code == 422

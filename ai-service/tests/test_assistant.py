from fastapi.testclient import TestClient

from app.main import app

KEY = "dev-secret-change-me"
client = TestClient(app)


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
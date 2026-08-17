from fastapi.testclient import TestClient

from app.main import app

client = TestClient(app)


def test_health_ok():
    resp = client.get("/api/v1/health")
    assert resp.status_code == 200
    body = resp.json()
    assert body["status"] == "ok"
    assert body["version"]
    assert "ocr" in body["dependencies"]
    assert "llm_provider" in body["dependencies"]


def test_health_is_unauthenticated():
    resp = client.get("/api/v1/health")
    assert resp.status_code == 200
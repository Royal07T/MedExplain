from fastapi.testclient import TestClient

from app.main import app
from app.services.ai.nlp_service import NLPService

KEY = "dev-secret-change-me"
client = TestClient(app)


class TestNlpService:
    def test_summarize_returns_extractive_summary(self):
        text = (
            "Patient presented with chest pain and shortness of breath. "
            "An ECG was performed and showed sinus rhythm. "
            "Chest X-ray showed mild cardiomegaly. "
            "The patient was started on aspirin and rosuvastatin. "
            "They were advised to follow up with cardiology in two weeks."
        )
        result = NLPService().summarize(text, max_sentences=3)
        assert result.original_sentence_count == 5
        assert result.retained_sentence_count == 3
        assert result.summary

    def test_summarize_empty_text(self):
        result = NLPService().summarize("", max_sentences=3)
        assert result.summary == ""
        assert result.original_sentence_count == 0

    def test_extract_concepts_finds_medications_and_diagnoses(self):
        text = (
            "Patient with type 2 diabetes and hypertension is taking metformin, "
            "lisinopril and aspirin."
        )
        result = NLPService().extract_concepts(text)
        values = {(c.type, c.value) for c in result.concepts}
        assert ("diagnosis", "type 2 diabetes") in values
        assert ("diagnosis", "hypertension") in values
        assert ("medication", "metformin") in values
        assert ("medication", "lisinopril") in values
        assert ("medication", "aspirin") in values

    def test_sentiment_positive(self):
        result = NLPService().analyze_sentiment(
            "The care was excellent and the staff were very pleasant and reassuring. "
            "I am satisfied and relieved with the treatment."
        )
        assert result.label == "positive"
        assert result.score > 0

    def test_sentiment_negative(self):
        result = NLPService().analyze_sentiment(
            "I was very dissatisfied, the pain was severe and care was poor."
        )
        assert result.label == "negative"
        assert result.score < 0

    def test_sentiment_neutral(self):
        result = NLPService().analyze_sentiment("The appointment was at 10 o'clock.")
        assert result.label == "neutral"


class TestNlpEndpoints:
    def test_requires_service_key(self):
        resp = client.post("/api/v1/nlp/summarize", json={"text": "hello"})
        assert resp.status_code == 401

    def test_summarize_endpoint(self):
        resp = client.post(
            "/api/v1/nlp/summarize",
            json={"text": "First sentence of the note. Second sentence here."},
            headers={"X-Service-Key": KEY},
        )
        assert resp.status_code == 200
        body = resp.json()
        assert body["summary"]
        assert body["retained_sentence_count"] >= 1

    def test_concepts_endpoint(self):
        resp = client.post(
            "/api/v1/nlp/concepts",
            json={"text": "Diagnosed with asthma, on salbutamol."},
            headers={"X-Service-Key": KEY},
        )
        assert resp.status_code == 200
        body = resp.json()
        kinds = {c["type"] for c in body["concepts"]}
        assert "diagnosis" in kinds
        assert "medication" in kinds

    def test_sentiment_endpoint(self):
        resp = client.post(
            "/api/v1/nlp/sentiment",
            json={"text": "Great care, very satisfied."},
            headers={"X-Service-Key": KEY},
        )
        assert resp.status_code == 200
        assert resp.json()["label"] == "positive"

    def test_validates_text_required(self):
        resp = client.post(
            "/api/v1/nlp/summarize",
            json={},
            headers={"X-Service-Key": KEY},
        )
        assert resp.status_code == 422

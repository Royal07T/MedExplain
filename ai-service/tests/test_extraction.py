import pytest
from fastapi.testclient import TestClient

from app.core.config import Settings
from app.main import app
from app.schemas.extraction import DocumentType, ExtractionMethod, LabResultStatus
from app.services.extraction.lab_parser import detect_document_type, parse_lab_report
from app.services.extraction.ocr import OCR_AVAILABLE
from tests.helpers import build_pdf

KEY = "dev-secret-change-me"
client = TestClient(app)

PDF_TEXT = (
    "Test Result Unit Reference Range Status\n"
    "Glucose 95 mg/dL 70-99 Normal\n"
    "Cholesterol 240 mg/dL < 200 High\n"
    "Hemoglobin 13.5 g/dL 12.0-16.0\n"
    "TSH 0.3 uIU/mL 0.4-4.0\n"
    "HIV Antibody Negative\n"
)


class TestLabParser:
    def test_parses_numeric_rows_with_status(self):
        tests = parse_lab_report(PDF_TEXT)
        by_name = {t.name: t for t in tests}
        assert by_name["Glucose"].value == "95"
        assert by_name["Glucose"].unit == "mg/dL"
        assert by_name["Glucose"].reference_range == "70-99"
        assert by_name["Glucose"].status == LabResultStatus.WITHIN_RANGE

    def test_parses_above_range_via_keyword(self):
        tests = {t.name: t for t in parse_lab_report(PDF_TEXT)}
        assert tests["Cholesterol"].value == "240"
        assert tests["Cholesterol"].status == LabResultStatus.ABOVE_RANGE

    def test_compares_numeric_value_to_range(self):
        tests = {t.name: t for t in parse_lab_report(PDF_TEXT)}
        assert tests["Hemoglobin"].status == LabResultStatus.WITHIN_RANGE
        assert tests["TSH"].status == LabResultStatus.BELOW_RANGE

    def test_parses_qualitative_result(self):
        tests = {t.name: t for t in parse_lab_report(PDF_TEXT)}
        assert tests["HIV Antibody"].value == "Negative"
        assert tests["HIV Antibody"].status == LabResultStatus.NEGATIVE

    def test_skips_header_and_meta_lines(self):
        text = (
            "Patient: John Doe 45y M\n"
            "Date: 2026-08-17\n"
            "Test Result Unit Reference Range Status\n"
            "Creatinine 1.2 mg/dL 0.7-1.3\n"
        )
        tests = parse_lab_report(text)
        assert [t.name for t in tests] == ["Creatinine"]

    def test_detects_document_type(self):
        assert detect_document_type(PDF_TEXT) == DocumentType.LAB_REPORT
        assert detect_document_type("chest x-ray findings normal") == DocumentType.RADIOLOGY_REPORT
        assert detect_document_type("no recognizable content") == DocumentType.UNKNOWN


class TestExtractEndpoint:
    def test_extracts_pdf_text(self):
        pdf = build_pdf(PDF_TEXT)
        resp = client.post(
            "/api/v1/documents/extract",
            files={"file": ("report.pdf", pdf, "application/pdf")},
            headers={"X-Service-Key": KEY},
        )
        assert resp.status_code == 200
        body = resp.json()
        assert body["extraction_method"] == ExtractionMethod.PDF_TEXT.value
        assert body["document_type"] == DocumentType.LAB_REPORT.value
        assert "Glucose" in body["raw_text"]
        assert body["lab_tests"] == []

    def test_pdf_without_text_layer_reports_warning(self):
        pdf = build_pdf("")
        resp = client.post(
            "/api/v1/documents/extract",
            files={"file": ("blank.pdf", pdf, "application/pdf")},
            headers={"X-Service-Key": KEY},
        )
        assert resp.status_code == 200
        body = resp.json()
        assert body["extraction_method"] == ExtractionMethod.NONE.value
        assert body["raw_text"] == ""
        assert body["warnings"]

    def test_unsupported_file_type(self):
        resp = client.post(
            "/api/v1/documents/extract",
            files={"file": ("notes.txt", b"hello", "text/plain")},
            headers={"X-Service-Key": KEY},
        )
        assert resp.status_code == 415

    def test_size_limit(self, monkeypatch):
        from app.api.v1 import documents as documents_api

        monkeypatch.setattr(
            documents_api,
            "get_settings",
            lambda: Settings(max_upload_mb=0),
        )
        resp = client.post(
            "/api/v1/documents/extract",
            files={"file": ("report.pdf", b"x", "application/pdf")},
            headers={"X-Service-Key": KEY},
        )
        assert resp.status_code == 413

    def test_requires_service_key(self):
        resp = client.post(
            "/api/v1/documents/extract",
            files={"file": ("report.pdf", build_pdf(PDF_TEXT), "application/pdf")},
        )
        assert resp.status_code == 401

    def test_wrong_service_key(self):
        resp = client.post(
            "/api/v1/documents/extract",
            files={"file": ("report.pdf", build_pdf(PDF_TEXT), "application/pdf")},
            headers={"X-Service-Key": "wrong"},
        )
        assert resp.status_code == 401


@pytest.mark.skipif(OCR_AVAILABLE, reason="tesseract installed; OCR path differs")
class TestOcrUnavailable:
    def test_image_extraction_reports_unavailable_gracefully(self):
        resp = client.post(
            "/api/v1/documents/extract",
            files={"file": ("scan.png", b"\x89PNG\r\n\x1a\nfakepng", "image/png")},
            headers={"X-Service-Key": KEY},
        )
        assert resp.status_code == 200
        body = resp.json()
        assert body["extraction_method"] == ExtractionMethod.NONE.value
        assert body["raw_text"] == ""
        assert any("OCR" in w for w in body["warnings"])


class TestParseLabReportEndpoint:
    def test_parses_lab_report(self):
        resp = client.post(
            "/api/v1/documents/parse-lab-report",
            json={"raw_text": PDF_TEXT},
            headers={"X-Service-Key": KEY},
        )
        assert resp.status_code == 200
        body = resp.json()
        names = [t["name"] for t in body["lab_tests"]]
        assert "Glucose" in names
        assert "Cholesterol" in names

    def test_respects_inferred_document_type(self):
        resp = client.post(
            "/api/v1/documents/parse-lab-report",
            json={"raw_text": PDF_TEXT},
            headers={"X-Service-Key": KEY},
        )
        assert resp.json()["document_type"] == DocumentType.LAB_REPORT.value

    def test_requires_service_key(self):
        resp = client.post("/api/v1/documents/parse-lab-report", json={"raw_text": PDF_TEXT})
        assert resp.status_code == 401
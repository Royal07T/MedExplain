import pytest
from pydantic import ValidationError

from app.schemas.extraction import (
    DocumentType,
    ExtractionMethod,
    ExtractionResponse,
    LabResultStatus,
    LabTest,
)


def test_lab_test_defaults():
    test = LabTest(name="Glucose", value="95")
    assert test.unit is None
    assert test.reference_range is None
    assert test.status == LabResultStatus.UNKNOWN


def test_lab_test_requires_name_and_value():
    with pytest.raises(ValidationError):
        LabTest(name="Glucose")


def test_extraction_response_defaults():
    resp = ExtractionResponse()
    assert resp.document_type == DocumentType.UNKNOWN
    assert resp.extraction_method == ExtractionMethod.NONE
    assert resp.raw_text == ""
    assert resp.lab_tests == []
    assert resp.warnings == []


def test_document_type_values():
    assert {m.value for m in DocumentType} == {
        "lab_report",
        "doctor_report",
        "radiology_report",
        "unknown",
    }


def test_extraction_method_values():
    assert {m.value for m in ExtractionMethod} == {"pdf_text", "ocr", "none"}


def test_lab_result_status_values():
    assert {m.value for m in LabResultStatus} == {
        "within_range",
        "above_range",
        "below_range",
        "positive",
        "negative",
        "unknown",
    }
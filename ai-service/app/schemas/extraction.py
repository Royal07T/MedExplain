from enum import Enum

from pydantic import BaseModel, Field


class DocumentType(str, Enum):
    LAB_REPORT = "lab_report"
    DOCTOR_REPORT = "doctor_report"
    RADIOLOGY_REPORT = "radiology_report"
    UNKNOWN = "unknown"


class ExtractionMethod(str, Enum):
    PDF_TEXT = "pdf_text"
    OCR = "ocr"
    NONE = "none"


class LabResultStatus(str, Enum):
    WITHIN_RANGE = "within_range"
    ABOVE_RANGE = "above_range"
    BELOW_RANGE = "below_range"
    POSITIVE = "positive"
    NEGATIVE = "negative"
    UNKNOWN = "unknown"


class LabTest(BaseModel):
    name: str
    value: str
    unit: str | None = None
    reference_range: str | None = None
    status: LabResultStatus = LabResultStatus.UNKNOWN


class ExtractionResponse(BaseModel):
    document_type: DocumentType = DocumentType.UNKNOWN
    extraction_method: ExtractionMethod = ExtractionMethod.NONE
    raw_text: str = ""
    lab_tests: list[LabTest] = Field(default_factory=list)
    warnings: list[str] = Field(default_factory=list)


class ParseLabReportRequest(BaseModel):
    raw_text: str
    document_type: DocumentType | None = None
    extraction_method: ExtractionMethod | None = None
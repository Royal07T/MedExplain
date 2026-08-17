from enum import Enum

from pydantic import BaseModel, Field

from app.schemas.extraction import DocumentType, LabTest


class AnalysisCategory(str, Enum):
    FACT = "fact"
    REFERENCE_COMPARISON = "reference_comparison"
    EDUCATION = "education"
    POSSIBLE_CONTEXT = "possible_context"
    QUESTION_FOR_PROFESSIONAL = "question_for_professional"


class AnalysisItem(BaseModel):
    test_name: str
    explanation: str
    category: AnalysisCategory


class ExplainRequest(BaseModel):
    document_type: DocumentType = DocumentType.UNKNOWN
    raw_text: str = ""
    lab_tests: list[LabTest] = Field(default_factory=list)


class AiAnalysis(BaseModel):
    summary: str
    disclaimer: str
    concerns: list[str] = Field(default_factory=list)
    items: list[AnalysisItem] = Field(default_factory=list)
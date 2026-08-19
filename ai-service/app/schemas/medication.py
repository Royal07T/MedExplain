"""Medication extraction schemas (roadmap #6 Medication Intelligence).

Educational only: values are never interpreted as a diagnosis. Interaction and
side-effect content must come from the RAG layer or be marked "ask your clinician".
"""

from pydantic import BaseModel, Field


class Medication(BaseModel):
    name: str
    strength: str | None = None
    dosage_form: str | None = None
    dose: str | None = None
    frequency: str | None = None
    route: str | None = None
    prescriber: str | None = None
    indications: str | None = None
    start_date: str | None = None
    end_date: str | None = None


class MedicationExtraction(BaseModel):
    medications: list[Medication] = Field(default_factory=list)
    warnings: list[str] = Field(default_factory=list)


class MedicationExtractionRequest(BaseModel):
    raw_text: str = Field(min_length=1, max_length=20000)
    llm_fallback: bool = False
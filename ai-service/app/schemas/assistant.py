"""Virtual Health Assistant schemas (deterministic symptom triage).

Symptom checking is a transparent rule-based triage that maps reported
symptoms to a care urgency band. It never diagnoses; it only advises whether
and how urgently to seek professional care.
"""

from pydantic import BaseModel, Field


class SymptomCheckRequest(BaseModel):
    text: str = Field(min_length=1, max_length=2000)


class SymptomHit(BaseModel):
    symptom: str
    category: str
    urgent: bool = False


class SymptomCheckResponse(BaseModel):
    urgency: str
    message: str
    red_flags: list[str] = Field(default_factory=list)
    matched: list[SymptomHit] = Field(default_factory=list)
    disclaimer: str = ""

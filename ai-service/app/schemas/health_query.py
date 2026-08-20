"""Health Intelligence Layer schemas.

The request carries the deterministic, ownership-scoped context computed by the
Laravel backend (never raw documents or full text). The response is a strictly
validated structured answer; malformed AI output is rejected at this boundary.
"""

from pydantic import BaseModel, Field


class PatientContext(BaseModel):
    age: int | None = None
    sex: str | None = None


class HealthQueryRequest(BaseModel):
    query_id: str | None = None
    question: str = Field(min_length=1, max_length=4000)
    intent: str = "GENERAL_HEALTH_QUESTION"
    patient_context: PatientContext = Field(default_factory=PatientContext)

    # Deterministic sections computed by the backend. Only the ones a given
    # intent needs are populated (lazy retrieval — the full history is never
    # sent).
    comparison: dict | None = None
    previous_report_available: bool | None = None
    trend: dict | None = None
    detected_test: str | None = None
    target_lab_result: dict | None = None
    medications_at_date: list[dict] = Field(default_factory=list)
    recent_changes: list[dict] = Field(default_factory=list)
    timeline: list[dict] = Field(default_factory=list)
    lab_history: list[dict] = Field(default_factory=list)
    medication_history: list[dict] = Field(default_factory=list)

    # Deterministic provenance: which reports/labs/medications were used.
    data_used: list[dict] = Field(default_factory=list)


class HealthQueryContextItem(BaseModel):
    text: str
    category: str = "education"


class HealthQueryResponse(BaseModel):
    summary: str
    facts: list[str] = Field(default_factory=list)
    changes: list[str] = Field(default_factory=list)
    context: list[HealthQueryContextItem] = Field(default_factory=list)
    educational_explanation: list[str] = Field(default_factory=list)
    questions_for_professional: list[str] = Field(default_factory=list)
    sources: list[str] = Field(default_factory=list)
    disclaimer: str = ""
    data_used: list[dict] = Field(default_factory=list)

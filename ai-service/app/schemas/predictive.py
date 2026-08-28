"""Predictive analytics schemas (deterministic heuristic scoring).

Models are deliberately rule-based and transparent: they surface the factors
that contributed to each score so a clinician can audit the reasoning. No
opaque ML is used, and scores are always expressed as a point-in-time estimate
— never a diagnosis.
"""

from pydantic import BaseModel, Field


class ReadmissionRequest(BaseModel):
    age: int | None = Field(default=None, ge=0, le=130)
    prior_admissions_90d: int = Field(default=0, ge=0)
    prior_admissions_12m: int = Field(default=0, ge=0)
    comorbidities: list[str] = Field(default_factory=list)
    length_of_stay_days: float = Field(default=0, ge=0)
    polypharmacy: bool = False
    hba1c_uncontrolled: bool = False
    hemoglobin_low: bool = False
    discharge_to_home: bool = True


class ReadmissionResponse(BaseModel):
    score: int
    level: str
    contributors: list[str] = Field(default_factory=list)


class LengthOfStayRequest(BaseModel):
    age: int | None = Field(default=None, ge=0, le=130)
    admission_type: str = "elective"
    acuity: str = "non-urgent"
    comorbidities: list[str] = Field(default_factory=list)
    icu_required: bool = False
    surgery_required: bool = False


class LengthOfStayResponse(BaseModel):
    predicted_days: float
    range_min: float
    range_max: float
    model: str = "heuristic"

    confidence: float = 0.0
    drivers: list[str] = Field(default_factory=list)


class VitalSigns(BaseModel):
    heart_rate: int | None = None
    respiratory_rate: int | None = None
    temperature_c: float | None = None
    systolic_bp: int | None = None
    diastolic_bp: int | None = None
    spo2: int | None = None
    conscious: bool = True
    on_oxygen: bool = False


class DeteriorationRequest(BaseModel):
    vitals: VitalSigns = Field(default_factory=VitalSigns)
    age: int | None = Field(default=None, ge=0, le=130)


class DeteriorationResponse(BaseModel):
    score: int
    level: str
    components: dict[str, int] = Field(default_factory=dict)
    red_flags: list[str] = Field(default_factory=list)

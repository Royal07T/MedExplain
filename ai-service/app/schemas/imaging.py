from pydantic import BaseModel, Field


class ImagingAnalysisRequest(BaseModel):
    """Inputs describing an imaging order to analyze."""

    modality: str = Field(default="", max_length=50)
    body_region: str | None = Field(default=None, max_length=100)
    clinical_indication: str | None = Field(default=None, max_length=2000)
    priority: str = Field(default="routine", max_length=20)
    status: str = Field(default="pending", max_length=50)
    icd_code: str | None = Field(default=None, max_length=20)
    radiation_dose_mgy: float | None = Field(default=None, ge=0)
    image_count: int | None = Field(default=None, ge=0)
    scheduled_at: str | None = Field(default=None)


class Recommendation(BaseModel):
    title: str
    detail: str
    priority_impact: str = Field(default="", max_length=50)


class ImagingAnalysisResponse(BaseModel):
    priority_level: str
    rationale: str
    recommendations: list[Recommendation] = Field(default_factory=list)
    quality_hints: list[str] = Field(default_factory=list)
    disclaimer: str = Field(default="")
    analyzed_modality: str = Field(default="")

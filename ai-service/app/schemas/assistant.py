from pydantic import BaseModel, Field

from app.schemas.extraction import LabTest
from app.schemas.medication import Medication


class AssistantRequest(BaseModel):
    message: str = Field(min_length=1, max_length=4000)
    lab_tests: list[LabTest] = Field(default_factory=list)
    medications: list[Medication] = Field(default_factory=list)


class AssistantResponse(BaseModel):
    reply: str
    disclaimer: str
    sources: list[str] = Field(default_factory=list)
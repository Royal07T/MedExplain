from abc import ABC, abstractmethod

from app.schemas.analysis import AiAnalysis
from app.schemas.extraction import DocumentType, LabTest


class LLMProvider(ABC):
    @abstractmethod
    async def explain(
        self,
        document_type: DocumentType,
        raw_text: str,
        lab_tests: list[LabTest],
        knowledge_context: str | None = None,
    ) -> AiAnalysis: ...
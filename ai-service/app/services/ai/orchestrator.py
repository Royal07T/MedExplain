"""AI Orchestrator — request lifecycle, agent sequencing, and safety gates.

Wraps the deterministic parsers as agents, serves the explain pipeline through
the LLM provider (itself routed through the LLM gateway), and enforces safety
constraints before returning structured output.
"""

from app.schemas.analysis import AiAnalysis, ExplainRequest
from app.schemas.assistant import AssistantRequest, AssistantResponse
from app.schemas.extraction import (
    DocumentType,
    ExtractionMethod,
    ExtractionResponse,
    LabTest,
)
from app.schemas.medication import MedicationExtraction
from app.services.ai.agents.document_agent import DocumentAgent, UnsupportedFileTypeError
from app.services.ai.agents.lab_agent import LabAgent
from app.services.ai.agents.medication_agent import MedicationAgent
from app.services.ai.assistant import AssistantService
from app.services.ai.knowledge import KnowledgeBase
from app.services.llm.base import LLMProvider
from app.services.llm.gateway import LLMGateway
from app.services.llm.factory import DISCLAIMER


class Orchestrator:
    def __init__(
        self,
        llm_provider: LLMProvider | None = None,
        gateway: LLMGateway | None = None,
        *,
        llm_fallback: bool = False,
        knowledge: KnowledgeBase | None = None,
    ):
        from app.services.llm.factory import get_llm_provider

        self.llm_provider = llm_provider if llm_provider is not None else get_llm_provider()
        self.document_agent = DocumentAgent()
        self.lab_agent = LabAgent(gateway=gateway)
        self.medication_agent = MedicationAgent(gateway=gateway)
        self.llm_fallback = llm_fallback
        self.knowledge = knowledge if knowledge is not None else self._default_knowledge()
        self.assistant = AssistantService(gateway=gateway, knowledge=self.knowledge)

    @staticmethod
    def _default_knowledge() -> KnowledgeBase:
        from app.services.ai.knowledge import get_knowledge_base

        return get_knowledge_base()

    # ------------------------------------------------------------------ extract

    def extract_document(self, data: bytes, filename: str | None) -> ExtractionResponse:
        """Run the Document Agent and map to the public extraction contract."""
        try:
            result = self.document_agent.extract(data, filename)
        except UnsupportedFileTypeError as exc:
            raise exc

        return ExtractionResponse(
            document_type=result.document_type,
            extraction_method=result.method,
            raw_text=result.text,
            lab_tests=[],
            warnings=result.warnings,
        )

    def parse_lab_report(
        self,
        raw_text: str,
        document_type: DocumentType | None = None,
        extraction_method: ExtractionMethod | None = None,
    ) -> ExtractionResponse:
        return ExtractionResponse(
            document_type=document_type or self._detect(raw_text),
            extraction_method=extraction_method or ExtractionMethod.NONE,
            raw_text=raw_text,
            lab_tests=self.lab_agent.parse(raw_text),
        )

    # ------------------------------------------------------------------ explain

    async def explain(self, request: ExplainRequest) -> AiAnalysis:
        labs = list(request.lab_tests)
        if not labs:
            labs = await self.lab_agent.extract(
                request.raw_text,
                llm_fallback=self.llm_fallback,
            )
        # Range gate: fill gaps only from the curated store; never invent.
        labs = self.knowledge.enrich_labs(labs)
        grounding = self.knowledge.grounding_context(labs)
        analysis = await self.llm_provider.explain(
            request.document_type,
            request.raw_text,
            labs,
            knowledge_context=grounding,
        )
        return self._run_safety_gates(analysis, labs)

    async def extract_medications(
        self,
        raw_text: str,
        *,
        llm_fallback: bool = False,
    ) -> MedicationExtraction:
        return await self.medication_agent.extract(raw_text, llm_fallback=llm_fallback)

    async def chat(self, request: AssistantRequest) -> AssistantResponse:
        return await self.assistant.chat(request)

    # ------------------------------------------------------------ safety gates

    def _run_safety_gates(
        self,
        analysis: AiAnalysis,
        labs: list[LabTest],
    ) -> AiAnalysis:
        # The professional-consult disclaimer must always be present.
        if not analysis.disclaimer.strip():
            analysis.disclaimer = DISCLAIMER

        # Concerns should reference only tests actually present in the input.
        known = {test.name.strip().lower() for test in labs}
        if known:
            analysis.concerns = list(
                dict.fromkeys(
                    concern
                    for concern in analysis.concerns
                    if any(name in concern.lower() for name in known)
                )
            )
        else:
            analysis.concerns = list(dict.fromkeys(analysis.concerns))

        return analysis

    @staticmethod
    def _detect(raw_text: str) -> DocumentType:
        from app.services.extraction.lab_parser import detect_document_type

        return detect_document_type(raw_text)


def get_orchestrator() -> Orchestrator:
    """Build a fresh orchestrator from current settings (never cached)."""
    return Orchestrator()
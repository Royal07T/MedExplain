import asyncio

from app.schemas.analysis import AiAnalysis, AnalysisCategory, AnalysisItem, ExplainRequest
from app.schemas.extraction import (
    DocumentType,
    ExtractionMethod,
    LabResultStatus,
    LabTest,
)
from app.services.ai.orchestrator import Orchestrator
from app.services.llm.factory import DISCLAIMER
from app.services.llm.base import LLMProvider

from tests.helpers import build_pdf


def run(coro):
    return asyncio.run(coro)


class FakeProvider(LLMProvider):
    def __init__(self, analysis: AiAnalysis | None = None):
        self.analysis = analysis or AiAnalysis(summary="s", disclaimer="d")
        self.calls: list[tuple[DocumentType, str, list[LabTest], str | None]] = []

    async def explain(self, document_type, raw_text, lab_tests, knowledge_context=None):
        self.calls.append((document_type, raw_text, lab_tests, knowledge_context))
        return self.analysis


GLUCOSE = LabTest(
    name="Glucose",
    value="95",
    unit="mg/dL",
    reference_range="70-99",
    status=LabResultStatus.WITHIN_RANGE,
)

PDF_TEXT = (
    "Test Result Unit Reference Range Status\n"
    "Glucose 95 mg/dL 70-99 Normal\n"
)


class TestExplainPipeline:
    def test_passes_through_provider_with_supplied_labs(self):
        provider = FakeProvider()
        orch = Orchestrator(llm_provider=provider)
        result = run(
            orch.explain(
                ExplainRequest(
                    document_type=DocumentType.LAB_REPORT,
                    raw_text="raw",
                    lab_tests=[GLUCOSE],
                )
            )
        )
        assert result.summary == "s"
        doc_type, raw, labs, context = provider.calls[0]
        assert doc_type == DocumentType.LAB_REPORT
        assert raw == "raw"
        assert labs == [GLUCOSE]
        assert "Glucose" in context

    def test_enriches_labs_from_knowledge_store(self):
        provider = FakeProvider()
        orch = Orchestrator(llm_provider=provider)
        run(
            orch.explain(
                ExplainRequest(
                    document_type=DocumentType.LAB_REPORT,
                    raw_text="Glucose 95 mg/dL",
                    lab_tests=[],
                )
            )
        )
        _, _, labs, context = provider.calls[0]
        assert labs[0].name == "Glucose"
        assert labs[0].reference_range == "70-99"
        assert labs[0].status == LabResultStatus.WITHIN_RANGE
        assert "MedExplain Curated Reference Ranges" in context

    def test_parses_labs_from_raw_text_when_none_supplied(self):
        provider = FakeProvider()
        orch = Orchestrator(llm_provider=provider)
        run(
            orch.explain(
                ExplainRequest(
                    document_type=DocumentType.LAB_REPORT,
                    raw_text=PDF_TEXT,
                    lab_tests=[],
                )
            )
        )
        _, _, labs, _ = provider.calls[0]
        assert [t.name for t in labs] == ["Glucose"]

    def test_safety_gate_injects_disclaimer_when_missing(self):
        provider = FakeProvider(analysis=AiAnalysis(summary="s", disclaimer=""))
        orch = Orchestrator(llm_provider=provider)
        result = run(
            orch.explain(
                ExplainRequest(
                    document_type=DocumentType.LAB_REPORT,
                    raw_text="x",
                    lab_tests=[GLUCOSE],
                )
            )
        )
        assert result.disclaimer == DISCLAIMER

    def test_safety_gate_filters_unrelated_concerns(self):
        analysis = AiAnalysis(
            summary="s",
            disclaimer="d",
            concerns=["Unrelated issue", "Glucose (95) may need review"],
            items=[AnalysisItem(test_name="Glucose", explanation="e", category=AnalysisCategory.EDUCATION)],
        )
        provider = FakeProvider(analysis=analysis)
        orch = Orchestrator(llm_provider=provider)
        result = run(
            orch.explain(
                ExplainRequest(
                    document_type=DocumentType.LAB_REPORT,
                    raw_text="x",
                    lab_tests=[GLUCOSE],
                )
            )
        )
        assert result.concerns == ["Glucose (95) may need review"]


class TestExtractionPipeline:
    def test_extract_document_keeps_lab_tests_empty(self):
        orch = Orchestrator()
        resp = orch.extract_document(build_pdf(PDF_TEXT), "report.pdf")
        assert resp.document_type == DocumentType.LAB_REPORT
        assert resp.extraction_method == ExtractionMethod.PDF_TEXT
        assert resp.lab_tests == []

    def test_parse_lab_report_infers_type_and_parses(self):
        orch = Orchestrator()
        resp = orch.parse_lab_report(PDF_TEXT)
        assert resp.document_type == DocumentType.LAB_REPORT
        assert resp.extraction_method == ExtractionMethod.NONE
        assert [t.name for t in resp.lab_tests] == ["Glucose"]
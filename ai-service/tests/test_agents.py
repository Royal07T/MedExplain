import asyncio

import pytest

from app.schemas.extraction import DocumentType, ExtractionMethod
from app.schemas.medication import MedicationExtraction
from app.services.ai.agents import DocumentAgent, LabAgent, MedicationAgent, UnsupportedFileTypeError
from app.services.ai.agents.medication_agent import RxParser
from app.services.llm.gateway import GatewayConfig, LLMGateway

from tests.helpers import build_pdf

PDF_TEXT = (
    "Test Result Unit Reference Range Status\n"
    "Glucose 95 mg/dL 70-99 Normal\n"
    "Cholesterol 240 mg/dL < 200 High\n"
)


def run(coro):
    return asyncio.run(coro)


class TestDocumentAgent:
    def test_extracts_pdf_text(self):
        result = DocumentAgent().extract(build_pdf(PDF_TEXT), "report.pdf")
        assert result.method == ExtractionMethod.PDF_TEXT
        assert result.document_type == DocumentType.LAB_REPORT
        assert "Glucose" in result.text
        assert result.warnings == []

    def test_rejects_unsupported_type(self):
        with pytest.raises(UnsupportedFileTypeError):
            DocumentAgent().extract(b"hello", "notes.txt")

    def test_blank_pdf_reports_warning(self):
        result = DocumentAgent().extract(build_pdf(""), "blank.pdf")
        assert result.method == ExtractionMethod.NONE
        assert result.text == ""
        assert result.warnings


class TestLabAgent:
    def test_deterministic_parse(self):
        tests = LabAgent().parse(PDF_TEXT)
        by_name = {t.name: t for t in tests}
        assert by_name["Glucose"].value == "95"
        assert by_name["Cholesterol"].status.value == "above_range"

    def test_extract_prefers_deterministic_without_llm(self):
        tests = run(LabAgent().extract(PDF_TEXT))
        assert len(tests) == 2

    def test_parse_with_llm_via_stub_gateway(self):
        from app.services.llm.gateway import ProviderConfig

        config = GatewayConfig(
            default_provider="stub",
            providers={"stub": ProviderConfig(name="stub", model="stub")},
        )
        agent = LabAgent(gateway=LLMGateway(config))
        tests = run(agent.parse_with_llm("some text"))
        assert isinstance(tests, list)


class TestMedicationAgent:
    def test_rx_parser_extracts_common_phrases(self):
        text = (
            "Metformin 500 mg tablet twice daily\n"
            "Take Lisinopril 10 mg oral once a day\n"
        )
        meds = RxParser.parse(text)
        assert len(meds) == 2
        metformin = next(m for m in meds if m.name.lower() == "metformin")
        assert metformin.strength == "500 mg"
        assert metformin.frequency == "twice daily"
        lisinopril = next(m for m in meds if m.name.lower() == "lisinopril")
        assert lisinopril.route == "oral"

    def test_extract_without_llm_fallback(self):
        agent = MedicationAgent()
        result = run(agent.extract("nothing here"))
        assert isinstance(result, MedicationExtraction)
        assert result.medications == []

    def test_extract_llm_fallback_via_stub_gateway(self):
        from app.services.llm.gateway import ProviderConfig

        config = GatewayConfig(
            default_provider="stub",
            providers={"stub": ProviderConfig(name="stub", model="stub")},
        )
        agent = MedicationAgent(gateway=LLMGateway(config))
        result = run(agent.extract("nothing here", llm_fallback=True))
        assert isinstance(result, MedicationExtraction)
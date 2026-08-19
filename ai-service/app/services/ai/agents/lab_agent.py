"""Lab Agent — wraps the deterministic lab parser with an optional LLM fallback.

The deterministic parser wins on the happy path. When it yields no results, the
agent can request structured JSON from the LLM gateway validated against the
existing :class:`LabTest` schema.
"""

from app.schemas.extraction import LabTest, LabTestList
from app.services.extraction.lab_parser import parse_lab_report
from app.services.llm.gateway import ChatMessage, LLMGateway, Role

_SYSTEM_PROMPT = (
    "You are MedExplain. Extract laboratory tests from a medical report into "
    "strict JSON. Rules: never invent values or reference ranges not present in "
    "the input; output one item per test; include name, value, unit, reference "
    "range (if present), and status. Return JSON matching the shape "
    '{"tests": [{"name": string, "value": string, "unit": string|null, '
    '"reference_range": string|null, "status": "within_range" | "above_range" | '
    '"below_range" | "positive" | "negative" | "unknown"}]}.'
)


class LabAgent:
    def __init__(self, gateway: LLMGateway | None = None):
        self._gateway = gateway

    def _gateway_instance(self) -> LLMGateway:
        if self._gateway is not None:
            return self._gateway
        from app.services.llm.factory import get_llm_gateway

        return get_llm_gateway()

    def parse(self, raw_text: str) -> list[LabTest]:
        """Deterministic parsing only (no LLM)."""
        return parse_lab_report(raw_text)

    async def extract(
        self,
        raw_text: str,
        *,
        llm_fallback: bool = False,
    ) -> list[LabTest]:
        tests = parse_lab_report(raw_text)
        if tests or not llm_fallback or not raw_text.strip():
            return tests
        return await self.parse_with_llm(raw_text)

    async def parse_with_llm(self, raw_text: str) -> list[LabTest]:
        messages = [
            ChatMessage(role=Role.SYSTEM, content=_SYSTEM_PROMPT),
            ChatMessage(
                role=Role.USER,
                content=f"Extract laboratory tests from this report:\n\n{raw_text[:8000]}",
            ),
        ]
        result = await self._gateway_instance().generate_json(
            messages,
            task="extract_labs",
            response_schema=LabTestList,
        )
        return list(result.tests)
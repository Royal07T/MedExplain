"""Medication Agent (roadmap #6) — deterministic Rx parser + optional LLM fallback.

Educational only. Interactions / side-effect content is out of scope for this
agent and must come from the RAG layer or be marked "ask your clinician".
"""

import re

from app.schemas.medication import Medication, MedicationExtraction
from app.services.llm.gateway import ChatMessage, LLMGateway, Role

_STRENGTH_RE = re.compile(
    r"(?P<strength>\d+(?:[.,]\d+)?\s*(?:mg|mcg|µg|g|ml|units?|IU|meq|mmol))",
    re.IGNORECASE,
)
_DOSE_RE = re.compile(
    r"\b(?P<dose>\d+(?:[.,]\d+)?)\s*(?:tablets?|capsules?|tabs?|caps?|puffs?|"
    r"drops?|units?|mg|mcg|ml|injections?|squirts?)",
    re.IGNORECASE,
)
_FREQUENCY_RE = re.compile(
    r"\b(?P<freq>(?:once|twice|three times|2|3)?\s*(?:daily|a\s+day|per\s+day)|"
    r"every\s+\d+\s*(?:hours?|days?)|qhs|qid|tid|bid|prn)\b",
    re.IGNORECASE,
)
_ROUTE_RE = re.compile(
    r"\b(?P<route>oral|by mouth|po|topical|intravenous|iv|subcutaneous|sc|"
    r"intramuscular|im|sublingual|inhaled|nasal|rectal|ophthalmic|otic)\b",
    re.IGNORECASE,
)
_FORM_RE = re.compile(
    r"\b(?P<form>tablet|tablets|caplet|caplets|capsule|capsules|injection|injections|"
    r"inhaler|cream|ointment|syrup|suspension|solution|patch|suppository|drops)\b",
    re.IGNORECASE,
)
_NAME_RE = re.compile(
    r"(?:take\s+)?(?P<name>[A-Za-z][A-Za-z\s\-]{1,40}?)\s+\d+",
    re.IGNORECASE,
)

_SYSTEM_PROMPT = (
    "You are MedExplain. Extract medications from a prescription or medication "
    "list into strict JSON. Rules: educational only; never diagnose, never "
    "suggest interactions; use only what the text states. Return JSON matching "
    '{"medications": [{"name": string, "strength": string|null, '
    '"dosage_form": string|null, "dose": string|null, "frequency": string|null, '
    '"route": string|null, "prescriber": string|null, "indications": string|null, '
    '"start_date": string|null, "end_date": string|null}]}.'
)


class RxParser:
    """Deterministic regex parser for common medication phrases."""

    @staticmethod
    def parse(raw_text: str) -> list[Medication]:
        medications: list[Medication] = []
        for line in raw_text.splitlines():
            med = _parse_line(line)
            if med is not None and not any(m.name.lower() == med.name.lower() for m in medications):
                medications.append(med)
        return medications


def _parse_line(line: str) -> Medication | None:
    name_match = _NAME_RE.search(line)
    if not name_match:
        return None
    name = " ".join(name_match.group("name").strip().split()).rstrip(":")
    if not name or len(name) > 80:
        return None
    strength = _match_or_none(_STRENGTH_RE, line, "strength")
    dose = _match_or_none(_DOSE_RE, line, "dose")
    frequency = _match_or_none(_FREQUENCY_RE, line, "freq")
    route = _match_or_none(_ROUTE_RE, line, "route")
    form = _match_or_none(_FORM_RE, line, "form")
    return Medication(
        name=name,
        strength=strength,
        dosage_form=form,
        dose=dose,
        frequency=frequency,
        route=route,
    )


def _match_or_none(pattern: re.Pattern[str], line: str, group: str) -> str | None:
    match = pattern.search(line)
    return match.group(group) if match else None


class MedicationAgent:
    def __init__(self, gateway: LLMGateway | None = None):
        self._gateway = gateway

    def _gateway_instance(self) -> LLMGateway:
        if self._gateway is not None:
            return self._gateway
        from app.services.llm.factory import get_llm_gateway

        return get_llm_gateway()

    def parse(self, raw_text: str) -> list[Medication]:
        return RxParser.parse(raw_text)

    async def extract(
        self,
        raw_text: str,
        *,
        llm_fallback: bool = False,
    ) -> MedicationExtraction:
        medications = self.parse(raw_text)
        warnings: list[str] = []
        if not medications and llm_fallback and raw_text.strip():
            messages = [
                ChatMessage(role=Role.SYSTEM, content=_SYSTEM_PROMPT),
                ChatMessage(
                    role=Role.USER,
                    content=f"Extract medications from this text:\n\n{raw_text[:8000]}",
                ),
            ]
            result = await self._gateway_instance().generate_json(
                messages,
                task="extract_medications",
                response_schema=MedicationExtraction,
            )
            medications = list(result.medications)
            warnings = list(result.warnings)
        return MedicationExtraction(medications=medications, warnings=warnings)
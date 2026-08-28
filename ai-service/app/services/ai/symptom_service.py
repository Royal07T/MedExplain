"""Virtual Health Assistant service — deterministic symptom triage.

Maps free-text symptoms to a care-urgency band using curated keyword lists and
red-flag detection. The output never diagnoses a condition; it only advises on
urgency of seeking care and offers educational next steps. Fully offline — no
LLM and no external service.
"""

import re

from app.schemas.assistant import SymptomCheckRequest, SymptomCheckResponse, SymptomHit
from app.services.llm.factory import DISCLAIMER

# Symptom → (category, urgent) tuples. Urgent symptoms warrant prompt care.
_SYMPTOM_TABLE: list[tuple[re.Pattern, str, str, bool]] = [
    # Emergency / life-threatening red flags
    (re.compile(r"\bchest pain\b"), "chest pain", "emergency", True),
    (re.compile(r"\bchest tightness\b"), "chest tightness", "emergency", True),
    (re.compile(r"\bsevere bleeding\b"), "severe bleeding", "emergency", True),
    (re.compile(r"\bdifficulty breathing\b"), "difficulty breathing", "emergency", True),
    (re.compile(r"\bcan'?t breathe\b"), "shortness of breath", "emergency", True),
    (re.compile(r"\bpassing out\b"), "fainting", "emergency", True),
    (re.compile(r"\bunconscious\b"), "loss of consciousness", "emergency", True),
    (re.compile(r"\bstroke\b"), "possible stroke", "emergency", True),
    (re.compile(r"\bsuicide\b"), "suicidal thoughts", "emergency", True),
    (re.compile(r"\bself-?harm\b"), "self-harm", "emergency", True),
    (re.compile(r"\bsudden confusion\b"), "sudden confusion", "emergency", True),
    (re.compile(r"\bslurred speech\b"), "slurred speech", "emergency", True),
    # Urgent symptoms
    (re.compile(r"\bhigh fever\b"), "high fever", "urgent", True),
    (re.compile(r"\bfever\b"), "fever", "moderate", False),
    (re.compile(r"\bsevere (headache|abdominal|back) pain\b"), "severe pain", "urgent", True),
    (re.compile(r"\bvomiting blood\b"), "vomiting blood", "urgent", True),
    (re.compile(r"\bblood in (stool|urine)\b"), "blood in stool/urine", "urgent", True),
    (re.compile(r"\bspreading rash\b"), "spreading rash", "urgent", True),
    (re.compile(r"\bsevere headache\b"), "severe headache", "urgent", True),
    (re.compile(r"\bdizziness\b"), "dizziness", "moderate", False),
    (re.compile(r"\bbright red bleeding\b"), "heavy bleeding", "urgent", True),
    # Moderate symptoms
    (re.compile(r"\bheadache\b"), "headache", "moderate", False),
    (re.compile(r"\bnausea\b"), "nausea", "moderate", False),
    (re.compile(r"\bvomiting\b"), "vomiting", "moderate", False),
    (re.compile(r"\bdiarrhoea|diarrhea\b"), "diarrhoea", "moderate", False),
    (re.compile(r"\bcough\b"), "cough", "moderate", False),
    (re.compile(r"\bsore throat\b"), "sore throat", "moderate", False),
    (re.compile(r"\bpain\b"), "pain", "general", False),
    (re.compile(r"\bfatigue\b"), "fatigue", "general", False),
    (re.compile(r"\bweakness\b"), "weakness", "general", False),
    (re.compile(r"\binsomnia|trouble sleeping\b"), "sleep problems", "general", False),
    (re.compile(r"\banxiety\b"), "anxiety", "general", False),
    (re.compile(r"\bstress\b"), "stress", "general", False),
]

_EMERGENCY_URGENT_MSG = (
    "The symptoms you described can be serious. Please seek medical care "
    "promptly or contact emergency services if needed. I can only provide "
    "general educational information and cannot assess your condition."
)

_CONSIDERATE_MSG = (
    "Based on the symptoms you described I'd suggest checking in with a "
    "clinician to be safe. I can only provide general educational information, "
    "not a diagnosis."
)

_GENERAL_MSG = (
    "The symptoms you described sound common and often resolve on their own, "
    "but if they persist or worsen please see a clinician. I can only provide "
    "general educational information, not a diagnosis."
)


class SymptomService:
    def check(self, request: SymptomCheckRequest) -> SymptomCheckResponse:
        low = request.text.lower()

        hits: list[SymptomHit] = []
        red_flags: list[str] = []
        emergency = False
        urgent = False

        for pattern, label, category, is_urgent in _SYMPTOM_TABLE:
            if pattern.search(low):
                hits.append(SymptomHit(symptom=label, category=category, urgent=is_urgent))
                if is_urgent and category == "emergency":
                    emergency = True
                    red_flags.append(label)
                elif is_urgent:
                    urgent = True
                    red_flags.append(label)

        if emergency:
            return SymptomCheckResponse(
                urgency="emergency",
                message=_EMERGENCY_URGENT_MSG,
                red_flags=red_flags,
                matched=hits,
                disclaimer=DISCLAIMER,
            )

        if urgent:
            return SymptomCheckResponse(
                urgency="urgent",
                message=_EMERGENCY_URGENT_MSG,
                red_flags=red_flags,
                matched=hits,
                disclaimer=DISCLAIMER,
            )

        has_moderate = any(h.category == "moderate" for h in hits)

        return SymptomCheckResponse(
            urgency="moderate" if has_moderate or hits else "general",
            message=_CONSIDERATE_MSG if (has_moderate or hits) else _GENERAL_MSG,
            red_flags=red_flags,
            matched=hits,
            disclaimer=DISCLAIMER,
        )


def get_symptom_service() -> SymptomService:
    return SymptomService()

"""Deterministic, offline assistant for the radiology reading workflow.

This service is rule-based and never calls a live model. It inspects an
imaging order (modality, body region, clinical indication, priority, status,
ICD code, radiation dose, image count) and returns:

- a recommended reading priority (stat / urgent / routine)
- a readable rationale explaining that priority
- actionable reading recommendations
- quality / anomaly hints (e.g. missing indication, high radiation dose,
  duplicate or low-yield ordering signals)

Every output is derived from transparent heuristics so a clinician can see
the reasoning behind a suggestion.
"""

from app.schemas.imaging import (
    ImagingAnalysisRequest,
    ImagingAnalysisResponse,
    Recommendation,
)
from app.services.llm.factory import DISCLAIMER

# Reading-priority modifier keyword groups keyed on the clinical indication.
# Stat-level signals suggest potential life/immediate-function threats; urgent
# signals suggest time-sensitive findings even if the stated priority is routine.
_STAT_INDICATION_KEYWORDS = {
    "stroke": ["stroke", "cva", "thrombosis", "embolus", "embolism", "ischem", "bleed"],
    "mass": ["mass", "tumor", "tumour", "neoplasm", "lesion", "cancer", "malignan"],
    "aortic": ["aorta", "dissect", "aneurysm", "aortic"],
    "cardiac": ["acute coronary", "chest pain", "troponin", "myocard", "infarct"],
    "neuro": ["seizure", "trauma", "fracture skull", "edema", "effusion"],
    "obstruction": ["obstruction", "perforation", "free air", "ischemic bowel"],
}

_URGENT_INDICATION_KEYWORDS = {
    "infection": ["infection", "pneumonia", "abscess", "sepsis", "septic"],
    "fracture": ["fracture", "dislocat"],
    "dvt": ["dvt", "deep vein", "pulmonary emboli", "pe "],
    "obstruction_mild": ["occlusion", "stricture", "stone", "calculus"],
    "appendix": ["appendic", "cholecyst", "pancreat"],
}


class ImagingService:
    """Rule-based radiology reading-workflow assistant."""

    VALID_PRIORITIES = {"routine", "urgent", "stat"}

    def __init__(self) -> None:
        self._disclaimer = DISCLAIMER

    def analyze(self, request: ImagingAnalysisRequest) -> ImagingAnalysisResponse:
        if request.priority not in self.VALID_PRIORITIES:
            request.priority = "routine"

        stated_priority = request.priority
        indication = (request.clinical_indication or "").lower()

        stat_hits = []
        for label, keywords in _STAT_INDICATION_KEYWORDS.items():
            if any(keyword in indication for keyword in keywords):
                stat_hits.append(label)

        urgent_hits = []
        for label, keywords in _URGENT_INDICATION_KEYWORDS.items():
            if any(keyword in indication for keyword in keywords):
                urgent_hits.append(label)

        level, rationale = self._recommend_priority(stated_priority, stat_hits, urgent_hits)
        recommendations = self._recommendations(
            request, stated_priority, stat_hits, urgent_hits
        )
        quality_hints = self._quality_hints(request, level)

        return ImagingAnalysisResponse(
            priority_level=level,
            rationale=rationale,
            recommendations=recommendations,
            quality_hints=quality_hints,
            disclaimer=self._disclaimer,
            analyzed_modality=request.modality,
        )

    def _recommend_priority(self, stated, stat_hits, urgent_hits):
        if stat_hits or stated == "stat":
            if stat_hits:
                reason = "indication keywords suggest an acute/time-sensitive process"
                if stated == "stat":
                    reason = "order is already stat and indication is consistent"
                return "stat", (
                    f"Recommended reading priority is 'stat': {reason} "
                    f"(matched: {', '.join(stat_hits)})."
                )
            return "stat", (
                "Recommended reading priority is 'stat' to match the stated "
                "stat urgency on the order."
            )
        if urgent_hits or stated == "urgent":
            reason = "indication suggests a time-sensitive finding"
            if stated == "urgent":
                reason = "order is already marked urgent"
            return "urgent", (
                f"Recommended reading priority is 'urgent': {reason} "
                f"(matched: {', '.join(urgent_hits) if urgent_hits else 'stated priority'})."
            )
        return "routine", (
            "Recommended reading priority is 'routine': no acute red-flag "
            "signals in the indication and the stated priority is not urgent/stat."
        )

    def _recommendations(self, request, stated, stat_hits, urgent_hits):
        recs = []
        if stat_hits:
            recs.append(Recommendation(
                title="Escalate reading",
                detail=(
                    "The clinical indication contains terms consistent with an "
                    "acute event. Consider flagging this study for immediate "
                    "radiology review and notifying the ordering clinician "
                    "of the potential finding."
                ),
                priority_impact="high",
            ))
        elif urgent_hits:
            recs.append(Recommendation(
                title="Prioritize within the session",
                detail=(
                    "The indication suggests a time-sensitive but not "
                    "immediately life-threatening process. Schedule this "
                    "study ahead of routine work in the current reading queue."
                ),
                priority_impact="medium",
            ))

        modality = request.modality.lower()
        body_region = (request.body_region or "").lower()
        if request.radiation_dose_mgy is not None and request.radiation_dose_mgy > 20:
            recs.append(Recommendation(
                title="Review radiation dose",
                detail=(
                    f"Radiation dose of {request.radiation_dose_mgy:.1f} mGy is "
                    "high for this study. Confirm the exposure settings and "
                    "document justification before reporting."
                ),
                priority_impact="low",
            ))

        if modality and body_region and modality not in body_region and "chest" in body_region and "x" not in modality and "ct" not in modality:
            recs.append(Recommendation(
                title="Verify modality-region match",
                detail=(
                    f"The body region '{request.body_region}' may be mismatched "
                    f"with the selected modality '{request.modality}'. Confirm "
                    "the ordering intent before finalizing."
                ),
                priority_impact="low",
            ))

        if not request.clinical_indication or not request.clinical_indication.strip():
            recs.append(Recommendation(
                title="Capture clinical indication",
                detail=(
                    "No clinical indication was recorded. Adding the indication "
                    "improves appropriateness review and reporting accuracy."
                ),
                priority_impact="medium",
            ))

        return recs

    def _quality_hints(self, request, level):
        hints = []
        if request.image_count is not None and request.image_count < 1:
            hints.append("Image count is zero; no images have been acquired yet.")
        if request.status in ("pending", "scheduled") and level == "stat":
            hints.append("Stat study still pending/scheduled; verify scheduling priority.")
        if not request.icd_code:
            hints.append("No ICD code attached to the order; consider adding one for reporting.")
        if request.radiation_dose_mgy is None and "x" in request.modality.lower():
            hints.append("Radiation dose not recorded for a radiographic study.")
        if not hints:
            hints.append("No quality anomalies detected for this order.")
        return hints


def get_imaging_service() -> ImagingService:
    return ImagingService()

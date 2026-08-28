"""Predictive analytics service — transparent, rule-based heuristic scoring.

Real ML would require labeled clinical outcomes and careful validation; in
their absence these are intentionally simple, auditable point systems. Every
score returns its contributing factors so a clinician can see the reasoning.
Scores are estimates to support prioritisation, never diagnoses.
"""

from app.schemas.predictive import (
    DeteriorationRequest,
    DeteriorationResponse,
    LengthOfStayRequest,
    LengthOfStayResponse,
    ReadmissionRequest,
    ReadmissionResponse,
    VitalSigns,
)

_CHRONIC = {
    "hypertension", "type 2 diabetes", "heart failure", "copd", "asthma",
    "chronic kidney disease", "atrial fibrillation", "coronary artery disease",
    "stroke", "depression", "anxiety", "obesity", "hyperlipidemia",
    "hypothyroidism", "osteoarthritis", "gout", "anemia",
}


class PredictiveService:
    # ------------------------------------------------------------------ risk
    def readmission_risk(self, request: ReadmissionRequest) -> ReadmissionResponse:
        score = 0
        contributors: list[str] = []

        if request.prior_admissions_90d >= 1:
            score += 10 * min(request.prior_admissions_90d, 3)
            contributors.append(
                f"{request.prior_admissions_90d} admission(s) in the last 90 days"
            )
        if request.prior_admissions_12m >= 3:
            score += 10
            contributors.append(f"{request.prior_admissions_12m} admissions in the last year")

        chronic = [c for c in request.comorbidities if c.strip().lower() in _CHRONIC]
        if chronic:
            score += min(5 * len(chronic), 20)
            contributors.append(f"{len(chronic)} chronic condition(s)")

        if request.length_of_stay_days >= 7:
            score += 10
            contributors.append("prolonged length of stay (7+ days)")

        if request.polypharmacy:
            score += 5
            contributors.append("polypharmacy")

        if request.hba1c_uncontrolled:
            score += 5
            contributors.append("uncontrolled diabetes")

        if request.hemoglobin_low:
            score += 5
            contributors.append("low haemoglobin")

        if not request.discharge_to_home:
            score += 5
            contributors.append("discharge disposition other than home")

        score = min(score, 100)

        return ReadmissionResponse(
            score=score,
            level=self._risk_level(score),
            contributors=contributors,
        )

    # --------------------------------------------------------- length of stay
    def predict_length_of_stay(self, request: LengthOfStayRequest) -> LengthOfStayResponse:
        days = 1.0
        drivers: list[str] = []

        acuity_bump = {"resuscitation": 4.0, "emergent": 3.0, "urgent": 1.5}
        if request.acuity.lower() in acuity_bump:
            days += acuity_bump[request.acuity.lower()]
            drivers.append(f"acuity {request.acuity}")

        if request.admission_type.lower() == "emergency":
            days += 1.5
            drivers.append("emergency admission")

        chronic = [c for c in request.comorbidities if c.strip().lower() in _CHRONIC]
        if chronic:
            bump = min(len(chronic) * 0.75, 3.0)
            days += bump
            drivers.append(f"{len(chronic)} chronic condition(s)")

        if request.icu_required:
            days += 2.0
            drivers.append("ICU required")

        if request.surgery_required:
            days += 1.5
            drivers.append("surgery required")

        if request.age and request.age >= 75:
            days += 1.0
            drivers.append("age 75+")

        confidence = min(0.4 + 0.05 * len(drivers), 0.85)
        spread = max(0.75, 0.35 * days)

        return LengthOfStayResponse(
            predicted_days=round(days, 1),
            range_min=round(max(0, days - spread), 1),
            range_max=round(days + spread, 1),
            confidence=round(confidence, 2),
            drivers=drivers,
        )

    # ----------------------------------------------------------- deterioration
    def deterioration_risk(self, request: DeteriorationRequest) -> DeteriorationResponse:
        v = request.vitals
        components: dict[str, int] = {}
        red_flags: list[str] = []

        if v.respiratory_rate is not None:
            rr = v.respiratory_rate
            score = self._news_rr(rr)
            components["respiratory_rate"] = score
            if score >= 3:
                red_flags.append("abnormal respiratory rate")

        if v.spo2 is not None:
            score = self._news_spo2(v.spo2, v.on_oxygen)
            components["spo2"] = score
            if score >= 3:
                red_flags.append("low oxygen saturation")

        if v.systolic_bp is not None:
            score = self._news_sbp(v.systolic_bp)
            components["systolic_bp"] = score
            if score >= 3:
                red_flags.append("abnormal systolic blood pressure")

        if v.heart_rate is not None:
            hr = v.heart_rate
            score = self._news_hr(hr)
            components["heart_rate"] = score
            if score >= 3:
                red_flags.append("abnormal heart rate")

        if v.temperature_c is not None:
            temp = v.temperature_c
            score = self._news_temp(temp)
            components["temperature"] = score
            if score >= 3:
                red_flags.append("abnormal temperature")

        if v.conscious is not False:
            components["consciousness"] = 0
        else:
            components["consciousness"] = 3
            red_flags.append("reduced consciousness")

        total = sum(components.values())
        level = self._early_warning_level(total)

        return DeteriorationResponse(
            score=total,
            level=level,
            components=components,
            red_flags=red_flags,
        )

    # ------------------------------------------------------------ helpers

    @staticmethod
    def _risk_level(score: int) -> str:
        if score >= 40:
            return "high"
        if score >= 20:
            return "moderate"
        return "low"

    @staticmethod
    def _early_warning_level(score: int) -> str:
        if score >= 7:
            return "critical"
        if score >= 5:
            return "high"
        if score >= 3:
            return "moderate"
        return "low"

    @staticmethod
    def _news_rr(rr: int) -> int:
        if rr <= 8 or rr >= 25:
            return 3
        if rr <= 11 or rr >= 21:
            return 1
        return 0

    @staticmethod
    def _news_spo2(spo2: int, on_oxygen: bool) -> int:
        scale = 0 if not on_oxygen else 2
        if spo2 <= 91:
            return 3 + scale
        if spo2 <= 93:
            return 2 + scale
        if spo2 <= 95:
            return 1 + scale
        return 0

    @staticmethod
    def _news_sbp(sbp: int) -> int:
        if sbp <= 90:
            return 3
        if sbp <= 100:
            return 2
        if sbp <= 110:
            return 1
        if sbp >= 220:
            return 3
        return 0

    @staticmethod
    def _news_hr(hr: int) -> int:
        if hr <= 40 or hr >= 131:
            return 3
        if hr <= 50 or hr >= 111:
            return 2
        if hr <= 90:
            return 0
        return 1

    @staticmethod
    def _news_temp(temp: float) -> int:
        if temp <= 35.0 or temp >= 39.1:
            return 3
        if temp <= 36.0 or temp >= 38.1:
            return 1
        return 0


def get_predictive_service() -> PredictiveService:
    return PredictiveService()

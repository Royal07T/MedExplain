"""Curated, source-backed seed content for the knowledge layer.

Educational reference ranges and documents are human-curated (not model-derived)
and carry provenance. Ranges are illustrative adult reference values; the LLM
must never invent ranges — this store is the only range source.
"""

from app.services.ai.knowledge.base import KnowledgeDocument, KnowledgeSource

_SOURCE = KnowledgeSource(
    name="MedExplain Curated Reference Ranges",
    version="1.0",
    published_at="2026-01-01",
)

_EDU_SOURCE = KnowledgeSource(
    name="MedExplain Curated Patient Education",
    version="1.0",
    published_at="2026-01-01",
)


def seed_ranges():
    from app.services.ai.knowledge.base import RangeEntry

    return [
        RangeEntry(name="Glucose", aliases=["blood glucose", "glucose (fpg)"], low=70, high=99, unit="mg/dL", source=_SOURCE, description="Fasting plasma glucose."),
        RangeEntry(name="Hemoglobin", aliases=["hgb", "hb"], low=12.0, high=16.0, unit="g/dL", source=_SOURCE),
        RangeEntry(name="Hematocrit", aliases=["hct"], low=36, high=46, unit="%", source=_SOURCE),
        RangeEntry(name="Creatinine", aliases=["creat"], low=0.7, high=1.3, unit="mg/dL", source=_SOURCE),
        RangeEntry(name="Sodium", aliases=["na"], low=135, high=145, unit="mmol/L", source=_SOURCE),
        RangeEntry(name="Potassium", aliases=["k", "k+"], low=3.5, high=5.0, unit="mmol/L", source=_SOURCE),
        RangeEntry(name="TSH", aliases=["thyroid stimulating hormone", "thyrotropin"], low=0.4, high=4.0, unit="uIU/mL", source=_SOURCE),
        RangeEntry(name="ALT", aliases=["alanine aminotransferase"], low=7, high=56, unit="U/L", source=_SOURCE),
        RangeEntry(name="AST", aliases=["aspartate aminotransferase"], low=10, high=40, unit="U/L", source=_SOURCE),
        RangeEntry(name="Total Cholesterol", aliases=["cholesterol", "tc"], operator="<", bound=200, unit="mg/dL", source=_SOURCE),
        RangeEntry(name="LDL Cholesterol", aliases=["ldl", "ldl-c"], operator="<", bound=100, unit="mg/dL", source=_SOURCE),
        RangeEntry(name="HDL Cholesterol", aliases=["hdl", "hdl-c"], low=40, high=60, unit="mg/dL", source=_SOURCE),
        RangeEntry(name="Triglycerides", aliases=["trig", "tg"], operator="<", bound=150, unit="mg/dL", source=_SOURCE),
        RangeEntry(name="HbA1c", aliases=["a1c", "glycated hemoglobin"], low=4.0, high=5.6, unit="%", source=_SOURCE),
        RangeEntry(name="Ferritin", low=30, high=400, unit="ng/mL", source=_SOURCE),
        RangeEntry(name="Vitamin D", aliases=["25-oh vitamin d", "25 hydroxyvitamin d"], low=30, high=100, unit="ng/mL", source=_SOURCE),
        RangeEntry(name="Uric Acid", aliases=["urate"], low=3.5, high=7.2, unit="mg/dL", source=_SOURCE),
        RangeEntry(name="WBC", aliases=["white blood cell count", "white blood cells"], low=4.5, high=11.0, unit="x10^9/L", source=_SOURCE),
        RangeEntry(name="Platelets", aliases=["platelet count", "plt"], low=150, high=400, unit="x10^9/L", source=_SOURCE),
        RangeEntry(name="Bilirubin", aliases=["total bilirubin"], low=0.1, high=1.2, unit="mg/dL", source=_SOURCE),
        RangeEntry(name="CRP", aliases=["c-reactive protein"], operator="<", bound=3, unit="mg/L", source=_SOURCE),
    ]


def seed_documents():
    return [
        KnowledgeDocument(
            id="cholesterol-panel",
            title="Understanding your cholesterol panel",
            content=(
                "A lipid panel reports total cholesterol, LDL, HDL, and triglycerides. "
                "LDL is often called 'bad' cholesterol and HDL 'good' cholesterol. "
                "High LDL and triglycerides, or low HDL, are associated with increased "
                "cardiovascular risk. Reference values are educational; your clinician "
                "interprets them with your full risk profile."
            ),
            keywords=["cholesterol", "ldl", "hdl", "triglycerides", "lipid", "panel", "heart"],
            source=_EDU_SOURCE,
        ),
        KnowledgeDocument(
            id="glucose-hba1c",
            title="Blood glucose and HbA1c basics",
            content=(
                "Fasting glucose reflects blood sugar at one moment, while HbA1c "
                "estimates average glucose over roughly three months. A single high "
                "reading is not a diagnosis; repeat testing and clinical assessment "
                "are needed before any conclusions."
            ),
            keywords=["glucose", "hba1c", "a1c", "sugar", "diabetes", "fasting"],
            source=_EDU_SOURCE,
        ),
        KnowledgeDocument(
            id="thyroid-function",
            title="Thyroid function tests",
            content=(
                "TSH is the first-line screening test for thyroid function. High TSH "
                "with low free hormone suggests underactive thyroid; low TSH with high "
                "hormone suggests overactive thyroid. Interpretation always requires "
                "clinical context."
            ),
            keywords=["tsh", "thyroid", "thyrotropin", "hormone"],
            source=_EDU_SOURCE,
        ),
    ]
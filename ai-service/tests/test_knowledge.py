from app.schemas.extraction import LabResultStatus, LabTest
from app.services.ai.knowledge import KnowledgeBase, KnowledgeStore, build_default_store
from app.services.ai.knowledge.seed import seed_documents, seed_ranges


def _store() -> KnowledgeStore:
    return build_default_store()


class TestRangeGate:
    def test_lookup_known_range_by_canonical_name(self):
        entry = _store().lookup_range("Glucose")
        assert entry is not None
        assert entry.reference_range() == "70-99"
        assert entry.unit == "mg/dL"

    def test_lookup_normalizes_aliases_and_case(self):
        store = _store()
        assert store.lookup_range("  hgb ") is not None
        assert store.lookup_range("A1C") is not None
        assert store.lookup_range("HDL-C") is not None

    def test_lookup_one_sided_range_rendering(self):
        entry = _store().lookup_range("Total Cholesterol")
        assert entry.reference_range() == "< 200"

    def test_unknown_range_returns_none_never_invented(self):
        assert _store().lookup_range("Phlebotinum Assay") is None
        assert _store().lookup_range("") is None


class TestRetrieval:
    def test_keyword_search_finds_relevant_document_first(self):
        results = _store().search("cholesterol ldl hdl triglycerides")
        assert results
        assert results[0].document.id == "cholesterol-panel"

    def test_search_ignores_irrelevant_query(self):
        assert _store().search("bicycle maintenance") == []

    def test_search_k_limits_results(self):
        assert len(_store().search("cholesterol", k=2)) <= 2

    def test_get_document_by_id(self):
        store = _store()
        assert store.get_document("thyroid-function") is not None
        assert store.get_document("nope") is None


class TestEnrichLabs:
    def test_fills_missing_range_and_status_from_store(self):
        kb = KnowledgeBase()
        labs = [LabTest(name="Glucose", value="95")]
        enriched = kb.enrich_labs(labs)
        assert enriched[0].reference_range == "70-99"
        assert enriched[0].status == LabResultStatus.WITHIN_RANGE

    def test_reports_range_wins_over_store(self):
        kb = KnowledgeBase()
        labs = [LabTest(name="Glucose", value="95", reference_range="60-100", status=LabResultStatus.UNKNOWN)]
        enriched = kb.enrich_labs(labs)
        assert enriched[0].reference_range == "60-100"
        assert enriched[0].status == LabResultStatus.WITHIN_RANGE

    def test_out_of_store_range_marks_status(self):
        kb = KnowledgeBase()
        labs = [LabTest(name="TSH", value="0.3")]
        enriched = kb.enrich_labs(labs)
        assert enriched[0].reference_range == "0.4-4"
        assert enriched[0].status == LabResultStatus.BELOW_RANGE

    def test_unknown_test_stays_unknown(self):
        kb = KnowledgeBase()
        labs = [LabTest(name="Mystery Assay", value="1")]
        enriched = kb.enrich_labs(labs)
        assert enriched[0].reference_range is None
        assert enriched[0].status == LabResultStatus.UNKNOWN

    def test_does_not_mutate_input(self):
        kb = KnowledgeBase()
        labs = [LabTest(name="Glucose", value="95")]
        kb.enrich_labs(labs)
        assert labs[0].reference_range is None


class TestGroundingContext:
    def test_known_range_includes_provenance(self):
        kb = KnowledgeBase()
        context = kb.grounding_context([LabTest(name="Glucose", value="95")])
        assert "70-99" in context
        assert "MedExplain Curated Reference Ranges" in context
        assert "v1.0" in context

    def test_unknown_range_reports_not_available(self):
        kb = KnowledgeBase()
        context = kb.grounding_context([LabTest(name="Mystery Assay", value="1")])
        assert "not available in this report" in context


class TestStoreConstruction:
    def test_default_store_uses_seed_content(self):
        store = _store()
        assert store.get_document("cholesterol-panel") is not None
        assert store.lookup_range("Creatinine") is not None

    def test_custom_store_accepts_injected_content(self):
        store = KnowledgeStore(ranges=seed_ranges(), documents=seed_documents())
        assert store.lookup_range("Sodium") is not None
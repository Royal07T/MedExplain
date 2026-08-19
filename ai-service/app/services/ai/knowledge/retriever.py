"""KnowledgeBase — the service facade for the knowledge / RAG layer.

Retrieval is local and deterministic (see :class:`KnowledgeStore`). Its outputs
ground explanations and enforce the range gate: a reference range is only ever
filled from the curated store, never invented. Unknown tests stay unknown and are
reported as "not available in this report".
"""

from app.schemas.extraction import LabResultStatus, LabTest
from app.services.ai.knowledge.store import KnowledgeStore, build_default_store
from app.services.extraction.lab_parser import compare_value_to_range

_UNKNOWN_LINE = "{name}: reference range not available in this report"


class KnowledgeBase:
    def __init__(self, store: KnowledgeStore | None = None):
        self._store = store or build_default_store()

    @property
    def store(self) -> KnowledgeStore:
        return self._store

    def lookup_range(self, name: str):
        return self._store.lookup_range(name)

    def enrich_labs(self, labs: list[LabTest]) -> list[LabTest]:
        """Fill missing reference ranges from the curated store (only).

        The report's own range always wins; the store only fills gaps. Status is
        recomputed from the store range when the parser left it unknown.
        """
        enriched: list[LabTest] = []
        for lab in labs:
            entry = self._store.lookup_range(lab.name)
            reference_range = lab.reference_range
            if not reference_range and entry is not None:
                reference_range = entry.reference_range() or None

            status = lab.status
            if status == LabResultStatus.UNKNOWN and reference_range:
                status = compare_value_to_range(lab.value, reference_range)

            enriched.append(
                lab.model_copy(update={"reference_range": reference_range, "status": status})
            )
        return enriched

    def grounding_context(self, labs: list[LabTest]) -> str:
        """Provenance-backed context lines for the explain prompt (never invents)."""
        lines: list[str] = []
        for lab in labs:
            entry = self._store.lookup_range(lab.name)
            if entry is None:
                lines.append(_UNKNOWN_LINE.format(name=lab.name))
                continue
            ref = entry.reference_range()
            unit = f" {entry.unit}" if entry.unit else ""
            provenance = f"source: {entry.source.name} v{entry.source.version}"
            if ref:
                lines.append(f"{entry.name}: reference {ref}{unit} ({provenance})")
            else:
                lines.append(f"{entry.name}: no numeric reference range ({provenance})")
        return "\n".join(lines)


def get_knowledge_base() -> KnowledgeBase:
    """Build a fresh knowledge base (never cached; deterministic and keyless)."""
    return KnowledgeBase()
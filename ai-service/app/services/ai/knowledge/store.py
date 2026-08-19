"""KnowledgeStore — deterministic, source-backed retrieval.

Phase 2 uses a keyword/token-overlap index (no embedding provider or vector
store decision is required yet). The store is read-only: it never writes, and it
is the only source of reference ranges (the hard gate against fabrication).
"""

import re
from collections import Counter

from app.services.ai.knowledge.base import (
    KnowledgeDocument,
    RangeEntry,
    RetrievalResult,
)
from app.services.ai.knowledge.seed import seed_documents, seed_ranges

_STOPWORDS = {
    "a", "an", "the", "and", "or", "for", "of", "in", "on", "to", "is", "are",
    "your", "with", "this", "that", "report", "test", "value", "range",
    "reference", "result", "understanding", "basics",
}
_TOKEN_RE = re.compile(r"[A-Za-z0-9]+")


class KnowledgeStore:
    def __init__(
        self,
        ranges: list[RangeEntry] | None = None,
        documents: list[KnowledgeDocument] | None = None,
    ):
        self._ranges = ranges or seed_ranges()
        self._documents = documents or seed_documents()
        self._range_index: dict[str, RangeEntry] = {}
        for entry in self._ranges:
            keys = {entry.name, *entry.aliases}
            for key in keys:
                self._range_index[self.normalize(key)] = entry
        self._doc_by_id = {doc.id: doc for doc in self._documents}

    @staticmethod
    def normalize(name: str) -> str:
        return " ".join(name.strip().lower().split())

    # ----------------------------------------------------------- range gate

    def lookup_range(self, name: str) -> RangeEntry | None:
        """Resolve a test name to a curated range, or None (never invented)."""
        if not name:
            return None
        return self._range_index.get(self.normalize(name))

    # ------------------------------------------------------------ retrieval

    def search(self, query: str, *, k: int = 5) -> list[RetrievalResult]:
        tokens = self._tokens(query)
        if not tokens:
            return []
        scores: list[tuple[float, KnowledgeDocument]] = []
        for doc in self._documents:
            title = Counter(self._tokens(doc.title))
            keywords = Counter(self._tokens(" ".join(doc.keywords)))
            content = Counter(self._tokens(doc.content))
            score = sum(
                title.get(t, 0) * 3 + keywords.get(t, 0) * 2 + content.get(t, 0)
                for t in tokens
            )
            if score > 0:
                scores.append((score, doc))
        scores.sort(key=lambda pair: (-pair[0], pair[1].id))
        return [RetrievalResult(document=doc, score=score) for score, doc in scores[:k]]

    def get_document(self, doc_id: str) -> KnowledgeDocument | None:
        return self._doc_by_id.get(doc_id)

    @staticmethod
    def _tokens(text: str) -> list[str]:
        return [
            token
            for token in _TOKEN_RE.findall(text.lower())
            if token not in _STOPWORDS
        ]


def build_default_store() -> KnowledgeStore:
    return KnowledgeStore()
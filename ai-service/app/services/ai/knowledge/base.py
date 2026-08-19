"""Knowledge / RAG models — source-backed content with provenance.

Every reference range and educational document carries a provenance record
(source, version, published date). Nothing in this layer is model-derived.
"""

from pydantic import BaseModel, Field


class KnowledgeSource(BaseModel):
    name: str
    version: str
    published_at: str


class RangeEntry(BaseModel):
    """A curated reference range for one test.

    Ranges are either two-sided (``low``/``high``) or one-sided (``operator`` +
    ``bound``, e.g. "< 200"). ``reference_range()`` renders them in the same
    format the deterministic parser already understands.
    """

    name: str
    aliases: list[str] = Field(default_factory=list)
    low: float | None = None
    high: float | None = None
    operator: str | None = None
    bound: float | None = None
    unit: str | None = None
    source: KnowledgeSource
    description: str = ""

    def reference_range(self) -> str:
        if self.operator and self.bound is not None:
            return f"{self.operator} {self.bound:g}"
        if self.low is not None and self.high is not None:
            return f"{self.low:g}-{self.high:g}"
        return ""


class KnowledgeDocument(BaseModel):
    id: str
    title: str
    content: str
    keywords: list[str] = Field(default_factory=list)
    source: KnowledgeSource


class RetrievalResult(BaseModel):
    document: KnowledgeDocument
    score: float
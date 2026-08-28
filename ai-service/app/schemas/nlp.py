"""Natural Language Processing schemas.

All NLP operations are deterministic and offline: extractive summarization,
lexicon-based sentiment, and dictionary/regex concept extraction. They never
require a live LLM and never send raw documents to a third party.
"""

from pydantic import BaseModel, Field


class NoteSummaryRequest(BaseModel):
    text: str = Field(min_length=1, max_length=50000)
    max_sentences: int = Field(default=4, ge=1, le=10)


class NoteSummaryResponse(BaseModel):
    summary: str
    original_sentence_count: int = 0
    retained_sentence_count: int = 0


class ConceptExtractionRequest(BaseModel):
    text: str = Field(min_length=1, max_length=50000)


class Concept(BaseModel):
    type: str
    value: str
    confidence: float = 1.0


class ConceptExtractionResponse(BaseModel):
    concepts: list[Concept] = Field(default_factory=list)


class SentimentAnalysisRequest(BaseModel):
    text: str = Field(min_length=1, max_length=20000)


class SentimentAnalysisResponse(BaseModel):
    label: str
    score: float
    positive_hits: int = 0
    negative_hits: int = 0

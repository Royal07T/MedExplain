"""Natural Language Processing service — deterministic and offline.

- ``summarize``: extractive summarization. Sentences are scored by term
  frequency and position, then the top-scoring sentences are re-ordered to
  preserve the original narrative. Only text actually present in the note is
  ever returned (no generation, no invention).
- ``extract_concepts``: pulls medications and diagnoses from free text using
  curated dictionaries and simple pattern matching.
- ``analyze_sentiment``: lexicon-based sentiment (positive/negative word
  matching) producing a label and a bounded score.

These functions never call a live model and never expose raw content to any
external service.
"""

import re

from app.schemas.nlp import (
    Concept,
    ConceptExtractionResponse,
    NoteSummaryResponse,
    SentimentAnalysisResponse,
)

_STOPWORDS = {
    "a", "an", "and", "are", "as", "at", "be", "been", "but", "by", "for",
    "from", "had", "has", "have", "he", "her", "his", "i", "if", "in", "is",
    "it", "its", "of", "on", "or", "our", "she", "that", "the", "their",
    "them", "there", "these", "they", "this", "to", "was", "we", "were",
    "with", "you", "your", "s", "t",
}

_POSITIVE_WORDS = {
    "improving", "improved", "better", "good", "well", "stable", "excellent",
    "positive", "satisfied", "satisfaction", "pleased", "great", "calm",
    "comfortable", "relieved", "responsive", "progress", "recovered", "thankful",
}
_NEGATIVE_WORDS = {
    "worsening", "worse", "bad", "poor", "unstable", "severe", "negative",
    "dissatisfied", "complained", "deteriorating", "decline", "declined",
    "pain", "painful", "agitated", "anxious", "distressed", "nausea",
    "feverish", "concerning", "critical", "alarming", "struggling",
}

_MEDICATION_PATTERNS = [
    r"\b(?:aspirin|rosuvastatin|atorvastatin|metformin|lisinopril|amlodipine|"
    r"metoprolol|warfarin|apixaban|clopidogrel|omeprazole|pantoprazole|"
    r"furosemide|spironolactone|levothyroxine|insulin|gliclazide|salbutamol|"
    r"paracetamol|ibuprofen|prednisolone|amoxicillin|clarithromycin|"
    r"hydrochlorothiazide|ramipril|atorvastatin)\b",
]

_DIAGNOSIS_PATTERNS = [
    r"\b(?:type 2 diabetes|type 2 diabetes mellitus|hypertension|"
    r"congestive heart failure|heart failure|chronic kidney disease|ckd|"
    r"copd|asthma|atrial fibrillation|coronary artery disease|cad|"
    r"ischemic heart disease|stroke|cerebrovascular accident|cva|"
    r"pneumonia|urinary tract infection|uti|anemia|depression|anxiety|"
    r"obesity|hyperlipidemia|hypothyroidism|osteoarthritis|gout)\b",
    r"\b(?:diaphragmatic|epigastric|inguinal)\s+hernia\b",
]


class NLPService:
    def summarize(
        self,
        text: str,
        max_sentences: int = 4,
    ) -> NoteSummaryResponse:
        sentences = self._split_sentences(text)
        if not sentences:
            return NoteSummaryResponse(summary="", original_sentence_count=0, retained_sentence_count=0)

        terms = self._term_frequencies(text)
        scored: list[tuple[float, int]] = []
        for idx, sentence in enumerate(sentences):
            s_terms = self._tokenize(sentence)
            if not s_terms:
                score = 0.0
            else:
                score = sum(terms.get(t, 0) for t in s_terms) / (len(s_terms) ** 0.5)
            # Position bonus: opening sentences are more likely to carry the gist.
            score += 1.0 if idx < 2 else 0.0
            scored.append((score, idx))

        top = sorted(scored, key=lambda pair: (-pair[0], pair[1]))[:max_sentences]
        kept_indices = sorted(idx for _, idx in top)
        summary = " ".join(sentences[i] for i in kept_indices)

        return NoteSummaryResponse(
            summary=summary,
            original_sentence_count=len(sentences),
            retained_sentence_count=len(kept_indices),
        )

    def extract_concepts(self, text: str) -> ConceptExtractionResponse:
        low = text.lower()
        concepts: list[Concept] = []
        seen: set[tuple[str, str]] = set()

        def _add(kind: str, value: str, confidence: float = 1.0) -> None:
            key = (kind, value)
            if key in seen:
                return
            seen.add(key)
            concepts.append(Concept(type=kind, value=value, confidence=confidence))

        for pattern in _MEDICATION_PATTERNS:
            for match in re.finditer(pattern, low):
                _add("medication", match.group(0).strip())

        for pattern in _DIAGNOSIS_PATTERNS:
            for match in re.finditer(pattern, low):
                value = match.group(0).strip()
                if value == "type 2 diabetes mellitus":
                    value = "type 2 diabetes"
                _add("diagnosis", value)

        return ConceptExtractionResponse(concepts=concepts)

    def analyze_sentiment(self, text: str) -> SentimentAnalysisResponse:
        low = text.lower()
        tokens = re.findall(r"[a-z']+", low)
        positive = sum(1 for t in tokens if t in _POSITIVE_WORDS)
        negative = sum(1 for t in tokens if t in _NEGATIVE_WORDS)
        total = positive + negative

        score = 0.0
        if total:
            score = (positive - negative) / total

        if positive == negative or total == 0:
            label = "neutral"
        elif positive > negative:
            label = "positive"
        else:
            label = "negative"

        return SentimentAnalysisResponse(
            label=label,
            score=round(score, 3),
            positive_hits=positive,
            negative_hits=negative,
        )

    # ------------------------------------------------------------ helpers

    @staticmethod
    def _split_sentences(text: str) -> list[str]:
        raw = re.split(r"(?<=[.!?])\s+", text.strip())
        return [s.strip() for s in raw if s.strip()]

    @staticmethod
    def _tokenize(text: str) -> list[str]:
        return [
            t for t in re.findall(r"[a-z]+", text.lower())
            if t not in _STOPWORDS
        ]

    @staticmethod
    def _term_frequencies(text: str) -> dict[str, int]:
        counts: dict[str, int] = {}
        for token in NLPService._tokenize(text):
            counts[token] = counts.get(token, 0) + 1
        return counts


def get_nlp_service() -> NLPService:
    return NLPService()

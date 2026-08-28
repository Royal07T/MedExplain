from fastapi import APIRouter, Depends

from app.core.security import require_service_key
from app.schemas.nlp import (
    ConceptExtractionRequest,
    ConceptExtractionResponse,
    NoteSummaryRequest,
    NoteSummaryResponse,
    SentimentAnalysisRequest,
    SentimentAnalysisResponse,
)
from app.services.ai.nlp_service import NLPService

router = APIRouter(dependencies=[Depends(require_service_key)])


@router.post("/nlp/summarize", response_model=NoteSummaryResponse)
def summarize(request: NoteSummaryRequest) -> NoteSummaryResponse:
    return NLPService().summarize(
        request.text,
        max_sentences=request.max_sentences,
    )


@router.post("/nlp/concepts", response_model=ConceptExtractionResponse)
def extract_concepts(request: ConceptExtractionRequest) -> ConceptExtractionResponse:
    return NLPService().extract_concepts(request.text)


@router.post("/nlp/sentiment", response_model=SentimentAnalysisResponse)
def analyze_sentiment(request: SentimentAnalysisRequest) -> SentimentAnalysisResponse:
    return NLPService().analyze_sentiment(request.text)

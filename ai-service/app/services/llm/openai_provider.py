import json

import httpx

from app.schemas.analysis import AiAnalysis
from app.schemas.extraction import DocumentType, LabTest
from app.services.llm.base import LLMProvider

_SYSTEM_PROMPT = (
    "You are MedExplain, an educational medical report assistant for patients. "
    "Explain laboratory results in plain, patient-friendly language. "
    "Rules: never diagnose; never invent or assume reference ranges not present in "
    "the input; clearly separate facts, reference comparisons, education, possible "
    "contexts, and questions the patient should ask a healthcare professional; "
    "always encourage consulting a qualified clinician. Respond with strict JSON "
    "matching this shape: {\"summary\": string, \"disclaimer\": string, "
    "\"concerns\": [string], \"items\": [{\"test_name\": string, "
    "\"explanation\": string, \"category\": \"fact\" | \"reference_comparison\" | "
    "\"education\" | \"possible_context\" | \"question_for_professional\"}]}. "
    "Each item must correspond to one input test."
)

_RAW_TEXT_LIMIT = 4000


class OpenAIProvider(LLMProvider):
    def __init__(self, api_key: str, model: str, timeout: float = 30.0):
        self._api_key = api_key
        self._model = model
        self._timeout = timeout

    async def explain(
        self,
        document_type: DocumentType,
        raw_text: str,
        lab_tests: list[LabTest],
    ) -> AiAnalysis:
        payload = {
            "model": self._model,
            "temperature": 0.2,
            "response_format": {"type": "json_object"},
            "messages": [
                {"role": "system", "content": _SYSTEM_PROMPT},
                {"role": "user", "content": self._build_prompt(document_type, raw_text, lab_tests)},
            ],
        }
        headers = {"Authorization": f"Bearer {self._api_key}"}
        async with httpx.AsyncClient(timeout=self._timeout) as client:
            response = await client.post(
                "https://api.openai.com/v1/chat/completions",
                json=payload,
                headers=headers,
            )
        response.raise_for_status()
        content = response.json()["choices"][0]["message"]["content"]
        return AiAnalysis.model_validate_json(content)

    @staticmethod
    def _build_prompt(
        document_type: DocumentType,
        raw_text: str,
        lab_tests: list[LabTest],
    ) -> str:
        tests_json = json.dumps(
            [test.model_dump(mode="json") for test in lab_tests],
            ensure_ascii=False,
        )
        excerpt = raw_text.strip()[:_RAW_TEXT_LIMIT]
        return (
            f"Document type: {document_type.value}\n\n"
            f"Raw text excerpt:\n{excerpt}\n\n"
            f"Structured tests:\n{tests_json}\n\n"
            "Produce the educational explanation as strict JSON per the schema."
        )
import json

from app.schemas.analysis import AiAnalysis
from app.schemas.extraction import DocumentType, LabTest
from app.services.llm.base import LLMProvider
from app.services.llm.gateway import (
    ChatMessage,
    ChatModel,
    GatewayConfig,
    LLMGateway,
    ProviderConfig,
    Role,
)

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
    """App-level provider that serves ``explain`` through the LLM gateway.

    Keeps the legacy :class:`LLMProvider` contract so existing callers and tests
    are untouched while every model call now flows through the gateway.
    """

    def __init__(
        self,
        api_key: str,
        model: str,
        base_url: str = "https://api.openai.com/v1",
        timeout: float = 30.0,
        *,
        provider_name: str = "openai",
        gateway: LLMGateway | None = None,
    ):
        self._gateway = gateway or self._build_gateway(
            provider_name=provider_name,
            api_key=api_key,
            model=model,
            base_url=base_url,
            timeout=timeout,
        )

    @staticmethod
    def _build_gateway(
        *,
        provider_name: str,
        api_key: str,
        model: str,
        base_url: str,
        timeout: float,
    ) -> LLMGateway:
        primary = ProviderConfig(
            name=provider_name,
            base_url=base_url,
            api_key=api_key,
            model=model,
        )
        config = GatewayConfig(
            default_provider=provider_name,
            timeout=timeout,
            fallback_order=[provider_name],
            providers={
                provider_name: primary,
                "stub": ProviderConfig(name="stub", model="stub"),
            },
            routing={
                "explain": ChatModel(
                    provider=provider_name,
                    model=model,
                    base_url=base_url,
                    api_key=api_key,
                    temperature=0.2,
                )
            },
        )
        return LLMGateway(config)

    async def explain(
        self,
        document_type: DocumentType,
        raw_text: str,
        lab_tests: list[LabTest],
        knowledge_context: str | None = None,
    ) -> AiAnalysis:
        messages = [
            ChatMessage(role=Role.SYSTEM, content=_SYSTEM_PROMPT),
            ChatMessage(
                role=Role.USER,
                content=self._build_prompt(document_type, raw_text, lab_tests, knowledge_context),
            ),
        ]
        return await self._gateway.generate_json(
            messages,
            task="explain",
            response_schema=AiAnalysis,
        )

    @staticmethod
    def _build_prompt(
        document_type: DocumentType,
        raw_text: str,
        lab_tests: list[LabTest],
        knowledge_context: str | None = None,
    ) -> str:
        tests_json = json.dumps(
            [test.model_dump(mode="json") for test in lab_tests],
            ensure_ascii=False,
        )
        excerpt = raw_text.strip()[:_RAW_TEXT_LIMIT]
        context_block = (
            f"\nSource-backed reference context:\n{knowledge_context}\n\n"
            if knowledge_context
            else ""
        )
        return (
            f"Document type: {document_type.value}\n\n"
            f"Raw text excerpt:\n{excerpt}\n\n"
            f"Structured tests:\n{tests_json}\n\n"
            f"{context_block}"
            "Produce the educational explanation as strict JSON per the schema. "
            "Use the reference ranges in the structured tests; the source-backed "
            "context is grounding only — never add ranges the tests do not carry."
        )
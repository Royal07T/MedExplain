"""Deterministic, keyless provider used for tests and local development.

Always emits schema-shaped output so the gateway's structured-output path is
exercised end to end without any network or credentials.
"""

from enum import Enum
from typing import get_origin

from pydantic import BaseModel
from pydantic.fields import FieldInfo

from app.services.llm.gateway.types import ChatMessage, ChatModel, ChatResponse
from app.services.llm.gateway.providers.base import LLMProvider


def _stub_value(annotation) -> object:
    if annotation is str:
        return ""
    if annotation is int:
        return 0
    if annotation is float:
        return 0.0
    if annotation is bool:
        return False
    origin = get_origin(annotation)
    if origin is list or annotation is list:
        return []
    if origin is dict or annotation is dict:
        return {}
    if isinstance(annotation, type) and issubclass(annotation, Enum):
        return next(iter(annotation))
    if isinstance(annotation, type) and issubclass(annotation, BaseModel):
        return _stub_instance(annotation)
    return None


def _stub_instance(schema: type[BaseModel]) -> BaseModel:
    kwargs: dict[str, object] = {}
    for name, field_info in schema.model_fields.items():
        field_info: FieldInfo
        if field_info.is_required():
            kwargs[name] = _stub_value(field_info.annotation)
        elif field_info.default_factory is not None:
            kwargs[name] = field_info.default_factory()
        elif field_info.default is not None:
            kwargs[name] = field_info.default
        else:
            kwargs[name] = None
    return schema.model_validate(kwargs)


class StubProvider(LLMProvider):
    async def chat(
        self,
        messages: list[ChatMessage],
        model: ChatModel,
    ) -> ChatResponse:
        return ChatResponse(
            content="This is a stub response for development purposes. In production, a real LLM would provide a detailed answer based on the patient's labs and medications.",
            model="stub"
        )

    async def generate_json(
        self,
        messages: list[ChatMessage],
        model: ChatModel,
        response_schema: type[BaseModel],
    ) -> BaseModel:
        return _stub_instance(response_schema)
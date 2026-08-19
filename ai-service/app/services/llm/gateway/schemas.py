"""Pydantic wire models for OpenAI-compatible chat completions responses."""

from pydantic import BaseModel, Field


class WireChoiceMessage(BaseModel):
    content: str = ""


class WireChoice(BaseModel):
    message: WireChoiceMessage


class WireUsage(BaseModel):
    prompt_tokens: int = 0
    completion_tokens: int = 0


class WireChatResponse(BaseModel):
    model: str | None = None
    choices: list[WireChoice] = Field(default_factory=list)
    usage: WireUsage | None = None
"""Typed failure modes for the LLM gateway.

Failures are split into transient (retry / fallback) and permanent (fail fast)
so that retry, backoff, and provider fallback logic can be driven from the
exception type alone. Instances never carry request bodies, API keys, or
document contents.
"""


class ProviderError(Exception):
    """Base class for all LLM gateway errors."""


class ProviderConnectionError(ProviderError):
    """Network, timeout, or connection failure. Transient; safe to retry."""


class ProviderRateLimitError(ProviderError):
    """Provider throttled the request. Transient; retry with backoff."""


class ProviderAuthError(ProviderError):
    """Invalid or missing credentials. Permanent; fail fast."""


class ProviderResponseError(ProviderError):
    """Provider returned an unusable/error response. Permanent; fail fast."""


class SchemaValidationError(ProviderError):
    """The provider's output could not be parsed or validated against the schema.

    Carries the offending raw content and a human-readable list of validation
    errors so the gateway can re-request with corrective feedback.
    """

    def __init__(self, *, raw_content: str | None = None, errors: list[str] | None = None):
        super().__init__("provider response failed schema validation")
        self.raw_content = raw_content
        self.errors = errors or []


class GatewayConfigError(ProviderError):
    """The gateway configuration references an unknown provider or malformed value."""


class GatewayFallbackError(ProviderError):
    """Every configured provider failed; the last underlying error is chained."""
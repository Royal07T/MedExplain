"""Gateway configuration: provider table, routing, and retry/fallback tuning.

Configuration is assembled from environment variables only — no secrets are
stored in code. The shape mirrors the env contract documented in
`docs/ai-architecture-blueprint.md` (Section 4.7).
"""

from collections.abc import Callable
from dataclasses import dataclass, field

from app.services.llm.gateway.types import ChatModel

PROVIDER_ENV = (
    ("openai", "openai_api_key", "openai_base_url", "openai_model"),
    ("openrouter", "openrouter_api_key", "openrouter_base_url", "openrouter_model"),
    ("agentrouter", "agentrouter_api_key", "agentrouter_base_url", "agentrouter_model"),
)


@dataclass
class ProviderConfig:
    name: str
    base_url: str = ""
    api_key: str | None = None
    model: str = ""
    supports_json: bool = True
    temperature: float = 0.2


@dataclass
class GatewayConfig:
    default_provider: str = "stub"
    timeout: float = 30.0
    max_retries: int = 2
    retry_backoff: float = 1.5
    fallback_order: list[str] = field(default_factory=lambda: ["stub"])
    routing: dict[str, ChatModel] = field(default_factory=dict)
    providers: dict[str, ProviderConfig] = field(default_factory=dict)
    observer: Callable[[object], None] | None = None

    def emit(self, event: object) -> None:
        if self.observer is not None:
            self.observer(event)


def _parse_list(raw: str) -> list[str]:
    return [item.strip() for item in raw.split(",") if item.strip()]


def _parse_routing(raw: str, providers: dict[str, ProviderConfig]) -> dict[str, ChatModel]:
    routing: dict[str, ChatModel] = {}
    for entry in _parse_list(raw):
        task, sep, spec = entry.partition(":")
        provider, _, model = spec.partition("/")
        task, provider = task.strip(), provider.strip()
        model = model.strip()
        if not sep or not task or not provider or not model:
            continue
        cfg = providers.get(provider)
        if cfg is None:
            continue
        routing[task] = ChatModel(
            provider=provider,
            model=model,
            base_url=cfg.base_url,
            api_key=cfg.api_key,
            temperature=cfg.temperature,
        )
    return routing


def build_gateway_config(settings) -> GatewayConfig:
    """Assemble a :class:`GatewayConfig` from an app Settings instance."""
    providers: dict[str, ProviderConfig] = {"stub": ProviderConfig(name="stub", model="stub")}

    for name, key_attr, url_attr, model_attr in PROVIDER_ENV:
        api_key = getattr(settings, key_attr, None) or getattr(settings, "openai_api_key", None)
        base_url = getattr(settings, url_attr, None) or getattr(settings, "openai_base_url", "")
        model = getattr(settings, model_attr, None) or getattr(settings, "openai_model", "")
        providers[name] = ProviderConfig(
            name=name,
            base_url=base_url or "",
            api_key=api_key,
            model=model or "",
        )

    default_provider = (getattr(settings, "llm_provider", "stub") or "stub").strip().lower()
    if default_provider not in providers:
        default_provider = "stub"

    return GatewayConfig(
        default_provider=default_provider,
        timeout=float(getattr(settings, "llm_timeout", 30.0)),
        max_retries=int(getattr(settings, "llm_max_retries", 2)),
        retry_backoff=float(getattr(settings, "llm_retry_backoff", 1.5)),
        fallback_order=_parse_list(getattr(settings, "llm_fallback", "stub")),
        routing=_parse_routing(getattr(settings, "llm_routing", ""), providers),
        providers=providers,
    )
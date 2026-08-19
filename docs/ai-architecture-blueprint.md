# MedExplain — AI Architecture Blueprint

**Status:** Approved blueprint (no code changes)
**Scope:** Evolve the AI service from a single-path pipeline into a multi-agent,
RAG-backed architecture behind a generic, provider-agnostic LLM gateway.
**Companion doc:** `docs/architecture.md` (as-built system architecture).

---

## 1. Executive summary

Today the AI service (`ai-service/`) is a linear pipeline:
`extract / OCR → lab parse → one LLM call → structured JSON`. Roadmap item #1
(Report Explanation) is shipped. The long-term vision is a multi-agent AI
service — Document / Lab / Medication agents routed by an orchestrator, backed
by a knowledge/RAG layer, and served by a **generic LLM gateway** that can target
OpenAI, OpenRouter, or AgentRouter from configuration alone.

This blueprint is the implementation plan for that evolution. It is
**gateway-centered**: the generic LLM gateway is the foundation everything else
stands on, and is designed first so all later layers (agents, RAG, new product
pillars) reuse one interface.

No application code changes are part of this document. It is the reference for
the phased implementation to follow.

---

## 2. Current state (as-built)

### 2.1 AI service flow

```
POST /api/v1/documents/extract        → text/OCR extraction (ExtractionResponse)
POST /api/v1/documents/parse-lab-report → regex lab parsing
POST /api/v1/analysis/explain         → LLM explanation (AiAnalysis)
```

- `app/services/extraction/text_extractor.py` — pypdf text layer, OCR fallback.
- `app/services/extraction/ocr.py` — optional pytesseract; graceful `NONE` result when unavailable.
- `app/services/extraction/lab_parser.py` — deterministic regex parser → `LabTest[]`.
- `app/services/llm/base.py` — `LLMProvider` ABC with a single `explain()` method.
- `app/services/llm/openai_provider.py` — raw `httpx` POST to
  `{OPENAI_BASE_URL}/chat/completions`, Bearer auth, `response_format: json_object`.
- `app/services/llm/factory.py` — `LLM_PROVIDER` selects provider;
  `stub | openai | openrouter | agentrouter` are accepted (OpenRouter/AgentRouter
  are aliases into the OpenAI-compatible provider).
- Pydantic v2 validates every response; malformed AI output never reaches Laravel.

### 2.2 Persistence (Laravel, Milestone 4 tables exist)

- `document_extractions` — `raw_text`, `extraction_method`.
- `lab_results` — `name`, `value`, `unit`, `reference_range`, `status`, `sort_order`.
- `ai_analyses` / `analysis_items` — analysis + categorized items.
- `medical_documents` — status/type lifecycle (`uploaded → processing → processed|failed`).

**Gaps relevant to the roadmap:** no `medications` / `prescriptions`, no
`health_events` / timeline, no normalized time-series-friendly lab schema, and no
knowledge/document store for RAG.

### 2.3 Frontend

SaaS-style dashboard (hero, stat cards, quick actions) and an expanded Settings
page are shipped. Roadmap item #1 (Report Explanation) is complete.

---

## 3. Target AI-service architecture

```
                       FastAPI (ai-service)
                                  │
                      ┌───────────┴───────────┐
                      │     AI Orchestrator    │   request lifecycle, routing,
                      └───────────┬───────────┘   sequencing, safety gates
                                  │
          ┌───────────────────────┼────────────────────────┐
          ▼                       ▼                        ▼
  Document Agent            Lab Agent              Medication Agent
          │                       │                        │
          ▼                       ▼                        ▼
   OCR / text parser        Lab parser            Rx parser
          │                       │                        │
          └───────────────────────┼────────────────────────┘
                                  ▼
                        Knowledge / RAG layer
                        (source-backed, safety-bounded)
                                  ▼
                        Generic LLM Gateway
                        OpenAI · OpenRouter · AgentRouter
```

### 3.1 Component responsibilities

| Component | Responsibility |
|---|---|
| `AI Orchestrator` | Owns the request lifecycle: classify document, dispatch agents, merge structured outputs, enforce safety gates, map to response schema. |
| `Document Agent` | Text/OCR extraction, document-type detection, metadata normalization. Wraps current `text_extractor` / `ocr`. |
| `Lab Agent` | Lab-test extraction and normalization (names, units, reference ranges, status). Wraps current `lab_parser`; gains LLM-assisted fallback. |
| `Medication Agent` | Medication / Rx extraction (drug, dose, frequency, route, prescriber). New capability for roadmap item #6. |
| `Knowledge / RAG` | Retrieval over curated, source-backed medical-education content and reference ranges. Read-only boundary: never a diagnostic source. |
| `Generic LLM Gateway` | Provider-agnostic chat/completions + structured output. The centerpiece (Section 4). |

### 3.2 Principles

- **Stateless FastAPI service** — no DB, no user model (unchanged).
- **Pydantic at every boundary** — internal agent outputs and gateway outputs are
  validated models; malformed data fails at the boundary, never downstream.
- **Safety-first** — agents produce *educational* content only; the gateway and
  RAG layer never invent reference ranges, never diagnose, and always include the
  "consult a professional" guardrail.
- **Backward compatible** — existing `/documents/*` and `/analysis/explain`
  endpoints keep their contracts; the refactor is behavior-preserving.

---

## 4. LLM Gateway design (centerpiece)

### 4.1 Goals

- One interface for chat completions + structured JSON across providers.
- Providers (OpenAI, OpenRouter, AgentRouter, stub, and future local models)
  selected by configuration, not code.
- Uniform routing, timeout, retry, fallback, and streaming semantics.
- Strict, provider-agnostic structured output (Pydantic-validated).
- No secrets in code or config files; everything via environment.

### 4.2 Module layout

```
app/services/llm/gateway/
├── __init__.py            # public exports
├── types.py               # ChatMessage, ChatModel, GatewayConfig, Usage
├── client.py              # LLMGateway: routing + fallback + retry logic
├── providers/
│   ├── base.py            # LLMProvider protocol (chat, generate_json)
│   ├── stub.py            # deterministic, keyless
│   └── openai_compatible.py  # OpenAI / OpenRouter / AgentRouter
└── schemas.py             # request/response models (Pydantic)
```

### 4.3 Core types (`types.py`)

```python
class Role(str, Enum):
    SYSTEM = "system"
    USER = "user"
    ASSISTANT = "assistant"

@dataclass
class ChatMessage:
    role: Role
    content: str

@dataclass
class ChatModel:
    provider: str          # "openai" | "openrouter" | "agentrouter" | "stub" | ...
    model: str             # model id as the provider expects it
    base_url: str          # OpenAI-compatible endpoint base URL
    api_key: str | None    # never logged, never persisted
    temperature: float = 0.2
    max_tokens: int | None = None

@dataclass
class Usage:
    prompt_tokens: int
    completion_tokens: int
```

### 4.4 Provider protocol (`providers/base.py`)

```python
class LLMProvider(ABC):
    @abstractmethod
    async def chat(self, messages: list[ChatMessage], model: ChatModel) -> ChatResponse: ...

    @abstractmethod
    async def generate_json(
        self, messages: list[ChatMessage], model: ChatModel,
        response_schema: type[BaseModel],
    ) -> BaseModel: ...
```

- `chat` — plain text completions (used by RAG-assisted agents).
- `generate_json` — structured output, validated against `response_schema`
  (the gateway enforces the same guarantee Pydantic already gives today).

### 4.5 OpenAI-compatible provider (`providers/openai_compatible.py`)

- POSTs to `{model.base_url}/chat/completions`.
- Bearer auth from `model.api_key`.
- Structured JSON via `response_format: {"type": "json_object"}` when the
  provider advertises support (capability flag per provider entry).
- Maps errors to typed exceptions:
  - `ProviderConnectionError` (transient → retry/fallback)
  - `ProviderRateLimitError` (retry with backoff)
  - `ProviderResponseError` (permanent → fail fast)
- Never logs request bodies, keys, or document contents.

### 4.6 Gateway client (`client.py`)

`LLMGateway` is the single entry point used by the orchestrator and agents:

```python
class LLMGateway:
    def __init__(self, config: GatewayConfig): ...

    async def chat(self, messages, *, task: str) -> ChatResponse
    async def generate_json(self, messages, *, task: str, response_schema) -> BaseModel
```

Behavior:

- **Model routing** — a routing table maps a semantic `task` (e.g. `explain`,
  `extract_labs`, `extract_medications`, `retrieve_context`) to a `ChatModel`.
  Unknown tasks fall back to the default model.
- **Retry** — transient failures retried with exponential backoff (env-tuned).
- **Fallback** — an ordered provider list per task: if the primary fails
  permanently/after retries, try the next provider before surfacing an error.
- **Structured output** — `generate_json` re-requests on schema-validation
  failure (bounded), then fails fast with a typed error.
- **Observability** — emits provider, model, latency, usage, and error event
  metadata (no content) for audit/logging.

### 4.7 Provider registry & config

Built from environment variables (not code edits). Example shape (values are
illustrative; the implementation reads a provider table):

```
LLM_PROVIDER=agentrouter
LLM_ROUTING=explain:agentrouter/gpt-5.6-sol, extract:openai/gpt-4o-mini

OPENAI_API_KEY=…
OPENAI_BASE_URL=https://api.openai.com/v1
OPENROUTER_API_KEY=…
OPENROUTER_BASE_URL=https://openrouter.ai/api/v1
AGENTROUTER_API_KEY=…
AGENTROUTER_BASE_URL=https://agentrouter.org/v1
LLM_TIMEOUT=30
LLM_MAX_RETRIES=2
LLM_RETRY_BACKOFF=1.5
LLM_FALLBACK=agentrouter,openai,stub
```

Rules:

- **No secrets in the repository.** Only `.env` / environment supplies keys.
- `stub` is always available as the final fallback for keyless dev/test.
- The gateway never writes secrets to logs, traces, or error messages.

### 4.8 Structured JSON contract

The existing `AiAnalysis` schema is preserved. Gateway `generate_json` is the
single code path that produces it (and later: extraction, medication, trend
schemas). Schema changes for new pillars (Section 6) must be additive and
backward compatible.

---

## 5. Orchestrator & agents

### 5.1 Orchestrator (`app/services/ai/orchestrator.py`)

- Classifies incoming text/document type (existing `detect_document_type`).
- Selects and sequences agents; merge their outputs into the response schema.
- Runs safety gates:
  - Never fabricate reference ranges → RAG context is the only range source.
  - Distinguish `fact | reference_comparison | education | possible_context |
    question_for_professional` (existing `AnalysisCategory`).
  - Ensure the "consult a professional" disclaimer is present.
- Uses the `LLMGateway` for every model call; uses parsers for deterministic
  work first, LLM only as fallback or for content generation.

### 5.2 Document Agent

- Reuses `text_extractor` / `ocr` verbatim; exposes `extract()` → `ExtractionResult`.
- No LLM required in the happy path.

### 5.3 Lab Agent

- Deterministic: existing `parse_lab_report`.
- Fallback: `LLMGateway.generate_json` with the `LabTest[]` schema when the
  deterministic parser yields low confidence or an empty result.
- Output validated against the existing `LabTest` model.

### 5.4 Medication Agent (new, roadmap #6)

- `RxParser`: deterministic patterns for dose/frequency/route where possible.
- `LLMGateway.generate_json` with a `Medication` schema (`name`, `strength`,
  `dosage_form`, `dose`, `frequency`, `route`, `prescriber`, `indications`,
  `start_date`, `end_date`).
- Safety: educational only — interactions/side-effects content must come from
  the RAG layer or be marked as "ask your clinician".

### 5.5 Knowledge / RAG layer (`app/services/ai/knowledge/`)

- Source-backed content: curated educational material and reference ranges with
  provenance (source, version, date). Never model-derived.
- Retrieval: embeddings + similarity search (or a deterministic keyword index in
  the first phase) over `knowledge_documents`.
- Boundary: retrieved context is *grounding* for explanations, never an absolute
  diagnostic; unknown ranges → "not available in this report" rather than invention.
- The RAG layer calls the gateway for generation; retrieval itself is local.

---

## 6. API & data evolution

### 6.1 Schema growth (backend migrations — future phases)

| Table | Purpose | Needed by |
|---|---|---|
| `medications` / `prescriptions` | Medication extraction & history | #6 Medication Intelligence |
| `health_events` | Timeline events (tests, visits, vaccinations) | #4 Health Timeline, #5 PHR |
| `knowledge_documents` | Curated, source-backed RAG content (provenance fields) | #2 Document Intelligence, RAG |
| `lab_results` indexes | `(user_id, name, collected_at)` for time-series | #3 Lab Trends |
| `lab_results.loinc` / normalized name | Cross-report trend joins | #3, #4, #5 |

### 6.2 Endpoint evolution

- **Phase 0:** no new endpoints; existing contracts unchanged.
- **Phase 1:** `/analysis/explain` behavior preserved, now served through gateway + orchestrator.
- **Later phases:** `/analysis/trends`, `/medications`, `/health/timeline`,
  `/health/record`, `/assistant` (chat), `/clinician/*` (portal), `/api/*` (partner platform).

### 6.3 Frontend

Reuse the shipped SaaS shell (layout, dashboard cards, settings). New pillars add
routes/views under the existing guarded layout; colors and design language stay
the same.

---

## 7. Roadmap mapping

| # | Pillar | Core components required | Depends on |
|---|---|---|---|
| 1 | Report Explanation | Gateway + Lab Agent (shipped) | ✅ done |
| 2 | Medical Document Intelligence | Orchestrator, Document Agent, RAG | Gateway (P0) |
| 3 | Lab Result Trends | Normalized lab_results + time-series query | #2 data model |
| 4 | Personal Health Timeline | `health_events`, timeline endpoint | #3 |
| 5 | Personal Health Record | Aggregated view of labs/meds/events | #4 |
| 6 | Medication Intelligence | Medication Agent, Rx parser, RAG (interactions edu) | Gateway + RAG |
| 7 | AI Health Assistant | Gateway chat, orchestrated multi-agent RAG, guardrails | #6 |
| 8 | Clinician Portal | RBAC/roles in Laravel, clinician-scoped APIs | #7 |
| 9 | Healthcare Provider Integration | Partner auth (OAuth), scoped consent, audit | #8 |
| 10 | HealthTech API Platform | Public API productization, keys, quotas, docs | #9 |

---

## 8. Phased implementation plan

Each phase is implemented, tested, and committed before the next. Every phase
preserves the security and safety constraints in Section 9.

### Phase 0 — Generic LLM Gateway (behavior-preserving)

- **Files:** `ai-service/app/services/llm/gateway/` (types, providers, client),
  `ai-service/app/core/config.py` (gateway settings), tests.
- **Work:** build gateway; reimplement `OpenAIProvider` on top of
  `OpenAICompatibleProvider`; keep `LLM_PROVIDER` aliases working; `stub` final fallback.
- **Risk:** provider response-shape drift → typed exceptions + fixture-based tests per provider.
- **Verify:** existing `pytest` suite green; live smoke against OpenAI / OpenRouter / AgentRouter base URLs.

### Phase 1 — Orchestrator + agents refactor

- **Files:** `app/services/ai/orchestrator.py`, `app/services/ai/agents/…`.
- **Work:** wrap existing parsers as Document/Lab agents; introduce orchestrator;
  `/analysis/explain` served through gateway + orchestrator; add Medication Agent skeleton.
- **Risk:** regression in explanation quality → golden fixtures + category assertions.
- **Verify:** endpoint contract unchanged; quality tests; new agent unit tests.

### Phase 2 — Knowledge / RAG layer

- **Files:** `app/services/ai/knowledge/`, `knowledge_documents` migration (later),
  embed/retrieve services.
- **Work:** curated content store with provenance; retrieval; gate ranges through RAG.
- **Risk:** hallucinated ranges → hard gate: no range without source in store.
- **Verify:** retrieval precision tests; "unknown range" behavior test.

### Phase 3 — Lab Trends & Timeline (#3, #4)

- **Files:** backend migrations + Laravel models/services; new endpoints;
  frontend trends/timeline views.
- **Work:** normalized lab names (LOINC), `collected_at`, time-series queries,
  trend explanations via gateway.
- **Verify:** cross-report trend correctness tests; frontend chart rendering.

### Phase 4 — Medication Intelligence & Assistant (#6, #7)

- **Files:** Medication Agent, Rx parser, `medications`/`prescriptions` tables,
  chat endpoint, assistant frontend.
- **Work:** medication extraction + educational explanations; guarded assistant chat.
- **Verify:** medication extraction fixtures; assistant safety/guardrail tests.

### Phase 5+ — PHR, Clinician Portal, Provider Integration, API Platform (#5, #8–10)

- **Files:** Laravel roles/permissions, OAuth, scoped APIs, frontend portals.
- **Verify:** RBAC/audit tests; consent-scoped data access tests; rate limits.

---

## 9. Security & safety constraints (non-negotiable)

- **No secrets in code/config.** Keys only via environment; never logged.
- **No document/test contents in logs, traces, or audit entries.**
- **Service-to-service auth:** `X-Service-Key`, constant-time compare (unchanged).
- **Ownership enforcement in Laravel** (policies) for every user-scoped endpoint.
- **Medical safety:** never diagnose; never invent reference ranges (RAG is the
  only range source); always include the professional-consult disclaimer.
- **Typed failures:** transient vs permanent separation drives retry/fallback;
  stack traces never reach the patient.
- **Rate limiting** on auth and high-volume endpoints; OAuth + consent before any
  partner access (pillars #9–10).
- **Audit logging** for security-sensitive actions (login, document deletion,
  data export, partner consent).

## 10. Non-goals (this blueprint)

- No production code changes yet.
- No choice of embedding provider / vector store is locked in (Phase 2 decides).
- No UI redesign — existing design language is preserved.
- No data-migration of existing reports is in scope for Phase 0–1.
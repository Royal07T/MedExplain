# MedExplain — Architecture

## Overview

MedExplain is an AI-powered, educational platform that helps patients understand medical
reports and laboratory results. It extracts structured test information from uploaded
documents and produces patient-friendly explanations with an emphasis on medical safety:
it never diagnoses, never invents reference ranges, and always directs users to a
qualified healthcare professional.

## High-level flow

```
Vue 3 SPA ──HTTPS/JSON──► Laravel 13 REST API ──► Database (MySQL/PostgreSQL)
 (frontend)               (backend; owns auth, users,      ▲
    ▲                         documents, business logic)   │ private document storage
    └──── poll processing status / fetch results ──────────┘
                                │
                         FastApiClient (Laravel service)
                                │ service-to-service (shared key, HTTPS)
                                ▼
                       FastAPI (Python) ─► text extraction / OCR / lab parsing / LLM explanation
```

## Components

| Component   | Responsibility |
|-------------|----------------|
| Vue 3 SPA   | Login/register, dashboard, upload, report results. Talks only to Laravel. |
| Laravel API | Users, authentication (Sanctum tokens), documents, persistence, ownership (policies), queue jobs, audit logging. Single source of truth. |
| FastAPI     | Document parsing, OCR, lab-result extraction, LLM explanations. Stateless, no DB, no user model. |
| Database    | Relational storage (MySQL via phpMyAdmin locally; PostgreSQL-compatible migrations). |
| Storage     | Private local disk via Laravel `local` disk; swappable to S3-compatible storage via config. |

## Repository layout

```
backend/     Laravel 13 REST API
frontend/    Vue 3 + TypeScript SPA
ai-service/  FastAPI service
docker/      Docker dev infrastructure
docs/        This documentation
```

## Database schema

Tables: `users`, `profiles`, `medical_documents`, `document_extractions`, `lab_results`,
`ai_analyses`, `analysis_items`, `medications`, `audit_logs`, `clinician_patient_access`,
`api_partners`, `patient_consents`, `notifications`, plus Laravel framework tables
(`personal_access_tokens`, `jobs`, `failed_jobs`, `cache`, `sessions`, `password_reset_tokens`).

Key columns:
- `users.role`: `patient | clinician` (default `patient`)
- `users.plan`: `free | pro` (default `free`)
- `clinician_patient_access`: many-to-many `clinician_user_id` ↔ `patient_user_id` — the
  single source of truth for clinician authorization.
- `api_partners`: `client_id`, `client_secret` (hashed), `scopes` (json), `quota_per_minute`,
  `is_active`.
- `patient_consents`: `partner_id` ↔ `patient_user_id`, `scopes` (json), `granted_at`,
  `revoked_at`.
- `notifications`: `user_id`, `title`, `body`, `type`, `data` (json), `read_at`.

Statuses are stored as string enums:
- `medical_documents.status`: `uploaded | processing | processed | failed`
- `medical_documents.document_type`: `lab_report | doctor_report | radiology_report | unknown`
- `lab_results.status`: `within_range | above_range | below_range | positive | negative | unknown`
- `ai_analyses.status`: `pending | completed | failed`

## Laravel ↔ FastAPI contract (Milestone 3)

- Auth: `X-Service-Key` header, compared with constant-time compare using `FASTAPI_API_KEY`.
- Config via env: `FASTAPI_BASE_URL`, `FASTAPI_API_KEY`, `FASTAPI_TIMEOUT`.
- Endpoints: `GET /api/v1/health`, `POST /api/v1/documents/extract`,
  `POST /api/v1/documents/parse-lab-report`, `POST /api/v1/analysis/explain`,
  `POST /api/v1/health/query`.
- FastAPI returns predictable structured JSON validated with Pydantic.

## Laravel API (v1)

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| POST | `/api/v1/auth/register` | – | Register (throttled) |
| POST | `/api/v1/auth/login` | – | Login (throttled) |
| POST | `/api/v1/auth/logout` | token | Revoke current token |
| POST | `/api/v1/auth/forgot-password` | – | Request reset link |
| POST | `/api/v1/auth/reset-password` | – | Reset password |
| POST | `/api/v1/auth/email/verification-notification` | token | Resend verification |
| GET  | `/api/v1/email/verify/{id}/{hash}` | signed | Verify email |
| GET  | `/api/v1/user` | token | Current user + profile |
| PUT  | `/api/v1/user` | token | Update name + profile details |
| POST | `/api/v1/user/avatar` | token | Upload avatar image |
| GET/POST | `/api/v1/documents` | token | List / upload documents |
| GET/DELETE | `/api/v1/documents/{document}` | token | View / delete |
| GET  | `/api/v1/documents/{document}/analysis` | token | AI analysis |
| GET  | `/api/v1/labs/names` | token | Known lab test names |
| GET  | `/api/v1/labs/trends` | token | Lab value trends |
| GET  | `/api/v1/health/timeline` | token | Recent health events |
| GET  | `/api/v1/health/record` | token | Aggregated personal health record |
| GET  | `/api/v1/medications` | token | User's medications |
| POST | `/api/v1/health/query` | token | AI assistant — structured health-intelligence answer (throttled) |
| GET  | `/api/v1/plan` | token | Current plan summary |
| POST | `/api/v1/plan/upgrade` | token | Upgrade to Pro (audited) |
| POST | `/api/v1/plan/cancel` | token | Cancel subscription (audited) |
| GET  | `/api/v1/notifications` | token | List notifications + unread count |
| GET  | `/api/v1/notifications/unread-count` | token | Unread badge count |
| POST | `/api/v1/notifications/read-all` | token | Mark all read |
| POST | `/api/v1/notifications/{id}/read` | token | Mark one read (user-scoped) |
| GET/POST | `/api/v1/clinician/patients` | clinician | List / grant patient access |
| GET  | `/api/v1/clinician/patients/{patient}/record` | clinician | View granted patient's record |
| GET  | `/api/v1/partner/consents` | token | List the patient's consents |
| POST/DELETE | `/api/v1/partner/consents/{partner}` | token | Grant / revoke consent |
| POST | `/api/v1/partner/oauth/token` | – | Partner client-credentials token |
| GET  | `/api/v1/partner/patients` | partner | Patients with active consent |
| GET  | `/api/v1/partner/patients/{patient}/record` | partner | Consent-scoped health record |
| GET  | `/api/v1/api-docs` | – | OpenAPI 3.0 spec |

All user-facing document routes enforce ownership via Laravel Policies — never
frontend-only checks. Partner routes are additionally consent-scoped, clinician routes
require an explicit grant, and notifications are user-scoped.

## Post-MVP feature areas

These areas were added after the original milestones and are fully implemented,
tested, and verified.

### Personal health record

`HealthService::record()` aggregates, per patient: the latest result for each lab test,
all medications, a recent-events timeline, and profile summary. Exposed as
`GET /health/record` for the patient's own app, and — with an active consent — to
partners and (on explicit grant) clinicians via the same service.

### Clinician portal (role-based access)

- `UserRole` enum (`patient` / `clinician`) on `users.role`, default `patient`.
- `role` middleware alias → `EnsureUserRole` (fails closed, `403`).
- `clinician_patient_access` pivot is the **single source of truth** for authorization:
  a clinician only ever views patients explicitly granted by email. Grants and every
  record view are audited (`clinician_access_granted`, `clinician_record_viewed`).
- The granted patient receives an in-app notification when a clinician is granted access.

### Provider (partner) integration

- `api_partners` hold client credentials (hashed), scopes, a per-minute quota, and an
  active flag. `PatientConsent` is patient-managed and scope-specific.
- OAuth 2.0 client-credentials token endpoint (`POST /partner/oauth/token`) issues a
  Sanctum token bound to the partner model.
- Partner middleware aliases: `partner` (`EnsureActivePartner`) and
  `partner-scope:health_record:read` (`EnsurePartnerScope`); rate limiter `partner` is
  keyed by partner id using `quota_per_minute` (excess → `429`).
- Every record access requires an active consent and is audited
  (`partner_token_issued`, `partner_record_accessed`). Consent grant/revoke by the
  patient is audited and produces an in-app notification.

### Plans & subscriptions

- `Plan` enum (`free` / `pro`), default `free`, exposed via `UserResource.plan`.
- Idempotent `PlanService::upgrade()` / `cancel()` backed by `POST /plan/upgrade` and
  `POST /plan/cancel` (audited, rate-limited). No billing provider is integrated yet —
  these simulate the subscription change. Upgrade/cancel produces an in-app
  notification.

### In-app notifications

- `notifications` table + `NotificationService`; `NotificationController` endpoints are
  strictly user-scoped (reading or mutating another user's notification → `403`).
- Real events create notifications: document uploaded, analysis ready, processing
  failed, plan upgraded/cancelled, partner connected/disconnected, and clinician access
  granted. The SPA bell polls the unread count and renders a dropdown with
  mark-single / mark-all actions.

### OpenAPI documentation

`GET /api/v1/api-docs` serves `backend/resources/api/openapi.json` (OpenAPI 3.0),
documenting the partner-facing endpoints plus account, plan, and notification routes.

## Health Intelligence Layer (AI assistant)

`POST /api/v1/health/query` answers a natural-language question about the user's
own health data. It is a split-brain orchestrator: deterministic computation and
ownership live in Laravel; explanation lives in FastAPI.

### Laravel side (deterministic brain)

- `HealthQueryService` — orchestrator with a handler map keyed by intent.
- `IntentRegistry` / `IntentDefinition` — deterministic regex intent detection
  (no LLM routing) and per-intent `requires_rag` flags.
- Deterministic services: `ReportComparisonService`, `LabTrendEngine`,
  `MedicationAtDateResolver`, `RecentHealthChangesService`.
- `HealthContextService` — lazy, user-scoped context retrieval. Only the sections
  the detected intent needs are computed and sent (never the full history).
- `FastApiClient::healthQuery()` sends the structured context to FastAPI.
- Route is authenticated (Sanctum), throttled (`health-query`, 10/min), and the
  returned answer is wrapped as `{ query_id, intent, answer }`.

### FastAPI side (explanation layer)

- `POST /api/v1/health/query` (service-key auth) → `HealthIntelligenceService`.
- Adds trusted knowledge only when needed: `GENERAL_HEALTH_QUESTION` always, plus
  any test names found in the structured sections (RAG grounding, never invented).
- Calls the LLM gateway with `task="health_query"` and `HealthQueryResponse` as
  the strict Pydantic schema (malformed output is rejected at the boundary).
- Safety gates: urgent-hint questions are deferred without the LLM; missing
  required data returns a deterministic "unavailable" answer without the LLM;
  `sources` come only from the curated store and `data_used` is echoed from the
  request; facts/changes referencing unknown tests or invented numbers are
  dropped; empty output falls back to a deterministic summary.

### Frontend

`/assistant` view under the AI Assistant nav group: a chat where each question
gets a sectioned answer (summary, what the data shows, what changed, in-context
notes, learn more, questions for your clinician, data used, sources,
disclaimer), plus suggested questions and a loading state.

## Document processing pipeline (Milestone 2–4)

Upload → validate (MIME + size) → store privately → create `medical_documents`
(`status=uploaded`) → dispatch `ProcessMedicalDocumentJob` (`status=processing`) →
FastAPI extract/OCR → parse lab tests → store `document_extractions` + `lab_results` →
FastAPI explain → store `ai_analyses` + `analysis_items` → `status=processed`.
Failures mark the document `failed` with a safe technical message; stack traces never
reach the patient.

## Milestones & roadmap

Build order for the MVP. Each milestone is implemented, verified (tests + live smoke
test), and committed before the next begins.

| # | Milestone | Delivers | Status |
|---|-----------|----------|--------|
| 1 | Foundation + Laravel authentication | Monorepo, Laravel scaffold, envs, `users`/`profiles`/`audit_logs` schema, Sanctum auth API (register/login/logout/verification/password reset), rate limiting, tests | ✅ Done |
| 2 | Medical document upload & storage | `medical_documents` schema, model, policy, controller, private `documents` disk, upload validation (MIME/size), list/view/delete, audit events, tests | ✅ Done |
| 3 | FastAPI service | FastAPI scaffold, `GET /health`, `POST /documents/extract`, `POST /documents/parse-lab-report`, `POST /analysis/explain`, Pydantic schemas, text extraction / OCR, lab parsing, LLM provider abstraction, tests | ✅ Done |
| 4 | Processing pipeline | `ProcessMedicalDocumentJob`, `FastApiClient`, persistence of `document_extractions`/`lab_results`/`ai_analyses`/`analysis_items`, status transitions, retries, safe failure handling | ✅ Done |
| 5 | Vue 3 frontend | SPA foundation (router guards, Pinia, axios, layout), auth pages, dashboard, upload flow, report/results UI, status badges, loading/error/empty states, disclaimer | ✅ Done |
| 6 | Hardening, docs & Docker | Audit wiring polish, rate-limit review, full README, `docker-compose.yml` (frontend, Laravel, FastAPI, PostgreSQL, Redis), full-stack tests | ✅ Done |
| 7 | Medication intelligence + AI assistant | Medications management + patient-grounded assistant UI (backend + ai-service + frontend) | ✅ Done |
| 8 | Personal health record | `GET /health/record` aggregation + frontend health-record view | ✅ Done |
| 9 | Clinician portal | Roles, explicit grants, clinician record access, audit events | ✅ Done |
| 10 | Provider integration + API platform | OAuth client credentials, patient consents, consent-scoped partner access, OpenAPI docs | ✅ Done |
| 11 | Plans, notifications & responsive UI | Free/Pro plans, in-app notifications, SaaS-style responsive shell | ✅ Done |
| 12 | Health Intelligence Layer | AI assistant: intent registry + deterministic services + FastAPI health-query layer + `/assistant` chat frontend | ✅ Done |

Suggested commit sequence (used so far, to be continued):

```
feat: initialize Laravel backend              ✅ 4d39d67
feat: add authentication                      ✅ 56be490
feat: add medical document uploads            ✅ 6c299f0
feat: initialize FastAPI service              ✅
feat: add laboratory report extraction        ✅
feat: add AI analysis                         ✅
feat: add Vue dashboard                       ✅
feat: add report analysis UI                  ✅
feat: add medications & AI assistant          ✅
feat: add personal health record              ✅
feat: add clinician portal                    ✅
feat: add provider integration & API docs     ✅
feat: add plans, notifications, responsive UI  ✅
feat: add health intelligence layer           ✅
feat: add plans, notifications & responsive UI ✅
test: add document processing tests           ✅
docs: add project documentation               ✅
```

### Milestone 3 — FastAPI service (`ai-service/`)

**Scope**: Standalone FastAPI service: service-to-service auth, document text extraction,
OCR fallback, laboratory-report parsing, LLM provider abstraction, Pydantic v2 schemas,
tests. Stateless — no DB, no user model.

**Files**

```
ai-service/
├── pyproject.toml            # fastapi, uvicorn, pydantic-settings, python-multipart,
│                             # pypdf, Pillow, pytesseract (optional)
├── .env.example              # FASTAPI_API_KEY, LLM_PROVIDER, OPENAI_API_KEY, ...
├── app/
│   ├── main.py               # app factory + lifespan
│   ├── core/
│   │   ├── config.py         # pydantic-settings Settings (env-driven)
│   │   └── security.py       # X-Service-Key dependency
│   ├── api/v1/
│   │   ├── router.py         # versioned router
│   │   ├── health.py         # GET /api/v1/health
│   │   ├── documents.py      # POST /api/v1/documents/extract,
│   │   │                     #     POST /api/v1/documents/parse-lab-report
│   │   └── analysis.py       # POST /api/v1/analysis/explain
│   ├── schemas/
│   │   ├── extraction.py     # LabTest, ExtractionResponse, DocumentType
│   │   └── analysis.py       # AiAnalysis, AnalysisItem
│   └── services/
│       ├── extraction/
│       │   ├── text_extractor.py   # pypdf for PDFs with a text layer
│       │   ├── ocr.py              # pytesseract for images / text-less PDFs
│       │   └── lab_parser.py       # regex + LLM-assisted parsing into LabTest[]
│       └── llm/
│           ├── base.py             # LLMProvider protocol
│           ├── factory.py          # provider selection via LLM_PROVIDER env
│           └── openai_provider.py  # structured JSON output
└── tests/
    ├── test_health.py
    ├── test_schemas.py
    ├── test_extraction.py
    └── test_analysis.py
```

**Key decisions**

- Auth via `X-Service-Key` header, constant-time compare (`secrets.compare_digest`).
- OCR path only when a PDF has no extractable text layer; Tesseract is an optional
  system dependency — when unavailable, report `extraction_method=pdf_text` gaps
  gracefully instead of failing.
- LLM access through an `LLMProvider` protocol + factory with a built-in `stub`
  provider, so tests and keyless dev runs work without an API key.
- Every response validated by Pydantic v2 models; LLM output is coerced to the same
  schemas so malformed AI output never reaches Laravel.
- Health endpoint returns version + dependency status for observability.

**Verify**: `uvicorn app.main:app --port 8001`; `pytest`; curl health + extract with a
sample PDF and a sample lab-report image.

**Commits**: `feat: initialize FastAPI service` → `feat: add laboratory report extraction`
→ `feat: add AI analysis`.

### Milestone 4 — Processing pipeline

**Scope**: Wire uploads to FastAPI asynchronously: `ProcessMedicalDocumentJob`,
`FastApiClient`, persistence of `document_extractions` / `lab_results` / `ai_analyses` /
`analysis_items`, status transitions, retries, safe failure handling, and the analysis
read endpoint.

**Files**

```
backend/app/
├── Jobs/ProcessMedicalDocumentJob.php       # uploaded → processing → processed|failed
├── Services/FastApiClient.php               # Http client, key header, timeout, typed errors
├── Services/DocumentProcessor.php           # orchestrates job steps
├── DTOs/ExtractionDto.php, LabResultDto.php, AiAnalysisDto.php
├── Models/DocumentExtraction.php, LabResult.php, AiAnalysis.php, AnalysisItem.php
└── Http/Resources/AnalysisResource.php      # analysis + items + linked lab results
backend/database/migrations/                 # document_extractions, lab_results,
                                             # ai_analyses, analysis_items
backend/config/fastapi.php                   # base_url, api_key, timeout (env-driven)
backend/routes/api.php                       # GET /api/v1/documents/{document}/analysis
backend/tests/Feature/Documents/             # processing + analysis tests
```

**Key decisions**

- Upload dispatches the job; status flow `uploaded → processing → processed`, failures
  mark the document `failed` with a safe technical message (stack traces stay in logs).
- `FastApiClient` uses Laravel's `Http` client with env-based timeout; typed exceptions
  (`FastApiConnectionException`, `FastApiResponseException`) separate transient vs
  permanent failures; never logs document contents.
- Retries with backoff only for transient (connection/timeout/5xx) failures; permanent
  parse failures fail fast.
- `document_extractions.extraction_method`: `pdf_text | ocr | none`.
- Analysis endpoint enforces the `MedicalDocumentPolicy` (ownership) before returning
  `AiAnalysis` + `analysis_items` + related `lab_results`.
- Extractions stored per-document in a transaction; partial failure rolls back cleanly.

**Verify**: `php artisan test` with `Http::fake()` covering status transitions, retries,
and failures; full-stack smoke against a running FastAPI.

**Commits**: `feat: add document processing jobs` → `test: add document processing tests`.

### Milestone 5 — Vue 3 frontend (`frontend/`)

**Scope**: Complete SPA: foundation (routing, state, HTTP client, layouts) plus every
page and component from the spec.

**Files**

```
frontend/
├── package.json, vite.config.ts, tsconfig.json, index.html
├── .env.example                              # VITE_API_URL
└── src/
    ├── main.ts, App.vue
    ├── router/index.ts                       # auth-guarded routes
    ├── stores/auth.ts, reports.ts            # Pinia
    ├── api/client.ts                         # axios + Bearer token interceptor
    ├── api/auth.ts, api/documents.ts
    ├── types/                                # User, Document, Analysis DTOs
    ├── layouts/AppLayout.vue, Nav.vue
    ├── components/ StatusBadge.vue, UploadDropzone.vue, ReportCard.vue,
    │               ResultCard.vue, Disclaimer.vue, LoadingState.vue,
    │               ErrorState.vue, EmptyState.vue
    ├── composables/ useAuth.ts, usePolling.ts
    └── views/ Login.vue, Register.vue, Dashboard.vue, Reports.vue,
               Upload.vue, ReportDetail.vue, Profile.vue, Settings.vue
```

**Key decisions**

- Vite + Vue 3 + TypeScript; Tailwind for a modern responsive UI.
- Auth token kept in Pinia and `localStorage`; axios interceptor attaches the Bearer
  header and redirects on 401.
- Route guards redirect unauthenticated users to `/login`.
- `ReportDetail` polls document status until `processed`, then renders results with
  loading / error / empty states.
- Medical disclaimer shown wherever results or AI content is displayed.

**Verify**: `npm run dev` / `npm run build`; vitest unit tests for auth, upload, and
report rendering flows.

**Commits**: `feat: add Vue dashboard` → `feat: add report analysis UI`.

### Milestone 6 — Hardening, docs & Docker

**Scope**: Production-readiness polish: full `docker-compose` stack, container
Dockerfiles, audit/rate-limit review, and complete README.

**Files**

```
docker-compose.yml               # nginx, frontend, backend, queue worker, ai-service,
                                 # postgres, redis
docker/laravel/Dockerfile
docker/fastapi/Dockerfile
docker/nginx/default.conf        # reverse proxy + static frontend
README.md                        # full setup, dev + deploy instructions
```

**Key decisions**

- Secrets injected via env; `.env.example` at repo root.
- Named volumes for the database and private document storage.
- Dedicated Laravel queue-worker service alongside the API.
- Healthchecks on every service; FastAPI `GET /health` wired into compose.
- Multi-stage builds to keep images lean.

**Verify**: `docker compose up` brings the full stack healthy; end-to-end smoke
(register → upload → processed → results).

**Commit**: `docs: add project documentation`.

## Security & safety principles

- Sanctum bearer tokens; passwords always hashed (`bcrypt`).
- Private storage; no public medical document URLs.
- Ownership via policies; server-side authorization.
- Role-based access (`patient`/`clinician`); clinician record access requires an explicit
  grant and every access is audited.
- Partner access is consent-scoped: an active, scope-specific patient consent is required,
  with per-partner rate limiting and full auditing.
- Notifications are user-scoped; a user can only read or update their own.
- Audit log for security-sensitive actions; never stores document contents or test values.
- Rate limiting on auth endpoints; no account enumeration via forgot-password.
- Medical disclaimer in UI; AI distinguishes facts / reference comparison / education /
  possible context / questions for a healthcare professional; never invents reference
  ranges; never diagnoses.
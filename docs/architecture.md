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
frontend/    Vue 3 + TypeScript SPA (planned)
ai-service/  FastAPI service (planned)
docker/      Docker dev infrastructure (planned)
docs/        This documentation
```

## Database schema (planned / in-progress)

Tables: `users`, `profiles`, `medical_documents`, `document_extractions`, `lab_results`,
`ai_analyses`, `analysis_items`, `audit_logs`, plus Laravel framework tables
(`personal_access_tokens`, `jobs`, `failed_jobs`, `cache`, `sessions`, `password_reset_tokens`).

Statuses are stored as string enums:
- `medical_documents.status`: `uploaded | processing | processed | failed`
- `medical_documents.document_type`: `lab_report | doctor_report | radiology_report | unknown`
- `lab_results.status`: `within_range | above_range | below_range | positive | negative | unknown`
- `ai_analyses.status`: `pending | completed | failed`

Implemented in Milestone 1: `users` (Laravel default), `profiles`, `audit_logs`,
`personal_access_tokens`, and the framework tables. `medical_documents` was added in
Milestone 2. The remaining tables (`document_extractions`, `lab_results`, `ai_analyses`,
`analysis_items`) land with the processing pipeline in Milestone 4.

## Laravel ↔ FastAPI contract (Milestone 3)

- Auth: `X-Service-Key` header, compared with constant-time compare using `FASTAPI_API_KEY`.
- Config via env: `FASTAPI_BASE_URL`, `FASTAPI_API_KEY`, `FASTAPI_TIMEOUT`.
- Endpoints: `GET /api/v1/health`, `POST /api/v1/documents/extract`,
  `POST /api/v1/documents/parse-lab-report`, `POST /api/v1/analysis/explain`.
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
| GET/POST | `/api/v1/documents` | token | List / upload documents (Milestone 2) |
| GET/DELETE | `/api/v1/documents/{document}` | token | View / delete (Milestone 2) |
| GET  | `/api/v1/documents/{document}/analysis` | token | Analysis (Milestone 4) |

All document routes enforce ownership via Laravel Policies — never frontend-only checks.

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
| 3 | FastAPI service | FastAPI scaffold, `GET /health`, `POST /documents/extract`, `POST /documents/parse-lab-report`, `POST /analysis/explain`, Pydantic schemas, text extraction / OCR, lab parsing, LLM provider abstraction, tests | ⏳ Planned |
| 4 | Processing pipeline | `ProcessMedicalDocumentJob`, `FastApiClient`, persistence of `document_extractions`/`lab_results`/`ai_analyses`/`analysis_items`, status transitions, retries, safe failure handling | ⏳ Planned |
| 5 | Vue 3 frontend | SPA foundation (router guards, Pinia, axios, layout), auth pages, dashboard, upload flow, report/results UI, status badges, loading/error/empty states, disclaimer | ⏳ Planned |
| 6 | Hardening, docs & Docker | Audit wiring polish, rate-limit review, full README, `docker-compose.yml` (frontend, Laravel, FastAPI, PostgreSQL, Redis), full-stack tests | ⏳ Planned |

Suggested commit sequence (used so far, to be continued):

```
feat: initialize Laravel backend              ✅ 4d39d67
feat: add authentication                      ✅ 56be490
feat: add medical document uploads            ✅ 6c299f0
feat: initialize FastAPI service              ⏳
feat: add laboratory report extraction        ⏳
feat: add AI analysis                         ⏳
feat: add Vue dashboard                       ⏳
feat: add report analysis UI                  ⏳
test: add document processing tests           ⏳
docs: add project documentation               ⏳
```

## Security & safety principles

- Sanctum bearer tokens; passwords always hashed (`bcrypt`).
- Private storage; no public medical document URLs.
- Ownership via policies; server-side authorization.
- Audit log for security-sensitive actions; never stores document contents or test values.
- Rate limiting on auth endpoints; no account enumeration via forgot-password.
- Medical disclaimer in UI; AI distinguishes facts / reference comparison / education /
  possible context / questions for a healthcare professional; never invents reference
  ranges; never diagnoses.
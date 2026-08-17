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
`personal_access_tokens`, and the framework tables.

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

## Security & safety principles

- Sanctum bearer tokens; passwords always hashed (`bcrypt`).
- Private storage; no public medical document URLs.
- Ownership via policies; server-side authorization.
- Audit log for security-sensitive actions; never stores document contents or test values.
- Rate limiting on auth endpoints; no account enumeration via forgot-password.
- Medical disclaimer in UI; AI distinguishes facts / reference comparison / education /
  possible context / questions for a healthcare professional; never invents reference
  ranges; never diagnoses.
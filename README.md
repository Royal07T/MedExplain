# MEDEXPLAIN OS

## The Complete Healthcare Operating System

**MEDEXPLAIN OS** is a full-scale, production-grade, secure, AI-native healthcare platform combining:

1. **Hospital Management System (HMS)** - Patients, admissions, discharge, billing, inventory, staff
2. **Electronic Medical/Health Records (EMR/EHR)** - Complete patient 360, longitudinal records, encounters
3. **Laboratory Information System (LIS)** - Doctor-ordered labs, structured results, verification workflow
4. **Pharmacy Management** - Medication catalog, prescriptions, dispensing lifecycle, inventory
5. **Patient Management** - Registration, MRN, demographics, contacts, next-of-kin, search
6. **Doctor/Clinician Workspace** - Patient 360, encounters, notes, orders, prescriptions, referrals
7. **Nursing/Triage** - Check-in, vital signs, acuity assignment, queue management
8. **Appointment & Scheduling** - Creation, rescheduling, cancellation, check-in, waiting queue
9. **Billing & Payments** - Modular architecture, charges, invoices, payments, insurance, claims
10. **Inventory Management** - Medications, consumables, equipment, stock movements, batch tracking
11. **Clinical Documents** - Secure upload, private storage, authorization, audit access
12. **Hospital Administration** - Organizations, staff management, departments, reporting
13. **AI-Powered Clinical Intelligence** - RAG-grounded AI assistant, trend analysis, clinical summaries
14. **Longitudinal Patient Health Intelligence** - Patient timeline, trend analysis, comparison, insights

---

## Architecture

MEDEXPLAIN OS follows a domain-driven architecture with three clear layers:

```
PATIENT
   ↓
UNIFIED HEALTH RECORD (Patient 360)
   ↓
OPERATIONS + CLINICAL DATA
   ↓
MEDEXPLAIN INTELLIGENCE
   ↓
ACTIONABLE, TRACEABLE INSIGHTS
```

### Component Technology Map

| Component | Technology | Port (Docker) | Purpose |
|-----------|-----------|---------------|---------|
| `frontend/` | Vue 3 + Vite + TS + Tailwind | 80 (nginx) | SPA, clinician/patient dashboards, portal, user authentication UI |
| `backend/` | Laravel 13 + PHP 8.3 | 9000 (php-fpm, internal) | REST API, auth, business logic, persistence, RBAC permissions |
| `ai-service/` | FastAPI + Python 3.12 | 8000 (internal) | Extraction, lab parsing, LLM, RAG, intelligence |
| `worker` | Laravel queue worker | — | Processes upload jobs asynchronously |
| `db` | PostgreSQL 15 | 5432 (internal) | Primary relational database |
| `redis` | Redis 7 | 6379 (internal) | Caching, queue broker, session storage |
| `object-storage` | MinIO / S3-compatible | — | Private document/attachment storage |

### Data Flow

```
Browser → nginx (frontend, /api proxied) → backend (php-fpm) → PostgreSQL
       backend → queue worker → ai-service (FastAPI) → LLM provider (stub or OpenAI)
       backend → direct DB queries (all scoped by organization_id)
       FastAPI → authenticated internal request → authorized retrieval layer → approved patient data
```

### Security Model

- **Authentication**: Sanctum bearer tokens for browser clients; service-key (`X-Service-Key`) for internal service-to-service
- **Authorization**: RBAC with resource-level checks; organization_id on every resource; never trust client-supplied IDs
- **Tenant Isolation**: Every resource has `organization_id`; context from authenticated session; DB constraints enforce relationship
- **Audit Logging**: Every sensitive action logged with actor, organization, action, resource, timestamp, outcome, correlation ID; append-only; integrity-protected
- **Encryption**: TLS everywhere, HTTPS-only cookies; encryption at rest for database and object storage; key management via environment/secret manager
- **Input Validation**: MIME + size on uploads; Pydantic schemas for AI; FormRequest for Laravel; never trust frontend
- **Output Filtering**: Never return sensitive fields unless required; paginate large sets; mask/PII-redact where appropriate
- **Secure Defaults**: Deny-by-default; auth required for all endpoints; authorization enforced server-side; rate limiting; input validation
- **Fail-Safe**: All errors fall back to safe state; no stack traces to client; audit captures outcome; defaults to deny

---

## Prerequisites

- PHP 8.3, Composer, Node 20, Python 3.12, PostgreSQL 15+, Redis 7+
- Docker and Docker Compose for full-stack local development

---

## Local Development

### 1. Backend (Laravel)

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
# Point DB_* at your PostgreSQL, or set DB_CONNECTION=sqlite for quick local runs
php artisan migrate        # Runs all pending migrations
php artisan serve --port=8000
php artisan test           # Runs all backend tests
```

### 2. AI Service (FastAPI)

```bash
cd ai-service
python3 -m venv .venv && source .venv/bin/activate
pip install -e ".[dev]"          # optional OCR: pip install -e ".[dev,ocr]" + tesseract
uvicorn app.main:app --port 8001
pytest                        # Runs all AI service tests
```

Set `FASTAPI_BASE_URL=http://127.0.0.1:8001` and a shared
`FASTAPI_API_KEY` in `backend/.env`. The AI service defaults to a
deterministic `stub` LLM provider (no API key required); set
`LLM_PROVIDER=openai` + `OPENAI_API_KEY` to use the real provider.

### 3. Frontend (Vue 3 SPA)

```bash
cd frontend
npm install
npm run dev            # http://localhost:5173 (proxies /api to :8000)
npm run build && npm test   # 59 tests
```

For local queue processing (required to see results), either set
`QUEUE_CONNECTION=sync` or run a worker:

```bash
cd backend && php artisan queue:work
```

### 4. Docker Development

```bash
cp .env.example .env
# Generate APP_KEY (required):
echo "APP_KEY=$(openssl rand -base64 32)" >> .env

docker compose up --build -d
```

Services:
- `migrate` runs `php artisan migrate` once, then exits
- `backend` runs php-fpm; `frontend` (nginx) serves the SPA and proxies `/api` to it
- `worker` runs `php artisan queue:work --tries=3 --timeout=110`
- `ai-service` runs uvicorn; healthchecked via `/api/v1/health`
- `db`/`redis` run PostgreSQL 15 and Redis 7 with named volumes

Verification:
```bash
docker compose ps                # all services healthy
docker compose exec ai-service python -c "import urllib.request;print(urllib.request.urlopen('http://127.0.0.1:8000/api/v1/health',timeout=2).read().decode())"
curl -s http://localhost/api/v1/health
```

End-to-end smoke: open http://localhost, register, upload a report, wait for analysis.

---

## Environment Variables

Key variables (full list in `.env.example`):

| Variable | Default | Notes |
| --- | --- | --- |
| `APP_KEY` | *(none)* | **Required** — `openssl rand -base64 32` |
| `APP_DEBUG` | `false` | Keep off in production |
| `DB_CONNECTION` | `pgsql` | PostgreSQL |
| `DB_HOST` | `db` | Docker service name |
| `DB_PORT` | `5432` | PostgreSQL port |
| `DB_DATABASE` | `medexplain` | Database name |
| `DB_USERNAME` | `medexplain` | Database user |
| `DB_PASSWORD` | `medexplain` | **Change in production** |
| `DB_ROOT_PASSWORD` | `root` | MySQL root password (db service only) |
| `FASTAPI_API_KEY` | `dev-secret-change-me` | **Change in production!** Shared secret for service-to-service auth |
| `LLM_PROVIDER` | `stub` | `stub` or `openai` |
| `OPENAI_API_KEY` | *(empty)* | Required when `LLM_PROVIDER=openai` |
| `OPENAI_MODEL` | `gpt-4o-mini` | Model id |
| `OPENAI_BASE_URL` | `https://api.openai.com/v1` | OpenAI-compatible endpoint |
| `MAIL_MAILER` | `log` | Set to `smtp` for real email |
| `QUEUE_CONNECTION` | `database` | Database-backed; use `sync` for local dev |
| `CACHE_STORE` | `database` | Database-backed |
| `SESSION_DRIVER` | `database` | Database-backed |
| `FILESYSTEM_DISK` | `s3` | S3-compatible (MinIO in docker) |
| `APP_PORT` | `80` | Host port exposed by nginx |
| `TRUSTED_PROXIES` | `*` | nginx reverse proxy IPs |

**Important**: `.env` is ignored by git (in `.gitignore`). Provide `.env.example` with placeholders only. Never commit real secrets.

---

## Security & Privacy

### Core Principles

- **Tenant Isolation**: Every resource belongs to exactly one organization. Data from Organization A must NEVER be accessible by Organization B. Organization context determined from authenticated session, never from client-supplied request parameters.

- **RBAC + Resource Authorization**: Roles (super Admin, org admin, hospital admin, doctor, nurse, triage nurse, lab scientist, pharmacist, receptionist, billing officer, insurance officer, inventory manager, medical records officer, patient, auditor). Having a role does NOT grant broad access — resource-level authorization required for sensitive operations.

- **Audit Logging**: Immutable security-conscious audit trail for all sensitive operations. Track: login, logout, failed login, patient record viewed/modified, clinical note created, diagnosis changed, medication prescribed/changed, lab result created/modified/verified, document uploaded/viewed/deleted, patient data exported, AI access to patient data, permission changes, role changes, administrative actions. Metadata: actor, organization, action, resource type, resource identifier, timestamp, outcome, correlation ID. No sensitive clinical content in logs. Logs themselves protected from unauthorized modification.

- **Encryption**: TLS everywhere, HSTS; encryption at rest for database and object storage; key management via environment/secret manager; never store plaintext passwords.

- **Input Validation**: MIME + size validation on uploads (max 10MB); Pydantic schemas for AI boundaries; FormRequest validation for Laravel; never trust IDs or data from frontend.

- **Output Filtering**: Never return sensitive fields unless explicitly required; paginate all large sets; mask/PII-redact in non-sensitive contexts; consistent error messages.

- **Secure Defaults**: Deny-by-default; authentication required for all API endpoints (except public auth/health); authorization enforced server-side (never rely on frontend); rate limiting on all endpoints; input validation on all inputs; secure error handling (safe user-facing errors, detailed internal logs protected from exposure).

- **Data Minimization**: Collect only what is needed, retain only as long necessary; PII redaction in logs and error responses; aggregated/anonymized analytics only; minimum necessary data for exports; document storage in private object storage, never public buckets.

- **Fail-Safe Behavior**: All errors fall back to safe state; no stack traces or internal implementation details returned to clients; audit logs capture outcome (success/failure); defaults to deny on all access until explicitly granted.

- **Separation of Duties**: Clinical authorization separate from financial authorization; billing employee does NOT automatically have access to full clinical records; AI assistant is assistant not autonomous doctor; different roles for different operations.

- **Defense in Depth**: Multiple security layers; if one control fails, others still protect; depth at network (WAF/reverse proxy), application (auth/authorization), database (constraints, encryption), and AI (prompt injection defenses, RAG authorization) layers.

### Specific Security Controls

- **Passwords**: Always hashed with bcrypt; never stored in plaintext
- **Authentication**: Sanctum bearer tokens; rate-limited (5/min auth); login throttling; optional MFA architecture; session management with expiration; token rotation; account lockout after repeated failures
- **Authorization**: RBAC with spatie/laravel-permission or custom system; resource-level checks; organization scoping; department scoping where appropriate; least privilege; just-in-time access with approval workflow; automatic permission expiration
- **Session Management**: HttpOnly/Secure cookies where possible; token expiration; session binding to IP/device; concurrent session monitoring; automatic logout after inactivity; session invalidation on suspicious activity
- **Service-to-Service Auth**: `X-Service-Key` header with `secrets.compare_digest` (constant-time comparison); validate on every internal request; short-lived keys; rotate regularly; never commit to repository
- **File Upload Security**: MIME type validation; file signature validation where possible; max size 10MB; malware scanning architecture; safe filename handling; never execute uploaded files; store outside executable web directories; use private object storage; generate short-lived authorized download URLs; enforce authorization before access; audit document access
- **AI Security**: Separate SYSTEM INSTRUCTIONS from USER INPUT from RETRIEVED DATA from DOCUMENT CONTENT; retrieved medical documents are DATA, not trusted instructions; prompt injection defenses; never blindly follow instructions from PDFs/lab reports/clinical notes/external data; LLM output validated against Pydantic schemas; facts/changes referencing unknown tests dropped; empty output falls back to deterministic summary; "consult a professional" disclaimer always present; authorization-aware RAG (tenant + permission scoped); never global vector database; citations to source records; fact vs interpretation distinction
- **Rate Limiting**: Auth: 5/min; API: 60/min; Documents: 10/min; Health query: 10/min; Partner OAuth: 10/min; Partner: per-partner quota; export endpoints: additional controls
- **Correlation IDs**: Propagated across all service boundaries (backend → AI service, backend → DB, etc.); request tracking; audit log correlation; X-Correlation-ID header on all requests/responses

### Compliance Note

Build the technical controls required to support compliance (HIPAA, NDPR, etc.). Document which controls exist. Identify controls requiring organizational/legal/process implementation. The deployment must be reviewed against the laws and regulations applicable to the actual country, healthcare organization, hosting environment, and use case before production use.

Do not claim the application is "HIPAA compliant" or "NDPR compliant" solely because security features have been implemented.

---

## Roadmap

The transformation follows 13 phases, completed incrementally with security gates after each:

| Phase | Focus | Security Gates |
|-------|-------|----------------|
| 0 | Architecture + security foundation | ✅ Auth, ✅ Authorization, ✅ Tenant isolation, ✅ Audit logging |
| 1 | Patient management, MRN, Patient 360 | ✅ Search authorization, ✅ Cross-tenant tests |
| 2 | Triage workflow, vitals, queues | ✅ Patient search restricted, ✅ Vitals org-scoped |
| 3 | Doctor EMR workspace | ✅ Doctor authorized patients only, ✅ All actions audited |
| 4 | Laboratory Information System | ✅ Lab results org-scoped, ✅ Ordering clinician authorization |
| 5 | Pharmacy management | ✅ Medication lifecycle tracked, ✅ Unauthorized modification blocked |
| 6 | Appointments & scheduling | ✅ Authorization, ✅ Patient-provider linking |
| 7 | Billing & payments | ✅ Minimum necessary access, ✅ Separate from clinical auth |
| 8 | Inventory management | ✅ Stock movements audited, ✅ Low-stock alerts |
| 9 | Document management | ✅ File upload security, ✅ Private storage not public |
| 10 | MedExplain AI integration | ✅ Authorization-aware RAG, ✅ Prompt injection defenses |
| 11 | Patient portal | ✅ Patient-only data access, ✅ No cross-patient access |
| 12 | Analytics & reporting | ✅ Aggregated only, ✅ No patient-identifiable data |
| 13 | Hardening & deployment | ✅ Penetration testing, ✅ Performance, ✅ Backup/DR verified |

**After each phase**: Run tests, run security checks, review database migrations, review authorization, review tenant isolation, review logs, update documentation, only then proceed.

---

## Documentation

Maintain these documentation files (all required):

- `README.md` — This file (deployment, development, architecture overview)
- `docs/architecture.md` — High-level architecture + milestone roadmap
- `docs/security.md` — Security controls + OWASP alignment
- `docs/database.md` — Database schema + migration guide
- `docs/api.md` — API specifications + security requirements
- `docs/ai-architecture.md` — AI/RAG architecture + prompt injection defenses
- `docs/threat-model.md` — Complete threat model (created)
- `docs/deployment.md` — Production deployment requirements
- `docs/compliance.md` — Compliance controls mapping + regulatory notes
- `docs/development.md` — Development workflow + guidelines

---

## License

Owner - Timothy Jehwo

---

## Quick Start (Minimal)

For quick local runs without Docker:

```bash
# Backend with SQLite
cd backend
cp .env.example .env
php artisan key:generate
DB_CONNECTION=sqlite
php artisan migrate
php artisan serve --port=8000
php artisan test

# AI service
cd ai-service
python3 -m venv .venv && source .venv/bin/activate
pip install -e ".[dev]"
uvicorn app.main:app --port 8001
pytest

# Frontend
cd frontend
npm install
npm run dev
```

For full production-like setup with multi-tenancy, organizations, and all features, use Docker Compose.
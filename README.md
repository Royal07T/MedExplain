# MedExplain

An AI-powered medical report explainer. Upload a lab report and get a
plain-language, educational explanation of what each result means — clearly
separated from medical advice.

> **Disclaimer**: MedExplain is educational and never a substitute for
> professional medical care. It does not diagnose and never invents reference
> ranges. Always discuss results with a qualified healthcare professional.

## Features

- Upload PDF/JPG/PNG medical reports (max 10 MB), private per user.
- AI pipeline: text extraction → lab-result parsing → educational explanations.
- Human-readable analysis with categorized items, reference-range comparisons,
  and "questions to ask your clinician".
- Lab trends and a health timeline across uploaded reports.
- Personal health record: the latest result per lab test, medications, and a
  recent-events timeline in one view.
- AI assistant: one chat entry point grounded in your own reports, labs, and
  medications. Ask a natural-language question and get a structured, grounded
  answer covering the data, changes, education, and questions for your clinician —
  powered by deterministic backend intelligence plus the AI service.
- Clinician portal: role-based access (patient/clinician) with explicit,
  audited patient grants — clinicians only ever see consented patients.
- Provider (partner) integration: OAuth 2.0 client credentials, patient-managed
  consent, consent-scoped health-record access, and per-partner rate limits.
- OpenAPI 3.0 spec served at `GET /api/v1/api-docs`.
- Plans & subscriptions: Free/Pro plans with an idempotent, audited
  upgrade/cancel flow.
- In-app notifications for uploads, analysis readiness, plan changes, consents,
  and clinician access.
- Email verification, password reset, rate-limited auth, server-side ownership
  enforcement, and an audit log for security-sensitive actions.
- Responsive SaaS-style UI: grouped sidebar navigation plus a top navbar with
  notifications and account controls.

## Architecture

| Component | Tech | Port (compose) | Purpose |
| --- | --- | --- | --- |
| `frontend/` | Vue 3 + Vite + TS + Tailwind | 80 (nginx) | SPA, polls analysis status |
| `backend/` | Laravel 13 + PHP 8.3 | 9000 (php-fpm, internal) | REST API, auth, uploads, queue |
| `ai-service/` | FastAPI + Python 3.12 | 8000 (internal) | Extraction, lab parsing, LLM |
| `worker` | Laravel queue worker | — | Processes upload jobs asynchronously |
| `db` | MySQL 8.4 | 3306 (internal) | Data store |
| `redis` | Redis 7 | 6379 (internal) | Reserved for scale-out |

```
Browser → nginx (frontend, /api proxied) → backend (php-fpm)
        backend → queue worker → ai-service (FastAPI) → LLM provider (stub or OpenAI)
```

Auth uses Sanctum bearer tokens. The API and AI service authenticate to each
other with an `X-Service-Key` header (constant-time comparison); the AI service
also serves `GET /api/v1/health` (unauthenticated).

## Project layout

```
.
├── backend/            # Laravel API (routes, controllers, services, jobs, tests)
├── ai-service/         # FastAPI service (extraction, lab parser, LLM providers, tests)
├── frontend/           # Vue 3 SPA (views, stores, api client, components, tests)
├── docs/architecture.md  # Architecture + milestone roadmap
└── docker/             # Dockerfiles + nginx config
```

## Local development

Prerequisites: PHP 8.3, Composer, Node 20, Python 3.12, and a MySQL instance
(or use SQLite for quick runs).

### 1. Backend

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
# point DB_* at your MySQL, or set DB_CONNECTION=sqlite for quick local runs
php artisan migrate
php artisan serve --port=8000
php artisan test        # 176 tests, 589 assertions
```

### 2. AI service

```bash
cd ai-service
python3 -m venv .venv && source .venv/bin/activate
pip install -e ".[dev]"          # optional OCR: pip install -e ".[dev,ocr]" + tesseract
uvicorn app.main:app --port 8001
pytest                        # 107 tests
```

Set `FASTAPI_BASE_URL=http://127.0.0.1:8001` and a shared
`FASTAPI_API_KEY` in `backend/.env`. The AI service defaults to a
deterministic `stub` LLM provider (no API key required); set
`LLM_PROVIDER=openai` + `OPENAI_API_KEY` to use the real provider. The
OpenAI provider is endpoint-agnostic: point `OPENAI_BASE_URL` at any
OpenAI-compatible gateway (default `https://api.openai.com/v1`, or e.g.
`https://openrouter.ai/api/v1`) and set `OPENAI_MODEL` to that gateway's
model id.

### 3. Frontend

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

## Docker deployment

```bash
cp .env.example .env
# generate an APP_KEY (required):
echo "APP_KEY=$(openssl rand -base64 32)" >> .env

docker compose up --build -d
```

Services:

- `migrate` runs `php artisan migrate` once, then exits (not restarted).
- `backend` runs php-fpm; `frontend` (nginx) serves the SPA and proxies `/api`
  to it.
- `worker` runs `php artisan queue:work --tries=3 --timeout=110`.
- `ai-service` runs uvicorn; healthchecked via `/api/v1/health`.
- `db`/`redis` run MySQL 8.4 and Redis 7 with named volumes.

Verification:

```bash
docker compose ps                # all services healthy (except migrate: exited 0)
docker compose exec ai-service python -c "import urllib.request;print(urllib.request.urlopen('http://127.0.0.1:8000/api/v1/health',timeout=2).read().decode())"
curl -s http://localhost/api/v1/health
```

End-to-end smoke: open http://localhost, register, upload a report, wait for the
analysis. Private documents persist in the `document_storage` volume.

### Environment variables

Key variables (full list in `.env.example`):

| Variable | Default | Notes |
| --- | --- | --- |
| `APP_KEY` | *(none)* | **Required** — `openssl rand -base64 32` |
| `APP_DEBUG` | `false` | Keep off in production |
| `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | `medexplain` | MySQL credentials |
| `DB_ROOT_PASSWORD` | `root` | MySQL root password (db service only) |
| `FASTAPI_API_KEY` | `dev-secret-change-me` | **Change in production** |
| `LLM_PROVIDER` | `stub` | `stub` or `openai` |
| `OPENAI_API_KEY` | *(empty)* | Required when `LLM_PROVIDER=openai` |
| `OPENAI_MODEL` | `gpt-4o-mini` | Model id (OpenRouter/AgentRouter models also work) |
| `OPENAI_BASE_URL` | `https://api.openai.com/v1` | OpenAI-compatible endpoint, e.g. `https://openrouter.ai/api/v1` |
| `MAIL_MAILER` | `log` | Set to `smtp` for real email |
| `APP_PORT` | `80` | Host port exposed by nginx |

## Security & privacy

- Sanctum bearer tokens; passwords hashed with bcrypt.
- Medical documents stored on a private disk; never publicly addressable.
- Ownership enforced via policies; document processing runs per-user.
- Role-based access: `patient`/`clinician` roles, and clinicians only ever view
  patients they were explicitly granted (grants are audited, never inferred).
- Partner access is consent-scoped: a provider can only read a patient's
  record with an active, scope-specific consent granted by the patient, and
  every access is audited. Each partner has a per-minute rate limit.
- Rate limits on auth (5/min), uploads (10/min), and general API (60/min).
- Notifications are user-scoped: a user can only ever read or update their own.
- Audit log records security-sensitive actions without ever storing document
  contents or test values.
- AI responses are split into `fact`, `reference_comparison`, `education`,
  `possible_context`, and `question_for_professional` categories, and the UI
  always shows a medical disclaimer.

## Roadmap

Milestones 1–6 and their verification steps are documented in
[`docs/architecture.md`](docs/architecture.md).

## License

Owner - Timothy Jehwo<img width="1346" height="655" alt="Screenshot from 2026-08-20 01-09-55" src="https://github.com/user-attachments/assets/ca0dfbea-0357-4958-974d-f13764a0aa40" />
<img width="1346" height="655" alt="Screenshot from 2026-08-20 01-10-06" src="https://github.com/user-attachments/assets/a48c73bb-dc90-4786-bfb5-35334222a1c1" />
<img width="1346" height="655" alt="Screenshot from 2026-08-20 01-10-21" src="https://github.com/user-attachments/assets/2644dab7-fbdd-4315-91ff-a6fa96b1cedc" />
<img width="1346" height="655" alt="Screenshot from 2026-08-20 01-10-29" src="https://github.com/user-attachments/assets/84c17da9-bb32-4751-803f-7200cb6cf585" />
<img width="1346" height="655" alt="Screenshot from 2026-08-20 01-10-42" src="https://github.com/user-attachments/assets/4c8984f0-a3fe-4a84-9dbe-b8780e7a56d2" />
<img width="1346" height="655" alt="Screenshot from 2026-08-20 01-10-51" src="https://github.com/user-attachments/assets/c9d37dbe-337e-41c8-9bbc-e45745d95bb1" />
<img width="1346" height="655" alt="Screenshot from 2026-08-20 01-11-17" src="https://github.com/user-attachments/assets/fa8cb015-0a34-4a12-96d5-fbec2f437a0a" />
<img width="1346" height="655" alt="Screenshot from 2026-08-20 01-11-41" src="https://github.com/user-attachments/assets/83ac6e71-074a-4435-ba54-fac646aa09f0" />
.

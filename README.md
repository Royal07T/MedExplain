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
- Email verification, password reset, rate-limited auth, server-side ownership
  enforcement, and an audit log for security-sensitive actions.

## Architecture

| Component | Tech | Port (compose) | Purpose |
| --- | --- | --- | --- |
| `frontend/` | Vue 3 + Vite + TS + Tailwind | 80 (nginx) | SPA, polls analysis status |
| `backend/` | Laravel 12 + PHP 8.3 | 9000 (php-fpm, internal) | REST API, auth, uploads, queue |
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
php artisan test        # 57 tests, 196 assertions
```

### 2. AI service

```bash
cd ai-service
python3 -m venv .venv && source .venv/bin/activate
pip install -e ".[dev]"          # optional OCR: pip install -e ".[dev,ocr]" + tesseract
uvicorn app.main:app --port 8001
pytest                        # 32 tests
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
npm run build && npm test
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
- Rate limits on auth (5/min), uploads (10/min), and general API (60/min).
- Audit log records security-sensitive actions without ever storing document
  contents or test values.
- AI responses are split into `fact`, `reference_comparison`, `education`,
  `possible_context`, and `question_for_professional` categories, and the UI
  always shows a medical disclaimer.

## Roadmap

Milestones 1–6 and their verification steps are documented in
[`docs/architecture.md`](docs/architecture.md).

## License

Proprietary — see repository owner.

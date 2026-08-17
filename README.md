# MedExplain

AI-powered medical report explanation platform — an educational tool that helps patients understand laboratory results. It does **not** diagnose disease and does **not** replace a qualified healthcare professional.

**Milestone status: 1 (Foundation + Laravel authentication)** — backend API scaffolded, Sanctum auth, profile + audit-log schema.

## Repository layout

```
backend/     Laravel 13 REST API (auth, users, documents, business logic)
frontend/    Vue 3 + TypeScript SPA (planned)
ai-service/  FastAPI document/AI service (planned)
docker/      Docker dev infrastructure (planned)
docs/        Architecture and design documentation
```

See `docs/architecture.md` for the full design. A complete README (architecture, setup, API overview, security, medical disclaimer, roadmap) will be added in the final milestone.

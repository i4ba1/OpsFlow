# OpsFlow

> OpsFlow is a production-oriented multi-tenant operations platform built with Laravel 13 and React 19 to demonstrate modern full-stack engineering, relational data modeling, secure tenant isolation, workflow automation, SLA tracking, event-driven processing, real-time collaboration, automated testing, and deployable infrastructure.

**Status:** Phases 0–10 implemented across tenancy/RBAC, customers/projects, the service-request workflow engine, comments/files/real-time, the SLA engine, approvals, invoicing, dashboards and production hardening. See `docs/` for the full PRD and the ADRs.

## Tech Stack

| Layer       | Stack                                                                              |
| ----------- | ---------------------------------------------------------------------------------- |
| Backend     | PHP 8.5 · Laravel 13 · Sanctum · Horizon · Reverb · Pest 4 · PHPStan · Pint        |
| Frontend    | React 19 · TypeScript · Vite 8 · TanStack Query v5 · MUI · React Hook Form · Zod   |
| Data / Ops  | PostgreSQL 18 · Redis 8 · S3 (MinIO) · Nginx · Docker Compose · GitHub Actions      |

## Demo

`make setup` seeds the **Merlion Digital Services** tenant with ~120 service
requests spread across statuses and SLA outcomes so the dashboard is meaningful
immediately. Demo logins (password `password` for all):

| Role     | Email                          |
| -------- | ------------------------------ |
| Admin    | admin@demo.opsflow.local       |
| Manager  | manager@demo.opsflow.local     |
| Operator | operator@demo.opsflow.local    |
| Finance  | finance@demo.opsflow.local     |
| Customer | customer@demo.opsflow.local    |

## Local Setup

Prerequisites: Docker with the Compose plugin, GNU Make.

```bash
git clone <repo>
cd opsflow
make setup
```

`make setup` builds the images, installs Composer/npm dependencies, generates the
application key, and runs migrations with seeds.

### Services

| Service        | URL                     | Notes                       |
| -------------- | ----------------------- | --------------------------- |
| App (nginx)    | http://localhost:8080   | SPA + `/api` + Reverb proxy |
| API health     | http://localhost:8080/up | Laravel healthcheck         |
| API ping       | http://localhost:8080/api/v1/ping | Envelope smoke endpoint |
| Mailpit        | http://localhost:8025   | Local email inspection      |
| MinIO console  | http://localhost:9001   | `opsflow` / `opsflow-secret` |

## Developer Commands

```bash
make up              # start the stack
make down            # stop the stack
make logs            # tail all logs
make backend-test    # Pest test suite
make frontend-test   # Vitest test suite
make lint            # Pint + ESLint
make analyse         # PHPStan + tsc --noEmit + Prettier check
make fresh           # destroy volumes and rebuild images
make shell-backend   # shell into the backend container
make psql            # psql into PostgreSQL
```

Run `make help` for the full list.

## Repository Layout

```text
backend/    # Laravel 13 API (app, routes/api/v1, Pest tests)
frontend/   # React 19 + TypeScript SPA (Vite, TanStack Query, MUI)
docker/     # Nginx config, PHP-FPM and Node Dockerfiles
docs/       # PRD and ADRs (docs/adr/0001-0007)
.github/    # CI workflow, PR template, Dependabot
compose.yaml
Makefile
```

## Testing

- **Backend:** `make backend-test` — Pest feature/unit/architecture checks (runs
  against an ephemeral environment inside the containers, or locally with SQLite).
- **Frontend:** `make frontend-test` — Vitest + React Testing Library (jsdom).
- **CI:** GitHub Actions runs Pint → PHPStan → Pest and ESLint → tsc → Prettier →
  Vitest → Vite build on every PR (`.github/workflows/ci.yml`).

## Documentation

- Full specification: [docs/OpsFlow_Complete_Technical_PRD.md](docs/OpsFlow_Complete_Technical_PRD.md)
- Architecture decision records: `docs/adr/` (monolith, tenancy, auth, PostgreSQL,
  TanStack Query, after-commit events, SLA calendar)
- Live API reference: `GET /docs/api` (Swagger UI) when the backend is running

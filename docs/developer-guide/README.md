# Developer Guide

Everything a contributor needs to work on OpsFlow: layout, architecture,
conventions, commands, and how to add features. See the
[Deployment Guide](../deployment/README.md) for running and shipping the stack.

- [Repository layout](#repository-layout)
- [Tech stack](#tech-stack)
- [Getting started](#getting-started)
- [Commands](#commands)
- [Backend architecture](#backend-architecture)
- [Frontend architecture](#frontend-architecture)
- [Testing](#testing)
- [Code quality gates](#code-quality-gates)
- [Conventions](#conventions)
- [Adding a feature](#adding-a-feature)
- [Troubleshooting](#troubleshooting)

## Repository layout

```text
backend/                 Laravel 13 API
  app/
    Actions/             Write use-cases (one class per operation)
    Domain/              Framework-free domain logic (workflow, SLA)
    Enums/               PHP backed enums
    Events/              Domain + broadcast events
    Http/
      Controllers/Api/V1 Versioned API controllers
      Middleware/         Request id, tenancy, permissions, security headers
      Requests/          Form requests (validation)
      Resources/Api/V1   API resources (response shape)
    Jobs/                Queued jobs (SLA scan, approval expiry, invoice overdue)
    Models/              Eloquent models
    Policies/            Authorization policies
    Queries/             Read-model query objects (e.g. dashboard)
    Support/             Tenancy, audit, notifications, request numbers
  bootstrap/app.php      Middleware, routing, exceptions, broadcasting
  database/              Migrations, factories, seeders
  routes/                api.php, web.php, channels.php, console.php
  tests/                 Pest feature/unit/architecture suites
frontend/                React 19 + TypeScript SPA
  src/
    api/                 Axios client, query client, query keys, errors
    app/                 Providers and router
    auth/                Auth context and API
    components/layout/   App shell
    features/<domain>/   Pages, dialogs, hooks, API per domain
    permissions/         Guards and Can
    realtime/            Laravel Echo setup and channel hooks
    types/               Shared API and feature types
  e2e/                   Playwright specs + committed screenshots
docker/                  nginx / PHP-FPM / Node images
docs/                    PRD, ADRs, and these guides
compose.yaml             Local service topology
Makefile                 Orchestration wrapper
```

## Tech stack

| Layer | Stack |
| --- | --- |
| Backend | PHP 8.5 · Laravel 13 · Sanctum · Horizon · Reverb · Pest 4 · PHPStan · Pint |
| Frontend | React 19 · TypeScript · Vite 8 · TanStack Query v5 · MUI · React Hook Form · Zod |
| Data / Ops | PostgreSQL 18 · Redis 8 · S3 (MinIO) · Nginx · Docker Compose · GitHub Actions |

## Getting started

```bash
# Full stack in Docker (recommended)
make setup

# or, for host-based development
npm install
composer --working-dir=backend install
npm run dev            # artisan dev + Vite together via concurrently
```

Run the frontend against the Docker backend:

```bash
npm run dev --workspace frontend    # http://localhost:5173, proxies to :8080
```

## Commands

### Make (Docker wrapper)

```bash
make up / down / restart / logs
make backend-test        # Pest inside the backend container
make frontend-test       # Vitest inside the frontend container
make e2e                 # Playwright against the live stack (:8080)
make lint                # Pint + ESLint
make analyse             # PHPStan + tsc + Prettier
make format              # auto-fix Pint + Prettier
make shell-backend / shell-frontend / psql
make fresh               # destroy volumes and rebuild images
```

### Root npm scripts

```bash
npm run dev / build / test / lint / typecheck / analyse / format / e2e
```

### Running a single test

```bash
# Backend
php artisan test --filter="request lifecycle"
php artisan test tests/Feature/ServiceRequests/ServiceRequestWorkflowTest.php

# Frontend
npm run test:frontend -- request-actions-panel
```

## Backend architecture

### Request lifecycle

1. `AssignRequestId` attaches an `X-Request-ID`.
2. `ResolveTenant` resolves the tenant from the authenticated user.
3. `auth:sanctum` authenticates the bearer token.
4. `permission:<name>` (`CheckPermission`) enforces RBAC on the route.
5. Form request validates input.
6. Controller delegates to an **Action**.
7. Action runs in a DB transaction, records domain events/audit, and dispatches
   after-commit events.
8. An API **Resource** shapes the JSON envelope:
   `{ "data": ..., "meta": { "request_id": ... } }`.

### Key patterns

- **Actions** (`app/Actions`) — single-purpose write use-cases. Controllers stay
  thin; business rules are testable in isolation.
- **Domain** (`app/Domain`) — framework-free logic: `ServiceRequestWorkflow`
  (state machine + per-edge permissions), `SlaCalculator`, `SlaStateEvaluator`,
  `BusinessCalendar`.
- **Form requests** — all validation; never trust the client.
- **Resources** — the wire contract. Frontend types must match these exactly.
- **Policies** — row-level authorization; controllers also assert same-tenant.
- **Enums** — statuses, priorities, and types are backed enums.
- **Query objects** (`app/Queries`) — read-model aggregations (dashboards),
  cached per organization.

### Multi-tenancy

Every tenant-owned row carries `organization_id`. List queries always filter by
the authenticated user's organization, and detail endpoints `abort(404)` on a
foreign organization (404, not 403, so existence is never leaked). Cross-tenant
coverage is part of the Pest suite.

### RBAC

Permissions are seeded from the catalog in
`database/seeders/PermissionSeeder.php` and grouped onto system roles in
`OrganizationRoleSeeder.php` (admin, manager, operator, finance, customer).
Routes declare required permissions via the `permission` middleware alias;
`ServiceRequestWorkflow::permissionFor()` maps transition edges to the
resolve/close/reopen capabilities. The API exposes only the transitions the
current user may actually perform (`available_transitions`).

### Events, queues, and scheduling

- Domain events (`ServiceRequestCreated`, `CommentCreated`, …) are internal.
- `*Broadcast` events implement `ShouldBroadcast` and go out on
  `afterCommit` — clients receive only resource identity and re-fetch.
- `NotificationDispatcher` persists in-app notifications and pushes
  `notification.created`.
- `routes/console.php` schedules the SLA monitor (every minute), approval
  expiry (five minutes), and invoice overdue sweep (hourly).
- Horizon supervises queues; `/internal/health` verifies the workers.

### Realtime

`routes/channels.php` authorizes private channels (bare names without the
`private-` prefix — Laravel strips it before matching):

```text
organization.{organizationId}   any org member
request.{serviceRequestId}      org member (or request.view holders)
user.{userId}                   that user only
```

Channel authorization runs through `/broadcasting/auth`, registered with
`['middleware' => ['auth:sanctum']]` in `bootstrap/app.php`.

## Frontend architecture

### Server state

All server state lives in TanStack Query. Query keys are centralised in
`src/api/query-keys.ts` (`customerKeys`, `projectKeys`, `requestKeys`,
`approvalKeys`, `invoiceKeys`, `notificationKeys`, `slaKeys`). Mutations update
or invalidate the relevant keys; realtime events invalidate on the client.

### Auth and permissions

- `auth-context.tsx` stores the bearer token in `localStorage`, restores the
  session via `GET /me`, and exposes `user`, `hasPermission`, and `login/logout`.
- `permissions/guards.tsx` provides `RequireAuth` and `RequirePermission` route
  guards; `permissions/Can.tsx` provides the `Can` component and `usePermission`.
- Navigation is filtered by permission, and the API remains authoritative.

### Realtime

- `realtime/echo.ts` lazily builds a Laravel Echo (Reverb) client, sending the
  bearer token to `/broadcasting/auth`.
- `RealtimeProvider` owns the connection lifecycle and subscribes to the user
  channel for notifications.
- `useRequestChannel(requestId)` invalidates a request's detail/history/comments
  on `request.updated`, `request.assigned`, and `comment.created`.

### Forms

React Hook Form + Zod everywhere. Dialog components own their mutation and
invalidate on success; pages stay declarative.

## Testing

| Layer | Tool | Location |
| --- | --- | --- |
| Backend feature/unit/arch | Pest 4 | `backend/tests` |
| Frontend components/units | Vitest + Testing Library | `frontend/src/__tests__` |
| End-to-end | Playwright | `frontend/e2e` |

- Pest runs against SQLite in memory (`phpunit.xml`) with `RefreshDatabase`; the
  `force="true"` env flags keep tests isolated even when the Docker container
  provides a production-like environment.
- Playwright drives the **real** stack (`make up && make seed` first) and saves a
  full-page screenshot per scenario to `frontend/e2e/screenshots/`.
- Re-seed before E2E when you need deterministic data: `make fresh && make setup`
  (or `docker compose exec backend php artisan migrate:fresh --seed`).

## Code quality gates

Run before every push:

```bash
make format     # Pint + Prettier
make lint       # Pint --test + ESLint
make analyse    # PHPStan + tsc + Prettier --check
make backend-test
make frontend-test
```

CI enforces the same gates (see `.github/workflows/ci.yml`).

## Conventions

- **PHP:** PSR-12 via Pint (`pint.json`). Strict types, final classes for
  services/actions, typed properties, and no framework calls in `Domain`.
- **TypeScript:** strict mode, no `any`, feature-first folders, colocated API
  functions.
- **No comments unless they explain intent** — the code should read clearly.
- **Commits:** Conventional Commits, e.g. `feat(requests): add transition workflow`,
  `fix(sla): skip holidays`, `test(auth): cross-tenant coverage`, `docs(adr): ...`.
- **Decisions:** record significant architectural choices as ADRs in `docs/adr/`.

## Adding a feature

A typical vertical slice:

1. **Migration / model** — add columns and casts; update factories.
2. **Enum + validation** — add a backed enum and a form request.
3. **Action** — implement the write use-case in a transaction; record events and
   audit entries; dispatch after-commit broadcasts.
4. **Controller + route** — thin controller, `permission:` middleware, resource
   response.
5. **Pest test** — happy path, validation, RBAC, and cross-tenant cases.
6. **Frontend API + types** — add functions to `features/<domain>/*-api.ts` and
   types to `types/features.ts`; add query keys.
7. **UI** — page/dialog with React Hook Form + Zod; gate controls with
   `usePermission()`.
8. **Vitest + Playwright** — unit/component coverage and, for critical paths, an
   E2E scenario.
9. **Docs** — update the relevant guide and, if architectural, add an ADR.

## Troubleshooting

| Symptom | Cause / fix |
| --- | --- |
| `502` after recreating the backend container | nginx cached the old container IP — `docker compose restart nginx`. |
| `make backend-test` hits the dev database | Ensure `phpunit.xml` env entries use `force="true"` and that Compose does not inject `backend/.env` as process env. |
| Login returns `429` during manual testing | 5 requests/minute per IP — clear with `php artisan cache:clear`. |
| `npm run test` picks up Playwright specs | Vitest is scoped to `src/**`; keep E2E specs in `frontend/e2e`. |
| Timeline shows `— → —` | The event resource shape is `occurred_at` + `data.from`/`data.to`; keep frontend types in sync. |

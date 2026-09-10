# Deployment Guide

This guide covers running OpsFlow locally with Docker Compose and preparing a
production-style deployment. For day-to-day development see the
[Developer Guide](../developer-guide/README.md).

- [Architecture at a glance](#architecture-at-a-glance)
- [Prerequisites](#prerequisites)
- [Environment configuration](#environment-configuration)
- [Local deployment (one command)](#local-deployment-one-command)
- [Service map](#service-map)
- [Production deployment](#production-deployment)
- [Docker specifics](#docker-specifics)
- [Health checks and observability](#health-checks-and-observability)
- [CI/CD](#cicd)
- [Post-deploy checklist](#post-deploy-checklist)
- [Troubleshooting](#troubleshooting)

## Architecture at a glance

```text
Browser ──► nginx (:8080)
              ├── /            → frontend SPA (Vite dev server locally, static build in prod)
              ├── /api/...     → PHP-FPM (Laravel 13 API)
              ├── /up          → Laravel health endpoint
              ├── /internal/health → service-check probe (firewall this)
              ├── /broadcasting/ → Sanctum-authenticated channel authorization
              └── /app/...     → Reverb WebSocket server

Laravel ──► PostgreSQL 18   (source of truth)
         ──► Redis 8         (cache, sessions, queues, Horizon, Reverb scaling)
         ──► S3 / MinIO      (attachments)
         ──► SMTP / Mailpit  (local email)
Queue worker: Horizon   Scheduler: php artisan schedule:work
```

## Prerequisites

- Docker Engine with the Compose plugin
- GNU Make (the `Makefile` is the orchestration entry point)
- Ports `8080`, `8025`, `9001` free on the host

For a non-Docker install you additionally need PHP 8.3+ with `pdo_pgsql`,
`redis`, `intl`, `bcmath`, `zip`, `pcntl`; Composer 2; Node 24+; PostgreSQL 18;
Redis 8; and an S3-compatible bucket.

## Environment configuration

Both applications ship with `.env.example` templates. `make setup` copies them to
`.env` on first run.

### Backend (`backend/.env`)

| Variable | Purpose | Local default |
| --- | --- | --- |
| `APP_ENV` / `APP_DEBUG` | Runtime mode | `local` / `true` |
| `APP_KEY` | Encryption key (`php artisan key:generate`) | generated |
| `APP_URL` | Public base URL | `http://localhost:8080` |
| `DB_CONNECTION` … `DB_PASSWORD` | PostgreSQL 18 connection | `pgsql` / `postgres` |
| `SESSION_DRIVER` | Session store | `redis` |
| `CACHE_STORE` | Cache/rate-limiter store | `redis` |
| `QUEUE_CONNECTION` | Queue backend | `redis` |
| `SANCTUM_STATEFUL_DOMAINS` | SPA cookie domains | `localhost:8080,localhost:5173` |
| `BROADCAST_CONNECTION` | Realtime driver | `reverb` |
| `REVERB_*` | Reverb app credentials/host/port/scheme | `opsflow-key`, `reverb:8080` |
| `FILESYSTEM_DISK` | Attachment disk | `s3` |
| `AWS_*` | S3/MinIO credentials and endpoint | MinIO at `http://minio:9000` |
| `MAIL_*` | Mail transport | Mailpit at `mailpit:1025` |
| `SENTRY_LARAVEL_DSN` | Optional error tracking | empty |

### Frontend (`frontend/.env`)

| Variable | Purpose | Local default |
| --- | --- | --- |
| `VITE_API_BASE_URL` | REST API base path | `/api/v1` |
| `VITE_REVERB_APP_KEY` | Must match backend `REVERB_APP_KEY` | `opsflow-key` |
| `VITE_REVERB_HOST` / `VITE_REVERB_PORT` / `VITE_REVERB_SCHEME` | WebSocket endpoint | `localhost` / `8080` / `http` |

> The Reverb app key is public (used for the WebSocket handshake); the secret
> stays server-side only.

## Local deployment (one command)

```bash
make setup
```

`make setup` runs `build → init-env → install → key → migrate`:

1. builds the backend and frontend images,
2. copies `.env.example` → `.env` where missing,
3. installs Composer and npm dependencies inside the containers,
4. generates the Laravel app key,
5. migrates and seeds the **Merlion Digital Services** demo tenant.

When it finishes the app is live at <http://localhost:8080>. Sign in with any
demo account (password `password`):

| Role | Email |
| --- | --- |
| Admin | `admin@demo.opsflow.local` |
| Manager | `manager@demo.opsflow.local` |
| Operator | `operator@demo.opsflow.local` |
| Finance | `finance@demo.opsflow.local` |
| Customer | `customer@demo.opsflow.local` |

Step-by-step equivalents:

```bash
make build       # build the images
make up          # start all services
make init-env    # copy .env templates
make install     # composer install + npm install + up -d
make key         # php artisan key:generate
make migrate     # migrate --seed
make seed        # re-run seeders (idempotent)
```

## Service map

| Service | URL | Notes |
| --- | --- | --- |
| App (nginx) | <http://localhost:8080> | SPA + `/api` + Reverb proxy |
| API health | <http://localhost:8080/up> | Laravel health endpoint |
| Internal health | <http://localhost:8080/internal/health> | DB/Redis/queue checks — firewall in prod |
| API ping | <http://localhost:8080/api/v1/ping> | Envelope smoke endpoint |
| API docs | <http://localhost:8080/docs/api> | Swagger UI over `/openapi.yaml` |
| Mailpit | <http://localhost:8025> | Local email inspection |
| MinIO console | <http://localhost:9001> | `opsflow` / `opsflow-secret` |

## Production deployment

Use the local stack as the reference topology: nginx in front of PHP-FPM, a
separate Horizon worker, a scheduler process, and a Reverb process, all sharing
one PostgreSQL and one Redis.

### 1. Build artifacts

```bash
# Backend (no dev dependencies, optimized autoloader)
composer --working-dir=backend install --no-dev --optimize-autoloader

# Frontend static build (outputs frontend/dist)
npm ci
npm run build
```

Serve `frontend/dist` as static files from nginx (instead of proxying to the Vite
dev server used locally). Keep the `/api`, `/up`, `/broadcasting`, and `/app`
locations proxied to PHP-FPM / Reverb.

### 2. Configure the environment

Set at minimum:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:...          # php artisan key:generate --show
APP_URL=https://opsflow.example.com
LOG_LEVEL=warning

DB_CONNECTION=pgsql
DB_HOST=...                 # managed PostgreSQL 18
DB_DATABASE=opsflow
DB_USERNAME=...
DB_PASSWORD=...

SESSION_DRIVER=redis
CACHE_STORE=redis
QUEUE_CONNECTION=redis
REDIS_HOST=...
REDIS_PASSWORD=...

BROADCAST_CONNECTION=reverb
REVERB_APP_ID=...
REVERB_APP_KEY=...
REVERB_APP_SECRET=...
REVERB_HOST=ws.opsflow.example.com
REVERB_PORT=443
REVERB_SCHEME=https

FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=...
AWS_BUCKET=...
# Omit AWS_ENDPOINT / AWS_USE_PATH_STYLE_ENDPOINT for real AWS S3.

MAIL_MAILER=smtp
MAIL_HOST=...
```

Point the frontend build at the same WebSocket key/host:

```dotenv
VITE_REVERB_APP_KEY=...        # same as REVERB_APP_KEY
VITE_REVERB_HOST=ws.opsflow.example.com
VITE_REVERB_PORT=443
VITE_REVERB_SCHEME=https
```

### 3. Release the application

```bash
php artisan migrate --force
php artisan optimize          # config + route + view cache
# On each deploy, restart long-running processes:
php artisan queue:restart     # or supervisorctl restart horizon
```

### 4. Run the long-lived processes

| Process | Command |
| --- | --- |
| Web | `php-fpm` behind nginx (TLS terminated at the proxy) |
| Queue / Horizon | `php artisan horizon` |
| Scheduler | `php artisan schedule:work` (or a `* * * * * php artisan schedule:run` cron) |
| WebSockets | `php artisan reverb:start --host=0.0.0.0 --port=8080` |

The scheduler owns three jobs (see `routes/console.php`):

- SLA monitor — every minute (`CheckActiveServiceRequestSlas`)
- Approval expiry — every five minutes (`ExpirePendingApprovals`)
- Invoice overdue sweep — hourly (`MarkOverdueInvoices`)

Multiple Reverb nodes scale horizontally through Redis; set `REVERB_SCALING_ENABLED`
and point every node at the same Redis.

### 5. Storage permissions

PHP-FPM workers run as `www-data`. The image creates `storage/**` and
`bootstrap/cache` with `www-data` ownership. If you mount a fresh volume, ensure
those paths are writable:

```bash
chown -R www-data:www-data backend/storage backend/bootstrap/cache
```

### 6. Protect internal endpoints

- Firewall `/internal/health` to your load balancer/orchestrator only.
- Put the Horizon dashboard behind authentication before exposing it.
- Terminate TLS at the proxy and forward `X-Forwarded-*`; configure
  `TrustProxies` accordingly.

## Docker specifics

- **Backend image** — `docker/backend/Dockerfile` builds on `php:8.5-fpm-bookworm`
  with `pdo_pgsql`, `pgsql`, `zip`, `intl`, `pcntl`, `bcmath`, the Redis PECL
  extension, and Composer. Build-time opcache is already provided by the base image.
- **Frontend image** — `docker/frontend/Dockerfile` runs the Vite dev server
  locally; in production serve the static build from nginx.
- **nginx** — `docker/nginx/default.conf` routes `/api`, `/up`, `/sanctum`,
  `/broadcasting` to Laravel and `/app` to Reverb.
- **Compose volumes** — `backend_vendor`, `backend_storage`, `frontend_node_modules`
  are named volumes; `pgdata`, `redis_data`, `minio_data` hold state.
- **PostgreSQL 18** — the data volume mounts at `/var/lib/postgresql` (18 stores
  data in a version-specific subdirectory), *not* `/var/lib/postgresql/data`.

> Laravel reads configuration from the bind-mounted `backend/.env`; the Compose
> services intentionally do **not** inject `.env` as process environment. This
> keeps the test suite isolated (see the Developer Guide).

## Health checks and observability

- `GET /up` — orchestrator liveness probe.
- `GET /internal/health` — detailed per-service check (database, Redis, queue),
  returns `ok`/`error`.
- Horizon UI — queue throughput, failed jobs, and retries.
- Logs — `make logs` (all services) or `make logs-backend`.
- Email — Mailpit at <http://localhost:8025> locally.
- Sentry — set `SENTRY_LARAVEL_DSN` to enable error tracking.

## CI/CD

`.github/workflows/ci.yml` runs on every push/PR:

| Backend job | Frontend job |
| --- | --- |
| Pint (style) | ESLint |
| PHPStan / Larastan | `tsc --noEmit` |
| Pest (feature/unit/arch) | Prettier check |
| Composer audit | Vitest |
| | Vite production build |
| | `npm audit --audit-level=high` |

The backend job provisions a `postgres:18-alpine` service and runs migrations
before the Pest suite (the suite itself runs against SQLite in memory).

## Post-deploy checklist

- [ ] `APP_ENV=production`, `APP_DEBUG=false`, strong `APP_KEY` set.
- [ ] Database migrated (`php artisan migrate --force`).
- [ ] `php artisan optimize` executed and caches warmed.
- [ ] Horizon, scheduler, and Reverb processes supervised and auto-restarting.
- [ ] `/up` returns `200`; `/internal/health` reports all services healthy.
- [ ] WebSocket handshake succeeds and `/broadcasting/auth` returns `200` for an
      authenticated user (private channels).
- [ ] Attachments upload to and download from the configured object storage.
- [ ] `SANCTUM_STATEFUL_DOMAINS` and `REVERB_*` match the public domain.
- [ ] Outbound email delivered (SMTP credentials valid).
- [ ] TLS terminated; `/internal/health` and Horizon are not publicly reachable.

## Troubleshooting

| Symptom | Cause / fix |
| --- | --- |
| `502 Bad Gateway` after recreating only the backend container | nginx cached the old container IP — `docker compose restart nginx`. |
| `make setup` fails building the backend image | Ensure opcache is **not** added to `docker-php-ext-install`/`docker-php-ext-enable`; the base image already provides it. |
| PostgreSQL container restarts immediately | The `pgdata` volume is mounted at `/var/lib/postgresql/data`; PostgreSQL 18 requires `/var/lib/postgresql`. |
| Login returns `500` with a `bigint` error | The Sanctum migration must use `uuidMorphs('tokenable')` for UUID users. |
| Attachment upload throws `PortableVisibilityConverter not found` | Install `league/flysystem-aws-s3-v3` (required by the S3 disk). |
| Login returns `429` | Login is rate-limited to 5 requests/minute per IP. |

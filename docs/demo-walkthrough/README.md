# Demo Walkthrough

A guided tour of OpsFlow for demos, interviews, and reviewers. It follows the
PRD §89 "recruiter demo" story and uses the committed Playwright screenshots so
the experience is reproducible.

- [Before you start](#before-you-start)
- [Demo credentials](#demo-credentials)
- [The story](#the-story)
- [Automated proof: E2E screenshots](#automated-proof-e2e-screenshots)
- [What to highlight](#what-to-highlight)
- [Video walkthrough outline](#video-walkthrough-outline)

## Before you start

```bash
make fresh && make setup     # clean volumes, build, migrate, seed
```

The stack is then live at <http://localhost:8080>, seeded with the **Merlion
Digital Services** tenant: three customers, three projects, an SLA policy on the
Singapore business calendar, ~120 service requests across statuses and SLA
outcomes, pending approvals, and invoices.

Confirm the running E2E suite at any time:

```bash
make e2e
```

## Demo credentials

Password is `password` for every account.

| Role | Email | Use it to show |
| --- | --- | --- |
| Manager | `manager@demo.opsflow.local` | Assignment, priorities, transitions, approvals, invoices |
| Operator | `operator@demo.opsflow.local` | Working requests, comments, attachments |
| Customer | `customer@demo.opsflow.local` | The customer portal and creating requests |
| Finance | `finance@demo.opsflow.local` | Invoicing |
| Admin | `admin@demo.opsflow.local` | Full access |

## The story

### 1 — Manager: the operations picture

Sign in as the manager and land on the **Operations dashboard**. Point out the
live cards — open requests, critical, at-risk and breached SLA, compliance, and
median resolution — plus the 30-day volume and priority charts. This is a single
tenant's data, isolated by row-level tenancy.

### 2 — Customer: raise a request

Sign in as the customer and click **New request**. Note that customer users don't
pick a customer — it's inferred from their organization membership. Enter
something like *"Intermittent API timeout during checkout"* with priority
`HIGH`.

### 3 — Manager: work it in real time

Back as the manager, open **Requests** — the new request is at the top. Open it
and use the **Manage** panel: **assign** the operator, **change priority**, and
**transition** the status. The **Activity timeline** records every step. Because
OpsFlow is real-time, other connected sessions update without a refresh.

### 4 — Operator: collaborate

As the operator, comment on the request and **upload a diagnostic attachment**.
Internal notes stay hidden from the customer; public replies are visible.

### 5 — SLA

Explain that a scheduled job scans open requests every minute, recomputes SLA
state against business hours, and raises at-risk and breach notifications. The
request's SLA badge reflects this live.

### 6 — Approval

From the request, **Request approval** and assign the customer. Switch to the
customer, open **Approvals**, and **Approve** it. The status becomes `APPROVED`.

### 7 — Invoices

As finance (or the manager), open **Invoices**, create an invoice with line
items, **issue** it, and **mark it paid**. Due dates drive the automatic
overdue sweep.

### 8 — Architecture

Close on the engineering depth: Laravel actions and policies, the workflow state
machine with per-edge permissions, PostgreSQL schema and indexes, the React
query hooks, and the GitHub Actions pipeline. The
[Developer Guide](../developer-guide/README.md) maps it all out.

## Automated proof: E2E screenshots

The Playwright suite (`frontend/e2e/app.spec.ts`) drives the real stack through
the critical paths and writes one screenshot per scenario on every run. These are
the committed images referenced in the [root README](../../README.md).

### Login

<img src="../../frontend/e2e/screenshots/01-login.png" alt="OpsFlow login screen" width="720">

### E2E-001 — Manager request lifecycle

Create → assign → change priority → transition → verify the timeline.

<img src="../../frontend/e2e/screenshots/02-e2e-001-manager-lifecycle.png" alt="Manager request lifecycle" width="720">

The detail page shows the assigned operator (Wei Ming Tan), `CRITICAL` priority,
the transition controls (`MARK IN PROGRESS`, `MARK CANCELLED`), and the timeline
ending in `NEW → TRIAGED`.

### E2E-002 — Customer collaboration

The customer portal creates a request, comments, and uploads an attachment.

<img src="../../frontend/e2e/screenshots/03-e2e-002-customer-collaboration.png" alt="Customer collaboration" width="720">

### E2E-003 — Approval

A customer approves a pending approval; the status shows `APPROVED`.

<img src="../../frontend/e2e/screenshots/04-e2e-003-approval.png" alt="Approval flow" width="720">

### E2E-004 — Cross-role authorization

An operator reaching for an unauthorized route (invoices) is redirected to the
dashboard, and permission-gated navigation is hidden.

<img src="../../frontend/e2e/screenshots/05-e2e-004-cross-role.png" alt="Cross-role authorization" width="720">

## What to highlight

- **Secure multi-tenancy** — every query is organization-scoped; cross-tenant
  access returns 404 in the test suite.
- **RBAC end to end** — permissions gate routes, navigation, and UI controls,
  and the API is always authoritative.
- **A real workflow engine** — the transition matrix is code, with per-edge
  capabilities and optimistic locking.
- **Business-hours SLA** — due dates skip weekends and holidays and drive
  scheduled monitoring.
- **Event-driven + realtime** — after-commit broadcasts, notifications, and a
  private-channel authorization model.
- **Production posture** — PostgreSQL 18, Redis, Horizon, Reverb, S3, Docker,
  CI gates (Pint, PHPStan, Pest, ESLint, tsc, Prettier, Vitest, build), and ADRs.

## Video walkthrough outline

Target length 3–5 minutes (PRD §90):

```text
00:00  Introduction — what OpsFlow is
00:20  The problem it solves
00:50  Architecture — Laravel API + React SPA + Postgres/Redis/Reverb
01:20  Customer request flow
02:00  Workflow and authorization
02:30  SLA and background processing
03:00  Real-time collaboration
03:30  Testing and security
04:00  CI/CD and deployment
04:30  Closing
```

Explain decisions confidently; don't read source line by line.

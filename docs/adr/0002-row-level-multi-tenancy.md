# 0002 — Row-Level Multi-Tenancy

## Context

OpsFlow serves many organizations from one deployment. Each organization must
never see another's requests, customers, invoices or audit rows.

## Decision

Single database, shared schema, **row-level tenant ownership**: every
tenant-owned table carries an `organization_id` UUID column. A `TenantContext`
singleton is resolved per request from the authenticated user's organization,
and every query is scoped to it. Route-model bindings are org-guarded
(`abort_unless(..., 404)`) so foreign ids look identical to missing ids.

## Alternatives

- Database-per-tenant — operationally heavy and overkill for the portfolio.
- PostgreSQL RLS — powerful but obscures the application-level story and adds
  deployment complexity for little gain here.

## Consequences

- All queries must be scoped; this is enforced centrally via middleware,
  controller org guards and feature tests proving cross-tenant isolation.
- A future RLS migration can sit behind the same model boundary.

# 0003 — Sanctum Token Authentication

## Context

The React SPA needs to authenticate against the Laravel API. The SPA runs from
the same origin in production (nginx), so cookie sessions are an option, but
the local dev layout serves the SPA through the same nginx entrypoint.

## Decision

Use **Laravel Sanctum with bearer tokens** issued at login and stored by the
SPA in `localStorage`. The axios client attaches `Authorization: Bearer` and
drops the token on any 401. Sanctum's stateful middleware stays enabled so a
future move to HttpOnly cookies (PRD §38) is a configuration change, not a
rewrite.

## Alternatives

- Session cookies via `EnsureFrontendRequestsAreStateful` — more secure against
  XSS token theft but needs CSRF bootstrap on every cold start and stricter
  same-origin handling during local dev.

## Consequences

- Tokens are visible to JavaScript; mitigated by an `HttpOnly`-cookie path
  being documented as the production preference and the API enforcing
  authorization server-side regardless of the transport.

# 0001 — Modular Monolith

## Context

OpsFlow is a B2B operations platform that needs clear module boundaries across
identity, customers, service requests, SLA, approvals, invoicing and reporting,
while remaining a portfolio project that a reviewer can run and reason about.

## Decision

Build a **modular monolith**: one Laravel application with explicit
`Actions`/`Domain` folders per capability, one React SPA, one PostgreSQL
database, Redis for queues/cache and Reverb for WebSockets. No service
boundaries between modules.

## Alternatives

- **Microservices** — network boundaries, distributed transactions, service
  discovery and per-service operations are not justified at this scale.
- **Fat controllers** — rejected; business logic lives in transactional
  actions and pure domain services.

## Consequences

- Cheap to run, test and deploy; module boundaries come from code discipline
  (enforced partly by Pest architecture tests).
- Moving a capability out later is possible because each module owns its
  domain logic and exposes explicit actions.

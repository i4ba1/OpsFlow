# 0004 — PostgreSQL

## Context

OpsFlow stores transactional business data with strong integrity requirements:
money, state machines, unique human-readable numbers, aggregate reporting.

## Decision

Use **PostgreSQL 18** for the primary database. Money is stored in
`NUMERIC(18,2)` columns; JSON payloads (metadata, audit values) use `jsonb`;
aggregations run in SQL, not PHP.

## Alternatives

- MySQL — comparable for simple CRUD but weaker JSON/analytic ergonomics.
- SQLite — used only for the local/CI test runner, never production.

## Consequences

- Dashboard aggregations and partial index opportunities are available later
  (e.g. the open-request SLA scan index in PRD §25).
- The test suite runs on SQLite in-memory, so migrations stay portable between
  the two engines.

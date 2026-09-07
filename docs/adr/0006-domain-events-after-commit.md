# 0006 — Domain Events After Commit

## Context

Sensitive mutations (request transitions, assignments, approvals, invoices)
must persist state atomically and then trigger side effects: notifications,
broadcasts and cache invalidation. Firing events before the transaction commits
can notify users about changes that were rolled back.

## Decision

Dispatch **domain events via `DB::afterCommit`** so listeners run only after
the transaction is durable. Queued jobs and broadcasts are dispatched from the
after-commit callback; idempotent jobs (SLA monitor, invoice overdue) guard
against duplicates with status filters and unique keys.

## Alternatives

- Fire events immediately inside the transaction — leaks rolled-back state.
- Rely on listeners polling — adds latency and complexity.

## Consequences

- Side effects are consistent with the database state; the request-created,
  request-assigned and approval events all follow this pattern.

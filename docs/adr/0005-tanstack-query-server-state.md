# 0005 — TanStack Query for Server State

## Context

The React frontend talks to a REST API for collections, resources and
mutations. Server state has caching, invalidation, refetch and mutation
lifecycle semantics that are fundamentally different from local UI state.

## Decision

Own **all server state through TanStack Query v5**: query keys per feature
(PRD §37), `useQuery` for reads and `useMutation` for writes, with cache
invalidation driven by success handlers. Local UI state (dialogs, filters,
wizard steps) stays in React state; forms use React Hook Form + Zod.

## Alternatives

- Redux/Zustand global stores — duplicate API data in client state and force
  hand-rolled caching and refetch logic.
- Plain fetch + useEffect — no deduplication, no background refetch, manual
  loading/error handling everywhere.

## Consequences

- Consistent loading/error/empty states per feature; mutations invalidate
  exactly the right query keys; less client state to reason about.

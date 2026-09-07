# 0007 — SLA Business Calendar

## Context

SLA due dates must reflect working hours, weekends and holidays in the
organization's timezone — "add N minutes" against wall-clock time is wrong for
a request raised on a Friday afternoon with a 4-hour critical resolution
budget.

## Decision

Model a **business calendar** (weekly hour ranges + holiday dates in an IANA
timezone) per organization. A pure `SlaCalculator` walks working windows in the
calendar timezone: remaining budget minutes consume only open windows, skipping
nights, weekends and holidays, and results are returned as UTC instants. All
timestamps are stored in UTC; the organization timezone is presentation and
calculation context only.

## Alternatives

- Wall-clock deadlines — demonstrably wrong across weekends and holidays.
- Third-party calendar/SLA engines — heavy and opaque for the demonstration
  of the exact algorithm.

## Consequences

- The calculator is a pure service fully unit-tested (weekend, midnight,
  holiday and timezone cases). The per-minute monitor derives
  HEALTHY/AT_RISK/BREACHED/MET states from business minutes elapsed and records
  each threshold crossing exactly once.

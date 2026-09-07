import type { ServiceRequest, SlaState } from '../../types/features';

export type SlaSummary = {
  state: SlaState;
  label: string;
  remainingLabel: string | null;
};

/**
 * Derive a lightweight SLA summary from the request's due timestamps.
 * The authoritative business-hours calculation lives on the backend; this
 * gives the UI an immediate wall-clock signal for open requests.
 */
export function slaSummary(request: ServiceRequest, now = new Date()): SlaSummary {
  if (request.resolution_due_at === null) {
    return { state: null, label: 'No SLA', remainingLabel: null };
  }

  const due = new Date(request.resolution_due_at);
  const resolvedAt = request.resolved_at !== null ? new Date(request.resolved_at) : null;

  if (resolvedAt !== null) {
    const met = resolvedAt.getTime() <= due.getTime();
    return {
      state: met ? 'MET' : 'BREACHED',
      label: met ? 'MET' : 'BREACHED',
      remainingLabel: null,
    };
  }

  const msRemaining = due.getTime() - now.getTime();

  if (msRemaining <= 0) {
    return { state: 'BREACHED', label: 'BREACHED', remainingLabel: 'Overdue' };
  }

  // Wall-clock proxy for the warning band; backend computes business time.
  const hoursRemaining = msRemaining / 3_600_000;
  const label = hoursRemaining < 4 ? 'AT RISK' : 'HEALTHY';
  const state: SlaState = hoursRemaining < 4 ? 'AT_RISK' : 'HEALTHY';

  return { state, label, remainingLabel: formatRemaining(msRemaining) };
}

function formatRemaining(ms: number): string {
  const totalMinutes = Math.max(1, Math.floor(ms / 60_000));
  const hours = Math.floor(totalMinutes / 60);
  const minutes = totalMinutes % 60;

  if (hours > 24) {
    const days = Math.floor(hours / 24);
    return `${days}d ${hours % 24}h`;
  }

  return `${hours}h ${minutes}m`;
}

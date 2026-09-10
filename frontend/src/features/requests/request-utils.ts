import type { ServiceRequestPriority, ServiceRequestStatus } from '../../types/features';

export const STATUS_LABEL: Record<ServiceRequestStatus, string> = {
  NEW: 'New',
  TRIAGED: 'Triaged',
  IN_PROGRESS: 'In progress',
  WAITING_CUSTOMER: 'Waiting on customer',
  WAITING_INTERNAL: 'Waiting on internal',
  ESCALATED: 'Escalated',
  RESOLVED: 'Resolved',
  CLOSED: 'Closed',
  CANCELLED: 'Cancelled',
};

export const PRIORITY_COLORS: Record<
  ServiceRequestPriority,
  'error' | 'warning' | 'info' | 'default'
> = {
  CRITICAL: 'error',
  HIGH: 'warning',
  MEDIUM: 'info',
  LOW: 'default',
};

export const TERMINAL_STATUSES: ServiceRequestStatus[] = ['RESOLVED', 'CLOSED', 'CANCELLED'];

export function isTerminal(status: ServiceRequestStatus): boolean {
  return TERMINAL_STATUSES.includes(status);
}

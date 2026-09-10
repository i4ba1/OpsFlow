import type { AppNotification } from '../../types/features';

const NOTIFICATION_LABELS: Record<string, string> = {
  REQUEST_CREATED: 'Request created',
  REQUEST_ASSIGNED: 'Request assigned',
  REQUEST_PRIORITY_CHANGED: 'Priority changed',
  REQUEST_STATUS_CHANGED: 'Status changed',
  REQUEST_COMMENT_ADDED: 'New comment',
  APPROVAL_REQUESTED: 'Approval requested',
  APPROVAL_RESPONDED: 'Approval responded',
  SLA_AT_RISK: 'SLA at risk',
  SLA_BREACHED: 'SLA breached',
  REQUEST_RESOLVED: 'Request resolved',
  INVOICE_ISSUED: 'Invoice issued',
  INVOICE_OVERDUE: 'Invoice overdue',
};

export function notificationLabel(type: string): string {
  return NOTIFICATION_LABELS[type] ?? type;
}

function dataString(notification: AppNotification, key: string): string | null {
  const value = notification.data?.[key];

  return typeof value === 'string' && value !== '' ? value : null;
}

/**
 * Human summary for a notification row, preferring the resource title and
 * falling back to the request/invoice number.
 */
export function notificationMessage(notification: AppNotification): string {
  return (
    dataString(notification, 'title') ??
    dataString(notification, 'request_number') ??
    notificationLabel(notification.type)
  );
}

/**
 * In-app route the notification points at, based on the resource identity the
 * server attached (PRD §34 payloads carry resource_type/resource_id).
 */
export function notificationLink(notification: AppNotification): string {
  const resource = notification.resource;

  if (resource === null) {
    return '/app';
  }

  switch (resource.type) {
    case 'service_request':
      return `/app/requests/${resource.id}`;
    case 'approval':
      return `/app/approvals/${resource.id}`;
    case 'invoice':
      return `/app/invoices/${resource.id}`;
    default:
      return '/app';
  }
}

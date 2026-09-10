import { describe, expect, it } from 'vitest';

import type { AppNotification } from '../types/features';
import {
  notificationLabel,
  notificationLink,
  notificationMessage,
} from '../features/notifications/notification-utils';

function makeNotification(overrides: Partial<AppNotification> = {}): AppNotification {
  return {
    id: 'n1',
    type: 'REQUEST_ASSIGNED',
    data: null,
    read_at: null,
    read: false,
    actor: null,
    resource: null,
    created_at: '2026-09-10T00:00:00Z',
    ...overrides,
  };
}

describe('notification-utils', () => {
  it('maps known types to human labels and passes through unknown ones', () => {
    expect(notificationLabel('SLA_BREACHED')).toBe('SLA breached');
    expect(notificationLabel('SOMETHING_ELSE')).toBe('SOMETHING_ELSE');
  });

  it('prefers the resource title, then the request number', () => {
    expect(notificationMessage(makeNotification({ data: { title: 'Printer down' } }))).toBe(
      'Printer down',
    );
    expect(notificationMessage(makeNotification({ data: { request_number: 'REQ-1' } }))).toBe(
      'REQ-1',
    );
    expect(notificationMessage(makeNotification({ data: null }))).toBe('Request assigned');
  });

  it('resolves the in-app link from the attached resource', () => {
    expect(
      notificationLink(makeNotification({ resource: { type: 'service_request', id: 'r1' } })),
    ).toBe('/app/requests/r1');
    expect(notificationLink(makeNotification({ resource: { type: 'approval', id: 'a1' } }))).toBe(
      '/app/approvals/a1',
    );
    expect(notificationLink(makeNotification({ resource: { type: 'invoice', id: 'i1' } }))).toBe(
      '/app/invoices/i1',
    );
    expect(notificationLink(makeNotification({ resource: null }))).toBe('/app');
  });
});

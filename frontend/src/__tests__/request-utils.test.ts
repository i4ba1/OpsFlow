import { describe, expect, it } from 'vitest';

import { STATUS_LABEL, isTerminal } from '../features/requests/request-utils';

describe('request-utils', () => {
  it('provides a human label for every service request status', () => {
    expect(Object.keys(STATUS_LABEL)).toHaveLength(9);
    expect(STATUS_LABEL.NEW).toBe('New');
    expect(STATUS_LABEL.WAITING_CUSTOMER).toBe('Waiting on customer');
  });

  it('treats only resolved, closed and cancelled as terminal', () => {
    expect(isTerminal('RESOLVED')).toBe(true);
    expect(isTerminal('CLOSED')).toBe(true);
    expect(isTerminal('CANCELLED')).toBe(true);

    expect(isTerminal('NEW')).toBe(false);
    expect(isTerminal('IN_PROGRESS')).toBe(false);
    expect(isTerminal('WAITING_INTERNAL')).toBe(false);
  });
});

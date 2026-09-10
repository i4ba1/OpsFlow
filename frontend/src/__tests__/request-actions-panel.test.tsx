import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ThemeProvider, createTheme } from '@mui/material';
import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

vi.mock('../features/requests/request-api', () => ({
  assignRequest: vi.fn(),
  changeRequestPriority: vi.fn(),
  transitionRequest: vi.fn(),
}));

vi.mock('../features/users/user-api', () => ({
  fetchDirectoryUsers: vi.fn().mockResolvedValue([]),
}));

vi.mock('../permissions/Can', () => ({
  usePermission: () => ({ hasPermission: () => true }),
}));

import type { ServiceRequest } from '../types/features';
import { RequestActionsPanel } from '../features/requests/RequestActionsPanel';

const request: ServiceRequest = {
  id: 'req-1',
  request_number: 'REQ-2026-000001',
  title: 'Test request',
  description: 'A description',
  status: 'NEW',
  priority: 'HIGH',
  customer: null,
  project: null,
  assignee: null,
  category: null,
  requested_by: { id: 'u1', name: 'Ada' },
  created_at: '2026-09-10T00:00:00Z',
  updated_at: '2026-09-10T00:00:00Z',
  version: 1,
  first_response_due_at: null,
  resolution_due_at: null,
  resolved_at: null,
  closed_at: null,
  available_transitions: [
    { status: 'TRIAGED', permission: 'request.transition' },
    { status: 'CANCELLED', permission: 'request.close' },
  ],
};

function renderPanel() {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false }, mutations: { retry: false } },
  });

  return render(
    <QueryClientProvider client={queryClient}>
      <ThemeProvider theme={createTheme()}>
        <RequestActionsPanel request={request} />
      </ThemeProvider>
    </QueryClientProvider>,
  );
}

describe('RequestActionsPanel', () => {
  it('renders a button for each transition the API exposes', () => {
    renderPanel();

    expect(screen.getByRole('button', { name: 'Mark Triaged' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Mark Cancelled' })).toBeInTheDocument();
  });

  it('renders assignment and priority controls for a mutable request', () => {
    renderPanel();

    expect(screen.getByText('Manage')).toBeInTheDocument();
    expect(screen.getByLabelText('Assignee')).toBeInTheDocument();
    expect(screen.getByLabelText('Priority')).toBeInTheDocument();
  });
});

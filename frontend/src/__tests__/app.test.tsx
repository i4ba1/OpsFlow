import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ThemeProvider, createTheme } from '@mui/material';
import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { describe, expect, it } from 'vitest';

import { normalizeApiError } from '../api/errors';
import { HomePage } from '../features/dashboard/HomePage';

function renderHome() {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: { retry: false, gcTime: 0 },
    },
  });

  return render(
    <QueryClientProvider client={queryClient}>
      <ThemeProvider theme={createTheme()}>
        <MemoryRouter>
          <HomePage />
        </MemoryRouter>
      </ThemeProvider>
    </QueryClientProvider>,
  );
}

describe('HomePage', () => {
  it('renders the OpsFlow brand', () => {
    renderHome();

    expect(screen.getByRole('heading', { name: 'OpsFlow' })).toBeInTheDocument();
  });
});

describe('normalizeApiError', () => {
  it('falls back to a generic envelope for unknown errors', () => {
    const normalized = normalizeApiError(new Error('boom'));

    expect(normalized.code).toBe('INTERNAL_ERROR');
    expect(normalized.message).toBeTruthy();
  });
});

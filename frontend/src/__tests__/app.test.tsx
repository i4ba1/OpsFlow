import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { normalizeApiError } from '../api/errors';
import { AppProviders } from '../app/providers/AppProviders';
import { HomePage } from '../features/dashboard/HomePage';

describe('HomePage', () => {
  it('renders the OpsFlow brand', () => {
    render(
      <AppProviders>
        <HomePage />
      </AppProviders>,
    );

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

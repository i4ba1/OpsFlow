import { defineConfig } from '@playwright/test';

/**
 * OpsFlow end-to-end tests (PRD §54).
 *
 * Targets the running app served through nginx at http://localhost:8080, where the
 * SPA, /api/v1 and Reverb are all same-origin. Start the stack first:
 *
 *   make up && make seed
 *
 * then run the suite from the frontend workspace:
 *
 *   npm run e2e
 */
export default defineConfig({
  testDir: './e2e',
  // The suite drives a single seeded database and logs in repeatedly; the login
  // endpoint is throttled per IP (5/min), so run serially to stay under it and
  // to avoid races on the shared demo data.
  fullyParallel: false,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: 1,
  reporter: process.env.CI ? [['line'], ['html', { open: 'never' }]] : 'list',
  use: {
    baseURL: process.env.PLAYWRIGHT_BASE_URL ?? 'http://localhost:8080',
    trace: 'on-first-retry',
  },
  webServer: process.env.CI
    ? {
        // In CI the full Docker stack is expected to be running already; only wait
        // for it to be reachable.
        command: 'true',
        url: 'http://localhost:8080/up',
        reuseExistingServer: true,
        timeout: 120_000,
      }
    : undefined,
});

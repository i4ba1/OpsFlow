import { mkdirSync } from 'node:fs';
import path from 'node:path';

import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';

/**
 * OpsFlow end-to-end suite (PRD §54).
 *
 * These drive the REAL running stack: browser -> React -> /api/v1 -> Laravel ->
 * PostgreSQL. Start and seed the stack first (make up && make seed); the tests
 * target the Phase 10 DemoSeeder dataset (password `password` for everyone).
 *
 * E2E tests mutate the demo data, so reseed (`make fresh && make setup`) for a
 * deterministic run.
 *
 * A full-page screenshot of the final state of every test is written to
 * `e2e/screenshots/` and embedded in the repository README.
 */

const SCREENSHOT_DIR = path.join('e2e', 'screenshots');

// Stable, readable file names for the README screenshots.
const SCREENSHOT_FILES: Record<string, string> = {
  'redirects unauthenticated visitors to the login screen': '01-login',
  'E2E-001 manager request lifecycle': '02-e2e-001-manager-lifecycle',
  'E2E-002 customer collaboration': '03-e2e-002-customer-collaboration',
  'E2E-003 customer approves a pending approval': '04-e2e-003-approval',
  'E2E-004 cross-role authorization redirects unauthorized routes': '05-e2e-004-cross-role',
};

mkdirSync(SCREENSHOT_DIR, { recursive: true });

test.afterEach(async ({ page }, testInfo) => {
  // Make fixed/sticky chrome static so full-page captures render cleanly.
  await page.addStyleTag({
    content:
      'header.MuiAppBar-root{position:absolute !important;} .MuiDrawer-paper{position:absolute !important;}',
  });

  const file =
    SCREENSHOT_FILES[testInfo.title] ?? testInfo.title.replace(/[^a-z0-9]+/gi, '-').toLowerCase();

  await page.screenshot({ path: path.join(SCREENSHOT_DIR, `${file}.png`), fullPage: true });
});

const DEMO = {
  manager: 'manager@demo.opsflow.local',
  operator: 'operator@demo.opsflow.local',
  customer: 'customer@demo.opsflow.local',
};

async function login(page: Page, email: string): Promise<void> {
  await page.goto('/#/login');
  await page.getByLabel('Email').fill(email);
  await page.getByLabel('Password').fill('password');
  await page.getByRole('button', { name: 'Sign in' }).click();
  await expect(page).toHaveURL(/#\/app/, { timeout: 15_000 });
}

async function pickFirstOption(
  page: Page,
  label: string,
  optionName: string | RegExp,
): Promise<void> {
  await page.getByLabel(label).click();
  await page.getByRole('option', { name: optionName }).first().click();
}

async function createRequest(page: Page, title: string): Promise<void> {
  await page.getByRole('link', { name: 'Requests' }).first().click();
  await expect(page).toHaveURL(/#\/app\/requests/);

  await page.getByRole('button', { name: 'New request' }).click();

  // Internal users choose a customer; customer-portal users have it inferred.
  const customerField = page.getByLabel('Customer');
  if ((await customerField.count()) > 0) {
    await customerField.click();
    await page
      .getByRole('option', { name: /^Orchid Retail/ })
      .first()
      .click();
  }

  await page.getByLabel('Title').fill(title);
  await page.getByLabel('Description').fill('Created end to end by the Playwright suite.');
  await page.getByRole('button', { name: 'Create request' }).click();
  await expect(page.getByRole('button', { name: 'Create request' })).toHaveCount(0);
}

test.describe('opsflow end-to-end (PRD §54)', () => {
  test('redirects unauthenticated visitors to the login screen', async ({ page }) => {
    await page.goto('/#/app');
    await expect(page).toHaveURL(/#\/login/);
  });

  test('E2E-001 manager request lifecycle', async ({ page }) => {
    await login(page, DEMO.manager);

    const title = `E2E lifecycle ${Date.now()}`;
    await createRequest(page, title);

    // The newest request sorts first; open its detail page.
    await page
      .getByRole('cell', { name: new RegExp(title) })
      .first()
      .click();
    await expect(page).toHaveURL(/#\/app\/requests\/[0-9a-f-]+/);

    // Assign the operator.
    await pickFirstOption(page, 'Assignee', 'Wei Ming Tan');
    await Promise.all([
      page.waitForResponse((response) => response.url().endsWith('/assign') && response.ok()),
      page.getByRole('button', { name: 'Assign' }).click(),
    ]);
    await expect(page.getByText('Wei Ming Tan').first()).toBeVisible();

    // Change priority.
    await pickFirstOption(page, 'Priority', 'CRITICAL');
    await Promise.all([
      page.waitForResponse((response) => response.url().endsWith('/priority') && response.ok()),
      page.getByRole('button', { name: 'Update priority' }).click(),
    ]);

    // Walk the state machine NEW -> TRIAGED.
    await Promise.all([
      page.waitForResponse((response) => response.url().endsWith('/transition') && response.ok()),
      page.getByRole('button', { name: 'Mark Triaged' }).click(),
    ]);

    // The timeline records the transition.
    await expect(page.getByText(/→ TRIAGED/).first()).toBeVisible();
  });

  test('E2E-002 customer collaboration', async ({ page }) => {
    await login(page, DEMO.customer);

    const title = `E2E customer ${Date.now()}`;
    await createRequest(page, title);
    await page
      .getByRole('cell', { name: new RegExp(title) })
      .first()
      .click();
    await expect(page).toHaveURL(/#\/app\/requests\/[0-9a-f-]+/);

    // Comment.
    await page.getByLabel('Add a comment').fill('Customer follow-up from Playwright.');
    await page.getByRole('button', { name: 'Post comment' }).click();
    await expect(page.getByText('Customer follow-up from Playwright.')).toBeVisible();

    // Attachment.
    await page.locator('input[type="file"]').setInputFiles({
      name: 'diagnostic.txt',
      mimeType: 'text/plain',
      buffer: Buffer.from('diagnostic report'),
    });
    await expect(page.getByText('diagnostic.txt')).toBeVisible();
  });

  test('E2E-003 customer approves a pending approval', async ({ page }) => {
    await login(page, DEMO.customer);

    await page.getByRole('link', { name: 'Approvals' }).first().click();
    await expect(page).toHaveURL(/#\/app\/approvals/);

    await page.locator('tr', { hasText: 'PENDING' }).first().click();
    await expect(page).toHaveURL(/#\/app\/approvals\/[0-9a-f-]+/);

    await page.getByRole('button', { name: 'Approve' }).click();
    await expect(page.getByText('APPROVED').first()).toBeVisible();
  });

  test('E2E-004 cross-role authorization redirects unauthorized routes', async ({ page }) => {
    await login(page, DEMO.operator);

    // Operators hold request.view but not invoice.view.
    await page.goto('/#/app/invoices');
    await expect(page).toHaveURL(/#\/app$/);
    await expect(page.getByText('Operations dashboard')).toBeVisible();
  });
});

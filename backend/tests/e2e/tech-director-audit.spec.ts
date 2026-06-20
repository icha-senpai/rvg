import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { test, expect, type Locator, type Page } from '@playwright/test';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const screenshotDir = path.resolve(__dirname, '../../test-results/tech-director-audit');

fs.mkdirSync(screenshotDir, { recursive: true });

test.describe.configure({ mode: 'serial' });

async function capture(page: Page, name: string): Promise<void> {
  await page.screenshot({
    path: path.join(screenshotDir, name),
    fullPage: true,
  });
}

async function openDateTimePicker(page: Page, label: string): Promise<Locator> {
  const picker = page.getByText(label, { exact: true }).locator('xpath=..');

  await picker.getByRole('button').first().click();
  return picker;
}

async function pickDateTime(page: Page, label: string, daysAhead: number, hourValue: string, minuteValue: string): Promise<void> {
  const picker = await openDateTimePicker(page, label);
  const now = new Date();
  const target = new Date(now);
  target.setDate(now.getDate() + daysAhead);

  let monthOffset = (target.getFullYear() - now.getFullYear()) * 12 + (target.getMonth() - now.getMonth());

  while (monthOffset > 0) {
    await picker.getByRole('button', { name: '›' }).click();
    monthOffset -= 1;
  }

  while (monthOffset < 0) {
    await picker.getByRole('button', { name: '‹' }).click();
    monthOffset += 1;
  }

  await picker.getByRole('button', { name: String(target.getDate()), exact: true }).click();

  const hourSection = picker.getByText('Hour', { exact: true }).locator('xpath=..');
  await hourSection.getByRole('button').click();
  await page.locator('.hz-popover-surface.fixed button').filter({
    hasText: new RegExp(`^${hourValue}$`),
  }).first().click();

  const minuteSection = picker.getByText('Minute', { exact: true }).locator('xpath=..');
  await minuteSection.getByRole('button').click();
  await page.locator('.hz-popover-surface.fixed button').filter({
    hasText: new RegExp(`^${minuteValue}$`),
  }).first().click();

  await page.getByRole('heading', { name: 'Create Operation' }).click();
}

test('tech director visual audit across ledger, operations, run tool, and admin user editing', async ({ page }) => {
  test.setTimeout(120000);

  const uniqueSuffix = Date.now();
  const operationTitle = `Tech Director Audit ${uniqueSuffix}`;
  const updatedBio = `Tech director audit bio ${uniqueSuffix}`;

  await page.setViewportSize({ width: 1440, height: 1200 });

  // Ensure the member persona exists so the walk-in and admin-edit steps have a stable target.
  await page.goto('/dev/auth/login/member');
  await expect(page).not.toHaveURL(/\/verify$/);

  await page.goto('/dev/auth/login/tech_director');
  await expect(page).not.toHaveURL(/\/verify$/);

  await page.goto('/organization/ledger');
  await expect(page.locator('body')).toContainText('Overview');
  await expect(page.locator('body')).toContainText('Transactions');
  await expect(page.locator('body')).toContainText('Inventory');
  await expect(page.locator('body')).toContainText('Ships');
  await capture(page, '01-ledger-overview.png');

  await page.getByRole('button', { name: 'Inventory', exact: true }).first().click();
  await expect(page.locator('body')).toContainText('Inventory Records');
  await capture(page, '02-ledger-inventory.png');

  await page.goto('/operations/dashboard');
  await expect(page.getByRole('button', { name: 'Create Operation' })).toBeVisible();
  await capture(page, '03-operations-dashboard.png');

  await page.getByRole('button', { name: 'Create Operation' }).click();
  await expect(page.getByRole('heading', { name: 'Create Operation' })).toBeVisible();
  await capture(page, '04-operation-create-blank.png');

  await page.getByPlaceholder('Operation name...').fill(operationTitle);
  await pickDateTime(page, 'Start Time', 1, '20', '00');
  await capture(page, '05-operation-create-filled.png');

  const dialog = page.locator('aside[role="dialog"]').first();
  const dialogBody = dialog.locator('section').first();
  await dialogBody.evaluate((element) => {
    element.scrollTop = element.scrollHeight;
  });
  await expect(dialog.getByText('Finalize Operation')).toBeVisible();

  const publishButton = dialog.locator('button').filter({ hasText: 'Publish' }).last();
  await publishButton.scrollIntoViewIfNeeded();
  await publishButton.click();
  await expect(page).toHaveURL(/operation=\d+/);
  await expect(page.locator('body')).toContainText(operationTitle);
  await capture(page, '06-operation-published.png');

  const operationId = new URL(page.url()).searchParams.get('operation');
  expect(operationId).toBeTruthy();

  await page.goto(`/operations/${operationId}/run`);
  await expect(page).toHaveURL(/\/operations\/\d+\/run$/);
  await expect(page.locator('body')).toContainText('Operation Run Tool');
  const rosterSnapshot = page.locator('section').filter({
    has: page.getByText('Roster Snapshot', { exact: true }),
  }).first();
  await capture(page, '07-run-tool-before-start.png');

  await page.getByRole('button', { name: 'Start Operation' }).first().click();
  await page.getByRole('button', { name: 'Start Operation' }).last().click();
  await expect(page.getByText(new RegExp(`You are about to mark "${operationTitle}" as In Progress\\.`))).toBeHidden();
  await expect(page.locator('body')).toContainText(/in progress/i);
  await capture(page, '08-run-tool-started.png');

  await page.getByRole('button', { name: 'Select a verified member' }).click();
  await page.getByPlaceholder('Search verified members...').fill('DevMember');
  await page.locator('li').filter({ hasText: 'DevMember' }).first().click();
  await page.getByRole('button', { name: 'Add Walk-In' }).click();
  await expect(page.getByRole('button', { name: 'Add Walk-In' })).toHaveText('Add Walk-In');
  await expect(rosterSnapshot).toContainText('DevMember');
  await capture(page, '09-run-tool-walk-in.png');

  await page.goto('/admin/dashboard?tab=users');
  await expect(page.locator('body')).toContainText('User Administration');
  await capture(page, '10-admin-users-panel.png');

  await page.getByPlaceholder('Search by name, RSI handle, Discord name, rank, role, or ID...').fill('DevMember');
  await page.getByRole('button', { name: 'Search' }).click();
  await expect(page.locator('body')).toContainText('DevMember');
  await page.getByRole('button', { name: 'Edit' }).first().click();
  await expect(page.locator('body')).toContainText('Admin User Editor');
  await capture(page, '11-admin-user-editor.png');

  await page.getByPlaceholder('Member biography...').fill(updatedBio);
  await page.getByRole('button', { name: 'Save Changes' }).click();
  await expect(page.getByPlaceholder('Member biography...')).toHaveValue(updatedBio);
  await capture(page, '12-admin-user-saved.png');
});

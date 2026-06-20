import { test, expect } from '@playwright/test';
import { loginAsPersona } from './support/devAuth';

test.describe('verify page states', () => {
  test('guest sees discord step', async ({ page }) => {
    await page.goto('/verify');

    await expect(page.getByText('Step 1 · Verify with Discord')).toBeVisible();
  });

  test('verify preview persona sees the RSI step', async ({ page }) => {
    await loginAsPersona(page, 'verify_preview');

    await expect(page.getByText('Step 2 · RSI Verification')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Generate Code' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Verify RSI' })).toBeVisible();
  });

  test('not in guild error renders on verify page', async ({ page }) => {
    await page.goto('/verify?error=not_in_guild');

    await expect(page.getByText('Access denied. You must be in the org Discord before you can continue.')).toBeVisible();
  });

  test('discord availability error renders on verify page', async ({ page }) => {
    await page.goto('/verify?error=discord_check_unavailable');

    await expect(page.getByText('Discord verification is temporarily unavailable')).toBeVisible();
  });

  test('oauth error renders on verify page', async ({ page }) => {
    await page.goto('/verify?error=oauth');

    await expect(page.getByText('Discord login failed. Please try again.')).toBeVisible();
  });
});

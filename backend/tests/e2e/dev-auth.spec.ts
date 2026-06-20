import { test, expect } from '@playwright/test';
import { allPersonas, loginAsPersona, logoutDevPersona } from './support/devAuth';

test.describe('dev auth personas', () => {
  for (const persona of allPersonas) {
    test(`can enter the app as ${persona}`, async ({ page }) => {
      await loginAsPersona(page, persona);

      if (persona === 'verify_preview') {
        await expect(page.getByText('Step 2 · RSI Verification')).toBeVisible();
        return;
      }

      await expect(page).toHaveURL(/^(?!.*\/verify$).*/);
      await expect(page.locator('body')).toContainText(/Horizon|Member|Operations|Ledger|Settings/i);
    });
  }

  test('dev auth index lists every persona', async ({ page }) => {
    await page.goto('/dev/auth');

    for (const persona of allPersonas) {
      await expect(page.locator('body')).toContainText(persona);
    }
  });

  test('verified personas can escape the verify preview account', async ({ page }) => {
    await loginAsPersona(page, 'verify_preview');
    await loginAsPersona(page, 'member');

    await expect(page).not.toHaveURL(/\/verify$/);
  });

  test.afterEach(async ({ page }) => {
    await logoutDevPersona(page);
  });
});

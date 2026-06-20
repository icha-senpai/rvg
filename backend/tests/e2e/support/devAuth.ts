import { expect, type Page } from '@playwright/test';

export const verifiedPersonas = [
  'member',
  'penetrators_lt',
  'cit',
  'commander',
  'wing_commander',
  'admiral',
  'grand_admiral',
  'director',
  'tech_director',
] as const;

export const allPersonas = [
  ...verifiedPersonas,
  'verify_preview',
] as const;

export type DevPersona = (typeof allPersonas)[number];

export async function logoutDevPersona(page: Page): Promise<void> {
  await page.goto('/dev/auth/logout');
}

export async function loginAsPersona(page: Page, persona: DevPersona): Promise<void> {
  await page.goto(`/dev/auth/login/${persona}`);

  if (persona === 'verify_preview') {
    await expect(page).toHaveURL(/\/verify$/);
    return;
  }

  await expect(page).not.toHaveURL(/\/verify$/);
}

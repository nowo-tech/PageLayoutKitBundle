import { expect, type Page } from '@playwright/test';

/** Sign in with the in-memory demo account (admin / admin). */
export async function loginAsAdmin(page: Page): Promise<void> {
  await page.goto('/login');
  await page.fill('input[name="_username"]', 'admin');
  await page.fill('input[name="_password"]', 'admin');
  await page.click('button[type="submit"]');
  await expect(page).not.toHaveURL(/\/login$/);
}

import { test, expect } from '@playwright/test';
import { loginAsAdmin } from './helpers';

/** REQ-DEMO-013 — PageLayoutKit demo happy path (public blocks → login → admin reorder → block editor). */
test.describe('PageLayoutKit demo', () => {
  test('public home renders the seeded page blocks', async ({ page }) => {
    const response = await page.goto('/');
    expect(response?.ok()).toBeTruthy();
    await expect(page.locator('main article.card').first()).toBeVisible();
    await expect(page.locator('main article h2', { hasText: /hero/i }).first()).toBeVisible();
  });

  test('admin layout requires login', async ({ page }) => {
    await page.goto('/admin/pages/home/layout');
    await expect(page).toHaveURL(/\/login$/);
    await expect(page.locator('input[name="_username"]')).toBeVisible();
  });

  test('admin can reorder blocks and open a block editor', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto('/admin/pages/home/layout');
    await expect(page.getByRole('heading', { name: 'Layout: home' })).toBeVisible();
    const rows = page.locator('table tbody tr');
    await expect(rows.first()).toBeVisible();
    expect(await rows.count()).toBeGreaterThan(1);

    // Saving the unchanged order round-trips the reorder form back to the layout screen.
    await page.getByRole('button', { name: 'Save order' }).click();
    await expect(page.getByRole('heading', { name: 'Layout: home' })).toBeVisible();
    await expect(rows.first()).toBeVisible();

    await rows.first().getByRole('link', { name: 'Edit' }).click();
    await expect(page.getByRole('heading', { name: /Edit block/i })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Back to layout' })).toBeVisible();
  });
});

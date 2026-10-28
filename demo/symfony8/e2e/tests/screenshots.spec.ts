import { test, expect } from '@playwright/test';
import { mkdirSync } from 'node:fs';
import { resolve } from 'node:path';
import { loginAsAdmin, waitForWebProfiler } from './helpers';

/** REQ-DEMO-013 — README screenshots (captured as PNG, converted to WebP by scripts/to-webp.mjs). */
const outDir = process.env.SCREENSHOT_DIR
  ? resolve(process.env.SCREENSHOT_DIR)
  : resolve(__dirname, '../../../../docs/images/demo');

test.beforeAll(() => {
  mkdirSync(outDir, { recursive: true });
});

test.describe('PageLayoutKit screenshots', () => {
  test.use({ viewport: { width: 1280, height: 1000 } });

  test('page-blocks — public page composed from ordered blocks', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto('/');
    await expect(page.locator('main article.card').first()).toBeVisible();
    await expect(page.getByRole('link', { name: 'Edit block' }).first()).toBeVisible();
    await waitForWebProfiler(page);
    await page.screenshot({ path: resolve(outDir, 'page-blocks.png') });
  });

  test('admin-layout — reorder the blocks of a page', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto('/admin/pages/home/layout');
    await expect(page.locator('table tbody tr').first()).toBeVisible();
    await waitForWebProfiler(page);
    await page.screenshot({ path: resolve(outDir, 'admin-layout.png') });
  });
});

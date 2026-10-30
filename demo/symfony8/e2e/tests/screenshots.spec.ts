import { test, expect, type Page } from '@playwright/test';
import { mkdirSync } from 'node:fs';
import { resolve } from 'node:path';

/**
 * REQ-DEMO-013 — demo frame: navbar + page content + Symfony WebProfiler toolbar (kept visible on purpose).
 */
const outDir = process.env.SCREENSHOT_DIR
  ? resolve(process.env.SCREENSHOT_DIR)
  : resolve(__dirname, '../../../../docs/images/demo');

async function waitForToolbar(page: Page) {
  await page
    .locator('.sf-toolbar .sf-toolbar-block')
    .first()
    .waitFor({ state: 'visible', timeout: 15000 })
    .catch(() => {});
  await page
    .waitForFunction(() => !(document.querySelector('.sf-toolbar')?.textContent ?? '').includes('Loading'), null, { timeout: 15000 })
    .catch(() => {});
}

async function shoot(page: Page, path: string, name: string, height: number) {
  await page.setViewportSize({ width: 1280, height });
  await page.goto(path);
  await expect(page.locator('nav.navbar .navbar-brand')).toBeVisible();
  await waitForToolbar(page);
  await page.screenshot({ path: resolve(outDir, `${name}.png`) });
}

test.beforeAll(() => {
  mkdirSync(outDir, { recursive: true });
});

test.describe('WordTemplate demo screenshots', () => {
  test('home — template form', async ({ page }) => {
    await shoot(page, '/', 'home', 900);
  });

  test('conditionals — form, named decisions and expression decisions', async ({ page }) => {
    await shoot(page, '/conditionals', 'conditionals', 2050);
    await expect(page.locator('[data-testid="expressions"]')).toBeVisible();
  });

  test('expression examples — Word text, PHP context, result', async ({ page }) => {
    await shoot(page, '/conditionals/examples', 'expression-examples', 1650);
    await expect(page.locator('[data-testid="example-result"]').first()).toBeVisible();
  });
});

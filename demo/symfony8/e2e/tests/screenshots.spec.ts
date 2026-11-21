import { test, expect, type Page } from '@playwright/test';
import { mkdirSync } from 'node:fs';
import { resolve } from 'node:path';
import { frameShot } from './frame';

/**
 * REQ-DEMO-013 — demo frame: the demo platform shell + page content, without the Symfony WebProfiler toolbar (see frame.ts).
 */
const outDir = process.env.SCREENSHOT_DIR
  ? resolve(process.env.SCREENSHOT_DIR)
  : resolve(__dirname, '../../../../docs/images/demo');

async function shoot(page: Page, path: string, name: string, height: number) {
  await page.setViewportSize({ width: 1280, height });
  await page.goto(path);
  await frameShot(page, resolve(outDir, `${name}.png`), { minHeight: 700 });
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

  test('loops — invoice rows, loop variables, nested loops', async ({ page }) => {
    await shoot(page, '/loops', 'loops', 1650);
    await expect(page.locator('[data-testid="example-result"]').first()).toBeVisible();
  });
});

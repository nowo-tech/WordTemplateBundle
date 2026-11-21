import { expect, type Page } from '@playwright/test';

/**
 * REQ-DEMO-013 framing: the demo platform shell + page, without the Symfony WebProfiler toolbar (it is not part of the
 * platform, so it is hidden). The viewport is grown to the content height so the clip is never truncated.
 */
export async function frameShot(page: Page, path: string, opts: { minHeight?: number; pad?: number } = {}): Promise<void> {
  const pad = opts.pad ?? 0;
  const width = page.viewportSize()?.width ?? 1360;

  await page.addStyleTag({ content: '.sf-toolbar,.sf-minitoolbar,[id^="sfwdt"]{display:none!important}' });
  await expect(page.locator('[data-testid="demo-platform"]')).toBeVisible();

  // Let a dialog finish its open animation before the capture.
  await page.waitForFunction(() => {
    const dialog = document.querySelector('dialog[open]');

    return !dialog || getComputedStyle(dialog).opacity === '1';
  }, undefined, { timeout: 5000 }).catch(() => undefined);
  await page.waitForTimeout(400);

  const contentBottom = await page.evaluate(() => {
    const main = document.querySelector('main');

    return Math.ceil(main ? main.getBoundingClientRect().bottom + window.scrollY : document.documentElement.scrollHeight);
  });
  const height = Math.max(opts.minHeight ?? 720, contentBottom + pad + 60);
  await page.setViewportSize({ width, height });
  await page.evaluate(() => window.scrollTo(0, 0));

  await page.screenshot({ path, clip: { x: 0, y: 0, width: await page.evaluate(() => window.innerWidth), height: await page.evaluate(() => window.innerHeight) } });
}

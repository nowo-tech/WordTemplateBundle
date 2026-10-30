import { test, expect } from '@playwright/test';

test.describe('WordTemplate demo', () => {
  test('home lists the template placeholders', async ({ page }) => {
    const response = await page.goto('/');
    expect(response?.ok()).toBeTruthy();
    await expect(page.locator('h1')).toContainText('WordTemplateBundle');
    await expect(page.locator('.alert-info')).toContainText('placeholder(s)');
  });

  test('conditionals page shows named and expression decisions', async ({ page }) => {
    await page.goto('/conditionals');
    await expect(page.locator('[data-testid="decisions"] tbody tr')).not.toHaveCount(0);
    const expressions = page.locator('[data-testid="expressions"] tbody tr');
    await expect(expressions.first()).toContainText('order.total > 100');
    await expect(expressions.first()).toContainText('kept');
  });

  test('changing the total removes the expression block', async ({ page }) => {
    await page.goto('/conditionals');
    await page.getByLabel('Total EUR (?float)').fill('50');
    await page.getByRole('button', { name: 'Preview decisions' }).click();
    await expect(page.locator('[data-testid="expressions"] tbody tr').first()).toContainText('removed');
  });

  test('download .docx returns a Word document', async ({ page }) => {
    await page.goto('/conditionals');
    const download = page.waitForEvent('download');
    await page.getByRole('button', { name: 'Download .docx' }).click();
    expect((await download).suggestedFilename()).toBe('conditionals-filled.docx');
  });

  test('expression examples show Word text, PHP context and result', async ({ page }) => {
    await page.goto('/conditionals/examples');
    const card = page.locator('[data-testid="example-expression-true"]');
    await expect(card).toContainText('${#if total > 1000}');
    await expect(card.locator('[data-testid="example-result"]')).toContainText('Orders above 1,000 EUR ship for free.');
    await expect(page.locator('[data-testid="example-expression-false"] [data-testid="example-result"]')).not.toContainText('ship for free');
    await expect(page.locator('[data-testid="example-error-message"]')).toContainText('unknown variable "totl"');
  });

  test('example documents can be downloaded', async ({ request }) => {
    const template = await request.get('/conditionals/examples/nested/template.docx');
    expect(template.ok()).toBeTruthy();
    expect(template.headers()['content-type']).toContain('wordprocessingml');
    const result = await request.get('/conditionals/examples/nested/result.docx');
    expect(result.ok()).toBeTruthy();
  });
});

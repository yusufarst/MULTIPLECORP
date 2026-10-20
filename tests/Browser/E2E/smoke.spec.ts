import { expect, test } from '@playwright/test';

// TL-09's smoke run (TASK-003, acceptance criterion 2): each engine reaches the stack over HTTPS and
// receives SECURITY WS-08's static headers from Nginx. The journeys arrive with TASK-062–TASK-064.

test('loads the stack over HTTPS with the security headers', async ({ page }) => {
    const response = await page.goto('/robots.txt');
    const headers = (await response?.allHeaders()) ?? {};

    expect(response?.status()).toBe(200);
    expect(page.url()).toMatch(/^https:/);
    expect(headers['x-frame-options']).toBe('DENY');
    expect(headers['x-content-type-options']).toBe('nosniff');
    expect(headers['referrer-policy']).toBe('same-origin');
    expect(headers['cross-origin-opener-policy']).toBe('same-origin');
});

test('is redirected from plain HTTP to HTTPS', async ({ page }) => {
    const response = await page.goto(`${process.env.E2E_HTTP_URL ?? 'http://web:8080'}/robots.txt`);

    expect(response?.status()).toBe(200);
    expect(page.url()).toMatch(/^https:\/\/[^/]+:8443\/robots\.txt$/);
});

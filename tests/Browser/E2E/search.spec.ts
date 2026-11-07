import { expect as base, type Page, test } from '@playwright/test';

import { stockRun } from '../stock-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-09 (TASK-059; UXS-33–UXS-35; IP-15; IP-17; criteria 1, 4–6): PG-009 *Cari* — a typed SKU found exactly first
// under "Kode Cocok" and opened by Enter, the term never in the address or the title; a scanner's burst into the field
// going to the exact lookup and opening the stock PG-019; an unknown scanned code saying so and creating nothing; a
// burst while focus is outside a field that accepts scans discarded with its notice, no command sent.

test.describe.configure({ timeout: 300_000 });

/** A keyboard-wedge scanner: the code's keys within the burst gap, then Enter. */
async function scan(page: Page, code: string): Promise<void> {
    await page.keyboard.type(code, { delay: 5 });
    await page.keyboard.press('Enter');
}

test('finds a SKU exactly first and opens it with Enter, the term never in the address (criteria 1, 4)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await stockRun(page, `cari-${String(Date.now())}`);

    await page.goto('/cari');
    await expect(page.getByRole('heading', { name: 'Cari', exact: true })).toBeVisible();
    const field = page.locator('#konten').getByRole('textbox', { name: 'Cari atau pindai kode' });
    await expect(field).toBeFocused();
    await field.fill(run.sku);
    const exact = page.locator('[data-slot="search-exact"]');
    await expect(exact.getByRole('heading', { name: 'Kode Cocok' })).toBeVisible();
    await expect(exact.getByRole('link')).toHaveCount(1);
    await expect(exact).toContainText(run.name);
    expect(page.url()).not.toContain(run.sku);
    expect(await page.title()).not.toContain(run.sku);

    await field.press('Enter');
    await page.waitForURL(`**/gudang/stok/${run.product}`);
    expect(page.url()).not.toContain(run.sku);
});

test('sends a scanned code to the exact lookup and opens PG-019; an unknown code creates nothing (criteria 5, 6)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await stockRun(page, `cari-pindai-${String(Date.now())}`);
    const requests: string[] = [];
    page.on('request', (request) => {
        if (request.method() === 'POST') requests.push(new URL(request.url()).pathname);
    });

    await page.goto('/cari');
    const field = page.locator('#konten').getByRole('textbox', { name: 'Cari atau pindai kode' });
    await expect(field).toBeFocused();
    const unknown = `TIDAKADA${String(Date.now())}`;
    await scan(page, unknown);
    await expect(page.locator('[data-slot="scan-unknown"]')).toContainText(`Kode ${unknown} tidak dikenal.`);
    await expect(page).toHaveURL(/\/cari$/);
    // Only the two lookups were asked: the search and the exact scan lookup, never a command (GAP-015).
    expect(requests.every((path) => path === '/cari' || path === '/gudang/pindai')).toBe(true);
    expect(requests).toContain('/gudang/pindai');

    await field.fill('');
    await field.focus();
    await scan(page, run.sku);
    await page.waitForURL(`**/gudang/stok/${run.product}`);
});

test('discards a burst while focus is outside a field that accepts scans (IP-15; DESIGN_SYSTEM §13.2)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    await stockRun(page, `cari-luar-${String(Date.now())}`);

    await page.goto('/cari');
    await page.locator('#konten').getByRole('link', { name: 'Kembali' }).focus();
    await scan(page, 'KODELUAR1234');
    await expect(page.getByRole('alert').filter({ hasText: 'Kode terpindai diabaikan.' })).toBeVisible();
    await expect(page).toHaveURL(/\/cari$/);
});

test('shows the full-screen search on the phone, Kembali returning where search was opened (NAV-009; D-IA-04)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await stockRun(page, `cari-ponsel-${String(Date.now())}`);
    await page.setViewportSize({ width: 390, height: 844 });

    await page.goto('/gudang/stok');
    await page.getByRole('navigation', { name: 'Navigasi utama' }).last().getByRole('button', { name: 'Cari' }).click();
    await page.waitForURL('**/cari');
    const field = page.locator('#konten').getByRole('textbox', { name: 'Cari atau pindai kode' });
    await field.fill(run.sku);
    await expect(page.locator('[data-slot="search-exact"]')).toContainText(run.name);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    await field.press('Escape');
    await page.waitForURL('**/gudang/stok');
});

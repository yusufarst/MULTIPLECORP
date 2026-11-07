import { expect as base, type Page, test } from '@playwright/test';

import { stockRun } from '../stock-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-09 (TASK-059; UXS-33–UXS-35; IP-15; IP-17; criteria 1, 4–6): PG-009 *Cari* — a typed SKU found exactly first
// under "Kode Cocok" and opened by Enter, the term never in the address or the title; a scanner's burst into the field
// deciding on the exact lookups and opening the stock PG-019; an unknown scanned code saying so and creating nothing;
// a burst while focus is outside a field that accepts scans discarded with its notice, its Enter withheld.

test.describe.configure({ timeout: 300_000 });

/**
 * A keyboard-wedge scanner into the focused field. It types faster than the runner's key events travel — a loaded CI
 * runner can space them past the burst gap —, so the burst is played inside the page as a scanner delivers it, each
 * character's keydown and insertion and then the Enter, as components.spec.ts does. Answers whether Enter was withheld.
 */
async function scan(page: Page, code: string): Promise<boolean> {
    return page.evaluate((typed) => {
        const target = document.activeElement ?? document.body;
        const value = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value');
        let withheld = false;

        for (const key of [...Array.from(typed), 'Enter']) {
            const event = new KeyboardEvent('keydown', { key, bubbles: true, cancelable: true });

            if (!target.dispatchEvent(event)) {
                withheld = key === 'Enter' || withheld;
            } else if (key !== 'Enter' && target instanceof HTMLInputElement) {
                value?.set?.call(target, target.value + key);
                target.dispatchEvent(new Event('input', { bubbles: true }));
            }
        }

        return withheld;
    }, code);
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

test('decides a scan on the exact lookups and opens PG-019; an unknown code creates nothing (criteria 1, 5, 6)', async ({ page }) => {
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
    // Enter arrives before the search's answer: the page waits for it rather than guessing (PF-19).
    expect(await scan(page, unknown)).toBe(true);
    await expect(page.locator('[data-slot="scan-unknown"]')).toContainText(`Kode ${unknown} tidak dikenal.`);
    await expect(page).toHaveURL(/\/cari$/);
    // Only the two lookups were asked: the search and the exact scan lookup, never a command (GAP-015).
    expect(requests.every((path) => path === '/cari' || path === '/gudang/pindai')).toBe(true);
    expect(requests).toContain('/gudang/pindai');

    await field.fill('');
    await field.focus();
    expect(await scan(page, run.sku)).toBe(true);
    await page.waitForURL(`**/gudang/stok/${run.product}`);
});

test('discards a burst while focus is outside a field that accepts scans, its Enter withheld (IP-15; DESIGN_SYSTEM §13.2)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    await stockRun(page, `cari-luar-${String(Date.now())}`);

    await page.goto('/cari');
    await page.locator('#konten').getByRole('link', { name: 'Kembali' }).focus();
    // The guard withholds the burst's Enter, so a focused link or button is never pressed by a scan.
    expect(await scan(page, 'KODELUAR1234')).toBe(true);
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
    // The return target is the page search was opened from from the first render, before anything is typed (NV-01).
    await expect(page.locator('header').getByRole('link', { name: 'Kembali' })).toHaveAttribute('href', /\/gudang\/stok$/);
    const field = page.locator('#konten').getByRole('textbox', { name: 'Cari atau pindai kode' });
    await field.fill(run.sku);
    await expect(page.locator('[data-slot="search-exact"]')).toContainText(run.name);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    await field.press('Escape');
    await page.waitForURL('**/gudang/stok');
});

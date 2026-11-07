import { readFile } from 'node:fs/promises';

import { expect as base, type Page, test } from '@playwright/test';

import { reportRun, runExports } from '../report-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-09 (TASK-060; CJ-14's reports; UXS-36; IP-12; acceptance criteria 3, 4, 6, 7, 9, 12): PG-051's sales report with its
// company and period filters as address keys — one section per company, its own total —; "Ekspor" requesting the report
// as a job and landing on PG-053 with the request *Diminta*; a third request while two are owed refused in place with
// MSG-22; the job's file *Siap Diunduh* and downloaded as an ordinary browser download; the print view's line of filters.

test.describe.configure({ timeout: 300_000 });

async function exportExcel(page: Page): Promise<void> {
    await page.getByRole('button', { name: 'Ekspor' }).click();
    await page.getByRole('menuitem', { name: 'Ekspor Excel' }).click();
}

test('filters the sales report by company and period in the address, one section per company with its total (criteria 3, 4, 12)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await reportRun(page, `laporan-${String(Date.now())}`);

    await page.goto(`/laporan?laporan=penjualan&perusahaan=${run.code}`);
    await expect(page.getByRole('heading', { name: 'Laporan', exact: true })).toBeVisible();
    const table = page.locator('[data-table="penjualan"]');
    await expect(table.getByRole('heading', { name: 'Invoice terbit' })).toBeVisible();
    const section = table.locator(`[data-slot="report-section"][data-company="${run.code}"]`);
    await expect(section.locator('[data-slot="section-total"]')).toContainText('Rp1.000.000');
    await expect(table.locator('[data-slot="report-section"]')).toHaveCount(1);

    await page.getByLabel('Periode').selectOption('bulan-lalu');
    await page.waitForURL(/periode=bulan-lalu/);
    expect(new URL(page.url()).searchParams.get('perusahaan')).toBe(run.code);
    await expect(page.locator('[data-table="penjualan"]')).toContainText('Tidak ada data pada periode dan perusahaan ini.');

    // The print view is this page: its filters as a line of text (DESIGN_SYSTEM §17; DP-19).
    await page.emulateMedia({ media: 'print' });
    await expect(page.locator('[data-slot="print-header"]')).toContainText(`Perusahaan: ${run.code}`);
    await page.emulateMedia({ media: 'screen' });
});

test('requests an export to PG-053, refuses a third while two are owed, then downloads the ready file (criteria 6, 7, 9; UXS-36; IP-12)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await reportRun(page, `ekspor-${String(Date.now())}`);
    const report = `/laporan?laporan=penjualan&perusahaan=${run.code}`;

    await page.goto(report);
    await expect(page.locator('[data-table="penjualan"]')).toContainText('Rp1.000.000');
    await exportExcel(page);
    await page.waitForURL('**/laporan/ekspor');
    await expect(page.getByRole('heading', { name: 'Ekspor Saya' })).toBeVisible();
    const rows = page.locator('[data-slot="export-row"]');
    await expect(rows).toHaveCount(1);
    await expect(rows.first()).toContainText('Laporan Penjualan · Excel');
    await expect(rows.first()).toContainText('Diminta');

    await page.goto(report);
    await exportExcel(page);
    await page.waitForURL('**/laporan/ekspor');
    await page.goto(report);
    await exportExcel(page);
    await expect(page.getByText('Dua ekspor Anda masih diproses. Tunggu salah satunya selesai.')).toBeVisible();
    expect(new URL(page.url()).pathname).toBe('/laporan');

    expect(await runExports(page)).toBe(2);
    await page.goto('/laporan/ekspor');
    await expect(rows).toHaveCount(2);
    await expect(rows.first()).toContainText('Siap Diunduh');
    const download = page.waitForEvent('download');
    await rows.first().getByRole('link', { name: 'Unduh' }).click();
    const file = await download;

    expect(file.suggestedFilename()).toMatch(/^laporan-penjualan-.*\.xlsx$/);
    expect((await readFile(await file.path())).subarray(0, 2).toString()).toBe('PK');
});

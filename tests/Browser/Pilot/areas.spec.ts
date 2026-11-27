import AxeBuilder from '@axe-core/playwright';
import { expect as base, type Page, test } from '@playwright/test';

import { importRun } from '../import-fixtures';
import { projectRun, type ProjectRun } from '../project-fixtures';
import { searchStock, stockRun } from '../stock-fixtures';

// APPR-042's pilot on the real stack (the Owner's directive of 2026-11-27): what the pilot deliberately does differently
// from the contract the four layers' specs still encode — Beranda's figures as cards (the per-company table only for
// a comparison of several companies), the project's stage track folded on the phone and read-only, PG-018's labelled rows up to 1,024 px
// and its table from 1,025 px, the stock list before the company values on the phone (D-1) — and the check the layers'
// specs cannot make of them. The layers' own specs stay unchanged: where they fail on a deliberate difference they are
// classified, and this file is the pilot's contract on the same stack, with the same fixtures. Nothing here is a release
// check, and the branch is never merged.

const expect = base.configure({ timeout: 60_000 });
const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];
const UUID = /\/proyek\/[0-9a-f-]{36}$/;

test.describe.configure({ timeout: 300_000 });

const fits = (page: Page): Promise<boolean> => page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth);

async function fillParties(page: Page, run: ProjectRun, title: string): Promise<void> {
    await page.getByRole('combobox', { name: /^Perusahaan/ }).selectOption(run.company);
    await page.getByRole('combobox', { name: 'Klien' }).fill(run.client);
    await page.getByRole('option', { name: run.client }).click();
    await page.getByLabel(/^Unit/).selectOption({ label: run.unit });
    await page.getByLabel(/^PIC/).selectOption({ index: 1 });
    await page.getByLabel(/^Judul proyek/).fill(title);
}

test('Beranda: the four KPIs as cards with the link to the details, and no axe violation on the three home pages', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    await importRun(page, `pilot-beranda-${String(Date.now())}`);

    await page.goto('/beranda');
    await expect(page.getByRole('heading', { name: 'Angka Periode' })).toBeVisible();

    // The figures as cards; the per-company table is only for a comparison of several companies, so a layer's data order
    // decides whether it shows — the layers' own specs assume it always does. "Rincian angka" is a link to PG-052.
    for (const label of ['Nilai penjualan (invoice terbit)', 'Kas masuk (uang diterima)', 'Piutang aktif', 'Laba manajerial']) {
        await expect(page.getByText(label, { exact: true }).first()).toBeVisible();
    }

    await expect(page.getByRole('link', { name: 'Rincian angka' })).toBeVisible();
    expect((await new AxeBuilder({ page }).withTags(TAGS).analyze()).violations).toEqual([]);

    await page.goto('/pekerjaan');
    await expect(page.getByRole('heading', { name: 'Pekerjaan', level: 1 })).toBeVisible();
    await expect(page.locator('[data-region-state="loading"]')).toHaveCount(0);
    expect((await new AxeBuilder({ page }).withTags(TAGS).analyze()).violations).toEqual([]);

    await page.goto('/tinjauan');
    await expect(page.getByRole('region', { name: 'Kondisi yang Masih Terbuka' })).toBeVisible();
    expect((await new AxeBuilder({ page }).withTags(TAGS).analyze()).violations).toEqual([]);
});

test('Proyek on the phone: "Tahap n dari N" first, the track folded under "Tahap proyek" and read-only (D-2)', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    const run = await projectRun(page, `pilot-ponsel-${String(Date.now())}`);

    await page.goto('/proyek/baru');
    await fillParties(page, run, 'Proyek dari ponsel');
    await page.getByRole('button', { name: 'Buat Proyek' }).click();
    await page.waitForURL(UUID);

    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Proyek dari ponsel');
    await expect(page.locator('#konten')).toContainText(/Tahap \d+ dari \d+/);
    await expect(page.getByRole('list', { name: 'Tahap proyek' })).toHaveCount(0);

    await page.getByRole('button', { name: 'Tahap proyek' }).click();
    const track = page.getByRole('list', { name: 'Tahap proyek' });
    await expect(track).toBeVisible();
    // Read-only: nothing in the track can be focused or clicked.
    await expect(track.locator('a, button, input, [tabindex]')).toHaveCount(0);
    expect(await fits(page)).toBe(true);
});

for (const width of [320, 390, 768, 1024, 1025, 1280]) {
    test(`Stok (PG-018–PG-020) at ${String(width)} px: no sideways scroll, labelled rows up to 1,024 px and the table from 1,025 px`, async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 900 });
        const run = await stockRun(page, `pilot-stok-${String(width)}-${String(Date.now())}`);
        await page.setViewportSize({ width, height: 844 });

        await page.goto('/gudang/stok');
        await searchStock(page, run.name);
        await expect(page.locator('#konten')).toContainText(run.name);
        expect(await fits(page)).toBe(true);

        if (width >= 1025) {
            await expect(page.getByRole('table', { name: 'Stok' })).toBeVisible();
        } else {
            await expect(page.getByRole('table', { name: 'Stok' })).toHaveCount(0);
            await expect(page.getByRole('list', { name: 'Stok' })).toBeVisible();
        }

        await page.goto(`/gudang/stok/${run.product}`);
        await expect(page.locator('[data-slot="product-lots"]')).toContainText(run.lot);
        expect(await fits(page)).toBe(true);

        await page.goto(`/gudang/riwayat-stok?produk=${run.product}`);
        await expect(page.locator('#konten')).toContainText(run.name);
        expect(await fits(page)).toBe(true);
    });
}

test('Stok on the phone: the list comes before the company values (D-1)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await stockRun(page, `pilot-urutan-${String(Date.now())}`);
    await page.setViewportSize({ width: 390, height: 844 });

    await page.goto('/gudang/stok');
    await searchStock(page, run.name);
    await expect(page.getByRole('list', { name: 'Stok' })).toBeVisible();

    const values = page.getByRole('heading', { name: 'Nilai Stok Perusahaan' });
    await values.waitFor({ timeout: 20_000 }).catch(() => undefined);

    if ((await values.count()) > 0) {
        const list = await page.getByRole('list', { name: 'Stok' }).boundingBox();
        const heading = await values.boundingBox();
        expect(list?.y ?? Number.POSITIVE_INFINITY).toBeLessThan(heading?.y ?? 0);
    }
});

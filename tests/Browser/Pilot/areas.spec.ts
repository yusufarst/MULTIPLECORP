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

test('Stok: the list comes before the company values on the phone and after them from the tablet (D-1)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await stockRun(page, `pilot-urutan-${String(Date.now())}`);

    for (const [width, phone] of [[390, true], [768, false]] as const) {
        await page.setViewportSize({ width, height: 844 });
        await page.goto('/gudang/stok');
        await searchStock(page, run.name);

        const list = page.getByRole('list', { name: 'Stok' });
        await expect(list).toBeVisible();
        await expect(page.locator('[data-slot="stock-values-place"]')).toHaveCount(1);

        // What never depends on the fixture's values: the place of the values in the document and the flex order of the two
        // blocks. The phone's order is the document's (list first); from md up the values' place takes order 1 and the list 2.
        const layout = await list.evaluate((ul) => {
            const place = document.querySelector('[data-slot="stock-values-place"]');
            const parent = place?.parentElement ?? null;
            let block: Element = ul;

            while (block.parentElement !== null && block.parentElement !== parent) block = block.parentElement;

            return {
                sibling: place !== null && block.parentElement === parent,
                listFirst: place !== null && (block.compareDocumentPosition(place) & Node.DOCUMENT_POSITION_FOLLOWING) !== 0,
                listOrder: Number(getComputedStyle(block).order),
                placeOrder: place === null ? Number.NaN : Number(getComputedStyle(place).order),
            };
        });

        expect(layout.sibling).toBe(true);
        expect(layout.listFirst).toBe(true);

        if (phone) {
            expect(layout.placeOrder).toBeGreaterThanOrEqual(layout.listOrder);
        } else {
            expect(layout.placeOrder).toBeLessThan(layout.listOrder);
        }

        // And where it can be seen: with the company values on screen, their place on the page.
        const values = page.locator('[data-slot="stock-values"]').first();
        const shown = await values.waitFor({ state: 'visible', timeout: 20_000 }).then(() => true, () => false);

        console.log(`D-1 at ${String(width)} px: order list ${String(layout.listOrder)}, values ${String(layout.placeOrder)}; the company values ${shown ? 'on screen, position checked' : 'not on screen (the fixture holds none), position not checked'}`);

        if (shown) {
            const listBox = await list.boundingBox();
            const valuesBox = await values.boundingBox();

            if (phone) {
                expect(listBox?.y ?? Number.POSITIVE_INFINITY).toBeLessThan(valuesBox?.y ?? 0);
            } else {
                expect(valuesBox?.y ?? Number.POSITIVE_INFINITY).toBeLessThan(listBox?.y ?? 0);
            }
        }
    }
});

test('Beranda: "Muat Ulang" and the 60-second poll each send a partial request for the home groups alone (F-04)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    await importRun(page, `pilot-poll-${String(Date.now())}`);

    await page.clock.install();
    await page.goto('/beranda');
    await expect(page.getByRole('heading', { name: 'Angka Periode' })).toBeVisible();

    const partial = async (request: { allHeaders: () => Promise<Record<string, string>> }): Promise<string | undefined> => (await request.allHeaders())['x-inertia-partial-data'];

    // The user's refresh.
    const clicked = page.waitForRequest(async (request) => (await partial(request)) !== undefined, { timeout: 30_000 });
    await page.getByRole('button', { name: 'Muat Ulang' }).click();
    const byUser = await partial(await clicked);
    expect(byUser?.length ?? 0).toBeGreaterThan(0);

    // The poll: nothing but the clock moves, past the minute.
    await expect(page.getByRole('button', { name: 'Muat Ulang' })).toBeEnabled();
    const polled = page.waitForRequest(async (request) => (await partial(request)) !== undefined, { timeout: 30_000 });
    await page.clock.fastForward(61_000);
    const byPoll = await partial(await polled);
    expect(byPoll?.length ?? 0).toBeGreaterThan(0);
    // The same groups, never the whole page.
    expect(byPoll).toBe(byUser);
});

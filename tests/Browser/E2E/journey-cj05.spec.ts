import { expect as base, type Locator, type Page, test } from '@playwright/test';

import { settledVisit } from '../adjustment-fixtures';
import { applyWith, countLine, openCount } from '../count-fixtures';
import { countJourney } from '../journey-c-fixtures';
import { clean, guardsClean, watch } from '../journey-fixtures';
import { searchStock } from '../stock-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-09 (TASK-062; CJ-05; J-INV-04; J-INV-05; J-INV-06; UXS-39; AC-06; AC-22; FX-01 7–9; criteria 4, 7, 8): one
// continuous journey on the phone on the floor and the desktop at the desk — a condition change, the reservation bound
// and the reservations of the annex's examples 7 and 8, an opname whose usable loss cuts the chosen reservation
// (example 9), a disposal with its loss recorded once, and a shortage across companies left pending on its own line —,
// each figure read on PG-019 equal to the annex; its console and network captured and ReconcileGuards clean at its end.

// One journey crosses PG-019, PG-027, PG-011, PG-026 and PG-018 at two widths with eleven committed commands.
test.describe.configure({ timeout: 300_000 });

const PHONE = { width: 390, height: 844 };
const DESKTOP = { width: 1280, height: 900 };

/** PG-019's four quantities (PT-17) in the product's base unit: Stok fisik, Direservasi, Tidak layak pakai, Tersedia. */
async function figures(page: Page, product: string, physical: number, reserved: number, unusable: number, available: number): Promise<void> {
    await page.goto(`/gudang/stok/${product}`);
    const block = page.locator('[data-slot="inventory-block"]');

    for (const [label, value] of [['Stok fisik', physical], ['Direservasi', reserved], ['Tidak layak pakai', unusable], ['Tersedia', available]] as const) {
        const row = block.locator('dl > div').filter({ has: page.locator('dt', { hasText: new RegExp(`^${label}$`) }) });
        await expect(row.locator('dd'), `${label} on PG-019`).toHaveText(new RegExp(`^${String(value)} `));
    }
}

/** PG-011's deferred groups land after each visit; a quantity is typed once they have, as WebKit lost one filled while they did (reservations.spec.ts). */
function groups(page: Page): Promise<unknown> {
    return Promise.all(['history', 'predicates', 'documents'].map((group) => page.waitForResponse(async (response) => (await response.request().headerValue('x-inertia-partial-data')) === group)));
}

/** The project's confirmed warehouse line on PG-011, the page settled. */
async function projectLine(page: Page, project: string): Promise<Locator> {
    const loaded = groups(page);
    await page.goto(`/proyek/${project}`);
    await loaded;

    return page.getByRole('list', { name: 'Baris terkonfirmasi' }).locator('[data-line="1"]');
}

/** "Reservasi" from PG-011's line (AX-01): the dialog's ceiling, the quantity, the answer and the line's reserved figure. */
async function reserve(page: Page, project: string, ceiling: number, quantity: number, reserved: number): Promise<void> {
    const line = await projectLine(page, project);
    await line.getByRole('button', { name: 'Reservasi' }).click();
    const dialog = page.getByRole('alertdialog');
    await expect(dialog).toContainText(`Paling banyak ${String(ceiling)} `);
    await dialog.getByRole('textbox').fill(String(quantity));
    await dialog.getByRole('button', { name: 'Reservasi' }).click();
    await expect(page.locator('#konten').getByText('Reservasi tersimpan.')).toBeVisible();
    await expect(line.locator('dd').nth(1)).toHaveText(new RegExp(`^${String(reserved)} `));
}

const unitsOf = (page: Page, lot: string, condition: string) => page.locator('[data-slot="adjustment-lot"]').filter({ hasText: lot }).filter({ hasText: condition }).first();

test('CJ-05: a condition change, the reservation bound, an opname loss cutting a reservation, a disposal and a pending loss on its own line, each figure the annex\'s 7–9 (J-INV-04–J-INV-06; UXS-39; criterion 4)', async ({ page }, testInfo) => {
    const captured = watch(page);
    await page.setViewportSize(DESKTOP);
    const run = await countJourney(page, `cj05-${String(Date.now())}`);

    // On the floor: ten usable units, nothing reserved; "Ubah Kondisi" turns two of them unusable (J-INV-04; AX-06).
    await page.setViewportSize(PHONE);
    await figures(page, run.product, 10, 0, 0, 10);
    await settledVisit(page, () => page.locator('[data-slot="product-commands"]').getByRole('link', { name: 'Ubah Kondisi' }).click());
    await expect(page.getByRole('heading', { name: 'Penyesuaian & Kondisi', exact: true })).toBeVisible();
    await unitsOf(page, run.lot, 'Layak pakai').getByLabel(/Jumlah dari/).fill('2');
    await page.getByLabel('Bukti', { exact: true }).selectOption({ index: 1 });
    await page.locator('[data-slot="adjustment-form"]').getByRole('button', { name: 'Ubah Kondisi' }).click();
    const change = page.getByRole('alertdialog');
    await change.getByLabel('Alasan').fill('Dua unit penyok saat diperiksa');
    await change.getByRole('button', { name: 'Ubah Kondisi' }).click();
    await page.waitForURL(`**/gudang/stok/${run.product}`);
    await expect(page.locator('#konten')).toContainText('Kondisi unit tercatat');

    // Annex 8: ON HAND 10, UNUSABLE 2 — at most 8 reservable, 10 refused with the availability in words (BR-RSV-01).
    await page.setViewportSize(DESKTOP);
    await figures(page, run.product, 10, 0, 2, 8);
    const line = await projectLine(page, run.project);
    await line.getByRole('button', { name: 'Reservasi' }).click();
    const dialog = page.getByRole('alertdialog');
    await expect(dialog).toContainText('Paling banyak 8 ');
    await dialog.getByRole('textbox').fill('10');
    const settled = groups(page);
    await dialog.getByRole('button', { name: 'Reservasi' }).click();
    await expect(dialog).toContainText('Stok tersedia tidak cukup. Tersedia saat ini: 8.');
    await settled;
    await dialog.getByRole('textbox').fill('3');
    await dialog.getByRole('button', { name: 'Reservasi' }).click();
    await expect(page.locator('#konten').getByText('Reservasi tersimpan.')).toBeVisible();
    await expect(line.locator('dd').nth(1)).toHaveText(/^3 /);

    // Annex 7: ON HAND 10, RESERVED 3, UNUSABLE 2 → AVAILABLE 5; a confirmed reservation of 2 more → 10/5/2, AVAILABLE 3.
    await figures(page, run.product, 10, 3, 2, 5);
    await reserve(page, run.project, 5, 2, 5);
    await figures(page, run.product, 10, 5, 2, 3);

    // Annex 9's start: the rest of the reservable 8 reserved — RESERVED 8, nothing available.
    await reserve(page, run.project, 3, 3, 8);
    await figures(page, run.product, 10, 8, 2, 0);

    // J-INV-05 on the floor: "Mulai Hitungan" for the rack's usable units, 6 counted of 8 (ST-03; AX-06).
    await page.setViewportSize(PHONE);
    await openCount(page, run.product, run.rack);
    await countLine(page, '6');
    await expect(page.locator('[data-slot="count-variance"]').first()).toContainText('-2');

    // At the desk: the loss of 2 usable units names its lot and, in the same command, cuts the chosen reservation by 2 (annex 9; BR-RSV-04; IP-20).
    await page.setViewportSize(DESKTOP);
    await page.locator('[data-slot="count-lot"]').filter({ hasText: run.lot }).first().getByLabel(/Jumlah dari/).fill('2');
    const cut = page.locator('[data-slot="reservation-cut"]');
    await expect(cut).toContainText('Stok Tidak Cukup untuk Semua Reservasi');
    await expect(cut.getByRole('link', { name: run.project_number })).toHaveAttribute('href', `/proyek/${run.project}`);
    await cut.getByLabel('Kurangi Reservasi').fill('2');
    await applyWith(page, 'Dua unit layak pakai hilang dari rak');
    await expect(page.locator('[data-slot="count-detail"]')).toContainText('Diterapkan');
    await expect(page.locator('[data-slot="count-detail"]')).not.toContainText('Catat Baris');
    await figures(page, run.product, 8, 6, 2, 0);
    await expect((await projectLine(page, run.project)).locator('dd').nth(1)).toHaveText(/^6 /);

    // J-INV-06 on the floor: "Musnahkan" the two unusable units, the loss recorded once (AX-30; SF-LOSS).
    await page.setViewportSize(PHONE);
    await settledVisit(page, () => page.goto(`/gudang/penyesuaian?produk=${run.product}&aksi=penyesuaian`));
    await expect(page.locator('[data-slot="adjustment-form"]')).toContainText('Kerugian dicatat satu kali');
    await page.getByLabel('Jenis penyesuaian').selectOption({ label: 'Musnahkan' });
    await unitsOf(page, run.lot, 'Tidak layak pakai').getByLabel(/Jumlah dari/).fill('2');
    await page.getByLabel('Bukti', { exact: true }).selectOption({ index: 1 });
    await page.locator('[data-slot="adjustment-form"]').getByRole('button', { name: 'Catat Penyesuaian Stok' }).click();
    const disposal = page.getByRole('alertdialog');
    await disposal.getByLabel('Alasan').fill('Rusak total, dimusnahkan');
    await disposal.getByRole('button', { name: 'Catat Penyesuaian Stok' }).click();
    await expect(page.locator('#konten')).toContainText('Penyesuaian stok tercatat');
    await settledVisit(page, () => page.goto(`/gudang/penyesuaian?produk=${run.product}&aksi=penyesuaian`));
    await expect(page.locator('[data-slot="loss-record"]').first().getByRole('button', { name: 'Balikkan Kerugian' })).toBeVisible();
    await figures(page, run.product, 6, 6, 0, 0);

    // UXS-39: a shortage among two companies' lots is left pending for the Owner, physically adjusted at once (SF-UNATTRIBUTED; PJ-07).
    await openCount(page, run.pending_product, run.pending_rack);
    await countLine(page, '9');
    await expect(page.locator('[data-slot="count-variance"]').first()).toContainText('-1');
    await page.setViewportSize(DESKTOP);
    await expect(page.locator('[data-slot="count-across"]')).toContainText('Selisih menunggu penetapan');
    await page.locator('[data-slot="count-split"]').getByLabel('Selisih menunggu penetapan').fill('1');
    await applyWith(page, 'Tidak jelas milik siapa');
    await expect(page.locator('[data-slot="count-detail"]')).toContainText('Diterapkan');

    // Its own line on PG-019 and PG-018, outside the figures (MSG-41).
    await page.setViewportSize(PHONE);
    await figures(page, run.pending_product, 9, 0, 0, 9);
    await expect(page.locator('[data-slot="stock-pending"]')).toContainText(/Selisih 1\b.*menunggu penetapan Owner/);
    await page.setViewportSize(DESKTOP);
    await page.goto('/gudang/stok');
    await searchStock(page, run.pending_name);
    await expect(page.locator('[data-slot="stock-pending"]').first()).toContainText('menunggu penetapan Owner');

    await guardsClean(page, testInfo, [run.company, run.other]);
    await clean(captured, testInfo);
});

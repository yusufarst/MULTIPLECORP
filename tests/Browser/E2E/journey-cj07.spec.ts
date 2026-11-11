import { expect as base, type Page, test } from '@playwright/test';

import { scanBurst, twinCode } from '../journey-c-fixtures';
import { clean, guardsClean, watch } from '../journey-fixtures';
import { currentLine, fillLine, receivingRun, record } from '../receiving-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-09 (TASK-062; CJ-07; J-INV-01; J-INV-03; UXS-10; UXS-15; UXS-34; UXS-35; IP-15; AC-03; GAP-015; criteria 5, 7, 8):
// the barcode flow as one journey on the phone and on the desktop with a keyboard-wedge scanner simulated as keyboard
// input — an unknown code offering its registration and creating nothing, an ambiguous code listing its candidates and
// choosing none, a receipt by scan and one by typed SKU and "Cari Produk", more than the remainder refused with it, a
// serial scanned twice counted once, the project's line dispatched by scan and by typed SKU with the second a likely
// duplicate —; its console and network captured and ReconcileGuards clean at its end. The real scanners are Layer 3's.

// One journey crosses PG-021, PG-022 twice, PG-023 and PG-024 with five committed commands, two refusals and a dozen scans.
test.describe.configure({ timeout: 300_000 });

/** The visible one of the scan field's two result lines (phone and desktop compositions). */
const shown = (page: Page, role: 'alert' | 'status', text: string) => page.getByRole(role).filter({ hasText: text }).locator('visible=true');

/** PG-024's line: its quantity, the oldest lot tapped, "Catat Barang Keluar" through its confirmation (dispatch.spec.ts). */
async function dispatchTwo(page: Page): Promise<void> {
    await page.locator('[data-slot="dispatch-line"]:visible').getByLabel('Jumlah keluar').fill('2');
    await page.locator('[data-slot="dispatch-lot"]').first().getByRole('button').first().click();
    await recordDispatch(page);
}

async function recordDispatch(page: Page): Promise<void> {
    await page.locator('[data-slot="dispatch-line"]:visible').getByRole('button', { name: 'Catat Barang Keluar' }).click();
    await page.getByRole('alertdialog').getByRole('button', { name: 'Catat Barang Keluar' }).click();
}

for (const [name, width, height] of [['phone', 390, 844], ['desktop', 1280, 900]] as const) {
    test(`CJ-07 on the ${name}: receiving and dispatch by simulated scans, a duplicate counted once, an unknown and an ambiguous code, typed SKU and "Cari Produk" (J-INV-01; J-INV-03; UXS-34; UXS-35; criterion 5)`, async ({ page }, testInfo) => {
        const captured = watch(page);
        await page.setViewportSize({ width, height });
        const run = await receivingRun(page, `cj07-${name}-${String(Date.now())}`);
        const twin = await twinCode(page, run.project);
        // DOC-03's numbering in place, as dispatch.spec.ts's world has it (TASK-041's fixture).
        expect((await page.request.post('/__fixtures/dispatch', { data: { company: run.company } })).ok()).toBe(true);
        const posted: string[] = [];
        page.on('request', (request) => {
            if (request.method() === 'POST') posted.push(new URL(request.url()).pathname);
        });

        // J-INV-01 from the worklist: the project's purchase, no price on the floor (PJ-18), the scan field listening (IP-15 1).
        await page.goto('/gudang/barang-masuk');
        await expect(page.getByRole('heading', { name: 'Barang Masuk', exact: true })).toBeVisible();
        await page.locator('#konten').locator(`a[href="/gudang/barang-masuk/${run.project_purchase}"]:visible`).first().click();
        await page.waitForURL(`**/gudang/barang-masuk/${run.project_purchase}`);
        await expect(page.getByRole('heading', { name: 'Terima Barang', exact: true })).toBeVisible();
        await expect(page.locator('#konten')).not.toContainText('Rp');
        const field = page.getByRole('textbox', { name: 'Pindai atau ketik kode' });
        await expect(field).toBeFocused();

        // IP-15 5: an unknown code says so and offers "Daftarkan Barcode" — the scan itself creates nothing.
        const unknown = `TIDAKADA${String(Date.now())}`;
        await scanBurst(page, unknown);
        await expect(shown(page, 'alert', `Kode ${unknown} tidak dikenal.`)).toBeVisible();
        await expect(page.getByRole('button', { name: 'Daftarkan Barcode' })).toBeVisible();
        await expect(field).toHaveValue('');
        await expect(field).toBeFocused();

        // IP-15 6 (UXS-34): an ambiguous code lists its two candidates and selects none; the user's choice opens the line.
        await scanBurst(page, twin.code);
        await expect(shown(page, 'alert', `Kode ${twin.code} cocok dengan lebih dari satu barang. Pilih yang dimaksud.`)).toBeVisible();
        const candidates = page.locator('[data-slot="scan-candidates"]').getByRole('button');
        await expect(candidates).toHaveCount(2);
        await expect(candidates.filter({ hasText: twin.name })).toHaveCount(1);
        await expect(page.getByRole('status').filter({ hasText: 'dibuka' }).locator('visible=true')).toHaveCount(0);
        await candidates.filter({ hasText: run.goods }).click();
        await expect(shown(page, 'status', `Baris ${run.goods} dibuka.`)).toBeVisible();
        await expect(page.locator('[data-slot="scan-candidates"]')).toHaveCount(0);
        // Only the exact scan lookup was asked: no registration, no command (GAP-015; IP-15 3).
        expect(posted).toContain('/gudang/pindai');
        expect(posted.filter((path) => path !== '/gudang/pindai')).toEqual([]);
        await expect(page.locator('[data-slot="receive-summary"]')).toHaveCount(0);

        // A quantity item scanned once, its quantity entered and recorded by the line's own command (IP-15 8; AX-04).
        await fillLine(page, '4', run.rack);
        await currentLine(page).getByRole('textbox', { name: 'Nomor surat jalan pemasok' }).fill(`SJ-${name}-1`);
        // UXS-35: a scan while the confirmation is open is discarded with its Enter — nothing looked up, nothing pressed.
        await currentLine(page).getByRole('button', { name: 'Catat Barang Masuk' }).click();
        const confirmation = page.getByRole('alertdialog', { name: 'Catat barang masuk ini?' });
        await expect(confirmation).toBeVisible();
        expect(await scanBurst(page, unknown)).toBe(true);
        await expect(confirmation).toBeVisible();
        await expect(page.locator('[data-slot="scan-field"] [role="alert"]').filter({ hasText: unknown })).toHaveCount(0);
        await expect(page.locator('[data-slot="receive-summary"]')).toHaveCount(0);
        await confirmation.getByRole('button', { name: 'Catat Barang Masuk' }).click();
        await expect(currentLine(page).getByText('Barang masuk tercatat.')).toBeVisible();
        await expect(page.locator('[data-slot="receive-progress"]')).toHaveText('0 baris tercatat · 1 baris tersisa');
        await expect(currentLine(page)).toContainText('Sisa diterima 6');

        // UXS-10: more than the remainder is REJECTED with the remainder, the input kept.
        await fillLine(page, '7', run.rack);
        await record(page);
        const refused = page.getByRole('alertdialog', { name: 'Catat barang masuk ini?' });
        await expect(refused).toContainText(/Jumlah melebihi sisa baris ini \(6(\.0+)?\)/);
        await refused.getByRole('button', { name: 'Kembali' }).click();
        await expect(currentLine(page).getByRole('textbox', { name: 'Layak pakai' })).toHaveValue('7');

        // IP-15 9: no scanner — the same field takes typed codes: the unknown one is still unknown, nothing was registered;
        // the typed SKU opens the line, and "Cari Produk" finds it by name.
        await field.fill(unknown);
        await field.press('Enter');
        await expect(shown(page, 'alert', `Kode ${unknown} tidak dikenal.`)).toBeVisible();
        await field.fill(run.sku);
        await field.press('Enter');
        await expect(shown(page, 'status', `Baris ${run.goods} dibuka.`)).toBeVisible();
        await expect(shown(page, 'alert', `Kode ${unknown} tidak dikenal.`)).toHaveCount(0);
        await page.getByRole('button', { name: 'Cari Produk' }).click();
        await page.getByRole('textbox', { name: 'Cari Produk' }).fill(run.goods);
        await expect(page.locator('[data-slot="receive-lines"] li')).toHaveCount(1);
        await page.locator('[data-slot="receive-lines"] li').first().getByRole('button').click();
        await fillLine(page, '6', run.rack);
        await record(page);
        await expect(page.locator('[data-slot="receive-progress"]')).toHaveText('1 baris tercatat · 0 baris tersisa');
        await expect(page.locator('[data-slot="receive-summary"]').getByRole('listitem')).toHaveCount(2);

        // IP-15 7 (MSG-36): a serial scanned twice in one command counts once; the second unit typed without a scanner.
        const serial = `SN-CJ07-${String(Date.now())}`;
        await page.goto(`/gudang/barang-masuk/${run.serial_purchase}`);
        await expect(currentLine(page)).toContainText(run.serial_name);
        await expect(field).toBeFocused();
        await scanBurst(page, serial);
        await expect(currentLine(page).locator('[data-slot="line-serials"]')).toContainText(serial);
        await field.focus();
        await scanBurst(page, serial);
        await expect(shown(page, 'alert', `Nomor seri ${serial} sudah dipindai.`)).toBeVisible();
        await expect(currentLine(page).locator('[data-slot="line-serials"] li')).toHaveCount(1);
        await field.fill(`${serial}-2`);
        await field.press('Enter');
        await expect(currentLine(page).locator('[data-slot="line-serials"] li')).toHaveCount(2);
        const rack = currentLine(page).getByRole('combobox', { name: 'Rak' });
        await rack.selectOption({ value: (await rack.locator('option', { hasText: run.rack }).getAttribute('value')) ?? '' });
        await record(page);
        await expect(currentLine(page).getByText('Barang masuk tercatat.')).toBeVisible();

        // J-INV-03: from PG-023 to the project's line; the ambiguous code chooses nothing here either (IP-15 6).
        await page.goto(`/gudang/barang-keluar?perusahaan=${run.company}`);
        await expect(page.getByRole('heading', { name: 'Barang Keluar', exact: true })).toBeVisible();
        await expect(page.locator('#konten')).toContainText(run.goods);
        await page.goto(`/gudang/barang-keluar/${run.project}`);
        await expect(page.getByRole('heading', { name: 'Keluarkan Barang', exact: true })).toBeVisible();
        await field.focus();
        await scanBurst(page, twin.code);
        await expect(shown(page, 'alert', `Kode ${twin.code} cocok dengan lebih dari satu barang. Pilih yang dimaksud.`)).toBeVisible();
        await expect(page.locator('[data-slot="dispatch-line"]:visible').getByLabel('Jumlah keluar')).toHaveValue('');
        // UXS-34 names PG-024 too: its two candidates listed for the user's choice, and "Cari Produk" beside the field.
        await expect(page.locator('[data-slot="scan-candidates"]').getByRole('button')).toHaveCount(2);
        await expect(page.getByRole('button', { name: 'Cari Produk' })).toBeVisible();

        // The SKU scanned opens the line; dispatched by its own command (AX-03).
        await field.focus();
        await scanBurst(page, run.sku);
        await expect(shown(page, 'status', `Baris ${run.goods} dibuka.`)).toBeVisible();
        await dispatchTwo(page);
        await expect(page.locator('[data-slot="dispatch-line"]:visible')).toContainText('Barang keluar tercatat');
        await expect(page.locator('[data-slot="dispatch-record"]')).toHaveCount(1);

        // Without a scanner: the unknown code typed is still unknown; the typed SKU opens the line, and the same
        // dispatch again is a likely duplicate, committed only with the matches ticked and a reason (UXS-15; BD-13).
        await field.fill(unknown);
        await field.press('Enter');
        await expect(shown(page, 'alert', `Kode ${unknown} tidak dikenal.`)).toBeVisible();
        await field.fill(run.sku);
        await field.press('Enter');
        await expect(shown(page, 'status', `Baris ${run.goods} dibuka.`)).toBeVisible();
        await dispatchTwo(page);
        const warning = page.locator('[data-slot="duplicate-warning"]');
        await expect(warning).toBeVisible();
        await expect(page.locator('[data-slot="dispatch-record"]')).toHaveCount(1);
        await warning.getByRole('checkbox').first().check();
        await warning.getByRole('textbox').fill('Kiriman kedua hari ini');
        await recordDispatch(page);
        await expect(page.locator('[data-slot="dispatch-record"]')).toHaveCount(2);

        await guardsClean(page, testInfo, [run.company]);
        await clean(captured, testInfo);
    });
}

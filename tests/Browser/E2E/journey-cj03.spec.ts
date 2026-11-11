import { expect as base, type Page, test } from '@playwright/test';

import { deliveryRun } from '../delivery-fixtures';
import { type HandoverRun } from '../handover-fixtures';
import { clean, guardsClean, watch } from '../journey-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-09 (TASK-062; CJ-03; J-FUL-02; J-FUL-04; AC-07; BR-INV-02; FS-05; criteria 3, 7, 8): on the phone and on the desktop,
// a shipment of 3 delivered in part — 2 recorded from PG-030 with the recipient, the condition and the proof, the third still
// awaiting —, PG-019's four stock figures read before and after and unchanged, the delivery listed under "Tercatat"; then a
// service handover of 4 of 10 — on the phone through Edit → Preview → Finalize, at the desk beside the BAST's live preview
// and its BAST issued in place —, no stock screen involved; ReconcileGuards clean for the run's two companies, the console
// and network clean.

// One continuous journey of two records and an issue over PG-018, PG-019, PG-030, PG-031, PG-011 and PG-033 after its fixtures.
test.describe.configure({ timeout: 300_000 });

/**
 * TASK-047's fixture on the run's second company — StockFixture's, which holds no PROJECT or BAST numbering yet —: a
 * confirmed service-only project of one line of 10 with its BAST numbering started, for the same signed-in Owner.
 */
async function serviceProject(page: Page, company: string): Promise<Omit<HandoverRun, 'company'>> {
    const response = await page.request.post('/__fixtures/serah-terima', { data: { company, lines: 1, recorded: false } });

    if (!response.ok()) {
        throw new Error(`The handover fixture failed: ${String(response.status())} ${await response.text()}`);
    }

    return (await response.json()) as Omit<HandoverRun, 'company'>;
}

/** PG-019 from PG-018 by the product's SKU typed into the scan field (IP-15), once PG-018's deferred region has settled. */
async function openStock(page: Page, sku: string, product: string): Promise<void> {
    await page.goto('/gudang/stok');
    await expect(page.locator('[data-region-state="loading"]')).toHaveCount(0);
    await page.getByRole('textbox', { name: 'Pindai atau ketik kode' }).click();
    await page.keyboard.type(sku, { delay: 5 });
    await page.keyboard.press('Enter');
    await page.waitForURL(`**/gudang/stok/${product}`);
}

/** PG-019's quantity block (PT-17), each figure read from its own value: Stok fisik, Direservasi, Tidak layak pakai, Tersedia. */
async function expectStock(page: Page, figures: readonly [string, string, string, string]): Promise<void> {
    const block = page.locator('[data-slot="inventory-block"] dl');
    await expect(block.locator('dt')).toHaveText(['Stok fisik', 'Direservasi', 'Tidak layak pakai', 'Tersedia']);
    await expect(block.locator('dd data')).toHaveCount(figures.length);

    for (const [index, value] of figures.entries()) {
        await expect(block.locator('dd').nth(index).locator('data')).toHaveAttribute('value', value);
    }
}

// The pool after the fixtures: 10 of the company's own lot and 4 of the other company's, 3 of the own shipped against the
// line's reservation of 3 — 11 on hand, none reserved, 2 unusable, 9 available (BR-INV-01; DispatchPosting).
const STOCK = ['11', '0', '2', '9'] as const;

for (const [name, width, height] of [['phone', 390, 844], ['desktop', 1280, 900]] as const) {
    test(`CJ-03 on the ${name}: delivers 2 of 3 with the stock unchanged before and after, then hands over 4 of 10 of a service (J-FUL-02; J-FUL-04; AC-07; BR-INV-02)`, async ({ page }, testInfo) => {
        await page.setViewportSize({ width: 1280, height: 900 });
        const run = await deliveryRun(page, `cj03-${name}-${String(Date.now())}`);
        const service = await serviceProject(page, run.other);
        const captured = watch(page);
        await page.setViewportSize({ width, height });
        const content = page.locator('#konten');

        // A project number repeats across the shared database's companies: a row is the run's by its company's code too.
        const ours = () => page.locator('[data-slot="delivery-row"]').filter({ hasText: run.project_number }).filter({ hasText: run.code });

        await openStock(page, run.sku, run.product);
        await expectStock(page, STOCK);

        // J-FUL-02 from PG-030: the shipment awaiting, 2 of its 3 recorded with the recipient, condition and proof (AX-09).
        await page.goto('/gudang/pengiriman');
        await ours().first().getByRole('link').first().click();
        await page.waitForURL(new RegExp(`/gudang/pengiriman/${run.shipment}`));
        await expect(page.locator('[data-slot="delivery-shipment"]')).toContainText('Pengiriman mencatat apa yang diterima klien. Stok tidak berubah di sini.');
        const line = page.locator('[data-slot="delivery-line"]').first();
        // Dikirim · Diterima · Ditutup · Belum diterima.
        await expect(line.locator('dd')).toHaveText([/^3 /, /^0 /, /^0 /, /^3 /]);
        await line.getByLabel(/^Jumlah/).fill('2');
        const form = page.locator('[data-slot="delivery-form"]');
        await form.getByLabel('Nama penerima').fill('Bu Sari');
        await form.getByLabel('Kondisi saat diterima').fill('Kemasan utuh');
        await form.getByLabel('Bukti pengiriman').selectOption({ value: run.proof });
        await form.getByRole('button', { name: 'Catat Pengiriman', exact: true }).click();
        await page.waitForURL(/\/gudang\/pengiriman$/);
        await expect(content).toContainText('Pengiriman tercatat.');

        // Partial: the shipment still awaits its third unit, and the delivery of 2 is listed as recorded (IP-18; NAV-030).
        await expect(ours()).toHaveCount(1);
        await page.getByRole('navigation', { name: 'Pengiriman', exact: true }).getByRole('link', { name: 'Tercatat' }).click();
        await page.waitForURL(/\/gudang\/pengiriman\?tab=tercatat$/);
        await expect(ours()).toHaveCount(1);
        await ours().getByRole('link', { name: 'Bu Sari' }).click();
        await page.waitForURL(new RegExp(`/gudang/pengiriman/${run.shipment}`));
        await expect(line.locator('dd')).toHaveText([/^3 /, /^2 /, /^0 /, /^1 /]);
        await expect(page.locator('[data-slot="delivery-form"]')).toBeVisible();
        const recorded = page.locator('[data-slot="delivery-record"]');
        await expect(recorded).toHaveCount(1);
        await expect(recorded).toContainText('Bu Sari');
        await expect(recorded).toContainText('Kemasan utuh');

        // The delivery changed no stock: the same four figures (AC-07; BR-INV-02).
        await openStock(page, run.sku, run.product);
        await expectStock(page, STOCK);

        // J-FUL-04 on the service project: 4 of its 10 handed over, no stock screen on the way (FS-05).
        if (name === 'phone') {
            await page.goto(`/proyek/${service.project}/serah-terima`);
            await expect(page.locator('[data-slot="document-composer"]')).toHaveAttribute('data-band', 'phone');
        } else {
            await page.goto(`/proyek/${service.project}`);
            await page.getByRole('link', { name: 'Catat serah terima jasa' }).first().click();
            await expect(page).toHaveURL(new RegExp(`/proyek/${service.project}/serah-terima$`));
        }

        await expect(page.getByRole('heading', { name: new RegExp(`Serah terima jasa — ${service.number}`) })).toBeVisible();
        await page.getByLabel(/^Penerima/).fill('Pak Budi');
        await page.getByLabel(/^Jumlah diserahkan/).fill('4');
        await page.getByLabel(/^Pekerjaan yang dilakukan/).fill('Instalasi tahap satu');

        if (name === 'phone') {
            // DC-11 at the client's site: Edit → Preview → Finalize, the preview full-screen.
            await page.getByRole('button', { name: 'Pratinjau', exact: true }).click();
            const preview = page.getByRole('region', { name: /^Pratinjau BAST/ });
            await expect(preview).toContainText('Pak Budi');
            await expect(preview).toContainText('DRAF — belum terbit');
            await page.getByRole('button', { name: 'Lanjut' }).click();
        } else {
            // D-DS-23 at the desk: the form beside the BAST's live preview.
            const paper = page.getByRole('region', { name: /^Pratinjau BAST/ });
            await expect(paper).toContainText('DRAF — belum terbit');
            await expect(paper).toContainText('Pak Budi');
            await expect(paper).toContainText('Instalasi tahap satu');
        }

        await page.getByRole('button', { name: 'Catat Serah Terima' }).click();
        await expect(page).toHaveURL(new RegExp(`/proyek/${service.project}$`));
        await expect(content.getByText('Serah terima jasa tercatat.')).toBeVisible();

        // The handover is partial: 6 still to hand over, the recorded one awaiting its BAST.
        await page.goto(`/proyek/${service.project}/serah-terima`);
        await expect(page.getByTestId('service-line')).toContainText('Dipesan 10 · diserahkan 4 · dibatalkan 0 · sisa 6');
        const handover = page.getByTestId('handover');
        await expect(handover).toHaveCount(1);
        await expect(handover).toContainText('Pak Budi');
        await expect(handover).toContainText('Belum ada BAST.');

        if (name === 'desktop') {
            // "Terbitkan BAST" in place, its number given at the issue (J-FUL-04; IP-08).
            await handover.getByRole('button', { name: 'Terbitkan BAST' }).click();
            const issue = page.getByRole('alertdialog');
            await expect(issue).toContainText('Terbitkan BAST?');
            await issue.getByRole('button', { name: 'Terbitkan BAST' }).click();
            await expect(handover).toContainText(/BAST UJI\/BAST\//);
            await expect(page).toHaveURL(new RegExp(`/proyek/${service.project}/serah-terima$`));
            await expect(handover.getByRole('button', { name: 'Terbitkan BAST' })).toHaveCount(0);
        }

        await guardsClean(page, testInfo, [run.company, run.other]);
        await clean(captured, testInfo);
    });
}

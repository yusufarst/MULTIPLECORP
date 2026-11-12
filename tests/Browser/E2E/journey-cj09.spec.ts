import { expect as base, type Page, test } from '@playwright/test';

import { signedIn, stepUp } from '../account-fixtures';
import { dispatchRun } from '../dispatch-fixtures';
import { evidenceCompany, uniquePdf } from '../evidence-fixtures';
import { PASSWORD } from '../import-fixtures';
import { clean, guardsClean, watch } from '../journey-fixtures';
import { designedNotFound, grantSelf, merged, noDecisionControl, notFoundFace, openAccess, previewAccess, registerOrder, toggle } from '../journey-k1-fixtures';
import { ownerSignedIn } from '../numbering-fixtures';
import { runExports } from '../report-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-09 (TASK-064; CJ-09; AC-13; SM-23; J-INV-07; UXS-18; UXS-20–UXS-23; UXS-25; architecture challenge 8a; criterion 1): company
// isolation as the pages show it, on companies made for the run — company A with its project and lot, company B holding a pooled
// lot of the same product —, each Admin in a browser context of its own beside the Owner's:
// - the Company-A-only Admin on the phone and the desktop: B's pooled lot with the relation marker alone on PG-019 and PG-024 —
//   no name, code, source or cost, no link (UXS-20) —; drawing it without the inter-company authority refused with MSG-14,
//   nothing recorded, the refusal routed to the Owner's *Penolakan yang diteruskan* (UXS-22; J-INV-07; XL-1); the Owner review
//   queue's address the not-found page, identical to an address that does not exist, with no entry for it (UXS-25);
// - the Admin with A and B, on both: each lot with its own company and its own cost, nothing summed across them (UXS-21); then
//   company A's grant revoked by the Owner on PG-060 while the Admin's dispatch page of A's project is open — the next command
//   and the next reload answer not found, the Admin's ready export of A turns *Tidak Tersedia* and its download answers the same
//   (UXS-23; RV-08; RV-03);
// - an Admin of A registering a file identical to evidence of B on the desktop: the ordinary answer, nothing of B shown, the
//   match listed for the Owner alone on PG-008's *Keamanan* (UXS-18; FL-10; LG-02).
// Each journey watches every page's console and network — the not-found answers it asks for by design are asserted given and
// set aside, nothing else — and ends with ReconcileGuards clean for its companies (TS-11).

test.describe.configure({ timeout: 300_000 });

const DESK = { width: 1280, height: 900 };
const PHONE = { width: 390, height: 844 };

/** MSG-14 as the dispatch line answers a draw on another company's lot without the authority (AZ-08; SQ-23). */
const MSG_14 = 'Tindakan ini menyangkut catatan perusahaan lain di luar akses Anda. Tidak diproses, dan diteruskan: Owner atau admin yang berwenang akan melihatnya.';

/** The Admin's own browser context beside the Owner's, at the width of the run. */
async function adminPage(page: Page, viewport: { width: number; height: number }) {
    const context = await page.context().browser()?.newContext({ baseURL: test.info().project.use.baseURL, ignoreHTTPSErrors: true, viewport });

    if (context === undefined) {
        throw new Error('No browser for the Admin\'s context.');
    }

    return { context, admin: await context.newPage() };
}

/** PG-024's quantity and lot chosen on the open dispatch line, then "Catat Barang Keluar" and its confirmation (J-INV-03). */
async function dispatchFrom(page: Page, lot: string, quantity: string, reason?: string): Promise<void> {
    await page.locator('[data-slot="dispatch-line"]:visible').getByLabel('Jumlah keluar').fill(quantity);
    await page.locator('[data-slot="dispatch-lot"]').filter({ hasText: lot }).getByRole('button').first().click();

    if (reason !== undefined) {
        await page.locator('[data-slot="dispatch-line"]:visible').getByLabel('Alasan alokasi antar-perusahaan').fill(reason);
    }

    await page.locator('[data-slot="dispatch-line"]:visible').getByRole('button', { name: 'Catat Barang Keluar' }).click();
    await page.getByRole('alertdialog').getByRole('button', { name: 'Catat Barang Keluar' }).click();
}

for (const [device, viewport] of [['phone', PHONE], ['desktop', DESK]] as const) {
    test(`CJ-09 on the ${device}: the Company-A-only Admin sees B's pooled lot by its marker alone, its draw refused with MSG-14 and routed, the review queue's address not found (UXS-20; UXS-22; UXS-25; J-INV-07; criterion 1)`, async ({ page }, testInfo) => {
        const captured = watch(page);
        await page.setViewportSize(DESK);
        const run = await dispatchRun(page, `cj09-${device}-${String(Date.now())}`);

        const { context, admin } = await adminPage(page, viewport);
        const capturedAdmin = watch(admin);
        // SECURITY_TEST_MATRIX §2's Company-A-only Admin: company A, *Lihat stok*, *Lihat proyek*, *Keluarkan barang* — no
        // inter-company authority, nothing high-risk.
        await signedIn(admin, `cj09-a-${device}`, { plain: true });
        expect((await admin.request.post('/__fixtures/dispatch/plain', { data: { company: run.company } })).ok()).toBe(true);
        const content = admin.locator('#konten');

        // UXS-20 on PG-019: the own lot with its source; B's lot with the relation marker, its code, quantity, rack and condition
        // only — no name, company code, source, cost or link (PJ-01; PJ-04; H7-02).
        await admin.goto(`/gudang/stok/${run.product}`);
        const lots = admin.locator('[data-slot="product-lots"]');
        await expect(lots.locator('[data-slot="product-lot"]').filter({ hasText: run.lot }).first()).toContainText(`Sumber ${run.code}`);
        const foreign = lots.locator('[data-slot="product-lot"]').filter({ hasText: 'perusahaan lain' });
        await expect(foreign).toHaveCount(1);
        await expect(foreign).not.toContainText('Sumber');
        await expect(foreign.locator('a')).toHaveCount(0);
        await expect(content).not.toContainText(run.other_name);
        await expect(content).not.toContainText(run.other_code);
        await expect(content).not.toContainText('Biaya tersisa');

        // J-INV-07 inside J-INV-03: the same marker on PG-024's lots; drawing B's lot without `stock.allocate_intercompany`
        // REJECTED with MSG-14 naming who will see it, nothing recorded (UXS-22; AZ-08; XL-1).
        await admin.goto(`/gudang/barang-keluar/${run.project}`);
        await expect(admin.getByRole('heading', { name: 'Keluarkan Barang', exact: true })).toBeVisible();
        await expect(admin.locator('[data-slot="dispatch-lot"]').filter({ hasText: 'perusahaan lain' })).toHaveCount(1);
        await expect(content).not.toContainText(run.other_name);
        await dispatchFrom(admin, 'perusahaan lain', '1', 'Stok sendiri habis');
        await expect(admin.locator('[data-slot="dispatch-line"]:visible')).toContainText(MSG_14);
        await expect(admin.locator('[data-slot="dispatch-record"]')).toHaveCount(0);

        // The routed notice reaches those with the grants — the Owner's *Penolakan yang diteruskan* (QS-20; PJ-13).
        await page.goto('/beranda');
        const routed = page.getByRole('region', { name: 'Penolakan yang diteruskan' });
        await expect(routed).toContainText('Meneruskan alokasi antar-perusahaan yang ditolak');
        await expect(routed).toContainText(new RegExp(`${run.code}|${run.other_code}`));

        // UXS-25: the Owner review queue's address answers the Admin as the not-found page, identical to an address that does
        // not exist (AZ-11; OD-14); no entry, badge or count for it.
        const queue = await admin.goto('/tinjauan');
        expect(queue?.status()).toBe(404);
        const asQueue = await notFoundFace(admin);
        const unknownPath = `/tinjauan-${String(Date.now())}`;
        const unknown = await admin.goto(unknownPath);
        expect(unknown?.status()).toBe(404);
        expect(await notFoundFace(admin)).toBe(asQueue);
        await admin.goto('/pekerjaan');
        await expect(admin.getByRole('heading', { name: 'Pekerjaan', level: 1 })).toBeVisible();
        await expect(admin.getByRole('link', { name: 'Tinjauan' })).toHaveCount(0);
        await expect(admin.getByRole('link', { name: 'Beranda' })).toHaveCount(0);

        await guardsClean(page, testInfo, [run.company, run.other]);
        await clean(designedNotFound(merged(captured, capturedAdmin), [/^GET \/tinjauan 404$/, new RegExp(`^GET ${unknownPath} 404$`)]), testInfo);
        await context.close();
    });

    test(`CJ-09 on the ${device}: the Admin with A and B sees each lot's company and no sum across them; A's grant revoked on an open page — the next command and reload not found, the export Tidak Tersedia (UXS-21; UXS-23; RV-03; RV-08; criterion 1)`, async ({ page }, testInfo) => {
        const captured = watch(page);
        await page.setViewportSize(DESK);
        const run = await dispatchRun(page, `cj09-cabut-${device}-${String(Date.now())}`);

        // SECURITY_TEST_MATRIX §2's Admin with A and B, with `cost.view` — a high-risk view, so signed in with TOTP (AU-18).
        const { context, admin } = await adminPage(page, DESK);
        const capturedAdmin = watch(admin);
        const account = await signedIn(admin, `cj09-ab-${device}`);
        await grantSelf(admin, [run.company, run.other], ['stock.view', 'cost.view', 'projects.view', 'stock.dispatch', 'reports.export']);
        const content = admin.locator('#konten');

        // An export of the stock report over both companies, generated and ready before the change (IP-12).
        await admin.goto('/laporan?laporan=stok');
        await expect(admin.getByRole('heading', { name: 'Laporan', exact: true })).toBeVisible();
        await expect(admin.locator('[data-table]').first()).toBeVisible();
        await admin.getByRole('button', { name: 'Ekspor' }).click();
        await admin.getByRole('menuitem', { name: 'Ekspor Excel' }).click();
        await admin.waitForURL('**/laporan/ekspor');
        const exports = admin.locator('[data-slot="export-row"]');
        await expect(exports.first()).toContainText('Laporan Stok · Excel');
        expect(await runExports(admin)).toBe(1);
        await admin.goto('/laporan/ekspor');
        await expect(exports.first()).toContainText('Siap Diunduh');
        const download = (await exports.first().getByRole('link', { name: 'Unduh' }).getAttribute('href')) ?? '';
        expect(download).toMatch(/^\/laporan\/ekspor\/[0-9a-f-]{36}\/unduh$/);

        // UXS-21 on PG-019: both lots carry their own company and their own remaining cost; neither is marked as another
        // company's, and no figure sums the two (CS-08; PF-25).
        await admin.setViewportSize(viewport);
        await admin.goto(`/gudang/stok/${run.product}`);
        const lots = admin.locator('[data-slot="product-lots"]');
        await expect(admin.locator('[data-region-state="loading"]')).toHaveCount(0);
        await expect(lots.locator('[data-slot="product-lot"]').filter({ hasText: run.lot }).first()).toContainText(`Sumber ${run.code}`);
        const fromB = lots.locator('[data-slot="product-lot"]').filter({ hasText: `Sumber ${run.other_code}` });
        await expect(fromB).toHaveCount(1);
        await expect(fromB).toContainText(run.other_name);
        await expect(lots.getByText('perusahaan lain')).toHaveCount(0);
        for (const lot of await lots.locator('[data-slot="product-lot"]').all()) {
            await expect(lot.getByText(/Biaya tersisa/)).toHaveCount(1);
        }
        await expect(content).not.toContainText(/Total biaya|Jumlah biaya/);

        // UXS-23: company A's dispatch page open on the Admin's device while the Owner revokes A on PG-060 — B kept — behind its
        // confirmation and the step-up (J-ACC-02; IP-10).
        const open = `/gudang/barang-keluar/${run.project}`;
        await admin.goto(open);
        await expect(admin.getByRole('heading', { name: 'Keluarkan Barang', exact: true })).toBeVisible();

        await openAccess(page, account.id);
        await toggle(page, `perusahaan-${run.company}`);
        await previewAccess(page);
        await page.getByRole('button', { name: 'Simpan Akses' }).click();
        const revoking = page.getByRole('alertdialog');
        await expect(revoking).toContainText(`Akses Perusahaan dicabut: ${run.code}`);
        await revoking.getByRole('button', { name: 'Simpan Akses', exact: true }).click();
        await stepUp(page, PASSWORD);
        await expect(page.getByText('Akses disimpan.').first()).toBeVisible();

        // The open page's next command answers not found, identical to a missing page — nothing recorded (RV-03; AZ-03).
        await dispatchFrom(admin, run.lot, '1');
        const asCommand = await notFoundFace(admin);
        expect(asCommand).toContain('Halaman tidak ditemukan.');

        // Its next reload the same (IP-21).
        const reload = await admin.goto(open);
        expect(reload?.status()).toBe(404);
        expect(await notFoundFace(admin)).toBe(asCommand);

        // RV-08: the export over A turns *Tidak Tersedia*, with no download; its address answers as a missing page (DP-14).
        await admin.goto('/laporan/ekspor');
        await expect(exports.first()).toContainText('Tidak Tersedia');
        await expect(exports.first()).toContainText('Ekspor ini tidak lagi tersedia karena akses Anda berubah atau masa simpannya berakhir.');
        await expect(exports.first().getByRole('link', { name: 'Unduh' })).toHaveCount(0);
        expect((await admin.request.get(download, { maxRedirects: 0 })).status()).toBe(404);

        // Nothing was dispatched from A's lot.
        await page.goto(open);
        await expect(page.locator('[data-slot="dispatch-record"]')).toHaveCount(0);

        await guardsClean(page, testInfo, [run.company, run.other]);
        const escaped = open.replace(/[/.-]/g, '\\$&');
        await clean(designedNotFound(merged(captured, capturedAdmin), [new RegExp(`^POST ${escaped}/keluarkan 404$`), new RegExp(`^GET ${escaped} 404$`)]), testInfo);
        await context.close();
    });
}

test('CJ-09 on the desktop: an Admin of A registers a file identical to evidence of B — the ordinary answer, nothing of B shown; the Owner alone sees the match on the review queue (UXS-18; FL-10; IP-16; LG-02; criterion 1)', async ({ page }, testInfo) => {
    const captured = watch(page);
    await page.setViewportSize(DESK);
    await ownerSignedIn(page, `cj09-bukti-${String(Date.now())}`);
    const a = await evidenceCompany(page);
    const b = await evidenceCompany(page);
    const stamp = String(Date.now());
    const bytes = uniquePdf();

    // B's client order, registered by the Owner.
    const inB = await registerOrder(page, b.company, bytes, 'sp-b.pdf', `SMA Uji B ${stamp}`, `SP/B/${stamp}`);
    await expect(inB.getByText('Versi 1')).toBeVisible();

    // An Admin of A only, with *Lihat bukti* and *Unggah bukti* — a high-risk view, so signed in with TOTP (AU-18).
    const { context, admin } = await adminPage(page, DESK);
    const capturedAdmin = watch(admin);
    const account = await signedIn(admin, 'cj09-bukti-a');
    await grantSelf(admin, [a.company], ['evidence.view', 'evidence.upload']);

    // An ordinary registration first, then the same bytes as B's: both answers alike — the evidence's own sheet, version 1
    // current, no warning, flag, note or reason, nothing naming B (UXS-18; IP-16).
    const ordinary = await registerOrder(admin, a.company, uniquePdf(), 'sp-a1.pdf', `SMA Uji A ${stamp}`, `SP/A1/${stamp}`);
    await expect(ordinary.getByText('Versi 1')).toBeVisible();
    await expect(ordinary.getByText('Berlaku', { exact: true })).toBeVisible();
    const matched = await registerOrder(admin, a.company, bytes, 'sp-a2.pdf', `SMA Uji A ${stamp}`, `SP/A2/${stamp}`);
    await expect(matched.getByText('Versi 1')).toBeVisible();
    await expect(matched.getByText('Berlaku', { exact: true })).toBeVisible();
    await expect(matched.getByRole('link', { name: 'Unduh' })).toHaveAttribute('href', /\/berkas\/[0-9a-f-]{36}\/unduh$/);
    await expect(admin.locator('[data-slot="duplicate-warning"]')).toHaveCount(0);
    for (const hidden of [b.code, `SMA Uji B ${stamp}`, `SP/B/${stamp}`]) {
        await expect(admin.locator('body')).not.toContainText(hidden);
    }
    await expect(matched).not.toContainText(/perusahaan lain|serupa|ganda/i);

    // The Owner's PG-008 *Keamanan*: the match by its actor, with A's evidence and B's related record, and no decision control
    // (LG-02; TECH-022; UXS-40). The event is written after the Admin's answer, so the list is read until it arrives.
    await expect(async () => {
        await page.goto(`/tinjauan?tab=keamanan&pelaku=${account.id}`);
        await expect(page.locator('[data-slot="review-items"]')).toContainText('Bukti serupa di perusahaan lain', { timeout: 5_000 });
    }).toPass({ timeout: 60_000 });
    const item = page.locator('[data-slot="review-items"] li').filter({ hasText: 'Bukti serupa di perusahaan lain' }).first();
    await expect(item).toContainText(a.code);
    await expect(item.getByRole('link', { name: 'Buka Catatan' })).toBeVisible();
    await expect(item.getByRole('link', { name: `Catatan terkait · ${b.code}` })).toBeVisible();
    await noDecisionControl(page);

    await guardsClean(page, testInfo, [a.company, b.company]);
    await clean(merged(captured, capturedAdmin), testInfo);
    await context.close();
});

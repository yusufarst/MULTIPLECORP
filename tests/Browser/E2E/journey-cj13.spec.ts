import { expect as base, type Page, test } from '@playwright/test';

import { type Account, signedIn, signIn, stepUp } from '../account-fixtures';
import { evidenceCompany, uniquePdf } from '../evidence-fixtures';
import { adminSignOff, batchOf, importRun, PASSWORD, prepareCompany, work } from '../import-fixtures';
import { clean, guardsClean, watch } from '../journey-fixtures';
import { box, confirmationAged, merged, noDecisionControl, openAccess, prepareBatch, previewAccess, registerOrder, reviewSources, toggle } from '../journey-k1-fixtures';
import { numberingCompany, ownerSignedIn } from '../numbering-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-09 (TASK-064; CJ-13; AICWDF §28's approval example; UXS-26; UXS-40; QS-14; AU-15; IP-10; criterion 5): on the desktop, the
// Owner's approvals and decisions on companies made for the run —
// - a sent quotation's approval recorded as the commercial confirmation (J-QUO-01; J-PRJ-02), and a project confirmed without a
//   quotation on the client's registered order (J-PRJ-02's other path);
// - a high-risk Owner command on PG-060: the session's first asks the step-up, the next within 15 minutes does not, and once the
//   last confirmation is older than 15 minutes the password dialog opens above the open confirmation, the form behind it keeps its
//   input, and the same `command_id` is sent after it (UXS-26; MSG-27; H7-09) — the decision then on PG-008's *Aktivitas*;
// - PG-008 with every QS-14 source: the ADM_PLUS and OWNER_ONLY events and an acknowledged duplicate warning (QS-21) on
//   *Aktivitas*; AUTHORIZATION_ANOMALY, EVIDENCE_DUPLICATE_UNDISCLOSED and IMPORT_COLLISION_UNDISCLOSED on *Keamanan*; pool
//   evidence on *Bukti Gudang Bersama*; an import row whose committed twin lies outside its batch's scope on *Impor*; money
//   recovered for a written-off invoice, a refund with an unbacked remainder (a refunded credit's payment then partly contra'd, each
//   through its command's own action; L-46; C-44) and an invoice left outstanding after a payment under *Kondisi yang Masih
//   Terbuka* — each linking to its record, with no approve, dismiss or read control anywhere (UXS-40).
// Each journey watches its console and network and ends with ReconcileGuards clean for its companies (TS-11).

test.describe.configure({ timeout: 300_000 });

const DESK = { width: 1280, height: 900 };
const YEAR_START = `${String(new Date().getFullYear())}-01-01`;

/** A fixture of the signed-in Owner's run, its answer. */
async function fixture<T>(page: Page, path: string, data: Record<string, unknown>): Promise<T> {
    const response = await page.request.post(path, { data });

    if (!response.ok()) {
        throw new Error(`The fixture ${path} failed: ${String(response.status())} ${await response.text()}`);
    }

    return (await response.json()) as T;
}

/** PG-008 on one tab, filtered as asked, read again until the items the run made are listed (they may be written after the answer). */
async function review(page: Page, query: string, expected: string[]): Promise<void> {
    const items = page.locator('[data-slot="review-items"]');

    await expect(async () => {
        await page.goto(`/tinjauan?${query}`);
        await expect(page.getByRole('heading', { name: 'Tinjauan', level: 1 })).toBeVisible({ timeout: 5_000 });
        for (const text of expected) {
            await expect(items).toContainText(text, { timeout: 5_000 });
        }
    }).toPass({ timeout: 60_000 });
}

test('CJ-13: a quotation approval and a confirmation recorded, then a high-risk Owner command through the step-up after 15 minutes with the same command_id, listed on the review queue (UXS-26; UXS-40; AU-15; criterion 5)', async ({ page }, testInfo) => {
    const captured = watch(page);
    await page.setViewportSize(DESK);
    const owner = await signedIn(page, 'cj13-owner', { owner: true });
    const content = page.locator('#konten');

    // TASK-033's sent quotation in one company, TASK-034's unconfirmed project with its client's order in another.
    const quoting = await numberingCompany(page, YEAR_START);
    const quotation = await fixture<{ code: string; project: string; number: string }>(page, '/__fixtures/penawaran', { company: quoting, lines: 1, stage: 'sent' });
    const confirming = await numberingCompany(page, YEAR_START);
    const order = await fixture<{ code: string; project: string; number: string }>(page, '/__fixtures/konfirmasi', { company: confirming, confirmed: false });

    // J-QUO-01: the client's approval recorded on PG-014, landing on PG-011 as the commercial confirmation.
    await page.goto(`/proyek/${quotation.project}/penawaran`);
    await expect(page.getByRole('heading', { level: 1 })).toHaveText(`Penawaran ${quotation.number}`);
    await page.getByRole('button', { name: 'Catat Jawaban Klien' }).first().click();
    await page.getByRole('alertdialog').getByRole('button', { name: 'Catat Disetujui' }).click();
    await page.waitForURL(`**/proyek/${quotation.project}`);
    await expect(page.getByText('Persetujuan klien dicatat sebagai konfirmasi komersial.')).toBeVisible();
    await page.goto(`/dokumen/penawaran?perusahaan=${quoting}`);
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Daftar Penawaran');
    await expect(page.getByRole('row').filter({ hasText: quotation.number })).toContainText('Disetujui Klien');

    // J-PRJ-02 without a quotation: "Konfirmasi Proyek" on the client's registered order, the lines then locked.
    await page.goto(`/proyek/${order.project}`);
    await page.getByRole('button', { name: 'Konfirmasi Proyek' }).click();
    const confirmation = page.getByRole('alertdialog');
    await expect(confirmation).toContainText('tidak memesan stok');
    await confirmation.getByRole('button', { name: 'Konfirmasi Proyek' }).click();
    await expect(content.getByText('Proyek dikonfirmasi. Baris dan harganya sudah dikunci.')).toBeVisible();
    await expect(content).toContainText('Dikonfirmasi tanpa penawaran');

    // Every access command's request, with its `command_id` and its status, from here on.
    const sent: { path: string; command: string; status: number }[] = [];
    page.on('response', (response) => {
        const request = response.request();

        if (request.method() === 'PUT' && /^\/pengaturan\/pengguna\/[0-9a-f-]{36}\/akses$/.test(new URL(request.url()).pathname)) {
            const body = request.postDataJSON() as { command_id?: unknown } | null;
            sent.push({ path: new URL(request.url()).pathname, command: typeof body?.command_id === 'string' ? body.command_id : '', status: response.status() });
        }
    });

    // The session's first high-risk command asks the step-up (AU-15): an account created on PG-060.
    const stamp = String(Date.now());
    const email = `cj13-${stamp}@contoh.test`;
    await page.goto('/pengaturan/pengguna');
    await page.getByRole('button', { name: 'Buat Akun' }).click();
    await page.getByLabel(/^Nama/).fill(`Akun CJ13 ${stamp}`);
    await page.getByLabel(/^E-mail/).fill(email);
    await page.locator('form').getByRole('button', { name: 'Buat Akun' }).click();
    await page.getByRole('alertdialog').getByRole('button', { name: 'Buat Akun', exact: true }).click();
    await stepUp(page, owner.password);
    await expect(page.getByRole('heading', { level: 1 })).toHaveText(`Akun CJ13 ${stamp}`);
    await page.waitForURL(/akun=/);
    const account = new URL(page.url()).searchParams.get('akun') ?? '';

    // Within 15 minutes the next one is sent once and answered, no password asked.
    await openAccess(page, account);
    await toggle(page, `perusahaan-${quoting}`);
    await toggle(page, 'kewenangan-projects.view');
    await previewAccess(page);
    await page.getByRole('button', { name: 'Simpan Akses' }).click();
    await page.getByRole('alertdialog').getByRole('button', { name: 'Simpan Akses', exact: true }).click();
    await expect(page.getByText('Akses disimpan.').first()).toBeVisible();
    expect(sent.map((each) => each.status === 423)).toEqual([false]);

    // Past 15 minutes since the last confirmation (UXS-26): the password dialog above the open confirmation, MSG-27 naming the
    // account; the confirmation behind keeps the change listed; after the password the same `command_id` is sent again.
    await openAccess(page, account);
    await toggle(page, 'kewenangan-projects.view');
    await expect(box(page, 'kewenangan-projects.view')).toHaveAttribute('data-unchecked');
    await previewAccess(page);
    await confirmationAged(page);
    await page.getByRole('button', { name: 'Simpan Akses' }).click();
    const revoking = page.locator('[role="alertdialog"]').filter({ hasText: 'Simpan akses?' });
    await expect(revoking).toContainText('Lihat proyek dicabut');
    await revoking.getByRole('button', { name: 'Simpan Akses', exact: true }).click();
    const password = page.getByRole('alertdialog', { name: 'Konfirmasi Kata Sandi' });
    await expect(password).toBeVisible();
    await expect(password).toContainText('Konfirmasi kata sandi untuk melanjutkan. Diminta kembali setelah 15 menit.');
    await expect(revoking).toBeVisible();
    await expect(revoking).toContainText('Lihat proyek dicabut');
    expect(await password.evaluate((dialog) => {
        const area = dialog.getBoundingClientRect();
        const top = document.elementFromPoint(area.left + area.width / 2, area.top + area.height / 2);

        return top !== null && dialog.contains(top);
    }), 'the password dialog lies above the open confirmation').toBe(true);
    await stepUp(page, owner.password);
    await expect(page.getByText('Akses disimpan.').first()).toBeVisible();
    await expect(page.locator('[role="alertdialog"]')).toHaveCount(0);

    expect(sent.map((each) => each.status === 423)).toEqual([false, true, false]);
    const [granted, asked, replayed] = sent;
    expect(asked?.command, 'the step-up\'s replay carries the same command_id').toBe(replayed?.command);
    expect(asked?.command).toMatch(/^[0-9a-f-]{36}$/);
    expect(asked?.command, 'a new intent mints a new command_id').not.toBe(granted?.command);
    await openAccess(page, account);
    await expect(box(page, 'kewenangan-projects.view')).toHaveAttribute('data-unchecked');

    // The decision on PG-008's *Aktivitas* (QS-14: OWNER_ONLY), by its actor and linked to the account; no decision control.
    await review(page, `pelaku=${owner.id}`, ['Mengubah akses akun']);
    await expect(page.locator(`[data-slot="review-items"] a[href*="${account}"]`).first()).toBeVisible();
    await noDecisionControl(page);

    await guardsClean(page, testInfo, [quoting, confirming]);
    await clean(captured, testInfo);
});

test('CJ-13 review queue: an access grant, an evidence match and an import collision outside the Admin\'s scope listed for the Owner on Aktivitas, Keamanan and Impor, with no decision control (UXS-40; UXS-18; UXS-19; QS-14; criterion 5)', async ({ browser, page }, testInfo) => {
    const captured = watch(page);
    await page.setViewportSize(DESK);
    const run = await importRun(page, `cj13-impor-${String(Date.now())}`);
    const identity = `S-${String(Date.now())}`;
    await fixture(page, '/__fixtures/impor/kembar', { identity });

    // An Admin of the run preparing in the run's company only (PJ-20), given *Lihat bukti* and *Unggah bukti* by the Owner on
    // PG-060 behind the step-up — the Owner's OWNER_ONLY decision.
    const preparer = await fixture<Account>(page, '/__fixtures/akun', { run: `cj13-penyiap-${String(Date.now())}`, kind: 'totp' });
    const scoped = await prepareCompany(page, run.company, preparer.id);
    await openAccess(page, preparer.id);
    await toggle(page, 'kewenangan-evidence.view');
    await toggle(page, 'kewenangan-evidence.upload');
    await previewAccess(page);
    await page.getByRole('button', { name: 'Simpan Akses' }).click();
    const granting = page.getByRole('alertdialog');
    await expect(granting).toContainText('Lihat bukti — berlaku di');
    await granting.getByRole('button', { name: 'Simpan Akses', exact: true }).click();
    await stepUp(page, PASSWORD);
    await expect(page.getByText('Akses disimpan.').first()).toBeVisible();

    // Evidence of another company the Owner registers.
    const other = await evidenceCompany(page);
    const bytes = uniquePdf();
    const stamp = String(Date.now());
    await expect((await registerOrder(page, other.company, bytes, 'sp-lain.pdf', `SMA Uji Lain ${stamp}`, `SP/L/${stamp}`)).getByText('Versi 1')).toBeVisible();

    // The Admin, on its own device: the same bytes registered in the run's company, then a batch whose row's committed twin lies
    // in a company outside its scope — each answered as ordinary (UXS-18; UXS-19; DP-18).
    const context = await browser.newContext({ baseURL: test.info().project.use.baseURL, ignoreHTTPSErrors: true, viewport: DESK });
    const admin = await context.newPage();
    const capturedAdmin = watch(admin);
    await signIn(admin, preparer);
    await expect((await registerOrder(admin, run.company, bytes, 'sp-sama.pdf', `SMA Uji ${stamp}`, `SP/S/${stamp}`)).getByText('Versi 1')).toBeVisible();
    const batch = await prepareBatch(admin, scoped, 'OPENING_STOCK', [{ id: identity, perusahaan: scoped.code, produk: 'TIDAK-ADA', lokasi: 'TIDAK-ADA', jumlah: '1', biaya: '100' }], 'stok.csv');
    await expect(batch.locator('[data-slot="import-problems"]')).toContainText('Tidak Valid');

    // *Keamanan*: both undisclosed matches by the Admin, each with its company and its related record (TECH-022; LG-02).
    await review(page, `tab=keamanan&pelaku=${preparer.id}`, ['Bukti serupa di perusahaan lain', 'Data impor serupa di perusahaan lain']);
    const evidenceMatch = page.locator('[data-slot="review-items"] li').filter({ hasText: 'Bukti serupa di perusahaan lain' }).first();
    await expect(evidenceMatch).toContainText(run.code);
    await expect(evidenceMatch.getByRole('link', { name: 'Buka Catatan' })).toBeVisible();
    await expect(evidenceMatch.getByRole('link', { name: `Catatan terkait · ${other.code}` })).toBeVisible();
    await noDecisionControl(page);

    // The Admin's second batch: a legacy document of the run's company whose twin is not committed yet, dry-run to *Uji Coba
    // Lolos* and signed off by the Owner and an Admin (OD-13); its twin then committed in a company outside the batch's scope,
    // and the Owner's "Jalankan Impor" — within AU-15's 15 minutes of the grant's password, so none is asked — runs the
    // validation again and marks the row DUPLICATE (IMP-05; C-47). A kind whose rows carry a company and name no master: a
    // rack is a pool record of no company (CS-05), so its twin never lies outside a scope.
    const legacy = `D-${stamp}`;
    const documents = await prepareBatch(admin, scoped, 'LEGACY_DOCUMENT', [{ id: legacy, perusahaan: scoped.code, jenis: 'Faktur', nomor: `LAMA-${stamp}` }], 'dokumen.csv');
    await documents.getByRole('button', { name: 'Jalankan Uji Coba', exact: true }).click();
    await expect(admin.locator('#konten')).toContainText('Uji coba dijalankan.');
    const id = batchOf(admin);
    await work(page, id);
    const committing = page.locator('[data-slot="import-batch"]');
    await expect(committing.locator('[data-status="import_batch.DRY_RUN_PASSED"]')).toBeVisible();
    await committing.getByRole('button', { name: 'Setujui Hasil Uji Coba' }).click();
    const approval = page.getByRole('dialog', { name: 'Setujui Hasil Uji Coba' });
    await approval.getByLabel('Bukti persetujuan').setInputFiles({ name: 'persetujuan.pdf', mimeType: 'application/pdf', buffer: uniquePdf() });
    await expect(approval.getByText('persetujuan.pdf')).toBeVisible();
    await approval.getByRole('button', { name: 'Setujui Hasil Uji Coba' }).click();
    await expect(page.locator('#konten')).toContainText('Persetujuan dicatat.');
    await adminSignOff(page, id);
    await page.reload();
    await expect(committing.locator('[data-status="import_batch.SIGNED_OFF"]')).toBeVisible();
    await fixture(page, '/__fixtures/impor/kembar', { identity: legacy, kind: 'LEGACY_DOCUMENT' });
    await committing.getByRole('button', { name: 'Jalankan Impor' }).click();
    await page.getByRole('alertdialog', { name: 'Jalankan Impor' }).getByRole('button', { name: 'Jalankan Impor' }).click();
    await expect(page.locator('#konten')).toContainText('Impor dijalankan.');
    await work(page, id);
    await expect(committing.locator('[data-status="import_batch.COMMITTED"]')).toBeVisible();

    // *Impor*: the DUPLICATE row whose twin lies outside the batch's scope, by its preparer, linked to its batch (DP-18).
    await review(page, `tab=impor&pelaku=${preparer.id}`, ['Dokumen lama']);
    const row = page.locator('[data-slot="review-items"] li').filter({ hasText: 'Dokumen lama' }).first();
    await expect(row).toContainText(/Baris \d+/);
    await expect(row.getByRole('link', { name: 'Buka Catatan' })).toBeVisible();
    await noDecisionControl(page);

    // *Aktivitas*: the Owner's grant change — OWNER_ONLY, without a company, so listed under the run's company filter too —
    // linked to the account.
    await review(page, `perusahaan=${run.company}`, ['Mengubah akses akun']);
    await expect(page.locator(`[data-slot="review-items"] a[href*="${preparer.id}"]`).first()).toBeVisible();
    await noDecisionControl(page);

    await guardsClean(page, testInfo, [run.company, other.company]);
    await clean(merged(captured, capturedAdmin), testInfo);
    await context.close();
});

test('CJ-13 review queue: an acknowledged duplicate on Aktivitas, an anomaly on Keamanan, pool evidence on Bukti Gudang Bersama and the open conditions of recovered money, a refund with an unbacked remainder and an invoice outstanding after a payment, with no decision control (UXS-40; QS-14; QS-21; criterion 5)', async ({ page }, testInfo) => {
    const captured = watch(page);
    await page.setViewportSize(DESK);
    await ownerSignedIn(page, `cj13-kondisi-${String(Date.now())}`);

    // QS-21: a reference-less client order of the same issuer, type and date as a registered one, recorded once the match is
    // ticked with a reason (IP-05; CJ-20's path).
    const twin = await evidenceCompany(page, true);
    await page.goto(`/dokumen/bukti?cabang=${twin.company}`);
    await page.getByRole('button', { name: 'Unggah', exact: true }).filter({ visible: true }).first().click();
    const sheet = page.getByRole('dialog', { name: 'Unggah Dokumen' });
    await sheet.getByLabel('Unggah', { exact: true }).setInputFiles({ name: 'sp-kedua.pdf', mimeType: 'application/pdf', buffer: uniquePdf() });
    await expect(sheet.getByText('sp-kedua.pdf')).toBeVisible();
    await sheet.getByLabel('Jenis bukti').selectOption({ label: 'Surat pesanan klien' });
    await sheet.getByLabel(/^Penerbit\b/).fill(twin.issuer);
    await sheet.getByLabel('Tanggal dokumen').fill('2026-10-01');
    await sheet.getByRole('button', { name: 'Daftarkan Bukti' }).click();
    const warning = sheet.locator('[data-slot="duplicate-warning"]');
    await expect(warning).toContainText('Kemungkinan catatan ganda.');
    await warning.getByRole('checkbox', { name: 'Ini catatan yang berbeda' }).click();
    await warning.getByRole('textbox').fill('Surat pesanan kedua dari sekolah yang sama hari itu');
    await sheet.getByRole('button', { name: 'Daftarkan Bukti' }).click();
    await expect(page.getByRole('dialog', { name: 'Surat pesanan klien' })).toBeVisible();

    // Pool evidence the Owner recorded and an account of the run carrying an OWNER_ONLY capability in a user grant.
    const sources = await reviewSources(page);

    // TASK-049's invoices of Rp1,000,000: one billed and paid 600,000 — outstanding after a payment —, one billed, written off for
    // 500,000 and Rp200,000 recovered for it afterwards (TASK-050).
    const paid = await numberingCompany(page, YEAR_START);
    const paidRun = await fixture<{ code: string }>(page, '/__fixtures/invoice', { company: paid, stage: 'issued', lines: 1, billed: true, bank: true, paid: '600000', apply: true });
    const recovered = await numberingCompany(page, YEAR_START);
    const recoveredRun = await fixture<{ code: string }>(page, '/__fixtures/invoice', { company: recovered, stage: 'issued', lines: 1, billed: true, written_off: '500000', recovery: '200000' });

    // TASK-053's refund (AX-23; L-46; C-44): a Rp200,000 payment with its proof, unapplied, refunded in full from the company's
    // account with a transfer proof, then Rp100,000 of it contra'd as wrongly recorded (TASK-051) — the Rp100,000 of the refund it
    // no longer backs an unbacked remainder on the case the contra opens.
    const refunded = await numberingCompany(page, YEAR_START);
    const refundedRun = await fixture<{ code: string }>(page, '/__fixtures/invoice', { company: refunded, stage: 'issued', lines: 1, bank: true, paid: '200000', refund: '200000', unbacked: '100000' });

    // *Kondisi yang Masih Terbuka*: each condition with its company and a link to its invoice — the refund's to its case.
    await page.goto('/tinjauan');
    const conditions = page.getByRole('table', { name: 'Kondisi yang Masih Terbuka' });
    await expect(conditions).toBeVisible();
    const outstanding = conditions.getByRole('row').filter({ hasText: 'Invoice masih bersisa setelah pembayaran' }).filter({ hasText: paidRun.code });
    await expect(outstanding).toHaveCount(1);
    await expect(outstanding.getByRole('link', { name: 'Buka Catatan' })).toHaveAttribute('href', /\/keuangan\/invoice\/[0-9a-f-]{36}$/);
    const recovery = conditions.getByRole('row').filter({ hasText: 'Uang pulihan dari invoice yang dihapusbukukan belum dipakai' }).filter({ hasText: recoveredRun.code });
    await expect(recovery).toHaveCount(1);
    await expect(recovery.getByRole('link', { name: 'Buka Catatan' })).toHaveAttribute('href', /\/keuangan\/invoice\/[0-9a-f-]{36}$/);
    const unbacked = conditions.getByRole('row').filter({ hasText: 'Pengembalian dana dengan sisa tanpa dasar' }).filter({ hasText: refundedRun.code });
    await expect(unbacked).toHaveCount(1);
    await expect(unbacked.getByRole('link', { name: 'Buka Catatan' })).toHaveAttribute('href', /\/dokumen\/kasus\?kasus=[0-9a-f-]{36}$/);
    await noDecisionControl(page);

    // *Aktivitas*: the acknowledged warning (QS-21) and the write-off (OWNER_ONLY), by the Owner.
    await review(page, `pelaku=${sources.owner}`, ['Tetap menyimpan bukti yang mirip bukti lain', twin.code, 'Menghapuskan piutang', recoveredRun.code]);
    await noDecisionControl(page);

    // *Keamanan*: the AUTHORIZATION_ANOMALY of the account of the run, with its reason code.
    await review(page, `tab=keamanan&pelaku=${sources.anomaly}`, ['Kejanggalan akses', sources.anomaly_name]);
    await expect(page.locator('[data-slot="review-items"] li').filter({ hasText: 'Kejanggalan akses' }).first()).toContainText('OWNER_ONLY_IN_USER_GRANT');
    await noDecisionControl(page);

    // *Bukti Gudang Bersama*: the pool evidence, labelled *Gudang bersama*, its issuer, linked to its record.
    await review(page, `tab=bukti&pelaku=${sources.owner}`, [sources.issuer]);
    const pool = page.locator('[data-slot="review-items"] li').filter({ hasText: sources.issuer }).first();
    await expect(pool).toContainText('Catatan gudang');
    await expect(pool).toContainText('Gudang bersama');
    await expect(pool.getByRole('link', { name: 'Buka Catatan' })).toBeVisible();
    await noDecisionControl(page);

    await guardsClean(page, testInfo, [twin.company, paid, recovered, refunded]);
    await clean(captured, testInfo);
});

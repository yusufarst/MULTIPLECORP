import { expect as base, type Page, test } from '@playwright/test';

import { completionRun } from '../completion-fixtures';
import { cancellationRun, expectStock, onProject } from '../journey-b3-fixtures';
import { clean, guardsClean, watch } from '../journey-fixtures';
import { invoiceRun } from '../invoice-fixtures';

// TL-09 (TASK-063; CJ-17; J-PRJ-03; J-PRJ-04; UXS-37; AC-21; BR-PRJ-02; BR-PRJ-04; criteria 5, 7, 8): at the desk, as the Owner, through the real
// pages — PG-012 reached from PG-011's *Tindakan Lain* (NAV-012) refusing "Selesaikan Proyek" with the unmet conditions named (C-39), the open
// remainder cancelled on PG-011 with its reason (AX-14), then the ten conditions met and the project completed (AX-25); a project with its
// fulfilment open and an issued invoice outstanding force-completed with the snapshot shown, the reason given, a change made meanwhile in
// another tab answered as CONFLICT and shown again (ST-04), and the record keeping the reason, the unmet conditions and the residual
// obligations — the receivable still open on PG-043 (AX-26; C-38) —; and a project with reserved stock cancelled from PG-011 with its reason,
// its reservations released by the same action as PG-019 shows (AX-27). Each test watches its console and network from its first step and
// ends with ReconcileGuards reporting no discrepancy for its company (TS-11; TEST_STRATEGY §8, §16).

// Each test crosses PG-011, PG-012 and its dialogs several times, PG-011 settling its three deferred groups on each visit.
test.describe.configure({ timeout: 300_000 });

// The project and completion steps wait as completion.spec, cancellation.spec and reservations.spec do.
const expect = base.configure({ timeout: 60_000 });

const DESK = { width: 1280, height: 900 };

function stamp(): string {
    return `${String(Date.now())}-${String(Math.floor(Math.random() * 1000))}`;
}

/** PG-012 from PG-011's *Tindakan Lain* (NAV-012), its ten conditions shown. */
async function toCompletion(page: Page, project: string): Promise<void> {
    await onProject(page, project);
    await page.getByRole('button', { name: 'Tindakan Lain' }).filter({ visible: true }).first().click();
    await page.getByRole('menuitem', { name: 'Penyelesaian Proyek' }).click();
    await page.waitForURL(`**/proyek/${project}/penyelesaian`);
    await expect(page.getByRole('heading', { name: 'Penyelesaian Proyek', level: 1 })).toBeVisible();
    await expect(page.locator('[data-slot="completion-condition"]')).toHaveCount(10);
}

const conditionsUnmet = (page: Page) => page.locator('[data-slot="completion-condition"][data-met="false"]');

/** A condition's row by its name, as PG-012 states it (journey.pred.*). */
const condition = (page: Page, name: string) => page.locator('[data-slot="completion-condition"]').filter({ has: page.locator('span.font-semibold', { hasText: new RegExp(`^${name}$`) }) });

/** The unmet conditions as the Owner's Force Complete section names them — the reading AX-25 and AX-26 make (ProjectCompletion::read). */
async function unmetNamed(page: Page): Promise<string> {
    const line = page.locator('[data-slot="completion-force"] p').filter({ hasText: /^Syarat yang belum terpenuhi: / });
    await expect(line).toHaveCount(1);

    return ((await line.textContent()) ?? '').replace(/^Syarat yang belum terpenuhi: /, '');
}

test('CJ-17: refuses completion with an unmet condition, then completes once the remainder is cancelled and all ten hold (J-PRJ-03; AX-25; C-39; criterion 5)', async ({ page }, testInfo) => {
    const captured = watch(page);
    await page.setViewportSize(DESK);
    const run = await completionRun(page, `cj17-selesai-${stamp()}`, false);
    const content = page.locator('#konten');

    // PG-012 with the service line's ten still to hand over: *Pemenuhan* unmet, what is missing said, where it is resolved linked (J-PRJ-03).
    await toCompletion(page, run.project);
    const fulfilment = condition(page, 'Pemenuhan');
    await expect(fulfilment).toHaveAttribute('data-met', 'false');
    await expect(fulfilment).toContainText('Belum terpenuhi');
    await expect(fulfilment.getByRole('link', { name: 'Selesaikan di halamannya' })).toHaveAttribute('href', `/proyek/${run.project}`);
    const unmet = await unmetNamed(page);
    expect(unmet.split(', ')).toContain('Pemenuhan');
    await expect(page.getByText('Semua syarat terpenuhi. Proyek tetap Aktif sampai Anda menyelesaikannya.')).toHaveCount(0);

    // "Selesaikan Proyek" is refused with the unmet conditions named, nothing written (C-39; MSG-39's confirmation first).
    await page.getByRole('button', { name: 'Selesaikan Proyek', exact: true }).click();
    const refuse = page.getByRole('alertdialog');
    await expect(refuse).toContainText('Syarat dibaca ulang saat disimpan.');
    await refuse.getByRole('button', { name: 'Selesaikan Proyek', exact: true }).click();
    await expect(refuse).toContainText(`Belum dapat diselesaikan. Syarat belum terpenuhi: ${unmet}.`);
    await refuse.getByRole('button', { name: 'Kembali', exact: true }).click();
    await expect(refuse).toBeHidden();
    await expect(page.locator('[data-slot="completion-record"]')).toHaveCount(0);
    await expect(fulfilment).toHaveAttribute('data-met', 'false');

    // Resolved where PG-012 points: "Batalkan Sisa" on PG-011's line, the remainder of ten with its reason (J-PRJ-04's AX-14).
    await onProject(page, run.project);
    const line = page.getByRole('list', { name: 'Baris terkonfirmasi' }).locator('[data-line="1"]');
    await line.getByRole('button', { name: 'Batalkan Sisa' }).click();
    const cancel = page.getByRole('alertdialog');
    await expect(cancel).toContainText('Batalkan sisa baris 1?');
    await cancel.getByLabel(/^Jumlah dibatalkan/).fill('10');
    await cancel.getByLabel('Alasan').fill('Klien menunda sisa pekerjaan ke tahun depan');
    await cancel.getByRole('button', { name: 'Batalkan Sisa' }).click();
    await expect(content).toContainText('Sisa baris dibatalkan.');

    // The ten conditions hold: "Selesaikan Proyek" completes the project, no Force Complete offered (L-12; AX-25).
    await toCompletion(page, run.project);
    await expect(conditionsUnmet(page)).toHaveCount(0);
    await expect(condition(page, 'Pemenuhan')).toHaveAttribute('data-met', 'true');
    await expect(page.getByText('Semua syarat terpenuhi. Proyek tetap Aktif sampai Anda menyelesaikannya.')).toBeVisible();
    await expect(page.locator('[data-slot="completion-force"]')).toHaveCount(0);
    await page.getByRole('button', { name: 'Selesaikan Proyek', exact: true }).click();
    await page.getByRole('alertdialog').getByRole('button', { name: 'Selesaikan Proyek', exact: true }).click();
    await page.waitForURL(`**/proyek/${run.project}`);
    await expect(content).toContainText('Proyek diselesaikan.');
    await expect(content).toContainText('Proyek ini sudah ditutup; hanya tindakan lanjutan yang tersedia.');

    // The record on PG-012: completed normally, no reason asked, nothing left to complete (UXI-40).
    await content.getByRole('link', { name: 'Lihat catatan penyelesaian' }).click();
    await page.waitForURL(`**/proyek/${run.project}/penyelesaian`);
    const record = page.locator('[data-slot="completion-record"]');
    await expect(record.getByRole('heading', { name: 'Diselesaikan', exact: true })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Selesaikan Proyek', exact: true })).toHaveCount(0);

    await guardsClean(page, testInfo, [run.company]);
    await clean(captured, testInfo);
});

test('CJ-17 / UXS-37: the Owner force-completes with the snapshot, a change meanwhile answered as CONFLICT and shown again, the reason and the residual obligations kept (J-PRJ-03; AX-26; ST-04; C-38; criterion 5)', async ({ page }, testInfo) => {
    const captured = watch(page);
    await page.setViewportSize(DESK);
    const run = await invoiceRun(page, `cj17-paksa-${stamp()}`, { stage: 'issued' });
    const content = page.locator('#konten');
    const reason = 'Klien menutup kerja sama; sisa layanan tidak dilanjutkan';
    const added = `Berita acara uji fungsi ${stamp()}`;
    const year = String(new Date().getFullYear());
    const invoiceNumber = new RegExp(`UJI/INV/${year}/\\d{4}`);

    // PG-012's Force Complete section, the Owner's only: the statement, the unmet conditions and the residual obligations as a snapshot —
    // the issued invoice of Rp1,000,000 outstanding (InvoiceFixture's issued stage) — (UXS-37; OD-12).
    await toCompletion(page, run.project);
    const force = page.locator('[data-slot="completion-force"]');
    await expect(force.getByRole('heading', { name: 'Selesaikan Paksa' })).toBeVisible();
    await expect(force).toContainText('Tidak ada yang dibuat-buat: setiap kewajiban tetap tercatat dan berjalan');
    const unmet = await unmetNamed(page);
    expect(unmet.split(', ')).toContain('Pemenuhan');
    const obligations = force.locator('[data-slot="completion-obligations"]');
    const receivable = obligations.getByRole('listitem').filter({ hasText: 'Piutang terbuka' });
    await expect(receivable).toHaveCount(1);
    await expect(receivable).toContainText(invoiceNumber);
    await expect(receivable).toContainText('Rp1.000.000');
    await expect(force).not.toContainText(added);

    // The dialog bound to that list, the reason given (MSG-40).
    await force.getByRole('button', { name: 'Selesaikan Paksa' }).click();
    const dialog = page.getByRole('alertdialog');
    await expect(dialog).toContainText(`Proyek diselesaikan paksa dengan syarat belum terpenuhi (${unmet}) dan kewajiban sisa seperti tercantum.`);
    await expect(dialog.getByRole('button', { name: 'Selesaikan Paksa' })).toBeDisabled();
    await dialog.getByLabel('Alasan').fill(reason);

    // Meanwhile, in another tab of the same session, a required document is added to the project's checklist (J-ADM-01): the residual
    // obligations change under the open dialog.
    const tab = await page.context().newPage();
    const second = watch(tab);
    await tab.setViewportSize(DESK);
    await onProject(tab, run.project);
    await tab.getByRole('button', { name: 'Tambah Persyaratan' }).click();
    const add = tab.getByRole('alertdialog');
    await add.getByLabel(/^Jenis persyaratan/).selectOption('EXTERNAL_DOCUMENT');
    await add.getByLabel(/^Jenis dokumen pihak luar/).selectOption('CLIENT_SPK');
    await add.getByLabel(/^Uraian/).fill(added);
    await add.getByLabel(/^Cara memenuhi/).selectOption('UPLOADED');
    await add.getByRole('button', { name: 'Tambah Persyaratan' }).click();
    await expect(tab.locator('#konten').getByText('Persyaratan ditambahkan.')).toBeVisible();
    await tab.close();
    captured.console.push(...second.console);
    captured.failed.push(...second.failed);
    captured.csp.push(...second.csp);
    captured.requests += second.requests;

    // The confirmation of the stale snapshot answers CONFLICT, the current snapshot shown again with the added document, the reason kept (ST-04).
    await dialog.getByRole('button', { name: 'Selesaikan Paksa' }).click();
    await expect(dialog).toContainText('Kondisi proyek berubah sejak halaman dibuka. Periksa ringkasan terbaru, lalu konfirmasi lagi.');
    const missing = obligations.getByRole('listitem').filter({ hasText: added });
    await expect(missing).toHaveCount(1);
    await expect(missing).toContainText('Dokumen kurang');
    await expect(dialog.getByLabel('Alasan')).toHaveValue(reason);
    await expect(page.locator('[data-slot="completion-record"]')).toHaveCount(0);
    // The conditions as the current snapshot names them — the list the second confirmation is bound to.
    const confirmed = await unmetNamed(page);
    expect(confirmed.split(', ')).toContain('Pemenuhan');
    await expect(dialog).toContainText(`Proyek diselesaikan paksa dengan syarat belum terpenuhi (${confirmed}) dan kewajiban sisa seperti tercantum.`);

    // Confirmed again on the list shown: force-completed, the obligations still running (AX-26; BR-PRJ-04).
    await dialog.getByRole('button', { name: 'Selesaikan Paksa' }).click();
    await page.waitForURL(`**/proyek/${run.project}`);
    await expect(content).toContainText('Proyek diselesaikan paksa. Kewajiban sisa tetap berjalan.');
    await expect(content).toContainText('Proyek ini sudah ditutup; hanya tindakan lanjutan yang tersedia.');

    // The record on PG-012 keeps the reason, the unmet conditions and the residual obligations of the snapshot confirmed (C-38; AC-21).
    await content.getByRole('link', { name: 'Lihat catatan penyelesaian' }).click();
    await page.waitForURL(`**/proyek/${run.project}/penyelesaian`);
    const record = page.locator('[data-slot="completion-record"]');
    await expect(record.getByRole('heading', { name: 'Diselesaikan paksa', exact: true })).toBeVisible();
    await expect(record).toContainText(`Alasan: ${reason}`);
    await expect(record).toContainText(`Syarat yang belum terpenuhi: ${confirmed}`);
    const kept = record.locator('[data-slot="completion-obligations"]').getByRole('listitem');
    await expect(kept.filter({ hasText: 'Piutang terbuka' })).toContainText(invoiceNumber);
    await expect(kept.filter({ hasText: 'Piutang terbuka' })).toContainText('Rp1.000.000');
    await expect(kept.filter({ hasText: added })).toContainText('Dokumen kurang');
    await expect(page.locator('[data-slot="completion-force"]')).toHaveCount(0);
    await expect(page.getByRole('button', { name: 'Selesaikan Proyek', exact: true })).toHaveCount(0);

    // Nothing fabricated: the receivable stays open and collectable on PG-043, its outstanding unchanged (BR-PRJ-04).
    await page.goto(`/keuangan/invoice/${run.invoice ?? ''}`);
    const outstanding = page.getByTestId('position').locator('div').filter({ has: page.locator('dt', { hasText: 'Sisa tagihan' }) }).locator('dd');
    await expect(outstanding).toHaveText('Rp1.000.000');

    await guardsClean(page, testInfo, [run.company]);
    await clean(captured, testInfo);
});

test('CJ-17: cancels a project from PG-011 with its reason, its reservations released by the same action (J-PRJ-04; AX-27; criterion 5)', async ({ page }, testInfo) => {
    const captured = watch(page);
    await page.setViewportSize(DESK);
    // TASK-038's world: 8 usable units, 5 of them reserved for the project's line of 10.
    const run = await cancellationRun(page, `cj17-batal-${stamp()}`, '8', '5');
    const content = page.locator('#konten');
    const reason = 'Sekolah membatalkan pengadaan tahun ini';

    await expectStock(page, run.product, ['8', '5', '0', '3']);

    // "Batalkan Proyek" from *Tindakan Lain*: the dialog lists what must be corrected first, says the reservations are released, takes the reason.
    await onProject(page, run.project);
    await page.getByRole('button', { name: 'Tindakan Lain' }).filter({ visible: true }).first().click();
    await page.getByRole('menuitem', { name: 'Batalkan Proyek' }).click();
    const dialog = page.getByRole('alertdialog');
    await expect(dialog).toContainText('Semua reservasi aktifnya dilepas sekarang');
    const companions = dialog.locator('[data-slot="cancel-companions"]');
    await expect(companions).toContainText('Koreksi pendamping untuk apa yang benar-benar dibatalkan');
    await expect(companions.locator('[data-region-state="loading"]')).toHaveCount(0);
    await expect(companions).toContainText('Fakta yang tetap benar tetap tercatat');
    await dialog.getByLabel('Alasan').fill(reason);
    await dialog.getByRole('button', { name: 'Batalkan Proyek' }).click();
    await expect(content).toContainText('Proyek dibatalkan. Reservasinya dilepas.');
    await expect(content).toContainText('Proyek ini sudah ditutup; hanya tindakan lanjutan yang tersedia.');
    await expect(page.locator('[data-slot="cancelled-record"]')).toContainText(`Alasan: ${reason}`);

    // The five reserved units are free again: on hand unchanged, nothing reserved, all eight available (AX-27; BR-INV-01).
    await expectStock(page, run.product, ['8', '0', '0', '8']);

    await guardsClean(page, testInfo, [run.company]);
    await clean(captured, testInfo);
});

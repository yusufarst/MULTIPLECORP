import { expect as base, type Locator, type Page, test } from '@playwright/test';

import { administrationRun } from '../administration-fixtures';
import { dropFile, uniquePdf, uniquePhoto } from '../evidence-fixtures';
import { onProject } from '../journey-b3-fixtures';
import { clean, guardsClean, watch } from '../journey-fixtures';

// TL-09 (TASK-063; CJ-20; J-ADM-01; J-DOC-02; UXS-17; AC-09; GAP-053; BR-ADM-01; criteria 6, 7, 8): one continuous journey of the Owner on
// a confirmed project's *Dokumen Administrasi* — TASK-035's checklist with the default invoice item, the company's SPK to be generated and
// the school's order letter to be uploaded —: at the desk the company SPK drafted from PG-011 and its requirement left *Disiapkan*, the
// draft neither satisfying it nor offered as its link (GAP-053; BR-ADM-01); the order letter dropped on PG-038's upload field, registered and
// linked to its requirement, which turns *Terpenuhi* (IP-16; NX-07; NX-14); then on the phone at 390 px the checklist read, a second order
// letter of the same issuer, type and date photographed and registered, meeting the in-scope likely-duplicate warning with the matching
// evidence linked, recorded once the difference is ticked and a reason given (UXS-17; IP-05; MSG-15). Its console and network captured and
// ReconcileGuards clean for its company at its end (TS-11; TEST_STRATEGY §8, §16).

// Eleven dialog and upload steps across PG-011, PG-036 and PG-038 at two widths, PG-011 settling its deferred groups on each visit.
test.describe.configure({ timeout: 300_000 });

// The checklist and upload steps wait as administration.spec and evidence.spec do.
const expect = base.configure({ timeout: 60_000 });

const DESK = { width: 1280, height: 900 };
const PHONE = { width: 390, height: 844 };
// The letters' date, past for every run; a reference-less letter of one issuer, type and date is BD-14's likely duplicate in its company.
const LETTER_DATE = '2026-10-01';
const LETTER = 'Surat pesanan klien';

const checklist = (page: Page): Locator => page.getByRole('list', { name: 'Persyaratan administrasi' });
const requirement = (page: Page, description: string): Locator => checklist(page).getByRole('listitem').filter({ hasText: description });

/** PG-038's upload sheet of the run's company, opened from its primary action (IP-16). */
async function openUpload(page: Page, company: string): Promise<Locator> {
    await page.goto(`/dokumen/bukti?cabang=${company}`);
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Bukti');
    await page.getByRole('button', { name: 'Unggah', exact: true }).filter({ visible: true }).first().click();
    const sheet = page.getByRole('dialog', { name: 'Unggah Dokumen' });
    await expect(sheet).toBeVisible();

    return sheet;
}

/** The letter's identity: a client order of the issuer, without the issuer's reference, on the letters' date. */
async function identify(sheet: Locator, issuer: string): Promise<void> {
    await sheet.getByLabel('Jenis bukti').selectOption({ label: LETTER });
    // A required field's name carries its marking, "Penerbit (wajib)"; the reference's starts "Nomor referensi penerbit".
    await sheet.getByLabel(/^Penerbit\b/).fill(issuer);
    await sheet.getByLabel('Tanggal dokumen').fill(LETTER_DATE);
}

test('CJ-20: the checklist with a draft that never satisfies, the order letter uploaded at the desk and linked, a second one photographed on the phone meeting the in-scope duplicate warning (J-ADM-01; J-DOC-02; UXS-17; criterion 6)', async ({ page }, testInfo) => {
    const captured = watch(page);
    await page.setViewportSize(DESK);
    const run = await administrationRun(page, `cj20-${String(Date.now())}`);
    const content = page.locator('#konten');
    const issuer = `Sekolah Pemesan ${run.code}`;
    const spk = (): Locator => requirement(page, 'SPK perusahaan untuk sekolah');
    const letter = (): Locator => requirement(page, 'Surat pesanan dari sekolah');

    // PG-011's *Dokumen Administrasi*: the company SPK to be generated and the school's order letter to be uploaded, both missing (J-ADM-01).
    await onProject(page, run.project);
    await expect(spk()).toContainText('Belum Ada');
    await expect(spk()).toContainText('Belum ada dokumen terbit dengan berkas siap.');
    await expect(letter()).toContainText('Belum Ada');
    await expect(letter()).toContainText('Belum ada unggahan yang sah.');
    await expect(letter().getByRole('link', { name: 'Unggah bukti' })).toHaveAttribute('href', '/dokumen/bukti');

    // The company SPK drafted from PG-011 (WF-PRJ-02's optional output; ROUTE-221), opened on PG-036 as a draft without a number (J-DOC-01).
    await page.getByRole('button', { name: 'Buat SPK Perusahaan' }).click();
    await page.waitForURL(/\/dokumen\/[0-9a-f-]{36}$/);
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('SPK');
    await expect(page.getByRole('region', { name: 'Pratinjau SPK, draf belum terbit' })).toContainText('DRAF — belum terbit');

    // GAP-053; BR-ADM-01: the draft prepares the requirement and never satisfies it, nor is it offered as what satisfies it.
    await onProject(page, run.project);
    await expect(spk()).toContainText('Disiapkan');
    await expect(spk()).toContainText('Draf sudah disiapkan. Draf belum memenuhi persyaratan.');
    await expect(spk()).not.toContainText('Terpenuhi');
    await spk().getByRole('button', { name: 'Tautkan Dokumen' }).click();
    const none = page.getByRole('alertdialog');
    await expect(none).toContainText('Draf tidak pernah memenuhi persyaratan.');
    await expect(none).toContainText('Belum ada dokumen terbit atau bukti yang dapat ditautkan.');
    await expect(none.getByRole('combobox')).toHaveCount(0);
    await none.getByRole('button', { name: 'Kembali', exact: true }).click();
    await expect(none).toBeHidden();

    // At the desk the order letter is dropped on PG-038's upload field — drag and drop beside the picker —, registered as version 1 (IP-16; NX-07).
    const desk = await openUpload(page, run.company);
    await dropFile(page, desk.locator('[data-slot="upload"] .border-dashed'), 'surat-pesanan.pdf', 'application/pdf', uniquePdf());
    await expect(desk.getByText('surat-pesanan.pdf')).toBeVisible();
    await identify(desk, issuer);
    await desk.getByRole('button', { name: 'Daftarkan Bukti' }).click();
    const registered = page.getByRole('dialog', { name: LETTER });
    await expect(registered).toBeVisible();
    await expect(registered.getByText('Versi 1')).toBeVisible();
    await expect(registered.getByText('Berlaku', { exact: true })).toBeVisible();
    await expect(registered.locator('dd').filter({ hasText: issuer })).toBeVisible();

    // Linked to the letter's requirement from the checklist, the upload offered by its type and issuer (J-ADM-01; NX-14): *Terpenuhi*.
    await onProject(page, run.project);
    await letter().getByRole('button', { name: 'Tautkan Dokumen' }).click();
    const link = page.getByRole('alertdialog');
    const choice = `${LETTER} · ${issuer}`;
    await expect(link.getByRole('combobox')).toContainText(choice);
    await link.getByRole('combobox').selectOption({ label: choice });
    await link.getByRole('button', { name: 'Tautkan Dokumen' }).click();
    await expect(content.getByText('Dokumen ditautkan.')).toBeVisible();
    await expect(letter()).toContainText('Terpenuhi');
    const linked = letter().getByRole('link', { name: choice });
    await expect(linked).toHaveAttribute('href', /^\/dokumen\/bukti\?bukti=[0-9a-z-]+$/);
    const evidence = ((await linked.getAttribute('href')) ?? '').replace('/dokumen/bukti?bukti=', '');
    // Still only a draft: the SPK stays prepared, never satisfied.
    await expect(spk()).toContainText('Disiapkan');

    // On the phone: what is satisfied and what is still missing, the page within the screen (J-ADM-01's phone column).
    await page.setViewportSize(PHONE);
    await onProject(page, run.project);
    await expect(letter()).toContainText('Terpenuhi');
    await expect(spk()).toContainText('Draf sudah disiapkan. Draf belum memenuhi persyaratan.');
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);

    // A second order letter of the same issuer, type and date photographed from the picker: IP-05's likely duplicate in the company in
    // scope, its match the desk's letter, linked to its own page; recorded only once the difference is ticked with a reason (UXS-17; MSG-15).
    const phone = await openUpload(page, run.company);
    const picker = phone.getByLabel('Unggah', { exact: true });
    await expect(picker).toHaveAttribute('accept', /image\/jpeg/);
    await picker.setInputFiles({ name: 'foto-surat-pesanan.png', mimeType: 'image/png', buffer: uniquePhoto() });
    await expect(phone.getByText('foto-surat-pesanan.png')).toBeVisible();
    await identify(phone, issuer);
    await phone.getByRole('button', { name: 'Daftarkan Bukti' }).click();
    const warning = phone.locator('[data-slot="duplicate-warning"]');
    await expect(warning).toContainText('Kemungkinan catatan ganda.');
    await expect(warning).toContainText('Jika ini memang catatan berbeda, beri alasan dan lanjutkan.');
    const match = warning.getByRole('link', { name: new RegExp(`^${LETTER}`) });
    await expect(match).toHaveCount(1);
    await expect(match).toHaveAttribute('href', new RegExp(`/dokumen/bukti\\?cabang=${run.company}&bukti=${evidence}$`));
    await expect(page.getByRole('dialog', { name: LETTER })).toHaveCount(0);
    await warning.getByRole('checkbox', { name: 'Ini catatan yang berbeda' }).click();
    await warning.getByRole('textbox').fill('Surat pesanan kedua dari sekolah untuk tambahan barang');
    await phone.getByRole('button', { name: 'Daftarkan Bukti' }).click();
    const second = page.getByRole('dialog', { name: LETTER });
    await expect(second).toBeVisible();
    await expect(second.getByText('Versi 1')).toBeVisible();
    await expect(second.locator('dd').filter({ hasText: issuer })).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);

    await guardsClean(page, testInfo, [run.company]);
    await clean(captured, testInfo);
});

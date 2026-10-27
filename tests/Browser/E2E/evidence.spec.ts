import { expect, type Page, test } from '@playwright/test';

import { dropFile, evidenceCompany, uniquePdf, uniquePhoto } from '../evidence-fixtures';
import { ownerSignedIn } from '../numbering-fixtures';

// TL-09 (TASK-030; J-DOC-02; CJ-20; IP-16; IP-05; criteria 1–3): PG-038's upload and registration against the real
// routes — on the desktop a client order dropped on the field, uploaded with its progress and registered, its sheet
// showing version 1 current; on the phone a photo from the picker, which may open the camera, registered as a delivery
// proof; and a reference-less client order of the same issuer, type and date meeting IP-05's warning, recorded only once
// the match is ticked and a reason given.

async function openUpload(page: Page): Promise<void> {
    await page.getByRole('button', { name: 'Unggah', exact: true }).filter({ visible: true }).first().click();
    await expect(page.getByRole('dialog', { name: 'Unggah Dokumen' })).toBeVisible();
}

async function identify(page: Page, type: string, issuer: string, reference: string | null): Promise<void> {
    const sheet = page.getByRole('dialog', { name: 'Unggah Dokumen' });
    await sheet.getByLabel('Jenis bukti').selectOption({ label: type });
    await sheet.getByLabel('Penerbit').fill(issuer);

    if (reference !== null) {
        await sheet.getByLabel('Nomor referensi penerbit').fill(reference);
    }

    await sheet.getByLabel('Tanggal dokumen').fill('2026-10-01');
}

test('registers a client order dropped on the desktop\'s field, its sheet showing version 1 current (J-DOC-02; IP-16)', async ({ page }) => {
    await ownerSignedIn(page, `bukti-desktop-${String(Date.now())}`);
    const { company } = await evidenceCompany(page);
    await page.goto(`/dokumen/bukti?cabang=${company}`);
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Bukti');
    await expect(page.getByText('Belum ada bukti di cabang ini.')).toBeVisible();

    await openUpload(page);
    const sheet = page.getByRole('dialog', { name: 'Unggah Dokumen' });
    await dropFile(page, sheet.locator('[data-slot="upload"] .border-dashed'), 'sp-klien.pdf', 'application/pdf', uniquePdf());
    await expect(sheet.getByText('sp-klien.pdf')).toBeVisible();
    await identify(page, 'Surat pesanan klien', 'SMA Negeri 9 Uji', `SP/${String(Date.now())}`);
    await sheet.getByRole('button', { name: 'Daftarkan Bukti' }).click();

    const evidence = page.getByRole('dialog', { name: 'Surat pesanan klien' });
    await expect(evidence).toBeVisible();
    await expect(evidence.getByText('Versi 1')).toBeVisible();
    await expect(evidence.getByText('Berlaku', { exact: true })).toBeVisible();
    await expect(evidence.getByRole('link', { name: 'Unduh' })).toHaveAttribute('href', /\/berkas\/[0-9a-f-]{36}\/unduh$/);
});

test('registers a photo taken on the phone as a delivery proof (J-DOC-02; IP-16 step 1)', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await ownerSignedIn(page, `bukti-ponsel-${String(Date.now())}`);
    const { company } = await evidenceCompany(page);
    await page.goto(`/dokumen/bukti?cabang=${company}`);

    await openUpload(page);
    const sheet = page.getByRole('dialog', { name: 'Unggah Dokumen' });
    const picker = sheet.getByLabel('Unggah', { exact: true });
    await expect(picker).toHaveAttribute('accept', /image\/jpeg/);
    await picker.setInputFiles({ name: 'foto-terima.png', mimeType: 'image/png', buffer: uniquePhoto() });
    await expect(sheet.getByText('foto-terima.png')).toBeVisible();
    await identify(page, 'Bukti kirim', 'Penerima Gudang Uji', null);
    await sheet.getByRole('button', { name: 'Daftarkan Bukti' }).click();

    await expect(page.getByRole('dialog', { name: 'Bukti kirim' })).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
});

test('warns of a reference-less likely duplicate and records it once the match is ticked with a reason (CJ-20; IP-05)', async ({ page }) => {
    await ownerSignedIn(page, `bukti-ganda-${String(Date.now())}`);
    const { company, issuer } = await evidenceCompany(page, true);
    await page.goto(`/dokumen/bukti?cabang=${company}`);

    await openUpload(page);
    const sheet = page.getByRole('dialog', { name: 'Unggah Dokumen' });
    await sheet.getByLabel('Unggah', { exact: true }).setInputFiles({ name: 'sp-tanpa-nomor.pdf', mimeType: 'application/pdf', buffer: uniquePdf() });
    await expect(sheet.getByText('sp-tanpa-nomor.pdf')).toBeVisible();
    await identify(page, 'Surat pesanan klien', issuer, null);
    await sheet.getByRole('button', { name: 'Daftarkan Bukti' }).click();

    const warning = sheet.locator('[data-slot="duplicate-warning"]');
    await expect(warning).toContainText('Kemungkinan catatan ganda.');
    await expect(warning.getByRole('link', { name: /Surat pesanan klien/ })).toBeVisible();
    await warning.getByRole('checkbox', { name: 'Ini catatan yang berbeda' }).click();
    await warning.getByRole('textbox').fill('Surat pesanan kedua dari sekolah yang sama hari itu');
    await sheet.getByRole('button', { name: 'Daftarkan Bukti' }).click();

    await expect(page.getByRole('dialog', { name: 'Surat pesanan klien' })).toBeVisible();
    await expect(page.getByRole('dialog', { name: 'Surat pesanan klien' }).getByText(issuer)).toBeVisible();
});

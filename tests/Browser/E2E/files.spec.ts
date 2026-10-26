import { readFile } from 'node:fs/promises';

import { expect, test } from '@playwright/test';

import { issuedDocument, SAMPLE_PDF } from '../files-fixtures';
import { ownerSignedIn } from '../numbering-fixtures';

// TL-09 (TASK-022; INT-013, INT-014, INT-226, INT-285; IP-12, IP-16; criterion 1): an upload and a download on the
// sample page against the real pipeline and ROUTE-226 — the file streamed with its progress, stored, listed and
// downloaded with its own bytes —; a file outside the purpose refused with MSG-31 and nothing stored; an issued
// document's rendition state read by ROUTE-285, *PDF sedang dibuat* while owed and *PDF siap* with "Unduh" once ready.

test('uploads a file through the pipeline and downloads it by its record (INT-013; INT-014; IP-16)', async ({ page }) => {
    await ownerSignedIn(page, `berkas-${String(Date.now())}`);
    await page.goto('/__fixtures/berkas');
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Bukti');

    await page.getByLabel('Bukti').setInputFiles({ name: 'surat-pesanan.pdf', mimeType: 'application/pdf', buffer: SAMPLE_PDF });
    await expect(page.getByText('Terunggah')).toBeVisible();
    const row = page.getByRole('listitem').filter({ hasText: 'surat-pesanan.pdf' });
    await expect(row).toBeVisible();

    const download = page.waitForEvent('download');
    await row.getByRole('link', { name: 'Unduh' }).click();
    const file = await download;

    expect(file.suggestedFilename()).toBe('surat-pesanan.pdf');
    expect((await readFile(await file.path())).equals(SAMPLE_PDF)).toBe(true);
});

test('refuses a file outside the purpose with MSG-31, storing nothing (FL-02; INT-013)', async ({ page }) => {
    await ownerSignedIn(page, `berkas-tolak-${String(Date.now())}`);
    await page.goto('/__fixtures/berkas');

    await page.getByLabel('Bukti').setInputFiles({ name: 'gambar.svg', mimeType: 'image/svg+xml', buffer: Buffer.from('<svg xmlns="http://www.w3.org/2000/svg"/>') });
    await expect(page.getByRole('alert')).toContainText('Berkas tidak dapat diunggah.');
    await expect(page.getByRole('listitem').filter({ hasText: 'gambar.svg' })).toHaveCount(0);
    await expect(page.getByRole('button', { name: 'Coba Lagi' })).toBeVisible();
});

test('shows an owed rendition as being made and a ready one with its download (IP-12; INT-285)', async ({ page }) => {
    await ownerSignedIn(page, `berkas-rendisi-${String(Date.now())}`);

    await page.goto(`/__fixtures/berkas?versi=${await issuedDocument(page, false)}`);
    await expect(page.getByText('PDF Sedang Dibuat')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Muat Ulang' })).toBeVisible();

    await page.goto(`/__fixtures/berkas?versi=${await issuedDocument(page, true)}`);
    await expect(page.getByText('PDF Siap')).toBeVisible();
    const download = page.waitForEvent('download');
    await page.locator('[data-slot="rendition-status"]').getByRole('link', { name: 'Unduh' }).click();
    const pdf = await download;

    expect((await readFile(await pdf.path())).subarray(0, 5).toString()).toBe('%PDF-');
});

import { expect, test } from '@playwright/test';

import { documentDraft, rendition } from '../document-fixtures';

// TL-09 (TASK-029; J-DOC-01; CJ-15; criteria 1, 4, 8, 11): a draft prepared from its record opened on PG-036 — the form
// beside the live preview marked "DRAF — belum terbit" without a number —, issued through its confirmation into a
// number and a pending PDF, rendered into the inline rendition with "Unduh" and "Cetak" under FL-07's inline profile,
// a failed rendition shown with MSG-08 and no re-issue; then voided with its reason, the number kept.

function stamp(): string {
    return `${String(Date.now()).slice(-6)}${String(Math.floor(Math.random() * 100))}`;
}

test('issues a prepared draft from PG-036 and shows its rendition (J-DOC-01; CJ-15)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const { id: document } = await documentDraft(page, `dokumen-${stamp()}`);
    const content = page.locator('#konten');

    await page.goto(`/dokumen/${document}`);
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('SPK');
    const paper = page.getByRole('region', { name: 'Pratinjau SPK, draf belum terbit' });
    await expect(paper).toBeVisible();
    await expect(paper).toContainText('DRAF — belum terbit');
    await expect(paper).toContainText('Nomor diberikan saat terbit');
    await expect(paper).toContainText('Laptop 14 inci');
    await expect(paper).toContainText('Rp18.315.000');
    await expect(page.locator('[data-slot="document-composer"]')).toHaveAttribute('data-band', 'desktop');

    await content.getByRole('button', { name: 'Simpan Draf' }).click();
    await expect(content.getByText('Draf tersimpan.')).toBeVisible();

    await content.getByRole('button', { name: 'Terbitkan SPK' }).click();
    const confirm = page.getByRole('alertdialog', { name: 'Terbitkan SPK?' });
    await expect(confirm).toContainText('Nomor akan ditetapkan dan tidak dapat diubah.');
    await confirm.getByRole('button', { name: 'Terbitkan SPK' }).click();
    await expect(confirm).toBeHidden();
    await expect(content.getByText('PDF sedang dibuat. Dokumen sudah terbit.')).toBeVisible();
    const year = String(new Date().getFullYear());
    await expect(page.getByRole('heading', { level: 1 })).toHaveText(new RegExp(`^SPK UJI/09/${year}/\\d{4}$`));
    await expect(content.getByText('PDF Sedang Dibuat', { exact: true })).toBeVisible();
    await expect(paper).toBeHidden();

    await rendition(page, document, 'READY');
    await content.getByRole('button', { name: 'Muat Ulang' }).click();
    await expect(content.getByText('PDF Siap', { exact: true })).toBeVisible();
    const frame = content.locator('iframe');
    await expect(frame).toHaveAttribute('src', /^\/berkas\/[0-9a-f-]{36}\/tampil$/);
    const inline = await page.request.get((await frame.getAttribute('src')) ?? '');
    expect(inline.status()).toBe(200);
    expect(inline.headers()['content-type']).toBe('application/pdf');
    expect(inline.headers()['content-disposition']).toMatch(/^inline/);
    expect(inline.headers()['content-security-policy']).toBe("default-src 'none'; frame-ancestors 'self'");
    await expect(content.getByRole('link', { name: 'Unduh' }).first()).toHaveAttribute('href', /\/unduh$/);
    await expect(content.getByRole('link', { name: 'Cetak' })).toHaveAttribute('href', /\/tampil$/);
});

test('shows a failed rendition with MSG-08 and never a re-issue, then voids the document with its reason (UXS-09; J-DOC-01)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const { id: document } = await documentDraft(page, `dokumen-gagal-${stamp()}`);
    const content = page.locator('#konten');

    await page.goto(`/dokumen/${document}`);
    await content.getByRole('button', { name: 'Terbitkan SPK' }).click();
    await page.getByRole('alertdialog', { name: 'Terbitkan SPK?' }).getByRole('button', { name: 'Terbitkan SPK' }).click();
    await expect(content.getByText('PDF sedang dibuat. Dokumen sudah terbit.')).toBeVisible();

    await rendition(page, document, 'FAILED');
    await page.reload();
    await expect(content.getByText('PDF Gagal Dibuat', { exact: true })).toBeVisible();
    await expect(content.getByText(/PDF gagal dibuat dan akan dicoba lagi otomatis/)).toBeVisible();
    await expect(content.getByRole('button', { name: /^Terbitkan/ })).toHaveCount(0);

    await content.getByRole('button', { name: 'Tindakan Lain' }).click();
    await page.getByRole('menuitem', { name: 'Batalkan Dokumen' }).click();
    const dialog = page.getByRole('alertdialog', { name: /^Batalkan SPK / });
    await expect(dialog).toContainText('Catatan bisnisnya tidak berubah.');
    await dialog.getByRole('textbox').fill('Salah pihak penerima');
    await dialog.getByRole('button', { name: 'Batalkan Dokumen' }).click();
    await expect(dialog).toBeHidden();
    await expect(content.getByText('Dokumen dibatalkan. Nomornya tidak dipakai lagi.')).toBeVisible();
    await expect(content.getByText('Dibatalkan', { exact: true })).toBeVisible();
    await expect(content.getByText(/Salah pihak penerima/)).toBeVisible();
});

test('lists the document on PG-035 and finds it by its exact number (ROUTE-035; PF-19)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const { id: document, company } = await documentDraft(page, `dokumen-daftar-${stamp()}`);
    const content = page.locator('#konten');
    await page.goto(`/dokumen/${document}`);
    await content.getByRole('button', { name: 'Terbitkan SPK' }).click();
    await page.getByRole('alertdialog', { name: 'Terbitkan SPK?' }).getByRole('button', { name: 'Terbitkan SPK' }).click();
    await expect(content.getByText('PDF sedang dibuat. Dokumen sudah terbit.')).toBeVisible();
    const number = ((await page.getByRole('heading', { level: 1 }).textContent()) ?? '').replace(/^SPK /, '');

    // The run's company is one branch of many the Owner reads; the list opens on it by its address.
    await page.goto(`/dokumen?perusahaan=${company}`);
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Dokumen');
    await page.getByLabel(/^Cari nomor dokumen/).fill(number.toLowerCase());
    await page.getByRole('button', { name: 'Cari' }).click();
    await expect(content.getByRole('cell', { name: number })).toBeVisible();
});

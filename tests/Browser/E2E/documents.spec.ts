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

test('names the period a backdated draft\'s number follows beside its "Tanggal Mundur" mark (UXS-14; PT-25; NM-05; TASK-090)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const { id: document } = await documentDraft(page, `dokumen-mundur-${stamp()}`);
    const content = page.locator('#konten');

    await page.goto(`/dokumen/${document}`);
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('SPK');
    await expect(content.getByText('Tanggal Mundur')).toHaveCount(0);
    await expect(content.getByText(/^Nomor mengikuti periode/)).toHaveCount(0);

    // The server's business day is WIB's; the first of its month is backdated, or the previous month's first on the first.
    const today = new Date(Date.now() + 7 * 3_600_000).toISOString().slice(0, 10);
    const [year = 0, month = 1] = today.split('-').map(Number);
    const sameMonth = !today.endsWith('-01');
    const day = sameMonth ? `${today.slice(0, 8)}01` : new Date(Date.UTC(year, month - 2, 1)).toISOString().slice(0, 10);

    await content.getByRole('button', { name: 'Ubah' }).first().click();

    if (!sameMonth) {
        await page.getByRole('button', { name: 'Bulan sebelumnya' }).click();
    }

    await page.locator(`[data-day="${day}"] button`).click();
    await expect(content.getByText('Tanggal Mundur')).toBeVisible();

    // The fixture numbers the type yearly from 1 January (DocumentFixture): the period is the day's year, none before it.
    if (day.slice(0, 4) === today.slice(0, 4)) {
        await expect(content.getByText(`Nomor mengikuti periode ${day.slice(0, 4)}`)).toBeVisible();
    } else {
        await expect(content.getByText(/^Nomor mengikuti periode/)).toHaveCount(0);
    }
});

test('fits the paper across the composer\'s widths without a resize-observer loop or any page error (DESIGN_SYSTEM §14.3; TEST_STRATEGY §8; TASK-092)', async ({ page }) => {
    const errors: string[] = [];
    page.on('pageerror', (error) => { errors.push(error.message); });
    await page.setViewportSize({ width: 1280, height: 900 });
    const { id: document } = await documentDraft(page, `dokumen-lebar-${stamp()}`);

    await page.goto(`/dokumen/${document}`);
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('SPK');
    const composer = page.locator('[data-slot="document-composer"]');
    const frame = page.locator('[data-slot="paper-frame"]:visible').first();
    const fitted = async () => {
        await expect(frame).toBeVisible();
        await expect.poll(() => frame.evaluate((element) => element.scrollWidth - element.clientWidth)).toBeLessThanOrEqual(1);
    };

    // Each band's composition with its own preview opened — the desktop's beside the form, the tablet's "Pratinjau" tab, the
    // phone's "Pratinjau" step in its full dialog —, the paper fitted to its frame: never wider than the frame's own width.
    for (const [width, band] of [[1280, 'desktop'], [1279, 'desktop'], [1025, 'desktop'], [1024, 'tablet'], [390, 'phone'], [1280, 'desktop']] as const) {
        await page.setViewportSize({ width, height: 900 });
        await expect(composer).toHaveAttribute('data-band', band);

        if (band === 'desktop') {
            await fitted();
        } else if (band === 'tablet') {
            await composer.getByRole('tab', { name: 'Pratinjau' }).click();
            await fitted();
        } else {
            await composer.getByRole('button', { name: 'Pratinjau', exact: true }).click();
            const preview = page.getByRole('dialog');
            await expect(preview.locator('[data-slot="paper-frame"]')).toBeVisible();
            await fitted();
            await preview.getByRole('button', { name: 'Kembali' }).click();
            await expect(preview).toHaveCount(0);
        }
    }

    expect(errors, 'no page error while the composer moves across its widths (TEST_STRATEGY §8)').toEqual([]);
});

test.describe('with the frame\'s scrollbar drawn as a classic one', () => {
    // Only this test injects a style of its own: the scrollbar a non-overlay system draws, which takes the frame's width.
    test.use({ bypassCSP: true });

    test('keeps the paper within its available width as a lower and a taller viewport make the frame\'s scrollbar come and go (TASK-092; Codex on pull request #80)', async ({ page }, testInfo) => {
        const errors: string[] = [];
        page.on('pageerror', (error) => { errors.push(error.message); });
        await page.setViewportSize({ width: 1280, height: 1600 });
        const { id: document } = await documentDraft(page, `dokumen-gulir-${stamp()}`);

        await page.goto(`/dokumen/${document}`);
        await expect(page.getByRole('heading', { level: 1 })).toHaveText('SPK');
        await page.addStyleTag({ content: '[data-slot="paper-frame"]::-webkit-scrollbar { width: 15px; height: 15px; } [data-slot="paper-frame"]::-webkit-scrollbar-thumb { background: #8a8a8a; }' });
        const composer = page.locator('[data-slot="document-composer"]');
        const frame = page.locator('[data-slot="paper-frame"]:visible').first();
        await expect(composer).toHaveAttribute('data-band', 'desktop');
        await expect(frame).toBeVisible();
        testInfo.annotations.push({ type: 'scrollbar gutter', description: `${String(await frame.evaluate((element) => element.offsetWidth - element.clientWidth - 2))} px` });

        // The frame's height follows the viewport's: tall, the paper needs no scrollbar; low, it scrolls; tall again, it does not.
        for (const [height, scrolls] of [[1600, false], [500, true], [1600, false]] as const) {
            await page.setViewportSize({ width: 1280, height });
            await expect.poll(() => frame.evaluate((element) => element.scrollHeight - element.clientHeight > 1)).toBe(scrolls);
            await expect.poll(() => frame.evaluate((element) => element.scrollWidth - element.clientWidth)).toBeLessThanOrEqual(1);
        }

        expect(errors, 'no page error while the frame\'s scrollbar comes and goes (TEST_STRATEGY §8)').toEqual([]);
    });
});

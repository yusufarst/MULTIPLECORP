import { expect, test } from '@playwright/test';

import { documentDraft } from '../document-fixtures';

// TL-11 (TASK-029; D-DS-23; DC-11; INT-012; TEST_STRATEGY §9; criterion 11): the composer at every band edge — from
// 1,025 px the form beside the live preview, never a tab, every input reachable and the paper readable through "100%"
// and "Layar penuh"; at 1,024 px "Rincian | Pratinjau"; on the phone Edit → Preview → Finalize, the preview full-screen.

for (const width of [1025, 1279, 1280]) {
    test(`sets the form beside the live preview at ${String(width)} px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        const draft = await documentDraft(page, `komposer-${String(width)}-${String(Date.now())}`);
        await page.goto(`/dokumen/${draft}`);

        const composer = page.locator('[data-slot="document-composer"]');
        await expect(composer).toHaveAttribute('data-band', 'desktop');
        await expect(page.getByRole('tab')).toHaveCount(0);
        const paper = page.getByRole('region', { name: 'Pratinjau SPK, draf belum terbit' });
        await expect(paper).toBeVisible();
        await expect(page.getByRole('button', { name: 'Simpan Draf' })).toBeInViewport();
        await expect(page.getByRole('button', { name: 'Terbitkan SPK' })).toBeVisible();
        // The preview sits on the right of the form, side by side.
        const form = await page.getByRole('button', { name: 'Simpan Draf' }).boundingBox();
        const preview = await paper.boundingBox();
        expect((preview?.x ?? 0) > (form?.x ?? 0)).toBe(true);
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);

        await page.getByRole('radio', { name: '100%' }).or(page.getByRole('button', { name: '100%' })).first().click();
        await expect(paper).toContainText('DRAF — belum terbit');
        await page.getByRole('button', { name: 'Layar penuh' }).click();
        const full = page.getByRole('dialog', { name: 'Pratinjau SPK, draf belum terbit' });
        await expect(full).toBeVisible();
        await full.getByRole('button', { name: 'Tutup' }).click();
        await expect(full).toBeHidden();
    });
}

test('offers "Rincian | Pratinjau" at 1,024 px', async ({ page }) => {
    await page.setViewportSize({ width: 1024, height: 900 });
    const draft = await documentDraft(page, `komposer-1024-${String(Date.now())}`);
    await page.goto(`/dokumen/${draft}`);

    await expect(page.locator('[data-slot="document-composer"]')).toHaveAttribute('data-band', 'tablet');
    await expect(page.getByRole('tab', { name: 'Rincian' })).toBeVisible();
    await page.getByRole('tab', { name: 'Pratinjau' }).click();
    await expect(page.getByRole('region', { name: 'Pratinjau SPK, draf belum terbit' })).toContainText('DRAF — belum terbit');
});

test('steps the phone through Edit, Preview and Finalize, the preview full-screen at 390 px (DC-11)', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    const draft = await documentDraft(page, `komposer-390-${String(Date.now())}`);
    await page.goto(`/dokumen/${draft}`);

    await expect(page.locator('[data-slot="document-composer"]')).toHaveAttribute('data-band', 'phone');
    await page.getByRole('button', { name: 'Pratinjau', exact: true }).click();
    const preview = page.getByRole('dialog', { name: 'Pratinjau SPK, draf belum terbit' });
    await expect(preview.getByRole('region', { name: 'Pratinjau SPK, draf belum terbit' })).toContainText('Nomor diberikan saat terbit');
    await preview.getByRole('button', { name: 'Lanjut' }).click();
    await expect(page.getByRole('button', { name: 'Terbitkan SPK' })).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
});

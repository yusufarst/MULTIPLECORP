import { expect as base, test } from '@playwright/test';

import { auditPage } from '../localization-audit';
import { currentLine, receivingRun } from '../receiving-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-10 (TASK-039; LOCALIZATION §3; §3.4; §6.6; DOD-16): PG-021 and PG-022 in both languages, keys of the catalogue and never hardcoded copy; a language switch keeps the unsent input; DOC-12's paper stays Indonesian.

test.describe.configure({ timeout: 240_000 });

for (const [locale, texts] of [
    ['id', { list: 'Barang Masuk', title: 'Terima Barang', record: 'Catat Barang Masuk', usable: 'Layak pakai', damaged: 'Rusak diterima', rejected: 'Ditolak', ready: 'Siap Memindai', find: 'Cari Produk', field: 'Pindai atau ketik kode', progress: '0 baris tercatat · 1 baris tersisa' }],
    ['en', { list: 'Goods receiving', title: 'Receive goods', record: 'Record goods received', usable: 'Usable', damaged: 'Damaged and accepted', rejected: 'Rejected', ready: 'Ready to scan', find: 'Find product', field: 'Scan or type a code', progress: '0 lines recorded · 1 lines left' }],
] as const) {
    test(`shows PG-021 and PG-022 in ${locale}`, async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 900 });
        const run = await receivingRun(page, `terima-${locale}-${String(Date.now())}`, locale);

        await page.goto('/gudang/barang-masuk');
        await expect(page.getByRole('heading', { name: texts.list, exact: true })).toBeVisible();
        await expect(page.locator('#konten')).toContainText(run.supplier);
        await auditPage(page, locale, 'PG-021');

        await page.goto(`/gudang/barang-masuk/${run.purchase}`);
        await expect(page.getByRole('heading', { name: texts.title, exact: true })).toBeVisible();
        const line = currentLine(page);
        await expect(line.getByRole('button', { name: texts.record })).toBeVisible();
        await expect(line.getByRole('textbox', { name: texts.usable })).toBeVisible();
        await expect(line.getByRole('textbox', { name: texts.damaged })).toBeVisible();
        await expect(line.getByRole('textbox', { name: texts.rejected })).toBeVisible();
        await expect(page.getByRole('textbox', { name: texts.field })).toBeVisible();
        await expect(page.locator('[data-slot="scan-field"]')).toContainText(texts.ready);
        await expect(page.getByRole('button', { name: texts.find })).toBeVisible();
        await expect(page.locator('[data-slot="receive-progress"]')).toHaveText(texts.progress);
        await auditPage(page, locale, 'PG-022');
    });
}

// The switch keeps unsent input in memory: audited at its own width only, a resize never coming between the input and
// its check (PG-022 is audited at the phone width above).
test('keeps the unsent input, the scan field and the line in progress across a language switch (UI/UX; LOCALIZATION §3)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await receivingRun(page, `terima-ganti-${String(Date.now())}`);
    await page.goto(`/gudang/barang-masuk/${run.purchase}`);
    await currentLine(page).getByRole('textbox', { name: 'Layak pakai' }).fill('4');
    await auditPage(page, 'id', 'PG-022 with the typed input', { phone: false });

    await page.getByRole('button', { name: /Menu akun/ }).click();
    await page.getByRole('menuitemcheckbox', { name: 'English' }).click();
    await expect(page.getByRole('heading', { name: 'Receive goods', exact: true })).toBeVisible();
    await expect(currentLine(page).getByRole('textbox', { name: 'Usable' })).toHaveValue('4');
    await auditPage(page, 'en', 'PG-022 with the typed input', { phone: false });
});

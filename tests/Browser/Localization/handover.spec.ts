import { expect, test } from '@playwright/test';

import { handoverRun } from '../handover-fixtures';

// TL-10 (TASK-047; LOCALIZATION §3, §3.4; DOD-16; criterion 4): PG-033 in both languages — keys of the catalogue, never hardcoded copy —,
// the BAST's paper in Indonesian whatever the interface language.

for (const [locale, texts] of [
    ['id', { page: 'Serah Terima Jasa', record: 'Catat Serah Terima', recorded: 'Serah terima tercatat', issue: 'Terbitkan BAST', reverse: 'Balikkan', recipient: /^Penerima/ }],
    ['en', { page: 'Service handover', record: 'Record Handover', recorded: 'Recorded handovers', issue: 'Issue BAST', reverse: 'Reverse', recipient: /^Recipient/ }],
] as const) {
    test(`shows PG-033 in ${locale}, its paper in Indonesian`, async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 900 });
        const run = await handoverRun(page, `serah-${locale}-${String(Date.now())}`, { recorded: true, locale });

        await page.goto(`/proyek/${run.project}/serah-terima`);
        await expect(page.getByRole('heading', { name: new RegExp(`${texts.page} — ${run.number}`, 'i') })).toBeVisible();
        await expect(page.getByRole('button', { name: texts.record })).toBeVisible();
        await expect(page.getByText(texts.recorded, { exact: true })).toBeVisible();
        await expect(page.getByTestId('handover').getByRole('button', { name: texts.issue })).toBeVisible();
        await expect(page.getByTestId('handover').getByRole('button', { name: texts.reverse })).toBeVisible();
        await expect(page.getByLabel(texts.recipient)).toBeVisible();

        // The paper stays Indonesian (LOCALIZATION §3.4).
        const paper = page.getByRole('region', { name: /BAST/ });
        await expect(paper).toContainText('Berita Acara Serah Terima');
        await expect(paper).toContainText('Tanggal Serah Terima');
        await expect(paper).toContainText('DRAF — belum terbit');
    });
}

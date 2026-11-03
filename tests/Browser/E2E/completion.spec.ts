import { expect as base, test } from '@playwright/test';

import { completionRun } from '../completion-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-09 (TASK-055; J-PRJ-03; UXS-37; CJ-17's completion steps; INT-161; INT-162; NAV-012): at the desk, a project whose ten conditions hold completed by the explicit decision; on the phone,
// the Owner's Force Complete of a project with its fulfilment open, the snapshot shown, the reason given, the record keeping what stays.

test.describe.configure({ timeout: 360_000 });

test('J-PRJ-03: completes a project whose ten conditions hold on PG-012 (L-12; AX-25)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await completionRun(page, `selesai-${String(Date.now())}`, true);

    await page.goto(`/proyek/${run.project}/penyelesaian`);
    await expect(page.getByRole('heading', { name: 'Penyelesaian Proyek', level: 1 })).toBeVisible();
    await expect(page.locator('[data-slot="completion-condition"][data-met="false"]')).toHaveCount(0);
    await page.getByRole('button', { name: 'Selesaikan Proyek', exact: true }).click();
    await page.getByRole('alertdialog').getByRole('button', { name: 'Selesaikan Proyek', exact: true }).click();
    await expect(page.locator('#konten')).toContainText('Proyek diselesaikan.');
});

test('UXS-37: the Owner force-completes on the phone, the snapshot shown and the reason given (AX-26; BR-PRJ-04)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await completionRun(page, `paksa-${String(Date.now())}`, false);
    await page.setViewportSize({ width: 390, height: 844 });

    await page.goto(`/proyek/${run.project}/penyelesaian`);
    const force = page.locator('[data-slot="completion-force"]');
    await expect(force).toContainText('Tidak ada yang dibuat-buat');
    await force.getByRole('button', { name: 'Selesaikan Paksa' }).click();
    const dialog = page.getByRole('alertdialog');
    await dialog.getByLabel('Alasan').fill('Klien menutup kerja sama');
    await dialog.getByRole('button', { name: 'Selesaikan Paksa' }).click();
    await expect(page.locator('#konten')).toContainText('Proyek diselesaikan paksa.');
    await page.goto(`/proyek/${run.project}/penyelesaian`);
    await expect(page.locator('[data-slot="completion-record"]')).toContainText('Diselesaikan paksa');
});

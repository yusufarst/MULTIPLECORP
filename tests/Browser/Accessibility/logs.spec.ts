import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';

import { atRest } from '../evidence-fixtures';
import { logsRun } from '../logs-fixtures';

// TL-12 (TASK-061; DESIGN_SYSTEM §12.1): PG-063 with its on-demand before and after and PG-064 with a notice's event
// pass the WCAG 2.2 AA checks at both bands, each measured at rest — table headers, the dialog, keyboard reach.

const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];

for (const width of [390, 1280]) {
    test(`finds no WCAG 2.2 AA violation on PG-063 and PG-064 at ${String(width)} px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        const run = await logsRun(page, `log-a11y-${String(width)}-${String(Date.now())}`);

        await page.goto(`/aktivitas/riwayat?perusahaan=${run.code}`);
        await expect(page.locator('#konten')).toContainText('Membuat proyek');
        await atRest(page);
        expect((await new AxeBuilder({ page }).withTags(TAGS).analyze()).violations).toEqual([]);

        if (width >= 768) {
            await page.getByRole('table', { name: 'Riwayat Aktivitas', exact: true }).locator('tbody tr').first().getByRole('button', { name: 'Tindakan baris' }).click();
            await page.getByRole('menuitem', { name: 'Lihat sebelum dan sesudah' }).click();
            await expect(page.getByRole('dialog')).toContainText('Lama 55');
            await atRest(page);
            expect((await new AxeBuilder({ page }).withTags(TAGS).analyze()).violations).toEqual([]);
            await page.keyboard.press('Escape');
        }

        await page.goto(`/aktivitas/log-keamanan?peristiwa=${encodeURIComponent(run.notice)}&asal=beranda`);
        await expect(page.locator('#konten')).toContainText('Kejanggalan akses');
        await atRest(page);
        expect((await new AxeBuilder({ page }).withTags(TAGS).analyze()).violations).toEqual([]);
    });
}

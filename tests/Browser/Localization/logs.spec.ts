import { expect, test } from '@playwright/test';

import { logsRun } from '../logs-fixtures';

// TL-10 (TASK-061; LOCALIZATION §3; DOD-16): PG-063 and PG-064 in both languages — keys of the catalogue, never
// hardcoded copy; the values an event recorded — its reason — stay as recorded (§3.4).

for (const [locale, texts] of [
    ['id', { history: 'Riwayat Aktivitas', security: 'Log Keamanan', action: 'Membuat proyek', company: 'Perusahaan', kind: 'Gagal masuk', kindFilter: 'Jenis' }],
    ['en', { history: 'Activity history', security: 'Security log', action: 'Created a project', company: 'Company', kind: 'Sign-in failed', kindFilter: 'Kind' }],
] as const) {
    test(`shows PG-063 and PG-064 in ${locale}`, async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 900 });
        const run = await logsRun(page, `log-${locale}-${String(Date.now())}`, locale);

        await page.goto(`/aktivitas/riwayat?perusahaan=${run.code}`);
        await expect(page.getByRole('heading', { name: texts.history })).toBeVisible();
        await expect(page.getByLabel(texts.company)).toBeVisible();
        const table = page.getByRole('table', { name: texts.history, exact: true });
        await expect(table).toContainText(texts.action);
        await expect(table).toContainText(`Uji log ${run.tag}`);

        await page.goto('/aktivitas/log-keamanan?jenis=LOGIN_FAILED');
        await expect(page.getByRole('heading', { name: texts.security })).toBeVisible();
        await expect(page.getByLabel(texts.kindFilter)).toBeVisible();
        await expect(page.getByRole('table', { name: texts.security, exact: true })).toContainText(texts.kind);
    });
}

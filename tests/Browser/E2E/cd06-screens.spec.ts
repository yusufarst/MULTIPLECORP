import { expect as base, test } from '@playwright/test';

import { invoiceRun } from '../invoice-fixtures';
import { projectRun } from '../project-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-09 (TASK-065; DIR-079 CD-06; TEST_STRATEGY §9; criterion 7): the screens the Owner's business decisions changed, verified on
// the application where the journeys named for them pass the screen by — CA-06's project rows on PG-010, CA-10's overdue projects
// in *Beranda*'s decision group and CB-03's receivables position with its ageing chart on PG-006. CA-07's dispatch-ready lines are
// checked on PG-007 within CJ-07, CA-09's purchase number within CJ-02, CA-08's language by CJ-18; CB-01's and CB-02's screens are
// CJ-16's, staging only (TEST_STRATEGY §7).

test.describe.configure({ timeout: 240_000 });

const DESK = { width: 1280, height: 900 };
const NEXT_STEP = /Lengkapi unit dan PIC klien|Buat dan terbitkan penawaran|Catat jawaban klien|Revisi penawaran|Konfirmasi proyek|Siapkan barang|Pesan dan konfirmasi|Catat penerimaan|Catat serah terima|Lengkapi dokumen|Buat invoice|Tagihkan invoice|Catat pembayaran|Selesaikan/;

test('CA-06 on PG-010: a project row shows its journey stage, its blocker marker and its next step (DIR-079 CA-06; QB-01)', async ({ page }) => {
    await page.setViewportSize(DESK);
    // TASK-032's company and an ACTIVE project of it created through ROUTE-151's action, an open correction case on it — a blocker.
    const run = await projectRun(page, `cd06-ca06-${String(Date.now())}`, { project: true, blocked: true });
    await page.goto(`/proyek?perusahaan=${run.code}`);
    await expect(page.getByRole('heading', { name: 'Proyek', level: 1 })).toBeVisible();

    const row = page.getByRole('row').filter({ hasText: run.number ?? '' });
    await expect(row).toHaveCount(1);
    // The journey summary group arrives after the rows (QB-01): its stage with its step, the blocker marker and the next step.
    await expect(row).not.toContainText('Memuat tahap…');
    await expect(row).toContainText(/\(langkah \d+ dari \d+\)/);
    await expect(row).toContainText('Terhambat');
    await expect(row).toContainText(NEXT_STEP);
});

test('CA-10 on PG-006: the Owner\'s decision group lists an ACTIVE project past its deadline with its days overdue (DIR-077 CA-10; QS-23)', async ({ page }) => {
    await page.setViewportSize(DESK);
    const run = await projectRun(page, `cd06-ca10-${String(Date.now())}`, { project: true, overdue: true });
    await page.goto(`/beranda?perusahaan=${run.company}`);
    await expect(page.getByRole('heading', { name: 'Beranda', level: 1 })).toBeVisible();
    await expect(page.locator('[data-region-state="loading"]')).toHaveCount(0);

    const decisions = page.getByRole('region', { name: 'Perlu keputusan Anda' });
    const overdue = decisions.getByRole('region', { name: 'Proyek melewati tenggat' });
    await expect(overdue).toContainText(run.number ?? '');
    await expect(overdue).toContainText('lewat 3 hari');
});

test('CB-03 on PG-006: one company\'s receivables position among the period figures and its ageing chart with the chart\'s table (DIR-079 CB-03; QB-10; VP-6)', async ({ page }) => {
    await page.setViewportSize(DESK);
    // TASK-049's company: a service project and its invoice of Rp1,000,000 issued and billed, nothing paid.
    const run = await invoiceRun(page, `cd06-cb03-${String(Date.now())}`, { stage: 'issued', billed: true });
    await page.goto(`/beranda?perusahaan=${run.company}`);
    await expect(page.getByRole('heading', { name: 'Beranda', level: 1 })).toBeVisible();
    await expect(page.locator('[data-region-state="loading"]')).toHaveCount(0);

    const figures = page.getByRole('region', { name: 'Angka Periode' });
    await expect(figures.locator('[data-slot="metric-strip"]')).toContainText('Piutang aktif');
    await expect(figures.locator('[data-slot="metric-strip"]')).toContainText('Rp1.000.000');
    const ageing = figures.locator('[data-slot="ageing"]');
    await expect(ageing).toBeVisible();
    // A chart of defined values: its amounts labelled on the bars and the same rows in its data table (VP-6; SLOP-34).
    await expect(ageing.getByRole('table', { name: 'Tabel' }).getByRole('row').filter({ hasText: 'Rp1.000.000' })).toHaveCount(1);
    await expect(ageing.locator('svg text').filter({ hasText: /Rp/ }).first()).toBeVisible();
});

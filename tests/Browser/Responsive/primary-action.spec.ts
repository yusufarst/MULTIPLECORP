import { expect as base, type Locator, type Page, test } from '@playwright/test';

import { signedIn } from '../account-fixtures';
import { numberingCompany } from '../numbering-fixtures';
import { projectRun } from '../project-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-11 (TASK-096 C; GAP-073; PT-03; §11; C1A-2; NAV-011; J-PRJ-01; J-FIN-03): on the phone PG-010's "Buat Proyek", PG-011's
// next step and PG-045's "Catat Pembayaran" sit in the bottom action bar as PG-014's primary action does — one action, a touch
// target, in view without scrolling and clear of the shell's bottom bar, opening what the desktop opens —; from 768 px the page
// header holds it and no bar shows; none to whom lacks the capability; none while PG-010's empty list offers the action itself.

test.describe.configure({ timeout: 300_000 });

const bar = (page: Page, name: string): Locator => page.getByRole('region', { name, exact: true });
const header = (page: Page): Locator => page.locator('[data-slot="page-header"]');

/** The bar's one action: a touch target in view at the page's top, above the shell's bottom bar. */
async function reachable(page: Page, action: Locator): Promise<void> {
    await expect(action).toHaveCount(1);
    await expect(action).toBeInViewport();
    const box = await action.boundingBox();
    const nav = await page.getByRole('navigation', { name: 'Navigasi utama' }).locator('visible=true').boundingBox();
    expect(box?.height ?? 0).toBeGreaterThanOrEqual(44);
    expect((box?.y ?? 0) + (box?.height ?? 0)).toBeLessThanOrEqual(nav?.y ?? 844);
}

test.describe('on the phone with a coarse pointer', () => {
    test.use({ hasTouch: true, isMobile: true, viewport: { width: 390, height: 844 } });

    test('shows PG-010\'s, PG-011\'s and PG-045\'s primary action in the bottom action bar, opening what the desktop opens (PT-03; J-PRJ-01; J-FIN-03)', async ({ page }) => {
        const run = await projectRun(page, `utama-hp-${String(Date.now())}`, { project: true });
        expect(await page.evaluate(() => window.matchMedia('(pointer: coarse)').matches)).toBe(true);

        await page.goto('/proyek');
        await expect(page.locator('#konten')).toContainText(run.number ?? '');
        const create = bar(page, 'Proyek').getByRole('link', { name: 'Buat Proyek' });
        await reachable(page, create);
        await expect(create).toHaveAttribute('href', '/proyek/baru');
        await expect(header(page).getByRole('link', { name: 'Buat Proyek' })).toBeHidden();

        await page.goto(`/proyek/${run.project ?? ''}`);
        const next = bar(page, 'Perjalanan Proyek').getByRole('link');
        await reachable(page, next);
        const href = await next.getAttribute('href');
        const label = ((await next.textContent()) ?? '').trim();
        expect(href).not.toBeNull();
        expect(label).not.toBe('');

        await page.goto('/keuangan/pembayaran');
        const record = bar(page, 'Pembayaran').getByRole('button', { name: 'Catat Pembayaran' });
        await reachable(page, record);
        await record.click();
        await page.waitForURL('**/keuangan/pembayaran/baru');

        // The desktop's header opens the same next step.
        await page.setViewportSize({ width: 1280, height: 900 });
        await page.goto(`/proyek/${run.project ?? ''}`);
        await expect(header(page).getByRole('link', { name: label, exact: true })).toHaveAttribute('href', href ?? '');
    });

    test('offers no primary action to whom lacks the capability, and none in a bar while PG-010\'s empty list offers it (AZ-01; C1A-2)', async ({ page, browser }) => {
        const run = await projectRun(page, `utama-hak-${String(Date.now())}`, { project: true });
        // A company of its own with no project yet, for PG-010's empty list.
        const empty = await numberingCompany(page, `${String(new Date().getFullYear())}-01-01`);
        const phone = { baseURL: test.info().project.use.baseURL, ignoreHTTPSErrors: true, viewport: { width: 390, height: 844 }, hasTouch: true, isMobile: true };

        const viewing = await browser.newContext(phone);
        const viewer = await viewing.newPage();
        const reader = await signedIn(viewer, 'utama-lihat');
        expect((await viewer.request.post('/__fixtures/k2/grant', { data: { account: reader.id, company: run.company, profile: 'view' } })).ok()).toBe(true);
        await viewer.goto('/proyek');
        await expect(viewer.locator('#konten')).toContainText(run.number ?? '');
        await expect(bar(viewer, 'Proyek')).toHaveCount(0);
        await expect(viewer.getByRole('link', { name: 'Buat Proyek' })).toHaveCount(0);
        await viewing.close();

        const creating = await browser.newContext(phone);
        const creator = await creating.newPage();
        const maker = await signedIn(creator, 'utama-kosong');
        expect((await creator.request.post('/__fixtures/k2/grant', { data: { account: maker.id, company: empty, profile: 'manage' } })).ok()).toBe(true);
        await creator.goto('/proyek');
        const state = creator.locator('[data-region-state="empty"]');
        await expect(state.getByRole('link', { name: 'Buat Proyek' })).toBeVisible();
        await expect(bar(creator, 'Proyek')).toHaveCount(0);
        await expect(creator.getByRole('link', { name: 'Buat Proyek' }).locator('visible=true')).toHaveCount(1);
        await creating.close();
    });
});

for (const width of [767, 768, 1280]) {
    test(`keeps one primary action at ${String(width)} px — the bar below 768 px, the page header from it (PT-03; §11)`, async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 900 });
        const run = await projectRun(page, `utama-lebar-${String(width)}-${String(Date.now())}`, { project: true });
        await page.setViewportSize({ width, height: 900 });

        await page.goto('/proyek');
        await expect(page.locator('#konten')).toContainText(run.number ?? '');
        await expect(page.getByRole('link', { name: 'Buat Proyek' }).locator('visible=true')).toHaveCount(1);
        await expect(bar(page, 'Proyek')).toHaveCount(width < 768 ? 1 : 0);
        await expect(header(page).getByRole('link', { name: 'Buat Proyek' })).toBeVisible({ visible: width >= 768 });

        await page.goto('/keuangan/pembayaran');
        await expect(page.getByRole('button', { name: 'Catat Pembayaran' }).locator('visible=true')).toHaveCount(1);
        await expect(bar(page, 'Pembayaran')).toHaveCount(width < 768 ? 1 : 0);
    });
}

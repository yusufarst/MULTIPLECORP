import { expect, type Page, test } from '@playwright/test';

import { projectRun } from '../project-fixtures';

// TL-09 and TL-11 (TASK-097; DESIGN_SYSTEM §6, §8, PT-03, PT-19, D-DS-02; KB-03): the shared foundation's corrected
// behaviours on Chromium and WebKit — the tablet band's 80 px rail, one visible title on the phone and PG-011's financial
// part named once, a busy control's turning spinner and its stillness under reduced motion, 16 px fields on a coarse
// pointer with the desktop's sizes kept, and "Kode referensi" only with a code.

/** The navigation columns taller than a bar: the sidebar or its rail, with whether it is the rail and its width. */
async function columns(page: Page): Promise<{ rail: boolean; width: number }[]> {
    return page.evaluate(() =>
        [...document.querySelectorAll('nav')]
            .filter((nav) => nav.checkVisibility() && nav.getBoundingClientRect().height > 300)
            .map((nav) => ({ rail: nav.hasAttribute('data-rail'), width: Math.round(nav.getBoundingClientRect().width) })),
    );
}

test('shows the 80 px rail from 768 to 1,024 px and the sidebar from 1,025 px (§6; KB-03; criterion 6)', async ({ page }) => {
    await page.goto('/__fixtures/shell?persona=owner&run=rail');
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();

    for (const width of [768, 900, 1024]) {
        await page.setViewportSize({ width, height: 900 });
        await expect.poll(() => columns(page), `${String(width)} px`).toEqual([{ rail: true, width: 80 }]);
    }

    for (const width of [1025, 1280]) {
        await page.setViewportSize({ width, height: 900 });
        await expect.poll(async () => (await columns(page)).map((column) => column.rail), `${String(width)} px`).toEqual([false]);
        expect((await columns(page))[0]?.width ?? 0, `${String(width)} px`).toBeGreaterThanOrEqual(220);
    }

    await page.setViewportSize({ width: 767, height: 900 });
    await expect.poll(() => columns(page), '767 px: the phone\'s bars, no column').toEqual([]);
});

test('names "Kode referensi" only with a code the server gave (PT-19; criterion 5)', async ({ page }) => {
    await page.goto('/__fixtures/komponen?bahasa=id');
    const section = page.locator('[data-gallery="PT-19"]');
    const without = section.locator('[data-specimen="no-reference"]');

    await expect(without.getByRole('button', { name: 'Muat Ulang Bagian Ini' })).toBeVisible();
    await expect(without).not.toContainText('Kode referensi');
    await expect(without.locator('[data-code]')).toHaveCount(0);
    // The control: with a code, the line stands.
    await expect(section.locator('[data-region-state]').filter({ hasText: 'ERR-7F3K-2Q9M' }).first()).toContainText('Kode referensi');
});

test.describe('a busy control', () => {
    /** Holds the sign-in's request so the button stays busy past the spinner's 300 ms delay. */
    async function busy(page: Page): Promise<void> {
        await page.route('**/masuk', async (route) => {
            if (route.request().method() === 'POST') {
                await new Promise((resolve) => setTimeout(resolve, 3000));
            }

            await route.continue();
        });
        await page.goto('/masuk');
        await page.getByLabel('E-mail').fill('putaran@example.com');
        await page.getByLabel('Kata sandi', { exact: true }).fill('bukan-kata-sandi-ini');
        await page.getByRole('button', { name: 'Masuk', exact: true }).click();
    }

    const motion = (page: Page) =>
        page.locator('[data-slot="spinner"] svg').first().evaluate((icon) => {
            const style = getComputedStyle(icon);

            return { name: style.animationName, duration: style.animationDuration, iterations: style.animationIterationCount };
        });

    test('turns its spinner, one turn per 900 ms, after the 300 ms delay (§8; criterion 2)', async ({ page }) => {
        await busy(page);
        await expect(page.locator('[data-slot="spinner"]')).toBeVisible();
        expect(await motion(page)).toEqual({ name: 'spin', duration: '0.9s', iterations: 'infinite' });
    });

    test('keeps its spinner still under reduced motion, its label kept (§8; criterion 2)', async ({ page }) => {
        await page.emulateMedia({ reducedMotion: 'reduce' });
        await busy(page);
        const spinner = page.locator('[data-slot="spinner"]');
        await expect(spinner).toBeVisible();
        await expect(spinner).toHaveAttribute('role', 'status');
        await expect(spinner).toContainText('Masuk');
        expect((await motion(page)).name).toBe('none');
    });
});

test('keeps the desktop\'s field size on a fine pointer (D-DS-02; criterion 3)', async ({ page }) => {
    await page.goto('/masuk');
    expect(await page.evaluate(() => window.matchMedia('(pointer: coarse)').matches)).toBe(false);
    await expect(page.getByLabel('E-mail')).toHaveCSS('font-size', '14px');
});

test.describe('on the phone with a coarse pointer', () => {
    test.use({ hasTouch: true, isMobile: true, viewport: { width: 390, height: 844 } });

    /** Every visible text field, select and textarea of the page, with its computed size. */
    const sizes = (page: Page) =>
        page.evaluate(() =>
            [...document.querySelectorAll('input, select, textarea')]
                .filter((field) => field.checkVisibility() && !['checkbox', 'radio', 'hidden', 'file', 'range'].includes((field as HTMLInputElement).type))
                .map((field) => ({ name: field.getAttribute('name') ?? field.id, size: getComputedStyle(field).fontSize })),
        );

    test('sets every field to 16 px, the sign-in\'s and a form page\'s (D-DS-02; criterion 3)', async ({ page }) => {
        await page.goto('/masuk');
        expect(await page.evaluate(() => window.matchMedia('(pointer: coarse)').matches)).toBe(true);
        const signIn = await sizes(page);
        expect(signIn.length).toBeGreaterThanOrEqual(2);
        expect(signIn.filter((field) => field.size !== '16px')).toEqual([]);

        await projectRun(page, `kolom-sentuh-${String(Date.now())}`);
        await page.goto('/proyek/baru');
        await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
        const form = await sizes(page);
        expect(form.length).toBeGreaterThan(0);
        expect(form.filter((field) => field.size !== '16px')).toEqual([]);
    });

    test('shows one visible title per page, and PG-011\'s financial part one heading (§6; PT-03; criterion 4)', async ({ page }) => {
        const run = await projectRun(page, `satu-judul-${String(Date.now())}`, { project: true });

        for (const path of ['/proyek', '/proyek/baru', `/proyek/${run.project ?? ''}`]) {
            await page.goto(path);
            await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
            const titles = await page.evaluate(() =>
                [...document.querySelectorAll('h1, header:not([data-slot]) > p')]
                    .filter((element) => element.checkVisibility({ visibilityProperty: true }))
                    .map((element) => element.textContent.trim()),
            );
            expect(titles, path).toHaveLength(1);
        }

        await expect(page.getByRole('heading', { name: 'Posisi keuangan' })).toHaveCount(1);
    });
});

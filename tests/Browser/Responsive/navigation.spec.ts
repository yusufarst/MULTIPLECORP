import { expect, type Page, test } from '@playwright/test';

// TL-09 and TL-11 (TASK-099; CR-002 K-4; DESIGN_SYSTEM §6, PT-01, PT-02, §10 "Shell", GR-11; INFORMATION_ARCHITECTURE §5.3, §6): the
// *Menu* at each band on the local stack's shell fixture — the 248 px sidebar with its three groups from 1,025 px, the 80 px
// labelled rail from 768 to 1,024 px with *Menu* opening the same tree as a 20 rem drawer, the phone's bottom bar and its
// full-screen *Menu* — and focus kept in a sheet and returned to its button on Escape.

let runs = 0;

async function open(page: Page, persona: 'owner' | 'admin', width: number): Promise<void> {
    await page.setViewportSize({ width, height: 900 });
    await page.goto(`/__fixtures/shell?persona=${persona}&run=nav${String(Date.now())}-${String((runs += 1))}`);
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
}

/** The navigation column taller than a bar — the sidebar or its rail — with whether it is the rail and its width. */
const column = (page: Page) =>
    page.evaluate(() => {
        const nav = [...document.querySelectorAll('nav')].find((each) => each.checkVisibility() && each.getBoundingClientRect().height > 300);

        return { rail: nav?.hasAttribute('data-rail') ?? null, width: Math.round(nav?.getBoundingClientRect().width ?? 0) };
    });

/** The width `--sidebar-width` resolves to at this viewport, measured on a probe of that width. */
const sidebarToken = (page: Page) =>
    page.evaluate(() => {
        const probe = document.createElement('div');
        probe.style.width = 'var(--sidebar-width)';
        document.body.append(probe);
        const width = Math.round(probe.getBoundingClientRect().width);
        probe.remove();

        return width;
    });

test('shows the 248 px sidebar with the three groups and each module opening in place, from 1,025 px (K-4; PT-01)', async ({ page }) => {
    for (const viewport of [1025, 1280, 1440]) {
        await open(page, 'owner', viewport);
        const nav = page.getByRole('navigation', { name: 'Navigasi utama' }).first();

        // DESIGN_SYSTEM §6: 248 px at the canvas, at least 220 px at runtime — the desktop band's `--sidebar-width` is
        // `max(220px, 248 × --du)`, so the column is exactly the token's width at this viewport, within those bounds.
        const token = await sidebarToken(page);
        expect(token, `${String(viewport)} px`).toBeGreaterThanOrEqual(220);
        expect(token, `${String(viewport)} px`).toBeLessThanOrEqual(248);
        expect(await column(page), `${String(viewport)} px`).toEqual({ rail: false, width: token });

        for (const group of ['Operasional', 'Keuangan & dokumen', 'Administrasi']) {
            await expect(nav.getByText(group, { exact: true })).toBeVisible();
        }

        const module = nav.getByRole('button', { name: 'Keuangan', exact: true });
        await expect(module).toHaveAttribute('aria-expanded', 'false');
        await module.click();
        await expect(module).toHaveAttribute('aria-expanded', 'true');
        await expect(nav.getByRole('link', { name: 'Invoice', exact: true })).toBeVisible();
    }
});

test('shows the 80 px labelled rail from 768 to 1,024 px and its Menu as a 20 rem drawer of the same tree (K-4; PT-01; §6)', async ({ page }) => {
    for (const viewport of [768, 900, 1024]) {
        await open(page, 'owner', viewport);
        const rail = page.locator('nav[data-rail]');

        expect(await column(page), `${String(viewport)} px`).toEqual({ rail: true, width: 80 });
        // Labelled: each entry's name is text under its icon, never an icon alone.
        for (const name of ['Beranda', 'Proyek', 'Pekerjaan', 'Tinjauan', 'Menu']) {
            await expect(rail.getByText(name, { exact: true })).toBeVisible();
        }

        const menu = rail.getByRole('button', { name: 'Menu', exact: true });
        await menu.click();
        const drawer = page.getByRole('dialog');
        await expect(drawer).toBeVisible();
        const sheet = page.locator('[data-slot="sheet-content"]');
        await expect(sheet).not.toHaveAttribute('data-starting-style');
        expect(Math.round((await sheet.boundingBox())?.width ?? 0), `${String(viewport)} px`).toBe(320);

        for (const group of ['Operasional', 'Keuangan & dokumen', 'Administrasi']) {
            await expect(drawer.getByText(group, { exact: true })).toBeVisible();
        }

        await page.keyboard.press('Escape');
        await expect(drawer).toHaveCount(0);
        await expect(menu).toBeFocused();
    }
});

test('gives the phone a bottom bar and a full-screen Menu — Kerja, the groups, Akun — whose focus returns to its button (K-4; PT-02; GR-11)', async ({ page }) => {
    await open(page, 'owner', 390);
    const bar = page.getByRole('navigation', { name: 'Navigasi utama' }).last();
    const menu = bar.getByRole('button', { name: 'Menu', exact: true });

    await expect(bar.getByRole('link')).toHaveText(['Beranda', 'Proyek']);
    await menu.click();
    const drawer = page.getByRole('dialog');
    await expect(drawer).toBeVisible();
    const sheet = page.locator('[data-slot="sheet-content"]');
    await expect(sheet).not.toHaveAttribute('data-starting-style');
    const box = await sheet.boundingBox();

    expect(Math.round(box?.width ?? 0)).toBe(390);
    expect(Math.round(box?.height ?? 0)).toBeGreaterThanOrEqual(800);
    for (const label of ['Kerja', 'Operasional', 'Keuangan & dokumen', 'Administrasi', 'Akun']) {
        await expect(drawer.getByText(label, { exact: true })).toBeVisible();
    }

    await page.keyboard.press('Escape');
    await expect(drawer).toHaveCount(0);
    await expect(menu).toBeFocused();
});

test('gives an Admin the modules its capabilities open and no other, nothing disabled (AZ-01; NV-08)', async ({ page }) => {
    await open(page, 'admin', 1280);
    const nav = page.getByRole('navigation', { name: 'Navigasi utama' }).first();

    await expect(nav.getByRole('button', { name: 'Pengaturan', exact: true })).toHaveCount(0);
    await expect(nav.getByRole('button', { name: 'Aktivitas & Keamanan', exact: true })).toHaveCount(0);
    await expect(nav.locator('[disabled], [aria-disabled="true"]')).toHaveCount(0);
});

import { expect as base, type Page, test, type TestInfo } from '@playwright/test';

import { clean, guardsClean, watch } from '../journey-fixtures';
import { auditStatuses, shownStatuses } from '../journey-k3-fixtures';
import { projectRun } from '../project-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-09 (TASK-064; CJ-22; UXS-42; UXS-43; UXH-07; DESIGN_SYSTEM §12.1, §18; criteria 10, 12): on the desktop, every status the walked pages
// show — the company's readiness list, the project list, the design system's gallery, *Beranda* and *Pekerjaan* — is a text label with a
// shape cue, the shape hidden from assistive technology beside the label, two states of a family never told apart by hue alone: what stays
// when colour is removed (greyscale) or confused (colour blindness) is the label and the shape, which the DOM carries independently of any
// colour. Each page is also kept in greyscale as evidence. UXS-43: *Beranda*'s anti-slop audit — its row lists and one figures table, no
// KPI-card wall, no chart but the ageing chart of defined values with its labels and data table, no banner, no greeting, no emoji, no heading
// above 20 px — and *Pekerjaan*'s the same. The console and network clean, ReconcileGuards clean for the run's company (TS-11).

test.describe.configure({ timeout: 300_000 });

const DESK = { width: 1280, height: 900 };

const GREETING = /\b(selamat (pagi|siang|sore|malam|datang)|halo|hai|welcome|good (morning|afternoon|evening))\b/i;
const EMOJI = /\p{Extended_Pictographic}/u;

type Faces = Map<string, Map<string, { label: string; shape: string }>>;

/**
 * The page in greyscale as evidence (UXS-42): the filter set through the CSS object model — never a style element or attribute, which the
 * content security policy would refuse —, a screenshot kept, the filter removed. The assertions are the DOM's, not the picture's.
 */
async function greyscale(page: Page, testInfo: TestInfo, name: string): Promise<void> {
    await page.evaluate(() => {
        document.documentElement.style.filter = 'grayscale(1)';
    });
    await testInfo.attach(`${name}-greyscale.png`, { body: await page.screenshot({ fullPage: true }), contentType: 'image/png' });
    await page.evaluate(() => {
        document.documentElement.style.filter = '';
    });
}

/** Every status of the page settled and audited (UXS-42), the faces seen added to the run's. */
async function audit(page: Page, testInfo: TestInfo, name: string, faces: Faces, settled = true): Promise<number> {
    // A page's deferred groups settled first; the gallery shows its loading state on purpose, as a specimen.
    if (settled) {
        await expect(page.locator('[data-region-state="loading"]')).toHaveCount(0);
    }

    const shown = await shownStatuses(page);
    auditStatuses(shown, name, faces);
    await greyscale(page, testInfo, name);

    return shown.filter((status) => status.visible).length;
}

/** The anti-slop rules every dashboard page keeps (DESIGN_SYSTEM §18): no greeting, no emoji, no heading above 20 px, no card wall. */
async function antiSlop(page: Page, where: string): Promise<void> {
    const content = page.locator('#konten');
    const text = await content.innerText();
    expect(text, `${where}: no greeting (SLOP-20; §16)`).not.toMatch(GREETING);
    expect(text, `${where}: no emoji as decoration (SLOP-22)`).not.toMatch(EMOJI);
    // SLOP-26 as DESIGN_SYSTEM §5.2 and §7 state it: a page title on the reference's scale — at most `--text-title` (32 px at
    // the canvas; the phone's title smaller) — and the display size only on the sign-in, never a hero. The title's token is read
    // from the page; the display's — 44 × `--tu` in §5.2 — is computed from the unit, since a page that uses no display text
    // carries no `--text-display` (Tailwind emits only the theme variables in use).
    const h1 = page.getByRole('heading', { level: 1 });
    const scale = await page.evaluate(() => {
        const measure = (size: string): number => {
            const probe = document.createElement('span');
            probe.style.fontSize = size;
            document.body.append(probe);
            const px = parseFloat(getComputedStyle(probe).fontSize);
            probe.remove();

            return px;
        };

        return { title: measure('var(--text-title)'), display: measure('calc(44 * var(--tu))') };
    });
    expect(scale.display, 'the display size measured above the title size (the probe resolved)').toBeGreaterThan(scale.title);
    const titleSize = await h1.evaluate((heading) => parseFloat(getComputedStyle(heading).fontSize));
    expect(titleSize, `${where}: the page title at most --text-title (SLOP-26; DESIGN_SYSTEM §5.2; PT-03)`).toBeLessThanOrEqual(scale.title);
    expect(titleSize, `${where}: never the sign-in's display size (SLOP-26)`).toBeLessThan(scale.display);
    await expect(content.locator('section [data-slot="card"]'), `${where}: no KPI cards — counts are inline text in signal rows (SLOP-18; PT-21)`).toHaveCount(0);
    expect(await content.locator('[data-slot="metric-strip"]').count(), `${where}: key figures in one strip at most, never a wall of tiles (SLOP-18)`).toBeLessThanOrEqual(1);
    await expect(content.getByRole('banner'), `${where}: no banner in the page (SLOP-19; PT-03)`).toHaveCount(0);
    await expect(content.locator('section img'), `${where}: no illustration (SLOP-13; PT-19)`).toHaveCount(0);
    await expect(content.locator('canvas'), `${where}: no canvas drawing`).toHaveCount(0);
    // Icons only where they carry meaning, at their sizes (SLOP-23; SLOP-32): no decorative drawing outside the ageing chart.
    const large = await content.locator('svg').evaluateAll((svgs) =>
        svgs.filter((svg) => svg.closest('[data-slot="ageing"]') === null && svg.getBoundingClientRect().width > 28).map((svg) => svg.getAttribute('class') ?? svg.tagName),
    );
    expect(large, `${where}: no decorative drawing (SLOP-12; SLOP-13)`).toEqual([]);
}

test('CJ-22: every status a text label with a shape cue, never hue alone, and the anti-slop audit of Beranda and Pekerjaan (UXS-42; UXS-43; UXH-07; criterion 10)', async ({ page }, testInfo) => {
    const captured = watch(page);
    await page.setViewportSize(DESK);
    // TASK-032's company of the run, its PROJECT numbering started, and a DRAFT project of it.
    const run = await projectRun(page, `cj22-${String(Date.now())}`, { project: true, draft: true });
    const faces: Faces = new Map();

    // PG-060's readiness list: both states of its family side by side — *Lengkap* for the started numbering, *Belum* for the rest.
    await page.goto(`/pengaturan/perusahaan?perusahaan=${run.company}`);
    const readiness = page.getByRole('list', { name: 'Kesiapan Perusahaan' });
    await expect(readiness.locator('[data-status="readiness_item.COMPLETE"]').first()).toBeVisible();
    await expect(readiness.locator('[data-status="readiness_item.NOT_COMPLETE"]').first()).toBeVisible();
    await expect(readiness.locator('[data-status="readiness_item.COMPLETE"]').first()).toHaveText('Lengkap');
    await expect(readiness.locator('[data-status="readiness_item.NOT_COMPLETE"]').first()).toHaveText('Belum');
    expect(await audit(page, testInfo, 'pg-060-readiness', faces)).toBeGreaterThan(1);

    // PG-010 filtered to the run's company: the DRAFT project with its state, beside whatever states the shared list shows.
    await page.goto(`/proyek?perusahaan=${run.code}`);
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Proyek');
    await expect(page.locator('[data-status="project.DRAFT"]:visible').first()).toHaveText('Draf');
    expect(await audit(page, testInfo, 'pg-010-projects', faces)).toBeGreaterThan(0);
    await page.goto('/proyek');
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Proyek');
    await audit(page, testInfo, 'pg-010-all', faces);

    // The design system's gallery: the families it composes, one record's pill and the list's row form (PT-13).
    await page.goto('/__fixtures/komponen');
    await expect(page.locator('[data-status="project.ACTIVE"]').first()).toBeVisible();
    await expect(page.locator('[data-status="project.COMPLETED_FORCED"]').first()).toBeVisible();
    expect(await audit(page, testInfo, 'gallery', faces, false)).toBeGreaterThan(3);
    // Two states of one family in one view, readable with the colour gone: their labels and their shapes both differ.
    expect(faces.get('project')?.get('ACTIVE')?.shape).not.toBe(faces.get('project')?.get('COMPLETED_FORCED')?.shape);
    expect(faces.get('readiness_item')?.get('COMPLETE')?.shape).not.toBe(faces.get('readiness_item')?.get('NOT_COMPLETE')?.shape);

    // UXS-43 on PG-006 *Beranda*: row lists and one figures table; a chart only the ageing chart of defined values, with its direct labels
    // and its data table; no KPI-card wall, banner or greeting (DESIGN_SYSTEM §18 SLOP-18–SLOP-20, SLOP-34; PT-21).
    await page.goto('/beranda');
    await expect(page.getByRole('heading', { name: 'Beranda', level: 1 })).toBeVisible();
    await expect(page.locator('[data-region-state="loading"]')).toHaveCount(0);
    const content = page.locator('#konten');
    await expect(page.getByRole('region', { name: 'Perlu keputusan Anda' })).toBeVisible();
    await expect(page.getByRole('table', { name: 'Angka Periode' })).toHaveCount(1);
    const ageing = content.locator('[data-slot="ageing"]');
    await expect(content.locator('table'), 'one figures table, and the ageing chart\'s data table when it is shown').toHaveCount(1 + (await ageing.count()));
    await expect(content.locator('.recharts-wrapper'), 'no chart but the ageing chart (SLOP-34)').toHaveCount(await ageing.locator('.recharts-wrapper').count());

    if ((await ageing.count()) > 0) {
        // The ageing chart is of defined values: each bar's amount labelled and the same rows in its table (VP-6; GR-23).
        await expect(ageing.getByRole('table', { name: 'Tabel' }).getByRole('row')).not.toHaveCount(0);
        await expect(ageing.locator('svg text').filter({ hasText: /Rp/ }).first()).toBeVisible();
    }

    expect(await content.getByRole('heading', { level: 2 }).count(), 'five groups at most (SLOP-28; IA §9)').toBeLessThanOrEqual(5);
    await antiSlop(page, 'PG-006 Beranda');
    await audit(page, testInfo, 'pg-006-beranda', faces);

    // PG-007 *Pekerjaan*: row lists only — no chart, no figures wall.
    await page.getByRole('link', { name: 'Buka Pekerjaan' }).click();
    await expect(page.getByRole('heading', { name: 'Pekerjaan', level: 1 })).toBeVisible();
    await expect(page.locator('[data-region-state="loading"]')).toHaveCount(0);
    await expect(content.locator('.recharts-wrapper')).toHaveCount(0);
    await antiSlop(page, 'PG-007 Pekerjaan');
    await audit(page, testInfo, 'pg-007-pekerjaan', faces);

    await guardsClean(page, testInfo, [run.company]);
    await clean(captured, testInfo);
});

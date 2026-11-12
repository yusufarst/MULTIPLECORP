import { expect as base, type Locator, type Page, test } from '@playwright/test';

import { signedIn } from '../account-fixtures';
import { clean, guardsClean, watch } from '../journey-fixtures';
import { capitalized, letters, type SearchStamps, searchWorld, type SearchWorld } from '../journey-k3-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-09 (TASK-064; CJ-12; AC-23; FX-07; UXS-33; PF-19–PF-23; CS-07; DP-06; criteria 4, 12): search and filter on PG-009 *Cari* as the
// Company-A-only Admin of SECURITY_TEST_MATRIX §2 — a product by its name, its SKU and its barcode, a project by its number and by its title,
// a client, a document by its number —; a two-character term answered with MSG-24 and exact and prefix matches only; a term matching more
// than 200 of the company's projects answered with MSG-23 above the list; and never a record of a company outside the Admin's scope, though
// the other company of the run holds the same project and document numbers and titles of its own with the same term. On the desktop and on
// the phone at 390 CSS px. Every test watches its console and network from its first step and ends with ReconcileGuards reporting no
// discrepancy for its companies (TS-11; TEST_STRATEGY §8, §16).

// The world's fixture draws two projects and issues two quotations through their actions and writes some 210 projects before the walk.
test.describe.configure({ timeout: 300_000 });

const DESK = { width: 1280, height: 900 };
const PHONE = { width: 390, height: 844 };

const MSG_23 = 'Daftar dipotong. Persempit kata kunci.';
const MSG_24 = 'Ketik minimal 3 huruf atau angka untuk mencari nama. Kode dicari persis.';

function stamps(): SearchStamps {
    // Two characters no other spec's names begin with; the stamps letters only, so no term reads as a code (Term::looksLikeCode).
    return { bulk: letters(10), name: letters(10), hidden: letters(10), prefix: 'qv' };
}

/** PG-009's one field. */
function field(page: Page): Locator {
    return page.locator('#konten').getByRole('textbox', { name: 'Cari atau pindai kode' });
}

/** The line under the field that says why a list is short or cut (MSG-23, MSG-24). */
function notice(page: Page): Locator {
    return page.locator('#konten').getByRole('search').getByRole('status');
}

/** A result group by its heading (Cari.tsx's TYPE_LABELS). */
function group(page: Page, heading: string): Locator {
    return page.locator('[data-slot="search-group"]').filter({ has: page.getByRole('heading', { name: heading, exact: true }) });
}

function exact(page: Page): Locator {
    return page.locator('[data-slot="search-exact"]');
}

/** A term typed into the field and its answer settled: the loading line gone (Cari.tsx). */
async function look(page: Page, term: string): Promise<void> {
    await field(page).fill(term);
    await expect(page.locator('#konten [data-region-state="loading"]')).toHaveCount(0);
}

/** Every project row the page lists names the run's own company and never the other (PT-04; CS-07). */
async function onlyOwn(rows: Locator, world: SearchWorld): Promise<void> {
    await expect(rows.first()).toBeVisible();

    for (const text of await rows.allInnerTexts()) {
        expect(text, 'a project row of the Admin\'s company only (CS-07; DP-06)').toContain(world.own.code);
        expect(text, 'never a record of the company outside the Admin\'s scope (DP-06)').not.toContain(world.other.code);
    }
}

test('CJ-12 at the desk: products, SKU, barcode, projects, clients and documents found within the Admin\'s company, a two-character term and more than 200 matches (AC-23; UXS-33; criterion 4)', async ({ page }, testInfo) => {
    const captured = watch(page);
    await page.setViewportSize(DESK);
    // The Company-A-only Admin of SECURITY_TEST_MATRIX §2: a plain account with TOTP, granted the run's own company and `projects.view` there.
    await signedIn(page, 'cj12-admin');
    const run = stamps();
    const world = await searchWorld(page, run);

    await page.goto('/cari');
    await expect(page.getByRole('heading', { name: 'Cari', exact: true })).toBeVisible();
    await expect(field(page)).toBeFocused();

    // A product by its name, its SKU and its barcode: the codes exactly first under "Kode Cocok" (PF-19; IP-17).
    await look(page, capitalized(run.name));
    await expect(group(page, 'Produk / Jasa')).toContainText(world.own.product);
    await expect(group(page, 'Klien')).toContainText(world.own.client);

    await look(page, world.own.sku.toLowerCase());
    await expect(exact(page).getByRole('heading', { name: 'Kode Cocok' })).toBeVisible();
    await expect(exact(page).getByRole('link')).toHaveCount(1);
    await expect(exact(page)).toContainText(world.own.product);

    await look(page, world.own.barcode);
    await expect(exact(page).getByRole('link')).toHaveCount(1);
    await expect(exact(page)).toContainText(world.own.product);

    // A project by its number: the other company of the run holds the same number, and only the Admin's company's is listed (CS-07).
    await look(page, world.own.project_number);
    await expect(exact(page).getByRole('link')).toHaveCount(1);
    await expect(exact(page)).toContainText(world.own.code);
    await expect(exact(page)).not.toContainText(world.other.code);

    // A document by its number, the same in both companies: the Admin's company's alone, opening PG-036 (PJ-15).
    await look(page, world.own.document_number);
    await expect(exact(page).getByRole('link')).toHaveCount(1);
    await expect(exact(page)).toContainText(world.own.code);
    await expect(exact(page)).not.toContainText(world.other.code);
    await expect(exact(page).getByRole('link')).toHaveAttribute('href', /^\/dokumen\/[0-9a-f-]{36}$/);

    // A title only the other company's project holds: nothing, as for a record that does not exist (DP-06).
    await look(page, capitalized(run.hidden));
    await expect(page.locator('#konten').getByText('Tidak ditemukan.')).toBeVisible();
    await expect(page.locator('[data-slot="search-group"], [data-slot="search-exact"]')).toHaveCount(0);

    // UXS-33, a two-character term: MSG-24, and only names equal to it or beginning with it — never one holding it inside (PF-23).
    await look(page, 'Qv');
    await expect(notice(page)).toHaveText(MSG_24);
    const products = group(page, 'Produk / Jasa');
    await expect(products).toContainText(world.own.prefixed);
    await expect(products).not.toContainText(world.own.middle);

    for (const label of await page.locator('[data-slot="search-group"] li a > span:first-child > span:first-child').allInnerTexts()) {
        expect(label.toLowerCase(), 'a two-character term finds by equality and prefix only (PF-23)').toMatch(/^qv/);
    }

    // UXS-33, more than 200 matches: MSG-23 above the list, the projects listed all the Admin's company's (PF-21; PF-22).
    await look(page, capitalized(run.bulk));
    await expect(notice(page)).toHaveText(MSG_23);
    const projects = group(page, 'Proyek');
    await onlyOwn(projects.getByRole('listitem'), world);
    await expect(projects).not.toContainText('Proyek Luar');
    const above = await notice(page).boundingBox();
    const list = await projects.boundingBox();
    expect(above !== null && list !== null && above.y < list.y, 'the cut notice stands above the list (UXS-33)').toBe(true);
    // The term never enters the address or the title (IA §11; DP-02).
    expect(page.url()).not.toContain(run.bulk);
    expect((await page.title()).toLowerCase()).not.toContain(run.bulk);

    await guardsClean(page, testInfo, [world.own.company, world.other.company]);
    await clean(captured, testInfo);
});

test('CJ-12 on the phone at 390 px: the full-screen search from the bar, the cut list and a number, each within the Admin\'s company (AC-23; UXS-33; D-IA-04; criterion 4)', async ({ page }, testInfo) => {
    const captured = watch(page);
    await page.setViewportSize(DESK);
    await signedIn(page, 'cj12-ponsel');
    const run = stamps();
    const world = await searchWorld(page, run);
    await page.setViewportSize(PHONE);

    // PG-009 from the phone's bar (NAV-009): the bar's *Cari* is every account's.
    await page.goto('/proyek');
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
    await page.getByRole('navigation', { name: 'Navigasi utama' }).last().getByRole('button', { name: 'Cari' }).click();
    await page.waitForURL('**/cari');
    await expect(page.locator('header').getByRole('link', { name: 'Kembali' })).toHaveAttribute('href', /\/proyek$/);

    await look(page, capitalized(run.bulk));
    await expect(notice(page)).toHaveText(MSG_23);
    await onlyOwn(group(page, 'Proyek').getByRole('listitem'), world);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), 'no sideways scroll at 390 px').toBe(true);

    await look(page, world.own.project_number);
    await expect(exact(page).getByRole('link')).toHaveCount(1);
    await expect(exact(page)).toContainText(world.own.code);

    await look(page, 'Qv');
    await expect(notice(page)).toHaveText(MSG_24);
    await expect(group(page, 'Produk / Jasa')).toContainText(world.own.prefixed);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), 'no sideways scroll at 390 px').toBe(true);

    await page.setViewportSize(DESK);
    await guardsClean(page, testInfo, [world.own.company, world.other.company]);
    await clean(captured, testInfo);
});

import { type Page, test } from '@playwright/test';

import { clean, guardsClean, watch } from '../journey-fixtures';
import { adminActor, DESK, expect, grant, merged, newAccount, noOverflow, nothingStored, PHONE, runId, signInAt, slow } from '../journey-k2-fixtures';
import { numberingCompany } from '../numbering-fixtures';
import { quotationRun } from '../quotation-fixtures';

// TL-09, TL-10's switch case (TASK-064; CJ-18; AC-14; LOCALIZATION §3.7; CA-08; architecture challenge 17b; criteria 6, 12): the account
// menu's *Bahasa tampilan* (ROUTE-118; INT-002) switched on a page with unsent input — PG-014's draft at the desk, PG-065 on the phone at
// 390 —, the page, its data and the typed input kept by a visit that keeps the page's state, never a full reload, the layout without a
// horizontal scroll and nothing stored on the device; then "Keluar" and a new sign-in in the chosen language, kept in `users.locale`.
// Each test watches its console and network from its first step and ends with ReconcileGuards clean for its company.

test.describe.configure({ timeout: 300_000 });

/** A mark on the page's own window: still there after the switch only if the switch never reloaded the document. */
async function markDocument(page: Page): Promise<void> {
    await page.evaluate(() => {
        Reflect.set(window, '__cj18', true);
    });
}

async function sameDocument(page: Page): Promise<boolean> {
    return page.evaluate(() => Reflect.get(window, '__cj18') === true);
}

async function toEnglish(page: Page): Promise<void> {
    await page.getByRole('button', { name: /Menu akun/ }).click();
    await page.getByRole('menuitemcheckbox', { name: 'English' }).click();
}

/** "Sign out" in English, through MSG-35, to sign-in. */
async function signOutInEnglish(page: Page): Promise<void> {
    await page.getByRole('button', { name: /Account menu/ }).click();
    await page.getByRole('menuitem', { name: 'Sign out' }).click();
    await page.getByRole('alertdialog').getByRole('button', { name: 'Sign out' }).click();
    await page.waitForURL('**/masuk');
}

/** Nothing of the language on the device: neither browser storage nor a cookie names it (LOCALIZATION §2). */
async function noLanguageStored(page: Page): Promise<void> {
    expect(await page.evaluate(() => [...Object.keys(window.localStorage), ...Object.keys(window.sessionStorage)].filter((key) => /bahasa|locale|lang/i.test(key)))).toEqual([]);
    expect((await page.context().cookies()).map((cookie) => cookie.name).filter((cookie) => /bahasa|locale|lang/i.test(cookie))).toEqual([]);
}

test('CJ-18 at the desk: English on PG-014 with an unsent description keeps the page, its data and the input, then holds across a new sign-in (AC-14; LOCALIZATION §3.7; criterion 6)', async ({ page, browser }, testInfo) => {
    const captured = watch(page);
    await page.setViewportSize(DESK);
    const run = runId('cj18', testInfo);
    const q = await quotationRun(page, run, { stage: 'draft' });
    const a = await adminActor(browser, testInfo, page, 'cj18a', q.company, { profile: 'quotation', viewport: DESK, goLive: `${String(new Date().getFullYear())}-01-01` });
    const p = a.page;
    const description = p.locator('#line-0 input').first();
    const product = `Buku Uji ${q.code} 1`;

    await p.goto(`/proyek/${q.project}/penawaran`);
    await slow(p.getByRole('heading', { level: 1 })).toHaveText(`Penawaran ${q.number}`);
    await expect(p.locator('#konten')).toContainText(product);
    const typed = `Belum dikirim ${run}`;
    await description.fill(typed);
    await markDocument(p);

    await toEnglish(p);
    await slow(p.getByRole('heading', { level: 1 })).toHaveText(`Quotation ${q.number}`);
    await p.keyboard.press('Escape');
    expect(await sameDocument(p), 'the switch is a visit that keeps the page, never a reload (WFL-04)').toBe(true);
    await expect(description, 'the unsent input kept').toHaveValue(typed);
    await expect(p.getByLabel('Description').first()).toHaveValue(typed);
    await expect(p.locator('#konten'), 'the page\'s data kept').toContainText(product);
    await expect(p.getByRole('button', { name: 'Save draft' })).toBeVisible();
    await noOverflow(p);
    await noLanguageStored(p);
    await nothingStored(p, [typed]);

    // The language is the account's (`users.locale`): a new sign-in opens in English.
    await signOutInEnglish(p);
    await signInAt(p, a.account);
    await expect(p.getByRole('button', { name: /Account menu/ })).toBeVisible();
    await p.goto(`/proyek/${q.project}/penawaran`);
    await slow(p.getByRole('heading', { level: 1 })).toHaveText(`Quotation ${q.number}`);
    await noOverflow(p);

    await guardsClean(page, testInfo, [q.company]);
    await clean(merged(captured, a.captured), testInfo);
    await a.context.close();
});

test('CJ-18 on the phone: English on PG-065 with an unsent password keeps the page and the input, the layout within 390, then holds across a new sign-in (AC-14; CA-08; criterion 6)', async ({ page }, testInfo) => {
    const captured = watch(page);
    await page.setViewportSize(PHONE);
    const company = await numberingCompany(page, null);
    const account = await newAccount(page, 'cj18p');
    await grant(page, account, company, 'view');
    await signInAt(page, account);

    await page.goto('/akun');
    await slow(page.getByRole('heading', { level: 1 })).toHaveText('Akun Saya');
    await expect(page.locator('#konten')).toContainText(account.email);
    await page.getByLabel('Kata sandi saat ini').fill(account.password);
    await markDocument(page);

    await toEnglish(page);
    await slow(page.getByRole('heading', { level: 1 })).toHaveText('My account');
    await page.keyboard.press('Escape');
    expect(await sameDocument(page), 'the switch keeps the page, never a reload').toBe(true);
    await expect(page.getByLabel('Current password'), 'the unsent input kept').toHaveValue(account.password);
    await expect(page.locator('#konten'), 'the page\'s data kept').toContainText(account.email);
    await noOverflow(page);
    await noLanguageStored(page);
    await nothingStored(page, [account.password]);

    await signOutInEnglish(page);
    await signInAt(page, account);
    await expect(page.getByRole('button', { name: /Account menu/ })).toBeVisible();
    await page.goto('/akun');
    await slow(page.getByRole('heading', { level: 1 })).toHaveText('My account');
    await noOverflow(page);

    await guardsClean(page, testInfo, [company]);
    await clean(captured, testInfo);
});

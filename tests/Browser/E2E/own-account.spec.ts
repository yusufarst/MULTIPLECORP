import { expect, type Page, test } from '@playwright/test';

import { disarmGoogle, fakeGoogle, nothingKept, signedIn, signIn, stepUp, totp } from '../account-fixtures';

// TL-09 (TASK-023; J-ACC-01, CJ-10, CJ-18; AUTH_JOURNEYS §2.9): PG-065's part of the journeys against the real routes —
// the password change and the sign-in with the new one, MSG-29 naming the event; the enrolment, its codes and their
// viewing, nothing of them kept in history or on the device (SM-13); the Google link through the faked provider and
// the unlink; and the language switch on a page with unsent input, kept across a re-login.

// A switch or a step is a visit and, the first time, a language's bundle: on a loaded runner it may take longer than
// the default five seconds of an assertion.
const VISIT = 15_000;

const NEW_PASSWORD = 'Pelangi-Biru#8492';

/** The form of a code-taking step, found by its button. */
function step(page: Page, button: string) {
    return page.locator('form').filter({ has: page.getByRole('button', { name: button, exact: true }) });
}

// Each journey signs in, visits and signs in again: longer than the default half minute on a loaded runner.
test.describe.configure({ timeout: 120_000 });

test.afterEach(async ({ page }) => {
    await disarmGoogle(page);
});

test('switches the language with unsent input, changes the password and keeps the language across a re-login (ROUTE-111, ROUTE-118; CJ-18; criteria 2, 8, 9)', async ({ page }) => {
    const account = await signedIn(page, 'akun-sandi');

    await page.getByRole('button', { name: /Menu akun/ }).click();
    await page.getByRole('menuitem', { name: 'Akun Saya' }).click();
    await page.waitForURL('**/akun');
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Akun Saya');

    // CJ-18: the switch keeps the page and its unsent input, and stores nothing on the device.
    await page.getByLabel('Kata sandi saat ini').fill(account.password);
    await page.getByRole('button', { name: /Menu akun/ }).click();
    await page.getByRole('menuitemcheckbox', { name: 'English' }).click();
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('My account', { timeout: VISIT });
    // A checkbox item keeps the menu open for another choice; it closes on Escape.
    await page.keyboard.press('Escape');
    await expect(page.getByLabel('Current password')).toHaveValue(account.password);
    expect(await page.evaluate(() => [...Object.keys(window.localStorage), ...Object.keys(window.sessionStorage)].filter((name) => /bahasa|locale|lang/i.test(name)))).toEqual([]);
    await nothingKept(page, [account.password]);

    await page.getByRole('textbox', { name: /^New password/ }).fill(NEW_PASSWORD);
    await page.getByLabel('Repeat the new password').fill(NEW_PASSWORD);
    await page.getByRole('button', { name: 'Change Password' }).click();
    await expect(page.getByText('Password changed. The account\'s other sessions have ended.')).toBeVisible({ timeout: VISIT });
    await nothingKept(page, [account.password, NEW_PASSWORD]);

    await page.getByRole('button', { name: /Account menu/ }).click();
    await page.getByRole('menuitem', { name: 'Sign out' }).click();
    await page.getByRole('alertdialog').getByRole('button', { name: 'Sign out' }).click();
    await page.waitForURL('**/masuk', { timeout: VISIT });

    // The new password signs in; the account's language applies at once and MSG-29 names the change.
    await signIn(page, account, NEW_PASSWORD, 1);
    await expect(page.getByRole('button', { name: /Account menu/ })).toBeVisible({ timeout: VISIT });
    await expect(page.getByText(/A security change was made to this account: Password changed/)).toBeVisible();
});

test('returns with "Kembali" to the page the account menu was opened from (NAV-065)', async ({ page }) => {
    await signedIn(page, 'akun-kembali');
    const origin = new URL(page.url()).pathname;

    await page.getByRole('button', { name: /Menu akun/ }).click();
    await page.getByRole('menuitem', { name: 'Akun Saya' }).click();
    await page.waitForURL('**/akun');
    // A visit at the same address keeps the origin: the language switched there and back.
    await page.getByLabel('Bahasa tampilan').selectOption('en');
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('My account', { timeout: VISIT });
    await page.getByLabel('Display language').selectOption('id');
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Akun Saya', { timeout: VISIT });

    await page.getByRole('button', { name: 'Kembali' }).click();
    await page.waitForURL((url) => url.pathname === origin, { timeout: VISIT });
});

test('turns two-step verification on, shows the codes once and views them again, none kept (ROUTE-112, ROUTE-113; criteria 3, 4)', async ({ page }) => {
    const account = await signedIn(page, 'akun-totp', { plain: true });
    await page.goto('/akun');
    await expect(page.getByText('Belum aktif')).toBeVisible();

    await page.getByRole('button', { name: 'Aktifkan Verifikasi Dua Langkah' }).click();
    await stepUp(page, account.password);
    await expect(page.getByRole('img', { name: /Pindai kode QR/ })).toBeVisible({ timeout: VISIT });
    const key = (await page.locator('code').filter({ hasText: /^[A-Z2-7\s]+$/ }).first().innerText()).replace(/\s+/g, '');
    expect(key).toMatch(/^[A-Z2-7]{32}$/);

    await step(page, 'Konfirmasi').getByLabel('Kode verifikasi').fill(totp(key));
    await step(page, 'Konfirmasi').getByRole('button', { name: 'Konfirmasi' }).click();
    await expect(page.getByText('Verifikasi dua langkah aktif. Simpan kode pemulihan di bawah ini.')).toBeVisible({ timeout: VISIT });
    const shown = page.getByRole('list', { name: 'Kode pemulihan' }).getByRole('listitem');
    await expect(shown).toHaveCount(8);
    const first = await shown.allInnerTexts();
    await nothingKept(page, [key, ...first]);

    // A reload shows them no more: flash data, once.
    await page.reload();
    await expect(page.getByRole('list', { name: 'Kode pemulihan' })).toHaveCount(0);

    const viewing = totp(key, 1);
    await step(page, 'Lihat Kode').getByLabel('Kode verifikasi').fill(viewing);
    await step(page, 'Lihat Kode').getByRole('button', { name: 'Lihat Kode' }).click();
    // A changed factor asks the step-up again (AU-15).
    await stepUp(page, account.password);
    await expect(page.getByText('Kode pemulihan ditampilkan.')).toBeVisible({ timeout: VISIT });
    await expect(shown).toHaveText(first);
    await nothingKept(page, [key, ...first]);

    // The same code a second time is refused (M-39).
    await step(page, 'Buat Kode Baru').getByLabel('Kode verifikasi').fill(viewing);
    await step(page, 'Buat Kode Baru').getByRole('button', { name: 'Buat Kode Baru' }).click();
    await expect(page.getByText('Kode tidak cocok atau sudah tidak berlaku. Masukkan kode terbaru dari aplikasi autentikator.')).toBeVisible({ timeout: VISIT });
});

test('links Google through the faked provider with a current code, then unlinks it (ROUTE-102, ROUTE-116, ROUTE-117; criteria 6, 7)', async ({ page }) => {
    const account = await signedIn(page, 'akun-google');
    const subject = `sub-akun-${String(Date.now())}`;
    await fakeGoogle(page, subject);

    await page.goto('/akun');
    await expect(page.getByText('Belum tertaut')).toBeVisible();
    await page.getByRole('link', { name: 'Tautkan Akun Google' }).click();
    await page.waitForURL('**/akun', { timeout: VISIT });
    await expect(page.getByText('Akun Google sudah diverifikasi. Masukkan kode verifikasi untuk menautkannya.')).toBeVisible();

    await step(page, 'Konfirmasi Tautan').getByLabel('Kode verifikasi').fill(totp(account.secret ?? '', 1));
    await step(page, 'Konfirmasi Tautan').getByRole('button', { name: 'Konfirmasi Tautan' }).click();
    await stepUp(page, account.password);
    await expect(page.getByText('Akun Google ditautkan.')).toBeVisible({ timeout: VISIT });
    await expect(page.getByText(/^Tertaut ke /)).toBeVisible();
    await nothingKept(page, [subject]);

    await page.getByRole('button', { name: 'Lepas Tautan Google' }).click();
    await expect(page.getByText('Tautan Google dilepas. Sesi lain akun ini telah diakhiri.')).toBeVisible({ timeout: VISIT });
    await expect(page.getByText('Belum tertaut')).toBeVisible();
});

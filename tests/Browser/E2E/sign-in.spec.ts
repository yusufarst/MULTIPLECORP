import { expect, type Page, test } from '@playwright/test';

// TL-09 (TASK-017; E2E S2-J01; acceptance criteria 1, 4): the sign-in, reset and logout journeys against the real
// routes of PG-001 and PG-003 on the local stack, with a synthetic account and its reset link from the account
// fixture (routes/fixtures.php) — the generic answer of AU-04, the token taken out of the address (AU-12), the
// password set once, the session ended by "Keluar" with MSG-35 (INT-018).

type Account = { email: string; password: string; token: string };

let runs = 0;

async function account(page: Page): Promise<Account> {
    const response = await page.request.post('/__fixtures/akun', { data: { run: `e2e-${String(Date.now())}-${String((runs += 1))}` } });
    expect(response.ok()).toBe(true);

    return (await response.json()) as Account;
}

async function signIn(page: Page, email: string, password: string): Promise<void> {
    await page.goto('/masuk');
    await page.getByLabel('E-mail').fill(email);
    await page.getByLabel('Kata sandi', { exact: true }).fill(password);
    await page.getByRole('button', { name: 'Masuk', exact: true }).click();
}

test('signs in after AU-04\'s generic answer and logs out with MSG-35 (ROUTE-101, ROUTE-108; INT-101, INT-018)', async ({ page }) => {
    const { email, password } = await account(page);

    await signIn(page, email, 'salah-sekali');
    await expect(page.getByText('E-mail atau kata sandi salah.')).toBeVisible();
    await expect(page.getByLabel('E-mail')).toHaveValue(email);
    await expect(page.getByLabel('Kata sandi', { exact: true })).toHaveValue('');

    await page.getByLabel('Kata sandi', { exact: true }).fill(password);
    await page.getByRole('button', { name: 'Masuk', exact: true }).click();
    await page.waitForURL((url) => url.pathname === '/');
    await expect(page.getByRole('button', { name: /Menu akun/ })).toContainText('Rani Uji');

    await page.getByRole('button', { name: /Menu akun/ }).click();
    await page.getByRole('menuitem', { name: 'Keluar' }).click();
    const warning = page.getByRole('alertdialog', { name: 'Isian yang belum dikirim' });
    await expect(warning).toBeVisible();
    await warning.getByRole('button', { name: 'Keluar' }).click();
    await page.waitForURL('**/masuk');

    // The ended session shows nothing: the shell's address answers sign-in again.
    await page.goto('/');
    await page.waitForURL('**/masuk');
});

test('sets the password once from the link, the token out of the address, then signs in with it (ROUTE-003, ROUTE-106; AU-11, AU-12)', async ({ page }) => {
    const { email, token } = await account(page);

    await page.goto(`/atur-kata-sandi#${token}`);
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Atur kata sandi Anda');
    await expect.poll(() => new URL(page.url()).hash).toBe('');
    expect(await page.evaluate(() => JSON.stringify(window.history.state))).not.toContain(token);

    await page.getByLabel('Kata sandi baru', { exact: true }).fill('Rapat-Gudang-24!');
    await page.getByLabel('Ulangi kata sandi baru').fill('Rapat-Gudang-24!');
    await page.getByRole('button', { name: 'Simpan Kata Sandi' }).click();
    await page.waitForURL('**/masuk');
    await expect(page.getByRole('status')).toContainText('Kata sandi tersimpan. Masuk untuk melanjutkan.');

    await signIn(page, email, 'Rapat-Gudang-24!');
    await page.waitForURL((url) => url.pathname === '/');

    // Used once: the same link answers MSG-28.
    await page.context().clearCookies();
    await page.goto(`/atur-kata-sandi#${token}`);
    await page.getByLabel('Kata sandi baru', { exact: true }).fill('Rapat-Gudang-25!');
    await page.getByLabel('Ulangi kata sandi baru').fill('Rapat-Gudang-25!');
    await page.getByRole('button', { name: 'Simpan Kata Sandi' }).click();
    await expect(page.getByRole('alert')).toContainText('Tautan tidak berlaku. Minta tautan baru kepada Owner.');
});

test('answers a link without its token on the page, the form closed (AU-12)', async ({ page }) => {
    await page.goto('/atur-kata-sandi');

    await expect(page.getByRole('alert')).toContainText('Tautan tidak berlaku.');
    await expect(page.getByRole('button', { name: 'Simpan Kata Sandi' })).toBeDisabled();
});

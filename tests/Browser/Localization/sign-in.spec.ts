import { expect, test } from '@playwright/test';

// TL-10 (TASK-017; INT-001): PG-001 and PG-003 in both languages — no key shown as itself, the document title
// "{nama layar} · MultipleCorp" —; the switch keeps PG-001's typed e-mail and never its password, and keeps
// PG-003's token in memory, so the password is still set from the same link (AU-12; LOCALIZATION §3.7).

const KEY_SHOWN = /\b(messages|glossary|pages|auth_rules|forms|actions)\.[a-z0-9_]+/;

for (const [lang, heading] of [['id', 'Masuk ke MultipleCorp'], ['en', 'Sign in to MultipleCorp']] as const) {
    test(`renders PG-001 and PG-003 in ${lang}`, async ({ page }) => {
        await page.goto(`/masuk?bahasa=${lang}`);
        await expect(page.locator('html')).toHaveAttribute('lang', lang);
        await expect(page.getByRole('heading', { level: 1 })).toHaveText(heading);
        await expect(page).toHaveTitle(/ · MultipleCorp$/);
        expect(await page.locator('body').innerText()).not.toMatch(KEY_SHOWN);

        await page.goto(`/atur-kata-sandi?bahasa=${lang}#AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA`);
        await expect(page.locator('html')).toHaveAttribute('lang', lang);
        expect(await page.locator('body').innerText()).not.toMatch(KEY_SHOWN);
    });
}

test('keeps the typed e-mail and never the password across the switch on PG-001 (INT-001)', async ({ page }) => {
    await page.goto('/masuk');
    await page.getByLabel('E-mail').fill('rani@contoh.test');
    await page.getByLabel('Kata sandi', { exact: true }).fill('rahasia-sekali');

    await page.getByRole('button', { name: 'EN' }).click();
    await expect(page.locator('html')).toHaveAttribute('lang', 'en');
    await expect(page.getByLabel('Email')).toHaveValue('rani@contoh.test');
    await expect(page.getByLabel('Password', { exact: true })).toHaveValue('');
});

test('keeps PG-003\'s token in memory across the switch, the password set from the same link (INT-001; AU-12)', async ({ page }) => {
    const account = (await (await page.request.post('/__fixtures/akun', { data: { run: `lang-${String(Date.now())}` } })).json()) as { token: string };

    await page.goto(`/atur-kata-sandi#${account.token}`);
    await page.getByRole('button', { name: 'EN' }).click();
    await expect(page.locator('html')).toHaveAttribute('lang', 'en');

    await page.getByLabel('New password', { exact: true }).fill('Rapat-Gudang-24!');
    await page.getByLabel('Repeat the new password').fill('Rapat-Gudang-24!');
    await page.getByRole('button', { name: 'Save Password' }).click();
    await page.waitForURL('**/masuk*');
});

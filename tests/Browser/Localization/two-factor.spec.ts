import { expect, test } from '@playwright/test';

// TL-10 (TASK-018; INT-001): PG-002 and PG-004 in both languages — no key shown as itself, the document title
// "{nama layar} · MultipleCorp" —; PG-002, before sign-in, in the language carried as `bahasa` across the sign-in
// pages (NV-07), PG-004, inside the enrolment-only session, in the account's own language (AU-13).

const KEY_SHOWN = /\b(messages|glossary|pages|auth_rules|auth|forms|actions|dialogs)\.[a-z0-9_]+/;

for (const [lang, challenge, enrolment] of [['id', 'Masukkan kode verifikasi', 'Aktifkan Verifikasi Dua Langkah'], ['en', 'Enter the verification code', 'Turn on two-step verification']] as const) {
    test(`renders PG-002 and PG-004 in ${lang}`, async ({ page }) => {
        for (const kind of ['totp', 'owner'] as const) {
            const account = (await (await page.request.post('/__fixtures/akun', { data: { run: `lang-${kind}-${lang}-${String(Date.now())}`, kind, locale: lang } })).json()) as { email: string; password: string };
            await page.goto(`/masuk?bahasa=${lang}`);
            await page.locator('input[name="email"]').fill(account.email);
            await page.locator('input[name="password"]').fill(account.password);
            await page.locator('button[type="submit"]').click();
            await page.waitForURL(kind === 'totp' ? '**/masuk/verifikasi*' : '**/verifikasi-dua-langkah*');

            await expect(page.locator('html')).toHaveAttribute('lang', lang);
            await expect(page.getByRole('heading', { level: 1 })).toHaveText(kind === 'totp' ? challenge : enrolment);
            await expect(page).toHaveTitle(/ · MultipleCorp$/);
            expect(await page.locator('body').innerText()).not.toMatch(KEY_SHOWN);
            await page.context().clearCookies();
        }
    });
}

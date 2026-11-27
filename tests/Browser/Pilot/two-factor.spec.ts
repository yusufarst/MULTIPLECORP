import { createHmac } from 'node:crypto';

import { expect, type Page, test } from '@playwright/test';

// APPR-042's pilot on the real stack (DIR-134 E5): the recovery codes' stage of PG-004 — "Simpan kode pemulihan", the
// codes shown once and kept out of the history entry, and "Lanjutkan" open only after the Owner confirms the codes are
// saved. The layers' two-factor spec stays unchanged and fails at the heading of this stage, a deliberate difference.
// The CI workflow runs this file apart and logs its results only: no screenshot, trace or message of it is kept.

type Account = { email: string; password: string };

async function account(page: Page): Promise<Account> {
    const response = await page.request.post('/__fixtures/akun', { data: { run: `pilot-tf-${String(Date.now())}`, kind: 'owner' } });
    expect(response.ok()).toBe(true);

    return (await response.json()) as Account;
}

/** RFC 6238 with the project's parameters: six digits, 30 seconds, HMAC-SHA1 over the base32 secret. */
function totp(secret: string): string {
    const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    let bits = '';

    for (const character of secret.replace(/\s+/g, '').toUpperCase()) {
        bits += alphabet.indexOf(character).toString(2).padStart(5, '0');
    }

    const bytes = Buffer.from((bits.match(/.{8}/g) ?? []).map((byte) => parseInt(byte, 2)));
    const counter = Buffer.alloc(8);
    counter.writeBigUInt64BE(BigInt(Math.floor(Date.now() / 1000 / 30)));
    const digest = createHmac('sha1', bytes).update(counter).digest();
    const start = (digest[digest.length - 1] ?? 0) & 0x0f;

    return String((digest.readUInt32BE(start) & 0x7fffffff) % 1_000_000).padStart(6, '0');
}

test('enrols an Owner without TOTP — the recovery codes stage: shown once, kept out of the history, "Lanjutkan" after the confirmation (E5)', async ({ page }) => {
    const { email, password } = await account(page);

    await page.goto('/masuk');
    await page.getByLabel('E-mail').fill(email);
    await page.getByLabel('Kata sandi', { exact: true }).fill(password);
    await page.getByRole('button', { name: 'Masuk', exact: true }).click();
    await page.waitForURL('**/verifikasi-dua-langkah');
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Aktifkan Verifikasi Dua Langkah');

    await expect(page.getByRole('img', { name: 'Atur aplikasi autentikator' })).toBeVisible();
    const key = (await page.locator('code').innerText()).replace(/\s+/g, '');
    expect(key).toMatch(/^[A-Z2-7]{32}$/);
    await page.getByLabel('Kode 6 digit dari aplikasi autentikator').fill(totp(key));
    await page.getByRole('button', { name: 'Aktifkan' }).click();

    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Simpan kode pemulihan');
    // Two live regions on this page (the line and the copy confirmation): the line by its text.
    await expect(page.getByRole('status').filter({ hasText: 'Simpan kode pemulihan ini. Kode hanya ditampilkan sekali.' })).toBeVisible();
    await expect(page.getByRole('list', { name: 'Kode pemulihan' }).getByRole('listitem')).toHaveCount(8);
    expect(await page.evaluate(() => JSON.stringify(window.history.state))).not.toContain(key);

    const proceed = page.getByRole('button', { name: 'Lanjutkan' });
    await expect(proceed).toBeDisabled();
    await page.getByRole('checkbox', { name: 'Saya sudah menyimpan kode pemulihan di tempat yang aman.' }).check();
    await expect(proceed).toBeEnabled();
    await proceed.click();
    await page.waitForURL((url) => ['/beranda', '/pekerjaan'].includes(url.pathname));
});

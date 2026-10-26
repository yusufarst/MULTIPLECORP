import { createHmac } from 'node:crypto';

import { expect, type Page, test } from '@playwright/test';

// TL-09 (TASK-018; E2E S2-J01; AUTH_JOURNEYS §§2.1, 2.3, 2.4; acceptance criteria 1, 3, 6): the challenge after the
// password with the generic code answer, the recovery code into the enrolment-only session and its new device, and
// the enrolment of an Owner without TOTP — each against the real routes of PG-001, PG-002 and PG-004, with synthetic
// accounts from the account fixture (routes/fixtures.php). The codes come from the test's own authenticator.

type Account = { email: string; password: string; secret?: string; recovery_codes?: string[] };

let runs = 0;

async function account(page: Page, kind: 'totp' | 'owner'): Promise<Account> {
    const response = await page.request.post('/__fixtures/akun', { data: { run: `tf-${String(Date.now())}-${String((runs += 1))}`, kind } });
    expect(response.ok()).toBe(true);

    return (await response.json()) as Account;
}

/** RFC 6238 with the project's parameters: six digits, 30 seconds, HMAC-SHA1 over the base32 secret. */
function totp(secret: string, offset = 0): string {
    const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    let bits = '';

    for (const character of secret.replace(/\s+/g, '').toUpperCase()) {
        bits += alphabet.indexOf(character).toString(2).padStart(5, '0');
    }

    const bytes = Buffer.from((bits.match(/.{8}/g) ?? []).map((byte) => parseInt(byte, 2)));
    const counter = Buffer.alloc(8);
    counter.writeBigUInt64BE(BigInt(Math.floor(Date.now() / 1000 / 30) + offset));
    const digest = createHmac('sha1', bytes).update(counter).digest();
    const start = (digest[digest.length - 1] ?? 0) & 0x0f;

    return String((digest.readUInt32BE(start) & 0x7fffffff) % 1_000_000).padStart(6, '0');
}

async function password(page: Page, email: string, secret: string): Promise<void> {
    await page.goto('/masuk');
    await page.getByLabel('E-mail').fill(email);
    await page.getByLabel('Kata sandi', { exact: true }).fill(secret);
    await page.getByRole('button', { name: 'Masuk', exact: true }).click();
}

async function enrolNewDevice(page: Page): Promise<void> {
    await expect(page.getByRole('img', { name: 'Atur aplikasi autentikator' })).toBeVisible();
    const key = (await page.locator('code').innerText()).replace(/\s+/g, '');
    expect(key).toMatch(/^[A-Z2-7]{32}$/);

    await page.getByLabel('Kode 6 digit dari aplikasi autentikator').fill(totp(key));
    await page.getByRole('button', { name: 'Aktifkan' }).click();
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Kode pemulihan');
    await expect(page.getByRole('status')).toContainText('Simpan kode pemulihan ini. Kode hanya ditampilkan sekali.');
    await expect(page.getByRole('list', { name: 'Kode pemulihan' }).getByRole('listitem')).toHaveCount(8);
    expect(await page.evaluate(() => JSON.stringify(window.history.state))).not.toContain(key);

    await page.getByRole('button', { name: 'Lanjutkan' }).click();
    await page.waitForURL((url) => url.pathname === '/');
}

test('signs in with the password and then one code, a wrong code answered generically (ROUTE-002, ROUTE-104; AU-16)', async ({ page }) => {
    const { email, password: secret, secret: key } = await account(page, 'totp');

    await password(page, email, secret);
    await page.waitForURL('**/masuk/verifikasi');
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Masukkan kode verifikasi');

    await page.getByLabel('Kode 6 digit dari aplikasi autentikator').fill('000000');
    await page.getByRole('button', { name: 'Verifikasi' }).click();
    await expect(page.getByText('Kode tidak cocok atau sudah tidak berlaku. Masukkan kode terbaru dari aplikasi autentikator.')).toBeVisible();

    await page.getByLabel('Kode 6 digit dari aplikasi autentikator').fill(totp(key ?? ''));
    await page.getByRole('button', { name: 'Verifikasi' }).click();
    await page.waitForURL((url) => url.pathname === '/');
    await expect(page.getByRole('button', { name: /Menu akun/ })).toContainText('Rani Uji');
});

test('returns from PG-002 to PG-001 with the e-mail kept, the attempt discarded (NAV-002; criterion 6)', async ({ page }) => {
    const { email, password: secret, secret: key } = await account(page, 'totp');

    await password(page, email, secret);
    await page.waitForURL('**/masuk/verifikasi');
    await page.getByRole('button', { name: 'Kembali' }).click();
    await page.waitForURL('**/masuk');
    await expect(page.getByLabel('E-mail')).toHaveValue(email);

    // The discarded attempt completes nothing: PG-002 answers PG-001 again.
    await page.goto('/masuk/verifikasi');
    await page.waitForURL('**/masuk');
    expect(key).toBeDefined();
});

test('uses a recovery code into the enrolment-only session and sets up a new device (ROUTE-105, ROUTE-004, ROUTE-107; AU-20 b)', async ({ page }) => {
    const { email, password: secret, recovery_codes: codes } = await account(page, 'totp');

    await password(page, email, secret);
    await page.waitForURL('**/masuk/verifikasi');
    await page.getByRole('button', { name: 'Gunakan kode pemulihan' }).click();
    await page.getByLabel('Kode pemulihan').fill(codes?.[0] ?? '');
    await page.getByRole('button', { name: 'Verifikasi' }).click();

    await page.waitForURL('**/verifikasi-dua-langkah');
    await expect(page.getByText('Atur ulang aplikasi autentikator untuk melanjutkan.')).toBeVisible();

    // Nothing else is reachable until the device is confirmed (AU-18).
    await page.goto('/');
    await page.waitForURL('**/verifikasi-dua-langkah');

    await enrolNewDevice(page);
});

test('enrols an Owner without TOTP from its enrolment-only session (ROUTE-004, ROUTE-107; AU-18; criterion 1)', async ({ page }) => {
    const { email, password: secret } = await account(page, 'owner');

    await password(page, email, secret);
    await page.waitForURL('**/verifikasi-dua-langkah');
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Aktifkan Verifikasi Dua Langkah');

    await enrolNewDevice(page);
});

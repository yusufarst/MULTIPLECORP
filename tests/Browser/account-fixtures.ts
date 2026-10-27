import { createHmac } from 'node:crypto';

import { expect, type Page } from '@playwright/test';

// PG-065's browser fixtures (TASK-023): a synthetic account for the run signed in through PG-001 — and PG-002 when it
// has TOTP —, the test's own authenticator, and the faked Google of ROUTE-102 and ROUTE-103 for the link attempt
// (routes/fixtures.php; SM-20 — no test calls Google).

export type Account = { id: string; email: string; password: string; secret?: string; recovery_codes?: string[] };

let runs = 0;

/** RFC 6238 with the project's parameters: six digits, 30 seconds, HMAC-SHA1 over the base32 secret. */
export function totp(secret: string, offset = 0): string {
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

/** Waits out the last seconds of the current 30-second step, so that a code read now is still current when the
    server checks it on a slow runner. */
export async function freshStep(): Promise<void> {
    const left = 30_000 - (Date.now() % 30_000);

    if (left < 6_000) {
        await new Promise((resolve) => setTimeout(resolve, left + 250));
    }
}

/** The sign-in of PG-001 and, with a secret, PG-002's code of the current step — or of a later one (`offset`) when an
    earlier code of the account may have taken this step (M-39). PG-065's code-taking steps then use the next. */
export async function signIn(page: Page, account: Account, password = account.password, offset = 0): Promise<void> {
    await page.goto('/masuk');
    await page.getByLabel('E-mail').fill(account.email);
    await page.getByLabel('Kata sandi', { exact: true }).fill(password);
    await page.getByRole('button', { name: 'Masuk', exact: true }).click();

    if (account.secret !== undefined) {
        await page.waitForURL('**/masuk/verifikasi');
        await freshStep();
        await page.getByLabel('Kode 6 digit dari aplikasi autentikator').fill(totp(account.secret, offset));
        await page.getByRole('button', { name: 'Verifikasi' }).click();
    }

    await page.waitForURL((url) => !url.pathname.startsWith('/masuk'));
}

/** A fresh account for the run — with a confirmed TOTP unless `plain`, an Owner with it when `owner` —, signed in. */
export async function signedIn(page: Page, prefix: string, options: { plain?: boolean; owner?: boolean; locale?: 'id' | 'en' } = {}): Promise<Account> {
    const kind = options.owner === true ? 'owner' : options.plain === true ? 'plain' : 'totp';
    const response = await page.request.post('/__fixtures/akun', {
        data: { run: `${prefix}-${String(Date.now())}-${String((runs += 1))}`, kind, totp: options.owner === true, locale: options.locale ?? 'id' },
    });
    expect(response.ok()).toBe(true);
    const account = (await response.json()) as Account;
    await signIn(page, account);

    return account;
}

/**
 * Arms the faked Google for a subject. ROUTE-102's answer is read without following it — a redirect hop is never
 * routed, so Google itself is never reached —, its state and nonce taken from the authorization address, and the
 * browser is sent straight to the callback with them.
 */
export async function fakeGoogle(page: Page, subject: string): Promise<void> {
    await page.request.post('/__fixtures/google', { data: { sub: subject } });
    await page.route(
        (url) => url.pathname === '/masuk/google',
        async (route) => {
            const answer = await route.fetch({ maxRedirects: 0 });
            const asked = new URL(answer.headers().location ?? '');

            if (asked.host !== 'accounts.google.com') {
                await route.fulfill({ status: 200, contentType: 'text/html', headers: { 'set-cookie': answer.headers()['set-cookie'] ?? '' }, body: `<meta http-equiv="refresh" content="0;url=${asked.toString()}">` });

                return;
            }

            await page.request.post('/__fixtures/google', { data: { nonce: asked.searchParams.get('nonce') } });
            const callback = new URL('/masuk/google/callback', new URL(page.url()).origin);
            callback.search = new URLSearchParams({ state: asked.searchParams.get('state') ?? '', code: 'kode-fixture' }).toString();
            await route.fulfill({ status: 200, contentType: 'text/html', headers: { 'set-cookie': answer.headers()['set-cookie'] ?? '' }, body: `<meta http-equiv="refresh" content="0;url=${callback.toString()}">` });
        },
    );
    await page.route('https://accounts.google.com/**', () => {
        throw new Error('A test reached Google (SM-20).');
    });
}

export async function disarmGoogle(page: Page): Promise<void> {
    await page.request.post('/__fixtures/google', { data: { off: true } });
}

/** Nothing of a shown code, key or QR kept: not in the history's entries, not in the device's storage (SM-13). */
export async function nothingKept(page: Page, secrets: string[]): Promise<void> {
    const kept = await page.evaluate(() => JSON.stringify([window.history.state, Object.entries(window.localStorage), Object.entries(window.sessionStorage)]));

    for (const secret of secrets) {
        expect(kept).not.toContain(secret);
    }
}

/** The step-up the shell asks at the first sensitive step of a session (AU-15; IP-10): the password, then the same
    command again. */
export async function stepUp(page: Page, password: string): Promise<void> {
    const dialog = page.getByRole('alertdialog', { name: 'Konfirmasi Kata Sandi' });
    await expect(dialog).toBeVisible({ timeout: 15_000 });
    await dialog.getByLabel('Kata sandi').fill(password);
    await dialog.getByRole('button', { name: 'Lanjutkan' }).click();
    await expect(dialog).toHaveCount(0, { timeout: 15_000 });
}

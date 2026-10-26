import { createHmac } from 'node:crypto';

import { type Page } from '@playwright/test';

// PG-061's browser fixtures (TASK-021): an Owner with a confirmed TOTP signed in through PG-001 and PG-002, and a
// synthetic company for the run with go-live — whose store is TASK-077's — armed or cleared (routes/fixtures.php).

type Account = { email: string; password: string; secret: string };

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

export async function ownerSignedIn(page: Page, run: string, locale: 'id' | 'en' = 'id'): Promise<void> {
    const account = (await (await page.request.post('/__fixtures/akun', { data: { run, kind: 'owner', totp: true, locale } })).json()) as Account;
    await page.goto('/masuk');
    await page.getByLabel('E-mail').fill(account.email);
    await page.getByLabel('Kata sandi', { exact: true }).fill(account.password);
    await page.getByRole('button', { name: 'Masuk', exact: true }).click();
    await page.waitForURL('**/masuk/verifikasi');
    await page.getByLabel('Kode 6 digit dari aplikasi autentikator').fill(totp(account.secret));
    await page.getByRole('button', { name: 'Verifikasi' }).click();
    await page.waitForURL((url) => !url.pathname.startsWith('/masuk'));
}

/** A company for the run; go-live recorded on the day given, or cleared. */
export async function numberingCompany(page: Page, goLive: string | null): Promise<string> {
    const answer = (await (await page.request.post('/__fixtures/penomoran', { data: { company: true, go_live: goLive ?? '' } })).json()) as { company: string };

    return answer.company;
}

export async function clearGoLive(page: Page): Promise<void> {
    await page.request.post('/__fixtures/penomoran', { data: { go_live: '' } });
}

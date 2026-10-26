import { createHmac } from 'node:crypto';

import AxeBuilder from '@axe-core/playwright';
import { expect, type Page, test } from '@playwright/test';

// TL-09, TL-10 and TL-12 (TASK-019; E2E S2-J01; AUTH_JOURNEYS §2.2; SM-20 — no test calls Google): the faked Google
// journey against the real ROUTE-102 and ROUTE-103 — Google's authorization address intercepted and answered with
// the callback, the token exchange answered by the local fixture (tests/Support/Fixtures/GoogleFixture.php) —, the
// linked account with active TOTP reaching PG-002's code step only, an unlinked one PG-001's one generic failure;
// the button in both languages and under WCAG 2.2 AA.

let runs = 0;

function totp(secret: string): string {
    const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    let bits = '';

    for (const character of secret.toUpperCase()) {
        bits += alphabet.indexOf(character).toString(2).padStart(5, '0');
    }

    const counter = Buffer.alloc(8);
    counter.writeBigUInt64BE(BigInt(Math.floor(Date.now() / 1000 / 30)));
    const digest = createHmac('sha1', Buffer.from((bits.match(/.{8}/g) ?? []).map((byte) => parseInt(byte, 2)))).update(counter).digest();
    const start = (digest[digest.length - 1] ?? 0) & 0x0f;

    return String((digest.readUInt32BE(start) & 0x7fffffff) % 1_000_000).padStart(6, '0');
}

/**
 * Arms the faked Google for a subject. ROUTE-102's answer is read without following it — a redirect hop is never
 * routed, so Google itself is never reached —, its state and nonce taken from the authorization address, and the
 * browser is sent straight to the callback with them.
 */
async function fakeGoogle(page: Page, subject: string): Promise<void> {
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
            // The session cookie ROUTE-102 set travels with the page that moves on to the callback.
            await route.fulfill({ status: 200, contentType: 'text/html', headers: { 'set-cookie': answer.headers()['set-cookie'] ?? '' }, body: `<meta http-equiv="refresh" content="0;url=${callback.toString()}">` });
        },
    );
    // Google is never reached: any request to it fails the test.
    await page.route('https://accounts.google.com/**', () => {
        throw new Error('A test reached Google (SM-20).');
    });
}

test.afterEach(async ({ page }) => {
    await page.request.post('/__fixtures/google', { data: { off: true } });
});

test('signs in with Google and then the code, never past Google alone (ROUTE-102, ROUTE-103; AU-22; K2; criterion 2)', async ({ page }) => {
    const subject = `sub-${String(Date.now())}-${String((runs += 1))}`;
    const account = (await (await page.request.post('/__fixtures/akun', { data: { run: `g-${subject}`, kind: 'totp', google: subject } })).json()) as { secret: string };
    await fakeGoogle(page, subject);

    await page.goto('/masuk');
    await page.getByRole('link', { name: 'Lanjutkan dengan Google' }).click();
    await page.waitForURL('**/masuk/verifikasi');

    // After Google, a code only: no recovery code, the disclosure instead (AU-19; AU-22).
    await expect(page.getByRole('button', { name: 'Gunakan kode pemulihan' })).toHaveCount(0);
    await expect(page.getByRole('button', { name: 'Tidak bisa memakai aplikasi?' })).toBeVisible();

    // Nothing is reachable before the code: the shell answers PG-001, whose view discards the attempt.
    await page.goto('/');
    await page.waitForURL('**/masuk');

    await page.goto('/masuk');
    await page.getByRole('link', { name: 'Lanjutkan dengan Google' }).click();
    await page.waitForURL('**/masuk/verifikasi');
    await page.getByLabel('Kode 6 digit dari aplikasi autentikator').fill(totp(account.secret));
    await page.getByRole('button', { name: 'Verifikasi' }).click();
    await page.waitForURL((url) => url.pathname === '/');
    await expect(page.getByRole('button', { name: /Menu akun/ })).toContainText('Rani Uji');
});

test('answers an unlinked Google account with one generic failure on PG-001 (AU-22; criterion 1)', async ({ page }) => {
    await fakeGoogle(page, `sub-tak-tertaut-${String(Date.now())}`);

    await page.goto('/masuk');
    await page.getByRole('link', { name: 'Lanjutkan dengan Google' }).click();
    await page.waitForURL('**/masuk');
    await expect(page.getByRole('alert')).toHaveText('Tidak dapat masuk dengan Google. Masuk dengan e-mail dan kata sandi.');
    await expect(page.getByLabel('E-mail')).toBeVisible();
});

test('shows the Google button in both languages and under WCAG 2.2 AA (GR-13; TL-10, TL-12)', async ({ page }) => {
    await page.request.post('/__fixtures/google', { data: { sub: 'sub-tampilan' } });

    await page.goto('/masuk?bahasa=en');
    await expect(page.getByRole('link', { name: 'Continue with Google' })).toBeVisible();
    await page.goto('/masuk');
    await expect(page.getByRole('link', { name: 'Lanjutkan dengan Google' })).toBeVisible();
    await expect(page.getByText('Di perangkat bersama, keluar dari Google setelah selesai; kunci ponsel saat tidak dipakai.')).toBeVisible();

    expect((await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa']).analyze()).violations).toEqual([]);
});

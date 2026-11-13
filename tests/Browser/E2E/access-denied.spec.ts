import { expect, test } from '@playwright/test';

import { signedIn } from '../account-fixtures';

// TL-09 (TASK-095; D8; IP-21; AZ-11; WS-07): an address the account's capability refuses answers PG-005 inside the shell —
// the account's hints and its language —, rendered without a page error, alike an unknown address; a guest is still sent to
// sign-in; the Owner still opens the page. The refusal and its status are the route's own, unchanged.

const VISIT = 15_000;

test.describe.configure({ timeout: 120_000 });

test('answers an address the account cannot open with PG-005 in the shell, rendered without a page error, as an unknown address (IP-21; AZ-11; TASK-095)', async ({ page }) => {
    const errors: string[] = [];
    page.on('pageerror', (error) => { errors.push(error.message); });
    await signedIn(page, 'tolak', { plain: true });
    const system = page.locator('[data-system-page="not_found"]');

    // The Owner's review queue, opened by address: refused by the route's own capability.
    const refused = await page.goto('/tinjauan');
    expect(refused?.status()).toBe(404);
    await expect(system).toBeVisible({ timeout: VISIT });
    await expect(system).toContainText('Halaman tidak ditemukan.');
    await expect(page.getByRole('button', { name: /Menu akun/ }), 'the shell with the account\'s hints').toBeVisible();
    // The face without its reference code, which names the request (outcome.reference).
    const faceOf = async () => (await system.innerText()).split('\n').filter((line) => !line.startsWith('Kode referensi:')).join('\n');
    const face = await faceOf();

    // An unknown address answers alike (AZ-11).
    const unknown = await page.goto(`/tinjauan-${String(Date.now())}`);
    expect(unknown?.status()).toBe(404);
    await expect(system).toBeVisible({ timeout: VISIT });
    expect(await faceOf()).toBe(face);
    expect(errors, 'no page error').toEqual([]);

    // A guest's document request for it is still sent to sign-in.
    await page.context().clearCookies();
    await page.goto('/tinjauan');
    await expect(page).toHaveURL(/\/masuk$/, { timeout: VISIT });
});

test('still opens the review queue for the Owner (AZ-11; TASK-095)', async ({ page }) => {
    await signedIn(page, 'tolak-pemilik', { owner: true });
    const answer = await page.goto('/tinjauan');
    expect(answer?.status()).toBe(200);
    await expect(page.locator('[data-system-page]')).toHaveCount(0);
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible({ timeout: VISIT });
});

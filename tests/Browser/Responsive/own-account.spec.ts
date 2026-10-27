import { expect, test } from '@playwright/test';

import { signedIn, stepUp, totp } from '../account-fixtures';

// TL-11 (TASK-023; NAV-065 `[both]`; IA §14.2): PG-065's sections stacked on the phone and laid out on the desktop,
// with the device replacement's QR code shown, never a page scroll sideways.

// A sign-in through PG-001 and PG-002, a step-up and the checks: longer than the default half minute on WebKit.
test.describe.configure({ timeout: 90_000 });

for (const width of [320, 390, 768, 1280, 1680]) {
    test(`lays PG-065 out without a sideways scroll at ${String(width)} px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        const account = await signedIn(page, `akun-resp-${String(width)}`);

        await page.goto('/akun');
        await expect(page.getByRole('heading', { level: 1 })).toHaveText('Akun Saya');
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);

        await page.getByLabel('Kode dari perangkat saat ini').fill(totp(account.secret ?? '', 1));
        await page.getByRole('button', { name: 'Ganti Perangkat' }).click();
    await stepUp(page, account.password);
        await expect(page.getByRole('img', { name: /Pindai kode QR/ })).toBeVisible({ timeout: 15_000 });
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    });
}

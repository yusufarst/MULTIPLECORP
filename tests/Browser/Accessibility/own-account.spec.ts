import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';

import { signedIn, stepUp, totp } from '../account-fixtures';

// TL-12 (TASK-023): PG-065 — its sections, the open account menu with the language choice, and the device
// replacement's QR code and key — passes the WCAG 2.2 AA checks at the phone and desktop bands.

const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];

// A sign-in through PG-001 and PG-002, a step-up and the checks: longer than the default half minute on WebKit.
test.describe.configure({ timeout: 90_000 });

for (const width of [390, 1280]) {
    test(`finds no WCAG 2.2 AA violation on PG-065 at ${String(width)} px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        const account = await signedIn(page, `akun-a11y-${String(width)}`);

        await page.goto('/akun');
        await expect(page.getByRole('heading', { level: 1 })).toHaveText('Akun Saya');
        expect((await new AxeBuilder({ page }).withTags(TAGS).analyze()).violations).toEqual([]);

        await page.getByRole('button', { name: /Menu akun/ }).click();
        await expect(page.getByRole('menuitemcheckbox', { name: 'English' })).toBeVisible();
        // The menu fades in: its colours are measured at rest, never blended mid-motion.
        const menu = page.locator('[data-slot="dropdown-menu-content"]');
        await expect(menu).not.toHaveAttribute('data-starting-style');
        await expect(menu).toHaveCSS('opacity', '1');
        expect((await new AxeBuilder({ page }).withTags(TAGS).analyze()).violations).toEqual([]);
        await page.keyboard.press('Escape');

        await page.getByLabel('Kode dari perangkat saat ini').fill(totp(account.secret ?? '', 1));
        await page.getByRole('button', { name: 'Ganti Perangkat' }).click();
    await stepUp(page, account.password);
        await expect(page.getByRole('img', { name: /Pindai kode QR/ })).toBeVisible({ timeout: 15_000 });
        expect((await new AxeBuilder({ page }).withTags(TAGS).analyze()).violations).toEqual([]);
    });
}

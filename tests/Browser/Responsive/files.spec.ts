import { expect, test } from '@playwright/test';

import { ownerSignedIn } from '../numbering-fixtures';

// TL-11 (TASK-022; IP-16; mobile-first): the upload field — the phone's camera or file picker, its limits stated — at
// every band without a sideways scroll.

for (const width of [320, 390, 768, 1280, 1680]) {
    test(`lays the upload field out without a sideways scroll at ${String(width)} px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        await ownerSignedIn(page, `berkas-resp-${String(width)}-${String(Date.now())}`);

        await page.goto('/__fixtures/berkas');
        await expect(page.getByRole('button', { name: 'Pilih File' })).toBeVisible();
        await expect(page.getByText('PDF, JPG, PNG atau WebP, maksimal 20 MB.')).toBeVisible();
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    });
}

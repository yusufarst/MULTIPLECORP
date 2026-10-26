import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';

// TL-12 (TASK-017): PG-001 and PG-003 pass the WCAG 2.2 AA checks at the phone and desktop bands, with AU-04's
// answer shown on PG-001.

const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];

for (const width of [390, 1280]) {
    test(`finds no WCAG 2.2 AA violation on PG-001 and PG-003 at ${String(width)} px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });

        await page.goto('/masuk');
        await page.getByLabel('E-mail').fill(`tidak-ada-${String(Date.now())}@contoh.test`);
        await page.getByLabel('Kata sandi', { exact: true }).fill('salah-sekali');
        await page.getByRole('button', { name: 'Masuk', exact: true }).click();
        await expect(page.getByText('E-mail atau kata sandi salah.')).toBeVisible();
        expect((await new AxeBuilder({ page }).withTags(TAGS).analyze()).violations).toEqual([]);

        await page.goto('/atur-kata-sandi#AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA');
        await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
        expect((await new AxeBuilder({ page }).withTags(TAGS).analyze()).violations).toEqual([]);
    });
}

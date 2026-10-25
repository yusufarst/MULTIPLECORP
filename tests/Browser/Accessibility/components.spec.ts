import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';

// TL-12 (TASK-015; acceptance criterion 2): the components pass the WCAG 2.2 AA checks in both languages, at the
// phone and desktop bands, with their overlays open — names, roles and states announced, focus visible.

const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];

for (const lang of ['id', 'en'] as const) {
    for (const width of [390, 1280]) {
        test(`finds no WCAG 2.2 AA violation in ${lang} at ${String(width)} px`, async ({ page }) => {
            await page.setViewportSize({ width, height: 900 });
            await page.goto(`/__fixtures/komponen?bahasa=${lang}`);
            await expect(page.getByRole('heading', { level: 1 })).toBeVisible();

            const results = await new AxeBuilder({ page }).withTags(TAGS).analyze();
            expect(results.violations).toEqual([]);
        });
    }
}

test('announces the dialogs, menus and choices by name and state', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    await page.goto('/__fixtures/komponen');

    await page.locator('[data-gallery="PT-26"]').getByRole('button', { name: 'Catat Pembayaran' }).click();
    const dialog = page.getByRole('alertdialog', { name: 'Catat Pembayaran?' });
    await expect(dialog).toBeVisible();
    expect((await new AxeBuilder({ page }).include('[data-slot=alert-dialog-content]').withTags(TAGS).analyze()).violations).toEqual([]);
    await page.keyboard.press('Escape');

    await page.getByRole('button', { name: 'Tindakan Lain' }).first().click();
    await expect(page.getByRole('menuitem', { name: 'Tandai Sengketa' })).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(page.getByRole('button', { name: 'Tindakan Lain' }).first()).toBeFocused();

    await expect(page.getByRole('group', { name: 'Bahasa tampilan' }).getByRole('button', { name: 'ID', pressed: true })).toBeVisible();
    await expect(page.getByRole('switch')).toHaveAttribute('aria-checked', 'false');
    await expect(page.locator('[aria-current="step"]')).toContainText('(langkah 2 dari 4)');
});

import AxeBuilder from '@axe-core/playwright';
import { expect, type Page, test } from '@playwright/test';

import { documentDraft } from '../document-fixtures';
import { atRest } from '../evidence-fixtures';

// TL-12 (TASK-029; DESIGN_SYSTEM §15; INT-012; criterion 11): PG-035 and PG-036's composer pass the WCAG 2.2 AA checks
// at both bands, measured at rest; the paper a named region, never a live region, after the form in reading order,
// its scrolling frame reachable by keyboard.

const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];

async function noViolation(page: Page): Promise<void> {
    await atRest(page);
    expect((await new AxeBuilder({ page }).withTags(TAGS).analyze()).violations).toEqual([]);
}

for (const width of [390, 1280]) {
    test(`finds no WCAG 2.2 AA violation on PG-035 and PG-036 at ${String(width)} px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        const { id: draft } = await documentDraft(page, `dokumen-a11y-${String(width)}-${String(Date.now())}`);

        await page.goto(`/dokumen/${draft}`);
        await expect(page.getByRole('heading', { level: 1 })).toHaveText('SPK');
        await noViolation(page);

        await page.goto('/dokumen');
        await expect(page.getByRole('heading', { level: 1 })).toHaveText('Dokumen');
        await noViolation(page);
    });
}

test('keeps the paper a named region after the form, never live, its frame reachable by keyboard (§15; 2.1.1)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const { id: draft } = await documentDraft(page, `dokumen-kertas-${String(Date.now())}`);
    await page.goto(`/dokumen/${draft}`);

    const paper = page.getByRole('region', { name: 'Pratinjau SPK, draf belum terbit' });
    await expect(paper).toHaveAttribute('tabindex', '0');
    await expect(paper).not.toHaveAttribute('aria-live', /.+/);
    const order = await page.evaluate(() => {
        const save = Array.from(document.querySelectorAll('button')).find((button) => button.textContent.includes('Simpan Draf'));
        const frame = document.querySelector('[data-slot="paper-frame"]');

        return save !== undefined && frame !== null && (save.compareDocumentPosition(frame) & Node.DOCUMENT_POSITION_FOLLOWING) !== 0;
    });
    expect(order).toBe(true);
    await paper.focus();
    await expect(paper).toBeFocused();
});

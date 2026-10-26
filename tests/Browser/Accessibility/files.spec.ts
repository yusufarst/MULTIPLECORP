import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';

import { issuedDocument } from '../files-fixtures';
import { ownerSignedIn } from '../numbering-fixtures';

// TL-12 (TASK-022; DESIGN_SYSTEM §10 *Upload*; PT-22): the upload field is labelled — its input named by its label and
// described by its limits — and the page with a rendition's state passes the WCAG 2.2 AA checks at both bands.

const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];

for (const width of [390, 1280]) {
    test(`labels the upload field and finds no WCAG 2.2 AA violation at ${String(width)} px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        await ownerSignedIn(page, `berkas-a11y-${String(width)}-${String(Date.now())}`);

        await page.goto(`/__fixtures/berkas?versi=${await issuedDocument(page, false)}`);
        const input = page.getByLabel('Bukti');
        await expect(input).toHaveAttribute('type', 'file');
        await expect(input).toHaveAccessibleDescription('PDF, JPG, PNG atau WebP, maksimal 20 MB.');
        await expect(page.getByText('PDF Sedang Dibuat')).toBeVisible();
        expect((await new AxeBuilder({ page }).withTags(TAGS).analyze()).violations).toEqual([]);
    });
}

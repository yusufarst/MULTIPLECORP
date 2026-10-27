import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';

import { evidenceCompany, uniquePdf } from '../evidence-fixtures';
import { ownerSignedIn } from '../numbering-fixtures';

// TL-12 (TASK-030; DESIGN_SYSTEM §10 *Upload*; PG-038): the list and the upload sheet pass the WCAG 2.2 AA checks at
// both bands — the upload field named by its label and described by its limits, its finished state announced.

const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];

for (const width of [390, 1280]) {
    test(`finds no WCAG 2.2 AA violation on PG-038 and its upload sheet at ${String(width)} px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        await ownerSignedIn(page, `bukti-a11y-${String(width)}-${String(Date.now())}`);
        const { company } = await evidenceCompany(page);

        await page.goto(`/dokumen/bukti?cabang=${company}`);
        expect((await new AxeBuilder({ page }).withTags(TAGS).analyze()).violations).toEqual([]);

        await page.getByRole('button', { name: 'Unggah', exact: true }).filter({ visible: true }).first().click();
        const sheet = page.getByRole('dialog', { name: 'Unggah Dokumen' });
        const input = sheet.getByLabel('Unggah', { exact: true });
        await expect(input).toHaveAccessibleDescription('PDF, JPG, PNG, WebP, DOCX atau XLSX, maksimal 20 MB. Di ponsel, Anda bisa langsung memotret.');
        await input.setInputFiles({ name: 'spk.pdf', mimeType: 'application/pdf', buffer: uniquePdf() });
        await expect(sheet.getByRole('status')).toContainText('spk.pdf');
        expect((await new AxeBuilder({ page }).withTags(TAGS).analyze()).violations).toEqual([]);
    });
}

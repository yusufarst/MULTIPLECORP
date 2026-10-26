import { type Page } from '@playwright/test';

// TASK-022's browser fixtures: the sample page of routes/fixtures.php, a synthetic PDF and an issued synthetic
// document whose rendition is owed or rendered at once (FilesFixture). Local only; no real file or document.

export const SAMPLE_PDF = Buffer.from('%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\nstartxref\n9\n%%EOF\n');

export async function issuedDocument(page: Page, render: boolean): Promise<string> {
    const response = await page.request.post('/__fixtures/berkas/rendisi', { data: { render } });

    return ((await response.json()) as { version: string }).version;
}

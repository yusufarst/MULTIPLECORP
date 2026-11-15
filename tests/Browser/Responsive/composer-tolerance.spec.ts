import { mkdirSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';

import { expect, test } from '@playwright/test';

import { documentDraft } from '../document-fixtures';

// TL-11 (TASK-065; TEST_STRATEGY §9's tolerance; DESIGN_SYSTEM §14.3; D-DS-23; GAP-053; criterion 6): each layout's
// draft preview on PG-036 — the composer's one paper, which PG-014, PG-033, PG-042 and PG-043 compose from the same
// template — against the issued rendition of the same content laid out by the renderer's own engine (WeasyPrint, through
// the layout fixture): the same blocks in the same order, the draft marking, the number line and the signature images
// excepted, the paper's size and page count, and each block's position on the page within 1 mm. The comparison is
// recorded beside the gate's records for the Owner's decision (APPR-038); only a measurement that could not be made fails.

type Block = { key: string; page?: number; x: number; y: number; w: number; h: number };
type Issued = { pages: number; width: number; height: number; blocks: Block[] };

/** What may differ by rule: the draft's marking, the number line an issue fills, and the stamp and signature images. */
const EXCEPTED = new Set(['paper-marking', 'paper-watermark', 'paper-number', 'paper-number-pending', 'paper-stamp', 'paper-sign']);
const TOLERANCE = 1;
const LAYOUTS = Array.from({ length: 14 }, (_, index) => `DOC-${String(index + 1).padStart(2, '0')}`);

for (const layout of LAYOUTS) {
    test(`${layout}'s draft preview against its issued layout within 1 mm`, async ({ page }, testInfo) => {
        test.setTimeout(120_000);
        await page.setViewportSize({ width: 1280, height: 900 });
        const { id } = await documentDraft(page, `toleransi-${layout}-${String(Date.now())}`, layout);
        await page.goto(`/dokumen/${id}`);

        const paper = page.locator('[data-slot="paper-frame"] .paper').first();
        await expect(paper).toBeVisible();
        await page.evaluate(() => document.fonts.ready.then(() => undefined));

        // The preview's blocks in the paper's own millimetres: the fitted sheet's scale divided out, the sheet's padding
        // standing for the page's margins on screen (paper.css).
        const preview = await paper.evaluate((root: HTMLElement) => {
            const MM = 25.4 / 96;
            const sheet = root.getBoundingClientRect();
            const scale = sheet.width / root.offsetWidth;
            const blocks: { key: string; x: number; y: number; w: number; h: number }[] = [];

            for (const element of Array.from(root.querySelectorAll('*'))) {
                const key = Array.from(element.classList).find((name) => name.startsWith('paper-')) ?? (element.tagName === 'TR' ? 'tr' : null);

                if (key === null) continue;

                const box = element.getBoundingClientRect();
                const round = (value: number) => Math.round((value / scale) * MM * 100) / 100;
                blocks.push({ key, x: round(box.left - sheet.left), y: round(box.top - sheet.top), w: round(box.width), h: round(box.height) });
            }

            return { width: Math.round(root.offsetWidth * MM * 100) / 100, height: Math.round(root.offsetHeight * MM * 100) / 100, blocks };
        });

        const response = await page.request.get(`/__fixtures/dokumen/tata-letak?dokumen=${encodeURIComponent(id)}`);
        expect(response.ok(), await response.text()).toBe(true);
        const issued = (await response.json()) as Issued;

        const draftBlocks = preview.blocks.filter((block) => !EXCEPTED.has(block.key));
        const issuedBlocks = issued.blocks.filter((block) => !EXCEPTED.has(block.key));
        // A measurement that found nothing is no comparison: it fails rather than passes.
        expect(draftBlocks.length).toBeGreaterThan(0);
        expect(issuedBlocks.length).toBeGreaterThan(0);

        const order = draftBlocks.map((block) => block.key).join(' ') === issuedBlocks.map((block) => block.key).join(' ');
        const firstPage = issuedBlocks.filter((block) => block.page === 1).length;
        const pairs = draftBlocks.slice(0, Math.min(firstPage, issuedBlocks.length)).map((block, index) => {
            const other = issuedBlocks[index] ?? block;

            return { key: block.key, same: block.key === other.key, dx: Math.round((block.x - other.x) * 100) / 100, dy: Math.round((block.y - other.y) * 100) / 100, preview: block, issued: other };
        });
        const worst = pairs.reduce((most, pair) => Math.max(most, Math.abs(pair.dx), Math.abs(pair.dy)), 0);
        // The same comparison with the first block's offset removed — a constant shift of the whole layout, such as the
        // draft marking's height or a margin of the issued document's own, set apart from a difference within the layout.
        const shift = { x: pairs[0]?.dx ?? 0, y: pairs[0]?.dy ?? 0 };
        const residual = Math.round(pairs.reduce((most, pair) => Math.max(most, Math.abs(pair.dx - shift.x), Math.abs(pair.dy - shift.y)), 0) * 100) / 100;
        const bottom = draftBlocks.reduce((most, block) => Math.max(most, block.y + block.h), 0);
        // The screen's sheet is one continuous page: its page count is the printable heights its content reaches (20 mm the bottom margin).
        const previewPages = Math.max(1, Math.ceil((bottom + 20) / issued.height));
        const size = Math.abs(preview.width - issued.width) <= TOLERANCE;
        const result = order && size && previewPages === issued.pages && worst <= TOLERANCE ? 'PASS' : 'FAIL';

        const record = {
            layout, engine: testInfo.project.name, document: id, result, tolerance_mm: TOLERANCE,
            same_blocks_in_order: order, blocks: { preview: draftBlocks.length, issued: issuedBlocks.length, compared: pairs.length, beyond_first_page: issuedBlocks.length - firstPage },
            paper_mm: { preview: preview.width, issued: { width: issued.width, height: issued.height } },
            pages: { preview_estimated: previewPages, issued: issued.pages },
            worst_mm: worst, first_block_shift_mm: shift, residual_after_shift_mm: residual, residual_within_tolerance: residual <= TOLERANCE,
            beyond: pairs.filter((pair) => !pair.same || Math.abs(pair.dx) > TOLERANCE || Math.abs(pair.dy) > TOLERANCE).slice(0, 12),
            excepted: [...EXCEPTED],
        };
        const directory = join(process.cwd(), 'storage/test-results/browser/gate/composer', testInfo.project.name);
        mkdirSync(directory, { recursive: true });
        writeFileSync(join(directory, `${layout}.json`), `${JSON.stringify(record, null, 2)}\n`);
        testInfo.annotations.push({ type: 'tolerance', description: `${layout} ${result}: worst ${String(worst)} mm, residual ${String(residual)} mm, pages ${String(previewPages)}/${String(issued.pages)}` });
    });
}

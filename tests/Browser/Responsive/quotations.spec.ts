import { expect, test } from '@playwright/test';

import { quotationRun } from '../quotation-fixtures';

// TL-11 (TASK-033; D-DS-23; INT-012; UXS-41; NAV-014, NAV-037; criterion 8): a 30-line quotation draft in the composer at
// 1,025, 1,279 and 1,280 px — the form beside the live paper, never a tab, one focus order through every line's inputs to
// "Simpan Draf" by keyboard alone, each focused input in view, no sideways scroll —; "Rincian | Pratinjau" at 1,024 px;
// PG-037 without a sideways scroll on the phone.

for (const width of [1025, 1279, 1280]) {
    test(`keeps a 30-line draft's inputs in one keyboard order beside the live paper at ${String(width)} px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        const run = await quotationRun(page, `penawaran-lebar-${String(width)}-${String(Date.now())}`, { lines: 30, stage: 'draft' });
        await page.goto(`/proyek/${run.project}/penawaran`);

        await expect(page.locator('[data-slot="document-composer"]')).toHaveAttribute('data-band', 'desktop');
        await expect(page.getByRole('tab')).toHaveCount(0);
        const paper = page.getByRole('region', { name: /^Pratinjau Penawaran/ });
        await expect(paper).toContainText('Penawaran Harga');
        const form = await page.getByLabel(/^Berlaku sampai/).boundingBox();
        expect(((await paper.boundingBox())?.x ?? 0) > (form?.x ?? 0)).toBe(true);
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);

        await page.getByLabel(/^Berlaku sampai/).focus();
        const prices: number[] = [];

        for (let step = 0; step < 200; step++) {
            await page.keyboard.press('Tab');
            const focused = await page.evaluate(() => {
                const element = document.activeElement as HTMLInputElement | null;
                const rect = element?.getBoundingClientRect();

                return {
                    label: (element?.labels?.[0]?.textContent ?? element?.textContent ?? '').trim(),
                    line: element?.closest('li[id^="line-"]')?.id ?? null,
                    // WebKit's Tab reaches fields, not buttons: the form ends at "Simpan Draf" or, there, at the paper beside it.
                    end: element?.closest('[data-slot="paper-frame"]') != null,
                    inView: rect !== undefined && rect.top >= 0 && rect.bottom <= window.innerHeight,
                };
            });

            if (focused.label === 'Simpan Draf' || focused.end) break;
            expect(focused.inView).toBe(true);
            if (focused.label.startsWith('Harga satuan') && focused.line !== null) prices.push(Number(focused.line.replace('line-', '')));
        }

        expect(prices).toEqual(Array.from({ length: 30 }, (_, index) => index));
    });
}

test('offers "Rincian | Pratinjau" for the draft at 1,024 px', async ({ page }) => {
    await page.setViewportSize({ width: 1024, height: 900 });
    const run = await quotationRun(page, `penawaran-1024-${String(Date.now())}`, { stage: 'draft' });
    await page.goto(`/proyek/${run.project}/penawaran`);

    await expect(page.locator('[data-slot="document-composer"]')).toHaveAttribute('data-band', 'tablet');
    await page.getByRole('tab', { name: 'Pratinjau' }).click();
    await expect(page.getByRole('region', { name: /^Pratinjau Penawaran/ })).toContainText('Penawaran Harga');
});

for (const width of [320, 390]) {
    test(`shows PG-014's sent quotation and PG-037 without a sideways scroll at ${String(width)} px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 844 });
        const run = await quotationRun(page, `penawaran-hp-lebar-${String(width)}-${String(Date.now())}`, { stage: 'sent' });

        await page.goto(`/proyek/${run.project}/penawaran`);
        await expect(page.getByRole('button', { name: 'Catat Jawaban Klien' }).first()).toBeVisible();
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);

        await page.goto(`/dokumen/penawaran?perusahaan=${run.company}`);
        await expect(page.locator('#konten')).toContainText(run.number);
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    });
}

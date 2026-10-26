import { expect, test } from '@playwright/test';

// TL-11 (TASK-018): PG-002 and PG-004 at the edges of DESIGN_SYSTEM §6's bands — no horizontal scroll, the submit
// whole and its label unclipped, the QR code inside the column.

const BANDS = [320, 390, 767, 768, 1024, 1280, 1680];

for (const kind of ['totp', 'owner'] as const) {
    test(`holds ${kind === 'totp' ? 'PG-002' : 'PG-004'} at every band edge`, async ({ page }) => {
        const account = (await (await page.request.post('/__fixtures/akun', { data: { run: `band-${kind}-${String(Date.now())}`, kind } })).json()) as { email: string; password: string };
        await page.goto('/masuk');
        await page.getByLabel('E-mail').fill(account.email);
        await page.getByLabel('Kata sandi', { exact: true }).fill(account.password);
        await page.getByRole('button', { name: 'Masuk', exact: true }).click();
        await page.waitForURL(kind === 'totp' ? '**/masuk/verifikasi' : '**/verifikasi-dua-langkah');

        for (const width of BANDS) {
            await page.setViewportSize({ width, height: 900 });

            const layout = await page.evaluate(() => ({
                scroll: document.documentElement.scrollWidth > document.documentElement.clientWidth,
                clipped: [...document.querySelectorAll('main button, main label')]
                    .filter((element) => element.checkVisibility() && element.scrollWidth > element.clientWidth + 1)
                    .map((element) => element.textContent),
            }));

            expect(layout.scroll, `${kind} at ${String(width)} px`).toBe(false);
            expect(layout.clipped, `${kind} at ${String(width)} px`).toEqual([]);
        }
    });
}

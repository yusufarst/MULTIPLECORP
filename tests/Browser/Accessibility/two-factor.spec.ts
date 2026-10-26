import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';

// TL-12 (TASK-018): PG-002 with the generic code answer shown and PG-004 with its QR code and code field pass the
// WCAG 2.2 AA checks at the phone and desktop bands — the code entry's labelling and its error.

const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];

for (const width of [390, 1280]) {
    test(`finds no WCAG 2.2 AA violation on PG-002 and PG-004 at ${String(width)} px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });

        for (const kind of ['totp', 'owner'] as const) {
            const account = (await (await page.request.post('/__fixtures/akun', { data: { run: `a11y-${kind}-${String(width)}-${String(Date.now())}`, kind } })).json()) as { email: string; password: string };
            await page.goto('/masuk');
            await page.getByLabel('E-mail').fill(account.email);
            await page.getByLabel('Kata sandi', { exact: true }).fill(account.password);
            await page.getByRole('button', { name: 'Masuk', exact: true }).click();

            if (kind === 'totp') {
                await page.waitForURL('**/masuk/verifikasi');
                await page.getByLabel('Kode 6 digit dari aplikasi autentikator').fill('000000');
                await page.getByRole('button', { name: 'Verifikasi' }).click();
                await expect(page.getByText('Kode tidak cocok atau sudah tidak berlaku.', { exact: false })).toBeVisible();
            } else {
                await page.waitForURL('**/verifikasi-dua-langkah');
                await expect(page.getByRole('img', { name: 'Atur aplikasi autentikator' })).toBeVisible();
            }

            expect((await new AxeBuilder({ page }).withTags(TAGS).analyze()).violations).toEqual([]);
            await page.context().clearCookies();
        }
    });
}

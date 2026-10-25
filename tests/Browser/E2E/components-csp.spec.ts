import { expect, test } from '@playwright/test';

// TL-14 and HO-37 (TASK-015; acceptance criterion 5): the components hold WS-08's policy — no `'unsafe-inline'`
// style: every overlay, popup and toast is opened under the real header while the page listens for
// `securitypolicyviolation`, and none is reported. The policy itself is asserted too, so a loosened header fails.
// One report is the engine's own: WebKit flags the internal styling of every native `<select>` — a bare
// `document.createElement('select')` with no library on the page reproduces it, as the second test shows —, so
// that report alone is set aside, and only on WebKit; Chromium reports nothing at all.

const ENGINE_SELECT = 'style-src-attr SELECT inline';

test('opens every overlay with no CSP violation and no inline-style allowance', async ({ page, browserName }) => {
    // Every overlay of the gallery in turn: longer than one assertion's run on a loaded WebKit runner.
    test.setTimeout(90_000);

    await page.addInitScript(() => {
        const seen: string[] = [];
        Object.assign(window, { cspViolations: seen });
        document.addEventListener('securitypolicyviolation', (event) => {
            seen.push(`${event.violatedDirective} ${(event.target as Element | null)?.tagName ?? ''} ${event.blockedURI} ${event.sample}`.trim());
        });
    });

    const response = await page.goto('/__fixtures/komponen');
    const policy = (await response?.allHeaders())?.['content-security-policy'] ?? '';
    expect(policy).toMatch(/style-src 'self' 'nonce-[^']+'(;|$)/);
    expect(policy).not.toContain('unsafe-inline');

    await page.getByRole('combobox', { name: 'Pemasok' }).first().fill('SUP');
    await expect(page.getByRole('option').first()).toBeVisible();
    await page.keyboard.press('Escape');

    await page.getByRole('button', { name: 'Ubah' }).first().click();
    await expect(page.getByRole('grid')).toBeVisible();
    await page.keyboard.press('Escape');

    await page.getByRole('button', { name: 'Tindakan Lain' }).first().click();
    await expect(page.getByRole('menu')).toBeVisible();
    await page.keyboard.press('Escape');

    await page.getByRole('button', { name: /Tampilkan/ }).first().click();
    await expect(page.getByRole('checkbox', { name: /ARJ/ })).toBeVisible();
    await page.keyboard.press('Escape');

    for (const name of ['Catat Pembayaran', 'Konfirmasi Kata Sandi', 'Sesi Akan Berakhir']) {
        await page.locator('[data-gallery="PT-26"]').getByRole('button', { name }).click();
        await expect(page.getByRole('alertdialog')).toBeVisible();
        await page.waitForTimeout(400);

        if (name === 'Sesi Akan Berakhir') {
            await page.getByRole('button', { name: 'Tetap Masuk' }).click();
        } else {
            await page.keyboard.press('Escape');
        }

        await expect(page.getByRole('alertdialog')).toHaveCount(0);
    }

    await page.locator('[data-gallery="PT-14"]').getByRole('button', { name: 'Tutup notifikasi' }).click();
    await expect(page.locator('[data-slot=toast-viewport]').getByText('ARJ/INV/2026/0147')).toBeVisible();

    await page.getByRole('button', { name: 'Salin nomor' }).first().hover();
    await page.waitForTimeout(800);

    const violations = await page.evaluate(() => (window as unknown as { cspViolations: string[] }).cspViolations);
    expect(violations.filter((violation) => !(browserName === 'webkit' && violation === ENGINE_SELECT))).toEqual([]);
});

test("WebKit's native select report comes from the engine, with no library on the page", async ({ page, browserName }) => {
    test.skip(browserName !== 'webkit', "the report is WebKit's alone");
    await page.goto('/__fixtures/bahasa');

    const reports = await page.evaluate(async () => {
        const seen: string[] = [];
        document.addEventListener('securitypolicyviolation', (event) => {
            seen.push(`${event.violatedDirective} ${(event.target as Element | null)?.tagName ?? ''} ${event.blockedURI}`.trim());
        });
        document.body.append(document.createElement('select'));
        await new Promise((resolve) => setTimeout(resolve, 300));

        return seen;
    });

    expect(reports).toEqual([ENGINE_SELECT]);
});

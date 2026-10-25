import { expect, test } from '@playwright/test';

// TL-09 (TASK-014; acceptance criterion 4): INT-001's switch on a page with unsent input keeps the input and
// the page — a visit that keeps the page's state, never a full reload — and stores nothing on the device
// (LOCALIZATION §2, §3.7). The sign-in pages carry it with TASK-017; CJ-18 is TASK-064's.

// A switch is a visit and, the first time, its language's bundle: on a loaded runner it may take longer than
// the default five seconds of an assertion.
const SWITCHED = 15_000;

test('switches the language and keeps the typed input, the page and nothing stored (INT-001)', async ({ page }) => {
    await page.goto('/__fixtures/bahasa');
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Masuk ke MultipleCorp');

    await page.getByLabel('E-mail').fill('pengguna@contoh.test');
    await page.evaluate(() => {
        (window as unknown as { kept: boolean }).kept = true;
    });
    await page.getByRole('button', { name: 'EN' }).click();

    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Sign in to MultipleCorp', { timeout: SWITCHED });
    await expect(page).toHaveURL(/[?&]bahasa=en(&|$)/);
    await expect(page.getByLabel('Email')).toHaveValue('pengguna@contoh.test');
    expect(await page.evaluate(() => (window as unknown as { kept?: boolean }).kept)).toBe(true);

    await page.getByRole('button', { name: 'ID' }).click();
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Masuk ke MultipleCorp', { timeout: SWITCHED });
    await expect(page.getByLabel('E-mail')).toHaveValue('pengguna@contoh.test');

    const stored = await page.evaluate(() => ({ local: window.localStorage.length, session: window.sessionStorage.length }));
    expect(stored).toEqual({ local: 0, session: 0 });
    expect((await page.context().cookies()).map((cookie) => cookie.name).filter((name) => /bahasa|locale|lang/i.test(name))).toEqual([]);
});

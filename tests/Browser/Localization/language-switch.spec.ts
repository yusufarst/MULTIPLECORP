import { expect, type Page, test } from '@playwright/test';

// TL-10 (TASK-014; acceptance criteria 1, 3, 4): both languages complete on the page — no key shown as itself,
// the document's language set —, an unknown choice ignored for Indonesian, and each language's strings loaded
// once as one bundle, never per key (LOCALIZATION §3.6, §3.8; TL-15).

const KEY_SHOWN = /\b(messages|glossary|status|auth|documents|pages|shell|actions|forms)\.[a-z0-9_]+/;

async function expectComplete(page: Page, lang: 'id' | 'en'): Promise<void> {
    await expect(page.locator('html')).toHaveAttribute('lang', lang);
    expect(await page.locator('body').innerText()).not.toMatch(KEY_SHOWN);
}

test('renders each language complete, an unknown choice as Indonesian (§2, §3.6)', async ({ page }) => {
    await page.goto('/__fixtures/bahasa?bahasa=en');
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Sign in to MultipleCorp');
    await expectComplete(page, 'en');

    await page.goto('/__fixtures/bahasa');
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Masuk ke MultipleCorp');
    await expectComplete(page, 'id');

    await page.goto('/__fixtures/bahasa?bahasa=fr');
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Masuk ke MultipleCorp');
    await expectComplete(page, 'id');
});

test('loads each language once, as one bundle (§3.8; TL-15)', async ({ page }) => {
    const bundles: string[] = [];
    page.on('request', (request) => {
        const name = /\/build\/assets\/(id|en)-[^/]+\.js$/.exec(request.url())?.[1];

        if (name !== undefined) {
            bundles.push(name);
        }
    });

    await page.goto('/__fixtures/bahasa');
    await page.getByRole('button', { name: 'EN' }).click();
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Sign in to MultipleCorp');
    await page.getByRole('button', { name: 'ID' }).click();
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Masuk ke MultipleCorp');
    await page.getByRole('button', { name: 'EN' }).click();
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Sign in to MultipleCorp');

    expect(bundles.sort()).toEqual(['en', 'id']);
});

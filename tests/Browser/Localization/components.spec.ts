import { expect, type Page, test } from '@playwright/test';

// TL-10 (TASK-015; acceptance criterion 6): every component's text in both languages — no key shown as itself
// — and the language switch on a page built from the components keeping the unsent input in every field
// (LOCALIZATION §3.7; CJ-18 in TASK-064; the architecture challenge's sequence 17b).

const SWITCHED = 15_000;
const KEY_SHOWN = /\b(messages|glossary|status|outcome|states|actions|forms|search|date|scan|timeline|capability|company|stage|nav|toast|finance|inventory|readiness|dialogs|duplicates|list|prerequisite|pages|pt\d+|journey|purchase)\.[a-z0-9_]+/;

async function expectComplete(page: Page, lang: 'id' | 'en'): Promise<void> {
    await expect(page.locator('html')).toHaveAttribute('lang', lang);
    expect(await page.locator('body').innerText()).not.toMatch(KEY_SHOWN);
}

test('renders every component in each language with no key shown', async ({ page }) => {
    await page.goto('/__fixtures/komponen?bahasa=en');
    await expect(page.getByRole('button', { name: 'Record Payment' }).first()).toBeVisible();
    await expectComplete(page, 'en');

    await page.goto('/__fixtures/komponen');
    await expect(page.getByRole('button', { name: 'Catat Pembayaran' }).first()).toBeVisible();
    await expectComplete(page, 'id');
});

test('keeps the unsent input in every field across the switch', async ({ page }) => {
    await page.goto('/__fixtures/komponen');

    await page.locator('#gallery-name').fill('PT Arjuna Sarana');
    await page.locator('#gallery-quantity').fill('12,5');
    await page.getByLabel('Harga', { exact: true }).first().fill('1.250.000');
    await page.getByRole('textbox', { name: 'Alasan', exact: true }).fill('Termin pertama');
    await page.getByLabel('Kode 6 digit dari aplikasi autentikator').fill('123456');
    await page.getByRole('checkbox', { name: 'Kirim langsung ke klien' }).first().check();
    await page.getByRole('radio', { name: 'SIPLAH' }).check();
    await page.getByRole('switch').check();
    await page.getByRole('textbox', { name: 'Pindai atau ketik kode' }).fill('KRT-A4');

    await page.getByRole('button', { name: 'EN', exact: true }).click();
    await expect(page.getByRole('button', { name: 'Record Payment' }).first()).toBeVisible({ timeout: SWITCHED });

    await expect(page.locator('#gallery-name')).toHaveValue('PT Arjuna Sarana');
    // IP-13: the value left in a field shows formatted, its entry kept.
    await expect(page.locator('#gallery-quantity')).toHaveValue('12,5 rim');
    await expect(page.getByLabel('Price', { exact: true }).first()).toHaveValue('Rp1.250.000');
    await expect(page.getByRole('textbox', { name: 'Reason', exact: true })).toHaveValue('Termin pertama');
    await expect(page.getByLabel(/6-digit code/)).toHaveValue('123456');
    await expect(page.getByRole('checkbox').first()).toBeChecked();
    await expect(page.getByRole('radio', { name: 'SIPLAH' })).toBeChecked();
    await expect(page.getByRole('switch')).toBeChecked();
    await expect(page.getByRole('textbox', { name: /scan or type/i })).toHaveValue('KRT-A4');
    await expectComplete(page, 'en');
});

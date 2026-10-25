import { expect, type Page, test } from '@playwright/test';

// TL-09 (TASK-015; acceptance criterion 3): the design system's components behave as ADMIN_FLOW's interaction
// patterns state, keyboard included (DESIGN_SYSTEM §13), on the local stack's component gallery
// (routes/fixtures.php) before the first pages compose them.

const GALLERY = '/__fixtures/komponen';
// Opening a record is an Inertia visit: on a loaded runner it may take longer than an assertion's five seconds.
const VISIT = 15_000;

async function open(page: Page): Promise<void> {
    await page.goto(GALLERY);
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
}

test('IP-13: money and quantities read the Indonesian separators and show the formatted value on leaving', async ({ page }) => {
    await open(page);
    const price = page.getByLabel('Harga', { exact: true }).first();

    await price.fill('1.250.000,5');
    await price.blur();
    await expect(price).toHaveValue('Rp1.250.000,50');

    await price.fill('1.5');
    await price.blur();
    await expect(price).toHaveValue('1.5');
});

test('IP-14: the first invalid field takes focus and the summary counts the fields', async ({ page }) => {
    await open(page);
    await page.getByRole('button', { name: 'Lanjutkan', exact: true }).click();

    await expect(page.getByRole('alert').filter({ hasText: 'Periksa 2 isian yang ditandai.' })).toBeVisible();
    await expect(page.locator('#gallery-name')).toBeFocused();
    await expect(page.locator('#gallery-name')).toHaveAttribute('aria-invalid', 'true');

    await page.getByRole('button', { name: 'Perbaiki' }).nth(1).click();
    await expect(page.locator('#gallery-quantity')).toBeFocused();
});

test('GF-12: a dependent field is disabled with its reason until its prerequisite is chosen', async ({ page }) => {
    await open(page);
    await expect(page.getByText('Pilih Pemasok lebih dulu.')).toBeVisible();
    await expect(page.getByRole('combobox', { name: 'Proyek' })).toBeDisabled();

    const supplier = page.getByRole('combobox', { name: 'Pemasok' }).first();
    await supplier.fill('SUP');
    await expect(page.getByRole('option', { name: /SUP-0012/ })).toContainText('Kode Cocok');
    await page.keyboard.press('Enter');
    await expect(page.getByRole('combobox', { name: 'Proyek' })).toBeEnabled();
});

test('PT-26 and KB-10: Ctrl+Enter opens the confirmation, focus on its first input; only its button sends', async ({ page }) => {
    await open(page);
    await page.locator('#gallery-name').focus();
    await page.keyboard.press('Control+Enter');

    const dialog = page.getByRole('alertdialog', { name: 'Catat Pembayaran?' });
    await expect(dialog).toBeVisible();
    await expect(dialog.getByRole('textbox')).toBeFocused();
    await expect(dialog.getByRole('button', { name: 'Catat Pembayaran' })).toBeDisabled();

    // A scanner's Enter in the dialog never confirms (§13.2).
    await page.keyboard.press('Enter');
    await expect(dialog).toBeVisible();

    await dialog.getByRole('textbox').fill('Pembayaran termin pertama');
    await dialog.getByRole('button', { name: 'Catat Pembayaran' }).click();
    await expect(dialog.getByText('Tersimpan', { exact: true })).toBeVisible();

    await page.keyboard.press('Escape');
    await expect(dialog).toBeHidden();
});

test('PT-16 and KB-04: Esc closes a dialog except the re-authentication, which only "Keluar" ends', async ({ page }) => {
    await open(page);
    await page.getByRole('button', { name: 'Konfirmasi Kata Sandi' }).click();
    const stepUp = page.getByRole('alertdialog', { name: 'Konfirmasi Kata Sandi' });
    await expect(stepUp.getByLabel('Kata sandi')).toBeFocused();
    await page.keyboard.press('Escape');
    await expect(stepUp).toBeHidden();

    await page.getByRole('button', { name: 'Masuk lagi untuk melanjutkan' }).click();
    const reauth = page.getByRole('alertdialog', { name: 'Masuk lagi untuk melanjutkan' });
    await expect(reauth.getByLabel('E-mail')).toBeFocused();
    await page.keyboard.press('Escape');
    await expect(reauth).toBeVisible();
    await reauth.getByRole('button', { name: 'Keluar' }).click();
    await expect(reauth).toBeHidden();

    await page.getByRole('button', { name: 'Isian yang belum dikirim' }).click();
    const unsent = page.getByRole('alertdialog', { name: 'Isian yang belum dikirim' });
    await expect(unsent.getByRole('button', { name: 'Kembali' })).toBeFocused();
});

test('§13.2: a burst outside a scan field is discarded with its Enter and the field keeps its value', async ({ page }) => {
    await open(page);
    const name = page.locator('#gallery-name');
    await name.fill('PT Arjuna');
    await name.focus();
    // A keyboard wedge types faster than the test runner's own key events travel, so the burst is played inside
    // the page as a scanner delivers it. A real key's time is the device's, not when the page gets to it, so the
    // events are made together and then delivered — each character's keydown and insertion, then the Enter.
    await page.evaluate(() => {
        const field = document.activeElement as HTMLInputElement;
        const value = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value');
        const keys = [...Array.from('8991234567890'), 'Enter'].map((key) => new KeyboardEvent('keydown', { key, bubbles: true, cancelable: true }));

        for (const event of keys) {
            if (field.dispatchEvent(event) && event.key !== 'Enter') {
                value?.set?.call(field, field.value + event.key);
                field.dispatchEvent(new Event('input', { bubbles: true }));
            }
        }
    });

    await expect(name).toHaveValue('PT Arjuna');
    await expect(page.getByRole('alert').filter({ hasText: 'Kode terpindai masuk ke kolom Perusahaan. Pindai di kolom Pindai.' })).toBeVisible();
    await expect(page.getByRole('alertdialog')).toHaveCount(0);
});

test('PT-20: the scan field looks up a code with Enter and keeps focus; an unknown code is announced', async ({ page }) => {
    await open(page);
    const scan = page.getByRole('textbox', { name: 'Pindai atau ketik kode' });
    await scan.focus();
    await page.keyboard.type('8991234567890', { delay: 5 });
    await page.keyboard.press('Enter');

    await expect(scan).toBeFocused();
    await expect(scan).toHaveValue('');
    await expect(page.getByRole('status').filter({ hasText: '8991234567890' })).toBeVisible();

    await scan.fill('XXXX');
    await page.keyboard.press('Enter');
    await expect(page.getByRole('alert').filter({ hasText: 'Kode XXXX tidak dikenal.' })).toBeVisible();
});

test('PT-05 and PT-08: the arrows move between rows, Enter opens one; "Muat Lagi" continues without totals', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    await open(page);
    const table = page.getByRole('table', { name: 'Piutang' });
    const rows = table.locator('tbody tr');

    await rows.first().focus();
    await page.keyboard.press('ArrowDown');
    await expect(rows.nth(1)).toBeFocused();
    await page.keyboard.press('Enter');
    await expect(page).toHaveURL(/#inv-1$/, { timeout: VISIT });

    await page.getByRole('button', { name: 'Muat Lagi' }).click();
    await expect(page.getByText('Sebagian data sudah diperiksa. Muat lagi untuk melanjutkan.')).toBeVisible();
    await page.getByRole('button', { name: 'Muat Lagi' }).click();
    await expect(page.getByText('Semua sudah ditampilkan.')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Muat Lagi' })).toHaveCount(0);
});

test('PT-10 and KB-06–KB-07: one empty last line, Alt+N to it, Alt+Delete removes with "Urungkan"', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    await open(page);
    const editor = page.getByRole('region', { name: 'Baris pembelian' }).or(page.locator('[data-slot=line-editor]'));
    const quantity = editor.getByLabel('Jumlah');

    await expect(quantity).toHaveCount(1);
    await quantity.first().fill('3');
    await expect(quantity).toHaveCount(2);

    await quantity.first().focus();
    await page.keyboard.press('Alt+Delete');
    await expect(quantity).toHaveCount(1);
    await editor.getByRole('button', { name: 'Urungkan' }).click();
    await expect(quantity).toHaveCount(2);
    await expect(quantity.first()).toHaveValue('3');
    // KB-07: "Urungkan" puts the line back and focus into it, so the keys go on from there.
    await expect(editor.getByRole('combobox', { name: 'Produk/Jasa' }).first()).toBeFocused();

    await page.keyboard.press('Alt+n');
    await expect(editor.getByRole('combobox', { name: 'Produk/Jasa' }).last()).toBeFocused();
});

test('PT-15: a toast is a quiet confirmation that leaves by itself', async ({ page }) => {
    await open(page);
    await page.locator('[data-gallery="PT-14"]').getByRole('button', { name: 'Tutup notifikasi' }).click();
    const toast = page.locator('[data-slot=toast-viewport]').getByText('ARJ/INV/2026/0147');

    await expect(toast).toBeVisible();
    await expect(toast).toBeHidden({ timeout: 10_000 });
});

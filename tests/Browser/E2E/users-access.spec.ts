import { expect, type Page, test } from '@playwright/test';

import { nothingKept, signedIn, signIn, stepUp } from '../account-fixtures';
import { numberingCompany } from '../numbering-fixtures';

// TL-09 (TASK-024; J-ACC-02; CJ-11; AC-13; UXS-27): the Owner's path on PG-060 against its real routes — an account
// created, given a company and a capability after "Pratinjau Akses", its password-setting link shown once and
// delivered, activated; its holder setting the password and signing in; the Owner ending that session, reissuing the
// account's credentials with a reason and deactivating it. Synthetic accounts and companies from the fixtures.

const VISIT = 15_000;

test.describe.configure({ timeout: 180_000 });

/** A confirmation's own button, then the step-up when the shell asks for it (IP-08; IP-10). */
async function confirm(page: Page, label: string, password?: string): Promise<void> {
    await page.getByRole('alertdialog').getByRole('button', { name: label, exact: true }).click();

    if (password !== undefined) {
        await stepUp(page, password);
    }
}

async function tab(page: Page, name: string): Promise<void> {
    await page.getByRole('tab', { name }).click();
    await expect(page.getByRole('tab', { name })).toHaveAttribute('aria-selected', 'true', { timeout: VISIT });
}

test('creates an account, grants after the preview, shows its link once, activates, ends its session, reissues and deactivates (J-ACC-02; CJ-11)', async ({ page, browser }) => {
    const owner = await signedIn(page, 'pengguna', { owner: true });
    const company = await numberingCompany(page, null);
    const email = `dewi-${String(Date.now())}@contoh.test`;

    // Create (ROUTE-121): its confirmation first, then the step-up when it is sent.
    await page.goto('/pengaturan/pengguna');
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Pengguna & Akses');
    await page.getByRole('button', { name: 'Buat Akun' }).click();
    await page.getByLabel(/^Nama/).fill('Dewi Uji');
    await page.getByLabel(/^E-mail/).fill(email);
    await page.locator('form').getByRole('button', { name: 'Buat Akun' }).click();
    await expect(page.getByRole('alertdialog')).toContainText(email);
    await confirm(page, 'Buat Akun', owner.password);
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Dewi Uji', { timeout: VISIT });

    // Access (ROUTE-124): a company and a capability, "Pratinjau Akses", then "Simpan Akses".
    await tab(page, 'Akses');
    // The checkbox's own control is the label's: the input behind it is hidden.
    await page.locator(`label[for="perusahaan-${company}"]`).click();
    await page.locator('label[for="kewenangan-projects.view"]').click();
    await page.getByRole('button', { name: 'Pratinjau Akses' }).click();
    const preview = page.locator('[data-slot="capability-preview"]');
    await expect(preview).toBeVisible({ timeout: VISIT });
    await expect(preview.getByText('Baru').first()).toBeVisible();
    await page.getByRole('button', { name: 'Simpan Akses' }).click();
    await expect(page.getByRole('alertdialog')).toContainText('Lihat proyek');
    await confirm(page, 'Simpan Akses');
    await expect(page.getByText('Akses disimpan.').first()).toBeVisible({ timeout: VISIT });

    // The link (ROUTE-125), shown once, kept nowhere.
    await tab(page, 'Tautan');
    await page.getByRole('button', { name: 'Buat Tautan Atur Kata Sandi' }).click();
    const shown = page.getByRole('status').filter({ hasText: 'Salin tautan ini sekarang.' });
    await expect(shown).toBeVisible({ timeout: VISIT });
    const url = await shown.getByRole('textbox').inputValue();
    const token = url.split('#')[1] ?? '';
    expect(token.length).toBeGreaterThan(40);
    await nothingKept(page, [token]);

    // Activation (ROUTE-123): a capability that is not high-risk keeps the ONBOARDING link.
    await tab(page, 'Profil');
    await page.getByRole('button', { name: 'Aktifkan' }).click();
    await confirm(page, 'Aktifkan');
    await expect(page.getByText('Akun diaktifkan.').first()).toBeVisible({ timeout: VISIT });

    // The holder sets the password through the link and signs in, in a device of its own.
    const holder = await browser.newContext();
    const device = await holder.newPage();
    await device.goto(`/atur-kata-sandi#${token}`);
    await device.getByLabel('Kata sandi baru', { exact: true }).fill('Rapat-Gudang-24!');
    await device.getByLabel('Ulangi kata sandi baru').fill('Rapat-Gudang-24!');
    await device.getByRole('button', { name: 'Simpan Kata Sandi' }).click();
    await device.waitForURL('**/masuk');
    await signIn(device, { id: '', email, password: 'Rapat-Gudang-24!' });

    // "Akhiri Sesi" (ROUTE-130): the holder signs in again.
    await page.reload();
    await tab(page, 'Sesi');
    await page.getByRole('button', { name: 'Akhiri Sesi' }).first().click();
    await confirm(page, 'Akhiri Sesi');
    await expect(page.getByText('Sesi diakhiri.').first()).toBeVisible({ timeout: VISIT });
    await device.goto('/akun');
    await device.waitForURL('**/masuk', { timeout: VISIT });
    await holder.close();

    // The reissue of an account without TOTP (ROUTE-127): a reason, then one RESET link shown once.
    await tab(page, 'Keamanan');
    await page.getByRole('button', { name: 'Terbitkan Ulang Kredensial' }).click();
    await page.getByRole('alertdialog').getByLabel(/^Alasan/).fill('Kata sandi dibagikan, dilaporkan langsung');
    await confirm(page, 'Terbitkan Ulang Kredensial');
    await expect(page.getByText('Kredensial akun diatur ulang.', { exact: false }).first()).toBeVisible({ timeout: VISIT });

    // Deactivation (ROUTE-123).
    await tab(page, 'Profil');
    await page.getByRole('button', { name: 'Nonaktifkan' }).click();
    await confirm(page, 'Nonaktifkan');
    await expect(page.getByText('Akun dinonaktifkan.', { exact: false }).first()).toBeVisible({ timeout: VISIT });

    // "Kembali" from an account → the directory (NAV-060).
    await page.getByRole('button', { name: 'Kembali' }).click();
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Pengguna & Akses', { timeout: VISIT });
    await expect(page.getByText('Dewi Uji').first()).toBeVisible();
});

test('answers an Admin\'s address for PG-060 as a missing page (OD-01; IP-21)', async ({ page }) => {
    await signedIn(page, 'pengguna-admin');
    const answer = await page.goto('/pengaturan/pengguna');
    expect(answer?.status()).toBe(404);
    await expect(page.getByText('Pengguna & Akses')).toHaveCount(0);
});

import { expect as base, type Page, test } from '@playwright/test';

import { signedIn, stepUp } from '../account-fixtures';
import { clean, guardsClean, watch } from '../journey-fixtures';
import { accountTimeline, box, openAccess, previewAccess, runCompanies, toggle } from '../journey-k1-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-09 (TASK-064; CJ-11; J-ACC-02; UXS-27; AC-13; GAP-032; RG-06–RG-08; RG-11; criterion 3): on the desktop, the Owner's PG-060 —
// an account created behind its confirmation and the step-up, then given two companies of the run and four capabilities, one of
// them ADM_PLUS with its reason, after "Pratinjau Akses" shows the capability-by-company matrix of the result; then the
// prerequisite *Lihat proyek* and one company revoked, the preview marking its dependants "Nonaktif — prasyarat dicabut" — their
// grants kept — and the removed company's column *Dicabut*; both changes on the account's timeline, the first with its reason.
// The journey watches its console and network and ends with ReconcileGuards clean for its companies (TS-11).

test.describe.configure({ timeout: 300_000 });

const DESK = { width: 1280, height: 900 };

/** One capability's row of the desktop matrix, by its name. */
function matrixRow(page: Page, name: string) {
    return page.getByRole('table', { name: 'Pratinjau Akses' }).getByRole('row').filter({ has: page.getByRole('rowheader', { name, exact: true }) });
}

test('CJ-11: the Owner creates an account, grants two companies and capabilities after the capability-by-company preview, then revokes a prerequisite and a company, its dependants disabled with their grants kept (J-ACC-02; UXS-27; RG-06; RG-11; criterion 3)', async ({ page }, testInfo) => {
    const captured = watch(page);
    await page.setViewportSize(DESK);
    const owner = await signedIn(page, 'cj11-owner', { owner: true });
    const [a, b] = await runCompanies(page, 2);

    if (a === undefined || b === undefined) {
        throw new Error('The companies fixture gave fewer than two companies.');
    }

    const stamp = String(Date.now());
    const name = `Akun CJ11 ${stamp}`;
    const email = `cj11-${stamp}@contoh.test`;

    // Create (ROUTE-121): its confirmation lists the account, then the session's first sensitive step asks the step-up (IP-10).
    await page.goto('/pengaturan/pengguna');
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Pengguna & Akses');
    await page.getByRole('button', { name: 'Buat Akun' }).click();
    await page.getByLabel(/^Nama/).fill(name);
    await page.getByLabel(/^E-mail/).fill(email);
    await page.locator('form').getByRole('button', { name: 'Buat Akun' }).click();
    const creating = page.getByRole('alertdialog');
    await expect(creating).toContainText(email);
    await expect(creating).toContainText('Akun dibuat nonaktif sebagai Admin Operasional tanpa akses.');
    await creating.getByRole('button', { name: 'Buat Akun', exact: true }).click();
    await stepUp(page, owner.password);
    await expect(page.getByRole('heading', { level: 1 })).toHaveText(name);
    await page.waitForURL(/akun=/);
    const account = new URL(page.url()).searchParams.get('akun') ?? '';
    expect(account).toMatch(/^[0-9a-f-]{36}$/);

    // Grant (ROUTE-124): both companies; *Lihat proyek*, *Kelola proyek* and *Batalkan proyek* on it, *Lihat stok*. Each
    // dependant states its prerequisite; the ADM_PLUS capability asks its reason (RG-08).
    await openAccess(page, account);
    await expect(page.locator('label[for="kewenangan-projects.manage"]')).toContainText('Memerlukan Lihat proyek');
    for (const key of [`perusahaan-${a.id}`, `perusahaan-${b.id}`, 'kewenangan-projects.view', 'kewenangan-projects.manage', 'kewenangan-projects.cancel', 'kewenangan-stock.view']) {
        await toggle(page, key);
        await expect(box(page, key)).toHaveAttribute('data-checked');
    }

    const reason = `Admin proyek cabang ${stamp}`;
    await page.getByLabel(/^Alasan/).fill(reason);

    // "Simpan Akses" waits for the preview of exactly this choice (PT-30).
    await expect(page.getByRole('button', { name: 'Simpan Akses' })).toBeDisabled();
    const preview = await previewAccess(page);

    // UXS-27: every granted capability against every granted company — a capability applies in each (RG-07; GAP-032).
    const matrix = page.getByRole('table', { name: 'Pratinjau Akses' });
    await expect(matrix.getByRole('columnheader')).toContainText([a.code, b.code]);
    for (const capability of ['Lihat proyek', 'Kelola proyek', 'Batalkan proyek', 'Lihat stok']) {
        await expect(matrixRow(page, capability).getByRole('cell')).toHaveText(['Baru', 'Baru']);
    }
    await expect(preview.getByText('4 kewenangan berlaku')).toHaveCount(2);

    await page.getByRole('button', { name: 'Simpan Akses' }).click();
    const granting = page.getByRole('alertdialog');
    await expect(granting).toContainText('Kewenangan berlaku di setiap perusahaan yang dapat diaksesnya.');
    await expect(granting).toContainText('Akses Perusahaan ditambah:');
    await expect(granting).toContainText(a.code);
    await expect(granting).toContainText(b.code);
    await expect(granting).toContainText('Kelola proyek — berlaku di');
    await expect(granting).toContainText('Batalkan proyek — berlaku di');
    // Within 15 minutes of the step-up, the next access change asks no password again (AU-15).
    await granting.getByRole('button', { name: 'Simpan Akses', exact: true }).click();
    await expect(page.getByText('Akses disimpan.').first()).toBeVisible();
    await expect(page.getByRole('alertdialog')).toHaveCount(0);

    // Revoke: the prerequisite *Lihat proyek* and company B. The preview marks the dependants "Nonaktif — prasyarat dicabut" in
    // the company kept, every capability *Dicabut* in the company removed, *Lihat stok* still *Dapat* where it stays (RG-06).
    await openAccess(page, account);
    for (const key of [`perusahaan-${a.id}`, `perusahaan-${b.id}`, 'kewenangan-projects.view', 'kewenangan-projects.manage', 'kewenangan-projects.cancel', 'kewenangan-stock.view']) {
        await expect(box(page, key)).toHaveAttribute('data-checked');
    }
    await toggle(page, 'kewenangan-projects.view');
    await toggle(page, `perusahaan-${b.id}`);
    await expect(box(page, 'kewenangan-projects.view')).toHaveAttribute('data-unchecked');
    await expect(box(page, `perusahaan-${b.id}`)).toHaveAttribute('data-unchecked');
    const revoked = await previewAccess(page);

    for (const dependant of ['Kelola proyek', 'Batalkan proyek']) {
        const cells = matrixRow(page, dependant).getByRole('cell');
        await expect(cells.filter({ hasText: /^Nonaktif — prasyarat dicabut$/ })).toHaveCount(1);
        await expect(cells.filter({ hasText: /^Dicabut$/ })).toHaveCount(1);
    }
    await expect(matrixRow(page, 'Lihat proyek').getByRole('cell')).toHaveText(['Dicabut', 'Dicabut']);
    const stock = matrixRow(page, 'Lihat stok').getByRole('cell');
    await expect(stock.filter({ hasText: /^Dapat$/ })).toHaveCount(1);
    await expect(stock.filter({ hasText: /^Dicabut$/ })).toHaveCount(1);
    await expect(revoked.getByText('1 kewenangan berlaku')).toHaveCount(1);
    await expect(revoked.getByText('0 kewenangan berlaku')).toHaveCount(1);

    await page.getByRole('button', { name: 'Simpan Akses' }).click();
    const revoking = page.getByRole('alertdialog');
    await expect(revoking).toContainText(`Akses Perusahaan dicabut: ${b.code}`);
    await expect(revoking).toContainText('Lihat proyek dicabut');
    await expect(revoking).toContainText('Kelola proyek nonaktif — prasyarat dicabut');
    await expect(revoking).toContainText('Batalkan proyek nonaktif — prasyarat dicabut');
    await revoking.getByRole('button', { name: 'Simpan Akses', exact: true }).click();
    await expect(page.getByText('Akses disimpan.').first()).toBeVisible();

    // The dependants keep their grants, disabled until the prerequisite returns (RG-06); company B and *Lihat proyek* are gone.
    await openAccess(page, account);
    await expect(box(page, 'kewenangan-projects.manage')).toHaveAttribute('data-checked');
    await expect(box(page, 'kewenangan-projects.cancel')).toHaveAttribute('data-checked');
    await expect(box(page, 'kewenangan-stock.view')).toHaveAttribute('data-checked');
    await expect(box(page, 'kewenangan-projects.view')).toHaveAttribute('data-unchecked');
    await expect(box(page, `perusahaan-${a.id}`)).toHaveAttribute('data-checked');
    await expect(box(page, `perusahaan-${b.id}`)).toHaveAttribute('data-unchecked');

    // RG-11: both changes on the account's own timeline, the grant with its reason; history keeps both.
    const rows = await accountTimeline(page, account);
    const changes = rows.filter({ hasText: 'Mengubah akses akun' });
    await expect(changes).toHaveCount(2);
    await expect(changes.filter({ hasText: reason })).toHaveCount(1);

    // The directory lists the account as an Admin Operasional, still inactive until its link and activation (J-ACC-02).
    await page.goto('/pengaturan/pengguna');
    await expect(page.getByRole('row').filter({ hasText: email })).toContainText('Admin Operasional');

    await guardsClean(page, testInfo, [a.id, b.id]);
    await clean(captured, testInfo);
});

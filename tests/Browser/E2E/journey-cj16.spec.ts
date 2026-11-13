import { expect as base, type Locator, type Page, test } from '@playwright/test';

import { type Account, signIn } from '../account-fixtures';
import { uniquePdf } from '../evidence-fixtures';
import { clean, watch } from '../journey-fixtures';
import { csv, fx09, type Fx09Batch, type Fx09Seed, letters, merged, secondContext, wibToday } from '../journey-k3-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-09 (TASK-064; CJ-16; J-MIG-01; AC-17; UXH-10; UXS-19; UXS-31; UXS-32; WF-MIG-01; NM-03; criterion 11): the opening import at the desk on
// FX-09's batches — prepare, quarantine, dry run, joint sign-off, commit, the numbering seeds and go-live —, STAGING ONLY (TEST_STRATEGY §7, §4):
// its specification and FX-09 batches are written here, its run is the operator's on staging (TASK-081), with staging's own accounts, worker
// and go-live, never the local stack's fixture routes and never a check written here as passed. In CI — and anywhere CJ16_STAGING is not set —
// every test of this file is skipped with its reason, recorded as NOT RUN in TASK-064's evidence until the operator's staging run supplies
// its evidence in TEST_STRATEGY §16's form (TS-08). The run's secrets come from the operator's environment, never from the repository.
//
// The operator's environment (all required for the run):
//   CJ16_STAGING=1                      the run is the operator's, on staging (E2E_BASE_URL is staging's address)
//   CJ16_OWNER_EMAIL / CJ16_OWNER_PASSWORD / CJ16_OWNER_TOTP     an Owner of staging with its TOTP secret
//   CJ16_ADMIN_EMAIL / CJ16_ADMIN_PASSWORD / CJ16_ADMIN_TOTP     an Admin preparing in CJ16_COMPANY only, with `import` preparation
//   CJ16_COMPANY / CJ16_COMPANY_CODE    the company the batches open (its public identifier and its code)
//   CJ16_OUTSIDE_CODE                   a company's code outside the Admin's grants (UXS-19)
//   CJ16_CUTOFF                         the batches' cutoff date, YYYY-MM-DD, on or before today
//   CJ16_GO_LIVE_RECORDED=0|1           whether go-live is recorded yet: UXS-32 runs before it, the seeds' start after it
//   CJ16_SEEDS (optional)               the seeds as TYPE:PERIOD:LAST,…; FX-09's PROJECT and PURCHASE of the cutoff's year when unset
// After the run the operator records ReconcileGuards' result on staging (TS-11) — the local stack's reconciliation fixture does not exist there.

// A whole cutover over PG-058 and PG-061, each batch waiting on staging's worker.
test.describe.configure({ timeout: 300_000, mode: 'serial' });

const NOT_RUN = 'CJ-16 is staging only (TEST_STRATEGY §7): NOT RUN here — recorded as NOT RUN in TASK-064 until the operator\'s staging run (TASK-081) supplies its evidence (criterion 11; TS-08)';
const STAGING = process.env.CJ16_STAGING === '1';

test.skip(!STAGING, NOT_RUN);

const DESK = { width: 1280, height: 900 };

function env(name: string): string {
    const value = process.env[name];

    if (value === undefined || value === '') {
        throw new Error(`CJ-16's staging run needs ${name} in the operator's environment.`);
    }

    return value;
}

function account(prefix: 'OWNER' | 'ADMIN'): Account {
    return { id: '', email: env(`CJ16_${prefix}_EMAIL`), password: env(`CJ16_${prefix}_PASSWORD`), secret: env(`CJ16_${prefix}_TOTP`) };
}

/**
 * The seeds the company's schemes need at the cutoff (SeedRows::missing), from CJ16_SEEDS as `TYPE:PERIOD:LAST` pairs separated by commas —
 * the last numbers the operator reads from the company's papers —, or FX-09's synthetic default when it is not set.
 */
function seeds(): Fx09Seed[] | undefined {
    const listed = process.env.CJ16_SEEDS;

    return listed === undefined || listed === ''
        ? undefined
        : listed.split(',').map((entry) => {
              const [jenis = '', periode = '', last = ''] = entry.trim().split(':');

              return { jenis, periode, last };
          });
}

type Cutover = { company: string; code: string; outside: string; cutoff: string; run: string; batches: Fx09Batch[] };

function cutover(): Cutover {
    const run = letters(6);
    const code = env('CJ16_COMPANY_CODE');
    const cutoff = env('CJ16_CUTOFF');
    expect(cutoff <= wibToday(), 'the cutoff is on or before today').toBe(true);

    return { company: env('CJ16_COMPANY'), code, outside: env('CJ16_OUTSIDE_CODE'), cutoff, run, batches: fx09(code, env('CJ16_OUTSIDE_CODE'), run, cutoff, seeds()) };
}

function batchOf(cut: Cutover, kind: string): Fx09Batch {
    const batch = cut.batches.find((candidate) => candidate.kind === kind);

    if (batch === undefined) {
        throw new Error(`FX-09 holds no ${kind} batch.`);
    }

    return batch;
}

/** "Siapkan Batch" on PG-058 from a source of the rows given, for the company of the cutover. */
async function prepare(page: Page, cut: Cutover, kind: string, name: string, rows: Record<string, string>[]): Promise<Locator> {
    await page.goto('/data-master/impor');
    await page.getByRole('button', { name: 'Siapkan Batch', exact: true }).first().click();
    const sheet = page.getByRole('dialog', { name: 'Siapkan Batch' });
    await sheet.getByLabel('Jenis data').selectOption({ value: kind });
    await sheet.getByLabel('Tanggal batas').fill(cut.cutoff);
    const company = sheet.getByRole('checkbox', { name: new RegExp(`^${cut.code} · `) });

    if ((await company.count()) > 0) {
        await company.check();
    }

    await sheet.getByLabel('File sumber').setInputFiles({ name, mimeType: 'text/csv', buffer: csv(rows) });
    await expect(sheet.getByText(name)).toBeVisible();
    await sheet.getByRole('button', { name: 'Siapkan Batch', exact: true }).click();
    await expect(page.locator('#konten')).toContainText('Batch disiapkan.');

    return page.locator('[data-slot="import-batch"]');
}

/** The batch's state as staging's worker leaves it: the page shown again until the state is in (no fixture runs the job on staging). */
async function settles(page: Page, batch: Locator, state: string): Promise<void> {
    await expect(async () => {
        await page.reload();
        await expect(batch.locator(`[data-status="import_batch.${state}"]`)).toBeVisible({ timeout: 5_000 });
    }).toPass({ timeout: 60_000 });
}

test('CJ-16 quarantine: FX-09\'s malformed, duplicate and out-of-scope rows surfaced with their reasons, the batch never passing with them (J-MIG-01; UXS-19; FL-06)', async ({ browser, page }, testInfo) => {
    const captured = watch(page);
    await page.setViewportSize(DESK);
    const cut = cutover();
    await signIn(page, account('OWNER'));

    for (const kind of ['LOCATION', 'PRODUCT', 'SUPPLIER']) {
        const fx = batchOf(cut, kind);
        // No seed row in a quarantined source: a batch left PREPARED holding valid seed rows would hold the type's start back (NM-03).
        const batch = await prepare(page, cut, fx.kind, `karantina-${fx.name}`, [...fx.clean.filter((row) => row.nomor_jenis === undefined), ...fx.dirty]);
        const problems = batch.locator('[data-slot="import-problems"]');

        for (const finding of fx.findings) {
            await expect(problems).toContainText(finding);
        }

        await batch.getByRole('button', { name: 'Jalankan Uji Coba', exact: true }).click();
        await expect(page.locator('#konten')).toContainText('Uji coba dijalankan.');
        await settles(page, batch, 'PREPARED');
        await expect(batch.getByRole('button', { name: 'Jalankan Uji Coba Lagi' })).toBeVisible();
    }

    // UXS-19: the Admin preparing in its one company; a row of a company outside its scope listed under *Tidak Valid* with MSG-18 alone.
    const context = await secondContext(browser, testInfo);
    const admin = await context.newPage();
    const capturedAdmin = watch(admin);
    await signIn(admin, account('ADMIN'));
    const stock = batchOf(cut, 'OPENING_STOCK');
    const batch = await prepare(admin, cut, stock.kind, `karantina-${stock.name}`, [...stock.clean, ...stock.dirty]);
    const problems = batch.locator('[data-slot="import-problems"]');
    await expect(problems).toContainText('Tidak dapat diimpor — perlu tinjauan Owner.');
    await expect(problems).toContainText('Tidak Valid');
    await expect(problems).not.toContainText('Duplikat');
    await expect(problems).not.toContainText(cut.outside);

    for (const finding of stock.findings) {
        await expect(problems).toContainText(finding);
    }

    await clean(merged(captured, capturedAdmin), testInfo);
    await context.close();
});

test('CJ-16 cutover: clean FX-09 batches dry-run, signed off by the Admin and the Owner, committed behind the step-up with their seeds, then numbering and go-live (J-MIG-01; UXS-31; UXS-32; AC-17)', async ({ browser, page }, testInfo) => {
    const captured = watch(page);
    await page.setViewportSize(DESK);
    const cut = cutover();
    const owner = account('OWNER');
    await signIn(page, owner);
    const context = await secondContext(browser, testInfo);
    const admin = await context.newPage();
    const capturedAdmin = watch(admin);
    await signIn(admin, account('ADMIN'));
    const numbering = `/pengaturan/penomoran?perusahaan=${cut.company}&jenis=purchase`;

    // UXS-32: before go-live is recorded, the start and seed commands are not offered and the state is explained; no control records it.
    if (process.env.CJ16_GO_LIVE_RECORDED !== '1') {
        await page.goto(numbering);
        await expect(page.getByText('Go-live belum dicatat. Penomoran dimulai setelah go-live dicatat saat peralihan sistem.')).toBeVisible();
        await expect(page.getByRole('button', { name: 'Mulai Penomoran' })).toHaveCount(0);
        await expect(page.getByRole('button', { name: 'Tetapkan Nomor Awal' })).toHaveCount(0);
        await expect(page.getByRole('button', { name: /go-live/i })).toHaveCount(0);
    }

    for (const kind of ['LOCATION', 'PRODUCT', 'SUPPLIER', 'OPENING_STOCK', 'OPEN_PURCHASE']) {
        const fx = batchOf(cut, kind);
        const batch = await prepare(page, cut, fx.kind, fx.name, fx.clean);
        await expect(batch.locator('[data-status="import_batch.PREPARED"]')).toBeVisible();
        const id = new URL(page.url()).searchParams.get('batch') ?? '';

        // The control totals per company for the batch's facts, to match against the signed totals (DP-18; §15).
        if (kind === 'OPENING_STOCK') {
            await expect(batch).toContainText('Total kontrol per perusahaan');
        }

        // UXS-31: while this batch holds valid seed rows, a start of the type is refused with the batch linked and its commit-or-abort path.
        if (kind === 'LOCATION' && process.env.CJ16_GO_LIVE_RECORDED === '1') {
            await page.goto(numbering);
            await expect(page.locator('#konten')).toContainText('masih memuat baris nomor awal');
            await page.goto(`/data-master/impor?batch=${id}`);
        }

        await batch.getByRole('button', { name: 'Jalankan Uji Coba', exact: true }).click();
        await expect(page.locator('#konten')).toContainText('Uji coba dijalankan.');
        await settles(page, batch, 'DRY_RUN_PASSED');

        // The joint sign-off: the Owner's and the Admin's, each with its evidence (OD-13).
        for (const signer of [page, admin]) {
            await signer.goto(`/data-master/impor?batch=${id}`);
            const own = signer.locator('[data-slot="import-batch"]');
            await own.getByRole('button', { name: 'Setujui Hasil Uji Coba' }).click();
            const sheet = signer.getByRole('dialog', { name: 'Setujui Hasil Uji Coba' });
            await sheet.getByLabel('Bukti persetujuan').setInputFiles({ name: `persetujuan-${cut.run}.pdf`, mimeType: 'application/pdf', buffer: uniquePdf() });
            await expect(sheet.getByText(`persetujuan-${cut.run}.pdf`)).toBeVisible();
            await sheet.getByRole('button', { name: 'Setujui Hasil Uji Coba' }).click();
            await expect(signer.locator('#konten')).toContainText('Persetujuan dicatat.');
        }

        await page.reload();
        await expect(batch.locator('[data-status="import_batch.SIGNED_OFF"]')).toBeVisible();

        // The Owner's commit behind MSG-39 and the step-up (OD-13; AU-15).
        await batch.getByRole('button', { name: 'Jalankan Impor' }).click();
        const confirm = page.getByRole('alertdialog', { name: 'Jalankan Impor' });
        await expect(confirm).toContainText(cut.code);
        await expect(confirm).toContainText(cut.cutoff);
        await confirm.getByRole('button', { name: 'Jalankan Impor' }).click();
        // The step-up at the session's first sensitive step; a later commit within its window goes straight through (AU-15).
        const stepUp = page.getByRole('alertdialog', { name: 'Konfirmasi Kata Sandi' });
        await expect(stepUp.or(page.locator('#konten').getByText('Impor dijalankan.'))).toBeVisible();

        if (await stepUp.isVisible()) {
            await stepUp.getByLabel('Kata sandi').fill(owner.password);
            await stepUp.getByRole('button', { name: 'Lanjutkan' }).click();
        }

        await expect(page.locator('#konten')).toContainText('Impor dijalankan.');
        await settles(page, batch, 'COMMITTED');
        await expect(batch.locator('[data-slot="import-counts"]')).toContainText('Terimpor');
        await expect(batch).toContainText('Fakta pembuka yang salah dikoreksi di sini, tidak pernah dengan mengimpor ulang.');
    }

    // The numbering at the cutover: the PURCHASE seed the import wrote stands on PG-061 after the last legacy number (HO-32; CB-02).
    await page.goto(numbering);
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Penomoran Dokumen');
    const purchaseSeed = (seeds() ?? [{ jenis: 'PURCHASE', periode: cut.cutoff.slice(0, 4), last: '20' }]).find((seed) => seed.jenis === 'PURCHASE');

    if (purchaseSeed !== undefined) {
        const counter = page.getByRole('row').filter({ has: page.getByRole('cell', { name: purchaseSeed.periode, exact: true }) });
        await expect(counter).toContainText(String(Number(purchaseSeed.last) + 1));
    }

    await clean(merged(captured, capturedAdmin), testInfo);
    await context.close();
});

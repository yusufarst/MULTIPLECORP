import { type Page } from '@playwright/test';

import { totp } from './account-fixtures';
import { numberingCompany, ownerSignedIn } from './numbering-fixtures';

// TASK-057's browser fixture (routes/fixtures.php; ImportFixture): the Owner signed in, a company of the run whose PROJECT and PURCHASE schemes cover the
// cutoff, go-live recorded after it, the seeds each batch carries, a synthetic CSV source built here, the Admin's sign-off and the batch's owed job run on
// the server since the CI stack runs no worker, and an Admin of the run preparing in one company only. Synthetic data only.

export type ImportRun = { company: string; code: string; cutoff: string; seeds: { jenis: string; periode: string }[] };

/** The fixture accounts' password (AccountFixture::PASSWORD), the step-up's answer. */
export const PASSWORD = 'Kata-Sandi-Uji-9';

export async function importRun(page: Page, run: string, locale: 'id' | 'en' = 'id'): Promise<ImportRun> {
    await ownerSignedIn(page, run, locale);
    const company = await numberingCompany(page, `${String(new Date().getFullYear())}-01-01`);

    return { company, ...(await prepareCompany(page, company)) };
}

export async function prepareCompany(page: Page, company: string, preparer?: string): Promise<Omit<ImportRun, 'company'>> {
    const response = await page.request.post('/__fixtures/impor', { data: { company, preparer } });

    if (!response.ok()) {
        throw new Error(`The import fixture failed: ${String(response.status())} ${await response.text()}`);
    }

    return (await response.json()) as Omit<ImportRun, 'company'>;
}

/** A CSV of the rows with the run's seed rows, each after the last legacy number 20 (HO-32). */
export function source(run: Omit<ImportRun, 'company'>, rows: Record<string, string>[]): Buffer {
    const all: Record<string, string>[] = [...rows, ...run.seeds.map((seed) => ({ perusahaan: run.code, nomor_jenis: seed.jenis, nomor_periode: seed.periode, nomor_terakhir: '20' }))];
    const columns = [...new Set([...all.flatMap((row) => Object.keys(row)), 'id', 'perusahaan', 'nomor_jenis', 'nomor_periode', 'nomor_terakhir'])];

    return Buffer.from([columns.join(','), ...all.map((row) => columns.map((column) => row[column] ?? '').join(','))].join('\n') + '\n', 'utf8');
}

export async function adminSignOff(page: Page, batch: string): Promise<void> {
    await page.request.post('/__fixtures/impor/setujui', { data: { batch } });
}

/** The batch's owed dry run or commit run on the server, then the page shown again. */
export async function work(page: Page, batch: string): Promise<void> {
    await page.request.post('/__fixtures/impor/kerjakan', { data: { batch } });
    await page.goto(`/data-master/impor?batch=${batch}`);
}

/** The batch the page shows, from its address. */
export function batchOf(page: Page): string {
    return new URL(page.url()).searchParams.get('batch') ?? '';
}

/** An Admin of the run, signed in on its own page, preparing in the company given only (PJ-20). */
export async function preparerSignedIn(page: Page, run: string): Promise<string> {
    const account = (await (await page.request.post('/__fixtures/akun', { data: { run, kind: 'totp' } })).json()) as { id: string; email: string; password: string; secret: string };
    await page.goto('/masuk');
    await page.getByLabel('E-mail').fill(account.email);
    await page.getByLabel('Kata sandi', { exact: true }).fill(account.password);
    await page.getByRole('button', { name: 'Masuk', exact: true }).click();
    await page.waitForURL('**/masuk/verifikasi');
    await page.getByLabel('Kode 6 digit dari aplikasi autentikator').fill(totp(account.secret));
    await page.getByRole('button', { name: 'Verifikasi' }).click();
    await page.waitForURL((url) => !url.pathname.startsWith('/masuk'));

    return account.id;
}

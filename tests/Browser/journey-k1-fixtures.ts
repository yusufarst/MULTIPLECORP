import { expect, type Locator, type Page } from '@playwright/test';

import { type ImportRun, source } from './import-fixtures';
import { watch } from './journey-fixtures';

// TASK-064's access-journey preconditions for CJ-09, CJ-11 and CJ-13 (routes/fixtures.php; JourneyAccessFixture) and the
// page steps two of them share: companies of the run with nothing in them, the signed-in account's own grants for an Admin
// persona of SECURITY_TEST_MATRIX §2, the session's password confirmation moved past AU-15's 15 minutes, the two QS-14
// sources no page reaches, PG-038's registration of a file and PG-060's access section. Synthetic data only.

export type Captured = ReturnType<typeof watch>;
export type RunCompany = { id: string; code: string; name: string };

/** Companies for the run, with nothing in them (JourneyAccessFixture::companies). */
export async function runCompanies(page: Page, count: number): Promise<RunCompany[]> {
    const response = await page.request.post('/__fixtures/journey-k1/perusahaan', { data: { count } });

    if (!response.ok()) {
        throw new Error(`The companies fixture failed: ${String(response.status())} ${await response.text()}`);
    }

    return (await response.json()) as RunCompany[];
}

/** The signed-in account's own companies and capabilities, each granted once. */
export async function grantSelf(page: Page, companies: string[], capabilities: string[]): Promise<void> {
    const response = await page.request.post('/__fixtures/journey-k1/hak', { data: { companies, capabilities } });

    if (!response.ok()) {
        throw new Error(`The grant fixture failed: ${String(response.status())} ${await response.text()}`);
    }
}

/** The session's last password confirmation 16 minutes back (AU-15; UXS-26), asked while the page sends nothing of its own. */
export async function confirmationAged(page: Page): Promise<void> {
    await expect(page.locator('[data-region-state="loading"]')).toHaveCount(0, { timeout: 60_000 });
    const response = await page.request.post('/__fixtures/journey-k1/langkah-naik');

    if (!response.ok()) {
        throw new Error(`The step-up fixture failed: ${String(response.status())} ${await response.text()}`);
    }
}

export type ReviewSources = { owner: string; issuer: string; anomaly: string; anomaly_name: string };

/** Pool evidence the signed-in Owner recorded and an AUTHORIZATION_ANOMALY of an account of the run (QS-14). */
export async function reviewSources(page: Page): Promise<ReviewSources> {
    const response = await page.request.post('/__fixtures/journey-k1/tinjauan');

    if (!response.ok()) {
        throw new Error(`The review fixture failed: ${String(response.status())} ${await response.text()}`);
    }

    return (await response.json()) as ReviewSources;
}

/** Several pages' captures as one journey's evidence (TEST_STRATEGY §16). */
export function merged(...captures: Captured[]): Captured {
    return {
        console: captures.flatMap((captured) => captured.console),
        failed: captures.flatMap((captured) => captured.failed),
        csp: captures.flatMap((captured) => captured.csp),
        requests: captures.reduce((sum, captured) => sum + captured.requests, 0),
        engine: captures[0]?.engine ?? '',
    };
}

/**
 * The not-found answers a journey asks for by design (IP-21; AZ-11; UXS-23; UXS-25): each must have been answered — a step
 * expecting it that met another status fails here —, and only those exact requests leave the captured failures; every
 * other critical status still fails the journey.
 */
export function designedNotFound(captured: Captured, expected: RegExp[]): Captured {
    for (const pattern of expected) {
        expect(captured.failed.some((entry) => pattern.test(entry)), `the designed not-found answer ${pattern.source} was given`).toBe(true);
    }

    return { ...captured, failed: captured.failed.filter((entry) => !expected.some((pattern) => pattern.test(entry))) };
}

/** PG-005's not-found page as the account sees it, without its per-request reference code (MSG-09). */
export async function notFoundFace(page: Page): Promise<string> {
    const system = page.locator('[data-system-page="not_found"]');
    await expect(system).toBeVisible();
    await expect(system).toContainText('Halaman tidak ditemukan.');

    return system.evaluate((element) => {
        const copy = element.cloneNode(true) as HTMLElement;
        copy.querySelectorAll('[data-code]').forEach((code) => {
            code.remove();
        });

        return copy.innerText.replace(/\s+/g, ' ').trim();
    });
}

/** PG-038's registration of a file as a client order of the branch (J-DOC-02), its answer the evidence's own sheet. */
export async function registerOrder(page: Page, company: string, bytes: Buffer, name: string, issuer: string, reference: string): Promise<Locator> {
    await page.goto(`/dokumen/bukti?cabang=${company}`);
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Bukti');
    await page.getByRole('button', { name: 'Unggah', exact: true }).filter({ visible: true }).first().click();
    const sheet = page.getByRole('dialog', { name: 'Unggah Dokumen' });
    await expect(sheet).toBeVisible();
    await sheet.getByLabel('Unggah', { exact: true }).setInputFiles({ name, mimeType: 'application/pdf', buffer: bytes });
    await expect(sheet.getByText(name)).toBeVisible();
    await sheet.getByLabel('Jenis bukti').selectOption({ label: 'Surat pesanan klien' });
    await sheet.getByLabel(/^Penerbit\b/).fill(issuer);
    await sheet.getByLabel('Nomor referensi penerbit').fill(reference);
    await sheet.getByLabel('Tanggal dokumen').fill('2026-10-01');
    await sheet.getByRole('button', { name: 'Daftarkan Bukti' }).click();

    return page.getByRole('dialog', { name: 'Surat pesanan klien' });
}

/** The opening import's batch prepared by an Admin of the run from a CSV (J-MIG-01), as opening-import.spec.ts prepares one. */
export async function prepareBatch(page: Page, run: Omit<ImportRun, 'company'>, kind: string, rows: Record<string, string>[], name: string): Promise<Locator> {
    await page.goto('/data-master/impor');
    await page.getByRole('button', { name: 'Siapkan Batch', exact: true }).first().click();
    const sheet = page.getByRole('dialog', { name: 'Siapkan Batch' });
    await sheet.getByLabel('Jenis data').selectOption({ value: kind });
    await sheet.getByLabel('Tanggal batas').fill(run.cutoff);
    await sheet.getByRole('checkbox', { name: new RegExp(`^${run.code} · `) }).check();
    await sheet.getByLabel('File sumber').setInputFiles({ name, mimeType: 'text/csv', buffer: source(run, rows) });
    await expect(sheet.getByText(name)).toBeVisible();
    await sheet.getByRole('button', { name: 'Siapkan Batch', exact: true }).click();
    await expect(page.locator('#konten')).toContainText('Batch disiapkan.');

    return page.locator('[data-slot="import-batch"]');
}

/** PG-060's account opened on its *Akses* section (J-ACC-02). */
export async function openAccess(page: Page, account: string): Promise<void> {
    await page.goto(`/pengaturan/pengguna?akun=${account}&bagian=akses`);
    await expect(page.getByRole('tab', { name: 'Akses' })).toHaveAttribute('aria-selected', 'true');
    await expect(page.getByRole('button', { name: 'Pratinjau Akses' })).toBeVisible();
}

/** A company or capability ticked or cleared on PG-060 by its label — the checkbox's own input is hidden behind it. */
export async function toggle(page: Page, key: string): Promise<void> {
    await page.locator(`label[for="${key}"]`).click();
}

/** The checkbox of a company or capability on PG-060, for its state (`data-checked` / `data-unchecked`). */
export function box(page: Page, key: string): Locator {
    return page.locator('li', { has: page.locator(`label[for="${key}"]`) }).locator('[data-slot="checkbox"]');
}

/** "Pratinjau Akses", then its matrix shown (PT-30; UXS-27). */
export async function previewAccess(page: Page): Promise<Locator> {
    await page.getByRole('button', { name: 'Pratinjau Akses' }).click();
    const preview = page.locator('[data-slot="capability-preview"]');
    await expect(preview).toBeVisible();

    return preview;
}

/** The PG-063 timeline of one account (IX-13), its rows. */
export async function accountTimeline(page: Page, account: string): Promise<Locator> {
    await page.goto(`/aktivitas/riwayat?catatan=user:${account}`);
    const table = page.getByRole('table', { name: 'Riwayat Aktivitas' });
    await expect(table).toBeVisible();

    return table.getByRole('row');
}

/** PG-008's controls that must not exist (UXS-40): no approve, dismiss or read control on the review queue. */
export async function noDecisionControl(page: Page): Promise<void> {
    for (const control of ['Setujui', 'Abaikan', 'Tandai dibaca']) {
        await expect(page.getByRole('button', { name: control, exact: true })).toHaveCount(0);
    }
}

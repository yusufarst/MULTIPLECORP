import { expect, test } from '@playwright/test';

import { type Account, signIn } from '../account-fixtures';
import { projectRun } from '../project-fixtures';
import { searchStock, stockRun } from '../stock-fixtures';

// The Owner's walkthrough preview (the directive of 2026-11-27): the same synthetic fixtures the browser layers use, run once
// against the isolated preview stack so that a person can sign in and see Beranda, Proyek and Stok with something in them.
// Skipped unless WALKTHROUGH_SEED=1 — the CI workflow runs it once as a rehearsal without printing anything, and the
// layers never list it. The two accounts it makes are printed only with WALKTHROUGH_PRINT=1, on the operator's own terminal.

test.describe.configure({ timeout: 600_000 });

async function account(page: import('@playwright/test').Page, run: string, withTotp: boolean): Promise<Account> {
    const response = await page.request.post('/__fixtures/akun', { data: { run, kind: 'owner', totp: withTotp, locale: 'id' } });
    expect(response.ok()).toBe(true);

    return (await response.json()) as Account;
}

test('seeds the walkthrough preview and checks what an Owner who made none of it sees', async ({ page }) => {
    test.skip(process.env.WALKTHROUGH_SEED !== '1', 'Only for the isolated preview and its CI rehearsal.');
    const stamp = String(Date.now());

    // The data: a company with stock, a company with a project; each helper signs its own throwaway Owner in.
    await page.setViewportSize({ width: 1280, height: 900 });
    const stock = await stockRun(page, `wt-stok-${stamp}`);
    const project = await projectRun(page, `wt-proyek-${stamp}`, { project: true });

    // The people the walkthrough signs in as: an Owner with two-step verification, and an Owner who has none yet (E5's enrolment).
    const owner = await account(page, `wt-owner-${stamp}`, true);
    const fresh = await account(page, `wt-baru-${stamp}`, false);

    await page.context().clearCookies();
    await signIn(page, owner);
    await page.goto('/gudang/stok');
    await searchStock(page, stock.name);
    await expect(page.locator('#konten')).toContainText(stock.name);
    await page.goto('/proyek');
    await expect(page.locator('#konten')).toContainText(project.client);

    if (process.env.WALKTHROUGH_PRINT === '1') {
        process.stdout.write(
            [
                '',
                '== Akun walkthrough (sintetis; hanya untuk pratinjau ini) ==',
                `Owner dengan 2FA   : ${owner.email} | ${owner.password} | kunci TOTP (ketik manual di aplikasi autentikator): ${owner.secret ?? '-'}`,
                `Owner tanpa 2FA    : ${fresh.email} | ${fresh.password} (untuk mencoba pendaftaran 2FA dan kode pemulihan)`,
                `Cari di Stok       : ${stock.name}`,
                `Klien di Proyek    : ${project.client}`,
                '',
            ].join('\n'),
        );
    }
});

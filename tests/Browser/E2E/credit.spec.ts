import { expect as base, test } from '@playwright/test';

import { creditRun } from '../credit-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-09 (TASK-053; J-FIN-06; CJ-04's credit and refund; INT-256; INT-264; NAV-048): a client's Rp200,000 credit seen on PG-048 traced to its payment, its project's credit held with a reason, then refunded with its transfer proof, each answering in place.

test.describe.configure({ timeout: 360_000 });

test('J-FIN-06: holds a project\'s credit, then refunds the client\'s credit with its transfer proof (CJ-04; AX-23)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await creditRun(page, `kredit-${String(Date.now())}`);

    await page.goto('/keuangan/kredit-pelanggan');
    const group = page.locator('[data-slot="credit-group"]').filter({ hasText: run.client_name });
    await expect(group).toContainText('200.000');
    await group.locator('[data-slot="credit-project"]').first().getByRole('button', { name: 'Tahan Kredit' }).click();
    const holding = page.getByRole('alertdialog');
    await holding.getByLabel('Alasan').fill('Untuk proyek lanjutan');
    await holding.getByRole('button', { name: 'Tahan Kredit', exact: true }).click();
    await expect(page.locator('#konten')).toContainText('Kredit proyek ditahan.');

    await group.locator('[data-slot="credit-source"]').first().getByRole('button', { name: 'Kembalikan Dana' }).click();
    const refunding = page.getByRole('alertdialog');
    await refunding.getByLabel('Bukti transfer').selectOption({ value: run.transfer_proof });
    await refunding.getByLabel('Alasan').fill('Klien meminta dana kembali');
    await refunding.getByRole('button', { name: 'Kembalikan Dana', exact: true }).click();
    await expect(page.locator('#konten')).toContainText('Pengembalian dana dicatat.');
    await expect(page.locator('[data-slot="credit-refund"]').first()).toContainText('Berlaku');
});

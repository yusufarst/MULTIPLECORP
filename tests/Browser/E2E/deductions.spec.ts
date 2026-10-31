import { expect as base, test } from '@playwright/test';

import { invoiceRun } from '../invoice-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-09 (TASK-052; J-FIN-04; CJ-08; INT-255; INT-260; FX-01 6): a net remittance settled on its evidence, the receivable closed with no Cash-In for the fee; the contra in place, both records visible and the receivable raised again; beyond the evidence a refusal with its figure, the input kept.

test.describe.configure({ timeout: 420_000 });

test('settles a SIPLAH net remittance on its evidence and contras it in place (J-FIN-04; CJ-08; AC-15; INT-255; INT-260)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await invoiceRun(page, `potong-${String(Date.now())}`, { stage: 'issued', billed: true, bank: true, paid: '970000', apply: true, claim: '30000', proven: '30000' });

    await page.goto(`/keuangan/potongan?perusahaan=${run.company}`);
    const waiting = page.getByTestId('deduction-waiting');
    await expect(waiting).toContainText('Rp30.000');
    await waiting.getByRole('button', { name: 'Catat Potongan' }).click();

    const form = page.getByRole('dialog', { name: 'Catat Potongan' });
    await expect(form).toContainText('Tanpa bukti tidak ada potongan');
    await expect(form.getByLabel(/^Jenis potongan/).locator('option')).toHaveCount(2);
    await form.getByLabel(/^Jenis potongan/).selectOption({ index: 1 });
    await form.getByLabel(/^Invoice/).selectOption({ index: 1 });
    await form.getByLabel(/^Jumlah/).fill('30000');
    await form.getByLabel(/^Nota biaya/).selectOption({ index: 1 });
    await form.getByRole('button', { name: 'Catat Potongan' }).click();

    const confirm = page.getByRole('alertdialog');
    await confirm.getByLabel(/^Alasan/).fill('Nota biaya SIPLAH terverifikasi');
    await confirm.getByRole('button', { name: 'Catat Potongan' }).click();

    await expect(page.getByRole('table', { name: 'Potongan' })).toContainText('BIAYA_SIPLAH');
    await expect(page.getByRole('table', { name: 'Potongan' })).toContainText('Aktif');
    await expect(page.getByText('Tidak ada potongan yang menunggu bukti.')).toBeVisible();

    await page.goto(`/keuangan/invoice/${run.invoice ?? ''}`);
    await expect(page.locator('#konten')).toContainText('Rp0');

    await page.goto(`/keuangan/potongan?perusahaan=${run.company}`);
    await page.getByRole('button', { name: 'Tindakan baris' }).first().click();
    await page.getByRole('menuitem', { name: 'Batalkan Potongan (Kontra)' }).click();
    const contra = page.getByRole('alertdialog');
    await contra.getByLabel(/^Alasan/).fill('Nota biaya keliru');
    await contra.getByRole('button', { name: 'Batalkan Potongan (Kontra)' }).click();

    await expect(page.getByRole('table', { name: 'Potongan' })).toContainText('Dikontra');
    await expect(page.getByTestId('deduction-waiting')).toContainText('Rp30.000');
});

test('refuses a deduction beyond its evidence with the figure, the form keeping its input (C-18; IP-03; NV-04)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await invoiceRun(page, `potong-lebih-${String(Date.now())}`, { stage: 'issued', billed: true, bank: true, paid: '900000', apply: true, claim: '50000', proven: '10000' });

    await page.goto(`/keuangan/potongan?perusahaan=${run.company}`);
    await page.getByTestId('deduction-waiting').getByRole('button', { name: 'Catat Potongan' }).click();
    const form = page.getByRole('dialog', { name: 'Catat Potongan' });
    await expect(form.getByLabel(/^Jenis potongan/).locator('option')).toHaveCount(2);
    await form.getByLabel(/^Jenis potongan/).selectOption({ index: 1 });
    await form.getByLabel(/^Invoice/).selectOption({ index: 1 });
    await form.getByLabel(/^Jumlah/).fill('40000');
    await form.getByLabel(/^Nota biaya/).selectOption({ index: 1 });
    await form.getByRole('button', { name: 'Catat Potongan' }).click();
    const confirm = page.getByRole('alertdialog');
    await confirm.getByLabel(/^Alasan/).fill('Nota biaya');
    await confirm.getByRole('button', { name: 'Catat Potongan' }).click();

    await expect(confirm).toContainText('Rp10.000');
    await confirm.getByRole('button', { name: 'Kembali' }).click();
    await expect(form.getByLabel(/^Jumlah/)).toHaveValue(/40/);
});

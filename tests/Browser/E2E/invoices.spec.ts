import { expect as base, test } from '@playwright/test';

import { invoiceRun } from '../invoice-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-09 (TASK-049; J-FIN-01 — S2-J03's composer on the desktop and the phone —; J-FIN-08; CJ-04's invoices and pre-payment Kuitansi; CJ-06's revision and void): the journey's composer, the issue saying that no receivable is active, the cap's refusal with what remains; the Kuitansi's draft on PG-036 issued with its reason,
// recording no payment; a correction listing each reduction and what becomes of it.

test.describe.configure({ timeout: 240_000 });

test('composes a value-only invoice beside its live paper, saves and issues it, the answer stating that nothing is billed (J-FIN-01; D-DS-23; INT-241; INT-242)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await invoiceRun(page, `invoice-${String(Date.now())}`);

    await page.goto(`/keuangan/invoice/baru?proyek=${run.project}`);
    await expect(page.getByRole('heading', { name: new RegExp(`Invoice baru — ${run.number}`) })).toBeVisible();
    const paper = page.getByRole('region', { name: /^Pratinjau Invoice/ });
    await expect(paper).toContainText('DRAF — belum terbit');
    await page.getByLabel(/^Keterangan/).fill('Termin pertama');
    await page.getByRole('textbox', { name: /^Nilai/ }).fill('1000000');
    await expect(paper).toContainText('Termin pertama');
    await expect(paper).toContainText('Rp1.000.000');

    await page.getByRole('button', { name: 'Simpan Draf' }).click();
    await expect(page.locator('#konten').getByText('Draf invoice tersimpan.')).toBeVisible();
    await page.getByRole('button', { name: 'Terbitkan Invoice' }).click();
    const dialog = page.getByRole('alertdialog');
    await expect(dialog).toContainText('belum ada piutang aktif');
    await dialog.getByRole('button', { name: 'Terbitkan Invoice' }).click();

    await expect(page.locator('#konten').getByText(/Invoice UJI\/INV\/.* sudah terbit/)).toBeVisible();
    await page.getByRole('link', { name: 'Buka Invoice' }).click();
    await expect(page.getByTestId('position')).toContainText('Rp1.000.000');
    await expect(page.locator('#konten')).toContainText('Belum Ditagihkan');
});

test('refuses an issue above the cap with what remains, the input kept (C-31; L-16; MSG-02)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await invoiceRun(page, `invoice-batas-${String(Date.now())}`);

    await page.goto(`/keuangan/invoice/baru?proyek=${run.project}`);
    await page.getByLabel(/^Keterangan/).fill('Terlalu besar');
    await page.getByRole('textbox', { name: /^Nilai/ }).fill('3000000');
    await page.getByRole('button', { name: 'Simpan Draf' }).click();
    await expect(page.locator('#konten').getByText('Draf invoice tersimpan.')).toBeVisible();
    await page.getByRole('button', { name: 'Terbitkan Invoice' }).click();
    await page.getByRole('alertdialog').getByRole('button', { name: 'Terbitkan Invoice' }).click();
    await expect(page.getByRole('alertdialog')).toContainText(/Sisa yang bisa ditagih Rp2\.500\.000/);
});

test('issues the pre-payment Kuitansi on PG-036 with its reason, recording no payment (J-FIN-08; AX-31; GAP-029; DIR-081 OB-02)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await invoiceRun(page, `kuitansi-${String(Date.now())}`, { stage: 'issued' });

    await page.goto(`/keuangan/invoice/${run.invoice ?? ''}`);
    await page.getByRole('button', { name: 'Tindakan lain' }).click();
    await page.getByRole('menuitem', { name: 'Terbitkan Kuitansi untuk Proses Pembayaran' }).click();
    const dialog = page.getByRole('alertdialog');
    await expect(dialog).toContainText('Tidak ada pembayaran yang tercatat');
    await dialog.getByLabel(/^Jumlah Kuitansi/).fill('400000');
    await dialog.getByRole('button', { name: 'Lanjut' }).click();

    await expect(page).toHaveURL(/\/dokumen\//);
    await page.getByRole('button', { name: /^Terbitkan/ }).click();
    const issue = page.getByRole('alertdialog');
    await expect(issue).toContainText('Tidak ada pembayaran yang tercatat');
    await issue.getByRole('textbox').fill('Klien meminta untuk proses pembayaran');
    await issue.getByRole('button', { name: /^Terbitkan/ }).click();
    await expect(page.locator('#konten').getByText('Kuitansi terbit. Belum ada pembayaran yang tercatat.')).toBeVisible();
    await expect(page.locator('#konten')).toContainText('Menunggu Pembayaran');

    await page.goto(`/keuangan/invoice/${run.invoice ?? ''}`);
    await expect(page.getByTestId('kuitansi')).toContainText('Menunggu Pembayaran');
    await expect(page.getByTestId('position')).toContainText('Rp1.000.000');
});

test('voids an invoice with its basis, reason and the payment\'s disposition, listing each reduction (CJ-06; ST-07; IP-09; CM-23)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await invoiceRun(page, `invoice-batal-${String(Date.now())}`, { stage: 'issued', payment: true });

    await page.goto(`/keuangan/invoice/${run.invoice ?? ''}`);
    await expect(page.getByTestId('reductions')).toContainText('Rp400.000');
    await page.getByRole('button', { name: 'Tindakan lain' }).click();
    await page.getByRole('menuitem', { name: 'Batalkan Invoice' }).click();
    const dialog = page.getByRole('alertdialog');
    await dialog.getByLabel(/^Dasar koreksi/).selectOption('UNBILLED_ENTRY_ERROR');
    await expect(dialog.getByLabel(/^Pembayaran/)).toHaveValue('RELEASE');
    await dialog.getByRole('textbox').last().fill('Salah catat nilai invoice');
    await dialog.getByRole('button', { name: 'Batalkan Invoice' }).click();

    await expect(page.locator('#konten').getByText('Invoice dibatalkan.')).toBeVisible();
    await expect(page.locator('#konten')).toContainText('Salah catat nilai invoice');
    await expect(page.getByTestId('position')).toContainText('Rp0');
});

test('edits a draft on the phone through Edit, Preview and Finalize, the preview full-screen (S2-J03; DC-11)', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    const run = await invoiceRun(page, `invoice-hp-${String(Date.now())}`, { stage: 'draft' });

    await page.goto(`/keuangan/invoice/${run.invoice ?? ''}`);
    await expect(page).toHaveURL(/\/keuangan\/invoice\/baru\?invoice=/);
    await expect(page.locator('[data-slot="document-composer"]')).toHaveAttribute('data-band', 'phone');
    await page.getByRole('button', { name: 'Pratinjau', exact: true }).click();
    await expect(page.getByRole('region', { name: /^Pratinjau Invoice/ })).toContainText('DRAF — belum terbit');
    await page.getByRole('button', { name: 'Lanjut' }).click();
    await expect(page.getByRole('button', { name: 'Terbitkan Invoice' })).toBeVisible();
});

test('lists the branch\'s invoices, finds one by its exact number, and tells a number that matches none from an empty list (ROUTE-041; SCR-38; IP-17; PT-19; FA-22; TASK-096)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await invoiceRun(page, `invoice-daftar-${String(Date.now())}`, { stage: 'issued' });
    const number = run.invoice_number ?? '';
    expect(number).toMatch(/^UJI\/INV\//);

    await page.goto(`/keuangan/invoice?perusahaan=${run.company}`);
    const table = page.getByRole('table', { name: 'Invoice', exact: true });
    const field = page.getByLabel(/^Cari nomor invoice/);
    await expect(table).toContainText(number);
    await expect(table).toContainText('Belum Ditagihkan');

    // A number that matches none says so with MSG-25, naming the term sent, announced; never the empty list's sentence.
    await field.fill('TIDAK-ADA');
    await page.getByRole('button', { name: 'Cari' }).click();
    const unmatched = page.locator('[data-region-state="no-match"]');
    await expect(unmatched).toContainText('Kode TIDAK-ADA tidak dikenal.');
    await expect(page.locator('#konten [aria-live="polite"]').filter({ hasText: 'Kode TIDAK-ADA tidak dikenal.' })).toHaveCount(1);
    await expect(page.locator('#konten')).not.toContainText('Belum ada invoice di cabang ini.');

    // "Atur Ulang" clears the lookup, the list back and the field focused for the next.
    await unmatched.getByRole('button', { name: 'Atur Ulang' }).click();
    await expect(table).toContainText(number);
    await expect(field).toHaveValue('');
    await expect(field).toBeFocused();
    await expect(page).not.toHaveURL(/nomor=/);

    await field.fill(number);
    await page.getByRole('button', { name: 'Cari' }).click();
    await expect(table.locator('tbody tr')).toHaveCount(1);
    await expect(table).toContainText(number);
});

test('keeps a branch with no invoice its own sentence, a lookup there its MSG-25, the field bounded to 100 characters (IP-17; PT-19; TASK-096)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await invoiceRun(page, `invoice-kosong-${String(Date.now())}`);

    await page.goto(`/keuangan/invoice?perusahaan=${run.company}`);
    await expect(page.locator('[data-region-state="empty"]')).toContainText('Belum ada invoice di cabang ini.');
    await expect(page.locator('[data-region-state="no-match"]')).toHaveCount(0);

    const field = page.getByLabel(/^Cari nomor invoice/);
    await field.pressSequentially('A'.repeat(101));
    await expect(field).toHaveValue('A'.repeat(100));
    await field.fill('UJI/INV/0000');
    await page.getByRole('button', { name: 'Cari' }).click();
    await expect(page.locator('[data-region-state="no-match"]')).toContainText('Kode UJI/INV/0000 tidak dikenal.');
    await expect(page.locator('[data-region-state="empty"]')).toHaveCount(0);
});

test('answers a lookup sent while "Muat Lagi" is in flight alone, the page it asked for never joining it (IP-18; TASK-096)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await invoiceRun(page, `invoice-balapan-${String(Date.now())}`, { stage: 'issued', drafts: 51 });
    const number = run.invoice_number ?? '';
    expect(number).toMatch(/^UJI\/INV\//);
    // "Muat Lagi"'s page held back, so the lookup's answer lands first.
    await page.route((url) => url.pathname === '/keuangan/invoice' && url.searchParams.has('setelah'), async (route) => {
        await new Promise((resolve) => { setTimeout(resolve, 10_000); });
        await route.continue().catch(() => undefined);
    });

    await page.goto(`/keuangan/invoice?perusahaan=${run.company}`);
    const rows = page.getByRole('table', { name: 'Invoice', exact: true }).locator('tbody tr');
    await expect(rows).toHaveCount(50);
    await page.getByRole('button', { name: 'Muat Lagi' }).click();
    await page.getByLabel(/^Cari nomor invoice/).fill(number);
    await page.getByRole('button', { name: 'Cari' }).click();
    await expect(rows).toHaveCount(1);
    await expect(rows.first()).toContainText(number);

    // Past the held page's release, still the lookup's one row.
    await page.waitForTimeout(12_000);
    await expect(rows).toHaveCount(1);
    await expect(rows.first()).toContainText(number);
});

test('reopens a draft with the tax it stores, stated through a save of the same value, proposed anew for another (DATABASE §12; FS-13)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await invoiceRun(page, `invoice-pajak-${String(Date.now())}`, { tax: true });

    await page.goto(`/keuangan/invoice/baru?proyek=${run.project}`);
    await page.getByLabel(/^Keterangan/).fill('Termin pertama');
    await page.getByRole('textbox', { name: /^Nilai/ }).fill('1000000');
    const tax = page.getByRole('textbox', { name: /^Pajak/ });
    await expect(tax).toHaveValue('Rp110.000');
    await tax.fill('109000');
    await page.getByRole('button', { name: 'Simpan Draf' }).click();
    await expect(page.locator('#konten').getByText('Draf invoice tersimpan.')).toBeVisible();

    await page.reload();
    await expect(tax).toHaveValue('Rp109.000');
    await expect(page.getByRole('region', { name: /^Pratinjau Invoice/ })).toContainText('Rp109.000');

    await page.getByLabel(/^Keterangan/).fill('Termin pertama, diperbaiki');
    await page.getByRole('button', { name: 'Simpan Draf' }).click();
    await expect(page.locator('#konten').getByText('Draf invoice tersimpan.')).toBeVisible();
    await page.reload();
    await expect(tax).toHaveValue('Rp109.000');

    await page.getByRole('textbox', { name: /^Nilai/ }).fill('2000000');
    await expect(tax).toHaveValue('Rp220.000');
});

test('shows a saved draft as it will issue after the project moved on, an edit reading the current price (WF-FIN-01; architecture challenge 15a)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await invoiceRun(page, `invoice-pin-${String(Date.now())}`, { stage: 'moved' });

    await page.goto(`/keuangan/invoice/baru?invoice=${run.invoice ?? ''}`);
    const paper = page.getByRole('region', { name: /^Pratinjau Invoice/ });
    await expect(paper).toContainText('Rp250.000');
    await expect(paper).toContainText('Rp1.000.000');
    await expect(page.locator('#konten')).toContainText('Baris proyek berubah sejak draf ini disimpan.');
    await expect(page.getByRole('button', { name: 'Terbitkan Invoice' })).toBeEnabled();

    await page.getByTestId('invoice-line').getByRole('textbox').fill('5');
    await expect(paper).toContainText('Rp300.000');
    await expect(paper).toContainText('Rp1.500.000');
    await expect(page.getByRole('button', { name: 'Terbitkan Invoice' })).toBeDisabled();
});


test('opens the business date\'s calendar above the issue dialog and backdates the issue, its number\'s period named (PT-25; IP-07; UXS-14; TASK-091)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await invoiceRun(page, `invoice-mundur-${String(Date.now())}`);

    await page.goto(`/keuangan/invoice/baru?proyek=${run.project}`);
    await expect(page.getByRole('heading', { name: new RegExp(`Invoice baru — ${run.number}`) })).toBeVisible();
    await page.getByLabel(/^Keterangan/).fill('Termin mundur');
    await page.getByRole('textbox', { name: /^Nilai/ }).fill('1000000');
    await page.getByRole('button', { name: 'Simpan Draf' }).click();
    await expect(page.locator('#konten').getByText('Draf invoice tersimpan.')).toBeVisible();
    await page.getByRole('button', { name: 'Terbitkan Invoice' }).click();
    const dialog = page.getByRole('alertdialog');
    await expect(dialog).not.toContainText('Tanggal Mundur');

    // The calendar opened from inside the dialog sits above it: its month control and its day take the press (DESIGN_SYSTEM §5).
    const today = new Date(Date.now() + 7 * 3_600_000).toISOString().slice(0, 10);
    const [year = 0, month = 1] = today.split('-').map(Number);
    const day = new Date(Date.UTC(year, month - 2, 20)).toISOString().slice(0, 10);
    await dialog.getByRole('button', { name: 'Ubah' }).click();
    const previous = page.getByRole('button', { name: 'Bulan sebelumnya' });
    await expect(previous).toBeInViewport({ ratio: 1 });
    await previous.click();
    await page.locator(`[data-day="${day}"] button`).click();

    await expect(dialog).toContainText('Tanggal Mundur');

    // UXS-14 (TASK-090): the invoice fixture numbers yearly from 1 January — the period is the day's year, none before it.
    if (day.slice(0, 4) === today.slice(0, 4)) {
        await expect(dialog).toContainText(`Nomor mengikuti periode ${day.slice(0, 4)}`);
    } else {
        await expect(dialog).not.toContainText('Nomor mengikuti periode');
    }

    await dialog.getByRole('button', { name: 'Kembali' }).click();
    await expect(dialog).toHaveCount(0);
});

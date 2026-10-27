import { randomBytes } from 'node:crypto';
import { crc32, deflateSync } from 'node:zlib';

import { expect, test } from '@playwright/test';

import { signedIn, stepUp } from '../account-fixtures';

// TL-09 (TASK-025; J-MD-01; CJ-19; criteria 2–4, 8, 9): the Owner's company master and tax configuration against the
// real routes — a company created, its legal data saved, a bank account added behind the step-up and changed by a new
// one with the old one deactivated, its logo set from an upload, its readiness list following each step —; then on
// PG-062 a tax type, its rate version, and an overlapping one refused at its field.

/** A 4×4 PNG of one random colour, so no run's upload repeats another's. */
function logo(): Buffer {
    const chunk = (type: string, data: Buffer) => {
        const length = Buffer.alloc(4);
        length.writeUInt32BE(data.length);
        const body = Buffer.concat([Buffer.from(type, 'ascii'), data]);
        const crc = Buffer.alloc(4);
        crc.writeUInt32BE(crc32(body));

        return Buffer.concat([length, body, crc]);
    };
    const header = Buffer.alloc(13);
    header.writeUInt32BE(4, 0);
    header.writeUInt32BE(4, 4);
    header.writeUInt8(8, 8);
    header.writeUInt8(2, 9);
    const colour = randomBytes(3);
    const row = Buffer.concat([Buffer.from([0]), colour, colour, colour, colour]);

    return Buffer.concat([Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]), chunk('IHDR', header), chunk('IDAT', deflateSync(Buffer.concat([row, row, row, row]))), chunk('IEND', Buffer.alloc(0))]);
}

test('creates a company and sets it up section by section, its readiness following (J-MD-01; CJ-19)', async ({ page }) => {
    const owner = await signedIn(page, 'perusahaan', { owner: true });
    const code = `U${randomBytes(3).toString('hex').toUpperCase()}`;
    await page.goto('/pengaturan/perusahaan');
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Perusahaan');

    await page.getByRole('button', { name: 'Tambah Perusahaan' }).first().click();
    const create = page.getByRole('dialog', { name: 'Tambah Perusahaan' });
    await create.getByLabel(/^Kode/).fill(code);
    await create.getByLabel(/^Nama badan usaha/).fill(`CV Uji ${code}`);
    await create.getByRole('button', { name: 'Tambah Perusahaan' }).click();
    await expect(page.getByRole('heading', { level: 1 })).toHaveText(`CV Uji ${code}`);
    const readiness = page.getByRole('list', { name: 'Kesiapan Perusahaan' });
    await expect(readiness.getByRole('listitem').filter({ hasText: 'Rekening bank' })).toContainText('Belum');

    await page.getByLabel(/^Bentuk badan usaha/).fill('CV');
    await page.getByRole('button', { name: 'Simpan', exact: true }).click();
    await expect(page.getByText('Data perusahaan disimpan.')).toBeVisible();

    await page.getByRole('tab', { name: 'Rekening Bank' }).click();
    await page.getByRole('button', { name: 'Tambah Rekening' }).click();
    await page.getByLabel(/^Nama bank/).fill('Bank Uji');
    await page.getByLabel(/^Nomor rekening/).fill('1234567890');
    await page.getByRole('button', { name: 'Tambah Rekening' }).click();
    await stepUp(page, owner.password);
    await expect(page.getByText('Rekening ditambahkan.')).toBeVisible();
    await expect(readiness.getByRole('listitem').filter({ hasText: 'Rekening bank' })).toContainText('Lengkap');

    await page.getByRole('button', { name: 'Ganti Rekening' }).click();
    await page.getByLabel(/^Nomor rekening/).fill('5555666677');
    await page.getByRole('button', { name: 'Ganti Rekening' }).last().click();
    // The old account's deactivation is confirmed first, with its reason (IP-08).
    const confirm = page.getByRole('alertdialog', { name: /^Ganti Rekening/ });
    await confirm.getByRole('textbox').fill('Pindah ke rekening baru');
    await confirm.getByRole('button', { name: 'Ganti Rekening' }).click();
    await expect(page.getByText('Rekening diganti; rekening lama dinonaktifkan.')).toBeVisible();
    await expect(page.getByRole('listitem').filter({ hasText: '1234567890' })).toContainText('Nonaktif');

    await page.getByRole('tab', { name: 'Aset Identitas' }).click();
    const logoSection = page.locator('section').filter({ has: page.getByRole('heading', { name: 'Logo' }) });
    await logoSection.getByLabel('Berkas baru').setInputFiles({ name: 'logo.png', mimeType: 'image/png', buffer: logo() });
    await expect(logoSection.getByText('logo.png')).toBeVisible();
    await logoSection.getByRole('button', { name: 'Pasang Berkas' }).click();
    await expect(page.getByText('Aset identitas dipasang.')).toBeVisible();
});

test('configures a tax type and its rate, refusing an overlapping one at its field (criterion 8; C-52)', async ({ page }) => {
    await signedIn(page, 'pajak', { owner: true });
    const code = `T${randomBytes(3).toString('hex').toUpperCase()}`;
    await page.goto('/pengaturan/pajak');
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Pajak');

    await page.getByRole('button', { name: 'Tambah Jenis Pajak' }).click();
    await page.getByLabel(/^Kode/).fill(code);
    await page.getByLabel(/^Nama/).fill(`Pajak ${code}`);
    await page.getByLabel(/^Kategori/).selectOption({ label: 'Pajak keluaran' });
    await page.getByRole('button', { name: 'Simpan', exact: true }).click();
    await expect(page.getByText('Pengaturan pajak disimpan.')).toBeVisible();

    for (const [from, refused] of [['2025-01-01', false], ['2025-06-01', true]] as const) {
        await page.getByRole('tab', { name: 'Tarif' }).click();
        await page.getByRole('button', { name: 'Tambah Tarif' }).click();
        await page.getByLabel(/^Jenis pajak/).selectOption({ label: `${code} · Pajak ${code}` });
        await page.getByLabel(/^Tarif \(%\)/).fill('12');
        await page.getByLabel(/^Berlaku mulai/).fill(from);
        await page.getByRole('button', { name: 'Simpan', exact: true }).click();
        await expect(refused ? page.getByText('Rentang tanggal ini bertumpang tindih dengan pengaturan lain yang sama.') : page.getByText('Pengaturan pajak disimpan.')).toBeVisible();
    }
});

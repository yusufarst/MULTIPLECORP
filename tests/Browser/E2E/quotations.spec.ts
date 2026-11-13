import { expect, test } from '@playwright/test';

import { quotationRun } from '../quotation-fixtures';

// TL-09 (TASK-033; J-QUO-01; J-PRJ-02's approval; CJ-01's quotation and approval steps; CJ-13; criteria 1, 3, 4, 5): the
// draft created from the project's lines on PG-014, its validity and price entered beside the live paper and saved,
// "Terbitkan Penawaran" after its confirmation giving the number, "Catat Jawaban Klien" recording the approval and
// landing on PG-011; the revision on PG-037; and on the phone the sent quotation's quick action recording a rejection,
// then "Buat Revisi". No control voids a quotation or implies a reservation or a payment.

test('drafts, issues and approves a quotation from PG-014, landing on PG-011 (J-QUO-01; J-PRJ-02; CJ-01; criteria 1, 3)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await quotationRun(page, `penawaran-${String(Date.now())}`);
    const content = page.locator('#konten');

    await page.goto(`/proyek/${run.project}/penawaran`);
    await expect(page.getByRole('heading', { level: 1 })).toHaveText(`Penawaran ${run.number}`);
    await page.getByRole('button', { name: 'Buat Draf dari Baris Proyek' }).click();
    await expect(page.getByText('Draf penawaran dibuat dari baris proyek.')).toBeVisible();

    await page.getByLabel(/^Berlaku sampai/).fill('31/12/' + String(new Date().getFullYear() + 1));
    await page.getByRole('textbox', { name: /^Harga satuan/ }).first().fill('20000');
    await expect(page.getByRole('region', { name: /Pratinjau/ })).toContainText('Penawaran Harga');
    await expect(page.getByRole('region', { name: /Pratinjau/ })).toContainText('Rp20.000');
    await page.getByRole('button', { name: 'Simpan Draf' }).click();
    await expect(page.getByText('Draf penawaran tersimpan.')).toBeVisible();

    await page.getByRole('button', { name: 'Terbitkan Penawaran' }).click();
    const confirm = page.getByRole('alertdialog');
    await expect(confirm).toContainText('Penawaran tidak memesan stok dan tidak mencatat uang.');
    await confirm.getByRole('button', { name: 'Terbitkan Penawaran' }).click();
    await expect(page.locator('#konten').getByText('Penawaran terbit. Berkasnya sedang disiapkan.')).toBeVisible();
    await expect(content).toContainText(/UJI\/PNW\/\d{4}\/0001/);
    await expect(content).not.toContainText(/Batalkan|Reservasi|Pembayaran/);

    await page.getByRole('button', { name: 'Catat Jawaban Klien' }).first().click();
    await page.getByRole('alertdialog').getByRole('button', { name: 'Catat Disetujui' }).click();
    await page.waitForURL(`**/proyek/${run.project}`);
    await expect(page.getByText('Persetujuan klien dicatat sebagai konfirmasi komersial.')).toBeVisible();

    await page.goto(`/dokumen/penawaran?perusahaan=${run.company}`);
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Daftar Penawaran');
    const row = page.getByRole('row').filter({ hasText: run.number });
    await expect(row).toContainText('Disetujui Klien');
    await expect(row).toContainText(/UJI\/PNW\/\d{4}\/0001/);
});

// TASK-094 (D7; IP-02; D-UX-02): the draft's inputs and lines frozen while its save is in flight or its outcome unknown —
// a lost answer, an unreached request —, "Coba Lagi" resending the same command_id, and editable again once the outcome is
// known: saved, or refused for its correction.
test('freezes the draft while a save is in flight or its outcome unknown, the retry under the same command_id, and frees it on a known outcome (IP-02; D-UX-02; TASK-094)', async ({ page }) => {
    test.setTimeout(180_000);
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await quotationRun(page, `beku-${String(Date.now())}`);
    const path = `/proyek/${run.project}/penawaran`;
    const saving = (url: URL) => url.pathname === path;
    const sent: string[] = [];
    page.on('request', (request) => {
        if (request.method() === 'POST' && saving(new URL(request.url()))) {
            sent.push((request.postDataJSON() as { command_id: string }).command_id);
        }
    });

    await page.goto(path);
    await expect(page.getByRole('heading', { level: 1 })).toHaveText(`Penawaran ${run.number}`);
    await page.getByRole('button', { name: 'Buat Draf dari Baris Proyek' }).click();
    await expect(page.getByText('Draf penawaran dibuat dari baris proyek.')).toBeVisible();
    // The draft's creation posted to the same address; only the saves below are counted.
    sent.length = 0;

    const draft = page.locator('[data-slot="quotation-draft"]');
    const valid = page.getByLabel(/^Berlaku sampai/);
    const price = page.getByRole('textbox', { name: /^Harga satuan/ }).first();
    const description = page.getByRole('textbox', { name: 'Keterangan' }).first();
    const save = page.getByRole('button', { name: 'Simpan Draf' });
    const unknown = page.getByText('Belum dapat dipastikan apakah tindakan ini tersimpan. Tekan Coba Lagi — sistem tidak akan mencatat dua kali.');
    const saved = page.getByText('Draf penawaran tersimpan.');
    const frozen = async () => {
        await expect(draft).toBeDisabled();
        for (const field of [valid, price, description]) await expect(field).toBeDisabled();
        await expect(page.getByRole('button', { name: /^Hapus baris/ }).first()).toBeDisabled();
        await expect(save).toBeDisabled();
    };
    const editable = async () => {
        await expect(draft).toBeEnabled();
        for (const field of [valid, price, description]) await expect(field).toBeEditable();
    };

    // A refusal is a known outcome — a validity already past, which the server refuses —: the draft stays editable for its
    // correction, the field marked.
    await price.fill('20000');
    await valid.fill('01/01/2020');
    await save.click();
    await expect(valid).toHaveAttribute('aria-invalid', 'true');
    await editable();

    // In flight: frozen until the answer arrives, then saved and editable.
    await valid.fill('31/12/' + String(new Date().getFullYear() + 1));
    const before = sent.length;
    let release = () => {};
    const held = new Promise<void>((resolve) => { release = resolve; });
    await page.route(saving, async (route) => {
        if (route.request().method() !== 'POST') {
            await route.fallback();

            return;
        }

        await held;
        await route.fallback();
    });
    await save.click();
    await expect.poll(() => sent.length).toBe(before + 1);
    const inFlight = sent.at(-1);
    await frozen();
    release();
    await expect(saved).toBeVisible();
    await page.unroute(saving);
    await editable();

    // A lost answer — the server recorded the save, its answer never came back —: unknown, frozen; "Coba Lagi" sends the same
    // command_id and the recorded outcome frees the draft.
    await description.fill(`Keterangan hilang ${run.number}`);
    await page.route(saving, async (route) => {
        if (route.request().method() !== 'POST') {
            await route.fallback();

            return;
        }

        await route.fetch();
        await route.abort('failed');
    });
    await save.click();
    await expect(unknown).toBeVisible();
    await frozen();
    const lost = sent.at(-1);
    await page.unroute(saving);
    await page.getByRole('button', { name: 'Coba Lagi' }).click();
    await expect(saved).toBeVisible();
    expect(sent.at(-1), 'the retry under the same command_id').toBe(lost);
    await editable();
    await expect(description).toHaveValue(`Keterangan hilang ${run.number}`);

    // An unreached request — the answer is unknown as well —: frozen, and the retry under its command_id saves it.
    await description.fill(`Keterangan tertunda ${run.number}`);
    await page.route(saving, async (route) => {
        if (route.request().method() !== 'POST') {
            await route.fallback();

            return;
        }

        await route.abort('failed');
    });
    await save.click();
    await expect(unknown).toBeVisible();
    await frozen();
    const unreached = sent.at(-1);
    await page.unroute(saving);
    await page.getByRole('button', { name: 'Coba Lagi' }).click();
    await expect(saved).toBeVisible();
    expect(sent.at(-1), 'the retry under the same command_id').toBe(unreached);
    expect(new Set([inFlight, lost, unreached]).size, 'one command_id per intent: the held save, the lost one and the unreached one').toBe(3);
    await editable();
    await expect(description).toHaveValue(`Keterangan tertunda ${run.number}`);
});

test('records the client\'s rejection through the phone\'s quick action, then creates a revision (J-QUO-01; criterion 4)', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    const run = await quotationRun(page, `penawaran-hp-${String(Date.now())}`, { stage: 'sent' });

    await page.goto(`/proyek/${run.project}/penawaran`);
    await expect(page.getByRole('heading', { level: 1 })).toHaveText(`Penawaran ${run.number}`);
    await page.getByRole('button', { name: 'Catat Jawaban Klien' }).first().click();
    const dialog = page.getByRole('alertdialog');
    await dialog.getByRole('radio', { name: 'Klien menolak' }).check();
    await dialog.getByLabel(/^Catatan/).fill('Anggaran belum tersedia');
    await dialog.getByRole('button', { name: 'Catat Ditolak' }).click();
    await expect(page.locator('#konten').getByText('Penolakan klien dicatat.')).toBeVisible();
    await expect(page.locator('#konten')).toContainText('Ditolak Klien');

    await page.getByRole('button', { name: 'Buat Revisi' }).first().click();
    await expect(page.getByText('Revisi baru dibuat sebagai draf.')).toBeVisible();
    await expect(page.getByRole('list', { name: 'Riwayat revisi' }).getByRole('listitem')).toHaveCount(2);
});

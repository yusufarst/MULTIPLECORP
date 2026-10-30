import { expect, test } from '@playwright/test';

import { handoverRun } from '../handover-fixtures';

// TL-09 (TASK-047; J-FUL-04; CJ-03's service handover; CJ-15's BAST from PG-033; criteria 1–6): at the desk from the journey's *Jasa*
// stage — the handover's form beside the BAST's live preview, "Catat Serah Terima" answering on PG-011 —; the excess refused with
// the remainder and the input kept; a likely duplicate refused with its match until confirmed with a reason; "Terbitkan BAST" issued
// in place with its number; "Balikkan" with its reason. On the phone at the client's site: Edit → Preview → Finalize.

test('records a handover from the journey at the desk, the live preview beside the form, then answers on PG-011 (J-FUL-04; NAV-033; D-DS-23)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await handoverRun(page, `serah-${String(Date.now())}`);

    await page.goto(`/proyek/${run.project}`);
    await page.getByRole('link', { name: 'Catat serah terima jasa' }).first().click();
    await expect(page).toHaveURL(new RegExp(`/proyek/${run.project}/serah-terima$`));
    await expect(page.getByRole('heading', { name: new RegExp(`Serah terima jasa — ${run.number}`) })).toBeVisible();

    const paper = page.getByRole('region', { name: /^Pratinjau BAST/ });
    await expect(paper).toContainText('Berita Acara Serah Terima');
    await expect(paper).toContainText('DRAF — belum terbit');
    await page.getByLabel(/^Penerima/).fill('Bu Sari');
    await page.getByLabel(/^Jumlah diserahkan/).fill('4');
    await page.getByLabel(/^Pekerjaan yang dilakukan/).fill('Instalasi ruang laboratorium');
    await expect(paper).toContainText('Bu Sari');
    await expect(paper).toContainText('Instalasi ruang laboratorium');

    await page.getByRole('button', { name: 'Catat Serah Terima' }).click();
    await expect(page).toHaveURL(new RegExp(`/proyek/${run.project}$`));
    await expect(page.locator('#konten').getByText('Serah terima jasa tercatat.')).toBeVisible();
});

test('refuses the excess with the remainder and keeps the input (C-30; PX-03; NV-04; criterion 2)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await handoverRun(page, `serah-sisa-${String(Date.now())}`, { recorded: true });

    await page.goto(`/proyek/${run.project}/serah-terima`);
    await page.getByLabel(/^Penerima/).fill('Bu Sari');
    await page.getByLabel(/^Jumlah diserahkan/).fill('7');
    await page.getByLabel(/^Pekerjaan yang dilakukan/).fill('Tahap dua');
    await page.getByRole('button', { name: 'Catat Serah Terima' }).click();

    await expect(page.locator('#konten').getByText(/melebihi sisa\. Sisanya 6/)).toBeVisible();
    await expect(page.getByLabel(/^Penerima/)).toHaveValue('Bu Sari');
    await expect(page.getByLabel(/^Pekerjaan yang dilakukan/)).toHaveValue('Tahap dua');
});

test('refuses a likely duplicate with its match until confirmed with a reason (L-49; INT-015; MSG-15; criterion 6)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await handoverRun(page, `serah-ganda-${String(Date.now())}`, { recorded: true });

    await page.goto(`/proyek/${run.project}/serah-terima`);
    await page.getByLabel(/^Penerima/).fill('Bu Sari');
    await page.getByLabel(/^Jumlah diserahkan/).fill('4');
    await page.getByLabel(/^Pekerjaan yang dilakukan/).fill('Tahap dua');
    await page.getByRole('button', { name: 'Catat Serah Terima' }).click();

    const warning = page.locator('[data-slot="duplicate-warning"]');
    await expect(warning).toContainText('Kemungkinan catatan ganda');
    await warning.getByRole('checkbox').check();
    await warning.getByRole('textbox').fill('Ini tahap kedua yang berbeda');
    await page.getByRole('button', { name: 'Catat Serah Terima' }).click();
    await expect(page).toHaveURL(new RegExp(`/proyek/${run.project}$`));
    await expect(page.locator('#konten').getByText('Serah terima jasa tercatat.')).toBeVisible();
});

test('issues the BAST in place with its number, then reverses the line with its reason and flags the BAST (CJ-15; INT-222; INT-209; CM-27; criteria 4, 5)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await handoverRun(page, `serah-bast-${String(Date.now())}`, { recorded: true });
    const handover = page.getByTestId('handover');

    await page.goto(`/proyek/${run.project}/serah-terima`);
    await expect(handover).toContainText('Belum ada BAST.');
    await handover.getByRole('button', { name: 'Terbitkan BAST' }).click();
    const issue = page.getByRole('alertdialog');
    await expect(issue).toContainText('Terbitkan BAST?');
    await issue.getByRole('button', { name: 'Terbitkan BAST' }).click();
    await expect(handover).toContainText(/BAST UJI\/BAST\//);
    await expect(page).toHaveURL(new RegExp(`/proyek/${run.project}/serah-terima$`));
    await expect(handover.getByRole('button', { name: 'Terbitkan BAST' })).toHaveCount(0);

    await handover.getByRole('button', { name: 'Balikkan' }).click();
    const reverse = page.getByRole('alertdialog');
    await expect(reverse).toContainText('Balikkan baris 1?');
    await reverse.getByRole('textbox').fill('Salah catat jumlah');
    await reverse.getByRole('button', { name: 'Balikkan' }).click();
    await expect(page.locator('#konten').getByText('Baris pembalik tercatat.')).toBeVisible();
    await expect(handover).toContainText('Salah catat jumlah');
    await expect(handover).toContainText('Revisi atau batalkan BAST-nya.');
    await expect(handover.getByRole('button', { name: 'Balikkan' })).toHaveCount(0);
});

test('records a handover on the phone through Edit, Preview and Finalize, the preview full-screen (DC-11; J-FUL-04; criterion 3)', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    const run = await handoverRun(page, `serah-hp-${String(Date.now())}`);

    await page.goto(`/proyek/${run.project}/serah-terima`);
    await expect(page.locator('[data-slot="document-composer"]')).toHaveAttribute('data-band', 'phone');
    await page.getByLabel(/^Penerima/).fill('Pak Budi');
    await page.getByLabel(/^Jumlah diserahkan/).fill('10');
    await page.getByLabel(/^Pekerjaan yang dilakukan/).fill('Instalasi selesai seluruhnya');
    await page.getByRole('button', { name: 'Pratinjau', exact: true }).click();
    const preview = page.getByRole('region', { name: /^Pratinjau BAST/ });
    await expect(preview).toContainText('Pak Budi');
    await expect(preview).toContainText('DRAF — belum terbit');
    await page.getByRole('button', { name: 'Lanjut' }).click();
    await page.getByRole('button', { name: 'Catat Serah Terima' }).click();
    await expect(page).toHaveURL(new RegExp(`/proyek/${run.project}$`));
    await expect(page.locator('#konten').getByText('Serah terima jasa tercatat.')).toBeVisible();
});

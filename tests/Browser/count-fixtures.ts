import { expect, type Page } from '@playwright/test';

// TASK-043's browser helpers over TASK-042's fixture: a count opened from the page for the fixture's rack, its lines entered as the phone does.

export async function openCount(page: Page, product: string, rack: string): Promise<void> {
    await page.goto(`/gudang/stok-opname?produk=${product}`);
    const form = page.locator('[data-slot="count-open"]');
    const rackField = form.getByLabel('Rak');
    await rackField.selectOption({ value: (await rackField.locator('option', { hasText: rack }).getAttribute('value')) ?? '' });
    await form.getByRole('button', { name: 'Mulai Hitungan' }).click();
    await expect(page.locator('[data-slot="count-detail"]')).toBeVisible();
}

export async function countLine(page: Page, quantity: string): Promise<void> {
    const form = page.locator('[data-slot="count-line"]');
    await form.getByLabel('Jumlah dihitung').fill(quantity);
    await form.getByRole('button', { name: 'Catat Baris' }).click();
    await expect(page.locator('[data-slot="count-line-row"]').first()).toBeVisible();
    await expect(form).toContainText('Baris dicatat');
}

export async function applyWith(page: Page, reason: string): Promise<void> {
    const apply = page.locator('[data-slot="count-apply"]');
    const proof = apply.getByLabel('Bukti', { exact: true });

    if ((await proof.count()) > 0 && (await proof.inputValue()) === '') {
        await proof.selectOption({ index: 1 });
    }

    await apply.getByRole('button', { name: 'Terapkan Selisih' }).click();
    const dialog = page.getByRole('alertdialog');
    await dialog.getByLabel('Alasan').fill(reason);
    await dialog.getByRole('button', { name: 'Terapkan Selisih' }).click();
}

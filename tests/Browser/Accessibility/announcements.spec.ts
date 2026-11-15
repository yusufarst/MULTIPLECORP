import { expect, test } from '@playwright/test';

// TL-12 (TASK-066; DESIGN_SYSTEM §15, §12.2; WCAG 4.1.3; criterion 8): a command's outcome announced through the action
// bar's live regions, both present from the page's load — COMMITTED and PENDING in the polite one, REJECTED, CONFLICT and
// FAILED in the assertive one —, each outcome chosen by the keyboard on the design system's gallery, whose action bar is
// the component every page's form and record use.

const POLITE = ['Tersimpan', 'Sedang Diproses'];
const ASSERTIVE = ['Tidak Dapat Diproses', 'Data Sudah Berubah', 'Sudah Tercatat', 'Belum Dapat Dipastikan', 'Belum Diproses'];

test('announces each outcome in the live region its kind takes, both regions present from load (4.1.3)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    await page.goto('/__fixtures/komponen');
    const bar = page.getByRole('region', { name: 'Catat Pembayaran' }).last();
    const polite = bar.locator('[aria-live="polite"]');
    const assertive = bar.locator('[aria-live="assertive"]');

    // Present and empty before any outcome: a region added with its message is not announced.
    await expect(polite).toBeAttached();
    await expect(assertive).toBeAttached();
    await expect(polite).toHaveText('');
    await expect(assertive).toHaveText('');

    const choices = page.getByRole('region', { name: 'PT-14' });

    for (const label of [...POLITE, ...ASSERTIVE]) {
        const choice = choices.getByRole('button', { name: label, exact: true });
        await choice.focus();
        await page.keyboard.press('Enter');
        const [into, other] = POLITE.includes(label) ? [polite, assertive] : [assertive, polite];
        await expect(into, `${label}: in its region`).not.toHaveText('');
        await expect(other, `${label}: not in the other`).toHaveText('');
    }
});

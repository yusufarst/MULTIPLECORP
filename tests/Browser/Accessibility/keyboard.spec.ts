import { expect, type Locator, type Page, test } from '@playwright/test';

import { type Account, freshStep, landed, totp } from '../account-fixtures';

// TL-12 (TASK-066; DESIGN_SYSTEM §13, §15; WCAG 2.1.1, 2.1.2, 2.1.4, 2.2.1, 2.4.3, 3.3.8; criteria 7 and 8): the interaction
// patterns the critical journeys are made of, operated by the keyboard alone — sign-in with its second step and the
// password manager's fields, IP-08's confirmation opened, dismissed with Escape and its focus returned, then filled and
// confirmed, the account menu's language switch, the idle warning extended more than once — and no single printable key
// acting as a shortcut. UXS-41's quotation of many lines by keyboard is CJ-01's (`tests/Browser/E2E/journey-cj01.spec.ts`);
// the scans are keyboard-wedge input in CJ-07 and the scan specifications; every page's focus walk — no trap, no positive
// tab index — is the accessibility audit's (`tests/Browser/a11y-gate.ts`).

test.describe.configure({ timeout: 180_000 });

let runs = 0;

async function owner(page: Page, prefix: string): Promise<Account> {
    const response = await page.request.post('/__fixtures/akun', { data: { run: `${prefix}-${String(Date.now())}-${String((runs += 1))}`, kind: 'owner', totp: true, locale: 'id' } });
    expect(response.ok()).toBe(true);

    return (await response.json()) as Account;
}

/** Tab, as a keyboard alone does, until `target` holds the focus, within `most` presses. */
async function tabTo(page: Page, target: Locator, most = 40): Promise<void> {
    for (let press = 0; press < most; press += 1) {
        if (await target.evaluate((element) => element === document.activeElement)) {
            return;
        }

        await page.keyboard.press('Tab');
    }

    await expect(target).toBeFocused();
}

/** PG-001 and PG-002 by keyboard: the fields reached by Tab, the password inserted as a manager or a paste would, Enter. */
async function signInByKeyboard(page: Page, account: Account): Promise<void> {
    await page.goto('/masuk');
    const email = page.getByLabel('E-mail');
    const password = page.getByLabel('Kata sandi', { exact: true });
    // 1.3.5 and 3.3.8: the fields state their purpose, so a password manager fills them and nothing has to be recalled.
    await expect(email).toHaveAttribute('autocomplete', 'username');
    await expect(password).toHaveAttribute('autocomplete', 'current-password');
    await tabTo(page, email);
    await page.keyboard.type(account.email);
    await tabTo(page, password, 3);
    await page.keyboard.insertText(account.password);
    await page.keyboard.press('Enter');
    await page.waitForURL('**/masuk/verifikasi');
    await freshStep();
    const code = page.getByLabel('Kode 6 digit dari aplikasi autentikator');
    await expect(code).toHaveAttribute('autocomplete', 'one-time-code');
    await tabTo(page, code);
    await page.keyboard.type(totp(account.secret ?? ''));
    await page.keyboard.press('Enter');
    await landed(page);
}

test('signs in with its second step by the keyboard alone, the fields stating their purpose (2.1.1; 1.3.5; 3.3.8)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    await signInByKeyboard(page, await owner(page, 'kbd-masuk'));
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Beranda');
});

test('acts on no single printable key, focus left where it was (2.1.4; DESIGN_SYSTEM §13; UXI-42)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    await signInByKeyboard(page, await owner(page, 'kbd-kunci'));
    const at = page.url();
    await page.evaluate(() => {
        (document.activeElement as HTMLElement | null)?.blur();
    });

    for (const key of ['k', 'n', 'b', 'g', 'c', '/', '?', '1', 'Shift+N']) {
        await page.keyboard.press(key);
        expect(page.url(), `"${key}" moves nowhere`).toBe(at);
        await expect(page.getByRole('dialog').or(page.getByRole('alertdialog')).or(page.getByRole('menu')), `"${key}" opens nothing`).toHaveCount(0);
        expect(await page.evaluate(() => document.activeElement === document.body), `"${key}" takes no focus`).toBe(true);
    }
});

test('switches the display language from the account menu by the keyboard alone (2.1.1; INT-002)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    await signInByKeyboard(page, await owner(page, 'kbd-bahasa'));
    await tabTo(page, page.getByRole('button', { name: /Menu akun/ }));
    await page.keyboard.press('Enter');
    const menu = page.getByRole('menu');
    await expect(menu).toBeVisible();
    expect(await menu.evaluate((element) => element.contains(document.activeElement)), 'the focus moves into the open menu').toBe(true);
    const english = menu.getByRole('menuitemcheckbox', { name: 'English' });

    for (let press = 0; press < 12 && !(await english.evaluate((element) => element === document.activeElement)); press += 1) {
        await page.keyboard.press('ArrowDown');
    }

    await expect(english).toBeFocused();
    await page.keyboard.press('Enter');
    await expect(page.locator('html')).toHaveAttribute('lang', 'en');
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Home');
});

test('opens IP-08\'s confirmation, closes it with Escape to its trigger, then fills and confirms it by the keyboard alone (2.1.1; 2.1.2; 2.4.3; IP-08)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    await page.goto('/__fixtures/komponen');
    const trigger = page.getByRole('button', { name: 'Catat Pembayaran', exact: true }).first();
    await tabTo(page, trigger, 60);
    await page.keyboard.press('Enter');
    const dialog = page.getByRole('alertdialog', { name: 'Catat Pembayaran?' }).or(page.getByRole('dialog', { name: 'Catat Pembayaran?' }));
    await expect(dialog).toBeVisible();
    expect(await dialog.evaluate((element) => element.contains(document.activeElement)), 'the focus moves into the dialog').toBe(true);

    // Escape closes it and the focus returns to what opened it (§15).
    await page.keyboard.press('Escape');
    await expect(dialog).toBeHidden();
    await expect(trigger).toBeFocused();

    // Opened again: the reason typed, the focus kept inside the dialog's loop, the command confirmed — COMMITTED announced.
    await page.keyboard.press('Enter');
    await expect(dialog).toBeVisible();
    const reason = dialog.getByLabel('Alasan');
    await tabTo(page, reason, 10);
    await page.keyboard.type('Termin kedua');
    // The reason given, the confirming button is enabled — the loop's last control (IP-08).
    await expect(dialog.getByRole('button', { name: 'Catat Pembayaran', exact: true })).toBeEnabled();

    // The dialog's focus loop: each Tab settles inside it — Base UI's hidden focus guards, which hand the focus back to the
    // dialog's first or last control, are its own and never a resting place.
    for (let press = 0; press < 12; press += 1) {
        await page.keyboard.press('Tab');
        await expect
            .poll(() => dialog.evaluate((element) => element.contains(document.activeElement) && !(document.activeElement?.hasAttribute('data-base-ui-focus-guard') ?? false)), { message: 'Tab stays inside the open dialog', timeout: 2_000 })
            .toBe(true);
    }

    await tabTo(page, dialog.getByRole('button', { name: 'Catat Pembayaran', exact: true }), 12);
    await page.keyboard.press('Enter');
    // The answer appears in the dialog's own action region (D-DS-06), announced politely; Escape then closes it to its trigger.
    await expect(dialog.locator('[aria-live="polite"]')).toContainText('Pembayaran tersimpan.');
    await page.keyboard.press('Escape');
    await expect(dialog).toBeHidden();
    await expect(trigger).toBeFocused();
});

test('extends the session from the idle warning more than once by the keyboard alone (2.2.1; IP-11; MSG-33)', async ({ page }) => {
    await page.clock.install();
    await page.setViewportSize({ width: 1280, height: 900 });
    await page.goto(`/__fixtures/shell?persona=owner&run=kbd-${String(Date.now())}`);
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
    const warning = page.getByRole('alertdialog', { name: 'Sesi Akan Berakhir' });
    const stay = warning.getByRole('button', { name: 'Tetap Masuk' });

    for (const time of ['first', 'second', 'third']) {
        await page.clock.fastForward('55:30');
        await expect(warning, `the ${time} warning`).toBeVisible();
        // The warning opens on "Tetap Masuk": Enter alone extends the session.
        await expect(stay).toBeFocused();
        await page.keyboard.press('Enter');
        await expect(warning, `the ${time} extension`).toBeHidden();
    }
});

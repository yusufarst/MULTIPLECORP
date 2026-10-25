import { expect, type Page, test } from '@playwright/test';

// TL-09 (TASK-016; acceptance criteria 2, 5, 7, 8): the shell's paths on desktop and phone on the local stack's
// shell fixture (routes/fixtures.php) — each persona's entries, *Menu lengkap*, "Buat" and the account menu, the
// rail, the skip link and the *Pintasan* help, the phone's bottom bar and *Lainnya* —; the search type-ahead with
// the term only in the request body; and the re-authentication in place and the step-up against a stub of the
// sign-in, the unsent input kept and the pending `command_id` replayed rather than run twice.

const VISIT = 15_000;
let runs = 0;

async function open(page: Page, persona: 'owner' | 'admin' | 'gudang', width = 1280): Promise<string> {
    const run = `r${String(Date.now())}-${String((runs += 1))}`;
    await page.setViewportSize({ width, height: 900 });
    await page.goto(`/__fixtures/shell?persona=${persona}&run=${run}`);
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();

    return run;
}

test('shows the Owner the primary entries, *Menu lengkap* and every "Buat" item (INT-003, INT-004)', async ({ page }) => {
    await open(page, 'owner');
    const nav = page.getByRole('navigation', { name: 'Navigasi utama' }).first();

    await expect(nav.getByRole('link')).toHaveText(['Beranda', 'Proyek', 'Pekerjaan', 'Tinjauan']);
    await nav.getByRole('button', { name: 'Menu lengkap' }).click();
    await expect(nav.getByRole('link', { name: 'Keuangan' })).toHaveAttribute('href', '/keuangan/invoice');
    await expect(nav.locator('[aria-current]')).toHaveCount(0);
    await expect(nav.getByRole('link', { name: 'Aktivitas & Keamanan' })).toBeVisible();

    await page.getByRole('button', { name: 'Buat', exact: true }).click();
    await expect(page.getByRole('menuitem')).toHaveText(['Proyek', 'Pembelian', 'Barang Masuk', 'Pembayaran', 'Unggah Dokumen']);
    await page.keyboard.press('Escape');
    // The closed menu leaves only after its exit motion, which WebKit still runs when the next one opens.
    await expect(page.getByRole('menu')).toHaveCount(0);

    await page.getByRole('button', { name: /Menu akun/ }).click();
    await expect(page.getByRole('menu')).toContainText('Laras');
    await expect(page.getByRole('menu')).toContainText('Owner');
    await expect(page.getByRole('menuitem', { name: 'Keluar' })).toBeVisible();
});

test('gives an Admin only what the account\'s capabilities open — no Owner entry, nothing disabled (NV-08; criterion 5)', async ({ page }) => {
    await open(page, 'admin');
    const nav = page.getByRole('navigation', { name: 'Navigasi utama' }).first();

    await expect(nav.getByRole('link')).toHaveText(['Pekerjaan', 'Proyek']);
    await nav.getByRole('button', { name: 'Menu lengkap' }).click();
    await expect(nav.getByRole('link', { name: 'Pengaturan' })).toHaveCount(0);
    await expect(nav.getByRole('link', { name: 'Aktivitas & Keamanan' })).toHaveCount(0);
    await expect(page.locator('[disabled], [aria-disabled="true"]').filter({ hasText: /Tinjauan|Pengaturan/ })).toHaveCount(0);

    await page.getByRole('button', { name: 'Buat', exact: true }).click();
    await expect(page.getByRole('menuitem')).toHaveText(['Proyek', 'Pembelian', 'Pembayaran', 'Unggah Dokumen']);
});

test('collapses to the rail with Ctrl+B, opens the help with Ctrl+/, and leads with the skip link (KB-02, KB-03)', async ({ page }) => {
    await open(page, 'owner');

    await page.keyboard.press('Tab');
    await expect(page.getByRole('link', { name: 'Langsung ke konten utama' })).toBeFocused();
    await page.keyboard.press('Enter');
    await expect(page.locator('#konten')).toBeFocused();

    await page.keyboard.press('Control+b');
    await expect(page.locator('nav[data-rail]')).toBeVisible();
    await page.keyboard.press('Control+b');
    await expect(page.locator('nav[data-rail]')).toHaveCount(0);

    await page.keyboard.press('Control+/');
    await expect(page.getByRole('dialog', { name: 'Pintasan' })).toContainText('Buka pencarian');
});

test('gives the phone its bottom bar of four and *Lainnya* with *Kerja*, *Menu lengkap* and *Akun* (PT-02; GR-11)', async ({ page }) => {
    await open(page, 'owner', 390);
    const bar = page.getByRole('navigation', { name: 'Navigasi utama' }).last();

    await expect(bar.getByRole('link')).toHaveText(['Beranda', 'Proyek']);
    await expect(bar.getByRole('button')).toHaveText(['Cari', 'Lainnya']);

    for (const box of await bar.locator('a, button').evaluateAll((items) => items.map((item) => item.getBoundingClientRect()))) {
        expect(Math.min(box.width, box.height)).toBeGreaterThanOrEqual(60);
    }

    await bar.getByRole('button', { name: 'Lainnya' }).click();
    const drawer = page.getByRole('dialog');
    await expect(drawer.locator('section h2')).toHaveText(['Kerja', 'Menu lengkap', 'Akun']);
    await expect(drawer.getByRole('link', { name: 'Tinjauan' })).toBeVisible();
});

test('searches with the term in the request body only, an exact code first (IP-17; DP-02; INT-005)', async ({ page }) => {
    const bodies: string[] = [];
    await page.route('**/cari', async (route) => {
        bodies.push(route.request().postData() ?? '');
        await route.fulfill({
            json: {
                exact: [{ type: 'project', id: '01a1', code: 'ARJ/PRJ/2026/0007', label: 'Pengadaan meja', company: 'ARJ' }],
                groups: [{ type: 'client', rows: [{ type: 'client', id: '01a2', code: null, label: 'SMAN 1 Bantul' }] }],
                notice: null,
            },
        });
    });
    await open(page, 'owner');

    await page.keyboard.press('Control+k');
    await expect(page.getByRole('combobox', { name: 'Cari atau pindai kode' })).toBeFocused();
    await page.keyboard.type('ARJ/PRJ/2026/0007');

    await expect(page.getByRole('option').first()).toContainText('Kode Cocok');
    expect(JSON.parse(bodies.at(-1) ?? '{}')).toEqual({ q: 'ARJ/PRJ/2026/0007' });
    expect(page.url()).not.toContain('0007');

    // One hit opens its record: an Inertia visit to the project's journey (PG-011) — answered here by the stack's
    // fallback, since the fixture keeps no session.
    const visit = page.waitForRequest((request) => request.url().endsWith('/proyek/01a1') && request.headers()['x-inertia'] === 'true', { timeout: VISIT });
    await page.keyboard.press('Enter');
    await visit;
});

test('signs in again in place after the session ends: the input kept, the command replayed once (IP-11; HO-02; criterion 7)', async ({ page }) => {
    await open(page, 'admin');
    await page.getByLabel('Alasan').fill('Termin pertama');
    await page.getByRole('button', { name: 'Sesi Berakhir' }).click();
    await page.waitForTimeout(300);

    await page.getByRole('button', { name: 'Catat Pembayaran' }).click();
    const dialog = page.getByRole('alertdialog', { name: 'Masuk lagi untuk melanjutkan' });
    await expect(dialog).toBeVisible();
    const pending = await page.locator('[data-pending-command]').getAttribute('data-pending-command');
    expect(pending).toMatch(/^[0-9a-f-]{36}$/);

    await dialog.getByLabel('E-mail').fill('dimas@contoh.test');
    await dialog.getByLabel('Kata sandi').fill('salah');
    await dialog.getByRole('button', { name: 'Lanjutkan' }).click();
    await expect(dialog).toContainText('E-mail atau kata sandi salah.');

    await dialog.getByLabel('Kata sandi').fill('sandi-contoh-fixture');
    await dialog.getByRole('button', { name: 'Lanjutkan' }).click();

    const outcome = page.getByRole('status').filter({ hasText: 'Tersimpan' });
    await expect(outcome).toBeVisible({ timeout: VISIT });
    await expect(outcome).toHaveAttribute('data-command-id', pending ?? '');
    await expect(outcome).toHaveAttribute('data-executed', '1');
    await expect(page.getByLabel('Alasan')).toHaveValue('Termin pertama');
});

test('never offers the previous term\'s results once the term changes — not by Enter, in the debounce or the new request (IP-17)', async ({ page }) => {
    const held: { release?: () => void } = {};
    await page.route('**/cari', async (route) => {
        const { q } = JSON.parse(route.request().postData() ?? '{}') as { q?: string };

        if (q === 'ARJ/PRJ/2026/0007') {
            await route.fulfill({ json: { exact: [{ type: 'project', id: '01a1', code: 'ARJ/PRJ/2026/0007', label: 'Pengadaan meja', company: 'ARJ' }], groups: [], notice: null } });

            return;
        }

        // The new term's answer is held until Enter has been pressed while it is on its way.
        await new Promise<void>((resolve) => {
            held.release = resolve;
        });
        await route.fulfill({ json: { exact: [], groups: [{ type: 'client', rows: [{ type: 'client', id: '01a2', code: null, label: 'SMAN 1 Bantul' }] }], notice: null } });
    });
    await open(page, 'owner');
    const visits: string[] = [];
    page.on('request', (request) => {
        if (request.headers()['x-inertia'] === 'true') {
            visits.push(request.url());
        }
    });

    await page.keyboard.press('Control+k');
    await page.keyboard.type('ARJ/PRJ/2026/0007');
    await expect(page.getByRole('option').first()).toContainText('Pengadaan meja');

    // In the debounce: the previous term's row is gone at once, and Enter opens nothing.
    await page.keyboard.type('8');
    await expect(page.getByRole('option', { name: /Pengadaan meja/ })).toHaveCount(0);
    await page.keyboard.press('Enter');

    // With the new term's request on its way: still nothing to choose.
    await expect.poll(() => held.release !== undefined, { timeout: VISIT }).toBe(true);
    await expect(page.getByRole('option', { name: /Pengadaan meja/ })).toHaveCount(0);
    await page.keyboard.press('Enter');

    held.release?.();
    await expect(page.getByRole('option').first()).toContainText('SMAN 1 Bantul');
    await expect(page.getByRole('combobox', { name: 'Cari atau pindai kode' })).toHaveValue('ARJ/PRJ/2026/00078');
    expect(visits.filter((url) => url.endsWith('/proyek/01a1'))).toEqual([]);
});

test('extends the session by a foreground request when "Tetap Masuk" is pressed (IP-11; MSG-33; AU-07)', async ({ page }) => {
    await page.clock.install();
    await open(page, 'owner');

    // Five minutes before the idle limit the warning opens.
    await page.clock.fastForward('55:30');
    const warning = page.getByRole('alertdialog', { name: 'Sesi Akan Berakhir' });
    await expect(warning).toBeVisible();

    const stay = page.waitForRequest((request) => request.method() === 'GET' && request.headers()['x-inertia'] === 'true');
    await warning.getByRole('button', { name: 'Tetap Masuk' }).click();
    expect((await stay).headers()['x-background-request']).toBeUndefined();
    await expect(warning).toBeHidden();

    // The visit counts as activity: the next checks find a full hour ahead, no warning.
    await page.clock.fastForward('01:00');
    await expect(warning).toBeHidden();
});

test('replays a command whose answer was lost, after signing in again, with the recorded outcome (IP-11; HO-02; criterion 7)', async ({ page }) => {
    await open(page, 'admin');
    await page.getByLabel('Alasan').fill('Termin kedua');
    let lost = false;
    // The first attempt reaches the server, which records it, and its answer never comes back.
    await page.route('**/__fixtures/shell/perintah**', async (route) => {
        if (lost) {
            await route.continue();

            return;
        }

        lost = true;
        await route.fetch({ maxRedirects: 0 });
        await route.abort('failed');
    });

    await page.getByRole('button', { name: 'Catat Pembayaran' }).click();
    const unknown = page.getByRole('alert').filter({ hasText: 'Belum dapat dipastikan' });
    await expect(unknown).toBeVisible({ timeout: VISIT });
    const pending = await page.locator('[data-pending-command]').getAttribute('data-pending-command');

    await page.getByRole('button', { name: 'Sesi Berakhir' }).click();
    await page.waitForTimeout(300);
    await unknown.getByRole('button', { name: 'Coba Lagi' }).click();

    const dialog = page.getByRole('alertdialog', { name: 'Masuk lagi untuk melanjutkan' });
    await expect(dialog).toBeVisible();
    await dialog.getByLabel('E-mail').fill('dimas@contoh.test');
    await dialog.getByLabel('Kata sandi').fill('sandi-contoh-fixture');
    await dialog.getByRole('button', { name: 'Lanjutkan' }).click();

    const outcome = page.getByRole('status').filter({ hasText: 'Tersimpan' });
    await expect(outcome).toHaveAttribute('data-command-id', pending ?? '', { timeout: VISIT });
    await expect(outcome).toHaveAttribute('data-replayed', 'true');
    await expect(outcome).toHaveAttribute('data-executed', '1');
});

test('loads the page afresh when another person signs in, before anything is uncovered or resent (IP-11; UXH-02)', async ({ page }) => {
    await open(page, 'admin');
    await page.getByLabel('Alasan').fill('Termin pertama');
    await page.getByRole('button', { name: 'Sesi Berakhir' }).click();
    await page.waitForTimeout(300);
    await page.getByRole('button', { name: 'Catat Pembayaran' }).click();

    const dialog = page.getByRole('alertdialog', { name: 'Masuk lagi untuk melanjutkan' });
    await expect(dialog).toBeVisible();
    await dialog.getByLabel('E-mail').fill('bayu@contoh.test');
    await dialog.getByLabel('Kata sandi').fill('sandi-contoh-fixture');
    const loaded = page.waitForEvent('load');
    await dialog.getByRole('button', { name: 'Lanjutkan' }).click();
    await loaded;

    await expect(page.getByRole('button', { name: /Menu akun/ })).toContainText('Bayu');
    await expect(page.getByLabel('Alasan')).toHaveValue('');
    await expect(page.getByRole('status').filter({ hasText: 'Tersimpan' })).toHaveCount(0);
});

test('warns of the unsent input before "Keluar" from the dialog, then loads sign-in afresh (IP-11; MSG-35)', async ({ page }) => {
    await open(page, 'admin');
    await page.getByRole('button', { name: 'Sesi Berakhir' }).click();
    await page.waitForTimeout(300);
    await page.getByRole('button', { name: 'Catat Pembayaran' }).click();

    const dialog = page.getByRole('alertdialog', { name: 'Masuk lagi untuk melanjutkan' });
    await expect(dialog).toBeVisible();
    await dialog.getByRole('button', { name: 'Keluar' }).click();

    const warning = page.getByRole('alertdialog', { name: 'Isian yang belum dikirim' });
    await expect(warning).toContainText('Isian yang belum dikirim akan hilang');
    await warning.getByRole('button', { name: 'Keluar' }).click();
    await page.waitForURL('**/masuk', { timeout: VISIT });
});

test('asks for the step-up with a status, never a redirect, and sends the same command after (IP-10; criterion 8)', async ({ page }) => {
    await open(page, 'owner');
    await page.getByRole('button', { name: 'Buat Tautan Atur Kata Sandi' }).click();

    const dialog = page.getByRole('alertdialog', { name: 'Konfirmasi Kata Sandi' });
    await expect(dialog).toBeVisible();
    await expect(dialog).toContainText('Laras');
    const pending = await page.locator('[data-pending-command]').getAttribute('data-pending-command');

    await dialog.getByLabel('Kata sandi').fill('sandi-contoh-fixture');
    await dialog.getByRole('button', { name: 'Lanjutkan' }).click();

    const outcome = page.getByRole('status').filter({ hasText: 'Tersimpan' });
    await expect(outcome).toHaveAttribute('data-command-id', pending ?? '', { timeout: VISIT });
    await expect(outcome).toHaveAttribute('data-executed', '1');
});

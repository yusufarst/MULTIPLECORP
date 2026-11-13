import { expect as base, type Page, type Route, test } from '@playwright/test';

import { type Account, signIn, totp } from '../account-fixtures';
import { numberingCompany } from '../numbering-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-09 (TASK-093; D6; UXH-02; IP-11; AU-15): the in-place re-authentication on PG-014's real save and the application's
// own gateway. A session still signed in that met a 419 proves its account by the server's password confirmation of that
// account — a wrong password, another account's e-mail or a forged token refused, the dialog open, nothing replayed —;
// an ended session signs in anew with its second factor; another account, even with the same display name, capabilities
// and companies, gets the full document load and never the first account's command. Each replay is counted by the
// outcome the server recorded for its command_id (CI-05), never by a second effect. Every account here is an Admin with
// a second factor, scoped to the one company the test creates: an Owner's hints list every company, which a parallel
// test changes — and a changed hint rightly loads the page afresh (UXH-02).

test.describe.configure({ timeout: 300_000 });

type Recorded = { command_id: string; state: string; actor: string | null };

/** An account named as every fixture account is ("Rani Uji"), with TOTP, given ADMIN_OPERASIONAL, `company` alone and PG-014's capabilities. */
async function scopedAccount(page: Page, run: string, company: string): Promise<Account> {
    const response = await page.request.post('/__fixtures/akun', { data: { run, kind: 'totp', locale: 'id' } });
    expect(response.ok(), await response.text()).toBe(true);
    const account = (await response.json()) as Account;
    const scoped = await page.request.post('/__fixtures/reauth/lingkup', { data: { account: account.id, company } });
    expect(scoped.ok(), await scoped.text()).toBe(true);

    return account;
}

/** PG-014 with a draft, its account signed in on the page; the account's credentials and company. */
async function draftPage(page: Page, run: string): Promise<{ account: Account; path: string; company: string }> {
    const company = await numberingCompany(page, `${String(new Date().getFullYear())}-01-01`);
    const account = await scopedAccount(page, run, company);
    await signIn(page, account);
    const made = await page.request.post('/__fixtures/penawaran', { data: { company, lines: 1, stage: 'draft' } });
    expect(made.ok(), await made.text()).toBe(true);
    const quotation = (await made.json()) as { project: string; number: string };
    const path = `/proyek/${quotation.project}/penawaran`;
    await page.goto(path);
    await expect(page.getByRole('heading', { level: 1 })).toHaveText(`Penawaran ${quotation.number}`);

    return { account, path, company };
}

/** Every command the page sends to `path`, its command_id among them (IP-02). */
function commandsTo(page: Page, path: string): string[] {
    const ids: string[] = [];
    page.on('request', (request) => {
        if (request.method() !== 'GET' && new URL(request.url()).pathname === path) {
            const id = (request.postDataJSON() as Record<string, unknown> | null)?.command_id;

            if (typeof id !== 'string' || !/^[0-9a-f-]{36}$/.test(id)) throw new Error(`a command without a valid command_id: ${JSON.stringify(id)}`);
            ids.push(id);
        }
    });

    return ids;
}

async function recorded(page: Page, id: string): Promise<Recorded[]> {
    const response = await page.request.get(`/__fixtures/reauth/perintah?ids=${id}`);
    expect(response.ok()).toBe(true);

    return ((await response.json()) as { commands: Recorded[] }).commands;
}

/** The next POST to `path` answered by the server's own 419: sent without the browser's same-origin marker and with a token the session never issued. */
async function forge419(page: Page, path: string): Promise<{ status: () => number }> {
    let done = false;
    let status = 0;
    await page.route((url) => url.pathname === path, async (route: Route) => {
        if (route.request().method() !== 'POST' || done) {
            await route.fallback();

            return;
        }

        done = true;
        const response = await route.fetch({ headers: { ...route.request().headers(), 'sec-fetch-site': 'cross-site', 'x-csrf-token': 'kedaluwarsa', 'x-xsrf-token': 'kedaluwarsa' } });
        status = response.status();
        await route.fulfill({ response });
    });

    return { status: () => status };
}

const dialog = (page: Page) => page.getByRole('alertdialog', { name: 'Masuk lagi untuk melanjutkan' });
const description = (page: Page) => page.locator('#line-0 input').first();

/** The dialog's e-mail and password sent, and the server's answer to `endpoint` awaited — a repeated MSG-19 never read as the earlier one. */
async function submit(page: Page, email: string, password: string, endpoint: string): Promise<void> {
    await dialog(page).getByLabel('E-mail').fill(email);
    await dialog(page).getByLabel('Kata sandi', { exact: true }).fill(password);
    const answered = page.waitForResponse((response) => response.request().method() === 'POST' && new URL(response.url()).pathname === endpoint);
    await dialog(page).getByRole('button', { name: 'Lanjutkan' }).click();
    await answered;
}

/** The code step's six digits sent and the server's answer awaited. */
async function sendCode(page: Page, code: string): Promise<void> {
    await dialog(page).getByLabel('Kode 6 digit dari aplikasi autentikator').fill(code);
    const answered = page.waitForResponse((response) => response.request().method() === 'POST' && new URL(response.url()).pathname === '/masuk/verifikasi');
    await dialog(page).getByRole('button', { name: 'Lanjutkan' }).click();
    await answered;
}

async function mark(page: Page): Promise<void> {
    await page.evaluate(() => { Reflect.set(window, '__covered', 1); });
}

test('a session still signed in after a 419: a forged token, a wrong password and another account\'s e-mail refused with nothing replayed, then its own password replays the one command once (D6; IP-11; AU-15)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = `reauth-hidup-${String(Date.now())}`;
    const { account, path, company } = await draftPage(page, run);
    const other = await scopedAccount(page, `${run}-lain`, company);
    const sent = commandsTo(page, path);
    await mark(page);

    await description(page).fill(`Isian tertunda ${run}`);
    const forged = await forge419(page, path);
    await page.getByRole('button', { name: 'Simpan Draf' }).click();
    await expect(dialog(page)).toBeVisible();
    expect(forged.status(), 'the server answered the forged token 419 — the session still signed in').toBe(419);
    expect(sent).toHaveLength(1);
    const pending = sent[0] ?? '';
    expect(await recorded(page, pending), 'the 419 ran nothing').toEqual([]);

    // The dialog's own request with a forged token: the server's 419, never a success.
    const forgedConfirm = await forge419(page, '/konfirmasi-kata-sandi');
    await submit(page, account.email, account.password, '/konfirmasi-kata-sandi');
    await expect.poll(() => forgedConfirm.status()).toBe(419);
    await expect(dialog(page)).toBeVisible();
    await expect(dialog(page)).toContainText('E-mail atau kata sandi salah.');

    // A wrong password: refused by the server's own check, the dialog open.
    await submit(page, account.email, 'bukan-kata-sandinya', '/konfirmasi-kata-sandi');
    await expect(dialog(page)).toContainText('E-mail atau kata sandi salah.');
    await expect(dialog(page)).toBeVisible();

    // Another account's e-mail and its own correct password: the session's account is not proved — refused alike.
    await submit(page, other.email, other.password, '/konfirmasi-kata-sandi');
    await expect(dialog(page)).toContainText('E-mail atau kata sandi salah.');
    await expect(dialog(page)).toBeVisible();

    expect(sent, 'nothing replayed while unproved').toEqual([pending]);
    expect(await recorded(page, pending), 'nothing recorded while unproved').toEqual([]);

    // Its own e-mail and password: the page resumed in place, the one command replayed once under its command_id.
    await submit(page, account.email, account.password, '/konfirmasi-kata-sandi');
    await expect(dialog(page)).toHaveCount(0);
    await expect(page.locator('#konten [data-answer="COMMITTED"]')).toContainText('Draf penawaran tersimpan.');
    expect(await page.evaluate((): unknown => Reflect.get(window, '__covered')), 'the same document — resumed in place').toBe(1);
    expect(new Set(sent), 'one command_id across the 419 and its replay').toEqual(new Set([pending]));
    const rows = await recorded(page, pending);
    expect(rows, 'one recorded outcome').toHaveLength(1);
    expect(rows[0]?.state).toBe('COMMITTED');
    expect(rows[0]?.actor).toBe(account.id);
});

test('an ended session signs in anew with its second factor and replays the one command; another account with the same display hints gets the full document load and nothing of it (D6; UXH-02; AU-16)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = `reauth-habis-${String(Date.now())}`;
    const { account, path, company } = await draftPage(page, run);
    // The fixture names every account alike: a second account with the same role, grants and company has the same hints.
    const twin = await scopedAccount(page, `${run}-kembar`, company);
    const sent = commandsTo(page, path);

    // The same account: password and code, the page resumed, the pending command replayed once.
    await mark(page);
    await description(page).fill(`Isian pertama ${run}`);
    expect((await page.request.post('/__fixtures/reauth/usang', { data: { account: account.id } })).ok()).toBe(true);
    await page.getByRole('button', { name: 'Simpan Draf' }).click();
    await expect(dialog(page)).toBeVisible();
    const first = sent.at(-1) ?? '';
    expect(await recorded(page, first), 'the ended session ran nothing').toEqual([]);
    await submit(page, account.email, account.password, '/masuk');
    const code = dialog(page).getByLabel('Kode 6 digit dari aplikasi autentikator');
    await expect(code).toBeVisible();
    // The sign-in of this test took the current step (M-39): the code of the next one.
    await sendCode(page, totp(account.secret ?? '', 1));
    await expect(dialog(page)).toHaveCount(0);
    await expect(page.locator('#konten [data-answer="COMMITTED"]')).toContainText('Draf penawaran tersimpan.');
    expect(await page.evaluate((): unknown => Reflect.get(window, '__covered'))).toBe(1);
    expect(sent.filter((id) => id === first).length, 'sent before and replayed once, under one command_id').toBe(2);
    expect((await recorded(page, first)).map((row) => row.state)).toEqual(['COMMITTED']);

    // Another account whose hints match: the full document load before anything is uncovered or resent.
    await mark(page);
    await description(page).fill(`Isian kedua ${run}`);
    expect((await page.request.post('/__fixtures/reauth/usang', { data: { account: account.id } })).ok()).toBe(true);
    await page.getByRole('button', { name: 'Simpan Draf' }).click();
    await expect(dialog(page)).toBeVisible();
    const second = sent.at(-1) ?? '';
    const before = sent.length;
    await submit(page, twin.email, twin.password, '/masuk');
    await expect(code).toBeVisible();
    await sendCode(page, totp(twin.secret ?? ''));
    await page.waitForFunction(() => Reflect.get(window, '__covered') === undefined);
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
    expect(sent.length, 'nothing of the first account resent').toBe(before);
    expect(await recorded(page, second), 'nothing recorded under the second account').toEqual([]);
    await expect(description(page)).not.toHaveValue(`Isian kedua ${run}`);
});

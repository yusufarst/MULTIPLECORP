import { type Browser, type BrowserContext, expect as base, type Locator, type Page, type TestInfo } from '@playwright/test';

import { landed, totp } from './account-fixtures';
import { watch } from './journey-fixtures';

// TASK-064's shared steps for CJ-10, CJ-18 and CJ-21 (routes/fixtures.php; JourneySessionFixture): a run's synthetic accounts and
// their Admin grants, the sign-in through PG-001 and PG-002 and the in-place re-authentication (IP-11) with the test's own
// authenticator, each code newer than the account's last (M-39); the session aged past the idle limit (AU-07), its last activity read
// (RB-05); the command identifiers a page sends and their `command_log` rows (CI-05; M-10); a project row held past the lock timeout
// (SQ-14); and the captures of every page a journey opens, merged, with the failures it injected itself set aside one by one.

export const expect = base;
export const slow = base.configure({ timeout: 60_000 });

export const DESK = { width: 1280, height: 900 };
export const PHONE = { width: 390, height: 844 };

export type Captured = ReturnType<typeof watch>;
export type K2Account = { id: string; email: string; password: string; secret?: string; recovery_codes?: string[] };
export type Actor = { context: BrowserContext; page: Page; captured: Captured; account: K2Account };
export type Recorded = { command_id: string; command_type: string; state: string; outcome_code: string | null; actor: string | null };

let runs = 0;

/** A run identifier: the engine and the time, so Chromium and WebKit never meet in the one database. */
export function runId(prefix: string, testInfo: TestInfo): string {
    return `${prefix}-${testInfo.project.name.slice(0, 2)}-${String(Date.now())}-${String((runs += 1))}`;
}

/** The captures of every page a journey opened, as one (journey-cj06.spec.ts's merge). */
export function merged(...all: Captured[]): Captured {
    // Read live, so each page's deferred decisions (`pending`) and the failures they add are seen when clean() reads them.
    return {
        get console() { return all.flatMap((one) => one.console); },
        get failed() { return all.flatMap((one) => one.failed); },
        get csp() { return all.flatMap((one) => one.csp); },
        get requests() { return all.reduce((sum, one) => sum + one.requests, 0); },
        engine: all[0]?.engine ?? '',
        get pending() { return all.flatMap((one) => one.pending); },
    };
}

/**
 * Sets aside a failure the journey injected itself — the 419 it made the server answer, the transport failure it made the browser
 * report —, at most `most` entries beginning with `prefix`, and answers how many: every other failure still fails the journey.
 */
export function setAside(captured: Captured, prefix: string, most: number): number {
    let removed = 0;
    captured.failed = captured.failed.filter((entry) => {
        if (removed < most && entry.startsWith(prefix)) {
            removed += 1;

            return false;
        }

        return true;
    });

    return removed;
}

/** A fresh synthetic account (AccountFixture), its e-mail named by a short tag, the time and a counter: TOTP unless `kind` says otherwise, a Google subject linked when given. */
export async function newAccount(page: Page, tag: string, options: { kind?: 'totp' | 'plain' | 'owner'; totp?: boolean; google?: string; locale?: 'id' | 'en' } = {}): Promise<K2Account> {
    const response = await page.request.post('/__fixtures/akun', {
        data: { run: `${tag.slice(0, 12)}-${String(Date.now())}-${String((runs += 1))}`, kind: options.kind ?? 'totp', totp: options.totp ?? false, locale: options.locale ?? 'id', ...(options.google === undefined ? {} : { google: options.google }) },
    });
    expect(response.ok(), 'the account fixture answered').toBe(true);

    return (await response.json()) as K2Account;
}

/** The account made an Admin of one company with a profile's capabilities (JourneySessionFixture::grant), its name its own when given. */
export async function grant(page: Page, account: K2Account, company: string, profile: 'view' | 'quotation', name?: string): Promise<void> {
    const response = await page.request.post('/__fixtures/k2/grant', { data: { account: account.id, company, profile, ...(name === undefined ? {} : { name }) } });
    expect(response.ok(), `the grant fixture answered: ${await response.text()}`).toBe(true);
}

const usedStep = new Map<string, number>();

function step(): number {
    return Math.floor(Date.now() / 30_000);
}

/** Records that the account's code of the current step was taken elsewhere (an enrolment, account-fixtures' sign-in). */
export function tookCode(account: K2Account): void {
    usedStep.set(account.email, step());
}

/**
 * A code newer than the account's last one (M-39; Totp::WINDOW = 1): the current step's, or the next one's when the current was
 * taken or ends within six seconds; when even the next was taken, the clock is waited out to the step after it.
 */
export async function nextCode(account: K2Account): Promise<string> {
    const last = usedStep.get(account.email) ?? step() - 1;

    while (last + 1 > step() + 1) {
        await new Promise((resolve) => setTimeout(resolve, 30_000 - (Date.now() % 30_000) + 250));
    }

    let target = Math.max(step(), last + 1);

    if (target === step() && 30_000 - (Date.now() % 30_000) < 6_000) {
        target += 1;
    }

    usedStep.set(account.email, target);

    return totp(account.secret ?? '', target - step());
}

/** PG-001 with the e-mail and password, then PG-002's code when the account has TOTP, landed (account-fixtures' `landed`). */
export async function signInAt(page: Page, account: K2Account, password = account.password): Promise<void> {
    await page.goto('/masuk');
    await page.getByLabel('E-mail').fill(account.email);
    await page.getByLabel('Kata sandi', { exact: true }).fill(password);
    await page.getByRole('button', { name: 'Masuk', exact: true }).click();

    if (account.secret !== undefined) {
        await page.waitForURL('**/masuk/verifikasi');
        await page.getByLabel('Kode 6 digit dari aplikasi autentikator').fill(await nextCode(account));
        await page.getByRole('button', { name: 'Verifikasi' }).click();
    }

    await landed(page);
}

/** IP-11's dialog over the open page (session-dialogs.tsx): opaque, MSG-11, "Keluar" and "Lanjutkan". */
export function reauthDialog(page: Page): Locator {
    return page.getByRole('alertdialog', { name: 'Masuk lagi untuk melanjutkan' });
}

/** Signs in again at the dialog: the e-mail and password, then the code step in the same dialog when the account has TOTP (AU-16). */
export async function reauthenticate(page: Page, account: K2Account, password = account.password): Promise<void> {
    const dialog = reauthDialog(page);
    await slow(dialog).toBeVisible();
    await expect(dialog).toContainText('Sesi Anda telah berakhir. Masukkan e-mail dan kata sandi untuk melanjutkan. Isian Anda di halaman ini tidak hilang.');
    await dialog.getByLabel('E-mail').fill(account.email);
    await dialog.getByLabel('Kata sandi', { exact: true }).fill(password);
    await dialog.getByRole('button', { name: 'Lanjutkan' }).click();

    if (account.secret !== undefined) {
        const code = dialog.getByLabel('Kode 6 digit dari aplikasi autentikator');
        await slow(code).toBeVisible();
        await code.fill(await nextCode(account));
        await dialog.getByRole('button', { name: 'Lanjutkan' }).click();
    }
}

/** A journey's second actor in a browser of its own, watched from its first step, signed in as a fresh Admin of the company. */
export async function adminActor(
    browser: Browser,
    testInfo: TestInfo,
    setup: Page,
    tag: string,
    company: string,
    options: { profile: 'view' | 'quotation'; viewport: { width: number; height: number }; goLive?: string; name?: string; locale?: 'id' | 'en' },
): Promise<Actor> {
    const context = await browser.newContext({
        baseURL: testInfo.project.use.baseURL,
        ignoreHTTPSErrors: true,
        viewport: options.viewport,
        ...(options.goLive === undefined ? {} : { extraHTTPHeaders: { 'X-Fixture-Go-Live': options.goLive } }),
    });
    const page = await context.newPage();
    const captured = watch(page);
    const account = await newAccount(setup, tag, { locale: options.locale ?? 'id' });
    await grant(setup, account, company, options.profile, options.name);
    await signInAt(page, account);

    return { context, page, captured, account };
}

/** The account's session rows as the store holds them (JourneySessionFixture::session); `age` moves their last activity back. */
export async function sessionOf(page: Page, account: K2Account, action: 'read' | 'age' = 'read', seconds = 3_700): Promise<{ sessions: number; last_activity: number | null }> {
    const response = await page.request.post('/__fixtures/k2/sesi', { data: { account: account.id, action, seconds } });
    expect(response.ok(), 'the session fixture answered').toBe(true);

    return (await response.json()) as { sessions: number; last_activity: number | null };
}

/** The idle limit passed while the page stayed open (AU-07): the account's session is a guest's at its next request. */
export async function idleExpired(page: Page, account: K2Account): Promise<void> {
    const after = await sessionOf(page, account, 'age', 3_700);
    expect(after.sessions, 'the account has a session to end').toBeGreaterThan(0);
}

/**
 * The idle limit passed for an account whose page was read under FX-08's clock before midnight: that request wrote its
 * session's last activity on the day before, so the session may already be past the limit — and purged by the session
 * store's sweep —; whichever it is, no session of the account stays active and its next request is a guest's (AU-07).
 */
export async function idleEnded(page: Page, account: K2Account): Promise<void> {
    const after = await sessionOf(page, account, 'age', 3_700);
    const limit = Math.floor(Date.now() / 1000) - 3_600;
    expect(after.sessions === 0 || (after.last_activity !== null && after.last_activity < limit), 'no session of the account stays active').toBe(true);
}

/** The `command_log` rows of the identifiers given — the recorded outcomes (CI-05). */
export async function recorded(page: Page, ids: string[]): Promise<Recorded[]> {
    const response = await page.request.get(`/__fixtures/k2/perintah?ids=${ids.join('%2C')}`);
    expect(response.ok(), 'the command fixture answered').toBe(true);

    return ((await response.json()) as { commands: Recorded[] }).commands;
}

/** The one recorded outcome of an identifier, waited for until its terminal row is written. */
export async function outcomeOf(page: Page, id: string): Promise<Recorded> {
    await slow.poll(async () => (await recorded(page, [id])).length).toBe(1);
    const [row] = await recorded(page, [id]);

    return row as Recorded;
}

export type Sent = { method: string; body: Record<string, unknown> };

/** Every command request the page sends to `path`, in order, with its body — its `command_id` among them (IP-02). */
export function commandsTo(page: Page, path: string): Sent[] {
    const sent: Sent[] = [];
    page.on('request', (request) => {
        if (request.method() !== 'GET' && new URL(request.url()).pathname === path) {
            sent.push({ method: request.method(), body: (request.postDataJSON() ?? {}) as Record<string, unknown> });
        }
    });

    return sent;
}

/** The distinct identifiers among some sent commands. */
export function identities(sent: Sent[]): string[] {
    return [...new Set(sent.map((one) => {
        const id = one.body.command_id;

        // Every sent command carries its intent's identifier (CI-05): a missing or malformed one is a failure, never a value.
        if (typeof id !== 'string' || !/^[0-9a-f-]{36}$/i.test(id)) {
            throw new Error(`a sent command without a valid command_id: ${JSON.stringify(id)}`);
        }

        return id;
    }))];
}

/** A project row held FOR UPDATE for `seconds` (SQ-14's lock wait), answered when the hold ends; awaited only once it is in place. */
export async function holdProject(page: Page, project: string, seconds: number): Promise<{ ended: Promise<unknown> }> {
    const ended = page.request.post('/__fixtures/k2/tahan', { data: { project, seconds }, timeout: 60_000 });
    await slow.poll(async () => ((await (await page.request.get(`/__fixtures/k2/tahan?project=${project}`)).json()) as { held: boolean }).held).toBe(true);

    return { ended };
}

/** The server's business day, the day before, and the instant 30 s before the WIB midnight that began it (FX-08). */
export async function serverDay(page: Page): Promise<{ today: string; yesterday: string; before_midnight: string }> {
    const response = await page.request.get('/__fixtures/k2/hari');
    expect(response.ok()).toBe(true);

    return (await response.json()) as { today: string; yesterday: string; before_midnight: string };
}

/** Nothing of the unsent input or the pending identifier on the device or in the history's entries (UXH-02; SM-13; WS-07). */
export async function nothingStored(page: Page, values: string[]): Promise<void> {
    const kept = await page.evaluate(() => JSON.stringify([window.history.state, Object.entries(window.localStorage), Object.entries(window.sessionStorage)]));

    for (const value of values) {
        expect(kept, 'nothing of the form in browser storage or history (UXH-02)').not.toContain(value);
    }
}

/** The layout kept after a change: nothing wider than the viewport (LOCALIZATION §3.7). */
export async function noOverflow(page: Page): Promise<void> {
    const widths = await page.evaluate(() => ({ scroll: document.documentElement.scrollWidth, client: document.documentElement.clientWidth }));
    expect(widths.scroll, 'no horizontal scroll (LOCALIZATION §3.7)').toBeLessThanOrEqual(widths.client);
}

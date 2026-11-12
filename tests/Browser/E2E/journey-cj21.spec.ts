import { type Page, type Route, test } from '@playwright/test';

import { clean, guardsClean, watch } from '../journey-fixtures';
import {
    adminActor,
    commandsTo,
    DESK,
    expect,
    grant,
    holdProject,
    idleExpired,
    identities,
    merged,
    newAccount,
    nothingStored,
    outcomeOf,
    PHONE,
    reauthDialog,
    reauthenticate,
    recorded,
    runId,
    serverDay,
    sessionOf,
    setAside,
    slow,
} from '../journey-k2-fixtures';
import { quotationRun, type QuotationRun } from '../quotation-fixtures';

// TL-09 (TASK-064; CJ-21; AC-16; GAP-008; architecture challenge 19a; UXS-01–UXS-05, UXS-07, UXS-08, UXS-11–UXS-13; UXH-02; criteria 8, 9,
// 12): command identity and session behaviour on PG-014 *Penawaran* of a company made for the run, by fresh Admins of it signed in with
// TOTP — a double click on a slow network, an answer lost after the commit and retried across an idle expiry, a request that never
// reached the server, a lock-wait FAILED, a 401 and a 419 each re-authenticated in place, every one recording one effect under one
// `command_id`; two Admins on one draft, the later one answered CONFLICT with its input kept and re-applied; a client answer "already
// recorded" with no re-apply, on the phone; a prefilled date crossing WIB midnight under the controlled clock (FX-08), CONFLICT with both
// dates; and UXH-02's cases — the unsent input in memory and never in browser storage, deferred groups marked background and extending
// no session, another account signing in at the re-authentication dialog uncovering and resending nothing. Every attempt is asserted by
// its recorded outcome in `command_log`, never by a second effect (CI-05; M-10). Each test watches its console and network from its
// first step and ends with ReconcileGuards reporting no discrepancy for its company (TS-11); the failures it injected itself — the 419
// the server answered and the transports it cut — are set aside one by one, every other failing it.

// A journey signs in an Owner and one or two Admins, then crosses several re-authentications, each with a TOTP code newer than the last.
test.describe.configure({ timeout: 300_000 });

/** The run's go-live, as quotationRun arms it (numbering-fixtures): the year's first day. */
const goLive = (): string => `${String(new Date().getFullYear())}-01-01`;

/** PG-014 opened and settled. */
async function openQuotation(page: Page, q: QuotationRun): Promise<void> {
    await page.goto(`/proyek/${q.project}/penawaran`);
    await slow(page.getByRole('heading', { level: 1 })).toHaveText(`Penawaran ${q.number}`);
}

/** The first line's *Keterangan* — a DOM locator, readable while a modal dialog keeps the page out of the accessibility tree. */
const description = (page: Page) => page.locator('#line-0 input').first();

const committed = (page: Page) => page.locator('#konten [data-answer="COMMITTED"]').filter({ hasText: 'Draf penawaran tersimpan.' });

test('CJ-21 at the desk: a double click, a lost answer across an idle expiry, an unreached request, a lock-wait FAILED, a 401 and a 419 — one effect under one command_id each (UXS-01–UXS-05, UXS-07, UXS-08; criteria 8, 9)', async ({ page, browser }, testInfo) => {
    const captured = watch(page);
    await page.setViewportSize(DESK);
    const run = runId('cj21', testInfo);
    const q = await quotationRun(page, run, { stage: 'draft' });
    const a = await adminActor(browser, testInfo, page, 'cj21a', q.company, { profile: 'quotation', viewport: DESK, goLive: goLive() });
    const p = a.page;
    const path = `/proyek/${q.project}/penawaran`;
    const matcher = (url: URL): boolean => url.pathname === path;
    const sent = commandsTo(p, path);
    await openQuotation(p, q);
    const save = p.getByRole('button', { name: 'Simpan Draf' });
    const failed = p.locator('#konten [data-answer="FAILED"]');

    // UXS-01: a double click on a slow network — the control busy, one identifier sent, one outcome recorded.
    await description(p).fill(`Langkah satu ${run}`);
    let release: () => void = () => undefined;
    const gate = new Promise<void>((resolve) => {
        release = resolve;
    });
    const slowNetwork = async (route: Route): Promise<void> => {
        if (route.request().method() === 'POST') await gate;
        await route.fallback();
    };
    await p.route(matcher, slowNetwork);
    await save.dblclick();
    await expect(save, 'the control is busy after the first click (IP-02)').toHaveAttribute('aria-busy', 'true');
    release();
    await slow(committed(p)).toBeVisible();
    await p.unroute(matcher, slowNetwork);
    const doubled = identities(sent);
    expect(doubled, 'the double click sent one command_id (CI-01; HO-03)').toHaveLength(1);
    expect((await outcomeOf(page, doubled[0] ?? '')).state).toBe('COMMITTED');

    // UXS-03: the answer lost after the commit (MSG-07), then the session idle-expires; "Coba Lagi" meets the in-place re-login and
    // returns the recorded outcome under the same identifier — no second command.
    let mark = sent.length;
    await description(p).fill(`Langkah dua ${run}`);
    let lost = false;
    const loseAnswer = async (route: Route): Promise<void> => {
        if (route.request().method() !== 'POST' || lost) {
            await route.fallback();

            return;
        }

        lost = true;
        await route.fetch({ maxRedirects: 0 });
        await route.abort('failed');
    };
    await p.route(matcher, loseAnswer);
    await save.click();
    await slow(failed).toContainText('Belum dapat dipastikan apakah tindakan ini tersimpan. Tekan Coba Lagi — sistem tidak akan mencatat dua kali.');
    await expect(failed).toContainText('Belum Dapat Dipastikan');
    const lostId = String(sent[mark]?.body.command_id);
    expect((await outcomeOf(page, lostId)).state, 'the lost answer\'s command was recorded once').toBe('COMMITTED');
    // D-UX-02: the inputs frozen while the outcome is unknown.
    await expect.soft(description(p), 'the inputs are frozen while the outcome is unknown (IP-02; D-UX-02)').toBeDisabled();
    await idleExpired(page, a.account);
    await failed.getByRole('button', { name: 'Coba Lagi' }).click();
    await reauthenticate(p, a.account);
    await slow(reauthDialog(p)).toHaveCount(0);
    await slow(committed(p)).toBeVisible();
    await p.unroute(matcher, loseAnswer);
    const lostAttempts = sent.slice(mark);
    expect(identities(lostAttempts), 'the retry after the re-login carried the same command_id (HO-02; CI-05)').toEqual([lostId]);
    expect(lostAttempts.at(-1)?.body, 'and the same payload').toEqual(lostAttempts[0]?.body);
    expect(await recorded(page, [lostId]), 'one recorded outcome, COMMITTED').toEqual([expect.objectContaining({ state: 'COMMITTED', actor: a.account.id })]);

    // UXS-08: a transport failure before the server saw it — still MSG-07, nothing recorded; the retry runs it once.
    mark = sent.length;
    await description(p).fill(`Langkah tiga ${run}`);
    let cut = false;
    const cutTransport = async (route: Route): Promise<void> => {
        if (route.request().method() !== 'POST' || cut) {
            await route.fallback();

            return;
        }

        cut = true;
        await route.abort('failed');
    };
    await p.route(matcher, cutTransport);
    await save.click();
    await slow(failed).toContainText('Belum dapat dipastikan apakah tindakan ini tersimpan.');
    const unreachedId = String(sent[mark]?.body.command_id);
    expect(await recorded(page, [unreachedId]), 'the server never saw the first attempt').toEqual([]);
    await failed.getByRole('button', { name: 'Coba Lagi' }).click();
    expect((await outcomeOf(page, unreachedId)).state).toBe('COMMITTED');
    await slow(committed(p)).toBeVisible();
    await p.unroute(matcher, cutTransport);
    expect(identities(sent.slice(mark)), 'the retry ran the same intent (IP-02)').toEqual([unreachedId]);

    // UXS-07: a lock-wait timeout — MSG-06 "Aman diulang", nothing recorded; once the lock is gone the same identifier commits.
    mark = sent.length;
    await description(p).fill(`Langkah empat ${run}`);
    const hold = await holdProject(page, q.project, 15);
    await save.click();
    await slow(failed).toContainText('Sistem sedang sibuk sehingga tindakan ini belum diproses. Aman diulang.');
    await expect(failed).toContainText('Belum Diproses');
    const lockedId = String(sent[mark]?.body.command_id);
    expect(await recorded(page, [lockedId]), 'a lock-wait FAILED commits nothing (SQ-14; RY-07)').toEqual([]);
    await hold.ended;
    await failed.getByRole('button', { name: 'Coba Lagi' }).click();
    expect((await outcomeOf(page, lockedId)).state).toBe('COMMITTED');
    await slow(committed(p)).toBeVisible();
    expect(identities(sent.slice(mark)), 'safe to repeat under the same identifier').toEqual([lockedId]);

    // UXS-04 with UXS-05's identity: the session ended while the form was open; the submission answers 401, the page and its input stay
    // in memory and nowhere else, and the re-login resends the same identifier once.
    mark = sent.length;
    const typed401 = `Langkah lima ${run}`;
    await description(p).fill(typed401);
    await idleExpired(page, a.account);
    await save.click();
    await slow(reauthDialog(p)).toBeVisible();
    const pending401 = String(sent[mark]?.body.command_id);
    await expect(description(p), 'the unsent input stays on the page behind the dialog').toHaveValue(typed401);
    await nothingStored(p, [typed401, pending401]);
    await reauthenticate(p, a.account);
    expect((await outcomeOf(page, pending401)).state).toBe('COMMITTED');
    await slow(reauthDialog(p)).toHaveCount(0);
    expect(identities(sent.slice(mark)), 'the 401 and its resend share one command_id').toEqual([pending401]);
    await expect(description(p)).toHaveValue(typed401);

    // UXS-05: 419 on submit — the server's own answer to a stale forgery token after the session ended — is the ended session: the
    // page stays, nothing is lost, and the resubmission carries the same identifier.
    mark = sent.length;
    const typed419 = `Langkah enam ${run}`;
    await description(p).fill(typed419);
    await idleExpired(page, a.account);
    let forged = false;
    let forgedStatus = 0;
    const staleToken = async (route: Route): Promise<void> => {
        if (route.request().method() !== 'POST' || forged) {
            await route.fallback();

            return;
        }

        forged = true;
        // Sent as a request without the browser's same-origin marker and with a token the session never issued.
        const response = await route.fetch({ headers: { ...route.request().headers(), 'sec-fetch-site': 'cross-site', 'x-csrf-token': 'kedaluwarsa', 'x-xsrf-token': 'kedaluwarsa' } });
        forgedStatus = response.status();
        await route.fulfill({ response });
    };
    await p.route(matcher, staleToken);
    await save.click();
    await slow(reauthDialog(p)).toBeVisible();
    expect(forgedStatus, 'the server answered the stale token 419').toBe(419);
    const pending419 = String(sent[mark]?.body.command_id);
    expect(await recorded(page, [pending419]), 'the 419 ran nothing').toEqual([]);
    await expect(description(p)).toHaveValue(typed419);
    await nothingStored(p, [typed419, pending419]);
    await reauthenticate(p, a.account);
    expect((await outcomeOf(page, pending419)).state).toBe('COMMITTED');
    await slow(reauthDialog(p)).toHaveCount(0);
    await p.unroute(matcher, staleToken);
    expect(identities(sent.slice(mark)), 'the 419 and its resend share one command_id').toEqual([pending419]);

    // The failures this journey injected, set aside one by one: the server's 419 and up to two transports it cut (an engine may name
    // a cut a cancellation, which the capture already leaves out).
    expect(setAside(a.captured, `POST ${path} 419`, 1), 'the injected 419 was seen').toBe(1);
    expect(setAside(a.captured, `POST ${path} `, 2)).toBeLessThanOrEqual(2);
    await guardsClean(page, testInfo, [q.company]);
    await clean(merged(captured, a.captured), testInfo);
    await a.context.close();
});

test('CJ-21 two Admins on one draft, then a prefilled date across WIB midnight: CONFLICT with the input kept and re-applied, CONFLICT with both dates (UXS-11, UXS-13; FX-08; criterion 8)', async ({ page, browser }, testInfo) => {
    const captured = watch(page);
    await page.setViewportSize(DESK);
    const run = runId('cj21d', testInfo);
    const q = await quotationRun(page, run, { stage: 'draft' });
    const a = await adminActor(browser, testInfo, page, 'cj21a', q.company, { profile: 'quotation', viewport: DESK, goLive: goLive() });
    const b = await adminActor(browser, testInfo, page, 'cj21b', q.company, { profile: 'quotation', viewport: DESK, goLive: goLive() });
    const path = `/proyek/${q.project}/penawaran`;
    const sentA = commandsTo(a.page, path);
    await openQuotation(a.page, q);
    await openQuotation(b.page, q);

    // B saves a new validity first.
    await b.page.getByLabel(/^Berlaku sampai/).fill(`31/12/${String(new Date().getFullYear() + 1)}`);
    await b.page.getByRole('button', { name: 'Simpan Draf' }).click();
    await slow(committed(b.page)).toBeVisible();

    // A saves its own description at the version it read: CONFLICT, MSG-03, its input kept beside the newer version (ST-01; M-38).
    const mine = `Keterangan Admin A ${run}`;
    await description(a.page).fill(mine);
    await a.page.getByRole('button', { name: 'Simpan Draf' }).click();
    const conflict = a.page.locator('#konten [data-answer="CONFLICT"]');
    await slow(conflict).toContainText('Data ini sudah berubah sejak Anda membukanya. Isian Anda tetap ada di formulir. Tinjau versi terbaru, lalu terapkan ulang.');
    await expect(conflict).toContainText('Data Sudah Berubah');
    await expect(description(a.page), 'the later editor\'s input is kept (HO-04)').toHaveValue(mine);
    const compare = a.page.locator('[data-slot="stale-comparison"]');
    await expect(compare.locator('[data-differs]'), 'the changed fields marked (PX-07)').toHaveCount(2);
    const [staleId] = identities(sentA);
    expect(await outcomeOf(page, staleId ?? '')).toEqual(expect.objectContaining({ state: 'CONFLICT', outcome_code: 'ST-01' }));

    // "Terapkan Ulang pada Versi Terbaru": B's validity taken, A's description kept, then saved as a new intent.
    await compare.getByRole('button', { name: 'Terapkan Ulang pada Versi Terbaru' }).click();
    await expect(compare).toHaveCount(0);
    await expect(description(a.page)).toHaveValue(mine);
    await a.page.getByRole('button', { name: 'Simpan Draf' }).click();
    await slow(committed(a.page)).toBeVisible();
    const saves = identities(sentA);
    expect(saves, 'the re-applied save is a new intent (IP-03)').toHaveLength(2);
    expect((await outcomeOf(page, saves[1] ?? '')).state).toBe('COMMITTED');

    // UXS-13 under FX-08's controlled clock: the page read 30 s before the WIB midnight that began today — its form prefilled with
    // yesterday —, the session ended overnight; the issue is confirmed after midnight, signed in again in place over the open
    // confirmation (UXS-05), and answered CONFLICT with both dates and the picker (ST-08; MSG-16).
    const day = await serverDay(page);
    const beforeMidnight = async (route: Route): Promise<void> => {
        if (route.request().resourceType() === 'document') {
            await route.continue({ headers: { ...route.request().headers(), 'x-fixture-clock': day.before_midnight } });

            return;
        }

        await route.fallback();
    };
    const matcher = (url: URL): boolean => url.pathname === path;
    await a.page.route(matcher, beforeMidnight);
    await openQuotation(a.page, q);
    await a.page.unroute(matcher, beforeMidnight);
    await idleExpired(page, a.account);
    const sentIssue = commandsTo(a.page, `${path}/terbitkan`);
    await a.page.getByRole('button', { name: 'Terbitkan Penawaran' }).click();
    const issuing = a.page.getByRole('alertdialog', { name: 'Terbitkan Penawaran?' });
    await expect(issuing.locator(`time[datetime="${day.yesterday}"]`), 'prefilled with the day the page was read').toBeVisible();
    await expect(issuing).toContainText('Hari ini');
    await issuing.getByRole('button', { name: 'Terbitkan Penawaran' }).click();
    await slow(reauthDialog(a.page)).toBeVisible();
    await expect(a.page.locator('[role="alertdialog"]').filter({ hasText: 'Terbitkan Penawaran?' }), 'the confirmation stays open beneath the dialog').toHaveCount(1);
    await reauthenticate(a.page, a.account);
    const stale = a.page.locator('[data-answer="CONFLICT"]').filter({ hasText: 'Tanggal transaksi terisi otomatis' }).first();
    await slow(stale).toBeVisible();
    await expect(stale).toContainText(day.yesterday);
    await expect(stale).toContainText(day.today);
    await expect(stale).toContainText('Pilih tanggal yang benar.');
    await expect(issuing.getByRole('button', { name: 'Ubah' }), 'the picker offered with the conflict').toBeVisible();
    const issues = identities(sentIssue);
    expect(issues, 'the 401 and its resend share one command_id').toHaveLength(1);
    expect(await outcomeOf(page, issues[0] ?? '')).toEqual(expect.objectContaining({ state: 'CONFLICT', outcome_code: 'ST-08' }));

    await guardsClean(page, testInfo, [q.company]);
    await clean(merged(captured, a.captured, b.captured), testInfo);
    await a.context.close();
    await b.context.close();
});

test('CJ-21 on the phone: a client answer "already recorded" by another Admin, double-tapped — one command_id, CONFLICT, no re-apply (UXS-01, UXS-12; criterion 8)', async ({ page, browser }, testInfo) => {
    const captured = watch(page);
    await page.setViewportSize(DESK);
    const run = runId('cj21r', testInfo);
    const q = await quotationRun(page, run, { stage: 'sent' });
    const a = await adminActor(browser, testInfo, page, 'cj21a', q.company, { profile: 'quotation', viewport: PHONE, goLive: goLive() });
    const b = await adminActor(browser, testInfo, page, 'cj21b', q.company, { profile: 'quotation', viewport: DESK, goLive: goLive() });
    const sentA = commandsTo(a.page, `/proyek/${q.project}/penawaran/jawaban`);
    await openQuotation(a.page, q);
    await openQuotation(b.page, q);

    // B records the client's approval at the desk (J-QUO-01), landing on PG-011.
    await b.page.getByRole('button', { name: 'Catat Jawaban Klien' }).first().click();
    await b.page.getByRole('alertdialog').getByRole('button', { name: 'Catat Disetujui' }).click();
    await b.page.waitForURL(`**/proyek/${q.project}`);
    await slow(b.page.getByText('Persetujuan klien dicatat sebagai konfirmasi komersial.')).toBeVisible();

    // A, on the phone with the page read before, double-taps the same answer: one identifier, MSG-04's state, nothing to re-apply.
    await a.page.getByRole('button', { name: 'Catat Jawaban Klien' }).filter({ visible: true }).first().click();
    const answering = a.page.getByRole('alertdialog');
    await answering.getByRole('button', { name: 'Catat Disetujui' }).dblclick();
    const already = answering.locator('[data-answer="CONFLICT"]');
    await slow(already).toContainText('Jawaban klien atas revisi ini sudah dicatat. Muat ulang untuk melihatnya.');
    await expect(already).toContainText('Sudah Tercatat');
    await expect(a.page.getByRole('button', { name: 'Terapkan Ulang pada Versi Terbaru' }), 'no re-apply in this state (SQ-07; UXS-12)').toHaveCount(0);
    await expect(a.page.getByRole('button', { name: 'Coba Lagi' })).toHaveCount(0);
    const answers = identities(sentA);
    expect(answers, 'the double tap sent one command_id (CI-01)').toHaveLength(1);
    expect(await outcomeOf(page, answers[0] ?? '')).toEqual(expect.objectContaining({ state: 'CONFLICT', outcome_code: 'BD-05', actor: a.account.id }));

    await guardsClean(page, testInfo, [q.company]);
    await clean(merged(captured, a.captured, b.captured), testInfo);
    await a.context.close();
    await b.context.close();
});

test('CJ-21 UXH-02: deferred groups are background and extend no session; another account signing in at the dialog uncovers and resends nothing (RB-05; IP-11; criterion 9)', async ({ page, browser }, testInfo) => {
    const captured = watch(page);
    await page.setViewportSize(DESK);
    const run = runId('cj21u', testInfo);
    const q = await quotationRun(page, run, { stage: 'draft' });
    const a = await adminActor(browser, testInfo, page, 'cj21a', q.company, { profile: 'quotation', viewport: DESK, goLive: goLive() });
    // B: the same display name (the account fixture's), the same capabilities and the same company as A.
    const bAccount = await newAccount(page, 'cj21b');
    await grant(page, bAccount, q.company, 'quotation');

    // PG-011's deferred groups held until A's session has aged by half an hour, then let through: each marked background, and the
    // session's last activity unchanged by them; a foreground visit after them extends it (RB-05; RV-06; IP-11).
    const journey = `/proyek/${q.project}`;
    const onJourney = (url: URL): boolean => url.pathname === journey;
    const groups: Record<string, string>[] = [];
    let release: () => void = () => undefined;
    const gate = new Promise<void>((resolve) => {
        release = resolve;
    });
    const holdGroups = async (route: Route): Promise<void> => {
        const headers = await route.request().allHeaders();

        if (headers['x-inertia-partial-data'] !== undefined) {
            groups.push(headers);
            await gate;
        }

        await route.fallback();
    };
    await a.page.route(onJourney, holdGroups);
    await a.page.goto(journey);
    await slow(a.page.getByRole('heading', { level: 1 })).toBeVisible();
    await slow.poll(() => groups.length).toBeGreaterThan(0);
    const aged = await sessionOf(page, a.account, 'age', 1_800);
    release();
    await slow(a.page.locator('[data-region-state="loading"]')).toHaveCount(0);
    await a.page.unroute(onJourney, holdGroups);
    expect(groups.map((headers) => headers['x-background-request']), 'each deferred group is a background request (RB-05)').toEqual(groups.map(() => '1'));
    expect((await sessionOf(page, a.account)).last_activity, 'background requests extend no session (RV-06; H7-13)').toBe(aged.last_activity);
    await openQuotation(a.page, q);
    expect((await sessionOf(page, a.account)).last_activity ?? 0, 'a foreground visit extends it').toBeGreaterThan(aged.last_activity ?? 0);

    // A's unsent input and pending command at the dialog; B signs in there: a full document load that uncovers nothing of A's page
    // and resends nothing of A's (UXH-02; UXS-03).
    const path = `/proyek/${q.project}/penawaran`;
    const sentA = commandsTo(a.page, path);
    const typed = `Isian Admin A ${run}`;
    await description(a.page).fill(typed);
    await idleExpired(page, a.account);
    await a.page.getByRole('button', { name: 'Simpan Draf' }).click();
    await slow(reauthDialog(a.page)).toBeVisible();
    const pendingId = String(sentA[0]?.body.command_id);
    await nothingStored(a.page, [typed, pendingId]);
    const reloaded = a.page.waitForEvent('load', { timeout: 60_000 }).then(() => 'reloaded', () => 'none');
    const resent = a.page.waitForRequest((request) => request.method() === 'POST' && new URL(request.url()).pathname === path, { timeout: 60_000 }).then(() => 'resent', () => 'none');
    await reauthenticate(a.page, bAccount);
    expect(await Promise.race([reloaded, resent]), 'another account\'s sign-in is a full document load, never a resend').toBe('reloaded');
    await slow(a.page.getByRole('heading', { level: 1 })).toHaveText(`Penawaran ${q.number}`);
    await expect(description(a.page), 'nothing of A\'s unsent input is uncovered').not.toHaveValue(typed);
    expect(identities(sentA), 'A\'s command_id never resent under B').toEqual([pendingId]);
    expect(sentA).toHaveLength(1);
    expect(await recorded(page, [pendingId]), 'nothing of A\'s ran under B').toEqual([]);
    expect((await sessionOf(page, bAccount)).sessions, 'the page is B\'s session now').toBe(1);

    await guardsClean(page, testInfo, [q.company]);
    await clean(merged(captured, a.captured), testInfo);
    await a.context.close();
});

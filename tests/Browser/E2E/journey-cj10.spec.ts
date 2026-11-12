import { type Page, test } from '@playwright/test';

import { disarmGoogle, fakeGoogle, landed, signedIn, stepUp, totp } from '../account-fixtures';
import { clean, guardsClean, watch } from '../journey-fixtures';
import {
    adminActor,
    commandsTo,
    DESK,
    expect,
    grant,
    idleExpired,
    merged,
    newAccount,
    nextCode,
    nothingStored,
    PHONE,
    reauthDialog,
    reauthenticate,
    runId,
    signInAt,
    slow,
    tookCode,
} from '../journey-k2-fixtures';
import { numberingCompany } from '../numbering-fixtures';

// TL-09 (TASK-064; CJ-10; J-ACC-01; AC-13; GAP-044; UXS-06, UXS-24; SM-20 — no test calls Google; criteria 2, 12): the sign-in journeys
// against the real routes of PG-001, PG-002, PG-004, PG-065 and PG-060 with synthetic accounts of the run, each an Admin of a company
// made for it — a password with AU-04's one answer, then the code with its own; Google simulated (account-fixtures' `fakeGoogle`), an
// unknown identity answered on PG-001 and creating nothing, a linked one reaching the code step only; the enrolment-only session of a
// high-risk account without TOTP (AU-18); the in-place re-login after the idle limit with the page and its unsent input kept and the
// interrupted request sent again unchanged (IP-11); the session ended by "Keluar", by the idle limit and by the Owner's "Akhiri Sesi",
// Back showing nothing of it (UXS-06); and a deactivated account's retry answered MSG-19 at a dialog it cannot pass (UXS-24). Desktop
// and phone at 390. Each test watches its console and network from its first step and ends with ReconcileGuards clean for its company.

test.describe.configure({ timeout: 300_000 });

test.afterEach(async ({ page }) => {
    await disarmGoogle(page);
});

/** A display name of the run's own, so the Owner's pages find this account among the fixture's namesakes. */
function nameOf(run: string): string {
    return `Rani ${run.replace(/[^0-9]/g, '').slice(-8)}`;
}

/** UXS-06: Back after the session ended shows the sign-in page — the ended session's entry fetched afresh and refused —, never its page. */
async function backShowsNothing(page: Page, name: string): Promise<void> {
    await page.goBack({ waitUntil: 'commit' });
    await slow(page.getByRole('heading', { level: 1 })).toHaveText('Masuk ke MultipleCorp');
    await expect(page.getByText(name)).toHaveCount(0);
}

/** "Keluar" from the re-authentication dialog, through MSG-35, to a full document load of sign-in (IP-11; H7-12). */
async function leaveFromDialog(page: Page): Promise<void> {
    await reauthDialog(page).getByRole('button', { name: 'Keluar' }).click();
    const warning = page.getByRole('alertdialog', { name: 'Isian yang belum dikirim' });
    await expect(warning).toContainText('Isian yang belum dikirim akan hilang.');
    await warning.getByRole('button', { name: 'Keluar' }).click();
    await page.waitForURL('**/masuk');
}

test('CJ-10 at the desk: a password and the code, the in-place re-login keeping PG-065 and its input, then "Keluar" with Back showing nothing (J-ACC-01; AU-04; AU-16; IP-11; UXS-06; criterion 2)', async ({ page }, testInfo) => {
    const captured = watch(page);
    await page.setViewportSize(DESK);
    const run = runId('cj10p', testInfo);
    const name = nameOf(run);
    const company = await numberingCompany(page, null);
    const account = await newAccount(page, 'cj10p');
    await grant(page, account, company, 'view', name);

    // PG-001: a wrong password answered with AU-04's one message, the e-mail kept; then PG-002's code, a wrong one answered too.
    await page.goto('/masuk');
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Masuk ke MultipleCorp');
    await page.getByLabel('E-mail').fill(account.email);
    await page.getByLabel('Kata sandi', { exact: true }).fill('Salah-Sekali-1');
    await page.getByRole('button', { name: 'Masuk', exact: true }).click();
    await expect(page.getByText('E-mail atau kata sandi salah.')).toBeVisible();
    await expect(page.getByLabel('E-mail')).toHaveValue(account.email);
    await page.getByLabel('Kata sandi', { exact: true }).fill(account.password);
    await page.getByRole('button', { name: 'Masuk', exact: true }).click();
    await page.waitForURL('**/masuk/verifikasi');
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Masukkan kode verifikasi');
    await page.getByLabel('Kode 6 digit dari aplikasi autentikator').fill('000000');
    await page.getByRole('button', { name: 'Verifikasi' }).click();
    await expect(page.getByText('Kode tidak cocok atau sudah tidak berlaku. Masukkan kode terbaru dari aplikasi autentikator.')).toBeVisible();
    await page.getByLabel('Kode 6 digit dari aplikasi autentikator').fill(await nextCode(account));
    await page.getByRole('button', { name: 'Verifikasi' }).click();
    await landed(page);
    await expect(page.getByRole('button', { name: /Menu akun/ })).toContainText(name);

    // IP-11 on PG-065: the password change filled in, the idle limit passed; the submission answers 401, the dialog covers the page
    // with the input kept in memory and nowhere else; after the password and the code the same request goes again, once.
    const fresh = `Pelangi-Biru#${run.replace(/[^0-9]/g, '').slice(-6)}`;
    await page.goto('/akun');
    await slow(page.getByRole('heading', { level: 1 })).toHaveText('Akun Saya');
    await page.getByLabel('Kata sandi saat ini').fill(account.password);
    await page.getByLabel(/^Kata sandi baru/).fill(fresh);
    await page.getByLabel(/^Ulangi kata sandi baru/).fill(fresh);
    const changes = commandsTo(page, '/akun/kata-sandi');
    await idleExpired(page, account);
    await page.getByRole('button', { name: 'Ubah Kata Sandi' }).click();
    await slow(reauthDialog(page)).toBeVisible();
    await expect(page.locator('#konten input[autocomplete="current-password"]'), 'the unsent input stays on the page').toHaveValue(account.password);
    await expect(page.locator('#konten input[autocomplete="new-password"]').first()).toHaveValue(fresh);
    await nothingStored(page, [fresh]);
    await reauthenticate(page, account);
    await slow(page.getByText('Kata sandi diubah. Sesi lain akun ini telah diakhiri.')).toBeVisible();
    await expect(page).toHaveURL(/\/akun$/);
    expect(changes, 'the interrupted request and its one resend').toHaveLength(2);
    expect(changes[1]?.body, 'sent again unchanged').toEqual(changes[0]?.body);

    // "Keluar": MSG-35, a full document load of sign-in; Back shows nothing of the ended session (UXS-06; AU-06).
    await page.getByRole('button', { name: /Menu akun/ }).click();
    await page.getByRole('menuitem', { name: 'Keluar' }).click();
    const warning = page.getByRole('alertdialog', { name: 'Isian yang belum dikirim' });
    await expect(warning).toContainText('Isian yang belum dikirim akan hilang. Tetap keluar?');
    await warning.getByRole('button', { name: 'Keluar' }).click();
    await page.waitForURL('**/masuk');
    await backShowsNothing(page, name);

    // The new password signs in, with the next code.
    await signInAt(page, account, fresh);
    await expect(page.getByRole('button', { name: /Menu akun/ })).toContainText(name);

    await guardsClean(page, testInfo, [company]);
    await clean(captured, testInfo);
});

test('CJ-10 on the phone: an unknown Google identity creates nothing, a linked one reaches the code; the idle limit re-logged in place; the Owner ends the session and Back shows nothing (AU-22; IP-11; UXS-06; criterion 2)', async ({ page, browser }, testInfo) => {
    const captured = watch(page);
    await page.setViewportSize(PHONE);
    const ownerContext = await browser.newContext({ baseURL: testInfo.project.use.baseURL, ignoreHTTPSErrors: true, viewport: DESK });
    const owner = await ownerContext.newPage();
    const capturedOwner = watch(owner);
    const ownerAccount = await signedIn(owner, 'cj10-owner', { owner: true });
    const company = await numberingCompany(owner, null);
    const run = runId('cj10g', testInfo);
    const name = nameOf(run);

    // AU-22: an identity no account has linked — PG-001's one generic failure, and nothing created.
    const unknown = `sub-cj10-tak-${String(Date.now())}`;
    await fakeGoogle(page, unknown);
    await page.goto('/masuk');
    await page.getByRole('link', { name: 'Lanjutkan dengan Google' }).click();
    await slow(page.getByRole('alert')).toHaveText('Tidak dapat masuk dengan Google. Masuk dengan e-mail dan kata sandi.');
    await expect(page.getByLabel('E-mail')).toBeVisible();
    const left = (await (await page.request.get(`/__fixtures/k2/google?sub=${unknown}`)).json()) as { identities: number; accounts: number };
    expect(left, 'an unknown Google identity creates nothing (AU-22)').toEqual({ identities: 0, accounts: 0 });

    // A linked identity: past Google to the code step only — no recovery code after Google (AU-19) —, then landed.
    const subject = `sub-cj10-${String(Date.now())}`;
    const account = await newAccount(owner, 'cj10g', { google: subject });
    await grant(owner, account, company, 'view', name);
    await page.request.post('/__fixtures/google', { data: { sub: subject } });
    await page.getByRole('link', { name: 'Lanjutkan dengan Google' }).click();
    await page.waitForURL('**/masuk/verifikasi');
    await expect(page.getByRole('button', { name: 'Gunakan kode pemulihan' })).toHaveCount(0);
    await page.getByLabel('Kode 6 digit dari aplikasi autentikator').fill(await nextCode(account));
    await page.getByRole('button', { name: 'Verifikasi' }).click();
    await landed(page);
    const bar = page.getByRole('navigation', { name: 'Navigasi utama' }).last();

    // The idle limit passed: the next visit answers 401 and the page is covered; the password and the code resume it in place.
    await idleExpired(owner, account);
    await bar.getByRole('link', { name: 'Proyek' }).click();
    await reauthenticate(page, account);
    await page.waitForURL('**/proyek');
    await slow(page.getByRole('heading', { level: 1 })).toHaveText('Proyek');
    await slow(reauthDialog(page)).toHaveCount(0);

    // Remote termination: the Owner ends the session on PG-060 after the step-up (ROUTE-130; AU-15).
    await owner.goto(`/pengaturan/pengguna?akun=${account.id}&bagian=profil`);
    await slow(owner.getByRole('heading', { level: 1 })).toHaveText(name);
    await owner.getByRole('tab', { name: 'Sesi' }).click();
    await owner.getByRole('button', { name: 'Akhiri Sesi' }).first().click();
    await owner.getByRole('alertdialog').getByRole('button', { name: 'Akhiri Sesi', exact: true }).click();
    await stepUp(owner, ownerAccount.password);
    await slow(owner.getByText('Sesi diakhiri.').first()).toBeVisible();

    // The phone's next visit: the opaque dialog over the page (UXS-06); "Keluar" from it; Back shows nothing of the ended session.
    await bar.getByRole('link', { name: 'Proyek' }).click();
    await slow(reauthDialog(page)).toBeVisible();
    await leaveFromDialog(page);
    await backShowsNothing(page, name);

    // LG-02 as the Owner's PG-064 shows it: the account's sign-in recorded (its event content is TASK-010's).
    await owner.goto('/aktivitas/log-keamanan?jenis=LOGIN_SUCCEEDED');
    await expect.soft(owner.getByRole('table', { name: 'Log Keamanan', exact: true }).getByRole('row').filter({ hasText: name }).first(), 'the sign-in in the Owner\'s security log (LG-02)').toBeVisible();

    await guardsClean(owner, testInfo, [company]);
    await clean(merged(captured, capturedOwner), testInfo);
    await ownerContext.close();
});

test('CJ-10 at the desk: a high-risk account enrols in its enrolment-only session; a deactivated Admin\'s retry is refused at a dialog it cannot pass (AU-18; UXS-24; criterion 2)', async ({ page, browser }, testInfo) => {
    const captured = watch(page);
    await page.setViewportSize(DESK);
    const run = runId('cj10e', testInfo);
    const name = nameOf(run);

    // AU-18: an Owner without TOTP signs in to the enrolment-only session, enrols, confirms a code and sees the recovery codes once.
    const owner = await newAccount(page, 'cj10e', { kind: 'owner' });
    await page.goto('/masuk');
    await page.getByLabel('E-mail').fill(owner.email);
    await page.getByLabel('Kata sandi', { exact: true }).fill(owner.password);
    await page.getByRole('button', { name: 'Masuk', exact: true }).click();
    await page.waitForURL('**/verifikasi-dua-langkah');
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Aktifkan Verifikasi Dua Langkah');
    await page.goto('/proyek');
    await page.waitForURL('**/verifikasi-dua-langkah');
    await expect(page.getByRole('img', { name: 'Atur aplikasi autentikator' })).toBeVisible();
    const key = (await page.locator('code').innerText()).replace(/\s+/g, '');
    expect(key).toMatch(/^[A-Z2-7]{32}$/);
    await page.getByLabel('Kode 6 digit dari aplikasi autentikator').fill(totp(key));
    owner.secret = key;
    tookCode(owner);
    await page.getByRole('button', { name: 'Aktifkan' }).click();
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Kode pemulihan');
    await expect(page.getByRole('list', { name: 'Kode pemulihan' }).getByRole('listitem')).toHaveCount(8);
    await nothingStored(page, [key]);
    await page.getByRole('button', { name: 'Lanjutkan' }).click();
    await landed(page);

    // An Admin of the run's company at work in a browser of its own.
    const company = await numberingCompany(page, null);
    const admin = await adminActor(browser, testInfo, page, 'cj10d', company, { profile: 'view', viewport: DESK, name });
    await admin.page.goto('/proyek');
    await slow(admin.page.getByRole('heading', { level: 1 })).toHaveText('Proyek');

    // The Owner deactivates it on PG-060 after the step-up (ROUTE-123; RV-05).
    await page.goto(`/pengaturan/pengguna?akun=${admin.account.id}&bagian=profil`);
    await slow(page.getByRole('heading', { level: 1 })).toHaveText(name);
    await page.getByRole('button', { name: 'Nonaktifkan' }).click();
    await page.getByRole('alertdialog').getByRole('button', { name: 'Nonaktifkan', exact: true }).click();
    await stepUp(page, owner.password);
    await slow(page.getByText('Akun dinonaktifkan.', { exact: false }).first()).toBeVisible();

    // UXS-24: its next visit is unauthenticated; its own credentials at the dialog answer MSG-19 — nothing of the account's state —,
    // no code step, and the dialog cannot be passed; "Keluar" leaves; Back shows nothing.
    await admin.page.locator('a[href="/proyek"]:visible').first().click();
    const dialog = reauthDialog(admin.page);
    await slow(dialog).toBeVisible();
    await dialog.getByLabel('E-mail').fill(admin.account.email);
    await dialog.getByLabel('Kata sandi', { exact: true }).fill(admin.account.password);
    await dialog.getByRole('button', { name: 'Lanjutkan' }).click();
    await slow(dialog).toContainText('E-mail atau kata sandi salah.');
    await expect(dialog.getByLabel('Kode 6 digit dari aplikasi autentikator')).toHaveCount(0);
    await expect(dialog).toBeVisible();
    await leaveFromDialog(admin.page);
    await backShowsNothing(admin.page, name);

    await guardsClean(page, testInfo, [company]);
    await clean(merged(captured, admin.captured), testInfo);
    await admin.context.close();
});

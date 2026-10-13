# Authentication journeys

Status: DRAFT | Updated: 2026-10-14 | Owner: Planning

**Draft — not normative.** This is an S2-1 document of Stage 2 of the P7 UX re-baseline, written under [DIR-061](../../00-governance/DECISION_LOG.md#dir-061-dir-062-obs-023-and-tech-031--p7-ux-re-baseline-stage-2-decisions-s2-0-authorization-baseline-and-prototype-setup) SD-01, SD-05 and SD-10 and [DIR-064](../../00-governance/DECISION_LOG.md#dir-063-dir-064-obs-024-and-tech-032--p7-ux-re-baseline-s2-0-acceptance-s2-1-authorization-baseline-and-stage-2-documents) §12.5 (BP-4). Behaviour follows MULTIPLECORP — P0–P6 and ADMIN_FLOW —, structure follows R1, and presentation follows the Golden Frontend Reference v1.0.0, which adapts to MULTIPLECORP and never the reverse (AK-1; SD-02). [INFORMATION_ARCHITECTURE](../INFORMATION_ARCHITECTURE.md) and [DESIGN_SYSTEM](../DESIGN_SYSTEM.md) stay APPROVED under APPR-008 and under replacement, and [ADMIN_FLOW](../ADMIN_FLOW/README.md) stays in force, until a later authorized stage changes them; Stage 3 uses this draft, and nothing in it is normative.

**Sources.** [SECURITY](../../05-security/SECURITY.md) — AU-01–AU-25 of its authentication controls, LG-07's alerts and the handoffs H7-01–H7-19, H8-02, H8-17, H8-20, H8-22, H9-06, H9-07, H9-10 and H9-13; ADMIN_FLOW [J-ACC-01 and J-ACC-02](../ADMIN_FLOW/s10-a-journeys.md#101-access-and-master-data), [IP-10](../ADMIN_FLOW/s05-command-model.md#ip-10--step-up-confirmation), [IP-11](../ADMIN_FLOW/s06-09-session-forms-lists.md#ip-11--session-limits-expiry-and-re-authentication) and the messages MSG-11, MSG-19, MSG-20, MSG-27–MSG-29 and MSG-33–MSG-35 of [§14](../ADMIN_FLOW/s14-15-messages-scenarios.md#14-message-catalogue); [GAP-044](../../00-governance/GAP_REGISTER.md#gap-044--admin_flows-login-and-session-journeys-predate-totp-and-google-sign-in), [GAP-045](../../00-governance/GAP_REGISTER.md#gap-045--anyone-who-can-reach-an-accounts-second-factor-step-can-delay-its-sign-in-through-the-cooldown) and [GAP-047](../../00-governance/GAP_REGISTER.md#gap-047--one-phone-holding-both-the-persons-google-session-and-the-authenticator); R1 [k)](../evidence/P7_REBASELINE_GATE.md#k-language-and-authentication) with the sign-in copy the gate record recorded; GR-12 and GR-13; DIR-053 C-1; [IA_PAGE_INVENTORY](IA_PAGE_INVENTORY.md) PG-001–PG-005, PG-060 and PG-065 and its §7. The framework mechanics SECURITY names — Fortify, Socialite, the session store — are SECURITY's statements, re-verified at implementation (H8-22; GAP-043); this document states no technology behaviour of its own.

## 1. Rules every journey keeps

- **One sequence, none shorter.** Every way of establishing a session — the sign-in page, the re-login after an expiry and Google sign-in — runs [AU-04](../../05-security/SECURITY.md#2-authentication-sessions-and-credentials)'s sequence: a correct password, or a verified Google sign-in, then the second factor; a high-risk account without active TOTP gets only the enrolment-only session (AU-04; AU-16; AU-18; AU-22). Where [ADMIN_FLOW](../ADMIN_FLOW/README.md#admin-and-owner-flows--interaction-model) predates the amendment — IP-11's re-login dialog and J-ACC-01's password wording — SECURITY governs ([GAP-044](../../00-governance/GAP_REGISTER.md#triage); H7-19).
- **All three personas are high-risk.** Laras holds the OWNER role, Dimas and Bayu ADM_PLUS grants and `finance.view`, `cost.view` or `evidence.view`, so each signs in with TOTP (AU-18; IA_PAGE_INVENTORY §1).
- **Generic answers.** An unknown account, a wrong password and an inactive account get the same message, status and timing — MSG-19 "E-mail atau kata sandi salah." —; a wrong, reused or stale code, a non-matching recovery code and an expired or voided challenge get one generic answer; a failed Google sign-in one generic message (AU-04; AU-22; H7-01).
- **Throttling without lockout.** Per identifier and address and per address, with doubling cooldowns; the account-wide second-factor limit — the fifth failed code within 15 minutes starts a cooldown of 1, 2, 4, 8 and then 15 minutes, never a permanent lockout —, its refusals answered as a throttled login, MSG-20 (AU-05; GAP-045, ACCEPTED_RISK).
- **Authentication boundaries clear history.** The sign-in page, the challenge and enrolment pages, the Google return, the first response after sign-in, every expiry and termination and the credential pages clear history and the client caches, or reload the whole document (AU-06; H7-12).
- **Secrets shown once.** A credential link, a QR code with its text key, and recovery codes are shown once as non-persisted flash data, never kept in a page or in history (AU-12; AU-16; AU-19).
- **No new rule.** P5 semantics are unchanged; where SECURITY gives no rule, this document says "needs definition — not proposed" (§4).
- **Copy.** Mandated copy is quoted exactly — the MSG texts, AU-04's answer, H7-16's advice —; copy [the gate record](../evidence/P7_REBASELINE_GATE.md#p7-ux-re-baseline--evidence-and-gates) recorded for R1 is quoted from there; any other copy is **proposed**, in Indonesian with the GL terms and within [OC-06](../../00-governance/DECISION_LOG.md#dir-055-dir-056-obs-020-and-tech-028--p7-ux-re-baseline-checkpoint-c1-decision-rework-authorization-baseline-and-rework), for the Owner's validation at the preview, its English renderings in [S2-J01](journeys/S2-J01.md)'s copy section (DIR-064 §11.3).

## 2. The journeys

Each journey lists its steps — page, what the person does, the rule, the answer and the next page. "Simulated" marks what the prototype only imitates ([SIMULATION_REGISTER](SIMULATION_REGISTER.md)); the application's tests are named in §3.

### 2.1 Sign-in with a password, then TOTP

| Step | Page | The person | Rule | Answer | Next |
| --- | --- | --- | --- | --- | --- |
| 1 | PG-001 | sees "Masuk ke MultipleCorp", *E-mail*, *Kata sandi* with "Tampilkan", "Masuk", the disclosure "Lupa kata sandi?" — its answer "Minta tautan atur ulang kepada Owner." folded —, "atau", "Lanjutkan dengan Google", [H7-16](../../05-security/SECURITY.md#p7--presentation-only)'s advice and the switch "ID \| EN" (R1 k)) | no self-registration and no self-service reset in V1 (AU-01; AU-12; AU-17); paste and password managers allowed (H7-04) | — | — |
| 2 | PG-001 | enters the e-mail and password, "Masuk" | AU-04; the throttles of AU-05 | [MSG-19](../ADMIN_FLOW/s14-15-messages-scenarios.md#14-message-catalogue) for any failure; MSG-20 while a throttle runs | step 3 |
| 3 | PG-002 | an account with active TOTP: "Masukkan kode verifikasi", the label "Kode 6 digit dari aplikasi autentikator", "Verifikasi", and "Gunakan kode pemulihan" after a password (R1 k)) | the pending challenge, five minutes, nothing else reachable (AU-16); the session identifier regenerated at the password step (AU-06) | — | — |
| 4 | PG-002 | enters the code | a code at a later time step only, once (AU-16); the second-factor limit (AU-05) | the generic code answer, recorded copy "Kode tidak cocok atau sudah tidak berlaku. Masukkan kode terbaru dari aplikasi autentikator." ([the gate record](../evidence/P7_REBASELINE_GATE.md#p7-ux-re-baseline--evidence-and-gates)'s Stage 1 direction e), kept by R1 k)); MSG-20 in a cooldown | step 5 |
| 5 | the landing page | is signed in — PG-006 for the Owner, PG-007 for an Admin | LOGIN_SUCCEEDED at the challenge's success; the identifier regenerated; history cleared (AU-04; AU-06) | MSG-29 on the first page after a credential event (AU-13; H7-14; H7-18) | — |

A high-risk account without active TOTP goes from step 2 to the enrolment-only session (§2.4, steps 4–7). **Simulated** in S2-J01 (SIM-01; SIM-02): the accounts, the password check and the code check, with fixed synthetic values (SS-01; SS-02).

### 2.2 Google, then TOTP

Google sign-in is for an existing account with active TOTP only — EXISTING_ACCOUNT_ONLY: no registration, no automatic linking (AU-22; D6; K2).

| Step | Page | The person | Rule | Answer | Next |
| --- | --- | --- | --- | --- | --- |
| 1 | PG-001 | "Lanjutkan dengan Google" | the OpenID Connect code flow, `prompt=select_account` every time, scopes `openid email`, no Google script on any page (AU-22) | — | Google's account chooser |
| 2 | Google | chooses the account | — | — | the return to MULTIPLECORP |
| 3 | the return | — | the account found only by the Google subject linked to it; an unknown or unlinked subject, an inactive account, one without active TOTP and one in recovery all fail alike (AU-22; AU-20 b) | one generic message — copy not recorded, **proposed**: "Tidak dapat masuk dengan Google. Masuk dengan e-mail dan kata sandi." —; password sign-in stays available (AU-24) | PG-002 |
| 4 | PG-002 | the code only — no recovery code after Google — with the disclosure "Tidak bisa memakai aplikasi?", its answer "Masuk dengan kata sandi untuk memakai kode pemulihan." (R1 k); the gate record's measured checks) | the challenge with purpose GOOGLE (AU-16); a Google sign-in alone never establishes a session (K2) | the generic code answer; MSG-20 | the landing page |

H7-16's advice stays on PG-001: "Di perangkat bersama, keluar dari Google setelah selesai; kunci ponsel saat tidak dipakai." (R1 g); [GAP-047](../../00-governance/GAP_REGISTER.md#triage), ACCEPTED_RISK). The Google button's drawing follows GR-13 in the application and the Owner's answer for the prototype ([PROTOTYPE_PLAN](PROTOTYPE_PLAN.md); the Owner's question on it in [OWNER_QUESTIONS](OWNER_QUESTIONS.md)). **Simulated** in S2-J01 (SIM-03): Google's chooser and return, with no OAuth and no outbound request — the button leads to the code step (SS-03; SS-05).

### 2.3 A recovery code — the device is lost

| Step | Page | The person | Rule | Answer | Next |
| --- | --- | --- | --- | --- | --- |
| 1 | PG-001 → PG-002 | signs in with the password, then "Gunakan kode pemulihan" | a recovery code only after a correct password, never after Google (AU-16; AU-19) | — | — |
| 2 | PG-002 | enters one recovery code | found and removed under the account row lock — a code works once (AU-19); the second-factor limit (AU-05) | the generic code answer; MSG-20 | step 3 |
| 3 | PG-004 | is in the enrolment-only session, which can only enrol a new device; the account is *in recovery* and Google sign-in is not eligible until the new device is confirmed (AU-20 b) | RECOVERY_CODE_USED; the Owner alerted for a high-risk account (AU-19; LG-07) | — | §2.4 steps 5–7 |

The proposed copy of PG-004 for this path states that a new device must be set up before work continues — "Atur ulang aplikasi autentikator untuk melanjutkan." (**proposed**, H7-15). **Simulated** in S2-J01 (SIM-02): one recovery code accepted; the enrolment that follows is shown as far as S2-J01's screen list goes.

### 2.4 The credential link — set the password, sign in, enrol

The proposed resolution of [SCR-02](../INFORMATION_ARCHITECTURE.md#81-system-account-and-beranda)'s DECIDE-IN-STAGE-2 item ([IA_PAGE_INVENTORY §7.1](IA_PAGE_INVENTORY.md#71-scr-02--the-credential-link-and-the-second-factor-enrolment)): one continuous journey across three pages, because "a credential link never creates a session and never bypasses TOTP" (AU-20).

| Step | Page | The person | Rule | Answer | Next |
| --- | --- | --- | --- | --- | --- |
| 1 | PG-003 | opens the link the Owner delivered out of band | the token in the address fragment, removed from the address bar and history on load, never kept in page state; `Referrer-Policy: no-referrer`, `Cache-Control: no-store` (AU-12; AU-11) | MSG-28 "Tautan tidak berlaku. Minta tautan baru kepada Owner." for an invalid, expired, used or revoked link (AU-12) | — |
| 2 | PG-003 | sets a password under the rules shown — 8 to 128 characters with a lowercase and an uppercase letter, a digit and a symbol; a common password refused, and a breached one whenever the range service answers (GAP-046) — with "Tampilkan" and paste allowed | AU-03, profile AICWDF-COMPAT-8; H7-04 | the refusal in words beside the field (IP-14) | step 3 |
| 3 | PG-001 | is told the password is set and signs in with it | the link sets the password and creates no session (AU-20); the account's other sessions end (AU-09) | the proposed line "Kata sandi tersimpan. Masuk untuk melanjutkan." (**proposed**, H7-04) | step 4 |
| 4 | PG-001 → PG-004 | a high-risk account without active TOTP | the enrolment-only session reaches only the enrolment, the password confirmation and logout (AU-18) | — | step 5 |
| 5 | PG-004 | scans the QR code, or types the text key, into an authenticator app | a new 32-character secret held in the session for at most 15 minutes; the QR code and key shown only during the enrolment (AU-16) | — | — |
| 6 | PG-004 | enters one code to confirm | one correct code confirms; the secret, eight recovery codes and the time are written together (AU-16; AU-19); after 15 minutes the password is confirmed again (H7-15; AU-15) | the generic code answer | step 7 |
| 7 | PG-004 | sees the eight recovery codes once, with an instruction to keep them — **proposed** "Simpan kode pemulihan ini. Kode hanya ditampilkan sekali." (H7-15; AU-19) — and continues | TOTP_ENROLLED alerts the Owner for a high-risk account (LG-07) | — | the landing page |

**Not linked in the prototype:** the credential-link journey is outside S2-J01's scope ([SD-05](../../00-governance/DECISION_LOG.md#dir-061-dir-062-obs-023-and-tech-031--p7-ux-re-baseline-stage-2-decisions-s2-0-authorization-baseline-and-prototype-setup); IA_PAGE_INVENTORY §5.1); it is designed here for Stage 3 and P8 (H8-17).

### 2.5 The re-login after an expiry — D-UX-03's redesign, proposed

D-UX-03 is DECIDE-IN-STAGE-2 (DIR-064 §4). The proposal keeps its purpose — the page is not left, so unsent input survives in memory and nowhere else (IP-11; H7-05; WS-07) — and makes the dialog run AU-04's whole sequence; Stage 3 aligns ADMIN_FLOW's IP-11 and MSG-11.

| Step | Where | The person | Rule | Answer | Next |
| --- | --- | --- | --- | --- | --- |
| 1 | any page, a 401 or 419 answer | the page is covered by an opaque layer; history and caches cleared at once (H7-12) | IP-11; AU-06 | MSG-11 "Sesi Anda telah berakhir. Masukkan e-mail dan kata sandi untuk melanjutkan. Isian Anda di halaman ini tidak hilang." | step 2 |
| 2 | the dialog | enters the e-mail and password | AU-04 | MSG-19; MSG-20 | step 3 |
| 3 | the dialog | an account with active TOTP: the code step in the same dialog — the TOTP code, or "Gunakan kode pemulihan" | AU-16 — none shorter than the sign-in page (AU-04; H7-19) | the generic code answer; MSG-20 | step 4, or step 5 for a recovery code |
| 4 | the page | the same account — display name, capabilities and granted companies unchanged — continues: an unsent form is sent as a new intent; an unknown outcome shows MSG-07 with "Coba Lagi" on the same `command_id` | IP-11's same-account check; a different account is a full document load | — | the page |
| 5 | the dialog | a recovery code was used, or the account is high-risk without active TOTP: the session is enrolment-only and cannot resume the page | AU-18; AU-20 b | MSG-35's warning that unsent input will be lost, then a full document load | PG-004 |
| 6 | the dialog | "Lanjutkan dengan Google" — Google leaves the page | AU-22 | MSG-35's warning first, then a full document load | Google, then PG-002 |
| 7 | the dialog | "Keluar", or another person at the device | IP-11 | MSG-35, then a full document load | PG-001 |

The dialog is a focused task: Esc never closes it ([KB-04](../DESIGN_SYSTEM.md#131-keyboard)), and it stays above any open dialog (PT-16). **Simulated** in the prototype only as far as S2-J01's screen list reaches; the expiry itself is a scripted state (SIM-04; [SIMULATION_REGISTER](SIMULATION_REGISTER.md)).

### 2.6 Session expiry and the idle warning

| Step | Where | The person | Rule | Answer | Next |
| --- | --- | --- | --- | --- | --- |
| 1 | any page | five minutes before the 60-minute idle limit sees the dialog with the remaining time and "Tetap Masuk" | idle means no user-initiated request — polls and refreshes never extend a session (AU-07; H7-13); usable any number of times within the absolute limit (WCAG 2.2.1) | MSG-33 "Sesi akan berakhir dalam {n} menit karena tidak ada aktivitas." | "Tetap Masuk" extends; otherwise §2.5 |
| 2 | the notice strip | fifteen and five minutes before the 12-hour absolute limit | AU-07; no "remember me" (AU-08) | MSG-34 "Sesi berakhir pukul {jam} dan tidak dapat diperpanjang. Kirim isian Anda sebelum itu; isian yang belum dikirim akan hilang." | — |
| 3 | — | the absolute limit passes | AU-07 | a full document load, history cleared | PG-001 |

### 2.7 Logout

| Step | Where | The person | Rule | Answer | Next |
| --- | --- | --- | --- | --- | --- |
| 1 | the account menu | *Keluar* | logout ends the session, regenerates the request-forgery token and clears history (AU-06) | with unsent input, MSG-35 "Isian yang belum dikirim akan hilang. Tetap keluar?" first; without it nothing is asked (GR-09 as DIR-061 SD-02 corrects it) | PG-001, a full document load |

### 2.8 The generic, cooldown and throttling answers

| Situation | Rule | Answer |
| --- | --- | --- |
| Unknown account, wrong password, inactive account | AU-04 | MSG-19 "E-mail atau kata sandi salah." — same status and timing |
| Wrong, reused or stale code; a non-matching recovery code; an expired or voided challenge | AU-04; AU-16 | the generic code answer, as recorded in §2.1 |
| A failed Google sign-in, for any reason | AU-22 | one generic message (§2.2, proposed copy); password sign-in available |
| A throttle on the sign-in, per identifier and address or per address | AU-05 | MSG-20 "Terlalu banyak percobaan. Coba lagi dalam {n} menit." — never which key |
| The account-wide second-factor cooldown | AU-05 | the throttled answer, MSG-20, the code neither checked nor consumed |
| A credential link that cannot be used | AU-12 | MSG-28 "Tautan tidak berlaku. Minta tautan baru kepada Owner." |
| The limiter store unavailable | AU-05; WS-12 | every covered step refused — fails closed; the same throttled answer |

### 2.9 *Akun Saya* — the account's self-service

The proposed resolution of SCR-03's item ([IA_PAGE_INVENTORY §7.2](IA_PAGE_INVENTORY.md#72-scr-03--akun-saya)): one page, PG-065, with sections; each sensitive action behind the step-up (AU-15; WFL-09).

| Section | What it holds | Rule |
| --- | --- | --- |
| *Profil dan akses* | the account's profile, role, effective capabilities and companies, read-only | AU-13; [PJ-17](../../05-security/PERMISSIONS_MATRIX.md#73-special-projections) |
| *Kata sandi* | "Ubah Kata Sandi" — the current password required; the new one under AU-03; the account's other sessions end | AU-13; AU-09 |
| *Verifikasi dua langkah* | the TOTP status and enrolment time; enrol, for an account that is not high-risk and has none; replace the device — an active session, the step-up and a current code, the old secret valid until the new one is confirmed; disable, offered only to an account that is not high-risk, with the step-up and a current code, which also unlinks Google | AU-16; AU-20 a, f; H7-15 |
| *Kode pemulihan* | view — the step-up and a current code; regenerate — the same, all eight replaced; shown once as flash data; the Owner alerted for a high-risk account | AU-19; LG-07 |
| *Google* | link — from a session established with TOTP: Google's chooser, then a confirming request with the step-up and a current code; the linked e-mail and the link date shown read-only; unlink — the step-up, the account's other sessions ending | AU-22; AU-23; AU-24; H7-16 |
| *Aktivitas akun* | the last successful sign-in and the account's own credential and factor events — link issued and by whom, link used, password set or changed, TOTP enrolled, replaced, reset or disabled, credentials voided, recovery codes used, viewed or regenerated, a cooldown started, Google linked or unlinked | AU-13; H7-14; H7-18 |

The first-login notice after any such event — MSG-29 and "Jika bukan Anda, segera beri tahu Owner." — heads the landing page (AU-13; H7-18). AU-13 lists no language setting: the switch sits in the account menu under C-1 and R1 k), and whether a per-account preference is kept is [LOCALIZATION_ARCHITECTURE](LOCALIZATION_ARCHITECTURE.md)'s question.

### 2.10 The Owner's account screens and the security notices

The proposed resolution of SCR-57's item ([IA_PAGE_INVENTORY §7.5](IA_PAGE_INVENTORY.md#75-scr-57--pengguna--akses-the-owners-account-screens)), on PG-060's account detail; never on the acting Owner's own account (OD-01):

- each account's TOTP status and enrolment time; "reset TOTP" — for an account without TOTP, a credential reissue — with the step-up, a mandatory reason and one RESET link shown once for out-of-band delivery, the account's password made unusable and its Google identity unlinked (AU-20 c, d; AU-12; AU-15);
- the RESET link that a role assignment, a grant or an activation issues when it makes an account without confirmed TOTP high-risk, shown once to the acting Owner (AU-18; AU-12);
- the Google unlink with its reason, every session of the account ending (AU-24, OWNER); the account's Google link history — never a secret, a code or a provider subject (PJ-17; H7-17).

**Security notices on *Beranda*** head the Owner's decision group: MSG-29's Owner variant when a link the Owner issued is used (AU-12), and LG-07's single security events — an OWNER_RECOVERY_LINK_ISSUED, an AUTHORIZATION_ANOMALY, a successful Owner sign-in from an address not seen for it in 30 days —, each as its neutral type, its time and a link to PG-064, nothing marked read (INFORMATION_ARCHITECTURE §9; LG-07). LG-07's other alerts reach the Owner as P9 delivers them (H9-10; H9-06).

## 3. Trace, simulation and what stays for P8 and P9

| Journey | Rules | Messages | Pages | In the prototype | P8 and P9 |
| --- | --- | --- | --- | --- | --- |
| §2.1 Password, then TOTP | [AU-03](../../05-security/SECURITY.md#2-authentication-sessions-and-credentials), AU-04, AU-05, AU-06, AU-16, AU-18 | [MSG-19](../ADMIN_FLOW/s14-15-messages-scenarios.md#14-message-catalogue), MSG-20, MSG-29; the generic code answer | PG-001, PG-002, the landing page | S2-J01, simulated checks | H8-17; H8-02, H8-22 |
| §2.2 Google, then TOTP | AU-16, AU-20 b, AU-22, AU-24; [GAP-047](../../00-governance/GAP_REGISTER.md#triage) | the Google generic message; the code answer; MSG-20 | PG-001, Google, PG-002 | S2-J01, no OAuth ([SS-03](../evidence/P7_REBASELINE_GATE.md#stage-2--owner-decisions-dir-061); SS-05) | H8-20, H8-22; the OAuth client P9 (H9-13; GAP-042) |
| §2.3 A recovery code | AU-05, AU-16, AU-19, AU-20 b | the code answer; MSG-20 | PG-001, PG-002, PG-004 | S2-J01, one code | H8-17 |
| §2.4 The credential link | AU-03, AU-09, AU-11, AU-12, AU-15, AU-16, AU-18, AU-19, AU-20 | MSG-28 | PG-003, PG-001, PG-004 | not linked | H8-17; the link's delivery out of band ([DEP-08](../../01-product/V1_SCOPE.md#dependencies-and-evidence-needed)) |
| §2.5 The re-login | AU-04, AU-06, AU-16, AU-18, AU-20 b, AU-22; IP-11 | MSG-11, MSG-19, MSG-20, MSG-35, MSG-07 | the dialog over any page; PG-004; PG-001 | a scripted state, as S2-J01 lists it | H8-17 (the in-place re-login reaching the challenge); UXH-02 |
| §2.6 Expiry and the idle warning | AU-07, AU-08; H7-05, H7-13 | MSG-33, MSG-34 | any page; PG-001 | a scripted state | H8-17; UXH-02 |
| §2.7 Logout | AU-06; MSG-35 | MSG-35 | PG-001 | S2-J01 — with and without unsent input | H8-17 |
| §2.8 Answers | AU-04, AU-05, AU-12, AU-22; WS-12 | MSG-19, MSG-20, MSG-28 | PG-001, PG-002, PG-003 | S2-J01, the generic answers | H8-17; GAP-045's accepted exposure |
| §2.9 *Akun Saya* | AU-13, AU-15, AU-16, AU-19, AU-20 a and f, AU-23, AU-24; [PJ-17](../../05-security/PERMISSIONS_MATRIX.md#73-special-projections) | MSG-27, MSG-29 | PG-065 | the out-of-scope page | H8-17, H8-20 |
| §2.10 The Owner's account screens; the notices | AU-12, AU-18, AU-20 c and d, AU-24; LG-07; OD-01; H7-17 | MSG-29's Owner variant | PG-060, PG-006, PG-064 | the out-of-scope page; the notice on PG-006 as data of S2-J02 | H8-17; alert delivery P9 (H9-10; H9-06); the recovery runbook P9 (H9-07) |

Every simulated step is listed in [SIMULATION_REGISTER](SIMULATION_REGISTER.md) and every S2-J01 step in [S2-J01](journeys/S2-J01.md)'s trace matrix. Nothing here changes a SECURITY control; P8 tests the journeys as SECURITY's handoffs state them.

## 4. Needs definition — not proposed

[SECURITY](../../05-security/SECURITY.md#security-architecture) gives a rule for every step of §2; what it does not give is copy, which the journeys draft as **proposed** for the Owner's validation, never as a rule:

- the Google generic failure message (§2.2) — AU-22 fixes that it is one and reveals nothing, not its words;
- the line after a password is set through a link (§2.4 step 3), the instruction with the recovery codes (§2.4 step 7) and the line of the enrolment after a recovery code (§2.3);
- the headings and labels of *Akun Saya*'s sections (§2.9).

No rule, figure or business meaning is added by them ([DIR-064](../../00-governance/DECISION_LOG.md#dir-063-dir-064-obs-024-and-tech-032--p7-ux-re-baseline-s2-0-acceptance-s2-1-authorization-baseline-and-stage-2-documents) §11.3). The language preference before and after sign-in is C-1's and [LOCALIZATION_ARCHITECTURE](LOCALIZATION_ARCHITECTURE.md)'s, not this document's.

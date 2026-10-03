# P5 authentication amendment — verification, review and gate

Status: REVIEW | Updated: 2026-10-04 | Owner: Planning

The evidence record of the P5 security and authentication amendment authorized by the Owner under [DIR-043](../../00-governance/DECISION_LOG.md#dir-042-dir-043-obs-016-and-tech-025--p5-authentication-amendment-directives-baseline-and-amendment), continued under [DIR-045](../../00-governance/DECISION_LOG.md#dir-044-dir-045-risk-006-risk-007-and-tech-025--p5-authentication-amendment-continuation-after-the-adversarial-review), resumed in a new session under [DIR-046](../../00-governance/DECISION_LOG.md#dir-046-and-tech-025--p5-authentication-amendment-resume-in-a-new-session) and continued again under [DIR-048](../../00-governance/DECISION_LOG.md#dir-047-dir-048-risk-006-and-tech-025--p5-authentication-amendment-second-continuation-after-review-round-2) and [DIR-050](../../00-governance/DECISION_LOG.md#dir-049-dir-050-risk-005-and-tech-025--p5-authentication-amendment-third-continuation-after-review-round-4), which applies the Owner's decisions D5 and D6 (DIR-037), K1–K4 (DIR-042) and the Owner's decisions on its first, second and fourth adversarial reviews (DIR-044, DIR-047, DIR-049). It records the external verification of DIR-043 §6.3, the Level-1 adjustments that verification required, the amendments made, every round of the adversarial review of DIR-043 §9 — rounds 1 to 3, the focused round 4, the final focused round 5 and the focused rounds 6 and 7 that the convergence rule of DIR-050 §2 required — with every disposition and every carried finding, and the gate of DIR-043 §10, with the additions of DIR-045, DIR-046, DIR-048 and DIR-050, and its zero-context check. It is evidence, not a specification: the rules live in the documents it names, and the hashes of every amended file are under [TECH-025](../../00-governance/DECISION_LOG.md#dir-042-dir-043-obs-016-and-tech-025--p5-authentication-amendment-directives-baseline-and-amendment). The amendment is PENDING OWNER APPROVAL; nothing here approves it.

## Baseline

Verified before any change and recorded as [OBS-016](../../00-governance/DECISION_LOG.md#dir-042-dir-043-obs-016-and-tech-025--p5-authentication-amendment-directives-baseline-and-amendment): local `main`, `origin/main` and live `main` at `c26f31e9b053689b07a29877fe1016c6b8103e37`, the published migration-confirmation commit; no branch `amendment/p5-auth` locally or on the remote; a clean tree and index; all 38 source records equal to their recorded SHA-256, each working copy equal to its blob. The Owner confirmed in session that the authorization be executed in full, exactly as written. The branch `amendment/p5-auth` was created from `c26f31e`; the first three commits carry the source records and decision entries, the SECURITY and PERMISSIONS_MATRIX amendment, and the DATABASE, CONCURRENCY_IDEMPOTENCY, ARCHITECTURE, API_AND_INTEGRATIONS and PERFORMANCE amendment; the fourth carries everything else.

## External verification

Read on 2026-10-03 from current official documentation and, where it is silent, from the official source of the release named; nothing was installed. The versions are evidence, not pins (DEP-07), and SECURITY's [source evidence](../SECURITY.md#source-and-version-evidence) records them. Documentation was read from the official documentation repository and pages; source from the official repositories at the release tags: Laravel framework v13.34.0, Fortify v1.40.0, Socialite v5.31.0 and pragmarx/google2fa v9.1.0, the TOTP library that Fortify v1.40.0 requires (^9.0).

| # | Planning finding (DIR-043 §6.3) | Result | Source and version seen |
| --- | --- | --- | --- |
| 1 | The Password rule with `min`, `max`, `mixedCase`, `numbers`, `symbols` and `uncompromised`, the last using k-Anonymity through haveibeenpwned.com | Confirmed; `letters` also exists, and a password counts as compromised from one occurrence unless a threshold is given | Laravel 13.x documentation — Validation |
| 2 | Fortify is optional | Confirmed | Laravel 13.x documentation — Fortify |
| 3 | A customizable login pipeline | Confirmed: `authenticateThrough`, and `authenticateUsing` for the credential check | the same |
| 4 | Six-digit TOTP with enable-then-confirm | Confirmed | the same |
| 5 | A QR code | Confirmed; the source renders it locally as SVG | the same; Fortify v1.40.0 source |
| 6 | Recovery codes viewable and regenerable | Confirmed | Laravel 13.x documentation — Fortify |
| 7 | A challenge accepting `code` or `recovery_code` | Confirmed | the same |
| 8 | Password confirmation before two-factor settings change | Confirmed, as the default | the same |
| 9 | The session ID regenerated during authentication with Fortify | Confirmed | Laravel 13.x documentation — HTTP Session |
| 10 | Password confirmation lasts three hours by default and is configurable | Confirmed: `password_timeout` | Laravel 13.x documentation — Authentication |
| 11 | `logoutOtherDevices` needs the current password | Confirmed | the same |
| 12 | `APP_PREVIOUS_KEYS` keeps data encrypted with a previous key readable | Confirmed: the current key encrypts; decryption tries the current key, then each previous one | Laravel 13.x documentation — Encryption |
| 13 | Socialite supports Google, verifies session state unless `stateless`, and takes optional parameters through `with` | Confirmed; `with` must not carry reserved keywords such as `state` or `response_type` | Laravel 13.x documentation — Socialite |
| 14 | The TOTP period is set by the third-party library Fortify uses, not read | Corrected by reading it: google2fa v9.1.0 defaults to six digits, a 30-second period and a window of one step, each settable; Fortify sets no period. The project sets period and window explicitly, so nothing rests on the default | pragmarx/google2fa v9.1.0 source; Fortify v1.40.0 composer requirement |
| 15 | A configurable verification window | Confirmed: `fortify-options.two-factor-authentication.window` | Fortify v1.40.0 source |
| 16 | Code reuse refused only through the cache, and only when a cache is present | Confirmed, with a detail: the cache key is the code's hash, not the account, kept for window × 60 s | the same |
| 17 | A used recovery code replaced by a new one without locking | Confirmed | the same |
| 18 | Columns `two_factor_secret`, `two_factor_recovery_codes`, `two_factor_confirmed_at`, the first two encrypted | Confirmed: text, text and timestamp, encrypted with the current encrypter — the application key unless replaced | the same (migration and `Fortify::currentEncrypter`) |
| 19 | Socialite keeps `state` in the session and removes it at the callback | Confirmed: put at the redirect, pulled at the callback and compared in constant time | Socialite v5.31.0 source |
| 20 | PKCE as an opt-in | Confirmed: `enablePKCE`, S256, the verifier kept in the session and pulled at the token exchange; Google's discovery document lists S256 | the same; Google OpenID Connect page |
| 21 | The Google user ID taken from `sub` | Confirmed, from the userinfo endpoint | Socialite v5.31.0 source |
| 22 | No `nonce` handling for Google | Confirmed, with a detail: `user()` does not return the token response's ID token, and `with` parameters also go with the token request | the same |
| 23 | The `uncompromised` check treats a service failure as "not compromised" | Confirmed: a transport error is reported and, like an unsuccessful answer, leaves an empty result; the default timeout is 30 s and the request asks for padding | Laravel framework v13.34.0 source — `NotPwnedVerifier` |
| 24 | Google's `prompt` accepts `none`, `consent` and `select_account` only, so re-authentication at Google cannot be forced | Confirmed. Google also offers `auth_time` and `amr` claims when enabled in its settings; they report, not force, a fresh sign-in, and the design does not use them | Google Identity — OpenID Connect, page updated 2026-06-15 |
| 25 | `sub` is the stable identifier and the e-mail must not be one | Confirmed: unique, never reused or changed, at most 255 case-sensitive ASCII characters | the same |

Further facts read and used: a `nonce` gives replay protection and is to be presented once; an ID token received directly from Google's token endpoint over HTTPS with the client secret can be trusted without a signature check, and its `iss` is `https://accounts.google.com` or `accounts.google.com` (Google OpenID Connect); Fortify v1.40.0 has a passkeys feature, its default configuration enables every feature, it challenges only an account whose TOTP is confirmed when confirmation is on, keeps the pending challenge as the user's id in the session without an expiry, regenerates the session identifier when the challenge succeeds, throttles the challenge only when a limiter is configured, and exposes the QR code and secret key again through routes; Socialite's Google default scopes are `openid profile email` and its Guzzle options, timeouts among them, come from configuration; the Pwned Passwords range API needs no key, has no rate limit, supports padding, and its corpus can be downloaded (Have I Been Pwned API v3). **No finding contradicts a decision of DIR-043 §2 or makes a control of its §7 impossible**, so stop condition 4 was not met.

## Level-1 adjustments from the verification

Each keeps the control's intent of DIR-043 §7 and changes only its mechanism; none is presented as a framework guarantee.

1. **`nonce` (§7.7).** Socialite's `user()` does not return the ID token, so the project's Google provider — a subclass inside the authentication boundary — keeps the token response and checks the ID token's `nonce`, audience, issuer and expiry and that its `sub` equals the userinfo `sub` (SECURITY AU-22). Google documents that a token taken directly from its token endpoint with the client secret needs no signature check.
2. **Code reuse (§7.4).** Fortify's provider checks reuse through the cache, keyed by the code, and its verification contract carries no account, so a project step accepts a code only by the conditional update of `two_factor_last_step` under the account row (AU-16; CONCURRENCY_IDEMPOTENCY RV-12).
3. **Recovery-code consumption (§7.4).** Fortify replaces a used code with a new one without a lock; the project removes it under the account row lock in one transaction (AU-19; RV-13).
4. **Old secret valid until the new device is confirmed (§7.5 a, b).** Fortify's enable step writes a new secret at once, and DIR-043 §7.9 allows no other field, so an enrolment holds the new secret encrypted in the server-side session until one correct code confirms it; a stored secret is then always a confirmed one, which the strengthened constraint C-62 (the four fields set and cleared together) enforces (AU-16; DATABASE §4.1).
5. **The pending challenge (§7.4).** Fortify keeps only the user's id in the session, without an expiry; the project's challenge carries the purpose, a digest of the credential and factor state and a 5-minute expiry, and the session identifier is also regenerated at the password step (AU-06, AU-16).
6. **High-risk routing (§7.3).** Fortify challenges only accounts whose TOTP is confirmed, so a project pipeline step sends a high-risk account without TOTP to the enrolment-only session (AU-18).
7. **The breached-password check's failure (§7.2).** The framework's verifier passes a password when the service fails; to keep D5's blocking, the project's check refuses the password when the range service gives no answer, asks the person to try again and writes PASSWORD_CHECK_UNAVAILABLE (AU-03; D-SEC-15). The availability cost — no password can be set while the free service is unreachable — is reported to the Owner. *Superseded by the Owner's decision R-07 (DIR-044): when the service fails or does not answer, the check is skipped and recorded as PASSWORD_CHECK_SKIPPED, and the password is accepted only if it passes the local list and every other rule (AU-03; D-SEC-15).*
8. **Scopes (§7.7).** Socialite's Google default adds `profile`, so the scopes are set to `openid email` explicitly (AU-22).
9. **Limiters (§7.1).** Fortify's challenge route is throttled only when configured, so the project defines the two-factor and Google limiters, failing closed (AU-05).

## Other Level-1 choices

- **K1 read on effective capabilities (§7.3):** an active grant counts whatever its prerequisites, and a capability carried by a future custom role counts like a grant, so the test is never weaker than K1's wording; it is a read of role identity that PERMISSIONS_MATRIX AZ-02 now lists. A session that fails the per-request check ends, rather than being narrowed to enrolment (AU-18; D-SEC-18). *Corrected under DIR-045 (R-23 b): AU-18 now states K1 exactly — evaluated on effective capabilities, a grant counting while its prerequisites hold and a custom role's capability outside K1 —, TOTP optional for every other account; the per-request check is unchanged.*
- **"In recovery" (§7.5 b):** derived from the account's latest TOTP event in `audit_events`, written in the same transaction as the recovery-code use, because no field may be added (AU-20 b). *Under DIR-045 (R-16) the code-free enrolment of path b is bound to the session the recovery-code login opened; the derived state still decides Google eligibility.*
- **Links created by a confirming POST (§7.7):** Google's callback is a GET that changes no business state; the holder's following POST, under request-forgery protection with password confirmation and a current code, creates the link (AU-23; WS-04).
- **An Owner's own account:** a TOTP reset or a Google unlink through `users.manage` never targets the acting Owner, whose own account uses paths a, b, d or e and the SELF unlink; C-63 enforces the actor rules of the unlink contract (AU-20 c; PERMISSIONS_MATRIX OD-01).
- **Design values** (re-verified when versions are chosen): a 160-bit secret (32 base32 characters); a 5-minute challenge; a 15-minute enrolment; a 10-minute Google attempt marker and pending link; Google timeouts of 5 s and 15 s; a 5-second breached-password check; an alert above 20 failed codes for one account in an hour.
- **Records and structure:** source records 39 and 40 numbered as records 37 and 38 were, with `-text` entries and a SOURCE_OF_TRUTH provenance subsection (the Level-1 reading named to the Owner before any edit); PERMISSIONS_MATRIX's new table numbered 125 after the earlier rows, whose numbers other documents cite; the CONCURRENCY_IDEMPOTENCY §17 heading kept, with a sentence naming H6-13–H6-16, so that links to it stay valid; LR-08 given its one analysed exception; IC-08's job rule confined to business integrations; EXECUTION_CONTEXT's auth-profile heading kept because AGENTS.md links to it; the SOURCE_OF_TRUTH registry updated as a continuity record; the four K4 risks recorded as four ACCEPTED_RISK gaps with RISK-002–RISK-005; DECISION_INDEX given the status PENDING OWNER APPROVAL.
- **Residual disclosed, not accepted:** a phone holding both the Google session and the authenticator lets whoever holds it unlocked pass both steps until the loss is reported (TM-72) — a consequence of D6 and K2 as approved, recorded for the Owner's attention at approval.

## Amendments

| Document | Rows changed or added | Directive |
| --- | --- | --- |
| SECURITY | Header note; source evidence (12 rows); AU-03 rewritten; AU-04, AU-05, AU-06, AU-09, AU-11–AU-15 extended (the AU-15 window unchanged); AU-16 rewritten; AU-18–AU-24 added; EN-03; WS-08; WS-12; LG-01–LG-04; LG-07; SX-01; SX-02; SX-04; threat actors; TM-01–TM-04, TM-06, TM-57 revised; TM-59–TM-72 added; count and residuals; D-SEC-06 and D-SEC-14 superseded; D-SEC-15–D-SEC-21 added; H6-13–H6-16, H7-15–H7-19, H8-16–H8-22, H9-13–H9-15 added, H9-07 extended; traceability | D5, D6 (DIR-037); K1–K4 (DIR-042); DIR-043 §7, §8 |
| PERMISSIONS_MATRIX | Header note; AZ-02; AZ-13 added; mapping sentence of §3; OD-01; PJ-17; §7.2 intro, row 1, row 125 added, infrastructure sentence and counts (125 tables); traceability | D6, K1, K2; DIR-043 §8 |
| DATABASE | Header note (first part); §4 module map (IAM 8, total 125, authentication state outside the count); §4.1 `users` and `user_external_identities`; §18 C-62–C-65; §19 nullable-key sentence and two family rows; §20 index row; §28 credentials, triggers and audited changes; §30 traceability; §31 downstream | D5, D6, K1–K3; DIR-043 §7.9 |
| CONCURRENCY_IDEMPOTENCY | Header note and authority sentence; source evidence row; D-CC-10; TX-07, TX-08; LR-08; LK-03 and the row-family sentence; §4.3 LK-03; CI-09; BD-19; RV-05, RV-10 extended; RV-11–RV-16 added; H6-07, H6-11 extended; §17 note and H6-13–H6-16; M-39–M-43; HO-11 extended; HO-40, HO-41 added; traceability | D5, D6, K1–K3; DIR-043 §8 |
| ARCHITECTURE | Header note; §1 authentication decision; §3 Identity row; §4 code organization; §8 authentication boundary; §13 outbound adapters; §15; §16; §19; §20 | D5, D6, K2; AICWDF §4A.1, §4A.7–§4A.9 |
| API_AND_INTEGRATIONS | Header note; RB-02; RB-03; section 3; traceability | D5, D6 |
| PERFORMANCE | Header note; the planned path of P8's performance targets; IX-18; traceability | D6; DIR-043 §8 |
| V1_SCOPE | Header note; CAP-13; DEP-10, DEP-11 | D5, D6, K1, K2 |
| ACCEPTANCE_CRITERIA | Header note; AC-13 | D5, D6, K1, K2 |
| GAP_REGISTER | GAP-033 and GAP-036 continuations and triage rows; a GAP-006 continuation for the 125-table projection; GAP-038–GAP-044 with triage rows; summary and totals (44 — 3 CLOSED, 36 OPEN, 0 OWNER_DECISION_REQUIRED, 5 ACCEPTED_RISK) | K4; DIR-043 §8 |
| COST_POLICY | Header note; the recurring-cost inventory and its free-tier record | D5, D6; AICWDF §4C.4 |
| DECISION_INDEX | Status value; the language row; DIR-037 D5 and D6; DIR-042 and DIR-043 rows; D-SEC-06, D-SEC-14 and D-SEC-15–D-SEC-21 | DIR-042, DIR-043 |
| AICWDF_ADOPTION | Header note; §4A, §15 and §16 section-map rows; the two authentication exceptions | D5, D6; DIR-043 §8 |
| EXECUTION_CONTEXT | Header note; the auth-profile lines; invariant 2 (AZ-01–AZ-13) | D5, D6, K1, K2 |
| SOURCE_OF_TRUTH | Provenance of records 39 and 40; registry states and this document's row; the word "and" restored before APPR-007 | DIR-043 §6.2, §8 |
| PHASE_STATUS, CURRENT_HANDOFF | The amendment VERIFYING and awaiting approval; P1, P4 and P6 notes; P8 BLOCKED; the safe next action | DIR-043 §8 |
| DECISION_LOG, CHANGELOG | DIR-042, DIR-043, OBS-016, TECH-025, RISK-002–RISK-005; two entries | DIR-043 §6.2, §8 |
| Source records 39, 40; `.gitattributes` | Two transcripts and their `-text` entries | DIR-043 §6.2 |

The continuation (DIR-045 and DIR-046) changes, in the same documents:

| Document | Rows changed or added | Directive |
| --- | --- | --- |
| SECURITY | Header note; AU-03, AU-05, AU-09, AU-11–AU-13, AU-15, AU-16, AU-18–AU-24; EN-03; WS-07; LG-01, LG-02, LG-07; SX-02, SX-04; TM-63, TM-65–TM-67, TM-71 revised and TM-73 added, with the count and residuals; D-SEC-15–D-SEC-19, D-SEC-21; H6-13, H6-15, H6-16, H7-15, H7-17, H7-18, H8-02, H8-16–H8-22, H9-03, H9-14, H9-15; traceability | DIR-044 R-01, R-02, R-05–R-07; DIR-045 §4 |
| PERMISSIONS_MATRIX | Header note; RG-11; the `users.manage` catalogue row's "Covers" text; OD-01; PJ-17 | DIR-044 R-05, R-06; DIR-045 §3, §4 |
| DATABASE | Header note; §4.1 `users` and `user_external_identities`; §18 C-62; §19; §28; §31 | DIR-044 R-02; DIR-045 §4 |
| CONCURRENCY_IDEMPOTENCY | Header note; TX-07, TX-08; CI-09; RV-05, RV-11–RV-16 revised and RV-17 added; H6-07, H6-11, H6-15, H6-16; §18 introduction, M-44 and M-45 added; HO-11, HO-40, HO-41; traceability | DIR-044 R-01, R-02, R-05; DIR-045 §4 |
| ARCHITECTURE | Header note; §8; §13; §19 | DIR-044 R-07; DIR-045 §4 |
| V1_SCOPE, ACCEPTANCE_CRITERIA, COST_POLICY, AICWDF_ADOPTION, EXECUTION_CONTEXT | Header notes; CAP-13, DEP-11; AC-13; the Pwned Passwords record; the password-profile exception row; two auth-profile lines | DIR-044 R-01, R-05–R-07 |
| GAP_REGISTER | GAP-033, GAP-036 and GAP-043 continuations; GAP-045 and GAP-046 with triage rows; summary and totals (46 — 3 CLOSED, 36 OPEN, 0 OWNER_DECISION_REQUIRED, 7 ACCEPTED_RISK) | DIR-044 (e), (f); DIR-045 §5 step 3 |
| DECISION_LOG, DECISION_INDEX, SOURCE_OF_TRUTH, CHANGELOG | DIR-044, DIR-045, DIR-046, RISK-006, RISK-007 and the TECH-025 continuation; their index rows; the provenance of records 41–43 and this document's registry row; one entry | DIR-045 §5 step 3; DIR-046 §5 |
| PHASE_STATUS, CURRENT_HANDOFF | The continuation and the state | DIR-045 |
| Source records 41–43; `.gitattributes` | Three transcripts and their `-text` entries | DIR-045 §5 step 3; DIR-046 §5 |
| This record | The round-1 section replaced under DIR-046 §4.2; the dispositions, the continuation's Level-1 choices, round 2, the gate and the zero-context check added; superseded items marked | DIR-045 §5; DIR-046 §4 |

The second continuation (DIR-047 and DIR-048) changes, in the same documents:

| Document | Rows changed or added | Directive |
| --- | --- | --- |
| SECURITY | Header note; AU-03, AU-05, AU-12, AU-16, AU-18, AU-20, AU-21, AU-22; LG-01, LG-02, LG-07; WS-12; SX-02; TM-61, TM-72, TM-73 and the count paragraph; D-SEC-15, D-SEC-16, D-SEC-18; H6-15, H6-16, H8-17, H8-20–H8-22, H9-03, H9-14; traceability | DIR-047; DIR-048 §2, §3 |
| PERMISSIONS_MATRIX | Header note; RG-11; OD-01 | DIR-048 §3, §4 |
| DATABASE | Header note; §28 | DIR-048 §3 R2-11 (a), §4 |
| CONCURRENCY_IDEMPOTENCY | Header note; D-CC-10; RV-11, RV-12, RV-14, RV-15, RV-17; H6-07, H6-15; M-44, M-45; HO-24, HO-41 | DIR-048 §3, §4 |
| PERFORMANCE | Header note; IX-19; SZ-09 note; traceability | DIR-048 §3 R2-02, §4 |
| GAP_REGISTER | GAP-036 continuation; GAP-045 rewritten for (e) as extended; GAP-047 with its triage row; summary and totals (47 — 3 CLOSED, 37 OPEN, 0 OWNER_DECISION_REQUIRED, 7 ACCEPTED_RISK) | DIR-047; DIR-048 §5 step 5 |
| DECISION_LOG, DECISION_INDEX, SOURCE_OF_TRUTH, CHANGELOG | DIR-047, DIR-048, the RISK-006 row and continuation and the TECH-025 continuation; their index rows; the provenance of records 44–45 and this document's registry row; the R2-12 correction and one entry | DIR-048 §5 step 2; R2-12 |
| PHASE_STATUS, CURRENT_HANDOFF | The second continuation and the state | DIR-048 |
| Source records 44–45; `.gitattributes` | Two transcripts and their `-text` entries | DIR-048 §5 step 2 |
| This record | Round 2's status, its dispositions and the Level-1 choices of the second continuation; round 3, the gate and the zero-context check | DIR-048 §5 steps 4–6 |

The third continuation (DIR-049 and DIR-050), with the round-3 dispositions before it, changes, in the same documents:

| Document | Rows changed or added | Directive |
| --- | --- | --- |
| SECURITY | Header note; AU-05, AU-06, AU-12, AU-14, AU-20, AU-21, AU-22; AU-25 added; LG-01, LG-02, LG-07; WS-12; SX-02, SX-04; TM-57, TM-63 and the count paragraph; D-SEC-16, D-SEC-19; H6-15, H7-17, H8-17, H8-19, H8-21, H9-03, H9-07, H9-14; traceability | DIR-048 §5 step 5 (round 3); DIR-049; DIR-050 §§3–4 |
| PERMISSIONS_MATRIX | Header note; AZ-02; RG-11 | DIR-050 §5 |
| DATABASE | Header note; §28; §31 | DIR-048 (round 3); DIR-050 |
| CONCURRENCY_IDEMPOTENCY | Header note; TX-07; D-CC-10; CI-09; RV-10, RV-12, RV-14, RV-15, RV-17; RV-18 added; H6-07, H6-15; §18 introduction, M-44, M-45; M-46 added; HO-11, HO-24, HO-41; traceability | DIR-048 §5 step 5 (round 3); DIR-050 §§3–5 |
| PERFORMANCE | SZ-09 note | DIR-048 (round 3); DIR-050 R4-08 |
| GAP_REGISTER | GAP-041 continuation; GAP-045 (R3-14, R4-04); summary | DIR-049; DIR-050 |
| DECISION_LOG, DECISION_INDEX, SOURCE_OF_TRUTH, CHANGELOG | DIR-049, DIR-050, the RISK-005 row and continuation and the TECH-025 continuation; their index rows and the DIR-044 R-02 row; the provenance of records 46–47 and this document's registry row; the R3-08 correction and one entry | DIR-050 §6 step 2 |
| PHASE_STATUS, CURRENT_HANDOFF | The third continuation and the state | DIR-050 |
| Source records 46–47; `.gitattributes` | Two transcripts and their `-text` entries | DIR-050 §6 step 2 |
| This record | Rounds 3 and 4 with their dispositions and the Level-1 choices of the third continuation | DIR-048 §5; DIR-050 §6 |

The final focused reviews (rounds 5 to 7, DIR-050 §2) change, in the same documents:

| Document | Rows changed or added | Directive |
| --- | --- | --- |
| SECURITY | Header note; AU-05, AU-14, AU-18, AU-20, AU-21, AU-25; LG-02, LG-07; TM-57 and the count paragraph; H8-17, H8-21, H9-07, H9-10, H9-14; traceability | DIR-050 §2; §6 steps 5–6 |
| PERMISSIONS_MATRIX | RG-11 | DIR-050 §5 |
| CONCURRENCY_IDEMPOTENCY | TX-07; D-CC-10; RV-12, RV-17, RV-18; H6-07; M-44, M-46; HO-41 | DIR-050 §2; §6 steps 5–6 |
| GAP_REGISTER | GAP-044 continuation (ADMIN_FLOW J-ACC-01, from the zero-context check); GAP-048–GAP-050 added with their triage rows; summary and totals (50 — 3 CLOSED, 40 OPEN, 0 OWNER_DECISION_REQUIRED, 7 ACCEPTED_RISK) | DIR-050 §2; §6 step 6 |
| DECISION_LOG, CHANGELOG | The TECH-025 index row and continuation with the per-file hashes; the R5-06 correction and the entry of the final reviews | DIR-050 §6 steps 6–8 |
| PHASE_STATUS, CURRENT_HANDOFF | GAP-048–GAP-050 and the state | DIR-050 |
| This record | Rounds 5 to 7 verbatim with their dispositions, the Level-1 choices of the round-5 corrections, the carried findings, the gate and the zero-context check | DIR-050 §6 steps 5–7 |

## Superseded decisions

- **D-SEC-06** (15-character minimum) — SUPERSEDED by **D-SEC-15** (AICWDF-COMPAT-8, D5), marked in SECURITY §9 and pointed to by DECISION_INDEX.
- **D-SEC-14** (MFA as a seam only) — SUPERSEDED by **D-SEC-16** (mandatory TOTP for high-risk accounts, D5 and K1), marked the same way.
- The old wording of AU-03 and AU-16 is replaced in place; the rows keep their identifiers. Until the Owner approves the amendment, DECISION_INDEX keeps D-SEC-06 and D-SEC-14 as SUPERSEDED-PENDING.
- By the Owner's decisions on the first review (DIR-044), within the amendment itself: "throttled like login" of DIR-043 §7.4 for the second factor is superseded by the account-wide second-factor limit (R-01; AU-05); the voiding command of DIR-043 §7.6 by the key-compromise incident command (R-02; AU-21), its event TOTP_VOIDED renamed CREDENTIALS_VOIDED; viewing recovery codes after password confirmation alone (DIR-043 §7.4) by the view with a current code (R-06; AU-19); and the fail-closed breached-password check of the first drafts by the recorded skip (R-07; AU-03), its event PASSWORD_CHECK_UNAVAILABLE renamed PASSWORD_CHECK_SKIPPED. The "must enrol" of DIR-043 §7.5 c applies only to a high-risk account (R-13; K1 governs).
- By the Owner's decisions on the fourth review (DIR-049): DIR-044 R-02's "existing paths only" is amended by the OWNER-restoration command (AU-25), and the recovery order written for R3-01 and R3-13 is replaced by DIR-050 §3.
- By the Owner's decision on the second review (DIR-047): the residual (e) as DIR-044 accepted it, for a person who holds the password, is extended and now reads (e) anyone who can reach an account's second-factor step can deliberately delay that account's sign-in by triggering the account-wide cooldown of R-01 — a person who holds its password, a browser in which the person's linked Google account is still signed in, or an abandoned authenticated session within the password-confirmation window.

## Adversarial review — round 1

Round 1 is the review run under DIR-043 §9 on 2026-10-03 against the working tree of `amendment/p5-auth` — the commits `7135fba`, `015270c` and `6dbc49a` after `c26f31e`, plus the fourth-commit drafts not yet committed. It stopped the amendment under DIR-043's stop conditions 6 and 7 before any repair. The Owner decided its Owner-level findings in DIR-044; DIR-045 resolves the others at Level 1. Its evidence is set deterministically under [DIR-046](../../00-governance/DECISION_LOG.md#dir-046-and-tech-025--p5-authentication-amendment-resume-in-a-new-session), which replaces step 2 of DIR-045 §5.

Persisted evidence — the summary kept outside the repository when the amendment stopped, verified on 2026-10-04 before use:

- Path: `C:\Users\YUSUFS~1\AppData\Local\Temp\claude\C--Projects-MultipleCorp\4d945a72-b385-4e08-83bf-8076dc87f5f9\scratchpad\P5_AUTH_ADVERSARIAL_REVIEW_2026-10-03.md`
- Size: 34 lines, 7,801 bytes
- SHA-256: `ACAF24E901971E609CC214BE1F1967DEEAD9A3391792A6BB170DFCC6E522AEBE` — observed, and equal to the value DIR-045 §5 step 2 and DIR-046 §4.1 give

Statement:

- This verified scratchpad summary, reproduced verbatim below, is the persisted round-1 evidence.
- Any partial round-1 content written by the interrupted session is superseded by this deterministic replacement.
- The original longer sub-agent output is not reproduced or reconstructed.
- No session transcript, conversation history, hidden reasoning or agent reasoning was accessed.
- [Review round 2](#adversarial-review--round-2) is the complete new independent review record.

The summary's identifiers and locations refer to the working tree it reviewed; [the dispositions](#dispositions-of-round-1) say where each finding now stands.

````markdown
# Adversarial review — P5 authentication amendment (DIR-043 §9)

Run on 2026-10-03 by a fresh agent with no prior context, read-only, against the working tree of `amendment/p5-auth` (commits 7135fba, 015270c, 6dbc49a after c26f31e, plus the uncommitted Commit 4 changes). Kept outside the repository because the task stopped under stop conditions 6 and 7 before step 5d; nothing was repaired.

Summary: HIGH 2 (R-01, R-02); MEDIUM 6 (R-03–R-08); LOW 16 (R-09–R-24). MATERIAL 19 (R-01–R-19), NON-MATERIAL 5 (R-20–R-24). OWNER-LEVEL: R-01 and R-02 in full; R-05 for accepting the residual; R-06 and R-07 only if their Owner-level variants are chosen.

Mechanical checks that passed: new source-record hashes; 125 tables with IAM 8 wherever current; 80 capabilities (37/26/17); AU-01, AU-02, AU-07, AU-08, AU-10, AU-17 byte-identical; 72 TM rows; gap totals 44 — 3 CLOSED, 36 OPEN, 5 ACCEPTED_RISK; every new identifier defined once; no claim of approval or publication; every unlink path stated identically in SECURITY AU-24, DATABASE §4.1/C-63 and CONCURRENCY; no operator user row anywhere; the OWNER_RECOVERY unlink traceable to the operator.

## Findings

- **R-01 — HIGH — MATERIAL — OWNER-LEVEL.** No bound on TOTP guessing per account or per challenge: AU-05 throttles failed codes per account+IP and per IP only (like login, no cross-address block); a challenge lasts 5 minutes and a failed code does not void it; code checks are cheap HMACs outside H6-12's verification cap; 3 valid codes in 10^6 per guess; TWO_FACTOR_FAILED not aggregated. A password holder with many addresses can guess at server speed (≈26% per challenge at a few hundred requests/s). Level-1 parts: void a challenge after a few failures; aggregate the failure events; P8 tests. A per-account cross-address limiter is also needed, which departs from DIR-043 §7.4 ("Attempts throttled like login, never lockout") and lets a password holder delay the person's sign-in; keeping the design means accepting a residual beyond K4.
- **R-02 — HIGH — MATERIAL — OWNER-LEVEL.** AU-21's voiding after a key-plus-database leak leaves passwords valid, so high-risk accounts fall back to password plus first-come enrolment while the attacker can crack 8-character hashes offline. Not stated: retire the leaked key at once (not keep it in APP_PREVIOUS_KEYS), delete all sessions, rotate the HMAC keys. Substantive fix (credential reissue for every high-risk account) changes the approved §7.6; keeping §7.6 is a risk acceptance beyond K4.
- **R-03 — MEDIUM.** Fortify's own two-factor routes (enable, confirm, disable, qr-code, secret-key, recovery-codes, challenge) are never excluded; they would re-show a confirmed secret, regenerate codes without a current code, accept a recovery code after Google. Fix: state they are not registered or are replaced; extend H8-02/H8-17. Level 1.
- **R-04 — MEDIUM.** Four different rules for when the security event of a reset/recovery/unlink is written (AU-20 "in the path's transaction" vs LG-02 "outside business transactions" vs RV-05/15/16 "after it" vs H6-11/RV-12/CI-09); RV-14 omits GOOGLE_UNLINKED for TOTP_DISABLED; DATABASE §28 "written in their own transactions" vs §23. Fix: audit event in the transaction, security event after commit, stated the same everywhere. Level 1.
- **R-05 — MEDIUM — residual acceptance OWNER-LEVEL.** First-enrolment race: a password holder can enrol before the person; §8 text says the person's notice reveals it, but the notice never reaches someone who cannot pass the challenge; no TOTP_ENROLLED alert; RV-15 rejects a reset of an account without TOTP, so the Owner cannot void a compromised password of an unenrolled high-risk account. Level-1: alert on first enrolment of any high-risk account; correct TM-67/§8; let users.manage reissue credentials without TOTP; disclose. Full closure (enrolment only from an Owner-issued link) is Owner-level.
- **R-06 — MEDIUM.** Recovery codes are viewable with password confirmation only; path b then enrols a new device — a code-free device replacement; RECOVERY_CODES_VIEWED never alerted, RECOVERY_CODE_USED only for OWNER accounts. Level-1: alert both for every high-risk account; record in TM-66. Requiring a current code to view would override §7.4 (Owner-level).
- **R-07 — MEDIUM.** The fail-closed breached-password check makes the free range service a hard dependency of every password set, including path c and the Owner's last-resort recovery (30-minute link); ARCHITECTURE §13 understates it; residual only in the gate document. Level-1: record the residual in §8/TM-71 and as a P9 gap; plan the offline corpus. Relaxing the blocking would be Owner-level.
- **R-08 — MEDIUM.** Working-tree texts describe the fourth commit, the review, the gate and the hash list as done (PHASE_STATUS, CURRENT_HANDOFF, DECISION_LOG TECH-025, CHANGELOG, gate preface, amended headers). Fix: complete steps d–f before Commit 4. Level 1.
- **R-09 — LOW.** RG-11 still requires an actor for every account change; the OWNER_RECOVERY link use has none (exception only in LG-01); AU-11's store records an "issuer" for every link. Fix: add the exception to RG-11; state the issuer is NULL for OWNER_RECOVERY.
- **R-10 — LOW.** RV-05 lists the DEACTIVATION unlink after the token/session/export writes that LR-02 makes last. Fix: reorder.
- **R-11 — LOW.** RV-13/RV-14 do not re-read `is_active`; the challenge digest omits the active flag; nothing says pending-challenge or enrolment-only sessions carry the user id. Fix: re-read; void challenges on deactivation; bind sessions to the user id.
- **R-12 — LOW.** ARCHITECTURE §8/§19 forbid the Google e-mail outside the boundary, but PJ-17 needs `email_at_link` on screens. Fix: narrow to provider types and the subject.
- **R-13 — LOW.** AU-20 c "must enrol" makes TOTP mandatory beyond K1 for a non-high-risk account. Fix: "must enrol when high-risk; otherwise may".
- **R-14 — LOW.** No rule keeps the QR, text key and new codes out of browser history. Fix: AU-12's non-persisted flash rule.
- **R-15 — LOW.** Shared props reach the enrolment-only session; no way to re-confirm the password after 15 minutes there. Fix: minimal props; state the re-confirmation.
- **R-16 — LOW.** Path b's code-free enrolment keyed on the account-wide "in recovery" state, so a full session of an account in recovery could replace the device without a code. Fix: bind it to the session RV-13 creates.
- **R-17 — LOW.** The attempt marker's purpose is not bound to the session's authentication state or account. Fix: bind and refuse mismatches.
- **R-18 — LOW.** Voiding audit granularity unstated (per-account TOTP_VOIDED needed); TX-08 gives the key commands a role/window §28 and §16 do not allow; H9-03 not extended. Fix accordingly.
- **R-19 — LOW.** (a) Code steps other than the challenge do not re-check that the secret is unchanged; (b) an exhausted recovery set must be an encrypted empty list, not NULL (C-62). Fix accordingly.
- **R-20 — LOW, non-material.** HO-40 ("sending the person back to the login") vs AU-04 (one generic answer).
- **R-21 — LOW, non-material.** No flag for a link whose e-mail belongs to someone else; GOOGLE_LINKED alerted only for Owners.
- **R-22 — LOW, non-material.** The `users.manage` "Covers" text of the catalogue omits the TOTP reset and Google unlink (catalogue frozen by design).
- **R-23 — LOW, non-material.** (a) The userinfo call is unnecessary given the verified ID token; (b) AU-18's stricter reading of K1 only partly disclosed.
- **R-24 — LOW, non-material.** Precision: CI-09 wording on the callback and the self-unlink; "11 rows" should be 12; §18 intro cites DIR-032 §9 only; AU-22 lacks the *source* label for Socialite's state removal.
````

## Dispositions of round 1

Every finding of the persisted summary, with its disposition. "Owner" means decided by the Owner in DIR-044 and applied as DIR-045 §2 states it; "Level 1" means resolved under DIR-045 §4, keeping the finding's intent. Where a row names a document's rows, the change is in the working tree of the fourth commit.

| Finding | Disposition | Where it now stands |
| --- | --- | --- |
| R-01 | Owner (R-01: A) — the account-wide second-factor limit; Level-1 values below | SECURITY AU-05 (the rule in full), AU-16, LG-02 (TWO_FACTOR_COOLDOWN_STARTED, TWO_FACTOR_COOLDOWN_REFUSED), LG-07, D-SEC-16, TM-63, TM-73, H6-16, H7-15, H7-18, H8-17, H8-18; CONCURRENCY_IDEMPOTENCY H6-07, RV-12, RV-13, M-44, HO-41; residual (e) GAP-045 (RISK-006) |
| R-02 | Owner (R-02: A) — the key-compromise incident command, a full incident response for every account | SECURITY AU-21, AU-12, AU-13, LG-01, LG-02 (CREDENTIALS_VOIDED in place of TOTP_VOIDED), LG-07, SX-02, SX-04, D-SEC-19, TM-65, H8-21, H9-03, H9-14; CONCURRENCY_IDEMPOTENCY TX-07, CI-09, RV-17, H6-15, HO-41; DATABASE §4.1, §19, §28, §31; PERMISSIONS_MATRIX PJ-17 |
| R-03 | Level 1 — Fortify's own two-factor routes not registered, or replaced by project routes | SECURITY AU-16, EN-03, D-SEC-17, TM-65, H8-02, H8-17, H8-22; GAP-043 |
| R-04 | Level 1 — one rule: the audit event in the transaction of the change, the security event after the commit, outside it (LG-02); GOOGLE_UNLINKED named for TOTP_DISABLED | SECURITY AU-20, AU-23, AU-24, LG-01; CONCURRENCY_IDEMPOTENCY CI-09, RV-05, RV-12–RV-14, H6-11; DATABASE §4.1, §28 ("in the transaction of the change it records", consistent with §23) |
| R-05 | Owner (R-05: a RESET link at the grant) — credential reissue when a grant or role makes an account without confirmed TOTP high-risk; the first-enrolment alert; the reset without TOTP | SECURITY AU-18, AU-12, AU-20 c, LG-02 (PASSWORD_RESET_REQUESTED, reason HIGH_RISK_GRANT), LG-07 (TOTP_ENROLLED of a high-risk account), D-SEC-18, TM-67 and the count paragraph, H7-17, H8-17, H8-19; PERMISSIONS_MATRIX OD-01; CONCURRENCY_IDEMPOTENCY RV-11, RV-15, M-45 |
| R-06 | Owner (R-06: yes) — viewing recovery codes needs password confirmation and a current code; viewing and use alerted for every high-risk account | SECURITY AU-13, AU-15, AU-19, LG-07, TM-66, H7-15, H8-18; PERMISSIONS_MATRIX PJ-17; CONCURRENCY_IDEMPOTENCY CI-09, RV-12 |
| R-07 | Owner (R-07: accepted when the local list passes) — the remote check skipped and recorded on failure or timeout, never inside a transaction; no offline corpus | SECURITY AU-03, D-SEC-15, LG-02 (PASSWORD_CHECK_SKIPPED in place of PASSWORD_CHECK_UNAVAILABLE), LG-07, TM-71, H7-15, H8-16; ARCHITECTURE §13; V1_SCOPE CAP-13, DEP-11; ACCEPTANCE_CRITERIA AC-13; COST_POLICY; EXECUTION_CONTEXT; residual (f) GAP-046 (RISK-007); Level-1 adjustment 7 above superseded |
| R-08 | Level 1 — nothing describes the review, the gate, the fourth commit or the hash list as done before it is | The state records are worded as of the fourth commit that carries them, and the gate checks each statement against the actual state ([gate](#gate), G-17); the per-file hashes are computed from the final content before that commit (TECH-025) |
| R-09 | Level 1 — the exception for the use of an OWNER_RECOVERY link, which has no application actor; no application issuer for such a link | PERMISSIONS_MATRIX RG-11 (DIR-045 §3); SECURITY AU-11, LG-01; PERMISSIONS_MATRIX PJ-17; CONCURRENCY_IDEMPOTENCY H6-11 |
| R-10 | Level 1 — the DEACTIVATION unlink before the statements LR-02 makes last | CONCURRENCY_IDEMPOTENCY RV-05 |
| R-11 | Level 1 — the active flag re-read at every code step; challenge and enrolment-only sessions carry the account's id, so a deactivation voids them | SECURITY AU-16, AU-18; CONCURRENCY_IDEMPOTENCY RV-05, RV-12–RV-14 |
| R-12 | Level 1 — the boundary narrowed to provider-specific types and the provider subject; the display e-mail of a link leaves it through the Identity module's projection | ARCHITECTURE §8, §19 |
| R-13 | Level 1 — after a reset a high-risk account must enrol and any other account may (K1) | SECURITY AU-20 c |
| R-14 | Level 1 — the QR code, the text key and the recovery codes shown only as non-persisted flash data | SECURITY AU-16, AU-19, H7-15, H8-17, H8-18; CONCURRENCY_IDEMPOTENCY RV-14 |
| R-15 | Level 1 — no shared data beyond the display name and the locale on the challenge page and in an enrolment-only session; its password re-confirmation after 15 minutes | SECURITY WS-07, AU-15, AU-16, AU-18, EN-03, H7-15, H8-17 |
| R-16 | Level 1 — path (b)'s code-free enrolment bound to the session its recovery-code login opened | SECURITY AU-20 b, D-SEC-19, H8-18; CONCURRENCY_IDEMPOTENCY RV-13, RV-14 |
| R-17 | Level 1 — the attempt marker bound to the session's authentication state and account; mismatches refused | SECURITY AU-22, AU-23, D-SEC-21, H8-20; CONCURRENCY_IDEMPOTENCY RV-16 |
| R-18 | Level 1 — TX-08 restored to `GuardMaintenance` and repairs; the operator's key commands under the runtime role without a new role, privilege or window, one account per TX-07 transaction, with a per-account audit event; H9-03 extended. No change of the database role model is needed, so DIR-045's stop for this finding does not apply | CONCURRENCY_IDEMPOTENCY TX-07, TX-08, CI-09, RV-17, H6-15; DATABASE §28; SECURITY AU-21, LG-01, H9-03 |
| R-19 | Level 1 — (a) every code step re-checks under the account row that the secret is unchanged; (b) a used-up recovery-code set is an encrypted empty list, never NULL | SECURITY AU-16, AU-19, H6-13; CONCURRENCY_IDEMPOTENCY RV-12, RV-13; DATABASE §4.1, C-62 |
| R-20 | Level 1 — HO-40 aligned with AU-04's single generic answer | CONCURRENCY_IDEMPOTENCY HO-40 |
| R-21 | Level 1 — GOOGLE_LINKED alerts the Owner for every high-risk account; the Owner sees the linked e-mail | SECURITY AU-23, LG-07, H8-20; CONCURRENCY_IDEMPOTENCY RV-16; PERMISSIONS_MATRIX PJ-17 (unchanged on this point) |
| R-22 | Level 1 under DIR-045 §3 — the "Covers" text of `users.manage` names the TOTP reset and the Google unlink; identifier, class, prerequisites and the count of 80 unchanged | PERMISSIONS_MATRIX §4 catalogue row |
| R-23 | Level 1 — (a) the userinfo call dropped: the subject and e-mail are read from the verified ID token; (b) AU-18 states K1 exactly, evaluated on effective capabilities, the stricter reading removed | (a) SECURITY AU-22, D-SEC-21, H6-15, H8-20, H8-22; GAP-043; (b) SECURITY AU-18, D-SEC-18, H8-17; CONCURRENCY_IDEMPOTENCY RV-11 |
| R-24 | Level 1 — the precision items corrected | CONCURRENCY_IDEMPOTENCY CI-09 (the callback runs no transaction; the own unlink carries no code); the 12 source-evidence rows of the [amendments](#amendments) table; the §18 introduction (M-39–M-45 and their sources); SECURITY AU-22 (*source* label on Socialite's state removal) |

The persisted summary does not record a finding differently from DIR-045 §4, so no intent had to be adjusted. Round 1 left no open material finding once these dispositions were applied; [round 2](#adversarial-review--round-2) re-checks them.

## Level-1 choices of the continuation

- **The second-factor limit (R-01):** the attempt window is a rolling 15 minutes; the progression is 1, 2, 4 and 8 minutes for the first four breaches within 24 hours and 15 minutes, the cap, for every later one; the escalation level is the number of breaches in the last 24 hours, and a breach starts a new attempt window; a success resets nothing — the window count, the escalation level, the aggregated counts and the events —, so no success-caused reset exists to be recorded. A submission takes its place in the count by one atomic increment before its code is evaluated and gives it back only when the code is accepted, so concurrent submissions cannot exceed the count. Refusals during the cooldown are aggregated per account and minute, as THROTTLED is, with their number. The limiter store holds the count, the level and the cooldown; the security events stay the record behind the alerts.
- **New security-event kinds and renames:** TWO_FACTOR_COOLDOWN_STARTED and TWO_FACTOR_COOLDOWN_REFUSED added (38 kinds); TOTP_VOIDED renamed CREDENTIALS_VOIDED and PASSWORD_CHECK_UNAVAILABLE renamed PASSWORD_CHECK_SKIPPED, because their meaning changed with R-02 and R-07; a RESET link issued by a high-risk change is a PASSWORD_RESET_REQUESTED with reason HIGH_RISK_GRANT.
- **The incident command (R-02):** run per account in id order, each in its own TX-07 transaction, restartable by its run identifier; the new `APP_KEY` and HMAC keys are put in place first by the runbook, and the leaked key stays in the previous-keys list only while surviving ciphertext needs it — the design stores none beyond the voided TOTP data and the deleted sessions, which P9's key inventory confirms.
- **The grant reissue (R-05):** every role or grant change re-evaluates the high-risk test, a prerequisite's grant included; the unusable hash is computed before the transaction and a stale pre-read rejects and retries the command. The "first enrolment" alert is every TOTP_ENROLLED of a high-risk account, since such an enrolment always follows a state without TOTP.
- **R-18's reference:** DIR-045 names "DATABASE §16 and §28"; the summary names "§28 and §16" for the database roles, and the role statement of §16 is ARCHITECTURE §16. The resolution follows DATABASE §28 and ARCHITECTURE §16, keeping the finding's intent.
- **R-23 (a):** the userinfo call is dropped, so the token exchange is the only call to Google.
- **Scenarios and threats:** M-44 and M-45 added for the limit and the grant reissue; TM-73 added for the deliberate cooldown, making 73 threat scenarios.
- **Custom roles (R-23 b):** a capability carried by a future custom role (RG-12) is not a grant, and K1 does not reach it; whether such a role needs TOTP is left to the Owner when the role is introduced.

## Adversarial review — round 2

Run on 2026-10-04 under DIR-045 §5 step 5 by a fresh agent with no prior context, bound by the rule of DIR-046 §2, read-only, against the complete corrected working tree of `amendment/p5-auth`: the commits `7135fba`, `015270c` and `6dbc49a` after `c26f31e`, plus the uncommitted fourth-commit changes. Its scope was DIR-043 §9 with the additions of DIR-045 §5 step 5. The report below is recorded verbatim, as the agent returned it to the executor; a copy is kept outside the repository at `C:\Users\YUSUFS~1\AppData\Local\Temp\claude\C--Projects-MultipleCorp\2dc7d0a3-0abc-47f2-a842-c97e1ca046a8\scratchpad\P5_AUTH_ADVERSARIAL_REVIEW_ROUND2_2026-10-04.md` (332 lines, 25,878 bytes, SHA-256 `DE89EA5EBC30FB3439047F771FC45E17D201E77BD24FB69B827EDC9F03ED2975`).

**Status at the stop (2026-10-04).** The executor confirmed that finding R2-04 needs an Owner decision — the Owner's R-01 counts failures at the challenge that follows a Google sign-in, so whoever holds the person's linked Google session can trigger the account-wide cooldown without the password, a residual beyond the accepted (e) — and stopped the amendment under DIR-043's stop conditions 6 and 7 (stop condition 7 as DIR-045 §3 reads it) before any repair. No finding of round 2 has a disposition yet; the gate and the zero-context check have not run; the fourth commit does not exist and nothing is pushed. The Owner then decided R2-04 (DIR-047) and authorized the second continuation (DIR-048); the dispositions follow the report.

````markdown
# Adversarial review, round 2: P5 authentication amendment

Reviewer: an independent agent with no prior context, working read-only on the complete working tree of `amendment/p5-auth` on 2026-10-04. That tree is commits 7135fba, 015270c and 6dbc49a after c26f31e, plus the uncommitted fourth-commit changes and the untracked files.

**Result:** 13 findings — 0 HIGH, 4 MEDIUM, 9 LOW. One of them (R2-04) needs an Owner decision.

## What I read

- **Git state:** `git status --porcelain -uall`, `git log`, and `git diff c26f31e --stat` / `--name-status`.
  - I read the full diff against c26f31e for every changed file.
  - All three commits carry the Owner's identity and their messages have no trailer.
- **Source records 39–43, in full:** DIR-042, DIR-043 (§§1–12), DIR-044, DIR-045 (§§1–6) and DIR-046.
- **Gate record, in full:** docs/05-security/evidence/P5_AUTH_AMENDMENT_GATE.md.
- **SECURITY.md and PERMISSIONS_MATRIX.md, in full.** I compared the amended rows with c26f31e (AU-01–AU-24, EN-03, WS-07, WS-12, LG-01, LG-02, LG-07, SX-04, H6-07, H6-11, H9-03, H9-07).
- **DATABASE:**
  - s04-00 and s04-01 in full.
  - §28 in full.
  - Diffs of s01-03, s18-19, s20-24 and s29-31.
  - The `audit_events` row of s04-13.
  - The README table.
- **CONCURRENCY_IDEMPOTENCY:**
  - Diffs of all six amended parts.
  - Rows LK-03–LK-05 and LR-01–LR-03, and the TX-07–TX-09 rows (now and at c26f31e).
  - HO-24, NX-02 and the README table.
- **ARCHITECTURE:** the diff, plus §8, §13, §15 and §16 in full.
- **Other diffs:** API_AND_INTEGRATIONS, PERFORMANCE (IX-18), V1_SCOPE, ACCEPTANCE_CRITERIA, GAP_REGISTER, DECISION_LOG (DIR-042–DIR-046, OBS-016, TECH-025, RISK-002–RISK-007), DECISION_INDEX, SOURCE_OF_TRUTH, EXECUTION_CONTEXT, COST_POLICY, AICWDF_ADOPTION, PHASE_STATUS, CURRENT_HANDOFF, CHANGELOG, `.gitattributes`.
- **Context, read only:**
  - PERFORMANCE SZ-09.
  - The D-CC-10 lines of P6_QUALITY_GATE.
  - ADMIN_FLOW IP-11.
  - WORKFLOWS WF-ACC-02.
- **Compliance with DIR-046 §2:**
  - I opened no transcript, history, `.claude`, `tool-results` or scratchpad content.
  - One large diff output was diverted by the tool harness into a tool-results file. I did not open it; I read the same content from the working tree instead.
  - I created and modified nothing. I ran only read-only git commands, and the link check was an in-memory script fed on stdin.

## Mechanical checks

1. **Source-record hashes — PASS.**
   - The five new records match their SOURCE_OF_TRUTH SHA-256, line and byte counts. Each has a `-text` entry. There are 43 files.
   - The 38 earlier records are unchanged against c26f31e, and the 40 against 6dbc49a.
2. **Branch diff stays within scope — PASS.** It touches only the documents of DIR-043 §6.2 and §8 and DIR-045/046, plus the gate record. BUSINESS_RULES, DOMAIN_MODEL, WORKFLOWS, REFERENCE_COVERAGE, ADMIN_FLOW, DESIGN_SYSTEM and INFORMATION_ARCHITECTURE are untouched.
3. **Capability catalogue — PASS.** 80 capabilities, ADM 37 / ADM_PLUS 26 / OWNER_ONLY 17. Only the "Covers" text of `users.manage` changed. Roles RG-01 and the CS rows are unchanged.
4. **Unchanged authentication rows — PASS.** AU-01, AU-02, AU-07, AU-08, AU-10 and AU-17 are byte-identical to c26f31e. The AU-15 window is still 15 minutes.
5. **Counts — PASS.**
   - 125 logical tables; the module map sums to 125 with IAM at 8.
   - PERMISSIONS_MATRIX §7.2 has 125 rows: 102 single-class + 19 split + 3 following their owner + 1 outside the classes.
   - Every current statement of the total agrees; mentions of 124 are only in historical records.
   - 18 CAP rows, 14 document types (DOC-01–DOC-14), 13 modules.
6. **Threat model and register totals — PASS.**
   - 73 TM rows, all unique; 38 security-event kinds (21 + 17).
   - Gap totals 46: 3 CLOSED / 36 OPEN / 0 OWNER_DECISION_REQUIRED / 7 ACCEPTED_RISK.
   - The new AU, D-SEC, H6–H9, RV, M, HO, C, IX, GAP, RISK and DIR identifiers are unique. The duplicate LK entries were already there at c26f31e: the registry and the §4.3 table.
7. **Links — PASS (as expected now).** Every relative link and anchor in the repository resolves except three anchors in the gate record: `#adversarial-review--round-2` (twice) and `#gate`, whose sections are not written yet.
8. **Split documents — PASS.** No heading was added or renumbered, and the DATABASE and CONCURRENCY_IDEMPOTENCY README tables are still valid.
9. **No fail-closed breached-password statement remains — PASS.** The only occurrences are the gate record's superseded adjustment 7 and the verbatim round-1 summary.
10. **No secret, client ID or placeholder text — PASS.**
11. **No operator user row, and the unlink contract matches everywhere — PASS.**
    - No document gives the operator a user row.
    - Every unlink path is stated the same way in SECURITY AU-24/AU-20, DATABASE §4.1/C-63 and CONCURRENCY RV-05/RV-14/RV-15/RV-16/H6-11.
    - The OWNER_RECOVERY unlink can be traced to the operator through the link's issue events.
12. **"Audit event in the transaction, security event after the commit" — PASS.** No document contradicts it.
13. **R-01 parameters — PASS.** The threshold of 5, the rolling 15-minute window, the 1/2/4/8/15-minute progression with its cap, the 15-minute and 24-hour decay, "a success resets nothing", the flows covered and the exclusion of enrolment confirmation agree wherever they are stated.
    - AU-05, H6-07 and GAP-045 state the rule in full.
    - D-SEC-16, TM-63 and M-44 state it partially but do not contradict it.
    - The progression and decay are P8 obligations (H8-17, M-44).
14. **No claim of approval or publication — PASS, with one exception.** The TECH-025 hash list is absent, as expected. The state documents assert review and gate results that must be true when the fourth commit is made. CHANGELOG has one misattribution (R2-12).

## Findings

### R2-01 — MEDIUM — MATERIAL — Level 1: Activation makes an account high-risk without R-05's credential reissue

**Locations**
- SECURITY:
  - AU-18 "Enforcement" ("every command that changes an account's role or grants … when the account becomes high-risk").
  - D-SEC-18.
  - AU-12, last sentence.
  - The §8 count paragraph ("every other way for a high-risk account without TOTP to obtain a usable password runs through such a link").
  - H8-17.
- PERMISSIONS_MATRIX OD-01, RG-05 (`users.manage` activates accounts) and RG-10 (deactivation revokes grants; reactivation requires fresh grants).
- CONCURRENCY:
  - RV-11 ("Every NX-02 command that changes an account's role or grants").
  - RV-05 (a reactivation exists; deactivation leaves the password usable).
  - NX-02 ("deactivation and reactivation").
  - M-45.
- The approved WORKFLOWS WF-ACC-02 main path: create → role → company grants → capabilities → activate.

**Problem**
- K1 and AU-18 make only an *active* account high-risk, and the test runs only in role and grant commands.
- A role or grant given while the account is inactive therefore evaluates to "not high-risk".
- Activation evaluates nothing.

**Failure scenario**
1. Admin X has no TOTP and is not high-risk. X is deactivated (perhaps because the password leaked); X's password stays usable.
2. Following WF-ACC-02's order, the Owner grants X a company and `finance.view` (or assigns the OWNER role), then activates X.
3. Neither command ends sessions, makes the password unusable or issues a RESET link.
4. X is now high-risk without TOTP. Whoever knows the old password gets the enrolment-only session and enrols their own authenticator — the first-enrolment race R-05 was decided to close. Only the TOTP_ENROLLED alert remains.

**Recommended fix**
- Make the activation command (NX-02) evaluate AU-18's test as RV-11 does. When activation makes an account without confirmed TOTP high-risk, it makes the password unusable, revokes unused links and ends sessions. The Owner then delivers a RESET link through the existing AU-12 issue.
- Alternative: role and grant commands evaluate the test whether or not the account is active.
- Either option stays inside R-05's intent without stretching DIR-045 §3's AU-12 narrowing.
- Update AU-18, D-SEC-18, OD-01, RV-11 (or RV-05), M-45, H8-17 and the §8 count paragraph.

### R2-02 — MEDIUM — MATERIAL — Level 1: The second-factor limit's state can be lost silently, so the limit fails open

**Locations**
- SECURITY AU-05 ("The limiter store holds the count, the escalation level and the cooldown … losing its state is a store failure handled under H6-07, never a success"), H6-16, WS-12 and H8-17.
- CONCURRENCY H6-07 (the same claim), D-CC-10 ("P9 may weigh the framework's failover store as a way to shorten such an outage"), RV-12, M-44 and HO-24 (an eviction policy; "whether a failover store is worth having").
- The approved PERFORMANCE SZ-09 (the store "needs no persistence for correctness" and has "an eviction policy under which limiter keys can neither evict queue payloads and locks nor fill the store").

**Problem**
- The count, the active cooldown and the escalation level exist only in Valkey.
- The approved P6 design makes that store non-persistent, lets limiter keys be evicted, and lets P9 add a failover store.
- A restart, an eviction or a failover produces an empty state that looks exactly like "no failures". Nothing can detect it as a "store failure", so the limit silently resets — failing open.
- This contradicts R-01: the limiter store fails closed, and the escalation level is never silently reset.

**Failure scenario**
- A password holder attacking an account at the 15-minute cap does any of the following:
  - waits out, or benefits from, a Valkey restart;
  - provokes eviction by creating many limiter keys (failed logins with random identifiers from many IPv6 /64s), which the SZ-09 policy allows;
  - benefits from a failover to an empty secondary store.
- The cooldown disappears and the escalation level drops from 15-minute to 1-minute cooldowns, or to zero, raising the guess rate well beyond the "about 480 a day" that TM-63 states.

**Recommended fix**
- Enforce the limit from durable rows, as H6-07 already does for uploads and exports:
  - derive the escalation level from TWO_FACTOR_COOLDOWN_STARTED events in the last 24 hours;
  - derive a floor for the window count from TWO_FACTOR_FAILED events in the last 15 minutes, with an index on `security_events`;
  - or keep this one limit on a database-backed limiter.
- State that the second-factor limiter never uses a failover store and that its keys are excluded from eviction (D-CC-10, HO-24, SZ-09 note).
- Add tests for a store restart, an eviction and a failover keeping the limit (H8-17, M-44).

### R2-03 — MEDIUM — MATERIAL — Level 1: The key-compromise incident command leaves a window whose artefacts survive the response

**Locations**
- SECURITY AU-21 (the runbook keeps the leaked key listed "while surviving ciphertext still needs it"), SX-02, H8-21 and H9-14.
- CONCURRENCY RV-17 ("while it runs, an account not yet voided keeps working, and one already voided has no session and no usable credential"; "After the last account every remaining session row is deleted"), H6-15 and HO-41.

**Problem**
- Accounts are voided one by one, in id order, with no block on sign-in.
- Unused links are revoked only when each account is processed. The final sweep deletes sessions only.

**Failure scenario**
1. The incident runs while the leaked key stays in `APP_PREVIOUS_KEYS`, which AU-21 allows if surviving ciphertext needs it.
2. The attacker holds the leaked key and the database copy. That lets them compute TOTP codes from the decrypted secrets, forge cookies for live session IDs, and possibly use a cracked 8-character password (K4).
3. Through an Owner account not yet voided, the attacker:
   - issues a RESET link to an account already voided — it uses the new HMAC key, so it stays valid;
   - creates an account with the OWNER role after the run has passed the highest id;
   - or links its own Google identity to not-yet-voided accounts — R-02 keeps those links.
4. All of this survives the run. RV-17's claim that a voided account has "no usable credential" is false, and R-02's "every unused credential token revoked" is not met at the end.

**Recommended fix** (no database role or maintenance window is needed)
- Start the run with a sweep that deletes every session and revokes every unused credential token.
- While the run is in progress, refuse sign-in, credential-link issue and use, and NX-02 commands — for example an incident flag checked by the login pipeline, the session guard and the credential flows, or the framework's maintenance mode.
- Process every account, including any created after the run started.
- End with a second sweep that revokes unused tokens and deletes sessions.
- State that the leaked key is not listed at all unless P9's inventory finds ciphertext that must survive.
- In H9-14, have the Owner review accounts, grants, links and Google links created since the suspected leak — unlinking suspect ones through the existing OWNER unlink — before issuing RESET links.
- Update RV-17, AU-21, H8-21 and HO-41.

### R2-04 — MEDIUM — MATERIAL — OWNER-LEVEL: A linked Google session alone can trigger the account-wide cooldown — a residual beyond (e)

**Locations**
- SECURITY AU-05: its covered flows include "the sign-in challenge after a password or a Google sign-in", but its residual sentence speaks only of "a person who holds an account's password".
- SECURITY TM-61, TM-73 and the §8 count paragraph.
- GAP-045; DIR-045 §2 (e); RISK-006.

**Problem**
- The Google path reaches the account's TOTP challenge without the MULTIPLECORP password.
- The accepted residual (e) covers only password holders.
- A narrower variant: whoever holds an unattended authenticated session, within its 15-minute password confirmation, can also fail codes at the view, regenerate, replace or disable steps.

**Failure scenario**
1. On a shared office PC (D6's shared-device concern, TM-61), the person stayed signed in to Google.
2. The next user clicks "Sign in with Google" and selects that account. With `prompt=select_account` and a live Google session, no Google password is asked.
3. The next user reaches the person's challenge and enters five wrong codes.
4. The account-wide cooldown starts and blocks the person's password sign-in as well. It can be repeated, up to 15 minutes each time, from any PC or address.

**Recommended fix**
- The Owner decides either:
  - to extend residual (e) to everyone who can reach the account's second-factor step (holders of the linked Google session, or of an unattended session); or
  - a mitigation that departs from R-01's single shared count — for example, failures at a Google-path challenge feeding a Google-only cooldown that makes only Google sign-in ineligible.
- Level-1 part, now: correct AU-05, TM-61, TM-73, the count paragraph and GAP-045 to name this population, and disclose it as not accepted.

### R2-05 — LOW — MATERIAL — Level 1: An enrolment confirmation can race a credential reissue

**Locations**
- CONCURRENCY RV-14 (re-reads only `is_active` and the TOTP fields), RV-11, RV-15 and M-45.
- SECURITY AU-18 ("so that only the person who receives the link can set a password and enrol").

**Failure scenario**
1. A non-high-risk account without TOTP is being enrolled optionally by whoever holds its password.
2. The Owner's grant (RV-11) or reissue (RV-15) commits: the password becomes unusable, a RESET link is issued and sessions are deleted.
3. The in-flight confirmation, whose session guard passed before that commit, locks LK-03, finds "no enrolment", and writes the holder's device.
4. As its last statements it revokes the account's unused links — including the RESET link just issued.
5. No takeover follows, because the password is unusable. But R-05's guarantee is broken and the account needs another reset.

**Recommended fix**
- RV-14, and RV-12's non-challenge steps, re-read under LK-03 the password hash that the session's digest names (as RV-10 does) and refuse on a difference.
- Add the race to M-45 and HO-41.

### R2-06 — LOW — MATERIAL — Level 1: Duplicate-callback handling contradicts DIR-043 §7.7 and M-43

**Locations**
- SECURITY AU-22:
  - "A SIGN_IN callback in a session whose challenge was already pending when the attempt started leads to that challenge".
  - A session that "has meanwhile … gained another pending challenge", or a "used … `state`", gets the generic failure.
- SECURITY AU-16 ("nothing else is reachable") and H8-20.
- CONCURRENCY M-43 ("another arriving while it is pending leads to it unchanged").
- DIR-043 §7.7 ("A second callback while a TOTP challenge is already pending leads to that challenge and does not disturb it").

**Failure scenario**
- A browser double submission or Back on the callback: the first callback creates the challenge.
- M-43 and §7.7 send the second callback to that challenge; AU-22 gives it the generic failure.
- P8 tests derived from H8-20 and from M-43 would therefore expect different outcomes.
- It is also unstated whether the failure leaves the pending challenge intact.

**Recommended fix**
- Align AU-22 with §7.7 and M-43: any Google callback arriving while a challenge is pending in the session leads to that challenge unchanged and consumes nothing. Other stale or mismatched callbacks get the generic failure and change nothing.
- Make AU-16's "nothing else is reachable" except the callback.

### R2-07 — LOW — MATERIAL — Level 1: RG-11's single exception is contradicted by actor-less operator events

**Locations**
- PERMISSIONS_MATRIX RG-11: "One exception … no operator, system identity or placeholder user is ever recorded as an actor".
- SECURITY LG-01 ("the operator's commands of AU-14 and AU-21 record the operator's identity — never a user row — with `actor_user_id` NULL") and AU-21.
- CONCURRENCY RV-17 ("with its audit event — no actor").

**Problem**
- RG-11 requires an actor for every account and credential-link change, except the use of an OWNER_RECOVERY link.
- The incident command's per-account events (password made unusable, links revoked) and the OWNER_RECOVERY link issue are written with no actor. RG-11 neither allows this nor lets the operator be the actor.
- A literal reading pushes an implementer, or P8's reconciliation (H8-05), towards a system or operator actor — exactly what RG-11 and C-63 forbid.

**Recommended fix**
- In the RG-11 exception sentence, which DIR-045 §3 lets the amendment write, state that the operator's console commands of AU-14 and AU-21 record the operator's identity as AU-14 records it, in place of an application actor.
- Narrow the last clause to "no user row for an operator, system identity or placeholder".
- Align RV-17's wording.
- If DIR-045 §3 is read as not allowing this, the change is Owner-level.

### R2-08 — LOW — NON-MATERIAL — Level 1: LG-07 omits the path-(e) TOTP reset of an Owner account

**Locations:** SECURITY AU-20 ("every such event of an OWNER-role account … alerts (LG-07)"), LG-07 and H9-15.

**Problem and scenario**
- LG-07 alerts a TOTP reset only when it is "by an Owner".
- The link use of path (e) (TOTP_RESET and PASSWORD_RESET_COMPLETED for an OWNER-role account, with no actor) is not listed.
- If the issue alert is missed, or delivered only in-app, the moment the Owner account is actually taken over raises no alert.

**Recommended fix:** add every TOTP_RESET of an OWNER-role account (or every AU-20 event of one) to LG-07.

### R2-09 — LOW — NON-MATERIAL — Level 1: AU-03 and D-SEC-15 misstate the framework behaviour that the source-evidence table records

**Locations**
- SECURITY AU-03: the verifier counts a failure as not compromised "without reporting it (source)".
- D-SEC-15: "a skip that leaves no record, as the framework's verifier does (source)".
- The SECURITY source-evidence row for the `NotPwnedVerifier`, which says "a transport error is reported".

**Problem:** this is a framework fact that the source-evidence table contradicts. The control itself is unaffected.

**Recommended fix:** reword to "reports a transport error to the exception handler, leaves an unsuccessful answer unreported, and writes no security event".

### R2-10 — LOW — NON-MATERIAL — Level 1: What happens to Google links after incident recovery is unstated

**Locations:** SECURITY AU-21 ("Google links are kept"), AU-14 / AU-20 (e), H8-21 and H9-14.

**Problem**
- Each Owner account is recovered through path (e), whose link use unlinks Google with reason OWNER_RECOVERY. Owners' links are therefore not kept.
- Other accounts keep their links only if they receive a plain AU-12 RESET link; through the path (c) reset, theirs would be unlinked as well.
- P8 could test the wrong expectation.

**Recommended fix:** state this consequence, and specify a plain AU-12 RESET link for non-Owner accounts after the incident.

### R2-11 — LOW — NON-MATERIAL — Level 1: Precision items

- **(a) DATABASE §28.** The runtime role's UPDATE set ("lifecycle, draft or unlink columns") does not name `users.password` or the four TOTP fields. Yet RV-10–RV-17 write them under that role, and §28 now says the operator commands "need no privilege beyond the runtime role's".
- **(b) Authority class of operator audit events.** The class of the actor-less events of AU-14 (issue) and AU-21 is unstated, although CI-09 and H6-11 state SYSTEM for the others. OD-16/H8-05 reconcile on it and QS-14 reads it.
- **(c) SECURITY H9-03.** "which run under the runtime role" can be read to include `GuardMaintenance`, which runs under the administration role (TX-08).
- **(d) CONCURRENCY RV-15.** It names TOTP_RESET and GOOGLE_UNLINKED only. For the reissue of an account without TOTP, TOTP_RESET is a misnomer, and the RESET link's PASSWORD_RESET_REQUESTED (AU-12, LG-02) is not named.
- **(e) SECURITY header note.** It does not list WS-07 or H9-03, which the continuation changed and which the gate record lists.

### R2-12 — LOW — NON-MATERIAL — Level 1: CHANGELOG attributes the gate to the stage that stopped

**Location:** CHANGELOG "Unreleased — 2026-10-03, P5 security and authentication amendment", third bullet.

**Problem**
- It says the 44-gap stage "created P5_AUTH_AMENDMENT_GATE with … the adversarial review and its dispositions, the gate and the zero-context check".
- That stage stopped at review round 1 (DIR-045 §1). No dispositions, gate or zero-context check existed then, so the sentence is contrary to R-08.

**Recommended fix:** say that it created the record with the verification and the first review, which stopped the amendment.

### R2-13 — LOW — NON-MATERIAL — Level 1: A pending challenge or enrolment-only session only carries the user id if the project writes it

**Locations:** SECURITY AU-16 and AU-18 ("its session row carries the account's id"); CONCURRENCY RV-05.

**Problem**
- The framework's database session handler fills `user_id` only for an authenticated guard login.
- A pending challenge, or an enrolment-only session that is not a guard login, therefore carries no id unless a project step writes it. A deactivation would then not delete those sessions.
- This is not marked as a project step, nor listed in H8-22/GAP-043. Impact is limited because `is_active` is re-read at every code step and at confirmation.

**Recommended fix:** mark it as a project step and add it to H6-15 and H8-22.

## Summary by attack area

1. **Bypassing TOTP:** R2-01, R2-05.
   - Google, credential links and recovery sessions: no finding.
   - IP-11 is covered by AU-04, H7-19 and GAP-044.
2. **Unsafe recovery (paths a–f, AU-14, K3 order):** R2-10 (clarity). No unsafe path found.
3. **Google as authorization, e-mail as key, link takeover, identity mismatch:** no finding. R2-03 covers links created during an incident run.
4. **Duplicate, stale and replayed callbacks; marker binding:** R2-06.
5. **Session fixation; sessions surviving:** R2-03, R2-13. Otherwise no finding.
6. **Reused codes and concurrency:** R2-05. Otherwise no finding.
7. **Enumeration:** no finding.
8. **Shared devices; deactivated accounts:** R2-04, R2-13.
9. **Missing audit:** R2-07, R2-08, R2-11(b), R2-11(d). The in-transaction / after-commit rule is stated consistently.
10. **Contradictions with approved P4/P5/P6:** R2-02 (SZ-09, HO-24, D-CC-10), R2-07, R2-11(a), R2-11(c).
11. **Unsupported framework guarantees:** R2-09, R2-13.
12. **R-01 account-wide limit:** R2-02, R2-04. The statement, concurrency, success and P8-obligation checks pass.
13. **R-02 incident command:** R2-03, R2-07, R2-10, R2-11(a)(b). Database role and transaction class are otherwise consistent: runtime role, TX-07, TX-08 restored.
14. **R-05 grant reset:** R2-01, R2-05.
15. **R-07 fail-open check:** no material finding; R2-09 is wording only. No fail-closed statement remains, the check never runs inside a transaction, and residual (f) is recorded.
16. **R-06 recovery-code viewing:** no finding.
17. **AU-18 equals K1:** no finding. No reading makes TOTP mandatory beyond K1.
18. **DIR-043 §3 scope limits as narrowed:** no finding.
19. **Accepted risk beyond K4, (e) and (f):** R2-04 (OWNER-LEVEL). TM-72 stays disclosed and not accepted, as the gate record states. No other acceptance is claimed.

## Counts

- **By severity:** HIGH 0; MEDIUM 4 (R2-01–R2-04); LOW 9 (R2-05–R2-13).
- **By materiality:** MATERIAL 7 (R2-01–R2-07); NON-MATERIAL 6 (R2-08–R2-13).
- **OWNER-LEVEL:**
  - R2-04: acceptance of the cooldown residual for anyone who can reach the second-factor step without the password, or an Owner-chosen departure from R-01's single shared count.
  - Contingent: R2-07 becomes Owner-level only if the executor reads DIR-045 §3 as not allowing the RG-11 wording fix.
````

## Dispositions of round 2

The Owner decided R2-04 in DIR-047 and authorized the second continuation in [DIR-048](../../00-governance/DECISION_LOG.md#dir-047-dir-048-risk-006-and-tech-025--p5-authentication-amendment-second-continuation-after-review-round-2), whose §3 sets the Level-1 disposition of every other finding; where it is silent the reviewer's recommended fix is followed. Each change is in the working tree of the fourth commit.

| Finding | Disposition | Where it now stands |
| --- | --- | --- |
| R2-01 | Level 1 — an account is high-risk only while active (K1); the activation command (NX-02) runs AU-18's test and, when it makes an account without confirmed TOTP high-risk, does exactly what R-05 requires of a grant — the password made unusable, the unused credential links revoked, the sessions ended and one RESET link issued; a role or grant change on an inactive account changes nothing until the activation | SECURITY AU-12, AU-18, D-SEC-18, H8-17; PERMISSIONS_MATRIX OD-01; CONCURRENCY_IDEMPOTENCY RV-11, M-45, HO-41 |
| R2-02 | Level 1 — the limit's state made durable: the stricter of the limiter store and a durable floor read from security events, failing closed when the floor cannot be read or an event cannot be written; the store's keys for the limit excluded from eviction and from any failover store; restart, eviction and failover tests as P8 obligations. The approach chosen and why: [below](#level-1-choices-of-the-second-continuation) | SECURITY AU-05, WS-12, D-SEC-16, H6-16, H8-17; CONCURRENCY_IDEMPOTENCY D-CC-10, H6-07, RV-12, M-44, HO-24, HO-41; PERFORMANCE IX-19, SZ-09 |
| R2-03 | Level 1 — the incident protocol: (a) an opening sweep deletes every session and revokes every unused credential token; (b) while the run lasts the application stays in the framework's maintenance mode, without a bypass, so sign-in, credential-link issue and use and every NX-02 command are refused — no table or field is needed; (c) every account is processed, those created after the run started included; (d) a closing sweep revokes every unused credential token and deletes every session again; the leaked key not listed unless P9's inventory finds ciphertext that must survive; the Owner's review of what was created since the suspected leak, and the unlink of suspect Google links, before RESET links are issued | SECURITY AU-21, SX-02, H6-15, H8-21, H9-14; CONCURRENCY_IDEMPOTENCY RV-17, H6-15, HO-41 |
| R2-04 | Owner (R2-04: 1, DIR-047) — the residual (e) extended, R-01's single shared count unchanged; the Level-1 addition of DIR-048 §2: every breach alert names its triggering path | SECURITY AU-05, LG-02, LG-07, TM-61, TM-73 and the §8 count paragraph; GAP-045; DECISION_LOG RISK-006 and its continuation; DECISION_INDEX |
| R2-05 | Level 1 — every non-challenge code step and the enrolment confirmation re-read the password hash the session's digest names, refusing on a difference | CONCURRENCY_IDEMPOTENCY RV-12, RV-14, M-45, HO-41; SECURITY H8-17 |
| R2-06 | Level 1 — a Google callback arriving while a challenge is pending leads to it unchanged and consumes nothing; any other stale or mismatched callback gets the generic failure and changes nothing | SECURITY AU-16, AU-22, H8-20 (consistent with DIR-043 §7.7 and CONCURRENCY_IDEMPOTENCY M-43) |
| R2-07 | Level 1 under DIR-048 §4 — the operator's console commands of AU-14 and AU-21 record the operator's identity as AU-14 records it, in place of an application actor; no user row for an operator, system identity or placeholder | PERMISSIONS_MATRIX RG-11; CONCURRENCY_IDEMPOTENCY RV-17; SECURITY LG-01 |
| R2-08 | Level 1 — every AU-20 event of an OWNER-role account alerts, the TOTP_RESET of path (e) included | SECURITY LG-07 |
| R2-09 | Level 1 — the verifier's behaviour stated as the source evidence records it | SECURITY AU-03, D-SEC-15 |
| R2-10 | Level 1 — after an incident, Owner accounts recovered through path (e) lose their Google link; every other account receives a plain AU-12 RESET link, which keeps its link | SECURITY AU-12, AU-21, H8-21, H9-14 |
| R2-11 | Level 1 — (a) DATABASE §28 names `users.password` and the four TOTP fields in the runtime role's UPDATE set (DIR-048 §4); (b) the actor-less operator events carry authority class SYSTEM, as CI-09 and H6-11 record the others; (c) H9-03 states that `GuardMaintenance` runs under the administration role; (d) a reissue of an account without TOTP is not a TOTP_RESET and names PASSWORD_RESET_REQUESTED; (e) the SECURITY header lists WS-07 and H9-03 | DATABASE §28; SECURITY LG-01, H9-03, AU-20 c, header; CONCURRENCY_IDEMPOTENCY RV-15, RV-17 |
| R2-12 | Level 1 — the CHANGELOG bullet now says the earlier stage created the gate record with the verification and the first review, which stopped the amendment | CHANGELOG |
| R2-13 | Level 1 — writing the account's id into a pending-challenge or enrolment-only session row marked as a project step | SECURITY AU-16, AU-18, H6-15, H8-22; CONCURRENCY_IDEMPOTENCY H6-15 |

After these dispositions round 2 has no open finding; [round 3](#adversarial-review--round-3) re-checks them.

## Level-1 choices of the second continuation

- **R2-02, the durable floor:** the limit is enforced from the stricter of the limiter store and a floor read from the account's TWO_FACTOR_FAILED and TWO_FACTOR_COOLDOWN_STARTED security events on the partial index IX-19. Chosen over a database-backed limiter because it keeps the approved limiter store as the atomic gate that bounds concurrent submissions (CONCURRENCY_IDEMPOTENCY D-CC-10), reuses a durable record the limit already writes — as H6-07 already enforces the upload and export bounds from durable rows —, and needs one partial index and no table or field, while a database-backed limiter would need the framework's database cache table and move every operation of the limit onto PostgreSQL. The limiter store stays in the path, so its keys for the limit are excluded from eviction and no failover store serves them. The failure and breach events are written before the step answers, and "the stricter of" is one atomic operation of the store that raises its count, level and cooldown to at least the floor before the place is taken, so the bound on concurrent submissions holds right after a store loss (round 3, R3-02); a step whose floor cannot be read, or whose event cannot be written, is refused. The keys carry no expiry of their own and hold time-stamped entries the operation trims, so the HO-24 policy, which evicts only keys with an expiry, never selects them.
- **R2-03, the refusal while the incident command runs:** the framework's maintenance mode without a bypass — no table or field is needed, queue workers stop with it, and the console command runs regardless; the command re-reads the accounts after the last one so that any created before maintenance mode took effect is processed.
- **The triggering path:** recorded in TWO_FACTOR_COOLDOWN_STARTED as PASSWORD, GOOGLE or SESSION_STEP and named in the alert.
- **R2-01, the reason code:** a RESET link issued by an activation is a PASSWORD_RESET_REQUESTED with the same reason, HIGH_RISK_GRANT, as one issued by a grant or a role assignment.
- **TM-72:** its residual — a consequence of D6 and K2 as approved, disclosed and not accepted — is registered as GAP-047, OPEN, owned by the Owner's decision at the approval of the amendment, so that every threat row stating a residual has a disposition.

## Adversarial review — round 3

Run on 2026-10-04 under DIR-048 §5 step 5 by a fresh agent with no prior context, bound by the rule of DIR-046 §2, read-only, against the complete corrected working tree of `amendment/p5-auth` after the round-2 dispositions. Its scope: every change made for DIR-048 §2 and §3 and its interactions with R-01, R-02, R-05 and the reset paths; a regression run of round 2's mechanical checks; and a disposition for every threat row that states a residual, TM-72 included. The report below is recorded verbatim, as the agent returned it to the executor; a copy is kept outside the repository at `C:\Users\YUSUFS~1\AppData\Local\Temp\claude\C--Projects-MultipleCorp\2dc7d0a3-0abc-47f2-a842-c97e1ca046a8\scratchpad\P5_AUTH_ADVERSARIAL_REVIEW_ROUND3_2026-10-04.md` (398 lines, 28,418 bytes, SHA-256 `B4FC374CDE7D60A34BB1C04032B9FE6C86560A332B884587B579272F672D8CD3`). It found no finding that needs an Owner decision.

````markdown
# Adversarial review, round 3: P5 authentication amendment

Reviewer: an independent agent with no prior context, working read-only on 2026-10-04 on the complete working tree of `amendment/p5-auth`. That tree is commits 7135fba, 015270c and 6dbc49a after c26f31e, plus the uncommitted fourth-commit changes (27 modified files) and the untracked files (source records 41–45 and the gate record). The scope is DIR-048 §5 step 5.

**Result:** 15 findings: 0 HIGH, 1 MEDIUM, 14 LOW. Six are MATERIAL and nine NON-MATERIAL. None needs an Owner decision; R3-01 has a contingent note.

## What I read

- **Owner records:**
  - DIR-048 (P5_AUTH_SECOND_CONTINUATION…) and DIR-047 (P5_AUTH_ROUND2…), in full.
  - DIR-044 (P5_AUTH_REVIEW_OWNER_DECISIONS…), in full.
  - DIR-045: R-01, R-02, R-05–R-07 and the residuals (e) and (f).
  - DIR-043: K1–K4, §3, the stop conditions, §7.8 and the commit plan.
- **Gate record:** docs/05-security/evidence/P5_AUTH_AMENDMENT_GATE.md, in full (601 lines).
- **SECURITY:**
  - The header and the source evidence.
  - AU-01–AU-24 in full; EN-01–EN-08; WS-07, WS-12; LG-01–LG-07; SX-01–SX-07.
  - §8 in full (TM-01–TM-73 and the count paragraph); D-SEC-15–D-SEC-21.
  - Handoffs H6-07, H6-11, H6-15, H6-16, H7-15, H7-17, H7-18, H8-02, H8-05, H8-17, H8-19–H8-22, H9-03, H9-07, H9-14, H9-15; the traceability excerpt.
  - AU-01, AU-02, AU-07, AU-08, AU-10 and AU-17 compared with c26f31e.
- **PERMISSIONS_MATRIX:**
  - The header, AZ-02, AZ-13, RG-05, RG-06, RG-10–RG-12, the `users.manage` catalogue row and OD-01.
  - The full diff against c26f31e and the capability counts.
- **DATABASE:**
  - The s01-03 header and the s04-00 module map.
  - The s04-01 rows for `users` and `user_external_identities`, compared with c26f31e and 6dbc49a.
  - §28 (the role table, the runtime UPDATE set and the security statements) and the authority-class list of s20-24.
  - The README tables, and the headings of every split file against c26f31e.
- **CONCURRENCY_IDEMPOTENCY:**
  - The header, the source evidence, D-CC-01–D-CC-12 and the TX table.
  - NX-02, CI-09, RV-01–RV-17 and H6-01–H6-16.
  - The §18 introduction and M-38–M-45; HO-23–HO-25, HO-40 and HO-41.
- **PERFORMANCE:** the header, IX-13–IX-19, SZ-09 and the traceability.
- **GAP_REGISTER:** the triage table, the summary paragraph, the GAP-036 continuations and GAP-044–GAP-047. The totals were recomputed.
- **DECISION_LOG:**
  - The index rows for RISK-006, DIR-047, DIR-048 and TECH-025.
  - The DIR-044 entry's statement of (e) and the DIR-047/DIR-048 section in full.
  - The TECH-024 hash-list convention.
- **Other documents:**
  - The DECISION_INDEX rows and the SOURCE_OF_TRUTH provenance of records 41–45 with the registry row.
  - The three P5 entries in CHANGELOG; the PHASE_STATUS diff; CURRENT_HANDOFF in full.
  - V1_SCOPE CAP-13, DEP-10 and DEP-11; ACCEPTANCE_CRITERIA AC-13.
  - The EXECUTION_CONTEXT and COST_POLICY diffs; WORKFLOWS WF-ACC-02; the line-ending note in TOOLCHAIN; ARCHITECTURE (searched).
- **Compliance with DIR-046 §2:**
  - I opened, listed and searched no transcript, history, `.claude`, `tool-results` or scratchpad content. The scratchpad paths named in the gate record were not opened.
  - I created and modified nothing. I ran only read-only git commands, and my checks were in-memory scripts fed on stdin.

## Mechanical checks

1. **Source records — PASS.**
   - There are 45 files.
   - The 40 committed at 6dbc49a are byte-identical to their blobs (40/40 `git hash-object` matches); none has a diff against 6dbc49a.
   - Records 41–45 match their SOURCE_OF_TRUTH SHA-256, line and byte counts:
     - 87B8E1E7…, 47 / 2,072;
     - 820E1214…, 354 / 17,840;
     - FB7D46BE…, 196 / 9,040;
     - B8A49FBC…, 16 / 945;
     - C63F72E1…, 317 / 15,686.
   - Each has a `-text` entry.
   - The round-2 fenced report in the gate record hashes to DE89EA5E…2975 (332 lines, 25,878 bytes), as DIR-048 §1 states.
2. **Branch diff scope — PASS.**
   - BUSINESS_RULES, DOMAIN_MODEL, WORKFLOWS, REFERENCE_COVERAGE, ADMIN_FLOW, DESIGN_SYSTEM and INFORMATION_ARCHITECTURE are untouched.
   - No quality-gate evidence file of an earlier phase is touched.
   - Source records are only added.
3. **Capabilities — PASS.** 80 capabilities, ADM 37 / ADM_PLUS 26 / OWNER_ONLY 17. Only the "Covers" text of `users.manage` changed.
4. **Unchanged rows — PASS.** AU-01, AU-02, AU-07, AU-08, AU-10 and AU-17 are byte-identical to c26f31e after CR removal. The AU-15 window is 15 minutes.
5. **Counts — PASS.**
   - The module map sums to 125 with IAM at 8, over 13 modules; §7.2 has 125 rows.
   - 18 CAP rows; 14 document types.
   - Mentions of 124 appear only in historical notes.
6. **No new logical table or field — PASS.**
   - `users` has exactly four new fields against c26f31e.
   - The `user_external_identities` field list is unchanged against 6dbc49a.
   - IX-19 is an index only. The floor's duration, level and path sit in the existing whitelisted `details` of LG-02.
7. **Threat rows and event kinds — PASS.** 73 TM rows, all unique; 38 security-event kinds.
8. **Gap totals — PASS.** 47 gaps: 3 CLOSED, 37 OPEN, 0 OWNER_DECISION_REQUIRED, 7 ACCEPTED_RISK.
9. **Identifiers — PASS.**
   - IX-19, GAP-047, DIR-047 and DIR-048 are each defined once.
   - No reference points to an undefined identifier.
   - The duplicated AX rows in CONCURRENCY predate the amendment.
10. **Links — PASS, as expected now.** Only `#gate` and `#adversarial-review--round-3` dangle, both in the gate record.
11. **Split documents — PASS.** The headings of every DATABASE and CONCURRENCY file are identical to c26f31e, and the README files are unchanged.
12. **No fail-closed breached-password statement — PASS.** The phrase survives only in the superseded adjustment 7 and the verbatim round-1 summary.
13. **No secret, client ID or placeholder — PASS.**
14. **No operator user row; unlink contract identical — PASS.**
    - RG-11 reads "no user row for an operator, system identity or placeholder".
    - The six unlink reasons and their `unlinked_by` values agree across AU-24, AU-20, DATABASE §4.1/C-63, RV-05, RV-14–RV-16 and H6-11.
15. **Audit event in the transaction, security event after the commit — PASS with one gap.** The incident command's opening and closing sweeps write no audit event (R3-04).
16. **R-01 parameters — PASS.**
    - The threshold, window, progression, cap, decay and success rule agree in AU-05, H6-07, GAP-045, D-SEC-16, TM-63, M-44 and H8-17.
    - The durable floor's fidelity is covered by R3-02 and R3-03.
17. **(e) as extended in the same words — FAIL.**
    - Exact in AU-05, TM-61, TM-73, the §8 count paragraph, GAP-045, the RISK-006 row and continuation, the DIR-047 row and entry, DECISION_INDEX and the gate record.
    - CHANGELOG restates it in other words (R3-08).
18. **Incident protocol (a)–(d) — PASS.** Character-identical in AU-21, RV-17, H8-21, H9-14 and HO-41. Its soundness is covered by R3-01, R3-04, R3-07, R3-13 and R3-15.
19. **R-05 applied the same way by grant, role assignment and activation — PASS** in AU-18, AU-12, D-SEC-18, OD-01, RV-11, M-45, H8-17 and the count paragraph. CI-09 and H7-17 omit activation (R3-10).
20. **Breach alert names its path — PASS** in AU-05, LG-02, LG-07, TM-61, TM-73, GAP-045, RV-12, M-44 and H8-17. Quality: R3-11.
21. **No claim of approval or publication — PASS.**
    - The state documents are worded as of the fourth commit.
    - The TECH-025 per-file hash list is absent, as expected.
    - The gate record's preface would be false at that commit (R3-09).

## Findings

### R3-01 — MEDIUM — MATERIAL — Level 1 (contingent note below): The incident review ignores role assignments and activations, so an attacker-assigned Owner survives the incident response

**Locations**
- SECURITY AU-21 ("first operator recovery (AU-14; AU-20 e) for each Owner account …; then, before any RESET link is issued, the Owner reviews the accounts, grants, credential links and Google links created since the suspected leak").
- SECURITY H9-14 (the same list), H8-21 ("the Owner's review of what was created since the suspected leak") and AU-14 (the command serves any "account holding the OWNER role").
- CONCURRENCY RV-04 (one Owner may demote another while one Owner remains).

**Problem**
- In the leak window the attacker can act as any signed-in Owner. With APP_KEY and the `sessions` table they can forge the encrypted cookie of a live session, and they can also decrypt TOTP secrets.
- From an Owner account they can assign the OWNER role to an existing account or reactivate a deactivated one.
- Neither is "created since the suspected leak", so the review does not cover it.
- Operator recovery is ordered for "each Owner account" before any review. The operator verifies only the person's identity, not whether the role is legitimate.

**Failure scenario**
1. Insider X holds an ordinary Admin account.
2. The attacker, through a hijacked Owner session, assigns X the OWNER role.
3. The incident run voids everything.
4. The operator recovers every Owner account, X's included. X is a real employee, so the in-person check passes.
5. X is now an Owner with a fresh password and TOTP. X can demote or deactivate the legitimate Owner (RV-04) before, or despite, the review, which does not list role changes.
6. A reactivated account of a former employee who colludes with the attacker gets a "plain RESET link" like every other account.

**Recommended fix**
- Extend the review in AU-21, H8-21 and H9-14 to every account, role, grant, activation, credential-link and Google-link change since the suspected leak, read from the audit events RG-11 guarantees.
- In the runbook (H9-14, H9-07), the operator recovers first the accounts whose OWNER role predates the suspected leak, confirmed from the audit trail. An account given the OWNER role, or reactivated, since then is recovered or issued a link only after a recovered Owner's review confirms it.
- Add the case to H8-21.
- *Contingent:* if R-02's "AU-14 operator recovery for each Owner account" is read as requiring recovery of such an account before any review, the gating part becomes Owner-level. The review extension is Level 1 either way.

### R3-02 — LOW — MATERIAL — Level 1: After a store loss, the "stricter of" rule does not bound concurrent submissions

**Locations**
- SECURITY AU-05 *Durability* and WS-12 ("cannot loosen it").
- CONCURRENCY D-CC-10, H6-07, RV-12 ("the cooldown, the escalation level and the count read as the stricter of the store and the durable floor") and M-44 (expected "at most five codes evaluated in the attempt window; one breach").
- PERFORMANCE SZ-09 note ("loosens nothing"); HO-24; the gate's Level-1 choice ("so the floor cannot lag the decision").

**Problem**
- Concurrency is bounded only by the store's atomic place-taking, which restarts at zero after a restart, an eviction or a failover.
- The floor is a non-atomic read that can only say "the count is at least F".
- Taking the maximum of a fresh place and F + 1 lets several submissions all sit at the threshold. Adding the two double-counts while the store is intact.
- No document says how the two are combined atomically.

**Failure scenario**
1. Four failures are recorded in the current window; Valkey restarts.
2. Five concurrent submissions from five addresses take places 1–5 on the empty key. Each reads F = 4 and computes an effective place of max(p, 5) = 5: at the threshold, not beyond it.
3. All five codes are evaluated where R-01 allows one, and each failure counts as "the fifth", producing up to five breach events.
4. That is up to threshold − 1 extra guesses per loss event, and the SZ-09 and WS-12 claims are not exactly true.

**Recommended fix**
- Before the increment, seed the store's count, level and cooldown keys atomically from the floor: set-if-absent with the window's TTL, or one atomic script that raises the value to the floor and then increments. Define "stricter of" as this seeding.
- Add "concurrent submissions immediately after a restart, an eviction or a failover" to M-44 and H8-17.
- State how "excluded from eviction" is realized. Valkey's eviction policy is instance-wide, and HO-24's policy makes limiter keys the eviction candidates. The options are a separate store with no eviction, or keys without a TTL under a volatile policy.

### R3-03 — LOW — MATERIAL — Level 1: The durable floor can count failed enrolment confirmations, which R-01 excludes

**Locations**
- SECURITY AU-05 (*excluded:* "the confirmation of an enrolment … which stays under the per-account-and-IP and per-IP throttles only"; "each failure writes TWO_FACTOR_FAILED").
- PERFORMANCE IX-19 (predicate on `event_kind` only); CONCURRENCY D-CC-10, H6-07, RV-14 ("checks the code … as RV-12 finds a step"; the failure path is unstated).
- SECURITY LG-02 (TWO_FACTOR_FAILED is not defined) and LG-07 (">20 TWO_FACTOR_FAILED … in an hour").

**Problem**
- The floor counts every TWO_FACTOR_FAILED of the account, and it is applied on every covered step, not only after a store loss.
- No document says that a failed enrolment confirmation writes no TWO_FACTOR_FAILED. The natural implementation reuses RV-12's failure path, which does write it.

**Failure scenario**
1. A person replacing a device (path a) mistypes the new device's confirmation code four times.
2. Their next current-code step, or their next sign-in challenge within 15 minutes, starts with a floor count of 4. One more typo starts a cooldown.
3. This contradicts R-01's exclusion, and the LG-07 hourly signal fires on enrolment typos.

**Recommended fix**
- State in AU-05, LG-02 and RV-14 that TWO_FACTOR_FAILED is written only by a covered step.
- A failed enrolment confirmation writes none, or writes another kind or reason that IX-19's predicate excludes.
- Add the case to H8-17 and M-44.

### R3-04 — LOW — MATERIAL — Level 1: The incident command's sweeps revoke credential links without any audit event

**Locations**
- SECURITY AU-21 (a) and (d), and "one business audit event in that account's transaction".
- CONCURRENCY RV-17, TX-07 and CI-09 ("one transaction per account").
- PERMISSIONS_MATRIX RG-11 ("Every account, role, grant and credential-link change is a business audit event with before/after, actor, reason and authority class"); SECURITY LG-01 ("credential-link issue and revocation").

**Problem**
- The opening sweep revokes every unused token before the per-account transactions run. The closing sweep revokes tokens created during the run.
- No audit event, actor, transaction class or lock order is stated for either sweep. The per-account events then find nothing left to revoke.

**Failure scenario**
1. The opening sweep revokes a RESET link the attacker issued an hour earlier.
2. No audit event records that revocation, its operator actor or the run.
3. The Owner's review, and the reconciliation of H8-05, see a revoked link with no revocation event.
4. RG-11, an approved rule, is not met.

**Recommended fix**
- Each sweep writes, for each account whose links it revokes, an audit event with the operator's identity as AU-14 records it, authority class SYSTEM and the run identifier.
- State the sweeps' transaction class and their lock order (LK-03 before LK-04 and LK-05).
- Assert it in H8-21.

### R3-05 — LOW — MATERIAL — Level 1: The R2-05 re-read compares against a digest that a Google-established session never defines

**Locations**
- CONCURRENCY RV-10 ("The session keeps a keyed digest of the password hash its login verified … never of a hash read afterwards"), RV-12 and RV-14 (the R2-05 re-read "compare it with the digest the session keeps (RV-10)").
- SECURITY AU-06, AU-16 and AU-22.

**Problem**
- A Google sign-in verifies no password, and no document says which password digest a session established by Google and TOTP keeps.
- R2-05 now makes that digest decide every non-challenge code step and every confirmation.

**Failure scenario**
1. An implementer stamps nothing on Google sessions. RV-10's per-request check then either ends every such session or skips them.
2. If it skips them, a device replacement (path a) in a Google session races an Owner's credential reissue (RV-15).
3. The replacement commits second and, as its last statements, revokes the account's unused links, including the RESET link just issued. That is the race R2-05 closed.

**Recommended fix**
- In RV-10, AU-06 and AU-22, state that a Google-established session is stamped, when its challenge is accepted, with the digest of the password hash its challenge's digest bound (re-read under LK-03 and found equal).
- Add a Google-session case to M-45 and H8-17.

### R3-06 — LOW — MATERIAL — Level 1: The R2-11(d) rename removed the alert for an Owner's reissue of an account without TOTP

**Locations**
- SECURITY AU-20 ("every Owner reset of an account, alerts (LG-07)"); AU-20 c (for an account without TOTP: "PASSWORD_RESET_REQUESTED and, if a link existed, GOOGLE_UNLINKED, never TOTP_RESET").
- CONCURRENCY RV-15.
- SECURITY LG-07 (alerts "every TOTP reset of any account by an Owner"; no PASSWORD_RESET_REQUESTED) and H9-15.

**Problem**
- Before R2-11(d) this reset wrote TOTP_RESET and was alerted.
- It now writes PASSWORD_RESET_REQUESTED, which LG-07 does not list, so AU-20 and LG-07 disagree.

**Failure scenario**
- An Owner is deceived into reissuing the credentials of an Admin without TOTP (TM-68, K4).
- Neither the operator nor the other Owners are alerted, because P9 builds H9-15 from LG-07's list.

**Recommended fix**
- LG-07 alerts every Owner reset under AU-20 c and d, with or without TOTP.
- Give the reset command's PASSWORD_RESET_REQUESTED a reason code, for example CREDENTIAL_REISSUE, so it can be told apart from an ordinary link issue.
- Assert it in H8-19.

### R3-07 — LOW — NON-MATERIAL — Level 1: Who enables maintenance mode, when, and how the command verifies it are unstated, and the wording collides with "no maintenance window"

**Locations**
- SECURITY AU-21 (b), H8-21 and H9-14; CONCURRENCY RV-17 ("while it runs no web request is served") and HO-41.
- The gate's Level-1 choice ("any created before maintenance mode took effect").
- The phrase "no … maintenance window" in AU-21, RV-17 and DATABASE §28.

**Problem**
- No document fixes that maintenance mode is enabled before the opening sweep, without a bypass secret.
- Nor does it say that the command refuses to start or continue unless maintenance mode is active, that every web process honours the flag (the framework's file flag is per filesystem, and P9 has not fixed the process layout), or that it is lifted only after the closing sweep.
- A zero-context reader also sees "no maintenance window" next to "maintenance mode" as a contradiction.

**Failure scenario**
- The operator forgets the step, or one web process does not see the flag.
- A non-high-risk account with a cracked 8-character password signs in during the run, and RV-17's claim is false.
- The impact is limited: the new APP_KEY that comes first makes TOTP secrets undecryptable, so no Owner can pass the challenge, and the closing sweep and the re-read clean up.

**Recommended fix**
- State these steps and the command's check in AU-21, RV-17 and H9-14.
- Write "no database maintenance window (TX-08)" where "no maintenance window" now appears.

### R3-08 — LOW — NON-MATERIAL — Level 1: CHANGELOG restates (e) in other words

**Location:** CHANGELOG "Unreleased — 2026-10-04, P5 authentication amendment second continuation", first bullet ("the residual (e) now covers anyone … — a password holder, a browser still signed in to the person's linked Google account, …").

**Problem:** DIR-048 §2 requires (e) "in the same words … anywhere else that states (e)". This bullet fails the gate check of DIR-048 §5 step 6 as written.

**Recommended fix:** Quote (e) exactly, or refer to it without restating it.

### R3-09 — LOW — NON-MATERIAL — Level 1: The gate record's preface would be false at the fourth commit

**Location:** gate record, opening paragraph.

**Problem**
- It names DIR-043, DIR-045 and DIR-046 only, not DIR-047 or DIR-048.
- It records "the two rounds of the adversarial review" and "the gate … with the additions of DIR-045 and DIR-046".
- At the fourth commit there will be at least three rounds, and the gate will carry the additions of DIR-048 §5 step 6.

**Recommended fix:** Update the preface when round 3 and the gate are written.

### R3-10 — LOW — NON-MATERIAL — Level 1: Activation is missing from CI-09 and H7-17, and the onboarding of a new high-risk account is not described

**Locations**
- CI-09 ("a role or grant change that makes an account high-risk and a deactivation are SF-CMD commands of NX-02").
- H7-17 ("the RESET link a high-risk grant issues").
- WF-ACC-02 (create → role → grants → activate) and AU-12.

**Problem**
- Under R2-01, every new high-risk account without TOTP is activated into R-05.
- Any ONBOARDING link issued at creation is revoked and replaced by a 60-minute RESET link, shown once to the acting Owner at the activation.
- Neither P6's command list nor P7's handoff says this.

**Failure scenario:** P7's activation screen does not show the one-time RESET link, and the Owner must reissue it.

**Recommended fix:** Name role assignment and activation in CI-09 and H7-17, and state the consequence for a new account in AU-12 or H7-17.

### R3-11 — LOW — NON-MATERIAL — Level 1: The breach alert names only the fifth failure's path

**Locations:** SECURITY AU-05, LG-02 (the path is carried only by TWO_FACTOR_COOLDOWN_STARTED) and LG-07.

**Problem**
- With one shared count, failures from different paths add up. The fifth failure's path need not be the one the Owner should close.
- SESSION_STEP does not identify which session to end.

**Failure scenario**
1. Four failures come from a shared PC through the Google path.
2. The person's own typo at password sign-in makes the fifth.
3. The alert says "password sign-in", and the Owner leaves the Google identity linked.

**Recommended fix**
- Record the path in each TWO_FACTOR_FAILED's whitelisted `details`; for SESSION_STEP, also a reference to the session's device summary (AU-10). This adds no field.
- Have the breach alert list the counted failures by path.
- State that a recovery-code sign-in counts as PASSWORD.

### R3-12 — LOW — NON-MATERIAL — Level 1: Two residuals in the §8 count paragraph carry no stated disposition

**Location:** the SECURITY §8 count paragraph.

**Problem**
- "Whoever consumes a credential link before its holder can also enrol the device … (TM-67; AU-12)" is the residual of TM-52 approved under APPR-006, extended to enrolment. It is not a new acceptance.
- "Google-side changes are seen only at the next Google sign-in (AU-24)" is the behaviour DIR-043 §7.8 specifies.
- Neither entry says so, which makes the gate's "every residual has a disposition" check a matter of judgement.

**Recommended fix:** Cite APPR-006/TM-52 and DIR-043 §7.8 in the paragraph.

### R3-13 — LOW — NON-MATERIAL — Level 1: The K3 last-resort rule in AU-14 and H9-07 collides with "operator recovery for each Owner account"

**Locations**
- AU-14 ("the runbook uses it only when neither can serve").
- H9-07 ("no other active Owner can perform AU-20 d").
- AU-21 and H9-14 (each Owner account, per DIR-044 R-02).

**Problem**
- Once a first Owner is recovered and enrolled, "another active Owner" exists.
- The runbook's K3 check would then refuse the second operator recovery that AU-21 orders.

**Recommended fix:** In AU-14 and H9-07, state that after the incident command every Owner account is recovered through the operator command as DIR-044 R-02 orders. Alternatively, state that all Owner links are issued before any is used.

### R3-14 — LOW — NON-MATERIAL — Level 1: How a sole Owner's own targeted account closes the path is unstated

**Locations**
- GAP-045 "Mitigation boundary" (the Owner can unlink, end the session or reset).
- AU-20 c and AU-24 (neither may target the acting Owner); H9-07 ("no usable recovery code").

**Problem**
- Suppose the target is the sole Owner's own account, attacked through the Google path, and the Owner has no live session.
- None of the listed remedies is available to that Owner.
- Recovery codes are refused while each cooldown runs, so the Owner must win a race at each cooldown's end against an automated holder of the Google session.
- H9-07 could refuse operator recovery because codes "exist".
- This is within the accepted (e), but the way out is not written down.

**Recommended fix**
- State the remedies for the Owner's own account:
  - the SELF unlink from any live session, which needs no code;
  - another Owner's OWNER unlink;
  - otherwise path (e), which unlinks Google.
- State that H9-07 treats codes that a sustained cooldown prevents from being used as not usable.

### R3-15 — LOW — NON-MATERIAL — Level 1: The incident runbook rotates only APP_KEY and the HMAC keys

**Locations:** SECURITY AU-21, SX-02 and H9-14; SX-01.

**Problem**
- The likeliest way to get APP_KEY together with the database is a leak of the environment configuration.
- That configuration also holds the database role credentials, the Valkey password and the Google client secret, and the runbook does not rotate them.

**Recommended fix:** H9-14 rotates every SX-01 secret held with the leaked key (the Google client secret as SX-02 describes), unless P9's inventory shows the leak could not have included them.

## Residual-stating threat rows (scope C)

| Row | Residual | Disposition |
| --- | --- | --- |
| TM-06 | via TM-69 and TM-70; GAP-033 | K4 (TM-69, TM-70). GAP-033 is OPEN until approval and P8/P9 evidence |
| TM-57 | operator abuse of the recovery command | K4 — GAP-041 (RISK-005) |
| TM-61 | cooldown triggered from a Google-signed-in browser | (e) as extended — GAP-045 (RISK-006) |
| TM-68 | an Owner deceived into a reset | K4 — GAP-040 |
| TM-69 | offline guessing of 8-character passwords | K4 — GAP-038 |
| TM-70 | real-time phishing | K4 — GAP-039 |
| TM-71 | a breached password set during a range-service outage | (f) — GAP-046 (RISK-007) |
| TM-72 | one phone holding the Google session and the authenticator | Open gap GAP-047, OPEN. Owner: the Owner's decision at the approval of the amendment. Phases P7 (H7-16) and P8 (H8-20). No acceptance is claimed, so the rule is met |
| TM-73 | the deliberate cooldown | (e) as extended — GAP-045 |
| TM-67 (count paragraph) | a link consumed first lets the consumer enrol | Within the TM-52 residual approved under APPR-006; the label is missing (R3-12) |
| AU-24 (count paragraph; no TM row) | Google-side changes seen only at the next Google sign-in | Behaviour specified by the Owner in DIR-043 §7.8; the label is missing (R3-12) |

- TM-63's "about 480 a day at the cap" is the bound of the Owner's R-01 decision, alerted at every breach, and states no residual.
- The other entries of the count paragraph predate the amendment and were approved with it under APPR-006: GAP-032, pool evidence, office files, credential links, egress, `stock.view` timing and host trust.
- No accepted risk beyond K4, (e) as extended and (f) is claimed anywhere.

## Interactions checked

- **R-01:** sound apart from R3-02, R3-03, R3-11 and R3-14.
- **R-02:** sound against in-flight requests. The conditional updates, the R2-05 re-read and the closing sweep defeat late consumptions, password changes, confirmations, links and logins. Remaining issues: R3-01, R3-04, R3-07, R3-13 and R3-15.
- **R-05:** consistent across grant, role assignment and activation, apart from R3-05, R3-06 and R3-10.
- **Paths a–f:** no unsafe path found.

## Summary

- **By severity:** HIGH 0; MEDIUM 1 (R3-01); LOW 14 (R3-02–R3-15).
- **By materiality:** MATERIAL 6 (R3-01–R3-06); NON-MATERIAL 9 (R3-07–R3-15).
- **Focused round:** R3-01, R3-02 and R3-03 change the design of a control, so they qualify for the one further focused round of DIR-048 §5 step 5.
- **OWNER-LEVEL:** none. Contingent: R3-01's gating of operator recovery, only if R-02's "each Owner account" is read as requiring recovery of an account given the OWNER role during the suspected compromise window before any review.
````

## Dispositions of round 3

No finding needs an Owner decision. Every finding is resolved at Level 1 under DIR-048 §5 step 5, following the reviewer's recommended fix unless the row says otherwise.

| Finding | Disposition | Where it now stands |
| --- | --- | --- |
| R3-01 | Level 1 — the post-incident review covers every account, role, grant, activation, credential-link and Google-link change since the suspected leak, read from the audit events RG-11 guarantees; the operator first recovers the Owner accounts whose OWNER role predates the suspected leak, and an account given the OWNER role, or activated, since then is recovered or issued a link only once a recovered Owner's review confirms it, otherwise the Owner removes the role or deactivates it through the ordinary commands; if no OWNER role predates the leak, every Owner account is recovered first. Reading of the contingent note: DIR-044 R-02 names the paths recovery uses — operator recovery for each Owner account, then RESET links — and does not order an account whose OWNER role the attacker may have given to be recovered before any review; ordering the recovery keeps R-02's paths and its intent, so no Owner decision is needed | SECURITY AU-21, H8-21, H9-14 |
| R3-02 | Level 1 — "the stricter of" is one atomic operation of the limiter store that raises the count, level and cooldown to at least the durable floor before the place is taken, so concurrent submissions stay bounded right after a store loss; the keys carry no expiry of their own and hold time-stamped entries the operation trims, so the HO-24 policy, which evicts only keys with an expiry, never selects them | SECURITY AU-05; CONCURRENCY_IDEMPOTENCY D-CC-10, H6-07, RV-12, M-44, HO-24; PERFORMANCE SZ-09; SECURITY H8-17 |
| R3-03 | Level 1 — TWO_FACTOR_FAILED is written only by a covered step; a failed enrolment confirmation takes no place in the count and writes none, only the per-account-and-IP and per-IP throttles applying | SECURITY AU-05, LG-02, H8-17; CONCURRENCY_IDEMPOTENCY RV-14, M-44 |
| R3-04 | Level 1 — each sweep removes an account's sessions and unused credential links in one TX-07 transaction per account, LK-03 before LK-04 and LK-05, with an audit event carrying the operator's identity as AU-14 records it, authority class SYSTEM and the run identifier; session rows without an account are deleted last without one | SECURITY AU-21, H8-21; CONCURRENCY_IDEMPOTENCY RV-17, HO-41 |
| R3-05 | Level 1 — a session established by Google and TOTP keeps the digest of the password hash its challenge's digest bound, re-read under the account row when the challenge is accepted | SECURITY AU-06, AU-22, H8-17; CONCURRENCY_IDEMPOTENCY RV-10, M-45 |
| R3-06 | Level 1 — LG-07 alerts every Owner reset under AU-20 c and d, with or without TOTP; the reissue's PASSWORD_RESET_REQUESTED carries reason CREDENTIAL_REISSUE | SECURITY AU-20 c, LG-02, LG-07, H8-19; CONCURRENCY_IDEMPOTENCY RV-15 |
| R3-07 | Level 1 — the operator enables the maintenance mode on every web process before the opening sweep, its flag placed where every process reads it, and lifts it only after the closing sweep; the command refuses to start or to continue without it; "no maintenance window" now reads "no database maintenance window (TX-08)" | SECURITY AU-21, H8-21, H9-14; CONCURRENCY_IDEMPOTENCY RV-17, HO-41; DATABASE §28 |
| R3-08 | Level 1 — CHANGELOG refers to (e) without restating it | CHANGELOG |
| R3-09 | Level 1 — the preface of this record names DIR-047 and DIR-048, every review round and the gate's additions | this record |
| R3-10 | Level 1 — role assignment and activation named in CI-09 and H7-17; for an account created with a high-risk role or grants, the activation revokes any unused ONBOARDING link and issues the RESET link instead | CONCURRENCY_IDEMPOTENCY CI-09; SECURITY AU-12, H7-17 |
| R3-11 | Level 1 — every TWO_FACTOR_FAILED carries its path in its details — a recovery-code sign-in counting as PASSWORD, a session step with the session's device summary —, and the breach alert lists the counted failures by path | SECURITY AU-05, LG-02, LG-07, H8-17; CONCURRENCY_IDEMPOTENCY RV-12, M-44 |
| R3-12 | Level 1 — the count paragraph labels the credential-link residual as TM-52's, approved under APPR-006 and extended to enrolment, and the Google-side residual as the behaviour DIR-043 §7.8 specifies | SECURITY §8 count paragraph |
| R3-13 | Level 1 — after the incident command every Owner account is recovered through the operator command as DIR-044 R-02 orders, in the order of AU-21, whether or not another Owner is active | SECURITY AU-14, H9-07 |
| R3-14 | Level 1 — the way out for an Owner's own targeted account: the SELF unlink from any live session, another Owner's OWNER unlink, or else operator recovery; the runbook counts recovery codes that a sustained cooldown keeps from being used as not usable | GAP-045; SECURITY H9-07 |
| R3-15 | Level 1 — the incident runbook also rotates every other secret of SX-01 the leak may have included, unless P9's inventory shows it could not have | SECURITY AU-21, SX-02, H9-14 |

R3-01, R3-02 and R3-03 change the design of a control, so one further focused round runs on those changes only ([round 4](#adversarial-review--round-4-focused)).

## Adversarial review — round 4 (focused)

Run on 2026-10-04 under DIR-048 §5 step 5 by a fresh agent with no prior context, bound by the rule of DIR-046 §2, read-only, on the changes of round 3 that alter a control's design — R3-01 (with R3-13), R3-02 and R3-03 — and on whether they broke the incident protocol or the wording of (e). The report below is recorded verbatim, as the agent returned it to the executor; a copy is kept outside the repository at `C:\Users\YUSUFS~1\AppData\Local\Temp\claude\C--Projects-MultipleCorp\2dc7d0a3-0abc-47f2-a842-c97e1ca046a8\scratchpad\P5_AUTH_ADVERSARIAL_REVIEW_ROUND4_2026-10-04.md` (348 lines, 27,688 bytes, SHA-256 `19B335F02A4D1CE1A224540D3D7492D5725E81201A17097A296695BAA7A56AAD`).

**Status at the stop (2026-10-04).** The executor confirmed that finding R4-01 needs an Owner decision: when every Owner whose role predates the suspected leak has been demoted or deactivated during the leak window — which PERMISSIONS_MATRIX RG-09 and CONCURRENCY_IDEMPOTENCY RV-04 allow while another active Owner remains —, the operator command of SECURITY AU-14 refuses those accounts and no existing path restores a legitimate Owner, so closing it needs a path beyond DIR-044 R-02's "existing paths only", a restore from a backup, a supervised recovery or the acceptance of a residual beyond K4, (e) and (f). The amendment stopped under DIR-043's stop conditions 6 and 7 (stop condition 7 as DIR-048 §4 reads it) before any repair: no finding of round 4 has a disposition yet; the round-3 dispositions stand in the working tree; the gate and the zero-context check have not run; the fourth commit does not exist and nothing is pushed.

````markdown
# Adversarial review, round 4 (focused): P5 authentication amendment

Reviewer: an independent agent with no prior context. I worked read-only on 2026-10-04 on the working tree of `amendment/p5-auth`: HEAD 6dbc49a plus the uncommitted fourth-commit changes and the untracked source records 41–45 and gate record.

Scope, under DIR-048 §5 step 5: the three control-design changes that round 3 caused.
- R3-01: the post-incident recovery order and review, with the related R3-13 additions to AU-14 and H9-07.
- R3-02: the durable floor of the second-factor limit, taken as "the stricter of" in one atomic seeding operation, on keys without expiry.
- R3-03: failed enrolment confirmations excluded from the count and from the floor.

Also in scope: whether these changes broke the incident protocol (a)–(d) or the residual (e) wording.

**Result:** 8 findings. By severity: 0 HIGH, 2 MEDIUM, 6 LOW. By materiality: 5 MATERIAL, 3 NON-MATERIAL. One is OWNER-LEVEL (R4-01).

## What I read

- **Owner records:**
  - DIR-048 (P5_AUTH_SECOND_CONTINUATION_OWNER_AUTHORIZATION_2026-10-04.txt), in full: §2, §3 (R2-02 and R2-03), §4 (guards) and §5.
  - DIR-045 (P5_AUTH_CONTINUATION_OWNER_AUTHORIZATION_2026-10-03.txt), in full: §2, with R-01, R-02, R-05 and the residuals (e) and (f).
- **Gate record** (docs/05-security/evidence/P5_AUTH_AMENDMENT_GATE.md):
  - the round-2 dispositions;
  - the Level-1 choices of the second continuation;
  - review round 3, verbatim;
  - the "Dispositions of round 3".
- **SECURITY:**
  - rows AU-04 (by search), AU-05, AU-11, AU-14, AU-15, AU-16, AU-20, AU-21;
  - rows WS-12, LG-02, LG-07, D-SEC-16, TM-63;
  - handoffs H6-07, H6-16, H8-17, H8-21, H9-07, H9-09, H9-14.
- **PERMISSIONS_MATRIX:** RG-09, RG-10 and RG-11.
- **CONCURRENCY_IDEMPOTENCY:**
  - s00-03: D-CC-10, TX-07, TX-08, and the source rows for Laravel Cache and Scheduling;
  - s14-17: RV-04, RV-05, RV-10–RV-14, RV-17, H6-07, H6-16;
  - s18: M-44 and M-45;
  - s19-20: HO-24 and HO-41.
- **PERFORMANCE:** IX-19 and SZ-09. I also read HO-24 and SZ-09 as they stood at c26f31e.
- **DATABASE:**
  - §28: the role table and the runtime role's privileges;
  - the `audit_events` row of s04-13;
  - the `users` and `user_external_identities` rows of s04-01, compared with 6dbc49a.
- **WORKFLOWS:** WF-ACC-01 and WF-ACC-02.
- **Searches across docs/ and CHANGELOG:**
  - every occurrence of the residual (e);
  - every occurrence of TWO_FACTOR_FAILED;
  - every statement of the Owner recovery order and of "suspected leak";
  - every statement that a breach starts a new attempt window;
  - how the first Owner account is created (I found none).
- **Compliance with DIR-046 §2:**
  - I opened, listed and searched no transcript, conversation log, history, `.claude` directory, `tool-results` folder or scratchpad. The scratchpad path named in the gate record was not opened.
  - I created and modified nothing. I used only read-only git commands (`status`, `diff`, `show` of committed blobs) and Python scripts that only read files in memory.

## Checks

1. **Incident protocol (a)–(d) character-identical in AU-21, H8-21, H9-14, RV-17 and HO-41 — PASS.** One match in each row, 463 characters, byte-identical.
2. **Residual (e) identical wherever it is stated — PASS.**
   - 11 occurrences match exactly: SECURITY ×4, GAP_REGISTER, DECISION_LOG ×4, DECISION_INDEX and the gate record.
   - The only other wording is the historical DIR-044 entry in DECISION_LOG. It quotes the original (e) and adds "DIR-047 later extends (e)".
   - CHANGELOG refers to (e) without restating it.
3. **Durability passage identical in AU-05, D-CC-10 and H6-07 — PASS.** From "the limit is enforced from the stricter of two sources" to "no failover store serves them": 1,417 characters, identical in all three.
4. **R-01 reproduced exactly — FAIL in part.** These elements agree:
   - the threshold of five;
   - the rolling 15-minute window;
   - the progression 1/2/4/8/15 with the 15-minute cap;
   - the escalation level over 24 hours;
   - a success resetting nothing;
   - the exclusion of enrolment confirmation (AU-05, H6-07, LG-02, RV-14, H8-17, M-44).

   Two things fail:
   - "A breach starts a new attempt window" appears only in AU-05 (R4-04).
   - The floor's and the seeding's time arithmetic does not reproduce the decay and threshold exactly (R4-05).
5. **Concurrency bound after a store restart, eviction or failover — PASS.**
   - The floor is read before the atomic operation. The operation raises the store to the floor and then increments, so submissions that arrive after a loss get places beyond the threshold.
   - Remaining margin, not a finding: submissions already in flight at the instant of the loss can add at most their own number. The claim "never … reduces the count below the record" still holds, because those failures are not yet recorded.
6. **Fail closed — PASS.** The step is refused in each of these cases, stated in AU-05, D-CC-10, H6-07, RV-12, H8-17 and M-44:
   - the floor cannot be read;
   - a failure or breach event cannot be written;
   - the store is down.
7. **Keys without expiry, and their trimming — PASS with caveats.** Keys stay bounded by the number of accounts. Caveats: R4-05 (seeded entry times) and R4-08 (framework limiter and lock eviction).
8. **No new logical table or field — PASS.**
   - IX-19 is a partial index on existing `security_events` columns.
   - The floor's duration, level and path sit in the whitelisted `details` object.
   - The `users` and `user_external_identities` rows add no identifier against 6dbc49a.
   - The store keys are not database objects.
9. **R3-03 — PASS (wording: R4-07).**
   - A failed enrolment confirmation takes no place in the count and writes no TWO_FACTOR_FAILED, so the IX-19 predicate cannot see it.
   - It stays throttled per account and IP and per IP.
   - Path b: the recovery-code sign-in stays covered (RV-13); the new-device confirmation is excluded.
   - Device replacement: the current code stays covered (AU-15, AU-20 a); the new-device confirmation is excluded.
   - The confirmation checks only a secret just shown in the same session, so it gives no oracle on the existing secret.
10. **R3-01 soundness against an attacker who assigned the OWNER role or reactivated an account — FAIL** (R4-01, R4-02, R4-03).
11. **R-02 kept — PASS for the main path, FAIL in the cases of R4-01.**
    - Main path: R-02's paths and their order are kept — operator recovery for Owner accounts, then Owner-issued RESET links. Gating window-assigned Owners on the review is a Level-1 refinement consistent with the review DIR-048 R2-03 placed before the RESET links.
    - In the cases of R4-01, no existing path can restore a legitimate Owner. Those cases need the Owner.
12. **Consistency of AU-21, AU-14, H8-21, H9-07 and H9-14 — FAIL in part.** H8-21 and H9-14 omit the fallback and the "otherwise" outcome (R4-02). The "recovered, or issued a link" pairing is ambiguous (R4-06).

## Findings

### R4-01 — MEDIUM — MATERIAL — OWNER-LEVEL: Demoting or deactivating the pre-leak Owners defeats the recovery order, and no existing path restores them

**Locations**
- SECURITY AU-21:
  - "First, operator recovery (AU-14; AU-20 e) for each Owner account whose OWNER role predates the suspected leak, as the audit trail shows";
  - "an account given the OWNER role, or activated, since the suspected leak is recovered, or issued a link, only once that review confirms it";
  - "If no OWNER role predates the suspected leak, every Owner account is recovered first and the review follows."
- SECURITY AU-14: the command issues an OWNER_RECOVERY link "for an account holding the OWNER role and refuses any other account".
- SECURITY AU-04: an inactive account gets the generic login failure. AU-11: links are invalidated on deactivation.
- SECURITY H8-21, H9-07 and H9-14.
- PERMISSIONS_MATRIX RG-09 and RG-10; CONCURRENCY RV-04 and RV-05.

**Problem**
- The recovery order classifies accounts by the age of the OWNER role they now hold.
- During the leak window, an attacker acting as an Owner can do the following. This is the premise R3-01 accepted: a password cracked offline (TM-69, K4) plus a TOTP secret decrypted with the leaked key, or a hijacked live session.
  - Give the OWNER role to an account X: a colluding employee, or an account the attacker creates and activates. R-05 shows X's RESET link to the acting "Owner".
  - Then remove the OWNER role from every pre-leak Owner, or deactivate them. RV-04 allows this while X remains an active Owner.
- After the incident, two things block the legitimate Owners:
  - AU-14 refuses a demoted account.
  - A deactivated account cannot sign in after using its link.
- Only an active Owner can restore the role or the active flag. No existing operator path does it.
- Separately, the gating removes something R-02 provided: if the only pre-leak Owner is unavailable (incapacitated or departed), a legitimate Owner added during the window can never be confirmed, and so is never recovered. Read literally, R-02 recovers every Owner account.

**Failure scenario**
1. In the window, the attacker (as Owner A) assigns OWNER to X, sets X's password through the RESET link and enrols X's TOTP. Through X, it removes A's OWNER role.
2. The leak is detected, and the incident command voids everything.
3. If the fallback is read as applying (no current OWNER role predates the leak):
   - X is "every Owner account" and is recovered first, without review.
   - A colluding X passes the in-person check, becomes the sole Owner and performs the review itself.
   - Only X can restore A.
4. If it is read as not applying (A's role did predate the leak):
   - The operator cannot recover A: AU-14 refuses A, or A is inactive.
   - X waits for a review that no recovered Owner can perform.
   - The company has no path back to a legitimate Owner.

**Recommended fix**
- *Level 1:* H9-14 and H8-21 detect, from the audit trail, any pre-leak Owner demoted or deactivated since the suspected leak. In that case the runbook neither falls back nor recovers a window-assigned Owner; it halts and escalates to the Owner.
- *Owner decision*, choosing one of:
  - (i) a new audited operator console command that restores, for a person the operator verifies in person, the OWNER role and the active flag the account held at the suspected leak. It would run under the runtime role and add no table or field, but it is a path beyond R-02's "existing paths only".
  - (ii) restoring the IAM state from the backup taken before the suspected leak, accepting what that means for later data.
  - (iii) recovering the window-assigned Owner first, under supervision, so that its first act restores the pre-leak Owner — which trusts that person.
  - (iv) accepting the residual. This lies beyond K4, (e) and (f), so it falls under stop condition 7.
- The Owner should also decide whether a legitimate Owner added during the window may be recovered without a pre-leak Owner's review when none is available.

### R4-02 — MEDIUM — MATERIAL — Level 1: The first group and the fallback are defined by role age alone, which brings back the R3-01 scenario

**Locations**
- SECURITY AU-21 (the three sentences quoted in R4-01).
- SECURITY H8-21 and H9-14: they state neither the fallback nor the "otherwise the Owner removes the role or deactivates the account" outcome.
- SECURITY H9-07.
- PERMISSIONS_MATRIX RG-10 and CONCURRENCY RV-05: a deactivation revokes grants and keeps the role.

**Problem**
- **(a) A reactivated former Owner fits both rules.** A former Owner deactivated before the leak keeps the OWNER role. If the attacker reactivates it in the window, it is both:
  - "an Owner account whose OWNER role predates the suspected leak", recovered first and unreviewed; and
  - "an account … activated since the suspected leak", gated on the review.

  No precedence is stated.
- **(b) The fallback recovers every Owner unreviewed, and it is the ordinary case in practice.** It applies in two situations:
  - the leak cannot be dated, so the conservative "suspected leak" is the leaked key's creation in the dated key register — and a key generated at installation predates every OWNER role;
  - the first Owner's role has no audit event. The documents never say how the first Owner is created, so "as the audit trail shows" may be unable to show it.
- **(c) Undefined inputs.** Nothing says who sets "the suspected leak", or what is used when it cannot be dated.
- **(d) Handoffs.** H8-21 and H9-14 carry neither the fallback nor the "otherwise" outcome, so the P8 tests and the P9 runbook omit both.

**Failure scenario (a)**
- An attacker reactivates a departed co-owner F who colludes.
- The operator recovers F first, because F's role predates the leak.
- F then demotes the legitimate Owner (RV-04) before, or despite, the review.

**Failure scenario (b)**
1. The key has never been rotated since installation, and the leak is found in an undated public paste.
2. The suspected leak is set at the key's creation, so no OWNER role predates it.
3. X, given the OWNER role by the attacker in the window, is recovered together with A before any review.
4. X demotes A and controls the review. This is round 3's original R3-01 scenario.

**Recommended fix**
- Define the first group as the accounts that held the OWNER role and were active at the suspected leak and have not been reactivated or re-assigned since, as the audit trail shows. An account activated since the suspected leak is gated whatever the age of its role.
- If there is no such account, or the first Owner's assignment has no audit event:
  - the operator first recovers only the Owner accounts whose holders the operator verifies in person as Owners of record, against evidence outside the application that the Owner keeps for this purpose (for example beside the dated key register);
  - every other Owner account is gated on that Owner's review.
- If the leak cannot be dated, the suspected leak is the earliest time the leaked material could have been exposed, recorded in the command's audit events.
- Carry the fallback and the "otherwise" outcome into H8-21 and H9-14, and test both.

### R4-03 — LOW — MATERIAL — Level 1: The review and the "predates" test trust an audit trail that a database-level attacker can bypass or forge

**Locations**
- SECURITY AU-21: "read from the audit events PERMISSIONS_MATRIX RG-11 guarantees" and "as the audit trail shows".
- SECURITY H8-21 and H9-14.
- PERMISSIONS_MATRIX RG-11.
- DATABASE §28: the runtime role has `SELECT, INSERT` on AE tables and `UPDATE` of lifecycle columns on MD tables.
- DATABASE s04-13 `audit_events`: `occurred_at` has no database-enforced value and the rows have no chain.
- SECURITY SX-01: the credentials of the database roles, migration/owner included, are in the environment configuration.
- The AU-21 runbook rotates "the database role credentials … unless P9's inventory shows the leak could not have included it". SECURITY H9-09 leaves the database binding to P9.

**Problem**
- RG-11 guarantees audit events only for changes made through the application.
- The amended runbook itself contemplates that the leak included the database credentials. With a reachable database, or with the host, an attacker can:
  - change a role, a grant, an active flag or a Google link with a plain UPDATE that writes no audit event;
  - insert an audit row with any `occurred_at`.
- The review then misses the change, and a forged, backdated "OWNER assigned" row lets the attacker's account pass "predates the suspected leak".

**Failure scenario**
- The environment file and a dump leak while PostgreSQL is reachable (the binding is not yet fixed, H9-09).
- One UPDATE gives X the OWNER role, and one INSERT adds an audit row dated a year earlier.
- The operator, reading the audit trail, recovers X first, unreviewed.

**Recommended fix**
- State that the audit trail is authoritative only if the attacker could not write to the database.
- When P9's inventory cannot exclude write access (database credentials in the leak with network reach, or host access), the review and the "predates" test also compare the current roles, grants, active flags and Google links with the latest backup taken before the suspected leak (each backup is paired with its key, H9-14).
- Every difference, and every OWNER role absent from that backup, is treated as a change since the suspected leak.
- Add this to H8-21 and H9-14.

### R4-04 — LOW — MATERIAL — Level 1: "A breach starts a new attempt window" is stated only in AU-05; under "the stricter of", a store built from H6-07 breaks R-01's progression

**Locations**
- SECURITY AU-05, *Threshold*: "the breach starts a new attempt window, its failures being carried by the escalation level".
- CONCURRENCY H6-07: its R-01 sentence says only "a failure leaves the count 15 minutes after it was made and a breach leaves the escalation level 24 hours after it occurred".
- SECURITY H8-17: "a failure leaving the count after 15 minutes and a breach the escalation level after 24 hours".
- CONCURRENCY M-44: "the decay of the count and of the escalation level".
- SECURITY D-SEC-16.
- The floor in AU-05, D-CC-10 and H6-07: "since the later of 15 minutes ago and the latest breach".

**Problem**
- Only AU-05 (and the gate record's Level-1 choice) states the new-window rule. H6-07 — the specification P6 implements — restates R-01 without it, and H8-17 and M-44 test the decay but not the reset.
- Under "the stricter of", whichever source holds more wins. A store that keeps the breached window's failures therefore overrides the floor, which correctly restarts at the breach.

**Failure scenario**
1. Five wrong codes between 09:00:00 and 09:00:40; a 1-minute cooldown starts.
2. At 09:02 the person enters the correct code. The store still holds five failures (they leave at 09:15), so the place taken is the sixth, beyond the threshold.
3. The step is refused without evaluation — not as a cooldown, with no breach event and no alert — until 09:15.
4. Every breach therefore blocks for about 15 minutes. The 1/2/4/8 progression never applies, and a second breach cannot be recorded inside the window.

**Recommended fix**
- Add the new-window clause of AU-05 to H6-07's R-01 sentence and to D-SEC-16, and state that the atomic operation empties the count at a breach.
- Add to H8-17 and M-44:
  - after a cooldown, five more failures are needed for the next breach;
  - a correct code after the cooldown is evaluated and accepted.

### R4-05 — LOW — MATERIAL — Level 1: The time arithmetic of the floor and of the seeding is unspecified, so R-01's decay and threshold are not reproduced exactly

**Locations**
- SECURITY AU-05 *Durability*, CONCURRENCY D-CC-10 and H6-07:
  - "the count is at least the failures recorded since the later of 15 minutes ago and the latest breach";
  - "the store's count, level and cooldown are raised to at least the floor";
  - "holding time-stamped entries that the operation trims".
- CONCURRENCY RV-12: the failure event is written, then the breach event.
- PERFORMANCE IX-19; CONCURRENCY M-44; SECURITY H8-17.

**Problem**
- **(a) Seeded entries have no stated time.** The store keeps time-stamped entries, each trimmed when its own time passes, but "raised to at least the floor" is stated as a number. If entries are stamped at the seeding:
  - a seeded failure stays in the count up to 15 minutes after the real failure has left it;
  - a seeded breach keeps the escalation level up to 24 hours longer.
- **(b) The window boundary is the breach event's write time.** Three things carry failures of the breached window into the new one:
  - the fifth failure's own event is written immediately before the breach event, or at the same instant if one clock value is used for both;
  - failures evaluated concurrently in the breached window write their events after it;
  - a submission that read the floor just before a breach applies it after the store has started the new window.
- Every effect is stricter than R-01, never looser. But R-01's "fifth failed code" and its decay rule are not reproduced exactly, and "the stricter of" keeps the error in place.

**Failure scenario 1**
1. Four failures at 10:00; the store restarts at 10:14.
2. At 10:14:30 the correct code is submitted. The floor's four failures are seeded as entries stamped 10:14:30, and the code is accepted.
3. At 10:20 there is one typo. The real failures left the window at 10:15, but the seeded entries stay until 10:29:30.
4. The typo is counted as the fifth failure: a breach and a cooldown, where R-01 counts one failure.

**Failure scenario 2**
1. A double-click submits the fifth wrong code twice.
2. The second request reads the floor (four failures) before the first request writes its events.
3. The first request breaches. During the cooldown, the second seeds four failures into the new window.
4. After the 1-minute cooldown, a single typo is a second breach, with a 2-minute cooldown.

**Recommended fix**
- The floor read returns the time of each counted failure, and of each breach of the last 24 hours with its duration.
- The operation inserts any missing entries at those times, identified so that a failure is never counted twice — for example by a submission identifier stored both as the store entry and in the event's whitelisted `details`. This adds no field.
- The breached window's failures, the fifth included, are those whose places were taken before the breach; each TWO_FACTOR_FAILED can carry its place time in `details`.
- A floor read older than the store's latest breach does not seed the new window.
- Add tests to M-44 and H8-17:
  - a seeded failure leaves the count 15 minutes after it was made;
  - a seeded breach leaves the escalation level 24 hours after it occurred;
  - a duplicate fifth submission carries nothing into the next window.

### R4-06 — LOW — NON-MATERIAL — Level 1: "Recovered, or issued a link" leaves open an Owner-issued link for a confirmed window-assigned Owner

**Locations**
- SECURITY AU-21: "an account given the OWNER role, or activated, since the suspected leak is recovered, or issued a link".
- SECURITY H8-21 and H9-14: "an OWNER role assigned or an account activated in that period recovered or reset only once confirmed".
- Compared with:
  - AU-14 and H9-07 (R3-13): "every Owner account is recovered through it as DIR-044 R-02 orders";
  - R2-10: Owner accounts recovered through path (e) lose their Google link, and other accounts keep theirs with a plain RESET link.

**Problem**
- The pairing is not explicit. A confirmed account holding the OWNER role could be given a plain AU-12 RESET link, or an AU-20 d reset, instead of operator recovery.
- That would contradict R-02, AU-14 and R2-10. A plain RESET link would also keep a Google link that the review missed.

**Failure scenario**
- The recovered Owner confirms B, given the OWNER role legitimately during the window.
- The Owner then issues B a plain RESET link. B bypasses the operator recovery R-02 orders and keeps a Google identity that was linked in the window.

**Recommended fix**
- In AU-21, H8-21 and H9-14, state the two outcomes separately:
  - a confirmed account holding the OWNER role is recovered through AU-14, path (e), and loses its Google link;
  - a confirmed account activated since the leak without the OWNER role receives its plain RESET link.

### R4-07 — LOW — NON-MATERIAL — Level 1: "Counted nowhere" contradicts the throttles that still apply to enrolment confirmation

**Locations**
- SECURITY H8-17: "a failed enrolment confirmation writing no TWO_FACTOR_FAILED and counted nowhere".
- CONCURRENCY M-44: "failed enrolment confirmations neither counted nor recorded as TWO_FACTOR_FAILED".
- Compared with:
  - SECURITY AU-05: the confirmation "stays under the per-account-and-IP and per-IP throttles", and *Defence in depth* says the enrolment confirmation is "also throttled per account and client IP and per client IP";
  - CONCURRENCY RV-14: "only the per-account-and-IP and per-IP throttles applying".

**Problem**
- A P8 test written from H8-17 would assert that the per-account+IP and per-IP throttles do not count these failures. Either the test fails, or the throttles are dropped so that it passes.

**Recommended fix**
- Replace the wording in both places with: "taking no place in the account's second-factor count and writing no TWO_FACTOR_FAILED, while still counted by the per-account-and-IP and per-IP throttles".

### R4-08 — LOW — NON-MATERIAL — Level 1: The store key and eviction rules do not fit the framework limiter or the store's locks

**Locations**
- SECURITY WS-12: "Rate limits on the framework limiter backed by Valkey".
- SECURITY AU-05 *Durability*, CONCURRENCY D-CC-10 and H6-07: the keys "carry no expiry of their own … under the HO-24 policy that evicts only keys with an expiry".
- CONCURRENCY HO-24: "limiter keys … can neither evict queue payloads and locks nor fill the store".
- PERFORMANCE SZ-09.
- CONCURRENCY s00-03 source row: `withoutOverlapping` takes a cache lock with a 24-hour default expiry. H6-12: the funnel over atomic locks.

**Problem**
- **(a) The framework limiter cannot implement this limit.**
  - The framework's limiter keeps a fixed decay window, and its keys carry that decay as an expiry. This is to be re-verified in source at implementation.
  - The second-factor limit needs a rolling window, time-stamped entries, a raise to the floor and an increment in one operation, and keys without expiry. That requires a project-written atomic script on the store, yet WS-12 places every limit on the framework limiter.
- **(b) Locks remain eviction candidates.** Under a policy that evicts only keys with an expiry, the remaining candidates include the funnel's and `withoutOverlapping`'s locks, which carry expiries. HO-24's "limiter keys can neither evict … locks" therefore rests on sizing alone, or on `noeviction`, which also satisfies "evicts only keys with an expiry".

**Consequence**
- Built on the framework limiter, the second-factor keys get an expiry and become evictable, contrary to the stated exclusion. The floor still restores the limit, so nothing loosens.
- If P9 chooses a volatile policy without enough headroom, limiter pressure can evict the password-verification funnel's lock.

**Recommended fix**
- In WS-12 and H6-07, state that this one limit is a project-written atomic script on the store, not the framework limiter.
- In HO-24 and SZ-09, state that locks carry expiries: the store is sized never to evict, and the policy (`noeviction` or a volatile one) is only the backstop.

## Summary

- **By severity:** HIGH 0; MEDIUM 2 (R4-01, R4-02); LOW 6 (R4-03 to R4-08).
- **By materiality:** MATERIAL 5 (R4-01 to R4-05); NON-MATERIAL 3 (R4-06 to R4-08).
- **By change reviewed:**
  - R3-01: R4-01, R4-02, R4-03, R4-06.
  - R3-02: R4-04, R4-05, R4-08.
  - R3-03: R4-07. Its control design is sound.
- **Unbroken by these changes:**
  - the incident protocol (a)–(d), character-identical in all five places;
  - the residual (e), identical wherever it is stated;
  - no new table or field.
- **OWNER-LEVEL findings:** R4-01 — when every pre-leak Owner has been demoted or deactivated during the window, or no pre-leak Owner is available, no path within R-02's "existing paths only" restores a legitimate Owner. The Owner must choose a new operator restore command, a restore from a pre-leak backup, a supervised recovery of the window-assigned Owner, or acceptance of the residual. Its detect-and-halt part, and R4-02 to R4-08, are Level 1.
````

## Dispositions of round 4

The Owner decided R4-01 in DIR-049 and authorized the third continuation in [DIR-050](../../00-governance/DECISION_LOG.md#dir-049-dir-050-risk-005-and-tech-025--p5-authentication-amendment-third-continuation-after-review-round-4), whose §3 sets the post-incident recovery design and whose §4 the disposition of every other finding; where it is silent the reviewer's recommended fix is followed. Section 3 of DIR-050 replaces the recovery order that the round-3 dispositions of R3-01 and R3-13 wrote; those rows above stand as the history of that step.

| Finding | Disposition | Where it now stands |
| --- | --- | --- |
| R4-01 | Owner (R4-01: 1, DIR-049) — a new, narrow, audited OWNER-restoration command for a pre-window Owner whose OWNER role or active flag was removed during the compromise window, restoring window-start values only; it amends DIR-044 R-02's "existing paths only". The Level-1 part — detecting from the audit trail every pre-window Owner demoted or deactivated during the window — is step 2 of the order. An account that first became Owner during the window is never restored or recovered as an Owner without a recovered pre-window Owner's review | SECURITY AU-25 (new), AU-14, AU-21, LG-01, LG-02 (OWNER_RESTORED), LG-07, SX-04, TM-57, D-SEC-19, H6-15, H8-21, H9-03, H9-07, H9-14; PERMISSIONS_MATRIX AZ-02, RG-11; CONCURRENCY_IDEMPOTENCY TX-07, CI-09, RV-18 (new), M-46 (new), H6-15, HO-41; DATABASE §28; GAP-041 and RISK-005 continuations |
| R4-02 | Resolved by DIR-050 §3 — the pre-window Owner defined as an account that held the OWNER role and was active at the window's start, so a reactivated former Owner is not one; restoration to window-start values only; the AU-21 fallback that recovered every Owner account unreviewed removed; an undatable window starting at the leaked key's creation in the dated key register. The order, the halt conditions and the review's outcomes are carried into H8-21 and H9-14 and tested | SECURITY AU-25, AU-21, H8-21, H9-14 |
| R4-03 | Resolved by DIR-050 §§3.2 and 3.6 — Scenario A only when P9's inventory excludes write access by the attacker to the database and the server; otherwise Scenario B, recovery only from a backup or snapshot independently verified as predating the relevant compromise and trustworthy enough for incident recovery, the DIR-009 same-VPS backup never presumed clean or trustworthy, and an Owner incident decision outside the application when none exists | SECURITY AU-25, AU-21, H8-21, H9-14 |
| R4-04 | Level 1 — "a breach starts a new attempt window, its failures being carried by the escalation level" stated wherever R-01's limit is stated, and the atomic operation empties the count at a breach; tests that five more failures are needed after a cooldown and that a correct code after it is accepted | SECURITY AU-05, D-SEC-16, H8-17; CONCURRENCY_IDEMPOTENCY D-CC-10, H6-07, M-44; GAP-045 |
| R4-05 | Level 1 — the floor returns each counted failure's time, submission identifier and place time and each breach's time and duration; the operation inserts what the store lacks at its own time, never twice, and seeds nothing into a new window from a floor read before the store's latest breach; the breached window's failures are those whose places were taken before the breach; the submission identifier and place time go in TWO_FACTOR_FAILED's whitelisted details, no field added; tests for seeded decay and a duplicate fifth submission | SECURITY AU-05, LG-02, H8-17; CONCURRENCY_IDEMPOTENCY D-CC-10, H6-07, RV-12, M-44 |
| R4-06 | Resolved by step 4 of the order — a confirmed account holding the OWNER role is recovered through AU-14, path (e), and loses its Google link; a confirmed account activated during the window without the OWNER role receives a plain AU-12 RESET link | SECURITY AU-25, AU-21, H8-21, H9-14 |
| R4-07 | Level 1 — the reviewer's wording: a failed enrolment confirmation takes no place in the account's second-factor count and writes no TWO_FACTOR_FAILED, while still counted by the per-account-and-IP and per-IP throttles | SECURITY H8-17; CONCURRENCY_IDEMPOTENCY M-44 |
| R4-08 | Level 1 — the limit is a project-written atomic script on the store, not the framework limiter; the store is sized never to evict because the framework's locks carry expiries, its policy being only the backstop; the statement sits in the SZ-09 note DIR-048 §4 allows, and the sizing is a P9 obligation (HO-24) | SECURITY AU-05, WS-12; CONCURRENCY_IDEMPOTENCY D-CC-10, H6-07, HO-24; PERFORMANCE SZ-09 |

After these dispositions round 4 has no open finding; [round 5](#adversarial-review--round-5-final-focused), the final focused review, re-checks them.

## Level-1 choices of the third continuation

- **Enforcing "never without review":** after the incident command, the Owner-recovery command refuses an account whose OWNER assignment the audit trail dates inside the recorded window unless the operator names the recovered pre-window Owner who confirmed it — one whose OWNER_RECOVERY link use after the window the audit trail shows —, recorded in the issue's audit event, so the rule of DIR-049 is checked by the command and not left to the runbook alone. *(Corrected by R5-01, [Dispositions of round 5](#dispositions-of-round-5): the guard is keyed on pre-window status — a link without a named confirmer only for an account that held the OWNER role and was active at the run's recorded window start, a confirmer checked as itself a pre-window Owner.)*
- **The new security-event kind:** OWNER_RESTORED records each restoration with the recorded window start and is alerted (39 kinds).
- **The restoration's locks:** the OWNER role row (LK-02) before the account's row (LK-03), as RV-04 orders for every change of an OWNER-role account; a second run finds nothing removed and is refused. *(Corrected by R5-05, [Dispositions of round 5](#dispositions-of-round-5): a second restoration of an account in the same run is refused, as is any restoration after the run's recovery has ended or with another window start.)*
- **Identifiers:** the design is the new SECURITY AU-25, the transaction CONCURRENCY_IDEMPOTENCY RV-18 and the scenario M-46; AU-14, AU-21, AU-25, H8-21, H9-07 and H9-14 state its order in the same words, RG-11 recording the operator's identity for the command. *(Corrected by R5-06, [Dispositions of round 5](#dispositions-of-round-5): RG-11 states no order.)*
- **The R2-02 seeding refined:** the "raise to the floor" of the second continuation's choice above is made exact by R4-05 — each missing failure and breach inserted at its own time, never twice — as SECURITY AU-05 now states.

## Adversarial review — round 5 (final, focused)

Run on 2026-10-04 under DIR-050 §6 step 5 by a fresh agent with no prior context, bound by the rule of DIR-046 §2, read-only, on the complete corrected working tree after the round-4 dispositions: every change made for DIR-050 §§3–4, its interactions with the incident protocol, the R-01 limit and the reset paths, and a regression run of the mechanical checks of rounds 2 to 4. The report below is recorded verbatim, as the agent returned it to the executor; a copy is kept outside the repository at `C:\Users\YUSUFS~1\AppData\Local\Temp\claude\C--Projects-MultipleCorp\2dc7d0a3-0abc-47f2-a842-c97e1ca046a8\scratchpad\P5_AUTH_ADVERSARIAL_REVIEW_ROUND5_2026-10-04.md` (504 lines, 37,089 bytes, SHA-256 `4C75AAF25C8FC689A01CACC9697C47C82748CA929D5F7C58763D224A786D026E`).

````markdown
# Adversarial review, round 5 (final, focused): P5 authentication amendment

Reviewer: an independent agent with no prior context. I worked read-only on 2026-10-04 on the working tree of `amendment/p5-auth`, which is HEAD 6dbc49a plus 27 modified files and 8 untracked files: source records 41–47 and the gate record.

Scope, under DIR-050 §6 step 5:
- A. Every change made for DIR-050 §§3–4.
- B. How those changes interact with the incident protocol (a)–(d), the R-01 limit, the reset and recovery paths a–f, and the lock order.
- C. A regression run of the mechanical checks of rounds 2–4.
- D. Any accepted risk beyond K4, (e) as extended, and (f).

**Result:** 12 findings.
- By severity: 0 HIGH, 3 MEDIUM, 9 LOW.
- By materiality: 5 MATERIAL, 7 NON-MATERIAL.
- None needs a stop now, on two conditions:
  - R5-02 is handled by disclosure, as TM-72 / GAP-047 was. It still needs the Owner's decision at approval.
  - The contingent note of R5-03 is read as I recommend.

## What I read

**Owner records**
- In full:
  - DIR-050 (P5_AUTH_THIRD_CONTINUATION_OWNER_AUTHORIZATION_2026-10-04.txt);
  - its Appendix A, DIR-049 (P5_AUTH_ROUND4_OWNER_DECISIONS_2026-10-04.txt).
- Partly:
  - DIR-043 §3 (not authorized);
  - DIR-045 §3 (amendments to the authorization);
  - DIR-048 §4 (scope and guards) and its outline.
- I could not check the transcriptions against their originals. I checked them only against their recorded hashes.

**Gate record** (docs/05-security/evidence/P5_AUTH_AMENDMENT_GATE.md)
- The preface, Baseline, the Amendments tables and Superseded decisions.
- Round 2's header, "Mechanical checks", counts and dispositions, and the Level-1 choices of the second continuation.
- Round 3's "Mechanical checks", residual table, interactions, summary and dispositions.
- Round 4 verbatim, "Dispositions of round 4" and "Level-1 choices of the third continuation".

**SECURITY**
- The header and the source evidence.
- AU-01–AU-25 in full.
- WS-12, LG-01, LG-02, LG-07, SX-04.
- TM-06, TM-57, TM-61, TM-63, TM-64, TM-66, TM-68, and the §8 count paragraph.
- D-SEC-16, D-SEC-19.
- H6-07, H6-15, H8-07, H8-17, H8-19, H8-21, H9-03, H9-07, H9-14.
- The traceability entry for DIR-049.

**PERMISSIONS_MATRIX**
- The header, AZ-02, RG-05–RG-12, OD-01.
- The capability catalogue and §7.2, compared with c26f31e.

**CONCURRENCY_IDEMPOTENCY**
- s00-03: the header, D-CC-10, TX-07, TX-08.
- s04-06: LR-01–LR-08 and LK-01–LK-05.
- s10-13: CI-09.
- s14-17: RV-04, RV-05, RV-11, RV-12, RV-14, RV-17, RV-18, H6-07, H6-15; RV-13 by search.
- s18: the introduction and M-44–M-46.
- s19-20: HO-11, HO-24, HO-41.

**DATABASE**
- The module map (s04-00) and s04-01, compared with c26f31e and 6dbc49a.
- §28 in full.
- The table-class definition of SR (s01-03).
- The changed lines of s01-03, s18-19 and s29-31.

**Other documents**
- PERFORMANCE SZ-09.
- GAP_REGISTER: the triage table, the summary paragraph, and GAP-041 and GAP-045–GAP-047 in full.
- DECISION_LOG:
  - the index rows for TECH-025, RISK-005, DIR-049 and DIR-050;
  - the DIR-049/DIR-050 section in full.
- The changes in DECISION_INDEX, SOURCE_OF_TRUTH, CHANGELOG, PHASE_STATUS and CURRENT_HANDOFF.
- Searches of ARCHITECTURE, EXECUTION_CONTEXT, API_AND_INTEGRATIONS and the product documents for the operator commands.

**Compliance with DIR-046 §2**
- I opened, listed and searched no transcript, conversation log, history, `.claude` content, `tool-results` folder or scratchpad. The scratchpad paths named in the gate record were not opened.
- The tool harness diverted one large `git diff` output (of DATABASE) to a tool-results file. I did not open it, and I read the same content from the working tree instead.
- I created and modified nothing. I ran only read-only git commands (`status`, `diff`, `show`, `ls-tree`, `rev-parse`, `hash-object --no-filters` without `-w`, `config --get`). My checks were Python scripts fed on stdin that read files into memory.

## Mechanical checks

1. **Source records — PASS.**
   - There are 47 files. The 40 committed at 6dbc49a are byte-identical to their blobs (40 of 40 hash matches).
   - Records 41–47 match their SOURCE_OF_TRUTH SHA-256 and line/byte counts. Each has a `-text` entry:
     - 87B8E1E7…, 47 / 2,072;
     - 820E1214…, 354 / 17,840;
     - FB7D46BE…, 196 / 9,040;
     - B8A49FBC…, 16 / 945;
     - C63F72E1…, 317 / 15,686;
     - 347C9436…, 61 / 3,480;
     - 3723862B…, 370 / 18,017.
   - The fenced reports of rounds 2, 3 and 4 in the gate record hash to their recorded values:
     - DE89EA5E…, 332 lines;
     - B4FC374C…, 398 lines;
     - 19B335F0…, 348 lines / 27,688 bytes.
2. **Branch-diff scope — PASS.**
   - None of these is touched: BUSINESS_RULES, DOMAIN_MODEL, WORKFLOWS, REFERENCE_COVERAGE, ADMIN_FLOW, DESIGN_SYSTEM, INFORMATION_ARCHITECTURE.
   - No quality-gate evidence file of an earlier phase is touched.
   - Source records are only added.
3. **Capabilities — PASS.** 80 capabilities: ADM 37, ADM_PLUS 26, OWNER_ONLY 17. Only the "Covers" column of `users.manage` changed. The RG rows other than RG-11 and all CS rows are unchanged against c26f31e.
4. **Unchanged authentication rows — PASS.** AU-01, AU-02, AU-07, AU-08, AU-10 and AU-17 are byte-identical to c26f31e after CR removal. The AU-15 window is 15 minutes.
5. **Counts — PASS.**
   - The module map sums to 125 with IAM 8, over 13 modules.
   - PERMISSIONS_MATRIX §7.2 has 125 unique rows.
   - 18 CAP rows; 14 document types.
   - Mentions of 124 appear only in historical records.
6. **No new logical table or field — PASS.**
   - `users` has exactly four new fields against c26f31e.
   - `users` and `user_external_identities` add no identifier against 6dbc49a.
   - The submission identifier, place time and window start sit in existing whitelisted details or audit events.
   - See R5-10 for what the restoration writes in existing columns.
7. **Threat rows and event kinds — PASS.** 73 TM rows, all unique. The event list is 39 kinds (21 + 18), with OWNER_RESTORED added and the list unique. The "38 kinds" mention survives only in the gate record's history.
8. **Gap totals — PASS.** Recomputed from the triage table: 47 gaps — 3 CLOSED, 37 OPEN, 0 OWNER_DECISION_REQUIRED, 7 ACCEPTED_RISK. This matches the stated totals.
9. **Identifiers — PASS.**
   - AU-25, RV-18 and M-46 are each defined once.
   - No row family has duplicates.
   - No reference points to an undefined identifier in the AU, RV, M, H6–H9, TM, D-SEC, GAP, DIR, RISK, LG, SX, IX, C, HO, AZ, TECH and OBS families.
10. **Links — PASS, as expected now.** Every relative link and anchor in 105 Markdown files resolves, except `#gate` and `#adversarial-review--round-5-final-focused` in the gate record.
11. **Split documents — PASS.** The headings of every DATABASE and CONCURRENCY_IDEMPOTENCY file are identical to c26f31e, and the README files are unchanged.
12. **No fail-closed breached-password statement — PASS.** The phrase survives only in the gate record's superseded adjustment 7 and its history.
13. **No secret, client ID or placeholder — PASS.**
14. **No operator user row; unlink contract identical — PASS.** Every statement forbids an operator, system or placeholder user. The six unlink reasons are unchanged. R5-10 covers `user_roles.granted_by`.
15. **Audit event in the transaction, security event after the commit — PASS.** AU-25 and RV-18 follow the rule: the audit event in the transaction, then OWNER_RESTORED after the commit.
16. **R-01 parameters — PASS.**
    - The 645-character R-01 sentence, with "a breach starts a new attempt window", is identical in D-SEC-16, H6-07 and GAP-045.
    - AU-05 states the same rules.
    - TM-63, H8-17 and M-44 agree.
17. **(e) as extended — PASS.** The 337-character text is identical in 11 places: SECURITY ×4, GAP_REGISTER, DECISION_LOG ×4, DECISION_INDEX and the gate record. The only other wording is the historical DIR-044 entry.
18. **Incident protocol (a)–(d) — PASS.** The 463-character text is identical in AU-21, RV-17, H8-21, H9-14 and HO-41, and in the gate's R2-03 row.
19. **Residual-stating threat rows — PASS.**
    - The set is the same as in round 3: TM-06, TM-57, TM-61, TM-68–TM-73, plus the count paragraph. Each has a disposition. TM-72 is open gap GAP-047.
    - TM-57 now names the restoration command (K4; GAP-041; RISK-005).
    - R5-02 identifies an undisclosed residual.
20. **No claim of approval or publication — PASS, with false claims noted.**
    - The state documents are worded as of the fourth commit, and the TECH-025 hash list is absent, as expected.
    - Statements that would be false at that commit are in R5-06.
21. **DIR-050 §6 step 7 additions:**
    - **§3 stated the same way — PASS in part.**
      - The order sentence (520 characters) is identical in AU-14, AU-21, AU-25, H8-21, H9-07 and H9-14.
      - The halt conditions are identical in AU-25, H8-21 and H9-14; the terms and scenarios in AU-25 and H9-14.
      - The command's limits are identical in AU-25 and RV-18; the review passage in AU-21 and AU-25; the guard in AU-14 and AU-25.
      - RG-11 states no order, and H9-14 lacks the review's outcomes (R5-06).
    - **The order unambiguous — FAIL** in the AU-14 guard (R5-01) and in CHANGELOG (R5-06).
    - **No window Owner restored or recovered without review — FAIL in part** (R5-01).
    - **No window Owner classified as pre-window — PASS by definition.** Window binding and conservative dating are in R5-05.
    - **DIR-009 never presented as clean — PASS.** Its only mentions say it is never presumed clean or trustworthy.
    - **Restoration command narrow — PASS as stated**, with R5-05, R5-08 and R5-10.
    - **R4-04 and R4-05 stated the same way — PASS** in the 2,012-character durability passage, identical in AU-05, D-CC-10 and H6-07, and in D-SEC-16, H6-07, GAP-045, H8-17 and M-44. FAIL in part for RV-12 (R5-07). The breach identity gap is R5-04.
    - **R4-07 — PASS** in H8-17 and M-44.
    - **R4-08 — PASS** in AU-05, WS-12, D-CC-10, H6-07, HO-24 and SZ-09.

## Findings

### R5-01 — MEDIUM — MATERIAL — Level 1: The Owner-recovery command's guard depends on when the OWNER role was assigned, not on pre-window status. It can refuse a legitimate pre-window Owner and admit a reactivated former Owner.

**Locations**
- SECURITY AU-14 and AU-25: "for an account whose OWNER assignment the audit trail dates inside the recorded window, the Owner-recovery command refuses unless the operator names the recovered pre-window Owner who confirmed it in the review — one whose OWNER_RECOVERY link use after the window the audit trail shows —".
- SECURITY H8-21: "the Owner-recovery command refusing an account made Owner inside the window without a named confirming pre-window Owner".
- CONCURRENCY RV-18 ("a second run for the same account finds nothing removed and is refused") and M-46.
- The gate record, "Level-1 choices of the third continuation", first bullet: "so the rule of DIR-049 is checked by the command and not left to the runbook alone".
- PERMISSIONS_MATRIX RG-09, RG-10; CONCURRENCY RV-04, RV-05.

**Problem**
- **(a) A legitimate Owner wrongly refused.**
  - During the window, the attacker removes pre-window Owner A's OWNER role and assigns it again. RV-04 allows this while the attacker's window Owner X stays active.
  - A now holds OWNER through an assignment the audit trail dates inside the window.
  - AU-25 step 3 recovers A without review, and the text says "no pre-window Owner needs another Owner's review before its own restoration or recovery". Yet the command refuses A unless a confirmer is named.
  - The restoration command also refuses A, because A's role and active flag already equal their window-start values ("finds nothing removed").
  - If A is the only pre-window Owner, nobody can confirm A. None of AU-25's halt conditions applies, since a pre-window Owner is established and available. The documented path is stuck.
- **(b) A reactivated former Owner wrongly admitted.**
  - A former Owner F kept the OWNER role but was inactive at the window start; deactivation keeps the role (RG-10, RV-05). The attacker reactivates F during the window.
  - F is not a pre-window Owner, so it must wait for the step-4 review.
  - But F's OWNER assignment predates the window, so the guard does not refuse F.
  - "An account that first became Owner during the compromise window" does not literally cover F either.
- **(c) The confirmer is not checked as pre-window.**
  - The command checks only that the confirmer used an OWNER_RECOVERY link after the window. H8-21 says "pre-window Owner"; AU-14 and AU-25 do not require it.
  - Once F is recovered as in (b), F can be named as the confirmer of a window Owner.

**Failure scenarios**
1. As Owner A, the attacker assigns OWNER to X. X removes A's role, then assigns it again.
   - After the incident command, the restoration of A is refused, and AU-14 refuses A.
   - A is the only pre-window Owner, so no path remains.
2. The attacker reactivates F, a departed co-owner who colludes.
   - The operator sees an OWNER assignment that predates the window and recovers F.
   - F is named as X's confirmer, and X is recovered.
   - F and X then remove A's role.

**Recommended fix**
- Key the guard on the pre-window status that RV-18 already reads.
- After the incident command, the Owner-recovery command issues a link without a named confirmer only for an account the audit trail shows held the OWNER role and was active at the recorded window start.
- For every other account holding the OWNER role, it refuses unless the operator names a confirmer that:
  - was itself a pre-window Owner; and
  - has an OWNER_RECOVERY link use after the window in the audit trail.
  - "Every other account" includes one whose OWNER assignment or activation falls inside the window, such as a reactivated former Owner.
- Wherever the DIR-049 rule is restated, cover an account activated during the window while holding the OWNER role.
- Add three cases to M-46 and H8-21:
  - a pre-window Owner whose role was removed and reassigned during the window is recovered without a confirmer;
  - a reactivated former Owner is refused without one;
  - a confirmer that was not a pre-window Owner is refused.
- Correct the gate's Level-1 choice.

### R5-02 — MEDIUM — MATERIAL — Level 1 (disclosure now; the decision is the Owner's at approval): The restoration also reverses a legitimate removal of a pre-window Owner, and no document says so.

**Locations**
- SECURITY AU-25: terms and step (2).
- The order sentence in AU-14, AU-21, H8-21, H9-07 and H9-14.
- SECURITY TM-57 and the §8 count paragraph.
- The GAP-041 continuation; RISK-005.
- DIR-050 §3.3 step 2 and §6 step 7.

**Problem**
- Step 2 restores every pre-window Owner whose OWNER role or active flag was removed during the window. The operator checks only the person's identity, in person, and step 3 then recovers the account.
- Nothing distinguishes the attacker's removal from a legitimate one made during the window, for example a co-owner who left or was removed for cause.
- The order requires that no pre-window Owner needs another's review. So the restored person becomes a full Owner, with `users.manage`, `permissions.manage` and bank-account authority, and stands equal in the review.
- Under RV-04, either restored Owner can remove the other while one remains. The review can undo the restoration, but only afterwards and in a race.
- The longer the window, the likelier a legitimate removal falls inside it — for example an undated leak, which starts at the key's creation.
- TM-57 and RISK-005 cover operator abuse, not this honest use of the command. No TM row, count-paragraph entry or gap discloses it.

**Failure scenario**
- A and B are Owners at the window start. During a three-week window, A legitimately removes B's role and deactivates B after B leaves.
- The leak is found, and Scenario A applies. B comes in person; the operator restores B, then recovers B.
- Before A finishes recovery, B changes a bank account or removes A's role. OWNER_RESTORED only reports it after the fact.

**Recommended fix**
- Level 1, now:
  - Disclose the residual in TM-57 (or a new TM row) and in the §8 count paragraph.
  - Register it as an open gap owned by the Owner's decision at approval, without claiming acceptance, as TM-72 / GAP-047 were handled.
  - Have H9-14 require the runbook to show every recovered pre-window Owner the actor and time of each removal the restoration reversed, before the review.
- For the Owner at approval:
  - accept the residual, which lies beyond K4, (e) and (f), so stop condition 7 would then apply; or
  - amend DIR-050 §3.3 step 2 — for example, restore only removals the Owner establishes, together with the window start, as part of the compromise. This would change DIR-050's rule that no pre-window Owner needs another's review.
- This is not a stop now if it is disclosed without acceptance.

### R5-03 — MEDIUM — MATERIAL — Level 1 (contingent note): Scenario B never says the restored state must still go through the incident response, and its text reads as excluding it.

**Locations**
- SECURITY AU-25:
  - "after the key-compromise incident command of AU-21, recovery follows this design";
  - Scenario A step (1) is the incident command;
  - "otherwise Scenario B applies: no step of Scenario A runs, recovery uses only a backup or snapshot …";
  - the halt case "or when no pre-window Owner is available".
- SECURITY AU-21: "Scenario B applies whenever …", and P9's "pairing of each backup with the key valid at its time".
- SECURITY H8-21, H9-14 ("each backup paired with the key valid at its time").
- DIR-050 §3.6 ("No step of 3.3 runs"); DIR-044 R-02.

**Problem**
- **(a) Contradiction.** The incident command runs before the scenario is chosen (the AU-25 preamble, AU-21), yet it is also step (1) of Scenario A, which Scenario B excludes. Whether it runs in Scenario B is contradictory.
- **(b) The backup still holds exposed credentials.** A backup that predates the compromise still holds what the leak exposed:
  - password hashes that the attacker's dump also holds and can crack offline (TM-69);
  - TOTP secrets and recovery codes encrypted under the key valid at the backup's time. H9-14 pairs that key with the backup, and it is the leaked key unless the key was rotated since.

  Restoring the backup after the command undoes the voiding; restoring it instead of the command leaves those credentials live. R-02 requires the full incident response whenever the key leaked with the database, but nothing applies it to the restored state.
- **(c) No Owner after the restore.** When Scenario B is entered because no pre-window Owner is available, a restore does not make one available. The Owner's incident decision outside the application is named only for the no-backup case.

**Failure scenario**
- In Scenario B, the runbook restores last week's verified backup with its paired key and lifts maintenance mode. The attacker decrypts the TOTP secrets with the leaked key, cracks an 8-character Admin password and signs in.
- Alternatively, the key is rotated first, every high-risk account is locked out, and no recovery order is stated.

**Recommended fix**
- In AU-25, AU-21, H8-21 and H9-14, state that:
  - "no step of Scenario A runs" applies to the untrusted state;
  - a restore brings back the credentials the leak exposed, so before the restored application serves any request, the runbook installs new keys (the leaked key never listed) and runs the incident command, protocol (a)–(d), on the restored state;
  - Owner recovery then follows AU-25's order against the restored audit trail;
  - a restore does not provide an available pre-window Owner, so that case also ends in the Owner's incident decision outside the application.
- Add a Scenario B recovery test to H8-21 and H9-14.
- Contingent: if "No step of 3.3 runs" is read as forbidding the incident command on the restored state, it conflicts with DIR-044 R-02 and needs the Owner.

### R5-04 — LOW — MATERIAL — Level 1: The floor gives a breach no identifier, so a breach can be counted twice and R-01's progression is not reproduced.

**Locations**
- SECURITY AU-05 *Durability*, CONCURRENCY D-CC-10 and H6-07, in their identical passage: "every failure and breach of the floor that the store lacks is inserted at its own time — a failure identified by the submission identifier its event carries, so that none is counted twice —".
- CONCURRENCY RV-12.
- SECURITY LG-02: TWO_FACTOR_COOLDOWN_STARTED carries only the level, the duration and the path.
- SECURITY H8-17; CONCURRENCY M-44.

**Problem**
- The floor is merged into the store before every covered submission, not only after a store loss.
- Failures are matched by submission identifier; breaches only by time.
- The store stamps a breach when its operation records it. The event's `occurred_at` is written afterwards, by another statement.
- Unless both carry the same instant — which nothing states — the store "lacks" the floor's breach, inserts it, and the escalation level rises by one.

**Failure scenario**
- After five wrong codes, the store records the breach at 09:00:40.100, and the event is written at 09:00:40.160.
- At 09:02, the next submission inserts the breach "at .160", and the level becomes 2.
- The next breaches then run at level 3 (4 minutes instead of 2) and level 5 (15 minutes instead of 4). The progression is 1, 4, 15 instead of 1, 2, 4, 8, 15, and the recorded levels are wrong.
- This is stricter than R-01, never looser — the same class of defect as R4-05.

**Recommended fix**
- Give each breach an identifier. Either:
  - TWO_FACTOR_COOLDOWN_STARTED carries the triggering failure's submission identifier in its whitelisted details (no field is added), and the store keys its breach entries by it; or
  - the event takes the store's breach time.
- State it in the shared passage, in LG-02 and in RV-12.
- Add to H8-17 and M-44:
  - the 1/2/4/8/15 progression is reproduced while the floor is merged at every submission;
  - no breach is counted twice.

### R5-05 — LOW — MATERIAL — Level 1: The window start, "only during incident recovery" and "at most once" are not tied to any durable record, so the restoration's guarantees rest on the operator alone.

**Locations**
- SECURITY AU-25 terms: the start is "recorded by the operator in the audit event of every recovery step"; the command is "usable only during incident recovery after the incident command has completed".
- CONCURRENCY RV-18 ("refuses to run unless the incident command of RV-17 has completed"; "a second run … finds nothing removed and is refused").
- M-46: the invariant "at most once".
- The AU-14 guard ("the recorded window"); RV-17 (the run identifier).

**Problem**
- **(a)** The window start is an input to each run. Nothing makes the restoration and the AU-14 guard use the same value. A later run can choose an earlier start, which makes "pre-window" any account that was ever an active Owner and was removed after that start.
- **(b)** No durable record marks when the incident command completed or when recovery ended.
- **(c)** "Finds nothing removed" compares the current state only. A restored Owner who is later legitimately removed is restored a second time.
- **(d)** Nothing says which start to use when the evidence is uncertain. A start set too late classifies a window Owner as pre-window. Nothing covers a leaked key whose creation is missing from the register, or a leak that included several keys.

**Failure scenario**
- Months after recovery, an Owner removed after the incident persuades the operator to run the command again with the original start. The audit trail still shows a removal during the window, so the role is restored again.
- Or the AU-14 guard is run with a later start than the restoration, and a window Owner passes as pre-window.

**Recommended fix**
- Record the window start once, as established with the Owner — in the incident command's run events or in one run-level audit event — and make the restoration and the AU-14 guard read it and refuse any other value.
- Refuse a second restoration of the same account for that run.
- End recovery with an operator-recorded audit event of the run (an audit action, not a field), after which the command refuses.
- State that an uncertain start is the earliest the evidence allows, and that a key creation that is unregistered or ambiguous leads to Scenario B.
- Add M-46 cases. Operator abuse stays TM-57's accepted risk; this fix closes off operator error and deception.

### R5-06 — LOW — NON-MATERIAL — Level 1: Claims that §3 is stated "in the same words" are false for RG-11 and H9-14, and CHANGELOG states the order ambiguously.

**Locations**
- DECISION_LOG, TECH-025 bullet "rounds 3 and 4, and the third continuation applied": "stated in the same words in AU-14, AU-21, RG-11, H8-21, H9-07 and H9-14".
- The gate record:
  - "Level-1 choices of the third continuation", Identifiers bullet;
  - the disposition of R4-02 ("carried into H8-21 and H9-14") and of R4-06 (H9-14);
  - round 2's R2-10 row ("AU-12, AU-21, H8-21, H9-14").
- SECURITY H8-21 and H9-14.
- CHANGELOG, third-continuation bullet: "then its recovery through AU-14, then its review".

**Problem**
- RG-11 states only how the command records the operator's identity, and no order.
- H9-14 — the P9 runbook handoff — lacks:
  - the review's three outcomes, the "otherwise" outcome among them, which DIR-050 §4 R4-02 requires;
  - R2-10's "every other account receives a plain RESET link, which keeps its Google link";
  - the AU-14 guard;
  - the in-person check before a restoration.
- H8-21 names "the three outcomes" only by reference and no longer states R2-10's outcome.
- CHANGELOG's "its review" can be read as the pre-window Owner being reviewed, which DIR-050 §6 step 7 requires the text to exclude.

**Failure scenario**
- The gate check passes on the TECH-025 claim.
- P9 writes the runbook from H9-14 and leaves out the "otherwise" outcome.

**Recommended fix**
- Add to H9-14 the review passage that AU-21 and AU-25 already share, the AU-14 guard and the in-person check.
- State the outcomes in H8-21, or cite that passage.
- Drop RG-11 from both claims that the order is stated "in the same words".
- Correct the R2-10, R4-02 and R4-06 locations.
- In CHANGELOG, write "then the review it conducts".
- Fix this before the gate; do not carry it.

### R5-07 — LOW — NON-MATERIAL — Level 1: RV-12 states the threshold and the seeding without R4-04's new window, and only part of R4-05's arithmetic.

**Locations**
- CONCURRENCY RV-12:
  - "the store first brought up to the durable floor of SECURITY AU-05, each missing failure and breach inserted at its own time, in the same atomic operation that takes the place";
  - "the failure that is the fifth within the attempt window invalidates the challenge or pending step and starts the cooldown".
- Compare AU-05, D-CC-10, H6-07 and D-SEC-16.

**Problem**
- RV-12 is the step sequence P6 implements for every code step. It omits three rules:
  - a breach starts a new attempt window and empties the count;
  - a failure is never inserted twice (submission identifier);
  - a floor read before the store's latest breach seeds nothing.
- DIR-050 §6 step 7 requires the R4-04 and R4-05 rules to be stated the same way wherever R-01's limit is stated.

**Failure scenario**
- Working from RV-12 alone, P6 seeds from a stale floor after a breach — R4-05's duplicate-fifth-submission case.

**Recommended fix**
- Add the three clauses to RV-12, or cite the shared passage of D-CC-10 and H6-07 together with the new-window clause.
- Fix this before the gate. It could be carried to P8 (owner: Planner; H8-17, M-44) only if the gate accepts the reference to H6-07 as enough.

### R5-08 — LOW — NON-MATERIAL — Level 1: The general rules the restoration departs from do not name it as an exception.

**Locations**
- SECURITY AU-18 *Enforcement*: "every command that can make an account high-risk — a role assignment, a grant … and an activation … issues one RESET link". Its sentence "Owner recovery (AU-14) is an operator mechanism, not a capability" names only AU-14.
- PERMISSIONS_MATRIX:
  - RG-05: "Only `permissions.manage` … assigns the role"; "`users.manage` … activates".
  - OD-01: "only `users.manage` creates, edits, activates or deactivates accounts".
- CONCURRENCY RV-05: "a reactivation deletes the account's sessions again".
- Compare AU-25 and RV-18: "not an NX-02 activation and issues no RESET link"; "writes no … session".

**Problem**
- The restoration sets the OWNER role and the active flag on an account without TOTP.
- By AU-18's own words, that command must reissue the account's credentials. By RG-05 and OD-01, only `permissions.manage` and `users.manage` may do it.
- AU-25 says the opposite without naming the rules it overrides.

**Failure scenario**
- P6 routes the restoration through the shared role-assignment action, because the command runs "through the application's actions".
- The R-05 reissue fires and issues a RESET link — with no acting Owner to show it to.
- That creates a password path into an Owner account outside path (e) and its Google unlink.

**Recommended fix**
- In AU-25, and in RG-11, which DIR-050 §5 allows to cover the command, state that the restoration is an operator mechanism outside RG-05, OD-01 and AU-18's enforcement:
  - it exercises no capability;
  - it issues no RESET link;
  - it runs no session statement;
  - the account recovers only through path (e).
- Name AU-25 beside AU-14 in AU-18's operator-mechanism sentence, if AU-18 may be edited.
- Carry: possible to P8 (owner: Planner; H8-21 asserting no RESET link), but the fix is cheap now.

### R5-09 — LOW — NON-MATERIAL — Level 1: AU-12's post-incident rule and the review's outcomes do not match AU-25.

**Locations**
- SECURITY AU-12: "after the key-compromise incident command every account other than an Owner's receives a plain Owner-issued RESET link the same way, which keeps its Google link (AU-21)".
- The review passage in AU-21 and AU-25: "for any other such account the Owner removes the role or deactivates the account".

**Problem**
- **(a)** AU-12 gives a RESET link to every non-Owner account. That includes an account the attacker created or activated that the review does not confirm and that AU-25 deactivates.
- **(b)** The listed outcomes reverse only roles and activations:
  - An unconfirmed grant, such as `finance.view` given to a colluder, survives "removes the role".
  - "Any other such account" literally also covers a confirmed account that was only changed.

**Failure scenario**
- The recovered Owner follows the listed outcomes and removes the colluder's role, but the grant stays.
- Or the Owner follows AU-12 and issues the colluder a RESET link before the review.

**Recommended fix**
- In AU-25 (DIR-043 §3 forbids changes to AU-12 beyond the authorized extensions), state that AU-12's post-incident link goes only to the accounts the review keeps.
- Phrase the third outcome as: "for any account or change the review does not confirm, the Owner reverses it through the ordinary commands — revoking the grant, removing the role or deactivating the account".
- Carry: possible to P9 (owner: Planner; the H9-14 runbook). It is better fixed now, with R5-06.

### R5-10 — LOW — NON-MATERIAL — Level 1: What the restoration writes in `user_roles` and `users` is not stated, and no actor column can record the operator.

**Locations**
- DATABASE s04-01:
  - `user_roles` is an SR table with PK (`user_id`, `role_id`) and `granted_by/at`; one launch role per user;
  - `users.deactivated_at/by`.
- DATABASE §28: the runtime role gets INSERT and lifecycle UPDATE on SR tables, and DELETE only on the draft tables of §19. The restoration "needs no privilege beyond the runtime role's".
- CONCURRENCY RV-18: "set back only what was removed".
- RG-11 and DIR-043 §3: no user representation of the operator.

**Problem**
- Restoring OWNER means re-creating a `user_roles` row and removing the role the attacker gave in its place.
- `granted_by` has no lawful value for the operator, who has no user row.
- Nothing states how a role row is removed under the runtime role's privileges. This gap predates the amendment (P4), and the restoration inherits it.
- Nothing states whether `deactivated_at/by` are cleared.

**Failure scenario**
- P6 invents a system user, which is forbidden, or records the attacker's account as the granter of the restored role.

**Recommended fix**
- State in RV-18 that:
  - the restored row takes its window-start values, `granted_by/at` included, from the audit trail's before-image;
  - the role given during the window is removed by the same mechanism a role change uses — state that mechanism, or register it as a P4/P6 gap;
  - `deactivated_at/by` are cleared.
- Carry: possible to P8 (owner: Planner; H8-21 and M-46 asserting the written values and no user row).

### R5-11 — LOW — NON-MATERIAL — Level 1: The new first-Owner audit event has no actor rule.

**Locations**
- SECURITY AU-25 Obligations and H9-14: the installation step "writing an audit event of the OWNER assignment and the operator's identity".
- PERMISSIONS_MATRIX RG-11: an event without an application actor is allowed only for an OWNER_RECOVERY link use and the AU-14, AU-21 and AU-25 commands.

**Problem**
- The installation step is outside RG-11's list, so its event would break RG-11's actor rule.
- DIR-050 §5 lets RG-11 cover only the restoration command.

**Failure scenario**
- P9 designs the installation step and either finds it forbidden or creates a placeholder actor.

**Recommended fix**
- Carry to P9 (owner: Planner, with P10 for the installation step).
- The step records the operator's identity the way AU-14 does, and RG-11's operator rule is extended to it under P9's own authorization.

### R5-12 — LOW — NON-MATERIAL — Level 1: Precision items

- CONCURRENCY TX-07 lists the OWNER-restoration command among "the operator's console commands of SECURITY AU-14 and AU-21". It is AU-25's command.
- SECURITY TM-57 now covers the restoration command, but its proof column (H8-07, H8-19) omits H8-21, which tests the restoration through M-46.
- The §8 count paragraph still names the fourth K4 residual as "operator abuse of the recovery command (TM-57; GAP-041)", though RISK-005 now also covers the restoration command.
- Carry: to P8 (owner: Planner).

## Interactions checked

- **Incident protocol (a)–(d):** unchanged, and character-identical in all five places.
  - The restoration and the AU-14 recovery run only after the command; how "completed" is known is R5-05.
  - Between the closing sweep and recovery, nobody can sign in: passwords are unusable, TOTP is cleared, links are revoked and Google is not eligible. Recovery steps therefore race only each other.
  - Scenario B's relation to the command is R5-03.
- **R-01:** sound apart from R5-04 and R5-07. The recovery design does not touch the limit, and the enrolment confirmation after recovery stays excluded.
- **Reset and recovery paths a–f:**
  - After an incident, Owners are recovered only through path (e), consistently in AU-14, AU-20, AU-21 and AU-25.
  - A confirmed window Owner is never recovered through paths (c) or (d) (R4-06 resolved).
  - Remaining issues: AU-12 (R5-09) and AU-18 (R5-08).
- **Concurrency:**
  - RV-18 takes LK-02, then LK-03, in ascending order under LR-01 and as RV-04 orders.
  - The AU-14 issue takes LK-03, then LK-04.
  - The sweeps take LK-03, with LK-04 and LK-05 last (LR-02).
  - A role change racing a restoration waits at LK-02 (M-46).
  - The audit-trail reads are of append-only rows.
  - No lock cycle found.

## Summary

- **By severity:** HIGH 0; MEDIUM 3 (R5-01–R5-03); LOW 9 (R5-04–R5-12).
- **By materiality:** MATERIAL 5 (R5-01–R5-05); NON-MATERIAL 7 (R5-06–R5-12).
- **Carried LOW NON-MATERIAL findings:**
  - R5-06 and R5-07 must be fixed before the gate.
  - May be carried: R5-08 (P8, Planner), R5-09 (P9, Planner), R5-10 (P8, Planner), R5-11 (P9, Planner with P10), R5-12 (P8, Planner).
- **Convergence rule:** in my reading, the corrections for R5-01, R5-03 and R5-05 change how security controls behave, so the further focused review of DIR-050 §2 would apply after them.
- **Scope D:** no text claims an accepted risk beyond K4, (e) as extended, and (f). R5-02 identifies an undisclosed residual.
- **OWNER-LEVEL findings:** none that requires a stop now.
  - R5-02 must reach the Owner for a decision at approval, as GAP-047 does. It becomes a stop under condition 7 only if the residual is recorded as accepted, or if DIR-050 §3.3 step 2 is changed.
  - Contingent: R5-03 becomes Owner-level if "No step of 3.3 runs" is read as forbidding the incident command on a restored state, because that conflicts with DIR-044 R-02.
````

## Dispositions of round 5

DIR-050 §2 sets the convergence rule. Under it, a Level-1 finding is corrected and validated without another full adversarial round. A further focused review is still required when a correction materially changes how a security control behaves, adds a persistence mechanism or dependency, or raises a HIGH or Owner-level issue. A LOW non-material finding may be carried to P8 or P9, with an owner and a phase, recorded here and in the GAP register. A finding that needs a new Owner decision stops the task first.

Round 5 raised no finding that needs a new Owner decision now:

- R5-02 is disclosed and not accepted, so the Owner decides it at approval, as GAP-047 was handled.
- R5-03 is resolved by the reading its reviewer recommended. That reading is the one consistent with DIR-044 R-02 and DIR-050 §3.6, so the contingent Owner-level question does not arise.

The corrections of R5-01, R5-03 and R5-05 change how security controls behave, so the focused [round 6](#adversarial-review--round-6-focused) re-checks them. Where DIR-050 is silent, the reviewer's recommended fix is followed.

| Finding | Disposition | Where it now stands |
| --- | --- | --- |
| R5-01 | **Level 1, the reviewer's fix.** The Owner-recovery command's guard is keyed on pre-window status. Until the run's recovery ends:<br>• a link without a named confirmer is issued only for an account that the audit trail shows held the OWNER role and was active at the run's recorded window start;<br>• every other account holding the OWNER role is refused unless the operator names the recovered pre-window Owner who confirmed it. That includes an account whose OWNER assignment or activation falls inside the window.<br>• The command checks that the confirmer held the OWNER role and was active at that window start, and that the audit trail shows its OWNER_RECOVERY link use after the window.<br>The DIR-049 rule is restated, in the order sentence's six places, to cover an account activated during the window while holding the OWNER role. The three cases are in M-46 and H8-21. This record's Level-1 choice is annotated as corrected. **The change is material, so round 6 re-checks it.** | SECURITY AU-14, AU-21, AU-25, H8-21, H9-07, H9-14; CONCURRENCY_IDEMPOTENCY M-46 |
| R5-02 | **Disclosed, not accepted** (Level 1). The decision is the Owner's at approval.<br>• TM-57 states that the command's honest use also reverses a removal made legitimately during the window.<br>• The count paragraph lists that residual as a consequence of DIR-050 §3.3 as decided, for which no new acceptance is claimed.<br>• GAP-048 registers it as OPEN for the Owner's decision at approval.<br>• Step (2) of AU-25 and the runbook of H9-14 show every recovered pre-window Owner, before the review, the actor and time of each removal the restoration reversed.<br>At approval the Owner can accept the residual, which would be an acceptance beyond K4, (e) as extended, and (f) and therefore the Owner's own act. Alternatively, the Owner can amend DIR-050 §3.3 step 2. | SECURITY AU-25, TM-57, the count paragraph, H8-21, H9-14; GAP-048 |
| R5-03 | **Level 1, the reviewer's recommended reading.**<br>• "No step of Scenario A runs" applies to the untrusted state. That state is replaced rather than recovered, and the application stays closed in the maintenance mode meanwhile.<br>• A restored backup or snapshot brings back the credentials the leak exposed. Before the restored application serves any request, the runbook installs new keys, never listing the leaked key, and runs the incident command, protocol (a)–(d), on the restored state.<br>• Recovery then follows steps (2) to (4) of Scenario A against the restored audit trail.<br>• If no pre-window Owner is available on the restored state, recovery stops for the Owner's incident decision outside the application.<br>• AU-25's preamble no longer places the incident command before the choice of scenario.<br>This is consistent with DIR-044 R-02, which requires the full incident response whenever the key leaked with the database, and with DIR-050 §3.6. A Scenario B recovery test is added. **The change is material, so round 6 re-checks it.** | SECURITY AU-25, AU-21, H8-21, H9-14 |
| R5-04 | **Level 1, the reviewer's first option.** TWO_FACTOR_COOLDOWN_STARTED carries, in its whitelisted details, the submission identifier of the failure that triggered it, so no field is added. The floor returns each breach's identifier, and the store keys its breach entries by it, so no breach is counted twice. Tests are added for the 1, 2, 4, 8 and 15-minute progression reproduced while the floor is merged at every submission. | SECURITY AU-05, LG-02, H8-17; CONCURRENCY_IDEMPOTENCY D-CC-10, H6-07, RV-12, M-44 |
| R5-05 | **Level 1, the reviewer's fix.**<br>• The window start is established with the Owner. When the evidence is uncertain, it is the earliest start the evidence allows. When the leak cannot be dated, it is the leaked key's creation time, or the earliest such time if several keys leaked.<br>• The operator records the start once, as an input of the incident command, in its run's audit events.<br>• Every later recovery step of that run reads the start from there, records it, and refuses any other value.<br>• A leaked key whose creation is unregistered or ambiguous leaves no pre-window Owner established with certainty, so Scenario B applies.<br>• A second restoration of an account in the same run is refused.<br>• The operator records the end of the run's recovery in an audit event of the run, using the command. This is an audit action, not a field. After it, the command refuses, and the guard of R5-01 no longer applies.<br>M-46 gains the cases. **The change is material, so round 6 re-checks it.** | SECURITY AU-25, AU-14, H8-21, H9-14; CONCURRENCY_IDEMPOTENCY RV-18, M-46 |
| R5-06 | **Level 1, fixed before the gate.**<br>• H9-14 gains the in-person verification before a restoration, the display of R5-02, the guard of R5-01 and the review passage that AU-21 and AU-25 share.<br>• H8-21 states the review's outcomes.<br>• RG-11 is dropped from the claims, in TECH-025 and in this record's Level-1 choices, that the order is stated "in the same words". The order is stated that way in AU-14, AU-21, AU-25, H8-21, H9-07 and H9-14, while RG-11 records the operator's identity.<br>• CHANGELOG now reads "then the review it conducts".<br>With these additions, the locations named in the rows of R2-10, R4-02 and R4-06 are now accurate as written. | SECURITY H8-21, H9-14; DECISION_LOG TECH-025; CHANGELOG; this record |
| R5-07 | **Level 1, fixed before the gate.** RV-12 now states that:<br>• a breach starts a new attempt window, its failures carried by the escalation level, and the operation empties the count;<br>• no failure or breach is inserted twice — a failure is identified by its submission identifier, and a breach by the identifier of the failure that triggered it;<br>• a floor read before the store's latest breach seeds nothing into the new window. | CONCURRENCY_IDEMPOTENCY RV-12 |
| R5-08 | **Level 1, fixed now rather than carried.** AU-25, RV-18 and RG-11 state that the command is an operator mechanism outside RG-05, OD-01 and AU-18's enforcement: it exercises no capability, issues no RESET link and runs no session statement, and the account recovers only through path (e). AU-18's operator-mechanism sentence now names AU-25 beside AU-14. AU-18 is one of the amendment's own new rows. | SECURITY AU-18, AU-25; PERMISSIONS_MATRIX RG-11; CONCURRENCY_IDEMPOTENCY RV-18 |
| R5-09 | **Level 1, fixed now.** The review passage of AU-21, AU-25 and H9-14 changes in two ways:<br>• the Owner reverses any account or change the review does not confirm through the ordinary commands — revoking the grant, removing the role or deactivating the account;<br>• AU-12's post-incident link goes only to an account the review keeps, so an account created or activated during the window receives one only once confirmed.<br>AU-12 itself is not edited (DIR-043 §3). | SECURITY AU-21, AU-25, H8-21, H9-14 |
| R5-10 | **Level 1, fixed now.** RV-18 states that:<br>• the role row takes its window-start values, `granted_by` and `granted_at` included, from the before-image of the audit event that recorded its removal;<br>• the role given during the window is replaced by the same statement and privilege a role change uses (RV-04). That statement predates the amendment and is unchanged.<br>• a restored activation clears `deactivated_at` and `deactivated_by`;<br>• no user row stands for the operator.<br>M-46 asserts the written values. | CONCURRENCY_IDEMPOTENCY RV-18, M-46 |
| R5-11 | **Carried under DIR-050 §2** as a LOW non-material finding. Phase: P9, with P10 for the installation step. Owner: the Planner. The installation step records the operator's identity as AU-14 records it, and P9's own authorization extends RG-11's operator rule to it. | GAP-049 (OPEN) |
| R5-12 | **Level 1, fixed now.**<br>• TX-07 names AU-25.<br>• TM-57's proof column adds H8-21.<br>• The count paragraph names the OWNER-restoration command under the K4 residual, with RISK-005. | CONCURRENCY_IDEMPOTENCY TX-07; SECURITY TM-57, the count paragraph |

## Level-1 choices of the round-5 corrections

- **Where the window start is recorded:**
  - It is an input of the incident command, recorded in its run's audit events. That is one durable record, written before any recovery step, with no field added.
  - Every later step names the run and refuses any other value.
  - When the start cannot be dated, the leaked key's creation time is in the key register at once, so containment is not delayed.
- **The end of a run's recovery:** an audit event of the run, written with the OWNER-restoration command. It is an audit action, not a field and not a new command. *(Refined by R6-01, [Dispositions of round 6](#dispositions-of-round-6): the command records the end only when every active account holding the OWNER role is a pre-window Owner of the run or has an Owner-recovery issue in the run naming a confirmer, so the end never lifts the guard from an unreviewed account.)*
- **R5-02's disclosure:** placed in TM-57, whose command it concerns, rather than in a new threat row. The threat model stays at 73 rows.
- **R5-11:** registered as its own gap, GAP-049, rather than as a continuation note, so that a reader with no context finds the carried item, its owner and its phase.
- **The breach identifier:** the submission identifier of the triggering failure, already carried by TWO_FACTOR_FAILED, rather than the store's breach time. It is exact across the store and the event without any clock agreement.

## Adversarial review — round 6 (focused)

Run on 2026-10-04 under DIR-050 §2 by a fresh agent with no prior context, bound by the rule of DIR-046 §2, read-only. It ran on the working tree corrected after the round-5 dispositions. Its scope was the corrections of R5-01, R5-03 and R5-05 and everything they touch, the other round-5 corrections, a regression run of the mechanical checks, and accepted risk. The report below is recorded verbatim, as the agent returned it to the executor. The agent's report repeats the heading and text of R6-05 twice; it is recorded as returned, and the two copies are one finding. A copy is kept outside the repository at `C:\Users\YUSUFS~1\AppData\Local\Temp\claude\C--Projects-MultipleCorp\2dc7d0a3-0abc-47f2-a842-c97e1ca046a8\scratchpad\P5_AUTH_ADVERSARIAL_REVIEW_ROUND6_2026-10-04.md` (425 lines, 31,571 bytes, SHA-256 `B76A28F82EDC0C86A4179B40FA5A6451A8BF1DACF93C0A053D5A536F961F9F1F`).

````markdown
# Adversarial review, round 6 (focused): P5 authentication amendment

**Reviewer and scope.** I am an independent agent with no prior context. I worked read-only on 2026-10-04 on the working tree of `amendment/p5-auth`: HEAD 6dbc49a, plus 27 modified files and 8 untracked files (source records 41–47 and the gate record). The scope was the round-5 corrections of R5-01, R5-03 and R5-05 and everything they touch, the other round-5 corrections, a regression run of the mechanical checks, and accepted risk.

**What I read**
- **DIR-050** in full.
- **Gate record:**
  - round 5 verbatim, "Dispositions of round 5" and "Level-1 choices of the round-5 corrections";
  - "Dispositions of round 4" and "Level-1 choices of the third continuation".
- **SECURITY:**
  - AU-05, AU-12, AU-14, AU-18, AU-21, AU-25;
  - LG-01, LG-02, LG-07;
  - TM-57 and the §8 count paragraph;
  - H8-17, H8-21, H9-07, H9-14.
- **PERMISSIONS_MATRIX:**
  - RG-01–RG-05, RG-09–RG-11, OD-01 and CS-01–CS-08;
  - the capability catalogue, compared with c26f31e.
- **CONCURRENCY_IDEMPOTENCY:** D-CC-10, TX-07, RV-01–RV-18, H6-07, M-44–M-46, HO-41.
- **DATABASE:**
  - s04-00, compared with c26f31e;
  - s04-01, compared with c26f31e and 6dbc49a;
  - `audit_events` and `security_events` in s04-13.
- **GAP_REGISTER:** the triage table, the totals paragraph, GAP-041 and GAP-045–GAP-049.
- **DECISION_LOG:** the TECH-025 index row, the R-02 text of the DIR-044 entry, and the DIR-049/DIR-050 section.
- **Other files:** the SOURCE_OF_TRUTH rows for records 41–47, and the diffs of CHANGELOG, PHASE_STATUS and CURRENT_HANDOFF.

I did not read DIR-043, DIR-045 or DIR-048 in full. I relied on DIR-050 §5 and DECISION_LOG for their guards.

**Compliance with DIR-046 §2**
- I opened, listed and searched no transcript, conversation log, history, `.claude` content, `tool-results` folder, Temp or scratchpad folder. That includes the scratchpad path named in the gate record.
- The harness diverted one Bash output (DECISION_LOG lines 547–585) to a tool-results file. I did not open it. I re-read the same lines from the working tree with line-limited reads.
- I created, modified, staged and deleted nothing, and wrote no temporary file.
- Git commands used, all read-only:
  - `status`, `diff` (with `--stat` and `--word-diff`), `show`, `ls-tree`, `rev-parse`;
  - `hash-object --no-filters` without `-w`;
  - `cat-file blob`, which is read-only, used in place of `show` for byte comparison.
- My checks were Python scripts fed on stdin that read files into memory.

## Mechanical checks

1. **Source records — PASS.**
   - There are 47 files. The 40 committed at 6dbc49a are byte-identical to their blobs (40 of 40).
   - Records 41–47 match their SOURCE_OF_TRUTH SHA-256 and line/byte counts:
     - 87B8E1E7…, 47 / 2,072;
     - 820E1214…, 354 / 17,840;
     - FB7D46BE…, 196 / 9,040;
     - B8A49FBC…, 16 / 945;
     - C63F72E1…, 317 / 15,686;
     - 347C9436…, 61 / 3,480;
     - 3723862B…, 370 / 18,017.
   - Each of the seven has a `-text` entry in `.gitattributes`.
   - The fenced reports in the gate record match their recorded hashes:
     - round 2: DE89EA5E…;
     - round 3: B4FC374C…;
     - round 4: 19B335F0…;
     - round 5: 4C75AAF2…, 504 lines / 37,089 bytes.
2. **Capabilities — PASS.** 80 capabilities: ADM 37, ADM_PLUS 26, OWNER_ONLY 17. Against c26f31e, only the Covers column of `users.manage` changed.
3. **Tables — PASS.**
   - The module map has 13 modules summing to 125, with IAM 8.
   - `users` has 5 identifiers at c26f31e plus exactly four new ones: `two_factor_secret`, `two_factor_recovery_codes`, `two_factor_confirmed_at`, `two_factor_last_step`.
   - The identifier sets of `users`, `user_roles` and `user_external_identities` are unchanged against 6dbc49a.
4. **Threat model — PASS.** 73 TM rows, all unique.
5. **Security events — PASS.** 39 kinds (19 + 2 + 18), all unique; OWNER_RESTORED is among them.
6. **Gap register — PASS.**
   - The triage table has 49 unique rows, and there are 49 sections.
   - Recomputed: 3 CLOSED, 39 OPEN, 0 OWNER_DECISION_REQUIRED, 7 ACCEPTED_RISK. This equals the stated totals.
   - The other state records are stale (R6-09).
7. **Identifiers — PASS.**
   - No duplicate within one file is new against c26f31e.
   - There are 139 new identifiers. Those that appear in more than one file are:
     - DIR index rows, in DECISION_INDEX and DECISION_LOG;
     - the H6 mapping table;
     - the gate record's residual table.
   - No reference in a changed file points to an undefined identifier, across the AU, RV, M, H6–H9, TM, D-SEC, GAP, DIR, RISK, LG, SX, IX, C, HO, AZ, TECH, OBS, RG, OD, CS, WS, LR, LK, TX, CI, D-CC, NX, BD, SZ, DEP and APPR families.
8. **Links and anchors — PASS.** 1,721 relative links checked. Only two do not resolve, both as expected: `#gate` (gate record, line 217) and `#adversarial-review--round-6-focused` (line 1950). `#zero-context-check` is not referenced.
9. **Character identity — PASS.**

   | Passage | Length | Where it is identical |
   | --- | --- | --- |
   | Residual (e) | — | 11 places: SECURITY ×4, GAP_REGISTER, DECISION_LOG ×4, DECISION_INDEX, gate record. The only other wording is the historical DIR-044 text. |
   | Durability passage | — | AU-05, D-CC-10 and H6-07, from "the limit is a project-written atomic script" through "no failover store serves them" |
   | R-01 sentence | 645 | D-SEC-16, H6-07, GAP-045 |
   | Protocol (a)–(d) | 463 | AU-21, H8-21, H9-14, RV-17, HO-41, and the gate record |
   | Order sentence | 630 | AU-14, AU-21, AU-25, H8-21, H9-07, H9-14 |
   | Guard | 678 | AU-14, AU-25, H9-14 |
   | Halt passage | 301 | AU-25, H8-21, H9-14 |
   | Terms | 815 | AU-25, H9-14 |
   | Scenarios | 1,386 | AU-25, H9-14 |
   | Review passage | 903 | AU-21, AU-25, H9-14 |
   | Command limits | 641 | AU-25, RV-18 |

   AU-21 and RV-17 do not state the window-start input (R6-06).
10. **No approval or publication claim — PASS.** PHASE_STATUS and CURRENT_HANDOFF are pre-worded as of the fourth commit ("every gate passed", "documentation gate PASS"). Those statements become true only after the gate — the same caveat as round 5.
11. **No secret, placeholder or client ID — PASS.** The only hits are the existing `TASK-XXX` path template and PHASE_STATUS "TODO" statuses.
12. **No operator or system user row — PASS.** Every statement forbids one. RV-18 writes no user row.
13. **Earlier phases' quality-gate evidence — PASS.** The diff against c26f31e touches no evidence file.
14. **Accepted risk (F) — PASS.**
    - No text claims an accepted risk beyond:
      - K4, with TM-57 and RISK-005 now covering the restoration command;
      - (e) as extended;
      - (f).
    - R5-02 is disclosed and not accepted: TM-57, the count paragraph, and GAP-048 OPEN.
    - TM-72 / GAP-047 is still OPEN.
    - R6-02(b) and R6-04 below are undisclosed residuals, not claimed acceptances.

## Findings

### R6-01 — MEDIUM — MATERIAL — Level 1: The Owner-recovery guard stops applying when the operator records the end of recovery. Nothing conditions that event, so an unreviewed window Owner can then be recovered under K3 alone.

**Locations**
- The guard sentence in SECURITY AU-14, AU-25 and H9-14: "until the run's recovery ends, the Owner-recovery command issues a link without a named confirmer only for …".
- AU-25: "it refuses once the run's recovery has ended, which the operator records with the command in an audit event of the run".
- CONCURRENCY RV-18, M-46; SECURITY H8-21.
- The gate record:
  - the R5-05 row ("After it, the command refuses, and the guard of R5-01 no longer applies");
  - "Level-1 choices of the round-5 corrections".

**Problem**
- One operator-recorded event does two things:
  - it closes the restoration command, which fails safe;
  - it lifts the AU-14 guard, which fails open.
- Nothing ties the event to the end of the review. Nothing requires that, by then, every account holding the OWNER role is one of these:
  - a pre-window Owner;
  - an account recovered with a named confirmer;
  - an account the review reversed.
- After the event, AU-14 falls back to K3's order, which only the runbook checks ("the runbook uses it only when neither can serve"):
  - after the incident every recovery code is void, so a window Owner meets "no usable recovery code";
  - whether another Owner "can serve" is the operator's judgement.
- DIR-049's rule has no time limit. R5-05's purpose was to close operator error and deception, which TM-57 does not accept; it accepts only abuse.
- Secondary effect: a run whose end is never recorded keeps the guard on. An Owner appointed legitimately long after that window then cannot be recovered under K3 without a confirmer who never reviewed it.

**Failure scenario**
1. X was made OWNER during the window. After the incident command, pre-window Owner A is restored and recovered; the review has not yet dealt with X.
2. The operator takes "recovery" to mean the pre-window Owners' recovery and records the end. Or X persuades the operator that recovery is over and that A is abroad.
3. X comes in person and is verified as X. X has no usable codes and no other Owner is available, so K3 applies and the link is issued without a confirmer.
4. X becomes a full Owner and removes A's role, which RV-04 allows while one Owner remains.

**Recommended fix**
- Key the confirmer requirement on the window, not on the end of recovery:
  - an account whose OWNER assignment, or whose activation while holding OWNER, falls inside a run's recorded window always needs a named confirmer that passes the R5-01 checks;
  - the exception is an account for which the audit trail already shows an issue in that run naming a valid confirmer.
- The window is closed, so later Owners are unaffected. The end event then closes only the restoration command.
- Alternative: the command refuses to record the end while any OWNER-role account is neither a pre-window Owner of the run nor shown with such an issue.
- Add the case to M-46 and H8-21, and correct the R5-05 disposition and Level-1 wording. No field is added.

### R6-02 — MEDIUM — MATERIAL — Level 1: The OWNER-restoration command does not require the account to be untouched since the incident command. It can therefore reverse a recovered Owner's later decision, and can create an OWNER account with a usable password outside path (e).

**Locations**
- CONCURRENCY RV-18: "re-read … that its role or its active flag was removed during the window and that the run has not already restored it … set back only what is still missing — the OWNER role assignment, the active flag, or both — to its window-start value".
- SECURITY AU-25:
  - "The account's password is already unusable from the incident command, so the person recovers only through AU-14, path (e)";
  - "needs no restoration: the command changes nothing";
  - step (4), whose review covers "the accounts, roles, … created or changed during the window".
- AU-12's post-incident link.
- AU-18 *Enforcement*; DIR-044 R-05.
- M-46, H8-21, H9-14.

**Problem**
- **(a) Later decisions reversed.** The precondition looks for a removal during the window, but the write compares the current state with the window-start values. Only an account the run has already restored is protected. For an account not yet restored:
  - its flag was removed in the window and its role after the window by a recovered Owner: both are set back;
  - its role was removed and reassigned in the window (R5-01(a), "needs no restoration") and removed again after the window: the role is set back.

  The command stays usable until the operator records the end, so this can happen during or after the review. R5-05(c) is closed only for accounts already restored.
- **(b) The password premise is never checked.** A demoted pre-window Owner, now ADMIN:
  - counts as "an account other than an Owner's" under AU-12;
  - falls inside the review's scope;
  - can therefore receive a RESET link (step 4, AU-12, or an AU-20 c reissue) before its holder appears.

  A later restoration keeps the password that link set, any optional TOTP and the kept Google link. The result is a high-risk account with a usable password, without R-05's reissue and without path (e). If someone else consumed the link (TM-67), that person gets an Owner's enrolment-only session, enrols, and acts as Owner until the operator's path (e) link is used.
- **(c) The no-op case is undefined.** It is not stated whether "no restoration needed" means a refusal, or a commit that writes an audit event and raises OWNER_RESTORED. That decides whether the account counts as "already restored" in the run.

**Failure scenarios**
1. A and B are pre-window Owners, and A was deactivated in the window. B recovers first and, in the review, removes A's OWNER role for cause. A comes in person and is restored, with flag and role both set back. A then removes B.
2. The review keeps the demoted A as ADMIN and gives the account a RESET link, which an interceptor consumes. A later appears and is restored as OWNER. The interceptor logs in, enrols, and changes a bank account before A's OWNER_RECOVERY link is used.

**Recommended fix**
- In RV-18 and AU-25, the command refuses with the current state unless the audit trail shows the account untouched since the incident command's run:
  - no role, flag or grant change;
  - no credential-link issue or use;
  - no password set;
  - no TOTP enrolment;
  - no session established.
- It sets back only an attribute whose last change was a removal during the window.
- The no-change case is refused without writing, so nothing is alerted.
- AU-25 step (4) and H9-14 leave a pre-window Owner's account untouched until its restoration and recovery.
- Add the cases to M-46 and H8-21.
- This stays within DIR-050 §3.5 ("only for … removed during the window"; "never … sets a password … or a credential link"), and adds no field.
- A recovered Owner's removal of a pre-window Owner not yet restored then stands. That is the contest GAP-048 already describes; note it there.

### R6-03 — MEDIUM — MATERIAL — Level 1 (Owner-level only if the fix is declined): The restored state runs Scenario A's in-application steps without Scenario A's precondition, and the restore's other exposures are not closed.

**Locations**
- SECURITY AU-25 and H9-14, in their identical passage:
  - "against the restored audit trail, which the verification makes authoritative";
  - "the runbook installs new keys, the leaked key never listed";
  - "the application staying closed in the maintenance mode meanwhile".
- SECURITY AU-21 and H8-21.
- DIR-050 §3.2 ("Only then is the audit trail authoritative (RG-11) and the in-application path below used") and §3.6.
- AU-21's runbook, which rotates every other SX-01 secret.

**Problem**
- **(a) Authority of the restored trail.** Verifying a backup proves its content was clean when taken. It does not exclude the attacker's write access to the restored system: a snapshot brings back the entry point and the host access the attacker had. Steps (2)–(4) then read an audit trail the attacker can extend — exactly the situation §3.2 excludes.
- **(b) Restored secrets.** A snapshot restore brings back the snapshot's environment and service-side secrets:
  - the database role passwords inside the cluster;
  - the Valkey password;
  - the Google client secret and the mail credentials;
  - host credentials.

  "installs new keys" covers only APP_KEY and the HMAC keys.
- **(c) Containment of the untrusted state.** On a host the attacker can write, maintenance mode is a flag the attacker can lift. It contains nothing, and the leaked credentials stay usable there.
- **Contingency.** Without (a), the restored-state path conflicts with DIR-050 §3.2's "only then" and with §3.6, and needs the Owner. With (a), the restored state is effectively a Scenario-A state, consistent with §3.2, §3.6 and R-02.

**Failure scenario**
1. A snapshot from before the write compromise is restored, new keys are installed and the incident command runs.
2. The entry point is unchanged, for example an unpatched service or an SSH key held in the snapshot.
3. The attacker re-enters and inserts an audit event that makes X a pre-window Owner.
4. X is recovered without a confirmer.

**Recommended fix**
- In AU-25, AU-21, H8-21 and H9-14, state three things:
  - The restored state is brought up, and steps (2)–(4) run, only once P9's inventory excludes the attacker's write access to the restored database and server. Otherwise recovery stops for the Owner's incident decision outside the application.
  - After the restore and before any request is served, AU-21's full rotation runs again: every SX-01 secret the leak may have included, as it stands in the restored environment and services.
  - The untrusted host is taken out of service as soon as Scenario B is chosen.
- Add the test to H8-21.

### R6-04 — LOW — MATERIAL — Level 1 (disclosure now; the Owner decides at approval): On a restored state whose backup predates the window start, the restored Owner set stands in for the window-start set. An Owner removed legitimately between the backup and the compromise comes back and is recovered without review. No document says so.

**Locations**
- SECURITY AU-25 and H9-14, the restored-state passage.
- The AU-14 guard ("the audit trail shows held the OWNER role and was active at the run's recorded window start").
- TM-57, the count paragraph, GAP-048.

**Problem**
- The restored trail ends at the backup time B. Status at the recorded start S, which is later than B, can only be inferred from the last state before B. Changes between B and S are lost:
  - an Owner added in that interval is simply missing, which affects availability only;
  - an Owner removed in that interval is back as a full Owner, recovered through path (e) with no confirmer.
- The in-person check proves identity, not entitlement.
- The R5-02 display shows only removals the restoration reversed, and none of these is in the restored trail.

**Failure scenario**
- Co-owner C was removed for cause two weeks before the compromise, and the verified backup is three weeks old.
- On the restored state C is an Owner, comes in person, is recovered, and stands equal in the review — R5-02's effect by another route.

**Recommended fix**
- Disclose it in TM-57, the count paragraph and GAP-048 (extended), or in a new OPEN gap, as a consequence of DIR-050 §3.6 as decided, with no acceptance claimed.
- State in AU-25 and H9-14 that on a restored state pre-window status is read as of the backup's time.
- Have the runbook show every recovered Owner, before the review, the backup's time and the restored Owner set.
- Accepting the residual would go beyond K4, (e) and (f).

### R6-05 — LOW — MATERIAL — Level 1: The confirmer check ignores the confirmer's current status, and the confirmation itself rests on the operator's word.

**Locations**
- The guard in AU-14, AU-25 and H9-14: "checking that the confirmer held the OWNER role and was active at that window start and that the audit trail shows its OWNER_RECOVERY link use after the window".
- M-46, H8-21; the alert of OWNER_RECOVERY_LINK_ISSUED (AU-14, LG-07).

**Problem**
- **(a)** A pre-window Owner who recovered and was then removed or deactivated in the review still passes as a confirmer.
- **(b)** The command checks only that the confirmer is eligible. That this person actually confirmed this account is the operator's statement:
  - nothing says the operator gets the confirmation from the confirmer;
  - the issue's alert does not reach the confirmer.

  "P confirmed me" is therefore a deception path, outside TM-57's abuse risk.

**Failure scenario**
- Q removes P's role in the review.
- X, P's accomplice and a window Owner, tells the operator "P confirmed me", and P confirms by phone.
- X is recovered as an Owner.

**Recommended fix**
- Check, under the confirmer's row lock (LK-03, taken with the account's row in id order, as RV-15 does), that the confirmer currently holds the OWNER role and is active.
- Name the confirmer in the issue's audit and security events, and alert the confirmer.
- In the runbook (H9-07, H9-14), obtain the confirmation from the confirmer in person, never through the account being recovered.
- Add the case to M-46 and H8-21.

### R6-05 — LOW — MATERIAL — Level 1: The confirmer check ignores the confirmer's current status, and the confirmation itself rests on the operator's word.

**Locations**
- The guard in AU-14, AU-25 and H9-14: "checking that the confirmer held the OWNER role and was active at that window start and that the audit trail shows its OWNER_RECOVERY link use after the window".
- M-46, H8-21; the alert of OWNER_RECOVERY_LINK_ISSUED (AU-14, LG-07).

**Problem**
- **(a)** A pre-window Owner who recovered and was then removed or deactivated in the review still passes as a confirmer.
- **(b)** The command checks only that the confirmer is eligible. That this person actually confirmed this account is the operator's statement:
  - nothing says the operator gets the confirmation from the confirmer;
  - the issue's alert does not reach the confirmer.

  "P confirmed me" is therefore a deception path, outside TM-57's abuse risk.

**Failure scenario**
- Q removes P's role in the review.
- X, P's accomplice and a window Owner, tells the operator "P confirmed me", and P confirms by phone.
- X is recovered as an Owner.

**Recommended fix**
- Check, under the confirmer's row lock (LK-03, taken with the account's row in id order, as RV-15 does), that the confirmer currently holds the OWNER role and is active.
- Name the confirmer in the issue's audit and security events, and alert the confirmer.
- In the runbook (H9-07, H9-14), obtain the confirmation from the confirmer in person, never through the account being recovered.
- Add the case to M-46 and H8-21.

### R6-06 — LOW — NON-MATERIAL — Level 1: The rows of the commands that write or read the window start do not say so.

**Locations**
- SECURITY AU-21.
- CONCURRENCY RV-17 (which records only "the run's identifier in its details") and HO-41.
- SECURITY AU-14.
- Compare AU-25's Terms and RV-18.

**Problem**
- AU-25 says the start is "recorded once … as an input of the incident command in its run's audit events". AU-21 and RV-17 — RV-17 being the sequence P6 implements — neither take nor record it.
- RV-17's restart does not refuse a different start.
- AU-14 does not say:
  - that the issue records the start;
  - which run it reads when there are several.
- DIR-050 §6 step 7 asks §3 to be stated the same way in AU-21.
- The gap fails safe — RV-18 refuses without a start — but an incident command built from RV-17 would leave the restoration and the guard unusable.

**Recommended fix**
- In AU-21 and RV-17:
  - the incident command takes the start established with the Owner as an input;
  - it records the start with the run identifier in every audit event of the run;
  - a restart refuses any other value.
- In AU-14: the issue records the run and its start, and reads the latest run whose recovery has not ended.
- Add a test to HO-41 and H8-21.
- Fix this before the gate. It can be carried to P8 (owner: Planner) only if the gate accepts AU-25's statement as enough.

### R6-07 — LOW — NON-MATERIAL — Level 1: No path is stated for a window start that turns out to be wrong.

**Locations**
- SECURITY AU-25 Terms, H9-14.
- CONCURRENCY RV-18 ("any other value refused"), M-46.
- The Level-1 choice "the leaked key's creation time is in the key register at once, so containment is not delayed".

**Problem**
- **(a) A start known only later.** Containment may use the key's creation time as a conservative start. That start can reach back before every OWNER assignment, which forces Scenario B's destructive restore, or widens GAP-048 — and the run can never narrow it.
- **(b) A start later found to be earlier.** An account made Owner between the true start and the recorded start has already been recovered as pre-window, against DIR-050 §6 step 7.
- **(c) Scenario B.** The containment run's record is lost with the replaced state, and nothing says the restored run records the same start.

**Recommended fix**
- In AU-25 and H9-14, state that a corrected start — narrower, earlier or mistyped — means a new run of the incident command with that start, whose recovery follows AU-25 again.
- The new run's window spans the earlier run's recovery, so the runbook shows the recovered Owners the earlier run's decisions.
- Keep the established start in the incident record outside the application.
- Carry: P9 (owner: Planner; H9-14), with an M-46 case in P8.

### R6-08 — LOW — NON-MATERIAL — Level 1: The halt conditions can loop, or force a restore that achieves nothing.

**Locations**
- SECURITY AU-25 "When the in-application path stops", with H8-21 and H9-14.
- The restored-state sentence "stops for the Owner's incident decision … when no pre-window Owner is available on it".
- DIR-050 §3.4 and §3.6.

**Problem**
- **(a) A loop on the restored state.** The halt "no pre-window Owner can be established with certainty" still says "handled as Scenario B", which sends recovery back to another restore. Examples: an OWNER assignment with no audit event, or the first Owner installed before the P9/P10 obligation existed.
- **(b) A pointless restore on a trusted state.** "No pre-window Owner is available" leads to Scenario B, so a backup is restored and every record since it is lost — though a restore cannot make a person available — before stopping for the Owner's decision anyway.

**Recommended fix**
- On a restored state, either halt condition ends in the Owner's incident decision outside the application.
- On a trusted state, a halt goes to that decision, which may choose a restore.
- If "handled as Scenario B" is read as mandating the restore, only state that the restore precedes the decision, and leave any change to the Owner.
- Carry: P9 (owner: Planner; H9-14).

### R6-09 — LOW — NON-MATERIAL — Level 1: Precision and state items

- **RV-18's before-image.** "The before-image of the audit event that recorded its removal" is ambiguous when the window holds several removals. Only the first change after the window start carries the window-start `granted_by` and `granted_at`; the last could name a window account as the granter. Carry: P8 (owner: Planner; M-46).
- **H9-07.** It states the order but not the confirmer step that AU-14's command now enforces; H9-14 has it. Carry: P9 (owner: Planner).
- **Stale state records.** Fix before the gate and the commit:
  - the DECISION_LOG TECH-025 index row still reads "GAP-038–GAP-047 registered";
  - CHANGELOG's third-continuation bullet records neither GAP-048, GAP-049 nor the totals of 49, which earlier bullets each record, and still says "the final focused review";
  - DECISION_LOG has no TECH-025 entry for the round-5 corrections.

## Interactions checked

- **Incident protocol (a)–(d):** unchanged and character-identical in all six places.
- **Credentials on the restored state.** Once the new keys are installed and the incident command has run, no exposed application credential can serve a request:
  - passwords are unusable;
  - TOTP data and recovery codes are voided;
  - links are revoked and the HMAC keys rotated;
  - sessions are deleted, and the new APP_KEY voids old cookies and signed URLs;
  - there is no remember-me (AU-08);
  - Google sign-in is ineligible until TOTP is active, and then still needs TOTP.

  The remaining exposures are outside the application (R6-03).
- **R5-01's guard and the restoration command:**
  - a pre-window Owner whose role was removed and reassigned during the window gets a link without a confirmer — correct;
  - its restoration is an undefined no-op (R6-02c);
  - a reactivated former Owner is refused without a confirmer;
  - a confirmer must have been a pre-window Owner, but its current status is not checked (R6-05);
  - the guard stops applying at the end event (R6-01).
- **Legitimate pre-window Owners:** no longer blocked by the guard. A pre-window Owner left unrestored when the end is recorded must be promoted by another Owner — acceptable.
- **R5-05's window start and Scenario B:** the record is sound within a run; the containment run's record is lost with the replaced state (R6-07c). "Second restoration refused" and the end event close R5-05(c) only for accounts already restored (R6-02).
- **Persistence:** no table, field, role or privilege is added.
  - `audit_events` has a `metadata jsonb` column and entity columns with no NOT NULL rule stated.
  - No catalogue of audit actions exists that would need extending.
  - What is new is that two commands read run-level audit events as control state (the start and the end), with precedents in RV-17's restart and R-01's floor. My fixes add no further mechanism.
- **R-01:** untouched by R5-01, R5-03 and R5-05. R5-04 and R5-07 are consistent across:
  - LG-02, where the breach is keyed by the triggering submission identifier;
  - the durability passage;
  - RV-12, H8-17 and M-44.
- **Locks:** RV-18 takes LK-02, then LK-03. The AU-14 issue takes LK-03, then LK-04. R6-05's fix adds the confirmer's row at LK-03 in id order (as RV-15 does), with no lock cycle.
- **Reset paths:** Owners recover only through path (e), except for the AU-12 / AU-20 c path to a demoted pre-window Owner described in R6-02b.
- **DIR-044 R-02 and DIR-050 §3.6:** consistent under the R5-03 reading, provided R6-03(a) is applied.

## Summary

- **By severity:** HIGH 0; MEDIUM 3 (R6-01–R6-03); LOW 6 (R6-04–R6-09).
- **By materiality:** MATERIAL 5 (R6-01–R6-05); NON-MATERIAL 4 (R6-06–R6-09).
- **May be carried under DIR-050 §2:**
  - R6-07: P9 (owner: Planner; H9-14), with an M-46 case in P8;
  - R6-08: P9 (owner: Planner; H9-14);
  - R6-09, first item: P8 (owner: Planner; M-46);
  - R6-09, second item: P9 (owner: Planner; H9-07).
- **Should be fixed before the gate:**
  - R6-06 — DIR-050 §6 step 7 asks for AU-21. It may be carried to P8 (Planner; HO-41, H8-21) only if the gate accepts AU-25 as enough.
  - R6-09's state records.
- **Further focused review:** yes. The corrections of R6-01, R6-02, R6-03 and R6-05 change how security controls behave:
  - when the guard stops applying;
  - the restoration command's preconditions;
  - Scenario B's preconditions;
  - the confirmer check.

  Under DIR-050 §2 they therefore need another focused review (round 7), limited to those changes. R6-04 (disclosure only) and the non-material items do not need one on their own. No recommended fix adds a persistence mechanism, table, field, role or privilege.
- **OWNER-LEVEL:** none requires a stop now. Three findings can still reach the Owner:
  - **R6-03** becomes Owner-level if the restored-state path is kept without Scenario A's precondition, because it would then conflict with DIR-050 §3.2 and §3.6.
  - **R6-02** becomes Owner-level if DIR-050 §3.5 is read as requiring restoration whatever happened after the window. That conflicts with DIR-044 R-05 and with the review's authority.
  - **R6-04** must be disclosed, not accepted. Like GAP-048, it goes to the Owner at approval. Accepting it would be an accepted risk beyond K4, (e) and (f), which is stop condition 7.
- **Accepted risk:** no text currently claims more than K4 (with TM-57 now covering the new command), (e) as extended, and (f).
````

## Dispositions of round 6

The convergence rule of DIR-050 §2 applies again. Round 6 raised no finding that needs a new Owner decision now. Its three contingent Owner-level notes do not arise, because the reviewer's recommended fix is applied to each:

- **R6-03:** the restored state takes Scenario A's precondition, which is consistent with DIR-050 §3.2 and §3.6.
- **R6-02:** the restoration refuses an account touched since the incident command, which is consistent with DIR-044 R-05 and the review's authority.
- **R6-04:** the residual is disclosed and not accepted.

The corrections of R6-01, R6-02, R6-03 and R6-05 change how security controls behave, so the focused [round 7](#adversarial-review--round-7-focused) re-checks them. No correction adds a table, field, role, privilege, persistence mechanism or dependency. Where DIR-050 is silent, the reviewer's recommended fix is followed.

| Finding | Disposition | Where it now stands |
| --- | --- | --- |
| R6-01 | **Level 1, the reviewer's alternative fix.** The OWNER-restoration command records the end of a run's recovery only when every active account holding the OWNER role is a pre-window Owner of the run or has an Owner-recovery issue in the run naming a confirmer. Otherwise it refuses with the accounts that block it. The end therefore never lifts the guard from an unreviewed account, and later Owners are unaffected once it is recorded. The alternative was chosen over keying the guard on the window for good, because it leaves AU-14's K3 use after recovery unchanged. Cases are added to M-46 and H8-21, and this record's Level-1 choice on the end of recovery is annotated. **The change is material, so round 7 re-checks it.** | SECURITY AU-14, AU-25, H8-21, H9-14 (the guard); CONCURRENCY_IDEMPOTENCY RV-18, M-46 |
| R6-02 | **Level 1, the reviewer's fix.**<br>• The command refuses, with the current state and without writing, an account the audit trail does not show untouched since the incident command's run. "Untouched" means no change of its role, active flag or grants, no credential link issued or used, no password set, no TOTP enrolled and no session established.<br>• It sets back only an attribute whose last change was a removal during the window.<br>• An account whose role and flag already hold their window-start values is refused without writing, so nothing is alerted.<br>• The review leaves the account of a pre-window Owner not yet restored and recovered untouched.<br>• A change made to such an account anyway stands, and that contest is noted in GAP-048.<br>This stays within DIR-050 §3.5. **The change is material, so round 7 re-checks it.** | SECURITY AU-25, AU-21, H8-21, H9-14 (the review passage); CONCURRENCY_IDEMPOTENCY RV-18, M-46; GAP-048 |
| R6-03 | **Level 1, the reviewer's fix.**<br>• The untrusted host is taken out of service as soon as Scenario B is chosen.<br>• The restored state is brought up only once P9's inventory excludes write access by the attacker to the restored database and server. Otherwise recovery stops for the Owner's incident decision outside the application.<br>• Before the restored state serves any request, the runbook installs new keys, rotates again every other SX-01 secret the leak may have included, as it stands in the restored environment and services, and runs the incident command.<br>This gives the restored state Scenario A's precondition, as DIR-050 §3.2 requires. A test is added. **The change is material, so round 7 re-checks it.** | SECURITY AU-25, AU-21, H8-21, H9-14 |
| R6-04 | **Disclosed, not accepted** (Level 1). The decision is the Owner's at approval.<br>• On a restored state, pre-window status is read at the window's start or, when the backup is older, at the backup's time.<br>• The runbook shows every recovered pre-window Owner, before the review, the backup's time and the restored Owner set.<br>• TM-57 and the count paragraph state the residual: an Owner removed legitimately between the backup and the window's start is reinstated, as a consequence of DIR-050 §3.6 as decided.<br>• GAP-048 is widened and renamed "Post-incident recovery can reinstate an Owner removed legitimately" to cover both routes, and stays OPEN for the Owner's decision at approval. | SECURITY AU-25, TM-57, the count paragraph, H8-21, H9-14; GAP-048 |
| R6-05 | **Level 1, the reviewer's fix.**<br>• Under the row locks of the account and the confirmer, taken in id order (LK-03), the Owner-recovery command also checks that the confirmer holds the OWNER role and is active now.<br>• The issue's audit and security events name the confirmer, who is alerted.<br>• The runbook obtains the confirmation from the confirmer in person, never through the account being recovered.<br>Cases are added to M-46 and H8-21. **The change is material, so round 7 re-checks it.** | SECURITY AU-14, AU-25, H8-21, H9-07, H9-14; CONCURRENCY_IDEMPOTENCY M-46 |
| R6-06 | **Level 1, fixed before the gate.**<br>• The incident command takes the window start established with the Owner as an input, and records it with the run identifier in every audit event of the run. A restart refuses any other value.<br>• The Owner-recovery issue reads the latest run whose recovery has not ended, and records that run and its window start.<br>• Tests are added to HO-41 and H8-21. | SECURITY AU-21, AU-14, AU-25, H8-21; CONCURRENCY_IDEMPOTENCY RV-17, HO-41 |
| R6-07 | **Carried under DIR-050 §2** as a LOW non-material finding. Phase: P9, with an M-46 case in P8. Owner: the Planner. A corrected window start means a new run of the incident command whose recovery follows AU-25 again, and the established start is kept in the incident record outside the application. | GAP-050 (OPEN) |
| R6-08 | **(a) Fixed, Level 1:** on a restored state, either halt condition ends in the Owner's incident decision outside the application, so recovery cannot loop.<br>**(b) Carried under DIR-050 §2** as a LOW non-material finding. Phase: P9. Owner: the Planner. On a trusted state a halt goes to the Owner's incident decision, which may choose a restore. DIR-050 §3.4's "handled as Scenario B" is left as the Owner wrote it. | SECURITY AU-25, H8-21, H9-14; GAP-050 (OPEN) |
| R6-09 | **Fixed now rather than carried** (Level 1).<br>• The restored role row takes its values from the before-image of the window's first change of that row.<br>• H9-07 states the confirmer step.<br>• The stale state records — the TECH-025 index row, the CHANGELOG bullet and a TECH-025 entry for the final reviews — are corrected before the gate. | CONCURRENCY_IDEMPOTENCY RV-18, M-46; SECURITY H9-07; DECISION_LOG TECH-025; CHANGELOG |

## Adversarial review — round 7 (focused)

Run on 2026-10-04 under DIR-050 §2 by a fresh agent with no prior context, bound by the rule of DIR-046 §2, read-only. It ran on the working tree corrected after the round-6 dispositions. Its scope was the corrections of R6-01, R6-02, R6-03 and R6-05 and everything they touch, the other round-6 corrections, a regression run of the mechanical checks, and accepted risk. The report below is recorded verbatim, as the agent returned it to the executor. A copy is kept outside the repository at `C:\Users\YUSUFS~1\AppData\Local\Temp\claude\C--Projects-MultipleCorp\2dc7d0a3-0abc-47f2-a842-c97e1ca046a8\scratchpad\P5_AUTH_ADVERSARIAL_REVIEW_ROUND7_2026-10-04.md` (239 lines, 20,942 bytes, SHA-256 `88FE296D3E4C8AD501387505F9A86842A6DF7F32DE245BF3813BA5CAD3BC66B1`).

````markdown
# Adversarial review, round 7 (focused): P5 authentication amendment

**Scope and compliance.** I am an independent agent with no prior context. On 2026-10-04 I worked read-only on `amendment/p5-auth`: HEAD 6dbc49a plus 27 modified and 8 untracked files.

**What I read**
- DIR-050, in full.
- The gate record: round 6 (verbatim), "Dispositions of round 6", "Dispositions of round 5" and "Level-1 choices of the round-5 corrections".
- SECURITY:
  - AU-05, AU-11, AU-12, AU-14, AU-18, AU-20, AU-21, AU-25;
  - LG-01, LG-02, LG-07, SX-01–SX-07;
  - TM-57 and the §8 count paragraph;
  - D-SEC-19, H8-21, H9-06, H9-07, H9-10, H9-14.
- CONCURRENCY_IDEMPOTENCY: LR-01–LR-12, LK-00–LK-31 with §4.3, TX-07, CI-09, RV-01–RV-18, H6-07, H6-11, M-44–M-46, HO-41.
- PERMISSIONS_MATRIX: RG-11 and the capability catalogue.
- DATABASE: s04-00 and s04-01.
- GAP_REGISTER: GAP-045–GAP-050 and the totals.
- DECISION_LOG: the R-02 and R-05 text of DIR-044, and the DIR-049/DIR-050 entries.
- SOURCE_OF_TRUTH: the rows for records 41–47.

**Compliance with DIR-046 §2**
- I opened, listed and searched no transcript, conversation log, history or cache, nothing under `.claude`, no `tool-results` folder, and no Temp or scratchpad path. That includes the path named in the gate record.
- The harness diverted one Bash output (DECISION_LOG lines 547–585) to a tool-results file. I did not open it. I re-read the lines I needed from the working tree, truncated line by line.
- I created, modified, staged and deleted nothing, and wrote no temporary file.
- Git commands used, all read-only: `status`, `log`, `diff`, `show`, `ls-tree`, `cat-file blob`, `grep`. My other checks were Python scripts fed on stdin that read files into memory.

## Mechanical checks

1. **Source records — PASS.**
   - There are 47 files. The 40 committed at 6dbc49a are byte-identical to their blobs.
   - Records 41–47 match SOURCE_OF_TRUTH in SHA-256 and in line/byte counts: 87B8E1E7… 47/2,072; 820E1214… 354/17,840; FB7D46BE… 196/9,040; B8A49FBC… 16/945; C63F72E1… 317/15,686; 347C9436… 61/3,480; 3723862B… 370/18,017.
   - Each of the seven has a `-text` entry in `.gitattributes`.
2. **Fenced reports — PASS.** The round 2–6 reports in the gate record match their recorded SHA-256 (DE89EA5E…, B4FC374C…, 19B335F0…, 4C75AAF2…, B76A28F8…). Round 6 is 425 lines and 31,571 bytes.
3. **Capabilities — PASS.** There are 80: ADM 37, ADM_PLUS 26, OWNER_ONLY 17. Against c26f31e, only column 5 (Covers) of `users.manage` changed.
4. **Tables — PASS.**
   - The module map has 13 modules summing to 125, with IAM 8; s04-01 has 8 table rows.
   - `users` gains exactly `two_factor_secret`, `two_factor_recovery_codes`, `two_factor_confirmed_at` and `two_factor_last_step` against c26f31e.
   - The identifier sets of `users`, `roles`, `user_roles`, both grant tables and `user_external_identities` are unchanged against 6dbc49a.
5. **Threat model — PASS.** 73 TM rows, all unique.
6. **Security events — PASS.** 39 kinds (19 + 2 + 18), all unique.
7. **Gap register — PASS.**
   - The triage table has 50 unique rows, and there are 50 sections.
   - Recomputed: 3 CLOSED, 40 OPEN, 0 OWNER_DECISION_REQUIRED, 7 ACCEPTED_RISK. This equals the stated totals.
   - CURRENT_HANDOFF describes GAP-048–GAP-050 consistently.
8. **Identifiers — PASS.**
   - No duplicate row identifier within a file is new against c26f31e.
   - The new identifiers defined in more than one file are only DIR rows (DECISION_INDEX and DECISION_LOG) and H6-13–H6-16 (SECURITY §10 and the H6 map).
   - No reference in a changed file is undefined. The only unmatched FS-/UX- references are pre-existing, and the same files referenced them at c26f31e.
9. **Links and anchors — PASS.** 1,721 relative links in 105 Markdown files. Only two do not resolve: `#gate` (line 217) and `#adversarial-review--round-7-focused` (line 2418). The round-6 anchor now resolves.
10. **Character identity — PASS.**

    | Passage | Characters | Identical in |
    | --- | --- | --- |
    | Residual (e) | — | 11 places: SECURITY ×4, DECISION_LOG ×4, DECISION_INDEX, GAP_REGISTER, gate record. Elsewhere only the historical DIR-044 wording. |
    | Durability passage | 2,287 | AU-05, D-CC-10, H6-07 |
    | R-01 sentence | 645 | D-SEC-16, H6-07, GAP-045 |
    | Order sentence | 630 | AU-14, AU-21, AU-25, H8-21, H9-07, H9-14 |
    | Guard | 1,305 | AU-14, AU-25, H9-14 |
    | Incident protocol (a)–(d) | 463 | AU-21, RV-17, H8-21, H9-14, HO-41 |
    | Scenarios | 1,960 | AU-25, H9-14 |
    | Review passage | 993 | AU-21, AU-25, H9-14 |
    | Halt passage | 301 | AU-25, H8-21, H9-14 |
    | Terms | 815 | AU-25, H9-14 |
    | Command limits | 641 | AU-25, RV-18 |

11. **No approval or publication claim — PASS.**
    - There is no new APPR identifier. Every APPROVED status line reads "pending Owner approval (TECH-025)".
    - The same caveat as rounds 5 and 6 applies: PHASE_STATUS lines 14 and 27 ("every gate passed") and CURRENT_HANDOFF line 27 ("documentation gate PASS") are worded as of the fourth commit.
12. **No secret, placeholder or client ID — PASS.** Nothing was found in the diff against c26f31e or in the new files.
13. **No operator or system user row — PASS.** RV-18, M-46, RG-11, AU-14 and D-SEC-19 all forbid one.
14. **Earlier phases' quality-gate evidence — PASS.** The diff against c26f31e touches no evidence file. The only new evidence file is the gate record.
15. **Accepted risk (G) — PASS.**
    - No text claims an accepted risk beyond:
      - K4, with TM-57 and RISK-005 covering the OWNER-restoration command;
      - (e), as DIR-047 extends it;
      - (f).
    - GAP-047 and GAP-048 (R5-02, R6-04) are disclosed, "not accepted" and OPEN in TM-57, the count paragraph and the register.
    - The TM-52/APPR-006 residual is labelled "not a new acceptance".

## Findings

### R7-01 — LOW — MATERIAL — Level 1: The Owner-recovery command can be run for a deactivated pre-window Owner before its restoration. That link issue counts as a "touch", so the restoration DIR-050 §3.3 step 2 requires is lost for the whole run.

**Locations**
- SECURITY AU-14: "issues an OWNER_RECOVERY link (AU-11) for an account holding the OWNER role and refuses any other account". There is no active check.
- AU-20 e.
- The guard's branch without a confirmer: "held the OWNER role and was active at the run's recorded window start".
- The order sentence. It states "first restored" but nothing enforces it.
- AU-25's command paragraph and CONCURRENCY RV-18: "untouched since the incident command's run — … no credential link issued or used …".
- H8-21, H9-07, H9-14, M-46; GAP-048's fourth bullet.
- AU-12's post-incident sentence. AU-12 itself cannot be edited (DIR-043 §3).

**Problem**
- Deactivation keeps the `user_roles` row (RV-05). A pre-window Owner whose active flag alone was removed during the window therefore still holds the OWNER role.
- AU-14 checks neither the flag nor whether a restoration is pending, and its guard admits the account without a confirmer.
- The link gives nothing while the account is inactive:
  - AU-04 and AU-09 refuse an inactive account;
  - RV-10 re-reads `is_active` when the link is used;
  - a later reactivation voids any password, through RV-11.
- RV-18 nevertheless counts the issue as a touch. It then refuses the restoration, without writing, for the rest of the run, and nothing removes the touch.
- After that the person returns only through another Owner's reactivation. That is exactly the dependency DIR-050 says no pre-window Owner has: §3.3, and the §6 step 7 gate question.
- (b) AU-12's post-incident sentence ("every account other than an Owner's receives a plain Owner-issued RESET link") literally covers a pre-window Owner demoted during the window. Only the review passage says to leave that account untouched. GAP-048 discloses that a recovered Owner's *removal* stands, but not that any touch, a RESET link included, defeats the restoration.

**Failure scenario**
1. A is the sole Owner. Through a hijacked session the attacker makes X an Owner and deactivates A.
2. The incident command completes. The operator follows the familiar K3 runbook (H9-07) and runs AU-14 for A. A holds OWNER and was active at the window start, so a link is issued without a confirmer.
3. The operator then notices the deactivation and runs the restoration. It is refused: A was touched since the run.
4. A cannot sign in, and X needs a confirmer that does not exist. Neither halt condition fits literally, because A is available. Recovery falls to the Owner's decision outside the application or to Scenario B's restore — R4-01's case again, caused by one step taken out of order.

**Recommended fix**
- **(i)** AU-14's command, and AU-20 e, refuse an account that is not active. Put this outside the identical guard sentence, for example in AU-14's first sentence. The order then holds for every pre-window Owner, since AU-14 already refuses a demoted one. Add cases to H8-21 and M-46.
- **(ii) Alternative:** RV-18 does not count as a touch an OWNER_RECOVERY link the operator issued for that account in the run, nor that link's use.
- **Either way:**
  - H9-07 and H9-14 check the audit trail for removals before any issue.
  - GAP-048's fourth bullet states that any touch defeats the restoration, a RESET link included, and that AU-12's post-incident link never goes to a pre-window Owner's account.

### R7-02 — LOW — NON-MATERIAL — Level 1: The end condition looks only at active OWNER accounts, and nothing says when the operator records the end.

**Locations**
- The end clause of the guard (AU-14, AU-25, H9-14).
- AU-25's command paragraph and the end sentence of RV-18.
- H8-21, M-46, H9-14.

**Problem**
- **(a) The end can be blocked.** A recovered Owner may appoint an Owner after the incident command, or reactivate an OWNER-role account (RV-04, RV-05, RV-11). That account is neither a pre-window Owner of the run nor has an Owner-recovery issue, so it blocks the end. Two ways out exist, and neither is stated:
  - an issue naming a confirmer — but K3 forbids the command while the account's own codes or another Owner can serve, and using the link would reset the account needlessly;
  - a temporary demotion.

  If the end is never recorded:
  - the guard stays on, and a later K3 recovery of any Owner that was not a pre-window Owner needs a confirmer from that run, who may no longer exist;
  - the restoration command stays usable, so an untouched pre-window Owner deactivated during the window can be restored long afterwards. That stretches the GAP-048 residual in time.
- **(b) The end can come too early.** Inactive accounts are ignored, so the end can be recorded while an untouched pre-window Owner, whose deactivation the trail shows, has not yet appeared. That person then returns only through another Owner's command. DIR-050 §3.5 limits the command to "incident recovery", so this is consistent with DIR-050 only if the end marks recovery actually being over. The zero-context answer to §6 step 7 needs this caveat.
- For security, the guard never lifts from an unreviewed active Owner, and (a) fails safe.

**Failure scenario**
1. A was deactivated in the window and is abroad. B recovers and conducts the review, and the operator keeps the end open for A.
2. B appoints N as an Owner. A returns and is restored.
3. The end is refused because N blocks it. Nothing documented clears N, so the end stays open indefinitely.

**Recommended fix (runbook, non-material)**
- State in AU-25 and H9-14 when the end is recorded:
  - once the review is complete;
  - and once every pre-window Owner whose removal the trail shows has been restored, or the Owner's incident record outside the application states that person is unavailable.
- No account is newly made an Owner before the end. One that was is demoted (RV-04) before the end and reinstated after it.
- Add cases to M-46 and H8-21.
- This may be carried to P9 (owner: Planner; H9-14), with the M-46 case in P8. Changing the condition itself instead — accepting an account whose OWNER assignment and activation both postdate the run, made by an Owner's audited command — would be material.

### R7-03 — LOW — NON-MATERIAL — Level 1: The confirmer alert exists only in AU-14. LG-07, H9-10 and LG-02 do not carry it.

**Locations**
- The guard: "the issue's audit and security events name the confirmer, who is alerted (LG-07)".
- LG-07: "every OWNER_RECOVERY_LINK_ISSUED … alert the named operator and the Owner".
- H9-10: "Delivery of security alerts to the named operator and the Owner (LG-07)".
- LG-02: the details are defined kind by kind, with none for OWNER_RECOVERY_LINK_ISSUED, and `actor_user_id` is the acting account.

**Problem**
- The confirmer is not a recipient in LG-07 or H9-10.
- LG-02 does not say where the confirmer goes. It cannot be `actor_user_id`, because the confirmer does not act.
- An implementation built from LG-07 and H9-10 drops the detective half of R6-05(b)'s fix.

**Recommended fix**
- LG-07 and H9-10: the confirmer named by an OWNER_RECOVERY_LINK_ISSUED is also alerted, under the same channel rule.
- LG-02: that event carries the confirmer's public id, the run and the window start in its whitelisted details, so no field is added.
- This only aligns the rows with behaviour already decided. Fix it before the gate.

### R7-04 — LOW — NON-MATERIAL — Level 1: M-46 and H8-21 state the end condition differently from AU-14 and RV-18.

**Locations**
- M-46, "Must hold": "no account that was not a pre-window Owner recovered as an Owner without a named confirmer …, before or after the end of recovery".
- H8-21: "neither a pre-window Owner nor recovered with a named confirmer".
- AU-14 and RV-18: "has an Owner-recovery issue in the run naming such a confirmer", with the guard holding only "until the run's recovery ends".

**Problem**
- **(a)** After the end, AU-14 recovers under K3 alone a confirmed window Owner or a later Owner. M-46's "or after" forbids that, so a P8 test built on M-46 contradicts AU-14.
- **(b)** H8-21 requires the link to have been used, where AU-14 needs only the issue.

**Recommended fix**
- M-46: "no account that was neither a pre-window Owner nor confirmed in the review by an issue naming such a confirmer recovered as an Owner, before or after the end".
- H8-21: "nor has an Owner-recovery issue in the run naming such a confirmer".
- Fix before the gate.

### R7-05 — LOW — NON-MATERIAL — Level 1: Precision items

- **(a) "Untouched since the incident command's run" does not exclude the run's own writes.** The run writes every unusable password hash and revokes every link. Read from the run's start, "no password set" would refuse every restoration. State "after its closing sweep, the run's own events excepted" in AU-25 and RV-18. Fix now.
- **(b) The Terms clause escapes R6-08(a)'s fix.** "A leaked key whose creation is unregistered or ambiguous … so Scenario B applies" is not covered by the restored-state rule, so on a restored state it still reads as another restore. On a trusted state it forces a restore that cannot register the key. State it as a case of the first halt condition. Carry with GAP-050 (P9, Planner).
- **(c) The end recording has no stated lock or transaction.** LK-02 anchors the set of active OWNER accounts, and M-46 has no race between the end and a role change or activation. A late race only equals an Owner's act after the end, so the security effect is nil. Carry to P8 (Planner; RV-18, M-46).
- **(d) RV-18 and AU-25 do not say which run the restoration reads when there are several.** AU-14 reads "the latest run whose recovery has not ended". Carry with GAP-050 (P9, Planner; an M-46 case in P8).

## Interactions checked

- **A. R6-01 (end of recovery)**
  - The end cannot be recorded while an unreviewed account is an active Owner. That includes one an Owner recovered through AU-20 c or d instead of AU-14, which is a useful backstop.
  - AU-14's K3 use after the end is safe. Every active Owner is then a pre-window Owner or was confirmed. An inactive OWNER-role account can be issued a link, because AU-14 has no active check, but it can neither use it nor sign in.
  - A window Owner that the review deactivates rather than demotes stays a dormant OWNER-role account, ignored by the end. It returns only through an Owner's reactivation, which RV-11 turns into a RESET link to that Owner — an Owner decision that DIR-050 §3.3 step 4 allows. Removing the role is the more robust of the review's two outcomes.
  - The end can be wrongly met only in the availability sense of R7-02(b), and never met in the case of R7-02(a).
- **B. R6-02 ("untouched")**
  - It is checkable from business audit events written inside their transactions: role, flag and grant changes and link issues. A password, a TOTP enrolment or a session first needs a link.
  - The incident command changes no role or flag, but "since" needs R7-05(a).
  - "Sets back only an attribute whose last change was a removal" is consistent with DIR-050 §3.5 and with the (user_id, role_id) key of `user_roles`: the OWNER row's last change is its deletion, and the before-image of the window's first change is the window-start row.
  - It blocks a legitimate restoration through the operator's own issue (R7-01), and otherwise only through acts contrary to the runbook.
  - No text requires another Owner's review before a pre-window Owner's restoration or recovery. A touch made against the runbook stands, as disclosed in GAP-048 (to be widened by R7-01(b)).
- **C. R6-03 (Scenario B's restored state)**
  - The restored state meets §3.2's Scenario A precondition.
  - §3.4: on a restored state, a halt ends in the Owner's decision.
  - §3.6: no step runs on the untrusted state, only an independently verified backup is used, and the DIR-009 backup is never presumed clean.
  - R-02: the full incident command runs on the restored state, after new keys and the rotation of the SX-01 secrets.
  - When the backup is older than the window start, reading status "at the backup's time" equals reading the restored trail at the start, because the trail holds no event between the two. The guard, the confirmer check and RV-18 therefore agree, and RV-18 then finds no removal inside the window.
  - No wording forbids what R-02 requires or requires what DIR-050 forbids.
- **D. R6-05 (locks and alert)**
  - The issue locks the account and the confirmer at LK-03 in id order, then LK-04.
  - RV-04 and RV-18 take LK-02, then LK-03, and RV-15 uses the same two-row pattern. All are ascending, so no cycle is possible.
  - The confirmer's role and flag are fixed by its own LK-03 (§4.3).
  - LG-07: see R7-03.
- **E. The other round-6 corrections**
  - R6-04 is disclosed and not accepted, in TM-57, the count paragraph and GAP-048.
  - R6-06 is present in AU-21, AU-14, RV-17, HO-41 and H8-21.
  - R6-08(a) is present, apart from R7-05(b).
  - R6-09 is present in RV-18 and H9-07.
  - GAP-050 carries R6-07 and R6-08(b), with the Planner as owner, P9 as phase and an M-46 case in P8.
- **Incident protocol and R-01:** both are character-identical and untouched by the round-6 corrections.
- **Persistence:** no table, field, role or privilege is added. The start, the end and the confirmer use existing audit events and details.
- **Reset paths:** Owners' ordinary commands stay outside the guard by design. AU-12 is covered under R7-01(b).

## Summary

- **By severity:** HIGH 0, MEDIUM 0, LOW 5.
- **By materiality:** MATERIAL 1 (R7-01); NON-MATERIAL 4 (R7-02–R7-05).
- **May be carried (LOW, non-material):**
  - R7-02: P9 (Planner; H9-14), with M-46 and H8-21 cases in P8;
  - R7-05(b) and (d): with GAP-050 (P9, Planner);
  - R7-05(c): P8 (Planner; RV-18, M-46).
- **Fix before the gate:** R7-03, R7-04 and R7-05(a), all wording alignments. R7-01 is material and is not carried.
- **Another focused review under DIR-050 §2:**
  - **R7-01, fix (i):** I judge this not a material change. It refuses only a link that could never be used on an inactive account, and changes no credential outcome and no guard decision. The GAP-048 and runbook wording is also non-material.
  - **R7-01, fix (ii):** this changes the restoration's precondition. It is material and would need a focused review.
  - **R7-02:** the runbook fix is non-material. Changing the end condition itself would be material.
  - **The other fixes:** none changes how a control behaves.
  - So no further focused review is needed if R7-01 takes fix (i) and R7-02 the runbook route. No recommended fix adds a persistence mechanism, a dependency, a table, a field, a role or a privilege.
- **OWNER-LEVEL:** none. Nothing here requires a stop.
- **Accepted risk:** nothing beyond K4 (TM-57 and RISK-005 covering the new command), (e) as extended, and (f). Stop condition 7 is not triggered.
````

## Dispositions of round 7

Round 7 raised no HIGH or MEDIUM finding and nothing that needs an Owner decision. Its one material finding, R7-01, takes the reviewer's fix (i). The reviewer judged that fix not material, because it refuses only a link that could never be used on an inactive account and changes no credential outcome and no guard decision. R7-02 takes the runbook route the reviewer judged non-material, and every other correction aligns wording with behaviour already decided.

Under the convergence rule of DIR-050 §2, therefore, no further focused review is required, and the review series converges here. No correction adds a table, field, role, privilege, persistence mechanism or dependency.

| Finding | Disposition | Where it now stands |
| --- | --- | --- |
| R7-01 | **Level 1, the reviewer's fix (i), not material.**<br>• The Owner-recovery command, and path (e) of AU-20, refuse an inactive account, so a pre-window Owner deactivated during the window is restored before its recovery.<br>• H9-07 and H9-14 check the audit trail for removals during the window before any issue, running a pending restoration first.<br>• The review passage states that AU-12's post-incident link never goes to the account of a pre-window Owner not yet restored and recovered, because any touch defeats its restoration.<br>• GAP-048 states that any touch, a RESET link included, defeats the restoration.<br>• Cases are added to M-46 and H8-21. | SECURITY AU-14, AU-20, AU-21, AU-25, H8-21, H9-07, H9-14; CONCURRENCY_IDEMPOTENCY M-46; GAP-048 |
| R7-02 | **Level 1, the runbook route, not material.** AU-25 and H9-14 state when the operator records the end of a run's recovery: once the review is complete and every pre-window Owner whose removal the trail shows has been restored, or the Owner's incident record outside the application states that person unavailable. Before the end no account is newly made an Owner; one that was is demoted before the end and reinstated after it. H8-21 tests it. The end condition itself is unchanged. | SECURITY AU-25, H8-21, H9-14 |
| R7-03 | **Level 1, fixed before the gate.**<br>• LG-07 and H9-10 add the confirmer as a recipient of the OWNER_RECOVERY_LINK_ISSUED it is named in.<br>• LG-02 places the run, its window start and the confirmer's public id in that event's whitelisted details, so no field is added. | SECURITY LG-02, LG-07, H9-10 |
| R7-04 | **Level 1, fixed before the gate.**<br>• M-46's invariant now states the end condition as AU-14 and RV-18 do: no account that was neither a pre-window Owner nor confirmed by an Owner-recovery issue naming a valid confirmer is recovered as an Owner before the end, or left active and holding the OWNER role at it.<br>• H8-21 names the issue, not the link's use. | CONCURRENCY_IDEMPOTENCY M-46; SECURITY H8-21 |
| R7-05 | **(a) Level 1, fixed:** "untouched since the incident command's closing sweep, the run's own writes excepted", in AU-25 and RV-18.<br>**(b) Level 1, fixed now rather than carried:** a leaked key whose creation is unregistered or ambiguous is the first halt condition, so it ends in the Owner's decision on a restored state (AU-25 and H9-14 Terms).<br>**(c) Carried under DIR-050 §2** as a LOW non-material finding. Phase: P8. Owner: the Planner. The lock and transaction of the end's recording, and its race with a role change or an activation, are carried in GAP-050, which is renamed "Post-incident recovery cases carried to P8 and P9".<br>**(d) Level 1, fixed now rather than carried:** the restoration reads the latest run whose recovery has not ended, in AU-25 and RV-18. | SECURITY AU-25, H9-14; CONCURRENCY_IDEMPOTENCY RV-18; GAP-050 (OPEN) |

### Carried findings of the final reviews

Every LOW non-material finding carried under DIR-050 §2 is listed below with its owner and phase. Each is also recorded in the GAP register.

| Finding | What is carried | Owner | Phase | Register |
| --- | --- | --- | --- | --- |
| R5-11 | The actor rule for the installation step's first-Owner audit event | Planner | P9, with P10 | GAP-049 |
| R6-07 | A window start that turns out to be wrong: a new run of the incident command with the corrected start | Planner | P9 (H9-14), with an M-46 case in P8 | GAP-050 |
| R6-08 (b) | A halt on a trusted state goes to the Owner's incident decision, which may choose a restore | Planner | P9 (H9-14) | GAP-050 |
| R7-05 (c) | The lock and transaction of the end's recording, and its race with a role change or an activation | Planner | P8 (RV-18, M-46) | GAP-050 |

## Gate

This is the gate of DIR-043 §10, as amended by DIR-045 §5 step 6, DIR-046 and DIR-048 §5 step 6, with the additions of DIR-050 §6 step 7. It was run on 2026-10-04 on the final working tree, after round 7's dispositions, with read-only commands and scripts; the scripts stay outside the repository. Each check is listed with its evidence. **Result: PASS, 32 of 32.**

| # | Check | Result | Evidence |
| --- | --- | --- | --- |
| G-01 | Source records: the 40 committed source records byte-identical to `6dbc49a`; records 41–45 matching their recorded hashes; the two new hashes (records 46 and 47) recorded and re-verified (DIR-050 §6 step 7, which supersedes the counts of DIR-043, DIR-045 and DIR-048) | PASS | 47 files under `docs/00-governance/sources/`. All 40 committed at `6dbc49a` have the same blob there (`git hash-object --no-filters` equal to `git rev-parse 6dbc49a:<path>`). Records 41–47 equal their SOURCE_OF_TRUTH SHA-256, line and byte counts: 87B8E1E7… 47/2,072; 820E1214… 354/17,840; FB7D46BE… 196/9,040; B8A49FBC… 16/945; C63F72E1… 317/15,686; 347C9436… 61/3,480 (new); 3723862B… 370/18,017 (new). Each is LF with a `-text` entry in `.gitattributes`. |
| G-02 | The branch diff against `c26f31e` touches only the documents that DIR-043 §§6.2 and 8 name, as DIR-045 §3, DIR-048 §4 and DIR-050 §5 extend them | PASS | 33 tracked files changed and 8 new: the 9 new source records, `.gitattributes`, CHANGELOG, the governance files, V1_SCOPE, ACCEPTANCE_CRITERIA, ARCHITECTURE, the DATABASE parts (s01-03, s04-00, s04-01, s18-19, s20-24, s25-28, s29-31), PERMISSIONS_MATRIX, SECURITY, this record, API_AND_INTEGRATIONS, the CONCURRENCY_IDEMPOTENCY parts (s00-03, s04-06, s10-13, s14-17, s18, s19-20), PERFORMANCE, EXECUTION_CONTEXT, PHASE_STATUS and CURRENT_HANDOFF. Not touched: BUSINESS_RULES, DOMAIN_MODEL, WORKFLOWS, REFERENCE_COVERAGE, ADMIN_FLOW, DESIGN_SYSTEM, INFORMATION_ARCHITECTURE, and every earlier phase's quality-gate evidence. |
| G-03 | Unchanged: the 80 capabilities and their classes (37 / 26 / 17), roles, company-scope rules, AU-07, AU-08, the AU-15 window, AU-02, AU-17 | PASS | 80 catalogue rows: ADM 37, ADM_PLUS 26, OWNER_ONLY 17. Against `c26f31e` only the Covers column of `users.manage` changed. Of the 20 RG and CS rows only RG-11 changed, which DIR-045 §3 and DIR-050 §5 allow. AU-02, AU-07, AU-08, AU-10 and AU-17 are byte-identical to `c26f31e`, and AU-15 keeps its 15-minute window. |
| G-04 | Content counts: 18 MUST capabilities, 14 document types, 13 modules, 125 logical tables with IAM 8; every place stating the table total agrees | PASS | V1_SCOPE has 18 CAP rows, and CAP-08 names 14 document types. The DATABASE module map has 13 modules — 8 + 9 + 5 + 6 + 11 + 8 + 26 + 6 + 7 + 10 + 3 + 19 + 7 = 125 — with IAM 8. Every current statement says 125; the 124s are historical records (OBS-013, TECH-023, the P5 continuation of GAP-006, MIGRATION_MAP, the P4 and P5 gate evidence, the archived state and CHANGELOG history). |
| G-05 | Every unlink path states its reason, its `unlinked_by` and its audit and security evidence the same way in SECURITY, DATABASE and CONCURRENCY_IDEMPOTENCY; no document gives the operator a user row; the OWNER_RECOVERY unlink traces to the operator through the credential link and its issue event | PASS | The six reasons — SELF, OWNER, DEACTIVATION, CREDENTIAL_REISSUE, TOTP_DISABLED and OWNER_RECOVERY — appear in DATABASE §4.1's CHECK, in SECURITY AU-20 and AU-24 and in CONCURRENCY_IDEMPOTENCY. `unlinked_by` is NULL exactly for OWNER_RECOVERY in all three. Every mention of an operator, system or placeholder user forbids one: SECURITY, PERMISSIONS_MATRIX RG-11, DATABASE §4.1 and §18, RV-18 and M-46. AU-14 and AU-20 e name the credential link in the issue, use and unlink events. |
| G-06 | Every new identifier unique; every new control traces to a decision or a design clause; no superseded decision presented as current | PASS | No row family has a duplicate. AU-25, RV-18, M-46, IX-19, GAP-038–GAP-050, DIR-042–DIR-050, RISK-002–RISK-007 and TECH-025 are each defined once; DIR rows appear in both DECISION_INDEX and DECISION_LOG by design. Every new row cites its decision (D5, D6, K1–K4, DIR-044 R-01–R-07, DIR-047, DIR-049, DIR-050 §3) or a DIR-043 §7 clause. D-SEC-06 and D-SEC-14 are marked SUPERSEDED, the superseded wordings are in [Superseded decisions](#superseded-decisions), and adjustment 7 is marked superseded. |
| G-07 | Nothing described as a framework guarantee unless DIR-043 §6.3, as verified, supports it | PASS | The framework statements are those of [External verification](#external-verification) and SECURITY's source evidence. The project's own mechanisms — the second-factor limit as a project-written script, the conditional update of the last time step, the incident command and the restoration command — are stated as project designs, and GAP-043 records that the framework behaviour read only in source is re-verified at implementation. |
| G-08 | Section numbers and README tables of the split documents still valid | PASS | Every heading of every DATABASE and CONCURRENCY_IDEMPOTENCY part equals `c26f31e`, and the README tables are unchanged. |
| G-09 | Repository-wide link check passes; every referenced identifier exists | PASS | 1,733 relative links and anchors in the repository's Markdown files resolve, this section's anchor included. Every identifier referenced in the lines the branch adds is defined. The only unmatched tokens are BR-XC-01, BR-XC-03 and BR-ACC-01, defined as BUSINESS_RULES list items; DIR-024's historical D-2–D-4 labels; and SHA-1, a hash name. |
| G-10 | Phase status only in PHASE_STATUS; EXECUTION_CONTEXT lines cite their owners; DECISION_INDEX holds no rule text | PASS | Phase states — VERIFYING, BLOCKED, DONE — appear only in PHASE_STATUS. CURRENT_HANDOFF states the task's PENDING OWNER APPROVAL and points to PHASE_STATUS. EXECUTION_CONTEXT's authentication lines cite their owning rows — SECURITY AU-03, AU-05, AU-16, AU-18–AU-24, PERMISSIONS_MATRIX AZ-13 — and their decisions (D5, D6, K1, K2, DIR-044). DECISION_INDEX's new rows name decisions and point to DECISION_LOG. |
| G-11 | No secrets, no placeholder text, no client ID or secret value | PASS | No Google client ID or secret, no key material and no placeholder in the diff or the new files. The only TODO hits are PHASE_STATUS's status value, the review reports that quote it, and text outside the amendment. |
| G-12 | The adversarial review recorded, with no open material finding | PASS | Rounds 1 to 7 are recorded, rounds 2 to 7 verbatim with their hashes. Every finding has a disposition. Round 7's one material finding is fixed, and no material finding is open. The LOW non-material findings carried under DIR-050 §2 are listed with owner and phase in [Carried findings of the final reviews](#carried-findings-of-the-final-reviews) and in GAP-049 and GAP-050. |
| G-13 | Each decision of DIR-044 stated the same way in SECURITY, PERMISSIONS_MATRIX, DATABASE, CONCURRENCY_IDEMPOTENCY, ARCHITECTURE, GAP_REGISTER and the P8 and P9 handoffs | PASS | R-01: the sentence is identical in D-SEC-16, H6-07 and GAP-045, and the durability passage in AU-05, D-CC-10 and H6-07. R-02: protocol (a)–(d) is identical in AU-21, RV-17, H8-21, H9-14 and HO-41. R-05: AU-18, AU-12, RV-11, H8-17 and M-45. R-06: AU-19, PJ-17 and H8-18. R-07: AU-03, D-SEC-15, ARCHITECTURE §13, CAP-13, DEP-11, AC-13 and H8-16. |
| G-14 | AU-18 states exactly K1 | PASS | AU-18 states K1's three criteria for an active account in K1's words — the OWNER role whatever its grants, an active ADM_PLUS grant, and an active grant of `finance.view`, `cost.view`, `profit.view` or `evidence.view` — and "TOTP is optional for every other account". |
| G-15 | No fail-closed breached-password statement remains | PASS | "Fail closed" survives only for the second-factor limit (AU-05, D-CC-10, H6-07) and in DIR-044's R-01 text; the breached-password check is skipped and recorded (R-07). |
| G-16 | R-01 stated identically wherever it appears — threshold, immediate effect, attempt window, progression, cap, decay and success rules, flows covered, enrolment excluded | PASS | The 645-character sentence is identical in D-SEC-16, H6-07 and GAP-045. AU-05, TM-63, RV-12, H8-17 and M-44 state the same parameters, and RV-12 now also states R4-04's new window and R4-05's seeding. |
| G-17 | Nothing describes the review, the gate, the fourth commit or the hash list as done before it is (R-08) | PASS | PHASE_STATUS, CURRENT_HANDOFF, CHANGELOG and TECH-025 are worded as of the fourth commit that carries them: "every gate passed", "the fourth carrying this handoff", "documentation gate PASS", and the hash list "computed from the content of the fourth commit". This gate passes, so those statements are true at that commit. Nothing claims approval or publication: every changed document's header says pending Owner approval, and CURRENT_HANDOFF says "Not published". |
| G-18 | The R-01 progression and decay rules recorded as P8 test obligations | PASS | H8-17 and M-44 list the fifth failure, the progression of 1, 2, 4, 8 and 15 minutes with its cap, the decay of the count and of the escalation level, the new window after a breach, seeded decay, the duplicate fifth submission and the progression with the floor merged at every submission. |
| G-19 | (e) as extended stated in the same words everywhere | PASS | The 337-character text is identical in 11 places: SECURITY ×4 (AU-05, TM-61, TM-73, the count paragraph), GAP-045, DECISION_LOG ×4, DECISION_INDEX and this record. The only other wording is DIR-044's historical text. |
| G-20 | Every command that can make an account high-risk — grant, role assignment, activation — applies R-05 the same way | PASS | AU-18 *Enforcement*, AU-12 and CONCURRENCY_IDEMPOTENCY RV-11 state one rule for all three: sessions are ended and, without TOTP, the password made unusable, links revoked and one RESET link issued. H8-17 and M-45 test it. The OWNER-restoration command is stated as an operator mechanism outside it (AU-18, AU-25, RG-11, RV-18). |
| G-21 | The R2-02 approach and its durability stated the same way in every document that carries it, with the restart, eviction and failover tests listed as P8 obligations | PASS | The durability passage is identical in AU-05, D-CC-10 and H6-07. PERFORMANCE IX-19 and the SZ-09 note, WS-12 and HO-24 agree. The restart, eviction and failover tests are in H8-17, M-44 and HO-41. |
| G-22 | The R2-03 protocol stated the same way in AU-21, RV-17, H8-21, H9-14 and HO-41 | PASS | The 463-character protocol is identical in all five. |
| G-23 | No new table or field, database role or privilege | PASS | 125 logical tables with IAM 8. `users` gains exactly the four TOTP fields against `c26f31e`, and the identifier sets of the IAM tables are unchanged against `6dbc49a`. DATABASE §28's four database roles are unchanged against `c26f31e`. The window start, the end of recovery, the submission identifiers and the confirmer sit in existing audit events and whitelisted details. |
| G-24 | Every residual-risk threat row has a disposition, TM-72 included | PASS | TM-06 points to TM-69 and TM-70. The dispositions:<br>• TM-57: K4, accepted (GAP-041, RISK-005); its reinstatement residual is disclosed and not accepted (GAP-048).<br>• TM-61, TM-73: (e), accepted (GAP-045, RISK-006).<br>• TM-68, TM-69, TM-70: K4, accepted (GAP-040, GAP-038, GAP-039).<br>• TM-71: (f), accepted (GAP-046, RISK-007).<br>• TM-72: disclosed, not accepted (GAP-047).<br>The count paragraph lists each. |
| G-25 | DIR-050 §3 stated the same way in AU-14, AU-21, the new AU row, RG-11, the new RV and M rows, H8-21, H9-07 and H9-14 | PASS | Character-identical passages:<br>• the order sentence: AU-14, AU-21, AU-25, H8-21, H9-07 and H9-14;<br>• the guard: AU-14, AU-25 and H9-14;<br>• the terms and the scenarios: AU-25 and H9-14;<br>• the halt conditions: AU-25, H8-21 and H9-14;<br>• the review passage: AU-21, AU-25 and H9-14;<br>• the command's limits: AU-25 and RV-18.<br>RG-11 states §3.5's exception for the command in the same terms. M-46 tests the command and the guard. |
| G-26 | The order of §3.3 unambiguous; no text requires a pre-window Owner to be reviewed by another Owner before its own restoration or recovery | PASS | The order sentence states the order — restoration where needed, then recovery through AU-14, path (e), then the review — and says that no pre-window Owner needs another Owner's review first. AU-14 refuses an inactive account, so a deactivated pre-window Owner is restored before its recovery. The guard admits a pre-window Owner without a confirmer. CHANGELOG reads "then the review it conducts". No text states otherwise. |
| G-27 | No account that first became Owner during the window restored or recovered as an Owner without the review of a recovered pre-window Owner | PASS | The restoration command restores only a pre-window Owner, never promotes. The guard refuses any other OWNER-role account unless the operator names a recovered pre-window Owner who confirmed it in person, is an active Owner now, and is alerted. Such accounts include one assigned or activated inside the window. The end of a run's recovery cannot be recorded while such an account is active without that confirmation. |
| G-28 | No account that first became Owner during the window classified as a pre-window Owner | PASS | A pre-window Owner is defined as an account that held the OWNER role and was active at the window's start. The start is recorded once and any other value refused. An uncertain start is taken as the earliest the evidence allows, and an unregistered key creation is a halt condition. |
| G-29 | No text presents the DIR-009 backup as clean or trustworthy | PASS | Every mention in the amended documents says the DIR-009 same-VPS backup is never presumed clean or trustworthy (AU-25, H9-14, DIR-050's entries, CHANGELOG) or concerns the backup policy itself. |
| G-30 | The R4-04 and R4-05 rules stated the same way wherever R-01's limit is stated | PASS | "A breach starts a new attempt window" is in the R-01 sentence (D-SEC-16, H6-07, GAP-045), AU-05 and RV-12. The seeding rules — at its own time, never twice, a breach identified by its triggering submission, no seeding from a floor read before the latest breach — are in the durability passage (AU-05, D-CC-10, H6-07) and RV-12. LG-02 carries the identifiers. H8-17 and M-44 test them. |
| G-31 | No accepted risk beyond the four of K4, (e) as extended, and (f) (stop condition 7) | PASS | The accepted risks are K4 (TM-57, TM-68–TM-70; GAP-038–GAP-041), with TM-57 and RISK-005 covering the OWNER-restoration command as DIR-050 §5 states; (e) (GAP-045); and (f) (GAP-046). GAP-047 and GAP-048 are disclosed, not accepted, and OPEN for the Owner's decision at approval. The TM-52 residual is labelled "not a new acceptance". |
| G-32 | Zero-context check | PASS | See [Zero-context check](#zero-context-check). |

## Zero-context check

Run on 2026-10-04 by a fresh agent with no prior context, bound by the rule of DIR-046 §2, read-only, on the final working tree. It answered only from the specification and state documents, never from this record. Its questions are those of:

- DIR-043 §10: the password rule, who must have TOTP, a Google identity and access, the Owner's reset of an Admin's TOTP, the actor of the operator recovery's unlink, the table total, and the status with P8;
- DIR-045 §5 step 6: the fifth wrong code, success and evidence, the leak command, a grant making an account high-risk, and the range service down;
- DIR-048 §5 step 6: a Google session and the cooldown, activation, the store's restart, and sign-in during the incident command;
- DIR-050 §6 step 7: every pre-window Owner demoted or deactivated, the order and whether another Owner's review is needed first, an account made Owner in the window, an undatable leak, and write access that cannot be excluded.

It was also asked about the DIR-009 backup, claims of approval or publication, and any requirement that a pre-window Owner be reviewed first. The report below is recorded verbatim, as the agent returned it to the executor. A copy is kept outside the repository at `C:\Users\YUSUFS~1\AppData\Local\Temp\claude\C--Projects-MultipleCorp\2dc7d0a3-0abc-47f2-a842-c97e1ca046a8\scratchpad\P5_AUTH_ZERO_CONTEXT_CHECK_2026-10-04.md` (153 lines, 19,032 bytes, SHA-256 `A0832A3265BBF5C8485B113AEB1B39122CEF6D98E8150321DB57BF1D5897FDB5`).

````markdown
# Zero-context check: P5 authentication amendment

**What I read.** I started at AGENTS.md and followed its pointers through EXECUTION_CONTEXT, CURRENT_HANDOFF and PHASE_STATUS. I then read:
- **SECURITY:** source evidence, §§1–11, and all AU, EN, WS, LG, SX, TM, D-SEC and H6–H9 rows.
- **PERMISSIONS_MATRIX:** header, AZ-13, RG-02, RG-05, RG-09, RG-11, PJ-17, OD-01 and §7.2 row 125.
- **DATABASE:** README, header, §4 module map, §4.1 and C-62–C-65.
- **CONCURRENCY_IDEMPOTENCY:** D-CC-10, RV-11, RV-12, RV-17, RV-18, M-44 and M-46.
- **Governance:** GAP_REGISTER GAP-033–GAP-050; the DECISION_LOG index, the DIR-042–DIR-050 entries and DIR-009/RISK-001; DECISION_INDEX.
- **Other documents:** V1_SCOPE CAP-13 and DEP-10/11; ACCEPTANCE_CRITERIA AC-13; the Google and TOTP lines of ARCHITECTURE; ADMIN_FLOW J-ACC-01; CHANGELOG.
- **Git, read-only:** `rev-parse`, `log`, `status` and `diff --cached`.

**DIR-046 §2 compliance.**
- I opened, listed and searched no transcript, conversation log, history, cache, `.claude`, tool-results, Temp or scratchpad location.
- I created, modified, staged and committed nothing.
- Two repository-wide Grep searches returned match lines from `P5_AUTH_AMENDMENT_GATE.md`, plus file names under `sources/`. I did not open that file and did not use those lines.
- The tool diverted one Bash output to a tool-results file. I did not open it; I re-read those rows from the working tree in smaller pieces.

## Answers

1. **Password rule.**
   - **Profile:** AICWDF-COMPAT-8. 8–128 characters with at least one lowercase letter, one uppercase letter, one digit and one symbol. Any printable Unicode and spaces are allowed, normalized to NFC. Paste and password managers are allowed. There is no periodic expiry; a change is forced only on evidence of compromise.
   - **Refused when:** the password is on the local common-password list (checked first, offline); the k-Anonymity breached-password range check finds it (5 s timeout, never inside a database transaction); or it contains the account's name or e-mail, the product name or a company name.
   - **Hashing:** unchanged, Argon2id (AU-02).
   - *SECURITY AU-03, D-SEC-15; EXECUTION_CONTEXT auth profile; DECISION_INDEX DIR-037 D5 (ACTIVE) and D-SEC-06 (SUPERSEDED-PENDING); V1_SCOPE CAP-13; AC-13.*
   - **Contradiction:** ADMIN_FLOW J-ACC-01 is approved under APPR-008 and the amendment was not allowed to edit it. It still says the person sets a password of "at least 15 characters, no composition rule". GAP-044 covers ADMIN_FLOW's login journeys but does not name this sentence. The ownership rule resolves it in favour of AU-03: SECURITY owns the control, and "nothing derives from the superseded D-SEC-06" (EXECUTION_CONTEXT, GAP-036). The 15-character text in GAP-033's first bullet and the 2026-09-30 CHANGELOG entry is historical.

2. **Who must have TOTP.** Every high-risk account, meaning an active account that meets one of these:
   - it holds the OWNER role (always);
   - it holds an active, effective grant of any ADM_PLUS capability;
   - it holds such a grant of `finance.view`, `cost.view`, `profit.view` or `evidence.view`.

   A grant disabled by a revoked prerequisite does not count, and neither does a capability carried by a future custom role. TOTP is optional for everyone else.
   - *SECURITY AU-16, AU-18, D-SEC-18; DIR-042 K1; EXECUTION_CONTEXT; AC-13.*
   - AGENTS.md rule 16 and DECISION_INDEX's D5 row say "mandatory TOTP" without a qualifier. That is shorthand pointing to the owning text, not a conflict.

3. **Can a Google identity grant access by itself?** No. A Google identity, its e-mail and any Workspace domain are inputs to no authorization rule, and linking one grants no role, capability or company scope. Google sign-in alone also never establishes a session. It works only for an existing linked account with active TOTP that is not in recovery, the account is found by `sub` only, and the TOTP challenge always follows.
   - *PERMISSIONS_MATRIX AZ-13; SECURITY AU-22, AU-23; ARCHITECTURE §8; CAP-13; AC-13; K2.*

4. **Owner resets an Admin's TOTP (path c).**
   - **Preconditions:** `users.manage`, step-up and a mandatory reason; never on the acting Owner's own account.
   - **In the command:** the four TOTP fields are cleared and pending challenges are voided. Every session ends and unused links are revoked. The password is made unusable and one RESET link is shown once for out-of-band delivery. A linked Google identity is unlinked with reason CREDENTIAL_REISSUE and the acting Owner as `unlinked_by`.
   - **Events:** the audit event is written in the transaction, then TOTP_RESET after the commit. The reset is alerted, and the account is told at its next login.
   - **Afterwards:** the person sets a password and logs in, and must enrol if high-risk.
   - *SECURITY AU-20 c and its common effects, AU-12, AU-15, AU-24, LG-02, LG-07; PERMISSIONS_MATRIX OD-01; CONCURRENCY RV-15; DATABASE C-63.*

5. **OWNER_RECOVERY unlink.** `unlinked_by` is NULL. This is the only reason for which it may be NULL (CHECK C-63), and no operator user row exists.
   - **Tracing:** the audit events of the link's use and of the unlink name the credential link by its store identifier. That link's issue records the operator's identity — the host account and the stated name, with `actor_user_id` NULL — in its business audit event and in OWNER_RECOVERY_LINK_ISSUED.
   - *SECURITY AU-14, AU-20 e, AU-24, LG-01, H8-19; PERMISSIONS_MATRIX RG-11; DATABASE §4.1 `user_external_identities`, C-63.*

6. **Table counts.** There are 125 logical tables, 8 of them in IAM: `users`, `roles`, `capabilities`, `role_capabilities`, `user_roles`, `user_capability_grants`, `user_company_grants` and `user_external_identities`. Session, credential-token and limiter state are excluded from the count.
   - *DATABASE §4 module map, §4.1; PERMISSIONS_MATRIX §7.2 row 125; CURRENT_HANDOFF Database row; PHASE_STATUS P4 row; SECURITY AU-11.*
   - "124" appears only in historical notes, such as the TECH-022 note in the DATABASE header, the P4 and P5 gates and MIGRATION_MAP.

7. **Status of the amendment and of P8.**
   - **Amendment:** VERIFYING / PENDING OWNER APPROVAL. Its wording is REVIEW, and it is "neither approved nor published"; `main` is unchanged. The Owner decisions it applies are binding within their subjects. The amended documents keep APPROVED only for their last approved revision.
   - **P8:** BLOCKED. It waits for the Owner's approval of the amendment and for P7 (GAP-035, GAP-037, GAP-044).
   - *PHASE_STATUS P5 and P8 rows and the milestone row; CURRENT_HANDOFF task status and blockers; EXECUTION_CONTEXT; DECISION_LOG TECH-025; GAP-036.*
   - **Difference from the working tree:** CURRENT_HANDOFF's "Last completed action" describes the fourth commit as done. In fact HEAD is `6dbc49a` with three amendment commits, 35 working-tree entries are uncommitted, nothing is staged, and DECISION_LOG still holds `<!-- TECH-025 HASH TABLE -->`.
   - GAP-036's original description still says PHASE_STATUS "shows it READY"; its continuations supersede that.

8. **The fifth wrong second-factor code.**
   - **Counting:** the count is per account, across all IPs and every code-accepting step, within a rolling 15-minute attempt window. Enrolment confirmation is excluded.
   - **Breach:** the fifth failure immediately voids the challenge or pending step. It starts an account-wide cooldown of 1, 2, 4 or 8 minutes, then 15 minutes (the cap), by the number of breaches in the last 24 hours, and it starts a new attempt window.
   - **During the cooldown:** every covered step is refused without evaluating the code — a recovery code is neither checked nor consumed — with the throttled-login answer.
   - **Events:** TWO_FACTOR_COOLDOWN_STARTED carries the level, duration, path and submission identifier and alerts the Owner and the operator. Refusals are recorded as aggregated TWO_FACTOR_COOLDOWN_REFUSED. The cooldown is never a permanent lockout.
   - *SECURITY AU-05, D-SEC-16, LG-02, LG-07, TM-63; CONCURRENCY RV-12, M-44; DIR-044 R-01.*

9. **Does a successful sign-in erase earlier failures?** No. A success resets nothing: not the count, the escalation level, the aggregated counts or any event. Security events are never deleted or rewritten; the accepted code only gives back its own place in the count.
   - *SECURITY AU-05 "Success", D-SEC-16, H8-17; CONCURRENCY RV-12, M-44.*

10. **The leak (key-compromise incident) command.** It is run when the key is believed leaked together with the database, as a full incident response for every account.
    - **Voided:** the four TOTP fields are cleared and never re-encrypted. Every password is made unusable, every unused link revoked and every session deleted.
    - **Keys:** the LG-03 and AU-11 HMAC keys are rotated. A new `APP_KEY` and HMAC keys come first, and the other SX-01 secrets are rotated. The leaked key is retired, and not listed unless ciphertext must survive.
    - **Protocol:** (a) an opening sweep; (b) maintenance mode with no bypass; (c) every account processed, including ones created during the run; (d) a closing sweep.
    - **Window start:** it takes the compromise-window start as input and records it in every audit event of the run.
    - **Google links:** kept, but not eligible until TOTP is active again.
    - **Events:** per account, an audit event and CREDENTIALS_VOIDED carrying the operator's identity, with `actor_user_id` NULL; the Owner and the operator are alerted.
    - **Who runs it:** the operator only, under the runtime role, one account per TX-07 transaction. Recovery then follows AU-25.
    - *SECURITY AU-21, SX-02, SX-04, LG-07, H9-14; CONCURRENCY RV-17; DIR-044 R-02; DIR-048 R2-03.*

11. **A grant makes an account without TOTP high-risk.** If the grant (a prerequisite's grant included) leaves an active account high-risk:
    - the same transaction ends all its sessions;
    - without confirmed TOTP it also makes the password unusable, revokes unused links and issues one RESET link, shown once to the acting Owner (PASSWORD_RESET_REQUESTED, reason HIGH_RISK_GRANT);
    - the person then gets only an enrolment-only session until a device is confirmed, and the first enrolment alerts the Owner;
    - an account with confirmed TOTP keeps its password and meets the challenge at its next login;
    - on an inactive account nothing happens until activation.
    - *SECURITY AU-18, AU-12, LG-02, D-SEC-18; PERMISSIONS_MATRIX OD-01; CONCURRENCY RV-11, M-45; DIR-044 R-05.*

12. **Range service down.** Yes, a password can be set. On a timeout, transport error or unsuccessful answer the remote check is skipped. The password is accepted only if it passes the local list, the containment rule and every other rule. PASSWORD_CHECK_SKIPPED is recorded with the failure as its reason, and repeated skips alert. This is the accepted residual (f).
    - *SECURITY AU-03, D-SEC-15, TM-71, LG-07, H8-16; GAP-046 ACCEPTED_RISK (RISK-007); DIR-044 R-07; CAP-13; AC-13; DEP-11.*

13. **Google session only.** Yes, and it is accepted. A browser still signed in to the person's linked Google account reaches the post-Google TOTP challenge and can fail codes on purpose to start the account-wide cooldown. This is residual (e) as extended by DIR-047. Each breach alert names the GOOGLE path, so the Owner can unlink the identity.
    - *SECURITY AU-05, TM-61, TM-73, §8 count paragraph; GAP-045 ACCEPTED_RISK; RISK-006; DIR-047.*

14. **Activation of a high-risk account without TOTP.** Activation runs the high-risk test; role or grant changes made while the account was inactive take effect here.
    - If it becomes high-risk without confirmed TOTP, the same transaction ends its sessions, makes the password unusable and revokes unused links. For an account created with high-risk roles or grants, that includes its unused ONBOARDING link.
    - It issues one RESET link, shown once to the acting Owner.
    - The person sets a password, gets an enrolment-only session and must enrol; the first enrolment is alerted.
    - *SECURITY AU-18, AU-12, D-SEC-18; PERMISSIONS_MATRIX OD-01; CONCURRENCY RV-11; DIR-048 R2-01; H8-17.*

15. **Does the limit survive a store restart?** Yes.
    - **Mechanism:** a project-written atomic script merges the store with a durable floor read from the TWO_FACTOR_FAILED and TWO_FACTOR_COOLDOWN_STARTED events (PERFORMANCE IX-19). A restart, eviction or failover therefore never shortens a cooldown, lowers the escalation level or reduces the count.
    - **Store keys:** they carry no expiry and are excluded from eviction, and no failover store serves them.
    - **Fail-closed:** a step is refused if the floor cannot be read, the event cannot be written, or the store is down.
    - *SECURITY AU-05 "Durability", WS-12, H8-17; CONCURRENCY D-CC-10, RV-12, M-44, HO-24; DIR-048 R2-02.*

16. **Sign-in during the incident command.** It is refused. Maintenance mode with no bypass lasts the whole run, so sign-in, credential-link issue and use, and every NX-02 command are refused. No web request is served, and the command refuses to start or continue outside maintenance mode.
    - *SECURITY AU-21 (b), H8-21, H9-14; CONCURRENCY RV-17.*

17. **Every pre-window Owner demoted or deactivated during the window.** They are still pre-window Owners, because they held OWNER and were active at the window start. In Scenario A:
    - after the incident command completes, the operator verifies each person in person and runs the OWNER-restoration command;
    - the command restores only the removed attribute, to its window-start value, and refuses an account touched since the closing sweep, so the runbook leaves those accounts untouched;
    - each is then recovered through AU-14 path (e) — AU-14 refuses an inactive account, which is why restoration comes first — and then they conduct the review;
    - the runbook shows them the actor and time of each removal that was reversed.

    A legitimate removal is reversed too. GAP-048 discloses this without accepting it, for the Owner's decision. The path halts to Scenario B only if no pre-window Owner can be established with certainty, or none is available.
    - *SECURITY AU-25, AU-14, TM-57; CONCURRENCY RV-18, M-46; GAP-048; DIR-049; DIR-050 §3.*

18. **Order, and is another Owner's review needed?**
    - **Order:** (1) the incident command completes; (2) restoration where a pre-window Owner's OWNER role or active flag was removed; (3) every pre-window Owner is recovered via AU-14 path (e), sets a password and must enrol; (4) the recovered pre-window Owners conduct the review.
    - **Review:** no pre-window Owner needs another Owner's review before its own restoration or recovery. Its link is issued without a named confirmer.
    - *SECURITY AU-25, AU-14, AU-21, H8-21, H9-07, H9-14; CONCURRENCY RV-18; DECISION_LOG DIR-050.*

19. **An account that first became Owner during the window.** It cannot be restored or recovered as an Owner without a recovered pre-window Owner's review. That holds whether or not another Owner is active.
    - The restoration command never promotes it.
    - Until the run's recovery ends, the Owner-recovery command refuses it unless the operator names a recovered pre-window Owner who confirmed it in person. Under row locks, the command checks that the confirmer held OWNER and was active at the window start, used its OWNER_RECOVERY link after the window, and is an active Owner now. The confirmer is named in the events and alerted.
    - The run's recovery cannot end while such an account is unconfirmed.
    - *SECURITY AU-14, AU-21, AU-25, H8-21; CONCURRENCY RV-18, M-46; DIR-049.*

20. **The leak cannot be dated.** The window starts at the leaked key's creation time in the dated key register, or the earliest such time if several keys leaked.
    - If that creation is unregistered or ambiguous, no pre-window Owner can be established with certainty. This is the first halt condition: the in-application path stops and Scenario B applies. On a restored state it ends in the Owner's incident decision outside the application.
    - P9 weighs periodic key rotation to shorten such windows.
    - GAP-050 notes that a conservative start may reach back before every OWNER assignment, that a corrected start has no defined path, and that it is unclear whether a halt on a trusted state means a restore is mandatory. All three are open for P9.
    - *SECURITY AU-25 terms, halt and obligations; H9-14; GAP-048; GAP-050.*

21. **Attacker write access to the database cannot be excluded.** Scenario B applies.
    - No step of Scenario A runs on the untrusted state, and the host is taken out of service.
    - Recovery uses only a backup or snapshot independently verified to predate the compromise and to be trustworthy; the DIR-009 backup is never presumed clean. If none exists, the Owner takes an incident decision outside the application.
    - A restored state is brought up only once write access to it is excluded. Before it serves any request it gets new keys (the leaked key never listed), the SX-01 secrets rotated again, and the incident command.
    - Steps 2–4 of Scenario A then run on the restored audit trail, with pre-window status read at the window start, or at the backup's time if the backup is older. Either halt condition leads to the Owner's decision.
    - *SECURITY AU-25, AU-21, H8-21, H9-14; DIR-049; GAP-048.*

## Additional checks

**(a) Is the DIR-009 backup presented as clean or trustworthy?** No document does. Every mention says it is "never presumed clean or trustworthy": SECURITY AU-25 and H9-14, the DECISION_LOG DIR-049 row and entry, TECH-025, DECISION_INDEX and CHANGELOG. AU-21's "a database backup holds ciphertext only" concerns encryption, not trustworthiness.

**(b) Does any document claim the amendment is approved or published?** No. Every state document says PENDING OWNER APPROVAL, VERIFYING or REVIEW, "neither approved nor published".
- The only nearby approval is DIR-042's approval of the P5 authentication blueprint. Both the DECISION_LOG and DECISION_INDEX say this covers "the design, not the amended documents".
- The "Status: APPROVED" lines at the top of SECURITY, PERMISSIONS_MATRIX and DATABASE refer to their last approved revision; the "Amendment — REVIEW" note below each one says so.

**(c) Must a pre-window Owner be reviewed by another Owner first?** No document requires it. SECURITY AU-14, AU-21, AU-25, H8-21, H9-07 and H9-14 state the opposite. GAP-048 says requiring it would change DIR-050 and needs an Owner decision.

**Verdict:** All 21 questions can be answered from the specifications. Twenty are unambiguous. Q1 has one literal contradiction: ADMIN_FLOW J-ACC-01's "at least 15 characters, no composition rule" against SECURITY AU-03. The ownership rule resolves it in favour of AU-03, but no gap names it. Q7 is unambiguous on status, but CURRENT_HANDOFF records the fourth commit as done while the working tree shows it uncommitted.
````

**Result: PASS.** Every question was answered from the specifications, with citations, and every answer agrees with the decisions:

- AICWDF-COMPAT-8; TOTP for every high-risk account under K1; no access from a Google identity.
- Path (c)'s reset with the RESET link and the CREDENTIAL_REISSUE unlink.
- `unlinked_by` NULL for OWNER_RECOVERY, traced through the credential link.
- 125 tables with IAM 8; PENDING OWNER APPROVAL with P8 BLOCKED.
- R-01's breach; success resetting nothing; the incident protocol; R-05 at a grant and at activation; (f) when the range service is down; (e) as extended for a Google session; the durable floor; sign-in refused during the run.
- DIR-050 §3's restoration, order, guard, undatable window and Scenario B.

None of the three additional checks found a problem.

The agent raised two observations:

1. **ADMIN_FLOW J-ACC-01** still shows the superseded password rule ("at least 15 characters, no composition rule"). The amendment may not edit ADMIN_FLOW (DIR-043 §3), so this is resolved at Level 1 by a continuation of GAP-044. That continuation names the sentence, states that SECURITY AU-03 prevails within its subject until P7 aligns it, and assigns its replacement to the P7 re-baseline (H7-19).
2. **CURRENT_HANDOFF** describes the fourth commit as done while the working tree was still uncommitted. This is by design: the state records are worded as of the fourth commit that carries them (R-08; [gate](#gate), G-17). The hash-table marker the agent saw in DECISION_LOG is replaced by the table before that commit.

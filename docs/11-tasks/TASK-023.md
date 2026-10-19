# TASK-023 — My account — password, two-step verification, Google link and language

Status: REVIEW | Updated: 2026-10-19 | Owner: Planning

Written by WF-PC5-W's writer of bundle W1 of run PC-5 on 2026-10-19 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99 — a REVIEW draft of P11, approved, or not, with the [Task plan](TASK_PLAN.md) at OG-4. **No Task starts before the Owner's execution authorization at OG-4.** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** TODO

**RISK CLASS:** HIGH

**EXECUTOR:** AGENT

**OBJECTIVE:**

Build PG-065 *Akun Saya* — the account's own profile, role, effective capabilities, companies, TOTP status, linked Google e-mail, last login and credential and factor events, read-only — with its self-service steps: the password change, TOTP enrolment and device replacement, viewing and regenerating the recovery codes, disabling TOTP for an account that is not high-risk, linking and unlinking its own Google identity, and the display language (ROUTE-065, ROUTE-111–ROUTE-118).

**WHY THIS EXISTS:**

SECURITY AU-13 gives every authenticated account self-service of its own credentials, factor and language, nothing else being self-editable; INFORMATION_ARCHITECTURE §14.2 puts every one of those functions on one page, PG-065 (SCR-03), each sensitive step behind the step-up (AU-15), and `DIR-077` CA-08 adds the display language kept in `users.locale` (RB-10). The sign-in Tasks TASK-017–TASK-019 build the mechanisms; this Task gives the account its own page over them.

**DEPENDS ON:**

- TASK-014 — Localization — locales, the language switch and the key catalogue
- TASK-016 — Application shell, navigation and the company context
- TASK-019 — Google sign-in for existing accounts

**BLOCKS:**

- TASK-062 — Critical journeys A — the golden flow, purchasing, fulfilment, counts, scanning and SIPLAH
- TASK-063 — Critical journeys B — finance, corrections, reports, documents, completion and the checklist
- TASK-064 — Critical journeys C — isolation, sign-in, access, search, approvals, import, language, masters, command identity and status
- TASK-065 — UI/UX consistency gate, the responsive matrix and the anti-slop audit
- TASK-066 — Accessibility — WCAG 2.2 AA across every page
- TASK-067 — Localization completeness and the per-page copy lists
- TASK-068 — Performance targets and query budgets in CI
- TASK-069 — Security test matrix, route integrity and zero dead interactions

**PARALLEL SAFE:** NO — its identity steps run on the account row (LK-03) and the TOTP and Google state that the sign-in pipeline of TASK-017–TASK-019 reads

**IN SCOPE:**

- PG-065 with NAV-065 and its view ROUTE-065 (SELF; PJ-17; SCR-03): one page in sections — the profile, role, effective capabilities and companies, read-only; sign-in and security — the password, TOTP, the recovery codes and the Google link; its last successful login and its own credential and factor events (AU-13; INFORMATION_ARCHITECTURE §14.2)
- ROUTE-111 with INT-111: the own password change — the current password, AU-03's profile, the account's other sessions ended, the password-change rate class of WS-12 (AU-13; RV-10)
- ROUTE-112–ROUTE-115 with INT-112–INT-115, each behind the step-up (AU-15; IP-10): TOTP enrolment and device replacement (AU-16; AU-20 a), viewing and regenerating the recovery codes with a current code (AU-19), disabling TOTP only while the account is not high-risk (AU-18; AU-20 f); the QR code, the text key and the codes shown once as non-persisted flash data (AU-12's rule)
- ROUTE-116 and ROUTE-117 with INT-116 and INT-117: the LINK attempt through TASK-019's redirect and callback, then the holder's confirming request with password confirmation and a current code (AU-22, AU-23; BD-19), and the account's own unlink (AU-24, SELF)
- ROUTE-118 with INT-118 and INT-002: *Bahasa tampilan* in the account menu and on PG-065 — `id`, `en` or the default, kept in `users.locale` and applied to the session at once (RB-10; `DIR-077` CA-08; LOCALIZATION §2)
- the account's own credential and factor events with the first-login notice MSG-29 (AU-13; H7-14; H7-18; TM-52), an Owner's reset, reissue or unlink of this account (H7-17) among them; H7-16's labels, advice on shared devices and the linked e-mail and date shown read-only; UXI-22

**OUT OF SCOPE:**

- sign-in, sessions, credential links and the step-up mechanism (TASK-017); the challenge, the enrolment-only session and PG-004 (TASK-018); Google's redirect and callback (TASK-019)
- the Owner's account screens on PG-060 — another account's TOTP reset, Google unlink and sessions (TASK-024)
- the locales, the key catalogue and the switch on the sign-in pages (TASK-014); MSG-29's Owner variant on *Beranda* (TASK-058)

**REFERENCES:**

- [SECURITY §2](../05-security/SECURITY.md#2-authentication-sessions-and-credentials) AU-13, AU-15, AU-16, AU-18–AU-20, AU-22–AU-24
- [SECURITY §6](../05-security/SECURITY.md#6-audit-and-security-logging) LG-01–LG-03, LG-07; [§8](../05-security/SECURITY.md#8-threat-model) TM-52, TM-59, TM-60, TM-62, TM-63, TM-66, TM-67, TM-72, TM-73; [§9](../05-security/SECURITY.md#9-design-decisions) D-SEC-16, D-SEC-17, D-SEC-20, D-SEC-21
- [SECURITY §10 — P7](../05-security/SECURITY.md#p7--presentation-only) H7-14, H7-16–H7-18; [— P8](../05-security/SECURITY.md#p8--required-denial-and-security-tests-p8-creates-its-own-files) H8-18–H8-20
- [PERMISSIONS_MATRIX §7.3](../05-security/PERMISSIONS_MATRIX.md#73-special-projections) PJ-17; [§9](../05-security/PERMISSIONS_MATRIX.md#9-owner-only-denials) OD-01
- [ROUTE_CONTRACTS](../03-workflows/ROUTE_CONTRACTS.md#the-account-itself--akun-saya-self-under-security-au-13-permissions_matrix-pj-17) ROUTE-111–ROUTE-118; [§2](../03-workflows/ROUTE_CONTRACTS.md#2-page-views--route-001route-065) ROUTE-065
- [INTERACTION_CONTRACTS](../03-workflows/INTERACTION_CONTRACTS.md#the-account-itself--akun-saya-self-under-security-au-13-permissions_matrix-pj-17) INT-111–INT-118; [§2](../03-workflows/INTERACTION_CONTRACTS.md#2-shared-interactions--int-001int-020) INT-002, INT-011
- [NAVIGATION_CONTRACTS §2.1](../07-ux-design/NAVIGATION_CONTRACTS.md#21-system-account-and-beranda) NAV-065
- [INFORMATION_ARCHITECTURE §8.1](../07-ux-design/INFORMATION_ARCHITECTURE.md#81-system-account-and-beranda) PG-065; [§14.2](../07-ux-design/INFORMATION_ARCHITECTURE.md#142-scr-03--akun-saya)
- [API_AND_INTEGRATIONS §1](../06-api-performance/API_AND_INTEGRATIONS.md#1-route-boundaries) RB-03, RB-10; [LOCALIZATION §2](../07-ux-design/LOCALIZATION.md#2-locales-the-choice-and-where-it-is-kept)
- [CONCURRENCY_IDEMPOTENCY §10](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s10-13-identity-failure-retry.md#10-command-identity-and-replay) CI-09; [§11](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s10-13-identity-failure-retry.md#11-separate-duplicates) BD-19; [§16](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s14-17-numbering-jobs-revocation.md#16-authorization-and-revocation-races) RV-10, RV-12, RV-14, RV-16; [§17](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s14-17-numbering-jobs-revocation.md#17-p5-obligations-h6-01h6-12) H6-13–H6-15; [§18](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s18-scenarios.md#18-mandatory-concurrency-and-failure-scenarios) M-39, M-41, M-42; [§19](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s19-20-handoff-traceability.md#19-handoff-obligations) HO-41
- [ADMIN_FLOW J-ACC-01](../07-ux-design/ADMIN_FLOW/s10-a-journeys.md#101-access-and-master-data); [§14](../07-ux-design/ADMIN_FLOW/s14-15-messages-scenarios.md#14-message-catalogue) MSG-27, MSG-29; [§3](../07-ux-design/ADMIN_FLOW/s01-04-foundations.md#3-derived-interaction-requirements) UXI-22
- [SECURITY_TEST_MATRIX](../08-testing/SECURITY_TEST_MATRIX.md#3-the-matrix) SM-02, SM-18–SM-20
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (59): ROUTE-065; ROUTE-111–ROUTE-118; INT-002; INT-111–INT-118; AU-13; AU-19; AU-23–AU-24; TM-52; TM-59–TM-60; TM-62–TM-63; TM-66–TM-67; TM-72–TM-73; D-SEC-16–D-SEC-17; D-SEC-20–D-SEC-21; H6-13–H6-15; H7-14; H7-16–H7-18; H8-18–H8-20; NX-02; BD-19; HO-41; RB-03; RB-10; PG-065; SCR-03; UXI-22; J-ACC-01; MSG-29; NAV-065; SM-18–SM-20

**EXPECTED MODULES / FILE AREAS:**

- the IAM module's own-account actions and their authentication boundary (ARCHITECTURE §4, §8); `resources/js/Pages/IAM` for PG-065; the account menu's language entry in the shell of TASK-016; `tests/Feature/IAM`

**BUSINESS RULES:**

- BR-XC-02: the account acts only as itself; nothing about another account is self-editable (AU-13; AZ-12)

**ARCHITECTURE RULES:**

- Fortify and Socialite stay behind IAM's authentication boundary; Fortify's own two-factor routes stay unregistered, the project's routes replacing them (AU-16; D-SEC-17); the breached-password check and Google's token exchange run before any transaction (H6-15)
- the TOTP, storage and Google decisions D-SEC-16, D-SEC-20 and D-SEC-21 as TASK-018 and TASK-019 build them — the four TOTP fields set and cleared together, `user_external_identities` rows never deleted, no Google token stored

**SECURITY / PERMISSION REQUIREMENTS:**

- every route declares SELF (RC-03; `DIR-081` OB-03) and reaches the account's own row only (SM-02); the step-up of AU-15 on ROUTE-112–ROUTE-117 with a current code where AU-15 asks for one; the auth rate class, fail closed, on the code-taking steps and the second-factor limit of AU-05; request-forgery protection on ROUTE-118 (WS-04)
- TOTP disable refused for a high-risk account (AU-18; AU-20 f); the codes, the secret and the QR never as page props kept in history and never in browser storage (AU-12; AU-19; H7-12)

**COMPANY SCOPE AND FIELD PROJECTION:**

- SELF only (PJ-17; RC-03): `users` row 1, `user_roles`, `user_capability_grants`, `user_company_grants` (names of own granted companies) and `user_external_identities` (the e-mail at link time and the link date) of PERMISSIONS_MATRIX §7.2 as the account's own; `two_factor_confirmed_at` as its TOTP status; the password, secret, recovery codes, `two_factor_last_step` and `provider_subject` never projected; the effective capabilities and scope shown read-only to shape the page, the server still deciding (AZ-03); no company scope applies — the page is global (IA §8.1)

**CONCURRENCY / IDEMPOTENCY:**

- outside the command envelope (CI-09; RB-03): the password change, the code-taking steps, the enrolment confirmation, the disable, the link and the own unlink are each one TX-07 identity transaction under the account's row at LK-03 (RV-10, RV-12, RV-14, RV-16), not NX-02 commands; the language change carries no `command_id` (RB-10)
- a code accepted once only (H6-13; C-65; M-39); one active Google link per subject and per account, the same pair a no-op (BD-19; C-64; M-41); a link racing a deactivation (M-42); the own unlink against the account's own requests (HO-41)

**AUDIT:**

- each change writes its audit event in its transaction, the account itself the actor with authority class SYSTEM (LG-01; RG-11; CI-09), and its security event after the commit — PASSWORD_CHANGED, TOTP_ENROLLED, TOTP_REPLACED, TOTP_DISABLED, RECOVERY_CODES_VIEWED, RECOVERY_CODES_REGENERATED, GOOGLE_LINKED, GOOGLE_UNLINKED (LG-02); the Owner alerts of LG-07 for a high-risk account; no password, code, secret, token or provider subject in any record (LG-03; BR-XC-03)

**DATABASE IMPACT ASSESSMENT:**

- Schema change: NONE — uses the tables of TASK-005–TASK-009; a missing column is an executor discovery (AGENT_OPERATING_MODEL)
- Migration required: NO
- Existing data transformation: NONE
- Direct production DB access: FORBIDDEN
- Data-loss risk: NONE KNOWN

**MODULARITY / EXTENSIBILITY:**

- Module boundary affected: IAM, tier 0, and its authentication boundary (ARCHITECTURE §3, §8)
- Stable core kept provider-neutral: YES
- External/vendor-specific code isolated: YES — Fortify and Socialite only behind the authentication boundary (ARCHITECTURE §8)
- Replaceable adapter/contract required: NO
- Provider lock-in risk: NONE beyond the Owner's baseline stack; Google stays an optional path, every account keeping a password (AU-22)
- Future extension impact: LOW
- Speculative abstraction avoided: YES — no repository class per model, no generic workflow or rules engine (ARCHITECTURE §1)

**AUTH IMPACT:**

- Authentication change: PROPOSED — builds the self-service controls of SECURITY AU-13 over the mechanisms of TASK-017–TASK-019, nothing beyond them
- Authentication implementation: LARAVEL_NATIVE — Fortify and Socialite (D-SEC-17; [EXECUTION_CONTEXT](EXECUTION_CONTEXT.md)), reused, never redesigned
- Auth profile: AICWDF-COMPAT-8 with the common and breached-password blocklist for the own password change (AU-03; D-SEC-15)
- Google sign-in: ON where a client is configured (TASK-019; GLC-09) — this Task adds only linking and the own unlink
- Google registration behavior: EXISTING_ACCOUNT_ONLY — a link is made by the account holder, never by e-mail or registration (AU-22; AU-23)
- Google credentials owner action required: NO — the clients are TASK-082's; tests use the faked provider
- Authorization change: NONE — SELF routes only (RC-03; AZ-13: how an account authenticates never authorizes)
- Provider/library-specific leakage into domain: NO — the provider subclass and the Fortify hooks stay inside the authentication boundary (ARCHITECTURE §8)

**COST IMPACT:**

- New recurring cost: NONE
- Paid third-party service: NO
- Free/open-source alternative: N/A — nothing paid is considered
- Estimated additional monthly production cost: 0
- Owner approval required: NO

**MCP SESSION PROFILE:**

- Required MCP servers: none — [TOOLCHAIN](../00-governance/TOOLCHAIN.md#mcp-servers) records none installed
- MCP servers that should remain OFF: every server not required
- Native CLI/package preferred over MCP: YES — the framework's and package managers' own commands
- Post-Task deactivation/cleanup: none

**TOOL REQUIREMENTS:**

- Context7: REQUIRED — Fortify's password update, password confirmation and two-factor features, and Socialite's Google provider, at the installed versions
- Graphify: OPTIONAL — once the IAM module of TASK-017–TASK-019 exists
- Browser E2E: REQUIRED — PG-065's steps of J-ACC-01, CJ-10 and CJ-18
- Other: the Google provider faked in local and CI runs — no test calls Google (SM-20); a controlled clock for the codes and the 15-minute step-up window
- Missing safe tool behavior: AUTO-INSTALL project-locally inside this Task (AICWDF §6.3) and record it in TOOLCHAIN; a paid tier or a privileged installation STOPS for the Owner (COST_POLICY)

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: refreshed before the change when Graphify is available ([TOOLCHAIN](../00-governance/TOOLCHAIN.md)); otherwise a targeted search of the IAM module
- Pre-change questions: which identity steps of TASK-017–TASK-019 these routes reuse — the code check, the step-up and the session restamp — and which session-guard digests a change on PG-065 moves (AU-06)
- Expected affected areas: the IAM module and its authentication boundary; the shell's account menu

**CONTEXT7:**

- Topics to verify: Fortify's `UpdatesUserPasswords`, password confirmation with `password_timeout`, the routes its two-factor feature registers; Socialite's Google driver and its scopes; the framework's session regeneration
- Version-sensitive assumptions: each Fortify and Socialite behaviour SECURITY reads in source re-verified at the installed version (GAP-043; SM-22)

**UI / UX REQUIREMENTS:**

- Follow global design rules: YES
- Indonesian default: YES
- English support: YES — and the page holds *Bahasa tampilan* (INT-002)
- Plain-language Indonesian: YES — the MSG- texts (MSG-19, MSG-20, MSG-27, MSG-29) and LOCALIZATION's keys as written; a new key listed for the Owner's per-page review (`DIR-081` OB-04)
- Language switch impact: the chosen language applies at once to every page and at the next sign-in; the page keeps its unsent input (LOCALIZATION §3.7)
- Mobile-first: YES — `[both]` (NAV-065)
- Responsive: YES
- Adaptive: N/A — one sectioned page at every band
- Visual breathing room: REQUIRED
- Comfortable density: REQUIRED
- shadcn/ui reuse: the design system's forms (PT-09), the step-up dialog (PT-28), dialogs (PT-16), inline messages (PT-14) and the activity timeline for the own events (PT-18)
- Design reference: PG-065 as INFORMATION_ARCHITECTURE §8.1 and §14.2 state it, its pages and copy designed in [AUTH_JOURNEYS](../07-ux-design/stage2/AUTH_JOURNEYS.md); the Golden Frontend Reference for presentation only (DIR-061 SD-02)
- Primary action: each section's own submit — one per region (PT-11)
- Secondary actions: as NAV-065 and the section's INT- contract name — view or regenerate the codes, replace the device, unlink
- Destructive actions: TOTP disable and the own Google unlink, each behind the step-up with its consequence stated
- Redundancy constraints: one control per action; the language entry in the account menu and on the page sends the same ROUTE-118 (INT-118 points to INT-002)
- Loading state: the submit's pending state, no second submission (PT-19)
- Empty state: no linked Google identity, no TOTP and no events each say so in one sentence with at most one action (PT-19)
- Error state: the generic code answers and the cooldown in place (MSG-19, MSG-20; AU-05), AU-23's generic link failures, the refusal of a disable for a high-risk account in words

**ROUTE IMPACT:**

- ROUTE-065 (PG-065's view); ROUTE-111 (password), ROUTE-112 (enrol or replace), ROUTE-113 (view codes), ROUTE-114 (regenerate codes), ROUTE-115 (disable), ROUTE-116 (link), ROUTE-117 (own unlink), ROUTE-118 (display language)

**INTERACTION CONTRACTS:**

- INT-111–INT-117; INT-118 as INT-002; INT-011 for the step-up (with TASK-017)

**NAVIGATION CONTRACT:**

- Parent: none — the account menu; *Lainnya* › *Akun* (NAV-065)
- Entry: the account menu; *Lainnya* › *Akun*
- Back action: "Kembali" → the page the account menu was opened from (NAV-065)
- Cancel: "Batal"; the step-up's "Kembali" → the form, the input kept (NV-02)
- Success destination: PG-065 with the change in place; the display language changes at once (`DIR-077` CA-08)
- Error behavior: NV-04; a failed step-up answers in place (IP-10)
- State preservation: unsent input as a form's (NV-05) — never a password, code or secret in history (H7-12)
- Mobile behavior: `[both]` — the same sections stacked, each sensitive step's dialog full screen (NV-06)

**ACCEPTANCE CRITERIA:**

1. PG-065 shows the account's own profile, role, effective capabilities, companies, TOTP status, linked Google e-mail and date and its own events, read-only, and no route of this Task reaches another account's row (PJ-17; SM-02).
2. A password change needs the current password and AU-03's profile, ends every other session of the account, keeps the acting one, and is limited to 5 per 15 minutes (ROUTE-111; AU-13; RV-10).
3. Each of ROUTE-112–ROUTE-117 asks the step-up once 15 minutes have passed since the last confirmation, and a step that takes a current code accepts a code only once, in sequence and concurrently (AU-15; C-65; M-39).
4. The enrolment's QR code and key and every set of recovery codes appear once as non-persisted flash data, never in a page prop kept in history or in browser storage; a regeneration voids the previous set (AU-16; AU-19; H7-12).
5. Disabling TOTP is refused while the account is high-risk; otherwise it clears the four TOTP fields together and unlinks the Google identity with reason TOTP_DISABLED (AU-20 f; AU-24; C-62).
6. A Google link is created only by the holder's confirming request with password confirmation and a current code; the same pair already linked is a no-op, a subject linked to another account is refused with GOOGLE_LINK_CONFLICT and an Owner alert, and a link racing a deactivation leaves no active link (AU-23; BD-19; M-41; M-42).
7. The own unlink ends the account's other sessions and records `unlinked_by` = the account with reason SELF (AU-24; C-63).
8. The display language changes every page at once, persists across a new session, leaves another account unaffected, and a value other than `id`, `en` or the default is refused (RB-10; INT-002).
9. After any credential or factor event of the account — an Owner's reset or unlink included — its next sign-in shows MSG-29 naming the event and its time (AU-13; H7-14; H7-18).
10. Every change writes its audit event in its transaction with the account as actor and authority class SYSTEM, and its security event after the commit, holding no password, code, secret or token (LG-01–LG-03; CI-09).

**TEST REQUIREMENTS:**

- Lint: TL-01
- Typecheck: TL-02
- Unit: the locale value rule and the disable control's high-risk condition (TL-03)
- Feature: ROUTE-065 and ROUTE-111–ROUTE-118 with each answer and refusal their INT- rows name (TL-04)
- Integration: M-39 at a code-taking step, M-41 and M-42, and HO-41's own unlink against the account's own requests, with real sessions (TL-05)
- Authorization: SELF only — every route reaches the account's own row and no other (SM-02) (TL-06)
- Route integrity: ROUTE-065, ROUTE-111–ROUTE-118 audited (TL-07)
- Interaction: INT-002 and INT-111–INT-118, the step-up expiring on each guarded one (TL-08; IG-05)
- Browser E2E: PG-065's part of J-ACC-01 — the password change, enrolment, viewing the codes, the Google link with the faked provider and the unlink — and CJ-18's language switch with unsent input and across a re-login (TL-09)
- Localization/language switch: both languages on PG-065 and the account menu (TL-10)
- Visual breathing room/density: TEST_STRATEGY §9
- Responsive: TL-11
- Accessibility: TL-12
- Production build: TL-13
- Security: SM-18–SM-20 (H8-18–H8-20) against TM-59, TM-60, TM-62, TM-63, TM-66, TM-67, TM-72 and TM-73; the codes, the secret and the QR absent from history and browser storage (SM-13) (TL-14)
- Performance: PFM-01 and PFM-02 for PG-065; PFM-19 — the identity steps read only the account row and the limiter (TL-15)
- Migration: N/A — no migration or import code in this Task
- Other: GAP-043 — the Fortify behaviours these routes rely on re-verified and recorded (SM-22)

**TEST CHECKLIST:**

- [ ] Lint
- [ ] Typecheck
- [ ] Relevant automated tests
- [ ] Authorization
- [ ] Route integrity
- [ ] Interaction behavior
- [ ] Browser E2E if required
- [ ] Responsive/adaptive if required
- [ ] Visual breathing room/density if UI
- [ ] Production build
- [ ] Security checks if required
- [ ] Database safety review
- [ ] Final diff review

**COMPLETION EVIDENCE:**

- Test outputs: each required layer's result in [TEST_STRATEGY §16](../08-testing/TEST_STRATEGY.md#16-evidence)'s form, tied to the tested commit (DOD-04)
- Build result: the production build of TL-13
- Route evidence: the route audit for the routes this Task adds or changes, or N/A with its reason
- Interaction evidence: the INT- tests of this Task, or N/A with its reason
- Browser evidence: `E2E: PASS — Evidence: …` for its journeys (DOD-18), or N/A with its reason
- Localization evidence: both languages on every page it changes (DOD-16), or N/A with its reason
- Cost evidence/exception if applicable: ADDITIONAL RECURRING COST CHANGE: NONE (DOD-20)
- Files changed: the list, every one inside the IN SCOPE areas
- Commit SHA: the commit under the Owner's identity, with no AI attribution
- Notes: each non-applicable item with its reason (DOD-03); the DONE update of TASK_PLAN, this file and CURRENT_HANDOFF (AICWDF §22.3)

**SAFE NEXT TASK:**

- TASK-062 — when its other dependencies are DONE and its READY gate holds

**DO NOT DO:**

- add to V1 — a capability, page, route, field, integration, document type, report or paid service — or drop, defer or narrow a V1_REQUIRED requirement; a Task that would need either raises an Owner question and waits ([DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-20)
- access the VPS, production, its data or a credential, or put a secret, real data, a host detail or production configuration in the repository ([INFRASTRUCTURE](../09-operations/INFRASTRUCTURE.md) INF-01, INF-05; SECURITY SX-01, SX-04, SX-06; [PRODUCTION_DATA_SAFETY](../00-governance/PRODUCTION_DATA_SAFETY.md))
- add AI, model or tool attribution to a commit — the Owner's configured identity only ([AGENTS.md](../../AGENTS.md#git-commit-attribution); DIR-022)
- start before the Owner's execution authorization at OG-4, or mark DONE without the required tests passing and the completion evidence ([TASK_PLAN](TASK_PLAN.md#2-rules-of-the-plan); [DEFINITION_OF_DONE](../08-testing/DEFINITION_OF_DONE.md#2-the-checklist))
- weaken, skip or delete a valid test to obtain a green run, or adopt a paid tier without the Owner's COST_POLICY decision (DOD-05; [COST_POLICY](../00-governance/COST_POLICY.md))
- let a route of PG-065 accept an account reference, edit anything AU-13 does not list, or show a confirmed TOTP secret again
- put a password, code, secret, QR code, token or provider subject in a page prop kept in history, browser storage, a log or an audit record
- register a Fortify two-factor route that bypasses the project's steps, or link a Google identity by e-mail or from the callback alone

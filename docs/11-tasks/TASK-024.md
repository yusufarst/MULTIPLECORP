# TASK-024 — Users and access — accounts, roles, capabilities and company grants

Status: APPROVED | Updated: 2026-10-20 | Owner: Planning

Approval: [APPR-016](../00-governance/DECISION_LOG.md#appr-016--p11-task-plan-tasks-and-feature-coverage-matrix-approved), explicit Owner approval on 2026-10-20 (DIR-085 OF-07) of this document as committed in the first execution session's approval commit; the approved file hash and the verified conditions are recorded there. Approval changes lifecycle only, not implementation authorization.

Amendment: on 2026-10-26 the Owner's decision [DIR-097](../00-governance/DECISION_LOG.md#dir-097--task-020s-discoveries) (R1) adds one AUDIT line: the account image AU-25's reading of the audit trail needs; no other line changes. It is a Level 1 record under CHANGE_CONTROL, as the Owner directs.

Written by WF-PC5-W's writer of bundle W1 of run PC-5 on 2026-10-19 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99; the Owner approved P11 with the [Task plan](TASK_PLAN.md) at OG-4 (DIR-085 OF-07). **No Task starts before the freeze record, from which the execution authorization is in force (DIR-085 OF-09).** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** DONE

**RISK CLASS:** HIGH

**EXECUTOR:** AGENT

**OBJECTIVE:**

Build PG-060 *Pengguna & Akses*, the Owner's page for individual accounts: create, edit, deactivate and reactivate them; assign the role and the company and capability grants after the capability-by-company preview; issue and revoke credential links; end an account's sessions; reset another account's TOTP and unlink its Google identity — each an NX-02 command behind the step-up (ROUTE-060, ROUTE-121–ROUTE-128, ROUTE-130; WF-ACC-02; J-ACC-02).

**WHY THIS EXISTS:**

Accounts, roles, capabilities and company grants are Owner-only authority (WORKFLOWS §4; W4-01; OD-01, OD-02), and default deny makes every Admin capability an individual grant (RG-02, RG-04); AC-13 is met per account only if the Owner can see and change exactly what each account may do in each company — a capability applies to every granted company (RG-07; GAP-032), so the preview shows the result before it is saved (UXS-27). TASK-011 builds the model; this Task builds the Owner's page and commands over it.

**DEPENDS ON:**

- TASK-013 — Command envelope, command identity, idempotency and the lock order
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

**PARALLEL SAFE:** NO — its access changes lock target accounts at LK-03 and the Owner set at LK-02, which every command and sign-in of those accounts also meets (RV-01–RV-05)

**IN SCOPE:**

- PG-060 with NAV-060 and its view ROUTE-060 (`users.manage`, `permissions.manage`; SCR-57): the account list and an account's detail with sections for role, companies, capabilities, sessions and credential links, and H7-17's account screens — TOTP status and enrolment time, the reset, the Google unlink with its reason and the Google link history (IA §14.5)
- ROUTE-121–ROUTE-123 with INT-121–INT-123: create, edit, deactivate and reactivate an account — one individual account per person by its normalized e-mail (AU-01), deactivation revoking its grants, unlinking its Google identity with reason DEACTIVATION and ending its sessions in the command (RG-10; AU-09; AU-24; RV-05), reactivation needing fresh grants
- ROUTE-124 with INT-124: the role, company and capability grants after the preview "Pratinjau Akses" — the capability-by-company matrix *Dapat*, *Baru*, *Dicabut*, prerequisites stated per capability and "Nonaktif — prasyarat dicabut" for a dependant a removed prerequisite disables (PT-30; D-DS-09; UXS-27; RG-06; RG-07; GAP-032); each ADM_PLUS capability its own grant (RG-08), `stock.allocate_intercompany` among them (L-03); OWNER_ONLY capabilities never offered for an Admin (RG-02; OD-02); a change that makes an account without confirmed TOTP high-risk ending its sessions and reissuing its credentials in the same command, the person then enrolling on PG-004 (AU-18; RV-11; H7-15)
- ROUTE-125 and ROUTE-126 with INT-125 and INT-126: "Buat Tautan Atur Kata Sandi" — the link shown once with its expiry as non-persisted flash data for out-of-band delivery, the person setting the password on PG-003 under H7-04's page (AU-11; AU-12; DEP-08) — and its revocation; a replayed issue shows the link's record, never the link (CI-05)
- ROUTE-127 and ROUTE-128 with INT-127 and INT-128: another account's TOTP reset — for an account without TOTP the password reissue — and Google unlink, each with a mandatory reason, never on the acting Owner itself (AU-20 c, d; AU-24; RV-15; D-SEC-19; DIR-044 R-05); unlinked rows kept, never deleted (D-SEC-20; H7-16)
- ROUTE-130 with INT-130: "Akhiri Sesi" for one of an account's sessions, shown as a device summary with start and last activity (AU-10; PJ-17), a quick action on the phone too (J-ACC-02) — where MSG-29's notice to the issuing Owner sends it when a link was used by someone else (H7-14)
- the last active Owner protected on every path (RG-09; L-34; RV-04); each credential link's record — an OWNER_RECOVERY link with the operator's console as its issuer (PJ-17; TM-57) — and MSG-28's single answer for a used, expired or revoked link (AU-12; UXI-22); account onboarding as separate commands, each with its own answer (IP-19; UXI-31)

**OUT OF SCOPE:**

- the capability catalogue, the resolver, the authorization order and the route audit (TASK-011); the field projection (TASK-012)
- sign-in, credential-link consumption on PG-003 and the step-up mechanism (TASK-017); TOTP itself and its challenge (TASK-018); Google sign-in (TASK-019); the account's own page (TASK-023)
- the operator's Owner-recovery, incident and OWNER-restoration commands (TASK-020); the Owner's in-app notices on *Beranda* (TASK-058); the security log page (TASK-061)

**REFERENCES:**

- [WORKFLOWS §5.1](../03-workflows/WORKFLOWS/s05-01-02-access-project.md#wf-acc-02--users-roles-capabilities-and-company-grants-desk--own) WF-ACC-02; [§4](../03-workflows/WORKFLOWS/s01-04-foundations.md#4-actor-and-authority-matrix); [§12](../03-workflows/WORKFLOWS/s10-14-signals-decisions-traceability.md#12-planner-determined-level-1-decisions) L-03, L-34
- [BUSINESS_RULES §4](../02-domain/BUSINESS_RULES.md#access-boundary-acc) BR-ACC-01–BR-ACC-03; [§1](../02-domain/BUSINESS_RULES.md#audit-convention) BR-XC-01–BR-XC-03
- [PERMISSIONS_MATRIX §3](../05-security/PERMISSIONS_MATRIX.md#3-role-and-grant-model) RG-01–RG-12; [§4](../05-security/PERMISSIONS_MATRIX.md#4-capability-catalogue); [§7.3](../05-security/PERMISSIONS_MATRIX.md#73-special-projections) PJ-17; [§9](../05-security/PERMISSIONS_MATRIX.md#9-owner-only-denials) OD-01, OD-02
- [SECURITY §2](../05-security/SECURITY.md#2-authentication-sessions-and-credentials) AU-01, AU-09–AU-12, AU-15, AU-17, AU-18, AU-20, AU-24; [§6](../05-security/SECURITY.md#6-audit-and-security-logging) LG-01–LG-03, LG-07; [§8](../05-security/SECURITY.md#8-threat-model) TM-02, TM-05–TM-07, TM-29, TM-45, TM-52, TM-57, TM-60, TM-62, TM-66–TM-68, TM-72, TM-73; [§9](../05-security/SECURITY.md#9-design-decisions) D-SEC-19, D-SEC-20
- [SECURITY §10 — P7](../05-security/SECURITY.md#p7--presentation-only) H7-01, H7-04, H7-14–H7-17; [— P8](../05-security/SECURITY.md#p8--required-denial-and-security-tests-p8-creates-its-own-files) H8-18–H8-20; [— P6](../05-security/SECURITY.md#p6--mechanisms-only) H6-06, H6-15
- [ROUTE_CONTRACTS](../03-workflows/ROUTE_CONTRACTS.md#users-and-access--pengguna--akses-pg-060-owner_only) ROUTE-121–ROUTE-128, ROUTE-130; [§2](../03-workflows/ROUTE_CONTRACTS.md#2-page-views--route-001route-065) ROUTE-060
- [INTERACTION_CONTRACTS](../03-workflows/INTERACTION_CONTRACTS.md#users-and-access--pengguna--akses-pg-060-owner_only) INT-121–INT-128, INT-130
- [NAVIGATION_CONTRACTS §2.6](../07-ux-design/NAVIGATION_CONTRACTS.md#26-reports-master-data-settings-and-owner-areas) NAV-060
- [INFORMATION_ARCHITECTURE §8.6](../07-ux-design/INFORMATION_ARCHITECTURE.md#86-reports-master-data-settings-and-owner-areas) PG-060; [§14.5](../07-ux-design/INFORMATION_ARCHITECTURE.md#145-scr-57--pengguna--akses-the-owners-account-screens)
- [ADMIN_FLOW J-ACC-02](../07-ux-design/ADMIN_FLOW/s10-a-journeys.md#101-access-and-master-data); [§15](../07-ux-design/ADMIN_FLOW/s14-15-messages-scenarios.md#visibility-and-authority) UXS-27; [§14](../07-ux-design/ADMIN_FLOW/s14-15-messages-scenarios.md#14-message-catalogue) MSG-28, MSG-29; [§3](../07-ux-design/ADMIN_FLOW/s01-04-foundations.md#3-derived-interaction-requirements) UXI-16, UXI-22, UXI-31
- [DESIGN_SYSTEM §4](../07-ux-design/DESIGN_SYSTEM.md#4-design-decisions) D-DS-09; [§9.3](../07-ux-design/DESIGN_SYSTEM.md#93-forms-and-entry) PT-30
- [CONCURRENCY_IDEMPOTENCY §7.3](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s07-statement-sequences.md#73-command-families-outside-ax-0137) NX-02; [§16](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s14-17-numbering-jobs-revocation.md#16-authorization-and-revocation-races) RV-01–RV-05, RV-08, RV-11, RV-15; [§18](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s18-scenarios.md#18-mandatory-concurrency-and-failure-scenarios) M-13, M-20–M-22, M-42, M-45; [§19](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s19-20-handoff-traceability.md#19-handoff-obligations) HO-41
- [DATABASE §18](../04-architecture/DATABASE/s18-19-constraints-integrity.md#18-constraint-catalogue) C-32, C-54; [API_AND_INTEGRATIONS §1](../06-api-performance/API_AND_INTEGRATIONS.md#1-route-boundaries) RB-07
- [SECURITY_TEST_MATRIX](../08-testing/SECURITY_TEST_MATRIX.md#3-the-matrix) SM-04, SM-05, SM-07, SM-13, SM-19, SM-20
- [GAP_REGISTER](../00-governance/GAP_REGISTER.md#gap-032--capability-grants-apply-to-every-granted-company) GAP-032
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (109): O-01; O-06; CAP-01; CAP-13; AC-13; REF-002–REF-004; REF-049; IMG-01; OWN-02; OWN-05; BR-ACC-01–BR-ACC-03; WF-ACC-02; L-03; ROUTE-060; ROUTE-121–ROUTE-128; ROUTE-130; INT-121–INT-128; INT-130; C-32; C-54; RG-01–RG-12; AU-01; AU-09; AU-12; AU-17; AU-20; AU-24; TM-02; TM-05–TM-07; TM-29; TM-45; TM-52; TM-57; TM-60; TM-62; TM-66–TM-68; TM-72–TM-73; D-SEC-19–D-SEC-20; H6-06; H6-15; H7-01; H7-04; H7-14–H7-17; H8-18–H8-20; NX-02; M-13; M-20–M-22; M-42; M-45; HO-20; HO-41; RB-07; PG-060; SCR-57; UXI-16; UXI-22; UXI-31; J-ACC-02; MSG-28–MSG-29; UXS-27; D-DS-09; PT-30; NAV-060; SM-19–SM-20; GAP-032

**EXPECTED MODULES / FILE AREAS:**

- the IAM module's account and access actions — CreateUser, UpdateUser, DeactivateUser, ReactivateUser, GrantRole, GrantCompany, GrantCapability, IssueCredentialLink, RevokeCredentialLink, ResetTotp, UnlinkGoogleIdentity, EndUserSession (RC-02) — with their policies and queries; `resources/js/Pages/IAM` for PG-060; `tests/Feature/IAM` and `tests/Concurrency/`

**BUSINESS RULES:**

- BR-ACC-01: default deny — an Admin holds no company and no capability until the Owner grants them (RG-04); BR-ACC-02 and BR-ACC-03 as the grants' reach: S3 company data only within a grant and its family capability, a deactivated company kept in scope for history, collection and corrections (CS-02; C-54)
- BR-XC-01–BR-XC-03: permissions, grants and user access are critical audit areas; accounts are never shared (AU-01; AZ-12); the last active Owner is never deactivated or demoted (L-34; RG-09)

**ARCHITECTURE RULES:**

- one action per command in IAM (ARCHITECTURE §3, §5); the effective-capability resolver of TASK-011 reads the grants this page writes, and no policy reads a role name (AZ-02); the framework-native gates over the approved tables, no permission package (D-PM-07)
- grants stay global per user in V1 (RG-07; D-PM-06) — no company-scoped capability grant is built (GAP-032's future seam)

**SECURITY / PERMISSION REQUIREMENTS:**

- the page and every command OWNER_ONLY: `users.manage` for accounts, links, sessions, resets and unlinks; `permissions.manage` for role and grants (RG-05; OD-01; OD-02); an Admin's address for PG-060 answers as IP-21 (AZ-11)
- step-up on every command (AU-15; ROUTE-121–ROUTE-130); a reset or unlink of the acting Owner's own account refused (AU-20 c; AU-24); a grant to oneself refused (OD-02); a grant without its prerequisites refused (RG-06); an OWNER_ONLY capability never granted to an Admin (RG-02)
- the credential link built only from the configured application URL and shown once (AU-12; WS-11; TM-07); deactivation ending every session (AU-09; H6-06)

**COMPANY SCOPE AND FIELD PROJECTION:**

- the Owner's scope covers every company (CS-01; RG-03); the page is global (IA §8.6); the commands target accounts, not a company's records, so CS-03's company check is the Owner's all-company scope; a company grant names a company row of the workspace (C-32)
- projection PJ-17's Owner view — the directory, grants, each account's sessions as a device summary with start and last activity, each credential link's purpose, issuer, issue time, expiry and use or revocation, TOTP status and enrolment time and the Google link history — never a TOTP secret, recovery code, provider subject, session identifier, payload, raw address or token (PERMISSIONS_MATRIX §7.2 rows 1–7 and 125)

**CONCURRENCY / IDEMPOTENCY:**

- NX-02 SF-CMD commands with `command_id` (CI-01; ROUTE-121–ROUTE-130), lock set LK-00, LK-02, LK-03, LK-04, LK-05 in that order, class TX-09 (20 s): the acting Owner's and the target's rows at LK-03 in id order (RV-01, RV-02, RV-15; H6-15); a demotion or deactivation of an Owner takes LK-02 first (RV-04; M-22); token and session rows written by the last statements (LR-02)
- an access change waits for the target's command in flight and commits after it; a later command of the target is denied (RV-03; M-20); a deactivation racing a command, a login and an open session (RV-05; M-21); a high-risk change racing the target's login, password change and enrolment (RV-11; M-45); a link racing a deactivation (M-42); export requests the change no longer covers marked WITHHELD in its transaction (RV-08)
- replay re-authorized, another account's reuse a CONFLICT without disclosure (CI-05; M-13; UXI-16); a replayed link issue returns the link's identifier, never the link (CI-05)

**AUDIT:**

- every creation of an account and every change of its role or active flag carries, in its audit event's before/after, the account's image under the keys `role`, `role_granted_by`, `role_granted_at` and `is_active` that TASK-020's reading of the audit trail uses, and every change of an account's grants, credentials or sessions is an audit event of the account (`entity_type` `user`), so the trail establishes every pre-window Owner and every touch since an incident's closing sweep (AU-25; RV-18; DIR-097 R1)
- every account, role, grant and credential-link change a business audit event in its command's transaction with before/after, actor, reason and authority class OWNER_ONLY (RG-11; LG-01; AZ-10), the security events after the commit — ACCOUNT_SETUP_LINK_ISSUED, PASSWORD_RESET_REQUESTED, TOTP_RESET, GOOGLE_UNLINKED, SESSION_INVALIDATED among them (LG-02) — and LG-07's alert for every Owner reset of an account; the reason mandatory on a reset and an unlink (AU-20 c; AU-24); no token or link in clear in any record (LG-03); the OD-16 reconciliation finds no anomaly (SM-05)

**DATABASE IMPACT ASSESSMENT:**

- Schema change: NONE — uses the tables of TASK-005–TASK-009; a missing column is an executor discovery (AGENT_OPERATING_MODEL)
- Migration required: NO
- Existing data transformation: NONE
- Direct production DB access: FORBIDDEN
- Data-loss risk: NONE KNOWN

**MODULARITY / EXTENSIBILITY:**

- Module boundary affected: IAM, tier 0 (ARCHITECTURE §3)
- Stable core kept provider-neutral: YES
- External/vendor-specific code isolated: YES — framework facilities or a small interface (ARCHITECTURE §2)
- Replaceable adapter/contract required: NO
- Provider lock-in risk: NONE beyond the Owner's baseline stack
- Future extension impact: LOW — custom roles stay additive rows (RG-12) and a company-scoped grant an expand-only seam (GAP-032), neither built
- Speculative abstraction avoided: YES — no repository class per model, no generic workflow or rules engine (ARCHITECTURE §1)

**AUTH IMPACT:**

- Authentication change: PROPOSED — the Owner's commands that change another account's credentials — credential links, the TOTP reset or password reissue, the Google unlink, ending sessions — as SECURITY AU-12, AU-18, AU-20 c, d and AU-24 design them, nothing beyond them
- Authentication implementation: LARAVEL_NATIVE — decided once ([EXECUTION_CONTEXT](EXECUTION_CONTEXT.md)), reused, never redesigned
- Auth profile: AICWDF-COMPAT-8 — unchanged; the person sets the password through the link (AU-03; AU-12)
- Google sign-in: N/A — not touched; only the Owner's unlink of another account's identity is built here
- Google registration behavior: EXISTING_ACCOUNT_ONLY — unchanged; no account is created or linked from Google (AU-22)
- Google credentials owner action required: NO
- Authorization change: PROPOSED — the Owner's grant and revocation commands over the model of TASK-011, nothing beyond RG-01–RG-12
- Provider/library-specific leakage into domain: NO

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

- Context7: REQUIRED — the framework's gates and policies, database sessions and their deletion by user id, and password confirmation, at the installed versions
- Graphify: OPTIONAL — once TASK-011's resolver and TASK-017–TASK-019's IAM code exist
- Browser E2E: REQUIRED — J-ACC-02 and CJ-11
- Other: a two-connection harness for the revocation races (TASK-003); a controlled clock for the step-up window
- Missing safe tool behavior: AUTO-INSTALL project-locally inside this Task (AICWDF §6.3) and record it in TOOLCHAIN; a paid tier or a privileged installation STOPS for the Owner (COST_POLICY)

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: refreshed before the change when Graphify is available ([TOOLCHAIN](../00-governance/TOOLCHAIN.md)); otherwise a targeted search of the IAM module and the resolver
- Pre-change questions: where the effective-capability resolver and the high-risk test read the grants this page writes; which export requests and sessions an access change must withhold or end
- Expected affected areas: IAM; the resolver and the session guard of TASK-011 and TASK-017 read, never changed

**CONTEXT7:**

- Topics to verify: the framework's session `database` driver and deleting a user's sessions; gate and policy registration; password confirmation's `password_timeout`
- Version-sensitive assumptions: none from memory — each verified at the installed version

**UI / UX REQUIREMENTS:**

- Follow global design rules: YES
- Indonesian default: YES
- English support: YES
- Plain-language Indonesian: YES — H7-01's generic credential messages, MSG-27, MSG-28 and "Pratinjau Akses" as written
- Language switch impact: keeps unsent input
- Mobile-first: YES — a `[desk]` page readable on the phone with its quick actions (NV-06)
- Responsive: YES
- Adaptive: YES — on the phone read, "Akhiri Sesi" and "Nonaktifkan" as quick actions; grant editing opens but is a desk task (J-ACC-02)
- Visual breathing room: REQUIRED
- Comfortable density: REQUIRED
- shadcn/ui reuse: the data table (PT-05), the detail layout (PT-12), the capability-by-company preview (PT-30), the confirmation (PT-26) and the step-up dialog (PT-28)
- Design reference: PG-060 as INFORMATION_ARCHITECTURE §8.6 and §14.5 state it, its account screens designed in [AUTH_JOURNEYS](../07-ux-design/stage2/AUTH_JOURNEYS.md); the Golden Frontend Reference for presentation only (DIR-061 SD-02)
- Primary action: on the list, creating an account; on the access section, "Simpan Akses" after "Pratinjau Akses" (UXS-27)
- Secondary actions: "Buat Tautan Atur Kata Sandi", "Akhiri Sesi", the TOTP reset and the Google unlink
- Destructive actions: deactivation, link revocation, ending a session, the TOTP reset and the Google unlink — each with its listed change (IP-08) or its reason (IP-09) and the step-up
- Redundancy constraints: one control per command (INT-121–INT-130); every OWNER_ONLY capability absent from an Admin's grant editor (OD-02)
- Loading state: PT-19; an access change that waits for the target's command in flight, up to about 20 s, keeps the control busy (TX-09; J-ACC-02)
- Empty state: an account without any grant shows that it has none, with the grant action (RG-04)
- Error state: RG-09's refusal in words; a duplicate e-mail at the field; CONFLICT on a stale `lock_version` (IP-03); FAILED after the 20 s wait, safe to repeat (IP-04)

**ROUTE IMPACT:**

- ROUTE-060 (PG-060's view); ROUTE-121 (create), ROUTE-122 (edit), ROUTE-123 (deactivate or reactivate), ROUTE-124 (grants), ROUTE-125 (issue a link), ROUTE-126 (revoke a link), ROUTE-127 (TOTP reset), ROUTE-128 (Google unlink), ROUTE-130 (end a session); ROUTE-129 stays withdrawn — the key-compromise command is the operator's console command (AU-21)

**INTERACTION CONTRACTS:**

- INT-121–INT-128, INT-130; INT-011 for the step-up (with TASK-017)

**NAVIGATION CONTRACT:**

- Parent: none — *Pengaturan* › *Pengguna & Akses*; PG-006 for the security notice (NAV-060)
- Entry: *Pengaturan* › *Pengguna & Akses*; *Beranda*'s security notice
- Back action: "Kembali" from an account → PG-060 (NAV-060)
- Cancel: "Batal"; the step-up's and the confirmation's "Kembali" → the form, the input kept (NV-02)
- Success destination: the account's detail with the change in place; a credential link shown once
- Error behavior: NV-04; a failed step-up answers in place (IP-10)
- State preservation: the open tab in the address; unsent input as a form's (NV-05)
- Mobile behavior: `[desk]` — read and the two quick actions on the phone (J-ACC-02)

**ACCEPTANCE CRITERIA:**

1. Only the Owner reaches PG-060 and its commands; an Admin's request for any of them answers as IP-21, identical to a nonexistent address (OD-01; OD-02; AZ-11).
2. A new account holds no company and no capability; every Admin capability is an individual grant, each ADM_PLUS capability its own grant row, and an OWNER_ONLY capability is refused for any account but through the OWNER role (RG-02; RG-04; RG-08).
3. Before "Simpan Akses" the preview shows every granted capability against every granted company as *Dapat*, *Baru* or *Dicabut*, and a dependant of a removed prerequisite as "Nonaktif — prasyarat dicabut" with its grant kept (UXS-27; RG-06; RG-07; GAP-032).
4. Deactivating or demoting the last active Owner is REJECTED with the rule in words, also when two such commands race (RG-09; RV-04; M-22).
5. A deactivation revokes the account's grants, unlinks its Google identity with reason DEACTIVATION, revokes its unused links and ends all its sessions in one command, and the account's next request is unauthenticated (RG-10; AU-09; RV-05; M-21).
6. A role assignment, grant or activation that makes an account without confirmed TOTP high-risk ends its sessions, makes its password unusable and issues one RESET link, shown once, in the same command (AU-18; RV-11; M-45).
7. A credential link appears once as non-persisted flash data and is never stored in clear; a replayed issue returns the link's record, never the link; a revoked, used or expired link answers MSG-28 (AU-11; AU-12; CI-05).
8. Another account's TOTP reset or Google unlink needs the step-up and a reason, is refused on the acting Owner's own account, ends every session of the target and raises its alert (AU-20 c, d; AU-24; RV-15; LG-07).
9. An access change racing the target's command in flight commits after it and the target's next command is denied; a wait beyond TX-09's 20 s answers FAILED, safe to repeat under the same `command_id` (RV-02; RV-03; M-20; IP-04).
10. Every change writes its audit event in its transaction with before/after, the acting Owner, its reason where required and authority class OWNER_ONLY, and the OD-16 reconciliation finds no anomaly (RG-11; AZ-10; SM-05).

**TEST REQUIREMENTS:**

- Lint: TL-01
- Typecheck: TL-02
- Unit: the preview's derivation of *Dapat*, *Baru*, *Dicabut* and of the dependants a removed prerequisite disables (TL-03)
- Feature: ROUTE-060, ROUTE-121–ROUTE-128 and ROUTE-130 with each refusal — a duplicate e-mail, a stale `lock_version`, RG-09, NX-02's checks, a self-reset (TL-04)
- Integration: M-13, M-20–M-22, M-42 and M-45, and HO-41's high-risk grant against a login in flight and reset against the target's own requests, with real sessions; HO-20's session deletion by account as deactivation uses it, beside TASK-017's tests of the behaviour (TL-05)
- Authorization: OD-01 and OD-02 on every route and path; SM-04's self-granting and last-Owner elements and RG-07 on the Admin with A and B (TL-06)
- Route integrity: ROUTE-060, ROUTE-121–ROUTE-128 and ROUTE-130 audited; no ROUTE-129 (TL-07)
- Interaction: INT-121–INT-128 and INT-130, each with one `command_id` replayed and the step-up expiring (TL-08; IG-05)
- Browser E2E: J-ACC-02 and CJ-11 — create, grant after the preview, issue a link, end a session, reset, deactivate (TL-09)
- Localization/language switch: both languages on PG-060 (TL-10)
- Visual breathing room/density: TEST_STRATEGY §9
- Responsive: TL-11
- Accessibility: TL-12
- Production build: TL-13
- Security: SM-04, SM-05, SM-07, SM-13 (a revoked grant with an open session, a partial reload and a queued export), SM-19 (H8-19's paths c and d) and SM-20 (H8-20's Owner unlink), against TM-02, TM-05–TM-07, TM-29, TM-45, TM-52, TM-57, TM-60, TM-62, TM-66–TM-68, TM-72 and TM-73 (TL-14)
- Performance: PFM-01 and PFM-02 for PG-060; PFM-20 for the NX-02 commands (TL-15)
- Migration: N/A — no migration or import code in this Task
- Other: H8-18 — after a reset no recovery code of the target is usable (AU-20 c)

**CRITERIA DISPOSITIONS:**

- Criterion 1: `tests/Authorization/UsersAndAccessAccessTest.php` — every route declares `users.manage`, `permissions.manage` for the grants, both OWNER_ONLY; an Admin holding every other capability, its step-up confirmed, answered by the not-found page on every route and path, writing nothing —; the browser's Admin address answered 404 (`tests/Browser/E2E/users-access.spec.ts`)
- Criterion 2: `tests/Feature/IAM/UsersAndAccessTest.php` — a new account inactive, ADMIN_OPERASIONAL, with no company and no capability —; an OWNER_ONLY capability refused for an account at validation and in the command (RG-02), each ADM_PLUS grant its own row with its reason (RG-08) — `tests/Authorization/UsersAndAccessAccessTest.php` for every OWNER_ONLY code
- Criterion 3: `tests/Unit/IAM/AccessPreviewTest.php` — *Dapat*, *Baru*, *Dicabut* against every granted company and "Nonaktif — prasyarat dicabut" with the grant kept —; the preview's partial reload and the save in `tests/Feature/IAM/UsersAndAccessTest.php`; the browser's "Pratinjau Akses" before "Simpan Akses", the save confirming only the state previewed
- Criterion 4: `tests/Feature/IAM/UsersAndAccessTest.php` — the last active Owner's deactivation REJECTED with the rule in words —; M-22's two Owners against each other and one Owner sending both, under real sessions (`tests/Integration/Authorization/AccessChangeRacesTest.php`)
- Criterion 5: `tests/Feature/IAM/UsersAndAccessTest.php` — the grants revoked, Google unlinked with DEACTIVATION, the links revoked and the sessions ended in one command —; HO-20's deletion by account and the next request unauthenticated with real sessions (`tests/Integration/Authentication/AccountCommandSessionsTest.php`); M-21 and M-42 in both orders under real sessions
- Criterion 6: `tests/Feature/IAM/UsersAndAccessTest.php` — a grant making an active account without TOTP high-risk: sessions ended, password unusable, one RESET link shown once; the activation of an account given high-risk grants while inactive replacing its ONBOARDING link —; M-45 against the account's own password change and its TOTP confirmation committed first, and HO-41's grant against a session signed in for real
- Criterion 7: `tests/Feature/IAM/UsersAndAccessTest.php` and `tests/Interaction/UsersAndAccessTest.php` — the link flashed once, never in the audit; a replayed issue its record, never the link; a revoked link refused afterwards —; in the browser the link absent from history and storage
- Criterion 8: `tests/Feature/IAM/UsersAndAccessTest.php` — the reset with and without TOTP, the reason required, the acting Owner's own refused, every session ended, TOTP_RESET or PASSWORD_RESET_REQUESTED of reason CREDENTIAL_REISSUE, the Google unlink with reason OWNER —; HO-41's reset against the target's own requests, its recovery codes void (H8-18)
- Criterion 9: `tests/Integration/Authorization/AccessChangeRacesTest.php` — an access change and a deactivation committing after the target's command in flight, the target's next command denied (M-20) —; TX-09's wait answered FAILED by the envelope's deadline (TASK-013's tests)
- Criterion 10: each command's audit event in its transaction with its command, the acting Owner, OWNER_ONLY and its reason, the security events after the commit — the feature tests' assertions —; SM-05's reconciliation finds no OWNER_ONLY grant (`tests/Authorization/UsersAndAccessAccessTest.php`)

**TEST CHECKLIST:**

- [x] Lint: ESLint and Pint PASS in CI (TL-01)
- [x] Typecheck: TypeScript and Larastan/PHPStan at level `max` PASS in CI (TL-02)
- [x] Relevant automated tests: Unit — the preview's derivation (TL-03); Feature — ROUTE-060, ROUTE-121–ROUTE-128 and ROUTE-130 with each refusal (TL-04); Integration — M-13, M-20–M-22, M-42, M-45, HO-20 and HO-41 with real sessions (TL-05); PASS in CI
- [x] Authorization: OD-01 and OD-02 on every route and path; SM-04's self-grant and RG-07 on the Admin with A and B (TL-06)
- [x] Route integrity: ROUTE-060, ROUTE-121–ROUTE-128 and ROUTE-130 in the route audit and the route list; no ROUTE-129 (TL-07)
- [x] Interaction behavior: INT-121–INT-128 and INT-130, each with one `command_id` replayed and the step-up expiring (TL-08)
- [x] Browser E2E if required: J-ACC-02 and CJ-11 — create, grant after the preview, the link shown once, activate, the holder's sign-in and its session ended, the reissue and the deactivation — on Chromium and WebKit (TL-09)
- [x] Responsive/adaptive if required: PG-060 at 320, 390, 768, 1,280 and 1,680 px without a sideways scroll, "Nonaktifkan" and the sessions on the phone (TL-11)
- [x] Visual breathing room/density if UI: TASK-015's table, sections, preview and confirmation, nothing hand-styled
- [x] Production build: TL-13 PASS in CI
- [x] Security checks if required: SM-04, SM-05, SM-07, SM-13, SM-19 (paths c and d) and SM-20 (the Owner's unlink) (TL-14)
- [x] Database safety review: no migration; local and CI databases only, no production connection
- [x] Final diff review: each rule against the contract and its sources; ER-06's one independent review, its points fixed and tested

**COMPLETION EVIDENCE:**

- Test outputs (TEST_STRATEGY §16), tested commit `fb2be5f`: fresh CI on GitHub's `ubuntu-24.04` runner, runs 40447074607 (push) and 40447079116 (pull request #29), the three jobs PASS in each — "Verify", "Browser" and "Performance and migration". The Owner's workstation's Docker engine stopped answering during the Task, so CI ran every suite; nothing local is claimed beyond ESLint and TypeScript
- Build result: `TL-13 Frontend build: PASS — Evidence: fb2be5f, CI ubuntu-24.04 run 40447074607`
- Route evidence: TL-07's route audit PASS — ROUTE-060 `GET /pengaturan/pengguna`, ROUTE-121 `POST /pengaturan/pengguna`, ROUTE-122 `PUT …/{id}`, ROUTE-123 `POST …/{id}/status`, ROUTE-124 `PUT …/{id}/akses`, ROUTE-125 `POST …/{id}/tautan`, ROUTE-126 `POST …/{id}/tautan/cabut`, ROUTE-127 `POST …/{id}/reset-verifikasi`, ROUTE-128 `POST …/{id}/lepas-google`, ROUTE-130 `POST …/{id}/sesi/{sesi}/akhiri`, the commands behind `step-up` —; `tests/Routes/RouteAuditTest.php`, `tests/Feature/BootTest.php`
- Interaction evidence: `tests/Interaction/UsersAndAccessTest.php` (INT-121–INT-128, INT-130)
- Browser evidence: `TL-09 E2E/browser: PASS — Evidence: fb2be5f, CI ubuntu-24.04 run 40447074607`; TL-10, TL-11 and TL-12 for PG-060 in the same run
- Localization evidence: PG-060 in both languages (`tests/Browser/Localization/users-access.spec.ts`) (DOD-16); 199 catalogue keys added — the capabilities' names, their areas and PG-060's copy —, "Simpan Akses" canonical, the rest proposed
- Performance evidence: PFM-01 and PFM-02 — the directory and an account's detail in the same statements for one row and for 25 —; PFM-20 — a grant change within the budget (`tests/Performance/UsersAndAccessQueriesTest.php`) (TL-15)
- Cost evidence/exception if applicable: ADDITIONAL RECURRING COST CHANGE: NONE
- Files changed: `app/Modules/IAM/` — `Actions/` (the ten NX-02 commands), `Services/AccountAccess`, `Queries/UsersAndAccess`, `Data/` (`AccessPreview`, `CapabilityAreas`, `SessionHandle`), `Authentication/GoogleIdentities` (the Owner's unlink inside a command) —, `app/Modules/ORG/Queries/CompanyDirectory` (the grantable companies), `app/Http/Controllers/UsersAndAccessController.php`, `routes/web.php`; the frontend's PG-060, `lib/use-command.ts` and the settings navigation; `lang/`; the tests, `tests/Support/AccessRaces.php` and the browser fixtures; `tests/Architecture/SourceScan.php`'s AZ-02 allowance; TOOLCHAIN's row
- Commit SHA: `2629e7e`, `0aa1552`, `4910953`, `494d089`, `5d5630c`, `7d38a93` and `fb2be5f`, the Owner's identity, no signature and no AI attribution; this record's commit follows them on `task/TASK-024-users-access`
- Notes: criteria as CRITERIA DISPOSITIONS states. Readings within the approved text: a new account is created inactive and activated once its grants and link are set (WF-ACC-02's "→ activate"; AU-12; RV-11; M-45); an account's detail is the whitelisted address keys `akun` and `bagian` of ROUTE-060, no route viewing one account; ROUTE-130 names a session by a keyed hash of its identifier, which PJ-17 never shows; ROUTE-126 names the link by its purpose, one open per account and purpose; a promotion to OWNER revokes the account's individual grants; an Owner may deactivate itself while another active Owner remains (M-22), RG-09 refusing the last; a link, a reset and an unlink of the acting Owner's own account refused; the capabilities grouped by area from WORKFLOWS §4's rows, ADM_PLUS under "Kewenangan khusus" (GL-036); the directory 25 rows at a time with PT-08's "Muat Lagi". ER-06's independent review found no HIGH point; its MEDIUM and LOW points fixed and tested. Codex's review of pull request #29 found one P1 — a reissued RESET link not shown on the section its answer opens — and one P2 — the directory capped at 500 accounts —; both fixed and tested. No DONE record for another Task changed; no server or VPS touched


**SAFE NEXT TASK:**

- TASK-062 — when its other dependencies are DONE and its READY gate holds

**DO NOT DO:**

- add to V1 — a capability, page, route, field, integration, document type, report or paid service — or drop, defer or narrow a V1_REQUIRED requirement; a Task that would need either raises an Owner question and waits ([DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-20)
- access the VPS, production, its data or a credential, or put a secret, real data, a host detail or production configuration in the repository ([INFRASTRUCTURE](../09-operations/INFRASTRUCTURE.md) INF-01, INF-05; SECURITY SX-01, SX-04, SX-06; [PRODUCTION_DATA_SAFETY](../00-governance/PRODUCTION_DATA_SAFETY.md))
- add AI, model or tool attribution to a commit — the Owner's configured identity only ([AGENTS.md](../../AGENTS.md#git-commit-attribution); DIR-022)
- start before the Owner's execution authorization at OG-4, or mark DONE without the required tests passing and the completion evidence ([TASK_PLAN](TASK_PLAN.md#2-rules-of-the-plan); [DEFINITION_OF_DONE](../08-testing/DEFINITION_OF_DONE.md#2-the-checklist))
- weaken, skip or delete a valid test to obtain a green run, or adopt a paid tier without the Owner's COST_POLICY decision (DOD-05; [COST_POLICY](../00-governance/COST_POLICY.md))
- grant an OWNER_ONLY capability to an Admin, let an account grant to itself, or add a launch role, a custom-role designer or a company-scoped grant (RG-01; RG-07; D-01)
- add impersonation, "login as", a shared or service account or an API token (AU-17; RB-07)
- show a credential link again, store it in clear, or reset or unlink the acting Owner's own account through these commands

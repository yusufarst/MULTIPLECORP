# TASK-020 — Installation step, the first Owner and the Owner-recovery path

Status: REVIEW | Updated: 2026-10-20 | Owner: Planning

Written by the executor of run PC-5 on 2026-10-19 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99 — a REVIEW draft of P11, approved, or not, with the [Task plan](TASK_PLAN.md) at OG-4. **No Task starts before the Owner's execution authorization at OG-4.** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** TODO

**RISK CLASS:** HIGH

**EXECUTOR:** AGENT

**OBJECTIVE:**

Build the operator's audited console commands of SECURITY §2 and RELEASE_PLAN PRQ-09: the installation step that creates the first Owner, the OWNER_RECOVERY link of AU-14, the key-compromise incident command and the re-encryption command of AU-21, and the OWNER-restoration command of AU-25 with its halts as OG-3 amended them — each run only by the human operator, never by an agent.

**WHY THIS EXISTS:**

An Owner who loses access, a leaked key and a compromised window need a designed path that never sets, reads or reveals a secret (AU-14, AU-21, AU-25; D-SEC-19); GAP-049 asks for the installation event's actor rule and GAP-050 for the post-incident cases; DIR-083 OE-10 sends a recovery halt on a trusted state first to the Owner's incident decision outside the application.

**DEPENDS ON:**

- TASK-010 — Audit and security logging
- TASK-018 — Two-step verification (TOTP) and recovery codes
- TASK-019 — Google sign-in for existing accounts

**BLOCKS:**

- TASK-069 — Security test matrix, route integrity and zero dead interactions
- TASK-076 — Operator runbooks — Owner-account recovery, key compromise, Scenario B and key rotation
- TASK-085 — Installation, the first Owner, two-step enrolment and the APP_KEY rotation

**PARALLEL SAFE:** NO — it changes the IAM recovery state the sign-in Tasks rely on

**IN SCOPE:**

- the installation step creating the first Owner with its audit event recording the operator's identity — the host account and the stated name, never a user row (PRQ-09; GAP-049; SM-24)
- the first Owner's password path as [DIR-085](../00-governance/DECISION_LOG.md#dir-085-dir-086-obs-034-and-tech-041--og-4-decisions-appr-016-the-planning-freeze-and-the-execution-authorization) OF-06 decides (P11Q-06 (a); TASK_PLAN §8): the operator issues the first Owner's OWNER_RECOVERY link through AU-14's command right after the installation step, with the Owner present, who opens it on the Owner's own device; the operator's statement of why is recorded in the issue's audit event and AU-14's alerts follow; no new mechanism — nothing beyond this Task's commands is built
- AU-14: the command issuing an OWNER_RECOVERY link for an active OWNER-role account only, its effects on use (TOTP cleared, sessions ended, links revoked, Google unlinked with reason OWNER_RECOVERY), its audit and security events and alerts (LG-07; H9-06); and its run guard: until a recovery run's recovery ends, a link without a named confirmer only for an account that the audit trail shows held the OWNER role and was active at the run's recorded window start, that run and its window start recorded in the issue's audit event, and every other OWNER-role account refused unless the operator names the recovered pre-window Owner who confirmed it in the review — checked under the row locks of the account and the confirmer, taken in id order (LK-03): the confirmer an active Owner at the window start, its OWNER_RECOVERY link used after the window, and an active Owner now —, the confirmer named in the issue's events and alerted (LG-07)
- AU-21: the re-encryption command for rotation through the previous-keys list, and the key-compromise incident command with the compromise window recorded once as its input, running protocol (a)–(d): an opening sweep that deletes every session and revokes every unused credential token, the application kept in maintenance mode without a bypass while the run lasts, every account processed — one created after the run started included —, and a closing sweep; it refuses to start, or to continue, unless the maintenance mode is active, each sweep runs one identity transaction per account under its row (RV-17), and a stopped run is restarted only with the same window start, skipping the accounts already voided
- AU-25: the OWNER-restoration command for pre-window Owners only, refusing every other account, once per account per run (M-46), refusing to run before the incident command has completed — the audit events of its closing sweep show it — or once the run's recovery has ended, and refusing an account touched since the closing sweep, the run's own writes excepted (RV-18); the end of the run's recovery recorded only when every active account holding the OWNER role is a pre-window Owner of the run or has an Owner-recovery issue in the run naming a confirmer, refused otherwise with the accounts that block it (AU-14; RV-18); with its halt conditions — on a trusted state the halt goes first to the Owner's incident decision outside the application with the client (DIR-083 OE-10), on a restored state ending in that decision as the scenarios state
- the operator's runbook steps these commands serve are TASK-076's; this Task's commands print what the runbook needs and nothing secret

**OUT OF SCOPE:**

- running any command on production — the operator's, in TASK-085 and in an incident
- the runbooks (TASK-076) and the drills (TASK-081)

**REFERENCES:**

- [SECURITY §2](../05-security/SECURITY.md#2-authentication-sessions-and-credentials) AU-14, AU-20, AU-21, AU-25
- [SECURITY §9](../05-security/SECURITY.md#9-design-decisions) D-SEC-19
- [INFRASTRUCTURE §7](../09-operations/INFRASTRUCTURE.md#7-security-operations) §7.1–§7.4
- [RELEASE_PLAN §10](../10-release/RELEASE_PLAN.md#10-production-release-requirements-for-p11) PRQ-09
- [SECURITY_TEST_MATRIX](../08-testing/SECURITY_TEST_MATRIX.md) SM-19, SM-21, SM-24
- [CONCURRENCY_IDEMPOTENCY §18](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s18-scenarios.md#18-mandatory-concurrency-and-failure-scenarios) M-46
- [BACKUP_RECOVERY §6](../09-operations/BACKUP_RECOVERY.md#6-the-tabletop-incident-recovery-exercise) TT-F25, TT-F30
- [GAP_REGISTER](../00-governance/GAP_REGISTER.md) GAP-049, GAP-050
- [CONCURRENCY_IDEMPOTENCY §16](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s14-17-numbering-jobs-revocation.md#16-authorization-and-revocation-races) RV-17, RV-18
- [TASK_PLAN §8](TASK_PLAN.md#8-owner-questions) P11Q-06, decided by DIR-085 OF-06
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (30): CAP-13; AU-14; AU-20–AU-21; AU-25; SX-04; TM-06; TM-57; TM-66; TM-68; TM-72–TM-73; D-SEC-19; H6-15; H7-15; H7-17; H8-18–H8-19; H8-21; H9-07; M-46; SM-19; SM-21; SM-24; TT-F25; TT-F30; PRQ-09; GAP-049–GAP-050; GAP-067

**EXPECTED MODULES / FILE AREAS:**

- the IAM and OPS modules' console commands and their tests; no route

**BUSINESS RULES:**

- N/A — SECURITY §2 and the decisions DIR-049, DIR-050 and DIR-083 OE-10 are the rule set, referenced, never restated

**ARCHITECTURE RULES:**

- console commands only, refusing to run outside a console; each one transaction where AU- says so; the end of recovery atomic under the OWNER role row (TT-F30)

**SECURITY / PERMISSION REQUIREMENTS:**

- the threats of [SECURITY §8](../05-security/SECURITY.md#8-threat-model) mapped to this Task, each with the controls its row names: TM-06, Owner-account takeover (AU-16, AU-18, AU-02, AU-03, AU-05, AU-15, AU-20, LG-07); TM-57, Operator abuse of the Owner-recovery command, which now also resets TOTP and unlinks Google, and of the OWNER-restoration command, whose honest use also reverses a removal of a pre-window Owner made legitimately during the compromise window, as a restored state does for an Owner removed legitimately between its backup and the window's start (AU-14, AU-20, AU-25, LG-07, H9-06, H9-07, H9-14, RV-04); TM-66, Theft or reuse of recovery codes — viewed on an unattended session, then used to replace the device (AU-19, AU-05, AU-20, LG-07); TM-68, Reset abuse — an Owner persuaded to reset an Admin's TOTP, or a reset or recovery link used to take an account over (AU-20, AU-12, AU-14); TM-72, One phone holding both the person's Google session and the authenticator, so that whoever holds it unlocked can pass both steps (AU-22, AU-20, AU-24, LG-07); TM-73, Anyone who can reach an account's second-factor step — its password holder, a browser still signed in to the person's linked Google account, or an abandoned authenticated session — failing codes on purpose to delay that account's sign-in (AU-05, AU-20, AU-24, AU-10, LG-07)
- no command sets, reads or reveals a password, TOTP secret or key; each records the operator's identity without a user row; SX-04: agents never run them on production

**COMPANY SCOPE AND FIELD PROJECTION:**

- N/A — accounts and roles only; no company data

**CONCURRENCY / IDEMPOTENCY:**

- the OWNER-restoration command refused when run twice for one account in one run (M-46); the window's start read from the run's audit and any other value refused

**AUDIT:**

- the business audit and the security events of each command, naming the credential link or the run as AU-14 and AU-25 state; alerts to the named operator and the Owner (LG-07)

**DATABASE IMPACT ASSESSMENT:**

- Schema change: NONE — uses TASK-005
- Migration required: NO
- Existing data transformation: NONE
- Direct production DB access: FORBIDDEN
- Data-loss risk: NONE KNOWN

**MODULARITY / EXTENSIBILITY:**

- Module boundary affected: IAM and OPS console layer
- Stable core kept provider-neutral: YES
- External/vendor-specific code isolated: YES — framework facilities or a small interface (ARCHITECTURE §2)
- Replaceable adapter/contract required: NO
- Provider lock-in risk: NONE beyond the Owner's baseline stack
- Future extension impact: LOW
- Speculative abstraction avoided: YES — no repository class per model, no generic workflow or rules engine (ARCHITECTURE §1)

**AUTH IMPACT:**

- Authentication change: PROPOSED — the designed recovery commands, nothing beyond them
- Authentication implementation: LARAVEL_NATIVE ([EXECUTION_CONTEXT](EXECUTION_CONTEXT.md))
- Auth profile: AICWDF-COMPAT-8 — passwords set only by the Owner through the link
- Google sign-in: N/A — unlinked by AU-14's link only
- Google registration behavior: N/A — no path registers an account
- Google credentials owner action required: NO
- Authorization change: NONE — OWNER_ONLY rules unchanged
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

- Context7: REQUIRED — the framework's console commands and encrypter key rotation (previous keys)
- Graphify: N/A — the codebase is young
- Browser E2E: N/A — no page
- Other: none
- Missing safe tool behavior: AUTO-INSTALL project-locally inside this Task (AICWDF §6.3) and record it in TOOLCHAIN; a paid tier or a privileged installation STOPS for the Owner (COST_POLICY)

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: N/A — the codebase is too young for a graph to add value; the impact is read from the diff and ARCHITECTURE §3–§4
- Pre-change questions: which accounts and links each command touches
- Expected affected areas: the IN SCOPE areas only

**CONTEXT7:**

- Topics to verify: the framework's `APP_PREVIOUS_KEYS` rotation and re-encryption
- Version-sensitive assumptions: none from memory

**UI / UX REQUIREMENTS:**

- Follow global design rules: N/A — console commands only; the Owner's link opens PG-003 of TASK-017
- Indonesian default: N/A — console commands only; the Owner's link opens PG-003 of TASK-017
- English support: N/A — console commands only; the Owner's link opens PG-003 of TASK-017
- Plain-language Indonesian: N/A — console commands only; the Owner's link opens PG-003 of TASK-017
- Language switch impact: NONE
- Mobile-first: N/A — console commands only; the Owner's link opens PG-003 of TASK-017
- Responsive: N/A — console commands only; the Owner's link opens PG-003 of TASK-017
- Adaptive: N/A — console commands only; the Owner's link opens PG-003 of TASK-017
- Visual breathing room: N/A — console commands only; the Owner's link opens PG-003 of TASK-017
- Comfortable density: N/A — console commands only; the Owner's link opens PG-003 of TASK-017
- shadcn/ui reuse: N/A — console commands only; the Owner's link opens PG-003 of TASK-017
- Design reference: N/A — console commands only; the Owner's link opens PG-003 of TASK-017
- Primary action: N/A — console commands only; the Owner's link opens PG-003 of TASK-017
- Secondary actions: N/A — console commands only; the Owner's link opens PG-003 of TASK-017
- Destructive actions: N/A — console commands only; the Owner's link opens PG-003 of TASK-017
- Redundancy constraints: N/A — console commands only; the Owner's link opens PG-003 of TASK-017
- Loading state: N/A — console commands only; the Owner's link opens PG-003 of TASK-017
- Empty state: N/A — console commands only; the Owner's link opens PG-003 of TASK-017
- Error state: N/A — console commands only; the Owner's link opens PG-003 of TASK-017

**ROUTE IMPACT:**

- none — console only

**INTERACTION CONTRACTS:**

- N/A — no page interaction

**NAVIGATION CONTRACT:**

- Parent: N/A — console only
- Entry: N/A — console only
- Back action: N/A — console only
- Cancel: N/A — console only
- Success destination: N/A — console only
- Error behavior: N/A — console only
- State preservation: N/A — console only
- Mobile behavior: N/A — console only

**ACCEPTANCE CRITERIA:**

1. The installation step creates exactly one first Owner and records the operator's identity, with no user row as actor (SM-24).
2. AU-14's command refuses every account that is not an active OWNER-role account, and its link's use has exactly the effects AU-14 lists; until a recovery run's recovery ends it issues a link without a named confirmer only to a pre-window Owner of the run, and refuses every other OWNER-role account unless the operator names a recovered pre-window Owner who confirmed it, checked under both rows' locks taken in id order (AU-14; SM-21).
3. The incident command records the compromise window once; every later step reads it and refuses another value; it refuses to start or to continue outside maintenance mode, runs the opening sweep, every account — one created after the run started included — and the closing sweep, and voids each account exactly once across a restart (AU-21 (a)–(d); RV-17).
4. The OWNER-restoration command restores pre-window Owners only, once per account per run, and refuses an account that first became Owner during the window (M-46); it refuses to run before the incident command has completed or once the run's recovery has ended, and refuses an account touched since the closing sweep; the end of the run's recovery is recorded only when no unreviewed OWNER-role account remains, and is refused otherwise with the accounts that block it (AU-25; RV-18).
5. Each halt condition stops the path and names the Owner's incident decision outside the application, first on a trusted state (DIR-083 OE-10).
6. The first Owner sets a password only through the path DIR-085 OF-06 decides — an OWNER_RECOVERY link issued by AU-14's command right after the installation step, with the Owner present —, and no other path exists (P11Q-06 (a); TASK_PLAN §8).

**TEST REQUIREMENTS:**

- Lint: TL-01
- Typecheck: TL-02
- Unit: the window and halt rules (TL-03)
- Feature: each command's refusals and effects (TL-04)
- Integration: the commands against PostgreSQL with synthetic accounts; the atomic end of recovery (TL-05)
- Authorization: no route reaches a command; OWNER_ONLY unchanged (TL-06)
- Route integrity: N/A — no route
- Interaction: N/A — no INT- contract in this Task
- Browser E2E: N/A — the link's page is TASK-017's
- Localization/language switch: N/A — console output in English for the operator
- Visual breathing room/density: N/A — no page in this Task
- Responsive: N/A — no page in this Task
- Accessibility: N/A — no page in this Task
- Production build: TL-13
- Security: SM-19, SM-21, SM-24 (TL-14)
- Performance: N/A — operator commands off the request path
- Migration: N/A — no migration or import code in this Task
- Other: GAP-050's M-46 case

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

- TASK-069 — when its other dependencies are DONE and its READY gate holds

**DO NOT DO:**

- add to V1 — a capability, page, route, field, integration, document type, report or paid service — or drop, defer or narrow a V1_REQUIRED requirement; a Task that would need either raises an Owner question and waits ([DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-20)
- access the VPS, production, its data or a credential, or put a secret, real data, a host detail or production configuration in the repository ([INFRASTRUCTURE](../09-operations/INFRASTRUCTURE.md) INF-01, INF-05; SECURITY SX-01, SX-04, SX-06; [PRODUCTION_DATA_SAFETY](../00-governance/PRODUCTION_DATA_SAFETY.md))
- add AI, model or tool attribution to a commit — the Owner's configured identity only ([AGENTS.md](../../AGENTS.md#git-commit-attribution); DIR-022)
- start before the Owner's execution authorization at OG-4, or mark DONE without the required tests passing and the completion evidence ([TASK_PLAN](TASK_PLAN.md#2-rules-of-the-plan); [DEFINITION_OF_DONE](../08-testing/DEFINITION_OF_DONE.md#2-the-checklist))
- weaken, skip or delete a valid test to obtain a green run, or adopt a paid tier without the Owner's COST_POLICY decision (DOD-05; [COST_POLICY](../00-governance/COST_POLICY.md))
- run any of these commands against production or a production backup
- add a recovery path, a role or a bypass the decisions do not state

# TASK-021 — Numbering, its settings page and the Owner's raise after a restore

Status: APPROVED | Updated: 2026-10-20 | Owner: Planning

Approval: [APPR-016](../00-governance/DECISION_LOG.md#appr-016--p11-task-plan-tasks-and-feature-coverage-matrix-approved), explicit Owner approval on 2026-10-20 (DIR-085 OF-07) of this document as committed in the first execution session's approval commit; the approved file hash and the verified conditions are recorded there. Approval changes lifecycle only, not implementation authorization.

Amendment: on 2026-10-25 the Owner's decision [DIR-095](../00-governance/DECISION_LOG.md#dir-095--task-013s-discoveries) (3) adds one IN SCOPE line: the `number_sequences` guard family registered with `ReconcileGuards`; no other line changes. It is a Level 1 record under CHANGE_CONTROL, as the Owner directs.

Written by the executor of run PC-5 on 2026-10-19 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99; the Owner approved P11 with the [Task plan](TASK_PLAN.md) at OG-4 (DIR-085 OF-07). **No Task starts before the freeze record, from which the execution authorization is in force (DIR-085 OF-09).** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** DONE

**RISK CLASS:** HIGH

**EXECUTOR:** AGENT

**OBJECTIVE:**

Number documents, projects and purchases per company, type and period, unique and monotonic, with the numbering schemes, their start, seeds, retired numbers and go-live as a state, the settings page PG-061, and the Owner's audited raise after a restore that OG-3 added.

**WHY THIS EXISTS:**

Business numbers are legal identity: C-10 makes them unique per company, type and period, monotonic and append-only; DIR-083 OE-11 adds the Owner's raise so that, after a restore, no numbered type issues again below the numbers already used on paper (GAP-064); the import's seeds and the cutover's go-live depend on it (GAP-058; PRQ-07).

**DEPENDS ON:**

- TASK-006 — Schema — ORG, PTY, CAT and DOC tables
- TASK-013 — Command envelope, command identity, idempotency and the lock order
- TASK-016 — Application shell, navigation and the company context
- TASK-018 — Two-step verification (TOTP) and recovery codes

**BLOCKS:**

- TASK-029 — Document engine — drafts, issue, revision, void, renditions and the composer
- TASK-032 — Projects — list, form, journey page and amendment
- TASK-074 — Backup tooling, the off-server copy and the drill procedures
- TASK-076 — Operator runbooks — Owner-account recovery, key compromise, Scenario B and key rotation
- TASK-077 — Cutover tooling — the numbering go-live command, the post-import refresh and the rehearsal procedure

**PARALLEL SAFE:** NO — it owns the counters every issuing Task draws from

**IN SCOPE:**

- the numbering schemes and sequences of DATABASE §4.2 with C-10's uniqueness and monotonicity; retired numbers; numbers tombstoned for discarded drafts as their actions state
- numbering concurrency NM-01–NM-10 and the lock LK-31 (CONCURRENCY_IDEMPOTENCY §14); the scenarios M-15, M-17, M-18 and M-31
- ConfigureNumbering and StartNumbering (ROUTE-136, ROUTE-137; INT-136, INT-137) on PG-061 with NAV-061, the start and seed confirmations and the scheme refusals ADMIN_FLOW §12 states (UXS-28–UXS-32)
- go-live as a state of numbering; the operator's one-time go-live command itself is TASK-077's (PRQ-07; HO-32; UXH-10)
- the Owner's raise after a restore (DIR-083 OE-11): through the numbering command (NX-01, locked at LK-31; ROUTE-137 as amended), recording per company, type and period the highest paper number; raising each affected counter, or creating the live period's, to the next number; once per recovery, never lowering one; no seed — no seed value or boundary moved and no defect alert —; audited with the incident (NM-03, NM-04; ADMIN_FLOW §12.1)
- the issuing types' draw at issue only (SF-ISSUE), the draw itself called by TASK-029 and the other issuing Tasks
- `DIR-095`: the `number_sequences` guard family of DATABASE §3.1 registered with `ReconcileGuards` with its formula, verified with the numbering concurrency of LK-31 (TASK-013's registry)

**OUT OF SCOPE:**

- issuing any document (TASK-029 and the issuing Tasks)
- the opening import's seeds (TASK-057, using this Task's seeding rule)
- the runbook step and the cutover (TASK-076, TASK-077, TASK-087)

**REFERENCES:**

- [DATABASE §4.2](../04-architecture/DATABASE/s04-02-org.md#42-org--organization)
- [CONCURRENCY_IDEMPOTENCY §14](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s14-17-numbering-jobs-revocation.md#14-numbering-concurrency)
- [CONCURRENCY_IDEMPOTENCY §7.3](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s07-statement-sequences.md#73-command-families-outside-ax-0137) NX-01, NX-13
- [ADMIN_FLOW §12](../07-ux-design/ADMIN_FLOW/s11-13-corrections-numbering-import.md#12-numbering-and-company-onboarding)
- [ROUTE_CONTRACTS](../03-workflows/ROUTE_CONTRACTS.md#companies-and-settings--perusahaan-penomoran-dokumen-pajak-pg-059-pg-061-pg-062-owner_only-j-md-01) ROUTE-136, ROUTE-137
- [NAVIGATION_CONTRACTS §2.6](../07-ux-design/NAVIGATION_CONTRACTS.md#26-reports-master-data-settings-and-owner-areas) NAV-061
- [TEST_STRATEGY §15](../08-testing/TEST_STRATEGY.md#15-migration-backup-and-restore-tests) — the raise's tests
- [RELEASE_PLAN §6](../10-release/RELEASE_PLAN.md#6-cutover-and-rollback) RBK-02; GLC-25
- [BACKUP_RECOVERY §4](../09-operations/BACKUP_RECOVERY.md#4-the-restore-procedure) step 7 — the users resume only after the raise
- the architecture challenge's sequences 12a–12b, 14a ([P11 gate record](evidence/P11_QUALITY_GATE.md#whole-system-architecture-challenge-sp-98))
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (44): CAP-08; REF-036; ROUTE-061; ROUTE-136–ROUTE-137; INT-136–INT-137; LK-31; NX-01; NX-13; NM-01–NM-10; M-15; M-17–M-18; M-31; HO-19; HO-32; HO-39; D-IA-13; PG-061; SCR-55; J-MD-01; UXS-28–UXS-32; UXH-10; NAV-061; GLC-25; PRQ-07; migration-rule DB24-09; GAP-016; GAP-058; GAP-064

**EXPECTED MODULES / FILE AREAS:**

- the ORG module's numbering service and actions; PG-061

**BUSINESS RULES:**

- C-10 and BR-DOC-02: unique per company, type and period, monotonic, append-only

**ARCHITECTURE RULES:**

- one numbering service; a number drawn only inside the issuing action's transaction; never reused

**SECURITY / PERMISSION REQUIREMENTS:**

- PG-061 and both commands under `companies.manage` (OWNER_ONLY), with step-up where ROUTE-136 and ROUTE-137 say

**COMPANY SCOPE AND FIELD PROJECTION:**

- per company: a scheme and its sequences belong to one company; the Owner's scope is every company (CS-01)

**CONCURRENCY / IDEMPOTENCY:**

- NM-01–NM-10 under LK-31; two issues never draw one number (M-15, M-17); the raise refused if it would lower a counter or if the same recovery already raised it (NX-01)
- NX-01's company-row lock: the numbering command — the start, the seeds, the scheme changes and the raise — locks its company row (LK-06) before LK-31, reads schemes, counters and the import batch rows under it and writes at LK-31; the Owner's commit request of a batch (NX-13) takes the same LK-06, so that the start and a scheme change are refused while a batch that is not ABORTED holds a VALID seed row of the type, serialized with that request (NM-03; M-18)

**AUDIT:**

- every scheme change, start and raise audited; the raise's event names the incident — the restore and its recovery point — (NM-03; LG-01)

**DATABASE IMPACT ASSESSMENT:**

- Schema change: NONE — uses TASK-006
- Migration required: NO
- Existing data transformation: NONE
- Direct production DB access: FORBIDDEN
- Data-loss risk: NONE KNOWN

**MODULARITY / EXTENSIBILITY:**

- Module boundary affected: ORG, tier 1, called by DOC and the issuing modules
- Stable core kept provider-neutral: YES
- External/vendor-specific code isolated: YES — framework facilities or a small interface (ARCHITECTURE §2)
- Replaceable adapter/contract required: NO
- Provider lock-in risk: NONE beyond the Owner's baseline stack
- Future extension impact: LOW
- Speculative abstraction avoided: YES — no repository class per model, no generic workflow or rules engine (ARCHITECTURE §1)

**AUTH IMPACT:**

- Authentication change: NONE
- Authentication implementation: LARAVEL_NATIVE — decided once ([EXECUTION_CONTEXT](EXECUTION_CONTEXT.md)), reused, never redesigned
- Auth profile: N/A — this Task does not touch authentication
- Google sign-in: N/A — not touched
- Google registration behavior: N/A — not touched
- Google credentials owner action required: NO
- Authorization change: NONE
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

- Context7: REQUIRED — PostgreSQL row locks and sequences behaviour as NM- states
- Graphify: OPTIONAL — once issuing Tasks exist
- Browser E2E: REQUIRED — PG-061's journey J-MD-01's numbering part and the raise's page path
- Other: none
- Missing safe tool behavior: AUTO-INSTALL project-locally inside this Task (AICWDF §6.3) and record it in TOOLCHAIN; a paid tier or a privileged installation STOPS for the Owner (COST_POLICY)

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: refreshed before the change when Graphify is available (TOOLCHAIN); otherwise a targeted search of the module
- Pre-change questions: which actions draw numbers and through which lock
- Expected affected areas: ORG and every issuing module

**CONTEXT7:**

- Topics to verify: PostgreSQL locking for gap-free counters in the installed version
- Version-sensitive assumptions: none from memory

**UI / UX REQUIREMENTS:**

- Follow global design rules: YES
- Indonesian default: YES
- English support: YES
- Plain-language Indonesian: YES — MSG- texts of ADMIN_FLOW §12
- Language switch impact: keeps unsent input
- Mobile-first: YES — a desktop page readable on the phone (IA §8.6)
- Responsive: YES
- Adaptive: N/A — one layout, readable on the phone
- Visual breathing room: REQUIRED
- Comfortable density: REQUIRED
- shadcn/ui reuse: the design system's forms and confirmations
- Design reference: ADMIN_FLOW §12.1 and IA §8.6's PG-061
- Primary action: the page's save or start
- Secondary actions: the raise, for the Owner after a restore
- Destructive actions: none — no control lowers or resets a counter (ADMIN_FLOW §12.1)
- Redundancy constraints: one start per scheme
- Loading state: PT-19
- Empty state: a company without a scheme
- Error state: the scheme refusals and C-10's refusal

**ROUTE IMPACT:**

- ROUTE-061 (PG-061's view), ROUTE-136, ROUTE-137

**INTERACTION CONTRACTS:**

- INT-136, INT-137

**NAVIGATION CONTRACT:**

- Parent: *Pengaturan* (NAV-061)
- Entry: *Pengaturan* › *Penomoran Dokumen*
- Back action: as NAV-061
- Cancel: leaves without saving, with the unsent-input warning (INT-009)
- Success destination: the change in place
- Error behavior: the refusal at its field
- State preservation: the selected company
- Mobile behavior: readable, actions on desktop

**ACCEPTANCE CRITERIA:**

1. Two concurrent issues of one type in one period draw two consecutive numbers and never the same one.
2. A number once drawn is never drawn again, also after a draft is discarded.
3. After a restore no numbered type issues again before the Owner's raise — the restore runbook's procedural stop, the users resuming only after the raise (BACKUP_RECOVERY §4 step 7; RBK-02), carried by TASK-074 and TASK-076 with no application mechanism added —; the raise sets each affected counter to the next number after the highest paper number per company, type and period, once per recovery, and refuses a lower value or a second raise for the same recovery (DIR-083 OE-11; NM-03, NM-04, NX-01; GLC-25; architecture challenge 12a, 12b).
4. The raise moves no seed value or boundary and raises no defect alert, and its audit event names the incident.
5. Only the Owner reaches PG-061 and its commands.
6. The start and a scheme change are refused while a batch that is not ABORTED holds a VALID seed row of the type, and the numbering command and the Owner's batch commit request serialize under LK-06 (NX-01; NX-13; M-18).
7. Sequences seeded by the opening import start after the last legacy number per company, type and period and stay unique and monotonic; an opening purchase whose legacy number does not fit its company's scheme draws its number at the commit, a fitting one keeping its legacy number (DIR-079 CB-01; DATABASE §24; C-10; architecture challenge 14a, with TASK-057).

**TEST REQUIREMENTS:**

- Lint: TL-01
- Typecheck: TL-02
- Unit: the scheme and period rules (TL-03)
- Feature: ROUTE-136 and ROUTE-137 with the raise (TL-04)
- Integration: two-connection draws (M-15, M-17, M-18); TEST_STRATEGY §15's raise cases on real PostgreSQL with a restored fixture and the controlled clock (FX-08) — each affected counter lifted, or a live period's counter created, to the number after the highest recorded, and the next issue drawing it; a lowering raise and a second raise in the same recovery refused without writing; the audit event naming the incident; no seed moved and no defect alert; an Admin refused; no number drawn twice (C-10) — (TL-05; architecture challenge 12a, 12b); the start and scheme refusals against a batch's VALID seed rows under LK-06, serialized with NX-13's commit request (M-18); and a sequence seeded after the last legacy number (14a)
- Authorization: OWNER_ONLY (TL-06)
- Route integrity: ROUTE-061, -136, -137 audited (TL-07)
- Interaction: INT-136, INT-137 (TL-08)
- Browser E2E: PG-061's path and the raise (TL-09; UXS-28–UXS-32)
- Localization/language switch: both languages (TL-10)
- Visual breathing room/density: TEST_STRATEGY §9
- Responsive: TL-11
- Accessibility: TL-12
- Production build: TL-13
- Security: step-up where stated (TL-14)
- Performance: the draw within its lock budget (TL-15)
- Migration: N/A — no migration or import code in this Task
- Other: GAP-016's period boundary at midnight WIB

**CRITERIA DISPOSITIONS:**

- Criterion 1: `tests/Integration/Numbering/AllocatorRacesTest.php` — three issues by three actors drawing 41, 42 and 43 from one counter (M-15); a second issuer held at the counter row until the first commits, then drawing the next number (NM-01; LK-31); two first issues of a new live period sharing one counter, the second waiting in the insert, numbers 1 and 2 (M-16; FU-07). Criterion 2: `AllocatorRacesTest.php` — an issue rolled back after its draw consuming nothing, the next issue taking that number (M-17) —; `tests/Integration/Numbering/NumberingPeriodsTest.php` — a lost counter answered SQ-18, never re-created below the numbers issued under it. Criterion 3: `NumberingPeriodsTest.php` — the raise over a restored fixture lifting September's counter and creating October's live counter to the number after the paper's highest, a lowering raise and a second raise for one recovery point refused writing nothing, another recovery point raising again, the next issues drawing the raised numbers —; `tests/Feature/Numbering/NumberingCommandsTest.php` — the same over HTTP, and a raise over numbers issued here answered SQ-18; the restore's procedural stop is TASK-074's and TASK-076's (BACKUP_RECOVERY §4 step 7). Criterion 4: `NumberingPeriodsTest.php` and `NumberingCommandsTest.php` — no seed value or boundary moved, no defect alert, the audit events naming the restore's reference and recovery point. Criterion 5: `tests/Authorization/NumberingAccessTest.php` — ROUTE-061, ROUTE-136 and ROUTE-137 under `companies.manage` (OWNER_ONLY), an Admin with every other capability answered by the not-found page writing nothing, a guest sent to sign-in —; `tests/Interaction/NumberingTest.php`. Criterion 6: `AllocatorRacesTest.php` — the start waiting at the company row held by a batch's commit request and then refused by the batch's VALID seed row (LK-06; NX-13; M-18) —; `NumberingCommandsTest.php` — a start refused while a batch holds seed rows. Criterion 7: `NumberingPeriodsTest.php` — a PURCHASE sequence seeded by the opening import after the last legacy number, its boundary the batch's cutoff, unique and monotonic (DB24-09; 14a); the opening purchase's kept legacy number is AX-28's, TASK-057's

**TEST CHECKLIST:**

- [x] Lint: ESLint and Pint PASS locally and in CI (TL-01)
- [x] Typecheck: TypeScript and Larastan/PHPStan at level `max` PASS locally and in CI (TL-02)
- [x] Relevant automated tests: Unit — the format, period, governs and live rules (TL-03); Feature — ROUTE-136 and ROUTE-137 with the raise (TL-04); Integration — the draws under real sessions, HO-19's periods with FX-08's clock, TEST_STRATEGY §15's raise (TL-05); PASS locally and in CI
- [x] Authorization: OWNER_ONLY on PG-061 and both commands (TL-06)
- [x] Route integrity: ROUTE-061, ROUTE-136 and ROUTE-137 in the route audit and the route list (TL-07)
- [x] Interaction behavior: INT-136, INT-137 with the replay of one `command_id` (TL-08)
- [x] Browser E2E if required: PG-061's path — no start before go-live, a scheme, the start through its statements and tick, the raise —, and a lost answer's "Coba Lagi" under its `command_id` with MSG-35 before unsent input is lost (IP-04; INT-009), on Chromium and WebKit (TL-09)
- [x] Responsive/adaptive if required: PG-061 at 320, 390, 768, 1,280 and 1,680 px without a sideways scroll, its commands on the desktop (TL-11)
- [x] Visual breathing room/density if UI: the design system's forms, tables and confirmation dialog, nothing hand-styled (TEST_STRATEGY §9)
- [x] Production build: TL-13 PASS locally and in CI; the numbering fixture local-only
- [x] Security checks if required: no step-up on ROUTE-136/137 (DIR-098 reading 1); OD-03's denials; the secret-like scan and both dependency audits PASS in CI (TL-14)
- [x] Database safety review: no migration and no schema change — TASK-006's tables, their C-10 trigger and seed floor —; local and CI databases only, no production connection
- [x] Final diff review: each rule against the contract and its sources, nothing invented; ER-06's one independent review, its points fixed and tested

**COMPLETION EVIDENCE:**

- Test outputs (TEST_STRATEGY §16), tested commit `3af812a`: fresh CI on GitHub's `ubuntu-24.04` runner, runs 40344048010 (push) and 40344052536 (pull request #26), the three jobs PASS in each — "Verify", "Browser" and "Performance and migration". On the Owner's workstation, 2026-10-26: the suites Unit 292, Architecture 50, Feature 193, Integration 925, Authorization 110, Routes 11, Interaction 20 and Performance 5; TypeScript, ESLint, `tests/Frontend/` 30, Pint, Larastan/PHPStan, the production build, and PG-061's E2E, Localization, Responsive and Accessibility specs with the shell's on Chromium and WebKit PASS
- Build result: `TL-13 Frontend build: PASS — Evidence: 3af812a, CI ubuntu-24.04 run 40344048010`
- Route evidence: TL-07's route audit PASS — ROUTE-061 `GET /pengaturan/penomoran`, ROUTE-136 `PUT /pengaturan/penomoran/{type}` and ROUTE-137 `POST /pengaturan/penomoran/{type}/mulai`, each `capability:companies.manage` —; `tests/Routes/RouteAuditTest.php`, `tests/Feature/BootTest.php`
- Interaction evidence: `tests/Interaction/NumberingTest.php` (INT-136, INT-137)
- Browser evidence: `TL-09 E2E/browser: PASS — Evidence: 3af812a, CI ubuntu-24.04 run 40344048010`; TL-10, TL-11 and TL-12 for PG-061 in the same run
- Localization evidence: PG-061 in both languages (`tests/Browser/Localization/numbering.spec.ts`) (DOD-16); 86 keys added through `lang/registry/added-keys.json` for the Owner's review, 21 quoting ADMIN_FLOW §12's canonical Indonesian
- Cost evidence/exception if applicable: ADDITIONAL RECURRING COST CHANGE: NONE — nothing installed
- Files changed: `app/Modules/ORG/` — `Services/` (the allocator, the state NM-03 reads, the seeds, go-live's interface), `Actions/` (`ConfigureNumbering`, `StartNumbering`), `Data/`, `Enums/SequenceType.php`, `Queries/NumberingArea.php`, `Http/Controllers/NumberingController.php` —; `app/Support/Guards/` and `app/Console/Commands/ReconcileGuards.php` (the verified family); `app/Providers/AppServiceProvider.php` (go-live's binding); `routes/web.php`, `routes/fixtures.php`; `resources/js/Pages/Settings/Numbering.tsx`, `resources/js/lib/navigation.ts`; `lang/`; `tests/`; DIR-098's records and TOOLCHAIN's row; this file, TASK_PLAN, CURRENT_HANDOFF and CHANGELOG
- Commit SHA: `197a472`, `018c9e3`, `85e32a6`, `156b258`, `e3f9263`, `b657d5d` and `3af812a`, the Owner's identity, no signature and no AI attribution; this record's commit follows them on `task/TASK-021-numbering`
- Notes: criteria as CRITERIA DISPOSITIONS states. Environment: the Owner's Windows workstation, Claude Code Desktop, 2026-10-26; Context7 consulted for PostgreSQL's row locks, READ COMMITTED re-evaluation and sequences (TOOLCHAIN's TASK-021 row); CI the final proof. The Owner decided at one checkpoint DIR-098 — D1 the format's tokens, D2 the raise's incident and once per recovery point, readings (1)–(8) — and, on ER-06's finding, D1a, a format holding its period's tokens. ER-06's one review found two HIGH points — formatted numbers repeating across periods, answered by D1a; first use read from the lock's answer, which a restart's re-derived lock set hides, now read from the increment —, and MEDIUM and LOW points — the recording day from the command's own clock, the races with distinct actors proving the wait at the counter row, the start before the go-live day, the raise's proof before it creates a counter, the seed's insert-or-lock, the recovery point in WIB, a first use's own time and actor —, each fixed and tested. Codex's review of pull request #26 found two P2 points on PG-061 — no "Coba Lagi" for a lost answer, which left a form frozen; no MSG-35 before unsent input was lost to a change of company or type or a visit away —, both fixed and tested in the E2E spec. A seed meeting a counter the raise moved may alert a defect it cannot tell from a draw, kept for TASK-057, which seeds; the start's boundary day and its periods' day are read a moment apart, a midnight between them leaving the next period to be seeded. The counters' verification runs in `guards:reconcile` and after the numbering tests, not after every integration test, the other suites' factories writing numbered rows with no counter until the issuing Tasks draw through the allocator. UXS-28 and UXS-29 run once PG-013 and PG-043 exist (TASK-032, TASK-049); the allocator's issue path is called by TASK-029, TASK-032, TASK-037 and the other issuing Tasks; go-live is unrecorded until TASK-077 (DIR-085 OF-04). Readings within the approved text are listed in CURRENT_HANDOFF. The READY gates of the Tasks TASK-021 blocks were re-checked at this DONE: TASK-029 waits on TASK-022 and TASK-025, TASK-032 on TASK-025 and TASK-027, TASK-074 on TASK-022, TASK-029 and TASK-072, TASK-076 on TASK-074, TASK-077 on P11Q-04. Each non-applicable item above carries its reason; TASK_PLAN, this file and CURRENT_HANDOFF updated for DONE

**SAFE NEXT TASK:**

- TASK-029 — when its other dependencies are DONE and its READY gate holds

**DO NOT DO:**

- add to V1 — a capability, page, route, field, integration, document type, report or paid service — or drop, defer or narrow a V1_REQUIRED requirement; a Task that would need either raises an Owner question and waits ([DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-20)
- access the VPS, production, its data or a credential, or put a secret, real data, a host detail or production configuration in the repository ([INFRASTRUCTURE](../09-operations/INFRASTRUCTURE.md) INF-01, INF-05; SECURITY SX-01, SX-04, SX-06; [PRODUCTION_DATA_SAFETY](../00-governance/PRODUCTION_DATA_SAFETY.md))
- add AI, model or tool attribution to a commit — the Owner's configured identity only ([AGENTS.md](../../AGENTS.md#git-commit-attribution); DIR-022)
- start before the Owner's execution authorization at OG-4, or mark DONE without the required tests passing and the completion evidence ([TASK_PLAN](TASK_PLAN.md#2-rules-of-the-plan); [DEFINITION_OF_DONE](../08-testing/DEFINITION_OF_DONE.md#2-the-checklist))
- weaken, skip or delete a valid test to obtain a green run, or adopt a paid tier without the Owner's COST_POLICY decision (DOD-05; [COST_POLICY](../00-governance/COST_POLICY.md))
- offer a control that lowers or resets a counter
- treat the raise as a seed or move a seed boundary

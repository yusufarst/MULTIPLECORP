# TASK-077 — Cutover tooling — the numbering go-live command, the post-import refresh and the rehearsal procedure

Status: APPROVED | Updated: 2026-10-20 | Owner: Planning

Approval: [APPR-016](../00-governance/DECISION_LOG.md#appr-016--p11-task-plan-tasks-and-feature-coverage-matrix-approved), explicit Owner approval on 2026-10-20 (DIR-085 OF-07) of this document as committed in the first execution session's approval commit; the approved file hash and the verified conditions are recorded there. Approval changes lifecycle only, not implementation authorization.

Written by WF-PC5-W's writer of bundle W4 of run PC-5 on 2026-10-19 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99; the Owner approved P11 with the [Task plan](TASK_PLAN.md) at OG-4 (DIR-085 OF-07). **No Task starts before the freeze record, from which the execution authorization is in force (DIR-085 OF-09).** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** BLOCKED — waiting on P11Q-04

**RISK CLASS:** HIGH

**EXECUTOR:** AGENT

**OBJECTIVE:**

Build the cutover tooling the human operator runs and the procedures that use it: the operator's one-time go-live command, which records go-live once as the number allocator's setting kept with the deployment, after every company's seeds have committed (PRQ-07; HO-32); the post-import refresh of the planner statistics with the re-check of the hot plans on the imported data (PRQ-08; HO-36); the import rehearsal procedure on staging with synthetic batches shaped like the legacy data (IMP-13); and the cutover runbook that orders CUT-01–CUT-08 with the import's operational rules and the rollback decisions of RBK-01–RBK-06.

**WHY THIS EXISTS:**

Opening stock and open receivables can be counted twice at migration, and a moving baseline corrupts day one ([GAP-010](../00-governance/GAP_REGISTER.md#gap-010--opening-stock-and-open-receivables-can-be-counted-twice-at-migration)); the Owner chose a short, announced cutover freeze sized from the measured import rehearsal ([DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-06). HO-32 orders the cutover so that no company issues before its counters are seeded and go-live is recorded once — until it is, the numbering command neither starts nor seeds (NM-03) — and the architecture challenge's sequence 14b requires the go-live command to run after the commit. HO-36 asks for fresh statistics and checked plans before the first live day, and PRQ-07 and PRQ-08 ask P11 for both.

**DEPENDS ON:**

- TASK-021 — Numbering, its settings page and the Owner's raise after a restore
- TASK-057 — Opening import — preparation, dry run, sign-off and commit
- TASK-072 — Deployment definition, health and smoke checks, maintenance mode and mixed-version safeguards
- the Owner's answer to P11Q-04 recorded, with what it calls for done — a step run or a narrow amendment approved ([TASK_PLAN §8](TASK_PLAN.md#8-owner-questions)) — BLOCKED until then

**BLOCKS:**

- TASK-081 — Staging drills and rehearsals — failure, restore, Scenario B and the import rehearsal
- TASK-087 — Cutover and go-live

**PARALLEL SAFE:** NO — it adds the one-time go-live command TASK-021's numbering reads and the cutover runbook TASK-081 rehearses and TASK-087 runs

**IN SCOPE:**

- **The go-live command** (PRQ-07; HO-32; UXH-10): an operator-only console command, never a web route, that records go-live — the day this database became the system of record (NM-03) — once, as the number allocator's setting kept with the deployment that nobody changes afterwards (INFRASTRUCTURE §4.2), a second recording refused; what it records is what TASK-021's numbering reads as go-live; GLC-07's production check finds the setting unset until CUT-07 (TASK-084); where the setting is kept follows INFRASTRUCTURE §4.2 as P11Q-04's answer defines it (below), never a silent schema change (TASK_PLAN §4)
- **The post-import refresh** (PRQ-08; HO-36; IMP-10): the planner statistics refreshed after the commit, and the registered hot queries of PERFORMANCE §12 explained on the imported data before go-live, read queries only — never a data-changing statement on production — and plans reported without data values (PFM-03), for the operator to judge against the plans recorded at 1x, 10x and 100x
- **The import rehearsal procedure** (IMP-13; GAP-010): on staging with synthetic batches shaped like the legacy data — FX-09 at the volume the data steward reports, counts only — to measure the commit's duration and size the chunks so that each commits well within its job's timeout (JB-07; IMP-06), and to size the freeze from it with the post-import backup's and the production restore drill's measured durations (OPI-25; RELEASE_PLAN §7) and the off-hours window they wait for (BACKUP_RECOVERY §5); the generator of those batches from counts alone, never from a real row (RL-05; DEP-05)
- **The cutover runbook** (CUT-01–CUT-08), each step with its people as RELEASE_PLAN §6 names them: the cutover agreed — the cutoff, the freeze window outside the busiest days with its length from IMP-13, its announcement to the clients in advance, the batches' order and the people (CUT-01; DIR-083 OE-06); production set up before any data with GLC-06, GLC-07, GLC-11 and GLC-24 met first, the installation step, then the one `APP_KEY` rotation, before the masters, accounts, roles and grants, tax configuration and the numbering schemes as they were (CUT-02; HO-32); each batch prepared, dry-run, reconciled and signed on the real data through PG-058, repeated until it passes (CUT-03); the freeze, the final snapshot at the cutoff and its batches dry-run, reconciled and jointly signed (CUT-04); a verified backup before the commit, then the Owner's commit in IMP-03's order, a stopped batch resumed (CUT-05); reconciliation, statistics and reservations (CUT-06); the production restore drill of a recovery point taken after CUT-06, with its paired key, timed (GLC-04; RELEASE_PLAN §7); go-live recorded once by the operator after every company's seeds have committed, then the Owner's starts on PG-061 for the types no import covers (CUT-07; architecture challenge 14b); open for business — the first issue per company, the heartbeat and alerts watched, the freeze ended (CUT-08); no delta batch (OE-06)
- **The import's operational rules as runbook checks** (IMP-01–IMP-13), the import feature itself being TASK-057's: what is imported and what stays archive, the company masters and schemes set up first (IMP-01); one cutoff and snapshot per batch, historical references apart from current effects (IMP-02); the batches' order from their keys (IMP-03); the dry run on production through PG-058 by the Admin and the Owner, never on staging and never by an agent (IMP-04); the two sign-offs and the Owner's commit behind the confirmation and step-up, the validation run again at the commit request (IMP-05); chunks, resume and abort (IMP-06); the seeds at the cutover — kept or drawn purchase numbers, the starts for uncovered types (IMP-07); reservations re-created through the ordinary commands (IMP-08); units and serials (IMP-09); statistics and plans (IMP-10); `ReconcileGuards` clean and the committed facts reconciled to the signed control totals, signed by the validators (IMP-11); raw files private in the application's storage (IMP-12); the rehearsal (IMP-13)
- **The cutover's rollback decisions** (RBK-01–RBK-06): a failed step stops for the operator's and the Owner's decision (RBK-04); a wrong opening fact corrected on its record by contras and reversals by import identity, a committed batch never imported again (RBK-03; CM-33); data recovery only by TASK-076's restore runbook on the Owner's decision, with the raise before any numbered issue (RBK-02); a code rollback and an interrupted migration by their runbooks (RBK-01; RBK-05); the second application never touched (RBK-06)
- **A deployment inside the cutover window** follows TASK-072's runbook — expand-only migrations, payload versions and one-release command validity, the drain before it (HO-31; HO-34)
- **Held for the Owner's answer to P11Q-04, built here once the answer and its definitions are approved, which comes before this Task is READY** ([TASK_PLAN §8](TASK_PLAN.md#8-owner-questions)): under option (a), where the go-live setting is stored and which actor the go-live command's audit event carries, as the narrow amendment defines them (INFRASTRUCTURE §4.2; HO-32; PRQ-07; RG-11) — the setting kept there, and the command writing that event; under option (b), as the Owner decides each (DIR-083 OE-20)

**OUT OF SCOPE:**

- running the go-live command, the refresh, an import or any cutover step on staging or production — the operator's, the Owner's and the validators' (rehearsed in TASK-081, run in TASK-087)
- the opening import feature — PG-058's preparation, dry run, report, sign-offs, commit, progress, resume and abort (TASK-057; PRQ-06) — and numbering with PG-061, the starts and the raise (TASK-021)
- the backup and drill tooling (TASK-074), the deployment definition (TASK-072) and the restore and rollback runbooks (TASK-076)
- any real legacy data, extract or file of the data steward (IMP-12; RL-05)

**REFERENCES:**

- [RELEASE_PLAN §5](../10-release/RELEASE_PLAN.md#5-the-opening-import) IMP-01–IMP-13; [§6](../10-release/RELEASE_PLAN.md#6-cutover-and-rollback) CUT-01–CUT-08, RBK-01–RBK-06; [§7](../10-release/RELEASE_PLAN.md#7-rehearsal-and-drills); [§8](../10-release/RELEASE_PLAN.md#8-uat) UAT-01, UAT-02; [§9](../10-release/RELEASE_PLAN.md#9-the-go-live-checklist) GLC-04, GLC-07, GLC-14; [§10](../10-release/RELEASE_PLAN.md#10-production-release-requirements-for-p11) PRQ-06–PRQ-08; [§13](../10-release/RELEASE_PLAN.md#13-owner-questions) P10Q-01
- [CONCURRENCY_IDEMPOTENCY §19](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s19-20-handoff-traceability.md#19-handoff-obligations) HO-31–HO-34, HO-36; [§14](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s14-17-numbering-jobs-revocation.md#14-numbering-concurrency) NM-03, NM-08; [§15](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s14-17-numbering-jobs-revocation.md#15-jobs-scheduler-and-queue) JB-07, JB-09
- [INFRASTRUCTURE §4.2](../09-operations/INFRASTRUCTURE.md#42-configuration) — the go-live setting; [§5](../09-operations/INFRASTRUCTURE.md#5-the-deployment-path)
- [DATABASE §24](../04-architecture/DATABASE/s20-24-index-storage-migration.md#24-migration-and-opening-data); [WORKFLOWS §5.8](../03-workflows/WORKFLOWS/s05-07-08-completion-migration.md#58-migration-adjacent-workflow) WF-MIG-01
- [ADMIN_FLOW §12](../07-ux-design/ADMIN_FLOW/s11-13-corrections-numbering-import.md#12-numbering-and-company-onboarding), [§13](../07-ux-design/ADMIN_FLOW/s11-13-corrections-numbering-import.md#13-opening-import); [§16](../07-ux-design/ADMIN_FLOW/s16-17-handoff-traceability.md#16-handoff-obligations) UXH-10
- [PERFORMANCE §12](../06-api-performance/PERFORMANCE.md#12-index-register-and-verification-procedure); [PERFORMANCE_TARGETS](../08-testing/PERFORMANCE_TARGETS.md#3-the-measured-targets) PFM-03
- [TEST_STRATEGY §4](../08-testing/TEST_STRATEGY.md#4-environments-and-data) FX-09; [§15](../08-testing/TEST_STRATEGY.md#15-migration-backup-and-restore-tests) — the opening import's tests
- [GAP_REGISTER](../00-governance/GAP_REGISTER.md#gap-010--opening-stock-and-open-receivables-can-be-counted-twice-at-migration) GAP-010
- [DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-06, OE-11
- Architecture challenge — the sequence 14b of the [P11 gate record](evidence/P11_QUALITY_GATE.md#whole-system-architecture-challenge-sp-98), their assertions carried into this Task's acceptance criteria and tests
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (39): CM-33; M-33; HO-31–HO-34; HO-36; UXH-10; IMP-01–IMP-13; CUT-01–CUT-08; RBK-01–RBK-06; PRQ-07–PRQ-08; GAP-010; GAP-068

**EXPECTED MODULES / FILE AREAS:**

- the go-live console command beside TASK-021's numbering service; the post-import refresh command; the generator of FX-09-shaped rehearsal batches; their tests under `tests/`; the cutover runbook and the import rehearsal procedure in the repository's operations documentation

**BUSINESS RULES:**

- HO-32's order and NM-03's meaning of go-live; C-10 — numbers unique and monotonic per company, type and period, legacy numbers included — unchanged
- BR-XD-06 and L-50 — opening facts are migration facts, old history stays archive and opening credit is never live-period Cash-In (IMP-01); CM-33 — a wrong opening fact is corrected by contras and reversals by import identity

**ARCHITECTURE RULES:**

- the go-live command is console-only and operator-only, and records once; the import commits only through the ordinary actions with deterministic command identities (AX-28; IMP-06) — TASK-057's
- the refresh only reads and refreshes statistics; nothing in this Task writes business data
- every cutover step runs through the application or the operator's console on the production host, never by an agent and never as direct database access (PRODUCTION_DATA_SAFETY)

**SECURITY / PERMISSION REQUIREMENTS:**

- the go-live command and the refresh run only by the operator; no route reaches them (INF-02)
- raw legacy files are uploaded by the Admin to the application's private storage as import sources, never into the repository, a ticket or an agent's reach (IMP-12; FL-06); the rehearsal uses synthetic batches only (RL-05)
- the commit stays behind `import.commit`, OWNER_ONLY, the irreversible confirmation and the step-up (OD-13; IP-08; IP-10) — TASK-057's

**COMPANY SCOPE AND FIELD PROJECTION:**

- the go-live setting is one for the whole number allocator and carries no company data; each company's seeds and starts stay per company (NM-03; TASK-021)
- the import's scope rules are TASK-057's — a collision outside the preparer's scope refused with MSG-18 alone (DP-18), the Owner's commit over every company (CS-01); the refresh's plans carry no company record or data value

**CONCURRENCY / IDEMPOTENCY:**

- go-live recorded once, a second recording refused (HO-32); the seeds' counters are taken at LK-31 by TASK-021's numbering, never by this command
- the commit's chunks replay under deterministic command identities, a retried chunk finding its committed rows done and a stopped batch resumed by another Owner (CI-01; CI-08; JB-07; JB-09; M-33; AX-28; BD-11) — the rehearsal measures them; the refresh takes no lock of the canonical order

**AUDIT:**

- the import's commit and sign-offs are audited by TASK-057's actions (LG-01; L-33); the go-live recording is entered, with its time and the operator by role, in the cutover record and the release record (REL-12); a business audit event for the go-live command, and its actor rule, are not stated by the sources — RG-11's operator rule names AU-14's, AU-21's and AU-25's commands and the installation step —; the event is written as P11Q-04's answer defines it, built here once the answer is approved, which comes before this Task is READY (IN SCOPE)

**DATABASE IMPACT ASSESSMENT:**

- Schema change: NONE — no table or column
- Migration required: NO
- Existing data transformation: NONE
- Direct production DB access: FORBIDDEN
- Data-loss risk: NONE KNOWN

**MODULARITY / EXTENSIBILITY:**

- Module boundary affected: ORG's numbering for the go-live command; OPS for the refresh and the rehearsal generator
- Stable core kept provider-neutral: YES
- External/vendor-specific code isolated: YES — framework facilities or a small interface (ARCHITECTURE §2)
- Replaceable adapter/contract required: NO
- Provider lock-in risk: NONE beyond the Owner's baseline stack
- Future extension impact: LOW
- Speculative abstraction avoided: YES — no migration framework or delta importer; the freeze needs none (DIR-083 OE-06)

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

- Context7: REQUIRED — PostgreSQL's statistics refresh and `EXPLAIN` at the recorded version; the framework's console commands and settings
- Graphify: OPTIONAL — the import and numbering modules exist by now; a targeted search of AX-28's commit path and the allocator serves otherwise
- Browser E2E: N/A — no page; the import's journey CJ-16 is TASK-064's
- Other: FX-09 at the data steward's counts; staging for the rehearsal, run by the operator in TASK-081
- Missing safe tool behavior: AUTO-INSTALL project-locally inside this Task (AICWDF §6.3) and record it in TOOLCHAIN; a paid tier or a privileged installation STOPS for the Owner (COST_POLICY)

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: refreshed before the change when Graphify is available (TOOLCHAIN); otherwise a targeted search of the allocator, NM-03's go-live reads and AX-28
- Pre-change questions: where TASK-021 reads go-live; which seeds each batch writes; which queries PERFORMANCE §12 registers as hot
- Expected affected areas: ORG's numbering, OPS's operator commands and the operations documentation

**CONTEXT7:**

- Topics to verify: refreshing planner statistics and reading plans without executing data-changing statements; the framework's one-time console command patterns
- Version-sensitive assumptions: none from memory

**UI / UX REQUIREMENTS:**

- Follow global design rules: N/A — console commands and documents only; the Owner's starts are PG-061's (TASK-021)
- Indonesian default: N/A — console commands and documents only
- English support: N/A — console commands and documents only
- Plain-language Indonesian: N/A — console commands and documents only
- Language switch impact: NONE
- Mobile-first: N/A — console commands and documents only
- Responsive: N/A — console commands and documents only
- Adaptive: N/A — console commands and documents only
- Visual breathing room: N/A — console commands and documents only
- Comfortable density: N/A — console commands and documents only
- shadcn/ui reuse: N/A — console commands and documents only
- Design reference: N/A — console commands and documents only; UXH-10's presentation is PG-061's
- Primary action: N/A — console commands and documents only
- Secondary actions: N/A — console commands and documents only
- Destructive actions: N/A — no page; go-live is irreversible by design and recorded once
- Redundancy constraints: N/A — console commands and documents only
- Loading state: N/A — console commands and documents only
- Empty state: N/A — console commands and documents only
- Error state: N/A — no page; a second go-live is refused with its reason

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

1. The go-live command runs only from the console, records go-live once, refuses a second recording, and no route reaches it (HO-32; PRQ-07).
2. On the fixture, before go-live the numbering command neither starts nor seeds and nothing is created on demand; after it, the Owner's start works for a type no import covers and each company's first issue draws its seeded number (NM-03; UXH-10).
3. The cutover runbook puts the go-live command after every batch is COMMITTED and reconciled, every company's seeds have committed and the production restore drill is recorded (CUT-05–CUT-07; GLC-04; architecture challenge 14b).
4. The refresh updates the planner statistics and reports the registered hot queries' plans on the imported data without running a data-changing statement or printing a data value (HO-36; PRQ-08; PFM-03).
5. The rehearsal generator produces FX-09-shaped synthetic batches from counts alone, and the procedure yields the commit's duration, a chunk size within JB-07's timeout and the freeze's length with OPI-25's durations (IMP-13; IMP-06).
6. The cutover runbook orders CUT-01–CUT-08 with each step's people, the freeze announced in advance outside the busiest days and no delta batch, and the rollback decisions of RBK-01–RBK-06 — a wrong opening fact corrected by contras and reversals, never imported again (DIR-083 OE-06; RBK-03).
7. No runbook, procedure or tool holds real legacy data, a secret or a host detail (IMP-12; RL-05).
8. Once P11Q-04 (a)'s definitions are approved, the go-live command keeps the setting where they define it and writes the audit event with the actor they name, once; a second run is refused and writes nothing (INFRASTRUCTURE §4.2; HO-32; RG-11; TASK_PLAN §8).

**TEST REQUIREMENTS:**

- Lint: TL-01
- Typecheck: TL-02
- Unit: the once-only rule of the go-live command and the generator's shape from counts (TL-03)
- Feature: the command's refusal of a second run and outside the console; the numbering states before and after go-live; the setting's store and the audit event's actor as P11Q-04's answer defines them (TL-04)
- Integration: on real PostgreSQL with TASK-021's numbering and TASK-057's import over FX-09 — seeds committed, go-live recorded after the commit, the first issue per company drawing its seeded number, the refresh on the imported fixture (TL-05; TEST_STRATEGY §15's numbering at the cutover)
- Authorization: no route reaches the command or the refresh (TL-06)
- Route integrity: N/A — no route added or changed
- Interaction: N/A — no INT- contract in this Task
- Browser E2E: N/A — no page in this Task
- Localization/language switch: N/A — console output for the operator
- Visual breathing room/density: N/A — no page in this Task
- Responsive: N/A — no page in this Task
- Accessibility: N/A — no page in this Task
- Production build: TL-13
- Security: SM-15's secret scan; no data value in the refresh's output (TL-14)
- Performance: PFM-03's plans re-checked on the imported fixture at 10x and 100x through the refresh's report (TL-15)
- Migration: TL-16 — the opening import's dry run and commit on FX-09 with go-live after the commit (TEST_STRATEGY §15)
- Other: GAP-010's reconciliation after the rehearsal's commit — stock per company equal to its opening lots, receivables to the imported remainders, credits to the signed opening evidence — with `ReconcileGuards` clean

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

- TASK-081 — when its other dependencies are DONE and its READY gate holds

**DO NOT DO:**

- add to V1 — a capability, page, route, field, integration, document type, report or paid service — or drop, defer or narrow a V1_REQUIRED requirement; a Task that would need either raises an Owner question and waits ([DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-20)
- access the VPS, production, its data or a credential, or put a secret, real data, a host detail or production configuration in the repository ([INFRASTRUCTURE](../09-operations/INFRASTRUCTURE.md) INF-01, INF-05; SECURITY SX-01, SX-04, SX-06; [PRODUCTION_DATA_SAFETY](../00-governance/PRODUCTION_DATA_SAFETY.md))
- add AI, model or tool attribution to a commit — the Owner's configured identity only ([AGENTS.md](../../AGENTS.md#git-commit-attribution); DIR-022)
- start before the Owner's execution authorization at OG-4, or mark DONE without the required tests passing and the completion evidence ([TASK_PLAN](TASK_PLAN.md#2-rules-of-the-plan); [DEFINITION_OF_DONE](../08-testing/DEFINITION_OF_DONE.md#2-the-checklist))
- weaken, skip or delete a valid test to obtain a green run, or adopt a paid tier without the Owner's COST_POLICY decision (DOD-05; [COST_POLICY](../00-governance/COST_POLICY.md))
- run the go-live command, the refresh, an import or any cutover step on staging or production, or touch legacy data (INF-01; RL-05; IMP-12)
- give the go-live command a web route, or let it record go-live twice or change it afterwards (HO-32; INFRASTRUCTURE §4.2)
- import a committed batch again, add a delta batch, or correct a wrong opening fact by importing (RBK-03; CM-33; DIR-083 OE-06)
- let the refresh run a data-changing statement or print a data value (PFM-03; PRODUCTION_DATA_SAFETY)

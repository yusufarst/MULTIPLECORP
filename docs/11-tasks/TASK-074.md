# TASK-074 — Backup tooling, the off-server copy and the drill procedures

Status: APPROVED | Updated: 2026-10-20 | Owner: Planning

Approval: [APPR-016](../00-governance/DECISION_LOG.md#appr-016--p11-task-plan-tasks-and-feature-coverage-matrix-approved), explicit Owner approval on 2026-10-20 (DIR-085 OF-07) of this document as committed in the first execution session's approval commit; the approved file hash and the verified conditions are recorded there. Approval changes lifecycle only, not implementation authorization.

Written by WF-PC5-W's writer of bundle W4 of run PC-5 on 2026-10-19 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99; the Owner approved P11 with the [Task plan](TASK_PLAN.md) at OG-4 (DIR-085 OF-07). **No Task starts before the freeze record, from which the execution authorization is in force (DIR-085 OF-09).** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** TODO

**RISK CLASS:** HIGH

**EXECUTOR:** AGENT

**OBJECTIVE:**

Build, with placeholder values only, the backup tooling the human operator runs: the coherent recovery point of a PostgreSQL dump and its private files under one identifier, the manifest with checksums and a truthful status, the daily and pre-deployment runs, rotation, retention and cleanup strictly by retention, each generation paired with its key-register entry, the encrypted off-server copy of each verified daily generation with its own verification, retention and failure alert — and the restore procedure's tooling with the restore, copy and failure drill procedures, each drill recording what AC-18 and AC-19 ask.

**WHY THIS EXISTS:**

CAP-16 requires operational readiness on the existing VPS with multiple recovery points, verified retention, restore proof and failure monitoring, and AC-18 and AC-19 define its evidence; V1_SCOPE's local-backup contract hands BK-01–BK-12 to P9 (OWN-06; REF-060), and [BACKUP_RECOVERY](../09-operations/BACKUP_RECOVERY.md#1-the-decision-and-its-limits) designs them as BKR-01–BKR-09. GAP-011 warns that a database restore alone cannot recover usable business documents, and the Owner's [DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-09 adds the off-server copy that narrows RISK-001 (GAP-063) — the architecture challenge's sequences 13a–13c. PRQ-10 asks for the drill procedures beside the tooling.

**DEPENDS ON:**

- TASK-013 — Command envelope, command identity, idempotency and the lock order
- TASK-018 — Two-step verification (TOTP) and recovery codes
- TASK-021 — Numbering, its settings page and the Owner's raise after a restore
- TASK-022 — Jobs, files and the document renderer
- TASK-029 — Document engine — drafts, issue, revision, void, renditions and the composer
- TASK-072 — Deployment definition, health and smoke checks, maintenance mode and mixed-version safeguards

**BLOCKS:**

- TASK-075 — Monitoring — the business heartbeat, alerts and the uptime check
- TASK-076 — Operator runbooks — Owner-account recovery, key compromise, Scenario B and key rotation
- TASK-079 — Staging measurements — capacity, Argon2id, durations and the disk budget
- TASK-081 — Staging drills and rehearsals — failure, restore, Scenario B and the import rehearsal
- TASK-084 — Production preparation — configuration, outbound HTTPS, branch protection, backups and the off-server copy

**PARALLEL SAFE:** NO — it writes the backup, copy and restore tooling that the runbooks, the measurements, the drills and the production preparation use (TASK-076, TASK-079, TASK-081, TASK-084)

**IN SCOPE:**

- **The recovery point** (BKR-01; BK-01; BK-02): a logical PostgreSQL dump in the custom format, consistent at one snapshot, named by timestamp and the deployed commit, with the private file disk — evidence, renditions, identity assets, product images — captured after the dump's snapshot, so that every file row the dump holds has its bytes; exports and temporary files left out (GT-06)
- **The manifest and the status** (BK-07; BKR-02; BKR-03): each part's SHA-256 and size, verified after writing and before cleanup; a run successful only when it completed, its checksums verify and its manifest lists every part, anything else reported failed; a checksum never taken as restore proof
- **The runs** (BK-03; BK-04): before every deployment, its verified completion the gate of REL-08 in TASK-072's runbook; daily at `<BACKUP_TIME>`, outside business hours and clear of `ReconcileGuards`' 02:00 WIB snapshot (HO-28), the time OPI-23 records in TASK-084; a missed or stale run — no verified backup within 26 hours — raised as a signal for TASK-075's alerts (BK-10)
- **Generations, retention and cleanup** (BK-05; BK-06; BK-12; BKR-04): separately identifiable directories `<BACKUP_DIR>/<timestamp>-<commit>/`, never one file overwritten; 7 daily, 4 weekly and 3 monthly generations as configuration (DIR-079 CB-11), confirmed against the measured disk budget (GLC-05); the pre-deployment generation and the one taken before the opening import commits (CUT-05) kept until the next release or go-live and counted in B; cleanup only of expired generations after the newer ones verified, never live data or the last verified recovery points, and a failed backup deleting nothing
- **Disk pressure** (BACKUP_RECOVERY §3): the thresholds `<DISK_WARN_PCT>` and `<DISK_CRIT_PCT>` as configuration; at the critical level no new generation starts unless the space for it and the retained verified generations is there, the skipped run logged and raised, never reported successful; nothing required deleted to make room; MULTIPLECORP's cleanup never deletes a file of the second application — the disk monitoring and its alerts are TASK-075's (BK-11)
- **Isolation and access** (BKR-06; BKR-07; SX-07; H9-09): MULTIPLECORP's dumps, files and manifests in its own directory, readable only by the operator account and never by the web runtime's, never mixed with the second application's; a backup, drill or restore reads and writes only MULTIPLECORP's database, files and backup directory, its schedule clear of the other application's windows (OPI-27); operated by the named human operator alone
- **The key pairing** (BKR-08; TM-65): each generation's manifest names the key-register entry of the `APP_KEY` valid at its time — the entry, never a key —; the restore installs that key before the application reads any TOTP data; a paired key that is a leaked key is never installed or listed, the restored state taking AU-25's new keys (INFRASTRUCTURE §7.3 step 5); the TOTP secrets and recovery codes stay ciphertext in every generation
- **The off-server copy** (BKR-09; INF-06; DIR-083 OE-09; GAP-063): each daily generation, once verified, copied encrypted to the destination OPI-32 names by role — a placeholder in the tooling —, outside the VPS and its provider account; encrypted so that the key that decrypts the copy, and a copy of the generation's paired key, are kept apart from the VPS in the operator's secret store and never with the copy — the latest generation's paired key being also the live `APP_KEY` in the VPS's environment (SX-01; AU-21) — and the copy restores without the VPS (BKR-08, BKR-09; architecture challenge 13b); only a verified generation copied (13a); the copy counted only when its checksums verify at the destination; a failed or missing copy raised for the operator's alert and never deleting a generation the retention keeps (BK-10; BK-12; 13c); its own retention 7 daily, 4 weekly and 3 monthly as far as the destination's space allows (DIR-079 CB-11); its transient file counted in §3's P term; each copy's generation time and checksums shown so that a copy is used only once verified to predate an incident (TT-F21; TT-F24); no paid destination (COST_POLICY §4C.2)
- **The restore tooling** of [BACKUP_RECOVERY §4](../09-operations/BACKUP_RECOVERY.md#4-the-restore-procedure) (BK-08; BKR-05): writes stopped — MULTIPLECORP in maintenance mode, its workers and scheduler stopped, the second application untouched; the recovery point chosen and verified, a corrupt or missing generation sending the procedure to the next older verified one; the dump into a new database of MULTIPLECORP's own instance and the files into a new directory, never over the live ones, with the paired key; the reconciliation — every `file_objects` row with its bytes and every byte file with its row, issued documents with their renditions, a missing rendition rendered again from the issued snapshot and never reissued or renumbered (GAP-007; GAP-011), the critical records listed for the operator's and the Owner's check against the papers; `ReconcileGuards` before reopening, a difference reported and never repaired silently (HO-28; GAP-031); owed work resumed through its durable rows and sweeps JB-02 and JB-09, never from Valkey; then step 7 — the Owner's raise through TASK-021's command before any numbered type issues again (DIR-083 OE-11; RBK-02); never run as the migration/owner role without the guard verification afterwards
- **The drill procedures** (PRQ-10; BK-09; [BACKUP_RECOVERY §5](../09-operations/BACKUP_RECOVERY.md#5-restore-testing); RELEASE_PLAN §7): the restore drill into isolation, timed against the 4-hour RTO and the recovery point's age against the 24-hour RPO, reconciled, its result recorded usable or not, with the cases an intact backup, a corrupt generation, a missing generation, an incomplete run, a disk-pressure run by a lowered threshold — never by filling the shared disk — and a failed cleanup, each with its expected truthful status; the off-server copy's drill — a copied generation brought back from its destination with its keys and restored by §4's procedure unchanged; the failure drill — a stopped worker, a stuck queue, a failed and a stale backup, the disk's warning level by a lowered threshold, a reconciliation difference and a missed scheduler run, each signal to reach the operator and the Owner; each record's date, recovery point, elapsed time, result and follow-ups, kept by the operator, its summary without secrets; the cadence of DIR-079 CB-12 — monthly for the first three months after go-live, then quarterly, and after any change to the backup tooling
- **The P9 handoffs as they bear on backups**: HO-28's `ReconcileGuards` after every restore and its snapshot clear of the backup window; HO-23's schedule entry beside the scheduler's, no step flushing Valkey; HO-25's backup signals handed to TASK-075; HO-30's dump and restore tools matched to the recorded PostgreSQL version; HO-21, HO-26 and HO-29 stay TASK-072's configuration and schedules
- **What the evidence must show**: the tooling produces the records AC-18 and AC-19 name — recovery point and elapsed time, checksums, reconciliation, the retention boundary, failed, stale and disk-pressure runs — for the operator's drills (TASK-081, TASK-087); no recovery after total VPS or storage loss is inferred from a local drill, RISK-001 staying narrowed and never removed (OS-04; GAP-018); the operator's failure detection stays distinct from the in-app alerts of REF-048 (TASK-058); everything runs on the existing VPS and a destination already held, at no cost (REF-062; DEP-02)

**OUT OF SCOPE:**

- running a backup, copy, restore or drill on staging or production — the operator's (TASK-079, TASK-081, TASK-084, TASK-087)
- the disk monitoring and the alerts of BK-10 and BK-11 (TASK-075); the alert channel (TASK-080)
- naming the destination, holding the keys and keeping the key register — the operator's (OPI-32, TASK-084; the register's entries, TASK-085)
- the runbooks of INFRASTRUCTURE §7 that use a restore — the key compromise and Scenario B (TASK-076)
- the Owner's raise command (TASK-021) and `ReconcileGuards` (TASK-013) themselves

**REFERENCES:**

- [BACKUP_RECOVERY §1](../09-operations/BACKUP_RECOVERY.md#1-the-decision-and-its-limits) BKR-01–BKR-09; [§2](../09-operations/BACKUP_RECOVERY.md#2-the-twelve-obligations) BK-01–BK-12; [§3](../09-operations/BACKUP_RECOVERY.md#3-the-shared-disk-budget); [§4](../09-operations/BACKUP_RECOVERY.md#4-the-restore-procedure); [§5](../09-operations/BACKUP_RECOVERY.md#5-restore-testing); [§8](../09-operations/BACKUP_RECOVERY.md#8-owner-questions) — DIR-079 CB-11–CB-13
- [V1_SCOPE](../01-product/V1_SCOPE.md#v1-local-backup-and-p9-handoff-contract) BK-01–BK-12; [MUST SHIP](../01-product/V1_SCOPE.md#must-ship) CAP-16; [dependencies](../01-product/V1_SCOPE.md#dependencies-and-evidence-needed) DEP-02
- [ACCEPTANCE_CRITERIA](../01-product/ACCEPTANCE_CRITERIA.md#product-acceptance-contracts) AC-18, AC-19; [operational success](../01-product/ACCEPTANCE_CRITERIA.md#operational-success-and-go-live-evidence) OS-04
- [REFERENCE_COVERAGE](../01-product/REFERENCE_COVERAGE.md#pdf-capability-inventory) REF-048, REF-060, REF-062; [final Owner decision coverage](../01-product/REFERENCE_COVERAGE.md#final-owner-decision-coverage) OWN-06
- [INFRASTRUCTURE §1](../09-operations/INFRASTRUCTURE.md#1-invariants) INF-06, INF-07; [§4.3](../09-operations/INFRASTRUCTURE.md#43-secrets-and-keys); [§5](../09-operations/INFRASTRUCTURE.md#5-the-deployment-path) step 2
- [RELEASE_PLAN §7](../10-release/RELEASE_PLAN.md#7-rehearsal-and-drills); [§9](../10-release/RELEASE_PLAN.md#9-the-go-live-checklist) GLC-04, GLC-06, GLC-20; [§10](../10-release/RELEASE_PLAN.md#10-production-release-requirements-for-p11) PRQ-10
- [SECURITY §7](../05-security/SECURITY.md#7-secrets-production-boundary-and-supply-chain) SX-07; [§8](../05-security/SECURITY.md#8-threat-model) TM-65; [§10](../05-security/SECURITY.md#p9--operations) H9-09
- [CONCURRENCY_IDEMPOTENCY §19](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s19-20-handoff-traceability.md#19-handoff-obligations) HO-21, HO-23, HO-25, HO-26, HO-28–HO-30; [§8](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s08-09-guards-stale-state.md#8-guards-and-first-use-rows); [§15](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s14-17-numbering-jobs-revocation.md#15-jobs-scheduler-and-queue) JB-02, JB-09
- [TEST_STRATEGY §15](../08-testing/TEST_STRATEGY.md#15-migration-backup-and-restore-tests) — the backup and restore tooling tests
- [GAP_REGISTER](../00-governance/GAP_REGISTER.md#gap-011--a-database-restore-alone-cannot-recover-usable-business-documents) GAP-011; [GAP-063](../00-governance/GAP_REGISTER.md#gap-063--an-off-server-backup-against-total-vps-loss-awaits-the-owners-decision); [GAP-018](../00-governance/GAP_REGISTER.md#gap-018--owner-accepts-total-vps-or-storage-loss-exposure-with-local-only-backup)
- [DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-09, OE-11; [COST_POLICY](../00-governance/COST_POLICY.md#procedure) §4C.2
- Architecture challenge — the sequences 13a, 13b, 13c of the [P11 gate record](evidence/P11_QUALITY_GATE.md#whole-system-architecture-challenge-sp-98), their assertions carried into this Task's acceptance criteria and tests
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (46): O-07; CAP-16; DEP-02; BK-01–BK-12; AC-18–AC-19; OS-04; REF-060; REF-062; OWN-06; SX-07; TM-65; H9-09; HO-21; HO-23; HO-25–HO-26; HO-28–HO-30; INF-06; BKR-01–BKR-09; TT-F21; TT-F24; PRQ-10; GAP-011; GAP-063

**EXPECTED MODULES / FILE AREAS:**

- the operator's backup, copy and restore commands or scripts, run under the operator account, with their configuration placeholders — `<BACKUP_DIR>`, `<BACKUP_TIME>`, `<DISK_WARN_PCT>`, `<DISK_CRIT_PCT>` and the copy's destination by role —; their tests under `tests/` on synthetic data; the drill procedures in the repository's operations documentation

**BUSINESS RULES:**

- N/A — no business rule; BK-01–BK-12 and BKR-01–BKR-09 are the contract, and the raise of BACKUP_RECOVERY §4 step 7 is TASK-021's command under DIR-083 OE-11

**ARCHITECTURE RULES:**

- a recovery point is the dump and its files under one identifier, coherent (BKR-01); a restore always goes into isolation, never over live writes, and data is recovered only by this procedure, never by a code rollback (BKR-05; RL-04)
- owed work resumes from its durable rows and sweeps, never from Valkey, which is never restored as truth (JB-02; JB-09; SZ-09)
- the tooling acts on MULTIPLECORP's project, database, storage and backup directory only (BKR-07; INF-07)

**SECURITY / PERMISSION REQUIREMENTS:**

- backup files readable only by the operator account, never by the web runtime's, never reachable from the web (SX-07; H9-09)
- no key, password or secret in a manifest, a log, the tooling or the repository — the manifest names the key-register entry only (BKR-08); nothing that decrypts the copy kept on the VPS or with the copy (BKR-09)
- no agent ever holds a backup file or access to one (BKR-06; INF-01); TOTP secrets and recovery codes stay ciphertext under `APP_KEY` in every generation (TM-65; AU-21)

**COMPANY SCOPE AND FIELD PROJECTION:**

- N/A — a generation holds the whole workspace and is the operator's alone; no application path reads a backup, so no company scope or projection applies (BKR-06)

**CONCURRENCY / IDEMPOTENCY:**

- the dump consistent at one snapshot and the files captured after it (BK-01; BKR-01); a second run writes a new generation, never over an earlier one (BK-05)
- a restore never runs over live writes — maintenance mode on, workers and scheduler stopped (BACKUP_RECOVERY §4 step 1); owed work re-dispatched by JB-02 and JB-09; the daily run clear of `ReconcileGuards`' nightly snapshot (HO-28); no lock class or AX- action of the application is touched

**AUDIT:**

- each run, copy, cleanup and drill logs its outcome truthfully — failed, stale or skipped, never successful (BK-10; BKR-02); the drill records kept by the operator with their summary without secrets (BACKUP_RECOVERY §5); the raise after a restore audited with the incident by TASK-021's command (NM-03; RBK-02); `ReconcileGuards`' result kept with the restore's record

**DATABASE IMPACT ASSESSMENT:**

- Schema change: NONE — no table or column
- Migration required: NO
- Existing data transformation: NONE
- Direct production DB access: FORBIDDEN
- Data-loss risk: NONE KNOWN

**MODULARITY / EXTENSIBILITY:**

- Module boundary affected: OPS's operator tooling, outside every business module
- Stable core kept provider-neutral: YES — standard PostgreSQL dumps and files; the destination is a device or storage already held, behind the copy step alone
- External/vendor-specific code isolated: YES — the copy's transfer to its destination in one step
- Replaceable adapter/contract required: NO
- Provider lock-in risk: NONE — no provider backup or snapshot feature is relied on (INF-08)
- Future extension impact: LOW
- Speculative abstraction avoided: YES — no backup service or second server; only the copy the Owner decided (DIR-083 OE-09)

**AUTH IMPACT:**

- Authentication change: NONE
- Authentication implementation: LARAVEL_NATIVE — decided once ([EXECUTION_CONTEXT](EXECUTION_CONTEXT.md)), reused, never redesigned
- Auth profile: N/A — this Task does not touch authentication; the paired key keeps the TOTP data readable after a restore (BKR-08)
- Google sign-in: N/A — not touched
- Google registration behavior: N/A — not touched
- Google credentials owner action required: NO
- Authorization change: NONE
- Provider/library-specific leakage into domain: NO

**COST IMPACT:**

- New recurring cost: NONE
- Paid third-party service: NO
- Free/open-source alternative: N/A — nothing paid is considered; the copy goes to a device or storage the Owner or the client already holds, at no recurring cost, and a paid destination STOPS under COST_POLICY §4C.2 (BKR-09)
- Estimated additional monthly production cost: 0
- Owner approval required: NO

**MCP SESSION PROFILE:**

- Required MCP servers: none — [TOOLCHAIN](../00-governance/TOOLCHAIN.md#mcp-servers) records none installed
- MCP servers that should remain OFF: every server not required
- Native CLI/package preferred over MCP: YES — the framework's and package managers' own commands
- Post-Task deactivation/cleanup: none

**TOOL REQUIREMENTS:**

- Context7: REQUIRED — `pg_dump` and `pg_restore` in the custom format and their snapshot consistency at the recorded PostgreSQL version; the chosen open-source encryption and checksum tools
- Graphify: OPTIONAL — the file and document tables exist by now; a targeted search of `file_objects` and the rendition rows serves otherwise
- Browser E2E: N/A — no page
- Other: synthetic data and a test destination in CI; the reconciliation queries over `file_objects` and the issued versions
- Missing safe tool behavior: AUTO-INSTALL project-locally inside this Task (AICWDF §6.3) and record it in TOOLCHAIN; a paid tier or a privileged installation STOPS for the Owner (COST_POLICY)

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: refreshed before the change when Graphify is available (TOOLCHAIN); otherwise a targeted search of the file storage and the rendition jobs
- Pre-change questions: which file purposes the private disk holds (DATABASE §22); which jobs owe work after a restore (JB-02, JB-09); where the key-register entry is read at restore
- Expected affected areas: OPS's operator tooling, its tests and the drill procedures only

**CONTEXT7:**

- Topics to verify: the custom-format dump's consistency and parallel restore; restoring into a new database of the same instance; public-key or other encryption under which the copy's decrypting key never sits on the VPS; checksum tooling
- Version-sensitive assumptions: none from memory — the dump tool's version matched to the server's major version (HO-30)

**UI / UX REQUIREMENTS:**

- Follow global design rules: N/A — operator tooling only; no page
- Indonesian default: N/A — operator tooling only
- English support: N/A — operator tooling only
- Plain-language Indonesian: N/A — operator tooling only
- Language switch impact: NONE
- Mobile-first: N/A — operator tooling only
- Responsive: N/A — operator tooling only
- Adaptive: N/A — operator tooling only
- Visual breathing room: N/A — operator tooling only
- Comfortable density: N/A — operator tooling only
- shadcn/ui reuse: N/A — operator tooling only
- Design reference: N/A — operator tooling only
- Primary action: N/A — operator tooling only
- Secondary actions: N/A — operator tooling only
- Destructive actions: N/A — no page; cleanup deletes only expired, superseded generations (BKR-04)
- Redundancy constraints: N/A — operator tooling only
- Loading state: N/A — operator tooling only
- Empty state: N/A — operator tooling only
- Error state: N/A — no page; every failure is a truthful failed status with its alert (BKR-02)

**ROUTE IMPACT:**

- none — operator tooling only; no web route reaches a backup (SX-07)

**INTERACTION CONTRACTS:**

- N/A — no page interaction

**NAVIGATION CONTRACT:**

- Parent: N/A — operator tooling only
- Entry: N/A — operator tooling only
- Back action: N/A — operator tooling only
- Cancel: N/A — operator tooling only
- Success destination: N/A — operator tooling only
- Error behavior: N/A — operator tooling only
- State preservation: N/A — operator tooling only
- Mobile behavior: N/A — operator tooling only

**ACCEPTANCE CRITERIA:**

1. A run writes one generation — the dump and its files under one identifier, every file row of the dump with its bytes — and a manifest whose checksums verify (BKR-01; BK-01; BK-02; BK-07).
2. A corrupt, missing or incomplete generation, and a run skipped at the critical disk level, are each reported failed, never successful (BKR-02; BK-07; BACKUP_RECOVERY §3).
3. A failed backup or cleanup deletes nothing; cleanup removes only expired generations after the newer ones verified, never the last verified recovery points or the pre-deployment and pre-import generations BK-06 keeps (BKR-04; BK-12).
4. Each manifest names its generation's key-register entry and holds no key, and a restore installs the paired key before any TOTP read (BKR-08).
5. Only a verified generation is copied off-server; a copy counts only when its checksums verify at the destination; a failed or missing copy raises the operator's alert and deletes no retained generation; nothing that decrypts the copy is kept on the VPS or with the copy (BKR-09; DIR-083 OE-09; architecture challenge 13a, 13b, 13c).
6. A restore lands in a new database and directory of MULTIPLECORP's own, reconciles files and rows, re-renders a missing rendition from its issued snapshot without reissuing, runs `ReconcileGuards` before reopening, and never touches the live state or the second application (BK-08; BKR-05; BKR-07; GAP-011).
7. The drill procedures time a restore against the 4-hour RTO and its recovery point against the 24-hour RPO, record the result usable or not, and cover each case of BACKUP_RECOVERY §5 (BK-09; AC-18; AC-19).
8. Backup files are readable only by the operator account, and no step reads or writes the second application's database, files or backups (SX-07; BKR-07).

**TEST REQUIREMENTS:**

- Lint: TL-01
- Typecheck: TL-02
- Unit: the status, retention and cleanup rules and the disk-pressure rule (TL-03)
- Feature: the restore's reconciliation and the commands' refusals outside the console (TL-04)
- Integration: TEST_STRATEGY §15's backup and restore tooling cases on synthetic data in CI — the manifest and checksums; corrupt, missing and incomplete generations; a missed or stale run raising its signal; rotation and the retention boundary; a failed cleanup deleting nothing required; disk pressure by a lowered threshold; a restore into isolation with the paired key; the copy to a test destination, its verification there and a failed copy (TL-05)
- Authorization: N/A — no route; the files' permissions asserted instead (SX-07)
- Route integrity: N/A — no route added or changed
- Interaction: N/A — no INT- contract in this Task
- Browser E2E: N/A — no page in this Task
- Localization/language switch: N/A — no user-facing copy in this Task
- Visual breathing room/density: N/A — no page in this Task
- Responsive: N/A — no page in this Task
- Accessibility: N/A — no page in this Task
- Production build: TL-13
- Security: no secret in manifests or logs, the copy's encryption and key separation, the files' permissions; SM-15's secret scan (TL-14)
- Performance: N/A — the durations of a full backup and a restore drill are measured on the VPS (OPI-25; TASK-079); PFM-08 bounds the `ReconcileGuards` step of a restore
- Migration: N/A — no migration or import code in this Task
- Other: GAP-011 and GAP-007 — after a restore of a fixture whose rendition file is missing, the rendition is produced again from its issued snapshot and no number changes

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

- TASK-076 — when its other dependencies are DONE and its READY gate holds

**DO NOT DO:**

- add to V1 — a capability, page, route, field, integration, document type, report or paid service — or drop, defer or narrow a V1_REQUIRED requirement; a Task that would need either raises an Owner question and waits ([DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-20)
- access the VPS, production, its data or a credential, or put a secret, real data, a host detail or production configuration in the repository ([INFRASTRUCTURE](../09-operations/INFRASTRUCTURE.md) INF-01, INF-05; SECURITY SX-01, SX-04, SX-06; [PRODUCTION_DATA_SAFETY](../00-governance/PRODUCTION_DATA_SAFETY.md))
- add AI, model or tool attribution to a commit — the Owner's configured identity only ([AGENTS.md](../../AGENTS.md#git-commit-attribution); DIR-022)
- start before the Owner's execution authorization at OG-4, or mark DONE without the required tests passing and the completion evidence ([TASK_PLAN](TASK_PLAN.md#2-rules-of-the-plan); [DEFINITION_OF_DONE](../08-testing/DEFINITION_OF_DONE.md#2-the-checklist))
- weaken, skip or delete a valid test to obtain a green run, or adopt a paid tier without the Owner's COST_POLICY decision (DOD-05; [COST_POLICY](../00-governance/COST_POLICY.md))
- hold, open, copy or restore a real backup, or run the tooling against staging or production (BKR-06; INF-01)
- report a partial, unverified or skipped run as successful, or delete a generation the retention keeps (BKR-02; BKR-04)
- keep a key, a password or the copy's decrypting key on the VPS beside the copy, in a manifest, a log or the repository (BKR-08; BKR-09)
- restore over the live database or files, or read or write the second application's data or backups (BKR-05; BKR-07)

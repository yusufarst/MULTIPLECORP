# TASK-081 — Staging drills and rehearsals — failure, restore, Scenario B and the import rehearsal

Status: APPROVED | Updated: 2026-10-20 | Owner: Planning

Approval: [APPR-016](../00-governance/DECISION_LOG.md#appr-016--p11-task-plan-tasks-and-feature-coverage-matrix-approved), explicit Owner approval on 2026-10-20 (DIR-085 OF-07) of this document as committed in the first execution session's approval commit; the approved file hash and the verified conditions are recorded there. Approval changes lifecycle only, not implementation authorization.

Written by WF-PC5-W's writer of bundle W4 of run PC-5 on 2026-10-19 and 2026-10-20 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99; the Owner approved P11 with the [Task plan](TASK_PLAN.md) at OG-4 (DIR-085 OF-07). **No Task starts before the freeze record, from which the execution authorization is in force (DIR-085 OF-09).** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** BLOCKED — waiting on DEP-05

**RISK CLASS:** HIGH

**EXECUTOR:** HUMAN OPERATOR

**OBJECTIVE:**

On the staging stack, with synthetic data and synthetic keys, the human operator runs and records the drills and rehearsals owed before go-live, by the procedures of TASK-074, TASK-076 and TASK-077: the failure drill, every signal reaching the operator and the Owner through the adopted channel; the restore drill with each case of BACKUP_RECOVERY §5, timed against the RTO and the RPO; the off-server copy's drill procedure; H9-14's Scenario B recovery test; the operator-run recovery commands of SM-07, SM-19 and SM-21; and the opening import rehearsal at the data steward's volumes — never touching the second application.

**WHY THIS EXISTS:**

A checksum is not restore proof — only a drill proves a recovery point (BKR-03; AC-18) —, and the failure drill is what shows that a stalled queue or a failed backup reaches a human (GAP-013; GLC-03). GLC-26 owes H9-14's Scenario B rehearsal before go-live, GLC-20 the off-server copy's drill, GAP-011 a timed restore, and GAP-010 an import rehearsal before the cutover, because legacy data exists in unknown condition (DEP-05). The tabletop was a discussion, never a recovery test, so every one of these passes only by its recorded evidence ([DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-14) — the architecture challenge's sequences 13a and 16a.

**DEPENDS ON:**

- TASK-064 — Critical journeys C — isolation, sign-in, access, search, approvals, import, language, masters, command identity and status
- TASK-070 — Full release regression and the device UAT's preparation
- TASK-074 — Backup tooling, the off-server copy and the drill procedures
- TASK-076 — Operator runbooks — Owner-account recovery, key compromise, Scenario B and key rotation
- TASK-077 — Cutover tooling — the numbering go-live command, the post-import refresh and the rehearsal procedure
- TASK-078 — Staging stack, the two applications' isolation and the provider features
- TASK-080 — Alert channel adopted and monitoring live
- DEP-05: the data steward's sanitized structures, counts and reconciled control totals of the legacy data received ([V1_SCOPE](../01-product/V1_SCOPE.md#dependencies-and-evidence-needed)) — BLOCKED until received

**BLOCKS:**

- TASK-084 — Production preparation — configuration, outbound HTTPS, branch protection, backups and the off-server copy

**PARALLEL SAFE:** NO — the drills run one at a time on the shared VPS, outside both applications' business hours and clear of the second application's windows

**IN SCOPE:**

- **The failure drill** ([RELEASE_PLAN §7](../10-release/RELEASE_PLAN.md#7-rehearsal-and-drills); GLC-03; GLC-16; GAP-013; TT-F01; PRQ-10): a stopped worker, a stuck queue, a failed and a stale backup, the disk's warning level by a lowered threshold — never by filling the shared disk —, a reconciliation difference and a scheduler that missed its run, each signal reaching the operator and the Owner through OPI-05's channel, with the in-app floor and the daily look kept; again after any later change to alerting
- **The restore drill on staging** ([BACKUP_RECOVERY §5](../09-operations/BACKUP_RECOVERY.md#5-restore-testing); BK-09; AC-18; AC-19; GAP-011): TASK-076's restore runbook into isolation on staging's synthetic data with the generation's paired synthetic key (BKR-08), timed against the 4-hour RTO and the recovery point's age against the 24-hour RPO, reconciled, its result recorded usable or not; the cases — an intact backup, a corrupt generation, a missing generation, an incomplete run, a disk-pressure run by a lowered threshold, a failed cleanup — each with its expected truthful status; this is the rehearsal of GLC-04's production drill after the import (TASK-087), and no recovery after total VPS or storage loss is inferred from it (OS-04; REF-060; OWN-06; RISK-001 narrowed)
- **The off-server copy's drill procedure** (BKR-09; GAP-063; TT-F20): a verified synthetic generation copied off the VPS by TASK-074's tooling, brought back with its keys and restored into isolation by BACKUP_RECOVERY §4 unchanged, its checksums verified, its record kept; GLC-20's own drill — a copied production generation restored from the destination OPI-32 names — runs where that destination is recorded (TASK-084)
- **H9-14's recovery test** (GLC-26; INFRASTRUCTURE §7.3 step 5; SM-21): Scenario B on staging with synthetic data and synthetic keys by TASK-076's runbook — a verified generation restored onto a clean stack, new keys installed with the leaked key never listed, every SX-01 secret the scenario's leak may have included rotated, the incident command run in full on the restored state, then Scenario A's steps (2)–(4) —; only a backup verified as predating the scenario's window used, a copy proving nothing by existing (TT-F21; TT-F24; architecture challenge 13a); the second application untouched (16a)
- **The operator-run security cases** ([SECURITY_TEST_MATRIX §9](../08-testing/SECURITY_TEST_MATRIX.md#9-where-each-case-runs)): SM-07's and SM-19's Owner-recovery procedure end to end with the operator running the command; SM-21's incident command and OWNER-restoration with M-46's cases and the halt's path — on a trusted state first to the Owner's decision outside the application (DIR-083 OE-10) —, all on staging, never on production
- **The import rehearsal** (IMP-13; GAP-010; DEP-05): TASK-077's procedure with FX-09-shaped synthetic batches at the volume the data steward reports, counts only; the dry run, the two sign-offs and the commit done through PG-058 as the Admin and the Owner will do them; the commit's duration measured, the chunk size set within JB-07's timeout and a retried chunk shown to find its rows done (M-33); the freeze's length sized with OPI-25's durations; the reconciliation after it — `ReconcileGuards` clean, stock per company equal to its opening lots, receivables to the imported remainders, credits to the signed opening evidence; the real data's dry run stays on production (IMP-04; TASK-087)
- **GLC-19's other half**: the backup and the restore drill above shown without touching the second application's database, files, backups or windows (BKR-07), completing TASK-078's record
- **What the drills prove and what they do not**: the operator's failure detection, distinct from the in-app alerts of REF-048 (TASK-058); readiness on the existing VPS at no cost (CAP-16; REF-062)
- **The record**: each drill's date, recovery point, elapsed time, result and follow-ups, kept by the operator, its summary without secrets (BACKUP_RECOVERY §5)
- **The first release's Layer 3 run on staging** (REL-04; TEST_STRATEGY §3, Layer 3): the steps only staging can host, run by the operator from TASK-070's preparation, with this Task's failure and restore drills — each result the operator's record (placed here in Part 4)
- **CJ-16 on staging** (TEST_STRATEGY §7): the opening import end to end during the import rehearsal, from the script TASK-064 prepares — its pass the operator's record, never counted before it exists (placed here in Part 4)

**OUT OF SCOPE:**

- the production restore drill after the import (GLC-04; TASK-087) and the drill of the real off-server copy (GLC-20; TASK-084)
- any production data, backup or key, and any run on production (PRODUCTION_DATA_SAFETY; INF-01)
- the tooling, runbooks and procedures themselves (TASK-074, TASK-076, TASK-077) and the channel's adoption (TASK-080)
- a recovery-time claim after total VPS or storage loss (BACKUP_RECOVERY §1)

**REFERENCES:**

- [BACKUP_RECOVERY §1](../09-operations/BACKUP_RECOVERY.md#1-the-decision-and-its-limits) BKR-03, BKR-07–BKR-09; [§4](../09-operations/BACKUP_RECOVERY.md#4-the-restore-procedure); [§5](../09-operations/BACKUP_RECOVERY.md#5-restore-testing); [§6](../09-operations/BACKUP_RECOVERY.md#the-exercise-held-in-pc-4) TT-F01, TT-F20, TT-F21, TT-F24
- [RELEASE_PLAN §5](../10-release/RELEASE_PLAN.md#5-the-opening-import) IMP-04, IMP-13; [§7](../10-release/RELEASE_PLAN.md#7-rehearsal-and-drills); [§9](../10-release/RELEASE_PLAN.md#9-the-go-live-checklist) GLC-03, GLC-04, GLC-19, GLC-20, GLC-26; [§10](../10-release/RELEASE_PLAN.md#10-production-release-requirements-for-p11) PRQ-10
- [INFRASTRUCTURE §6](../09-operations/INFRASTRUCTURE.md#6-observability); [§7.3](../09-operations/INFRASTRUCTURE.md#73-the-post-incident-recovery-runbook) step 5
- [V1_SCOPE](../01-product/V1_SCOPE.md#must-ship) CAP-16; [dependencies](../01-product/V1_SCOPE.md#dependencies-and-evidence-needed) DEP-05; [the local-backup contract](../01-product/V1_SCOPE.md#v1-local-backup-and-p9-handoff-contract) BK-09
- [ACCEPTANCE_CRITERIA](../01-product/ACCEPTANCE_CRITERIA.md#product-acceptance-contracts) AC-18, AC-19; [operational success](../01-product/ACCEPTANCE_CRITERIA.md#operational-success-and-go-live-evidence) OS-04
- [REFERENCE_COVERAGE](../01-product/REFERENCE_COVERAGE.md#pdf-capability-inventory) REF-048, REF-060, REF-062; [final Owner decision coverage](../01-product/REFERENCE_COVERAGE.md#final-owner-decision-coverage) OWN-06
- [SECURITY_TEST_MATRIX](../08-testing/SECURITY_TEST_MATRIX.md#3-the-matrix) SM-07, SM-19, SM-21
- [CONCURRENCY_IDEMPOTENCY §15](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s14-17-numbering-jobs-revocation.md#15-jobs-scheduler-and-queue) JB-07; [§18](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s18-scenarios.md#18-mandatory-concurrency-and-failure-scenarios) M-33, M-46
- [TEST_STRATEGY §4](../08-testing/TEST_STRATEGY.md#4-environments-and-data) FX-09; [§15](../08-testing/TEST_STRATEGY.md#15-migration-backup-and-restore-tests)
- [GAP_REGISTER](../00-governance/GAP_REGISTER.md#gap-010--opening-stock-and-open-receivables-can-be-counted-twice-at-migration) GAP-010; [GAP-011](../00-governance/GAP_REGISTER.md#gap-011--a-database-restore-alone-cannot-recover-usable-business-documents); [GAP-013](../00-governance/GAP_REGISTER.md#gap-013--successful-http-health-checks-can-hide-stalled-business-processing); [GAP-063](../00-governance/GAP_REGISTER.md#gap-063--an-off-server-backup-against-total-vps-loss-awaits-the-owners-decision)
- [DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-09, OE-10, OE-14
- Architecture challenge — the sequences 13a, 16a of the [P11 gate record](evidence/P11_QUALITY_GATE.md#whole-system-architecture-challenge-sp-98), their assertions carried into this Task's acceptance criteria and tests
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (29): O-07; CAP-16; DEP-05; BK-09; AC-18–AC-19; OS-04; REF-060; REF-062; OWN-06; CM-33; M-33; M-46; CJ-16; BKR-09; TT-F01; TT-F20–TT-F21; TT-F24; REL-04; GLC-03; GLC-16; GLC-19–GLC-20; GLC-26; PRQ-10; GAP-010–GAP-011; GAP-063

**EXPECTED MODULES / FILE AREAS:**

- no code; the operator's drill records outside the repository, their summaries without secrets in COMPLETION EVIDENCE; a defect a drill finds goes back to the Task that owns the tooling, runbook or procedure through the executor feedback loop ([AGENT_OPERATING_MODEL](../00-governance/AGENT_OPERATING_MODEL.md#executor-feedback-loop))

**BUSINESS RULES:**

- N/A — no business rule is built; the import rehearsal's reconciliation checks the opening-fact rules TASK-057 builds (BR-XD-06; CM-33) against GAP-010's control totals

**ARCHITECTURE RULES:**

- a restore always into isolation, never over live writes (BKR-05); only a drill proves a recovery point (BKR-03); Scenario B on a clean stack, never on the untrusted one (INFRASTRUCTURE §7.3 step 5)
- every drill outside both applications' business hours, planned around the second application's windows and load, never touching it (BACKUP_RECOVERY §5; BKR-07)

**SECURITY / PERMISSION REQUIREMENTS:**

- synthetic data and synthetic keys only — never a production backup, key or secret; in Scenario B's restored state the leaked synthetic key is never listed (BKR-08; INFRASTRUCTURE §7.3 step 5)
- the recovery and incident commands run by the operator alone (SX-04); no token, link or key in a drill record (SX-01)

**COMPANY SCOPE AND FIELD PROJECTION:**

- N/A — the synthetic group only; the import rehearsal's out-of-scope rows are refused with MSG-18 alone as TASK-057 builds it (DP-18), and no drill reads a company's data outside its synthetic fixture

**CONCURRENCY / IDEMPOTENCY:**

- the import's chunks replay under deterministic command identities, a retried chunk finding its rows done (CI-01; CI-08; JB-07; M-33); SM-21's M-46 cases — a second restoration of one account in one run refused; no drill runs over live writes (BACKUP_RECOVERY §4 step 1)

**AUDIT:**

- the synthetic run's audit and security events — CREDENTIALS_VOIDED, OWNER_RESTORED, OWNER_RECOVERY_LINK_ISSUED, TOTP_SECRETS_REENCRYPTED — checked by SM-19's and SM-21's cases with the operator's identity and no user row (LG-01; LG-02; RG-11); each drill's record kept by the operator (BACKUP_RECOVERY §5)

**DATABASE IMPACT ASSESSMENT:**

- Schema change: NONE — no repository change; production data untouched except through the approved path
- Migration required: NO
- Existing data transformation: NONE
- Direct production DB access: FORBIDDEN
- Data-loss risk: NONE KNOWN

**MODULARITY / EXTENSIBILITY:**

- Module boundary affected: none — no code
- Stable core kept provider-neutral: YES — no provider backup, snapshot or recovery feature is used (INF-08)
- External/vendor-specific code isolated: N/A — no code
- Replaceable adapter/contract required: NO
- Provider lock-in risk: NONE
- Future extension impact: LOW — the drills repeat at DIR-079 CB-12's cadence after go-live
- Speculative abstraction avoided: YES — the procedures as written, nothing added

**AUTH IMPACT:**

- Authentication change: NONE — the drills exercise the designed recovery and incident commands on staging
- Authentication implementation: N/A — no code; the profile stays LARAVEL_NATIVE ([EXECUTION_CONTEXT](EXECUTION_CONTEXT.md))
- Auth profile: N/A — not touched; staging's accounts and keys are synthetic
- Google sign-in: N/A — not touched; a recovered synthetic Owner's Google link is unlinked as AU-20 e states
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

- Context7: N/A — the operator follows TASK-074's, TASK-076's and TASK-077's procedures as built
- Graphify: N/A — no code
- Browser E2E: REQUIRED — CJ-16 on staging during the import rehearsal, run from TASK-064's script (TL-09); the Admin's and the Owner's PG-058 steps are done in a supported browser on staging
- Other: TASK-074's tooling, TASK-076's runbooks, TASK-077's procedure and generator, TASK-080's channel, synthetic keys and the staging stack of TASK-078
- Missing safe tool behavior: N/A — no agent installs anything; the operator uses only the built tooling, and a paid tier STOPS for the Owner (COST_POLICY §4C.2)

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: N/A — no code
- Pre-change questions: the second application's windows and load (OPI-27); the data steward's counts for the import rehearsal; which steps the drill records must time
- Expected affected areas: the staging stack only

**CONTEXT7:**

- Topics to verify: N/A — no library is configured
- Version-sensitive assumptions: none — the drills run the release's tagged commit as built

**UI / UX REQUIREMENTS:**

- Follow global design rules: N/A — no page is built or changed; PG-058 is used as TASK-057 built it
- Indonesian default: N/A — no page is built or changed
- English support: N/A — no page is built or changed
- Plain-language Indonesian: N/A — no page is built or changed
- Language switch impact: NONE
- Mobile-first: N/A — no page is built or changed
- Responsive: N/A — no page is built or changed
- Adaptive: N/A — no page is built or changed
- Visual breathing room: N/A — no page is built or changed
- Comfortable density: N/A — no page is built or changed
- shadcn/ui reuse: N/A — no page is built or changed
- Design reference: N/A — no page is built or changed
- Primary action: N/A — no page is built or changed
- Secondary actions: N/A — no page is built or changed
- Destructive actions: N/A — no page; every restore lands in isolation, on synthetic data
- Redundancy constraints: N/A — no page is built or changed
- Loading state: N/A — no page is built or changed
- Empty state: N/A — no page is built or changed
- Error state: N/A — no page; a failed drill is recorded as failed, with its follow-up

**ROUTE IMPACT:**

- none — staging serves the release's routes as built

**INTERACTION CONTRACTS:**

- N/A — no interaction is built or changed

**NAVIGATION CONTRACT:**

- Parent: N/A — no page
- Entry: N/A — no page
- Back action: N/A — no page
- Cancel: N/A — no page
- Success destination: N/A — no page
- Error behavior: N/A — no page
- State preservation: N/A — no page
- Mobile behavior: N/A — no page

**ACCEPTANCE CRITERIA:**

1. Every signal of the failure drill reaches the operator and the Owner through the channel, each recorded (GLC-03; GAP-013; TT-F01).
2. The staging restore drill restores into isolation with its paired synthetic key, is timed against the RTO and the RPO, reconciles, and records its result usable or not; each case of BACKUP_RECOVERY §5 shows its expected truthful status (BK-09; AC-18; AC-19).
3. A copied synthetic generation brought back from outside the VPS restores unchanged by BACKUP_RECOVERY §4, its checksums verified (BKR-09).
4. H9-14's Scenario B rehearsal passes on a clean stack — new keys with the leaked key never listed, every affected secret rotated, the incident command in full, then Scenario A's steps (2)–(4) — from a backup verified as predating the window (GLC-26; architecture challenge 13a).
5. The operator-run cases of SM-07, SM-19 and SM-21 pass on staging, the halt on a trusted state going first to the Owner's decision outside the application (DIR-083 OE-10).
6. The import rehearsal at the data steward's counts yields the commit's duration, a chunk size within JB-07's timeout and the freeze's length, and its reconciliation shows no double-counted stock, receivable or credit (IMP-13; GAP-010).
7. No drill touched the second application, its data, its backups or its windows, and none used production data or keys (BKR-07; INF-07; architecture challenge 16a).

**TEST REQUIREMENTS:**

- Lint: N/A — no code in this Task
- Typecheck: N/A — no code in this Task
- Unit: N/A — no code in this Task
- Feature: N/A — no route or command in this Task
- Integration: N/A — no code; the drills on the staging stack stand in its place
- Authorization: N/A — no route; SM-19's and SM-21's operator-run cases instead
- Route integrity: N/A — no route added or changed
- Interaction: N/A — no INT- contract in this Task
- Browser E2E: CJ-16 on staging during the import rehearsal, from TASK-064's script, its pass the operator's record (TL-09)
- Localization/language switch: N/A — no user-facing copy in this Task
- Visual breathing room/density: N/A — no page in this Task
- Responsive: N/A — no page in this Task
- Accessibility: N/A — no page in this Task
- Production build: N/A — staging runs the release's tagged commit as CI built it
- Security: SM-07's, SM-19's and SM-21's operator-run cases on staging (TL-14)
- Performance: N/A — the drills' elapsed times are drill results; OPI-25's measurement is TASK-079's
- Migration: TL-16's staging half — the opening import rehearsed on FX-09-shaped batches with its reconciliation (TEST_STRATEGY §15)
- Other: each drill's record — date, recovery point, elapsed time, result and follow-ups

**TEST CHECKLIST:**

- [ ] Lint — N/A: no code in this Task
- [ ] Typecheck — N/A: no code in this Task
- [ ] Relevant automated tests — N/A: no code; the operator's drill records — the failure drill, the restore drill with its six cases, the copy drill, Scenario B, the operator-run security cases, the import rehearsal — stand in their place
- [ ] Authorization — SM-19's and SM-21's operator-run cases recorded on staging; the commands run by the operator alone
- [ ] Route integrity — N/A: no route added or changed
- [ ] Interaction behavior — N/A: no interaction built or changed
- [ ] Browser E2E if required — CJ-16 on staging during the import rehearsal, from TASK-064's script (TL-09)
- [ ] Responsive/adaptive if required — N/A: no page
- [ ] Visual breathing room/density if UI — N/A: no page
- [ ] Production build — N/A: staging runs the release's tagged commit
- [ ] Security checks if required — SM-07, SM-19 and SM-21 on staging; the leaked synthetic key never listed in Scenario B's restored state
- [ ] Database safety review — synthetic data and keys only; every restore in isolation; nothing of the second application read or written
- [ ] Final diff review — no repository change beyond the evidence summary; a defect sent back to the owning Task

**COMPLETION EVIDENCE:**

- Test outputs: the operator's record of each drill — its date, recovery point, elapsed time against the RTO and RPO, reconciliation, result and follow-ups — and of the import rehearsal's durations, chunk size and reconciliation, in [TEST_STRATEGY §16](../08-testing/TEST_STRATEGY.md#16-evidence)'s form without secrets; only recorded evidence passes (DIR-083 OE-14)
- Build result: N/A — no build in this Task; the drilled commit is named in each record
- Route evidence: N/A — no route added or changed
- Interaction evidence: N/A — no INT- contract in this Task
- Browser evidence: CJ-16's run on staging during the import rehearsal, its pass the operator's record (DOD-18)
- Localization evidence: N/A — no page in this Task
- Cost evidence/exception if applicable: ADDITIONAL RECURRING COST CHANGE: NONE (DOD-20)
- Files changed: none in the application; this file's evidence summary only
- Commit SHA: the commit that records the summary, under the Owner's identity, with no AI attribution
- Notes: each non-applicable item with its reason (DOD-03); GLC-04's production drill left to TASK-087 and GLC-20's drill of the real copy to TASK-084; GLC-19 completed for the backup and the restore drill; the DONE update of TASK_PLAN, this file and CURRENT_HANDOFF (AICWDF §22.3), made on the operator's record and never on an agent's claim (TASK_PLAN §2)

**SAFE NEXT TASK:**

- TASK-084 — when its other dependencies are DONE and its READY gate holds

**DO NOT DO:**

- add to V1 — a capability, page, route, field, integration, document type, report or paid service — or drop, defer or narrow a V1_REQUIRED requirement; a Task that would need either raises an Owner question and waits ([DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-20)
- let an agent access the VPS, production, its data or a credential, or put a secret, real data, a host detail or production configuration in the repository ([INFRASTRUCTURE](../09-operations/INFRASTRUCTURE.md) INF-01, INF-05; SECURITY SX-01, SX-04, SX-06; [PRODUCTION_DATA_SAFETY](../00-governance/PRODUCTION_DATA_SAFETY.md))
- add AI, model or tool attribution to a commit — the Owner's configured identity only ([AGENTS.md](../../AGENTS.md#git-commit-attribution); DIR-022)
- start before the Owner's execution authorization at OG-4, or mark DONE without the required tests passing and the completion evidence ([TASK_PLAN](TASK_PLAN.md#2-rules-of-the-plan); [DEFINITION_OF_DONE](../08-testing/DEFINITION_OF_DONE.md#2-the-checklist))
- weaken, skip or delete a valid test to obtain a green run, or adopt a paid tier without the Owner's COST_POLICY decision (DOD-05; [COST_POLICY](../00-governance/COST_POLICY.md))
- run a drill with production data, a production backup or a production key, or on production (PRODUCTION_DATA_SAFETY; INF-01)
- fill the shared disk to test pressure, or touch the second application, its backups or its windows (BACKUP_RECOVERY §5; BKR-07)
- record a drill as passed without its timed, reconciled record, or a checksum as restore proof (DIR-083 OE-14; BKR-03)
- use real legacy rows, extracts or files in the import rehearsal (IMP-12; RL-05)

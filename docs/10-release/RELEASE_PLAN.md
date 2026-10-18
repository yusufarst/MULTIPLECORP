# Release plan

Status: REVIEW — Draft of P10, not normative before its approval | Updated: 2026-10-19 | Owner: Planning

**Draft of P10, not normative before its approval.** Run PC-4 drafted this document on 2026-10-19 under [DIR-082](../00-governance/DECISION_LOG.md#dir-081-dir-082-obs-032-and-tech-039--og-2-decisions-appr-011-run-pc-4-p8-and-p10-drafted-and-p9-completed) SP-86, P10 being authorized by the Owner's [DIR-081](../00-governance/DECISION_LOG.md#dir-081-dir-082-obs-032-and-tech-039--og-2-decisions-appr-011-run-pc-4-p8-and-p10-drafted-and-p9-completed) OB-09; the Owner approves P10, or not, by a separate decision at OG-3. Nothing here is a release, a deployment, a migration or an import, and no agent connected to the VPS or any production system to write it; P9's [INFRASTRUCTURE](../09-operations/INFRASTRUCTURE.md) and [BACKUP_RECOVERY](../09-operations/BACKUP_RECOVERY.md) are completed in the same run, and §9 lists what is still owed before go-live.

**Sources.** [AICWDF](../00-governance/AICWDF_ADOPTION.md) §21, §32 and §39, with §29's legitimate schema evolution; the brief's §§43, 45–47 and 55 ([source record](../00-governance/sources/OWNER_BRIEF_2026-09-27.txt)), the inputs the [ownership registry](../00-governance/SOURCE_OF_TRUTH.md#canonical-ownership-registry) names for P10; every requirement an approved document or P9's drafts hand to P10 — found by a scripted search for "P10", sources and evidence excluded, and mapped in §14 —, chief among them [ACCEPTANCE_CRITERIA](../01-product/ACCEPTANCE_CRITERIA.md)' release completion and operational success, [V1_SCOPE](../01-product/V1_SCOPE.md) DEP-05 and DEP-06, [PRODUCTION_DATA_SAFETY](../00-governance/PRODUCTION_DATA_SAFETY.md)'s owed procedures, [WORKFLOWS](../03-workflows/WORKFLOWS/s05-07-08-completion-migration.md) WF-MIG-01, [DATABASE §24](../04-architecture/DATABASE/s20-24-index-storage-migration.md#24-migration-and-opening-data), [CONCURRENCY_IDEMPOTENCY](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s19-20-handoff-traceability.md#19-handoff-obligations) HO-31–HO-34 and HO-36, [ADMIN_FLOW](../07-ux-design/ADMIN_FLOW/s11-13-corrections-numbering-import.md#13-opening-import) §13 and UXH-10, [SECURITY](../05-security/SECURITY.md) AU-25 and H9-14, and the gaps whose resolution names P10 ([GAP_REGISTER](../00-governance/GAP_REGISTER.md)) — GAP-010, GAP-011, GAP-012, GAP-013, GAP-014, GAP-019, GAP-023, GAP-024, GAP-025, GAP-049 and GAP-058 among them; P8's [TEST_STRATEGY](../08-testing/TEST_STRATEGY.md), [SECURITY_TEST_MATRIX](../08-testing/SECURITY_TEST_MATRIX.md), [PERFORMANCE_TARGETS](../08-testing/PERFORMANCE_TARGETS.md) and [DEFINITION_OF_DONE](../08-testing/DEFINITION_OF_DONE.md), drafted with it.

Authority: P10 — the release sequence and its declarations, the opening import's design, the cutover, rollback, the rehearsal and the drills, UAT, the go-live checklist, the release integrity report, module and release aggregation and the production-release requirements P11 turns into Tasks. It adds no business rule, capability, control or budget and relaxes none.

**Public repository.** No host, address, port, account, secret, provider detail or real data appears here: the operator's values are placeholders such as `<OPERATOR>` and live outside the repository, legacy files stay private business data, and what is still to be supplied is §9's list, never assumed.

## 1. Principles

| RL | Principle | Grounds |
| --- | --- | --- |
| RL-01 | **The human operator alone deploys, from `main`.** No agent and no automatic trigger from the repository ever deploys, migrates, imports, backs up or restores production; agents work only locally and through CI | INFRASTRUCTURE INF-01–INF-03; AGENT_OPERATING_MODEL; AICWDF §29 |
| RL-02 | **No date reduces scope or a required check.** A release goes when its evidence passes, not when a date arrives; a real scope or date tradeoff is the Owner's decision, raised with its evidence | GAP-014; DIR-035 D1; brief §47 |
| RL-03 | **Every release declares** `DB CHANGE: NONE / APPROVED CONTROLLED MIGRATION` and `ADDITIONAL RECURRING COST CHANGE: NONE / APPROVED EXCEPTION` | AICWDF §21; COST_POLICY |
| RL-04 | **A code rollback is not data recovery.** Recorded stock, payments and issued documents are never undone by redeploying older code; data is recovered only by BACKUP_RECOVERY's procedure, by the operator, on the Owner's decision | GAP-012; PRODUCTION_DATA_SAFETY; BACKUP_RECOVERY BKR-05 |
| RL-05 | **Production data stays in production.** Real legacy and business data are handled only by the operator on the production host, by the Owner and the validators through the production application, and by the data steward who prepares the legacy extracts — outside the repository and out of every agent's reach; staging, CI and every test use synthetic data | PRODUCTION_DATA_SAFETY; TEST_STRATEGY TS-04 |
| RL-06 | **A release passes on evidence only**: its release integrity report PASS (§11) and the Owner's release approval; "should work" is not evidence | AICWDF §§21, 26, 39; OS-06 |
| RL-07 | **Scope is protected after the freeze.** A new feature enters V1 only when it blocks core business operation, or is a critical security issue, a critical data integrity issue or a mandatory client requirement | brief §47 |
| RL-08 | **Expand now, contract later.** A release's migrations are expand-only; the contract step comes in a later release, so the previous code keeps working during a rolling deployment and a code rollback stays possible | HO-31; PRODUCTION_DATA_SAFETY |

## 2. The branch and environment model

AICWDF §32's model as the repository adopts it with P9's environments ([INFRASTRUCTURE §3](../09-operations/INFRASTRUCTURE.md#3-environments)), subject to [P10Q-02](#p10q-02--the-release-branch-model):

```text
feature/* or hotfix/*
  ↓ pull request, CI (TEST_STRATEGY Layer 1)
main, protected — required CI, the Owner's approval of the pull request, no force push
  ↓ a release: a tagged commit of main whose CI passed (Layer 2 for its modules)
STAGING — the ephemeral stack the operator starts on the shared VPS, resource-capped and never touching the
  second production application, synthetic data (DIR-079 CB-04; INFRASTRUCTURE INF-07)
  ↓ rehearsal, Layer 3 regression, drills, UAT with the Owner
the Owner's release approval on the release integrity report
  ↓
PRODUCTION — deployed from main by the human operator (INFRASTRUCTURE §5)
```

Docker Compose is the deployment tooling unless OPI-03 records that the VPS already runs Coolify (DIR-079 CB-05); either way the operator runs it. Protecting `main` is a repository setting the Owner makes; it is a go-live item (§9).

## 3. The release sequence

Every release, the first included, follows these steps in order; a failed step stops the release for the operator's decision (§6).

| REL | Step | What it holds | Grounds |
| --- | --- | --- | --- |
| REL-01 | **Ready** | The release's required Tasks DONE (DEFINITION_OF_DONE); CI green on the release commit of `main`; Layer 2's regression passed for every module the release touches | AICWDF §21; TEST_STRATEGY §3 |
| REL-02 | **Declarations** | DB CHANGE and ADDITIONAL RECURRING COST CHANGE (RL-03); the rollback target — the previous release's commit, its schema version and the pre-deployment backup (§6) | AICWDF §21 |
| REL-03 | **Staging rehearsal** | The operator starts staging — resource-capped beside both production applications, never touching the second one (INFRASTRUCTURE INF-07) —, seeds the synthetic data and deploys the release there by the same steps as production: the migration, the worker restart, an interrupted-migration case and its recovery; job payload versions and a page loaded before the deployment posting a command afterwards (HO-31); a rollback of the release by RBK-01; the measured duration of each step, for a release window kept clear of the second application's windows (OPI-27) | GAP-012; HO-31; CB-04 |
| REL-04 | **Layer 3 regression and drills** | TEST_STRATEGY Layer 3 on staging, the operator running the steps only staging can host; for the first release, and after a change to alerting or backup tooling, the failure drill and the restore drill of §7 | AICWDF §28; GAP-013; CB-12 |
| REL-05 | **UAT, when required** | §8 with the Owner and the validators; every release that changes what a user does or sees | AICWDF §21 |
| REL-06 | **The release integrity report** | §11, compiled from the evidence; PASS or FAIL | AICWDF §39 |
| REL-07 | **The Owner's release approval** | on the report, the declarations and the UAT record | AICWDF §21; OS rows |
| REL-08 | **Pre-deployment backup** | a backup whose verification completed; a failed or unverified backup stops the deployment | BK-03; INFRASTRUCTURE §5 step 2 |
| REL-09 | **Drain** | in-flight cascades drained before the deployment, the queue at rest | HO-34 |
| REL-10 | **Deploy** | INFRASTRUCTURE §5 steps 3–5: a maintenance window or a rolling deployment as P10Q-03 settles; expand-only migrations through the migration connection; new code; every queue worker and the scheduler restarted; no step flushes the queue store | HO-31; HO-23; CP-07 |
| REL-11 | **Verify** | health and smoke checks of the business heartbeat, not HTTP alone; `ReconcileGuards` after a release that changes a posting service; the smoke checks read only; and the operator's read-only check, at every release, of WS-10's gates and GLC-07's items on production | GAP-013; HO-34; INFRASTRUCTURE §6 |
| REL-12 | **Monitor and record** | the heartbeat and alerts watched by the operator through the release's first business day; the deployed commit and schema version recorded; the release's report, declarations, approval and actual effort kept with the release record | GAP-012; GAP-014; INFRASTRUCTURE §6 |
| REL-13 | **Contract later** | a contract migration only in a later release, after the code that needed the old form is gone | HO-31 |

## 4. Release requirements

AICWDF §21's requirements, each with its step and evidence:

| Requirement | Step | Evidence |
| --- | --- | --- |
| required Tasks DONE | REL-01 | the Task plan's counters; TASKS of §11 |
| CI passes | REL-01 | the release commit's CI run |
| staging | REL-03, REL-04 | the rehearsal record |
| critical E2E | REL-04 | CJ-01–CJ-22 at Layer 3, `E2E: PASS — Evidence: …` |
| localization verification | REL-04 | TEST_STRATEGY §10 in both languages |
| regression | REL-01, REL-04 | Layers 2 and 3 |
| UAT when required | REL-05 | §8's record |
| release approval | REL-07 | the Owner's approval |
| rollback target | REL-02 | the previous commit, schema version and backup |
| backup readiness when relevant | REL-08 | the verified pre-deployment backup |
| health check | REL-11 | the business heartbeat |
| smoke test | REL-11 | the smoke checks' result |
| monitoring | REL-12 | the heartbeat, alerts and uptime check watched |
| the DB and cost declarations | REL-02 | §11's DATABASE and COST sections |

**Module and release aggregation** (ACCEPTANCE_CRITERIA; REFERENCE_COVERAGE's P10 row). A module counts toward a release only when DEFINITION_OF_DONE §5 holds for it. **V1's release completion** requires, together: full requirement → canonical specification → workflow or rule → Task → acceptance → required test traceability through the FEATURE_COVERAGE_MATRIX P11 creates (GAP-024), with all mandatory Tasks DONE and no undocumented cut; the golden normal flow and its applicable branches passing (CJ-01); separate Owner and Admin Operasional UAT evidence; the agreed migration reconciliation (§5); company and permission isolation; inventory and payment integrity and the critical race and retry checks; TestSprite regression in the role P8Q-01 settles and fresh CI; security and N+1 and performance evidence; a verifiable backup and a timed restore of a production recovery point (§7), RISK-001 (GAP-018) explicitly retained — or OQ-26's answer recorded —; validated production configuration — debug off, secrets and access correct; a successful operator deployment with its smoke checks; and the operational outcomes OS-01–OS-06 evidenced, the Owner giving the final business decision. Every reference scope item stays covered (REF-061), the UAT, migration, recovery and deployment evidence is kept, and the actual effort is recorded beside the estimates (GAP-014).

## 5. The opening import

P10's import design for CAP-15 and AC-17 over WF-MIG-01, [DATABASE §24](../04-architecture/DATABASE/s20-24-index-storage-migration.md#24-migration-and-opening-data), AX-28 and [ADMIN_FLOW §13](../07-ux-design/ADMIN_FLOW/s11-13-corrections-numbering-import.md#13-opening-import) — legacy data exists and is never assumed clean (brief §45; DEP-05).

| IMP | Design | Grounds |
| --- | --- | --- |
| IMP-01 | **What is imported.** Current masters — clients with their units and PICs, suppliers, products with units, barcodes, serial policy and prices; opening stock as opening lots per company, product, location and condition, with cost, conversion snapshot and serials; open projects with their remaining demand lines; open purchases with their remaining quantities; open receivables per open invoice with the remaining value and the due date, or the `migrasi` ageing bucket; opening customer credit with its signed evidence; useful current-year documents as legacy evidence and identified archive items. Old history stays archive, never replayed as current effects. The company masters, accounts, grants, tax configuration and numbering schemes are set up in the application before the import | brief §45; WF-MIG-01; BR-XD-06; L-50 |
| IMP-02 | **The cutoff and the snapshot.** Each batch has one cutoff date and its companies; the legacy snapshot is taken at the cutoff; historical references are kept apart from current effects, so that no replayed purchase duplicates opening stock and no fresh invoice duplicates an imported remainder | GAP-010; DATABASE §24 |
| IMP-03 | **The order of the batches**, from the keys the facts carry: masters first; then opening lots; then open projects with their MIGRATION confirmations and demand lines; then open purchases, whose PURCHASE seed rows commit first and whose project purchases refer to those projects and lines (DATABASE §4.6; AX-28's project lock); then opening receivables and opening credit; then documents and archive — each batch carrying the seeds of HO-32 for the companies it covers | AX-28; NM-08; HO-32; DATABASE §4.6 |
| IMP-04 | **Validation and the dry run.** The same validation path on an isolated copy, the batch left *Uji Coba Lolos* (DRY_RUN_PASSED): rows valid, invalid, duplicate and skipped, each invalid row with its number and reason; a collision outside the preparer's scope refused with MSG-18 alone; the control totals per company — stock quantity and cost, receivables, credits — beside the totals the data steward signs. For the real data the dry run runs in the production application before go-live, by the Admin and the Owner through PG-058, never on staging and never by an agent | AC-17; DEP-05; DP-18; RL-05 |
| IMP-05 | **Sign-off and commit.** The Admin and the Owner each record a sign-off with its evidence (L-33); the Owner commits behind the irreversible confirmation and the step-up; the validation runs again at the commit request, and a batch lacking a seed row for a period its cutoff falls into is refused with the missing type and period (NM-03) | OD-13; IP-08; IP-10; HO-32 |
| IMP-06 | **Chunking, resume and abort.** The commit runs row by row through the ordinary actions with deterministic command identities, in chunks sized from the rehearsal so that each commits well within its job's timeout (JB-07): a retried chunk finds its committed rows done; a refused or FAILED row stops the batch with its reason, and the Owner resumes once the cause is removed — another Owner may resume; committed rows stay committed (PX-05); a lost chunk is re-dispatched from the batch's commit request (JB-09). A batch that has committed no row may be aborted by the Owner with a reason; it stays recorded with its results, and the corrected source is prepared as a new batch. A batch that has committed rows is never aborted or imported again: a wrong opening fact is corrected on its record by contras and reversals by import identity (CM-33) | HO-33; AX-28; M-33; CI-01; CI-08 |
| IMP-07 | **Numbering at the cutover.** Before the dry run, the numbering schemes in force before the cutoff are set up as they were and every scheme of a covered type is settled; each batch carries, for every numbered type of each company it covers, the seeds HO-32 requires — each after every number of its period issued outside, whatever its date, or 1 where there is none —; an imported purchase keeps its legacy number when that number fits its company's PURCHASE scheme, the sequence seeded after the last legacy number — AX-28 refusing a kept legacy number at or above its period's committed seed, which the dry run surfaces (NM-08) —, and any other imported purchase is numbered at the commit from the period of its business date (DIR-079 CB-01; GAP-058); a company's PURCHASE seed before go-live is the import's seed row, after its last legacy purchase number or 1 where it has none, and a company no import seeds starts its PURCHASE numbering on PG-061 once go-live is recorded (`DIR-081` OB-05; NX-01); the Owner sets the starts of the types no import covers on PG-061 (UXH-10). Until a company's seeds commit, NM-03 refuses its every issue | HO-32; NM-03; NM-08; DIR-079 CB-02 |
| IMP-08 | **Reservations** for the open projects' confirmed demand are re-created after the import through the ordinary reservation commands, before go-live; no opening fact reserves by itself | DATABASE §24; WF-MIG-01; GAP-023 |
| IMP-09 | **Units and serials.** Opening lots carry their conversion snapshot, base quantities stay integral for counted and serialized products, and serialized products bring their serials | BR-INV-12; C-09; GAP-025 |
| IMP-10 | **Statistics and plans.** After the commit the planner statistics are refreshed and the hot plans re-checked on the imported data before go-live | HO-36; PERFORMANCE_TARGETS PFM-03 |
| IMP-11 | **Reconciliation.** After the commit `ReconcileGuards` reports no difference; the committed facts are checked against the signed control totals — stock per company equal to its opening lots, receivables equal to the imported remainders, credits equal to the signed opening evidence —, and the validators sign the reconciliation | GAP-010; AC-17; OS-02 |
| IMP-12 | **Raw files stay private.** The legacy files are uploaded by the Admin to the application's private storage as import sources — UTF-8 CSV or XLSX within FL-02's limits —, never into the repository, a ticket or an agent's reach; what Planning may see is the data steward's sanitized structures, counts and totals | DATABASE §24; FL-02; DEP-05; PRODUCTION_DATA_SAFETY |
| IMP-13 | **The rehearsal.** The import is rehearsed on staging with synthetic batches shaped like the legacy data — FX-09 at the volume the data steward reports, counts only — to measure the commit's duration and size the chunks; the freeze is sized from it with the post-import backup's and the production restore drill's measured durations (OPI-25; §7) and the off-hours window they wait for (BACKUP_RECOVERY §5); the real data then gets its dry run on production before go-live (IMP-04) | GAP-010; DEP-05 |

## 6. Cutover and rollback

**The cutover**, in this order, so that no company can issue before its counters are seeded and go-live is recorded once (HO-32), under the freeze or delta of [P10Q-01](#p10q-01--cutover-freeze-or-delta):

| CUT | Step | Who |
| --- | --- | --- |
| CUT-01 | The cutover is agreed: the cutoff, the freeze window or the delta, the order of the batches and the people — operator, Owner, validators, data steward | the Owner |
| CUT-02 | Production set up before any data, GLC-06, GLC-07, GLC-11 and GLC-24 met first — backups, the production configuration, monitoring and the provider features able to act on the host confirmed off before any real data —: the installation step creating the first Owner with its audit event (GAP-049), then the key rotation P9Q-16 may choose (INFRASTRUCTURE §7.4), before the masters; the company masters, accounts, roles and grants, tax configuration and the numbering schemes as they were (HO-32) | the operator; the Owner |
| CUT-03 | Each batch prepared, dry-run, reconciled and signed on the real data (IMP-04, IMP-05), repeated until it passes | the Admin; the Owner |
| CUT-04 | The freeze begins: the legacy system stops issuing and recording; the final snapshot is taken at the cutoff, and its batches are prepared, dry-run, reconciled to the signed totals and jointly signed off (IMP-04; IMP-05) | the Owner; the data steward |
| CUT-05 | A backup verified before the commit; then the Owner commits the batches in IMP-03's order, resuming any stopped one | the operator; the Owner |
| CUT-06 | Reconciliation, statistics and reservations (IMP-08, IMP-10, IMP-11) | the operator; the validators |
| CUT-07 | Go-live recorded once by the operator as the number allocator's setting, after every company's seeds have committed; the Owner's starts for the types no import covers | the operator; the Owner |
| CUT-08 | Open for business: the first issue per company, the heartbeat and alerts watched; the freeze ends | the Owner; the operator |

Under the delta option instead, CUT-04's freeze is replaced by a delta batch with its own cutoff, imported before the company's first issue and seeding again every period in which it finds a number issued outside after the earlier data was taken (HO-32; HO-33).

**Rollback.**

| RBK | Rule | Grounds |
| --- | --- | --- |
| RBK-01 | **Code rollback** is the redeployment of the rollback target, the previous release's commit — possible because the release's migrations were expand-only; no down migration of data runs. It runs in a maintenance window: the queue is drained by the newer workers before the older code returns, and pages of the newer release are reloaded or their commands refused, since the one-release validity of payloads and commands holds forward only (CONCURRENCY_IDEMPOTENCY §15); it is rehearsed in REL-03 | RL-08; HO-31; HO-34 |
| RBK-02 | **Data recovery is separate**: a restore by BACKUP_RECOVERY §4 only, by the operator on the Owner's decision, into isolation and never over live writes; what happened after the recovery point is recorded as lost (BACKUP_RECOVERY §4 step 7), and no numbered type issues again until the Owner has decided how the numbers already used on the business's papers are protected and how the facts on those papers are recorded again — [OQ-29](../07-ux-design/stage2/OWNER_QUESTIONS.md#oq-29--numbers-already-used-on-paper-after-a-restores-recovery-point) —, since a counter restored below them would draw them again (C-10) | RL-04; BKR-05; GAP-011; OQ-29; GAP-064 |
| RBK-03 | **A wrong opening fact** is corrected on its record by contras and reversals by import identity, before and after go-live alike; a committed batch is never imported again (IMP-06) | CM-33; DATABASE §24; WF-MIG-01; ADMIN_FLOW §13 |
| RBK-04 | **A failed step stops** the release: the application goes into maintenance mode when writes are at risk; the operator and the Owner choose between fixing forward, a code rollback and a data recovery, and the choice and its reason are recorded | GAP-012; INFRASTRUCTURE §5 step 8 |
| RBK-05 | **An interrupted migration** is recovered by the procedure its staging rehearsal proved (REL-03) | GAP-012 |
| RBK-06 | **The second application is never touched**: a deployment, rollback, restore or maintenance mode of MULTIPLECORP acts on its own project, database, storage and backups alone, and the shared host proxy changes only by its tested reload; a Scenario B takes the host out of service, which stops the second application too — the client told and involved (INFRASTRUCTURE §7.2 step 3; TT-F06) | INFRASTRUCTURE INF-07, §§2 and 5; BACKUP_RECOVERY BKR-07; the Owner's condition 3 in session |

## 7. Rehearsal and drills

- **The release rehearsal**, every release, on the ephemeral staging stack the operator starts on the VPS with synthetic data and removes afterwards — the migration, the worker restart, the interrupted-migration case, the mixed-version checks and the steps' durations (REL-03; DIR-079 CB-04; GAP-012).
- **The import rehearsal** before the cutover (IMP-13).
- **The failure drill**, on staging before go-live and again after a change to alerting: a stopped worker, a stuck queue, a failed and a stale backup, the disk's warning level — simulated by a lowered threshold, never by filling the shared disk —, a reconciliation difference and a scheduler that missed its run — each signal reaching the operator and the Owner through the zero-cost push channel the operator confirms (OPI-05), with the floor of the in-app notice and the operator's daily look kept (DIR-079 CB-06; GAP-013; INFRASTRUCTURE §6).
- **The restore drill**: rehearsed first on staging's synthetic data; then, after the opening import commits and before go-live is recorded, the operator restores a production recovery point — the generation taken after CUT-06 — into isolation with its paired key (BKR-08), never touching the second application (BKR-07), and times it against the 4-hour RTO and the 24-hour RPO — AC-18's evidence —, with the reconciliation of BACKUP_RECOVERY §4 (GAP-011) — a successful local drill proves no recovery after total VPS or storage loss, which stays accepted under GAP-018 (RISK-001) —; then monthly for the first three months after go-live, then quarterly, and after any change to the backup tooling (DIR-079 CB-12; BK-09; AC-18).
- **H9-14's recovery test**, a Scenario B recovery rehearsal on staging before go-live, with synthetic data and synthetic keys (BACKUP_RECOVERY §5; INFRASTRUCTURE §7.3 step 5): a verified generation restored onto a clean stack, new keys installed with the leaked key never listed, every SX-01 secret the leak may have included rotated, the incident command run in full on the restored state, then Scenario A's steps (2)–(4) — never touching the second application (GLC-26).
- **The tabletop incident-recovery exercise** is P9's, held with the Owner in run PC-4 (BACKUP_RECOVERY §6; GAP-051); its findings are tracked to their owners in BACKUP_RECOVERY §6; those that bind go-live are GLC-16, GLC-20, GLC-21 and GLC-23 (TT-F01, TT-F03, TT-F04, TT-F12, TT-F15, TT-F17, TT-F20, TT-F26, TT-F29), and TT-F27 — a second Owner account — is the business's own decision, no go-live item.

## 8. UAT

UAT with the Owner and the Admin Operasional validators (DEP-06), on staging with synthetic data, before the first release and whenever a release changes what a user does or sees (AICWDF §21). **The P8 obligations it relies on:** TEST_STRATEGY §12's real-device checks — the phone journeys at 390 and 320 CSS px and the desktop composer (GAP-020; DIR-077 CA-02; DIR-079 CD-03, CD-04) — and its UAT items — the message catalogue and glossary (UXH-08), the payment duplicate window (HO-35), each document type's content (GAP-019), the authenticator phones (DEP-11); TEST_STRATEGY §9's screens of CD-06 and §10's per-page copy lists (`DIR-081` OB-04); the critical journeys of TEST_STRATEGY §7; and SECURITY_TEST_MATRIX's and PERFORMANCE_TARGETS' results as the evidence of isolation and of the release-critical performance.

**What P10 adds to it:**

| UAT | Item | Grounds |
| --- | --- | --- |
| UAT-01 | Numbering and go-live at the cutover as PG-061 presents them — the seeds from the import, the Owner's starts for the types no import covers, go-live recorded once by the operator — and ADMIN_FLOW §12's numbering journeys | UXH-10; HO-32 |
| UAT-02 | The opening import with the validators: preparation, the dry-run report, the reconciliation to the signed totals and the sign-off, rehearsed on staging and then done on the real data (§5) | UXH-10; DEP-06; AC-17 |
| UAT-03 | Representative daily work with data entered once and reused, and correct outputs (OS-01); inventory and money trustworthy at opening and after repeated and concurrent work (OS-02) | OS-01; OS-02 |
| UAT-04 | Separate Owner and Admin Operasional UAT evidence, each validator's record kept on its own | ACCEPTANCE_CRITERIA, V1 release completion |

**The record** is TEST_STRATEGY §12's — validator, date, build, tasks, results, dispositions and an issue log —, ending with the Owner's sign-off. **Capacity:** the validators' review time is secured before the UAT is scheduled; a validator who is not available delays the release, and UAT is never shortened to keep a date (GAP-014; RL-02).

## 9. The go-live checklist

Every item is true, with its evidence, before go-live is recorded (CUT-07); each has its owner by role. Go-live waits for every one — P10's rule —, every operator input not available in PC-4 among them (`DIR-081` OB-10).

| GLC | Item | Owner |
| --- | --- | --- |
| GLC-01 | V1's release completion (§4) and the first release's REL-01–REL-07 passed | Planning; the Owner |
| GLC-02 | Every operator input of INFRASTRUCTURE §9 and BACKUP_RECOVERY §7 recorded and its rule applied — the table below; an input not available in PC-4 stays here until it is | the operator |
| GLC-03 | The failure drill passed, the alert channel tested end to end, and its COST_POLICY §4C.4 record written before it is adopted (§7; DIR-079 CB-06) | the operator |
| GLC-04 | The first restore drill of a production recovery point passed and recorded — after the opening import, with its paired key, timed against the RTO and RPO (§7; AC-18; DIR-079 CB-12) | the operator |
| GLC-05 | The disk budget of BACKUP_RECOVERY §3 checked against the measured terms — both production applications' combined use included (OPI-27; INFRASTRUCTURE INF-07) — and the retention of 7 daily, 4 weekly and 3 monthly generations confirmed — a retention the budget cannot hold comes back to the Owner before go-live (DIR-079 CB-11; `DIR-081` OB-10) | the operator; the Owner |
| GLC-06 | Backups scheduled outside business hours, the first verified backup taken, the disk thresholds set and the backup duty named (BK-04; BK-07; BK-11; DIR-079 CB-13, CB-14) | the operator |
| GLC-07 | The production configuration validated on production by the operator, read-only: SX-03 in full — `APP_DEBUG=false`, `APP_ENV=production`, no debug or profiling tooling, cached configuration, a health endpoint without internals, PostgreSQL and Valkey private with authentication, the runtime database role —, WS-10's gates, the database roles and privileges of DATABASE §28 (H9-01), the firewall and bindings of H9-09, secrets present with restricted permissions, TLS and HSTS, the headers of SECURITY_TEST_MATRIX SM-08 and the go-live setting unset until CUT-07; checked before CUT-02 and again at every release (REL-11) | the operator |
| GLC-08 | Outbound HTTPS from the VPS to Google and to the breached-password range service tested (DEP-11; H9-13) | the operator |
| GLC-09 | The production Google OAuth client set up by the Owner before Google sign-in is enabled — otherwise Google sign-in stays off (GAP-042; H9-13) | the Owner |
| GLC-10 | Every high-risk account holder has an authenticator app on their own phone; the Owner's TOTP is enrolled (DEP-11; AU-16) | the account holders |
| GLC-11 | Monitoring live: the business heartbeat, its alerts, the external uptime check — its COST_POLICY §4C.4 record written before it is adopted — and the log viewer (INFRASTRUCTURE §6; DIR-079 CB-07, CB-10) | the operator |
| GLC-12 | `main` protected: required CI, the Owner's approval and no force push (§2) | the Owner |
| GLC-13 | The installation step's audit event recorded with the operator's identity (GAP-049; SM-24) | the operator |
| GLC-14 | The opening import committed, reconciled and signed; reservations re-created; statistics refreshed (§5; CUT-06) | the Owner; the validators; the operator |
| GLC-15 | UAT signed by the Owner and the validators, the per-page copy lists reviewed (§8) | the Owner |
| GLC-16 | The tabletop exercise held — in PC-4 on 2026-10-19, as a discussion with the runbooks of INFRASTRUCTURE §7.1–§7.3 (BACKUP_RECOVERY §6; GAP-051) —, and its pre-go-live findings closed: the alert channel tested, the private incident record and the private contact record of the Owners in place, the dated key register started at the installation (TT-F01; TT-F03; TT-F04; TT-F12) | the operator; the Owner |
| GLC-17 | The validators' review time held and the representative document templates validated (GAP-014; DEP-04) | the Owner |
| GLC-18 | The release integrity report PASS and the Owner's go-live approval (§11) | the Owner |
| GLC-19 | The isolation of the two production applications shown on staging — MULTIPLECORP's own containers, database instance, storage, secrets, backups and deployment —, and its deployment, backup, restore drill and rollback rehearsed without touching the second application (INFRASTRUCTURE INF-07; BKR-07; RBK-06) | the operator |
| GLC-20 | [OQ-26](../07-ux-design/stage2/OWNER_QUESTIONS.md#oq-26--an-off-server-backup-against-total-vps-loss) — the off-server backup — answered by the Owner, and what it decides in place and drilled | the Owner; the operator |
| GLC-21 | INFRASTRUCTURE P9Q-14 and P9Q-15 answered — an alternate operator named, which triggers GAP-041's review, with the alert channel tested on the alternate's phone too, or the single-operator risk accepted by the Owner; and who settles a dispute between recovered pre-window Owners | the Owner |
| GLC-22 | The backup time approved by the Owner (OPI-23), and the provider account's second factor confirmed by its holder (OPI-28) | the Owner; the client |
| GLC-23 | INFRASTRUCTURE P9Q-16 — whether the application key is rotated once after the first Owner, periodically, or only on SX-02's triggers — and [OQ-28](../07-ux-design/stage2/OWNER_QUESTIONS.md#oq-28--the-owners-decision-before-any-restore-on-a-trusted-state) — the Owner's decision before any restore — answered by the Owner; where a rotation is chosen, done by INFRASTRUCTURE §7.4 after the first Owner and before any business data (CUT-02); where OQ-28 is approved, its narrow amendment of SECURITY AU-25 made under change control (BACKUP_RECOVERY §6, TT-F26 and TT-F29) | the Owner; Planning |
| GLC-24 | Every provider feature able to act on the host or its data — automation or AI assistance among them — confirmed off before production data exists, or its reach recorded and accepted by the Owner with the client (OPI-29; INFRASTRUCTURE INF-08) | the client; the Owner |
| GLC-25 | [OQ-29](../07-ux-design/stage2/OWNER_QUESTIONS.md#oq-29--numbers-already-used-on-paper-after-a-restores-recovery-point) — the numbers already used on paper after a restore's recovery point, and the lost facts recorded again — answered by the Owner and its rule in place (RBK-02; GAP-064) | the Owner; Planning |
| GLC-26 | H9-14's recovery test — the Scenario B recovery rehearsal on staging, with synthetic data and synthetic keys — passed and recorded (§7; BACKUP_RECOVERY §5; INFRASTRUCTURE §7.3 step 5) | the operator |

**The operator's inputs and their rules** (GLC-02). Each is recorded by role or as a figure, never as a secret, host name, address, port or account name. The Owner answered in session on 2026-10-19 as the operator ([INFRASTRUCTURE §9](../09-operations/INFRASTRUCTURE.md#9-the-operators-inputs)); staging — every rehearsal and drill with it — cannot start before the inputs needed before staging are recorded, and go-live waits for all of them:

| Input, as P9 defines it | What it decides | Recorded in PC-4 | Needed before |
| --- | --- | --- | --- |
| OPI-01 | the named operator and an alternate (GAP-013; DIR-079 CB-14) | the Owner is the operator; the alternate not yet named (INFRASTRUCTURE P9Q-14) | go-live |
| OPI-02 | the VPS's measured free disk, usage, operating system and bandwidth period | not available; only the plan's nominal figures known | staging |
| OPI-03 | Docker Compose, or Coolify if the VPS already runs it (DIR-079 CB-05) | not known; none assumed installed | staging |
| OPI-04 | DNS at the current provider, or the edge proxy with WS-11's trusted proxy (DIR-079 CB-08) | not available | go-live |
| OPI-05 | the zero-cost push channel, tested by the failure drill (DIR-079 CB-06) | not chosen; a Telegram bot to the operator's and the Owner's phones recommended, its COST_POLICY §4C.4 record before adoption (INFRASTRUCTURE §9) | go-live |
| OPI-06 | the Owner's Google Cloud access for the OAuth clients (GLC-09) | not confirmed; Google sign-in stays off until it is | go-live, if Google sign-in is to be on |
| OPI-07 | measured worker memory, Argon2id timing and PostgreSQL behaviour, deciding the optional scanner (DIR-079 CB-09) and PFM-12 | not available | go-live |
| OPI-08 | outbound HTTPS tested (GLC-08) | not tested | go-live |
| OPI-09 | the installation step's operator identity (GLC-13) | the Owner, as the operator, runs the step | go-live |
| OPI-21 | the measured disk terms, deciding the retention check (DIR-079 CB-11; GLC-05) | not available | staging for U₀ and the free space; go-live for DB, F, L and D |
| OPI-22 | the backup directory's volume and permissions, by role (DIR-079 CB-13) | not determined; MULTIPLECORP's own directory (BKR-07); the off-server question is OQ-26 | staging |
| OPI-23 | the backup time, outside business hours | not determined; 03:00 WIB recommended, checked against OPI-27 and approved before go-live (GLC-22) | go-live |
| OPI-24 | backup and restore duty and its alternate (DIR-079 CB-14) | the Owner; the alternate not yet named (INFRASTRUCTURE P9Q-14) | go-live |
| OPI-25 | the measured durations of a full backup and a restore drill, against the RPO and RTO | not measured | go-live |
| OPI-26 | the client's grant of access to the VPS for the operator | not yet given | staging |
| OPI-27 | the second production application's measured use and its own windows (INFRASTRUCTURE INF-07) | not available | staging |
| OPI-28 | the provider account's holder and its second factor (GAP-018) | the client holds it; its second factor not recorded | go-live |
| OPI-29 | the plan's provider-side features, verified, and those able to act on the host confirmed off (INFRASTRUCTURE INF-08) | not verified; none relied on | staging; before production data for the confirmation (GLC-24) |
| OPI-30 | the application's domain and staging's name, kept outside the repository (INFRASTRUCTURE §2) | added in Part 5's review, not asked in session — owed | staging |
| OPI-31 | every holder of administrative access to the host or the provider account, by role (GAP-041; GAP-018) | added in Part 5's review, not asked in session — owed; the client holds the provider account | go-live |

## 10. Production-release requirements for P11

Requirements the Tasks of P11 must meet — **not Tasks**: P11 cuts its own, each citing the requirement here.

| PRQ | Requirement | Grounds |
| --- | --- | --- |
| PRQ-01 | The deployment definition for the tooling DIR-079 CB-05 settles, with placeholder values only, and the operator's release runbook for REL-08–REL-13 | INFRASTRUCTURE §5; RL-01 |
| PRQ-02 | The staging stack and its synthetic seed, started and removed by the operator | DIR-079 CB-04; TEST_STRATEGY FX-02 |
| PRQ-03 | The health and smoke checks of the business heartbeat, reading only | GAP-013; INFRASTRUCTURE §6 |
| PRQ-04 | Expand-only migrations with the mixed-version safeguards — job payload versions, one-release command validity, the fingerprint algorithm kept for the retention period, no step that flushes the store | HO-31; CP-07 |
| PRQ-05 | The drain of in-flight cascades before a deployment and `ReconcileGuards` after one that changes a posting service | HO-34 |
| PRQ-06 | The opening import of §5: PG-058's preparation, dry run, report, sign-offs, commit, progress, resume and abort, with JB-05 and JB-07, the control-total report per company and the seed validation of HO-32 | ADMIN_FLOW §13; AX-28; HO-32; HO-33 |
| PRQ-07 | Numbering at the cutover: PG-061, the Owner's starts and the operator's one-time go-live command | HO-32; UXH-10 |
| PRQ-08 | The statistics refresh and plan check after the import | HO-36 |
| PRQ-09 | The installation step creating the first Owner with its audit event | GAP-049; AU-25 |
| PRQ-10 | The failure-drill and restore-drill procedures on staging, beside P9's backup tooling | GAP-013; DIR-079 CB-12 |
| PRQ-11 | The release integrity report compiled from the suites' evidence | §11; TEST_STRATEGY §16 |
| PRQ-12 | The verification of the release tooling and of P8's tooling before the first release | TEST_STRATEGY §17; TOOLCHAIN |
| PRQ-13 | The FEATURE_COVERAGE_MATRIX before the planning freeze, with no unexplained missing mapping | GAP-024; REFERENCE_COVERAGE |
| PRQ-14 | The release record — commit, schema version, declarations, report, approval and actual effort — and the application's change log | REL-12; GAP-014 |
| PRQ-15 | The runbooks of INFRASTRUCTURE §7.1–§7.4 — the Owner-account recovery, the key-compromise incident, the post-incident recovery with Scenario B, which AU-25 asks of P9 and P10, and the key rotation and re-encryption — as the operator's working documents, their filled records private, with the tabletop's findings applied | AU-25; H9-14; GAP-051 |

## 11. The release integrity report

AICWDF §39's report, filled from evidence for every release and kept with its release record; a section with a nonzero failure count is FAIL, and the report is PASS only when every section is.

| Section | From | Must read |
| --- | --- | --- |
| TASKS | the Task plan | current total, done; blocked 0, verifying 0, required todo 0 |
| ROUTES | TEST_STRATEGY §8's route crawl and SM-02 | routes reviewed; broken 0; unexpected 404, 405 and 500 0 |
| INTERACTIONS | the INT- tests | critical controls tested; dead 0; unclear 0 |
| CRITICAL JOURNEYS | CJ-01–CJ-22 at Layer 3 | total, passed; failed 0 |
| UI/UX | TEST_STRATEGY §§9–11 | Indonesian default, English, mobile and desktop critical flows, back and navigation integrity and critical accessibility PASS; missing translations 0; redundant critical actions 0 |
| BROWSER | every journey's console and network capture | critical console errors 0; critical failed requests 0 |
| TOOLCHAIN | TOOLCHAIN's rows | required capabilities available: PASS |
| COST | COST_POLICY's inventory | additional recurring production cost; paid exceptions; approval status |
| DATABASE | the release's declaration | DB CHANGE; direct production DB mutation by a coding agent: NO |
| BUILD | TL-13 | production build PASS |
| SECURITY, PERFORMANCE, INTEGRITY | SECURITY_TEST_MATRIX; PERFORMANCE_TARGETS §4; `ReconcileGuards` after Layer 3 on staging | every case PASS; the release-critical set within its targets; no difference |
| RELEASE STATUS | all of the above | PASS / FAIL |

## 12. Architecture challenge and pre-mortem

The whole-system architecture challenge before the planning freeze and the pre-mortem before execution, which the operating model requires, keep their evidence here, never in a second risk register ([AGENT_OPERATING_MODEL](../00-governance/AGENT_OPERATING_MODEL.md#continuous-adversarial-review)); they are run with P11, in PC-5, and none has run yet. **The form:** assume the V1 release failed; for each cause — across modules, data, operations and people —, its warning signs, its prevention, its detection and its recovery, and the finding it opens or cites in [GAP_REGISTER](../00-governance/GAP_REGISTER.md), which stays the finding index.

## 13. Owner questions

Each P10 matter that is the Owner's, with its options and one recommendation; none is adopted before the answer, and none needs a paid service.

### P10Q-01 — Cutover freeze or delta

- **Context.** GAP-010 leaves the cutover's freeze or delta reconciliation to an Owner agreement at P10 (WF-MIG-01; DATABASE §24; HO-33): live operations during the import create a moving baseline, and client operations must never be stopped silently. HO-32 already states how the seeds behave under either option.
- **Options.** (a) **A short, announced freeze**: the legacy system stops issuing and recording from an agreed moment until go-live; the batches' cutoff is the freeze's last day and they seed the freeze's periods (HO-32); its length is set from the import rehearsal's measured duration with the post-import backup and the production restore drill that follow it before go-live (IMP-13; §7), and it is placed outside the busiest days, the clients told in advance. (b) **A delta**: operations continue in the legacy system during the import and the restore drill; a delta batch with its own cutoff is imported before each company's first issue here and seeds again (HO-32; HO-33) — no pause, at the price of a second import and its reconciliation; a delta only adds new facts — a fact imported earlier that changed meanwhile, a payment against an imported receivable or a dispatch from an imported lot, is entered through the ordinary commands or corrected by CM-33, never imported again (DATABASE §24) —, and P10 designs that delta of facts if this option is chosen. (c) **A freeze for stock and documents, a delta for payments received meanwhile**: shorter for the warehouse, two reconciliations for finance, the payments received meanwhile entered through the ordinary commands as (b) states.
- **Recommendation.** (a): one cutoff, one reconciliation and the fewest moving parts, which is GAP-010's protection against double-counted stock and debt; the delta stays available if the measured freeze proves too long.

### P10Q-02 — The release branch model

- **Context.** AICWDF §32's default runs feature branches into `develop`, staging and UAT from there, then a pull request from `develop` to `main` and production from `main`. P9's draft states the model as work through pull requests and CI into `main`, a release rehearsed in staging and production deployed from `main` by the operator (INFRASTRUCTURE §3).
- **Options.** (a) **`main` with release tags**: feature and hotfix branches merge into a protected `main` through pull requests and CI; a release is a tagged commit of `main`, rehearsed, accepted and deployed (§2) — one long-lived branch, the framework's gates kept. (b) **AICWDF's default with `develop`**: an integration branch between features and `main`; staging and UAT from `develop`, then a pull request to `main` — a second long-lived branch to keep in step.
- **Recommendation.** (a): with one operator and a small team, a single protected `main` keeps every gate of §32 — pull request, CI, staging, UAT, approval and production from `main` — without a second branch to reconcile; it matches P9's draft.

### P10Q-03 — The release window

- **Context.** INFRASTRUCTURE §5 allows a maintenance window or a rolling deployment that keeps commands valid for one release (HO-31); GAP-012 makes a changed outage tolerance the Owner's decision. No approved document states when the application may be unavailable.
- **Options.** (a) **Every production deployment in an announced window outside business hours**, the application in maintenance mode for its duration, measured at the rehearsal; the go-live cutover inside P10Q-01's freeze. (b) **Rolling deployments in business hours** when a release needs no window, windows only for those that do. (c) (a) for the first three months after go-live, then (b) on the Owner's later decision.
- **Recommendation.** (c): the first releases run with the operator watching outside business hours while the procedure is new, and the Owner decides on rolling deployments once the release record shows them safe.

## 14. Coverage

Every requirement P10 receives, each mapped by script to this document's sections (RLP): AICWDF §21's declarations and requirements, §32's model and §39's report sections; the brief's §§43, 45–47 and 55; SP-86's list; and every hit of a scripted search of P0–P7's normative documents and P9's drafts for "P10", sources and evidence excluded, grouped by its identifier or, where a line carries none, by its section. Gate OG-3 checks it. **115 requirements, 115 answered, 0 missing.**

| Source | Requirement | Answered in |
| --- | --- | --- |
| AICWDF §21 | AICWDF §21 — DB CHANGE: NONE / APPROVED CONTROLLED MIGRATION | RLP §1, RLP §4 |
| AICWDF §21 | AICWDF §21 — ADDITIONAL RECURRING COST CHANGE: NONE / APPROVED EXCEPTION | RLP §1 |
| AICWDF §21 | AICWDF §21 — required Tasks DONE | RLP §4 |
| AICWDF §21 | AICWDF §21 — CI passes | RLP §4 |
| AICWDF §21 | AICWDF §21 — staging | RLP §4 |
| AICWDF §21 | AICWDF §21 — critical E2E | RLP §4 |
| AICWDF §21 | AICWDF §21 — localization verification | RLP §4 |
| AICWDF §21 | AICWDF §21 — regression | RLP §4 |
| AICWDF §21 | AICWDF §21 — UAT when required | RLP §4 |
| AICWDF §21 | AICWDF §21 — release approval | RLP §4 |
| AICWDF §21 | AICWDF §21 — rollback target | RLP §4 |
| AICWDF §21 | AICWDF §21 — backup readiness when relevant | RLP §4 |
| AICWDF §21 | AICWDF §21 — health check | RLP §4 |
| AICWDF §21 | AICWDF §21 — smoke test | RLP §4 |
| AICWDF §21 | AICWDF §21 — monitoring | RLP §4 |
| AICWDF §32 | AICWDF §32 — the branch and environment model, with P9 | RLP §2, RLP §13 |
| AICWDF §32 | AICWDF §32 — protect `main` | RLP §2, RLP §9 |
| AICWDF §39 | AICWDF §39 — TASKS | RLP §11 |
| AICWDF §39 | AICWDF §39 — ROUTES | RLP §11 |
| AICWDF §39 | AICWDF §39 — INTERACTIONS | RLP §11 |
| AICWDF §39 | AICWDF §39 — CRITICAL JOURNEYS | RLP §11 |
| AICWDF §39 | AICWDF §39 — UI/UX | RLP §11 |
| AICWDF §39 | AICWDF §39 — BROWSER | RLP §11 |
| AICWDF §39 | AICWDF §39 — TOOLCHAIN | RLP §11 |
| AICWDF §39 | AICWDF §39 — COST | RLP §11 |
| AICWDF §39 | AICWDF §39 — DATABASE | RLP §11 |
| AICWDF §39 | AICWDF §39 — BUILD | RLP §11 |
| AICWDF §39 | AICWDF §39 — RELEASE STATUS | RLP §11 |
| AICWDF §29 | AICWDF §29.3 — a migration rehearsed, backed up, approved and run by the controlled executor | RLP §3 |
| Brief §43 | Brief §43 — fresh CI before release | RLP §3 |
| Brief §45 | Brief §45 — the data migration: current masters, prices, opening stock, open projects, open receivables, useful documents; history as archive; data never assumed clean | RLP §5 |
| Brief §46 | Brief §46 — V1 success means real operational work, trustworthy inventory, documents, receivables, payments, monitoring, safe data, backup | RLP §4 |
| Brief §47 | Brief §47 — an operational production release; scope protected after the freeze | RLP §1 |
| Brief §55 | Brief §55 — P10, the implementation and release plan; no fixed date (DIR-035 D1) | RLP §1 |
| SP-86 | The release sequence and the branch and environment model with P9 (AICWDF §32) | RLP §2, RLP §3 |
| SP-86 | The opening import — CB-01's legacy numbers, OB-05's seeds (HO-32; AX-28), GAP-058 — as P10's import design | RLP §5 |
| SP-86 | Cutover and rollback | RLP §6 |
| SP-86 | UAT with the Owner on the application, the P8 obligations it relies on named | RLP §8 |
| SP-86 | The rehearsal on CB-04's ephemeral staging (GAP-012) | RLP §7, RLP §3 |
| SP-86 | The failure drill (GAP-013) | RLP §7 |
| SP-86 | The go-live checklist with OB-10's operator inputs not recorded in PC-4 and their CB rules | RLP §9 |
| SP-86 | The alert channel's test (CB-06) | RLP §9 |
| SP-86 | The restore drill (CB-12) | RLP §9 |
| SP-86 | The operator alone deploying, from `main` | RLP §1 |
| SP-86 | The production-release Tasks P11 must create, listed as requirements | RLP §10 |
| SP-86 | No date reduces scope or a required check | RLP §1 |
| AGENT_OPERATING_MODEL | AGENT_OPERATING_MODEL — operational details planned in P9 and P10; agents only in development, test and staging | RLP §1 |
| AICWDF_ADOPTION | AICWDF_ADOPTION — §21, §32 and §39 and the deployment path pending P10 | RLP §2, RLP §3, RLP §11 |
| GAP_REGISTER | GAP-010 | RLP §5, RLP §13 |
| GAP_REGISTER | GAP-011 | RLP §6, RLP §7 |
| GAP_REGISTER | GAP-012 | RLP §3, RLP §6, RLP §7 |
| GAP_REGISTER | GAP-013 | RLP §3, RLP §7 |
| GAP_REGISTER | GAP-014 | RLP §1, RLP §8, RLP §9 |
| GAP_REGISTER | GAP-018 | RLP §7 |
| GAP_REGISTER | GAP-019 | RLP §8 |
| GAP_REGISTER | GAP-023 | RLP §5 |
| GAP_REGISTER | GAP-024 | RLP §4, RLP §10 |
| GAP_REGISTER | GAP-025 | RLP §5 |
| GAP_REGISTER | GAP-049 | RLP §6, RLP §9, RLP §10 |
| GAP_REGISTER | GAP-058 | RLP §5 |
| GAP_REGISTER | GAP-059 | RLP §5 |
| GAP_REGISTER | GAP-062 | RLP §5 |
| GAP_REGISTER | GAP_REGISTER — every open gap's P10 mechanism or evidence | RLP §5, RLP §7 |
| PRODUCTION_DATA_SAFETY | PRODUCTION_DATA_SAFETY — migration rehearsal, cutover, rollback and the release declaration of DB change | RLP §3, RLP §6 |
| PROJECT_CHARTER | PROJECT_CHARTER — P10, release, migration, cutover, rollback and UAT | RLP §3, RLP §6, RLP §8 |
| ACCEPTANCE_CRITERIA | AC-18 | RLP §7 |
| ACCEPTANCE_CRITERIA | ACCEPTANCE_CRITERIA — P10 owns module and release aggregation | RLP §4 |
| REFERENCE_COVERAGE | REF-061 | RLP §4 |
| REFERENCE_COVERAGE | REFERENCE_COVERAGE — the inventory set's future P10 aggregation | RLP §4 |
| REFERENCE_COVERAGE | REFERENCE_COVERAGE's P10 row — module and release checks, all reference scope, UAT, migration, recovery and deployment evidence and actual effort | RLP §4 |
| V1_SCOPE | DEP-05 | RLP §5 |
| V1_SCOPE | DEP-06 | RLP §8 |
| BUSINESS_RULES | BUSINESS_RULES — live-period overlap rules are P10's (GAP-010) | RLP §13 |
| BUSINESS_RULES | BUSINESS_RULES — the open items at their P10 gate | RLP §5 |
| WORKFLOWS | WF-MIG-01 — the cutover freeze or delta, an Owner agreement at P10 | RLP §5, RLP §13 |
| WORKFLOWS §13 | WORKFLOWS §13 — the cutover freeze or delta and the opening reconciliation rehearsal | RLP §5, RLP §13 |
| WORKFLOWS §14 | WORKFLOWS §14 — the cutover freeze versus delta (GAP-010) | RLP §13 |
| DATABASE | DATABASE — P10 owns the cutover | RLP §6 |
| DATABASE §24 | DATABASE §24 — the rehearsal and the freeze or delta decision | RLP §5, RLP §13 |
| DATABASE §25 | AX-28 | RLP §5 |
| DATABASE §30 | CAP-15 | RLP §5 |
| DATABASE §31 | DATABASE §31 — the opening import rehearsal, control totals and the freeze or delta | RLP §5, RLP §13 |
| SECURITY §2 | AU-25 | RLP §9, RLP §10 |
| SECURITY §10 | H9-14 | RLP §10 |
| CONCURRENCY_IDEMPOTENCY | CONCURRENCY_IDEMPOTENCY — P10 owns release and cutover | RLP §3, RLP §6 |
| CONCURRENCY_IDEMPOTENCY §8 | CONCURRENCY_IDEMPOTENCY §8 — `ReconcileGuards` after a deployment that changes a posting service | RLP §3 |
| CONCURRENCY_IDEMPOTENCY §15 | CONCURRENCY_IDEMPOTENCY §15 — expand-only migrations, deployment and worker restart | RLP §3 |
| CONCURRENCY_IDEMPOTENCY §19 | HO-31 | RLP §3, RLP §6, RLP §10 |
| CONCURRENCY_IDEMPOTENCY §19 | HO-32 | RLP §5, RLP §6 |
| CONCURRENCY_IDEMPOTENCY §19 | HO-33 | RLP §5, RLP §13 |
| CONCURRENCY_IDEMPOTENCY §19 | HO-34 | RLP §3, RLP §10 |
| CONCURRENCY_IDEMPOTENCY §19 | HO-36 | RLP §5 |
| ADMIN_FLOW | ADMIN_FLOW — P10 owns the cutover | RLP §6 |
| ADMIN_FLOW §13 | ADMIN_FLOW §13 — the abort's mechanics | RLP §5 |
| ADMIN_FLOW §16 | UXH-10 | RLP §5, RLP §8 |
| INFRASTRUCTURE §3 | INFRASTRUCTURE §3 — P10 owns the release, its rehearsal and the cutover | RLP §2 |
| INFRASTRUCTURE §4.2 | INFRASTRUCTURE §4.2 — the cutover ordered around the go-live setting | RLP §6 |
| INFRASTRUCTURE §5 | INFRASTRUCTURE §5 — each release's plan, rehearsal, cutover and rollback | RLP §3, RLP §6 |
| INFRASTRUCTURE §11 | INFRASTRUCTURE §11 — AICWDF §32 with P10; the deployment path pending P9 and P10 | RLP §2 |
| BACKUP_RECOVERY §6 | The pre-go-live findings TT-F01, TT-F03, TT-F04 and TT-F12 of the tabletop | RLP §9 |
| BACKUP_RECOVERY §6 | P9Q-16 and OQ-28, answered before go-live (TT-F26; TT-F29) | RLP §9 |
| INFRASTRUCTURE §7 | The runbooks of INFRASTRUCTURE §7.1–§7.4 as working documents of the operator | RLP §10 |
| SECURITY §10 | H9-14 — the recovery test, a Scenario B recovery rehearsal before go-live (R5-03) | RLP §7, RLP §9 |
| AGENT_OPERATING_MODEL | AGENT_OPERATING_MODEL — the architecture challenge and the pre-mortem kept in RELEASE_PLAN | RLP §12 |
| Owner in session | Condition 1 — the two production applications isolated | RLP §6, RLP §9 |
| Owner in session | Condition 2 — capacity from the combined use of both applications | RLP §9 |
| Owner in session | Condition 3 — a backup, restore or rollback of one application never damages the other | RLP §6, RLP §7 |
| Owner in session | Condition 4 — no provider feature assumed active | RLP §9 |
| Owner in session | Condition 5 — every input not available a pre-staging or pre-go-live obligation, never a PASS | RLP §9 |
| Owner in session | OQ-26 — an off-server backup against total VPS loss, for the Owner | RLP §9 |
| Owner in session | P9Q-14 — the alternate operator while the Owner is the operator | RLP §9 |
| INFRASTRUCTURE §9 | OPI-26 | RLP §9 |
| INFRASTRUCTURE §9 | OPI-27 | RLP §9 |
| INFRASTRUCTURE §9 | OPI-28 | RLP §9 |
| INFRASTRUCTURE §9 | OPI-29 | RLP §9 |

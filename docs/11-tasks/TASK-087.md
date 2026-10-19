# TASK-087 — Cutover and go-live

Status: REVIEW | Updated: 2026-10-20 | Owner: Planning

Written by WF-PC5-W's writer of bundle W4 of run PC-5 on 2026-10-19 and 2026-10-20 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99 — a REVIEW draft of P11, approved, or not, with the [Task plan](TASK_PLAN.md) at OG-4. **No Task starts before the Owner's execution authorization at OG-4.** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** TODO

**RISK CLASS:** HIGH

**EXECUTOR:** HUMAN OPERATOR

**OBJECTIVE:**

The human operator runs the cutover of RELEASE_PLAN §6 by TASK-077's runbook, with the Owner, the Admin, the validators and the data steward each in their steps: the cutover agreed and announced; production's masters, accounts, grants, tax configuration and numbering schemes set up after the installation and the rotation; each batch dry-run, reconciled and signed on the real data in the production application; the freeze, a verified backup and the Owner's commit; reconciliation, statistics and reservations; the first restore drill of a production recovery point, timed; then — every go-live item of RELEASE_PLAN §9 holding with its evidence, the release integrity report PASS and the Owner's go-live approval given — go-live recorded once after every company's seeds have committed, and the business opened.

**WHY THIS EXISTS:**

Opening stock and receivables counted twice, or a moving baseline, would corrupt the first live day ([GAP-010](../00-governance/GAP_REGISTER.md#gap-010--opening-stock-and-open-receivables-can-be-counted-twice-at-migration)); the Owner chose a short, announced freeze ([DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-06); HO-32 orders the cutover so that no company issues before its seeds and go-live is recorded once; a production restore drill is AC-18's evidence and keeps GAP-011 open until it passes; and go-live waits for every item of the checklist, passed only by its recorded evidence (RELEASE_PLAN §9; OE-14) — the architecture challenge's sequences 12a and 14b, with GAP-064's raise in place and GAP-014's capacity proven.

**DEPENDS ON:**

- TASK-057 — Opening import — preparation, dry run, sign-off and commit
- TASK-077 — Cutover tooling — the numbering go-live command, the post-import refresh and the rehearsal procedure
- TASK-083 — The alternate operator named and the dispute's governance recorded
- TASK-085 — Installation, the first Owner, two-step enrolment and the APP_KEY rotation
- TASK-086 — UAT with the Owner and the validators

**BLOCKS:**

- none

**PARALLEL SAFE:** NO — the cutover freezes the legacy system and is the plan's last step; nothing runs beside it

**IN SCOPE:**

- **CUT-01**, agreed by the Owner: the cutoff, the freeze window outside the busiest days with its length from TASK-081's import rehearsal (IMP-13), its announcement to the clients in advance, the batches' order and the people — the operator, the Owner, the validators and the data steward (DIR-083 OE-06; DEP-06)
- **CUT-02's remainder**, after TASK-084 and TASK-085: with GLC-06, GLC-07, GLC-11 and GLC-24 met and the installation and the one rotation done, the Owner sets up the company masters, the accounts, roles and grants, the tax configuration and the numbering schemes as they were before the cutoff, every scheme of a covered type settled before the dry run (HO-32; IMP-01; IMP-07); each high-risk account enrolling TOTP at its first sign-in (AU-18)
- **CUT-03 and CUT-04**: each batch prepared, dry-run, reconciled and signed on the real data in the production application through PG-058 by the Admin and the Owner — never on staging and never by an agent —, repeated until it passes; then the freeze — the legacy system stops issuing and recording —, the final snapshot at the cutoff and its batches dry-run, reconciled to the signed totals and jointly signed (IMP-04; IMP-05); the legacy files uploaded by the Admin to the application's private storage only (IMP-12)
- **CUT-05**: a backup verified before the commit and kept until go-live (BK-06); the Owner's commit in IMP-03's order behind the irreversible confirmation and the step-up, a stopped batch resumed once its cause is removed (IMP-06)
- **CUT-06 and GLC-14**: `ReconcileGuards` reporting no difference and the committed facts checked against the signed control totals — stock per company equal to its opening lots, receivables to the imported remainders, credits to the signed opening evidence —, signed by the validators (IMP-11; GAP-010); TASK-077's post-import refresh (IMP-10; HO-36); the reservations of the open projects' confirmed demand re-created through the ordinary commands (IMP-08)
- **GLC-04**: the first restore drill of a production recovery point — the generation taken after CUT-06 — by TASK-076's restore runbook, into isolation with its paired key, timed against the 4-hour RTO and the 24-hour RPO, reconciled and recorded, never touching the second application (RELEASE_PLAN §7; AC-18; BK-09; GAP-011); DB, F, L and D measured after the import and GLC-05's disk budget completed with them (BACKUP_RECOVERY §3)
- **The go-live gate** ([RELEASE_PLAN §9](../10-release/RELEASE_PLAN.md#9-the-go-live-checklist)): every item true with its evidence before go-live is recorded — GLC-01, V1's release completion and the first release's REL-01–REL-07 passed; GLC-14 and GLC-04 above; GLC-18, the release integrity report PASS and the Owner's go-live approval; GLC-25, the Owner's raise after a restore in the release and tested and the restore runbook's step 7 in the operator's working copy (DIR-083 OE-11; architecture challenge 12a); and every other item read from its Task's record — GLC-09 met by TASK-082's production client or by Google sign-in staying off, GLC-16's tabletop findings closed (TT-F01, TT-F03, TT-F04, TT-F12) —, none passed without its evidence (OE-14)
- **CUT-07**: go-live recorded once by the operator with TASK-077's command after every company's seeds have committed (HO-32; architecture challenge 14b); then the Owner's starts on PG-061 for the types no import covers (UXH-10)
- **CUT-08**: open for business — each company's first issue drawing its seeded number, the heartbeat and alerts watched by the operator through the first business day (REL-12) —, the freeze ended
- **A failure during the cutover**: a failed step stops for the operator's and the Owner's decision (RBK-04); a wrong opening fact corrected by contras and reversals by import identity, never imported again (RBK-03); a data recovery only by the restore runbook on the Owner's decision, no numbered type issuing again before the Owner's raise and the lost facts recorded again under new numbers (RBK-02; GAP-064)
- **The release record** (REL-12; PRQ-14): the deployed commit and schema version, the declarations, the report, the Owner's approval, the go-live's time and the actual effort beside the estimates (GAP-014)
- **INF-01 and INF-03**: the release in production is the tagged commit of `main` the operator deployed; no agent touches production, its data or the legacy files at any step (RL-01; RL-05)

**OUT OF SCOPE:**

- the import feature, the tooling and the runbooks themselves (TASK-057, TASK-076, TASK-077)
- a delta batch or a second import of a committed batch (DIR-083 OE-06; RBK-03)
- rolling deployments in business hours during the first three months (DIR-083 OE-08)
- the yearly rotation and the restore drills after go-live at DIR-079 CB-12's cadence — operations routines, no go-live item

**REFERENCES:**

- [RELEASE_PLAN §4](../10-release/RELEASE_PLAN.md#4-release-requirements) — V1's release completion; [§5](../10-release/RELEASE_PLAN.md#5-the-opening-import) IMP-01–IMP-13; [§6](../10-release/RELEASE_PLAN.md#6-cutover-and-rollback) CUT-01–CUT-08, RBK-02–RBK-04; [§7](../10-release/RELEASE_PLAN.md#7-rehearsal-and-drills); [§9](../10-release/RELEASE_PLAN.md#9-the-go-live-checklist) GLC-01, GLC-04, GLC-05, GLC-14, GLC-18, GLC-25 and the rule that go-live waits for every item; [§11](../10-release/RELEASE_PLAN.md#11-the-release-integrity-report); [§13](../10-release/RELEASE_PLAN.md#13-owner-questions) P10Q-01
- [INFRASTRUCTURE §1](../09-operations/INFRASTRUCTURE.md#1-invariants) INF-01, INF-03, INF-07; [§4.2](../09-operations/INFRASTRUCTURE.md#42-configuration) — the go-live setting
- [BACKUP_RECOVERY §3](../09-operations/BACKUP_RECOVERY.md#3-the-shared-disk-budget), [§4](../09-operations/BACKUP_RECOVERY.md#4-the-restore-procedure), [§5](../09-operations/BACKUP_RECOVERY.md#5-restore-testing)
- [V1_SCOPE](../01-product/V1_SCOPE.md#dependencies-and-evidence-needed) DEP-06; [the local-backup contract](../01-product/V1_SCOPE.md#v1-local-backup-and-p9-handoff-contract) BK-09; [ACCEPTANCE_CRITERIA](../01-product/ACCEPTANCE_CRITERIA.md#product-acceptance-contracts) AC-17, AC-18
- [CONCURRENCY_IDEMPOTENCY §19](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s19-20-handoff-traceability.md#19-handoff-obligations) HO-32, HO-33, HO-36; [§14](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s14-17-numbering-jobs-revocation.md#14-numbering-concurrency) NM-03
- [ADMIN_FLOW §12](../07-ux-design/ADMIN_FLOW/s11-13-corrections-numbering-import.md#12-numbering-and-company-onboarding), [§13](../07-ux-design/ADMIN_FLOW/s11-13-corrections-numbering-import.md#13-opening-import); [§16](../07-ux-design/ADMIN_FLOW/s16-17-handoff-traceability.md#16-handoff-obligations) UXH-10
- [GAP_REGISTER](../00-governance/GAP_REGISTER.md#gap-010--opening-stock-and-open-receivables-can-be-counted-twice-at-migration) GAP-010; [GAP-011](../00-governance/GAP_REGISTER.md#gap-011--a-database-restore-alone-cannot-recover-usable-business-documents); [GAP-014](../00-governance/GAP_REGISTER.md#gap-014--eighteen-days-is-a-target-not-a-measured-delivery-capacity); [GAP-064](../00-governance/GAP_REGISTER.md#gap-064--numbers-already-used-on-paper-after-a-restores-recovery-point)
- [DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-06, OE-08, OE-11, OE-14
- Architecture challenge — the sequences 12a, 14b of the [P11 gate record](evidence/P11_QUALITY_GATE.md#whole-system-architecture-challenge-sp-98), their assertions carried into this Task's acceptance criteria and tests
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (23): DEP-06; BK-09; INF-01; INF-03; CUT-01–CUT-08; GLC-01; GLC-04–GLC-05; GLC-14; GLC-16; GLC-18; GLC-25; GAP-010–GAP-011; GAP-014; GAP-064

**EXPECTED MODULES / FILE AREAS:**

- no code; the cutover record, the go-live checklist with each item's evidence and the release record, kept by the operator and the Owner without real data and summarized in COMPLETION EVIDENCE; the legacy files and every real fact stay in production and the private records only

**BUSINESS RULES:**

- the opening facts as WF-MIG-01 and DATABASE §24 define them — opening lots, open projects and purchases, open receivables and opening credit as migration facts, old history archive (BR-XD-06; L-50) —; CM-33 for a wrong opening fact; C-10 for numbers, legacy ones included; the raise after a restore as NM-03 and NX-01 state it

**ARCHITECTURE RULES:**

- the real data enters only through the production application — PG-058's import and the ordinary actions with deterministic command identities (AX-28) —, never by direct database access (PRODUCTION_DATA_SAFETY)
- go-live is a setting recorded once and never changed afterwards (INFRASTRUCTURE §4.2); a code rollback is never data recovery (RL-04)

**SECURITY / PERMISSION REQUIREMENTS:**

- the commit only by `import.commit`, OWNER_ONLY, after the joint sign-off, behind the irreversible confirmation and the step-up (OD-13; IP-08; IP-10)
- the masters, accounts, grants and numbering by their OWNER_ONLY capabilities (OD-01; OD-03; RG-05); each new high-risk account with TOTP at its first sign-in (AU-18)
- the legacy files private (IMP-12; FL-06); no agent touches production or its data (INF-01; SX-04)

**COMPANY SCOPE AND FIELD PROJECTION:**

- the Owner works over every company (CS-01); the Admin prepares batches within its granted companies, a collision outside its scope refused with MSG-18 alone (DP-18); each company's seeds and starts stay its own (NM-03); field projection applies to every page and report as built (PJ-01–PJ-23)

**CONCURRENCY / IDEMPOTENCY:**

- the commit's chunks replay under deterministic command identities, a retried chunk finding its rows done and a stopped batch resumed by another Owner (CI-01; CI-08; JB-07; JB-09; M-33; AX-28); a seed only ever raises a counter (HO-33; BD-11)
- go-live recorded once (HO-32); the raise after a restore once per recovery, never lowering a counter, at LK-31 (NX-01); the reservations re-created through the ordinary reservation commands under their own locks (IMP-08)

**AUDIT:**

- the masters, accounts, roles, grants, tax configuration and numbering changes audited as privileged changes (LG-01); the sign-offs (L-33) and the import commit audited (LG-01); the go-live recording entered in the cutover and release records with its time and the operator by role (REL-12); a raise after a restore audited with the incident (NM-03)

**DATABASE IMPACT ASSESSMENT:**

- Schema change: NONE — no repository change; production data untouched except through the approved path
- Migration required: NO
- Existing data transformation: NONE — the opening facts are created through the application's import and ordinary actions, never by transforming data in place (DATABASE §24)
- Direct production DB access: FORBIDDEN
- Data-loss risk: bounded, not none — a restore during or after the cutover loses what happened after its recovery point, recorded as lost and entered again under new numbers (RBK-02); the verified pre-commit backup and the post-import generation bound it (CUT-05; GLC-04); total VPS loss stays RISK-001, narrowed by the off-server copy

**MODULARITY / EXTENSIBILITY:**

- Module boundary affected: none — no code
- Stable core kept provider-neutral: YES — no provider feature is relied on (INF-08)
- External/vendor-specific code isolated: N/A — no code
- Replaceable adapter/contract required: NO
- Provider lock-in risk: NONE
- Future extension impact: LOW
- Speculative abstraction avoided: YES — one freeze, one cutoff, no delta (DIR-083 OE-06)

**AUTH IMPACT:**

- Authentication change: NONE — the accounts are created and enrolled as built
- Authentication implementation: N/A — no code; the profile stays LARAVEL_NATIVE ([EXECUTION_CONTEXT](EXECUTION_CONTEXT.md))
- Auth profile: AICWDF-COMPAT-8 — unchanged; each new account sets its password through its credential link (AU-11; AU-12)
- Google sign-in: ON in production only if TASK-082's production client exists, otherwise off (GLC-09)
- Google registration behavior: EXISTING_ACCOUNT_ONLY — accounts are created by the Owner, never by Google (AU-22)
- Google credentials owner action required: NO — the production client, if any, is TASK-082's
- Authorization change: NONE — the roles and grants are set through the built OWNER_ONLY commands
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

- Context7: N/A — the operator and the Owner run the built application and TASK-077's runbook
- Graphify: N/A — no code
- Browser E2E: N/A — no journey is added; the Admin and the Owner work in PG-058 and PG-061 as built
- Other: TASK-077's cutover runbook, go-live command and refresh; TASK-076's restore runbook; TASK-074's tooling; the data steward's sanitized structures, counts and signed totals, the legacy files themselves staying in production's private storage
- Missing safe tool behavior: N/A — nothing is installed during the cutover

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: N/A — no code
- Pre-change questions: the cutoff and the freeze's length from the import rehearsal; the order of the batches; which numbered types no import covers; which go-live items still lack their evidence
- Expected affected areas: production's business data, entered through the application only

**CONTEXT7:**

- Topics to verify: N/A — no library is configured
- Version-sensitive assumptions: none — production runs the release's tagged commit as built

**UI / UX REQUIREMENTS:**

- Follow global design rules: N/A — no page is built or changed; PG-058 and PG-061 are used as built
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
- Destructive actions: N/A — no page; the commit is irreversible by design, behind its confirmation and step-up (IP-08; IP-10)
- Redundancy constraints: N/A — no page is built or changed
- Loading state: N/A — no page is built or changed
- Empty state: N/A — no page is built or changed
- Error state: N/A — no page; a refused or FAILED row stops its batch until the Owner resumes it (IMP-06)

**ROUTE IMPACT:**

- none — the release's routes are used as built; the go-live command is console only

**INTERACTION CONTRACTS:**

- N/A — no interaction is built or changed

**NAVIGATION CONTRACT:**

- Parent: N/A — no page is built or changed
- Entry: N/A — no page is built or changed
- Back action: N/A — no page is built or changed
- Cancel: N/A — no page is built or changed
- Success destination: N/A — no page is built or changed
- Error behavior: N/A — no page is built or changed
- State preservation: N/A — no page is built or changed
- Mobile behavior: N/A — no page is built or changed

**ACCEPTANCE CRITERIA:**

1. The cutover is agreed and announced to the clients in advance, the freeze's length taken from the import rehearsal and placed outside the busiest days (CUT-01; DIR-083 OE-06).
2. Every batch is dry-run, reconciled and signed on the real data in the production application by the Admin and the Owner before the commit, never on staging and never by an agent (CUT-03; CUT-04; IMP-04; IMP-05).
3. A verified backup precedes the commit; after it `ReconcileGuards` reports no difference, stock, receivables and credits equal the signed control totals with the validators' signatures, the statistics are refreshed and the reservations re-created (CUT-05; CUT-06; GLC-14; GAP-010).
4. The first restore drill of a production recovery point taken after CUT-06 restores into isolation with its paired key within the RTO and the RPO and is recorded, the second application untouched (GLC-04; AC-18; GAP-011).
5. Go-live is recorded once, by the operator, only after every company's seeds have committed and every go-live item holds with its evidence, the release integrity report PASS and the Owner's go-live approval given (CUT-07; GLC-01; GLC-18; architecture challenge 14b).
6. The Owner's raise after a restore is in the release and tested, and the restore runbook's step 7 is in the operator's working copy (GLC-25; architecture challenge 12a).
7. Each company's first issue draws its seeded number, and the heartbeat and alerts are watched through the first business day (CUT-08; REL-12).
8. No agent touched production, its data or the legacy files at any step (INF-01; RL-05).

**TEST REQUIREMENTS:**

- Lint: N/A — no code in this Task
- Typecheck: N/A — no code in this Task
- Unit: N/A — no code in this Task
- Feature: N/A — no route or command is built
- Integration: N/A — no code; the import's own dry run and reconciliation on the real data are the evidence (IMP-04; IMP-11)
- Authorization: N/A — no route; the commit's OWNER_ONLY rule used as built (OD-13)
- Route integrity: N/A — no route added or changed
- Interaction: N/A — no INT- contract is built
- Browser E2E: N/A — no journey is added; CJ-16 is TASK-064's
- Localization/language switch: N/A — no user-facing copy is added
- Visual breathing room/density: N/A — no page in this Task
- Responsive: N/A — no page in this Task
- Accessibility: N/A — no page in this Task
- Production build: N/A — production runs the release's tagged commit as CI built it
- Security: no SECURITY_TEST_MATRIX case runs on production (§9); GLC-07's read-only check repeated by the operator if a release is deployed in the window (REL-11)
- Performance: N/A — PFM-03's plans re-checked on the imported data by TASK-077's refresh at CUT-06
- Migration: N/A — no migration code; the real data enters through the import, whose dry run on production is its proof (IMP-04)
- Other: the go-live checklist, item by item with the record that evidences it, before CUT-07

**TEST CHECKLIST:**

- [ ] Lint — N/A: no code in this Task
- [ ] Typecheck — N/A: no code in this Task
- [ ] Relevant automated tests — N/A: no code; the cutover record — each CUT- step with its people and result, the reconciliation, the production restore drill and the go-live checklist with each item's evidence — stands in their place
- [ ] Authorization — the commit by `import.commit` after the joint sign-off; the masters, grants and numbering by their OWNER_ONLY capabilities
- [ ] Route integrity — N/A: no route added or changed
- [ ] Interaction behavior — N/A: no interaction built or changed
- [ ] Browser E2E if required — N/A: no journey is added
- [ ] Responsive/adaptive if required — N/A: no page
- [ ] Visual breathing room/density if UI — N/A: no page
- [ ] Production build — N/A: production runs the release's tagged commit
- [ ] Security checks if required — the legacy files only in production's private storage; no agent access at any step
- [ ] Database safety review — the real data entered only through the application; the verified pre-commit backup and the post-import drill recorded
- [ ] Final diff review — only the cutover and release records' summaries; no real data, secret or host detail in them

**COMPLETION EVIDENCE:**

- Test outputs: the operator's and the Owner's cutover record — CUT-01–CUT-08 each with its people, time and result, the dry runs and sign-offs, the reconciliation with the validators' signatures, the production restore drill timed against the RTO and RPO, the go-live checklist with each item's evidence, go-live's recorded time — in [TEST_STRATEGY §16](../08-testing/TEST_STRATEGY.md#16-evidence)'s form without real data; go-live waits for every item (DIR-083 OE-14)
- Build result: N/A — no build in this Task; the deployed tagged commit is named in the release record
- Route evidence: N/A — no route added or changed
- Interaction evidence: N/A — no INT- contract in this Task
- Browser evidence: N/A — no journey in this Task
- Localization evidence: N/A — no page in this Task
- Cost evidence/exception if applicable: ADDITIONAL RECURRING COST CHANGE: NONE (DOD-20)
- Files changed: the release record's and the cutover record's summaries — nothing holding real data
- Commit SHA: the commit that records them, under the Owner's identity, with no AI attribution
- Notes: each non-applicable item with its reason (DOD-03); every go-live item named with the Task whose record evidences it; the DONE update of TASK_PLAN, this file and CURRENT_HANDOFF (AICWDF §22.3), made on the operator's and the Owner's records and never on an agent's claim (TASK_PLAN §2)

**SAFE NEXT TASK:**

- none — the last Task of the plan

**DO NOT DO:**

- add to V1 — a capability, page, route, field, integration, document type, report or paid service — or drop, defer or narrow a V1_REQUIRED requirement; a Task that would need either raises an Owner question and waits ([DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-20)
- access the VPS, production, its data or a credential, or put a secret, real data, a host detail or production configuration in the repository ([INFRASTRUCTURE](../09-operations/INFRASTRUCTURE.md) INF-01, INF-05; SECURITY SX-01, SX-04, SX-06; [PRODUCTION_DATA_SAFETY](../00-governance/PRODUCTION_DATA_SAFETY.md))
- add AI, model or tool attribution to a commit — the Owner's configured identity only ([AGENTS.md](../../AGENTS.md#git-commit-attribution); DIR-022)
- start before the Owner's execution authorization at OG-4, or mark DONE without the required tests passing and the completion evidence ([TASK_PLAN](TASK_PLAN.md#2-rules-of-the-plan); [DEFINITION_OF_DONE](../08-testing/DEFINITION_OF_DONE.md#2-the-checklist))
- weaken, skip or delete a valid test to obtain a green run, or adopt a paid tier without the Owner's COST_POLICY decision (DOD-05; [COST_POLICY](../00-governance/COST_POLICY.md))
- record go-live before every company's seeds have committed and every go-live item holds with its evidence (CUT-07; RELEASE_PLAN §9; DIR-083 OE-14)
- import a committed batch again, add a delta batch, or correct a wrong opening fact by importing (RBK-03; DIR-083 OE-06)
- let an agent touch production, its data, the legacy files or any step of the cutover (INF-01; RL-05)
- let a numbered type issue again after a restore before the Owner's raise (RBK-02; GLC-25)

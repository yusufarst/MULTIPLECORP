# TASK-073 — Release process — branch protection, release tags, the release record and integrity report

Status: APPROVED | Updated: 2026-10-20 | Owner: Planning

Approval: [APPR-016](../00-governance/DECISION_LOG.md#appr-016--p11-task-plan-tasks-and-feature-coverage-matrix-approved), explicit Owner approval on 2026-10-20 (DIR-085 OF-07) of this document as committed in the first execution session's approval commit; the approved file hash and the verified conditions are recorded there. Approval changes lifecycle only, not implementation authorization.

Written by WF-PC5-W's writer of bundle W4 of run PC-5 on 2026-10-19 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99; the Owner approved P11 with the [Task plan](TASK_PLAN.md) at OG-4 (DIR-085 OF-07). **No Task starts before the freeze record, from which the execution authorization is in force (DIR-085 OF-09).** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** READY

**RISK CLASS:** HIGH

**EXECUTOR:** AGENT

**OBJECTIVE:**

Build the release process every release follows: the branch and tag model DIR-083 OE-07 settles — feature and hotfix branches through pull requests and CI into a protected `main`, a release a tagged commit of `main` whose CI passed —, the branch-protection settings written for the Owner to apply, the declarations of RL-03, the release record and the application's change log of PRQ-14, and the release integrity report of RELEASE_PLAN §11 compiled from the suites' evidence (PRQ-11), PASS only when every section is.

**WHY THIS EXISTS:**

A release passes on evidence only — its integrity report PASS and the Owner's approval; "should work" is not evidence ([RELEASE_PLAN §1](../10-release/RELEASE_PLAN.md#1-principles) RL-06; OS-06) — and every deployment comes from `main` by the human operator (INF-03; RL-01). REL-01–REL-13 order each release, GLC-01 and GLC-18 need the first release's REL-01–REL-07 passed and its report PASS, and GAP-017 warns that green checks can miss the real risks unless each check's run is evidenced. PRQ-11 and PRQ-14 ask P11 for the report and the release record.

**DEPENDS ON:**

- TASK-003 — CI pipeline, test layers and the synthetic fixture harness
- TASK-004 — Verification and release tooling check

**BLOCKS:**

- TASK-084 — Production preparation — configuration, outbound HTTPS, branch protection, backups and the off-server copy

**PARALLEL SAFE:** NO — it changes the CI configuration and the release files that every release, and TASK-084's branch protection, read

**IN SCOPE:**

- **The branch and tag model** of [RELEASE_PLAN §2](../10-release/RELEASE_PLAN.md#2-the-branch-and-environment-model) (DIR-083 OE-07): feature and hotfix branches through pull requests and CI — TEST_STRATEGY Layer 1 — into a protected `main`; a release a tagged commit of `main` whose CI passed, Layer 2 for the modules it touches; the tag's naming recorded in the release runbook as the executor's routine choice; no `develop` branch
- **The branch protection** of `main` written as the settings list the Owner applies (GLC-12; TASK-084): the required CI checks, named after TASK-003's jobs, the Owner's approval of every pull request, no force push; the agent changes no repository setting (RELEASE_PLAN §2: a repository setting the Owner makes)
- **CI as the gate, never the deployer**: the release workflow accepts a tag only on a commit of `main` whose CI passed; no CI job, tag rule or trigger deploys, migrates, imports, backs up or restores, and CI never holds a production secret (INF-03; RL-01; SX-05)
- **REL-01, Ready**: the release's required Tasks DONE under DEFINITION_OF_DONE, read from the Task plan's counters; CI green on the release commit; Layer 2's regression passed for every module the release touches (TEST_STRATEGY §3); the module aggregation of DEFINITION_OF_DONE §5 for each module the release counts
- **REL-02, Declarations**: `DB CHANGE: NONE / APPROVED CONTROLLED MIGRATION` and `ADDITIONAL RECURRING COST CHANGE: NONE / APPROVED EXCEPTION` (RL-03; COST_POLICY), and the rollback target — the previous release's commit, its schema version and the pre-deployment backup whose verified completion BK-03 requires before the deployment proceeds (REL-08)
- **REL-04 and REL-05** as release-checklist items for the operator and the Owner: Layer 3 on staging and, for the first release and after a change to alerting or backup tooling, the failure and restore drills (RELEASE_PLAN §7); UAT whenever the release changes what a user does or sees, its record TEST_STRATEGY §12's (RELEASE_PLAN §8)
- **REL-06, the release integrity report** of [RELEASE_PLAN §11](../10-release/RELEASE_PLAN.md#11-the-release-integrity-report) (PRQ-11): TASKS, ROUTES, INTERACTIONS, CRITICAL JOURNEYS, UI/UX, BROWSER, TOOLCHAIN, COST, DATABASE, BUILD, SECURITY, PERFORMANCE and INTEGRITY, compiled by script from the CI artefacts and the operator's staging summaries entered without secrets (TEST_STRATEGY §16); a section with a nonzero failure count FAIL, RELEASE STATUS PASS only when every section is; a planned check never counted as run (GAP-017)
- **REL-07, the Owner's release approval** on the report, the declarations and the UAT record, recorded with its date
- **REL-03 and REL-08–REL-13**, the operator's steps of TASK-072's runbook, whose results the release record keeps — the rehearsal's durations, the verified backup, the deployment's window, the verification, the deployed commit and schema version, the first business day watched, a contract step only in a later release
- **PRQ-14, the release record**: the commit and its tag, the schema version, the declarations, the report, the Owner's approval and the actual effort beside the estimates (REL-12; GAP-014) — and the application's change log, one entry per release
- **The principles as checks** of the process (RL-01–RL-08): no date reduces scope or a required check, a tradeoff going to the Owner with its evidence (RL-02); every release declares DB and cost changes (RL-03); a code rollback is not data recovery (RL-04); no release artifact holds production or legacy data (RL-05); evidence only (RL-06); after the freeze a change outside the release's Tasks enters only on RL-07's grounds and with the Owner's approval (DIR-083 OE-20); expand now, contract later (RL-08)
- **INF-03**: the release record names the deployed commit, which is the tagged commit of `main` the report covers

**OUT OF SCOPE:**

- applying the branch protection — the Owner's repository setting, recorded in TASK-084 (GLC-12)
- the deployment definition and the operator's runbook steps (TASK-072); running any step on staging or production (the operator Tasks)
- the suites whose evidence the report reads (TASK-062–TASK-070) and the tooling verification (TASK-004)
- the first release's own report, approval and go-live (GLC-01, GLC-18; TASK-087)

**REFERENCES:**

- [RELEASE_PLAN §1](../10-release/RELEASE_PLAN.md#1-principles) RL-01–RL-08; [§2](../10-release/RELEASE_PLAN.md#2-the-branch-and-environment-model); [§3](../10-release/RELEASE_PLAN.md#3-the-release-sequence) REL-01–REL-13; [§4](../10-release/RELEASE_PLAN.md#4-release-requirements) — AICWDF §21's requirements and the module and release aggregation
- [RELEASE_PLAN §9](../10-release/RELEASE_PLAN.md#9-the-go-live-checklist) GLC-01, GLC-12, GLC-18; [§10](../10-release/RELEASE_PLAN.md#10-production-release-requirements-for-p11) PRQ-11, PRQ-14; [§11](../10-release/RELEASE_PLAN.md#11-the-release-integrity-report); [§13](../10-release/RELEASE_PLAN.md#13-owner-questions) P10Q-02, decided by DIR-083 OE-07
- [INFRASTRUCTURE §1](../09-operations/INFRASTRUCTURE.md#1-invariants) INF-03; [§3](../09-operations/INFRASTRUCTURE.md#3-environments); [§5](../09-operations/INFRASTRUCTURE.md#5-the-deployment-path)
- [V1_SCOPE](../01-product/V1_SCOPE.md#v1-local-backup-and-p9-handoff-contract) BK-03
- [TEST_STRATEGY §3](../08-testing/TEST_STRATEGY.md#3-the-three-regression-layers), [§16](../08-testing/TEST_STRATEGY.md#16-evidence)
- [DEFINITION_OF_DONE §5](../08-testing/DEFINITION_OF_DONE.md#5-module-and-release-completion)
- [ACCEPTANCE_CRITERIA](../01-product/ACCEPTANCE_CRITERIA.md#feature-module-and-release-completion-obligations) — the release obligations; OS-06
- [GAP_REGISTER](../00-governance/GAP_REGISTER.md#gap-017--green-checks-may-miss-the-actual-concurrency-and-workload-risks) GAP-017; [GAP-014](../00-governance/GAP_REGISTER.md#gap-014--eighteen-days-is-a-target-not-a-measured-delivery-capacity)
- [COST_POLICY](../00-governance/COST_POLICY.md) — the cost declaration
- [SECURITY §7](../05-security/SECURITY.md#7-secrets-production-boundary-and-supply-chain) SX-05, SX-06
- [DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-07, OE-14, OE-20
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (27): BK-03; OS-06; INF-03; RL-01–RL-08; REL-01–REL-13; PRQ-11; PRQ-14; GAP-017

**EXPECTED MODULES / FILE AREAS:**

- the release workflow and the report compiler in TASK-003's CI configuration and `scripts/`; the release runbook's process part, the release-record template and the branch-protection settings list in the repository's operations documentation; the application's change log

**BUSINESS RULES:**

- N/A — no business rule; RL-07's scope rule and DIR-083 OE-20 bind what a release may contain

**ARCHITECTURE RULES:**

- one long-lived, protected `main`; a release is its tagged commit (OE-07); CI reads, tests and reports, and never deploys (RL-01)
- the report is derived from the runs' evidence by script, never typed by hand; its sections are exactly §11's

**SECURITY / PERMISSION REQUIREMENTS:**

- no secret, host detail or real data in the report, the record or the change log (SX-06; RL-05; DOD-21); the operator's staging evidence enters as a summary without secrets
- CI actions pinned by commit SHA, lockfile-only installs and no production secret in CI (SX-05; SM-15)
- every commit under the Owner's configured identity, with no AI attribution (DIR-022); the branch protection makes the Owner approve each pull request

**COMPANY SCOPE AND FIELD PROJECTION:**

- N/A — no data path; the process reads test evidence on synthetic data only

**CONCURRENCY / IDEMPOTENCY:**

- N/A — no command, lock or job of the application

**AUDIT:**

- N/A — no business action; the release record and the change log are the trail of what each release shipped, with the Owner's approval and the deployed commit recorded (REL-07; REL-12; PRQ-14)

**DATABASE IMPACT ASSESSMENT:**

- Schema change: NONE — no table or column
- Migration required: NO
- Existing data transformation: NONE
- Direct production DB access: FORBIDDEN
- Data-loss risk: NONE KNOWN

**MODULARITY / EXTENSIBILITY:**

- Module boundary affected: none — CI and release files outside the application's modules
- Stable core kept provider-neutral: YES — the release record and report are plain repository files
- External/vendor-specific code isolated: YES — the repository host's workflow files only
- Replaceable adapter/contract required: NO
- Provider lock-in risk: NONE beyond the public repository's CI the project already uses at no cost
- Future extension impact: LOW
- Speculative abstraction avoided: YES — no release-management service or dashboard

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

- Context7: REQUIRED — the repository host's branch-protection settings, tag-triggered workflows and artefact retention at their current version
- Graphify: N/A — no application code
- Browser E2E: N/A — no page
- Other: TASK-003's CI and the tools TASK-004 verified
- Missing safe tool behavior: AUTO-INSTALL project-locally inside this Task (AICWDF §6.3) and record it in TOOLCHAIN; a paid tier or a privileged installation STOPS for the Owner (COST_POLICY)

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: N/A — no application code; the impact is read from the CI files and RELEASE_PLAN §11
- Pre-change questions: which CI jobs and artefacts feed each section of the report; which checks are staging-only and come from the operator's summaries
- Expected affected areas: the CI configuration, `scripts/` and the release documentation only

**CONTEXT7:**

- Topics to verify: required status checks, review rules and force-push protection on the repository host; workflows triggered by a tag and the commit's branch check; artefact retention
- Version-sensitive assumptions: none from memory

**UI / UX REQUIREMENTS:**

- Follow global design rules: N/A — no page; the report and record are documents for the Owner and the operator
- Indonesian default: N/A — no page
- English support: N/A — no page
- Plain-language Indonesian: N/A — no page
- Language switch impact: NONE
- Mobile-first: N/A — no page
- Responsive: N/A — no page
- Adaptive: N/A — no page
- Visual breathing room: N/A — no page
- Comfortable density: N/A — no page
- shadcn/ui reuse: N/A — no page
- Design reference: N/A — no page
- Primary action: N/A — no page
- Secondary actions: N/A — no page
- Destructive actions: N/A — no page
- Redundancy constraints: N/A — no page
- Loading state: N/A — no page
- Empty state: N/A — no page
- Error state: N/A — no page; a failed section reads FAIL with its count

**ROUTE IMPACT:**

- none

**INTERACTION CONTRACTS:**

- N/A — none

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

1. The release workflow refuses a tag on a commit that is not on `main` or whose CI did not pass, and no workflow deploys, migrates or reaches staging or production (INF-03; RL-01).
2. Fed fixture evidence, the report writes every section of RELEASE_PLAN §11, marks a section FAIL on any nonzero failure count and RELEASE STATUS PASS only when every section passes (PRQ-11; RL-06).
3. A check with no run's evidence is reported as not run, never as PASS (GAP-017; TEST_STRATEGY §16).
4. The release record holds the commit and tag, the schema version, both declarations of RL-03, the report, the Owner's approval, the rollback target with its verified pre-deployment backup, and the actual effort (PRQ-14; REL-02; BK-03; GAP-014).
5. The branch-protection settings list names the required checks, the Owner's approval of pull requests and no force push, ready for the Owner to apply (GLC-12).
6. The secret-like scan finds nothing in the record, the report or the change log, and none holds real data (SM-15; RL-05).

**TEST REQUIREMENTS:**

- Lint: TL-01 on the scripts
- Typecheck: TL-02 where the scripts are typed
- Unit: the report's section rules on fixture evidence — a pass, a failure, a missing run (TL-03)
- Feature: N/A — no route or command in this Task
- Integration: N/A — no database, queue or job path; the release workflow is run in CI on a test tag instead — refused off `main` and on a red commit, with no deploy step
- Authorization: N/A — no route, query class or command in this Task
- Route integrity: N/A — no route added or changed
- Interaction: N/A — no INT- contract in this Task
- Browser E2E: N/A — no page in this Task
- Localization/language switch: N/A — no user-facing copy in this Task
- Visual breathing room/density: N/A — no page in this Task
- Responsive: N/A — no page in this Task
- Accessibility: N/A — no page in this Task
- Production build: N/A — no application code; the report's BUILD section reads TL-13's result
- Security: SM-15's gates on the release workflow — the secret scan, SHA-pinned actions, lockfile-only installs (TL-14)
- Performance: N/A — the report's PERFORMANCE section reads the release-critical set of PERFORMANCE_TARGETS §4 measured on staging (TASK-079)
- Migration: N/A — no migration or import code in this Task
- Other: the report's sections compared one by one with RELEASE_PLAN §11's table, and the record's fields with PRQ-14 and REL-02

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
- Build result: N/A — no application code in this Task; the release workflow's CI run on a test tag stands in its place
- Route evidence: the route audit for the routes this Task adds or changes, or N/A with its reason
- Interaction evidence: the INT- tests of this Task, or N/A with its reason
- Browser evidence: `E2E: PASS — Evidence: …` for its journeys (DOD-18), or N/A with its reason
- Localization evidence: both languages on every page it changes (DOD-16), or N/A with its reason
- Cost evidence/exception if applicable: ADDITIONAL RECURRING COST CHANGE: NONE (DOD-20)
- Files changed: the list, every one inside the IN SCOPE areas
- Commit SHA: the commit under the Owner's identity, with no AI attribution
- Notes: each non-applicable item with its reason (DOD-03); the DONE update of TASK_PLAN, this file and CURRENT_HANDOFF (AICWDF §22.3)

**SAFE NEXT TASK:**

- TASK-084 — when its other dependencies are DONE and its READY gate holds

**DO NOT DO:**

- add to V1 — a capability, page, route, field, integration, document type, report or paid service — or drop, defer or narrow a V1_REQUIRED requirement; a Task that would need either raises an Owner question and waits ([DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-20)
- access the VPS, production, its data or a credential, or put a secret, real data, a host detail or production configuration in the repository ([INFRASTRUCTURE](../09-operations/INFRASTRUCTURE.md) INF-01, INF-05; SECURITY SX-01, SX-04, SX-06; [PRODUCTION_DATA_SAFETY](../00-governance/PRODUCTION_DATA_SAFETY.md))
- add AI, model or tool attribution to a commit — the Owner's configured identity only ([AGENTS.md](../../AGENTS.md#git-commit-attribution); DIR-022)
- start before the Owner's execution authorization at OG-4, or mark DONE without the required tests passing and the completion evidence ([TASK_PLAN](TASK_PLAN.md#2-rules-of-the-plan); [DEFINITION_OF_DONE](../08-testing/DEFINITION_OF_DONE.md#2-the-checklist))
- weaken, skip or delete a valid test to obtain a green run, or adopt a paid tier without the Owner's COST_POLICY decision (DOD-05; [COST_POLICY](../00-governance/COST_POLICY.md))
- add a CI job, tag rule or trigger that deploys, migrates, imports, backs up, restores or reaches staging or production (INF-03; RL-01)
- change a repository setting — the Owner applies the branch protection (RELEASE_PLAN §2; GLC-12)
- report a section or the release PASS without its run's evidence, or let a date shorten a required check (RL-02; RL-06)
- put a secret, a host detail or real data into the record, the report or the change log (RL-05; SX-06)

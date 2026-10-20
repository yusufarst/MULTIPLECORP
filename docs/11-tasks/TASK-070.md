# TASK-070 — Full release regression and the device UAT's preparation

Status: APPROVED | Updated: 2026-10-20 | Owner: Planning

Approval: [APPR-016](../00-governance/DECISION_LOG.md#appr-016--p11-task-plan-tasks-and-feature-coverage-matrix-approved), explicit Owner approval on 2026-10-20 (DIR-085 OF-07) of this document as committed in the first execution session's approval commit; the approved file hash and the verified conditions are recorded there. Approval changes lifecycle only, not implementation authorization.

Written by WF-PC5-W's writer of bundle W3 of run PC-5 on 2026-10-19 and 2026-10-20 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99; the Owner approved P11 with the [Task plan](TASK_PLAN.md) at OG-4 (DIR-085 OF-07). **No Task starts before the freeze record, from which the execution authorization is in force (DIR-085 OF-09).** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** TODO

**RISK CLASS:** STANDARD

**EXECUTOR:** AGENT

**OBJECTIVE:**

Run Layer 3, the full release regression, in fresh CI over the release candidate — every critical journey on the phone and on the desktop, the major roles, every route, the core forms, the critical integrations, every security case and the release-critical performance checks, every layer TL-01–TL-16 —, compile its results as the inputs of the release integrity report with the requirement-to-test traceability of every FEATURE_COVERAGE_MATRIX entry, prepare the staging part of Layer 3 for the operator, and prepare the device UAT — its device and browser matrix, scripts, synthetic data and record forms — for the Owner and the validators (TASK-086).

**WHY THIS EXISTS:**

AICWDF §28 and TEST_STRATEGY §3 require a full regression before production whose results feed AICWDF §39's release integrity report (RELEASE_PLAN §11); ACCEPTANCE_CRITERIA's V1 release completion asks for full requirement → specification → rule → Task → acceptance → test traceability (GAP-024). USB scanners exist but phones, browsers, printers and media are not verified (DEP-03; GAP-020), and scanner and session behaviour must be exercised on the real devices (GAP-015); the UAT with the Owner and the Admin Operasional validators is the Owner's (UAT-01–UAT-04; TASK-086), and this Task makes it ready without running it. TS-01–TS-12 govern every check.

**DEPENDS ON:**

- TASK-062 — Critical journeys A — the golden flow, purchasing, fulfilment, counts, scanning and SIPLAH
- TASK-063 — Critical journeys B — finance, corrections, reports, documents, completion and the checklist
- TASK-064 — Critical journeys C — isolation, sign-in, access, search, approvals, import, language, masters, command identity and status
- TASK-065 — UI/UX consistency gate, the responsive matrix and the anti-slop audit
- TASK-066 — Accessibility — WCAG 2.2 AA across every page
- TASK-067 — Localization completeness and the per-page copy lists
- TASK-068 — Performance targets and query budgets in CI
- TASK-069 — Security test matrix, route integrity and zero dead interactions

**BLOCKS:**

- TASK-081 — Staging drills and rehearsals — failure, restore, Scenario B and the import rehearsal
- TASK-086 — UAT with the Owner and the validators

**PARALLEL SAFE:** NO — it runs every suite against one release candidate in fresh CI and its results gate the UAT and the release

**IN SCOPE:**

- **Layer 3 in fresh CI** on one release candidate (TEST_STRATEGY §3): every critical journey CJ-01–CJ-22 on the phone and on the desktop, CJ-16 excepted, which runs on staging only; the major roles — the Owner and an Admin of each grant shape of SECURITY_TEST_MATRIX §2; every route through TL-07; the core forms; the critical integrations — the renderer, the queue, exports and the opening import, the breached-password check simulated, Google sign-in simulated in CI and checked on staging by the operator (SM-20) —; every SECURITY_TEST_MATRIX case; PERFORMANCE_TARGETS' release-critical checks that CI can run; and every layer TL-01–TL-16 as TEST_STRATEGY §2 requires it — the suites of TASK-062–TASK-069 and the module Tasks run again, none rewritten here
- the verification command run locally and again in fresh CI, a check passing only in both (TS-10); `ReconcileGuards` clean after the run (TS-11); a failed check recorded with its output, never retried until green, the release staying blocked until it passes (TS-06; TS-08; TS-12)
- **the release integrity report's inputs** in TEST_STRATEGY §16's form, section by section of RELEASE_PLAN §11 — TASKS from the Task plan, ROUTES and INTERACTIONS from TASK-069's counts, CRITICAL JOURNEYS, UI/UX from TASK-065–TASK-067, BROWSER from every journey's console and network capture, BUILD from TL-13, SECURITY and PERFORMANCE from TASK-068 and TASK-069 —, each with its evidence; the report itself is compiled with the release record (TASK-073; PRQ-11)
- **traceability** (GAP-024): every FEATURE_COVERAGE_MATRIX entry mapped to a Task traced to the acceptance criteria and the passing tests that prove it — TEST_STRATEGY §5's naming of tests by requirement —, and every non-Task disposition checked against its reason; a hole reported, never filled by assumption
- **the staging part of Layer 3**, prepared for the operator as a runbook with its expected results — the journeys at Layer 3 on staging, CJ-16 among them, Google sign-in with the staging OAuth client, PFM-01's times and PFM-13's load, the staging-only security cases of SM-07, SM-08, SM-19, SM-20 and SM-21 —, run by the operator on the ephemeral staging stack and never by an agent (TEST_STRATEGY §4; TASK-078, TASK-079, TASK-081)
- **the device UAT's preparation** (TEST_STRATEGY §12; RELEASE_PLAN §8): the device and browser matrix — the current stable Chrome and Edge on the office PCs, Chrome on Android, Safari on iPhone, the list to be corrected with the UAT's evidence (DIR-083 OE-02) —; the real-device scripts — S2-J01's sign-in, S2-J03's work home and project journey at 390 CSS px, S2-J05's receiving at 390 and 320 CSS px, UT-06, UT-12 and UT-18–UT-24 at 390 CSS px, each naming the device, the browser and the CSS width (DIR-077 CA-02; DIR-079 CD-03) —; the desktop composer at TEST_STRATEGY §9's widths (CD-04); the scanners and printing — wedge speed, the Enter suffix, the scan field's focus, Bluetooth pairing, the phone's camera application through the file picker, label and document printing by the method the office has (DEP-03; GAP-015; GAP-020) —; the files users really hold against FL-02 (DEP-04; DEP-05); time on the device beside the server budgets (AS-06; PFM-17)
- the UAT's items prepared with their scripts: the message catalogue and glossary and the payment duplicate window's presentation (UXH-08); the seven-day duplicate window against the Owner's practice (HO-35; BD-02); each document type DOC-01–DOC-14 with FX-10 (GAP-019); each high-risk account holder's authenticator phone (DEP-11); representative daily work with data entered once and reused (OS-01); the per-page copy lists of TASK-067 (TEST_STRATEGY §10); RELEASE_PLAN's UAT-01 numbering and go-live at the cutover, UAT-02 the opening import with the validators, UAT-03 daily work with inventory and money trustworthy at opening and after repeated and concurrent work (OS-02), UAT-04 separate Owner and Admin Operasional records
- the UAT record form — validator, date, build, tasks, each result and its disposition, an issue log —, nothing recorded that did not happen, an unavailable validator a blocker reported to the Owner; the validators' review time scheduled by RELEASE_PLAN (GAP-014)
- each failure recorded and sent through the executor feedback loop to the Task that owns it — a DONE Task's regression becoming a new defect Task under TASK_PLAN §7 (DEFINITION_OF_DONE §1; AGENT_OPERATING_MODEL)
- **the suites' joins**, which need TASK-065–TASK-067 all DONE (TASK_PLAN §2's order): in TASK-065's gate record, the language items from TASK-067's evidence and the accessibility critical checks from TASK-066's, so that all twenty-one items of AICWDF §27 are answered for every page (TEST_STRATEGY §9–§11)

**OUT OF SCOPE:**

- running the UAT, its sessions with the validators and the Owner's sign-off (TASK-086); the staging runs and measurements (TASK-078, TASK-079, TASK-081)
- writing a new suite or changing one — each suite's Task; the release record, tags and the integrity report's compilation (TASK-073)
- production, the cutover and go-live (TASK-084–TASK-087)

**REFERENCES:**

- [TEST_STRATEGY §1](../08-testing/TEST_STRATEGY.md#1-principles) TS-01–TS-12; [§2](../08-testing/TEST_STRATEGY.md#2-test-layers); [§3](../08-testing/TEST_STRATEGY.md#3-the-three-regression-layers) Layer 3; [§4](../08-testing/TEST_STRATEGY.md#4-environments-and-data); [§5](../08-testing/TEST_STRATEGY.md#5-domain-and-data-integrity) traceability for P11; [§7](../08-testing/TEST_STRATEGY.md#7-browser-end-to-end-tests-and-critical-journeys); [§12](../08-testing/TEST_STRATEGY.md#12-devices-and-uat); [§16](../08-testing/TEST_STRATEGY.md#16-evidence); [§17](../08-testing/TEST_STRATEGY.md#17-tooling)
- [RELEASE_PLAN §8](../10-release/RELEASE_PLAN.md#8-uat) UAT-01–UAT-04; [§11](../10-release/RELEASE_PLAN.md#11-the-release-integrity-report); [§10](../10-release/RELEASE_PLAN.md#10-production-release-requirements-for-p11) PRQ-11
- [SECURITY_TEST_MATRIX §9](../08-testing/SECURITY_TEST_MATRIX.md#9-where-each-case-runs); [PERFORMANCE_TARGETS §4](../08-testing/PERFORMANCE_TARGETS.md#4-the-release-critical-set)
- [V1_SCOPE](../01-product/V1_SCOPE.md#dependencies-and-evidence-needed) DEP-03–DEP-06, DEP-11; [ACCEPTANCE_CRITERIA](../01-product/ACCEPTANCE_CRITERIA.md#feature-module-and-release-completion-obligations) V1 release completion and its [operational success](../01-product/ACCEPTANCE_CRITERIA.md#operational-success-and-go-live-evidence) OS-01, OS-02
- [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) §§2–5; [DEFINITION_OF_DONE §5](../08-testing/DEFINITION_OF_DONE.md#5-module-and-release-completion)
- [ADMIN_FLOW §16](../07-ux-design/ADMIN_FLOW/s16-17-handoff-traceability.md#16-handoff-obligations) UXH-06, UXH-08, UXH-10
- [GAP_REGISTER](../00-governance/GAP_REGISTER.md#gap-015--scannersession-behavior-can-create-false-success-or-duplicate-intent) GAP-015; [GAP-020](../00-governance/GAP_REGISTER.md#gap-020--mobile-first-and-existing-usb-scanners-do-not-prove-device-compatibility); [GAP-024](../00-governance/GAP_REGISTER.md#gap-024--broad-capability-groups-can-hide-missing-reference-requirements-before-execution); [AGENT_OPERATING_MODEL](../00-governance/AGENT_OPERATING_MODEL.md#executor-feedback-loop)
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (88): DOC-01–DOC-14; DEP-03; OS-01–OS-02; OS-06; IMG-06; M-01–M-46; HO-35; UXH-06; UXH-08; UXH-10; TS-01–TS-12; UAT-01–UAT-04; GAP-015; GAP-020; GAP-024

**EXPECTED MODULES / FILE AREAS:**

- the Layer 3 run's pipeline and its evidence bundle, the traceability report over the FEATURE_COVERAGE_MATRIX, the staging runbook for the operator, and the UAT's device matrix, scripts, synthetic data set and record forms — in the repository's test and release-evidence areas, holding no real data —; no application code

**BUSINESS RULES:**

- N/A — no rule is built or changed; the regression re-proves every rule through the suites that own it, and the UAT confirms with the Owner what representative daily work produces (OS-01; OS-02)

**ARCHITECTURE RULES:**

- TS-01–TS-12 throughout: the tests belong to their Tasks; deterministic assertions, never opinions; real PostgreSQL; synthetic data only; no layer the only proof; no valid test weakened; failure paths tested as much as success; planned and run kept apart; budgets measured, never assumed; local and then fresh CI; guards reconciled after every writing test; VERIFYING is not DONE
- one release candidate for every suite of the run, its commit named in every piece of evidence (TEST_STRATEGY §16)

**SECURITY / PERMISSION REQUIREMENTS:**

- the major roles run as the Owner and an Admin of each grant shape of SECURITY_TEST_MATRIX §2; every STM case in the run (TL-14); the UAT on staging with synthetic data, never real client data, credentials or host details in a script, record or evidence (DOD-21; TEST_STRATEGY §12)
- each high-risk UAT participant's authenticator checked on their own phone without any secret entering the repository (DEP-11)

**COMPANY SCOPE AND FIELD PROJECTION:**

- N/A — the scope and projection cases are TASK-069's matrix, re-run here unchanged; the UAT's synthetic group ARJ, BTN and CKP carries the companies the validators work in

**CONCURRENCY / IDEMPOTENCY:**

- the concurrency and failure proofs of TEST_STRATEGY §6 — M-01–M-46 and the HO- proofs — re-run in the full regression with `ReconcileGuards` after each; the UAT's "after repeated and concurrent work" item checks inventory and money with the validators (UAT-03; OS-02)

**AUDIT:**

- the audit assertions of every suite re-run (DOD-19); the UAT record kept per validator, separately for the Owner and the Admin Operasional (UAT-04), nothing recorded that did not happen (TEST_STRATEGY §12)

**DATABASE IMPACT ASSESSMENT:**

- Schema change: NONE — test data only
- Migration required: NO
- Existing data transformation: NONE
- Direct production DB access: FORBIDDEN
- Data-loss risk: NONE KNOWN

**MODULARITY / EXTENSIBILITY:**

- Module boundary affected: none — test runs, evidence and UAT materials only
- Stable core kept provider-neutral: YES
- External/vendor-specific code isolated: YES — the suites' tools as their Tasks installed them (TEST_STRATEGY §17)
- Replaceable adapter/contract required: NO
- Provider lock-in risk: NONE — the public repository's CI at no cost; TestSprite only as OE-01 allows
- Future extension impact: LOW — each release repeats the run
- Speculative abstraction avoided: YES — the existing suites and the verification command, no test-management service

**AUTH IMPACT:**

- Authentication change: NONE
- Authentication implementation: LARAVEL_NATIVE — decided once ([EXECUTION_CONTEXT](EXECUTION_CONTEXT.md)), reused, never redesigned
- Auth profile: AICWDF-COMPAT-8 — re-proved by the security suites in the run; the UAT's accounts set their passwords under it on staging
- Google sign-in: ON — simulated in CI; the real staging redirect is the operator's (SM-20)
- Google registration behavior: EXISTING_ACCOUNT_ONLY — re-proved by SM-20 in the run
- Google credentials owner action required: NO — the staging OAuth client is TASK-082's
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

- Context7: N/A — no new tool or version is introduced; the suites' tools are verified by their Tasks and TASK-004
- Graphify: OPTIONAL — to trace a failing check to the Task that owns it
- Browser E2E: REQUIRED — every critical journey on the phone and the desktop in the run
- Other: the verification command (TEST_STRATEGY §17); TestSprite's regression runs where TASK-004 recorded its entitlement at no cost (OE-01); FX-02 and FX-10 for the UAT's staging data set
- Missing safe tool behavior: AUTO-INSTALL project-locally inside this Task (AICWDF §6.3) and record it in TOOLCHAIN; a paid tier or a privileged installation STOPS for the Owner (COST_POLICY)

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: refreshed before the run when Graphify is available ([TOOLCHAIN](../00-governance/TOOLCHAIN.md)); otherwise each failure traced to its Task through the FEATURE_COVERAGE_MATRIX
- Pre-change questions: which commit is the release candidate; which suites and layers each Task's DEFINITION_OF_DONE required
- Expected affected areas: the evidence and UAT materials only; a failure's fix lands in its owning Task

**CONTEXT7:**

- Topics to verify: N/A — the run reuses verified tools
- Version-sensitive assumptions: the release candidate's versions are the ones its suites verified; a version change re-opens SM-22's source re-verification

**UI / UX REQUIREMENTS:**

- Follow global design rules: N/A — no page is built; TASK-065's gate re-run in the regression
- Indonesian default: N/A — TASK-067's suite re-run; the UAT confirms the copy with the validators (UXH-08)
- English support: N/A — TASK-067's suite re-run
- Plain-language Indonesian: N/A — confirmed at the UAT through its copy items (UXH-08)
- Language switch impact: NONE
- Mobile-first: YES — every journey on the phone in the run; the real phones at the device UAT at 390 and 320 CSS px
- Responsive: N/A — TASK-065's matrix re-run
- Adaptive: N/A — TASK-065's matrix re-run; the desktop composer at the UAT
- Visual breathing room: N/A — TASK-065's gate; confirmed by the Owner at UAT
- Comfortable density: N/A — TASK-065's gate; confirmed by the Owner at UAT
- shadcn/ui reuse: N/A — no component is built
- Design reference: N/A — no page is built
- Primary action: N/A — no page is built
- Secondary actions: N/A — no page is built
- Destructive actions: N/A — no page is built
- Redundancy constraints: N/A — no page is built
- Loading state: N/A — no page is built
- Empty state: N/A — no page is built
- Error state: N/A — no page is built

**ROUTE IMPACT:**

- none — every route re-crawled through TL-07 in the run (TASK-069's suite)

**INTERACTION CONTRACTS:**

- N/A — no INT- contract is built; every INT- test re-run in the regression

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

1. One release candidate passes Layer 3 in fresh CI — every journey CJ-01–CJ-22 but CJ-16 on the phone and the desktop, the major roles, every route, the core forms, the critical integrations, every SECURITY_TEST_MATRIX case and the release-critical checks CI can run —, after passing the verification command locally (TEST_STRATEGY §3; TS-10).
2. Every layer TL-01–TL-16 each Task required passes on the release candidate, `ReconcileGuards` reports no difference after the run, and no valid test was weakened, skipped or deleted (TS-06; TS-11; DOD-05).
3. The release integrity report's inputs exist for TASKS, ROUTES, INTERACTIONS, CRITICAL JOURNEYS, UI/UX, BROWSER, BUILD, SECURITY and PERFORMANCE, each with its evidence and none reporting a planned check as run (RELEASE_PLAN §11; TS-08).
4. Every FEATURE_COVERAGE_MATRIX entry mapped to a Task traces to its acceptance criteria and passing tests, and every disposition has its reason; no hole is left unexplained (GAP-024; TEST_STRATEGY §5).
5. The operator's staging runbook lists every Layer 3 check that runs on staging with its expected result and evidence form — CJ-16, the Layer 3 journeys, PFM-01's times and PFM-13, SM-07, SM-08, SM-19, SM-20 and SM-21 (TEST_STRATEGY §4; SECURITY_TEST_MATRIX §9).
6. The device UAT's matrix and scripts cover the supported browsers, the real-device journeys at 390 and 320 CSS px with UT-06, UT-12 and UT-18–UT-24, the desktop composer, the scanners and printing, the users' files and the device times, each script naming what its record must hold (TEST_STRATEGY §12; DEP-03; GAP-015; GAP-020).
7. The UAT's item scripts exist for UXH-08's copy and duplicate window, HO-35's seven days, DOC-01–DOC-14 with FX-10, DEP-11's authenticator phones, OS-01's daily work, the per-page copy lists and UAT-01–UAT-04, with the record form TEST_STRATEGY §12 states (RELEASE_PLAN §8).
8. Every failure found is sent to its owning Task, and the Task stays VERIFYING until the re-run passes (TS-12; AGENT_OPERATING_MODEL).
9. TASK-065's gate record holds for every page the language items from TASK-067's evidence and the accessibility critical checks from TASK-066's, all twenty-one items answered (TEST_STRATEGY §9–§11; AICWDF §27).
10. The traceability report shows each cause PMC-01–PMC-15 of RELEASE_PLAN §12.1 answered on the release candidate — the agent Tasks it names DONE and the tests that detect it passed —, and lists the operator's and the Owner's items it names for the go-live checklist; a cause without its detecting test fails this Task (DIR-085 OF-01; RELEASE_PLAN §12.2).

**TEST REQUIREMENTS:**

- Lint: TL-01 in the run
- Typecheck: TL-02 in the run
- Unit: TL-03 in the run
- Feature: TL-04 in the run
- Integration: TL-05 in the run, the concurrency and reconciliation suites included
- Authorization: TL-06 in the run (TASK-069's cases)
- Route integrity: TL-07 in the run — every route
- Interaction: TL-08 in the run
- Browser E2E: TL-09 — every critical journey on the phone and the desktop in CI; the Layer 3 staging runs prepared
- Localization/language switch: TL-10 in the run (TASK-067's suite)
- Visual breathing room/density: TEST_STRATEGY §9 re-run (TASK-065's gate)
- Responsive: TL-11 in the run
- Accessibility: TL-12 in the run, TASK-066's engine on every page state the journeys reach; the manual Layer-3 checks recorded as TASK-066 prepared them
- Production build: TL-13 on the release candidate
- Security: TL-14 — every SECURITY_TEST_MATRIX case and H8-15's gates
- Performance: TL-15 — PERFORMANCE_TARGETS §4's release-critical set as far as CI runs it; its staging part prepared for TASK-079
- Migration: TL-16 — the release's migrations up on the previous schema with the previous release's smoke suite, and the opening import's suite (TEST_STRATEGY §15)
- Other: the traceability report (GAP-024), with the pre-mortem's causes PMC-01–PMC-15 (RELEASE_PLAN §12.1); the UAT materials for TASK-086

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

- TASK-086 — when its other dependencies are DONE and its READY gate holds

**DO NOT DO:**

- add to V1 — a capability, page, route, field, integration, document type, report or paid service — or drop, defer or narrow a V1_REQUIRED requirement; a Task that would need either raises an Owner question and waits ([DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-20)
- access the VPS, production, its data or a credential, or put a secret, real data, a host detail or production configuration in the repository ([INFRASTRUCTURE](../09-operations/INFRASTRUCTURE.md) INF-01, INF-05; SECURITY SX-01, SX-04, SX-06; [PRODUCTION_DATA_SAFETY](../00-governance/PRODUCTION_DATA_SAFETY.md))
- add AI, model or tool attribution to a commit — the Owner's configured identity only ([AGENTS.md](../../AGENTS.md#git-commit-attribution); DIR-022)
- start before the Owner's execution authorization at OG-4, or mark DONE without the required tests passing and the completion evidence ([TASK_PLAN](TASK_PLAN.md#2-rules-of-the-plan); [DEFINITION_OF_DONE](../08-testing/DEFINITION_OF_DONE.md#2-the-checklist))
- weaken, skip or delete a valid test to obtain a green run, or adopt a paid tier without the Owner's COST_POLICY decision (DOD-05; [COST_POLICY](../00-governance/COST_POLICY.md))
- run the UAT, act as a validator, or record a UAT result that a validator did not give (TEST_STRATEGY §12; TASK-086)
- run any check on staging, the VPS or production, or put real client data, a credential or a host detail in a script, record or evidence (TEST_STRATEGY §4; INF-01; DOD-21)
- report a release as ready with a failed, unrun or planned check, or mix evidence from different release candidates (TS-08; TS-12; RELEASE_PLAN §11)

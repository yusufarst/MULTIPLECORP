# TASK-062 — Critical journeys A — the golden flow, purchasing, fulfilment, counts, scanning and SIPLAH

Status: REVIEW | Updated: 2026-10-20 | Owner: Planning

Written by WF-PC5-W's writer of bundle W3 of run PC-5 on 2026-10-19 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99 — a REVIEW draft of P11, approved, or not, with the [Task plan](TASK_PLAN.md) at OG-4. **No Task starts before the Owner's execution authorization at OG-4.** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** TODO

**RISK CLASS:** STANDARD

**EXECUTOR:** AGENT

**OBJECTIVE:**

Write and run, in a real browser on the CI stack, the critical journeys CJ-01, CJ-02, CJ-03, CJ-05, CJ-07 and CJ-08 over the built system — the golden business flow with both supply branches and a stock-served project, purchasing with and without a PO, partial delivery and service handover, counts with condition change, adjustment and loss, the barcode flow, and a SIPLAH project with its net settlement —, each with the ADMIN_FLOW journeys and UX scenarios TEST_STRATEGY §7 runs in it, its evidence in TEST_STRATEGY §16's form, and every defect it finds sent to the Task that owns it.

**WHY THIS EXISTS:**

AICWDF §26 and TEST_STRATEGY §7 make the critical UI journeys a browser proof with evidence — "should work" refused —, and AC-20 requires the golden journey and its applicable branches to pass end to end, without dummy purchases or receipts (GAP-021). A journey is the only check that crosses every module in one flow; the module Tasks prove their own steps, and these suites prove that they work together (DEFINITION_OF_DONE §5; TEST_STRATEGY §3, Layer 2). A verification Task never changes a requirement and never records a check as passed without its evidence (TS-08; AGENT_OPERATING_MODEL).

**DEPENDS ON:**

- TASK-004 — Verification and release tooling check
- TASK-023 — My account — password, two-step verification, Google link and language
- TASK-024 — Users and access — accounts, roles, capabilities and company grants
- TASK-056 — Project cancellation
- TASK-057 — Opening import — preparation, dry run, sign-off and commit
- TASK-058 — Home, Work and Review — signals and the action queue
- TASK-059 — Search and scanning
- TASK-060 — Reports, the consolidated report and exports
- TASK-061 — Activity history and security log

**BLOCKS:**

- TASK-070 — Full release regression and the device UAT's preparation

**PARALLEL SAFE:** NO — its suites share the CI stack, the browser harness and the synthetic fixtures with the other verification Tasks, and its defects reopen module work

**IN SCOPE:**

- the browser suites, in Playwright on the Chromium and WebKit engines, on the CI stack with FX-02's synthetic group ARJ, BTN and CKP and the personas of INFORMATION_ARCHITECTURE §13 — Laras, the Owner; Dimas and Bayu, Admins with the grants of SECURITY_TEST_MATRIX §2 —, each journey walked through its pages, their INT- controls and their NAV- paths at the desktop and phone widths its devices name, the phone at 390 CSS px (TEST_STRATEGY §7, §12; IG-05):
  - **CJ-01**, the golden business flow — login, project, client, items, quotation, approval, purchase, receive *or* drop-ship, deliver, invoice, billing, payment, completion — with both branches and a project served from stock with no purchase; the journeys J-PRJ-01, J-QUO-01, J-PRJ-02, J-FUL-01, J-INV-02 and J-FUL-03; UXS-41's keyboard-only quotation with many lines and UXS-44's project detail within the performance rules; desktop, the phone for its `[both]` steps (AC-04; AC-20; GAP-021)
  - **CJ-02**, purchase with and without a PO and several suppliers for one project; J-PUR-01, J-PUR-02; desktop (AC-05)
  - **CJ-03**, partial delivery and service handover; J-FUL-02, J-FUL-04; desktop and phone (AC-07)
  - **CJ-05**, stock opname, condition change, adjustment, disposal and loss, a pending unattributed loss; J-INV-04, J-INV-05, J-INV-06; UXS-39; the phone on the floor and the desktop (AC-06; AC-22)
  - **CJ-07**, the barcode flow — receiving and dispatch with the scanner, a duplicate scan, an unknown code, manual lookup —; J-INV-01, J-INV-03; UXS-10, UXS-15, UXS-34, UXS-35; the phone and the desktop with a keyboard-wedge scanner simulated as keyboard input, the real scanners at Layer 3 (AC-03; GAP-015; TEST_STRATEGY §12)
  - **CJ-08**, a SIPLAH-channel project with its references and net settlement; J-FIN-04; desktop (AC-15; FX-01 6)
- each journey's figures asserted exactly against FX-01 where its steps carry them — AC-20's journey, the stock fixtures 7–9 in CJ-05, the SIPLAH net settlement 6 in CJ-08 —; `ReconcileGuards` clean after each journey (TS-11; FX-01)
- each journey's console and network captured: a critical console error or a critical failed request fails it (AICWDF §25; TEST_STRATEGY §8); its evidence `E2E: PASS — Evidence: <run, commit, browser and width, trace or screenshots, network and console capture>` (TEST_STRATEGY §16)
- TestSprite's UI and E2E regression runs over the same journeys where TASK-004 recorded its entitlement at no cost, never as the sole proof; otherwise the Owner told before the gate that needs it (DIR-083 OE-01; TEST_STRATEGY §17)
- the suites registered as Layer 2's regression for the module groups they touch and as part of Layer 3 (TEST_STRATEGY §3)
- each defect recorded with its reproducer, evidence, affected behaviour and proposed minimum fix, and sent through the executor feedback loop to the Task that owns the behaviour — a regression of a DONE Task becoming a new defect Task recorded in TASK_PLAN §7 —; the journey stays failing until the fix passes; a requirement that looks wrong goes to Planning, never changed here (AGENT_OPERATING_MODEL; DEFINITION_OF_DONE §1)

**OUT OF SCOPE:**

- the other journeys — CJ-04, CJ-06, CJ-14, CJ-15, CJ-17, CJ-20 (TASK-063) and CJ-09–CJ-13, CJ-16, CJ-18, CJ-19, CJ-21, CJ-22 (TASK-064) —; the UI/UX gate, accessibility, localization, performance and security suites (TASK-065–TASK-069); the full release regression and the device UAT's preparation (TASK-070)
- fixing any module behaviour — the owning Task's, through the feedback loop; changing a fixture's expected value — the source's value prevails (TEST_STRATEGY §4)
- runs on staging, on the VPS or on real devices — the operator's and the UAT's (TEST_STRATEGY §4, §12; TASK-081, TASK-086)

**REFERENCES:**

- [TEST_STRATEGY §7](../08-testing/TEST_STRATEGY.md#7-browser-end-to-end-tests-and-critical-journeys) CJ-01–CJ-03, CJ-05, CJ-07, CJ-08 with the journeys and UX scenarios run in each; [§2](../08-testing/TEST_STRATEGY.md#2-test-layers) TL-09; [§3](../08-testing/TEST_STRATEGY.md#3-the-three-regression-layers); [§4](../08-testing/TEST_STRATEGY.md#4-environments-and-data) FX-01, FX-02; [§8](../08-testing/TEST_STRATEGY.md#8-route-navigation-and-interaction-integrity); [§12](../08-testing/TEST_STRATEGY.md#12-devices-and-uat); [§16](../08-testing/TEST_STRATEGY.md#16-evidence); [§17](../08-testing/TEST_STRATEGY.md#17-tooling); [§1](../08-testing/TEST_STRATEGY.md#1-principles) TS-02, TS-05, TS-06, TS-08, TS-11
- [ADMIN_FLOW §10.2](../07-ux-design/ADMIN_FLOW/s10-a-journeys.md#102-project-and-commercial), [§10.3](../07-ux-design/ADMIN_FLOW/s10-a-journeys.md#103-inventory-and-purchasing), [§10.4](../07-ux-design/ADMIN_FLOW/s10-b-journeys.md#104-fulfilment), [§10.6](../07-ux-design/ADMIN_FLOW/s10-b-journeys.md#106-finance) — the journeys; [§15](../07-ux-design/ADMIN_FLOW/s14-15-messages-scenarios.md#15-mandatory-ux-scenarios) UXS-10, UXS-15, UXS-34, UXS-35, UXS-39, UXS-41, UXS-44
- [ACCEPTANCE_CRITERIA](../01-product/ACCEPTANCE_CRITERIA.md#product-acceptance-contracts) AC-03–AC-07, AC-15, AC-20, AC-22; [BUSINESS_RULES](../02-domain/BUSINESS_RULES.md#numeric-annex-binding-examples-for-p8-fixtures-gap-004) numeric annex 6–9; [§6](../02-domain/BUSINESS_RULES.md#6-cross-domain-interaction-rules) BR-XD-02, BR-XD-03
- [WORKFLOWS §9](../03-workflows/WORKFLOWS/s08-09-corrections-indivisible.md#9-indivisible-business-actions) the AX- rows the journeys' steps run; [API_AND_INTEGRATIONS §2](../06-api-performance/API_AND_INTEGRATIONS.md#2-contract-for-a-future-external-integration) IC-14
- [INFORMATION_ARCHITECTURE §13](../07-ux-design/INFORMATION_ARCHITECTURE.md#13-role-based-navigation) the personas and paths; [SECURITY_TEST_MATRIX §2](../08-testing/SECURITY_TEST_MATRIX.md#2-fixtures) the accounts
- [AGENT_OPERATING_MODEL](../00-governance/AGENT_OPERATING_MODEL.md#executor-feedback-loop) the executor feedback loop; [DEFINITION_OF_DONE §1](../08-testing/DEFINITION_OF_DONE.md#1-statuses-and-the-done-rule), [§5](../08-testing/DEFINITION_OF_DONE.md#5-module-and-release-completion)
- [GAP_REGISTER](../00-governance/GAP_REGISTER.md#gap-015--scannersession-behavior-can-create-false-success-or-duplicate-intent) GAP-015; [GAP-021](../00-governance/GAP_REGISTER.md#gap-021--treating-the-reference-journey-as-mandatory-would-create-dummy-transactions)
- Architecture challenge — the sequence 9a of the [P11 gate record](evidence/P11_QUALITY_GATE.md#whole-system-architecture-challenge-sp-98), their assertions carried into this Task's acceptance criteria and tests
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (7): TL-09; CJ-01–CJ-03; CJ-05; CJ-07–CJ-08

**EXPECTED MODULES / FILE AREAS:**

- the browser suite's journey specifications for CJ-01, CJ-02, CJ-03, CJ-05, CJ-07 and CJ-08 and their page helpers in the end-to-end test area TASK-003 set up; the journeys' seed scripts over FX-01 and FX-02; no application code

**BUSINESS RULES:**

- the journeys assert, never redefine: AC-20 and BR-XD-02/BR-XD-03 — existing-stock orders without a dummy purchase, service lines without warehouse movements, direct supplier delivery without a fictitious receipt; BR-INV-01 and the annex's stock examples 7–9 in CJ-05; BR-FIN-09 and annex example 6 — the SIPLAH net settlement as a deduction settlement on evidence, gross unchanged, Cash-In the money received (architecture challenge 9a)
- IC-14, BR-FIN-10 and FS-13: SIPLAH values are entered as recorded metadata and no cashback or fee is computed by a formula; no step waits on SIPLAH

**ARCHITECTURE RULES:**

- TS-05: the browser suite adds to the deterministic backend, integration and concurrency tests of the module Tasks and never replaces them; TestSprite is never the sole proof (OE-01)
- TS-03 and TS-04: real PostgreSQL behind the CI stack and synthetic data only, generated with fixed seeds; no production data, copy or sample

**SECURITY / PERMISSION REQUIREMENTS:**

- each step runs as an account holding exactly the capabilities its journey needs on FX-02's companies (SECURITY_TEST_MATRIX §2); the suites hold no real credential — secrets generated per run and never committed, the secret-like scan of SM-15 over the suite and its fixtures (DOD-21)
- a step that fails with a denial it should not meet is a defect of the owning Task's authorization; the denial suites themselves are TASK-069's

**COMPANY SCOPE AND FIELD PROJECTION:**

- the journeys run in the companies their records belong to, the Owner across all; each step asserts that the actor sees what its journey needs and its pages show each record's company label (PT-04; CS-08); the field-projection denials are TASK-069's (SM-25)

**CONCURRENCY / IDEMPOTENCY:**

- N/A — no command is built here; the journeys exercise the AX- rows of their steps — CJ-01: AX-13, AX-01, AX-04 or AX-08, AX-03, AX-09, AX-15, AX-16, AX-17, AX-18, AX-19, AX-25; CJ-02: AX-29 and AX-32 with the purchases' NX-04; CJ-05: AX-06, AX-07, AX-30, AX-37; CJ-07: AX-04, AX-03 and BD-13's likely-duplicate warning; CJ-08: AX-21 —, each step asserting its one recorded outcome, the races and replays being TEST_STRATEGY §6's suites and CJ-21's (TASK-064)

**AUDIT:**

- each journey asserts the audit trail of its steps where a rule requires one — an adjustment's reason and audit event, a correction's linkage, the individual actor on every committed step (DOD-19; BR-XC-01; TEST_STRATEGY §5) —; the suite itself writes no business record outside the CI stack

**DATABASE IMPACT ASSESSMENT:**

- Schema change: NONE — test data only
- Migration required: NO
- Existing data transformation: NONE
- Direct production DB access: FORBIDDEN
- Data-loss risk: NONE KNOWN

**MODULARITY / EXTENSIBILITY:**

- Module boundary affected: none — test code only, outside the application's modules
- Stable core kept provider-neutral: YES
- External/vendor-specific code isolated: YES — Playwright and TestSprite stay in the test tree (TEST_STRATEGY §17)
- Replaceable adapter/contract required: NO
- Provider lock-in risk: NONE — Playwright is free and self-hosted in CI; TestSprite only as OE-01 allows
- Future extension impact: LOW — a further journey is one specification
- Speculative abstraction avoided: YES — page helpers only where two journeys share a page

**AUTH IMPACT:**

- Authentication change: NONE
- Authentication implementation: LARAVEL_NATIVE — decided once ([EXECUTION_CONTEXT](EXECUTION_CONTEXT.md)), reused, never redesigned
- Auth profile: N/A — the suites sign in with synthetic accounts through the existing sign-in; nothing changes
- Google sign-in: N/A — not used by these journeys; CJ-10 is TASK-064's
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

- Context7: REQUIRED — Playwright's trace, console and network capture, keyboard input for a simulated wedge scanner, and its Chromium and WebKit projects at the installed version
- Graphify: OPTIONAL — to map each journey step to the module and Task that owns it when a defect is found
- Browser E2E: REQUIRED — this Task is the browser proof of its six journeys
- Other: TestSprite where TASK-004 recorded its entitlement at no cost (OE-01); FX-01 and FX-02 from TASK-003's harness
- Missing safe tool behavior: AUTO-INSTALL project-locally inside this Task (AICWDF §6.3) and record it in TOOLCHAIN; a paid tier or a privileged installation STOPS for the Owner (COST_POLICY)

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: refreshed before the run when Graphify is available ([TOOLCHAIN](../00-governance/TOOLCHAIN.md)); otherwise each step's page traced to its Task through the FEATURE_COVERAGE_MATRIX
- Pre-change questions: which Task owns each page, route and command a journey crosses, so that a defect reaches its owner
- Expected affected areas: the test tree only; a defect's fix lands in its owning Task

**CONTEXT7:**

- Topics to verify: Playwright's projects for Chromium and WebKit, trace and video retention, console and request-failure hooks, device emulation for the phone widths
- Version-sensitive assumptions: none from memory

**UI / UX REQUIREMENTS:**

- Follow global design rules: N/A — no page is built; the gate of AICWDF §27 is TASK-065's
- Indonesian default: YES — the journeys run in the default Indonesian interface and assert its copy (TEST_STRATEGY §10)
- English support: N/A — both languages are TASK-067's suite and CJ-18 is TASK-064's
- Plain-language Indonesian: YES — each step's answer asserted by its MSG- text where the journey names one
- Language switch impact: NONE — the switch is not used by these journeys
- Mobile-first: YES — the phone steps at 390 CSS px for CJ-01's `[both]` steps, CJ-03, CJ-05 and CJ-07
- Responsive: N/A — the band edges are TASK-065's responsive matrix (TL-11)
- Adaptive: YES — each journey walks the phone and desktop compositions its devices name (IG-05)
- Visual breathing room: N/A — judged by TASK-065's gate
- Comfortable density: N/A — judged by TASK-065's gate
- shadcn/ui reuse: N/A — no component is built
- Design reference: N/A — the journeys follow the built pages and ADMIN_FLOW's journeys, not a design reference
- Primary action: N/A — no page; each step presses its page's primary action as its journey states
- Secondary actions: N/A — no page
- Destructive actions: N/A — no page; a journey's confirmations (IP-08, IP-09) are walked as steps
- Redundancy constraints: N/A — duplicate actions are counted by TASK-065
- Loading state: N/A — no page; the suites wait on the pages' own loading states, never on fixed sleeps
- Empty state: N/A — no page
- Error state: N/A — no page; the journeys' refusal steps assert their answers — UXS-10's REJECTED, UXS-15's duplicate warning, UXS-34's ambiguous code

**ROUTE IMPACT:**

- none — no route is added or changed; the journeys request the routes their pages use, each request captured in the network log

**INTERACTION CONTRACTS:**

- N/A — no INT- contract is built; the journeys press the INT- controls of their pages, whose tests are each module Task's

**NAVIGATION CONTRACT:**

- Parent: N/A — no page; each journey walks its pages' NAV- contracts
- Entry: N/A — no page
- Back action: N/A — no page; the back and cancel paths on the journeys' route are asserted as steps
- Cancel: N/A — no page
- Success destination: N/A — no page; each step's destination asserted as its NAV- contract names
- Error behavior: N/A — no page
- State preservation: N/A — no page
- Mobile behavior: N/A — no page; the phone steps as above

**ACCEPTANCE CRITERIA:**

1. CJ-01 passes end to end with both supply branches and with a project served from stock with no purchase, no dummy purchase, receipt or warehouse movement created on a path that does not need it, and its figures equal to AC-20's expectations (AC-20; BR-XD-02; GAP-021).
2. CJ-02 records a purchase with a PO and one without, and several suppliers for one project without duplicating its demand (AC-05).
3. CJ-03 records a partial delivery and a service handover on the desktop and the phone, delivery never changing stock (AC-07; BR-INV-02).
4. CJ-05 records an opname with its variance, a condition change, an adjustment, a disposal and a loss, and a pending unattributed loss shown on its own line, its quantities equal to the annex's examples 7–9 (AC-06; AC-22; UXS-39).
5. CJ-07 receives and dispatches by simulated scans, counts a duplicate scan once, offers registration for an unknown code without creating anything, lists an ambiguous code's candidates, and works by typed SKU and "Cari Produk" without a scanner (AC-03; UXS-34; UXS-35; GAP-015).
6. CJ-08 records a SIPLAH project's references as entered values and its net settlement as a deduction settlement on evidence, with the annex's example 6 exactly — Cash-In 9,700,000, fee 300,000, outstanding 0 —, and no SIPLAH value computed anywhere (AC-15; IC-14; FS-13; architecture challenge 9a).
7. Every journey's run records its evidence in TEST_STRATEGY §16's form with no critical console error and no critical failed request (TEST_STRATEGY §7, §8).
8. Every failure found is recorded with its reproducer and sent to its owning Task; no expected value or requirement is changed in this Task, and no check is reported as passed without its evidence (TS-06; TS-08; AGENT_OPERATING_MODEL).

**TEST REQUIREMENTS:**

- Lint: TL-01 on the suite's code
- Typecheck: TL-02 on the suite's code
- Unit: N/A — no domain logic in this Task
- Feature: N/A — the routes and commands are each module Task's (TL-04)
- Integration: N/A — the races and the database paths are TEST_STRATEGY §6's and the module Tasks'
- Authorization: N/A — the denial suites are TASK-069's; the journeys run with the capabilities their steps need
- Route integrity: N/A — the route crawl and audit are TASK-069's; each journey's network capture flags an unexpected 404, 405 or 500
- Interaction: N/A — the INT- tests are each module Task's; the journeys walk them
- Browser E2E: TL-09 — CJ-01, CJ-02, CJ-03, CJ-05, CJ-07 and CJ-08 with their journeys and UX scenarios, on Chromium and WebKit
- Localization/language switch: N/A — TASK-067's suite and CJ-18 (TASK-064)
- Visual breathing room/density: N/A — TASK-065's gate
- Responsive: N/A — TASK-065's band-edge matrix; these journeys run at their own phone and desktop widths
- Accessibility: the engine of TASK-066 runs on every page state these journeys reach (TEST_STRATEGY §11)
- Production build: TL-13 for the suite's code; the journeys run on the CI stack of TEST_STRATEGY §4
- Security: N/A — TASK-069's matrix; the suite's secret-like scan (SM-15)
- Performance: N/A — TASK-068's budgets; the journeys' network capture is kept for it
- Migration: N/A — no migration or import code in this Task
- Other: `ReconcileGuards` clean after each journey (TS-11); TestSprite's regression runs where OE-01 allows

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

- TASK-070 — when its other dependencies are DONE and its READY gate holds

**DO NOT DO:**

- add to V1 — a capability, page, route, field, integration, document type, report or paid service — or drop, defer or narrow a V1_REQUIRED requirement; a Task that would need either raises an Owner question and waits ([DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-20)
- access the VPS, production, its data or a credential, or put a secret, real data, a host detail or production configuration in the repository ([INFRASTRUCTURE](../09-operations/INFRASTRUCTURE.md) INF-01, INF-05; SECURITY SX-01, SX-04, SX-06; [PRODUCTION_DATA_SAFETY](../00-governance/PRODUCTION_DATA_SAFETY.md))
- add AI, model or tool attribution to a commit — the Owner's configured identity only ([AGENTS.md](../../AGENTS.md#git-commit-attribution); DIR-022)
- start before the Owner's execution authorization at OG-4, or mark DONE without the required tests passing and the completion evidence ([TASK_PLAN](TASK_PLAN.md#2-rules-of-the-plan); [DEFINITION_OF_DONE](../08-testing/DEFINITION_OF_DONE.md#2-the-checklist))
- weaken, skip or delete a valid test to obtain a green run, or adopt a paid tier without the Owner's COST_POLICY decision (DOD-05; [COST_POLICY](../00-governance/COST_POLICY.md))
- change a requirement, an acceptance criterion or a fixture's expected value to make a journey pass, or fix a module's behaviour inside this Task (TS-06; AGENT_OPERATING_MODEL)
- report a journey as passed without its run's evidence, or let TestSprite or a screenshot stand as the only proof (TS-08; OE-01)
- run a journey against staging, the VPS, production or real data (TEST_STRATEGY §4; INF-01)

# TASK-063 — Critical journeys B — finance, corrections, reports, documents, completion and the checklist

Status: REVIEW | Updated: 2026-10-19 | Owner: Planning

Written by WF-PC5-W's writer of bundle W3 of run PC-5 on 2026-10-19 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99 — a REVIEW draft of P11, approved, or not, with the [Task plan](TASK_PLAN.md) at OG-4. **No Task starts before the Owner's execution authorization at OG-4.** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** TODO

**RISK CLASS:** STANDARD

**EXECUTOR:** AGENT

**OBJECTIVE:**

Write and run, in a real browser on the CI stack, the critical journeys CJ-04, CJ-06, CJ-14, CJ-15, CJ-17 and CJ-20 over the built system — invoices, billing and payments with their DP, termin, overpayment, credit, refund, duplicate and pre-payment Kuitansi; corrections and voids with backdating; receivables, reports and exports; the generated document lifecycle in the composer; project completion, Force Complete and cancellation; and the administrative checklist with external documents —, each with the ADMIN_FLOW journeys and UX scenarios TEST_STRATEGY §7 runs in it, its evidence in TEST_STRATEGY §16's form, and every defect it finds sent to the Task that owns it.

**WHY THIS EXISTS:**

These journeys carry the money, the corrections and the documents across modules: AC-10's payment cases, AC-12's corrections, AC-11 and AC-23's reports and exports, AC-08's documents, AC-21's completion and AC-09's checklist must work together as the user walks them, not only as separate tests (DEFINITION_OF_DONE §4–§5; TEST_STRATEGY §7). Billing, payment and documents reconcile together or the module does not pass (ACCEPTANCE_CRITERIA, module completion). A verification Task never changes a requirement and never records a check as passed without its evidence (TS-08; AGENT_OPERATING_MODEL).

**DEPENDS ON:**

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

- the browser suites, in Playwright on the Chromium and WebKit engines, on the CI stack with FX-02's synthetic group and the personas of INFORMATION_ARCHITECTURE §13, each journey walked through its pages, their INT- controls and their NAV- paths at the widths its devices name, the phone at 390 CSS px (TEST_STRATEGY §7, §12; IG-05):
  - **CJ-04**, invoices, billing, partial and full payment, DP and termin, overpayment, credit and refund, a payment duplicate, the pre-payment Kuitansi; J-FIN-01, J-FIN-03, J-FIN-05, J-FIN-06, J-FIN-07, J-FIN-08; UXS-16, UXS-38; desktop and phone (AC-10; FX-01 1–5)
  - **CJ-06**, correction and void — reversals, returns, invoice revision and void, backdating —; J-PUR-03, J-FUL-05; UXS-14, UXS-29; desktop (AC-12)
  - **CJ-14**, receivables and ageing, the reports with company and date filters, an export, its row bound and a third concurrent export; J-FIN-02; UXS-36; desktop (AC-11; AC-23)
  - **CJ-15**, the generated document lifecycle — a draft prepared from the recorded facts and opened on PG-036 with the form beside the live preview on the desktop and Edit → Preview → Finalize on the phone, issue with confirmation, the rendition, a failed rendition —; J-DOC-01; UXS-09; the desktop at 1,025, 1,279 and 1,280 px with the tablet's toggle at 1,024 px, and the phone (AC-08; DESIGN_SYSTEM §14.3; `DIR-081` OB-02; TEST_STRATEGY §9)
  - **CJ-17**, project completion with its predicates, Force Complete by the Owner, cancellation; J-PRJ-03, J-PRJ-04; UXS-37; desktop (AC-21)
  - **CJ-20**, the administrative checklist and external documents — evidence uploads, an in-scope duplicate warning —; J-DOC-02, J-ADM-01; UXS-17; desktop and phone (AC-09)
- each journey's figures asserted exactly against FX-01 — the AC-10 baseline, the advance, the overpayment, the reallocation and the void after partial payment in CJ-04 (1–5) —, and the documents' content against the facts they render, the draft never satisfying a requirement (GAP-053; TEST_STRATEGY §9); `ReconcileGuards` clean after each journey (TS-11)
- each journey's console and network captured, a critical console error or failed request failing it; its evidence in TEST_STRATEGY §16's form (AICWDF §25; TEST_STRATEGY §8, §16)
- TestSprite's UI and E2E regression runs over the same journeys where TASK-004 recorded its entitlement at no cost, never as the sole proof; otherwise the Owner told before the gate that needs it (DIR-083 OE-01)
- the suites registered as Layer 2's regression for the module groups they touch and as part of Layer 3 (TEST_STRATEGY §3)
- each defect recorded with its reproducer, evidence, affected behaviour and proposed minimum fix, and sent through the executor feedback loop to the Task that owns it — a regression of a DONE Task becoming a new defect Task recorded in TASK_PLAN §7 —; the journey stays failing until the fix passes; a requirement that looks wrong goes to Planning, never changed here (AGENT_OPERATING_MODEL; DEFINITION_OF_DONE §1)

**OUT OF SCOPE:**

- the other journeys (TASK-062, TASK-064); the UI/UX gate, accessibility, localization, performance and security suites (TASK-065–TASK-069); the full release regression and the device UAT's preparation (TASK-070)
- the content and layout validation of each document type with the Owner and the validators (GAP-019; TEST_STRATEGY §12; TASK-086)
- fixing any module behaviour — the owning Task's; changing a fixture's expected value — the source's value prevails (TEST_STRATEGY §4); runs on staging, the VPS or real devices

**REFERENCES:**

- [TEST_STRATEGY §7](../08-testing/TEST_STRATEGY.md#7-browser-end-to-end-tests-and-critical-journeys) CJ-04, CJ-06, CJ-14, CJ-15, CJ-17, CJ-20 with the journeys and UX scenarios run in each; [§9](../08-testing/TEST_STRATEGY.md#9-the-uiux-consistency-gate) the composer and preview; [§2](../08-testing/TEST_STRATEGY.md#2-test-layers) TL-09; [§3](../08-testing/TEST_STRATEGY.md#3-the-three-regression-layers); [§4](../08-testing/TEST_STRATEGY.md#4-environments-and-data) FX-01, FX-02, FX-10; [§16](../08-testing/TEST_STRATEGY.md#16-evidence); [§17](../08-testing/TEST_STRATEGY.md#17-tooling); [§1](../08-testing/TEST_STRATEGY.md#1-principles) TS-05, TS-06, TS-08, TS-11
- [ADMIN_FLOW §10.3](../07-ux-design/ADMIN_FLOW/s10-a-journeys.md#103-inventory-and-purchasing), [§10.4](../07-ux-design/ADMIN_FLOW/s10-b-journeys.md#104-fulfilment), [§10.5](../07-ux-design/ADMIN_FLOW/s10-b-journeys.md#105-documents-and-administration), [§10.6](../07-ux-design/ADMIN_FLOW/s10-b-journeys.md#106-finance), [§10.7](../07-ux-design/ADMIN_FLOW/s10-b-journeys.md#107-completion-cancellation-and-migration) — the journeys; [§15](../07-ux-design/ADMIN_FLOW/s14-15-messages-scenarios.md#15-mandatory-ux-scenarios) UXS-09, UXS-14, UXS-16, UXS-17, UXS-29, UXS-36–UXS-38
- [ACCEPTANCE_CRITERIA](../01-product/ACCEPTANCE_CRITERIA.md#product-acceptance-contracts) AC-08–AC-12, AC-21, AC-23; [BUSINESS_RULES](../02-domain/BUSINESS_RULES.md#numeric-annex-binding-examples-for-p8-fixtures-gap-004) numeric annex 1–5; [DEFINITION_OF_DONE §4](../08-testing/DEFINITION_OF_DONE.md#4-the-completion-examples)
- [DESIGN_SYSTEM §14.3](../07-ux-design/DESIGN_SYSTEM.md#143-document-preview) the composer and preview
- [WORKFLOWS §9](../03-workflows/WORKFLOWS/s08-09-corrections-indivisible.md#9-indivisible-business-actions) the AX- rows the journeys' steps run
- [INFORMATION_ARCHITECTURE §13](../07-ux-design/INFORMATION_ARCHITECTURE.md#13-role-based-navigation); [SECURITY_TEST_MATRIX §2](../08-testing/SECURITY_TEST_MATRIX.md#2-fixtures)
- [AGENT_OPERATING_MODEL](../00-governance/AGENT_OPERATING_MODEL.md#executor-feedback-loop); [DEFINITION_OF_DONE §1](../08-testing/DEFINITION_OF_DONE.md#1-statuses-and-the-done-rule), [§5](../08-testing/DEFINITION_OF_DONE.md#5-module-and-release-completion)
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (7): TL-09; CJ-04; CJ-06; CJ-14–CJ-15; CJ-17; CJ-20

**EXPECTED MODULES / FILE AREAS:**

- the browser suite's journey specifications for CJ-04, CJ-06, CJ-14, CJ-15, CJ-17 and CJ-20 and their page helpers in the end-to-end test area TASK-003 set up; their seed scripts over FX-01, FX-02 and FX-10; no application code

**BUSINESS RULES:**

- the journeys assert, never redefine: BR-FIN-01–BR-FIN-06 with the annex's examples 1–5 — issue implying no cash, billing activating only the unpaid remainder, an advance never re-counted, overpayment becoming credit and a refund consuming it, a reallocation keeping the payment's total, a void disposing every application (BR-CR-03) —; BR-DOC-04's pre-payment Kuitansi creating no Cash-In (UXS-38)
- BR-CR-01–BR-CR-06 and the CM rows their steps take: a correction starting from its record, with reason and linkage, the original and the correction both visible; a backdated command keeping its recorded time with the mark "Tanggal Mundur" (BR-DT-03; UXS-14)
- BR-PRJ-02 and BR-PRJ-04 in CJ-17: completion only with the ten predicates true, Force Complete the Owner's with its residual obligations visible; BR-ADM-01 and BR-ADM-02 in CJ-20: a draft never satisfying a requirement, a waiver only on an eligible item

**ARCHITECTURE RULES:**

- TS-05: the browser suite adds to the module Tasks' deterministic and concurrency tests and never replaces them; TestSprite is never the sole proof (OE-01)
- TS-03 and TS-04: real PostgreSQL, the queue and the renderer behind the CI stack, synthetic data only

**SECURITY / PERMISSION REQUIREMENTS:**

- each step runs as an account holding exactly the capabilities its journey needs — Force Complete as the Owner, the corrections with their ADM+ capabilities (SECURITY_TEST_MATRIX §2) —; the suites hold no real credential, the secret-like scan of SM-15 over them (DOD-21)
- a step that fails with a denial it should not meet is a defect of the owning Task's authorization; the denial suites are TASK-069's

**COMPANY SCOPE AND FIELD PROJECTION:**

- the journeys run in the companies their records belong to, the Owner across all; each step asserts that the actor sees what its journey needs — the money families with `finance.view`, the document's payload by its family (PJ-15) —; the projection denials are TASK-069's (SM-25)

**CONCURRENCY / IDEMPOTENCY:**

- N/A — no command is built here; the journeys exercise the AX- rows of their steps — CJ-04: AX-16, AX-17, AX-18, AX-19, AX-23, AX-24, AX-31 and BD-02's duplicate warning; CJ-06: AX-05, AX-10, AX-11, AX-22; CJ-15: AX-15 with its rendition jobs; CJ-17: AX-25, AX-26, AX-27; CJ-20: the evidence and requirement families NX-03, NX-07, NX-14 —, each step asserting its one recorded outcome; the races and replays are TEST_STRATEGY §6's suites and CJ-21's (TASK-064)

**AUDIT:**

- each journey asserts the audit trail its steps require — a correction's reason and linkage, a payment duplicate's override reason, Force Complete's reason, snapshot and residual obligations (C-38), a waiver's reason (DOD-19; BR-XC-01) —; the suite writes nothing outside the CI stack

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
- Google sign-in: N/A — not used by these journeys
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

- Context7: REQUIRED — Playwright's trace, console and network capture, file uploads and downloads, viewport sizes for the composer's widths, at the installed version
- Graphify: OPTIONAL — to map each journey step to the Task that owns it when a defect is found
- Browser E2E: REQUIRED — this Task is the browser proof of its six journeys
- Other: TestSprite where TASK-004 recorded its entitlement at no cost (OE-01); FX-01, FX-02 and FX-10 from TASK-003's harness; a rendition failure injected on the CI stack for UXS-09
- Missing safe tool behavior: AUTO-INSTALL project-locally inside this Task (AICWDF §6.3) and record it in TOOLCHAIN; a paid tier or a privileged installation STOPS for the Owner (COST_POLICY)

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: refreshed before the run when Graphify is available ([TOOLCHAIN](../00-governance/TOOLCHAIN.md)); otherwise each step's page traced to its Task through the FEATURE_COVERAGE_MATRIX
- Pre-change questions: which Task owns each page, route and command a journey crosses
- Expected affected areas: the test tree only; a defect's fix lands in its owning Task

**CONTEXT7:**

- Topics to verify: Playwright's download handling and request interception for a failed rendition; viewport emulation at 1,024–1,280 px and 390 px
- Version-sensitive assumptions: none from memory

**UI / UX REQUIREMENTS:**

- Follow global design rules: N/A — no page is built; the gate of AICWDF §27 is TASK-065's
- Indonesian default: YES — the journeys run in the default Indonesian interface and assert its copy; the documents stay Indonesian (TEST_STRATEGY §10)
- English support: N/A — both languages are TASK-067's suite
- Plain-language Indonesian: YES — each step's answer asserted by its MSG- text where the journey names one — MSG-08, MSG-15, MSG-21, MSG-22, MSG-39, MSG-40
- Language switch impact: NONE — the switch is not used by these journeys
- Mobile-first: YES — the phone steps of CJ-04, CJ-15 and CJ-20 at 390 CSS px
- Responsive: N/A — the band edges are TASK-065's matrix; CJ-15 runs the composer's widths TEST_STRATEGY §9 names
- Adaptive: YES — CJ-15's form beside the live preview on the desktop, the toggle at 1,024 px and Edit → Preview → Finalize on the phone (D-DS-23; INT-012)
- Visual breathing room: N/A — judged by TASK-065's gate
- Comfortable density: N/A — judged by TASK-065's gate
- shadcn/ui reuse: N/A — no component is built
- Design reference: N/A — the journeys follow the built pages and ADMIN_FLOW's journeys
- Primary action: N/A — no page; each step presses its page's primary action as its journey states
- Secondary actions: N/A — no page
- Destructive actions: N/A — no page; the journeys' confirmations and reason steps are walked as steps
- Redundancy constraints: N/A — duplicate actions are counted by TASK-065
- Loading state: N/A — no page; the suites wait on the pages' loading and PENDING states, never on fixed sleeps
- Empty state: N/A — no page
- Error state: N/A — no page; the journeys' refusal steps assert their answers — UXS-09's failed rendition, UXS-16's duplicate, UXS-29's C-10, UXS-36's limits

**ROUTE IMPACT:**

- none — no route is added or changed; the journeys request the routes their pages use, captured in the network log

**INTERACTION CONTRACTS:**

- N/A — no INT- contract is built; the journeys press the INT- controls of their pages, INT-012's composer among them, whose tests are each module Task's

**NAVIGATION CONTRACT:**

- Parent: N/A — no page; each journey walks its pages' NAV- contracts
- Entry: N/A — no page
- Back action: N/A — no page; back and cancel on the journeys' route asserted as steps
- Cancel: N/A — no page
- Success destination: N/A — no page; each step's destination asserted as its NAV- contract names
- Error behavior: N/A — no page
- State preservation: N/A — no page
- Mobile behavior: N/A — no page; the phone steps as above

**ACCEPTANCE CRITERIA:**

1. CJ-04 issues and bills invoices and records partial, full, DP, termin and over-payments, credit and its refund, a payment duplicate's warning and the pre-payment Kuitansi's own state, every figure equal to the annex's examples 1–5 (AC-10; UXS-16; UXS-38).
2. CJ-06 records reversals, returns, an invoice revision and a void with the disposition of every reduction, and a backdated command with its mark and its period's number, the originals and the corrections both visible (AC-12; UXS-14; UXS-29).
3. CJ-14 shows receivables and ageing and the reports by company and date, exports one, and meets the row bound and a third concurrent export with MSG-21 and MSG-22, the export equal to the report's rows (AC-11; AC-23; UXS-36).
4. CJ-15 prepares a draft from the recorded facts, previews it beside the form at 1,025, 1,279 and 1,280 px and through the toggle at 1,024 px and Edit → Preview → Finalize on the phone, issues it with its confirmation and number, shows the rendition, and keeps *Terbit* and the same number through a failed rendition (AC-08; UXS-09; DESIGN_SYSTEM §14.3).
5. CJ-17 refuses completion with an unmet condition, completes when all ten hold, force-completes as the Owner with the snapshot, the reason and the residual obligations kept, and cancels a project with its reservations released (AC-21; UXS-37).
6. CJ-20 uploads evidence on the desktop and the phone, links it to a requirement, meets the in-scope duplicate warning, and never lets a draft satisfy a requirement (AC-09; UXS-17).
7. Every journey's run records its evidence in TEST_STRATEGY §16's form with no critical console error and no critical failed request (TEST_STRATEGY §7, §8).
8. Every failure found is recorded with its reproducer and sent to its owning Task; no expected value or requirement is changed here, and no check is reported as passed without its evidence (TS-06; TS-08; AGENT_OPERATING_MODEL).

**TEST REQUIREMENTS:**

- Lint: TL-01 on the suite's code
- Typecheck: TL-02 on the suite's code
- Unit: N/A — no domain logic in this Task
- Feature: N/A — the routes and commands are each module Task's (TL-04)
- Integration: N/A — the races and the database paths are TEST_STRATEGY §6's and the module Tasks'
- Authorization: N/A — the denial suites are TASK-069's; the journeys run with the capabilities their steps need
- Route integrity: N/A — the route crawl and audit are TASK-069's; each journey's network capture flags an unexpected 404, 405 or 500
- Interaction: N/A — the INT- tests are each module Task's; the journeys walk them
- Browser E2E: TL-09 — CJ-04, CJ-06, CJ-14, CJ-15, CJ-17 and CJ-20 with their journeys and UX scenarios, on Chromium and WebKit
- Localization/language switch: N/A — TASK-067's suite; the documents' Indonesian is asserted in CJ-15
- Visual breathing room/density: N/A — TASK-065's gate
- Responsive: N/A — TASK-065's band-edge matrix; CJ-15 runs the composer's widths
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
- run a journey against staging, the VPS, production or real data, or use a real document as a fixture (TEST_STRATEGY §4; FX-10)

# TASK-065 — UI/UX consistency gate, the responsive matrix and the anti-slop audit

Status: REVIEW | Updated: 2026-10-20 | Owner: Planning

Written by WF-PC5-W's writer of bundle W3 of run PC-5 on 2026-10-19 and 2026-10-20 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99 — a REVIEW draft of P11, approved, or not, with the [Task plan](TASK_PLAN.md) at OG-4. **No Task starts before the Owner's execution authorization at OG-4.** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** TODO

**RISK CLASS:** STANDARD

**EXECUTOR:** AGENT

**OBJECTIVE:**

Run AICWDF §27's UI/UX consistency gate, item by item, on every built page of the application — never on the prototype or a reference render —: the measured items by the browser suite — tokens, one primary action, duplicate actions, states, the responsive matrix at every band edge of DESIGN_SYSTEM §6 (TL-11), target sizes, density, mandated copy never cut, the document composer within its tolerance — and the reviewed items against their named rules — modern visual quality and the anti-slop audit SLOP-01–SLOP-45 —, each recorded PASS, FAIL or a count, every finding sent to the Task that owns the page.

**WHY THIS EXISTS:**

DEFINITION_OF_DONE's UI/UX dimension asks every UI Task to answer AICWDF §27's gate (DOD-08), and TEST_STRATEGY §9 says how each item is judged on the built screens; the prototype's measurements were dropped as requirements and the application is measured by P8 (DESIGN_SYSTEM Annex A, DA-01, DA-02, DA-05). The anti-slop constitution of OB §21 is a rule set to be audited, not an impression (DESIGN_SYSTEM §18; UXH-07), and the screens the Owner's decisions changed were never shown in the prototype, so they are verified here on the application (DIR-079 CD-06).

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

- TASK-066 — Accessibility — WCAG 2.2 AA across every page
- TASK-069 — Security test matrix, route integrity and zero dead interactions
- TASK-070 — Full release regression and the device UAT's preparation

**PARALLEL SAFE:** NO — it renders every page on the shared CI stack and browser harness, and its findings reopen the pages' own Tasks

**IN SCOPE:**

- the gate's record for each of INFORMATION_ARCHITECTURE §8's 65 pages — PG-001–PG-065 — and their dialogs, sheets and states: AICWDF §27's twenty-one items, each PASS, FAIL or a count, the optional states — loading, empty, error, destructive action safety — N/A only with their reason; nothing recorded as passed that was not judged (TEST_STRATEGY §9; DOD-08)
- **design system consistency**, measured by the browser suite: the computed tokens — colour, type, spacing, radius, elevation, motion — equal to DESIGN_SYSTEM §5's values, a value outside them a defect (DA-05; SLOP-33); components and patterns as §§9–10 compose them, the shell one component set on every page (DA-03); the reference's recipes where DA-01 and DA-02 name them; an accepted and documented difference of Annex A stays a recorded difference, never a pass (CA-05; CD-05)
- **primary action clear** — one per page or region, present and enabled when its INT- contract says (DESIGN_SYSTEM §9.4) —; **unnecessary duplicate actions: 0**, counted per page against its INT- contracts; **back action clear and navigation contract valid**, judged by the NAV- paths' tests of TEST_STRATEGY §8; **destructive action safety**, IP-08's confirmation before every irreversible command
- the **responsive matrix** (TL-11): every page at the edges of DESIGN_SYSTEM §6's bands — 320, 390, 767, 768, 1,024, 1,025, 1,200, 1,279, 1,280 and 1,680 CSS px —, the phone composition the default, the patterns switching as §11's matrix states, reflow at 320 px without horizontal page scrolling except inside a table's frame (GR-05); a coarse pointer's 44 px targets, the floor tasks' 48 px and 16 px text fields on touch (D-DS-19; D-DS-02); **mobile-first, responsive and adaptive** judged from it
- **loading, empty and error states** — each state of a page as DESIGN_SYSTEM §12.3 lists it, deferred groups included, an empty search saying so (FA-22), outcomes as §12.2 presents them
- **visual breathing room and content density** — measured spacing tokens; the dense mode only on OC-09's named screens, every other screen in the comfortable density, reviewed against AICWDF §18.2 —; **mandated copy never cut**: a select's value, a form bar's reason, a read-only value and a label wrap under WCAG 2.2 1.4.12's text spacing, at 768 px and every band edge (D-DS-18; OB-08)
- **the document composer and preview** on PG-014, PG-033, PG-036, PG-042 and PG-043, and on PG-036 for the drafts that the issuing controls of PG-016, PG-022, PG-024, PG-031 and PG-043 prepare: the draft never satisfying a requirement and never writing, its totals labelled provisional and then equal to the server's, only projected fields in the browser, the paper Indonesian in both interface languages, the issued layout matched within TEST_STRATEGY §9's tolerance — the same template, paper, orientation, margins and page count, the same blocks in the same order, each within 1 mm —, the desktop at 1,025, 1,279 and 1,280 px, the tablet toggle at 1,024 px and the phone's Edit → Preview → Finalize at 390 px with DOC-12's floor step (DESIGN_SYSTEM §14.3; D-DS-20; D-DS-23; INT-012; GAP-053)
- the screens the Owner's decisions changed, verified on the application with their named checks — CA-06's project rows, CA-07's dispatch-ready lines, CA-08's display language, CA-09's purchase number, CA-10's overdue projects, CB-01's imported purchase number, CB-02's PURCHASE scheme and seed, CB-03's receivables position (TEST_STRATEGY §9; DIR-079 CD-06)
- **modern visual quality** and the **anti-slop audit**: SLOP-01–SLOP-45 reviewed on the built screens against each preventing rule and GA1 d)'s dispositions, the dashboard's audit by UXS-43 (DESIGN_SYSTEM §18; UXH-07), the reviewer's judgement against the named rule, confirmed by the Owner at UAT (TEST_STRATEGY §9)
- the gate record's places for the items owned by the other suites — the language items of TASK-067 (TEST_STRATEGY §10) and the accessibility critical checks of TASK-066 (§11) —, never judged here: TASK-070, which depends on all three, enters their evidence
- a starting value tuned by measurement recorded as a Level-1 change with its evidence, its rule unchanged (DESIGN_SYSTEM §1; TEST_STRATEGY §9)
- each finding recorded with its page, item, evidence and proposed minimum fix and sent through the executor feedback loop to the Task that owns the page — a DONE Task's regression becoming a new defect Task under TASK_PLAN §7 —; a rule that looks wrong goes to Planning (AGENT_OPERATING_MODEL)

**OUT OF SCOPE:**

- the full WCAG 2.2 AA audit (TASK-066); both languages and the per-page copy lists (TASK-067); the critical journeys (TASK-062–TASK-064); the route crawl and the zero-dead-interaction counts (TASK-069)
- the Owner's confirmation of the reviewed items at UAT and the real-device checks (TASK-070, TASK-086)
- changing a token, pattern or rule to make a page pass — Planning's, under change control (DESIGN_SYSTEM §1)

**REFERENCES:**

- [TEST_STRATEGY §9](../08-testing/TEST_STRATEGY.md#9-the-uiux-consistency-gate) the gate, the composer and the CD-06 screens; [§2](../08-testing/TEST_STRATEGY.md#2-test-layers) TL-11; [§8](../08-testing/TEST_STRATEGY.md#8-route-navigation-and-interaction-integrity); [§16](../08-testing/TEST_STRATEGY.md#16-evidence); [§17](../08-testing/TEST_STRATEGY.md#17-tooling)
- [DESIGN_SYSTEM §5](../07-ux-design/DESIGN_SYSTEM.md#5-tokens); [§6](../07-ux-design/DESIGN_SYSTEM.md#6-layout-grid-and-breakpoints); [§9](../07-ux-design/DESIGN_SYSTEM.md#9-patterns); [§10](../07-ux-design/DESIGN_SYSTEM.md#10-component-composition); [§11](../07-ux-design/DESIGN_SYSTEM.md#11-responsive-pattern-matrix); [§12](../07-ux-design/DESIGN_SYSTEM.md#12-status-outcome-and-state-patterns); [§14.3](../07-ux-design/DESIGN_SYSTEM.md#143-document-preview); [§18](../07-ux-design/DESIGN_SYSTEM.md#18-anti-ai-slop-mapping) SLOP-01–SLOP-45; [Annex A](../07-ux-design/DESIGN_SYSTEM.md#annex-a-the-carry-forward-disposition-sp-66) DA-01–DA-05; [§4](../07-ux-design/DESIGN_SYSTEM.md#4-design-decisions) D-DS-02, D-DS-18–D-DS-20, D-DS-23
- [INFORMATION_ARCHITECTURE §8](../07-ux-design/INFORMATION_ARCHITECTURE.md#8-screen-inventory) the 65 pages; [INTERACTION_CONTRACTS](../03-workflows/INTERACTION_CONTRACTS.md#1-conventions) IG-01–IG-05; [NAVIGATION_CONTRACTS](../07-ux-design/NAVIGATION_CONTRACTS.md#1-conventions) NV-01–NV-08
- [ADMIN_FLOW §5](../07-ux-design/ADMIN_FLOW/s05-command-model.md#ip-08--confirming-an-irreversible-command) IP-08; [§15](../07-ux-design/ADMIN_FLOW/s14-15-messages-scenarios.md#design) UXS-41–UXS-44; [§16](../07-ux-design/ADMIN_FLOW/s16-17-handoff-traceability.md#16-handoff-obligations) UXH-07
- [DESIGN_REFERENCES](../07-ux-design/DESIGN_REFERENCES.md) — the Golden Frontend Reference, for presentation only (DIR-061 SD-02)
- [DEFINITION_OF_DONE §2](../08-testing/DEFINITION_OF_DONE.md#2-the-checklist) DOD-08; [AGENT_OPERATING_MODEL](../00-governance/AGENT_OPERATING_MODEL.md#executor-feedback-loop)
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (55): DOC-12; IMG-06; UXS-41–UXS-44; UXH-07; UXH-11; SLOP-01–SLOP-45; DA-05; TL-11

**EXPECTED MODULES / FILE AREAS:**

- the browser suite's gate specifications — the computed-token, target-size, duplicate-action and state checks per page, the band-edge matrix, the composer's tolerance comparison — and the gate record per page in the test evidence area TASK-003 set up; the reviewer's anti-slop checklist with its evidence; no application code

**BUSINESS RULES:**

- N/A — the gate judges presentation and interaction against DESIGN_SYSTEM's and ADMIN_FLOW's rules; the composer check's draft rule is the one business rule it touches — a draft never satisfies a requirement (DIR-059 GD-07; BR-ADM-01)

**ARCHITECTURE RULES:**

- judged on the built screens of the CI stack, never on the prototype or a reference render (DESIGN_SYSTEM Annex A; TEST_STRATEGY §9); the reference governs presentation only, behaviour always the canonical specifications (DIR-061 SD-02)
- a measured item is checked by the browser suite and a reviewed item judged against its named rule (TEST_STRATEGY §9); TS-05 — the gate adds to the module Tasks' own UI checks, never replaces them

**SECURITY / PERMISSION REQUIREMENTS:**

- each page rendered as the personas who reach it, each with the capabilities of SECURITY_TEST_MATRIX §2's accounts, so that a control absent without its capability is counted absent, never as a defect (AZ-01; IG-01); the composer's projected-fields check (PJ-15; PJ-19)
- synthetic data only; no secret or credential in the gate's evidence (DOD-21)

**COMPANY SCOPE AND FIELD PROJECTION:**

- N/A — the gate checks presentation; scope and projection are TASK-069's matrix — except the composer's check that only projected fields reach the browser (TEST_STRATEGY §9)

**CONCURRENCY / IDEMPOTENCY:**

- N/A — no command is built or raced here; the states the gate judges include CONFLICT and FAILED as DESIGN_SYSTEM §12.2 presents them

**AUDIT:**

- N/A — the gate writes no business record; its own record is the evidence of TEST_STRATEGY §16 per page

**DATABASE IMPACT ASSESSMENT:**

- Schema change: NONE — test data only
- Migration required: NO
- Existing data transformation: NONE
- Direct production DB access: FORBIDDEN
- Data-loss risk: NONE KNOWN

**MODULARITY / EXTENSIBILITY:**

- Module boundary affected: none — test code and gate records only
- Stable core kept provider-neutral: YES
- External/vendor-specific code isolated: YES — the browser suite's tools stay in the test tree (TEST_STRATEGY §17)
- Replaceable adapter/contract required: NO
- Provider lock-in risk: NONE — Playwright is free and self-hosted in CI
- Future extension impact: LOW — a further page joins the gate's page list
- Speculative abstraction avoided: YES — one check per §27 item, no visual-regression service

**AUTH IMPACT:**

- Authentication change: NONE
- Authentication implementation: LARAVEL_NATIVE — decided once ([EXECUTION_CONTEXT](EXECUTION_CONTEXT.md)), reused, never redesigned
- Auth profile: N/A — the gate signs in with synthetic accounts through the existing sign-in; PG-001–PG-004 are judged as pages
- Google sign-in: N/A — not exercised; PG-001's Google control is judged as a control
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

- Context7: REQUIRED — Playwright's computed-style reads, viewport sizes, pointer emulation and element geometry; shadcn/ui's and Tailwind's token output at the installed versions
- Graphify: OPTIONAL — to trace a page's component to the Task that owns it when a finding is sent
- Browser E2E: REQUIRED — every page rendered at the band edges by the browser suite
- Other: the reference's recipes where DA-01 and DA-02 name them, read from DESIGN_REFERENCES; FX-02 and FX-10 for realistic content and documents (TASK-003)
- Missing safe tool behavior: AUTO-INSTALL project-locally inside this Task (AICWDF §6.3) and record it in TOOLCHAIN; a paid tier or a privileged installation STOPS for the Owner (COST_POLICY)

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: refreshed before the run when Graphify is available ([TOOLCHAIN](../00-governance/TOOLCHAIN.md)); otherwise each page traced to its Task through the FEATURE_COVERAGE_MATRIX
- Pre-change questions: which Task owns each page and shared component; which screens are OC-09's dense ones
- Expected affected areas: the test tree and the gate records only; a finding's fix lands in its owning Task

**CONTEXT7:**

- Topics to verify: `getComputedStyle` and bounding-box reads in Playwright; coarse-pointer and touch emulation; PDF page geometry for the composer's tolerance
- Version-sensitive assumptions: none from memory

**UI / UX REQUIREMENTS:**

- Follow global design rules: YES — the gate judges every built page against them (EXECUTION_CONTEXT, global UI rules)
- Indonesian default: YES — TASK-070 enters TASK-067's evidence for it in the gate record
- English support: YES — TASK-070 enters TASK-067's evidence for it in the gate record
- Plain-language Indonesian: YES — reviewed against the glossary and the copy rules (LOCALIZATION §4), TASK-067's evidence entered by TASK-070
- Language switch impact: NONE — the switch's behaviour is TASK-067's and CJ-18's; the gate checks the layout in both languages at the band edges
- Mobile-first: YES — the phone composition judged as the default (TEST_STRATEGY §9)
- Responsive: YES — every page at the ten band edges of TL-11
- Adaptive: YES — the pattern switches of DESIGN_SYSTEM §11 judged per page
- Visual breathing room: REQUIRED — measured spacing tokens and the reviewer's judgement under AICWDF §18.2
- Comfortable density: REQUIRED — the dense mode only on OC-09's screens
- shadcn/ui reuse: judged — components and patterns as DESIGN_SYSTEM §§9–10 compose them, no page-by-page convention (UXH-11)
- Design reference: the Golden Frontend Reference for presentation only, applied to the R1 structure (DIR-061 SD-02; DIR-059); a difference is a finding, an accepted one stays recorded (CA-05)
- Primary action: judged — one per page or region (DESIGN_SYSTEM §9.4)
- Secondary actions: judged — in "Tindakan Lain" or their parts, never duplicating the primary (PT-03)
- Destructive actions: judged — IP-08's confirmation before every irreversible command, the destructive style only on the confirming button (PT-11)
- Redundancy constraints: judged — unnecessary duplicate actions counted per page, target 0
- Loading state: judged — every page and deferred group per DESIGN_SYSTEM §12.3 and PT-19
- Empty state: judged — one sentence and at most one action, no illustration (PT-19; SLOP-13)
- Error state: judged — outcomes as DESIGN_SYSTEM §12.2 presents them, a group's error with its retry

**ROUTE IMPACT:**

- none — no route is added or changed; the gate requests every page's view route

**INTERACTION CONTRACTS:**

- N/A — no INT- contract is built; the gate counts each page's controls against its INT- contracts (IG-01)

**NAVIGATION CONTRACT:**

- Parent: N/A — no page; the gate records each page's NAV- contract as valid from TEST_STRATEGY §8's NAV- tests
- Entry: N/A — no page
- Back action: N/A — no page; "Back action clear" judged per page
- Cancel: N/A — no page
- Success destination: N/A — no page
- Error behavior: N/A — no page
- State preservation: N/A — no page
- Mobile behavior: N/A — no page; the phone composition judged per page

**ACCEPTANCE CRITERIA:**

1. Every one of the 65 pages has a gate record with AICWDF §27's twenty-one items: the items this gate judges each PASS, FAIL or a count, the optional states N/A only with their reason, and the places of the language and accessibility items kept for TASK-070 to fill from TASK-067's and TASK-066's evidence (TEST_STRATEGY §9; DOD-08).
2. On every page the computed colour, type, spacing, radius, elevation and motion values equal DESIGN_SYSTEM §5's tokens; any other value is recorded as a defect (DA-05; SLOP-33).
3. Every page has one primary action per page or region, and the count of unnecessary duplicate actions is 0 (DESIGN_SYSTEM §9.4; INTERACTION_CONTRACTS).
4. Every page renders at 320, 390, 767, 768, 1,024, 1,025, 1,200, 1,279, 1,280 and 1,680 CSS px with its band's composition, no horizontal page scroll outside a table's frame at 320 px, and targets of 44 px for a coarse pointer and 48 px on the floor tasks (TL-11; GR-05; D-DS-19).
5. No mandated copy is cut: a select's value, a form bar's reason, a read-only value and a label wrap under 1.4.12's text spacing at 768 px and every band edge (D-DS-18).
6. The composer and preview pass TEST_STRATEGY §9's checks on every page that creates or edits a generated document, the preview matching the issued rendition within the stated tolerance (D-DS-23; GAP-053).
7. The eight screens of DIR-079 CD-06 are verified on the application with their named checks (TEST_STRATEGY §9).
8. Each of SLOP-01–SLOP-45 is reviewed on the built screens with its result and evidence, the dashboard's UXS-43 audit included, and none is recorded as passed without its review (DESIGN_SYSTEM §18; UXH-07).
9. Every finding is sent to the Task that owns its page with its evidence; no token, pattern or rule is changed in this Task (AGENT_OPERATING_MODEL; DESIGN_SYSTEM §1).

**TEST REQUIREMENTS:**

- Lint: TL-01 on the suite's code
- Typecheck: TL-02 on the suite's code
- Unit: N/A — no domain logic in this Task
- Feature: N/A — no route or command in this Task
- Integration: N/A — no database, queue or job path in this Task
- Authorization: N/A — the gate renders as the personas; the denial suites are TASK-069's
- Route integrity: N/A — the crawl is TASK-069's
- Interaction: the duplicate-action and primary-action counts against each page's INT- contracts (TL-08's contracts as the reference)
- Browser E2E: every page rendered and measured in the browser suite (TL-09's harness)
- Localization/language switch: N/A — TASK-067's suite; the layout in both languages at the band edges checked here
- Visual breathing room/density: TEST_STRATEGY §9 — measured spacing tokens and the reviewed density
- Responsive: TL-11 — the ten band edges on every page
- Accessibility: N/A — TASK-066's audit; TASK-070 enters its critical checks' result in the gate record
- Production build: TL-13 for the suite's code; the pages from the CI stack's build
- Security: N/A — TASK-069's matrix; the suite's secret-like scan (SM-15)
- Performance: N/A — TASK-068's budgets
- Migration: N/A — no migration or import code in this Task
- Other: the anti-slop review SLOP-01–SLOP-45 with its evidence; the CD-06 screens' verification

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
- judge an item on the prototype or a reference render, or record an item as passed that was not judged (DESIGN_SYSTEM Annex A; TS-08)
- change a token, a pattern, a rule or a page inside this Task to make the gate pass — the finding goes to its owner (AGENT_OPERATING_MODEL)
- add a page, control, chart or decorative element that DESIGN_SYSTEM does not define (OE-20; SLOP-34)

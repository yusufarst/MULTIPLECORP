# TASK-066 — Accessibility — WCAG 2.2 AA across every page

Status: REVIEW | Updated: 2026-10-20 | Owner: Planning

Written by WF-PC5-W's writer of bundle W3 of run PC-5 on 2026-10-19 and 2026-10-20 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99 — a REVIEW draft of P11, approved, or not, with the [Task plan](TASK_PLAN.md) at OG-4. **No Task starts before the Owner's execution authorization at OG-4.** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** TODO

**RISK CLASS:** STANDARD

**EXECUTOR:** AGENT

**OBJECTIVE:**

Audit every built page against WCAG 2.2 at level AA and DESIGN_SYSTEM §15: an accessibility engine inside the browser suite on every page state the journeys and the gate reach, at the phone and desktop widths; the measured checks — contrast, focus visible and never obscured, target sizes, reflow at 320 CSS px, text spacing, reduced motion —; the keyboard-only runs of every critical journey; and the manual checks of TEST_STRATEGY §11 prepared for Layer 3 — a screen reader on the desktop and on a phone, status beyond colour, the idle warning, announced status messages, dialogs' focus and return —, each result recorded with its evidence and each defect sent to the Task that owns the page.

**WHY THIS EXISTS:**

CAP-14 and AC-14 require an accessible interface on the phone and the desktop, and UXH-05 hands P8 the WCAG 2.2 AA audit of the built screens with measured contrast (TEST_STRATEGY §11). Every UI Task runs AICWDF §27's critical checks on its own pages (DOD-17); the full audit of every AA criterion, across pages and journeys, runs here at Layer 3 (TEST_STRATEGY §2, TL-12; §3). The prototype's reflow check at 320 px (GR-05) was a Stage 4 verification only — the application must prove it.

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
- TASK-062 — Critical journeys A — the golden flow, purchasing, fulfilment, counts, scanning and SIPLAH
- TASK-063 — Critical journeys B — finance, corrections, reports, documents, completion and the checklist
- TASK-064 — Critical journeys C — isolation, sign-in, access, search, approvals, import, language, masters, command identity and status
- TASK-065 — UI/UX consistency gate, the responsive matrix and the anti-slop audit

**BLOCKS:**

- TASK-069 — Security test matrix, route integrity and zero dead interactions
- TASK-070 — Full release regression and the device UAT's preparation

**PARALLEL SAFE:** NO — it runs inside the shared browser suite on the CI stack, and its findings reopen the pages' own Tasks

**IN SCOPE:**

- **automated**: an accessibility engine inside the browser suite on every page state the journeys of TASK-062–TASK-064 and the gate of TASK-065 reach — dialogs, sheets, confirmations, deferred groups and outcomes included —, at the phone and desktop widths, failing the run on a violation of an AA criterion it can decide (TEST_STRATEGY §11); this Task depends on those four, so their page states and scripts exist when it runs
- **measured**: the rendered contrast of text (4.5:1), large text and control boundaries (3:1), forced-colors mode keeping system colours; focus visible — the 2 px ring with its offset — and never obscured by a sticky header, action bar, scan dock or toast (2.4.11); targets of 24 px with a fine pointer, 44 px with a coarse pointer at every width and 48 px on the floor tasks (2.5.8; D-DS-19); reflow at 320 CSS px without horizontal page scrolling except inside a table's frame (1.4.10; GR-05); text spacing and 200 % zoom losing nothing, mandated copy wrapping (1.4.12; D-DS-18); reduced motion removing movement (D-DS-17)
- **keyboard-only**: every critical journey operated by keyboard alone in reading order, with no trap except a dialog's focus loop and no positive tab index — UXS-41's desktop quotation with many lines among them —; no shortcut a single printable key and none active in a scan field (DESIGN_SYSTEM §13; UXI-42)
- DESIGN_SYSTEM §15's rules checked per page: one `h1`, headings in order, landmarks and the skip link "Langsung ke Konten"; dialogs moving focus in, an inert background, Escape closing except the re-authentication, focus returning to the trigger; visible labels, `autocomplete` on the account's own e-mail and password, errors tied to fields and announced, paste and password managers allowed (3.3.8), data already entered not asked again (3.3.7); outcomes announced through a live region present from load — polite for COMMITTED and PENDING, assertive for REJECTED, CONFLICT and FAILED — and scan feedback with focus kept in the scan field (4.1.3); the idle warning with "Tetap Masuk" (2.2.1); no function needing a drag (2.5.7); consistent help (3.2.6); a different language's content carrying its own `lang`
- **manual, at Layer 3**: a screen reader on the critical journeys on the desktop and on a phone; status by more than colour, in greyscale and for colour-blind users (UXS-42); the idle warning, the announced status messages and the dialogs' focus and return heard as a user hears them — each prepared here as a per-journey script with its expected announcements, run by a person with the reader and the device recorded, and recorded as not run until it is run (TEST_STRATEGY §11)
- the AICWDF §27 critical checks' result per page, recorded as this Task's evidence, which TASK-070 enters in TASK-065's gate record
- each defect recorded with its page, criterion, evidence and proposed minimum fix and sent through the executor feedback loop to the Task that owns the page — a DONE Task's regression becoming a new defect Task under TASK_PLAN §7 (AGENT_OPERATING_MODEL)

**OUT OF SCOPE:**

- running TEST_STRATEGY §11's manual Layer-3 checks with real assistive technology — a person the Owner names, in TASK-086's UAT (placed there in Part 4); this Task prepares their scripts
- each UI Task's own critical checks on its pages (DOD-17); the UI/UX gate's other items (TASK-065); the journeys themselves (TASK-062–TASK-064)
- changing a token, colour or component to pass a criterion — Planning's change under DESIGN_SYSTEM §1, the defect's owner applying it
- the real-device checks of TEST_STRATEGY §12 beyond the screen reader on a phone (TASK-070, TASK-086)

**REFERENCES:**

- [TEST_STRATEGY §11](../08-testing/TEST_STRATEGY.md#11-accessibility); [§2](../08-testing/TEST_STRATEGY.md#2-test-layers) TL-12; [§3](../08-testing/TEST_STRATEGY.md#3-the-three-regression-layers) Layer 3; [§16](../08-testing/TEST_STRATEGY.md#16-evidence); [§17](../08-testing/TEST_STRATEGY.md#17-tooling)
- [DESIGN_SYSTEM §15](../07-ux-design/DESIGN_SYSTEM.md#15-accessibility); [§13](../07-ux-design/DESIGN_SYSTEM.md#13-keyboard-and-scanner); [§5.1](../07-ux-design/DESIGN_SYSTEM.md#51-colour); [§6](../07-ux-design/DESIGN_SYSTEM.md#6-layout-grid-and-breakpoints); [§12.1](../07-ux-design/DESIGN_SYSTEM.md#121-status-system); [§4](../07-ux-design/DESIGN_SYSTEM.md#4-design-decisions) D-DS-17–D-DS-19; [Annex B](../07-ux-design/DESIGN_SYSTEM.md#annex-b-the-carried-items-gr-01gr-23) GR-05
- [V1_SCOPE](../01-product/V1_SCOPE.md#must-ship) CAP-14; [ACCEPTANCE_CRITERIA](../01-product/ACCEPTANCE_CRITERIA.md#product-acceptance-contracts) AC-14
- [ADMIN_FLOW §16](../07-ux-design/ADMIN_FLOW/s16-17-handoff-traceability.md#16-handoff-obligations) UXH-05; [§15](../07-ux-design/ADMIN_FLOW/s14-15-messages-scenarios.md#design) UXS-41, UXS-42; [§3](../07-ux-design/ADMIN_FLOW/s01-04-foundations.md#3-derived-interaction-requirements) UXI-42
- [INFORMATION_ARCHITECTURE §8](../07-ux-design/INFORMATION_ARCHITECTURE.md#8-screen-inventory) the 65 pages
- [DEFINITION_OF_DONE §2](../08-testing/DEFINITION_OF_DONE.md#2-the-checklist) DOD-08, DOD-17; [AGENT_OPERATING_MODEL](../00-governance/AGENT_OPERATING_MODEL.md#executor-feedback-loop)
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (9): O-07; CAP-14; AC-14; IMG-09; UXS-41–UXS-42; UXH-05; GR-05; TL-12

**EXPECTED MODULES / FILE AREAS:**

- the accessibility engine's hook in the browser suite, the measured checks per page, the keyboard-only journey runs and the manual scripts for Layer 3, in the test area TASK-003 set up; the audit record per page and criterion; no application code

**BUSINESS RULES:**

- N/A — the audit judges accessibility against WCAG 2.2 AA and DESIGN_SYSTEM §15; no business rule is read or changed

**ARCHITECTURE RULES:**

- the audit runs on the built screens of the CI stack, never on the prototype or a reference render (DESIGN_SYSTEM Annex A); the automated engine adds to the measured and manual checks and never replaces them (TS-05)

**SECURITY / PERMISSION REQUIREMENTS:**

- each page audited as the personas who reach it, with the capabilities of SECURITY_TEST_MATRIX §2's accounts; synthetic data and accounts only, no secret in the audit's evidence (DOD-21)
- the re-authentication and step-up dialogs audited without weakening them: paste and password managers allowed, `autocomplete` on the account's own fields only (3.3.8; AU-15)

**COMPANY SCOPE AND FIELD PROJECTION:**

- N/A — the audit checks accessibility; scope and projection are TASK-069's matrix

**CONCURRENCY / IDEMPOTENCY:**

- N/A — no command is built or raced; the announced outcomes include CONFLICT and FAILED as their live region states (4.1.3)

**AUDIT:**

- N/A — the audit writes no business record; its own record is TEST_STRATEGY §16's evidence per page and criterion

**DATABASE IMPACT ASSESSMENT:**

- Schema change: NONE — test data only
- Migration required: NO
- Existing data transformation: NONE
- Direct production DB access: FORBIDDEN
- Data-loss risk: NONE KNOWN

**MODULARITY / EXTENSIBILITY:**

- Module boundary affected: none — test code and audit records only
- Stable core kept provider-neutral: YES
- External/vendor-specific code isolated: YES — the accessibility engine stays in the browser suite (TEST_STRATEGY §17)
- Replaceable adapter/contract required: NO
- Provider lock-in risk: NONE — a free engine inside the self-hosted suite, verified under DEP-07
- Future extension impact: LOW — a further page joins the audit's page list
- Speculative abstraction avoided: YES — the engine's rule set and the measured checks, no accessibility-overlay product

**AUTH IMPACT:**

- Authentication change: NONE
- Authentication implementation: LARAVEL_NATIVE — decided once ([EXECUTION_CONTEXT](EXECUTION_CONTEXT.md)), reused, never redesigned
- Auth profile: N/A — the audit signs in with synthetic accounts; PG-001–PG-004 are audited as pages
- Google sign-in: N/A — not exercised; PG-001's Google control is audited as a control
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

- Context7: REQUIRED — the accessibility engine's WCAG 2.2 AA rule tags and its Playwright integration, contrast measurement and forced-colors emulation, `prefers-reduced-motion` emulation, at the installed versions
- Graphify: OPTIONAL — to trace a page's component to the Task that owns it when a defect is sent
- Browser E2E: REQUIRED — the engine and the measured checks run inside the browser suite
- Other: a desktop screen reader and a phone screen reader for the manual Layer-3 scripts, run by a person; FX-02 for realistic content (TASK-003)
- Missing safe tool behavior: AUTO-INSTALL project-locally inside this Task (AICWDF §6.3) and record it in TOOLCHAIN; a paid tier or a privileged installation STOPS for the Owner (COST_POLICY)

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: refreshed before the run when Graphify is available ([TOOLCHAIN](../00-governance/TOOLCHAIN.md)); otherwise each page traced to its Task through the FEATURE_COVERAGE_MATRIX
- Pre-change questions: which shared component a failing criterion lives in, so that one fix reaches every page that uses it
- Expected affected areas: the test tree and the audit records only; a defect's fix lands in its owning Task

**CONTEXT7:**

- Topics to verify: the engine's rule set for WCAG 2.2 AA and which criteria it cannot decide; Playwright's `emulateMedia` for reduced motion and forced colors; keyboard focus inspection
- Version-sensitive assumptions: none from memory

**UI / UX REQUIREMENTS:**

- Follow global design rules: YES — DESIGN_SYSTEM §15's rules audited on every page
- Indonesian default: N/A — the audit's language checks are `lang` attributes only; the copy is TASK-067's
- English support: YES — each page audited in both languages, a different language's content carrying its own `lang`
- Plain-language Indonesian: N/A — TASK-067's review
- Language switch impact: NONE — audited after a switch as any page state
- Mobile-first: YES — the phone widths audited, reflow at 320 CSS px
- Responsive: YES — the audit at the phone and desktop widths; the full band matrix is TASK-065's
- Adaptive: YES — coarse-pointer targets at every width (D-DS-19)
- Visual breathing room: N/A — TASK-065's gate; text spacing overrides audited here (1.4.12)
- Comfortable density: N/A — TASK-065's gate
- shadcn/ui reuse: N/A — no component is built; the components' accessible names and roles audited
- Design reference: N/A — the audit judges against WCAG 2.2 AA and DESIGN_SYSTEM §15
- Primary action: N/A — no page; each primary action audited for name, focus and target size
- Secondary actions: N/A — no page
- Destructive actions: N/A — no page; confirmations audited as dialogs — focus in, inert background, focus returned
- Redundancy constraints: N/A — TASK-065 counts duplicates
- Loading state: N/A — no page; the busy states audited as announced (4.1.3)
- Empty state: N/A — no page; an empty search's status line audited as announced (FA-22)
- Error state: N/A — no page; errors audited as tied to fields and announced (3.3.1; 4.1.3)

**ROUTE IMPACT:**

- none — no route is added or changed; the audit requests every page's view route

**INTERACTION CONTRACTS:**

- N/A — no INT- contract is built; each page's controls are audited by name, role, state, keyboard and target

**NAVIGATION CONTRACT:**

- Parent: N/A — no page; the skip link, landmarks and back actions audited per page
- Entry: N/A — no page
- Back action: N/A — no page
- Cancel: N/A — no page
- Success destination: N/A — no page
- Error behavior: N/A — no page
- State preservation: N/A — no page
- Mobile behavior: N/A — no page; the phone widths audited as above

**ACCEPTANCE CRITERIA:**

1. The engine runs on every page state the journeys and the gate reach, at the phone and desktop widths, and the run fails on any violation of an AA criterion it can decide; the final run reports none (TEST_STRATEGY §11; TL-12).
2. Every page's rendered text meets 4.5:1 and its large text and control boundaries 3:1, also in forced-colors mode (DESIGN_SYSTEM §15).
3. Focus is visible on every focusable element and never obscured by a sticky region, a dock or a toast (2.4.11).
4. Targets measure at least 24 px with a fine pointer, 44 px with a coarse pointer at every width and 48 px on the floor tasks (2.5.8; D-DS-19).
5. Every page reflows at 320 CSS px without horizontal page scrolling except inside a table's frame, and text spacing overrides and 200 % zoom lose nothing (1.4.10; 1.4.12; GR-05).
6. Under reduced motion no movement remains beyond a crossfade of at most 150 ms (D-DS-17).
7. Every critical journey completes by keyboard alone without a trap, UXS-41's quotation included, and no single printable key acts as a shortcut (DESIGN_SYSTEM §13; UXI-42).
8. Outcomes are announced through a live region present from load — polite for COMMITTED and PENDING, assertive for REJECTED, CONFLICT and FAILED —, scan feedback keeps focus in the scan field, and the idle warning can be extended any number of times (4.1.3; 2.2.1).
9. Every state is distinguishable without colour, in greyscale and for colour-blind users (UXS-42; DESIGN_SYSTEM §12.1).
10. The manual screen-reader runs on the desktop and a phone have their scripts and expected announcements; their results are recorded with the reader and the device once a person runs them at Layer 3, and are never reported as passed before (TEST_STRATEGY §11; TS-08).
11. Every defect found is sent to its owning Task with its evidence; no criterion is waived and no rule changed in this Task (AGENT_OPERATING_MODEL; DOD-03).

**TEST REQUIREMENTS:**

- Lint: TL-01 on the suite's code
- Typecheck: TL-02 on the suite's code
- Unit: N/A — no domain logic in this Task
- Feature: N/A — no route or command in this Task
- Integration: N/A — no database, queue or job path in this Task
- Authorization: N/A — pages audited as the personas; the denial suites are TASK-069's
- Route integrity: N/A — the crawl is TASK-069's
- Interaction: the controls' names, roles, states and keyboard operation per page (TL-08's controls as the inventory)
- Browser E2E: the journeys' runs reused for the keyboard-only and engine checks (TL-09)
- Localization/language switch: each page audited in both languages for `lang` (TL-10's pages)
- Visual breathing room/density: N/A — TASK-065's gate
- Responsive: the phone and desktop widths and the 320 px reflow; the full matrix is TASK-065's (TL-11)
- Accessibility: TL-12 — automated, measured and keyboard-only in CI; the manual Layer-3 runs prepared and recorded
- Production build: TL-13 for the suite's code; the pages from the CI stack's build
- Security: N/A — TASK-069's matrix; the suite's secret-like scan (SM-15)
- Performance: N/A — TASK-068's budgets
- Migration: N/A — no migration or import code in this Task
- Other: the critical checks' result per page recorded as this Task's evidence, entered in TASK-065's gate record by TASK-070

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
- waive a criterion, record a manual check as passed before a person has run it, or treat the engine's silence as proof of a criterion it cannot decide (TS-08; DOD-03)
- add an accessibility overlay or third-party widget to the application (DEP-07; OE-20)
- change a colour, token or component inside this Task — the defect goes to its owner (AGENT_OPERATING_MODEL)

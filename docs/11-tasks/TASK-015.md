# TASK-015 — Design system — tokens, components, patterns and states

Status: REVIEW | Updated: 2026-10-19 | Owner: Planning

Written by the executor of run PC-5 on 2026-10-19 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99 — a REVIEW draft of P11, approved, or not, with the [Task plan](TASK_PLAN.md) at OG-4. **No Task starts before the Owner's execution authorization at OG-4.** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** TODO

**RISK CLASS:** STANDARD

**EXECUTOR:** AGENT

**OBJECTIVE:**

Build the design system as DESIGN_SYSTEM states it — tokens, layout bands, typography, icons and motion, the shadcn/ui primitives kept generic, the feature components PT-01–PT-31 with their states, the status and outcome patterns, keyboard and scanner rules, accessibility and the anti-slop discipline — ready for every page Task.

**WHY THIS EXISTS:**

A consistent, accessible, comfortable interface in natural Indonesian is V1's experience contract (CAP-14; DIR-035 D4); DESIGN_SYSTEM's D-DS-, UXV-, PT-, KB- and SLOP- rules, its Annexes A and B, and ADMIN_FLOW's interaction patterns IP- are built once here, not per page.

**DEPENDS ON:**

- TASK-014 — Localization — locales, the language switch and the key catalogue

**BLOCKS:**

- TASK-016 — Application shell, navigation and the company context
- TASK-017 — Password sign-in, sessions and credential links

**PARALLEL SAFE:** NO — every page Task composes these components

**IN SCOPE:**

- the tokens of §5 (colour, typography, spacing and density, radius, elevation, layers, motion with reduced motion) as the only values (SLOP-; DA-05)
- the layout, grid and bands of §6, the typography of §7, the icons and motion of §8 — Lucide pinned, Inter self-hosted from the application's origin (WS-08; DIR-059 GD-08)
- the shadcn/ui primitives copied into the codebase and kept generic (§10), and the feature components of §10 with the patterns PT-01–PT-31 of §9
- the status, outcome and state patterns of §12, keyboard and scanner of §13 (KB-), domain presentations of §14, accessibility of §15, copy and formatting of §16, print views of §17 and the anti-slop mapping of §18
- the interaction patterns of ADMIN_FLOW IP-01–IP-21 in their presentation — answers, CONFLICT, FAILED and retry, duplicates, confirmations, step-up, validation, background work, list continuation, search and lookup
- the carried items of Annex B as GR- states them — GR-04's grey lint rule with TASK-002, GR-06's rem keys, GR-18's icon set — and Annex A's rules
- the two choices DESIGN_SYSTEM §10 leaves to P11 under DEP-07: Base UI or Radix for the primitives, and Toast or Sonner — chosen with their verified versions, licences at no cost, and recorded in TOOLCHAIN
- HO-37: whether a vetted primitive needs `'unsafe-inline'` styles under WS-08's CSP — verified; if one does, STOP and report it to Planning with the evidence, never loosening the CSP

**OUT OF SCOPE:**

- the shell and navigation (TASK-016)
- any page (its own Task)
- the document composer and paper (TASK-029)

**REFERENCES:**

- [DESIGN_SYSTEM §1–4](../07-ux-design/DESIGN_SYSTEM.md#1-conventions)
- [DESIGN_SYSTEM §5](../07-ux-design/DESIGN_SYSTEM.md#5-tokens)
- [DESIGN_SYSTEM §6](../07-ux-design/DESIGN_SYSTEM.md#6-layout-grid-and-breakpoints)
- [DESIGN_SYSTEM §9](../07-ux-design/DESIGN_SYSTEM.md#9-patterns)
- [DESIGN_SYSTEM §10](../07-ux-design/DESIGN_SYSTEM.md#10-component-composition)
- [DESIGN_SYSTEM §11](../07-ux-design/DESIGN_SYSTEM.md#11-responsive-pattern-matrix)
- [DESIGN_SYSTEM §12](../07-ux-design/DESIGN_SYSTEM.md#12-status-outcome-and-state-patterns)
- [DESIGN_SYSTEM §13](../07-ux-design/DESIGN_SYSTEM.md#13-keyboard-and-scanner)
- [DESIGN_SYSTEM §15](../07-ux-design/DESIGN_SYSTEM.md#15-accessibility)
- [DESIGN_SYSTEM §18](../07-ux-design/DESIGN_SYSTEM.md#18-anti-ai-slop-mapping)
- [DESIGN_SYSTEM Annexes A and B](../07-ux-design/DESIGN_SYSTEM.md#annex-a-the-carry-forward-disposition-sp-66)
- [ADMIN_FLOW §5–§9](../07-ux-design/ADMIN_FLOW/s05-command-model.md#5-command-interaction-model) IP-01–IP-21
- [DESIGN_REFERENCES](../07-ux-design/DESIGN_REFERENCES.md)
- [SECURITY §4](../05-security/SECURITY.md#4-web-and-input-security) WS-08, WS-09
- [CONCURRENCY_IDEMPOTENCY §19](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s19-20-handoff-traceability.md#19-handoff-obligations) HO-37
- the architecture challenge's sequences 17b ([P11 gate record](evidence/P11_QUALITY_GATE.md#whole-system-architecture-challenge-sp-98))
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (303): SH-02; AC-14; REF-052–REF-055; BR-DT-01–BR-DT-06; SF-DATE; L-09; L-14; INT-005; INT-007–INT-009; INT-011; IG-01–IG-05; C-50; WS-08–WS-09; TM-28; TM-37; TM-50; H7-02–H7-03; H7-06–H7-08; H7-10–H7-11; H8-20; HO-01–HO-03; HO-05–HO-10; HO-37–HO-38; PF-13–PF-18; PF-41–PF-42; UXI-01–UXI-03; UXI-11; UXI-13–UXI-14; UXI-21; UXI-23; UXI-25; UXI-28; UXI-30; UXI-36–UXI-39; UXI-42; D-UX-01–D-UX-02; D-UX-04–D-UX-11; IP-01; IP-07–IP-10; IP-13–IP-14; IP-17–IP-19; MSG-13; MSG-39–MSG-40; UXS-05–UXS-06; UXS-08; UXS-10; UXS-13–UXS-14; UXS-21; UXS-24; UXS-26; UXS-33; UXS-35; UXS-42; UXH-11; UXV-01–UXV-22; D-DS-01–D-DS-23; PT-01–PT-31; KB-01–KB-11; SLOP-01–SLOP-45; DA-03–DA-09; DA-11–DA-12; GR-01; GR-04–GR-09; GR-15–GR-16; GR-18–GR-21; component Shell — sidebar, *Menu lengkap*, icon rail, top bar, skip link; component Phone app bar and bottom bar; *Lainnya*; component Page header; component Button; component Split action; component Field and number entry; component Select and lookup; component Date field and business date; component Date range; component Code entry; component Checkbox, radio and switch; component Status pill and status text; component Company tag and count badge; component Card and section card; component KPI tile; component Facts, key-value and metric strip; component Tabs; component Segmented control; component Data table and row list; component Filter toolbar and company view; component States of a region; component System page; component Timeline; component Checklist; component Stage track and stage navigator; component Segmented stage indicator; component The now strip; component Dialog and confirmation; component Menu and "Tindakan Lain"; account menu; component Popover, tooltip and long text; component Search and scan; component Toast; component Notice and alert; component Checks, result, next step and prerequisite; component Disclosure; component Position and inventory blocks; component Multi-line editor; component Sticky action bar; component Progress bar, keycap, avatar, icon disc, link

**EXPECTED MODULES / FILE AREAS:**

- `resources/js/components/ui/` (primitives), `resources/js/components/` (feature components), the token files and Tailwind configuration, the self-hosted fonts and icons

**BUSINESS RULES:**

- N/A — components receive business decisions as data; none decides a capability, number, state or wording (DESIGN_SYSTEM §10)

**ARCHITECTURE RULES:**

- primitives generic, feature components composing them and receiving the server's decisions as data (§10); presentation only, behaviour from the canonical specifications (DIR-061 SD-02)

**SECURITY / PERMISSION REQUIREMENTS:**

- no raw HTML rendering of user text (WS-09); fonts and icons served from the application's origin under the CSP (WS-08); no external script or font host

**COMPANY SCOPE AND FIELD PROJECTION:**

- the company label and filter components (PT-04) display the scope the server sends; they never compute it

**CONCURRENCY / IDEMPOTENCY:**

- the presentation of COMMITTED, REJECTED, CONFLICT, PENDING and FAILED and of one `command_id` per intent (IP-01–IP-04) — the mechanism is TASK-013's

**AUDIT:**

- N/A — presentation only

**DATABASE IMPACT ASSESSMENT:**

- Schema change: NONE — no table or column
- Migration required: NO
- Existing data transformation: NONE
- Direct production DB access: FORBIDDEN
- Data-loss risk: NONE KNOWN

**MODULARITY / EXTENSIBILITY:**

- Module boundary affected: platform presentation
- Stable core kept provider-neutral: YES
- External/vendor-specific code isolated: YES — framework facilities or a small interface (ARCHITECTURE §2)
- Replaceable adapter/contract required: NO
- Provider lock-in risk: NONE beyond the Owner's baseline stack
- Future extension impact: LOW
- Speculative abstraction avoided: YES — no repository class per model, no generic workflow or rules engine (ARCHITECTURE §1)
- the primitive library replaceable behind shadcn/ui's copies

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

- Context7: REQUIRED — the current shadcn/ui documentation for the chosen primitive variant, Tailwind's configuration, Lucide and the font loading
- Graphify: N/A — the codebase is young
- Browser E2E: REQUIRED — components verified with Playwright's component testing or on the first pages that use them; no test route ships in a build (TL-13)
- Other: an automated accessibility engine in the browser suite (TEST_STRATEGY §11)
- Missing safe tool behavior: AUTO-INSTALL project-locally inside this Task (AICWDF §6.3) and record it in TOOLCHAIN; a paid tier or a privileged installation STOPS for the Owner (COST_POLICY)

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: N/A — the codebase is too young for a graph to add value; the impact is read from the diff and ARCHITECTURE §3–§4
- Pre-change questions: which patterns each page family needs first
- Expected affected areas: the IN SCOPE areas only

**CONTEXT7:**

- Topics to verify: shadcn/ui's Base UI and Radix variants; Toast and Sonner; Tailwind's token configuration; Lucide's pinned release names
- Version-sensitive assumptions: Lucide icon names checked at the pinned release (DESIGN_SYSTEM §12.1)

**UI / UX REQUIREMENTS:**

- Follow global design rules: YES — this Task builds them
- Indonesian default: YES
- English support: YES
- Plain-language Indonesian: YES
- Language switch impact: components hold longer English text without breaking (LOCALIZATION §3.7)
- Mobile-first: YES
- Responsive: YES — the bands of §6 and the matrix of §11
- Adaptive: YES — the phone and desktop variants §9 names
- Visual breathing room: REQUIRED (AICWDF §18.2)
- Comfortable density: REQUIRED
- shadcn/ui reuse: every primitive from shadcn/ui
- Design reference: the Golden Frontend Reference v1.0.0 for presentation only, adapted to shadcn/ui (DESIGN_REFERENCES)
- Primary action: one per region (PT-11)
- Secondary actions: as PT-11 and the menus state
- Destructive actions: confirmation as PT-26 states
- Redundancy constraints: no duplicate critical action (TEST_STRATEGY §9)
- Loading state: PT-19
- Empty state: PT-19
- Error state: PT-19 and PT-14

**ROUTE IMPACT:**

- none

**INTERACTION CONTRACTS:**

- the presentation side of INT-005, INT-007–INT-009 and INT-011

**NAVIGATION CONTRACT:**

- Parent: N/A — components only; navigation is TASK-016's
- Entry: N/A — components only; navigation is TASK-016's
- Back action: N/A — components only; navigation is TASK-016's
- Cancel: N/A — components only; navigation is TASK-016's
- Success destination: N/A — components only; navigation is TASK-016's
- Error behavior: N/A — components only; navigation is TASK-016's
- State preservation: N/A — components only; navigation is TASK-016's
- Mobile behavior: N/A — components only; navigation is TASK-016's

**ACCEPTANCE CRITERIA:**

1. Every token of §5 exists and no component uses a value outside them (SLOP-).
2. Each of PT-01–PT-31 exists with its states — loading, empty, error, denied — and passes the accessibility checks at the bands of TL-11.
3. IP-01–IP-21's presentations behave as ADMIN_FLOW states, keyboard included (KB-).
4. The Base UI or Radix and the Toast or Sonner choices are recorded with versions and licences; no paid component is used.
5. HO-37 is answered with evidence: no `'unsafe-inline'` style, or a STOP report naming the primitive.

**TEST REQUIREMENTS:**

- Lint: TL-01 with GR-04's grey rule
- Typecheck: TL-02
- Unit: N/A — no PHP logic; formatting helpers covered by TL-10
- Feature: N/A — no route
- Integration: N/A — no database, queue or job path in this Task
- Authorization: N/A — components receive decisions
- Route integrity: N/A — no route added or changed
- Interaction: the patterns' behaviour (TL-08)
- Browser E2E: component tests in the browser (TL-09)
- Localization/language switch: both languages in every component's text (TL-10)
- Visual breathing room/density: breathing room and density per DESIGN_SYSTEM (TEST_STRATEGY §9)
- Responsive: TL-11's bands
- Accessibility: WCAG 2.2 AA critical checks (TL-12)
- Production build: TL-13 — no test route shipped
- Security: the CSP holds with the components; no inline style (HO-37) (TL-14)
- Performance: bundle and first-load within PERFORMANCE §8 (TL-15)
- Migration: N/A — no migration or import code in this Task
- Other: the anti-slop audit of §18 on the component set

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

- TASK-016 — when its other dependencies are DONE and its READY gate holds

**DO NOT DO:**

- add to V1 — a capability, page, route, field, integration, document type, report or paid service — or drop, defer or narrow a V1_REQUIRED requirement; a Task that would need either raises an Owner question and waits ([DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-20)
- access the VPS, production, its data or a credential, or put a secret, real data, a host detail or production configuration in the repository ([INFRASTRUCTURE](../09-operations/INFRASTRUCTURE.md) INF-01, INF-05; SECURITY SX-01, SX-04, SX-06; [PRODUCTION_DATA_SAFETY](../00-governance/PRODUCTION_DATA_SAFETY.md))
- add AI, model or tool attribution to a commit — the Owner's configured identity only ([AGENTS.md](../../AGENTS.md#git-commit-attribution); DIR-022)
- start before the Owner's execution authorization at OG-4, or mark DONE without the required tests passing and the completion evidence ([TASK_PLAN](TASK_PLAN.md#2-rules-of-the-plan); [DEFINITION_OF_DONE](../08-testing/DEFINITION_OF_DONE.md#2-the-checklist))
- weaken, skip or delete a valid test to obtain a green run, or adopt a paid tier without the Owner's COST_POLICY decision (DOD-05; [COST_POLICY](../00-governance/COST_POLICY.md))
- loosen the CSP or add an external font, icon or script host
- give a primitive business logic or wording of its own

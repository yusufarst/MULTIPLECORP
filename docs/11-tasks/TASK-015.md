# TASK-015 — Design system — tokens, components, patterns and states

Status: APPROVED | Updated: 2026-10-20 | Owner: Planning

Approval: [APPR-016](../00-governance/DECISION_LOG.md#appr-016--p11-task-plan-tasks-and-feature-coverage-matrix-approved), explicit Owner approval on 2026-10-20 (DIR-085 OF-07) of this document as committed in the first execution session's approval commit; the approved file hash and the verified conditions are recorded there. Approval changes lifecycle only, not implementation authorization.

Written by the executor of run PC-5 on 2026-10-19 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99; the Owner approved P11 with the [Task plan](TASK_PLAN.md) at OG-4 (DIR-085 OF-07). **No Task starts before the freeze record, from which the execution authorization is in force (DIR-085 OF-09).** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** DONE

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
- TASK-022 — Jobs, files and the document renderer

**PARALLEL SAFE:** NO — every page Task composes these components

**IN SCOPE:**

- the tokens of §5 (colour, typography, spacing and density, radius, elevation, layers, motion with reduced motion) as the only values (SLOP-; DA-05)
- the layout, grid and bands of §6, the typography of §7, the icons and motion of §8 — Lucide pinned, Inter self-hosted from the application's origin (WS-08; DIR-059 GD-08)
- the shadcn/ui primitives copied into the codebase and kept generic (§10), and the feature components of §10 with the patterns PT-01–PT-31 of §9
- the status, outcome and state patterns of §12, keyboard and scanner of §13 (KB-), domain presentations of §14, accessibility of §15, copy and formatting of §16, print views of §17 and the anti-slop mapping of §18
- the interaction patterns of ADMIN_FLOW IP-01–IP-21 in their presentation — answers, CONFLICT, FAILED and retry, duplicates, confirmations, step-up, validation, background work, list continuation, search and lookup
- the P7 handoffs of CONCURRENCY_IDEMPOTENCY §19 those patterns answer: HO-03 — a disabled control a convenience only, the screen showing the recorded outcome —; HO-04's CONFLICT and stale handling, an intent already recorded shown as "already recorded: check before repeating" and never re-applied; HO-05's likely-duplicate confirmation as a second explicit submission naming the matches it acknowledges; HO-07's retry after FAILED with the same `command_id`; HO-08's explicit business date, prefilled from the server's day (WIB), its CONFLICT once no longer today (ST-08); and HO-10's list continuation without totals and its cut search
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
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (302): O-07; CAP-14; SH-02; AC-14; REF-052–REF-055; IMG-09; BR-DT-01–BR-DT-06; SF-DATE; L-09; L-14; INT-005; INT-007–INT-009; INT-011; IG-01–IG-05; C-50; WS-08–WS-09; TM-28; TM-37; TM-50; H7-02–H7-03; H7-06–H7-08; H7-10–H7-11; H8-20; HO-03–HO-05; HO-07–HO-08; HO-10; HO-37; PF-13–PF-18; PF-41–PF-42; UXI-01–UXI-03; UXI-11; UXI-13–UXI-14; UXI-21; UXI-23; UXI-25; UXI-28; UXI-30; UXI-36–UXI-39; UXI-42; D-UX-01–D-UX-02; D-UX-04–D-UX-11; IP-01; IP-07–IP-10; IP-13–IP-14; IP-17–IP-19; MSG-13; MSG-39–MSG-40; UXS-05–UXS-06; UXS-08; UXS-10; UXS-13–UXS-14; UXS-21; UXS-24; UXS-26; UXS-33; UXS-35; UXS-42; UXH-11; UXV-01–UXV-22; D-DS-01–D-DS-23; PT-01–PT-31; KB-01–KB-11; SLOP-01–SLOP-45; DA-03–DA-09; DA-11–DA-12; GR-01; GR-04–GR-09; GR-15–GR-16; GR-18–GR-21; component Shell — sidebar, *Menu lengkap*, icon rail, top bar, skip link; component Phone app bar and bottom bar; *Lainnya*; component Page header; component Button; component Split action; component Field and number entry; component Select and lookup; component Date field and business date; component Date range; component Code entry; component Checkbox, radio and switch; component Status pill and status text; component Company tag and count badge; component Card and section card; component KPI tile; component Facts, key-value and metric strip; component Tabs; component Segmented control; component Data table and row list; component Filter toolbar and company view; component States of a region; component System page; component Timeline; component Checklist; component Stage track and stage navigator; component Segmented stage indicator; component The now strip; component Dialog and confirmation; component Menu and "Tindakan Lain"; account menu; component Popover, tooltip and long text; component Search and scan; component Toast; component Notice and alert; component Checks, result, next step and prerequisite; component Disclosure; component Position and inventory blocks; component Multi-line editor; component Sticky action bar; component Progress bar, keycap, avatar, icon disc, link

**EXPECTED MODULES / FILE AREAS:**

- `resources/js/components/ui/` (primitives), `resources/js/components/` (feature components), the token files and Tailwind configuration, the self-hosted fonts and icons

**BUSINESS RULES:**

- N/A — components receive business decisions as data; none decides a capability, number, state or wording (DESIGN_SYSTEM §10)

**ARCHITECTURE RULES:**

- primitives generic, feature components composing them and receiving the server's decisions as data (§10); presentation only, behaviour from the canonical specifications (DIR-061 SD-02)

**SECURITY / PERMISSION REQUIREMENTS:**

- the threats of [SECURITY §8](../05-security/SECURITY.md#8-threat-model) mapped to this Task, each with the controls its row names: TM-28, Browser history, back-forward cache or client cache after logout, expiry or remote termination (AU-06, WS-07, WS-08); TM-37, Stored XSS rendered in React or a PDF (WS-09, FL-08); TM-50, Clickjacking (WS-08)
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
6. Switching the language on a page built from the components keeps unsent input in every field and holds the layout at TL-11's bands (LOCALIZATION §3.7; CJ-18 in TASK-064; architecture challenge 17b).

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

**CRITERIA DISPOSITIONS:**

- Criterion 1: `tests/Frontend/tokens.test.ts` reads §5 and holds `resources/css/tokens.css` to it — every ADOPT and ADAPT token present with §5's colour, no EXCLUDE token —; Tailwind's default namespaces are cleared, so a utility names only a token, and `multiplecorp/token-values` fails a class carrying its own value or a spacing step off §5.3's scale, with GR-04's per-surface greys, in TL-01. Criterion 2: PT-03–PT-31 built in `resources/js/components/` with their loading, empty, error and denied states (PT-19's region states and system page), checked by TL-12's axe runs at 390 and 1,280 px in both languages and with the dialog open, and by TL-11's bands; PT-01 and PT-02 — the shell and the phone navigation — are TASK-016's (OUT OF SCOPE). Criterion 3: IP-01–IP-21's presentations — the outcome faces and the action region, one `command_id` per intent, the business date, the confirmation and step-up, IP-13's entry and IP-14's validation, the lookup, the scan field's burst guard, the list continuation, the duplicate warning, the stale comparison, the reservation cut, the upload, several commands — with KB-04–KB-11, held by `tests/Frontend/entry.test.ts` and TL-09's `components.spec.ts` on Chromium and WebKit. Criterion 4: Base UI 1.9.0 (MIT) and its Toast chosen, recorded with every package's version and licence in TOOLCHAIN's row "Design system facilities (TASK-015)"; no paid component. Criterion 5: HO-37 answered with evidence — no `'unsafe-inline'` style: `CSPProvider` turns Base UI's style elements off, and TL-14's `components-csp.spec.ts` opens every overlay under the real header with no violation; WebKit's report of its native `<select>`'s internal styling is the engine's, reproduced by a bare `<select>` with no library, and the CSP is unchanged. Criterion 6: TL-10's `components.spec.ts` switches the language on the gallery with unsent input in every kind of field — text, number, money, lookup, select, textarea, code, checkbox, radio, switch, scan — and keeps it, and TL-11 holds the layout at every band in both languages; CJ-18 is TASK-064's

**TEST CHECKLIST:**

- [x] Lint: ESLint with the hardcoded-copy, GR-04 and new token-values rules, and Pint on the changed PHP, PASS locally and in CI (TL-01)
- [x] Typecheck: the TypeScript compiler over the app, the browser specs and the frontend tests PASS on the workstation and in CI (TL-02)
- [x] Relevant automated tests: the ESLint rules' fixtures (42) and `tests/Frontend/` (17 — §5's tokens, IP-13's entry, the provisional decimals, IP-02's intent, §13.2's burst) (TL-03); `KeyCatalogueTest` with the 90 added keys held to their registry (TL-03); `LocaleTest` (TL-04); PASS locally and in CI
- [x] Authorization: N/A — components receive the server's decisions as data; no route
- [x] Route integrity: N/A — no application route added or changed; the gallery is the `local` environment's fixture route only, never in testing, staging or production
- [x] Interaction behavior: the patterns' behaviour — IP-02, IP-13, IP-14, the confirmation's focus and KB-10, the step-up and re-authentication, the scan guard, the list keys and continuation, the line editor's KB-05–KB-08 — (TL-08 with TL-09)
- [x] Browser E2E if required: the components on the gallery on Chromium and WebKit (TL-09)
- [x] Responsive/adaptive if required: TL-11's band edges from 320 to 1,680 px in both languages — no page-level horizontal scroll, no clipped button, the table from 768 px and the rows below
- [x] Visual breathing room/density if UI: §5.3's density and spacing scale enforced by the token rule; English strings held at every band (TEST_STRATEGY §9)
- [x] Production build: TL-13 — the production build without `VITE_FIXTURE_PAGES` contains no fixture page — PASS on the workstation and in CI
- [x] Security checks if required: TL-14 and HO-37 — the CSP unchanged, no `'unsafe-inline'`, no violation from a component; no raw HTML (WS-09); fonts and icons from the application's origin (WS-08); the secret-like scan and both dependency audits PASS
- [x] Database safety review: no migration and no schema change; local and CI databases only, no production connection
- [x] Final diff review: each component against DESIGN_SYSTEM and ADMIN_FLOW, the anti-slop mapping of §18 kept — no card per section, no gradient, glow, illustration, KPI delta, progress percentage, avatar in the timeline or celebratory motion; STANDARD and outside ER-06's areas, no reviewer

**COMPLETION EVIDENCE:**

- Test outputs (TEST_STRATEGY §16), tested commit `63fbfec`: fresh CI on GitHub's `ubuntu-24.04` runner, runs 40214973032 (push) and 40214976186 (pull request #18), the three jobs PASS in each — "Verify" (every check of `scripts/verify.sh`, the ESLint rules and the frontend tests included), "Browser" (TL-09–TL-12 on Chromium and WebKit) and "Performance and migration" (TL-15, TL-16 from empty). The first run, on `aef790a`, failed one WebKit test: Alt+N reached the line editor before "Urungkan" had put focus back into the restored line; `63fbfec` binds KB-06 and KB-07 to the whole editor and the test waits for that focus. On the Owner's workstation, 2026-10-25: TypeScript, ESLint, the rules' fixtures 42, `tests/Frontend/` 17, Unit `tests/Unit/Localization/` 12, Feature `LocaleTest` 12, Routes and Architecture 61, Pint, the browser suite on Chromium and WebKit (61 PASS, one WebKit-only test skipped on Chromium), the production build and the repository gates PASS
- Build result: `TL-13 Frontend build: PASS — Evidence: 63fbfec, CI ubuntu-24.04 run 40214973032`; the production build without `VITE_FIXTURE_PAGES` has no fixture page; its application chunk 432.8 kB, 136.4 kB gzipped — PERFORMANCE §8 states no bundle budget
- Route evidence: N/A — no application route; the gallery's fixture route is the `local` environment's only
- Interaction evidence: N/A for INT- tests of this Task's own — the presentation side of INT-005, INT-007–INT-009 and INT-011 is held by TL-09's component specs; their INT- tests come with the pages that send those commands
- Browser evidence: `TL-09 E2E/browser: PASS — Evidence: 63fbfec, CI ubuntu-24.04 run 40214973032`; TL-10, TL-11 and TL-12 PASS in the same run
- Localization evidence: every component in both languages on the gallery, the switch keeping unsent input in every field (DOD-16); 90 keys added through `lang/registry/added-keys.json` for the Owner's review
- Cost evidence/exception if applicable: ADDITIONAL RECURRING COST CHANGE: NONE — the installed packages are free and open-source, nothing hosted
- Files changed: `resources/css/tokens.css`, `resources/css/app.css`; `resources/js/components/ui/`, `resources/js/components/`, `resources/js/lib/`, `resources/js/app.tsx`, `resources/js/Pages/Fixtures/`; `lang/id.json`, `lang/en.json`, `lang/registry/added-keys.json`; `eslint/`, `eslint.config.js`; `routes/fixtures.php`, `.env.example`, `package.json`, `package-lock.json`; `tests/Unit/Localization/KeyCatalogueTest.php`, `tests/Frontend/`, `tests/Browser/`; TOOLCHAIN's row; this file, TASK-016's status, TASK_PLAN, CURRENT_HANDOFF and CHANGELOG
- Commit SHA: `aef790a` and `63fbfec`, the Owner's identity, no signature and no AI attribution; this record's commit follows them on `task/TASK-015-design-system`
- Notes: criteria as CRITERIA DISPOSITIONS states. Environment: the Owner's Windows workstation, Claude Code Desktop, 2026-10-25 (TOOLCHAIN, "Execution environments (TASK-001)"); the session resumed after the workstation's unexpected restart with the uncommitted work intact; Context7 not available, each facility verified from the installed sources and by the tests (TOOLCHAIN's TASK-015 row); the local runs used a container-local copy of the working tree and two browser workers, CI the final proof. `aef790a` carried a stale CURRENT TASK and NEXT READY TASK in TASK_PLAN, corrected by this record. Readings within the approved text are listed in CURRENT_HANDOFF. The READY gates of the Tasks TASK-015 blocks were re-checked at this DONE: TASK-016 READY — TASK-011, TASK-012, TASK-013 and TASK-015 DONE —, TASK-017 waiting on TASK-016, TASK-022 on TASK-017. Each non-applicable item above carries its reason; TASK_PLAN, this file and CURRENT_HANDOFF updated for DONE

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

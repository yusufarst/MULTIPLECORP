# TASK-091 — Defect D4 — a popover opened inside a modal layer stacks above it

Status: APPROVED | Updated: 2026-11-11 | Owner: Planning

Approval: a Level 1 defect Task of [TASK_PLAN §7](TASK_PLAN.md#7-controlled-task-plan-changes) Change 18, created through the executor feedback loop from TASK-063's defect D4 within [APPR-016](../00-governance/DECISION_LOG.md#appr-016--p11-task-plan-tasks-and-feature-coverage-matrix-approved)'s plan; it changes no requirement, acceptance criterion, route or expected value. References are by identifier and link; where a line and its source differ, the source wins.

**STATUS:** IN_PROGRESS

**RISK CLASS:** STANDARD

**EXECUTOR:** AGENT

**OBJECTIVE:**

Make a popover — the business date's calendar first among them — opened from inside a modal layer (a dialog, an alert dialog, a sheet or a drawer) stack above that layer, reachable and fully visible, while a popover opened from the page keeps DESIGN_SYSTEM's layer below the modal, so that TASK-063's CJ-06 can backdate in an issue dialog.

**WHY THIS EXISTS:**

TASK-063's CJ-06, in fresh CI on `2bdc2a4`, found on Chromium and WebKit that "Ubah" in "Terbitkan invoice ini?" opens the calendar beneath the dialog — dimmed by its backdrop, its month controls unreachable —: every popover is portaled at `--z-popover` (80) and every modal layer at `--z-modal` (100) or `--z-sheet`/`--z-drawer`, so a popover from a modal is always under it. A regression of TASK-015's DONE components (DEFINITION_OF_DONE §1; Change 18).

**DEPENDS ON:**

- TASK-015 — Design system — tokens, components, patterns and states

**BLOCKS:**

- TASK-063 — Critical journeys B — finance, corrections, reports, documents, completion and the checklist

**PARALLEL SAFE:** NO — it changes the shared components every page composes

**IN SCOPE:**

- the popover's layer chosen by where it opens: from a modal layer, above that layer; from the page, `--z-popover` as DESIGN_SYSTEM's scale states — the scale's tokens unchanged
- every popover-based component that can open inside a modal layer (the date field's calendar first) taking the same rule
- a browser test opening the business date's calendar inside an issue dialog and choosing a day of the previous month, and TASK-063's CJ-06 backdating step

**OUT OF SCOPE:**

- the layer scale's values and order (DESIGN_SYSTEM §5); any page's content; native selects

**REFERENCES:**

- [DESIGN_SYSTEM](../07-ux-design/DESIGN_SYSTEM.md) §5 the layer scale (`--z-popover` 80 · `--z-sheet` 90 · `--z-drawer` 95 · `--z-modal` 100), §10 "Date field"; PT-25; IP-07
- [TASK-015](TASK-015.md); [TASK-063](TASK-063.md) — CJ-06

**EXPECTED MODULES / FILE AREAS:**

- `resources/js/components/ui/popover.tsx` and the modal layers' components (dialog, alert dialog, sheet, drawer) that give their children the layer; the components fixture page's spec

**BUSINESS RULES:**

- unchanged

**ARCHITECTURE RULES:**

- one shared rule in the components; no page sets a z-index of its own

**SECURITY / PERMISSION REQUIREMENTS:**

- unchanged — each page's own capabilities

**COMPANY SCOPE AND FIELD PROJECTION:**

- unchanged

**CONCURRENCY / IDEMPOTENCY:**

- N/A — no command changes

**AUDIT:**

- N/A — no audit event changes

**MCP SESSION PROFILE:**

- Required MCP servers: none — [TOOLCHAIN](../00-governance/TOOLCHAIN.md#mcp-servers) records none installed
- MCP servers that should remain OFF: every server not required
- Native CLI/package preferred over MCP: YES — the framework's and package managers' own commands
- Post-Task deactivation/cleanup: none

**AUTH IMPACT:**

- Authentication change: NONE
- Authentication implementation: LARAVEL_NATIVE — decided once ([EXECUTION_CONTEXT](EXECUTION_CONTEXT.md)), reused, never redesigned
- Auth profile: N/A — nothing changes
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

**DATABASE IMPACT ASSESSMENT:**

- Schema change: NONE
- Migration required: NO
- Existing data transformation: NONE
- Direct production DB access: FORBIDDEN
- Data-loss risk: NONE

**MODULARITY / EXTENSIBILITY:**

- Module boundary affected: the shared UI components
- Stable core kept provider-neutral: YES
- External/vendor-specific code isolated: YES
- Replaceable adapter/contract required: NO
- Provider lock-in risk: NONE
- Future extension impact: NONE
- Speculative abstraction avoided: YES

**TOOL REQUIREMENTS:**

- Context7: REQUIRED — Base UI's Popover portal and positioner inside a Dialog at the installed version
- Graphify: OPTIONAL
- Browser E2E: REQUIRED — TASK-063's journeys
- Other: none
- Missing safe tool behavior: AUTO-INSTALL project-locally (AICWDF §6.3); a paid tier STOPS for the Owner

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: N/A — the call sites are found by search
- Pre-change questions: which components open a popover and which open a modal layer
- Expected affected areas: the shared components

**UI / UX REQUIREMENTS:**

- Follow global design rules: YES
- Indonesian default: YES — the existing keys
- English support: YES — the existing keys in both languages
- Plain-language Indonesian: YES — the existing copy
- Language switch impact: NONE
- Mobile-first: YES — the phone's sheets take the same rule
- Responsive: YES — the existing compositions
- Adaptive: YES — the existing compositions
- Visual breathing room: YES — the existing tokens
- Comfortable density: YES — unchanged
- shadcn/ui reuse: YES — the existing components only
- Design reference: DESIGN_SYSTEM's own rows named in REFERENCES
- Primary action: unchanged
- Secondary actions: unchanged
- Destructive actions: unchanged
- Redundancy constraints: none added
- Loading state: unchanged
- Empty state: unchanged
- Error state: unchanged

**ROUTE IMPACT:**

- none

**INTERACTION CONTRACTS:**

- N/A — no INT- row changes

**NAVIGATION CONTRACT:**

- Parent: unchanged
- Entry: unchanged
- Back action: unchanged
- Cancel: unchanged
- Success destination: unchanged
- Error behavior: unchanged
- State preservation: unchanged
- Mobile behavior: unchanged

**ACCEPTANCE CRITERIA:**

1. A popover opened from inside a dialog, an alert dialog, a sheet or a drawer is shown above that layer and its controls are reachable; a popover opened from the page keeps `--z-popover` below the modal layers (DESIGN_SYSTEM §5).
2. Inside an issue dialog the business date's calendar moves to the previous month and its day is chosen, the dialog showing the "Tanggal Mundur" mark (PT-25; IP-07).
3. TASK-063's CJ-06 backdating step passes on Chromium and WebKit, its assertions unchanged.

**TEST REQUIREMENTS:**

- Lint: TL-01
- Typecheck: TL-02
- Unit: N/A — no domain logic
- Feature: N/A — no server change
- Integration: N/A — no database path changes
- Authorization: N/A — unchanged
- Route integrity: N/A — no route
- Interaction: the calendar inside an issue dialog (browser)
- Browser E2E: TL-09 — the calendar in a dialog and CJ-06 on Chromium and WebKit
- Localization/language switch: N/A — no new key
- Visual breathing room/density: N/A — existing tokens
- Responsive: N/A — compositions unchanged
- Accessibility: the calendar keeps its focus order and labels inside the dialog's focus trap
- Production build: TL-13
- Security: N/A
- Performance: N/A — no request added
- Migration: N/A
- Other: none

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
- Route evidence: N/A — no route is added or changed
- Interaction evidence: the browser test named in TEST REQUIREMENTS
- Browser evidence: `E2E: PASS — Evidence: …` on Chromium and WebKit
- Localization evidence: no new key unless named in IN SCOPE; existing keys in both languages
- Cost evidence/exception if applicable: ADDITIONAL RECURRING COST CHANGE: NONE (DOD-20)
- Files changed: the list, every one inside the IN SCOPE areas
- Commit SHA: the commit under the Owner's identity, with no AI attribution
- Notes: each non-applicable item with its reason (DOD-03); the DONE update of TASK_PLAN, this file and CURRENT_HANDOFF (AICWDF §22.3)

**SAFE NEXT TASK:**

- TASK-063 — its verification resumed once TASK-090, TASK-091 and TASK-092 are DONE

**DO NOT DO:**

- add to V1 — a capability, page, route, field, key, command or paid service — or change a requirement, an acceptance criterion or an expected value
- weaken, skip or delete a test, or change TASK-063's journey assertions, to obtain a green run (DOD-05; TS-06)
- change the database schema, touch the VPS, production or its data
- add AI, model or tool attribution to a commit — the Owner's configured identity only ([AGENTS.md](../../AGENTS.md#git-commit-attribution); DIR-022)
- mark DONE without the required tests passing in fresh CI and the completion evidence ([DEFINITION_OF_DONE](../08-testing/DEFINITION_OF_DONE.md#1-statuses-and-the-done-rule))

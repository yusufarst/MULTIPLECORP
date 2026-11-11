# TASK-092 — Defect D5 — the composer's paper fit without a resize-observer loop

Status: APPROVED | Updated: 2026-11-11 | Owner: Planning

Approval: a Level 1 defect Task of [TASK_PLAN §7](TASK_PLAN.md#7-controlled-task-plan-changes) Change 18, created through the executor feedback loop from TASK-063's defect D5 within [APPR-016](../00-governance/DECISION_LOG.md#appr-016--p11-task-plan-tasks-and-feature-coverage-matrix-approved)'s plan; it changes no requirement, acceptance criterion, route or expected value. References are by identifier and link; where a line and its source differ, the source wins.

**STATUS:** READY

**RISK CLASS:** STANDARD

**EXECUTOR:** AGENT

**OBJECTIVE:**

Fit the composer's paper to its frame without changing, inside the frame's own resize observer, the size it observes — no "ResizeObserver loop completed with undelivered notifications" page error on a width change —, the paper's scale and its scrolled extent unchanged, so that TASK-063's CJ-15 passes on WebKit.

**WHY THIS EXISTS:**

TASK-063's CJ-15, in fresh CI on `2bdc2a4` (WebKit), captured the page error "ResizeObserver loop completed with undelivered notifications." while the composer moved across its widths: `fit` in `resources/js/features/doc/composer.tsx` sets the scaled sheet's negative margins inside the observer of the frame those margins resize, a loop the browser defers and reports. A regression of TASK-029's DONE composer (DEFINITION_OF_DONE §1; Change 18).

**DEPENDS ON:**

- TASK-029 — Document engine — drafts, issue, revision, void, renditions and the composer

**BLOCKS:**

- TASK-063 — Critical journeys B — finance, corrections, reports, documents, completion and the checklist

**PARALLEL SAFE:** NO — it changes the composer TASK-063's CJ-15 walks

**IN SCOPE:**

- the paper fit applied outside the observer's delivery — the next animation frame, or only on a changed frame width —, its scale and margins as now (DESIGN_SYSTEM §14.3)
- a browser test resizing the composer across its bands with no page error, and TASK-063's CJ-15

**OUT OF SCOPE:**

- the composer's bands, preview, zoom and print (unchanged); the action bar's own observer unless it shows the same loop

**REFERENCES:**

- [DESIGN_SYSTEM](../07-ux-design/DESIGN_SYSTEM.md) §14.3 the composer; TEST_STRATEGY §8 — a critical console error fails a journey
- [TASK-029](TASK-029.md); [TASK-063](TASK-063.md) — CJ-15

**EXPECTED MODULES / FILE AREAS:**

- `resources/js/features/doc/composer.tsx`; the documents browser spec

**BUSINESS RULES:**

- unchanged

**ARCHITECTURE RULES:**

- the fit stays in the composer; no new dependency

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

- Module boundary affected: DOC's composer component
- Stable core kept provider-neutral: YES
- External/vendor-specific code isolated: YES
- Replaceable adapter/contract required: NO
- Provider lock-in risk: NONE
- Future extension impact: NONE
- Speculative abstraction avoided: YES

**TOOL REQUIREMENTS:**

- Context7: OPTIONAL — the ResizeObserver specification's loop rule
- Graphify: OPTIONAL
- Browser E2E: REQUIRED — TASK-063's journeys
- Other: none
- Missing safe tool behavior: AUTO-INSTALL project-locally (AICWDF §6.3); a paid tier STOPS for the Owner

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: N/A — the call sites are found by search
- Pre-change questions: which observers change what they observe
- Expected affected areas: the composer

**UI / UX REQUIREMENTS:**

- Follow global design rules: YES
- Indonesian default: YES — the existing keys
- English support: YES — the existing keys in both languages
- Plain-language Indonesian: YES — the existing copy
- Language switch impact: NONE
- Mobile-first: YES — the phone's preview unchanged
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

1. Resizing the composer across 1,280, 1,279, 1,025 and 1,024 px and the phone raises no page error and keeps the paper fitted to its frame (DESIGN_SYSTEM §14.3; TEST_STRATEGY §8).
2. TASK-063's CJ-15 passes on Chromium and WebKit, its assertions unchanged.

**TEST REQUIREMENTS:**

- Lint: TL-01
- Typecheck: TL-02
- Unit: N/A — no domain logic
- Feature: N/A — no server change
- Integration: N/A — no database path changes
- Authorization: N/A — unchanged
- Route integrity: N/A — no route
- Interaction: the composer resized with no page error (browser)
- Browser E2E: TL-09 — the composer across its widths and CJ-15 on Chromium and WebKit
- Localization/language switch: N/A — no new key
- Visual breathing room/density: N/A — existing tokens
- Responsive: N/A — compositions unchanged
- Accessibility: unchanged — the frame stays reachable by keyboard
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

# TASK-094 — Defect D7 — PG-014's inputs frozen while a save's outcome is unknown (IP-02; D-UX-02)

Status: APPROVED | Updated: 2026-11-12 | Owner: Planning

Approval: a Level 1 defect Task of [TASK_PLAN §7](TASK_PLAN.md#7-controlled-task-plan-changes) Change 19, created through the executor feedback loop from TASK-064's defect D7 within [APPR-016](../00-governance/DECISION_LOG.md#appr-016--p11-task-plan-tasks-and-feature-coverage-matrix-approved)'s plan; it changes no requirement, acceptance criterion, route or expected value. References are by identifier and link; where a line and its source differ, the source wins.

**STATUS:** READY

**RISK CLASS:** STANDARD

**EXECUTOR:** AGENT

**OBJECTIVE:**

Freeze PG-014's inputs while a save's outcome is unknown — the draft composer's fields and lines disabled until the answer is known or the same command is retried —, as IP-02 and D-UX-02 require and the other composers do, so that TASK-064's CJ-21 passes its test 1.

**WHY THIS EXISTS:**

TASK-064's CJ-21 test 1, in fresh CI on `f976270`, found on Chromium and WebKit that PG-014's inputs stay editable while a lost answer's outcome is unknown (`save.frozen` not applied in `Quotation.tsx`): an edit there would be lost or sent under the wrong intent. A regression of TASK-033's DONE page (Change 19).

**DEPENDS ON:**

- TASK-033 — Quotations

**BLOCKS:**

- TASK-064 — Critical journeys C — isolation, sign-in, access, search, approvals, import, language, masters, command identity and status

**PARALLEL SAFE:** NO — it changes PG-014, which TASK-064's CJ-21 walks

**IN SCOPE:**

- PG-014's draft composer's inputs and line editor disabled while the save's command is pending or its outcome unknown (`save.frozen`), as PG-042's composer does
- TASK-064's CJ-21 test 1

**OUT OF SCOPE:**

- the save command and its answers (unchanged); other pages

**REFERENCES:**

- IP-02 and D-UX-02 in [ADMIN_FLOW](../07-ux-design/ADMIN_FLOW/s05-command-model.md)
- [TASK-033](TASK-033.md); [TASK-064](TASK-064.md) — CJ-21

**EXPECTED MODULES / FILE AREAS:**

- `resources/js/Pages/PRJ/Quotation.tsx`

**BUSINESS RULES:**

- unchanged

**ARCHITECTURE RULES:**

- the existing command hook's `frozen` state, as the other composers use it

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

- Module boundary affected: PRJ's PG-014
- Stable core kept provider-neutral: YES
- External/vendor-specific code isolated: YES
- Replaceable adapter/contract required: NO
- Provider lock-in risk: NONE
- Future extension impact: NONE
- Speculative abstraction avoided: YES

**TOOL REQUIREMENTS:**

- Context7: OPTIONAL
- Graphify: OPTIONAL
- Browser E2E: REQUIRED — TASK-064's CJ-21
- Other: none
- Missing safe tool behavior: AUTO-INSTALL project-locally (AICWDF §6.3); a paid tier STOPS for the Owner

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: N/A — the call sites are found by search
- Pre-change questions: how PG-042 freezes its composer
- Expected affected areas: PG-014

**UI / UX REQUIREMENTS:**

- Follow global design rules: YES
- Indonesian default: YES — the existing keys
- English support: YES — the existing keys in both languages
- Plain-language Indonesian: YES — the existing copy
- Language switch impact: NONE
- Mobile-first: YES — unchanged
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

1. While a PG-014 save is pending or its outcome unknown, the draft's inputs and lines cannot be edited, and they are editable again once the outcome is known (IP-02; D-UX-02).
2. TASK-064's CJ-21 passes on Chromium and WebKit, its assertions unchanged.

**TEST REQUIREMENTS:**

- Lint: TL-01
- Typecheck: TL-02
- Unit: N/A
- Feature: N/A — no server change
- Integration: N/A — no database path changes
- Authorization: N/A — unchanged
- Route integrity: N/A — no route
- Interaction: CJ-21 test 1 (browser)
- Browser E2E: TL-09 — CJ-21 on Chromium and WebKit
- Localization/language switch: N/A — no new key
- Visual breathing room/density: N/A — existing tokens
- Responsive: N/A — compositions unchanged
- Accessibility: disabled inputs keep their labels
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

- TASK-064 — its verification resumed once TASK-093 and TASK-094 are DONE

**DO NOT DO:**

- add to V1 — a capability, page, route, field, key, command or paid service — or change a requirement, an acceptance criterion or an expected value
- weaken, skip or delete a test, or change TASK-064's journey assertions, to obtain a green run (DOD-05; TS-06)
- change the database schema, touch the VPS, production or its data
- add AI, model or tool attribution to a commit — the Owner's configured identity only ([AGENTS.md](../../AGENTS.md#git-commit-attribution); DIR-022)
- mark DONE without the required tests passing in fresh CI and the completion evidence ([DEFINITION_OF_DONE](../08-testing/DEFINITION_OF_DONE.md#1-statuses-and-the-done-rule))

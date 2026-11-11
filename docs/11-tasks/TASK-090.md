# TASK-090 — Defect D3 — the number's period on a backdated numbered command (UXS-14)

Status: APPROVED | Updated: 2026-11-11 | Owner: Planning

Approval: a Level 1 defect Task of [TASK_PLAN §7](TASK_PLAN.md#7-controlled-task-plan-changes) Change 18, created through the executor feedback loop from TASK-063's defect D3 within [APPR-016](../00-governance/DECISION_LOG.md#appr-016--p11-task-plan-tasks-and-feature-coverage-matrix-approved)'s plan; it changes no requirement, acceptance criterion, route or expected value. References are by identifier and link; where a line and its source differ, the source wins.

**STATUS:** IN_PROGRESS

**RISK CLASS:** STANDARD

**EXECUTOR:** AGENT

**OBJECTIVE:**

Show, on every form whose command draws a number on its business date, UXS-14's "Nomor mengikuti periode {periode}" beside the "Tanggal Mundur" mark when that date is chosen earlier than today — the period the number will be drawn from, by the type's numbering granularity —, so that TASK-063's CJ-06 passes its UXS-14 step.

**WHY THIS EXISTS:**

TASK-063's CJ-06 and the code it walks show that `BusinessDateControl` renders the period line only when given `periodOf`, and that no page passes it — only the components fixture does —: the backdated mark appears without the period the number follows, against UXS-14, PT-25 and IP-07. A regression of DONE behaviour is a new defect Task (DEFINITION_OF_DONE §1; Change 18).

**DEPENDS ON:**

- TASK-015 — Design system — tokens, components, patterns and states
- TASK-021 — Numbering, its settings page and the Owner's raise after a restore

**BLOCKS:**

- TASK-063 — Critical journeys B — finance, corrections, reports, documents, completion and the checklist

**PARALLEL SAFE:** NO — it changes the forms TASK-063's journeys walk

**IN SCOPE:**

- the period each numbered command's number follows given to its form: the numbering granularity of the sequence type the command draws — every DOC-01–DOC-14 issue through `DocumentIssuing`, a project's creation (PROJECT, `ProjectRecords`) and a purchase's record (PURCHASE, `PurchaseRecords`) — for the company the form is for, read as PG-061 reads it (TASK-021)
- each of those forms passing `periodOf` to its `BusinessDateControl`, so that a backdated date shows "Nomor mengikuti periode {periode}" — YEAR as `2026`, MONTH as `2026-11` (PG-061's own `periodOf`) —; a date of today shows neither the mark nor the line, as now
- a browser test of the line on a backdated issue, and TASK-063's CJ-06 UXS-14 step

**OUT OF SCOPE:**

- the number's draw, the period's rules and MSG-17 (TASK-021; unchanged); any command without a number; the calendar's layering (TASK-091)

**REFERENCES:**

- [UXS-14](../07-ux-design/ADMIN_FLOW/s14-15-messages-scenarios.md) — "Tanggal Mundur" · "Nomor mengikuti periode {periode}"; PT-25; IP-07; NM-05; BR-DT-03
- [TASK-015](TASK-015.md) — `BusinessDateControl`; [TASK-021](TASK-021.md) — the numbering's periods and PG-061; [TASK-063](TASK-063.md) — CJ-06

**EXPECTED MODULES / FILE AREAS:**

- the numbered commands' page queries and pages (DOC, FIN, PRJ, PUR, FUL as each issue form lives), a shared helper for the period from a granularity; the date-field component only if its contract needs a shared helper

**BUSINESS RULES:**

- unchanged — NM-05: the number comes from the date's period; the line states it before sending

**ARCHITECTURE RULES:**

- a page reads the granularity through ORG's numbering contract, never another module's tables; one period helper shared by the forms and PG-061

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

- Module boundary affected: the issuing forms' page props and their date controls
- Stable core kept provider-neutral: YES
- External/vendor-specific code isolated: YES
- Replaceable adapter/contract required: NO
- Provider lock-in risk: NONE
- Future extension impact: NONE
- Speculative abstraction avoided: YES

**TOOL REQUIREMENTS:**

- Context7: OPTIONAL — React props only
- Graphify: OPTIONAL
- Browser E2E: REQUIRED — TASK-063's journeys
- Other: none
- Missing safe tool behavior: AUTO-INSTALL project-locally (AICWDF §6.3); a paid tier STOPS for the Owner

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: N/A — the call sites are found by search
- Pre-change questions: which forms draw a number on their business date, and where each reads its company
- Expected affected areas: the issue dialogs and the project and purchase forms

**UI / UX REQUIREMENTS:**

- Follow global design rules: YES
- Indonesian default: YES — the existing keys
- English support: YES — the existing keys in both languages
- Plain-language Indonesian: YES — the existing copy
- Language switch impact: NONE
- Mobile-first: YES — the dialogs' phone compositions unchanged
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

1. Every form whose command draws a number on its business date shows "Nomor mengikuti periode {periode}" with the "Tanggal Mundur" mark when the chosen date is earlier than today, its period by the type's granularity, and neither when the date is today (UXS-14; PT-25; NM-05).
2. TASK-063's CJ-06 UXS-14 step passes on Chromium and WebKit, its assertion unchanged.

**TEST REQUIREMENTS:**

- Lint: TL-01
- Typecheck: TL-02
- Unit: the period helper for YEAR and MONTH
- Feature: each numbered form's page answer carries its type's granularity
- Integration: N/A — no database path changes
- Authorization: N/A — unchanged
- Route integrity: N/A — no route
- Interaction: a backdated issue dialog shows the line (browser)
- Browser E2E: TL-09 — the line on a backdated issue and CJ-06 on Chromium and WebKit
- Localization/language switch: N/A — no new key
- Visual breathing room/density: N/A — existing tokens
- Responsive: N/A — compositions unchanged
- Accessibility: the line is text beside the mark, read with the date
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

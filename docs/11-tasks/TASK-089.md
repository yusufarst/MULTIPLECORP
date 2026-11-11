# TASK-089 — Defect D2 — PG-011 within three deferred groups (PF-34, UXS-44)

Status: APPROVED | Updated: 2026-11-11 | Owner: Planning

Approval: a Level 1 defect Task of [TASK_PLAN §7](TASK_PLAN.md#7-controlled-task-plan-changes) Change 17, created through the executor feedback loop from TASK-062's defect D2 within [APPR-016](../00-governance/DECISION_LOG.md#appr-016--p11-task-plan-tasks-and-feature-coverage-matrix-approved)'s plan; it changes no requirement, acceptance criterion, route or expected value. References are by identifier and link; where a line and its source differ, the source wins.

**STATUS:** READY

**RISK CLASS:** STANDARD

**EXECUTOR:** AGENT

**OBJECTIVE:**

Bring PG-011, *Perjalanan Proyek*, back within three deferred groups — predicates, documents and *Riwayat* — by loading the cancelled project's record (TASK-056) with an existing group, so that UXS-44 holds and TASK-062's CJ-01 passes.

**WHY THIS EXISTS:**

TASK-062's CJ-01 UXS-44 test, in fresh CI on `dd851d3` (runs 42941402230 and 42941406273), found on Chromium and WebKit that PG-011 makes four deferred-group requests: TASK-056 added the cancelled record as a fourth group, where PF-34, UXN-14 and PG-011's row allow three. A regression after DONE is a new defect Task (DEFINITION_OF_DONE §1; Change 17).

**DEPENDS ON:**

- TASK-056 — Project cancellation

**BLOCKS:**

- TASK-062 — Critical journeys A — the golden flow, purchasing, fulfilment, counts, scanning and SIPLAH

**PARALLEL SAFE:** NO — it changes PG-011, which TASK-062's CJ-01 walks

**IN SCOPE:**

- ROUTE-011's props: the cancelled record (L-10) loaded in an existing deferred group of PG-011, the page keeping its three groups — predicates, documents, *Riwayat* — and showing the record as before
- a feature test of the page's deferred groups

**OUT OF SCOPE:**

- what the cancelled record shows, the cancellation command and its companions (TASK-056); any other page; any route, key or schema change

**REFERENCES:**

- [PERFORMANCE](../06-api-performance/PERFORMANCE.md) PF-34; [INFORMATION_ARCHITECTURE §8](../07-ux-design/INFORMATION_ARCHITECTURE.md#8-screen-inventory) UXN-14 and PG-011's row — three deferred groups, predicates, documents, *Riwayat* (QB-02)
- [UXS-44](../07-ux-design/ADMIN_FLOW/s14-15-messages-scenarios.md); [TASK-056](TASK-056.md); [TASK-062](TASK-062.md) — CJ-01's UXS-44

**EXPECTED MODULES / FILE AREAS:**

- `app/Modules/PRJ/Http/Controllers/ProjectsController.php`; PG-011's page where it reads the record; PRJ's feature tests

**BUSINESS RULES:**

- unchanged — the cancelled record and the facts that stay (L-10)

**ARCHITECTURE RULES:**

- QB-02: the page's own props read once; each deferred group one request

**SECURITY / PERMISSION REQUIREMENTS:**

- unchanged — the record's own read rules (TASK-056)

**COMPANY SCOPE AND FIELD PROJECTION:**

- unchanged

**CONCURRENCY / IDEMPOTENCY:**

- N/A — no command

**AUDIT:**

- N/A

**DATABASE IMPACT ASSESSMENT:**

- Schema change: NONE
- Migration required: NO
- Existing data transformation: NONE
- Direct production DB access: FORBIDDEN
- Data-loss risk: NONE

**MODULARITY / EXTENSIBILITY:**

- Module boundary affected: PRJ's controller only
- Stable core kept provider-neutral: YES
- External/vendor-specific code isolated: YES
- Replaceable adapter/contract required: NO
- Provider lock-in risk: NONE
- Future extension impact: NONE
- Speculative abstraction avoided: YES

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

**MCP SESSION PROFILE:**

- Required MCP servers: none — [TOOLCHAIN](../00-governance/TOOLCHAIN.md#mcp-servers) records none installed
- MCP servers that should remain OFF: every server not required
- Native CLI/package preferred over MCP: YES — the framework's and package managers' own commands
- Post-Task deactivation/cleanup: none

**TOOL REQUIREMENTS:**

- Context7: REQUIRED — Inertia's deferred-prop groups at the installed version
- Graphify: OPTIONAL
- Browser E2E: REQUIRED — TASK-062's CJ-01 UXS-44
- Other: none
- Missing safe tool behavior: AUTO-INSTALL project-locally (AICWDF §6.3); a paid tier STOPS for the Owner

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: N/A — one controller action
- Pre-change questions: which group the record joins without delaying the page's first answer
- Expected affected areas: PG-011 only

**UI / UX REQUIREMENTS:**

- Follow global design rules: YES
- Indonesian default: YES — the page's existing keys
- English support: YES — no new key; the existing keys in both languages
- Plain-language Indonesian: YES — the existing copy
- Language switch impact: NONE
- Mobile-first: YES — unchanged
- Responsive: YES — the page's existing composition
- Adaptive: YES — the page's existing composition
- Visual breathing room: YES — the existing tokens and components
- Comfortable density: YES — unchanged
- shadcn/ui reuse: YES — the existing components only
- Design reference: the sibling page that already meets the rule
- Primary action: unchanged
- Secondary actions: unchanged
- Destructive actions: unchanged
- Redundancy constraints: none added
- Loading state: the record loads with its group's loading state
- Empty state: unchanged
- Error state: the group's error state

**ROUTE IMPACT:**

- ROUTE-011's response: one deferred group fewer; its path, methods and props unchanged

**INTERACTION CONTRACTS:**

- N/A — no INT- row changes

**NAVIGATION CONTRACT:**

- Parent: unchanged (NAV-011)
- Entry: unchanged
- Back action: unchanged
- Cancel: unchanged
- Success destination: unchanged
- Error behavior: unchanged
- State preservation: unchanged
- Mobile behavior: unchanged

**ACCEPTANCE CRITERIA:**

1. PG-011 declares at most three deferred groups — predicates, documents, *Riwayat* — and a cancelled project still shows its record (PF-34; UXN-14; L-10).
2. TASK-062's CJ-01 UXS-44 test passes on Chromium and WebKit, its assertion unchanged.

**TEST REQUIREMENTS:**

- Lint: TL-01
- Typecheck: TL-02
- Unit: N/A
- Feature: ROUTE-011's deferred groups counted, the cancelled record present in its group
- Integration: N/A
- Authorization: N/A — unchanged
- Route integrity: N/A — the route unchanged
- Interaction: N/A
- Browser E2E: TL-09 — CJ-01's UXS-44 on Chromium and WebKit
- Localization/language switch: N/A
- Visual breathing room/density: N/A
- Responsive: N/A
- Accessibility: N/A
- Production build: TL-13
- Security: N/A
- Performance: PF-34 — three groups
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
- Localization evidence: no new key; the page's existing keys in both languages
- Cost evidence/exception if applicable: ADDITIONAL RECURRING COST CHANGE: NONE (DOD-20)
- Files changed: the list, every one inside the IN SCOPE areas
- Commit SHA: the commit under the Owner's identity, with no AI attribution
- Notes: each non-applicable item with its reason (DOD-03); the DONE update of TASK_PLAN, this file and CURRENT_HANDOFF (AICWDF §22.3)

**SAFE NEXT TASK:**

- TASK-062 — its verification resumed once TASK-088 and TASK-089 are DONE

**DO NOT DO:**

- add to V1 — a capability, page, route, field, key, command or paid service — or change a requirement, an acceptance criterion or an expected value
- weaken, skip or delete a test, or change TASK-062's journey assertions, to obtain a green run (DOD-05; TS-06)
- touch any other page's behaviour, the database schema, the VPS, production or its data
- add AI, model or tool attribution to a commit — the Owner's configured identity only ([AGENTS.md](../../AGENTS.md#git-commit-attribution); DIR-022)
- mark DONE without the required tests passing in fresh CI and the completion evidence ([DEFINITION_OF_DONE](../08-testing/DEFINITION_OF_DONE.md#1-statuses-and-the-done-rule))

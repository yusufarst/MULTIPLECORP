# TASK-088 — Defect D1 — PG-024's ambiguous-code candidates and "Cari Produk" (UXS-34)

Status: APPROVED | Updated: 2026-11-11 | Owner: Planning

Approval: a Level 1 defect Task of [TASK_PLAN §7](TASK_PLAN.md#7-controlled-task-plan-changes) Change 17, created through the executor feedback loop from TASK-062's defect D1 within [APPR-016](../00-governance/DECISION_LOG.md#appr-016--p11-task-plan-tasks-and-feature-coverage-matrix-approved)'s plan; it changes no requirement, acceptance criterion, route or expected value. References are by identifier and link; where a line and its source differ, the source wins.

**STATUS:** DONE

**RISK CLASS:** STANDARD

**EXECUTOR:** AGENT

**OBJECTIVE:**

Make PG-024, *Keluarkan Barang*, answer a scan that matches more than one row by listing the candidates for the user's choice, each resolved as a single match would be, and offer "Cari Produk" beside its scan field to find a line by name, SKU or description without a scanner — UXS-34's outcome for PG-022 and PG-024, which PG-022 already meets —, so that TASK-062's CJ-07 passes its criterion 5.

**WHY THIS EXISTS:**

TASK-062's CJ-07, in fresh CI on `dd851d3` (runs 42941402230 and 42941406273), found on the phone and the desktop and on Chromium and WebKit that PG-024 shows MSG-37 for an ambiguous code with no candidate to choose and no "Cari Produk": a regression of TASK-041's DONE behaviour against UXS-34 and IP-15. A regression after DONE is a new defect Task (DEFINITION_OF_DONE §1; Change 17).

**DEPENDS ON:**

- TASK-041 — Dispatch (Barang Keluar) and inter-company allocation

**BLOCKS:**

- TASK-062 — Critical journeys A — the golden flow, purchasing, fulfilment, counts, scanning and SIPLAH

**PARALLEL SAFE:** NO — it changes PG-024, which TASK-062's CJ-07 walks

**IN SCOPE:**

- PG-024's scan answer for an ambiguous code: MSG-37 with the candidates listed as choices; a chosen serial confirmed in its line, a chosen product opening its line, a product not on the project refused with the page's existing answer — the same resolution as a single match (UXS-34; IP-15; SF-SCAN)
- "Cari Produk" beside PG-024's scan field: a search field filtering the project's dispatch lines by name, SKU or description, closed again by the same control, as PG-022 offers it
- a browser test of both on PG-024, beside TASK-041's dispatch spec

**OUT OF SCOPE:**

- the scan lookup ROUTE-282 and SF-SCAN's matching (TASK-039); PG-022, which already meets UXS-34; any route, command, key, posting or schema change; TASK-062's journeys themselves

**REFERENCES:**

- [UXS-34](../07-ux-design/ADMIN_FLOW/s14-15-messages-scenarios.md) — candidates listed; the typed SKU and "Cari Produk"; MSG-37; IP-15
- [INFORMATION_ARCHITECTURE §8.3](../07-ux-design/INFORMATION_ARCHITECTURE.md#83-purchasing-and-warehouse) PG-022, PG-024; [DESIGN_SYSTEM §13.2](../07-ux-design/DESIGN_SYSTEM.md#132-scanner)
- [TASK-041](TASK-041.md) — the page's owner; [TASK-062](TASK-062.md) — CJ-07, criterion 5

**EXPECTED MODULES / FILE AREAS:**

- `resources/js/Pages/INV/DispatchGoods.tsx`; the dispatch browser spec and its fixtures; no backend code

**BUSINESS RULES:**

- the scan's resolution is unchanged — a candidate chosen is handled exactly as that row scanned alone; nothing is dispatched by choosing a candidate (WF-INV-03)

**ARCHITECTURE RULES:**

- the page reuses the scan client and the components PG-022 uses; no new shared abstraction

**SECURITY / PERMISSION REQUIREMENTS:**

- unchanged — PG-024's `stock.dispatch` and the lookup's own authorization (TASK-039)

**COMPANY SCOPE AND FIELD PROJECTION:**

- unchanged — the candidates come from the lookup's company-scoped answer

**CONCURRENCY / IDEMPOTENCY:**

- N/A — no command is added or changed

**AUDIT:**

- N/A — choosing a candidate records nothing

**DATABASE IMPACT ASSESSMENT:**

- Schema change: NONE
- Migration required: NO
- Existing data transformation: NONE
- Direct production DB access: FORBIDDEN
- Data-loss risk: NONE

**MODULARITY / EXTENSIBILITY:**

- Module boundary affected: INV's page only
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

- Context7: OPTIONAL — React state only
- Graphify: OPTIONAL
- Browser E2E: REQUIRED — the dispatch spec and TASK-062's CJ-07
- Other: none
- Missing safe tool behavior: AUTO-INSTALL project-locally (AICWDF §6.3); a paid tier STOPS for the Owner

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: N/A — one page
- Pre-change questions: what PG-022 does for the same scan answer
- Expected affected areas: PG-024 only

**UI / UX REQUIREMENTS:**

- Follow global design rules: YES
- Indonesian default: YES — the page's existing keys
- English support: YES — no new key; the existing keys in both languages
- Plain-language Indonesian: YES — the existing copy
- Language switch impact: NONE
- Mobile-first: YES — `[floor]`, the phone first
- Responsive: YES — the page's existing composition
- Adaptive: YES — the page's existing composition
- Visual breathing room: YES — the existing tokens and components
- Comfortable density: YES — unchanged
- shadcn/ui reuse: YES — the existing components only
- Design reference: the sibling page that already meets the rule
- Primary action: unchanged
- Secondary actions: "Cari Produk" beside the scan field
- Destructive actions: none
- Redundancy constraints: none added
- Loading state: unchanged
- Empty state: the filter's no-match state shows no line
- Error state: MSG-37 with its candidates; the existing refusals

**ROUTE IMPACT:**

- none

**INTERACTION CONTRACTS:**

- PG-024's scan field and its "Cari Produk" control as UXS-34 states them; no INT- row changes

**NAVIGATION CONTRACT:**

- Parent: unchanged (NAV-024)
- Entry: unchanged
- Back action: unchanged
- Cancel: unchanged
- Success destination: unchanged
- Error behavior: MSG-37 with its candidates
- State preservation: the chosen line stays current
- Mobile behavior: the candidates and the search field in the page's phone composition

**ACCEPTANCE CRITERIA:**

1. An ambiguous scan on PG-024 shows MSG-37 and lists each candidate as a choice; choosing one resolves it as a single match would — a serial confirmed in its line, a product's line opened, a product off the project refused (UXS-34; IP-15).
2. "Cari Produk" beside PG-024's scan field filters the project's lines by name, SKU or description and closes again (UXS-34).
3. TASK-062's CJ-07 passes on Chromium and WebKit at the phone and desktop widths, its assertions unchanged.

**TEST REQUIREMENTS:**

- Lint: TL-01
- Typecheck: TL-02
- Unit: N/A — no domain logic
- Feature: N/A — no route changes
- Integration: N/A — no database path changes
- Authorization: N/A — unchanged
- Route integrity: N/A — no route
- Interaction: the dispatch browser spec's ambiguous-scan and "Cari Produk" test
- Browser E2E: TL-09 — the dispatch spec and CJ-07 on Chromium and WebKit
- Localization/language switch: N/A — no new key
- Visual breathing room/density: N/A — existing components
- Responsive: N/A — the page's composition is unchanged
- Accessibility: the candidates as buttons with their names; the search field labelled
- Production build: TL-13
- Security: N/A
- Performance: N/A — no request added
- Migration: N/A
- Other: none

**TEST CHECKLIST:**

- [x] Lint: Pint and ESLint PASS in CI (TL-01) — the React Compiler's read-before-declaration error of `a9cf81d` fixed in `77c81e2`
- [x] Typecheck: PHPStan and TypeScript PASS in CI (TL-02)
- [x] Relevant automated tests: every PHP layer PASS in CI; the new browser test below
- [x] Authorization: N/A — unchanged; PG-024's `stock.dispatch` and the lookup's own authorization
- [x] Route integrity: N/A — no route added or changed; the route audit (TL-07) PASS in CI; the test fixture `__fixtures/dispatch/kembar` exists only in the fixtures' routes
- [x] Interaction behavior: `dispatch.spec.ts` — "lists an ambiguous code's candidates for the user's choice and finds a line with \"Cari Produk\"" PASS in CI on Chromium and WebKit
- [x] Browser E2E if required: `dispatch.spec.ts` (TL-09) PASS in CI on Chromium and WebKit; CJ-07 runs in TASK-062's verification on the merged code
- [x] Responsive/adaptive if required: TL-11 PASS in CI; the new test at 390 CSS px
- [x] Visual breathing room/density if UI: existing components and tokens only (TEST_STRATEGY §9)
- [x] Production build: TL-13 PASS in CI
- [x] Security checks if required: N/A — no new surface; the CI security layer (TL-14) PASS
- [x] Database safety review: no schema change, no migration, no write path; production not touched
- [x] Final diff review: Codex's review of `77c81e2` on pull request #75 completed with no finding (its state comment: has_any_findings false; 👍 on the request 6789520803); its one P1 on `a9cf81d`, naming a commit `a3fc752` that does not exist here, answered with the evidence in its thread

**COMPLETION EVIDENCE:**

- Test outputs (TEST_STRATEGY §16), tested commit `77c81e2`: fresh CI on GitHub's `ubuntu-24.04` runner — run 42970091717 (push) and run 42970101026 (pull request #75) every job PASS. The first head `a9cf81d` failed lint (`DispatchGoods.tsx:133:41`, `addSerial` read before its declaration) and one WebKit test in `dispatch.spec.ts` (run 42966079617). That test was diagnosed from its trace, not rerun: both selects resolved to one handle, `{ index: 1 }` having chosen the unit just dispatched from the line's pre-dispatch choices, which the reload then dropped. The test now waits for the settled choices, asserts the shipped unit is not offered, chooses by name and asserts the choice survives the evidence sheet. This record's head is proven by its own fresh CI before the merge
- Build result: TL-13 PASS in the same runs
- Route evidence: N/A — no route added or changed; the route audit PASS
- Interaction evidence: the new `dispatch.spec.ts` test — MSG-37 with two candidates, the off-project candidate refused, the project's own opening its line, "Cari Produk" filtering and closing
- Browser evidence: `E2E: PASS — Evidence: 77c81e2, CI ubuntu-24.04 run 42970091717, tests/Browser/E2E/dispatch.spec.ts on Chromium and WebKit`
- Localization evidence: no new key — MSG-37, `pt20.search` and the scan answers already in both languages; the localization layer PASS
- Cost evidence/exception if applicable: ADDITIONAL RECURRING COST CHANGE: NONE — no package added, no credit bought
- Files changed: `resources/js/Pages/INV/DispatchGoods.tsx`; `tests/Browser/E2E/dispatch.spec.ts`; `tests/Support/Fixtures/DispatchFixture.php` (`twin`); `routes/fixtures.php`; Change 17's documents — `docs/11-tasks/TASK_PLAN.md`, `TASK-041.md`, `TASK-056.md`, `TASK-062.md`, `TASK-088.md`, `TASK-089.md` —; `docs/handoff/CURRENT_HANDOFF.md`
- Commit SHA: `a9cf81d` and `77c81e2` on `task/TASK-088-dispatch-scan-candidates`, the Owner's identity, no AI attribution; this record's commit follows them, verified by the pull request head's CI
- Notes: criteria 1 and 2 PASS by the new test; criterion 3 is proven by TASK-062's CJ-07 on Chromium and WebKit at both widths once TASK-062's branch carries this fix and TASK-089's. Codex's P1 on PR #75 named a commit `a3fc752` authored by "Codex": no such commit exists in this repository (the API answers 422), every commit of the pull request has the Owner as author and committer, and the finding was answered in its thread with that evidence, no history rewritten. Observed for Planning, not changed here: SubstituteDialog offers the line's earlier serials until the post-dispatch choices arrive, so for a moment the unit just dispatched is selectable as the one "actually shipped" (TASK-041's dialog)
- Notes, DOD: each non-applicable item with its reason (DOD-03); the DONE update of TASK_PLAN, this file and CURRENT_HANDOFF (AICWDF §22.3)

**SAFE NEXT TASK:**

- TASK-062 — its verification resumed once TASK-088 and TASK-089 are DONE

**DO NOT DO:**

- add to V1 — a capability, page, route, field, key, command or paid service — or change a requirement, an acceptance criterion or an expected value
- weaken, skip or delete a test, or change TASK-062's journey assertions, to obtain a green run (DOD-05; TS-06)
- touch any other page's behaviour, the database schema, the VPS, production or its data
- add AI, model or tool attribution to a commit — the Owner's configured identity only ([AGENTS.md](../../AGENTS.md#git-commit-attribution); DIR-022)
- mark DONE without the required tests passing in fresh CI and the completion evidence ([DEFINITION_OF_DONE](../08-testing/DEFINITION_OF_DONE.md#1-statuses-and-the-done-rule))

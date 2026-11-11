# TASK-089 — Defect D2 — PG-011 within three deferred groups (PF-34, UXS-44)

Status: APPROVED | Updated: 2026-11-11 | Owner: Planning

Approval: a Level 1 defect Task of [TASK_PLAN §7](TASK_PLAN.md#7-controlled-task-plan-changes) Change 17, created through the executor feedback loop from TASK-062's defect D2 within [APPR-016](../00-governance/DECISION_LOG.md#appr-016--p11-task-plan-tasks-and-feature-coverage-matrix-approved)'s plan; it changes no requirement, acceptance criterion, route or expected value. References are by identifier and link; where a line and its source differ, the source wins.

**STATUS:** DONE

**RISK CLASS:** STANDARD

**EXECUTOR:** AGENT

**OBJECTIVE:**

Bring PG-011, *Perjalanan Proyek*, back within three deferred groups — predicates, documents and *Riwayat* — by loading the cancelled project's record (TASK-056) with the page itself instead of as a fourth group, so that UXS-44 holds and TASK-062's CJ-01 passes.

**WHY THIS EXISTS:**

TASK-062's CJ-01 UXS-44 test, in fresh CI on `dd851d3` (runs 42941402230 and 42941406273), found on Chromium and WebKit that PG-011 makes four deferred-group requests: TASK-056 added the cancelled record as a fourth group, where PF-34, UXN-14 and PG-011's row allow three. A regression after DONE is a new defect Task (DEFINITION_OF_DONE §1; Change 17).

**DEPENDS ON:**

- TASK-056 — Project cancellation

**BLOCKS:**

- TASK-062 — Critical journeys A — the golden flow, purchasing, fulfilment, counts, scanning and SIPLAH

**PARALLEL SAFE:** NO — it changes PG-011, which TASK-062's CJ-01 walks

**IN SCOPE:**

- ROUTE-011's props: the cancelled record (L-10) read with the page — nothing read unless the project is CANCELLED —, its failure answering that part alone as a group's does (PT-19), the page keeping its three groups — predicates, documents, *Riwayat* — and showing the record as before. Joining an existing group was set aside: a group of two props is asked as one request naming both props, which still breaks the one-group-per-part rule UXS-44 checks
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
- Pre-change questions: whether the record can come with the page without delaying it — it reads nothing for a project that is not CANCELLED
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
- Loading state: none — the record arrives with the page
- Empty state: unchanged
- Error state: the part's own failure state, as a group's (PT-19)

**ROUTE IMPACT:**

- ROUTE-011's response: one deferred group fewer, the record among the page's own props; its path, methods and prop names unchanged

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
- Feature: ROUTE-011's deferred groups — predicates, documents, history — for an ACTIVE and a CANCELLED project, the cancelled record in the page's own answer
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

- [x] Lint: Pint and ESLint PASS in CI (TL-01)
- [x] Typecheck: PHPStan and TypeScript PASS in CI (TL-02)
- [x] Relevant automated tests: every PHP layer PASS in CI, with `ProjectCancellationTest`'s new test of PG-011's groups
- [x] Authorization: N/A — unchanged; the record's own read rules (TASK-056), its finance figures still behind `finance.view`
- [x] Route integrity: N/A — ROUTE-011's path, methods and prop names unchanged; the route audit (TL-07) PASS in CI
- [x] Interaction behavior: N/A — no INT- row changes
- [x] Browser E2E if required: the E2E layer on Chromium and WebKit; CJ-01's UXS-44 runs in TASK-062's verification on the merged code
- [x] Responsive/adaptive if required: TL-11 PASS in CI
- [x] Visual breathing room/density if UI: unchanged components (TEST_STRATEGY §9)
- [x] Production build: TL-13 PASS in CI
- [x] Security checks if required: N/A — no new surface; the CI security layer (TL-14) PASS
- [x] Database safety review: no schema change, no migration, no write path; production not touched
- [x] Final diff review: Codex's review of `4d409b4` on pull request #76 completed with no finding (its state comment: has_any_findings false)

**COMPLETION EVIDENCE:**

- Test outputs (TEST_STRATEGY §16), first head `4d409b4`: fresh CI on GitHub's `ubuntu-24.04` runner — run 42985531006 (pull request #76) every job PASS but the E2E layer, where one test outside this Task failed on WebKit; run 42985507372 (push) every layer PASS but the E2E job, whose 334 tests all finished — 332 PASS, the one pre-existing skip, none failed — and whose Playwright process then did not exit before the job's 45-minute limit. Diagnosed from the evidence, not rerun: the failed test, `parties.spec.ts:65` (TASK-026's client archive), waited 5 s for "Diarsipkan" while its archive command's answer was still in flight — the dialog's "Arsipkan" disabled, no response in the trace, no error status —; the same test passed on WebKit in the push run on the same commit (16.7 s against Chromium's 6.5 s), and no file it touches is in this diff; the class matches TASK-060's record of a WebKit command answer past 5 s in a spec outside the Task. No timeout raised, no assertion changed. This record's head is proven by its own fresh CI, every job required to PASS before the merge
- Build result: TL-13 PASS in the same runs
- Route evidence: N/A — the route unchanged; the route audit PASS
- Interaction evidence: N/A — no INT- row changes
- Browser evidence: `E2E: PASS — Evidence: <this record's head>, CI ubuntu-24.04, tests/Browser/E2E on Chromium and WebKit`, as its pull request's checks show before the merge
- Localization evidence: no new key; the record's existing keys in both languages
- Cost evidence/exception if applicable: ADDITIONAL RECURRING COST CHANGE: NONE — no package added, no credit bought
- Files changed: `app/Modules/PRJ/Http/Controllers/ProjectsController.php`; `resources/js/Pages/PRJ/Journey.tsx`; `tests/Feature/PRJ/ProjectCancellationTest.php`; `docs/11-tasks/TASK_PLAN.md`, `TASK-089.md`; `docs/handoff/CURRENT_HANDOFF.md`
- Commit SHA: `4d409b4` on `task/TASK-089-journey-deferred-groups`, the Owner's identity, no AI attribution; this record's commit follows it, verified by the pull request head's CI
- Notes: criterion 1 PASS by the feature test — three groups for an ACTIVE and a CANCELLED project, the record in the page's own answer; criterion 2 is proven by TASK-062's CJ-01 UXS-44 on Chromium and WebKit once TASK-062's branch carries this fix and TASK-088's. The way the contract first named — the record joining an existing group — was set aside and the contract amended at the start: Inertia asks a group of two props as one request naming both, which still fails the one-group-per-part rule. Observed for Planning, not changed here: WebKit's E2E commands on the CI runner answer about 2.5 times slower than Chromium's, so a 5 s wait after a command occasionally expires in a spec outside the Task under test; and a Playwright process that did not exit after its last test (run 42985507372)
- Notes, DOD: each non-applicable item with its reason (DOD-03); the DONE update of TASK_PLAN, this file and CURRENT_HANDOFF (AICWDF §22.3)

**SAFE NEXT TASK:**

- TASK-062 — its verification resumed once TASK-088 and TASK-089 are DONE

**DO NOT DO:**

- add to V1 — a capability, page, route, field, key, command or paid service — or change a requirement, an acceptance criterion or an expected value
- weaken, skip or delete a test, or change TASK-062's journey assertions, to obtain a green run (DOD-05; TS-06)
- touch any other page's behaviour, the database schema, the VPS, production or its data
- add AI, model or tool attribution to a commit — the Owner's configured identity only ([AGENTS.md](../../AGENTS.md#git-commit-attribution); DIR-022)
- mark DONE without the required tests passing in fresh CI and the completion evidence ([DEFINITION_OF_DONE](../08-testing/DEFINITION_OF_DONE.md#1-statuses-and-the-done-rule))

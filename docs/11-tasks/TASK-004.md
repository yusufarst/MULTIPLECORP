# TASK-004 — Verification and release tooling check

Status: APPROVED | Updated: 2026-10-20 | Owner: Planning

Approval: [APPR-016](../00-governance/DECISION_LOG.md#appr-016--p11-task-plan-tasks-and-feature-coverage-matrix-approved), explicit Owner approval on 2026-10-20 (DIR-085 OF-07) of this document as committed in the first execution session's approval commit; the approved file hash and the verified conditions are recorded there. Approval changes lifecycle only, not implementation authorization.

Written by the executor of run PC-5 on 2026-10-19 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99; the Owner approved P11 with the [Task plan](TASK_PLAN.md) at OG-4 (DIR-085 OF-07). **No Task starts before the freeze record, from which the execution authorization is in force (DIR-085 OF-09).** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** DONE

**RISK CLASS:** STANDARD

**EXECUTOR:** AGENT

**OBJECTIVE:**

Verify, before any release work depends on them, the availability, version, licence and cost of P8's tooling and of the release tooling, and record TestSprite's entitlement exactly as DIR-083 OE-01 requires.

**WHY THIS EXISTS:**

PRQ-12 asks for the verification of the release tooling and of P8's tooling before the first release; OE-01 allows TestSprite only once a P11 Task has verified its entitlement at no cost or the Owner has approved a cost exception (DEP-07; REF-061).

**DEPENDS ON:**

- TASK-003 — CI pipeline, test layers and the synthetic fixture harness

**BLOCKS:**

- TASK-062 — Critical journeys A — the golden flow, purchasing, fulfilment, counts, scanning and SIPLAH
- TASK-063 — Critical journeys B — finance, corrections, reports, documents, completion and the checklist
- TASK-064 — Critical journeys C — isolation, sign-in, access, search, approvals, import, language, masters, command identity and status
- TASK-065 — UI/UX consistency gate, the responsive matrix and the anti-slop audit
- TASK-066 — Accessibility — WCAG 2.2 AA across every page
- TASK-067 — Localization completeness and the per-page copy lists
- TASK-068 — Performance targets and query budgets in CI
- TASK-069 — Security test matrix, route integrity and zero dead interactions
- TASK-073 — Release process — branch protection, release tags, the release record and integrity report

**PARALLEL SAFE:** NO — it writes TOOLCHAIN, which every Task reads

**IN SCOPE:**

- each tool of TEST_STRATEGY §17 and of the release path — Pest, Larastan, Pint, ESLint, Playwright, the accessibility engine, k6 or an equivalent, the secret scanner, the CI runner — verified for availability, version, licence and cost, and recorded in TOOLCHAIN (AICWDF §6.8)
- TestSprite: whether its use for the UI and E2E regression runs is available at no cost to this project; the finding recorded with its evidence
- if TestSprite is not available at no cost: the Owner told before the gate that needs it, with the COST_POLICY §4C.2 record for a decision, and Playwright's suite kept as the proof (OE-01)
- Context7 and Graphify's state recorded (AICWDF §5.1, §5.2)

**OUT OF SCOPE:**

- adopting any paid tier — the Owner's decision
- building the suites (TASK-062–TASK-070)

**REFERENCES:**

- [TEST_STRATEGY §17](../08-testing/TEST_STRATEGY.md#17-tooling)
- [TEST_STRATEGY §18](../08-testing/TEST_STRATEGY.md#p8q-01--the-browser-e2e-capability-and-testsprites-role) P8Q-01, decided by DIR-083 OE-01
- [RELEASE_PLAN §10](../10-release/RELEASE_PLAN.md#10-production-release-requirements-for-p11) PRQ-12
- [V1_SCOPE](../01-product/V1_SCOPE.md#dependencies-and-evidence-needed) DEP-07
- [COST_POLICY](../00-governance/COST_POLICY.md)
- [TOOLCHAIN](../00-governance/TOOLCHAIN.md)
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (6): DEP-07; REF-061; SX-05; TM-49; INF-04; PRQ-12

**EXPECTED MODULES / FILE AREAS:**

- `docs/00-governance/TOOLCHAIN.md` — the rows of the verified tools (TEST_STRATEGY §17; AICWDF §6.8)

**BUSINESS RULES:**

- N/A — no business rule

**ARCHITECTURE RULES:**

- N/A — no application code changes

**SECURITY / PERMISSION REQUIREMENTS:**

- the threats of [SECURITY §8](../05-security/SECURITY.md#8-threat-model) mapped to this Task, each with the controls its row names: TM-49, Vulnerable or malicious dependency (SX-05)
- no account, credential or token of a tool is written to the repository; an account the Owner creates for a tool stays the Owner's

**COMPANY SCOPE AND FIELD PROJECTION:**

- N/A — no data path

**CONCURRENCY / IDEMPOTENCY:**

- N/A — no command

**AUDIT:**

- N/A — no business action

**DATABASE IMPACT ASSESSMENT:**

- Schema change: NONE
- Migration required: NO
- Existing data transformation: NONE
- Direct production DB access: FORBIDDEN
- Data-loss risk: NONE KNOWN

**MODULARITY / EXTENSIBILITY:**

- Module boundary affected: none
- Stable core kept provider-neutral: YES
- External/vendor-specific code isolated: YES — framework facilities or a small interface (ARCHITECTURE §2)
- Replaceable adapter/contract required: NO
- Provider lock-in risk: NONE beyond the Owner's baseline stack
- Future extension impact: LOW
- Speculative abstraction avoided: YES — no repository class per model, no generic workflow or rules engine (ARCHITECTURE §1)

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

- New recurring cost: NONE unless the Owner approves an exception
- Paid third-party service: possibly TestSprite — never adopted without the Owner's COST_POLICY decision
- Free/open-source alternative: YES — Playwright for the deterministic suite
- Estimated additional monthly production cost: 0
- Owner approval required: YES — only if TestSprite needs a paid tier

**MCP SESSION PROFILE:**

- Required MCP servers: none — [TOOLCHAIN](../00-governance/TOOLCHAIN.md#mcp-servers) records none installed
- MCP servers that should remain OFF: every server not required
- Native CLI/package preferred over MCP: YES — the framework's and package managers' own commands
- Post-Task deactivation/cleanup: none

**TOOL REQUIREMENTS:**

- Context7: N/A — each tool's licence and price are read on its official licence and pricing pages and dated; Context7, which serves library documentation, is itself among the tools whose state this Task records (AICWDF §5.1)
- Graphify: N/A — no code analysis
- Browser E2E: REQUIRED — Playwright confirmed on both engines
- Other: TestSprite's free entitlement checked without creating a paid account
- Missing safe tool behavior: AUTO-INSTALL project-locally inside this Task (AICWDF §6.3) and record it in TOOLCHAIN; a paid tier or a privileged installation STOPS for the Owner (COST_POLICY)

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: N/A — not a code change
- Pre-change questions: N/A — not a code change
- Expected affected areas: TOOLCHAIN

**CONTEXT7:**

- Topics to verify: N/A — licences and prices are read from their official pages, dated
- Version-sensitive assumptions: every price and entitlement dated when read

**UI / UX REQUIREMENTS:**

- Follow global design rules: N/A — no page
- Indonesian default: N/A — no page
- English support: N/A — no page
- Plain-language Indonesian: N/A — no page
- Language switch impact: NONE
- Mobile-first: N/A — no page
- Responsive: N/A — no page
- Adaptive: N/A — no page
- Visual breathing room: N/A — no page
- Comfortable density: N/A — no page
- shadcn/ui reuse: N/A — no page
- Design reference: N/A — no page
- Primary action: N/A — no page
- Secondary actions: N/A — no page
- Destructive actions: N/A — no page
- Redundancy constraints: N/A — no page
- Loading state: N/A — no page
- Empty state: N/A — no page
- Error state: N/A — no page

**ROUTE IMPACT:**

- none

**INTERACTION CONTRACTS:**

- N/A — none

**NAVIGATION CONTRACT:**

- Parent: N/A — no page
- Entry: N/A — no page
- Back action: N/A — no page
- Cancel: N/A — no page
- Success destination: N/A — no page
- Error behavior: N/A — no page
- State preservation: N/A — no page
- Mobile behavior: N/A — no page

**ACCEPTANCE CRITERIA:**

1. Every tool of TEST_STRATEGY §17 has a TOOLCHAIN row with version, licence, cost and the date it was verified.
2. TestSprite's entitlement is recorded with its evidence; if it is not free, the Owner is told with the COST_POLICY record and nothing paid is adopted.
3. No tool needing a paid tier is installed.

**TEST REQUIREMENTS:**

- Lint: N/A — no code changes
- Typecheck: N/A — no code changes
- Unit: N/A — no domain logic in this Task
- Feature: N/A — no route or command in this Task
- Integration: N/A — no database, queue or job path in this Task
- Authorization: N/A — no route, query class or command in this Task
- Route integrity: N/A — no route added or changed
- Interaction: N/A — no INT- contract in this Task
- Browser E2E: Playwright's smoke run on Chromium and WebKit re-run as the proof of availability
- Localization/language switch: N/A — no user-facing copy in this Task
- Visual breathing room/density: N/A — no page in this Task
- Responsive: N/A — no page in this Task
- Accessibility: the accessibility engine run once inside the browser suite
- Production build: N/A — no code change
- Security: the dependency audit of each installed tool (TL-14)
- Performance: the load tool run once against the local stack
- Migration: N/A — no migration or import code in this Task
- Other: TOOLCHAIN's rows reviewed against the evidence

**TEST CHECKLIST:**

- [x] Lint: N/A — no application code; the new test and load files pass ESLint
- [x] Typecheck: N/A — no application code; the new spec passes the TypeScript compiler
- [x] Relevant automated tests: Playwright's smoke run on Chromium and WebKit, the accessibility engine's run and the load tool's run, PASS locally and in CI
- [x] Authorization: N/A — no route, query class or command
- [x] Route integrity: N/A — no route added or changed
- [x] Interaction behavior: N/A — no INT- contract
- [x] Browser E2E if required: Playwright's smoke run on Chromium and WebKit, PASS
- [x] Responsive/adaptive if required: N/A — no page
- [x] Visual breathing room/density if UI: N/A — no page
- [x] Production build: N/A — no code change; TL-13 still PASS in CI
- [x] Security checks if required: the dependency audits with the new packages, PASS in CI
- [x] Database safety review: N/A — no schema or data change
- [x] Final diff review: TOOLCHAIN's rows against their dated evidence, TestSprite's state with its evidence under the Owner's decision, and no paid tier adopted

**COMPLETION EVIDENCE:**

- Test outputs (TEST_STRATEGY §16), tested commit `a430f15160046181831192f741b21d9319edd5f3`: fresh CI on GitHub's `ubuntu-24.04` runner, run 39431436154 (push, `a430f15`) and run 39431448325 (pull request #4, its merge commit `15f2328`), the three jobs PASS in each. "Verify": every check of `scripts/verify.sh` PASS — the Composer and npm audits with the new packages, Larastan/PHPStan at level `max` and the production check among them —, TL-06–TL-08 EMPTY. "Browser": TL-09 PASS and TL-12 PASS — axe-core 4.14.0 finds no WCAG 2.2 AA violation on the stack's "not found" page on Chromium and WebKit, a proof of the engine's availability only (APPR-017) —, TL-10 and TL-11 EMPTY. "Performance and migration": `TL-15 Load tool smoke run (k6): PASS`, TL-15's PHP suite and TL-16 EMPTY. The first push `0a8c82e` passed the same but the load run, which the automated review found missing from CI and `a430f15` adds (runs 39430303553 and 39430316641). Locally, in the cloud session: the browser section PASS on both engines, k6 85,846 requests with none failed, ESLint, `tsc` and `npm audit` PASS; the Composer audit timed out reaching Packagist twice there, and Larastan/PHPStan and the production check are not run there (TASK-002, option B)
- Build result: N/A — no code change; the tools' own runs are the test outputs
- Route evidence: N/A — no route added or changed
- Interaction evidence: N/A — no INT- contract
- Browser evidence: `E2E: PASS — Evidence: a430f15, CI ubuntu-24.04 run 39431448325, docker compose run --rm e2e npx --no-install playwright test -c tests/Browser/playwright.config.ts tests/Browser/E2E, browser-evidence` — the proof of availability on both engines
- Localization evidence: N/A — no page
- Cost evidence/exception if applicable: ADDITIONAL RECURRING COST CHANGE: NONE — every installed tool open source at no cost; TestSprite not adopted, optional by the Owner's decision (DIR-087), its site refused in the cloud session (HTTP 403, 2026-10-20), no account and no plan
- Files changed: TOOLCHAIN, `package.json`, `package-lock.json`, `tests/Browser/Accessibility/`, `tests/Performance/load/smoke.js`, `docker-compose.yml`, `scripts/verify.sh`, `DEVELOPMENT.md`, this file, TASK_PLAN and CURRENT_HANDOFF; APPR-017's amendment — DEFINITION_OF_DONE, ACCEPTANCE_CRITERIA, DECISION_LOG, DECISION_INDEX, the eighty-fifth source record and `.gitattributes`; this record's commit adds this evidence, TASK_PLAN's DONE update, TASK-073's status, CURRENT_HANDOFF and CHANGELOG
- Commit SHA: `e7cc0bf`, `0a8c82e` and `a430f15160046181831192f741b21d9319edd5f3`, the Owner's identity, no signature and no AI attribution; this record's commit follows them on `task/TASK-004-tooling-check`
- Notes: N/A with its reason as the checklist states. TestSprite: the canonical DOD-14 and ACCEPTANCE_CRITERIA §8 required its flows; the conflict was recorded and the Owner approved the narrow amendment (APPR-017) — TestSprite where available at no cost, otherwise Playwright's suite and the other relevant tests, no required coverage reduced. GitHub's billing page and the vendors' sites are refused in the cloud session; licences come from the packages' metadata and licence files, read 2026-10-20. Context7 not available; Graphify not installed and not needed yet. The DONE update of TASK_PLAN, this file and CURRENT_HANDOFF is in this record's commit (AICWDF §22.3)

**SAFE NEXT TASK:**

- TASK-062 — when its other dependencies are DONE and its READY gate holds

**DO NOT DO:**

- add to V1 — a capability, page, route, field, integration, document type, report or paid service — or drop, defer or narrow a V1_REQUIRED requirement; a Task that would need either raises an Owner question and waits ([DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-20)
- access the VPS, production, its data or a credential, or put a secret, real data, a host detail or production configuration in the repository ([INFRASTRUCTURE](../09-operations/INFRASTRUCTURE.md) INF-01, INF-05; SECURITY SX-01, SX-04, SX-06; [PRODUCTION_DATA_SAFETY](../00-governance/PRODUCTION_DATA_SAFETY.md))
- add AI, model or tool attribution to a commit — the Owner's configured identity only ([AGENTS.md](../../AGENTS.md#git-commit-attribution); DIR-022)
- start before the Owner's execution authorization at OG-4, or mark DONE without the required tests passing and the completion evidence ([TASK_PLAN](TASK_PLAN.md#2-rules-of-the-plan); [DEFINITION_OF_DONE](../08-testing/DEFINITION_OF_DONE.md#2-the-checklist))
- weaken, skip or delete a valid test to obtain a green run, or adopt a paid tier without the Owner's COST_POLICY decision (DOD-05; [COST_POLICY](../00-governance/COST_POLICY.md))
- create a paid account, accept a paid plan or enter payment details
- record an entitlement without its dated evidence

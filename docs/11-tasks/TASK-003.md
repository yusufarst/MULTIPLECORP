# TASK-003 — CI pipeline, test layers and the synthetic fixture harness

Status: APPROVED | Updated: 2026-10-20 | Owner: Planning

Approval: [APPR-016](../00-governance/DECISION_LOG.md#appr-016--p11-task-plan-tasks-and-feature-coverage-matrix-approved), explicit Owner approval on 2026-10-20 (DIR-085 OF-07) of this document as committed in the first execution session's approval commit; the approved file hash and the verified conditions are recorded there. Approval changes lifecycle only, not implementation authorization.

Written by the executor of run PC-5 on 2026-10-19 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99; the Owner approved P11 with the [Task plan](TASK_PLAN.md) at OG-4 (DIR-085 OF-07). **No Task starts before the freeze record, from which the execution authorization is in force (DIR-085 OF-09).** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** DONE

**RISK CLASS:** STANDARD

**EXECUTOR:** AGENT

**OBJECTIVE:**

Run every deterministic check of TEST_STRATEGY in CI on each push and pull request, on the production-like stack, with the synthetic fixture harness and the one local verification command.

**WHY THIS EXISTS:**

Every Task's DONE needs its layers to pass with evidence ([TEST_STRATEGY §2](../08-testing/TEST_STRATEGY.md#2-test-layers); DOD-02, DOD-04); the numeric fixtures carry the binding examples (FX-; BUSINESS_RULES' numeric annex); SM-15 asks for CI gates.

**DEPENDS ON:**

- TASK-002 — Coding conventions and architecture guardrails

**BLOCKS:**

- TASK-004 — Verification and release tooling check
- TASK-005 — Schema foundations — IAM and OPS tables, history and types
- TASK-014 — Localization — locales, the language switch and the key catalogue
- TASK-072 — Deployment definition, health and smoke checks, maintenance mode and mixed-version safeguards
- TASK-073 — Release process — branch protection, release tags, the release record and integrity report

**PARALLEL SAFE:** NO — it changes the CI configuration every Task runs

**IN SCOPE:**

- TASK-001's minimal CI workflow, in the public repository at no cost, extended to every deterministic layer: TL-01–TL-08, TL-13, TL-14 and TL-16 on every push and pull request, the browser suite of TL-09–TL-12 on Chromium and WebKit (DIR-083 OE-02)
- PostgreSQL and Valkey services in CI matching INFRASTRUCTURE §3's production-like stack
- Pest on PHPUnit with its parallel database strategy; Playwright installed project-locally
- the synthetic fixture harness: factories and seeders with synthetic data only, the numeric fixtures FX-01–FX-10 as reusable datasets (TEST_STRATEGY §4)
- the CI gates of SM-15: lockfile-only installs, SHA-pinned actions, the secret-like scan, the dependency audits
- `scripts/verify.sh`, the one local verification command of TEST_STRATEGY §17

**OUT OF SCOPE:**

- the tooling verification and TestSprite's entitlement (TASK-004)
- staging and any deployment (TASK-072, TASK-078)
- the journeys' suites (TASK-062–TASK-064)

**REFERENCES:**

- [TEST_STRATEGY §1](../08-testing/TEST_STRATEGY.md#1-principles)
- [TEST_STRATEGY §2](../08-testing/TEST_STRATEGY.md#2-test-layers)
- [TEST_STRATEGY §3](../08-testing/TEST_STRATEGY.md#3-the-three-regression-layers)
- [TEST_STRATEGY §4](../08-testing/TEST_STRATEGY.md#4-environments-and-data)
- [TEST_STRATEGY §16](../08-testing/TEST_STRATEGY.md#16-evidence)
- [TEST_STRATEGY §17](../08-testing/TEST_STRATEGY.md#17-tooling)
- [SECURITY_TEST_MATRIX](../08-testing/SECURITY_TEST_MATRIX.md) SM-15
- [BUSINESS_RULES numeric annex](../02-domain/BUSINESS_RULES.md#numeric-annex-binding-examples-for-p8-fixtures-gap-004)
- [SECURITY §7](../05-security/SECURITY.md#7-secrets-production-boundary-and-supply-chain) SX-05, SX-06
- [INFRASTRUCTURE §3](../09-operations/INFRASTRUCTURE.md#3-environments)
- [TOOLCHAIN](../00-governance/TOOLCHAIN.md)
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (45): OS-06; REF-061; SX-06; TM-48; TS-01–TS-12; TL-01–TL-16; FX-01–FX-10; SM-15; INF-05; GAP-004

**EXPECTED MODULES / FILE AREAS:**

- the CI workflow files, `phpunit.xml`, the Pest configuration, `playwright.config.ts`, `database/factories/`, `tests/Fixtures/`, `scripts/verify.sh`

**BUSINESS RULES:**

- the numeric annex's binding examples become fixtures as written, never re-derived (GAP-004)

**ARCHITECTURE RULES:**

- tests sit by layer as TEST_STRATEGY §2 names them; no production code changes for a test except a seam ARCHITECTURE §18 allows

**SECURITY / PERMISSION REQUIREMENTS:**

- the threats of [SECURITY §8](../05-security/SECURITY.md#8-threat-model) mapped to this Task, each with the controls its row names: TM-48, Secrets in logs, repository or client bundles (LG-03, SX-01, SX-06)
- synthetic data only (SX-06); no secret in CI except GitHub's own token; third-party actions pinned to a SHA (SM-15)

**COMPANY SCOPE AND FIELD PROJECTION:**

- N/A — the harness holds data of synthetic companies only; company isolation is tested by the Tasks that build data paths

**CONCURRENCY / IDEMPOTENCY:**

- the harness supports the concurrent assertions TEST_STRATEGY §6 needs — two database connections and controlled interleaving — for the Tasks that use them

**AUDIT:**

- N/A — no business action

**DATABASE IMPACT ASSESSMENT:**

- Schema change: NONE — test databases only, created from the migrations of TASK-005–TASK-009 once they exist
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

- New recurring cost: NONE — the public repository's CI at no cost (TEST_STRATEGY §17)
- Paid third-party service: NO
- Free/open-source alternative: YES — Pest, Playwright and the public CI
- Estimated additional monthly production cost: 0
- Owner approval required: NO

**MCP SESSION PROFILE:**

- Required MCP servers: none — [TOOLCHAIN](../00-governance/TOOLCHAIN.md#mcp-servers) records none installed
- MCP servers that should remain OFF: every server not required
- Native CLI/package preferred over MCP: YES — the framework's and package managers' own commands
- Post-Task deactivation/cleanup: none

**TOOL REQUIREMENTS:**

- Context7: REQUIRED — Pest's parallel testing, Playwright's projects for Chromium and WebKit, the CI runner's service containers
- Graphify: N/A — the codebase is the skeleton
- Browser E2E: REQUIRED — Playwright installed and one smoke run on both engines
- Other: the CI runner, Pest, Playwright, the package managers' locked installs, a secret scanner, each verified and recorded in TOOLCHAIN
- Missing safe tool behavior: AUTO-INSTALL project-locally inside this Task (AICWDF §6.3) and record it in TOOLCHAIN; a paid tier or a privileged installation STOPS for the Owner (COST_POLICY)

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: N/A — the codebase is too young for a graph to add value; the impact is read from the diff and ARCHITECTURE §3–§4
- Pre-change questions: which layers each later Task will add to
- Expected affected areas: the IN SCOPE areas only

**CONTEXT7:**

- Topics to verify: Pest parallel and database refresh; Playwright's WebKit on the CI runner; service containers for PostgreSQL and Valkey
- Version-sensitive assumptions: none taken from memory

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

1. A push runs every listed layer in CI and fails on any failing check; the result is linked from the pull request.
2. The browser suite runs one smoke test on Chromium and on WebKit.
3. FX-01–FX-10 load as datasets and each binding example of the numeric annex is asserted where its Task lands.
4. The CI gates of SM-15 refuse an unlocked install, an unpinned action and a secret-like string.
5. `scripts/verify.sh` runs locally the deterministic layers an agent can run and reports each.

**TEST REQUIREMENTS:**

- Lint: TL-01 in CI
- Typecheck: TL-02 in CI
- Unit: the harness's own tests (TL-03)
- Feature: a sample feature test against the compose stack (TL-04)
- Integration: PostgreSQL and Valkey reached in CI (TL-05)
- Authorization: the job slot for TL-06, empty until TASK-011
- Route integrity: the job slot for TL-07, empty until TASK-011's route audit
- Interaction: the job slot for TL-08
- Browser E2E: a smoke run on Chromium and WebKit (TL-09)
- Localization/language switch: the job slot for TL-10
- Visual breathing room/density: N/A — no page
- Responsive: the job slot for TL-11
- Accessibility: the job slot for TL-12
- Production build: TL-13 in CI
- Security: SM-15's gates (TL-14)
- Performance: the job slot for TL-15
- Migration: the migrate-from-empty job of TL-16, empty until TASK-005
- Other: `scripts/verify.sh` on a clean checkout

**TEST CHECKLIST:**

- [x] Lint — Pint and ESLint, PASS
- [x] Typecheck — Larastan/PHPStan at level `max` (CI) and the TypeScript compiler, PASS
- [x] Relevant automated tests — the harness's unit tests, the fixture sets against their sources, a feature test against the stack, the integration tests with the race harness, PASS
- [x] Authorization — N/A: no route; TL-06's slot EMPTY until TASK-011
- [x] Route integrity — N/A: no route; TL-07's slot EMPTY until TASK-011
- [x] Interaction behavior — N/A: no control; TL-08's slot EMPTY until the first INT- contract
- [x] Browser E2E if required — the smoke run on Chromium and WebKit, PASS
- [x] Responsive/adaptive if required — N/A: no page; TL-11's slot EMPTY
- [x] Visual breathing room/density if UI — N/A: no page
- [x] Production build — TL-13 PASS
- [x] Security checks if required — SM-15's gates, each detection proved on its fixture, PASS
- [x] Database safety review — no schema or data; test databases only, and a database test refused outside its per-process test database
- [x] Final diff review — the project's review list answered on the diff (ER-05); no ER-06 area touched: no application code changes, the race harness being test code

**COMPLETION EVIDENCE:**

- Test outputs (TEST_STRATEGY §16), tested commit `0f3811359a5e8d377d4817e27a6e6d0d76e8720c`: fresh CI on GitHub's `ubuntu-24.04` runner, run 39423375614 (push, `0f38113`) and run 39423398059 (pull request #3, its merge commit `051871c3cd7317f1ba261b1a9e032802e4ca59e3`), the three jobs PASS in each. Job "Verify", `scripts/verify.sh`: the stack, the repository gates with their self-test, the commit-message check over `a802511..0f38113`, the plan integrity check, TL-01 Pint and ESLint, TL-02 Larastan/PHPStan at level `max` and the TypeScript compiler, TL-03 the unit and architecture suites and the project's ESLint rules, TL-04 the feature suite, TL-05 the integration suite — PostgreSQL and Valkey reached, the race harness —, TL-13 the Vite build and the production caches and configuration check, TL-14 the secret-like scan and both dependency audits, each with its detection proof: PASS; TL-06 Authorization and TL-07 Route integrity EMPTY until TASK-011, TL-08 Interaction EMPTY until the first Task with an INT- contract. Job "Browser": TL-09 PASS, the smoke run on Chromium and WebKit; TL-10–TL-12 EMPTY until their pages. Job "Performance and migration": TL-15 EMPTY until the first Task with a budget, TL-16 EMPTY until TASK-005. Each run keeps its logs and JUnit reports as the artifacts `verify-evidence` and `browser-evidence`. Locally, in the cloud session of 2026-10-20, `scripts/verify.sh` with PHP on the host against the stack's containers: every check PASS — 31 unit, 45 architecture, 18 feature and 8 integration tests in parallel, the race harness's tests alike in six runs — but Larastan/PHPStan and the production check, not run there (GitHub refuses the Larastan download; TASK-002, the Owner's option B); `scripts/verify.sh browser performance migration`: the smoke run's 4 tests PASS on Chromium and WebKit in the Playwright image, the other layers EMPTY
- Build result: TL-13 PASS in CI — the Vite build and the production check —, and the build locally
- Route evidence: N/A — no route is added or changed
- Interaction evidence: N/A — no INT- contract in this Task
- Browser evidence: `E2E: PASS — Evidence: 0f38113, CI ubuntu-24.04 run 39423398059, docker compose run --rm e2e npx --no-install playwright test -c tests/Browser/playwright.config.ts tests/Browser/E2E, browser-evidence` — the smoke run on Chromium and WebKit; the journeys are TASK-062–TASK-064's
- Localization evidence: N/A — no page
- Cost evidence/exception if applicable: ADDITIONAL RECURRING COST CHANGE: NONE — the public repository's CI at no cost; Playwright and its image free
- Files changed: the 49 files of `0f38113` — `scripts/verify.sh`, `scripts/ci/audit-dependencies.sh`, `scripts/ci/check-repository.sh`, `.github/workflows/ci.yml`, `phpunit.xml`, `tests/Pest.php`, `tests/TestCase.php`, `tests/Support/`, `tests/Fixtures/`, `tests/Unit/`, `tests/Feature/DatabaseIsolationTest.php`, `tests/Integration/ConcurrencyHarnessTest.php`, the empty layers' folders with their READMEs, `tests/Browser/`, `docker-compose.yml`, `package.json`, `package-lock.json`, `.gitignore`, `DEVELOPMENT.md`, TOOLCHAIN, this file, TASK_PLAN and CURRENT_HANDOFF —, each inside the IN SCOPE areas; this record's commit adds this evidence, TOOLCHAIN's CI result, TASK_PLAN's DONE update, the statuses of TASK-004, TASK-005 and TASK-014, CURRENT_HANDOFF and CHANGELOG; the commit after it, `scripts/ci/check-plan.mjs`'s self-test
- Commit SHA: `0f3811359a5e8d377d4817e27a6e6d0d76e8720c`, the Owner's identity, no signature and no AI attribution; this record's commit follows it on `task/TASK-003-ci-pipeline`
- Notes: N/A with its reason — Authorization, Route integrity, Interaction, Localization, visual, responsive and accessibility checks (no route and no page), Performance (no query), Migration (no migration): their layers' slots run in CI and report EMPTY, never PASS, until their Tasks write the first test. Factories and seeders arrive with their models and tables (TASK-005–TASK-009), on the harness's synthetic values; FX-02–FX-05 and FX-09 are data until those tables exist, and each binding example of FX-01 is asserted by the Task that lands its rule. Context7 is not available in the cloud session (TOOLCHAIN, "Current documentation"): Pest's parallel testing and its per-process databases, the framework's process concurrency driver and Playwright's browser builds were read from the installed packages' own sources, the Playwright image from the Microsoft Container Registry's tag index. This record's own CI run found that the plan integrity check's self-test (TASK-002) faked a disagreement by setting TASK-003's contract to DONE, which this record made true; the self-test now sets a status the contract does not hold, in the commit that follows this record. The automated review of pull request #3 named a commit `4f41d4e` carrying AI attribution that is not in the repository; the pull request carries only the Owner's commit, so nothing changed. The DONE update of TASK_PLAN, this file and CURRENT_HANDOFF is in this record's commit (AICWDF §22.3)

**SAFE NEXT TASK:**

- TASK-004 — when its other dependencies are DONE and its READY gate holds

**DO NOT DO:**

- add to V1 — a capability, page, route, field, integration, document type, report or paid service — or drop, defer or narrow a V1_REQUIRED requirement; a Task that would need either raises an Owner question and waits ([DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-20)
- access the VPS, production, its data or a credential, or put a secret, real data, a host detail or production configuration in the repository ([INFRASTRUCTURE](../09-operations/INFRASTRUCTURE.md) INF-01, INF-05; SECURITY SX-01, SX-04, SX-06; [PRODUCTION_DATA_SAFETY](../00-governance/PRODUCTION_DATA_SAFETY.md))
- add AI, model or tool attribution to a commit — the Owner's configured identity only ([AGENTS.md](../../AGENTS.md#git-commit-attribution); DIR-022)
- start before the Owner's execution authorization at OG-4, or mark DONE without the required tests passing and the completion evidence ([TASK_PLAN](TASK_PLAN.md#2-rules-of-the-plan); [DEFINITION_OF_DONE](../08-testing/DEFINITION_OF_DONE.md#2-the-checklist))
- weaken, skip or delete a valid test to obtain a green run, or adopt a paid tier without the Owner's COST_POLICY decision (DOD-05; [COST_POLICY](../00-governance/COST_POLICY.md))
- use real data or a real company's identity in a fixture
- mark a job green by skipping it

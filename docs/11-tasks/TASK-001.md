# TASK-001 — Repository scaffold and module skeleton

Status: APPROVED | Updated: 2026-10-20 | Owner: Planning

Approval: [APPR-016](../00-governance/DECISION_LOG.md#appr-016--p11-task-plan-tasks-and-feature-coverage-matrix-approved), explicit Owner approval on 2026-10-20 (DIR-085 OF-07) of this document as committed in the first execution session's approval commit; the approved file hash and the verified conditions are recorded there. Approval changes lifecycle only, not implementation authorization.

Written by the executor of run PC-5 on 2026-10-19 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99; the Owner approved P11 with the [Task plan](TASK_PLAN.md) at OG-4 (DIR-085 OF-07). **No Task starts before the freeze record, from which the execution authorization is in force (DIR-085 OF-09).** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** DONE

**RISK CLASS:** HIGH

**EXECUTOR:** AGENT

**OBJECTIVE:**

Create the application repository's runnable skeleton on the approved stack, organized in the layers and modules of ARCHITECTURE §2–§4, with local services on Docker Compose and no business feature, table or route.

**WHY THIS EXISTS:**

Every later Task builds on one stack decided once — Laravel, Inertia, React with TypeScript, shadcn/ui on Tailwind CSS, PostgreSQL, Nginx and Valkey, a modular monolith ([ARCHITECTURE §1](../04-architecture/ARCHITECTURE.md#1-baseline-and-constraints); [EXECUTION_CONTEXT](EXECUTION_CONTEXT.md)); the skeleton fixes the module and layer layout before code exists (CAP-16's readiness starts here).

**DEPENDS ON:**

- none — the plan's first Task; READY once the tools of its TOOL REQUIREMENTS are verified in the environment it runs in ([TASK_PLAN §2](TASK_PLAN.md#2-rules-of-the-plan); [record 84](../00-governance/sources/EXECUTION_AUTHORIZATION_2026-10-20.txt) §3.6)

**BLOCKS:**

- TASK-002 — Coding conventions and architecture guardrails

**PARALLEL SAFE:** NO — it creates the repository layout every Task uses

**IN SCOPE:**

- the Laravel application with Inertia and React on TypeScript and Vite, the versions verified and recorded in TOOLCHAIN — each runtime, library and tool, its supported version and its licence and commercial-use terms checked in its official documentation before it is adopted (DEP-07)
- Tailwind CSS and shadcn/ui initialized, no component added (TASK-015 adds them)
- Docker Compose for local development and CI with PostgreSQL and Valkey, and Nginx in front of the application as in production (INFRASTRUCTURE §3)
- the module namespaces of ARCHITECTURE §3 — IAM, ORG, PTY, CAT, PRJ, PUR, INV, CST, FUL, DOC, ADM, FIN, OPS, Reporting and the import orchestration — empty, each with a short README naming its tier and its DATABASE §4 tables
- `.env.example` with placeholder values only; the security headers of WS-08 with the framework's per-request CSP nonce and the CSRF middleware of WS-04
- the framework's default migrations, models, routes and welcome page that conflict with the contracts removed, so the schema belongs to TASK-005–TASK-009 alone
- `DEVELOPMENT.md`, a new file beside the approved README.md: how to start, test and build locally — README.md itself is not edited by this Task
- a minimal CI workflow in the public repository's CI at no cost (TEST_STRATEGY §17; DIR-083 OE-02), running this Task's own checks — TL-01, TL-02, TL-04, TL-05, TL-13 and TL-14 — on a fresh runner on every push and pull request, so that this Task's DONE has its fresh-CI half (DEFINITION_OF_DONE §1; TS-10); TASK-003 extends it to every layer

**OUT OF SCOPE:**

- any business table, model or migration (TASK-005–TASK-009)
- authentication, Fortify and Socialite (TASK-017–TASK-019)
- CI beyond the minimal workflow — the other layers, the browser suite, the fixture harness and the rest of SM-15's gates (TASK-003) — and coding guardrails (TASK-002)
- README.md, APPROVED under APPR-009: its sentence that application development has not started is recorded as an open item in CURRENT_HANDOFF when this Task is DONE, and corrected by Planning, its owner, as a Level 1 continuity update with its amendment line and DECISION_LOG record (CHANGE_CONTROL)
- the deployment definition and any production configuration (TASK-072)

**REFERENCES:**

- [ARCHITECTURE §1](../04-architecture/ARCHITECTURE.md#1-baseline-and-constraints)
- [ARCHITECTURE §2](../04-architecture/ARCHITECTURE.md#2-layers)
- [ARCHITECTURE §3](../04-architecture/ARCHITECTURE.md#3-modules)
- [ARCHITECTURE §4](../04-architecture/ARCHITECTURE.md#4-code-organization)
- [ARCHITECTURE §16](../04-architecture/ARCHITECTURE.md#16-configuration-environments-and-database-roles)
- [ARCHITECTURE §19](../04-architecture/ARCHITECTURE.md#19-prohibited-patterns)
- [EXECUTION_CONTEXT](EXECUTION_CONTEXT.md)
- [SECURITY §4](../05-security/SECURITY.md#4-web-and-input-security) WS-04, WS-08, WS-09
- [SECURITY §7](../05-security/SECURITY.md#7-secrets-production-boundary-and-supply-chain) SX-01, SX-05, SX-06
- [INFRASTRUCTURE §3](../09-operations/INFRASTRUCTURE.md#3-environments) — the environments this local stack mirrors
- [TOOLCHAIN](../00-governance/TOOLCHAIN.md)
- [DESIGN_SYSTEM §10](../07-ux-design/DESIGN_SYSTEM.md#10-component-composition) — shadcn/ui copied into the codebase
- [TEST_STRATEGY §17](../08-testing/TEST_STRATEGY.md#17-tooling) — the public repository's CI at no cost
- [DEFINITION_OF_DONE §1](../08-testing/DEFINITION_OF_DONE.md#1-statuses-and-the-done-rule) — DONE needs the required tests to pass locally and again in fresh CI
- [SECURITY_TEST_MATRIX](../08-testing/SECURITY_TEST_MATRIX.md) SM-15
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (21): O-07; CAP-16; DEP-07; REF-056; OWN-06; WS-04; WS-06; WS-08; SX-01; TM-08; TM-28; TM-48; TM-50; TM-65; H8-20; HO-37; RB-03; RB-10; TS-10; SM-08; SM-15

**EXPECTED MODULES / FILE AREAS:**

- the application root, `app/` with one namespace per module (ARCHITECTURE §4), `resources/js/` for React and TypeScript, `docker-compose.yml`, `.env.example`, `DEVELOPMENT.md`, the minimal CI workflow file

**BUSINESS RULES:**

- N/A — no business behaviour is built; the business rules start with the module Tasks

**ARCHITECTURE RULES:**

- one Laravel application, one PostgreSQL database, one repository and one deployable unit; layers presentation → application → domain → infrastructure, dependencies downward only (ARCHITECTURE §2)
- the dependency tiers of ARCHITECTURE §3, never a write cycle; Eloquent used directly, no repository class per model
- none of ARCHITECTURE §1's excluded patterns and none of §19's prohibited ones

**SECURITY / PERMISSION REQUIREMENTS:**

- the threats the skeleton answers first, each by the controls below: TM-08, a forged state change (WS-04, WS-05); TM-28, a page served from a cache after logout or expiry (WS-08's `no-store`); TM-50, clickjacking (WS-08's `frame-ancestors 'none'`); TM-48, a secret in the repository or a client bundle (SX-01, SX-06); TM-65 only so far as the application key stays outside the repository and the database (AU-21, built by TASK-018)
- HTTPS only (WS-06): the local Nginx redirects plain HTTP; HSTS is staged in production by the deployment definition, never here (TASK-072)
- HO-37: the CSP starts without `'unsafe-inline'`; whether a vetted UI primitive needs runtime styles is decided with the components in TASK-015 (WS-08); the CSP set here stays unchanged for Google sign-in — no Google script or frame on any page (H8-20, tested in TASK-019)
- no route exists yet: RB-03 — no GET changes state, every command one request with its `command_id` — and RB-10, the account's own locale route, bind the routes TASK-013, TASK-014 and TASK-016 add
- CSRF through the framework middleware (WS-04); the response headers and the CSP with a per-request nonce on every HTML and Inertia response (WS-08); React escaping, no raw HTML (WS-09)
- no secret in the repository: environment configuration outside it, `.env.example` with placeholders only (SX-01, SX-06); every dependency justified and from its official source (SX-05)
- the minimal CI workflow holds no secret but the CI's own token, pins its third-party actions to a SHA and installs from the lockfiles only (SM-15; SX-05, SX-06)

**COMPANY SCOPE AND FIELD PROJECTION:**

- N/A — no company-owned record exists yet; the scope resolver is TASK-011's

**CONCURRENCY / IDEMPOTENCY:**

- N/A — no command exists yet; the command envelope is TASK-013's

**AUDIT:**

- N/A — no business action exists yet; audit is TASK-010's

**DATABASE IMPACT ASSESSMENT:**

- Schema change: NONE — no business table; the framework's default migrations removed
- Migration required: NO
- Existing data transformation: NONE
- Direct production DB access: FORBIDDEN
- Data-loss risk: NONE KNOWN

**MODULARITY / EXTENSIBILITY:**

- Module boundary affected: every module namespace created empty, in its tier
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
- Free/open-source alternative: N/A — nothing paid is considered
- Estimated additional monthly production cost: 0
- Owner approval required: NO

**MCP SESSION PROFILE:**

- Required MCP servers: none — [TOOLCHAIN](../00-governance/TOOLCHAIN.md#mcp-servers) records none installed
- MCP servers that should remain OFF: every server not required
- Native CLI/package preferred over MCP: YES — the framework's and package managers' own commands
- Post-Task deactivation/cleanup: none

**TOOL REQUIREMENTS:**

- Context7: REQUIRED — the current official installation documentation of Laravel, Inertia, React, Vite, Tailwind CSS and shadcn/ui for the versions chosen
- Graphify: N/A — no code exists before this Task
- Browser E2E: N/A — no page exists after this Task
- Other: Docker Compose, PHP, Composer and Node.js on the development machine — a precondition of this Task's READY gate (AICWDF §22.8): TOOLCHAIN records Git and Node.js but not the others, so each is verified available before the Task starts, or its installation approved by the Owner where it is privileged, as installing Docker on the recorded workstation is; the CI runner of the minimal workflow; each verified and recorded in TOOLCHAIN (AICWDF §6.8)
- Missing safe tool behavior: AUTO-INSTALL project-locally inside this Task (AICWDF §6.3) and record it in TOOLCHAIN; a paid tier or a privileged installation STOPS for the Owner (COST_POLICY)

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: N/A — the repository is empty before this Task
- Pre-change questions: N/A — nothing exists to analyse before this Task
- Expected affected areas: the whole skeleton

**CONTEXT7:**

- Topics to verify: the current stable Laravel release and its PHP requirement; Inertia's React adapter; Vite with React and TypeScript; Tailwind CSS with shadcn/ui's initialization; the PostgreSQL and Valkey images
- Version-sensitive assumptions: every version pinned in the lockfiles and recorded in TOOLCHAIN; none assumed from memory

**UI / UX REQUIREMENTS:**

- Follow global design rules: N/A — no user-facing page is built; the welcome page is removed
- Indonesian default: N/A — no user-facing page is built; the welcome page is removed
- English support: N/A — no user-facing page is built; the welcome page is removed
- Plain-language Indonesian: N/A — no user-facing page is built; the welcome page is removed
- Language switch impact: NONE
- Mobile-first: N/A — no user-facing page is built; the welcome page is removed
- Responsive: N/A — no user-facing page is built; the welcome page is removed
- Adaptive: N/A — no user-facing page is built; the welcome page is removed
- Visual breathing room: N/A — no user-facing page is built; the welcome page is removed
- Comfortable density: N/A — no user-facing page is built; the welcome page is removed
- shadcn/ui reuse: N/A — no user-facing page is built; the welcome page is removed
- Design reference: N/A — no user-facing page is built; the welcome page is removed
- Primary action: N/A — no user-facing page is built; the welcome page is removed
- Secondary actions: N/A — no user-facing page is built; the welcome page is removed
- Destructive actions: N/A — no user-facing page is built; the welcome page is removed
- Redundancy constraints: N/A — no user-facing page is built; the welcome page is removed
- Loading state: N/A — no user-facing page is built; the welcome page is removed
- Empty state: N/A — no user-facing page is built; the welcome page is removed
- Error state: N/A — no user-facing page is built; the welcome page is removed

**ROUTE IMPACT:**

- none — no route is added; every route is a ROUTE_CONTRACTS row built by its own Task
- the framework's default routes and its welcome page removed

**INTERACTION CONTRACTS:**

- N/A — no interaction exists yet

**NAVIGATION CONTRACT:**

- Parent: N/A — no page is built
- Entry: N/A — no page is built
- Back action: N/A — no page is built
- Cancel: N/A — no page is built
- Success destination: N/A — no page is built
- Error behavior: N/A — no page is built
- State preservation: N/A — no page is built
- Mobile behavior: N/A — no page is built

**ACCEPTANCE CRITERIA:**

1. A fresh clone starts the local stack with the documented command and the application boots against PostgreSQL and Valkey.
2. The module namespaces of ARCHITECTURE §3 exist, each with its README naming its tier and its tables; no business code exists.
3. No route, migration or model of the framework's defaults remains that a contract does not name; the schema is left to TASK-005–TASK-009.
4. Every HTML response carries WS-08's headers with a nonce-based CSP, and a state-changing request without its CSRF token is refused (WS-04).
5. The repository holds no secret, real data or host detail; `.env.example` holds placeholders only.
6. The verified versions are recorded in TOOLCHAIN for each environment the Task runs in — the cloud session's machine or the Owner's workstation —, and every runtime, image and package version is pinned (record 84 §1.4; RELEASE_PLAN §12.2).
7. The minimal CI workflow runs TL-01, TL-02, TL-04, TL-05, TL-13 and TL-14 on a fresh runner on every push and pull request and fails on any failing check; its green run on the tested commit is the fresh-CI half of this Task's DONE (DEFINITION_OF_DONE §1).
8. `DEVELOPMENT.md` states how to start, test and build locally on Linux and on the Owner's Windows workstation, its commands running as written in the environment the Task runs in and re-run in the other when a session first runs there (record 84 §3.6, ER-13); text files keep LF line endings through `.gitattributes`; README.md is unchanged (record 84 §1.4; RELEASE_PLAN §12.2).

**TEST REQUIREMENTS:**

- Lint: the linters of TASK-002 run once they exist; here the framework's defaults pass (TL-01)
- Typecheck: the TypeScript compiler on the empty frontend (TL-02)
- Unit: N/A — no domain code
- Feature: a boot smoke test; WS-08's headers and the CSP nonce; the CSRF refusal (TL-04)
- Integration: PostgreSQL and Valkey reachable from the application in the compose stack (TL-05)
- Authorization: N/A — no route
- Route integrity: N/A — no route registered
- Interaction: N/A — no interaction
- Browser E2E: N/A — no page
- Localization/language switch: N/A — no copy; localization is TASK-014's
- Visual breathing room/density: N/A — no page
- Responsive: N/A — no page
- Accessibility: N/A — no page
- Production build: the frontend production build and the framework's production caches (TL-13)
- Security: composer audit and npm audit clean or each finding recorded; SM-08's cases the skeleton can show — CSRF refusing a state-changing request without a valid token, the cookie flags, the CSP nonce and directives, `frame-ancestors`, `nosniff`, Referrer-Policy and `APP_DEBUG=false` in the production configuration check —, its route cases with the Tasks that add routes; SM-15's gates the minimal CI workflow already has — SHA-pinned actions, lockfile-only installs and no secret but the CI's own token (TL-14, TL-09)
- Performance: N/A — no query
- Migration: N/A — no business migration; the framework defaults removed are listed in the evidence
- Other: `DEVELOPMENT.md`'s commands run as written in the environment the Task runs in, the environment recorded in the evidence (record 84 ER-13); no committed text file with CRLF line endings; the minimal CI workflow green on a fresh runner for TL-01, TL-02, TL-04, TL-05, TL-13 and TL-14

**TEST CHECKLIST:**

- [x] Lint — TL-01 Pint, PASS
- [x] Typecheck — TL-02 the TypeScript compiler, PASS
- [x] Relevant automated tests — TL-04 17 feature tests and TL-05 4 integration tests, PASS
- [x] Authorization — N/A: no route
- [x] Route integrity — N/A: no route registered; the route list asserted empty
- [x] Interaction behavior — N/A: no interaction
- [x] Browser E2E if required — N/A: no page
- [x] Responsive/adaptive if required — N/A: no page
- [x] Visual breathing room/density if UI — N/A: no page
- [x] Production build — TL-13, PASS
- [x] Security checks if required — TL-14 and SM-08's skeleton cases, PASS
- [x] Database safety review — no schema, migration or data; the framework's default migrations removed
- [x] Final diff review — the project's review list answered on the diff (ER-05); no ER-06 area touched

**COMPLETION EVIDENCE:**

- Test outputs (TEST_STRATEGY §16), tested commit `6a275b9a60f65f074a763ff384254443fcfa313d`: fresh CI on GitHub's `ubuntu-24.04` runner — Docker Engine 28.0.4, Compose v2.38.2 —, run 39406400423 (push, `6a275b9`) and run 39406464158 (pull request #1, its merge commit `727b49b3dcf95bbcebd672312c2ee6d4ff81d897`), every step PASS: the application image's first real build — PHP 8.5.11 with `pdo_pgsql` compiled and checked by `php -m` —, the lockfile installs (125 PHP and 50 JavaScript packages), TL-01 Pint PASS, TL-02 `tsc --noEmit` PASS, TL-04 17 tests and 43 assertions PASS, TL-05 4 tests and 20 assertions PASS — PostgreSQL 18.6, Valkey 9.1.2, every WS-08 header once through Nginx, the redirect of plain HTTP —, TL-14 `composer audit` (no advisory) and `npm audit` (0 vulnerabilities) PASS, the repository gates and their fixtures PASS — actions pinned by SHA, no secret but the CI's token, lockfile-only installs, no CRLF text file. Locally, in the cloud session of 2026-10-20 (Ubuntu 24.04.5, PHP 8.3.6, Composer 2.8.12, Node.js 24.22.0 in its image, Docker Engine 29.9.1, Compose v5.6.0), the same layers PASS, TL-05 with the official `php:8.4.26` images standing in for the application image, which the session's network policy (`dl-cdn.alpinelinux.org` refused) cannot build — a stand-in run, not evidence of the image's build, which the CI runs above give
- Build result: TL-13 PASS in CI — the Vite 8.3.4 production build, and `scripts/ci/production-check.sh`: Composer's authoritative class map without the development packages, the framework's production caches, then debug off, the configuration cached, no test route and no development dependency; its negative case (debug on) detected locally
- Route evidence: N/A — no route is added; the framework's `/up`, the local disk's file route and Inertia DevTools' routes removed, the route list asserted empty (`BootTest`)
- Interaction evidence: N/A — no interaction exists
- Browser evidence: N/A — no page exists
- Localization evidence: N/A — no copy and no page; localization is TASK-014's
- Cost evidence/exception if applicable: ADDITIONAL RECURRING COST CHANGE: NONE — the public repository's CI at no cost
- Files changed: the 88 files of `6a275b9` — the application skeleton at the repository root (`app/` with the module namespaces of `app/Modules/`, `bootstrap/`, `config/`, `database/`, `public/`, `resources/`, `routes/`, `storage/`, `tests/`), `docker-compose.yml` and `docker/`, `.env.example`, `DEVELOPMENT.md`, `.github/workflows/ci.yml` and `scripts/ci/`, the lockfiles, `.gitattributes`, `.gitignore`, `.editorconfig`, `.npmrc` and `.dockerignore`; TOOLCHAIN, this file, TASK_PLAN and CURRENT_HANDOFF —, each inside the IN SCOPE areas; this record's commit adds TOOLCHAIN's CI row values, this evidence, TASK_PLAN's DONE update, TASK-002's status, CURRENT_HANDOFF and CHANGELOG
- Commit SHA: `6a275b9a60f65f074a763ff384254443fcfa313d`, the Owner's identity, no signature and no AI attribution; this record's commit follows it on `task/TASK-001-scaffold`
- Notes: the framework defaults removed — the migrations `0001_01_01_000000_create_users_table`, `0001_01_01_000001_create_cache_table` and `0001_01_01_000002_create_jobs_table`, the `User` model, `UserFactory`, `DatabaseSeeder`, `welcome.blade.php`, `routes/console.php`, the `/up` route, the local disk's file route (`serve` off) and Inertia DevTools (off by default), and the skeleton's `laravel/tinker`, `laravel/pail`, `laravel/pao` and `fakerphp/faker`. N/A with its reason: Unit (no domain code), Authorization and Route integrity (no route), Interaction, Browser E2E, Localization, visual, responsive and accessibility checks (no page), Performance (no query), Migration (no migration). `DEVELOPMENT.md`'s commands: in the cloud session each ran as written but `docker compose build app` and the commands that run in the application image, which the network policy blocks there — `npm` there with the session's proxy certificate authority —; the CI runner runs them in the same order and built the image; the Owner's Windows workstation re-runs them when a session first runs there (ER-13). An automated reviewer's two findings on pull request #1 do not reproduce: the image lacks no extension the lockfile needs (CI installed and ran all 125 packages in it), and a request reaches no session middleware while no route exists (CI's request through Nginx answered 404 with the `database` driver set; the session table is TASK-005's, before any route). Open items: README.md's sentence that application development has not started, for Planning (OUT OF SCOPE); the framework's default error pages render unstyled under the CSP until later Tasks design them. The DONE update of TASK_PLAN, this file and CURRENT_HANDOFF is in this record's commit (AICWDF §22.3)

**SAFE NEXT TASK:**

- TASK-002 — when its other dependencies are DONE and its READY gate holds

**DO NOT DO:**

- add to V1 — a capability, page, route, field, integration, document type, report or paid service — or drop, defer or narrow a V1_REQUIRED requirement; a Task that would need either raises an Owner question and waits ([DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-20)
- access the VPS, production, its data or a credential, or put a secret, real data, a host detail or production configuration in the repository ([INFRASTRUCTURE](../09-operations/INFRASTRUCTURE.md) INF-01, INF-05; SECURITY SX-01, SX-04, SX-06; [PRODUCTION_DATA_SAFETY](../00-governance/PRODUCTION_DATA_SAFETY.md))
- add AI, model or tool attribution to a commit — the Owner's configured identity only ([AGENTS.md](../../AGENTS.md#git-commit-attribution); DIR-022)
- start before the Owner's execution authorization at OG-4, or mark DONE without the required tests passing and the completion evidence ([TASK_PLAN](TASK_PLAN.md#2-rules-of-the-plan); [DEFINITION_OF_DONE](../08-testing/DEFINITION_OF_DONE.md#2-the-checklist))
- weaken, skip or delete a valid test to obtain a green run, or adopt a paid tier without the Owner's COST_POLICY decision (DOD-05; [COST_POLICY](../00-governance/COST_POLICY.md))
- add a route, page, table, package or service the contracts do not name, or a placeholder page that would ship
- install a package beyond the stack of ARCHITECTURE §1 without the justification SX-05 asks

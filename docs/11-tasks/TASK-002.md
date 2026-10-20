# TASK-002 — Coding conventions and architecture guardrails

Status: APPROVED | Updated: 2026-10-20 | Owner: Planning

Approval: [APPR-016](../00-governance/DECISION_LOG.md#appr-016--p11-task-plan-tasks-and-feature-coverage-matrix-approved), explicit Owner approval on 2026-10-20 (DIR-085 OF-07) of this document as committed in the first execution session's approval commit; the approved file hash and the verified conditions are recorded there. Approval changes lifecycle only, not implementation authorization.

Written by the executor of run PC-5 on 2026-10-19 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99; the Owner approved P11 with the [Task plan](TASK_PLAN.md) at OG-4 (DIR-085 OF-07). **No Task starts before the freeze record, from which the execution authorization is in force (DIR-085 OF-09).** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** TODO

**RISK CLASS:** STANDARD

**EXECUTOR:** AGENT

**OBJECTIVE:**

Make the architecture enforceable before features exist: static analysis, formatting, the architecture tests of the layers and tiers, the commit rule and the coding conventions every Task follows.

**WHY THIS EXISTS:**

Layer and tier violations, prohibited patterns and AI attribution are cheapest to stop at the first commit; SM-03 asks for architecture tests and the brief's static tools are named in TEST_STRATEGY §17.

**DEPENDS ON:**

- TASK-001 — Repository scaffold and module skeleton

**BLOCKS:**

- TASK-003 — CI pipeline, test layers and the synthetic fixture harness

**PARALLEL SAFE:** NO — it changes the shared configuration every later Task runs

**IN SCOPE:**

- Larastan/PHPStan at a strict level, Laravel Pint, ESLint and the TypeScript compiler in strict mode, each with its configuration (TEST_STRATEGY §17)
- architecture tests: presentation → application → domain → infrastructure only; the module tiers of ARCHITECTURE §3 with no write cycle; ARCHITECTURE §19's prohibited patterns; controllers thin (SM-03)
- the per-surface grey lint rule of DESIGN_SYSTEM Annex B GR-04 as a frontend lint rule, applied once TASK-015 adds the tokens
- the coding conventions as a section of `DEVELOPMENT.md` (TASK-001): naming, one action per business command, money and quantity types (EXECUTION_CONTEXT invariant 1), English identifiers (AICWDF §4B.3)
- a commit-message check that refuses AI, model or tool attribution trailers (AGENTS.md; DIR-022)
- the N+1 prevention and loading rules of PERFORMANCE §3 (PF-01–PF-12) as conventions with their guard — lazy loading refused outside production, eager loading declared per query class
- this Task's checks — the static tools, the architecture tests, the commit-message check and the secret-like scan — added as jobs of TASK-001's minimal CI workflow, so that they pass locally and again in fresh CI (DEFINITION_OF_DONE §1); TASK-003 extends the workflow to every layer

**OUT OF SCOPE:**

- CI beyond this Task's jobs — the other layers, the browser suite and SM-15's gates (TASK-003)
- any feature code
- the query budgets themselves (QB-; TASK-068 and each page's Task)

**REFERENCES:**

- [ARCHITECTURE §2](../04-architecture/ARCHITECTURE.md#2-layers)
- [ARCHITECTURE §3](../04-architecture/ARCHITECTURE.md#3-modules)
- [ARCHITECTURE §19](../04-architecture/ARCHITECTURE.md#19-prohibited-patterns)
- [TEST_STRATEGY §17](../08-testing/TEST_STRATEGY.md#17-tooling)
- [SECURITY_TEST_MATRIX](../08-testing/SECURITY_TEST_MATRIX.md) SM-03
- [SECURITY §7](../05-security/SECURITY.md#7-secrets-production-boundary-and-supply-chain) SX-05, SX-06
- [DESIGN_SYSTEM Annex B](../07-ux-design/DESIGN_SYSTEM.md#annex-b-the-carried-items-gr-01gr-23) GR-04
- [AGENTS.md](../../AGENTS.md#git-commit-attribution)
- [EXECUTION_CONTEXT](EXECUTION_CONTEXT.md)
- [DEFINITION_OF_DONE §1](../08-testing/DEFINITION_OF_DONE.md#1-statuses-and-the-done-rule)
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (24): WS-01; WS-09; SX-05–SX-06; TM-09; TM-37; TM-48–TM-49; PF-01–PF-12; GR-04; TL-01–TL-02; INF-05

**EXPECTED MODULES / FILE AREAS:**

- the tool configurations at the repository root; `tests/Architecture/`; the conventions section of `DEVELOPMENT.md`; this Task's jobs in the CI workflow

**BUSINESS RULES:**

- N/A — no business rule; the conventions carry EXECUTION_CONTEXT's invariants for later Tasks

**ARCHITECTURE RULES:**

- the rules above are executable and fail the build when broken

**SECURITY / PERMISSION REQUIREMENTS:**

- the threats of [SECURITY §8](../05-security/SECURITY.md#8-threat-model) mapped to this Task, each with the controls its row names: TM-09, Mass assignment of company, authority or state fields (WS-01, WS-02, DP-03); TM-37, Stored XSS rendered in React or a PDF (WS-09, FL-08); TM-48, Secrets in logs, repository or client bundles (LG-03, SX-01, SX-06); TM-49, Vulnerable or malicious dependency (SX-05)
- a secret-like scan of the changes (SX-06), run locally and as a job of the CI workflow; TASK-003 makes it one of SM-15's gates

**COMPANY SCOPE AND FIELD PROJECTION:**

- N/A — no data path exists yet

**CONCURRENCY / IDEMPOTENCY:**

- N/A — no command exists yet

**AUDIT:**

- N/A — no business action

**DATABASE IMPACT ASSESSMENT:**

- Schema change: NONE — no table or column
- Migration required: NO
- Existing data transformation: NONE
- Direct production DB access: FORBIDDEN
- Data-loss risk: NONE KNOWN

**MODULARITY / EXTENSIBILITY:**

- Module boundary affected: none — the rules guard every boundary
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

- Context7: REQUIRED — the current configuration options of Larastan, Pint, ESLint and Pest's architecture plugin
- Graphify: N/A — the codebase is the skeleton of TASK-001
- Browser E2E: N/A — no page
- Other: the static tools of TEST_STRATEGY §17, verified and recorded in TOOLCHAIN
- Missing safe tool behavior: AUTO-INSTALL project-locally inside this Task (AICWDF §6.3) and record it in TOOLCHAIN; a paid tier or a privileged installation STOPS for the Owner (COST_POLICY)

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: N/A — the codebase is too young for a graph to add value; the impact is read from the diff and ARCHITECTURE §3–§4
- Pre-change questions: which namespaces the architecture tests must cover
- Expected affected areas: the IN SCOPE areas only

**CONTEXT7:**

- Topics to verify: Larastan levels; Pest architecture expectations; ESLint flat configuration for React and TypeScript
- Version-sensitive assumptions: rule names checked against the installed versions

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

1. A forbidden dependency — a domain class using HTTP, a lower tier writing through a higher one, a controller with business logic — fails the architecture tests.
2. Each static tool runs with its committed configuration and passes on the skeleton.
3. A commit message carrying an AI or tool attribution trailer is refused by the commit check.
4. A lazy-loaded relation in a test run fails it, as PERFORMANCE §3 requires.
5. The conventions section of `DEVELOPMENT.md` states the action, query, money and naming rules with links to their sources.
6. This Task's checks run as jobs of the CI workflow on a fresh runner and pass there as they do locally (DEFINITION_OF_DONE §1).
7. A CI job checks the Task plan's integrity on every push and pull request — TASK_PLAN §1's counters equal to its §4 list, §3's checked boxes equal to the DONE Tasks, each contract's STATUS, DEPENDS ON and BLOCKS equal to its §4 row, every dependency mirrored and the graph acyclic, and the relative links and anchors under `docs/` resolving —, and a disagreement fails the job (RELEASE_PLAN §12.2; PMC-15).

**TEST REQUIREMENTS:**

- Lint: Pint and ESLint (TL-01)
- Typecheck: PHPStan and the TypeScript compiler (TL-02)
- Unit: the architecture tests, each rule proven with a deliberate violation in a fixture (SM-03)
- Feature: N/A — no endpoint
- Integration: N/A — no service used
- Authorization: N/A — no route
- Route integrity: N/A — no route
- Interaction: N/A — no INT- contract in this Task
- Browser E2E: N/A — no page
- Localization/language switch: N/A — no copy
- Visual breathing room/density: N/A — no page in this Task
- Responsive: N/A — no page in this Task
- Accessibility: N/A — no page in this Task
- Production build: the production build still passes (TL-13)
- Security: the secret-like scan and the dependency audit (TL-14)
- Performance: N/A — no query
- Migration: N/A — no migration
- Other: the commit-message check on a sample attributed message; the plan-integrity job on the committed plan and on a fixture copy with a deliberate mismatch, which it fails; this Task's jobs green in the CI workflow on a fresh runner

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
- Route evidence: the route audit for the routes this Task adds or changes, or N/A with its reason
- Interaction evidence: the INT- tests of this Task, or N/A with its reason
- Browser evidence: `E2E: PASS — Evidence: …` for its journeys (DOD-18), or N/A with its reason
- Localization evidence: both languages on every page it changes (DOD-16), or N/A with its reason
- Cost evidence/exception if applicable: ADDITIONAL RECURRING COST CHANGE: NONE (DOD-20)
- Files changed: the list, every one inside the IN SCOPE areas
- Commit SHA: the commit under the Owner's identity, with no AI attribution
- Notes: each non-applicable item with its reason (DOD-03); the DONE update of TASK_PLAN, this file and CURRENT_HANDOFF (AICWDF §22.3)

**SAFE NEXT TASK:**

- TASK-003 — when its other dependencies are DONE and its READY gate holds

**DO NOT DO:**

- add to V1 — a capability, page, route, field, integration, document type, report or paid service — or drop, defer or narrow a V1_REQUIRED requirement; a Task that would need either raises an Owner question and waits ([DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-20)
- access the VPS, production, its data or a credential, or put a secret, real data, a host detail or production configuration in the repository ([INFRASTRUCTURE](../09-operations/INFRASTRUCTURE.md) INF-01, INF-05; SECURITY SX-01, SX-04, SX-06; [PRODUCTION_DATA_SAFETY](../00-governance/PRODUCTION_DATA_SAFETY.md))
- add AI, model or tool attribution to a commit — the Owner's configured identity only ([AGENTS.md](../../AGENTS.md#git-commit-attribution); DIR-022)
- start before the Owner's execution authorization at OG-4, or mark DONE without the required tests passing and the completion evidence ([TASK_PLAN](TASK_PLAN.md#2-rules-of-the-plan); [DEFINITION_OF_DONE](../08-testing/DEFINITION_OF_DONE.md#2-the-checklist))
- weaken, skip or delete a valid test to obtain a green run, or adopt a paid tier without the Owner's COST_POLICY decision (DOD-05; [COST_POLICY](../00-governance/COST_POLICY.md))
- lower a tool's level to make the skeleton pass
- add a rule the canonical documents do not support

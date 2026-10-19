# TASK-004 — Verification and release tooling check

Status: REVIEW | Updated: 2026-10-20 | Owner: Planning

Written by the executor of run PC-5 on 2026-10-19 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99 — a REVIEW draft of P11, approved, or not, with the [Task plan](TASK_PLAN.md) at OG-4. **No Task starts before the Owner's execution authorization at OG-4.** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** TODO

**RISK CLASS:** STANDARD

**EXECUTOR:** AGENT

**OBJECTIVE:**

Verify, before any release work depends on them, the availability, version, licence and cost of P8's tooling and of the release tooling, and record TestSprite's entitlement exactly as DIR-083 OE-01 requires.

**WHY THIS EXISTS:**

PRQ-12 asks for the verification of the release tooling and of P8's tooling before the first release; OE-01 allows TestSprite only once a P11 Task has verified its entitlement at no cost or the Owner has approved a cost exception (DEP-07; REF-061).

**DEPENDS ON:**

- TASK-003 — CI pipeline, test layers and the synthetic fixture harness

**BLOCKS:**

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

- Context7: REQUIRED — each tool's current official licence and pricing pages
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

- [ ] Each tool verified and recorded
- [ ] TestSprite's entitlement recorded with evidence
- [ ] No paid tier adopted
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

- TASK-073 — when its other dependencies are DONE and its READY gate holds

**DO NOT DO:**

- add to V1 — a capability, page, route, field, integration, document type, report or paid service — or drop, defer or narrow a V1_REQUIRED requirement; a Task that would need either raises an Owner question and waits ([DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-20)
- access the VPS, production, its data or a credential, or put a secret, real data, a host detail or production configuration in the repository ([INFRASTRUCTURE](../09-operations/INFRASTRUCTURE.md) INF-01, INF-05; SECURITY SX-01, SX-04, SX-06; [PRODUCTION_DATA_SAFETY](../00-governance/PRODUCTION_DATA_SAFETY.md))
- add AI, model or tool attribution to a commit — the Owner's configured identity only ([AGENTS.md](../../AGENTS.md#git-commit-attribution); DIR-022)
- start before the Owner's execution authorization at OG-4, or mark DONE without the required tests passing and the completion evidence ([TASK_PLAN](TASK_PLAN.md#2-rules-of-the-plan); [DEFINITION_OF_DONE](../08-testing/DEFINITION_OF_DONE.md#2-the-checklist))
- weaken, skip or delete a valid test to obtain a green run, or adopt a paid tier without the Owner's COST_POLICY decision (DOD-05; [COST_POLICY](../00-governance/COST_POLICY.md))
- create a paid account, accept a paid plan or enter payment details
- record an entitlement without its dated evidence

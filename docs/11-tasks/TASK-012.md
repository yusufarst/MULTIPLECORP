# TASK-012 — Field projection, read classes and cross-company links

Status: REVIEW | Updated: 2026-10-19 | Owner: Planning

Written by the executor of run PC-5 on 2026-10-19 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99 — a REVIEW draft of P11, approved, or not, with the [Task plan](TASK_PLAN.md) at OG-4. **No Task starts before the Owner's execution authorization at OG-4.** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** TODO

**RISK CLASS:** HIGH

**EXECUTOR:** AGENT

**OBJECTIVE:**

Limit what each account sees of every table: the field projection PJ-01–PJ-23, the read classes RD-01–RD-10, the table classification of PERMISSIONS_MATRIX §7.2 with its special projections, the cross-company link authorization XL-1–XL-5 and the data-path coverage DP-, applied to Inertia props and every query class.

**WHY THIS EXISTS:**

Field projection is invariant 3 of EXECUTION_CONTEXT; the pooled stock with preserved attribution and shared masters (GAP-003, GAP-006) are safe only if each family of fields reaches only its capability, and SM-25 tests it.

**DEPENDS ON:**

- TASK-009 — Schema — FUL and FIN tables, finance and tax representations
- TASK-011 — Authorization core — capabilities, roles, grants, company scope and the route audit

**BLOCKS:**

- TASK-016 — Application shell, navigation and the company context
- TASK-022 — Jobs, files and the document renderer

**PARALLEL SAFE:** NO — every page and query Task builds on the projection layer

**IN SCOPE:**

- the projection classes and entitlements of §7.1 and the classification of every table in §7.2, with the special projections of §7.3 and the read classes of §7.4
- Inertia prop resources that carry only projected fields; shared props as WS-07 limits them
- XL-1–XL-5: the inter-company overlay, correction-case side and context, linked projects and real transfers, each authorized as §8 states
- the data-path coverage matrix DP- as an executable check: every path a projection and a denial test
- the image and prop rules of PERFORMANCE §8 (PF-31–PF-36) for projected props

**OUT OF SCOPE:**

- capabilities and scope (TASK-011)
- the pages themselves — each page's Task applies these classes

**REFERENCES:**

- [PERMISSIONS_MATRIX §7](../05-security/PERMISSIONS_MATRIX.md#7-resource-and-field-projection)
- [PERMISSIONS_MATRIX §8](../05-security/PERMISSIONS_MATRIX.md#8-cross-company-link-authorization)
- [PERMISSIONS_MATRIX §11](../05-security/PERMISSIONS_MATRIX.md#11-data-path-coverage-matrix)
- [SECURITY §4](../05-security/SECURITY.md#4-web-and-input-security) WS-07
- [PERFORMANCE §8](../06-api-performance/PERFORMANCE.md#8-images-and-inertia-props)
- [SECURITY_TEST_MATRIX](../08-testing/SECURITY_TEST_MATRIX.md) SM-12, SM-25
- the architecture challenge's sequences 1a, 8b–8c, 18b ([P11 gate record](evidence/P11_QUALITY_GATE.md#whole-system-architecture-challenge-sp-98))
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (93): PJ-01–PJ-23; RD-01–RD-10; XL-1–XL-5; DP-01–DP-20; D-PM-01–D-PM-12; WS-07; TM-13–TM-14; TM-18–TM-21; TM-24–TM-25; TM-28; TM-52–TM-55; PF-31–PF-36; SM-25; GAP-003; GAP-006

**EXPECTED MODULES / FILE AREAS:**

- the platform projection classes; the prop resources' base; the DP- coverage test

**BUSINESS RULES:**

- N/A — projection rules only; each module's business rules are its own Task's

**ARCHITECTURE RULES:**

- projection happens in query classes and resources, never in React (ARCHITECTURE §9–§10); no field reaches the client to be hidden there

**SECURITY / PERMISSION REQUIREMENTS:**

- no oracle: a projected-away field never leaks through counts, sorting, filters or errors (SM-12); WS-07's shared props only

**COMPANY SCOPE AND FIELD PROJECTION:**

- PJ-01–PJ-23 and RD-01–RD-10 inside the actor's scope (CS-); POOL records with S1 physical attributes only; cost and finance families by `cost.view` and `finance.view`

**CONCURRENCY / IDEMPOTENCY:**

- N/A — read side; a stale projection after a revoked grant is handled by TASK-011's re-authorization and TASK-016's reload rules

**AUDIT:**

- N/A — reads are not audited except where SECURITY §6 says (security events of TASK-010)

**DATABASE IMPACT ASSESSMENT:**

- Schema change: NONE — classifies the tables of TASK-005–TASK-009
- Migration required: NO
- Existing data transformation: NONE
- Direct production DB access: FORBIDDEN
- Data-loss risk: NONE KNOWN

**MODULARITY / EXTENSIBILITY:**

- Module boundary affected: platform read layer
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
- Authorization change: PROPOSED — the designed projection, nothing beyond it
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

- Context7: REQUIRED — Inertia's partial reloads and resource serialization
- Graphify: OPTIONAL — once modules exist, to list every query class a projection must cover
- Browser E2E: N/A — no page
- Other: none
- Missing safe tool behavior: AUTO-INSTALL project-locally inside this Task (AICWDF §6.3) and record it in TOOLCHAIN; a paid tier or a privileged installation STOPS for the Owner (COST_POLICY)

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: N/A — the codebase is too young for a graph to add value; the impact is read from the diff and ARCHITECTURE §3–§4
- Pre-change questions: which query classes and resources must use the projection
- Expected affected areas: the IN SCOPE areas only

**CONTEXT7:**

- Topics to verify: Inertia shared props and lazy props in the installed versions
- Version-sensitive assumptions: none from memory

**UI / UX REQUIREMENTS:**

- Follow global design rules: N/A — no page — pages apply the projection
- Indonesian default: N/A — no page — pages apply the projection
- English support: N/A — no page — pages apply the projection
- Plain-language Indonesian: N/A — no page — pages apply the projection
- Language switch impact: NONE
- Mobile-first: N/A — no page — pages apply the projection
- Responsive: N/A — no page — pages apply the projection
- Adaptive: N/A — no page — pages apply the projection
- Visual breathing room: N/A — no page — pages apply the projection
- Comfortable density: N/A — no page — pages apply the projection
- shadcn/ui reuse: N/A — no page — pages apply the projection
- Design reference: N/A — no page — pages apply the projection
- Primary action: N/A — no page — pages apply the projection
- Secondary actions: N/A — no page — pages apply the projection
- Destructive actions: N/A — no page — pages apply the projection
- Redundancy constraints: N/A — no page — pages apply the projection
- Loading state: N/A — no page — pages apply the projection
- Empty state: N/A — no page — pages apply the projection
- Error state: N/A — no page — pages apply the projection

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

1. Every table of DATABASE §4 has its §7.2 class in code, checked against the document by a test.
2. A field outside the actor's entitlement never appears in a prop, a JSON answer, an export or an error (SM-25 for the families that exist).
3. XL-1–XL-5 are the only cross-company references a query follows, each authorized as §8 states.
4. The DP- matrix runs as a test: each path has its projection and its denial.

**TEST REQUIREMENTS:**

- Lint: TL-01
- Typecheck: TL-02
- Unit: the class and entitlement resolution (TL-03)
- Feature: props of a sample page for each viewer class (TL-04)
- Integration: the projection applied inside queries before aggregation (TL-05)
- Authorization: SM-12 and SM-25 for the families that exist (TL-06)
- Route integrity: N/A — no route added or changed
- Interaction: N/A — no INT- contract in this Task
- Browser E2E: N/A — no page
- Localization/language switch: N/A — no user-facing copy in this Task
- Visual breathing room/density: N/A — no page in this Task
- Responsive: N/A — no page in this Task
- Accessibility: N/A — no page in this Task
- Production build: TL-13
- Security: no oracle through counts, sort or filters (TL-14)
- Performance: PF-31–PF-36 for props (TL-15)
- Migration: N/A — no migration or import code in this Task
- Other: N/A — nothing further

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

- TASK-016 — when its other dependencies are DONE and its READY gate holds

**DO NOT DO:**

- add to V1 — a capability, page, route, field, integration, document type, report or paid service — or drop, defer or narrow a V1_REQUIRED requirement; a Task that would need either raises an Owner question and waits ([DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-20)
- access the VPS, production, its data or a credential, or put a secret, real data, a host detail or production configuration in the repository ([INFRASTRUCTURE](../09-operations/INFRASTRUCTURE.md) INF-01, INF-05; SECURITY SX-01, SX-04, SX-06; [PRODUCTION_DATA_SAFETY](../00-governance/PRODUCTION_DATA_SAFETY.md))
- add AI, model or tool attribution to a commit — the Owner's configured identity only ([AGENTS.md](../../AGENTS.md#git-commit-attribution); DIR-022)
- start before the Owner's execution authorization at OG-4, or mark DONE without the required tests passing and the completion evidence ([TASK_PLAN](TASK_PLAN.md#2-rules-of-the-plan); [DEFINITION_OF_DONE](../08-testing/DEFINITION_OF_DONE.md#2-the-checklist))
- weaken, skip or delete a valid test to obtain a green run, or adopt a paid tier without the Owner's COST_POLICY decision (DOD-05; [COST_POLICY](../00-governance/COST_POLICY.md))
- send a field to the client in order to hide it there
- add a projection class or a cross-company link kind PERMISSIONS_MATRIX does not define

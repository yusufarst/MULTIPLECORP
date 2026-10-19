# TASK-078 — Staging stack, the two applications' isolation and the provider features

Status: REVIEW | Updated: 2026-10-19 | Owner: Planning

Written by WF-PC5-W's writer of bundle W4 of run PC-5 on 2026-10-19 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99 — a REVIEW draft of P11, approved, or not, with the [Task plan](TASK_PLAN.md) at OG-4. **No Task starts before the Owner's execution authorization at OG-4.** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** TODO

**RISK CLASS:** HIGH

**EXECUTOR:** HUMAN OPERATOR

**OBJECTIVE:**

The human operator starts the ephemeral staging stack on the VPS from TASK-072's definition with its synthetic seed, resource-capped below the headroom both production applications need; shows on it the isolation of the two applications — MULTIPLECORP's own containers, database instance, Valkey, storage, secrets, backup directory and deployment — and rehearses the release's deployment and its code rollback on it without touching the second application; checks TLS and HSTS on the staging host; has every provider-side feature able to act on the host or its data confirmed off, or its reach accepted by the Owner with the client; removes the stack after the release's staging work; and records each result.

**WHY THIS EXISTS:**

Staging is the only place where the VPS's own hardware, TLS and the operator's commands are exercised ([TEST_STRATEGY §4](../08-testing/TEST_STRATEGY.md#4-environments-and-data)), and on a VPS shared with the client's second production application no MULTIPLECORP step may touch the other ([INFRASTRUCTURE](../09-operations/INFRASTRUCTURE.md#1-invariants) INF-07; DIR-083 OE-13) — the Owner's conditions (1), (3) and (4) in session. GLC-19 requires that isolation shown on staging and GLC-24 the provider features able to act on the host confirmed off before production data exists, and [DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-14 makes both go-live conditions passed only by recorded evidence — the architecture challenge's sequence 16a.

**DEPENDS ON:**

- TASK-071 — The operator's inputs before staging
- TASK-072 — Deployment definition, health and smoke checks, maintenance mode and mixed-version safeguards

**BLOCKS:**

- TASK-079 — Staging measurements — capacity, Argon2id, durations and the disk budget
- TASK-080 — Alert channel adopted and monitoring live
- TASK-081 — Staging drills and rehearsals — failure, restore, Scenario B and the import rehearsal
- TASK-086 — UAT with the Owner and the validators

**PARALLEL SAFE:** NO — it runs on the shared VPS, and the measurements, the alert channel, the drills and the UAT of TASK-079, TASK-080, TASK-081 and TASK-086 run on the stack it proves

**IN SCOPE:**

- **The staging stack** (PRQ-02; DIR-079 CB-04): started by the operator from TASK-072's definition, with its own containers, PostgreSQL instance, Valkey and storage and synthetic data only — the fictional group ARJ, BTN and CKP of FX-02, never a copy of production —; resource-capped below the headroom both production applications need (OPI-27); started only under the client's grant (OPI-26), outside both applications' business hours and clear of the second application's own windows (INFRASTRUCTURE §3)
- **GLC-19's isolation, shown**: MULTIPLECORP's own containers, database instance, storage, secrets, backup directory and deployment project; PostgreSQL and Valkey reachable only from MULTIPLECORP's containers, with authentication, on no interface the second application can reach (H9-09); MULTIPLECORP's own firewall rules only (INFRASTRUCTURE §7); staging's name routed by the shared host proxy through its tested reload, the other application's connections kept (INFRASTRUCTURE §2)
- **The release rehearsed** by TASK-072's runbook (REL-03): the migration, the worker restart, the interrupted-migration case and its recovery (RBK-05), the mixed-version checks — job payload versions and a page loaded before the deployment posting a command afterwards (HO-31) —, a code rollback by RBK-01, and each step's measured duration for a release window kept clear of the second application's (OPI-27) — all without touching the second application (GLC-19; RBK-06)
- **The second application watched** through every step and never stopped, reloaded, read or changed (INF-07; architecture challenge 16a)
- **TLS and HSTS on the staging host**: the free certificate, plain HTTP redirected, HSTS at its configured stage and the static headers, the case SECURITY_TEST_MATRIX §9 leaves to the operator (SM-08; WS-06; H9-02)
- **The provider features** (OPI-29; INF-08; GLC-24): each feature TASK-071 recorded as verified on the panel; every feature able to act on the host or its data — automation or AI assistance among them — confirmed off before production data exists, or its reach recorded and accepted by the Owner with the client; none relied on; the confirmation re-checked before production data in TASK-084 (CUT-02)
- **INF-01**: the operator alone works on the VPS; no agent reaches staging, which agents meet only as CI's production-like stack (INFRASTRUCTURE §3)
- **The removal**: the stack removed after the release's staging work, its synthetic data with it (CB-04); each later staging Task starts it again from the same definition
- **The record**: each check in TEST_STRATEGY §16's form without secrets, with REL-03's step durations, the resource caps used and each provider feature's state

**OUT OF SCOPE:**

- the measurements (TASK-079), the alert channel and monitoring live (TASK-080), the drills and the import rehearsal (TASK-081), the UAT (TASK-086)
- the backup and the restore drill of GLC-19, which need TASK-074's tooling — shown without touching the second application in TASK-081's drills
- anything on production (TASK-084, TASK-085, TASK-087)
- any change to the second application or its windows; a standing staging stack or a separate paid staging server (P9Q-01 (b), (c))

**REFERENCES:**

- [INFRASTRUCTURE §1](../09-operations/INFRASTRUCTURE.md#1-invariants) INF-01, INF-07, INF-08; [§2](../09-operations/INFRASTRUCTURE.md#2-topology); [§3](../09-operations/INFRASTRUCTURE.md#3-environments); [§5](../09-operations/INFRASTRUCTURE.md#5-the-deployment-path); [§7](../09-operations/INFRASTRUCTURE.md#7-security-operations); [§9](../09-operations/INFRASTRUCTURE.md#9-the-operators-inputs) OPI-26, OPI-27, OPI-29; [§10](../09-operations/INFRASTRUCTURE.md#10-owner-questions) P9Q-01 (DIR-079 CB-04)
- [RELEASE_PLAN §2](../10-release/RELEASE_PLAN.md#2-the-branch-and-environment-model); [§3](../10-release/RELEASE_PLAN.md#3-the-release-sequence) REL-03; [§6](../10-release/RELEASE_PLAN.md#6-cutover-and-rollback) RBK-01, RBK-05, RBK-06; [§9](../10-release/RELEASE_PLAN.md#9-the-go-live-checklist) GLC-19, GLC-24; [§10](../10-release/RELEASE_PLAN.md#10-production-release-requirements-for-p11) PRQ-02
- [TEST_STRATEGY §4](../08-testing/TEST_STRATEGY.md#4-environments-and-data) FX-02; [§16](../08-testing/TEST_STRATEGY.md#16-evidence)
- [SECURITY_TEST_MATRIX](../08-testing/SECURITY_TEST_MATRIX.md#3-the-matrix) SM-08; [§9](../08-testing/SECURITY_TEST_MATRIX.md#9-where-each-case-runs)
- [SECURITY §4](../05-security/SECURITY.md#4-web-and-input-security) WS-06, WS-08; [§10](../05-security/SECURITY.md#p9--operations) H9-02, H9-09
- [CONCURRENCY_IDEMPOTENCY §19](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s19-20-handoff-traceability.md#19-handoff-obligations) HO-31
- [PRODUCTION_DATA_SAFETY](../00-governance/PRODUCTION_DATA_SAFETY.md)
- [DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-13, OE-14
- Architecture challenge — the sequence 16a of the [P11 gate record](evidence/P11_QUALITY_GATE.md#whole-system-architecture-challenge-sp-98), their assertions carried into this Task's acceptance criteria and tests
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (6): INF-01; INF-07–INF-08; GLC-19; GLC-24; PRQ-02

**EXPECTED MODULES / FILE AREAS:**

- no code; the operator's record outside the repository, summarized in COMPLETION EVIDENCE; a defect found in the definition or the runbook goes back to TASK-072 through the executor feedback loop ([AGENT_OPERATING_MODEL](../00-governance/AGENT_OPERATING_MODEL.md#executor-feedback-loop)), never patched on the host

**BUSINESS RULES:**

- N/A — no business rule; staging runs the release on synthetic data only

**ARCHITECTURE RULES:**

- staging is ephemeral, isolated, resource-capped and synthetic, and is removed after the release (DIR-079 CB-04; INF-07)
- every step acts on MULTIPLECORP's own staging project; production and the second application keep serving (RBK-06); a code rollback is not data recovery (RL-04)

**SECURITY / PERMISSION REQUIREMENTS:**

- synthetic data and synthetic secrets only — never production's data, backups or keys (INFRASTRUCTURE §3; PRODUCTION_DATA_SAFETY); staging's name kept outside the repository (OPI-30)
- the CSP report endpoint exists on staging only (WS-08; EN-03); the operator alone holds staging's access (INF-01)

**COMPANY SCOPE AND FIELD PROJECTION:**

- N/A — the synthetic group only; staging enforces the same scope and projection as production, which the suites of TASK-069 prove — this Task adds no path

**CONCURRENCY / IDEMPOTENCY:**

- N/A — no command or lock is added; the rehearsal exercises HO-31's one-release validity on staging as TASK-072 built it

**AUDIT:**

- N/A — no business action on real data; staging's synthetic audit and security events go with the stack, and the operator's record is the evidence (TEST_STRATEGY §16)

**DATABASE IMPACT ASSESSMENT:**

- Schema change: NONE — no repository change; production data untouched except through the approved path
- Migration required: NO
- Existing data transformation: NONE
- Direct production DB access: FORBIDDEN
- Data-loss risk: NONE KNOWN

**MODULARITY / EXTENSIBILITY:**

- Module boundary affected: none — no code
- Stable core kept provider-neutral: YES — no provider feature is relied on (INF-08)
- External/vendor-specific code isolated: N/A — no code
- Replaceable adapter/contract required: NO
- Provider lock-in risk: NONE — every provider feature able to act on the host is confirmed off or accepted with its reach recorded (GLC-24)
- Future extension impact: LOW
- Speculative abstraction avoided: YES — the same definition as production, no staging-only tooling

**AUTH IMPACT:**

- Authentication change: NONE
- Authentication implementation: N/A — no code; the profile stays LARAVEL_NATIVE ([EXECUTION_CONTEXT](EXECUTION_CONTEXT.md))
- Auth profile: N/A — not touched; staging's accounts are synthetic
- Google sign-in: N/A — off on staging until TASK-082's staging client exists (INFRASTRUCTURE §4.3)
- Google registration behavior: N/A — not touched
- Google credentials owner action required: NO
- Authorization change: NONE
- Provider/library-specific leakage into domain: NO

**COST IMPACT:**

- New recurring cost: NONE
- Paid third-party service: NO
- Free/open-source alternative: N/A — nothing paid is considered; staging runs on the existing VPS (DIR-079 CB-04)
- Estimated additional monthly production cost: 0
- Owner approval required: NO

**MCP SESSION PROFILE:**

- Required MCP servers: none — [TOOLCHAIN](../00-governance/TOOLCHAIN.md#mcp-servers) records none installed
- MCP servers that should remain OFF: every server not required
- Native CLI/package preferred over MCP: YES — the framework's and package managers' own commands
- Post-Task deactivation/cleanup: none

**TOOL REQUIREMENTS:**

- Context7: N/A — the operator follows TASK-072's definition and runbook, whose versions that Task verified
- Graphify: N/A — no code
- Browser E2E: N/A — no journey is added; the page-before-deployment case is checked in a supported browser on staging (HO-31)
- Other: the operator's access to the VPS and the panel (OPI-26); TASK-072's definition, seed and runbook; a TLS and header check run against the staging host
- Missing safe tool behavior: N/A — no agent installs anything; the operator installs only what TASK-072's definition names, and a paid tier STOPS for the Owner (COST_POLICY §4C.2)

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: N/A — no code
- Pre-change questions: which of the second application's windows and loads the rehearsal must avoid (OPI-27); which provider features TASK-071 recorded as active (OPI-29)
- Expected affected areas: the staging stack only; nothing of production or the second application

**CONTEXT7:**

- Topics to verify: N/A — no library is configured beyond TASK-072's definition
- Version-sensitive assumptions: none — staging runs the release's tagged commit as built

**UI / UX REQUIREMENTS:**

- Follow global design rules: N/A — no page is built or changed; staging serves the release as built
- Indonesian default: N/A — no page is built or changed
- English support: N/A — no page is built or changed
- Plain-language Indonesian: N/A — no page is built or changed
- Language switch impact: NONE
- Mobile-first: N/A — no page is built or changed
- Responsive: N/A — no page is built or changed
- Adaptive: N/A — no page is built or changed
- Visual breathing room: N/A — no page is built or changed
- Comfortable density: N/A — no page is built or changed
- shadcn/ui reuse: N/A — no page is built or changed
- Design reference: N/A — no page is built or changed
- Primary action: N/A — no page is built or changed
- Secondary actions: N/A — no page is built or changed
- Destructive actions: N/A — no page; the stack's removal deletes synthetic data only
- Redundancy constraints: N/A — no page is built or changed
- Loading state: N/A — no page is built or changed
- Empty state: N/A — no page is built or changed
- Error state: N/A — no page; a failed rehearsal step stops for the operator's decision (RBK-04)

**ROUTE IMPACT:**

- none — staging serves the release's routes as built

**INTERACTION CONTRACTS:**

- N/A — no interaction is built or changed

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

1. The staging stack runs from TASK-072's definition with synthetic data only, resource-capped below both production applications' headroom, and is removed after the release's staging work (DIR-079 CB-04; PRQ-02).
2. The record shows MULTIPLECORP's own containers, PostgreSQL instance, Valkey, storage, secrets, backup directory and deployment project, with PostgreSQL and Valkey unreachable from anything but MULTIPLECORP's containers (GLC-19; INF-07; H9-09).
3. The release's deployment and its code rollback are rehearsed on staging by the runbook — the migration, the worker restart, the interrupted-migration case and its recovery, the mixed-version checks — and each step's duration is recorded (REL-03; RBK-01; RBK-05).
4. Throughout, the second application keeps serving and nothing of it is stopped, reloaded, read or changed; the shared host proxy changes only by its tested reload (INF-07; RBK-06; architecture challenge 16a).
5. The TLS and HSTS checks pass on the staging host (SM-08; WS-06).
6. Every provider-side feature is recorded with its state, and every feature able to act on the host or its data is confirmed off, or its reach recorded and accepted by the Owner with the client (GLC-24; INF-08).
7. No agent held any access to staging, the VPS or the panel during the Task (INF-01).

**TEST REQUIREMENTS:**

- Lint: N/A — no code in this Task
- Typecheck: N/A — no code in this Task
- Unit: N/A — no code in this Task
- Feature: N/A — no route or command in this Task; the release's smoke checks run on staging by the runbook (REL-11)
- Integration: N/A — no code; the deployment and rollback rehearsal on the staging stack stands in its place (REL-03)
- Authorization: N/A — no route; GLC-19's isolation checks of the stores and the network instead
- Route integrity: N/A — no route added or changed
- Interaction: N/A — no INT- contract in this Task
- Browser E2E: N/A — no journey; the page-before-deployment case checked in a supported browser (HO-31)
- Localization/language switch: N/A — no user-facing copy in this Task
- Visual breathing room/density: N/A — no page in this Task
- Responsive: N/A — no page in this Task
- Accessibility: N/A — no page in this Task
- Production build: N/A — the release's build is CI's TL-13; staging runs its tagged commit
- Security: SM-08's TLS and HSTS on the staging host (TL-14); H9-09's private stores
- Performance: N/A — the measurements are TASK-079's; REL-03's step durations are recorded here
- Migration: TL-16's staging half — the release's migrations on staging with the interrupted-migration case and its recovery (REL-03; RBK-05)
- Other: GLC-24's confirmation, each provider feature with its state and, where one stays on, the Owner's and the client's recorded acceptance

**TEST CHECKLIST:**

- [ ] Lint — N/A: no code in this Task
- [ ] Typecheck — N/A: no code in this Task
- [ ] Relevant automated tests — N/A: no code; the operator's record of the stack, the isolation checks, the rehearsal with its step durations and the removal stands in their place
- [ ] Authorization — the stores reachable only from MULTIPLECORP's containers; no agent access to staging (INF-01; H9-09)
- [ ] Route integrity — N/A: no route added or changed
- [ ] Interaction behavior — N/A: no interaction built or changed
- [ ] Browser E2E if required — N/A: no journey; the page-before-deployment case recorded from the operator's browser
- [ ] Responsive/adaptive if required — N/A: no page
- [ ] Visual breathing room/density if UI — N/A: no page
- [ ] Production build — N/A: staging runs the release's tagged commit as CI built it
- [ ] Security checks if required — SM-08's TLS and HSTS on the staging host recorded
- [ ] Database safety review — synthetic data only, no production data, backup or key on staging; nothing of the second application read or changed
- [ ] Final diff review — no repository change beyond this record's summary; a definition defect sent back to TASK-072

**COMPLETION EVIDENCE:**

- Test outputs: the operator's record — the stack's start with its caps, each GLC-19 isolation check, REL-03's steps with their durations and the RBK-01 rollback, the second application's health through each step, SM-08's result, each provider feature's state, the removal — in [TEST_STRATEGY §16](../08-testing/TEST_STRATEGY.md#16-evidence)'s form without secrets; only recorded evidence passes (DIR-083 OE-14)
- Build result: N/A — no build in this Task; the tagged commit staged is named in the record
- Route evidence: N/A — no route added or changed
- Interaction evidence: N/A — no INT- contract in this Task
- Browser evidence: N/A — no journey; the page-before-deployment check is part of the record
- Localization evidence: N/A — no page in this Task
- Cost evidence/exception if applicable: ADDITIONAL RECURRING COST CHANGE: NONE (DOD-20) — staging on the existing VPS
- Files changed: none in the application; this file's evidence summary only
- Commit SHA: the commit that records the summary, under the Owner's identity, with no AI attribution
- Notes: each non-applicable item with its reason (DOD-03); GLC-19 completed for the backup and restore drill by TASK-081's record; the DONE update of TASK_PLAN, this file and CURRENT_HANDOFF (AICWDF §22.3), made on the operator's record and never on an agent's claim (TASK_PLAN §2)

**SAFE NEXT TASK:**

- TASK-079 — when its other dependencies are DONE and its READY gate holds

**DO NOT DO:**

- add to V1 — a capability, page, route, field, integration, document type, report or paid service — or drop, defer or narrow a V1_REQUIRED requirement; a Task that would need either raises an Owner question and waits ([DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-20)
- access the VPS, production, its data or a credential, or put a secret, real data, a host detail or production configuration in the repository ([INFRASTRUCTURE](../09-operations/INFRASTRUCTURE.md) INF-01, INF-05; SECURITY SX-01, SX-04, SX-06; [PRODUCTION_DATA_SAFETY](../00-governance/PRODUCTION_DATA_SAFETY.md))
- add AI, model or tool attribution to a commit — the Owner's configured identity only ([AGENTS.md](../../AGENTS.md#git-commit-attribution); DIR-022)
- start before the Owner's execution authorization at OG-4, or mark DONE without the required tests passing and the completion evidence ([TASK_PLAN](TASK_PLAN.md#2-rules-of-the-plan); [DEFINITION_OF_DONE](../08-testing/DEFINITION_OF_DONE.md#2-the-checklist))
- weaken, skip or delete a valid test to obtain a green run, or adopt a paid tier without the Owner's COST_POLICY decision (DOD-05; [COST_POLICY](../00-governance/COST_POLICY.md))
- let an agent run a step of this Task or reach staging, the VPS or the panel — the operator alone (INF-01)
- load production data, a production backup or a production secret into staging (INFRASTRUCTURE §3; PRODUCTION_DATA_SAFETY)
- stop, reload, read or change the second application, or run staging inside its windows or beyond its caps (INF-07; OPI-27)
- rely on a provider feature, or leave one able to act on the host on without the Owner's and the client's recorded acceptance (INF-08; GLC-24)

# TASK-071 — The operator's inputs before staging

Status: REVIEW | Updated: 2026-10-20 | Owner: Planning

Written by WF-PC5-W's writer of bundle W4 of run PC-5 on 2026-10-19 and 2026-10-20 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99 — a REVIEW draft of P11, approved, or not, with the [Task plan](TASK_PLAN.md) at OG-4. **No Task starts before the Owner's execution authorization at OG-4.** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** BLOCKED — waiting on OPI-26, P11Q-01, P11Q-05

**RISK CLASS:** HIGH

**EXECUTOR:** HUMAN OPERATOR

**OBJECTIVE:**

The human operator records, outside the repository where they are sensitive and in INFRASTRUCTURE §9 and BACKUP_RECOVERY §7 where they are facts, every operator input that staging needs — the client's grant of access (OPI-26), the VPS's measured disk, usage, operating system and bandwidth period (OPI-02), what the VPS already runs (OPI-03), the second production application's measured use and windows (OPI-27), the provider-side features verified on the panel (OPI-29), the domain and staging's name (OPI-30), the measured U₀ and free space (OPI-21) and the backup directory by role (OPI-22) — and the zero-cost alert channel the operator confirms (OPI-05), so that the rules waiting on them apply and staging can start.

**WHY THIS EXISTS:**

In PC-4 none of these inputs was available: the client had not given the operator access, only the plan's nominal figures were known and what the VPS runs was not ([INFRASTRUCTURE §9](../09-operations/INFRASTRUCTURE.md#9-the-operators-inputs)). Staging — every rehearsal and drill with it — cannot start before the inputs needed before staging are recorded, and go-live waits for all of them (RELEASE_PLAN GLC-02); nominal capacity is not free space (DEP-01); and no approval, gate or plan is evidence that an input exists ([DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-14). DIR-079 CB-05's deployment tooling, CB-06's alert channel and CB-11's retention check each wait on one of them (`DIR-081` OB-10).

**DEPENDS ON:**

- the operator's or the Owner's input OPI-26 recorded ([RELEASE_PLAN §9](../10-release/RELEASE_PLAN.md#9-the-go-live-checklist)) — BLOCKED until recorded
- the Owner's answers to P11Q-01 and P11Q-05 recorded, with what they call for done — a step run or a narrow amendment approved ([TASK_PLAN §8](TASK_PLAN.md#8-owner-questions)) — BLOCKED until then

**BLOCKS:**

- TASK-072 — Deployment definition, health and smoke checks, maintenance mode and mixed-version safeguards
- TASK-075 — Monitoring — the business heartbeat, alerts and the uptime check
- TASK-078 — Staging stack, the two applications' isolation and the provider features
- TASK-082 — Google OAuth clients for staging and production

**PARALLEL SAFE:** YES — the operator's read-only inspection and the facts it records touch no code and no file that a Task able to run beside it changes, so it runs beside the agent Tasks of the foundation

**IN SCOPE:**

- **OPI-26** — the client's grant of access to the VPS, and to the provider's panel where an operation needs it, to the operator and never to an agent (INF-01), recorded as granted, by role; it comes first, since most other inputs are read on the host or the panel
- **OPI-02** — the VPS's measured free disk, current usage, operating system and the bandwidth allowance's period, recorded as figures; the plan's nominal 4 vCPU, 16 GB RAM, 200 GB NVMe and 16 TB are never taken as measured (DEP-01; PERFORMANCE AS-01; INFRASTRUCTURE §8); REF-062's existing VPS at near-zero incremental cost stays the baseline
- **OPI-03** — what the VPS already runs — Docker, Docker Compose, Coolify, Nginx, other services — and its firewall tooling, recorded, none assumed installed; DIR-079 CB-05's rule applied once it is recorded — Docker Compose, or Coolify only where the VPS already runs it (P9Q-02) —, and the shared host proxy that holds the public HTTPS port for both applications recorded for INFRASTRUCTURE §2's design, which WS-11's trusted proxy reads (TM-01's throttling keys, TM-07's links); TASK-072 waits on this input
- **OPI-27** — the second production application's measured CPU, memory, disk and bandwidth, and its own backup, maintenance and deployment windows, recorded as figures and times (INF-07); every capacity figure counts both applications' combined use (INFRASTRUCTURE §8)
- **OPI-29** — the plan's provider-side features and whether each is active, verified on the panel and recorded, none relied on (INF-08); the confirmation that every feature able to act on the host or its data is off, or its reach accepted by the Owner with the client, is GLC-24's, before production data exists (TASK-078, TASK-084)
- **OPI-30** — the application's domain `<APP_DOMAIN>` and the name staging uses, with the TLS certificates they need, kept by the operator and never recorded in the repository (INFRASTRUCTURE §2; §9's "kept"); WS-11's trusted host and DEP-10's redirect URIs of TASK-082 read them
- **OPI-21, staging part** — U₀ and the VPS's free space measured and recorded as figures (BACKUP_RECOVERY §3); DB, F, L and D stay projected from GT-04–GT-06 and the data steward's counts until they are measured after the opening import (GLC-05)
- **OPI-22** — the local backup directory `<BACKUP_DIR>` on a volume the operator names, and its permissions, recorded as a role with the path kept (DEP-02; SX-07); MULTIPLECORP's own, apart from the second application's (BKR-07); the off-server copy's destination is OPI-32, a go-live input (TASK-084)
- **OPI-05** — the zero-cost alert channel the operator confirms, under DIR-079 CB-06: a push channel to the operator's and the Owner's own phones — the recommended bot or another zero-cost channel —, recorded, its token kept as a secret under SX-01, each message a neutral signal name with no S3 or S4 value; its COST_POLICY §4C.4 record written before it is adopted (GLC-03); whether it is a channel the operator does not solely control recorded for the Owner's alerts (H9-06; GAP-041); the in-app notice at the Owner's next login kept as the floor either way, and the optional self-service e-mail reset kept off unless the channel is e-mail and P9 confirms it (DEP-08; INFRASTRUCTURE §6); TT-F01's manual alarm stays until TASK-080 and TASK-081 test the channel; TASK-075 waits on this input
- **GLC-02's staging part** — each input above recorded and the rule it decides applied, its row in INFRASTRUCTURE §9 or BACKUP_RECOVERY §7 moving from "not available" to the recorded fact; an input that cannot be obtained stays owed and keeps this Task BLOCKED, never a PASS (the Owner's condition (5) in session; `DIR-081` OB-10)
- the record's form: by role or as a figure, never a secret, host name, address, port or account name (RELEASE_PLAN §9); the sensitive values held only by the operator, outside the repository and every agent's reach (INF-02; INF-05)

**OUT OF SCOPE:**

- installing, configuring, starting or changing anything on the VPS, its firewall, the shared host proxy or the second application — staging is TASK-078's, production TASK-084's (INF-07; the Owner's condition (6) in session)
- the go-live inputs: OPI-01 and OPI-24 (TASK-083), OPI-04, OPI-08, OPI-23, OPI-28, OPI-31 and OPI-32 (TASK-084), OPI-06 (TASK-082), OPI-07 and OPI-25 (TASK-079), OPI-09 (TASK-085)
- the measured DB, F, L and D after the opening import and the disk-budget check of GLC-05 (TASK-079, TASK-087)
- the alert channel's set-up on the VPS and its end-to-end test (TASK-080, TASK-081); the monitoring tooling (TASK-075)
- adopting any paid service — an option that needs one is recorded under COST_POLICY §4C.2 as an Owner question and never adopted (INF-04)

**REFERENCES:**

- [INFRASTRUCTURE §9](../09-operations/INFRASTRUCTURE.md#9-the-operators-inputs) OPI-02, OPI-03, OPI-05, OPI-26, OPI-27, OPI-29, OPI-30 — what each input is, where it goes and when it is needed
- [BACKUP_RECOVERY §7](../09-operations/BACKUP_RECOVERY.md#7-the-operators-inputs) OPI-21, OPI-22; [§3](../09-operations/BACKUP_RECOVERY.md#3-the-shared-disk-budget) — the terms U₀ and K
- [INFRASTRUCTURE §1](../09-operations/INFRASTRUCTURE.md#1-invariants) INF-01, INF-02, INF-04, INF-05, INF-07, INF-08
- [INFRASTRUCTURE §2](../09-operations/INFRASTRUCTURE.md#2-topology), [§6](../09-operations/INFRASTRUCTURE.md#6-observability) — the alert channel — and [§8](../09-operations/INFRASTRUCTURE.md#8-capacity-and-cost)
- [INFRASTRUCTURE §10](../09-operations/INFRASTRUCTURE.md#10-owner-questions) P9Q-02 and P9Q-03, decided by DIR-079 CB-05 and CB-06
- [RELEASE_PLAN §9](../10-release/RELEASE_PLAN.md#9-the-go-live-checklist) GLC-02, GLC-03 and the operator's inputs table
- [V1_SCOPE](../01-product/V1_SCOPE.md#dependencies-and-evidence-needed) DEP-01, DEP-02, DEP-08
- [REFERENCE_COVERAGE](../01-product/REFERENCE_COVERAGE.md#pdf-capability-inventory) REF-062
- [SECURITY §4](../05-security/SECURITY.md#4-web-and-input-security) WS-11; [§8](../05-security/SECURITY.md#8-threat-model) TM-01, TM-07; [§10](../05-security/SECURITY.md#p9--operations) H9-06
- [BACKUP_RECOVERY §6](../09-operations/BACKUP_RECOVERY.md#the-exercise-held-in-pc-4) TT-F01
- [COST_POLICY](../00-governance/COST_POLICY.md#procedure) §4C.2, §4C.4
- [DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-13, OE-14 — the shared VPS and operational evidence
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (20): DEP-01; DEP-08; REF-062; WS-11; TM-01; TM-07; H9-06; INF-01–INF-02; OPI-02–OPI-03; OPI-05; OPI-21–OPI-22; OPI-26–OPI-27; OPI-29–OPI-30; TT-F01; GLC-02

**EXPECTED MODULES / FILE AREAS:**

- no code; the input rows of INFRASTRUCTURE §9 and BACKUP_RECOVERY §7, with the operator's inputs table of RELEASE_PLAN §9 that restates them, changed under [CHANGE_CONTROL](../00-governance/CHANGE_CONTROL.md#decision-authority) to the recorded facts — by role or as figures —; every sensitive value in the operator's own records outside the repository and the VPS's web root

**BUSINESS RULES:**

- N/A — no business rule; INFRASTRUCTURE §9, BACKUP_RECOVERY §7 and the decisions DIR-079 CB-05, CB-06 and CB-11 state what each input settles, referenced, never restated

**ARCHITECTURE RULES:**

- the rules that wait on these inputs apply as written once their input is recorded, no further Owner decision being needed unless a rule cannot be met (`DIR-081` OB-10): DIR-079 CB-05's tooling with Nginx kept as ARCHITECTURE §1 states it (DIR-083 OE-13); INFRASTRUCTURE §2's shared host proxy; INF-07's isolation of the two applications; DIR-079 CB-06's channel; a rule that cannot be met — no zero-cost channel, a host without room for MULTIPLECORP's isolated stack — goes back to the Owner as a question, never a silent choice

**SECURITY / PERMISSION REQUIREMENTS:**

- access to the VPS and the panel is the client's grant to the operator alone (OPI-26; INF-01, INF-02); no agent receives any part of it (SX-04)
- no secret, host name, address, port, user name, secret path, provider account or key detail enters the repository, a log, an issue or a document — placeholders in angle brackets only (INF-05; SX-01; SX-06); the channel's token is a secret of SX-01 (OPI-05)
- the inspection reads the host and the panel only; it changes no firewall rule, service or setting of either application (INF-07)

**COMPANY SCOPE AND FIELD PROJECTION:**

- N/A — no application data path; the inputs describe the host and its operation, never company data

**CONCURRENCY / IDEMPOTENCY:**

- N/A — no command, lock, job or AX- action; nothing runs on the application

**AUDIT:**

- N/A — no business action and no security event; the operator's record of each input — what was read, by whom by role, and the fact recorded — is kept outside the repository without secrets and summarized in COMPLETION EVIDENCE ([TEST_STRATEGY §16](../08-testing/TEST_STRATEGY.md#16-evidence))

**DATABASE IMPACT ASSESSMENT:**

- Schema change: NONE — no repository change; production data untouched except through the approved path
- Migration required: NO
- Existing data transformation: NONE
- Direct production DB access: FORBIDDEN
- Data-loss risk: NONE KNOWN

**MODULARITY / EXTENSIBILITY:**

- Module boundary affected: none — no code
- Stable core kept provider-neutral: YES — no provider feature is relied on; each is only recorded (INF-08)
- External/vendor-specific code isolated: N/A — no code
- Replaceable adapter/contract required: NO
- Provider lock-in risk: NONE — the hosting provider's features stay unused unless a later Owner decision adopts one (INF-08); the alert channel is replaceable at no cost (OPI-05)
- Future extension impact: LOW
- Speculative abstraction avoided: YES — no tooling is written to collect the inputs

**AUTH IMPACT:**

- Authentication change: NONE
- Authentication implementation: N/A — no code; the profile stays LARAVEL_NATIVE ([EXECUTION_CONTEXT](EXECUTION_CONTEXT.md))
- Auth profile: N/A — not touched
- Google sign-in: N/A — OPI-06 and the clients are TASK-082's; OPI-30's domain and staging name are what their redirect URIs need
- Google registration behavior: N/A — not touched
- Google credentials owner action required: NO
- Authorization change: NONE
- Provider/library-specific leakage into domain: NO

**COST IMPACT:**

- New recurring cost: NONE
- Paid third-party service: NO
- Free/open-source alternative: N/A — nothing paid is considered; OPI-05's channel is zero-cost, its COST_POLICY §4C.4 record written before it is adopted (GLC-03)
- Estimated additional monthly production cost: 0
- Owner approval required: NO

**MCP SESSION PROFILE:**

- Required MCP servers: none — [TOOLCHAIN](../00-governance/TOOLCHAIN.md#mcp-servers) records none installed
- MCP servers that should remain OFF: every server not required
- Native CLI/package preferred over MCP: YES — the framework's and package managers' own commands
- Post-Task deactivation/cleanup: none

**TOOL REQUIREMENTS:**

- Context7: N/A — no library or framework behaviour is used
- Graphify: N/A — no code
- Browser E2E: N/A — no page
- Other: the operator's own access to the VPS and the provider's panel under OPI-26, read-only; the host's own tools for disk, memory, CPU, bandwidth and service listings — nothing installed
- Missing safe tool behavior: N/A — nothing is installed by an agent or on the VPS in this Task; a measurement that needs a tool the host lacks waits for TASK-078, and a paid tool STOPS for the Owner (COST_POLICY §4C.2)

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: N/A — no code
- Pre-change questions: which rule each input decides — DIR-079 CB-05, CB-06 and CB-11, INF-07's isolation — and which Task waits on it — TASK-072 on OPI-03, TASK-075 on OPI-05, TASK-078 on the staging inputs, TASK-082 on OPI-30
- Expected affected areas: the input rows of INFRASTRUCTURE §9, BACKUP_RECOVERY §7 and RELEASE_PLAN §9 only

**CONTEXT7:**

- Topics to verify: N/A — no library behaviour; the host is read as found
- Version-sensitive assumptions: none — OPI-03 records the services and versions the host runs, never assumed

**UI / UX REQUIREMENTS:**

- Follow global design rules: N/A — no page; the operator's records are no user interface
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
- Destructive actions: N/A — no page; nothing on the host is changed
- Redundancy constraints: N/A — no page
- Loading state: N/A — no page
- Empty state: N/A — no page
- Error state: N/A — no page; an input not available is recorded as owed

**ROUTE IMPACT:**

- none — no route; OPI-30's domain and staging name are the hosts the trusted-host configuration of WS-11 will accept (TASK-072)

**INTERACTION CONTRACTS:**

- N/A — no page interaction

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

1. OPI-26 is recorded as granted to the operator, by role, and no agent holds a credential, shell, panel session, backup file or log of the VPS (INF-01).
2. OPI-02, OPI-27 and OPI-21's U₀ and free space are recorded as measured figures — the second application's use beside MULTIPLECORP's —, and none of them is the plan's nominal figure (DEP-01; INFRASTRUCTURE §8).
3. OPI-03 is recorded with the shared host proxy, and DIR-079 CB-05's rule is applied to it: Docker Compose, or Coolify only where the VPS already runs it, with Nginx kept.
4. Every provider-side feature of OPI-29 is recorded as verified on the panel, active or not, and no step of any later Task relies on one (INF-08).
5. OPI-22 is recorded by role with its path kept, apart from the second application's backups (BKR-07); OPI-30's domain and staging name appear nowhere in the repository.
6. OPI-05 is recorded with its COST_POLICY §4C.4 record written before adoption, its token kept outside the repository, the in-app floor kept and the e-mail reset left off unless the channel is e-mail (H9-06; DEP-08).
7. The secret-like scan over the changed rows finds no secret, host name, address, port or account name (INF-05; SM-15).
8. An input that cannot be obtained is recorded as owed and the Task stays BLOCKED on it; no input is recorded as available without the operator's record (DIR-083 OE-14).

**TEST REQUIREMENTS:**

- Lint: N/A — no code in this Task
- Typecheck: N/A — no code in this Task
- Unit: N/A — no domain logic in this Task
- Feature: N/A — no route or command in this Task
- Integration: N/A — no database, queue or job path in this Task
- Authorization: N/A — no route, query class or command; the host's access is the client's grant to the operator (OPI-26)
- Route integrity: N/A — no route added or changed
- Interaction: N/A — no INT- contract in this Task
- Browser E2E: N/A — no page in this Task
- Localization/language switch: N/A — no user-facing copy in this Task
- Visual breathing room/density: N/A — no page in this Task
- Responsive: N/A — no page in this Task
- Accessibility: N/A — no page in this Task
- Production build: N/A — no build in this Task
- Security: the secret-like scan of SM-15 over the changed rows (TL-14); INF-01 held — no agent access granted
- Performance: N/A — no target is measured; OPI-02 and OPI-27 are the inputs of TASK-079's PFM-13 run and the disk budget
- Migration: N/A — no migration or import code in this Task
- Other: each recorded row checked against INFRASTRUCTURE §9's "Where it goes" — recorded, recorded by role, kept — and against GLC-02's staging list

**TEST CHECKLIST:**

- [ ] Lint — N/A: no code in this Task
- [ ] Typecheck — N/A: no code in this Task
- [ ] Relevant automated tests — N/A: no code; the operator's record of each input — OPI-02, OPI-03, OPI-05, OPI-21's staging part, OPI-22, OPI-26, OPI-27, OPI-29, OPI-30 — stands in their place
- [ ] Authorization — the client's grant recorded by role (OPI-26); no access given to an agent (INF-01)
- [ ] Route integrity — N/A: no route
- [ ] Interaction behavior — N/A: no interaction
- [ ] Browser E2E if required — N/A: no page
- [ ] Responsive/adaptive if required — N/A: no page
- [ ] Visual breathing room/density if UI — N/A: no page
- [ ] Production build — N/A: no build
- [ ] Security checks if required — the secret-like scan of the changed rows passed (SM-15); OPI-30 and the channel's token kept outside the repository
- [ ] Database safety review — no database read or changed; the host and the panel read only
- [ ] Final diff review — only the input rows of INFRASTRUCTURE §9, BACKUP_RECOVERY §7 and RELEASE_PLAN §9 changed, each a fact by role or figure

**COMPLETION EVIDENCE:**

- Test outputs: the operator's record of each input — the input, where it was read, by role, and the fact recorded —, kept outside the repository without secrets and summarized here in [TEST_STRATEGY §16](../08-testing/TEST_STRATEGY.md#16-evidence)'s form; only recorded evidence passes (DIR-083 OE-14)
- Build result: N/A — no build in this Task
- Route evidence: N/A — no route added or changed
- Interaction evidence: N/A — no INT- contract in this Task
- Browser evidence: N/A — no journey in this Task
- Localization evidence: N/A — no page in this Task
- Cost evidence/exception if applicable: ADDITIONAL RECURRING COST CHANGE: NONE (DOD-20); OPI-05's COST_POLICY §4C.4 record
- Files changed: the input rows of INFRASTRUCTURE §9 and BACKUP_RECOVERY §7, and RELEASE_PLAN §9's table that restates them — nothing else
- Commit SHA: the commit that records them, under the Owner's identity, with no AI attribution
- Notes: each non-applicable item with its reason (DOD-03); an input not available named as owed, never as a PASS; the DONE update of TASK_PLAN, this file and CURRENT_HANDOFF (AICWDF §22.3), made on the operator's record and never on an agent's claim (TASK_PLAN §2)

**SAFE NEXT TASK:**

- TASK-072 — when its other dependencies are DONE and its READY gate holds

**DO NOT DO:**

- add to V1 — a capability, page, route, field, integration, document type, report or paid service — or drop, defer or narrow a V1_REQUIRED requirement; a Task that would need either raises an Owner question and waits ([DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-20)
- let an agent access the VPS, production, its data or a credential, or put a secret, real data, a host detail or production configuration in the repository ([INFRASTRUCTURE](../09-operations/INFRASTRUCTURE.md) INF-01, INF-05; SECURITY SX-01, SX-04, SX-06; [PRODUCTION_DATA_SAFETY](../00-governance/PRODUCTION_DATA_SAFETY.md))
- add AI, model or tool attribution to a commit — the Owner's configured identity only ([AGENTS.md](../../AGENTS.md#git-commit-attribution); DIR-022)
- start before the Owner's execution authorization at OG-4, or mark DONE without the required tests passing and the completion evidence ([TASK_PLAN](TASK_PLAN.md#2-rules-of-the-plan); [DEFINITION_OF_DONE](../08-testing/DEFINITION_OF_DONE.md#2-the-checklist))
- weaken, skip or delete a valid test to obtain a green run, or adopt a paid tier without the Owner's COST_POLICY decision (DOD-05; [COST_POLICY](../00-governance/COST_POLICY.md))
- let an agent run any step of this Task or reach the VPS, the panel or a record of them — the operator alone works on the host, under the client's grant (INF-01; OPI-26)
- write the domain, a host name, an address, a port, an account name, a path or a token into the repository, an issue or a chat (INF-05; RELEASE_PLAN §9)
- install, configure, start or stop anything on the VPS, change its firewall or the shared host proxy, or touch the second application in this Task (INF-07)
- take the plan's nominal figures as measured, or record an input as available without the operator's record of it (DEP-01; DIR-083 OE-14)

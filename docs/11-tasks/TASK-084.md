# TASK-084 — Production preparation — configuration, outbound HTTPS, branch protection, backups and the off-server copy

Status: REVIEW | Updated: 2026-10-20 | Owner: Planning

Written by WF-PC5-W's writer of bundle W4 of run PC-5 on 2026-10-19 and 2026-10-20 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99 — a REVIEW draft of P11, approved, or not, with the [Task plan](TASK_PLAN.md) at OG-4. **No Task starts before the Owner's execution authorization at OG-4.** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** BLOCKED — waiting on OPI-04, OPI-28, OPI-31, OPI-32, P11Q-05

**RISK CLASS:** HIGH

**EXECUTOR:** HUMAN OPERATOR

**OBJECTIVE:**

Before any production data exists, the human operator prepares production on the VPS from TASK-072's definition and records the go-live inputs this needs: the production configuration validated read-only, outbound HTTPS to Google and to the breached-password range service tested, the backups scheduled at the Owner-approved time with the first verified backup and the disk thresholds set, the off-server copy in place with its keys apart from the VPS and drilled once, the provider features able to act on the host confirmed off and TASK-080's monitoring made live on production — with the Owner protecting `main` and the provider account's holder confirming its second factor — each passed only by its recorded evidence.

**WHY THIS EXISTS:**

The cutover sets production up before any data, GLC-06, GLC-07, GLC-11 and GLC-24 met first ([RELEASE_PLAN §6](../10-release/RELEASE_PLAN.md#6-cutover-and-rollback) CUT-02). CAP-16, AC-18 and AC-19 need production's backups with verified retention and failure handling; the Owner's [DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-09 adds the encrypted off-server copy, drilled before go-live, its key kept apart from the VPS — GAP-063 open until then and the architecture challenge's sequence 13b —; whoever holds the provider account can reach, snapshot or delete the VPS and its backups (OPI-28; GAP-018); and the operator's inputs not available in PC-4 are go-live obligations, never a PASS (OE-14).

**DEPENDS ON:**

- TASK-073 — Release process — branch protection, release tags, the release record and integrity report
- TASK-074 — Backup tooling, the off-server copy and the drill procedures
- TASK-079 — Staging measurements — capacity, Argon2id, durations and the disk budget
- TASK-081 — Staging drills and rehearsals — failure, restore, Scenario B and the import rehearsal
- the operator's or the Owner's inputs OPI-04, OPI-28, OPI-31, OPI-32 recorded ([RELEASE_PLAN §9](../10-release/RELEASE_PLAN.md#9-the-go-live-checklist)) — BLOCKED until recorded
- the Owner's answer to P11Q-05 recorded, with what it calls for done — a step run or a narrow amendment approved ([TASK_PLAN §8](TASK_PLAN.md#8-owner-questions)) — BLOCKED until then

**BLOCKS:**

- TASK-085 — Installation, the first Owner, two-step enrolment and the APP_KEY rotation

**PARALLEL SAFE:** NO — it prepares the production host that TASK-085's installation and every later production step use

**IN SCOPE:**

- **Its six inputs**, each recorded in INFRASTRUCTURE §9 or BACKUP_RECOVERY §7 by role or as a fact, never as a secret, host name, address, port or account name — the Task BLOCKED until OPI-04, OPI-28, OPI-31 and OPI-32, which others give first, are recorded, while OPI-08 and OPI-23 it records itself, OPI-08 by GLC-08's test and OPI-23 by the operator's proposal with the Owner's approval (TASK_PLAN §2):
  - OPI-04 — the domain's DNS provider and whether a proxy fronts it, recorded by the operator; DIR-079 CB-08's rule applied — DNS at the current provider with TLS on the VPS, or, where an edge proxy already fronts the domain, that proxy with WS-11's trusted-proxy configuration (P9Q-05)
  - OPI-08 — outbound HTTPS from the VPS to Google and to the breached-password range service, recorded as tested by the operator (GLC-08 below)
  - OPI-23 — the backup time `<BACKUP_TIME>`, proposed by the operator — 03:00 WIB recommended, after `ReconcileGuards`' 02:00 run — checked against the second application's windows (OPI-27) and the measured duration (OPI-25), and approved by the Owner (GLC-22)
  - OPI-28 — who holds the provider account, by role — the client — and whether its sign-in uses a second factor, confirmed by its holder (GLC-22; GAP-018)
  - OPI-31 — every holder of administrative access to the host or to the provider account, the second application's operators included, recorded by role (GAP-041; GAP-018)
  - OPI-32 — the off-server copy's destination, a device or storage the Owner or the client already holds, and the custody of the keys kept apart from the VPS, recorded by role and never by access details (BKR-09)
- **Production brought up** from TASK-072's definition and the release's tagged commit of `main` (INF-03), MULTIPLECORP's own project sharing only the host (INF-07), the release's expand-only migrations run by the operator through the migration connection (INFRASTRUCTURE §5 step 4)
- **GLC-07**, validated by the operator on production, read-only, before CUT-02 and again at every release (REL-11): SX-03 in full — `APP_DEBUG=false`, `APP_ENV=production`, no debug or profiling tooling, cached configuration, a health endpoint without internals, PostgreSQL and Valkey private with authentication, the runtime database role —, WS-10's gates (TM-27), DATABASE §28's roles and privileges (H9-01), the firewall and bindings of H9-09, secrets present with restricted permissions, TLS and HSTS, the headers SM-08 names, and the go-live setting unset until CUT-07
- **GLC-08** (DEP-11; H9-13): outbound HTTPS from the VPS to Google and to the breached-password range service tested, within the timeouts of AU-03 and AU-22
- **GLC-06** (BK-04; BK-07; BK-11; DIR-079 CB-13, CB-14): TASK-074's backups scheduled daily at the approved time, outside business hours; the first verified backup taken — the dump and its files coherent (BK-01; BK-02), the manifest verified (BK-07), rotation, retention and cleanup by the tooling (BK-05; BK-06; BK-12); the disk thresholds set from TASK-079's budget; the backup duty named — the Owner, as OPI-24 records it, its alternate being named in TASK-083 (GLC-21); backup files readable only by the operator account (SX-07; TM-65)
- **GLC-20** (BKR-09; INF-06; DIR-083 OE-09; GAP-063; TT-F20): the off-server copy in place — each daily generation, once verified, copied encrypted to OPI-32's destination outside the VPS and its provider account, its keys kept apart from the VPS and never with the copy (architecture challenge 13b), its retention DIR-079 CB-11's as far as the destination allows — and one copied generation brought back with its keys and restored into isolation by BACKUP_RECOVERY §4 in the copy's drill, never touching the second application, its record kept; no recurring cost
- **GLC-24** (OPI-29; INF-08): every provider feature able to act on the host or its data — automation or AI assistance among them — confirmed off before production data exists, or its reach recorded and accepted by the Owner with the client, re-checked here after TASK-078's confirmation
- **Monitoring live on production** (GLC-11's production half; CUT-02): TASK-080's heartbeat with its alerts, the external uptime check and the log viewer running on production before any production data exists
- **GLC-12**: `main` protected by the Owner with TASK-073's settings — required CI, the Owner's approval of every pull request, no force push
- **GLC-22**: the backup time approved by the Owner (OPI-23) and the provider account's second factor confirmed by its holder (OPI-28)
- **GLC-02's go-live part**: these inputs recorded and their rules applied, beside TASK-071's staging inputs
- **What the evidence covers**: production's backups meet BK-01–BK-12 with recorded evidence (CAP-16; AC-19; DEP-02); the timed restore of a production recovery point is GLC-04's, after the import (TASK-087; AC-18); no recovery after total VPS loss is promised, RISK-001 narrowed by the copy (OS-04; REF-060; OWN-06); the operator's failure detection stays distinct from REF-048's in-app alerts; the existing VPS stays the baseline at no new cost (REF-062)
- **INF-01**: the operator alone works on production; no agent holds a credential, shell, connection, backup or log of it
- **The record**: each check's result by role, without secrets, in TEST_STRATEGY §16's form
- **The private records in place** (GLC-16; TT-F03; TT-F12): the private incident record and the private contact record of the Owners, from TASK-076's blank forms, kept outside the repository by the operator and the Owner, in place before go-live — GLC-16's part that no earlier Task holds (placed here in Part 4)

**OUT OF SCOPE:**

- the installation step, the first Owner, two-step enrolment and the `APP_KEY` rotation (TASK-085)
- any business data, the opening import and the cutover (TASK-087), and the production restore drill after the import (GLC-04; TASK-087)
- the staging measurements and drills (TASK-079, TASK-081)
- any change to the second application or to a firewall rule that is not MULTIPLECORP's; any paid destination or service (INF-07; COST_POLICY §4C.2)

**REFERENCES:**

- [INFRASTRUCTURE §1](../09-operations/INFRASTRUCTURE.md#1-invariants) INF-01, INF-03, INF-06–INF-08; [§2](../09-operations/INFRASTRUCTURE.md#2-topology); [§4.3](../09-operations/INFRASTRUCTURE.md#43-secrets-and-keys); [§4.4](../09-operations/INFRASTRUCTURE.md#44-database-roles); [§5](../09-operations/INFRASTRUCTURE.md#5-the-deployment-path); [§7](../09-operations/INFRASTRUCTURE.md#7-security-operations); [§9](../09-operations/INFRASTRUCTURE.md#9-the-operators-inputs) OPI-04, OPI-08, OPI-28, OPI-29, OPI-31; [§10](../09-operations/INFRASTRUCTURE.md#10-owner-questions) P9Q-05 (DIR-079 CB-08)
- [BACKUP_RECOVERY §1](../09-operations/BACKUP_RECOVERY.md#1-the-decision-and-its-limits) BKR-08, BKR-09; [§2](../09-operations/BACKUP_RECOVERY.md#2-the-twelve-obligations); [§3](../09-operations/BACKUP_RECOVERY.md#3-the-shared-disk-budget); [§5](../09-operations/BACKUP_RECOVERY.md#5-restore-testing); [§7](../09-operations/BACKUP_RECOVERY.md#7-the-operators-inputs) OPI-23, OPI-24, OPI-32; [the exercise held in PC-4](../09-operations/BACKUP_RECOVERY.md#the-exercise-held-in-pc-4) TT-F20
- [RELEASE_PLAN §2](../10-release/RELEASE_PLAN.md#2-the-branch-and-environment-model); [§6](../10-release/RELEASE_PLAN.md#6-cutover-and-rollback) CUT-02; [§9](../10-release/RELEASE_PLAN.md#9-the-go-live-checklist) GLC-02, GLC-06–GLC-08, GLC-11, GLC-12, GLC-20, GLC-22, GLC-24
- [V1_SCOPE](../01-product/V1_SCOPE.md#must-ship) CAP-16; [dependencies](../01-product/V1_SCOPE.md#dependencies-and-evidence-needed) DEP-02, DEP-11; [the local-backup contract](../01-product/V1_SCOPE.md#v1-local-backup-and-p9-handoff-contract) BK-01–BK-12
- [ACCEPTANCE_CRITERIA](../01-product/ACCEPTANCE_CRITERIA.md#product-acceptance-contracts) AC-18, AC-19; [operational success](../01-product/ACCEPTANCE_CRITERIA.md#operational-success-and-go-live-evidence) OS-04
- [REFERENCE_COVERAGE](../01-product/REFERENCE_COVERAGE.md#pdf-capability-inventory) REF-048, REF-060, REF-062; [final Owner decision coverage](../01-product/REFERENCE_COVERAGE.md#final-owner-decision-coverage) OWN-06
- [SECURITY §4](../05-security/SECURITY.md#4-web-and-input-security) WS-10, WS-11; [§7](../05-security/SECURITY.md#7-secrets-production-boundary-and-supply-chain) SX-03, SX-07; [§8](../05-security/SECURITY.md#8-threat-model) TM-27, TM-65; [§10](../05-security/SECURITY.md#p9--operations) H9-01, H9-09, H9-13
- [SECURITY_TEST_MATRIX §9](../08-testing/SECURITY_TEST_MATRIX.md#9-where-each-case-runs) — no case on production
- [GAP_REGISTER](../00-governance/GAP_REGISTER.md#gap-063--an-off-server-backup-against-total-vps-loss-awaits-the-owners-decision) GAP-063; [GAP-018](../00-governance/GAP_REGISTER.md#gap-018--owner-accepts-total-vps-or-storage-loss-exposure-with-local-only-backup); [GAP-041](../00-governance/GAP_REGISTER.md#gap-041--operator-abuse-of-the-owner-recovery-command)
- [DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-09, OE-13, OE-14; [COST_POLICY](../00-governance/COST_POLICY.md#procedure) §4C.2
- Architecture challenge — the sequence 13b of the [P11 gate record](evidence/P11_QUALITY_GATE.md#whole-system-architecture-challenge-sp-98), their assertions carried into this Task's acceptance criteria and tests
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (53): O-07; CAP-16; DEP-02; DEP-11; BK-01–BK-12; AC-18–AC-19; OS-04; REF-060; REF-062; OWN-06; WS-10; SX-03; SX-07; TM-27; TM-65; HO-28; INF-01; INF-06; INF-08; OPI-04; OPI-08; OPI-23; OPI-28–OPI-29; OPI-31–OPI-32; BKR-09; TT-F03; TT-F12; TT-F20; GLC-02; GLC-06–GLC-08; GLC-11–GLC-12; GLC-16; GLC-20; GLC-22; GLC-24; GAP-063

**EXPECTED MODULES / FILE AREAS:**

- no code; the six input rows of INFRASTRUCTURE §9 and BACKUP_RECOVERY §7, with RELEASE_PLAN §9's table that restates them, changed under [CHANGE_CONTROL](../00-governance/CHANGE_CONTROL.md#decision-authority) by role; production's configuration, secrets, keys and destination held on the VPS or apart from it by the operator, never in the repository

**BUSINESS RULES:**

- N/A — no business rule; production holds no business data in this Task

**ARCHITECTURE RULES:**

- production comes from the tagged commit of `main` by TASK-072's definition (INF-03); MULTIPLECORP's own project only (INF-07)
- backups stay on the VPS with the one off-server copy the Owner decided and no other external destination (INF-06; BACKUP_RECOVERY §1); no provider feature is relied on (INF-08)

**SECURITY / PERMISSION REQUIREMENTS:**

- SX-03's production settings and WS-10's gates hold before any data (TM-27); secrets present with restricted permissions, readable only by the service account (SX-01; H9-09)
- the copy's decrypting key and a copy of each generation's paired key kept apart from the VPS in the operator's secret store, and never with the copy — the latest generation's paired key being also the live `APP_KEY` in the VPS's environment (BKR-08, BKR-09; SX-01; TM-65)
- the provider account's second factor confirmed and every holder of administrative access recorded by role (GAP-018; GAP-041); no agent holds any production access (INF-01; SX-04)

**COMPANY SCOPE AND FIELD PROJECTION:**

- N/A — no business data exists in production during this Task; scope and projection are the application's, unchanged

**CONCURRENCY / IDEMPOTENCY:**

- N/A — no command or lock; the daily backup runs clear of `ReconcileGuards`' 02:00 WIB snapshot (HO-28) and never overlaps the second application's windows (BKR-07)

**AUDIT:**

- N/A — no business action; each check's record is the operator's, by role and without secrets, and the Owner's branch protection and the holder's second-factor confirmation are recorded with it

**DATABASE IMPACT ASSESSMENT:**

- Schema change: NONE — no repository change; production data untouched except through the approved path
- Migration required: NO — no new migration; the release's expand-only migrations run by the operator through the migration connection at production's first deployment (INFRASTRUCTURE §5 step 4)
- Existing data transformation: NONE
- Direct production DB access: FORBIDDEN
- Data-loss risk: NONE KNOWN

**MODULARITY / EXTENSIBILITY:**

- Module boundary affected: none — no code
- Stable core kept provider-neutral: YES — no provider backup, snapshot or automation feature is relied on (INF-08)
- External/vendor-specific code isolated: N/A — no code
- Replaceable adapter/contract required: NO
- Provider lock-in risk: NONE — the off-server copy sits outside the VPS and its provider account (BKR-09)
- Future extension impact: LOW
- Speculative abstraction avoided: YES — the definition and tooling as built, no extra service

**AUTH IMPACT:**

- Authentication change: NONE
- Authentication implementation: N/A — no code; the profile stays LARAVEL_NATIVE ([EXECUTION_CONTEXT](EXECUTION_CONTEXT.md))
- Auth profile: N/A — not touched; the breached-password range service's reachability tested for AU-03 (GLC-08)
- Google sign-in: N/A — outbound HTTPS to Google tested (GLC-08); the production client is TASK-082's
- Google registration behavior: N/A — not touched
- Google credentials owner action required: NO
- Authorization change: NONE
- Provider/library-specific leakage into domain: NO

**COST IMPACT:**

- New recurring cost: NONE
- Paid third-party service: NO
- Free/open-source alternative: N/A — nothing paid is considered; the copy's destination is a device or storage already held, and a paid one STOPS for the Owner (BKR-09; COST_POLICY §4C.2)
- Estimated additional monthly production cost: 0
- Owner approval required: NO

**MCP SESSION PROFILE:**

- Required MCP servers: none — [TOOLCHAIN](../00-governance/TOOLCHAIN.md#mcp-servers) records none installed
- MCP servers that should remain OFF: every server not required
- Native CLI/package preferred over MCP: YES — the framework's and package managers' own commands
- Post-Task deactivation/cleanup: none

**TOOL REQUIREMENTS:**

- Context7: N/A — the operator follows TASK-072's definition and runbook and TASK-074's tooling as built
- Graphify: N/A — no code
- Browser E2E: N/A — no page
- Other: the operator's access under OPI-26; TASK-073's branch-protection settings list for the Owner; TASK-080's monitoring configuration; a TLS and header check and outbound HTTPS tests run from the VPS
- Missing safe tool behavior: N/A — no agent installs anything; the operator installs only what TASK-072's definition names, and a paid tier STOPS for the Owner (COST_POLICY §4C.2)

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: N/A — no code
- Pre-change questions: what OPI-04 records about the domain's DNS and proxy; the second application's windows for the backup time (OPI-27); the destination OPI-32 names
- Expected affected areas: production's MULTIPLECORP project on the VPS and the input rows only

**CONTEXT7:**

- Topics to verify: N/A — no library is configured beyond TASK-072's definition
- Version-sensitive assumptions: none — production runs the release's tagged commit as built

**UI / UX REQUIREMENTS:**

- Follow global design rules: N/A — no page is built or changed
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
- Destructive actions: N/A — no page; the copy's drill restores into isolation only
- Redundancy constraints: N/A — no page is built or changed
- Loading state: N/A — no page is built or changed
- Empty state: N/A — no page is built or changed
- Error state: N/A — no page; a failed check stops production's preparation for the operator's decision (RBK-04)

**ROUTE IMPACT:**

- none — production serves the release's routes as built

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

1. OPI-04, OPI-08, OPI-23, OPI-28, OPI-31 and OPI-32 are recorded by role or as facts without a secret and their rules applied — DIR-079 CB-08's for OPI-04 —; the Task is BLOCKED until the four others give first are recorded, and DONE only when all six are (GLC-02; TASK_PLAN §2).
2. The production configuration is validated read-only — SX-03 in full, WS-10's gates, the database roles and privileges, the firewall and bindings, secrets with restricted permissions, TLS and HSTS, the headers SM-08 names, and the go-live setting unset (GLC-07).
3. Outbound HTTPS from the VPS to Google and to the breached-password range service passes (GLC-08; DEP-11).
4. The daily backups run at the Owner-approved time, the first verified backup exists, the disk thresholds are set, the backup duty is named, and backup files are readable only by the operator account (GLC-06; GLC-22; SX-07).
5. Each daily generation is copied encrypted to OPI-32's destination outside the VPS and its provider account, with every decrypting key kept apart from the VPS, and one copied generation has been restored in a drill without touching the second application (GLC-20; BKR-09; architecture challenge 13b).
6. Every provider feature able to act on the host or its data is confirmed off, or its reach accepted by the Owner with the client, before production data exists (GLC-24).
7. `main` is protected by the Owner — required CI, the Owner's approval, no force push — and the provider account's second factor is confirmed by its holder (GLC-12; GLC-22).
8. TASK-080's monitoring runs on production before any production data exists (CUT-02).
9. No agent held any production access, and no secret, host detail or account name entered the repository (INF-01; INF-05).

**TEST REQUIREMENTS:**

- Lint: N/A — no code in this Task
- Typecheck: N/A — no code in this Task
- Unit: N/A — no code in this Task
- Feature: N/A — no route or command in this Task
- Integration: N/A — no code; GLC-07's read-only validation and the backup and copy runs on production stand in its place
- Authorization: N/A — no route; the database roles' privileges checked read-only (H9-01)
- Route integrity: N/A — no route added or changed
- Interaction: N/A — no INT- contract in this Task
- Browser E2E: N/A — no page in this Task
- Localization/language switch: N/A — no user-facing copy in this Task
- Visual breathing room/density: N/A — no page in this Task
- Responsive: N/A — no page in this Task
- Accessibility: N/A — no page in this Task
- Production build: N/A — production runs the release's tagged commit as CI built it (TL-13)
- Security: no SECURITY_TEST_MATRIX case runs on production (§9); GLC-07's read-only validation is the operator's check (REL-11)
- Performance: N/A — no target is measured on production here
- Migration: N/A — no new migration; the release's migrations run at production's first deployment by the operator
- Other: the copy drill's record — the generation, its checksums at the destination, the restore into isolation and the reconciliation

**TEST CHECKLIST:**

- [ ] Lint — N/A: no code in this Task
- [ ] Typecheck — N/A: no code in this Task
- [ ] Relevant automated tests — N/A: no code; the operator's records of GLC-06, GLC-07, GLC-08, GLC-20, GLC-24 and the monitoring live, and the Owner's of GLC-12 and GLC-22, stand in their place
- [ ] Authorization — the database roles and privileges validated read-only; no agent access to production
- [ ] Route integrity — N/A: no route added or changed
- [ ] Interaction behavior — N/A: no interaction built or changed
- [ ] Browser E2E if required — N/A: no page
- [ ] Responsive/adaptive if required — N/A: no page
- [ ] Visual breathing room/density if UI — N/A: no page
- [ ] Production build — N/A: production runs the release's tagged commit as CI built it
- [ ] Security checks if required — GLC-07's read-only validation recorded; the copy's keys apart from the VPS; the provider account's second factor confirmed
- [ ] Database safety review — no business data yet; the copy's drill restored into isolation; nothing of the second application read or written
- [ ] Final diff review — only the six input rows changed, by role and without a secret, host detail or account name

**COMPLETION EVIDENCE:**

- Test outputs: the operator's record of each check — GLC-07's items one by one, GLC-08's tests, the backup schedule and the first verified backup, the thresholds, the copy in place and its drill, GLC-24's confirmations, the monitoring live — and the Owner's records of GLC-12 and GLC-22, in [TEST_STRATEGY §16](../08-testing/TEST_STRATEGY.md#16-evidence)'s form without secrets; only recorded evidence passes (DIR-083 OE-14)
- Build result: N/A — no build in this Task; the deployed tagged commit is named in the record
- Route evidence: N/A — no route added or changed
- Interaction evidence: N/A — no INT- contract in this Task
- Browser evidence: N/A — no journey in this Task
- Localization evidence: N/A — no page in this Task
- Cost evidence/exception if applicable: ADDITIONAL RECURRING COST CHANGE: NONE (DOD-20) — the copy's destination already held
- Files changed: the input rows OPI-04, OPI-08, OPI-23, OPI-28, OPI-31 and OPI-32, with RELEASE_PLAN §9's restating table — nothing else
- Commit SHA: the commit that records them, under the Owner's identity, with no AI attribution
- Notes: each non-applicable item with its reason (DOD-03); while an input is not recorded the Task stays BLOCKED; GLC-04's production restore drill left to TASK-087; the DONE update of TASK_PLAN, this file and CURRENT_HANDOFF (AICWDF §22.3), made on the operator's and the Owner's records and never on an agent's claim (TASK_PLAN §2)

**SAFE NEXT TASK:**

- TASK-085 — when its other dependencies are DONE and its READY gate holds

**DO NOT DO:**

- add to V1 — a capability, page, route, field, integration, document type, report or paid service — or drop, defer or narrow a V1_REQUIRED requirement; a Task that would need either raises an Owner question and waits ([DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-20)
- let an agent access the VPS, production, its data or a credential, or put a secret, real data, a host detail or production configuration in the repository ([INFRASTRUCTURE](../09-operations/INFRASTRUCTURE.md) INF-01, INF-05; SECURITY SX-01, SX-04, SX-06; [PRODUCTION_DATA_SAFETY](../00-governance/PRODUCTION_DATA_SAFETY.md))
- add AI, model or tool attribution to a commit — the Owner's configured identity only ([AGENTS.md](../../AGENTS.md#git-commit-attribution); DIR-022)
- start before the Owner's execution authorization at OG-4, or mark DONE without the required tests passing and the completion evidence ([TASK_PLAN](TASK_PLAN.md#2-rules-of-the-plan); [DEFINITION_OF_DONE](../08-testing/DEFINITION_OF_DONE.md#2-the-checklist))
- weaken, skip or delete a valid test to obtain a green run, or adopt a paid tier without the Owner's COST_POLICY decision (DOD-05; [COST_POLICY](../00-governance/COST_POLICY.md))
- put business data on production before GLC-06, GLC-07, GLC-11 and GLC-24 hold (CUT-02)
- keep the copy's decrypting key on the VPS, a generation's paired key only on the VPS, or either key with the copy, or adopt a paid destination (BKR-08, BKR-09; COST_POLICY §4C.2)
- run a test case, a load or a write on production beyond the operator's read-only checks and the copy's drill into isolation (SECURITY_TEST_MATRIX §9; BACKUP_RECOVERY §5)
- record an input or a go-live item as passed without its evidence, or change a firewall rule of the second application (DIR-083 OE-14; INF-07)

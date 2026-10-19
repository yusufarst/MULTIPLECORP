# TASK-072 — Deployment definition, health and smoke checks, maintenance mode and mixed-version safeguards

Status: REVIEW | Updated: 2026-10-20 | Owner: Planning

Written by WF-PC5-W's writer of bundle W4 of run PC-5 on 2026-10-19 and 2026-10-20 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99 — a REVIEW draft of P11, approved, or not, with the [Task plan](TASK_PLAN.md) at OG-4. **No Task starts before the Owner's execution authorization at OG-4.** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** BLOCKED — waiting on OPI-03, P11Q-02

**RISK CLASS:** HIGH

**EXECUTOR:** AGENT

**OBJECTIVE:**

Prepare in the repository, with placeholder values only, everything the human operator needs to deploy MULTIPLECORP by INFRASTRUCTURE §5: the deployment definition for the tooling DIR-079 CB-05 settles once OPI-03 is recorded — MULTIPLECORP's own containers, its Nginx site file, PostgreSQL and Valkey on a private network, the workers and the scheduler, the environment template and the database roles' connections —, the staging stack's definition and synthetic seed, the minimal health check and the read-only smoke checks of the business heartbeat, the maintenance mode on every web process, the mixed-version safeguards with their tests, and the operator's release runbook for REL-08–REL-13. No agent deploys anything.

**WHY THIS EXISTS:**

RELEASE_PLAN PRQ-01–PRQ-05 ask P11 for the deployment definition and release runbook, the staging stack, the heartbeat's health and smoke checks, the mixed-version safeguards and the drain ([RELEASE_PLAN §10](../10-release/RELEASE_PLAN.md#10-production-release-requirements-for-p11)). GAP-012 warns that an old worker can run against a changed schema or payload and that a code rollback never undoes recorded stock or issued documents; GAP-013 that a green HTTP check can hide stalled business work; and the VPS is shared with the client's second production application, which no MULTIPLECORP step may touch ([DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-13; INF-07) — the architecture challenge's sequences 11a and 16a.

**DEPENDS ON:**

- TASK-003 — CI pipeline, test layers and the synthetic fixture harness
- TASK-071 — The operator's inputs before staging
- the operator's or the Owner's input OPI-03 recorded ([RELEASE_PLAN §9](../10-release/RELEASE_PLAN.md#9-the-go-live-checklist)) — BLOCKED until recorded
- the Owner's answer to P11Q-02 recorded and the narrow amendment it authorizes approved ([TASK_PLAN §8](TASK_PLAN.md#8-owner-questions)) — BLOCKED until then

**BLOCKS:**

- TASK-074 — Backup tooling, the off-server copy and the drill procedures
- TASK-075 — Monitoring — the business heartbeat, alerts and the uptime check
- TASK-077 — Cutover tooling — the numbering go-live command, the post-import refresh and the rehearsal procedure
- TASK-078 — Staging stack, the two applications' isolation and the provider features

**PARALLEL SAFE:** NO — it defines the deployment, the environment template and the migration checks that TASK-074, TASK-075, TASK-077 and TASK-078 build on

**IN SCOPE:**

- **The input it waits on.** OPI-03 — what the VPS already runs, Docker, Docker Compose, Coolify, Nginx, other services, and its firewall tooling — is recorded by the operator in TASK-071, in INFRASTRUCTURE §9's row; this Task stays BLOCKED until it is recorded, since CB-05's choice of tooling and the shared host proxy's part of §2 depend on it
- **The deployment definition** for CB-05's tooling — Docker Compose, or Coolify only where OPI-03 records that the VPS already runs it — with placeholder values only (PRQ-01): MULTIPLECORP's own project of Nginx, the application with its web workers, two queue workers and the scheduler, PostgreSQL and Valkey, with its own containers, volumes, network, storage and secrets, sharing only the host with the second application (INF-07; INFRASTRUCTURE §2, §4.1); memory and CPU limits on each container as configuration whose values TASK-079's measurements fill (SZ-01–SZ-04)
- **Nginx**, MULTIPLECORP's own site file: plain HTTP redirected, HSTS in WS-06's stages — `max-age=300`, then one week, then one year with `includeSubDomains` once every subdomain serves HTTPS —, the static headers of WS-08, version banners off, the proxy rate limit on the login endpoint that bounds total concurrency (H9-02; TM-50; TM-58), request logs without the credential-link paths and query strings or the Google callback's query string (LG-04; H8-20); the shared host proxy's part as OPI-03 records it — MULTIPLECORP's site entry changed only after a configuration test and applied by a reload that keeps the other application's connections, never a restart (INFRASTRUCTURE §2)
- **WS-11**: trusted proxies limited to the proxy in front of MULTIPLECORP, trusted hosts limited to the configured domain, absolute links built from the configured application URL, so throttling keys and links cannot be spoofed (TM-01; TM-07); WS-08's policy first in report-only mode on staging, with its reports to the staging-only, throttled and size-capped same-origin endpoint EN-03 allows, then enforced — TASK-001 sets the nonce-bearing policy itself
- **The environment template**: every secret of SX-01 named in `.env.example` with a placeholder — `APP_KEY`, the credentials of the runtime, migration/owner and administration roles, Valkey's password, the HMAC keys of LG-03, each environment's Google client — and SX-03's production settings: `APP_DEBUG=false`, `APP_ENV=production`, no debug or profiling tooling, cached configuration (WS-10; TM-27; TM-48; INFRASTRUCTURE §4.2)
- **The database connections** of DATABASE §28's roles: the runtime connection for web and workers, the migration/owner connection used only by the deployment's migration step, its credential never in the runtime's environment, and the administration connection for `GuardMaintenance` and restore verification only (H9-01; INFRASTRUCTURE §4.4; EN-07); the extensions `pg_trgm`, `btree_gist`, `pg_stat_statements` and `auto_explain` provisioned by the migration role (INFRASTRUCTURE §4.2)
- **Private stores**: PostgreSQL and Valkey on a container network only MULTIPLECORP's containers reach, with authentication, never public and never on an interface the second application can reach; Valkey sized never to evict, its policy `noeviction` or one that evicts only keys with an expiry (H9-09; SX-03; HO-24; SZ-09); `APP_KEY` readable only by the service account, outside the database (TM-65)
- **Configuration owned by P9**: the timeouts TX-01–TX-10 with the runtime role's default statement timeout and the retry budgets RY-02, RY-03 and RY-08 (HO-21); the hashing parameters and the slots of H6-12, their values from TASK-079's measurement (HO-26); the PostgreSQL and framework versions chosen, recorded, with the evidence tables re-verified against them — a generated column a CHECK or a partial index reads declared STORED (HO-30)
- **Schedules and restarts**: the scheduler's cron entry and the process manager; every queue worker and the scheduler restarted on deployment; no step flushes Valkey, so limiter and lock keys survive (HO-23; CP-07); the schedule entries of the retention and cleanup jobs their Tasks build — session garbage collection, the credential-token purge, failed-job retention of 7 days, `command_log`'s purge JB-08, export-file cleanup, unlinked uploads after 7 days (H9-04; HO-29; INFRASTRUCTURE §7); `ReconcileGuards`' nightly run clear of the backup window (HO-28); log files with restricted permissions and a rotation within the disk budget (H9-08; LG-06)
- **The health and smoke checks** (PRQ-03; GAP-013): the minimal unauthenticated health check of EN-03, without internals (SX-03); and read-only smoke checks of the business heartbeat INFRASTRUCTURE §6 lists — the oldest queued job's age, failed jobs, the scheduler's last successful run, `ReconcileGuards`' and the purges' daily success, stale renditions and owed work, database connections, free disk and backup freshness —, never a write (REL-11); alerting on them is TASK-075's (HO-25)
- **The maintenance mode**: the framework's mode with its flag placed where every web process of MULTIPLECORP reads it, without a bypass when the incident runbook needs it, each process confirmed by a forbidden request answered as refused (H9-14; TT-F10); MULTIPLECORP's alone, the second application serving throughout (INFRASTRUCTURE §5)
- **The mixed-version safeguards** (PRQ-04; HO-31; GAP-012; TEST_STRATEGY §15): expand-only migrations, an architecture test refusing a drop, rename or type change in an expand migration; the previous release's code passing its smoke suite against the expanded schema; a worker of the previous release releasing a job payload newer than it understands; a command posted from a page loaded before the deployment staying valid for one release; the fingerprint algorithm of a recorded version kept for the retention period; no step that flushes the store — the payload versions themselves being TASK-022's and command identity TASK-013's
- **The drain and the reconciliation** (PRQ-05; HO-34; GAP-031): in-flight cascades drained before a deployment, and `ReconcileGuards` — TASK-013's command — after one that changes a posting service, as runbook steps
- **The staging stack's definition** and its synthetic seed (PRQ-02; FX-02): the same definition, resource-capped below the headroom both production applications need, with its own containers, database, Valkey and storage, started and removed by the operator (DIR-079 CB-04; INFRASTRUCTURE §3), never touching the second application
- **The operator's release runbook** for REL-08–REL-13 (PRQ-01): the verified pre-deployment backup by TASK-074's tooling, a failed or unverified one stopping the deployment (REL-08; BK-03); the drain (REL-09); the deployment in an announced window outside business hours for the first three months after go-live, the application in maintenance mode for its measured duration, expand-only migrations through the migration connection, new code, workers and scheduler restarted (REL-10; DIR-083 OE-08); the verification — the smoke checks, `ReconcileGuards` where a posting service changed, and the operator's read-only check of WS-10's gates and GLC-07's items (REL-11); the heartbeat watched through the first business day, the deployed commit and schema version recorded (REL-12); the contract step only in a later release (REL-13); and the staging rehearsal of REL-03 by the same steps, with the interrupted-migration case and its recovery (RBK-05) and RBK-01's code rollback in a maintenance window; a failed step stopping for the operator's and the Owner's decision (RBK-04); REL-01, REL-02 and REL-04–REL-07 are TASK-073's release process, which the runbook's steps follow
- **UXH-09**: the post-response step that LG-02's `TECH-022` clause prefers confirmed on the chosen runtime, a refusal answer included, and no deployment step — Nginx or the shared host proxy — stripping the client's marking of background requests
- **INF-07 at every step** (16a): each step acts on MULTIPLECORP's own project, database connections, storage, backup directory and schedules only; MULTIPLECORP adds only its own firewall rules — HTTPS through the shared host proxy and the operator's administrative access — and never changes the other application's (INFRASTRUCTURE §7); the shared host proxy changes only by its tested reload (RBK-06)

**OUT OF SCOPE:**

- running any part on staging or production — the operator's, in TASK-078, TASK-084, TASK-085 and at every release (INF-02, INF-03; RL-01)
- the backup tooling, its schedule and the off-server copy (TASK-074); the heartbeat's alerts, the uptime check and the log viewer (TASK-075); the runbooks of INFRASTRUCTURE §7 (TASK-076); the cutover tooling and the go-live command (TASK-077)
- the release process — readiness, declarations, tags, branch protection, the integrity report and the release record (TASK-073)
- the measured values of workers, hashing and limiter caps (TASK-079); the payload versions (TASK-022) and command identity (TASK-013) themselves; the CSP nonce in the application (TASK-001)
- any change to the second application, a firewall rule that is not MULTIPLECORP's, or a provider feature (INF-07; INF-08)

**REFERENCES:**

- [INFRASTRUCTURE §2](../09-operations/INFRASTRUCTURE.md#2-topology), [§3](../09-operations/INFRASTRUCTURE.md#3-environments), [§4](../09-operations/INFRASTRUCTURE.md#4-processes-configuration-and-secrets), [§5](../09-operations/INFRASTRUCTURE.md#5-the-deployment-path), [§6](../09-operations/INFRASTRUCTURE.md#6-observability) and [§7](../09-operations/INFRASTRUCTURE.md#7-security-operations); [§1](../09-operations/INFRASTRUCTURE.md#1-invariants) INF-01–INF-03, INF-05, INF-07
- [INFRASTRUCTURE §10](../09-operations/INFRASTRUCTURE.md#10-owner-questions) P9Q-01, P9Q-02 — DIR-079 CB-04, CB-05
- [RELEASE_PLAN §1](../10-release/RELEASE_PLAN.md#1-principles) RL-01, RL-04, RL-08; [§3](../10-release/RELEASE_PLAN.md#3-the-release-sequence) REL-01–REL-13; [§6](../10-release/RELEASE_PLAN.md#6-cutover-and-rollback) RBK-01, RBK-04–RBK-06; [§10](../10-release/RELEASE_PLAN.md#10-production-release-requirements-for-p11) PRQ-01–PRQ-05
- [SECURITY §3](../05-security/SECURITY.md#3-enforcement-architecture) EN-03, EN-07; [§4](../05-security/SECURITY.md#4-web-and-input-security) WS-06, WS-08, WS-10, WS-11; [§6](../05-security/SECURITY.md#6-audit-and-security-logging) LG-03, LG-04, LG-06; [§7](../05-security/SECURITY.md#7-secrets-production-boundary-and-supply-chain) SX-01, SX-03, SX-05; [§8](../05-security/SECURITY.md#8-threat-model) TM-01, TM-07, TM-27, TM-28, TM-48, TM-50, TM-65
- [SECURITY §10](../05-security/SECURITY.md#p9--operations) H9-01, H9-02, H9-04, H9-08, H9-09, H9-14; [P8 handoff](../05-security/SECURITY.md#p8--required-denial-and-security-tests-p8-creates-its-own-files) H8-20
- [CONCURRENCY_IDEMPOTENCY §19](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s19-20-handoff-traceability.md#19-handoff-obligations) HO-21–HO-31, HO-34; [§15](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s14-17-numbering-jobs-revocation.md#15-jobs-scheduler-and-queue) — payload versioning and the scheduler
- [ARCHITECTURE §1](../04-architecture/ARCHITECTURE.md#1-baseline-and-constraints), [§16](../04-architecture/ARCHITECTURE.md#16-configuration-environments-and-database-roles); [DATABASE §28](../04-architecture/DATABASE/s25-28-transactions-handoffs.md#28-security-ready-data-design)
- [PERFORMANCE §13](../06-api-performance/PERFORMANCE.md#13-resource-inputs-for-p9) SZ-01–SZ-09
- [TEST_STRATEGY §15](../08-testing/TEST_STRATEGY.md#15-migration-backup-and-restore-tests) — the schema migrations' mixed-version tests; [§4](../08-testing/TEST_STRATEGY.md#4-environments-and-data) FX-02
- [ADMIN_FLOW §16](../07-ux-design/ADMIN_FLOW/s16-17-handoff-traceability.md#16-handoff-obligations) UXH-09
- [GAP_REGISTER](../00-governance/GAP_REGISTER.md#gap-012--rolling-code-changes-may-leave-workers-executing-incompatible-jobs) GAP-012, GAP-013, GAP-031
- [DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-07, OE-08, OE-13
- Architecture challenge — the sequences 11a, 16a of the [P11 gate record](evidence/P11_QUALITY_GATE.md#whole-system-architecture-challenge-sp-98), their assertions carried into this Task's acceptance criteria and tests
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (50): WS-06; WS-08; WS-10–WS-11; SX-01; SX-03; TM-01; TM-07; TM-27–TM-28; TM-48; TM-50; TM-65; H8-20; H9-01–H9-02; H9-04; H9-08–H9-09; HO-21; HO-23; HO-25–HO-26; HO-28–HO-31; HO-34; UXH-09; INF-07; REL-01–REL-13; PRQ-01–PRQ-05; GAP-012; GAP-031

**EXPECTED MODULES / FILE AREAS:**

- the production and staging deployment definition beside TASK-001's local one, the environment template `.env.example`, MULTIPLECORP's Nginx site file, the scheduler and process-manager configuration; OPS's minimal health check and heartbeat reader; the mixed-version architecture and smoke tests under `tests/` (TL-16); the operator's release runbook in the repository's operations documentation — every value a placeholder in angle brackets (INFRASTRUCTURE's public-repository rule)

**BUSINESS RULES:**

- N/A — no business rule; the heartbeat reads the durable rows the jobs keep (CONCURRENCY_IDEMPOTENCY §15) and computes no business figure

**ARCHITECTURE RULES:**

- every deployment comes from `main`, run by the human operator — never by an agent, a CI job or an automatic trigger from the repository (INF-03; RL-01)
- a code rollback is not data recovery, and recorded stock, payments and issued documents are never undone by redeploying older code (RL-04; GAP-012); expand now, contract later (RL-08)
- Nginx stays as ARCHITECTURE §1 states it (DIR-083 OE-13); one PostgreSQL instance of MULTIPLECORP's own, never shared with the second application, and no connection pooler — sessions end with the request (SZ-03; HO-22); Valkey never a system of record (ARCHITECTURE §1)
- the smoke checks read only, and the health check carries no internals (SX-03); no new observability or orchestration platform without need (GAP-012; GAP-013)

**SECURITY / PERMISSION REQUIREMENTS:**

- placeholders only: no real value of any SX-01 secret in a file, a log, an image or a CI variable, and CI never holds a production secret (SX-01; SX-05; INF-05; TM-48)
- the migration/owner credential never in the runtime's environment; the administration role reached only by `GuardMaintenance` and restore verification; web and workers on the runtime role only (INFRASTRUCTURE §4.4; EN-07)
- the health check is one of EN-03's unauthenticated exceptions and discloses no version, path, count or record (SX-03; WS-10; TM-27); every other route stays authenticated
- TLS, staged HSTS, the static headers — `frame-ancestors 'none'` and `X-Frame-Options: DENY` against TM-50, `Cache-Control: no-store` on authenticated responses against TM-28 — and banners off (WS-06; WS-08; H9-02); trusted proxies and hosts as WS-11 states
- PostgreSQL and Valkey private, authenticated and bound away from the second application (H9-09); `APP_KEY` held outside the database (TM-65; SX-01)

**COMPANY SCOPE AND FIELD PROJECTION:**

- N/A — no business data path: the heartbeat's figures are workspace-wide counts and ages read in system context for the operator only — operational records never reach an Admin path (PERMISSIONS_MATRIX PJ-21) — and carry no company record, S3 or S4 value

**CONCURRENCY / IDEMPOTENCY:**

- the deployment honours HO-31: a job payload of a newer version is released back by an older worker, a newer worker accepts the previous version for one release (CONCURRENCY_IDEMPOTENCY §15), a command from a page loaded before the deployment replays under its `command_id` as CI-01–CI-09 state, and no step flushes Valkey's locks or limiter keys (HO-23; CP-07)
- in-flight cascades drained before the deployment (HO-34); the maintenance mode keeps every NX-02 command refused while an incident run lasts (H9-14, protocol (b)); no lock class or AX- action is added

**AUDIT:**

- the deployment writes no business audit; the deployed commit and schema version are recorded in the release record (REL-12; TASK-073); `ReconcileGuards`' and `GuardMaintenance`'s own audit is TASK-013's (H9-03); logs keep LG-03's redaction and LG-04's correlation, and request logs drop the credential-link and Google-callback query strings (LG-04)

**DATABASE IMPACT ASSESSMENT:**

- Schema change: NONE — no table or column
- Migration required: NO
- Existing data transformation: NONE
- Direct production DB access: FORBIDDEN
- Data-loss risk: NONE KNOWN

**MODULARITY / EXTENSIBILITY:**

- Module boundary affected: the deployment layer outside the application's modules; OPS for the health check and the heartbeat reader
- Stable core kept provider-neutral: YES — no provider feature is relied on (INF-08); the tooling is CB-05's
- External/vendor-specific code isolated: YES — the deployment tooling's files only
- Replaceable adapter/contract required: NO
- Provider lock-in risk: NONE beyond the Owner's baseline stack
- Future extension impact: LOW — rolling deployments come only after a later Owner decision (DIR-083 OE-08)
- Speculative abstraction avoided: YES — no orchestration platform, no blue-green tooling, no new observability platform (GAP-012; GAP-013)

**AUTH IMPACT:**

- Authentication change: NONE
- Authentication implementation: LARAVEL_NATIVE — decided once ([EXECUTION_CONTEXT](EXECUTION_CONTEXT.md)), reused, never redesigned
- Auth profile: N/A — not touched; the hashing parameters are configuration whose values TASK-079 measures (HO-26)
- Google sign-in: N/A — the client's variables are placeholders; the clients are TASK-082's
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

- Context7: REQUIRED — the compose tooling CB-05 settles, Nginx's header inheritance, HSTS and request limits, the framework's maintenance mode, trusted proxies and hosts, scheduler and queue restart, and the runtime's post-response step, at the installed versions
- Graphify: OPTIONAL — the jobs, schedules and commands exist by now; a targeted search serves otherwise
- Browser E2E: N/A — the page-before-deployment case is a feature test; staging's browser checks are TASK-078's
- Other: TASK-003's CI stack to start the definition with PostgreSQL and Valkey; the configuration test of Nginx and of the compose file run locally and in CI
- Missing safe tool behavior: AUTO-INSTALL project-locally inside this Task (AICWDF §6.3) and record it in TOOLCHAIN; a paid tier or a privileged installation STOPS for the Owner (COST_POLICY)

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: refreshed before the change when Graphify is available (TOOLCHAIN); otherwise a targeted search of the jobs, the scheduler and the command envelope
- Pre-change questions: which jobs and schedules exist and which keys live in Valkey; which commands a page loaded before a deployment can post; which migrations of the release are expand-only
- Expected affected areas: the deployment files, OPS's health reader and the TL-16 tests only

**CONTEXT7:**

- Topics to verify: where the framework's maintenance flag lives so that every web process reads it; the queue-restart signal and its cache use; trusted-proxy and trusted-host configuration; Nginx `add_header` inheritance, HSTS and `limit_req`; the compose tooling's resource limits and private networks; the post-response step of the chosen PHP runtime
- Version-sensitive assumptions: none from memory — each behaviour verified at the installed version and the versions recorded (HO-30)

**UI / UX REQUIREMENTS:**

- Follow global design rules: N/A — no page; the maintenance mode answers with the framework's own refusal, and users learn of a window from its announcement (REL-10)
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
- Destructive actions: N/A — no page; the runbook's stop on a failed step is the operator's decision (RBK-04)
- Redundancy constraints: N/A — no page
- Loading state: N/A — no page
- Empty state: N/A — no page
- Error state: N/A — no page; the health check answers without internals

**ROUTE IMPACT:**

- the minimal health check of EN-03 — unauthenticated, without internals (SX-03) —, and on staging only the throttled, size-capped CSP report endpoint of WS-08 (EN-03); ROUTE_CONTRACTS holds no row for either, and whether they need one — TEST_STRATEGY §8 fails a route without a contract — is left to Planning, so this Task adds and changes no ROUTE- row

**INTERACTION CONTRACTS:**

- N/A — no INT- contract; a command posted from a page loaded before a deployment keeps the server answers of INT-015–INT-017 (TASK-013)

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

1. The deployment definition, the environment template and the runbook hold placeholders only; the secret-like scan finds no secret, host, address, port or account (SX-01; SM-15).
2. Started in CI, the definition reaches MULTIPLECORP's PostgreSQL and Valkey only from its own containers, with authentication, and the runtime's environment holds no migration/owner credential (H9-09; INFRASTRUCTURE §4.4).
3. Plain HTTP is redirected; every HTML and Inertia response carries WS-08's static headers and HSTS at its configured stage, with no version banner (WS-06; WS-08; H9-02).
4. A forwarded header from an untrusted source changes neither a throttling key nor a built link, and a request for another host is refused (WS-11; TM-01; TM-07).
5. The health check answers unauthenticated and without internals; the smoke checks report every heartbeat signal of INFRASTRUCTURE §6 and write nothing (SX-03; PRQ-03).
6. With the maintenance mode on, every web process of MULTIPLECORP refuses a forbidden request, and only the operator's step lifts it (H9-14; TT-F10).
7. An expand migration that drops, renames or retypes a column fails the architecture test; the previous release's smoke suite passes on the expanded schema; a previous-release worker releases a newer payload; a command from a page loaded before the deployment stays valid for one release (PRQ-04; architecture challenge 11a).
8. No step of the definition or the runbook flushes Valkey, or stops, reloads, reads or changes the second application, its data or its secrets; the shared host proxy changes only by its tested reload (INF-07; RBK-06; architecture challenge 16a).
9. The runbook orders REL-08–REL-13 and the staging rehearsal of REL-03, stops on an unverified backup and on any failed step for the operator's decision, and records the deployed commit and schema version (REL-08; RBK-04; REL-12).
10. Request logs hold no credential-link path or query string and no Google callback query string (LG-04; H8-20).

**TEST REQUIREMENTS:**

- Lint: TL-01, with the configuration tests of the Nginx and compose files
- Typecheck: TL-02
- Unit: the heartbeat reader's signal rules and the health check's minimal answer (TL-03)
- Feature: the health check, the maintenance-mode refusal on every web process, trusted proxies and hosts, the headers and the redirect (TL-04)
- Integration: the definition started on CI's stack — private network, authentication, the roles' connections, workers and scheduler restarted with Valkey's keys intact (TL-05)
- Authorization: the health check the only unauthenticated addition; every other route still declares its capability (TL-06; SM-02)
- Route integrity: SM-02's route audit with the health check as one of EN-03's exceptions (TL-07); the route crawl's treatment of the two endpoints without a contract follows Planning's answer (TEST_STRATEGY §8)
- Interaction: N/A — no INT- contract in this Task
- Browser E2E: N/A — no page; staging's checks are TASK-078's
- Localization/language switch: N/A — no user-facing copy in this Task
- Visual breathing room/density: N/A — no page in this Task
- Responsive: N/A — no page in this Task
- Accessibility: N/A — no page in this Task
- Production build: TL-13 — the production configuration check: debug off, no test route, no development dependency (WS-10; SX-03)
- Security: SM-08's headers, HSTS and banners on the local definition — the staging host's TLS check is the operator's —; SM-15's secret scan; WS-10's gates (TL-14)
- Performance: N/A — no budgeted page or query; the staging measurements are TASK-079's
- Migration: TL-16 — the mixed-version cases of TEST_STRATEGY §15 on the 10x fixture, each migration's duration recorded for the release window
- Other: the runbook read step by step against INFRASTRUCTURE §5 and RELEASE_PLAN §3, each step naming its source; GAP-031's `ReconcileGuards` step present after a posting-service change

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

- TASK-074 — when its other dependencies are DONE and its READY gate holds

**DO NOT DO:**

- add to V1 — a capability, page, route, field, integration, document type, report or paid service — or drop, defer or narrow a V1_REQUIRED requirement; a Task that would need either raises an Owner question and waits ([DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-20)
- access the VPS, production, its data or a credential, or put a secret, real data, a host detail or production configuration in the repository ([INFRASTRUCTURE](../09-operations/INFRASTRUCTURE.md) INF-01, INF-05; SECURITY SX-01, SX-04, SX-06; [PRODUCTION_DATA_SAFETY](../00-governance/PRODUCTION_DATA_SAFETY.md))
- add AI, model or tool attribution to a commit — the Owner's configured identity only ([AGENTS.md](../../AGENTS.md#git-commit-attribution); DIR-022)
- start before the Owner's execution authorization at OG-4, or mark DONE without the required tests passing and the completion evidence ([TASK_PLAN](TASK_PLAN.md#2-rules-of-the-plan); [DEFINITION_OF_DONE](../08-testing/DEFINITION_OF_DONE.md#2-the-checklist))
- weaken, skip or delete a valid test to obtain a green run, or adopt a paid tier without the Owner's COST_POLICY decision (DOD-05; [COST_POLICY](../00-governance/COST_POLICY.md))
- deploy, migrate, start or stop anything on staging or production, or add a CI job or trigger that deploys (INF-03; RL-01)
- write a real value into the definition, the environment template, an image or a CI variable (SX-01; INF-05)
- add a step that flushes Valkey, restarts the shared host proxy, or reads or changes the second application (HO-23; INF-07)
- put the migration/owner credential in the runtime's environment, or a contract migration in the release that needs the old form (INFRASTRUCTURE §4.4; RL-08)

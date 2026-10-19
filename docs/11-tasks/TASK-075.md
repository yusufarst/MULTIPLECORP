# TASK-075 — Monitoring — the business heartbeat, alerts and the uptime check

Status: REVIEW | Updated: 2026-10-19 | Owner: Planning

Written by WF-PC5-W's writer of bundle W4 of run PC-5 on 2026-10-19 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99 — a REVIEW draft of P11, approved, or not, with the [Task plan](TASK_PLAN.md) at OG-4. **No Task starts before the Owner's execution authorization at OG-4.** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** BLOCKED — waiting on OPI-05

**RISK CLASS:** HIGH

**EXECUTOR:** AGENT

**OBJECTIVE:**

Build, free and self-hosted on the VPS and with placeholder values only, the monitoring INFRASTRUCTURE §6 designs: the business heartbeat's signals with their alert rules, the signals HO-25 lists, the backup, copy and disk alerts of BK-10 and BK-11, the statement statistics with their monthly report, the delivery of LG-07's and H9-15's security alerts to the named operator, the alternate once named and the Owner, the self-hosted log viewer and the external uptime check — delivered through the zero-cost channel OPI-05 records, with the in-app notice and the operator's daily look kept as the floor.

**WHY THIS EXISTS:**

A successful HTTP health check can hide stalled business processing — missing documents, failing backups, a stuck queue persist unnoticed ([GAP-013](../00-governance/GAP_REGISTER.md#gap-013--successful-http-health-checks-can-hide-stalled-business-processing)); BK-10 and BK-11 require a failed backup and disk pressure to reach the named operator, H9-10 and H9-15 hand the security alerts' delivery to P9, HO-25 lists what must be monitored and alerted, and GAP-007 asks for stale renditions to be watched. Only an outside check sees the VPS down (P9Q-07), and no paid service may be adopted (INF-04). A failed off-server copy must reach a human too — the architecture challenge's sequence 13c.

**DEPENDS ON:**

- TASK-071 — The operator's inputs before staging
- TASK-072 — Deployment definition, health and smoke checks, maintenance mode and mixed-version safeguards
- the operator's or the Owner's input OPI-05 recorded ([RELEASE_PLAN §9](../10-release/RELEASE_PLAN.md#9-the-go-live-checklist)) — BLOCKED until recorded

**BLOCKS:**

- TASK-080 — Alert channel adopted and monitoring live

**PARALLEL SAFE:** NO — it adds the alert rules and the one delivery path that TASK-080 makes live and that every later alert uses

**IN SCOPE:**

- **The input it waits on.** OPI-05 — the zero-cost alert channel the operator confirms under DIR-079 CB-06 — is recorded by the operator in TASK-071, in INFRASTRUCTURE §9's row, with its COST_POLICY §4C.4 record; this Task stays BLOCKED until it is recorded, since the delivery adapter is written for that channel
- **The business heartbeat's alert rules** (INFRASTRUCTURE §6; GAP-013): the oldest queued job's age past its configured bound, failed jobs, the scheduler's last successful run, a day without a successful `ReconcileGuards` or purge run (HO-25; HO-28; HO-29), stale renditions and owed work, FAILED renditions after their five attempts (JB-01; GAP-007), database connections, free disk and backup freshness — the signals TASK-072's smoke checks read, each with its threshold as configuration
- **The backup, copy and disk alerts** (BK-10; BK-11): a failed, stale — no verified backup within 26 hours (BK-04) — or skipped run, a failed cleanup, and a failed or missing off-server copy (BKR-09; architecture challenge 13c), each reaching the named operator; the disk's warning and critical thresholds `<DISK_WARN_PCT>` and `<DISK_CRIT_PCT>`, crossing one alerting and starting the capacity response of BACKUP_RECOVERY §3
- **HO-25's monitored signals**: every deadlock (`40P01`, each one a defect), retries and restarts (HO-21; HO-23), lock waits by class, FAILED rates by cause, a FAILED access change, guard-missing alerts and reconciliation differences (GAP-031; C-61), and statements slower than 500 ms logged (SZ-06); Valkey's availability (HO-24; INFRASTRUCTURE §4.1); the measured hashing parameters (HO-26) and the recorded versions (HO-30) stay TASK-079's and TASK-072's, the monitoring components' own versions recorded with them
- **Statement statistics**: `pg_stat_statements` and `auto_explain` in staging and production, the heaviest statements and the table and index sizes reported monthly, crossing half of a threshold opening a measured review, never an automatic change (PERFORMANCE §12 item 6; GT-07; PFM-18)
- **Security monitoring** (H9-10; H9-15): LG-07's detection signals and the alerts AU-03, AU-05, AU-18–AU-21 and AU-23 add, delivered to the named operator, to the alternate of P9Q-14 once TASK-083 names one (DIR-083 OE-03) and to the Owner, and to the confirmer an OWNER_RECOVERY_LINK_ISSUED names (LG-07); until a channel the operator does not solely control is confirmed, the Owner's floor stays the in-app notice at the next login — *Beranda*'s security row, built by TASK-058 (INFORMATION_ARCHITECTURE §9; AU-14; GAP-041)
- **The delivery path**: one small adapter for OPI-05's channel, its token read from the environment as a secret of SX-01, each message a neutral signal name with its time and no S3 or S4 value or secret (LG-03; OPI-05); the operator's daily look at the heartbeat kept as the floor (DIR-079 CB-06)
- **The log viewer** (P9Q-04; DIR-079 CB-07): the framework's structured logs with a self-hosted viewer, reached only through the operator's administrative access and never by an application route; no error data leaves the VPS (INFRASTRUCTURE §6; LG-05)
- **The external uptime check** (P9Q-07; DIR-079 CB-10): a free external check that sees the VPS from outside through TASK-072's minimal health check, its COST_POLICY §4C.4 record — limits, quota behaviour, lock-in, exit and data portability — written before it is adopted (GLC-11; INF-04)
- **PRQ-03's half here**: the heartbeat's signals raised as alerts; the health check and the read-only smoke checks themselves are TASK-072's

**OUT OF SCOPE:**

- making the monitoring live on staging and production and testing the channel end to end — the operator's (TASK-080; the failure drill, TASK-081)
- the in-app Owner security notices (TASK-058), the security log page (TASK-061) and the security events and detection rules themselves (TASK-010)
- the health check and smoke checks (TASK-072); the backup and copy tooling whose outcomes raise these signals (TASK-074)
- naming the alternate operator (TASK-083); any paid tracker, monitor or messaging service (INF-04; P9Q-03 (d), P9Q-04 (c))

**REFERENCES:**

- [INFRASTRUCTURE §6](../09-operations/INFRASTRUCTURE.md#6-observability); [§4.1](../09-operations/INFRASTRUCTURE.md#41-processes); [§9](../09-operations/INFRASTRUCTURE.md#9-the-operators-inputs) OPI-05; [§10](../09-operations/INFRASTRUCTURE.md#10-owner-questions) P9Q-03, P9Q-04, P9Q-07 (DIR-079 CB-06, CB-07, CB-10), P9Q-14; [§1](../09-operations/INFRASTRUCTURE.md#1-invariants) INF-04
- [BACKUP_RECOVERY §2](../09-operations/BACKUP_RECOVERY.md#2-the-twelve-obligations) BK-04, BK-10, BK-11; [§1](../09-operations/BACKUP_RECOVERY.md#1-the-decision-and-its-limits) BKR-02, BKR-09; [§3](../09-operations/BACKUP_RECOVERY.md#3-the-shared-disk-budget)
- [SECURITY §6](../05-security/SECURITY.md#6-audit-and-security-logging) LG-03, LG-05, LG-07; [§10](../05-security/SECURITY.md#p9--operations) H9-06, H9-10, H9-15
- [CONCURRENCY_IDEMPOTENCY §19](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s19-20-handoff-traceability.md#19-handoff-obligations) HO-21, HO-23–HO-26, HO-28–HO-30; [§15](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s14-17-numbering-jobs-revocation.md#15-jobs-scheduler-and-queue) JB-01, JB-06, JB-08
- [PERFORMANCE §12](../06-api-performance/PERFORMANCE.md#12-index-register-and-verification-procedure) item 6; [§11](../06-api-performance/PERFORMANCE.md#11-growth-thresholds) GT-07; [§13](../06-api-performance/PERFORMANCE.md#13-resource-inputs-for-p9) SZ-06; [PERFORMANCE_TARGETS](../08-testing/PERFORMANCE_TARGETS.md#3-the-measured-targets) PFM-18
- [INFORMATION_ARCHITECTURE §9](../07-ux-design/INFORMATION_ARCHITECTURE.md#9-dashboards-and-queues) — the Owner's security notices
- [RELEASE_PLAN §9](../10-release/RELEASE_PLAN.md#9-the-go-live-checklist) GLC-03, GLC-11; [§10](../10-release/RELEASE_PLAN.md#10-production-release-requirements-for-p11) PRQ-03
- [COST_POLICY](../00-governance/COST_POLICY.md#procedure) §4C.2, §4C.4
- [GAP_REGISTER](../00-governance/GAP_REGISTER.md#gap-013--successful-http-health-checks-can-hide-stalled-business-processing) GAP-013; [GAP-007](../00-governance/GAP_REGISTER.md#gap-007--an-issued-database-document-may-have-no-matching-pdf); [GAP-041](../00-governance/GAP_REGISTER.md#gap-041--operator-abuse-of-the-owner-recovery-command)
- [DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-03, OE-14
- Architecture challenge — the sequence 13c of the [P11 gate record](evidence/P11_QUALITY_GATE.md#whole-system-architecture-challenge-sp-98), their assertions carried into this Task's acceptance criteria and tests
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (15): BK-10–BK-11; H9-10; H9-15; HO-21; HO-23; HO-25–HO-26; HO-28–HO-30; INF-04; PRQ-03; GAP-007; GAP-013

**EXPECTED MODULES / FILE AREAS:**

- OPS's signal readers and the channel's delivery adapter; the alert rules, the log viewer's and the statement statistics' configuration beside TASK-072's deployment definition; the uptime check's set-up notes for the operator; their tests under `tests/` — every value a placeholder

**BUSINESS RULES:**

- N/A — the signals are operational counts and ages, never business figures; the user-facing QS- signals are TASK-058's

**ARCHITECTURE RULES:**

- free and self-hosted on the VPS, no new observability platform without need (GAP-013; INF-04); error data stays on the VPS (P9Q-04)
- every signal read from durable rows, the database or the host, never from Valkey's contents as truth (CONCURRENCY_IDEMPOTENCY §15)
- the monitoring only observes and alerts: it repairs, restarts or changes nothing, a reconciliation difference is never repaired silently (HO-28) and a growth threshold opens a review (GT-07)

**SECURITY / PERMISSION REQUIREMENTS:**

- the channel's token a secret of SX-01, in the environment only; no message, log line or report holds a secret, an S3 or S4 value or a credential token (LG-03; OPI-05)
- technical logs reach operators only, through the viewer behind the operator's administrative access (LG-05; PERMISSIONS_MATRIX PJ-21)
- the Owner's security alerts go through a channel the operator does not solely control once one is confirmed, otherwise by the in-app floor (LG-07; H9-06; GAP-041)

**COMPANY SCOPE AND FIELD PROJECTION:**

- N/A — the signals are workspace-wide operational counts read in system context for the operator; an alert names a neutral signal and its time, never a company record or another company's data

**CONCURRENCY / IDEMPOTENCY:**

- deadlocks, retries, restarts, lock waits by class and FAILED rates by cause are counted as the command envelope reports them — every deadlock a defect (CONCURRENCY_IDEMPOTENCY §4.4; HO-25); the readers take no lock of the canonical order LK-00–LK-31 and run no command or AX- action

**AUDIT:**

- no business audit; the security alerts follow LG-07's detection signals over the security events TASK-010 writes (LG-02), which delivery leaves unchanged; each delivery logged in the technical log with its correlation id and no payload (LG-04)

**DATABASE IMPACT ASSESSMENT:**

- Schema change: NONE — no table or column
- Migration required: NO
- Existing data transformation: NONE
- Direct production DB access: FORBIDDEN
- Data-loss risk: NONE KNOWN

**MODULARITY / EXTENSIBILITY:**

- Module boundary affected: OPS's operational readers and the delivery adapter, outside every business module
- Stable core kept provider-neutral: YES — the channel sits behind one adapter
- External/vendor-specific code isolated: YES — the channel's adapter and the uptime check's configuration only
- Replaceable adapter/contract required: YES — the channel is replaceable at no cost (OPI-05), so delivery goes through one small interface
- Provider lock-in risk: NONE — a free channel and a free uptime check, each with its exit recorded under COST_POLICY §4C.4
- Future extension impact: LOW
- Speculative abstraction avoided: YES — one channel adapter, no notification framework or metrics platform

**AUTH IMPACT:**

- Authentication change: NONE
- Authentication implementation: LARAVEL_NATIVE — decided once ([EXECUTION_CONTEXT](EXECUTION_CONTEXT.md)), reused, never redesigned
- Auth profile: N/A — this Task does not touch authentication; it delivers the alerts AU-03, AU-05, AU-18–AU-21 and AU-23 add
- Google sign-in: N/A — not touched; bursts of GOOGLE_SIGNIN_FAILED are one of LG-07's signals
- Google registration behavior: N/A — not touched
- Google credentials owner action required: NO
- Authorization change: NONE
- Provider/library-specific leakage into domain: NO

**COST IMPACT:**

- New recurring cost: NONE
- Paid third-party service: NO
- Free/open-source alternative: N/A — nothing paid is considered; the log viewer is self-hosted, and the channel and the uptime check are free tiers whose COST_POLICY §4C.4 records come before their adoption (GLC-03; GLC-11)
- Estimated additional monthly production cost: 0
- Owner approval required: NO

**MCP SESSION PROFILE:**

- Required MCP servers: none — [TOOLCHAIN](../00-governance/TOOLCHAIN.md#mcp-servers) records none installed
- MCP servers that should remain OFF: every server not required
- Native CLI/package preferred over MCP: YES — the framework's and package managers' own commands
- Post-Task deactivation/cleanup: none

**TOOL REQUIREMENTS:**

- Context7: REQUIRED — the framework's queue, failed-job and scheduler introspection, `pg_stat_statements` and `auto_explain` configuration, the chosen self-hosted log viewer and OPI-05's channel interface, at their current versions
- Graphify: OPTIONAL — the jobs and guards exist by now; a targeted search of the job and reconciliation tables serves otherwise
- Browser E2E: N/A — no page
- Other: a simulated channel in CI — no test calls the real one; the uptime check's terms read on its official page, dated
- Missing safe tool behavior: AUTO-INSTALL project-locally inside this Task (AICWDF §6.3) and record it in TOOLCHAIN; a paid tier or a privileged installation STOPS for the Owner (COST_POLICY)

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: refreshed before the change when Graphify is available (TOOLCHAIN); otherwise a targeted search of the jobs, the rendition and export rows and the security events
- Pre-change questions: which durable rows hold each piece of owed work; which security events LG-07 turns into alerts; where the backup tooling reports its outcomes
- Expected affected areas: OPS's readers and adapter, the monitoring configuration and their tests only

**CONTEXT7:**

- Topics to verify: reading queue age and failed jobs without touching payloads; the scheduler's last-run record; slow-statement logging and `auto_explain` thresholds; the channel's message API and its limits
- Version-sensitive assumptions: none from memory

**UI / UX REQUIREMENTS:**

- Follow global design rules: N/A — no page; the Owner's in-app floor is TASK-058's *Beranda* row
- Indonesian default: N/A — no page
- English support: N/A — no page
- Plain-language Indonesian: N/A — no page
- Language switch impact: NONE
- Mobile-first: N/A — no page; alerts arrive on the operator's and the Owner's own phones through the channel
- Responsive: N/A — no page
- Adaptive: N/A — no page
- Visual breathing room: N/A — no page
- Comfortable density: N/A — no page
- shadcn/ui reuse: N/A — no page
- Design reference: N/A — no page
- Primary action: N/A — no page
- Secondary actions: N/A — no page
- Destructive actions: N/A — no page; the monitoring changes nothing
- Redundancy constraints: N/A — no page
- Loading state: N/A — no page
- Empty state: N/A — no page
- Error state: N/A — no page; a failed delivery is visible in the technical log and the daily look

**ROUTE IMPACT:**

- none — no application route is added; the log viewer sits behind the operator's administrative access and the uptime check reads TASK-072's minimal health check

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

1. On CI's stack, each heartbeat signal of INFRASTRUCTURE §6 raises its alert on a seeded fault — a queued job past its bound, a failed job, a scheduler without a run, a day without a successful `ReconcileGuards` or purge, a stale rendition, a backup older than 26 hours, the disk past its warning level (GAP-013; BK-04; BK-11).
2. A failed, stale or skipped backup, a failed cleanup and a failed or missing off-server copy each alert the named operator (BK-10; BKR-09; architecture challenge 13c).
3. A deadlock, a FAILED access change, a guard-missing alert and a reconciliation difference each alert, and statements slower than 500 ms are logged (HO-25; SZ-06).
4. LG-07's and H9-15's signals reach the operator, the alternate once named and the Owner — or the Owner's in-app floor while no channel outside the operator's sole control is confirmed —, each as a neutral signal name without an S3 or S4 value (H9-10; LG-03).
5. The uptime check sees the VPS from outside through the minimal health check, and its COST_POLICY §4C.4 record exists before it is adopted (P9Q-07; GLC-11; INF-04).
6. No component sends error data or logs off the VPS, and the log viewer has no application route and is reached through the operator's access only (P9Q-04; LG-05).
7. The monthly report lists the heaviest statements and the table and index sizes, and crossing half of a threshold opens a review, never an automatic change (GT-07; PFM-18).

**TEST REQUIREMENTS:**

- Lint: TL-01
- Typecheck: TL-02
- Unit: each alert rule's threshold and the message builder's redaction (TL-03)
- Feature: the delivery adapter against the simulated channel, a failed delivery logged (TL-04)
- Integration: seeded faults on CI's stack — a stuck queue, a failed job, a stale rendition, a missed schedule, a reconciliation difference, a failed backup and a failed copy — each raising its alert (TL-05)
- Authorization: no application account reaches the logs or the monitoring; the route audit unchanged (TL-06)
- Route integrity: N/A — no route added or changed
- Interaction: N/A — no INT- contract in this Task
- Browser E2E: N/A — no page in this Task
- Localization/language switch: N/A — no user-facing copy; alerts are neutral signal names
- Visual breathing room/density: N/A — no page in this Task
- Responsive: N/A — no page in this Task
- Accessibility: N/A — no page in this Task
- Production build: TL-13
- Security: SM-15's secret scan; no token, secret or S3 or S4 value in a message or log line (TL-14)
- Performance: the readers run off the request path with bounded statements, and PFM-18's monthly inputs are produced (TL-15)
- Migration: N/A — no migration or import code in this Task
- Other: the alert list matched one by one with RELEASE_PLAN §7's failure drill and INFRASTRUCTURE §6, so that TASK-081's drill can exercise every signal

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

- TASK-080 — when its other dependencies are DONE and its READY gate holds

**DO NOT DO:**

- add to V1 — a capability, page, route, field, integration, document type, report or paid service — or drop, defer or narrow a V1_REQUIRED requirement; a Task that would need either raises an Owner question and waits ([DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-20)
- access the VPS, production, its data or a credential, or put a secret, real data, a host detail or production configuration in the repository ([INFRASTRUCTURE](../09-operations/INFRASTRUCTURE.md) INF-01, INF-05; SECURITY SX-01, SX-04, SX-06; [PRODUCTION_DATA_SAFETY](../00-governance/PRODUCTION_DATA_SAFETY.md))
- add AI, model or tool attribution to a commit — the Owner's configured identity only ([AGENTS.md](../../AGENTS.md#git-commit-attribution); DIR-022)
- start before the Owner's execution authorization at OG-4, or mark DONE without the required tests passing and the completion evidence ([TASK_PLAN](TASK_PLAN.md#2-rules-of-the-plan); [DEFINITION_OF_DONE](../08-testing/DEFINITION_OF_DONE.md#2-the-checklist))
- weaken, skip or delete a valid test to obtain a green run, or adopt a paid tier without the Owner's COST_POLICY decision (DOD-05; [COST_POLICY](../00-governance/COST_POLICY.md))
- adopt a paid monitoring, tracking, uptime or messaging service, or send error data or logs off the VPS (INF-04; P9Q-04)
- put the channel's token, a secret, a credential token or an S3 or S4 value into an alert, a log line or the repository (SX-01; LG-03)
- add an application route for the log viewer or the monitoring (LG-05; ROUTE_CONTRACTS)
- let the monitoring repair, restart or change anything by itself (HO-28; GT-07)

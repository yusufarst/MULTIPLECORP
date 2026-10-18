# Infrastructure

Status: REVIEW — Draft of P9, not normative before its approval | Updated: 2026-10-19 | Owner: Planning

**Draft of P9, not normative before its approval.** Run PC-2 drafted this document on 2026-10-18 under [DIR-078](../00-governance/DECISION_LOG.md#dir-077-dir-078-obs-030-and-tech-037--p7-ux-re-baseline-cp-a-decisions-run-pc-2-with-p9s-drafting-baseline-and-drafts) SP-67, P9 being authorized by the Owner's [DIR-077](../00-governance/DECISION_LOG.md#dir-077-dir-078-obs-030-and-tech-037--p7-ux-re-baseline-cp-a-decisions-run-pc-2-with-p9s-drafting-baseline-and-drafts) CA-14: drafted in PC-2, completed in PC-4 with the operator's inputs and the Owner's answers, and approved separately at OG-3. Run PC-4 completed it on 2026-10-19 under [DIR-082](../00-governance/DECISION_LOG.md#dir-081-dir-082-obs-032-and-tech-039--og-2-decisions-appr-011-run-pc-4-p8-and-p10-drafted-and-p9-completed) SP-87, with the operator's inputs the Owner gave in session as the operator and the tabletop exercise of BACKUP_RECOVERY §6; an input that was not available is an obligation of [RELEASE_PLAN §9](../10-release/RELEASE_PLAN.md#9-the-go-live-checklist), never a result. Nothing here is installed, configured or connected; no agent connected to the VPS or any production system to write it.

**Sources.** [AICWDF](../00-governance/AICWDF_ADOPTION.md) §20 and §32; [V1_SCOPE](../01-product/V1_SCOPE.md) DEP-01, DEP-02, DEP-07, DEP-08, DEP-10 and DEP-11; [SECURITY](../05-security/SECURITY.md) §10's H9-01–H9-15 with the P9 items of its rules; [CONCURRENCY_IDEMPOTENCY](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s19-20-handoff-traceability.md) HO-21–HO-30 and HO-32; [PERFORMANCE](../06-api-performance/PERFORMANCE.md) AS-01, PF-28, PF-39, GT-06, GT-07, §12 and §13; [DATABASE](../04-architecture/DATABASE/README.md) §§19, 22, 27, 28 and 31; [ARCHITECTURE](../04-architecture/ARCHITECTURE.md) §§1, 12, 16, 17 and 20; the gaps whose resolution names P9 ([GAP_REGISTER](../00-governance/GAP_REGISTER.md)); [PRODUCTION_DATA_SAFETY](../00-governance/PRODUCTION_DATA_SAFETY.md), [COST_POLICY](../00-governance/COST_POLICY.md) and [AGENT_OPERATING_MODEL](../00-governance/AGENT_OPERATING_MODEL.md); [PERMISSIONS_MATRIX §13](../05-security/PERMISSIONS_MATRIX.md) hands P9 what SECURITY §10 lists. Every requirement an approved document hands to P9 was found by a scripted search and is mapped in §11; backup and recovery are [BACKUP_RECOVERY](BACKUP_RECOVERY.md)'s.

Authority: P9 — the environments, the topology and processes, configuration and secrets, the deployment path, observability, security operations, capacity and cost. It adds no business rule, capability or security control: it provisions and operates what P0–P8 define.

**Public repository.** MULTIPLECORP is public ([TOOLCHAIN](../00-governance/TOOLCHAIN.md)): this document names no host, address, port, user name, secret path, provider account or key detail. Values appear only as placeholders in angle brackets — `<APP_DOMAIN>`, `<OPERATOR>` — that the operator fills outside the repository; what the operator must supply is listed in §9, never assumed.

## 1. Invariants

| INF | Invariant | Grounds |
| --- | --- | --- |
| INF-01 | **No agent ever holds access to the production host or its data** — no credential, shell, database connection, backup file or log of production; agents work only in local, CI and agent-reachable environments. | AGENT_OPERATING_MODEL; PRODUCTION_DATA_SAFETY |
| INF-02 | **Production is operated only by the named human operator** (`<OPERATOR>`; §9): deployments, migrations, backups, restores, the console commands and every secret. | AGENT_OPERATING_MODEL; ARCHITECTURE §17; GAP-013 |
| INF-03 | **Every deployment comes from `main`** and is run by the human operator, never by an agent or an automatic trigger from the repository (§5). | AICWDF §32; GAP-012 |
| INF-04 | **No paid service** is adopted: an option that needs one is recorded under COST_POLICY §4C.2 as an Owner question and never adopted; the existing VPS and free or self-hosted components come first. | COST_POLICY; AICWDF §20 |
| INF-05 | **No secret enters the repository**, a log, an issue or a document: secrets live in environment configuration outside the repository and the web root, with restricted permissions (§4). | SECURITY SX-01, SX-02 |
| INF-06 | **Backups stay on the production VPS** — local backup, never offsite —; the exposure to total VPS loss stays an accepted risk, unchanged unless the Owner decides OQ-26 otherwise. | DIR-009; GAP-018; OQ-26 |
| INF-07 | **Two production applications share the VPS.** The client's VPS runs MULTIPLECORP and a second production application of the client. MULTIPLECORP has its own containers, PostgreSQL instance, Valkey, storage, backups, environment secrets, logs, schedules and deployment project, and shares only the host — its operating system, CPU, memory, disk and network —, the host firewall, the provider account and, in P9's design pending OPI-03, the shared host proxy that terminates HTTPS for both (§2). A deployment, migration, backup, restore drill, restore, rollback, staging stack or drill of MULTIPLECORP never stops, changes or reads the other application, its data or its secrets; its release and maintenance windows stay clear of the other application's (OPI-27); and every capacity figure counts both applications' combined use. | the Owner's conditions in session, 2026-10-19 (§9); the shared host proxy, P9's design (§2) |
| INF-08 | **No provider feature is assumed.** The plan's provider-side features (OPI-29) are unverified and nothing here relies on them; none operates MULTIPLECORP's production, which INF-01 and INF-02 reserve to the named human operator, and before production data exists every feature able to act on the host or its data — automation or AI assistance among them — is confirmed off, or its reach recorded and accepted by the Owner with the client (RELEASE_PLAN GLC-24). A feature verified later is evidence for a decision, never a default. | the Owner's conditions in session; INF-01; INF-02 |

## 2. Topology

AICWDF §20's default topology on the existing VPS of DEP-01 — 4 vCPU, 16 GB RAM, 200 GB NVMe and 16 TB bandwidth, the plan's nominal figures, shared by production and its backups (AS-01) and by the client's second production application (INF-07):

```text
User's browser
  ↓ HTTPS
<APP_DOMAIN> — DNS at the domain's current provider (P9Q-05)
  ↓
The existing VPS
  ├─ the shared host proxy, with the second application: the public HTTPS port, TLS and HTTP→HTTPS (OPI-03)
  ├─ Nginx, MULTIPLECORP's own site file: the static security headers and the login rate limit (H9-02; ARCHITECTURE §1)
  ├─ web: Laravel with Inertia and React, PHP workers (SZ-01)
  ├─ queue workers (SZ-02) and the scheduler (HO-23)
  ├─ PostgreSQL — one instance, the roles of DATABASE §28 (H9-01)
  ├─ Valkey — queue, cache and limiter store, never a system of record (HO-24; SZ-09)
  └─ the private file disk (ARCHITECTURE §12; DATABASE §22) and the local backup area (BACKUP_RECOVERY)
```

- **Containers.** Nginx, the application with its workers and scheduler, PostgreSQL and Valkey run as containers under Docker Compose, which ARCHITECTURE §1 accepts — or under Coolify if OPI-03 records that the VPS already runs it (DIR-079 CB-05) —; PostgreSQL and Valkey sit on a container network only MULTIPLECORP's containers reach (H9-09). **MULTIPLECORP keeps Nginx** (ARCHITECTURE §1; H9-02): its own site file holds the static security headers and the login rate limit — Nginx being the shared host proxy itself where the host's proxy is Nginx, otherwise MULTIPLECORP's own web container, bound privately behind it —, and running MULTIPLECORP without Nginx would change ARCHITECTURE §1's stack, a question for the Owner. **The shared host proxy** alone holds the host's public HTTPS port and terminates TLS for both applications — P9's design pending OPI-03, an existing proxy of the host or one set up for both —, and the proxy in front of MULTIPLECORP is the trusted proxy of WS-11. "Edge proxy" names only DIR-079 CB-08's proxy in front of the domain (P9Q-05 (b); OPI-04).
- **One instance of each store.** A single PostgreSQL instance of MULTIPLECORP's own is sufficient (DATABASE §27) — never one shared with the second application (INF-07) —; no connection pooler — sessions end with the request (SZ-03; HO-22) —, so a transaction-pooling proxy is never added without moving the evidence registration keys first (FU-10).
- **Shared with a second production application** (INF-07). The shared host proxy routes each application's domain to that application's own services; MULTIPLECORP's site configuration is its own file, changed only after a configuration test and applied by a reload that keeps the other application's connections, never by a restart; memory and CPU limits on each of MULTIPLECORP's containers, set from the measured figures (§8), keep one application from starving the other.

## 3. Environments

| Environment | Where | Who works there | Data |
| --- | --- | --- | --- |
| Local | each developer's or agent's own machine | agents and developers | synthetic only — the fictional group (ARJ, BTN, CKP) |
| CI | the public repository's CI, at no cost | agents, through the pipeline | synthetic only; a production-like stack — web, workers, PostgreSQL, Valkey — started for the integration and E2E suites of P8 |
| Staging | DIR-079 CB-04 (P9Q-01): an ephemeral stack the operator starts on the VPS before a release, isolated from production, removed after | the human operator only, since it runs on the production host (INF-01) | synthetic only, never a copy of production (PRODUCTION_DATA_SAFETY) |
| Production | the existing VPS (DEP-01) | the human operator only (INF-02) | the business's data |

The branch and environment model is AICWDF §32's as the repository adopts it: work through pull requests and CI into `main`; a release rehearsed in staging; production deployed from `main` by the operator (§5). P10 owns the release, its rehearsal and the cutover. On the shared VPS staging is resource-capped below the headroom both production applications need and never touches the second one (INF-07); it starts only once the client has given the operator access (OPI-26).

## 4. Processes, configuration and secrets

### 4.1 Processes

- **Web workers.** Up to 16 PHP workers as the starting ceiling within the memory left after the second application's measured use (OPI-27), bound by memory — 60–100 MB each until measured on the VPS (SZ-01) —, each holding at most one database connection and only while a request runs; the measured value replaces the assumption once OPI-07 is recorded.
- **Queue workers and the scheduler.** Two workers on the single main queue and the scheduler (SZ-02); each job's timeout shorter than the queue's retry-after; at most one export at a time across accounts — JB-03's overlap lock with its 12-minute expiry —, and JB-01's unique-job lock lifetime above its 120 s timeout plus the queue wait it tolerates (HO-22). The scheduler's cron entry and the process manager restart every worker on deployment (HO-23); a daily task that misses its minute is not caught up, so a day without a successful run alerts (HO-25; CONCURRENCY_IDEMPOTENCY §15).
- **Connections.** About 16 web + 3 queue and scheduler + 6 administration and reserve = 25 PostgreSQL connections, inside the default limit (SZ-03; HO-22); database sessions end with the request.
- **Memory.** About a quarter of the RAM left after the second application's measured use (OPI-27) for PostgreSQL's shared buffers as the tuning start; the rest for the file cache, the PHP workers, Valkey, the renderer and the hash slots of H6-12 — three slots times the Argon2id memory cost (SZ-04).
- **Valkey** is sized never to evict: its locks carry expiries and would otherwise be eviction candidates, and the second-factor limit's keys carry no expiry (SECURITY AU-05); its policy is `noeviction` or one that evicts only keys with an expiry, with limiter keys unable to evict queue payloads and locks or to fill the store; availability monitored and alerted, authentication required, bound to the private interface (HO-24; SZ-09; H9-09). A failover store is not provisioned in V1: it could shorten an outage at no cost (D-CC-10), but the second-factor keys are served by none, so the gain is small — revisit after measurement. Limiter and lock keys live where no deployment step flushes them (HO-23; PERFORMANCE CP-07).

### 4.2 Configuration

- **Timeouts and retry budgets** — TX-01–TX-10, the runtime role's default statement timeout that page reads rely on included, and RY-02, RY-03 and RY-08 — are configuration this document owns and may change from measurement (HO-21; RY-09; SZ-05).
- **Hashing.** Confirm Argon2id in the production runtime and measure its parameters on the VPS within AU-02's bounds — about 0.25–0.5 s per hash within the memory budget —, otherwise AU-02's bcrypt fallback; generate the login dummy hash from the configured driver and parameters (AU-04); then tune the slots and waits of H6-12 (H9-11; HO-26; PF-39). A change of hashing parameters ends each account's other sessions at its next login, so it is a planned event (RV-10). A later driver change — the bcrypt fallback replaced by Argon2id — follows its own runbook, as AU-02 states it: a planned migration announced to the users, verification relaxed only for a bounded rehash-on-login window or new credential links issued, the window's start and end recorded, never a silent switch (H9-11).
- **Versions.** Record the chosen PostgreSQL and framework versions and re-verify the evidence tables against them — PostgreSQL 18's virtual generated columns included: a generated column a CHECK or a partial index reads is declared STORED (HO-30; DEP-07).
- **Extensions.** `pg_trgm` and `btree_gist`, and for verification the contrib modules `pg_stat_statements` and `auto_explain`, at no cost (DATABASE §31; ARCHITECTURE §20; PERFORMANCE §12 item 6).
- **The go-live setting.** The number allocator's go-live is recorded once, as a setting P9 keeps with the deployment and nobody changes afterwards (HO-32; NM-03); P10 orders the cutover around it.
- **Limiter and cap values** — WS-12, AU-05 and H6-12 — stay assumptions until measured on the VPS (SZ-07).
- **Environment variables** are named in P11's `.env.example` with placeholder values only; their real values exist only on the VPS (INF-05).

### 4.3 Secrets and keys

- **Storage.** `APP_KEY`, the credentials of DATABASE §28's roles, Valkey's password, the Google client secret and every other secret of SX-01 live in environment configuration outside the repository and the web root, readable only by the service account (SX-01; H9-09); backup files carry restricted permissions (SX-07).
- **Rotation.** SX-02's schedule and triggers; the key-compromise incident runbook of AU-21 orders the rotation — a new `APP_KEY` and new HMAC keys first, then every other secret of SX-01 the leak may have included unless the inventory shows it could not (H9-14).
- **Key management** (H9-14): `APP_KEY`'s storage and permissions, the dated key register, the rotation and re-encryption runbook and the key-compromise incident runbook — the maintenance mode on every web process, its flag where every process reads it, before the incident command and lifted only after its closing sweep. The TOTP secrets and recovery codes are encrypted with `APP_KEY`, so a database copy or a backup holds ciphertext only (AU-21; DATABASE §28). The runbooks are §7.1–§7.4, written in PC-4, §7.1–§7.3 rehearsed in its tabletop exercise (BACKUP_RECOVERY §6); each backup generation is paired with the key valid at its time (BACKUP_RECOVERY BKR-08); H9-14's recovery test — a Scenario B recovery rehearsal on staging with synthetic data and synthetic keys (BACKUP_RECOVERY §5) — is owed before go-live (RELEASE_PLAN GLC-26), and the first restore drill of a production recovery point with its paired key is AC-18's evidence (GLC-04); periodic rotation is weighed in P9Q-16 (AU-25; H9-14).
- **The Google OAuth client** of each environment, created with the Owner under the Owner's own Google Cloud access: the consent screen, the scopes `openid email`, each environment's exact redirect URI, the client secret stored and rotated as SX-01 and SX-02 say, and outbound HTTPS from the VPS to Google and to the breached-password range service with the timeouts of AU-03 and AU-22 (H9-13; DEP-10; DEP-11; GAP-042; GAP-046). Google sign-in — and its button, drawn to Google's guidelines (GR-13) — stays off in an environment until its client exists; the free-tier record of COST_POLICY §4C.4 is confirmed at set-up.

### 4.4 Database roles

The roles of DATABASE §28, created by P9 (H9-01; ARCHITECTURE §16; GAP-031; C-53; the database roles of CAP-01 and CAP-13):

| Role | Reached by | Rule |
| --- | --- | --- |
| Migration / owner | a separate Laravel database connection used only by migrations, in deployments and audited maintenance windows | its credential never in the runtime's environment; owner and TRUNCATE privileges kept out of the runtime; a restore never runs as it without the guard verification afterwards (HO-28) |
| Application runtime | web requests and queue workers | the per-table-class privileges of §28; the operator's console commands of AU-14, AU-21 and AU-25 run under it (TX-08) |
| Administration | `GuardMaintenance`, approved data repairs, restore verification | operator-only, each use audited (H9-03; C-61) |
| Read-only reporting | — | optional (EN-07); not provisioned in V1 unless a need appears, an export through it still applying the requester's projection |

## 5. The deployment path

From `main`, run by the human operator, never by an agent (INF-02, INF-03) — the deployment path [AICWDF_ADOPTION](../00-governance/AICWDF_ADOPTION.md) leaves pending for P9 and P10. P9 provides the path; P10 owns each release's plan, rehearsal, cutover and rollback (GAP-012; HO-31).

1. **Ready.** The release is a commit of `main` that passed CI; its staging rehearsal passed (P9Q-01) — the migration, the worker restart and an interrupted-migration case (GAP-012).
2. **Pre-deployment backup.** A backup with verified completion before anything changes; a failed or unverified backup stops the deployment (BK-03; BACKUP_RECOVERY §2).
3. **Maintenance window** where the release needs one; otherwise a rolling deployment that keeps commands valid for one release (HO-31).
4. **Expand.** Expand-only migrations through the migration/owner connection (§4.4).
5. **Deploy and restart.** New code; every queue worker and the scheduler restarted (HO-23); job payload versions honoured; no step flushes Valkey (HO-23; HO-31).
6. **Verify.** Health and smoke checks of the business heartbeat (§6), not HTTP alone (GAP-013); after a release that changes a posting service, `ReconcileGuards` (CONCURRENCY_IDEMPOTENCY §8).
7. **Contract later**, in a following release (HO-31).
8. **Record** the deployed commit and schema version (GAP-012). A failed step stops for the operator's decision: code rollback is not data recovery, and recorded stock or issued documents are never undone by a code rollback (GAP-012; BACKUP_RECOVERY).

The first-Owner installation step writes the audit event of the OWNER assignment with the operator's identity — the host account and the stated name, never a user row —, RG-11's operator rule extended to it under P9's approval (AU-25; GAP-049); it runs under the runtime role, operator-only and audited, never exposed to a web route, and is refused once the workspace is installed, so that every later OWNER account comes only through `permissions.manage` (RG-05; OD-01). The Owner, as the operator, runs it (OPI-09).

**On the shared VPS** (INF-07) every step acts on MULTIPLECORP's own project only — its containers, its database through its own connections, its storage, its backup directory and its schedules —; the shared host proxy changes only as §2 states; the maintenance mode is MULTIPLECORP's, and the other application keeps serving throughout.

## 6. Observability

Free and self-hosted components on the VPS, unless P9Q-04 and P9Q-07 decide otherwise; no new observability platform without need (GAP-013).

- **Logs.** The application's structured logs with request, job and business-action correlation, redacted of secrets and S3/S4 values; log files with restricted permissions; retention within the disk budget (LG-06; H9-08; BACKUP_RECOVERY §3).
- **The business heartbeat** — the signals an HTTP health check hides (GAP-013): the oldest queued job's age, failed jobs, the scheduler's last successful run, `ReconcileGuards`' and the purges' daily success (HO-25), stale renditions and owed work (GAP-007), database connections, free disk (BK-11) and backup freshness (BK-04).
- **Monitored and alerted** (HO-25): every deadlock (`40P01` — each one a defect, CONCURRENCY_IDEMPOTENCY §4.4), retries and restarts, lock waits by class, FAILED rates by cause, a FAILED access change, guard-missing alerts and reconciliation differences (GAP-031; C-61), failed jobs, stale renditions and owed work, a day without a successful `ReconcileGuards` or purge run, slow queries — statements slower than 500 ms (SZ-06).
- **Statement statistics.** `pg_stat_statements` and `auto_explain` in staging and production; the heaviest statements and the table and index sizes reported monthly; crossing half of a threshold opens a measured review, never an automatic change, P8's sizes at 100x being the first baseline (PERFORMANCE §12 item 6; GT-07; PERFORMANCE_TARGETS PFM-18).
- **Uptime.** An external check that sees the VPS from outside — the VPS cannot report its own death — at no cost (P9Q-07), with the business heartbeat inside.
- **Error tracking.** The framework's logs with a self-hosted viewer in V1 (P9Q-04); no error data leaves the VPS without the Owner's decision.
- **Security monitoring.** LG-07's detection signals — failures for one identifier across addresses, throttling spikes, bursts of ACCESS_DENIED, credential and factor events — delivered to the named operator and the Owner (H9-10), with the alerts AU-03, AU-05, AU-18–AU-21 and AU-23 add (H9-15) and the confirmer an OWNER_RECOVERY_LINK_ISSUED names (LG-07); until P9 confirms a channel the operator does not solely control, the Owner's floor is the in-app notice at the next login, *Beranda*'s security row (INFORMATION_ARCHITECTURE §9; AU-14; TM-57; GAP-033's alerting).
- **The alert channel** (H9-06; DEP-08; P9Q-03): a zero-cost channel confirmed and tested by a staging failure drill before go-live (GAP-013); the optional self-service e-mail reset stays off unless that channel is e-mail and P9 confirms it (AU-12; D-SEC-05). GAP-041's accepted risk is reviewed when the operator changes or a channel outside the operator's control is confirmed — unchanged until then.

## 7. Security operations

What SECURITY §10 hands P9 — the H9 items, which PERMISSIONS_MATRIX §13 points to — is answered here and in §§4 and 6.

- **TLS and the proxy** (H9-02; WS-06): free certificates with automatic renewal, plain HTTP redirected, HSTS introduced in stages — `max-age=300`, then one week, then one year with `includeSubDomains` once every subdomain serves HTTPS —, Nginx's static security headers, version banners off; forwarded-header, trusted-proxy and trusted-host configuration (WS-11); a proxy rate limit on the login endpoint that bounds total concurrency (TM-58; HO-24).
- **Network.** MULTIPLECORP adds only its own rules to the host firewall it shares with the second application — HTTPS through the shared host proxy and the operator's own administrative access —, never changing that application's; PostgreSQL and Valkey are on a container network only MULTIPLECORP's containers reach, with authentication, never public and never on an interface the second application can reach (H9-09; SX-03; INF-07).
- **Operator-only commands** (H9-03): `GuardMaintenance` under the administration role (TX-08), and, under the runtime role, the Owner-recovery command (AU-14), the re-encryption and key-compromise incident commands of AU-21, the OWNER-restoration command (AU-25) and the first-Owner installation step (§5) — each run only by the operator, each audited, never exposed to a web route.
- **Runbooks**, written in PC-4: the Owner-account recovery (§7.1; AU-14; H9-07), the key-compromise incident (§7.2; AU-21; H9-14) and the post-incident recovery of AU-25 with the carried cases of GAP-050 (§7.3), rehearsed in its tabletop exercise (BACKUP_RECOVERY §6; GAP-051), and the key rotation and re-encryption (§7.4; AU-21; SX-02), written after it and not rehearsed. WF-ACC-01's Owner-account recovery is §7.1's human procedure.
- **Retention and cleanup jobs** (H9-04; HO-27; HO-29), over the framework's own tables DATABASE §4 leaves to P5 and P9: session-store garbage collection — a session row's raw address and user agent go with it —; the credential-token purge; queue and failed-job retention with identifier-only payloads, failed-job retention of 7 days, so that `command_log`'s 30 days cover the queue's retry window plus it (CI-07); `command_log`'s purge JB-08 under DATABASE §19's trigger-guarded DELETE (CI-07); the audit events that record a start of numbering kept for as long as the company numbers here (NM-03); export-file cleanup, expired export requests included, and a withheld file removed at once (RV-08; FL-09); an upload never linked to any record removed after a retention window of 7 days, the starting value (DATABASE §19); business audit never deleted by an application role, security events kept at least 12 months and technical logs 30 days, starting values held within the disk budget (LG-06; BACKUP_RECOVERY §3).
- **The undisclosed security events** — a duplicate the uploader may not see, an import collision outside the preparer's scope — written by the post-response step SECURITY LG-02 prefers (UXH-09; HO-38).
- **Uploads.** The image library's policy — only the allowlisted input coders, no delegates, remote URLs or indirect reads, memory, time and pixel limits (H9-12; FL-03); the optional malware scanner only with confirmed memory headroom (H9-05; FL-11; P9Q-06).

### 7.1 The Owner-account recovery runbook

AU-14 and H9-07, with the tabletop's findings (BACKUP_RECOVERY §6). Run by the operator alone, never by an agent (SX-04).

1. **The last resort.** Outside a key-compromise run, the Owner's own recovery codes come first (AU-20 b) — a code a sustained second-factor cooldown keeps from use counts as not usable (AU-05) —, then another active Owner (AU-20 d); the operator records why neither can serve (K3). After the incident command, recovery follows §7.3.
2. **Identity in person.** The operator verifies the Owner in person through the private contact record of the Owners, made before go-live and kept outside the application and the VPS, never through contact details the application holds or a session or e-mail that may be compromised (TT-F12); the verification is written into the private incident record before the command runs, since the command cannot check an identity (TT-F13). When the Owner is the operator the record says so, and no independent verification is claimed (TT-F15; P9Q-14).
3. **The audit trail first.** The account's removals during an open compromise window are checked before any issue, a pending restoration run coming first (H9-07).
4. **The command**, on the production host, issues an OWNER_RECOVERY link for an active account holding the OWNER role and refuses any other; it never sets, reads or reveals a password or a TOTP secret, and its events record the operator's identity — the host account and the stated name, never a user row. **During a key-compromise run, until its recovery ends** (AU-14's guard), a link without a named confirmer goes only to an account the audit trail shows held the OWNER role and was active at the run's recorded window start; for any other account holding the OWNER role the operator names the recovered pre-window Owner who confirmed the account in the review — that confirmation obtained from the confirmer in person, never through the account being recovered, and written into the private record —, and the command checks the confirmer's standing before it issues (H9-07).
5. **The hand-over.** The Owner is present and opens the link on the Owner's own device; the link travels outside the application and is never written into a log, a ticket or the repository.
6. **The use.** Setting the password clears the TOTP secret and the recovery codes, ends every session, revokes the other unused links and unlinks Google (AU-20 e); the Owner enrols TOTP at once.
7. **The alert and the record.** OWNER_RECOVERY_LINK_ISSUED alerts the operator, the Owner — through a channel the operator does not solely control once one exists, otherwise by the in-app notice at the next login (LG-07; H9-06) — and the confirmer it names (AU-14); the private record holds the time, the account, the reason, the confirmer, the link's issue and use and the result, never the token.

### 7.2 The key-compromise incident runbook

AU-21 and H9-14, with the tabletop's findings (BACKUP_RECOVERY §6).

1. **The alarm.** The operator — the Owner today — is told by the alert channel or finds the sign; the private incident record takes first the detection time (WIB), the incident's type, its source and the environments possibly affected, its status — suspected —, and the evidence, masked, with no key value or credential (TT-F03). The client is told once the incident is confirmed or may affect the service or its data (TT-F02).
2. **The window's start** (AU-25), set with the Owner at the earliest the evidence allows — the leaked key's creation time in the dated key register when the leak cannot be dated, the earliest such time when the leak included several keys —, recorded once, in the private record and as the incident command's input, never guessed: an unregistered or ambiguous creation time is §7.3's first halt. The register's first entry is written when the installation's key is created, and every later key is entered at its creation (TT-F04).
3. **The inventory and the scenario.** Host access, network and firewall, credentials, logs and access through the other application on the VPS; a binding to the private interface never proves that there was no write access (TT-F07). Scenario A only when the inventory excludes write access, otherwise Scenario B: the host taken out of service — which stops the second application too, so the client is told and involved without delaying containment (TT-F06; INF-07).
4. **Rotate before the command** (TT-F09). A new `APP_KEY` and new HMAC keys in the environment, then every other secret of SX-01 the leak may have included — the database role credentials, the Valkey password, the Google client secret, any mail credential —; the leaked key listed among the previous keys only if the ciphertext inventory finds data that must survive, and then retired from that list as soon as every such ciphertext is re-encrypted under the new key (AU-21).
5. **The maintenance mode on every process** (TT-F10): without a bypass on every web process of MULTIPLECORP, each confirmed by a forbidden request answered as refused; an unconfirmed process stops the run.
6. **The command**, AU-21's protocol (a)–(d): the opening sweep, the maintenance mode kept, every account processed — those created during the run included —, the closing sweep; it refuses to start or continue outside the maintenance mode and, on a restart, any other window start.
7. **Lifted only when safe.** The maintenance mode is lifted after the closing sweep, every step verified and the security and recovery-readiness checks met; a failed step keeps it and stops the run.
8. **MULTIPLECORP only** (TT-F11): every action applies to MULTIPLECORP's environment; the second application's data and credentials change only under its own incident procedure.
9. **Then recovery**, §7.3.

### 7.3 The post-incident recovery runbook

AU-25, H9-14 and GAP-050's carried cases, with the tabletop's findings (BACKUP_RECOVERY §6).

1. **The order.** Restoration by the OWNER-restoration command, then recovery through §7.1's command, path (e), then the review; no account is touched before its restoration.
2. **Restoration.** Each pre-window Owner removed during the window is verified in person (§7.1 step 2) and restored, temporarily; before the review the restored Owners are shown with the account and the actor and time of the removal each reversed; the command refuses a window start other than the recorded one, an account that is not a pre-window Owner and a second restoration of one account in one run (M-46) (TT-F14).
3. **Recovery.** Each pre-window Owner through §7.1, each with its own audit event and the recorded window start.
4. **The review** (AU-25). The recovered pre-window Owners review the accounts, roles, grants, credential links and Google links **created or changed** during the window, leaving untouched the account of a pre-window Owner not yet restored and recovered — AU-12's post-incident link never going to it. Suspect Google links are unlinked through the OWNER unlink (AU-24); a confirmed account holding the OWNER role is recovered through path (e) and loses its Google link; a confirmed account activated during the window without the OWNER role receives a plain RESET link; any account or change the review does not confirm, the Owner reverses through the ordinary commands — revoking the grant, removing the role or deactivating the account —, and a removal confirmed as legitimate is made again, ending its temporary restoration (GAP-048); every other account receives a plain AU-12 RESET link that keeps its Google link, an account created or activated during the window only once the review confirms it; grants revoked during the window are restored through the ordinary Owner commands; each person then sets a password, signs in and, when high-risk, enrols TOTP (AU-18). A change the recovered pre-window Owners dispute is neither confirmed nor reversed while the dispute lasts: neither Owner is presumed right, no new deciding authority is invented, the protections stay — the account as the incident command left it, no RESET link —, the change is not treated as validated, and the review, with the end of recovery (step 8), is not complete until the dispute is settled by the rule P9Q-15 decides; the change is then confirmed or, unconfirmed, reversed as above (AU-25; TT-07; TT-F17). P9Q-15 is a governance rule outside the application.
5. **Scenario B** (AU-25). No step of Scenario A runs on the untrusted state, which is replaced rather than recovered: the host goes out of service as soon as Scenario B is chosen — the second application with it, the client told and involved (TT-F06) —, an incident command already run there serving only to contain it. Recovery uses only a backup or snapshot independently verified — its integrity, date and source — as predating the compromise and trustworthy; a backup on the compromised host is never presumed clean, an off-server copy proves nothing by existing (TT-F21; TT-F24; OQ-26), and **if no such backup or snapshot exists the in-application path stops for the Owner's incident decision outside the application**. The restored state, on a clean environment, is brought up only once the inventory excludes the attacker's write access to the restored database and server — otherwise recovery stops for that decision —; before it serves any request the maintenance mode is kept, new keys are installed with the leaked key never listed, every other secret of SX-01 the leak may have included is rotated again as it stands in the restored environment and services, the incident command runs in full under the new keys and every precondition is verified (TT-F23). Pre-window status is read at the window's start or, when the backup is older, at the backup's time, and the restored Owners are shown with that time; MULTIPLECORP's recovery stays isolated from the second application, which is never presumed safe and recovers by its own procedure (TT-F08).
6. **The halts** (AU-25). No pre-window Owner certain: on a trusted state, Scenario B; on a restored state, the Owner's decision outside the application. No pre-window Owner available: the same — or, if the Owner approves OQ-28, the authorized Owner's decision before any restore (TT-F29). An undatable leak of the installation's key always meets the first halt, since its creation precedes every OWNER assignment (TT-F05; P9Q-16). No Owner is created or guessed, and OWNER authority is never given to the operator, the client or anyone not authorized (TT-F16; TT-F25); the decision outside the application covers isolation, evidence, recovery from verifiable sources, a clean rebuild and every data-loss risk, each recorded and approved by its authority (TT-F22).
7. **A corrected window start** — narrower, earlier or mistyped — is a new run of the incident command with that start and AU-25's recovery again, the earlier run's decisions shown to the recovered Owners; the start's history, its corrections and their reasons stay in the private record outside the application and the VPS, never deleted (TT-F28; GAP-050).
8. **The end of recovery** is recorded only when every active account holding the OWNER role is a pre-window Owner of the run or has a recovery issue in the run naming a recovered pre-window Owner as its confirmer — AU-14's guard, which the command checks —; the review is complete; every pre-window Owner whose removal the audit trail shows has been restored, or the Owner's incident record outside the application states that person unavailable; no account has been newly made an Owner before the end — one that was is demoted before it and reinstated after it —; every action AU-14 and AU-25 require is done and recorded; and no security decision is open or disputed (AU-25; TT-F18; TT-12). Recorded early, the end would leave a removed pre-window Owner unrestorable, since the OWNER-restoration command refuses once the run's recovery has ended. GAP-050 recommends recording the end atomically in one transaction under the OWNER role row's lock, a competing role change taking its place in a deterministic order — a mechanism RV-18 does not yet state, which P11 adopts under GAP-050 and P8 tests (TT-F30) —; a later change is an ordinary Owner act.
9. **The record.** Every decision, reversal and open matter is in the private incident record with its audit events; nothing secret is in it or in the repository.

### 7.4 The key rotation and re-encryption runbook

AU-21, SX-02 and H9-14 — a planned rotation, never an incident (§7.2).

1. **The register.** Each key's creation, use and retirement is a dated entry of the key register in the operator's secret store, never in the repository (AU-21; TT-F04).
2. **The new key current, the old one listed.** A new `APP_KEY` becomes current and the old one goes into `APP_PREVIOUS_KEYS`, so that every existing secret stays readable (SX-02); the HMAC keys rotate only when the rotation's reason requires it, since their rotation breaks log linkage and invalidates every outstanding credential token, which the Owner then reissues (SX-02).
3. **Re-encryption.** The operator runs AU-21's re-encryption command, which re-encrypts every TOTP secret and recovery-code set under the current key and records TOTP_SECRETS_REENCRYPTED.
4. **The verified count.** Only when the count of re-encrypted accounts equals the enrolled ones is the old key retired from the previous-keys list and its retirement entered in the register.
5. **The backups.** A generation taken before the rotation stays paired with the key valid at its time, which is kept while that generation is retained (BACKUP_RECOVERY BKR-08).
6. **When.** On SX-02's triggers; the one rotation after the first Owner's set-up and a periodic rotation are P9Q-16's.

## 8. Capacity and cost

- **Capacity.** The VPS's actual free space, utilization, bandwidth period and workload are measured, never taken from the nominal figures (DEP-01; AS-01; OPI-02); both production applications' combined use counts — the second application's measured CPU, memory, disk and bandwidth (OPI-27) beside MULTIPLECORP's —, and PERFORMANCE's sizing inputs SZ-01–SZ-04 are set from what remains (INF-07); the shared-disk budget, its thresholds and its peaks are BACKUP_RECOVERY §3's, with PERFORMANCE's growth inputs (SZ-08; GT-06) and PF-28's export row bound — 200,000 rows as the starting value — as a disk guard.
- **Cost.** No new recurring cost: the components here are the VPS's own and free or self-hosted software; the zero-cost services — the Google OAuth client, the authenticator apps, the breached-password range service, and once confirmed the alert channel of OPI-05 and the external uptime check of DIR-079 CB-10 — are COST_POLICY's inventory with their free-tier records. **Paid-service exception registry:** empty. Every option of §10 that needs a paid service is recorded there under COST_POLICY §4C.2 and not adopted.

## 9. The operator's inputs

What the operator supplies, outside the repository where it is sensitive — never assumed here. "Recorded" means written into this document as a fact without a secret; "kept" means held only by the operator. The Owner answered in session on 2026-10-19 as the operator: each answer is recorded below as given, and each input not available is an obligation of [RELEASE_PLAN §9](../10-release/RELEASE_PLAN.md#9-the-go-live-checklist) before the step the last column names — staging or go-live —, never a result (SP-87; `DIR-081` OB-10).

**The facts given in session.** The VPS is a managed plan of a hosting provider with DEP-01's nominal figures — 4 vCPU, 16 GB RAM, 200 GB NVMe and 16 TB bandwidth —, bought by the client and held in the client's provider account; its runtime state is not verified. The Owner is the primary technical operator. The client has not yet given the operator access to the VPS or to its management panel. The VPS will run two production applications of the client: MULTIPLECORP and a second one (INF-07). The provider's name and the second application's name are kept out of this public repository.

**The Owner's conditions for the shared VPS**, stated in session and applied in this document, BACKUP_RECOVERY and RELEASE_PLAN: (1) the two applications isolated in application, database, storage, environment secrets and deployment (INF-07); (2) capacity computed from their combined use (§8; BACKUP_RECOVERY §3); (3) a backup, restore or rollback of one never damaging the other (§5; BACKUP_RECOVERY BKR-07; RELEASE_PLAN RBK-06); (4) no provider backup, security or configuration feature assumed active without verification (INF-08); (5) every input not available recorded as a pre-staging or pre-go-live obligation, never as a PASS (RELEASE_PLAN §9); (6) no server access, installation or request for a credential now (INF-01).

| OPI | Input | Where it goes | Recorded in PC-4 | Needed before |
| --- | --- | --- | --- | --- |
| OPI-01 | The named human operator and an alternate, `<OPERATOR>` (GAP-013) | recorded by role, not by account | **The Owner** is the operator; an alternate is **not yet named** — DIR-079 CB-14's rule, the Owner as the alternate, cannot hold while the Owner is the operator (P9Q-14) | go-live, for the alternate |
| OPI-02 | The VPS's measured free disk, current usage, operating system and the bandwidth allowance's period (DEP-01; AS-01) | recorded as figures | **Not available** — only the plan's nominal 200 GB NVMe and 16 TB bandwidth are known | staging |
| OPI-03 | What the VPS already runs — Docker, Docker Compose, Coolify, Nginx, other services — and its firewall tooling (P9Q-02) | recorded | **Not known**, and none assumed installed; DIR-079 CB-05's rule — Docker Compose unless the VPS already runs Coolify — is applied when it is recorded | staging |
| OPI-04 | The domain's DNS provider and whether a proxy fronts it (P9Q-05) | recorded | **Not available**; DIR-079 CB-08's rule is applied when it is recorded | go-live |
| OPI-05 | A zero-cost alert or mail channel the operator can confirm (H9-06; DEP-08; P9Q-03) | recorded; its credentials kept | **Not chosen.** Recommended under DIR-079 CB-06: a Telegram bot sending to the operator's and the Owner's own phones — one phone while the Owner is the operator, the alternate's too once one is named (P9Q-14); zero cost, a push to the phone, one outbound HTTPS call from the VPS, its token a secret kept as SX-01 says, each message a neutral signal name with no S3 or S4 value —, with the floor of the in-app notice and the daily look kept; the operator confirms it and the failure drill tests it; its COST_POLICY §4C.4 record is written before it is adopted (RELEASE_PLAN GLC-03). A channel the operator controls alone is not the Owner's independent channel of AU-14 and LG-07 (GAP-041) | go-live |
| OPI-06 | The Owner's Google Cloud access for the OAuth clients (DEP-10; H9-13) | used with the Owner; the secret kept | **Not confirmed**; until it is, Google sign-in stays off (GAP-042) | go-live, if Google sign-in is to be on |
| OPI-07 | Measured PHP worker memory, Argon2id timing and PostgreSQL behaviour on the VPS (SZ-01; H9-11; HO-26) | recorded as figures | **Not available**; DIR-079 CB-09's rule is applied to the headroom left after sizing both production applications (INF-07) — until then no scanner is installed and FL-01–FL-06 apply | go-live |
| OPI-08 | Outbound HTTPS from the VPS to Google and to the breached-password range service (DEP-11; H9-13) | recorded as tested | **Not tested** | go-live |
| OPI-09 | The installation step's operator identity — host account and stated name (GAP-049) | recorded in the audit event, not here | **The Owner**, as the operator, runs the installation step; the identity is written into its audit event then | go-live |
| OPI-26 | Access to the VPS — and to the provider's panel where an operation needs it — granted by the client to the operator, never to an agent (INF-01) | recorded as granted, by role | **Not yet given** | staging |
| OPI-27 | The second production application's measured use of CPU, memory, disk and bandwidth, and its own backup, maintenance and deployment windows (INF-07) | recorded as figures and times | **Not available** | staging |
| OPI-28 | Who holds the provider account, by role, and whether its sign-in uses a second factor — whoever holds it can reach, snapshot or delete the VPS and its local backups (GAP-018) | recorded by role | **The client** holds it; its second factor not recorded | go-live |
| OPI-29 | The plan's provider-side features and whether each is active, verified on the panel; every feature able to act on the host or its data — automation or AI assistance among them — confirmed off before production data exists, or its reach recorded and accepted by the Owner with the client (INF-08) | recorded | **Not verified**; none relied on | staging; before production data for the confirmation (RELEASE_PLAN GLC-24) |
| OPI-30 | The application's domain `<APP_DOMAIN>` and the name staging uses, with the TLS certificates they need (§2; DEP-10's redirect URIs) | kept, never recorded here | **Added in Part 5's review, not asked in session — owed** | staging |
| OPI-31 | Every holder of administrative access to the host or to the provider account — the second application's operators included —, by role (GAP-041; GAP-018) | recorded by role | **Added in Part 5's review, not asked in session — owed**; the client holds the provider account (OPI-28) | go-live |

## 10. Owner questions

Each Owner-level choice of this document, with its options and one recommendation: P9Q-01–P9Q-07 decided under DIR-079, the rules that wait on an operator input applied when that input is recorded, before the step RELEASE_PLAN §9 names (`DIR-081` OB-10); P9Q-14–P9Q-16 for OG-3. An option needing a paid service is recorded under COST_POLICY §4C.2 and never adopted.

### P9Q-01 — Where staging runs and who operates it

- **Options.** (a) An **ephemeral staging stack on the existing VPS**, started by the operator before a release with synthetic data, isolated — its own containers, database, Valkey and storage, resource-capped —, the release rehearsed there, then removed; the operator alone runs it (INF-01). Cost: none. Disk: a transient term of BACKUP_RECOVERY §3 during the release window. (b) A **standing staging stack on the VPS**: always available; a permanent share of RAM and disk beside production; cost none. (c) **A separate paid server for staging**: recorded under COST_POLICY §4C.2, not adopted. In every option CI runs the production-like stack for agents (§3).
- **Recommendation.** (a): GAP-012's rehearsal and GAP-013's failure drill need a production-like place, and only the release window needs it; it costs nothing and takes no standing share of the shared VPS.
- **Decided — [DIR-079](../00-governance/DECISION_LOG.md#dir-079-dir-080-obs-031-and-tech-038--p7-ux-re-baseline-c2-evidence-and-owner-decisions-run-pc-3-stage-3-completed-stage-4-and-gate-p7) CB-04.** Option (a): an ephemeral staging stack on the existing VPS, run by the operator, with synthetic data only.

### P9Q-02 — The deployment tooling on the VPS

- **Options.** (a) **Docker Compose**, run by the operator — ARCHITECTURE §1's accepted baseline; (b) **Coolify**, self-hosted — AICWDF §20's default routing — only if the VPS already runs it, since its control plane takes RAM and disk; (c) services installed directly on the host.
- **Recommendation.** (a), unless OPI-03 shows the VPS already runs (b): the fewest moving parts on a shared host, and nothing new for the operator to learn if the host already uses it.
- **Decided — [DIR-079](../00-governance/DECISION_LOG.md#dir-079-dir-080-obs-031-and-tech-038--p7-ux-re-baseline-c2-evidence-and-owner-decisions-run-pc-3-stage-3-completed-stage-4-and-gate-p7) CB-05.** Option (a): Docker Compose — unless OPI-03 records that the VPS already runs Coolify; then (b). **The operator input its rule waits on:** OPI-03, what the VPS already runs (§9); the rule is applied when the input is recorded, before the step RELEASE_PLAN §9 names (`DIR-081` OB-10), no further decision of the Owner being needed unless the rule cannot be met.

### P9Q-03 — The alert channel

- **Options.** (a) An **existing zero-cost mail channel**, only if the operator confirms one (DEP-08: never assumed); (b) a **zero-cost push channel** to the operator's and the Owner's own phones; (c) the **floor only**: the in-app notice at the Owner's next login and the operator's daily look at the heartbeat; (d) a paid SMS or mail service — COST_POLICY §4C.2, not adopted.
- **Recommendation.** (b), with (c) always kept as the floor, the Owner's part of it a channel the Owner controls — which also lets GAP-041's accepted risk be reviewed.
- **Decided — [DIR-079](../00-governance/DECISION_LOG.md#dir-079-dir-080-obs-031-and-tech-038--p7-ux-re-baseline-c2-evidence-and-owner-decisions-run-pc-3-stage-3-completed-stage-4-and-gate-p7) CB-06.** Option (b): a zero-cost push channel to the operator's and the Owner's own phone, with (c) always kept as the floor; the channel is confirmed by OPI-05 and tested by the staging failure drill before go-live (GAP-013); no paid channel. **The operator input its rule waits on:** OPI-05, the zero-cost channel the operator confirms (§9); the rule is applied when the input is recorded, before the step RELEASE_PLAN §9 names (`DIR-081` OB-10), no further decision of the Owner being needed unless the rule cannot be met.

### P9Q-04 — Error tracking

- **Options.** (a) **The framework's logs with a self-hosted log viewer**; (b) a **self-hosted error tracker** on the VPS — more memory; (c) a free-tier hosted tracker — error data, possibly with S3 or S4 values, leaves the VPS, and free-tier limits apply (COST_POLICY §4C.4).
- **Recommendation.** (a) for V1; (b) if errors become hard to triage after go-live.
- **Decided — [DIR-079](../00-governance/DECISION_LOG.md#dir-079-dir-080-obs-031-and-tech-038--p7-ux-re-baseline-c2-evidence-and-owner-decisions-run-pc-3-stage-3-completed-stage-4-and-gate-p7) CB-07.** Option (a): the framework's logs with a self-hosted viewer.

### P9Q-05 — DNS and an edge proxy

- **Options.** (a) **DNS at the domain's current provider**, TLS on the VPS (§7); (b) a **free edge proxy** in front of the VPS — shielding, at the price of TLS terminating at the edge, the trusted-proxy configuration of WS-11 and a free-tier dependency.
- **Recommendation.** (a), unless OPI-04 shows the domain already uses (b).
- **Decided — [DIR-079](../00-governance/DECISION_LOG.md#dir-079-dir-080-obs-031-and-tech-038--p7-ux-re-baseline-c2-evidence-and-owner-decisions-run-pc-3-stage-3-completed-stage-4-and-gate-p7) CB-08.** Option (a): DNS at the current provider with TLS on the VPS — unless OPI-04 records that the domain is already behind an edge proxy; then (b), with WS-11's trusted-proxy configuration. **The operator input its rule waits on:** OPI-04, the domain's DNS provider and any proxy in front of it (§9); the rule is applied when the input is recorded, before the step RELEASE_PLAN §9 names (`DIR-081` OB-10), no further decision of the Owner being needed unless the rule cannot be met.

### P9Q-06 — The optional malware scanner

- **Options.** (a) **None in V1** — uploads validated by FL-01–FL-06; (b) a **self-hosted scanner** when the measured memory headroom allows it (H9-05; FL-11).
- **Recommendation.** decide on OPI-07: (b) if the headroom after production sizing holds the scanner, otherwise (a).
- **Decided — [DIR-079](../00-governance/DECISION_LOG.md#dir-079-dir-080-obs-031-and-tech-038--p7-ux-re-baseline-c2-evidence-and-owner-decisions-run-pc-3-stage-3-completed-stage-4-and-gate-p7) CB-09.** Decided by OPI-07's measured figures: (b), a self-hosted scanner, if the memory headroom after production sizing holds it; otherwise (a). FL-01–FL-06 apply either way. **The operator input its rule waits on:** OPI-07, the measured memory on the VPS (§9); the rule is applied when the input is recorded, before the step RELEASE_PLAN §9 names (`DIR-081` OB-10), no further decision of the Owner being needed unless the rule cannot be met.

### P9Q-07 — Uptime monitoring from outside

- **Options.** (a) a **free external uptime check** — a free-tier dependency recorded under COST_POLICY §4C.4; (b) **no external check** — only the business heartbeat, which cannot report the VPS's own outage.
- **Recommendation.** (a): only an outside check sees the VPS down.
- **Decided — [DIR-079](../00-governance/DECISION_LOG.md#dir-079-dir-080-obs-031-and-tech-038--p7-ux-re-baseline-c2-evidence-and-owner-decisions-run-pc-3-stage-3-completed-stage-4-and-gate-p7) CB-10.** Option (a): a free external uptime check.

### P9Q-14 — An alternate operator while the Owner is the operator

- **Context.** DIR-079 CB-14 made the Owner the alternate of a named operator (BACKUP_RECOVERY P9Q-13). In session the Owner is the operator (OPI-01; OPI-24), so that rule cannot hold, and no one else can take the operator's duty — the alerts, backups, restores and operator-only commands of INF-02 — while the Owner is unavailable (GAP-013). The tabletop exercise found the same for AU-14 (BACKUP_RECOVERY §6, TT-F15): the in-person verification and the Owner's alert have no second, independent person, and the Owner asks for an alternate or a substitute control that fits AU-14.
- **Options.** (a) **An alternate named before go-live**, preferably someone the client designates, who receives the alerts (OPI-05), puts MULTIPLECORP into maintenance mode when a signal needs it and follows the written runbooks, given host access only while acting — which widens the trust AU-14 places in whoever holds host access and so triggers GAP-041's review (RISK-005) —; (b) **one operator in V1**, the Owner accepting that operator duty waits for the Owner — recorded as an accepted risk with its review trigger —, the runbooks written so that a replacement can take over; (c) **the provider's support as the fallback** — it can restart or restore the VPS but cannot operate the application, and it acts on the whole VPS, the second application included (INF-07).
- **Recommendation.** (a): a second person who can at least receive the alerts and take the first steps keeps a failing backup or a stalled queue from going unnoticed for days, at no cost; the operator-only commands stay with whoever holds host access under INF-02.

### P9Q-15 — Who settles a disagreement between recovered pre-window Owners

- **Context.** AU-25 gives the post-incident review to the recovered pre-window Owners, each holding the same OWNER authority. The tabletop exercise found no rule for a disagreement between them (BACKUP_RECOVERY §6, TT-F17), and the Owner asked that none be invented; until it is settled neither Owner is presumed right, the disputed change stays unvalidated and the protections stay (§7.3 step 4).
- **Options.** (a) **The business's own governance, outside the application**: the client, as the business and the VPS's owner, names in advance who decides between its Owners, and the runbook records that decision before any disputed change is made again; (b) **one Owner named in advance** as the decider of a dispute in a recovery review, recorded in the private Owner contact record — a procedural rule, the application unchanged; (c) **no rule**: the disputed change stays unresolved, with the protections in place, until the Owners agree.
- **Recommendation.** (a): who decides between the business's Owners belongs to the business's own governance, and naming it in advance keeps a review from stalling; the application and AU-25 stay unchanged.

### P9Q-16 — Rotating the application key: once after the first Owner, and periodically

- **Context.** AU-25 and H9-14 give P9 the weighing of periodic rotation of `APP_KEY`, which shortens the window when a leak cannot be dated. The tabletop exercise found that the installation's key is created before the first OWNER assignment, so an undatable leak of that key leaves no pre-window Owner and the in-application recovery always halts (BACKUP_RECOVERY §6, TT-F05). At TT-10 the Owner recommended one rotation after the first Owner is set up and before go-live, naming it OQ-27 in session; it is a P9 matter, since AU-25 and H9-14 leave the rotation's weighing to P9 and SX-02 already provides the rotation with its re-encryption (§7.4).
- **Options.** (a) **One rotation after the first Owner's set-up, before go-live**, by §7.4 — before any business data, so its re-encryption touches only the first Owner's TOTP data —, **then a periodic rotation**, for instance yearly, by the same runbook; (b) the one rotation only; (c) neither: rotation only on SX-02's triggers.
- **Recommendation.** (a): the one rotation gives the likeliest key compromise — an undatable one — a pre-window Owner, as the Owner recommended, and a yearly rotation bounds every later window at a year; each rotation costs one re-encryption run, and the HMAC keys rotate only when their reason requires it. It does not cover the case of no pre-window Owner available, and it replaces neither an off-server backup nor Scenario B.

## 11. Coverage

Every requirement an approved document hands to P9, found by a scripted search of P0–P7's normative documents for "P9" and joined with SP-67's list, each mapped by script to the sections of this document (INF) and of [BACKUP_RECOVERY](BACKUP_RECOVERY.md) (BKR) that answer it. Run PC-4 re-ran it on 2026-10-19 over P0–P7 and the drafts of P8 and P10, with the Owner's answers and conditions given in session (§9): 127 units of the search, each named by a row — 4 hits that only name P9's drafts as a searched source are no requirement —, and every row's identifier found in a section it cites. Gate OG-3 checks it. **182 requirements, 182 answered, 0 missing.**

| Source | Requirement | Answered in |
| --- | --- | --- |
| AICWDF §20 | AICWDF §20 — containers | INF §2 |
| AICWDF §20 | AICWDF §20 — Nginx | INF §2, INF §7, INF §9 |
| AICWDF §20 | AICWDF §20 — environment variables | INF §4 |
| AICWDF §20 | AICWDF §20 — secrets | INF §4 |
| AICWDF §20 | AICWDF §20 — staging | INF §3, INF §5, INF §10, BKR §3 |
| AICWDF §20 | AICWDF §20 — production | INF §1 |
| AICWDF §20 | AICWDF §20 — logs | INF §6 |
| AICWDF §20 | AICWDF §20 — metrics | INF §6 |
| AICWDF §20 | AICWDF §20 — uptime | INF §6 |
| AICWDF §20 | AICWDF §20 — error tracking | INF §6 |
| AICWDF §20 | AICWDF §20 — security monitoring | INF §6 |
| AICWDF §20 | AICWDF §20 — backup | BKR §2 |
| AICWDF §20 | AICWDF §20 — offsite backup | INF §1, BKR §1 |
| AICWDF §20 | AICWDF §20 — restore | BKR §2, BKR §4 |
| AICWDF §20 | AICWDF §20 — restore testing | BKR §5 |
| AICWDF §20 | AICWDF §20 — incident visibility | INF §5, INF §6, INF §10 |
| AICWDF §20 | AICWDF §20 — recurring-cost inventory | INF §8 |
| AICWDF §20 | AICWDF §20 — paid-service exception registry | INF §8 |
| AICWDF §32 | AICWDF §32 — the branch and environment model, with P10 | INF §1, INF §3 |
| SECURITY §10 | H9-01 | INF §2, INF §4 |
| SECURITY §10 | H9-02 | INF §2, INF §7 |
| SECURITY §10 | H9-03 | INF §4, INF §7 |
| SECURITY §10 | H9-04 | INF §7 |
| SECURITY §10 | H9-05 | INF §7, INF §10 |
| SECURITY §10 | H9-06 | INF §6, INF §9 |
| SECURITY §10 | H9-07 | INF §7 |
| SECURITY §10 | H9-08 | INF §6, BKR §3 |
| SECURITY §10 | H9-09 | INF §2, INF §4, INF §7 |
| SECURITY §10 | H9-10 | INF §6 |
| SECURITY §10 | H9-11 | INF §4, INF §9 |
| SECURITY §10 | H9-12 | INF §7 |
| SECURITY §10 | H9-13 | INF §4, INF §9 |
| SECURITY §10 | H9-14 | INF §4, INF §7, BKR §6 |
| SECURITY §10 | H9-15 | INF §6 |
| SECURITY | AU-02 | INF §4 |
| SECURITY | AU-12 | INF §6 |
| SECURITY | AU-14 | INF §4, INF §6, INF §7, BKR §6 |
| SECURITY | AU-21 | INF §4, INF §6, INF §7, BKR §6, BKR §8 |
| SECURITY | AU-25 | INF §4, INF §5, INF §7, BKR §4, BKR §6 |
| SECURITY | EN-07 | INF §4 |
| SECURITY | WS-06 | INF §7 |
| SECURITY | FL-09 | INF §7, BKR §3 |
| SECURITY | FL-11 | INF §7, INF §10 |
| SECURITY | LG-06 | INF §6, INF §7, BKR §3 |
| SECURITY | LG-07 | INF §6 |
| SECURITY | SX-01 | INF §1, INF §4 |
| SECURITY | SX-02 | INF §1, INF §4 |
| SECURITY | TM-57 | INF §6 |
| SECURITY | D-SEC-05 | INF §6 |
| V1_SCOPE | BK-01 | BKR §1, BKR §2 |
| V1_SCOPE | BK-02 | BKR §1, BKR §2 |
| V1_SCOPE | BK-03 | INF §5, BKR §2 |
| V1_SCOPE | BK-04 | INF §6, BKR §2 |
| V1_SCOPE | BK-05 | BKR §1, BKR §2 |
| V1_SCOPE | BK-06 | BKR §2 |
| V1_SCOPE | BK-07 | BKR §1, BKR §2, BKR §4 |
| V1_SCOPE | BK-08 | BKR §1, BKR §2, BKR §4 |
| V1_SCOPE | BK-09 | BKR §1, BKR §2, BKR §5 |
| V1_SCOPE | BK-10 | BKR §1, BKR §2 |
| V1_SCOPE | BK-11 | INF §6, BKR §2, BKR §3 |
| V1_SCOPE | BK-12 | BKR §1, BKR §2 |
| V1_SCOPE | DEP-01 | INF §2, INF §3, INF §8, INF §9, BKR §3, BKR §7 |
| V1_SCOPE | DEP-02 | BKR §7 |
| V1_SCOPE | DEP-07 | INF §4 |
| V1_SCOPE | DEP-08 | INF §6, INF §9, INF §10 |
| V1_SCOPE | DEP-10 | INF §4, INF §9 |
| V1_SCOPE | DEP-11 | INF §4, INF §9 |
| V1_SCOPE | V1_SCOPE — the 200 GB disk budget | BKR §3 |
| V1_SCOPE | V1_SCOPE — the warning and critical thresholds | BKR §3 |
| ACCEPTANCE_CRITERIA | AC-18 | BKR §1, BKR §2, BKR §5 |
| ACCEPTANCE_CRITERIA | AC-19 | BKR §2, BKR §5 |
| GAP_REGISTER | GAP-007 | INF §6, BKR §4 |
| GAP_REGISTER | GAP-011 | BKR §1, BKR §4 |
| GAP_REGISTER | GAP-012 | INF §1, INF §5, INF §10 |
| GAP_REGISTER | GAP-013 | INF §1, INF §5, INF §6, INF §9, INF §10, BKR §7 |
| GAP_REGISTER | GAP-031 | INF §4, INF §6, BKR §1, BKR §4 |
| GAP_REGISTER | GAP-042 | INF §4 |
| GAP_REGISTER | GAP-049 | INF §5, INF §9 |
| GAP_REGISTER | GAP-050 | INF §7, BKR §6 |
| GAP_REGISTER | GAP-051 | INF §7, BKR §6 |
| GAP_REGISTER | GAP-033 — its alerting | INF §6 |
| GAP_REGISTER | GAP-018 — accepted risk, unchanged | INF §1, BKR §1 |
| GAP_REGISTER | GAP-041 — accepted risk, unchanged | INF §6, INF §10 |
| GAP_REGISTER | GAP-048 — accepted risk, unchanged | BKR §6 |
| PRODUCTION_DATA_SAFETY | PRODUCTION_DATA_SAFETY — the procedures owed | INF §1, INF §3, BKR §1 |
| COST_POLICY | COST_POLICY — no paid service (§4C.2) | INF §1, INF §8, INF §10, BKR §8 |
| CONCURRENCY_IDEMPOTENCY | HO-21 | INF §4 |
| CONCURRENCY_IDEMPOTENCY | HO-22 | INF §2, INF §4 |
| CONCURRENCY_IDEMPOTENCY | HO-23 | INF §2, INF §4, INF §5 |
| CONCURRENCY_IDEMPOTENCY | HO-24 | INF §2, INF §4, INF §7 |
| CONCURRENCY_IDEMPOTENCY | HO-25 | INF §4, INF §6 |
| CONCURRENCY_IDEMPOTENCY | HO-26 | INF §4, INF §9 |
| CONCURRENCY_IDEMPOTENCY | HO-27 | INF §7 |
| CONCURRENCY_IDEMPOTENCY | HO-28 | INF §4, BKR §1, BKR §2, BKR §4 |
| CONCURRENCY_IDEMPOTENCY | HO-29 | INF §7 |
| CONCURRENCY_IDEMPOTENCY | HO-30 | INF §4 |
| CONCURRENCY_IDEMPOTENCY | HO-32 — the go-live setting | INF §4 |
| CONCURRENCY_IDEMPOTENCY | CI-07 | INF §7 |
| CONCURRENCY_IDEMPOTENCY | RY-09 | INF §4 |
| CONCURRENCY_IDEMPOTENCY | JB-01 | INF §4 |
| CONCURRENCY_IDEMPOTENCY | D-CC-10 | INF §4 |
| CONCURRENCY_IDEMPOTENCY | RV-08 | INF §7 |
| CONCURRENCY_IDEMPOTENCY | RV-10 | INF §4 |
| CONCURRENCY_IDEMPOTENCY | H6-12 | INF §4 |
| CONCURRENCY_IDEMPOTENCY | §8 — guards after a restore | INF §5, INF §6, BKR §1, BKR §2, BKR §4 |
| CONCURRENCY_IDEMPOTENCY | §15 — a missed daily run | INF §4 |
| CONCURRENCY_IDEMPOTENCY | §4.4 — every deadlock | INF §6 |
| PERFORMANCE | AS-01 | INF §2, INF §8, INF §9 |
| PERFORMANCE | PF-28 | INF §8, BKR §3 |
| PERFORMANCE | PF-39 | INF §4 |
| PERFORMANCE | GT-06 | INF §8, BKR §2, BKR §3 |
| PERFORMANCE | GT-07 | INF §6, BKR §3 |
| PERFORMANCE | §12 item 6 | INF §4, INF §6 |
| PERFORMANCE §13 | SZ-01 | INF §2, INF §4, INF §9 |
| PERFORMANCE §13 | SZ-02 | INF §2, INF §4 |
| PERFORMANCE §13 | SZ-03 | INF §2, INF §4 |
| PERFORMANCE §13 | SZ-04 | INF §4 |
| PERFORMANCE §13 | SZ-05 | INF §4 |
| PERFORMANCE §13 | SZ-06 | INF §6 |
| PERFORMANCE §13 | SZ-07 | INF §4 |
| PERFORMANCE §13 | SZ-08 | INF §8, BKR §3 |
| PERFORMANCE §13 | SZ-09 | INF §2, INF §4 |
| DATABASE | DATABASE §4 | INF §7 |
| DATABASE | DATABASE §19 | INF §7 |
| DATABASE | DATABASE §22 | INF §2, BKR §1, BKR §2 |
| DATABASE | DATABASE §27 | INF §2 |
| DATABASE | DATABASE §28 | INF §2, INF §4, BKR §1 |
| DATABASE | DATABASE §31 | INF §4 |
| DATABASE | C-53 | INF §4 |
| DATABASE | C-61 | INF §4, INF §6 |
| ARCHITECTURE | ARCHITECTURE §1 | INF §2, INF §10 |
| ARCHITECTURE | ARCHITECTURE §12 | INF §2 |
| ARCHITECTURE | ARCHITECTURE §16 | INF §4 |
| ARCHITECTURE | ARCHITECTURE §17 | INF §1 |
| ARCHITECTURE | ARCHITECTURE §20 | INF §4 |
| ADMIN_FLOW | UXH-09 | INF §7 |
| DESIGN_SYSTEM | GR-13 | INF §4 |
| WORKFLOWS | WF-ACC-01 | INF §7 |
| INFORMATION_ARCHITECTURE | INFORMATION_ARCHITECTURE §9 | INF §6 |
| AGENT_OPERATING_MODEL | AGENT_OPERATING_MODEL — the operator's role, agents never in production | INF §1 |
| AICWDF_ADOPTION | AICWDF_ADOPTION — the deployment path pending P9 and P10 | INF §5 |
| PERMISSIONS_MATRIX | PERMISSIONS_MATRIX §13 | INF §7 |
| REFERENCE_COVERAGE | OWN-06 | BKR §1 |
| GAP_REGISTER | GAP-046 | INF §4, INF §9 |
| DATABASE §30 | CAP-01 | INF §4 |
| DATABASE §30 | CAP-13 | INF §4 |
| DATABASE §30 | CAP-16 | BKR §1, BKR §2 |
| PROJECT_CHARTER | PROJECT_CHARTER — P9, infrastructure, observability, backup and recovery | INF §1, BKR §1 |
| ACCEPTANCE_CRITERIA | ACCEPTANCE_CRITERIA — recovery measurements and workloads operationalized with P8 | BKR §5, INF §8 |
| PERFORMANCE_TARGETS | PFM-18 | INF §6 |
| PERFORMANCE_TARGETS | PERFORMANCE_TARGETS §5 — the measured figures P9 sizes from | INF §4, INF §8 |
| SECURITY_TEST_MATRIX | SM-20 — the staging OAuth client | INF §4 |
| SECURITY_TEST_MATRIX | SM-24 — the installation event's rule | INF §5 |
| RELEASE_PLAN | RELEASE_PLAN §2 and P10Q-02 — the release model on P9's environments | INF §3, INF §5 |
| RELEASE_PLAN | RELEASE_PLAN §7 — the tabletop exercise is P9's | BKR §6 |
| RELEASE_PLAN | RELEASE_PLAN §9 — the operator's inputs OPI-01–OPI-29 as go-live items | INF §9, BKR §7 |
| RELEASE_PLAN | PRQ-10 — the drill procedures beside P9's backup tooling | BKR §5 |
| RELEASE_PLAN | PRQ-15 — the recovery runbooks of AU-25 with P9 | INF §7, BKR §6 |
| Owner in session | The facts of the VPS and its operation, given in session on 2026-10-19 | INF §9 |
| Owner in session | Condition 1 — the two production applications isolated | INF §1, INF §2, INF §5 |
| Owner in session | Condition 2 — capacity from both applications' combined use | INF §8, BKR §3 |
| Owner in session | Condition 3 — a backup, restore or rollback of one application never damages the other | INF §5, BKR §1, BKR §4 |
| Owner in session | Condition 4 — no provider feature assumed active | INF §1, INF §9 |
| Owner in session | Condition 5 — inputs not available as pre-staging or pre-go-live obligations, never PASS | INF §9, BKR §7 |
| Owner in session | Condition 6 — no server access, installation or credential request now | INF §9 |
| Owner in session | OPI-05 — a simple zero-cost push channel recommended, testable before go-live | INF §9 |
| Owner in session | OPI-23 — a backup time outside business hours recommended, approved before go-live | BKR §7 |
| Owner in session | OPI-22 — an off-server backup to be considered (OQ-26) | INF §1, BKR §1 |
| Owner in session | OPI-01 and OPI-24 — no alternate yet (P9Q-14) | INF §10, BKR §8 |
| INFRASTRUCTURE §9 | OPI-26 | INF §3, INF §9 |
| INFRASTRUCTURE §9 | OPI-27 | INF §8, INF §9, BKR §3 |
| INFRASTRUCTURE §9 | OPI-28 | INF §9 |
| INFRASTRUCTURE §9 | OPI-29 | INF §1, INF §9 |
| BACKUP_RECOVERY §6 | The tabletop exercise TT-01–TT-12, held in PC-4 (GAP-051) | BKR §6 |
| INFRASTRUCTURE §7 | The runbooks owed in PC-4 — AU-14, AU-21 and AU-25 with GAP-050 | INF §7 |
| Owner in session | P9Q-15 — a disagreement between recovered pre-window Owners | INF §10, BKR §6 |
| Owner in session | P9Q-16 — one APP_KEY rotation after the first Owner, before go-live (named OQ-27 in session) | INF §10, BKR §6 |
| Owner in session | OQ-28 — a decision of the Owner before any restore on a trusted state | INF §7, BKR §6 |
| SECURITY | SX-07 | BKR §1 |
| SECURITY §10 | H9-11 — the runbook of a later driver change (AU-02) | INF §4 |
| INFRASTRUCTURE §9 | OPI-30 | INF §9 |
| INFRASTRUCTURE §9 | OPI-31 | INF §9 |

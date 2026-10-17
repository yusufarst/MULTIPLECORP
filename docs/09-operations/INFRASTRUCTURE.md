# Infrastructure

Status: REVIEW — Draft of P9, not normative before its approval | Updated: 2026-10-18 | Owner: Planning

**Draft of P9, not normative before its approval.** Run PC-2 drafted this document on 2026-10-18 under [DIR-078](../00-governance/DECISION_LOG.md#dir-077-dir-078-obs-030-and-tech-037--p7-ux-re-baseline-cp-a-decisions-run-pc-2-with-p9s-drafting-baseline-and-drafts) SP-67, P9 being authorized by the Owner's [DIR-077](../00-governance/DECISION_LOG.md#dir-077-dir-078-obs-030-and-tech-037--p7-ux-re-baseline-cp-a-decisions-run-pc-2-with-p9s-drafting-baseline-and-drafts) CA-14: drafted in PC-2, completed in PC-4 with the operator's inputs and the Owner's answers, and approved separately at OG-3. Nothing here is installed, configured or connected; no agent connected to the VPS or any production system to write it.

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
| INF-06 | **Backups stay on the production VPS** — local backup, never offsite —; the exposure to total VPS loss stays an accepted risk, unchanged. | DIR-009; GAP-018 |

## 2. Topology

AICWDF §20's default topology on the existing VPS of DEP-01 (4 vCPU, 16 GB RAM, 200 GB NVMe, shared by production and its backups — AS-01):

```text
User's browser
  ↓ HTTPS
<APP_DOMAIN> — DNS at the domain's current provider (P9Q-05)
  ↓
The existing VPS
  ├─ reverse proxy (Nginx): TLS, HTTP→HTTPS, static headers, the login rate limit (H9-02)
  ├─ web: Laravel with Inertia and React, PHP workers (SZ-01)
  ├─ queue workers (SZ-02) and the scheduler (HO-23)
  ├─ PostgreSQL — one instance, the roles of DATABASE §28 (H9-01)
  ├─ Valkey — queue, cache and limiter store, never a system of record (HO-24; SZ-09)
  └─ the private file disk (ARCHITECTURE §12; DATABASE §22) and the local backup area (BACKUP_RECOVERY)
```

- **Containers.** The application, its workers and scheduler, PostgreSQL, Valkey and Nginx run as containers under Docker Compose, which ARCHITECTURE §1 accepts — or under the tooling the VPS already runs, the operator's input (P9Q-02; OPI-03) —, each service bound to the host's private interface except the proxy's HTTPS port (H9-09).
- **One instance of each store.** A single PostgreSQL instance is sufficient (DATABASE §27); no connection pooler — sessions end with the request (SZ-03; HO-22) —, so a transaction-pooling proxy is never added without moving the evidence registration keys first (FU-10).

## 3. Environments

| Environment | Where | Who works there | Data |
| --- | --- | --- | --- |
| Local | each developer's or agent's own machine | agents and developers | synthetic only — the fictional group (ARJ, BTN, CKP) |
| CI | the public repository's CI, at no cost | agents, through the pipeline | synthetic only; a production-like stack — web, workers, PostgreSQL, Valkey — started for the integration and E2E suites of P8 |
| Staging | **P9Q-01** — recommended: an ephemeral stack the operator starts on the VPS before a release, isolated from production, removed after | the human operator only, since it runs on the production host (INF-01) | synthetic only, never a copy of production (PRODUCTION_DATA_SAFETY) |
| Production | the existing VPS (DEP-01) | the human operator only (INF-02) | the business's data |

The branch and environment model is AICWDF §32's as the repository adopts it: work through pull requests and CI into `main`; a release rehearsed in staging; production deployed from `main` by the operator (§5). P10 owns the release, its rehearsal and the cutover.

## 4. Processes, configuration and secrets

### 4.1 Processes

- **Web workers.** Up to 16 PHP workers as the starting ceiling, bound by memory — 60–100 MB each until measured on the VPS (SZ-01) —, each holding at most one database connection and only while a request runs; the measured value replaces the assumption in PC-4 (OPI-07).
- **Queue workers and the scheduler.** Two workers on the single main queue and the scheduler (SZ-02); each job's timeout shorter than the queue's retry-after; at most one export at a time across accounts — JB-03's overlap lock, its expiry set here —, and JB-01's unique-job lock lifetime above its 120 s timeout plus the queue wait it tolerates (HO-22). The scheduler's cron entry and the process manager restart every worker on deployment (HO-23); a daily task that misses its minute is not caught up, so a day without a successful run alerts (HO-25; CONCURRENCY_IDEMPOTENCY §15).
- **Connections.** About 16 web + 3 queue and scheduler + 6 administration and reserve = 25 PostgreSQL connections, inside the default limit (SZ-03; HO-22); database sessions end with the request.
- **Memory.** About a quarter of RAM for PostgreSQL's shared buffers as the tuning start; the rest for the file cache, the PHP workers, Valkey, the renderer and the hash slots of H6-12 — three slots times the Argon2id memory cost (SZ-04).
- **Valkey** is sized never to evict: its locks carry expiries and would otherwise be eviction candidates, and the second-factor limit's keys carry no expiry (SECURITY AU-05); its policy is `noeviction` or one that evicts only keys with an expiry, with limiter keys unable to evict queue payloads and locks or to fill the store; availability monitored and alerted, authentication required, bound to the private interface (HO-24; SZ-09; H9-09). A failover store is not provisioned in V1: it could shorten an outage at no cost (D-CC-10), but the second-factor keys are served by none, so the gain is small — revisit after measurement. Limiter and lock keys live where no deployment step flushes them (HO-23; PERFORMANCE CP-07).

### 4.2 Configuration

- **Timeouts and retry budgets** — TX-01–TX-10, the runtime role's default statement timeout that page reads rely on included, and RY-02, RY-03 and RY-08 — are configuration this document owns and may change from measurement (HO-21; RY-09; SZ-05).
- **Hashing.** Confirm Argon2id in the production runtime and measure its parameters on the VPS within AU-02's bounds — about 0.25–0.5 s per hash within the memory budget —, otherwise AU-02's bcrypt fallback; generate the login dummy hash from the configured driver and parameters (AU-04); then tune the slots and waits of H6-12 (H9-11; HO-26; PF-39). A change of hashing parameters ends each account's other sessions at its next login, so it is a planned event (RV-10).
- **Versions.** Record the chosen PostgreSQL and framework versions and re-verify the evidence tables against them — PostgreSQL 18's virtual generated columns included: a generated column a CHECK or a partial index reads is declared STORED (HO-30; DEP-07).
- **Extensions.** `pg_trgm` and `btree_gist`, and for verification the contrib modules `pg_stat_statements` and `auto_explain`, at no cost (DATABASE §31; ARCHITECTURE §20; PERFORMANCE §12 item 6).
- **The go-live setting.** The number allocator's go-live is recorded once, as a setting P9 keeps with the deployment and nobody changes afterwards (HO-32; NM-03); P10 orders the cutover around it.
- **Limiter and cap values** — WS-12, AU-05 and H6-12 — stay assumptions until measured on the VPS (SZ-07).
- **Environment variables** are named in P11's `.env.example` with placeholder values only; their real values exist only on the VPS (INF-05).

### 4.3 Secrets and keys

- **Storage.** `APP_KEY`, the credentials of DATABASE §28's roles, Valkey's password, the Google client secret and every other secret of SX-01 live in environment configuration outside the repository and the web root, readable only by the service account (SX-01; H9-09); backup files carry restricted permissions (SX-07).
- **Rotation.** SX-02's schedule and triggers; the key-compromise incident runbook of AU-21 orders the rotation — a new `APP_KEY` and new HMAC keys first, then every other secret of SX-01 the leak may have included unless the inventory shows it could not (H9-14).
- **Key management** (H9-14): `APP_KEY`'s storage and permissions, the dated key register, the rotation and re-encryption runbook and the key-compromise incident runbook — the maintenance mode on every web process, its flag where every process reads it, before the incident command and lifted only after its closing sweep. The TOTP secrets and recovery codes are encrypted with `APP_KEY`, so a database copy or a backup holds ciphertext only (AU-21; DATABASE §28). The runbooks are written in PC-4 and rehearsed in the tabletop exercise (BACKUP_RECOVERY §6).
- **The Google OAuth client** of each environment, created with the Owner under the Owner's own Google Cloud access: the consent screen, the scopes `openid email`, each environment's exact redirect URI, the client secret stored and rotated as SX-01 and SX-02 say, and outbound HTTPS from the VPS to Google and to the breached-password range service with the timeouts of AU-03 and AU-22 (H9-13; DEP-10; DEP-11; GAP-042; GAP-046). Google sign-in — and its button, drawn to Google's guidelines (GR-13) — stays off in an environment until its client exists; the free-tier record of COST_POLICY §4C.4 is confirmed at set-up.

### 4.4 Database roles

The roles of DATABASE §28, created by P9 (H9-01; ARCHITECTURE §16; GAP-031; C-53):

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

The first-Owner installation step writes the audit event of the OWNER assignment with the operator's identity — the host account and the stated name, never a user row —, RG-11's operator rule extended to it under P9's approval (AU-25; GAP-049).

## 6. Observability

Free and self-hosted components on the VPS, unless P9Q-04 and P9Q-07 decide otherwise; no new observability platform without need (GAP-013).

- **Logs.** The application's structured logs with request, job and business-action correlation, redacted of secrets and S3/S4 values; log files with restricted permissions; retention within the disk budget (LG-06; H9-08; BACKUP_RECOVERY §3).
- **The business heartbeat** — the signals an HTTP health check hides (GAP-013): the oldest queued job's age, failed jobs, the scheduler's last successful run, `ReconcileGuards`' and the purges' daily success (HO-25), stale renditions and owed work (GAP-007), database connections, free disk (BK-11) and backup freshness (BK-04).
- **Monitored and alerted** (HO-25): every deadlock (`40P01` — each one a defect, CONCURRENCY_IDEMPOTENCY §4.4), retries and restarts, lock waits by class, FAILED rates by cause, a FAILED access change, guard-missing alerts and reconciliation differences (GAP-031; C-61), failed jobs, stale renditions and owed work, a day without a successful `ReconcileGuards` or purge run, slow queries — statements slower than 500 ms (SZ-06).
- **Statement statistics.** `pg_stat_statements` and `auto_explain` in staging and production; the heaviest statements and the table and index sizes reported monthly; crossing half of a threshold opens a measured review, never an automatic change (PERFORMANCE §12 item 6; GT-07).
- **Uptime.** An external check that sees the VPS from outside — the VPS cannot report its own death — at no cost (P9Q-07), with the business heartbeat inside.
- **Error tracking.** The framework's logs with a self-hosted viewer in V1 (P9Q-04); no error data leaves the VPS without the Owner's decision.
- **Security monitoring.** LG-07's detection signals — failures for one identifier across addresses, throttling spikes, bursts of ACCESS_DENIED, credential and factor events — delivered to the named operator and the Owner (H9-10), with the alerts AU-03, AU-05, AU-18–AU-21 and AU-23 add (H9-15) and the confirmer an OWNER_RECOVERY_LINK_ISSUED names (LG-07); until P9 confirms a channel the operator does not solely control, the Owner's floor is the in-app notice at the next login, *Beranda*'s security row (INFORMATION_ARCHITECTURE §9; AU-14; TM-57; GAP-033's alerting).
- **The alert channel** (H9-06; DEP-08; P9Q-03): a zero-cost channel confirmed and tested by a staging failure drill before go-live (GAP-013); the optional self-service e-mail reset stays off unless that channel is e-mail and P9 confirms it (AU-12; D-SEC-05). GAP-041's accepted risk is reviewed when the operator changes or a channel outside the operator's control is confirmed — unchanged until then.

## 7. Security operations

What SECURITY §10 hands P9 — the H9 items, which PERMISSIONS_MATRIX §13 points to — is answered here and in §§4 and 6.

- **TLS and the proxy** (H9-02; WS-06): free certificates with automatic renewal, plain HTTP redirected, HSTS introduced in stages — `max-age=300`, then one week, then one year with `includeSubDomains` once every subdomain serves HTTPS —, Nginx's static security headers, version banners off; forwarded-header, trusted-proxy and trusted-host configuration (WS-11); a proxy rate limit on the login endpoint that bounds total concurrency (TM-58; HO-24).
- **Network.** A host firewall allowing only HTTPS and the operator's own administrative access; PostgreSQL and Valkey bound to the private interface with authentication (H9-09).
- **Operator-only commands** (H9-03): `GuardMaintenance` under the administration role (TX-08), and, under the runtime role, the Owner-recovery command (AU-14), the re-encryption and key-compromise incident commands of AU-21 and the OWNER-restoration command (AU-25) — each run only by the operator, each audited, never exposed to a web route.
- **Runbooks owed in PC-4:** the Owner-account recovery (AU-14; H9-07) — identity verification, K3's order, the command, the hand-over and the alert; key management and the key-compromise incident (AU-21; H9-14); the post-incident recovery of AU-25 with the carried cases of GAP-050; each rehearsed in the tabletop exercise (BACKUP_RECOVERY §6; GAP-051). WF-ACC-01's Owner-account recovery is this human procedure.
- **Retention and cleanup jobs** (H9-04; HO-27; HO-29), over the framework's own tables DATABASE §4 leaves to P5 and P9: session-store garbage collection — a session row's raw address and user agent go with it —; the credential-token purge; queue and failed-job retention with identifier-only payloads, failed-job retention shorter than `command_log`'s; `command_log`'s purge JB-08 under DATABASE §19's trigger-guarded DELETE (CI-07); the audit events that record a start of numbering kept for as long as the company numbers here (NM-03); export-file cleanup, expired export requests included, and a withheld file removed at once (RV-08; FL-09); an upload never linked to any record removed after the retention window `<UPLOAD_ORPHAN_DAYS>` (DATABASE §19); business audit never deleted by an application role (LG-06).
- **The undisclosed security events** — a duplicate the uploader may not see, an import collision outside the preparer's scope — written by the post-response step SECURITY LG-02 prefers (UXH-09; HO-38).
- **Uploads.** The image library's policy — only the allowlisted input coders, no delegates, remote URLs or indirect reads, memory, time and pixel limits (H9-12; FL-03); the optional malware scanner only with confirmed memory headroom (H9-05; FL-11; P9Q-06).

## 8. Capacity and cost

- **Capacity.** The VPS's actual free space, utilization, bandwidth period and workload are measured, never taken from the nominal figures (DEP-01; AS-01; OPI-02); the shared-disk budget, its thresholds and its peaks are BACKUP_RECOVERY §3's, with PERFORMANCE's growth inputs (SZ-08; GT-06) and PF-28's export row bound — 200,000 rows as the starting value — as a disk guard.
- **Cost.** No new recurring cost: the components here are the VPS's own and free or self-hosted software; the zero-cost services — the Google OAuth client, the authenticator apps, the breached-password range service — are COST_POLICY's inventory with their free-tier records. **Paid-service exception registry:** empty. Every option of §10 that needs a paid service is recorded there under COST_POLICY §4C.2 and not adopted.

## 9. The operator's inputs

What the operator supplies in PC-4, outside the repository where it is sensitive — never assumed here. "Recorded" means written into this document as a fact without a secret; "kept" means held only by the operator.

| OPI | Input | Where it goes |
| --- | --- | --- |
| OPI-01 | The named human operator and an alternate, `<OPERATOR>` (GAP-013) | recorded by role, not by account |
| OPI-02 | The VPS's measured free disk, current usage, operating system and the bandwidth allowance's period (DEP-01; AS-01) | recorded as figures |
| OPI-03 | What the VPS already runs — Docker, Docker Compose, Coolify, Nginx, other services — and its firewall tooling (P9Q-02) | recorded |
| OPI-04 | The domain's DNS provider and whether a proxy fronts it (P9Q-05) | recorded |
| OPI-05 | A zero-cost alert or mail channel the operator can confirm (H9-06; DEP-08; P9Q-03) | recorded; its credentials kept |
| OPI-06 | The Owner's Google Cloud access for the OAuth clients (DEP-10; H9-13) | used with the Owner; the secret kept |
| OPI-07 | Measured PHP worker memory, Argon2id timing and PostgreSQL behaviour on the VPS (SZ-01; H9-11; HO-26) | recorded as figures |
| OPI-08 | Outbound HTTPS from the VPS to Google and to the breached-password range service (DEP-11; H9-13) | recorded as tested |
| OPI-09 | The installation step's operator identity — host account and stated name (GAP-049) | recorded in the audit event, not here |

## 10. Owner questions

Each Owner-level choice of this document, with its options and one recommendation, answered at the latest at OG-2 (SP-67). An option needing a paid service is recorded under COST_POLICY §4C.2 and never adopted.

### P9Q-01 — Where staging runs and who operates it

- **Options.** (a) An **ephemeral staging stack on the existing VPS**, started by the operator before a release with synthetic data, isolated — its own containers, database, Valkey and storage, resource-capped —, the release rehearsed there, then removed; the operator alone runs it (INF-01). Cost: none. Disk: a transient term of BACKUP_RECOVERY §3 during the release window. (b) A **standing staging stack on the VPS**: always available; a permanent share of RAM and disk beside production; cost none. (c) **A separate paid server for staging**: recorded under COST_POLICY §4C.2, not adopted. In every option CI runs the production-like stack for agents (§3).
- **Recommendation.** (a): GAP-012's rehearsal and GAP-013's failure drill need a production-like place, and only the release window needs it; it costs nothing and takes no standing share of the shared VPS.

### P9Q-02 — The deployment tooling on the VPS

- **Options.** (a) **Docker Compose**, run by the operator — ARCHITECTURE §1's accepted baseline; (b) **Coolify**, self-hosted — AICWDF §20's default routing — only if the VPS already runs it, since its control plane takes RAM and disk; (c) services installed directly on the host.
- **Recommendation.** (a), unless OPI-03 shows the VPS already runs (b): the fewest moving parts on a shared host, and nothing new for the operator to learn if the host already uses it.

### P9Q-03 — The alert channel

- **Options.** (a) An **existing zero-cost mail channel**, only if the operator confirms one (DEP-08: never assumed); (b) a **zero-cost push channel** to the operator's and the Owner's own phones; (c) the **floor only**: the in-app notice at the Owner's next login and the operator's daily look at the heartbeat; (d) a paid SMS or mail service — COST_POLICY §4C.2, not adopted.
- **Recommendation.** (b), with (c) always kept as the floor, the Owner's part of it a channel the Owner controls — which also lets GAP-041's accepted risk be reviewed.

### P9Q-04 — Error tracking

- **Options.** (a) **The framework's logs with a self-hosted log viewer**; (b) a **self-hosted error tracker** on the VPS — more memory; (c) a free-tier hosted tracker — error data, possibly with S3 or S4 values, leaves the VPS, and free-tier limits apply (COST_POLICY §4C.4).
- **Recommendation.** (a) for V1; (b) if errors become hard to triage after go-live.

### P9Q-05 — DNS and an edge proxy

- **Options.** (a) **DNS at the domain's current provider**, TLS on the VPS (§7); (b) a **free edge proxy** in front of the VPS — shielding, at the price of TLS terminating at the edge, the trusted-proxy configuration of WS-11 and a free-tier dependency.
- **Recommendation.** (a), unless OPI-04 shows the domain already uses (b).

### P9Q-06 — The optional malware scanner

- **Options.** (a) **None in V1** — uploads validated by FL-01–FL-06; (b) a **self-hosted scanner** when the measured memory headroom allows it (H9-05; FL-11).
- **Recommendation.** decide on OPI-07: (b) if the headroom after production sizing holds the scanner, otherwise (a).

### P9Q-07 — Uptime monitoring from outside

- **Options.** (a) a **free external uptime check** — a free-tier dependency recorded under COST_POLICY §4C.4; (b) **no external check** — only the business heartbeat, which cannot report the VPS's own outage.
- **Recommendation.** (a): only an outside check sees the VPS down.

## 11. Coverage

Every requirement an approved document hands to P9, found by a scripted search of P0–P7's normative documents for "P9" and joined with SP-67's list, each mapped by script to the sections of this document (INF) and of [BACKUP_RECOVERY](BACKUP_RECOVERY.md) (BKR) that answer it; PC-4 completes the answers and gate OG-3 checks them. **143 requirements, 143 answered, 0 missing.**

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

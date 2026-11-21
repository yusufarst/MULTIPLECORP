# Monitoring, alerts, the log viewer and the uptime check

Status: REVIEW | Updated: 2026-11-21 | Owner: Planning

Written by [TASK-075](../../11-tasks/TASK-075.md): the monitoring [INFRASTRUCTURE §6](../INFRASTRUCTURE.md) designs, free and self-hosted on the VPS. Every value is a placeholder the operator sets (TASK-080). Making the channel live, testing its delivery and adopting the uptime check are the operator's work in TASK-080. Exercising every signal in the failure drill is TASK-081's.

## 1. How an alert travels

1. **Something logs an alert.** That is every record at CRITICAL or above, plus HO-25's signals named in `App\Support\Monitoring\AlertSignals`.
2. **The `alerts` log channel picks it up.** Every log stack includes this channel.
3. **The channel turns it into a neutral signal name.** The name is the record's own sentence, with any address or long number masked. It carries no value from the record's context: no id, amount, address, S3 or S4 value, or secret (LG-03).
4. **The name is sent through OPI-05's channel.** That is e-mail through the Owner's existing Cloudflare Email Service, to verified addresses only, at no cost (DIR-129).
5. **Repeats are suppressed.** One message is sent per signal per `ALERT_THROTTLE_MINUTES` while the signal keeps repeating.

The framework's logs themselves stay on the VPS (LG-05). A failed delivery is logged as a warning, without its token, and raises no further alert.

| Setting | Placeholder | Meaning |
| --- | --- | --- |
| `ALERT_CHANNEL_URL` | `<CHANNEL_SEND_ENDPOINT>` | The sending endpoint of the channel's account, as its own documentation names it. Unset, nothing is sent. |
| `ALERT_CHANNEL_TOKEN` | `<CHANNEL_TOKEN>` | A secret of SX-01, in the environment only, sent as the bearer header. |
| `ALERT_FROM` | `<SENDER_ADDRESS>` | The verified sender. |
| `ALERT_RECIPIENTS` | `<OPERATOR>,<OWNER>` | The named operator and the Owner. The alternate is added once TASK-083 names one (DIR-083 OE-03). Verified addresses only. |
| `ALERT_THROTTLE_MINUTES` | 60 | The window for repeats. |
| `ALERT_QUEUE_MAX`, `ALERT_OLDEST_JOB_MINUTES`, `ALERT_RENDITION_OWED_MINUTES` | 1000, 15, 15 | The heartbeat's bounds. |
| `BACKUP_DISK_WARN_PCT`, `BACKUP_DISK_CRIT_PCT` | `<DISK_WARN_PCT>`, `<DISK_CRIT_PCT>` | The disk's levels, shared with the backup tooling (BACKUP_RECOVERY §3). |
| `SLOW_STATEMENT_MS` | 500 | SZ-06's bound. |

**Floors.** Until a channel outside the operator's sole control is confirmed, the Owner's floor stays the in-app notice at the next login, *Beranda*'s security row (TASK-058; GAP-041). The operator's daily look at the heartbeat stays the floor for every signal (DIR-079 CB-06): `php artisan ops:smoke`.

## 2. The signals, matched with the failure drill (RELEASE_PLAN §7; INFRASTRUCTURE §6)

Each row is a signal TASK-081's failure drill can raise on staging.

**The heartbeat.** `ops:heartbeat` runs every five minutes, from two places: the scheduler, and a watchdog the scheduler does not run. The watchdog is the operator's host cron entry: `*/5 * * * *`, starting a one-off container of MULTIPLECORP's image that runs `php artisan ops:heartbeat`, as the backup tooling's entries do ([BACKUP_RESTORE §1](BACKUP_RESTORE.md#1-the-tooling-and-its-configuration)). With the watchdog, a stopped scheduler still raises its stale `scheduler <task>` signals. The two runs share one throttle, so a failing signal is sent once per window. A probe that cannot read, such as the database refusing connections or the queue or store down, raises its signal as `unavailable`, and the other probes are still read. The name it sends is `Heartbeat: <signal> failing (INFRASTRUCTURE §6).`

| Signal | Raised when | How the drill raises it |
| --- | --- | --- |
| `queue`, `oldest job` | The backlog passes its bound, or the oldest waiting job is past 15 minutes | Stop the worker and queue work. |
| `failed jobs` | A job failed in the last 24 hours | Make a job fail. |
| `renditions`, `renditions exhausted` | A rendition is owed past 15 minutes, or FAILED after its five attempts (JB-01) | Stop the worker after an issue, or break the renderer. |
| `scheduler <task>` | A scheduled task has no success within its window. `guards:reconcile` and the purges are allowed a day. | Stop the scheduler. |
| `database` | Connections pass 80% of the maximum | Exhaust the connections. |
| `disk` | Usage at `<DISK_WARN_PCT>` or past it; the critical level is named in the detail (BK-11) | Lower the thresholds for one run, never by filling the disk. |

**The backup tooling.** These come from `backup:run`, `backup:copy` and `backup:status`. The name sent is `Backup alert (BK-10): …`.

| Signal | Raised when |
| --- | --- |
| The backup run failed | A run failed (BK-10) |
| The run was skipped | No space at the critical level |
| Cleanup failed | Cleanup could not remove an expired generation |
| No verified backup within the stale window | No verified backup within 26 hours (BK-04) |
| A generation is corrupt | A generation fails its checks |
| The off-server copy failed | A copy failed (BKR-09; 13c) |
| No verified off-server copy within the stale window | No verified copy within 26 hours |
| The restore was refused or failed | A restore was refused or failed |

**HO-25 and SZ-06.** These are raised by the application wherever they occur.

| Signal | Raised when |
| --- | --- |
| `HO-25 deadlock (40P01)` | Any deadlock; each one is a defect |
| `HO-25 guard reconciliation difference` | `ReconcileGuards` reports a difference |
| `HO-25 guard family not verified` | A guard family could not be verified |
| A guard row is missing; the command failed (GU-02) | Raised at CRITICAL |
| `HO-25 failed access change` | A FAILED IAM or ADM command on roles, grants, capabilities or accounts |
| A statement slower than 500 ms | Logged with its text and never its values (SZ-06). Not alerted. |

**Security (LG-07; H9-10; H9-15).** These are the records the authentication and recovery code logs as alerts. Each is sent by its sentence, which names its rule, for example `A second-factor cooldown started (AU-05; LG-07).`

| Signal | Rules |
| --- | --- |
| More than 20 failed sign-ins for one identifier in an hour | AU-05 |
| Second-factor events | AU-05, AU-18–AU-20 |
| Recovery-link and Owner-recovery events | AU-14 |
| The key-compromise command | AU-21 |
| Google-identity events | AU-23 |

**Valkey.** Its unavailability shows in the heartbeat as failing scheduler stamps and limiter errors in the logs (HO-24). The measured hashing parameters (HO-26) and the recorded versions (HO-30) are TASK-079's and TASK-072's.

## 3. Statement statistics (PERFORMANCE §12 item 6; GT-07; PFM-18)

**Where the data comes from.** `pg_stat_statements` and `auto_explain` are preloaded by the deployment definition. The operator creates `pg_stat_statements` in staging's and production's databases as the superuser (INFRASTRUCTURE §4.2).

**The report.** `php artisan ops:statement-report` runs on the first of each month at 04:00 WIB. It lists the heaviest statements by total time, each normalized by PostgreSQL so it shows no value, and the largest tables with their indexes.

**Reviews.** An entry past half of its threshold is marked REVIEW and logged. Review means a measured review by Planning and the operator, never an automatic change.

## 4. The log viewer (P9Q-04; DIR-079 CB-07)

The framework writes structured JSON lines to `storage/logs` and the containers' output, and the logs never leave the VPS (LG-05). They are viewed through Coolify's own log view of MULTIPLECORP's project. Coolify is already the deployment's self-hosted panel (COOLIFY_DEPLOYMENT), reached only through the operator's administrative access. On the VPS, `docker logs` serves the same.

No application route serves a log. No component sends error data or logs off the VPS: the alert channel carries only a signal name.

## 5. The external uptime check (P9Q-07; DIR-079 CB-10)

This is what the operator sets up and adopts in TASK-080, after its COST_POLICY §4C.4 record: limits, quota behaviour, lock-in, exit and data portability (GLC-11; INF-04).

- **What it calls.** A free external check calls `https://<domain>/kesehatan` from outside the VPS every five minutes or more often. `/kesehatan` is TASK-072's minimal health check: it answers `OK` with 200, or `UNAVAILABLE` with 503, and shows no internals.
- **What its failure raises.** A failure is two consecutive answers that are not 200, or no answer. It alerts through the check's own e-mail to the operator's and the Owner's verified addresses. It is the one signal that still reaches them when the VPS itself is down.
- **What it holds.** The domain and the check's schedule only. No credential of MULTIPLECORP's is given to it.

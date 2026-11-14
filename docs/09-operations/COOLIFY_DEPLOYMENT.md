# Coolify — MULTIPLECORP's deployment on the shared VPS

Status: APPROVED — the decision; §2's controls await the Owner's acceptance | Updated: 2026-11-14 | Owner: Planning

Decision: the Owner's approval of 2026-11-14 (DIR-117, APPR-033; [transcript](../00-governance/sources/EXECUTION_OWNER_APPROVAL_COOLIFY-TASK-072_2026-11-14.txt)), on the change proposal raised under the directive of the same day ([transcript](../00-governance/sources/EXECUTION_OWNER_DIRECTIVE_SHIP-MULTIPLECORP_2026-11-14.txt), section 4). It amends [INFRASTRUCTURE P9Q-02](INFRASTRUCTURE.md#p9q-02--the-deployment-tooling-on-the-vps): self-hosted Coolify is the intended deployment manager for MULTIPLECORP on the Hostinger VPS that will also host the second application. It authorizes preparing the deployment in the repository (TASK-072), **not** installing Coolify, operating the VPS or releasing: every VPS action below is the human Owner's, and no agent accesses the VPS. Placeholder values only; no host detail or secret belongs in this repository.

## 1. What changes and what does not

- **Changes.** Coolify, open source and self-hosted, at no recurring cost (COST_POLICY), runs MULTIPLECORP's stack from [`deploy/coolify/compose.yml`](../../deploy/coolify/compose.yml) — one resource per environment, staging and production, in MULTIPLECORP's own Coolify project. Coolify's proxy holds ports 80 and 443 and issues each domain's certificate; the host's Nginx, installed but serving nothing, is retired by §4's transition. MULTIPLECORP's own Nginx site runs inside its stack ([`deploy/nginx/multiplecorp.conf.template`](../../deploy/nginx/multiplecorp.conf.template)).
- **Does not change.** Every requirement, acceptance criterion, security rule, backup rule and release gate: GitHub Actions is the mandatory CI; production is deployed **by hand** from an approved `main` commit whose CI passed, by its release tag (TASK-073); Coolify's automatic deployment, its webhooks and its preview deployments stay **off** for production; the staging smoke tests, the backup and restore drills, monitoring and the Owner's release approval stay as approved (RELEASE_PLAN; INFRASTRUCTURE §§5–7). TASK-071's unverified inputs stay outstanding.

## 2. The shared management plane — controls for the Owner's acceptance

Coolify's dashboard can start, stop and read every application it manages, the second one included: it is a single point of compromise for both. The controls below are proposed for the Owner's acceptance before production data reaches the VPS (INF-08):

| # | Control |
| --- | --- |
| C1 | The dashboard is never reachable from the internet: its ports are closed in UFW except from the operator's address, or the dashboard has its own HTTPS domain behind the proxy — never both open |
| C2 | One Coolify account per person, a strong unique password, two-step verification on; no shared account, no agent account |
| C3 | Coolify's API tokens off, or scoped and recorded; no token in a repository, chat or ticket |
| C4 | Each application's secrets only in its own resource, marked secret; only that application's owner edits them |
| C5 | Repository access per application by a read-only deploy key or GitHub App, kept in Coolify only |
| C6 | Automatic deployment, webhooks and preview deployments off for production; a deployment is a deliberate operator action |
| C7 | Coolify updates applied by the Owner in a maintenance window after a backup, never automatically during business hours |
| C8 | Coolify's own data — its database and configuration — in the VPS backup plan, apart from each application's data backups |
| C9 | The dashboard's audit trail and the server's access log kept and reviewed after any incident (INFRASTRUCTURE §7) |

## 3. Isolation of the two applications (INF-07)

| Resource | Rule |
| --- | --- |
| Project | One Coolify project per application; staging and production its separate environments |
| Network | Each stack's own networks: `edge` for the proxy to reach `web`, `internal` (no outbound route) for the database and the cache, `egress` for the application's outbound HTTPS. "Connect to predefined network" stays off; no service of one application reaches the other's |
| Database and cache | Each application its own PostgreSQL and Valkey, volumes and credentials — never shared; no published database or cache port |
| Volumes | Named volumes per application and environment; no host path shared |
| Secrets | Each application's variables only in its own resource ([`production.env.example`](../../deploy/coolify/production.env.example) names MULTIPLECORP's); the migration/owner credentials only in the one-shot `migrate` and `db-roles` services, never in a running process (INFRASTRUCTURE §4.4) |
| Backups | MULTIPLECORP's approved backups — local and an encrypted copy outside the VPS and its provider account, the key apart (INF-06; BKR-09; TASK-074); a Coolify scheduled backup counts only where it meets the same rules |
| Runtime | CPU and memory limits on every service, inside OPI-27's reservations, Coolify's control plane counted |
| Operations | A deployment, restart, maintenance mode or restore of one application never touches the other's resource |

## 4. From the host's Nginx to Coolify's proxy — the transition (the Owner's steps)

Nothing is deployed yet, so no live traffic is affected; the steps are still done in order, each checked.

1. **Conflict checks, read-only.** `sudo ss -ltnp` shows what listens on 80, 443, 8000, 6001 and 6002; `systemctl status nginx` shows Nginx's state; `sudo ufw status numbered` records the rules before any change. Stop if anything other than Nginx holds 80 or 443, or anything holds Coolify's ports.
2. **Keep the way back.** Copy `/etc/nginx` to a dated folder in the operator's home and record the UFW rules; nothing is deleted.
3. **Retire the host's Nginx.** `sudo systemctl stop nginx`, then `sudo systemctl disable nginx`; `ss` shows 80 and 443 free.
4. **Install Coolify** with its official installer, after reading the script, on the VPS's existing Docker; let its proxy take 80 and 443; apply §2's C1 and C2 at once.
5. **Verify.** Coolify's proxy answers on 80 and 443; the dashboard is unreachable from an outside address under C1; UFW still allows only the administrative access, HTTP and HTTPS.
6. **Rollback**, if step 4 or 5 fails before any application is deployed: stop Coolify's proxy container, `sudo systemctl enable --now nginx`, restore UFW's recorded rules, confirm with `ss`, and record what failed in OPI-03.

## 5. Staging

In MULTIPLECORP's Coolify project, the staging environment holds one Docker Compose resource: the MULTIPLECORP repository, branch `main`, compose file `deploy/coolify/compose.yml`, the staging domain of OPI-30 on the `web` service's port 8080, its own variables from `production.env.example` with staging values, and resource limits below the headroom both production applications need (INFRASTRUCTURE §3). Synthetic data only (FX-02); started and removed by the operator; never the second application's resources (DIR-079 CB-04).

## 6. A production deployment — by hand, from a verified commit

INFRASTRUCTURE §5's path, through Coolify:

1. **Ready:** a `main` commit whose CI passed and whose staging rehearsal passed, with its release tag (TASK-073); `MULTIPLECORP_RELEASE` set to it.
2. **Pre-deployment backup** by TASK-074's tooling, verified; a failed or unverified backup stops here (BK-03).
3. **Maintenance window:** announced, outside business hours for the first three months; `php artisan down` in the `app` container — the flag lives in the shared `storage` volume every web process reads.
4. **Deploy** the tagged commit in Coolify: the `migrate` service runs the expand-only migrations through the migration connection and the catalogue sync; every process of the release waits for it; workers and the scheduler restart with the release (HO-23); no step flushes Valkey.
5. **Verify:** the health check `/up` through the domain; `php artisan ops:smoke` in the `app` container — the read-only smoke checks of the business heartbeat (INFRASTRUCTURE §6), each line PASS, FAIL or NOT CHECKED, exit code 1 on any FAIL —; `php artisan guards:reconcile` after a release that changes a posting service; then `php artisan up`.
6. **Record** the deployed commit and schema version; a failed step stops for the operator's decision — code rollback is not data recovery (GAP-012).

## 7. Effect on the plan — the total stays 95

| Task | Effect |
| --- | --- |
| TASK-071 | OPI-03 updated by the Owner once Coolify is installed; OPI-27's reservations count Coolify's control plane; its other inputs stay outstanding |
| TASK-072 | Its repository preparation proceeds alongside TASK-071 on the verified OPI-03 facts (TASK_PLAN Change 22); its acceptance criteria are unchanged |
| TASK-078, TASK-084, TASK-085, TASK-087 | The operator's steps run through Coolify's dashboard in place of Compose commands |

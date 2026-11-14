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

**Resource settings**, on staging and production alike: *Preserve repository during deployment* on (General > Build pipeline), since `web` and `db-roles` mount the site template and the roles script from the repository when they run — without it neither starts —; *Connect to predefined network* off; `APP_ENV` `staging` here and `production` on production, the image tag carrying it so the two environments never share a build. **After the first deployment**, before the seed: `docker inspect` of the `db` and `cache` containers lists the stack's `internal` network only — a network Coolify added to either stops here for the Owner's decision (§2, §3) —, and `env` in `app`, the workers and the scheduler holds no `DB_MIGRATION_`, `DB_SUPERUSER` or `DB_ADMIN_` variable (INFRASTRUCTURE §4.4).

**Seed.** After staging's first deployment, in the `app` container: `php artisan iam:install-owner --name=<name> --email=<e-mail> --operator=<the operator's name>` installs staging's first Owner (AU-25), then `php artisan staging:seed --owner=<that e-mail>` makes FX-02's companies — ARJ, BTN and CKP — and its base personas through the product's own commands as that Owner. It runs on staging only, leaves what already exists as it is, and shows each new account's password-setting link once (AU-11); the accounts use reserved example domains, so no mail leaves.

**Rehearse (REL-03)** each release on staging by §6's steps, recording each step's duration for the production window, kept clear of the second application's windows (OPI-27):

- the migration and the worker restart;
- an interrupted migration — the `migrate` service stopped while it runs — and its recovery (RBK-05): PostgreSQL runs each migration in its own transaction, so the expected recovery is the deployment run again; the rehearsal confirms it and records the procedure it proved;
- a job queued under the previous release worked by the new one, and a page loaded before the deployment posting a command afterwards (HO-31);
- the code rollback of §6.

Removed by the operator afterwards, its volumes with it.

## 6. A production deployment — the release runbook (REL-08–REL-13)

INFRASTRUCTURE §5's path through Coolify, by the human operator, from a verified `main` commit; each step names its source. Every step acts on MULTIPLECORP's own resource alone — never the second application, its data or its secrets —, no step flushes Valkey, and the shared host proxy changes only by §4's tested reload (INF-07; RBK-06). A failed step stops the release for the operator's and the Owner's decision — fix forward, the code rollback below, or a data recovery —, the choice and its reason recorded, the application kept in maintenance mode while writes are at risk; a code rollback is not data recovery (RBK-04; GAP-012).

1. **Ready** (§5 step 1; REL-01–REL-07, TASK-073's release process): a `main` commit whose CI passed and whose staging rehearsal passed (REL-03), with the Owner's release approval and its release tag; `MULTIPLECORP_RELEASE` set to it.
2. **Pre-deployment backup** (REL-08; BK-03; §5 step 2): by TASK-074's tooling, its verification completed; a failed or unverified backup stops here.
3. **Maintenance window** (REL-10; §5 step 3; DIR-083 OE-08): announced, outside business hours for the first three months after go-live, for the duration the rehearsal measured. `php artisan down` in the `app` container: the flag lives in the shared `storage` volume every web process reads; a page through the domain answering 503 confirms it, while `/up` keeps answering OK (H9-14).
4. **Drain** (REL-09; HO-34): the queue workers pause in the maintenance mode, so `php artisan queue:work --stop-when-empty --force --tries=1` in the `app` container works the in-flight cascades and exits; `php artisan ops:smoke`'s queue line then shows 0 jobs.
5. **Deploy** (REL-10; §5 steps 4–5): the tagged commit in Coolify. The `migrate` service runs the expand-only migrations through the migration connection, then the catalogue sync; every process of the release waits for it; the workers and the scheduler restart with the release (HO-23); job payload versions are honoured (HO-31).
6. **Verify** (REL-11; §5 step 6; GAP-013): `/up` through the domain; `php artisan ops:smoke` in the `app` container — the read-only smoke checks of the business heartbeat (INFRASTRUCTURE §6), each line PASS, FAIL or NOT CHECKED, exit code 1 on any FAIL; the scheduler's lines may show the window's pause, since scheduled tasks wait in the maintenance mode —; `php artisan guards:reconcile --operator=<name>` after a release that changes a posting service (GAP-031); the operator's read-only check of WS-10's gates and GLC-07's items. Then `php artisan up`, and `php artisan ops:smoke` again after five minutes, every line PASS but the backups' NOT CHECKED until TASK-074's tooling records them.
7. **Monitor and record** (REL-12; §5 step 8): the heartbeat and alerts watched through the release's first business day; the deployed commit and the schema version — the last migration `php artisan migrate:status` lists — recorded with the release record (TASK-073).
8. **Contract later** (REL-13; §5 step 7): a migration that drops, renames or retypes runs only in a later release, after the code that needed the old form is gone, its file named `_contract_`; the architecture test refuses any other (HO-31).

**Code rollback** (RBK-01): in a maintenance window, by steps 3–6 — the queue drained by the newer workers first, then the previous release's tag deployed; its migrations were expand-only, so no down migration runs; pages of the newer release are reloaded or their commands refused, since the one-release validity of payloads and commands holds forward only (CONCURRENCY_IDEMPOTENCY §15). Rehearsed at REL-03.

**The administration credential** (INFRASTRUCTURE §4.4; SECURITY EN-07) is in no running process: `db-roles` provisions the login, and for an approved `guards:maintain` run the operator opens a shell in the `app` container and gives it to that run alone, from the password manager — `read -r DB_ADMIN_USERNAME; stty -echo; read -r DB_ADMIN_PASSWORD; stty echo; export DB_ADMIN_USERNAME DB_ADMIN_PASSWORD; php artisan guards:maintain <family> --reason=<why> --reference=<the defect or the restore> --operator=<name>` —, never on a command line, in a file or in the resource's variables.

## 7. Effect on the plan — the total stays 95

| Task | Effect |
| --- | --- |
| TASK-071 | OPI-03 updated by the Owner once Coolify is installed; OPI-27's reservations count Coolify's control plane; its other inputs stay outstanding |
| TASK-072 | Its repository preparation proceeds alongside TASK-071 on the verified OPI-03 facts (TASK_PLAN Change 22); its acceptance criteria are unchanged |
| TASK-078, TASK-084, TASK-085, TASK-087 | The operator's steps run through Coolify's dashboard in place of Compose commands |

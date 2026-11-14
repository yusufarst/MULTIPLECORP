# Coolify as the shared deployment manager — change proposal

Status: PROPOSED — awaiting the Owner's approval | Updated: 2026-11-14 | Owner: Planning

Source: the Owner's directive of 2026-11-14 ([transcript](../00-governance/sources/EXECUTION_OWNER_DIRECTIVE_SHIP-MULTIPLECORP_2026-11-14.txt), section 4): the Owner prefers Coolify as the shared deployment manager for MULTIPLECORP and the second application on the Hostinger KVM 4 VPS, each application strictly isolated. This file is the change-control record that decision needs; nothing in it applies until the Owner approves it, and no agent accesses the VPS.

## 1. Why an approval is needed

[INFRASTRUCTURE P9Q-02](INFRASTRUCTURE.md#p9q-02--the-deployment-tooling-on-the-vps) is decided by DIR-079 CB-05 and restated by DIR-110: **Docker Compose, unless OPI-03 records that the VPS already runs Coolify**. OPI-03 recorded Docker, Docker Compose, Nginx and UFW, and no Coolify. Installing Coolify now is therefore a change to an approved decision (CHANGE_CONTROL), not the application of its rule. No requirement, acceptance criterion, security rule, backup rule or release gate changes with it.

## 2. The decision proposed

- **Coolify, self-hosted, open source, no recurring cost** (COST_POLICY), manages the deployments of both applications on the VPS; P9Q-02 becomes option (b) on the Owner's decision.
- **The edge.** Coolify's proxy owns ports 80 and 443 and issues the certificates (Let's Encrypt) for each application's domain (OPI-30). The host's Nginx, installed but serving nothing, is stopped and disabled, so two proxies never compete for the same ports. The alternative — Nginx kept as the edge and Coolify's proxy off — is possible but every route is then kept by hand; it is not recommended.
- **The dashboard.** Coolify's own ports are never open to the internet: UFW allows them only from the operator's address, or the dashboard is reached through its own HTTPS domain; the Coolify account has a strong password and two-step verification; no agent receives its access.
- **What does not change.** GitHub Actions stays the mandatory CI; only a commit whose CI passed and whose release tag exists (TASK-073) is deployed; the staging smoke tests, the backup and restore drills, monitoring, the runbooks and the Owner's release approval stay as approved (RELEASE_PLAN; INFRASTRUCTURE §§5–7).

## 3. Isolation of the two applications (INF-07)

| Resource | Rule |
| --- | --- |
| Project | One Coolify project per application; staging and production as separate environments of it |
| Network | Each application's services on their own Docker network; no service of one reaches the other's; databases and caches never publish a host port |
| Database and cache | Each application its own PostgreSQL and Valkey containers and volumes — never shared, never the other's credentials |
| Volumes | Named volumes per application and environment; no host path shared between applications |
| Secrets | Each application's variables only in its own Coolify resource, marked secret; never in the repository, a chat or a log (SECURITY SX-01, SX-04) |
| Backups | MULTIPLECORP's approved backups unchanged — local on the VPS and an encrypted copy outside the VPS and its provider account, the key kept apart (INF-06; BKR-09; TASK-074). A Coolify scheduled backup counts only where it meets the same rules |
| Runtime resources | CPU and memory limits per service, inside the reservations OPI-27 records for each application and for Coolify's own control plane |
| Repository access | A read-only deploy key or GitHub App per application, kept in Coolify only |

## 4. Effect on the plan — no new Task

| Task | Effect |
| --- | --- |
| TASK-071 | OPI-03 updated once Coolify is installed; OPI-27 includes Coolify's control plane in the reservations |
| TASK-072 | The deployment definition is the same Compose stack, written for Coolify's Docker Compose deployment, with its health and smoke checks and maintenance mode unchanged |
| TASK-078 | The staging stack is the staging environment of MULTIPLECORP's Coolify project |
| TASK-084, TASK-085, TASK-087 | The operator's steps run through Coolify's dashboard in place of Compose commands |

## 5. The Owner's steps after approval (the Owner performs them; no agent touches the VPS)

1. Install Coolify with its official installer, after reading it, on the VPS that already runs Docker.
2. Close Coolify's dashboard ports to the internet in UFW, or give the dashboard its own HTTPS domain; set its account's two-step verification.
3. Stop and disable the host's Nginx; let Coolify's proxy hold ports 80 and 443.
4. Create one project per application and the MULTIPLECORP staging environment; record what was done in OPI-03 and the reservations in OPI-27, without secrets or host details in the repository.
5. Continue with the outstanding operator inputs — OPI-22, OPI-29, OPI-30 and OPI-05 — which Coolify does not replace.

# Operator input request

Status: OPEN | Date: 2026-11-11 | Raised by: the executor, under [DIR-108](../00-governance/DECISION_LOG.md#dir-108--the-owners-intra-task-helper-amendment-to-dir-102) | For: the operator (today the Owner, [OPI-01](../09-operations/INFRASTRUCTURE.md)) and the client

The agent Tasks can be finished in the repository, but the Tasks that need the VPS, the domain, the alert channel and the legacy data cannot start until these inputs are recorded. **Send no credential, password, key, token, IP address or host name to the executor or into the repository.** Answer each item by its role and a yes/no or a short fact. Record secrets only where [INFRASTRUCTURE](../09-operations/INFRASTRUCTURE.md) says they are kept. Every input is defined in INFRASTRUCTURE §OPI; the RELEASE_PLAN gate it unblocks is in its last column.

## Needed first — they unblock staging (TASK-071, TASK-072, TASK-078)

| Input | What to give | Unblocks |
|---|---|---|
| OPI-26 | The client grants the operator access to the VPS, and to the provider's panel where an operation needs it. Record "granted to the operator", by role. The agent never receives access (INF-01) | TASK-071, then TASK-072 and TASK-078 |
| OPI-03 | What the VPS already runs: Docker, Docker Compose, Coolify, Nginx and any other service, plus its firewall tool. A list is enough | TASK-072 (deployment definition) |
| OPI-02 | The VPS's free disk, current usage, operating system and bandwidth period, as measured on the host | TASK-072, TASK-078 |
| OPI-27 | The second production application's CPU, memory, disk and bandwidth use, and its backup, maintenance and deployment windows | TASK-078 |
| OPI-29 | The provider-side features active on the plan, as verified on the panel, especially any automation or AI assistance able to act on the host | TASK-078; before production data |
| OPI-30 | The application's domain and the name staging uses (the names only; the certificates are issued on the host) | TASK-078 |

## Needed before go-live

| Input | What to give | Unblocks |
|---|---|---|
| OPI-05 | A zero-cost alert channel the operator confirms. Recommended: a Telegram bot to the operator's and the Owner's phones (DIR-079 CB-06). Its token stays on the host | TASK-075, TASK-080 |
| OPI-04 | The domain's DNS provider and whether a proxy fronts it | TASK-084 |
| OPI-28 | Who holds the provider account, by role, and whether its sign-in uses a second factor | TASK-084 |
| OPI-31 | Every holder of administrative access to the host or the provider account, by role, including the second application's operators | TASK-084 |
| OPI-32 | The off-server backup copy's destination: a device or storage the Owner or the client already holds, and who keeps its keys apart from the VPS, by role (BKR-09) | TASK-084 |
| OPI-07, OPI-08 | Measured PHP worker memory, Argon2id timing and PostgreSQL behaviour on the VPS; outbound HTTPS from the VPS to Google and the breached-password range service | TASK-079, TASK-084 |
| OPI-06 | The Owner's Google Cloud access for the OAuth clients, only if Google sign-in is to be on | TASK-082 |
| DEP-05 | The data steward's private, sanitized structures, counts and reconciled control totals for the legacy data, and the agreed cutover boundary | TASK-081 (import rehearsal), TASK-087 |

## Outside this request

The OWNER Tasks (TASK-082, TASK-083, TASK-086) stay with the Owner. No agent runs a HUMAN OPERATOR or OWNER Task, or records its evidence.

---

## Owner-prepared response — 12 November 2026 (DRAFT, not evidence of completion)

**Status: OWNER RECOMMENDATIONS PREPARED; OPERATOR AND CLIENT FACTS STILL OPEN.**

This addendum is prepared at the Owner's request so the Executor can act without repeatedly asking for preferred implementation policies. It does **not** attest that the client granted access, the operator inspected the VPS, a service exists, a backup works, or any other live-state fact. The source request above remains OPEN. Every required human input stays BLOCKED until the authorized person supplies and records evidence at the location prescribed by `INFRASTRUCTURE`, `BACKUP_RECOVERY`, and `RELEASE_PLAN`.

### Policy preferences the Executor can use now

| Topic | Owner-prepared direction | Classification | What remains open |
|---|---|---|---|
| Agent/host boundary | No AI/agent receives VPS, panel, production-data, backup, or secret access. The authorized human operator performs every host operation. | Existing binding policy (INF-01/INF-02), reaffirmed | OPI-26 grant to operator |
| Deployment tooling | Keep Nginx. Prepare Docker Compose as the default **only if** OPI-03 does not establish existing Coolify; use Coolify only if its existing installation is confirmed. Do not assume either is installed. | Existing conditional rule, not OPI-03 evidence | Actual service and proxy inventory |
| Second application | Preserve strict isolation; do not restart, inspect or change the other production application. Coordinate windows only after OPI-27 is received. | Existing binding policy (INF-07) | Verified resource use and windows |
| Alert delivery (OPI-05) | **Preferred candidate:** zero-cost Telegram bot with notifications to Owner and operator, subject to operator confirmation, cost-policy check and an actual delivery test. Secret bot token never goes into repo/chat. Until confirmed, status stays OPEN. | Owner preference, NOT confirmation | Operator confirms channel and successful receipt |
| Provider-side features (OPI-29) | Default safety policy: any automation/AI assistance capable of acting on host or data must be verified OFF before production data, unless a separately recorded Owner/client decision accepts its reach. | Existing binding policy (INF-08), not an inventory | Actual provider-panel verification |
| Off-server backups (OPI-32) | Require an encrypted copy outside VPS and provider account on storage already held by Owner/client; encryption key held separately. Do not choose a specific device or claim that it exists without evidence. | Existing policy (INF-06/BKR-09), preference for no additional paid service | Real destination/custodian, copy and restore evidence |
| Google sign-in (OPI-06) | Keep Google sign-in disabled in environments lacking an OAuth client; configure it only through Owner's authorized Google Cloud access and verified redirect URLs. Do not drop V1 scope without approval. | Existing conditional safety requirement | Actual Owner access and OAuth configuration |
| Costs and credentials | No unapproved recurring costs; never publish passwords, keys, tokens, real hostnames or IPs, private URLs, provider account details or raw business data. | Existing project boundary | Operator-held private records |

### Facts that cannot be honestly filled by an assistant

| Input | Current truthful response | Authorized evidence required |
|---|---|---|
| **OPI-26** | **NOT CONFIRMED** — no evidence of client granting operator access provided | Client confirms grant to the named human operator, recorded by role (not credentials) |
| **OPI-03** | **NOT PROVIDED** — installed host services, proxy and firewall tool unknown | Operator's observed inventory of Docker/Compose/Coolify/Nginx, other services and firewall |
| OPI-02 | NOT MEASURED | Operator-observed OS, disk use/free, bandwidth period |
| OPI-27 | NOT MEASURED | Second application's resource use and maintenance/backup/deployment windows |
| OPI-29 | NOT VERIFIED | Operator's provider-panel feature inventory and active/off states |
| OPI-30 | NOT PROVIDED | App/staging domain names retained in operator-only records; repo notes verified status without hostnames |
| **OPI-05** | **CANDIDATE ONLY — NOT ADOPTED OR TESTED** | Operator confirms the zero-cost alert channel and checks delivery to the intended recipients |
| OPI-04 | NOT PROVIDED | DNS provider role/configuration and edge-proxy status, safely recorded |
| OPI-28 | NOT PROVIDED | Provider-account holder role and two-factor status |
| OPI-31 | NOT PROVIDED | All administrator-access holder roles, including second app's operators |
| **OPI-32** | **DESTINATION NOT SELECTED/VERIFIED** | Existing off-server destination and separate key custodian; later copy/restore evidence |
| OPI-07, OPI-08 | NOT MEASURED | Host measurements and outbound HTTPS verification by operator |
| OPI-06 | NOT VERIFIED | Owner confirms Google Cloud access and environment-specific client readiness |
| **DEP-05** | **NOT RECEIVED** | Data steward supplies *private* sanitized structure/count/control-total evidence and cutover boundary through authorized channel; no real legacy extract to repo/AI |
| OPI-21, OPI-22 (TASK-071) | NOT MEASURED/SELECTED | Operator's measured baseline disk usage/free and private backup-directory arrangement |
| Other release inputs | UNVERIFIED | Use `RELEASE_PLAN` and the applicable Task's complete checklist; this packet does not supersede them |

### Immediate collection checklist for the human operator

1. Obtain the client's VPS/provider-panel access grant directly to the named human operator (OPI-26). Record the grant by role only. **Never forward access to an agent.**
2. With that human-held access, inspect the existing host configuration without changing or restarting the second production application. Capture OPI-03, OPI-02, OPI-27, OPI-29, OPI-30, and staging-related OPI-21/22 in the prescribed private/approved records.
3. Confirm a zero-cost alert channel (OPI-05). Telegram is the suggested option, not an automatically adopted service. Keep tokens private and test actual delivery.
4. Record DNS, provider-account roles/2FA, access-holder roles, and separately held off-server backup destination (OPI-04/28/31/32).
5. Arrange data steward's sanitized structures/counts/control totals (DEP-05) **outside the repository**. Give agents only the synthetic-data shape and non-sensitive counts that their staging tests legitimately require.
6. Run host measurements (OPI-07/08) only through the authorized operator. Record PASS/FAIL with traceable non-secret evidence.

### Recording rule

- The Executor **may** incorporate these preferences into preparations, code templates, or proposed decision records within existing approved contracts.
- The Executor **must not** mark OPI-26, OPI-03, OPI-05, OPI-32, DEP-05 or any operator/Owner Task DONE solely because this file exists.
- Where a Task requires an explicit factual record in a different approved document, the human operator/Owner records it there. Do not substitute this addendum for required evidence.
- All unresolved facts should be reported together in a compact operator action list, without sending the same question repeatedly.

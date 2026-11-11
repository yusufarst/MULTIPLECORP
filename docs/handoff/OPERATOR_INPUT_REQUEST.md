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

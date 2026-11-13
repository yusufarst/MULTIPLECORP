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

## Operator handoff — owed inputs and the Owner's read-only steps (2026-11-13, DIR-112)

Prepared on 2026-11-13 under DIR-112 §8 from INFRASTRUCTURE §9, BACKUP_RECOVERY §7, RELEASE_PLAN §9, V1_SCOPE and the contracts of TASK-071–TASK-087. Nothing here is evidence; each input stays owed until its human actor records it (DIR-083 OE-14). **No secret, password, key, token, IP address, host name, port or account name in a chat or the repository.**

Legend: REQ `docs/handoff/OPERATOR_INPUT_REQUEST.md`; INF §9 `docs/09-operations/INFRASTRUCTURE.md#9-the-operators-inputs`; BKR §7 `docs/09-operations/BACKUP_RECOVERY.md#7-the-operators-inputs`, both restated in `docs/10-release/RELEASE_PLAN.md#9-the-go-live-checklist`; UAT = TASK-086's record; operator = the Owner today.

### 1. Owed inputs

| ID | Responsible | Information needed | Recorded in | Blocks | Non-sensitive summary enough? | Access / authorization? |
|---|---|---|---|---|---|---|
| ***Before staging*** | | | | | | |
| OPI-26 | Client grants; operator records | VPS (and panel) access for the operator, never an agent | INF §9, by role | TASK-071 → TASK-072, TASK-075, TASK-078, TASK-082 | Yes; credentials stay with operator | Yes: client's grant |
| OPI-03 | Operator | Docker, Compose, Coolify, Nginx, other services, firewall tool, public-HTTPS proxy | INF §9 | TASK-071, TASK-072 | Yes; no rules, ports, paths | Yes: VPS |
| OPI-02 | Operator | Measured free disk, usage, OS, bandwidth period | INF §9 | TASK-071; feeds TASK-079 | Yes: figures | Yes: VPS, panel |
| OPI-21 | Operator | Staging: U₀, free space; go-live: DB, F, L, D after import | BKR §7 | TASK-071; TASK-079, TASK-087 (GLC-05) | Yes: figures | Yes: VPS |
| OPI-22 | Operator | Backup directory's volume and permissions | BKR §7, by role | TASK-071 | Yes; path kept privately | Yes: VPS |
| OPI-27 | Operator; client (windows) | Second application's CPU, memory, disk, bandwidth; its backup, maintenance, deployment windows | INF §9 | TASK-071; feeds TASK-078, TASK-079, TASK-081, TASK-084 | Yes; never its name | Yes: VPS, client |
| OPI-29 | Operator; client and Owner decide (GLC-24) | Each provider-side feature, active or not, verified on the panel | INF §9 | TASK-071, TASK-078, TASK-084 | Yes; no provider name | Yes: panel |
| OPI-30 | Operator keeps | Domain and staging name | Private record; INF §9 "kept, never recorded here" | TASK-071; read by TASK-072, TASK-078, TASK-082 | **No**: host names stay private | No |
| OPI-05 | Operator confirms | Zero-cost channel to the Owner's phone (Telegram recommended); sole control or not; §4C.4 record first | INF §9; COST_POLICY | TASK-071, TASK-075, TASK-080 | Yes; token and chat ID secret, VPS environment only | Yes: free bot account |
| ***Before go-live*** | | | | | | |
| OPI-04 | Operator | DNS provider; edge proxy or not | INF §9 | TASK-084 | Yes; no records | Yes: domain holder |
| OPI-28 | Client (holder) | Provider account's second factor, confirmed by the holder | INF §9 | TASK-084 (GLC-22) | Yes; no account name | Yes: provider account |
| OPI-31 | Operator, from client | Every administrative-access holder, second application's included | INF §9, by role | TASK-084 | Yes: roles only | Yes: client |
| OPI-32 | Operator names; Owner or client holds | Existing off-server destination; key custodian apart from the VPS | BKR §7, by role | TASK-084 (GLC-20) | Yes; keys in the operator's secret store, never with the copy | Yes: holder's storage |
| OPI-07 | Operator | Worker memory, Argon2id timing, PostgreSQL behaviour on staging | INF §9 | Measured in TASK-079 → TASK-084 | Yes: figures | Yes: VPS |
| OPI-08 | Operator | Outbound HTTPS to Google and the breached-password range service | INF §9 | Tested in TASK-084 (GLC-08) | Yes: PASS/FAIL | Yes: VPS |
| OPI-25 | Operator | Backup and restore-drill durations | BKR §7 | Measured in TASK-079; feeds TASK-081, TASK-084 | Yes: figures | Yes: VPS |
| OPI-23 | Operator proposes; Owner approves | Backup time (03:00 WIB recommended) against OPI-27, OPI-25 | BKR §7 | TASK-084 (GLC-22) | Yes | Yes: Owner's approval |
| OPI-06 | Owner | Google Cloud access for the OAuth clients; sign-in off until then (GLC-09) | INF §9; COST_POLICY | TASK-082 | Yes; client secret only in environment configuration | Yes: Owner's Google Cloud |
| OPI-01/24 | Owner; client designates | Alternate operator and backup-duty alternate | INF §9; BKR §7 | TASK-083 (GLC-21) → TASK-087 | Yes; contacts in the private contact record | Yes: client; host access only while acting |
| OPI-09 | Operator | Role answered; owed: the installation audit event | That event | TASK-085 (GLC-13) | **No**: identity only in the event | Yes: production host |
| DEP-05 | Data steward; Owner (boundary) | Sanitized structures, counts, control totals; cutover boundary | Private, outside the repository (IMP-12) | TASK-081; feeds TASK-077, TASK-079, TASK-087 | **No**: legacy data never in repository, staging or chat; agents get counts | Yes: legacy data |
| DEP-03 | Validators | Scanners, phones/browsers, printing tested | UAT | TASK-086 | Yes | Yes: real devices |
| DEP-04 | Owner, Admin | Sanitized examples, fields, print outputs validated | UAT | TASK-086 (GLC-17) | Yes; sanitized only | No |
| DEP-06 | Owner, Admin Operasional | Availability (review hours), acceptance evidence | UAT | TASK-086, TASK-087 | Yes | No |
| DEP-09 | Operator | Users, volumes, office network observations | TASK-079; UAT | TASK-079, TASK-086 | Yes | Yes: office observation |
| DEP-11 | Account holders; operator | Authenticator phones; range-service HTTPS (OPI-08) | UAT; INF §9 | TASK-084, TASK-085, TASK-086 | Yes; no TOTP secrets | Yes: own phones; VPS |
| ***Also required, not listed*** | | | | | | |
| P9Q-15 | Client with Owner | Who settles a dispute between recovered pre-window Owners | TASK-076 runbook (INF §7.3 step 4) | TASK-083 (GLC-21) | Yes: role | Yes: client's decision |
| GLC-16 | Operator, Owner | Private incident and Owner contact records | Outside repository and VPS | TASK-084 | **No**: contacts private | No |
| CB-10 | Operator | Free external uptime check, §4C.4 record first | COST_POLICY | TASK-080 (GLC-11) | Yes | Yes: free external service |
| GLC-12 | Owner | `main` protection (TASK-073 settings) | TASK-084 record | TASK-084 | Yes | Yes: GitHub admin |

DEP-01, DEP-02, DEP-08 and DEP-10 fall under OPI-02/21/25, OPI-22/32, OPI-05, OPI-06.

### 2. Sebelum staging — langkah untuk Owner sebagai operator (Bahasa Indonesia, DIR-112)

Hanya membaca: jangan memasang, menjalankan, menghentikan, me-reload, atau mengubah apa pun di VPS, firewall, proxy bersama, maupun aplikasi kedua (INF-07); alat yang belum ada menunggu TASK-078. **Rahasia — kata sandi, kunci, token, alamat IP, nama host, port, nama akun — tidak pernah ditempel ke chat maupun ke repositori.**

1. **OPI-26:** klien memberi Anda, sebagai operator, akses VPS (dan panel penyedia). Catat hanya "diberikan kepada operator".
2. **Catatan pribadi** di luar repositori dan di luar percakapan AI: untuk setiap input, ID-nya, dari mana dibaca, oleh peran apa, tanggal (WIB), dan faktanya — ini bukti penyelesaian TASK-071.
3. **OPI-02:** jalankan `cat /etc/os-release`, `free -m`, `df -h`; periode kuota bandwidth dari panel.
4. **OPI-03:** jalankan `docker --version`, `docker compose version`, `nginx -v`, `docker ps --filter name=coolify`, `systemctl list-units --type=service --state=running`, `systemctl is-active ufw firewalld nftables`, `sudo ss -tlnp` (untuk melihat proses yang melayani HTTPS publik). Catat nama dan versi saja — tanpa port, alamat, atau aturan.
5. **OPI-27 dan OPI-21:** CPU, memori, dan bandwidth seluruh host dari grafik panel selama periode yang representatif; disk aplikasi kedua (K) dan jendela pemeliharaannya dari klien — jangan pernah membuka atau mengubah aplikasi kedua. Sebelum MULTIPLECORP dipasang, U₀ = disk terpakai − K.
6. **OPI-29:** di panel, setiap fitur penyedia (backup/snapshot, keamanan, konfigurasi, otomasi, AI) dan apakah aktif; jangan mengubah apa pun.
7. **OPI-30 dan OPI-22:** nama domain/staging dan path `<BACKUP_DIR>` hanya di catatan pribadi; volume itu terpisah dari backup aplikasi kedua, izin hanya untuk operator (SX-07); jangan membuat apa pun.
8. **OPI-05:** pastikan kanal tanpa biaya ke ponsel Anda (Telegram direkomendasikan, DIR-110) dan apakah hanya Anda yang mengendalikannya; tulis catatan §4C.4-nya (batas, kuota, lock-in, jalan keluar, portabilitas) sebelum dipakai; token dan chat ID hanya di penyimpanan rahasia Anda.
9. **Pencatatan:** Anda — bukan executor — memperbarui baris INF §9, BKR §7, dan RELEASE_PLAN §9 berdasarkan peran atau angka, diringkas di TASK-071 dalam bentuk TEST_STRATEGY §16; commit dengan identitas Anda, tanpa atribusi AI; pemindai rahasia CI (SM-15) harus lulus, tanpa detail host (AC-7). Input yang belum bisa diperoleh tetap terutang dan TASK-071 tetap BLOCKED (AC-8).

### 3. Inconsistencies

1. **OPI-21/22 missing** from REQ's staging table (L9–16), though `TASK-071.md` L44–45 (AC-2, AC-5) and `BACKUP_RECOVERY.md` L188–189 need them before staging; only the addendum adds them (L74, L80).
2. **OPI-05 timing:** REQ L22, `INFRASTRUCTURE.md` L220 and `RELEASE_PLAN.md` L208 say go-live; `TASK-071.md` L46 (AC-6, AC-8) makes it a TASK-071 input, gating TASK-078; TASK_PLAN §4 has TASK-075 BLOCKED on it.
3. **Chains:** REQ L11 omits TASK-075, TASK-082 (`TASK-071.md` L27–32); L13's OPI-02 → TASK-072 is unsupported, TASK-072 waiting on OPI-03 only (`TASK-072.md` L9, L43).
4. **OPI-30** (REQ L16) asks for names its own L5 forbids (`INFRASTRUCTURE.md` L229; `TASK-071.md` L281); the addendum corrects it (L65); readers TASK-072, TASK-082 omitted (`TASK-071.md` L159, L191).
5. **OPI-07/08** (REQ L27) are produced inside TASK-079 and TASK-084 (`TASK-079.md` L38; `TASK-084.md` L39), not prerequisites.
6. **DEP-05** (REQ L29) also feeds TASK-079 and TASK-077 (`TASK-079.md` L43; `TASK-077.md` L41); `BACKUP_RECOVERY.md` L59 uses the counts before staging.
7. **Omitted:** OPI-23, OPI-25, OPI-01/24, OPI-09, DEP-03/04/06/09/11 and P9Q-15; yet REQ L28 lists OPI-06 though L33 excludes OWNER Tasks.
8. **Pointers** (REQ L5): OPI-21–25 and OPI-32 are BACKUP_RECOVERY §7's, DEP-05 V1_SCOPE's; the last column lists Tasks, not gates.
9. **OPI-28** (REQ L24) re-asks the holder already recorded (`INFRASTRUCTURE.md` L227).

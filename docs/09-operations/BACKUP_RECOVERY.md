# Backup and recovery

Status: REVIEW — Draft of P9, not normative before its approval | Updated: 2026-10-18 | Owner: Planning

**Draft of P9, not normative before its approval.** Run PC-2 drafted this document on 2026-10-18 under [DIR-078](../00-governance/DECISION_LOG.md#dir-077-dir-078-obs-030-and-tech-037--p7-ux-re-baseline-cp-a-decisions-run-pc-2-with-p9s-drafting-baseline-and-drafts) SP-67 and the Owner's [DIR-077](../00-governance/DECISION_LOG.md#dir-077-dir-078-obs-030-and-tech-037--p7-ux-re-baseline-cp-a-decisions-run-pc-2-with-p9s-drafting-baseline-and-drafts) CA-14: completed in PC-4 with the operator's measurements and the Owner's answers, approved separately at OG-3. Nothing here is installed, scheduled or run; no agent connected to the VPS or any production system to write it.

**Sources.** [V1_SCOPE](../01-product/V1_SCOPE.md#v1-local-backup-and-p9-handoff-contract)'s local-backup contract — BK-01–BK-12, the 200 GB disk budget and its thresholds — and DEP-01 and DEP-02; [ACCEPTANCE_CRITERIA](../01-product/ACCEPTANCE_CRITERIA.md) AC-18 and AC-19; DIR-009 and DIR-010; [GAP_REGISTER](../00-governance/GAP_REGISTER.md) GAP-007, GAP-011, GAP-013, GAP-018, GAP-031, GAP-048, GAP-050 and GAP-051; [SECURITY](../05-security/SECURITY.md) AU-21, AU-25, SX-07 and H9-14; [CONCURRENCY_IDEMPOTENCY](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s19-20-handoff-traceability.md) HO-28 and §8; [DATABASE](../04-architecture/DATABASE/README.md) §§22 and 31; [PERFORMANCE](../06-api-performance/PERFORMANCE.md) GT-06 and SZ-08; [PRODUCTION_DATA_SAFETY](../00-governance/PRODUCTION_DATA_SAFETY.md)'s owed procedures; REFERENCE_COVERAGE OWN-06. The rest of P9 is [INFRASTRUCTURE](INFRASTRUCTURE.md), whose §11 maps every P9 requirement.

Authority: P9 — backup, restore, restore testing, the shared-disk budget and the post-incident recovery exercise. It adds no rule: it designs what V1_SCOPE's contract and SECURITY require, within the Owner's DIR-009.

**Public repository.** No host, address, port, user name, secret path, provider account or key detail appears here — placeholders in angle brackets only, such as `<BACKUP_DIR>`; what the operator supplies is §7's list.

## 1. The decision and its limits

- **Local only (DIR-009).** Every V1 backup is stored on the existing production VPS: local backup, never offsite; no external storage, second server or paid service. An external destination needs a later explicit Owner change (DIR-010), which this document does not ask for.
- **What local backup can do.** Recover from accidental data changes, application failure, a failed deployment, selected database corruption and accidental deletion, **when an intact suitable backup and a recoverable VPS remain**. It cannot protect against total VPS loss, total disk failure, deletion of the VPS, provider-level loss, an account compromise that destroys production and backups, or a storage failure of the whole VPS: that exposure stays **accepted** under GAP-018 (RISK-001), unchanged, and is never claimed mitigated.
- **The backup obligation of REFERENCE_COVERAGE OWN-06** — CAP-16 with BK-01–BK-12 — is this document.
- **Targets.** RPO ≤ 24 hours and RTO ≤ 4 hours are operational targets for that recoverable-VPS scenario, measured in drills (§5); a successful local drill does not demonstrate recovery after host or storage loss (AC-18).

| BKR | Rule | Grounds |
| --- | --- | --- |
| BKR-01 | A backup is one recovery point: the PostgreSQL dump and the private files it references, taken coherently — the files captured after the dump's snapshot, so every file row the dump holds has its bytes —, under one identifier and timestamp. | BK-01; BK-02; GAP-011; DATABASE §22, §31 |
| BKR-02 | A backup is successful only when it completed, its checksums verify and its manifest lists every part; anything else is reported failed — never a missing or partial backup reported successful. | BK-07; BK-10; V1_SCOPE |
| BKR-03 | A checksum is not restore proof: only a restore drill proves a recovery point (§5). | BK-07; BK-09 |
| BKR-04 | Cleanup deletes only expired generations, after the newer ones verified, never live data and never the last verified recovery points the retention keeps; a failed backup deletes nothing. | BK-05; BK-12 |
| BKR-05 | A restore never runs over live writes and never as the migration/owner role without the guard verification afterwards; the application reopens only after `ReconcileGuards` reports no difference or the operator decides the differences. | BK-08; HO-28; GAP-031; DATABASE §28 |
| BKR-06 | Backups are operated by the named human operator only; no agent ever holds a backup file or access to one. | INFRASTRUCTURE INF-01, INF-02; PRODUCTION_DATA_SAFETY |

## 2. The twelve obligations

| BK | Design | Evidence (AC-18, AC-19) |
| --- | --- | --- |
| BK-01 | A logical PostgreSQL dump in the custom format, consistent at one snapshot, its recovery point named by timestamp and the deployed commit (BKR-01) | AC-18: the restored database and its recovery point |
| BK-02 | The private file disk (DATABASE §22) — evidence, renditions, identity assets, product images — captured with each dump under the same identifier; renditions and thumbnails are reproducible and exports short-lived, so exports and temporary files are left out (GT-06) | AC-18: file reconciliation against `file_objects` |
| BK-03 | Before every deployment a backup runs and its verification completes; a failed or unverified backup stops the deployment (INFRASTRUCTURE §5) | AC-19: the pre-deployment run and its stop on failure |
| BK-04 | A daily scheduled backup at `<BACKUP_TIME>`, outside business hours and clear of `ReconcileGuards`' nightly snapshot (HO-28), meets the 24-hour RPO; a missed or stale run — no verified backup within `<BACKUP_STALE_HOURS>` — alerts (INFRASTRUCTURE §6) | AC-19: the schedule, a missed-run alert |
| BK-05 | Generations rotate as separately identifiable directories `<BACKUP_DIR>/<timestamp>-<commit>/`, never one file overwritten | AC-19: rotation across generations |
| BK-06 | Retention keeps several recovery points — daily, weekly and monthly generations, their counts P9Q-10's —, sized from measured capacity (§3), never guessed from the nominal disk | AC-19: the retention boundary |
| BK-07 | A manifest with each part's SHA-256 and size, verified after writing and before cleanup; a truthful status per run (BKR-02, BKR-03) | AC-18: checksums; AC-19: corrupt or incomplete runs reported failed |
| BK-08 | The restore procedure of §4, isolated from live writes, with critical-record and file reconciliation and safe job resumption, followable by the operator alone | AC-18: the operator follows it without overwriting live data |
| BK-09 | Restore drills at the cadence of P9Q-11, each recording the recovery point, the elapsed time and whether business operation was usable (§5) | AC-18: periodic retests with cadence and results |
| BK-10 | Each run logs its outcome; a failure, a stale backup or a failed cleanup alerts the named operator through INFRASTRUCTURE §6's channel (P9Q-03) | AC-19: failures logged and actionable |
| BK-11 | Disk monitoring with a warning and a critical threshold and reserved headroom (§3); crossing one alerts and starts the capacity response | AC-19: disk-pressure assertions |
| BK-12 | Cleanup strictly by retention after the newer generations verified (BKR-04); a failed backup or cleanup never deletes required generations or live data | AC-19: cleanup-failure assertions |

## 3. The shared-disk budget

The 200 GB nominal disk of DEP-01 holds production and its backups; nominal capacity is not free space. P9 budgets it from **measured** figures (OPI-21), never from the nominal size, and sets every number below in PC-4.

| Term | What it holds | Input |
| --- | --- | --- |
| U₀ | the operating system and everything else already on the VPS | measured (OPI-21) |
| DB | PostgreSQL's data with its indexes and WAL | measured, grown by PERFORMANCE's GT-04–GT-05 (SZ-08) |
| F | uploaded evidence, identity assets, product images and renditions | measured, grown by GT-06 |
| L | logs and audit files within LG-06's retention (H9-08) | measured |
| X | exports and temporary files, short-lived (FL-09; PF-28's row bound as a guard) | bounded by the export limits |
| D | container images and volumes, if containers are used | measured |
| B | the retained backup generations: Σ (dump + files) over P9Q-10's counts | computed |
| S | staging's transient stack during a release (INFRASTRUCTURE P9Q-01) | measured on the first rehearsal |
| P | the peak during a backup — one generation being written beside the retained ones — or during a restore drill — a restored copy beside production | computed |
| H | reserved free headroom | set by P9 |

- **The budget holds** when U₀ + DB + F + L + X + D + B + max(S, P) + H stays within the measured capacity, with the growth of one year projected; the projection is redone monthly with GT-07's report.
- **Thresholds.** A warning at `<DISK_WARN_PCT>` and a critical level at `<DISK_CRIT_PCT>` of used space, each derived from the terms above so that a backup and a restore drill still fit at the warning level (BK-11).
- **Disk pressure.** At the warning level the operator is alerted and the capacity response starts: shorten the transient terms first — exports, temporary files, staging —, then review retention with the Owner. At the critical level no new backup generation starts unless the space for it and the retained verified generations is there; the skipped run is logged and alerted, never reported successful; nothing required is deleted to make room (BKR-02, BKR-04; V1_SCOPE).

## 4. The restore procedure

Run by the operator alone, from the recovery point chosen with the Owner; never over live writes (BK-08; BKR-05; GAP-011).

1. **Stop writes.** The application in maintenance mode; queue workers and the scheduler stopped.
2. **Choose and verify** the recovery point: its manifest's checksums verify (BK-07); a corrupt or missing generation sends the procedure to the next older verified one.
3. **Restore into isolation.** The dump into a new database and the files into a new directory, never over the live ones; the operator keeps the live state until the restore is accepted.
4. **Reconcile.** Every `file_objects` row has its bytes and every byte file its row; issued documents with their renditions — a missing rendition is rendered again from the issued snapshot, never re-issued or renumbered (GAP-007); the critical records the operator and the Owner check — the latest payments, receipts and issued numbers — against the business's own papers.
5. **Verify the guards.** `ReconcileGuards` before reopening; a difference is reported to the operator and the Owner and never repaired silently (HO-28; GAP-031; CONCURRENCY_IDEMPOTENCY §8).
6. **Resume safely.** Owed work restarts through its durable rows and sweeps (JB-02; JB-09), not from Valkey; the scheduler and the workers restart (GAP-011).
7. **Reopen and record** the recovery point, the elapsed time and what was lost after it.

A restore after a key compromise is not this procedure alone: it is AU-25's Scenario B, rehearsed in §6.

## 5. Restore testing

- **Drills** at the cadence of P9Q-11, on the VPS outside business hours, with the transient space of §3's P term: the restore of §4 into isolation, the elapsed time measured against the 4-hour RTO and the recovery point's age against the 24-hour RPO, the reconciliation done, the result recorded — usable or not (BK-09; AC-18).
- **Cases** (AC-18; AC-19): an intact backup; a corrupt generation; a missing generation; an incomplete run; a disk-pressure run; a failed cleanup — each with its expected truthful status.
- **Record.** Each drill's date, recovery point, elapsed time, result and follow-ups, kept by the operator; a summary without secrets enters P9's evidence for OG-3.

## 6. The tabletop incident-recovery exercise

The script for the exercise DIR-051 F4 requires (GAP-051), to run **with the Owner in PC-4** — a walk-through on paper with the runbook of H9-14, nothing executed on any system. It covers the post-incident recovery of SECURITY AU-25 — the incident command of AU-21, Scenario A or B, the OWNER-restoration command, recovery through AU-14 path (e) and the review — and the case GAP-048 accepts: an Owner legitimately removed during the compromise window and temporarily restored by recovery (RISK-009). The carried cases of GAP-050 are walked as variations. The result is recorded in PC-4's evidence; GAP-051 closes only then, and GAP-048's accepted risk is reviewed with it, unchanged until then.

**Roles:** the Owner; the operator (`<OPERATOR>`); a second pre-window Owner when the scenario has one; Planning as scribe. **Material:** the runbooks of AU-14, AU-21 and AU-25 (INFRASTRUCTURE §7), the dated key register, a fictional audit trail of the group ARJ, BTN and CKP.

| TT | Step | The question the step answers | Expected — the stop if not |
| --- | --- | --- | --- |
| TT-01 | **The alarm.** A leaked `APP_KEY` is suspected | who is told, through which channel, and what is written first? | the operator and the Owner told through INFRASTRUCTURE §6's channel; the time and the suspicion recorded |
| TT-02 | **The window's start**, set with the Owner — the earliest the evidence allows, or the leaked key's creation time in the dated key register | is the start recorded once, as an input of the incident command, and read by every later step? | recorded once; an unregistered or ambiguous key creation leaves no pre-window Owner certain — the first halt |
| TT-03 | **The inventory**: could the attacker write to the database or the server? | Scenario A or Scenario B? | Scenario A only when the inventory excludes write access; otherwise B |
| TT-04 | **Scenario A — the incident command** (AU-21), protocol (a)–(d): opening sweep, maintenance mode without a bypass, every account processed, closing sweep | does the maintenance flag reach every web process before the command and lift only after the closing sweep? | each step in order; keys rotated as H9-14 orders |
| TT-05 | **Scenario A — restoration**: a pre-window Owner removed during the window — the legitimately removed Owner of GAP-048 among them — is verified in person and restored by the OWNER-restoration command | is every restored Owner shown, before the review, with the actor and time of the removal it reversed? | the runbook's list shown; the command's refusals understood |
| TT-06 | **Scenario A — recovery** of each pre-window Owner through AU-14 path (e): a new password and TOTP | is the order kept — restoration, then recovery, then review? | the order kept; no account touched before its restoration |
| TT-07 | **Scenario A — the review** by the recovered pre-window Owners: accounts, roles, grants, credential links and Google links changed in the window | is the legitimately removed Owner's removal decided again here, and its restoration reversed through the ordinary commands if confirmed? | the removal re-decided by the review; the temporary restoration ends (GAP-048) |
| TT-08 | **Scenario B**: the untrusted host out of service; a backup or snapshot independently verified as predating the compromise and trustworthy — the DIR-009 same-VPS backup never presumed clean | does such a backup exist? | if none, the in-application path stops for the Owner's incident decision outside the application |
| TT-09 | **Scenario B — the restored state**: new keys, every secret rotated, the incident command run on it, then steps TT-05–TT-07 against the restored audit trail | is pre-window status read at the window's start, or at the backup's time when older, and shown with the restored Owner set? | the runbook's list with the backup's time |
| TT-10 | **The halts**: no pre-window Owner certain; none available | where does each halt lead, on a trusted and on a restored state? | to Scenario B on a trusted state; to the Owner's decision outside the application on a restored one |
| TT-11 | **GAP-050's variations**: a window start later found to be wrong; a halt on a trusted state; the end of recovery and a late role change | what does the runbook say, and what stays open for P8 (M-46)? | each answered or recorded as open |
| TT-12 | **The end of recovery** — every active Owner a pre-window Owner of the run or confirmed by one — and the record | is the end recorded only then, and the exercise's findings assigned? | findings with owners; GAP-051's result recorded |

## 7. The operator's inputs

| OPI | Input | Where it goes |
| --- | --- | --- |
| OPI-21 | The measured disk terms U₀, DB, F, L and D of §3, and the VPS's free space (DEP-01) | recorded as figures |
| OPI-22 | The local backup directory, `<BACKUP_DIR>`, on a volume the operator names, and its permissions (DEP-02; SX-07) | recorded as a role, the path kept |
| OPI-23 | The backup time `<BACKUP_TIME>`, outside business hours | recorded |
| OPI-24 | The person who holds backup and restore duty and an alternate (GAP-013) | recorded by role |
| OPI-25 | The measured duration of a full backup and of a restore drill on the VPS | recorded as figures, against the RPO and RTO |

## 8. Owner questions

Answered at the latest at OG-2 (SP-67); a paid option is recorded under COST_POLICY §4C.2 and never adopted.

### P9Q-10 — How far back the recovery points reach

- **Options.** (a) **7 daily, 4 weekly and 3 monthly** generations, as the starting shape; (b) **14 daily and 4 weekly**; (c) fewer, where the measured disk cannot hold (a). The counts are confirmed against §3's budget in PC-4 either way.
- **Recommendation.** (a): a week of daily points for mistakes found quickly, and three months for those found at a month's closing, within the budget if the measured terms allow.

### P9Q-11 — The restore drill's cadence

- **Options.** (a) **Monthly for the first three months after go-live, then quarterly**, and after any change to the backup tooling; (b) **quarterly** from the start; (c) twice a year.
- **Recommendation.** (a): the procedure is proved while it is new, then kept alive.

### P9Q-12 — Protecting the backup files

- **Options.** (a) **Restricted permissions on the VPS** — owned by the service account, unreadable to others (SX-07) —, the TOTP secrets and recovery codes already ciphertext under `APP_KEY` (AU-21); (b) (a) plus **encrypting each generation** with a key held off the VPS by the Owner and the operator — protecting a copy that leaves the VPS, which DIR-009 does not foresee, at the cost of a key whose loss makes every backup unreadable.
- **Recommendation.** (a): every backup stays on the VPS, and a separately held key adds a failure that could block a restore.

### P9Q-13 — Who holds backup and restore duty

- **Options.** (a) **The named operator, the Owner as the alternate who can call the operator in**; (b) **two named operators**.
- **Recommendation.** (a) at V1's size; (b) when a second person can take the duty.

## 9. Coverage

The requirements this document answers, from [INFRASTRUCTURE §11](INFRASTRUCTURE.md#11-coverage)'s scripted table: **49 of 143**.

| Source | Requirement | Answered in |
| --- | --- | --- |
| AICWDF §20 | AICWDF §20 — staging | INF §3, INF §5, INF §10, BKR §3 |
| AICWDF §20 | AICWDF §20 — backup | BKR §2 |
| AICWDF §20 | AICWDF §20 — offsite backup | INF §1, BKR §1 |
| AICWDF §20 | AICWDF §20 — restore | BKR §2, BKR §4 |
| AICWDF §20 | AICWDF §20 — restore testing | BKR §5 |
| SECURITY §10 | H9-08 | INF §6, BKR §3 |
| SECURITY §10 | H9-14 | INF §4, INF §7, BKR §6 |
| SECURITY | AU-14 | INF §4, INF §6, INF §7, BKR §6 |
| SECURITY | AU-21 | INF §4, INF §6, INF §7, BKR §6, BKR §8 |
| SECURITY | AU-25 | INF §4, INF §5, INF §7, BKR §4, BKR §6 |
| SECURITY | FL-09 | INF §7, BKR §3 |
| SECURITY | LG-06 | INF §6, INF §7, BKR §3 |
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
| V1_SCOPE | V1_SCOPE — the 200 GB disk budget | BKR §3 |
| V1_SCOPE | V1_SCOPE — the warning and critical thresholds | BKR §3 |
| ACCEPTANCE_CRITERIA | AC-18 | BKR §1, BKR §2, BKR §5 |
| ACCEPTANCE_CRITERIA | AC-19 | BKR §2, BKR §5 |
| GAP_REGISTER | GAP-007 | INF §6, BKR §4 |
| GAP_REGISTER | GAP-011 | BKR §1, BKR §4 |
| GAP_REGISTER | GAP-013 | INF §1, INF §5, INF §6, INF §9, INF §10, BKR §7 |
| GAP_REGISTER | GAP-031 | INF §4, INF §6, BKR §1, BKR §4 |
| GAP_REGISTER | GAP-050 | INF §7, BKR §6 |
| GAP_REGISTER | GAP-051 | INF §7, BKR §6 |
| GAP_REGISTER | GAP-018 — accepted risk, unchanged | INF §1, BKR §1 |
| GAP_REGISTER | GAP-048 — accepted risk, unchanged | BKR §6 |
| PRODUCTION_DATA_SAFETY | PRODUCTION_DATA_SAFETY — the procedures owed | INF §1, INF §3, BKR §1 |
| COST_POLICY | COST_POLICY — no paid service (§4C.2) | INF §1, INF §8, INF §10, BKR §8 |
| CONCURRENCY_IDEMPOTENCY | HO-28 | INF §4, BKR §1, BKR §2, BKR §4 |
| CONCURRENCY_IDEMPOTENCY | §8 — guards after a restore | INF §5, INF §6, BKR §1, BKR §2, BKR §4 |
| PERFORMANCE | PF-28 | INF §8, BKR §3 |
| PERFORMANCE | GT-06 | INF §8, BKR §2, BKR §3 |
| PERFORMANCE | GT-07 | INF §6, BKR §3 |
| PERFORMANCE §13 | SZ-08 | INF §8, BKR §3 |
| DATABASE | DATABASE §22 | INF §2, BKR §1, BKR §2 |
| DATABASE | DATABASE §28 | INF §2, INF §4, BKR §1 |
| REFERENCE_COVERAGE | OWN-06 | BKR §1 |

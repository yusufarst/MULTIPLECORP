# Backup and recovery

Status: APPROVED | Updated: 2026-10-31 | Owner: Planning

Approval: [APPR-013](../00-governance/DECISION_LOG.md#appr-013--p9-infrastructure-observability-backup-and-recovery-approved), explicit Owner approval on 2026-10-19 (DIR-083 OE-16) of this document as committed in run PC-5's approval commit; the approved file hash and the verified conditions are recorded there. Approval changes lifecycle only, not implementation authorization.

Amendment: on 2026-10-31, under [DIR-101](../00-governance/DECISION_LOG.md#dir-101--the-owners-decisions-on-p11q-07-of-04-and-the-labels), the Owner's decisions on P11Q-07 and OF-04 (APPR-027) and Change 15 of [TASK_PLAN §7](../11-tasks/TASK_PLAN.md#7-controlled-task-plan-changes), only step 3 of the restore procedure. Each changed statement is marked `DIR-101`.

Run PC-2 drafted this document on 2026-10-18 under [DIR-078](../00-governance/DECISION_LOG.md#dir-077-dir-078-obs-030-and-tech-037--p7-ux-re-baseline-cp-a-decisions-run-pc-2-with-p9s-drafting-baseline-and-drafts) SP-67 and the Owner's [DIR-077](../00-governance/DECISION_LOG.md#dir-077-dir-078-obs-030-and-tech-037--p7-ux-re-baseline-cp-a-decisions-run-pc-2-with-p9s-drafting-baseline-and-drafts) CA-14: completed in PC-4 with the operator's measurements and the Owner's answers, approved separately at OG-3 (DIR-083 OE-16). Run PC-4 completed it on 2026-10-19 under [DIR-082](../00-governance/DECISION_LOG.md#dir-081-dir-082-obs-032-and-tech-039--og-2-decisions-appr-011-run-pc-4-p8-and-p10-drafted-and-p9-completed) SP-87, with the operator's inputs the Owner gave in session as the operator and the tabletop exercise of BACKUP_RECOVERY §6; an input that was not available is an obligation of [RELEASE_PLAN §9](../10-release/RELEASE_PLAN.md#9-the-go-live-checklist), never a result. Nothing here is installed, scheduled or run; no agent connected to the VPS or any production system to write it.

**OG-3 decisions** (note of 2026-10-19, written by run PC-5 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-90–SP-93). At OG-3 the Owner decided OQ-26 (b) under [DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-09 — the off-server copy, BKR-09, with its key, retention, destination and drill (§§1–3, 5 and 7) —, OQ-29 (a) under OE-11 (§4 step 7) and INFRASTRUCTURE P9Q-14 under OE-03 (§§7–8), and confirmed the six aligned findings of §6 (OE-12). Every obligation an answer adds is a go-live item of RELEASE_PLAN §9 with its evidence, never marked passed (OE-14). P9's approval is APPR-013, in effect only in PC-5's approval commit after gate PC5-A (OE-16).

**Sources.** [V1_SCOPE](../01-product/V1_SCOPE.md#v1-local-backup-and-p9-handoff-contract)'s local-backup contract — BK-01–BK-12, the 200 GB disk budget and its thresholds — and DEP-01 and DEP-02; [ACCEPTANCE_CRITERIA](../01-product/ACCEPTANCE_CRITERIA.md) AC-18 and AC-19; DIR-009 and DIR-010; [GAP_REGISTER](../00-governance/GAP_REGISTER.md) GAP-007, GAP-011, GAP-013, GAP-018, GAP-031, GAP-048, GAP-050 and GAP-051; [SECURITY](../05-security/SECURITY.md) AU-21, AU-25, SX-07 and H9-14; [CONCURRENCY_IDEMPOTENCY](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s19-20-handoff-traceability.md) HO-28 and §8; [DATABASE](../04-architecture/DATABASE/README.md) §§22 and 31; [PERFORMANCE](../06-api-performance/PERFORMANCE.md) GT-06 and SZ-08; [PRODUCTION_DATA_SAFETY](../00-governance/PRODUCTION_DATA_SAFETY.md)'s owed procedures; REFERENCE_COVERAGE OWN-06. The rest of P9 is [INFRASTRUCTURE](INFRASTRUCTURE.md), whose §11 maps every P9 requirement.

Authority: P9 — backup, restore, restore testing, the shared-disk budget and the post-incident recovery exercise. It adds no rule: it designs what V1_SCOPE's contract and SECURITY require, within the Owner's DIR-009.

**Public repository.** No host, address, port, user name, secret path, provider account or key detail appears here — placeholders in angle brackets only, such as `<BACKUP_DIR>`; what the operator supplies is §7's list.

## 1. The decision and its limits

- **Local, with one off-server copy (DIR-009; DIR-083 OE-09).** Every V1 backup is stored on the existing production VPS: local backup, never offsite; no external storage, second server or paid service — except the copy the Owner decided at OG-3: beside the local backups, each daily backup generation is also copied, encrypted, to a destination outside the VPS and outside its provider account (BKR-09). An external destination needs a later explicit Owner change (DIR-010); the Owner's DIR-083 OE-09 is that change, amending DIR-009's local-only policy and V1_SCOPE's local-backup contract for this copy alone — the Owner's own request in session (OQ-26; TT-08) —, and no other external destination is asked for.
- **What local backup can do.** Recover from accidental data changes, application failure, a failed deployment, selected database corruption and accidental deletion, **when an intact suitable backup and a recoverable VPS remain**. It cannot protect against total VPS loss, total disk failure, deletion of the VPS, provider-level loss, an account compromise that destroys production and backups, or a storage failure of the whole VPS: that exposure stays **accepted** under GAP-018 (RISK-001), narrowed by the off-server copy and never removed — a total loss then loses what the copy's interval leaves out —, and is never claimed mitigated.
- **The backup obligation of REFERENCE_COVERAGE OWN-06** — CAP-16 with BK-01–BK-12 — is this document.
- **Targets.** RPO ≤ 24 hours and RTO ≤ 4 hours are operational targets for that recoverable-VPS scenario, measured in drills (§5); a successful local drill does not demonstrate recovery after host or storage loss (AC-18), and the off-server copy's drill (§5) proves that the copy restores, not a recovery time after such a loss.

| BKR | Rule | Grounds |
| --- | --- | --- |
| BKR-01 | A backup is one recovery point: the PostgreSQL dump and the private files it references, taken coherently — the files captured after the dump's snapshot, so every file row the dump holds has its bytes —, under one identifier and timestamp. | BK-01; BK-02; GAP-011; DATABASE §22, §31 |
| BKR-02 | A backup is successful only when it completed, its checksums verify and its manifest lists every part; anything else is reported failed — never a missing or partial backup reported successful. | BK-07; BK-10; V1_SCOPE |
| BKR-03 | A checksum is not restore proof: only a restore drill proves a recovery point (§5). | BK-07; BK-09 |
| BKR-04 | Cleanup deletes only expired generations, after the newer ones verified, never live data and never the last verified recovery points the retention keeps; a failed backup deletes nothing. | BK-05; BK-12 |
| BKR-05 | A restore never runs over live writes and never as the migration/owner role without the guard verification afterwards; the application reopens only after `ReconcileGuards` reports no difference or the operator decides the differences. | BK-08; HO-28; GAP-031; DATABASE §28 |
| BKR-06 | Backups are operated by the named human operator only; no agent ever holds a backup file or access to one. | INFRASTRUCTURE INF-01, INF-02; PRODUCTION_DATA_SAFETY |
| BKR-07 | **One application's backups are its own.** MULTIPLECORP's dumps, files and manifests live in its own directory, readable only by the operator account and never by the web runtime's (SX-07), and never mixed with the second application's; a backup, restore drill or restore of MULTIPLECORP reads and writes only MULTIPLECORP's database, files and backup directory, and its schedule never overlaps the other application's backup or maintenance window (OPI-27). | INFRASTRUCTURE INF-07; SX-07; DIR-079 CB-13; the Owner's conditions in session |
| BKR-08 | **Each generation is paired with its key.** A generation's manifest names the key-register entry of the `APP_KEY` valid at its time; that key — in the operator's secret store, never with the backup — is kept while the generation is retained, and the restore of §4 installs it before the application reads any TOTP data, so that a restore after a rotation brings back readable secrets — except after a key compromise: a paired key that is a leaked key is never installed or listed, and the restored state takes AU-25's new keys (INFRASTRUCTURE §7.3 step 5). | SECURITY AU-21, H9-14; BK-08 |
| BKR-09 | **The off-server copy** (DIR-083 OE-09). Beside the local backups, each daily generation, once verified (BKR-02), is also copied, encrypted, to a destination outside the VPS and outside its provider account — a device or storage the Owner or the client already holds, at no recurring cost, which the operator names before go-live by role and never by its access details (OPI-32) —; its encryption key and the generation's paired key of BKR-08 are kept apart from the VPS and never with the copy, so the copy can be restored without the VPS; its retention follows DIR-079 CB-11 — 7 daily, 4 weekly and 3 monthly — as far as the destination's space allows; a copy counts only when its checksums verify at the destination, and a failed or missing copy alerts the operator (BKR-02; BK-10); it is restored once in a drill before go-live (§5; RELEASE_PLAN GLC-20). A paid destination is never adopted without the Owner's COST_POLICY §4C.2 decision, and a copy made after an incident's window began proves nothing by existing (TT-F21). | DIR-083 OE-09; DIR-079 CB-11; BK-07; BK-10; COST_POLICY |

## 2. The twelve obligations

| BK | Design | Evidence (AC-18, AC-19) |
| --- | --- | --- |
| BK-01 | A logical PostgreSQL dump in the custom format, consistent at one snapshot, its recovery point named by timestamp and the deployed commit (BKR-01) | AC-18: the restored database and its recovery point |
| BK-02 | The private file disk (DATABASE §22) — evidence, renditions, identity assets, product images — captured with each dump under the same identifier; renditions and thumbnails are reproducible and exports short-lived, so exports and temporary files are left out (GT-06) | AC-18: file reconciliation against `file_objects` |
| BK-03 | Before every deployment a backup runs and its verification completes; a failed or unverified backup stops the deployment (INFRASTRUCTURE §5) | AC-19: the pre-deployment run and its stop on failure |
| BK-04 | A daily scheduled backup at `<BACKUP_TIME>`, outside business hours and clear of `ReconcileGuards`' nightly snapshot (HO-28), meets the 24-hour RPO; a missed or stale run — no verified backup within 26 hours, the 24-hour RPO with a two-hour margin for the run, revised with OPI-25 — alerts (INFRASTRUCTURE §6) | AC-19: the schedule, a missed-run alert |
| BK-05 | Generations rotate as separately identifiable directories `<BACKUP_DIR>/<timestamp>-<commit>/`, never one file overwritten | AC-19: rotation across generations |
| BK-06 | Retention keeps several recovery points — daily, weekly and monthly generations, their counts P9Q-10's —, sized from measured capacity (§3), never guessed from the nominal disk; the pre-deployment generation of BK-03 and the generation taken before the opening import commits (RELEASE_PLAN CUT-05) are kept until the next release, or until go-live, and counted in B | AC-19: the retention boundary |
| BK-07 | A manifest with each part's SHA-256 and size, verified after writing and before cleanup; a truthful status per run (BKR-02, BKR-03) | AC-18: checksums; AC-19: corrupt or incomplete runs reported failed |
| BK-08 | The restore procedure of §4, isolated from live writes, with critical-record and file reconciliation and safe job resumption, followable by the operator alone | AC-18: the operator follows it without overwriting live data |
| BK-09 | Restore drills at the cadence of P9Q-11, each recording the recovery point, the elapsed time and whether business operation was usable (§5) | AC-18: periodic retests with cadence and results |
| BK-10 | Each run logs its outcome; a failure, a stale backup or a failed cleanup alerts the named operator through INFRASTRUCTURE §6's channel (P9Q-03) | AC-19: failures logged and actionable |
| BK-11 | Disk monitoring with a warning and a critical threshold and reserved headroom (§3); crossing one alerts and starts the capacity response | AC-19: disk-pressure assertions |
| BK-12 | Cleanup strictly by retention after the newer generations verified (BKR-04); a failed backup or cleanup never deletes required generations or live data | AC-19: cleanup-failure assertions |

The off-server copy of BKR-09 follows BK-04's daily schedule, BK-07's checksums at its destination, BK-10's alerting and BK-12's cleanup by its own retention, so that a failed copy is reported failed and never deletes a generation the retention keeps (DIR-083 OE-09).

## 3. The shared-disk budget

The 200 GB nominal disk of DEP-01 holds production and its backups — and the client's second production application with its own (INFRASTRUCTURE INF-07); nominal capacity is not free space. P9 budgets it from **measured** figures (OPI-21; OPI-27), never from the nominal size. In PC-4 none was available (§7): U₀, K and the free space are measured before staging; DB, F, L and D are projected from GT-04–GT-06 and the data steward's counts before staging and measured after the opening import, before go-live; every number below is set once measured, the check being RELEASE_PLAN's pre-go-live item GLC-05, and a retention the budget cannot hold comes back to the Owner before go-live (DIR-079 CB-11; `DIR-081` OB-10).

| Term | What it holds | Input |
| --- | --- | --- |
| U₀ | the operating system and everything else on the VPS outside K | measured (OPI-21) |
| K | the client's second production application — its data, files, logs, containers and backups, with their growth (INFRASTRUCTURE INF-07) | measured (OPI-27) |
| DB | PostgreSQL's data with its indexes and WAL | measured, grown by PERFORMANCE's GT-04–GT-05 (SZ-08) |
| F | uploaded evidence, identity assets, product images and renditions, and the opening import's sources (RELEASE_PLAN IMP-12) | measured, grown by GT-06 |
| L | logs and audit files within LG-06's retention (H9-08) | measured |
| X | exports and temporary files, short-lived (FL-09; PF-28's row bound as a guard) | bounded by the export limits |
| D | container images and volumes, if containers are used | measured |
| B | the retained backup generations: Σ (dump + files) over P9Q-10's counts, with the pre-deployment and pre-import generations BK-06 keeps | computed |
| S | staging's transient stack during a release (INFRASTRUCTURE P9Q-01) | measured on the first rehearsal |
| P | the peak during a backup — one generation being written beside the retained ones — and during a restore drill — a restored copy beside production —, counted together, since staging, a backup and a drill can meet in a release (RELEASE_PLAN REL-03, REL-04), with the off-server copy's transient file while it is written, if its tooling needs one (BKR-09) | computed |
| Kₚ | the second application's own transient peak during its backup or maintenance window, which never overlaps MULTIPLECORP's (INFRASTRUCTURE INF-07) | measured (OPI-27) |
| H | reserved free headroom | set by P9 |

- **The budget holds** when U₀ + K + DB + F + L + X + D + B + max(S + P, Kₚ) + H stays within the measured capacity, with the growth of one year projected; the projection is redone monthly with GT-07's report.
- **Thresholds.** A warning at `<DISK_WARN_PCT>` and a critical level at `<DISK_CRIT_PCT>` of used space, each derived from the terms above so that a backup and a restore drill still fit at the warning level (BK-11).
- **Disk pressure.** At the warning level the operator is alerted and the capacity response starts: shorten the transient terms first — exports, temporary files, staging —, then review retention with the Owner. At the critical level no new backup generation starts unless the space for it and the retained verified generations is there; the skipped run is logged and alerted, never reported successful; nothing required is deleted to make room (BKR-02, BKR-04; V1_SCOPE); MULTIPLECORP's cleanup never deletes a file of the second application, whose own response is its operator's (BKR-07).

## 4. The restore procedure

Run by the operator alone, from the recovery point chosen with the Owner; never over live writes (BK-08; BKR-05; GAP-011).

1. **Stop writes.** MULTIPLECORP in maintenance mode; its queue workers and scheduler stopped; the second application keeps running, untouched (BKR-07).
2. **Choose and verify** the recovery point: its manifest's checksums verify (BK-07); a corrupt or missing generation sends the procedure to the next older verified one.
3. **Restore into isolation.** The deployment's go-live file (INFRASTRUCTURE §4.2) stays where it is and is never restored over or rewritten, so a restore does not change what go-live was (NM-03; `DIR-101`). The dump into a new database of MULTIPLECORP's own PostgreSQL instance and the files into a new directory of its own storage, never over the live ones and never into the second application's, the generation's paired key installed in the restored environment — never a leaked key: the restored state of a key compromise takes AU-25's new keys (BKR-08; INFRASTRUCTURE §7.3 step 5) —; the operator keeps the live state until the restore is accepted.
4. **Reconcile.** Every `file_objects` row has its bytes and every byte file its row; issued documents with their renditions — a missing rendition is rendered again from the issued snapshot, never re-issued or renumbered (GAP-007); the critical records the operator and the Owner check — the latest payments, receipts and issued numbers — against the business's own papers.
5. **Verify the guards.** `ReconcileGuards` before reopening; a difference is reported to the operator and the Owner and never repaired silently (HO-28; GAP-031; CONCURRENCY_IDEMPOTENCY §8).
6. **Resume safely.** Owed work restarts through its durable rows and sweeps (JB-02; JB-09), not from Valkey; the scheduler and the workers restart (GAP-011).
7. **Raise, reopen and record** (DIR-083 OE-11). Before any numbered type issues again, the Owner records, per company, type and period, the highest number on the papers issued after the recovery point, and the numbering command raises each affected counter — or creates the live period's — to the number after it, once in this recovery and never lowering one, audited with the incident (CONCURRENCY_IDEMPOTENCY NM-03; NX-01); MULTIPLECORP's users resume only after it. The recovery point, the elapsed time and what was lost after it are recorded, and the lost facts are recorded again through the ordinary commands under new numbers, the papers' numbers kept beside them in the private incident record outside the application (RELEASE_PLAN RBK-02).

A restore after a key compromise is not this procedure alone: it is AU-25's Scenario B, rehearsed in §6.

## 5. Restore testing

- **Drills** at the cadence of P9Q-11, on the VPS outside both applications' business hours, planned around the second application's windows and load (OPI-27) and never touching it (BKR-07), with the transient space of §3's P term: the restore of §4 into isolation, the elapsed time measured against the 4-hour RTO and the recovery point's age against the 24-hour RPO, the reconciliation done, the result recorded — usable or not (BK-09; AC-18).
- **Cases** (AC-18; AC-19): an intact backup; a corrupt generation; a missing generation; an incomplete run; a disk-pressure run, simulated by a lowered threshold, never by filling the shared disk (INFRASTRUCTURE INF-07); a failed cleanup — each with its expected truthful status. The first drill before go-live restores a production recovery point — the generation taken after the opening import — with its paired key, and times it: AC-18's evidence; staging's synthetic restore is its rehearsal (RELEASE_PLAN §7). **H9-14's recovery test** is a Scenario B recovery rehearsal, owed before go-live (RELEASE_PLAN GLC-26): on staging, with synthetic data and synthetic keys, the operator restores a verified generation onto a clean stack, installs new keys with the leaked key never listed, rotates every SX-01 secret the scenario's leak may have included, runs the incident command in full on the restored state and then Scenario A's steps (2)–(4) (INFRASTRUCTURE §7.3 step 5; SECURITY AU-25; P5's R5-03).
- **The off-server copy's drill** (BKR-09; DIR-083 OE-09). Once before go-live a copied generation is brought back from its destination with its keys and restored into isolation by §4's procedure, unchanged, never touching the second application (BKR-07): the copy restored and reconciled, its recovery point and elapsed time recorded (RELEASE_PLAN GLC-20). It proves the copy, not a recovery time after a total loss.
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

*Note of Part 5:* INFRASTRUCTURE §7.2 step 2 now carries AU-25's clause for a leak of several keys — the earliest of their times —, added after the exercise; TT-02 above is the script as walked.

*Note of PC-5:* TT-10's expected answer is the script as walked under AU-25 before the Owner's DIR-083 OE-10; under OE-10 a halt on a trusted state goes first to the Owner's incident decision outside the application, with the client, which may choose Scenario B's restore or keep the trusted state (INFRASTRUCTURE §7.3 step 6).

### The exercise held in PC-4

Held on 2026-10-19 in session with the Owner, who is also the operator, Planning the scribe (SP-87; `DIR-081` OB-10) — a discussion only: no system was touched, nothing was run, and it is no recovery test. TT-05–TT-07 and TT-09 were walked on stated variations — a dated leak, two fictional pre-window Owners, a verified off-server backup — so that each step could be answered. Every answer is recorded as given, by role; the names of the provider and of the second application are kept out of the repository. The runbooks it rehearsed are [INFRASTRUCTURE §7.1–§7.3](INFRASTRUCTURE.md#7-security-operations), which now carry its findings. **Result:** GAP-051's exercise is held, within the scope of a tabletop discussion; GAP-048's accepted risk was walked (TT-05; TT-07) and stays unchanged; five questions go to the Owner at OG-3 — OQ-26 and OQ-28, which would change P0–P7, and INFRASTRUCTURE's P9Q-14, P9Q-15 and P9Q-16, the last the question named OQ-27 in session. The Owner decided all five at OG-3 under DIR-083 — OQ-26 (b) (OE-09), OQ-28 (b) (OE-10) and P9Q-14–P9Q-16 (a) (OE-03–OE-05) —, which answers TT-F15, TT-F17, TT-F19, TT-F20, TT-F26 and TT-F29; each obligation they add is a go-live item of RELEASE_PLAN §9, never marked passed (OE-14).

| TT | The Owner's answer, by role | Against the expected |
| --- | --- | --- |
| TT-01 | The Owner, as the operator, receives the alarm and leads. The planned channel — a personal Telegram bot — is not yet set up, so the alarm is handled and recorded manually; the client is told once the incident is confirmed or may affect the service or its data. Written first: the detection time (WIB), the type, the source and the environments possibly affected, the status — suspected —, the evidence masked with no key value | met through the manual floor; the channel owed (TT-F01) |
| TT-02 | The start at the earliest the evidence allows — with an earlier leak not excluded, the key's creation time in the register —, recorded once in the private record and as the command's input; every later step refuses another value; an unregistered or ambiguous creation time never guessed, the first halt | met |
| TT-03 | Write access judged from host access, network, firewall, credentials, logs and access through the other application, a private-interface binding proving nothing: **Scenario B**, since the store credentials leaked too. The host untrusted and taken out of service; the client told that the second application stops too and involved without delaying containment; recovery only from a verified backup older than the window's start, on a trusted environment; both applications kept isolated, the second never presumed safe | met |
| TT-04 | The maintenance mode on every process, confirmed by a refused forbidden request; the opening sweep; `APP_KEY`, the HMAC keys, the database and Valkey credentials and every affected secret rotated; no old key kept unless ciphertext needs it; the closing sweep; the mode lifted only after verification, kept on any failure; MULTIPLECORP's environment only | corrected — the rotation placed after the opening sweep, against AU-21's keys first (TT-F09) |
| TT-05 | On a stated variation — a dated leak, two fictional pre-window Owners —: Owner-2 verified directly through a trusted contact known before the compromise; the restored Owners shown with the account and the actor and time of the removal; the refusals expected — another window start, an account that is not a pre-window Owner, a second restoration (M-46), a missing verification —; the restoration temporary, the removal decided at TT-07 | met, except that a missing verification is no refusal the command can make (TT-F13) |
| TT-06 | The order restoration → recovery → review; Owner-2 through path (e) with a private hand-over; Owner-1 — the Owner and operator — through the official command outside the application, no token recorded and no independent verification claimed. AU-14, read for the Owner's condition, neither forbids an operator's recovery of its own Owner account nor requires a separate verifier | met (TT-F15) |
| TT-07 | The window's changes reviewed; a removal confirmed as legitimate made again through the ordinary command, ending the temporary restoration; in a disagreement neither Owner presumed right and no new authority invented — unresolved for OG-3, the protections kept —; unconfirmed changes reversed; the review not complete while a security decision is open | met; no deciding rule (TT-F17) |
| TT-08 | No backup independently verified as older than the compromise, the local backups untrusted: the in-application path stops, and the Owner decides outside the application with the client — isolation, evidence, verifiable sources, a clean rebuild considered, every risk recorded and approved —, the second application separately. On OQ-26 the Owner supports an isolated, encrypted, restore-tested off-server backup as a pre-go-live obligation, its details at OG-3 | met |
| TT-09 | On a stated variation — a verified off-server backup of day X−1 —: used only after its integrity, date and source are verified; before the first request the maintenance mode, the new keys and the rotation, the incident command in full and the verification; pre-window status read at the backup's time and the restored Owners shown with it; isolation from the second application | met |
| TT-10 | Both halts where AU-25 sends them; no Owner created or guessed; no OWNER authority to the operator, the client or anyone not authorized; OQ-27 recorded with the Owner's recommendation | met |
| TT-11 | (a) a corrected start as a new run — agreed; (b) the Owner's decision before any restore — option (ii), recorded as OQ-28; (c) the end recorded atomically — agreed | each answered |
| TT-12 | The end of recovery only when its conditions hold — the OWNER accounts verified, the review complete, no decision open, AU-14's and AU-25's actions recorded, the end atomic —; the findings' owners approved, which neither approves the OG-3 questions nor marks any pre-go-live obligation passed | met |

*Notes of Part 5 — Planning's, not the Owner's answers:* at TT-10, the question the Owner named OQ-27 is recorded as INFRASTRUCTURE P9Q-16, a P9 matter (AU-25; H9-14), and OQ-27 is not used; at TT-11 (b), the two orders offered were (i) a restore first, then the Owner's decision, and (ii) the Owner's decision first, which may choose a restore (OQ-28).

**The findings and their owners**, by the division the Owner approved at TT-12 — the Owner; the Owner with the client; the operator; Planning; the client; P8 and P11 —, an assignment that neither approves an OG-3 question nor marks a pre-go-live obligation passed. Six rows — TT-F02, TT-F06, TT-F14, TT-F16, TT-F18 and TT-F25 — were aligned in Part 5 to that division, which the table drafted in Part 4 had applied differently (PC4F-49); the Owner confirms them with P9 at OG-3. **Confirmed at OG-3** ([DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-12): the Owner confirmed the six rows TT-F02, TT-F06, TT-F14, TT-F16, TT-F18 and TT-F25 as this table assigns them, aligned to the division approved at TT-12 — a confirmation that answers no question and marks no obligation passed:

| Finding | What it records | Owner | Where it lands |
| --- | --- | --- | --- |
| TT-F01 | The alert channel not yet configured or tested; the alarm manual until it is | the operator | RELEASE_PLAN GLC-03; OPI-05 |
| TT-F02 | The client told once an incident is confirmed or may affect the service or its data | Planning; the Owner with the client | INFRASTRUCTURE §7.2 step 1 |
| TT-F03 | The incident record private, outside the repository, its evidence masked | the operator | §7.2 step 1 |
| TT-F04 | The dated key register started when the installation's key is created | the operator | §7.2 step 2; GLC-16 |
| TT-F05 | An undatable leak of the installation's key always halts the in-application path | Planning | §7.3 step 6; P9Q-16 |
| TT-F06 | Scenario B stops both applications; the runbooks and the recovery plans cover both | Planning; the Owner with the client | §7.2 step 3; RELEASE_PLAN RBK-06 |
| TT-F07 | The inventory's scope; a private-interface binding proves nothing | the operator | §7.2 step 3 |
| TT-F08 | The second application never presumed safe; its recovery its own | the client | §7.3 step 5 |
| TT-F09 | Every key and affected secret rotated before the incident command | Planning | §7.2 step 4 |
| TT-F10 | The maintenance mode confirmed on every process by a refused request | the operator | §7.2 step 5 |
| TT-F11 | The incident actions on MULTIPLECORP only | the client | §7.2 step 8 |
| TT-F12 | A private contact record of the Owners, made before go-live, as the trusted channel | the operator | §7.1 step 2; GLC-16 |
| TT-F13 | The in-person verification recorded as a precondition before the command | Planning; the operator | §7.1 step 2 |
| TT-F14 | The restoration temporary; the removal's legitimacy decided by the review | Planning | §7.3 step 2 |
| TT-F15 | No second, independent person while the Owner is the operator | the Owner | INFRASTRUCTURE P9Q-14 |
| TT-F16 | The client confirms operationally but is not by that an operator or a link recipient | the Owner with the client | §7.3 step 6 |
| TT-F17 | No deciding rule for a disagreement between recovered pre-window Owners | the Owner | INFRASTRUCTURE P9Q-15 |
| TT-F18 | The review and the end of recovery wait for every open security decision | Planning | §7.3 step 8 |
| TT-F19 | No backup can serve Scenario B today | the Owner | OQ-26 |
| TT-F20 | The Owner's direction: an isolated, encrypted, restore-tested off-server backup before go-live | the Owner, at OG-3 | OQ-26; RELEASE_PLAN GLC-20 |
| TT-F21 | An off-server copy serves only when verified to predate the compromise | Planning; the operator | §7.3 step 5 |
| TT-F22 | The decision outside the application and what it covers | the Owner with the client | §7.3 step 6 |
| TT-F23 | The restored state's opening checklist | Planning; the operator | §7.3 step 5 |
| TT-F24 | An off-server backup verified independently before use | the operator | §7.3 step 5 |
| TT-F25 | The halts where AU-25 sends them; no Owner created or guessed | Planning | §7.3 step 6 |
| TT-F26 | One `APP_KEY` rotation after the first Owner, before go-live | the Owner, at OG-3 | INFRASTRUCTURE P9Q-16; RELEASE_PLAN GLC-23 |
| TT-F27 | With one Owner, halt (2) waits on that person; a second Owner account is the business's decision | the Owner with the client | — |
| TT-F28 | A corrected window start as a new run; the start's history kept | Planning; the operator | §7.3 step 7 |
| TT-F29 | The authorized decision before any restore on a trusted state | the Owner, at OG-3 | OQ-28; RELEASE_PLAN GLC-23 |
| TT-F30 | The end of recovery atomic under the OWNER role row; the race tested in P8 | P8 and P11 | §7.3 step 8; SECURITY_TEST_MATRIX SM-21 |

## 7. The operator's inputs

The Owner answered in session on 2026-10-19 as the operator; each answer is recorded as given, and each input not available is an obligation of [RELEASE_PLAN §9](../10-release/RELEASE_PLAN.md#9-the-go-live-checklist) before the step the last column names, never a result (INFRASTRUCTURE §9).

| OPI | Input | Where it goes | Recorded in PC-4 | Needed before |
| --- | --- | --- | --- | --- |
| OPI-21 | The measured disk terms U₀, DB, F, L and D of §3, and the VPS's free space (DEP-01) | recorded as figures | **Not available** — the nominal 200 GB is shared by two production applications; DIR-079 CB-11's rule: the disk-budget check moves to RELEASE_PLAN GLC-05, and a retention of 7 daily, 4 weekly and 3 monthly generations the budget cannot hold comes back to the Owner before go-live (`DIR-081` OB-10) | staging for U₀ and the free space; go-live for the measured DB, F, L and D (GLC-05) |
| OPI-22 | The local backup directory, `<BACKUP_DIR>`, on a volume the operator names, and its permissions (DEP-02; SX-07) | recorded as a role, the path kept | **Not determined**; the Owner, as the operator, is responsible; the directory is MULTIPLECORP's own, apart from the second application's (BKR-07); the off-server copy is BKR-09's (DIR-083 OE-09), its destination OPI-32 | staging |
| OPI-23 | The backup time `<BACKUP_TIME>`, outside business hours | recorded | **Not determined.** Recommended: **03:00 WIB** daily — after `ReconcileGuards`' 02:00 WIB run (HO-28), outside business hours —, to be checked against the second application's own backup and maintenance windows once OPI-27 is recorded and against the measured duration (OPI-25), and approved by the Owner before go-live | go-live |
| OPI-24 | The person who holds backup and restore duty and an alternate (GAP-013) | recorded by role | **The Owner**; an alternate not yet named — INFRASTRUCTURE P9Q-14, decided by DIR-083 OE-03: named before go-live, preferably designated by the client | go-live, for the alternate |
| OPI-25 | The measured duration of a full backup and of a restore drill on the VPS | recorded as figures, against the RPO and RTO | **Not measured** | go-live |
| OPI-32 | The off-server copy's destination — a device or storage the Owner or the client already holds — and the custody of the keys kept apart from the VPS (BKR-09) | recorded by role, never by access details | **Not named** — added at OG-3 by DIR-083 OE-09, after PC-4's session | go-live (RELEASE_PLAN GLC-20) |

## 8. Owner questions

Decided under DIR-079 CB-11–CB-14, the rule that waits on an operator input applied when that input is recorded (`DIR-081` OB-10); a paid option is recorded under COST_POLICY §4C.2 and never adopted.

### P9Q-10 — How far back the recovery points reach

- **Options.** (a) **7 daily, 4 weekly and 3 monthly** generations, as the starting shape; (b) **14 daily and 4 weekly**; (c) fewer, where the measured disk cannot hold (a). The counts are confirmed against §3's budget once OPI-21 is recorded, either way.
- **Recommendation.** (a): a week of daily points for mistakes found quickly, and three months for those found at a month's closing, within the budget if the measured terms allow.
- **Decided — [DIR-079](../00-governance/DECISION_LOG.md#dir-079-dir-080-obs-031-and-tech-038--p7-ux-re-baseline-c2-evidence-and-owner-decisions-run-pc-3-stage-3-completed-stage-4-and-gate-p7) CB-11.** Option (a): 7 daily, 4 weekly and 3 monthly backups, confirmed against §3's disk budget once OPI-21 is recorded — the check is RELEASE_PLAN GLC-05 —, and a retention the budget cannot hold comes back to the Owner before go-live (`DIR-081` OB-10). **The operator input its rule waits on:** OPI-21, the measured disk terms of §3 (§7); the rule is applied when the input is recorded (`DIR-081` OB-10), no further decision of the Owner being needed unless the rule cannot be met.

### P9Q-11 — The restore drill's cadence

- **Options.** (a) **Monthly for the first three months after go-live, then quarterly**, and after any change to the backup tooling; (b) **quarterly** from the start; (c) twice a year.
- **Recommendation.** (a): the procedure is proved while it is new, then kept alive.
- **Decided — [DIR-079](../00-governance/DECISION_LOG.md#dir-079-dir-080-obs-031-and-tech-038--p7-ux-re-baseline-c2-evidence-and-owner-decisions-run-pc-3-stage-3-completed-stage-4-and-gate-p7) CB-12.** Option (a): a restore drill monthly for the first three months, then quarterly.

### P9Q-12 — Protecting the backup files

- **Options.** (a) **Restricted permissions on the VPS** — readable only by the operator account, never by the web runtime's (SX-07) —, the TOTP secrets and recovery codes already ciphertext under `APP_KEY` (AU-21); (b) (a) plus **encrypting each generation** with a key held off the VPS by the Owner and the operator — protecting a copy that leaves the VPS, which DIR-009 does not foresee, at the cost of a key whose loss makes every backup unreadable.
- **Recommendation.** (a): every backup stays on the VPS, and a separately held key adds a failure that could block a restore.
- **Decided — [DIR-079](../00-governance/DECISION_LOG.md#dir-079-dir-080-obs-031-and-tech-038--p7-ux-re-baseline-c2-evidence-and-owner-decisions-run-pc-3-stage-3-completed-stage-4-and-gate-p7) CB-13.** Option (a): restricted permissions. *Note of PC-5:* the local generations keep CB-13's restricted permissions; the off-server copy of BKR-09 is encrypted, as the Owner's DIR-083 OE-09 decides.

### P9Q-13 — Who holds backup and restore duty

- **Options.** (a) **The named operator, the Owner as the alternate who can call the operator in**; (b) **two named operators**.
- **Recommendation.** (a) at V1's size; (b) when a second person can take the duty.
- **Decided — [DIR-079](../00-governance/DECISION_LOG.md#dir-079-dir-080-obs-031-and-tech-038--p7-ux-re-baseline-c2-evidence-and-owner-decisions-run-pc-3-stage-3-completed-stage-4-and-gate-p7) CB-14.** Option (a): a named operator with the Owner as the alternate, recorded by role (OPI-01). In session the Owner is the operator (OPI-01; OPI-24), so the Owner cannot also be the alternate: INFRASTRUCTURE P9Q-14 asks who is — decided at OG-3 (DIR-083 OE-03): an alternate named before go-live, preferably designated by the client (RELEASE_PLAN GLC-21).

## 9. Coverage

The requirements this document answers, from [INFRASTRUCTURE §11](INFRASTRUCTURE.md#11-coverage)'s scripted table: **75 of 194**.

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
| GAP_REGISTER | GAP-018 — accepted risk, narrowed by the off-server copy (DIR-083 OE-09) | INF §1, BKR §1 |
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
| DATABASE §30 | CAP-16 | BKR §1, BKR §2 |
| PROJECT_CHARTER | PROJECT_CHARTER — P9, infrastructure, observability, backup and recovery | INF §1, BKR §1 |
| ACCEPTANCE_CRITERIA | ACCEPTANCE_CRITERIA — recovery measurements and workloads operationalized with P8 | BKR §5, INF §8 |
| RELEASE_PLAN | RELEASE_PLAN §7 — the tabletop exercise is P9's | BKR §6 |
| RELEASE_PLAN | RELEASE_PLAN §9 — the operator's inputs OPI-01–OPI-29 as go-live items | INF §9, BKR §7 |
| RELEASE_PLAN | PRQ-10 — the drill procedures beside P9's backup tooling | BKR §5 |
| RELEASE_PLAN | PRQ-15 — the recovery runbooks of AU-25 with P9 | INF §7, BKR §6 |
| Owner in session | Condition 2 — capacity from both applications' combined use | INF §8, BKR §3 |
| Owner in session | Condition 3 — a backup, restore or rollback of one application never damages the other | INF §5, BKR §1, BKR §4 |
| Owner in session | Condition 5 — inputs not available as pre-staging or pre-go-live obligations, never PASS | INF §9, BKR §7 |
| Owner in session | OPI-23 — a backup time outside business hours recommended, approved before go-live | BKR §7 |
| Owner in session | OPI-22 — an off-server backup to be considered (OQ-26, decided by DIR-083 OE-09) | INF §1, BKR §1 |
| Owner in session | OPI-01 and OPI-24 — no alternate yet (P9Q-14, decided by DIR-083 OE-03) | INF §10, BKR §8 |
| INFRASTRUCTURE §9 | OPI-27 | INF §8, INF §9, BKR §3 |
| BACKUP_RECOVERY §6 | The tabletop exercise TT-01–TT-12, held in PC-4 (GAP-051) | BKR §6 |
| Owner in session | P9Q-15 — a disagreement between recovered pre-window Owners | INF §10, BKR §6 |
| Owner in session | P9Q-16 — one APP_KEY rotation after the first Owner, before go-live (named OQ-27 in session) | INF §10, BKR §6 |
| Owner in session | OQ-28 — a decision of the Owner before any restore on a trusted state (decided by DIR-083 OE-10) | INF §7, BKR §6 |
| SECURITY | SX-07 | BKR §1 |
| BACKUP_RECOVERY §7 | OPI-32 | BKR §7 |
| V1_SCOPE | V1_SCOPE — the off-server copy P9 defines: its encryption and key, its retention, its destination by role and its drill (`DIR-083`) | INF §1, BKR §1, BKR §5, BKR §7 |
| OG-3 (DIR-083) | OE-03 — an alternate operator named before go-live, receiving the alerts, GAP-041's review triggered | INF §6, INF §7, INF §9, INF §10, BKR §7, BKR §8 |
| OG-3 (DIR-083) | OE-09 — the off-server copy with its keys, retention, destination and drill | INF §1, INF §4, INF §8, BKR §1, BKR §2, BKR §5, BKR §7 |
| OG-3 (DIR-083) | OE-10 — on a trusted state a recovery halt goes first to the Owner's incident decision | INF §7, BKR §6 |
| OG-3 (DIR-083) | OE-11 — the restore's step 7: the Owner's raise before any numbered issue | BKR §4 |
| OG-3 (DIR-083) | OE-12 — the six aligned findings' owners confirmed | BKR §6 |

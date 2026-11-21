# Cutover, the import rehearsal and go-live

Status: REVIEW | Updated: 2026-11-21 | Owner: Planning

Written by [TASK-077](../../11-tasks/TASK-077.md): the runbook that orders [RELEASE_PLAN](../../10-release/RELEASE_PLAN.md) §6's CUT-01–CUT-08 with the import's operational rules (IMP-01–IMP-13), the rehearsal that sizes the freeze, and the rollback decisions (RBK-01–RBK-06). Each step's rule is RELEASE_PLAN's. This page says when it runs, who runs it and which tool it uses.

No step here holds real legacy data, a secret or a host detail (IMP-12; RL-05). The legacy files reach only the application's private storage as import sources, uploaded by the Admin. Running the cutover is TASK-087's, by the human operator and the Owner, never by an agent.

## 1. The tools

| Tool | Who | What it does |
| --- | --- | --- |
| Opening import pages (PG-057, PG-058; TASK-057) | the Admin, the Owner | Prepare, dry run, sign-off and commit, per batch. |
| `php artisan import:rehearsal-batches <plan.json> --into=<new dir>` | the operator, on staging | Writes synthetic batches from counts alone (IMP-13). |
| `php artisan ops:post-import-refresh` | the operator | Refreshes the planner statistics and reports each registered hot query's plan on the imported data. It runs `EXPLAIN` only and prints no data value (IMP-10; HO-36). It runs under the owner's login given to the run, as the backup tooling does (`BACKUP_DB_USERNAME`, `BACKUP_DB_PASSWORD`). |
| `php artisan numbering:go-live <Y-m-d> --operator="<name>"` | the operator | Records go-live once (CUT-07; HO-32). |
| `php artisan backup:run --kind=preimport`, `backup:status` | the operator | The verified backup before the commit (CUT-05; [BACKUP_RESTORE](BACKUP_RESTORE.md)). |
| `php artisan guards:reconcile` | the operator | `ReconcileGuards` after the commit (IMP-11). |

The operator runs every command in a one-off container of MULTIPLECORP's image, as the image's own user ([BACKUP_RESTORE §1](BACKUP_RESTORE.md#1-the-tooling-and-its-configuration)). Each command refuses to run outside a console, and no route reaches any of them.

### How go-live is recorded (INFRASTRUCTURE §4.2, DIR-101)

**The store.** Go-live is one file on the `storage` volume, `storage/app/private/numbering-go-live` (`NUMBERING_GO_LIVE_FILE`). It holds the day as `Y-m-d` in WIB and is created with exclusive creation. Every web and worker process reads it, and no application write path reaches it. A restart or a database restore leaves it as it is.

**The command** creates the file, then commits the audit event `ORG.NumberingGoLiveRecorded`. The event carries the operator's identity and no application actor. A second run, and every case below, writes nothing unless stated:

| What the command finds | What it does |
| --- | --- |
| The file and no event | Writes the event from the file and changes nothing else. This repairs a commit that failed. |
| Both the file and the event | Refuses. |
| A batch neither COMMITTED nor ABORTED | Refuses. |

Until the file exists, the numbering command neither starts nor seeds (NM-03).

## 2. The import rehearsal (IMP-13), on staging, before CUT-01

1. **The plan of counts.** The data steward reports counts only, per company and kind. The operator writes them as a plan, never with a real row (RL-05; DEP-05): `{"companies": ["<code>"], "cutoff": "<Y-m-d>", "rows": {"LOCATION": n, "PRODUCT": n, "CLIENT": n, "SUPPLIER": n, "OPENING_STOCK": n, "OPEN_PROJECT": n, "OPEN_PURCHASE": n, "OPENING_RECEIVABLE": n}, "seeds": {"PROJECT": n, "PURCHASE": n}}`. The company codes are staging's synthetic companies.
2. **The batches.** Run `import:rehearsal-batches <plan> --into=<dir>`. It writes one file per kind, in IMP-03's order, each split at SourceTable's 30,000 rows. Every value is invented, and each file carries its companies' PROJECT and PURCHASE seed rows (IMP-07). Opening credits and archive items cite uploaded evidence and archive files, so they are rehearsed by hand with a few synthetic files.
3. **The rehearsal.** On staging's synthetic stack, set up the companies and their numbering schemes as production's will be (IMP-01; IMP-07). Then run each file through prepare, dry run, the two sign-offs and the commit, in order. For each commit, record its rows and its duration: the batch's commit request to its last committed row.
4. **The chunk size.** The rate is a batch's committed rows divided by its commit duration. Size each chunk so that it commits within half of its job's timeout (JB-07; IMP-06):

   chunk ≤ rate × timeout ÷ 2

   Recheck the size whenever the measured rate changes.
5. **The freeze's length** (CUT-01; DIR-083 OE-06) is the sum of four parts, plus a margin agreed with the Owner:

   freeze = final batches' dry runs and sign-offs + Σ commit durations at production's counts + post-import backup's measured duration (OPI-25) + production restore drill's duration (OPI-25; BACKUP_RECOVERY §5)

   The freeze also waits for the off-hours window those two need. It is recorded in the release record and announced to the clients in advance, outside the busiest days.
6. **The reconciliation after the rehearsal's commit** (GAP-010; IMP-11). Stock per company must equal its opening lots, receivables the imported remainders, and credits the signed opening evidence. `guards:reconcile` must report no difference. Finally, run `ops:post-import-refresh`; a plan marked REVIEW goes to the operator and Planning before production.

## 3. The cutover, CUT-01–CUT-08

| Step | When | Who | What, with its checks |
| --- | --- | --- | --- |
| CUT-01 | Before production holds any data | the operator, the Owner, the validators | The cutoff, the freeze window and its length from §2, the clients' announcement, the batches' order (IMP-03) and the people are agreed and recorded. No delta batch is planned: the final snapshot is one per batch (IMP-02). |
| CUT-02 | Before any real data | the operator; the Owner for the masters | First GLC-06, GLC-07 (go-live's file absent: `numbering:go-live` not yet run), GLC-11 and GLC-24 must be met. Then the installation step, then the one `APP_KEY` rotation. Then the masters, accounts, roles and grants, the tax configuration and the numbering schemes as they were before the cutoff (IMP-01; IMP-07; HO-32). |
| CUT-03 | Before the freeze | the Admin, the Owner | Each batch is prepared, dry-run, reconciled and signed on the real data through PG-058, repeated until it passes (IMP-04; IMP-05). The dry run is on production, never on staging and never by an agent. |
| CUT-04 | The freeze begins | the Owner, the data steward, the Admin | The legacy system stops issuing and recording. The final snapshot is taken at the cutoff. Its batches are prepared, dry-run, reconciled to the signed totals and jointly signed off. |
| CUT-05 | After the sign-offs | the operator, the Owner | `backup:run --kind=preimport` exits 0 and `backup:status` shows it VERIFIED. Then the Owner commits the batches in IMP-03's order, resuming a stopped one from its batch (IMP-06). A committed batch is never imported again. |
| CUT-06 | After every commit | the operator, the validators | Run `guards:reconcile`: no difference. Check the control totals per company (IMP-11). Re-create the open projects' reservations through the ordinary commands (IMP-08). Then run `ops:post-import-refresh`, with every plan OK or reviewed (IMP-10). |
| CUT-07 | After every batch is COMMITTED and reconciled, every company's seeds have committed, and the production restore drill is recorded (GLC-04) | the operator; the Owner for the starts | Run `numbering:go-live <day> --operator="<name>"` once (architecture challenge 14b). Then the Owner starts the types no import covers (NM-03; UXH-10). |
| CUT-08 | Open for business | the Owner, the operator | The first issue per company draws its seeded number. Watch the heartbeat and the alerts through the first day. The freeze ends. |

A deployment inside the cutover window follows [COOLIFY_DEPLOYMENT §6](../COOLIFY_DEPLOYMENT.md#6-a-production-deployment--the-release-runbook-rel-08rel-13): expand-only migrations, the drain before it, and payload versions and command validity across one release (HO-31; HO-34).

## 4. When a step fails (RBK-01–RBK-06)

- **A failed step stops** (RBK-04). Put the application in maintenance mode when writes are at risk. The operator and the Owner then choose between fixing forward, a code rollback and a data recovery, and record the choice and its reason.
- **A wrong opening fact** is corrected on its record by contras and reversals by import identity, before and after go-live alike. A committed batch is never imported again (RBK-03; CM-33).
- **Data recovery** is by [BACKUP_RESTORE §4](BACKUP_RESTORE.md#4-the-restore-backup_recovery-4) only, on the Owner's decision, into isolation. Before go-live, the generation to restore is the pre-import one. After it, the Owner's numbering raise comes before any numbered issue (RBK-02; DIR-083 OE-11). Restoring the database never touches go-live's file: if go-live was recorded, it stays recorded.
- **A code rollback** follows RBK-01's runbook, and **an interrupted migration** the procedure its staging rehearsal proved (RBK-05).
- **The second application is never touched** (RBK-06): every step acts on MULTIPLECORP's own project, database, storage and backups alone.

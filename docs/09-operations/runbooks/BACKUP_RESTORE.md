# Backup, off-server copy, restore and drills

Status: REVIEW | Updated: 2026-11-21 | Owner: Planning

Written by [TASK-074](../../11-tasks/TASK-074.md): the operator's procedures for the backup tooling that [BACKUP_RECOVERY](../BACKUP_RECOVERY.md) designs (BKR-01–BKR-09; BK-01–BK-12). Every value below is a placeholder that the operator sets on the VPS (TASK-084). No real path, key or destination is written here. Running a backup, copy, restore or drill on staging or production is the operator's alone (TASK-079, TASK-081, TASK-084, TASK-087). No agent runs one or holds a backup (BKR-06).

## 1. The tooling and its configuration

The commands are the application's own. They run in a one-off container of MULTIPLECORP's image under the **operator account** (`--user <operator uid>`), with `<BACKUP_DIR>` mounted. They refuse to run outside a console (SX-04).

| Command | What it does |
| --- | --- |
| `php artisan backup:run --kind=daily\|predeploy\|preimport\|manual` | One generation: the dump, then the files and the manifest. The run is verified, and the expired generations are then removed. |
| `php artisan backup:status [--quick]` | Each generation's status read from its bytes. A corrupt generation, or no verified backup or copy within 26 hours, fails the command and is alerted. |
| `php artisan backup:copy [<generation>]` | The encrypted off-server copy of a verified generation, verified at the destination. |
| `php artisan backup:fetch <copy> --secret-key-file=<file> --into=<dir>` | A copy brought back, decrypted and verified as a generation. |
| `php artisan backup:restore <generation> --database=<new> --files-root=<new> --operator="<name>" [--source=<dir>] [--fallback]` | §4 below. |
| `php artisan backup:copy-keys --secret-out=<file>` | The copy's key pair, made off the VPS. |

| Setting | Placeholder | Meaning |
| --- | --- | --- |
| `BACKUP_DIR` | `<BACKUP_DIR>` | MULTIPLECORP's own directory of generations, readable by the operator account alone (SX-07). It is never the second application's directory. |
| `BACKUP_COMMIT` | the deployed commit | The commit each generation's name carries (BK-01). |
| `BACKUP_KEY_REGISTER_ENTRY` | `<KEY_ENTRY>` | The key-register entry of the live `APP_KEY`. It is the entry's name, never the key (BKR-08). |
| `BACKUP_LEAKED_KEY_ENTRIES` | empty | The entries of leaked keys. A generation paired with one is restored only by AU-25's Scenario B. |
| `BACKUP_KEEP_DAILY`, `_WEEKLY`, `_MONTHLY` | 7, 4, 3 | DIR-079 CB-11, confirmed against the disk budget (GLC-05). |
| `BACKUP_DISK_WARN_PCT`, `BACKUP_DISK_CRIT_PCT` | `<DISK_WARN_PCT>`, `<DISK_CRIT_PCT>` | BACKUP_RECOVERY §3. |
| `BACKUP_COPY_DESTINATION` | `<COPY_DESTINATION>` | The destination OPI-32 names, mounted by role. |
| `BACKUP_COPY_PUBLIC_KEY` | `<COPY_PUBLIC_KEY>` | The public half of the copy's key pair. The secret half is never on the VPS. |
| `BACKUP_DB_USERNAME`, `BACKUP_DB_PASSWORD` | given to the run only | The login of the dump and the restore: the owner role's, which a dump of every table and a restore of owned objects need (DATABASE §28). It is in no running process and is passed to the one-off container alone. The tooling never uses the runtime's or the migrations' connection (H9-01). |
| `DB_ADMIN_USERNAME`, `DB_ADMIN_PASSWORD` | given to the restore only | The administration role, which reads `ReconcileGuards`' families on the restored database: restore verification (INFRASTRUCTURE §4.4). |

**What a generation holds.** A generation is `<BACKUP_DIR>/<UTC time>-<commit>/` and holds three files:

- `database.dump`: a custom-format `pg_dump` at one exported snapshot. Its tools have the server's major version, PostgreSQL 18 (HO-30).
- `files.tar`: the bytes of every file row the dump itself holds, read after its snapshot, with exports left out (GT-06). Each file is checked against its row's size and SHA-256, so the dump never names a missing file (BKR-01).
- `manifest.json`: written last. It holds each part's SHA-256 and size, the recovery point, the commit and the key-register entry.

**Status.** A generation is VERIFIED only when every checksum verifies. Otherwise it is INCOMPLETE (no manifest), CORRUPT or MISSING (BK-07). A failed, skipped or partial run deletes nothing and is never reported successful (BKR-02). Every failure is logged as `Backup alert (BK-10)`, which TASK-075's alerts read. Directories are `0700` and files `0600`.

## 2. The daily and pre-deployment runs

1. **Daily** at `<BACKUP_TIME>` (OPI-23), from the operator account's cron entry (HO-23). The time must be clear of `ReconcileGuards`' 02:00 WIB snapshot (HO-28) and of the second application's windows (OPI-27). The entry runs `backup:run --kind=daily`, then `backup:copy`, then `backup:status`. A non-zero exit is a failure the operator follows up.
2. **Before every deployment**, run `backup:run --kind=predeploy`. Its exit 0 is the gate of [COOLIFY_DEPLOYMENT §6](../COOLIFY_DEPLOYMENT.md#6-a-production-deployment--the-release-runbook-rel-08rel-13) step 2 (REL-08). Any other exit stops the deployment.
3. **Before the opening import commits** (CUT-05), run `backup:run --kind=preimport`.
4. **Retention.** Cleanup removes only the verified generations the retention lets expire. It never removes the newest verified generation or an unverified one. The latest pre-deployment generation is kept until the next release's. A pre-import generation is kept until a newer pre-deployment generation verifies, which is the go-live deployment's (BK-06). A failed cleanup stops, and is reported and alerted. A removal that stops halfway leaves an incomplete generation, never one that looks verified.
5. **Disk pressure** (BACKUP_RECOVERY §3). At `<DISK_WARN_PCT>` the run goes ahead and the operator is alerted. At `<DISK_CRIT_PCT>` the run starts only if the new generation fits. Otherwise it is skipped, alerted and reported as SKIPPED, with nothing deleted to make room.

## 3. The off-server copy (BKR-09; DIR-083 OE-09)

1. **The key pair, once, on the operator's own machine.** Run `backup:copy-keys --secret-out=<file>`. Keep the secret key in the operator's secret store, apart from the VPS and the copies, together with a copy of each paired `APP_KEY` (SX-01; AU-21). Set the printed public key as `BACKUP_COPY_PUBLIC_KEY`.
2. **Each daily run** copies the newest verified daily generation. The copy works like this:
   - Each file is encrypted to the public key: a fresh key under libsodium's XChaCha20-Poly1305 secret stream, sealed to the operator's X25519 key.
   - The files are written to `<COPY_DESTINATION>/<generation>.partial/`.
   - Their checksums are read back at the destination, and only then is the directory renamed.
   - A copy counts only once it verifies there. Only a verified generation is copied (13a).
3. **Failure.** A failed copy, or none verified within 26 hours, is alerted and deletes no retained generation. The copies keep the generations' retention.

## 4. The restore (BACKUP_RECOVERY §4)

1. **Stop writes.** Put MULTIPLECORP in maintenance mode and stop its queue workers and scheduler. The second application keeps running, untouched.
2. **Choose the recovery point with the Owner**, and check it with `backup:status`. If the chosen generation is corrupt or missing, `backup:restore` refuses it and names the next older verified generation. `--fallback` takes that one.
3. **Prepare the target.** As PostgreSQL's superuser, create a new empty database owned by the owner role in MULTIPLECORP's own instance: `createdb -O <owner role> <new database>`. Create `pg_stat_statements` in it if the dump lists it. The tooling creates the trusted extensions itself and refuses the rest with this instruction. Choose a new files' directory.
4. **Install the paired key.** Set the generation's paired `APP_KEY` (its register entry is in the manifest and the restore's record) in the environment of the one-off container. A leaked key is never installed: that is Scenario B (INFRASTRUCTURE §7.3 step 5; TASK-076).
5. **Run** `backup:restore <generation> --database=<new database> --files-root=<new directory> --operator="<name>"`. It does five things:
   - It restores the dump into the new database in one transaction and extracts the files into the new directory. It never goes over the live ones (BKR-05).
   - It refuses a key that does not open the restored second-factor secrets, before anything reads them (BKR-08).
   - It reconciles: every file row has its bytes and every byte file has its row. Each issued version without a rendition is rendered again from its issued payload, never reissued or renumbered (GAP-007).
   - It runs `ReconcileGuards`' read. A difference is reported, never repaired (HO-28).
   - It writes the record `<BACKUP_DIR>/restores/<time>-<generation>.json`, which holds no secret.
6. **Check the critical records with the Owner** against the business's papers. If the restore is accepted, point the stack's database and `FILES_ROOT` at the restored ones (`<files-root>/files`). Restart the workers and the scheduler, so that owed work resumes from its durable rows (JB-02; JB-09). Then follow §4 step 7: the Owner's numbering raise before any numbered type issues again (DIR-083 OE-11).

## 5. The drills (BACKUP_RECOVERY §5; AC-18; AC-19)

The cadence is monthly for the first three months after go-live, then quarterly, and after any change to this tooling (DIR-079 CB-12). Drills run outside both applications' business hours, around the second application's windows, never touching it. Each drill is recorded by the operator with its date, recovery point, elapsed time, result and follow-ups. A summary without secrets enters the release's evidence.

| Case | How | The expected truthful status |
| --- | --- | --- |
| Intact backup | §4 into isolation. Time it against the 4-hour RTO, and the recovery point's age against the 24-hour RPO. | The record shows `usable: true`, `within_rto`, `within_rpo`, no missing bytes and no guard difference. |
| Corrupt generation | Change one byte of a copy of a generation's part in a scratch directory, then run `backup:status` and `backup:restore --source=<scratch>`. | CORRUPT. The restore refuses it and names the next older verified generation. |
| Missing generation | Run `backup:restore` with a generation that does not exist. | MISSING. The restore refuses it. |
| Incomplete run | Stop a run before its manifest, or remove a manifest in a scratch copy. | INCOMPLETE. It is never copied or restored, and cleanup leaves it in place. |
| Disk pressure | Lower `BACKUP_DISK_WARN_PCT` and `BACKUP_DISK_CRIT_PCT` for one run. Never fill the shared disk (INF-07). | WARNING or CRITICAL alerted. At the critical level without the space, the run is SKIPPED and nothing is deleted. |
| Failed cleanup | Make one expired generation undeletable for the run, for example with a read-only directory. | Cleanup FAILED and alerted. Every kept generation stays. |
| Off-server copy (once before go-live; GLC-20) | `backup:fetch` a copied generation with the secret key from the store, then §4 with its paired key, unchanged. | The fetched generation is VERIFIED, and the restore's record is usable. This proves the copy, not a recovery time after a total loss. |

The first drill before go-live restores the generation taken after the opening import, with its paired key: AC-18's evidence. Staging's synthetic restore is its rehearsal (RELEASE_PLAN §7). A local drill proves no recovery after total VPS or storage loss: RISK-001 stays narrowed and is never removed (OS-04; GAP-018).

<?php

// The operator's backup tooling (TASK-074; BACKUP_RECOVERY BKR-01–BKR-09, BK-01–BK-12). Every value is a placeholder
// the operator sets on the VPS (TASK-084): no path, key or destination of a real environment is in the repository.
return [

    // `<BACKUP_DIR>`: MULTIPLECORP's own directory of generations, mounted into the operator's one-off container alone,
    // never into the web, worker or scheduler containers (SX-07).
    'directory' => env('BACKUP_DIR', storage_path('backups')),

    // The deployed commit a generation is named by (BK-01); the deployment sets it.
    'commit' => env('BACKUP_COMMIT', 'unknown'),

    // The key-register entry of the `APP_KEY` valid now — the entry's name, never a key (BKR-08; TM-65).
    'key_register_entry' => env('BACKUP_KEY_REGISTER_ENTRY'),

    // Key-register entries of leaked keys: a generation paired with one is restored only by AU-25's Scenario B.
    'leaked_key_entries' => array_values(array_filter(explode(',', (string) env('BACKUP_LEAKED_KEY_ENTRIES', '')))),

    // DIR-079 CB-11: 7 daily, 4 weekly and 3 monthly generations, confirmed against the disk budget (GLC-05).
    'retention' => [
        'daily' => (int) env('BACKUP_KEEP_DAILY', 7),
        'weekly' => (int) env('BACKUP_KEEP_WEEKLY', 4),
        'monthly' => (int) env('BACKUP_KEEP_MONTHLY', 3),
        // Days are counted in the business's time zone, where `<BACKUP_TIME>` is set (BK-04).
        'timezone' => 'Asia/Jakarta',
    ],

    // `<DISK_WARN_PCT>` and `<DISK_CRIT_PCT>` of used space on the backup directory's file system (BACKUP_RECOVERY §3).
    'disk' => [
        'warn_pct' => (int) env('BACKUP_DISK_WARN_PCT', 80),
        'critical_pct' => (int) env('BACKUP_DISK_CRIT_PCT', 90),
    ],

    // BK-10: no verified backup within 26 hours is a stale run.
    'stale_hours' => (int) env('BACKUP_STALE_HOURS', 26),

    // The off-server copy (BKR-09): the destination OPI-32 names, mounted here by role, and the public half of the
    // copy's key pair — the secret half never on the VPS.
    'copy' => [
        'destination' => env('BACKUP_COPY_DESTINATION'),
        'public_key' => env('BACKUP_COPY_PUBLIC_KEY'),
    ],

    // The login the operator gives to a dump or restore run alone — the owner role's in practice (DATABASE §28) —, read
    // from the run's environment; never the runtime's or the migrations' connection (H9-01).
    'database' => [
        'username' => env('BACKUP_DB_USERNAME'),
        'password' => env('BACKUP_DB_PASSWORD'),
    ],

    // The dump and restore tools, matched to the server's major version (HO-30).
    'pg_dump' => env('BACKUP_PG_DUMP', 'pg_dump'),
    'pg_restore' => env('BACKUP_PG_RESTORE', 'pg_restore'),
    'tool_timeout_seconds' => (int) env('BACKUP_TOOL_TIMEOUT', 4 * 3600),

    // The drill targets of AC-18 (BACKUP_RECOVERY §1).
    'rto_seconds' => 4 * 3600,
    'rpo_seconds' => 24 * 3600,
];

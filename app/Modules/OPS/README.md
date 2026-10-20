# Operations (OPS)

Tier 0 of [ARCHITECTURE §3](../../../docs/04-architecture/ARCHITECTURE.md#3-modules). `Models/` holds the Eloquent models of its tables (TASK-005); its other Tasks add the rest.

Tables ([DATABASE §4.13](../../../docs/04-architecture/DATABASE/s04-13-ops.md), 7): `audit_events`, `security_events`, `command_log`, `correction_cases`, `import_batches`, `import_rows`, `legacy_archive_items`.

Also the operational stores outside the 125 business tables: `export_requests` (CONCURRENCY_IDEMPOTENCY H6-04), whose behaviour is TASK-060's, and the framework's `failed_jobs` (SECURITY H9-04), whose retention is TASK-022's.

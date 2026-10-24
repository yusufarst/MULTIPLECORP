# Operations (OPS)

Tier 0 of [ARCHITECTURE §3](../../../docs/04-architecture/ARCHITECTURE.md#3-modules). `Models/` holds the Eloquent models of its tables (TASK-005); its other Tasks add the rest.

Tables ([DATABASE §4.13](../../../docs/04-architecture/DATABASE/s04-13-ops.md), 7): `audit_events`, `security_events`, `command_log`, `correction_cases`, `import_batches`, `import_rows`, `legacy_archive_items`.

Also the operational stores outside the 125 business tables: `export_requests` (CONCURRENCY_IDEMPOTENCY H6-04), whose behaviour is TASK-060's, and the framework's `failed_jobs` (SECURITY H9-04), whose retention is TASK-022's.

## Audit and security logging (TASK-010)

[SECURITY §6](../../../docs/05-security/SECURITY.md#6-audit-and-security-logging):

- `Services/AuditRecorder` — the business audit (LG-01; DATABASE §23). An action calls it inside its own transaction; it refuses to write outside one, so the row commits or rolls back with the change. `Data/AuditChange` builds the before/after — an update's changed keys only, a secret's key kept with its value redacted (`App\Support\Redaction`) — and `Data/AuditLinkage` the correction linkage (BR-CR-01), stored in the metadata with a backdated command's `business_date` (DIR-018).
- `Services/SecurityEventRecorder` — the security events (LG-02), on the database connection `security` of their own, so a refused or rolled-back command's event persists; a failed write never throws and is logged with the correlation id. Identifiers, addresses and provider subjects are stored only as the keyed hashes of `Services/SecurityLogHash`, under `SECURITY_LOG_HMAC_KEY` (LG-03); user agents as `Data/UserAgentSummary`; details only under its whitelist, which a later Task extends for its kind. Called inside a business transaction, it writes the event once that transaction ends, committed or rolled back, never waiting on its locks. `recordUndisclosed()` writes the two TECH-022 kinds after the open transaction commits, never on a rollback, once per command and matched record; the caller runs it after the response where the runtime offers that step.
- `Queries/AuditEventQuery` and `Queries/SecurityEventQuery` — the read models of PG-063 and PG-064 under `audit.view` and `security_log.view` (LG-05), newest first in keyset order (`Queries/KeysetPage`), filtered by WIB business days (`Queries/BusinessDay`; GAP-016). TASK-011 registers the capabilities as gates; TASK-012 applies PJ-11's projection; TASK-061 builds the pages.
- The correlation id (`App\Support\CorrelationId`, ARCHITECTURE §17) is started by the global middleware `AssignCorrelationId` and flows through the framework's context into jobs, log lines and both writers. The file log channels write JSON lines, redacted and neutralized by `App\Support\Logging\StructuredLog`, mode 0600, the daily channel keeping `LOG_DAILY_DAYS` files (LG-04, LG-06; H9-08).

Both writers read the command's transaction through `App\Support\Database\Transactions`, which leaves out a test's own wrapping transaction, so a test proves production's boundaries. The test suite's `RefreshDatabase` points the security events at the default connection, inside the test's transaction; the tests of the separate connection's independence use `DatabaseTruncation`.

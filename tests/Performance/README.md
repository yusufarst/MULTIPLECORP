# TL-15 — Performance

The measurements of [PERFORMANCE_TARGETS](../../docs/08-testing/PERFORMANCE_TARGETS.md) ([TEST_STRATEGY §14](../../docs/08-testing/TEST_STRATEGY.md#14-performance)), as TASK-068 leaves them for the initial release (APPR-035; APPR-036).

## In CI, on every run (`scripts/verify.sh performance`)

- **Query budgets** (PFM-01, PFM-02): each page and operation of QB-01–QB-17 counted beyond the request's envelope with `Tests\Support\QueryBudget` — the session's read and write, the account row and the three grant reads left out — against its budget's own number, and the same count for one row and for many. Commands count their statements (QB-13, QB-14).
- **Plans** (PFM-03, PFM-08): every query of the index register (`IndexRegisterTest`) and each guard family's `ReconcileGuards` statement planned with `EXPLAIN (ANALYZE, BUFFERS)` on the register's synthetic tables — 20,000 rows each, at or above 1x but for the audit, security, ledger, stock and import rows —, no sequential scan of a table above 10,000 rows in an interactive query (reconciliation reads whole tables by design); the plans are kept as evidence in `storage/logs/verify/plans/`, each guard table's size beside them in `reconcile-sizes.txt`, so a family planned over few rows shows as such. 100x is NOT TESTED for the initial release, never PASS (APPR-035).
- **Times** are recorded, never passed or failed: CI's runner is not staging (PERFORMANCE_TARGETS §1).
- A count above its budget is recorded as a miss for the Owner's decision (TASK-068 criterion 9) — a test marked incomplete with the measurement, never a loosened assertion.

## On staging, by the operator (TASK-079)

| Script | Measures | Target |
| --- | --- | --- |
| `load/staging.js`, `MODE=warm` | PFM-01's times: each release-critical page's cold request apart, then 50 warm ones | each page's p95 within its QB time — 300 ms for QB-01, QB-05, QB-07 and QB-10, 400 ms for QB-02 and QB-09, 150 ms for QB-06 |
| `load/staging.js`, `MODE=peak` | PFM-13: AS-03's peak — 8 page reads and 2 commands a second for `DURATION` | every p95 within its budget, no error, at 10x where the data allow |
| `php scripts/staging/measure-hashing.php` | PFM-12: the configured Argon2id verification timed on the VPS, beside the verification slots and the limiters | the operator's figure for OPI-07; each value kept or corrected through review |
| `php scripts/staging/measure-jobs.php` | PFM-16: renditions' issue to READY and exports' waiting and generation, from their rows | 30 s at 1x for a rendition; 100,000 rows within 60 s at 10x for an export |

`staging.js` signs in with `EMAIL` and `PASSWORD` of a persona that needs no second step — or takes `COOKIE` from an open session — and refuses a page the persona cannot open (`PAGES` limits the run). Every script reads only; none generates data. A time is reported only from staging's own run, PENDING until then.

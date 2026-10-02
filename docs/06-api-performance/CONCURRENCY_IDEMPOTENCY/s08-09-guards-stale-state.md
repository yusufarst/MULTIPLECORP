## 8. Guards and first-use rows

| ID | Rule |
| --- | --- |
| GU-01 | Each guard row is written once per command, by one statement carrying the net signed delta of all its columns (DATABASE §1); deltas that several posting services contribute to one row are combined first (ARCHITECTURE §7). One statement may carry several rows of one guard table, each row once. |
| GU-02 | The statement names its rows by their keys only and must affect exactly the rows it names. **Fewer is a failure, never a silent success:** the action ends FAILED with nothing committed and a guard-missing alert (SQ-18); it is not retried automatically and the row is never created at that point. |
| GU-03 | The row is already locked (LR-02) and its values were re-read under that lock, so the application refuses a broken precondition with the current state (SQ-02). The CHECK is the backstop, and a CHECK violation maps to the same rule (SQ-04). |
| GU-04 | Guard statements are issued in canonical order. |
| GU-05 | The `version` of a scope or product guard advances in the same statement by the number of the command's movements that have entries under that key, as DATABASE §3.1 defines it. |
| GU-06 | A conditional state transition names the expected state in its WHERE clause. Zero rows is the stale or duplicate outcome its sequence names (SQ-03), never success. |
| GU-07 | Only the posting service that owns a guard issues its statement (ARCHITECTURE §7). `GuardMaintenance` is the one audited exception; it follows the same order in a maintenance window (TX-08). |

**Born rows** are inserted with their record and have no creation race: the product guard with its product; the line guard with its line's first pin and the commercial guard with the project's first confirmation (AX-13) — a draft line or a DRAFT project, which may still be deleted, has none; the purchase-line guard with its line; the charge guard with its charge; the lot-cost row with its lot; the consumption guard with its consumption or confirmation line; the invoice guard with the invoice's issue; the source guard with its payment or opening credit; the money-fact guard with its money fact; the evidence guard with its evidence document or statement line; exposure rows with their case, and a founder's group rows with it; case counters with their case.

**First-use rows** are created on demand. **Insert-or-lock** means: `INSERT ... ON CONFLICT DO NOTHING` on the row's key, then a lock on the row — whichever transaction created it. A concurrent first user waits inside the insert for the other transaction and then finds the row. The row is inserted with the value its DATABASE §3.1 formula gives before the command's own effect — zero quantities, the reversed movement's original quantity, the share of the current allocation version — and the command's effect arrives only through its guard statement. It is created inside the command's savepoint, so a refused or failed command leaves none behind.

**A lost row is not a first use.** When the insert really creates the row, the command checks the proof in the last column before it goes on. A fact that the row should already summarize means that a guard row was lost: the command ends as SQ-18 — nothing committed, a guard-missing alert — and the row is never silently re-created, which would be an unaudited rebuild (C-61; GAP-031).

| ID | Row and key | First needed by | Also serialized by | Creation | Proof of first use |
| --- | --- | --- | --- | --- | --- |
| FU-01 | `stock_scope_balances` (product, location, condition) | the first movement into the scope | LK-13 | insert-or-lock at LK-14 | no ledger entry of the product in that scope |
| FU-02 | `stock_lot_balances` (lot, location, condition) | a receipt, move, condition change or restoration that first places the lot there | LK-13 | insert-or-lock at LK-15 | no ledger entry of the lot at that location and condition |
| FU-03 | `stock_reversal_balances` (reversed movement, lot, location, condition) | the first reversal of a movement at that key | LK-13 | insert-or-lock at LK-16, its original quantity taken from the reversed movement's entries | no reversal entry citing the movement at that key |
| FU-04 | `serial_units` (product, serial key) | the first registration of a serial | LK-13 | insert-or-lock at LK-17 in the state NEVER_RECEIVED, then the command's conditional transition | the key itself — a serial row cannot be lost while a fact references it |
| FU-05 | `purchase_charge_share_balances` (charge, line) | the first posting against a share | LK-21 | insert-or-lock at LK-22 | no posting cites a share of that charge and line |
| FU-06 | `unattributed_loss_group_exposures` (group case, company) | a recognition that joins a group with a candidate company the group does not hold yet | the founder case row at LK-11 | insert-or-lock at LK-11; a bound only rises | no other member of the group has an exposure row for that company |
| FU-07 | `number_sequences` (company, sequence type, period) | the first issue of a live period | nothing earlier — two issuers may share no other anchor | NM-02 at LK-31, with no seed value; a seed is inserted the same way by AX-28 or by the numbering command (NX-01) | the period is live (NM-03), and no issued version, project or tombstone carries it; for a seed, no version issued here, live project or tombstone carries a number of the period at or above its first number (NM-03) |
| FU-08 | `command_log` (command id) | every command | — | CI-03 at LK-00 | — |
| FU-09 | `reservations`, one ACTIVE per line | AX-01 on a line without one | LK-01 | inserted in phase D; the partial UNIQUE key is the backstop | — |
| FU-10 | evidence registration keys | NX-07 | — | `pg_advisory_lock`, session-level, in its two-integer form: first integer 7101 for a content hash and 7102 for a type-free identity — issuer, reference and date, whatever the company, or, for a document without a reference, company, issuer and date —, second integer the low 32 bits of the SHA-256 of the key. Every attempt takes them on the session it runs on, in (namespace, key) order, immediately before its lookups: in a short step of its own that sets the lock wait of TX-01 with `SET LOCAL` — a session-level key outlives the transaction that took it. The attempt's transaction first confirms that it runs on the session that holds the keys; if that session was lost, the attempt ends as SQ-16 and its retry takes the keys again. They are released when the attempt's transaction has ended, and if the session dies, they go with it. A collision only serializes two unrelated registrations for a moment. No other advisory lock exists in V1 | — |
| FU-11 | `stock_count_findings`, one OPEN per (product, location, condition) | a count application with an unexplained surplus | LK-13, LK-14 | inserted in phase D (C-34) | — |
| FU-12 | `documents`, one chain per record-bound subject and type | the first draft of a layout | the subject's anchor — LK-01, LK-21 or LK-25 | inserted in phase D; a lost race is SQ-08 | — |
| FU-13 | `inventory_lots.lot_code` | lot creation | — | generated, then `INSERT ... ON CONFLICT (lot_code) DO NOTHING`, regenerated when no row returns, at most five times (H6-10) | — |

```text
NON-EXECUTABLE ILLUSTRATION — a first-use scope row and its guard statement
(placeholders :p, :l, :c, :q, :m; the real statements belong to the executor)

-- phase B, at LK-14, after the product guard is locked
INSERT INTO stock_scope_balances (product_id, location_id, condition, claims_qty,
                                  pending_reduction_qty, pending_addition_qty, version)
VALUES (:p, :l, :c, 0, 0, 0, 0)
ON CONFLICT (product_id, location_id, condition) DO NOTHING;      -- a row returned: prove the first use (FU-01)
SELECT claims_qty, pending_reduction_qty, pending_addition_qty, version
  FROM stock_scope_balances
 WHERE product_id = :p AND location_id = :l AND condition = :c
   FOR NO KEY UPDATE;

-- phase D, one statement carrying the net delta; :m movements of the command touch the scope (GU-05)
UPDATE stock_scope_balances
   SET claims_qty = claims_qty + :q, version = version + :m
 WHERE product_id = :p AND location_id = :l AND condition = :c
RETURNING claims_qty, version;                                    -- no row is a failure (GU-02)
```

**Reconciliation cadence (GAP-031).** `ReconcileGuards` (JB-06) stays verify-only and `GuardMaintenance` audited and manual. It recomputes all twenty-two registry rows of DATABASE §3.1 every night at 02:00 WIB, each family inside one TX-05 snapshot so facts and guard are compared at one instant; it also runs after every restore before the application reopens (P9), after a deployment that changes a posting service (P10), on the operator's demand, and after every P8 scenario. Where a §3.1 formula replays facts in recorded order — a serial's latest event, a group bound — the order is the `recorded_at` the envelope stamps after its locks are granted (phase C), with the row id as tie-break: PostgreSQL's default would take it at transaction start, before the lock wait, and two commands serialized by an anchor could then appear in the opposite order. A first-use row left at zero by a derivation that locked more than it needed equals its formula and is no difference. A difference is reported to the named operator and the technical log and never repaired, and it blocks nothing by itself: the operator and the Owner decide on `GuardMaintenance`. Between runs, GU-02, the first-use proof and SQ-04 are the immediate signals of a broken guard.

## 9. Stale state

| ID | Mechanism |
| --- | --- |
| ST-01 | **`lock_version`.** An editable row is changed only by an update whose WHERE clause carries the `lock_version` the client saw and which increments it; zero rows is CONFLICT with the current state (SF-CMD step 5; OB §32). The version of an **aggregate root covers its draft children**: any edit of a draft quotation revision's or invoice version's line, a draft document version, a draft project's line or a purchase line before its first effect bumps its root, so two Admins editing different lines of one draft still conflict and totals are never lost; a confirmation without a quotation carries the project's version for the same reason (AX-13). The affected-row count is read from the builder's update — never from a model save, which discards it. |
| ST-02 | **Conditional transitions.** DRAFT → ISSUED or SENT, ISSUED → VOIDED or SUPERSEDED, an application's or settlement's end, a count's or finding's close follow GU-06; a second transition of the same row is CONFLICT carrying the state already reached. |
| ST-03 | **Count staleness.** Applying a count locks the count, then the product and scope guards, and compares each `stock_count_scopes.basis_version` with the live scope `version`. Any difference returns CONFLICT: after the rollback to its savepoint the transaction commits, with that outcome, the count's conditional OPEN → STALE transition and its audit event — of authority class SYSTEM, because no authority was exercised. Staleness never reverts, so marking it needs no other lock. No stock row changes, so a count recorded against a superseded scope version never overwrites stock (C-33; L-14; GAP-009). A surplus attribution moves the scope version like any movement, so a count opened before it is stale too (AX-07). |
| ST-04 | **Force Complete snapshot hash.** The Owner's confirmation carries the hash of the blocker snapshot shown. After LK-01 is held, the three documents the transition row will store — the predicate snapshot, the unmet predicates and the residual obligations, with every amount and quantity as a decimal string — are recomputed, serialized canonically (sorted keys, no timestamps, a format version) and hashed; a different hash is CONFLICT with the current snapshot (AX-26; C-38). A changed amount therefore changes the hash even when the same records remain. |
| ST-05 | **Session expiry.** A form that was never sent is submitted after re-authentication as a new intent through SF-CMD step 5: it carries the `lock_version`, guard version or snapshot hash it saw and meets ST-01–ST-04 like any command (AU-07; GAP-015). A submission that may have reached the server keeps its `command_id` across the login (HO-02): if it had committed, the replay by the same account returns that outcome (CI-05); if it had not, it runs now with every check. |
| ST-06 | **Preconditions are not stale checks.** A quantity or amount precondition is re-read under its guard's lock and refused as REJECTED with the current state (SF-CMD step 4); CONFLICT is reserved for ST-01–ST-05, ST-07–ST-09, the business identities of [section 11](s10-13-identity-failure-retry.md#11-separate-duplicates) and CI-05. The loser of a last-unit race is therefore REJECTED, deterministically. |
| ST-07 | **Disposition lists.** A command whose payload disposes of dependents — the applications and settlements of a payment contra, an invoice void or revision, a contra-fact — is compared, under LK-25, LK-26 and LK-27, with the dependents that exist now; if the payload does not cover exactly the current ACTIVE ones, the command is CONFLICT with the current list. |
| ST-08 | **A prefilled date that went stale.** The payload says whether the user chose its `business_date` or the form only prefilled it. A prefilled date that is no longer today (WIB) when the command first executes is CONFLICT with the current date, so nothing is backdated by a form left open across midnight or a month end (BR-DT-02); a chosen date is taken as it is (DIR-018), and a replay never re-evaluates either. |
| ST-09 | **Product policy.** Every movement, and every drop-ship confirmation, re-reads its product's serial policy and quantity scale after LK-13 is held; NX-01 changes them only under that lock (L-35). A difference from what the payload was validated against is CONFLICT with the current policy. Every other row that carries a quantity meets a changed scale through its scale key (DATABASE §1; C-09): that violation is CONFLICT with the current policy as well, and a change of scale that meets a row already using the old one is REJECTED (C-09). |


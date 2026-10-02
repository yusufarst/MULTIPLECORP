## 4. Canonical lock order

### 4.1 Lock rules

| ID | Rule |
| --- | --- |
| LR-01 | Every row lock of a transaction is acquired in ascending canonical order: by class position (4.2), then by the class's key order. A transaction never acquires a lock that sorts before one it already holds. |
| LR-02 | A command takes all its row locks before its first write ([section 6](#6-command-envelope), phase B). Its writes touch only rows it has locked or rows it inserted itself. Token and session rows (LK-04, LK-05), and the export-request rows an access change withholds (RV-08), are locked by the statements that write them, the last ones of their transactions. |
| LR-03 | The only lock mode this design requests is FOR NO KEY UPDATE. PostgreSQL itself takes the stronger FOR UPDATE mode for a DELETE and for an UPDATE that changes a column of a unique index a foreign key could use: the delete of a draft row (DATABASE §19), of a session row (RV-05, RV-10) and of an expired `command_log` row (JB-08); the issue transition that writes a version's number columns (AX-15, AX-16, AX-22, AX-31); the attribution that writes a count finding's company (AX-07); a change of a draft project's client or company (NX-03; L-21); and a master edit that changes a unique key column — a code, a product's quantity scale, a bank account's identity, a numbering scheme's effective date (NX-01). Such a statement waits for every transaction that holds the FOR KEY SHARE lock of a foreign-key check on that row — one that has inserted a row referencing it — and no cycle can close through that wait. No row references a session or a `command_log` row. For a transactional row (a draft, a version, a finding, a draft project) every inserter of a referencing row holds that row's anchor (LR-06), so it cannot be running at the same time. For a master row the inserter is an ordinary command. One that locks the master row — a company at LK-06, a bank account at LK-07 — meets the master command at that position, before it has written anything. Any other inserter never locks it: it inserts in its write phase, when it waits for nothing any more, or as a first-use insert at LK-11 or later, after whichever of its project, actor and company rows it takes and — where the inserted row references a product — after that product's guard (LR-09); the master command holds only its identity, its actor's row, the master row and, for a product's policy, that guard, so such an inserter never waits for it. The identity row of LK-00, the one row inserted before any lock, carries no foreign key (CI-03), so it takes no such lock at all. A master command changes the key of at most one row. Every other foreign-key check never waits on a lock this design takes. |
| LR-04 | A row is locked once. No command requests a lock on a row it already holds, and the only strengthening of a held lock is PostgreSQL's own, named in LR-03. |
| LR-05 | Rows of one class are locked in ascending key order — the class's natural key wherever a row can be created on first use, so that existing rows and rows about to be created sort the same way, otherwise its id — either one statement per row or one statement per class that selects by key with ORDER BY on that key. The manual gives no guarantee about the order in which one statement acquires its row locks, so P8 verifies the plan of each multi-row lock statement (sort below the row-locking node) and the single-row form is used wherever that cannot be shown (HO-13). |
| LR-06 | Anchor ownership: a mutable row family is changed only by a command that holds the anchor 4.3 names for it. A command may therefore read, after locking an anchor, the rows that anchor owns and treat them as fixed until it commits. |
| LR-07 | A first-use row is created by insert-or-lock at its canonical position ([section 8](s08-09-guards-stale-state.md#8-guards-and-first-use-rows)); a command waiting inside that insert holds only locks that sort before the row. |
| LR-08 | A unique business-identity key is inserted only in the write phase, after every lock is held, in canonical table order and then key order. Each such key is covered by an anchor its competitor also locks ([section 11](s10-13-identity-failure-retry.md#11-separate-duplicates)), so the competitor waits at the anchor and then finds the committed row; a wait inside a unique index can involve only transactions that wait for nothing else. |
| LR-09 | Every inventory movement locks the product guard row (LK-13) of each product it moves, whatever its net effect on that row, before any scope, lot, reversal, serial or reservation row of that product. A drop-ship confirmation moves no stock and locks the same row as the anchor of the product's serial policy and quantity scale (ST-09). |
| LR-10 | Every command scoped to one purchase — receipt, reversal, drop-ship confirmation, closure, cancellation, charge, cost correction, return, a disbursement against it and that disbursement's contra — locks that purchase's header (LK-21). |
| LR-11 | No lock is held across slow work ([section 3](s00-03-foundations.md#3-transaction-and-isolation-model), short transactions). |
| LR-12 | A lock wait is bounded by its class's `lock_timeout` (RY-04), which is longer than deadlock detection (1 s by default), so a real deadlock is reported as `40P01` and retried instead of timing out. |

### 4.2 Lock classes

Positions are total: a class with a lower number is always locked first. LK-00 is the envelope's own key, not a business anchor; **the project row is the first anchor, at position 1**. One class is no lock of a transaction at all: the registration keys of LK-28 are taken by the registration request before its transaction opens (FU-10). The order confirms DATABASE §26 — project → correction case → unattributed-loss case and its group → product → scope → lot → serial → reservation → project line → purchase line → charge and charge share → lot cost → consumption → invoice → application source → money fact → evidence → number counter — and inserts the other lock-bearing families without changing the relative order of those eighteen.

| LK | Class | Rows and order inside the class | Anchor of |
| --- | --- | --- | --- |
| LK-00 | Command identity | `command_log` by `command_id`, by insert-or-detect (CI-03) | the intent itself; only a duplicate of the same intent can wait on it, and that duplicate holds nothing else because this is its first statement |
| LK-01 | Project | `projects` by id | project state and header; the project-owned rows that change only under it — `project_siplah_details`, draft `project_lines`, `quotation_revisions` with their lines, `commercial_confirmations`, `line_scope_cancellations`, `project_state_transitions`, `admin_requirements` with their links and waivers; every input of the completion predicates of WF-PRJ-03 |
| LK-02 | Owner set | the built-in `roles` row `OWNER` | which accounts hold the OWNER role and are active (RG-09) |
| LK-03 | User | `users` by id — the actor and every target account are one class | the account's state and credential, its `user_roles`, `user_capability_grants` and `user_company_grants` rows, the admission and withholding of its export requests (H6-04; RV-08) and the count of its uploads (H6-07) |
| LK-04 | Credential token | credential-token store rows by id (AU-11), locked by the conditional single-use update itself | one credential link |
| LK-05 | Session | `sessions` by id | one session: single-row writes by the request that owns it; deleted by `user_id` under LK-03 |
| LK-06 | Company | `companies` by id | `is_active` and the company master: taken by a command that starts new business for the company (BR-ACC-03), by a master edit and by a deactivation |
| LK-07 | Company bank account | `company_bank_accounts` by id | the duplicate-payment check of WF-FIN-03 for that account (BD-02); the account master |
| LK-08 | Master and configuration | MD and CF rows in the fixed table order parties, catalogue, locations, numbering schemes, tax configuration, document types; then id; locked in phase B, changed by the conditional `lock_version` update in the write phase | the row itself |
| LK-09 | Import | `import_batches` by id, then `import_rows` by row number | batch state; row state |
| LK-10 | Correction case | `correction_cases` by id | case state and its guard counters |
| LK-11 | Unattributed loss | `unattributed_loss_cases` by id, then `unattributed_loss_exposures` by (case, company), then `unattributed_loss_group_exposures` by (group case, company) | case counters, exposure guards and overlap-group guards |
| LK-12 | Stock count | `stock_counts` by id, then `stock_count_findings` by id | count state; finding state |
| LK-13 | Product | `stock_product_balances` by product | ON HAND, RESERVED, UNUSABLE and, through LR-09, every stock row of the product; the product's serial policy and quantity scale (ST-09) |
| LK-14 | Scope | `stock_scope_balances` by (product, location, condition) | claims, pending quantities and `version` |
| LK-15 | Lot | `stock_lot_balances` by (lot, location, condition) | lot remainder |
| LK-16 | Reversal | `stock_reversal_balances` by (reversed movement, lot, location, condition) | reversal cap |
| LK-17 | Serial | `serial_units` by (product, serial key) | serial state |
| LK-18 | Reservation | `reservations` by id | remaining quantity and state |
| LK-19 | Project line | `project_line_balances` by project line | per-line conservation |
| LK-20 | Project commercial | `project_commercial_balances` by project | the invoice cap |
| LK-21 | Purchase | `purchases` by id, then draft `purchase_lines` by id, then `purchase_line_balances` by purchase line | header state, draft lines with their tax components, line caps and posted values (LR-10) |
| LK-22 | Charge | `purchase_charge_balances` by charge, then `purchase_charge_share_balances` by (charge, line) | charge and charge-share conservation |
| LK-23 | Lot cost | `lot_cost_balances` by lot | remaining cost; the lot's cost entries and the net cost of its consumptions |
| LK-24 | Consumption | `stock_consumption_balances`: consumption-keyed rows by id, then drop-ship-line-keyed rows by id | restored, reclassified and substituted caps |
| LK-25 | Invoice | `invoices` by id, then draft `invoice_versions`, then `invoice_balances`, then `receivable_dispute_holds` and `prepayment_kuitansi_links` | invoice state, the draft version, receivable conservation, holds and Kuitansi links |
| LK-26 | Application source | the `payments` or `opening_customer_credits` row, then `application_source_balances` (payment-keyed by id, then credit-keyed by id), then `payment_applications` by id, then `deduction_settlements` by id | the payment's citation columns, capacity and refund backing, application and settlement state transitions |
| LK-27 | Money fact | `money_fact_balances` in the order disbursement, other Cash-In, expense, settlement, write-off, then id; the citation columns of `other_cash_receipts` with their row | contras, refund backing and shortfall, transfer citation |
| LK-28 | Evidence registration keys | session-level advisory locks by namespace, then key (FU-10), taken by the registration request before its transaction opens and released when it has ended | the FL-10 lookups and the first registration of a content hash or of a type-free identity |
| LK-29 | Evidence | `evidence_documents` by id, then `bank_statement_lines` by id, then `evidence_balances` (evidence-keyed, then line-keyed), then `evidence_links` by id | current version, line void, citation and expense caps, links and unlinks |
| LK-30 | Document | `documents` by id, then `document_versions` by id, then `document_renditions` by id | the chain, draft edits, issue and void transitions, rendition attempts |
| LK-31 | Number counter | `number_sequences` by (company, sequence type, period) | the next number; `retired_numbers` rows are inserted at this position |

**Every guard of DATABASE §3.1 has a position:** `stock_product_balances` LK-13; `stock_scope_balances` LK-14; `stock_lot_balances` LK-15; `stock_reversal_balances` LK-16; `stock_consumption_balances` LK-24; `serial_units` LK-17; `reservations` LK-18; `project_line_balances` LK-19; `project_commercial_balances` LK-20; `purchase_line_balances` LK-21; `purchase_charge_balances` and `purchase_charge_share_balances` LK-22; `lot_cost_balances` LK-23; `evidence_balances` LK-29; `invoice_balances` LK-25; `application_source_balances` LK-26; `money_fact_balances` LK-27; the `unattributed_loss_cases` counters, `unattributed_loss_exposures` and `unattributed_loss_group_exposures` LK-11; the `correction_cases` counters LK-10; `number_sequences` LK-31.

**Every editable or state-bearing row family has a position** — those carrying `lock_version` and those changed by a conditional transition: projects, SIPLAH details, project lines, quotation revisions, administrative requirements with their links and waivers LK-01; users and their grants LK-03; credential links LK-04; companies LK-06; company bank accounts LK-07; the other masters and configuration rows LK-08; import batches and rows LK-09; correction cases LK-10; unattributed-loss cases LK-11; stock counts and findings LK-12; serials LK-17; reservations LK-18; purchases and their draft lines LK-21; invoices, draft invoice versions, dispute holds and Kuitansi links LK-25; payment citations, applications and settlements LK-26; other Cash-In citations LK-27; evidence documents, statement lines and evidence links LK-29; documents, draft document versions and renditions LK-30. Operational rows written outside command transactions — the state changes a job makes to its own `export_requests` row and the framework's queue, batch, failed-job and cache-lock rows — are single-row conditional writes of TX-04 that hold no other lock; an access change withholds the export requests of its target inside its own transaction, under that account's row (LK-03; LR-02).

### 4.3 What an anchor fixes

Under LR-06 each family below changes only while its anchor is held, so a command that holds the anchor may read the family and rely on it.

| Anchor | Fixes until commit |
| --- | --- |
| LK-01 project | its state; its lines, pins and current pointers; the reservations of its lines; its invoices, documents, requirements, deliveries and correction cases; every completion-predicate input |
| LK-03 user | the account's active flag, credential, role, capability grants and company grants; the admission and the withholding of its export requests — a job may still end one of them by its own conditional update — and the count of its uploads |
| LK-06 company | `is_active` |
| LK-10 correction case | its state and counters and the facts cited under it |
| LK-11 unattributed-loss case | its counters and exposure rows; its group's rows |
| LK-12 stock count | its state, scopes and lines |
| LK-13 product | every ledger quantity of the product: its scope and lot rows, its reservations' total and the pending cases of its scopes. A serial's state is fixed by its own row (LK-17); while it is IN_STOCK its lot, location and condition change only through movements and are fixed here, like the product's serial policy and quantity scale |
| LK-21 purchase header | the purchase's state, lines, line guards, charges and shares, receipts, confirmations and closures, and the disbursements that cite it |
| LK-23 lot cost | the lot's cost entries and draws, and therefore the set of its consumptions and their net costs |
| LK-25 invoice | its versions, guard, applications, settlements, write-offs, holds and Kuitansi links |
| LK-26 application source | its applications, anchored settlements, claimed deductions and contras |
| LK-27 money fact | its contras, backing and shortfall |
| LK-29 evidence | its citations and its links — every command that links evidence to a record, or a requirement to evidence, locks it |
| LK-30 document | its versions, their rendition attempts and the requirement links to them |

### 4.4 Why the order cannot deadlock

1. **Row locks.** By LR-01 and LR-02 every transaction acquires its row locks in one total order. In any set of transactions waiting on each other, the one holding the highest-sorting lock is waiting for a lock that sorts higher still, which no member of the set that waits for a lower lock can hold — so no cycle exists. The check is mechanical over the declared lock sets of [section 7](s07-statement-sequences.md#7-statement-sequences): every declared set is an ascending sequence of LK positions, so every "holds X while acquiring Y" edge points forward and the edge graph is acyclic ([P6_QUALITY_GATE](../evidence/P6_QUALITY_GATE.md#static-validation) records the run).
2. **LK-00.** Only a duplicate of the same intent waits on a command's identity key, and it does so as its first statement, holding nothing.
3. **Implicit locks.** A foreign-key check takes FOR KEY SHARE, which conflicts with no lock this design requests. The statements for which PostgreSQL takes FOR UPDATE by itself wait only for inserters that wait for nothing their command holds (LR-03), and the identity row — the one row inserted before any lock — carries no foreign key.
4. **Unique keys and first-use rows** are waited for at their canonical position (LR-07) or under a common anchor (LR-08).
5. **Advisory locks** exist only at LK-28. A registration request takes its keys before its transaction, in (namespace, key) order, while it holds nothing else, and asks for no further key afterwards — so a request waiting for a key holds no row lock, and the keys can close no cycle with each other or with a row lock.
6. **One mode.** Every requested lock is exclusive, so no command holds a weaker lock and later asks for a stronger one on the same row (LR-04), and every waiter queues behind the holder.
7. **Jobs, sweeps and `GuardMaintenance`** take either one row or rows in the same order.
8. **Data-dependent sets** never acquire out of order: a required row that was not locked forces a restart ([section 5](#5-data-dependent-lock-set-protocol)).

Deadlock retry (SQ-13) remains the backstop for a defect or an implicit lock nobody foresaw. It is never the design: P9 alerts on every `40P01`, because each one is a defect to investigate (HO-25).

## 5. Data-dependent lock-set protocol

A lock set is **STATIC** when every row in it is named by the payload, by write-once references of rows so named (a line to its header, a header to its company and project, a lot to its product, a consumption to its lot, dispatch line and bearer, an application to its source and target, a return to its case), or by state that an anchor the action has *already locked* fixes (4.3) — that state is read only after the anchor is held, and the rows it names sort after the anchor. A lock set is **DATA-DEPENDENT** when at least one required row is named only by mutable state that no earlier-sorting held anchor fixes — typically a row that sorts *before* the state that names it: a project named by a consumption, a correction case named by a returned unit, a pending case named by a scope.

A data-dependent action follows exactly this protocol:

1. **Derive** the candidate lock set without locks, by ordinary reads.
2. **Lock** every currently known row in canonical order.
3. **Re-read and re-derive** the lock set under those locks.
4. **If the required set grew** — any required row is not held — release everything by rolling back and restart the whole action.
5. **Retry from the beginning** with the expanded set (the union of every set derived so far), within a bounded restart count (RY-03).

Rules of the protocol: the derivation of step 1 may be stale, which step 3 detects; holding a row that turns out not to be needed is harmless; a restart re-runs the whole envelope from phase A, authorization included, and rolls back the `command_log` row with everything else; restarts are counted separately from deadlock retries; no business row is written before step 3 succeeds (LR-02), so a restart discards only locks and first-use rows. Deadlock retry (`40P01`) is only a backstop, never the primary design. Every action — static ones too — re-reads under its locks the columns it used to derive a lock and restarts on a difference, so a reference this document treats as write-once is verified rather than trusted.

Where it applies:

| Action | What is derived without locks (step 1) | What is re-derived under locks (step 3) |
| --- | --- | --- |
| AX-34 cost correction cascade | the lots of the corrected line or charge; their consumptions, restorations, losses, allocations and unrecovered shares; the bearer projects of those; the purchase-return cases and replacement lots involved | the same sets, read after LK-21 and LK-23 are held (4.3): a consumption committed meanwhile names a new project or case → restart |
| AX-37 unattributed-loss resolution and its reversal | the case, its exposure and group rows, its causal project, the lot claims to close; for a reversal, the resolution's consumptions and their net costs | the case is still the pending one (not converted or reversed), its group rows, and the lots still holding the claims in the scope, read after LK-11, LK-13, LK-14 and LK-15 |
| SF-REVAL fan-out | every completed project whose predicate input or CALC value the action changes (AX-05, AX-29, AX-32, AX-34, AX-20, AX-33, NX-14) | the same set; one REVALIDATE transition per project, each under its LK-01 lock |
| SF-UNATTRIBUTED overlap groups and candidate sets (AX-06, AX-07) | the pending cases of each affected scope, their founder and group rows, the candidate companies and causal projects | pending cases, group membership and the candidate lots of the scope, read after LK-13, LK-14 and LK-15 are held. **The candidate snapshot is taken under the scope and lot locks**, so no movement can change the candidates after it (DIR-027; C-36) |
| AX-05, AX-29, AX-32 charge re-spread and late charge | the lots that take the re-spread or late share and the consumptions already drawn from them, with their bearer projects and the purchase-return cases of the units among them that went back to the supplier | the same, after LK-21, LK-22 and LK-23 |
| AX-18, AX-19, AX-20, AX-22, AX-23, AX-33 on a money source or fact | the source's applications and anchored settlements, the invoices and projects they touch, the refunds they back and the case that carries a shortfall or takes a residual — for AX-18 and AX-19 only the case of a refund whose shortfall an application resolves | the same, after LK-25, LK-26 and LK-27 |
| Document changes — issue, void, revision, a finished rendition (AX-12, AX-15, AX-16, AX-22, AX-31, NX-12, JB-01) | the requirements linked to the version and their projects | the same, after LK-30 is held: a link is created only under LK-30 of what it links (NX-03), so none can appear afterwards |
| NX-14 evidence replacement; NX-08 citation correction; NX-11 reversals of case movements | the records the evidence or payment is linked or applied to, and their projects; the pending case, count and finding a reversed movement belongs to | the same, after LK-26 or LK-29; after LK-13 and LK-14 for a reversed movement |

## 6. Command envelope

Every SF-CMD command — from a web request, the import pipeline, a job or any future integration — runs this envelope once per attempt (ARCHITECTURE §5). It is the template of every row of [section 7](s07-statement-sequences.md#7-statement-sequences).

| Phase | What happens | SF-CMD step |
| --- | --- | --- |
| A — prepare (outside the command's transaction) | validate the allowed fields and formats, the `command_id` included (WS-01; CI-01); compute the payload fingerprint (CI-02); **replay probe** — one read of `command_log` by `command_id`: a terminal row sends the request straight to the replay table (CI-05) before any reference is resolved. Otherwise authorize in the controller (EN-01), resolve every reference by public identifier through the scope resolver (CS-04) — a reference that does not resolve is answered as not found only after the probe has been repeated, because the command in flight may be the one that removed it — and derive the lock set by unlocked reads. Evidence registration takes its keys in a short transaction of their own (FU-10) and runs the FL-10 lookups here, in every attempt and before the command's transaction (NX-07) | 1–3, first line; 6 |
| B — lock | BEGIN; `SET LOCAL` the class timeouts (RY-04); insert the `command_log` row at LK-00 (CI-03) — if it already exists, follow the replay table (CI-05); SAVEPOINT; lock rows in canonical order from LK-01, creating first-use rows where due; once LK-03 is held, re-authorize (RV-01) **before any precondition is read**, so a denial discloses nothing | 6, 2, 3 |
| C — decide | read the clock once: that value is `recorded_at` for every row the command writes, so rows serialized by an anchor carry their lock order; if no log row existed, probe the command key of the primary fact (CI-08); re-read under the locks and re-derive the lock set ([section 5](#5-data-dependent-lock-set-protocol)); the cross-company grant or allocation authority of AZ-08 → REJECTED and routed (SQ-23); stale-state checks ([section 9](s08-09-guards-stale-state.md#9-stale-state)) → CONFLICT; preconditions and the in-transaction duplicate re-checks ([section 11](s10-13-identity-failure-retry.md#11-separate-duplicates)) → REJECTED. A refusal keeps the state it read under the locks for its answer, rolls back to the savepoint, writes the terminal state on the `command_log` row and commits | 4, 5 |
| D — write | fact inserts; the guard statements (GU-01); conditional state transitions; the audit event; `SET CONSTRAINTS ALL IMMEDIATE`. A rule violation raised here (SQ-04, SQ-05, SQ-08, SQ-10) rolls back to the savepoint and is recorded as in phase C; the state it returns is re-read after that rollback | 7, 8, 9 |
| E — commit | update the `command_log` row to COMMITTED with its result identifiers and its expiry; COMMIT | 6 |
| F — after commit | dispatch jobs and invalidate caches; a failure here never changes the outcome (SQ-21) | 10 |

- **A terminal outcome is always committed by the transaction that holds the identity** (D-CC-05). The savepoint sits between the identity row and everything else, so a REJECTED or CONFLICT command commits its `command_log` row and nothing of the action — no lock survives the rollback, no first-use row is left behind, and a duplicate of the same intent stays blocked on LK-00 until the outcome is durable. Two refusals commit further rows, because an approved rule requires them: a stale count is marked STALE with its audit event (ST-03), and a refusal routed under AZ-08 leaves the audit events QS-20 reads (SQ-23). A denial or failure that ends the whole transaction (SQ-09, SQ-11–SQ-20, SQ-22) leaves no `command_log` row and is therefore never terminal, and a driven command records no refusal at all (CI-08).
- The response is sent only after the outcome is durable. An authorization denial (SQ-09, SQ-22) rolls the transaction back and answers as AZ-11 requires; it is not an outcome of the command and is not recorded.
- The audit event of the action is written in phase D and rolled back with the writes on a refusal (DATABASE §23); only the events of those two refusals are written after that rollback.
- `business_date` is an explicit field of every command payload and is part of the fingerprint, together with the fact of whether the user chose it or the form merely prefilled it (ST-08); a retry or replay after midnight or after a month boundary therefore carries the same date and draws from the same period (GAP-016).


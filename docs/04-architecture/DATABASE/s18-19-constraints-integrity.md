## 18. Constraint catalogue

Layers: **DB** — a PostgreSQL constraint, key, trigger or privilege guarantees it alone, against every application role; **DB+APP+DQ** — PostgreSQL guarantees the CHECK on a guard row at every commit, the single posting path guarantees that the guard moves with its facts in the same transaction, and a reconciliation query proves the coupling (a fact without its guard delta is invisible to the CHECK, so it is not "DB" alone); **APP+DB** — the application decides and PostgreSQL backstops; **APP** — application rule only (not safely expressible in PostgreSQL); **P6** — needs the atomic mechanism P6 designs (locks, ordering, retry, idempotency); **DQ** — derived reconciliation query that must return no rows (run by P8 tests and a periodic operator check). No layer is claimed against the table-owning migration role, which §28 restricts operationally. Every row is also a **P8 test obligation**; rows marked ⚡ need real concurrent tests. Cross-row equations are never forced into CHECK constraints: they become guard balances (single-row CHECKs over maintained sums) or DQ checks.

| # | Invariant | Rules | Layer | Guarantee |
| --- | --- | --- | --- | --- |
| C-01 | Barcode resolves to at most one active product | BR-INV-09; AC-03 | DB | partial UNIQUE (`code_key`) WHERE active, on the normalized code; APP never auto-selects unknown/ambiguous scans |
| C-02 | One serial identity; never in stock twice ⚡ | BR-INV-09 | APP+DB, P6 | UNIQUE (`product_id`, `serial_key`); conditional state transition; IN_STOCK column CHECK |
| C-03 | Non-negative base quantities per lot, scope and product; every pending reduction backed by lot claims of its scope ⚡ | BR-INV-01/04; DIR-027 | DB+APP+DQ, P6 | CHECKs on `stock_lot_balances`, `stock_scope_balances` (`claims_qty ≥ pending_reduction_qty`), `stock_product_balances`; one net update per guard row per command; the posting path couples ledger and guards; DQ per §3.1 |
| C-04 | AVAILABLE = ON HAND − RESERVED − UNUSABLE ≥ 0 ⚡ | BR-INV-01; BR-RSV-01; AX-01/03 | DB+APP+DQ, P6 | product guard CHECK (§5.2), coupled to the ledger and reservations by the posting path and verified by DQ |
| C-05 | Reservation bounds: remaining ≥ 0, one ACTIVE per line, per-line conservation ⚡ | BR-RSV-01/02; L-04; AX-01/02 | DB (reservation row), DB+APP+DQ (line guard), P6 | `reservations` CHECK + partial UNIQUE; `project_line_balances` CHECK |
| C-06 | A supply reduction below reservations carries a human cut | BR-RSV-04; FS-09 | APP + DB+APP+DQ | product CHECK fails without CUT events; APP requires the actor's choice |
| C-07 | Stock increases only through permitted movement types; lot-less entries only on case movements; net-zero movements conserve quantity | L-15; L-36; BR-INV-02 | DB | per-row sign and lot-less CHECKs by movement type; the net-zero deferred constraint trigger per movement (§5.3), so a LOCATION_MOVE of −5/+6 cannot commit |
| C-08 | One lot per acquisition; provenance immutable and keyed to its acquisition | BR-INV-04/12 | DB | UNIQUE source references; composite keys carrying the source company to the receiving line, import row, restoration or count finding; INSERT-only privilege |
| C-09 | Quantity conversion exact; no fractional base quantity of a counted or serialized product anywhere | BR-INV-03; L-31 | DB | `CHECK (qty_base = entered_qty * ratio_to_base)`; the scale key and CHECK on every quantity-bearing fact row (§1); ±1 per serial entry; restorations only in the cited or the base unit |
| C-10 | Document and project numbers unique per company/type/period, monotonic, append-only and never reused, legacy numbers included; retired project numbers tombstoned ⚡ | BR-DOC-02; AX-15 | DB, P6 | authoritative counters never lowered, created only for seeded or post-go-live periods; UNIQUE keys; `retired_numbers`; never `MAX(number)+1`; no gapless claim |
| C-11 | Issued content, number and issue data immutable; one issued and one draft version per chain; one chain per record-bound subject; one issue per document per command | BR-DOC-01; BR-SN-01 | DB | trigger on issued rows; partial UNIQUE indexes; `supersedes_version_id` UNIQUE; UNIQUE (`issue_command_id`, `document_id`) |
| C-12 | Render never reissues | BR-DOC-03 | DB | renditions reference the version; no number or payload columns |
| C-13 | Σ invoice applications + refund-backed consumption ≤ the source's real money ⚡ | BR-FIN-03; AX-19/23 | DB+APP+DQ, P6 | `application_source_balances` capacity CHECK |
| C-14 | Applications stay within one company and one client | BR-FIN-03/07; FS-10 | DB | composite foreign keys |
| C-15 | Receivable reductions only by applications, fee/tax settlements and net write-offs; outstanding ≥ 0 ⚡ | BR-FIN-04 | DB+APP+DQ, P6 | `invoice_balances` CHECK |
| C-16 | Void or downward revision disposes every reduction | BR-CR-03; AX-22 | DB+APP+DQ | lowering `gross_value` fails the CHECK unless disposed in the same transaction |
| C-17 | Replacement named explicitly; aging inherited from the replaced invoice's current act | L-30 | DB | `replaces_invoice_id` UNIQUE + composite keys; inherited act keyed to the replaced invoice and its dates; deferred constraint trigger for the current act |
| C-18 | Evidence amount caps (settlements, expenses and charges per proof) ⚡ | L-45; BR-FIN-09/16 | DB+APP+DQ, P6 | `evidence_balances` CHECK; UNIQUE evidence identity; type bound to role (C-58) |
| C-19 | Σ Cash-In per bank statement line ≤ line; one identity per real bank line; a mistyped line voidable only once uncited ⚡ | L-22; AX-18 | DB+APP+DQ, P6 | `evidence_balances` CHECK; bank-line identity on the bank reference or running balance, never a position; citation set, then corrected only with reason; a VOIDED line proves 0 |
| C-20 | Source-bill cap: Σ charges (current amounts) and expenses citing a bill ≤ its total, also after a charge correction ⚡ | L-43; AX-29/34 | DB+APP+DQ, P6 | `evidence_balances` CHECK moved with every charge correction |
| C-21 | Charge conservation: allocated + pending + expensed = charge; any amount allocated or expensed, never both; no rounding overshoot per charge or per line share | BR-PUR-05; L-47; AX-29 | DB+APP+DQ | `purchase_charge_balances` and `purchase_charge_share_balances` CHECKs (≤); cumulative rounding capped by the share still pending; DQ proves equality once every remainder has ended |
| C-22 | One active settlement per (payment, invoice, class, type); payment + settlements ≤ claimed gross; collected tax ≤ invoice tax component; a fully contra'd payment keeps no active settlement | L-28; AX-21 | DB; DB+APP+DQ | partial UNIQUE (DB); source-balance and invoice CHECKs (DB+APP+DQ) |
| C-23 | A fee is expensed exactly once — at most its evidence — and never by manual entry | BR-FIN-09 | DB+APP+DQ + APP | evidence `expensed_amount ≤ proven_amount`; FEE_DEDUCTION rows only from the settlement posting; role-bound evidence type |
| C-24 | Loss recognized once per lost unit ⚡ | BR-INV-13; AX-30 | APP+DB, P6 | loss is a consumption bounded by the lot guard; one INITIAL attribution per consumption; serial LOST state; reclassification bounded by `stock_consumption_balances`; loss reversal only through capped restorations |
| C-25 | HPP attributed once per consumption, drop-ship line and non-stock line | BR-FIN-12; L-18 | DB | partial UNIQUE on INITIAL attributions (per cost component for direct attributions) |
| C-26 | Lot cost conservation; the zeroing draw takes the remaining cost | BR-FIN-13; L-31 | DB+APP+DQ | `lot_cost_balances` CHECK; DQ: Σ entries = remaining + net draws |
| C-27 | Restored, reclassified and substituted ≤ consumed, for warehouse consumptions and drop-ship lines (movement-less drop-ship restorations included); contras on the reversed class and open quantity ⚡ | AX-10/35/36; PX-08 | DB+APP+DQ, P6 | `stock_consumption_balances` CHECK; §6.2 contra base |
| C-28 | Cross-company attribution conservation: net allocation = net cross-company consumption; both economic roots keyed to their causes | BR-INV-05; AX-10; L-44 | DB (by construction) + DQ | overlay on consumption and cost rows; lot source keyed to its acquisition, bearer keyed to its dispatch or resolution line; cost rows keyed to their consumption and bearer; reason CHECK; DQ over bearer ≠ source rows |
| C-29 | Normal receipts + closures + drop-ship confirmations ≤ ordered; replacement receipts ≤ replacement expected, separately ⚡ | PX-03; AX-04/08/12 | DB+APP+DQ, P6 | `purchase_line_balances` CHECKs — two separate caps; a cancellation closes the whole line |
| C-30 | Delivered ≤ shipped; handed over ≤ demand; reversal lines exact and once ⚡ | AX-09; PX-08 | DB+APP+DQ, P6 | `project_line_balances` CHECK; partial UNIQUE and quantity key on reversal lines |
| C-31 | Project invoice cap ⚡ | L-16; AX-16 | DB+APP+DQ, P6 | `project_commercial_balances` CHECK |
| C-32 | Company-scope references, including document bank destinations and invoice clients | BR-ACC-01/02; GAP-006 | DB + P5 | composite foreign keys (§15) with all-or-none nullable members; the audited inventory of intentional cross-company links; P5 default deny |
| C-33 | Stale counts are never applied ⚡ | BR-INV-07; L-14 | APP+DB, P6 | typed `stock_count_scopes.basis_version` compared with the scope guard's `version` |
| C-34 | One open count finding per scope and product | AX-07 | DB | partial UNIQUE |
| C-35 | Pending unattributed quantity ≥ 0; no double found/resolved/reversed; only loss cases can be found ⚡ | DIR-026/027; AX-37 | DB+APP+DQ, P6 | generated `pending_qty_base` CHECK; kind CHECK; one reversal per resolution |
| C-36 | Recognition snapshot immutable and complete; later movements never change it; a recognition fits the claims not already pending | DIR-027 items 3/8 | DB | INSERT-only privilege; candidates and exposures only in the recognition or conversion transaction with Σ = fixed exposure (deferred constraint trigger); column grants exclude the recognition columns; recognized ≤ exposure − prior pending |
| C-37 | Assignment within each company's recognition exposure and, across overlapping cases, within the group bound; evidence resolution closes only identified lots; an attribution error is reversed exactly and once | DIR-027 items 9/10; CM-27 | DB+APP+DQ + APP | `unattributed_loss_exposures` and `unattributed_loss_group_exposures` CHECKs; UNATTRIBUTED_RESOLUTION_REVERSAL once (partial UNIQUE); `supersedes_resolution_id` UNIQUE; evidence scope by the application |
| C-38 | Force Complete carries reason, snapshots and the confirmed blocker hash | BR-PRJ-04; AX-26 | DB | CHECK on `project_state_transitions` |
| C-39 | Normal completion only with all ten predicates true at commit ⚡ | BR-PRJ-02; AX-25 | APP, P6 | predicates re-read inside the completion transaction |
| C-40 | Owner-only and ADM+ authorities | WORKFLOWS §4 | APP (P5) | capabilities; audited `authority_class` |
| C-41 | Billing once per invoice; one root act and a linear correction chain; a replacement's root act inherits the replaced invoice's current act; opening receivables carry an OPENING act | AX-17; L-30; BR-FIN-14 | DB, P6 | partial UNIQUE root act; `supersedes_act_id` UNIQUE; composite keys on the inherited invoice and dates; deferred constraint trigger; guard pointer |
| C-42 | One uncleared dispute hold per invoice | BR-FIN-08 | DB | partial UNIQUE |
| C-43 | Pre-payment Kuitansi: Σ open ≤ outstanding at issue; one link per (payment, invoice); no receipt-mode Kuitansi while a pre-payment one is open ⚡ | AX-31 | APP, P6 + DB | issue-time checks under the invoice guard for both modes; typed `kuitansi_amount`; partial UNIQUE on links; one receipt chain per (payment, invoice) |
| C-44 | Refund-backed consumption stays binding while its refund stands; a refund is fully backed at creation; backing is released only by the refund's contra; a source with a refund shortfall keeps no invoice application and no capacity ⚡ | L-46; AX-20/23 | DB+APP+DQ | `money_fact_balances` refund CHECKs; `application_source_balances` shortfall CHECK; typed exits on `payment_applications` (transition trigger) |
| C-45 | A payment contra forces disposition of applications and settlements; Σ contras per money fact ≤ the fact | AX-20/33; BR-CR-02 | DB+APP+DQ | source-balance CHECKs; `money_fact_balances` CHECKs |
| C-46 | Correction cases close only without residuals; purchase-return closure equation over posted figures; a change to a closed case's figures reopens it | BR-CR-04; L-06; L-46; AX-11/34 | DB+APP+DQ | `correction_cases` CHECKs on guard counters posted by the case's facts; audited reopen transition |
| C-47 | Duplicate migration batch and row identity | AX-28; BR-INV-12; BR-FIN-14 | DB | UNIQUE (`import_kind`, `source_sha256`); partial UNIQUE committed identity; UNIQUE `import_row_id` on created facts |
| C-48 | One effect per command, even after the command log expires | GAP-008; SF-CMD step 6 | DB + P6 | UNIQUE (`command_id`, `command_line_no`) in every command-written table; `command_log` |
| C-49 | Business uniqueness of externally identified events, without blocking their own corrections | L-22/45/49; AX-04/08/13 | DB | UNIQUE original receiving and shipment references (corrective receipts cite the original instead); one root confirmation per revision; evidence and bank-line identity |
| C-50 | No committed business_date later than today (WIB) | L-09; BR-DT-04 | APP + trigger | a CHECK cannot use the current date safely |
| C-51 | Channel locked after the first channel-dependent commit | BR-PRJ-05 | APP + trigger | `project_siplah_details.locked_at` |
| C-52 | Effective tax configuration never overlaps, globally or per company | BR-FIN-17 | DB | exclusion constraints (treatment rules keyed on `coalesce(company_id, 0)`) |
| C-53 | Append-only facts, decisions, snapshots and audit | BR-CR-02; BR-XC-03 | DB (application roles) + P9 | privileges of the application roles (§28) and model-level update guards; the table-owning role restricted operationally |
| C-54 | Deactivated company blocks new business, allows collection | BR-ACC-03 | APP | command preconditions |
| C-55 | Reversals never restore more than was removed | BR-CR-02; CM-28/33/37; AX-36 | DB+APP+DQ | `stock_reversal_balances` CHECK for lot entries and the case counters for lot-less ones; reversal type CHECKs over an all-or-none typed pair; consumption-creating movements reversed only through capped restorations; one exact DROPSHIP_ERROR_REVERSAL per movement-less drop-ship restoration |
| C-56 | A void or downward revision states a typed, existing basis; a billed entry-error void commits only with its replacement, an unbilled one needs none | L-30; AX-22; CM-23/24 | DB | `void_basis` / `revision_basis` exclusive arcs with typed keys; deferred replacement trigger; the unbilled-basis trigger |
| C-57 | Cumulative posted goods, tax and charge values are net of receipt reversals, confirmation reversals and corrections; after any cost correction Σ attributed economic cost = corrected source cost ⚡ | BR-FIN-13; CM-17/35; AX-34 | DB+APP+DQ, P6 | posted ≤ value CHECKs on `purchase_line_balances` and the share guards, moved in the correction's transaction; cost-component discriminators; DQ per §3.1 |
| C-58 | Evidence type bound to its citing role; pool evidence belongs to no company | L-45; DIR-027 | DB | composite (`evidence_document_id`, `evidence_type`) keys with role CHECKs; `evidence_scope` with all-or-none company keys on links |
| C-59 | Every correction path is representable and capped: corrective receipts, drop-ship supplier returns and confirmation reversals, superseding confirmations and closures, claimed-deduction supersession, citation correction, bank-line void | CM-02/04/15/27; L-32 | DB | typed causal links (`corrects_receiving_line_id`, `supersedes_*`), movement-less restoration kinds under the consumption cap, transition triggers |
| C-60 | Settlement states follow ACTIVE → RESIDUAL, ACTIVE → CONTRAED and RESIDUAL → CONTRAED only, each once; a residual always has an exit | CM-22/24; L-06 | DB | transition trigger with column grant; guard effects per §11.4 |
| C-61 | Every guard has one formula, its discriminators and one normal writer; drift is reported and repaired only by the audited maintenance rebuild | BR-INV-08; GAP-031 | DQ + APP + P9 | §3.1 registry; `ReconcileGuards` verify-only; `GuardMaintenance` audited under the administration role |

## 19. Referential integrity and delete/archive policy

All foreign keys are `ON DELETE RESTRICT` and keys are never updated. `ON DELETE CASCADE` exists only from a draft parent to its draft-only children (DRAFT project → its lines, DRAFT quotation revision → its lines, DRAFT invoice or document version → its lines), and even then only when no committed row references them (L-37); a purchase line's tax components may likewise be deleted and re-entered before the line's first effect (L-23). Since the application role's DELETE privilege is table-wide, the draft-only rule is enforced by a BEFORE DELETE trigger on each of those tables that rejects any row not in its draft state (§13); every other table grants the application role no DELETE at all. `TECH-021`: `command_log` is the one further exception — its expired rows are deleted by the retention purge of [CONCURRENCY_IDEMPOTENCY JB-08](../../06-api-performance/CONCURRENCY_IDEMPOTENCY/s14-17-numbering-jobs-revocation.md#15-jobs-scheduler-and-queue), through a BEFORE DELETE trigger that rejects any row whose `expires_at` has not passed. Nullable foreign keys are used only where absence has business meaning (replenishment purchase without project, company-level bearer, uncited bank line flagged for follow-up, value-only invoice line).

| Family | Deletion | Archive / end of life | Notes |
| --- | --- | --- | --- |
| Company | never | `is_active` false (BR-ACC-03); collection continues | identity assets immutable files |
| User | never | deactivated; sessions ended; grants revoked | audit keeps attribution (BR-XC-02) |
| Roles, capabilities, grants | built-ins never; grants are revoked, not deleted | revocation timestamps | grant history is audit evidence |
| Client organization, unit, address, PIC | never once referenced | inactive: not selectable for new projects | outputs keep the as-used snapshot |
| Supplier | never once referenced | inactive: blocks new purchases | open purchases continue |
| Product, product unit, barcode | never once referenced | inactive: no new demand/purchases; stock stays countable | barcode rows deactivate, freeing the code |
| Unit, location | never once referenced | inactive | location moves remain valid history |
| Project | only a DRAFT without committed records (L-37) | CANCELLED / COMPLETED states; never deleted after activation | number never reused |
| Project line | draft only | cancellation or re-plan split | pins are immutable |
| Quotation revision | DRAFT only | REJECTED / derived EXPIRED | never voided |
| Commercial confirmation, closures, cancellations, waivers | never | superseding decision | L-32 |
| Purchase, lines, charges | never | CANCELLED header; remainder closure | PO documents voided with a cancellation |
| Receiving, lots, movements, ledger entries | never | reversal movements | ledger is permanent |
| Reservation | never | RELEASED / CONSUMED | events keep history |
| Dispatch, consumption, restoration | never | restorations and reversals | allocation follows |
| Delivery, handover, drop-ship confirmation | never | reversal lines, closures, contras | |
| Invoice and versions | DRAFT versions only | VOIDED / SUPERSEDED | number retired |
| Payment, other Cash-In, disbursement, expense, settlement | never | contra rows | BR-CR-02 |
| Payment application | never | SUPERSEDED / RELEASED | the mutable layer keeps every state |
| Write-off, dispute hold | never | supersession / clearing | Owner-only write-off |
| Document, version | DRAFT versions only | SUPERSEDED / VOIDED | |
| Rendition | never | new attempts | artifacts are derived |
| File object | only an upload never linked to any record, after the P9 retention window | immutable bytes | orphan cleanup is P9 |
| Evidence document and versions | never once linked | new version on replacement; links may be unlinked with reason | |
| Admin requirement | before satisfaction (EDIT) | REMOVED (Owner-only for required non-waivable items) | |
| Correction case | never | CLOSED | |
| Import batch and rows | never after commit; an aborted batch's rows remain as evidence | ABORTED | |
| Audit and security events | never by any application role | retention decided in P9 | |


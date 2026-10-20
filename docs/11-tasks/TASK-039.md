# TASK-039 — Receiving (Barang Masuk)

Status: REVIEW | Updated: 2026-10-20 | Owner: Planning

Written by WF-PC5-W's writer of bundle W1 of run PC-5 on 2026-10-19 and 2026-10-20 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99 — a REVIEW draft of P11, approved, or not, with the [Task plan](TASK_PLAN.md) at OG-4. **No Task starts before the Owner's execution authorization at OG-4.** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** TODO

**RISK CLASS:** HIGH

**EXECUTOR:** AGENT

**OBJECTIVE:**

Build receiving, *Barang Masuk* (WF-INV-01): the worklist PG-021 and the scan-led PG-022 *Terima Barang*, where each purchase line is received by its own command (AX-04; ROUTE-181) — the arrived quantity counted as usable to a rack, damaged-accepted straight into UNUSABLE or rejected at the door, exactly one lot per event with its source company and actual cost, serials registered, the supplier's delivery reference and evidence kept, and for a project-linked line the same-action reservation —; the optional Nota Terima Barang, DOC-12 (ROUTE-182); and the receipt's reversal of unconsumed quantity with its charge re-spread and reservation cut (AX-05; ROUTE-183), with the corrective receipts of CM-02 and CM-04.

**WHY THIS EXISTS:**

Receiving is where physical stock and its provenance begin: every acquisition creates exactly one lot carrying its source company and actual cost, damaged or rejected goods never become available, and the record traces what arrived against what was bought — the receive step of the golden journey (CAP-06; AC-06; AC-20; AC-22; O-02; OS-02; OWN-04; OWN-05; BR-INV-01; BR-INV-04; L-13). A receipt on a weak connection must take effect once (AC-16; REF-058), its purchase line's guard must hold against a racing cancellation or closure (architecture challenge 2a), and a mistaken receipt is reversed only while its units are unconsumed (CM-02; AX-05). The stock views, dispatch, adjustments, purchase returns and the opening import (TASK-040–TASK-042, TASK-044, TASK-057) build on the lots it creates.

**DEPENDS ON:**

- TASK-029 — Document engine — drafts, issue, revision, void, renditions and the composer
- TASK-031 — Correction cases
- TASK-037 — Purchases — project and replenishment purchases
- TASK-038 — Reservations

**BLOCKS:**

- TASK-040 — Stock views, stock history and movement reversal
- TASK-041 — Dispatch (Barang Keluar) and inter-company allocation
- TASK-042 — Condition, location moves, adjustments and losses
- TASK-044 — Purchase return to supplier
- TASK-057 — Opening import — preparation, dry run, sign-off and commit

**PARALLEL SAFE:** NO — it creates the lots and writes the stock ledger and its product, scope, lot and serial guards (LK-13–LK-17) and the purchase-line guard (LK-21) that every later inventory Task reads

**IN SCOPE:**

- the RECEIPT and RECEIPT_REVERSAL movements of DATABASE §5.3 through `InventoryPosting`, its only normal writer (ARCHITECTURE §7): the `stock_movements` header and its entries, `receiving_lines` — entered quantity, unit, ratio and base quantity, the usable, damaged and rejected split summing to it, the receipt kind and the supplier's delivery reference —, `inventory_lots` of kind RECEIPT keyed to its receiving line with an opaque, generated lot code (FU-13; PJ-23), `serial_units`, and the product, scope and lot guards, each guard row once per command (GU-01); the lot's cost through CST (TASK-036) and the purchase-line guard through `PurchasePosting` (TASK-037)
- ROUTE-181 with INT-181, "Catat Barang Masuk" (AX-04), one command per purchase line, each with its confirmation (IP-08) and its own answer (IP-19): an OPEN purchase's warehouse line with a remainder; the product identified by scan or chosen from the purchase's lines (J-INV-01; IP-15) — the scan field's exact lookup is ROUTE-282 with INT-006, INT-282 and SF-SCAN, built in this Task (below), while the command itself re-reads each serial's state under lock (C-02) —; the arrived quantity split into *Layak pakai* to a rack, *Rusak diterima* straight into UNUSABLE and *Ditolak* recorded on the line as a receiving discrepancy with no stock event, the remainder staying open, closed or returned (L-13); exactly one lot carrying the purchase's company as its source, the purchase line and the actual unit cost from SF-CHARGE at the line's cumulative target (BR-INV-04; DATABASE §6.1); serials registered for a serialized product, each unique while in stock (BR-INV-09); the supplier's delivery reference; evidence uploaded and linked (IP-16; SF-EVIDENCE; TASK-030)
- the receipt kinds of `receiving_lines`: ORIGINAL; SUPPLEMENT — CM-04's missing units of the same delivery, citing the receipt it completes —; RE_RECEIPT — CM-02's correct receipt after a reversal, citing the reversed receipt —; REPLACEMENT — goods replacing a purchase return, counted only against the separate replacement cap with REPLACEMENT_BASIS from its OPEN return case, the expectation itself recorded by TASK-044 (C-29; C-59; DATABASE §4.7)
- the same-action reservation offer "Reservasi untuk Proyek Ini" on a project-linked line: a quantity of the usable units, at most the line's unreserved confirmed demand ("Maksimal {v} unit"), sent with the receipt as one command through TASK-038's reservation service, needing `stock.reserve` (AX-04; BR-RSV-03; D-UX-12); no offer on a still-open purchase of a completed or cancelled project, whose receipt goes to the free pool (WF-PRJ-03 residual commands)
- PG-022 with NAV-022 and ROUTE-022 (SCR-21's task): the receiving projection — purchase number, supplier name, project link, product, ordered, received and remaining quantities, never a price, tax, charge or cost (PJ-18) —; on the phone the lines as a checklist with the scan field fixed at the bottom, each scan opening its line and each line recorded by its own command (D-UX-11; UXS-35), on the desktop the lines on the left and the current line on the right with the same scan field (J-INV-01); "Cari Produk" filtering the purchase's own lines (ROUTE_CONTRACTS §4); the progress list ("0 baris tercatat · 4 baris tersisa"), the summary after the last line, then PG-007 (NAV-022); an unknown outcome on a weak connection freezing the line's inputs with "Coba Lagi" under the same `command_id` (IP-04)
- PG-021 with NAV-021 and ROUTE-021 (SCR-21's worklist): the open warehouse remainders of OPEN purchases of the companies in scope (QS-05; PJ-18), grouped, each group with its own error and retry, leading to PG-022 of the purchase chosen; "Muat Lagi" (IP-18)
- ROUTE-182 with INT-182, "Terbitkan Nota Terima Barang" (DOC-12) per receiving event: its draft created or saved again from the recorded receipt by ROUTE-221 and opened on PG-036 — on the phone Edit → Preview → Finalize with the floor step (DC-11; AN-6) —, issued after IP-08's confirmation (`DIR-081` OB-02; TASK-029); INV's `DocumentSubjectProvider` for DOC-12 — the receiving event as subject, a committed receipt as precondition, never a fictitious receipt, the supplier's delivery reference printed (WF-DOC-01; PJ-03; PJ-15)
- ROUTE-183 with INT-183, "Balikkan Penerimaan" on PG-016 — and on PG-020 once TASK-040 exists — (AX-05; `stock.reverse`, ADM+): the reason (IP-09); only the lot's unconsumed quantity offered, whole (CM-02) or the excess (CM-03), consumed quantity showing the restoration needed first or "Buka Kasus" (TASK-031); RECEIPT_REVERSAL entries citing the receipt, the reversed units' charge share re-spread as never received in the same action (SF-CHARGE; TASK-036), the serials NEVER_RECEIVED, the purchase-line guard lowered, the IP-20 cut where usable supply would fall below the reservations (TASK-038), and a closed case whose figures change reopened (ADMIN_FLOW §11)
- units damaged at receipt of a project-linked line keep that project as their causal project (L-38): an input of WF-PRJ-03's predicate 8 — no unresolved unusable units whose causal project is this one —, resolved by TASK-042's outcomes and read by TASK-055
- ROUTE-282 with INT-006 and INT-282, `INV ScanLookup` (`stock.view`, the pool, S1) — moved here from TASK-059 in Part 4, so that this page and every warehouse page after it scan through it —: the exact lookup of a scanned code under the scanner's burst-sized limiter, separate from search (QB-06; WS-12) — the product, lot or serial in place; an unknown code MSG-25 with "Daftarkan Barcode" for `products.edit`, nothing created; an ambiguous code MSG-37 with the candidates for the user's choice; a serial that does not fit the operation MSG-36, its state in words (SF-SCAN; BR-INV-09)
- the scan field's (PT-20) shared behaviour for every page that hosts one — PG-019, PG-022, PG-024, PG-026, PG-027, PG-028, PG-034 (IA §10), each page placing it in its own Task, this one first on PG-022 —: one scan target saying "Siap Memindai", focused on entering the step and after each scan; Enter in it only looks up and adds or selects; a burst outside a field that accepts scans discarded with DESIGN_SYSTEM §13.2's notice; a serial scanned twice in one command counted once; quantity items scanned once with their quantity entered; with no scanner the same field taking a typed SKU, "Cari Produk" beside it; feedback in text and by a non-colour cue in a polite live region, errors assertive (IP-15; UXI-34; DESIGN_SYSTEM §13.2); on the phone fixed at the bottom above the action bar (D-DS-08)

**OUT OF SCOPE:**

- the purchase and its remainder closure (TASK-037); the costing rows (TASK-036); the reservation service and the cut block (TASK-038); PG-020's stock history and movement reversal (TASK-040); losses of units damaged at receipt (TASK-042); counts (TASK-043); purchase returns and the replacement expectation (TASK-044); drop-ship confirmation — a drop-ship line is never received (TASK-046; BR-INV-11)
- barcode registration from a scan, "Daftarkan Barcode" (TASK-027); evidence upload and registration (TASK-030); scanning into *Cari* (TASK-059); the document engine and PG-036 (TASK-029); opening lots (TASK-057); QS-03 and QS-05 on the home pages (TASK-058)
- a likely-duplicate warning on a receipt: INFORMATION_ARCHITECTURE §8.3 lists IP-05 among PG-022's hosts, while CONCURRENCY_IDEMPOTENCY BD-13 and INT-181 give AX-04 none — its duplicate is BD-01's identity —, so none is built here

**REFERENCES:**

- [WORKFLOWS §5.3](../03-workflows/WORKFLOWS/s05-03-inventory-purchasing.md#wf-inv-01--receiving-barang-masuk-floor) WF-INV-01; [§6](../03-workflows/WORKFLOWS/s06-07-subflows-partial.md#6-reusable-subflows) SF-SCAN, SF-CHARGE, SF-EVIDENCE, SF-RSV-CUT; [§7](../03-workflows/WORKFLOWS/s06-07-subflows-partial.md#7-partial-processing-rules) PX-01–PX-08; [§8](../03-workflows/WORKFLOWS/s08-09-corrections-indivisible.md#8-correction-reversal-and-cancellation-matrix) CM-02–CM-04; [§9](../03-workflows/WORKFLOWS/s08-09-corrections-indivisible.md#9-indivisible-business-actions) AX-04, AX-05; [§12](../03-workflows/WORKFLOWS/s10-14-signals-decisions-traceability.md#12-planner-determined-level-1-decisions) L-03, L-06, L-07, L-13–L-15, L-31, L-35, L-36, L-38, L-40, L-42, L-44, L-48; [§5.5](../03-workflows/WORKFLOWS/s05-04-05-fulfillment-documents.md#wf-doc-01--generated-document-lifecycle-desk) DOC-12 in WF-DOC-01's table; [WF-PRJ-03](../03-workflows/WORKFLOWS/s05-07-08-completion-migration.md#wf-prj-03--project-completion-penyelesaian-desk) predicate 8 and the residual commands
- [BUSINESS_RULES](../02-domain/BUSINESS_RULES.md#inventory-inv) BR-INV-01–BR-INV-13; [BR-RSV-03](../02-domain/BUSINESS_RULES.md#smreservation); [§5](../02-domain/BUSINESS_RULES.md#5-calculation-semantics) CALC-01, CALC-03, CALC-09, CALC-11; [§7](../02-domain/BUSINESS_RULES.md#7-forbidden-shortcuts) FS-01, FS-05
- [V1_SCOPE](../01-product/V1_SCOPE.md#must-ship) CAP-06; [DOC-12](../01-product/V1_SCOPE.md#selectable-document-catalog); [ACCEPTANCE_CRITERIA](../01-product/ACCEPTANCE_CRITERIA.md#product-acceptance-contracts) AC-06, AC-16, AC-20, AC-22; [OS-02](../01-product/ACCEPTANCE_CRITERIA.md#operational-success-and-go-live-evidence); [PRODUCT_OVERVIEW](../01-product/PRODUCT_OVERVIEW.md#problems-and-operational-outcomes) O-02; [REFERENCE_COVERAGE](../01-product/REFERENCE_COVERAGE.md#pdf-capability-inventory) REF-021–REF-030, REF-058; [OWN-04, OWN-05](../01-product/REFERENCE_COVERAGE.md#final-owner-decision-coverage)
- [DATABASE §4.7](../04-architecture/DATABASE/s04-07-inv.md#47-inv--inventory) `receiving_lines`, `inventory_lots`, `serial_units`, `stock_movements`; [§5.2](../04-architecture/DATABASE/s05-inventory.md#52-ledger-plus-guard-balances); [§5.3](../04-architecture/DATABASE/s05-inventory.md#53-movement-catalogue-and-sign-discipline) RECEIPT, RECEIPT_REVERSAL; [§5.4](../04-architecture/DATABASE/s05-inventory.md#54-lots-and-lot-claims); [§6.1](../04-architecture/DATABASE/s06-lot-cost-hpp.md#61-the-chain); [§8](../04-architecture/DATABASE/s07-10-representations.md#8-serial-representation); [§18](../04-architecture/DATABASE/s18-19-constraints-integrity.md#18-constraint-catalogue) C-01–C-04, C-07–C-09, C-24, C-28, C-29, C-33, C-47, C-49, C-59, C-61; [§25](../04-architecture/DATABASE/s25-28-transactions-handoffs.md#25-transaction-boundary-map) AX-04, AX-05
- [ARCHITECTURE §3](../04-architecture/ARCHITECTURE.md#3-modules) the tiers, `DocumentSubjectProvider`; [§7](../04-architecture/ARCHITECTURE.md#7-posting-services-and-guards) `InventoryPosting`, `PurchasePosting`
- [CONCURRENCY_IDEMPOTENCY §7.1](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s07-statement-sequences.md#71-lock-sets-of-ax-01ax-37) and [§7.2](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s07-statement-sequences.md#72-checks-effects-and-outcomes-of-ax-01ax-37) AX-04, AX-05; [§5](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s04-06-locks-envelope.md#5-data-dependent-lock-set-protocol); [§8](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s08-09-guards-stale-state.md#8-guards-and-first-use-rows) FU-01, FU-02, FU-04, FU-05, FU-09, FU-13; [§9](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s08-09-guards-stale-state.md#9-stale-state) ST-09; [§11](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s10-13-identity-failure-retry.md#11-separate-duplicates) BD-01, BD-13, BD-18; [§18](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s18-scenarios.md#18-mandatory-concurrency-and-failure-scenarios) M-04, M-05, M-10, M-30, M-35, M-36
- [PERMISSIONS_MATRIX §4](../05-security/PERMISSIONS_MATRIX.md#4-capability-catalogue) `stock.receive`, `stock.reserve`, `stock.reverse`, `documents.issue`; [§5](../05-security/PERMISSIONS_MATRIX.md#5-workflows-4-mapping) W4-13, W4-18; [§6](../05-security/PERMISSIONS_MATRIX.md#6-company-scope-rules) CS-03–CS-05, CS-07; [§7.2](../05-security/PERMISSIONS_MATRIX.md#72-table-classification) rows 39, 40, 44, 45, 48–52, 57–59; [§7.3](../05-security/PERMISSIONS_MATRIX.md#73-special-projections) PJ-01–PJ-03, PJ-15, PJ-18, PJ-23
- [ROUTE_CONTRACTS](../03-workflows/ROUTE_CONTRACTS.md#warehouse--receiving-reservations-dispatch-counts-adjustments-and-discrepancies-inv-pg-018pg-029) ROUTE-181–ROUTE-183; [§2](../03-workflows/ROUTE_CONTRACTS.md#2-page-views--route-001route-065) ROUTE-021, ROUTE-022; [§4](../03-workflows/ROUTE_CONTRACTS.md#4-visible-controls-without-a-route-of-their-own) "Cari Produk"; [INTERACTION_CONTRACTS](../03-workflows/INTERACTION_CONTRACTS.md#warehouse--receiving-reservations-dispatch-counts-adjustments-and-discrepancies-inv-pg-018pg-029) INT-181–INT-183; [§2](../03-workflows/INTERACTION_CONTRACTS.md#2-shared-interactions--int-001int-020) INT-006, INT-013, INT-017
- [NAVIGATION_CONTRACTS §2.3](../07-ux-design/NAVIGATION_CONTRACTS.md#23-purchasing-and-warehouse) NAV-021, NAV-022; [INFORMATION_ARCHITECTURE §8.3](../07-ux-design/INFORMATION_ARCHITECTURE.md#83-purchasing-and-warehouse) PG-021, PG-022; [§10](../07-ux-design/INFORMATION_ARCHITECTURE.md#10-search-lookup-and-scanner-entry-points)
- [ADMIN_FLOW §10.3](../07-ux-design/ADMIN_FLOW/s10-a-journeys.md#103-inventory-and-purchasing) J-INV-01; [§4](../07-ux-design/ADMIN_FLOW/s01-04-foundations.md#4-design-decisions) D-UX-11, D-UX-12; [§11](../07-ux-design/ADMIN_FLOW/s11-13-corrections-numbering-import.md#11-corrections-and-reversals) CM-02–CM-04; [IP-15](../07-ux-design/ADMIN_FLOW/s06-09-session-forms-lists.md#ip-15--scanner); [IP-16](../07-ux-design/ADMIN_FLOW/s06-09-session-forms-lists.md#ip-16--evidence-upload); [IP-19](../07-ux-design/ADMIN_FLOW/s05-command-model.md#ip-19--several-commands-on-one-screen); [IP-04](../07-ux-design/ADMIN_FLOW/s05-command-model.md#ip-04--failed-the-unknown-outcome-and-retry); [§15](../07-ux-design/ADMIN_FLOW/s14-15-messages-scenarios.md#lists-search-scanner-and-exports) UXS-34, UXS-35; [§14](../07-ux-design/ADMIN_FLOW/s14-15-messages-scenarios.md#14-message-catalogue) MSG-25, MSG-36, MSG-37
- [DESIGN_SYSTEM §13](../07-ux-design/DESIGN_SYSTEM.md#13-keyboard-and-scanner); [§14.2](../07-ux-design/DESIGN_SYSTEM.md#142-inventory); [LOCALIZATION §6.6](../07-ux-design/LOCALIZATION.md#66-pages--every-page-of-information_architecture-8) `pages.pg_021`, `pages.pg_022`
- [TEST_STRATEGY §5](../08-testing/TEST_STRATEGY.md#5-domain-and-data-integrity) the C- rows; [§6](../08-testing/TEST_STRATEGY.md#6-concurrency-and-failure-proofs); [§7](../08-testing/TEST_STRATEGY.md#7-browser-end-to-end-tests-and-critical-journeys) CJ-01, CJ-07; [§12](../08-testing/TEST_STRATEGY.md#12-devices-and-uat) the scanners; [SECURITY_TEST_MATRIX](../08-testing/SECURITY_TEST_MATRIX.md#3-the-matrix) SM-01, SM-04, SM-25; [PERFORMANCE_TARGETS](../08-testing/PERFORMANCE_TARGETS.md#2-the-query-budgets) QB-05, QB-06; [§3](../08-testing/PERFORMANCE_TARGETS.md#3-the-measured-targets) PFM-01, PFM-02, PFM-20
- [GAP_REGISTER](../00-governance/GAP_REGISTER.md#gap-009--dispatch-locks-alone-do-not-protect-stock-counts-and-serial-scans) GAP-009; [GAP-020](../00-governance/GAP_REGISTER.md#gap-020--mobile-first-and-existing-usb-scanners-do-not-prove-device-compatibility)
- Architecture challenge — the sequence 2a of the [P11 gate record](evidence/P11_QUALITY_GATE.md#whole-system-architecture-challenge-sp-98), their assertions carried into this Task's acceptance criteria and tests
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (118): O-02; CAP-06; DOC-12; AC-06; AC-16; AC-20; AC-22; OS-02; REF-021–REF-030; REF-058; IMG-03–IMG-04; IMG-06; OWN-01; OWN-04–OWN-05; BR-INV-01–BR-INV-13; CALC-01; CALC-09; CALC-11; FS-01; WF-INV-01; SF-SCAN; PX-01–PX-08; CM-02–CM-04; AX-04–AX-05; L-03; L-06–L-07; L-13–L-15; L-31; L-35–L-36; L-40; L-42; L-44; L-48; ROUTE-021–ROUTE-022; ROUTE-181–ROUTE-183; ROUTE-282; INT-006; INT-181–INT-183; INT-282; C-01–C-04; C-07–C-09; C-24; C-28–C-29; C-33; C-47; C-49; C-61; BD-01; BD-18; M-04–M-05; M-10; M-30; M-35–M-36; HO-10; QB-06; PG-021–PG-022; SCR-21; UXI-34; D-UX-12; IP-15; J-INV-01; UXS-34–UXS-35; NAV-021–NAV-022; GAP-009; GAP-067

**EXPECTED MODULES / FILE AREAS:**

- the INV module's ReceiveGoods and ReverseReceipt actions, `InventoryPosting`'s receipt and receipt-reversal paths, the lot-code generator, INV's `DocumentSubjectProvider` for DOC-12, the receiving query classes and `ShowReceivingWorklist` and `ShowReceiveGoods`; `resources/js/Pages/INV` for PG-021 and PG-022 with the scan field and line sheet in `resources/js/features/inv`; `tests/Feature/INV`, `tests/Concurrency/` and `tests/Reconciliation/`

**BUSINESS RULES:**

- L-13 with BR-INV-01 and BR-INV-09: three receiving outcomes — usable stock at a rack, damaged-accepted straight into UNUSABLE and never through AVAILABLE, rejected with no stock event —; every stock event names its lot and, for serialized goods, its serials, usable and unusable tracked per lot and serial; a serial is unique while in stock and never received twice while present
- BR-INV-04 and BR-INV-08: every receipt creates exactly one lot carrying its source company, its purchase line and its actual cost (SF-CHARGE; BR-PUR-05), lot consumption ≤ lot quantity, every derived quantity recomputable from the ledger (OWN-05; REF-022; REF-024; REF-026)
- PX-01–PX-08 with CALC-03: any number of receiving events per line, the remainder derived until received or closed; a receipt above the remainder is refused until the purchase is amended by a supplementary purchase (PX-03); a committed receipt is never undone by a later failure (PX-05); another authorized Admin continues the work (PX-07); PX-06's example — 100 bought, 60 received of which 5 damaged-accepted, 40 open
- BR-RSV-03 with D-UX-12: a purchased shortage becomes reservable at its receipt, offered inside the same command for a project-linked line (AC-22's mixed supply)
- CM-02–CM-04 with AX-05: a wrong product or serial, or an excess, is reversed only for the lot's unconsumed quantity, its charge share re-spread as never received; consumed quantity first needs the dependent restoration or a case; missing units are a further receipt of the same delivery
- BR-INV-02, BR-INV-11, FS-05 and L-40: receiving is a legitimate increase (L-15; C-07), and drop-ship and services create no warehouse event — a drop-ship line is never received, its confirmation and delivery TASK-046's
- BR-INV-03 and L-31: quantities in base units with the entered unit and the receiving line's ratio, exact and never rounded (C-09; REF-024); L-35: a changed serial policy or scale meets the receipt as a CONFLICT (ST-09)
- the inventory rule set shared with TASK-038 and TASK-040–TASK-043, as it reaches a receipt: L-03 and L-44 — a receipt creates no allocation, the lot's source company being its purchase's —; L-14 — a receipt into a counted scope makes that count stale (C-33) —; L-36, L-42 and L-48 govern moves, unidentified units and substitutions of the lots a receipt creates; L-07 — the restoration a consumed receipt needs first cites its consumption —; BR-INV-05–BR-INV-07, BR-INV-10, BR-INV-12 and BR-INV-13 govern allocations, returns, counts, restock advice, opening lots and losses of these lots; CALC-01's AVAILABLE rises by the usable units only; CALC-09 and CALC-11 are unchanged by a receipt, whose cost sits in the lot until it is drawn (BR-FIN-12; L-18); FS-01: no stored workflow status; L-06, mapped here through its ground BR-INV-13, governs invoice reductions and has no receiving effect
- BR-DOC-04 and BR-XD-04: the Nota Terima Barang renders the committed receipt, never a fictitious one, and issuing it changes no stock
- REF-021–REF-030 as receiving meets them: one shared warehouse (REF-021); source attribution starting at the lot (REF-022); the rack of every received unit (REF-023); partial and damaged receiving with scans, serials and evidence (REF-024); a safe, reasoned reversal (REF-025); the ledger and its balances (REF-026); counts, restock advice, returns and drop-ship reading these lots and never faked by a receipt (REF-027–REF-030); opening lots arrive only through the import's identity, never as receipts (BR-INV-12; C-47)

**ARCHITECTURE RULES:**

- INV is tier 5 (ARCHITECTURE §3): a receipt updates the purchase-line guard through PUR's `PurchasePosting` and the lot's cost through CST's `CostPosting`, while PUR never calls INV; the stock ledger and its guards are written only by `InventoryPosting` through `GuardUpdate`, one statement per guard row per command (§5.2; GU-01; GU-07)
- one lot per receiving event, its provenance keyed to the receiving line by composite key and never changed (C-08); the lot cites its source, never the reverse (DATABASE §4.7)
- the Nota is a layout of the receiving event issued through TASK-029's engine and template (AX-15); INV adds its subject and precondition only

**SECURITY / PERMISSION REQUIREMENTS:**

- ROUTE-021, ROUTE-022 and ROUTE-181 `stock.receive` (ADM, W4-13; prerequisite `stock.view`), the same-action reservation also `stock.reserve`; ROUTE-182 `documents.issue` with the operational family's view capability; ROUTE-183 `stock.reverse` (ADM_PLUS, W4-18), a cut also `stock.reserve` (PERMISSIONS_MATRIX §4; IP-20)
- every control absent without its capability (IG-01; NV-08), "Daftarkan Barcode" only with `products.edit` (IP-15); the purchase, line, product, rack, serials and evidence resolved through the scope resolver and the lot, its source company, cost and state derived, never taken from the payload (CS-04; DP-03; WS-02)

**COMPANY SCOPE AND FIELD PROJECTION:**

- the command's company is the purchase's (CS-03); the lot's source company is that company, keyed to the receiving line (C-08; C-28; OWN-05); racks, physical balances and serials are pool records, read with `stock.view` (CS-05)
- the receiving projection: PG-021, PG-022 and the receiving lines show `stock.receive` holders in scope the purchase number, supplier name, project link, product and quantities, never a price, tax, charge or cost (PJ-18; rows 39, 40, 44, 45, 52); the supplier's delivery reference with `projects.view` or `stock.receive` (PJ-03; row 52); lot cost CV(source c) only (rows 73, 74; PJ-02); a new lot's code opaque (PJ-23)
- PG-021 lists the purchases of companies in scope, each labelled with its company, the scope inside the query (CS-07; CS-08; PF-13); DOC-12's payload in the operational family within the receipt's company (PJ-15)

**CONCURRENCY / IDEMPOTENCY:**

- AX-04: STATIC, LK-00, LK-01, LK-03, LK-10, LK-13, LK-14, LK-15, LK-17, LK-18, LK-19, LK-21, LK-22, LK-29, with first-use rows FU-01, FU-02, FU-04, FU-05, FU-09 and FU-13 — LK-18 and LK-19 only with the reservation offer, LK-10 only for a replacement receipt; re-read under lock: the purchase OPEN, the cap of the receipt's kind on the line guard, the serial not in stock, the cited receipt of a corrective kind, a replacement's return case OPEN, the product's policy (ST-09); identity BD-01 — a second ORIGINAL with the same supplier delivery reference on the line is CONFLICT; refusals C-29, C-02; retry class A (CONCURRENCY_IDEMPOTENCY §7.1, §7.2)
- AX-05: DATA-DEPENDENT, LK-00, LK-01, LK-03, LK-10, LK-13, LK-14, LK-15, LK-17, LK-18, LK-19, LK-21, LK-22, LK-23 under §5's protocol — the projects of the cut reservations, of the consumptions taking a re-spread draw and of the return cases involved derived, locked and re-derived; re-read: the lot's unconsumed quantity in the scope, the charge's remaining received quantity, the cuts satisfying C-06; identity the lot; refusals C-03, C-06, C-21, AZ-08; retry class B
- a receipt racing its line's cancellation or remainder closure meets it at LK-21: one commits, the other REJECTED by C-29 or CONFLICT on the purchase's state (M-36; architecture challenge 2a); concurrent partial receipts commit until the cap and then REJECTED, duplicate ORIGINAL receipts CONFLICT (M-35); a serial scanned twice in a command counts once and two commands receiving one serial leave one success (M-05; BD-18; C-02); concurrent first use of a scope or lot creates one row (M-30)
- one `command_id` per line (CI-01): a lost answer retried — also after re-authentication — returns the recorded outcome and never posts twice (CI-05; M-10; AC-16)

**AUDIT:**

- every receipt and reversal is one business audit event in its transaction with the individual actor, the records affected and, for a reversal, its reason (BR-XC-01 — stock is a critical area; BR-CR-01; LG-01); a reversal is ADM_PLUS and reaches the Owner review queue (AZ-10; QS-14)
- the movement keeps `recorded_by`, `business_date`, `reason` and the command identity, the receiving line its kind, cited receipt and delivery reference, and a RECEIPT_REVERSAL names the receipt it reverses (DATABASE §4.7); ledger rows are append-only, never edited (C-53); the movement shows in the stock history (TASK-040; PJ-03)

**DATABASE IMPACT ASSESSMENT:**

- Schema change: NONE — uses the tables of TASK-005–TASK-009; a missing column is an executor discovery (AGENT_OPERATING_MODEL)
- Migration required: NO
- Existing data transformation: NONE
- Direct production DB access: FORBIDDEN
- Data-loss risk: NONE KNOWN

**MODULARITY / EXTENSIBILITY:**

- Module boundary affected: INV, tier 5 — calling PUR's `PurchasePosting`, CST's `CostPosting` and TASK-038's reservation service, implementing DOC's `DocumentSubjectProvider` for DOC-12 (ARCHITECTURE §3, §7)
- Stable core kept provider-neutral: YES
- External/vendor-specific code isolated: YES — the scanner is a keyboard wedge, no device library (IP-15)
- Replaceable adapter/contract required: NO
- Provider lock-in risk: NONE beyond the Owner's baseline stack
- Future extension impact: MEDIUM — every later inventory Task reads the lots and ledger it writes
- Speculative abstraction avoided: YES — no WMS, putaway rules or camera scanning (REF-023; V1_SCOPE D-05)

**AUTH IMPACT:**

- Authentication change: NONE
- Authentication implementation: LARAVEL_NATIVE — decided once ([EXECUTION_CONTEXT](EXECUTION_CONTEXT.md)), reused, never redesigned
- Auth profile: N/A — this Task does not touch authentication
- Google sign-in: N/A — not touched
- Google registration behavior: N/A — not touched
- Google credentials owner action required: NO
- Authorization change: NONE
- Provider/library-specific leakage into domain: NO

**COST IMPACT:**

- New recurring cost: NONE
- Paid third-party service: NO
- Free/open-source alternative: N/A — nothing paid is considered
- Estimated additional monthly production cost: 0
- Owner approval required: NO

**MCP SESSION PROFILE:**

- Required MCP servers: none — [TOOLCHAIN](../00-governance/TOOLCHAIN.md#mcp-servers) records none installed
- MCP servers that should remain OFF: every server not required
- Native CLI/package preferred over MCP: YES — the framework's and package managers' own commands
- Post-Task deactivation/cleanup: none

**TOOL REQUIREMENTS:**

- Context7: REQUIRED — PostgreSQL deferred constraint triggers and partial UNIQUE keys, row locks in the canonical order, `INSERT … ON CONFLICT` for first-use rows, at the installed version
- Graphify: OPTIONAL — once dispatch and adjustments read the lots
- Browser E2E: REQUIRED — J-INV-01 on the phone and the desktop with the scanner flow (CJ-07), the golden flow's receive step (CJ-01) and the Nota's draft on PG-036
- Other: a keyboard-wedge scanner simulation in CI and the real scanners at Layer 3 (TEST_STRATEGY §12; GAP-020); a two-connection harness (TASK-003)
- Missing safe tool behavior: AUTO-INSTALL project-locally inside this Task (AICWDF §6.3) and record it in TOOLCHAIN; a paid tier or a privileged installation STOPS for the Owner (COST_POLICY)

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: refreshed before the change when Graphify is available ([TOOLCHAIN](../00-governance/TOOLCHAIN.md)); otherwise a targeted search of INV, of `PurchasePosting` and of the CST and reservation calls
- Pre-change questions: which guards a receipt and its reversal move, in which order, and which later actions read a lot's provenance
- Expected affected areas: INV's posting service and pages; PUR's line guard; CST's lot cost; the reservation service

**CONTEXT7:**

- Topics to verify: deferred constraint triggers evaluated at commit; partial UNIQUE indexes with a nullable column; the framework's handling of a scanner's keystroke burst in a focused input; Inertia's partial reloads for one line's answer
- Version-sensitive assumptions: none from memory

**UI / UX REQUIREMENTS:**

- Follow global design rules: YES
- Indonesian default: YES
- English support: YES — for the pages; DOC-12's paper stays Indonesian (LOCALIZATION §3.4)
- Plain-language Indonesian: YES — `pages.pg_022`'s *Layak pakai*, *Rusak diterima*, *Ditolak* and "Reservasi untuk Proyek Ini"; the remainder in words when a receipt exceeds it (PX-03); "Sudah Tercatat" for BD-01 (IP-03); MSG-25, MSG-36 and MSG-37 at the scan; MSG-40 for a reversal's reason
- Language switch impact: keeps unsent input, the scan field's state and the line in progress
- Mobile-first: YES — `[floor]` pages, the phone first (NAV-021; NAV-022)
- Responsive: YES
- Adaptive: YES — the phone's line checklist with the scan field fixed at the bottom and one line at a time (D-UX-11); the desktop's lines on the left and current line on the right (J-INV-01); touch-size controls for a coarse pointer at any width (UXS-35)
- Visual breathing room: REQUIRED
- Comfortable density: REQUIRED
- shadcn/ui reuse: the scan field (PT-20), the line sheet and dialogs (PT-16), the evidence upload (IP-16; TASK-030), the quantity block (PT-17), the business date control (PT-25), the confirmation (PT-26), status badges (PT-13), inline messages per line (PT-14)
- Design reference: PG-021 and PG-022 as INFORMATION_ARCHITECTURE §8.3 and J-INV-01 state them, the scanner as DESIGN_SYSTEM §13.2; the Golden Frontend Reference for presentation only (DIR-061 SD-02)
- Primary action: "Catat Barang Masuk" for the current line, its confirmation first (IP-08)
- Secondary actions: "Reservasi untuk Proyek Ini" inside the same command; "Terbitkan Nota Terima Barang" per receiving event; "Cari Produk"; "Daftarkan Barcode" with `products.edit`
- Destructive actions: "Balikkan Penerimaan" (on PG-016 and PG-020), with its reason and a destructive confirming button (IP-09; PT-11)
- Redundancy constraints: one command per purchase line, each with its own answer and never one combined "Tersimpan" (IP-19); the reservation part of the receipt, never a later separate step (D-UX-12); a scan never submits a command (IP-15)
- Loading state: PT-19; a line's pending answer, its inputs frozen until the answer or "Coba Lagi" (IP-04)
- Empty state: PG-021 with no open remainder — one sentence (PT-19)
- Error state: the remainder exceeded, the serial in stock, the unknown or ambiguous code, "Sudah Tercatat" — each in words at its line; a group of PG-021 that fails shows its own error and retry (NAV-021); MSG-12 at the fields (IP-14)

**ROUTE IMPACT:**

- ROUTE-021, ROUTE-022 (the views); ROUTE-181 (receive one line, with its optional reservation), ROUTE-182 (issue DOC-12, after ROUTE-221's draft), ROUTE-183 (reverse a receipt); the evidence upload and registration ROUTE-228 and ROUTE-229 (TASK-030) and the scan lookup ROUTE-282, built here and used by PG-022 and the warehouse pages after it; "Cari Produk" client-only (ROUTE_CONTRACTS §4); PG-016's "Terima Barang" and "Balikkan Penerimaan" (TASK-037's page)

**INTERACTION CONTRACTS:**

- INT-181–INT-183; INT-006 (the scan field) with INT-282, its pointer, built here with ROUTE-282; INT-013 (the evidence upload), INT-017 (FAILED and "Coba Lagi"), INT-221 for the Nota's draft (TASK-029), INT-009 for an unsent line

**NAVIGATION CONTRACT:**

- Parent: — (*Gudang* › *Barang Masuk*) for PG-021; PG-021 · PG-007 for PG-022 (NAV-021; NAV-022)
- Entry: *Gudang* › *Barang Masuk*, "Buat" › *Barang Masuk* and work rows (NAV-021); work rows, "Mulai Terima Barang", PG-016's "Terima Barang" and "Buat" › *Barang Masuk* (NAV-022)
- Back action: "Kembali" → the origin; leaving with an unsent line asks MSG-35 (NAV-022)
- Cancel: the line sheet's "Batal"; the confirmation's "Kembali" → the line, the input kept (IP-08; NV-02)
- Success destination: each line's answer in place (IP-19); after the last line the summary, then PG-007; "Terbitkan Nota Terima Barang" → PG-036 with the note's draft (NAV-022; `DIR-081` OB-02); PG-021's choice → PG-022 of that purchase (NAV-021)
- Error behavior: NV-04, each step answering in place (IP-19); a group of PG-021 that fails shows its own error and retry while the others stay (NAV-021)
- State preservation: PG-021's open groups and company filter in the address; PG-022's unsent input kept across re-login, MSG-35 before leaving with an unsent line (NV-05)
- Mobile behavior: `[floor]` — the line checklist with the scan field fixed at the bottom (D-UX-11; NAV-022)

**ACCEPTANCE CRITERIA:**

1. A purchase line of 5 received in batches of 2 and then 3, each with its evidence, records two receiving events, two lots and a remainder of 3 and then 0 (AC-22; CALC-03; PX-01).
2. Of a line of 100, a receipt of 60 with 5 damaged-accepted puts 55 usable units at the chosen rack and 5 straight into UNUSABLE on the same lot, raises AVAILABLE by 55 only, and leaves 40 open on the purchase (PX-06; L-13; BR-INV-01).
3. Units rejected at the door create no stock event, are recorded on the receiving line, and leave the purchase remainder open (L-13; WF-INV-01).
4. A receipt above the line's remainder is REJECTED with the remainder in words, and a replacement receipt is counted only against its separate replacement cap, never admitting a normal receipt beyond the ordered quantity (PX-03; C-29).
5. Each receiving event creates exactly one lot whose source company is the purchase's, keyed to the receiving line, carrying the line's actual cost through CST and an opaque lot code (BR-INV-04; C-08; PJ-23; OWN-05).
6. A serial already in stock is refused at the scan with MSG-36 and by the command; a serial scanned twice in one command counts once; two commands receiving one serial leave one success (BR-INV-09; C-02; M-05).
7. An unknown code answers MSG-25 and offers "Daftarkan Barcode" only with `products.edit`; an ambiguous code lists its candidates and selects none; without a scanner the field takes a typed SKU with "Cari Produk" beside it (SF-SCAN; IP-15; UXS-34; C-01).
8. A second ORIGINAL receipt with the line's same supplier delivery reference is CONFLICT ("Sudah Tercatat"); a SUPPLEMENT for missing units cites the receipt it completes; concurrent partial receipts of one line commit until the cap and the rest are REJECTED (BD-01; C-49; CM-04; M-35).
9. A project-linked line received with "Reservasi untuk Proyek Ini" commits the receipt and the reservation as one command, at most the usable units and the line's unreserved confirmed demand; the part is absent without `stock.reserve`, and absent on a still-open purchase of a completed or cancelled project, whose units go to the free pool (AX-04; D-UX-12; BR-RSV-03; WF-PRJ-03).
10. A receipt racing a cancellation or a remainder closure of its line ends with one of them committed and the other REJECTED or CONFLICT, never both beyond the ordered quantity (M-36; C-29; architecture challenge 2a).
11. "Balikkan Penerimaan" offers only the lot's unconsumed quantity — consumed quantity shows the restoration needed first or "Buka Kasus" —, and commits the reversal, the re-spread of its charge share as never received, the serials NEVER_RECEIVED and, where usable supply would fall below the reservations, the actor's cut, in one transaction (AX-05; CM-02; CM-03; SF-CHARGE; IP-20).
12. After a wrong product's receipt is reversed, a RE_RECEIPT of the right product cites the reversed receipt and creates its own lot (CM-02; C-59).
13. An account with `stock.receive` and without `cost.view` receives a whole purchase and never meets a price, tax, charge or cost on PG-021, PG-022 or the Nota (PJ-18; J-INV-01; SM-25).
14. "Terbitkan Nota Terima Barang" opens PG-036 with the receiving event's draft — on the phone Edit → Preview → Finalize with the floor step — and issues only after its confirmation; the note renders the committed receipt with its supplier reference and changes no stock (DOC-12; `DIR-081` OB-02; BR-XD-04).
15. Each line is its own command with its own answer; a FAILED answer on a weak connection is retried with "Coba Lagi" under the same `command_id`, and a lost answer replayed after re-authentication returns the recorded receipt, one effect in each case (IP-04; IP-19; M-10; AC-16; REF-058).
16. A receipt sent against a product whose serial policy or quantity scale changed meanwhile is CONFLICT with the current policy (ST-09; L-35).
17. After every scenario the reconciliation suite finds the product, scope, lot and serial guards, the purchase-line guard and the lot cost equal to their facts (BR-INV-08; C-61).

**TEST REQUIREMENTS:**

- Lint: TL-01
- Typecheck: TL-02
- Unit: the receipt split and its base quantities (C-09); the cap of each receipt kind; the lot-code generation's retry bound (FU-13) (TL-03)
- Feature: ROUTE-021, ROUTE-022 and ROUTE-181–ROUTE-183 with each refusal — C-29, C-02, BD-01, ST-09, C-03, C-06, C-21, AZ-08 — and each receipt kind (TL-04)
- Integration: M-35 and M-36 with real sessions, M-05 and M-30; AX-05 under §5's protocol with a re-spread over a consumed lot; the guard CHECKs C-03, C-04, C-07, C-08 and C-09 by violating writes, C-24 and C-28 untouched by a receipt; the lot cost at the cumulative targets with TASK-036's fixtures, FX-04's price correction between partial receipts end to end; the reconciliation suite (TL-05)
- Authorization: `stock.receive`, `stock.reserve` for the offer and the cut, `stock.reverse` as ADM_PLUS, `documents.issue` for DOC-12; the receiving projection without `cost.view` (TL-06)
- Route integrity: ROUTE-021, ROUTE-022 and ROUTE-181–ROUTE-183 audited, with "Cari Produk" client-only (TL-07)
- Interaction: INT-181–INT-183, each with one `command_id` replayed (TL-08; IG-05)
- Browser E2E: J-INV-01 on the phone and the desktop — CJ-07's scanner flow with a duplicate scan, an unknown code and the manual lookup, CJ-01's receive step, INT-181's and INT-182's S2-J05 walk with the Nota's floor step; UXS-34 and UXS-35 (TL-09)
- Localization/language switch: both languages on PG-021 and PG-022; DOC-12's paper Indonesian (TL-10)
- Visual breathing room/density: TEST_STRATEGY §9 for PG-021 and PG-022
- Responsive: TL-11, the phone's checklist and the coarse-pointer controls
- Accessibility: TL-12, each scan announced in a polite live region and a scan error assertively (IP-15 step 10)
- Production build: TL-13
- Security: SM-01 for a foreign purchase, line, serial or evidence; SM-04 for W4-13 and W4-18; SM-25 for the receiving projection (TL-14)
- Performance: QB-06 and PFM-01 for the scan lookup; QB-05 for the receiving worklist; PFM-02; PFM-20 for one line's receipt (TL-15)
- Migration: N/A — no migration or import code in this Task; opening lots are TASK-057's
- Other: M-04's receipt racing a count, run with TASK-043; the real USB scanners at Layer 3 (TEST_STRATEGY §12; GAP-020); DOC-12's layout compared with its preview (DOCUMENT_PREVIEW_PRODUCER §4) and its sample listed for content validation (GAP-019)

**TEST CHECKLIST:**

- [ ] Lint
- [ ] Typecheck
- [ ] Relevant automated tests
- [ ] Authorization
- [ ] Route integrity
- [ ] Interaction behavior
- [ ] Browser E2E if required
- [ ] Responsive/adaptive if required
- [ ] Visual breathing room/density if UI
- [ ] Production build
- [ ] Security checks if required
- [ ] Database safety review
- [ ] Final diff review

**COMPLETION EVIDENCE:**

- Test outputs: each required layer's result in [TEST_STRATEGY §16](../08-testing/TEST_STRATEGY.md#16-evidence)'s form, tied to the tested commit (DOD-04)
- Build result: the production build of TL-13
- Route evidence: the route audit for the routes this Task adds or changes, or N/A with its reason
- Interaction evidence: the INT- tests of this Task, or N/A with its reason
- Browser evidence: `E2E: PASS — Evidence: …` for its journeys (DOD-18), or N/A with its reason
- Localization evidence: both languages on every page it changes (DOD-16), or N/A with its reason
- Cost evidence/exception if applicable: ADDITIONAL RECURRING COST CHANGE: NONE (DOD-20)
- Files changed: the list, every one inside the IN SCOPE areas
- Commit SHA: the commit under the Owner's identity, with no AI attribution
- Notes: each non-applicable item with its reason (DOD-03); the DONE update of TASK_PLAN, this file and CURRENT_HANDOFF (AICWDF §22.3)

**SAFE NEXT TASK:**

- TASK-040 — when its other dependencies are DONE and its READY gate holds

**DO NOT DO:**

- add to V1 — a capability, page, route, field, integration, document type, report or paid service — or drop, defer or narrow a V1_REQUIRED requirement; a Task that would need either raises an Owner question and waits ([DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-20)
- access the VPS, production, its data or a credential, or put a secret, real data, a host detail or production configuration in the repository ([INFRASTRUCTURE](../09-operations/INFRASTRUCTURE.md) INF-01, INF-05; SECURITY SX-01, SX-04, SX-06; [PRODUCTION_DATA_SAFETY](../00-governance/PRODUCTION_DATA_SAFETY.md))
- add AI, model or tool attribution to a commit — the Owner's configured identity only ([AGENTS.md](../../AGENTS.md#git-commit-attribution); DIR-022)
- start before the Owner's execution authorization at OG-4, or mark DONE without the required tests passing and the completion evidence ([TASK_PLAN](TASK_PLAN.md#2-rules-of-the-plan); [DEFINITION_OF_DONE](../08-testing/DEFINITION_OF_DONE.md#2-the-checklist))
- weaken, skip or delete a valid test to obtain a green run, or adopt a paid tier without the Owner's COST_POLICY decision (DOD-05; [COST_POLICY](../00-governance/COST_POLICY.md))
- let rejected units into stock, or damaged-accepted units through AVAILABLE (L-13)
- create a second lot for one receiving event, change a lot's source company, or take provenance or cost from the payload (C-08; BR-INV-04)
- show a price, tax, charge or cost on the receiving pages or the Nota (PJ-18)
- reverse consumed quantity, receive a drop-ship line, or offer the reservation as a later separate step (AX-05; BR-INV-11; D-UX-12)
- let a scan, Enter or a shortcut submit a receipt, or create a product or barcode from a scan (IP-15; SF-SCAN)

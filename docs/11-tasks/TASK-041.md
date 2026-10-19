# TASK-041 — Dispatch (Barang Keluar) and inter-company allocation

Status: REVIEW | Updated: 2026-10-20 | Owner: Planning

Written by WF-PC5-W's writer of bundle W1 of run PC-5 on 2026-10-19 and 2026-10-20 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99 — a REVIEW draft of P11, approved, or not, with the [Task plan](TASK_PLAN.md) at OG-4. **No Task starts before the Owner's execution authorization at OG-4.** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** TODO

**RISK CLASS:** HIGH

**EXECUTOR:** AGENT

**OBJECTIVE:**

Build warehouse dispatch, *Barang Keluar* (WF-INV-03), with the inter-company allocation embedded in it (WF-INV-07): the worklist PG-023 and the scan-led PG-024 *Keluarkan Barang*, where one indivisible dispatch (AX-03; ROUTE-186) takes usable stock out of the lots the operator confirms, consumes the line's reservation or reserves and consumes its unreserved remainder, moves the serials out, attributes HPP at each lot's cost and, for another company's lot, carries the allocation with its reason; the Surat Jalan, DOC-03 (ROUTE-187); SF-RESTORE for a dispatch still in the warehouse or returned undelivered (AX-10; CM-05; CM-06; ROUTE-188); and the identity substitution of a wrong serial or lot on goods that have left (AX-35; CM-36; ROUTE-189).

**WHY THIS EXISTS:**

Dispatch is the single physical subtraction for warehouse fulfilment — delivery never subtracts again —, it is where HPP is recognized once per unit at the actual lot cost, and in the shared pool it is where one company's stock serves another company's project through an explicit, auditable allocation that keeps the source's provenance and gives the consumer its cost, with no inter-company invoice, journal or cash, so that staff can trace every movement and its attribution (CAP-06; O-02; AC-06; AC-22; OWN-04; OWN-05; BR-INV-02; BR-INV-05; BR-FIN-12; GAP-003). Two dispatches never both take the last unit or the same lot's cost (AC-16; GAP-009; GAP-023), a returned shipment comes back once, and a substituted serial carries the delivery and its documents with it — the architecture challenge's sequences 1a, 2b, 3a, 3b, 3c, 18a and 18b. Deliveries and project cancellation (TASK-045, TASK-056) build on it.

**DEPENDS ON:**

- TASK-029 — Document engine — drafts, issue, revision, void, renditions and the composer
- TASK-038 — Reservations
- TASK-039 — Receiving (Barang Masuk)

**BLOCKS:**

- TASK-045 — Deliveries (Pengiriman) and delivery closure
- TASK-056 — Project cancellation

**PARALLEL SAFE:** NO — it consumes reservations, lots and serials and draws lot cost under the product, lot, serial, reservation, line and lot-cost guards (LK-13–LK-19, LK-23) that every inventory Task shares

**IN SCOPE:**

- the DISPATCH movement of DATABASE §5.3 through `InventoryPosting`: the header and its negative usable entries per lot, location and serial, `dispatch_lines` keyed to their project line, project and company — the reserved and unreserved parts summing to the line's quantity, the reservation that of the same line only —, `stock_consumptions` of kind DISPATCH with their bearer keyed to the dispatch line and their `stock_consumption_balances` rows, the HPP draws through CST at each lot's current net cost (TASK-036), the CONSUME events through TASK-038's reservation service, the serials DISPATCHED, and the line guard's net dispatched quantity through PRJ's `DemandPosting` (DATABASE §4.7; §25)
- ROUTE-186 with INT-186, "Catat Barang Keluar" (AX-03): the confirmed WAREHOUSE line of an ACTIVE project chosen; products and serials scanned or chosen — the scan lookup ROUTE-282 with INT-006 and SF-SCAN (TASK-039), each serial re-read IN_STOCK, usable and at its stated lot and location under lock —; the lots confirmed by the operator, the oldest-first order shown from the physical-only view as a picking aid only (BR-INV-04; PJ-01); at most the line's own reservation + AVAILABLE and the line's remainder (PX-03; L-04); the confirmation (IP-08), then the answer in place and PG-023 (NAV-024); several dispatches per line within per-line conservation; a likely duplicate — same line, quantity and business date — answered with MSG-15 and committed only with the actor's reasoned confirmation naming the matches (L-49; BD-13; IP-05; INT-015; QS-21); a pending unexplained-loss case never blocking a dispatch the normal invariants allow (DIR-027)
- the inter-company allocation of WF-INV-07 inside the dispatch: a lot whose source company differs from the project's shows the relation marker; choosing it needs `stock.allocate_intercompany`, a grant on the consuming company only and the mandatory "Alasan alokasi antar-perusahaan", otherwise the command is REJECTED and routed (XL-1; AZ-08; SQ-23; IP-06; MSG-14); the consumption and its cost rows carry the source company, the bearer project, the reason and the authorizing actor (C-28; DATABASE §6.5) — not a second consumption nor a second cost (L-18) —, the consuming project sees the cost in full as its own HPP with the source side masked, the source company the draw with the bearer masked (PJ-05; PJ-06)
- SF-RESTORE as INV's restoration service (AX-10), with ROUTE-188 and INT-188 — "Batalkan Barang Keluar (Belum Berangkat)" for a dispatch whose goods never left or are physically back (CM-05) and "Catat Barang Kembali Tanpa Terkirim" for a shipment refused or not sent (CM-06; `stock.reverse`, ADM+), its reason (IP-09): the restoration cites its consumption, is entered in the dispatch line's unit at its snapshot ratio or in the base unit (L-31), takes at most the consumption's open quantity and net dispatched − net delivered (PX-08; C-27), matches its serials, re-enters each unit in its lot in the condition chosen per unit at the contra amount of its HPP (DATABASE §6.2), reverses the consumption's allocation with it (L-44; AX-10), and may create a new reservation for the restored units in the same command with `stock.reserve` — the consumed reservation is never reactivated (L-07; CM-05); the delivery closure of CM-06 is TASK-045's ROUTE-203; the same service serves TASK-048's client returns and TASK-046's drop-ship return to the warehouse (CM-10; CM-14)
- ROUTE-189 with INT-189, "Koreksi Nomor Seri atau Lot" on the dispatch record — PG-024, or *Rincian proyek* on PG-011 — (AX-35; CM-36; `stock.reverse`, ADM+): the reason and the evidence — a count finding, a delivery or BAST serial, a physical check —; the same product and quantity; the recorded unit physically present returning to stock in its recorded condition and the unit actually shipped, recorded in stock and usable, taking its place — otherwise a case (TASK-031); an IDENTITY_SUBSTITUTION movement net zero per product, a SUBSTITUTION restoration and a new DISPATCH consumption on the same dispatch line, HPP contra'd at the old consumption's current net cost and re-attributed at the new lot's, the allocation reversed and re-recorded by the lot's company — a new allocation needing `stock.allocate_intercompany` again (XL-1) —; the line, reservation, shipment, delivery and quantities unchanged, and the record-bound documents it affects revised or kept with reason (WORKFLOWS §8; architecture challenge 3c)
- ROUTE-187 with INT-187, "Terbitkan Surat Jalan" (DOC-03): its draft created or saved again from the recorded dispatch by ROUTE-221 and opened on PG-036 — the form beside the live preview on the desktop, Edit → Preview → Finalize on the phone (DC-11) —, issued after IP-08's confirmation (`DIR-081` OB-02; TASK-029); INV's `DocumentSubjectProvider` for DOC-03 on a dispatch — the outbound shipment as subject, the committed dispatch as precondition, its signed copy the delivery proof, the layout's name never creating a second delivery (L-19; BR-DOC-04; V1_SCOPE DOC-03)
- PG-023 with NAV-023 and ROUTE-023 (SCR-22's worklist): the dispatch-ready lines — the remaining reservations of ACTIVE projects' warehouse lines of the companies in scope (CALC-02) —, grouped, each group with its own error and retry, leading to PG-024 of the project chosen; entered from *Gudang* › *Barang Keluar* and *Pekerjaan*'s QS-24 "Lihat Semua" (D-IA-20; `DIR-077` CA-07)
- PG-024 with NAV-024 and ROUTE-024 (SCR-22's task): the project's company and its reserved lines, the lots from the pool with the relation marker for another company's, scan-led — on the phone the lot confirmation as a list with one tap per lot, on the desktop line, lots and serials side by side (J-INV-03) —; cost and HPP never shown here; dispatch and delivery kept as two linked records (D-IA-16)
- the dispatch and restoration facts that WF-PRJ-03's fulfilment and stock predicates read, exposed through INV's query classes and `CompletionPredicateProvider`, the predicates TASK-055's; a dispatch on a completed or cancelled project is no residual command and is REJECTED (WF-PRJ-03; L-11)

**OUT OF SCOPE:**

- deliveries, the delivery closure of CM-06 and CM-07's loss in transit (TASK-045; TASK-042 for its loss); client returns, their cases and the reversal of a restoration or return, AX-36 (TASK-048); drop-ship confirmation and its returns (TASK-046)
- allocations created by a loss or by an unrecovered supplier-return share (TASK-042, TASK-044); the Owner's allocation report and the consolidated report (TASK-060); the costing services (TASK-036); the reservation service (TASK-038)
- the scan lookup (TASK-039); QS-06 and QS-24 on the home pages (TASK-058); the stock views (TASK-040); project cancellation (TASK-056)

**REFERENCES:**

- [WORKFLOWS §5.3](../03-workflows/WORKFLOWS/s05-03-inventory-purchasing.md#wf-inv-03--warehouse-dispatch-barang-keluar-floor) WF-INV-03; [WF-INV-07](../03-workflows/WORKFLOWS/s05-03-inventory-purchasing.md#wf-inv-07--inter-company-allocation-embedded-in-dispatch); [§6](../03-workflows/WORKFLOWS/s06-07-subflows-partial.md#6-reusable-subflows) SF-RESTORE, SF-SCAN; [§7](../03-workflows/WORKFLOWS/s06-07-subflows-partial.md#7-partial-processing-rules) PX-01–PX-08; [§8](../03-workflows/WORKFLOWS/s08-09-corrections-indivisible.md#8-correction-reversal-and-cancellation-matrix) CM-02, CM-05, CM-06, CM-10, CM-14, CM-36; [§9](../03-workflows/WORKFLOWS/s08-09-corrections-indivisible.md#9-indivisible-business-actions) AX-03, AX-10, AX-35; [§10](../03-workflows/WORKFLOWS/s10-14-signals-decisions-traceability.md#10-derived-signals-and-action-queue) QS-20, QS-21, QS-24; [§11](../03-workflows/WORKFLOWS/s10-14-signals-decisions-traceability.md#11-cross-company-and-multi-admin-rules); [§12](../03-workflows/WORKFLOWS/s10-14-signals-decisions-traceability.md#12-planner-determined-level-1-decisions) L-03, L-04, L-06, L-07, L-13–L-15, L-18, L-19, L-31, L-35, L-36, L-40, L-42, L-44, L-48, L-49; [WF-DOC-01](../03-workflows/WORKFLOWS/s05-04-05-fulfillment-documents.md#wf-doc-01--generated-document-lifecycle-desk) DOC-03; [WF-PRJ-03](../03-workflows/WORKFLOWS/s05-07-08-completion-migration.md#wf-prj-03--project-completion-penyelesaian-desk) the residual commands
- [BUSINESS_RULES](../02-domain/BUSINESS_RULES.md#inventory-inv) BR-INV-01–BR-INV-13; [BR-RSV-02](../02-domain/BUSINESS_RULES.md#smreservation); [BR-FIN-12, BR-FIN-13](../02-domain/BUSINESS_RULES.md#finance-fin); [BR-ACC-01, BR-ACC-02](../02-domain/BUSINESS_RULES.md#access-boundary-acc); [§5](../02-domain/BUSINESS_RULES.md#5-calculation-semantics) CALC-01, CALC-02, CALC-09, CALC-11; [the numeric annex](../02-domain/BUSINESS_RULES.md#numeric-annex-binding-examples-for-p8-fixtures-gap-004), examples 7, 10, 12; [§7](../02-domain/BUSINESS_RULES.md#7-forbidden-shortcuts) FS-01, FS-03, FS-07
- [V1_SCOPE](../01-product/V1_SCOPE.md#shared-warehouse-and-cost-attribution) shared warehouse and cost attribution; [CAP-06](../01-product/V1_SCOPE.md#must-ship); [DOC-03](../01-product/V1_SCOPE.md#selectable-document-catalog); [ACCEPTANCE_CRITERIA](../01-product/ACCEPTANCE_CRITERIA.md#product-acceptance-contracts) AC-06, AC-16, AC-20, AC-22; [OS-02](../01-product/ACCEPTANCE_CRITERIA.md#operational-success-and-go-live-evidence); [PRODUCT_OVERVIEW](../01-product/PRODUCT_OVERVIEW.md#problems-and-operational-outcomes) O-02; [REFERENCE_COVERAGE](../01-product/REFERENCE_COVERAGE.md#pdf-capability-inventory) REF-021–REF-030, REF-058; [OWN-04, OWN-05](../01-product/REFERENCE_COVERAGE.md#final-owner-decision-coverage)
- [DATABASE §4.7](../04-architecture/DATABASE/s04-07-inv.md#47-inv--inventory) `dispatch_lines`, `stock_consumptions`, `stock_consumption_balances`, `stock_restorations`, `serial_units`; [§5.2](../04-architecture/DATABASE/s05-inventory.md#52-ledger-plus-guard-balances) the race example; [§5.3](../04-architecture/DATABASE/s05-inventory.md#53-movement-catalogue-and-sign-discipline) DISPATCH, RESTORATION, IDENTITY_SUBSTITUTION; [§5.8](../04-architecture/DATABASE/s05-inventory.md#58-unattributed-economic-loss-cases-dir-026-dir-027) no retention; [§6.2](../04-architecture/DATABASE/s06-lot-cost-hpp.md#62-conservation-identity-and-draw-arithmetic); [§6.5](../04-architecture/DATABASE/s06-lot-cost-hpp.md#65-inter-company-allocation-overlay); [§15](../04-architecture/DATABASE/s13-17-history-scope-types.md#15-company-scope-and-tenancy); [§18](../04-architecture/DATABASE/s18-19-constraints-integrity.md#18-constraint-catalogue) C-01–C-04, C-07–C-09, C-24, C-27, C-28, C-32, C-33, C-47, C-61; [§25](../04-architecture/DATABASE/s25-28-transactions-handoffs.md#25-transaction-boundary-map) AX-03, AX-10, AX-35
- [ARCHITECTURE §3](../04-architecture/ARCHITECTURE.md#3-modules) `DocumentSubjectProvider`, `CompletionPredicateProvider`; [§7](../04-architecture/ARCHITECTURE.md#7-posting-services-and-guards) `InventoryPosting`, `CostPosting`, `DemandPosting`
- [CONCURRENCY_IDEMPOTENCY §7.1](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s07-statement-sequences.md#71-lock-sets-of-ax-01ax-37) and [§7.2](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s07-statement-sequences.md#72-checks-effects-and-outcomes-of-ax-01ax-37) AX-03, AX-10, AX-35; [§5](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s04-06-locks-envelope.md#5-data-dependent-lock-set-protocol) AX-34's derivation (M-07); [§9](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s08-09-guards-stale-state.md#9-stale-state) ST-06, ST-09; [§11](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s10-13-identity-failure-retry.md#11-separate-duplicates) BD-13; [§12](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s10-13-identity-failure-retry.md#12-failure-mapping) SQ-23; [§18](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s18-scenarios.md#18-mandatory-concurrency-and-failure-scenarios) M-01, M-03, M-04, M-05, M-07, M-10
- [PERMISSIONS_MATRIX §4](../05-security/PERMISSIONS_MATRIX.md#4-capability-catalogue) `stock.dispatch`, `stock.allocate_intercompany`, `stock.reverse`, `stock.reserve`, `documents.issue`; [§5](../05-security/PERMISSIONS_MATRIX.md#5-workflows-4-mapping) W4-13, W4-18, W4-22; [§2](../05-security/PERMISSIONS_MATRIX.md#2-authorization-model) AZ-08, AZ-10; [§6](../05-security/PERMISSIONS_MATRIX.md#6-company-scope-rules) CS-03–CS-05; [§7.2](../05-security/PERMISSIONS_MATRIX.md#72-table-classification) rows 48–51, 53–56, 61, 75; [§7.3](../05-security/PERMISSIONS_MATRIX.md#73-special-projections) PJ-01–PJ-06, PJ-15; [§8](../05-security/PERMISSIONS_MATRIX.md#8-cross-company-link-authorization) XL-1–XL-5
- [SECURITY §8](../05-security/SECURITY.md#8-threat-model) TM-25, TM-53; [§6](../05-security/SECURITY.md#6-audit-and-security-logging) LG-07
- [ROUTE_CONTRACTS](../03-workflows/ROUTE_CONTRACTS.md#warehouse--receiving-reservations-dispatch-counts-adjustments-and-discrepancies-inv-pg-018pg-029) ROUTE-186–ROUTE-189; [§2](../03-workflows/ROUTE_CONTRACTS.md#2-page-views--route-001route-065) ROUTE-023, ROUTE-024; [INTERACTION_CONTRACTS](../03-workflows/INTERACTION_CONTRACTS.md#warehouse--receiving-reservations-dispatch-counts-adjustments-and-discrepancies-inv-pg-018pg-029) INT-186–INT-189; [§2](../03-workflows/INTERACTION_CONTRACTS.md#2-shared-interactions--int-001int-020) INT-015
- [NAVIGATION_CONTRACTS §2.3](../07-ux-design/NAVIGATION_CONTRACTS.md#23-purchasing-and-warehouse) NAV-023, NAV-024; [INFORMATION_ARCHITECTURE §8.3](../07-ux-design/INFORMATION_ARCHITECTURE.md#83-purchasing-and-warehouse) PG-023, PG-024; [§4](../07-ux-design/INFORMATION_ARCHITECTURE.md#4-design-decisions) D-IA-16, D-IA-20
- [ADMIN_FLOW §10.3](../07-ux-design/ADMIN_FLOW/s10-a-journeys.md#103-inventory-and-purchasing) J-INV-03, J-INV-07; [§11](../07-ux-design/ADMIN_FLOW/s11-13-corrections-numbering-import.md#11-corrections-and-reversals) CM-05, CM-06, CM-36; [IP-05](../07-ux-design/ADMIN_FLOW/s05-command-model.md#ip-05--duplicate-warnings); [IP-06](../07-ux-design/ADMIN_FLOW/s05-command-model.md#ip-06--a-routed-refusal); [§15](../07-ux-design/ADMIN_FLOW/s14-15-messages-scenarios.md#duplicates) UXS-15; [UXS-22](../07-ux-design/ADMIN_FLOW/s14-15-messages-scenarios.md#visibility-and-authority); [UXS-34](../07-ux-design/ADMIN_FLOW/s14-15-messages-scenarios.md#lists-search-scanner-and-exports); [§14](../07-ux-design/ADMIN_FLOW/s14-15-messages-scenarios.md#14-message-catalogue) MSG-14, MSG-15, MSG-36, MSG-37
- [DESIGN_SYSTEM §14.2](../07-ux-design/DESIGN_SYSTEM.md#142-inventory); [§13.2](../07-ux-design/DESIGN_SYSTEM.md#132-scanner)
- [TEST_STRATEGY §6](../08-testing/TEST_STRATEGY.md#6-concurrency-and-failure-proofs) M-01, M-03, M-05, M-07; [§5](../08-testing/TEST_STRATEGY.md#5-domain-and-data-integrity) FX-01, FX-03; [§7](../08-testing/TEST_STRATEGY.md#7-browser-end-to-end-tests-and-critical-journeys) CJ-01, CJ-06, CJ-07, CJ-09; [SECURITY_TEST_MATRIX](../08-testing/SECURITY_TEST_MATRIX.md#3-the-matrix) SM-01, SM-04, SM-12, SM-25; [PERFORMANCE_TARGETS](../08-testing/PERFORMANCE_TARGETS.md#3-the-measured-targets) PFM-01, PFM-02, PFM-20
- [GAP_REGISTER](../00-governance/GAP_REGISTER.md#gap-003--one-stock-pool-must-preserve-source-and-consuming-company-attribution) GAP-003; [GAP-009](../00-governance/GAP_REGISTER.md#gap-009--dispatch-locks-alone-do-not-protect-stock-counts-and-serial-scans); [GAP-023](../00-governance/GAP_REGISTER.md#gap-023--available-stock-does-not-specify-reservation-or-usable-stock-policy)
- Architecture challenge — the sequences 1a, 2b, 3a, 3b, 3c, 18a, 18b of the [P11 gate record](evidence/P11_QUALITY_GATE.md#whole-system-architecture-challenge-sp-98), their assertions carried into this Task's acceptance criteria and tests
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (129): O-02; CAP-06; DOC-03; AC-06; AC-16; AC-20; AC-22; OS-02; REF-021–REF-030; REF-058; IMG-03–IMG-04; IMG-06; OWN-01; OWN-04–OWN-05; BR-INV-01–BR-INV-13; CALC-01; CALC-09; CALC-11; FS-01; WF-INV-03; WF-INV-07; SF-RESTORE; PX-01–PX-08; CM-02; CM-05–CM-06; CM-10; CM-14; CM-36–CM-37; AX-03; AX-10; AX-35; L-03; L-06–L-07; L-13–L-15; L-31; L-35–L-36; L-40; L-42; L-44; L-48; ROUTE-023–ROUTE-024; ROUTE-186–ROUTE-189; INT-186–INT-189; C-01–C-04; C-07–C-09; C-24; C-27–C-28; C-33; C-47; C-61; XL-1–XL-5; TM-25; TM-53; BD-13; M-01; M-03–M-07; M-10; D-IA-16; D-IA-20; PG-023–PG-024; SCR-22; J-INV-03; J-INV-07; UXS-15; UXS-22; UXS-34; NAV-023–NAV-024; GAP-003; GAP-009; GAP-023

**EXPECTED MODULES / FILE AREAS:**

- the INV module's Dispatch, ReverseDispatch and SubstituteDispatchIdentity actions, `InventoryPosting`'s dispatch, restoration and substitution paths, INV's `DocumentSubjectProvider` for DOC-03, the dispatch query classes and `ShowDispatchWorklist` and `ShowDispatchGoods`; `resources/js/Pages/INV` for PG-023 and PG-024 with the lot confirmation in `resources/js/features/inv`; `tests/Feature/INV`, `tests/Concurrency/` and `tests/Reconciliation/`

**BUSINESS RULES:**

- BR-INV-02 with FS-03: dispatch is the single physical subtraction for warehouse fulfilment; delivery, printing and layout selection never subtract; BR-RSV-02: the dispatch consumes the line's reservation as its one physical effect (annex 7: dispatching 5 reserved units → ON HAND 5, RESERVED 0, AVAILABLE 3; AC-22)
- BR-INV-04 and L-13: dispatch consumes identified lots that the operator confirms, the oldest-first suggestion a picking aid only, every event naming its lots and serials; BR-INV-09: a serial must be in stock and usable, and its lot is the serial's, never chosen
- BR-FIN-12, L-18 and CALC-09: HPP is the actual lot cost drawn once per unit at dispatch into the consuming project's direct cost; BR-FIN-13: the zeroing draw takes the residual (annex 10: 5 to X for 41,667 and 7 to Y for 58,333); CALC-11's company profit sums those project figures
- BR-INV-05, L-03, L-44 and FS-07 (WF-INV-07): another company's lot is consumed through an explicit allocation — source company, consuming company and project, product, base quantity, pinned lot cost, reason, individual actor, time —, authorized by ADM+ inter-company authority with a grant on the consuming company only, never a second consumption or cost, reversed only with the consumption it overlays, with no inter-company invoice, journal or cash transfer (annex 12; OWN-05; AC-06; REF-022)
- BR-ACC-02: the allocated cost is the consuming project's own cost, while the source company's purchases, suppliers, prices and other lots stay hidden
- SF-RESTORE with L-07 and L-31 (CM-05; CM-06): a restoration cites its consumptions, is capped by them and by net dispatched − net delivered (PX-08), matches its serials, converts at the cited event's snapshot, contras HPP proportionally with the residual on the restoration that zeroes the consumption, reverses the allocation with it, and never reactivates the consumed reservation; a restoration recorded in error is undone only by CM-37 (TASK-048)
- CM-36, L-48 and AX-35: a wrong serial or lot on goods that have left is corrected by substituting the same product and quantity, re-attributing cost and allocation at current net costs, nothing else changing; CM-02: a wrongly received unit already dispatched is first restored here before its receipt is reversed
- PX-01–PX-08 with L-04: several dispatches per line within per-line conservation; excess refused at the command; a committed dispatch is never undone implicitly by a later failure; any authorized Admin continues the work
- the inventory rule set shared with TASK-038–TASK-040, TASK-042 and TASK-043, as it reaches a dispatch: BR-INV-01 and CALC-01 — AVAILABLE ≥ 0 after every dispatch —; BR-INV-03 — base units with the dispatch line's snapshot (C-09) —; L-14 — a dispatch into a counted scope makes the count stale (C-33) —; L-15 — a restoration is a permitted increase (C-07) —; L-35 — a changed serial policy meets the dispatch as CONFLICT (ST-09) —; L-36 and L-42 — moves and unidentified units govern the lots it may choose —; L-40 and BR-INV-11 — a drop-ship or service line is never dispatched, its line guard's mode making it impossible (DATABASE §5.7) —; BR-INV-06, BR-INV-07, BR-INV-10, BR-INV-12 and BR-INV-13 — returns, counts, restock advice, opening lots and losses read the consumptions it writes, an opening lot dispatched like any other and imported once by its identity (C-47) —; BR-INV-08 and FS-01 — every quantity recomputable, no stored workflow status —; DIR-027 — a pending unexplained-loss case never blocks a dispatch the normal invariants allow; L-06, mapped here through its ground BR-INV-13, governs invoice reductions and has no dispatch effect
- REF-021–REF-030 and REF-058 as dispatch meets them: one pool (REF-021) with source and consuming attribution (REF-022), lots picked by rack (REF-023) and never from damaged units (REF-024), the dispatch with its barcode, project, actor and a safe reversal (REF-025), the ledger and balances (REF-026), counts and returns reading it (REF-027; REF-029), restock advice over what remains (REF-028), drop-ship never dispatched (REF-030), and one effect per retried dispatch (REF-058)

**ARCHITECTURE RULES:**

- INV is tier 5 (ARCHITECTURE §3): a dispatch draws cost through CST's `CostPosting`, consumes reservations through the reservation service and moves the line guard through PRJ's `DemandPosting`, all in its one transaction; the overlay is written by the same statements as the consumption and its cost rows, never as a separate step (DATABASE §6.5)
- a consumption-creating movement is undone only by capped restorations of its consumptions — never by a MOVEMENT_REVERSAL (DATABASE §5.3); a restoration is a new row citing what it restores (BR-CR-02)
- DOC-03 is a layout of the outbound shipment issued through TASK-029's engine and template (AX-15); INV adds its subject and precondition only; delivery is a separate linked record (D-IA-16; TASK-045)

**SECURITY / PERMISSION REQUIREMENTS:**

- ROUTE-023, ROUTE-024 and ROUTE-186 `stock.dispatch` (ADM, W4-13; prerequisites `stock.view`, `projects.view`), another company's lot also `stock.allocate_intercompany` (ADM_PLUS, W4-22; XL-1); ROUTE-187 `documents.issue`; ROUTE-188 `stock.reverse` (ADM_PLUS, W4-18), its same-action reservation `stock.reserve`; ROUTE-189 `stock.reverse`, a substitution onto another company's lot `stock.allocate_intercompany` again (XL-1)
- `stock.allocate_intercompany` is its own separately granted ADM+ authority (RG-08), held by neither of INFORMATION_ARCHITECTURE's Admin personas (§8.3's PG-024); a chosen effect on another company's record without its grant or authority is REJECTED and routed (AZ-08; SQ-23); TM-25 and TM-53 are answered by the masking of PJ-05 and PJ-06, the mandatory reason, the Owner's review and LG-07's signal on repeated single-unit allocations and allocations reversed soon after
- the line, lots, serials and reservation resolved through the scope resolver — the lot as a pool record (CS-05) —, and the bearer, source company, cost and allocation derived, never taken from the payload (CS-04; DP-03; WS-02)

**COMPANY SCOPE AND FIELD PROJECTION:**

- the command's company is the project's (CS-03); the lots are pool records chosen across companies, the source company keyed to the lot and the bearer to the dispatch line, so no row can contradict its cause (DATABASE §6.5; C-28; architecture challenge 1a); XL-1 is the only cross-company link a dispatch or substitution creates — XL-2 and XL-3 are set by the cases of the restorations' Tasks, XL-4 by a linked project (TASK-032), XL-5 by a real transfer (TASK-054) —, and no other cross-company reference is creatable (CS-04; C-32)
- rows 53–56: dispatch lines PV(c); consumptions CV(bearer c) with source identifiers masked (PJ-05; PJ-06), CV(source c) with the bearer masked; consumption balances PV(bearer c); restorations PV(c) of the case or bearer company; row 75: cost rows by side; a lot of another company on PG-024 shows its code, rack, condition and remaining quantity with the relation marker (PJ-01; PJ-04)
- PG-023 lists the projects of companies in scope, labelled per company (CS-07; CS-08; PF-13); DOC-03's payload in the operational family within the project's company (PJ-15); HPP never shown on the dispatch pages

**CONCURRENCY / IDEMPOTENCY:**

- AX-03: STATIC, LK-00, LK-01, LK-03, LK-13, LK-14, LK-15, LK-17, LK-18, LK-19, LK-23, the line's project at LK-01; re-read under lock: the line confirmed and WAREHOUSE, the quantity ≤ its own reservation + AVAILABLE and ≤ the line remainder, each lot row holding what is drawn, each serial IN_STOCK, usable and at its lot and location, the allocation authority when the lot's company differs, the product's policy (ST-09); identity BD-13 — the likely-duplicate query by the line under LK-19 —; refusals PX-03, C-04, C-03, C-02, L-49, AZ-08; retry class A (CONCURRENCY_IDEMPOTENCY §7.1, §7.2)
- AX-10: STATIC, LK-00, LK-01, LK-03, LK-10, LK-13, LK-14, LK-15, LK-17, LK-18, LK-19, LK-21, LK-22, LK-23, LK-24, LK-29 with FU-01, FU-02, FU-09 and FU-13, the bearer project at LK-01; re-read: the consumption's open quantity ≥ the quantity (C-27), PX-08's caps, the returned serials dispatched on that consumption; identity BD-13 — by the case, otherwise the line —; refusals C-27, C-30, L-49, AZ-08; retry class A
- AX-35: STATIC, LK-00, LK-01, LK-03, LK-13, LK-14, LK-15, LK-17, LK-23, LK-24, LK-29 with FU-02; re-read: the same product and quantity, the recorded unit present, the shipped unit in stock and usable; deferred net zero per product; identity the original consumption; refusals C-27, C-02, C-07, AZ-08; retry class A
- races meet at the product guard: with stock 1 two dispatches leave one COMMITTED and one REJECTED, never −1 (M-01; C-04; architecture challenge 3a), the loser REJECTED with the current state, never CONFLICT (ST-06); a dispatch consumes only its own line's reservation, so another project's reserved supply is never taken (M-03); one serial dispatched by two commands leaves one success (M-05; C-02); two draws of one lot take each unit's HPP once under the lock order, the lot cost at LK-23 (architecture challenge 18a); a cost correction racing a dispatch of an affected lot restarts with the new consumption (M-07; architecture challenge 2b); a second restoration of the same goods is refused by C-27 (architecture challenge 3b)
- every command carries `command_id` (CI-01); a lost answer retried, also after re-authentication, returns the recorded dispatch with one effect (CI-05; M-10; AC-16)

**AUDIT:**

- each dispatch, restoration and substitution is one business audit event in its transaction with the individual actor, the records affected and, for a correction, its reason (BR-XC-01 — stock is a critical area; LG-01); the highest authority class exercised is recorded — ADM_PLUS for a dispatch that carries an allocation, a restoration and a substitution —, each reaching the Owner review queue (AZ-10; QS-14)
- the allocation keeps `allocation_reason` and `allocation_authorized_by` on its consumption (DATABASE §4.7); a likely-duplicate confirmation is audited under the action's acknowledged variant, which QS-21 reads (CONCURRENCY_IDEMPOTENCY §11); a routed refusal commits one audit event per affected company (SQ-23); a cross-company event shows each side only its own keys (PJ-11)

**DATABASE IMPACT ASSESSMENT:**

- Schema change: NONE — uses the tables of TASK-005–TASK-009; a missing column is an executor discovery (AGENT_OPERATING_MODEL)
- Migration required: NO
- Existing data transformation: NONE
- Direct production DB access: FORBIDDEN
- Data-loss risk: NONE KNOWN

**MODULARITY / EXTENSIBILITY:**

- Module boundary affected: INV, tier 5 — calling CST, the reservation service and PRJ's `DemandPosting`, implementing DOC's `DocumentSubjectProvider` for DOC-03 (ARCHITECTURE §3, §7)
- Stable core kept provider-neutral: YES
- External/vendor-specific code isolated: YES — the scanner is a keyboard wedge, no device library (IP-15)
- Replaceable adapter/contract required: NO
- Provider lock-in risk: NONE beyond the Owner's baseline stack
- Future extension impact: MEDIUM — deliveries, client returns and drop-ship returns reuse the dispatch and the restoration service
- Speculative abstraction avoided: YES — no inter-company settlement engine, transfer order or valuation method (V1_SCOPE out of scope; FS-07; FS-13)

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

- Context7: REQUIRED — PostgreSQL row locks and CHECK re-evaluation under concurrent updates, deferred constraint triggers for the net-zero substitution, composite foreign keys, at the installed version
- Graphify: OPTIONAL — once deliveries and client returns cite the consumptions
- Browser E2E: REQUIRED — J-INV-03 on the phone and the desktop with another company's lot, the routed refusal and the Surat Jalan's draft (CJ-01's dispatch, CJ-07's scanner flow, CJ-09's isolation)
- Other: the synthetic group FX-02 with a shared pool of two companies; a two-connection harness (TASK-003); the numeric annex's examples 7, 10 and 12 and FX-03's serial substitution and erroneous-restoration scenarios
- Missing safe tool behavior: AUTO-INSTALL project-locally inside this Task (AICWDF §6.3) and record it in TOOLCHAIN; a paid tier or a privileged installation STOPS for the Owner (COST_POLICY)

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: refreshed before the change when Graphify is available ([TOOLCHAIN](../00-governance/TOOLCHAIN.md)); otherwise a targeted search of INV and of the CST, reservation and demand calls
- Pre-change questions: which guards a dispatch, a restoration and a substitution move and in which order; where the bearer and the source company of a consumption come from
- Expected affected areas: INV's posting paths and pages; CST's draws and overlay; the reservation service; PRJ's line guard

**CONTEXT7:**

- Topics to verify: concurrent `UPDATE` re-evaluation against a committed guard row and the CHECK it violates; deferred constraint triggers at commit; Inertia partial reloads for a line's answer
- Version-sensitive assumptions: none from memory

**UI / UX REQUIREMENTS:**

- Follow global design rules: YES
- Indonesian default: YES
- English support: YES — for the pages; DOC-03's paper stays Indonesian (LOCALIZATION §3.4)
- Plain-language Indonesian: YES — "Alasan alokasi antar-perusahaan"; MSG-14 for a routed refusal, MSG-15 for a likely duplicate, MSG-36 and MSG-37 at the scan, MSG-40 for a correction's reason; the substitution form stating that quantities and the shipment stay unchanged (ADMIN_FLOW §11)
- Language switch impact: keeps unsent input and the lots confirmed so far
- Mobile-first: YES — `[floor]` pages, scan-led on the phone (NAV-023; NAV-024)
- Responsive: YES
- Adaptive: YES — on the phone the lot confirmation as a list with one tap per lot, on the desktop line, lots and serials side by side (J-INV-03); dialogs as full-height sheets on the phone (PT-16)
- Visual breathing room: REQUIRED
- Comfortable density: REQUIRED
- shadcn/ui reuse: the scan field (PT-20), the lot list with "Ambil Lebih Dulu" and the relation marker (PT-17; PT-04), dialogs and sheets (PT-16), the confirmation with its reason (PT-26), status badges (PT-13), inline messages (PT-14)
- Design reference: PG-023 and PG-024 as INFORMATION_ARCHITECTURE §8.3 and J-INV-03 state them; the Golden Frontend Reference for presentation only (DIR-061 SD-02)
- Primary action: "Catat Barang Keluar" for the chosen line, its confirmation first (IP-08)
- Secondary actions: "Terbitkan Surat Jalan" on request; from the dispatch record "Koreksi Nomor Seri atau Lot"
- Destructive actions: "Batalkan Barang Keluar (Belum Berangkat)" and "Catat Barang Kembali Tanpa Terkirim", each with its reason and a destructive confirming button (IP-09; PT-11)
- Redundancy constraints: one dispatch per intent with its own answer; the Surat Jalan a separate command (IP-19); a likely duplicate continued only by a reasoned confirmation naming its matches (IP-05); no cost or HPP on the dispatch pages
- Loading state: PT-19; the line's inputs frozen while its answer is unknown (IP-04)
- Empty state: PG-023 with no dispatch-ready line — one sentence (PT-19)
- Error state: insufficient usable supply with the current figure, a serial absent or unusable, MSG-14 for another company's lot without authority, MSG-15 for a likely duplicate — each in words at its line; MSG-12 at the fields (IP-14)

**ROUTE IMPACT:**

- ROUTE-023, ROUTE-024 (the views); ROUTE-186 (dispatch), ROUTE-187 (issue DOC-03, after ROUTE-221's draft), ROUTE-188 (the dispatch's reversals with their restoration), ROUTE-189 (identity substitution, also from *Rincian proyek* on PG-011); the scan lookup ROUTE-282 (TASK-059) used by PG-024

**INTERACTION CONTRACTS:**

- INT-186–INT-189; INT-015 for the likely duplicate (TASK-013); INT-006 for the scan field (TASK-039); INT-221 for the Surat Jalan's draft (TASK-029); INT-009 for an unsent line

**NAVIGATION CONTRACT:**

- Parent: — (*Gudang* › *Barang Keluar*) for PG-023; PG-023 · PG-007 for PG-024 (NAV-023; NAV-024)
- Entry: *Gudang* › *Barang Keluar* and *Pekerjaan*'s QS-24 "Lihat Semua" (NAV-023); *Pekerjaan*'s QS-24 rows, work rows and the worklist (NAV-024; D-IA-20)
- Back action: "Kembali" → the origin; leaving with an unsent line asks MSG-35 (NAV-024)
- Cancel: the confirmation's "Kembali" → the line, the input kept (IP-08; NV-02); a reason step's "Kembali" → its form
- Success destination: the dispatch's answer in place, then PG-023; "Terbitkan Surat Jalan" on request → PG-036 with its draft (NAV-024; `DIR-081` OB-02); a reversal or substitution in place (NV-03)
- Error behavior: NV-04, each step answering in place (IP-19); a routed refusal names who will see it (IP-06); a group of PG-023 that fails shows its own error and retry (NAV-023)
- State preservation: PG-023's open groups and company filter in the address; PG-024's unsent input kept across re-login, MSG-35 before leaving with an unsent line (NV-05)
- Mobile behavior: `[floor]` — scan-led, one tap per lot (NAV-024; J-INV-03)

**ACCEPTANCE CRITERIA:**

1. With ON HAND 10, RESERVED 5 and UNUSABLE 2, dispatching the 5 reserved units gives ON HAND 5, RESERVED 0 and AVAILABLE 3, one physical effect per dispatch, each consumed reservation CONSUMED; a later delivery subtracts nothing (annex 7; BR-INV-02; AC-22).
2. A line with no reservation dispatches its quantity from AVAILABLE, reserved and consumed in the same action, so a project served from existing stock needs no purchase (WF-INV-03; BR-XD-02; AC-20).
3. With stock 1, two concurrent dispatches for different projects end with one COMMITTED and one REJECTED with the current state — never both, never a negative balance —, in both orders and for serialized and quantity products (M-01; C-04; AC-16; architecture challenge 3a).
4. A dispatch consumes only its own line's reservation, so a racing dispatch for another project never takes reserved supply (M-03; DATABASE §4.7's composite key).
5. A serial that is not in stock, is unusable or is at another lot or rack is refused; a serial dispatched by two commands leaves one success (C-02; M-05; BR-INV-09).
6. Five pieces of a box of 12 for 100,000 dispatched to X and seven to Y attribute 41,667 and 58,333 as their projects' HPP, Σ = 100,000; two concurrent draws of one lot take each unit's HPP once (annex 10; BR-FIN-12; BR-FIN-13; architecture challenge 18a).
7. B's project dispatching 6 units of A's lot of 10 at 12,000 records one consumption with source A, bearer B's project, its reason and its authorizing actor, and HPP 72,000 on B's project; B's `cost.view` sees 72,000 as its own cost with A's identity, supplier and purchase masked, A's `cost.view` sees the draw with B's project masked; A's lot keeps 4 unconsumed units, the most a later purchase return may take (annex 12; BR-INV-06; C-28; PJ-05; PJ-06; architecture challenge 18b).
8. Choosing another company's lot without `stock.allocate_intercompany`, without a grant on the consuming company, or without "Alasan alokasi antar-perusahaan" is REJECTED and routed with MSG-14, one audit event per affected company and nothing else committed; no grant on the source company is needed (XL-1; AZ-08; SQ-23; UXS-22).
9. A dispatch matching a dispatch of the same line, quantity and business date is REJECTED with MSG-15 listing the match, and commits only when resubmitted with the actor's reason naming it; a match that appeared meanwhile is REJECTED again (L-49; BD-13; UXS-15).
10. "Batalkan Barang Keluar (Belum Berangkat)" restores the units to their lots in the condition chosen per unit with an HPP contra at the consumption's current net cost, reverses the allocation with it, leaves the consumed reservation CONSUMED and may reserve the restored units anew in the same command with `stock.reserve` (CM-05; SF-RESTORE; L-07).
11. "Catat Barang Kembali Tanpa Terkirim" restores at most net dispatched − net delivered, and a second restoration of the same goods is REJECTED by the consumption's cap (CM-06; PX-08; C-27; architecture challenge 3b).
12. A partial restoration takes its proportional share of the consumption's net HPP and the restoration that zeroes the consumption takes the residual (L-31; DATABASE §6.2).
13. "Koreksi Nomor Seri atau Lot" with its evidence moves the consumption from the recorded serial, back in stock in its recorded condition, to the serial actually shipped, re-attributes HPP and allocation at current net costs, and leaves the line, reservation, shipment, delivery and quantities unchanged, the record-bound documents revised or kept with reason; a shipped unit not in stock and usable is REJECTED (CM-36; AX-35; C-27; architecture challenge 3c).
14. "Terbitkan Surat Jalan" opens PG-036 with the dispatch's draft and issues it only after its confirmation; the Surat Jalan renders the committed shipment, creates no second delivery and changes no stock (DOC-03; L-19; `DIR-081` OB-02).
15. A cost correction racing a dispatch of one of its lots ends with Σ attributed = the corrected cost: the dispatch took the old cost and was corrected, or took the corrected cost (M-07; architecture challenge 2b).
16. A pending unexplained-loss case in the scope blocks no dispatch the normal guards allow (DIR-027; M-06).
17. A drop-ship or service line is never offered for dispatch and its route refuses it (BR-INV-11; DATABASE §5.7).
18. Without `stock.dispatch` the pages and "Catat Barang Keluar" are absent; without `stock.reverse` the reversals and the substitution are absent; cost and HPP never appear on PG-023 or PG-024 (IG-01; SM-25).
19. Each of ROUTE-186–ROUTE-189 replayed with one `command_id` returns the recorded outcome, also after re-authentication, and with a changed payload is CONFLICT (CI-05; M-10; REF-058).
20. After every scenario the reconciliation suite finds the stock, reservation, line, consumption and lot-cost guards equal to their facts and C-28's DQ returns no row (BR-INV-08; C-61; OS-02).
21. A dispatch whose payload names another company's project line or reservation is refused exactly like a nonexistent reference, and another company's lot enters a dispatch only as an XL-1 allocation with its reason (CS-03; CS-04; C-32; architecture challenge 1a).

**TEST REQUIREMENTS:**

- Lint: TL-01
- Typecheck: TL-02
- Unit: the dispatch quantity's reserved and unreserved parts; the restoration's caps and its conversion at the dispatch line's snapshot (TL-03)
- Feature: ROUTE-023, ROUTE-024 and ROUTE-186–ROUTE-189 with each refusal — PX-03, C-04, C-03, C-02, L-49, AZ-08, C-27, C-30, C-07, ST-09 — and the allocation's reason and authority (TL-04)
- Integration: M-01 with a barrier before LK-13, both orders, serialized and quantity products; M-03 and M-05; M-07's three timings with TASK-036's cascade and TASK-037's ROUTE-175; M-06 with a fixture pending case; a restoration racing a second restoration of one consumption (C-27); the substitution's net-zero trigger (C-07); C-28's DQ after allocations, restorations and substitutions; the guard CHECKs C-01–C-04, C-08 and C-09 by violating writes and C-24 untouched by a dispatch; the reconciliation suite (TL-05)
- Authorization: `stock.dispatch` with its prerequisites, `stock.allocate_intercompany` as a separately granted ADM_PLUS authority with a grant on the consuming company only, `stock.reverse`, `stock.reserve` for the restoration's offer, `documents.issue` for DOC-03; the routed refusal's events per company (TL-06)
- Route integrity: ROUTE-023, ROUTE-024 and ROUTE-186–ROUTE-189 audited (TL-07)
- Interaction: INT-186–INT-189, each with one `command_id` replayed (TL-08; IG-05)
- Browser E2E: J-INV-03 — CJ-01's dispatch, CJ-07's scanner flow with a duplicate scan and manual lookup, CJ-09's Company-A-only Admin meeting Company B's lot and the routed refusal, CJ-06's dispatch reversal —; UXS-15, UXS-22 and UXS-34 at the phone and desktop widths (TL-09)
- Localization/language switch: both languages on PG-023 and PG-024; DOC-03's paper Indonesian (TL-10)
- Visual breathing room/density: TEST_STRATEGY §9 for PG-023 and PG-024
- Responsive: TL-11, the phone's one-tap lot list
- Accessibility: TL-12, each scan announced and the relation marker read as text
- Production build: TL-13
- Security: SM-01 for a foreign line, lot, serial or reservation; SM-04 for W4-13, W4-18 and W4-22; SM-12 for TM-25's and TM-53's masking and LG-07's signal; SM-25 for no cost on the dispatch pages and DOC-03's payload (TL-14)
- Performance: PFM-01 and PFM-02 for PG-023 and PG-024; QB-06 for the scan lookup (TASK-039); PFM-20 for a ten-line dispatch (TL-15)
- Migration: N/A — no migration or import code in this Task
- Other: FX-03's serial substitution and cross-company scenarios; GAP-003's and GAP-023's evidence recorded; DOC-03's layout compared with its preview (DOCUMENT_PREVIEW_PRODUCER §4) and its sample listed for content validation (GAP-019)

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

- TASK-045 — when its other dependencies are DONE and its READY gate holds

**DO NOT DO:**

- add to V1 — a capability, page, route, field, integration, document type, report or paid service — or drop, defer or narrow a V1_REQUIRED requirement; a Task that would need either raises an Owner question and waits ([DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-20)
- access the VPS, production, its data or a credential, or put a secret, real data, a host detail or production configuration in the repository ([INFRASTRUCTURE](../09-operations/INFRASTRUCTURE.md) INF-01, INF-05; SECURITY SX-01, SX-04, SX-06; [PRODUCTION_DATA_SAFETY](../00-governance/PRODUCTION_DATA_SAFETY.md))
- add AI, model or tool attribution to a commit — the Owner's configured identity only ([AGENTS.md](../../AGENTS.md#git-commit-attribution); DIR-022)
- start before the Owner's execution authorization at OG-4, or mark DONE without the required tests passing and the completion evidence ([TASK_PLAN](TASK_PLAN.md#2-rules-of-the-plan); [DEFINITION_OF_DONE](../08-testing/DEFINITION_OF_DONE.md#2-the-checklist))
- weaken, skip or delete a valid test to obtain a green run, or adopt a paid tier without the Owner's COST_POLICY decision (DOD-05; [COST_POLICY](../00-governance/COST_POLICY.md))
- subtract stock at delivery, printing or layout selection, or record a dispatch against a drop-ship or service line (FS-03; BR-INV-11)
- choose a lot by FIFO, average or any automatic rule, or let the oldest-first aid decide (BR-INV-04)
- create an allocation without its reason and authority, a second consumption or cost for it, or an inter-company invoice, journal or cash transfer; reverse an allocation apart from its consumption (BR-INV-05; FS-07; L-44)
- reactivate a consumed reservation, or restore more than net dispatched − net delivered (L-07; PX-08)
- show HPP or cost on the dispatch pages, or another company's identity for its lot (PJ-01; PJ-04)

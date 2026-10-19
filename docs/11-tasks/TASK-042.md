# TASK-042 — Condition, location moves, adjustments and losses

Status: REVIEW | Updated: 2026-10-20 | Owner: Planning

Written by WF-PC5-W's writer of bundle W1 of run PC-5 on 2026-10-19 and 2026-10-20 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99 — a REVIEW draft of P11, approved, or not, with the [Task plan](TASK_PLAN.md) at OG-4. **No Task starts before the Owner's execution authorization at OG-4.** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** TODO

**RISK CLASS:** HIGH

**EXECUTOR:** AGENT

**OBJECTIVE:**

Build condition changes, location moves, adjustments and losses on PG-027 *Penyesuaian & Kondisi* and from PG-018 and PG-019 (WF-INV-04; WF-INV-06): "Ubah Kondisi" and "Periksa Ulang" (AX-06; NX-05; ROUTE-195), "Pindah Lokasi" (NX-05; ROUTE-190), "Catat Penyesuaian Stok" for disposal, shrinkage and other loss (AX-06; AX-30; ROUTE-194) and a loss's exact reversal (CM-34; ROUTE-196); and SF-LOSS — every loss recognized exactly once at its lot's current net cost on its causal project or owning company, the loss-in-transit reclassification, the unrecovered share of a supplier return and the exact inverse — as the service the delivery, return and count Tasks call.

**WHY THIS EXISTS:**

Damaged, disposed, shrunk or lost goods must leave usable stock explicitly and never make their cost disappear or count twice (BR-INV-13; DIR-024 D-3; GAP-028): a loss is recorded once, in the same action as the stock-out that removes it, charged to the causal project when one is established and otherwise to the owning company, and reclassified rather than added where the cost already sits in a project's HPP. Every decrease below the reservations carries a person's cut (BR-RSV-04), increases never come from an adjustment (L-15), and a move only relocates units (L-36). Counts and client returns (TASK-043, TASK-048) build on these commands and on SF-LOSS.

**DEPENDS ON:**

- TASK-031 — Correction cases
- TASK-036 — Costing foundations — lot cost basis, charge allocation and HPP attribution
- TASK-039 — Receiving (Barang Masuk)
- TASK-040 — Stock views, stock history and movement reversal

**BLOCKS:**

- TASK-043 — Stock count (opname) and discrepancies
- TASK-048 — Client returns (sales return)
- TASK-057 — Opening import — preparation, dry run, sign-off and commit

**PARALLEL SAFE:** NO — it writes the stock ledger and its product, scope, lot, serial and consumption guards (LK-13–LK-17, LK-24) and posts losses through the lot-cost guard (LK-23) that every inventory Task shares

**IN SCOPE:**

- the CONDITION_CHANGE, LOCATION_MOVE, ADJUSTMENT_DECREASE and LOSS movements of DATABASE §5.3 through `InventoryPosting`, with their LOSS consumptions, LOSS cost rows through CST at each lot's current net cost (TASK-036) and serial states; the net-zero groups — a condition change per lot and rack, a move per lot and condition — checked by the deferred trigger at commit (C-07); every guard row once per command (GU-01)
- ROUTE-195 with INT-195 on PG-027, from a lot or serial on PG-019 (J-INV-04): "Ubah Kondisi" (AX-06; `stock.condition`, ADM+) — named lots or serials, *Layak pakai* → *Tidak layak pakai*, the reason (IP-09) and the evidence (IP-16), the IP-20 cut where usable supply would fall below the reservations (TASK-038) —; unidentified fungible units whose candidate lots all belong to one company confirmed lot by lot by the actor, oldest first only as a picking aid (L-42); "Periksa Ulang" (NX-05), the explicit re-inspection that returns UNUSABLE units to usable with its reason
- ROUTE-190 with INT-190, "Pindah Lokasi" on PG-018 and PG-019 (NX-05; `stock.move`): the same units of one lot and condition leaving one rack and arriving at another, scope and lot rows created on first use (FU-01; FU-02), a wrong move corrected by the opposite move (L-36; J-MD-05) — the phone flow with the scan field (J-MD-05)
- ROUTE-194 with INT-194, "Catat Penyesuaian Stok" (`stock.adjust`, ADM+): a decrease only — disposal of unusable units ("Musnahkan"), confirmed shrinkage or other physical loss — naming its lots or serials with reason and evidence (WF-INV-06; L-15); the stock-out, the IP-20 cut and SF-LOSS committed as one action (AX-06; AX-30); the form stating that the loss is recorded once, on the causal project or else on the owning company (J-INV-06); the bearer company in scope, with `stock.allocate_intercompany` when it differs from the lot's company, otherwise REJECTED and routed (AZ-08; XL-1; SQ-23)
- SF-LOSS as INV's loss service (AX-30): one LOSS consumption and cost row per lost unit at its current net lot cost, allocated charges and non-creditable tax included; the causal project by L-38 — a damaged-return case's returning project, a shipment in transit's project, the project of a line damaged at receipt — otherwise the lot's owning company; a unit lost under an open case that case's single loss outcome (CM-11); a loss charged to a project of another company than the lot's carrying one new allocation on its consumption (L-44); the loss-in-transit reclassification — HPP contra'd and the same amount recorded as loss, no stock-out, the dispatch's allocation kept — called by TASK-045's delivery closure (CM-07); the unrecovered share of a supplier return as a cost-only loss on its causal project (CM-15; CM-16), called by TASK-044's return outcome for warehouse and drop-ship returns alike (NX-16; DATABASE §5.7)
- ROUTE-196 with INT-196, a loss's exact reversal from its record (CM-34; AX-30's reversal; `stock.reverse`, ADM+): the loss contra'd, any reclassified HPP re-attributed and its allocation reversed in one action, the units coming back only through capped LOSS_REVERSAL restorations of the loss's consumptions, entered in the base unit (C-27; DATABASE §4.7); the same exact inverse used by CM-07's found goods (TASK-045), by CM-28's linked loss (ROUTE-212; TASK-040) and by CM-02's and CM-33's dependents restored first
- PG-027 with NAV-027 and ROUTE-027 (SCR-25): the forms of condition change, re-inspection and adjustment with the reason step and the cut block; the loss amount shown only with `cost.view` of its bearer (J-INV-06); increases never offered (L-15); its success PG-019 of the product with the answer (NAV-027)
- INV's part of `CompletionPredicateProvider` for unusable units — no unresolved unusable units whose causal project is the project (WF-PRJ-03 predicate 8) —, the predicate TASK-055's; loss recognition on such units is a residual command of a completed or cancelled project (WF-PRJ-03)

**OUT OF SCOPE:**

- SF-UNATTRIBUTED — the pending unexplained-loss or condition-change case across companies, its recognition from these forms, its conversion on disposal, its found stock and its resolution, with PG-029 (TASK-043); counts and their application (TASK-043)
- the delivery closure that calls the reclassification and the found goods' supersession (TASK-045); client returns and their cases (TASK-048); purchase returns and their outcomes (TASK-044); drop-ship returns (TASK-046); ROUTE-212's reversal entry on PG-020 (TASK-040); opening facts' correction (TASK-057)
- the reservation service and the cut block (TASK-038); the costing services (TASK-036); the stock views (TASK-040); QS-04, QS-21 and QS-22 on the home pages (TASK-058)

**REFERENCES:**

- [WORKFLOWS §5.3](../03-workflows/WORKFLOWS/s05-03-inventory-purchasing.md#wf-inv-04--condition-change-and-quarantine-floor) WF-INV-04; [WF-INV-06](../03-workflows/WORKFLOWS/s05-03-inventory-purchasing.md#wf-inv-06--inventory-adjustment-disposal-and-loss-floor); [§6](../03-workflows/WORKFLOWS/s06-07-subflows-partial.md#6-reusable-subflows) SF-LOSS, SF-RSV-CUT, SF-UNATTRIBUTED; [§8](../03-workflows/WORKFLOWS/s08-09-corrections-indivisible.md#8-correction-reversal-and-cancellation-matrix) CM-02, CM-07, CM-09, CM-11, CM-15, CM-16, CM-28, CM-33, CM-34; [§9](../03-workflows/WORKFLOWS/s08-09-corrections-indivisible.md#9-indivisible-business-actions) AX-06, AX-30; [§4](../03-workflows/WORKFLOWS/s01-04-foundations.md#4-actor-and-authority-matrix) the ADM+ stock actions; [§12](../03-workflows/WORKFLOWS/s10-14-signals-decisions-traceability.md#12-planner-determined-level-1-decisions) L-03, L-06, L-07, L-13–L-15, L-31, L-35, L-36, L-38, L-40, L-42, L-44, L-48, L-49; [WF-PRJ-03](../03-workflows/WORKFLOWS/s05-07-08-completion-migration.md#wf-prj-03--project-completion-penyelesaian-desk) predicate 8 and the residual commands
- [BUSINESS_RULES](../02-domain/BUSINESS_RULES.md#inventory-inv) BR-INV-01–BR-INV-13; [BR-RSV-04](../02-domain/BUSINESS_RULES.md#smreservation); [BR-FIN-12, BR-FIN-13](../02-domain/BUSINESS_RULES.md#finance-fin); [§5](../02-domain/BUSINESS_RULES.md#5-calculation-semantics) CALC-01, CALC-09, CALC-11; [the numeric annex](../02-domain/BUSINESS_RULES.md#numeric-annex-binding-examples-for-p8-fixtures-gap-004), examples 9, 15, 16; [§7](../02-domain/BUSINESS_RULES.md#7-forbidden-shortcuts) FS-01, FS-07
- [DATABASE §4.7](../04-architecture/DATABASE/s04-07-inv.md#47-inv--inventory) `stock_movements`, `stock_consumptions`, `stock_restorations`; [§5.3](../04-architecture/DATABASE/s05-inventory.md#53-movement-catalogue-and-sign-discipline); [§5.5](../04-architecture/DATABASE/s05-inventory.md#55-reservations-and-available); [§6.1](../04-architecture/DATABASE/s06-lot-cost-hpp.md#61-the-chain) loss and reclassification; [§6.2](../04-architecture/DATABASE/s06-lot-cost-hpp.md#62-conservation-identity-and-draw-arithmetic); [§6.5](../04-architecture/DATABASE/s06-lot-cost-hpp.md#65-inter-company-allocation-overlay); [§18](../04-architecture/DATABASE/s18-19-constraints-integrity.md#18-constraint-catalogue) C-01–C-04, C-07–C-09, C-24, C-27, C-28, C-33, C-47, C-55, C-61; [§25](../04-architecture/DATABASE/s25-28-transactions-handoffs.md#25-transaction-boundary-map) AX-06, AX-30
- [ARCHITECTURE §3](../04-architecture/ARCHITECTURE.md#3-modules) the tiers, `CompletionPredicateProvider`; [§7](../04-architecture/ARCHITECTURE.md#7-posting-services-and-guards) `InventoryPosting`, `CostPosting`
- [CONCURRENCY_IDEMPOTENCY §7.1](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s07-statement-sequences.md#71-lock-sets-of-ax-01ax-37) and [§7.2](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s07-statement-sequences.md#72-checks-effects-and-outcomes-of-ax-01ax-37) AX-06, AX-30; [§7.3](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s07-statement-sequences.md#73-command-families-outside-ax-0137) NX-05; [§5](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s04-06-locks-envelope.md#5-data-dependent-lock-set-protocol); [§8](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s08-09-guards-stale-state.md#8-guards-and-first-use-rows) FU-01, FU-02; [§9](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s08-09-guards-stale-state.md#9-stale-state) ST-03, ST-09; [§11](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s10-13-identity-failure-retry.md#11-separate-duplicates) BD-13; [§12](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s10-13-identity-failure-retry.md#12-failure-mapping) SQ-23; [§18](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s18-scenarios.md#18-mandatory-concurrency-and-failure-scenarios) M-04, M-30
- [PERMISSIONS_MATRIX §4](../05-security/PERMISSIONS_MATRIX.md#4-capability-catalogue) `stock.condition`, `stock.adjust`, `stock.move`, `stock.reverse`, `stock.allocate_intercompany`, `stock.reserve`; [§5](../05-security/PERMISSIONS_MATRIX.md#5-workflows-4-mapping) W4-13, W4-18, W4-21, W4-22; [§2](../05-security/PERMISSIONS_MATRIX.md#2-authorization-model) AZ-08; [§6](../05-security/PERMISSIONS_MATRIX.md#6-company-scope-rules) CS-05; [§7.2](../05-security/PERMISSIONS_MATRIX.md#72-table-classification) rows 47–51, 54, 56–59, 73–75; [§7.3](../05-security/PERMISSIONS_MATRIX.md#73-special-projections) PJ-01–PJ-08, PJ-22; [§8](../05-security/PERMISSIONS_MATRIX.md#8-cross-company-link-authorization) XL-1
- [ROUTE_CONTRACTS](../03-workflows/ROUTE_CONTRACTS.md#warehouse--receiving-reservations-dispatch-counts-adjustments-and-discrepancies-inv-pg-018pg-029) ROUTE-190, ROUTE-194–ROUTE-196; [§2](../03-workflows/ROUTE_CONTRACTS.md#2-page-views--route-001route-065) ROUTE-027; [INTERACTION_CONTRACTS](../03-workflows/INTERACTION_CONTRACTS.md#warehouse--receiving-reservations-dispatch-counts-adjustments-and-discrepancies-inv-pg-018pg-029) INT-190, INT-194–INT-196
- [NAVIGATION_CONTRACTS §2.3](../07-ux-design/NAVIGATION_CONTRACTS.md#23-purchasing-and-warehouse) NAV-027; [INFORMATION_ARCHITECTURE §8.3](../07-ux-design/INFORMATION_ARCHITECTURE.md#83-purchasing-and-warehouse) PG-027; [§8.7](../07-ux-design/INFORMATION_ARCHITECTURE.md#87-the-approved-screens-scr-01scr-60-and-their-pages) SCR-25
- [ADMIN_FLOW §10.3](../07-ux-design/ADMIN_FLOW/s10-a-journeys.md#103-inventory-and-purchasing) J-INV-04, J-INV-06; [§10.1](../07-ux-design/ADMIN_FLOW/s10-a-journeys.md#101-access-and-master-data) J-MD-05; [§11](../07-ux-design/ADMIN_FLOW/s11-13-corrections-numbering-import.md#11-corrections-and-reversals) CM-07, CM-09, CM-28, CM-34; [IP-09](../07-ux-design/ADMIN_FLOW/s05-command-model.md#ip-09--corrections-closures-and-destructive-actions); [IP-16](../07-ux-design/ADMIN_FLOW/s06-09-session-forms-lists.md#ip-16--evidence-upload); [IP-20](../07-ux-design/ADMIN_FLOW/s06-09-session-forms-lists.md#ip-20--choosing-a-reservation-cut); [§15](../07-ux-design/ADMIN_FLOW/s14-15-messages-scenarios.md#duplicates) UXS-15; [UXS-22](../07-ux-design/ADMIN_FLOW/s14-15-messages-scenarios.md#visibility-and-authority); [§14](../07-ux-design/ADMIN_FLOW/s14-15-messages-scenarios.md#14-message-catalogue) MSG-14, MSG-15, MSG-40
- [TEST_STRATEGY §5](../08-testing/TEST_STRATEGY.md#5-domain-and-data-integrity) the brief's inventory tests and FX-01 15–16; [§7](../08-testing/TEST_STRATEGY.md#7-browser-end-to-end-tests-and-critical-journeys) CJ-05; [SECURITY_TEST_MATRIX](../08-testing/SECURITY_TEST_MATRIX.md#3-the-matrix) SM-01, SM-04, SM-25; [PERFORMANCE_TARGETS](../08-testing/PERFORMANCE_TARGETS.md#3-the-measured-targets) PFM-01, PFM-02, PFM-20
- [GAP_REGISTER](../00-governance/GAP_REGISTER.md#gap-028--inventory-loss-and-damaged-return-cost-could-disappear) GAP-028
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (76): BR-INV-01–BR-INV-13; CALC-01; CALC-09; CALC-11; FS-01; WF-INV-04; WF-INV-06; SF-RSV-CUT; SF-LOSS; CM-02; CM-07; CM-09; CM-11; CM-15–CM-16; CM-28; CM-33–CM-34; AX-06; AX-30; L-03; L-06–L-07; L-13–L-15; L-31; L-35–L-36; L-40; L-42; L-44; L-48; ROUTE-027; ROUTE-190; ROUTE-194–ROUTE-196; INT-190; INT-194–INT-196; C-01–C-04; C-07–C-09; C-24; C-28; C-33; C-47; C-61; NX-05; BD-13; PG-027; SCR-25; J-INV-04; J-INV-06; UXS-15; UXS-22; NAV-027; GAP-028

**EXPECTED MODULES / FILE AREAS:**

- the INV module's ChangeCondition, Reinspect, MoveLocation, AdjustStock, RecognizeLoss and ReverseLoss actions (RC-02), INV's loss service and `InventoryPosting`'s condition, move, decrease and loss paths, `ShowAdjustments`; `resources/js/Pages/INV` for PG-027 and the move flow in `resources/js/features/inv`; `tests/Feature/INV`, `tests/Concurrency/` and `tests/Reconciliation/`

**BUSINESS RULES:**

- BR-INV-13 and SF-LOSS (DIR-024 D-3): disposal, shrinkage, loss in transit and client-damaged returns never make cost disappear — the loss is recorded exactly once at the lot's actual cost, charged to the causal project when known and otherwise to the owning company, with movement, provenance, reason, evidence, actor and time kept; where the cost already sits in a project's HPP it is reclassified, never added again (annex 15, 16; GAP-028)
- L-38: the causal project of a loss is a damaged-return case's returning project, the project of a shipment lost in transit, or the project of a line damaged at its receipt; otherwise the lot's owning company; L-44 with L-03: a loss charged to a project of another company than the lot's records one allocation on its loss consumption, authorized by ADM+ inter-company authority with a grant on the bearer company; a reclassification keeps the dispatch's allocation
- L-15 and WF-INV-06: adjustments only decrease; increases come only from receiving, opening lots, drop-ship returns, restorations, linked reversals of a wrong decrease, the exact reversal of a pending case or Owner-attributed surplus (C-07)
- BR-RSV-04 with SF-RSV-CUT: a condition change, an adjustment or a loss that would leave usable supply below the reservations carries the actor's cut in the same action (annex 9; C-06)
- L-13 and BR-INV-01: usable and unusable tracked per lot and serial; a condition change moves units between them on the same lot and rack, a unit never in RESERVED and UNUSABLE at once; UNUSABLE returns to usable only by an explicit re-inspection with its reason (WF-INV-04)
- L-36: a location move is a zero-net relocation of the same lot and condition (CALC-01 unchanged); L-42: unidentified fungible units within one company are confirmed by the actor, oldest first only as a picking aid; across companies the Owner decides through the pending case (DIR-026; DIR-027; TASK-043)
- CM-07, CM-09, CM-11, CM-15, CM-16 and CM-34: damage in the warehouse is a condition change, later repaired, returned or disposed with its loss; a loss in transit is reclassified on the shipment's project; a client-damaged return's disposal is its case's single loss on the causal project; an unrecovered supplier-return share is recognized once on its bearer; a wrong loss is reversed exactly, its units returning only by a linked reversal of its stock-out
- CM-02, CM-28 and CM-33: a wrong receipt, adjustment, condition change or opening fact whose units were lost is corrected after the loss's exact reversal restores its dependents — an opening fact by its import identity, never by importing again (C-47) —; a linked reversal never exceeds the original net of earlier reversals (C-55)
- CALC-09 and CALC-11: project-attributed losses are project direct cost and company-level losses reduce company profit, each once (BR-FIN-12; BR-FIN-13 for the split of a loss over its units)
- the inventory rule set shared with TASK-038–TASK-041 and TASK-043, as it reaches these commands: BR-INV-02–BR-INV-04 and BR-INV-08 — legitimate movements only, base units at the product's scale (C-09), named lots, every quantity recomputable —; BR-INV-05–BR-INV-07 and BR-INV-09–BR-INV-12 — allocations, returns, counts, serials, restock advice, drop-ship and opening lots as these commands meet them —; L-07 — a loss's restoration never reactivates a reservation —; L-14 — an adjustment or condition change in a counted scope makes the count stale (C-33) —; L-31 — loss-reversal restorations are entered in the base unit —; L-35 — a changed serial policy meets the command as CONFLICT (ST-09) —; L-40 — drop-ship units create no warehouse event to adjust —; L-48 — a serial found to be wrongly dispatched is corrected by substitution, not by an adjustment (CM-36) —; FS-01 — no stored workflow status; L-06, mapped here through its ground BR-INV-13, governs invoice reductions and has no adjustment effect

**ARCHITECTURE RULES:**

- INV is tier 5 (ARCHITECTURE §3): its loss service posts LOSS rows through CST's `CostPosting` inside the calling action's transaction — INV's own actions here, and FUL's delivery closure and INV's and FUL's returns in their Tasks —, and never calls a higher tier
- one loss path: every loss, reclassification, unrecovered share and loss reversal goes through the loss service, so a unit is lost once, a reclassification is a pair of rows and never a second draw, and the exact inverse restores what the loss removed (DATABASE §6.1, §6.4)
- a movement that created consumptions is undone only by restorations of those consumptions, a condition change or a move by a linked reversal or the opposite command (DATABASE §5.3)

**SECURITY / PERMISSION REQUIREMENTS:**

- ROUTE-027 `stock.condition` or `stock.adjust`; ROUTE-195 `stock.condition` (ADM_PLUS, W4-21); ROUTE-194 `stock.adjust` (ADM_PLUS, W4-21), a bearer company other than the lot's also `stock.allocate_intercompany` (W4-22; XL-1); ROUTE-190 `stock.move` (ADM, W4-13); ROUTE-196 `stock.reverse` (ADM_PLUS, W4-18); a cut in any of them also `stock.reserve` (IP-20)
- a loss and its exact reversal need the bearer company in scope — the causal project's company, else the lot's owning company —, otherwise REJECTED and routed; a condition change, re-inspection or move changes only physical pool state and needs only its capability and one company in scope (AZ-08; CS-05)
- the lots, serials, rack and evidence resolved through the scope resolver; the causal project, bearer, cost and allocation derived, never taken from the payload (CS-04; DP-03; WS-02); evidence of shared stock uploaded as pool evidence, its uploader confirming that scope, each pool upload reaching the Owner (PJ-08)

**COMPANY SCOPE AND FIELD PROJECTION:**

- the commands act on pool records (CS-05): racks, balances, lots' physical attributes and serials SV (rows 47–49, 57–59); a movement of a company in scope or a pool movement itemized in the history (PJ-03; rows 50, 51); another company's lot shown with the relation marker only (PJ-04)
- LOSS consumptions and cost rows: the bearer side CV(bearer c) with source identifiers masked, the source side CV(source c) with the bearer masked (rows 54, 75; PJ-05; PJ-06); the loss amount on PG-027 only with `cost.view` of the bearer (J-INV-06); a company's losses inside its own figures only (PJ-22)
- restorations of a loss PV(c) of the case or bearer company (row 56); lot cost CV(source c) (rows 73, 74)

**CONCURRENCY / IDEMPOTENCY:**

- AX-06: DATA-DEPENDENT, LK-00, LK-01, LK-03, LK-10, LK-11, LK-12, LK-13, LK-14, LK-15, LK-17, LK-18, LK-19, LK-23, LK-24, LK-29 with FU-01, FU-02, FU-06 and FU-11, holding the causal projects and the projects of the cut reservations at LK-01; re-read under lock: the named lots holding the quantity, the L-42 confirmation, the cuts; net-zero groups checked at commit; identity BD-13 — the case, otherwise the scope and lot —; refusals C-03, C-06, C-07, L-49, AZ-08; retry class B (CONCURRENCY_IDEMPOTENCY §7.1, §7.2)
- AX-30: STATIC, LK-00, LK-01, LK-03, LK-10, LK-13, LK-14, LK-15, LK-17, LK-18, LK-19, LK-23, LK-24, LK-29; re-read: the named lots holding the quantity, the serial's state, the causal project (L-38), the bearer company in scope, a reclassification ≤ the open quantity, the cuts; identity BD-13; refusals C-24, C-27, C-06, L-49, AZ-08; the reversal the exact inverse through restorations; retry class A
- NX-05 for the move and the re-inspection: LK-00, LK-01 — only when a project is affected —, LK-03, LK-12, LK-13, LK-14, LK-15, LK-17; first-use scope and lot rows (FU-01; FU-02); concurrent first use of one scope or lot creates one row (M-30)
- a movement racing a count of its scope leaves the count STALE at its application (M-04; ST-03; C-33); a likely duplicate is REJECTED with MSG-15 unless resubmitted with the actor's reasoned confirmation naming the matches (L-49; BD-13); every command carries `command_id`, replayed with the recorded outcome (CI-01; CI-05)

**AUDIT:**

- every condition change, re-inspection, move, adjustment, loss and reversal is one business audit event in its transaction with the individual actor, the reason, the evidence references and the records affected (BR-XC-01; BR-CR-01; LG-01) — the adjustment's reason and audit event among the brief's required inventory tests (TEST_STRATEGY §5) —; ADM_PLUS for `stock.condition`, `stock.adjust` and `stock.reverse`, each reaching the Owner review queue (AZ-10; QS-14)
- the LOSS rows name their bearer, cause and, for a reversal, what they reverse (`reverses_attribution_id`); a routed refusal commits one audit event per affected company (SQ-23); a likely-duplicate confirmation is audited under its acknowledged variant (QS-21); cross-company keys projected per side (PJ-11)

**DATABASE IMPACT ASSESSMENT:**

- Schema change: NONE — uses the tables of TASK-005–TASK-009; a missing column is an executor discovery (AGENT_OPERATING_MODEL)
- Migration required: NO
- Existing data transformation: NONE
- Direct production DB access: FORBIDDEN
- Data-loss risk: NONE KNOWN

**MODULARITY / EXTENSIBILITY:**

- Module boundary affected: INV, tier 5 — its loss service called by INV's and FUL's actions, posting through CST (ARCHITECTURE §3, §7)
- Stable core kept provider-neutral: YES
- External/vendor-specific code isolated: YES — framework facilities only (ARCHITECTURE §2)
- Replaceable adapter/contract required: NO
- Provider lock-in risk: NONE beyond the Owner's baseline stack
- Future extension impact: MEDIUM — counts, deliveries and returns post their losses through this service
- Speculative abstraction avoided: YES — no automatic attribution rule, FIFO or valuation method (DIR-026; FS-13)

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

- Context7: REQUIRED — PostgreSQL deferred constraint triggers for the net-zero groups and row locks in the canonical order, at the installed version
- Graphify: OPTIONAL — once counts, deliveries and returns call the loss service
- Browser E2E: REQUIRED — J-INV-04 and J-INV-06 on the phone and the desktop, the move from PG-019 (J-MD-05), a routed loss on another company's bearer (UXS-22) and CJ-05's condition change, disposal and loss
- Other: the synthetic group FX-02 with a shared pool of two companies; the numeric annex's examples 9, 15 and 16; fixture dispatches, cases and returns until TASK-045 and TASK-048 add the commands that call the reclassification and the case's loss
- Missing safe tool behavior: AUTO-INSTALL project-locally inside this Task (AICWDF §6.3) and record it in TOOLCHAIN; a paid tier or a privileged installation STOPS for the Owner (COST_POLICY)

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: refreshed before the change when Graphify is available ([TOOLCHAIN](../00-governance/TOOLCHAIN.md)); otherwise a targeted search of INV and of the calls into the loss service and CST
- Pre-change questions: which commands recognize a loss, how each determines its causal project, and which reverse it
- Expected affected areas: INV's posting paths, its loss service and PG-027; CST's LOSS rows; the later callers in FUL and INV

**CONTEXT7:**

- Topics to verify: deferred constraint triggers evaluated once per movement at commit; conditional serial-state updates; Inertia forms with the reason step and the cut block
- Version-sensitive assumptions: none from memory

**UI / UX REQUIREMENTS:**

- Follow global design rules: YES
- Indonesian default: YES
- English support: YES
- Plain-language Indonesian: YES — the form stating where the loss is recorded (J-INV-06); *Layak pakai* and *Tidak layak pakai*; MSG-14 for a routed refusal, MSG-15 for a likely duplicate, MSG-40 for the reasons
- Language switch impact: keeps unsent input
- Mobile-first: YES — `[floor]`, phone first: scan the unit, state the condition, photograph (J-INV-04; NAV-027)
- Responsive: YES
- Adaptive: YES — one form per command, a full-height sheet on the phone with the actions in a sticky bar (NV-06; PT-16)
- Visual breathing room: REQUIRED
- Comfortable density: REQUIRED
- shadcn/ui reuse: the scan field (PT-20), the form layout (PT-09), the evidence upload (IP-16), the cut block (IP-20; TASK-038), the quantity block (PT-17), the confirmation with its reason (PT-26), status badges (PT-13), inline messages (PT-14)
- Design reference: PG-027 as INFORMATION_ARCHITECTURE §8.3 and J-INV-04, J-INV-06 state it; the Golden Frontend Reference for presentation only (DIR-061 SD-02)
- Primary action: the form's command — "Ubah Kondisi" or "Catat Penyesuaian Stok"
- Secondary actions: "Periksa Ulang"; "Pindah Lokasi" from PG-018 and PG-019
- Destructive actions: "Catat Penyesuaian Stok" for a disposal or loss and ROUTE-196's reversal, each with its reason and a destructive confirming button (IP-09; PT-11)
- Redundancy constraints: no increase offered by an adjustment (L-15); one command per intent with its own answer; a likely duplicate continued only by a reasoned confirmation (IP-05)
- Loading state: PT-19
- Empty state: N/A — PG-027 is a form opened for a product or from *Gudang* › *Lainnya*, never an empty list
- Error state: the quantity a named lot does not hold, a missing cut, MSG-14, MSG-15 and C-24's and C-07's refusals in words; MSG-12 at the fields (IP-14)

**ROUTE IMPACT:**

- ROUTE-027 (PG-027's view); ROUTE-190 (move), ROUTE-194 (adjustment decrease or loss), ROUTE-195 (condition change or re-inspection), ROUTE-196 (a loss's exact reversal); PG-018's and PG-019's entries to them (TASK-040); the loss service called inside ROUTE-203 (TASK-045) and ROUTE-177 (TASK-044) by their Tasks

**INTERACTION CONTRACTS:**

- INT-190, INT-194–INT-196; the IP-20 block inside them (TASK-038); INT-013 for the evidence; INT-015 for a likely duplicate (TASK-013); INT-009 for unsent input

**NAVIGATION CONTRACT:**

- Parent: PG-019 · *Gudang* (NAV-027)
- Entry: *Gudang* › *Lainnya*; the product page PG-019 — "Ubah Kondisi" from a lot or serial, "Catat Penyesuaian Stok"; "Pindah Lokasi" from PG-018 and PG-019 (NAV-027; J-MD-05)
- Back action: "Kembali" → the origin (NAV-027)
- Cancel: "Batal" → the origin, MSG-35 first with unsent input; the reason step's "Kembali" → the form, the input kept (NV-02)
- Success destination: PG-019 of the product with the answer (NAV-027); a move in place (ROUTE-190)
- Error behavior: NV-04 — validation at the field, a rejected or routed command in place with the input kept (IP-06)
- State preservation: unsent input kept across re-login; MSG-35 before leaving (NAV-027; NV-05)
- Mobile behavior: `[floor]` — phone first, the form as a full-height sheet (NAV-027; NV-06)

**ACCEPTANCE CRITERIA:**

1. "Ubah Kondisi" on 2 usable units of a named lot moves them to UNUSABLE on the same lot and rack with their reason and evidence — ON HAND unchanged, AVAILABLE down by 2 —; a condition change that does not net to zero per lot and rack cannot commit (WF-INV-04; C-07; BR-INV-01).
2. With RESERVED 8 and a usable supply of 8, a condition change or loss of 2 usable units commits only with the actor's cuts totalling 2, together with it, the affected projects flagged (annex 9; BR-RSV-04; C-06; IP-20).
3. "Periksa Ulang" returns UNUSABLE units to usable only by its explicit decision with a reason (WF-INV-04).
4. Unidentified fungible units whose candidate lots all belong to one company are recorded only after the actor confirms each lot, the oldest shown first as a picking aid; nothing is attributed to a company automatically (L-42; DIR-026).
5. "Pindah Lokasi" of 5 units moves exactly 5 of one lot and condition from one rack to another, a move of −5/+6 is REJECTED, and no reservation or cost changes (L-36; C-07; J-MD-05).
6. "Catat Penyesuaian Stok" for 2 shrunk units of a lot costing 10,000 each removes them and records one LOSS of 20,000 on the owning company in the same transaction; no adjustment form offers an increase (WF-INV-06; L-15; SF-LOSS; C-24).
7. The disposal of a client-damaged unit of lot cost 50,000 whose case names project X records one LOSS of 50,000 on X as the case's single loss outcome (annex 16; L-38; CM-11).
8. Units damaged at the receipt of a project-linked line and later disposed are charged to that project (L-38).
9. A loss charged to a project of another company than the lot's records one allocation on its loss consumption with its reason and authority; without `stock.allocate_intercompany`, or without the bearer company in scope, the command is REJECTED and routed with MSG-14 (L-44; XL-1; AZ-08; SQ-23; UXS-22).
10. Two units dispatched to X at 50,000 each with one lost in transit give, through the delivery closure's call, the pair HPP −50,000 / LOSS +50,000 on X with no stock movement and the dispatch's allocation kept; X's cost stays 100,000 (annex 15; CM-07; SF-LOSS).
11. A loss's exact reversal contras the loss, re-attributes any reclassified HPP and reverses its allocation in one action, and its units come back only through capped restorations, never more than were lost (CM-34; C-27; C-55).
12. A unit is lost once: a second loss of the same serial or of quantity the lot no longer holds is REJECTED (C-24; C-03; BR-INV-13).
13. The unrecovered share of a supplier return is recognized once on its causal project as a cost-only loss, with an allocation when the project's company differs from the lot's (CM-15; CM-16; L-44).
14. A condition change or adjustment matching another of the same case, otherwise the same scope and lot, quantity and business date is REJECTED with MSG-15 and commits only with the actor's reasoned confirmation naming the match (L-49; BD-13; UXS-15).
15. A condition change, move or adjustment in a scope whose count is open makes that count STALE when it is applied, and nothing is overwritten (M-04; L-14; C-33).
16. The loss amount appears on PG-027 only with `cost.view` of its bearer, and the source company's identity, supplier and price stay masked for the bearer's viewer (J-INV-06; PJ-06; SM-25).
17. A condition change, re-inspection or move needs only its capability and one company in scope; a loss and its reversal need the bearer company in scope (AZ-08; CS-05).
18. Without `stock.condition`, `stock.adjust`, `stock.move` or `stock.reverse` its controls are absent and its route refused (IG-01; SM-04).
19. Each of ROUTE-190 and ROUTE-194–ROUTE-196 replayed with one `command_id` returns the recorded outcome, and with a changed payload is CONFLICT (CI-05; M-09).
20. After every scenario the reconciliation suite finds the stock, consumption and lot-cost guards equal to their facts, and each lot's cost entries equal its remaining cost plus its net draws (BR-INV-08; C-26; C-61).

**TEST REQUIREMENTS:**

- Lint: TL-01
- Typecheck: TL-02
- Unit: the causal-project rule of L-38 and the loss's split over its units at the lot's current net cost (annex 15, 16; TL-03)
- Feature: ROUTE-027, ROUTE-190 and ROUTE-194–ROUTE-196 with each refusal — C-03, C-06, C-07, C-24, C-27, L-49, AZ-08, ST-09 — and the reason and audit event of every adjustment (TL-04)
- Integration: the net-zero trigger for a −5/+6 move and an unbalanced condition change (C-07); a loss racing a dispatch of the same lot at LK-15 and LK-23; a cut with the reservation service; the reclassification and the exact reversal with fixture dispatches and cases; M-30; CM-28's linked reversal of these Tasks' movements end to end through ROUTE-212 (TASK-040); C-28's DQ after cross-company losses; the guard CHECKs C-01–C-04, C-08 and C-09 by violating writes; the reconciliation suite (TL-05)
- Authorization: `stock.condition`, `stock.adjust` and `stock.reverse` as ADM_PLUS, `stock.move` as ADM, `stock.allocate_intercompany` for a cross-company bearer, `stock.reserve` for a cut, the bearer company in scope (TL-06)
- Route integrity: ROUTE-027, ROUTE-190 and ROUTE-194–ROUTE-196 audited (TL-07)
- Interaction: INT-190 and INT-194–INT-196, each with one `command_id` replayed (TL-08; IG-05)
- Browser E2E: J-INV-04 and J-INV-06 — CJ-05's condition change, adjustment, disposal and loss —, the move from PG-019 on the phone, UXS-15 and UXS-22 (TL-09)
- Localization/language switch: both languages on PG-027 (TL-10)
- Visual breathing room/density: TEST_STRATEGY §9 for PG-027
- Responsive: TL-11
- Accessibility: TL-12, the reason and cut fields reachable and announced
- Production build: TL-13
- Security: SM-01 for a foreign lot, serial or evidence; SM-04 for W4-13, W4-18, W4-21 and W4-22; SM-25 for the loss amount by family (TL-14)
- Performance: PFM-01 and PFM-02 for PG-027; PFM-20 for a condition change and an adjustment of ten lots; QB-14 for AX-06's class B (TL-15)
- Migration: N/A — no migration or import code in this Task
- Other: M-04's movement racing a count, run with TASK-043; FX-03's unknown-company loss and damaged-return scenarios as far as this Task reaches them; GAP-028's evidence that no cost is lost or counted twice

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

- TASK-043 — when its other dependencies are DONE and its READY gate holds

**DO NOT DO:**

- add to V1 — a capability, page, route, field, integration, document type, report or paid service — or drop, defer or narrow a V1_REQUIRED requirement; a Task that would need either raises an Owner question and waits ([DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-20)
- access the VPS, production, its data or a credential, or put a secret, real data, a host detail or production configuration in the repository ([INFRASTRUCTURE](../09-operations/INFRASTRUCTURE.md) INF-01, INF-05; SECURITY SX-01, SX-04, SX-06; [PRODUCTION_DATA_SAFETY](../00-governance/PRODUCTION_DATA_SAFETY.md))
- add AI, model or tool attribution to a commit — the Owner's configured identity only ([AGENTS.md](../../AGENTS.md#git-commit-attribution); DIR-022)
- start before the Owner's execution authorization at OG-4, or mark DONE without the required tests passing and the completion evidence ([TASK_PLAN](TASK_PLAN.md#2-rules-of-the-plan); [DEFINITION_OF_DONE](../08-testing/DEFINITION_OF_DONE.md#2-the-checklist))
- weaken, skip or delete a valid test to obtain a green run, or adopt a paid tier without the Owner's COST_POLICY decision (DOD-05; [COST_POLICY](../00-governance/COST_POLICY.md))
- increase stock through an adjustment, or let a move or condition change create or destroy quantity (L-15; L-36; C-07)
- recognize a loss twice, apart from its stock-out, or by a second draw where the cost sits in HPP (BR-INV-13; AX-30; DATABASE §6.4)
- attribute unidentified units across companies by FIFO, oldest-first, proportion or any automatic rule (DIR-026; L-42)
- commit a decrease below the reservations without the actor's cut, or charge a loss to a bearer outside scope without routing it (BR-RSV-04; AZ-08)
- return a lost unit to stock other than through the exact loss reversal (CM-34; SF-LOSS)

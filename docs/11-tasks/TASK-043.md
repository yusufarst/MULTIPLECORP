# TASK-043 — Stock count (opname) and discrepancies

Status: REVIEW | Updated: 2026-10-20 | Owner: Planning

Written by WF-PC5-W's writer of bundle W2 of run PC-5 on 2026-10-19 and 2026-10-20 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99 — a REVIEW draft of P11, approved, or not, with the [Task plan](TASK_PLAN.md) at OG-4. **No Task starts before the Owner's execution authorization at OG-4.** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** BLOCKED — waiting on P11Q-02, P11Q-04

**RISK CLASS:** HIGH

**EXECUTOR:** AGENT

**OBJECTIVE:**

Build the stock count (*Stok Opname*) and its discrepancies: a count opened per scope — product × location × condition, plus serials — against the system basis at its start, its counted lines, the ADM+ application of its variance with the stale-count recount, the named-lot loss, the pending unexplained-loss case and the reservation cut a shortage needs, the reversal of pending or identified losses a surplus allows and the open count finding it otherwise becomes, and on PG-029 *Selisih & Temuan* the Owner's surplus attribution, the Owner's and the evidence-identified resolution of a pending case and their exact reversal (WF-INV-05; SF-UNATTRIBUTED; AX-06, AX-07, AX-37; PG-026, PG-029; ROUTE-191–ROUTE-193, ROUTE-197–ROUTE-200).

**WHY THIS EXISTS:**

CAP-06 and AC-06 require opname within the shared pool, and BR-INV-07 forbids applying a count as an absolute number: a count recorded before a concurrent receipt or dispatch would overwrite newer stock (GAP-009). An unexplained fungible loss across companies may never be attributed by FIFO, oldest-first or any other automatic rule — the Owner decides against an immutable recognition snapshot while no stock is frozen (BR-INV-13; DIR-026; `DIR-027`; GAP-030) —, and found stock may enter only as the Owner attributes it, with a flagged cost basis (L-15; BR-INV-04). The Owner's decision rows on *Beranda* (QS-18, QS-22; TASK-058) need this Task's findings and cases.

**DEPENDS ON:**

- TASK-042 — Condition, location moves, adjustments and losses
- the Owner's answers to P11Q-02 and P11Q-04 recorded and the narrow amendment they authorize approved ([TASK_PLAN §8](TASK_PLAN.md#8-owner-questions)) — BLOCKED until then

**BLOCKS:**

- TASK-055 — Project completion and Force Complete
- TASK-058 — Home, Work and Review — signals and the action queue

**PARALLEL SAFE:** NO — its count application and resolutions write the product, scope, lot, serial, reservation, lot-cost and consumption guards that TASK-038–TASK-042 also post through `InventoryPosting` and `CostPosting`, and the unattributed-case counters TASK-042 opens

**IN SCOPE:**

- the INV count and discrepancy records of DATABASE §4.7 in use (§5.6; §5.8): `stock_counts` with its states OPEN, APPLIED and STALE, `stock_count_scopes` with the typed `basis_version` and `system_qty_at_start` inserted only when the count opens, `stock_count_lines` with their counted quantity, serial or unregistered serial text and counter, `stock_count_findings` — one OPEN finding per product, location and condition (C-34) —, and the case tables `unattributed_loss_cases`, `_candidates`, `_exposures`, `_group_exposures`, `_resolutions` and `_resolution_lines`; the movement types COUNT_VARIANCE, LOSS, SURPLUS_ATTRIBUTION and the UNATTRIBUTED_RECOGNITION, _CONVERSION, _FOUND, _RESOLUTION and _RESOLUTION_REVERSAL rows with their sign and net-zero rules (DATABASE §5.3; C-07)
- ROUTE-191 with INT-191 (`stock.count`; NX-05): "Mulai Hitungan" on PG-026 — and on PG-019, TASK-040's page, as IA §8.3 places it — for a scope of product, rack and condition, the scope versions read under LK-14 and stored as the counted basis (L-14); "Hitung Ulang" after a stale count (MSG-30)
- ROUTE-192 with INT-192 (`stock.count`; NX-05): the counted lines, one request and one answer per line (IP-19), scan-led on the phone (SF-SCAN; IP-15; PT-20) — a repeated scan of one serial counting once, an unknown or ambiguous code never selected (MSG-25, MSG-37); an unexpected serial — one recorded as dispatched or not registered — recorded as a finding, never silently re-registered, its correction through the CM rows, typically CM-36 (WF-INV-05; L-48; TASK-041)
- ROUTE-193 with INT-193 (`stock.count_apply`; AX-06): the application re-reading every counted scope under its locks — a scope moved after the count started makes the count STALE with nothing overwritten (ST-03; C-33; MSG-30); a **negative variance** naming its lots from evidence, an unidentified fungible shortage confirmed by the ADM+ actor when its candidate lots are of one company, the oldest-first order shown only as a picking aid (L-42; BR-INV-04), or recorded as a pending unexplained-loss case with its immutable recognition snapshot when they are of several (SF-UNATTRIBUTED; C-36), never FIFO, oldest-first or proportional; SF-LOSS once per lost unit at its current net lot cost on its bearer in the same action (L-38; BR-INV-13; C-24), a unit under an open case becoming that case's single loss outcome; the reservation cut of IP-20 when usable supply would fall below the reservations (SF-RSV-CUT; BR-RSV-04; C-06); a **positive variance** first reversing a pending case of the scope exactly, then an identified loss only when a serial, a single candidate lot or company, or entry-error evidence identifies the units, otherwise becoming one open finding per scope and product whose units are not added to ON HAND (AX-06; L-15); the likely-duplicate warning of a fungible event (L-49; BD-13; INT-015)
- ROUTE-197 with INT-197 (`stock.attribute_surplus`, OWNER_ONLY; AX-07): the Owner's attribution of an open finding to an owning company with an explicit flagged cost basis, creating the flagged lot once (SURPLUS_ATTRIBUTION; BR-INV-04; C-08), the scope's pending losses and identified losses reversed first, and a count of that scope started before it becoming stale (OD-08)
- ROUTE-198, ROUTE-199 and ROUTE-200 with INT-198–INT-200 (AX-37): the Owner's assignment of a pending case's quantity to candidate companies within each one's recognition exposure and the overlap group's bound (C-37; FU-06), closing that quantity of lot claims in the scope — the assigned company's own lots first, explicitly confirmed, any shortfall from other lots the Owner chooses, each carrying an inter-company allocation (WF-INV-07; L-44; OD-09) — with SF-LOSS once at the closed claims' current net cost; the ADM+ evidence-identified resolution only under every condition of OD-10 (`DIR-027`); the exact reversal of a resolution, once, the snapshot and every later movement unchanged (CM-27; C-35); a condition-change case resolved only onto usable claims the assigned company still holds (SF-UNATTRIBUTED)
- found stock: a later surplus in a scope with a pending loss case reversing the pending quantity exactly — UNATTRIBUTED_FOUND, no loss ever recognized — and, after resolution, the exact SF-LOSS reversal, so nothing is recognized twice (DATABASE §5.8; C-35)
- PG-026 with NAV-026 and ROUTE-026 (SCR-24; J-INV-05): the counts with their lines, state and variance, "Terapkan Selisih" with its reason and cut; the page's print view for the count sheet as DESIGN_SYSTEM §17 states a print view (DP-19)
- PG-029 with NAV-029 and ROUTE-029 (SCR-27): the open findings and the pending cases with their recognition snapshot as PJ-07 projects it, the separate unattributed line of MSG-41 (UXS-39), "Tetapkan Surplus", "Tetapkan Perusahaan", "Tetapkan dengan Bukti" and "Balikkan Penetapan", each with its reason (IP-09)
- the registration count of L-35 — a count that records a serial for every on-hand unit before a product is switched to serialized (WF-MD-04) — as count lines only; the switch is TASK-027's
- the pending-case branch of TASK-042's ROUTE-194 and ROUTE-195, extended here (Part 4; TASK-042 hands it to this Task's case service): an unexplained loss across companies recorded through an adjustment, a disposal or a condition change opens its pending case (QS-22; SF-UNATTRIBUTED) — never a named loss —, shown as J-INV-04's *Selisih menunggu penetapan*, and the unit's later disposal converts it

**OUT OF SCOPE:**

- the exact loss reversal outside a count — TASK-042 (ROUTE-196); the linked reversal of a wrong count application or of a pending-case recognition or finding (CM-28; NX-11; ROUTE-212) is TASK-040's command, which this Task's movement rows must admit (C-55)
- the stock views PG-018–PG-020 and their pending-case line (TASK-040); QS-18 and QS-22 on *Beranda* and *Pekerjaan* (TASK-058); the reports' unattributed lines (TASK-060); the scan field's lookup ROUTE-282 with INT-006 (TASK-039)
- the dispatch identity substitution that corrects an unexpected serial (CM-36; TASK-041); the serial-policy switch (TASK-027)
- the contra of a settlement, expense, disbursement or attribution that the coverage line lists — CM-22, CM-38 and AX-33 (ROUTE-260): SF-CONTRA's users are payments, disbursements, expenses, settlements and purchase cost (WORKFLOWS §6), built by TASK-052 and TASK-054; a count's facts are corrected by CM-28's linked reversal with its exact loss reversal instead
- the other coverage entries whose source rows concern no count — L-06, an invoice void's dispositions (TASK-049); L-40, a drop-ship confirmation with its delivery (TASK-046); C-47, the opening import's batch and row identity (TASK-057)
- cancelling an open count, which `stock.count` covers (PERMISSIONS_MATRIX §4) and `stock_counts` would hold, but for which no route or interaction contract exists (ROUTE_CONTRACTS §3) — held for Planning, never built as an unrouted control (RC-06)
- how a registration count assigns its serials to lots, which no source states (DATABASE §8 names only the switch's precondition) — held for Planning
- a visible print control on PG-026: ROUTE_CONTRACTS §4 does not mark one (RC-06) — held for Planning; the print style alone is in scope

**REFERENCES:**

- [WORKFLOWS §5.3](../03-workflows/WORKFLOWS/s05-03-inventory-purchasing.md#wf-inv-05--stock-opname-floor) WF-INV-05, with [WF-INV-04](../03-workflows/WORKFLOWS/s05-03-inventory-purchasing.md#wf-inv-04--condition-change-and-quarantine-floor) and [WF-INV-07](../03-workflows/WORKFLOWS/s05-03-inventory-purchasing.md#wf-inv-07--inter-company-allocation-embedded-in-dispatch); [§6](../03-workflows/WORKFLOWS/s06-07-subflows-partial.md#6-reusable-subflows) SF-UNATTRIBUTED, SF-RSV-CUT, SF-LOSS, SF-SCAN, SF-CONTRA; [§8](../03-workflows/WORKFLOWS/s08-09-corrections-indivisible.md#8-correction-reversal-and-cancellation-matrix) CM-09, CM-27, CM-28, CM-34, CM-36; [§9](../03-workflows/WORKFLOWS/s08-09-corrections-indivisible.md#9-indivisible-business-actions) AX-06, AX-07, AX-37; [§10](../03-workflows/WORKFLOWS/s10-14-signals-decisions-traceability.md#10-derived-signals-and-action-queue) QS-18, QS-21, QS-22; [§12](../03-workflows/WORKFLOWS/s10-14-signals-decisions-traceability.md#12-planner-determined-level-1-decisions) L-03, L-13–L-15, L-35, L-38, L-42, L-44, L-48, L-49; [§4](../03-workflows/WORKFLOWS/s01-04-foundations.md#4-actor-and-authority-matrix)
- [BUSINESS_RULES §4](../02-domain/BUSINESS_RULES.md#inventory-inv) BR-INV-01–BR-INV-13; [§5](../02-domain/BUSINESS_RULES.md#5-calculation-semantics) CALC-01, CALC-09, CALC-11 and the [numeric annex](../02-domain/BUSINESS_RULES.md#numeric-annex-binding-examples-for-p8-fixtures-gap-004) 7–9; [§7](../02-domain/BUSINESS_RULES.md#7-forbidden-shortcuts) FS-01, FS-07, FS-09, FS-11
- [V1_SCOPE](../01-product/V1_SCOPE.md#must-ship) CAP-06; [reservation and usable stock](../01-product/V1_SCOPE.md#reservation-and-usable-stock); [ACCEPTANCE_CRITERIA](../01-product/ACCEPTANCE_CRITERIA.md#product-acceptance-contracts) AC-06, AC-16, AC-22
- [DATABASE §4.7](../04-architecture/DATABASE/s04-07-inv.md#47-inv--inventory); [§5.3](../04-architecture/DATABASE/s05-inventory.md#53-movement-catalogue-and-sign-discipline), [§5.6](../04-architecture/DATABASE/s05-inventory.md#56-counts-opname), [§5.8](../04-architecture/DATABASE/s05-inventory.md#58-unattributed-economic-loss-cases-dir-026-dir-027); [§6.3](../04-architecture/DATABASE/s06-lot-cost-hpp.md#63-opening-stock-drop-ship-returns-surplus-and-corrections), [§6.5](../04-architecture/DATABASE/s06-lot-cost-hpp.md#65-inter-company-allocation-overlay); [§18](../04-architecture/DATABASE/s18-19-constraints-integrity.md#18-constraint-catalogue) C-01–C-04, C-06–C-09, C-24, C-28, C-33–C-37, C-61
- [ARCHITECTURE §7](../04-architecture/ARCHITECTURE.md#7-posting-services-and-guards) `InventoryPosting`, `CostPosting`, `ReconcileGuards`
- [CONCURRENCY_IDEMPOTENCY §7.1](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s07-statement-sequences.md#71-lock-sets-of-ax-01ax-37) and [§7.2](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s07-statement-sequences.md#72-checks-effects-and-outcomes-of-ax-01ax-37) AX-06, AX-07, AX-37; [§7.3](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s07-statement-sequences.md#73-command-families-outside-ax-0137) NX-05, NX-11; [§5](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s04-06-locks-envelope.md#5-data-dependent-lock-set-protocol); [§8](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s08-09-guards-stale-state.md#8-guards-and-first-use-rows) FU-01, FU-02, FU-06, FU-11; [§9](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s08-09-guards-stale-state.md#9-stale-state) ST-03, ST-06, ST-09; [§11](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s10-13-identity-failure-retry.md#11-separate-duplicates) BD-09, BD-12, BD-13; [§18](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s18-scenarios.md#18-mandatory-concurrency-and-failure-scenarios) M-04, M-06, M-30
- [PERMISSIONS_MATRIX §2](../05-security/PERMISSIONS_MATRIX.md#2-authorization-model) AZ-08, AZ-10; [§4](../05-security/PERMISSIONS_MATRIX.md#4-capability-catalogue) `stock.count`, `stock.count_apply`, `stock.attribute_surplus`, `stock.resolve_unattributed`, `stock.resolve_unattributed_evidence`, `stock.reserve`, `stock.allocate_intercompany`; [§6](../05-security/PERMISSIONS_MATRIX.md#6-company-scope-rules) CS-05, CS-07; [§7.2](../05-security/PERMISSIONS_MATRIX.md#72-table-classification) rows 63–72; [§7.3](../05-security/PERMISSIONS_MATRIX.md#73-special-projections) PJ-01, PJ-04, PJ-07, PJ-08, PJ-22; [§7.4](../05-security/PERMISSIONS_MATRIX.md#74-read-classes) RD-02, RD-09; [§8](../05-security/PERMISSIONS_MATRIX.md#8-cross-company-link-authorization) XL-1; [§9](../05-security/PERMISSIONS_MATRIX.md#9-owner-only-denials) OD-08–OD-10, OD-15
- [SECURITY §6](../05-security/SECURITY.md#6-audit-and-security-logging) LG-01
- [ROUTE_CONTRACTS](../03-workflows/ROUTE_CONTRACTS.md#warehouse--receiving-reservations-dispatch-counts-adjustments-and-discrepancies-inv-pg-018pg-029) ROUTE-191–ROUTE-193, ROUTE-197–ROUTE-200; [§2](../03-workflows/ROUTE_CONTRACTS.md#2-page-views--route-001route-065) ROUTE-026, ROUTE-029; [§4](../03-workflows/ROUTE_CONTRACTS.md#4-visible-controls-without-a-route-of-their-own)
- [INTERACTION_CONTRACTS](../03-workflows/INTERACTION_CONTRACTS.md#warehouse--receiving-reservations-dispatch-counts-adjustments-and-discrepancies-inv-pg-018pg-029) INT-191–INT-193, INT-197–INT-200; [§2](../03-workflows/INTERACTION_CONTRACTS.md#2-shared-interactions--int-001int-020) INT-006, INT-015
- [NAVIGATION_CONTRACTS §2.3](../07-ux-design/NAVIGATION_CONTRACTS.md#23-purchasing-and-warehouse) NAV-026, NAV-029; [INFORMATION_ARCHITECTURE §8.3](../07-ux-design/INFORMATION_ARCHITECTURE.md#83-purchasing-and-warehouse) PG-026, PG-029; [§8.7](../07-ux-design/INFORMATION_ARCHITECTURE.md#87-the-approved-screens-scr-01scr-60-and-their-pages) SCR-24, SCR-27; [§7](../07-ux-design/INFORMATION_ARCHITECTURE.md#7-company-context-model) the relation marker
- [ADMIN_FLOW J-INV-05](../07-ux-design/ADMIN_FLOW/s10-a-journeys.md#103-inventory-and-purchasing); [IP-05](../07-ux-design/ADMIN_FLOW/s05-command-model.md#ip-05--duplicate-warnings); [IP-06](../07-ux-design/ADMIN_FLOW/s05-command-model.md#ip-06--a-routed-refusal); [IP-09](../07-ux-design/ADMIN_FLOW/s05-command-model.md#ip-09--corrections-closures-and-destructive-actions); [IP-19](../07-ux-design/ADMIN_FLOW/s05-command-model.md#ip-19--several-commands-on-one-screen); [IP-15](../07-ux-design/ADMIN_FLOW/s06-09-session-forms-lists.md#ip-15--scanner); [IP-20](../07-ux-design/ADMIN_FLOW/s06-09-session-forms-lists.md#ip-20--choosing-a-reservation-cut); [§14](../07-ux-design/ADMIN_FLOW/s14-15-messages-scenarios.md#14-message-catalogue) MSG-14, MSG-15, MSG-30, MSG-40, MSG-41; [UXS-39](../07-ux-design/ADMIN_FLOW/s14-15-messages-scenarios.md#workflow-presentations)
- [DESIGN_SYSTEM §12.1](../07-ux-design/DESIGN_SYSTEM.md#121-status-system) the count, finding and pending-case families; [§14.2](../07-ux-design/DESIGN_SYSTEM.md#142-inventory); [§13.2](../07-ux-design/DESIGN_SYSTEM.md#132-scanner); [§17](../07-ux-design/DESIGN_SYSTEM.md#17-print-views)
- [TEST_STRATEGY §5](../08-testing/TEST_STRATEGY.md#5-domain-and-data-integrity) FX-03, FX-05; [§6](../08-testing/TEST_STRATEGY.md#6-concurrency-and-failure-proofs); [§7](../08-testing/TEST_STRATEGY.md#7-browser-end-to-end-tests-and-critical-journeys) CJ-05; [SECURITY_TEST_MATRIX](../08-testing/SECURITY_TEST_MATRIX.md#3-the-matrix) SM-01, SM-04, SM-05, SM-25; [PERFORMANCE_TARGETS §3](../08-testing/PERFORMANCE_TARGETS.md#3-the-measured-targets) PFM-01, PFM-02, PFM-20
- [GAP_REGISTER](../00-governance/GAP_REGISTER.md#gap-009--dispatch-locks-alone-do-not-protect-stock-counts-and-serial-scans) GAP-009; [GAP-030](../00-governance/GAP_REGISTER.md#gap-030--an-unexplained-fungible-loss-across-companies-needs-an-owner-before-cost-moves)
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (83): BR-INV-01–BR-INV-13; CALC-01; CALC-09; CALC-11; FS-01; WF-INV-05; SF-UNATTRIBUTED; CM-09; CM-22; CM-38; AX-06–AX-07; AX-33; AX-37; L-03; L-06–L-07; L-13–L-15; L-31; L-35–L-36; L-40; L-42; L-44; L-48; ROUTE-026; ROUTE-029; ROUTE-191–ROUTE-193; ROUTE-197–ROUTE-200; INT-191–INT-193; INT-197–INT-200; C-01–C-04; C-07–C-09; C-24; C-28; C-33–C-35; C-47; C-61; NX-05; BD-09; BD-12–BD-13; PG-026; PG-029; SCR-24; SCR-27; J-INV-05; UXS-39; NAV-026; NAV-029; GAP-009; GAP-030

**EXPECTED MODULES / FILE AREAS:**

- the INV module's actions OpenCount, RecordCount, ApplyCountVariance, AttributeSurplus, ResolveUnattributedLoss, ResolveUnattributedLossWithEvidence and ReverseUnattributedResolution with `ShowStockCounts` and `ShowDiscrepancies` and their query classes (RC-02; ARCHITECTURE §4); `InventoryPosting` for the ledger, guards, serial states, reservation cuts and the unattributed-case counters, `CostPosting` for the losses and the flagged surplus basis (ARCHITECTURE §7); `resources/js/Pages/INV` for PG-026 and PG-029; `tests/Feature/INV`, `tests/Concurrency/` and `tests/Reconciliation/`

**BUSINESS RULES:**

- BR-INV-07, L-14 and BR-DT-05: a count records its counted basis per product × location × condition (+ serials); its variance applies only through a reasoned event re-read against that basis — any movement in the scope after the count started makes it stale and a recount follows; balances are never edited directly (FS-11; BR-INV-08)
- BR-INV-01, BR-RSV-04 and FS-09: AVAILABLE ≥ 0 always; a shortage that would leave usable supply below the reservations carries the actor's explicit cut of chosen reservations in the same action, never an automatic priority (annex 9)
- BR-INV-04, BR-INV-13, L-42 and DIR-026: every lost unit is named by lot from evidence; unidentified fungible units of one company are confirmed by the ADM+ actor with oldest-first only as a picking aid; across companies the stock-out commits with a pending case and its immutable snapshot (`DIR-027`), which no FIFO, oldest-first or proportional rule decides and which retains or freezes no other stock; the loss is recognized exactly once, on the causal project when one is established, otherwise the owning company (SF-LOSS; L-38; CALC-09; CALC-11)
- L-15 and BR-INV-04: stock increases only through the closed list — here the exact reversal of a pending case, the linked reversal of an identified loss and the Owner-attributed surplus with its flagged cost basis; a surplus reverses a prior loss only when evidence identifies the units
- L-44 and BR-INV-05: a shortfall closure from another company's lot inside an Owner resolution records one inter-company allocation on its loss consumption; provenance is never silently reassigned (FS-07; C-28)
- BR-INV-09, L-48 and SF-SCAN: a serial is unique while in stock; an unexpected serial is a finding resolved through the CM rows, never a silent re-registration; L-35: a registration count precedes a switch to serialized when stock is on hand
- L-49: a fungible count application without an external identity raises the likely-duplicate warning that needs the actor's reasoned confirmation (QS-21); L-13: usable and unusable remainders tracked per lot and serial
- FS-01: the count's, finding's and case's states are the stored decisions DATABASE §4.7 lists; every quantity stays derived (BR-INV-08)
- BR-INV-02, BR-INV-03, CALC-01 and C-04: a variance is a legitimate movement in base units — an alternate-unit count converted exactly, never a fractional base quantity of a counted or serialized product (C-09) —, and AVAILABLE = ON HAND − RESERVED − UNUSABLE ≥ 0 per product and location after it; C-01 and C-02: a scanned code resolves to at most one active product and a serial is one identity, never in stock twice
- L-07 and L-31: the LOSS_REVERSAL restorations of a found surplus after resolution or of a resolution's reversal cite the loss consumptions they restore, are capped by them, reverse their allocation with them and take proportional cost shares, the residual going to the restoration that zeroes the consumption (SF-RESTORE; BR-FIN-13; C-27)
- BR-INV-05, BR-INV-06, BR-INV-10–BR-INV-12 and L-03 hold unchanged: a count creates no allocation except an Owner shortfall closure's (XL-1), no purchase return, restock purchase or opening lot; L-36: a location move stays a zero-net movement of TASK-042 (C-07)

**ARCHITECTURE RULES:**

- INV is tier 5: it posts the costs of a loss and of a flagged surplus lot through CST's `CostPosting` in its own transaction and never calls a higher tier (ARCHITECTURE §3, §7); every guard has its one normal writer, and `ReconcileGuards` reports a drift without repairing it (C-61; GAP-031)
- one application may post several movements under one command — named-lot losses, pending cases, found and loss-reversal rows, findings —, all in one transaction (DATABASE §5.6; AX-06); the recognition snapshot rows are insert-only in the recognition or conversion transaction (C-36); a resolution never re-points or rewrites a later movement (`DIR-027`)
- the pooled views read through physical-only query classes that never select S3 tables (PJ-01); counts are POOL records (CS-05)

**SECURITY / PERMISSION REQUIREMENTS:**

- ROUTE-026 `stock.count`, the application's control only with `stock.count_apply` (RG-06); ROUTE-191 and ROUTE-192 `stock.count` (ADM — counting needs no ADM+); ROUTE-193 `stock.count_apply` (ADM_PLUS), a cut also `stock.reserve` (IP-20; AZ-08); a loss whose bearer company differs from the lot's company also `stock.allocate_intercompany` with a grant on the bearer company only, as AZ-08 and XL-1 state — ROUTE-193 and INT-193 do not name it (reported for Planning)
- ROUTE-029 `stock.resolve_unattributed` or `stock.attribute_surplus`, or `stock.resolve_unattributed_evidence` under PJ-07; ROUTE-197 `stock.attribute_surplus` and ROUTE-198 `stock.resolve_unattributed` OWNER_ONLY, never grantable (RG-02; OD-08; OD-09); ROUTE-199 `stock.resolve_unattributed_evidence` (ADM_PLUS) only when the server verifies every condition (a)–(f) of OD-10; ROUTE-200 `stock.resolve_unattributed`, an evidence-identified resolution's reversal also `stock.resolve_unattributed_evidence`; an Owner decision recorded in error is superseded only by the Owner (OD-15; CM-27)
- every ADM_PLUS and OWNER_ONLY command asks its reason (IP-09; MSG-40); a control is absent without its capability, never disabled (AZ-01; NV-08)

**COMPANY SCOPE AND FIELD PROJECTION:**

- counts, count lines, scopes, findings' quantities and pending cases are POOL records (CS-05): read with `stock.view` (S1; rows 63–67), commanded with their capability and at least one company in scope; a count loss on a lot needs its bearer company in scope — the causal project's company, otherwise the lot's owning company — or the command is REJECTED and routed (AZ-08; QS-20; IP-06; MSG-14); a cut of another company's project's reservation needs a grant on that company or is routed (SF-RSV-CUT)
- PJ-07 and rows 67–72: every `stock.view` holder sees the case's physical result — product, scope, recognized, pending, found and resolved quantities, state, date, reason and actor; candidates, exposures and group rows are S3 of each candidate — `cost.view` holders see their own company's rows, `stock.resolve_unattributed_evidence` holders the candidate lots and quantities without costs for companies in their scope, the Owner all —; the number and identity of other candidate companies are never shown to a non-Owner; resolutions' decider and reason to `cost.view` holders of an assigned company
- row 66: a finding's surplus quantity and state to `stock.view`, its attributed company and flagged basis to `cost.view` of that company; PJ-22: a pending case appears in a company's stock and profit figures only as that company's own exposure (MSG-41); another company's lot shows only the relation marker (PJ-04; PJ-01)
- PG-029's attribution parts and QS-18's and QS-22's attribution rows are Owner-only (RD-09; PJ-13); lists apply the scope filter inside the query, before ranking and pagination (CS-07)

**CONCURRENCY / IDEMPOTENCY:**

- NX-05 for ROUTE-191 and ROUTE-192 (LK-00, LK-01 only when a project is affected, LK-03, LK-12, LK-13, LK-14, LK-15, LK-17): a count opens by inserting its `stock_count_scopes` rows with the scope versions read under LK-14; one `command_id` per line (CI-01)
- AX-06 for ROUTE-193, DATA-DEPENDENT (§5's protocol): LK-00, LK-01 — causal projects, the projects of reservations cut, a project whose dispatched serial is found unexpectedly, the bearer projects of identified losses a positive variance reverses —, LK-03, LK-10, LK-11, LK-12, LK-13, LK-14, LK-15, LK-17, LK-18, LK-19, LK-23, LK-24, LK-29; first-use rows FU-01, FU-02, FU-06, FU-11; re-read under lock: the count OPEN and each `basis_version` equal to the live scope version, the named lots holding the quantity, the L-42 confirmation or the SF-UNATTRIBUTED split, candidate lots, prior pending quantity and group membership, the pending cases and identified losses a surplus reverses, the cuts; stale checks ST-03 and ST-09 — a stale count commits only its OPEN → STALE transition and changes no stock row (C-33); deferred: net-zero groups, lot-less signs, snapshot totals; refusals C-03, C-06, C-07, C-35, C-36, L-49, AZ-08; identity the count, or BD-13; retry class B (CONCURRENCY_IDEMPOTENCY §7.1–§7.2)
- AX-07 for ROUTE-197, DATA-DEPENDENT: LK-00, LK-01 (the bearer projects of the losses it reverses), LK-03, LK-11, LK-12, LK-13, LK-14, LK-15, LK-17, LK-23, LK-24, LK-29; FU-01, FU-02, FU-13; the finding's conditional OPEN → ATTRIBUTED, a finding no longer OPEN being CONFLICT; refusals C-34, C-35; identity BD-09; retry class B
- AX-37 for ROUTE-198–ROUTE-200, DATA-DEPENDENT: LK-00, LK-01 (each resolution line's bearer — the case's causal project; for a reversal, the bearers of the consumptions it restores), LK-03, LK-11, LK-13, LK-14, LK-15, LK-23, LK-24, LK-29; re-read: pending ≥ Σ assigned, each company within its exposure and group bound, the closed lots still holding the claims, the assigned company's own first, only the identified lots for an evidence resolution (OD-10); one reversal per resolution; a converted or reversed case is CONFLICT; refusals C-35, C-37; identity BD-12; retry class B
- the scenarios as real two-session tests: M-04 (a dispatch or receipt racing a count — the movement commits, the application is CONFLICT, nothing overwritten, both orders), M-06 (recognition and resolution racing a dispatch in the scope — nothing retained; a resolution whose lots were consumed meanwhile REJECTED with C-37), M-30 (first use of a scope, lot or overlap group); a replay of one `command_id` returns the recorded outcome, re-authorized (CI-05); a quantity precondition lost to a racing command is REJECTED, never CONFLICT (ST-06); `ReconcileGuards` reports no difference after each (HO-12)

**AUDIT:**

- every count opening, line, application, attribution, resolution and reversal is a business audit event in its command's transaction with the individual actor, business date, reason where required, before/after and linkage (BR-XC-01; LG-01); surplus attribution and unattributed-loss resolution are privileged changes LG-01 names; the event records the highest authority class exercised — ADM_PLUS for an application, OWNER_ONLY for an attribution or an Owner resolution — and every ADM_PLUS and OWNER_ONLY event, each evidence-identified resolution among them, reaches the Owner's review queue (AZ-10; QS-14; OD-10)
- a stale count's OPEN → STALE transition commits with its audit event of authority class SYSTEM and a human actor, read by SM-05 as a refusal, never an anomaly (ST-03); the timeline shows it as "kedaluwarsa" (DESIGN_SYSTEM §14.4)
- SF-UNATTRIBUTED's audit keeps the actor, timestamp, reason, evidence reviewed, product and quantity, lot and provenance context, the selected company, the resolution kind and before/after; a reversal links to the resolution it reverses (CM-27); a likely-duplicate confirmation commits under the acknowledged variant of its action code, listed in QS-21 (CONCURRENCY_IDEMPOTENCY §11); a cross-company resolution's event shows each side only its own keys (PJ-11)

**DATABASE IMPACT ASSESSMENT:**

- Schema change: NONE — uses the tables of TASK-005–TASK-009; a missing column is an executor discovery (AGENT_OPERATING_MODEL)
- Migration required: NO
- Existing data transformation: NONE
- Direct production DB access: FORBIDDEN
- Data-loss risk: NONE KNOWN

**MODULARITY / EXTENSIBILITY:**

- Module boundary affected: INV, tier 5, posting costs through CST (tier 3); OPS's case counters only through the case's posting service where a unit under an open case is lost (ARCHITECTURE §3)
- Stable core kept provider-neutral: YES
- External/vendor-specific code isolated: YES — framework facilities or a small interface (ARCHITECTURE §2)
- Replaceable adapter/contract required: NO
- Provider lock-in risk: NONE beyond the Owner's baseline stack
- Future extension impact: LOW
- Speculative abstraction avoided: YES — no generic workflow or rules engine; no valuation or allocation engine decides a company (ARCHITECTURE §1; FS-09; DIR-026)

**AUTH IMPACT:**

- Authentication change: NONE
- Authentication implementation: LARAVEL_NATIVE — decided once ([EXECUTION_CONTEXT](EXECUTION_CONTEXT.md)), reused, never redesigned
- Auth profile: N/A — this Task does not touch authentication
- Google sign-in: N/A — not touched
- Google registration behavior: N/A — not touched
- Google credentials owner action required: NO
- Authorization change: NONE — uses TASK-011's capabilities and policies as PERMISSIONS_MATRIX states them
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

- Context7: REQUIRED — PostgreSQL deferred constraint triggers, generated columns with CHECKs and savepoints inside one transaction; the framework's database transactions, at the installed versions
- Graphify: OPTIONAL — once INV's other Tasks exist
- Browser E2E: REQUIRED — J-INV-05 on the phone and the desktop, PG-029's paths and CJ-05's pending unattributed loss
- Other: the two-connection harness and the fixtures FX-03 and FX-05 of TASK-003; the hardware scanner at Layer 3 (TEST_STRATEGY §12)
- Missing safe tool behavior: AUTO-INSTALL project-locally inside this Task (AICWDF §6.3) and record it in TOOLCHAIN; a paid tier or a privileged installation STOPS for the Owner (COST_POLICY)

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: refreshed before the change when Graphify is available ([TOOLCHAIN](../00-governance/TOOLCHAIN.md)); otherwise a targeted search of INV's posting service and CST's cost posting
- Pre-change questions: which movement types and guards the application posts, which commands of TASK-038–TASK-042 meet the same scope and lot rows, and where the case counters are read (QS-18, QS-22, completion predicate 8)
- Expected affected areas: INV's count and case actions, `InventoryPosting`, `CostPosting`; PG-026, PG-029

**CONTEXT7:**

- Topics to verify: deferred constraint triggers evaluated at commit, savepoint rollback with a committed outcome, generated-column CHECKs, insert-only column grants
- Version-sensitive assumptions: none from memory

**UI / UX REQUIREMENTS:**

- Follow global design rules: YES
- Indonesian default: YES
- English support: YES
- Plain-language Indonesian: YES — MSG-30 for a stale count; MSG-41 for a pending quantity; the form saying that a surplus is not yet added to stock and that an unidentified shortage across companies is recorded as *Selisih menunggu penetapan* without naming or counting the other companies (J-INV-05; J-INV-04; PJ-07)
- Language switch impact: keeps unsent input and the counted lines not yet sent
- Mobile-first: YES — PG-026 `[floor]`: counting on the phone with the scan field and a running list of counted lines; applying and reviewing on the desktop (J-INV-05); PG-029 `[desk]` readable on the phone (NV-06)
- Responsive: YES
- Adaptive: YES — PG-026 scan-led on the phone, lines and variance side by side on the desktop; touch targets 44 px with a coarse pointer and 48 px for the floor task's operational controls (D-DS-19)
- Visual breathing room: REQUIRED
- Comfortable density: REQUIRED
- shadcn/ui reuse: the scan field (PT-20), multi-line entry (PT-10), data table and dense rows (PT-05, PT-06), the inventory presentation with its pending line (PT-17; §14.2), the reservation-cut block (IP-20), the confirmation with its reason (PT-26), status badges (PT-13) for *Terbuka*, *Kedaluwarsa*, *Diterapkan*, *Menunggu Owner*, *Menunggu Penetapan* and *Ditetapkan*, the timeline (PT-18)
- Design reference: PG-026 and PG-029 as INFORMATION_ARCHITECTURE §8.3 and J-INV-05 state them; the Golden Frontend Reference for presentation only (DIR-061 SD-02)
- Primary action: "Mulai Hitungan" or the line's record on PG-026, "Terapkan Selisih" once lines are counted; on PG-029 the Owner's "Tetapkan Perusahaan" or "Tetapkan Surplus" for the selected row
- Secondary actions: "Hitung Ulang" on a stale count; "Tetapkan dengan Bukti" for an ADM+ evidence holder; "Balikkan Penetapan"
- Destructive actions: none — no control deletes a count, a line, a finding or a case; corrections are linked reversals (CM-27; CM-28)
- Redundancy constraints: one open finding per scope and product (C-34); a count applied once, a stale one only recounted; a resolution reversed at most once (C-37); counting never offered as an increase path (L-15)
- Loading state: PT-19; a scan's result line and each line's answer in place (IP-19)
- Empty state: no open count, no open finding, no pending case — one sentence each (PT-19)
- Error state: MSG-30 with "Hitung Ulang"; the rejection with its rule in words and the current figure (MSG-02); the routed refusal MSG-14; the likely duplicate MSG-15

**ROUTE IMPACT:**

- ROUTE-026 (PG-026's view, GET `/gudang/stok-opname`) and ROUTE-029 (PG-029's view, GET `/gudang/selisih-temuan`)
- ROUTE-191 (POST `/gudang/stok-opname`), ROUTE-192 (POST `/gudang/stok-opname/{id}/baris`), ROUTE-193 (POST `/gudang/stok-opname/{id}/terapkan`)
- ROUTE-197 (POST `/gudang/selisih-temuan/{id}/surplus`), ROUTE-198 (`…/tetapkan`), ROUTE-199 (`…/tetapkan-bukti`), ROUTE-200 (`…/balikkan`)
- each answering RC-05's errors; the scan field's lookup is ROUTE-282 (TASK-039's, with INT-006 and INT-282)

**INTERACTION CONTRACTS:**

- INT-191–INT-193 and INT-197–INT-200, each with its *Test* as its test (IG-05); INT-006 for the scan field, INT-015 for the likely-duplicate second submission of ROUTE-193, INT-016 and INT-017 for CONFLICT and FAILED; the reason step of IG-03 on every ADM+ and Owner command

**NAVIGATION CONTRACT:**

- Parent: PG-026 *Gudang* › *Stok Opname* (NAV-026); PG-029 PG-006 · *Gudang* (NAV-029)
- Entry: *Gudang* › *Stok Opname*; QS-18 rows; "Mulai Hitungan" on PG-019; PG-029 from *Beranda*'s decision rows (QS-22, QS-18) and *Gudang* › *Lainnya*
- Back action: "Kembali" from a count → PG-026; PG-029 "Kembali" → the origin
- Cancel: the reason step's "Kembali" → the form, the input kept (IP-09; NV-02)
- Success destination: the count's answer in place (NAV-026); PG-029 with the case resolved (NAV-029)
- Error behavior: NV-04 — a stale count's MSG-30 in place with "Hitung Ulang"; a rejected command in place with the input kept
- State preservation: filters, sort, tab and cursor in the address (IP-18; NV-05); unsent counted lines kept across the re-login, never in browser history (H7-12)
- Mobile behavior: PG-026 `[floor]` — the scan field fixed and a running list of lines; PG-029 `[desk]` — readable on the phone

**ACCEPTANCE CRITERIA:**

1. A count stores each counted scope's version at its start; when any movement of a counted scope commits after that, "Terapkan Selisih" is CONFLICT, the count becomes *Kedaluwarsa* with MSG-30 and its audit event, and no stock row changes; "Hitung Ulang" opens a new count (ST-03; C-33; M-04; AC-06).
2. A negative variance on named lots commits its stock-out and its loss once each, at the lots' current net cost, on the causal project when one is established and otherwise on the lot's owning company; a unit under an open case becomes that case's single loss outcome (SF-LOSS; L-38; C-24; BR-INV-13).
3. An unidentified fungible shortage whose candidate lots belong to one company commits only with the ADM+ actor's explicit lot confirmation; when they belong to several companies it commits ON HAND's decrease with one pending case and its immutable snapshot of every candidate company, lot, quantity and current net cost — no lot, cost, loss or profit moves, and dispatch and receiving in the scope continue unblocked (SF-UNATTRIBUTED; L-42; C-36; M-06; `DIR-027`).
4. A shortage that would leave usable supply below the reservations commits only with the actor's chosen cuts in the same action, and a cut of another company's project without a grant on it is REJECTED and routed with MSG-14 (SF-RSV-CUT; C-06; AZ-08; QS-20).
5. A positive variance in a scope with a pending loss case first reverses that pending quantity exactly; an identified loss is reversed only when a serial, a single candidate lot or company, or entry-error evidence identifies the units; any other surplus becomes one open finding per scope and product, its units not added to ON HAND, and a second open finding for the same scope and product is refused (L-15; C-34; BD-09; AX-06).
6. Only the Owner attributes an open finding, with an explicit flagged cost basis that creates one flagged lot; an Admin's application leaves the finding open, and a count of the scope started before the attribution becomes stale (AX-07; OD-08; C-08).
7. An Owner resolution assigns at most the pending quantity, each company within its recognition exposure and the overlap group's bound, closes the assigned company's own claims first and a shortfall only from lots the Owner chooses, each with one inter-company allocation, and recognizes SF-LOSS once at the closed claims' current net cost; an over-assignment is REJECTED (C-35; C-37; L-44; C-28).
8. An evidence-identified resolution by an ADM+ holder commits only when every condition (a)–(f) of OD-10 holds — failing each one in turn is REJECTED —, and it appears in the Owner's review queue (OD-10; QS-14; SM-04).
9. A resolution is reversed exactly and at most once, re-opening the same pending quantity with the snapshot and every later movement unchanged; a found surplus after resolution goes through the exact loss reversal, never a second recognition (CM-27; C-35; SF-LOSS).
10. A non-Owner never sees the number or identity of other candidate companies; a `cost.view` holder sees only its own company's candidate and exposure rows, and its company figures show the pending case only as its own exposure (PJ-07; PJ-22; MSG-41; UXS-39; SM-25).
11. A repeated scan of one serial in a count counts once, an unexpected serial becomes a finding and is never re-registered, and one `command_id` replayed on any count command returns the recorded outcome (SF-SCAN; L-48; CI-05; AC-16).
12. After every scenario `ReconcileGuards` reports no difference for the product, scope, lot, consumption, lot-cost and case guards (C-61; HO-12).
13. An unexplained loss across companies recorded through PG-027's adjustment, disposal or condition change opens its pending case — never a named loss —, shown as *Selisih menunggu penetapan*, and the unit's later disposal converts it; the same `command_id` replayed returns the recorded outcome (QS-22; SF-UNATTRIBUTED; J-INV-04; CI-05).

**TEST REQUIREMENTS:**

- Lint: TL-01
- Typecheck: TL-02
- Unit: the variance splitter — named lots, the L-42 confirmation, the SF-UNATTRIBUTED split, the surplus order —, the exposure and group bounds, against FX-01 7–9 (TL-03)
- Feature: ROUTE-191–ROUTE-193 and ROUTE-197–ROUTE-200 with each refusal their contracts name (TL-04)
- Integration: M-04, M-06 and M-30 with real sessions in both orders; FX-05's cases — a pending case with dispatch continuing, an Owner resolution with and without substitution, an evidence resolution, stock found before and after resolution, an error reversal, a condition-change conversion —; FX-03's unknown-company loss; the transportation argument that an overlap group's bounds never deadlock a resolution; the reconciliation suite after each (TL-05; TEST_STRATEGY §5, §6)
- Authorization: `stock.count` against `stock.count_apply`; the OWNER_ONLY denials OD-08 and OD-09 for an Admin, also through a replayed or forged request; OD-10's conditions one by one; a count loss on another company's lot without a grant on the bearer (AZ-08) (TL-06; SM-04)
- Route integrity: ROUTE-026, ROUTE-029, ROUTE-191–ROUTE-193, ROUTE-197–ROUTE-200 audited (TL-07; SM-02)
- Interaction: INT-191–INT-193 and INT-197–INT-200 per IG-05, with one `command_id` replayed; INT-015's fungible confirmation on ROUTE-193 (TL-08)
- Browser E2E: J-INV-05 — counting on the phone, applying on the desktop — and CJ-05's pending unattributed loss; PG-029's resolution paths (TL-09)
- Localization/language switch: both languages on PG-026 and PG-029 (TL-10)
- Visual breathing room/density: TEST_STRATEGY §9
- Responsive: TL-11
- Accessibility: TL-12, the scan feedback's live region included (DESIGN_SYSTEM §15)
- Production build: TL-13
- Security: SM-01 for another company's records on these routes; SM-05 reading ST-03's SYSTEM-class events; SM-25 for the candidate and exposure projection (TL-14)
- Performance: PFM-01 and PFM-02 for PG-026 and PG-029; PFM-20 with QB-14 for the class-B commands AX-06, AX-07 and AX-37 (TL-15)
- Migration: N/A — no migration or import code in this Task
- Other: GAP-009's count-versus-dispatch and duplicate-scan proofs and GAP-030's fixtures recorded as their evidence; the registration count of L-35 limited to its lines until Planning states how serials are assigned

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

- TASK-058 — when its other dependencies are DONE and its READY gate holds

**DO NOT DO:**

- add to V1 — a capability, page, route, field, integration, document type, report or paid service — or drop, defer or narrow a V1_REQUIRED requirement; a Task that would need either raises an Owner question and waits ([DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-20)
- access the VPS, production, its data or a credential, or put a secret, real data, a host detail or production configuration in the repository ([INFRASTRUCTURE](../09-operations/INFRASTRUCTURE.md) INF-01, INF-05; SECURITY SX-01, SX-04, SX-06; [PRODUCTION_DATA_SAFETY](../00-governance/PRODUCTION_DATA_SAFETY.md))
- add AI, model or tool attribution to a commit — the Owner's configured identity only ([AGENTS.md](../../AGENTS.md#git-commit-attribution); DIR-022)
- start before the Owner's execution authorization at OG-4, or mark DONE without the required tests passing and the completion evidence ([TASK_PLAN](TASK_PLAN.md#2-rules-of-the-plan); [DEFINITION_OF_DONE](../08-testing/DEFINITION_OF_DONE.md#2-the-checklist))
- weaken, skip or delete a valid test to obtain a green run, or adopt a paid tier without the Owner's COST_POLICY decision (DOD-05; [COST_POLICY](../00-governance/COST_POLICY.md))
- apply a count as an absolute number, edit a balance directly, or apply a count whose scope moved after it started (BR-INV-07; C-33)
- attribute a company to an unexplained fungible loss or a found surplus by FIFO, oldest-first, proportion or any other automatic rule, or retain or freeze stock while a case is pending (DIR-026; `DIR-027`; FS-09)
- give an Admin, a job, an import or any API path the surplus attribution or the Owner resolution, or accept an evidence resolution that fails any OD-10 condition (OD-08–OD-10)
- show a non-Owner the number or identity of the other candidate companies, or put a pending case inside any company's figures (PJ-07; PJ-22)
- register an unexpected serial silently, or add a count increase outside L-15's list
- add a route or control for cancelling a count, for a registration count's serial assignment or for a print button before Planning states them (RC-06; OE-20)

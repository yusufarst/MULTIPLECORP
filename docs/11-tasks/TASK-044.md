# TASK-044 — Purchase return to supplier

Status: REVIEW | Updated: 2026-10-20 | Owner: Planning

Written by WF-PC5-W's writer of bundle W2 of run PC-5 on 2026-10-19 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99 — a REVIEW draft of P11, approved, or not, with the [Task plan](TASK_PLAN.md) at OG-4. **No Task starts before the Owner's execution authorization at OG-4.** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** TODO

**RISK CLASS:** HIGH

**EXECUTOR:** AGENT

**OBJECTIVE:**

Return purchased goods to their supplier: from the purchase or the lot, open the return case with its reason, take only physically present and unconsumed units of that supplier's lot out of stock with the reservation cut it needs, draw their current net cost into the supplier-return class, declare the outcome — replacement, refund, a supplier credit against the unpaid purchase value, or the unrecovered share recognized once on its bearer —, and close the case only when returned cost = refunded + replaced + unrecovered, on PG-028 *Retur ke Pemasok* (WF-PUR-03; AX-11; NX-16; ROUTE-176, ROUTE-177).

**WHY THIS EXISTS:**

CAP-06 and AC-06 include purchase returns with reasons and reconciling history, and AC-12 requires every correction to keep its original and linked history. A return must never make cost disappear or count it twice: the purchase value is contra'd only for what the supplier refunds or replaces, and any unrecovered share — freight, a restocking fee, a partial refund — is a loss once on its bearer (BR-INV-13; DIR-024 D-3; GAP-028). Units allocated to another company's project cannot be "unallocated" in place (BR-INV-06; annex 12). The completion predicates of TASK-055 read the return case's closure (BR-CR-04).

**DEPENDS ON:**

- TASK-037 — Purchases — project and replenishment purchases
- TASK-039 — Receiving (Barang Masuk)
- TASK-040 — Stock views, stock history and movement reversal

**BLOCKS:**

- TASK-048 — Client returns (sales return)
- TASK-054 — Expenses, disbursements and other cash
- TASK-055 — Project completion and Force Complete

**PARALLEL SAFE:** NO — the return writes the purchase-line, lot, lot-cost, consumption and reservation guards that receiving, dispatch and the cost cascade (TASK-037–TASK-042) post, and the return case's counters that the refund, the replacement receipt and the cost correction move

**IN SCOPE:**

- ROUTE-176 with INT-176 (`INV ReturnToSupplier`; `purchase_return.record`; AX-11): "Retur ke Pemasok" on PG-028, opened from the purchase on PG-016 or from the lot (ADMIN_FLOW §11 CM-09, CM-16): the return case of kind PURCHASE_RETURN with its reason (SF-CASE; IP-09; MSG-40); the lots and serials chosen — scan-led on the phone (SF-SCAN; PT-20) —, each unit of that supplier's purchase lot, physically present and unconsumed, usable or unusable (BR-INV-06; C-03); the PURCHASE_RETURN movement with its consumptions, never HPP, drawing the units' current net lot cost — goods, non-creditable tax and allocated charge shares — into class SUPPLIER_RETURN and the case's `returned_cost` (DATABASE §5.3, §6.1; SF-CHARGE); the reservation cut of IP-20 in the same action when usable supply would fall below the reservations (SF-RSV-CUT; C-06)
- ROUTE-177 with INT-177 (`PUR RecordReturnOutcome`; `purchase_return.record`; NX-16): the outcome declared or corrected with its reason — **replacement**, raising the purchase line's replacement expectation, whose units arrive by TASK-039's replacement receipt on a new lot carrying their basis (C-29; AX-04); **refund**, the expectation that keeps the case open until the money arrives as a non-income supplier refund (L-08; WF-FIN-07); a **supplier credit** evidenced against the unpaid purchase value; the **unrecovered share** recognized once through SF-LOSS on its bearer — the causal project per L-38, otherwise the owning company —, a SUPPLIER_RETURN −u / LOSS +u pair with cause RETURN_SETTLEMENT, with one cost-only inter-company allocation when the bearer's company differs from the lot's (L-44; XL-1)
- a client-damaged unit whose case outcome is a supplier return (CM-11): the unrecovered share charged to that case's causal project; a unit damaged in the warehouse (CM-09) returned the same way
- the case's figures and closure on PG-028: returned cost, refunded, replaced and unrecovered as posted counters, the equation's open part shown as the reason "Tutup Kasus" is withheld, and the closure through TASK-031's ROUTE-271 (`cases.close`) refused while returned cost ≠ refunded + replaced + unrecovered (C-46; J-PUR-03); a cost correction of the returned units after closure reopening the case in its own transaction (C-46; AX-34)
- PG-028 with NAV-028 and ROUTE-028 (SCR-26; J-PUR-03): the purchase return and its case — the units returned, the outcome, the figures, the timeline with "Dikoreksi oleh" / "Mengoreksi" links (ADMIN_FLOW §11)

**OUT OF SCOPE:**

- the supplier refund's money — "Catat Kas Masuk Lain" with its RETURN_REFUNDED adjustment, which moves the case counters under NX-10 (ROUTE-259; TASK-054); the replacement receipt's own command (ROUTE-181; TASK-039)
- the case service and ROUTE-271 itself (TASK-031); the cost-correction cascade over returned units (AX-34; ROUTE-175; TASK-037)
- a return recorded in error, reversed by ROUTE-211 with AX-36 (CM-37), which the matrix maps to TASK-048 and whose control sits on PG-028 and PG-034; a drop-ship return to the supplier (CM-15; ROUTE-207; TASK-046)
- purchase cancellation and remainder closure (AX-12, AX-32; TASK-037); the scan field's lookup ROUTE-282 with INT-006 (TASK-039)

**REFERENCES:**

- [WORKFLOWS §5.3](../03-workflows/WORKFLOWS/s05-03-inventory-purchasing.md#wf-pur-03--purchase-return-to-supplier-floor) WF-PUR-03, with [WF-INV-07](../03-workflows/WORKFLOWS/s05-03-inventory-purchasing.md#wf-inv-07--inter-company-allocation-embedded-in-dispatch); [§6](../03-workflows/WORKFLOWS/s06-07-subflows-partial.md#6-reusable-subflows) SF-CASE, SF-CHARGE, SF-LOSS, SF-RSV-CUT, SF-SCAN; [§8](../03-workflows/WORKFLOWS/s08-09-corrections-indivisible.md#8-correction-reversal-and-cancellation-matrix) CM-09, CM-11, CM-16, CM-37; [§9](../03-workflows/WORKFLOWS/s08-09-corrections-indivisible.md#9-indivisible-business-actions) AX-11; [§12](../03-workflows/WORKFLOWS/s10-14-signals-decisions-traceability.md#12-planner-determined-level-1-decisions) L-08, L-38, L-44, L-47
- [BUSINESS_RULES §4](../02-domain/BUSINESS_RULES.md#procurement-pur) BR-PUR-01–BR-PUR-05; [Inventory](../02-domain/BUSINESS_RULES.md#inventory-inv) BR-INV-05, BR-INV-06, BR-INV-13; [§2](../02-domain/BUSINESS_RULES.md#2-correction-taxonomy) BR-CR-01, BR-CR-04; [§5](../02-domain/BUSINESS_RULES.md#5-calculation-semantics) CALC-03, CALC-09 and the [numeric annex](../02-domain/BUSINESS_RULES.md#numeric-annex-binding-examples-for-p8-fixtures-gap-004) 12, 14
- [V1_SCOPE](../01-product/V1_SCOPE.md#must-ship) CAP-06; [ACCEPTANCE_CRITERIA](../01-product/ACCEPTANCE_CRITERIA.md#product-acceptance-contracts) AC-06, AC-12
- [DATABASE §4.13](../04-architecture/DATABASE/s04-13-ops.md#413-ops--operations) `correction_cases`; [§4.6](../04-architecture/DATABASE/s04-06-pur.md#46-pur--procurement) `purchase_line_balances`; [§5.3](../04-architecture/DATABASE/s05-inventory.md#53-movement-catalogue-and-sign-discipline) PURCHASE_RETURN; [§6.1](../04-architecture/DATABASE/s06-lot-cost-hpp.md#61-the-chain) the supplier return; [§6.5](../04-architecture/DATABASE/s06-lot-cost-hpp.md#65-inter-company-allocation-overlay); [§18](../04-architecture/DATABASE/s18-19-constraints-integrity.md#18-constraint-catalogue) C-03, C-06, C-21, C-24, C-28, C-29, C-46
- [ARCHITECTURE §7](../04-architecture/ARCHITECTURE.md#7-posting-services-and-guards) `InventoryPosting`, `CostPosting`, `PurchasePosting`
- [CONCURRENCY_IDEMPOTENCY §7.1](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s07-statement-sequences.md#71-lock-sets-of-ax-01ax-37) and [§7.2](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s07-statement-sequences.md#72-checks-effects-and-outcomes-of-ax-01ax-37) AX-11; [§7.3](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s07-statement-sequences.md#73-command-families-outside-ax-0137) NX-10, NX-16; [§9](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s08-09-guards-stale-state.md#9-stale-state) ST-06, ST-09; [§18](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s18-scenarios.md#18-mandatory-concurrency-and-failure-scenarios) M-01, M-07
- [PERMISSIONS_MATRIX §2](../05-security/PERMISSIONS_MATRIX.md#2-authorization-model) AZ-08; [§4](../05-security/PERMISSIONS_MATRIX.md#4-capability-catalogue) `purchase_return.record`, `stock.reserve`, `stock.allocate_intercompany`, `cases.close`; [§7.2](../05-security/PERMISSIONS_MATRIX.md#72-table-classification) rows 39–46, 48, 54, 75, 121; [§7.3](../05-security/PERMISSIONS_MATRIX.md#73-special-projections) PJ-01, PJ-04–PJ-06, PJ-16; [§8](../05-security/PERMISSIONS_MATRIX.md#8-cross-company-link-authorization) XL-1–XL-3
- [ROUTE_CONTRACTS](../03-workflows/ROUTE_CONTRACTS.md#purchases--buat-pembelian-and-detail-pembelian-pur-pg-016-pg-017-wf-pur-01wf-pur-03) ROUTE-176, ROUTE-177; [§2](../03-workflows/ROUTE_CONTRACTS.md#2-page-views--route-001route-065) ROUTE-028; [ROUTE-271](../03-workflows/ROUTE_CONTRACTS.md#operations--correction-cases-and-the-opening-import-ops-pg-040-pg-058-wf-mig-01)
- [INTERACTION_CONTRACTS](../03-workflows/INTERACTION_CONTRACTS.md#purchases--buat-pembelian-and-detail-pembelian-pur-pg-016-pg-017-wf-pur-01wf-pur-03) INT-176, INT-177; [INT-271](../03-workflows/INTERACTION_CONTRACTS.md#operations--correction-cases-and-the-opening-import-ops-pg-040-pg-058-wf-mig-01)
- [NAVIGATION_CONTRACTS §2.3](../07-ux-design/NAVIGATION_CONTRACTS.md#23-purchasing-and-warehouse) NAV-028; [INFORMATION_ARCHITECTURE §8.3](../07-ux-design/INFORMATION_ARCHITECTURE.md#83-purchasing-and-warehouse) PG-028; [§8.7](../07-ux-design/INFORMATION_ARCHITECTURE.md#87-the-approved-screens-scr-01scr-60-and-their-pages) SCR-26
- [ADMIN_FLOW J-PUR-03](../07-ux-design/ADMIN_FLOW/s10-a-journeys.md#103-inventory-and-purchasing); [§11](../07-ux-design/ADMIN_FLOW/s11-13-corrections-numbering-import.md#11-corrections-and-reversals); [IP-06](../07-ux-design/ADMIN_FLOW/s05-command-model.md#ip-06--a-routed-refusal); [IP-09](../07-ux-design/ADMIN_FLOW/s05-command-model.md#ip-09--corrections-closures-and-destructive-actions); [IP-20](../07-ux-design/ADMIN_FLOW/s06-09-session-forms-lists.md#ip-20--choosing-a-reservation-cut); [§14](../07-ux-design/ADMIN_FLOW/s14-15-messages-scenarios.md#14-message-catalogue) MSG-14, MSG-40
- [TEST_STRATEGY §5](../08-testing/TEST_STRATEGY.md#5-domain-and-data-integrity) FX-03; [§6](../08-testing/TEST_STRATEGY.md#6-concurrency-and-failure-proofs); [SECURITY_TEST_MATRIX](../08-testing/SECURITY_TEST_MATRIX.md#3-the-matrix) SM-01, SM-04, SM-25; [PERFORMANCE_TARGETS §3](../08-testing/PERFORMANCE_TARGETS.md#3-the-measured-targets) PFM-01, PFM-02, PFM-20
- [GAP_REGISTER](../00-governance/GAP_REGISTER.md#gap-028--inventory-loss-and-damaged-return-cost-could-disappear) GAP-028
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (40): O-02; CAP-06; AC-06; AC-12; AC-16; OS-02; REF-029; IMG-03–IMG-04; OWN-01; OWN-04–OWN-05; BR-PUR-01–BR-PUR-05; CALC-03; CALC-09; WF-PUR-03; CM-09; CM-11; CM-16; CM-37; AX-11; L-47; ROUTE-028; ROUTE-176–ROUTE-177; INT-176–INT-177; C-21; C-46; NX-16; M-01; M-07; PG-028; SCR-26; J-PUR-03; NAV-028

**EXPECTED MODULES / FILE AREAS:**

- the INV action ReturnToSupplier with `ShowSupplierReturns` and its query classes, the PUR action RecordReturnOutcome (RC-02; ARCHITECTURE §4); `InventoryPosting`, `CostPosting` — the supplier-return draws, the RETURN_SETTLEMENT pair and the purchase-return case figures — and `PurchasePosting` for the replacement expectation (ARCHITECTURE §7); `resources/js/Pages/INV` for PG-028; `tests/Feature/INV`, `tests/Feature/PUR`, `tests/Concurrency/`, `tests/Reconciliation/`

**BUSINESS RULES:**

- BR-INV-06 and annex 12: the returned quantity ≤ the lot's quantity − its consumption; units consumed by another company's project come back only through their restoration first, never "unallocated" in place
- BR-PUR-01–BR-PUR-05 with CALC-03: the purchase keeps its actual supplier, quantities, prices, taxes and charges; a replacement is expected separately from the line's normal remainder and capped by it (C-29); an unfillable remainder still ends only by its closure (BR-PUR-04); each returned unit's charge share follows the unit — recovered with the refund or replacement, otherwise unrecovered (SF-CHARGE; BR-PUR-05; L-47; C-21)
- BR-INV-13 and SF-LOSS: the unrecovered share is a loss exactly once on its bearer — the causal project per L-38, otherwise the owning company (CALC-09) —, never HPP and never a second cost; L-44: when that bearer's company differs from the lot's, the return consumption carries one cost-only allocation
- L-08 and BR-FIN-01: a supplier refund is non-income Cash-In, never revenue; creating the return moves no cash (BR-PUR-03)
- BR-CR-04 with AX-11: the return case closes only when returned cost = refunded + replaced + unrecovered over posted figures; an expected refund neither received nor evidenced keeps it open; BR-CR-01: every return carries its reason, actor, time and links

**ARCHITECTURE RULES:**

- INV (tier 5) records the stock-out and posts its cost through CST (tier 3); PUR (tier 4) records the outcome and the replacement expectation, never calling INV (ARCHITECTURE §3); the case counters move only through their posting services, in the transactions of the facts that post them (DATABASE §4.13; C-61)
- the closure equation is a CHECK on `correction_cases`: a commit that leaves a CLOSED purchase-return case unbalanced fails, so any correction reopens it in its own transaction (C-46)

**SECURITY / PERMISSION REQUIREMENTS:**

- ROUTE-028 `purchase_return.record` with `stock.view` and `cost.view`; ROUTE-176 the same, another company's lot or bearer also `stock.allocate_intercompany` with a grant on the bearer company only (XL-1), a cut also `stock.reserve` (IP-20); ROUTE-177 `purchase_return.record`; "Tutup Kasus" `cases.close` — each ADM_PLUS, separately granted, its reason mandatory (RG-08; IP-09)
- opening the case needs the capability of the return it records — there is no standalone case capability (PERMISSIONS_MATRIX §4); a control is absent without its capability (AZ-01)

**COMPANY SCOPE AND FIELD PROJECTION:**

- the command's company is the purchase's company (CS-03); the lot must be that purchase's lot, its source company the purchase's (C-08; CS-04); a chosen effect on another company's record — a cut of its project's reservation, an unrecovered share charged to its causal project — needs a grant on that company, with XL-1 for the allocation, or the command is REJECTED and routed (AZ-08; QS-20; IP-06; MSG-14)
- rows 39–46: purchases, lines, charges and their balances CV(c); row 48: the lot's physical fields to `stock.view` with the relation marker (PJ-01; PJ-04); rows 54 and 75: consumptions and cost attributions on the bearer side CV(bearer c) with source identifiers masked, the source side CV(source c) with the bearer masked (PJ-05; PJ-06)
- row 121 and PJ-16: the case with `projects.view` of its company; its returned, refunded, replaced and unrecovered counters by family; a causal project of another company named only when in scope, otherwise the relation marker (XL-3); rows for the other side written only as SYS consequences of the case's commands (XL-2)

**CONCURRENCY / IDEMPOTENCY:**

- AX-11 for ROUTE-176, STATIC: LK-00, LK-01 (the purchase's project, the case's causal project, the projects of reservations cut), LK-03, LK-10, LK-13, LK-14, LK-15, LK-17, LK-18, LK-19, LK-21, LK-23, LK-29; re-read under lock: the case OPEN, the lot's unconsumed quantity present, the cuts; stale check ST-09; guards: case counters, product, scope, lot, serial, reservation, line, purchase line, lot cost; facts: the PURCHASE_RETURN movement, its consumptions with their guards, SUPPLIER_RETURN attributions, CUT events; refusals C-03, C-06, AZ-08; identity the return case and intent; retry class A (CONCURRENCY_IDEMPOTENCY §7.1–§7.2)
- NX-16 for ROUTE-177 (LK-00, LK-01, LK-03, LK-10, LK-21, LK-27, LK-29): moves the case counters and the purchase line's replacement expectation, holding the projects the case names at LK-01; the unrecovered share recognized on its bearer's project
- a return racing a dispatch of the same lot meets it at LK-13: one commits, the other is REJECTED with the current remainder (ST-06; M-01's form); a return racing a cost correction of its lot meets the cascade's protocol (M-07); one `command_id` replayed returns the recorded outcome, re-authorized (CI-05)

**AUDIT:**

- the return, each outcome and the case's opening and closure are business audit events in their commands' transactions with the individual actor, reason, before/after and links to the purchase, the lot and the case (BR-XC-01; BR-CR-01; LG-01); each is ADM_PLUS and reaches the Owner's review queue (AZ-10; QS-14); a return with an inter-company allocation records the allocation reason and the authorizing actor (C-28; XL-1); a cross-company event shows each side only its own keys (PJ-11)

**DATABASE IMPACT ASSESSMENT:**

- Schema change: NONE — uses the tables of TASK-005–TASK-009; a missing column is an executor discovery (AGENT_OPERATING_MODEL)
- Migration required: NO
- Existing data transformation: NONE
- Direct production DB access: FORBIDDEN
- Data-loss risk: NONE KNOWN

**MODULARITY / EXTENSIBILITY:**

- Module boundary affected: INV (tier 5) for the stock-out, PUR (tier 4) for the outcome, CST (tier 3) for the cost and the case figures (ARCHITECTURE §3)
- Stable core kept provider-neutral: YES
- External/vendor-specific code isolated: YES — framework facilities or a small interface (ARCHITECTURE §2)
- Replaceable adapter/contract required: NO
- Provider lock-in risk: NONE beyond the Owner's baseline stack
- Future extension impact: LOW
- Speculative abstraction avoided: YES — no generic workflow or rules engine for returns (ARCHITECTURE §1)

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

- Context7: REQUIRED — PostgreSQL CHECK constraints over several counters of one row and row locks, the framework's database transactions, at the installed versions
- Graphify: OPTIONAL — once INV and PUR's other Tasks exist
- Browser E2E: REQUIRED — J-PUR-03: selecting and scanning units on the phone, the outcome and closure on the desktop
- Other: the two-connection harness and FX-03's charges-across-receipts and cross-company return scenarios (TASK-003)
- Missing safe tool behavior: AUTO-INSTALL project-locally inside this Task (AICWDF §6.3) and record it in TOOLCHAIN; a paid tier or a privileged installation STOPS for the Owner (COST_POLICY)

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: refreshed before the change when Graphify is available ([TOOLCHAIN](../00-governance/TOOLCHAIN.md)); otherwise a targeted search of INV, PUR and CST's posting services
- Pre-change questions: which commands move the purchase-return case's figures — the refund (NX-10), the replacement receipt (AX-04), the cost cascade (AX-34) — and which read the case's closure (TASK-055)
- Expected affected areas: INV's return action, PUR's outcome action, `CostPosting`'s case figures; PG-028; the entry on PG-016

**CONTEXT7:**

- Topics to verify: multi-column CHECK constraints evaluated per statement; conditional updates of guard rows
- Version-sensitive assumptions: none from memory

**UI / UX REQUIREMENTS:**

- Follow global design rules: YES
- Indonesian default: YES
- English support: YES
- Plain-language Indonesian: YES — the outcomes *Diganti* and *Dikembalikan dananya*; the open part of the closure equation in business words as the reason "Tutup Kasus" is withheld (J-PUR-03); the reason field MSG-40
- Language switch impact: keeps unsent input
- Mobile-first: YES — `[floor]`: selecting and scanning the units on the phone, the outcome and closure on the desktop (J-PUR-03)
- Responsive: YES
- Adaptive: YES — the scan-led selection on the phone, the case figures beside the units on the desktop; 48 px floor controls (D-DS-19)
- Visual breathing room: REQUIRED
- Comfortable density: REQUIRED
- shadcn/ui reuse: the detail layout (PT-12) with the case's figures, the scan field (PT-20), dense rows (PT-06), the reservation-cut block (IP-20), the confirmation with its reason (PT-26), status badges *Terbuka* and *Ditutup* (PT-13), the timeline (PT-18)
- Design reference: PG-028 as INFORMATION_ARCHITECTURE §8.3 and J-PUR-03 state it; the Golden Frontend Reference for presentation only (DIR-061 SD-02)
- Primary action: "Retur ke Pemasok" until units are returned, then the outcome, then "Tutup Kasus" when the equation balances
- Secondary actions: opening the purchase or the lot; the return's reversal (CM-37; TASK-048's route)
- Destructive actions: none — no control deletes a return or a case (FS-02)
- Redundancy constraints: one case per return; "Tutup Kasus" absent while the equation's open part stands; no refund money recorded on this page (TASK-054)
- Loading state: PT-19
- Empty state: no open return — one sentence (PT-19)
- Error state: the rejection with its rule and the current figure (MSG-02) — more than the unconsumed quantity, a cut missing —; the routed refusal MSG-14; the closure's refusal naming the open part

**ROUTE IMPACT:**

- ROUTE-028 (PG-028's view, GET `/gudang/retur-pemasok`)
- ROUTE-176 (POST `/pembelian/{id}/retur`) and ROUTE-177 (POST `/pembelian/{id}/retur/{ret}/hasil`)
- ROUTE-271 (TASK-031) reached from PG-028's "Tutup Kasus"; each route answering RC-05's errors

**INTERACTION CONTRACTS:**

- INT-176 and INT-177, each with its *Test* as its test (IG-05); INT-271 on PG-028; INT-016 and INT-017 for CONFLICT and FAILED; the reason step of IG-03 on every command

**NAVIGATION CONTRACT:**

- Parent: PG-016 · *Gudang* (NAV-028)
- Entry: *Gudang* › *Lainnya*; the purchase page PG-016; the lot's "Retur ke Pemasok" (ADMIN_FLOW §11)
- Back action: "Kembali" → the origin
- Cancel: "Batal" → the origin; the reason step's "Kembali" → the form, the input kept (NV-02)
- Success destination: PG-028 with the case and the answer (NAV-028)
- Error behavior: NV-04; a record outside scope → PG-005 (IP-21)
- State preservation: the open section or tab in the address (NV-05); unsent input kept across the re-login
- Mobile behavior: `[floor]` — selecting and scanning the units

**ACCEPTANCE CRITERIA:**

1. A return of more than the lot's physically present, unconsumed quantity — units dispatched or allocated to another company's project included — is REJECTED with the current figure; after A's lot of 10 has 6 allocated to B's project, A returns at most 4 (BR-INV-06; annex 12; C-03).
2. A return commits its stock-out, its PURCHASE_RETURN consumptions, the SUPPLIER_RETURN draw of the units' current net cost — goods, non-creditable tax and charge shares — and the case's returned cost in one action, never as HPP and never twice (AX-11; SF-CHARGE; C-21).
3. A return that would leave usable supply below the reservations commits only with the actor's chosen cuts; a cut of another company's project without a grant on it is REJECTED and routed (SF-RSV-CUT; C-06; AZ-08).
4. A replacement raises the purchase line's replacement expectation, separate from its normal remainder, and the replacement receipt is capped by it; a refund keeps the case open until the money arrives as a non-income supplier refund or a supplier credit is evidenced (C-29; L-08; WF-PUR-03).
5. An unrecovered share is recognized once on its bearer — the causal project, otherwise the owning company — and, when the bearer's company differs from the lot's, with one cost-only allocation that needs `stock.allocate_intercompany` and a grant on the bearer (SF-LOSS; L-44; XL-1; C-24; C-28).
6. "Tutup Kasus" is withheld, and a closure sent anyway is REJECTED, while returned cost ≠ refunded + replaced + unrecovered; a later cost correction of the returned units reopens a closed case in its own transaction (C-46; AX-34).
7. The return and its outcome require `purchase_return.record` with `stock.view` and `cost.view`; without them the controls are absent and the routes answer MSG-10 or MSG-09 (AZ-11; SM-04).
8. One `command_id` replayed on ROUTE-176 or ROUTE-177 returns the recorded outcome and never returns the units twice (CI-05; AC-16).

**TEST REQUIREMENTS:**

- Lint: TL-01
- Typecheck: TL-02
- Unit: the closure equation and the unrecovered share's split, against annex 12 and 14 (TL-03)
- Feature: ROUTE-176 and ROUTE-177 with each refusal; the closure through ROUTE-271 on PG-028 (TL-04)
- Integration: a return racing a dispatch and a cost correction of the same lot (TL-05; M-01's form, M-07); a replacement receipt against the expectation; a refund recorded later through TASK-054's command where it exists, otherwise a synthetic RETURN_REFUNDED posting; the reconciliation suite after each (TEST_STRATEGY §5)
- Authorization: `purchase_return.record`, `cases.close` and `stock.allocate_intercompany` as separately granted ADM_PLUS capabilities; a chosen effect on another company's record routed (TL-06; SM-04)
- Route integrity: ROUTE-028, ROUTE-176, ROUTE-177 audited (TL-07; SM-02)
- Interaction: INT-176 and INT-177 per IG-05, with one `command_id` replayed (TL-08)
- Browser E2E: J-PUR-03 on the phone and the desktop (TL-09)
- Localization/language switch: both languages on PG-028 (TL-10)
- Visual breathing room/density: TEST_STRATEGY §9
- Responsive: TL-11
- Accessibility: TL-12
- Production build: TL-13
- Security: SM-01 for another company's purchase or lot; SM-25 for the case's cost counters without `cost.view` (TL-14)
- Performance: PFM-01 and PFM-02 for PG-028; PFM-20 with QB-13 for AX-11 and NX-16 (TL-15)
- Migration: N/A — no migration or import code in this Task
- Other: GAP-028's no-cost-lost proof for a return with an unrecovered share recorded as its evidence

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

- TASK-055 — when its other dependencies are DONE and its READY gate holds

**DO NOT DO:**

- add to V1 — a capability, page, route, field, integration, document type, report or paid service — or drop, defer or narrow a V1_REQUIRED requirement; a Task that would need either raises an Owner question and waits ([DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-20)
- access the VPS, production, its data or a credential, or put a secret, real data, a host detail or production configuration in the repository ([INFRASTRUCTURE](../09-operations/INFRASTRUCTURE.md) INF-01, INF-05; SECURITY SX-01, SX-04, SX-06; [PRODUCTION_DATA_SAFETY](../00-governance/PRODUCTION_DATA_SAFETY.md))
- add AI, model or tool attribution to a commit — the Owner's configured identity only ([AGENTS.md](../../AGENTS.md#git-commit-attribution); DIR-022)
- start before the Owner's execution authorization at OG-4, or mark DONE without the required tests passing and the completion evidence ([TASK_PLAN](TASK_PLAN.md#2-rules-of-the-plan); [DEFINITION_OF_DONE](../08-testing/DEFINITION_OF_DONE.md#2-the-checklist))
- weaken, skip or delete a valid test to obtain a green run, or adopt a paid tier without the Owner's COST_POLICY decision (DOD-05; [COST_POLICY](../00-governance/COST_POLICY.md))
- return consumed or allocated units in place, or return more than the lot's present, unconsumed quantity (BR-INV-06)
- contra the purchase value for more than the supplier refunds or replaces, count a returned unit's cost as HPP, or recognize its unrecovered share twice (BR-INV-13; SF-LOSS)
- close a purchase-return case while returned cost ≠ refunded + replaced + unrecovered, or let a user write a case counter (C-46)
- record the supplier's refund money on PG-028 instead of through its Cash-In command (TASK-054)

# TASK-048 — Client returns (sales return)

Status: REVIEW | Updated: 2026-10-19 | Owner: Planning

Written by WF-PC5-W's writer of bundle W2 of run PC-5 on 2026-10-19 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99 — a REVIEW draft of P11, approved, or not, with the [Task plan](TASK_PLAN.md) at OG-4. **No Task starts before the Owner's execution authorization at OG-4.** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** TODO

**RISK CLASS:** HIGH

**EXECUTOR:** AGENT

**OBJECTIVE:**

Take delivered goods back from the client: from the delivery, open the sales-return case with its reason and evidence, receive the returned units through SF-RESTORE — usable back into usable stock on the original lot, damaged into UNUSABLE on it — with the HPP contra and any inter-company allocation reversed together with the consumption and an optional new reservation, lower net delivered without reversing the delivery record, list the demand, financial and damaged-unit companions as the case's open steps until done, and reverse exactly a restoration, sales return or purchase return recorded in error, on PG-034 *Retur dari Klien* (WF-FUL-05; SF-RESTORE; AX-10; AX-36; ROUTE-210, ROUTE-211).

**WHY THIS EXISTS:**

AC-06, AC-12 and AC-21 require returns with reasons that reconcile stock and keep every original visible: a return after delivery, billing or payment is linked but distinct stock, delivery and financial corrections, each explicit, none automatic, ending at the case's closure, with completion revalidated (BR-XD-01; architecture challenge 4a). A restoration must restore goods once, never twice (3b), and a client-damaged return must keep its cost on the causal project until its outcome — never vanishing onto the source company's lot (BR-INV-13; DIR-024 D-3; GAP-028; annex 16). The completion predicates (TASK-055) read the case's closure (BR-CR-04).

**DEPENDS ON:**

- TASK-042 — Condition, location moves, adjustments and losses
- TASK-045 — Deliveries (Pengiriman) and delivery closure

**BLOCKS:**

- TASK-055 — Project completion and Force Complete

**PARALLEL SAFE:** NO — restorations and their reversals write the product, scope, lot, serial, reservation, project-line, lot-cost and consumption guards and the case counters that dispatch, adjustments, purchase returns and the cost cascade (TASK-037–TASK-046) also post

**IN SCOPE:**

- ROUTE-210 with INT-210 (`INV RestoreStock`; `sales_return.record`; AX-10): "Retur dari Klien" from a delivered record — *Rincian proyek* › *Pengiriman*, *Tindakan Lain* or PG-030 (INFORMATION_ARCHITECTURE §14.3) —; the SALES_RETURN case with its reason and evidence (SF-CASE; IP-09; IP-16); the returned units, scan-led on the phone (PT-20), each serial one that left on that line's dispatch — otherwise refused at the scan, CM-36 first (L-07) —; the quantity in base units, an alternate-unit entry converted at the cited event's snapshot (BR-INV-03), ≤ net delivered (PX-08); **usable** back into usable stock on the original lot, **damaged** into UNUSABLE on it (BR-INV-06), the consumption's HPP contra'd at its current net cost — a partial restoration taking its proportional share, the residual to the restoration that zeroes the consumption (L-31; BR-FIN-13) — and its inter-company allocation reversed with it (WF-INV-07; L-44; C-28); the case recording the causal project and the source lot (XL-3); an optional new reservation in the same action, never reactivating the consumed one (L-07; IP-19); the likely-duplicate warning by the case (L-49; BD-13)
- net delivered falling by the returned quantity, the delivery record never reversed (PX-08); the case's open steps listed until done (ADMIN_FLOW §11): the demand — re-deliver by a new dispatch, reduce through revision and re-confirmation, or cancel the remaining scope —; the finance — nothing before invoicing; after invoicing or billing an invoice downward revision or void disposing every reduction (CM-12); after payment the released money becoming customer credit, refundable on request (CM-13) —; the damaged units' outcome — repaired by re-inspection, returned to the supplier, or disposed with the loss charged to the causal project, a new allocation on the loss consumption when the lot's company differs (CM-11; SF-LOSS; L-44); record-bound documents revised or voided and the requirements they satisfied reopened (WORKFLOWS §8)
- the case's closure on PG-034 through TASK-031's ROUTE-271 (`cases.close`; BR-CR-04); on a completed project the answer saying it returns to *Aktif* (SF-REVAL; CM-32)
- ROUTE-211 with INT-211 (`INV ReverseRestorationOrReturn`; `stock.reverse`; AX-36): "Balikkan (Tercatat Keliru)" on a restoration, sales return or purchase return recorded in error — on PG-034 and on PG-028 —, its linked reversal ≤ the original net of earlier reversals, the same lots, serials and condition still present, re-establishing the consumption, the HPP or the purchase-value contra and refund expectation, the inter-company allocation and the net dispatched or delivered quantities, with the reservation cut of IP-20 when usable supply would fall below the reservations; companion financial corrections and the case's reopening in the same case; never a loss (CM-37; C-55); for a drop-ship return or confirmation reversal, the one exact DROPSHIP_ERROR_REVERSAL (DATABASE §5.7)
- PG-034 with NAV-034 and ROUTE-034 (SCR-32; J-FUL-05): the return, its case with its open steps, the restored units, the companions' state and "Tutup Kasus"

**OUT OF SCOPE:**

- the other commands that restore through SF-RESTORE — a receiving reversal's dependent restoration (CM-02; TASK-039), the dispatch's reversal and undelivered return (CM-05, CM-06; ROUTE-188; TASK-041), the drop-ship return to the warehouse (CM-14; ROUTE-207; TASK-046) —, whose errors this Task's ROUTE-211 reverses
- the companions' own commands: re-dispatch (TASK-041), revision and re-confirmation (TASK-033, TASK-034), remaining-scope cancellation (TASK-034), the invoice's revision or void (ROUTE-245; TASK-049), credit and refund (ROUTE-252, ROUTE-256; TASK-051, TASK-053), re-inspection and disposal (ROUTE-195, ROUTE-194; TASK-042), the supplier return (ROUTE-176; TASK-044)
- the case service and ROUTE-271 (TASK-031); the scan lookup ROUTE-282 (TASK-059); the upload routes ROUTE-228–ROUTE-231 (TASK-030)

**REFERENCES:**

- [WORKFLOWS §5.4](../03-workflows/WORKFLOWS/s05-04-05-fulfillment-documents.md#wf-ful-05--sales-return-after-delivery-both) WF-FUL-05, with [WF-INV-07](../03-workflows/WORKFLOWS/s05-03-inventory-purchasing.md#wf-inv-07--inter-company-allocation-embedded-in-dispatch); [§6](../03-workflows/WORKFLOWS/s06-07-subflows-partial.md#6-reusable-subflows) SF-RESTORE, SF-CASE, SF-LOSS, SF-RSV-CUT, SF-REVAL, SF-SCAN; [§7](../03-workflows/WORKFLOWS/s06-07-subflows-partial.md#7-partial-processing-rules) PX-08; [§8](../03-workflows/WORKFLOWS/s08-09-corrections-indivisible.md#8-correction-reversal-and-cancellation-matrix) CM-02, CM-05, CM-06, CM-10–CM-14, CM-32, CM-36, CM-37; [§9](../03-workflows/WORKFLOWS/s08-09-corrections-indivisible.md#9-indivisible-business-actions) AX-10, AX-36; [§12](../03-workflows/WORKFLOWS/s10-14-signals-decisions-traceability.md#12-planner-determined-level-1-decisions) L-07, L-11, L-31, L-38, L-44, L-49
- [BUSINESS_RULES §6](../02-domain/BUSINESS_RULES.md#6-cross-domain-interaction-rules) BR-XD-01; [Inventory](../02-domain/BUSINESS_RULES.md#inventory-inv) BR-INV-03, BR-INV-05, BR-INV-06, BR-INV-09, BR-INV-13; [§2](../02-domain/BUSINESS_RULES.md#2-correction-taxonomy) BR-CR-01–BR-CR-05; [annex](../02-domain/BUSINESS_RULES.md#numeric-annex-binding-examples-for-p8-fixtures-gap-004) 16
- [V1_SCOPE](../01-product/V1_SCOPE.md#must-ship) CAP-06; [ACCEPTANCE_CRITERIA](../01-product/ACCEPTANCE_CRITERIA.md#product-acceptance-contracts) AC-06, AC-12, AC-21
- [DATABASE §5.3](../04-architecture/DATABASE/s05-inventory.md#53-movement-catalogue-and-sign-discipline) RESTORATION and MOVEMENT_REVERSAL; [§5.7](../04-architecture/DATABASE/s05-inventory.md#57-drop-ship-and-services); [§6.2](../04-architecture/DATABASE/s06-lot-cost-hpp.md#62-conservation-identity-and-draw-arithmetic), [§6.5](../04-architecture/DATABASE/s06-lot-cost-hpp.md#65-inter-company-allocation-overlay); [§4.13](../04-architecture/DATABASE/s04-13-ops.md#413-ops--operations) `correction_cases`; [§18](../04-architecture/DATABASE/s18-19-constraints-integrity.md#18-constraint-catalogue) C-03, C-06, C-09, C-27, C-28, C-55
- [CONCURRENCY_IDEMPOTENCY §7.1](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s07-statement-sequences.md#71-lock-sets-of-ax-01ax-37) and [§7.2](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s07-statement-sequences.md#72-checks-effects-and-outcomes-of-ax-01ax-37) AX-10, AX-36; [§8](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s08-09-guards-stale-state.md#8-guards-and-first-use-rows) FU-01–FU-03, FU-09, FU-13; [§11](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s10-13-identity-failure-retry.md#11-separate-duplicates) BD-13
- [PERMISSIONS_MATRIX §2](../05-security/PERMISSIONS_MATRIX.md#2-authorization-model) AZ-08; [§4](../05-security/PERMISSIONS_MATRIX.md#4-capability-catalogue) `sales_return.record`, `stock.reverse`, `stock.reserve`, `cases.close`; [§7.2](../05-security/PERMISSIONS_MATRIX.md#72-table-classification) rows 54–56, 61, 75, 121; [§7.3](../05-security/PERMISSIONS_MATRIX.md#73-special-projections) PJ-01, PJ-04–PJ-06, PJ-16; [§8](../05-security/PERMISSIONS_MATRIX.md#8-cross-company-link-authorization) XL-1–XL-3
- [ROUTE_CONTRACTS](../03-workflows/ROUTE_CONTRACTS.md#fulfilment--deliveries-direct-shipments-handovers-and-client-returns-ful-pg-030pg-034) ROUTE-210, ROUTE-211; [§2](../03-workflows/ROUTE_CONTRACTS.md#2-page-views--route-001route-065) ROUTE-034; [ROUTE-271](../03-workflows/ROUTE_CONTRACTS.md#operations--correction-cases-and-the-opening-import-ops-pg-040-pg-058-wf-mig-01)
- [INTERACTION_CONTRACTS](../03-workflows/INTERACTION_CONTRACTS.md#fulfilment--deliveries-direct-shipments-handovers-and-client-returns-ful-pg-030pg-034) INT-210, INT-211; [INT-271](../03-workflows/INTERACTION_CONTRACTS.md#operations--correction-cases-and-the-opening-import-ops-pg-040-pg-058-wf-mig-01)
- [NAVIGATION_CONTRACTS §2.4](../07-ux-design/NAVIGATION_CONTRACTS.md#24-fulfilment-cases-and-documents) NAV-034; [INFORMATION_ARCHITECTURE §8.4](../07-ux-design/INFORMATION_ARCHITECTURE.md#84-fulfilment-cases-and-documents) PG-034; [§14.3](../07-ux-design/INFORMATION_ARCHITECTURE.md#143-scr-32--retur-dari-klien-its-cross-project-entry) SCR-32's entry
- [ADMIN_FLOW J-FUL-05](../07-ux-design/ADMIN_FLOW/s10-b-journeys.md#104-fulfilment); [§11](../07-ux-design/ADMIN_FLOW/s11-13-corrections-numbering-import.md#11-corrections-and-reversals) CM-10–CM-13, CM-37; [IP-09](../07-ux-design/ADMIN_FLOW/s05-command-model.md#ip-09--corrections-closures-and-destructive-actions); [IP-19](../07-ux-design/ADMIN_FLOW/s05-command-model.md#ip-19--several-commands-on-one-screen); [IP-15](../07-ux-design/ADMIN_FLOW/s06-09-session-forms-lists.md#ip-15--scanner); [IP-20](../07-ux-design/ADMIN_FLOW/s06-09-session-forms-lists.md#ip-20--choosing-a-reservation-cut)
- [TEST_STRATEGY §5](../08-testing/TEST_STRATEGY.md#5-domain-and-data-integrity) FX-01 16, FX-03; [§6](../08-testing/TEST_STRATEGY.md#6-concurrency-and-failure-proofs); [§7](../08-testing/TEST_STRATEGY.md#7-browser-end-to-end-tests-and-critical-journeys) CJ-06; [SECURITY_TEST_MATRIX](../08-testing/SECURITY_TEST_MATRIX.md#3-the-matrix) SM-01, SM-04, SM-25; [PERFORMANCE_TARGETS §3](../08-testing/PERFORMANCE_TARGETS.md#3-the-measured-targets) PFM-01, PFM-02, PFM-20
- [GAP_REGISTER](../00-governance/GAP_REGISTER.md#gap-028--inventory-loss-and-damaged-return-cost-could-disappear) GAP-028; [GAP-005](../00-governance/GAP_REGISTER.md#gap-005--cancellation-may-encounter-delivered-billed-or-paid-downstream-records)
- Architecture challenge — the sequences 3b, 4a of the [P11 gate record](evidence/P11_QUALITY_GATE.md#whole-system-architecture-challenge-sp-98), their assertions carried into this Task's acceptance criteria and tests
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (23): WF-FUL-05; SF-RESTORE; CM-02; CM-05–CM-06; CM-10; CM-14; AX-10; AX-36; ROUTE-034; ROUTE-210–ROUTE-211; INT-210–INT-211; C-27–C-28; C-55; BD-13; PG-034; SCR-32; J-FUL-05; NAV-034; GAP-028

**EXPECTED MODULES / FILE AREAS:**

- the INV actions RestoreStock (the sales return's restoration) and ReverseRestorationOrReturn, FUL's `ShowClientReturn` and its query class (RC-02; ARCHITECTURE §3–§4); the restoration posted through INV's `InventoryPosting`, the HPP contra and the return's basis through CST's `CostPosting`, the net delivered figure through PRJ's `DemandPosting` (ARCHITECTURE §7); `resources/js/Pages/FUL` for PG-034; `tests/Feature/INV`, `tests/Feature/FUL`, `tests/Concurrency/`, `tests/Reconciliation/`

**BUSINESS RULES:**

- BR-XD-01 and BR-CR-04: a return after delivery, billing or payment is linked but distinct stock, delivery and financial corrections, each explicit, none automatic, ending at the case's closure; BR-CR-05, L-11 and CM-32: a material return on a completed project revalidates it, one on a cancelled project changes only what it corrects
- SF-RESTORE and L-07: a restoration cites the original consumptions; Σ restored ≤ consumed; a sales return ≤ net delivered (PX-08); returned serials must match; it restores the lot consumption, contra-attributes HPP at each consumption's current net cost and re-enters the units at that amount, never reactivates a consumed reservation
- BR-INV-06 and BR-INV-09: a damaged return lands in UNUSABLE, never AVAILABLE; serial traceability survives the return
- BR-INV-05, L-44 and C-28: the consumption's inter-company allocation is reversed with its restoration, damaged returns included, so net allocation always equals net cross-company consumption; a later loss charged to another company's causal project records a new allocation on the loss consumption
- BR-INV-13, L-38 and annex 16: a client-damaged unit's case names the returning project and the source lot; its loss on disposal is charged once to that project (GAP-028)
- CM-37 and C-55: a restoration or return recorded in error is undone only by its linked reversal, never more than was restored and never a loss; both records stay visible (BR-CR-02)
- BR-INV-03 and L-31: quantities in base units, an alternate-unit entry converting at the cited event's snapshot; the cost residual to the restoration that zeroes the consumption

**ARCHITECTURE RULES:**

- the restoration and its reversal are INV (tier 5) commands posting CST's cost (tier 3); PG-034 is FUL's page reading INV, PRJ and OPS through their query classes (ARCHITECTURE §3); the case counters move only through their posting services (C-61)
- consumption-creating movements are reversed only through capped restorations; every other correctable movement through a MOVEMENT_REVERSAL capped per lot, location and condition (DATABASE §5.3)

**SECURITY / PERMISSION REQUIREMENTS:**

- ROUTE-034 and ROUTE-210 `sales_return.record` (ADM_PLUS, prerequisites `stock.view` and `projects.view`), the same-action reservation `stock.reserve`, the companions by their own capabilities; ROUTE-211 `stock.reverse` (ADM_PLUS), a cut also `stock.reserve` (IP-20); "Tutup Kasus" `cases.close`; each with its reason (IP-09; MSG-40)
- the financial companions need `finance.view` and their capabilities; without them the case stays open and listed in QS-07 for a colleague (J-FUL-05)

**COMPANY SCOPE AND FIELD PROJECTION:**

- the return's company is the project's (CS-03); the case's causal project and source lot are set by SYS from the restored consumption, never chosen, and may name another company's lot — shown as named only when that company is in scope, otherwise the relation marker or the lot code (XL-3; PJ-04); the other side's rows are written only as SYS consequences of the case's commands (XL-2); reversing along the same consumption needs the case company in scope (XL-1; AZ-08)
- row 56: restorations PV(c) of the case or bearer company; rows 54 and 75: consumptions and HPP contras on the bearer side CV(bearer c), source identifiers masked (PJ-05; PJ-06); row 61: reservations SV with the relation marker; row 121 and PJ-16: the case with `projects.view`, its money counters by family
- the pooled lot rows through physical-only query classes (PJ-01)

**CONCURRENCY / IDEMPOTENCY:**

- AX-10 for ROUTE-210, STATIC: LK-00, LK-01 (the consumption's bearer project), LK-03, LK-10, LK-13, LK-14, LK-15, LK-17, LK-18, LK-19, LK-21, LK-22, LK-23, LK-24, LK-29; first-use rows FU-01, FU-02, FU-09, FU-13; re-read under lock: the case OPEN, the consumption's open quantity ≥ the quantity (C-27), the PX-08 caps, the returned serials dispatched on that consumption; stale check ST-09; refusals C-27, C-30, L-49, AZ-08; identity BD-13 by the case, otherwise the line; retry class A (CONCURRENCY_IDEMPOTENCY §7.1–§7.2)
- AX-36 for ROUTE-211, STATIC: LK-00, LK-01 (the re-established consumption's bearer project, the projects the reversed event's case names, for a purchase return the purchase's project, the projects of reservations cut), LK-03, LK-10, LK-13, LK-14, LK-15, LK-16, LK-17, LK-18, LK-19, LK-21, LK-22, LK-23, LK-24, LK-29; FU-03; re-read: the reversal ≤ the original net of earlier reversals, the same lots, serials and condition present, the cuts; deferred: reversal signs; refusals C-55, C-03, C-06, AZ-08; identity the original event; retry class A
- a sales return and an undelivered return of the same shipment meet at LK-19 and LK-24 under their PX-08 caps — net delivered and net dispatched − net delivered —, so no unit is restored twice (architecture challenge 3b); a return racing a cost correction of its lot follows the cascade's protocol (M-07); one `command_id` replayed returns the recorded outcome (CI-05)

**AUDIT:**

- the return, its case, each restoration and each reversal are business audit events with the individual actor, reason, evidence references, before/after and links to the delivery, the consumption and the case (BR-XC-01; BR-CR-01; LG-01); all are ADM_PLUS and reach the Owner's review queue (AZ-10; QS-14); the original and its reversal show "Dikoreksi oleh" and "Mengoreksi" (ADMIN_FLOW §11); a cross-company restoration's event shows each side only its own keys (PJ-11); a likely-duplicate confirmation commits under the acknowledged variant of its action code (QS-21)

**DATABASE IMPACT ASSESSMENT:**

- Schema change: NONE — uses the tables of TASK-005–TASK-009; a missing column is an executor discovery (AGENT_OPERATING_MODEL)
- Migration required: NO
- Existing data transformation: NONE
- Direct production DB access: FORBIDDEN
- Data-loss risk: NONE KNOWN

**MODULARITY / EXTENSIBILITY:**

- Module boundary affected: INV (tier 5) for the restoration and its reversal, CST for cost, FUL (tier 6) for PG-034 (ARCHITECTURE §3)
- Stable core kept provider-neutral: YES
- External/vendor-specific code isolated: YES — framework facilities or a small interface (ARCHITECTURE §2)
- Replaceable adapter/contract required: NO
- Provider lock-in risk: NONE beyond the Owner's baseline stack
- Future extension impact: MEDIUM — every later restoration kind is reversed through this Task's AX-36 command
- Speculative abstraction avoided: YES — no generic workflow engine; each companion stays its module's command (ARCHITECTURE §1)

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

- Context7: REQUIRED — PostgreSQL deferred constraint triggers for reversal signs and composite foreign keys, the framework's database transactions, at the installed versions
- Graphify: OPTIONAL — once the other restoring Tasks exist
- Browser E2E: REQUIRED — J-FUL-05: receiving the returned units with the scan field on the phone, the case and its companions at the desk; CJ-06's return
- Other: the two-connection harness and FX-03's cross-company returns, erroneous restorations and alternate-unit partial return (TASK-003)
- Missing safe tool behavior: AUTO-INSTALL project-locally inside this Task (AICWDF §6.3) and record it in TOOLCHAIN; a paid tier or a privileged installation STOPS for the Owner (COST_POLICY)

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: refreshed before the change when Graphify is available ([TOOLCHAIN](../00-governance/TOOLCHAIN.md)); otherwise a targeted search of INV's restorations, CST's contras and the case's posting service
- Pre-change questions: which restoration kinds exist (CM-05, CM-06, CM-10–CM-14, CM-15) and which reversal each takes (MOVEMENT_REVERSAL, RETURN_ERROR_REVERSAL, DROPSHIP_ERROR_REVERSAL); which predicates read the return case
- Expected affected areas: INV's restoration and reversal actions; `InventoryPosting`, `CostPosting`, `DemandPosting`; PG-034; the reversal control on PG-028

**CONTEXT7:**

- Topics to verify: deferred constraint triggers checked once per movement at commit; conditional serial transitions
- Version-sensitive assumptions: none from memory

**UI / UX REQUIREMENTS:**

- Follow global design rules: YES
- Indonesian default: YES
- English support: YES
- Plain-language Indonesian: YES — *Layak pakai* and *Rusak*; the case's open steps named in business words; on a completed project the answer saying it returns to *Aktif*; "Balikkan (Tercatat Keliru)" saying it re-establishes the consumption and is never a loss (ADMIN_FLOW §11)
- Language switch impact: keeps unsent input and the scanned units not yet sent
- Mobile-first: YES — `[both]`: receiving the returned units with the scan field on the phone; the case and the financial companion at the desk (J-FUL-05)
- Responsive: YES
- Adaptive: YES — scan-led on the phone, the case's open steps beside the units on the desktop; 48 px floor controls (D-DS-19)
- Visual breathing room: REQUIRED
- Comfortable density: REQUIRED
- shadcn/ui reuse: the detail layout (PT-12), the scan field (PT-20), dense rows (PT-06), the reservation-cut block (IP-20), the confirmation with its reason (PT-26), the readiness list for the case's open steps (PT-29), status badges *Terbuka*, *Ditutup*, *Layak Pakai*, *Tidak Layak Pakai* (PT-13), the timeline (PT-18)
- Design reference: PG-034 as INFORMATION_ARCHITECTURE §8.4 and J-FUL-05 state it; the Golden Frontend Reference for presentation only (DIR-061 SD-02)
- Primary action: "Retur dari Klien" on a delivered record; then the next open step; "Tutup Kasus" when none remains
- Secondary actions: the companions' links by capability; the same-action reservation offer; "Balikkan (Tercatat Keliru)"
- Destructive actions: none — no control deletes a return or a case; a correction is the linked reversal (FS-02)
- Redundancy constraints: a return starts only from its delivery, never from PG-040 (INFORMATION_ARCHITECTURE §14.3); one reversal per original within its cap (C-55)
- Loading state: PT-19
- Empty state: no return yet for the project — one sentence (PT-19)
- Error state: a serial that did not leave on that line refused at the scan with CM-36's path (MSG-36); more than net delivered REJECTED with the figure (MSG-02); the routed refusal MSG-14; MSG-15 for a likely duplicate

**ROUTE IMPACT:**

- ROUTE-034 (PG-034's view, GET `/proyek/{id}/retur`)
- ROUTE-210 (POST `/proyek/{id}/retur`) and ROUTE-211 (POST `/retur/{id}/balikkan`, from PG-034 and PG-028); ROUTE-271 (TASK-031) from PG-034's "Tutup Kasus"; each answering RC-05's errors

**INTERACTION CONTRACTS:**

- INT-210 and INT-211, each with its *Test* as its test (IG-05); INT-271 on PG-034; INT-006 for the scan field, INT-013 for the evidence, INT-015 for a likely duplicate's second submission of ROUTE-210; INT-016 and INT-017 for CONFLICT and FAILED

**NAVIGATION CONTRACT:**

- Parent: PG-011 · PG-030 (NAV-034)
- Entry: *Rincian proyek* › *Pengiriman*; *Tindakan Lain*; PG-030 › a delivered record
- Back action: "Kembali" → the origin
- Cancel: "Batal" → the origin; the reason step's "Kembali" → the form, the input kept (NV-02)
- Success destination: PG-034 with the case opened and the answer (NAV-034)
- Error behavior: NV-04; a record outside scope → PG-005 (IP-21)
- State preservation: the open section or tab in the address (NV-05); unsent input kept across the re-login
- Mobile behavior: `[both]` — the scan field for the returned units

**ACCEPTANCE CRITERIA:**

1. A return of more than net delivered, or of a serial that did not leave on that line's dispatch, is REJECTED with the figure or refused at the scan; a returned unit is restored once and never also by an undelivered return of the same shipment (PX-08; L-07; C-27; architecture challenge 3b).
2. A usable return re-enters usable stock on its original lot and a damaged one UNUSABLE on it, each with the HPP contra at the consumption's current net cost — proportional for a partial return, the residual to the restoration that zeroes it — and the consumption's inter-company allocation reversed in the same action (SF-RESTORE; L-31; L-44; C-28; BR-INV-06).
3. The return lowers net delivered by the returned quantity and never reverses the delivery record; it never reactivates the consumed reservation, and a new one is created only by the same-action offer with `stock.reserve` (PX-08; L-07; AX-10).
4. A return after delivery and billing leaves the invoice unchanged until its own revision or void, and after payment the released money unchanged until its own credit command: each companion is an explicit, separate command listed as the case's open step until done, and the case's closure is what the completion predicate reads (BR-XD-01; CM-12; CM-13; architecture challenge 4a).
5. A client-damaged return's case names the returning project and the source lot; disposing of the unit later charges its loss once to that project, with one new allocation when the lot's company differs (CM-11; SF-LOSS; L-44; annex 16; GAP-028).
6. A return on a completed project returns it to *Aktif* with a link, and one on a cancelled project changes only what it corrects (SF-REVAL; L-11; CM-32).
7. "Balikkan (Tercatat Keliru)" reverses a restoration, sales return or purchase return once within the original net of earlier reversals, re-establishing the consumption, its cost or the purchase-value contra, the allocation and the net quantities, never as a loss; one that would leave usable supply below the reservations commits only with the actor's cuts (CM-37; AX-36; C-55; C-06).
8. A likely duplicate return without a distinguishing reference is REJECTED with the matches until the actor confirms it with a reason; one `command_id` replayed returns once (L-49; BD-13; CI-05).
9. The return and its reversal are absent without `sales_return.record` and `stock.reverse` (AZ-01; SM-04).

**TEST REQUIREMENTS:**

- Lint: TL-01
- Typecheck: TL-02
- Unit: the proportional contra and its residual, the alternate-unit conversion at the cited snapshot (TL-03; L-31; FX-01 10)
- Feature: ROUTE-210 and ROUTE-211 with each refusal (TL-04)
- Integration: a sales return and an undelivered return of one shipment racing; a return racing a cost correction of its lot (M-07); a cross-company return with its allocation reversed; FX-01 16 and FX-03's erroneous restorations and alternate-unit partial return; the reconciliation suite after each (TL-05; TEST_STRATEGY §5, §6)
- Authorization: `sales_return.record` and `stock.reverse` as separately granted ADM_PLUS capabilities; a chosen effect on another company's record routed (TL-06; SM-04)
- Route integrity: ROUTE-034, ROUTE-210, ROUTE-211 audited (TL-07; SM-02)
- Interaction: INT-210 and INT-211 per IG-05, with one `command_id` replayed (TL-08)
- Browser E2E: J-FUL-05 on the phone and the desktop; CJ-06's return after billing (TL-09)
- Localization/language switch: both languages on PG-034 (TL-10)
- Visual breathing room/density: TEST_STRATEGY §9
- Responsive: TL-11
- Accessibility: TL-12
- Production build: TL-13
- Security: SM-01 for another company's delivery; SM-25 for the contra amounts without `cost.view` (TL-14)
- Performance: PFM-01 and PFM-02 for PG-034; PFM-20 with QB-13 for AX-10 and AX-36 (TL-15)
- Migration: N/A — no migration or import code in this Task
- Other: CM-10–CM-13 and CM-37's expected end states asserted, each also under concurrency (GAP-005; TEST_STRATEGY §5)

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
- reverse a delivery record for a return, or make any companion correction — invoice, credit, re-delivery, damaged outcome — automatic (PX-08; BR-XD-01)
- land a damaged return in usable stock, reactivate a consumed reservation, or leave an allocation standing after its consumption is restored (BR-INV-06; L-07; L-44)
- undo a restoration or return recorded in error by anything but its linked reversal, or record that reversal as a loss (CM-37)
- start a return from PG-040 or anywhere but its delivery (INFORMATION_ARCHITECTURE §14.3)

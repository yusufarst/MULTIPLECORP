# TASK-038 — Reservations

Status: APPROVED | Updated: 2026-10-20 | Owner: Planning

Approval: [APPR-016](../00-governance/DECISION_LOG.md#appr-016--p11-task-plan-tasks-and-feature-coverage-matrix-approved), explicit Owner approval on 2026-10-20 (DIR-085 OF-07) of this document as committed in the first execution session's approval commit; the approved file hash and the verified conditions are recorded there. Approval changes lifecycle only, not implementation authorization.

Written by WF-PC5-W's writer of bundle W1 of run PC-5 on 2026-10-19 and 2026-10-20 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99; the Owner approved P11 with the [Task plan](TASK_PLAN.md) at OG-4 (DIR-085 OF-07). **No Task starts before the freeze record, from which the execution authorization is in force (DIR-085 OF-09).** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** READY

**RISK CLASS:** HIGH

**EXECUTOR:** AGENT

**OBJECTIVE:**

Build reservations (WF-INV-02; SF-RESERVE): "Reservasi", "Kurangi Reservasi" and "Lepas" on PG-025 *Reservasi* and on PG-011's *Item dan pemenuhan* (AX-01, AX-02; ROUTE-184, ROUTE-185), binding usable existing supply to confirmed WAREHOUSE demand under AVAILABLE ≥ 0 and per-line conservation; the reservation service that a receipt's, a restoration's and a dispatch's same-action parts call; SF-RSV-CUT — the explicit human cut that every supply-reducing command carries, routed across companies (IP-20); and INV's part of `ProjectLifecycleParticipant` for re-confirmation, remaining-scope cancellation, re-plan and project cancellation.

**WHY THIS EXISTS:**

The Owner's stock contract makes a reservation a claim on usable supply for confirmed demand, never a physical movement, released or adjusted when the demand changes, with AVAILABLE = ON HAND − RESERVED − UNUSABLE never negative and the final unit never promised twice (OWN-04; AC-16; AC-22; BR-RSV-01–BR-RSV-04; GAP-023; architecture challenge 3a). No timed hold or automatic priority exists — a shortage is resolved by a person's explicit cut (FS-09; BR-RSV-04). Receiving offers reservations, dispatch consumes them and project cancellation releases them (TASK-039, TASK-041, TASK-056), so the reservation rows and their guards come first.

**DEPENDS ON:**

- TASK-028 — Rack locations and minimum stock
- TASK-034 — Commercial confirmation and fulfillment planning

**BLOCKS:**

- TASK-039 — Receiving (Barang Masuk)
- TASK-041 — Dispatch (Barang Keluar) and inter-company allocation
- TASK-056 — Project cancellation

**PARALLEL SAFE:** NO — it owns the reservation rows (LK-18) and the reserved quantities of the product (LK-13) and line (LK-19) guards that receiving, dispatch and every supply-reducing command move

**IN SCOPE:**

- the reservation records of DATABASE §4.7 in use: `reservations` — one ACTIVE per line, ACTIVE, RELEASED or CONSUMED, its remaining base quantity at the product's scale and never ACTIVE at zero — and `reservation_events` — CREATE, INCREASE, DECREASE, RELEASE, CONSUME and CUT, with its reason and, for a cut, the supply-reducing movement —, written by `InventoryPosting` with the product guard's RESERVED in the same transaction, the line guard's reserved quantity through PRJ's `DemandPosting` (DATABASE §5.5; ARCHITECTURE §7); a reservation binds a product's usable quantity to a line and names no lot or serial
- ROUTE-184 with INT-184, "Reservasi" (AX-01): on a confirmed WAREHOUSE line of an ACTIVE project, at most AVAILABLE and the line's remainder under per-line conservation (L-04), creating the line's ACTIVE reservation or increasing it; never from a quotation, a draft or an unconfirmed line (BR-QUO-03); from PG-025, from *Item dan pemenuhan* — "Reservasi" up to the available quantity, the rest shown as a shortage (J-FUL-01; TASK-034) —, and from the confirmation's answer, which proposes the warehouse lines' reservations as separate commands (L-02; IP-19; J-PRJ-02)
- ROUTE-185 with INT-185, "Kurangi Reservasi" and "Lepas" (AX-02): a reduction or release with its reason (J-INV-02), never above the reservation's remaining quantity at commit — a racing dispatch consumes it or the release releases it, never both —; nothing released because time passes (BR-RSV-02; FS-09; PX-02); a residual reservation of a completed, force-completed or cancelled project released as a residual command, shown as "perlu dilepas" (QS-16; BR-PRJ-04; WF-PRJ-03)
- the reservation parts of other commands, through INV's reservation service in their transactions: a receipt's same-action offer for a project-linked line (AX-04; BR-RSV-03; D-UX-12; TASK-039); a restoration's optional new reservation (AX-10; CM-05; TASK-041; TASK-048); dispatch's CONSUME of the line's own reservation, or the unreserved remainder reserved and consumed in the same action (WF-INV-03; TASK-041); a CONSUMED reservation never reactivated (L-07)
- INV's part of `ProjectLifecycleParticipant`, inside PRJ's transaction under LK-01 (ARCHITECTURE §3): AX-13's re-confirmation adjusting the reservations a lower demand affects (BR-QUO-02); AX-14's release of a cancelled or split remainder's reservation (SF-CANCEL-LINE; L-21); AX-27's release of every active reservation of a cancelled project (BR-PRJ-01; TASK-056)
- SF-RSV-CUT (BR-RSV-04; C-06; UXI-35; IP-20): the block "Stok Tidak Cukup untuk Semua Reservasi" in every supply-reducing form — the shortfall and the product's active reservations with their quantities, each project named where its company is in scope, otherwise the relation marker —; the actor enters the cuts until the shortfall is covered, no order is proposed and nothing is cut by the system; the CUT events commit with the reducing movement as one action (AX-02) and need `stock.reserve`; a cut touching a project of a company outside the actor's grants is REJECTED and routed (AZ-08; SQ-23; QS-20; IP-06); the affected projects flagged (QS-04) — the reducing commands are their Tasks' (OUT OF SCOPE)
- PG-025 with NAV-025 and ROUTE-025 (SCR-23): reservations by branch — the project with its company label, the line, the product and the remaining quantity —, another company's project only by the relation marker (PJ-04); filters, sort and "Muat Lagi" (IP-18); "Reservasi", "Kurangi Reservasi" and "Lepas" by capability
- INV's part of `CompletionPredicateProvider` for the reservations — no active reservation of the project (WF-PRJ-03 predicate 8) —, the predicate TASK-055's
- the reservations of an imported open project re-created after cutover through these commands, never imported (WF-MIG-01; C-47); a wrong opening lot's reversal carrying its cut like any supply reduction (CM-33)

**OUT OF SCOPE:**

- receiving with its offer's form (TASK-039); dispatch and its consumption, restorations and identity substitution (TASK-041; TASK-048); the supply-reducing commands that carry a cut — receipt reversal (TASK-039), condition change, adjustment and loss (TASK-042), count application (TASK-043), purchase return (TASK-044), the reversal of a restoration or return (TASK-048)
- the commercial confirmation, the line plan and its "Batalkan Sisa" (TASK-034); project cancellation's command (TASK-056); completion and its predicates (TASK-055)
- QS-01–QS-04, QS-16, QS-20 and QS-24 on the home pages (TASK-058); the stock views PG-018–PG-020 (TASK-040)
- any automatic reservation, priority rule, timed hold or allocation engine (FS-09; BR-RSV-04)

**REFERENCES:**

- [WORKFLOWS §5.3](../03-workflows/WORKFLOWS/s05-03-inventory-purchasing.md#wf-inv-02--reservation-management-desk) WF-INV-02; [§6](../03-workflows/WORKFLOWS/s06-07-subflows-partial.md#6-reusable-subflows) SF-RESERVE, SF-RSV-CUT; [§7](../03-workflows/WORKFLOWS/s06-07-subflows-partial.md#7-partial-processing-rules) PX-01–PX-08; [§8](../03-workflows/WORKFLOWS/s08-09-corrections-indivisible.md#8-correction-reversal-and-cancellation-matrix) CM-02, CM-09, CM-16, CM-27–CM-29, CM-33; [§9](../03-workflows/WORKFLOWS/s08-09-corrections-indivisible.md#9-indivisible-business-actions) AX-01, AX-02; [§10](../03-workflows/WORKFLOWS/s10-14-signals-decisions-traceability.md#10-derived-signals-and-action-queue) QS-01–QS-04, QS-16, QS-20; [§11](../03-workflows/WORKFLOWS/s10-14-signals-decisions-traceability.md#11-cross-company-and-multi-admin-rules); [§12](../03-workflows/WORKFLOWS/s10-14-signals-decisions-traceability.md#12-planner-determined-level-1-decisions) L-02–L-04, L-07, L-13–L-15, L-31, L-35, L-36, L-40, L-42, L-44, L-48; [WF-PRJ-03](../03-workflows/WORKFLOWS/s05-07-08-completion-migration.md#wf-prj-03--project-completion-penyelesaian-desk) predicate 8 and the residual commands; [WF-MIG-01](../03-workflows/WORKFLOWS/s05-07-08-completion-migration.md#wf-mig-01--opening-facts-desk)
- [BUSINESS_RULES SM:Reservation](../02-domain/BUSINESS_RULES.md#smreservation) BR-RSV-01–BR-RSV-04; [Inventory](../02-domain/BUSINESS_RULES.md#inventory-inv) BR-INV-01–BR-INV-13; [§5](../02-domain/BUSINESS_RULES.md#5-calculation-semantics) CALC-01, CALC-02, CALC-09, CALC-11; [the numeric annex](../02-domain/BUSINESS_RULES.md#numeric-annex-binding-examples-for-p8-fixtures-gap-004), examples 7–9; [§7](../02-domain/BUSINESS_RULES.md#7-forbidden-shortcuts) FS-01, FS-09
- [V1_SCOPE](../01-product/V1_SCOPE.md#reservation-and-usable-stock) reservation and usable stock; [ACCEPTANCE_CRITERIA](../01-product/ACCEPTANCE_CRITERIA.md#product-acceptance-contracts) AC-16, AC-22; [REFERENCE_COVERAGE](../01-product/REFERENCE_COVERAGE.md#final-owner-decision-coverage) OWN-04
- [DATABASE §4.7](../04-architecture/DATABASE/s04-07-inv.md#47-inv--inventory) `reservations`, `reservation_events`, `stock_product_balances`; [§5.2](../04-architecture/DATABASE/s05-inventory.md#52-ledger-plus-guard-balances); [§5.5](../04-architecture/DATABASE/s05-inventory.md#55-reservations-and-available); [§9](../04-architecture/DATABASE/s07-10-representations.md#9-project-and-commercial-representation) the line guard by mode; [§18](../04-architecture/DATABASE/s18-19-constraints-integrity.md#18-constraint-catalogue) C-01–C-09, C-24, C-28, C-33, C-47, C-61; [§25](../04-architecture/DATABASE/s25-28-transactions-handoffs.md#25-transaction-boundary-map) AX-01, AX-02
- [ARCHITECTURE §3](../04-architecture/ARCHITECTURE.md#3-modules) `ProjectLifecycleParticipant`, `CompletionPredicateProvider`; [§7](../04-architecture/ARCHITECTURE.md#7-posting-services-and-guards) `InventoryPosting`, `DemandPosting`
- [CONCURRENCY_IDEMPOTENCY §7.1](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s07-statement-sequences.md#71-lock-sets-of-ax-01ax-37) and [§7.2](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s07-statement-sequences.md#72-checks-effects-and-outcomes-of-ax-01ax-37) AX-01, AX-02 and the cut choice; [§8](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s08-09-guards-stale-state.md#8-guards-and-first-use-rows) FU-09, GU-05; [§9](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s08-09-guards-stale-state.md#9-stale-state) ST-06; [§12](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s10-13-identity-failure-retry.md#12-failure-mapping) SQ-23; [§18](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s18-scenarios.md#18-mandatory-concurrency-and-failure-scenarios) M-02, M-03
- [PERMISSIONS_MATRIX §4](../05-security/PERMISSIONS_MATRIX.md#4-capability-catalogue) `stock.reserve`; [§5](../05-security/PERMISSIONS_MATRIX.md#5-workflows-4-mapping) W4-12; [§2](../05-security/PERMISSIONS_MATRIX.md#2-authorization-model) AZ-08; [§6](../05-security/PERMISSIONS_MATRIX.md#6-company-scope-rules) CS-03, CS-05, CS-08; [§7.2](../05-security/PERMISSIONS_MATRIX.md#72-table-classification) rows 57, 61, 62; [§7.3](../05-security/PERMISSIONS_MATRIX.md#73-special-projections) PJ-01, PJ-04, PJ-13; [§7.4](../05-security/PERMISSIONS_MATRIX.md#74-read-classes) RD-02, RD-03
- [ROUTE_CONTRACTS](../03-workflows/ROUTE_CONTRACTS.md#warehouse--receiving-reservations-dispatch-counts-adjustments-and-discrepancies-inv-pg-018pg-029) ROUTE-184, ROUTE-185; [§2](../03-workflows/ROUTE_CONTRACTS.md#2-page-views--route-001route-065) ROUTE-025; [INTERACTION_CONTRACTS](../03-workflows/INTERACTION_CONTRACTS.md#warehouse--receiving-reservations-dispatch-counts-adjustments-and-discrepancies-inv-pg-018pg-029) INT-184, INT-185
- [NAVIGATION_CONTRACTS §2.3](../07-ux-design/NAVIGATION_CONTRACTS.md#23-purchasing-and-warehouse) NAV-025; [INFORMATION_ARCHITECTURE §8.3](../07-ux-design/INFORMATION_ARCHITECTURE.md#83-purchasing-and-warehouse) PG-025; [ADMIN_FLOW §10.3](../07-ux-design/ADMIN_FLOW/s10-a-journeys.md#103-inventory-and-purchasing) J-INV-02; [IP-20](../07-ux-design/ADMIN_FLOW/s06-09-session-forms-lists.md#ip-20--choosing-a-reservation-cut); [IP-06](../07-ux-design/ADMIN_FLOW/s05-command-model.md#ip-06--a-routed-refusal); [§3](../07-ux-design/ADMIN_FLOW/s01-04-foundations.md#3-derived-interaction-requirements) UXI-35; [§14](../07-ux-design/ADMIN_FLOW/s14-15-messages-scenarios.md#14-message-catalogue) MSG-14; [DESIGN_SYSTEM §14.2](../07-ux-design/DESIGN_SYSTEM.md#142-inventory) PT-17
- [TEST_STRATEGY §5](../08-testing/TEST_STRATEGY.md#5-domain-and-data-integrity) the C- rows and FX-01 7–9; [§6](../08-testing/TEST_STRATEGY.md#6-concurrency-and-failure-proofs) M-02, M-03; [SECURITY_TEST_MATRIX](../08-testing/SECURITY_TEST_MATRIX.md#3-the-matrix) SM-01, SM-04; [PERFORMANCE_TARGETS](../08-testing/PERFORMANCE_TARGETS.md#3-the-measured-targets) PFM-01, PFM-02, PFM-20
- [GAP_REGISTER](../00-governance/GAP_REGISTER.md#gap-023--available-stock-does-not-specify-reservation-or-usable-stock-policy) GAP-023
- Architecture challenge — the sequence 3a of the [P11 gate record](evidence/P11_QUALITY_GATE.md#whole-system-architecture-challenge-sp-98), their assertions carried into this Task's acceptance criteria and tests
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (98): O-02; CAP-06; AC-16; AC-22; OS-02; IMG-03–IMG-04; OWN-01; OWN-04–OWN-05; BR-INV-01–BR-INV-13; BR-RSV-01–BR-RSV-04; CALC-01; CALC-09; CALC-11; FS-01; WF-INV-02; SF-RESERVE; SF-RSV-CUT; PX-01–PX-08; CM-02; CM-05; CM-09; CM-16; CM-27–CM-29; CM-33; CM-36; AX-01–AX-02; L-02–L-03; L-06–L-07; L-13–L-15; L-31; L-35–L-36; L-40; L-42; L-44; L-48; ROUTE-025; ROUTE-184–ROUTE-185; INT-184–INT-185; C-01–C-09; C-24; C-28; C-33; C-47; C-61; M-02–M-03; M-09; PG-025; SCR-23; UXI-35; IP-20; J-PRJ-02; J-INV-02; J-FUL-01; NAV-025; GAP-023

**EXPECTED MODULES / FILE AREAS:**

- the INV module's Reserve and ReleaseReservation actions, its reservation service inside `InventoryPosting` and the cut service the supply-reducing actions call; INV's `ProjectLifecycleParticipant` and its `CompletionPredicateProvider` part; INV's reservation query classes and `ShowReservations`; `resources/js/Pages/INV` for PG-025 and the cut block in `resources/js/features/inv`; `tests/Feature/INV`, `tests/Concurrency/` and `tests/Reconciliation/`

**BUSINESS RULES:**

- BR-RSV-01 and L-02: a reservation is created only from confirmed demand by an explicit command, proposed at confirmation, and only against usable existing supply; at every commit AVAILABLE ≥ 0, and two concurrent demands never both obtain the final unit (AC-16; AC-22; OWN-04)
- BR-RSV-02: a reservation is never a physical movement; dispatch consumes it as the one physical effect; cancellation, reduction or no longer needing it releases or adjusts it; no timed holds (FS-09; PX-02)
- BR-RSV-03: a purchased shortage is not reservable before receipt; the receipt of a project-linked line offers the reservation in the same action (AX-04)
- BR-RSV-04 with SF-RSV-CUT: when usable supply would fall below Σ active reservations the same atomic operation carries the actor's explicit cut of chosen reservations — never an automatic priority —, the affected demand is flagged, and a unit is never counted in RESERVED and UNUSABLE at once
- BR-INV-01 and CALC-01: AVAILABLE = ON HAND − RESERVED − UNUSABLE ≥ 0 — annex 7 (10/3/2 → 5; a reservation of 2 → 3), annex 8 (ON HAND 10, UNUSABLE 2 → at most 8), annex 9 (RESERVED 8 and a loss of 2 usable units → cuts of 2); CALC-02's remaining reservation = reserved − consumed − released or adjusted
- L-04 and C-05: on a WAREHOUSE line reserved remaining + net dispatched + cancelled ≤ pinned demand; drop-ship and service lines are never reserved, creating no warehouse event (BR-INV-11; L-40)
- L-07 and CM-27: a consumed reservation is never reactivated — a restored unit may receive a new one —; a decision recorded in error changes reservations only through SF-RESERVE (L-32); CM-29: cancelling after a reservation only releases it first
- the inventory rule set shared with TASK-039–TASK-043, as it reaches a reservation: BR-INV-02–BR-INV-13 and L-13–L-15, L-35, L-36 and L-42 govern the lots, serials, conditions, counts, losses and increases whose usable supply a reservation reads, and none changes a reservation except through a cut; FS-01 and BR-INV-08: the state is a decision and every quantity is recomputable from the events; BR-INV-03 and L-31: base units at the product's scale (C-09); L-03 and L-44: a reservation is never an allocation and creates no consumption; L-48: an identity substitution leaves it unchanged (CM-36); CALC-09 and CALC-11: a reservation moves no cost — HPP arises at dispatch (BR-FIN-12); L-06, mapped here through its ground BR-INV-13 (nothing vanishes), governs invoice reductions and has no reservation effect
- CM-02, CM-09, CM-16, CM-28 and CM-33: each supply-reducing correction re-evaluates SF-RSV-CUT inside its own action; PX-01–PX-08: reservations are additive events, zero reserved is a valid resting state, excess is refused at the command, and any authorized Admin continues the work (PX-07)

**ARCHITECTURE RULES:**

- INV is tier 5 (ARCHITECTURE §3): it writes reservations and the product guard through `InventoryPosting` and the line guard through PRJ's `DemandPosting`; PRJ never writes INV's rows — re-confirmation, remaining-scope cancellation and project cancellation reach reservations only through `ProjectLifecycleParticipant`, inside PRJ's transaction
- one reservation path: every create, adjust, release, consume and cut goes through the reservation service, whichever action carries it; `GuardUpdate` writes each guard row once per command, so a reserved last-unit dispatch moves ON HAND and RESERVED in one statement (DATABASE §5.2; GU-01)
- a reservation's progress is derived, never a stored workflow status (L-01; FS-01)

**SECURITY / PERMISSION REQUIREMENTS:**

- ROUTE-025 and ROUTE-184 `stock.reserve` with `stock.view` and `projects.view`; ROUTE-185 `stock.reserve` (ADM, W4-12; prerequisites `stock.view`, `projects.view`); a cut inside a supply-reducing command needs `stock.reserve` besides that command's own capability (AZ-08; IP-20)
- each control absent without its capability (IG-01; NV-08), the server deciding either way (AZ-01); the line, the project and the chosen reservations resolved through the scope resolver, the state and the guard figures derived on the server and never taken from the payload (CS-04; DP-03; WS-02)

**COMPANY SCOPE AND FIELD PROJECTION:**

- the command's company is the line's project's (CS-03); availability is pooled physical data read with `stock.view` and no company grant (CS-05; PJ-01; RD-02); a reservation is PROJECT ↔ POOL — its quantity SV with the relation marker, its project PV(c) (rows 61, 62) —, so another company's reservation shows quantity and "perusahaan lain" only (PJ-04)
- a chosen cut on a reservation of a company outside the actor's grants needs a grant on that company or is REJECTED and routed, the refusal disclosing nothing beyond the marker (AZ-08; SQ-23; PJ-04); PG-025 lists each company in scope as its own branch, labelled, never totalled across companies for an Admin (CS-08; PF-41)

**CONCURRENCY / IDEMPOTENCY:**

- AX-01: STATIC, LK-00, LK-01, LK-03, LK-13, LK-18, LK-19 with FU-09 — the line's one ACTIVE reservation inserted in phase D, the partial UNIQUE key the backstop; re-read under lock: the line confirmed and WAREHOUSE, the quantity ≤ AVAILABLE and ≤ the line remainder; guard statements product, reservation, line; refusals C-04, C-05; retry class A (CONCURRENCY_IDEMPOTENCY §7.1, §7.2)
- AX-02: STATIC, the same classes; re-read: the reservation ACTIVE and the quantity ≤ its remaining; refusals C-05 and AZ-08; a cut is part of its supply-reducing command, adds at LK-01 the projects of every reservation it names, and runs under LK-13 and LK-18 with the actor's cuts in the payload, the product CHECK refusing a reduction that lacks them (C-06; §7.2's note)
- races meet at the product guard: the loser of a last-unit race is REJECTED with the current availability, never CONFLICT (ST-06; M-02); a reservation racing another project's dispatch — whichever locks first commits (M-03); a release racing a dispatch of the same reservation — `remaining_qty_base ≥ 0` decides (DATABASE §5.2)
- the participants run inside the caller's transaction under LK-01 (§7.1 notes); every command carries `command_id` (CI-01), its replay re-authorized and returning the recorded outcome, a changed payload CONFLICT (CI-05; M-09)

**AUDIT:**

- each reservation command — create, increase, reduction, release — is one business audit event in its transaction with the individual actor, before and after and the reason where required (BR-XC-01 — stock is a critical area; LG-01); a cut belongs to the audit event of the supply-reducing command that carries it; the `reservation_events` row keeps the reason and, for a cut, the movement that forced it (DATABASE §4.7)
- a routed cut's refusal commits one audit event per affected company with the routed action code and authority class SYSTEM, read by QS-20 (SQ-23); reservations and their events are never deleted — RELEASED and CONSUMED keep their history (DATABASE §19; C-53)

**DATABASE IMPACT ASSESSMENT:**

- Schema change: NONE — uses the tables of TASK-005–TASK-009; a missing column is an executor discovery (AGENT_OPERATING_MODEL)
- Migration required: NO
- Existing data transformation: NONE
- Direct production DB access: FORBIDDEN
- Data-loss risk: NONE KNOWN

**MODULARITY / EXTENSIBILITY:**

- Module boundary affected: INV, tier 5 — implementing PRJ's `ProjectLifecycleParticipant` and its `CompletionPredicateProvider` part, calling PRJ's `DemandPosting` (ARCHITECTURE §3, §7)
- Stable core kept provider-neutral: YES
- External/vendor-specific code isolated: YES — framework facilities only (ARCHITECTURE §2)
- Replaceable adapter/contract required: NO — both contracts exist (TASK-032)
- Provider lock-in risk: NONE beyond the Owner's baseline stack
- Future extension impact: MEDIUM — every supply-reducing Task carries its cut through this service
- Speculative abstraction avoided: YES — no allocation engine, priority rule or timed hold (FS-09; BR-RSV-04)

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

- Context7: REQUIRED — PostgreSQL row locks and CHECK behaviour under concurrent updates of one guard row, partial UNIQUE indexes, at the installed version
- Graphify: OPTIONAL — once receiving and dispatch call the service
- Browser E2E: REQUIRED — J-INV-02 from PG-025 and from *Item dan pemenuhan*, and the cut block on a supply-reducing form
- Other: a two-connection harness (TASK-003); synthetic stock and supply-reducing movements posted through `InventoryPosting` until TASK-039 and TASK-042 add the commands that record them; the numeric annex's examples 7–9 (FX-01)
- Missing safe tool behavior: AUTO-INSTALL project-locally inside this Task (AICWDF §6.3) and record it in TOOLCHAIN; a paid tier or a privileged installation STOPS for the Owner (COST_POLICY)

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: refreshed before the change when Graphify is available ([TOOLCHAIN](../00-governance/TOOLCHAIN.md)); otherwise a targeted search of INV and of PRJ's participant calls
- Pre-change questions: which actions change RESERVED, which carry a cut, and where AX-13, AX-14 and AX-27 hand over to the participant
- Expected affected areas: INV's reservation service and `InventoryPosting`; PRJ's participant contract; every later supply-reducing action

**CONTEXT7:**

- Topics to verify: concurrent `UPDATE` re-evaluation against a committed row and the CHECK it then violates; partial UNIQUE indexes on a state; the framework's transaction and row-lock helpers
- Version-sensitive assumptions: none from memory

**UI / UX REQUIREMENTS:**

- Follow global design rules: YES
- Indonesian default: YES
- English support: YES
- Plain-language Indonesian: YES — the refusal with the current availability in words (ST-06), the cut block's shortfall and choices (IP-20), MSG-14 for a routed refusal
- Language switch impact: keeps unsent input
- Mobile-first: YES — a `[desk]` page; the phone shows a line's reservations read-only (J-INV-02; NV-06)
- Responsive: YES
- Adaptive: YES — the reservations as a table on the desktop (PT-05) and dense rows on the phone (PT-06); dialogs as full-height sheets on the phone (PT-16)
- Visual breathing room: REQUIRED
- Comfortable density: REQUIRED
- shadcn/ui reuse: the data table and dense rows, dialogs and sheets (PT-16), the quantity block (PT-17; DESIGN_SYSTEM §14.2), the relation marker (PT-04), status badges (PT-13), inline messages (PT-14)
- Design reference: PG-025 as INFORMATION_ARCHITECTURE §8.3 states it, the cut block as IP-20; the Golden Frontend Reference for presentation only (DIR-061 SD-02)
- Primary action: "Reservasi" on a confirmed warehouse line
- Secondary actions: "Kurangi Reservasi" and "Lepas", each with its reason (J-INV-02)
- Destructive actions: none — a release ends no fact and a new reservation may follow (BR-RSV-02)
- Redundancy constraints: one ACTIVE reservation per line, a further "Reservasi" increasing it; the confirmation and its proposed reservations separate commands (IP-19); no automatic proposal of which reservation to cut (IP-20)
- Loading state: PT-19
- Empty state: no reservation in the branch — one sentence (PT-19)
- Error state: C-04's and C-05's refusals with the current availability and remainder; MSG-14 for a routed cut; MSG-12 at the fields (IP-14)

**ROUTE IMPACT:**

- ROUTE-025 (PG-025's view); ROUTE-184 (reserve), ROUTE-185 (reduce or release); the reservation parts of ROUTE-181, ROUTE-188 and ROUTE-210 and the cuts of ROUTE-176, ROUTE-183, ROUTE-193–ROUTE-195 and ROUTE-211, built by their Tasks on this service; ROUTE-011's *Item dan pemenuhan* carrying "Reservasi" (TASK-034)

**INTERACTION CONTRACTS:**

- INT-184, INT-185; the IP-20 block inside INT-176, INT-183, INT-193–INT-195 and INT-211 (their Tasks); INT-007 and INT-008 on PG-025

**NAVIGATION CONTRACT:**

- Parent: — (*Gudang* › *Reservasi*) · PG-011 (NAV-025)
- Entry: *Gudang* › *Reservasi*; *Rincian proyek*'s *Item dan pemenuhan*; the confirmation's proposed reservations (J-PRJ-02)
- Back action: "Kembali" → the origin (NAV-025)
- Cancel: the dialog's "Kembali", the input kept (NV-02)
- Success destination: PG-025 with the reservation's answer in the row (NAV-025); in place on PG-011 when started there (NV-03)
- Error behavior: NV-04 — the refusal in place with the current availability, a retry under the same `command_id`
- State preservation: filters, sort, tab and cursor in the address (IP-18; NV-05)
- Mobile behavior: `[desk]` — the phone reads a line's reservations (J-INV-02; NV-06)

**ACCEPTANCE CRITERIA:**

1. With ON HAND 10, RESERVED 3 and UNUSABLE 2 the pooled view shows AVAILABLE 5; reserving 2 for a confirmed warehouse line gives RESERVED 5 and AVAILABLE 3 and records no stock movement (numeric annex 7; AC-22; BR-RSV-02).
2. With ON HAND 10 and UNUSABLE 2 a reservation of 10 is REJECTED with the current availability and one of 8 commits (annex 8; C-04).
3. A quotation, a draft project, an unconfirmed line, a drop-ship line and a service line are never reserved, and "Reservasi" is absent for them (BR-QUO-03; BR-RSV-01; BR-INV-11).
4. A reservation above the line's remainder under per-line conservation is REJECTED with the current remainder (L-04; C-05).
5. With stock 1, two concurrent reservations for different projects end with one COMMITTED and one REJECTED with the current availability — never both, never CONFLICT, in both orders (M-02; ST-06; architecture challenge 3a).
6. "Kurangi Reservasi" and "Lepas" require a reason and never take more than the remaining quantity at commit; a release racing a dispatch of the same reservation leaves the units consumed or released, never both (AX-02; C-05).
7. No job, timer, completion or document releases a reservation; a residual reservation of a completed or cancelled project is listed as "perlu dilepas" and is released as a residual command (PX-02; FS-09; QS-16; BR-PRJ-04).
8. A consumed reservation is never reactivated, and reserving the line again creates a new ACTIVE reservation (L-07; CM-27).
9. With RESERVED 8, a supply-reducing command that removes 2 usable units cannot commit until the actor's cuts total 2; with them, the cuts and the reduction commit together, the affected projects are flagged, and no order of cuts is proposed by the system (annex 9; BR-RSV-04; C-06; IP-20).
10. A chosen cut on a reservation of a company outside the actor's grants is REJECTED and routed with one audit event per affected company, and the block shows that reservation only with the relation marker (AZ-08; SQ-23; QS-20; PJ-04; UXI-35).
11. A re-confirmation that lowers a line's demand adjusts its reservation in the same transaction; a remaining-scope cancellation or a re-plan split releases the remainder's reservation; a project cancellation releases every active reservation — each inside PRJ's transaction, its failure rolling the whole command back (BR-QUO-02; AX-13; AX-14; AX-27).
12. Without `stock.reserve` the three controls are absent and their routes refused; a reservation of a company outside scope shows its quantity with "perusahaan lain" and nothing more (IG-01; PJ-04).
13. A reservation command writes no lot, serial, movement or cost row, leaves every count's basis version unchanged and changes no CALC-09 or CALC-11 figure (BR-RSV-02; GU-05; BR-FIN-12).
14. ROUTE-184 and ROUTE-185 replayed with one `command_id` return the recorded outcome, and with a changed payload are CONFLICT (CI-05; M-09).
15. After every scenario the reconciliation suite finds the product guard's RESERVED equal to Σ the remaining quantities of its ACTIVE reservations and each line guard's reserved quantity equal to its reservation (BR-INV-08; C-61).

**TEST REQUIREMENTS:**

- Lint: TL-01
- Typecheck: TL-02
- Unit: AVAILABLE and the reservable maximum against the numeric annex 7–9 (FX-01; TL-03)
- Feature: ROUTE-025, ROUTE-184 and ROUTE-185 with each refusal — C-04, C-05, AZ-08 — and the participant's adjustments through AX-13, AX-14 and AX-27 driven by PRJ's commands (TL-04)
- Integration: M-02 with two sessions and a barrier before LK-13, both orders, serialized and quantity products; a release racing a synthetic consumption of the same reservation; a cut with a synthetic supply-reducing movement — refused without it (C-06), committed with it —; the participant inside PRJ's transaction, its failure rolling back; the C- rows a reservation meets — C-03, C-04, C-05, C-06, C-09 — by violating writes, and C-01, C-02, C-07, C-08, C-24, C-28 and C-33 unchanged by every reservation command; the reconciliation suite (C-61) (TL-05)
- Authorization: `stock.reserve` with its prerequisites, a cut's `stock.reserve` inside another command, a routed cut across companies (TL-06)
- Route integrity: ROUTE-025, ROUTE-184 and ROUTE-185 audited (TL-07)
- Interaction: INT-184 and INT-185, each with one `command_id` replayed (TL-08; IG-05)
- Browser E2E: J-INV-02 from PG-025 and from *Item dan pemenuhan*, CJ-01's reservation step after a confirmation, the IP-20 block on a supply-reducing form once one exists; the phone's read-only view (TL-09)
- Localization/language switch: both languages on PG-025 and the cut block (TL-10)
- Visual breathing room/density: TEST_STRATEGY §9 for PG-025
- Responsive: TL-11
- Accessibility: TL-12, the cut block's entries reachable and labelled
- Production build: TL-13
- Security: SM-01 for a foreign line, project or reservation; SM-04 for W4-12 (TL-14)
- Performance: PFM-01 and PFM-02 for PG-025; PFM-20 for AX-01 (TL-15)
- Migration: N/A — no migration or import code in this Task; imported projects' reservations are re-created by these commands (WF-MIG-01)
- Other: M-03, the reservation racing another project's dispatch, run with TASK-041's dispatch; GAP-023's last-unit evidence recorded

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

- TASK-039 — when its other dependencies are DONE and its READY gate holds

**DO NOT DO:**

- add to V1 — a capability, page, route, field, integration, document type, report or paid service — or drop, defer or narrow a V1_REQUIRED requirement; a Task that would need either raises an Owner question and waits ([DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-20)
- access the VPS, production, its data or a credential, or put a secret, real data, a host detail or production configuration in the repository ([INFRASTRUCTURE](../09-operations/INFRASTRUCTURE.md) INF-01, INF-05; SECURITY SX-01, SX-04, SX-06; [PRODUCTION_DATA_SAFETY](../00-governance/PRODUCTION_DATA_SAFETY.md))
- add AI, model or tool attribution to a commit — the Owner's configured identity only ([AGENTS.md](../../AGENTS.md#git-commit-attribution); DIR-022)
- start before the Owner's execution authorization at OG-4, or mark DONE without the required tests passing and the completion evidence ([TASK_PLAN](TASK_PLAN.md#2-rules-of-the-plan); [DEFINITION_OF_DONE](../08-testing/DEFINITION_OF_DONE.md#2-the-checklist))
- weaken, skip or delete a valid test to obtain a green run, or adopt a paid tier without the Owner's COST_POLICY decision (DOD-05; [COST_POLICY](../00-governance/COST_POLICY.md))
- reserve automatically, by a priority rule, from a quotation, or for a drop-ship or service line, or hold a reservation for a time (L-02; FS-09; BR-QUO-03)
- cut a reservation without the actor's explicit choice, or let a supply reduction below the reservations commit without its cut (BR-RSV-04; C-06)
- reactivate a consumed reservation, or count a unit in RESERVED and UNUSABLE at once (L-07; BR-RSV-04)
- write a lot, serial, movement or cost from a reservation, or let PRJ write INV's reservation rows (BR-RSV-02; ARCHITECTURE §3)

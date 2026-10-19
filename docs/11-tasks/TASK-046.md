# TASK-046 — Direct shipment (drop-ship)

Status: REVIEW | Updated: 2026-10-20 | Owner: Planning

Written by WF-PC5-W's writer of bundle W2 of run PC-5 on 2026-10-19 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99 — a REVIEW draft of P11, approved, or not, with the [Task plan](TASK_PLAN.md) at OG-4. **No Task starts before the Owner's execution authorization at OG-4.** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** BLOCKED — waiting on P11Q-02

**RISK CLASS:** HIGH

**EXECUTOR:** AGENT

**OBJECTIVE:**

Record goods the supplier ships straight to the client: the drop-ship confirmation of a project-linked purchase line flagged direct-to-client — supplier, shipped quantity, serials, the supplier's shipment reference and evidence —, its actual cost attributed to the project as HPP at confirmation, the delivery recorded in the same command when the client's proof arrives with it, the reversal of a confirmation recorded in error, and the client's return to the warehouse or straight to the supplier, on PG-032 *Kirim Langsung* (WF-FUL-03; AX-08; ROUTE-205–ROUTE-207).

**WHY THIS EXISTS:**

AC-06 and AC-20 require drop-ship to record real fulfilment without fake warehouse entries: a drop-ship line never has a receipt or a dispatch, and its cost is attributed once, at confirmation (BR-INV-11; FS-05; BR-FIN-12; L-18; CAP-06). A supplier shipment is an externally identified event, so one shipment reference is confirmed once (BD-04), and a returned drop-ship unit must neither create nor lose cost (CM-14; CM-15). Completion (TASK-055) and cancellation (TASK-056) read the line's net confirmed quantity (PX-08).

**DEPENDS ON:**

- TASK-029 — Document engine — drafts, issue, revision, void, renditions and the composer
- TASK-034 — Commercial confirmation and fulfillment planning
- TASK-037 — Purchases — project and replenishment purchases
- the Owner's answer to P11Q-02 recorded and the narrow amendment it authorizes approved ([TASK_PLAN §8](TASK_PLAN.md#8-owner-questions)) — BLOCKED until then

**BLOCKS:**

- TASK-054 — Expenses, disbursements and other cash
- TASK-055 — Project completion and Force Complete
- TASK-056 — Project cancellation

**PARALLEL SAFE:** NO — the confirmation writes the purchase-line and charge-share guards that receiving, cancellation and the cost cascade post (LK-21, LK-22), the project-line guard, serial rows and direct cost attributions

**IN SCOPE:**

- the FUL records of DATABASE §4.9 in use: `dropship_confirmations` with one confirmation per (purchase, supplier shipment reference) (BD-04; C-49), `dropship_confirmation_lines` — per serial for serialized goods, state DROPSHIPPED, never IN_STOCK; a replacement line after a supplier return carrying its case and counted only against the replacement cap —, and the direct `cost_attributions` per cost component posted at confirmation (DATABASE §6.1; L-18)
- ROUTE-205 with INT-205 (`FUL ConfirmDropShip`; `dropship.confirm`; AX-08): "Konfirmasi Kirim Langsung" from the journey's *Kirim langsung* stage for a DROP-SHIP demand line with its project-linked, direct-to-client purchase line; the supplier, shipped quantity, serials (SF-SCAN's serial rules: neither in stock nor already drop-shipped; BD-18; C-02), the supplier's shipment reference and its evidence; Σ confirmed ≤ the purchase line and ≤ the demand remainder (C-29; C-30; PX-03); the actual cost — line price, non-creditable tax and allocated attributable charges — taken from the purchase line by cumulative rounding, never typed (SF-CHARGE; BR-FIN-12; annex 14), and attributed to the project once (C-25); the delivery rows in the same command when the client's proof arrived with the supplier's confirmation (L-40; AX-08); the confirmation step (IP-08; MSG-39)
- ROUTE-206 with INT-206 (`FUL ReverseDropShipConfirmation`; `fulfillment.correct`): a confirmation recorded in error (CM-27) reversed with its reason as a movement-less DROPSHIP_CONFIRMATION_REVERSAL restoration — the HPP contra-attributed, the purchase line's confirmed quantity and posted values lowered, the serial back to NEVER_RECEIVED — capped by the confirmation line's consumption guard, lowering the line's net confirmed quantity (DATABASE §5.7; C-27; C-59)
- ROUTE-207 with INT-207 (`FUL ReturnDropShipToSupplier`; `sales_return.record`; AX-10), each with its case and reason (SF-CASE; IP-09): **"Retur ke Gudang"** (CM-14) — the HPP contra at the confirmation's current net cost and a new DROPSHIP_RETURN lot carrying exactly that amount, allocated charges and non-creditable tax included, as corrected by any CM-17 (DROPSHIP_RETURN_RECEIPT; C-08); **"Retur ke Pemasok"** (CM-15) — a movement-less DROPSHIP_SUPPLIER_RETURN restoration under a PURCHASE_RETURN case of the purchase's company, the returned units' net HPP moved to SUPPLIER_RETURN, then settled like a warehouse purchase return — refunded, replaced by a replacement confirmation carrying that basis, or unrecovered through a RETURN_SETTLEMENT loss on the causal project —, the case closing only when returned = refunded + replaced + unrecovered (DATABASE §5.7, §6.3; C-46); no warehouse event for CM-15
- PG-032 with NAV-032 and ROUTE-032 (SCR-30; J-FUL-03): the line's confirmations, their cost with `cost.view`, the reversal and the returns, each by capability; receiving and dispatch never offered for the line (BR-INV-11)

**OUT OF SCOPE:**

- the purchase and its direct-to-client flag (TASK-037); the re-plan split that sources a remainder from stock (L-21; TASK-034)
- a delivery recorded after the confirmation, its correction CM-08 and its closure (ROUTE-201–ROUTE-204; TASK-045); the delivery rows written here follow the same representation
- the outcome of a CM-15 case — the declared refund or replacement, a supplier credit, the unrecovered share — through ROUTE-177 (NX-16; TASK-044), the refund's money through ROUTE-259 (TASK-054) and the case's closure through ROUTE-271 (TASK-031)
- a drop-ship return or confirmation reversal recorded in error, undone once by a DROPSHIP_ERROR_REVERSAL through ROUTE-211 (CM-37; AX-36), which the matrix maps to TASK-048
- the company-issued DOC-03 for the direct shipment that WF-FUL-03 allows: no route issues it from PG-032 and the matrix maps DOC-03 to TASK-029, TASK-041 and TASK-045 only — held for Planning; the upload routes ROUTE-228–ROUTE-231 (TASK-030)

**REFERENCES:**

- [WORKFLOWS §5.4](../03-workflows/WORKFLOWS/s05-04-05-fulfillment-documents.md#wf-ful-03--drop-ship--direct-fulfillment-desk) WF-FUL-03; [§6](../03-workflows/WORKFLOWS/s06-07-subflows-partial.md#6-reusable-subflows) SF-CHARGE, SF-RESTORE, SF-CASE, SF-LOSS, SF-SCAN; [§7](../03-workflows/WORKFLOWS/s06-07-subflows-partial.md#7-partial-processing-rules) PX-03, PX-08; [§8](../03-workflows/WORKFLOWS/s08-09-corrections-indivisible.md#8-correction-reversal-and-cancellation-matrix) CM-14, CM-15, CM-17, CM-27, CM-37; [§9](../03-workflows/WORKFLOWS/s08-09-corrections-indivisible.md#9-indivisible-business-actions) AX-08, AX-10, AX-12; [§12](../03-workflows/WORKFLOWS/s10-14-signals-decisions-traceability.md#12-planner-determined-level-1-decisions) L-18, L-21, L-38, L-40
- [BUSINESS_RULES §4](../02-domain/BUSINESS_RULES.md#inventory-inv) BR-INV-04, BR-INV-11; [Finance](../02-domain/BUSINESS_RULES.md#finance-fin) BR-FIN-12, BR-FIN-13; [§6](../02-domain/BUSINESS_RULES.md#6-cross-domain-interaction-rules) BR-XD-02, BR-XD-03; [§7](../02-domain/BUSINESS_RULES.md#7-forbidden-shortcuts) FS-05
- [V1_SCOPE](../01-product/V1_SCOPE.md#must-ship) CAP-06; [ACCEPTANCE_CRITERIA](../01-product/ACCEPTANCE_CRITERIA.md#product-acceptance-contracts) AC-06, AC-20
- [DATABASE §4.9](../04-architecture/DATABASE/s04-09-ful.md#49-ful--fulfillment); [§5.7](../04-architecture/DATABASE/s05-inventory.md#57-drop-ship-and-services); [§6.1](../04-architecture/DATABASE/s06-lot-cost-hpp.md#61-the-chain), [§6.3](../04-architecture/DATABASE/s06-lot-cost-hpp.md#63-opening-stock-drop-ship-returns-surplus-and-corrections); [§18](../04-architecture/DATABASE/s18-19-constraints-integrity.md#18-constraint-catalogue) C-02, C-08, C-25, C-27, C-29, C-30, C-46, C-49, C-59
- [CONCURRENCY_IDEMPOTENCY §7.1](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s07-statement-sequences.md#71-lock-sets-of-ax-01ax-37) and [§7.2](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s07-statement-sequences.md#72-checks-effects-and-outcomes-of-ax-01ax-37) AX-08, AX-10; [§7.3](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s07-statement-sequences.md#73-command-families-outside-ax-0137) the CM-27 rule; [§9](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s08-09-guards-stale-state.md#9-stale-state) ST-09; [§11](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s10-13-identity-failure-retry.md#11-separate-duplicates) BD-04, BD-13, BD-18
- [PERMISSIONS_MATRIX §4](../05-security/PERMISSIONS_MATRIX.md#4-capability-catalogue) `dropship.confirm`, `fulfillment.correct`, `sales_return.record`, `cost.view`; [§7.2](../05-security/PERMISSIONS_MATRIX.md#72-table-classification) rows 75, 79–82, 121; [§7.3](../05-security/PERMISSIONS_MATRIX.md#73-special-projections) PJ-06, PJ-08, PJ-16
- [ROUTE_CONTRACTS](../03-workflows/ROUTE_CONTRACTS.md#fulfilment--deliveries-direct-shipments-handovers-and-client-returns-ful-pg-030pg-034) ROUTE-205–ROUTE-207; [§2](../03-workflows/ROUTE_CONTRACTS.md#2-page-views--route-001route-065) ROUTE-032
- [INTERACTION_CONTRACTS](../03-workflows/INTERACTION_CONTRACTS.md#fulfilment--deliveries-direct-shipments-handovers-and-client-returns-ful-pg-030pg-034) INT-205–INT-207
- [NAVIGATION_CONTRACTS §2.4](../07-ux-design/NAVIGATION_CONTRACTS.md#24-fulfilment-cases-and-documents) NAV-032; [INFORMATION_ARCHITECTURE §8.4](../07-ux-design/INFORMATION_ARCHITECTURE.md#84-fulfilment-cases-and-documents) PG-032; [§8.7](../07-ux-design/INFORMATION_ARCHITECTURE.md#87-the-approved-screens-scr-01scr-60-and-their-pages) SCR-30
- [ADMIN_FLOW J-FUL-03](../07-ux-design/ADMIN_FLOW/s10-b-journeys.md#104-fulfilment); [§11](../07-ux-design/ADMIN_FLOW/s11-13-corrections-numbering-import.md#11-corrections-and-reversals) CM-14, CM-15, CM-27; [IP-03](../07-ux-design/ADMIN_FLOW/s05-command-model.md#ip-03--conflict-and-stale-state); [IP-08](../07-ux-design/ADMIN_FLOW/s05-command-model.md#ip-08--confirming-an-irreversible-command); [IP-09](../07-ux-design/ADMIN_FLOW/s05-command-model.md#ip-09--corrections-closures-and-destructive-actions)
- [TEST_STRATEGY §7](../08-testing/TEST_STRATEGY.md#7-browser-end-to-end-tests-and-critical-journeys) CJ-01's drop-ship branch; [§5](../08-testing/TEST_STRATEGY.md#5-domain-and-data-integrity) FX-03, FX-04's drop-ship reversals; [SECURITY_TEST_MATRIX](../08-testing/SECURITY_TEST_MATRIX.md#3-the-matrix) SM-01, SM-04, SM-25; [PERFORMANCE_TARGETS §3](../08-testing/PERFORMANCE_TARGETS.md#3-the-measured-targets) PFM-01, PFM-02, PFM-20
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (16): WF-FUL-03; AX-08; L-40; ROUTE-032; ROUTE-205–ROUTE-207; INT-205–INT-207; BD-04; BD-18; PG-032; SCR-30; J-FUL-03; NAV-032

**EXPECTED MODULES / FILE AREAS:**

- the FUL module's actions ConfirmDropShip, ReverseDropShipConfirmation and ReturnDropShipToSupplier with `ShowDirectShipment` and its query class (RC-02; ARCHITECTURE §3–§4); the direct attributions, the return lot's basis and the supplier-return figures through CST's `CostPosting`, the purchase line through PUR's `PurchasePosting`, the drop-ship return receipt through INV's `InventoryPosting` (ARCHITECTURE §7); `resources/js/Pages/FUL` for PG-032; `tests/Feature/FUL`, `tests/Concurrency/`, `tests/Reconciliation/`

**BUSINESS RULES:**

- BR-INV-11, FS-05 and BR-XD-02: drop-ship creates zero warehouse events — no receipt, no dispatch, no fictitious entry; only a return to the warehouse (CM-14) creates a lot, carrying the original purchase cost (BR-INV-04)
- BR-FIN-12, L-18 and BR-FIN-13: one cost attribution per unit — a drop-ship line's actual cost attributed to the project at confirmation, by component, residual-absorbing, exactly once (C-25); corrections are contra-attributions (CM-17)
- CM-14 and CM-15: a returned unit's cost is neither created nor lost — the return lot carries exactly the HPP contra; a return to the supplier is settled by refund, replacement or one unrecovered loss on the causal project (L-38), the case closing on the equation (C-46)
- CM-27 and L-32: a confirmation recorded in error is superseded by its reversal with a reason, its dependants corrected first — a wrongly recorded delivery reversed by CM-08 before the confirmation (DATABASE §5.7)
- L-40 and BR-XD-03: confirmation and delivery may be one command when the client's proof arrives together; one project may mix warehouse, drop-ship and service lines, each line's mode deciding its domains

**ARCHITECTURE RULES:**

- FUL is tier 6; it posts through PRJ, CST, PUR and INV's posting services in its own transaction and never computes a cost a lower tier owns — the cost is the purchase line's (ARCHITECTURE §3, §7; DATABASE §6.1)
- drop-ship corrections create no ledger entries except CM-14's receipt; every reversal or return is capped by the confirmation line's consumption guard (DATABASE §5.7; C-27)

**SECURITY / PERMISSION REQUIREMENTS:**

- ROUTE-032 `dropship.confirm`, the reversal's control only with `fulfillment.correct` and a return's only with `sales_return.record` (RG-06), the cost only with `cost.view`; ROUTE-205 `dropship.confirm` (ADM); ROUTE-206 `fulfillment.correct` (ADM_PLUS); ROUTE-207 `sales_return.record` (ADM_PLUS), each correction asking its reason (IP-09; MSG-40)

**COMPANY SCOPE AND FIELD PROJECTION:**

- the confirmation belongs to the project's company, which is the purchase's company (L-05; CS-03); every line cites only that purchase's lines and that project's demand (CS-04; C-32)
- rows 79–80: confirmations and lines PV(c), the purchase reference CV(c); row 75: the direct attributions CV(c) — the actual cost appears only with `cost.view` (J-FUL-03); rows 81–82 for the delivery rows of L-40; row 121 and PJ-16 for the return's case, its cost counters by family
- the supplier's evidence by its family's view capability within scope (PJ-08)

**CONCURRENCY / IDEMPOTENCY:**

- AX-08 for ROUTE-205, STATIC: LK-00, LK-01 (the confirmation's project; for a replacement line the projects its return case names), LK-03, LK-10, LK-13 (the product's policy anchor, LR-09), LK-17, LK-19, LK-21, LK-22, LK-29; first-use rows FU-04, FU-05; re-read under lock: the purchase OPEN, the line's normal or replacement cap, the demand remainder, each serial neither in stock nor drop-shipped; stale check ST-09; refusals C-29, C-30, C-02; a second confirmation of one supplier shipment reference is CONFLICT with the existing one (BD-04; MSG-04 "Sudah tercatat"); identity BD-04; retry class A (CONCURRENCY_IDEMPOTENCY §7.1–§7.2)
- ROUTE-206's reversal writes the confirmation line's consumption guard (LK-24) and the purchase line's posted values (LK-21, LK-22) — the guards §7.2 lists for a confirmation reversal under AX-10; ROUTE-206 itself names AX-08, as §7.3 does for a CM-27 supersession (reported for Planning); whichever row governs, the lock set covers every guard written, in canonical order (CONCURRENCY_IDEMPOTENCY §4.1; HO-13)
- AX-10 for ROUTE-207, STATIC: LK-00, LK-01 (the confirmation's project), LK-03, LK-10, LK-13, LK-14, LK-15, LK-17, LK-18, LK-19, LK-21, LK-22, LK-23, LK-24, LK-29; FU-01, FU-02, FU-09, FU-13 for the return lot; re-read: the case OPEN, the consumption's open quantity ≥ the quantity (C-27), the PX-08 caps; refusals C-27, C-30, L-49, AZ-08; identity BD-13 by the case; retry class A
- a confirmation racing a purchase cancellation meets it at LK-21: the confirmation wins or the cancellation wins, never both (AX-12); two partial confirmations of one line commit until the cap, then REJECTED (C-29; M-35's form); one `command_id` replayed returns the recorded outcome (CI-05)

**AUDIT:**

- every confirmation, reversal and return is a business audit event with the individual actor, business date, reason where required and links to the purchase line, the demand line, the confirmation and the case (BR-XC-01; BR-CR-01; LG-01); the reversal and the returns are ADM_PLUS and reach the Owner's review queue (AZ-10; QS-14); the reversal shows "Dikoreksi oleh" on the confirmation and "Mengoreksi" on itself (ADMIN_FLOW §11)

**DATABASE IMPACT ASSESSMENT:**

- Schema change: NONE — uses the tables of TASK-005–TASK-009; a missing column is an executor discovery (AGENT_OPERATING_MODEL)
- Migration required: NO
- Existing data transformation: NONE
- Direct production DB access: FORBIDDEN
- Data-loss risk: NONE KNOWN

**MODULARITY / EXTENSIBILITY:**

- Module boundary affected: FUL, tier 6, posting through PRJ, CST, PUR and INV (ARCHITECTURE §3)
- Stable core kept provider-neutral: YES
- External/vendor-specific code isolated: YES — framework facilities or a small interface (ARCHITECTURE §2)
- Replaceable adapter/contract required: NO
- Provider lock-in risk: NONE beyond the Owner's baseline stack
- Future extension impact: LOW
- Speculative abstraction avoided: YES — no generic workflow or rules engine (ARCHITECTURE §1)

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

- Context7: REQUIRED — PostgreSQL UNIQUE keys and conditional updates under row locks, the framework's database transactions, at the installed versions
- Graphify: OPTIONAL — once FUL's other Tasks exist
- Browser E2E: REQUIRED — J-FUL-03 at the desk and CJ-01's drop-ship branch
- Other: the two-connection harness and FX-04's drop-ship reversal fixtures (TASK-003)
- Missing safe tool behavior: AUTO-INSTALL project-locally inside this Task (AICWDF §6.3) and record it in TOOLCHAIN; a paid tier or a privileged installation STOPS for the Owner (COST_POLICY)

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: refreshed before the change when Graphify is available ([TOOLCHAIN](../00-governance/TOOLCHAIN.md)); otherwise a targeted search of FUL and the posting services it calls
- Pre-change questions: which commands post the purchase line's confirmed quantity and posted values (AX-04, AX-08, AX-12, AX-32, AX-34), and which read the line's net confirmed quantity (completion, cancellation)
- Expected affected areas: FUL's drop-ship actions; `PurchasePosting`, `CostPosting`, `DemandPosting`; PG-032

**CONTEXT7:**

- Topics to verify: UNIQUE keys answered as CONFLICT; conditional serial transitions
- Version-sensitive assumptions: none from memory

**UI / UX REQUIREMENTS:**

- Follow global design rules: YES
- Indonesian default: YES
- English support: YES
- Plain-language Indonesian: YES — "Sudah tercatat" for a shipment reference already confirmed (MSG-04); the reversal and the returns stating what is contra'd and what stays (ADMIN_FLOW §11)
- Language switch impact: keeps unsent input
- Mobile-first: YES — `[desk]`; the phone shows the state readable (J-FUL-03; NV-06)
- Responsive: YES
- Adaptive: N/A — one form layout, readable on the phone
- Visual breathing room: REQUIRED
- Comfortable density: REQUIRED
- shadcn/ui reuse: the form layout and multi-line entry (PT-09, PT-10), the confirmation (PT-26) and the reason step, dense rows for the line's confirmations (PT-06), the timeline (PT-18)
- Design reference: PG-032 as INFORMATION_ARCHITECTURE §8.4 and J-FUL-03 state it; the Golden Frontend Reference for presentation only (DIR-061 SD-02)
- Primary action: "Konfirmasi Kirim Langsung"
- Secondary actions: the confirmation's reversal; "Retur ke Gudang"; "Retur ke Pemasok"
- Destructive actions: none — no control deletes a confirmation; corrections are restorations (DATABASE §5.7)
- Redundancy constraints: one confirmation per supplier shipment reference (BD-04); receiving and dispatch never offered for the line (BR-INV-11)
- Loading state: PT-19
- Empty state: no confirmation yet for the line — one sentence (PT-19)
- Error state: above the purchase line or the demand remainder REJECTED with the remainder (MSG-02); a serial already in stock or drop-shipped refused (MSG-36); MSG-04 for a shipment already recorded

**ROUTE IMPACT:**

- ROUTE-032 (PG-032's view, GET `/proyek/{id}/kirim-langsung`)
- ROUTE-205 (POST `/proyek/{id}/kirim-langsung`), ROUTE-206 (POST `…/kirim-langsung/{dsc}/balikkan`), ROUTE-207 (POST `…/kirim-langsung/{dsc}/retur`); each answering RC-05's errors

**INTERACTION CONTRACTS:**

- INT-205–INT-207, each with its *Test* as its test (IG-05); INT-015 for ROUTE-207's likely-duplicate second submission (BD-13); INT-016 and INT-017 for CONFLICT and FAILED

**NAVIGATION CONTRACT:**

- Parent: PG-011 (NAV-032)
- Entry: the journey's *Kirim langsung* stage
- Back action: "Kembali" → PG-011
- Cancel: "Batal" → PG-011; the confirmation's and the reason step's "Kembali" → the form, the input kept (NV-02)
- Success destination: PG-011 with the confirmation recorded (NAV-032); the reversal and a return answer in place
- Error behavior: NV-04 — validation at the field, a rejected command in place, the input kept
- State preservation: unsent input kept across the re-login; MSG-35 before leaving
- Mobile behavior: `[desk]` — the state readable on the phone

**ACCEPTANCE CRITERIA:**

1. A drop-ship confirmation creates no warehouse event — no receipt, dispatch, lot or stock change — and receiving and dispatch are never offered for the line (BR-INV-11; FS-05; AC-06; AC-20).
2. The confirmation attributes the line's actual cost — price, non-creditable tax and allocated charges, by cumulative rounding — to the project as HPP exactly once; the cost is shown only with `cost.view` (BR-FIN-12; L-18; C-25; J-FUL-03).
3. Σ confirmed above the purchase line or the demand remainder is REJECTED with the remainder; a second confirmation of the same supplier shipment reference is CONFLICT "Sudah tercatat"; one `command_id` replayed confirms once (C-29; C-30; BD-04; CI-05).
4. A serialized line takes each serial once in state DROPSHIPPED, never IN_STOCK; a serial already in stock or drop-shipped is refused (BD-18; C-02).
5. When the client's proof arrives with the supplier's confirmation, one command records both, and the delivery rows obey C-30 like any delivery (L-40; AX-08).
6. A confirmation recorded in error is reversed once with its reason: the HPP contra'd, the purchase line's confirmed quantity and posted values lowered, the serial back to NEVER_RECEIVED, the line's net confirmed quantity lowered, never beyond the confirmation (CM-27; C-27; C-59).
7. "Retur ke Gudang" creates one DROPSHIP_RETURN lot whose cost equals the HPP contra at the confirmation's current net cost, so cost is neither created nor lost (CM-14; C-08; DATABASE §6.3).
8. "Retur ke Pemasok" records no warehouse event, moves the returned units' net HPP to the supplier-return class under a purchase-return case, and that case closes only when returned = refunded + replaced + unrecovered (CM-15; C-46).
9. A confirmation racing the purchase's cancellation leaves either the confirmation or the cancellation, never both (AX-12).
10. The reversal and the returns are absent without `fulfillment.correct` and `sales_return.record`, each asking its reason (AZ-01; IP-09; SM-04).

**TEST REQUIREMENTS:**

- Lint: TL-01
- Typecheck: TL-02
- Unit: the cumulative-rounding share of a partial confirmation and the return lot's basis (TL-03; annex 14; BR-FIN-13)
- Feature: ROUTE-205–ROUTE-207 with each refusal (TL-04)
- Integration: a confirmation racing the purchase's cancellation and a second partial confirmation of one line; FX-04's drop-ship reversals — a confirmation reversal, a return to the warehouse, a return to the supplier settled by refund, replacement and unrecovered share —; the reconciliation suite after each (TL-05; TEST_STRATEGY §5)
- Authorization: `dropship.confirm`, `fulfillment.correct` and `sales_return.record` by class; the cost absent without `cost.view` (TL-06; SM-04; SM-25)
- Route integrity: ROUTE-032, ROUTE-205–ROUTE-207 audited (TL-07; SM-02)
- Interaction: INT-205–INT-207 per IG-05, with one `command_id` replayed (TL-08)
- Browser E2E: J-FUL-03 and CJ-01's drop-ship branch (TL-09)
- Localization/language switch: both languages on PG-032 (TL-10)
- Visual breathing room/density: TEST_STRATEGY §9
- Responsive: TL-11
- Accessibility: TL-12
- Production build: TL-13
- Security: SM-01 for another company's purchase or project (TL-14)
- Performance: PFM-01 and PFM-02 for PG-032; PFM-20 with QB-13 for AX-08 and AX-10 (TL-15)
- Migration: N/A — no migration or import code in this Task
- Other: CM-14, CM-15 and CM-27's expected end states asserted (TEST_STRATEGY §5)

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
- create a receipt, a dispatch or any warehouse movement for a drop-ship line, except CM-14's return to the warehouse (BR-INV-11; FS-05)
- let a user type a drop-ship line's cost, or attribute it twice (L-18; C-25)
- confirm one supplier shipment reference twice, or above the purchase line or the demand remainder (BD-04; C-29)
- edit or delete a confirmation instead of reversing it with its reason (CM-27)

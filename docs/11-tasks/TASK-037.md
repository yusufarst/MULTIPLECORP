# TASK-037 — Purchases — project and replenishment purchases

Status: APPROVED | Updated: 2026-10-20 | Owner: Planning

Approval: [APPR-016](../00-governance/DECISION_LOG.md#appr-016--p11-task-plan-tasks-and-feature-coverage-matrix-approved), explicit Owner approval on 2026-10-20 (DIR-085 OF-07) of this document as committed in the first execution session's approval commit; the approved file hash and the verified conditions are recorded there. Approval changes lifecycle only, not implementation authorization.

Written by WF-PC5-W's writer of bundle W1 of run PC-5 on 2026-10-19 and 2026-10-20 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99; the Owner approved P11 with the [Task plan](TASK_PLAN.md) at OG-4 (DIR-085 OF-07). **No Task starts before the freeze record, from which the execution authorization is in force (DIR-085 OF-09).** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** BLOCKED — waiting on P11Q-04

**RISK CLASS:** HIGH

**EXECUTOR:** AGENT

**OBJECTIVE:**

Build project and replenishment purchases on PG-015 *Pembelian*, PG-016 *Detail Pembelian* and PG-017 *Buat Pembelian* (WF-PUR-01; WF-PUR-02): a purchase with its supplier, goods and non-stock lines at actual prices, their tax treatment and conversion snapshots, its charges with their source bills and allocation (AX-29), its business number at save, the link to a project's demand lines and the direct-to-client flag; its amendment before the first effect, cancellation (AX-12), remainder closure (AX-32) and cost correction (AX-34) — ROUTE-171–ROUTE-175 —; the supplier's previous price inline (SH-01); and the purchase as the subject of the optional PO, DOC-02, and of its Surat Pesanan layout.

**WHY THIS EXISTS:**

CAP-05 and AC-05 require purchases with actual supplier, quantities, prices, tax and shipping, an optional PO and several suppliers per project without duplicated demand, and a purchase alone changes neither cash nor stock (BR-PUR-01–BR-PUR-03; REF-019; REF-020; IMG-03). Its lines and charges are where acquisition cost starts — the receipt's lot cost, the drop-ship's and the non-stock line's HPP (SF-CHARGE; L-18; GAP-027) —, an unfillable remainder ends only by an explicit closure (BR-PUR-04), and a wrong price or basis is corrected through one cascade (CM-17; CM-35). Receiving, purchase returns, direct shipment, expenses, the import and the reports build on it (TASK-039, TASK-044, TASK-046, TASK-054, TASK-057, TASK-060); the architecture challenge's sequences 1a, 2a, 2b and 2c assert it.

**DEPENDS ON:**

- TASK-026 — Clients and suppliers
- TASK-027 — Catalog — products, services, units, barcodes, prices and images
- TASK-029 — Document engine — drafts, issue, revision, void, renditions and the composer
- TASK-034 — Commercial confirmation and fulfillment planning
- TASK-036 — Costing foundations — lot cost basis, charge allocation and HPP attribution
- the Owner's answers to P11Q-02 and P11Q-04 recorded, with what they call for done ([TASK_PLAN §8](TASK_PLAN.md#8-owner-questions)) — P11Q-02's done: DIR-085 OF-02 and Change 4 (DIR-100), ROUTE-178 · INT-178 and ROUTE-179 · INT-179; P11Q-04's BLOCKED until the Owner approves [OF04_FACTS_DRAFT](OF04_FACTS_DRAFT.md)

**BLOCKS:**

- TASK-039 — Receiving (Barang Masuk)
- TASK-044 — Purchase return to supplier
- TASK-046 — Direct shipment (drop-ship)
- TASK-054 — Expenses, disbursements and other cash
- TASK-057 — Opening import — preparation, dry run, sign-off and commit
- TASK-060 — Reports, the consolidated report and exports

**PARALLEL SAFE:** NO — it owns the purchase row and line guards (LK-21) that receiving, drop-ship confirmation, purchase returns and disbursements meet, and draws the PURCHASE numbers (LK-31)

**IN SCOPE:**

- the PUR records of DATABASE §4.6 in use: `purchases` with the supplier and its snapshot, the business date, the supplier's reference, the project — NULL for replenishment — and the state OPEN, CLOSED or CANCELLED; `purchase_lines` (GOODS or NONSTOCK; WAREHOUSE or DROPSHIP, a drop-ship line always GOODS and project-linked) with entered quantity, unit, ratio and base quantity, the actual unit price excluding creditable tax and the line value; `purchase_line_tax_components` with the treatment recorded for the company, transaction context, business date and evidence (DIR-024 D-1); `purchase_charges` and their versioned `purchase_charge_line_shares`; `purchase_line_balances` through `PurchasePosting`, its only normal writer (ARCHITECTURE §7); `purchase_line_closures`; the three *_CORRECTION kinds of `purchase_line_adjustments` (BR-PUR-01; BR-SN-02; BR-INV-03)
- ROUTE-171 with INT-171, "Simpan Pembelian" (NX-04 with AX-29's charges): the supplier chosen directly (J-MD-03) — "Pemasok Baru" inline through TASK-026's supplier command —; goods lines with product, quantity and entered unit, actual price and tax components, each line linked to a confirmed demand line of the project where it serves one; non-stock lines, whose cost CST attributes to the project at the save (L-18; CALC-09); each charge with its source bill — issuer, reference and total — and its classification, *Masuk biaya perolehan* with an allocation basis or *Beban* (SF-CHARGE; DIR-024 D-2; L-43); "Kirim langsung ke klien" on a drop-ship line; the save drawing the purchase's number from its company's PURCHASE sequence through TASK-021's numbering service, REJECTED with MSG-17 where the period may create no counter (`DIR-077` CA-09; NM-03; C-10); the purchase OPEN, the answer stating that no cash has left and no stock has arrived (BR-PUR-03; J-PUR-01)
- the purchase's company: for a project purchase the project's, shown and never chosen (L-05; CS-03), the project ACTIVE; for a replenishment purchase the company chosen and active, goods lines only, its non-attributable charges company expenses (WF-PUR-02; BR-ACC-03; C-54); the supplier active; several suppliers and purchases may serve one project without duplicating its demand, and buying beyond the shortage is allowed, the excess becoming free stock after receipt (BR-PUR-02; WF-PUR-01)
- PG-017 with NAV-017 and ROUTE-017 (SCR-17): opened empty, prefilled with a project's shortage by its public id from the QS-02 row, the journey's *Gudang* stage and *Item dan pemenuhan*'s "Buat Pembelian" on PG-011 (TASK-034), with a product from QS-17's restock advice (J-MD-05), or from PG-016's "Tambahkan Pembelian Susulan" (ROUTE_CONTRACTS §4); the line editor for thirty lines by keyboard (IP-13; PT-10; UXS-35), charges under "Rincian lain" (DESIGN_SYSTEM Annex A.3), totals *Perkiraan* until saved (UXI-14); "Simpan Pembelian" intentionally disabled with its reason until a supplier and each line's quantity and price are given (ROUTE_CONTRACTS §4)
- SH-01 on PG-017: the previous actual purchase price and date of the supplier and item, inline, derived only from purchases of companies in scope, per company, with `cost.view`; no scoring, ranking or automatic supplier choice (CAP-02; PJ-09) — AC-02's purchasing half with REF-011, a project purchase reading its project's client organization and unit as TASK-026 and TASK-032 record them (REF-009; REF-010)
- ROUTE-172 with INT-172, the amendment on PG-016: goods lines editable before the purchase's first effect — a receipt, a drop-ship confirmation or a disbursement — under the purchase's `lock_version`, which covers its draft lines and tax components (ST-01); afterwards REJECTED, additions taking a supplementary purchase and cost errors CM-17; non-stock lines change only through CM-17's contra and re-attribution; company and project fixed at creation (L-23; NX-04)
- ROUTE-173 with INT-173, "Batalkan Pembelian" (AX-12; `purchase.cancel`, ADM+): only with zero receipts, zero drop-ship confirmations and no performed or paid non-stock line; the header CANCELLED with its number kept and its reason, every line's guard closed to its ordered quantity so that a racing receipt fails C-29, the non-stock lines' HPP contra'd, the charges expensed and the PO version voided (DATABASE §25), in one transaction; money already paid returns as a supplier refund (CM-18; CM-30); otherwise the form shows that the remainder closure applies (ADMIN_FLOW §11)
- ROUTE-174 with INT-174, "Tutup Sisa Pembelian" (AX-32; `purchase.close_remainder`, ADM+): the unreceived remainder of one or several lines closed with its reason, one closure per line per command, the remainder's pending charge shares re-spread over the received quantity in the same action (BR-PUR-04; L-47; CM-18; PX-04); and the exact inverse posting that a CM-27 supersession of a closure runs through ROUTE-169 (TASK-035)
- ROUTE-175 with INT-175, "Koreksi Harga, Pajak atau Biaya" (AX-34; `purchase.correct_cost`, ADM+): CM-17's price, tax or charge-amount correction and CM-35's basis correction after a receipt — the `purchase_line_adjustments` row, the line guard's current and posted values or the charge's amount, and TASK-036's cascade in the same transaction —, the form listing every lot, consumption and project the cascade will re-attribute; a charge corrected above its bill needs the bill's evidence corrected first (DATABASE §6.3; ADMIN_FLOW §11)
- PG-016 with NAV-016 and ROUTE-016 (SCR-16): lines with ordered, received and remaining quantities (CALC-03; "Sisa diterima"), charges with their allocation, the purchase's receipts, disbursements with `finance.view` and its documents, each part filled as its Task arrives; "Terbitkan PO" opening PG-036 with the PO's draft prepared from the recorded facts (ROUTE-221; ROUTE-222; `DIR-081` OB-02); "Terima Barang" to PG-022 (J-INV-01) and "Balikkan Penerimaan" (ROUTE-183) once TASK-039 exists, "Retur ke Pemasok" to PG-028 (J-PUR-03; ROUTE-176) once TASK-044 exists, never a control without its route (RC-06); the ADM+ corrections absent without their capability (H7-03); the timeline (PT-18)
- PG-015 with NAV-015 and ROUTE-015 (`cost.view`; SCR-15): purchases one company at a time with the company label, number, supplier, project, state and business date, "Buat Pembelian" and "Muat Lagi" (IP-18; PF-13); and the *Pengadaan* part of PG-011 — the project's purchases with their state and remaining quantities — through PUR's query classes on TASK-032's ROUTE-011
- PUR's `DocumentSubjectProvider` for the purchase record: DOC-02 and its company-issued Surat Pesanan layout (DOC-14) — two layouts of one record, each with its own type number, the precondition that the purchase exists, the PO optional and never a precondition, a client-issued Surat Pesanan only an upload (BR-DOC-04; BR-PUR-02; L-19; L-26; WF-DOC-01)
- PUR's part of `CompletionPredicateProvider` — no open project-linked purchase remainder without closure (WF-PRJ-03 predicate 8) — through PUR's query classes, the predicate itself TASK-055's (ARCHITECTURE §3)
- **Held for the Owner's answers to P11Q-02 and P11Q-04, built here once the answers and their rows or definitions are approved, which comes before this Task is READY** ([TASK_PLAN §8](TASK_PLAN.md#8-owner-questions)): under P11Q-02 (a), once the narrow amendment adds their rows, the route and interaction that record an attributable charge on a purchase after its first effect, with its source bill, classification and allocation and the cascade AX-29 and DATABASE §6.1 state (ARCHITECTURE §3's `RecordPurchaseCharge`), and "Koreksi Dasar Alokasi", CM-35's allocation-basis correction, as its own control on PG-016 (ADMIN_FLOW §11), each as its rows state; under P11Q-04 (a), the fact that makes a non-stock line performed, with the column the amendment defines, read by AX-12's cancellation guard; under option (b) of either, a behaviour the Owner takes out of V1 is not built, as the answer records (DIR-083 OE-20)

**OUT OF SCOPE:**

- the costing services — lot cost, allocation, re-spread and the cascade's costing rows (TASK-036); receiving, its reversal and the receiving projection (TASK-039); purchase returns, their outcomes and PG-028 (TASK-044); drop-ship confirmation (TASK-046); disbursements to suppliers and supplier refunds (TASK-054); opening purchases and their legacy numbers (TASK-057); purchase and cost reports (TASK-060)
- the supplier master and its price-history page (TASK-026); the catalogue (TASK-027); the document engine, PG-036 and ROUTE-221–ROUTE-222 (TASK-029); numbering (TASK-021); ROUTE-169's supersession route (TASK-035)
- a held behaviour of P11Q-02 or P11Q-04 that the Owner's answer takes out of V1 (option (b)); and, until the answer and its rows exist, any control of a held behaviour — never built without its route (RC-06)

**REFERENCES:**

- [WORKFLOWS §5.3](../03-workflows/WORKFLOWS/s05-03-inventory-purchasing.md#wf-pur-01--project-purchase-desk) WF-PUR-01; [WF-PUR-02](../03-workflows/WORKFLOWS/s05-03-inventory-purchasing.md#wf-pur-02--replenishment-purchase-desk); [§6](../03-workflows/WORKFLOWS/s06-07-subflows-partial.md#6-reusable-subflows) SF-CHARGE; [§7](../03-workflows/WORKFLOWS/s06-07-subflows-partial.md#7-partial-processing-rules) PX-03, PX-04, PX-06; [§8](../03-workflows/WORKFLOWS/s08-09-corrections-indivisible.md#8-correction-reversal-and-cancellation-matrix) CM-17, CM-18, CM-27, CM-30, CM-35; [§9](../03-workflows/WORKFLOWS/s08-09-corrections-indivisible.md#9-indivisible-business-actions) AX-12, AX-29, AX-32, AX-34; [§12](../03-workflows/WORKFLOWS/s10-14-signals-decisions-traceability.md#12-planner-determined-level-1-decisions) L-05, L-18, L-19, L-23, L-26, L-43, L-47; [§5.5](../03-workflows/WORKFLOWS/s05-04-05-fulfillment-documents.md#wf-doc-01--generated-document-lifecycle-desk) DOC-02 and DOC-14 in WF-DOC-01's table; [WF-PRJ-03](../03-workflows/WORKFLOWS/s05-07-08-completion-migration.md#wf-prj-03--project-completion-penyelesaian-desk) predicate 8
- [BUSINESS_RULES](../02-domain/BUSINESS_RULES.md#procurement-pur) BR-PUR-01–BR-PUR-05; [BR-DOC-04](../02-domain/BUSINESS_RULES.md#smdocument-all-doc-0114-invoice-is-a-specialization); [BR-SN-02](../02-domain/BUSINESS_RULES.md#historical-immutability-and-snapshots); [BR-FIN-11, BR-FIN-17](../02-domain/BUSINESS_RULES.md#finance-fin); [§5](../02-domain/BUSINESS_RULES.md#5-calculation-semantics) CALC-03, CALC-09; [the numeric annex](../02-domain/BUSINESS_RULES.md#numeric-annex-binding-examples-for-p8-fixtures-gap-004), example 14
- [V1_SCOPE](../01-product/V1_SCOPE.md#must-ship) CAP-02, CAP-05; [SH-01](../01-product/V1_SCOPE.md#should-ship); [DOC-02, DOC-14](../01-product/V1_SCOPE.md#selectable-document-catalog); [ACCEPTANCE_CRITERIA](../01-product/ACCEPTANCE_CRITERIA.md#product-acceptance-contracts) AC-02, AC-05; [REFERENCE_COVERAGE](../01-product/REFERENCE_COVERAGE.md#pdf-capability-inventory) REF-009–REF-011, REF-019, REF-020; [IMG-03](../01-product/REFERENCE_COVERAGE.md#image-and-non-tabular-pdf-inventory)
- [DATABASE §4.6](../04-architecture/DATABASE/s04-06-pur.md#46-pur--procurement); [§6.1, §6.3](../04-architecture/DATABASE/s06-lot-cost-hpp.md#61-the-chain); [§18](../04-architecture/DATABASE/s18-19-constraints-integrity.md#18-constraint-catalogue) C-10, C-20, C-21, C-29, C-54, C-57; [§19](../04-architecture/DATABASE/s18-19-constraints-integrity.md#19-referential-integrity-and-deletearchive-policy); [§25](../04-architecture/DATABASE/s25-28-transactions-handoffs.md#25-transaction-boundary-map) AX-12, AX-29, AX-32, AX-34
- [ARCHITECTURE §3](../04-architecture/ARCHITECTURE.md#3-modules) PUR, `DocumentSubjectProvider`, `CompletionPredicateProvider`; [§7](../04-architecture/ARCHITECTURE.md#7-posting-services-and-guards) `PurchasePosting`
- [CONCURRENCY_IDEMPOTENCY §7.1](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s07-statement-sequences.md#71-lock-sets-of-ax-01ax-37) and [§7.2](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s07-statement-sequences.md#72-checks-effects-and-outcomes-of-ax-01ax-37) AX-12, AX-29, AX-32, AX-34; [§7.3](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s07-statement-sequences.md#73-command-families-outside-ax-0137) NX-04; [§5](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s04-06-locks-envelope.md#5-data-dependent-lock-set-protocol); [§9](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s08-09-guards-stale-state.md#9-stale-state) ST-01; [§10](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s10-13-identity-failure-retry.md#10-command-identity-and-replay) CI-01, CI-05; [§11](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s10-13-identity-failure-retry.md#11-separate-duplicates) BD-17; [§18](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s18-scenarios.md#18-mandatory-concurrency-and-failure-scenarios) M-07, M-09, M-15, M-36
- [PERMISSIONS_MATRIX §4](../05-security/PERMISSIONS_MATRIX.md#4-capability-catalogue) `purchase.manage`, `purchase.cancel`, `purchase.close_remainder`, `purchase.correct_cost`, `documents.issue`; [§5](../05-security/PERMISSIONS_MATRIX.md#5-workflows-4-mapping) W4-13, W4-18, W4-19, W4-23; [§6](../05-security/PERMISSIONS_MATRIX.md#6-company-scope-rules) CS-03, CS-04, CS-06–CS-08; [§7.2](../05-security/PERMISSIONS_MATRIX.md#72-table-classification) rows 39–46, 114; [§7.3](../05-security/PERMISSIONS_MATRIX.md#73-special-projections) PJ-08, PJ-09, PJ-10, PJ-15, PJ-18
- [ROUTE_CONTRACTS](../03-workflows/ROUTE_CONTRACTS.md#purchases--buat-pembelian-and-detail-pembelian-pur-pg-016-pg-017-wf-pur-01wf-pur-03) ROUTE-171–ROUTE-175; [§2](../03-workflows/ROUTE_CONTRACTS.md#2-page-views--route-001route-065) ROUTE-015–ROUTE-017; [§4](../03-workflows/ROUTE_CONTRACTS.md#4-visible-controls-without-a-route-of-their-own); [INTERACTION_CONTRACTS](../03-workflows/INTERACTION_CONTRACTS.md#purchases--buat-pembelian-and-detail-pembelian-pur-pg-016-pg-017-wf-pur-01wf-pur-03) INT-171–INT-175
- [NAVIGATION_CONTRACTS §2.3](../07-ux-design/NAVIGATION_CONTRACTS.md#23-purchasing-and-warehouse) NAV-015–NAV-017; [INFORMATION_ARCHITECTURE §8.3](../07-ux-design/INFORMATION_ARCHITECTURE.md#83-purchasing-and-warehouse) PG-015–PG-017; [§8.7](../07-ux-design/INFORMATION_ARCHITECTURE.md#87-the-approved-screens-scr-01scr-60-and-their-pages) SCR-15–SCR-17
- [ADMIN_FLOW §10.3](../07-ux-design/ADMIN_FLOW/s10-a-journeys.md#103-inventory-and-purchasing) J-PUR-01–J-PUR-03, J-INV-01; [§10.1](../07-ux-design/ADMIN_FLOW/s10-a-journeys.md#101-access-and-master-data) J-MD-03, J-MD-05; [§11](../07-ux-design/ADMIN_FLOW/s11-13-corrections-numbering-import.md#11-corrections-and-reversals) CM-17, CM-18, CM-35; [§15](../07-ux-design/ADMIN_FLOW/s14-15-messages-scenarios.md#lists-search-scanner-and-exports) UXS-35; [IP-09](../07-ux-design/ADMIN_FLOW/s05-command-model.md#ip-09--corrections-closures-and-destructive-actions); [IP-13](../07-ux-design/ADMIN_FLOW/s06-09-session-forms-lists.md#ip-13--entry); [§14](../07-ux-design/ADMIN_FLOW/s14-15-messages-scenarios.md#14-message-catalogue) MSG-17, MSG-40
- [DESIGN_SYSTEM §9.3](../07-ux-design/DESIGN_SYSTEM.md#93-forms-and-entry) PT-10, PT-25; [Annex A.3](../07-ux-design/DESIGN_SYSTEM.md#a3-sp-52s-items-on-buat-pembelian); [§14.1](../07-ux-design/DESIGN_SYSTEM.md#141-financial-data); [LOCALIZATION §6.6](../07-ux-design/LOCALIZATION.md#66-pages--every-page-of-information_architecture-8) `pages.pg_015`–`pages.pg_017`
- [TEST_STRATEGY §7](../08-testing/TEST_STRATEGY.md#7-browser-end-to-end-tests-and-critical-journeys) CJ-01, CJ-02; [§5](../08-testing/TEST_STRATEGY.md#5-domain-and-data-integrity) FX-03, FX-04; [SECURITY_TEST_MATRIX](../08-testing/SECURITY_TEST_MATRIX.md#3-the-matrix) SM-01, SM-04, SM-25; [PERFORMANCE_TARGETS §3](../08-testing/PERFORMANCE_TARGETS.md#3-the-measured-targets) PFM-01, PFM-02, PFM-20
- [GAP_REGISTER](../00-governance/GAP_REGISTER.md#gap-027--purchase-charges-needed-an-acquisition-cost-rule) GAP-027
- Architecture challenge — the sequences 1a, 2a, 2b, 2c of the [P11 gate record](evidence/P11_QUALITY_GATE.md#whole-system-architecture-challenge-sp-98), their assertions carried into this Task's acceptance criteria and tests
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (86): O-01; CAP-02; CAP-05; SH-01; DOC-02; DOC-14; AC-02; AC-05; REF-009–REF-011; REF-016; REF-019–REF-020; IMG-01; IMG-03; IMG-06; BR-DOC-04; BR-PUR-01–BR-PUR-05; CALC-03; CALC-09; WF-PUR-01–WF-PUR-02; SF-CHARGE; CM-17–CM-18; CM-27; CM-30; CM-35; AX-12; AX-29; AX-32; AX-34; L-19; L-26; L-43; L-47; ROUTE-015–ROUTE-017; ROUTE-171–ROUTE-175; ROUTE-178–ROUTE-179; INT-171–INT-175; INT-178–INT-179; C-20–C-21; C-57; NX-04; BD-17; M-07; M-09; M-15; M-36; PG-015–PG-017; SCR-15–SCR-17; J-INV-01; J-MD-03; J-MD-05; J-PUR-01–J-PUR-03; UXS-35; NAV-015–NAV-017; GAP-027; GAP-066; GAP-068

**EXPECTED MODULES / FILE AREAS:**

- the PUR module's actions RecordPurchase — its charges recorded inside it (AX-29) —, AmendPurchase, CancelPurchase, ClosePurchaseRemainder and CorrectPurchaseCost (RC-02), `PurchasePosting`, its policies, query classes and `ShowPurchases`, `ShowPurchase` and `ShowPurchaseForm`; PUR's `DocumentSubjectProvider` and its `CompletionPredicateProvider` part; `resources/js/Pages/PUR` and the line editor in `resources/js/features/pur`; `tests/Feature/PUR` and `tests/Concurrency/`

**BUSINESS RULES:**

- BR-PUR-01 and BR-PUR-02: a purchase records the actual supplier, quantities, prices and applicable tax and shipping; its company is mandatory and its project optional — without one it is a replenishment —; a PO is optional and never a precondition; several suppliers and purchases may serve one project without duplicating demand (CAP-05; AC-05; REF-019; REF-020; IMG-03)
- BR-PUR-03 with BR-FIN-11 and FS-04: creating a purchase changes neither cash nor stock — stock changes only at receiving, cash only at a disbursement; no Cash-Out arises from purchasing
- BR-PUR-04 with PX-04: an unfillable remainder ends only by an explicit, reasoned purchase-remainder closure, which completion and reporting read; PX-03 and L-23: a receipt above the remainder is refused until a supplementary purchase adds to the source; PX-06's example — 100 bought, 60 received of which 5 damaged-accepted, 40 still open
- BR-PUR-05, SF-CHARGE, L-43 and L-47 (DIR-024 D-2): each charge names its source bill, is attributable with a basis or an expense, never both, and is conserved through pending shares and their re-spread; the allocation itself is TASK-036's
- BR-FIN-17 and DIR-024 D-1: the line records the tax treatment applicable to its company, context, business date and evidence; only the non-creditable part becomes acquisition cost, through SF-CHARGE
- L-05: a project-linked purchase belongs to the project's company; cross-company money is recorded only as real transfer facts (FS-07; architecture challenge 1a)
- L-18 and CALC-09: a non-stock line's cost is attributed to its project at the purchase, once; stock lines attribute at lot consumption, drop-ship lines at confirmation (TASK-041, TASK-046)
- CALC-03: remaining to receive = the line's ordered base quantity − received, until closure, derived from the line guard and never stored as a status (L-01; BR-INV-03 base units with the line's conversion snapshot)
- BR-DOC-04 with L-19 and L-26: one purchase record, two layouts — DOC-02 and the company-issued Surat Pesanan —, each truthful about its issuer, never a second purchase; issuing a layout changes no stock, cash or obligation (BR-XD-04)
- CM-17, CM-18, CM-30 and CM-35: before any receipt a wrong line is edited; afterwards a wrong price, tax or charge, or a wrong basis, is corrected only through AX-34; a purchase with nothing received, confirmed or paid is cancelled with its PO voided, otherwise its remainder is closed; received goods stay free pool stock with their provenance and money paid returns as a supplier refund
- BR-SN-01 and BR-SN-02: the supplier identity, prices, units with their ratio and the tax components are snapshots taken at commitment, never rewritten by master changes

**ARCHITECTURE RULES:**

- PUR is tier 4 (ARCHITECTURE §3): it calls CST (tier 3) for every cost posting, PRJ (tier 2) for the demand lines it links, and DOC (tier 1) for the PO's void; it never calls INV — receiving updates the purchase-line guards through `PurchasePosting`, and PUR never posts stock
- `purchase_line_balances` change only through `PurchasePosting` — quantities and cumulative posted values alike — in one statement per command (ARCHITECTURE §7; GU-01); the purchase number is drawn only inside the saving transaction, last (NM-01)
- the PO is a layout of the purchase, issued through TASK-029's engine and template (AX-15); PUR adds its subject and precondition only (DATABASE §10)

**SECURITY / PERMISSION REQUIREMENTS:**

- ROUTE-015 and ROUTE-016 `cost.view`, disbursements on PG-016 `finance.view`; ROUTE-017 and ROUTE-171 `purchase.manage` with `cost.view`; ROUTE-172 `purchase.manage`; ROUTE-173 `purchase.cancel` (ADM_PLUS, W4-19); ROUTE-174 `purchase.close_remainder` (ADM_PLUS, W4-23); ROUTE-175 `purchase.correct_cost` (ADM_PLUS, W4-18); "Terbitkan PO" `documents.issue`, its prerequisite the cost family's view capability (PERMISSIONS_MATRIX §4; W4-13)
- every control absent without its capability, never disabled (IG-01; NV-08; H7-03), the server deciding either way (AZ-01); the inline "Pemasok Baru" only with `parties.manage` (TASK-026)
- payload references — project, demand line, product, unit, supplier, source bill, tax rule — resolved through the scope resolver, and company, number, state, posted values and cost never accepted from the payload (CS-04; DP-03; WS-02)

**COMPANY SCOPE AND FIELD PROJECTION:**

- the command's company is the purchase's — the project's for a project purchase (L-05), the chosen active company for a new replenishment (CS-03; C-54); every company-owned reference resolves inside it and an out-of-scope one fails exactly like a nonexistent one (CS-04; AZ-11); no purchase names another company's project, client or lot (architecture challenge 1a)
- rows 39–46: purchases, lines, tax components, charges, shares, line balances, closures and adjustments CV(c), the receiving projection of PJ-18 to `stock.receive` holders in scope; row 114: disbursements FV(c), their purchase details CV(c); the source bill is cost-family evidence (PJ-08); DOC-02 and DOC-14 payloads `cost.view` (PJ-15)
- the supplier's S0 identity to every account, its tax id, address and contact with `projects.view` or `cost.view` (CS-06; PJ-10); price history only from purchases of companies in scope, per company, with `cost.view`, never pooled (PJ-09); PG-015 one company at a time, the scope inside the query, and no total across companies for an Admin (CS-07; CS-08; PF-13)

**CONCURRENCY / IDEMPOTENCY:**

- ROUTE-171 and ROUTE-172: NX-04 — LK-00, LK-01, LK-03, LK-06, LK-21, LK-31 —, ROUTE-171 adding, for its charges, the classes of AX-29 it touches at their positions — the share guards at LK-22 (FU-05) and the source bill at LK-29 (CONCURRENCY_IDEMPOTENCY §7.1's notes) —; company and project fixed at creation; the number drawn at LK-31, last (NM-01; NM-08), REJECTED where the period may create no counter (C-10; NM-03); the purchase's `lock_version` covering its draft lines (ST-01; CONCURRENCY_IDEMPOTENCY §7.3)
- ROUTE-173: AX-12, DATA-DEPENDENT, LK-00, LK-01, LK-03, LK-21, LK-22, LK-30 with FU-05, holding at LK-01 the purchase's project and the projects of requirements linked to the PO it voids; re-read under the header: no receipt or confirmation on any line and no performed or paid non-stock line; conditional OPEN → CANCELLED; every line's guard closed to its ordered quantity; refusal C-29, CONFLICT when the purchase is no longer OPEN; identity the purchase; retry class A
- ROUTE-174: AX-32, DATA-DEPENDENT, LK-00, LK-01, LK-03, LK-10, LK-21, LK-22, LK-23 with FU-05; re-read: the open remainder ≥ the closed quantity, the pending shares and the charge's received quantity; identity the purchase line + intent, UNIQUE (`command_id`, `purchase_line_id`); refusals C-29, C-21; retry class B; a receipt racing the closure: one COMMITTED, the other REJECTED against the new remainder (M-36; architecture challenge 2a)
- ROUTE-175: AX-34 under §5's protocol (TASK-036), DATA-DEPENDENT with FU-05; identity the purchase line or charge + intent; refusals C-57, C-20, C-21, C-46; retry class B, a lock wait answered FAILED and safe to repeat (TX-02); M-07
- AX-29 within ROUTE-171: identity the source bill (BD-17), its evidence guard capping charges and expenses (C-20); every command carries `command_id` (CI-01), a replay returning the recorded outcome after re-authorization and a changed payload CONFLICT (CI-05; M-09); concurrent saves draw distinct consecutive PURCHASE numbers (M-15)

**AUDIT:**

- every save, amendment, cancellation, closure and correction is one business audit event in its transaction with the individual actor, before and after, the reason where required and the records affected (BR-XC-01 — prices are a critical area; BR-CR-01; LG-01); ADM_PLUS for ROUTE-173–ROUTE-175, each reaching the Owner review queue (AZ-10; QS-14)
- the decisions keep their own history: `cancelled_by`, `cancelled_at` and `cancelled_reason` on the purchase, `decided_by`, `reason`, `business_date` and `supersedes_id` on a closure, `reason` and `command_id` on an adjustment (DATABASE §4.6); the purchase's timeline shows them, cost keys projected by family (PJ-11; LG-05)

**DATABASE IMPACT ASSESSMENT:**

- Schema change: NONE — uses the tables of TASK-005–TASK-009; a missing column is an executor discovery (AGENT_OPERATING_MODEL)
- Migration required: NO
- Existing data transformation: NONE
- Direct production DB access: FORBIDDEN
- Data-loss risk: NONE KNOWN

**MODULARITY / EXTENSIBILITY:**

- Module boundary affected: PUR, tier 4 — calling CST, PRJ and DOC, implementing DOC's `DocumentSubjectProvider` and PRJ's `CompletionPredicateProvider` (ARCHITECTURE §3)
- Stable core kept provider-neutral: YES
- External/vendor-specific code isolated: YES — framework facilities only (ARCHITECTURE §2)
- Replaceable adapter/contract required: NO — both contracts exist (TASK-029, TASK-032)
- Provider lock-in risk: NONE beyond the Owner's baseline stack
- Future extension impact: LOW
- Speculative abstraction avoided: YES — no supplier comparison or ranking (SH-01), no purchase-request or approval workflow, no PO entity beside the purchase (BR-DOC-04)

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

- Context7: OPTIONAL — Inertia forms with many rows and partial reloads, and PostgreSQL row locks in the canonical order, at the installed versions
- Graphify: OPTIONAL — once receiving and drop-ship cite the purchase lines
- Browser E2E: REQUIRED — J-PUR-01 and J-PUR-02 with and without a PO and with several suppliers for one project (CJ-02), the purchase step of CJ-01, thirty lines by keyboard on the desktop (UXS-35)
- Other: synthetic receipts and consumptions posted through the guards until TASK-039 and TASK-041 exist; the numeric annex's example 14 and FX-03's and FX-04's purchase fixtures
- Missing safe tool behavior: AUTO-INSTALL project-locally inside this Task (AICWDF §6.3) and record it in TOOLCHAIN; a paid tier or a privileged installation STOPS for the Owner (COST_POLICY)

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: refreshed before the change when Graphify is available ([TOOLCHAIN](../00-governance/TOOLCHAIN.md)); otherwise a targeted search of PUR and of the calls into CST and DOC
- Pre-change questions: which commands count as a purchase's first effect, and which actions read the purchase-line guard
- Expected affected areas: PUR; CST's charge and correction services; DOC's provider registry; PG-011's *Pengadaan* part

**CONTEXT7:**

- Topics to verify: Inertia's form state for a line editor and its partial reloads; PostgreSQL conditional updates on a state column; the framework's validation of nested line arrays
- Version-sensitive assumptions: none from memory

**UI / UX REQUIREMENTS:**

- Follow global design rules: YES
- Indonesian default: YES
- English support: YES — for the pages; DOC-02's and DOC-14's papers stay Indonesian (LOCALIZATION §3.4)
- Plain-language Indonesian: YES — the saved answer stating that no cash has left and no stock has arrived (J-PUR-01); "Simpan Pembelian"'s reason `pages.pg_017.save_reason`; MSG-17 for C-10; MSG-40 for the reasons; L-23's and L-43's refusals in words
- Language switch impact: keeps unsent input; the papers unchanged
- Mobile-first: YES — `[desk]` pages readable on the phone, PG-017 with small edits (NAV-017; NV-06)
- Responsive: YES
- Adaptive: YES — the line editor as a table of form rows on the desktop (PT-10), dense rows on the phone (PT-06); dialogs as full-height sheets on the phone (PT-16)
- Visual breathing room: REQUIRED
- Comfortable density: REQUIRED
- shadcn/ui reuse: the line editor with the product picker (PT-10; PT-27), "Rincian lain" for the charges (DESIGN_SYSTEM Annex A.3), the business date control (PT-25), the data table and dense rows (PT-05; PT-06), the detail layout with its timeline (PT-12; PT-18), the confirmation with its reason (PT-26), status badges (PT-13), inline messages (PT-14)
- Design reference: PG-015–PG-017 as INFORMATION_ARCHITECTURE §8.3 states them and DESIGN_SYSTEM Annex A.3's items on *Buat Pembelian*; the Golden Frontend Reference for presentation only (DIR-061 SD-02)
- Primary action: "Simpan Pembelian" on PG-017
- Secondary actions: on PG-016 "Terbitkan PO", "Terima Barang" (TASK-039), "Tambahkan Pembelian Susulan" and the amendment before the first effect; on PG-015 "Buat Pembelian"
- Destructive actions: "Batalkan Pembelian" and "Tutup Sisa Pembelian", each with its reason and a destructive confirming button (IP-09; PT-11); "Koreksi Harga, Pajak atau Biaya" as a correcting command listing what it re-attributes (PT-26; ADMIN_FLOW §11)
- Redundancy constraints: one save per form; the purchase and its PO separate commands with their own answers (IP-19); cancellation offered only when nothing is received, confirmed or paid, the remainder closure otherwise (ADMIN_FLOW §11); nothing implying that a purchase paid money or moved stock
- Loading state: PT-19; the form's totals *Perkiraan* until the server's (UXI-14)
- Empty state: PG-015 without purchases — one sentence and "Buat Pembelian" (PT-19)
- Error state: MSG-17 for C-10; the refusals of L-23, L-43 (C-20), AX-12 and C-29 in business words; CONFLICT on a stale `lock_version` with the current state (IP-03); MSG-12 at the fields (IP-14)

**ROUTE IMPACT:**

- ROUTE-015, ROUTE-016, ROUTE-017 (the views); ROUTE-171 (save), ROUTE-172 (amend), ROUTE-173 (cancel), ROUTE-174 (close the remainder), ROUTE-175 (correct the cost); "Terbitkan PO" through ROUTE-221 and ROUTE-222 (TASK-029); ROUTE-011's *Pengadaan* part carrying PUR's props (TASK-032); the client-only controls of ROUTE_CONTRACTS §4 — "Buat Pembelian", "Terima Barang" on PG-016, "Tambahkan Pembelian Susulan" — and the intentionally disabled "Simpan Pembelian"

**INTERACTION CONTRACTS:**

- INT-171–INT-175; INT-221 and INT-222 for the PO (TASK-029); INT-004's "Buat" › *Pembelian*, INT-005's supplier and product lookups, INT-007 and INT-008 on PG-015, INT-009 for unsent input

**NAVIGATION CONTRACT:**

- Parent: PG-015 for PG-016 and PG-017; PG-017 also from PG-011 and PG-007, its origin (NAV-016; NAV-017)
- Entry: *Menu lengkap* › *Pembelian* (NAV-015); "Buat" › *Pembelian*, PG-015's "Buat Pembelian", the QS-02 row prefilled and the journey's *Gudang* stage (NAV-017); the list, work rows and *Rincian proyek* › *Pengadaan* (NAV-016)
- Back action: "Kembali" → the origin — PG-015, or the project or work row it was opened from (NAV-016; NAV-017)
- Cancel: PG-017's "Batal" → the origin, MSG-35 first with unsent input; a reason step's "Kembali" → the form, the input kept (NV-02)
- Success destination: PG-016 of the saved purchase with its number (NAV-017); the amendment, cancellation, closure and correction in place (NV-03); "Terbitkan PO" → PG-036 with the PO's draft (NAV-016; `DIR-081` OB-02)
- Error behavior: NV-04 — validation at the field, a refusal in place with the input kept, a retry under the same `command_id`; a record outside scope → PG-005 (IP-21)
- State preservation: PG-015's filters, sort, tab and cursor in the address (IP-18); PG-016's open section or tab; PG-017's unsent input kept across re-login, MSG-35 before leaving (NV-05)
- Mobile behavior: `[desk]` — PG-017 readable with small edits, PG-015 and PG-016 readable on the phone (NAV-015–NAV-017; NV-06)

**ACCEPTANCE CRITERIA:**

1. "Simpan Pembelian" commits an OPEN purchase with its business number from its company's PURCHASE sequence, its lines and its charges, and records no Cash-In, Cash-Out, stock movement, lot or reservation; the answer says that no cash has left and no stock has arrived (BR-PUR-03; AC-05; J-PUR-01).
2. A save in a period whose PURCHASE numbering may create no counter is REJECTED with MSG-17 and nothing is committed; concurrent saves of one company and period draw distinct consecutive numbers, never reused (NM-03; C-10; M-15).
3. Two purchases from different suppliers linked to the same confirmed demand line both save without duplicating the line's demand; a purchase saves and proceeds the same with or without a PO (BR-PUR-02; AC-05; CJ-02).
4. A project purchase takes the project's company, shown and not chosen; a demand line, project or source bill of another company in the payload is refused exactly like a nonexistent one, and no purchase names another company's project or client (L-05; CS-03; CS-04; architecture challenge 1a).
5. A replenishment purchase has no project and goods lines only, its company chosen and active, its non-attributable charges company expenses; a deactivated company or an archived supplier refuses a new purchase (WF-PUR-02; C-54; J-MD-03).
6. Each charge names its source bill with its total; charges and expenses citing one bill beyond that total are REJECTED; freight of 100,000 on lines A 10 × 100,000 and B 30 × 100,000 on a line-value basis records shares of 25,000 and 75,000, and an administration fee of 20,000 classified *Beban* is an expense only (L-43; C-20; BD-17; numeric annex 14; architecture challenge 2c).
7. A line's tax components record the treatment applicable to its company, context, business date and evidence, and only their non-creditable part is offered to the lot cost (DIR-024 D-1; BR-FIN-17).
8. A non-stock line's cost is attributed to its project at the save and changes afterwards only by CM-17's contra and re-attribution (L-18; CALC-09).
9. A drop-ship line requires a project and a goods line and is never offered for receiving (WF-FUL-03; BR-INV-11).
10. Before the first receipt, drop-ship confirmation or disbursement the goods lines are amended in place, and two Admins editing different lines of one purchase leave the stale one CONFLICT; after the first effect the amendment is REJECTED and "Tambahkan Pembelian Susulan" opens PG-017 prefilled (L-23; ST-01; J-PUR-01).
11. "Batalkan Pembelian" on a purchase with nothing received, confirmed or paid commits CANCELLED with its number kept, its PO voided, its non-stock HPP contra'd and its charges expensed in one transaction; with a receipt it is REJECTED and the form points to "Tutup Sisa Pembelian" (AX-12; CM-18; ADMIN_FLOW §11).
12. After a cancellation every line's guard is closed to its ordered quantity, so a receipt arriving afterwards fails C-29; a receipt and a remainder closure of one line racing each other end with one COMMITTED and the other REJECTED against the new remainder, never both beyond the ordered quantity (C-29; M-36; architecture challenge 2a).
13. "Tutup Sisa Pembelian" on a line ordered 100 with 60 received closes 40 with its reason and re-spreads the line's pending charge share over the 60 in the same transaction, so allocated + pending + expensed = the charge (AX-32; L-47; PX-06; C-21).
14. "Koreksi Harga, Pajak atau Biaya" after a receipt first lists every lot, consumption and project it will re-attribute, then commits the adjustment and its cascade in one transaction with Σ attributed = the corrected cost; before any receipt the price is simply edited (AX-34; CM-17; CM-35; C-57; architecture challenge 2b).
15. Choosing a supplier and an item on PG-017 shows the previous actual price and date from purchases of companies in scope, per company and only with `cost.view`, with no score, rank or automatic choice (SH-01; AC-02; PJ-09).
16. Without `cost.view` the purchase pages, PG-011's *Pengadaan* part and every price, tax and charge are absent; without `purchase.cancel`, `purchase.close_remainder` or `purchase.correct_cost` its control is absent and its route refused (PJ-18; IG-01; SM-04; SM-25).
17. "Terbitkan PO" opens PG-036 with the PO's draft prepared from the purchase; DOC-02 and the Surat Pesanan layout of one purchase are two layouts of one record, the company-issued one carrying its truthful issuer, and issuing either changes no stock, cash or obligation (BR-DOC-04; L-26; `DIR-081` OB-02).
18. Each of ROUTE-171–ROUTE-175 replayed with one `command_id` returns the recorded outcome, and with a changed payload is CONFLICT (CI-05; M-09).
19. "Simpan Pembelian" stays disabled with its reason beside it until a supplier and each line's quantity and price are given, and a thirty-line purchase is entered by keyboard alone on the desktop (ROUTE_CONTRACTS §4; UXS-35).
20. Once the rows of P11Q-02 (a) are approved, a charge recorded after the purchase's first effect commits through its route with its source bill, its allocation and the cascade AX-29 and DATABASE §6.1 state, and "Koreksi Dasar Alokasi" commits CM-35's correction from its own control on PG-016; once P11Q-04 (a) defines the performed fact, "Batalkan Pembelian" is refused for a purchase with a performed non-stock line, read from that fact (AX-12; TASK_PLAN §8).

**TEST REQUIREMENTS:**

- Lint: TL-01
- Typecheck: TL-02
- Unit: a line's base quantity from its entered unit and ratio (C-09); the tax treatment chosen for company, context and business date; the line value and the basis values handed to `ChargeAllocator` (TL-03)
- Feature: ROUTE-015–ROUTE-017 and ROUTE-171–ROUTE-175 with each refusal — C-10 with MSG-17, L-23 after the first effect, AX-12's checks, C-29, C-20, C-21, C-57 — and the saved answer without cash or stock; once P11Q-02 (a)'s rows and P11Q-04 (a)'s fact exist, the post-effect charge, the basis correction's control and the performed guard with their refusals (TL-04)
- Integration: AX-12, AX-32 and AX-34 against synthetic receipts and draws posted through the guards — the cancellation closing every line, a closure racing a receipt both ways (M-36), the cascade's three timings (M-07) —; two saves citing one source bill concurrently against its cap (C-20); concurrent PURCHASE numbers (M-15); `PurchasePosting` as the only writer of the line guard, checked by the reconciliation suite (TL-05)
- Authorization: `purchase.manage` with its `cost.view` prerequisite, `purchase.cancel`, `purchase.close_remainder` and `purchase.correct_cost` as separately granted ADM_PLUS capabilities, `documents.issue` for the PO, `finance.view` for the disbursements part (TL-06)
- Route integrity: ROUTE-015–ROUTE-017 and ROUTE-171–ROUTE-175 audited, with ROUTE-011's changed props and ROUTE_CONTRACTS §4's client-only and disabled controls, and the held behaviours' routes once their rows exist (TL-07)
- Interaction: INT-171–INT-175, and the held behaviours' INT- rows once they exist, each with one `command_id` replayed (TL-08; IG-05)
- Browser E2E: J-PUR-01 and J-PUR-02 — CJ-02 with and without a PO and two suppliers for one project, CJ-01's purchase step from a shortage — and INT-171's S2-J04 walk at the desktop and phone widths; UXS-35's thirty lines by keyboard (TL-09)
- Localization/language switch: both languages on PG-015–PG-017; DOC-02's paper Indonesian (TL-10)
- Visual breathing room/density: TEST_STRATEGY §9 for PG-015–PG-017
- Responsive: TL-11 at DESIGN_SYSTEM §6's band edges
- Accessibility: TL-12, the line editor's focus order and the disabled save's reason announced
- Production build: TL-13
- Security: SM-01 for a foreign project, demand line, product, source bill or purchase; SM-04 for W4-13, W4-18, W4-19 and W4-23; SM-25 for prices, taxes, charges and the PO's payload (TL-14)
- Performance: PFM-01 and PFM-02 for PG-015 and PG-016, PG-015 read one company at a time (PF-13); PFM-20 for a ten-line ROUTE-171; QB-14 for ROUTE-174 and ROUTE-175 (TL-15)
- Migration: N/A — no migration or import code in this Task; opening purchases are TASK-057's
- Other: FX-03's duplicate bills and remainder closure and FX-04's price and charge correction between partial receipts, run again end to end with TASK-039; DOC-02's layout compared with its preview (DOCUMENT_PREVIEW_PRODUCER §4) and its sample listed for the Owner's content validation (GAP-019)

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
- record cash, stock, a lot or a reservation from a purchase or its PO, or make the PO a precondition (BR-PUR-03; BR-PUR-02; FS-04)
- add a supplier comparison, score, ranking or automatic supplier choice, or pool price history across companies (SH-01; PJ-09)
- edit a line after the purchase's first effect, change a purchase's company or project, or reuse or renumber a purchase number (L-23; NX-04; C-10)
- cancel a purchase that has a receipt, a confirmation or a performed or paid non-stock line, or end a remainder other than by its explicit closure (AX-12; BR-PUR-04; PX-04)
- write the line guard outside `PurchasePosting`, or post cost outside CST (ARCHITECTURE §7)

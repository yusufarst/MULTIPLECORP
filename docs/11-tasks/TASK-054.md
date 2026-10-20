# TASK-054 — Expenses, disbursements and other cash

Status: APPROVED | Updated: 2026-10-20 | Owner: Planning

Approval: [APPR-016](../00-governance/DECISION_LOG.md#appr-016--p11-task-plan-tasks-and-feature-coverage-matrix-approved), explicit Owner approval on 2026-10-20 (DIR-085 OF-07) of this document as committed in the first execution session's approval commit; the approved file hash and the verified conditions are recorded there. Approval changes lifecycle only, not implementation authorization.

Written by WF-PC5-W's writer of bundle W2 of run PC-5 on 2026-10-19 and 2026-10-20 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99; the Owner approved P11 with the [Task plan](TASK_PLAN.md) at OG-4 (DIR-085 OF-07). **No Task starts before the freeze record, from which the execution authorization is in force (DIR-085 OF-09).** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** TODO

**RISK CLASS:** HIGH

**EXECUTOR:** AGENT

**OBJECTIVE:**

Record the company's other money facts: a manual expense — actual cost with its company, optional causal project and evidence —, the disbursement that is the only Cash-Out — against a purchase, an expense or a tax payment, or a real inter-company transfer out —, and other Cash-In — received cashback as income, supplier refunds and transfers in as non-income restitution —, each with its evidence, on PG-049 *Pengeluaran & Kas Lain* (WF-FIN-07; NX-10; ROUTE-257–ROUTE-259).

**WHY THIS EXISTS:**

O-05, CAP-11 and AC-11 require actual/direct cost to stay apart from actual Cash-Out: creating a purchase or attributing cost creates no Cash-Out, and only an evidenced disbursement does, without counting the cost twice (BR-FIN-01; BR-FIN-11; BR-PUR-03; FS-04; REF-044). A supplier refund is restitution, not income, and received cashback is income only when the money actually arrives (L-08; BR-FIN-10); tax the company pays to the state is a Cash-Out that never turns remitted output tax into cost (BR-FIN-17; L-43). Money between companies moves only as two real facts, never automatically (BR-FIN-07; FS-07). The reports and the home signals (TASK-060, TASK-058) read these facts.

**DEPENDS ON:**

- TASK-030 — Evidence and uploaded documents
- TASK-036 — Costing foundations — lot cost basis, charge allocation and HPP attribution
- TASK-037 — Purchases — project and replenishment purchases
- TASK-044 — Purchase return to supplier
- TASK-046 — Direct shipment (drop-ship)
- TASK-052 — Deduction settlements and contras

**BLOCKS:**

- TASK-055 — Project completion and Force Complete
- TASK-058 — Home, Work and Review — signals and the action queue
- TASK-060 — Reports, the consolidated report and exports

**PARALLEL SAFE:** NO — its facts write the money-fact, evidence and source-bill guards (LK-27, LK-29), a purchase payment locks the purchase header (LK-21), and a supplier refund moves a purchase-return case's counters (LK-10)

**IN SCOPE:**

- the records in use (DATABASE §4.8, §4.12, §11.5): `expenses` of kind MANUAL — company, project or NULL for company level, category, amount, business date, evidence, a source bill cited directly —, `disbursements` of kind PURCHASE_PAYMENT, EXPENSE_PAYMENT, INTERCOMPANY_TRANSFER_OUT and TAX_REMITTANCE with their reference by kind, `other_cash_receipts` with category and `income_class`, and their `money_fact_balances`
- ROUTE-257 with INT-257 (`CST RecordExpense`; `expense.record` with `cost.view`; NX-10): "Catat Beban" — the actual cost with its company, its causal project where one exists, otherwise the company, and its evidence; an expense citing a source bill directly raising that bill's cited amount, so one bill caps its charges and expenses together (L-43; C-20); never the fee category, which only the settlement posts (C-23); a charge never both acquisition cost and expense (BR-PUR-05)
- ROUTE-258 with INT-258 (`FIN RecordDisbursement`; `disbursement.record`; NX-10): "Catat Pengeluaran Kas" against a purchase — its header locked (LR-10), the purchase shown paid only through such a disbursement (J-FIN-07) —, an expense, or a tax payment with its TAX_PAYMENT_PROOF and the treatment rule applied (BR-FIN-11; BR-FIN-17); an INTERCOMPANY_TRANSFER_OUT for a real inter-bank transfer, whose receiving company cites it within its cap (XL-5); the company bank account and evidence
- ROUTE-259 with INT-259 (`FIN RecordOtherCashIn`; `cashin.record`; NX-10): "Catat Kas Masuk Lain" — CASHBACK_INCOME or OTHER_INCOME as income, actually received (BR-FIN-10); SUPPLIER_REFUND for a returned, cancelled or overpaid purchase as non-income restitution with its RETURN_REFUNDED purchase adjustment, moving the return case's refunded figure and holding the projects that case names (L-08; NX-10; DATABASE §6.1); INTERCOMPANY_TRANSFER_IN citing its transfer-out within that disbursement's cap, both companies in scope, otherwise the Owner records it (XL-5; BR-FIN-07 b); OTHER_NON_INCOME; its statement-line citation set once and corrected only with reason (DATABASE §4.12)
- the expense kinds other Tasks create shown on PG-049 with their origin — FEE_DEDUCTION and TAX_TREATMENT from settlements (TASK-052), PURCHASE_CHARGE from non-attributable charges (TASK-037) — never editable here (DATABASE §4.8)
- PG-049 with NAV-049 and ROUTE-049 (SCR-45; J-FIN-07): disbursements, expenses and other Cash-In by branch, each kind's form by capability, expenses only with `cost.view`; an inter-company transfer's citation showing "perusahaan lain" unless both companies are in scope (INFORMATION_ARCHITECTURE §7); the correction "Batalkan Catatan (Kontra)" with TASK-052's ROUTE-260 (CM-38; AX-33)

**OUT OF SCOPE:**

- the contra itself (ROUTE-260; AX-33; CM-22, CM-38) — TASK-052's command, its control on PG-049 placed with it; the two Tasks share no dependency
- refunds of customer credit (ROUTE-256; TASK-053); client payments, also a client's payment received through a transfer in (ROUTE-251; TASK-051); bank statement lines on PG-050 (ROUTE-261, ROUTE-262; TASK-051)
- the purchase-return case and its outcome (ROUTE-176, ROUTE-177; TASK-044), whose refunded figure this Task's supplier refund moves — TASK-054 does not depend on TASK-044, so the refund against a return case is exercised when both exist
- the managerial reports and cashflow (CALC-10–CALC-14; TASK-060); the tax configuration (TASK-025); the upload routes (TASK-030)

**REFERENCES:**

- [WORKFLOWS §5.6](../03-workflows/WORKFLOWS/s05-06-finance.md#wf-fin-07--expenses-disbursements-and-other-cash-desk) WF-FIN-07; [§5.3](../03-workflows/WORKFLOWS/s05-03-inventory-purchasing.md#wf-pur-03--purchase-return-to-supplier-floor) WF-PUR-03's refund; [§6](../03-workflows/WORKFLOWS/s06-07-subflows-partial.md#6-reusable-subflows) SF-CHARGE, SF-CONTRA, SF-EVIDENCE; [§8](../03-workflows/WORKFLOWS/s08-09-corrections-indivisible.md#8-correction-reversal-and-cancellation-matrix) CM-18, CM-20, CM-22, CM-30, CM-38; [§9](../03-workflows/WORKFLOWS/s08-09-corrections-indivisible.md#9-indivisible-business-actions) AX-33; [§12](../03-workflows/WORKFLOWS/s10-14-signals-decisions-traceability.md#12-planner-determined-level-1-decisions) L-08, L-18, L-43, L-45 and the coverage line's other L- rows
- [BUSINESS_RULES §4](../02-domain/BUSINESS_RULES.md#finance-fin) BR-FIN-01–BR-FIN-17; [Procurement](../02-domain/BUSINESS_RULES.md#procurement-pur) BR-PUR-03, BR-PUR-05; [§5](../02-domain/BUSINESS_RULES.md#5-calculation-semantics) CALC-05–CALC-11, CALC-13, CALC-14; [§7](../02-domain/BUSINESS_RULES.md#7-forbidden-shortcuts) FS-04, FS-07, FS-13
- [V1_SCOPE](../01-product/V1_SCOPE.md#financial-concepts-and-billing) the financial concepts; [CAP-11](../01-product/V1_SCOPE.md#must-ship); [ACCEPTANCE_CRITERIA](../01-product/ACCEPTANCE_CRITERIA.md#product-acceptance-contracts) AC-11; [PRODUCT_OVERVIEW](../01-product/PRODUCT_OVERVIEW.md#problems-and-operational-outcomes) O-05; [REFERENCE_COVERAGE](../01-product/REFERENCE_COVERAGE.md#pdf-capability-inventory) REF-016, REF-044, REF-045
- [DATABASE §4.8](../04-architecture/DATABASE/s04-08-cst.md#48-cst--costing--expenses) `expenses`; [§4.12](../04-architecture/DATABASE/s04-12-fin.md#412-fin--finance) `disbursements`, `other_cash_receipts`, `money_fact_balances`; [§11.1](../04-architecture/DATABASE/s11-12-finance-tax.md#111-the-five-concepts-never-share-a-row), [§11.5](../04-architecture/DATABASE/s11-12-finance-tax.md#115-expenses-disbursements-and-other-cash); [§12](../04-architecture/DATABASE/s11-12-finance-tax.md#12-tax-architecture); [§6.1](../04-architecture/DATABASE/s06-lot-cost-hpp.md#61-the-chain) the supplier refund; [§18](../04-architecture/DATABASE/s18-19-constraints-integrity.md#18-constraint-catalogue) C-18, C-20, C-23, C-45, C-46
- [ARCHITECTURE §3](../04-architecture/ARCHITECTURE.md#3-modules) CST and FIN; [§7](../04-architecture/ARCHITECTURE.md#7-posting-services-and-guards) `CostPosting`, `MoneyPosting`, `PurchasePosting`, `EvidencePosting`
- [CONCURRENCY_IDEMPOTENCY §7.3](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s07-statement-sequences.md#73-command-families-outside-ax-0137) NX-10; [§4.1](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s04-06-locks-envelope.md#41-lock-rules) LR-10; [§11](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s10-13-identity-failure-retry.md#11-separate-duplicates) BD-14, BD-17
- [PERMISSIONS_MATRIX §4](../05-security/PERMISSIONS_MATRIX.md#4-capability-catalogue) `expense.record`, `disbursement.record`, `cashin.record`, `finance.contra`; [§7.2](../05-security/PERMISSIONS_MATRIX.md#72-table-classification) rows 78, 114, 115, 117; [§7.3](../05-security/PERMISSIONS_MATRIX.md#73-special-projections) PJ-08; [§8](../05-security/PERMISSIONS_MATRIX.md#8-cross-company-link-authorization) XL-5
- [ROUTE_CONTRACTS](../03-workflows/ROUTE_CONTRACTS.md#finance--invoices-payments-deductions-credit-other-cash-and-bank-statements-fin-pg-041pg-050) ROUTE-257–ROUTE-259, ROUTE-260; [§2](../03-workflows/ROUTE_CONTRACTS.md#2-page-views--route-001route-065) ROUTE-049
- [INTERACTION_CONTRACTS](../03-workflows/INTERACTION_CONTRACTS.md#finance--invoices-payments-deductions-credit-other-cash-and-bank-statements-fin-pg-041pg-050) INT-257–INT-259, INT-260
- [NAVIGATION_CONTRACTS §2.5](../07-ux-design/NAVIGATION_CONTRACTS.md#25-finance) NAV-049; [INFORMATION_ARCHITECTURE §8.5](../07-ux-design/INFORMATION_ARCHITECTURE.md#85-finance) PG-049; [§7](../07-ux-design/INFORMATION_ARCHITECTURE.md#7-company-context-model) the transfer citation's marker; [§8.7](../07-ux-design/INFORMATION_ARCHITECTURE.md#87-the-approved-screens-scr-01scr-60-and-their-pages) SCR-45
- [ADMIN_FLOW J-FIN-07](../07-ux-design/ADMIN_FLOW/s10-b-journeys.md#106-finance); [§11](../07-ux-design/ADMIN_FLOW/s11-13-corrections-numbering-import.md#11-corrections-and-reversals) CM-20, CM-38; [IP-16](../07-ux-design/ADMIN_FLOW/s06-09-session-forms-lists.md#ip-16--evidence-upload); [IP-09](../07-ux-design/ADMIN_FLOW/s05-command-model.md#ip-09--corrections-closures-and-destructive-actions)
- [TEST_STRATEGY §5](../08-testing/TEST_STRATEGY.md#5-domain-and-data-integrity) FX-03's duplicate bills, FX-04's cross-company bank accounts; [§7](../08-testing/TEST_STRATEGY.md#7-browser-end-to-end-tests-and-critical-journeys) CJ-14; [SECURITY_TEST_MATRIX](../08-testing/SECURITY_TEST_MATRIX.md#3-the-matrix) SM-01, SM-04, SM-25; [PERFORMANCE_TARGETS §3](../08-testing/PERFORMANCE_TARGETS.md#3-the-measured-targets) PFM-01, PFM-02, PFM-20
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (86): O-05; CAP-11; AC-11; OS-02; REF-016; REF-044–REF-045; IMG-07; OWN-01; OWN-05; BR-FIN-01–BR-FIN-17; CALC-05–CALC-08; CALC-11; FS-13; WF-FIN-07; CM-18; CM-20; CM-22; CM-30; CM-38; AX-33; L-05–L-08; L-12; L-16–L-18; L-22; L-26; L-28–L-31; L-43; L-45–L-47; L-50; ROUTE-049; ROUTE-257–ROUTE-259; INT-257–INT-259; C-13–C-15; C-18; C-23; C-25–C-26; C-41–C-42; C-47; C-52; C-57; NX-10; BD-02; BD-14; PG-049; SCR-45; J-FIN-07; NAV-049; GAP-067

**EXPECTED MODULES / FILE AREAS:**

- the CST action RecordExpense and the FIN actions RecordDisbursement and RecordOtherCashIn with `ShowOtherCash` and its query class (RC-02; ARCHITECTURE §3–§4); CST's `CostPosting` for the expense, FIN's `MoneyPosting` for the money-fact guards, PUR's `PurchasePosting` for a supplier refund's RETURN_REFUNDED adjustment, DOC's `EvidencePosting` for citations (ARCHITECTURE §7); `resources/js/Pages/FIN` for PG-049; `tests/Feature/CST`, `tests/Feature/FIN`, `tests/Concurrency/`

**BUSINESS RULES:**

- BR-FIN-01, BR-FIN-11 and FS-04: Cash-Out exists only as a disbursement with its reference and evidence; purchase creation and cost attribution never create one (BR-PUR-03; AC-11; O-05); the five concepts never share a row (DATABASE §11.1)
- CALC-09, CALC-11 and L-18: a project-attributed expense is part of that project's direct cost exactly once; an unattributed one is a company expense; system-created expenses — fees, non-attributable charges, configured tax treatments — come only from their postings; a charge is allocated or expensed, never both (BR-PUR-05; C-21)
- L-43 with C-20: a source bill's total caps the charges and expenses citing it, so one bill is never counted twice or recorded both as a charge and as an expense; acquisition-cost tax enters cost only through SF-CHARGE; a tax remittance is Cash-Out referencing its tax payment evidence and remitted output tax never becomes cost (BR-FIN-17; CALC-11)
- L-08 and BR-FIN-10: supplier refunds for returned, cancelled or overpaid purchases are non-income Cash-In (CM-18; CM-30); actually received cashback is categorized income; no cashback formula exists (FS-13)
- BR-FIN-07, FS-07 and XL-5: a real inter-company transfer is Cash-Out in one company and a Cash-In in the other citing it, capped by the transfer-out's net amount, never automatic; money received into the wrong company follows CM-20 (TASK-051, TASK-053)
- L-45 and C-18: each fact's evidence carries its identity and caps what cites it; CM-38 and AX-33: a wrong expense, disbursement or other Cash-In is corrected only by its contra (TASK-052), both visible, its dependent attributions with it
- the rest of the coverage line holds as invariants these commands must not break, each built by its own Task: BR-FIN-02–BR-FIN-06, BR-FIN-08, BR-FIN-09, BR-FIN-12–BR-FIN-16, CALC-05–CALC-08, C-13–C-15, C-23, C-25, C-26, C-41, C-42, C-52, C-57, BD-02 and BD-14 (invoices, billing, payments, settlements, cost, tax and evidence — TASK-049–TASK-053, TASK-036, TASK-025, TASK-030); L-05–L-07, L-12, L-16, L-17, L-22, L-26, L-28–L-31, L-46, L-47 (the family's other rules); L-50 and C-47 (opening facts — TASK-057); REF-016 (configurable components, no hard-coded formula); REF-045 (profit and margin in the reports — TASK-060); CM-22 (a fee's expense contra'd with its settlement — TASK-052)

**ARCHITECTURE RULES:**

- expenses belong to CST (tier 3), placed there so that Procurement and Finance stay acyclic; disbursements and other Cash-In to FIN (tier 6) (ARCHITECTURE §3)
- `money_fact_balances` caps contras at the original and a transfer-out's citations at its net amount by CHECK (DATABASE §4.12; C-45); one normal writer per guard (ARCHITECTURE §7)

**SECURITY / PERMISSION REQUIREMENTS:**

- ROUTE-049 `finance.view`, its expenses `cost.view`, and for the expenses alone `expense.record` with `cost.view` (RG-06); ROUTE-257 `expense.record` with `cost.view`; ROUTE-258 `disbursement.record`; ROUTE-259 `cashin.record` — each ADM, its form present only with its capability (AZ-01); the contra `finance.contra` (ADM_PLUS; TASK-052)
- each fact's evidence by its family's view capability: SUPPLIER_BILL the cost family, TAX_PAYMENT_PROOF and TRANSFER_PROOF the finance family (PJ-08)

**COMPANY SCOPE AND FIELD PROJECTION:**

- each fact's company is chosen on its form for a new root, in scope and active (CS-03); a project attribution names a project of that company; a purchase reference is that company's purchase (CS-04; C-32); a transfer-in cites another company's transfer-out only with both companies in scope, each side seeing its own fact and the other's identity masked (XL-5; INFORMATION_ARCHITECTURE §7)
- row 78: expenses CV(c); row 114: disbursements FV(c), a linked purchase or expense's details CV(c); row 115: other Cash-In FV(c), its transfer citation per XL-5; row 117: money-fact balances FV(c)
- PG-049 by branch, the scope filter inside the query, no total across companies for an Admin (CS-07; CS-08)

**CONCURRENCY / IDEMPOTENCY:**

- NX-10 for ROUTE-257–ROUTE-259 (LK-00, LK-01, LK-03, LK-10, LK-21, LK-27, LK-29): a payment for a purchase locks its header (LR-10); a supplier refund moves the counters of its return case and holds the projects that case names at LK-01; a transfer-in cites its transfer-out within the cap at LK-27; an expense citing a source bill moves that bill's evidence guard at LK-29 (C-20; BD-17's anchor); each guard row born in the action is no lock (CONCURRENCY_IDEMPOTENCY §7.1 notes)
- two transfers-in citing one transfer-out beyond its net amount leave the second REJECTED; an expense and a purchase charge citing one source bill beyond its total leave the second REJECTED (C-20); a disbursement against a purchase racing its cancellation meets it at LK-21; one `command_id` replayed returns the recorded outcome, re-authorized (CI-05)

**AUDIT:**

- every expense, disbursement and other Cash-In is a business audit event with the individual actor, business date, evidence references, before/after and links to the purchase, expense, tax evidence, return case or transfer it cites (BR-XC-01; LG-01); a cross-company transfer's event shows each side only its own keys (PJ-11)

**DATABASE IMPACT ASSESSMENT:**

- Schema change: NONE — uses the tables of TASK-005–TASK-009; a missing column is an executor discovery (AGENT_OPERATING_MODEL)
- Migration required: NO
- Existing data transformation: NONE
- Direct production DB access: FORBIDDEN
- Data-loss risk: NONE KNOWN

**MODULARITY / EXTENSIBILITY:**

- Module boundary affected: CST (tier 3) for expenses, FIN (tier 6) for disbursements and other Cash-In (ARCHITECTURE §3)
- Stable core kept provider-neutral: YES
- External/vendor-specific code isolated: YES — no bank or tax integration exists in V1 (API_AND_INTEGRATIONS §3)
- Replaceable adapter/contract required: NO
- Provider lock-in risk: NONE beyond the Owner's baseline stack
- Future extension impact: LOW
- Speculative abstraction avoided: YES — no general ledger, journal or tax algorithm (FS-13; ARCHITECTURE §1)

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

- Context7: REQUIRED — PostgreSQL CHECKs and composite foreign keys over exclusive arcs, an arbitrary-precision decimal library (DEP-07), at the installed versions
- Graphify: OPTIONAL — once the finance Tasks exist
- Browser E2E: REQUIRED — J-FIN-07 at the desk
- Other: the synthetic group FX-02 with two companies for the transfer, FX-03's duplicate bills (TASK-003)
- Missing safe tool behavior: AUTO-INSTALL project-locally inside this Task (AICWDF §6.3) and record it in TOOLCHAIN; a paid tier or a privileged installation STOPS for the Owner (COST_POLICY)

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: refreshed before the change when Graphify is available ([TOOLCHAIN](../00-governance/TOOLCHAIN.md)); otherwise a targeted search of CST's expenses, FIN's cash facts and the evidence guards
- Pre-change questions: which commands cite a source bill (AX-29, NX-10), which move a return case's refunded figure, and which reports read Cash-Out and expenses (CALC-09–CALC-14)
- Expected affected areas: CST's expense action; FIN's disbursement and other Cash-In actions; `CostPosting`, `MoneyPosting`, `PurchasePosting`, `EvidencePosting`; PG-049

**CONTEXT7:**

- Topics to verify: exclusive-arc CHECKs with composite foreign keys; decimal strings through the framework's casts
- Version-sensitive assumptions: none from memory

**UI / UX REQUIREMENTS:**

- Follow global design rules: YES
- Indonesian default: YES
- English support: YES
- Plain-language Indonesian: YES — each form naming what it records and that it moves cash or not; a supplier refund labelled restitution, not income; an inter-company transfer stated as two facts, one in each company (J-FIN-07)
- Language switch impact: keeps unsent input
- Mobile-first: YES — `[desk]`, readable on the phone (NV-06)
- Responsive: YES
- Adaptive: N/A — one list with its forms, readable on the phone
- Visual breathing room: REQUIRED
- Comfortable density: REQUIRED
- shadcn/ui reuse: data table and dense rows (PT-05, PT-06), the form layout (PT-09), the evidence upload (IP-16), the business-date control (PT-25), the company label and relation marker (PT-04), the timeline (PT-18)
- Design reference: PG-049 as INFORMATION_ARCHITECTURE §8.5 and J-FIN-07 state it; the Golden Frontend Reference for presentation only (DIR-061 SD-02)
- Primary action: the kind's record — "Catat Pengeluaran Kas", "Catat Beban" or "Catat Kas Masuk Lain" — by capability
- Secondary actions: "Batalkan Catatan (Kontra)" (TASK-052's route)
- Destructive actions: none — a wrong fact is corrected by its contra (CM-38)
- Redundancy constraints: one fact per real movement; no manual fee expense; a purchase never shown paid without a disbursement
- Loading state: PT-19
- Empty state: no record of the kind yet — one sentence (PT-19)
- Error state: a bill or transfer-out cap exceeded REJECTED with the figure (MSG-02); a reference of another company refused like an unknown one (CS-04; MSG-09)

**ROUTE IMPACT:**

- ROUTE-049 (PG-049's view, GET `/keuangan/kas-lain`)
- ROUTE-257 (POST `/keuangan/kas-lain/beban`), ROUTE-258 (`…/kas-lain/pengeluaran`), ROUTE-259 (`…/kas-lain/kas-masuk`); ROUTE-260 (TASK-052) from PG-049's correction; each answering RC-05's errors; each evidence's file by ROUTE-228 first

**INTERACTION CONTRACTS:**

- INT-257–INT-259, each with its *Test* as its test (IG-05); INT-260 on PG-049; INT-013 for the evidence; INT-016 and INT-017 for CONFLICT and FAILED

**NAVIGATION CONTRACT:**

- Parent: *Keuangan* › *Lainnya* (NAV-049)
- Entry: *Keuangan* › *Lainnya*
- Back action: "Kembali" → PG-041, *Keuangan*'s first page
- Cancel: "Batal" in a form, MSG-35 first with unsent input
- Success destination: PG-049 with the record (NAV-049)
- Error behavior: NV-04 — validation at the field, a rejected command in place, the input kept
- State preservation: filters, sort, tab and cursor in the address (IP-18; NV-05)
- Mobile behavior: `[desk]` — readable on the phone

**ACCEPTANCE CRITERIA:**

1. Creating a purchase or attributing cost creates no Cash-Out; a disbursement against the purchase creates Cash-Out once, and the purchase shows paid only through it (BR-FIN-11; FS-04; AC-11; J-FIN-07).
2. A project-attributed expense enters that project's direct cost once and a company-level one the company's; a manual expense of the fee category is refused (CALC-09; CALC-11; C-23).
3. An expense and a purchase charge citing one source bill together never exceed the bill's total; the second beyond it is REJECTED (L-43; C-20).
4. A supplier refund for a returned purchase is non-income Cash-In with its RETURN_REFUNDED adjustment and raises the return case's refunded figure; received cashback is income; neither is computed by formula (L-08; BR-FIN-10; FS-13; DATABASE §6.1).
5. A tax remittance is a disbursement citing its tax payment evidence and treatment rule, and remitted output tax never appears as cost (BR-FIN-11; BR-FIN-17; L-43).
6. An inter-company transfer is two facts — Cash-Out in A and a Cash-In in B citing it —, the Cash-In capped by the transfer-out's net amount, recorded only with both companies in scope, otherwise by the Owner; nothing is created automatically (BR-FIN-07; XL-5; FS-07).
7. Each form exists only with its capability; expenses are visible only with `cost.view`, cash facts with `finance.view` (AZ-01; RG-06; SM-04; SM-25).
8. One `command_id` replayed records once; a wrong fact is corrected only by its contra, both visible (CI-05; CM-38; BR-CR-02).

**TEST REQUIREMENTS:**

- Lint: TL-01
- Typecheck: TL-02
- Unit: the income class by category; the source-bill cap across charges and expenses (TL-03)
- Feature: ROUTE-257–ROUTE-259 with each refusal their contracts name (TL-04)
- Integration: two transfers-in citing one transfer-out; an expense and a charge citing one bill (FX-03's duplicate bills); a supplier refund moving a return case's figure, on a synthetic case until TASK-044 exists; the reconciliation suite after each (TL-05; TEST_STRATEGY §5)
- Authorization: `expense.record` with `cost.view`, `disbursement.record`, `cashin.record`; a transfer citation without both companies in scope refused (TL-06; SM-04)
- Route integrity: ROUTE-049, ROUTE-257–ROUTE-259 audited (TL-07; SM-02)
- Interaction: INT-257–INT-259 per IG-05, with one `command_id` replayed (TL-08)
- Browser E2E: J-FIN-07 (TL-09)
- Localization/language switch: both languages on PG-049 (TL-10)
- Visual breathing room/density: TEST_STRATEGY §9
- Responsive: TL-11
- Accessibility: TL-12
- Production build: TL-13
- Security: SM-01 for another company's purchase, expense or transfer; SM-25 for expenses without `cost.view` (TL-14)
- Performance: PFM-01 and PFM-02 for PG-049; PFM-20 with QB-13 for NX-10 (TL-15)
- Migration: N/A — no migration or import code in this Task
- Other: CM-38's contra of each fact kind asserted with TASK-052's route where it exists (TEST_STRATEGY §5)

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
- create Cash-Out from a purchase or a cost attribution, or Cash-In from anything but money actually received (BR-FIN-11; FS-04)
- record a fee expense manually, or count a charge as both acquisition cost and expense (C-23; BR-PUR-05)
- turn remitted output tax into cost, or count a supplier refund as income (BR-FIN-17; L-08)
- create an inter-company transfer, journal or cash movement automatically (FS-07; BR-FIN-07)
- edit or delete a recorded fact instead of its contra (BR-CR-02)

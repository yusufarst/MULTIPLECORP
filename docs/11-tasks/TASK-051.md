# TASK-051 — Payments, applications and bank statement lines

Status: REVIEW | Updated: 2026-10-20 | Owner: Planning

Written by WF-PC5-W's writer of bundle W2 of run PC-5 on 2026-10-19 and 2026-10-20 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99 — a REVIEW draft of P11, approved, or not, with the [Task plan](TASK_PLAN.md) at OG-4. **No Task starts before the Owner's execution authorization at OG-4.** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** TODO

**RISK CLASS:** HIGH

**EXECUTOR:** AGENT

**OBJECTIVE:**

Record money actually received and put it where it belongs: the payment fact with its date, amount, method, company bank account, the bank statement line it cites, reference, proof, paying client and remittance advice, the likely-duplicate warning, its same-command applications to issued invoices of the same client and company with the remainder left as customer credit, later applications and audited reallocations, the payment's contra with the forced disposition of everything it backs, the bank statement lines with their void, citation correction and the claimed deduction's supersession, and the receipt-mode Kuitansi with its Lampiran, on PG-045 *Pembayaran*, PG-046 *Catat Pembayaran* and PG-050 *Mutasi Bank* (WF-FIN-03; SF-APPLY; AX-18–AX-20; NX-08; ROUTE-251–ROUTE-254, ROUTE-261, ROUTE-262).

**WHY THIS EXISTS:**

CAP-10, CAP-11 and AC-10 require actual payments to create Cash-In and reduce the receivable, partial, DP, termin and overpayment kept traceable, excess explicit as credit, never profit, and every reallocation audited (OWN-01; O-05; REF-041–REF-045). One payment applied twice at once, to another client's or company's invoice, or one bank line recorded twice must never inflate the money (architecture challenges 6a–6c; GAP-004; GAP-008), and a correction of a payment must dispose of everything it backs without freeing capacity that real money does not support (L-46; DATABASE §11.3's Rp1 example; challenge 5b). Settlements, credit and refunds (TASK-052, TASK-053) and the reports (TASK-060) build on these facts.

**DEPENDS ON:**

- TASK-030 — Evidence and uploaded documents
- TASK-050 — Billing, receivables, dispute hold and Owner write-off

**BLOCKS:**

- TASK-052 — Deduction settlements and contras
- TASK-053 — Customer credit and refunds
- TASK-060 — Reports, the consolidated report and exports

**PARALLEL SAFE:** NO — payments and applications write the application-source, invoice, money-fact and evidence guards (LK-25–LK-27, LK-29) that invoices, settlements, refunds, write-offs and other cash post

**IN SCOPE:**

- the FIN and DOC records in use (DATABASE §4.10, §4.12, §11.3): `payments` with `recovery_for_invoice_id` and the transfer citation, `payment_claimed_deductions`, `application_source_balances`, `payment_applications` with their typed exits, `financial_contras`, `bank_statement_lines` with their `evidence_balances` and BANK_STATEMENT evidence
- ROUTE-251 with INT-251 (`FIN RecordPayment`; `payment.record` with `finance.view`; AX-18): "Catat Pembayaran" on PG-046 from "Buat" › *Pembayaran*, the journey's *Pembayaran* stage, *Piutang* or PG-043 — company, client, business date, amount > 0, method, the company's own bank account (required unless cash), the statement line it matches, reference, proof (IP-16), a treasurer or platform recorded as intermediary metadata, and the remittance advice's claimed gross and deductions where the client paid net —; the same-command applications, amounts per issued invoice of that client and company, billed or unbilled, never drafts, each ≤ its outstanding, Σ ≤ the payment, the rest left as *Kredit Pelanggan* (SF-APPLY; BR-FIN-03; BR-FIN-05; PX-03); MSG-39 first (IP-08); success PG-045 with the payment first (NAV-046; S2-J06)
- the duplicate and citation rules of ROUTE-251: the likely-duplicate warning — the same company bank account and amount within seven days either side, whatever the reference — continued only with `warnings.override` and its reason naming the matches (BD-02; L-22; IP-05; UXS-16); Σ Cash-In citing one statement line, net of contras, ≤ the line, one identity per real bank line (BD-03; C-19); a multi-client line as one payment fact per client sharing the line (L-22); a payment without proof allowed only with its reason, flagged *Tanpa Bukti*, failing the audit predicate and its credit not refundable — a company-issued document never proof of receipt (L-29); money received for a written-off invoice carrying `recovery_for_invoice_id` and listed for the Owner (WF-FIN-05; QS-12); a client payment citing another company's INTERCOMPANY_TRANSFER_OUT within its cap, both companies in scope, otherwise the Owner records it (XL-5; BR-FIN-07 b; DATABASE §11.5)
- ROUTE-252 with INT-252 (`FIN ApplyPayment`; `payment.apply`; AX-19): "Terapkan" on PG-045, the same route serving PG-048's "Terapkan ke Invoice" that TASK-053 places — unapplied money or opening credit applied under the same laws; an application that resolves a refund shortfall (L-46; DATABASE §11.3)
- ROUTE-253 with INT-253 (`FIN ReallocateApplication`; `payment.reallocate`; AX-19): "Pindahkan Penerapan" (CM-21) with its reason — the old application SUPERSEDED, a new one ACTIVE, the payment total unchanged (annex 4); an application consumed by a refund offering no move, the row saying why (L-46); the settlements anchored to the old (payment, invoice) moved with it or kept with reason in the same action (L-28); a Kuitansi whose linked total falls unlinked (AX-31)
- ROUTE-254 with INT-254 (`FIN ContraPayment`; `finance.contra`; AX-20): "Batalkan Catatan Pembayaran (Kontra)" (CM-19) — the form listing every application and settlement and asking what happens to each (ST-07): invoice applications first, released to credit or moved to the re-recorded payment; a refund-consumed application moved only to the re-recorded payment of the same client and company, any refund left unbacked becoming an explicit residual on the PAYMENT_CORRECTION case; settlements moved to the re-recorded payment or turned RESIDUAL; Σ contras ≤ the payment (C-45; C-44); the correct payment recorded afterwards as a separate command, the case open until it is (SF-CONTRA; SF-CASE)
- ROUTE-261 with INT-261 (`DOC RecordBankStatementLine`; `bank_statement.record` with `finance.view` and `evidence.upload`; NX-08): "Catat Mutasi Bank" on PG-050 with its BANK_STATEMENT evidence — account, date, amount, line reference as its identity (L-45) —; a payment recorded before its line flagged until cited, its citation set when the line arrives (DATABASE §11.3; QS-12)
- ROUTE-262 with INT-262 (`DOC VoidBankStatementLine · FIN CorrectBankCitation · SupersedeClaimedDeduction`; `cash.correct`; NX-08), each with its reason: a mistyped line voided only while uncited (C-19); a wrong citation corrected by a reasoned change that moves both lines' guards, never by a contra of the payment; a wrong remittance-advice figure superseded by a new claimed-deduction row, never below what is already settled against it (DATABASE §11.4)
- the receipt-mode DOC-05 *Kuitansi* for a recorded payment and its DOC-07 *Lampiran Kuitansi*, issued from PG-036 through TASK-029's routes, their record-bound precondition — the payment fact; an issued DOC-05 for the Lampiran — and payload supplied through FIN's `DocumentSubjectProvider`; refused while a pre-payment Kuitansi is open on the invoice, and money already linked receiving no second Kuitansi (BR-DOC-04; C-43; WF-DOC-01)
- PG-045 with NAV-045 and ROUTE-045 (SCR-41), PG-046 with NAV-046 and ROUTE-046 (SCR-42; J-FIN-03), PG-050 with NAV-050 and ROUTE-050 (SCR-46; J-FIN-07's statement lines): payments with their applications, *Tanpa Bukti* and *Dikontra* states, the lines and their citation; for a viewer without `bank_statement.record`, QS-12's bank-line row is text without a link (INFORMATION_ARCHITECTURE §8.5); "Tautkan Kuitansi" on PG-046 (TASK-049's ROUTE-250; J-FIN-08)

**OUT OF SCOPE:**

- deduction settlements and their contras (ROUTE-255, ROUTE-260; TASK-052); refunds and credit's page (ROUTE-256, PG-048; TASK-053); disbursements, other Cash-In and the transfer-out itself (ROUTE-258, ROUTE-259; TASK-054)
- the dispositions that settlements and refunds add to AX-19 and AX-20 are tested here on synthetic settlements and refunds until TASK-052 and TASK-053 exist, which re-run them on real ones
- money received into the wrong company (CM-20): its two facts are each their own command — refund and fresh payment, or a real transfer (XL-5; ADMIN_FLOW §11) — through TASK-053's and TASK-054's routes and this Task's citing payment
- the pre-payment Kuitansi and its link (ROUTE-249, ROUTE-250; TASK-049); the upload routes ROUTE-228–ROUTE-231 and evidence registration (TASK-030); CALC-14's cashflow and the managerial reports of CAP-11 (TASK-060)

**REFERENCES:**

- [WORKFLOWS §5.6](../03-workflows/WORKFLOWS/s05-06-finance.md#wf-fin-03--payment-receipt-and-application-pembayaran-both) WF-FIN-03, with [WF-FIN-05](../03-workflows/WORKFLOWS/s05-06-finance.md#wf-fin-05--dispute-hold-and-owner-write-off-desk)'s recovery and [WF-FIN-07](../03-workflows/WORKFLOWS/s05-06-finance.md#wf-fin-07--expenses-disbursements-and-other-cash-desk)'s cross-company money; [§5.5](../03-workflows/WORKFLOWS/s05-04-05-fulfillment-documents.md#wf-doc-01--generated-document-lifecycle-desk) WF-DOC-01's DOC-05 and DOC-07 rows; [§6](../03-workflows/WORKFLOWS/s06-07-subflows-partial.md#6-reusable-subflows) SF-APPLY, SF-CONTRA, SF-CASE, SF-EVIDENCE; [§7](../03-workflows/WORKFLOWS/s06-07-subflows-partial.md#7-partial-processing-rules) PX-01–PX-08; [§8](../03-workflows/WORKFLOWS/s08-09-corrections-indivisible.md#8-correction-reversal-and-cancellation-matrix) CM-19–CM-21; [§9](../03-workflows/WORKFLOWS/s08-09-corrections-indivisible.md#9-indivisible-business-actions) AX-18–AX-20; [§10](../03-workflows/WORKFLOWS/s10-14-signals-decisions-traceability.md#10-derived-signals-and-action-queue) QS-12, QS-14, QS-21; [§12](../03-workflows/WORKFLOWS/s10-14-signals-decisions-traceability.md#12-planner-determined-level-1-decisions) L-22, L-28, L-29, L-45, L-46 and the coverage line's other L- rows
- [BUSINESS_RULES §4](../02-domain/BUSINESS_RULES.md#finance-fin) BR-FIN-01–BR-FIN-17; [SM:Document](../02-domain/BUSINESS_RULES.md#smdocument-all-doc-0114-invoice-is-a-specialization) BR-DOC-04; [§2](../02-domain/BUSINESS_RULES.md#2-correction-taxonomy) BR-CR-02; [§5](../02-domain/BUSINESS_RULES.md#5-calculation-semantics) CALC-05–CALC-08, CALC-11 and the [numeric annex](../02-domain/BUSINESS_RULES.md#numeric-annex-binding-examples-for-p8-fixtures-gap-004) 1–5; [§7](../02-domain/BUSINESS_RULES.md#7-forbidden-shortcuts) FS-04, FS-08, FS-10, FS-13
- [V1_SCOPE](../01-product/V1_SCOPE.md#financial-concepts-and-billing) the financial concepts; [CAP-10, CAP-11](../01-product/V1_SCOPE.md#must-ship); [DOC-05, DOC-07](../01-product/V1_SCOPE.md#selectable-document-catalog); [ACCEPTANCE_CRITERIA](../01-product/ACCEPTANCE_CRITERIA.md#product-acceptance-contracts) AC-10, AC-11, AC-16; [PRODUCT_OVERVIEW](../01-product/PRODUCT_OVERVIEW.md#problems-and-operational-outcomes) O-05; [REFERENCE_COVERAGE](../01-product/REFERENCE_COVERAGE.md#pdf-capability-inventory) REF-016, REF-041–REF-045
- [DATABASE §4.12](../04-architecture/DATABASE/s04-12-fin.md#412-fin--finance); [§4.10](../04-architecture/DATABASE/s04-10-doc.md#410-doc--documents--evidence) `bank_statement_lines`; [§11.1](../04-architecture/DATABASE/s11-12-finance-tax.md#111-the-five-concepts-never-share-a-row), [§11.3](../04-architecture/DATABASE/s11-12-finance-tax.md#113-payments-applications-credit-and-refunds), [§11.4](../04-architecture/DATABASE/s11-12-finance-tax.md#114-deduction-settlements-write-offs-and-pre-payment-kuitansi), [§11.5](../04-architecture/DATABASE/s11-12-finance-tax.md#115-expenses-disbursements-and-other-cash); [§18](../04-architecture/DATABASE/s18-19-constraints-integrity.md#18-constraint-catalogue) C-13–C-15, C-19, C-22, C-43–C-45
- [ARCHITECTURE §7](../04-architecture/ARCHITECTURE.md#7-posting-services-and-guards) `MoneyPosting`, `ReceivablePosting`, `EvidencePosting`
- [CONCURRENCY_IDEMPOTENCY §7.1](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s07-statement-sequences.md#71-lock-sets-of-ax-01ax-37) and [§7.2](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s07-statement-sequences.md#72-checks-effects-and-outcomes-of-ax-01ax-37) AX-18–AX-20; [§7.3](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s07-statement-sequences.md#73-command-families-outside-ax-0137) NX-08; [§5](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s04-06-locks-envelope.md#5-data-dependent-lock-set-protocol); [§9](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s08-09-guards-stale-state.md#9-stale-state) ST-06, ST-07; [§10](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s10-13-identity-failure-retry.md#10-command-identity-and-replay) CI-05; [§11](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s10-13-identity-failure-retry.md#11-separate-duplicates) BD-02, BD-03, BD-14; [§18](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s18-scenarios.md#18-mandatory-concurrency-and-failure-scenarios) M-08–M-14, M-28, M-37
- [PERMISSIONS_MATRIX §4](../05-security/PERMISSIONS_MATRIX.md#4-capability-catalogue) `payment.record`, `payment.apply`, `payment.reallocate`, `finance.contra`, `bank_statement.record`, `cash.correct`, `warnings.override`; [§7.2](../05-security/PERMISSIONS_MATRIX.md#72-table-classification) rows 9, 93, 94, 105–108, 116, 117; [§7.3](../05-security/PERMISSIONS_MATRIX.md#73-special-projections) PJ-08, PJ-15; [§8](../05-security/PERMISSIONS_MATRIX.md#8-cross-company-link-authorization) XL-5; [§11](../05-security/PERMISSIONS_MATRIX.md#11-data-path-coverage-matrix) DP-16
- [ROUTE_CONTRACTS](../03-workflows/ROUTE_CONTRACTS.md#finance--invoices-payments-deductions-credit-other-cash-and-bank-statements-fin-pg-041pg-050) ROUTE-251–ROUTE-254, ROUTE-261, ROUTE-262; [§2](../03-workflows/ROUTE_CONTRACTS.md#2-page-views--route-001route-065) ROUTE-045, ROUTE-046, ROUTE-050
- [INTERACTION_CONTRACTS](../03-workflows/INTERACTION_CONTRACTS.md#finance--invoices-payments-deductions-credit-other-cash-and-bank-statements-fin-pg-041pg-050) INT-251–INT-254, INT-261, INT-262; [§2](../03-workflows/INTERACTION_CONTRACTS.md#2-shared-interactions--int-001int-020) INT-013, INT-015
- [NAVIGATION_CONTRACTS §2.5](../07-ux-design/NAVIGATION_CONTRACTS.md#25-finance) NAV-045, NAV-046, NAV-050; [INFORMATION_ARCHITECTURE §8.5](../07-ux-design/INFORMATION_ARCHITECTURE.md#85-finance) PG-045, PG-046, PG-050; [§8.7](../07-ux-design/INFORMATION_ARCHITECTURE.md#87-the-approved-screens-scr-01scr-60-and-their-pages) SCR-41, SCR-42, SCR-46
- [ADMIN_FLOW J-FIN-03, J-FIN-07, J-FIN-08](../07-ux-design/ADMIN_FLOW/s10-b-journeys.md#106-finance); [§11](../07-ux-design/ADMIN_FLOW/s11-13-corrections-numbering-import.md#11-corrections-and-reversals) CM-19–CM-21; [IP-05](../07-ux-design/ADMIN_FLOW/s05-command-model.md#ip-05--duplicate-warnings); [IP-08](../07-ux-design/ADMIN_FLOW/s05-command-model.md#ip-08--confirming-an-irreversible-command); [IP-09](../07-ux-design/ADMIN_FLOW/s05-command-model.md#ip-09--corrections-closures-and-destructive-actions); [IP-16](../07-ux-design/ADMIN_FLOW/s06-09-session-forms-lists.md#ip-16--evidence-upload); [UXS-16](../07-ux-design/ADMIN_FLOW/s14-15-messages-scenarios.md#duplicates)
- [DESIGN_SYSTEM §12.1](../07-ux-design/DESIGN_SYSTEM.md#121-status-system) the payment family; [§14.1](../07-ux-design/DESIGN_SYSTEM.md#141-financial-data)
- [TEST_STRATEGY §5](../08-testing/TEST_STRATEGY.md#5-domain-and-data-integrity) FX-01 1–5, FX-04's Rp1 refund-shortfall attack; [§6](../08-testing/TEST_STRATEGY.md#6-concurrency-and-failure-proofs); [§7](../08-testing/TEST_STRATEGY.md#7-browser-end-to-end-tests-and-critical-journeys) CJ-01, CJ-04, CJ-21; [SECURITY_TEST_MATRIX](../08-testing/SECURITY_TEST_MATRIX.md#3-the-matrix) SM-01, SM-04, SM-12, SM-25; [PERFORMANCE_TARGETS §2](../08-testing/PERFORMANCE_TARGETS.md#2-the-query-budgets) QB-12; [§3](../08-testing/PERFORMANCE_TARGETS.md#3-the-measured-targets) PFM-01, PFM-02, PFM-07, PFM-20
- [GAP_REGISTER](../00-governance/GAP_REGISTER.md#gap-004--exact-arithmetic-does-not-define-managerial-financial-meaning) GAP-004; [GAP-008](../00-governance/GAP_REGISTER.md#gap-008--retry-safety-is-undefined-after-a-lost-response-or-key-reuse)
- Architecture challenge — the sequences 5b, 6a, 6b, 6c of the [P11 gate record](evidence/P11_QUALITY_GATE.md#whole-system-architecture-challenge-sp-98), their assertions carried into this Task's acceptance criteria and tests
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (142): O-04–O-05; CAP-10–CAP-11; DOC-05; DOC-07; AC-10–AC-12; AC-16; OS-02; REF-016; REF-041–REF-045; REF-049; REF-058; IMG-06–IMG-07; OWN-01; OWN-05; BR-FIN-01–BR-FIN-17; BR-DOC-04; CALC-05–CALC-08; CALC-11; FS-13; WF-FIN-03; SF-APPLY; PX-01–PX-08; CM-19–CM-21; AX-18–AX-20; L-05–L-08; L-12; L-16–L-19; L-22; L-26; L-28–L-31; L-43; L-45–L-47; L-50; ROUTE-045–ROUTE-046; ROUTE-050; ROUTE-251–ROUTE-254; ROUTE-261–ROUTE-262; INT-251–INT-254; INT-261–INT-262; C-13–C-15; C-18–C-19; C-23; C-25–C-26; C-41–C-42; C-44–C-45; C-47; C-52; C-57; NX-08; BD-02–BD-03; BD-14; M-08–M-14; M-28; M-37; HO-12; PG-045–PG-046; PG-050; SCR-41–SCR-42; SCR-46; J-FIN-03; J-FIN-07–J-FIN-08; UXS-01; UXS-16; NAV-045–NAV-046; NAV-050; GAP-004

**EXPECTED MODULES / FILE AREAS:**

- the FIN module's actions RecordPayment, ApplyPayment, ReallocateApplication, ContraPayment, CorrectBankCitation and SupersedeClaimedDeduction, the DOC actions RecordBankStatementLine and VoidBankStatementLine, with `ShowPayments`, `ShowRecordPayment` and `ShowBankStatement` and their query classes (RC-02; ARCHITECTURE §3–§4); FIN's `DocumentSubjectProvider` part for the receipt-mode DOC-05 and DOC-07; `MoneyPosting`, `ReceivablePosting` and DOC's `EvidencePosting` (ARCHITECTURE §7); `resources/js/Pages/FIN`; `tests/Feature/FIN`, `tests/Feature/DOC`, `tests/Concurrency/`, `tests/Reconciliation/`

**BUSINESS RULES:**

- BR-FIN-01, CALC-14's Cash-In and OWN-01: a payment is Cash-In only for money actually received, distinct from sales value, receivable, cost and Cash-Out; zero or negative normal payments are rejected (BR-FIN-06; AC-10); FS-04: no Cash-In from issuing, billing, a settlement or a Kuitansi
- BR-FIN-03 and SF-APPLY: Σ applications ≤ the payment, atomically, across invoices and refunds; each ≤ the invoice's outstanding (BR-FIN-04; C-13; C-15); same client and same company only — never a cross-company application (BR-FIN-07; C-14; FS-10); a reallocation supersedes, never deletes, and the payment never disappears (annex 4)
- BR-FIN-05, CALC-06 and FS-08: an unapplied remainder is customer credit, never revenue or profit (annex 3); an advance applied to an unbilled invoice is Cash-In once, billing activating only the rest (annex 2)
- L-22 and BR-FIN-06: retry is the command identity; the duplicate heuristic ignores the reference and needs the ADM+ override with a reason; every bank payment cites its statement line, Σ Cash-In per line, net of contras, ≤ the line at every commit, full citation being a reconciliation target; L-29: proof required, an unevidenced payment flagged, failing the audit predicate, its credit not refundable
- BR-CR-02, SF-CONTRA, L-46 and CM-19: a wrong payment fact is corrected only by its contra, both visible, forcing the disposition of every application and settlement; a refund-consumed application moves only to the re-recorded payment of the same client and company, and an unbacked refund stays an explicit residual on the case; no disposition frees capacity that real money does not support (C-44; DATABASE §11.3)
- L-28 and L-45: settlements anchored to a moved (payment, invoice) move with the application or stay with reason in the same action; a bank line's identity is account, date, amount and line reference
- PX-01–PX-08: payments and applications are additive, an unapplied remainder a valid resting state, excess application becoming credit (PX-03), a partial payment leaving the rest active and ageing (PX-06), any authorized Admin continuing the work (PX-07)
- BR-DOC-04: a receipt-mode Kuitansi requires the referenced real payment and is never proof of receipt for itself (L-29); its Lampiran follows its revisions and voids
- the rest of the coverage line holds as invariants these commands must not break, each built by its own Task: BR-FIN-02, BR-FIN-08–BR-FIN-17, CALC-05, CALC-07, CALC-08, CALC-11, C-18, C-23, C-25, C-26, C-41, C-42, C-52, C-57 (invoices, billing, settlements, cost and tax — TASK-049–TASK-054, TASK-036, TASK-025); L-05–L-08, L-12, L-16–L-19, L-26, L-30, L-31, L-43, L-47 (the family's other rules); L-50 and C-47 (an opening credit is an application source with its import identity, never live Cash-In — TASK-057); REF-016 (configurable tax, fee and VA components, no hard-coded formula — FS-13); O-05, CAP-11, AC-11, REF-044 and REF-045 (managerial finance reconciling with these facts — TASK-060)

**ARCHITECTURE RULES:**

- the five concepts never share a row: Cash-In is `payments` and other Cash-In net of contras (DATABASE §11.1); an application's amount is immutable, only its end columns change once through the transition trigger (DATABASE §11.3)
- `application_source_balances`, `money_fact_balances` and application states have one normal writer, `MoneyPosting`; `invoice_balances` `ReceivablePosting`; `evidence_balances` DOC's `EvidencePosting` (ARCHITECTURE §7); a refund's backing exits only through its typed exits (C-44)
- FIN is tier 6 and DOC tier 1: the bank statement line is DOC's evidence, cited by FIN's payment under LK-29 (ARCHITECTURE §3)

**SECURITY / PERMISSION REQUIREMENTS:**

- ROUTE-045 `finance.view`; ROUTE-046 and ROUTE-251 `payment.record` with `finance.view`, covering the same-command applications, a duplicate's second submission also `warnings.override` (ADM_PLUS); ROUTE-252 `payment.apply`; ROUTE-253 `payment.reallocate` (ADM_PLUS); ROUTE-254 `finance.contra` (ADM_PLUS); ROUTE-050 and ROUTE-261 `bank_statement.record` with `finance.view` and `evidence.upload`; ROUTE-262 `cash.correct` (ADM_PLUS); every correction asks its reason (IP-09; MSG-40)
- a replay of a payment's `command_id` returns its recorded outcome only to its original actor after re-authorization; another account's reuse is CONFLICT disclosing nothing (DP-16; CI-05; M-13)

**COMPANY SCOPE AND FIELD PROJECTION:**

- a payment's company is the one chosen on PG-046 for this new root, in scope and active, never prefilled from the working filter for an account with several companies (CS-03; INFORMATION_ARCHITECTURE §7); its client, invoices and bank account belong to that company by composite keys (C-14; C-32); an INTERCOMPANY_TRANSFER_OUT is cited only with both companies in scope, each side seeing its own fact, the other masked (XL-5)
- rows 105–108, 116 and 117: payments, claimed deductions, source balances, applications, contras and money-fact balances FV(c); rows 93–94: statement lines and their balances FV(c); row 9: the company's bank accounts FV(c); PAYMENT_PROOF and BANK_STATEMENT are the finance family's evidence, with `evidence.view` and `finance.view` (PJ-08)
- an evidence duplicate outside scope or in an invisible family discloses nothing to the actor (DP-13; SM-12); lists by branch with the scope filter inside the query and no total across companies for an Admin (CS-07; CS-08)

**CONCURRENCY / IDEMPOTENCY:**

- AX-18 for ROUTE-251, DATA-DEPENDENT: LK-00, LK-01 (the projects of the invoices its same-command applications target; the project of a case whose refund shortfall one of them resolves), LK-03, LK-07 (the bank account — BD-02's anchor), LK-10, LK-25, LK-26, LK-27, LK-29 (the statement line — BD-03's anchor); re-read under lock: the account the company's and active, the statement line ACTIVE with capacity, a cited transfer-out's capacity, the duplicate query under its anchor — so two concurrent submissions cannot both pass —, the applications as AX-19; refusals C-19, C-13, C-15, C-44, L-22; identity BD-02 and BD-03 with the command identity; retry class A (CONCURRENCY_IDEMPOTENCY §7.1–§7.2; §11)
- AX-19 for ROUTE-252 and ROUTE-253, DATA-DEPENDENT: LK-00, LK-01 (the old and new target invoices' projects; a resolved shortfall's case), LK-03, LK-10, LK-25 in id order (LR-01; M-28), LK-26, LK-27, LK-29; re-read: source capacity and outstanding ≥ the amount, the application not refund-consumed, the anchored settlements' disposition; the old application's conditional end — already ended is CONFLICT; refusals C-13, C-15, C-44; identity the payment and target; retry class A
- AX-20 for ROUTE-254, DATA-DEPENDENT: LK-00, LK-01 (every invoice the source is applied to; the case's project), LK-03, LK-10, LK-25, LK-26, LK-27, LK-29; re-read: the contra ≤ the source's net amount; the payload covering every ACTIVE application and settlement — a stale list is CONFLICT with the current one (ST-07); refusals C-13, C-44, C-45; identity the original payment; retry class B
- NX-08 for ROUTE-261 and ROUTE-262 (LK-00, LK-01, LK-03, LK-26, LK-27, LK-29): a citation correction moves both lines' guards in one transaction, data-dependent through the payment's applications
- the scenarios as real two-session tests: M-08 (one `command_id` twice, in sequence and concurrently — one payment), M-09 (same id, another payload — CONFLICT), M-10 (the answer lost, then the session expired — the original result), M-11 (two ids citing one line beyond it — the second REJECTED C-19), M-12 (two Admins, one real payment, no line — the second REJECTED L-22 until overridden naming the match), M-13 (another account's reuse), M-14 (a contra racing an application or refund on the source — capacity never freed beyond real money; the Rp1 contra), M-28 (two reallocations between the same invoices in opposite orders), M-37 (an invoice void racing an application); `ReconcileGuards` reports no difference after each (HO-12)

**AUDIT:**

- payments and applications are critical audit areas (BR-XC-01): every payment, application, reallocation, contra, statement line, void, citation correction and claimed-deduction supersession is a business audit event with the individual actor, business date, reason where required, evidence references, before/after and links (BR-CR-01; LG-01); an overridden duplicate warning commits under its acknowledged variant with the matches it names and is ADM_PLUS (AZ-10; QS-21; CONCURRENCY_IDEMPOTENCY §11); reallocations, contras and cash corrections are ADM_PLUS and reach the Owner's review queue, as does credit linked to a written-off invoice (QS-14; PERMISSIONS_MATRIX §10)

**DATABASE IMPACT ASSESSMENT:**

- Schema change: NONE — uses the tables of TASK-005–TASK-009; a missing column is an executor discovery (AGENT_OPERATING_MODEL)
- Migration required: NO
- Existing data transformation: NONE
- Direct production DB access: FORBIDDEN
- Data-loss risk: NONE KNOWN

**MODULARITY / EXTENSIBILITY:**

- Module boundary affected: FIN, tier 6, citing DOC's (tier 1) statement lines and evidence (ARCHITECTURE §3)
- Stable core kept provider-neutral: YES
- External/vendor-specific code isolated: YES — framework facilities or a small interface; no bank integration exists in V1 (API_AND_INTEGRATIONS)
- Replaceable adapter/contract required: NO
- Provider lock-in risk: NONE beyond the Owner's baseline stack
- Future extension impact: MEDIUM — settlements, refunds, write-off recovery and reports act on these facts
- Speculative abstraction avoided: YES — no reconciliation or accounting engine (FS-13; ARCHITECTURE §1)

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

- Context7: REQUIRED — PostgreSQL transition triggers with column grants, CHECKs over guard rows and row locks in id order, an arbitrary-precision decimal library (DEP-07), at the installed versions
- Graphify: OPTIONAL — once the finance Tasks exist
- Browser E2E: REQUIRED — J-FIN-03 on the phone with one application and at the desk with several and the statement line; S2-J06; CJ-04's payments, overpayment and duplicate
- Other: the two-connection harness and the numeric fixtures FX-01 1–5 and FX-04 (TASK-003)
- Missing safe tool behavior: AUTO-INSTALL project-locally inside this Task (AICWDF §6.3) and record it in TOOLCHAIN; a paid tier or a privileged installation STOPS for the Owner (COST_POLICY)

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: refreshed before the change when Graphify is available ([TOOLCHAIN](../00-governance/TOOLCHAIN.md)); otherwise a targeted search of FIN, DOC's evidence and the money posting service
- Pre-change questions: which commands consume application capacity (AX-19, AX-23, AX-24's supersession), which move evidence citations (AX-18, AX-21, NX-08, NX-10), which unlink a Kuitansi
- Expected affected areas: FIN's payment actions, DOC's statement-line actions; `MoneyPosting`, `ReceivablePosting`, `EvidencePosting`; PG-045, PG-046, PG-050; DOC-05 and DOC-07's subject

**CONTEXT7:**

- Topics to verify: transition triggers enforcing a once-only end; CHECK violations mapped to rule codes; `SELECT … FOR UPDATE` in id order across several rows
- Version-sensitive assumptions: none from memory

**UI / UX REQUIREMENTS:**

- Follow global design rules: YES
- Indonesian default: YES
- English support: YES
- Plain-language Indonesian: YES — MSG-39 in the payment's confirmation; MSG-15 for a duplicate, both forms (UXS-16); the missing-proof reason; the contra form listing each application and settlement and what becomes of it (ADMIN_FLOW §11); the row of a refund-consumed application saying why it cannot move (L-46)
- Language switch impact: keeps unsent input — the payment and its application lines
- Mobile-first: YES — PG-045 and PG-046 `[both]`: a payment with its proof and one application on the phone, several applications and the statement line at the desk (J-FIN-03); PG-050 `[desk]`, dense
- Responsive: YES
- Adaptive: YES — PG-046 one column with its application step on the phone (NAV-046), the applications beside the payment on the desktop
- Visual breathing room: REQUIRED
- Comfortable density: REQUIRED — PG-050 dense
- shadcn/ui reuse: the form layout and multi-line entry (PT-09, PT-10), the evidence upload (IP-16), the business-date control (PT-25), the confirmation with its reason (PT-26), data table and dense rows (PT-05, PT-06), status badges *Tanpa Bukti* and *Dikontra* (PT-13), the financial position block (PT-24), the timeline (PT-18)
- Design reference: PG-045, PG-046 and PG-050 as INFORMATION_ARCHITECTURE §8.5 and S2-J06 state them; the Golden Frontend Reference for presentation only (DIR-061 SD-02)
- Primary action: "Catat Pembayaran"
- Secondary actions: "Terapkan"; "Pindahkan Penerapan"; "Batalkan Catatan Pembayaran (Kontra)"; "Catat Mutasi Bank"; the line's void, the citation's correction and the claimed deduction's supersession; "Tautkan Kuitansi" (TASK-049's route)
- Destructive actions: none beyond the correcting contra with its reason — no control deletes a payment or an application (FS-02)
- Redundancy constraints: one payment fact per real receipt — a likely duplicate warned before a second submission; one identity per bank line; a second company's invoices never offered for the same payment (J-FIN-03)
- Loading state: PT-19; the command's pending state, no double submission (UXS-01)
- Empty state: no payment yet, no statement line — one sentence (PT-19)
- Error state: an application above the outstanding REJECTED with the figure; more than the statement line REJECTED (C-19); an invoice of another company never offered; MSG-15 for a duplicate; MSG-07 with "Coba Lagi" for an unknown outcome (IP-04)

**ROUTE IMPACT:**

- ROUTE-045 (GET `/keuangan/pembayaran`), ROUTE-046 (GET `/keuangan/pembayaran/baru`), ROUTE-050 (GET `/keuangan/mutasi-bank`)
- ROUTE-251 (POST `/keuangan/pembayaran`), ROUTE-252 (`…/{id}/terapkan`), ROUTE-253 (`…/{id}/realokasi`), ROUTE-254 (`…/{id}/kontra`), ROUTE-261 (POST `/keuangan/mutasi-bank`), ROUTE-262 (`…/mutasi-bank/{id}/koreksi`); each answering RC-05's errors; the proof's file by ROUTE-228 first (TASK-030)

**INTERACTION CONTRACTS:**

- INT-251–INT-254, INT-261 and INT-262, each with its *Test* as its test (IG-05); INT-013 for the proof; INT-015 for the duplicate's second submission with `warnings.override`; INT-016 and INT-017 for CONFLICT and FAILED

**NAVIGATION CONTRACT:**

- Parent: PG-045 — *Keuangan* › *Pembayaran* (NAV-045); PG-046 — PG-045 · PG-011 · PG-044 (NAV-046); PG-050 — *Keuangan* › *Lainnya* (NAV-050)
- Entry: *Keuangan* › *Pembayaran*; "Buat" › *Pembayaran*; the journey's *Pembayaran* stage; *Piutang*; *Keuangan* › *Lainnya*
- Back action: PG-046 "Kembali" → the origin, MSG-35 with unsent input; PG-050 "Kembali" → PG-041, *Keuangan*'s first page
- Cancel: "Batal" → the origin; MSG-39's and the reason step's "Kembali" → the form, the input kept (IP-08; IP-09)
- Success destination: PG-045 with the recorded payment first (NAV-046; S2-J06); PG-050 with the line (NAV-050); applications and corrections in place
- Error behavior: NV-04 — validation at the field, a rejected command in place, the input kept
- State preservation: filters, sort, tab and cursor in the address (IP-18); PG-046's unsent input kept across the re-login, never in browser history
- Mobile behavior: PG-045, PG-046 `[both]` — one column with its application step; PG-050 `[desk]`, dense

**ACCEPTANCE CRITERIA:**

1. Payments of Rp300,000 then Rp700,000 applied to a billed Rp1,000,000 invoice create those Cash-In amounts and leave Rp700,000, then zero outstanding; a payment of Rp1,200,000 against Rp1,000,000 outstanding applies Rp1,000,000 and leaves Rp200,000 as customer credit, never profit; a zero or negative payment is rejected (AC-10; annex 1, 3; BR-FIN-05; BR-FIN-06).
2. Two applications of one payment committing at once never exceed the payment's real money, and no invoice's outstanding goes below zero: the second is REJECTED with the current capacity (BR-FIN-03; BR-FIN-04; C-13; C-15; AX-19; architecture challenge 6a).
3. An application to an invoice of another client or another company is refused; such invoices are never offered for the payment (BR-FIN-07; C-14; FS-10; architecture challenge 6b).
4. Two payments citing one bank statement line beyond its amount leave the second REJECTED; a payment of the same company bank account and amount within seven days either side is REJECTED with the match until resubmitted with `warnings.override`, its reason and the acknowledged match, and an override naming another match is REJECTED again (C-19; BD-02; BD-03; M-11; M-12; architecture challenge 6c; UXS-16).
5. One `command_id` submitted twice, in sequence or concurrently, records one payment; the answer lost after commit and retried after re-login returns the original result; reused by another account it is CONFLICT disclosing nothing (M-08; M-10; M-13; CI-05; AC-16).
6. A reallocation of Rp400,000 from invoice A to B supersedes the old application and keeps the payment total; an application consumed by a refund cannot be moved; the settlements anchored to the old (payment, invoice) move or stay with reason in the same command (annex 4; L-46; L-28; CM-21).
7. A payment's contra commits only with a disposition of every active application and settlement; a stale list is CONFLICT; the Rp1 contra of DATABASE §11.3 cannot free capacity for a new application, and a legitimate shortfall stays on the case as an unbacked-refund residual (AX-20; ST-07; C-44; C-45; M-14; FX-04).
8. A billed invoice with Rp400,000 of this payment applied is voided only with that application re-targeted to the named replacement or released to credit in the void's own command; the payment's total and capacity stay exact and no money is orphaned (annex 5; C-16; AX-22; architecture challenge 5b).
9. A payment without proof commits only with its reason, shows *Tanpa Bukti*, fails the audit predicate and its credit cannot be refunded; a company-issued Kuitansi is never accepted as proof (L-29).
10. A mistyped statement line is voided only while uncited; a wrong citation is corrected by moving both lines' guards with a reason, never by a contra; a claimed deduction is superseded, never below what is already settled against it (C-19; NX-08; DATABASE §11.4).
11. A receipt-mode Kuitansi issues only for a recorded payment, is refused while a pre-payment Kuitansi is open on the invoice, and linked money receives no second Kuitansi (BR-DOC-04; C-43).
12. The corrections are absent without `payment.reallocate`, `finance.contra` and `cash.correct`, each asking its reason; payment pages show nothing without `finance.view` (AZ-01; SM-04; SM-25).

**TEST REQUIREMENTS:**

- Lint: TL-01
- Typecheck: TL-02
- Unit: capacity, outstanding and credit against FX-01 1–5; the typed exits of a refund-backed application (TL-03)
- Feature: ROUTE-251–ROUTE-254, ROUTE-261 and ROUTE-262 with each refusal their contracts name (TL-04)
- Integration: M-08–M-14, M-28 and M-37 with real sessions, both orders; FX-04's Rp1 refund-shortfall attack with synthetic refunds until TASK-053 exists; anchored settlements moving with a reallocation with synthetic settlements until TASK-052 exists; the reconciliation suite after each (TL-05; TEST_STRATEGY §5, §6)
- Authorization: `payment.record`, `payment.apply`, `payment.reallocate`, `finance.contra`, `bank_statement.record`, `cash.correct`, `warnings.override` by class; the replay after a revocation answered as AZ-11 (TL-06; SM-04; SM-13)
- Route integrity: ROUTE-045, ROUTE-046, ROUTE-050, ROUTE-251–ROUTE-254, ROUTE-261, ROUTE-262 audited (TL-07; SM-02)
- Interaction: INT-251–INT-254, INT-261, INT-262 per IG-05, with one `command_id` replayed; INT-015's override kind with and without the capability (TL-08)
- Browser E2E: J-FIN-03 and S2-J06 on the phone and the desktop; CJ-04's partial, full and overpayment, credit and the duplicate; CJ-21's lost answer and retry (TL-09)
- Localization/language switch: both languages on PG-045, PG-046 and PG-050; DOC-05 and DOC-07 in Indonesian (TL-10)
- Visual breathing room/density: TEST_STRATEGY §9
- Responsive: TL-11
- Accessibility: TL-12
- Production build: TL-13
- Security: SM-01 for another company's payment, invoice and statement line; SM-12 for an evidence duplicate's oracle; SM-25 (TL-14)
- Performance: QB-12 for PG-045 and PG-050 with PFM-01 and PFM-02; PFM-07 for QS-12's bounded statement; PFM-20 with QB-13 for AX-18 and AX-19 and QB-14 for AX-20 (TL-15)
- Migration: N/A — no migration or import code in this Task; opening credits are TASK-057's
- Other: CM-19 and CM-21's expected end states asserted, each also under concurrency (TEST_STRATEGY §5)

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

- TASK-052 — when its other dependencies are DONE and its READY gate holds

**DO NOT DO:**

- add to V1 — a capability, page, route, field, integration, document type, report or paid service — or drop, defer or narrow a V1_REQUIRED requirement; a Task that would need either raises an Owner question and waits ([DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-20)
- access the VPS, production, its data or a credential, or put a secret, real data, a host detail or production configuration in the repository ([INFRASTRUCTURE](../09-operations/INFRASTRUCTURE.md) INF-01, INF-05; SECURITY SX-01, SX-04, SX-06; [PRODUCTION_DATA_SAFETY](../00-governance/PRODUCTION_DATA_SAFETY.md))
- add AI, model or tool attribution to a commit — the Owner's configured identity only ([AGENTS.md](../../AGENTS.md#git-commit-attribution); DIR-022)
- start before the Owner's execution authorization at OG-4, or mark DONE without the required tests passing and the completion evidence ([TASK_PLAN](TASK_PLAN.md#2-rules-of-the-plan); [DEFINITION_OF_DONE](../08-testing/DEFINITION_OF_DONE.md#2-the-checklist))
- weaken, skip or delete a valid test to obtain a green run, or adopt a paid tier without the Owner's COST_POLICY decision (DOD-05; [COST_POLICY](../00-governance/COST_POLICY.md))
- apply a payment across companies or clients, beyond its real money, or beyond an invoice's outstanding (BR-FIN-03; BR-FIN-07; C-13–C-15)
- turn a duplicate warning into a block or remove it, or let an override cover a match it does not name (BD-02; CONCURRENCY_IDEMPOTENCY §11)
- correct a payment by editing or deleting it, a citation by contra, or free refund backing that real money does not support (BR-CR-02; L-46; C-44)
- count customer credit as revenue, or accept a company-issued document as proof of receipt (FS-08; L-29)

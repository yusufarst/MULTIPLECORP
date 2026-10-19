# TASK-049 — Invoices and the pre-payment Kuitansi

Status: REVIEW | Updated: 2026-10-20 | Owner: Planning

Written by WF-PC5-W's writer of bundle W2 of run PC-5 on 2026-10-19 and 2026-10-20 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99 — a REVIEW draft of P11, approved, or not, with the [Task plan](TASK_PLAN.md) at OG-4. **No Task starts before the Owner's execution authorization at OG-4.** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** TODO

**RISK CLASS:** HIGH

**EXECUTOR:** AGENT

**OBJECTIVE:**

Build the invoice — the single sales-value record with its two layouts DOC-06 *Invoice* and DOC-04 *Nota* —: its draft composed from confirmed lines or as a value-only DP or termin record with its tax components, its issue within the project cap that snapshots the agreed value and leaves it *Belum Ditagihkan* with no Cash-In and no active receivable, its void or downward revision with a typed basis, a named replacement that inherits the billing dates and the disposition of every attached reduction, and the pre-payment Kuitansi (DOC-05 *untuk Proses Pembayaran*) with its link to the real payment, on PG-041 *Invoice*, PG-042 *Buat Invoice* and PG-043 *Detail Invoice* (WF-FIN-01; WF-FIN-08; AX-16; AX-22; AX-31; ROUTE-241, ROUTE-242, ROUTE-245, ROUTE-249, ROUTE-250).

**WHY THIS EXISTS:**

O-04, CAP-10 and AC-10 require staff to tell issued sales value and *Belum Ditagihkan* apart from billed receivable and actual Cash-In (OWN-01; REF-039; IMG-07): issuing an invoice snapshots the agreed value and implies no cash, no stock change and no obligation beyond its own (BR-FIN-02; BR-XD-04; architecture challenge 4b). A correction after billing and payment must dispose every reduction and never reset aging (BR-CR-03; L-06; L-30; challenges 5a, 5b), a return's invoice correction stays its own explicit command (BR-XD-01; challenge 4a), and institutional payers need a signed Kuitansi before they transfer, without it ever counting as money received (DIR-024 D-4; GAP-029). Billing, payments and settlements (TASK-050–TASK-052) act on the invoice this Task issues.

**DEPENDS ON:**

- TASK-025 — Companies, identity assets, bank accounts and tax configuration
- TASK-029 — Document engine — drafts, issue, revision, void, renditions and the composer
- TASK-034 — Commercial confirmation and fulfillment planning

**BLOCKS:**

- TASK-050 — Billing, receivables, dispute hold and Owner write-off
- TASK-056 — Project cancellation

**PARALLEL SAFE:** NO — it owns the invoice guard (LK-25) and the project's invoice cap (LK-20) that billing, payments, settlements, write-offs and completion read and post

**IN SCOPE:**

- the FIN invoice records of DATABASE §4.12 in use (§11.2): `invoices` with origin, `replaces_invoice_id` and the typed `void_basis`, `invoice_versions` with their typed `revision_basis`, `invoice_version_lines`, `invoice_tax_components` snapshotting the treatment resolved at the business date (DATABASE §12; BR-FIN-17; C-52), `invoice_balances` initialized at issue, `prepayment_kuitansi_links`
- ROUTE-241 with INT-241 (`FIN SaveInvoiceDraft`; `invoice.issue` with `finance.view`; NX-09): the composer on PG-042 — "Diambil dari proyek" and "Diisi sekarang" on the left, the live preview "DRAF — belum terbit" on the right from 1,025 px, Edit → Preview → Finalize on the phone (D-DS-23; `DIR-079` CD-04; DC-11; INT-012) —; a draft from confirmed lines, itemized, or a value-only DP or termin record (L-16); tax components recorded apart from the sales value under the treatment for the company, context, business date and evidence (BR-FIN-17; DIR-024 D-1); totals labelled *perkiraan* until saved (UXI-14); "Simpan Draf" on PG-042 and on PG-043 for a draft, two editors of one draft meeting CONFLICT (ST-01; UXS-11; M-38)
- ROUTE-242 with INT-242 (`FIN IssueInvoice`; `invoice.issue`; AX-16): "Terbitkan Invoice" — or its layout *Nota* — after MSG-39 (IP-08): the company active (C-54), the project cap Σ non-void sales values ≤ Σ confirmed demand value (C-31; L-16), the number of the company, type and business-date period drawn at issue (NM-03; C-10; UXS-29), the version ISSUED with its snapshot and its document version; the answer saying that no receivable is active and no cash is recorded yet, then "Buka Invoice" → PG-043 (NAV-042; J-FIN-01); invoicing above delivered value allowed and flagged (QS-15), never blocked
- ROUTE-245 with INT-245 (`FIN VoidInvoice · ReviseInvoiceDown`; `invoice.correct`; AX-22): an unbilled invoice revised or voided (CM-23); a billed one only with evidence of an entry error, a changed agreement — revision and re-confirmation with client evidence — or a return, value owed but uncollectable leaving only by the Owner's write-off (CM-24); the typed basis — the return's case, the re-confirmation, the cancellation — as a real row of the company (C-56); value still owed only to a replacement named in the same action, whose root billing act is INHERITED from the replaced invoice's current act, so aging is never reset (L-30; C-17; C-41); the project cap checked on the net; the payload disposing every reduction — applications re-targeted to the replacement or released to credit; each settlement re-recorded on the replacement up to what its evidence supports there, otherwise kept as an explicit unresolved-deduction residual on the correction case; an Owner write-off superseded or re-recorded on the replacement by the Owner in the same action (`writeoff.supersede`; OD-07) — the form listing them (ST-07); a linked pre-payment Kuitansi unlinked when its linked total falls (BR-CR-03; L-06; C-16)
- ROUTE-249 with INT-249 (`FIN IssuePrepaymentKuitansi`; `documents.issue`; AX-31): "Terbitkan Kuitansi untuk Proses Pembayaran" on PG-043 for an issued, non-void invoice: the amount entered and its reason; its draft created from the recorded facts and that amount by TASK-029's ROUTE-221 and opened on PG-036, where the confirmation with its reason issues it through this route, never ROUTE-222 (`DIR-081` OB-02); Σ open pre-payment Kuitansi on the invoice including this one ≤ its outstanding, and no receipt-mode Kuitansi while one is open (C-43); no Cash-In, payment fact, settlement or paid state — the invoice's position block unchanged and the dialog saying so; the state *Menunggu Pembayaran* marked "untuk proses pembayaran" until linked or voided (UXI-27; UXS-38; QS-19)
- ROUTE-250 with INT-250 (`FIN LinkPrepaymentKuitansi`; `payment.record`; AX-31): "Tautkan Kuitansi" on PG-036, PG-043 or PG-046 — the Kuitansi amount shown beside the linked payments' applications to that invoice plus its settlements; a link whose amounts differ REJECTED with both figures and the path — revise the Kuitansi, or void it and issue another (CM-26) —; one link per (payment, invoice); money already linked never receiving a second Kuitansi; a later correction that lowers the linked total unlinking it, the Kuitansi showing *Menunggu Pembayaran* again with the cause (J-FIN-08)
- PG-041 with NAV-041 and ROUTE-041 (SCR-38): invoices by branch, the exact number lookup, "Buat Invoice" opening PG-042 for the project chosen in the lookup (ROUTE_CONTRACTS §4); PG-042 with NAV-042 and ROUTE-042; PG-043 with NAV-043 and ROUTE-043 (SCR-39): lines, tax, the position block (PT-24), applications and settlements, documents, the hosts for TASK-050's and TASK-051's controls, a draft opening in the composer
- the invoice's record-bound precondition and payload for DOC-04, DOC-06 and the pre-payment DOC-05 through FIN's `DocumentSubjectProvider` (ARCHITECTURE §3): two layouts of one record, value, billing and receivable identical (L-26; BR-DOC-04); the receipt-mode DOC-05 is TASK-051's

**OUT OF SCOPE:**

- billing, its correction, dispute hold and the Owner's write-off with their controls on PG-043 and PG-044 (ROUTE-243, ROUTE-244, ROUTE-246–ROUTE-248; TASK-050); payments, applications, reallocation and contra (ROUTE-251–ROUTE-254; TASK-051); settlements (ROUTE-255; TASK-052); credit and refund (TASK-053)
- the dispositions' downstream commands — this Task's AX-22 disposes what TASK-050–TASK-053 create; until they exist its dispositions are tested on synthetic reductions, and each of those Tasks re-runs them on its real facts
- the document engine, numbering and the composer component (TASK-021, TASK-029); the tax configuration (TASK-025); QS-09, QS-15 and QS-19 on *Beranda* and *Pekerjaan* (TASK-058); the cancellation's invoice consequences (TASK-056)

**REFERENCES:**

- [WORKFLOWS §5.6](../03-workflows/WORKFLOWS/s05-06-finance.md#wf-fin-01--invoice-sales-record-issuance-desk) WF-FIN-01 and [WF-FIN-08](../03-workflows/WORKFLOWS/s05-06-finance.md#wf-fin-08--kuitansi-untuk-proses-pembayaran-pre-payment-kuitansi-desk), with [WF-FIN-02](../03-workflows/WORKFLOWS/s05-06-finance.md#wf-fin-02--billing-activation-and-receivable-monitoring-piutang-desk), [WF-FIN-03](../03-workflows/WORKFLOWS/s05-06-finance.md#wf-fin-03--payment-receipt-and-application-pembayaran-both) and [WF-FIN-05](../03-workflows/WORKFLOWS/s05-06-finance.md#wf-fin-05--dispute-hold-and-owner-write-off-desk) for what PG-043 hosts; [§5.5](../03-workflows/WORKFLOWS/s05-04-05-fulfillment-documents.md#wf-doc-01--generated-document-lifecycle-desk) WF-DOC-01's DOC-04–DOC-06 rows; [§6](../03-workflows/WORKFLOWS/s06-07-subflows-partial.md#6-reusable-subflows) SF-ISSUE, SF-APPLY, SF-SETTLE, SF-CASE, SF-REVAL; [§8](../03-workflows/WORKFLOWS/s08-09-corrections-indivisible.md#8-correction-reversal-and-cancellation-matrix) CM-12, CM-23, CM-24, CM-26; [§9](../03-workflows/WORKFLOWS/s08-09-corrections-indivisible.md#9-indivisible-business-actions) AX-16, AX-22, AX-31; [§10](../03-workflows/WORKFLOWS/s10-14-signals-decisions-traceability.md#10-derived-signals-and-action-queue) QS-09, QS-15, QS-19; [§12](../03-workflows/WORKFLOWS/s10-14-signals-decisions-traceability.md#12-planner-determined-level-1-decisions) L-01, L-05–L-08, L-12, L-16–L-19, L-21, L-22, L-26, L-28–L-31, L-39, L-43, L-45–L-47, L-49, L-50
- [BUSINESS_RULES §4](../02-domain/BUSINESS_RULES.md#finance-fin) BR-FIN-01–BR-FIN-17; [SM:Document](../02-domain/BUSINESS_RULES.md#smdocument-all-doc-0114-invoice-is-a-specialization) BR-DOC-01–BR-DOC-04; [§2](../02-domain/BUSINESS_RULES.md#2-correction-taxonomy) BR-CR-03, BR-CR-06; [§5](../02-domain/BUSINESS_RULES.md#5-calculation-semantics) CALC-05–CALC-08, CALC-11 and the [numeric annex](../02-domain/BUSINESS_RULES.md#numeric-annex-binding-examples-for-p8-fixtures-gap-004) 1, 2, 5, 13; [§6](../02-domain/BUSINESS_RULES.md#6-cross-domain-interaction-rules) BR-XD-01–BR-XD-07; [§7](../02-domain/BUSINESS_RULES.md#7-forbidden-shortcuts) FS-04, FS-08, FS-13
- [V1_SCOPE](../01-product/V1_SCOPE.md#financial-concepts-and-billing) the financial concepts; [CAP-10](../01-product/V1_SCOPE.md#must-ship); [DOC-04–DOC-06](../01-product/V1_SCOPE.md#selectable-document-catalog); [ACCEPTANCE_CRITERIA](../01-product/ACCEPTANCE_CRITERIA.md#product-acceptance-contracts) AC-10, AC-12, AC-16; [PRODUCT_OVERVIEW](../01-product/PRODUCT_OVERVIEW.md#problems-and-operational-outcomes) O-04; [REFERENCE_COVERAGE](../01-product/REFERENCE_COVERAGE.md#pdf-capability-inventory) REF-039–REF-043, REF-049, REF-057; [IMG-07](../01-product/REFERENCE_COVERAGE.md#image-and-non-tabular-pdf-inventory); [OWN-01, OWN-04](../01-product/REFERENCE_COVERAGE.md#final-owner-decision-coverage)
- [DATABASE §4.12](../04-architecture/DATABASE/s04-12-fin.md#412-fin--finance); [§11.1](../04-architecture/DATABASE/s11-12-finance-tax.md#111-the-five-concepts-never-share-a-row), [§11.2](../04-architecture/DATABASE/s11-12-finance-tax.md#112-invoice-billing-and-receivable), [§11.4](../04-architecture/DATABASE/s11-12-finance-tax.md#114-deduction-settlements-write-offs-and-pre-payment-kuitansi); [§12](../04-architecture/DATABASE/s11-12-finance-tax.md#12-tax-architecture); [§18](../04-architecture/DATABASE/s18-19-constraints-integrity.md#18-constraint-catalogue) C-10, C-13–C-17, C-31, C-41–C-43, C-52, C-54, C-56
- [ARCHITECTURE §3](../04-architecture/ARCHITECTURE.md#3-modules) `DocumentSubjectProvider`, `DocumentChangeParticipant`; [§7](../04-architecture/ARCHITECTURE.md#7-posting-services-and-guards) `ReceivablePosting`, `MoneyPosting`
- [CONCURRENCY_IDEMPOTENCY §7.1](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s07-statement-sequences.md#71-lock-sets-of-ax-01ax-37) and [§7.2](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s07-statement-sequences.md#72-checks-effects-and-outcomes-of-ax-01ax-37) AX-16, AX-22, AX-31; [§7.3](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s07-statement-sequences.md#73-command-families-outside-ax-0137) NX-09, NX-12; [§9](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s08-09-guards-stale-state.md#9-stale-state) ST-01, ST-02, ST-07, ST-08; [§11](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s10-13-identity-failure-retry.md#11-separate-duplicates) BD-06; [§18](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s18-scenarios.md#18-mandatory-concurrency-and-failure-scenarios) M-15–M-19, M-37, M-38
- [PERMISSIONS_MATRIX §4](../05-security/PERMISSIONS_MATRIX.md#4-capability-catalogue) `invoice.issue`, `invoice.correct`, `writeoff.supersede`, `documents.issue`, `payment.record`; [§7.2](../05-security/PERMISSIONS_MATRIX.md#72-table-classification) rows 37, 87–89, 99–104, 113; [§7.3](../05-security/PERMISSIONS_MATRIX.md#73-special-projections) PJ-12, PJ-15, PJ-19; [§9](../05-security/PERMISSIONS_MATRIX.md#9-owner-only-denials) OD-07
- [ROUTE_CONTRACTS](../03-workflows/ROUTE_CONTRACTS.md#finance--invoices-payments-deductions-credit-other-cash-and-bank-statements-fin-pg-041pg-050) ROUTE-241, ROUTE-242, ROUTE-245, ROUTE-249, ROUTE-250; [§2](../03-workflows/ROUTE_CONTRACTS.md#2-page-views--route-001route-065) ROUTE-041–ROUTE-043; [§4](../03-workflows/ROUTE_CONTRACTS.md#4-visible-controls-without-a-route-of-their-own) "Buat Invoice" and the draft's live preview
- [INTERACTION_CONTRACTS](../03-workflows/INTERACTION_CONTRACTS.md#finance--invoices-payments-deductions-credit-other-cash-and-bank-statements-fin-pg-041pg-050) INT-241, INT-242, INT-245, INT-249, INT-250; [§2](../03-workflows/INTERACTION_CONTRACTS.md#2-shared-interactions--int-001int-020) INT-012
- [NAVIGATION_CONTRACTS §2.5](../07-ux-design/NAVIGATION_CONTRACTS.md#25-finance) NAV-041–NAV-043; [INFORMATION_ARCHITECTURE §8.5](../07-ux-design/INFORMATION_ARCHITECTURE.md#85-finance) PG-041–PG-043; [§8.7](../07-ux-design/INFORMATION_ARCHITECTURE.md#87-the-approved-screens-scr-01scr-60-and-their-pages) SCR-38, SCR-39
- [ADMIN_FLOW J-FIN-01–J-FIN-03, J-FIN-05, J-FIN-08](../07-ux-design/ADMIN_FLOW/s10-b-journeys.md#106-finance); [§11](../07-ux-design/ADMIN_FLOW/s11-13-corrections-numbering-import.md#11-corrections-and-reversals) CM-23–CM-26; [§12.3](../07-ux-design/ADMIN_FLOW/s11-13-corrections-numbering-import.md#123-the-c-10-refusal-for-an-admin); [§3](../07-ux-design/ADMIN_FLOW/s01-04-foundations.md#3-derived-interaction-requirements) UXI-14, UXI-27; [UXS-11](../07-ux-design/ADMIN_FLOW/s14-15-messages-scenarios.md#command-identity-and-outcomes), [UXS-29](../07-ux-design/ADMIN_FLOW/s14-15-messages-scenarios.md#numbering), [UXS-38](../07-ux-design/ADMIN_FLOW/s14-15-messages-scenarios.md#workflow-presentations)
- [DESIGN_SYSTEM §12.1](../07-ux-design/DESIGN_SYSTEM.md#121-status-system) the pre-payment Kuitansi and invoice families; [§14.1](../07-ux-design/DESIGN_SYSTEM.md#141-financial-data); [§14.3](../07-ux-design/DESIGN_SYSTEM.md#143-document-preview); [§4](../07-ux-design/DESIGN_SYSTEM.md#4-design-decisions) D-DS-23
- [TEST_STRATEGY §5](../08-testing/TEST_STRATEGY.md#5-domain-and-data-integrity) FX-01 1–5 and 13, FX-03; [§6](../08-testing/TEST_STRATEGY.md#6-concurrency-and-failure-proofs); [§7](../08-testing/TEST_STRATEGY.md#7-browser-end-to-end-tests-and-critical-journeys) CJ-01, CJ-04, CJ-06, CJ-15; [SECURITY_TEST_MATRIX](../08-testing/SECURITY_TEST_MATRIX.md#3-the-matrix) SM-01, SM-04, SM-25; [PERFORMANCE_TARGETS §2](../08-testing/PERFORMANCE_TARGETS.md#2-the-query-budgets) QB-08, QB-09; [§3](../08-testing/PERFORMANCE_TARGETS.md#3-the-measured-targets) PFM-01, PFM-02, PFM-20
- [GAP_REGISTER](../00-governance/GAP_REGISTER.md#gap-004--exact-arithmetic-does-not-define-managerial-financial-meaning) GAP-004; [GAP-026](../00-governance/GAP_REGISTER.md#gap-026--taxes-withheld-or-collected-at-payment-had-no-settlement-path); [GAP-029](../00-governance/GAP_REGISTER.md#gap-029--institutional-payment-processing-needs-a-kuitansi-before-payment)
- Architecture challenge — the sequences 4a, 4b, 5a, 5b of the [P11 gate record](evidence/P11_QUALITY_GATE.md#whole-system-architecture-challenge-sp-98), their assertions carried into this Task's acceptance criteria and tests
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (132): O-04; CAP-10; DOC-04–DOC-06; AC-10; AC-12; AC-16; REF-039–REF-043; REF-049; REF-057; IMG-07; OWN-01; OWN-04; BR-XD-01–BR-XD-07; BR-FIN-01–BR-FIN-17; BR-DOC-04; CALC-05–CALC-08; CALC-11; FS-13; WF-FIN-01; WF-FIN-08; AX-16; AX-22; AX-31; L-01; L-05–L-08; L-12; L-16–L-19; L-21–L-22; L-26; L-28–L-31; L-39; L-43; L-45–L-47; L-49–L-50; ROUTE-041–ROUTE-043; ROUTE-241–ROUTE-242; ROUTE-245; ROUTE-249–ROUTE-250; INT-241–INT-242; INT-245; INT-249–INT-250; C-13–C-16; C-18; C-23; C-25–C-26; C-31; C-41–C-43; C-47; C-52; C-56–C-57; NX-09; BD-02; BD-06; BD-13–BD-14; PG-041–PG-043; SCR-38–SCR-39; UXI-27; J-FIN-01–J-FIN-03; J-FIN-05; J-FIN-08; UXS-11; UXS-29; UXS-38; NAV-041–NAV-043; GAP-004; GAP-026; GAP-029

**EXPECTED MODULES / FILE AREAS:**

- the FIN module's actions SaveInvoiceDraft, IssueInvoice, VoidInvoice, ReviseInvoiceDown, IssuePrepaymentKuitansi and LinkPrepaymentKuitansi with `ShowInvoices`, `ShowInvoiceComposer` and `ShowInvoice` and their query classes (RC-02; ARCHITECTURE §4); FIN's `DocumentSubjectProvider` for DOC-04, DOC-06 and the pre-payment DOC-05, and its `DocumentChangeParticipant` part for an invoice document's void or revision (ARCHITECTURE §3); `ReceivablePosting` for `invoice_balances`, `MoneyPosting` for the dispositions, PRJ's `DemandPosting` for the invoice cap (ARCHITECTURE §7); `resources/js/Pages/FIN` with TASK-029's composer; `tests/Feature/FIN`, `tests/Concurrency/`, `tests/Reconciliation/`

**BUSINESS RULES:**

- BR-FIN-01, BR-FIN-02, CALC-08 and OWN-01: the five concepts stay distinct — issue records the agreed Sales/Transaction Value, output tax recorded apart and never operating revenue (BR-FIN-17), *Belum Ditagihkan* separately visible, no Cash-In and no active receivable until the billing act (annex 1, 2); FS-04: no Cash-In from issuing, no settlement from printing a Kuitansi
- L-16 and CALC-08: a project-level cap Σ non-void sales values ≤ confirmed demand value; value-only DP and termin invoices allowed; above-delivered invoicing flagged, never blocked (PX-06)
- L-26 and BR-DOC-04: the invoice record is the single sales-value record; DOC-06 and DOC-04 are its layouts, value, billing and receivable identical; BR-DOC-01–BR-DOC-03 and BR-SN-01: an issued version is an immutable snapshot, its number drawn at issue, its rendition derived (TASK-029)
- BR-CR-03, BR-CR-06, L-06 and L-30: a billed invoice is voided or revised down only with an explicit disposition of every attached reduction, atomically, never below the applied total; a replacement is named in the same action, never inferred, and inherits `billed_at` and `due_date`; another due date only through CM-25 with entry-error evidence (TASK-050); BR-FIN-04 and CALC-05: outstanding = billed − applications − fee and tax settlements − write-offs, never negative
- BR-DOC-04 as amended by DIR-024 and AX-31: the pre-payment Kuitansi creates no Cash-In, payment, settlement or paid state, is linked to the real payment when it occurs, stays immutable under the revision and void rules and never causes a second recognition (GAP-029); a receipt-mode Kuitansi requires the real payment (TASK-051)
- BR-XD-01 and CM-12: a return's invoice correction is its own explicit command citing the return's case, never automatic (architecture challenge 4a); BR-XD-04: generating a layout changes no stock, cash or obligation (4b); BR-XD-05: views update after each committed command; BR-XD-06 and BR-FIN-14: an opening receivable is an invoice of origin OPENING with its import identity, and L-50's opening customer credit an application source, never a live-period Cash-In (C-47; TASK-057); BR-XD-07: the races are proved by real concurrent tests
- BR-FIN-03, BR-FIN-05–BR-FIN-07, BR-FIN-09, BR-FIN-16, C-13–C-15, C-18 and C-23 bind the dispositions of ROUTE-245: a released application becomes credit traceable to payment money, never revenue (FS-08); applications stay within one company and client (C-14); a re-recorded settlement stays within its evidence's proven amount and a fee's expense stays once (L-28; L-45)
- the rest of the coverage line holds as invariants this Task's commands must not break, each built by its own workflow's Task: L-05 and L-07 (a project's company; restorations — TASK-037, TASK-048), L-08, L-18, L-19, L-39, L-43, L-47 (cost and documents — TASK-036–TASK-047), L-12 and L-17 (completion and write-off — TASK-055, TASK-050), L-21 (re-plan — TASK-034), L-22, L-29, L-46 (payments, proof and refunds — TASK-051, TASK-053), L-31 (rounding), L-49 with BD-13 (fungible duplicates), BD-02 and BD-14 (the payment and evidence duplicate warnings — TASK-051, TASK-030), L-01 (progress derived), OWN-04 (no invoice moves or reserves stock), BR-FIN-08, BR-FIN-10–BR-FIN-15, CALC-06, CALC-07, CALC-11, FS-13 (no invented formula), C-25, C-26, C-41, C-42 and C-57

**ARCHITECTURE RULES:**

- FIN is tier 6; DOC (tier 1) reads the invoice layouts' and the Kuitansi's precondition and payload through FIN's `DocumentSubjectProvider`, and an invoice document's void or revision re-evaluates requirements and revalidates completion inside DOC's transaction through `DocumentChangeParticipant` (ARCHITECTURE §3)
- `invoice_balances` has one normal writer, `ReceivablePosting`; a disposition moves the receivable, money and case guards in one transaction, or the CHECK refuses the commit (C-16; ARCHITECTURE §7); no amount is ever read from a document payload (DATABASE §10)
- the composer is TASK-029's one component; one template per layout for the preview and the renderer (D-DS-23)

**SECURITY / PERMISSION REQUIREMENTS:**

- ROUTE-041 and ROUTE-043 `finance.view` — the screens do not exist without it (J-FIN-01); ROUTE-042 and ROUTE-241 `invoice.issue` with `finance.view`; ROUTE-242 `invoice.issue`; ROUTE-245 `invoice.correct` (ADM_PLUS), with a standing write-off also `writeoff.supersede` (OWNER_ONLY) in the same command, otherwise REJECTED (OD-07); ROUTE-249 `documents.issue` (prerequisite the subject family's view capability, here `finance.view`); ROUTE-250 `payment.record`
- each correction asks its reason (IP-09; MSG-40); the Kuitansi's issue asks MSG-39 with its reason (IP-08; IP-09); the draft's props carry no stamp or signature file, no number and no field above the viewer's projection (PJ-19; WS-07)

**COMPANY SCOPE AND FIELD PROJECTION:**

- an invoice belongs to its project's company and its client is the project's client by composite key (C-32; DATABASE §4.12); the command's company is the invoice's (CS-03); a replacement matches its company and client (C-17); every reference resolves through the scope resolver (CS-04)
- rows 99–104 and 113: invoices, versions, lines, tax components, balances, billing acts and Kuitansi links FV(c); row 37: the project's commercial balance FV(c); rows 87–89 and PJ-15: DOC-04, DOC-05 and DOC-06 are the finance family — payload and rendition with `finance.view` within the document's company, metadata with `projects.view`; PJ-12: money keys of snapshots by family; PJ-19: the identity assets on the paper
- PG-041 lists by branch with every row's company where several are in scope; no total across companies for an Admin (CS-07; CS-08); a number of another company's invoice is never shown (PJ-23)

**CONCURRENCY / IDEMPOTENCY:**

- NX-09 for ROUTE-241 (LK-00, LK-01, LK-03, LK-25): draft edits by the root's `lock_version`, covering its lines, so two editors of different lines still conflict (ST-01; M-38; UXS-11)
- AX-16 for ROUTE-242, DATA-DEPENDENT: LK-00, LK-01 (the invoice's project and the projects of requirements linked to its document version), LK-03, LK-06, LK-20, LK-25, LK-30, LK-31 last; first-use rows FU-07, FU-12; re-read under lock: the invoice DRAFT, the company active, the confirmed value, NM-03's period rule; stale checks ST-01, ST-02, ST-08; refusals C-31, C-54, C-10; a second issue of the draft is CONFLICT with the issued number (BD-06); retry class A; N concurrent issues draw distinct numbers and a rolled-back one consumes none (M-15–M-19)
- AX-22 for ROUTE-245, DATA-DEPENDENT: LK-00, LK-01 (the invoice's project, the projects of requirements linked to the document version, the projects a case that takes a residual names), LK-03, LK-10, LK-20, LK-25 (both invoices), LK-26, LK-27, LK-29, LK-30, LK-31; FU-07, FU-12; re-read: the typed basis exists, the payload disposes every reduction, no standing write-off unless the Owner acts, the replacement named, the cap on the net, NM-03 for the number it draws; stale checks ST-01, ST-02, ST-07 — a payload whose dispositions no longer match the current ACTIVE reductions is CONFLICT with the current list —, ST-08; deferred: replacement billing, void with replacement; refusals C-16, C-17, C-31, C-56, C-10; identity the invoice version; retry class B; M-37: a void racing an application or settlement on the invoice — the first commits, the void after a new reduction is CONFLICT, an application after the void REJECTED (C-15)
- AX-31 for ROUTE-249 and ROUTE-250, DATA-DEPENDENT: LK-00, LK-01, LK-03, LK-25, LK-30, LK-31; FU-07, FU-12; under LK-25 both issue modes check Σ open pre-payment Kuitansi ≤ outstanding and refuse a receipt-mode Kuitansi while one is open, so different numbering periods cannot race past the check (DATABASE §11.4); at link, the amount equals the linked applications plus settlements; one link per (payment, invoice); refusals C-43, C-10; retry class A
- one `command_id` replayed returns the recorded outcome, re-authorized, and "Terbitkan Invoice" pressed twice issues once (CI-05; UXS-01; UXS-02)

**AUDIT:**

- invoices are a critical audit area (BR-XC-01): every draft save, issue, void, revision, Kuitansi issue, link and unlink is a business audit event in its command's transaction with the individual actor, business date, reason where required, before/after and links — the replaced and the replacement invoice, the basis row, every disposed reduction (BR-CR-01; LG-01); a void or revision is ADM_PLUS and one that supersedes a write-off OWNER_ONLY, both reaching the Owner's review queue (AZ-10; QS-14); the document version records its issuer and issue command (TASK-029)

**DATABASE IMPACT ASSESSMENT:**

- Schema change: NONE — uses the tables of TASK-005–TASK-009; a missing column is an executor discovery (AGENT_OPERATING_MODEL)
- Migration required: NO
- Existing data transformation: NONE
- Direct production DB access: FORBIDDEN
- Data-loss risk: NONE KNOWN

**MODULARITY / EXTENSIBILITY:**

- Module boundary affected: FIN, tier 6, with DOC (tier 1) through its two inversion contracts and PRJ through `DemandPosting` (ARCHITECTURE §3)
- Stable core kept provider-neutral: YES
- External/vendor-specific code isolated: YES — framework facilities or a small interface (ARCHITECTURE §2)
- Replaceable adapter/contract required: NO
- Provider lock-in risk: NONE beyond the Owner's baseline stack
- Future extension impact: MEDIUM — billing, payments, settlements, credit and completion act on this Task's invoice and its dispositions
- Speculative abstraction avoided: YES — no payment-terms, tax or accounting engine (WF-FIN-02; FS-13; ARCHITECTURE §1)

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

- Context7: REQUIRED — PostgreSQL deferred constraint triggers and composite foreign keys, an arbitrary-precision decimal library for `Money` (DEP-07), Inertia's props for the composer, at the installed versions
- Graphify: OPTIONAL — once the finance Tasks exist
- Browser E2E: REQUIRED — J-FIN-01 on the desktop composer and a draft on the phone; J-FIN-08's Kuitansi; CJ-04's invoices and the pre-payment Kuitansi
- Other: the two-connection harness, the controlled clock FX-08 and the numeric fixtures FX-01 (TASK-003)
- Missing safe tool behavior: AUTO-INSTALL project-locally inside this Task (AICWDF §6.3) and record it in TOOLCHAIN; a paid tier or a privileged installation STOPS for the Owner (COST_POLICY)

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: refreshed before the change when Graphify is available ([TOOLCHAIN](../00-governance/TOOLCHAIN.md)); otherwise a targeted search of FIN, DOC's subject providers and the posting services
- Pre-change questions: which commands reduce `invoice_balances` (AX-18, AX-19, AX-20, AX-21, AX-24, AX-33), which unlink a Kuitansi, which read the invoice cap and the invoice's state (completion, cancellation, QS-09, QS-15, QS-19)
- Expected affected areas: FIN's invoice and Kuitansi actions; `ReceivablePosting`, `MoneyPosting`, `DemandPosting`; PG-041–PG-043; PG-036's Kuitansi draft

**CONTEXT7:**

- Topics to verify: deferred triggers at commit for the replacement's inherited act; exact decimal arithmetic through strings; Inertia's partial reloads respecting the projection
- Version-sensitive assumptions: none from memory

**UI / UX REQUIREMENTS:**

- Follow global design rules: YES
- Indonesian default: YES
- English support: YES
- Plain-language Indonesian: YES — the issue's answer saying that no receivable is active and no cash is recorded yet (J-FIN-01); MSG-39 in the issue's confirmation; the Kuitansi's dialog saying that it records no payment (J-FIN-08); a correction's form listing every reduction and what becomes of it (ST-07); MSG-17 for an unseeded period (UXS-29)
- Language switch impact: keeps unsent input; the paper stays Indonesian (LOCALIZATION §3.4)
- Mobile-first: YES — `[desk]`: the composer on the desktop; on the phone the invoice read and a draft as Edit → Preview → Finalize (J-FIN-01; DC-11)
- Responsive: YES — the composer's widths of INT-012
- Adaptive: YES — the composer's arrangements by band (DESIGN_SYSTEM §14.3)
- Visual breathing room: REQUIRED
- Comfortable density: REQUIRED
- shadcn/ui reuse: TASK-029's component Document composer and paper, multi-line entry (PT-10), the financial position block (PT-24; §14.1), data table and dense rows (PT-05, PT-06), the confirmation with its reason (PT-26), status badges *Belum Ditagihkan*, *Ditagihkan*, *Lewat Jatuh Tempo*, *Lunas*, *Sengketa*, *Dihapuskan*, *Menunggu Pembayaran*, *Terhubung ke Pembayaran* (PT-13), the timeline (PT-18)
- Design reference: PG-041–PG-043 as INFORMATION_ARCHITECTURE §8.5 states them — the composer's form on the left and the live preview on the right (`DIR-079` CD-04); the Golden Frontend Reference for presentation only (DIR-061 SD-02)
- Primary action: "Terbitkan Invoice" on a draft; on an issued invoice the next action its state calls for — "Tagihkan" (TASK-050)
- Secondary actions: "Simpan Draf"; "Terbitkan Kuitansi untuk Proses Pembayaran"; "Tautkan Kuitansi"; "Revisi Turun" and "Batalkan Invoice" (`invoice.correct`)
- Destructive actions: none beyond the correcting void, with its reason — no control deletes an issued invoice (FS-02)
- Redundancy constraints: one draft per chain; one issue per draft (BD-06); one link per (payment, invoice); no receipt-mode Kuitansi while a pre-payment one is open (C-43)
- Loading state: PT-19; the rendition's state after issue (MSG-05; TASK-029)
- Empty state: no invoice yet — one sentence and "Buat Invoice" (PT-19)
- Error state: above the project cap REJECTED with the remaining value (L-16; MSG-02); MSG-17 for an Admin in an unseeded period; MSG-03 for a stale draft; a link whose amounts differ REJECTED with both figures and the path (J-FIN-08)

**ROUTE IMPACT:**

- ROUTE-041 (GET `/keuangan/invoice`), ROUTE-042 (GET `/keuangan/invoice/baru`, the project by its public id), ROUTE-043 (GET `/keuangan/invoice/{id}`)
- ROUTE-241 (POST `/keuangan/invoice`), ROUTE-242 (`…/{id}/terbitkan`), ROUTE-245 (`…/{id}/koreksi`), ROUTE-249 (`…/{id}/kuitansi`) and ROUTE-250 (POST `/keuangan/kuitansi/{id}/tautkan`); TASK-029's ROUTE-221 creates ROUTE-249's draft first; each answering RC-05's errors

**INTERACTION CONTRACTS:**

- INT-241, INT-242, INT-245, INT-249, INT-250, each with its *Test* as its test (IG-05); INT-012 on PG-042 and on PG-043 for a draft; INT-016 and INT-017 for CONFLICT and FAILED

**NAVIGATION CONTRACT:**

- Parent: PG-041 — *Keuangan* › *Invoice* (NAV-041); PG-042 — PG-011 · PG-041 (NAV-042); PG-043 — PG-041 · PG-044 · PG-011 (NAV-043)
- Entry: *Keuangan* › *Invoice*; the journey's *Invoice* stage and "Buat invoice"; document readiness; *Rincian proyek* › *Keuangan*; QS-09–QS-11 rows
- Back action: "Kembali" → the origin; leaving with unsent input asks MSG-35
- Cancel: "Batal" → the origin; MSG-39's "Kembali" and each reason step's "Kembali" → the form, the input kept (IP-08; IP-09)
- Success destination: the issue's result with its number in place, then "Buka Invoice" → PG-043 (NAV-042); "Terbitkan Kuitansi untuk Proses Pembayaran" → PG-036 with the Kuitansi's draft, the amount entered (NAV-043; `DIR-081` OB-02)
- Error behavior: NV-04; a record outside scope → PG-005 (IP-21)
- State preservation: PG-041's filters, sort, tab and cursor in the address (IP-18); PG-043's open section or tab in the address; unsent input kept across the re-login
- Mobile behavior: `[desk]` — the composer as DC-11's Edit → Preview → Finalize on the phone (S2-J03)

**ACCEPTANCE CRITERIA:**

1. Issuing a Rp1,000,000 invoice records a sales value of Rp1,000,000, shows *Belum Ditagihkan*, and creates no Cash-In, no active receivable, no stock change and no other obligation; an output-tax component is recorded apart and is not operating revenue (AC-10; annex 1, 13; BR-FIN-02; BR-XD-04; architecture challenge 4b).
2. An issue that would raise Σ non-void sales values above the confirmed demand value is REJECTED with the remaining value; a value-only DP or termin invoice within the cap issues; invoicing above delivered value issues and is flagged in QS-15 (C-31; L-16; PX-06).
3. Two Admins editing one draft get CONFLICT for the later save with the input kept; "Terbitkan Invoice" pressed twice or retried after a lost answer issues once with one number; N concurrent issues draw N distinct numbers (ST-01; UXS-11; BD-06; M-15–M-19; CI-05; AC-16).
4. An Admin backdating an invoice into a period that is not live and has no counter receives MSG-17 with the input kept; the issue succeeds after the Owner seeds that period (C-10; NM-03; UXS-29).
5. A billed invoice with Rp400,000 applied is voided only together with the disposition of the Rp400,000 — re-targeted to a named replacement or released to credit —, and a void or downward revision that leaves any application, settlement or write-off undisposed does not commit (annex 5; C-16; BR-CR-03; architecture challenge 5b).
6. A billed entry-error void commits only with a replacement named in the same action whose billing act is inherited from the replaced invoice's current act, with the same `billed_at` and `due_date`, so aging never restarts; a billed void without replacement must cite the return, re-confirmation or cancellation that ends the value (L-30; C-17; C-56; architecture challenge 5a).
7. A return's invoice correction is a separate command citing the return's case; nothing in the invoice changes when the goods come back until it runs (BR-XD-01; CM-12; architecture challenge 4a).
8. A settlement that the replacement's evidence cannot carry stays as an unresolved-deduction residual on the correction case, blocking its closure; a standing Owner write-off makes an Admin's void or revision REJECTED unless the Owner supersedes or re-records it in the same command (L-06; OD-07; CM-24).
9. A void or revision racing an application or settlement on the same invoice leaves one of them committed: the correction after a new reduction is CONFLICT with the current list, an application after the void REJECTED (M-37; ST-07; C-15).
10. A pre-payment Kuitansi issues only while Σ open pre-payment Kuitansi on the invoice ≤ its outstanding; it creates no Cash-In, payment, settlement or paid state, leaves the position block unchanged and shows *Menunggu Pembayaran* with "untuk proses pembayaran"; a receipt-mode Kuitansi on that invoice is REJECTED while it is open (AX-31; C-43; UXS-38; GAP-029).
11. "Tautkan Kuitansi" links only when the Kuitansi amount equals the linked payments' applications to that invoice plus its settlements — otherwise REJECTED with both figures and the revise-or-void path —, once per (payment, invoice); a later correction that lowers that total unlinks it with the cause shown (AX-31; CM-26; QS-19).
12. Without `finance.view` the invoice screens do not exist; the corrections are absent without `invoice.correct`, and the payload and rendition of DOC-04–DOC-06 reach only `finance.view` holders of the invoice's company (J-FIN-01; PJ-15; SM-25).

**TEST REQUIREMENTS:**

- Lint: TL-01
- Typecheck: TL-02
- Unit: the cap, the output-tax separation and the residual-absorbing split of a value-only termin, against FX-01 1, 2, 5 and 13 (TL-03; BR-FIN-13)
- Feature: ROUTE-241, ROUTE-242, ROUTE-245, ROUTE-249 and ROUTE-250 with each refusal their contracts name (TL-04)
- Integration: M-15–M-19 through AX-16; M-37 with synthetic applications and settlements until TASK-051 and TASK-052 exist; M-38 on a draft; the replacement's inherited act and C-56's deferred trigger; the Kuitansi cap under two numbering periods racing; the reconciliation suite after each (TL-05; TEST_STRATEGY §5, §6)
- Authorization: `invoice.issue`, `invoice.correct` (ADM_PLUS), `writeoff.supersede` (OWNER_ONLY, OD-07), `documents.issue`, `payment.record`; every screen absent without `finance.view` (TL-06; SM-04)
- Route integrity: ROUTE-041–ROUTE-043, ROUTE-241, ROUTE-242, ROUTE-245, ROUTE-249, ROUTE-250 audited (TL-07; SM-02)
- Interaction: INT-241, INT-242, INT-245, INT-249, INT-250 per IG-05 with one `command_id` replayed; INT-012 on PG-042 with an invoice at 1,025, 1,279 and 1,280 px and the tablet's toggle at 1,024 px (TL-08)
- Browser E2E: J-FIN-01 — S2-J03's composer on the desktop and the phone —; J-FIN-08; CJ-04's invoices and pre-payment Kuitansi; CJ-06's invoice revision and void (TL-09)
- Localization/language switch: both languages on PG-041–PG-043; DOC-04, DOC-05 and DOC-06 in Indonesian whatever the interface language (TL-10)
- Visual breathing room/density: TEST_STRATEGY §9 — the composer beside the form
- Responsive: TL-11
- Accessibility: TL-12
- Production build: TL-13
- Security: SM-01 for another company's invoice; SM-25 for the finance family's projection (TL-14)
- Performance: QB-08 for PG-041 and QB-09 for PG-043 with PFM-01 and PFM-02; PFM-20 with QB-13 for AX-16 and AX-31 and QB-14 for AX-22 (TL-15)
- Migration: N/A — no migration or import code in this Task; opening invoices are TASK-057's
- Other: CM-23, CM-24 and CM-26's expected end states asserted; GAP-029's no-double-recognition test (TEST_STRATEGY §5)

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

- TASK-050 — when its other dependencies are DONE and its READY gate holds

**DO NOT DO:**

- add to V1 — a capability, page, route, field, integration, document type, report or paid service — or drop, defer or narrow a V1_REQUIRED requirement; a Task that would need either raises an Owner question and waits ([DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-20)
- access the VPS, production, its data or a credential, or put a secret, real data, a host detail or production configuration in the repository ([INFRASTRUCTURE](../09-operations/INFRASTRUCTURE.md) INF-01, INF-05; SECURITY SX-01, SX-04, SX-06; [PRODUCTION_DATA_SAFETY](../00-governance/PRODUCTION_DATA_SAFETY.md))
- add AI, model or tool attribution to a commit — the Owner's configured identity only ([AGENTS.md](../../AGENTS.md#git-commit-attribution); DIR-022)
- start before the Owner's execution authorization at OG-4, or mark DONE without the required tests passing and the completion evidence ([TASK_PLAN](TASK_PLAN.md#2-rules-of-the-plan); [DEFINITION_OF_DONE](../08-testing/DEFINITION_OF_DONE.md#2-the-checklist))
- weaken, skip or delete a valid test to obtain a green run, or adopt a paid tier without the Owner's COST_POLICY decision (DOD-05; [COST_POLICY](../00-governance/COST_POLICY.md))
- create Cash-In, an active receivable, a stock change or a settlement from issuing an invoice or a Kuitansi, or count output tax as revenue (BR-FIN-02; FS-04; BR-FIN-17)
- void or revise a billed invoice without disposing every reduction, without its typed basis, or with a replacement that restarts aging (BR-CR-03; L-30; C-56)
- let an Admin's void or revision remove a standing write-off, or infer a replacement (OD-07; L-30)
- issue a receipt-mode Kuitansi while a pre-payment one is open, or link money that already has a Kuitansi (C-43; AX-31)
- hard-code a tax rate or treatment, or read an amount from a document payload (FS-13; DATABASE §10, §12)

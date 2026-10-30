# TASK-050 — Billing, receivables, dispute hold and Owner write-off

Status: APPROVED | Updated: 2026-10-20 | Owner: Planning

Approval: [APPR-016](../00-governance/DECISION_LOG.md#appr-016--p11-task-plan-tasks-and-feature-coverage-matrix-approved), explicit Owner approval on 2026-10-20 (DIR-085 OF-07) of this document as committed in the first execution session's approval commit; the approved file hash and the verified conditions are recorded there. Approval changes lifecycle only, not implementation authorization.

Written by WF-PC5-W's writer of bundle W2 of run PC-5 on 2026-10-19 and 2026-10-20 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99; the Owner approved P11 with the [Task plan](TASK_PLAN.md) at OG-4 (DIR-085 OF-07). **No Task starts before the freeze record, from which the execution authorization is in force (DIR-085 OF-09).** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** READY

**RISK CLASS:** HIGH

**EXECUTOR:** AGENT

**OBJECTIVE:**

Turn an issued invoice into an active receivable and keep it truthful: the billing act with `billed_at` and an entered due date that activates only the unpaid remainder, its entry-error correction as a linear chain, ageing from the due date in the WIB buckets, the dispute hold set and cleared with a reason, the Owner's write-off within the outstanding — available same-client credit applied first or a reason given — and its supersession when money is recovered, the Surat Permintaan Pembayaran (DOC-13) from the billing data, on PG-044 *Piutang* and PG-043's billing controls (WF-FIN-02; WF-FIN-05; AX-17; AX-24; ROUTE-243, ROUTE-244, ROUTE-246–ROUTE-248).

**WHY THIS EXISTS:**

CAP-10, AC-10 and REF-040 require explicit billing with `billed_at` and `due_date` and ageing on that basis, *Belum Ditagihkan* kept apart from active receivables (BR-FIN-02; IMG-07; O-04). A billing act corrected twice, or a replacement that restarts ageing, would misstate the debt: one root act and a linear correction chain, a replacement inheriting the replaced invoice's current act (architecture challenge 5a; BD-07; C-17; C-41; L-30). A disputed receivable keeps ageing and blocks normal completion; only the Owner disposes of uncollectable value, without Cash-In or profit, and recovered money supersedes the write-off rather than vanishing (BR-FIN-08; DIR-019; L-17; OWN-01). Payments (TASK-051), completion (TASK-055) and the opening import's receivables (TASK-057) build on this receivable.

**DEPENDS ON:**

- TASK-049 — Invoices and the pre-payment Kuitansi

**BLOCKS:**

- TASK-051 — Payments, applications and bank statement lines
- TASK-055 — Project completion and Force Complete
- TASK-057 — Opening import — preparation, dry run, sign-off and commit

**PARALLEL SAFE:** NO — billing, holds and write-offs write the invoice guard (LK-25) that invoices, payments, settlements and completion read and post

**IN SCOPE:**

- the FIN records of DATABASE §4.12 in use (§11.2, §11.4): `invoice_billing_acts` of kind BILLING and CORRECTION — the INHERITED act of a replacement written by TASK-049's AX-22, the OPENING act by TASK-057 —, `invoice_balances.current_billing_act_id`, `receivable_dispute_holds` with one uncleared hold per invoice (C-42), `receivable_writeoffs` and their supersession as a `financial_contras` row
- ROUTE-243 with INT-243 (`FIN BillInvoice`; `billing.record`; AX-17): "Tagihkan" on PG-043 and PG-044 after the confirmation (IP-08): the billing date and the entered due date — no payment-terms engine —, one root act per invoice, a second one CONFLICT (BD-07; C-41); the unpaid remainder becoming active receivable — billed value − applications − fee and tax settlements − write-offs ≥ 0 —, advance applications already reducing what billing activates, so no Cash-In is counted again (BR-FIN-04; annex 2); billing records no amount (DATABASE §11.2)
- ROUTE-244 with INT-244 (`FIN CorrectBilling`; `billing.correct`; AX-17): "Koreksi Penagihan" only with entry-error evidence, a superseding act in a linear chain, the superseded dates staying visible, QS-14 listing the change, ageing recomputed on the corrected date (CM-25)
- ageing (CALC-07; BR-FIN-15): days past the due date at the WIB day boundary in the buckets current, 1–30, 31–60, 61–90 and >90; disposed amounts leaving collectible ageing; a disputed receivable keeping its indicator and ageing; an opening receivable aged on its real due date or the `migrasi` bucket (BR-FIN-14)
- ROUTE-246 with INT-246 (`FIN SetDisputeHold · ClearDisputeHold`; `receivables.dispute`; NX-09): "Tandai Sengketa" and "Cabut Sengketa" on PG-043, each with its reason; the receivable staying active and ageing, the page saying that it blocks normal completion; the hold lapsing when the outstanding reaches zero (BR-FIN-08; L-12; L-17; QS-11)
- ROUTE-247 with INT-247 (`FIN WriteOffReceivable`; `receivables.write_off`, OWNER_ONLY; AX-24): "Hapuskan Piutang" — amount ≤ outstanding, reason, before/after —, the confirmation with its reason (IP-08; IP-09); the same client and company's unapplied credit shown, to be applied first or a reason recorded (L-17); the amount leaving active receivable and collectible ageing, kept as disposed history, with no Cash-In and no profit (DIR-019; BR-FIN-08)
- ROUTE-248 with INT-248 (`FIN SupersedeWriteOff`; `writeoff.supersede`, OWNER_ONLY; AX-24): "Terapkan dan Cabut Penghapusan" for money recovered after a write-off — credit linked to the written-off invoice and listed for the Owner (QS-12; QS-14) —, the write-off superseded by up to that amount and the payment applied to the invoice in the same action, the original kept as disposed history (L-17; WF-FIN-05)
- DOC-13 *Surat Permintaan Pembayaran* from the invoice and its billing data, issued from PG-036 through TASK-029's ROUTE-221 and ROUTE-222, with no cash effect; this Task supplies its record-bound precondition — an issued non-void invoice, normally billed — and payload through FIN's `DocumentSubjectProvider` (WF-DOC-01; ARCHITECTURE §3)
- PG-044 with NAV-044 and ROUTE-044 (SCR-40; D-IA-15): active receivables with their due state, *Belum Ditagihkan* and *Sengketa* as tabs, subtotals per company and never a total across companies for an Admin (CS-08), "Tagihkan" and "Catat Pembayaran" → PG-046 (J-FIN-03); PG-043's billing, dispute and write-off controls, each by capability

**OUT OF SCOPE:**

- the invoice, its void and revision with their dispositions — the INHERITED act included — and the pre-payment Kuitansi (TASK-049); the payment, its applications and the recovery payment's recording with `recovery_for_invoice_id` (ROUTE-251, ROUTE-252; TASK-051); settlements (TASK-052)
- ROUTE-248's recovered payment comes from TASK-051: until it exists, the supersession is tested on a synthetic payment, and TASK-051 re-runs it on real ones
- ageing on *Beranda* and in the reports (PG-006, PG-051; TASK-058, TASK-060); the Owner's consolidated receivables (PG-052; TASK-060); the completion predicate that the hold blocks (TASK-055)

**REFERENCES:**

- [WORKFLOWS §5.6](../03-workflows/WORKFLOWS/s05-06-finance.md#wf-fin-02--billing-activation-and-receivable-monitoring-piutang-desk) WF-FIN-02 and [WF-FIN-05](../03-workflows/WORKFLOWS/s05-06-finance.md#wf-fin-05--dispute-hold-and-owner-write-off-desk); [§5.5](../03-workflows/WORKFLOWS/s05-04-05-fulfillment-documents.md#wf-doc-01--generated-document-lifecycle-desk) WF-DOC-01's DOC-13 row; [§8](../03-workflows/WORKFLOWS/s08-09-corrections-indivisible.md#8-correction-reversal-and-cancellation-matrix) CM-24, CM-25, CM-27; [§9](../03-workflows/WORKFLOWS/s08-09-corrections-indivisible.md#9-indivisible-business-actions) AX-17, AX-24; [§10](../03-workflows/WORKFLOWS/s10-14-signals-decisions-traceability.md#10-derived-signals-and-action-queue) QS-09–QS-12, QS-14; [§12](../03-workflows/WORKFLOWS/s10-14-signals-decisions-traceability.md#12-planner-determined-level-1-decisions) L-12, L-17, L-30 and the coverage line's other L- rows; [§4](../03-workflows/WORKFLOWS/s01-04-foundations.md#4-actor-and-authority-matrix)
- [BUSINESS_RULES §4](../02-domain/BUSINESS_RULES.md#finance-fin) BR-FIN-01–BR-FIN-17; [§5](../02-domain/BUSINESS_RULES.md#5-calculation-semantics) CALC-05–CALC-08, CALC-11 and the [numeric annex](../02-domain/BUSINESS_RULES.md#numeric-annex-binding-examples-for-p8-fixtures-gap-004) 1, 2; [§1](../02-domain/BUSINESS_RULES.md#dates-and-backdating--dir-018) BR-DT-06; [§7](../02-domain/BUSINESS_RULES.md#7-forbidden-shortcuts) FS-08, FS-13
- [V1_SCOPE](../01-product/V1_SCOPE.md#financial-concepts-and-billing) the financial concepts; [CAP-10](../01-product/V1_SCOPE.md#must-ship); [DOC-13](../01-product/V1_SCOPE.md#selectable-document-catalog); [ACCEPTANCE_CRITERIA](../01-product/ACCEPTANCE_CRITERIA.md#product-acceptance-contracts) AC-10, AC-12, AC-16; [REFERENCE_COVERAGE](../01-product/REFERENCE_COVERAGE.md#pdf-capability-inventory) REF-039–REF-043, REF-049, REF-057; [IMG-07](../01-product/REFERENCE_COVERAGE.md#image-and-non-tabular-pdf-inventory); [OWN-01, OWN-04](../01-product/REFERENCE_COVERAGE.md#final-owner-decision-coverage)
- [DATABASE §4.12](../04-architecture/DATABASE/s04-12-fin.md#412-fin--finance); [§11.2](../04-architecture/DATABASE/s11-12-finance-tax.md#112-invoice-billing-and-receivable), [§11.4](../04-architecture/DATABASE/s11-12-finance-tax.md#114-deduction-settlements-write-offs-and-pre-payment-kuitansi); [§18](../04-architecture/DATABASE/s18-19-constraints-integrity.md#18-constraint-catalogue) C-15, C-17, C-41, C-42
- [ARCHITECTURE §6](../04-architecture/ARCHITECTURE.md#6-domain-model-value-objects-and-calculators) `AgingCalculator`; [§7](../04-architecture/ARCHITECTURE.md#7-posting-services-and-guards) `ReceivablePosting`, `MoneyPosting`
- [CONCURRENCY_IDEMPOTENCY §7.1](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s07-statement-sequences.md#71-lock-sets-of-ax-01ax-37) and [§7.2](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s07-statement-sequences.md#72-checks-effects-and-outcomes-of-ax-01ax-37) AX-17, AX-24; [§7.3](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s07-statement-sequences.md#73-command-families-outside-ax-0137) NX-09; [§11](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s10-13-identity-failure-retry.md#11-separate-duplicates) BD-07; [§18](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s18-scenarios.md#18-mandatory-concurrency-and-failure-scenarios) M-26
- [PERMISSIONS_MATRIX §4](../05-security/PERMISSIONS_MATRIX.md#4-capability-catalogue) `billing.record`, `billing.correct`, `receivables.dispute`, `receivables.write_off`, `writeoff.supersede`; [§7.2](../05-security/PERMISSIONS_MATRIX.md#72-table-classification) rows 103, 104, 110, 111, 116; [§6](../05-security/PERMISSIONS_MATRIX.md#6-company-scope-rules) CS-07, CS-08; [§9](../05-security/PERMISSIONS_MATRIX.md#9-owner-only-denials) OD-07, OD-11, OD-15
- [ROUTE_CONTRACTS](../03-workflows/ROUTE_CONTRACTS.md#finance--invoices-payments-deductions-credit-other-cash-and-bank-statements-fin-pg-041pg-050) ROUTE-243, ROUTE-244, ROUTE-246–ROUTE-248; [§2](../03-workflows/ROUTE_CONTRACTS.md#2-page-views--route-001route-065) ROUTE-044
- [INTERACTION_CONTRACTS](../03-workflows/INTERACTION_CONTRACTS.md#finance--invoices-payments-deductions-credit-other-cash-and-bank-statements-fin-pg-041pg-050) INT-243, INT-244, INT-246–INT-248
- [NAVIGATION_CONTRACTS §2.5](../07-ux-design/NAVIGATION_CONTRACTS.md#25-finance) NAV-044, NAV-043; [INFORMATION_ARCHITECTURE §8.5](../07-ux-design/INFORMATION_ARCHITECTURE.md#85-finance) PG-044, PG-043; [§4](../07-ux-design/INFORMATION_ARCHITECTURE.md#4-design-decisions) D-IA-15; [§8.7](../07-ux-design/INFORMATION_ARCHITECTURE.md#87-the-approved-screens-scr-01scr-60-and-their-pages) SCR-40
- [ADMIN_FLOW J-FIN-02, J-FIN-03, J-FIN-05](../07-ux-design/ADMIN_FLOW/s10-b-journeys.md#106-finance); [§11](../07-ux-design/ADMIN_FLOW/s11-13-corrections-numbering-import.md#11-corrections-and-reversals) CM-25, CM-27; [IP-08](../07-ux-design/ADMIN_FLOW/s05-command-model.md#ip-08--confirming-an-irreversible-command); [IP-09](../07-ux-design/ADMIN_FLOW/s05-command-model.md#ip-09--corrections-closures-and-destructive-actions)
- [DESIGN_SYSTEM §12.1](../07-ux-design/DESIGN_SYSTEM.md#121-status-system) the invoice and receivable family; [§14.1](../07-ux-design/DESIGN_SYSTEM.md#141-financial-data)
- [TEST_STRATEGY §5](../08-testing/TEST_STRATEGY.md#5-domain-and-data-integrity) FX-01 1–2, FX-03's recovery after a write-off, FX-08; [§7](../08-testing/TEST_STRATEGY.md#7-browser-end-to-end-tests-and-critical-journeys) CJ-04, CJ-14; [SECURITY_TEST_MATRIX](../08-testing/SECURITY_TEST_MATRIX.md#3-the-matrix) SM-01, SM-04, SM-25; [PERFORMANCE_TARGETS §2](../08-testing/PERFORMANCE_TARGETS.md#2-the-query-budgets) QB-07; [§3](../08-testing/PERFORMANCE_TARGETS.md#3-the-measured-targets) PFM-01, PFM-02, PFM-20
- [GAP_REGISTER](../00-governance/GAP_REGISTER.md#gap-004--exact-arithmetic-does-not-define-managerial-financial-meaning) GAP-004; [GAP-016](../00-governance/GAP_REGISTER.md#gap-016--event-time-and-business-date-may-cross-different-numbering-periods)
- Architecture challenge — the sequences 5a, 5b of the [P11 gate record](evidence/P11_QUALITY_GATE.md#whole-system-architecture-challenge-sp-98), their assertions carried into this Task's acceptance criteria and tests
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (101): O-04; CAP-10; DOC-13; AC-10; AC-12; AC-16; OS-02; REF-039–REF-043; REF-049; REF-057; IMG-06–IMG-07; OWN-01; BR-FIN-01–BR-FIN-17; CALC-05–CALC-08; CALC-11; FS-13; WF-FIN-02; WF-FIN-05; CM-24–CM-25; CM-27; AX-17; AX-24; L-05–L-08; L-12; L-16–L-18; L-22; L-26; L-28–L-31; L-43; L-45–L-47; L-50; ROUTE-044; ROUTE-243–ROUTE-244; ROUTE-246–ROUTE-248; INT-243–INT-244; INT-246–INT-248; C-13–C-15; C-18; C-23; C-25–C-26; C-41–C-42; C-47; C-52; C-57; NX-09; BD-02; BD-07; BD-14; M-26; D-IA-15; PG-044; SCR-40; J-FIN-02–J-FIN-03; J-FIN-05; NAV-044

**EXPECTED MODULES / FILE AREAS:**

- the FIN module's actions BillInvoice, CorrectBilling, SetDisputeHold, ClearDisputeHold, WriteOffReceivable and SupersedeWriteOff with `ShowReceivables` and its query class (RC-02; ARCHITECTURE §4); the `AgingCalculator` (ARCHITECTURE §6); FIN's `DocumentSubjectProvider` part for DOC-13 and its `CompletionPredicateProvider` part for the receivable and the hold (ARCHITECTURE §3); `ReceivablePosting` and `MoneyPosting` (ARCHITECTURE §7); `resources/js/Pages/FIN` for PG-044 and PG-043's controls; `tests/Feature/FIN`, `tests/Concurrency/`

**BUSINESS RULES:**

- BR-FIN-02 and BR-FIN-04 with CALC-05: the billing act activates only the unpaid remainder; outstanding = billed − applications − fee and tax settlements − write-off disposals, never negative; advances are never re-counted at billing (annex 1, 2; OWN-01; REF-039; REF-040)
- BR-FIN-15, CALC-07 and BR-DT-06: ageing is days past the due date at the WIB day boundary, in the buckets current, 1–30, 31–60, 61–90 and >90; the due date is entered at billing — no payment-terms engine (FS-13)
- L-30 and CM-25: a replacement inherits `billed_at` and `due_date`; another due date only through a correction with entry-error evidence, the superseded basis visible (C-17; C-41)
- BR-FIN-08 and L-12: a dispute hold is Admin-recordable, keeps the receivable active and ageing from the due date, settles nothing and blocks normal completion; clearing needs a reason; L-17: it lapses at zero outstanding
- BR-FIN-08, DIR-019 and L-17: only the Owner writes off — amount, reason, individual actor, before/after —, removing the amount from active receivable and collectible ageing, kept as disposed history, with no Cash-In, profit or pretended payment; available same-client credit applied first or the reason recorded; money really received is reconstructed as a real payment, never written off, and money recovered after a write-off is applied in the same action as the Owner's supersession (FS-08: no receivable silently disappears)
- BR-DOC-04 and BR-XD-04: DOC-13 reuses the billing information and creates no cash mutation
- the rest of the coverage line holds as invariants these commands must not break, each built by its own Task: BR-FIN-01, BR-FIN-03, BR-FIN-05–BR-FIN-07, BR-FIN-09–BR-FIN-14, BR-FIN-16, BR-FIN-17, CALC-06, CALC-08, CALC-11, C-13, C-14, C-18, C-23, C-25, C-26, C-52, C-57 and BD-02, BD-14 (payments, settlements, cost, tax and evidence — TASK-036, TASK-051–TASK-054, TASK-025, TASK-030); L-05–L-08, L-16, L-18, L-22, L-26, L-28, L-29, L-31, L-43, L-45–L-47 (the finance and cost family's rules); C-47 and BR-FIN-14 (an opening receivable of TASK-057 carries its OPENING act and import identity) and L-50 (opening customer credit an application source, never a live-period Cash-In — TASK-057); OWN-04 (no receivable command moves stock)

**ARCHITECTURE RULES:**

- billing records an act, never an amount: the activated receivable is derived from `invoice_balances` (DATABASE §11.2); `invoice_balances` has one normal writer, `ReceivablePosting` (ARCHITECTURE §7)
- a write-off's supersession is a `financial_contras` row on the write-off, committed with the recovered payment's application to the invoice (DATABASE §11.4); no derived position is stored as independent truth (FS-11)

**SECURITY / PERMISSION REQUIREMENTS:**

- ROUTE-044 `finance.view`; ROUTE-243 `billing.record` (ADM); ROUTE-244 `billing.correct` (ADM_PLUS); ROUTE-246 `receivables.dispute` (ADM); ROUTE-247 `receivables.write_off` and ROUTE-248 `writeoff.supersede`, OWNER_ONLY — never grantable, their controls existing only for the Owner, also through any API or replay (OD-11; RG-02; AZ-01)
- every correction, hold change and write-off asks its reason (IP-09; MSG-40); a write-off asks MSG-39 and MSG-40 (IP-08); an Owner decision recorded in error is superseded only by the Owner (OD-15; CM-27)

**COMPANY SCOPE AND FIELD PROJECTION:**

- the command's company is the invoice's (CS-03); a recovered payment applied in a supersession is of the same client and company (BR-FIN-07; C-14)
- rows 103, 104, 110, 111 and 116: balances, billing acts, write-offs, holds and contras FV(c) — the whole receivable family needs `finance.view` within scope; DOC-13 is the finance family (PJ-15)
- PG-044 by branch, every row with its company where several are in scope, subtotals per company and never a total across companies for an Admin; the Owner's consolidated receivables only on PG-052 (CS-07; CS-08; D-IA-15; J-FIN-02)

**CONCURRENCY / IDEMPOTENCY:**

- AX-17 for ROUTE-243 and ROUTE-244, STATIC: LK-00, LK-01 (the invoice's project), LK-03, LK-25; re-read under lock: the invoice issued and not void; no root act, or a correction with entry-error evidence; guard: the invoice; deferred: replacement billing; a second root act is CONFLICT (BD-07; C-41 — a partial UNIQUE key and a linear `supersedes_act_id` chain); retry class A (CONCURRENCY_IDEMPOTENCY §7.1–§7.2)
- NX-09 for ROUTE-246 (LK-00, LK-01, LK-03, LK-25): one uncleared hold per invoice (C-42); a second set is refused
- AX-24 for ROUTE-247 and ROUTE-248, STATIC: LK-00, LK-01 (the invoice's project), LK-03, LK-25, LK-26, LK-27, LK-29; re-read: amount ≤ outstanding, the available same-client credit applied first or the reason (L-17), for a supersession the recovered payment's application; facts: the write-off with its guard, or its contra with the application; refusal C-15; identity the invoice and intent; retry class A
- a hold, a write-off or a billing correction changes a completion predicate input and holds the invoice's project at LK-01, so a racing completion re-reads its predicates and fails, or the correction revalidates the completed project (M-26); one `command_id` replayed returns the recorded outcome, re-authorized (CI-05)

**AUDIT:**

- billing is a critical audit area (BR-XC-01): every billing act, correction, hold, clearing, write-off and supersession is a business audit event with the individual actor, business date, reason, before/after and links to the invoice, the superseded act or the recovered payment (BR-CR-01; LG-01); write-offs and supersessions are privileged changes LG-01 names, OWNER_ONLY; billing corrections ADM_PLUS — each reaching the Owner's review queue, with QS-14 listing a billing-act change (AZ-10; CM-25)

**DATABASE IMPACT ASSESSMENT:**

- Schema change: NONE — uses the tables of TASK-005–TASK-009; a missing column is an executor discovery (AGENT_OPERATING_MODEL)
- Migration required: NO
- Existing data transformation: NONE
- Direct production DB access: FORBIDDEN
- Data-loss risk: NONE KNOWN

**MODULARITY / EXTENSIBILITY:**

- Module boundary affected: FIN, tier 6, with DOC (tier 1) through `DocumentSubjectProvider` and PRJ through `CompletionPredicateProvider` (ARCHITECTURE §3)
- Stable core kept provider-neutral: YES
- External/vendor-specific code isolated: YES — framework facilities or a small interface (ARCHITECTURE §2)
- Replaceable adapter/contract required: NO
- Provider lock-in risk: NONE beyond the Owner's baseline stack
- Future extension impact: LOW
- Speculative abstraction avoided: YES — no payment-terms or collection engine (WF-FIN-02; ARCHITECTURE §1)

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

- Context7: REQUIRED — PostgreSQL partial UNIQUE keys and deferred constraint triggers, date arithmetic at a fixed time zone, at the installed versions
- Graphify: OPTIONAL — once the finance Tasks exist
- Browser E2E: REQUIRED — J-FIN-02 and J-FIN-05 at the desk; CJ-04's billing
- Other: the controlled clock FX-08 for the WIB day boundary and the ageing buckets (TASK-003)
- Missing safe tool behavior: AUTO-INSTALL project-locally inside this Task (AICWDF §6.3) and record it in TOOLCHAIN; a paid tier or a privileged installation STOPS for the Owner (COST_POLICY)

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: refreshed before the change when Graphify is available ([TOOLCHAIN](../00-governance/TOOLCHAIN.md)); otherwise a targeted search of FIN's receivable actions and `ReceivablePosting`
- Pre-change questions: which commands write billing acts (AX-17, AX-22's INHERITED act, AX-28's OPENING act) and which read the current act and the hold (ageing, completion, QS-09–QS-11)
- Expected affected areas: FIN's billing, hold and write-off actions; PG-044; PG-043's controls; DOC-13's subject

**CONTEXT7:**

- Topics to verify: date-only columns and the WIB day boundary; partial UNIQUE keys answered as CONFLICT
- Version-sensitive assumptions: none from memory

**UI / UX REQUIREMENTS:**

- Follow global design rules: YES
- Indonesian default: YES
- English support: YES
- Plain-language Indonesian: YES — the page saying that a disputed receivable stays active, keeps ageing and blocks normal completion (J-FIN-05); the write-off form showing the client's available credit to apply first; MSG-39 for "Tagihkan" and "Hapuskan Piutang"
- Language switch impact: keeps unsent input
- Mobile-first: YES — `[desk]`; on the phone the lookup of a client's receivables (J-FIN-02; NV-06)
- Responsive: YES
- Adaptive: N/A — one list layout, readable on the phone
- Visual breathing room: REQUIRED
- Comfortable density: REQUIRED
- shadcn/ui reuse: the data table and dense rows (PT-05, PT-06), tabs *Aktif*, *Belum Ditagihkan*, *Sengketa*, the financial position block (PT-24), the business-date control (PT-25), the confirmation with its reason (PT-26), status badges *Belum Ditagihkan*, *Ditagihkan*, *Lewat Jatuh Tempo*, *Lunas*, *Sengketa*, *Dihapuskan* (PT-13), the timeline (PT-18)
- Design reference: PG-044 as INFORMATION_ARCHITECTURE §8.5 and D-IA-15 state it; the Golden Frontend Reference for presentation only (DIR-061 SD-02)
- Primary action: "Tagihkan" on an unbilled invoice
- Secondary actions: "Catat Pembayaran" → PG-046; "Tandai Sengketa", "Cabut Sengketa"; "Koreksi Penagihan"; the Owner's "Hapuskan Piutang" and "Terapkan dan Cabut Penghapusan"
- Destructive actions: none beyond the Owner's write-off, a disposition kept as history with its confirmation and reason
- Redundancy constraints: one root billing act per invoice (BD-07); one uncleared hold per invoice (C-42); no grand total across companies for an Admin
- Loading state: PT-19
- Empty state: no active receivable, nothing unbilled, no dispute — one sentence each (PT-19)
- Error state: a second billing act CONFLICT (MSG-04); a write-off above the outstanding REJECTED with the figure (MSG-02); a correction without entry-error evidence refused at its field

**ROUTE IMPACT:**

- ROUTE-044 (PG-044's view, GET `/keuangan/piutang`)
- ROUTE-243 (POST `/keuangan/invoice/{id}/tagihkan`), ROUTE-244 (`…/koreksi-tagihan`), ROUTE-246 (`…/sengketa`), ROUTE-247 (`…/hapuskan`), ROUTE-248 (`…/cabut-penghapusan`); DOC-13 through TASK-029's ROUTE-221 and ROUTE-222; each answering RC-05's errors

**INTERACTION CONTRACTS:**

- INT-243, INT-244, INT-246–INT-248, each with its *Test* as its test (IG-05); INT-007 and INT-008 for PG-044's tabs, filters and continuation; INT-016 and INT-017 for CONFLICT and FAILED

**NAVIGATION CONTRACT:**

- Parent: PG-044 — *Keuangan* › *Piutang* · PG-006 (NAV-044); the controls on PG-043 under NAV-043
- Entry: *Keuangan* › *Piutang*; *Beranda*'s QS-10 and QS-11 rows; PG-043
- Back action: "Kembali" → the origin when opened from a decision row
- Cancel: each dialog's "Kembali" → the form, the input kept (IP-08; IP-09)
- Success destination: the billing act, hold or write-off in place
- Error behavior: NV-04 — a rejected command in place, the input kept
- State preservation: tabs, filters, sort and cursor in the address (IP-18; NV-05)
- Mobile behavior: `[desk]` — readable on the phone

**ACCEPTANCE CRITERIA:**

1. Billing a Rp1,000,000 invoice with no payment records `billed_at` and the entered due date and activates Rp1,000,000; after a pre-billing advance of Rp400,000 applied, billing activates only Rp600,000 and records no second Cash-In (AC-10; annex 1, 2; BR-FIN-04).
2. A second billing act on the same invoice is CONFLICT, and one `command_id` replayed bills once (CI-05; AC-16); a correction without entry-error evidence is refused; a correct correction supersedes the act in a linear chain, the superseded dates stay visible, ageing is recomputed and QS-14 lists the change (BD-07; C-41; CM-25; architecture challenge 5a).
3. A replacement invoice named in a void-and-replace carries an inherited act with the replaced invoice's current `billed_at` and `due_date`, and its ageing continues from that due date, never from the replacement's issue (L-30; C-17; architecture challenge 5a).
4. Ageing places a receivable in current, 1–30, 31–60, 61–90 or >90 days past its due date at the WIB day boundary, written-off amounts leave collectible ageing, and a disputed receivable keeps its indicator and its ageing (BR-FIN-15; CALC-07; FX-08).
5. A dispute hold set with a reason keeps the receivable active and blocks normal completion; one uncleared hold per invoice; clearing needs a reason; the hold lapses when the outstanding reaches zero (BR-FIN-08; C-42; L-17).
6. Only the Owner writes off, at most the outstanding, with reason and before/after: the amount leaves active receivable and collectible ageing and stays as disposed history with no Cash-In and no profit; with unapplied credit of the same client and company, the write-off commits only after that credit is applied or with the recorded reason (AX-24; L-17; OD-11; OWN-01).
7. Money recovered after a write-off is applied to the invoice in the same command as the Owner's supersession of the write-off by up to that amount; the original write-off stays visible as disposed history (AX-24; L-17; WF-FIN-05).
8. A void or downward revision of a billed, partly paid invoice that carries a standing write-off commits only when the Owner supersedes or re-records the write-off in the same command, so no reduction is left undisposed and the write-off never vanishes (AX-22; AX-24; OD-07; C-16; architecture challenge 5b).
9. An Admin, an API path or a replayed request never reaches "Hapuskan Piutang" or "Terapkan dan Cabut Penghapusan": the controls are absent and the routes answer MSG-10 or MSG-09 (OD-11; AZ-11; SM-04).
10. DOC-13 issues from an issued non-void invoice with its billing data and creates no cash mutation (BR-DOC-04; WF-DOC-01).
11. PG-044 shows each company's subtotal and never a total across companies to an Admin; `finance.view` is needed to see any of it (CS-08; D-IA-15; SM-25).

**TEST REQUIREMENTS:**

- Lint: TL-01
- Typecheck: TL-02
- Unit: the `AgingCalculator` across the WIB boundary and the buckets; the activated remainder after advances (TL-03; FX-01 1–2; FX-08)
- Feature: ROUTE-243, ROUTE-244, ROUTE-246–ROUTE-248 with each refusal (TL-04)
- Integration: a hold or write-off racing a completion (M-26); a supersession with a synthetic recovered payment until TASK-051 exists; FX-03's recovery after a write-off (TL-05; TEST_STRATEGY §5)
- Authorization: `billing.record`, `billing.correct` (ADM_PLUS), `receivables.dispute`; `receivables.write_off` and `writeoff.supersede` OWNER_ONLY, denied to an Admin and through any replay (TL-06; SM-04)
- Route integrity: ROUTE-044, ROUTE-243, ROUTE-244, ROUTE-246–ROUTE-248 audited (TL-07; SM-02)
- Interaction: INT-243, INT-244, INT-246–INT-248 per IG-05 with one `command_id` replayed (TL-08)
- Browser E2E: J-FIN-02 and J-FIN-05; CJ-04's billing and CJ-14's receivables and ageing (TL-09)
- Localization/language switch: both languages on PG-044 and PG-043's controls; DOC-13 in Indonesian (TL-10)
- Visual breathing room/density: TEST_STRATEGY §9
- Responsive: TL-11
- Accessibility: TL-12
- Production build: TL-13
- Security: SM-01 for another company's invoice; SM-25 for the receivable figures without `finance.view` (TL-14)
- Performance: QB-07 for PG-044 with PFM-01 and PFM-02; PFM-20 with QB-13 for AX-17 and AX-24 (TL-15)
- Migration: N/A — no migration or import code in this Task; the OPENING act is TASK-057's
- Other: CM-25's expected end state asserted (TEST_STRATEGY §5)

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

- TASK-051 — when its other dependencies are DONE and its READY gate holds

**DO NOT DO:**

- add to V1 — a capability, page, route, field, integration, document type, report or paid service — or drop, defer or narrow a V1_REQUIRED requirement; a Task that would need either raises an Owner question and waits ([DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-20)
- access the VPS, production, its data or a credential, or put a secret, real data, a host detail or production configuration in the repository ([INFRASTRUCTURE](../09-operations/INFRASTRUCTURE.md) INF-01, INF-05; SECURITY SX-01, SX-04, SX-06; [PRODUCTION_DATA_SAFETY](../00-governance/PRODUCTION_DATA_SAFETY.md))
- add AI, model or tool attribution to a commit — the Owner's configured identity only ([AGENTS.md](../../AGENTS.md#git-commit-attribution); DIR-022)
- start before the Owner's execution authorization at OG-4, or mark DONE without the required tests passing and the completion evidence ([TASK_PLAN](TASK_PLAN.md#2-rules-of-the-plan); [DEFINITION_OF_DONE](../08-testing/DEFINITION_OF_DONE.md#2-the-checklist))
- weaken, skip or delete a valid test to obtain a green run, or adopt a paid tier without the Owner's COST_POLICY decision (DOD-05; [COST_POLICY](../00-governance/COST_POLICY.md))
- record an amount in a billing act, count an advance again at billing, or let a receivable go negative (BR-FIN-04; DATABASE §11.2)
- add a payment-terms engine, compute a due date, or restart ageing on a replacement (WF-FIN-02; L-30)
- give a write-off or its supersession to an Admin, a job or any API path, or write off money that was actually received (OD-11; BR-FIN-08)
- show an Admin a receivable total across companies (CS-08)

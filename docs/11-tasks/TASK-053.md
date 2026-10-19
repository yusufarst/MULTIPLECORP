# TASK-053 — Customer credit and refunds

Status: REVIEW | Updated: 2026-10-20 | Owner: Planning

Written by WF-PC5-W's writer of bundle W2 of run PC-5 on 2026-10-19 and 2026-10-20 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99 — a REVIEW draft of P11, approved, or not, with the [Task plan](TASK_PLAN.md) at OG-4. **No Task starts before the Owner's execution authorization at OG-4.** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** BLOCKED — waiting on P11Q-02

**RISK CLASS:** HIGH

**EXECUTOR:** AGENT

**OBJECTIVE:**

Show and spend customer credit truthfully: per client and company the credit derived from unapplied payment money, applications released by corrections and opening credit, each traceable to its source; its application to another invoice of the same client and company; and the refund — a Cash-Out disbursement with its transfer evidence that consumes one application of evidenced credit, final while the refund stands —, with an unbacked refund's shortfall shown on its case, on PG-048 *Kredit Pelanggan* (WF-FIN-06; AX-23; ROUTE-256).

**WHY THIS EXISTS:**

CAP-10 and AC-10 require excess money to be explicit credit or refund, never profit (BR-FIN-05; FS-08; annex 3; REF-042), and a returned delivery after payment releases money that becomes credit, refundable on request (CM-13). A refund must never be created unbacked, and its consumed application must stay binding until the refund itself is contra'd, so no payment correction can free money that has already left (L-46; C-44; DATABASE §11.3). Completion (TASK-055) needs project-originated credit dispositioned and every unbacked refund resolved (WF-PRJ-03 predicate 9), and the opening import (TASK-057) carries opening credit as an application source.

**DEPENDS ON:**

- TASK-051 — Payments, applications and bank statement lines
- TASK-052 — Deduction settlements and contras
- the Owner's answer to P11Q-02 recorded and the narrow amendment it authorizes approved ([TASK_PLAN §8](TASK_PLAN.md#8-owner-questions)) — BLOCKED until then

**BLOCKS:**

- TASK-055 — Project completion and Force Complete
- TASK-057 — Opening import — preparation, dry run, sign-off and commit

**PARALLEL SAFE:** NO — the refund writes the application-source and money-fact guards (LK-26, LK-27) and the case's shortfall counters that payments, contras and applications post

**IN SCOPE:**

- customer credit as derived, never stored (CALC-06; DATABASE §11.3): the `capacity_amount` of the client's payments and opening credits in one company — zero on any source carrying a refund shortfall —, shown per client and company with its sources: unapplied payment money, applications released by corrections, opening credit with its import identity and signed opening evidence (BR-FIN-05; BR-XD-06; L-50); credit linked to a written-off invoice listed for the Owner (QS-12; TASK-050)
- "Terapkan ke Invoice" on PG-048: TASK-051's ROUTE-252 (`payment.apply`; AX-19) to an issued invoice of the same client and company (BR-FIN-03; BR-FIN-07)
- ROUTE-256 with INT-256 (`FIN RefundCredit`; `refund.record`; AX-23): "Kembalikan Dana" with its transfer evidence (TRANSFER_PROOF, uploaded first; IP-16), the confirmation with its reason (IP-08; IP-09): a REFUND disbursement — Cash-Out, never negative Cash-In, never cost — and the ACTIVE REFUND-target applications that back it, committed together, fully backed at creation (C-44); the credit evidenced — a refund of an unevidenced payment's credit REJECTED (L-29); Σ applications ≤ the source's real money (C-13; annex 3)
- the refund's finality (L-46): its consumed application leaves ACTIVE only through its typed exits — re-recorded from the re-recorded payment (CM-19), its source contra'd with the unbackable part becoming the refund's shortfall on the correction case, or the refund's own contra (CM-38; TASK-052's ROUTE-260) —; no reallocation supersedes, releases or re-targets it (CM-21), the application's row offering no move and saying why (ADMIN_FLOW §11)
- the unbacked refund: its shortfall shown on its case and on PG-048, blocking the case's closure and normal completion until a later real payment of that client and company is applied to the refund disbursement, resolving it, or the disbursement is contra'd as an entry error (WF-FIN-06; QS-07; QS-14; C-44)
- money received into the wrong company (CM-20, BR-FIN-07 a): the receiving company's refund through this route, the fresh payment to the right one through TASK-051's ROUTE-251, each its own command (ADMIN_FLOW §11)
- PG-048 with NAV-048 and ROUTE-048 (SCR-44; J-FIN-06): credit per client and company with its sources, refunds and their state, "Terapkan ke Invoice" and "Kembalikan Dana" by capability

**OUT OF SCOPE:**

- payments, applications, reallocations and the payment's contra (ROUTE-251–ROUTE-254; TASK-051); the refund's contra and its control (ROUTE-260; TASK-052)
- opening customer credit's import and its correction (CM-33; ROUTE-214; TASK-057)
- the third disposition of project-originated credit that WF-FIN-06 and completion predicate 9 name — "hold with reason" —, for which no route, interaction, capability or column exists — held for Planning; apply and refund are in scope
- the completion predicate that reads credit dispositions and unbacked refunds (TASK-055); the cashflow reports (CALC-14; TASK-060)

**REFERENCES:**

- [WORKFLOWS §5.6](../03-workflows/WORKFLOWS/s05-06-finance.md#wf-fin-06--customer-credit-and-refund-desk) WF-FIN-06, with [WF-FIN-03](../03-workflows/WORKFLOWS/s05-06-finance.md#wf-fin-03--payment-receipt-and-application-pembayaran-both); [§5.7](../03-workflows/WORKFLOWS/s05-07-08-completion-migration.md#wf-prj-03--project-completion-penyelesaian-desk) WF-PRJ-03's predicate 9; [§6](../03-workflows/WORKFLOWS/s06-07-subflows-partial.md#6-reusable-subflows) SF-APPLY, SF-CONTRA, SF-CASE; [§8](../03-workflows/WORKFLOWS/s08-09-corrections-indivisible.md#8-correction-reversal-and-cancellation-matrix) CM-13, CM-19–CM-21, CM-38; [§9](../03-workflows/WORKFLOWS/s08-09-corrections-indivisible.md#9-indivisible-business-actions) AX-19, AX-20, AX-23, AX-33; [§10](../03-workflows/WORKFLOWS/s10-14-signals-decisions-traceability.md#10-derived-signals-and-action-queue) QS-07, QS-12, QS-14; [§12](../03-workflows/WORKFLOWS/s10-14-signals-decisions-traceability.md#12-planner-determined-level-1-decisions) L-29, L-46, L-50 and the coverage line's other L- rows
- [BUSINESS_RULES §4](../02-domain/BUSINESS_RULES.md#finance-fin) BR-FIN-01–BR-FIN-17; [§5](../02-domain/BUSINESS_RULES.md#5-calculation-semantics) CALC-05–CALC-08, CALC-11 and the [numeric annex](../02-domain/BUSINESS_RULES.md#numeric-annex-binding-examples-for-p8-fixtures-gap-004) 3; [§6](../02-domain/BUSINESS_RULES.md#6-cross-domain-interaction-rules) BR-XD-06; [§7](../02-domain/BUSINESS_RULES.md#7-forbidden-shortcuts) FS-08, FS-13
- [V1_SCOPE](../01-product/V1_SCOPE.md#financial-concepts-and-billing) the financial concepts; [CAP-10](../01-product/V1_SCOPE.md#must-ship); [ACCEPTANCE_CRITERIA](../01-product/ACCEPTANCE_CRITERIA.md#product-acceptance-contracts) AC-10; [REFERENCE_COVERAGE](../01-product/REFERENCE_COVERAGE.md#pdf-capability-inventory) REF-042
- [DATABASE §4.12](../04-architecture/DATABASE/s04-12-fin.md#412-fin--finance); [§11.3](../04-architecture/DATABASE/s11-12-finance-tax.md#113-payments-applications-credit-and-refunds) credit, refunds and finality; [§11.5](../04-architecture/DATABASE/s11-12-finance-tax.md#115-expenses-disbursements-and-other-cash); [§18](../04-architecture/DATABASE/s18-19-constraints-integrity.md#18-constraint-catalogue) C-13, C-14, C-44, C-45
- [ARCHITECTURE §7](../04-architecture/ARCHITECTURE.md#7-posting-services-and-guards) `MoneyPosting`
- [CONCURRENCY_IDEMPOTENCY §7.1](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s07-statement-sequences.md#71-lock-sets-of-ax-01ax-37) and [§7.2](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s07-statement-sequences.md#72-checks-effects-and-outcomes-of-ax-01ax-37) AX-23; [§5](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s04-06-locks-envelope.md#5-data-dependent-lock-set-protocol); [§18](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s18-scenarios.md#18-mandatory-concurrency-and-failure-scenarios) M-14
- [PERMISSIONS_MATRIX §4](../05-security/PERMISSIONS_MATRIX.md#4-capability-catalogue) `refund.record`, `payment.apply`, `finance.view`; [§7.2](../05-security/PERMISSIONS_MATRIX.md#72-table-classification) rows 105, 107, 108, 112, 114, 117, 121; [§7.3](../05-security/PERMISSIONS_MATRIX.md#73-special-projections) PJ-08, PJ-16
- [ROUTE_CONTRACTS](../03-workflows/ROUTE_CONTRACTS.md#finance--invoices-payments-deductions-credit-other-cash-and-bank-statements-fin-pg-041pg-050) ROUTE-256, ROUTE-252; [§2](../03-workflows/ROUTE_CONTRACTS.md#2-page-views--route-001route-065) ROUTE-048
- [INTERACTION_CONTRACTS](../03-workflows/INTERACTION_CONTRACTS.md#finance--invoices-payments-deductions-credit-other-cash-and-bank-statements-fin-pg-041pg-050) INT-256, INT-252
- [NAVIGATION_CONTRACTS §2.5](../07-ux-design/NAVIGATION_CONTRACTS.md#25-finance) NAV-048; [INFORMATION_ARCHITECTURE §8.5](../07-ux-design/INFORMATION_ARCHITECTURE.md#85-finance) PG-048; [§8.7](../07-ux-design/INFORMATION_ARCHITECTURE.md#87-the-approved-screens-scr-01scr-60-and-their-pages) SCR-44
- [ADMIN_FLOW J-FIN-06](../07-ux-design/ADMIN_FLOW/s10-b-journeys.md#106-finance); [§11](../07-ux-design/ADMIN_FLOW/s11-13-corrections-numbering-import.md#11-corrections-and-reversals) CM-20, CM-21, CM-38; [IP-08](../07-ux-design/ADMIN_FLOW/s05-command-model.md#ip-08--confirming-an-irreversible-command); [IP-09](../07-ux-design/ADMIN_FLOW/s05-command-model.md#ip-09--corrections-closures-and-destructive-actions); [IP-16](../07-ux-design/ADMIN_FLOW/s06-09-session-forms-lists.md#ip-16--evidence-upload)
- [TEST_STRATEGY §5](../08-testing/TEST_STRATEGY.md#5-domain-and-data-integrity) FX-01 3, FX-03's refunds and reallocation, FX-04's Rp1 refund-shortfall attack; [§6](../08-testing/TEST_STRATEGY.md#6-concurrency-and-failure-proofs); [§7](../08-testing/TEST_STRATEGY.md#7-browser-end-to-end-tests-and-critical-journeys) CJ-04; [SECURITY_TEST_MATRIX](../08-testing/SECURITY_TEST_MATRIX.md#3-the-matrix) SM-01, SM-04, SM-25; [PERFORMANCE_TARGETS §3](../08-testing/PERFORMANCE_TARGETS.md#3-the-measured-targets) PFM-01, PFM-02, PFM-07, PFM-20
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (66): BR-FIN-01–BR-FIN-17; CALC-05–CALC-08; CALC-11; FS-13; WF-FIN-06; CM-13; AX-23; L-05–L-08; L-12; L-16–L-18; L-22; L-26; L-28–L-31; L-43; L-45–L-47; L-50; ROUTE-048; ROUTE-256; INT-256; C-13–C-15; C-18; C-23; C-25–C-26; C-41–C-42; C-47; C-52; C-57; BD-02; BD-14; PG-048; SCR-44; J-FIN-06; NAV-048

**EXPECTED MODULES / FILE AREAS:**

- the FIN module's action RefundCredit with `ShowCustomerCredit` and its query class deriving credit (RC-02; ARCHITECTURE §3–§4); `MoneyPosting` for the refund's disbursement guard, its backing applications and the case's shortfall counter (ARCHITECTURE §7); `resources/js/Pages/FIN` for PG-048; `tests/Feature/FIN`, `tests/Concurrency/`, `tests/Reconciliation/`

**BUSINESS RULES:**

- BR-FIN-05, CALC-06 and FS-08: customer credit = unapplied payment remainders + explicit correction credits + opening customer credit, per client and company; every spend of it is an application; a refund is a disbursement consuming a specific application; overpayment is never profit and credit never revenue (annex 3; REF-042)
- BR-FIN-03 and BR-FIN-07: credit is applied only to another invoice of the same client and company; the same money can never be both refunded and applied (annex 3)
- BR-FIN-11 and CALC-14's Cash-Out: a refund is a Cash-Out disbursement with its reference and evidence — never negative Cash-In, never cost (WF-FIN-06)
- L-46 and C-44: a refund-consumed application is final while its disbursement stands and is released only by that disbursement's contra; a payment contra may move it only to the re-recorded payment; an unbacked refund is an explicit case residual resolved only by a later real payment applied to it or by the disbursement's contra; L-29: an unevidenced payment's credit is not refundable
- BR-XD-06 and L-50: opening customer credit is a migration fact per client and company with its import identity and signed opening evidence — an application source, never a live-period Cash-In (C-47; TASK-057)
- CM-13: after a return following payment, the released money becomes credit, refundable on request; WF-FIN-06's rule: project-originated credit needs a disposition before normal completion
- the rest of the coverage line holds as invariants these commands must not break, each built by its own Task: BR-FIN-01, BR-FIN-02, BR-FIN-04, BR-FIN-06, BR-FIN-08–BR-FIN-10, BR-FIN-12–BR-FIN-17, CALC-05, CALC-07, CALC-08, CALC-11, FS-13, C-15, C-18, C-23, C-25, C-26, C-41, C-42, C-52, C-57, BD-02 and BD-14 (invoices, billing, payments, settlements, cost, tax and evidence — TASK-049–TASK-052, TASK-054, TASK-036, TASK-025, TASK-030); L-05–L-08, L-12, L-16–L-18, L-22, L-26, L-28, L-30, L-31, L-43, L-45, L-47 (the family's other rules)

**ARCHITECTURE RULES:**

- credit is derived — the source's `capacity_amount` — and never stored as credit (DATABASE §11.3; FS-11); the refund's money-fact guard requires backed + shortfall = its net amount with no shortfall at creation, so a refund can never be created unbacked
- a REFUND-target application leaves ACTIVE only through the transition trigger's three typed exits (DATABASE §11.3); `MoneyPosting` is the one normal writer of the source and money-fact guards (ARCHITECTURE §7)

**SECURITY / PERMISSION REQUIREMENTS:**

- ROUTE-048 `finance.view`; "Terapkan ke Invoice" `payment.apply` (TASK-051's route); ROUTE-256 `refund.record` (ADM_PLUS), its confirmation with its reason (IP-08; IP-09; MSG-40); a control is absent without its capability (AZ-01)

**COMPANY SCOPE AND FIELD PROJECTION:**

- credit, its sources and its refunds belong to one company and one client (C-14; CS-03; CS-04); a refund from the wrong receiving company is that company's fact, the fresh payment the right company's (BR-FIN-07 a; CM-20)
- rows 105, 107, 108, 112, 114 and 117: payments, source balances, applications, opening credits, disbursements and money-fact balances FV(c); row 121 and PJ-16: the case's unbacked-refund counter by family; the transfer evidence of the finance family with `evidence.view` and `finance.view` (PJ-08)
- PG-048 per client and company, the scope filter inside the query and no total across companies for an Admin (CS-07; CS-08)

**CONCURRENCY / IDEMPOTENCY:**

- AX-23 for ROUTE-256, DATA-DEPENDENT (§5's protocol): LK-00, LK-01 (the projects of the invoices the consumed source has been applied to), LK-03, LK-26, LK-29; re-read under lock: the credit evidenced (L-29), the source's capacity ≥ the amount; facts: the REFUND disbursement with its guard — born in the action — and the REFUND applications; refusals C-13, C-44; identity the credit and intent; retry class B (CONCURRENCY_IDEMPOTENCY §7.1–§7.2)
- M-14: a refund racing a contra or an application on the same source — the first commits; a refund after the contra commits only within the capacity left, otherwise REJECTED (C-13); an application that resolves a shortfall racing the refund's contra; one `command_id` replayed refunds once, re-authorized (CI-05; AC-16)

**AUDIT:**

- every refund is a business audit event with the individual actor, business date, reason, transfer evidence, before/after and links to the credit's source and its applications (BR-XC-01; BR-CR-01; LG-01); refunds are ADM_PLUS and reach the Owner's review queue, as do unbacked-refund residuals (AZ-10; QS-14; PERMISSIONS_MATRIX §10)

**DATABASE IMPACT ASSESSMENT:**

- Schema change: NONE — uses the tables of TASK-005–TASK-009; a missing column is an executor discovery (AGENT_OPERATING_MODEL)
- Migration required: NO
- Existing data transformation: NONE
- Direct production DB access: FORBIDDEN
- Data-loss risk: NONE KNOWN

**MODULARITY / EXTENSIBILITY:**

- Module boundary affected: FIN, tier 6 (ARCHITECTURE §3)
- Stable core kept provider-neutral: YES
- External/vendor-specific code isolated: YES — no bank or payment integration exists in V1 (API_AND_INTEGRATIONS §3)
- Replaceable adapter/contract required: NO
- Provider lock-in risk: NONE beyond the Owner's baseline stack
- Future extension impact: LOW
- Speculative abstraction avoided: YES — credit is a derivation, not a ledger of its own (DATABASE §11.3; ARCHITECTURE §1)

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

- Context7: REQUIRED — PostgreSQL CHECKs over guard rows and transition triggers, the framework's database transactions, at the installed versions
- Graphify: OPTIONAL — once the finance Tasks exist
- Browser E2E: REQUIRED — J-FIN-06 at the desk; CJ-04's overpayment, credit and refund
- Other: the two-connection harness and FX-04's Rp1 refund-shortfall attack (TASK-003)
- Missing safe tool behavior: AUTO-INSTALL project-locally inside this Task (AICWDF §6.3) and record it in TOOLCHAIN; a paid tier or a privileged installation STOPS for the Owner (COST_POLICY)

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: refreshed before the change when Graphify is available ([TOOLCHAIN](../00-governance/TOOLCHAIN.md)); otherwise a targeted search of FIN's money posting and credit queries
- Pre-change questions: which commands change a source's capacity or a refund's backing (AX-18–AX-20, AX-22, AX-23, AX-24, AX-33) and which read credit (completion predicate 9, QS-12)
- Expected affected areas: FIN's refund action and credit query; `MoneyPosting`; PG-048

**CONTEXT7:**

- Topics to verify: CHECKs that combine backing and shortfall on one guard row; transition triggers with typed exits
- Version-sensitive assumptions: none from memory

**UI / UX REQUIREMENTS:**

- Follow global design rules: YES
- Indonesian default: YES
- English support: YES
- Plain-language Indonesian: YES — each credit's sources in business words; an unbacked refund remainder shown on its case as the reason it cannot close (J-FIN-06); a refund-consumed application's row saying why it cannot move (L-46)
- Language switch impact: keeps unsent input
- Mobile-first: YES — `[desk]`, readable on the phone (NV-06)
- Responsive: YES
- Adaptive: N/A — one list layout, readable on the phone
- Visual breathing room: REQUIRED
- Comfortable density: REQUIRED
- shadcn/ui reuse: the data table and dense rows (PT-05, PT-06), the evidence upload (IP-16), the confirmation with its reason (PT-26), the financial position block (PT-24), the timeline (PT-18)
- Design reference: PG-048 as INFORMATION_ARCHITECTURE §8.5 and J-FIN-06 state it; the Golden Frontend Reference for presentation only (DIR-061 SD-02)
- Primary action: "Terapkan ke Invoice" for a credit row
- Secondary actions: "Kembalikan Dana"
- Destructive actions: none — a wrong refund is corrected by its contra (CM-38; TASK-052)
- Redundancy constraints: one credit figure per client and company, derived; no refund of unevidenced credit (L-29)
- Loading state: PT-19
- Empty state: no customer credit — one sentence (PT-19)
- Error state: a refund of unevidenced credit or above the capacity REJECTED with the figure (MSG-02); MSG-07 with "Coba Lagi" for an unknown outcome

**ROUTE IMPACT:**

- ROUTE-048 (PG-048's view, GET `/keuangan/kredit-pelanggan`)
- ROUTE-256 (POST `/keuangan/kredit-pelanggan/{id}/kembalikan`); ROUTE-252 (TASK-051) from "Terapkan ke Invoice"; each answering RC-05's errors; the transfer evidence's file by ROUTE-228 first (TASK-030)

**INTERACTION CONTRACTS:**

- INT-256 with its *Test* as its test (IG-05); INT-252 on PG-048; INT-013 for the evidence; INT-016 and INT-017 for CONFLICT and FAILED

**NAVIGATION CONTRACT:**

- Parent: *Keuangan* › *Lainnya* (NAV-048)
- Entry: *Keuangan* › *Lainnya*; QS-12 rows
- Back action: "Kembali" → PG-041, *Keuangan*'s first page
- Cancel: the dialog's "Kembali" → the form, the input kept (IP-08; IP-09)
- Success destination: the refund or application in place
- Error behavior: NV-04 — a rejected command in place, the input kept
- State preservation: filters, sort, tab and cursor in the address (IP-18; NV-05)
- Mobile behavior: `[desk]` — readable on the phone

**ACCEPTANCE CRITERIA:**

1. After a Rp1,200,000 payment against Rp1,000,000 outstanding, PG-048 shows Rp200,000 credit for that client and company traced to that payment; refunding it is one application of Rp200,000 to a refund disbursement — Cash-Out —, and the same Rp200,000 can never also be applied to an invoice (annex 3; BR-FIN-05; BR-FIN-03).
2. A refund of credit from a payment without proof, or above the source's capacity, is REJECTED with the figure; a refund commits fully backed or not at all (L-29; C-13; C-44).
3. A refund's consumed application cannot be reallocated, released or re-targeted while the refund stands; only the refund's own contra returns it to credit, and the application's row offers no move and says why (L-46; CM-21; CM-38; ADMIN_FLOW §11).
4. A payment contra that leaves a refund not fully backed records the shortfall on the correction case, which blocks the case's closure and normal completion until a later real payment of that client and company is applied to the refund or the refund is contra'd; the Rp1 contra never frees capacity for a new application (L-46; C-44; DATABASE §11.3; FX-04).
5. Credit is applied only to an issued invoice of the same client and company; never to another company's invoice (BR-FIN-07; C-14).
6. A refund racing a contra or application on the same source leaves the source's capacity never above its real money; one `command_id` replayed refunds once (M-14; CI-05; AC-16).
7. Credit and refunds show nothing without `finance.view`, and "Kembalikan Dana" is absent without `refund.record` (AZ-01; SM-04; SM-25).

**TEST REQUIREMENTS:**

- Lint: TL-01
- Typecheck: TL-02
- Unit: the derived credit per client and company, zero on a source with a shortfall, against FX-01 3 (TL-03)
- Feature: ROUTE-256 with each refusal; the credit view's figures (TL-04)
- Integration: M-14 with real sessions, both orders; FX-04's Rp1 refund-shortfall attack and its legitimate-shortfall contrast; FX-03's refunds and reallocation; a refund's typed exits each once; the reconciliation suite after each (TL-05; TEST_STRATEGY §5, §6)
- Authorization: `refund.record` as a separately granted ADM_PLUS capability; `payment.apply` for the application (TL-06; SM-04)
- Route integrity: ROUTE-048 and ROUTE-256 audited (TL-07; SM-02)
- Interaction: INT-256 per IG-05, with one `command_id` replayed (TL-08)
- Browser E2E: J-FIN-06; CJ-04's credit and refund (TL-09)
- Localization/language switch: both languages on PG-048 (TL-10)
- Visual breathing room/density: TEST_STRATEGY §9
- Responsive: TL-11
- Accessibility: TL-12
- Production build: TL-13
- Security: SM-01 for another company's credit; SM-25 (TL-14)
- Performance: PFM-01 and PFM-02 for PG-048; PFM-07 for QS-12's bounded statement; PFM-20 with QB-14 for AX-23 (TL-15)
- Migration: N/A — no migration or import code in this Task; opening credits are TASK-057's
- Other: CM-13's credit after a return following payment asserted with TASK-048's case (TEST_STRATEGY §5)

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
- store credit as a balance of its own, count it as revenue or profit, or refund it as negative Cash-In or cost (FS-08; FS-11; WF-FIN-06)
- create a refund that is not fully backed, or refund an unevidenced payment's credit (C-44; L-29)
- free a refund-consumed application by a reallocation, or release refund backing beyond what real money supports (L-46)
- add a control or route for "hold with reason" before Planning states it (RC-06; OE-20)

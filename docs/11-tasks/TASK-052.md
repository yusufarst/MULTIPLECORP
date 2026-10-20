# TASK-052 — Deduction settlements and contras

Status: APPROVED | Updated: 2026-10-20 | Owner: Planning

Approval: [APPR-016](../00-governance/DECISION_LOG.md#appr-016--p11-task-plan-tasks-and-feature-coverage-matrix-approved), explicit Owner approval on 2026-10-20 (DIR-085 OF-07) of this document as committed in the first execution session's approval commit; the approved file hash and the verified conditions are recorded there. Approval changes lifecycle only, not implementation authorization.

Written by WF-PC5-W's writer of bundle W2 of run PC-5 on 2026-10-19 and 2026-10-20 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99; the Owner approved P11 with the [Task plan](TASK_PLAN.md) at OG-4 (DIR-085 OF-07). **No Task starts before the freeze record, from which the execution authorization is in force (DIR-085 OF-09).** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** BLOCKED — waiting on P11Q-02

**RISK CLASS:** HIGH

**EXECUTOR:** AGENT

**OBJECTIVE:**

Settle the deductions a client or intermediary takes from a payment — a verified intermediary fee, tax withheld or collected — as evidenced, non-cash reductions of the invoice's receivable, each anchored to its payment and its evidence identity, the fee expensed once and a tax expensed only through its configured, evidenced treatment; and record the contra of a settlement, expense, disbursement or refund with the disposition of everything that depends on it, on PG-047 *Potongan* and PG-049's correction (WF-FIN-04; SF-SETTLE; SF-CONTRA; AX-21; AX-33; ROUTE-255, ROUTE-260).

**WHY THIS EXISTS:**

Government treasurers, SIPLAH operators and other intermediaries remit net of fees and taxes with official evidence; an Owner write-off would misstate a real settlement and an open balance would block completion for the core client base (GAP-026; DIR-020; DIR-024 D-1; BR-FIN-09; BR-FIN-16). A deduction settled without its payment or proof, twice, or beyond its evidence would inflate the settled value (architecture challenge 6d; BD-08; C-18), a SIPLAH fee must never come from a formula (9a; FS-13; CAP-12; AC-15), and a replaced evidence must re-evaluate the settlements that depend on it (20a; L-41). Every immutable money fact is corrected only by its contra, both visible (BR-CR-02; CM-22; CM-38).

**DEPENDS ON:**

- TASK-051 — Payments, applications and bank statement lines
- the Owner's answer to P11Q-02 recorded, with what it calls for done — a step run or a narrow amendment approved ([TASK_PLAN §8](TASK_PLAN.md#8-owner-questions)) — BLOCKED until then

**BLOCKS:**

- TASK-053 — Customer credit and refunds
- TASK-054 — Expenses, disbursements and other cash
- TASK-055 — Project completion and Force Complete
- TASK-057 — Opening import — preparation, dry run, sign-off and commit

**PARALLEL SAFE:** NO — settlements and contras write the invoice, application-source, money-fact and evidence guards (LK-25–LK-27, LK-29) and the case's residual counters that payments, refunds and other cash post

**IN SCOPE:**

- the FIN records in use (DATABASE §4.12, §11.4): `deduction_settlements` of class FEE or TAX with direction, specific type, payment, invoice, evidence and the treatment rule applied, their states ACTIVE, RESIDUAL and CONTRAED (C-60), one ACTIVE per (payment, invoice, class, specific type) (C-22; BD-08); `financial_contras`; the fee's FEE_DEDUCTION expense and a tax's TAX_TREATMENT expense under a SETTLEMENT_TO_EXPENSE rule (DATABASE §12)
- ROUTE-255 with INT-255 (`FIN RecordDeductionSettlement`; `settlement.record` with `finance.view` and `evidence.view`; AX-21): "Catat Potongan" from *Keuangan* › *Potongan*, a QS-13 row, the payment or the invoice — fee or tax, specific type, amount, invoice, payment, its evidence (FEE_ADVICE, TAX_WITHHOLDING_PROOF or TAX_COLLECTION_PROOF) and business date —, the confirmation with its reason (IP-08; IP-09); the payment of the same remittance existing, the invoice issued and non-void with outstanding ≥ the amount, the same client and company; Σ settlements per evidence identity ≤ its proven amount across all payments and termins (L-45; C-18), payment + Σ its settlements ≤ the remittance advice's claimed gross, collected output tax ≤ the invoice's tax component, Σ reductions ≤ billed (C-22; BR-FIN-04); the receivable reduced with no Cash-In, no payment and the gross value unchanged
- the fee: a project-attributed expense exactly once under all of DIR-020's conditions, the fee category closed to manual entry, its expense ≤ the evidence's proven amount however often the settlement is re-recorded (BR-FIN-09; C-23; annex 6); the tax: not a payment, write-off, revenue or automatic expense — an expense only through an explicit configured, evidenced treatment recorded once, its type recorded and reported apart (BR-FIN-16; BR-FIN-17; annex 13)
- the guards of WF-FIN-04: no settlement without evidence — a claimed but unevidenced deduction stays outstanding and listed as QS-13 = claimed − evidenced; an unexplained short payment cannot be labelled a fee or a tax and the form has no field for it — it stays outstanding, is disputed or is written off by the Owner (QS-14's exception view; J-FIN-04)
- ROUTE-260 with INT-260 (`FIN RecordContra`; `finance.contra`; AX-33): "Batalkan Potongan (Kontra)" on PG-047 — the settlement contra'd, a fee's expense with it unless it moves exactly once with the re-recorded settlement, then re-recorded with the correct evidence (CM-22; C-60) —; and "Batalkan Catatan (Kontra)" on PG-049 for a disbursement, expense or refund recorded in error or whose transfer came back — its dependent attributions contra'd in the same action, a refund's consumed application released to credit only by its own contra, which resolves any shortfall first (CM-38; L-46; C-44); Σ contras ≤ the original (C-45); both records visible (SF-CONTRA; BR-CR-02)
- the residual settlement's exit: RESIDUAL → CONTRAED when its evidence resolves it — re-recorded on the invoice that evidence covers by a new ACTIVE settlement citing the same evidence in the same command, or contra'd when the issuer's evidence shows it was refunded or cancelled —, the case's residual falling by its amount (DATABASE §11.4; C-60) — the effects and guards; and, under the Owner's answer to P11Q-02 (a), once the narrow amendment adds its row and capability, the route that carries it, built here once the answer and its row are approved, which comes before this Task is READY ([TASK_PLAN §8](TASK_PLAN.md#8-owner-questions)); under option (b), if the Owner takes it out of V1, the route is not built, as the answer records (DIR-083 OE-20)
- the settlements' side of an evidence replacement: FIN's `DocumentChangeParticipant` re-evaluates, inside DOC's transaction, the settlements that cite the replaced evidence, under its cap (L-41; C-18; ARCHITECTURE §3; architecture challenge 20a); the replacement itself is TASK-030's ROUTE-231
- the SIPLAH channel's net settlement as one fee settlement on its evidence, never a formula (CAP-12; AC-15; REF-038; FS-13; annex 6); a SIPLAH order with no external connection (API_AND_INTEGRATIONS IC-14)
- PG-047 with NAV-047 and ROUTE-047 (SCR-43; J-FIN-04): the settlements by payment and invoice, their evidence and state, the claimed-minus-evidenced figure, "Catat Potongan" and the contra

**OUT OF SCOPE:**

- the payment, its claimed deductions and their supersession, its contra (CM-19; ROUTE-251, ROUTE-254, ROUTE-262; TASK-051), and the moves of anchored settlements in a reallocation (L-28; TASK-051) — they use this Task's settlement transitions
- the turning of settlements RESIDUAL by an invoice void or revision (CM-24; TASK-049); refunds (TASK-053); expenses, disbursements and other Cash-In themselves and PG-049 (TASK-054) — ROUTE-260's control on PG-049 and its contra of their facts are exercised when they exist, on synthetic facts until then
- the residual settlement's resolution route if the Owner's answer to P11Q-02 takes it out of V1 (option (b)), and, until its row and capability exist, any control for it — never built without its route (RC-06); which capability it needs — `settlement.record` (PERMISSIONS_MATRIX §4; ARCHITECTURE §3's `ResolveResidualSettlement`) or ROUTE-260's `finance.contra` (AX-33) — is the amendment's to state
- the tax configuration and its SETTLEMENT_TO_EXPENSE rules (`tax.configure`; TASK-025); the evidence upload and versioning (TASK-030)

**REFERENCES:**

- [WORKFLOWS §5.6](../03-workflows/WORKFLOWS/s05-06-finance.md#wf-fin-04--deduction-settlement-verified-intermediary-fee-and-tax-withheldcollected-desk) WF-FIN-04, with [WF-FIN-07](../03-workflows/WORKFLOWS/s05-06-finance.md#wf-fin-07--expenses-disbursements-and-other-cash-desk)'s corrections; [§5.5](../03-workflows/WORKFLOWS/s05-04-05-fulfillment-documents.md#wf-doc-02--external-and-uploaded-documents-both) WF-DOC-02's replacement; [§6](../03-workflows/WORKFLOWS/s06-07-subflows-partial.md#6-reusable-subflows) SF-SETTLE, SF-CONTRA, SF-EVIDENCE, SF-CASE; [§8](../03-workflows/WORKFLOWS/s08-09-corrections-indivisible.md#8-correction-reversal-and-cancellation-matrix) CM-19, CM-21, CM-22, CM-24, CM-38; [§9](../03-workflows/WORKFLOWS/s08-09-corrections-indivisible.md#9-indivisible-business-actions) AX-21, AX-33; [§10](../03-workflows/WORKFLOWS/s10-14-signals-decisions-traceability.md#10-derived-signals-and-action-queue) QS-07, QS-13, QS-14; [§12](../03-workflows/WORKFLOWS/s10-14-signals-decisions-traceability.md#12-planner-determined-level-1-decisions) L-06, L-28, L-41, L-43, L-45, L-46 and the coverage line's other L- rows
- [BUSINESS_RULES §4](../02-domain/BUSINESS_RULES.md#finance-fin) BR-FIN-01–BR-FIN-17; [§2](../02-domain/BUSINESS_RULES.md#2-correction-taxonomy) BR-CR-02; [§5](../02-domain/BUSINESS_RULES.md#5-calculation-semantics) CALC-05–CALC-08, CALC-11 and the [numeric annex](../02-domain/BUSINESS_RULES.md#numeric-annex-binding-examples-for-p8-fixtures-gap-004) 6, 13; [§7](../02-domain/BUSINESS_RULES.md#7-forbidden-shortcuts) FS-04, FS-13
- [V1_SCOPE](../01-product/V1_SCOPE.md#must-ship) CAP-10, CAP-12; [ACCEPTANCE_CRITERIA](../01-product/ACCEPTANCE_CRITERIA.md#product-acceptance-contracts) AC-10, AC-15; [REFERENCE_COVERAGE](../01-product/REFERENCE_COVERAGE.md#pdf-capability-inventory) REF-038; [API_AND_INTEGRATIONS §2](../06-api-performance/API_AND_INTEGRATIONS.md#2-contract-for-a-future-external-integration) IC-14
- [DATABASE §4.12](../04-architecture/DATABASE/s04-12-fin.md#412-fin--finance); [§11.4](../04-architecture/DATABASE/s11-12-finance-tax.md#114-deduction-settlements-write-offs-and-pre-payment-kuitansi), [§11.3](../04-architecture/DATABASE/s11-12-finance-tax.md#113-payments-applications-credit-and-refunds), [§11.5](../04-architecture/DATABASE/s11-12-finance-tax.md#115-expenses-disbursements-and-other-cash); [§12](../04-architecture/DATABASE/s11-12-finance-tax.md#12-tax-architecture); [§18](../04-architecture/DATABASE/s18-19-constraints-integrity.md#18-constraint-catalogue) C-15, C-18, C-22, C-23, C-44, C-45, C-60
- [ARCHITECTURE §3](../04-architecture/ARCHITECTURE.md#3-modules) `DocumentChangeParticipant`; [§7](../04-architecture/ARCHITECTURE.md#7-posting-services-and-guards) `MoneyPosting`, `ReceivablePosting`, `EvidencePosting`, `CostPosting`
- [CONCURRENCY_IDEMPOTENCY §7.1](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s07-statement-sequences.md#71-lock-sets-of-ax-01ax-37) and [§7.2](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s07-statement-sequences.md#72-checks-effects-and-outcomes-of-ax-01ax-37) AX-21, AX-33; [§7.3](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s07-statement-sequences.md#73-command-families-outside-ax-0137) NX-14; [§9](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s08-09-guards-stale-state.md#9-stale-state) ST-07; [§11](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s10-13-identity-failure-retry.md#11-separate-duplicates) BD-08, BD-14; [§18](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s18-scenarios.md#18-mandatory-concurrency-and-failure-scenarios) M-14, M-37
- [PERMISSIONS_MATRIX §4](../05-security/PERMISSIONS_MATRIX.md#4-capability-catalogue) `settlement.record`, `finance.contra`; [§7.2](../05-security/PERMISSIONS_MATRIX.md#72-table-classification) rows 78, 94, 106, 109, 116, 117, 121; [§7.3](../05-security/PERMISSIONS_MATRIX.md#73-special-projections) PJ-08, PJ-16
- [ROUTE_CONTRACTS](../03-workflows/ROUTE_CONTRACTS.md#finance--invoices-payments-deductions-credit-other-cash-and-bank-statements-fin-pg-041pg-050) ROUTE-255, ROUTE-260; [§2](../03-workflows/ROUTE_CONTRACTS.md#2-page-views--route-001route-065) ROUTE-047
- [INTERACTION_CONTRACTS](../03-workflows/INTERACTION_CONTRACTS.md#finance--invoices-payments-deductions-credit-other-cash-and-bank-statements-fin-pg-041pg-050) INT-255, INT-260
- [NAVIGATION_CONTRACTS §2.5](../07-ux-design/NAVIGATION_CONTRACTS.md#25-finance) NAV-047; [INFORMATION_ARCHITECTURE §8.5](../07-ux-design/INFORMATION_ARCHITECTURE.md#85-finance) PG-047; [§8.7](../07-ux-design/INFORMATION_ARCHITECTURE.md#87-the-approved-screens-scr-01scr-60-and-their-pages) SCR-43
- [ADMIN_FLOW J-FIN-04](../07-ux-design/ADMIN_FLOW/s10-b-journeys.md#106-finance); [§11](../07-ux-design/ADMIN_FLOW/s11-13-corrections-numbering-import.md#11-corrections-and-reversals) CM-22, CM-38; [IP-08](../07-ux-design/ADMIN_FLOW/s05-command-model.md#ip-08--confirming-an-irreversible-command); [IP-09](../07-ux-design/ADMIN_FLOW/s05-command-model.md#ip-09--corrections-closures-and-destructive-actions); [IP-16](../07-ux-design/ADMIN_FLOW/s06-09-session-forms-lists.md#ip-16--evidence-upload)
- [TEST_STRATEGY §5](../08-testing/TEST_STRATEGY.md#5-domain-and-data-integrity) FX-01 6 and 13, FX-03's tax and fee on one payment, voids with settlements and duplicate evidence, FX-04's residual settlements; [§7](../08-testing/TEST_STRATEGY.md#7-browser-end-to-end-tests-and-critical-journeys) CJ-08; [SECURITY_TEST_MATRIX](../08-testing/SECURITY_TEST_MATRIX.md#3-the-matrix) SM-01, SM-04, SM-25; [PERFORMANCE_TARGETS §3](../08-testing/PERFORMANCE_TARGETS.md#3-the-measured-targets) PFM-01, PFM-02, PFM-07, PFM-20
- [GAP_REGISTER](../00-governance/GAP_REGISTER.md#gap-026--taxes-withheld-or-collected-at-payment-had-no-settlement-path) GAP-026; [GAP-004](../00-governance/GAP_REGISTER.md#gap-004--exact-arithmetic-does-not-define-managerial-financial-meaning)
- Architecture challenge — the sequences 6d, 9a, 20a of the [P11 gate record](evidence/P11_QUALITY_GATE.md#whole-system-architecture-challenge-sp-98), their assertions carried into this Task's acceptance criteria and tests
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (93): O-04; CAP-10; CAP-12; AC-10; AC-15; OS-02; REF-016; REF-038; IMG-07; OWN-01; BR-FIN-01–BR-FIN-17; CALC-05–CALC-08; CALC-11; FS-13; WF-FIN-04; SF-CONTRA; SF-SETTLE; CM-19; CM-21–CM-22; CM-24; CM-38; AX-21; AX-33; L-05–L-08; L-12; L-16–L-18; L-22; L-26; L-28–L-31; L-43; L-45–L-47; L-50; ROUTE-047; ROUTE-255; ROUTE-260; INT-255; INT-260; C-13–C-15; C-18; C-22–C-23; C-25–C-26; C-41–C-42; C-47; C-52; C-57; C-60; BD-02; BD-08; BD-14; M-14; M-37; PG-047; SCR-43; J-FIN-04; NAV-047; GAP-004; GAP-026; GAP-066

**EXPECTED MODULES / FILE AREAS:**

- the FIN module's actions RecordDeductionSettlement and RecordContra with `ShowDeductions` and its query class (RC-02; ARCHITECTURE §3–§4); FIN's `DocumentChangeParticipant` part for settlements; `MoneyPosting` for settlement states and money-fact balances, `ReceivablePosting`, DOC's `EvidencePosting` for citations and expensed amounts, CST's posting of the system-created fee and tax expenses (ARCHITECTURE §7); `resources/js/Pages/FIN` for PG-047; `tests/Feature/FIN`, `tests/Concurrency/`, `tests/Reconciliation/`

**BUSINESS RULES:**

- BR-FIN-09 and DIR-020: a fee deduction is settled by one audited record when the intermediary verifiably remitted net of it — real and evidenced, the gross value unchanged, Cash-In only for money received, non-cash, a project expense exactly once, its actor, evidence and amount kept; no arbitrary deduction, invented formula, cashback, discount or write-off rides on it
- BR-FIN-16, BR-FIN-17 and CALC-11: a tax withheld or collected is settled once per payment, invoice and tax type on its official evidence; not a payment, write-off, revenue or automatic expense, an expense only through an explicit configured, evidenced treatment; reported apart for reconciliation with the company's tax records (GAP-026)
- L-28 and L-45: settlements are anchored to their payment and their evidence identity — one per payment, invoice, class and specific type; Σ per evidence identity ≤ its proven amount; payment + Σ ≤ claimed gross; collected output tax ≤ the invoice's tax component; BR-FIN-04 and CALC-05: the receivable falls by the settlement, never below zero
- L-06 and CM-24: settlements a void or revision cannot re-record stay explicit residuals on the case, blocking its closure until evidence resolves them; L-41: replacing an evidence re-evaluates the settlements that depended on it
- BR-CR-02, SF-CONTRA and CM-38: an immutable money fact is corrected only by its contra, at most the original net of prior contras, both visible, its dependent applications, settlements and attributions disposed in the same action; L-46: a refund's consumed application returns to credit only through the refund's contra
- FS-13 and CAP-12: no SIPLAH cashback or fee formula; SIPLAH values stay manual metadata and its net settlement is a fee settlement on evidence (AC-15; REF-038; IC-14); FS-04: no settlement from printing a Kuitansi
- the rest of the coverage line holds as invariants these commands must not break, each built by its own Task: BR-FIN-01–BR-FIN-03, BR-FIN-05–BR-FIN-08, BR-FIN-10–BR-FIN-15, CALC-06–CALC-08, C-13, C-14, C-25, C-26, C-41, C-42, C-52, C-57, BD-02 and BD-14 (payments, billing, cost, tax and evidence — TASK-049–TASK-051, TASK-054, TASK-036, TASK-025, TASK-030); L-05, L-07, L-08, L-12, L-16–L-18, L-22, L-26, L-29–L-31, L-43, L-47 (the family's other rules); L-50 and C-47 (opening facts carry their import identity — TASK-057); CM-19 (the payment's contra — TASK-051 — disposing settlements through this Task's transitions)

**ARCHITECTURE RULES:**

- a settlement creates no Cash-In row and no application; it moves the invoice's settled amount, the payment's `settled_amount` and the evidence's `cited_amount` together, or the CHECKs refuse the commit (DATABASE §11.4); settlement states change only through the transition trigger, each at most once (C-60)
- the fee expense category is written only by the settlement's posting, never by a manual expense (C-23; ARCHITECTURE §7)
- DOC (tier 1) reaches the settlements' re-evaluation only through FIN's `DocumentChangeParticipant`, inside DOC's transaction (ARCHITECTURE §3)

**SECURITY / PERMISSION REQUIREMENTS:**

- ROUTE-047 and ROUTE-255 `settlement.record` (ADM_PLUS) with `finance.view` and `evidence.view`; ROUTE-260 `finance.contra` (ADM_PLUS); each asks its confirmation and reason (IP-08; IP-09; MSG-40)
- the cited evidence of the finance family needs `evidence.view` with `finance.view` within scope (PJ-08); a duplicate evidence outside scope discloses nothing (DP-13)

**COMPANY SCOPE AND FIELD PROJECTION:**

- a settlement's payment, invoice and evidence belong to one company and client (C-14; C-32; CS-04); the command's company is the invoice's (CS-03)
- row 109: settlements FV(c), their cited evidence per PJ-08; rows 116–117: contras and money-fact balances FV(c); row 106: claimed deductions FV(c); row 94: evidence balances by the evidence's family; row 78: the fee's expense CV(c) — the expense side seen only with `cost.view`; row 121 and PJ-16: the case's residual counters by family
- PG-047 by branch, the scope filter inside the query, no total across companies for an Admin (CS-07; CS-08)

**CONCURRENCY / IDEMPOTENCY:**

- AX-21 for ROUTE-255, STATIC: LK-00, LK-01 (the invoice's project), LK-03, LK-25, LK-26, LK-29; re-read under lock: the invoice's outstanding ≥ the amount, settled + amount ≤ the claimed deductions, the evidence's capacity, collected tax ≤ the invoice's output tax; a second ACTIVE settlement of the same (payment, invoice, class, type) is CONFLICT and one beyond the evidence REJECTED C-18 (BD-08); refusals C-15, C-18, C-22, C-23; retry class A (CONCURRENCY_IDEMPOTENCY §7.1–§7.2)
- AX-33 for ROUTE-260, DATA-DEPENDENT (§5's protocol): LK-00, LK-01 (the project of the contra'd fact and of every dependent it disposes; the case's project), LK-03, LK-10, LK-21 (a disbursement against a purchase, LR-10), LK-25, LK-26, LK-27, LK-29; re-read: Σ contras ≤ the original, the payload disposing every dependent — a stale list is CONFLICT (ST-07) —, a refund's contra resolving shortfall first and releasing backing only up to its amount; conditional settlement transitions; refusals C-44, C-45, C-60; identity the original fact and intent; retry class B
- two settlements citing one evidence identity from different payments meet at LK-29: the second is REJECTED beyond the proven amount (C-18); a settlement racing the invoice's void meets it at LK-25 (M-37); a refund's contra racing an application that resolves its shortfall follows M-14; one `command_id` replayed returns the recorded outcome (CI-05)

**AUDIT:**

- every settlement and contra is a business audit event with the individual actor, business date, reason, evidence references, before/after and links to the payment, the invoice, the evidence and, for a contra, the original (BR-XC-01; BR-CR-01; LG-01); settlements and contras are ADM_PLUS and reach the Owner's review queue — deduction settlements are a QS-14 source by name (AZ-10; PERMISSIONS_MATRIX §10)

**DATABASE IMPACT ASSESSMENT:**

- Schema change: NONE — uses the tables of TASK-005–TASK-009; a missing column is an executor discovery (AGENT_OPERATING_MODEL)
- Migration required: NO
- Existing data transformation: NONE
- Direct production DB access: FORBIDDEN
- Data-loss risk: NONE KNOWN

**MODULARITY / EXTENSIBILITY:**

- Module boundary affected: FIN, tier 6, posting CST's system-created expenses and citing DOC's evidence (ARCHITECTURE §3)
- Stable core kept provider-neutral: YES
- External/vendor-specific code isolated: YES — no SIPLAH, bank or tax integration exists in V1 (API_AND_INTEGRATIONS §3)
- Replaceable adapter/contract required: NO — a future SIPLAH adapter sits behind ARCHITECTURE §13's interface (IC-14)
- Provider lock-in risk: NONE beyond the Owner's baseline stack
- Future extension impact: LOW
- Speculative abstraction avoided: YES — no tax algorithm, deduction engine or general ledger (FS-13; ARCHITECTURE §1)

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

- Context7: REQUIRED — PostgreSQL transition triggers with column grants and partial UNIQUE keys, the framework's database transactions, at the installed versions
- Graphify: OPTIONAL — once the finance Tasks exist
- Browser E2E: REQUIRED — J-FIN-04 at the desk; CJ-08's SIPLAH net settlement
- Other: the numeric fixtures FX-01 6 and 13 and FX-04's residual settlements (TASK-003)
- Missing safe tool behavior: AUTO-INSTALL project-locally inside this Task (AICWDF §6.3) and record it in TOOLCHAIN; a paid tier or a privileged installation STOPS for the Owner (COST_POLICY)

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: refreshed before the change when Graphify is available ([TOOLCHAIN](../00-governance/TOOLCHAIN.md)); otherwise a targeted search of FIN's settlements, the money posting service and DOC's evidence balances
- Pre-change questions: which commands change a settlement's state (AX-19, AX-20, AX-21, AX-22, AX-33) and which move an evidence's cited and expensed amounts (AX-18, AX-21, AX-29, NX-08, NX-10, NX-14)
- Expected affected areas: FIN's settlement and contra actions; `MoneyPosting`, `ReceivablePosting`, `EvidencePosting`; PG-047; the contra on PG-049

**CONTEXT7:**

- Topics to verify: transition triggers that allow each state change once; CHECKs on several guard rows in one transaction
- Version-sensitive assumptions: none from memory

**UI / UX REQUIREMENTS:**

- Follow global design rules: YES
- Indonesian default: YES
- English support: YES
- Plain-language Indonesian: YES — the form stating that without evidence no settlement exists and the claimed amount stays outstanding (J-FIN-04); no field for a short payment; the contra's form stating what it reverses and what stays (ADMIN_FLOW §11)
- Language switch impact: keeps unsent input
- Mobile-first: YES — `[desk]`, readable on the phone (NV-06)
- Responsive: YES
- Adaptive: N/A — one form layout, readable on the phone
- Visual breathing room: REQUIRED
- Comfortable density: REQUIRED
- shadcn/ui reuse: the form layout (PT-09), the evidence upload and choice (IP-16), the confirmation with its reason (PT-26), data table and dense rows (PT-05, PT-06), the financial position block (PT-24), the timeline (PT-18)
- Design reference: PG-047 as INFORMATION_ARCHITECTURE §8.5 and J-FIN-04 state it; the Golden Frontend Reference for presentation only (DIR-061 SD-02)
- Primary action: "Catat Potongan"
- Secondary actions: "Batalkan Potongan (Kontra)"; on PG-049 "Batalkan Catatan (Kontra)"
- Destructive actions: none beyond the correcting contra with its reason — no control deletes a settlement (FS-02)
- Redundancy constraints: one ACTIVE settlement per (payment, invoice, class, type) (BD-08); no settlement without evidence; no manual fee expense
- Loading state: PT-19
- Empty state: no settlement yet — one sentence; the claimed-but-unevidenced figure where it exists (QS-13)
- Error state: beyond the evidence's proven amount REJECTED with the figure (C-18; MSG-02); a duplicate settlement CONFLICT (MSG-04); a stale disposition list CONFLICT (MSG-03)

**ROUTE IMPACT:**

- ROUTE-047 (PG-047's view, GET `/keuangan/potongan`)
- ROUTE-255 (POST `/keuangan/potongan`) and ROUTE-260 (POST `/keuangan/kontra`, from PG-047 and PG-049); each answering RC-05's errors

**INTERACTION CONTRACTS:**

- INT-255 and INT-260, each with its *Test* as its test (IG-05); INT-016 and INT-017 for CONFLICT and FAILED; the reason step of IG-03 on both

**NAVIGATION CONTRACT:**

- Parent: *Keuangan* › *Potongan* · PG-043 (NAV-047)
- Entry: *Keuangan* › *Potongan*; QS-13 rows; the payment or the invoice (J-FIN-04)
- Back action: "Kembali" → the origin
- Cancel: the reason step's "Kembali" → the form, the input kept (IP-09; NV-02)
- Success destination: PG-047 with the settlement recorded (NAV-047); the contra in place, both visible
- Error behavior: NV-04 — validation at the field, a rejected command in place, the input kept
- State preservation: unsent input kept across the re-login; MSG-35 before leaving
- Mobile behavior: `[desk]` — readable on the phone

**ACCEPTANCE CRITERIA:**

1. A SIPLAH gross of Rp10,000,000 remitted as Rp9,700,000 with a verified fee of Rp300,000 records Cash-In 9,700,000 applied and one fee settlement of 300,000 that closes the receivable and attributes 300,000 project expense once; no formula computes the fee (annex 6; BR-FIN-09; FS-13; architecture challenge 9a; AC-15).
2. A treasurer's transfer of Rp9,850,000 against Rp11,100,000 billed with evidence of Rp1,100,000 collected and Rp150,000 withheld records two tax settlements leaving outstanding zero, payment + settlements = 11,100,000, and no settlement is revenue, payment or expense (annex 13; BR-FIN-16).
3. A settlement without its payment of the same remittance, without evidence, or beyond the evidence's proven amount across payments and termins does not commit; a second ACTIVE settlement of the same (payment, invoice, class, type) is CONFLICT (SF-SETTLE; BD-08; C-18; C-22; architecture challenge 6d).
4. Payment + Σ its settlements never exceed the remittance advice's claimed gross, and collected output tax never exceeds the invoice's tax component (C-22).
5. A fee is expensed exactly once, at most its evidence, however often its settlement is contra'd and re-recorded; a manual expense of the fee category is refused (C-23; BR-FIN-09).
6. A claimed but unevidenced deduction stays outstanding and listed as claimed − evidenced; a short payment cannot be recorded as a fee or tax (WF-FIN-04; QS-13; J-FIN-04).
7. A wrong or duplicate settlement's contra reverses its invoice and payment legs and the evidence citation, and the corrected settlement is re-recorded with its evidence (CM-22; C-60).
8. A disbursement, expense or refund recorded in error is contra'd once within its net amount with its dependent attributions; a refund's consumed application returns to credit only through that contra, resolving any shortfall first (CM-38; L-46; C-44; C-45).
9. Replacing the evidence that a settlement cites re-evaluates that settlement inside the replacement's transaction (L-41; architecture challenge 20a).
10. Settlements and contras are absent without `settlement.record` and `finance.contra`, each asking its reason; the cited evidence is visible only with `evidence.view` and `finance.view` (AZ-01; PJ-08; SM-04).
11. Once the row of P11Q-02 (a) is approved, a RESIDUAL settlement is resolved through its route under the capability the row names — re-recorded on the invoice its evidence covers, or contra'd —, RESIDUAL → CONTRAED once with the case's residual falling by its amount, and a second resolution is refused (DATABASE §11.4; C-60; AX-33; TASK_PLAN §8).

**TEST REQUIREMENTS:**

- Lint: TL-01
- Typecheck: TL-02
- Unit: the evidence cap across termins and the claimed-gross bound, against FX-01 6 and 13 (TL-03)
- Feature: ROUTE-255 and ROUTE-260 with each refusal their contracts name; the residual's resolution once its row exists (TL-04)
- Integration: two settlements citing one evidence from different payments; a settlement racing the invoice's void (M-37); a refund's contra racing a shortfall-resolving application (M-14) on synthetic refunds until TASK-053 exists; FX-03's tax and fee on one payment, voids with settlements and duplicate evidence; FX-04's residual settlements; the reconciliation suite after each (TL-05; TEST_STRATEGY §5)
- Authorization: `settlement.record` and `finance.contra` as separately granted ADM_PLUS capabilities (TL-06; SM-04)
- Route integrity: ROUTE-047, ROUTE-255, ROUTE-260 audited, and the resolution's route once its row exists (TL-07; SM-02)
- Interaction: INT-255 and INT-260, and the resolution's INT- row once it exists, per IG-05, with one `command_id` replayed (TL-08)
- Browser E2E: J-FIN-04; CJ-08's SIPLAH net settlement (TL-09)
- Localization/language switch: both languages on PG-047 (TL-10)
- Visual breathing room/density: TEST_STRATEGY §9
- Responsive: TL-11
- Accessibility: TL-12
- Production build: TL-13
- Security: SM-01 for another company's payment or evidence; SM-25 for the fee's expense without `cost.view` (TL-14)
- Performance: PFM-01 and PFM-02 for PG-047; PFM-07 for QS-13's bounded statement; PFM-20 with QB-13 for AX-21 and QB-14 for AX-33 (TL-15)
- Migration: N/A — no migration or import code in this Task
- Other: CM-22 and CM-38's expected end states asserted, each also under concurrency (TEST_STRATEGY §5)

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
- settle a deduction without its payment, its evidence or within no evidence cap, or label an unexplained short payment as a fee or tax (SF-SETTLE; WF-FIN-04)
- compute a SIPLAH fee, cashback or tax by formula, or expense a tax without its configured, evidenced treatment (FS-13; BR-FIN-16)
- expense a fee twice or through a manual expense (C-23)
- correct a settlement, expense, disbursement or refund by editing or deleting it instead of its contra (BR-CR-02)
- build the residual settlement's resolution on a route or capability before Planning names it (RC-06)

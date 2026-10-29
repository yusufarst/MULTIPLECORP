# TASK-036 — Costing foundations — lot cost basis, charge allocation and HPP attribution

Status: APPROVED | Updated: 2026-10-20 | Owner: Planning

Approval: [APPR-016](../00-governance/DECISION_LOG.md#appr-016--p11-task-plan-tasks-and-feature-coverage-matrix-approved), explicit Owner approval on 2026-10-20 (DIR-085 OF-07) of this document as committed in the first execution session's approval commit; the approved file hash and the verified conditions are recorded there. Approval changes lifecycle only, not implementation authorization.

Written by WF-PC5-W's writer of bundle W1 of run PC-5 on 2026-10-19 and 2026-10-20 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99; the Owner approved P11 with the [Task plan](TASK_PLAN.md) at OG-4 (DIR-085 OF-07). **No Task starts before the freeze record, from which the execution authorization is in force (DIR-085 OF-09).** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** DONE

**RISK CLASS:** HIGH

**EXECUTOR:** AGENT

**OBJECTIVE:**

Build the Costing module's foundations that every purchase, receipt, dispatch, loss, return and correction posts through: CST's `CostPosting` — the sole normal writer of the lot cost basis, the lot-cost guard, the one managerial cost ledger with its HPP, LOSS and SUPPLIER_RETURN classes, the charge and charge-share guards and the system-created expenses (ARCHITECTURE §7) —, the calculators `CostSplitter`, `ChargeAllocator` and `ProportionalContra` (ARCHITECTURE §6), SF-CHARGE's charge allocation with pending shares, re-spread and expensing (AX-29), the draw and contra arithmetic of DATABASE §6.2, the inter-company allocation overlay on cost rows (§6.5) and the costing part of the cost-correction cascade (AX-34; CM-17; CM-35), its conservation identities proved by reconciliation.

**WHY THIS EXISTS:**

Cost/HPP is actual, direct cost attributed once and never Cash-Out (CAP-11; AC-11; O-05; BR-FIN-01; BR-FIN-12). One ledger makes HPP versus loss a class instead of a second copy, so a unit of cost is never counted twice nor lost (DATABASE §6.4; GAP-028); purchase charges enter acquisition cost on an explicit basis that conserves the charge and its source bill (BR-PUR-05; DIR-024 D-2; GAP-027); multi-unit splits and corrections reconcile to the source cost to the rupiah (BR-FIN-13; GAP-004; GAP-025). Purchases, receiving, dispatch, losses and expenses (TASK-037, TASK-039, TASK-041, TASK-042, TASK-054) post through it, and the architecture challenge's sequences 2b, 2c, 18a and 18b assert it.

**DEPENDS ON:**

- TASK-008 — Schema — INV tables, inventory and lot-cost representations
- TASK-013 — Command envelope, command identity, idempotency and the lock order
- TASK-030 — Evidence and uploaded documents

**BLOCKS:**

- TASK-037 — Purchases — project and replenishment purchases
- TASK-042 — Condition, location moves, adjustments and losses
- TASK-054 — Expenses, disbursements and other cash

**PARALLEL SAFE:** NO — it owns the lot-cost (LK-23) and charge (LK-22) guards and the cost ledger that every purchasing, inventory and expense Task posts through

**IN SCOPE:**

- the CST tables of DATABASE §4.8 in use — `lot_cost_entries`, `lot_cost_balances`, `cost_attributions`, `purchase_charge_balances`, `purchase_charge_share_balances` and the PURCHASE_CHARGE kind of `expenses` — through `CostPosting`, their one normal writer, each guard row moved once per command by `GuardUpdate` with its net delta (ARCHITECTURE §7; GU-01; GU-07)
- the lot cost basis (DATABASE §6.1, §6.3): exactly one basis entry per lot by its kind — GOODS, NONCREDITABLE_TAX and CHARGE_SHARE for a receipt at the purchase line's cumulative targets over posted values net of reversals and corrections (C-57), OPENING_COST, SURPLUS_FLAGGED_BASIS, DROPSHIP_RETURN_BASIS equal to its HPP contra, REPLACEMENT_BASIS from its return case —; the RECEIPT_REVERSAL rows split over the lot's cost components and the BASIS_REVERSAL of units removed from an opening, surplus or drop-ship return lot; each posted by the command that creates or removes the lot (BR-INV-04; BR-INV-12; BR-FIN-17)
- the draw and contra arithmetic of DATABASE §6.2 in `CostSplitter` and `ProportionalContra`: a draw that zeroes the lot takes its remaining cost exactly, any other C × q ÷ Q rounded to Rp1 half-up; a restoration, reclassification or substitution takes the proportional share of the consumption's net cost of the class it reverses over its open quantity, the event that zeroes it absorbing the residual; restored units re-enter their lot at the contra amount (BR-FIN-13; L-31; GAP-025)
- the cost ledger's classes and causes (DATABASE §4.8, §6.1): HPP INITIAL on a lot draw with the consuming project as bearer, and direct INITIAL attributions per cost component for drop-ship confirmation lines and non-stock purchase lines, one INITIAL per source (C-25); the LOSS draw and the loss-in-transit reclassification pair; SUPPLIER_RETURN with the RETURN_SETTLEMENT pair of an unrecovered share; RESTORATION_CONTRA, COST_CORRECTION, CHARGE_RESPREAD, LATE_CHARGE, LOSS_REVERSAL and SUBSTITUTION rows, each a new signed row naming what it reverses (BR-FIN-12; BR-INV-13; L-18) — the commands that cause them are their Tasks' (OUT OF SCOPE)
- the inter-company allocation overlay on cost rows (DATABASE §6.5; BR-INV-05; L-44): a lot draw keyed to its consumption, lot and source company and carrying the consumption's bearer, a row whose source differs from its bearer carrying its allocation reason (C-28), the cost-only RETURN_SETTLEMENT allocation; reversed and re-attributed only along its consumption, so net allocation = net cross-company consumption by construction — the XL-1 authority is checked by the command that chooses the lot (AZ-08; TASK-041)
- SF-CHARGE and AX-29 in `ChargeAllocator` and `CostPosting`: a charge ATTRIBUTABLE with its basis or NON_ATTRIBUTABLE as an expense of the causal project or the company, never both (C-21); the basis split over the purchase lines with residual absorption by the last line in line order; each line's share allocated over the ordered quantity by cumulative rounding against its share guard, the open remainder's share pending; the re-spread over received quantity when a remainder ends by closure, cancellation or receipt reversal (AX-32, AX-12, AX-05 calling it), consumed units taking CHARGE_RESPREAD draws; the PURCHASE_CHARGE expense when nothing received of the charge remains (DIR-024 D-2 items 5–6); and the late charge's CHARGE_SHARE entries with LATE_CHARGE draws for units already consumed (DATABASE §6.1) (L-43; L-47; BR-PUR-05)
- the source-bill cap: every charge names its SUPPLIER_BILL evidence, and Σ current charge amounts and expenses citing one bill ≤ its total, moved with every charge correction through DOC's evidence guard (C-20; L-43; BD-17)
- AX-34's costing part (DATABASE §6.3 steps 1–5; CM-17; CM-35): a price or tax correction spread over the line's lots and non-replacement drop-ship lines by cumulative rounding of their net quantities as COST_CORRECTION entries; a charge-amount or basis correction as a new allocation version — every CHARGE posting of the old version contra'd and the corrected charge re-posted by AX-29's rules —; COST_CORRECTION attributions re-attributing every dependent consumption, restoration, loss, allocation (same bearer, same overlay), unrecovered share and replacement basis; a closed purchase-return case reopened when its equation no longer balances; one REVALIDATE per affected completed project where material (SF-REVAL) — the command with its PUR rows is TASK-037's ROUTE-175
- CST's query classes for the figures the higher tiers show — a lot's remaining cost, a project's HPP, losses and direct cost (CALC-09), a company's losses and non-attributable charges (CALC-11), the allocation rows —, each summing `cost_attributions` and `expenses` once (DATABASE §6.4 (8)), scope first and projected by family (AZ-04; CS-07; PJ-05; PJ-06; PJ-22)
- the reconciliation rows of DATABASE §3.1 for the CST guards — Σ lot entries = remaining cost + net draws, charge allocated + pending + expensed = charge, share posted ≤ share, posted values net of reversals and corrections — and C-28's DQ over rows whose bearer differs from their source (C-21; C-26; C-57; C-61)

**OUT OF SCOPE:**

- the commands that call `CostPosting`, with their pages: purchases and their charges, cancellation, remainder closure and ROUTE-175's cost correction (TASK-037); receiving and its reversal (TASK-039); dispatch, its restorations and identity substitution (TASK-041; TASK-048); losses, their reversal and SF-LOSS's causal-project rule (TASK-042); purchase returns and their outcomes (TASK-044); drop-ship confirmations (TASK-046); manual expenses, disbursements and contras (TASK-054); opening lots (TASK-057); count surplus attribution (TASK-043)
- a route that records an attributable charge on a purchase after its first effect: ROUTE_CONTRACTS lists none — ROUTE-171 carries AX-29's charges at the purchase's save and ROUTE-172 is refused after the first effect —, so only the late charge's posting rule is built here, never an entry point for it (DIR-083 OE-20)
- profitability and period reports, the consolidated report and the Owner's allocation report (TASK-060); the authority of an inter-company allocation (TASK-041; TASK-012)
- any valuation method, general ledger, journal or tax algorithm (FS-13)

**REFERENCES:**

- [WORKFLOWS §6](../03-workflows/WORKFLOWS/s06-07-subflows-partial.md#6-reusable-subflows) SF-CHARGE, SF-RESTORE, SF-LOSS (their cost legs); [§8](../03-workflows/WORKFLOWS/s08-09-corrections-indivisible.md#8-correction-reversal-and-cancellation-matrix) CM-17, CM-35; [§9](../03-workflows/WORKFLOWS/s08-09-corrections-indivisible.md#9-indivisible-business-actions) AX-29, AX-34; [§12](../03-workflows/WORKFLOWS/s10-14-signals-decisions-traceability.md#12-planner-determined-level-1-decisions) L-18, L-31, L-43, L-44, L-47; [WF-PUR-01](../03-workflows/WORKFLOWS/s05-03-inventory-purchasing.md#wf-pur-01--project-purchase-desk)'s charges
- [BUSINESS_RULES](../02-domain/BUSINESS_RULES.md#procurement-pur) BR-PUR-05; [Finance](../02-domain/BUSINESS_RULES.md#finance-fin) BR-FIN-01, BR-FIN-11–BR-FIN-13, BR-FIN-17; [Inventory](../02-domain/BUSINESS_RULES.md#inventory-inv) BR-INV-04, BR-INV-05, BR-INV-13; [§5](../02-domain/BUSINESS_RULES.md#5-calculation-semantics) CALC-09, CALC-11; [the numeric annex](../02-domain/BUSINESS_RULES.md#numeric-annex-binding-examples-for-p8-fixtures-gap-004), examples 10, 12, 14, 15, 16; [§7](../02-domain/BUSINESS_RULES.md#7-forbidden-shortcuts) FS-07, FS-13
- [V1_SCOPE](../01-product/V1_SCOPE.md#must-ship) CAP-11; [Financial concepts](../01-product/V1_SCOPE.md#financial-concepts-and-billing); [Shared warehouse and cost attribution](../01-product/V1_SCOPE.md#shared-warehouse-and-cost-attribution); [ACCEPTANCE_CRITERIA](../01-product/ACCEPTANCE_CRITERIA.md#product-acceptance-contracts) AC-11; [PRODUCT_OVERVIEW](../01-product/PRODUCT_OVERVIEW.md#problems-and-operational-outcomes) O-05; [REFERENCE_COVERAGE](../01-product/REFERENCE_COVERAGE.md#pdf-capability-inventory) REF-016, REF-044, REF-045
- [DATABASE §4.8](../04-architecture/DATABASE/s04-08-cst.md#48-cst--costing--expenses); [§4.6](../04-architecture/DATABASE/s04-06-pur.md#46-pur--procurement) `purchase_charges`, `purchase_charge_line_shares`, `purchase_line_balances`, `purchase_line_adjustments`; [§6](../04-architecture/DATABASE/s06-lot-cost-hpp.md#6-lot-cost-and-hpp-architecture) §6.1–§6.5; [§3.1](../04-architecture/DATABASE/s01-03-foundations.md#31-guard-registry-and-reconciliation); [§17](../04-architecture/DATABASE/s13-17-history-scope-types.md#17-money-quantity-and-date-types); [§18](../04-architecture/DATABASE/s18-19-constraints-integrity.md#18-constraint-catalogue) C-20, C-21, C-25, C-26, C-28, C-46, C-53, C-57, C-61; [§25](../04-architecture/DATABASE/s25-28-transactions-handoffs.md#25-transaction-boundary-map) AX-29, AX-34
- [ARCHITECTURE §3](../04-architecture/ARCHITECTURE.md#3-modules) the tiers; [§6](../04-architecture/ARCHITECTURE.md#6-domain-model-value-objects-and-calculators) the calculators; [§7](../04-architecture/ARCHITECTURE.md#7-posting-services-and-guards) `CostPosting`, `GuardUpdate`
- [CONCURRENCY_IDEMPOTENCY §7.1](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s07-statement-sequences.md#71-lock-sets-of-ax-01ax-37) and [§7.2](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s07-statement-sequences.md#72-checks-effects-and-outcomes-of-ax-01ax-37) AX-29, AX-34; [§4.2](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s04-06-locks-envelope.md#42-lock-classes) LK-21–LK-23; [§5](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s04-06-locks-envelope.md#5-data-dependent-lock-set-protocol); [§8](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s08-09-guards-stale-state.md#8-guards-and-first-use-rows) GU-01–GU-07, FU-05; [§11](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s10-13-identity-failure-retry.md#11-separate-duplicates) BD-17; [§18](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s18-scenarios.md#18-mandatory-concurrency-and-failure-scenarios) M-07
- [PERMISSIONS_MATRIX §2](../05-security/PERMISSIONS_MATRIX.md#2-authorization-model) AZ-04, AZ-08; [§6](../05-security/PERMISSIONS_MATRIX.md#6-company-scope-rules) CS-07, CS-08; [§7.2](../05-security/PERMISSIONS_MATRIX.md#72-table-classification) rows 73–78; [§7.3](../05-security/PERMISSIONS_MATRIX.md#73-special-projections) PJ-05, PJ-06, PJ-11, PJ-22; [§8](../05-security/PERMISSIONS_MATRIX.md#8-cross-company-link-authorization) XL-1
- [TEST_STRATEGY §4](../08-testing/TEST_STRATEGY.md#4-environments-and-data) FX-01, FX-03, FX-04; [§5](../08-testing/TEST_STRATEGY.md#5-domain-and-data-integrity) the identities proved; [§6](../08-testing/TEST_STRATEGY.md#6-concurrency-and-failure-proofs) M-07; [SECURITY_TEST_MATRIX](../08-testing/SECURITY_TEST_MATRIX.md#3-the-matrix) SM-03, SM-25; [PERFORMANCE_TARGETS §2](../08-testing/PERFORMANCE_TARGETS.md#2-the-query-budgets) QB-14; [§3](../08-testing/PERFORMANCE_TARGETS.md#3-the-measured-targets) PFM-03, PFM-20
- [GAP_REGISTER](../00-governance/GAP_REGISTER.md#gap-004--exact-arithmetic-does-not-define-managerial-financial-meaning) GAP-004; [GAP-025](../00-governance/GAP_REGISTER.md#gap-025--multi-unit-conversion-and-cost-split-arithmetic-can-silently-break-reconciliation); [GAP-027](../00-governance/GAP_REGISTER.md#gap-027--purchase-charges-needed-an-acquisition-cost-rule); [GAP-028](../00-governance/GAP_REGISTER.md#gap-028--inventory-loss-and-damaged-return-cost-could-disappear)
- Architecture challenge — the sequences 2b, 2c, 18a, 18b of the [P11 gate record](evidence/P11_QUALITY_GATE.md#whole-system-architecture-challenge-sp-98), their assertions carried into this Task's acceptance criteria and tests
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (25): O-05; CAP-11; AC-11; OS-02; REF-016; REF-044–REF-045; IMG-07; OWN-01; OWN-05; SF-CHARGE; CM-17; CM-35; AX-29; AX-34; L-43; C-20–C-21; C-57; BD-17; M-07; GAP-004; GAP-025; GAP-027–GAP-028

**EXPECTED MODULES / FILE AREAS:**

- the CST module's `CostPosting` with its allocation, re-spread and correction services and its query classes (ARCHITECTURE §3, §4); `Support`'s `CostSplitter`, `ChargeAllocator` and `ProportionalContra` over `Money` and `Quantity` (ARCHITECTURE §6); the CST rows of the `GuardUpdate` registry; `tests/Unit` for the calculators, `tests/Feature/CST`, `tests/Concurrency/` and `tests/Reconciliation/`

**BUSINESS RULES:**

- BR-FIN-12 and L-18: HPP records actual, direct cost once per unit — at warehouse dispatch at the pinned lot cost, at inter-company allocation, at drop-ship confirmation, or as a project-attributed purchase or expense —; corrections are contra-attributions and nothing is counted twice
- BR-PUR-05 and SF-CHARGE (DIR-024 D-2): an attributable charge is part of actual acquisition cost, allocated on its recorded deterministic basis with conservation and BR-FIN-13 rounding; a non-attributable charge is an expense of the causal project, else of the company; a charge is never both. L-43: a charge names its source bill, whose total caps Σ charges and expenses citing it. L-47: allocation runs over the ordered quantity with the open remainder's share pending, re-spread in the same action when the remainder ends, Σ allocated = charge once every remainder has ended
- BR-FIN-17 and L-43 (DIR-024 D-1): purchase tax enters lot cost only as the non-creditable treatment recorded on the purchase line for its company, context, business date and evidence — no hardcoded rate, no invented formula (FS-13); REF-016's configurable components reach cost only that way
- BR-FIN-13 and L-31: deterministic residual-absorbing Rp1 splits, the event that zeroes a lot or a consumption taking the remainder (numeric annex 10; DATABASE §6.2's partial returns)
- BR-INV-13 (DIR-024 D-3): a loss never makes cost disappear and is recorded once; where the cost already sits in a project's HPP it is reclassified, not added (annex 15, 16) — the posting pair is this Task's, the causal-project rule TASK-042's
- BR-INV-04 and BR-INV-05 with FS-07: one lot per acquisition carrying its actual cost; the allocation overlay keeps the source company's provenance and the consumer's attribution, never silently reassigned, with no inter-company invoice, journal or cash transfer (annex 12; architecture challenge 18b)
- BR-FIN-01 and BR-FIN-11 (CAP-11; AC-11; O-05; REF-044; REF-045): Cost/HPP and Cash-Out never share a row and no cost posting creates Cash-Out; CALC-09 is Σ HPP attributions and project-attributed expenses — losses and non-attributable charges included —, each once; CALC-11's company-level losses and expenses come from the same rows
- CM-17 and CM-35 through AX-34: a wrong price, tax or charge after a receipt, and a wrong allocation basis, are corrected in one action after which Σ attributed = the corrected cost; BR-CR-02: a correction is a new row linked to what it corrects (architecture challenge 2b)

**ARCHITECTURE RULES:**

- CST is tier 3 (ARCHITECTURE §3): PUR, INV and FIN actions call its write services inside their own transactions; CST never calls a higher tier — a correction's PUR rows (`purchase_line_adjustments` and the line guard's posted values through `PurchasePosting`) and a draw's INV rows belong to the calling action —, while it may call PRJ's revalidation (tier 2) and DOC's evidence guard (tier 1)
- one cost ledger: HPP, LOSS and SUPPLIER_RETURN are classes of `cost_attributions`, and moving cost between them is a pair of rows, never a second draw (DATABASE §6.4); the calculators are pure functions over exact decimals, never PHP float (ARCHITECTURE §6; DATABASE §17)
- managerial costing only — no general ledger, journal, valuation method or tax algorithm (FS-13; DATABASE §6); every guard has one formula and one normal writer, a drift reported, never repaired silently (C-61)

**SECURITY / PERMISSION REQUIREMENTS:**

- no route or capability of its own: `CostPosting` runs only inside an authorized command, its effects SYS consequences of approved links — SF-CHARGE shares, re-attribution under BR-FIN-12/13 and AX-34 — that need no further grant (AZ-08; W4-26); the chosen effects that need authority — a new allocation (XL-1), an ADM+ correction (`purchase.correct_cost`, W4-18) — are checked by the calling command
- amounts, classes, bearers and source companies are derived on the server, never taken from a payload (DP-03; WS-02)

**COMPANY SCOPE AND FIELD PROJECTION:**

- rows 73–78 of PERMISSIONS_MATRIX §7.2: `lot_cost_entries` and `lot_cost_balances` CV(source c); `cost_attributions` CV(bearer c) with source identifiers masked (PJ-06), lot draws also CV(source c) with the bearer masked (PJ-05); `purchase_charge_balances`, `purchase_charge_share_balances` and `expenses` CV(c)
- CST's query classes apply the scope resolver before filtering, aggregation and pagination (AZ-04; CS-07); a figure takes the class and families of its inputs, and a quantity is never combined with a cost for a viewer not entitled to that cost (PJ-22); an Admin gets figures per company, never a total across companies (CS-08); the consuming project's allocated cost reaches its bearer company's `cost.view` in full, the source side masked (PJ-06; architecture challenge 18b)

**CONCURRENCY / IDEMPOTENCY:**

- `CostPosting`'s statements run inside the calling action's lock set and transaction, at LK-22 (charge, then charge share) and LK-23 (lot cost) in canonical order, each guard row written once with its net delta (GU-01; GU-04); a share guard is a first-use row by insert-or-lock at LK-22 (FU-05), a lot-cost row is born with its lot (CONCURRENCY_IDEMPOTENCY §8)
- AX-29: DATA-DEPENDENT, LK-00, LK-01, LK-03, LK-10, LK-21, LK-22, LK-23, LK-29 with FU-05; re-read under lock the source bill's capacity, the basis and the purchase's received and consumed quantities; identity the source bill (BD-17); refusals C-20, C-21; retry class B (CONCURRENCY_IDEMPOTENCY §7.1, §7.2)
- AX-34: DATA-DEPENDENT, LK-00, LK-01, LK-03, LK-10, LK-21, LK-22, LK-23, LK-27, LK-29 with FU-05, under §5's protocol — the lots, their dependents, bearer projects and return cases derived, locked and re-derived after LK-21 and LK-23, a consumption committed meanwhile restarting the action with its project (M-07); identity the purchase line or charge + intent; refusals C-57, C-20, C-21, C-46; retry class B
- the re-spread called by AX-05, AX-12 and AX-32 derives the lots and consumptions that take it under §5 (after LK-21, LK-22, LK-23); every draw is bounded by its lot-cost guard (C-26) and taken under the canonical lock order — the product guard first, the lot cost at LK-23 —, so each unit's HPP is drawn once (architecture challenge 18a)
- every CST row carries its command's (`command_id`, `command_line_no`) under a UNIQUE key, so a replay never posts cost twice (C-48; CI-01–CI-09)

**AUDIT:**

- CST writes no audit event of its own: the calling command's event, in its transaction, records the actor, the highest authority class exercised and the records it affects (LG-01; AZ-10) — ADM_PLUS for AX-34 (`purchase.correct_cost`), which reaches the Owner review queue (QS-14); prices are a critical area of BR-XC-01
- the cost ledger is its own history: append-only rows with business date, command identity, source references and `reverses_attribution_id` for a contra, never updated or deleted (BR-CR-01; BR-CR-02; C-53); cost keys of an event's before and after projected by family (PJ-11; LG-05)

**DATABASE IMPACT ASSESSMENT:**

- Schema change: NONE — uses the tables of TASK-005–TASK-009; a missing column is an executor discovery (AGENT_OPERATING_MODEL)
- Migration required: NO
- Existing data transformation: NONE
- Direct production DB access: FORBIDDEN
- Data-loss risk: NONE KNOWN

**MODULARITY / EXTENSIBILITY:**

- Module boundary affected: CST, tier 3, called by PUR, INV and FIN (ARCHITECTURE §3); `Support`'s business-neutral calculators
- Stable core kept provider-neutral: YES
- External/vendor-specific code isolated: YES — the decimal library behind `Money` chosen at DEP-07 (ARCHITECTURE §6)
- Replaceable adapter/contract required: NO
- Provider lock-in risk: NONE beyond the Owner's baseline stack
- Future extension impact: MEDIUM — every later posting Task writes cost through this service
- Speculative abstraction avoided: YES — no valuation engine, ledger framework or generic rules engine (FS-13; ARCHITECTURE §1)

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

- Context7: REQUIRED — PostgreSQL `numeric` rounding and casts, CHECK constraints over several columns of one row, partial UNIQUE indexes on expressions; the decimal library chosen at DEP-07 and its rounding modes, at the installed versions
- Graphify: OPTIONAL — once the calling modules exist
- Browser E2E: N/A — no page
- Other: the numeric fixtures FX-01, FX-03 and FX-04 (TASK-003's harness); synthetic lots, consumptions and charges posted through the service until TASK-037, TASK-039 and TASK-041 add the commands that post them
- Missing safe tool behavior: AUTO-INSTALL project-locally inside this Task (AICWDF §6.3) and record it in TOOLCHAIN; a paid tier or a privileged installation STOPS for the Owner (COST_POLICY)

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: refreshed before the change when Graphify is available ([TOOLCHAIN](../00-governance/TOOLCHAIN.md)); otherwise a targeted search of CST and of the calls into `CostPosting`
- Pre-change questions: which actions post which cost kinds and causes, which guard rows each touches, and where a draw's bearer and source come from
- Expected affected areas: CST and `Support`; the posting calls of PUR, INV and FIN as their Tasks add them

**CONTEXT7:**

- Topics to verify: PostgreSQL `numeric` arithmetic and half-up rounding; multi-column CHECK constraints and partial UNIQUE indexes with `coalesce`; row locks taken in the canonical order; the decimal library's division and rounding API
- Version-sensitive assumptions: none from memory

**UI / UX REQUIREMENTS:**

- Follow global design rules: N/A — no page; the cost figures are presented by the pages of TASK-037, TASK-040 and TASK-060
- Indonesian default: N/A — no page; the cost figures are presented by the pages of TASK-037, TASK-040 and TASK-060
- English support: N/A — no page; the cost figures are presented by the pages of TASK-037, TASK-040 and TASK-060
- Plain-language Indonesian: N/A — no page; the refusals C-20, C-21 and C-57 are worded by the commands that raise them
- Language switch impact: NONE
- Mobile-first: N/A — no page in this Task
- Responsive: N/A — no page in this Task
- Adaptive: N/A — no page in this Task
- Visual breathing room: N/A — no page in this Task
- Comfortable density: N/A — no page in this Task
- shadcn/ui reuse: N/A — no page in this Task
- Design reference: N/A — no page; DESIGN_SYSTEM §14.1's *Biaya/HPP* label is the pages' Tasks'
- Primary action: N/A — no control of its own
- Secondary actions: N/A — no control of its own
- Destructive actions: N/A — no control of its own
- Redundancy constraints: N/A — no control of its own
- Loading state: N/A — no page in this Task
- Empty state: N/A — no page in this Task
- Error state: N/A — no page; a guard refusal reaches its command as REJECTED with its rule (ARCHITECTURE §7)

**ROUTE IMPACT:**

- none of its own — `CostPosting` is called by ROUTE-171 and ROUTE-173–ROUTE-175 (TASK-037), ROUTE-181 and ROUTE-183 (TASK-039), ROUTE-186, ROUTE-188 and ROUTE-189 (TASK-041), ROUTE-194 and ROUTE-196 (TASK-042) and the posting routes of the later Tasks

**INTERACTION CONTRACTS:**

- N/A — no control of its own; INT-171 and INT-175 (TASK-037) carry its charges and its corrections

**NAVIGATION CONTRACT:**

- Parent: N/A — no page
- Entry: N/A — no page
- Back action: N/A — no page
- Cancel: N/A — no page
- Success destination: N/A — no page
- Error behavior: N/A — no page
- State preservation: N/A — no page
- Mobile behavior: N/A — no page

**ACCEPTANCE CRITERIA:**

1. Lines A 10 pcs × 100,000 and B 30 pcs × 100,000 with an attributable freight of 100,000 on a line-value basis allocate A 25,000 and B 75,000, both lots costing 102,500 per piece, and a non-attributable administration fee of 20,000 becomes an expense only (numeric annex 14; BR-PUR-05).
2. A box of 12 pieces for 100,000 drawn 5 then 7 posts 41,667 and 58,333, Σ = 100,000 exactly; received 5 then 7, its GOODS entries are 41,667 then 58,333 by cumulative rounding (annex 10; BR-FIN-13; DATABASE §6.1).
3. With that lot drawn 5 by X and 7 by Y, X returning 2 takes a contra of 16,667, Z then drawing the lot's 2 units takes 16,667, and X returning its last 3 takes 25,000, so Y 58,333 + Z 16,667 + the lot 25,000 = 100,000 (DATABASE §6.2; L-31; GAP-025).
4. A charge on a line ordered 10 with 6 received posts the 6 units' share to the lot and keeps the rest pending, allocated + pending + expensed = the charge at every step; closing the remainder re-spreads the pending share over the received units in the same transaction, consumed units by CHARGE_RESPREAD draws; a charge with nothing received becomes a PURCHASE_CHARGE expense; once every remainder has ended Σ allocated = the charge exactly (C-21; L-47; AX-29).
5. Charges and expenses whose Σ citing one source bill would exceed the bill's total are REJECTED with C-20, also when a charge correction would exceed it; no charge is both allocated and expensed (C-20; C-21; L-43; BD-17; architecture challenge 2c).
6. Receiving 5 of a box of 12 for 100,000 posts GOODS 41,667, and dispatching 2 of them HPP 16,667; correcting the price to 120,000 posts COST_CORRECTION +8,333 on the lot and +3,333 on the dispatch, giving HPP 20,000 and a lot of 30,000 for 3 units; receiving the other 7 then posts GOODS 70,000, Σ = 120,000 (DATABASE §6.3; C-57; AX-34; CM-17; architecture challenge 2b).
7. A basis correction contras every CHARGE posting of the old allocation version and re-posts the charge under the new version — received units in lots, consumed units as draws, ended remainders re-spread, open remainders pending — so Σ allocated = the charge exactly (CM-35; AX-34; C-21).
8. Two units dispatched at 50,000 each (HPP 100,000), one lost in transit, give the pair HPP −50,000 / LOSS +50,000 and a project cost still 100,000; the delivered unit returned afterwards takes a restoration contra of 50,000 on the open quantity while the LOSS stays (annex 15; DATABASE §6.2).
9. A delivered unit of lot cost 50,000 returned damaged takes a restoration contra of 50,000 into its lot, and its later disposal one LOSS draw of 50,000 on the causal project — the cost neither lost nor counted twice (annex 16; BR-INV-13; GAP-028).
10. B's project drawing 6 units of A's lot of 10 at 12,000 records HPP 72,000 on B's project with source A, bearer B and the allocation reason; a cost row whose source differs from its bearer without a reason is refused; a restoration reverses the allocation with its consumption, so net allocation = net cross-company consumption (annex 12; C-28; L-44; architecture challenge 18b).
11. Two concurrent draws of one lot serialize under the lock order: each unit's HPP is drawn once and the lot's remaining cost never falls below zero, reaching exactly zero with its last unit (C-26; architecture challenge 18a).
12. A cost correction racing a draw on one of its lots ends with Σ attributed = the corrected cost in each of M-07's three timings — the draw took the old cost and was corrected, or took the corrected cost (M-07).
13. A purchase line's tax enters its lots' cost only as NONCREDITABLE_TAX under the treatment recorded on the line; creditable tax never enters cost (BR-FIN-17; L-43).
14. A cost correction that unbalances a closed purchase-return case reopens it in the same transaction, and a material correction writes one REVALIDATE per affected completed project (C-46; SF-REVAL).
15. No CST posting writes a Cash-Out; a project's CALC-09 sums each of its `cost_attributions` and `expenses` rows once; a source-side viewer sees an allocation draw with the bearer masked and the bearer side the cost in full with the source masked (BR-FIN-01; BR-FIN-11; AC-11; PJ-05; PJ-06).
16. After every scenario the reconciliation suite finds no difference on the lot-cost, charge and share guards and C-28's DQ returns no row; a cost row refuses update and delete (C-21; C-26; C-57; C-61; C-53).

**TEST REQUIREMENTS:**

- Lint: TL-01
- Typecheck: TL-02
- Unit: `CostSplitter`, `ChargeAllocator` and `ProportionalContra` against the numeric annex 10, 12, 14, 15 and 16 and DATABASE §6.2's and §6.3's fixtures, value by value (FX-01; TL-03)
- Feature: N/A — no route; the services are exercised through TL-05 by synthetic callers until TASK-037's commands exist
- Integration: real PostgreSQL — every posting kind with its guard CHECKs (C-20, C-21, C-26, C-57) and a violating write refused under the runtime role; FX-04's price and charge correction between partial receipts; FX-03's charges across receipts, remainder closure and duplicate bills; the late charge after consumption; AX-34 under §5's protocol with M-07's three timings against a concurrent synthetic draw; two concurrent draws of one lot; the reconciliation suite's CST rows after every scenario (TL-05; TEST_STRATEGY §5, §6)
- Authorization: CST's query classes — PJ-05, PJ-06 and PJ-22 for a source-side and a bearer-side viewer, per-company figures for an Admin with several companies (CS-08) (TL-06)
- Route integrity: N/A — no route added or changed
- Interaction: N/A — no INT- contract of its own
- Browser E2E: N/A — no page; CJ-02 and CJ-14 run through the purchasing and reporting Tasks
- Localization/language switch: N/A — no user-facing copy in this Task
- Visual breathing room/density: N/A — no page in this Task
- Responsive: N/A — no page in this Task
- Accessibility: N/A — no page in this Task
- Production build: TL-13
- Security: SM-03's architecture test that CST's query classes apply the scope resolver first; SM-25 for the cost family in those query classes (TL-14)
- Performance: QB-14 and PFM-20 — AX-29, AX-34 and the re-spread proportional to the rows they change, set-based where the posting rules allow; PFM-03 for the cascade's derivation queries on IX-08's indexes (TL-15)
- Migration: N/A — no migration or import code in this Task; OPENING_COST is posted by TASK-057's import
- Other: DATABASE §6.4's eight identities proved on the fixtures (GAP-025; GAP-027; GAP-028); GAP-004's Rp1 rounding at every split

**CRITERIA DISPOSITIONS:**

- Criterion 1: `tests/Integration/Costing/CostPostingTest.php` — lines A 10 × 100,000 and B 30 × 100,000 with an attributable freight of 100,000 on line value posting A 25,000 and B 75,000, both lots 102,500 a piece, the administration fee of 20,000 a PURCHASE_CHARGE expense and no lot entry; `tests/Unit/Support/CostingCalculatorsTest.php` — `ChargeAllocator` against annex 14
- Criterion 2: `tests/Integration/Costing/CostPostingTest.php` — the box of 12 for 100,000 drawn 5 then 7 as 41,667 and 58,333, and received 5 then 7 as GOODS 41,667 and 58,333, Σ = 100,000; `tests/Unit/Support/CostingCalculatorsTest.php` — `CostSplitter` against annex 10, Rp1 at every split (GAP-004)
- Criterion 3: `tests/Integration/Costing/CostPostingTest.php` — X's returns of 2 and 3 contra'd 16,667 and 25,000 over its open quantity, Z's draw 16,667, Y 58,333 + Z 16,667 + the lot 25,000 = 100,000; `tests/Unit/Support/CostingCalculatorsTest.php` — `ProportionalContra` against §6.2
- Criterion 4: `tests/Integration/Costing/ChargePostingTest.php` — 6 of 10 received posting 30,000 with 20,000 pending; the closure re-spreading it in its transaction as 10,000 on the lot and a CHARGE_RESPREAD draw of 10,000 on the 3 dispatched units; a charge with nothing received a PURCHASE_CHARGE expense citing its line; allocated + pending + expensed = the charge at every step — allocated and expensed from the charge's guard, pending read from its shares' guards —, Σ allocated = the charge once every remainder has ended; `tests/Integration/Costing/DirectCostTest.php` — a non-stock line's and a drop-ship line's shares posted as direct rows
- Criterion 5: `tests/Integration/Costing/ChargePostingTest.php` — a second charge beyond its bill of 30,000, refused whole, and a correction to 31,000 each REJECTED C-20, the bill cited once; allocated and expensed beyond the charge refused by C-21's CHECK under the runtime role, every charge's guard reconciled with its rows
- Criterion 6: `tests/Integration/Costing/CostPostingTest.php` — the price corrected to 120,000 between receipts: COST_CORRECTION +8,333 on the lot and +3,333 on the dispatch — HPP 20,000, the lot 30,000 for 3 units —, the second receipt GOODS 70,000, Σ = 120,000
- Criterion 7: `tests/Integration/Costing/ChargePostingTest.php` — a basis corrected from 1 : 1 to 3 : 1 contra'ing version 1 and re-posting version 2 — received units in lots, consumed units as draws, the open half pending —, Σ allocated = the charge once the remainder ends; an amount corrected to 60,000 with an expensed share present, its delta cited, a later receipt posting only what version 2 still has pending (FX-04), a share below its expense refused C-21; `tests/Integration/Costing/DirectCostTest.php` — version 1 contra'd where it sits — a non-stock line's project, a drop-ship line's project, its return lot and its supplier-return case — and version 2 re-posted there
- Criterion 8: `tests/Integration/Costing/CostPostingTest.php` — annex 15's pair HPP −50,000 / LOSS +50,000, the project's cost still 100,000, the returned unit's contra of 50,000 on the open quantity, the LOSS kept
- Criterion 9: `tests/Integration/Costing/CostPostingTest.php` — annex 16's damaged return contra'd 50,000 into its lot and its disposal one LOSS draw of 50,000 on the causal project
- Criterion 10: `tests/Integration/Costing/CostPostingTest.php` — annex 12's HPP 72,000 on B's project with source A, bearer B and the reason; a cross-company row without its reason refused under the runtime role (C-28); the restoration reversing the allocation with its consumption
- Criterion 11: `tests/Integration/Costing/CostingRacesTest.php` — two sessions drawing one lot of 3 for 100,000: the second waits on the lot's cost guard and draws half of what the first left (33,333 then 33,334), the last unit the rest, the lot exactly zero
- Criterion 12: `tests/Integration/Costing/CostingRacesTest.php` — M-07's three timings: a dispatch committed before the correction derived its locks corrected by it; one committed between the derivation and the acquisition restarting the correction with the dispatch's project (two executions); one waiting on the correction's locks drawing the corrected cost — Σ attributed + the lot = the corrected cost in each
- Criterion 13: `tests/Integration/Costing/CostPostingTest.php` — a line's input tax recorded INPUT_TO_ACQUISITION_COST entering its lots as NONCREDITABLE_TAX, one recorded INPUT_CREDITABLE never entering cost
- Criterion 14: `tests/Integration/Costing/CostCorrectionTest.php` — a price correction unbalancing a closed purchase-return case reopens it in the correcting command (its `reopen_command_id`), and its two dispatches to a completed project write one REVALIDATE with the correction's reference, none for an active project; `tests/Integration/Costing/BasisAndReturnTest.php` and `DirectCostTest.php` — a correction reaching a replaced return passes the replaced units' share to the replacement still held, a case left balanced staying closed
- Criterion 15: `tests/Integration/Costing/CostCorrectionTest.php` — CALC-09 and CALC-11's cost parts summing each cost row and expense once, no `disbursements` row or money fact written; `tests/Authorization/CostFiguresAccessTest.php` — PJ-05's source side with the bearer, its reason and actor masked, PJ-06's bearer side with the cost in full and the source masked
- Criterion 16: every scenario ends with `CostWorld::differences()` empty — the lot-cost, charge, share, case and posted-value families recomputed and C-28's overlay check `AllocationOverlay` —, beside RefreshDatabase's reconciliation after every integration test; `tests/Integration/Costing/CostCorrectionTest.php` — `lot_cost_entries`, `cost_attributions` and `expenses` refusing update and delete under the runtime role (C-53)
- Every posting kind (TEST REQUIREMENTS): `tests/Integration/Costing/DirectCostTest.php` — a non-stock line attributed with its charge, either order; a drop-ship confirmation, its return to the warehouse, its return to the supplier, its confirmation reversed, its return receipt reversed, a replacement confirmation; `tests/Integration/Costing/BasisAndReturnTest.php` — an opening lot and its BASIS_REVERSAL, a replacement lot, a supplier return's RETURN_SETTLEMENT pair as a cost-only allocation, a SUBSTITUTION and a LOSS_REVERSAL, a restoration refused against a consumption it cannot reverse

**TEST CHECKLIST:**

- [x] Lint: Pint and ESLint PASS in CI (TL-01)
- [x] Typecheck: Larastan/PHPStan at level `max` and TypeScript PASS in CI (TL-02)
- [x] Relevant automated tests: Unit — the calculators against the numeric annex (TL-03); Integration on real PostgreSQL — every posting kind with its guard CHECKs, the corrections between partial receipts, the charges across receipts, closures and bills, the late charge, M-07's three timings and two concurrent draws on real sessions, the reconciliation after every scenario (TL-05); PASS in CI and locally. Feature: N/A — no route, the services exercised through synthetic callers until TASK-037's commands exist
- [x] Authorization: CST's figures for a source-side viewer, a bearer-side viewer, an Admin of both companies and an Admin without `cost.view`, with its positive twin (TL-06)
- [x] Route integrity: N/A — no route added or changed
- [x] Interaction behavior: N/A — no INT- contract of its own
- [x] Browser E2E if required: N/A — no page; CJ-02 and CJ-14 run through the purchasing and reporting Tasks
- [x] Responsive/adaptive if required: N/A — no page
- [x] Visual breathing room/density if UI: N/A — no UI
- [x] Production build: TL-13 PASS in CI
- [x] Security checks if required: SM-03's architecture test that CST's query classes resolve their viewer and apply the scope resolver before any query runs, detected on its fixture; SM-25 for the cost family in those classes (TL-14)
- [x] Database safety review: no migration; cost rows insert-only under the runtime role (C-53), every CST guard moved through `GuardUpdate` in one statement per table, the CHECKs (C-20 through CST, C-21, C-26, C-28, C-57) the backstops; local and CI databases only
- [x] Final diff review: each rule against the contract and its sources; ER-06's independent review and Codex's points dispositioned

**COMPLETION EVIDENCE:**

- Test outputs (TEST_STRATEGY §16), tested commit `6ffdd19`: fresh CI on GitHub's `ubuntu-24.04` runner, runs 40928780678 (push) and 40928785903 (pull request #42), the three jobs PASS in each — "Verify", "Browser" and "Performance and migration"; the TASK-036 PHP tests, PHPStan and Pint also PASS on the workstation's stack
- Build result: `TL-13 Frontend build: PASS — Evidence: 6ffdd19, CI ubuntu-24.04 run 40928780678`
- Route evidence: N/A — no route added or changed
- Interaction evidence: N/A — no INT- contract of its own
- Browser evidence: N/A — no page; the browser layers PASS unchanged in CI run 40928780678
- Localization evidence: N/A — no user-facing copy
- Performance evidence: `tests/Performance/CostingQueriesTest.php` (TL-15) — AX-34's cascade at most 4 statements per further dependent, AX-29's charge at most 18 per further line — its three per-key locks, its two rows and the reads of its cumulative targets —, AX-32's re-spread at most 2 per further consumption, every CST guard in one statement per table (QB-14; PFM-20); the cascade's derivation reads on IX-08's registered indexes, whose plans `tests/Performance/IndexRegisterTest.php` verifies (PFM-03)
- Cost evidence/exception if applicable: ADDITIONAL RECURRING COST CHANGE: NONE — brick/math is MIT and was already locked through the framework
- Files changed: `composer.json` and `composer.lock` (brick/math direct) with TOOLCHAIN's amendment; `app/Support/Money.php` and `app/Support/Costing/` — `CostSplitter`, `ChargeAllocator`, `ProportionalContra` —; `app/Modules/CST/` — `Services/CostPosting`, `CostLedger`, `Data/ReceiptPosted`, `Queries/CostFigures`, `AllocationOverlay` —; PRJ's `Services/Revalidation`; DOC's `EvidenceGuard::uncited`; OPS's `CorrectionCases::post` with its `keepBalanced` option; `app/Support/Guards/Families.php`'s lot-cost, charge, share and posted-value formulas; the tests, the synthetic callers (`tests/Support/CostWorld.php`), the scope-first scan and its fixture
- Commit SHA: `1733edb`, `094034b`, `2d86a42`, `57d3af0`, `746cda9`, `7cce916`, `5451101`, `2f268ab`, `1055c74`, `6ffdd19`, the Owner's identity, no signature and no AI attribution; this record's commit follows them on `task/TASK-036-costing`
- Notes: criteria as CRITERIA DISPOSITIONS states. ER-06's independent review before the pull request found one P1, six P2 and seven P3 points, dispositioned in `5451101`, and a second independent pass over that fix one P1, two P2 and four P3 points, dispositioned in `2f268ab`: a non-stock line's charge share stayed pending and a correction lost it (P1) — an attributed non-stock line now counts its whole quantity as received, its share posted, re-posted and corrected as direct rows on its project; a drop-ship line's returns left its correction base and CST posted no drop-ship restoration — the line's net quantity subtracts only confirmation reversals, each part follows the units to their return lot or supplier-return case, and `dropshipRestored()` posts CM-14, CM-15 and CM-27, `replacementConfirmed()` a replacement confirmation's basis; a correction never reached a replacement's basis — a case's moved returned cost passes, line by line, the replaced units' share to the replacement lots and confirmations still held, moving the case's replaced value, and a case it leaves balanced stays closed (DATABASE §6.3 step 4, through `CorrectionCases::post`'s new `keepBalanced`); BASIS_REVERSAL had no rule, and its first version lost a drop-ship return lot's cost (second pass, P1) — `reverseBasis()` removes an opening or surplus lot's units as one BASIS_REVERSAL and a drop-ship return lot's back onto its confirmation line part by part, re-establishing its restoration (§5.3); a draw locked every project and case its lot ever served — a consumption's own posting takes AX-03's, AX-10's, AX-11's and AX-30's static set, a cascade the closure over its holders, cases and replacements; a new allocation version leaving out a line of the old one is refused, and a share below what it already expensed is refused C-21, an expense never shrinking; a late charge's supplier-return part was an INITIAL beside the confirmation's own (second pass) — it is a draw of its cause; the missing proofs are written — drop-ship and non-stock lines, a charge amount correction with an expensed share and a later receipt, settlement, substitution, loss reversal, the opening basis and its reversal, the replacement lot and confirmation, a case spanning two lines —, and criteria 4, 5 and 13's tests strengthened; REVALIDATE only where a project's net cost moved; a non-stock line's and a settlement's bearer projects locked; every charge reconciled, a missing guard reported, and each purchase line's posted values reconciled with the cost rows naming them (C-57); the lots' net quantities read in one statement; a restoration refused against a consumption it cannot reverse. Kept as readings: AX-29's and AX-32's re-spreads reopen a replaced case instead of correcting its replacement, as AX-29 states; DROPSHIP_ERROR_REVERSAL is CM-37's exact inverse, TASK-048's. Codex's review of `2f268ab` (COMPLETED) found one P2 point, fixed and tested in `1055c74` and its thread answered: a split whose earlier parts round up could leave its last line a negative share, which the shares' CHECK refuses — each part of `CostSplitter::split` is now capped by what the total still leaves, every part keeping the total's sign. Executor readings within the approved text: brick/math 1.0 becomes a direct dependency (TOOLCHAIN's 2026-10-29 amendment) — `Money` keeps scale 2 and PostgreSQL's half-up rounding, a split's share rounded to Rp1 (GAP-004); a re-spread weighs the net base quantities of the charge's lines' lots and drop-ship lines — "the received quantity of the charge"; a correction re-attributes a lot's part to its dependents as COST_CORRECTION rows by their net quantities, a dispatch's part split HPP and LOSS by its open and reclassified units; an expensed share stays expensed under a charge correction and carries its `expensed_out_amount` to the new version; REVALIDATE only for a COMPLETED_NORMAL or COMPLETED_FORCED bearer project, once per project and command, through PRJ's minimal `Revalidation` that TASK-055 extends; CST returns the posted goods and tax deltas (`ReceiptPosted`) and the caller moves PUR's line guard, as `PurchasePosting` will; C-20 is checked by CST through DOC's `EvidenceGuard::uncited` before the citation, whose CHECK would answer C-18; the lot code of an allocation row reaches every holder of `stock.view`, as the pooled lot resource shows it (PJ-06); C-28's DQ is `AllocationOverlay::violations()`, a system check read by no viewer; a cost sits in a lot or on a direct source, and a part reaching a drop-ship line is split by units between the units on its project, its return lots and its supplier-return cases — a drop-ship return's contra moves cost into its lot's basis, so the guards and the posted-value formula leave it out, the cost still allocated and posted (§5.7; "net of reversals" counting reversals only); a supplier return's cost on a drop-ship line moves to SUPPLIER_RETURN as a RECLASSIFICATION pair; a case's replacements take a correction's share line by line, by the replaced units still held over the units that line returned; a re-spread or late charge reaching a replaced case reopens it rather than correcting the replacement (AX-29); a closed purchase return a correction leaves balanced stays closed through OPS's new opt-in `keepBalanced`, every other posting reopening a case whose figures change (TASK-031's rule); DROPSHIP_ERROR_REVERSAL is CM-37's exact inverse with its command (TASK-048). Executor discoveries: `ProjectLineFactory`'s per-process line counter collides across race sessions, so the synthetic dispatch numbers its project line under the project's lock; a `docker compose exec` client can drop a long run's output while the run continues in the container, so the local suites log inside the container; TASK-034's PG-011 fulfilment budget test confirmed on the server's UTC day, a stale prefilled date after 17:00 UTC (ST-08) that made CI run 40924852673 fail at 17:51 UTC — it confirms on the business date now

**SAFE NEXT TASK:**

- TASK-037 — when its other dependencies are DONE and its READY gate holds

**DO NOT DO:**

- add to V1 — a capability, page, route, field, integration, document type, report or paid service — or drop, defer or narrow a V1_REQUIRED requirement; a Task that would need either raises an Owner question and waits ([DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-20)
- access the VPS, production, its data or a credential, or put a secret, real data, a host detail or production configuration in the repository ([INFRASTRUCTURE](../09-operations/INFRASTRUCTURE.md) INF-01, INF-05; SECURITY SX-01, SX-04, SX-06; [PRODUCTION_DATA_SAFETY](../00-governance/PRODUCTION_DATA_SAFETY.md))
- add AI, model or tool attribution to a commit — the Owner's configured identity only ([AGENTS.md](../../AGENTS.md#git-commit-attribution); DIR-022)
- start before the Owner's execution authorization at OG-4, or mark DONE without the required tests passing and the completion evidence ([TASK_PLAN](TASK_PLAN.md#2-rules-of-the-plan); [DEFINITION_OF_DONE](../08-testing/DEFINITION_OF_DONE.md#2-the-checklist))
- weaken, skip or delete a valid test to obtain a green run, or adopt a paid tier without the Owner's COST_POLICY decision (DOD-05; [COST_POLICY](../00-governance/COST_POLICY.md))
- write a CST guard or cost row outside `CostPosting`, or move cost between HPP and LOSS by a second draw instead of a pair of rows (ARCHITECTURE §7; DATABASE §6.4)
- use float arithmetic, a stock valuation method such as FIFO or average cost, or any ledger, journal or tax algorithm (FS-13; DATABASE §17)
- allocate and expense the same charge, or let charges and expenses exceed their source bill (C-20; C-21)
- update or delete a cost row, or correct cost by editing a figure instead of a contra or COST_CORRECTION row (C-53; BR-CR-02)
- add an entry point for a charge recorded after the purchase's first effect (DIR-083 OE-20)

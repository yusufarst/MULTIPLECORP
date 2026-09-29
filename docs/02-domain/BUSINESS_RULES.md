# Business rules

Status: APPROVED | Updated: 2026-09-29 | Owner: Planning

Approval: [APPR-003](../00-governance/DECISION_LOG.md#appr-003--p2-domain-baseline-approved), explicit Owner approval on 2026-09-29 of this document as published in checkpoint `1392966bfb89581d705e0394424705978e1d3db8`; the reviewed file hash and conditions are recorded there. Approval changes lifecycle only, not business meaning or implementation authorization.

Amendment: narrowly amended on 2026-09-29 to represent the Owner's P3 decisions [DIR-024](../00-governance/DECISION_LOG.md#dir-023-dir-024-and-tech-015--p3-authorization-owner-decisions-and-documentation-execution) (D-1 tax, D-2 purchase charges, D-3 inventory loss, D-4 pre-payment Kuitansi) and [DIR-026](../00-governance/DECISION_LOG.md#dir-025-obs-006-dir-026-and-tech-016--targeted-review-owner-loss-attribution-decision-and-corrections) (unexplained fungible loss attribution, BR-INV-13); only the rules and calculations marked `DIR-024`/`DIR-026` changed, plus Level-1 clarifications marked `TECH-016` after the targeted conservation review (opening customer credit in BR-XD-06/BR-FIN-05, Cash-Out references in BR-FIN-11, PLANNER-DETERMINED markers on planner elaborations in BR-FIN-16 and CALC-11). The pre-amendment SHA-256 is recorded there; the amended revision is approved under [APPR-004](../00-governance/DECISION_LOG.md#appr-004--p3-critical-business-workflows-approved).

Authority: P2 authorized by DIR-016; deep review, Q1, Q2/Q3 and fee-generalization Owner decisions under DIR-017/018/019/020 ([decision log](../00-governance/DECISION_LOG.md)). This document owns domain lifecycles/state rules, business invariants, correction/snapshot semantics and calculation meanings. Concepts and scope classes are owned by [DOMAIN_MODEL](DOMAIN_MODEL.md); phase evidence by [P2_QUALITY_GATE](P2_QUALITY_GATE.md).

**Anti-duplication contract:** [V1_SCOPE](../01-product/V1_SCOPE.md) owns the Owner's business meaning. Every rule below formalizes that meaning and cites its anchors (CAP/AC/DOC/OWN/REF/IMG/DIR); none replaces them. On conflict, V1_SCOPE and the latest Owner decision win; report under [change control](../00-governance/CHANGE_CONTROL.md).

**Boundary:** rules state business truth. P3 orchestrates workflows over these lifecycles without adding, removing or relaxing a transition. P4 chooses the concrete database guarantees for E-DB rules. P5 maps sensitivity classes to enforcement. P6 provides the atomic mechanisms where marked. P8 proves the rules with the fixtures in the numeric annex. No schema, screens, permission matrix or locking design here.

## Conventions

- Rule IDs: `BR-<AREA>-<nn>`; calculations `CALC-<nn>`; forbidden shortcuts `FS-<nn>`. Later phases cite these IDs.
- Enforcement class per invariant-bearing rule: **E-APP** application rule; **E-DB** additionally requires a database-level guarantee (P4 decides which; the tag never names a mechanism); **E-PROC** procedural/operational.
- **PLANNER-DETERMINED** marks a genuine Level-1 decision made under the delegated authority reaffirmed by DIR-017/019, with its evidence/rationale inline. No Owner business decision is pending: **OWNER_DECISION_REQUIRED = 0** (DIR-018/019/020).
- MUST/NEVER are normative. "Individual actor" always means the exact user account (DIR-018), never a shared role identity.

## 1. Cross-cutting rules

### Audit convention

- **BR-XC-01 (E-APP/E-DB).** Every committed business action records: individual actor, recorded_at, action, entity + identity, relevant before/after values, reason where required, and linkage to the records it affects. Critical areas: prices, stock, invoices, billing, payments/applications, permissions/grants, document issue/void, waivers, dispositions, completion/override, corrections. (OB §36; AC-12/13; DIR-018.)
- **BR-XC-02 (E-APP).** Multiple users holding one role remain separate audited identities; sensitive-action history MUST name the exact account. (DIR-018.)
- **BR-XC-03 (E-APP).** Business audit records are append-only and never contain secrets; technical logs are separate. (OB §36.)

### Dates and backdating — DIR-018

- **BR-DT-01 (E-APP/E-DB).** Dual-date model: every committed record carries an immutable `recorded_at`; records with business meaning in time also carry an explicit `business_date`. recorded_at is never rewritten.
- **BR-DT-02 (E-APP).** Owner and Admin Operasional hold the same backdating authority. A business_date earlier than recorded_at is legitimate only when it is the real date of the actual transaction/document; there is no Owner-only prior-month restriction.
- **BR-DT-03 (E-APP).** Every backdated committed transaction preserves recorded_at, business_date, individual actor, audit trail, reason where appropriate, and before/after for corrections.
- **BR-DT-04 (E-APP).** Backdating NEVER fabricates history, bypasses lifecycle rules, bypasses stock/financial integrity, or evades audit. Future-dating is not allowed unless a later explicitly defined workflow legitimately requires it.
- **BR-DT-05.** business_date governs period attribution and aging; recorded_at governs audit order. Integrity/conservation laws bind in recorded order against current state; business-date-filtered historical views are informational projections and may legitimately be non-chronological after backdating.
- **BR-DT-06. PLANNER-DETERMINED.** All business dates, numbering periods and aging use Asia/Jakarta (WIB) day boundaries. Evidence: every legal entity is a Yogyakarta company; all Owner activity is +07:00; single-timezone Java operation. (Discharges the GAP-016 timezone question.)

### Historical immutability and snapshots

- **BR-SN-01 (E-DB).** Snapshot at commitment (issue, confirmation, movement), never at render. Committed snapshots are immutable; master changes never rewrite them. (CAP-03/08; AC-03/08; OB §13.)
- **BR-SN-02.** Snapshot-bearing families: company legal + document identity incl. bank destination on financial documents; client/unit/PIC identity and address as used; product/service description; unit + conversion ratio + base equivalent; quantities; purchase/selling/SPJ-reference prices; tax/fee/VA components; supplier identity; channel metadata; issued document content.
- **BR-SN-03.** Masters answer "true now"; snapshots answer "asserted then". Only the Section-2 correction primitives touch committed history. The [relationship register](DOMAIN_MODEL.md#d-relationship-register) marks each cross-domain reference live vs snap.

## 2. Correction taxonomy

Eight primitives; committed business history never disappears; hard-delete is never a correction for referenced transactions (CAP-13, AC-12, OB §26).

| Primitive | Semantics | Applies to |
| --- | --- | --- |
| EDIT | Free change; permitted only before commitment/downstream effect | Drafts, unposted records |
| REVISION | New immutable version; prior versions preserved | Quotations, documents/invoices |
| VOID | Invalidates an issued artifact; existence + retired number preserved; NEVER cascades | Documents/invoices |
| REVERSAL | Equal-and-opposite linked event correcting a committed fact | Stock movements, entry-error facts |
| RETURN | Real commercial/physical event with own consequences | Purchase return (stock out + lot/allocation unwind + cost contra); sales return (stock in — damaged → UNUSABLE — + receivable/credit correction) |
| REALLOCATION | Superseding payment application under conservation | Payment applications |
| COMPENSATING ACTION | New corrective record where in-place reversal is impossible; triggers completion revalidation | Post-completion corrections, contra-facts |
| ARCHIVE / INACTIVE | Master/company deactivation only; never a transaction correction | Masters, companies |

- **BR-CR-01 (E-APP).** Every correction carries reason + individual actor + recorded_at + linkage to the original (BR-XC-01).
- **BR-CR-02 (E-APP/E-DB).** Immutable facts (payments, disbursements, expenses, movements) are corrected only by contra-facts/REVERSAL referencing the original; both remain permanently visible. A contra-fact forces explicit disposition of the original's applications/attributions.
- **BR-CR-03 (E-APP).** VOID or downward REVISION of a **billed** invoice is committable only together with an explicit audited disposition of every attached payment application (release-to-credit or re-target), atomically; a revision NEVER reduces effective value below the applied total. (Closes GAP-005's void-with-allocations hole.)
- **BR-CR-04 (E-APP).** RETURN, discrepancy and other correction cases end with an explicit **correction-case closure** decision; "no unresolved correction" completion predicates read these closures.
- **BR-CR-05 (E-APP).** Material corrections after completion (normal or forced) invalidate stale gate evidence and force revalidation (CAP-18, AC-21).
- **BR-CR-06.** Legality matrix (✓ allowed, ✗ prohibited, C = only with the coupled companion correction):

| Lifecycle \ Primitive | EDIT | REVISION | VOID | REVERSAL | RETURN | REALLOC | COMPENS | ARCHIVE |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Quotation | draft only | ✓ (re-approval) | ✗ (reject/expire instead) | ✗ | ✗ | ✗ | ✗ | ✗ |
| Document (generic) | draft only | ✓ new version | ✓ number retired | ✗ | ✗ | ✗ | ✗ | ✗ |
| Invoice unbilled | draft only | ✓ | ✓ | ✗ | ✗ | ✗ | ✗ | ✗ |
| Invoice billed | ✗ | C (BR-CR-03) | C (BR-CR-03) | ✗ | via sales return | ✗ | ✓ | ✗ |
| Payment / Disbursement / Expense | ✗ | ✗ | ✗ | contra-fact | ✗ | applications only | ✓ | ✗ |
| Stock movement | ✗ | ✗ | ✗ | ✓ linked | ✓ (commercial) | ✗ | ✓ | ✗ |
| Reservation | ✗ | adjust (audited) | release | ✗ | ✗ | ✗ | ✗ | ✗ |
| Delivery | pre-commit | ✗ | ✗ | ✓ correction record | sales return | ✗ | ✓ | ✗ |
| Admin requirement | ✓ pre-satisfaction | ✗ | N/A waiver (eligible only) | ✗ | ✗ | ✗ | reopen | ✗ |
| Master data / Company | ✓ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✓ (carve-out BR-ACC-03) |

The step-by-step procedural correction matrix (who does what, in which order, per downstream situation) is P3 work under GAP-005; this table fixes legality only.

## 3. State models

**Strategy (PLANNER-DETERMINED):** a stored state exists only where an explicit business decision creates truth that facts cannot derive. Everything quantitative or progressive is derived from immutable events and constrained by rules on those events. No universal status enum (FS-01). P4 may materialize derived values; events remain the truth (BR-INV-08).

### SM:Project

States: `DRAFT` → `ACTIVE` → (`CANCELLED` | `COMPLETED_NORMAL` | `COMPLETED_FORCED`); revalidation reopens both completed states → `ACTIVE`.

| Transition | Trigger | Preconditions |
| --- | --- | --- |
| DRAFT → ACTIVE | Project created/confirmed for work | Company, client/unit/PIC, channel set |
| ACTIVE → CANCELLED | Explicit cancellation decision | All reservations released; downstream corrections per Section 2; reason + audit |
| ACTIVE → COMPLETED_NORMAL | Completion decision | **All ten gate predicates true** (BR-PRJ-02) |
| ACTIVE → COMPLETED_FORCED | Owner Force Complete | BR-PRJ-04 requirements; unmet predicates recorded |
| COMPLETED_* → ACTIVE | Material correction (BR-CR-05) | Automatic revalidation requirement |

Prohibited: completion with any unresolved predicate outside Force Complete; Admin or API Force Complete; deletion of any project with committed history.

- **BR-PRJ-01 (E-APP).** Cancellation at any stage releases reservations and requires the Section-2 companion corrections for delivered/billed/paid effects; nothing is silently unwound.
- **BR-PRJ-02 (E-APP).** The ten completion predicates evaluate over facts + decisions only (ACCEPTANCE_CRITERIA completion table): fulfillment (delivered/handovered or remaining-scope cancellation recorded); delivery (complete or formally closed, no open discrepancy); returns/corrections (all correction cases closed, BR-CR-04); documents (required company documents issued/final); administration (required items complete or validly waived); billing (every issued non-void invoice of the project billed or voided); receivable (each project invoice settled — payments + fee settlements + tax settlements (`DIR-024`) — or Owner-disposed; a disputed balance blocks); stock (no incomplete movement, no open purchase remainder without closure); payment (no undisposed contra-fact or pending reallocation); audit (required audit records present).
- **BR-PRJ-03 (E-APP).** Admin may satisfy normal requirements within granted permissions and may mark only waiver-eligible administrative requirements TIDAK BERLAKU, with reason + individual actor + audit (DIR-011). **PLANNER-DETERMINED:** in the two-role V1, only Owner sets a requirement's waiver eligibility (default non-waivable); Admin only exercises the waiver. Rationale: otherwise Admin could manufacture their own bypass of the DIR-011 boundary.
- **BR-PRJ-04 (E-APP).** Owner-only Force Complete requires explicit confirmation, mandatory reason, individual actor, timestamp, before/after state and audit; it preserves unmet conditions and residual obligations visibly, fabricates no stock/cash/settlement/document, and cannot omit its own audit. Surviving reservations of a force-completed project remain visible in the action queue as release-required residuals.
- **BR-PRJ-05 (E-APP).** Channel is fixed per project (CAP-04/PRODUCT_OVERVIEW journey). PLANNER-DETERMINED mutability window: it may change, audited, only before the first channel-dependent committed record — later change would falsify committed channel metadata snapshots. SIPLAH metadata are recorded manual values (CAP-12); no core operation depends on SIPLAH availability.
- **BR-PRJ-06 (E-APP).** Commercial confirmation is the reservation trigger: normally quotation approval; where no quotation exists, an explicit confirmation decision with client order evidence attached (client SPK/Surat Pesanan as external attachment). No separate Order entity exists (PLANNER-DETERMINED; DIR-011 "quotation/order approval" wording).

### SM:Quotation

States: `DRAFT` → `SENT` → (`APPROVED` | `REJECTED` | `EXPIRED`); any non-draft state → new `DRAFT` revision (chain preserved).

- **BR-QUO-01 (E-APP).** Approval is a decision by an authorized individual; rejected/expired quotations are never silently approved; validity dates enforce EXPIRED.
- **BR-QUO-02 (E-APP).** Revision after approval creates a new revision requiring re-approval; confirmed quantities changing MUST release/adjust the affected reservations (OWN-04).
- **BR-QUO-03 (E-APP).** A quotation alone never changes any stock quantity or money (DIR-011).

### SM:Document (all DOC-01–14; Invoice is a specialization)

States: `DRAFT` → `ISSUED/FINAL` → (`REVISED` — new version | `VOIDED`).

- **BR-DOC-01 (E-DB).** An issued version is an immutable snapshot (content + company identity assets + number). Revision creates a new version; prior versions remain retrievable. Void preserves existence and permanently retires the version's number.
- **BR-DOC-02 (E-DB).** Numbers are unique per company/type/period; the period follows the document's **business_date**; a backdated document draws the next available number of its business-date period; sequences are append-only — NEVER insert between issued numbers, renumber, reuse a consumed number, or rewrite historical sequences. Chronological order and number order may differ (DIR-018). NEVER `MAX(number)+1` (OB §14); concurrency mechanism is P6.
- **BR-DOC-03 (E-APP).** Render artifact ≠ issue: rendering is derived and retryable and reproduces the issued snapshot; a failed/pending render never re-issues or renumbers (GAP-007). Issuing requires valid inputs and truthful issuer; layout selection has zero business effect (AC-08).
- **BR-DOC-04 (E-APP).** One business record, many layouts: one procurement order record renders as DOC-02 or DOC-14; Kuitansi (DOC-05) as a receipt requires the referenced real payment; `DIR-024` a *Kuitansi untuk Proses Pembayaran* may be issued before payment when legitimately required for payment processing — it creates no Cash-In, Payment fact or settlement, does not mark the invoice paid, stays distinguishable from evidence of money received, is linked to the actual Payment when it occurs, remains immutable under the revision/void rules and never causes duplicate financial recognition; BAST (DOC-08) and Berita Acara Pemeriksaan Barang (DOC-10) render user-confirmed facts, never auto-certification. Company-prepared SPK/HPS/Surat Pesanan carry truthful company-issuer/draft identity; a company draft NEVER satisfies a required client-issued original; external documents are authentic attachments with issuer metadata and are never generated (V1_SCOPE document catalog; GAP-019 owns content validation).

### SM:Reservation

States: `ACTIVE` → (`RELEASED` | `CONSUMED`); quantity adjustments while ACTIVE are audited events.

- **BR-RSV-01 (E-DB).** Created only from confirmed demand (BR-PRJ-06) and only against usable existing supply: at every commit, AVAILABLE ≥ 0 (equivalently Σ active reservations ≤ ON HAND − UNUSABLE). Two concurrent demands NEVER both obtain the final unit; the atomic mechanism is P6 (AC-16/22, GAP-023).
- **BR-RSV-02 (E-APP).** Reservation is never a physical movement; dispatch consumes it (one physical effect); cancellation, quantity reduction or no-longer-needed releases/adjusts it; no timed holds.
- **BR-RSV-03 (E-APP).** A purchased shortage is not reservable before receipt; it satisfies the remaining demand at receiving (AC-22 mixed supply). PLANNER-DETERMINED: reservations bind existing supply only.
- **BR-RSV-04 (E-APP).** When ON HAND − UNUSABLE falls below Σ active reservations (damage, opname loss), the same atomic operation MUST include an explicit human decision cutting specific reservation(s). PLANNER-DETERMINED: the cut is a human choice, never an automatic priority — an automatic rule would be the allocation engine DIR-011 excludes and would invent business priority between projects. The affected project demand is flagged for action. A unit is never counted in RESERVED and UNUSABLE simultaneously (DIR-011 no-double-subtraction).

### Decision events without state machines

Billing act; delivery formal closure; purchase-remainder closure; remaining-scope cancellation; correction-case closure; N/A waiver; receivable formal disposition; commercial confirmation; Force Complete. Each is a single audited decision with defined preconditions (rules above/below), not a lifecycle.

### Derived statuses

Receivable (unbilled-visible → active → settled → disposed), payment application state, purchase receipt progress (header decisions OPEN/CLOSED/CANCELLED only), delivery completeness, requirement satisfaction, and all stock quantities are derived from facts + decisions. Rules constrain the underlying facts; P4 may materialize views; reconciliation to events is mandatory (BR-INV-08).

## 4. Invariant catalog

### Inventory (INV)

- **BR-INV-01 (E-DB).** AVAILABLE = ON HAND − RESERVED − UNUSABLE and AVAILABLE ≥ 0 at all times; the three subtrahend categories are disjoint in effect (OWN-04; AC-22 fixture: 10/3/2 → 5).
- **BR-INV-02 (E-DB).** Physical stock changes only through legitimate movements; dispatch is the single physical subtraction for warehouse fulfillment — dispatch + delivery NEVER subtract twice; delivery never touches stock (AC-07).
- **BR-INV-03 (E-DB).** All quantity arithmetic is in base units. Transactions record entered unit + conversion snapshot + base equivalent; demand lines pin their conversion snapshot at confirmation; master ratio changes never rewrite history. Serialized products: base unit = piece, integer; fractional base quantities only where the unit warrants (fixed decimal scale, P4). PLANNER-DETERMINED model for the DIR-017 multi-unit requirement: base-unit ledger truth with pinned snapshots is the only representation that keeps one quantity truth across ratio changes.
- **BR-INV-04 (E-DB).** Every acquisition (receiving, opening stock, drop-ship return) creates exactly one receiving lot carrying source company, source purchase/import identity and actual cost (`DIR-024`: actual acquisition cost per BR-PUR-05 and BR-FIN-17); lot consumption ≤ lot quantity. PLANNER-DETERMINED: dispatch/allocation consumes identified lots — the system suggests oldest-first as a *picking aid only*, the operator confirms; attribution records the actual lots chosen (grounds: DIR-011's "actual/direct cost" plus mandatory provenance links; no FIFO/average valuation method exists or is implied). Positive opname variance creates a lot with an explicit, flagged cost basis.
- **BR-INV-05 (E-DB).** Inter-company stock allocation is an explicit auditable record (source company, consuming company/project, product, base qty, pinned lot cost, reason, individual actor, timestamp) conserving quantity and cost against lots; provenance is never silently reassigned; no automatic inter-company invoices, journals or cash transfers (OWN-05).
- **BR-INV-06 (E-APP).** Purchase return quantity ≤ lot quantity − lot consumption; returning allocated stock first requires an audited allocation reversal. Damaged sales returns land in UNUSABLE, never AVAILABLE.
- **BR-INV-07 (E-APP).** Opname records the counted basis; variance is applied only through a reasoned adjustment event; stale counts are detected against the basis (mechanism P6, GAP-009). Balances are never edited directly.
- **BR-INV-08 (E-DB).** Every derived quantity is recomputable from the event ledger; a materialized balance that cannot reconcile is a defect.
- **BR-INV-09 (E-APP).** Barcode resolves to at most one active product; unknown/ambiguous scans never silently select (AC-03). A serial is unique while in stock; the same serial cannot be received twice while present; serial traceability survives corrections.
- **BR-INV-10 (E-APP).** Minimum-stock/restock guidance is advisory over reconciled usable availability; it never creates a purchase (AC-22).
- **BR-INV-11 (E-APP).** Drop-ship and services create zero warehouse events. Drop-ship confirmation carries supplier, quantity, actual cost, serials where applicable and evidence; a drop-ship return to warehouse creates a lot carrying the original purchase cost (AC-06/20; GAP-021).
- **BR-INV-12 (E-APP).** Opening stock enters as opening lots with owning-company provenance, cost, migration-time conversion snapshot and import identity; reruns are idempotent by that identity; archived history is never replayed as current effects (CAP-15, AC-17, GAP-010).
- **BR-INV-13 (E-APP) — `DIR-024` D-3.** Disposal, shrinkage, loss in transit and client-damaged returns never make economic cost disappear: the loss is recorded exactly once at the relevant actual or attributed lot cost, charged to the project when the causal project is known and otherwise to the economic/owning company, preserving quantity movement, provenance, reason, evidence where available, individual actor, timestamp and audit. The same cost is never counted as both HPP and loss unless the underlying facts represent distinct amounts; where the cost already sits in a project's HPP, recognition reclassifies it (contra-attribution + loss) instead of adding it again. `DIR-026`: where evidence and provenance cannot establish which legal company economically owns a lost or condition-changed fungible quantity, no FIFO, oldest-first, proportional or other automatic rule decides it and no default attribution is made; the physical correction may be recorded while the company attribution stays explicitly pending, inside no company's profit, until the Owner attributes the case (orchestration in [WORKFLOWS](WORKFLOWS.md) SF-UNATTRIBUTED).

### Procurement (PUR)

- **BR-PUR-01 (E-APP).** A purchase records actual supplier, quantities, actual prices, applicable tax/shipping; company is mandatory; project is optional — a projectless purchase is warehouse replenishment (PLANNER-DETERMINED; grounds: restock advisory CAP-06 + the no-dummy-records principle GAP-021; CAP-05 "company/project references" read as references, not both mandatory).
- **BR-PUR-02 (E-APP).** PO is optional and never a precondition; multiple suppliers/POs may serve one project without duplicating demand (CAP-05, AC-05).
- **BR-PUR-03 (E-APP).** Purchase creation alone changes neither cash nor stock (DIR-011); receiving events change stock; disbursements change cash.
- **BR-PUR-04 (E-APP).** An unfillable remainder is ended by an explicit purchase-remainder closure decision (reason + audit); completion and reporting read the closure, not a guess.
- **BR-PUR-05 (E-APP) — `DIR-024` D-2.** A purchase charge directly attributable to acquiring goods and bringing them to their usable/available condition or destination is part of actual acquisition cost; when it applies to several goods lines or lots it is allocated proportionally on a deterministic basis appropriate to the charge, recorded explicitly and auditable, with conservation and BR-FIN-13 rounding. A charge not directly attributable to inventory acquisition is an expense — attributed to the project where the causal relationship is known, otherwise to the relevant company. The same charge is never counted both in lot/project cost and as expense. No schema or GL design is implied.

### Finance (FIN)

- **BR-FIN-01 (E-APP).** The five concepts stay distinct: Sales/Transaction Value ≠ Active Receivable ≠ Cash-In ≠ Cost/HPP ≠ Cash-Out (OWN-01). No formula converts SPJ/SIPLAH/reference values into any of them (AC-11).
- **BR-FIN-02 (E-APP).** Invoice issue snapshots the agreed Sales/Transaction Value and implies no cash (`DIR-024`: output-tax components are recorded separately and are not operating revenue, BR-FIN-17); issued-unbilled invoices remain separately visible as BELUM DITAGIHKAN. The billing act (invoice-level; billed_at + due_date) activates only the unpaid remainder (AC-10).
- **BR-FIN-03 (E-DB).** Payment-application conservation: Σ applications(payment) ≤ payment amount, atomically, across all target kinds (issued invoice — incl. unbilled, never drafts; refund disbursement; correction credit). Applications bind the same client and same company as the payment. Reallocation is a superseding application under the same law; a payment never disappears through reallocation (AC-10/12/16).
- **BR-FIN-04 (E-DB).** Active outstanding per invoice = billed value − payment applications − fee-deduction settlements − tax settlements (`DIR-024`, BR-FIN-16) − write-off disposals, and ≥ 0 always; Σ(applications + fee and tax settlements + disposals) ≤ billed value. Advances are never re-counted at billing; no negative receivable exists (OWN-01).
- **BR-FIN-05 (E-APP).** Customer credit = unapplied payment remainders + explicit correction credits + opening customer credit carried by migration (`TECH-016`, BR-XD-06); every spend of credit is an application; refund is a disbursement consuming a specific application; overpayment is never profit (AC-10).
- **BR-FIN-06 (E-APP).** Wrong-amount or wrong-record payments/expenses/disbursements are corrected by contra-facts (BR-CR-02); zero/negative normal payments are rejected (AC-10).
- **BR-FIN-07 (E-APP).** Cross-company application is prohibited. Recovery for money received into the wrong company: (a) refund from the receiving company and a fresh payment to the right one, or (b) record the actual inter-bank transfer as Cash-Out(A) + Cash-In(B), then apply — facts only when money really moved. (Not marked PLANNER-DETERMINED: this is a settled consequence of DIR-011's exclusion of automatic cross-company settlement and of company-scoped attribution, not a planner choice.)
- **BR-FIN-08 (E-APP).** Receivable formal disposition (DIR-019): Owner-only write-off removes the disposed amount from Active Receivable and collectible aging, preserves it permanently as disposed history, creates no Cash-In/profit/pretended payment, and requires amount, reason, individual actor, timestamp, before/after and audit linkage; managerial treatment only. Dispute hold keeps the receivable active and aging (from due_date) with a disputed reason and does not settle. Money genuinely received outside the normal flow is reconstructed as a real audited Payment fact (backdated to its real business_date per BR-DT rules) and applied normally — never written off.
- **BR-FIN-09 (E-APP).** Fee-deduction settlement (DIR-019; generalized by explicit Owner decision **DIR-020**): when an intermediary verifiably remits net of a deduction — a SIPLAH platform fee, or another verified intermediary deduction such as a legitimate bank/VA/payment-intermediary fee — one audited record settles that portion of the receivable, subject to all DIR-020 conditions: the deduction is real and evidenced; the gross transaction/receivable value is unchanged; Cash-In records only money actually received; the settlement is non-cash (no Cash-In/Cash-Out); the deduction is represented as project-attributed expense/cost **exactly once** and can never be double-counted elsewhere; actor, evidence, amount, timestamp and audit linkage are preserved. Owner write-off is never required merely because remittance was net of fee. DIR-020 authorizes no arbitrary deductions or invented formulas and does not extend to unsupported cashback, discounts, write-offs or other unrelated financial treatments.
- **BR-FIN-10 (E-APP).** No cashback/pengembalian formula exists. Actually received cashback/pengembalian money is a legitimate categorized Cash-In fact; recorded SIPLAH metadata alone never moves money (DIR-019, CAP-12).
- **BR-FIN-11 (E-APP).** Cash-Out exists only as a disbursement fact with its reference and evidence — a purchase, expense or refund, or (`TECH-016` clarification of existing meanings) a real inter-company transfer under BR-FIN-07 or a tax remittance under BR-FIN-17; purchase creation or cost attribution alone never creates it (OWN-01).
- **BR-FIN-12 (E-APP).** HPP attribution records actual/direct cost to the consuming project at: warehouse dispatch (pinned actual lot cost), inter-company allocation, drop-ship confirmation, or project-attributed purchase/expense; corrections are contra-attributions; nothing is counted twice (OWN-01/05; GAP-003/004).
- **BR-FIN-13 (E-APP).** PLANNER-DETERMINED rounding convention (GAP-004 assigns rounding formalization to P2): splitting a source cost across base-unit consumptions uses deterministic residual-absorbing rounding — allocate the rounded per-unit amounts and assign the remainder to the final consumption so Σ attributed = source cost exactly (worked example in the annex). This is conservation arithmetic, not a valuation policy.
- **BR-FIN-14 (E-APP).** Opening receivables are first-class migration facts: they participate in the billed sum, are valid application targets, carry an explicit aging basis (real due_date or the `migrasi` bucket) and import identity for idempotent reruns (CAP-15, GAP-010).
- **BR-FIN-15 (E-APP).** Aging (PLANNER-DETERMINED): days past due_date, WIB day boundary, buckets current / 1–30 / 31–60 / 61–90 / >90; due_date is entered at billing (no payment-terms engine). Grounds: DIR-011 records billed_at + due_date and "overdue" is only meaningful past due.
- **BR-FIN-16 (E-APP) — `DIR-024` D-1.** Tax settlement: when a client, government treasurer, SIPLAH operator or other legitimate intermediary withholds or collects tax from an invoice payment and valid evidence exists, one audited record settles that evidenced portion of the receivable (PLANNER-DETERMINED granularity, WORKFLOWS L-28, marked `TECH-016`: one record per payment, invoice and tax type, anchored to the payment and its evidence identity). It creates no Cash-In for that portion; it is not a payment, not a write-off, not operating revenue and not automatically an expense; the gross transaction/invoice value stays historically intact; actor, amount, tax type, evidence, business_date and audit linkage to the payment and invoice are preserved; it is recorded exactly once. It is distinct from the fee-deduction settlement of BR-FIN-09.
- **BR-FIN-17 (E-APP) — `DIR-024` D-1.** Tax treatment follows the NET model with configurable treatment: output tax is not operating revenue; purchase tax enters inventory/project cost only when, for the relevant company and transaction, it is legitimately non-creditable or otherwise part of actual economic acquisition cost; treatment is configurable by company, transaction context, effective period/date and supporting evidence, with no hardcoded universal treatment or rate and no invented tax formula. Exact computation and configuration are later technical design (FS-13 unchanged).

### Administration (ADM)

- **BR-ADM-01 (E-APP).** Each project has its own checklist; requirements are satisfied by generation, upload, or either, per requirement; required generated forms cannot silently become attachment-only; a company draft never satisfies a required client original (CAP-09, AC-09).
- **BR-ADM-02 (E-APP).** TIDAK BERLAKU is legal only on waiver-eligible requirements, with reason + individual actor + audit; eligibility is Owner-set (BR-PRJ-03); ineligible-waiver attempts are denied (AC-09/21).
- **BR-ADM-03 (E-APP).** Material corrections reopen affected requirements and re-trigger completion revalidation (BR-CR-05).

### Access boundary (ACC)

- **BR-ACC-01 (E-APP).** Default deny. Owner: all companies, consolidated views, all attribution. Admin: explicit company grants + capabilities, server-side. With inventory permission an Admin sees pooled physical availability (sensitivity S1, quantity-only); the nine protected categories (S3/S4) stay denied outside grants; the pooled view authorizes no cross-company mutation (OWN-02). P5 builds the resource/field matrix from the [sensitivity classes](DOMAIN_MODEL.md#scope-and-sensitivity-classes).
- **BR-ACC-02 (E-APP).** Supplier price history, lot costs and allocation cost attribution are S3 company data even though the physical pool is shared; shared master lookup is never a route to them (GAP-006).
- **BR-ACC-03 (E-APP).** Company deactivation blocks new projects/invoices/purchases; collection continues — payments/applications on existing receivables and returns/corrections on existing records stay allowed; history and issued-document snapshots remain intact. PLANNER-DETERMINED carve-out: blocking collection would strand live debt and violate the settled rule that outstanding debt never disappears (grounds: OB §26 inactive-over-delete pattern + the AC-21/completion receivable invariant).

## 5. Calculation semantics

Meanings only; the annex gives the binding worked examples. No valuation, tax or recognition algorithm is defined anywhere in V1.

| ID | Calculation | Business meaning |
| --- | --- | --- |
| CALC-01 | Available stock | BR-INV-01, per product (× location), base units |
| CALC-02 | Remaining reservation | Reservation qty − consumed by dispatch − released/adjusted |
| CALC-03 | Remaining receiving | Purchase line qty − received (base units), until closure (BR-PUR-04) |
| CALC-04 | Remaining delivery | Demand line qty − delivered, until formal closure; delivered ≤ dispatched for warehouse goods |
| CALC-05 | Outstanding receivable | BR-FIN-04 per invoice; project/company totals are sums |
| CALC-06 | Customer credit | BR-FIN-05 per client + company |
| CALC-07 | Receivable aging | BR-FIN-15; disposed amounts leave collectible aging; disputed stays with indicator |
| CALC-08 | Project revenue | Σ issued non-void invoice values of the project (sales value basis; never cash), excluding output-tax components (`DIR-024`, BR-FIN-17) |
| CALC-09 | Project direct cost / HPP | Σ HPP attributions + project-attributed expenses, including project-attributed losses and non-attributable charges (`DIR-024`, BR-INV-13, BR-PUR-05) (each exactly once) |
| CALC-10 | Project profit / margin | Revenue − direct cost; margin = profit ÷ revenue (revenue > 0) |
| CALC-11 | Company profit (managerial) | Σ project profits + categorized non-project Cash-In-based income records − unattributed company expenses, including company-level losses (`DIR-024`, BR-INV-13); tax settlements are not income and become expense only through an explicit configured, evidenced treatment recorded once; acquisition-cost tax enters cost through the purchase (BR-FIN-16/17); PLANNER-DETERMINED (WORKFLOWS L-43, marked `TECH-016`): a tax remittance is Cash-Out (BR-FIN-11), remitted output tax never becomes cost under the NET model, and any other tax payment follows its configured, evidenced treatment |
| CALC-12 | Consolidated profit | Σ company profits; Owner-only |
| CALC-13 | Period views | PLANNER-DETERMINED convention: each concept on its own business_date — revenue by issue date, Cash-In by payment date, HPP by attribution date, Cash-Out by disbursement date; managerial convention, not an accounting standard |
| CALC-14 | Managerial cashflow | Cash-In facts vs Cash-Out facts by business_date and company |

**Explicitly undefined (FS-13):** any SIPLAH cashback/pengembalian formula, any stock valuation method, any tax computation algorithm, any GL construct.

### Numeric annex (binding examples for P8 fixtures; GAP-004)

1. **AC-10 baseline.** Invoice Rp1,000,000 issued → sales value 1,000,000; BELUM DITAGIHKAN; no Cash-In; no active receivable. Billed (billed_at/due_date) → outstanding 1,000,000. Payments 300,000 then 700,000 → Cash-In each; outstanding 700,000 then 0.
2. **Advance.** Payment 400,000 before billing, applied to the issued-unbilled invoice → Cash-In 400,000 once; billing later activates 600,000 only.
3. **Overpayment.** Payment 1,200,000 against outstanding 1,000,000 → application 1,000,000; unapplied 200,000 = customer credit; refunding it = one application of 200,000 to a refund disbursement (Cash-Out); the same 200,000 can never also be applied to an invoice (BR-FIN-03).
4. **Reallocation.** Application 400,000 moved from invoice A to invoice B = superseding application; payment total unchanged; both steps audited.
5. **Void after partial payment.** Invoice billed 1,000,000, applied 400,000, then voided → void commits only with disposition of the 400,000 (credit or re-target); no orphaned money (BR-CR-03).
6. **SIPLAH net settlement (DIR-019).** Gross 10,000,000; remitted 9,700,000; verified fee 300,000 → Cash-In 9,700,000 applied; fee-deduction settlement 300,000 closes the receivable and attributes 300,000 project expense once; project profit reflects revenue 10,000,000 − (other costs + 300,000); cashflow shows 9,700,000.
7. **AC-22 stock.** ON HAND 10, RESERVED 3, UNUSABLE 2 → AVAILABLE 5; confirmed reservation +2 → 10/5/2 → AVAILABLE 3; quotation alone changes nothing; dispatch of 5 reserved units → ON HAND 5, RESERVED 0, AVAILABLE 3 (one physical effect).
8. **Reservation bound.** ON HAND 10, UNUSABLE 2 → max reservable 8 (AVAILABLE ≥ 0); attempting 10 is rejected (BR-RSV-01).
9. **Shrinkage.** RESERVED 8, then opname loss of 2 usable units → same operation must cut chosen reservation(s) by 2 and flag the project(s) (BR-RSV-04).
10. **Multi-unit cost split.** Buy 1 box = 12 pcs @ Rp100,000 → per-pc 8,333.33…; dispatch 5 pcs to X and 7 to Y → X: 41,667; Y: 58,333 (residual absorber); Σ = 100,000 exactly (BR-FIN-13).
11. **Ratio change.** Demand "2 box" confirmed at ratio 12 → 24 base pinned; master ratio later 10 → fulfillment still compares in the pinned 24 base units (BR-INV-03).
12. **Inter-company attribution.** A's lot 10 @ 12,000; B's project allocates 6 → allocation pins 6 × 12,000 to B's project HPP; A's purchase return afterwards limited to 4 (BR-INV-05/06).
13. **Tax settlement (`DIR-024` D-1; illustrative configured values, not a tax rule).** Sales value 10,000,000 + output-tax component 1,100,000 → billed 11,100,000; operating revenue 10,000,000. The treasurer transfers 9,850,000 with evidence of 1,100,000 tax collected and 150,000 tax withheld → Cash-In 9,850,000 applied; two tax settlements 1,100,000 and 150,000; outstanding 0; payment + settlements = 11,100,000; no settlement is revenue, payment or expense (BR-FIN-16/17).
14. **Charge allocation (`DIR-024` D-2).** Lines A 10 pcs × 100,000 and B 30 pcs × 100,000; attributable freight 100,000 on a line-value basis → A 25,000, B 75,000 → both lots 102,500 per piece; a non-attributable 20,000 administration fee is expense only (BR-PUR-05).
15. **Loss in transit (`DIR-024` D-3).** Two units dispatched to project X at 50,000 (HPP 100,000); one lost in transit → contra-attribution 50,000 + loss 50,000 on X; project cost stays 100,000, one unit delivered, nothing counted twice (BR-INV-13).
16. **Client-damaged return (`DIR-024` D-3).** One delivered unit (lot cost 50,000) returns damaged → back into UNUSABLE on its lot with HPP contra 50,000 and an open case naming the project; on disposal the loss 50,000 is charged to that project once (BR-INV-13).

## 6. Cross-domain interaction rules

- **BR-XD-01.** Return after delivery/billing/payment = linked but distinct corrections (stock + delivery + financial), each explicit, none automatic; the case ends with correction-case closure; completion revalidates (GAP-005 boundary; procedure P3).
- **BR-XD-02.** Existing-stock orders never require a dummy purchase; service-only projects never create warehouse movements; direct supplier delivery never creates fictitious receipts (AC-20; GAP-021).
- **BR-XD-03.** One project may mix warehouse, drop-ship and service items; each demand line's fulfillment mode decides which domains it touches.
- **BR-XD-04.** Document generation reuses confirmed project/transaction data; generating any layout changes no stock, cash or obligation (AC-08).
- **BR-XD-05.** Committed payments, stock changes and corrections update dashboards/queues/reports without waiting for completion; every displayed figure resolves to a CALC-ID + scope filter (CAP-17, AC-23).
- **BR-XD-06.** Migration: opening lots, opening receivables and opening customer credit are the only day-one effects (`TECH-016`, PLANNER-DETERMINED to carry AC-17's opening obligations: opening customer credit is an advance held at cutover per client and company, with import identity and signed opening evidence — an application source, never a live-period Cash-In); legacy history is identified archive; reruns are idempotent by import identity; live-period overlap rules are P10 (GAP-010).
- **BR-XD-07.** Where two operations race (final-unit reservation/dispatch, numbering, payment application, lot consumption), P2 fixes the truth that must hold; P6 owns the atomic mechanism; P8 proves it with real concurrent assertions (AC-16).

## 7. Forbidden shortcuts

- **FS-01.** No universal status enum; no lifecycle beyond those defined here.
- **FS-02.** No hard-delete or cascade-delete of referenced business history.
- **FS-03.** No stock subtraction at delivery, printing or layout selection; dispatch is the only physical subtraction for warehouse goods.
- **FS-04.** No Cash-In from issuing, no Cash-Out from purchasing, no settlement from printing a Kuitansi.
- **FS-05.** No fake warehouse events for services or drop-ship; no dummy purchases for existing stock.
- **FS-06.** No master-price/ratio backfill into committed history; no renumbering; no number reuse.
- **FS-07.** No silent provenance reassignment; no automatic inter-company invoices/journals/cash transfers.
- **FS-08.** No credit-as-revenue; no overpayment-as-profit; no receivable that silently disappears (incl. at completion or deactivation).
- **FS-09.** No automatic reservation-cut priorities; no timed reservation holds; no allocation engine.
- **FS-10.** No cross-company payment application; no shared Admin identity in audit records.
- **FS-11.** No stored duplicate of a derived quantity as independent truth; materializations must reconcile to events.
- **FS-12.** No company draft standing in for a required client-issued original; no generated external document.
- **FS-13.** No invented SIPLAH cashback/pengembalian formula, stock-valuation method, tax algorithm or GL construct.
- **FS-14.** No feature cut, weakened integrity/authorization/testing/recoverability justified by the 18-day window (DIR-016; V1_REQUIRED policy).

## Open items

None owner-pending: OWNER_DECISION_REQUIRED = 0 (DIR-018/019/020; the P3 questions were decided by DIR-024 and DIR-026). Technical/evidence obligations remain in the [gap register](../00-governance/GAP_REGISTER.md) at their P3–P10 gates; this document's rules are the P2 inputs those gates consume. Workflow orchestration over these rules is owned by [WORKFLOWS](WORKFLOWS.md).

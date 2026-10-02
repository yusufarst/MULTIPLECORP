## 10. Derived signals and action queue

Signals are projections over facts and decisions (BR-XD-05), never stored truth. They feed the Owner dashboard, the Admin action queue and in-app alerts (CAP-17); P7 designs presentation.

| QS | Signal | QS | Signal |
| --- | --- | --- | --- |
| QS-01 | Confirmed WAREHOUSE demand not yet reserved | QS-12 | Unapplied customer credit (incl. credit linked to a written-off invoice); flagged unevidenced payments; bank statement lines not yet fully cited |
| QS-02 | Shortage → purchase needed | QS-13 | Claimed remittance deductions not yet evidenced (claimed − evidenced) |
| QS-03 | Received goods for a project awaiting reservation | QS-14 | Owner review queue: every ADM+ correction/closure, deduction settlements, overridden warnings |
| QS-04 | Reservations cut / project demand flagged | QS-15 | Completion blockers per project; invoicing above delivered value; confirmed value not yet invoiced |
| QS-05 | Open purchase remainders | QS-16 | Residual obligations of force-completed/cancelled projects (incl. release-required reservations) |
| QS-06 | Dispatched or shipped but not delivered | QS-17 | Low stock / restock advice |
| QS-07 | Open discrepancies and correction cases, incl. unbacked-refund and unresolved-deduction residuals | QS-18 | Count findings, stale counts, unattributed surplus (Owner) |
| QS-08 | Render pending/failed | QS-19 | Pre-payment Kuitansi not yet linked or voided |
| QS-09 | Issued invoices BELUM DITAGIHKAN | QS-20 | Routed blockers needing other-company grants |
| QS-10 | Overdue receivables by aging bucket | QS-21 | Duplicate warnings overridden or confirmed (payments, evidence, fungible stock and delivery events) |
| QS-11 | Disputed receivables | QS-22 | Pending unexplained-loss attribution cases (Owner) with their recognition snapshots, and their separate unattributed display in company stock and profit views (`DIR-027`: they block no consumption) |

## 11. Cross-company and multi-Admin rules

- **Single-company projects:** each project, its purchases, invoices, payments and documents belong to one company; rare needs use a linked project (OB §4) or the inter-company allocation (WF-INV-07). No inter-company invoice, journal, cash transfer or cross-company payment application is created (FS-07; FS-10).
- **Shared pool:** an inventory-permitted Admin sees pooled physical availability (S1 quantities) without other-company purchase costs, profitability, banks, invoices, billing, payments, financial records, private documents or unrelated transaction detail (BR-ACC-01/02). Any action that would change another company's records (cuts, reversals) needs a grant on that company or is routed (QS-20). Which company economically bears an unexplained fungible loss or condition change is decided only by the Owner (SF-UNATTRIBUTED; DIR-026).
- **Individual Admins:** records are never locked to their creator; any authorized Admin continues open work; concurrent edits of the same draft return CONFLICT to the stale editor; corrections by a different Admin are normal and the audit shows both accounts; a deactivated Admin's history keeps its attribution (BR-XC-02; DIR-018). No ownership locks exist beyond the invariant-driven exclusivity of section 9.

## 12. Planner-determined Level-1 decisions

Each decision preserves approved business meaning and is recorded here with its grounds (DIR-005/017/019 authority). Owner business decisions are DIR-024 D-1–D-5, DIR-026 and its clarification `DIR-027`, not Level-1. After the targeted conservation review (DIR-025), TECH-016 revised L-06/07/11/17/22/28/30/31/42/43/44 and added L-45–L-50; the draft L-42's automatic cross-company rule is superseded by DIR-026, and the retention safeguard of the approved L-42 by the Owner's clarification `DIR-027`.

| ID | PLANNER-DETERMINED decision | Grounds |
| --- | --- | --- |
| L-01 | Workflow progress is always derived; no workflow status fields | FS-01/11; BR-XD-05 |
| L-02 | Reservation is an explicit command, proposed at confirmation; no automatic priority | BR-RSV-01/04; FS-09 |
| L-03 | Inter-company allocation needs separately grantable authority and a consuming-company grant only; allocated cost visible as the consuming project's cost; source records hidden | BR-INV-05; BR-ACC-01/02; DIR-024 D-5 |
| L-04 | Per-line conservation: reserved remaining + net dispatched + cancelled ≤ pinned demand (and equivalents for drop-ship/service) | CALC-02/04; BR-RSV-01 |
| L-05 | Project-linked purchases belong to the project's company; cross-company payment is recorded as real transfer facts | BR-FIN-07; FS-07 |
| L-06 | Void/downward revision of any invoice carrying reductions disposes of all of them in the same action; settlements that cannot be re-recorded on the replacement stay explicit residuals (receivable leg released, a fee's expense kept once unless refunded) that block case closure | BR-FIN-03/04; BR-CR-03; BR-INV-13 no-vanishing principle |
| L-07 | Restorations cite original consumptions, are capped, match serials, never reactivate a consumed reservation, reverse the consumption's allocation and re-enter the lot at the contra amount | BR-INV-02/05/08/09; BR-FIN-12; SM:Reservation |
| L-08 | Supplier refunds for returned/cancelled/overpaid purchases are non-income Cash-In; rebates follow BR-FIN-10 | BR-FIN-01/10/11; FS-08 |
| L-09 | No committed fact has a business_date later than today (WIB); due/validity/deadline dates are attributes | DIR-018 rule 7; BR-DT-04/06 |
| L-10 | Cancellation follows BR-PRJ-01 literally: any stage, explicit companion corrections, retained facts stay true, debt stays collectable | BR-PRJ-01; FS-08 |
| L-11 | Closed residual-command list after completion/cancellation; "material" = changes a predicate input or project CALC value; on a CANCELLED project corrections apply without any state change | BR-CR-05; BR-PRJ-04; SM:Project (no transition out of CANCELLED) |
| L-12 | Normal completion is an explicit decision that a permitted Admin may take; dispute hold is Admin-recordable | BR-PRJ-02; DIR-011; BR-FIN-08 |
| L-13 | Three receiving outcomes; every stock event names its lot; usable/unusable tracked per lot and serial | BR-INV-01/04/09 |
| L-14 | Counts per product × location × condition (+ serials); any movement in scope after count start → recount | BR-INV-07; BR-DT-05; GAP-009 |
| L-15 | Stock increases only via receiving, opening lots, drop-ship returns, restorations, linked reversals of wrong decreases (≤ original) or Owner-attributed surplus; surplus reverses a prior loss only when evidence identifies the units | BR-INV-04/08 |
| L-16 | Project-level invoice cap; value-only DP/termin invoices allowed; above-delivered invoicing flagged | CALC-08; BR-FIN-02 |
| L-17 | Owner write-off with available same-client credit requires applying it first or a reason; money recovered after a write-off is applied in the same action as the Owner's supersession of the write-off by that amount; a dispute hold lapses at zero outstanding | BR-FIN-05/08; BR-CR-06 COMPENSATING |
| L-18 | One cost attribution per unit: stock at lot consumption; drop-ship at confirmation; non-stock purchase lines at purchase; allocation overlays the dispatch; materials used for services are stock or drop-ship lines | BR-FIN-12; CALC-09 |
| L-19 | DOC-03 renders the outbound shipment and its signed copy is delivery proof | BR-DOC-04; AC-07 |
| L-20 | Partial acceptance via quotation revision; the latest APPROVED revision governs; reductions below executed quantities need returns/revision first | BR-QUO-02; BR-PRJ-06 |
| L-21 | Mode re-plan by splitting the unstarted remainder (same commercial snapshot); company change only in a DRAFT without committed records | BR-XD-03; BR-PRJ-05 |
| L-22 | Retry = command identity; duplicate-payment heuristic independent of reference with ADM+ audited override; every bank payment cites its statement line and Σ Cash-In per line, net of contras, ≤ line amount at every commit, full citation being a reconciliation target; multi-client lines → per-client facts | BR-FIN-03/06; GAP-008 |
| L-23 | Excess is rejected until the source is amended (supplementary purchase, revision) | CALC-03/04 |
| L-24 | Operational-context tags and Indonesian journey anchors as P7 inputs | V1_SCOPE language contract |
| L-25 | Approval records the client's acceptance; a SENT revision past validity presents as EXPIRED and cannot be approved | BR-QUO-01; PX-02 |
| L-26 | The invoice record is the single sales-value record; DOC-06 Invoice and DOC-04 Nota are its layouts | BR-FIN-02; BR-DOC-04; CALC-08 |
| L-27 | A project with confirmed commercial value carries the invoice record as a default required item, satisfied only when non-void invoiced sales value = confirmed sales value less cancelled scope and returns | CALC-08; FS-08; AC-21 |
| L-28 | Deduction settlements are anchored to their payment and evidence (one per payment, invoice, class and specific type; Σ per evidence identity ≤ proven amount; payment + Σ ≤ claimed gross; collected output tax ≤ invoice tax component); moving an application off an invoice moves its anchored settlements or keeps them with reason in the same action | BR-FIN-09/16; BR-FIN-04 |
| L-29 | Payment proof required; an unevidenced payment needs a reason, is flagged, fails the audit predicate and its credit is not refundable; a company-issued document is never proof of receipt | BR-FIN-06; AC-10 |
| L-30 | A replacement invoice is named explicitly in the same void-and-replace or revision action — never inferred — and inherits billed_at/due_date; a billed void without replacement must cite the return, re-confirmation or cancellation ending the value; another due date only via CM-25 with entry-error evidence | BR-FIN-15; FS-08 |
| L-31 | Base unit = smallest counted unit, alternates exact multiples; quantities never rounded; cost residual goes to the event that zeroes the lot; restorations convert at the cited event's snapshot and take proportional cost shares, the residual going to the restoration that zeroes the consumption | BR-INV-03; BR-FIN-13; GAP-025 |
| L-32 | A decision recorded in error is corrected by a superseding decision of the same authority (COMPENSATING ACTION) | BR-CR-06 |
| L-33 | Opening import: Admin prepares and dry-runs; Owner commits after joint sign-off (reviewed plan baseline accepted by DIR-024) | AC-17; OS-02; C2 |
| L-34 | The last active Owner account cannot be deactivated or demoted | OB §17; continuity |
| L-35 | Switching a product to serialized needs zero stock or a registration count | BR-INV-03/09 |
| L-36 | Location moves are zero-net location-change movements | BR-INV-02; CALC-01 |
| L-37 | A DRAFT project with no committed downstream record may be discarded with audit; its number is retired | FS-02; BR-CR-06 EDIT |
| L-38 | Causal project for losses: damaged-return case, shipment in transit, or damage at receipt of a project-linked line; otherwise the owning company | DIR-024 D-3 |
| L-39 | Service handover is the delivery equivalent for SERVICE lines | BR-XD-02/03; FS-05 |
| L-40 | Drop-ship confirmation and delivery may be recorded in one command when proof arrives together | BR-INV-11 |
| L-41 | Replacing an external document re-evaluates the requirements and settlements that depended on it; reproductions of company-issued documents never count as client or third-party originals | BR-ADM-03; FS-12 |
| L-42 | Lots of lost or condition-changed fungible units are named from evidence (serial, location, condition, label, documented damage); unidentified units whose candidate lots all belong to one company are confirmed explicitly by the ADM+ actor, oldest-first shown only as a picking aid; across companies the Owner decides (DIR-026) against the immutable recognition snapshot, and `DIR-027` no stock is retained or frozen while the case is pending: the resolution closes lot claims in the scope — the assigned company's own lots first, any shortfall from other lots with an inter-company allocation — without re-pointing later movements; a later surplus in the same scope reverses the pending quantity first | BR-INV-04; DIR-026; DIR-027 (the earlier retention safeguard is superseded) |
| L-43 | A purchase charge is identified by its source bill (issuer + reference) whose total caps Σ charge lines and expenses citing it; acquisition-cost tax enters cost only through SF-CHARGE; a tax remittance is Cash-Out referencing its tax payment evidence, remitted output tax never becomes cost and other tax payments follow their configured treatment | DIR-024 D-1/D-2; BR-FIN-11/17 |
| L-44 | Allocation follows consumption: every restoration reverses its consumption's allocation; a loss or unrecovered cost charged to a project of another company than the lot records one allocation on that loss or return consumption; a reclassification without stock-out keeps the dispatch's allocation | BR-INV-05/06; FS-07; DIR-024 D-3 |
| L-45 | Evidence identity = issuer, document type, the issuer's reference and document date per company (bank line: account, date, amount, line reference); re-attaching the same identity versions it; reference-less documents raise a duplicate warning with ADM+ override | BR-FIN-09/16; GAP-008; FS-12 |
| L-46 | A refund-consumed application is final while its disbursement stands and is released only by that disbursement's contra; a payment contra may move it only to the re-recorded payment; an unbacked refund is an explicit case residual resolved only by a later real payment applied to it or by the disbursement's contra | BR-FIN-03/05/06; BR-CR-02; FS-08 |
| L-47 | An attributable charge allocates over ordered quantity with the open remainder's share pending; closure, cancellation or reversal of never-received units re-spreads it over the received quantity in the same action; Σ allocated = charge once every remainder has ended | BR-PUR-05; BR-FIN-13; DIR-024 D-2 |
| L-48 | A wrong serial or lot on a shipped dispatch is corrected by identity substitution of the same product and quantity, re-attributing cost and allocation at current net costs | BR-INV-02/05/09; BR-CR-06 REVERSAL |
| L-49 | Fungible stock and delivery events without an external identity raise a likely-duplicate warning (same line or case, quantity and business_date) that needs the actor's reasoned confirmation, listed in QS-21 | BR-XD-07; GAP-008; AC-16 |
| L-50 | Opening customer credit is a migration fact per client and company with import identity and signed opening evidence: an application source, never a live-period Cash-In | BR-XD-06; BR-FIN-05 (both clarified by TECH-016); AC-17 |

## 13. Downstream obligations

- **P4 (database):** represent every record named in the workflows, including charge lines with explicit allocation basis, pending shares and source-bill totals, lot usable/unusable remainders, allocation overlays on consumptions (net allocation = net cross-company consumption), pending unexplained-loss cases with their candidate lots, deduction settlements anchored to payments with evidence identities, refund-application finality, explicit invoice replacement links, pre-payment Kuitansi mode and links, correction cases with their residuals, opening customer credit, effective-dated tax configuration, number-sequence seeding and import identities; give every E-DB rule and AX invariant a database guarantee; keep derived values reconcilable (BR-INV-08).
- **P5 (security):** turn section 4 into capabilities, default grants and ADM+ authorities; physical-only lot projection; S3/S4 protection of costs, settlements and tax evidence; Owner review queue access; denial of Admin/API Force Complete, write-off and its supersession, surplus and unexplained-loss attribution (except an ADM+ resolution fully identified by definitive evidence, `DIR-027`) and required-item removal; content-level duplicate detection for uploaded evidence as a technical hardening of L-45.
- **P6 (concurrency/idempotency):** mechanisms for AX-01–37, stale-state detection, retry identities, likely-duplicate warnings (L-49), atomic recognition and resolution of pending unexplained-loss cases without any retention (`DIR-027`), render durability, count staleness and sequence allocation.
- **P7 (UX):** Indonesian journeys per workflow and context tag; presentation of COMMITTED/REJECTED/CONFLICT/PENDING outcomes, blockers, QS signals, the Force Complete confirmation, the pre-payment Kuitansi's distinct state and pending unattributed losses.
- **P8 (testing):** scenario families per workflow and CM row, using each row's expected end state and the P2 numeric annex; the targeted-review scenarios recorded in P3_QUALITY_GATE as fixtures; real concurrency tests per AX; denial tests per section 4.
- **P10 (release):** cutover freeze or delta (GAP-010) and opening reconciliation rehearsal.
- **P11 (build units):** units cite WF/SF/CM/AX IDs; FEATURE_COVERAGE_MATRIX consumes the P3 traceability recorded in P3_QUALITY_GATE.

## 14. Traceability and open items

Each workflow names its P1 and P2 anchors above; the aggregate CAP-01–18 / AC-01–23 / DOC-01–14 / OWN-01–06 / REF-IMG → BR/CALC → WF/SF/CM/AX matrix and its orphan checks are owned by [P3_QUALITY_GATE](../evidence/P3_QUALITY_GATE.md#aggregate-traceability). OWNER_DECISION_REQUIRED = 0 (DIR-024; DIR-026). Deferred, not open business choices: revision-number format (GAP-019), cutover freeze versus delta (GAP-010, P10), masking of a consuming project's own allocated cost (P5), exact tax configuration and computation mechanics (DIR-024 D-1 item 7; P4/P5).

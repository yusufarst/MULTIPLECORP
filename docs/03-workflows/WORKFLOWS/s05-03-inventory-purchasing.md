### 5.3 Inventory and purchasing workflows

Quantities: ON HAND (physical, including unusable) · UNUSABLE (subset of ON HAND) · RESERVED (claims on usable supply) · AVAILABLE = ON HAND − RESERVED − UNUSABLE ≥ 0 (BR-INV-01). Every stock event names its lot, and for serialized goods its serials; the lot of a serialized unit is derived from the serial, never chosen. Usable and unusable remainders are tracked per lot and per serial (L-13). The one exception is a pending unexplained-loss case (SF-UNATTRIBUTED; DIR-026): its physical effect names the candidate lots without choosing one until the case is resolved (`DIR-027`: without retaining or freezing any other stock meanwhile), so per scope ON HAND = Σ lot remainders − pending lost quantity and UNUSABLE = Σ lot unusable remainders + pending condition-changed quantity.

#### WF-INV-02 — Reservation management `[desk]`

- **Purpose:** bind usable existing supply to confirmed demand without physical movement (BR-RSV-01/02). **Actors:** OWN, ADM with inventory capability; cuts may need ADM+ grants (below).
- **Create/increase (AX-01):** explicit command on a confirmed WAREHOUSE line (L-02); quantity ≤ AVAILABLE and ≤ the line remainder under per-line conservation (L-04). A quotation, a draft or an unconfirmed line never reserves (BR-QUO-03).
- **Reduce/release (AX-02):** on quantity reduction, remaining-scope cancellation, re-plan, project cancellation or "no longer needed"; reason recorded; no timed holds (BR-RSV-02; FS-09). A release never exceeds the reservation's remaining quantity at commit: a racing dispatch consumes it or the release releases it, never both.
- **Consumption:** only by dispatch (WF-INV-03); a CONSUMED reservation is never reactivated — a restored unit may receive a new reservation (L-07).
- **Shortage after reservation:** any command that lowers usable supply below Σ active reservations (condition change, count loss, adjustment, receiving reversal) must carry an explicit human cut of chosen reservation(s) in the same action (SF-RSV-CUT; BR-RSV-04); the affected projects are flagged (QS-04). If a cut would touch a project in a company outside the actor's grants, the command is REJECTED and routed (QS-20) to the Owner or an actor granted on every affected company.
- **Purchased shortage:** never reserved before receipt; at receipt of a project-linked line the same receiving action offers a reservation for that line's unreserved confirmed demand (BR-RSV-03; AX-04).
- **Residual reservations** of force-completed or cancelled projects are release-required (QS-16; BR-PRJ-04).
- **Traceability:** CAP-06; AC-16/22; OWN-04; GAP-023. Later: P6 atomic availability and per-line checks; P8 last-unit races.

#### WF-PUR-01 — Project purchase `[desk]`

- **Preconditions:** project ACTIVE; purchasing company = the project's company (L-05); supplier active.
- **Main path:** create purchase: supplier, goods lines (product, quantity, entered unit + conversion snapshot, actual unit price, tax components and the tax treatment applicable to the transaction — company, transaction context, business_date, evidence — recorded on the line, DIR-024 D-1), purchase charges, each identified by its source bill (issuer + reference, with the bill's total) and classified (DIR-024 D-2) as *attributable acquisition charge* (with an explicit allocation basis) or *non-attributable charge* (expense), Σ charge lines and expenses citing one bill ≤ its total (L-43), link to project/demand lines, direct-to-client flag for drop-ship lines → optional PO (DOC-02 or DOC-14) through SF-ISSUE → purchase OPEN.
- **No money, no stock:** creating a purchase changes neither cash nor stock (BR-PUR-03); Cash-Out arises only from a disbursement (WF-FIN-07); stock only from receiving.
- **Branches:** several suppliers and purchases may serve one project without duplicating demand (BR-PUR-02); purchasing more than the shortage is allowed — the excess becomes free pool stock after receipt (visible, not reserved); a drop-ship line continues in WF-FUL-03 and is never received into the warehouse.
- **Amendment:** before any receipt, drop-ship confirmation or disbursement, goods lines are editable; non-stock lines, whose cost is attributed to the project at purchase (L-18), change only through CM-17 contra and re-attribution; afterwards additions use a supplementary purchase and cost errors use CM-17 (L-23).
- **Cancellation (ADM+):** header CANCELLED only with zero receipts, zero drop-ship confirmations and no performed or paid non-stock line; non-stock lines' HPP is contra-attributed in the same action (AX-12; PO voided); after partial receipt the unfilled remainder ends by purchase-remainder closure (ADM+; BR-PUR-04; CM-18), which re-spreads the remainder's pending charge shares in the same action (AX-32; L-47).
- **Records:** Purchase + lines + charge lines, PO document. **Invariants:** BR-PUR-01–04, BR-SN-02.
- **Traceability:** CAP-05/11; AC-05; DOC-02/14; REF-019/020; IMG-03. Later: P4 purchase/charge representation; P5 S3 cost protection.

#### WF-PUR-02 — Replenishment purchase `[desk]`

- Same as WF-PUR-01 without a project link (BR-PUR-01); prompted, never created, by the restock advisory (QS-17); goods lines only (a drop-ship always needs a project). Non-attributable charges are company expenses (D-2 item 6). Received goods become free pool stock usable by any project through reservation and dispatch.
- **Traceability:** CAP-05/06; AC-05/22; DOC-02/14; REF-028.

#### WF-INV-01 — Receiving (*Barang Masuk*) `[floor]`

- **Preconditions:** OPEN purchase line with a warehouse remainder; SF-SCAN for product identity; serials for serialized products.
- **Main path (per purchase line = one receiving event):** count and classify the arrived quantity — **usable** (movement into usable stock at a location), **damaged-accepted** (movement straight into UNUSABLE, never passing through AVAILABLE), **rejected at the door** (no stock event; recorded as a receiving discrepancy; the remainder stays open, is closed, or is returned) (L-13) → the event creates exactly one receiving lot: source company, source purchase line, base quantity and actual unit cost from SF-CHARGE (line price, non-creditable tax per D-1, allocated attributable charges per D-2) (BR-INV-04) → serials registered (unique while in stock, BR-INV-09) → evidence via SF-EVIDENCE → optional DOC-12 Nota Terima Barang → for a project-linked line, same-action reservation offer.
- **Partial:** any number of receiving events; remainder = CALC-03 until received or closed.
- **Exceptions:** over-receipt → REJECTED (supplementary purchase first; PX-03); unknown barcode → registration by an authorized user (WF-MD-04); a serial already in stock → REJECTED; duplicate submission → one effect (AX-04).
- **Corrections:** CM-02/03/04; receiving reversal (AX-05) only for the lot's unconsumed quantity, its charge share re-spread as never received (SF-CHARGE); consumed quantity first needs the dependent restoration or a case.
- **Traceability:** CAP-03/06; AC-03/06/22; DOC-12; REF-024; IMG-04. Later: P4 lot/serial representation; P6 AX-04/05; P7 scanner flow.

#### WF-INV-03 — Warehouse dispatch (*Barang Keluar*) `[floor]`

- **Preconditions:** confirmed WAREHOUSE line with an active reservation, or an unreserved remainder covered by AVAILABLE (reserve-and-consume in one action); usable lot remainder; serials in stock and usable.
- **Main path:** select the demand line → SF-SCAN products/serials → confirm lots (oldest-first suggestion as a picking aid only, shown through a physical-only projection; BR-INV-04) → one indivisible dispatch (AX-03): usable stock out, reservation consumed, lot consumption per lot, serial state out, HPP attribution at pinned lot cost (L-18), inter-company allocation overlay where the lot's source company differs (WF-INV-07) → DOC-03 Surat Jalan for the shipment (L-19) → goods leave; delivery follows in WF-FUL-02.
- **Partial:** multiple dispatches per line within per-line conservation (L-04).
- **Exceptions:** insufficient usable supply after a competing action → REJECTED; serial absent or unusable → REJECTED; another company's lot without inter-company authority → REJECTED and routed (QS-20); `DIR-027`: a pending unexplained-loss case never blocks a dispatch that the normal invariants allow; duplicate → one effect; a likely duplicate without a distinguishing reference → L-49 warning.
- **Corrections:** CM-05 wrong dispatch still in the warehouse; CM-06 dispatched goods returned undelivered; CM-07 loss in transit; CM-36 wrong serial or lot recorded on goods that have left.
- **Invariants:** BR-INV-01/02/04/05/09, BR-RSV-02, BR-FIN-12; FS-03. **Traceability:** CAP-06/07; AC-06/07/16/22; DOC-03; REF-025; dispatch DoD (ACCEPTANCE_CRITERIA).

#### WF-INV-07 — Inter-company allocation (embedded in dispatch)

- **Trigger:** a dispatch for Company B's project consumes a lot whose source company is Company A; or a loss or unrecovered cost of A's lot is charged to B's project (L-44).
- **Effect:** in the same action (AX-03, AX-11 or AX-30), the consumption carries an allocation record: source company, consuming company/project, product, base quantity, pinned lot cost, reason, individual actor, timestamp (BR-INV-05). It is not a second consumption and not a second cost (L-18). A loss charged to B's project records its allocation on the loss consumption (a new stock-out), and an unrecovered share of a purchase return records a cost-only allocation on the return consumption; a reclassification without stock-out (loss in transit) keeps the dispatch's allocation and adds none (L-44). No inter-company invoice, journal or cash transfer is created (FS-07).
- **Authority:** ADM+ inter-company authority plus a grant on the consuming company only; no grant on the source company is needed or implied (DIR-024 D-5; L-03). The allocated cost is visible as the consuming project's own cost; the source company's purchases, suppliers, prices and other lots stay hidden (BR-ACC-02). P5 decides any further masking.
- **Reversal:** only together with the consumption it overlays — every restoration (SF-RESTORE, damaged returns included), a dispatch identity substitution (CM-36), a reversal of an erroneous restoration (CM-37, which re-establishes it) or the exact inverse of a charged loss (SF-LOSS) — never as a standalone step.
- **Conservation (AX-10):** for every lot and every consuming project of another company, net allocated quantity = the units of that lot net-consumed by that project's dispatches and losses, and net allocated cost = the cost of that lot charged to that project (HPP, loss or unrecovered share). Restored units carry no allocation, so a later purchase return of the source lot needs no separate reversal and can use only physically present, unconsumed quantity (BR-INV-06).
- **Traceability:** CAP-06/11; AC-06/11/13; OWN-05; GAP-003/006.

#### WF-INV-04 — Condition change and quarantine `[floor]`

- **Main path:** usable → UNUSABLE naming lot/serials, reason and evidence; if usable supply would fall below Σ active reservations, SF-RSV-CUT in the same action (AX-06). Fungible units whose lot cannot be identified from evidence are confirmed by the actor when the candidate lots belong to one company (L-42) and otherwise follow SF-UNATTRIBUTED (DIR-026). UNUSABLE → usable only by an explicit re-inspection decision with reason. Disposal of unusable units continues in WF-INV-06.
- **Damaged client returns** keep their correction case open until repaired, returned to the supplier, or disposed with loss (WF-FUL-05; SF-LOSS); the restoration already reversed any inter-company allocation, so repair leaves a plain unit of the source lot.
- **Authority:** ADM+ (DIR-024 D-5). **Traceability:** CAP-06; AC-22; REF-024/029; GAP-009/023.

#### WF-INV-05 — Stock opname `[floor]`

- **Main path:** open a count scope (product × location × condition, plus serials) → record counted quantities against the system basis at count start → variance application (ADM+) re-reads the scope; any movement recorded in that scope after count start makes the count stale → recount (L-14; recorded order per BR-DT-05).
- **Negative variance:** decrease adjustment naming lots identified by serial, location, condition, label or other evidence; unidentified units whose candidate lots all belong to one company are confirmed by the ADM+ actor with oldest-first shown only as a picking aid (L-42; BR-INV-04); when the candidate lots belong to several companies the decrease becomes a pending unexplained-loss case (SF-UNATTRIBUTED; DIR-026) — never an automatic FIFO, oldest-first or proportional attribution → SF-RSV-CUT if needed → SF-LOSS in the same action with the causal-project rule (L-38), except for a pending case; a unit under an open case becomes that case's single loss outcome (DIR-024 D-3).
- **Positive variance:** a surplus in a scope with a pending unexplained-loss case first reverses that pending quantity exactly (no lot or company touched); a prior loss or disposal is reversed only when a serial, a single candidate lot/company or entry-error evidence identifies those units (linked reversal restoring the original provenance); any other surplus becomes one open count finding per scope and product (QS-18) that the Owner alone attributes to an owning company with an explicit flagged cost basis, creating a flagged lot (DIR-024 D-5; BR-INV-04; L-15). Until attributed the units are not added to ON HAND, every later count basis of that scope includes the pending finding, and an attribution made after a later count started makes that count stale (AX-07).
- **Unexpected serials** (e.g., a serial recorded as dispatched) become count findings resolved through the CM rows — typically CM-36 when a dispatch recorded the wrong serial — never silent re-registration.
- **Traceability:** CAP-06; AC-06/16; REF-027; GAP-009.

#### WF-INV-06 — Inventory adjustment, disposal and loss `[floor]`

- **Main path:** decreases only (L-15): disposal of unusable units, confirmed shrinkage, other physical loss — naming lot/serials, reason and evidence → one action (AX-06): stock out, SF-RSV-CUT if usable supply is reduced, and SF-LOSS recording the loss exactly once at the current net lot cost, attributed to the causal project when one is established, otherwise to the owning company (DIR-024 D-3; L-38). Unidentified fungible units follow L-42 within one company; across companies the stock-out commits with a pending unexplained-loss case instead of a loss (SF-UNATTRIBUTED; DIR-026), and disposing of units held by a pending condition-change case turns it into a pending loss case with the same candidates.
- **Increases** never use adjustment: they come only from receiving, opening lots, drop-ship returns, restorations, linked reversals of a wrong decrease (≤ the original, same lots/serials/condition; CM-28), the exact reversal of a pending unexplained-loss case (SF-UNATTRIBUTED) or Owner-attributed count surplus.
- **Authority:** ADM+. **Traceability:** CAP-06/11; AC-06/11; REF-027/029.

#### WF-PUR-03 — Purchase return to supplier `[floor]`

- **Preconditions:** units of that supplier's purchase lot physically present and unconsumed (usable or unusable); allocated units cannot be "unallocated" in place (BR-INV-06).
- **Main path:** open SF-CASE → select lot/serials → stock out to supplier (lot consumption by return, never HPP), with SF-RSV-CUT in the same action if usable supply would fall below reservations (cross-company cuts routed, QS-20) → declare the outcome: **replacement** (the purchase expects the replacement quantity; a new receipt creates a new lot) or **refund** (supplier refund recorded as non-income Cash-In when money arrives, WF-FIN-07; L-08) → purchase-value contra only for what the supplier refunds or replaces; any unrecovered share (e.g., freight, restocking fee, partial refund) is recognized once through SF-LOSS on its bearer (causal project per L-38, with a cost-only allocation when its company differs from the lot's, L-44) → record-bound documents as needed → case closure only when the returned units' current net cost = value refunded (received as non-income Cash-In, or evidenced as a supplier credit against the unpaid purchase value) + value replaced (units re-received on the purchase line) + unrecovered share recognized through SF-LOSS; an expected refund neither received nor evidenced keeps the case open (AX-11).
- **Authority:** ADM+. **Invariants:** BR-INV-06, BR-INV-13, BR-CR-04, BR-FIN-11/12. **Corrections:** a return recorded in error → CM-37. **Traceability:** CAP-06; AC-06/12; REF-029.


### 5.4 Fulfillment workflows

#### WF-FUL-02 — Delivery (*Pengiriman*) `[both]`

- **Preconditions:** shipped quantity not yet delivered: warehouse lines = dispatched − delivered − returned undelivered − closed as lost; drop-ship lines = confirmed shipments not yet delivered. SERVICE lines use handover (WF-FUL-04) as their delivery equivalent and need no delivery record (L-39).
- **Main path:** record delivery against the shipment(s): delivered quantity per line, recipient name, business_date, condition, proof (signed Surat Jalan copy, photo, signature upload) → any difference or condition problem creates a discrepancy record and opens SF-CASE → optional inspection/acceptance facts recorded from the client's inspection (rendered by DOC-10) → optional BAST (DOC-08) from confirmed handover/acceptance facts.
- **Partial:** repeated deliveries; remaining delivery = CALC-04.
- **Closure:** delivery formal closure (ADM+, reason) when shipped-but-undelivered quantity will not be delivered (refused and returned: CM-06; lost: CM-07); unshipped demand ends by further dispatch or remaining-scope cancellation (SF-CANCEL-LINE).
- **Exceptions:** delivered > shipped → REJECTED; duplicate → one effect (AX-09); a likely duplicate without a distinguishing reference → L-49 warning; delivery never changes stock (BR-INV-02; FS-03).
- **Corrections:** CM-08 wrong delivery record; returns → WF-FUL-05.
- **Traceability:** CAP-07; AC-07/21; DOC-03/08/10; REF-031; IMG-05.

#### WF-FUL-03 — Drop-ship / direct fulfillment `[desk]`

- **Preconditions:** DROP-SHIP demand line with a project-linked purchase line flagged direct-to-client.
- **Main path:** supplier ships directly to the client → drop-ship confirmation (AX-08): supplier, shipped quantity, actual cost (line price, non-creditable tax per D-1, allocated attributable charges per D-2), serials where applicable, supplier evidence → HPP attributed to the project at confirmation (BR-FIN-12; L-18) → optional company-issued DOC-03 for the direct shipment → delivery record (WF-FUL-02); one command may record confirmation and delivery together when the client's proof arrives with the supplier's confirmation (L-40).
- **Partial:** several confirmations; Σ confirmed ≤ purchase line and ≤ demand remainder; one confirmation per supplier shipment reference.
- **Isolation:** zero warehouse events; receiving/dispatch are never offered for the line (BR-INV-11; FS-05). Sourcing the remainder from stock uses the re-plan split (L-21).
- **Corrections:** CM-14 client returns goods to the warehouse (new lot at the HPP contra amount, i.e. the confirmation's current net cost incl. allocated charges and non-creditable tax); CM-15 client returns goods straight to the supplier; wrong confirmation → contra with reason (CM-27).
- **Traceability:** CAP-06; AC-06/20; REF-030; IMG-04.

#### WF-FUL-04 — Service fulfillment and handover `[both]`

- **Main path:** record service performance/handover facts per service line (business_date, scope performed, recipient/acceptance, evidence) → BAST (DOC-08) from the confirmed facts. Service cost arises only from non-stock project-attributed purchase lines or expenses, such as subcontracting (L-18); materials physically used for a service are WAREHOUSE or DROP-SHIP lines of the same project, never expensed while still in a lot.
- **Rules:** services never create stock events (BR-INV-11; FS-05); Σ handed over ≤ pinned demand; handover is the delivery equivalent for completion (L-39).
- **Traceability:** CAP-07; AC-20; DOC-08; REF-031.

#### WF-FUL-05 — Sales return (after delivery) `[both]`

- **Preconditions:** delivered quantity on the line; returned ≤ net delivered (PX-08); each returned serial must come from that line's dispatch (L-07) — when the dispatch recorded the wrong serial, CM-36 corrects it first.
- **Main path:** open SF-CASE (reason, evidence) → physical receipt of the returned goods through SF-RESTORE: **usable** → back into usable stock on the original lot; **damaged** → into UNUSABLE on the original lot; in both cases HPP contra and any inter-company allocation reversed together with the consumption (WF-INV-07), the case recording the causal project and the source lot → net delivered falls by the returned quantity (PX-08; the delivery record is not reversed) → demand: re-deliver (new dispatch), reduce through revision/re-confirmation, or remaining-scope cancellation → finance: before invoicing nothing; after invoicing/billing an invoice downward revision or void with disposition of every reduction (CM-12); after payment the released money becomes customer credit, refundable on request (CM-13) → record-bound documents revised or voided, affected requirements reopened → damaged units: outcome recorded — repaired (condition change to usable), returned to supplier (WF-PUR-03), or disposed with loss charged to the causal project (SF-LOSS; DIR-024 D-3), a new allocation on the loss consumption when the lot's company differs (L-44) → case closure (BR-CR-04) → SF-REVAL when the project was completed.
- **Drop-ship returns:** CM-14/15. **Return recorded in error:** CM-37.
- **Traceability:** CAP-06/13; AC-06/12/21; REF-029; BR-XD-01.

### 5.5 Documents and administrative workflows

#### WF-DOC-01 — Generated document lifecycle `[desk]`

- **Main path:** DRAFT (editable, unnumbered; a preparation draft prints with draft marking and never satisfies a requirement) → issue through SF-ISSUE: valid inputs, truthful issuer, the record-bound precondition from the table below, the next available number of the company/type/business-date period, immutable snapshot of content + company identity assets + number (BR-DOC-01/02) → render through SF-RENDER (BG; PENDING/FAILED; every retry reproduces the issued snapshot; BR-DOC-03).
- **Revision:** a new issued version linked to its predecessor, which stays retrievable and keeps its identity; a revision whose business_date falls in another period takes that period's next number. **Void:** retires the version and its number forever; never cascades to the underlying business record (BR-CR-06). Quotations are revised, rejected or expired, never voided.
- **Concurrency:** issuing a given draft/version is one effect; two Admins issuing different documents at the same time receive distinct numbers (AX-15).
- **Deactivated company:** revisions, voids and replacements of existing documents, and collection documents (DOC-05/07/13) for existing receivables, remain possible; other new business documents are blocked (BR-ACC-03).
- **Deferred format:** whether a revision shows a new sequence number or a base number with a revision suffix is validated under GAP-019, within the fixed constraint "unique and never reused".
- **Traceability:** CAP-08; AC-08/12; DOC-01–14; REF-032–036; GAP-007/016/019.

| DOC | Renders business record | Issue precondition | Notes |
| --- | --- | --- | --- |
| DOC-01 Penawaran | Quotation revision | Demand lines; company identity | Issue = SENT; never voided (L-25) |
| DOC-02 PO / DOC-14 Surat Pesanan | One purchase order record (project or replenishment) | Purchase exists | Two layouts of one record; each issued layout has its own type number; client-issued Surat Pesanan is an upload |
| DOC-03 Surat Jalan / Faktur Pengiriman | Outbound shipment: dispatch or drop-ship shipment | Dispatch or shipment committed | Signed copy is the delivery proof (L-19) |
| DOC-04 Nota / DOC-06 Invoice | The invoice (sales) record | Issued sales record within the project cap | Two layouts of one record; value, billing and receivable identical (L-26) |
| DOC-05 Kuitansi | (a) a recorded payment; (b) *Kuitansi untuk Proses Pembayaran* against an issued invoice | (a) payment fact; (b) issued non-void invoice with outstanding ≥ amount | Mode (b) creates no Cash-In, payment, settlement or paid state and is visibly distinct (DIR-024 D-4; WF-FIN-08) |
| DOC-07 Lampiran Kuitansi | Detail of its Kuitansi | Issued DOC-05 | Follows its Kuitansi's revisions/voids |
| DOC-08 BAST | Confirmed handover/acceptance facts | Handover or accepted delivery recorded | Never auto-certified by status or printing |
| DOC-09 SPK | Company-issued work/order agreement | Confirmed inputs; truthful issuer | Never impersonates a client SPK; draft marking until issued |
| DOC-10 Berita Acara Pemeriksaan Barang | Recorded inspection/acceptance facts | Inspection facts recorded (WF-FUL-02) | User-confirmed facts only |
| DOC-11 HPS | Company-prepared estimate | Confirmed inputs; truthful issuer | Client/third-party HPS is an upload |
| DOC-12 Nota Terima Barang | Receiving event | Receipt committed | Never a fictitious receipt |
| DOC-13 Surat Permintaan Pembayaran | Invoice + billing data | Issued non-void invoice (normally at billing) | No cash effect |

Generating any layout changes no stock, cash or obligation (BR-XD-04; AC-08).

#### WF-DOC-02 — External and uploaded documents `[both]`

- **Main path:** upload the authentic original (client SPK/Surat Pesanan, SIPLAH order, third-party HPS, faktur pajak, NPWP, NIB, bank statement, tax withholding/collection proof, delivery and payment proofs) through SF-EVIDENCE with issuer metadata and its evidence identity (L-45) into private storage (S4), linked to its record or requirement.
- **Replacement:** a new attachment version supersedes the old one; history is kept; dependent requirement satisfaction and settlements are re-evaluated (L-41).
- **Authenticity guard:** an upload identical to a system-rendered artifact never satisfies a client-original requirement; official external documents are never generated (FS-12).
- **Traceability:** CAP-08/09; AC-08/09; REF-034; IMG-06.

#### WF-ADM-01 — Administrative checklist (*Dokumen Administrasi* / SPJ) `[desk]`

- **Main path:** define per-project requirements at any time: type (catalog document, external document type or free description), required/optional, satisfaction mode generated/uploaded/either, client-original flag; waiver eligibility is set only by the Owner, default non-waivable (BR-PRJ-03) → satisfy: generated → an issued and rendered version of that type; uploaded → an authentic attachment from the correct issuer; either → one of them; client-original → only an authentic upload (BR-ADM-01) → Admin marks TIDAK BERLAKU only on waiver-eligible items, with reason (BR-ADM-02).
- **Authority:** Admin may add requirements and tighten them (make required, stricter satisfaction mode, client-original); deleting, making optional, relaxing the satisfaction mode or clearing the client-original flag of a required non-waivable item is the Owner-only override (DIR-024 D-5); a required waiver-eligible item is handled by N/A, not removal.
- **Default:** a project with confirmed commercial value carries the invoice (sales) record as a default required item (L-27), satisfied only when the project's non-void invoiced sales value equals its confirmed sales value less cancelled scope and returns; a lower value needs revision/re-confirmation (L-20); QS-15 shows confirmed value not yet invoiced.
- **Derived status:** required / prepared (draft only) / missing / satisfied / waived is derived, never stored separately. Material corrections reopen affected items (BR-ADM-03).
- **Deferred:** reusable client checklist templates (V1_SCOPE D-02).
- **Traceability:** CAP-09; AC-09/21; REF-037; IMG-06/07; GAP-019/022.


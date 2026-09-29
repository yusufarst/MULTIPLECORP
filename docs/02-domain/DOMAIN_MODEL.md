# Domain model

Status: REVIEW | Updated: 2026-09-29 | Owner: Planning

Authority: P2 authorized by DIR-016; deep-review directives and final business decisions under DIR-017/018/019/020 ([decision log](../00-governance/DECISION_LOG.md)). This document owns entities, terminology, conceptual relationships and ownership/scope for MultipleCorp. Normative rules, lifecycles, invariants and calculation meanings are owned by [BUSINESS_RULES](BUSINESS_RULES.md). Phase evidence and P1→P2 traceability verification are owned by [P2_QUALITY_GATE](P2_QUALITY_GATE.md).

**Anti-duplication contract:** [V1_SCOPE](../01-product/V1_SCOPE.md) owns the Owner's business meaning (DIR-011 contract, DOC catalog, scope classes). This document organizes concepts derived from it and cites it; it never restates that contract as a second source. On any conflict, V1_SCOPE and the latest Owner decision win and the conflict goes to [change control](../00-governance/CHANGE_CONTROL.md).

**Boundary:** no tables, columns, indexes, FK definitions, migrations or physical ERD (P4); no workflow orchestration or screens (P3); no permission matrix (P5); no locking/idempotency mechanisms (P6). A concept below is a business meaning, not a storage decision.

## Reading conventions

- **Kind** — `F` immutable fact/event; `D` explicit business decision; `M` master data; `Doc` document artifact; `S` snapshot; `derived` computed truth that is never stored independently (P4 may materialize it; events remain the truth).
- **Scope** — `GLOBAL` workspace-wide; `MASTER` shared master data; `COMPANY` one legal company's record; `PROJECT` project-scoped (always company-attributed via the project's primary company); `POOL` shared physical warehouse quantities.
- **Sensitivity** — classes defined in [Scope and sensitivity classes](#scope-and-sensitivity-classes); P5 builds the enforcement matrix from them.
- **Lifecycle** — `SM:<name>` a stored-decision state model in BUSINESS_RULES; `EV` append-only event with reversal linkage; `DS` derived status; `A/I` active/inactive-archive master semantics only.

## A. Glossary

Working terms with their exact source meaning. UI copy is P7's job; these are domain meanings. Terms marked ⚠ are overloaded and must never be conflated.

| Term (ID) | English | Meaning | Distinguish from |
| --- | --- | --- | --- |
| Perusahaan | Legal Company | One legal entity (CV/PT/other) in the single shared workspace | The product name; rebranding never renames legal entities |
| Akses Perusahaan | Company Grant | Explicit per-company access granted to a user | Role/capability (what actions), grant (which company) |
| Klien / Organisasi → Unit → PIC | Client hierarchy | One generic model for every client; UNY/UGM are ordinary rows | Any institution-specific module (excluded) |
| Pemasok | Supplier | External seller master | Supplier *price history*, which is company-scoped derived data |
| Produk / Jasa | Product / Service | Catalog items; services never create stock events | — |
| Satuan Dasar / Satuan Alternatif | Base / alternate unit | Stock truth is always in base units; alternate units convert by master ratio | Conversion snapshot on a transaction (frozen ratio) |
| Barcode pabrikan / internal | Manufacturer / internal barcode | Identifier resolving to at most one active product | SKU (internal key), Serial (per-unit identity) |
| Nomor Seri | Serial number | Optional per-unit identity; serialized ⇒ integer piece quantities | Barcode (per product, not per unit) |
| Harga Beli / Harga Jual / Harga SPJ | Purchase / selling / SPJ-reference price | Master defaults + change history; SPJ price is an administrative/reference value, never revenue by formula | Transaction price snapshots (frozen at commitment) |
| Proyek | Project | The operational hub; one primary company, one client/unit/PIC, one channel | — |
| Item Proyek | Project item | Demand line (product/service, qty, unit + pinned conversion, price snapshot); the reconciliation spine for reservation/purchase/dispatch/delivery | — |
| Kanal REGULAR / SIPLAH | Transaction channel | Project attribute plus manual channel metadata; SIPLAH is never a module | A separate SIPLAH project system (excluded) |
| Nilai SIPLAH, nilai beli riil, fee, biaya VA, cashback/pengembalian | SIPLAH metadata | Manually recorded values; informational unless an explicit fact/settlement record exists; no formula ever derives money from them | Cash-In (actual money), fee-deduction settlement (explicit record) |
| Penawaran (Surat Penawaran) | Quotation | Commercial proposal with revision chain, validity, approval | The rendered DOC-01 layout of it |
| Konfirmasi Komersial | Commercial confirmation | The decision that demand is confirmed — normally quotation approval; explicit project confirmation where no quotation exists (client order evidence attached) | A separate Order entity (none exists) |
| Pembelian | Purchase | Actual acquisition record: supplier, items, actual price/tax/shipping; company mandatory, project optional (replenishment) | PO (optional document), Cash-Out (actual disbursement), receiving (physical arrival) |
| PO / Surat Pesanan ⚠ | Purchase order / order letter | One procurement order record; DOC-02 and DOC-14 are two layouts of it, never two orders | Client-issued Surat Pesanan (external attachment) |
| Lot Penerimaan | Receiving lot | Identified acquisition carrier created by every receiving/opening/drop-ship-return: source company, source purchase/import identity, actual cost, quantity | Movement event (the act), balance (derived) |
| Stok Fisik Bersama | Shared pool | One physical warehouse stock pool; quantities are pool-wide, cost/provenance company-protected | Company stock ownership (does not exist physically) |
| ON HAND / RESERVED / UNUSABLE / AVAILABLE | Quantity categories | AVAILABLE = ON HAND − RESERVED − UNUSABLE, always ≥ 0; categories disjoint in effect | — |
| Reservasi | Reservation | Demand claim on usable existing supply after confirmation; never a physical movement | Dispatch (physical), quotation (never reserves) |
| Alokasi Stok Antar-Perusahaan ⚠ | Inter-company stock allocation | Explicit auditable record when company B's project consumes company A's lot; conserves qty + pinned cost | Payment application (money-side; different concept, different word) |
| Barang Masuk / Barang Keluar | Receiving / dispatch | Physical movement events; dispatch is the single physical subtraction point | Delivery (client-side record; never subtracts stock) |
| Opname | Stock opname | Count event with counted basis; variance applied only via reasoned adjustment | Direct balance edit (forbidden) |
| Retur pembelian / retur penjualan ⚠ | Purchase / sales return | Opposite directions: purchase return = stock out to supplier + cost unwind; sales return = stock in (damaged → UNUSABLE) + receivable/credit correction | REVERSAL (error correction, not a commercial event) |
| Drop-ship | Direct fulfillment | Supplier→client; zero warehouse events; confirmation carries qty/actual cost/serials/evidence | Warehouse fulfillment |
| Pengiriman | Delivery | Client-side record: partial/full, recipient, condition, proof, discrepancies; formal closure is a decision | Dispatch (warehouse-side) |
| Dokumen Terbit / Final | Issued document version | Immutable snapshot + number; revision = new version; void retires version and number forever | Render artifact (retryable PDF of the issued version) |
| Dokumen Eksternal | External document | Authentic third-party original stored as attachment with issuer metadata; never generated, never satisfied by a company draft | Company-prepared variants of SPK/HPS/Surat Pesanan (truthful company issuer) |
| Dokumen Administrasi | Administrative requirement | Per-project checklist item: required/optional, waiver-eligibility, generated/uploaded/either | A universal SPJ package (none exists) |
| TIDAK BERLAKU | N/A waiver | Decision on a waiver-eligible requirement only; reason + individual actor + audit | Force Complete (project-level, Owner-only) |
| Invoice | Invoice | Issue records agreed Sales/Transaction Value; implies no cash | Billing (activation), Nota (transaction slip), Kuitansi (receipt) |
| BELUM DITAGIHKAN / DITAGIHKAN | Unbilled / billed | Issued-unbilled invoices stay separately visible; the billing act (billed_at, due_date) activates the unpaid remainder as active receivable | — |
| Piutang Aktif | Active receivable | Derived: billed − payment applications − fee-deduction settlements − write-off disposals; never negative | Sales value, Cash-In |
| Pembayaran | Payment | Immutable Cash-In fact: date, amount, method, company bank destination, reference, proof, individual actor | Payment application (allocation layer) |
| Aplikasi Pembayaran ⚠ | Payment application | The single mutable audited layer linking a payment to invoice / refund disbursement / correction credit; Σ applications ≤ payment | Inter-company stock allocation (stock-side) |
| Kredit Pelanggan | Customer credit | Derived: unapplied payment remainders + explicit correction credits; spending it is an application | Profit (never), refund (a disbursement consuming credit) |
| Penghapusan Piutang | Receivable write-off / disposition | Owner-only decision removing a residual from active receivable + collectible aging while preserving it permanently as disposed history (DIR-019) | Payment (real money), dispute hold (stays active) |
| Sengketa / Dispute hold | Dispute indicator | Receivable stays active and aging with a disputed reason; not settled; blocks normal completion | Write-off |
| Penyelesaian Potongan Fee | Fee-deduction settlement | One audited non-cash record settling a verified intermediary fee portion of a receivable and attributing that fee as project expense exactly once (DIR-019; generalized to verified bank/VA/payment-intermediary fees by DIR-020) | Payment application (cash), write-off |
| Biaya / HPP | Cost / HPP | Actual/direct economic cost attributed to a project via pinned lot cost or project-attributed purchase/expense; managerial only | Cash-Out (actual disbursement), master purchase price |
| Pengeluaran Kas | Disbursement / Cash-Out | Immutable fact of money actually paid out (purchase/expense/refund reference, evidence) | Purchase creation (never Cash-Out), HPP (economic, not cash) |
| Beban / Expense | Expense record | Company-scoped actual cost, optionally project-attributed; part of project direct cost when attributed | Formal accounting expense classification (excluded) |
| recorded_at / business_date | Dual dates | Immutable system timestamp vs explicit business date (DIR-018); business_date governs periods/aging, recorded_at governs audit order | — |
| Force Complete | Completion override | Owner-only exceptional decision; preserves unmet conditions and residual obligations; fabricates nothing | Normal completion (ten-condition gate) |
| Arsip / Nonaktif | Archive / inactive | Master and company semantics only; a deactivated company blocks new business but keeps collection and history | Deletion (never a correction path) |

Terminology anchors: the P2 brief's colloquial "Harga Real" = the sources' *real purchase value*; bare "pengembalian" = *refund/return amount*; "rak" = *rack/location*; "stok minimum" = *minimum stock/restock*. The sources define no SIPLAH-specific tax field; tax is a general configurable component (CAP-11).

## B. Domain map

Twelve domains plus two cross-cutting conventions. **Non-domains:** dashboards, action queues, alerts, search and reports are *projections* — every displayed number resolves to a BUSINESS_RULES calculation plus a scope filter. Profit/margin are *calculations*, not concepts. SIPLAH is *channel metadata*. There is no UNY/UGM/RT module, no separate SIPLAH system, no general ledger, no enterprise WMS, no supplier-comparison capability (all excluded by V1_SCOPE).

| # | Domain (code) | Purpose | Depends on |
| --- | --- | --- | --- |
| 1 | Access & Identity (ACC) | Who may act, on which company, as which individual | — |
| 2 | Companies (CO) | Legal identity and document-identity assets per company | — |
| 3 | Partners (PT) | Clients (org→unit→PIC) and suppliers as shared masters | — |
| 4 | Catalog & Units (CAT) | Products/services, SKU, units + conversions, barcodes, serial policy, price defaults | — |
| 5 | Projects & Demand (PRJ) | The operational hub: demand lines, channel, confirmation, completion | CO, PT, CAT |
| 6 | Quotations (QUO) | Commercial proposal, revision, approval | PRJ |
| 7 | Procurement (PUR) | Purchases (project-linked or replenishment), optional PO | CO, PT, CAT, PRJ |
| 8 | Inventory (INV) | Shared pool: movements, lots, reservations, allocations, opname, locations | CAT, PUR, PRJ |
| 9 | Fulfillment & Delivery (FUL) | Per-item fulfillment mode; deliveries, drop-ship, service handover | PRJ, INV |
| 10 | Documents & Files (DOC) | One generation capability + external attachments + numbering | CO, PRJ and the records it renders |
| 11 | Administration (ADM) | Per-project requirement checklist and waivers | PRJ, DOC |
| 12 | Finance (FIN) | Invoices, billing, receivables, payments/applications, credit, dispositions, expenses, HPP attribution, disbursements | PRJ, PUR, INV, FUL, DOC |

**Cross-cutting conventions (stated once in BUSINESS_RULES, reused by every domain):** (i) Audit & Correction — one audit-event convention (individual actor, recorded_at, action, entity, before/after, reason, linkage) and the eight-primitive correction taxonomy; (ii) Operational projections — the derived-view rule above.

## C. Concept catalogue

### 1. Access & Identity (ACC)

| Concept | Kind | Scope | Lifecycle | Sensitivity | Notes |
| --- | --- | --- | --- | --- | --- |
| User | M | GLOBAL | A/I | S2 | Individual identity; multiple users may hold the same role (≥2 Admin accounts at launch, DIR-018); never a shared identity |
| Role (Owner, Admin Operasional) | M | GLOBAL | A/I | S2 | Two built-in launch roles; capability-based; custom-role designer deferred (D-01) |
| Capability | M | GLOBAL | A/I | S2 | Named permission; server-side; role labels are never authorization |
| Company Grant | D | GLOBAL | A/I | S2 | Explicit user↔company access; default deny (DIR-011) |

### 2. Companies (CO)

| Concept | Kind | Scope | Lifecycle | Sensitivity | Notes |
| --- | --- | --- | --- | --- | --- |
| Legal Company | M | COMPANY | A/I | S2 | CV/PT/other; deactivation blocks new business, keeps collection + history (carve-out) |
| Company identity assets | M | COMPANY | A/I | S3 | NPWP, address/contact, logo, stamp/signature, director; snapshot-referenced by issued documents; stored signatures never imply external approval (AC-01) |
| Bank Account | M | COMPANY | A/I | S3 | One-to-many per company; payment facts record the actual destination |
| Document identity / numbering rules | M | COMPANY | A/I | S2 | Per-company issuer identity; formats validated under GAP-019 |

### 3. Partners (PT)

| Concept | Kind | Scope | Lifecycle | Sensitivity | Notes |
| --- | --- | --- | --- | --- | --- |
| Client Organization / Unit / PIC | M | MASTER | A/I | S0 name; S2 relations | One generic hierarchy; unit-specific addresses; historical outputs snapshot the address used |
| Supplier | M | MASTER | A/I | S0 name | Master identity only |
| Supplier price history | derived | COMPANY | — | S3 | Previous actual price / last purchase date, derived from that company's purchases; **never a shared master field** (GAP-006) |

### 4. Catalog & Units (CAT)

| Concept | Kind | Scope | Lifecycle | Sensitivity | Notes |
| --- | --- | --- | --- | --- | --- |
| Product / Service | M | MASTER | A/I | S0 | Goods vs service flag governs stock applicability; images; activation |
| SKU / Barcode | M | MASTER | A/I | S0 | Manufacturer barcode preferred, else internal; resolves to at most one active product; unknown/ambiguous scans never auto-select |
| Base unit + alternate units | M | MASTER | A/I | S0 | Fixed master conversion ratios; ratio changes never rewrite history (snapshots) |
| Serial policy / Serial number | M / F | MASTER / POOL | EV | S1 | Optional per product; serialized ⇒ base unit = piece, integer; serial unique while in stock |
| Price defaults (beli/jual/SPJ) + change history | M / F | MASTER | EV | S3 | Defaults and their history; transaction values are snapshots elsewhere; SPJ price is reference only |

### 5. Projects & Demand (PRJ)

| Concept | Kind | Scope | Lifecycle | Sensitivity | Notes |
| --- | --- | --- | --- | --- | --- |
| Project | D | PROJECT | SM:Project | S2 | Hub; number, primary company, client/unit/PIC, deadlines, activity history |
| Project Item | F/S | PROJECT | DS | S2 (S3 prices) | Demand line: product/service, qty, unit + pinned conversion snapshot at confirmation, price/tax snapshot; fulfillment mode set per item (warehouse / drop-ship / service) |
| Channel + SIPLAH metadata | F/S | PROJECT | — | S2 (S3 values) | REGULAR/SIPLAH per project; editable with audit until first channel-dependent commitment; manual metadata fields snapshot-recorded |
| Commercial Confirmation | D | PROJECT | event | S2 | Normally quotation approval; explicit confirmation with client order evidence where no quotation exists; the reservation trigger |
| Remaining-scope cancellation | D | PROJECT | event | S2 | Formal closure of unfillable demand with authority + reason (completion predicate source) |
| Completion Gate evaluation | derived | PROJECT | DS | S2 | Ten predicates over facts/decisions (BUSINESS_RULES); never a manual checkbox |
| N/A waiver record | D | PROJECT | event | S2 | Only waiver-eligible requirements; reason + individual actor + audit |
| Force Complete record | D | PROJECT | event | S2 | Owner-only; confirmation, mandatory reason, actor, timestamp, before/after, audit; preserves residual obligations |

### 6. Quotations (QUO)

| Concept | Kind | Scope | Lifecycle | Sensitivity | Notes |
| --- | --- | --- | --- | --- | --- |
| Quotation + revision chain | D/Doc | PROJECT | SM:Quotation | S2 (S3 values) | Draft/sent/approved/rejected/expired; validity; every revision preserved; approval = confirmation trigger; DOC-01 renders it |

### 7. Procurement (PUR)

| Concept | Kind | Scope | Lifecycle | Sensitivity | Notes |
| --- | --- | --- | --- | --- | --- |
| Purchase + items | F/D | COMPANY | DS (header OPEN/CLOSED/CANCELLED) | S3 | Actual supplier/qty/actual price/tax/shipping; company mandatory, project optional (replenishment); multiple suppliers/POs per project without duplicate demand; receipt progress derived from receiving events |
| Purchase Order | Doc | COMPANY | SM:Document | S3 | Optional; one order record rendered as DOC-02 or DOC-14; absence changes nothing |
| Purchase-remainder closure | D | COMPANY | event | S3 | Formal closure of an unfillable remainder (supplier short-delivered); completion/reporting predicate source |

### 8. Inventory (INV)

| Concept | Kind | Scope | Lifecycle | Sensitivity | Notes |
| --- | --- | --- | --- | --- | --- |
| Stock movement event | F | POOL | EV | S1 qty; S3 cost/refs | Receiving, dispatch, adjustment, opname variance, returns, condition change; base units; company/project/source refs, individual actor, reason, unit-entry snapshot; reversal linkage |
| Receiving Lot | F | POOL | EV | S3 | Created by every acquisition (receiving / opening stock / drop-ship return); source company, source purchase or import identity, actual cost, qty; consumption ≤ quantity |
| Stock balance (ON HAND/UNUSABLE/RESERVED/AVAILABLE) | derived | POOL | DS | S1 | Derived from events + active reservations; AVAILABLE ≥ 0 always |
| Reservation (+ adjustments) | D/F | PROJECT↔POOL | SM:Reservation | S1 qty; S2 project | Confirmed demand only; binds usable existing supply; consumed by dispatch; released on cancellation/no-longer-needed |
| Inter-company Stock Allocation | F | POOL↔COMPANY | EV | S3 | Source company, consuming company/project, product, base qty, pinned lot cost, reason, individual actor, timestamp; no automatic invoices/journals/cash |
| Opname count | F | POOL | EV | S1 | Counted basis recorded; variance only via reasoned adjustment |
| Rack / location | M | POOL | A/I | S1 | Simple lookup; no WMS |
| Minimum stock + restock advisory | M / derived | POOL | — | S1 | Per product; advisory only, never auto-buys |
| Opening stock lot | F | POOL | EV | S3 | Migration acquisition with owning-company provenance, cost, conversion snapshot, import identity (rerun-safe); archived history never replayed |

### 9. Fulfillment & Delivery (FUL)

| Concept | Kind | Scope | Lifecycle | Sensitivity | Notes |
| --- | --- | --- | --- | --- | --- |
| Delivery record | F | PROJECT | DS + closure D | S2; S4 proof | Partial/full; recipient, condition, proof/signature; discrepancy records; completeness derived; formal closure is a decision |
| Drop-ship confirmation | F | PROJECT | EV | S3 cost | Supplier, qty, actual cost (HPP attribution at confirmation), serials where applicable, fulfillment/delivery evidence; zero warehouse events |
| Service fulfillment / handover | F | PROJECT | EV | S2 | Services never create stock events; handover facts feed BAST (DOC-08) |
| Correction-case closure | D | PROJECT | event | S2 | Explicit resolution decision for a return/discrepancy case (completion predicate source) |

### 10. Documents & Files (DOC)

| Concept | Kind | Scope | Lifecycle | Sensitivity | Notes |
| --- | --- | --- | --- | --- | --- |
| Document instance (DOC-01–14) | Doc | COMPANY/PROJECT | SM:Document | S2/S3 by type | One shared capability; type + company identity; renders a business record — layouts never create a second transaction |
| Issued version | S | COMPANY | immutable | as instance | Content + identity assets + number snapshot; revision = new version; void retires version and number forever |
| Render artifact | derived | COMPANY | DS | as instance | Retryable; reproduces the issued snapshot; issue ≠ render (GAP-007) |
| Numbering sequence | F | COMPANY | EV | S2 | Unique per company/type/period; period = business_date; next-available-number, append-only (DIR-018) |
| External authentic attachment | F | COMPANY/PROJECT | EV | S4 | Client SPK/Surat Pesanan, third-party HPS, faktur pajak, NPWP, NIB, rekening koran, evidence files; issuer metadata; never generated |

### 11. Administration (ADM)

| Concept | Kind | Scope | Lifecycle | Sensitivity | Notes |
| --- | --- | --- | --- | --- | --- |
| Administrative requirement | D/M | PROJECT | DS | S2 | Per-project checklist; required/optional; satisfied via generated / uploaded / either; no universal SPJ package |
| Waiver eligibility attribute | D | PROJECT | — | S2 | Explicit per-requirement; default non-waivable; **Owner-only sets eligibility in V1, Admin only exercises N/A** |
| Requirement reopening | D | PROJECT | event | S2 | Material corrections reopen affected requirements (revalidation) |

### 12. Finance (FIN)

| Concept | Kind | Scope | Lifecycle | Sensitivity | Notes |
| --- | --- | --- | --- | --- | --- |
| Invoice | D/Doc/S | COMPANY/PROJECT | SM:Document (specialization) | S3 | Issue snapshots agreed Sales/Transaction Value; BELUM DITAGIHKAN until billed; DOC-06 renders it |
| Billing act | D | COMPANY | event | S3 | billed_at + due_date; activates the unpaid remainder; invoice-level (DIR-011) |
| Receivable | derived | COMPANY | DS | S3 | Active outstanding = billed − applications − fee settlements − disposals ≥ 0; aging from due_date; dispute indicator keeps it active |
| Payment | F | COMPANY | EV (immutable) | S3 | Cash-In fact: business_date, amount, method, bank destination, reference, proof, individual actor; corrections via contra-facts |
| Payment Application | D/F | COMPANY | EV (superseding) | S3 | Targets: issued invoice (incl. unbilled, never drafts), refund disbursement, correction credit; Σ ≤ payment; same client + same company only |
| Customer credit | derived | COMPANY | DS | S3 | Unapplied remainders + correction credits; every spend is an application |
| Refund disposition | D/F | COMPANY | EV | S3 | Disbursement consuming a specific application; never negative Cash-In, never cost |
| Receivable formal disposition | D | COMPANY | event | S3 | Owner-only write-off / settled-out-of-band; dispute hold indicator (DIR-019 semantics) |
| Fee-deduction settlement | D/F | COMPANY | EV | S3 | Verified intermediary fee settles its receivable portion and attributes the fee as project expense exactly once (DIR-019; generalized to verified bank/VA/payment-intermediary deductions by DIR-020) |
| Expense record | F | COMPANY | EV | S3 | Actual cost; optional project attribution (then part of project direct cost) |
| HPP attribution (+ contra) | F | COMPANY/PROJECT | EV | S3 | Pinned actual lot cost at dispatch / inter-company allocation / drop-ship confirmation / project-attributed purchase or expense; corrections are contra-attributions |
| Disbursement | F | COMPANY | EV (immutable) | S3 | Cash-Out fact with purchase/expense/refund reference and evidence; purchase creation alone never creates it |
| Opening receivable | F | COMPANY | EV | S3 | Migration fact; participates in billed sum; valid application target; explicit aging basis or migrasi bucket; import identity |
| Non-project Cash-In category | F | COMPANY | EV | S3 | E.g., actually received cashback/pengembalian; categorized; feeds cashflow and company profit, never a formula |

## D. Relationship register

Cross-domain and integrity-critical relationships only. **live** = operational reference to current master; **snap** = frozen assertion at commitment (see BUSINESS_RULES snapshot rules). Cardinality in words; P4 designs keys.

| Relationship | Card. | live/snap | Integrity note |
| --- | --- | --- | --- |
| Project → primary Company | many→1 | live + snap on documents | Rare cross-company cases use a linked project/controlled mechanism, never silent reassignment |
| Project → Client Org/Unit/PIC | many→1 each | live + snap on outputs | Unit address as used is snapshotted on issued outputs |
| Project Item → Product/Service | many→1 | live + snap (description, unit, ratio, price, tax) | Master changes never alter committed items |
| Quotation → Project | many→1 | live | Approval confirms the project's demand lines |
| Reservation → Project Item; → pool | many→1 | live | Reduces AVAILABLE only; consumed by dispatch; released/adjusted on change |
| Purchase → Supplier; → Project (optional) | many→1; many→0..1 | live + snap (supplier identity, actual prices) | Projectless purchase = replenishment |
| Receiving event → Purchase items; → Lot | many→many; 1→1 | — | Every acquisition creates exactly one lot; partial receiving = multiple events |
| Dispatch event → Reservation/Project Item; → Lots consumed | many→1; many→many | — | Single physical subtraction; lot consumption recorded (suggest-oldest, operator confirms) |
| Inter-company Allocation → source Lot; → consuming Project | many→1; many→1 | — | Conserves qty + pinned cost; provenance immutable |
| Delivery → Project Items (via dispatch or drop-ship confirmation) | many→many | — | Never subtracts stock; delivered ≤ dispatched for warehouse goods |
| Document instance → business record (quotation/order/delivery/payment/billing/handover) | many→1 | snap at issue | One record, many layouts; layout selection has zero business effect |
| Issued version → numbering sequence | 1→1 | — | Number retired with the version on void |
| Invoice → Project | many→1 | snap values | Multiple invoices per project supported (termin) |
| Billing act → Invoice | 1→1 | — | Invoice-level; gate condition aggregates over a project's invoices |
| Payment Application → Payment; → target (invoice/refund/credit) | many→1; many→1 | — | Conservation per payment; same client + company |
| Fee settlement → Receivable; → Expense attribution | 1→1; 1→1 | — | One record, both effects, exactly once |
| HPP attribution → Lot/Allocation/Drop-ship/Expense source; → Project | many→1; many→1 | — | Pinned actual cost; contra-attribution corrects |
| Disbursement → Purchase/Expense/Refund | many→1 | — | Evidence required; Cash-Out only here |
| Admin requirement → generated Doc or external attachment | many→0..1 | — | A company draft never satisfies a required client original |
| Audit event → any committed record | many→1 | — | Individual actor, before/after, reason, linkage |

## Scope and sensitivity classes

Definitions P5 must enforce (DIR-011 boundary; GAP-006). Owner: all classes, all companies, consolidated. Admin: S0 always; S1 only with inventory permission; S2–S4 only within explicit company grants; the pooled S1 view never becomes a route to S3.

| Class | Meaning | Examples |
| --- | --- | --- |
| S0 | Shared master identity | Product names/SKU/units/barcodes, client org names, supplier names |
| S1 | Pooled physical availability (quantity-only) | ON HAND/RESERVED/UNUSABLE/AVAILABLE per product, locations, serial presence |
| S2 | Company business records | Projects, deliveries, documents metadata, checklists, users/grants |
| S3 | Company financial data | Purchase costs, lots' costs, allocations' cost attribution, supplier price history, prices/invoices/billing/payments/credit/dispositions/expenses/HPP/disbursements, bank accounts, profitability |
| S4 | Private files | Uploaded originals, proofs, signatures/stamps, bank statements |

The nine protected categories of DIR-011 (purchase cost, profitability, banks, invoices, billing, payments, financial records, private documents, unrelated transaction detail) all fall in S3/S4.

## Traceability

The verified CAP-01–18 / DOC-01–14 / OWN-01–06 / REF-IMG carry-through map lives in [P2_QUALITY_GATE](P2_QUALITY_GATE.md#p1--p2-traceability). Rules referenced from this catalogue resolve in [BUSINESS_RULES](BUSINESS_RULES.md).

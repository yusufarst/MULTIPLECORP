# V1 scope and product constraints

Status: REVIEW | Updated: 2026-09-27 | Owner: Planning

Owner requirements are binding; prioritization and minimum delivery boundaries below are a P1 synthesis for review. **OB** refers to the [original brief](../00-governance/sources/OWNER_BRIEF_2026-09-27.txt); **P1** to the [P1 directive](../00-governance/sources/P1_OWNER_DIRECTIVE_2026-09-27.txt); **C1–C3** to the [follow-up answers](../00-governance/sources/P1_OWNER_CLARIFICATIONS_2026-09-27.txt). **REF** denotes the PDF/image supporting references reconciled under the latest **DIR-008**; [REFERENCE_COVERAGE](REFERENCE_COVERAGE.md) owns their comparison, not scope policy. The [product overview](PRODUCT_OVERVIEW.md) owns identity, users and goals. This is the sole inclusion/exclusion list.

## Priority contract

**MUST SHIP:** necessary for the requested operational release, including safeguards and required alternate paths. A capability being optional for a transaction (PO, serial tracking, SIPLAH) does not make support for it optional in V1. **SHOULD SHIP:** small refinements of included capabilities, only after MUST acceptance is secure; they cannot delay validation/cutover. **DEFERRED:** explicit future enhancements, no launch dependency. **OUT OF SCOPE:** excluded from this V1 product/architecture; re-entry requires owner decision.

This is a controlled scope envelope, not a claim that all work is feasible by 15 October. Removing an owner-requested capability, meaningful additions, changed workflows/access/financial meaning, recurring cost or a threatened delivery date follow OWNER_DECISION_REQUIRED. The deadline must not be met by weakening core protections. See GAP-014 for feasibility and acceptance inputs.

## MUST SHIP

Acceptance IDs below resolve in [ACCEPTANCE_CRITERIA](ACCEPTANCE_CRITERIA.md). Each capability is bounded by its stated minimum outcome, not an invitation to build an enterprise subsystem.

| ID | Capability and minimum launch boundary | Source | Acceptance |
| --- | --- | --- | --- |
| CAP-01 | One workspace; maintain company legal name, NPWP/tax identity, bank, contact/address, logo, stamp/signature and director/document assets; add companies. Two built-in roles; manage users, capabilities and explicit company grants. Legal identity independent of product brand | OB §§3–4, 17; P1 Product Name, Roles, Multi-Company Access; REF PDF #6 | AC-01, AC-13 |
| CAP-02 | Generic organization/unit/PIC with applicable unit-specific address and supplier masters; select suppliers directly; product-supplier purchase history, previous actual purchase price and last purchase date | OB §§7–8; P1 Clients, Supplier Comparison; REF PDF #10 | AC-02 |
| CAP-03 | Products and services, SKU, units, images, activation; manufacturer/internal barcode; optional serial tracking and quantity items without forced per-unit codes. Purchase/selling/SPJ-reference defaults, price-change history and transaction snapshots remain distinguishable | OB §§6, 13; P1 Cost Constraint; REF PDF #12–15 | AC-03, AC-16 |
| CAP-04 | Project-centered overview, project number/deadlines/items, client/unit/PIC, one primary company, channel; quotation draft/sent/validity/revision/approval and history. Reuse known data throughout work; identify pending actions. Rare company exceptions remain explicit and traceable | OB §§4, 11; P1 Project; REF PDF #17–18 | AC-04, AC-20 |
| CAP-05 | Purchases with actual supplier/quantities/prices, applicable tax/shipping, company/project references; optional PO, including multiple supplier purchases/POs against one project without duplicate demand | OB §§12–13; P1 Purchase Order, Cost vs Cash-Out; REF PDF #19–20 | AC-05 |
| CAP-06 | One physical stock pool; source/cost-owning and consuming company/project attribution; rack/location, receiving including partial/damaged quantities/evidence, dispatch, purchase/sales returns, adjustment/opname/reversal and reconciling movement/balance history. Per-product minimum stock, low-stock/restock guidance; allocation of available supply to project demand, with reservation policy unresolved. Drop-ship records real fulfillment without fake warehouse entry. No enterprise WMS | OB §§5–6, 27–28; P1 Warehouse; REF PDF #21–30 and image stock branch | AC-06, AC-16, AC-20, AC-22 |
| CAP-07 | Partial/full delivery records and documents linked to project items/fulfillment, with remaining quantities, recipient name, condition, proof/signature upload and discrepancy history; usable operational status | OB §§11, 14, 42; REF PDF #31 | AC-07 |
| CAP-08 | Company-correct selectable catalog of 14 generated business document types/variants defined below; draft/final, PDF/print, revision/void/history with stable historical prices/identity and applicable unique numbers. Owner/Admin selects what is needed per project | OB §§10, 13–14; P1 Correction Semantics; C1 | AC-08, AC-12 |
| CAP-09 | Project-specific Dokumen Administrasi checklist and private attachments; track required/prepared/missing material for the client/unit without a universal SPJ package. Support mandatory client documents through agreed generation or attachment treatment | OB §10; P1 Administrative Documents | AC-09 |
| CAP-10 | Invoice versus billing/submission distinguished, billing date/due date and receivable aging including unbilled/billed, unpaid/partial/paid/overdue. Full/partial/DP/termin payment with date, method, company bank destination where applicable, reference/proof and attribution; explicit audited overpayment/reallocation/correction | OB §§15, 41–42; P1 Correction Semantics; REF PDF #39–43 | AC-10, AC-12, AC-16 |
| CAP-11 | Managerial agreed/invoiced/collected amounts, direct/project costs/expenses, receivables, actual cash in/out and project/company/period/group profitability/margin under confirmed definitions. Configurable tax/fee/VA/adjustment components with snapshots distinct from purchase/selling/SPJ-reference prices. Actual disbursements separate from acquisition cost; purchase creation is not payment | OB §§6, 9, 16; P1 Cost vs Cash-Out, Finance; REF PDF #15–16, #44–46 | AC-11 |
| CAP-12 | REGULAR/SIPLAH channel on the same business flow; manual relevant external references/program/account values, fees, cashback/refund and notes; no invented automatic formula or dependency on SIPLAH uptime | OB §9; P1 SIPLAH | AC-15 |
| CAP-13 | Secure login/logout/reset/session behavior, supported password hashing/login throttling; server-side default-deny permissions/company/resource checks, safe validation/private files, auditable critical actions/corrections. Reason/history mandatory after downstream effects; prevent duplicate stock/money effects and silent stale overwrites | OB §§17, 24–28, 32, 36, 41; P1 Multi-Company Access, Correction Semantics; REF PDF #1 | AC-12–13, AC-16 |
| CAP-14 | All end-user UI in natural Bahasa Indonesia; mobile-first operation and desktop-specific productivity; accessible, consistent, restrained operational design with shadcn primitives. Appropriate mobile tasks and keyboard/mouse-intensive desktop work both receive explicit acceptance | OB §§20–23; P1 Language, Responsive, Visual Direction, Anti AI-Slop, P7 Future Requirement | AC-14, AC-20 |
| CAP-15 | Reconciled current masters/prices, opening stock, open projects/receivables and agreed useful documents; safely repeat/reconcile migration preparation; retain messy old history as an identified archive rather than fabricating new transactions | OB §45; P1 target and gap mandate | AC-17 |
| CAP-16 | Operational readiness on the existing VPS with safe production boundaries, backups including files, isolated restore proof, actionable failure detection and a human operator. RPO ≤24 hours and RTO ≤4 hours; near-zero incremental monthly infrastructure cost without mandatory paid SaaS | OB §§37–44; P1 Recovery Target, Cost Constraint | AC-18–19 |
| CAP-17 | Owner consolidated/per-company dashboard; Admin action queue for projects, quotations, purchase/PO, receiving/delivery, documents, billing, overdue and low stock. In-app attention alerts, authorized fast lookup of product/SKU/barcode/project/client/document; date/company-filtered stock, purchase, sales, AR, payment, profit and managerial cashflow reports with operational Excel/PDF/print exports. Views update after committed actions and corrections | OB §§16, 22, 29–30; REF PDF #7–8, #46–50; image final node | AC-23 |
| CAP-18 | Explicit project completion validation across fulfillment, delivery, required documents/admin, billing/receivable, returns/corrections and audit; show remaining blockers and preserve exceptional-closure evidence. Completion must remain truthful when a later correction affects the project | DIR-008 completion gate; REF PDF §§6.12–7; image completion box | AC-21 |

Reporting minimum in CAP-11 is a planner scope proposal consistent with managerial finance; final recognition/valuation/rounding definitions remain GAP-004. It does not authorize a general ledger, full supplier-payables workflow or silently turning DP/SPJ values into revenue. Cross-company consumption must preserve source attribution; the exact cost treatment is a later business-rule decision, not a P1 database design.

REF refinements restore missing detail in the REVIEW synthesis; none is a deadline-driven cut. Rack/location is a simple lookup within the shared warehouse. Low-stock/restock guidance is advisory and never creates a purchase automatically. Damaged, received, available, allocated and delivered quantities must remain distinguishable; their precise eligibility/release policy is future P2/P3 work (GAP-023). Allocation capability is preserved without silently choosing whether it exclusively reserves stock. Configurable financial components do not authorize arbitrary calculation code or a tax-rules engine; GAP-004 owns monetary meaning.

## Selectable document catalog

C1 authorizes a complete recommended set from which Owner/Admin chooses. The bounded V1 recommendation is **14 types/variants**, using reusable project/transaction data and a shared document capability rather than 14 independent workflows:

| ID | Generated type/variant | Product boundary |
| --- | --- | --- |
| DOC-01 | Penawaran | Agreed quotation content and company identity |
| DOC-02 | Purchase Order / PO | Optional supplier-order output; no mandatory PO step |
| DOC-03 | Surat Jalan / Faktur Pengiriman | Delivery output matched to actual fulfillment; layout name must not create duplicate delivery |
| DOC-04 | Nota | Business transaction output distinct from payment proof when unpaid |
| DOC-05 | Kuitansi | Receipt using the applicable recorded payment; no fabricated settlement |
| DOC-06 | Invoice | Financial/business invoice, separate from marking it billed |
| DOC-07 | Lampiran Kuitansi | Supporting detail linked to the relevant receipt, not a second payment |
| DOC-08 | BAST | Handover record; populated facts confirmed by authorized user, not assumed from printing |
| DOC-09 | SPK | Company-issued work/order agreement or clearly marked preparation draft with owner-validated issuer, wording and inputs; never impersonate a client's issued SPK |
| DOC-10 | Berita Acara Pemeriksaan Barang | User-confirmed inspection facts, not automatic certification from a delivery status |
| DOC-11 | HPS | Company-prepared administrative/reference estimate using confirmed inputs and explicit issuer; client/third-party HPS remains an authentic attachment |
| DOC-12 | Nota Terima Barang | Receipt of actual goods with confirmed source/reference; not fictitious warehouse entry |
| DOC-13 | Surat Permintaan Pembayaran | Payment request reusing the relevant billing information; no cash mutation from generation |
| DOC-14 | Surat Pesanan | Company-issued order-document variant or clearly marked preparation draft; reuse order data rather than a second purchase solely for layout; client-issued original is uploaded |

Client-issued SPK/Surat Pesanan, third-party HPS, NPWP, NIB, official faktur pajak, rekening koran/bank statement, and other externally issued evidence are stored as authentic private attachments. Document title alone does not determine issuer. REF PDF #34/image external list and C1's broad generation request are reconciled by retaining both company-prepared output and the external-original path, without generating a replica that claims external approval/signature. A draft cannot satisfy a requirement for an issued client original. Actual issuer/applicability and accepted content are validated under GAP-019; a requested change of issuer or business effect requires owner decision. No tax, banking or business-registration integration is implied.

V1 supplies a maintained baseline layout for each type with company identity applied; arbitrary template editing, unlimited client-specific variants and electronic-signature integrations are not included. Required fields, clauses, numbering applicability, signatures and accepted examples are validated by Owner/Admin in later phases (GAP-019). Selecting a type does not override permissions, missing required information, or actual business facts. The catalog replaces the earlier unanswered question about breadth; the exact content remains subject to UAT, not an excuse to remove any of these 14 outputs silently.

## SHOULD SHIP

These are discretionary presentation refinements inside existing capabilities, not new business modules or release commitments. Omission does not waive the corresponding MUST outcome.

| ID | Refinement | Limit / acceptance if included |
| --- | --- | --- |
| SH-01 | Show previous purchase price/date inline while choosing a supplier/item | Same authorized history already required by CAP-02; no scoring, supplier ranking or automatic supplier selection. Values match AC-02 and honor AC-13 |
| SH-02 | Additional keyboard shortcuts for frequent desktop actions | Beyond the accessible basic keyboard use required by CAP-14; discoverable, no accidental financial/stock submit and same outcome as normal action. Apply AC-14/16 |

No saved-workflow designer, new automation or extra integration is justified merely by remaining capacity.

## DEFERRED

| ID | Deferred enhancement | Source / return condition |
| --- | --- | --- |
| D-01 | Custom-role designer UI and additional built-in role packages | OB §17, 46; permission-capable architecture and launch grant management remain MUST |
| D-02 | Save client/unit administrative checklists as reusable templates; advanced document/template editor | OB §10, 46; project checklists and required document generation remain MUST |
| D-03 | Direct SIPLAH API/synchronization and other nonessential external integrations | OB §§9, 33–34, 46; manual channel metadata remains MUST |
| D-04 | Advanced analytics, forecasting and dashboards beyond operational/managerial needs | OB §46; basic CAP-11 reporting remains MUST |
| D-05 | Native mobile app, offline-write synchronization and AI features | OB §46; responsive browser workflows remain MUST; no camera-scanning promise without device evidence |
| D-06 | Broad historical reconstruction beyond agreed opening/current data and useful recent documents | OB §45; preserve the archive and reconcile opening obligations |

These are not implementation tasks or a promise of later delivery. A mandatory launch use case would require explicit scope review, not quiet reclassification.

## OUT OF SCOPE

- Supplier comparison/scoring/cheapest-supplier workflows or mandatory competing quotes (OB §7; P1 explicit exclusion).
- Separate UNY, UGM or RT UNY modules; a separate SIPLAH project system (OB §§8–9; P1 binding decisions).
- Formal accounting: general ledger, complex chart of accounts, debit/credit journal engine, balance sheet, depreciation or accounting-period close (OB §16; P1 Finance).
- Enterprise WMS, multi-warehouse expansion, complex cross-company settlement architecture or a generalized workflow/rules engine for rare exceptions. None is needed to preserve explicit attribution in the shared warehouse (OB §§4–5, 10, 18; P1 Warehouse).
- Separate company applications, customer/supplier self-service portals, a marketplace, native mobile product, or mandatory paid external services. No such additional user product was requested.
- Speculative infrastructure and distributed architecture excluded by the [engineering baseline](../00-governance/ENGINEERING_PRINCIPLES.md), and production access by planning/coding agents.

Nothing in this section removes simple receiving/delivery/payment corrections, company attribution, required documents, capabilities or recovery guarantees.

## Language and experience contract

The flow image supplies conceptual sequence and branching only. Its English labels, gradients, cards and colors are not the application design specification; the Indonesian/shadcn/anti-slop contract below governs.

Every end-user label, navigation item, form/help text, validation/permission/error message, confirmation, notification, empty/loading/status presentation and product-owned generated-document wording uses natural professional Bahasa Indonesia. Existing client/legal names, official codes, SKU/reference values and user-authored source content retain their legitimate wording; they are data, not UI translations. Internal code/API/documentation may remain English. P7 owns the final glossary and exact copy. Examples from the owner include Piutang, Pembayaran, Barang Masuk, Barang Keluar, Riwayat Stok, Dokumen Administrasi and Pengaturan.

Mobile MUST support dashboard/status monitoring, project/product/document/billing/payment lookup and detail, quick actions/approvals, and reasonable operational forms. Receiving/dispatch and barcode-related actions must work where the supported device/input permits; manual product/SKU lookup remains available when no scanner is attached. This is not permission to omit mobile stock workflows because desktop exists. Compatibility and practical limits must be demonstrated on real target devices (GAP-020).

Desktop MUST optimize intensive administration, large datasets/tables, keyboard/mouse use, efficient repeated/bulk entry, document creation, inventory, finance/reporting and long workflows. Efficient multi-item entry is required; a generalized bulk-import designer or spreadsheet clone is not implied. Reuse known project data rather than asking users to retype it for each output.

Visual priorities are clarity → task speed → error prevention → readability → consistency → responsive usability → aesthetics. The requested professional, modern, elegant, clean, calm, premium feel must preserve operational density. A decorative design that slows work fails acceptance. The full owner anti-slop prohibitions remain binding; they are not replaced by the word premium. This contract does not select tokens, layouts or components beyond the owner-selected shadcn system.

## P7 handoff requirements

P7 must define and review all of: Indonesian terminology/glossary; mobile and desktop navigation; responsive layout strategy; mobile table alternatives and desktop data-table standards; responsive forms; dialog versus sheet behavior; touch targets; keyboard and barcode interaction; typography, spacing, radius, density, icons, status system and design tokens; loading/error/empty states; activity/history; and an anti AI-slop review.

P7 cannot PASS until mobile-first and desktop-operational designs are both explicit. Existing shadcn primitives stay separate from feature/business components. These are future-phase deliverables, not P1 UI design work.

## Dependencies and evidence needed

| ID | Dependency / known state | Required evidence and owner | Matters by |
| --- | --- | --- | --- |
| DEP-01 | Existing VPS/domain; owner reports 4 vCPU, 16 GB RAM, 200 GB NVMe disk and 16 TB bandwidth (C3) | Verify actual free space, current utilization, bandwidth allowance period and measured restore transfer capacity; these nominal figures do not prove workload fit or RTO | P9 sizing/cost and release gate |
| DEP-02 | Off-VPS backup destination, retention, key custody and recovery operator are unconfirmed | Owner/operator identifies an existing usable destination or approves a costed alternative; restore evidence must meet targets | GAP-011/018; before release commitment |
| DEP-03 | USB scanners exist; mobile devices, connection/browser behavior, printers and media are not verified | Operational user tests representative scanner, phone/browser and document/label printing; confirm available label-print method without assuming a new printer purchase | P7 patterns and P8 device UAT |
| DEP-04 | C1 settles breadth via the 14-type recommendation; actual company identity assets/client document examples remain unvalidated | Owner/Admin provide or validate sanitized baseline examples, required fields/clauses and accepted print outputs | GAP-019; before document design/UAT |
| DEP-05 | Legacy products/prices/suppliers/clients/stock/open debt exist in unknown condition | Data steward provides private/sanitized structures, counts and reconciled control totals; archive/opening cutover boundary agreed | P2/P4 compatibility; P10 migration |
| DEP-06 | Owner and Admin Operasional are confirmed UAT/migration validators (C2); scheduled hours and execution/review capacity remain unverified | They confirm availability and acceptance evidence; later task estimates include review, failure testing and recovery rehearsal | GAP-014; feasibility and P10 commitment |
| DEP-07 | Runtime/library/license and development/QA tool availability are not yet selected/verified | Planner/executor check supported versions and commercial-use terms in official docs when selected; account entitlements/cost recorded, no unsuitable free-tier assumption | P4/P8/P9; before dependency adoption |
| DEP-08 | Secure onboarding/reset and operator alert delivery need a viable operational channel | Security/operations planning identifies an approved low-cost route and tests it; do not assume an existing SMTP entitlement or buy a service silently | P5/P9 readiness |
| DEP-09 | Office connectivity and real workload are unknown | Operator supplies likely simultaneous users/record/file volumes and practical network/device observations | P6 targets/P7 flows; P8 evidence |

Dependencies constrain delivery; they do not create new product modules. Core operation must not require SIPLAH availability. Provider-specific planning/testing tools must not become application runtime dependencies.

## Cost boundary

Target: **near-zero incremental monthly infrastructure cost**, as close to **Rp0/month** as reasonably possible, using the existing VPS/domain. Existing asset payments are not newly zero; capacity, storage, bandwidth, retention and maintenance still have operational limits. Current implementation spending: none.

| Cost area | P1 stance | Incremental monthly expectation / unresolved evidence |
| --- | --- | --- |
| Main application/database/web runtime | Prefer mature open-source, self-hosted runtime on existing VPS | Rp0 target if current capacity suffices; unverified, no upgrade chosen |
| PDF/barcode generation and private files | Prefer local/open-source capabilities; no mandatory metered API | Rp0 service-fee target; capacity and dependency terms must be verified later |
| Independent backups and recovery | Mandatory outcome; existing suitable off-VPS resources preferred | Unknown until DEP-02 is answered; Rp0 is not promised and backup cannot be omitted |
| Email/alerts, bandwidth or storage upgrade | Reuse suitable existing resources where possible | No service or recurring cost selected; unmet needs require a costed owner decision |
| Development/QA subscriptions and hardware | Separate from monthly production infrastructure | Entitlements and any one-time hardware costs unconfirmed; no purchase authorized or cost assumed away |

Any later recommendation with recurring cost must state purpose, estimated incremental monthly amount, free/self-hosted alternative, operational benefit and mandatory/optional status before owner decision. P1 recommends no paid vendor/plan, so it invents no price. If targets cannot be achieved with existing resources, GAP-018 requires a concrete resource/cost decision; do not weaken security, integrity, access or recovery.

## Unresolved decisions and scope freeze

The [gap register](../00-governance/GAP_REGISTER.md) owns remaining choices: financial recognition/allocation including the invoice/billing/receivable trigger (GAP-004), shared-stock/master visibility (GAP-006), offsite resource/cost feasibility (GAP-018), exceptional completion/waiver authority (GAP-022), and allocation/reservation/usable-stock policy (GAP-023). Capacity/availability (GAP-014), document issuer/content validation (GAP-019) and future full traceability (GAP-024) retain explicit gates. The scope does not silently choose these business policies.

Do not silently treat these choices as decided, or use a SHOULD/DEFERRED label to drop a mandatory client capability. P1 can document a coherent scope for review with these explicit dependencies; it cannot certify deadline feasibility, approved V1 scope or production readiness. Detailed business rules remain P2 and are not written in this phase.

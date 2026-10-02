# V1 scope and product constraints

Status: APPROVED | Updated: 2026-10-03 | Owner: Planning

Approval: [APPR-002](../00-governance/DECISION_LOG.md#appr-002--p1-product-definition-and-v1-scope-approved), explicit Owner approval on 2026-09-28 of the recovered P1 baseline; reviewed file hashes and scope are recorded there. Approval changes lifecycle only, not business requirements or implementation authorization.

Amendment: the DOC-05 row alone was narrowly amended on 2026-09-29 to represent the Owner's decision [DIR-024](../00-governance/DECISION_LOG.md#dir-023-dir-024-and-tech-015--p3-authorization-owner-decisions-and-documentation-execution) D-4 (pre-payment Kuitansi); the pre-amendment SHA-256 is recorded there and the amended revision is approved under [APPR-004](../00-governance/DECISION_LOG.md#appr-004--p3-critical-business-workflows-approved).

Amendment — REVIEW, pending Owner approval: on 2026-10-03 the sentences of the priority contract and the scope review that rested on the 15 October date, CAP-14 and the language and experience contract were narrowly amended to apply the Owner's decisions of no fixed delivery date ([DIR-035](../00-governance/DECISION_LOG.md#dir-034039-obs-013-and-tech-023--aicwdf-adoption-directives-migration-baseline-and-structural-migration) D1), AICWDF §18.2 over conflicting anti-AI-slop rules (D4) and the Indonesian-default bilingual UI (DIR-037 D7); TECH-023 records each sentence and the SHA-256 of the last approved revision. The amended wording is not approved until the Owner approves it; the decisions it applies are binding within their subjects. DEFERRED and OUT OF SCOPE are not reopened.

Owner requirements are binding; prioritization and minimum delivery boundaries below are the P1 baseline approved under APPR-002. **OB** refers to the [original brief](../00-governance/sources/OWNER_BRIEF_2026-09-27.txt); **P1** to the [P1 directive](../00-governance/sources/P1_OWNER_DIRECTIVE_2026-09-27.txt); **C1–C3** to the [follow-up answers](../00-governance/sources/P1_OWNER_CLARIFICATIONS_2026-09-27.txt). **REF** denotes the PDF/image supporting references reconciled under the latest **DIR-008**; [REFERENCE_COVERAGE](REFERENCE_COVERAGE.md) owns their comparison, not scope policy. The [product overview](PRODUCT_OVERVIEW.md) owns identity, users and goals. This is the sole inclusion/exclusion list. Latest binding business decisions are DIR-011, preserved in the final Owner source indexed by SOURCE_OF_TRUTH; their product contract is below.

## Priority contract

**MUST SHIP:** necessary for the requested operational release, including safeguards and required alternate paths. A capability being optional for a transaction (PO, serial tracking, SIPLAH) does not make support for it optional in V1. **SHOULD SHIP:** small refinements of included capabilities, only after MUST acceptance is secure; they cannot delay validation/cutover. **DEFERRED:** explicit future enhancements, no launch dependency. **OUT OF SCOPE:** excluded from this V1 product/architecture; re-entry requires owner decision.

This is a controlled scope envelope, not a claim that all work is feasible by any date: there is no fixed delivery date, and any future target is subject to the quality gates and completion criteria (DIR-035 D1). Removing an owner-requested capability, meaningful additions, changed workflows/access/financial meaning, recurring cost or a threatened delivery date follow OWNER_DECISION_REQUIRED. No target may be met by weakening core protections. See GAP-014 for capacity and acceptance inputs.

## MUST SHIP

Acceptance IDs below resolve in [ACCEPTANCE_CRITERIA](ACCEPTANCE_CRITERIA.md). Each capability is bounded by its stated minimum outcome, not an invitation to build an enterprise subsystem.

| ID | Capability and minimum launch boundary | Source | Acceptance |
| --- | --- | --- | --- |
| CAP-01 | One workspace; maintain company legal name, NPWP/tax identity, bank, contact/address, logo, stamp/signature and director/document assets; add companies. Two built-in roles; manage users, capabilities and explicit company grants. Legal identity independent of product brand | OB §§3–4, 17; P1 Product Name, Roles, Multi-Company Access; REF PDF #6 | AC-01, AC-13 |
| CAP-02 | Generic organization/unit/PIC with applicable unit-specific address and supplier masters; select suppliers directly; product-supplier purchase history, previous actual purchase price and last purchase date | OB §§7–8; P1 Clients, Supplier Comparison; REF PDF #10 | AC-02 |
| CAP-03 | Products and services, SKU, units, images, activation; manufacturer/internal barcode; optional serial tracking and quantity items without forced per-unit codes. Purchase/selling/SPJ-reference defaults, price-change history and transaction snapshots remain distinguishable | OB §§6, 13; P1 Cost Constraint; REF PDF #12–15 | AC-03, AC-16 |
| CAP-04 | Project-centered overview, project number/deadlines/items, client/unit/PIC, one primary company, channel; quotation draft/sent/validity/revision/approval and history. Reuse known data throughout work; identify pending actions. Rare company exceptions remain explicit and traceable | OB §§4, 11; P1 Project; REF PDF #17–18 | AC-04, AC-20 |
| CAP-05 | Purchases with actual supplier/quantities/prices, applicable tax/shipping, company/project references; optional PO, including multiple supplier purchases/POs against one project without duplicate demand | OB §§12–13; P1 Purchase Order, Cost vs Cash-Out; REF PDF #19–20 | AC-05 |
| CAP-06 | One physical stock pool; explicit auditable inter-company allocation retaining source/cost-owning and consuming company/project attribution; rack/location, receiving including partial/damaged quantities/evidence, dispatch, purchase/sales returns, adjustment/opname/reversal and reconciling movement/balance history. Per-product minimum stock and advisory restock guidance. Simple confirmed-project reservation, release/adjustment and usable availability under the Owner contract below. Drop-ship records real fulfillment without fake warehouse entry. No enterprise WMS | OB §§5–6, 27–28; DIR-011; REF PDF #21–30 and image stock branch | AC-06, AC-16, AC-20, AC-22 |
| CAP-07 | Partial/full delivery records and documents linked to project items/fulfillment, with remaining quantities, recipient name, condition, proof/signature upload and discrepancy history; usable operational status | OB §§11, 14, 42; REF PDF #31 | AC-07 |
| CAP-08 | Company-correct selectable catalog of 14 generated business document types/variants defined below; draft/final, PDF/print, revision/void/history with stable historical prices/identity and applicable unique numbers. Owner/Admin selects what is needed per project | OB §§10, 13–14; P1 Correction Semantics; C1 | AC-08, AC-12 |
| CAP-09 | Project-specific Dokumen Administrasi checklist and private attachments; track required/prepared/missing material for the client/unit without a universal SPJ package. Support mandatory client documents through agreed generation or attachment treatment. Permissioned Admin may mark only waiver-eligible requirements TIDAK BERLAKU with reason and audit; mandatory completion invariants cannot be silently bypassed | OB §10; DIR-011 completion decision | AC-09, AC-21 |
| CAP-10 | Issued/final invoice records agreed sales/transaction value; unbilled invoice remains BELUM DITAGIHKAN. Explicit billing creates active receivable for the unpaid amount, with billed_at/due_date and aging. Actual payments create Cash-In and reduce the applicable receivable; full/partial/DP/termin payments retain date, method, company bank destination where applicable, reference/proof and attribution; explicit overpayment/credit/refund and audited reallocation/correction | OB §§15, 41–42; DIR-011 financial semantics; REF PDF #39–43 | AC-10, AC-12, AC-16 |
| CAP-11 | Distinct sales/transaction value, active receivable, Cash-In, actual/direct Cost/HPP and Cash-Out; managerial agreed/invoiced/collected amounts, costs/expenses and project/company/period/group profitability/margin. Configurable tax/fee/VA/adjustment components with snapshots distinct from purchase/selling/SPJ-reference prices. Cost/HPP supports managerial profitability; actual disbursement alone creates Cash-Out, never purchase creation alone | OB §§6, 9, 16; DIR-011 financial/attribution decisions; REF PDF #15–16, #44–46 | AC-11 |
| CAP-12 | REGULAR/SIPLAH channel on the same business flow; manual relevant external references/program/account values, fees, cashback/refund and notes; no invented automatic formula or dependency on SIPLAH uptime | OB §9; P1 SIPLAH | AC-15 |
| CAP-13 | Secure login/logout/reset/session behavior, supported password hashing/login throttling; server-side default-deny permissions/company/resource checks, safe validation/private files, auditable critical actions/corrections. Necessary pooled physical availability is visible with inventory permission under the Owner contract; other-company business/financial records remain denied outside explicit grants. Reason/history mandatory after downstream effects; prevent duplicate stock/money effects and silent stale overwrites | OB §§17, 24–28, 32, 36, 41; DIR-011 visibility decision; REF PDF #1 | AC-12–13, AC-16 |
| CAP-14 | End-user UI in natural Bahasa Indonesia by default, with English as the secondary language through a language switch and no uncontrolled hardcoded user-facing copy; generated or issued business documents and business data stay in Bahasa Indonesia for V1; mobile-first operation and desktop-specific productivity; accessible, consistent, restrained operational design with shadcn primitives. Appropriate mobile tasks and keyboard/mouse-intensive desktop work both receive explicit acceptance | OB §§20–23; P1 Language, Responsive, Visual Direction, Anti AI-Slop, P7 Future Requirement; DIR-037 D7 | AC-14, AC-20 |
| CAP-15 | Reconciled current masters/prices, opening stock, open projects/receivables and agreed useful documents; safely repeat/reconcile migration preparation; retain messy old history as an identified archive rather than fabricating new transactions | OB §45; P1 target and gap mandate | AC-17 |
| CAP-16 | Operational readiness on the existing VPS: PostgreSQL and business-file backups stored only on that production VPS, multiple recovery points, scheduled/pre-deployment backups, verified retention/rotation/cleanup, restore proof, disk/failure monitoring and a human operator. RPO ≤24h/RTO ≤4h are targets only when VPS and local backup data remain recoverable; total-host/storage loss is accepted exposure. Near-zero incremental recurring cost | DIR-009 supersedes earlier offsite requirement; local backup contract below | AC-18–19 |
| CAP-17 | Owner consolidated/per-company dashboard; Admin action queue for projects, quotations, purchase/PO, receiving/delivery, documents, billing, overdue and low stock. In-app attention alerts, authorized fast lookup of product/SKU/barcode/project/client/document; date/company-filtered stock, purchase, sales, AR, payment, profit and managerial cashflow reports with operational Excel/PDF/print exports. Views update after committed actions and corrections | OB §§16, 22, 29–30; REF PDF #7–8, #46–50; image final node | AC-23 |
| CAP-18 | System evaluates the normal Project Completion Gate across fulfillment, delivery, documents/admin, billing/receivable, returns/corrections and audit. Show blockers and eligible administrative N/A evidence. Owner-only exceptional Force Complete requires explicit confirmation, reason, actor, timestamp, before/after state and audit log; it must not pretend normal conditions passed or erase obligations. Later corrections require truthful completion revalidation | DIR-008 completion gate; DIR-011 exception authority; REF PDF §§6.12–7; image completion box | AC-21 |

## Final Owner business decisions

DIR-011 settles the remaining P1 business choices. The following clauses own their product meaning; P2/P4/P5/P6 will formalize rules, data, field-level access and concurrency without changing that meaning. No schema, financial algorithm or state machine is selected here.

### Financial concepts and billing

Keep **Sales / Transaction Value**, **Active Receivable**, **Cash-In**, **Cost / HPP** and **Cash-Out** distinct. An ISSUED / FINAL invoice records agreed sales/transaction value; it implies neither payment nor Cash-In. Until explicitly billed it is separately visible as **BELUM DITAGIHKAN**, not included as an active billed receivable.

When an issued invoice is explicitly marked **BILLED / DITAGIHKAN**, its unpaid amount becomes an active receivable. Record **billed_at** and **due_date**; aging uses the finalized billing/due-date conventions. Actual recorded money received creates Cash-In and reduces the applicable receivable, with partial/full payment, DP/termin, overpayment handling and audited corrections/reallocation preserved. Pre-billing/advance payments remain real cash receipts; billing must use the remaining unpaid amount without recording the same Cash-In again or inventing a negative receivable.

Cost/HPP is actual/direct economic cost attributed to the project and used for managerial profitability. Cash-Out records actual money disbursed; creating a purchase is not proof that cash left the business. SPJ/reference values and configurable tax/fee/VA adjustments remain distinct inputs, never invented revenue/profit formulas. P2 must formalize and validate numeric allocation, currency/rounding, credit/refund and date examples against these five meanings (GAP-004/016); do not infer a valuation/tax method or a formal general ledger from this approval.

### Physical visibility and company isolation

Default deny remains mandatory. Owner may access all companies, consolidated and company-specific information, and all financial/inventory attribution. Admin operates only within explicit company grants and capabilities, enforced server-side.

An Admin with the required inventory permission may see **physical stock availability necessary for warehouse operations** across the shared pool. This does not grant another company's purchase cost, profitability, bank accounts, invoices, billing, payments, financial records, private documents or unrelated transaction details. Necessary physical inventory visibility and company business/financial visibility are separate. A pooled availability view does not authorize arbitrary actions on another company's projects or source records.

P5 must formalize the minimum resource/field projection and enforce it consistently on URLs, IDs, query parameters, payloads, API calls, file-download paths, search, exports, caches and jobs. Shared master lookup cannot become a route to sensitive related history. Owner's visibility choice is resolved (GAP-006); field mapping and negative-test proof remain technical work.

### Normal completion and exceptional override

The system determines whether all normal Project Completion Gate conditions are satisfied. Admin may complete normal requirements within granted permissions and may mark an administrative requirement **TIDAK BERLAKU / NOT APPLICABLE** only when that requirement is eligible for waiver; reason and audit history are mandatory. This is not blanket authority to waive mandatory invariants or invoke Force Complete.

Owner alone may use exceptional **FORCE COMPLETE / COMPLETION OVERRIDE** for legitimate business circumstances. Require explicit confirmation, mandatory reason, actor identity, timestamp, before/after state and audit log. Preserve a clear distinction from normal completion, including the unmet conditions and residual obligations. Override changes the completion outcome; it does not manufacture stock movement, Cash-In, debt settlement, a client-issued document or missing historical facts. The override itself cannot omit its required audit evidence. It must remain exceptional, not the default workflow.

Later material returns/corrections must invalidate stale completion evidence and cause revalidation; P2/P3/P5 formalize applicability, waiver eligibility and authorized transition checks within these settled roles. No generalized workflow engine or silent write-off is introduced (GAP-022).

### Reservation and usable stock

A quotation alone does not reserve stock. Reserve only after the commercial requirement is confirmed, normally quotation/order approval. A simple reservation associates quantity with a confirmed project/order and reduces available supply without making a physical stock-out movement.

Conceptually distinguish **ON HAND**, **RESERVED**, **UNUSABLE / QUARANTINED** and **AVAILABLE**, with the Owner invariant **AVAILABLE = ON HAND - RESERVED - UNUSABLE**. Damaged, rejected or quarantined goods are not normal usable available stock. The quantity categories must avoid double subtraction; changes in condition and reservations must reconcile coherently.

Release or adjust reservations on cancellation, quantity change or when no longer required. Physical stock decreases only on actual dispatch or another legitimate physical movement. Two simultaneous projects cannot both successfully reserve or dispatch the final available stock; reservation/dispatch races must protect confirmed allocations. P2/P4/P6 refine representation and atomic transitions while preserving this meaning, without timed-hold assumptions or an enterprise allocation engine (GAP-023). Rack/location, partial/damaged receiving and advisory low-stock/restock guidance remain included.

### Shared warehouse and cost attribution

Physical stock remains one shared pool. If Company B's project uses stock acquired by Company A, retain A's source/acquisition provenance through an **explicit auditable inter-company stock allocation** linked to the consuming project/company and its managerial cost attribution. No silent reassignment or double-counted physical/cost effect is allowed. Permissioned operations must preserve attribution without exposing protected source-company financial details to an unauthorized Admin.

V1 does not require automatic inter-company invoices, formal accounting journals or automatic cash transfers. The purpose is inventory traceability, managerial cost attribution and auditability; detailed source/cost links belong to P2/P4 (GAP-003/004), not a settlement subsystem.

## Selectable document catalog

C1 authorizes a complete recommended set from which Owner/Admin chooses. The bounded V1 recommendation is **14 types/variants**, using reusable project/transaction data and a shared document capability rather than 14 independent workflows:

| ID | Generated type/variant | Product boundary |
| --- | --- | --- |
| DOC-01 | Penawaran | Agreed quotation content and company identity |
| DOC-02 | Purchase Order / PO | Optional supplier-order output; no mandatory PO step |
| DOC-03 | Surat Jalan / Faktur Pengiriman | Delivery output matched to actual fulfillment; layout name must not create duplicate delivery |
| DOC-04 | Nota | Business transaction output distinct from payment proof when unpaid |
| DOC-05 | Kuitansi | Receipt using the applicable recorded payment; no fabricated settlement. DIR-024: a clearly distinguished *Kuitansi untuk Proses Pembayaran* may be issued before payment when legitimately required for payment processing; it creates no Cash-In, payment or settlement and is linked to the actual payment when it occurs |
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

Nothing in this section removes simple receiving/delivery/payment corrections, company attribution or required documents. Recovery obligations follow the Owner-approved local-only boundary and conditional targets below; there is no total-VPS-loss recovery guarantee.

## Language and experience contract

The flow image supplies conceptual sequence and branching only. Its English labels, gradients, cards and colors are not the application design specification; the Indonesian/shadcn/anti-slop contract below governs.

Every end-user label, navigation item, form/help text, validation/permission/error message, confirmation, notification and empty/loading/status presentation uses natural professional Bahasa Indonesia by default; English is the secondary language, selected through a language switch, and every localizable user-facing string is managed copy, never uncontrolled hardcoded text (DIR-037 D7). Product-owned generated and issued business-document wording and business data stay in Bahasa Indonesia for V1. Existing client/legal names, official codes, SKU/reference values and user-authored source content retain their legitimate wording; they are data, not UI translations. Internal code/API/documentation may remain English. P7 owns the final glossary and exact copy. Examples from the owner include Piutang, Pembayaran, Barang Masuk, Barang Keluar, Riwayat Stok, Dokumen Administrasi and Pengaturan.

Mobile MUST support dashboard/status monitoring, project/product/document/billing/payment lookup and detail, quick actions/approvals, and reasonable operational forms. Receiving/dispatch and barcode-related actions must work where the supported device/input permits; manual product/SKU lookup remains available when no scanner is attached. This is not permission to omit mobile stock workflows because desktop exists. Compatibility and practical limits must be demonstrated on real target devices (GAP-020).

Desktop MUST optimize intensive administration, large datasets/tables, keyboard/mouse use, efficient repeated/bulk entry, document creation, inventory, finance/reporting and long workflows. Efficient multi-item entry is required; a generalized bulk-import designer or spreadsheet clone is not implied. Reuse known project data rather than asking users to retype it for each output.

Visual priorities are clarity → task speed → error prevention → readability → consistency → responsive usability → aesthetics. The requested professional, modern, elegant, clean, calm, premium feel must keep comfortable density and visual breathing room (AICWDF §18.2); a data-heavy screen may be denser only where its work genuinely needs it. A decorative design that slows work fails acceptance. The anti-slop discipline that still helps remains binding — clarity, consistency, accessibility, professional appearance, no decorative clutter, no fake metrics, no meaningless cards, no duplicate actions and no UI gimmicks — and it is not replaced by the word premium; where an older anti-slop rule conflicts with AICWDF §18.2 or the Owner's latest UX direction, those prevail (DIR-035 D4). This contract does not select tokens, layouts or components beyond the owner-selected shadcn system.

## P7 handoff requirements

P7 must define and review all of: Indonesian terminology/glossary; mobile and desktop navigation; responsive layout strategy; mobile table alternatives and desktop data-table standards; responsive forms; dialog versus sheet behavior; touch targets; keyboard and barcode interaction; typography, spacing, radius, density, icons, status system and design tokens; loading/error/empty states; activity/history; and an anti AI-slop review.

P7 cannot PASS until mobile-first and desktop-operational designs are both explicit. Existing shadcn primitives stay separate from feature/business components. These are future-phase deliverables, not P1 UI design work.

## Dependencies and evidence needed

| ID | Dependency / known state | Required evidence and owner | Matters by |
| --- | --- | --- | --- |
| DEP-01 | Existing VPS/domain: 4 vCPU, 16 GB RAM, 200 GB NVMe and 16 TB bandwidth; production and backups share the disk | Verify actual free space/utilization, bandwidth allowance period, workload growth, peak backup/restore space and local restore duration; nominal capacity is not free space or recovery proof | P9 sizing/disk safety; release gate |
| DEP-02 | Backup destination settled: existing production VPS only (DIR-009). Local path, retention schedule, secure access/key custody and human operator still need P9 design/evidence | Operator supplies local storage inventory without credentials; planner defines multiple recovery points, retention/cleanup, scoped recovery drills and disk/failure response. No offsite destination is a dependency | GAP-011/013 technical work; GAP-018 accepted exposure |
| DEP-03 | USB scanners exist; mobile devices, connection/browser behavior, printers and media are not verified | Operational user tests representative scanner, phone/browser and document/label printing; confirm available label-print method without assuming a new printer purchase | P7 patterns and P8 device UAT |
| DEP-04 | C1 settles breadth via the 14-type recommendation; actual company identity assets/client document examples remain unvalidated | Owner/Admin provide or validate sanitized baseline examples, required fields/clauses and accepted print outputs | GAP-019; before document design/UAT |
| DEP-05 | Legacy products/prices/suppliers/clients/stock/open debt exist in unknown condition | Data steward provides private/sanitized structures, counts and reconciled control totals; archive/opening cutover boundary agreed | P2/P4 compatibility; P10 migration |
| DEP-06 | Owner and Admin Operasional are confirmed UAT/migration validators (C2); scheduled hours and execution/review capacity remain unverified | They confirm availability and acceptance evidence; later task estimates include review, failure testing and recovery rehearsal | GAP-014; feasibility and P10 commitment |
| DEP-07 | Runtime/library/license and development/QA tool availability are not yet selected/verified | Planner/executor check supported versions and commercial-use terms in official docs when selected; account entitlements/cost recorded, no unsuitable free-tier assumption | P4/P8/P9; before dependency adoption |
| DEP-08 | Secure onboarding/reset and operator alert delivery need a viable operational channel | Security/operations planning identifies an approved low-cost route and tests it; do not assume an existing SMTP entitlement or buy a service silently | P5/P9 readiness |
| DEP-09 | Office connectivity and real workload are unknown | Operator supplies likely simultaneous users/record/file volumes and practical network/device observations | P6 targets/P7 flows; P8 evidence |

Dependencies constrain delivery; they do not create new product modules. Core operation must not require SIPLAH availability. Provider-specific planning/testing tools must not become application runtime dependencies.

## V1 local backup and P9 handoff contract

**DIR-009 is an explicit Owner/client decision:** all V1 backups are stored only on the existing production VPS. Same-VPS backup is local backup, never offsite. No external object storage, second server, NAS, external cloud or paid offsite service is required or selected. An external destination requires a later explicit Owner change. Near-zero incremental recurring infrastructure cost remains the priority.

Local backups can support recovery from accidental data changes, application failure, failed deployment, selected database-corruption cases and accidental deletion **when an intact suitable backup and recoverable VPS remain**. They may not protect against total VPS loss, total disk failure, VPS deletion, provider-level loss, account compromise destroying production and backups, or catastrophic filesystem/storage failure affecting the whole VPS. This exposure is explicitly accepted in RISK-001 / GAP-018, not claimed mitigated.

RPO ≤24 hours and RTO ≤4 hours remain operational targets for that recoverable-VPS/local-data scenario. Measure the recovery point and return to usable business operation during scoped drills. Neither recovery nor a four-hour RTO is guaranteed after total VPS loss. A successful local drill does not demonstrate disaster recovery after host/storage loss.

P9 must define the following twelve obligations and map them to AC-18/19 evidence; this is a P1 handoff, not an implemented backup system:

| ID | Required local-backup outcome |
| --- | --- |
| BK-01 | PostgreSQL database backup with a recoverable, identifiable recovery point |
| BK-02 | Uploaded/business documents and required file/assets covered coherently with the database |
| BK-03 | Pre-deployment backup and verified completion before the deployment proceeds |
| BK-04 | Automated scheduled backups supporting the scoped RPO target; missed/stale runs visible |
| BK-05 | Rotation across separately identifiable generations; no continuously overwritten single backup file |
| BK-06 | Explicit retention policy preserving multiple recovery points and stated capacity assumptions |
| BK-07 | Integrity/checksum verification and truthful successful/failed status; checksum success alone is not restore proof |
| BK-08 | Human-followable restore procedure, isolated from live writes, including critical-record/file reconciliation and safe job resumption |
| BK-09 | Periodic restore tests with recorded cadence, recovery point, elapsed time and usable-operation result |
| BK-10 | Backup failure detection/logging and actionable notification to the named human operator |
| BK-11 | Disk-space monitoring, warning/critical thresholds and capacity response before a disk-full outage |
| BK-12 | Automatic cleanup strictly according to retention; no deletion of live business data or loss of required recovery generations due to a failed backup |

P9 must explicitly budget the shared **200 GB nominal disk** for PostgreSQL growth, uploaded documents, product images, generated PDFs, logs, exports/temp files, Docker data if applicable, backup growth and retained generations. Include operating-system/other existing usage, temporary backup staging and restore-drill peak space so apparently sufficient steady-state storage does not cause an outage during a backup or test.

P9 sets numerical warning/critical thresholds, reserved free headroom, retention duration/count and cleanup rules from measured free capacity and realistic growth/peak estimates; none is guessed from the nominal 200 GB. Define disk-pressure behavior that avoids exhausting production space, preserves required verified recovery points, logs/alerts failed or skipped work and never reports a missing backup as successful. Verify scheduled/pre-deploy backups, cleanup and restore can coexist with production within this budget.

GAP-011/013 retain the local implementation and evidence obligations. Under the Owner's recovery instruction DIR-010, do not reopen the offsite-backup requirement unless the Owner explicitly changes the decision. New evidence that approved local requirements cannot be met must be reported under GAP-011 and resolved within the authorized local policy; it does not itself authorize an offsite requirement, extra infrastructure or weaker retention/recovery controls.

## Cost boundary

Target: **near-zero incremental monthly infrastructure cost**, as close to **Rp0/month** as reasonably possible, using the existing VPS/domain. Existing asset payments are not newly zero; capacity, storage, bandwidth, retention and maintenance still have operational limits. Current implementation spending: none.

| Cost area | P1 stance | Incremental monthly expectation / unresolved evidence |
| --- | --- | --- |
| Main application/database/web runtime | Prefer mature open-source, self-hosted runtime on existing VPS | Rp0 target if current capacity suffices; unverified, no upgrade chosen |
| PDF/barcode generation and private files | Prefer local/open-source capabilities; no mandatory metered API | Rp0 service-fee target; capacity and dependency terms must be verified later |
| Local backups and scoped recovery | Existing production VPS only; shared disk budget, multiple recovery points, verified restore/cleanup | No external backup-service cost introduced; actual local capacity and operating effort remain to be verified under GAP-011 |
| Email/alerts, bandwidth or storage upgrade | Reuse suitable existing resources where possible | No service or recurring cost selected; unmet needs require a costed owner decision |
| Development/QA subscriptions and hardware | Separate from monthly production infrastructure | Entitlements and any one-time hardware costs unconfirmed; no purchase authorized or cost assumed away |

Any later recommendation with recurring cost must state purpose, estimated incremental monthly amount, free/self-hosted alternative, operational benefit and mandatory/optional status before owner decision. P1 selects no paid vendor/plan and invents no price. Backup location is already settled by DIR-009. If evidence shows approved local retention/recovery cannot fit existing resources, report that specific implementation constraint under GAP-011; do not automatically revive an offsite requirement or silently weaken the approved local contract.

## Resolved business decisions and scope review

DIR-011 resolves the P1 financial, visibility, completion-exception and reservation choices formerly marked OWNER_DECISION_REQUIRED. GAP-004/006/022/023 now track technical formalization/evidence as OPEN, explicitly **BUSINESS DECISION RESOLVED**; they are not pending Owner decisions. GAP-018 remains ACCEPTED_RISK under DIR-009/RISK-001, reaffirmed by DIR-011. The [gap register](../00-governance/GAP_REGISTER.md) owns all remaining technical, operational, content and traceability gates.

No current P1 OWNER_DECISION_REQUIRED item remains. Future concrete proposals that change the approved meaning still follow change control; routine domain/data/security/concurrency design does not reopen settled choices. Full scope, all document types and existing safeguards remain required. P1 is Owner-approved under APPR-002; delivery capacity and production readiness remain unproven. Stop at P1.

# Product definition

Status: APPROVED | Updated: 2026-09-29 | Owner: Planning

Approval: [APPR-002](../00-governance/DECISION_LOG.md#appr-002--p1-product-definition-and-v1-scope-approved), explicit Owner approval on 2026-09-28 of the recovered P1 baseline; reviewed file hashes and scope are recorded there. Approval changes lifecycle only, not business requirements or implementation authorization.

Amendment: the Kuitansi sentence in the partial-payment branch was narrowly amended on 2026-09-29 to represent the Owner's decision [DIR-024](../00-governance/DECISION_LOG.md#dir-023-dir-024-and-tech-015--p3-authorization-owner-decisions-and-documentation-execution) D-4; the pre-amendment SHA-256 is recorded there and the amended revision is approved under [APPR-004](../00-governance/DECISION_LOG.md#appr-004--p3-critical-business-workflows-approved).

Authority: Owner inputs are binding; this P1 product baseline is Owner-approved under APPR-002; application implementation requires separate authorization. Source shorthand: **OB** = [original brief](../00-governance/sources/OWNER_BRIEF_2026-09-27.txt), **P1** = [latest owner directive](../00-governance/sources/P1_OWNER_DIRECTIVE_2026-09-27.txt), **C1–C3** = [owner's follow-up answers](../00-governance/sources/P1_OWNER_CLARIFICATIONS_2026-09-27.txt). Later explicit input takes precedence. Acceptance of P0 is recorded separately as APPR-001. DIR-011 settles the four remaining P1 business decisions; its canonical product contract is in [V1_SCOPE](V1_SCOPE.md#final-owner-business-decisions). It is not approval of the whole P1 package.

## Definition and identity

**MultipleCorp — Company Management System** is one internal, project-centered workspace for a group of legal companies. It connects the operational record from client request and quotation through purchasing, fulfillment, documents, billing, payments and managerial visibility, so staff reuse business information instead of maintaining disconnected copies.

The former product label **Latansa Group Multi-Company Management System** denotes this same project; it is not another application or tenant. Legal entities such as **CV Latansa Jogjakarta** retain their actual names and identities. Product rebranding must not rename legal companies, bank destinations or historical documents. (P1: Product Name; OB §§2–4.)

The target remains an **Operational Production V1 on 15 October 2026**: safe daily use, recoverable data and usable business outputs. It is not a promise that the future ERP vision fits the remaining window. Priority order and role boundaries remain in the [charter](../00-governance/PROJECT_CHARTER.md) and [engineering principles](../00-governance/ENGINEERING_PRINCIPLES.md).

## Primary users and goals

| User | Goal | Product responsibility |
| --- | --- | --- |
| Super Admin / Owner | See operational and financial position across the group; control access and company identity | Manage users, granted capabilities and companies; examine consolidated and company/project information; resolve business exceptions |
| Admin Operasional | Complete daily work accurately with less repeated entry | Work within explicitly granted companies/capabilities; prepare projects, purchases, fulfillment, documents, billing and payments; identify pending work and correct mistakes through authorized paths |

These are the two launch roles. Permission-based behavior and company restrictions are required; future custom roles do not require a launch role-designer product. Clients, suppliers and client PICs are business records, not assumed application users. The owner names Owner and Admin Operasional jointly as UAT and migration validators (C2); their review hours are not yet confirmed. An inventory-permitted Admin can see necessary pooled physical availability without gaining other-company business/financial access. Eligible administrative N/A requires reason/audit; exceptional Force Complete belongs to Owner alone. Production operation remains a separate human responsibility, not a new built-in role. (OB §§8, 17, 37; P1: Roles, Multi-Company Access.)

## Problems and operational outcomes

| Outcome | Problem solved | Observable outcome | Related scope |
| --- | --- | --- | --- |
| O-01 | Disconnected client/company/item information | Staff locate one project and reuse its verified data across related operations and documents | CAP-01–05, CAP-08 |
| O-02 | Unexplained stock and fulfillment status | Staff know quantities fulfilled/remaining and can trace physical movements and source attribution, including direct delivery | CAP-06–07 |
| O-03 | Repeated document entry and uncertain versions | Staff produce the required company-correct documents and distinguish final history from corrections | CAP-08–09 |
| O-04 | Invoice, submission and payment confusion | Staff distinguish issued sales value and BELUM DITAGIHKAN from billed active receivable and actual Cash-In; billing/due dates support aging and partial/full payment tracking | CAP-10 |
| O-05 | Cost confused with actual cash movement | Owner sees actual/direct Cost/HPP for managerial profitability separately from actual Cash-Out, with auditable source-company and consuming-project attribution | CAP-11 |
| O-06 | Unsafe access or invisible mistakes | Unauthorized users cannot obtain sensitive company data; critical corrections retain reasons and history | CAP-13 |
| O-07 | Desk-only work and fragile go-live | Indonesian tasks work on appropriate mobile/desktop devices, with reconciled starting data and tested local recovery where VPS/backup data remain recoverable, under DIR-009 | CAP-14–16 |

Scope IDs and commitments are owned by [V1_SCOPE](V1_SCOPE.md); evidence for these outcomes is owned by [ACCEPTANCE_CRITERIA](ACCEPTANCE_CRITERIA.md). No percentage productivity improvement or transaction-volume target is invented before representative tasks/workloads are known.

CAP-17 makes the operational dashboards, search, alerts and reports explicit across O-01/O-04/O-05/O-07. CAP-18 adds a reliable project completion decision across O-02–06. The [reference comparison](REFERENCE_COVERAGE.md) preserves the complete PDF/image coverage and latest DIR-008 hierarchy; reference ingestion did not itself approve P1; the subsequent explicit approval is APPR-002.

## Reference business journey

Masuk → Autentikasi → Pemeriksaan Izin dan Lingkup Perusahaan → Dasbor → Buat/Buka Proyek → Pilih Perusahaan → Klien → Unit/PIC → Kanal REGULAR/SIPLAH → Barang/Jasa → Harga/Pajak/Nilai → Penawaran → Revisi bila diperlukan → Persetujuan → Keputusan Stok/Pengadaan → Pemenuhan → Pengiriman → Dokumen Pengiriman dan Bukti Serah Terima → Dokumen Transaksi yang Diperlukan → Invoice → Dokumen Administrasi → Penagihan → Pemantauan Piutang → Pembayaran → Validasi Penyelesaian → Proyek Selesai → Dasbor/Laporan Diperbarui.

| Branch / loop | Product interpretation | Coverage |
| --- | --- | --- |
| Quotation not approved | Revise with history and obtain applicable approval; no implicit execution from merely printing | CAP-04/08; AC-04/08 |
| Stock available | After commercial confirmation, reserve supply for the project; a quotation alone does not reserve. Respect AVAILABLE = ON HAND - RESERVED - UNUSABLE and release/adjust when no longer needed; no dummy purchase or re-receipt | CAP-06; AC-16/20/22 |
| Insufficient stock | Purchase remaining needs from chosen supplier(s), record actual price/components; optional PO per purchase. Existing supply and a purchased shortage can jointly meet demand without duplication | CAP-05/06; AC-05/22 |
| Warehouse | Receive only actual arrivals, including partial/serial/damaged evidence, then use attributable dispatch for delivery; barcode/manual identification as supported | CAP-03/06/07; AC-03/06/07/16/22 |
| Drop-ship or service | Direct supplier fulfillment has confirmation, no warehouse receipt/dispatch fiction; services use applicable fulfillment/handover, no fake goods | CAP-06; AC-20 |
| Partial delivery | Record delivered and remaining quantities/evidence; repeat until fulfilled or validly closed, preserving each record | CAP-07/18; AC-07/21 |
| Invoice/admin/billing | Issue records agreed sales value without Cash-In; show BELUM DITAGIHKAN until explicitly billed. Billing activates the unpaid receivable and records billed_at/due_date for aging; selected administrative evidence remains visible | CAP-08–10; AC-08–10 |
| Partial payment / advance | Additional payments resolve the remainder; DP/termin may occur before delivery/invoice where applicable. A receipt Kuitansi follows a real payment, regardless of its position in the diagram; DIR-024 allows a distinguished *Kuitansi untuk Proses Pembayaran* before payment for payment processing, without implying money received | CAP-10; AC-10/12/16 |
| Completion / later correction | System checks every normal condition. Admin may satisfy requirements or mark eligible admin items TIDAK BERLAKU with reason/audit; Owner alone may exceptionally Force Complete with confirmation, reason, actor, timestamp, before/after state and audit. Preserve unmet conditions and revalidate after later material corrections | CAP-09/18; AC-09/21; DIR-011 policy resolved, GAP-022 technical work |

The image's partial-payment arrow does not erase outstanding debt or pass normal completion. An Owner override is a separately confirmed and audited exceptional outcome; any remaining receivable stays visible and financially unchanged by the override itself. Dashboards/report values and action queues update after relevant committed changes throughout the journey, including corrections; the final diagram node does not mean they update only after completion.

This is a product orientation, not a mandatory linear workflow or a P3 state machine. Existing-stock orders must not require a fictitious new purchase; service-only work must not create warehouse movements; direct supplier delivery must not create fictitious warehouse receipts. Exact applicability and transitions belong to P2/P3. The contextual clarification preserves requested capabilities while removing forced dummy work (GAP-021); all branch requirements remain represented, now with the DIR-011 business decisions applied.

The project is the operational hub. Users should be able to find outstanding items, fulfillment, documents, billing, payments, administrative requirements and history without reconstructing the process from database-oriented screens. Organization → Unit/Department → PIC is the same client concept for UNY, UGM and other clients. SIPLAH adds channel information to this same project-centered experience. (OB §§8–11; P1: Current Business Decisions.)

## Product boundary and ownership

The [scope](V1_SCOPE.md) owns MUST/SHOULD/DEFERRED/OUT OF SCOPE classifications, cost/resource boundaries, dependency inventory and P7 obligations. [Acceptance criteria](ACCEPTANCE_CRITERIA.md) own product and operational success evidence. [GAP_REGISTER](../00-governance/GAP_REGISTER.md) owns unresolved decisions and risk treatment. [P1 quality gate](P1_QUALITY_GATE.md) records adversarial review and planning verification.

P1 does not choose a schema, stock-valuation algorithm, tax formula, permission matrix, correction state machine, library version, infrastructure topology or implementation unit. It makes their product outcomes and dependencies explicit so later phases can decide them without inventing business intent.

# Information architecture

Status: REVIEW | Updated: 2026-10-18 | Owner: Planning

**Draft of the P7 re-baseline — not normative before APPR-011; the approved revision stays in force.** Run PC-2 replaced this document in place on 2026-10-18 under [DIR-078](../00-governance/DECISION_LOG.md#dir-077-dir-078-obs-030-and-tech-037--p7-ux-re-baseline-cp-a-decisions-run-pc-2-with-p9s-drafting-baseline-and-drafts) SP-63, as Stage 3 of the P7 UX re-baseline drafts it: not finalized, its deltas made in PC-3, and approved, or not, with APPR-011 at OG-2. Run PC-3 completed it on 2026-10-18 under [DIR-080](../00-governance/DECISION_LOG.md#dir-079-dir-080-obs-031-and-tech-038--p7-ux-re-baseline-c2-evidence-and-owner-decisions-run-pc-3-stage-3-completed-stage-4-and-gate-p7) SP-71–SP-73, with only the deltas the Owner's C2 decision requires ([DIR-079](../00-governance/DECISION_LOG.md#dir-079-dir-080-obs-031-and-tech-038--p7-ux-re-baseline-c2-evidence-and-owner-decisions-run-pc-3-stage-3-completed-stage-4-and-gate-p7)): UF-01's document layout on the pages that create or edit a generated business document — PG-014, PG-033, PG-036, PG-042, and PG-043 for a draft (CD-04; DESIGN_SYSTEM D-DS-23); the pages that issue a document from recorded facts hold no draft or preview and stay outside the list pending OQ-22 — and the pages CB-01–CB-03 change — PG-058, PG-061 and PG-006 with §9's G5 —, each marked `DIR-079`. Until then the approved revision — APPROVED under [APPR-008](../00-governance/DECISION_LOG.md#appr-008--p7-ux-information-architecture-and-design-system-approved), its SHA-256 recorded in TECH-037 (`git show 8da7e89:docs/07-ux-design/INFORMATION_ARCHITECTURE.md`) — stays in force, and no build unit may use this draft.

**Sources.** Stage 2's drafts are reused, never re-derived (DIR-078 §0): [IA_PAGE_INVENTORY](stage2/IA_PAGE_INVENTORY.md) — its personas, product map, shell, company context and the 65 pages PG-001–PG-065 with their stable identifiers —, [NAVIGATION_MAP](stage2/NAVIGATION_MAP.md) — the role navigation, reachability and task paths —, [QUERY_BUDGET_CHECKS](stage2/QUERY_BUDGET_CHECKS.md) and [NON_LOSS_MATRIX](stage2/NON_LOSS_MATRIX.md); the structure R1 that Checkpoint C1 approved (GD-01, GD-03); the Owner's CP-A decisions ([DIR-077](../00-governance/DECISION_LOG.md#dir-077-dir-078-obs-030-and-tech-037--p7-ux-re-baseline-cp-a-decisions-run-pc-2-with-p9s-drafting-baseline-and-drafts)) — the project list's journey summary (CA-06), the dispatch-ready signal QS-24 (CA-07) and the Owner's overdue signal QS-23 (CA-10); and every concern the approved revision owns, carried or superseded below with its [NON_LOSS_MATRIX](stage2/NON_LOSS_MATRIX.md) row.

Authority: P7 — UX, Information Architecture & Design System, re-baselined under the Owner's decisions DIR-053–DIR-077 and drafted in Stage 3 by run PC-2 (DIR-078). This document owns **navigation truth**: the Indonesian UI glossary, the personas and their navigation, the product map, the application shell on phone and desktop, the company-context model, the page inventory with its stable PG- identifiers, the placement of the derived signals and queues, the search, lookup and scanner entry points and the page-addressing conventions. Each page's navigation contract is in [NAVIGATION_CONTRACTS](NAVIGATION_CONTRACTS.md); the routes and the interactions behind its controls in [ROUTE_CONTRACTS](../03-workflows/ROUTE_CONTRACTS.md) and [INTERACTION_CONTRACTS](../03-workflows/INTERACTION_CONTRACTS.md); its strings in [LOCALIZATION](LOCALIZATION.md); interaction truth — commands, sessions, journeys, corrections, messages — in [ADMIN_FLOW](ADMIN_FLOW/README.md); visual rules in [DESIGN_SYSTEM](DESIGN_SYSTEM.md); presentation follows the Golden Frontend Reference v1.0.0 recorded in [DESIGN_REFERENCES](DESIGN_REFERENCES.md), for presentation only (DIR-061 SD-02).

**Anti-duplication contract:** business meaning stays in [V1_SCOPE](../01-product/V1_SCOPE.md), [DOMAIN_MODEL](../02-domain/DOMAIN_MODEL.md), [BUSINESS_RULES](../02-domain/BUSINESS_RULES.md) and [WORKFLOWS](../03-workflows/WORKFLOWS/README.md); authorization truth in [PERMISSIONS_MATRIX](../05-security/PERMISSIONS_MATRIX.md) and [SECURITY](../05-security/SECURITY.md); mechanisms in [CONCURRENCY_IDEMPOTENCY](../06-api-performance/CONCURRENCY_IDEMPOTENCY/README.md) and [PERFORMANCE](../06-api-performance/PERFORMANCE.md). This document cites their identifiers and never restates, relaxes or extends a rule. A page row names the capability and projection that govern it; it grants nothing — the server authorizes every request (AZ-01), and no page shows a field its projection does not allow.

**Boundary:** documentation only — no route file, component, configuration or image. Pages are specified at the depth a later build unit needs; fields the domain documents define are referenced, not listed again. P8 owns the tests and P11 the build units.

## 1. Conventions

- **Identifier families defined here:** UXN (derived navigation requirements), D-IA (information-architecture decisions), GL (glossary entries) and PG (pages, PG-001–PG-065, stable). SCR-01–SCR-60, the approved revision's screens, are kept as a mapping — each maps to at least one page (§8.7) — and are not used for new work. NAV- contracts are NAVIGATION_CONTRACTS', one per page with the page's number (NAV-001 for PG-001); ROUTE- and INT- are ROUTE_CONTRACTS' and INTERACTION_CONTRACTS'. UXI, IP, J, MSG and UXS stay ADMIN_FLOW's; UXV, PT, KB, SLOP and D-DS DESIGN_SYSTEM's.
- **Personas are not roles.** V1 has two launch roles, OWNER and ADMIN_OPERASIONAL, and every Admin capability is an individual grant (RG-01; RG-02); the personas of §5.1 are the repository's synthetic set.
- **Labels** in italics or quotation marks are end-user wording in Bahasa Indonesia, the default; every label is a glossary entry of §3 or formed from its entries, and its English rendering is [LOCALIZATION](LOCALIZATION.md)'s. Page names carry a proposed English rendering for the Owner's validation (GAP-037).
- **Audience notation** is that of PERMISSIONS_MATRIX §1 — ALL, SV, PV(c), FV(c), CV(c), EV(c), OWN, SELF —, a capability always a code of its §4, and the field families S0–S4 those of its §7.
- **Context tags** are those of WORKFLOWS §1: `[floor]`, `[desk]`, `[both]`; a `[desk]` page opens on a phone for reading and small tasks and does not break.
- **Phone** and **desktop** name the two designed patterns of [DESIGN_SYSTEM §6](DESIGN_SYSTEM.md#6-layout-grid-and-breakpoints); neither is derived from the other by shrinking or stretching.
- **Examples** — company codes such as ARJ, BTN and CKP, names and numbers — are fictional and illustrative.
- **The prototype's marker** "PROTOTIPE · data contoh" is not part of the IA.

## 2. Derived navigation requirements

Carried from the approved revision, each row derived from repository truth; and is the first link of the traceability chain of [P7_QUALITY_GATE](evidence/P7_QUALITY_GATE.md#desainpakai-exploration).

| ID | Requirement | Repository source | Home |
| --- | --- | --- | --- |
| UXN-01 | Every end-user label, navigation item, message and status is natural, professional Bahasa Indonesia by default, from one glossary, with English available through the language switch ([LOCALIZATION](LOCALIZATION.md); DIR-037 D7); client and legal names, codes, SKUs and user-authored content are data and are never translated | V1_SCOPE language and experience contract; CAP-14; REF-052; AC-14 | [§3](#3-glossary) |
| UXN-02 | The project detail is the operational hub: overview, items, quotation, procurement, fulfilment, delivery, documents, billing, payments, administrative requirements and history | OB §11; CAP-04; AC-04 | [§5](#5-product-map), PG-011 |
| UXN-03 | Navigation follows the business flow, not the database structure; every V1 area of CAP-01–CAP-18 is reachable | OB §11; V1_SCOPE MUST SHIP | [§5](#5-product-map), [§6](#6-application-shell-and-global-navigation) |
| UXN-04 | Phone navigation and desktop navigation are designed separately; a desktop layout that is merely shrunk is a failure | V1_SCOPE language and experience contract and P7 handoff requirements; OB §22; AC-14; GAP-020 | [§6](#6-application-shell-and-global-navigation) |
| UXN-05 | After sign-in the Owner lands on the consolidated and per-company dashboard, an Admin on the action queue across granted companies | WF-ACC-01; CAP-17; REF-007; REF-008 | [§9](#9-dashboards-and-queues) |
| UXN-06 | Company context: scope is the granted companies; a working filter narrows views only; every command names its target record's company; a selected company never substitutes for authorization | WF-ACC-01; CS-01–CS-03; AZ-01 | [§7](#7-company-context-model) |
| UXN-07 | A list across companies labels each row with its company; no figure sums S3 values of several companies for an Admin; consolidated figures are the Owner's | CS-08; PF-25; PF-41; CALC-12 | [§7](#7-company-context-model), [§9](#9-dashboards-and-queues) |
| UXN-08 | Where a physical row relates to a company outside scope, only the relation marker "perusahaan lain" appears — never a name, code or other identifier | PJ-04; PJ-06; H7-02 | [§7](#7-company-context-model) |
| UXN-09 | Navigation entries and controls follow the account's capabilities as a hint only; the server decides | AZ-01; H7-03; WS-07 | [§6](#6-application-shell-and-global-navigation) |
| UXN-10 | A page outside scope answers exactly like a nonexistent page; a page in scope without the capability is forbidden | AZ-11; WS-10; H7-08 | [§11](#11-page-addressing), [ADMIN_FLOW IP-21](ADMIN_FLOW/s05-command-model.md#ip-21--denial-and-not-found) |
| UXN-11 | Addresses carry public identifiers only; nothing sensitive appears in a title, address, notification or history entry | DATABASE §16; H7-10; DP-01; PJ-23 | [§11](#11-page-addressing) |
| UXN-12 | Every signal QS-01–QS-24 (`DIR-077`) has a place, an audience from the projection rules, an entry point, an action link and an empty state; no queue is stored | WORKFLOWS §10; PJ-13; PF-24; DATABASE §3 | [§9](#9-dashboards-and-queues) |
| UXN-13 | The dashboard uses at most five deferred groups — signals in at most four, period figures in one snapshot — and refreshes no faster than once a minute by background requests that name their props | QB-10; PF-24; PF-26; PF-34; PF-36; PF-43; TX-10 | [§9](#9-dashboards-and-queues) |
| UXN-14 | Every other page uses at most three deferred groups; rarely needed data is optional | PF-34; QB-01–QB-12 | [§8](#8-screen-inventory) |
| UXN-15 | Lists continue by keyset "more" without totals or page numbers, 25 rows a page and 100 at most, and sort and filter only by registered keys | PF-13–PF-17; PF-42; IX-11; WS-03 | [§8](#8-screen-inventory) |
| UXN-16 | Search and lookup: exact lookup first, names from three consecutive letters or digits, scope first, a cut notice at the candidate bound | PF-19–PF-23; DP-06; CAP-17; REF-050 | [§10](#10-search-lookup-and-scanner-entry-points) |
| UXN-17 | Scanner entry points use exact lookup only; an ambiguous code is never selected by the system; manual SKU entry is always available | SF-SCAN; QB-06; WF-MD-04; AC-03; REF-013 | [§10](#10-search-lookup-and-scanner-entry-points) |
| UXN-18 | Owner-only areas exist only for the Owner; an Admin's request for one is the AZ-11 answer | OD-01–OD-14; PERMISSIONS_MATRIX §4, §10 | [§5](#5-product-map), [§6](#6-application-shell-and-global-navigation) |
| UXN-19 | Each screen row names its capability, projection, scope behaviour, signals, budget row, deferred groups, sort and filter keys, phone and desktop pattern, commands and print view | DIR-033 §10-C | [§8](#8-screen-inventory) |
| UXN-20 | A print view shows exactly its page; downloads and exports are ordinary browser requests | DP-19; RB-04; PF-29 | [§8](#8-screen-inventory), [DESIGN_SYSTEM §17](DESIGN_SYSTEM.md#17-print-views) |
| UXN-21 | The phone supports dashboard and status monitoring; lookup and detail of projects, products, documents, billing and payments; quick actions and approvals; reasonable operational forms; receiving, dispatch and barcode work; manual SKU lookup | V1_SCOPE language and experience contract; AC-14; OB §22 | [§6](#6-application-shell-and-global-navigation), [§8](#8-screen-inventory) |
| UXN-22 | The desktop optimizes intensive administration, large tables, keyboard and mouse work, multi-item entry, document creation, finance, reporting and long workflows, and reuses known project data | V1_SCOPE language and experience contract; AC-14 | [§6](#6-application-shell-and-global-navigation), [§8](#8-screen-inventory) |
| UXN-23 | A shared master shows its S0 identity to everyone and its S2 relations by capability; related records appear only through scope-filtered lists, and no count reveals a relationship outside scope | PJ-10; CS-06; CS-07 | PG-054–PG-056 |
| UXN-24 | Settings, master-data and history areas exist; a record's timeline follows the audit projection; workspace audit search is the Owner's | PJ-11; RD-10; OB §23 | [§5](#5-product-map) |
| UXN-25 | The Owner review queue is designed only after the GAP-034 settlement, shows every source of the signal register and offers action only through normal commands | PERMISSIONS_MATRIX §10; HO-38; SECURITY LG-02 (`TECH-022`) | [§9](#9-dashboards-and-queues) |
| UXN-26 | Nothing V1_SCOPE excludes or defers is navigable: no supplier comparison, institution module, SIPLAH module, ledger, custom-role designer or template editor | V1_SCOPE OUT OF SCOPE and DEFERRED | [§5](#5-product-map) |

## 3. Glossary

The single home of the Indonesian UI vocabulary. **Label** is what the interface shows — its words, not its letter case, which [DESIGN_SYSTEM §16](DESIGN_SYSTEM.md#16-copy-and-formatting) sets per element; **Avoid** lists wording that must not replace it. Document type names are the approved names of V1_SCOPE and keep their legal meaning. Statuses and messages are formed from these entries; their visual form is in [DESIGN_SYSTEM §12](DESIGN_SYSTEM.md#12-status-outcome-and-state-patterns), and message patterns in [ADMIN_FLOW §14](ADMIN_FLOW/s14-15-messages-scenarios.md#14-message-catalogue).

### 3.1 Areas and navigation

| ID | Internal term | Label | Usage note | Avoid |
| --- | --- | --- | --- | --- |
| GL-001 | Dashboard (home) | Beranda | the first page after sign-in for both roles | Dashboard, Dasbor |
| GL-002 | Admin action queue | Antrean Tindakan | the Admin's Beranda content | To-do, Tugas Saya |
| GL-003 | Owner review queue (QS-14) | Tinjauan Owner | reviews what already happened; approves nothing | Persetujuan, Approval |
| GL-004 | Project | Proyek | — | Project, Order |
| GL-005 | Quotation | Penawaran | also the approved name of DOC-01 | Quotation |
| GL-006 | Purchase | Pembelian | the acquisition record; never implies payment | Order pembelian |
| GL-007 | Warehouse | Gudang | the shared physical warehouse | Inventory |
| GL-008 | Stock (availability view) | Stok | — | Inventori |
| GL-009 | Receiving | Barang Masuk | Owner's term | Penerimaan gudang, Goods receiving |
| GL-010 | Dispatch | Barang Keluar | Owner's term; the one physical subtraction | Pengeluaran barang |
| GL-011 | Stock movement history | Riwayat Stok | Owner's term | Mutasi stok, Stock movement |
| GL-012 | Stock opname (count) | Stok Opname | — | Stock take |
| GL-013 | Delivery | Pengiriman | the client-side record; never changes stock | Delivery |
| GL-014 | Documents | Dokumen | generated documents and uploads | — |
| GL-015 | Administrative requirements | Dokumen Administrasi | Owner's term; the per-project checklist | SPJ (as a module name), Persyaratan administratif |
| GL-016 | Finance area | Keuangan | groups Invoice, Piutang, Pembayaran, Pengeluaran | Akuntansi, Finance |
| GL-017 | Receivables | Piutang | Owner's term | Tagihan (for the receivable) |
| GL-018 | Payment (received) | Pembayaran | Owner's term; money actually received | Pelunasan |
| GL-019 | Disbursements, expenses and other cash | Pengeluaran & Kas Lain | — | Biaya-biaya |
| GL-020 | Reports | Laporan | — | Analitik, Insight |
| GL-021 | Master data | Data Master | clients, suppliers, products, racks | — |
| GL-022 | Settings | Pengaturan | Owner's term; Owner-only area | Konfigurasi, Setting |
| GL-023 | Search | Cari | — | Telusuri |
| GL-024 | Scan | Pindai | — | Scan |
| GL-025 | Activity history (audit search) | Riwayat Aktivitas | workspace search, Owner only | Audit log |
| GL-026 | Record timeline | Riwayat | on every record | Log, Aktivitas |
| GL-027 | Security log | Log Keamanan | Owner only | — |
| GL-028 | Own exports | Ekspor Saya | the requester's own list | Unduhan |
| GL-029 | Account page | Akun Saya | — | Profil saya |
| GL-030 | More (phone navigation) | Lainnya | — | Menu lain |

### 3.2 People, access and companies

| ID | Internal term | Label | Usage note | Avoid |
| --- | --- | --- | --- | --- |
| GL-031 | Owner role | Owner | the role's name stays as the Owner uses it | Super Admin, Pemilik |
| GL-032 | Admin Operasional role | Admin Operasional | — | Staf, Operator |
| GL-033 | Legal company | Perusahaan | always the legal entity | Tenant, Cabang |
| GL-034 | Company grant | Akses Perusahaan | which companies an account may work in | Tenant access |
| GL-035 | Capability | Kewenangan | one grantable permission | Permission, Hak akses (for a single capability) |
| GL-036 | ADM+ capability | Kewenangan khusus | corrections, closures and overrides | Admin plus, Super user |
| GL-037 | Owner-only | Khusus Owner | — | — |
| GL-038 | Relation marker | perusahaan lain | lower case, in place of any identity of a company outside scope | Perusahaan B, the company's code or colour |
| GL-039 | Working company filter | Tampilkan perusahaan | a view filter | Pindah perusahaan, Masuk sebagai |
| GL-040 | All companies (filter value) | Semua perusahaan | — | Gabungan (reserved for the Owner's consolidated figures) |
| GL-041 | Consolidated (Owner figures) | Gabungan | Owner only | Total grup |
| GL-042 | Session | Sesi | — | — |
| GL-043 | Sign in / sign out | Masuk / Keluar | — | Login, Logout |
| GL-044 | Password | Kata sandi | — | Password, Sandi |
| GL-045 | Credential link | Tautan atur kata sandi | onboarding and reset | Link reset |
| GL-046 | Step-up confirmation | Konfirmasi kata sandi | — | Verifikasi ulang |
| GL-047 | Deactivate (account, company) | Nonaktifkan | — | Hapus |
| GL-048 | Client organization | Klien | — | Customer, Pelanggan (except in "Kredit Pelanggan") |
| GL-049 | Client unit | Unit | — | Departemen |
| GL-050 | Person in charge | PIC | the Owner's term | Kontak person |
| GL-051 | Supplier | Pemasok | — | Supplier, Vendor |
| GL-052 | Product / service | Produk / Jasa | — | Item (for the master) |
| GL-053 | SKU | SKU | data label, not translated | Kode barang |
| GL-054 | Barcode | Barcode | "Barcode pabrikan", "Barcode internal" | Kode batang |
| GL-055 | Serial number | Nomor seri | — | SN |
| GL-056 | Base unit / alternate unit | Satuan dasar / Satuan lain | — | UoM |
| GL-057 | Rack / location | Lokasi rak | — | Bin |
| GL-058 | Archive (master) | Arsipkan | — | Hapus |

### 3.3 Project, commercial and purchasing

| ID | Internal term | Label | Usage note | Avoid |
| --- | --- | --- | --- | --- |
| GL-059 | Project item (demand line) | Item proyek | — | Baris permintaan |
| GL-060 | Channel REGULAR / SIPLAH | Kanal: Reguler / SIPLAH | SIPLAH is a channel, never a module | Proyek SIPLAH (as a type) |
| GL-061 | Commercial confirmation | Konfirmasi Proyek | the decision that the client's order is confirmed | Approve order, Konfirmasi komersial |
| GL-062 | Client approval / rejection of a quotation | Disetujui klien / Ditolak klien | records the client's answer | Approved |
| GL-063 | Fulfilment mode: WAREHOUSE / DROP-SHIP / SERVICE | Cara pemenuhan: Gudang / Kirim langsung / Jasa | — | Dropship (bare) |
| GL-064 | Shortage | Kekurangan stok | — | Backorder |
| GL-065 | Purchase charge | Biaya pembelian | "Masuk biaya perolehan" or "Beban" | Ongkos lain |
| GL-066 | Source bill of a charge | Tagihan sumber | issuer, reference, total | — |
| GL-067 | Purchase-remainder closure | Tutup Sisa Pembelian | — | Close PO |
| GL-068 | Supplementary purchase | Pembelian susulan | — | — |
| GL-069 | Remaining-scope cancellation | Batalkan sisa | — | — |
| GL-070 | Project completion | Penyelesaian Proyek | "Selesaikan Proyek" as the action | Tutup proyek |
| GL-071 | Completion conditions (gate) | Syarat penyelesaian | ten conditions | Checklist selesai |
| GL-072 | Force Complete | Selesaikan Paksa | Owner only | Override |
| GL-073 | Residual obligations | Kewajiban tersisa | — | — |
| GL-074 | Project cancellation | Batalkan Proyek | — | Hapus proyek |
| GL-075 | Deadline | Tenggat | — | Deadline |

### 3.4 Warehouse

| ID | Internal term | Label | Usage note | Avoid |
| --- | --- | --- | --- | --- |
| GL-076 | ON HAND | Stok fisik | includes unusable units | Stok total |
| GL-077 | RESERVED | Direservasi | — | Dialokasikan |
| GL-078 | UNUSABLE | Tidak layak pakai | — | Rusak (as the category) |
| GL-079 | AVAILABLE | Tersedia | — | Stok bebas |
| GL-080 | Reservation | Reservasi | "Reservasi", "Kurangi Reservasi", "Lepas" | Alokasi, Booking |
| GL-081 | Receiving lot | Lot | with its opaque code | Batch |
| GL-082 | Oldest-first picking aid | Ambil lebih dulu | an aid, never a rule | FIFO |
| GL-083 | Receiving outcomes | Layak pakai / Rusak diterima / Ditolak | — | — |
| GL-084 | Condition change | Ubah kondisi | "Periksa Ulang" for the way back | — |
| GL-085 | Quarantine location | Karantina | — | — |
| GL-086 | Adjustment (decrease) | Penyesuaian Stok | decreases only; the action "Catat Penyesuaian Stok" | Koreksi stok (for the adjustment itself), Kurangi Stok |
| GL-087 | Disposal | Pemusnahan | — | — |
| GL-088 | Inventory loss | Kerugian persediaan | — | — |
| GL-089 | Pending unattributed loss case | Selisih menunggu penetapan | awaiting the Owner's attribution | Selisih perusahaan B |
| GL-090 | Count surplus finding | Temuan lebih | awaiting the Owner's attribution | — |
| GL-091 | Owner attribution | Tetapkan perusahaan | Owner only | — |
| GL-092 | Stale count | Hitungan kedaluwarsa | — | — |
| GL-093 | Inter-company allocation | Alokasi antar-perusahaan | the only use of "alokasi" | Transfer stok |
| GL-094 | Location move | Pindah lokasi | — | — |
| GL-095 | Minimum stock / restock advice | Stok minimum / Saran isi ulang | advice only | Auto reorder |
| GL-096 | Purchase return / sales return | Retur ke pemasok / Retur dari klien | — | Retur (bare, where direction is unclear) |
| GL-097 | Drop-ship confirmation | Konfirmasi kirim langsung | — | — |
| GL-098 | Service handover | Serah Terima Jasa | — | — |
| GL-099 | Recipient / delivery proof | Penerima / Bukti kirim | — | POD |
| GL-100 | Delivery discrepancy | Selisih pengiriman | — | — |
| GL-101 | Delivery closure | Tutup Pengiriman | — | — |
| GL-102 | Correction case | Kasus koreksi | — | Tiket |

### 3.5 Documents and administration

| ID | Internal term | Label | Usage note | Avoid |
| --- | --- | --- | --- | --- |
| GL-103 | DOC-01 | Penawaran | approved name | — |
| GL-104 | DOC-02 | Purchase Order / PO | approved name | Pesanan Pembelian |
| GL-105 | DOC-03 | Surat Jalan / Faktur Pengiriman | approved name | DO |
| GL-106 | DOC-04 | Nota | approved name | — |
| GL-107 | DOC-05 | Kuitansi | approved name; pre-payment mode: "Kuitansi untuk Proses Pembayaran" | Kwitansi |
| GL-108 | DOC-06 | Invoice | approved name | Faktur (alone) |
| GL-109 | DOC-07 | Lampiran Kuitansi | approved name | — |
| GL-110 | DOC-08 | BAST | approved name | — |
| GL-111 | DOC-09 | SPK | approved name; company-issued | — |
| GL-112 | DOC-10 | Berita Acara Pemeriksaan Barang | approved name | BAPB (unexplained) |
| GL-113 | DOC-11 | HPS | approved name; company-prepared | — |
| GL-114 | DOC-12 | Nota Terima Barang | approved name | — |
| GL-115 | DOC-13 | Surat Permintaan Pembayaran | approved name | — |
| GL-116 | DOC-14 | Surat Pesanan | approved name; company-issued layout | — |
| GL-117 | Draft | Draf | — | Draft, Konsep |
| GL-118 | Issue (a document) | Terbitkan | state: Terbit | Finalisasi, Posting |
| GL-119 | Revision | Revisi | a new issued version | Edit |
| GL-120 | Void | Batalkan Dokumen | state: Dibatalkan | Hapus, Void |
| GL-121 | Rendition states | PDF sedang dibuat / PDF siap / PDF gagal dibuat | — | Rendering, Error |
| GL-122 | Evidence | Bukti | uploaded originals and proofs | Lampiran (for evidence) |
| GL-123 | Upload | Unggah | — | Upload |
| GL-124 | Client original | Asli dari klien | — | — |
| GL-125 | Pool evidence | Bukti gudang bersama | — | — |
| GL-126 | Requirement: required / optional | Wajib / Opsional | — | Mandatory |
| GL-127 | Requirement status | Belum ada / Disiapkan / Terpenuhi / Tidak berlaku | derived | Pending, Done |
| GL-128 | N/A waiver | Tidak Berlaku | on eligible items only | Skip, Lewati |
| GL-129 | Waiver-eligible | Boleh ditandai tidak berlaku | Owner sets it | — |

### 3.6 Finance

| ID | Internal term | Label | Usage note | Avoid |
| --- | --- | --- | --- | --- |
| GL-130 | Sales / transaction value | Nilai penjualan | value of issued invoices | Omzet, Pendapatan kas |
| GL-131 | Issued, not billed | Belum Ditagihkan | separate from active receivables | Outstanding |
| GL-132 | Billing act | Tagihkan | state: Ditagihkan | Kirim invoice |
| GL-133 | Billing date / due date | Tanggal tagih / Jatuh tempo | — | TOP |
| GL-134 | Active receivable | Piutang aktif | — | — |
| GL-135 | Outstanding of an invoice | Sisa tagihan | — | Saldo |
| GL-136 | Aging | Umur piutang | buckets: Belum jatuh tempo, 1–30, 31–60, 61–90, >90 hari, Migrasi | Aging |
| GL-137 | Overdue | Lewat jatuh tempo | — | Telat |
| GL-138 | Paid in full | Lunas | derived | Paid |
| GL-139 | Cash-In | Kas masuk | money actually received | Pendapatan |
| GL-140 | Payment application | Penerapan pembayaran | "Terapkan ke Invoice" | Alokasi pembayaran |
| GL-141 | Customer credit | Kredit Pelanggan | — | Deposit, Saldo lebih |
| GL-142 | Refund | Pengembalian dana | — | Refund |
| GL-143 | Fee settlement / tax settlement | Potongan fee / Pajak dipotong atau dipungut | non-cash settlements with evidence | Diskon |
| GL-144 | Claimed deduction | Potongan diklaim | not yet evidenced | — |
| GL-145 | Dispute hold | Sengketa | — | Dispute |
| GL-146 | Write-off | Hapuskan Piutang | Owner only; state: Dihapuskan | Pemutihan |
| GL-147 | Disbursement / Cash-Out | Pengeluaran kas / Kas keluar | — | Biaya (for cash) |
| GL-148 | Expense | Beban | — | — |
| GL-149 | Other Cash-In | Kas masuk lain | — | — |
| GL-150 | Bank statement line | Mutasi bank | — | — |
| GL-151 | Cost / HPP | Biaya / HPP | — | Modal |
| GL-152 | Profit / margin | Laba / Margin | managerial | Untung |
| GL-153 | Managerial cashflow | Arus kas | — | Cashflow |
| GL-154 | Pre-payment Kuitansi state | Menunggu pembayaran | until linked or voided | Belum lunas |
| GL-155 | Contra-fact | Kontra | "Batalkan catatan (kontra)" | Hapus, Reverse |
| GL-156 | Reallocation | Pindahkan penerapan | — | Realokasi |

### 3.7 Commands, answers, dates and system

| ID | Internal term | Label | Usage note | Avoid |
| --- | --- | --- | --- | --- |
| GL-157 | Save (draft, master) | Simpan | — | Submit |
| GL-158 | Record (a fact) | Catat | "Catat Pembayaran", "Catat Barang Masuk" | Posting |
| GL-159 | COMMITTED | Tersimpan / Tercatat / Terbit | formed with the action's own verb | Berhasil (alone), Sukses |
| GL-160 | REJECTED | Tidak dapat diproses | — | Gagal, Error |
| GL-161 | CONFLICT | Data sudah berubah · Sudah tercatat | by cause | Konflik (alone) |
| GL-162 | PENDING | Sedang diproses | background work only | Loading |
| GL-163 | FAILED | Belum dapat dipastikan · Belum diproses | by cause | Gagal disimpan |
| GL-164 | Retry | Coba Lagi | same intent | Ulangi dari awal |
| GL-165 | Re-apply | Terapkan ulang | after a stale conflict | — |
| GL-166 | Reason | Alasan | — | Catatan (where a reason is required) |
| GL-167 | Business date | Tanggal transaksi | — | Tanggal posting |
| GL-168 | Recorded at | Dicatat | — | Created at |
| GL-169 | Backdated | Tanggal mundur | — | Backdate |
| GL-170 | Correction / reversal | Koreksi / Pembalikan | — | Undo |
| GL-171 | Load more | Muat lagi | — | Halaman berikutnya |
| GL-172 | Filter / sort | Filter / Urutkan | — | Saring |
| GL-173 | Export | Ekspor | states: Diminta, Diproses, Siap diunduh, Gagal, Tidak tersedia | Download (as the feature) |
| GL-174 | Download / print | Unduh / Cetak | — | — |
| GL-175 | Not found | Halaman tidak ditemukan | also for a page outside scope | Akses ditolak (for out of scope) |
| GL-176 | Numbering scheme | Format nomor | — | — |
| GL-177 | Start of numbering | Mulai Penomoran | Owner only | — |
| GL-178 | Seed | Nomor awal | "Tetapkan Nomor Awal" | — |
| GL-179 | Seed boundary | Batas awal penomoran | — | — |
| GL-180 | Go-live | Go-live | the Owner's own term for the cutover day | — |
| GL-181 | Opening import | Impor Data Awal | — | Migrasi (as a menu name) |
| GL-182 | Import batch states | Disiapkan / Uji coba lolos / Disetujui bersama / Selesai diimpor / Dibatalkan | — | — |
| GL-183 | Dry run | Uji coba impor | — | — |
| GL-184 | Cutoff date | Tanggal batas | — | — |
| GL-185 | Shortcut help | Pintasan | — | Hotkey |
| GL-186 | Reference code (correlation id) | Kode rujukan | on error pages | Error ID |
| GL-187 | Overflow menu of page actions | Tindakan Lain | the page's secondary actions; distinct from the phone navigation's *Lainnya* | Lainnya, Opsi |
| GL-188 | Overflow menu of a record's parts | Bagian Lain | the tabs that do not fit, in order (D-IA-09) | Lainnya |
| GL-189 | A project's purchasing part | Pengadaan | the part of SCR-10 listing the project's purchases and allocated cost; the area and the record stay *Pembelian* (GL-006) | Pembelian (as the part's name) |
| GL-190 | Undo a line removal | Urungkan | offered in the line editor after a line is removed, before the form is sent (KB-07) | Batal (for undo) |
| GL-191 | Access preview | Pratinjau Akses | the capability-by-company matrix shown before "Simpan Akses" (H7-06) | Hasil Akses |

**Conventions for data.** Legal and client names, SKUs, codes, serials, document numbers, user-entered descriptions and the content of uploaded documents are shown exactly as stored. Wording inside generated documents that the product owns — headings, field captions, the draft marking, "untuk proses pembayaran" — follows this glossary and the document-type names above; the clauses, fields and layout of each of the fourteen templates are validated with the Owner and Admin under GAP-019 and are a P11 and UAT matter, not designed here.

### 3.8 Labels of the re-baselined structure (Stage 3)

The labels R1's structure and the Owner's CP-A decisions add, recorded in the gate record's R1 sections or the journey specifications of [Stage 2](stage2/README.md); "proposed" marks wording no source gives, for the Owner's validation with LOCALIZATION's other proposed strings. Page names that are formed from §3.1–§3.7's entries — *Detail Pembelian*, *Buat Invoice*, *Catat Pembayaran* — need no entry of their own.

| ID | Internal term | Label | Usage note | Avoid |
| --- | --- | --- | --- | --- |
| GL-192 | Work home — work grouped by what has to happen next | Pekerjaan | every account's work page, PG-007, and the Admin's landing page; it takes the place of GL-002's *Antrean Tindakan* | Antrean Tindakan, To-do |
| GL-193 | The Owner's review entry | Tinjauan | the Owner's primary entry and page PG-008 — GL-003's queue, named as R1 names the entry | Persetujuan |
| GL-194 | Full menu | Menu lengkap | the collapsed list of the modules behind the primary entries (§5.3) | Semua menu |
| GL-195 | Work section of the phone's *Lainnya* | Kerja | the Owner's *Pekerjaan* and *Tinjauan* on the phone | — |
| GL-196 | Create menu | Buat | the top bar's menu of new roots (§10) | Tambah, Baru |
| GL-197 | Search-and-scan field | Cari atau pindai kode | the top bar's one field for typed and scanned codes (D-IA-04) | — |
| GL-198 | Display language | Bahasa tampilan | the switch "Indonesia \| English" in the account menu and on *Akun Saya* (`DIR-077` CA-08) | Bahasa sistem |
| GL-199 | Project journey | Perjalanan Proyek | the project's workspace, PG-011 | Detail Proyek |
| GL-200 | Project stages | Tahap proyek | the derived stage track of PG-011 | Status proyek |
| GL-201 | Now, next and blockers | Sekarang · Berikutnya · Hambatan | the journey's now strip | — |
| GL-202 | Document readiness | Kesiapan dokumen | the journey's documents part, with each project's checklist | — |
| GL-203 | Project details | Rincian proyek | the journey's parts below its strip | — |
| GL-204 | Remaining before completion | Yang tersisa sebelum selesai | the journey's unmet completion conditions (QS-15) | — |
| GL-205 | Financial position | Posisi keuangan | the journey's finance part, with `finance.view` | — |
| GL-206 | Projects needing attention | Proyek yang perlu perhatian | *Beranda*'s project rows with the segmented stage indicator | — |
| GL-207 | Needs your decision | Perlu keputusan Anda | *Beranda*'s decision group | — |
| GL-208 | Operational queue | Antrean operasional | *Beranda*'s one folded line of the operational groups, with "Buka Pekerjaan" | — |
| GL-209 | Show — the company filter | Tampilkan | "Tampilkan: Semua perusahaan" (§7) | Filter perusahaan |
| GL-210 | See all | Lihat Semua | a signal group's link to its full list (PT-21) | — |
| GL-211 | Overdue project | Proyek melewati tenggat | QS-23's row, the Owner's only (`DIR-077` CA-10) — proposed | Proyek terlambat |
| GL-212 | Dispatch-ready | Siap dikeluarkan | QS-24's rows (`DIR-077` CA-07) — proposed | — |

**Entries the structure changes.** GL-002 *Antrean Tindakan* gives way to GL-192 *Pekerjaan* as the work home's label; GL-003 keeps the queue's meaning, its entry and page named *Tinjauan* (GL-193); GL-030 *Lainnya* stays the phone's fifth place. Every other entry of §3.1–§3.7 is unchanged.

## 4. Design decisions

The approved revision's D-IA-01–D-IA-17 with their Stage 3 disposition — the gate record's [provisional dispositions](evidence/P7_REBASELINE_GATE.md#provisional-dispositions) as R1's [disposition delta](evidence/P7_REBASELINE_GATE.md#c1-rework--disposition-delta) revised them, carried from [NON_LOSS_MATRIX](stage2/NON_LOSS_MATRIX.md) section O —, then the decisions Stage 3 adds. A KEEP decision keeps its text in the approved revision (`git show 8da7e89:docs/07-ux-design/INFORMATION_ARCHITECTURE.md`, §4) and applies to the pages named; an ADAPT decision keeps its purpose in the form named; a SUPERSEDE decision is replaced by what is named. Each stays SUPERSEDED-PENDING in [DECISION_INDEX](../00-governance/DECISION_INDEX.md) until APPR-011.

| ID | Disposition | Where honoured or what replaces it |
| --- | --- | --- |
| D-IA-01 | SUPERSEDE (R1's delta) | task-first primary entries per persona with the collapsed *Menu lengkap* — §5.2, §5.3, §6.1 |
| D-IA-02 | KEEP | the company filter in list headers and on *Beranda* and *Pekerjaan*, never in the shell — §7 |
| D-IA-03 | ADAPT (R1's delta) | the phone's bottom bar per persona, four entries and at most five — §6.2 |
| D-IA-04 | KEEP | one entry, "Cari atau pindai kode", for typed and scanned codes — §6.1, §10 |
| D-IA-05 | KEEP | the notice strip and the idle dialog — §6.1, §8.8 |
| D-IA-06 | KEEP | no badge or count on an entry — §6.3 |
| D-IA-07 | SUPERSEDE | the Owner's *Beranda* of R1 c) — decisions first, the four KPIs, the projects needing attention, the operational queues as one folded line — PG-006; §9 |
| D-IA-08 | ADAPT | work grouped by what has to happen next — PG-007; §9 |
| D-IA-09 | SUPERSEDE | the project journey workspace of R1 e) — PG-011 |
| D-IA-10 | ADAPT (R1's delta) | each project's checklist inside the journey's "Kesiapan dokumen"; the cross-project view PG-039 |
| D-IA-11 | KEEP | the review queue's two parts — PG-008; §9.2 |
| D-IA-12 | KEEP | one section per company in a report — PG-051 |
| D-IA-13 | KEEP | the numbering area ordered by need — PG-061 |
| D-IA-14 | KEEP | master records addressable and search-first — PG-054, PG-055, PG-056 |
| D-IA-15 | ADAPT | receivables a list; ageing per company on *Beranda*'s one-company view and in the report — PG-044; PG-006; PG-051 |
| D-IA-16 | KEEP | dispatch and delivery two linked records — PG-023, PG-024; PG-030, PG-031 |
| D-IA-17 | KEEP | stock by product with its four quantities and the pending line — PG-018, PG-019 |

**Stage 3's decisions** — Level 1 unless an Owner decision is named; each changes no P0–P6 meaning:

| ID | Decision | Grounds |
| --- | --- | --- |
| D-IA-18 | The 65 pages of IA_PAGE_INVENTORY are the page inventory, each with its stable PG- identifier; SCR-01–SCR-60 survive as a mapping only (§8.7) | DIR-078 SP-63; GD-03 |
| D-IA-19 | The project list shows each row's journey stage, blocker marker and next action from QB-01's journey-summary group, filled when the group arrives; no stage filter is offered unless P8 shows a bounded filter that meets PF-42 | the Owner's CA-06 (DIR-077); PERFORMANCE QB-01 (`DIR-077`) |
| D-IA-20 | *Pekerjaan*'s goods group lists the dispatch-ready lines (QS-24), each leading to *Keluarkan Barang* (PG-024) for `stock.dispatch` and otherwise to the project's journey; "Lihat Semua" leads to the dispatch worklist (PG-023) | the Owner's CA-07; PJ-13 |
| D-IA-21 | The Owner's decision group on *Beranda* holds the overdue projects (QS-23) per company, each row leading to the project's journey; no Admin page shows the signal, and the deadline stays a project fact as the approved design shows it | the Owner's CA-10; PJ-13 (`DIR-077`) |
| D-IA-22 | The five DECIDE-IN-STAGE-2 items take IA_PAGE_INVENTORY §7's resolutions — §14 | IA_PAGE_INVENTORY §7; AUTH_JOURNEYS |
| D-IA-23 | *Bahasa tampilan* in the account menu and on *Akun Saya* changes the account's own display language at once and keeps it in `users.locale`; before sign-in the language lives in the address of the sign-in pages only | the Owner's CA-08; [LOCALIZATION](LOCALIZATION.md) |

## 5. Product map

Every V1 area as the users meet it — task first, from [IA_PAGE_INVENTORY](stage2/IA_PAGE_INVENTORY.md) §§1–2, which R1 structures. An entry exists for an account only when a page behind it is open to the account's capabilities and companies (UXN-09); an Owner-only entry never appears for an Admin (UXN-18); nothing excluded or deferred by V1_SCOPE is an entry (UXN-26).

### 5.1 Personas and devices

Personas are not roles: V1 has two launch roles, OWNER and ADMIN_OPERASIONAL, and every Admin capability is an individual grant (RG-01; RG-02). The personas are the repository's synthetic set ([AK-8](../00-governance/DECISION_LOG.md#dir-061-dir-062-obs-023-and-tech-031--p7-ux-re-baseline-stage-2-decisions-s2-0-authorization-baseline-and-prototype-setup); [SS-01](evidence/P7_REBASELINE_GATE.md#stage-2--owner-decisions-dir-061); SP-19).

| Persona | Role | Companies | Devices and context | Lands on | Sources |
| --- | --- | --- | --- | --- | --- |
| **Laras** | OWNER — every capability through the role (RG-02) | every company, ARJ, BTN and CKP (CS-01; RG-03) | the desk for decisions and Owner-only work; the phone for monitoring and lookup | *Beranda* | persona task analysis, "Owner — desktop and phone"; R1 a) |
| **Dimas** | ADMIN_OPERASIONAL | ARJ and BTN (CS-02) | the desk — multi-line editors, issue and payment work, `[desk]` journeys; the phone for reading and small tasks | *Pekerjaan* | "Admin Operasional — desk work"; R1 and VC1's record of him |
| **Bayu** | ADMIN_OPERASIONAL | ARJ (CS-02) | the phone on the warehouse floor, `[floor]` journeys, a keyboard-wedge scanner where one is attached ([D-UX-09](ADMIN_FLOW/s01-04-foundations.md#4-design-decisions); [GAP-020](../00-governance/GAP_REGISTER.md#triage)) | *Pekerjaan* | "Warehouse/field — an Admin Operasional account with warehouse grants, phone-first"; R1 |

**Grant sets — illustrative, not roles (SP-19).** Completed only from [PERMISSIONS_MATRIX §4](../05-security/PERMISSIONS_MATRIX.md#4-capability-catalogue), each grant with its prerequisites (RG-06), from the work the persona task analysis describes — not only what the journeys need. R1 and VC1 record Dimas with ARJ and BTN, `finance.view` and `cost.view`, and Bayu with ARJ warehouse grants and without `finance.view`.

| Capability | Class | Prerequisites | Dimas | Bayu | The work it serves (persona task analysis) |
| --- | --- | --- | --- | --- | --- |
| `projects.view` | ADM | grant | ✓ | ✓ | project lists and the journey; prerequisite of most commands below |
| `finance.view` | ADM | grant | ✓ | — | invoices, billing, payments, the *Keuangan* lists |
| `cost.view` | ADM | grant | ✓ | — | purchases with lines and charges |
| `stock.view` | ADM | — | ✓ | ✓ | prerequisite of reservation (Dimas); stock and racks, receiving, dispatch (Bayu) |
| `evidence.view` | ADM | grant | ✓ | ✓ | proofs and uploads of the families each may view (PJ-08) |
| `projects.manage` | ADM | `projects.view` | ✓ | — | project setup (J-PRJ-01) |
| `quotation.manage` | ADM | `projects.view`, `finance.view` | ✓ | — | quotations with many lines (J-QUO-01) |
| `projects.confirm` | ADM | `projects.view` | ✓ | — | the client's answer as confirmation (J-QUO-01; [AX-13](../03-workflows/WORKFLOWS/s08-09-corrections-indivisible.md#9-indivisible-business-actions)) |
| `stock.reserve` | ADM | `stock.view`, `projects.view` | ✓ | ✓ | reservations (J-INV-02); the same-action offer at receipt (AX-04) — S2-R06's variant removes it from Bayu |
| `purchase.manage` | ADM | `cost.view` | ✓ | — | purchases (J-PUR-01, J-PUR-02) |
| `stock.receive` | ADM | `stock.view` | — | ✓ | receiving (J-INV-01) |
| `stock.dispatch` | ADM | `stock.view`, `projects.view` | — | ✓ | dispatch (J-INV-03) |
| `stock.count` | ADM | `stock.view` | — | ✓ | stock opname (J-INV-05) |
| `delivery.record` | ADM | `projects.view` | — | ✓ | delivery records (J-FUL-02) |
| `documents.issue` | ADM | the subject family's view capability | ✓ | ✓ | documents (J-DOC-01); the operational [DOC-03](../01-product/V1_SCOPE.md#selectable-document-catalog) and DOC-12 under `projects.view` (PJ-15) for Bayu |
| `evidence.upload` | ADM | `evidence.view` | ✓ | ✓ | uploads (WF-DOC-02); delivery proof (DELIVERY_PROOF, PJ-08) |
| `invoice.issue` | ADM | `finance.view` | ✓ | — | invoices (J-FIN-01) |
| `billing.record` | ADM | `finance.view` | ✓ | — | billing (J-FIN-02) |
| `payment.record` | ADM | `finance.view` | ✓ | — | payments with their applications (J-FIN-03) |
| `payment.apply` | ADM | `finance.view` | ✓ | — | applications of money left unapplied (AX-19) |
| `requirements.manage` | ADM | `projects.view` | ✓ | — | *Dokumen Administrasi* (J-ADM-01) |
| `requirements.waive` | ADM | `projects.view` | ✓ | — | *Tidak Berlaku* on waiver-eligible items ([BR-ADM-02](../02-domain/BUSINESS_RULES.md#administration-adm)) |
| `projects.complete` | ADM | `projects.view` | ✓ | — | project completion ([SCR-14](#82-projects-and-quotations); AX-25) |
| `invoice.correct`, `billing.correct`, `documents.correct`, `finance.contra`, `payment.reallocate` | ADM_PLUS | `finance.view`; `documents.correct` the family's view capability | ✓ | — | corrections of the records the analysis names among the Admin's critical tasks — documents, invoices, billing acts and payments — stating what is reversed and what stays (D-UX-10) |
| `warnings.override` | ADM_PLUS | the record's view capability | ✓ | — | confirming a duplicate-payment or duplicate-evidence warning (D-UX-06; IP-05; BD-02) |
| `stock.condition`, `stock.adjust`, `stock.count_apply` | ADM_PLUS | `stock.view` | — | ✓ | counts and their findings, condition changes and losses (J-INV-04, J-INV-05, J-INV-06) |

Every capability above that the table does not mark for a persona is not held. Bayu holds no `finance.view` and no `cost.view`: his receiving runs under the receiving projection (PJ-18), without prices, taxes, charges or costs. Neither Admin holds an OWNER_ONLY capability (RG-02) or `stock.allocate_intercompany`, so another company's lot is never theirs to draw (XL-1). **Second factor:** all three accounts are high-risk under [AU-18](../05-security/SECURITY.md#2-authentication-sessions-and-credentials) — the OWNER role; an ADM_PLUS grant; `finance.view`, `cost.view` or `evidence.view` — so each signs in with TOTP ([AUTH_JOURNEYS](stage2/AUTH_JOURNEYS.md)).

**Variants and comparison columns.** Bayu without `stock.reserve`, which S2-R06 needs, is a recorded simulation scenario, not a persona ([SIMULATION_REGISTER](stage2/SIMULATION_REGISTER.md)). [NAVIGATION_MAP](stage2/NAVIGATION_MAP.md) adds a "full Admin" column — every ADM and ADM_PLUS capability in all three companies — to show every page an Admin can reach.

### 5.2 Primary entries

Task first: two to four primary entries per persona, the modules behind them in a collapsed *Menu lengkap* ([R1 a)](evidence/P7_REBASELINE_GATE.md#a-navigation--task-first-oc-02-pl-4); [OC-02](../00-governance/DECISION_LOG.md#dir-055-dir-056-obs-020-and-tech-028--p7-ux-re-baseline-checkpoint-c1-decision-rework-authorization-baseline-and-rework); [D-IA-01](#4-design-decisions) SUPERSEDE as R1 revised it).

| Persona | Primary entries, desktop | Bottom bar, phone | Lands on | Why each entry exists (R1 a)) |
| --- | --- | --- | --- | --- |
| Owner | *Beranda* · *Proyek* · *Pekerjaan* · *Tinjauan* | *Beranda* · *Proyek* · *Cari* · *Lainnya* | *Beranda* | the Owner's decisions, exceptions and figures (OC-05); the projects as journeys; the operational queues, demoted from *Beranda* and one click away; the review queue ([QS-14](../03-workflows/WORKFLOWS/s10-14-signals-decisions-traceability.md#10-derived-signals-and-action-queue)) |
| Admin | *Pekerjaan* · *Proyek* | *Pekerjaan* · *Proyek* · *Cari* · *Lainnya* | *Pekerjaan* | work grouped by what has to happen next (OC-01); the projects as journeys |

Work is entered from *Beranda*'s decision rows, *Pekerjaan*'s work rows, the journey's *Berikutnya* and primary action, search and scan, and "Buat" (§10).

### 5.3 *Menu lengkap* — the modules and their sub-pages as tabs

Each module opens on its first sub-page, its sub-pages shown as tabs, and rarely used ones behind a *Lainnya* tab (R1 a), "as Gudang › Stok shows"). The places come from R1's [revised non-loss map](evidence/P7_REBASELINE_GATE.md#revised-screens-scr-01scr-60).

| Module | Sub-pages, as tabs | Pages (§8) | Present when the account holds |
| --- | --- | --- | --- |
| *Pembelian* | *Daftar Pembelian* | PG-015–PG-017 | `cost.view` |
| *Gudang* | *Stok* · *Barang Masuk* · *Barang Keluar* · *Reservasi* · *Stok Opname* · *Lainnya* — *Riwayat Stok*, *Penyesuaian & Kondisi*, *Retur ke Pemasok*, *Selisih & Temuan*, *Pengiriman* | PG-018–PG-030 | `stock.view`, or a warehouse command capability (`stock.receive`, `stock.dispatch`); *Selisih & Temuan* the Owner and `stock.resolve_unattributed_evidence` (the approved revision's §5); *Pengiriman* `projects.view` |
| *Keuangan* | *Invoice* · *Piutang* · *Pembayaran* · *Potongan* · *Lainnya* — *Kredit Pelanggan*, *Pengeluaran & Kas Lain*, *Mutasi Bank* | PG-041–PG-050 | `finance.view` |
| *Dokumen* | *Dokumen* · *Penawaran* · *Bukti* · *Dokumen Administrasi* · *Kasus Koreksi* (§7.4) | PG-035–PG-040 | `projects.view`; *Bukti* `evidence.view` |
| *Laporan* | *Laporan* · *Laporan Gabungan* (Owner) · *Ekspor Saya* | PG-051–PG-053 | a report's read class (RD-02–RD-07); *Ekspor Saya* `reports.export` |
| *Data Master* | *Klien* · *Pemasok* · *Produk & Jasa* · *Lokasi Rak* · *Impor Data Awal* | PG-054–PG-058 | every active account for S0 names ([CS-06](../05-security/PERMISSIONS_MATRIX.md#6-company-scope-rules)); *Impor Data Awal* `import.prepare` or the Owner |
| *Aktivitas & Keamanan* (Owner) | *Riwayat Aktivitas* · *Log Keamanan* | PG-063, PG-064 | `audit.view`, `security_log.view` (OWNER_ONLY) |
| *Pengaturan* (Owner) | *Perusahaan* · *Pengguna & Akses* · *Penomoran Dokumen* · *Pajak* | PG-059–PG-062 | `companies.manage`, `users.manage`, `permissions.manage`, `tax.configure` (OWNER_ONLY) |

The journey's own pages — the quotation, the completion page, the delivery record, direct shipment, the service handover and the client return — are reached from the project and from work rows, not from *Menu lengkap* (R1's revised map). [CAP-16](../01-product/V1_SCOPE.md#must-ship)'s local backups have no screen, CAP-14 is the design as a whole, and SIPLAH is a project channel with its own fields, never an area (the approved revision's §5; V1_SCOPE).

### 5.4 Account menu, system page and the pages outside the shell

- **Account menu** — the account's name and role (*Owner* or *Admin Operasional*); *Akun Saya* (PG-065); *Bahasa tampilan: Indonesia | English*, which changes the account's own display language at once and keeps it in `users.locale` ([LOCALIZATION](LOCALIZATION.md); `DIR-077` CA-08); *Keluar*, which asks first only when input is unsent ([MSG-35](ADMIN_FLOW/s14-15-messages-scenarios.md#14-message-catalogue); GR-09 as corrected). On the phone, *Lainnya* › *Akun* holds the same three (R1 a)).
- **System page** — *Halaman Sistem* (PG-005): not found, forbidden and error, the not-found answer identical for an unknown, an out-of-scope and an Owner-only address (AZ-11; IP-21).
- **Outside the shell** — sign-in (PG-001), its second-factor step (PG-002), the credential-link page (PG-003) and the enrolment-only session's page (PG-004); their journeys are [AUTH_JOURNEYS](stage2/AUTH_JOURNEYS.md)'s.

### 5.5 The project journey as the hub, and what has no entry

The project journey (PG-011) is the operational hub UXN-02 asks for: its header and primary action, *Tahap proyek*, *Sekarang · Berikutnya · Hambatan*, "Yang tersisa sebelum selesai", "Kesiapan dokumen", "Posisi keuangan" and "Rincian proyek" carry the brief's parts — overview, items, quotation, procurement, fulfilment, delivery, documents, billing, payments, administrative requirements and history (OB §11) —, and every cross-project list leads back to it. The journey's own pages — the quotation, the completion page, the delivery record, the direct shipment, the service handover and the client return — are reached from the project and from work rows, not from *Menu lengkap*.

Correction entry points are not an area: each correction starts from the record it corrects ([ADMIN_FLOW §11](ADMIN_FLOW/s11-13-corrections-numbering-import.md#11-corrections-and-reversals)); *Kasus Koreksi* lists the cases those corrections open (§14.4). *Impor Data Awal* sits under *Data Master* so that an Admin holding `import.prepare` reaches it; its commit is the Owner's (PJ-20; OD-13). CAP-16's local backups are operated by the human operator and have no page; CAP-14 is this design as a whole; SIPLAH is a project channel with its own fields on the project, never an area (CAP-12; V1_SCOPE OUT OF SCOPE).

## 6. Application shell and global navigation

The shell is the frame every authenticated page shares; its visual form is PT-01 and PT-02 of [DESIGN_SYSTEM](DESIGN_SYSTEM.md#9-patterns), on the Golden Frontend Reference's anatomy (OQ-10). It never shows a business figure, a company's data, a count or a company context.

### 6.1 Desktop and tablet

| Part | Content | Rules |
| --- | --- | --- |
| Navigation | the persona's primary entries (§5.2), then the collapsed *Menu lengkap* (§5.3) | the reference supplies only the treatment of the navigation components ([GD-03](../00-governance/DECISION_LOG.md#dir-059-dir-060-obs-022-and-tech-030--p7-ux-re-baseline-checkpoint-c1-closure-golden-frontend-reference-adoption-authorization-baseline-and-reconciliation)); the current entry carries `aria-current="page"`; Ctrl+B collapses and expands the navigation ([KB-03](DESIGN_SYSTEM.md#131-keyboard)) |
| Top bar | the field **"Cari atau pindai kode"** with the hint "Ctrl K" (KB-01; [D-IA-04](#4-design-decisions) KEEP); **"Buat"** with its items (§10); the **account menu** (§5.4) | no company switch, company name, count or notification bell (§6.1; D-IA-02 KEEP); scanner input goes to the field when it has focus (§10) |
| Notice strip | absent unless a notice is due — the absolute session limit ([MSG-34](ADMIN_FLOW/s14-15-messages-scenarios.md#14-message-catalogue)) and a lost connection | the idle warning is a dialog, not a strip (D-IA-05 KEEP; [AU-07](../05-security/SECURITY.md#2-authentication-sessions-and-credentials); H7-05) |
| Content | the page header and the page; a list's company filter in its header (§7) | the page states its purpose through its title and its first actionable element (R1 g); OC-06) |

The *Pintasan* help stays reachable by its key, Ctrl+/ (KB-02); R1's top bar shows no "Pintasan" button, so whether a visible entry is added is a presentation choice settled in [DESIGN_SYSTEM_MAPPING](stage2/DESIGN_SYSTEM_MAPPING.md).

### 6.2 Phone

| Part | Content | Rules |
| --- | --- | --- |
| Top bar | a back arrow below a module's first page; the page title; the account button | the title is the screen's name, never a business number or name (H7-10) |
| Bottom bar | the persona's four entries (§5.2), each item at least 60 × 60 px (DESIGN_SYSTEM §5.3) | at most five entries (D-IA-03 ADAPT, R1's delta); an entry the account cannot open is absent, never disabled |
| *Lainnya* drawer | *Kerja* — for the Owner *Pekerjaan* and *Tinjauan* —; *Menu lengkap*; *Akun* with *Bahasa tampilan* and *Keluar* | grouped and ordered as on the desktop (R1 a)) |
| Focused tasks | a scan step, a full-height form sheet, a confirmation and the re-authentication dialog hide the bottom bar, the task's own action bar taking its place | leaving a task with unsent input asks first (ADMIN_FLOW §6; MSG-35) |

Starting new on the phone happens where the work is — "Buat Proyek" on the project list, "Mulai" on a work row — rather than through "Buat" (R1 a)).

### 6.3 Entries and capabilities

- An entry appears only when something behind it is open to the account's capabilities and companies; it is a hint, and the server decides every request ([AZ-01](../05-security/PERMISSIONS_MATRIX.md#2-authorization-model); UXN-09).
- Owner-only entries — *Tinjauan*, *Aktivitas & Keamanan*, *Pengaturan*, *Laporan Gabungan* and the *Gabungan* view — never appear for an Admin, and their addresses answer the not-found page (UXN-18; AZ-11).
- No entry carries a badge or a count (D-IA-06 KEEP; CS-08; [PF-15](../06-api-performance/PERFORMANCE.md#4-pagination)); nothing in the shell changes with a list's company filter.
- The prototype's marker "PROTOTIPE · data contoh" is not part of the IA; [SIMULATION_REGISTER](stage2/SIMULATION_REGISTER.md) places it.

## 7. Company context model

The company context is a **view**, never an authorization (WF-ACC-01; CS-03). It follows the rules below; the visual form of the filter, labels and marker is PT-04 of [DESIGN_SYSTEM](DESIGN_SYSTEM.md#9-patterns).

| Rule | Presentation | Grounds |
| --- | --- | --- |
| Scope is the companies of the account's active grants; the Owner has every company, active or deactivated | Nothing to choose: the account sees what its scope and capabilities allow, and a list or dashboard covers all of it by default | CS-01; CS-02; RG-03 |
| A working filter narrows what is shown | The control **"Tampilkan: Semua perusahaan"** sits in the header of every list and of Beranda for an account with two or more companies; it opens a checklist of the companies in scope, by name and code. It narrows the per-company branches of the list (PF-41), is intersected with scope by the server (DP-02) and lives in the page address as a whitelisted filter key, so reload and Back keep it; it is not stored anywhere else | PF-41; DP-02; WS-03 |
| One company in scope | No filter. Lists show the company's name once in the page header; rows carry no company label | CS-02 |
| Rows of several companies | Every row carries its company label — the company's short code beside the record number on desktop, before the first line on a phone row — and rows are ordered by their own values (PF-41) | CS-08; UXN-07 |
| No totals across companies for an Admin | A figure that counts or sums S3 values of several companies does not exist on an Admin's screens; a total is given per company — one subtotal row per company, never a grand total. The Owner's consolidated figures are labelled *Gabungan* and appear only on the Owner's consolidated views (PG-006 period figures, PG-052) | CS-08; PF-25; CALC-12 |
| A command names its company | The form of a new company-owned root has the required field **"Perusahaan"**, never prefilled from the working filter for an account with several companies; the form of an existing record shows that record's company as a fixed label in its header. Pickers in a form offer only records of that company, except the intentional links of PERMISSIONS_MATRIX §8 | CS-03; CS-04; AZ-05 |
| The filter never authorizes | The server checks the target record's company on every command; changing the filter changes no permission and no form | WF-ACC-01; AZ-01 |
| A company outside scope | Never named. Where a physical row relates to it, the **relation marker** "perusahaan lain" appears in its place — plain muted text, never a link, colour, logo, code or count; where a record of it would be reached by address or reference, the answer is the not-found page | PJ-04; H7-02; AZ-11 |

**Where the relation marker appears.** These are the only places; anywhere else a record of a company outside scope simply does not exist for the viewer.

| Place | What the viewer sees | Rule |
| --- | --- | --- |
| Pooled stock and lot lists (PG-018, PG-019) and the picking aid of a dispatch (PG-024) | the lot's opaque code, product, remaining quantity, rack and condition, and "perusahaan lain" | PJ-01; PJ-02; PJ-23 |
| Reservations of a project of another company (PG-025, PG-019, the reservation-cut block of [ADMIN_FLOW IP-20](ADMIN_FLOW/s06-09-session-forms-lists.md#ip-20--choosing-a-reservation-cut)) | the quantity and "proyek perusahaan lain" | PJ-04 |
| Movement history (PG-020) | nothing: movements of a company outside scope are not listed, and their quantities reach the viewer only through the pooled figures | PJ-03; D-PM-11 |
| Allocated cost on the consuming project (PG-011 *Pengadaan*, cost views) | the cost line in full, marked "dari stok perusahaan lain", the source company and its purchase details masked | PJ-06 |
| A lot's draw for another company (cost views of the source company) | "dialokasikan ke perusahaan lain", the bearer masked | PJ-05 |
| A correction case's causal project or source lot of another company (PG-040) | "proyek perusahaan lain" or the lot code | XL-3; PJ-16 |
| A linked project of another company (PG-011) | "proyek perusahaan lain" with the viewer's own link reason | XL-4 |
| A pending unexplained-loss case (PG-018, PG-019) | the physical quantities and state only; never the number or identity of the other candidate companies | PJ-07 |
| A cross-company event on a record's timeline | the viewer's own side; the other side's identifiers replaced by the marker | PJ-11 |
| An inter-company transfer citation (PG-049) | "perusahaan lain" unless both companies are in scope | XL-5 |
| A routed refusal ([ADMIN_FLOW MSG-14](ADMIN_FLOW/s14-15-messages-scenarios.md#14-message-catalogue)) | "catatan perusahaan lain di luar akses Anda" | SQ-23; PJ-04 |

**The re-baselined structure adds** ([IA_PAGE_INVENTORY](stage2/IA_PAGE_INVENTORY.md) §4), each a reading of the rules above, no new rule:

- *Pekerjaan* carries the company filter "Tampilkan: Semua perusahaan" like a list, for an account with two or more companies; its counts are per company (CS-08).
- The Owner's *Beranda* has the view switch *Gabungan* · ARJ · BTN · CKP, which filters the signal groups and the period figures (§9); consolidated figures exist only on the Owner's consolidated views (`reports.consolidated`; CALC-12; PF-25).
- A child record's form shows its root's company as a fixed label — the purchase started from a project's shortage shows "Perusahaan PT Bentara Niaga (BTN)" (CS-03; CS-04; L-05).
- The pooled projection: stock pages show S1 physical quantities pool-wide to `stock.view`, without costs, source references or another company's identity (PJ-01–PJ-04; PJ-22).

## 8. Screen inventory

The **page inventory**: sixty-five pages, PG-001–PG-065 — four outside the shell (PG-001–PG-004) and sixty-one inside it —, carried from [IA_PAGE_INVENTORY](stage2/IA_PAGE_INVENTORY.md) §5 with their stable identifiers (D-IA-18), grouped as the approved revision grouped its screens. A page is an addressable view (§11); a child line, ledger row or guard is reached through its root, and a dialog, sheet or confirmation is a state of its page. The Indonesian names come from the approved revision's §8 and R1, the English names are proposed (GAP-037); each page's route, its commands and their capabilities are contracts in [ROUTE_CONTRACTS](../03-workflows/ROUTE_CONTRACTS.md) — the page's view is ROUTE- with the page's number —, its interactions in [INTERACTION_CONTRACTS](../03-workflows/INTERACTION_CONTRACTS.md) and its navigation contract in [NAVIGATION_CONTRACTS](NAVIGATION_CONTRACTS.md) — NAV- with the page's number. Every SCR maps to at least one page (§8.7). In each group, the first table gives identity, route and purpose, the second access, company, entries and behaviour: the capability that opens the page and its parts (PERMISSIONS_MATRIX §4), the field families of PERMISSIONS_MATRIX §7 — a family the viewer lacks is absent, never masked or shown as zero (§7.1) —, the context tag of WORKFLOWS §1 and the dialogs, sheets and confirmations the page hosts under ADMIN_FLOW's interaction patterns.

### 8.1 System, account and Beranda

| PG | Name — Indonesian · English (proposed) | Route | Kind | Purpose | SCR · R1 element | Contracts |
| --- | --- | --- | --- | --- | --- | --- |
| PG-001 | *Masuk* · Sign in | `/masuk` | system | sign in with e-mail and password, or with Google; the second factor follows ([WF-ACC-01](../03-workflows/WORKFLOWS/s05-01-02-access-project.md#wf-acc-01--session-and-company-context-both); AU-04; AU-22) | SCR-01 · R1 k), RS-1 | NAV-001 · ROUTE-001 |
| PG-002 | *Verifikasi Dua Langkah* · Two-step verification | `/masuk/verifikasi` | system | the second factor: a TOTP code, or a recovery code after a password; a code only after Google (AU-16; AU-19; AU-22) | SCR-01 · R1 k), "Masukkan kode verifikasi" | NAV-002 · ROUTE-002 |
| PG-003 | *Atur Kata Sandi* · Set password | `/atur-kata-sandi`, the token in the fragment (AU-12) | system | set a password from a credential link (AU-03; AU-12) | SCR-02 · R1's map; §7.1 | NAV-003 · ROUTE-003 |
| PG-004 | *Aktifkan Verifikasi Dua Langkah* · Turn on two-step verification | `/verifikasi-dua-langkah` | task step | the enrolment-only session's single path for a high-risk account without TOTP: enrol, confirm a code, see the recovery codes once (AU-16; AU-18; AU-19; H7-15) | SCR-02's journey, and SCR-01's path for such an account (AU-04) · §7.1 | NAV-004 · ROUTE-004 |
| PG-005 | *Halaman Sistem* · System page | the address requested | system | not found, forbidden, error (AZ-11; [IP-21](ADMIN_FLOW/s05-command-model.md#ip-21--denial-and-not-found); WS-10) | SCR-04 · unchanged | NAV-005 · ROUTE-005 |
| PG-006 | *Beranda* · Home | `/beranda` | home | the Owner's decisions and exceptions first, the four KPIs in the same view, the projects needing attention, the Admin queues as one folded line (OC-05) | SCR-05 · R1 c), RS-3 | NAV-006 · ROUTE-006 |
| PG-007 | *Pekerjaan* · Work | `/pekerjaan` | work queue | work grouped by what has to happen next — goods, documents and cases, invoices and payments, project completion —, rows of QS signals only, the dispatch-ready lines among them (OC-01; PJ-13; `DIR-077` CA-07) | SCR-06, and SCR-05's operational groups for the Owner · R1 b), f), RS-4, RS-5 | NAV-007 · ROUTE-007 |
| PG-008 | *Tinjauan* · Review | `/tinjauan` | work queue | the Owner review queue — open conditions and activity to review (PERMISSIONS_MATRIX §10; QS-14) | SCR-07 · R1 a), the Owner's entry | NAV-008 · ROUTE-008 |
| PG-009 | *Cari* · Search | `/cari`, the term never in the address (§11) | system | results by type, an exact code first ([PF-19](../06-api-performance/PERFORMANCE.md#5-search); IP-17) | SCR-08 · R1 a), the top-bar field and the phone's *Cari* | NAV-009 · ROUTE-009 |
| PG-065 | *Akun Saya* · My account | `/akun` | account | the account's own profile, role, capabilities and companies, its own events, its password, TOTP, recovery codes and Google link, and its display language (AU-13; `DIR-077` CA-08) | SCR-03 · the account menu; *Lainnya* › *Akun*; §7.2 | NAV-065 · ROUTE-065 |

| PG | Personas · capability | Field families | Company | Entry points | Tag · phone | Hosts — dialogs, sheets, confirmations |
| --- | --- | --- | --- | --- | --- | --- |
| PG-001 | unauthenticated | — | — | the address; after logout or expiry | `[both]` · one narrow column | the switch "ID \| EN" (R1 k)); AU-04's generic answer (MSG-19) and the throttled answer (AU-05) |
| PG-002 | an account past its password or Google step (AU-16; AU-22) | — | — | PG-001 | `[both]` · one narrow column | "Gunakan kode pemulihan" after a password; "Tidak bisa memakai aplikasi?" after Google (R1 k)); the generic and cooldown answers (AU-05) |
| PG-003 | the link's holder (AU-12) | — | — | the credential link only | `[both]` | the password rules (AU-03); the answers for a used, expired or revoked link ([AUTH_JOURNEYS](stage2/AUTH_JOURNEYS.md)) |
| PG-004 | a high-risk account in its enrolment-only session (AU-18) | — | — | PG-003; PG-001 for such an account | `[both]` | the QR code and text key, the confirming code, the recovery codes shown once (AU-16; AU-19); the password re-confirmation after 15 minutes (H7-15) |
| PG-005 | every account | — | — | any unresolvable, out-of-scope or forbidden address | `[both]` | MSG-09 with the reference code and the way back (IP-21) |
| PG-006 | Laras (`review.view`, OWNER_ONLY; *Gabungan* `reports.consolidated`) | each signal's read class (RD-02–RD-09); the KPIs' CALC families (PJ-22) | *Gabungan* or one company, by the view switch | primary — the Owner's landing page | `[both]` · two decision rows, then "Lihat semua keputusan"; the four KPIs in a two-by-two grid (R1 c)) | five deferred groups, each with its loading state and error — the decision group with the Owner-only overdue projects (QS-23; `DIR-077` CA-10) ([PT-19](DESIGN_SYSTEM.md#94-actions-overlays-and-feedback); QB-10; PF-34); the chart "Umur piutang" with its table in the one-company view (R1 i)); the KPI *Piutang aktif* and the chart within QB-10's period figures, one grouped statement over `invoice_balances` (`DIR-079` CB-03) |
| PG-007 | all three; each group by its read class (PJ-13), *Tagihan dan pembayaran* only with `finance.view` | S1, S2 and S3 as each signal's read class | a view filter over the companies in scope; counts per company (CS-08) | primary — the Admin's landing page, the Owner's entry | `[both]` · groups collapsible; Bayu's "Berikutnya" card first (R1 f)) | the deferred signal groups (QB-10); the dispatch-ready rows (QS-24) and their "Lihat Semua" to *Barang Keluar*'s worklist (`DIR-077` CA-07; D-IA-20) |
| PG-008 | Laras (`review.view`) | each item's projection (RD-09; PJ-11) | branches and the items without a company | primary (Owner); *Beranda*'s QS-14 row | `[desk]` · rows with the window switch | four source tabs and the window filter (§9.2) |
| PG-009 | all three; company records by their view capability (PJ-10) | S0 masters; company records by family | branches for company records | the top bar, Ctrl K, the phone's *Cari* | `[both]` · full-screen search | the type-ahead and the 200-candidate notice (IP-17) |
| PG-065 | every account, itself (SELF; PJ-17) | own | global | the account menu; *Lainnya* › *Akun* | `[both]` | the password change (AU-13); *Bahasa tampilan* (`DIR-077` CA-08); TOTP, recovery codes and the Google link behind the step-up (AU-15) — [AUTH_JOURNEYS](stage2/AUTH_JOURNEYS.md) |

### 8.2 Projects and quotations

| PG | Name — Indonesian · English (proposed) | Route | Kind | Purpose | SCR · R1 element | Contracts |
| --- | --- | --- | --- | --- | --- | --- |
| PG-010 | *Proyek* · Projects | `/proyek` | list | find and open projects; each row shows its company code, number, title, client, state and deadline, and its journey stage, blocker marker and next action from QB-01's journey-summary group, filled when the group arrives — for a viewer without `finance.view` derived without QS-15's money items, which the group then omits (§13.7; PJ-13; PJ-22) —; no stage filter unless P8 shows a bounded filter that meets PF-42 (`DIR-077` CA-06; D-IA-19) | SCR-09 · R1 d), RS-6, with QB-01 extended (`DIR-077` CA-06) | NAV-010 · ROUTE-010 |
| PG-011 | *Perjalanan Proyek* · Project journey | `/proyek/{id}` | record workspace | the project as a guided journey — header and primary action, *Tahap proyek*, *Sekarang · Berikutnya · Hambatan*, "Yang tersisa sebelum selesai", "Kesiapan dokumen", "Posisi keuangan", "Rincian proyek" (OC-03; PL-1–PL-3; C1A-1; C1A-2) | SCR-10, with SCR-14's unmet conditions · R1 e), RS-7 | NAV-011 · ROUTE-011 |
| PG-012 | *Penyelesaian Proyek* · Project completion | `/proyek/{id}/penyelesaian` | task step | all ten conditions, "Selesaikan Proyek" and the Owner's "Selesaikan Paksa" (WF-PRJ-03; AX-25; AX-26) | SCR-14 · the journey's *Penyelesaian* stage | NAV-012 · ROUTE-012 |
| PG-013 | *Formulir Proyek* · Project form | `/proyek/baru`; `/proyek/{id}/ubah` | form or editor | create and amend a project with its demand lines (WF-PRJ-01) | SCR-11 · "Buat" › *Proyek*, "Buat Proyek", "Ubah proyek" in *Tindakan Lain* | NAV-013 · ROUTE-013 |
| PG-014 | *Penawaran* · Quotation | `/proyek/{id}/penawaran` | form or editor | edit, issue and revise the quotation in the document composer — on the desktop its form on the left and the live preview of the *Penawaran* on the right (DESIGN_SYSTEM D-DS-23; `DIR-079` CD-04) —; record the client's answer (WF-QUO-01; WF-PRJ-02) | SCR-13 · the journey's *Kesepakatan* stage; *Rincian proyek* | NAV-014 · ROUTE-014 |
| PG-037 | *Daftar Penawaran* · Quotations | `/dokumen/penawaran` | list | quotation revisions across projects (WF-QUO-01) | SCR-12 · *Dokumen* › *Penawaran* | NAV-037 · ROUTE-037 |

| PG | Personas · capability | Field families | Company | Entry points | Tag · phone | Hosts — dialogs, sheets, confirmations |
| --- | --- | --- | --- | --- | --- | --- |
| PG-010 | all three (`projects.view`); values with `finance.view` | S2; S3 values | a view filter | primary | `[both]` · rows and a filter sheet | the filter sheet (PT-07); the journey-summary group's loading state and error (PT-19; `DIR-077` CA-06); "Buat Proyek" (`projects.manage`); "Muat Lagi" (IP-18) |
| PG-011 | all three (`projects.view`); *Posisi keuangan* `finance.view`; *Biaya dan laba* `cost.view` and `profit.view`; *Pengadaan* `cost.view`; availability `stock.view` | S2; S3 by family; S4 links by family | one company's record | primary, from *Proyek*, *Pekerjaan*, *Beranda* and search | `[both]` · header, primary action, the now card and the track first (R1 e)) | three deferred groups — predicates, documents, *Riwayat* (QB-02); *Tindakan Lain*; IP-08's confirmation for the commands it starts |
| PG-012 | all three read (`projects.view`); "Selesaikan Proyek" `projects.complete` (Laras, Dimas); "Selesaikan Paksa" Laras (`projects.force_complete`) | S2; snapshot money keys by family (PJ-12) | record | the journey's *Penyelesaian* stage; *Pekerjaan* › *Penyelesaian proyek*; *Beranda*'s QS-15 row | `[desk]` · stacked condition rows | IP-08's confirmation; Force Complete bound to its blocker snapshot, with its reason (AX-26; IP-09) |
| PG-013 | Laras, Dimas (`projects.manage`; prices `finance.view`) | S2; S3 prices | new root: the chosen company, "Perusahaan" (CS-03); amend: the record's | "Buat" › *Proyek*; "Buat Proyek"; *Tindakan Lain* | `[both]` · one column, lines as rows | the line editor (IP-13; PT-10); "Tanggal transaksi" (IP-07); discarding a draft |
| PG-014 | Laras, Dimas (`quotation.manage` with `finance.view`; `projects.confirm`) | S2; S3 values | record | the journey's *Kesepakatan* stage; *Rincian proyek* | `[desk]` · read and "Catat Jawaban Klien" | the preview "DRAF — belum terbit" beside the form (DC-2; D-DS-23); "Terbitkan Penawaran" with IP-08 (AX-15); "Catat Jawaban Klien" (AX-13); "Buat Revisi" |
| PG-037 | all three (`projects.view`); values Laras, Dimas (`finance.view`) | S2; S3 values | branches | *Dokumen* › *Penawaran* | `[desk]` | "Muat Lagi" (IP-18) |

### 8.3 Purchasing and warehouse

| PG | Name — Indonesian · English (proposed) | Route | Kind | Purpose | SCR · R1 element | Contracts |
| --- | --- | --- | --- | --- | --- | --- |
| PG-015 | *Pembelian* · Purchases | `/pembelian` | list | purchases (WF-PUR-01; WF-PUR-02) | SCR-15 · *Menu lengkap* › *Pembelian* | NAV-015 · ROUTE-015 |
| PG-016 | *Detail Pembelian* · Purchase | `/pembelian/{id}` | record workspace | lines with received and remaining, charges and their allocation, receipts, disbursements, documents (WF-PUR-01–WF-PUR-03) | SCR-16 · the list, a work row, *Rincian proyek* › *Pengadaan* | NAV-016 · ROUTE-016 |
| PG-017 | *Buat Pembelian* · New purchase | `/pembelian/baru`, a project's shortage prefilled by its public id | form or editor | a purchase with lines and charges, a line linked to a project's demand (WF-PUR-01; WF-PUR-02; AX-29) | SCR-17 · "Buat" › *Pembelian*; the QS-02 work row; the journey's *Gudang* stage, RS-9 | NAV-017 · ROUTE-017 |
| PG-018 | *Stok* · Stock | `/gudang/stok` | list | pooled availability per product ([CALC-01](../02-domain/BUSINESS_RULES.md#5-calculation-semantics); PJ-01) | SCR-18 · *Gudang* › *Stok*, RS-2 | NAV-018 · ROUTE-018 |
| PG-019 | *Detail Stok Produk* · Product stock | `/gudang/stok/{id}` | record workspace | racks, lots, serials, reservations and recent movements of one product (PJ-01–PJ-04) | SCR-19 · from *Stok*, search and scan | NAV-019 · ROUTE-019 |
| PG-020 | *Riwayat Stok* · Stock history | `/gudang/riwayat-stok` | list | movement history (PJ-03) | SCR-20 · *Gudang* › *Lainnya*, dense | NAV-020 · ROUTE-020 |
| PG-021 | *Barang Masuk* · Goods receiving | `/gudang/barang-masuk` | work queue | the receiving worklist — open purchase remainders (QS-05; PJ-18) | SCR-21's worklist · *Gudang* › *Barang Masuk* | NAV-021 · ROUTE-021 |
| PG-022 | *Terima Barang* · Receive goods | `/gudang/barang-masuk/{id}`, the purchase | task step | receive one purchase line by line, scan-led — the three outcomes, the rack, the same-action reservation offer, the summary, [DOC-12](../01-product/V1_SCOPE.md#selectable-document-catalog) on request (WF-INV-01; AX-04; IP-19) | SCR-21's task · R1 f), RS-10 — work rows, "Mulai Terima Barang", "Buat" › *Barang Masuk* | NAV-022 · ROUTE-022 |
| PG-023 | *Barang Keluar* · Goods dispatch | `/gudang/barang-keluar` | work queue | the dispatch worklist of remaining reservations (CALC-02) | SCR-22's worklist · *Gudang* › *Barang Keluar*; *Pekerjaan*'s link (QUERY_BUDGET_CHECKS §3.4) | NAV-023 · ROUTE-023 |
| PG-024 | *Keluarkan Barang* · Dispatch goods | `/gudang/barang-keluar/{id}`, the project | task step | dispatch a project's reserved lines, scan-led, lots and serials confirmed; the Surat Jalan on request (WF-INV-03; WF-INV-07; AX-03) | SCR-22's task · the work rows "Catat Barang Keluar" and "Keluarkan barang" | NAV-024 · ROUTE-024 |
| PG-025 | *Reservasi* · Reservations | `/gudang/reservasi` | list | reserve, reduce and release (WF-INV-02; AX-01; AX-02) | SCR-23 · *Gudang* › *Reservasi*; *Rincian proyek* › *Item dan pemenuhan* | NAV-025 · ROUTE-025 |
| PG-026 | *Stok Opname* · Stock count | `/gudang/stok-opname` | list | counts, their lines and their application (WF-INV-05; AX-06) | SCR-24 · *Gudang* › *Stok Opname* | NAV-026 · ROUTE-026 |
| PG-027 | *Penyesuaian & Kondisi* · Adjustments and condition | `/gudang/penyesuaian` | form or editor | condition change, disposal, shrinkage and loss (WF-INV-04; WF-INV-06; AX-06; AX-30) | SCR-25 · *Gudang* › *Lainnya* | NAV-027 · ROUTE-027 |
| PG-028 | *Retur ke Pemasok* · Supplier return | `/gudang/retur-pemasok` | record workspace | a purchase return and its case (WF-PUR-03; AX-11) | SCR-26 · *Gudang* › *Lainnya*; the purchase page | NAV-028 · ROUTE-028 |
| PG-029 | *Selisih & Temuan* · Discrepancies and findings | `/gudang/selisih-temuan` | list | the Owner's attribution of pending losses and count surpluses; an ADM+ evidence resolution (SF-UNATTRIBUTED; AX-07; AX-37) | SCR-27 · *Beranda*'s decision rows (QS-22, QS-18); *Gudang* › *Lainnya* | NAV-029 · ROUTE-029 |

| PG | Personas · capability | Field families | Company | Entry points | Tag · phone | Hosts — dialogs, sheets, confirmations |
| --- | --- | --- | --- | --- | --- | --- |
| PG-015 | Laras, Dimas (`cost.view`) | S2; S3 cost | a view filter | *Menu lengkap* › *Pembelian* | `[desk]` | "Buat Pembelian" (`purchase.manage`); "Muat Lagi" (IP-18) |
| PG-016 | Laras, Dimas (`cost.view`; disbursements `finance.view`) | S2; S3 cost and finance | record | the list; work rows; *Rincian proyek* › *Pengadaan* | `[desk]` | "Terima Barang" (→ PG-022); "Terbitkan PO"; ADM+ corrections with their reason (IP-09), absent without the capability (H7-03) |
| PG-017 | Laras, Dimas (`purchase.manage` with `cost.view`) | S2; S3 cost | new root: the project's company, shown and not chosen, or the chosen company (CS-03; L-05) | "Buat" › *Pembelian*; the QS-02 row, prefilled; the journey's *Gudang* stage | `[desk]` · readable, small edits on the phone | the line editor and charges (IP-13; PT-10); "Rincian lain" (R1 j)); the summary an estimate (UXI-14); save (NX-04) |
| PG-018 | all three (`stock.view`) | S1; own companies' stock value `cost.view` | pool | *Gudang* › *Stok* | `[both]` · scan or search first | the filter sheet; the pending line (MSG-41) |
| PG-019 | all three (`stock.view`) | S1; lot cost `cost.view` (PJ-02) | pool, with the relation marker (PJ-04) | *Stok*; search; scan | `[both]` | "Pindah Lokasi", "Ubah Kondisi", "Catat Penyesuaian Stok", "Mulai Hitungan" by capability; the reservation cut (IP-20) |
| PG-020 | all three (`stock.view`; project `projects.view`; purchase `cost.view`) | S1; S2; S3 cost | branches and the pool branch | *Gudang* › *Lainnya* | `[both]` · dense | "Balikkan" (`stock.reverse`) with its reason (IP-09) |
| PG-021 | Laras, Bayu (`stock.receive`, PJ-18) | the receiving projection — no price, tax, charge or cost (PJ-18) | branches — purchases of companies in scope | *Gudang* › *Barang Masuk*; work rows | `[floor]` | "Muat Lagi" (IP-18) |
| PG-022 | Laras, Bayu (`stock.receive`; the offer `stock.reserve`, absent in S2-R06's variant) | S1; the receiving projection | the purchase's company | work rows; "Mulai Terima Barang"; "Buat" › *Barang Masuk* | `[floor]` · the line checklist, the scan field fixed at the bottom (D-UX-11) | the line sheet — three outcomes, rack, the reservation offer (AX-04; D-UX-12); IP-08's confirmation per line; "Cari Produk"; one answer per line (IP-19); IP-05's fungible-duplicate warning; the summary; "Terbitkan Nota Terima Barang" per receiving event, with IP-08 |
| PG-023 | Laras, Bayu (`stock.dispatch`) | S1; S2 | branches — projects in scope | *Gudang* › *Barang Keluar*; *Pekerjaan*'s QS-24 "Lihat Semua" (`DIR-077`) | `[floor]` | "Muat Lagi" (IP-18) |
| PG-024 | Laras, Bayu (`stock.dispatch`); another company's lot only with `stock.allocate_intercompany` (XL-1), held by neither Admin | S1; S2; the relation marker | the project's company; lots: pool | *Pekerjaan*'s QS-24 rows (`DIR-077` CA-07); work rows; the worklist | `[floor]` · scan-led | the oldest-first aid (PJ-01); IP-08's confirmation; a routed refusal (IP-06; MSG-14); IP-05's fungible-duplicate warning; "Terbitkan Surat Jalan" |
| PG-025 | all three (`stock.reserve`, with `stock.view` and `projects.view`) | S1; S2 | branches; other projects as the marker (PJ-04) | *Gudang* › *Reservasi*; *Rincian proyek* | `[desk]` | "Reservasi", "Kurangi Reservasi", "Lepas" (AX-01; AX-02) |
| PG-026 | Laras, Bayu (`stock.count`; applying `stock.count_apply`) | S1 | pool | *Gudang* › *Stok Opname*; QS-18 rows | `[floor]` | "Terapkan Selisih" with its reason (AX-06; IP-09); the reservation cut (IP-20); the count sheet's print |
| PG-027 | Laras, Bayu (`stock.condition`; `stock.adjust`) | S1; the bearer company | pool | *Gudang* › *Lainnya*; the product page | `[floor]` | the reason step (IP-09); the reservation cut (IP-20) |
| PG-028 | Laras (`purchase_return.record`, with `stock.view` and `cost.view`) | S1; S2; S3 cost | record | *Gudang* › *Lainnya*; the purchase page | `[floor]` | opening the case; stock out (AX-11); "Tutup Kasus" (`cases.close`); the reason step (IP-09) |
| PG-029 | Laras (`stock.resolve_unattributed`, `stock.attribute_surplus`); an ADM+ holder of `stock.resolve_unattributed_evidence` under PJ-07 | S1; the case snapshot (RD-09) | pool | *Beranda*'s decision rows (QS-22, QS-18); *Gudang* › *Lainnya* | `[desk]` | "Tetapkan Perusahaan", "Tetapkan dengan Bukti", "Tetapkan Surplus", "Balikkan Penetapan", each with its reason (IP-09) |

### 8.4 Fulfilment, cases and documents

| PG | Name — Indonesian · English (proposed) | Route | Kind | Purpose | SCR · R1 element | Contracts |
| --- | --- | --- | --- | --- | --- | --- |
| PG-030 | *Pengiriman* · Deliveries | `/gudang/pengiriman` | list | shipments not yet delivered, and delivery records (WF-FUL-02) | SCR-28 · QS-06 rows; *Rincian proyek* › *Pengiriman*; *Gudang* › *Lainnya* › *Pengiriman* | NAV-030 · ROUTE-030 |
| PG-031 | *Catat Pengiriman* · Record delivery | `/gudang/pengiriman/{id}`, the shipment | form or editor | the delivery record and its discrepancy (WF-FUL-02; AX-09) | SCR-29 · the journey's primary action; the work row | NAV-031 · ROUTE-031 |
| PG-032 | *Kirim Langsung* · Direct shipment | `/proyek/{id}/kirim-langsung` | form or editor | drop-ship confirmation (WF-FUL-03; AX-08) | SCR-30 · the journey's *Kirim langsung* stage | NAV-032 · ROUTE-032 |
| PG-033 | *Serah Terima Jasa* · Service handover | `/proyek/{id}/serah-terima` | form or editor | the service handover (WF-FUL-04) with its BAST draft in the document composer — on the desktop the handover's form on the left and the live preview of the BAST on the right (DESIGN_SYSTEM D-DS-23; `DIR-079` CD-04) | SCR-31 · the journey's *Jasa* stage and its primary action | NAV-033 · ROUTE-033 |
| PG-034 | *Retur dari Klien* · Client return | `/proyek/{id}/retur` | record workspace | a sales return and its case (WF-FUL-05; AX-10) | SCR-32 · *Rincian proyek* › *Pengiriman*; *Tindakan Lain*; §7.3 | NAV-034 · ROUTE-034 |
| PG-035 | *Dokumen* · Documents | `/dokumen` | list | generated documents (WF-DOC-01) | SCR-34 · *Menu lengkap* › *Dokumen*; the journey's "Kesiapan dokumen" › "Semua dokumen" (RS-8) | NAV-035 · ROUTE-035 |
| PG-036 | *Detail Dokumen* · Document | `/dokumen/{id}` | form or editor | a document's draft composer — carried and entered data on the left and, on the desktop, the live preview marked "DRAF — belum terbit" on the right (DESIGN_SYSTEM D-DS-23; `DIR-079` CD-04) —, its versions, rendition state, issue, revision and void (WF-DOC-01; WF-FIN-08; DC-1–DC-12) | SCR-35 · document readiness and the document list; VC1's composer | NAV-036 · ROUTE-036 |
| PG-038 | *Bukti* · Evidence | `/dokumen/bukti` | list | evidence — upload, register, versions and links (WF-DOC-02; SF-EVIDENCE) | SCR-36 · *Dokumen* › *Bukti*; "Buat" › *Unggah Dokumen* | NAV-038 · ROUTE-038 |
| PG-039 | *Dokumen Administrasi* · Administrative documents | `/dokumen/administrasi` | list | the cross-project view of unmet requirement items; each project's checklist sits in its journey's document readiness (WF-ADM-01) | SCR-37 · *Dokumen* › *Dokumen Administrasi*; RS-8 | NAV-039 · ROUTE-039 |
| PG-040 | *Kasus Koreksi* · Correction cases | `/dokumen/kasus` | list | correction cases with their residuals (SF-CASE) | SCR-33 · *Pekerjaan* › *Dokumen dan kasus* (QS-07); the journey's *Retur dan koreksi* condition; §7.4 | NAV-040 · ROUTE-040 |

| PG | Personas · capability | Field families | Company | Entry points | Tag · phone | Hosts — dialogs, sheets, confirmations |
| --- | --- | --- | --- | --- | --- | --- |
| PG-030 | all three (`projects.view`; recording `delivery.record`) | S2 | branches | QS-06 rows; *Rincian proyek* › *Pengiriman*; *Gudang* › *Lainnya* › *Pengiriman* | `[both]` | "Catat Pengiriman" (→ PG-031) |
| PG-031 | Laras, Bayu (`delivery.record`; closure `delivery.close`; correction `fulfillment.correct`) | S2; S4 DELIVERY_PROOF (PJ-08) | record | the journey's primary action; the work row | `[both]` · one column with photo proof | the evidence upload (IP-16); IP-05's fungible-duplicate warning; "Tutup Pengiriman" with its reason (IP-09); "Terbitkan" — DOC-08 or DOC-10 — with IP-08 |
| PG-032 | Laras (`dropship.confirm`; reversal `fulfillment.correct`; return `sales_return.record`; cost `cost.view`) | S2; S3 cost | record | the journey's *Kirim langsung* stage | `[desk]` | "Konfirmasi Kirim Langsung" (AX-08); the confirmation's reversal and a drop-ship return, each with its reason (IP-09; AX-08; AX-10) |
| PG-033 | Laras (`handover.record`) | S2 | record | the journey's *Jasa* stage | `[both]` | the handover; the BAST draft with its preview "DRAF — belum terbit" (DC-2; D-DS-23) |
| PG-034 | Laras (`sales_return.record`; companions by their capability) | S1; S2 | record | *Rincian proyek* › *Pengiriman*; *Tindakan Lain* | `[both]` | opening the case; the restoration with its reservation offer (AX-10; CM-05); "Tutup Kasus"; IP-09 |
| PG-035 | all three (`projects.view` for metadata; payload by family, PJ-15) | S2; S3 by family | branches | *Menu lengkap* › *Dokumen*; "Semua dokumen" | `[both]` | the exact number lookup (PF-19) |
| PG-036 | Laras, Dimas; Bayu for the operational family (`documents.issue`; revision and void `documents.correct`) | S2; S3 by family (PJ-15) | record | document readiness; the document list | `[desk]` · Edit → Preview → Finalize, full-screen, on the phone (DC-11) | the composer's preview beside the form (DC-2; D-DS-23); IP-08's issue confirmation; revision and void with their reason (IP-09); "Tautkan Kuitansi" (AX-31) |
| PG-038 | all three (`evidence.view` with the family's view capability; `evidence.upload`) | S4 by family (PJ-08) | branches and pool evidence | *Dokumen* › *Bukti*; "Buat" › *Unggah Dokumen* | `[both]` | the upload sheet (IP-16); duplicate-evidence warnings (IP-05; FL-10); "Jadikan Versi Berlaku" |
| PG-039 | all three read (`projects.view`); Laras, Dimas act (`requirements.manage`, `requirements.waive`); the Owner's fields Laras (OD-05, OD-06) | S2 | branches | *Dokumen* › *Dokumen Administrasi* | `[desk]` | "Tandai Tidak Berlaku" with its reason (IP-09); the Owner's relaxations |
| PG-040 | all three (`projects.view` of the case company; money counters by family, PJ-16) | S2; S3 counters by family | branches | *Pekerjaan* › *Dokumen dan kasus*; the journey; *Dokumen* › *Kasus Koreksi* | `[desk]` | "Tutup Kasus" (`cases.close`) with its reason (IP-09) |

### 8.5 Finance

| PG | Name — Indonesian · English (proposed) | Route | Kind | Purpose | SCR · R1 element | Contracts |
| --- | --- | --- | --- | --- | --- | --- |
| PG-041 | *Invoice* · Invoices | `/keuangan/invoice` | list | invoices (WF-FIN-01) | SCR-38 · *Keuangan* › *Invoice* | NAV-041 · ROUTE-041 |
| PG-042 | *Buat Invoice* · New invoice | `/keuangan/invoice/baru`, the project by its public id | form or editor | the invoice composer — "Diambil dari proyek" and "Diisi sekarang" on the left and, on the desktop, the live preview "DRAF — belum terbit" on the right (DESIGN_SYSTEM D-DS-23; `DIR-079` CD-04), "Simpan Draf" and "Terbitkan Invoice" (WF-FIN-01; L-26; DC-1–DC-12; GAP-053) | SCR-38's "Buat Invoice" with SCR-39's draft · R1's connected creation, RS-8; VC1's composer, AN-4 | NAV-042 · ROUTE-042 |
| PG-043 | *Detail Invoice* · Invoice | `/keuangan/invoice/{id}` | record workspace | lines, tax, position block, billing act, applications and settlements, documents; a draft opens in the composer, its form on the left and the live preview on the right on the desktop (DESIGN_SYSTEM D-DS-23; `DIR-079` CD-04) (WF-FIN-01; WF-FIN-02; WF-FIN-05; WF-FIN-08) | SCR-39 · document readiness; *Rincian proyek* › *Keuangan*; QS-09–QS-11 rows | NAV-043 · ROUTE-043 |
| PG-044 | *Piutang* · Receivables | `/keuangan/piutang` | list | active receivables with their due state; *Belum Ditagihkan*; *Sengketa* (WF-FIN-02) | SCR-40 · *Keuangan* › *Piutang*; *Beranda*'s QS-10 and QS-11 rows | NAV-044 · ROUTE-044 |
| PG-045 | *Pembayaran* · Payments | `/keuangan/pembayaran` | list | payments and their applications (WF-FIN-03) | SCR-41 · *Keuangan* › *Pembayaran* | NAV-045 · ROUTE-045 |
| PG-046 | *Catat Pembayaran* · Record payment | `/keuangan/pembayaran/baru` | form or editor | a payment with its same-command applications (WF-FIN-03; AX-18; SF-APPLY) | SCR-42 · "Buat" › *Pembayaran*; the journey's *Pembayaran* stage | NAV-046 · ROUTE-046 |
| PG-047 | *Potongan* · Deductions | `/keuangan/potongan` | form or editor | fee and tax deduction settlements (WF-FIN-04; AX-21) | SCR-43 · *Keuangan* › *Potongan*; QS-13 rows | NAV-047 · ROUTE-047 |
| PG-048 | *Kredit Pelanggan* · Customer credit | `/keuangan/kredit-pelanggan` | list | customer credit and refunds (WF-FIN-06) | SCR-44 · *Keuangan* › *Lainnya*; QS-12 rows | NAV-048 · ROUTE-048 |
| PG-049 | *Pengeluaran & Kas Lain* · Disbursements and other cash | `/keuangan/kas-lain` | list | disbursements, expenses and other Cash-In (WF-FIN-07) | SCR-45 · *Keuangan* › *Lainnya* | NAV-049 · ROUTE-049 |
| PG-050 | *Mutasi Bank* · Bank statement | `/keuangan/mutasi-bank` | list | bank statement lines and their citation (L-22) | SCR-46 · *Keuangan* › *Lainnya*, dense | NAV-050 · ROUTE-050 |

| PG | Personas · capability | Field families | Company | Entry points | Tag · phone | Hosts — dialogs, sheets, confirmations |
| --- | --- | --- | --- | --- | --- | --- |
| PG-041 | Laras, Dimas (`finance.view`) | S3 finance | branches | *Keuangan* › *Invoice* | `[desk]` | "Buat Invoice" (`invoice.issue`); the exact number lookup |
| PG-042 | Laras, Dimas (`invoice.issue` with `finance.view`) | S2; S3 finance; the identity assets of PJ-19 | the project's company | the journey's *Invoice* stage and "Buat invoice"; document readiness; *Keuangan* › *Invoice* | `[desk]` · on the phone the issue step shows as WF-FIN-01's `[desk]` rule and DC-11 say (S2-J03) | the preview "DRAF — belum terbit" beside the form (GD-07; DC-2; D-DS-23); "Simpan Draf"; MSG-39's one-step confirmation (IP-08; AX-16) |
| PG-043 | Laras, Dimas (`finance.view`; commands by capability) | S3 finance; S4 by family | record | readiness; *Rincian proyek* › *Keuangan*; QS-09–QS-11 rows | `[desk]` | for a draft, the composer: the preview "DRAF — belum terbit" beside the form (DC-2; D-DS-23), "Simpan Draf" and "Terbitkan Invoice" with MSG-39 (IP-08; AX-16); "Tagihkan" (IP-08); "Tandai Sengketa"; corrections with their reason (IP-09); Laras's "Hapuskan Piutang" (IP-08; IP-09) |
| PG-044 | Laras, Dimas (`finance.view`) | S3 finance | branches; subtotals per company | *Keuangan* › *Piutang*; *Beranda*'s QS-10 and QS-11 rows | `[desk]` | the tabs *Aktif*, *Belum Ditagihkan*, *Sengketa*; "Catat Pembayaran"; "Tagihkan" |
| PG-045 | Laras, Dimas (`finance.view`; "Terapkan" `payment.apply`) | S3 finance | branches | *Keuangan* › *Pembayaran* | `[both]` | "Terapkan"; corrections with their reason (IP-09) |
| PG-046 | Laras, Dimas (`payment.record` with `finance.view`, which covers same-command applications) | S3 finance; S4 PAYMENT_PROOF (PJ-08) | new root: the chosen company (CS-03) | "Buat" › *Pembayaran*; the journey's *Pembayaran* stage; *Piutang* | `[both]` · one column with its application step | the application step (AX-18; SF-APPLY); the duplicate-payment warning, continued only with `warnings.override` (IP-05; BD-02); the missing-proof reason (L-29); IP-08's confirmation |
| PG-047 | Laras (`settlement.record`, with `finance.view` and `evidence.view`) | S3; S4 | record | *Keuangan* › *Potongan*; QS-13 rows | `[desk]` | "Catat Potongan" (AX-21) with its confirmation, reason and evidence (IP-08; IP-09) |
| PG-048 | Laras, Dimas (`finance.view`; `payment.apply`); refund Laras (`refund.record`) | S3 | branches | *Keuangan* › *Lainnya*; QS-12 rows | `[desk]` | "Terapkan ke Invoice"; "Kembalikan Dana" (AX-23; IP-08; IP-09) |
| PG-049 | Laras, Dimas (`finance.view`; expenses `cost.view`, and the expenses alone with `expense.record`); recording by capability — Laras | S3 | branches | *Keuangan* › *Lainnya* | `[desk]` | each kind's form by capability; a correction (IP-09) |
| PG-050 | Laras (`bank_statement.record`, with `finance.view` and `evidence.upload`); for another viewer QS-12's bank-line row is text without a link (§9.1) | S3; S4 BANK_STATEMENT | branches | *Keuangan* › *Lainnya* | `[desk]` · dense | recording a line; a void (`cash.correct`, IP-09) |

### 8.6 Reports, master data, settings and Owner areas

| PG | Name — Indonesian · English (proposed) | Route | Kind | Purpose | SCR · R1 element | Contracts |
| --- | --- | --- | --- | --- | --- | --- |
| PG-051 | *Laporan* · Reports | `/laporan` | report | stock, purchase, sales, receivable, payment, profit and cash-flow reports (CAP-11; CAP-17) | SCR-47 · *Menu lengkap* › *Laporan*, dense | NAV-051 · ROUTE-051 |
| PG-052 | *Laporan Gabungan* · Consolidated report | `/laporan/gabungan` | report | consolidated figures and the inter-company allocation report (CALC-12) | SCR-48 · *Laporan* › *Laporan Gabungan*; *Beranda*'s "Rincian angka" | NAV-052 · ROUTE-052 |
| PG-053 | *Ekspor Saya* · My exports | `/laporan/ekspor` | list | the requester's own export requests (HO-09) | SCR-49 · *Laporan* › *Ekspor Saya* | NAV-053 · ROUTE-053 |
| PG-054 | *Klien* · Clients | `/data-master/klien` | list | client organizations, units and PICs, search-first (WF-MD-02; D-IA-14 KEEP) | SCR-50 · *Menu lengkap* › *Data Master* › *Klien* | NAV-054 · ROUTE-054 |
| PG-055 | *Pemasok* · Suppliers | `/data-master/pemasok` | list | suppliers (WF-MD-03) | SCR-51 · *Data Master* › *Pemasok* | NAV-055 · ROUTE-055 |
| PG-056 | *Produk & Jasa* · Products and services | `/data-master/produk` | list | products and services, their barcodes and labels (WF-MD-04) | SCR-52 · *Data Master* › *Produk & Jasa* | NAV-056 · ROUTE-056 |
| PG-057 | *Lokasi Rak* · Rack locations | `/data-master/lokasi-rak` | list | racks and minimum stock (WF-MD-05) | SCR-53 · *Data Master* › *Lokasi Rak* | NAV-057 · ROUTE-057 |
| PG-058 | *Impor Data Awal* · Opening import | `/data-master/impor` | list | batches, dry run, sign-off, commit and progress (WF-MIG-01) — an open purchase keeping its legacy number when that number fits its company's PURCHASE scheme, any other numbered when the import commits (`DIR-079` CB-01) | SCR-58 · *Data Master* › *Impor Data Awal* | NAV-058 · ROUTE-058 |
| PG-059 | *Perusahaan* · Companies | `/pengaturan/perusahaan` | settings | the company master and its derived readiness (WF-MD-01) | SCR-54 · *Pengaturan* › *Perusahaan* | NAV-059 · ROUTE-059 |
| PG-060 | *Pengguna & Akses* · Users and access | `/pengaturan/pengguna` | settings | accounts, role, company grants, capabilities, sessions and credential links, with the Owner's account screens of H7-17 (WF-ACC-02) | SCR-57 · *Pengaturan* › *Pengguna & Akses*; *Beranda*'s security notice; §7.5 | NAV-060 · ROUTE-060 |
| PG-061 | *Penomoran Dokumen* · Document numbering | `/pengaturan/penomoran` | settings | schemes, counters, boundaries, go-live and covering batches; start and seed ([NM-03](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s14-17-numbering-jobs-revocation.md#14-numbering-concurrency); HO-39) for each document type, PROJECT and PURCHASE — PURCHASE set like the document types — seeded before go-live by the opening import, after the company's last legacy purchase number or at 1 where it has none, or, for a company no import seeds, started here once go-live is recorded (NM-03; `DIR-079` CB-02; the reading [OQ-25](stage2/OWNER_QUESTIONS.md#oq-25--the-purchase-seed-of-1-before-go-live) asks the Owner to confirm) | SCR-55 · *Pengaturan* › *Penomoran Dokumen* | NAV-061 · ROUTE-061 |
| PG-062 | *Pajak* · Tax | `/pengaturan/pajak` | settings | tax types, rate versions, company profiles and treatment rules (WF-MD-01) | SCR-56 · *Pengaturan* › *Pajak* | NAV-062 · ROUTE-062 |
| PG-063 | *Riwayat Aktivitas* · Activity history | `/aktivitas/riwayat` | report | workspace audit search (BR-XC-01) | SCR-59 · *Aktivitas & Keamanan* › *Riwayat Aktivitas*, dense | NAV-063 · ROUTE-063 |
| PG-064 | *Log Keamanan* · Security log | `/aktivitas/log-keamanan` | report | security events (LG-02) | SCR-60 · *Aktivitas & Keamanan* › *Log Keamanan*, dense; *Beranda*'s security notice | NAV-064 · ROUTE-064 |

| PG | Personas · capability | Field families | Company | Entry points | Tag · phone | Hosts — dialogs, sheets, confirmations |
| --- | --- | --- | --- | --- | --- | --- |
| PG-051 | each report by its read class (RD-02–RD-06) — Laras all; Dimas those of `stock.view`, `projects.view`, `finance.view` and `cost.view`; Bayu the stock report | by read class | branches; one section per company for an Admin (D-IA-12 KEEP) | *Menu lengkap* › *Laporan* | `[desk]` · dense | "Ekspor" (`reports.export`, → PG-053) |
| PG-052 | Laras (`reports.consolidated`) | consolidated (RD-07) | all companies | *Laporan* › *Laporan Gabungan*; *Beranda*'s "Rincian angka" | `[desk]` · dense | "Ekspor" |
| PG-053 | Laras (`reports.export`, the requester only) | own requests | own | *Laporan* › *Ekspor Saya* | `[both]` | "Unduh"; "Minta Lagi" |
| PG-054 | all three — S0 names; units, addresses, PICs `projects.view`; edits `parties.manage` (Laras) | S0; S2 relations (PJ-10) | global; related records by branches | *Data Master* › *Klien* | `[both]` · search-first | search-first creation (PT-27) |
| PG-055 | all three — S0 names; details `projects.view` or `cost.view`; price history `cost.view`; edits `parties.manage` (Laras) | S0; S2; S3 per company (PJ-09) | global; history by branches | *Data Master* › *Pemasok* | `[desk]` | creation and archive |
| PG-056 | all three; price defaults by family (PJ-14); stock `stock.view`; edits `products.edit` (Laras) | S0; S3 defaults | global | *Data Master* › *Produk & Jasa* | `[desk]` | "Buat Barcode Internal"; "Cetak Label" |
| PG-057 | all three read (`stock.view`); edits `warehouse.manage` (Laras) | S1 | pool | *Data Master* › *Lokasi Rak* | `[desk]` | creation and edit |
| PG-058 | Laras — preparing `import.prepare` with every batch company in scope, committing `import.commit` | PJ-20 | the batch's companies | *Data Master* › *Impor Data Awal* | `[desk]` | the step-up before the commit (IP-10; AU-15); IP-08 |
| PG-059 | Laras (`companies.manage`) | PJ-19 | all companies | *Pengaturan* › *Perusahaan* | `[desk]` | the step-up for bank accounts and identity assets (IP-10) |
| PG-060 | Laras (`users.manage`, `permissions.manage`) | PJ-17 | global | *Pengaturan* › *Pengguna & Akses*; *Beranda*'s security notice | `[desk]` | the step-up (IP-10; AU-15); a credential link shown once (AU-12); H7-17's TOTP reset and Google unlink with their reason ([AUTH_JOURNEYS](stage2/AUTH_JOURNEYS.md)) |
| PG-061 | Laras (`companies.manage`) | PERMISSIONS_MATRIX §7.2 rows 10–12 | all companies | *Pengaturan* › *Penomoran Dokumen* | `[desk]` | "Mulai Penomoran" and "Tetapkan Nomor Awal" with IP-08's confirmation (NM-03) |
| PG-062 | Laras (`tax.configure`) | PERMISSIONS_MATRIX §7.2 rows 13–16 | global and per company | *Pengaturan* › *Pajak* | `[desk]` | configuration forms |
| PG-063 | Laras (`audit.view`) | PJ-11 | branches and events without a company | *Aktivitas & Keamanan* › *Riwayat Aktivitas* | `[desk]` · dense | filters on IX-13 |
| PG-064 | Laras (`security_log.view`) | PJ-21 | global | *Aktivitas & Keamanan* › *Log Keamanan*; *Beranda*'s security notice | `[desk]` · dense | the filter by kind |

### 8.7 The approved screens SCR-01–SCR-60 and their pages

Each screen of the approved revision with the page or pages that carry it now — a screen is split only where R1 places its purpose differently ([IA_PAGE_INVENTORY](stage2/IA_PAGE_INVENTORY.md) §5) —, from [NON_LOSS_MATRIX](stage2/NON_LOSS_MATRIX.md) section A; 60 of 60 placed, no page without a screen.

| SCR | Screen | Pages |
| --- | --- | --- |
| SCR-01 | Masuk | PG-001, PG-002 |
| SCR-02 | Atur Kata Sandi | PG-003, PG-004 |
| SCR-03 | Akun Saya | PG-065 |
| SCR-04 | Halaman Sistem | PG-005 |
| SCR-05 | Beranda (Owner) | PG-006; its operational groups on PG-007 |
| SCR-06 | Beranda — Antrean Tindakan (Admin) | PG-007 |
| SCR-07 | Tinjauan Owner | PG-008 |
| SCR-08 | Cari | PG-009 |
| SCR-09 | Daftar Proyek | PG-010 |
| SCR-10 | Detail Proyek | PG-011 |
| SCR-11 | Formulir Proyek | PG-013 |
| SCR-12 | Daftar Penawaran | PG-037 |
| SCR-13 | Penawaran | PG-014 |
| SCR-14 | Penyelesaian Proyek | PG-011 ("Yang tersisa sebelum selesai"), PG-012 |
| SCR-15 | Daftar Pembelian | PG-015 |
| SCR-16 | Detail Pembelian | PG-016 |
| SCR-17 | Formulir Pembelian | PG-017 |
| SCR-18 | Stok | PG-018 |
| SCR-19 | Detail Stok Produk | PG-019 |
| SCR-20 | Riwayat Stok | PG-020 |
| SCR-21 | Barang Masuk | PG-021, PG-022 |
| SCR-22 | Barang Keluar | PG-023, PG-024 |
| SCR-23 | Reservasi | PG-025 |
| SCR-24 | Stok Opname | PG-026 |
| SCR-25 | Penyesuaian & Kondisi | PG-027 |
| SCR-26 | Retur ke Pemasok | PG-028 |
| SCR-27 | Selisih & Temuan | PG-029 |
| SCR-28 | Pengiriman | PG-030 |
| SCR-29 | Catat Pengiriman | PG-031 |
| SCR-30 | Kirim Langsung | PG-032 |
| SCR-31 | Serah Terima Jasa | PG-033 |
| SCR-32 | Retur dari Klien | PG-034 |
| SCR-33 | Kasus Koreksi | PG-040 |
| SCR-34 | Dokumen | PG-035 |
| SCR-35 | Detail Dokumen | PG-036 |
| SCR-36 | Bukti | PG-038 |
| SCR-37 | Dokumen Administrasi | PG-039 |
| SCR-38 | Daftar Invoice | PG-041; "Buat Invoice" PG-042 |
| SCR-39 | Detail Invoice | PG-042 (the draft's composer), PG-043 |
| SCR-40 | Piutang | PG-044 |
| SCR-41 | Pembayaran | PG-045 |
| SCR-42 | Catat Pembayaran | PG-046 |
| SCR-43 | Potongan | PG-047 |
| SCR-44 | Kredit Pelanggan | PG-048 |
| SCR-45 | Pengeluaran & Kas Lain | PG-049 |
| SCR-46 | Mutasi Bank | PG-050 |
| SCR-47 | Laporan | PG-051 |
| SCR-48 | Laporan Gabungan | PG-052 |
| SCR-49 | Ekspor Saya | PG-053 |
| SCR-50 | Klien | PG-054 |
| SCR-51 | Pemasok | PG-055 |
| SCR-52 | Produk & Jasa | PG-056 |
| SCR-53 | Lokasi Rak | PG-057 |
| SCR-54 | Perusahaan | PG-059 |
| SCR-55 | Penomoran Dokumen | PG-061 |
| SCR-56 | Pajak | PG-062 |
| SCR-57 | Pengguna & Akses | PG-060 |
| SCR-58 | Impor Data Awal | PG-058 |
| SCR-59 | Riwayat Aktivitas | PG-063 |
| SCR-60 | Log Keamanan | PG-064 |

### 8.8 Global states of every page in the shell

Not pages: the idle warning dialog and the re-authentication covering the page after expiry — D-UX-03's redesign, [AUTH_JOURNEYS](stage2/AUTH_JOURNEYS.md) §2.5, with AU-04's whole sequence and unsent input kept in memory (AU-07; H7-05; H7-19) —, the notice strip (§6.1), the logout question when input is unsent (MSG-35), the *Pintasan* help (KB-02) and a lost-connection answer (IP-04).

## 9. Dashboards and queues

Signals are projections over facts and decisions; no queue is stored (WORKFLOWS §10; DATABASE §3). Each is one bounded statement, or one per predicate for QS-15 (PF-24), read with the viewer's scope first (CS-07) and shown per company (CS-08): a count reads "ARJ 4 · BTN 20+" — bounded per company, never summed across companies for an Admin (PF-15, PF-25). A signal row links to the page where the next action happens for a viewer who holds that action's capability, and otherwise to the record's view (PJ-13); a dashboard itself runs no command.

**Where the groups live (R1 b), c)).** The Owner lands on *Beranda* (PG-006): decisions and exceptions first — *Perlu keputusan Anda*, two rows on the phone, then "Lihat semua keputusan" —, the four KPIs in the same view, *Proyek yang perlu perhatian*, and the operational queues folded into one line, *Antrean operasional*, with "Buka Pekerjaan". Every account works from *Pekerjaan* (PG-007): work grouped by what has to happen next — *Barang: terima, keluarkan, kirim*, *Dokumen dan kasus*, *Tagihan dan pembayaran*, *Penyelesaian proyek* —, each group present only with its read class and Bayu's "Berikutnya" card first (R1 f)). R1 c) reads the groups below into *Beranda*'s four regions; their statements and budgets do not change with the regions ([S2-J02](stage2/journeys/S2-J02.md) §2).

**Groups and budget.** A page loads its frame and group headings in the first response; each group is a deferred group with its own loading state — a spinner after 300 ms — and its own error with "Muat Ulang Bagian Ini" (PT-19). *Beranda* has five groups — four signal groups and the period figures, read in one snapshot (TX-10; PF-43); *Pekerjaan* has four signal groups. QB-10 bounds each signal group at twelve statements, the ten completion predicates of QS-15 forming a group of their own.

| Group | *Beranda* — the Owner (PG-006) | *Pekerjaan* — every account (PG-007) | Statements |
| --- | --- | --- | --- |
| G1 *Perlu Perhatian Owner* · *Barang: terima, keluarkan, kirim* | the Owner's security notices (below); QS-14 (new items since the Owner's previous sign-in from every source of PG-008); QS-16; QS-18 attribution; QS-20; QS-22; QS-23, the overdue projects (`DIR-077` CA-10) | QS-01–QS-06, QS-16, QS-17, QS-18 (open findings, stale counts), QS-20, QS-24, the dispatch-ready lines (`DIR-077` CA-07) | 10 · 12 |
| G2 *Operasional* · *Dokumen dan kasus* | QS-01–QS-08, QS-17, QS-18 (open findings, stale counts), QS-21 — folded into *Antrean operasional* | QS-07, QS-08, QS-21 | 12 · 3–6 |
| G3 *Penagihan & Kas* · *Tagihan dan pembayaran* | QS-09–QS-13, QS-19 | the same, present only with `finance.view` | 8 |
| G4 *Penyelesaian Proyek* · *Penyelesaian proyek* | QS-15 | QS-15, its money items only with `finance.view` | 12 |
| G5 *Angka Periode* | CALC-08–CALC-14 per company and *Gabungan*, "Angka per {waktu} WIB"; the KPI *Piutang aktif* (CALC-05) and the ageing chart (CALC-07), one grouped statement over `invoice_balances` per company in scope within QB-10 — [OQ-21](stage2/OWNER_QUESTIONS.md#oq-21--the-receivables-kpi-and-the-ageing-chart-within-qb-10) (a) (`DIR-079` CB-03) | — | ≤ 7 |

**Refresh.** By the user ("Muat Ulang") or by a poll no faster than once a minute that names its groups' props (PF-26, PF-36); polls are background requests that never extend a session (H7-13). The time of the last refresh is shown quietly ("Diperbarui 14.05 WIB"). An empty group is one quiet sentence, never an illustration (PT-19); a group the account has no capability for does not exist on its page.

**Owner security notices.** The in-app notices SECURITY owes the Owner head G1 as rows of their own: a credential link the Owner issued was used (AU-12) — MSG-29's Owner variant with the account and the time — and, until P9 confirms another channel, the LG-07 alerts that are single security events — an OWNER_RECOVERY_LINK_ISSUED, an AUTHORIZATION_ANOMALY, a successful Owner sign-in from an address not seen for it in 30 days — each as its neutral type, its time and a link to PG-064 (§11). They are derived by one bounded statement (PF-24) over the security events recorded since the Owner's previous sign-in (AU-13), its plan verified in P8 (HO-16), so nothing is marked read; older ones stay on PG-064. The aggregate alerts of LG-07 — bursts, spikes, volumes and allocation patterns — reach the Owner as P9 delivers them (H9-10; UXH-09). No Admin page has them.

**Owner view switch.** *Gabungan* · ARJ · BTN · CKP on *Beranda* filters the signal groups and the period figures and is a view choice like the company filter (§7); the one-company view shows the ageing chart "Umur piutang" with its table (R1 i)).

### 9.1 Placement of every signal

The approved placement with the pages of §8 in place of its screens, the destinations of [NAVIGATION_MAP](stage2/NAVIGATION_MAP.md) §1, and the two signals of the Owner's CP-A decisions (`DIR-077`). The empty states are canonical copy, rendered in English by [LOCALIZATION](LOCALIZATION.md).

| QS | Signal (WORKFLOWS §10) | Audience — read class (PJ-13) | *Beranda* · *Pekerjaan* group | Entry point and action | Empty state |
| --- | --- | --- | --- | --- | --- |
| QS-01 | confirmed warehouse demand not yet reserved | RD-02 with its project part RD-03 | G2 · G1 | the line on PG-025, "Reservasi", for `stock.reserve`; otherwise the line on PG-011 | "Semua permintaan gudang sudah direservasi." |
| QS-02 | shortage → purchase needed | RD-02, RD-03 | G2 · G1 | PG-017 prefilled for `purchase.manage`; otherwise the line on PG-011 | "Tidak ada kekurangan stok." |
| QS-03 | received goods for a project awaiting reservation | RD-02, RD-03 | G2 · G1 | PG-025, "Reservasi", for `stock.reserve`; otherwise the line on PG-011 | "Tidak ada barang diterima yang menunggu reservasi." |
| QS-04 | reservations cut; project demand flagged | RD-02, RD-03 | G2 · G1 | PG-011 | "Tidak ada reservasi yang dikurangi." |
| QS-05 | open purchase remainders | RD-05, or `stock.receive` through PJ-18 | G2 · G1 | PG-022 for receiving; PG-016 for `cost.view` | "Tidak ada sisa pembelian yang menunggu barang." |
| QS-06 | dispatched or shipped, not delivered | RD-03 | G2 · G1 | PG-031 for `delivery.record`; otherwise PG-030 | "Semua barang yang keluar sudah tercatat terkirim." |
| QS-07 | open discrepancies and correction cases, with their residuals | RD-03; money residuals RD-04 or RD-05 | G2 · G2 | PG-040 | "Tidak ada kasus koreksi terbuka." |
| QS-08 | rendition pending or failed | RD-03 | G2 · G2 | PG-036 | "Semua PDF sudah tersedia." |
| QS-09 | issued invoices *Belum Ditagihkan* | RD-04 | G3 · G3 | PG-044's *Belum Ditagihkan* → PG-043 "Tagihkan" | "Tidak ada invoice yang belum ditagihkan." |
| QS-10 | overdue receivables by ageing bucket | RD-04 | G3 · G3 | PG-044 | "Tidak ada piutang yang lewat jatuh tempo." |
| QS-11 | disputed receivables | RD-04 | G3 · G3 | PG-044's *Sengketa* | "Tidak ada piutang dalam sengketa." |
| QS-12 | unapplied customer credit (credit linked to a written-off invoice included); payments flagged without proof; bank lines not fully cited | RD-04 | G3 · G3; the credit linked to a written-off invoice also in the Owner review queue | PG-048; PG-045; PG-050 for `bank_statement.record` — for another viewer the bank-line row is text without a link | "Tidak ada kredit atau pembayaran yang perlu ditindaklanjuti." |
| QS-13 | claimed deductions not yet evidenced | RD-04 | G3 · G3 | PG-047 for `settlement.record`; otherwise the invoice on PG-043 | "Tidak ada potongan yang menunggu bukti." |
| QS-14 | the Owner review queue | RD-09 (`review.view`) | G1 · absent — no entry, count or badge exists for an Admin | PG-008 | "Tidak ada aktivitas baru sejak Anda masuk sebelumnya." |
| QS-15 | completion blockers per project; invoicing above delivered value; confirmed value not yet invoiced | RD-03; the money items RD-04 | G4 · G4 | PG-011's "Yang tersisa sebelum selesai", then PG-012; "Buat invoice" → PG-042 for confirmed value not yet invoiced | "Tidak ada proyek aktif yang terhambat." |
| QS-16 | residual obligations of force-completed or cancelled projects | RD-03; money items RD-04 | G1 · G1 | PG-011 of the project | "Tidak ada kewajiban tersisa." |
| QS-17 | low stock and restock advice | RD-02 | G2 · G1 | PG-018 filtered "di bawah minimum"; "Buat Pembelian" for `purchase.manage` — advice only, never a purchase | "Tidak ada stok di bawah minimum." |
| QS-18 | count findings, stale counts, unattributed surplus | RD-02 (findings, stale counts); RD-09 (attribution, Owner) | G1 (attribution) and G2 · G1 (findings, stale counts) | PG-026 for `stock.count` or `stock.count_apply`, otherwise the product on PG-019; PG-029 for the Owner | "Tidak ada temuan hitungan yang terbuka." |
| QS-19 | pre-payment Kuitansi not yet linked or voided | RD-04 | G3 · G3 | PG-036 of the Kuitansi; PG-043 | "Tidak ada Kuitansi untuk Proses Pembayaran yang menunggu." |
| QS-20 | routed refusals needing other-company grants | the Owner and accounts with every company of the refused command in scope (PJ-13) | G1 · G1 | the refused command's target record; a notice with its age that leaves the list after 14 days — no task state, no "selesai" control | "Tidak ada penolakan yang diteruskan dalam 14 hari terakhir." |
| QS-21 | duplicate warnings overridden or confirmed | per family: RD-02 stock events, RD-03 delivery events, RD-04 payments, RD-08 evidence (PERMISSIONS_MATRIX §7.4); each also in QS-14 | G2 · G2 | the record | "Tidak ada peringatan duplikat yang dikonfirmasi." |
| QS-22 | pending unexplained-loss cases with their recognition snapshots; their separate display in company stock and profit views | the cases with snapshots RD-09 (Owner); the pending quantities RD-02 | G1 · not in *Pekerjaan* — the pending quantities show on PG-018 and PG-019 as their own line, blocking nothing (DIR-027) | PG-029 | "Tidak ada selisih yang menunggu penetapan." |
| QS-23 | overdue projects — ACTIVE, the one governing deadline before today (WIB) (`DIR-077` CA-10) | the Owner alone — RD-09; PJ-13 (`DIR-077`) | G1 · absent — no Admin page shows it; an Admin sees the deadline as the project's fact only | the project's PG-011 | "Tidak ada proyek yang melewati tenggat." (proposed) |
| QS-24 | dispatch-ready lines — the remaining reservations of ACTIVE projects' WAREHOUSE lines (`DIR-077` CA-07) | RD-02 with its project part RD-03 (`DIR-077`) | — · G1, in *Keluarkan dan kirim* | PG-024, "Keluarkan Barang", for `stock.dispatch`; otherwise the line on PG-011; "Lihat Semua" → PG-023 | "Tidak ada barang yang siap dikeluarkan." (proposed) |

### 9.2 The Owner review queue (SCR-07)

Designed after the GAP-034 settlement ([P7_QUALITY_GATE](evidence/P7_QUALITY_GATE.md#gap-034-settlement-and-ordering-evidence)). PG-008 is a **derived view**, never a stored queue and never an approval step: every listed action is already in effect; the Owner reviews it and, where something is wrong, acts on the record through its normal commands and correction rows (PERMISSIONS_MATRIX §10). Only `review.view` opens it, as the Owner's *Tinjauan* (PG-008); an Admin's request for its address is the not-found page (UXS-25).

| Part | Sources (PERFORMANCE signal register, QS-14 row) | Presentation |
| --- | --- | --- |
| *Kondisi yang Masih Terbuka* | recovered money linked to a written-off invoice; refunds with an unbacked remainder; invoices left with an outstanding amount after a payment — conditions that disappear by themselves once their cause is resolved through normal commands | a compact table above the activity: condition, company, record, since when, "Buka Catatan" |
| *Aktivitas untuk Ditinjau* | audit events of authority class ADM_PLUS or OWNER_ONLY — corrections, closures, deduction settlements, evidence-identified resolutions, the Owner's own actions; acknowledged duplicate warnings (QS-21); pool-evidence uploads; import rows in state DUPLICATE whose twin lies outside the preparer's scope; the security events AUTHORIZATION_ANOMALY, EVIDENCE_DUPLICATE_UNDISCLOSED and IMPORT_COLLISION_UNDISCLOSED (SECURITY LG-02, `TECH-022`) | four tabs, one per source — *Aktivitas* (audit events and acknowledged duplicate warnings), *Keamanan*, *Bukti Gudang Bersama*, *Impor* — each its own list, newest first, within the chosen window: time recorded — and the business date when it differs —, actor, company (or *Gudang bersama*), one plain sentence of what happened, the reason, "Buka Catatan". The two undisclosed-duplicate items show both records, each under the Owner's projection, their companies, the account and the match basis; the uploader or preparer was shown nothing (UXS-18, UXS-19) |

- **Window and filters.** The default window is the last seven days; *Hari ini*, *7 hari*, *30 hari* or a date range; filters by company and actor (§8.6). A company filter keeps the items that have no company — pool evidence, batch collisions, AUTHORIZATION_ANOMALY events — labelled *Gudang bersama* or *Tanpa perusahaan*. Each tab continues by "Muat Lagi" and shows no total.
- **A derived aid, not a state.** A divider "Tercatat setelah Anda masuk sebelumnya ({waktu})" separates newer from older items. It is computed from the Owner's own previous successful sign-in (AU-13; PJ-17); nothing is marked read, done or dismissed, and no control for that exists (PN-2's marker is not needed).
- **Retention.** The two `TECH-022` kinds are kept at least 12 months (LG-06); the audit events as long as the records they describe.

## 10. Search, lookup and scanner entry points

| Entry point | Where | Behaviour |
| --- | --- | --- |
| **Cari** — global search | desktop: the field in the top bar, reached also by Ctrl+K; phone: the navigation's *Cari* | A code — SKU, barcode, serial, project number, document number — is looked up exactly first (PF-19): one hit opens it, several rows sharing the key within scope are listed for the user to choose. A name of at least three consecutive letters or digits searches product, client and supplier names (S0) and the titles of projects in scope (PF-20, PF-22), names equal to or beginning with the term first, a type-ahead of at most 20 rows and PG-009 for the full result, cut at 200 candidates with the notice of [ADMIN_FLOW IP-17](ADMIN_FLOW/s06-09-session-forms-lists.md#ip-17--search-and-lookup). At most 100 characters (PF-23) |
| **Pindai** — scanning into *Cari* | desktop: the scanner typing into the Cari field when it has focus; phone: the Cari field of the full-screen search (D-IA-04); and the scan field of a warehouse screen | Exact lookup only (QB-06): the product, serial or lot found opens its stock detail (PG-019); unknown and ambiguous codes as [ADMIN_FLOW IP-15](ADMIN_FLOW/s06-09-session-forms-lists.md#ip-15--scanner) |
| Scan fields of warehouse pages | PG-019, PG-022, PG-024, PG-026, PG-027, PG-028, PG-034 | PT-20; manual SKU entry and "Cari Produk" always beside the field |
| Lookups inside forms | product, client, unit, PIC, supplier, project, invoice pickers | PT-27: exact code first, then names from three characters; client creation is search-first (PG-054) |
| Exact number lookups on lists | PG-010, PG-035, PG-041 | the list's search field: an exact project, document or invoice number within scope |

Search terms never appear in a page address or title (§11). A result, count or empty state never hints at a record outside the viewer's scope (DP-06).

**"Buat".**

Each item is present only with its capability (AZ-01, a hint), and each new root names its company (CS-03; §7):

| Item | Capability | Opens | Its company |
| --- | --- | --- | --- |
| *Proyek* | `projects.manage` | PG-013 | chosen in the form's "Perusahaan", never prefilled from the filter for several companies |
| *Pembelian* | `purchase.manage` | PG-017 | the project's company, shown and not chosen, when started from a project's shortage; otherwise chosen (L-05) |
| *Barang Masuk* | `stock.receive` | PG-021, to choose the purchase, then PG-022 | the purchase's company — a receipt follows its purchase |
| *Pembayaran* | `payment.record` | PG-046 | chosen in the form |
| *Unggah Dokumen* | `evidence.upload` | PG-038's upload sheet | the evidence's company, or the shared warehouse for pool evidence (PJ-08) |

Laras sees all five; Dimas *Proyek*, *Pembelian*, *Pembayaran* and *Unggah Dokumen*; Bayu *Barang Masuk* and *Unggah Dokumen*. On the phone, new work starts where it is — "Buat Proyek" on PG-010, "Mulai" on a work row (§6.2).

## 11. Page addressing

Each page's route is a contract in [ROUTE_CONTRACTS](../03-workflows/ROUTE_CONTRACTS.md) — for P11, not code — following these conventions, which stay the approved revision's.

| Element | Convention | Grounds |
| --- | --- | --- |
| Paths | lowercase Indonesian segments that follow the product map — `/beranda`, `/proyek`, `/proyek/{id}`, `/proyek/{id}/penyelesaian`, `/gudang/barang-masuk`, `/keuangan/piutang`, `/pengaturan/penomoran` — where `{id}` is a public identifier, UUIDv7 (DATABASE §16). A child line, ledger row or guard is addressed through its root and never has an address of its own | DP-01; DATABASE §16 |
| Query | only whitelisted filter and sort keys whose values are enumerations, dates or public identifiers, and the opaque cursor (PF-14; WS-03). Never a search term, name, amount, number or S3/S4 value | DP-02; H7-10 |
| Titles | "{screen name} · MultipleCorp" — never a business number, name, company, amount or state, because titles enter browser history and tab lists | H7-10 |
| Unknown, out-of-scope and Owner-only addresses | the not-found page PG-005, identical for each (AZ-11) | AZ-11; UXN-10 |
| History | page data in browser history is encrypted, and cleared at every authentication boundary, or the document reloads (AU-06; H7-12); no form data is remembered in history state ([ADMIN_FLOW §6](ADMIN_FLOW/s06-09-session-forms-lists.md#6-session-expiry-re-authentication-and-unsent-input)) | WS-07; H7-12 |
| Notifications and alerts | a neutral type and a link; their content is derived at display time under the viewer's projection, so a revoked company's alert disappears | DP-11; H7-10 |
| Downloads and exports | ordinary browser requests to the authorizing controller, never an Inertia visit and never a signed link as the only authorization | RB-04; PF-29; FL-07 |
| The language before sign-in | the sign-in pages PG-001–PG-004 carry the chosen language as one whitelisted key, `bahasa=id` or `bahasa=en`, kept by each link between them and stored nowhere; after sign-in the account's own language applies ([LOCALIZATION](LOCALIZATION.md); D-IA-23) | C-1; GR-14; AU-08 |
| Credential links | the token travels in the address fragment and is removed from the address bar and history on load (AU-12) | AU-12; H7-04 |
| Prefetch | off on S3 and S4 pages (PF-35) | WS-07 |

## 12. Traceability

- **V1_SCOPE and acceptance:** CAP-01–CAP-18 → §5 and the pages of §8; the language and experience contract → §3, §6, §8 and [LOCALIZATION](LOCALIZATION.md); AC-04 → PG-011; AC-13 → §7, §11; AC-14 → §6, §8's phone column, §13; AC-23 → §9, §10; REF-007 → PG-006; REF-008 → PG-007; REF-013 → §10; REF-048 → §9; REF-050 → §10; REF-052 → §3; REF-053 → §6; REF-054 → [DESIGN_SYSTEM §2](DESIGN_SYSTEM.md#2-foundation-and-source-evidence); REF-055 → [DESIGN_SYSTEM §18](DESIGN_SYSTEM.md#18-anti-ai-slop-mapping). Every V1 capability, acceptance criterion, SCR and user-visible rule is mapped to a page, a route or an interaction in [NON_LOSS_MATRIX](stage2/NON_LOSS_MATRIX.md) section Q.
- **WORKFLOWS:** §10 QS-01–QS-24 → §9.1 (24 of 24, QS-23 and QS-24 `DIR-077`); WF-ACC-01 → §6, §7, PG-006, PG-007; every workflow's pages → §8 and [ADMIN_FLOW §10](ADMIN_FLOW/s10-a-journeys.md#10-journeys).
- **PERMISSIONS_MATRIX and SECURITY:** CS-01–CS-08 → §7; PJ-01–PJ-23 → §7, §8; §10 → §9.2; H7-02 → §7; H7-10 → §11; H7-15–H7-19 → §14 and [AUTH_JOURNEYS](stage2/AUTH_JOURNEYS.md), [ADMIN_FLOW](ADMIN_FLOW/README.md) aligned (GAP-044); DP-01, DP-02, DP-06, DP-11, DP-19 → §8, §10, §11; AU-13's display language (`DIR-077` CA-08) → PG-065, §5.4.
- **PERFORMANCE:** PF-13–PF-17, PF-41, PF-42 → §8; QB-01's journey summary (`DIR-077` CA-06) → PG-010; PF-19–PF-23 → §10; PF-24–PF-26, PF-34, PF-43, QB-10 → §9, QB-10's receivables position (`DIR-079` CB-03) → PG-006 and §9's G5; the QS-14 row and IX-14 (`TECH-022`) → §9.2.
- **Stage 3's contracts:** each page's NAV- contract → [NAVIGATION_CONTRACTS](NAVIGATION_CONTRACTS.md); each page's view and commands → [ROUTE_CONTRACTS](../03-workflows/ROUTE_CONTRACTS.md); its important interactions → [INTERACTION_CONTRACTS](../03-workflows/INTERACTION_CONTRACTS.md); its strings → [LOCALIZATION](LOCALIZATION.md)'s key inventory; UF-01's document layout ([DESIGN_SYSTEM](DESIGN_SYSTEM.md#4-design-decisions) D-DS-23; `DIR-079` CD-04) → PG-014, PG-033, PG-036, PG-042 and PG-043; the pages that issue a document from recorded facts — PG-016, PG-022, PG-024, PG-031 and PG-043's pre-payment *Kuitansi* — hold no draft or preview and stay outside SP-71's list, pending [OQ-22](stage2/OWNER_QUESTIONS.md#oq-22--documents-issued-from-recorded-facts).
- **Gaps:** GAP-006 → §7; GAP-034 → §9.2; GAP-035 → §8 with the route, interaction and navigation contracts; GAP-037 → §3.8, [LOCALIZATION](LOCALIZATION.md); GAP-044 → §14; GAP-052 → QS-23, PG-006; GAP-054 → PG-010; GAP-055 → QS-24, PG-007; GAP-056 → PG-065, §5.4; GAP-058 → PG-058 (`DIR-079` CB-01); GAP-059 and GAP-062 → PG-061 (CB-02; OQ-25); GAP-060 → PG-006, §9's G5 (CB-03).

## 13. Role-based navigation

The navigation per persona and device, the reachability of every page and the paths to the frequent and critical tasks, carried from [NAVIGATION_MAP](stage2/NAVIGATION_MAP.md) with the Owner's CP-A signals. **Rules for every path.** An entry the account cannot use is absent, never disabled (AZ-01; IA_PAGE_INVENTORY §3.3); an unknown, an out-of-scope and an Owner-only address answer the not-found page, identical for each, and a record in scope without the capability answers forbidden, MSG-10 (AZ-11; IP-21; PG-005). "›" separates the steps of a path; a work row leads to the page where its next action happens for an account holding that action's capability, otherwise to the record's view (PJ-13; §9.1).

### 13.1 Laras, the Owner — desktop

Lands on *Beranda* (PG-006).

- **Primary entries**
  - *Beranda* (PG-006) — the decision rows: the security notice › PG-060 or PG-064; a pending unexplained loss ([QS-22](../03-workflows/WORKFLOWS/s10-14-signals-decisions-traceability.md#10-derived-signals-and-action-queue)) and an unattributed surplus (QS-18) › PG-029; new activity (QS-14) › PG-008; a routed refusal (QS-20) › the refused command's record; residual obligations (QS-16) › PG-011; overdue and disputed receivables (QS-10, QS-11) › PG-044; completion conditions unmet (QS-15) › PG-011's "Yang tersisa sebelum selesai"; an overdue project (QS-23, `DIR-077` CA-10) › PG-011. The KPIs › "Rincian angka" › PG-052. "Proyek yang perlu perhatian" › PG-011. "Antrean operasional" › "Buka Pekerjaan" › PG-007.
  - *Proyek* (PG-010) › a row › PG-011 › its stages and drill-down: PG-014 (*Kesepakatan*), PG-025 (*Item dan pemenuhan*), PG-016 (*Pengadaan*), PG-030 and PG-031 (*Pengiriman*), PG-032 (*Kirim langsung*), PG-033 (*Jasa*), PG-034 (a delivery's "Retur dari Klien"), PG-036 ("Kesiapan dokumen"), PG-042 ("Buat invoice"), PG-043 (*Keuangan*), PG-046 (*Pembayaran*), PG-012 (*Penyelesaian*, *Tindakan Lain* › "Selesaikan Paksa"); "Buat Proyek" › PG-013.
  - *Pekerjaan* (PG-007) — every group, each row by its signal: QS-01, QS-03 › PG-025; QS-02 › PG-017; QS-04, QS-16 › PG-011; QS-05 › PG-022; QS-06 › PG-031; QS-24 › PG-024, its "Lihat Semua" › PG-023 (`DIR-077` CA-07); QS-07 › PG-040; QS-08, QS-19 › PG-036; QS-09 › PG-044 *Belum Ditagihkan* › PG-043; QS-10, QS-11 › PG-044; QS-12 › PG-048, PG-045, PG-050; QS-13 › PG-047; QS-15 › PG-011; QS-17 › PG-018; QS-18 › PG-026; QS-20, QS-21 › the record.
  - *Tinjauan* (PG-008) › a record.
- ***Menu lengkap***
  - *Pembelian* › PG-015 › PG-016; "Buat Pembelian" › PG-017.
  - *Gudang* › *Stok* PG-018 › PG-019 · *Barang Masuk* PG-021 › PG-022 · *Barang Keluar* PG-023 › PG-024 · *Reservasi* PG-025 · *Stok Opname* PG-026 · *Lainnya* › PG-020, PG-027, PG-028, PG-029, PG-030 › PG-031.
  - *Keuangan* › *Invoice* PG-041 › PG-043, "Buat Invoice" › PG-042 · *Piutang* PG-044 · *Pembayaran* PG-045, "Catat Pembayaran" › PG-046 · *Potongan* PG-047 · *Lainnya* › PG-048, PG-049, PG-050.
  - *Dokumen* › PG-035 › PG-036 · *Penawaran* PG-037 · *Bukti* PG-038 · *Dokumen Administrasi* PG-039 · *Kasus Koreksi* PG-040.
  - *Laporan* › PG-051 · *Laporan Gabungan* PG-052 · *Ekspor Saya* PG-053.
  - *Data Master* › PG-054, PG-055, PG-056, PG-057, PG-058.
  - *Aktivitas & Keamanan* › PG-063, PG-064.
  - *Pengaturan* › PG-059, PG-060, PG-061, PG-062.
- **Top bar** — "Cari atau pindai kode" › PG-009 › a record, a scanned code › PG-019; "Buat" › *Proyek* PG-013 · *Pembelian* PG-017 · *Barang Masuk* PG-021 › PG-022 · *Pembayaran* PG-046 · *Unggah Dokumen* PG-038; the account menu › *Akun Saya* PG-065 · *Bahasa tampilan* · *Keluar* › PG-001.

### 13.2 Laras, the Owner — phone

Lands on *Beranda* (PG-006). The same pages as §13.1; the entries differ:

- **Bottom bar** — *Beranda* (PG-006, the first two decision rows, then "Lihat semua keputusan") · *Proyek* (PG-010) · *Cari* (PG-009, full-screen, typed or scanned codes) · *Lainnya*.
- ***Lainnya* drawer** — *Kerja* › *Pekerjaan* (PG-007), *Tinjauan* (PG-008); *Menu lengkap* › the modules of §13.1; *Akun* › *Akun Saya* (PG-065), *Bahasa tampilan*, *Keluar*.
- **No "Buat"** — new work starts where it is: "Buat Proyek" on PG-010, "Mulai" on a work row (R1 a)). A `[desk]` page opens for reading and small tasks and does not break (this document §8).

### 13.3 Dimas, Admin Operasional — at the desk

Lands on *Pekerjaan* (PG-007). ARJ and BTN in scope, so lists carry the company filter and rows their company code (IA_PAGE_INVENTORY §4).

- **Primary entries**
  - *Pekerjaan* (PG-007) — the groups his read classes open ([PJ-13](../05-security/PERMISSIONS_MATRIX.md#73-special-projections)): *Barang: terima, keluarkan, kirim* — QS-01, QS-03 › PG-025; QS-02 › PG-017; QS-04, QS-16 › PG-011; QS-05 › PG-016, receiving not being his (`stock.receive`); QS-06 › PG-030, the delivery record not being his (`delivery.record`); QS-24 › PG-011, dispatching not being his (`stock.dispatch`); QS-17 › PG-018; QS-18's open findings and stale counts › PG-019, counting not being his (`stock.count`); QS-20 › the record — *Dokumen dan kasus* — QS-07 › PG-040; QS-08 › PG-036; QS-21 › the record — *Tagihan dan pembayaran* (`finance.view`) — QS-09 › PG-044 › PG-043; QS-10, QS-11 › PG-044; QS-12 › PG-048 and PG-045, its bank-line rows text without a link (`bank_statement.record`); QS-13 › PG-043, the settlement not being his (`settlement.record`); QS-19 › PG-036 — *Penyelesaian proyek* — QS-15 › PG-011, money items with `finance.view`.
  - *Proyek* (PG-010) › PG-011 › PG-014, PG-025, PG-016, PG-030, PG-036, PG-042, PG-043, PG-046, PG-012; "Buat Proyek" › PG-013.
- ***Menu lengkap*** — *Pembelian* › PG-015, PG-016, PG-017 · *Gudang* › *Stok* PG-018 › PG-019 · *Reservasi* PG-025 · *Lainnya* › PG-020, PG-030 — *Barang Masuk*, *Barang Keluar*, *Stok Opname*, *Penyesuaian & Kondisi*, *Retur ke Pemasok* and *Selisih & Temuan* absent · *Keuangan* › *Invoice* PG-041, PG-042, PG-043 · *Piutang* PG-044 · *Pembayaran* PG-045, PG-046 · *Lainnya* › PG-048, PG-049 — *Potongan* and *Mutasi Bank* absent · *Dokumen* › PG-035 to PG-040 · *Laporan* › PG-051, the reports of his read classes — *Laporan Gabungan* and *Ekspor Saya* absent · *Data Master* › PG-054 to PG-057 — *Impor Data Awal* absent.
- **Top bar** — PG-009; "Buat" › *Proyek* PG-013 · *Pembelian* PG-017 · *Pembayaran* PG-046 · *Unggah Dokumen* PG-038; the account menu › PG-065, *Bahasa tampilan*, *Keluar*.
- **Absent** — *Beranda*, *Tinjauan*, *Aktivitas & Keamanan*, *Pengaturan* and *Laporan Gabungan* (OWNER_ONLY; IA_PAGE_INVENTORY §3.3).

### 13.4 Dimas — phone

Lands on *Pekerjaan* (PG-007). **Bottom bar** *Pekerjaan* · *Proyek* · *Cari* · *Lainnya*; ***Lainnya*** › *Menu lengkap* (the modules of §13.3) and *Akun* (PG-065, *Bahasa tampilan*, *Keluar*) — no *Kerja* section, which is the Owner's. His `[desk]` pages — the editors of PG-013, PG-014 and PG-017, the composer PG-042 — open for reading and small tasks; the invoice's issue step follows WF-FIN-01's `[desk]` rule (S2-J03).

### 13.5 Bayu, Admin Operasional with warehouse grants — phone

Lands on *Pekerjaan* (PG-007). ARJ only in scope, so lists carry no company filter and rows no company code (§7).

- **Bottom bar** — *Pekerjaan* · *Proyek* · *Cari* · *Lainnya*.
  - *Pekerjaan* (PG-007) — "Berikutnya" first: "Terima ARJ/PB/2026/0216 — 4 baris belum dicatat" › "Mulai Terima Barang" › PG-022 (R1 f)); then *Barang: terima, keluarkan, kirim* — QS-05 › PG-022 (`stock.receive`, PJ-18); QS-06 › PG-031 (`delivery.record`); QS-24 › PG-024 (`DIR-077` CA-07), its "Lihat Semua" › PG-023; QS-01, QS-03 › PG-025; QS-02, QS-04, QS-16 › PG-011, purchasing not being his; QS-17 › PG-018; QS-18 › PG-026 (`stock.count`); QS-20 › the record — *Dokumen dan kasus* — QS-07 › PG-040; QS-08 › PG-036; QS-21 › the record — *Penyelesaian proyek* — QS-15's non-money items › PG-011. *Tagihan dan pembayaran* does not exist for him (no `finance.view`).
  - *Proyek* (PG-010) › PG-011 without *Posisi keuangan*, *Pengadaan* or money figures (R1 e)).
  - *Cari* (PG-009) — a scanned or typed code › PG-019.
- ***Lainnya* drawer** — *Menu lengkap* › *Gudang* › *Stok* PG-018 › PG-019 · *Barang Masuk* PG-021 › PG-022 · *Barang Keluar* PG-023 › PG-024 · *Reservasi* PG-025 · *Stok Opname* PG-026 · *Lainnya* › PG-020, PG-027, PG-030 › PG-031 — *Retur ke Pemasok* and *Selisih & Temuan* absent · *Dokumen* › PG-035 to PG-040, payloads of the operational family only (PJ-15) · *Laporan* › PG-051, the stock report · *Data Master* › PG-054 to PG-057; *Akun* › PG-065, *Bahasa tampilan*, *Keluar*.
- **Absent** — *Pembelian* and *Keuangan* (no `cost.view`, no `finance.view`), "Buat" (the phone starts work where it is), and every OWNER_ONLY area.

### 13.6 Reachability

Every page against every persona and a **full Admin** — an Admin holding every ADM and ADM_PLUS capability (63 of [PERMISSIONS_MATRIX](../05-security/PERMISSIONS_MATRIX.md#permissions-matrix) §4) in all three companies, who shows every page an Admin can reach. Each cell gives the path from the landing page — *Beranda* for Laras, *Pekerjaan* for an Admin — on the desktop, for Bayu on his phone, or "absent" with the capability not held; "the same" repeats the path of the column before it, the first step on Bayu's phone going through the bottom bar or *Lainnya* (§1.5). The phone reaches the same pages as the desktop (§1.2, §1.4).

| PG | Laras (Owner) | Dimas | Bayu | Full Admin |
| --- | --- | --- | --- | --- |
| PG-001 | the sign-in address; after *Keluar* or the absolute limit | the same | the same | the same |
| PG-002 | PG-001 › password or Google | the same | the same | the same |
| PG-003 | a credential link only ([AU-12](../05-security/SECURITY.md#2-authentication-sessions-and-credentials)) | the same | the same | the same |
| PG-004 | an enrolment-only session only (AU-18; AU-20 b) | the same | the same | the same |
| PG-005 | any address the account cannot open (AZ-11) | the same | the same | the same |
| PG-006 | landing | absent — `review.view` (OWNER_ONLY) | absent — `review.view` (OWNER_ONLY) | absent — `review.view` (OWNER_ONLY) |
| PG-007 | *Pekerjaan*; *Beranda* › "Buka Pekerjaan" | landing | landing | landing |
| PG-008 | *Tinjauan*; *Beranda* › [QS-14](../03-workflows/WORKFLOWS/s10-14-signals-decisions-traceability.md#10-derived-signals-and-action-queue) row | absent — `review.view` | absent — `review.view` | absent — `review.view` |
| PG-009 | the top bar or Ctrl K | the same | *Cari* | the top bar or Ctrl K |
| PG-010 | *Proyek* | *Proyek* | *Proyek* | *Proyek* |
| PG-011 | *Proyek* › row; *Beranda* › a decision or attention row; *Pekerjaan* › row | *Pekerjaan* › row; *Proyek* › row | the same | the same |
| PG-012 | PG-011 › *Penyelesaian* | PG-011 › *Penyelesaian*; "Selesaikan Proyek" (`projects.complete`) | PG-011 › *Penyelesaian*, read only | PG-011 › *Penyelesaian*, with "Selesaikan Proyek" |
| PG-013 | "Buat" › *Proyek*; *Proyek* › "Buat Proyek" | the same | absent — `projects.manage` | the same as Dimas |
| PG-014 | PG-011 › *Kesepakatan* | PG-011 › *Kesepakatan* | absent — `quotation.manage` | PG-011 › *Kesepakatan* |
| PG-015 | *Menu lengkap* › *Pembelian* | the same | absent — `cost.view` | the same |
| PG-016 | *Pembelian* › row; PG-011 › *Pengadaan* | the same; *Pekerjaan* › QS-05 row | absent — `cost.view` | the same as Dimas |
| PG-017 | "Buat" › *Pembelian*; PG-015 › "Buat Pembelian"; *Pekerjaan* › QS-02 row; PG-011 › *Gudang* stage | the same | absent — `purchase.manage` | the same |
| PG-018 | *Menu lengkap* › *Gudang* › *Stok* | the same | *Lainnya* › *Menu lengkap* › *Gudang* › *Stok* | the same as Dimas |
| PG-019 | *Stok* › product; search or scan | the same | the same | the same |
| PG-020 | *Gudang* › *Lainnya* › *Riwayat Stok* | the same | the same | the same |
| PG-021 | *Gudang* › *Barang Masuk*; "Buat" › *Barang Masuk* | absent — `stock.receive` | *Gudang* › *Barang Masuk* | the same as Laras |
| PG-022 | *Pekerjaan* › QS-05 row; PG-021 › purchase | absent — `stock.receive` | *Pekerjaan* › "Berikutnya" › "Mulai Terima Barang"; QS-05 row; PG-021 › purchase | *Pekerjaan* › QS-05 row; PG-021 › purchase |
| PG-023 | *Gudang* › *Barang Keluar*; *Pekerjaan* › QS-24's "Lihat Semua" | absent — `stock.dispatch` | the same as Laras | the same as Laras |
| PG-024 | *Pekerjaan* › QS-24 row; PG-023 › project | absent — `stock.dispatch` | the same as Laras | the same as Laras |
| PG-025 | *Gudang* › *Reservasi*; *Pekerjaan* › QS-01 or QS-03 row; PG-011 › *Item dan pemenuhan* | the same | the same | the same |
| PG-026 | *Gudang* › *Stok Opname*; *Pekerjaan* › QS-18 row | absent — `stock.count` | the same as Laras | the same as Laras |
| PG-027 | *Gudang* › *Lainnya* › *Penyesuaian & Kondisi*; PG-019's actions | absent — `stock.condition`, `stock.adjust` | the same as Laras | the same as Laras |
| PG-028 | *Gudang* › *Lainnya* › *Retur ke Pemasok*; PG-016 | absent — `purchase_return.record` | absent — `purchase_return.record` | the same as Laras |
| PG-029 | *Beranda* › QS-22 or QS-18 decision row; *Gudang* › *Lainnya* | absent — `stock.resolve_unattributed_evidence` | absent — `stock.resolve_unattributed_evidence` | *Gudang* › *Lainnya* › *Selisih & Temuan*, the evidence resolution only (PJ-07) |
| PG-030 | *Gudang* › *Lainnya* › *Pengiriman*; *Pekerjaan* › QS-06 row; PG-011 › *Pengiriman* | the same | the same | the same |
| PG-031 | PG-011's primary action "Catat Pengiriman"; *Pekerjaan* › QS-06 row; PG-030 › shipment | absent — `delivery.record` | the same as Laras | the same as Laras |
| PG-032 | PG-011 › *Kirim langsung* | absent — `dropship.confirm` | absent — `dropship.confirm` | PG-011 › *Kirim langsung* |
| PG-033 | PG-011 › *Jasa* | absent — `handover.record` | absent — `handover.record` | PG-011 › *Jasa* |
| PG-034 | PG-011 › *Pengiriman* › a delivery › "Retur dari Klien"; PG-030 › a delivered record | absent — `sales_return.record` | absent — `sales_return.record` | the same as Laras |
| PG-035 | *Menu lengkap* › *Dokumen*; PG-011 › "Kesiapan dokumen" › "Semua dokumen" | the same | the same | the same |
| PG-036 | PG-035 › document; PG-011 › "Kesiapan dokumen" › item; *Pekerjaan* › QS-08 or QS-19 row | the same | the same, payloads of the operational family only (PJ-15) | the same as Laras |
| PG-037 | *Dokumen* › *Penawaran* | the same | the same, without values | the same |
| PG-038 | *Dokumen* › *Bukti*; "Buat" › *Unggah Dokumen* | the same | *Dokumen* › *Bukti* | the same as Laras |
| PG-039 | *Dokumen* › *Dokumen Administrasi* | the same | the same, read only | the same |
| PG-040 | *Dokumen* › *Kasus Koreksi*; *Pekerjaan* › QS-07 row; PG-011 › *Retur dan koreksi* | the same | the same | the same |
| PG-041 | *Menu lengkap* › *Keuangan* › *Invoice* | the same | absent — `finance.view` | the same |
| PG-042 | PG-011 › *Invoice* › "Buat invoice"; PG-041 › "Buat Invoice" | the same; *Pekerjaan* › QS-15 row › PG-011 › "Buat invoice" | absent — `invoice.issue` | the same as Dimas |
| PG-043 | PG-041 › row; PG-011 › *Keuangan*; *Pekerjaan* › QS-09 row › PG-044 › row | the same | absent — `finance.view` | the same |
| PG-044 | *Beranda* › QS-10 or QS-11 decision row; *Keuangan* › *Piutang* | *Keuangan* › *Piutang*; *Pekerjaan* › QS-09, QS-10 or QS-11 row | absent — `finance.view` | the same as Dimas |
| PG-045 | *Keuangan* › *Pembayaran*; *Pekerjaan* › QS-12 row | the same | absent — `finance.view` | the same |
| PG-046 | "Buat" › *Pembayaran*; PG-011 › *Pembayaran*; PG-044 › "Catat Pembayaran" | the same | absent — `payment.record` | the same |
| PG-047 | *Keuangan* › *Potongan*; *Pekerjaan* › QS-13 row | absent — `settlement.record` | absent — `settlement.record` | the same as Laras |
| PG-048 | *Keuangan* › *Lainnya* › *Kredit Pelanggan*; *Pekerjaan* › QS-12 row | the same | absent — `finance.view` | the same |
| PG-049 | *Keuangan* › *Lainnya* › *Pengeluaran & Kas Lain* | the same | absent — `finance.view` | the same |
| PG-050 | *Keuangan* › *Lainnya* › *Mutasi Bank*; *Pekerjaan* › QS-12's bank-line row | absent — `bank_statement.record` | absent — `finance.view`, `bank_statement.record` | the same as Laras |
| PG-051 | *Menu lengkap* › *Laporan* | the same, the reports of his read classes | the same, the stock report | the same, every report but the consolidated one |
| PG-052 | *Laporan* › *Laporan Gabungan*; *Beranda* › "Rincian angka" | absent — `reports.consolidated` | absent — `reports.consolidated` | absent — `reports.consolidated` |
| PG-053 | *Laporan* › *Ekspor Saya* | absent — `reports.export` | absent — `reports.export` | the same as Laras |
| PG-054 | *Menu lengkap* › *Data Master* › *Klien* | the same | the same | the same |
| PG-055 | *Data Master* › *Pemasok* | the same | the same | the same |
| PG-056 | *Data Master* › *Produk & Jasa* | the same | the same | the same |
| PG-057 | *Data Master* › *Lokasi Rak* | the same | the same | the same |
| PG-058 | *Data Master* › *Impor Data Awal* | absent — `import.prepare` | absent — `import.prepare` | the same as Laras, preparing only (`import.commit` is OWNER_ONLY) |
| PG-059 | *Menu lengkap* › *Pengaturan* › *Perusahaan* | absent — `companies.manage` | absent — `companies.manage` | absent — `companies.manage` |
| PG-060 | *Pengaturan* › *Pengguna & Akses*; *Beranda* › the security notice | absent — `users.manage`, `permissions.manage` | absent — `users.manage`, `permissions.manage` | absent — `users.manage`, `permissions.manage` |
| PG-061 | *Pengaturan* › *Penomoran Dokumen* | absent — `companies.manage` | absent — `companies.manage` | absent — `companies.manage` |
| PG-062 | *Pengaturan* › *Pajak* | absent — `tax.configure` | absent — `tax.configure` | absent — `tax.configure` |
| PG-063 | *Menu lengkap* › *Aktivitas & Keamanan* › *Riwayat Aktivitas* | absent — `audit.view` | absent — `audit.view` | absent — `audit.view` |
| PG-064 | *Aktivitas & Keamanan* › *Log Keamanan*; *Beranda* › the security notice | absent — `security_log.view` | absent — `security_log.view` | absent — `security_log.view` |
| PG-065 | the account menu › *Akun Saya* | the same | *Lainnya* › *Akun* › *Akun Saya* | the same as Laras |

**Pages reached in the shell (PG-005–PG-065, 61 pages):** Laras 61; Dimas 36; Bayu 30; the full Admin 52 — every page but the nine that only OWNER_ONLY capabilities open: PG-006 (`review.view`), PG-008, PG-052 and PG-059–PG-064. The counts equal IA_PAGE_INVENTORY §8's. Every persona reaches what its grants open and nothing else: each "absent" cell names a capability §1 of IA_PAGE_INVENTORY does not give that persona.

### 13.7 Variations that change navigation

| Variation | What changes | Sources |
| --- | --- | --- |
| An Admin without `finance.view` — Bayu | *Pekerjaan*'s group *Tagihan dan pembayaran* does not exist; *Keuangan* is absent from *Menu lengkap*; on the journey, *Posisi keuangan* and [QS-15](../03-workflows/WORKFLOWS/s10-14-signals-decisions-traceability.md#10-derived-signals-and-action-queue)'s money items are absent; documents of the finance family show their metadata without payload ([PJ-15](../05-security/PERMISSIONS_MATRIX.md#73-special-projections)) | this document §9; R1 b) and e); PJ-13; PJ-15 |
| An Admin without `stock.view` | every pooled view is denied — *Gudang*'s stock pages, S1 search facets and props, availability on the journey —, and with it every capability that needs it (RG-06): reservation, receiving, dispatch, counting; such an Admin still sees the S2–S4 records of the families granted, never another company's (the AC-13 resolution) | PERMISSIONS_MATRIX §3 (AC-13 resolution), §4, RG-06; PJ-01 |
| One company in scope — Bayu | no company filter; a list names the company once in its header and its rows carry no company label | §7; CS-02 |
| Several companies in scope — Dimas | the filter "Tampilkan: Semua perusahaan" on every list and on *Pekerjaan*; every row carries its company code; every count is per company — "ARJ 4 · BTN 2" —, never summed | CS-08; [PF-15](../06-api-performance/PERFORMANCE.md#4-pagination); PF-41; R1 b) |
| The Owner's OWNER_ONLY areas — Laras | *Beranda* with *Gabungan* (`reports.consolidated`), *Tinjauan* (`review.view`), *Aktivitas & Keamanan* (`audit.view`, `security_log.view`), *Pengaturan* (`companies.manage`, `users.manage`, `permissions.manage`, `tax.configure`), *Laporan Gabungan*, the attribution on *Selisih & Temuan* (`stock.resolve_unattributed`, `stock.attribute_surplus`), "Selesaikan Paksa" in *Tindakan Lain* (`projects.force_complete`), the write-off on an invoice (`receivables.write_off`), the requirement's Owner controls (OD-05, OD-06) and the import commit (`import.commit`); for an Admin each is absent, and its address answers the not-found page | PERMISSIONS_MATRIX §9 (OD-01–OD-16); RG-02; UXN-18; AZ-11 |
| A variant a check needs — Bayu without `stock.reserve` | *Reservasi* and QS-01's and QS-03's "Reservasi" actions are absent; at receipt the reservation offer is absent and the receipt is recorded normally (S2-R06) — a recorded simulation scenario, not a persona | S2-R06; [SP-19](../00-governance/DECISION_LOG.md#dir-063-dir-064-obs-024-and-tech-032--p7-ux-re-baseline-s2-0-acceptance-s2-1-authorization-baseline-and-stage-2-documents); [SIMULATION_REGISTER](stage2/SIMULATION_REGISTER.md) |

### 13.8 Paths to the frequent and critical tasks

From the landing page to the task's first page, for each task of the [persona task analysis](evidence/P7_REBASELINE_GATE.md#persona-task-analysis); a step is one choice of an entry, a row or an action.

| Persona | Task (persona task analysis) | Path | Steps |
| --- | --- | --- | --- |
| Laras | the period position and what needs attention | *Beranda* (PG-006) | 0 |
| Laras | review recorded activity | *Tinjauan* (PG-008) | 1 |
| Laras | look up a project, invoice or receivable, often on the phone | *Cari* › the record (PG-009 › PG-011, PG-043 or PG-044) | 2 |
| Laras | reports per company and consolidated | *Menu lengkap* › *Laporan* (PG-051) or *Laporan Gabungan* (PG-052); *Beranda* › "Rincian angka" | 2–3 |
| Laras | accounts, grants and credential links | *Menu lengkap* › *Pengaturan* › *Pengguna & Akses* (PG-060) | 3 |
| Laras | the attribution of unexplained losses and count surpluses | *Beranda* › the decision row › PG-029 | 1 |
| Laras | write-off | *Beranda* › the [QS-10](../03-workflows/WORKFLOWS/s10-14-signals-decisions-traceability.md#10-derived-signals-and-action-queue) row › PG-044 › the invoice (PG-043) › "Hapuskan Piutang" | 3 |
| Laras | Force Complete | *Proyek* › row › PG-011 › *Tindakan Lain* › "Selesaikan Paksa" (PG-012) | 4 |
| Laras | numbering start and seed; tax configuration | *Pengaturan* › *Penomoran Dokumen* (PG-061) or *Pajak* (PG-062) | 3 |
| Laras | the opening-import commit | *Data Master* › *Impor Data Awal* (PG-058) | 3 |
| Laras | acting on a security notice | *Beranda* › the notice › PG-060 or PG-064 | 1 |
| Laras | an overdue project | *Beranda* › the QS-23 row › PG-011 (`DIR-077` CA-10) | 1 |
| Dimas | a quotation with many lines | *Proyek* › row › PG-011 › *Kesepakatan* (PG-014) | 3 |
| Dimas | a purchase with lines and charges | "Buat" › *Pembelian* (PG-017); *Pekerjaan* › QS-02 row (prefilled) | 1–2 |
| Dimas | a reservation | *Pekerjaan* › QS-01 or QS-03 row › "Reservasi" (PG-025) | 1 |
| Dimas | an invoice | *Pekerjaan* › *Penyelesaian proyek* row (QS-15, "confirmed value not yet invoiced") › PG-011 › "Buat invoice" (PG-042) | 2 |
| Dimas | billing | *Pekerjaan* › QS-09 row › PG-044 *Belum Ditagihkan* › PG-043 › "Tagihkan" | 3 |
| Dimas | a payment with its applications | "Buat" › *Pembayaran* (PG-046); PG-011 › *Pembayaran* › "Catat Pembayaran" | 1 |
| Dimas | documents and *Dokumen Administrasi* | PG-011 › "Kesiapan dokumen" › an item (PG-036) | 2–3 |
| Dimas | project setup | "Buat" › *Proyek* (PG-013) | 1 |
| Dimas | project completion | *Pekerjaan* › *Penyelesaian proyek* row › PG-011 › PG-012 | 2 |
| Dimas | a correction | the record it corrects › its correction action ([ADMIN_FLOW](ADMIN_FLOW/README.md#admin-and-owner-flows--interaction-model) §11) | from the record |
| Dimas | confirming a duplicate warning | inside PG-046, the warning's second submission (IP-05) | 0 from the form |
| Bayu | receiving | *Pekerjaan* › "Berikutnya" › "Mulai Terima Barang" (PG-022) | 1 |
| Bayu | dispatch | *Pekerjaan* › a QS-24 row › "Keluarkan Barang" (PG-024) | 1 — OQ-03's option (b), decided (`DIR-077` CA-07) |
| Bayu | checking stock and racks | *Cari* › a scanned or typed code (PG-019) | 2 |
| Bayu | delivery records | *Pekerjaan* › QS-06 row › "Catat Pengiriman" (PG-031) | 1 |
| Bayu | stock opname | *Pekerjaan* › QS-18 row (PG-026); *Lainnya* › *Menu lengkap* › *Gudang* › *Stok Opname* | 1–4 |
| Bayu | condition changes and losses | *Cari* › PG-019 › "Ubah Kondisi" or "Catat Penyesuaian Stok" (PG-027) | 3 |

Every frequent task of the Admin at the desk and of the warehouse persona has a path of at most three steps from *Pekerjaan*, "Buat" or the project; dispatch now takes one step, through QS-24 in *Pekerjaan* (`DIR-077` CA-07; [QB-10](../06-api-performance/PERFORMANCE.md#6-query-budgets) as amended).

## 14. The five DECIDE-IN-STAGE-2 items, resolved

R1 kept five Stage 1 notes for Stage 2 ([revised non-loss map](evidence/P7_REBASELINE_GATE.md#revised-screens-scr-01scr-60)): three about content the authentication amendment adds — [SCR-02](#81-system-account-and-beranda), SCR-03, SCR-57 — and two about a location — SCR-32, SCR-33. Stage 3 takes the resolution IA_PAGE_INVENTORY §7 proposed for each (D-IA-22); none needs a rule the sources do not give, so none is an Owner question.

### 14.1 SCR-02 — the credential link and the second-factor enrolment

- **Question.** Whether a high-risk account enrols its second factor in the same journey as setting its password ([H7-15](../05-security/SECURITY.md#p7--presentation-only); [GAP-044](../00-governance/GAP_REGISTER.md#triage)).
- **Options.** (a) The credential-link page itself opens the enrolment after the password is set. (b) The person sets the password on the credential-link page (PG-003), signs in with it (PG-001), and the password step leads a high-risk account without TOTP into the enrolment-only session (PG-004), which reaches only the enrolment, the password confirmation and logout until a device is confirmed; the recovery codes are shown once; then the landing page.
- **Resolution (Stage 3).** (b), one continuous journey across the three pages. (a) is not open: "a credential link never creates a session and never bypasses TOTP" (AU-20), and the enrolment-only session begins only "after a correct password" (AU-18).
- **Grounds.** AU-03; AU-12; AU-16; AU-18; AU-19; AU-20; H7-15. The pages, steps and copy are designed in [AUTH_JOURNEYS](stage2/AUTH_JOURNEYS.md).

### 14.2 SCR-03 — *Akun Saya*

- **Question.** How AU-13's self-service grows with TOTP, the recovery codes and the Google link (H7-15; H7-16; H7-18).
- **Options.** (a) One page (PG-065) with sections — the account's profile, role, capabilities and companies, read-only; sign-in and security — the password, TOTP, the recovery codes and the Google link; its own events and last login. (b) A separate page for each function.
- **Resolution (Stage 3).** (a): every function of AU-13 on one page, each sensitive action behind the step-up (AU-15) — enrolling or replacing TOTP, viewing and regenerating the recovery codes with a current code (AU-19), disabling TOTP only for an account that is not high-risk (AU-16; AU-18), linking and unlinking Google (AU-23; AU-24) —, and the first-login notice of AU-13 and H7-18. Under the Owner's CA-08 the account's display language joins AU-13's self-service list (SECURITY, `DIR-077`): *Bahasa tampilan* sits in the account menu and in this page's account section, kept in `users.locale` ([LOCALIZATION](LOCALIZATION.md)).
- **Grounds.** AU-13; AU-15; AU-16; AU-19; AU-20 a and f; AU-23; AU-24; [PJ-17](../05-security/PERMISSIONS_MATRIX.md#73-special-projections); H7-15; H7-16; H7-18. Designed in [AUTH_JOURNEYS](stage2/AUTH_JOURNEYS.md).

### 14.3 SCR-32 — *Retur dari Klien*, its cross-project entry

- **Question.** Whether a sales return has a cross-project entry — *Proyek* › *Pengiriman* or *Kasus Koreksi*, since a return opens a case.
- **Options.** (a) From the project only — *Rincian proyek* › *Pengiriman* and *Tindakan Lain*. (b) Also from the cross-project *Pengiriman* list (PG-030), on a delivered record. (c) Also from *Kasus Koreksi* (PG-040), as "a new return".
- **Resolution (Stage 3).** (a) with (b): a correction always starts from the record it corrects, and a sales return starts from the delivery — "Retur dari Klien" ([ADMIN_FLOW](ADMIN_FLOW/README.md#admin-and-owner-flows--interaction-model) §11, CM-10–CM-13; J-FUL-05) —, which the journey and the cross-project *Pengiriman* list both show. *Kasus Koreksi* lists the case the return opens and starts none (§5).
- **Grounds.** ADMIN_FLOW §11; [WF-FUL-05](../03-workflows/WORKFLOWS/s05-04-05-fulfillment-documents.md#wf-ful-05--sales-return-after-delivery-both); §5; `sales_return.record`.

### 14.4 SCR-33 — *Kasus Koreksi*, its cross-project home

- **Question.** Where the cross-project list of correction cases lives, since cases also arise from purchases and stock (SCR-26), not only from projects.
- **Options.** (a) *Menu lengkap* › *Dokumen* › *Kasus Koreksi* (PG-040), beside the documents, as *Pekerjaan*'s group *Dokumen dan kasus* pairs them. (b) A module of its own in *Menu lengkap*, as §5 had an area *Kasus Koreksi*. (c) Under *Proyek*, as Stage 1 prototyped it — narrower than the cases' origins.
- **Resolution (Stage 3).** (a), keeping R1's module list unchanged; the open cases also appear in *Pekerjaan* (QS-07) and in the journey's *Retur dan koreksi* condition, and each case is reached from the record that opened it.
- **Grounds.** §5; R1 a) and b); QS-07; PJ-16; XL-3.

### 14.5 SCR-57 — *Pengguna & Akses*, the Owner's account screens

- **Question.** Where the Owner's account screens of H7-17 live — each account's TOTP status and reset, the Google unlink and its history.
- **Resolution (Stage 3).** In the account's detail on PG-060: its TOTP status and enrolment time; "reset TOTP" — for an account without TOTP, a password reissue — with the step-up, a reason and the out-of-band RESET link shown once; the RESET link that a role assignment, a grant or an activation issues when it makes an account without TOTP high-risk; the Google unlink with its reason; the account's Google link history — never a secret, a code or a provider subject.
- **Grounds.** AU-12; AU-15; AU-18; AU-20 c and d; AU-24; PJ-17; H7-17. Designed in [AUTH_JOURNEYS](stage2/AUTH_JOURNEYS.md).

## 15. Counts

Derived by script from §§8, 9 and 13 when this draft was written, and checked again for gate PC-2 (TECH-037):

| Count | Value |
| --- | --- |
| Pages | **65** — PG-001–PG-065: 4 outside the shell (PG-001–PG-004) and 61 in the shell (PG-005–PG-065), the system page PG-005 among them; each with its NAV- contract and its view's ROUTE- of the same number |
| SCR coverage | **60 of 60** — every SCR-01–SCR-60 maps to at least one page (§8.7); eight map to two or three pages: SCR-01, SCR-02, SCR-05, SCR-14, SCR-21, SCR-22, SCR-38 and SCR-39 |
| Pages without an SCR | **0** |
| Pages per persona, in the shell | **Laras 61**; **Dimas 36**; **Bayu 30**; the full Admin **52** — as [NAVIGATION_MAP](stage2/NAVIGATION_MAP.md) §2 counts them; the CP-A signals add no page |
| Signals placed | **24 of 24** — QS-01–QS-24 (§9.1), QS-23 the Owner's alone and QS-24 in *Pekerjaan*'s goods group (`DIR-077`) |
| Glossary | **212** entries — GL-001–GL-191 carried, GL-192–GL-212 added (§3.8) |


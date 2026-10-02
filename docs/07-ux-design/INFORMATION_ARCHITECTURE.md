# Information architecture

Status: APPROVED | Updated: 2026-10-02 | Owner: Planning

Approval: [APPR-008](../00-governance/DECISION_LOG.md#appr-008--p7-ux-information-architecture-and-design-system-approved), explicit conditional Owner approval on 2026-10-01 (DIR-033) of this document as committed in the P7 finalization checkpoint; the approved file hash, the verified conditions and the exclusions are recorded there. Approval changes lifecycle only, not implementation authorization.

Authority: P7 — UX, Information Architecture & Design System, authorized by the Owner's fast-track directive [DIR-033](../00-governance/DECISION_LOG.md#dir-033-obs-012-and-tech-022--p7-authorization-entry-baseline-and-ux-documentation) (twenty-ninth source record). This document owns **navigation truth**: the Indonesian UI glossary, the product map, the application shell and global navigation on phone and desktop, the company-context model, the screen inventory, the placement of the derived signals and queues, the search, lookup and scanner entry points and the page-addressing conventions. Interaction truth — commands, sessions, journeys, corrections, messages — is owned by [ADMIN_FLOW](ADMIN_FLOW.md); visual rules by [DESIGN_SYSTEM](DESIGN_SYSTEM.md); phase evidence by [P7_QUALITY_GATE](evidence/P7_QUALITY_GATE.md).

**Anti-duplication contract:** business meaning stays in [V1_SCOPE](../01-product/V1_SCOPE.md), [DOMAIN_MODEL](../02-domain/DOMAIN_MODEL.md), [BUSINESS_RULES](../02-domain/BUSINESS_RULES.md) and [WORKFLOWS](../03-workflows/WORKFLOWS/README.md); authorization truth in [PERMISSIONS_MATRIX](../05-security/PERMISSIONS_MATRIX.md) and [SECURITY](../05-security/SECURITY.md); mechanisms in [CONCURRENCY_IDEMPOTENCY](../06-api-performance/CONCURRENCY_IDEMPOTENCY/README.md) and [PERFORMANCE](../06-api-performance/PERFORMANCE.md). This document cites their identifiers and never restates, relaxes or extends a rule. A screen row names the capability and projection that govern it; it grants nothing — the server authorizes every request (AZ-01), and no screen shows a field its projection does not allow.

**Boundary:** documentation only — no route file, component, configuration or image. Screens are specified at the depth a later build unit needs; fields the domain documents define are referenced, not listed again. P8 owns the tests and P11 the build units ([ADMIN_FLOW §16](ADMIN_FLOW.md#16-handoff-obligations)).

## 1. Conventions

- **Identifier families defined here:** UXN (derived navigation requirements), D-IA (information-architecture decisions), GL (glossary entries), SCR (screens). UXI, IP, J, MSG and UXS are defined in ADMIN_FLOW; UXV, PT, KB, SLOP and D-DS in DESIGN_SYSTEM.
- **Labels** in italics or quotation marks are end-user wording in Bahasa Indonesia; every label used anywhere in the three P7 documents is a glossary entry of section 3 or is formed from its entries.
- **Audience notation** is that of PERMISSIONS_MATRIX §1 — ALL, SV, PV(c), FV(c), CV(c), EV(c), OWN, SELF — and a capability is always a code of its §4.
- **Context tags** are those of WORKFLOWS §1: `[floor]`, `[desk]`, `[both]`.
- **Phone** and **desktop** name the two designed patterns of [DESIGN_SYSTEM §6](DESIGN_SYSTEM.md#6-layout-grid-and-breakpoints); neither is derived from the other by shrinking or stretching.
- **Examples** — company codes such as ARJ and BTN, names and numbers — are fictional and illustrative ([DESIGN_SYSTEM §1](DESIGN_SYSTEM.md#1-conventions)).

## 2. Derived navigation requirements

Each row is derived from repository truth and is the first link of the traceability chain of [P7_QUALITY_GATE](evidence/P7_QUALITY_GATE.md#desainpakai-exploration).

| ID | Requirement | Repository source | Home |
| --- | --- | --- | --- |
| UXN-01 | Every end-user label, navigation item, message and status is natural, professional Bahasa Indonesia from one glossary; client and legal names, codes, SKUs and user-authored content are data and are never translated | V1_SCOPE language and experience contract; CAP-14; REF-052; AC-14 | [§3](#3-glossary) |
| UXN-02 | The project detail is the operational hub: overview, items, quotation, procurement, fulfilment, delivery, documents, billing, payments, administrative requirements and history | OB §11; CAP-04; AC-04 | [§5](#5-product-map), SCR-10 |
| UXN-03 | Navigation follows the business flow, not the database structure; every V1 area of CAP-01–CAP-18 is reachable | OB §11; V1_SCOPE MUST SHIP | [§5](#5-product-map), [§6](#6-application-shell-and-global-navigation) |
| UXN-04 | Phone navigation and desktop navigation are designed separately; a desktop layout that is merely shrunk is a failure | V1_SCOPE language and experience contract and P7 handoff requirements; OB §22; AC-14; GAP-020 | [§6](#6-application-shell-and-global-navigation) |
| UXN-05 | After sign-in the Owner lands on the consolidated and per-company dashboard, an Admin on the action queue across granted companies | WF-ACC-01; CAP-17; REF-007; REF-008 | [§9](#9-dashboards-and-queues) |
| UXN-06 | Company context: scope is the granted companies; a working filter narrows views only; every command names its target record's company; a selected company never substitutes for authorization | WF-ACC-01; CS-01–CS-03; AZ-01 | [§7](#7-company-context-model) |
| UXN-07 | A list across companies labels each row with its company; no figure sums S3 values of several companies for an Admin; consolidated figures are the Owner's | CS-08; PF-25; PF-41; CALC-12 | [§7](#7-company-context-model), [§9](#9-dashboards-and-queues) |
| UXN-08 | Where a physical row relates to a company outside scope, only the relation marker "perusahaan lain" appears — never a name, code or other identifier | PJ-04; PJ-06; H7-02 | [§7](#7-company-context-model) |
| UXN-09 | Navigation entries and controls follow the account's capabilities as a hint only; the server decides | AZ-01; H7-03; WS-07 | [§6](#6-application-shell-and-global-navigation) |
| UXN-10 | A page outside scope answers exactly like a nonexistent page; a page in scope without the capability is forbidden | AZ-11; WS-10; H7-08 | [§11](#11-page-addressing), [ADMIN_FLOW IP-21](ADMIN_FLOW.md#ip-21--denial-and-not-found) |
| UXN-11 | Addresses carry public identifiers only; nothing sensitive appears in a title, address, notification or history entry | DATABASE §16; H7-10; DP-01; PJ-23 | [§11](#11-page-addressing) |
| UXN-12 | Every signal QS-01–QS-22 has a place, an audience from the projection rules, an entry point, an action link and an empty state; no queue is stored | WORKFLOWS §10; PJ-13; PF-24; DATABASE §3 | [§9](#9-dashboards-and-queues) |
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
| UXN-23 | A shared master shows its S0 identity to everyone and its S2 relations by capability; related records appear only through scope-filtered lists, and no count reveals a relationship outside scope | PJ-10; CS-06; CS-07 | SCR-50–SCR-52 |
| UXN-24 | Settings, master-data and history areas exist; a record's timeline follows the audit projection; workspace audit search is the Owner's | PJ-11; RD-10; OB §23 | [§5](#5-product-map) |
| UXN-25 | The Owner review queue is designed only after the GAP-034 settlement, shows every source of the signal register and offers action only through normal commands | PERMISSIONS_MATRIX §10; HO-38; SECURITY LG-02 (`TECH-022`) | [§9](#9-dashboards-and-queues) |
| UXN-26 | Nothing V1_SCOPE excludes or defers is navigable: no supplier comparison, institution module, SIPLAH module, ledger, custom-role designer or template editor | V1_SCOPE OUT OF SCOPE and DEFERRED | [§5](#5-product-map) |

## 3. Glossary

The single home of the Indonesian UI vocabulary. **Label** is what the interface shows — its words, not its letter case, which [DESIGN_SYSTEM §16](DESIGN_SYSTEM.md#16-copy-and-formatting) sets per element; **Avoid** lists wording that must not replace it. Document type names are the approved names of V1_SCOPE and keep their legal meaning. Statuses and messages are formed from these entries; their visual form is in [DESIGN_SYSTEM §12](DESIGN_SYSTEM.md#12-status-outcome-and-state-patterns), and message patterns in [ADMIN_FLOW §14](ADMIN_FLOW.md#14-message-catalogue).

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
| GL-104 | DOC-02 | Purchase Order (PO) | approved name | Pesanan Pembelian |
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

## 4. Design decisions

Each decision is Level 1 under DIR-033 §13. "Exploration" names the DesainPakeAI brief whose alternatives were compared ([P7_QUALITY_GATE](evidence/P7_QUALITY_GATE.md#desainpakai-exploration)); a decision without one follows from repository rules alone.

| ID | Decision | Alternatives rejected | Grounds · exploration |
| --- | --- | --- | --- |
| D-IA-01 | Desktop navigation is a sidebar in business-flow order, one level of groups that open in place, collapsible to an icon rail, beside a slim top bar ([§6](#6-application-shell-and-global-navigation)) | "Pusat proyek" — purchases, deliveries and documents as tabs inside Proyek: it suggests that every purchase, delivery and document belongs to one project, which a purchase for stock contradicts (WF-PUR-01), and puts cross-project desk work one level deeper; "Bilah atas" — a horizontal menu: it fits only from 1,240 px, so common laptop widths fall back to a drawer, and a phone needs three taps where the others need two | UXN-03, UXN-22; OB §11 · DPB-02 |
| D-IA-02 | The company working filter sits in the header of each list and of Beranda, never in the shell; an account with one company sees no filter at all | a global filter in the top bar beside the account: it reads as a workspace switch, as if forms inherited the chosen company — the reading CS-03 forbids; a "Perusahaan: …" label in the top bar for a one-company account: it looks like a context badge | CS-01–CS-03; UXN-06 · DPB-02 |
| D-IA-03 | The phone has a bottom bar of five destinations — Beranda, Proyek, Gudang, Cari, Lainnya — and the rest in the *Lainnya* sheet, grouped as the sidebar is | a menu drawer only: every destination one tap further and out of thumb reach; a bottom bar with Keuangan and a centre "Pindai": a second entry doing what *Cari* does with a scanner, and warehouse work two taps away | UXN-04, UXN-21; GAP-020 · DPB-02, DPB-09 |
| D-IA-04 | One search entry, *Cari*, takes typed and scanned codes alike: exact lookup first, names from three characters | separate *Cari* and *Pindai* entries: the same exact lookup twice, and a choice the user should not have to make before scanning | PF-19; QB-06; UXN-16, UXN-17 · DPB-02 |
| D-IA-05 | Session notices that need no answer appear in a full-width strip under the top bar; the idle warning, which needs one, is a dialog ([ADMIN_FLOW §6](ADMIN_FLOW.md#6-session-expiry-re-authentication-and-unsent-input)) | inline text in the top bar: crowded, and cut below 1,280 px | H7-05; AU-07; AU-08; WCAG 2.2.1 · DPB-02 |
| D-IA-06 | No navigation entry carries a badge or a count | queue badges on entries: a count across companies is the total CS-08 forbids for an Admin, and a count per company does not fit an entry; the queues live on Beranda | PF-15; CS-08 · DPB-02, DPB-04 |
| D-IA-07 | The Owner's Beranda is a stack of signal groups — rows of condition, company counts, subject, age and link — in the same order on phone and desktop, with the view switch *Tampilan: Gabungan* or one company and the period figures as a plain table with the *Gabungan* row last | "Matriks perusahaan": only six of twenty signals fit the matrix, the other fourteen sit behind a disclosure, and the matrix needs 1,140 px; "Dua kolom": columns of uneven height, operations reduced to a strip of links, and finance one tap away on a phone | UXN-05, UXN-07, UXN-12, UXN-13; CS-08; PF-24 · DPB-03 |
| D-IA-08 | An Admin's Beranda groups its queue by kind of work, each row labelled with its company; a group the account has no capability for is absent | per company in columns: the urgency of two companies is hard to compare, the company code repeats under its own heading, and a one-company account falls back to another layout; one merged list: it reorders while its groups load, and sorts across signals by age — an order no signal statement gives | UXN-05, UXN-07, UXN-12; PF-24; PF-34 · DPB-04 |
| D-IA-09 | The project detail (SCR-10) shows its facts, its next step and a strip of derived facts per part, then the parts as tabs on desktop — the tabs that do not fit move, in order, into a trailing "Bagian Lain" menu while the selected part stays visible —; on a phone the parts are a list of rows, each with its derived fact, that open one part at a time | "Rel alur": a 288 px rail of parts beside the sidebar leaves too little width for the item and document tables; "Satu halaman": every part on one page strains the three deferred groups of QB-02 and repeats actions | UXN-02, UXN-14; OB §11; QB-02; PF-34 · DPB-05 |
| D-IA-10 | *Dokumen Administrasi* is organized as the project's checklist — each requirement with its derived state, its linked documents and uploads, and a preview beside; the *Dokumen* list is grouped by document type with each version chain indented | "Dua panel": one requirement at a time and three columns at desktop width; a row count on the document list: lists carry no totals | UXN-15; BR-ADM-01; FL-07; PF-15 · DPB-11 |
| D-IA-11 | The Owner review queue (SCR-07) has two parts: the open conditions as a table above, and the activity as one chronological stream within a time window | open conditions as dropdown chips: they read as filters and hide their detail; grouping by actor or by record: it breaks the chronological order the index serves (IX-13) and reads as monitoring of staff | UXN-12, UXN-25; PERMISSIONS_MATRIX §10; QS-14 · DPB-15 |
| D-IA-12 | A report shows one section per company — its summary figures above its table — with company and date range required in the filter bar | filters in a side panel: they narrow the table until names wrap to four lines; a whole-report total row under a list that continues by "Muat Lagi": it reads as the total of the rows loaded | UXN-07, UXN-20; PF-27; CS-08 · DPB-13 |
| D-IA-13 | The numbering area lists each company's numbered types by what they need — *Perlu Tindakan Owner*, *Tertahan*, *Sudah Dimulai* — with the reason, the action and the periods, counters, schemes and batches under "Rincian"; the company page shows a derived readiness list, missing items first | a company-by-type matrix: fifteen numbered types per company do not fit, and details sit below the grid; tabs per type: no overview across types; ordered onboarding steps: they imply a sequence V1 does not impose | NM-03; HO-39; UXN-18 · DPB-14 |
| D-IA-14 | Master records open as their own addressable pages; their lists are search-first, and creating a client starts by searching the existing ones | details in a side panel: it squeezes the list and gives the record no address of its own | UXN-11, UXN-16, UXN-23; PJ-10 · DPB-06 |
| D-IA-15 | Receivables are a list of invoices — company code, number, client, remainder, due state — with the selected invoice's detail beside it on desktop and as its own page on a phone | aging buckets with counts above the list: counts across companies for an Admin, and aging belongs to the receivables report; a client statement with a running balance: a figure no calculation defines and a keyset list cannot carry | UXN-07, UXN-15; CS-08; PF-15; IX-11 · DPB-12 |
| D-IA-16 | Dispatch (*Barang Keluar*, a warehouse record that changes stock) and delivery (*Pengiriman*, the client-side record that never does) stay two screens, linked to each other | one shipment screen with a stepper from dispatch to delivery: it merges two records of different meaning into one lifecycle | GL-010, GL-013; WF-INV-03; WF-FUL-02 · DPB-10 |
| D-IA-17 | Stock is organized by product: the list shows the four quantities of every product and expands to its racks, reservations and lots; the product's detail page (SCR-19) gathers its quantities, pending line, advice, racks, reservations, lots, serials and latest movements; on a phone the list opens the detail page | organized by rack: a product's warehouse-wide quantities one tap away, products repeated across racks, and a rack total that differs from its lots during a pending case invites misreading — the rack filter keeps that view available; a list beside a detail pane as the only view: the list compares no quantity but *Tersedia* | UXN-07, UXN-08, UXN-15; PJ-01–PJ-04; PJ-07; PJ-22; CALC-01 · DPB-08 |

## 5. Product map

Every V1 area as the users meet it, in the order of the business flow (OB §11). Each entry exists for an account only when one of its screens is open to the account's capabilities and companies (UXN-09); an Owner-only entry never appears for an Admin (UXN-18). Nothing excluded or deferred by V1_SCOPE is an entry (UXN-26).

| Area (label) | Entries | Screens | Capabilities served |
| --- | --- | --- | --- |
| **Beranda** | Owner: the consolidated and per-company overview; Admin: *Antrean Tindakan* | SCR-05, SCR-06 | CAP-17 |
| **Proyek** | Daftar Proyek · Penawaran | SCR-09–SCR-14 | CAP-04, CAP-18 |
| **Pembelian** | Daftar Pembelian | SCR-15–SCR-17 | CAP-05 |
| **Gudang** | Stok · Barang Masuk · Barang Keluar · Reservasi · Stok Opname · Penyesuaian & Kondisi · Retur ke Pemasok · Riwayat Stok · Selisih & Temuan (the Owner; an Admin only with `stock.resolve_unattributed_evidence`, for the evidence resolution under PJ-07) | SCR-18–SCR-27 | CAP-03, CAP-06 |
| **Pengiriman** | Pengiriman · Kirim Langsung · Serah Terima Jasa · Retur dari Klien | SCR-28–SCR-32 | CAP-06, CAP-07 |
| **Dokumen** | Dokumen · Bukti · Dokumen Administrasi | SCR-34–SCR-37 | CAP-08, CAP-09 |
| **Keuangan** | Invoice · Piutang · Pembayaran · Potongan · Kredit Pelanggan · Pengeluaran & Kas Lain · Mutasi Bank | SCR-38–SCR-46 | CAP-10, CAP-11, CAP-12 |
| **Kasus Koreksi** | the list of correction cases | SCR-33 | CAP-13 (AC-12) |
| **Laporan** | Laporan · Laporan Gabungan (Owner) · Ekspor Saya | SCR-47–SCR-49 | CAP-11, CAP-17 |
| **Data Master** | Klien · Pemasok · Produk & Jasa · Lokasi Rak · Impor Data Awal | SCR-50–SCR-53, SCR-58 | CAP-02, CAP-03, CAP-15 |
| **Khusus Owner** | Tinjauan Owner · Riwayat Aktivitas · Log Keamanan | SCR-07, SCR-59, SCR-60 | CAP-13, CAP-17 |
| **Pengaturan** (Owner) | Perusahaan · Pengguna & Akses · Penomoran Dokumen · Pajak | SCR-54–SCR-57 | CAP-01, CAP-13 |
| Global | Cari (typed or scanned codes) · Pintasan · Akun Saya · Keluar | SCR-08, SCR-03; [§10](#10-search-lookup-and-scanner-entry-points) | CAP-13, CAP-17 |

The project detail (SCR-10) is the operational hub: its parts — *Ringkasan, Item, Penawaran, Pengadaan, Pemenuhan, Pengiriman, Dokumen, Penagihan, Pembayaran, Dokumen Administrasi, Riwayat* — are the brief's list (OB §11; UXN-02), and every cross-project list above leads back to it. CAP-16 (local backups) is operated by the human operator and has no screen; CAP-14 is this design as a whole. SIPLAH is a project channel with its own fields on the project, never an area (CAP-12; V1_SCOPE OUT OF SCOPE).

Correction entry points are not an area: each correction starts from the record it corrects ([ADMIN_FLOW §11](ADMIN_FLOW.md#11-corrections-and-reversals)); *Kasus Koreksi* lists the cases those corrections open. *Impor Data Awal* sits under Data Master so that an Admin holding `import.prepare` reaches it; its commit is the Owner's (PJ-20; OD-13).

## 6. Application shell and global navigation

The shell is the frame every authenticated page shares (D-IA-01–D-IA-06); its visual form is PT-01 and PT-02 of [DESIGN_SYSTEM](DESIGN_SYSTEM.md#9-patterns). It never shows a business figure, a company's data, a count or a company context.

### 6.1 Desktop and tablet

| Part | Content | Rules |
| --- | --- | --- |
| Sidebar | "MultipleCorp" at the top; then the areas of [§5](#5-product-map) in their order: Beranda · Proyek ▸ · Pembelian · Gudang ▸ · Pengiriman ▸ · Dokumen ▸ · Keuangan ▸ · Kasus Koreksi · Laporan ▸ · Data Master ▸; for the Owner, under the group label *Khusus Owner*: Tinjauan Owner · Riwayat Aktivitas · Log Keamanan · Pengaturan ▸ | A group (▸) opens in place to its entries — one level, never a flyout; the group of the current page is open. The current entry is marked by weight, an accent rule and `aria-current="page"`. The sidebar collapses to an icon rail (Ctrl+B, KB-03), each icon with its accessible name and a tooltip; on tablets it starts collapsed |
| Top bar | the search field "Cari atau pindai kode" with the hint "Ctrl K" (KB-01); on the right "Pintasan" (KB-02) and the account menu — the account's name and role (*Owner* or *Admin Operasional*), with *Akun Saya* and *Keluar* | No company switch, company name, count or notification bell. Scanner input goes to the search field when it has focus ([§10](#10-search-lookup-and-scanner-entry-points)) |
| Notice strip | full width under the top bar; empty and absent unless a notice is due: the absolute session limit (ADMIN_FLOW MSG-34) and a lost connection | The anatomy of PT-14 — the tone's icon, a label, ink text; announced politely to assistive technology; never covers content; the idle warning is a dialog, not a strip (D-IA-05) |
| Content | the page header (PT-03) and the page | The company working filter, where a list has one, is part of the list header (D-IA-02; [§7](#7-company-context-model)) |

```
+-------------------+------------------------------------------------------------+
| MultipleCorp   [<]| [ Cari atau pindai kode           Ctrl K ]  Pintasan  Dimas v|
|                   +------------------------------------------------------------+
| Beranda           | (notice strip, only when a notice is due)                  |
| Proyek          > +------------------------------------------------------------+
| Pembelian         | Proyek                                         [Buat Proyek]|
| Gudang          v | Tampilkan: Semua perusahaan v   Status v   Klien v   Atur Ulang|
|   Stok            | ---------------------------------------------------------- |
|   Barang Masuk    | ARJ/PRJ/2026/0142  ARJ  Pengadaan ATK .. Aktif 01 Okt 2026 |
|   ...             | ...                                                        |
| Pengiriman      > |                          [Muat Lagi]                       |
| ...               |                                                            |
| KHUSUS OWNER      |                                                            |
| Tinjauan Owner    |                                                            |
+-------------------+------------------------------------------------------------+
```

### 6.2 Phone

| Part | Content | Rules |
| --- | --- | --- |
| Top bar | a back arrow on any page below an area's first page; the page's title; the account button (the account's initial) | The title is the screen name, never a business number or name (H7-10) |
| Notice strip | as on desktop, under the top bar | — |
| Bottom bar | Beranda · Proyek · Gudang · Cari · Lainnya — an icon above a label, each target at least 44 px | An entry the account cannot open is absent, never disabled; *Cari* opens the full-screen search, whose field takes typed and scanned codes (D-IA-04); *Lainnya* opens a full-height sheet with every other entry, grouped and ordered as the sidebar, then *Akun Saya* and *Keluar* |
| Focused tasks | a scan step, a full-height form sheet, a confirmation and the re-authentication dialog hide the bottom bar; the task's own action bar takes its place | Leaving a task with unsent input asks first (ADMIN_FLOW §6) |

```
+------------------------------------+
| <  Barang Masuk                (D) |
| (notice strip when due)            |
|------------------------------------|
|  page content                      |
|                                    |
|------------------------------------|
| Beranda Proyek Gudang Cari Lainnya |
+------------------------------------+
```

### 6.3 Entries and capabilities

- An entry appears when at least one of its screens is open to the account's capabilities and companies; it is a hint, and the server decides every request (UXN-09; AZ-01). A group shows only the entries the account can open.
- Owner-only entries — *Khusus Owner*, *Pengaturan*, *Laporan Gabungan* — never appear for an Admin, and their addresses answer the not-found page (UXN-18; AZ-11).
- Nothing in the shell changes with the working filter of a list, and no entry carries a badge or count (D-IA-06).

## 7. Company context model

The company context is a **view**, never an authorization (WF-ACC-01; CS-03). It follows the rules below; the visual form of the filter, labels and marker is PT-04 of [DESIGN_SYSTEM](DESIGN_SYSTEM.md#9-patterns).

| Rule | Presentation | Grounds |
| --- | --- | --- |
| Scope is the companies of the account's active grants; the Owner has every company, active or deactivated | Nothing to choose: the account sees what its scope and capabilities allow, and a list or dashboard covers all of it by default | CS-01; CS-02; RG-03 |
| A working filter narrows what is shown | The control **"Tampilkan: Semua perusahaan"** sits in the header of every list and of Beranda for an account with two or more companies; it opens a checklist of the companies in scope, by name and code. It narrows the per-company branches of the list (PF-41), is intersected with scope by the server (DP-02) and lives in the page address as a whitelisted filter key, so reload and Back keep it; it is not stored anywhere else | PF-41; DP-02; WS-03 |
| One company in scope | No filter. Lists show the company's name once in the page header; rows carry no company label | CS-02 |
| Rows of several companies | Every row carries its company label — the company's short code beside the record number on desktop, before the first line on a phone row — and rows are ordered by their own values (PF-41) | CS-08; UXN-07 |
| No totals across companies for an Admin | A figure that counts or sums S3 values of several companies does not exist on an Admin's screens; a total is given per company — one subtotal row per company, never a grand total. The Owner's consolidated figures are labelled *Gabungan* and appear only on the Owner's consolidated views (SCR-05 period figures, SCR-48) | CS-08; PF-25; CALC-12 |
| A command names its company | The form of a new company-owned root has the required field **"Perusahaan"**, never prefilled from the working filter for an account with several companies; the form of an existing record shows that record's company as a fixed label in its header. Pickers in a form offer only records of that company, except the intentional links of PERMISSIONS_MATRIX §8 | CS-03; CS-04; AZ-05 |
| The filter never authorizes | The server checks the target record's company on every command; changing the filter changes no permission and no form | WF-ACC-01; AZ-01 |
| A company outside scope | Never named. Where a physical row relates to it, the **relation marker** "perusahaan lain" appears in its place — plain muted text, never a link, colour, logo, code or count; where a record of it would be reached by address or reference, the answer is the not-found page | PJ-04; H7-02; AZ-11 |

**Where the relation marker appears.** These are the only places; anywhere else a record of a company outside scope simply does not exist for the viewer.

| Place | What the viewer sees | Rule |
| --- | --- | --- |
| Pooled stock and lot lists (SCR-18, SCR-19) and the picking aid of a dispatch (SCR-22) | the lot's opaque code, product, remaining quantity, rack and condition, and "perusahaan lain" | PJ-01; PJ-02; PJ-23 |
| Reservations of a project of another company (SCR-23, SCR-19, the reservation-cut block of [ADMIN_FLOW IP-20](ADMIN_FLOW.md#ip-20--choosing-a-reservation-cut)) | the quantity and "proyek perusahaan lain" | PJ-04 |
| Movement history (SCR-20) | nothing: movements of a company outside scope are not listed, and their quantities reach the viewer only through the pooled figures | PJ-03; D-PM-11 |
| Allocated cost on the consuming project (SCR-10 *Pengadaan*, cost views) | the cost line in full, marked "dari stok perusahaan lain", the source company and its purchase details masked | PJ-06 |
| A lot's draw for another company (cost views of the source company) | "dialokasikan ke perusahaan lain", the bearer masked | PJ-05 |
| A correction case's causal project or source lot of another company (SCR-33) | "proyek perusahaan lain" or the lot code | XL-3; PJ-16 |
| A linked project of another company (SCR-10) | "proyek perusahaan lain" with the viewer's own link reason | XL-4 |
| A pending unexplained-loss case (SCR-18, SCR-19) | the physical quantities and state only; never the number or identity of the other candidate companies | PJ-07 |
| A cross-company event on a record's timeline | the viewer's own side; the other side's identifiers replaced by the marker | PJ-11 |
| An inter-company transfer citation (SCR-45) | "perusahaan lain" unless both companies are in scope | XL-5 |
| A routed refusal ([ADMIN_FLOW MSG-14](ADMIN_FLOW.md#14-message-catalogue)) | "catatan perusahaan lain di luar akses Anda" | SQ-23; PJ-04 |

## 8. Screen inventory

Sixty screens. Each row names what governs it; the server decides every request (AZ-01), and no screen shows a field its projection does not allow. Column notes:

- **Audience · projection** — the capabilities of PERMISSIONS_MATRIX §4 that open the screen and its parts, and the projection rules (PJ) or table rows of §7.2 that limit its fields; a part whose capability is missing is absent, never masked or shown as zero.
- **Scope** — *branches*: one bounded branch per company in scope, merged (PF-41), labelled per company (§7); *pool*: no company (PF-13); *record*: the record's own company; *global*: not company-owned.
- **Budget · deferred** — the PERFORMANCE query-budget row (QB) or, for a screen without its own row, the nearest class with PF-01's constant query count; then the number of deferred groups (at most three; Beranda five, PF-34).
- **Sort · filter** — only keys PF-42 and IX-11 register, or a filter in PF-42's bounded form ("bounded": one branch reads at most a stated number of rows — 500 as the starting value, a P8 measurement under HO-16 — before its page is returned short with "Muat Lagi"). The default order of every list is newest first by `id` (PF-13). Every list continues by "Muat Lagi" without totals or page numbers (PF-15).
- **Phone · desktop** — the patterns of [DESIGN_SYSTEM §9](DESIGN_SYSTEM.md#9-patterns); a `[desk]` screen opens on a phone for reading and small tasks and does not break.
- **Commands · print** — the commands launched from the screen with their capability; "print" means a print view identical to the page (DP-19), "—" none.

### 8.1 System, account and Beranda

| Screen | Purpose · workflow | Tag | Audience · projection | Scope | Signals | Budget · deferred | Sort · filter | Phone · desktop | Commands · print |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| **SCR-01** Masuk | sign in · WF-ACC-01 | `[both]` | unauthenticated | — | — | PF-40 · 0 | — | one narrow column on both | sign in (CI-09) · — |
| **SCR-02** Atur Kata Sandi | set a password from a credential link · AU-12 | `[both]` | the link's holder | — | — | PF-40 · 0 | — | one narrow column on both | set password (CI-09) · — |
| **SCR-03** Akun Saya | own profile, role, capabilities and companies (read-only), own last login and credential events, password change · AU-13 | `[both]` | SELF · PJ-17 | global | — | detail class · 0 | — | stacked sections · two columns | change password (CI-09) · — |
| **SCR-04** Halaman Sistem | not found, forbidden, error · AZ-11; WS-10 | `[both]` | ALL | — | — | — · 0 | — | the page frame with the message ([ADMIN_FLOW IP-21](ADMIN_FLOW.md#ip-21--denial-and-not-found)) | — · — |
| **SCR-05** Beranda (Owner) | consolidated and per-company overview · WF-ACC-01; CAP-17 | `[both]` | OWN · RD-01–RD-09; `reports.consolidated` for *Gabungan* | branches; the view switch *Gabungan* or one company | QS-01–QS-22 placed as §9 | QB-10 · 5 (four signal groups and the period figures, TX-10) | — | PT-21 groups stacked and collapsible · PT-21 in the composition of §9 | links only · — |
| **SCR-06** Beranda — Antrean Tindakan (Admin) | the action queue across granted companies · WF-ACC-01; CAP-17 | `[both]` | any active Admin; each group by its read class · PJ-13 | branches | per §9, by capability | QB-10 · 4 | — | PT-21 · PT-21 | links only · — |
| **SCR-07** Tinjauan Owner | the Owner review queue · PERMISSIONS_MATRIX §10 | `[desk]` | OWN (`review.view`) · RD-09 | branches and the items without a company | QS-14 | QB-12 · 0 | tabs per source — *Aktivitas* sorted by time recorded (IX-13); *Keamanan* (read through IX-14), *Bukti Gudang Bersama* and *Impor* newest first by `id` (PF-13); filter: window, company (keeping the items without a company), actor (bounded) | PT-06 rows with the window switch · PT-05 with the condition strip, §9 | links to records · print |
| **SCR-08** Cari | search results by type · CAP-17; DP-06 | `[both]` | ALL for S0 masters; company records by their view capability · PJ-10 | branches for company records | — | QB-03 · 0 | result groups by type; [§10](#10-search-lookup-and-scanner-entry-points) | full-screen search with grouped rows · result page with a group per type | open · — |

### 8.2 Projects and quotations

| Screen | Purpose · workflow | Tag | Audience · projection | Scope | Signals | Budget · deferred | Sort · filter | Phone · desktop | Commands · print |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| **SCR-09** Daftar Proyek | find and open projects · WF-PRJ-01 | `[both]` | `projects.view` · §7.2 rows 28–38; values `finance.view` | branches | — | QB-01 · 1 (filter lookups) | sort: newest; deadline of open projects (IX-11, NULL last); filter: state, client (IX-11), company | PT-06 + PT-07 sheet · PT-05 + PT-07 bar | "Buat Proyek" (`projects.manage`) · print |
| **SCR-10** Detail Proyek | the hub: *Ringkasan, Item, Penawaran, Pengadaan, Pemenuhan, Pengiriman, Dokumen, Penagihan, Pembayaran, Dokumen Administrasi, Riwayat* · WF-PRJ-01–WF-PRJ-04, WF-FUL-01 | `[both]` | `projects.view`; *Penawaran* values, *Penagihan*, *Pembayaran* `finance.view`; *Pengadaan* `cost.view` (absent without it); availability `stock.view` · PJ-06, PJ-11, PJ-12, XL-4 | record | QS-01–QS-04, QS-06, QS-15, QS-16, QS-19 for this project | QB-02 · 3 (Riwayat, Syarat penyelesaian, Dokumen); every other part is an optional prop loaded by a named partial reload when its tab is chosen (PF-34, PF-36); the part strip's facts come from the root and its guard columns in the first response, those of *Dokumen* and *Riwayat* with their groups — verified in P8 (HO-16) | parts are small bounded sets (PF-16) | PT-12: header facts and next action, parts as drill-in list · PT-12 with the derived part strip PT-31 and tabs (D-IA-09) | per part: the journeys of ADMIN_FLOW §10.2–10.7 · print (summary) |
| **SCR-11** Formulir Proyek | create and amend a project · WF-PRJ-01 | `[both]` | `projects.manage`; prices `finance.view` | record; new root: the chosen company | — | QB-13 command · 0 | — | PT-09 one column, lines as PT-10 rows · PT-09 two columns + PT-10 | save (`projects.manage`); discard draft · — |
| **SCR-12** Daftar Penawaran | quotation revisions across projects · WF-QUO-01 | `[desk]` | `projects.view`; values `finance.view` | branches | — | QB-12 class · 0 | filter: state (bounded), company | PT-06 · PT-05 | open · print |
| **SCR-13** Penawaran | edit, issue, revise and record the client's answer · WF-QUO-01, WF-PRJ-02 | `[desk]` | `quotation.manage` (needs `finance.view`); `projects.confirm` · rows 33–34 | record | — | QB-09 class · 1 (revision chain) | lines: PF-16 | read and "Catat Jawaban Klien" · PT-10 line editor | save draft; "Terbitkan Penawaran" (AX-15); "Catat Jawaban Klien" — *Disetujui* or *Ditolak* (AX-13); "Buat Revisi" · the DOC-01 rendition |
| **SCR-14** Penyelesaian Proyek | the ten conditions, normal completion and the Owner's Force Complete · WF-PRJ-03 | `[desk]` | `projects.view`; `projects.complete`; OWN `projects.force_complete` · PJ-12 | record | QS-15, QS-16 | QB-02 group · 1 (the predicates, QS-15's set queries) | — | stacked condition rows · condition table with links | "Selesaikan Proyek" (AX-25); "Selesaikan Paksa" (AX-26, OWN) · print |

### 8.3 Purchasing and warehouse

| Screen | Purpose · workflow | Tag | Audience · projection | Scope | Signals | Budget · deferred | Sort · filter | Phone · desktop | Commands · print |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| **SCR-15** Daftar Pembelian | purchases · WF-PUR-01, WF-PUR-02 | `[desk]` | `cost.view` · rows 39–46 | branches | QS-05 marker | QB-12 class · 0 | sort: newest; business date (IX-11); filter: state (bounded), company | PT-06 · PT-05 | "Buat Pembelian" (`purchase.manage`) · print |
| **SCR-16** Detail Pembelian | lines with received and remaining, charges and their allocation, receipts, disbursements, documents · WF-PUR-01–WF-PUR-03 | `[desk]` | `cost.view`; disbursements `finance.view` | record | QS-05 | QB-09 class · 2 (receipts, documents) | lines: PF-16 | PT-12 · PT-12 | "Terima Barang" (→ SCR-21); "Terbitkan PO" (`documents.issue`); "Batalkan Pembelian" (`purchase.cancel`); "Tutup Sisa Pembelian" (`purchase.close_remainder`); "Koreksi Harga, Pajak atau Biaya" (`purchase.correct_cost`); "Retur ke Pemasok" · print |
| **SCR-17** Formulir Pembelian | create a purchase with lines and charges · WF-PUR-01, WF-PUR-02 | `[desk]` | `purchase.manage` (needs `cost.view`) | new root: the project's or the chosen company | — | QB-13 command · 0 | — | readable; small edits · PT-10 line editor + charges table | save (NX-04) · — |
| **SCR-18** Stok | pooled availability per product · CALC-01 | `[both]` | `stock.view` · PJ-01, PJ-04, PJ-22; stock value of own companies `cost.view` | pool | QS-17 marker; QS-22 pending line | QB-05 · 1 (lot and serial detail) | sort: product (IX-11 pool key); filter: rack (IX-12), below minimum (QS-17's statement), condition (bounded) | scan or search first, then PT-06 rows that open SCR-19 · PT-05 with the four quantities per product and rows that expand to racks, reservations and lots (D-IA-17) | links to moves, counts and adjustments · print |
| **SCR-19** Detail Stok Produk | racks, lots, serials, reservations and recent movements of one product | `[both]` | `stock.view` · PJ-01–PJ-04, PJ-07, PJ-23; lot cost of own companies `cost.view` | pool | QS-22 pending line | QB-04 · 2 (lots and serials; recent movements) | lots: oldest-first aid (PJ-01) | PT-17 stacked, with "Kembali ke Daftar" · PT-17: the four quantities, the pending line and the advice above racks, reservations, lots and serials and the latest movements (D-IA-17) | "Pindah Lokasi" (`stock.move`); "Ubah Kondisi" (`stock.condition`); "Catat Penyesuaian Stok" (`stock.adjust`); "Mulai Hitungan" (`stock.count`) · print |
| **SCR-20** Riwayat Stok | movement history · PJ-03 | `[both]` | `stock.view`; project `projects.view`, purchase `cost.view` · rows 50–51 | branches and the pool branch | — | QB-12 class · 0 | sort: business date (IX-11); filter: movement type (PF-42), company | PT-06 · PT-05 | "Balikkan" on a movement (`stock.reverse`) · print |
| **SCR-21** Barang Masuk | receiving · WF-INV-01 | `[floor]` | `stock.receive` · PJ-18; reservation offer `stock.reserve` | branches (purchases of companies in scope) | QS-05 worklist | QB-06 lookups + QB-13 command · 0 | worklist: newest purchase first | PT-20 scan-led flow, one line at a time · lines and current line side by side with PT-20 | "Catat Barang Masuk" (AX-04); "Reservasi untuk Proyek Ini" (AX-01); "Terbitkan Nota Terima Barang" · — |
| **SCR-22** Barang Keluar | dispatch · WF-INV-03, WF-INV-07 | `[floor]` | `stock.dispatch`; another company's lot `stock.allocate_intercompany` · PJ-01, PJ-04 | branches (projects in scope); lots: pool | QS-06 precursor | QB-06 + QB-13 · 0 | lots: oldest-first aid | PT-20 scan-led · line, lots and serials side by side | "Catat Barang Keluar" (AX-03); "Terbitkan Surat Jalan" · — |
| **SCR-23** Reservasi | reserve, reduce and release · WF-INV-02 | `[desk]` | `stock.reserve` (needs `stock.view`, `projects.view`) · rows 61–62 | branches; other projects as the marker | QS-01, QS-03, QS-04 | QB-12 class · 0 | filter: project (bounded), company | PT-06 · PT-05 | "Reservasi" (AX-01); "Kurangi Reservasi", "Lepas" (AX-02) · — |
| **SCR-24** Stok Opname | counts and their application · WF-INV-05 | `[floor]` | `stock.count`; apply `stock.count_apply` · rows 63–66 | pool | QS-18 (stale, open findings) | QB-12 class · 0 | filter: state (bounded) | PT-20 counting · count review table | "Mulai Hitungan"; record lines; "Terapkan Selisih" (AX-06) · print (count sheet) |
| **SCR-25** Penyesuaian & Kondisi | condition change, disposal, shrinkage and loss · WF-INV-04, WF-INV-06 | `[floor]` | `stock.condition`; `stock.adjust` · AZ-08 | pool; the bearer company | QS-22 created here | QB-13 command · 0 | — | PT-20 + one-column form · form | "Ubah Kondisi", "Periksa Ulang", "Catat Penyesuaian Stok" (AX-06, AX-30) · — |
| **SCR-26** Retur ke Pemasok | purchase return and its case · WF-PUR-03 | `[floor]` | `purchase_return.record` (needs `stock.view`, `cost.view`) | record | QS-07 | QB-13 command · 0 | — | PT-20 + form · form + case panel | open case; stock out (AX-11); outcome (NX-16); "Tutup Kasus" (`cases.close`) · print |
| **SCR-27** Selisih & Temuan | the Owner's attribution of pending losses and count surpluses · SF-UNATTRIBUTED; AX-07; AX-37 | `[desk]` | OWN (`stock.resolve_unattributed`, `stock.attribute_surplus`); ADM+ evidence resolution (`stock.resolve_unattributed_evidence`) with PJ-07's limited projection | pool | QS-18 (attribution), QS-22 | QB-12 class · 1 (snapshot of a case) | sort: oldest first | read · case list with its recognition snapshot | "Tetapkan Perusahaan" (AX-37, OWN); "Tetapkan dengan Bukti" (AX-37 under OD-10, ADM+); "Tetapkan Surplus" (AX-07, OWN); "Balikkan Penetapan" (CM-27) — [ADMIN_FLOW §10.3](ADMIN_FLOW.md#103-inventory-and-purchasing) · print |

### 8.4 Fulfilment, cases and documents

| Screen | Purpose · workflow | Tag | Audience · projection | Scope | Signals | Budget · deferred | Sort · filter | Phone · desktop | Commands · print |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| **SCR-28** Pengiriman | shipments not yet delivered, and delivery records · WF-FUL-02 | `[both]` | `projects.view`; `delivery.record` · rows 81–83 | branches | QS-06 | QB-12 class · 0 | filter: company; state (bounded) | PT-06 · PT-05 | "Catat Pengiriman" (→ SCR-29) · print |
| **SCR-29** Catat Pengiriman | the delivery record and its discrepancy · WF-FUL-02 | `[both]` | `delivery.record`; closure `delivery.close`; correction `fulfillment.correct` | record | — | QB-13 command · 0 | — | one-column form with photo proof · form | "Catat Pengiriman" (AX-09); "Tutup Pengiriman"; BAST and Berita Acara drafts · print |
| **SCR-30** Kirim Langsung | drop-ship confirmation · WF-FUL-03 | `[desk]` | `dropship.confirm`; cost `cost.view` | record | — | QB-13 command · 0 | — | form · form | "Konfirmasi Kirim Langsung" (AX-08) · — |
| **SCR-31** Serah Terima Jasa | service handover · WF-FUL-04 | `[both]` | `handover.record` | record | — | QB-13 command · 0 | — | one-column form · form | record handover; BAST · print |
| **SCR-32** Retur dari Klien | sales return and its case · WF-FUL-05 | `[both]` | `sales_return.record`; companions by their capability | record | QS-07 | QB-13 command · 0 | — | PT-20 receipt + form · case panel with steps | open case; restoration (AX-10); companions; "Tutup Kasus" · print |
| **SCR-33** Kasus Koreksi | correction cases with their residuals · SF-CASE | `[desk]` | `projects.view` of the case company; money counters by family · PJ-16, XL-3 | branches | QS-07 | QB-12 class · 1 (linked corrections) | filter: state (bounded), company | PT-06 · PT-05 + case detail | "Tutup Kasus" (`cases.close`) · print |
| **SCR-34** Dokumen | generated documents · WF-DOC-01 | `[both]` | `projects.view` (metadata); payload by family · PJ-15 | branches | QS-08 marker | QB-12 · 0 | filter: document type (bounded), company; exact number lookup (PF-19) | PT-06 · PT-05 | open · print |
| **SCR-35** Detail Dokumen | versions, rendition state, preview, revision and void · WF-DOC-01, WF-FIN-08 | `[desk]` | `projects.view` (metadata); payload by family · PJ-15 | record | QS-08, QS-19 | QB-09 class · 1 (versions) | — | read, download · preview beside the version chain | "Terbitkan" (AX-15, AX-16, AX-31); "Tautkan Kuitansi" (AX-31, `payment.record`); "Revisi"; "Batalkan Dokumen" (`documents.correct`) · the rendition |
| **SCR-36** Bukti | evidence: upload, register, versions, links · WF-DOC-02; SF-EVIDENCE | `[both]` | `evidence.view` + family; `evidence.upload` · PJ-08 | branches and pool evidence | QS-21 (evidence) | QB-12 · 0 | filter: evidence type (bounded) within the viewer's families, company | PT-06 + upload sheet · PT-05 + upload panel | register (NX-07); "Jadikan Versi Berlaku" (NX-14); override (`warnings.override`) · — |
| **SCR-37** Dokumen Administrasi | per-project checklist and a cross-project view of unmet items · WF-ADM-01 | `[desk]` | `projects.view`; `requirements.manage`, `requirements.waive`; OWN fields · rows 96–98 | record; cross-project: branches | QS-15 (administration) | QB-12 class · 0 | cross-project filter: status (bounded), company | PT-06 · checklist table with linked items | add, tighten, link; "Tandai Tidak Berlaku"; Owner relaxations · print |

### 8.5 Finance

| Screen | Purpose · workflow | Tag | Audience · projection | Scope | Signals | Budget · deferred | Sort · filter | Phone · desktop | Commands · print |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| **SCR-38** Daftar Invoice | invoices · WF-FIN-01 | `[desk]` | `finance.view` · rows 99–104 | branches | QS-09 marker | QB-08 · 0 | sort: newest; filter: client (PF-42), company; exact number lookup | PT-06 · PT-05 | "Buat Invoice" (`invoice.issue`) · print |
| **SCR-39** Detail Invoice | lines, tax, position block, billing act, applications and settlements, documents · WF-FIN-01, WF-FIN-02, WF-FIN-05, WF-FIN-08 | `[desk]` | `finance.view` · rows 99–113 | record | QS-09–QS-11, QS-13, QS-19 for it | QB-09 · 2 (applications and settlements; documents) | — | PT-24 first, then parts · PT-12 + PT-24 | "Terbitkan Invoice"; "Tagihkan" (`billing.record`); "Catat Pembayaran" (→ SCR-42); "Tandai Sengketa"/"Cabut Sengketa"; corrections (`invoice.correct`, `billing.correct`); OWN "Hapuskan Piutang"; "Kuitansi untuk Proses Pembayaran"; "Tautkan Kuitansi" (`payment.record`) · print |
| **SCR-40** Piutang | active receivables with ageing; *Belum Ditagihkan*; *Sengketa* · WF-FIN-02 | `[desk]` | `finance.view` · row 103 | branches; subtotals per company | QS-09, QS-10, QS-11 | QB-07 · 0 | sort: current due date (IX-11, NULL last); tabs: *Aktif*, *Belum Ditagihkan*, *Sengketa*; filter: company | PT-06 · PT-05 with the due state per row; ageing by bucket is on the receivables report (SCR-47; D-IA-15) | "Catat Pembayaran"; "Tagihkan" · print |
| **SCR-41** Pembayaran | payments and their applications · WF-FIN-03 | `[both]` | `finance.view` · rows 105–108 | branches | QS-12, QS-21 (payments) | QB-12 · 0 | sort: newest; business date (IX-11); filter: client (PF-42), company | PT-06 · PT-05 + detail | "Catat Pembayaran" (→ SCR-42); "Terapkan" (`payment.apply`); corrections (`finance.contra`, `payment.reallocate`) · print |
| **SCR-42** Catat Pembayaran | record a payment with its applications · WF-FIN-03 | `[both]` | `payment.record`, `payment.apply` | new root: the chosen company | — | QB-13 per command · 0 | — | one-column form with its application step · form and application table | "Catat Pembayaran" with its applications (AX-18); "Terapkan" for money left unapplied (AX-19); "Tautkan Kuitansi" (AX-31) · — |
| **SCR-43** Potongan | fee and tax deduction settlements · WF-FIN-04 | `[desk]` | `settlement.record` (needs `finance.view`, `evidence.view`) | record | QS-13 | QB-13 command · 0 | — | read · form with evidence | "Catat Potongan" (AX-21) · — |
| **SCR-44** Kredit Pelanggan | customer credit and refunds · WF-FIN-06 | `[desk]` | `finance.view`; `payment.apply`, `refund.record` · row 107 | branches | QS-12 | QB-12 class · 0 | filter: client (bounded), company | PT-06 · PT-05 | "Terapkan ke Invoice"; "Kembalikan Dana" (AX-23) · print |
| **SCR-45** Pengeluaran & Kas Lain | disbursements, expenses, other Cash-In · WF-FIN-07 | `[desk]` | `finance.view`; expenses `cost.view` · rows 78, 114–116 | branches | — | QB-12 class · 0 | tabs per kind — disbursements, expenses, other Cash-In — each sorted by business date (IX-11); filter: company | PT-06 · PT-05 | record each kind (`disbursement.record`, `expense.record`, `cashin.record`); correction (`finance.contra`) · print |
| **SCR-46** Mutasi Bank | bank statement lines and their citation · L-22 | `[desk]` | `bank_statement.record` (needs `finance.view`, `evidence.upload`) · row 93 | branches | QS-12 (lines not fully cited) | QB-12 class · 0 | filter: account (bounded), company | read · PT-05 | record line; void (`cash.correct`) · print |

### 8.6 Reports, master data, settings and Owner areas

| Screen | Purpose · workflow | Tag | Audience · projection | Scope | Signals | Budget · deferred | Sort · filter | Phone · desktop | Commands · print |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| **SCR-47** Laporan | stock, purchase, sales, receivable, payment, profit and cash-flow reports · CAP-11, CAP-17 | `[desk]` | each report by its read class (RD-02–RD-06); export `reports.export` | branches; a table per company for an Admin | a company's own exposure to pending cases as a separate line in its stock and profit reports, with `cost.view` of the company (PJ-22; §7.2 row 69) | QB-11 · 0 (one snapshot, TX-10) | company and date range required (PF-27) | report as PT-06 rows with a filter sheet · PT-05 tables | "Ekspor" (→ SCR-49) · print |
| **SCR-48** Laporan Gabungan | consolidated figures and the inter-company allocation report · CALC-12 | `[desk]` | OWN (`reports.consolidated`) · RD-07 | all companies | — | QB-11 · 0 | date range required | read · PT-05 | "Ekspor" · print |
| **SCR-49** Ekspor Saya | the requester's own export requests · HO-09 | `[both]` | `reports.export`; the requester only (PERMISSIONS_MATRIX §7.2, `TECH-021`) | own | — | QB-12 class · 0 | newest first | PT-06 · PT-05 | "Unduh" (an ordinary download); "Minta Lagi" · — |
| **SCR-50** Klien | organizations, units and PICs · WF-MD-02 | `[both]` | ALL (names); units, addresses, PICs `projects.view` · PJ-10 | global; related records by branches | — | QB-03 · 0 | search-first ([§10](#10-search-lookup-and-scanner-entry-points)); no browsing order beyond newest | search and one-column create · list and detail | create, edit, archive (`parties.manage`) · — |
| **SCR-51** Pemasok | suppliers · WF-MD-03 | `[desk]` | ALL (names); details `projects.view` or `cost.view`; price history `cost.view` per company · PJ-09, PJ-10 | global; history by branches | — | QB-03, QB-04 class · 1 (price history) | search-first | read · list and detail | create, edit, archive (`parties.manage`) · — |
| **SCR-52** Produk & Jasa | products and services · WF-MD-04 | `[desk]` | ALL; price defaults by family (PJ-14); stock `stock.view` | global | — | QB-03 list; QB-04 detail · 2 (price history, recent movements) | search-first; thumbnails one page (PF-31, PF-32) | lookup and detail · list and detail | create, edit, archive; "Buat Barcode Internal"; "Cetak Label" (`products.edit`) · print (label) |
| **SCR-53** Lokasi Rak | racks and minimum stock · WF-MD-05 | `[desk]` | `stock.view`; `warehouse.manage` | pool | QS-17 | QB-12 class · 0 | newest | read · PT-05 | create, edit (`warehouse.manage`) · print |
| **SCR-54** Perusahaan | company master and its derived readiness · WF-MD-01 | `[desk]` | OWN (`companies.manage`) · PJ-19 | all companies | readiness lines ([ADMIN_FLOW §12.4](ADMIN_FLOW.md#124-company-onboarding)) | detail class · 0 | — | read · detail with sections | the company form; identity assets and bank accounts behind the step-up; activate, deactivate · — |
| **SCR-55** Penomoran Dokumen | schemes, counters, boundaries, go-live, covering batches; start and seed · NM-03; HO-39 | `[desk]` | OWN (`companies.manage`) · §7.2 rows 10–12 | all companies | — | detail class · 0 | per company and type | read · per company and type ([ADMIN_FLOW §12](ADMIN_FLOW.md#12-numbering-and-company-onboarding)) | "Mulai Penomoran"; "Tetapkan Nomor Awal"; scheme add or change (NX-01) · — |
| **SCR-56** Pajak | tax types, rate versions, company profiles, treatment rules · WF-MD-01 | `[desk]` | OWN (`tax.configure`) · rows 13–16 | global and per company | — | detail class · 0 | — | read · tables and forms | configure (`tax.configure`) · — |
| **SCR-57** Pengguna & Akses | accounts, role, company grants, capabilities, sessions, credential links · WF-ACC-02 | `[desk]` | OWN (`users.manage`, `permissions.manage`) · PJ-17 | global | — | detail class · 0 | newest | read, end sessions, deactivate · list and detail with the capability-by-company preview | create, grant, revoke, deactivate, issue link — behind the step-up · — |
| **SCR-58** Impor Data Awal | batches, dry run, sign-off, commit, progress · WF-MIG-01 | `[desk]` | `import.prepare` with every batch company in scope; OWN `import.commit` · PJ-20 | the batch's companies | — | detail class · 0 | newest | read · batch list and detail ([ADMIN_FLOW §13](ADMIN_FLOW.md#13-opening-import)) | prepare, sign off, commit (OWN, step-up), resume, abort · print (dry-run report) |
| **SCR-59** Riwayat Aktivitas | workspace audit search · BR-XC-01 | `[desk]` | OWN (`audit.view`) · PJ-11 | branches and events without a company | — | QB-12 · 0 | sort: time recorded (IX-13); filter: company, date range, actor (bounded), action class (IX-13 partial indexes), backdated gap (bounded) | read · PT-05 + PT-18 | — · print |
| **SCR-60** Log Keamanan | security events · LG-02 | `[desk]` | OWN (`security_log.view`) · PJ-21 | global | — | QB-12 class · 0 | sort: newest; filter: kind (bounded) | read · PT-05 | — · — |

The record timeline (*Riwayat*, PT-18) is a part of every detail screen rather than a screen of its own: it follows PJ-11, loads as one deferred group where its screen allows one, and continues by "Muat Lagi" on (`entity_type`, `entity_id`, `occurred_at`, `id`) (IX-13).

## 9. Dashboards and queues

Signals are projections over facts and decisions; no queue is stored (WORKFLOWS §10; DATABASE §3). Each is one bounded statement, or one per predicate for QS-15 (PF-24), read with the viewer's scope first (CS-07) and shown per company (CS-08): a count reads "ARJ 4 · BTN 20+" — bounded per company, never summed across companies for an Admin (PF-15, PF-25). A signal row links to the screen where the next action happens for a viewer who holds that action's capability, and otherwise to the record's view (PJ-13); the dashboard itself runs no command.

**Groups and budget.** Beranda loads its frame and group headings in the first response; each group is a deferred group with its own skeleton and its own error with "Muat Ulang Bagian Ini" (PT-19). The Owner's Beranda has five groups — four signal groups and the period figures, read in one snapshot (TX-10; PF-43); an Admin's has four signal groups and no period figures. QB-10 bounds each signal group at twelve statements, the ten completion predicates of QS-15 forming a group of their own.

| Group | Owner (SCR-05) | Admin (SCR-06) | Statements |
| --- | --- | --- | --- |
| G1 *Perlu Perhatian Owner* | the Owner's security notices (below); QS-14 (new items since the Owner's previous sign-in from every source of SCR-07: the audit events and the security events of PERMISSIONS_MATRIX §10, pool-evidence uploads and the DUPLICATE import rows whose twin lies outside the preparer's scope), QS-16, QS-18 attribution, QS-20, QS-22 | — | 9 |
| G1 *Proyek & Gudang* | — | QS-01–QS-06, QS-16, QS-17, QS-18 (open findings, stale counts), QS-20 | 11 |
| G2 *Operasional* (Owner) · *Dokumen & Kasus* (Admin) | QS-01–QS-08, QS-17, QS-18 (open findings, stale counts), QS-21 | QS-07, QS-08, QS-21 | 12 · 3–6 |
| G3 *Penagihan & Kas* | QS-09–QS-13, QS-19 | the same, present only with `finance.view` | 8 |
| G4 *Penyelesaian Proyek* | QS-15 | QS-15 (money items only with `finance.view`) | 12 |
| G5 *Angka Periode* | CALC-08–CALC-14 per company and *Gabungan*, "Angka per {waktu} WIB" | — | ≤ 6 |

**Refresh.** By the user ("Muat Ulang") or by a poll no faster than once a minute that names its groups' props (PF-26, PF-36); polls are background requests that never extend a session (H7-13). The time of the last refresh is shown quietly ("Diperbarui 14.05 WIB"). An empty group is one quiet sentence, never an illustration (PT-19); a group the account has no capability for does not exist on its page.

**Owner security notices.** The in-app notices SECURITY owes the Owner head G1 as rows of their own: a credential link the Owner issued was used (AU-12) — MSG-29's Owner variant with the account and the time — and, until P9 confirms another channel, the LG-07 alerts that are single security events — an OWNER_RECOVERY_LINK_ISSUED, an AUTHORIZATION_ANOMALY, a successful Owner sign-in from an address not seen for it in 30 days — each as its neutral type, its time and a link to SCR-60 (§11). They are derived by one bounded statement (PF-24) over the security events recorded since the Owner's previous sign-in (AU-13), its plan verified in P8 (HO-16), so nothing is marked read; older ones stay on SCR-60. The aggregate alerts of LG-07 — bursts, spikes, volumes and allocation patterns — reach the Owner as P9 delivers them (H9-10; UXH-09). No Admin page has them.

**Owner view switch.** *Gabungan* or one company at a time; the switch filters the signal groups and the period table and is a view choice like the working filter (§7).

### 9.1 Placement of every signal

| QS | Signal (WORKFLOWS §10) | Audience — read class (PJ-13) | Owner · Admin group | Entry point and action | Empty state |
| --- | --- | --- | --- | --- | --- |
| QS-01 | confirmed warehouse demand not yet reserved | RD-02 with its project part RD-03 | G2 · G1 | the line on SCR-23, "Reservasi", for `stock.reserve`; otherwise the line on SCR-10 *Pemenuhan* | "Semua permintaan gudang sudah direservasi." |
| QS-02 | shortage → purchase needed | RD-02, RD-03 | G2 · G1 | SCR-17 prefilled for `purchase.manage`; otherwise the line on SCR-10 | "Tidak ada kekurangan stok." |
| QS-03 | received goods for a project awaiting reservation | RD-02, RD-03 | G2 · G1 | SCR-23, "Reservasi", for `stock.reserve`; otherwise the line on SCR-10 *Pemenuhan* | "Tidak ada barang diterima yang menunggu reservasi." |
| QS-04 | reservations cut; project demand flagged | RD-02, RD-03 | G2 · G1 | SCR-10 *Pemenuhan* | "Tidak ada reservasi yang dikurangi." |
| QS-05 | open purchase remainders | RD-05, or `stock.receive` through PJ-18 | G2 · G1 | SCR-21 for receiving; SCR-16 for `cost.view` | "Tidak ada sisa pembelian yang menunggu barang." |
| QS-06 | dispatched or shipped, not delivered | RD-03 | G2 · G1 | SCR-28; SCR-29 for `delivery.record` | "Semua barang yang keluar sudah tercatat terkirim." |
| QS-07 | open discrepancies and correction cases, with their residuals | RD-03; money residuals RD-04 or RD-05 | G2 · G2 | SCR-33 | "Tidak ada kasus koreksi terbuka." |
| QS-08 | rendition pending or failed | RD-03 | G2 · G2 | SCR-35 | "Semua PDF sudah tersedia." |
| QS-09 | issued invoices *Belum Ditagihkan* | RD-04 | G3 · G3 | SCR-40 tab *Belum Ditagihkan* → SCR-39 "Tagihkan" | "Tidak ada invoice yang belum ditagihkan." |
| QS-10 | overdue receivables by ageing bucket | RD-04 | G3 · G3 | SCR-40 | "Tidak ada piutang yang lewat jatuh tempo." |
| QS-11 | disputed receivables | RD-04 | G3 · G3 | SCR-40 tab *Sengketa* | "Tidak ada piutang dalam sengketa." |
| QS-12 | unapplied customer credit (credit linked to a written-off invoice included); payments flagged without proof; bank lines not fully cited | RD-04 | G3 · G3; the credit linked to a written-off invoice also in the Owner review queue | SCR-44; SCR-41; SCR-46 for `bank_statement.record` — for another viewer the bank-line row is text without a link | "Tidak ada kredit atau pembayaran yang perlu ditindaklanjuti." |
| QS-13 | claimed deductions not yet evidenced | RD-04 | G3 · G3 | SCR-43 for `settlement.record`; otherwise the invoice on SCR-39 | "Tidak ada potongan yang menunggu bukti." |
| QS-14 | the Owner review queue | RD-09 (`review.view`) | G1 · absent — no entry, count or badge exists for an Admin | SCR-07 | "Tidak ada aktivitas baru sejak Anda masuk sebelumnya." |
| QS-15 | completion blockers per project; invoicing above delivered value; confirmed value not yet invoiced | RD-03; the money items RD-04 | G4 · G4 | SCR-14 of the project | "Tidak ada proyek aktif yang terhambat." |
| QS-16 | residual obligations of force-completed or cancelled projects | RD-03; money items RD-04 | G1 · G1 | SCR-10 of the project | "Tidak ada kewajiban tersisa." |
| QS-17 | low stock and restock advice | RD-02 | G2 · G1 | SCR-18 filtered "di bawah minimum"; "Buat Pembelian" for `purchase.manage` — advice only, never a purchase | "Tidak ada stok di bawah minimum." |
| QS-18 | count findings, stale counts, unattributed surplus | RD-02 (findings, stale counts); RD-09 (attribution, Owner) | G1 (attribution) and G2 · G1 (findings, stale counts) | SCR-24 for `stock.count` or `stock.count_apply`, otherwise the product on SCR-19; SCR-27 for the Owner | "Tidak ada temuan hitungan yang terbuka." |
| QS-19 | pre-payment Kuitansi not yet linked or voided | RD-04 | G3 · G3 | SCR-35 of the Kuitansi; SCR-39 | "Tidak ada Kuitansi untuk Proses Pembayaran yang menunggu." |
| QS-20 | routed refusals needing other-company grants | the Owner and accounts with every company of the refused command in scope (PJ-13) | G1 · G1 | the refused command's target record; a notice with its age that leaves the list after 14 days — no task state, no "selesai" control | "Tidak ada penolakan yang diteruskan dalam 14 hari terakhir." |
| QS-21 | duplicate warnings overridden or confirmed | per family: RD-02 stock events, RD-03 delivery events, RD-04 payments, RD-08 evidence (PERMISSIONS_MATRIX §7.4); each also in QS-14 | G2 · G2 | the record | "Tidak ada peringatan duplikat yang dikonfirmasi." |
| QS-22 | pending unexplained-loss cases with their recognition snapshots; their separate display in company stock and profit views | the cases with snapshots RD-09 (Owner); the pending quantities RD-02 | G1 · not in the Admin queue — the pending quantities show on SCR-18 and SCR-19 as their own line, blocking nothing (DIR-027) | SCR-27 | "Tidak ada selisih yang menunggu penetapan." |

### 9.2 The Owner review queue (SCR-07)

Designed after the GAP-034 settlement ([P7_QUALITY_GATE](evidence/P7_QUALITY_GATE.md#gap-034-settlement-and-ordering-evidence)). SCR-07 is a **derived view**, never a stored queue and never an approval step: every listed action is already in effect; the Owner reviews it and, where something is wrong, acts on the record through its normal commands and correction rows (PERMISSIONS_MATRIX §10). Only `review.view` opens it; an Admin's request for its address is the not-found page (UXS-25).

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
| **Cari** — global search | desktop: the field in the top bar, reached also by Ctrl+K; phone: the navigation's *Cari* | A code — SKU, barcode, serial, project number, document number — is looked up exactly first (PF-19): one hit opens it, several rows sharing the key within scope are listed for the user to choose. A name of at least three consecutive letters or digits searches product, client and supplier names (S0) and the titles of projects in scope (PF-20, PF-22), names equal to or beginning with the term first, a type-ahead of at most 20 rows and SCR-08 for the full result, cut at 200 candidates with the notice of [ADMIN_FLOW IP-17](ADMIN_FLOW.md#ip-17--search-and-lookup). At most 100 characters (PF-23) |
| **Pindai** — scanning into *Cari* | desktop: the scanner typing into the Cari field when it has focus; phone: the Cari field of the full-screen search (D-IA-04); and the scan field of a warehouse screen | Exact lookup only (QB-06): the product, serial or lot found opens its stock detail (SCR-19); unknown and ambiguous codes as [ADMIN_FLOW IP-15](ADMIN_FLOW.md#ip-15--scanner) |
| Scan fields of warehouse screens | SCR-19, SCR-21, SCR-22, SCR-24, SCR-25, SCR-26, SCR-32 | PT-20; manual SKU entry and "Cari Produk" always beside the field |
| Lookups inside forms | product, client, unit, PIC, supplier, project, invoice pickers | PT-27: exact code first, then names from three characters; client creation is search-first (SCR-50) |
| Exact number lookups on lists | SCR-09, SCR-34, SCR-38 | the list's search field: an exact project, document or invoice number within scope |

Search terms never appear in a page address or title (§11). A result, count or empty state never hints at a record outside the viewer's scope (DP-06).

## 11. Page addressing

| Element | Convention | Grounds |
| --- | --- | --- |
| Paths | lowercase Indonesian segments that follow the product map — `/beranda`, `/proyek`, `/proyek/{id}`, `/proyek/{id}/penyelesaian`, `/gudang/barang-masuk`, `/keuangan/piutang`, `/pengaturan/penomoran` — where `{id}` is a public identifier, UUIDv7 (DATABASE §16). A child line, ledger row or guard is addressed through its root and never has an address of its own | DP-01; DATABASE §16 |
| Query | only whitelisted filter and sort keys whose values are enumerations, dates or public identifiers, and the opaque cursor (PF-14; WS-03). Never a search term, name, amount, number or S3/S4 value | DP-02; H7-10 |
| Titles | "{screen name} · MultipleCorp" — never a business number, name, company, amount or state, because titles enter browser history and tab lists | H7-10 |
| Unknown, out-of-scope and Owner-only addresses | the not-found page SCR-04, identical for each (AZ-11) | AZ-11; UXN-10 |
| History | page data in browser history is encrypted, and cleared at every authentication boundary, or the document reloads (AU-06; H7-12); no form data is remembered in history state ([ADMIN_FLOW §6](ADMIN_FLOW.md#6-session-expiry-re-authentication-and-unsent-input)) | WS-07; H7-12 |
| Notifications and alerts | a neutral type and a link; their content is derived at display time under the viewer's projection, so a revoked company's alert disappears | DP-11; H7-10 |
| Downloads and exports | ordinary browser requests to the authorizing controller, never an Inertia visit and never a signed link as the only authorization | RB-04; PF-29; FL-07 |
| Credential links | the token travels in the address fragment and is removed from the address bar and history on load (AU-12) | AU-12; H7-04 |
| Prefetch | off on S3 and S4 pages (PF-35) | WS-07 |

## 12. Traceability

- **V1_SCOPE and acceptance:** CAP-01–CAP-18 → §5; the language and experience contract → §3, §6, §8; AC-04 → SCR-10; AC-13 → §7, §11; AC-14 → §6, §8 phone and desktop columns; AC-23 → §9, §10; REF-007 → SCR-05; REF-008 → SCR-06; REF-013 → §10; REF-048 → §9; REF-050 → §10; REF-052 → §3; REF-053 → §6; REF-054 → [DESIGN_SYSTEM §2](DESIGN_SYSTEM.md#2-foundation-and-source-evidence); REF-055 → [DESIGN_SYSTEM §18](DESIGN_SYSTEM.md#18-anti-ai-slop-mapping).
- **WORKFLOWS:** §10 QS-01–QS-22 → §9.1 (22 of 22); WF-ACC-01 → §6, §7, SCR-05, SCR-06; every workflow's screens → §8 and [ADMIN_FLOW §10](ADMIN_FLOW.md#10-journeys).
- **PERMISSIONS_MATRIX and SECURITY:** CS-01–CS-08 → §7; PJ-01–PJ-23 → §7, §8; §10 → §9.2; H7-02 → §7; H7-10 → §11; DP-01, DP-02, DP-06, DP-11, DP-19 → §8, §10, §11.
- **PERFORMANCE:** PF-13–PF-17, PF-41, PF-42 → §8; PF-19–PF-23 → §10; PF-24–PF-26, PF-34, PF-43, QB-10 → §9; the QS-14 row and IX-14 (`TECH-022`) → §9.2.
- **Gaps:** GAP-006 → §7; GAP-034 → §9.2.

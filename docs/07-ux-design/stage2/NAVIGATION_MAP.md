# Navigation map

Status: DRAFT | Updated: 2026-10-14 | Owner: Planning

**Draft — not normative.** This is an S2-1 document of Stage 2 of the P7 UX re-baseline, written under [DIR-061](../../00-governance/DECISION_LOG.md#dir-061-dir-062-obs-023-and-tech-031--p7-ux-re-baseline-stage-2-decisions-s2-0-authorization-baseline-and-prototype-setup) SD-01, SD-05 and SD-10 and [DIR-064](../../00-governance/DECISION_LOG.md#dir-063-dir-064-obs-024-and-tech-032--p7-ux-re-baseline-s2-0-acceptance-s2-1-authorization-baseline-and-stage-2-documents) §12.2. Behaviour follows MULTIPLECORP — P0–P6 and ADMIN_FLOW —, structure follows R1, and presentation follows the Golden Frontend Reference v1.0.0, which adapts to MULTIPLECORP and never the reverse (AK-1; SD-02). [INFORMATION_ARCHITECTURE](../INFORMATION_ARCHITECTURE.md) and [DESIGN_SYSTEM](../DESIGN_SYSTEM.md) stay APPROVED under APPR-008 and under replacement, and [ADMIN_FLOW](../ADMIN_FLOW/README.md) stays in force, until a later authorized stage changes them; Stage 3 uses this draft, and nothing in it is normative.

**Sources.** [IA_PAGE_INVENTORY](IA_PAGE_INVENTORY.md) — its pages PG-001–PG-065, personas, grant sets, shell and entries; the gate record's R1 [revised direction](../evidence/P7_REBASELINE_GATE.md#c1-rework--revised-direction) a)–f) and [revised non-loss map](../evidence/P7_REBASELINE_GATE.md#c1-rework--revised-non-loss-map), and the [persona task analysis](../evidence/P7_REBASELINE_GATE.md#persona-task-analysis); INFORMATION_ARCHITECTURE [§9.1](../INFORMATION_ARCHITECTURE.md#91-placement-of-every-signal) as the behaviour of each signal row — its read class and the capability its action needs —, §§6–7; [PERMISSIONS_MATRIX](../../05-security/PERMISSIONS_MATRIX.md) §§3, 4, 7.4 and 9, AZ-01, AZ-11, the AC-13 resolution, CS-08 and PJ-13; [QUERY_BUDGET_CHECKS](QUERY_BUDGET_CHECKS.md), whose fitting designs the paths follow. No technology behaviour is stated.

**Rules for every path.** An entry the account cannot use is absent, never disabled (AZ-01; IA_PAGE_INVENTORY §3.3); an address the account cannot open answers the not-found page, the same for an unknown, an out-of-scope and an Owner-only address (AZ-11; PG-005). "›" separates the steps of a path; a work row leads to the page where its next action happens for an account holding that action's capability, otherwise to the record's view (PJ-13; INFORMATION_ARCHITECTURE §9.1).

## 1. Navigation trees

### 1.1 Laras, the Owner — desktop

Lands on *Beranda* (PG-006).

- **Primary entries**
  - *Beranda* (PG-006) — the decision rows: the security notice › PG-060 or PG-064; a pending unexplained loss ([QS-22](../../03-workflows/WORKFLOWS/s10-14-signals-decisions-traceability.md#10-derived-signals-and-action-queue)) and an unattributed surplus (QS-18) › PG-029; new activity (QS-14) › PG-008; a routed refusal (QS-20) › the refused command's record; residual obligations (QS-16) › PG-011; overdue and disputed receivables (QS-10, QS-11) › PG-044; completion conditions unmet (QS-15) › PG-011's "Yang tersisa sebelum selesai". The KPIs › "Rincian angka" › PG-052. "Proyek yang perlu perhatian" › PG-011. "Antrean operasional" › "Buka Pekerjaan" › PG-007.
  - *Proyek* (PG-010) › a row › PG-011 › its stages and drill-down: PG-014 (*Kesepakatan*), PG-025 (*Item dan pemenuhan*), PG-016 (*Pengadaan*), PG-030 and PG-031 (*Pengiriman*), PG-032 (*Kirim langsung*), PG-033 (*Jasa*), PG-034 (a delivery's "Retur dari Klien"), PG-036 ("Kesiapan dokumen"), PG-042 ("Buat invoice"), PG-043 (*Keuangan*), PG-046 (*Pembayaran*), PG-012 (*Penyelesaian*, *Tindakan Lain* › "Selesaikan Paksa"); "Buat Proyek" › PG-013.
  - *Pekerjaan* (PG-007) — every group, each row by its signal: QS-01, QS-03 › PG-025; QS-02 › PG-017; QS-04, QS-16 › PG-011; QS-05 › PG-022; QS-06 › PG-031; *Keluarkan dan kirim*'s link › PG-023; QS-07 › PG-040; QS-08, QS-19 › PG-036; QS-09 › PG-044 *Belum Ditagihkan* › PG-043; QS-10, QS-11 › PG-044; QS-12 › PG-048, PG-045, PG-050; QS-13 › PG-047; QS-15 › PG-011; QS-17 › PG-018; QS-18 › PG-026; QS-20, QS-21 › the record.
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

### 1.2 Laras, the Owner — phone

Lands on *Beranda* (PG-006). The same pages as §1.1; the entries differ:

- **Bottom bar** — *Beranda* (PG-006, the first two decision rows, then "Lihat semua keputusan") · *Proyek* (PG-010) · *Cari* (PG-009, full-screen, typed or scanned codes) · *Lainnya*.
- ***Lainnya* sheet** — *Kerja* › *Pekerjaan* (PG-007), *Tinjauan* (PG-008); *Menu lengkap* › the modules of §1.1; *Akun* › *Akun Saya* (PG-065), *Bahasa tampilan*, *Keluar*.
- **No "Buat"** — new work starts where it is: "Buat Proyek" on PG-010, "Mulai" on a work row (R1 a)). A `[desk]` page opens for reading and small tasks and does not break ([INFORMATION_ARCHITECTURE](../INFORMATION_ARCHITECTURE.md#information-architecture) §8).

### 1.3 Dimas, Admin Operasional — at the desk

Lands on *Pekerjaan* (PG-007). ARJ and BTN in scope, so lists carry the company filter and rows their company code (IA_PAGE_INVENTORY §4).

- **Primary entries**
  - *Pekerjaan* (PG-007) — the groups his read classes open ([PJ-13](../../05-security/PERMISSIONS_MATRIX.md#73-special-projections)): *Barang: terima, keluarkan, kirim* — QS-01, QS-03 › PG-025; QS-02 › PG-017; QS-04, QS-16 › PG-011; QS-05 › PG-016, receiving not being his (`stock.receive`); QS-06 › PG-030, the delivery record not being his (`delivery.record`); *Keluarkan dan kirim*'s link is absent for him (`stock.dispatch`); QS-17 › PG-018; QS-18's open findings and stale counts › PG-019, counting not being his (`stock.count`); QS-20 › the record — *Dokumen dan kasus* — QS-07 › PG-040; QS-08 › PG-036; QS-21 › the record — *Tagihan dan pembayaran* (`finance.view`) — QS-09 › PG-044 › PG-043; QS-10, QS-11 › PG-044; QS-12 › PG-048 and PG-045, its bank-line rows text without a link (`bank_statement.record`); QS-13 › PG-043, the settlement not being his (`settlement.record`); QS-19 › PG-036 — *Penyelesaian proyek* — QS-15 › PG-011, money items with `finance.view`.
  - *Proyek* (PG-010) › PG-011 › PG-014, PG-025, PG-016, PG-030, PG-036, PG-042, PG-043, PG-046, PG-012; "Buat Proyek" › PG-013.
- ***Menu lengkap*** — *Pembelian* › PG-015, PG-016, PG-017 · *Gudang* › *Stok* PG-018 › PG-019 · *Reservasi* PG-025 · *Lainnya* › PG-020, PG-030 — *Barang Masuk*, *Barang Keluar*, *Stok Opname*, *Penyesuaian & Kondisi*, *Retur ke Pemasok* and *Selisih & Temuan* absent · *Keuangan* › *Invoice* PG-041, PG-042, PG-043 · *Piutang* PG-044 · *Pembayaran* PG-045, PG-046 · *Lainnya* › PG-048, PG-049 — *Potongan* and *Mutasi Bank* absent · *Dokumen* › PG-035 to PG-040 · *Laporan* › PG-051, the reports of his read classes — *Laporan Gabungan* and *Ekspor Saya* absent · *Data Master* › PG-054 to PG-057 — *Impor Data Awal* absent.
- **Top bar** — PG-009; "Buat" › *Proyek* PG-013 · *Pembelian* PG-017 · *Pembayaran* PG-046 · *Unggah Dokumen* PG-038; the account menu › PG-065, *Bahasa tampilan*, *Keluar*.
- **Absent** — *Beranda*, *Tinjauan*, *Aktivitas & Keamanan*, *Pengaturan* and *Laporan Gabungan* (OWNER_ONLY; IA_PAGE_INVENTORY §3.3).

### 1.4 Dimas — phone

Lands on *Pekerjaan* (PG-007). **Bottom bar** *Pekerjaan* · *Proyek* · *Cari* · *Lainnya*; ***Lainnya*** › *Menu lengkap* (the modules of §1.3) and *Akun* (PG-065, *Bahasa tampilan*, *Keluar*) — no *Kerja* section, which is the Owner's. His `[desk]` pages — the editors of PG-013, PG-014 and PG-017, the composer PG-042 — open for reading and small tasks; the invoice's issue step follows WF-FIN-01's `[desk]` rule (S2-J03).

### 1.5 Bayu, Admin Operasional with warehouse grants — phone

Lands on *Pekerjaan* (PG-007). ARJ only in scope, so lists carry no company filter and rows no company code (INFORMATION_ARCHITECTURE §7).

- **Bottom bar** — *Pekerjaan* · *Proyek* · *Cari* · *Lainnya*.
  - *Pekerjaan* (PG-007) — "Berikutnya" first: "Terima ARJ/PB/2026/0216 — 4 baris belum dicatat" › "Mulai Terima Barang" › PG-022 (R1 f)); then *Barang: terima, keluarkan, kirim* — QS-05 › PG-022 (`stock.receive`, PJ-18); QS-06 › PG-031 (`delivery.record`); *Keluarkan dan kirim*'s link › PG-023 › PG-024 (QUERY_BUDGET_CHECKS §3.4); QS-01, QS-03 › PG-025; QS-02, QS-04, QS-16 › PG-011, purchasing not being his; QS-17 › PG-018; QS-18 › PG-026 (`stock.count`); QS-20 › the record — *Dokumen dan kasus* — QS-07 › PG-040; QS-08 › PG-036; QS-21 › the record — *Penyelesaian proyek* — QS-15's non-money items › PG-011. *Tagihan dan pembayaran* does not exist for him (no `finance.view`).
  - *Proyek* (PG-010) › PG-011 without *Posisi keuangan*, *Pengadaan* or money figures (R1 e)).
  - *Cari* (PG-009) — a scanned or typed code › PG-019.
- ***Lainnya* sheet** — *Menu lengkap* › *Gudang* › *Stok* PG-018 › PG-019 · *Barang Masuk* PG-021 › PG-022 · *Barang Keluar* PG-023 › PG-024 · *Reservasi* PG-025 · *Stok Opname* PG-026 · *Lainnya* › PG-020, PG-027, PG-030 › PG-031 — *Retur ke Pemasok* and *Selisih & Temuan* absent · *Dokumen* › PG-035 to PG-040, payloads of the operational family only (PJ-15) · *Laporan* › PG-051, the stock report · *Data Master* › PG-054 to PG-057; *Akun* › PG-065, *Bahasa tampilan*, *Keluar*.
- **Absent** — *Pembelian* and *Keuangan* (no `cost.view`, no `finance.view`), "Buat" (the phone starts work where it is), and every OWNER_ONLY area.

## 2. Reachability

Every page against every persona and a **full Admin** — an Admin holding every ADM and ADM_PLUS capability (63 of [PERMISSIONS_MATRIX](../../05-security/PERMISSIONS_MATRIX.md#permissions-matrix) §4) in all three companies, who shows every page an Admin can reach. Each cell gives the path from the landing page — *Beranda* for Laras, *Pekerjaan* for an Admin — on the desktop, for Bayu on his phone, or "absent" with the capability not held; "the same" repeats the path of the column before it, the first step on Bayu's phone going through the bottom bar or *Lainnya* (§1.5). The phone reaches the same pages as the desktop (§1.2, §1.4).

| PG | Laras (Owner) | Dimas | Bayu | Full Admin |
| --- | --- | --- | --- | --- |
| PG-001 | the sign-in address; after *Keluar* or the absolute limit | the same | the same | the same |
| PG-002 | PG-001 › password or Google | the same | the same | the same |
| PG-003 | a credential link only ([AU-12](../../05-security/SECURITY.md#2-authentication-sessions-and-credentials)) | the same | the same | the same |
| PG-004 | an enrolment-only session only (AU-18; AU-20 b) | the same | the same | the same |
| PG-005 | any address the account cannot open (AZ-11) | the same | the same | the same |
| PG-006 | landing | absent — OWNER role | absent — OWNER role | absent — OWNER role |
| PG-007 | *Pekerjaan*; *Beranda* › "Buka Pekerjaan" | landing | landing | landing |
| PG-008 | *Tinjauan*; *Beranda* › [QS-14](../../03-workflows/WORKFLOWS/s10-14-signals-decisions-traceability.md#10-derived-signals-and-action-queue) row | absent — `review.view` | absent — `review.view` | absent — `review.view` |
| PG-009 | the top bar or Ctrl K | the same | *Cari* | the top bar or Ctrl K |
| PG-010 | *Proyek* | *Proyek* | *Proyek* | *Proyek* |
| PG-011 | *Proyek* › row; *Beranda* › a decision or attention row; *Pekerjaan* › row | *Pekerjaan* › row; *Proyek* › row | the same | the same |
| PG-012 | PG-011 › *Penyelesaian* | PG-011 › *Penyelesaian*; "Selesaikan Proyek" (`projects.complete`) | PG-011 › *Penyelesaian*, read only | PG-011 › *Penyelesaian*, with "Selesaikan Proyek" |
| PG-013 | "Buat" › *Proyek*; *Proyek* › "Buat Proyek" | the same | absent — `projects.manage` | the same as Dimas |
| PG-014 | PG-011 › *Kesepakatan* | PG-011 › *Kesepakatan* | absent — `quotation.manage` | PG-011 › *Kesepakatan* |
| PG-015 | *Menu lengkap* › *Pembelian* | the same | absent — `cost.view` | the same |
| PG-016 | *Pembelian* › row; PG-011 › *Pengadaan* | the same; *Pekerjaan* › QS-05 row | absent — `cost.view` | the same as Dimas |
| PG-017 | "Buat" › *Pembelian*; *Pekerjaan* › QS-02 row; PG-011 › *Gudang* stage | the same | absent — `purchase.manage` | the same |
| PG-018 | *Menu lengkap* › *Gudang* › *Stok* | the same | *Lainnya* › *Menu lengkap* › *Gudang* › *Stok* | the same as Dimas |
| PG-019 | *Stok* › product; search or scan | the same | the same | the same |
| PG-020 | *Gudang* › *Lainnya* › *Riwayat Stok* | the same | the same | the same |
| PG-021 | *Gudang* › *Barang Masuk*; "Buat" › *Barang Masuk* | absent — `stock.receive` | *Gudang* › *Barang Masuk* | the same as Laras |
| PG-022 | *Pekerjaan* › QS-05 row; PG-021 › purchase | absent — `stock.receive` | *Pekerjaan* › "Berikutnya" › "Mulai Terima Barang"; QS-05 row; PG-021 › purchase | *Pekerjaan* › QS-05 row; PG-021 › purchase |
| PG-023 | *Gudang* › *Barang Keluar*; *Pekerjaan* › *Keluarkan dan kirim* › link | absent — `stock.dispatch` | the same as Laras | the same as Laras |
| PG-024 | PG-023 › project | absent — `stock.dispatch` | PG-023 › project | PG-023 › project |
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

**Pages reached in the shell (PG-005–PG-065, 61 pages):** Laras 61; Dimas 36; Bayu 30; the full Admin 52 — every page but the nine of the OWNER role and the OWNER_ONLY capabilities: PG-006, PG-008, PG-052 and PG-059–PG-064. The counts equal IA_PAGE_INVENTORY §8's. Every persona reaches what its grants open and nothing else: each "absent" cell names a capability §1 of IA_PAGE_INVENTORY does not give that persona.

## 3. Variations that change navigation

| Variation | What changes | Sources |
| --- | --- | --- |
| An Admin without `finance.view` — Bayu | *Pekerjaan*'s group *Tagihan dan pembayaran* does not exist; *Keuangan* is absent from *Menu lengkap*; on the journey, *Posisi keuangan* and [QS-15](../../03-workflows/WORKFLOWS/s10-14-signals-decisions-traceability.md#10-derived-signals-and-action-queue)'s money items are absent; documents of the finance family show their metadata without payload ([PJ-15](../../05-security/PERMISSIONS_MATRIX.md#73-special-projections)) | [INFORMATION_ARCHITECTURE](../INFORMATION_ARCHITECTURE.md#information-architecture) §9; R1 b) and e); PJ-13; PJ-15 |
| An Admin without `stock.view` | every pooled view is denied — *Gudang*'s stock pages, S1 search facets and props, availability on the journey —, and with it every capability that needs it (RG-06): reservation, receiving, dispatch, counting; such an Admin still sees the S2–S4 records of the families granted, never another company's (the AC-13 resolution) | PERMISSIONS_MATRIX §3 (AC-13 resolution), §4, RG-06; PJ-01 |
| One company in scope — Bayu | no company filter; a list names the company once in its header and its rows carry no company label | INFORMATION_ARCHITECTURE §7; CS-02 |
| Several companies in scope — Dimas | the filter "Tampilkan: Semua perusahaan" on every list and on *Pekerjaan*; every row carries its company code; every count is per company — "ARJ 4 · BTN 2" —, never summed | CS-08; [PF-15](../../06-api-performance/PERFORMANCE.md#4-pagination); PF-41; R1 b) |
| The Owner's OWNER_ONLY areas — Laras | *Beranda* with *Gabungan* (`reports.consolidated`), *Tinjauan* (`review.view`), *Aktivitas & Keamanan* (`audit.view`, `security_log.view`), *Pengaturan* (`companies.manage`, `users.manage`, `permissions.manage`, `tax.configure`), *Laporan Gabungan*, the attribution on *Selisih & Temuan* (`stock.resolve_unattributed`, `stock.attribute_surplus`), "Selesaikan Paksa" in *Tindakan Lain* (`projects.force_complete`), the write-off on an invoice (`receivables.write_off`), the requirement's Owner controls (OD-05, OD-06) and the import commit (`import.commit`); for an Admin each is absent, and its address answers the not-found page | PERMISSIONS_MATRIX §9 (OD-01–OD-16); RG-02; UXN-18; AZ-11 |
| A variant a check needs — Bayu without `stock.reserve` | *Reservasi* and QS-01's and QS-03's "Reservasi" actions are absent; at receipt the reservation offer is absent and the receipt is recorded normally (S2-R06) — a recorded simulation scenario, not a persona | S2-R06; [SP-19](../../00-governance/DECISION_LOG.md#dir-063-dir-064-obs-024-and-tech-032--p7-ux-re-baseline-s2-0-acceptance-s2-1-authorization-baseline-and-stage-2-documents); [SIMULATION_REGISTER](SIMULATION_REGISTER.md) |

## 4. Paths to the frequent and critical tasks

From the landing page to the task's first page, for each task of the [persona task analysis](../evidence/P7_REBASELINE_GATE.md#persona-task-analysis); a step is one choice of an entry, a row or an action.

| Persona | Task (persona task analysis) | Path | Steps |
| --- | --- | --- | --- |
| Laras | the period position and what needs attention | *Beranda* (PG-006) | 0 |
| Laras | review recorded activity | *Tinjauan* (PG-008) | 1 |
| Laras | look up a project, invoice or receivable, often on the phone | *Cari* › the record (PG-009 › PG-011, PG-043 or PG-044) | 2 |
| Laras | reports per company and consolidated | *Menu lengkap* › *Laporan* (PG-051) or *Laporan Gabungan* (PG-052); *Beranda* › "Rincian angka" | 2–3 |
| Laras | accounts, grants and credential links | *Menu lengkap* › *Pengaturan* › *Pengguna & Akses* (PG-060) | 3 |
| Laras | the attribution of unexplained losses and count surpluses | *Beranda* › the decision row › PG-029 | 1 |
| Laras | write-off | *Beranda* › the [QS-10](../../03-workflows/WORKFLOWS/s10-14-signals-decisions-traceability.md#10-derived-signals-and-action-queue) row › PG-044 › the invoice (PG-043) › "Hapuskan Piutang" | 3 |
| Laras | Force Complete | *Proyek* › row › PG-011 › *Tindakan Lain* › "Selesaikan Paksa" (PG-012) | 4 |
| Laras | numbering start and seed; tax configuration | *Pengaturan* › *Penomoran Dokumen* (PG-061) or *Pajak* (PG-062) | 3 |
| Laras | the opening-import commit | *Data Master* › *Impor Data Awal* (PG-058) | 3 |
| Laras | acting on a security notice | *Beranda* › the notice › PG-060 or PG-064 | 1 |
| Dimas | a quotation with many lines | *Proyek* › row › PG-011 › *Kesepakatan* (PG-014) | 3 |
| Dimas | a purchase with lines and charges | "Buat" › *Pembelian* (PG-017); *Pekerjaan* › QS-02 row (prefilled) | 1–2 |
| Dimas | a reservation | *Pekerjaan* › QS-01 or QS-03 row › "Reservasi" (PG-025) | 1 |
| Dimas | an invoice | *Pekerjaan* › *Penyelesaian proyek* row (QS-15, "confirmed value not yet invoiced") › PG-011 › "Buat invoice" (PG-042) | 2 |
| Dimas | billing | *Pekerjaan* › QS-09 row › PG-044 *Belum Ditagihkan* › PG-043 › "Tagihkan" | 3 |
| Dimas | a payment with its applications | "Buat" › *Pembayaran* (PG-046); PG-011 › *Pembayaran* › "Catat Pembayaran" | 1 |
| Dimas | documents and *Dokumen Administrasi* | PG-011 › "Kesiapan dokumen" › an item (PG-036) | 2–3 |
| Dimas | project setup | "Buat" › *Proyek* (PG-013) | 1 |
| Dimas | project completion | *Pekerjaan* › *Penyelesaian proyek* row › PG-011 › PG-012 | 2 |
| Dimas | a correction | the record it corrects › its correction action ([ADMIN_FLOW](../ADMIN_FLOW/README.md#admin-and-owner-flows--interaction-model) §11) | from the record |
| Dimas | confirming a duplicate warning | inside PG-046, the warning's second submission (IP-05) | 0 from the form |
| Bayu | receiving | *Pekerjaan* › "Berikutnya" › "Mulai Terima Barang" (PG-022) | 1 |
| Bayu | dispatch | *Pekerjaan* › *Keluarkan dan kirim* › the link (PG-023) › the project (PG-024) | 3 — one with OQ-03's option (b) ([OWNER_QUESTIONS](OWNER_QUESTIONS.md#oq-03--dispatch-ready-rows-in-pekerjaan)) |
| Bayu | checking stock and racks | *Cari* › a scanned or typed code (PG-019) | 2 |
| Bayu | delivery records | *Pekerjaan* › QS-06 row › "Catat Pengiriman" (PG-031) | 1 |
| Bayu | stock opname | *Pekerjaan* › QS-18 row (PG-026); *Lainnya* › *Menu lengkap* › *Gudang* › *Stok Opname* | 1–4 |
| Bayu | condition changes and losses | *Cari* › PG-019 › "Ubah Kondisi" or "Catat Penyesuaian Stok" (PG-027) | 3 |

Every frequent task of the Admin at the desk and of the warehouse persona has a path of at most three steps from *Pekerjaan*, "Buat" or the project; dispatch is the one warehouse task that needs three, because [QB-10](../../06-api-performance/PERFORMANCE.md#6-query-budgets)'s register holds no dispatch-ready signal (QUERY_BUDGET_CHECKS §3).

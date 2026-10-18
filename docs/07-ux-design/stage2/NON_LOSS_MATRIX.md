# Non-loss matrix

Status: DRAFT | Updated: 2026-10-18 | Owner: Planning

**Draft — not normative.** This is an S2-1 document of Stage 2 of the P7 UX re-baseline, written under [DIR-061](../../00-governance/DECISION_LOG.md#dir-061-dir-062-obs-023-and-tech-031--p7-ux-re-baseline-stage-2-decisions-s2-0-authorization-baseline-and-prototype-setup) SD-01, SD-05 and SD-10 and [DIR-064](../../00-governance/DECISION_LOG.md#dir-063-dir-064-obs-024-and-tech-032--p7-ux-re-baseline-s2-0-acceptance-s2-1-authorization-baseline-and-stage-2-documents) §12.3. Behaviour follows MULTIPLECORP — P0–P6 and ADMIN_FLOW —, structure follows R1, and presentation follows the Golden Frontend Reference v1.0.0, which adapts to MULTIPLECORP and never the reverse (AK-1; SD-02). [INFORMATION_ARCHITECTURE](../INFORMATION_ARCHITECTURE.md) and [DESIGN_SYSTEM](../DESIGN_SYSTEM.md) stay APPROVED under APPR-008 and under replacement, and [ADMIN_FLOW](../ADMIN_FLOW/README.md) stays in force, until a later authorized stage changes them; Stage 3 uses this draft, and nothing in it is normative.

**Sources.** Each section's identifier column is built by script from its defining document — INFORMATION_ARCHITECTURE [§8](../INFORMATION_ARCHITECTURE.md#8-screen-inventory), [§2](../INFORMATION_ARCHITECTURE.md#2-derived-navigation-requirements), [§3](../INFORMATION_ARCHITECTURE.md#3-glossary) and [§4](../INFORMATION_ARCHITECTURE.md#4-design-decisions); [WORKFLOWS §10](../../03-workflows/WORKFLOWS/s10-14-signals-decisions-traceability.md#10-derived-signals-and-action-queue), [§9](../../03-workflows/WORKFLOWS/s08-09-corrections-indivisible.md#9-indivisible-business-actions) and [WF-PRJ-03](../../03-workflows/WORKFLOWS/s05-07-08-completion-migration.md#wf-prj-03--project-completion-penyelesaian-desk); [V1_SCOPE](../../01-product/V1_SCOPE.md) — MUST SHIP, the selectable document catalog, SHOULD SHIP, DEFERRED and OUT OF SCOPE; [PERMISSIONS_MATRIX](../../05-security/PERMISSIONS_MATRIX.md) §§3, 4 and 9; ADMIN_FLOW [§10](../ADMIN_FLOW/s10-a-journeys.md#10-journeys) and [§14](../ADMIN_FLOW/s14-15-messages-scenarios.md#14-message-catalogue); DESIGN_SYSTEM [§3](../DESIGN_SYSTEM.md#3-derived-visual-requirements) and [§4](../DESIGN_SYSTEM.md#4-design-decisions); the gate record's [provisional dispositions](../evidence/P7_REBASELINE_GATE.md#provisional-dispositions) as R1's [disposition delta](../evidence/P7_REBASELINE_GATE.md#c1-rework--disposition-delta) revised them —, and its destinations from the S2-1 drafts: [IA_PAGE_INVENTORY](IA_PAGE_INVENTORY.md) (PG), [NAVIGATION_MAP](NAVIGATION_MAP.md), [WRITTEN_FLOWS](WRITTEN_FLOWS.md) (J-…, WFL) and [QUERY_BUDGET_CHECKS](QUERY_BUDGET_CHECKS.md). It completes R1's [revised non-loss map](../evidence/P7_REBASELINE_GATE.md#c1-rework--revised-non-loss-map), which said the full matrix stays Stage 3 work: S2-1 drafts the complete matrix and Stage 3 makes it normative (DIR-061 SD-05; DIR-063). No technology behaviour is stated.

*Note of 2026-10-18:* run PC-2 completed this matrix against Stage 3's drafts in [section Q](#q-stage-3--every-requirement-to-its-page-route-and-interaction) (DIR-078 SP-63); the matrix keeps its DRAFT status.

## How to read the matrix

Every row has at least one destination — a page (PG), a written flow, a place on a page, or "absent" with the rule that keeps it absent; a row without a destination would be a loss. Pages are IA_PAGE_INVENTORY's; "the journey" is the project journey workspace PG-011. Section P checks every count against its derivation.

### A. Screens — SCR-01–SCR-60

Every screen of INFORMATION_ARCHITECTURE §8 with its pages, its entry points and the R1 element that places it ([R1's revised screens](../evidence/P7_REBASELINE_GATE.md#revised-screens-scr-01scr-60); IA_PAGE_INVENTORY §5). The five DECIDE-IN-STAGE-2 notes carry IA_PAGE_INVENTORY §7's proposed resolutions.

| SCR | Screen | Pages | Entry points | R1 element · note |
| --- | --- | --- | --- | --- |
| SCR-01 | Masuk | PG-001, PG-002 | the sign-in address; after logout or the absolute limit | R1 k), RS-1 — the second factor ([AU-16](../../05-security/SECURITY.md#2-authentication-sessions-and-credentials)) |
| SCR-02 | Atur Kata Sandi | PG-003, PG-004 | the Owner's credential link only | DECIDE-IN-STAGE-2 → IA_PAGE_INVENTORY §7.1: the enrolment after sign-in in the enrolment-only session (AU-18; AU-20) |
| SCR-03 | Akun Saya | PG-065 | the account menu › *Akun Saya*; the phone's *Lainnya* › *Akun* | DECIDE-IN-STAGE-2 → §7.2: AU-13's self-service on one page |
| SCR-04 | Halaman Sistem | PG-005 | any address the account cannot open (AZ-11) | unchanged |
| SCR-05 | Beranda (Owner) | PG-006; its operational groups on PG-007 | primary — the Owner's landing page | R1 c), RS-3 — the Owner's queues moved to *Pekerjaan* ([OC-05](../../00-governance/DECISION_LOG.md#dir-055-dir-056-obs-020-and-tech-028--p7-ux-re-baseline-checkpoint-c1-decision-rework-authorization-baseline-and-rework)) |
| SCR-06 | Beranda — Antrean Tindakan (Admin) | PG-007 | primary — the Admin's landing page and the Owner's entry | R1 b), RS-4, RS-5 |
| SCR-07 | Tinjauan Owner | PG-008 | primary (Owner); *Beranda*'s QS-14 row | R1 a) |
| SCR-08 | Cari | PG-009 | the top bar, Ctrl K, the phone's *Cari* | R1 a) |
| SCR-09 | Daftar Proyek | PG-010 | primary | R1 d), RS-6 — as [QB-01](../../06-api-performance/PERFORMANCE.md#6-query-budgets) fits it (QUERY_BUDGET_CHECKS §2.5; OQ-02) |
| SCR-10 | Detail Proyek | PG-011 | primary, from *Proyek*, *Pekerjaan*, *Beranda* and search | R1 e), RS-7 — the journey workspace |
| SCR-11 | Formulir Proyek | PG-013 | "Buat" › *Proyek*; "Buat Proyek"; *Tindakan Lain* › "Ubah proyek" | R1 a), e) |
| SCR-12 | Daftar Penawaran | PG-037 | *Menu lengkap* › *Dokumen* › *Penawaran* | R1's revised map |
| SCR-13 | Penawaran | PG-014 | the journey's *Kesepakatan* stage; *Rincian proyek* | journey model c) |
| SCR-14 | Penyelesaian Proyek | PG-011 ("Yang tersisa sebelum selesai"), PG-012 | the *Penyelesaian* stage; *Pekerjaan* › *Penyelesaian proyek*; *Beranda*'s QS-15 row | R1 e); journey model d) |
| SCR-15 | Daftar Pembelian | PG-015 | *Menu lengkap* › *Pembelian* | R1 a) |
| SCR-16 | Detail Pembelian | PG-016 | the purchase list; a work row; *Rincian proyek* › *Pengadaan* | R1's revised map |
| SCR-17 | Formulir Pembelian | PG-017 | "Buat" › *Pembelian*; the QS-02 row, prefilled; the journey's *Gudang* stage | R1's connected work, RS-9 |
| SCR-18 | Stok | PG-018 | *Gudang* › *Stok* | RS-2 |
| SCR-19 | Detail Stok Produk | PG-019 | *Stok*; search; scan | R1's revised map |
| SCR-20 | Riwayat Stok | PG-020 | *Gudang* › *Lainnya* › *Riwayat Stok* | dense (OC-09) |
| SCR-21 | Barang Masuk | PG-021, PG-022 | the work rows and "Berikutnya" › "Mulai Terima Barang"; "Buat" › *Barang Masuk*; *Gudang* › *Barang Masuk* | R1 f), RS-10 — worklist and task split (IA_PAGE_INVENTORY §5) |
| SCR-22 | Barang Keluar | PG-023, PG-024 | *Pekerjaan*'s link; *Gudang* › *Barang Keluar*; the *Gudang* stage | worklist and task split; dispatch-ready rows as QB-10 fits them (QUERY_BUDGET_CHECKS §3.4; OQ-03) |
| SCR-23 | Reservasi | PG-025 | *Gudang* › *Reservasi*; QS-01 and QS-03 rows; *Item dan pemenuhan* | R1's revised map |
| SCR-24 | Stok Opname | PG-026 | *Gudang* › *Stok Opname*; QS-18 rows | R1's revised map |
| SCR-25 | Penyesuaian & Kondisi | PG-027 | *Gudang* › *Lainnya*; the product page | R1's revised map |
| SCR-26 | Retur ke Pemasok | PG-028 | *Gudang* › *Lainnya*; the purchase page | R1's revised map |
| SCR-27 | Selisih & Temuan | PG-029 | *Beranda*'s decision rows (QS-22, QS-18); *Gudang* › *Lainnya* | R1 c) |
| SCR-28 | Pengiriman | PG-030 | QS-06 rows; *Rincian proyek* › *Pengiriman*; *Gudang* › *Lainnya* › *Pengiriman* | R1's revised map |
| SCR-29 | Catat Pengiriman | PG-031 | the journey's primary action "Catat Pengiriman"; the work row | R1 e) |
| SCR-30 | Kirim Langsung | PG-032 | the journey's *Kirim langsung* stage | journey model a) |
| SCR-31 | Serah Terima Jasa | PG-033 | the journey's *Jasa* stage and primary action | journey model a) |
| SCR-32 | Retur dari Klien | PG-034 | a delivery on *Rincian proyek* › *Pengiriman* or on PG-030 › "Retur dari Klien" | DECIDE-IN-STAGE-2 → IA_PAGE_INVENTORY §7.3: from the delivery it corrects (ADMIN_FLOW §11) |
| SCR-33 | Kasus Koreksi | PG-040 | *Pekerjaan* › *Dokumen dan kasus* (QS-07); the journey's *Retur dan koreksi*; *Dokumen* › *Kasus Koreksi* | DECIDE-IN-STAGE-2 → §7.4: the cross-project home under *Dokumen* |
| SCR-34 | Dokumen | PG-035 | *Menu lengkap* › *Dokumen*; "Kesiapan dokumen" › "Semua dokumen" | RS-8 |
| SCR-35 | Detail Dokumen | PG-036 | document readiness; the document list | R1's connected work b); VC1's composer |
| SCR-36 | Bukti | PG-038 | *Dokumen* › *Bukti*; "Buat" › *Unggah Dokumen*; readiness "Unggah" | RS-8 |
| SCR-37 | Dokumen Administrasi | PG-039 | the journey's readiness on PG-011 ("Kesiapan dokumen"); *Dokumen* › *Dokumen Administrasi* | D-IA-10 ADAPT (R1's delta) |
| SCR-38 | Daftar Invoice | PG-041; "Buat Invoice" PG-042 | *Keuangan* › *Invoice* | R1's revised map |
| SCR-39 | Detail Invoice | PG-042 (the draft's composer), PG-043 | the journey's *Invoice* stage; readiness; *Rincian proyek* › *Keuangan*; QS-09–QS-11 rows | R1's connected creation, RS-8; VC1's composer, AN-4 |
| SCR-40 | Piutang | PG-044 | *Keuangan* › *Piutang*; *Beranda*'s QS-10 and QS-11 rows | R1 c) |
| SCR-41 | Pembayaran | PG-045 | *Keuangan* › *Pembayaran* | R1's revised map |
| SCR-42 | Catat Pembayaran | PG-046 | "Buat" › *Pembayaran*; the journey's *Pembayaran* stage | R1 a) |
| SCR-43 | Potongan | PG-047 | *Keuangan* › *Potongan*; QS-13 rows | R1's revised map |
| SCR-44 | Kredit Pelanggan | PG-048 | *Keuangan* › *Lainnya*; QS-12 rows | R1's revised map |
| SCR-45 | Pengeluaran & Kas Lain | PG-049 | *Keuangan* › *Lainnya* | R1's revised map |
| SCR-46 | Mutasi Bank | PG-050 | *Keuangan* › *Lainnya* | dense (OC-09) |
| SCR-47 | Laporan | PG-051 | *Menu lengkap* › *Laporan* | dense (OC-09) |
| SCR-48 | Laporan Gabungan | PG-052 | *Laporan* › *Laporan Gabungan*; *Beranda*'s "Rincian angka" | Owner only; dense |
| SCR-49 | Ekspor Saya | PG-053 | *Laporan* › *Ekspor Saya* | R1's revised map |
| SCR-50 | Klien | PG-054 | *Menu lengkap* › *Data Master* › *Klien* | R1's revised map |
| SCR-51 | Pemasok | PG-055 | *Data Master* › *Pemasok* | R1's revised map |
| SCR-52 | Produk & Jasa | PG-056 | *Data Master* › *Produk & Jasa* | R1's revised map |
| SCR-53 | Lokasi Rak | PG-057 | *Data Master* › *Lokasi Rak* | R1's revised map |
| SCR-54 | Perusahaan | PG-059 | *Menu lengkap* › *Pengaturan* › *Perusahaan* | Owner only |
| SCR-55 | Penomoran Dokumen | PG-061 | *Pengaturan* › *Penomoran Dokumen* | Owner only |
| SCR-56 | Pajak | PG-062 | *Pengaturan* › *Pajak* | Owner only |
| SCR-57 | Pengguna & Akses | PG-060 | *Pengaturan* › *Pengguna & Akses*; *Beranda*'s security notice | DECIDE-IN-STAGE-2 → §7.5: H7-17's account screens in the account's detail |
| SCR-58 | Impor Data Awal | PG-058 | *Data Master* › *Impor Data Awal* | R1's revised map |
| SCR-59 | Riwayat Aktivitas | PG-063 | *Menu lengkap* › *Aktivitas & Keamanan* › *Riwayat Aktivitas* | Owner only; dense |
| SCR-60 | Log Keamanan | PG-064 | *Aktivitas & Keamanan* › *Log Keamanan*; *Beranda*'s security notice | Owner only; dense |

### B. Signals — QS-01–QS-22

Where each signal appears, per persona — page and group —, with its capability condition: each count per company, never summed for an Admin (PJ-13; CS-08; PF-15). A row leads to the page where its next action happens for an account holding the action's capability, otherwise to the record (INFORMATION_ARCHITECTURE §9.1; [R1's revised signals](../evidence/P7_REBASELINE_GATE.md#revised-signals-qs-01qs-22); NAVIGATION_MAP §1).

| QS | Signal | Owner — Laras | Admin — Dimas · Bayu | Also | Capability condition |
| --- | --- | --- | --- | --- | --- |
| QS-01 | Confirmed WAREHOUSE demand not yet reserved | PG-007 › *Barang* | PG-007 › *Barang* — both | the *Gudang* stage; PG-025 "Reservasi" | RD-02 (`stock.view`) with its project part RD-03 (`projects.view`) |
| QS-02 | Shortage → purchase needed | PG-006 "Proyek yang perlu perhatian"; PG-007 | PG-007 › *Barang* › *Siapkan barang* — both | the journey's blocker "Stok kurang"; PG-017 prefilled for `purchase.manage` | RD-02, RD-03 |
| QS-03 | Received goods for a project awaiting reservation | PG-007 › *Barang* | PG-007 › *Barang* — both | the *Gudang* stage; PG-025 | RD-02, RD-03 |
| QS-04 | Reservations cut / project demand flagged | PG-007 › *Barang* | PG-007 › *Barang* — both | the journey's blocker on *Gudang* (PG-011) | RD-02, RD-03 |
| QS-05 | Open purchase remainders | PG-007 › *Barang* › *Terima barang* | Dimas PG-007 › *Terima barang* → PG-016; Bayu PG-007's "Berikutnya" and rows → PG-022 | PG-021, the receiving worklist, and PG-022 for `stock.receive`; PG-016 for `cost.view` | RD-05, or `stock.receive` through PJ-18 |
| QS-06 | Dispatched or shipped but not delivered | PG-007 › *Barang* › *Keluarkan dan kirim* | PG-007 › *Keluarkan dan kirim* — Dimas → PG-030; Bayu → PG-031 | the *Pengiriman* stage; PG-031 for `delivery.record`, otherwise PG-030 | RD-03 |
| QS-07 | Open discrepancies and correction cases, incl. unbacked-refund and unresolved-deduction residuals | PG-007 › *Dokumen dan kasus* | PG-007 › *Dokumen dan kasus* — both | the *Retur dan koreksi* condition; PG-040 | RD-03; money residuals RD-04 or RD-05 |
| QS-08 | Render pending/failed | PG-007 › *Dokumen dan kasus* | PG-007 › *Dokumen dan kasus* — both | readiness "PDF gagal dibuat"; PG-036 | RD-03 |
| QS-09 | Issued invoices BELUM DITAGIHKAN | PG-007 › *Tagihan dan pembayaran* | Dimas PG-007 › *Tagihan dan pembayaran*; Bayu absent | the *Penagihan* stage; PG-044 *Belum Ditagihkan* → PG-043 "Tagihkan" | RD-04 (`finance.view`) |
| QS-10 | Overdue receivables by aging bucket | PG-006 "Perlu keputusan Anda"; the chart "Umur piutang"; the overdue part of *Piutang aktif* | Dimas PG-007 › *Tagihan dan pembayaran*; Bayu absent | the journey's blocker on *Pembayaran* (QUERY_BUDGET_CHECKS §4); PG-044 | RD-04 |
| QS-11 | Disputed receivables | PG-006 "Perlu keputusan Anda" | Dimas PG-007 › *Tagihan dan pembayaran*; Bayu absent | PG-044 *Sengketa* | RD-04 |
| QS-12 | Unapplied customer credit (incl. credit linked to a written-off invoice); flagged unevidenced payments; bank statement lines not yet fully cited | PG-007 › *Tagihan dan pembayaran*; credit linked to a written-off invoice also in PG-008 | Dimas PG-007 › *Tagihan dan pembayaran*; Bayu absent | PG-048, PG-045; PG-050 for `bank_statement.record`, otherwise a row without a link | RD-04 |
| QS-13 | Claimed remittance deductions not yet evidenced (claimed − evidenced) | PG-007 › *Tagihan dan pembayaran* | Dimas PG-007 › *Tagihan dan pembayaran*; Bayu absent | PG-047 for `settlement.record`, otherwise PG-043 | RD-04 |
| QS-14 | Owner review queue: every ADM+ correction/closure, deduction settlements, overridden warnings | PG-006 "Aktivitas baru untuk ditinjau"; PG-008 | absent — no entry, count or badge | — | RD-09 (`review.view`, OWNER_ONLY) |
| QS-15 | Completion blockers per project; invoicing above delivered value; confirmed value not yet invoiced | PG-006 — decisions and "Proyek yang perlu perhatian"; PG-007 › *Penyelesaian proyek* | PG-007 › *Penyelesaian proyek* — both; money items with `finance.view` | the journey's "Yang tersisa sebelum selesai"; PG-012 | RD-03; money items RD-04 |
| QS-16 | Residual obligations of force-completed/cancelled projects (incl. release-required reservations) | PG-006 "Kewajiban tersisa" | PG-007 › *Barang* — both | the project's outcome view (PG-011) | RD-03; money items RD-04 |
| QS-17 | Low stock / restock advice | PG-007 › *Barang* | PG-007 › *Barang* — both | PG-018 filtered "di bawah minimum"; "Buat Pembelian" advice only ([BR-INV-10](../../02-domain/BUSINESS_RULES.md#inventory-inv)) | RD-02 |
| QS-18 | Count findings, stale counts, unattributed surplus (Owner) | attribution PG-006 "Perlu keputusan Anda" → PG-029; findings and stale counts PG-007 › *Barang* | PG-007 › *Barang* — findings and stale counts; Dimas → PG-019, Bayu → PG-026 | PG-026; PG-029 | RD-02; attribution RD-09 |
| QS-19 | Pre-payment Kuitansi not yet linked or voided | PG-007 › *Tagihan dan pembayaran* | Dimas PG-007 › *Tagihan dan pembayaran*; Bayu absent | the journey's blocker on *Pembayaran*; PG-036 the Kuitansi; PG-043 | RD-04 |
| QS-20 | Routed blockers needing other-company grants | PG-006 "Penolakan diteruskan kepada Anda" | PG-007 › *Barang* — both, for refusals whose every company is in scope | the refused command's record | the Owner, and accounts with every company of the refused command in scope (PJ-13) |
| QS-21 | Duplicate warnings overridden or confirmed (payments, evidence, fungible stock and delivery events) | PG-007 › *Dokumen dan kasus*; each also in QS-14 | PG-007 › *Dokumen dan kasus* — by family | the record | per family — RD-02, RD-03, RD-04, RD-08 |
| QS-22 | Pending unexplained-loss attribution cases (Owner) with their recognition snapshots, and their separate unattributed display in company stock and profit views (`DIR-027`: they block no consumption) | PG-006 "Selisih menunggu penetapan Anda" → PG-029 | not in the Admin's work | the pending line on PG-018 and PG-019 (MSG-41), blocking nothing (DIR-027) | RD-09 for the cases; RD-02 for the pending quantities |

### C. Documents — DOC-01–DOC-14 and WF-DOC-02's uploads

Where each document is prepared, previewed, issued, printed or uploaded (RS-8; document readiness, [R1's connected work b)](../evidence/P7_REBASELINE_GATE.md#b-document-readiness)). Common to all fourteen: each is reachable from the journey's "Kesiapan dokumen" — required and selected items first, the others behind "Dokumen lain yang bisa disiapkan" —; its draft is composed on PG-036 and previewed with "DRAF — belum terbit" (GD-07; DC-2; [DOCUMENT_PREVIEW_PRODUCER](DOCUMENT_PREVIEW_PRODUCER.md)); its issue is IP-08's confirmation (AX-15, or AX-16 for the invoice layouts); its rendition is printed or downloaded from PG-036 once *PDF siap* (IP-12); it is listed on PG-035 with its payload by family (PJ-15).

| DOC | Document | Prepared | Previewed and issued | Printed or uploaded |
| --- | --- | --- | --- | --- |
| DOC-01 | Penawaran | PG-014, from the project's lines (J-QUO-01) | PG-014 "Terbitkan Penawaran" (AX-15) | its rendition; listed on PG-037 |
| DOC-02 | Purchase Order / PO | PG-016, after the purchase is saved (J-PUR-01) | PG-016 "Terbitkan PO" (AX-15) | its rendition |
| DOC-03 | Surat Jalan / Faktur Pengiriman | PG-024, from the committed dispatch (J-INV-03; L-19) | PG-036 "Terbitkan Surat Jalan" (AX-15) | its rendition; the signed copy uploaded as DELIVERY_PROOF on PG-031 (IP-16) |
| DOC-04 | Nota | PG-042, the layout *Nota* of the invoice record (L-26) | PG-042 or PG-043 "Terbitkan Invoice" (AX-16) | its rendition |
| DOC-05 | Kuitansi | receipt mode from the recorded payment (PG-045, PG-043, readiness); the pre-payment mode from PG-043 (J-FIN-08) | PG-036 "Terbitkan" (AX-15); the pre-payment Kuitansi with AX-31, *Menunggu Pembayaran* until linked | its rendition |
| DOC-06 | Invoice | PG-042, the composer (J-FIN-01; S2-J03) | PG-042 or PG-043 "Terbitkan Invoice" with MSG-39 (AX-16) | its rendition |
| DOC-07 | Lampiran Kuitansi | from its issued Kuitansi, in readiness (R1's connected work a)) | PG-036 (AX-15) | its rendition |
| DOC-08 | BAST | PG-031 or PG-033, from the handover facts (J-FUL-02, J-FUL-04) | PG-033 beside its preview (D-DS-23) or PG-036 (AX-15) | its rendition; the client's signed BAST uploaded through readiness "Unggah" (DC-9) |
| DOC-09 | SPK | PG-011 after the confirmation (J-PRJ-02) | PG-036 (AX-15) | its rendition |
| DOC-10 | Berita Acara Pemeriksaan Barang | PG-031, from the inspection facts (J-FUL-02) | PG-036 (AX-15) | its rendition |
| DOC-11 | HPS | readiness, "Dokumen lain yang bisa disiapkan" | PG-036 (AX-15) | its rendition; a client's or third party's HPS stays an upload (THIRD_PARTY_HPS) |
| DOC-12 | Nota Terima Barang | PG-022's summary, on request (J-INV-01; S2-J05) | PG-036 "Terbitkan Nota Terima Barang" (AX-15) | its rendition |
| DOC-13 | Surat Permintaan Pembayaran | after billing, from PG-043 or readiness — "Menunggu: invoice terbit" before (J-FIN-02) | PG-036 (AX-15) | its rendition |
| DOC-14 | Surat Pesanan | PG-016, the PO's layout *Surat Pesanan* (J-PUR-01) | PG-036 (AX-15) | its rendition; a client-issued order uploaded as CLIENT_ORDER |
| WF-DOC-02 | External uploads | PG-038's upload sheet; "Buat" › *Unggah Dokumen*; an upload requirement's "Unggah" in readiness; a record's proof field (IP-16) | registration (NX-07); a new version with "Jadikan Versi Berlaku" (NX-14) | opened only as a download; an image as its re-encoded preview (IP-16) |

### D. Owner-only actions — OD-01–OD-16

The Owner's page and control for each denial of [PERMISSIONS_MATRIX §9](../../05-security/PERMISSIONS_MATRIX.md#9-owner-only-denials); for an Admin each control is absent and its address answers the not-found page (AZ-11; UXN-18), as [R1's Owner-only map](../evidence/P7_REBASELINE_GATE.md#owner-only-actions-permissions_matrix-9) placed them.

| OD | Denial | The Owner's page and control | For an Admin |
| --- | --- | --- | --- |
| OD-01 | Accounts | PG-060 — create, edit, activate, deactivate; "Buat Tautan Atur Kata Sandi"; TOTP reset and Google unlink, each with the step-up and a reason; *Beranda*'s security notice | absent — *Pengaturan* is not in an Admin's menu |
| OD-02 | Grants | PG-060 — role, company and capability grants with the preview (D-DS-09) | absent |
| OD-03 | Company master, bank accounts, numbering and identity assets | PG-059 (company master, bank accounts, identity assets); PG-061 (numbering) | absent; identity fields read where they print (PJ-19) |
| OD-04 | Tax configuration | PG-062 | absent; configured values read-only in forms |
| OD-05 | Waiver eligibility | the requirement's waiver-eligibility control in "Kesiapan dokumen" (PG-011) and on PG-039 | absent; an Admin may only add or tighten |
| OD-06 | Requirement relaxation | the requirement's relaxation controls, same places | absent; an Admin may only add or tighten |
| OD-07 | Write-off supersession | PG-043, in the same void or revision; "Terapkan dan Cabut Penghapusan" for recovered money | absent |
| OD-08 | Found stock | PG-029 "Tetapkan Surplus" (AX-07), from *Beranda*'s QS-18 row | absent; an Admin's count application leaves the finding open |
| OD-09 | Unexplained-loss resolution | PG-029 "Tetapkan Perusahaan" and "Balikkan Penetapan" (AX-37), from *Beranda*'s QS-22 row | absent |
| OD-10 | DIR-027 evidence exception — `stock.resolve_unattributed_evidence` records a resolution only when the server verifies all of | PG-029 "Tetapkan dengan Bukti" — the DIR-027 evidence exception, also the Owner's | present only with `stock.resolve_unattributed_evidence` (the full Admin); absent for Dimas and Bayu |
| OD-11 | Write-off | PG-043 "Hapuskan Piutang" (AX-24), from *Beranda*'s QS-10 and QS-11 rows through PG-044 | absent |
| OD-12 | Force Complete | the journey's *Tindakan Lain* › "Selesaikan Paksa" → PG-012 (AX-26) | absent; the address answers IP-21 |
| OD-13 | Opening import commit | PG-058, the commit after the joint sign-off (AX-28) | absent; preparation with `import.prepare` |
| OD-14 | Owner-only views | *Gabungan* on PG-006; PG-052; PG-008; PG-063; PG-064; all-company scope | absent |
| OD-15 | A decision of OWN! authority recorded in error is superseded only by the Owner — a write-off, a surplus attribution or an Owner resolution | the decision's record, reached from PG-008 and PG-063 (CM-27) | absent; `decisions.supersede` covers ADM and ADM+ decisions only |
| OD-16 | Consistency | no action — a consistency rule; anomalies listed on PG-064 (LG-02) and in PG-008's *Keamanan* | — |

### E. Capabilities — all 80 of PERMISSIONS_MATRIX §4

Each capability with its authority class; for a command capability, the pages and controls where it is exercised; for a view capability, the pages and field families it opens (PERMISSIONS_MATRIX §7). A control is present only with its capability (AZ-01; H7-03).

| Capability | Class | Exercised on, or opens |
| --- | --- | --- |
| `stock.view` | ADM | opens S1, pool-wide — PG-018, PG-019, PG-020, PG-057; availability on the journey; scan lookups (PG-009); prerequisite of the warehouse commands (RG-06) |
| `projects.view` | ADM | opens S2 — PG-010, PG-011, PG-012 read, PG-030, PG-035–PG-040; the RD-03 groups of PG-007; master relations on PG-054 (PJ-10) |
| `finance.view` | ADM | opens S3 finance — PG-041, PG-043–PG-045, PG-048, PG-049; *Posisi keuangan* on PG-011; PG-007's *Tagihan dan pembayaran*; values on PG-010 and PG-037; finance-family payloads (PJ-15) |
| `cost.view` | ADM | opens S3 cost — PG-015, PG-016; *Pengadaan* on PG-011; expenses on PG-049; lot cost on PG-019; supplier price history on PG-055 (PJ-09) |
| `profit.view` | ADM | opens profitability — *Biaya dan laba* on PG-011 with `cost.view`; the profit report on PG-051 (RD-06) |
| `evidence.view` | ADM | opens S4 by family — PG-038; proofs on record pages (PJ-08) |
| `reports.export` | ADM | "Ekspor" on PG-051 and PG-052; PG-053 |
| `reports.consolidated` | OWNER_ONLY | *Gabungan* on PG-006; PG-052 (OD-14) |
| `review.view` | OWNER_ONLY | PG-008; QS-14 on PG-006 |
| `audit.view` | OWNER_ONLY | PG-063 |
| `security_log.view` | OWNER_ONLY | PG-064 |
| `scope.all_companies` | OWNER_ONLY | no control of its own — the Owner's scope over every company in every branch list and signal (RG-03; CS-01) |
| `users.manage` | OWNER_ONLY | PG-060 — accounts, credential links, TOTP reset, Google unlink, behind the step-up (WFL-09) |
| `permissions.manage` | OWNER_ONLY | PG-060 — role, company and capability grants, the preview |
| `companies.manage` | OWNER_ONLY | PG-059; PG-061 |
| `tax.configure` | OWNER_ONLY | PG-062 |
| `requirements.set_waiver_eligibility` | OWNER_ONLY | the requirement's control in "Kesiapan dokumen" (PG-011) and on PG-039 (OD-05) |
| `requirements.relax` | OWNER_ONLY | the requirement's relaxation controls, same places (OD-06) |
| `writeoff.supersede` | OWNER_ONLY | PG-043 inside a void or revision; "Terapkan dan Cabut Penghapusan" (OD-07) |
| `stock.attribute_surplus` | OWNER_ONLY | PG-029 "Tetapkan Surplus" (AX-07) |
| `stock.resolve_unattributed` | OWNER_ONLY | PG-029 "Tetapkan Perusahaan", "Balikkan Penetapan" (AX-37) |
| `receivables.write_off` | OWNER_ONLY | PG-043 "Hapuskan Piutang" (AX-24) |
| `projects.force_complete` | OWNER_ONLY | PG-012 "Selesaikan Paksa", from the journey's *Tindakan Lain* (AX-26) |
| `import.commit` | OWNER_ONLY | PG-058, the commit (AX-28) |
| `parties.manage` | ADM | PG-054, PG-055 — create, edit, archive; inline in PG-013 and PG-017 (J-MD-02, J-MD-03) |
| `products.edit` | ADM | PG-056; "Daftarkan Barcode" from a scan (IP-15) |
| `warehouse.manage` | ADM | PG-057 |
| `projects.manage` | ADM | PG-013; "Buat" › *Proyek*; *Tindakan Lain* › "Ubah proyek"; "Ubah Cara Pemenuhan" in *Item dan pemenuhan* |
| `quotation.manage` | ADM | PG-014 — draft, "Terbitkan Penawaran", "Buat Revisi", a rejection |
| `projects.confirm` | ADM | PG-014 "Catat Jawaban Klien"; PG-011 "Konfirmasi Proyek" (AX-13) |
| `stock.reserve` | ADM | PG-025 "Reservasi", "Kurangi Reservasi", "Lepas"; *Item dan pemenuhan*; the offer on PG-022 (AX-04); the cut block (IP-20) |
| `purchase.manage` | ADM | PG-017 "Simpan"; "Buat" › *Pembelian*; "Tambahkan Pembelian Susulan" on PG-016 |
| `stock.receive` | ADM | PG-021, PG-022 "Catat Barang Masuk"; "Buat" › *Barang Masuk*; PG-016 "Terima Barang" |
| `stock.dispatch` | ADM | PG-023, PG-024 "Catat Barang Keluar" |
| `stock.move` | ADM | PG-019 and PG-018 "Pindah Lokasi" |
| `stock.count` | ADM | PG-026 "Mulai Hitungan" and the count lines; PG-019 "Mulai Hitungan" |
| `delivery.record` | ADM | PG-031 "Catat Pengiriman"; the journey's primary action; the QS-06 row |
| `dropship.confirm` | ADM | PG-032 "Konfirmasi Kirim Langsung" |
| `handover.record` | ADM | PG-033 |
| `documents.issue` | ADM | PG-036 "Terbitkan"; PG-016 (PO), PG-022 (DOC-12), PG-024 (DOC-03), PG-031 (DOC-08, DOC-10), PG-033 (BAST), PG-043 (the pre-payment Kuitansi, DOC-13) |
| `evidence.upload` | ADM | PG-038's upload sheet; "Buat" › *Unggah Dokumen*; readiness "Unggah"; proof fields on PG-022, PG-031, PG-046 |
| `invoice.issue` | ADM | PG-042 "Simpan Draf", "Terbitkan Invoice"; PG-041 "Buat Invoice" |
| `billing.record` | ADM | PG-043 "Tagihkan"; PG-044 |
| `payment.record` | ADM | PG-046 "Catat Pembayaran" with its same-command applications; "Tautkan Kuitansi" |
| `payment.apply` | ADM | PG-045 "Terapkan"; PG-048 "Terapkan ke Invoice" |
| `expense.record` | ADM | PG-049 "Catat Beban" |
| `disbursement.record` | ADM | PG-049 "Catat Pengeluaran Kas" |
| `cashin.record` | ADM | PG-049 "Catat Kas Masuk Lain" |
| `bank_statement.record` | ADM | PG-050 "Catat Mutasi Bank" |
| `requirements.manage` | ADM | "Kesiapan dokumen" on PG-011 — add, tighten, link; PG-039 |
| `projects.complete` | ADM | PG-012 "Selesaikan Proyek" (AX-25) |
| `receivables.dispute` | ADM | PG-043 "Tandai Sengketa", "Cabut Sengketa" |
| `requirements.waive` | ADM | "Tandai Tidak Berlaku" in readiness and on PG-039 |
| `import.prepare` | ADM | PG-058 — prepare, dry run, sign-off |
| `stock.reverse` | ADM_PLUS | "Balikkan Penerimaan" on PG-016 and PG-020 (CM-02, CM-03); the dispatch's reversals (CM-05, CM-06); "Balikkan" on PG-020; CM-34, CM-36, CM-37 from their records |
| `sales_return.record` | ADM_PLUS | PG-034 "Retur dari Klien", from a delivery; drop-ship returns from PG-032 (CM-14, CM-15) |
| `purchase_return.record` | ADM_PLUS | PG-028 "Retur ke Pemasok", from PG-016 |
| `purchase.correct_cost` | ADM_PLUS | PG-016 "Koreksi Harga, Pajak atau Biaya" (CM-17, CM-35) |
| `finance.contra` | ADM_PLUS | corrections on PG-045, PG-047 and PG-049 (SF-CONTRA) |
| `payment.reallocate` | ADM_PLUS | the reallocation on PG-045 (CM-21) |
| `invoice.correct` | ADM_PLUS | PG-043 — void or downward revision (CM-23, CM-24) |
| `documents.correct` | ADM_PLUS | PG-036 "Revisi", "Batalkan Dokumen" (CM-26) |
| `billing.correct` | ADM_PLUS | PG-043 — the billing act's correction (CM-25) |
| `decisions.supersede` | ADM_PLUS | the decision's record — an approval on PG-014, a waiver in readiness or on PG-039 (CM-27) |
| `fulfillment.correct` | ADM_PLUS | PG-031 "Koreksi Catatan Pengiriman" (CM-08); the handover's reversal line on PG-033; the confirmation's reversal on PG-032 |
| `warnings.override` | ADM_PLUS | the second submission of a duplicate warning on PG-046 (payments) and PG-038 (evidence) (IP-05) |
| `cash.correct` | ADM_PLUS | PG-050 — a statement line's void, a citation's correction; a claimed deduction's supersession |
| `purchase.cancel` | ADM_PLUS | PG-016 "Batalkan Pembelian" (AX-12) |
| `refund.record` | ADM_PLUS | PG-048 "Kembalikan Dana" (AX-23) |
| `settlement.record` | ADM_PLUS | PG-047 "Catat Potongan" (AX-21) |
| `stock.adjust` | ADM_PLUS | PG-027 "Catat Penyesuaian Stok"; PG-019 (AX-06, AX-30) |
| `stock.condition` | ADM_PLUS | PG-027 "Ubah Kondisi", "Periksa Ulang"; PG-019 (AX-06) |
| `stock.count_apply` | ADM_PLUS | PG-026 "Terapkan Selisih" (AX-06) |
| `stock.resolve_unattributed_evidence` | ADM_PLUS | PG-029 "Tetapkan dengan Bukti" (AX-37 under OD-10) |
| `stock.allocate_intercompany` | ADM_PLUS | no page of its own — the allocation reason inside PG-024, PG-027 and PG-028 (J-INV-07; XL-1) |
| `projects.cancel_scope` | ADM_PLUS | "Batalkan Sisa" in *Item dan pemenuhan* (AX-14) |
| `delivery.close` | ADM_PLUS | PG-031 "Tutup Pengiriman"; "Catat Hilang di Perjalanan" on PG-030 with `stock.adjust` (CM-07) |
| `purchase.close_remainder` | ADM_PLUS | PG-016 "Tutup Sisa Pembelian" (AX-32) |
| `cases.close` | ADM_PLUS | PG-040 "Tutup Kasus"; PG-028; PG-034 |
| `projects.cancel` | ADM_PLUS | the journey's *Tindakan Lain* › "Batalkan Proyek" (AX-27) |

### F. V1 capabilities — CAP-01–CAP-18

Each MUST SHIP capability of [V1_SCOPE](../../01-product/V1_SCOPE.md#must-ship) with its pages and written flows; the subject is a short label of the capability's row.

| CAP | Subject | Pages | Written flows |
| --- | --- | --- | --- |
| CAP-01 | the workspace, companies, users, roles and grants | PG-059, PG-060, PG-061 | J-MD-01, J-ACC-02, WFL-09 |
| CAP-02 | organizations, units, PICs and suppliers; purchase history | PG-054, PG-055; history on PG-016 and PG-017 (SH-01 aside) | J-MD-02, J-MD-03 |
| CAP-03 | products and services, units, barcodes, serials, price defaults | PG-056 | J-MD-04 |
| CAP-04 | the project-centred overview and the quotation | PG-010, PG-011, PG-013, PG-014, PG-037 | J-PRJ-01, J-QUO-01, J-PRJ-02, J-FUL-01 |
| CAP-05 | purchases and the optional PO | PG-015, PG-016, PG-017 | J-PUR-01, J-PUR-02 |
| CAP-06 | one stock pool with attribution: receiving, dispatch, returns, counts, conditions, allocation | PG-018–PG-029 | J-INV-01–J-INV-07, J-PUR-03 |
| CAP-07 | delivery records | PG-030, PG-031, PG-032, PG-033, PG-034 | J-FUL-02, J-FUL-03, J-FUL-04, J-FUL-05 |
| CAP-08 | the catalog of 14 generated documents | PG-011's readiness, PG-035, PG-036; section C | J-DOC-01 |
| CAP-09 | *Dokumen Administrasi* and private attachments | PG-011's readiness, PG-039, PG-038 | J-ADM-01, J-DOC-02 |
| CAP-10 | invoice, billing, receivable and payments | PG-041–PG-046 | J-FIN-01, J-FIN-02, J-FIN-03, J-FIN-05, J-FIN-08 |
| CAP-11 | the financial concepts, reports and profitability | PG-051, PG-052; the KPIs on PG-006; *Posisi keuangan* on PG-011; PG-047–PG-050 | J-FIN-04, J-FIN-06, J-FIN-07 |
| CAP-12 | the REGULAR and SIPLAH channel | PG-013's channel fields | J-PRJ-01 |
| CAP-13 | secure sign-in, logout, reset and sessions; the password profile, TOTP and Google | PG-001–PG-004, PG-065, PG-060 | J-ACC-01, WFL-05, WFL-06, WFL-09; [AUTH_JOURNEYS](AUTH_JOURNEYS.md) |
| CAP-14 | Indonesian by default, English secondary | every page; the switch on PG-001 and in the account menu | WFL-04; [LOCALIZATION_ARCHITECTURE](LOCALIZATION_ARCHITECTURE.md) |
| CAP-15 | migration of opening facts | PG-058 | J-MIG-01 |
| CAP-16 | local backups on the VPS | no page by design — operated by the human operator (INFORMATION_ARCHITECTURE §5; V1_SCOPE) | — |
| CAP-17 | dashboards, the action queue, alerts and lookup | PG-006, PG-007, PG-008, PG-009 | WFL-01, WFL-03; section B |
| CAP-18 | the completion gate and Force Complete | PG-011's "Yang tersisa sebelum selesai", PG-012 | J-PRJ-03, J-PRJ-04; section J |

### G. ADMIN_FLOW journeys — all 39

Each journey of ADMIN_FLOW §10 with its written flow ([WRITTEN_FLOWS §2](WRITTEN_FLOWS.md#2-the-admin_flow-journeys)) and its pages.

| Journey | Name | Written flow | Pages |
| --- | --- | --- | --- |
| J-ACC-01 | Session and company context | WRITTEN_FLOWS §2.1 | PG-001, PG-002, PG-006, PG-007, PG-065 |
| J-ACC-02 | Users, roles, capabilities and company grants | §2.1 | PG-060 |
| J-MD-01 | Company master | §2.1 | PG-059, PG-061, PG-062 |
| J-MD-02 | Client organization, unit and PIC | §2.1 | PG-054; inline in PG-013 |
| J-MD-03 | Supplier master | §2.1 | PG-055; inline in PG-017 |
| J-MD-04 | Catalog, units, barcodes, serial policy and price defaults | §2.1 | PG-056 |
| J-MD-05 | Locations and minimum stock | §2.1 | PG-057, PG-019 |
| J-PRJ-01 | Project setup and amendment | §2.2 | PG-010, PG-013, PG-011 |
| J-QUO-01 | Quotation cycle | §2.2 | PG-011, PG-014, PG-037 |
| J-PRJ-02 | Commercial confirmation | §2.2 | PG-014, PG-011, PG-036 |
| J-FUL-01 | Fulfilment planning per demand line | §2.2 | PG-011, PG-017 |
| J-INV-02 | Reservation management | §2.3 | PG-025, PG-011, PG-007 |
| J-PUR-01 | Project purchase | §2.3 | PG-007, PG-011, PG-017, PG-016, PG-036 |
| J-PUR-02 | Replenishment purchase | §2.3 | PG-015, PG-017, PG-018 |
| J-INV-01 | Receiving — *Barang Masuk* | §2.3 | PG-007, PG-021, PG-022, PG-016, PG-036 |
| J-INV-03 | Warehouse dispatch — *Barang Keluar* | §2.3 | PG-007, PG-023, PG-024, PG-036 |
| J-INV-07 | Inter-company allocation (embedded in dispatch) | §2.3 | inside PG-024, PG-027, PG-028; PG-011's *Pengadaan*; PG-052 |
| J-INV-04 | Condition change and quarantine | §2.3 | PG-027, PG-019 |
| J-INV-05 | Stock opname | §2.3 | PG-026, PG-007 |
| J-INV-06 | Adjustment, disposal and loss | §2.3 | PG-027, PG-019; PG-029 for pending cases |
| J-PUR-03 | Purchase return | §2.3 | PG-028, PG-016 |
| J-FUL-02 | Delivery — *Pengiriman* | §2.4 | PG-007, PG-011, PG-030, PG-031, PG-036 |
| J-FUL-03 | Drop-ship — *Kirim Langsung* | §2.4 | PG-011, PG-032 |
| J-FUL-04 | Service handover — *Serah Terima Jasa* | §2.4 | PG-011, PG-033, PG-036 |
| J-FUL-05 | Sales return | §2.4 | PG-011, PG-030, PG-034, PG-040 |
| J-DOC-01 | Generated document lifecycle | §2.5 | PG-011, PG-035, PG-036 |
| J-DOC-02 | External and uploaded documents | §2.5 | PG-038; readiness on PG-011 |
| J-ADM-01 | Administrative checklist — *Dokumen Administrasi* | §2.5 | PG-011, PG-039 |
| J-FIN-01 | Invoice issuance | §2.6 | PG-007, PG-011, PG-041, PG-042, PG-043 |
| J-FIN-02 | Billing and receivable monitoring — *Piutang* | §2.6 | PG-043, PG-044, PG-036 |
| J-FIN-03 | Payment receipt and application — *Pembayaran* | §2.6 | PG-046, PG-045, PG-044, PG-043, PG-011 |
| J-FIN-04 | Deduction settlement | §2.6 | PG-047 |
| J-FIN-05 | Dispute hold and Owner write-off | §2.6 | PG-043, PG-044, PG-006 |
| J-FIN-06 | Customer credit and refund | §2.6 | PG-048 |
| J-FIN-07 | Expenses, disbursements and other cash | §2.6 | PG-049, PG-050 |
| J-FIN-08 | Pre-payment Kuitansi | §2.6 | PG-043, PG-036, PG-046 |
| J-PRJ-03 | Project completion | §2.7 | PG-011, PG-012, PG-007, PG-006 |
| J-PRJ-04 | Project cancellation | §2.7 | PG-011 |
| J-MIG-01 | Opening facts | §2.7 | PG-058 |

### H. Indivisible actions — AX-01–AX-37

The page and control where each command of [WORKFLOWS §9](../../03-workflows/WORKFLOWS/s08-09-corrections-indivisible.md#9-indivisible-business-actions) starts, with its capability (PERMISSIONS_MATRIX §4; WRITTEN_FLOWS).

| AX | Action | Page and control | Capability |
| --- | --- | --- | --- |
| AX-01 | Reservation create/increase | PG-025 "Reservasi"; *Item dan pemenuhan* on PG-011; the same-action offer on PG-022 and at a restoration on PG-020 and PG-034 (AX-10; IP-19) | `stock.reserve` |
| AX-02 | Reservation reduce/release/cut | PG-025 "Kurangi Reservasi", "Lepas"; the cut block inside a supply-reducing form (IP-20) | `stock.reserve` |
| AX-03 | Dispatch | PG-024 "Catat Barang Keluar" | `stock.dispatch`; another company's lot `stock.allocate_intercompany` |
| AX-04 | Receiving | PG-022 "Catat Barang Masuk", with its optional reservation | `stock.receive`; the reservation `stock.reserve` |
| AX-05 | Receiving reversal | "Balikkan Penerimaan" on PG-016 or PG-020 | `stock.reverse` |
| AX-06 | Condition change / adjustment decrease / count variance | PG-027 "Ubah Kondisi", "Catat Penyesuaian Stok"; PG-026 "Terapkan Selisih" | `stock.condition`, `stock.adjust`, `stock.count_apply` |
| AX-07 | Count surplus attribution (OWN!) | PG-029 "Tetapkan Surplus" | `stock.attribute_surplus` (OWNER_ONLY) |
| AX-08 | Drop-ship confirmation | PG-032 "Konfirmasi Kirim Langsung" | `dropship.confirm` |
| AX-09 | Delivery record | PG-031 "Catat Pengiriman" | `delivery.record` |
| AX-10 | Restoration (SF-RESTORE) | PG-034, the receipt of the returned goods; the dispatch reversal's restoration (CM-05) | `sales_return.record`; `stock.reverse` |
| AX-11 | Purchase return | PG-028 "Retur ke Pemasok" | `purchase_return.record` |
| AX-12 | Purchase cancellation | PG-016 "Batalkan Pembelian" | `purchase.cancel` |
| AX-13 | Commercial confirmation / re-confirmation | PG-014 "Catat Jawaban Klien" — *Disetujui*; PG-011 "Konfirmasi Proyek" | `projects.confirm` |
| AX-14 | Remaining-scope cancellation / re-plan split | *Item dan pemenuhan* on PG-011 — "Batalkan Sisa", "Ubah Cara Pemenuhan" | `projects.cancel_scope`; the split `projects.manage` |
| AX-15 | Document issue | PG-036 "Terbitkan"; PG-014 "Terbitkan Penawaran"; PG-016 "Terbitkan PO"; PG-022 "Terbitkan Nota Terima Barang"; PG-024 "Terbitkan Surat Jalan" | `documents.issue`; DOC-01 `quotation.manage` |
| AX-16 | Invoice issue | PG-042 or PG-043 "Terbitkan Invoice" | `invoice.issue` |
| AX-17 | Billing activation / correction | PG-043 "Tagihkan"; its correction | `billing.record`; `billing.correct` |
| AX-18 | Payment recording | PG-046 "Catat Pembayaran" | `payment.record` |
| AX-19 | Payment application / reallocation | PG-045 "Terapkan"; PG-048 "Terapkan ke Invoice"; the reallocation on PG-045 | `payment.apply`; `payment.reallocate` |
| AX-20 | Payment contra | the payment's contra on PG-045 | `finance.contra` |
| AX-21 | Deduction settlement (fee/tax) | PG-047 "Catat Potongan" | `settlement.record` |
| AX-22 | Invoice void / downward revision | PG-043 — void or downward revision | `invoice.correct`; with a standing write-off `writeoff.supersede` |
| AX-23 | Refund disbursement | PG-048 "Kembalikan Dana" | `refund.record` |
| AX-24 | Owner write-off / supersession | PG-043 "Hapuskan Piutang"; "Terapkan dan Cabut Penghapusan" | `receivables.write_off`; `writeoff.supersede` (OWNER_ONLY) |
| AX-25 | Normal completion | PG-012 "Selesaikan Proyek" | `projects.complete` |
| AX-26 | Force Complete | PG-012 "Selesaikan Paksa" | `projects.force_complete` (OWNER_ONLY) |
| AX-27 | Project cancellation | the journey's *Tindakan Lain* › "Batalkan Proyek" | `projects.cancel` |
| AX-28 | Opening import batch | PG-058, the commit | `import.commit` (OWNER_ONLY); preparation `import.prepare` |
| AX-29 | Purchase-charge allocation | PG-017's charges, recorded with the save and allocated at receipt by SF-CHARGE | `purchase.manage` |
| AX-30 | Loss recognition / reversal | PG-027 "Catat Penyesuaian Stok" — the loss; its exact reversal (CM-34) | `stock.adjust`; `stock.reverse` |
| AX-31 | Pre-payment Kuitansi issue and link | PG-043 "Terbitkan Kuitansi untuk Proses Pembayaran"; "Tautkan Kuitansi" on PG-036, PG-043 or PG-046 | `documents.issue`; `payment.record` |
| AX-32 | Purchase-remainder closure | PG-016 "Tutup Sisa Pembelian" | `purchase.close_remainder` |
| AX-33 | Contra-fact (settlement, expense, disbursement, attribution) | the contra of a settlement, expense, disbursement or attribution — PG-047, PG-049 | `finance.contra` |
| AX-34 | Cost correction cascade (CM-17/35) | PG-016 "Koreksi Harga, Pajak atau Biaya" | `purchase.correct_cost` |
| AX-35 | Dispatch identity substitution (CM-36) | the dispatch record — PG-024, or *Rincian proyek* on PG-011 (CM-36) | `stock.reverse` |
| AX-36 | Reversal of a restoration or return (CM-37) | the return's record — PG-034 or PG-028 (CM-37) | `stock.reverse` |
| AX-37 | Unexplained-loss attribution (OWN!; `DIR-027`: ADM+ only for a resolution fully identified by definitive evidence) | PG-029 "Tetapkan Perusahaan"; "Tetapkan dengan Bukti" | `stock.resolve_unattributed` (OWNER_ONLY); `stock.resolve_unattributed_evidence` (ADM_PLUS, OD-10) |

### I. Messages — MSG-01–MSG-41

The pages or states where each message of [ADMIN_FLOW §14](../ADMIN_FLOW/s14-15-messages-scenarios.md#14-message-catalogue) appears; its text is canonical and quoted exactly in the journey specifications' copy sections.

| MSG | Situation | Pages or states |
| --- | --- | --- |
| MSG-01 | COMMITTED | every command's answer, on its page (IP-01) — for example PG-022's line *Tercatat*, PG-043's issued invoice, PG-045's recorded payment |
| MSG-02 | REJECTED | every command's refusal, on its page, the form and its input kept (IP-01) |
| MSG-03 | CONFLICT — stale | every form whose record changed since it was opened (IP-03) |
| MSG-04 | CONFLICT — already recorded | a command already recorded — PG-022 "Sudah Tercatat" (BD-01), PG-032 (BD-04), PG-043 (BD-06, BD-07) |
| MSG-05 | PENDING | derived work in progress — the rendition on PG-036, PG-042 and PG-043 (IP-12) |
| MSG-06 | FAILED — not processed | every command page, when nothing was processed (IP-04) |
| MSG-07 | FAILED — outcome unknown | every command page with an unknown outcome, and the covered page after re-authentication (IP-04; WFL-05) |
| MSG-08 | A failed rendition | a failed rendition — PG-036, PG-043; readiness *PDF gagal dibuat*; QS-08 |
| MSG-09 | Not found — nonexistent or outside scope | PG-005, the not-found page (WFL-07) |
| MSG-10 | Forbidden | PG-005's forbidden state (WFL-07) |
| MSG-11 | Session ended, in-place | the in-place re-authentication over any page (WFL-05; [AUTH_JOURNEYS](AUTH_JOURNEYS.md)) |
| MSG-12 | Validation summary | every form — beside the field (IP-14) |
| MSG-13 | Network loss before sending | every form, when the browser was offline before sending |
| MSG-14 | Routed refusal | a routed refusal — PG-024, PG-027, PG-028 and the reservation cut (IP-06; IP-20) |
| MSG-15 | Duplicate warning | a duplicate warning — PG-022, PG-024, PG-031, PG-046, PG-038 (IP-05) |
| MSG-16 | A prefilled date that went stale | every command form with a prefilled date that went stale (IP-07) |
| MSG-17 | C-10 for an Admin | an Admin's creation or issue without a started series — PG-013, PG-036, PG-042, PG-043 (C-10) |
| MSG-18 | An import collision outside scope | PG-058, an import collision outside scope (DP-18) |
| MSG-19 | Sign-in or re-authentication failed | PG-001, PG-002 and the re-authentication (AU-04) |
| MSG-20 | A limit was reached | PG-001, PG-002, search (WFL-01), the step-up (WFL-09) and any rate-limited command |
| MSG-21 | Export above the row bound | "Ekspor" on PG-051 and PG-052, above the row bound (PF-28) |
| MSG-22 | A third export | "Ekspor" on PG-051 and PG-052, a third export |
| MSG-23 | A search list was cut | PG-009 and the type-ahead, a cut list (WFL-01) |
| MSG-24 | A term too short for name search | PG-009 and every lookup, a term too short |
| MSG-25 | An unknown code | PG-009 and every scan field, an unknown code (IP-15) |
| MSG-26 | A list cannot continue | every list whose cursor fails (WFL-08) |
| MSG-27 | Step-up | the step-up dialog — PG-058, PG-059, PG-060, PG-065 (WFL-09) |
| MSG-28 | A credential link that cannot be used | PG-003, an unusable credential link |
| MSG-29 | Notice after a credential event | the first page after a credential event — PG-006 or PG-007; the Owner's variant heading PG-006's decisions |
| MSG-30 | A stale count | PG-026, a stale count (ST-03) |
| MSG-31 | An upload refused | PG-038's upload sheet and every proof field, a refused file |
| MSG-32 | The pool-evidence confirmation | PG-038, a pool-evidence upload (H7-11) |
| MSG-33 | Idle warning | every page in the shell, the idle warning (WFL-05) |
| MSG-34 | The absolute limit ahead | the notice strip, the absolute limit ahead (WFL-05) |
| MSG-35 | Leaving with unsent input | logout with unsent input (WFL-06); leaving a page with unsent input; "Keluar" from the re-authentication |
| MSG-36 | A scan that does not fit | the scan fields of PG-022, PG-024, PG-026 and PG-034 |
| MSG-37 | An ambiguous code | PG-009 and every scan field, an ambiguous code |
| MSG-38 | An account without any company grant | PG-007 for an Admin without any company grant (RG-04) |
| MSG-39 | Confirming an irreversible command | every IP-08 confirmation — PG-012, PG-014, PG-022, PG-024, PG-036, PG-042, PG-043, PG-046, PG-061 |
| MSG-40 | The reason field | every IP-09 reason step |
| MSG-41 | A pending unattributed quantity | PG-018 and PG-019, the pending line; a company's stock and profit views on PG-051 |

### J. Completion — WF-PRJ-03's ten predicates

Where each predicate shows on the journey (PG-011) and on the completion page (PG-012), as [R1's journey stages and predicates](../evidence/P7_REBASELINE_GATE.md#journey-stages-and-predicates) place them: unmet ones under "Yang tersisa sebelum selesai" with their resolving action, met ones behind "N syarat lain terpenuhi", all ten on PG-012.

| Predicate | Name | On the journey | On the completion page |
| --- | --- | --- | --- |
| Predicate 1 | Fulfillment | the *Gudang*, *Kirim langsung* and *Jasa* stages; "Yang tersisa" › *Pemenuhan* | PG-012, with its resolving link |
| Predicate 2 | Delivery | the *Pengiriman* stage; "Yang tersisa" › *Pengiriman* | PG-012 |
| Predicate 3 | Returns/corrections | the blocker overlay on *Pengiriman* or *Pembayaran*; "Yang tersisa" › *Retur dan koreksi* | PG-012 |
| Predicate 4 | Documents | the *Dokumen* stage; "Kesiapan dokumen"; "Yang tersisa" › *Dokumen* | PG-012 |
| Predicate 5 | Administration | the *Dokumen* and *Invoice* stages; "Kesiapan dokumen"; "Yang tersisa" › *Administrasi* | PG-012 |
| Predicate 6 | Billing | the *Penagihan* stage; "Yang tersisa" › *Penagihan* | PG-012 |
| Predicate 7 | Receivable | the *Pembayaran* stage and its blocker — an overdue receivable or a dispute hold from this predicate's rows (QUERY_BUDGET_CHECKS §4); "Posisi keuangan"; "Yang tersisa" › *Piutang* | PG-012 |
| Predicate 8 | Stock | the blocker overlay on *Gudang*; "Yang tersisa" › *Stok* | PG-012 |
| Predicate 9 | Payment | the *Pembayaran* stage, QS-19's blocker; "Yang tersisa" › *Pembayaran* | PG-012 |
| Predicate 10 | Audit | the *Penyelesaian* stage; "Yang tersisa" › *Audit* | PG-012 |

### K. Roles — OWNER and ADMIN_OPERASIONAL

What each of the two launch roles reaches, by authority class of grant (RG-01–RG-03; [NAVIGATION_MAP §2](NAVIGATION_MAP.md#2-reachability)).

| Role | Defined by | Holds | OWNER_ONLY | ADM_PLUS | ADM | Pages reached in the shell |
| --- | --- | --- | --- | --- | --- | --- |
| OWNER | RG-01 | every capability through the role (RG-02), every company (RG-03; CS-01) | all 17 — *Beranda* with *Gabungan*, *Tinjauan*, *Aktivitas & Keamanan*, *Pengaturan*, *Laporan Gabungan*, the attributions, the write-off, Force Complete, the import commit (section D) | all 26 | all 37 | 61 of 61 (Laras) |
| ADMIN_OPERASIONAL | RG-01 | no capability by the role — each an individual grant (RG-02); companies by grant (RG-03); with no grant only *Akun Saya*, S0 lookups and MSG-38 (RG-04) | never — refused at grant time (RG-02; OD-02) | each its own grant, given and revoked with a reason (RG-08) — the corrections and closures of section E | the view families and the commands of section E, each with its prerequisites (RG-06) | 52 for a full Admin; 36 for Dimas and 30 for Bayu under their illustrative grants (IA_PAGE_INVENTORY §1) |

### L. Scope boundary — SHOULD SHIP, DEFERRED and OUT OF SCOPE

The IA's half of AK-5: the SHOULD SHIP refinements with where each would sit if included — discretionary, never a new module —, and every DEFERRED and OUT OF SCOPE item of [V1_SCOPE](../../01-product/V1_SCOPE.md#deferred) confirmed absent from the IA — no page, entry, route or control —, with the MUST part it keeps. The OUT OF SCOPE items are V1_SCOPE's bullets, numbered here in their order.

| Item | Subject | In the IA | The MUST part kept |
| --- | --- | --- | --- |
| SH-01 | the previous purchase price and date beside the supplier and item choice | would sit inside PG-017's line editor, beside the supplier and product pickers, with `cost.view` (PJ-09) — discretionary, not a module | CAP-02's history on PG-016 and PG-017 |
| SH-02 | additional keyboard shortcuts | would extend the shell's shortcut layer and its *Pintasan* help (KB-02) — discretionary, not a module | CAP-14's accessible keyboard use (KB-01–KB-08) |
| D-01 | a custom-role designer and more role packages | absent | launch grant management on PG-060 (RG-01–RG-12) |
| D-02 | checklist templates; an advanced template editor | absent | project checklists in readiness and on PG-039; required document generation on PG-036 |
| D-03 | a SIPLAH API and other non-essential integrations | absent | manual channel metadata on PG-013 (CAP-12) |
| D-04 | advanced analytics and forecasting | absent | CAP-11's reporting — PG-051, PG-052 and the KPIs on PG-006 |
| D-05 | a native app, offline sync, AI features | absent; no camera scanning (D-UX-09) | responsive browser workflows on the phone (NAVIGATION_MAP §1) |
| D-06 | broad historical reconstruction | absent | the archive and the opening import on PG-058 (CAP-15) |
| OUT OF SCOPE 1 | supplier comparison, scoring or mandatory competing quotes | absent — the supplier is chosen directly (J-MD-03; PG-017) | CAP-02's direct supplier selection |
| OUT OF SCOPE 2 | separate UNY, UGM or RT UNY modules; a separate SIPLAH project system | absent — SIPLAH is a channel on PG-013 (CAP-12) | the clients as ordinary organizations (PG-054) |
| OUT OF SCOPE 3 | formal accounting | absent | managerial finance — PG-041–PG-051 (CAP-10, CAP-11) |
| OUT OF SCOPE 4 | an enterprise WMS, multi-warehouse, complex settlement, a rules engine | absent | one pool with attribution — PG-018–PG-029 (CAP-06) |
| OUT OF SCOPE 5 | separate company apps, portals, a marketplace, a native product, paid services | absent | one responsive application |
| OUT OF SCOPE 6 | speculative infrastructure; production access by agents | absent — no page | — |

### M. Navigation requirements — UXN-01–UXN-26

Where each requirement of [INFORMATION_ARCHITECTURE §2](../INFORMATION_ARCHITECTURE.md#2-derived-navigation-requirements) is met in the S2-1 drafts.

| UXN | Subject | Met in |
| --- | --- | --- |
| UXN-01 | one Indonesian glossary; data never translated | section O — the glossary carried unchanged; [LOCALIZATION_ARCHITECTURE](LOCALIZATION_ARCHITECTURE.md); every page's copy |
| UXN-02 | the project detail as the operational hub | PG-011, the journey workspace with *Rincian proyek* (R1 e)); IA_PAGE_INVENTORY §5 |
| UXN-03 | navigation follows the business flow; every V1 area reachable | IA_PAGE_INVENTORY §2; section F |
| UXN-04 | phone and desktop designed separately | IA_PAGE_INVENTORY §§3.1–3.2; NAVIGATION_MAP §1 |
| UXN-05 | the landing pages | PG-006 for the Owner, PG-007 for an Admin (J-ACC-01) |
| UXN-06 | the company context | IA_PAGE_INVENTORY §4; WFL-03 |
| UXN-07 | rows labelled by company; no S3 total for an Admin | IA_PAGE_INVENTORY §4; NAVIGATION_MAP §3; WFL-03 |
| UXN-08 | the relation marker | IA_PAGE_INVENTORY §4; PG-019, PG-024, PG-025 |
| UXN-09 | entries and controls as hints | IA_PAGE_INVENTORY §3.3; NAVIGATION_MAP's rules |
| UXN-10 | out of scope answers as not found; forbidden in scope | WFL-07; PG-005 |
| UXN-11 | public identifiers only; nothing sensitive in titles or history | IA_PAGE_INVENTORY §5's draft routes and §3.2's phone title |
| UXN-12 | every signal placed | section B; PG-006, PG-007 |
| UXN-13 | the dashboard's five deferred groups at most | PG-006 — five groups; PG-007 — four (QB-10) |
| UXN-14 | every other page at most three deferred groups | IA_PAGE_INVENTORY §5.2 — PG-011's three (QB-02); QUERY_BUDGET_CHECKS |
| UXN-15 | lists continue by "more" | WFL-08 |
| UXN-16 | search and lookup | WFL-01; IA_PAGE_INVENTORY §6.1 |
| UXN-17 | scanner entry points | WFL-01; IA_PAGE_INVENTORY §6.2 |
| UXN-18 | Owner-only areas only for the Owner | IA_PAGE_INVENTORY §3.3; section D; NAVIGATION_MAP §3 |
| UXN-19 | each screen row names its capability, projection, scope, budget and patterns | IA_PAGE_INVENTORY §5.2, with INFORMATION_ARCHITECTURE §8's budget and sort columns still governing |
| UXN-20 | the print view equals its page; downloads are ordinary requests | the renditions of section C; PG-036; [DESIGN_SYSTEM_MAPPING](DESIGN_SYSTEM_MAPPING.md) |
| UXN-21 | what the phone supports | NAVIGATION_MAP §§1.2, 1.4, 1.5; IA_PAGE_INVENTORY §5.2's tag and phone column |
| UXN-22 | what the desktop optimizes | IA_PAGE_INVENTORY §5.2; the editors PG-013, PG-014, PG-017 and the composer PG-042 |
| UXN-23 | shared masters | PG-054, PG-055, PG-056 |
| UXN-24 | settings, master-data and history areas; the timeline; the Owner's audit search | *Data Master* and *Pengaturan* in *Menu lengkap*; the record timeline on every detail page; PG-063 |
| UXN-25 | the Owner review queue | PG-008 |
| UXN-26 | nothing excluded or deferred is navigable | section L |

### N. Visual requirements — UXV-01–UXV-22

Where each requirement of [DESIGN_SYSTEM §3](../DESIGN_SYSTEM.md#3-derived-visual-requirements) is met in [DESIGN_SYSTEM_MAPPING](DESIGN_SYSTEM_MAPPING.md), section by section.

| UXV | Subject | Met in DESIGN_SYSTEM_MAPPING |
| --- | --- | --- |
| UXV-01 | shadcn/ui primitives as the foundation | §3 Components; §4 Patterns |
| UXV-02 | calm, dense, function over decoration | §2 Typography, spacing, radius, elevation, motion and density; §9 the anti-slop rules |
| UXV-03 | every anti-slop prohibition prevented by a rule | §9 |
| UXV-04 | no social-media patterns | §9 |
| UXV-05 | typography, spacing, radius, density, icons, status and tokens defined | §1 Tokens; §2; §5 States and tones; the icons with [PROTOTYPE_PLAN](PROTOTYPE_PLAN.md) (SP-16) |
| UXV-06 | responsive layout, mobile tables, the data table, forms, dialog against sheet, targets | §3; §6 Responsive rules |
| UXV-07 | keyboard and barcode interaction | §3 — the scan field and input group; §4 — IP-15 |
| UXV-08 | status never by colour alone | §5 |
| UXV-09 | WCAG 2.2 AA as the target | §1 — contrast computed; §2 — reduced motion; §3 — focus; §6 — reflow |
| UXV-10 | targets of 24 and 44 px | §2 — the targets of 24, 44 and 48 px (GR-07) |
| UXV-11 | phone patterns designed separately | §4; §6 |
| UXV-12 | one formatting of money, dates, quantities and codes | §4; [LOCALIZATION_ARCHITECTURE](LOCALIZATION_ARCHITECTURE.md)'s formatting |
| UXV-13 | the five financial concepts kept apart | §4 — PT-24's position block |
| UXV-14 | the four stock quantities, the marker and the pending line | §4; §5 |
| UXV-15 | renditions inline, uploads as attachments | §3 — the composer's preview; §4 — PT-22 and IP-16 |
| UXV-16 | thumbnails one page at a time | §4 |
| UXV-17 | loading, empty, error, denied, stale, CONFLICT, PENDING and FAILED | §5; §4 — PT-19 |
| UXV-18 | toasts, feedback and the timeline | §4 — GR-15's toasts, PT-18's timeline |
| UXV-19 | the print view identical to its page | §3 — the print view |
| UXV-20 | destructive actions, confirmations, corrections | §4 — IP-08, IP-09, GR-09 as corrected |
| UXV-21 | motion for feedback only, none under reduced motion | §2 |
| UXV-22 | Indonesian copy conventions | §9; [LOCALIZATION_ARCHITECTURE](LOCALIZATION_ARCHITECTURE.md)'s copy rules |

### O. Earlier P7 decisions — D-IA-01–D-IA-17 and D-DS-01–D-DS-09

Each decision with its provisional disposition — the gate record's [provisional dispositions](../evidence/P7_REBASELINE_GATE.md#provisional-dispositions) as R1's [disposition delta](../evidence/P7_REBASELINE_GATE.md#c1-rework--disposition-delta) revised them —: where each KEEP and ADAPT row is honoured, and what replaces each SUPERSEDE row. All stay SUPERSEDED-PENDING in DECISION_INDEX; GA1's [re-check](../evidence/P7_REBASELINE_GATE.md#earlier-p7-re-baseline-decisions) against the reference is noted where it adds.

| Decision | Disposition | Honoured or replaced in |
| --- | --- | --- |
| D-IA-01 | SUPERSEDE (R1's delta) | task-first primary entries with *Menu lengkap* — IA_PAGE_INVENTORY §§2.1–2.2, §3.1 |
| D-IA-02 | KEEP | the company filter in list headers, never in the shell — IA_PAGE_INVENTORY §4; WFL-03 |
| D-IA-03 | ADAPT (R1's delta) | the phone's bottom bar per persona, at most five entries — IA_PAGE_INVENTORY §3.2 |
| D-IA-04 | KEEP | one entry for typed and scanned codes — IA_PAGE_INVENTORY §3.1; WFL-01 |
| D-IA-05 | KEEP | the notice strip and the idle dialog — IA_PAGE_INVENTORY §3.1; WFL-05 |
| D-IA-06 | KEEP | no badge or count on an entry — IA_PAGE_INVENTORY §3.3 |
| D-IA-07 | SUPERSEDE | the Owner's *Beranda* C — PG-006 (R1 c)) |
| D-IA-08 | ADAPT | work grouped by what has to happen next — PG-007 (R1 b)) |
| D-IA-09 | SUPERSEDE | the journey workspace — PG-011 (R1 e)) |
| D-IA-10 | ADAPT (R1's delta) | the checklist inside document readiness — PG-011's "Kesiapan dokumen"; PG-039 |
| D-IA-11 | KEEP | the review queue's parts — PG-008 |
| D-IA-12 | KEEP | one section per company in a report — PG-051 |
| D-IA-13 | KEEP | the numbering area ordered by need — PG-061 |
| D-IA-14 | KEEP | masters addressable and search-first — PG-054, PG-055, PG-056 |
| D-IA-15 | ADAPT | receivables a list; ageing per company on *Beranda* and in the report — PG-044; PG-006's chart; PG-051 |
| D-IA-16 | KEEP | dispatch and delivery two linked screens — PG-023 and PG-024; PG-030 and PG-031 |
| D-IA-17 | KEEP | stock by product with four quantities and the pending line — PG-018, PG-019 |
| D-DS-01 | SUPERSEDE — now by the reference (GA1; GD-02) | the reference's tokens — [DESIGN_SYSTEM_MAPPING](DESIGN_SYSTEM_MAPPING.md) §1 |
| D-DS-02 | ADAPT — RECONCILED in GA1 | Inter, self-hosted, tabular figures, 16 px touch inputs, on the reference's scale — DESIGN_SYSTEM_MAPPING §2 |
| D-DS-03 | SUPERSEDE — RECONCILED in GA1 | the reference's density with OC-09's named dense screens — DESIGN_SYSTEM_MAPPING §§2, 6 |
| D-DS-04 | ADAPT | label, icon shape and tone in the reference's pill — DESIGN_SYSTEM_MAPPING §5 |
| D-DS-05 | SUPERSEDE | charts of defined values only — PG-006's "Umur piutang" (VP-6; OC-08); DESIGN_SYSTEM_MAPPING §3 |
| D-DS-06 | KEEP | the outcome in the sticky action region — DESIGN_SYSTEM_MAPPING §4 |
| D-DS-07 | KEEP | the multi-line editor and the phone's line sheets — PG-013, PG-014, PG-017; DESIGN_SYSTEM_MAPPING §3 |
| D-DS-08 | KEEP | the phone scan field fixed at the bottom — PG-022, PG-024; DESIGN_SYSTEM_MAPPING §3 |
| D-DS-09 | KEEP | the capability-by-company preview — PG-060 |

**The glossary.** INFORMATION_ARCHITECTURE §3's glossary is carried unchanged as the Indonesian vocabulary — its terms counted by script in section P —; every label in the S2-1 drafts uses it, and [LOCALIZATION_ARCHITECTURE](LOCALIZATION_ARCHITECTURE.md) sets how their English renderings are proposed and validated (§2.3), each journey specification's copy section proposing the English of the terms its screens use.

### P. Totals

Each section's identifier column against the identifiers its defining document holds, both read by script (DIR-064 §11.4); a row whose every destination cell is empty would be a row without a destination.

| Section | Identifiers | Defining document | Matrix rows | Column | Rows without a destination |
| --- | --- | --- | --- | --- | --- |
| A | Screens SCR-01–SCR-60 | 60 | 60 | equal, same order | 0 |
| B | Signals QS-01–QS-22 | 22 | 22 | equal, same order | 0 |
| C | Documents DOC-01–DOC-14 and WF-DOC-02 uploads | 15 | 15 — 14 document types and the upload path | equal, same order | 0 |
| D | Owner-only denials OD-01–OD-16 | 16 | 16 | equal, same order | 0 |
| E | Capabilities | 80 | 80 — ADM 37, ADM_PLUS 26, OWNER_ONLY 17 | equal, same order | 0 |
| F | V1 capabilities CAP-01–CAP-18 | 18 | 18 | equal, same order | 0 |
| G | ADMIN_FLOW journeys | 39 | 39 | equal, same order | 0 |
| H | Indivisible actions AX-01–AX-37 | 37 | 37 | equal, same order | 0 |
| I | Messages MSG-01–MSG-41 | 41 | 41 | equal, same order | 0 |
| J | WF-PRJ-03 predicates | 10 | 10 | equal, same order | 0 |
| K | Launch roles | 2 | 2 | equal, same order | 0 |
| L | SHOULD SHIP, DEFERRED and OUT OF SCOPE items | 14 | 14 — SH 2, DEFERRED 6, OUT OF SCOPE 6 | equal, same order | 0 |
| M | UXN-01–UXN-26 | 26 | 26 | equal, same order | 0 |
| N | UXV-01–UXV-22 | 22 | 22 | equal, same order | 0 |
| O | D-IA-01–D-IA-17 and D-DS-01–D-DS-09 | 26 | 26 — D-IA 17, D-DS 9; KEEP 14, ADAPT 6, SUPERSEDE 6 | equal, same order | 0 |

**In all:** 428 rows in sections A–O, each section equal to its defining document; **rows without a destination: 0**. The counts equal Planning's of DIR-064 §11.4 — SCR 60, QS 22, DOC 14, CAP 18, 80 capabilities (ADM 37, ADM_PLUS 26, OWNER_ONLY 17), OD 16, 39 journeys, AX 37, MSG 41, the ten predicates, UXN 26, UXV 22, D-IA 17 and D-DS 9. The glossary carried in section O holds 191 terms, GL-001–GL-191.

### Q. Stage 3 — every requirement to its page, route and interaction

*Added on 2026-10-18 by run PC-2 under [DIR-078](../../00-governance/DECISION_LOG.md#dir-077-dir-078-obs-030-and-tech-037--p7-ux-re-baseline-cp-a-decisions-run-pc-2-with-p9s-drafting-baseline-and-drafts) SP-63 and SP-68.* Sections A–P map the approved revision's identifiers to Stage 2's pages; this section maps every V1 capability, acceptance criterion, SCR and user-visible rule to the Stage 3 drafts — a page of [INFORMATION_ARCHITECTURE](../INFORMATION_ARCHITECTURE.md) §8 with its [NAVIGATION_CONTRACTS](../NAVIGATION_CONTRACTS.md) contract, a route of [ROUTE_CONTRACTS](../../03-workflows/ROUTE_CONTRACTS.md), an interaction of [INTERACTION_CONTRACTS](../../03-workflows/INTERACTION_CONTRACTS.md) or a key of [LOCALIZATION](../LOCALIZATION.md). Built by script from those documents and sections A–P.

#### Q.1 The approved screens SCR-01–SCR-60

Each screen with its pages ([INFORMATION_ARCHITECTURE §8.7](../INFORMATION_ARCHITECTURE.md#87-the-approved-screens-scr-01scr-60-and-their-pages)) and their navigation contracts and views.

| SCR | Pages | NAV · ROUTE |
| --- | --- | --- |
| SCR-01 | PG-001, PG-002 | NAV-001 · ROUTE-001; NAV-002 · ROUTE-002 |
| SCR-02 | PG-003, PG-004 | NAV-003 · ROUTE-003; NAV-004 · ROUTE-004 |
| SCR-03 | PG-065 | NAV-065 · ROUTE-065 |
| SCR-04 | PG-005 | NAV-005 · ROUTE-005 |
| SCR-05 | PG-006, PG-007 | NAV-006 · ROUTE-006; NAV-007 · ROUTE-007 |
| SCR-06 | PG-007 | NAV-007 · ROUTE-007 |
| SCR-07 | PG-008 | NAV-008 · ROUTE-008 |
| SCR-08 | PG-009 | NAV-009 · ROUTE-009 |
| SCR-09 | PG-010 | NAV-010 · ROUTE-010 |
| SCR-10 | PG-011 | NAV-011 · ROUTE-011 |
| SCR-11 | PG-013 | NAV-013 · ROUTE-013 |
| SCR-12 | PG-037 | NAV-037 · ROUTE-037 |
| SCR-13 | PG-014 | NAV-014 · ROUTE-014 |
| SCR-14 | PG-011, PG-012 | NAV-011 · ROUTE-011; NAV-012 · ROUTE-012 |
| SCR-15 | PG-015 | NAV-015 · ROUTE-015 |
| SCR-16 | PG-016 | NAV-016 · ROUTE-016 |
| SCR-17 | PG-017 | NAV-017 · ROUTE-017 |
| SCR-18 | PG-018 | NAV-018 · ROUTE-018 |
| SCR-19 | PG-019 | NAV-019 · ROUTE-019 |
| SCR-20 | PG-020 | NAV-020 · ROUTE-020 |
| SCR-21 | PG-021, PG-022 | NAV-021 · ROUTE-021; NAV-022 · ROUTE-022 |
| SCR-22 | PG-023, PG-024 | NAV-023 · ROUTE-023; NAV-024 · ROUTE-024 |
| SCR-23 | PG-025 | NAV-025 · ROUTE-025 |
| SCR-24 | PG-026 | NAV-026 · ROUTE-026 |
| SCR-25 | PG-027 | NAV-027 · ROUTE-027 |
| SCR-26 | PG-028 | NAV-028 · ROUTE-028 |
| SCR-27 | PG-029 | NAV-029 · ROUTE-029 |
| SCR-28 | PG-030 | NAV-030 · ROUTE-030 |
| SCR-29 | PG-031 | NAV-031 · ROUTE-031 |
| SCR-30 | PG-032 | NAV-032 · ROUTE-032 |
| SCR-31 | PG-033 | NAV-033 · ROUTE-033 |
| SCR-32 | PG-034 | NAV-034 · ROUTE-034 |
| SCR-33 | PG-040 | NAV-040 · ROUTE-040 |
| SCR-34 | PG-035 | NAV-035 · ROUTE-035 |
| SCR-35 | PG-036 | NAV-036 · ROUTE-036 |
| SCR-36 | PG-038 | NAV-038 · ROUTE-038 |
| SCR-37 | PG-039 | NAV-039 · ROUTE-039 |
| SCR-38 | PG-041, PG-042 | NAV-041 · ROUTE-041; NAV-042 · ROUTE-042 |
| SCR-39 | PG-042, PG-043 | NAV-042 · ROUTE-042; NAV-043 · ROUTE-043 |
| SCR-40 | PG-044 | NAV-044 · ROUTE-044 |
| SCR-41 | PG-045 | NAV-045 · ROUTE-045 |
| SCR-42 | PG-046 | NAV-046 · ROUTE-046 |
| SCR-43 | PG-047 | NAV-047 · ROUTE-047 |
| SCR-44 | PG-048 | NAV-048 · ROUTE-048 |
| SCR-45 | PG-049 | NAV-049 · ROUTE-049 |
| SCR-46 | PG-050 | NAV-050 · ROUTE-050 |
| SCR-47 | PG-051 | NAV-051 · ROUTE-051 |
| SCR-48 | PG-052 | NAV-052 · ROUTE-052 |
| SCR-49 | PG-053 | NAV-053 · ROUTE-053 |
| SCR-50 | PG-054 | NAV-054 · ROUTE-054 |
| SCR-51 | PG-055 | NAV-055 · ROUTE-055 |
| SCR-52 | PG-056 | NAV-056 · ROUTE-056 |
| SCR-53 | PG-057 | NAV-057 · ROUTE-057 |
| SCR-54 | PG-059 | NAV-059 · ROUTE-059 |
| SCR-55 | PG-061 | NAV-061 · ROUTE-061 |
| SCR-56 | PG-062 | NAV-062 · ROUTE-062 |
| SCR-57 | PG-060 | NAV-060 · ROUTE-060 |
| SCR-58 | PG-058 | NAV-058 · ROUTE-058 |
| SCR-59 | PG-063 | NAV-063 · ROUTE-063 |
| SCR-60 | PG-064 | NAV-064 · ROUTE-064 |

#### Q.2 V1 capabilities CAP-01–CAP-18

Section F's pages and written flows with their contracts.

| CAP | Pages | NAV · ROUTE | Written flows |
| --- | --- | --- | --- |
| CAP-01 | PG-059, PG-060, PG-061 | NAV-059 · ROUTE-059; NAV-060 · ROUTE-060; NAV-061 · ROUTE-061 | J-MD-01, J-ACC-02, WFL-09 |
| CAP-02 | PG-054, PG-055, PG-016, PG-017 | NAV-054 · ROUTE-054; NAV-055 · ROUTE-055; NAV-016 · ROUTE-016; NAV-017 · ROUTE-017 | J-MD-02, J-MD-03 |
| CAP-03 | PG-056 | NAV-056 · ROUTE-056 | J-MD-04 |
| CAP-04 | PG-010, PG-011, PG-013, PG-014, PG-037 | NAV-010 · ROUTE-010; NAV-011 · ROUTE-011; NAV-013 · ROUTE-013; NAV-014 · ROUTE-014; NAV-037 · ROUTE-037 | J-PRJ-01, J-QUO-01, J-PRJ-02, J-FUL-01 |
| CAP-05 | PG-015, PG-016, PG-017 | NAV-015 · ROUTE-015; NAV-016 · ROUTE-016; NAV-017 · ROUTE-017 | J-PUR-01, J-PUR-02 |
| CAP-06 | PG-018, PG-029 | NAV-018 · ROUTE-018; NAV-029 · ROUTE-029 | J-INV-01–J-INV-07, J-PUR-03 |
| CAP-07 | PG-030, PG-031, PG-032, PG-033, PG-034 | NAV-030 · ROUTE-030; NAV-031 · ROUTE-031; NAV-032 · ROUTE-032; NAV-033 · ROUTE-033; NAV-034 · ROUTE-034 | J-FUL-02, J-FUL-03, J-FUL-04, J-FUL-05 |
| CAP-08 | PG-011, PG-035, PG-036 | NAV-011 · ROUTE-011; NAV-035 · ROUTE-035; NAV-036 · ROUTE-036 | J-DOC-01 |
| CAP-09 | PG-011, PG-039, PG-038 | NAV-011 · ROUTE-011; NAV-039 · ROUTE-039; NAV-038 · ROUTE-038 | J-ADM-01, J-DOC-02 |
| CAP-10 | PG-041, PG-046 | NAV-041 · ROUTE-041; NAV-046 · ROUTE-046 | J-FIN-01, J-FIN-02, J-FIN-03, J-FIN-05, J-FIN-08 |
| CAP-11 | PG-051, PG-052, PG-006, PG-011, PG-047, PG-050 | NAV-051 · ROUTE-051; NAV-052 · ROUTE-052; NAV-006 · ROUTE-006; NAV-011 · ROUTE-011; NAV-047 · ROUTE-047; NAV-050 · ROUTE-050 | J-FIN-04, J-FIN-06, J-FIN-07 |
| CAP-12 | PG-013 | NAV-013 · ROUTE-013 | J-PRJ-01 |
| CAP-13 | PG-001, PG-004, PG-065, PG-060 | NAV-001 · ROUTE-001; NAV-004 · ROUTE-004; NAV-065 · ROUTE-065; NAV-060 · ROUTE-060 | J-ACC-01, WFL-05, WFL-06, WFL-09; [AUTH_JOURNEYS](AUTH_JOURNEYS.md) |
| CAP-14 | PG-001 | NAV-001 · ROUTE-001 | WFL-04; [LOCALIZATION_ARCHITECTURE](LOCALIZATION_ARCHITECTURE.md) |
| CAP-15 | PG-058 | NAV-058 · ROUTE-058 | J-MIG-01 |
| CAP-16 | no page by design — operated by the human operator (INFORMATION_ARCHITECTURE §5; V1_SCOPE) | — | — |
| CAP-17 | PG-006, PG-007, PG-008, PG-009 | NAV-006 · ROUTE-006; NAV-007 · ROUTE-007; NAV-008 · ROUTE-008; NAV-009 · ROUTE-009 | WFL-01, WFL-03; section B |
| CAP-18 | PG-011, PG-012 | NAV-011 · ROUTE-011; NAV-012 · ROUTE-012 | J-PRJ-03, J-PRJ-04; section J |

#### Q.3 Acceptance criteria AC-01–AC-23

Each criterion through its capabilities ([ACCEPTANCE_CRITERIA](../../01-product/ACCEPTANCE_CRITERIA.md)).

| AC | Capabilities | Pages | Also |
| --- | --- | --- | --- |
| AC-01 | CAP-01 | PG-059, PG-060, PG-061 | — |
| AC-02 | CAP-02 | PG-054, PG-055, PG-016, PG-017 | — |
| AC-03 | CAP-03 | PG-056 | — |
| AC-04 | CAP-04 | PG-010, PG-011, PG-013, PG-014, PG-037 | — |
| AC-05 | CAP-05 | PG-015, PG-016, PG-017 | — |
| AC-06 | CAP-06 | PG-018, PG-029 | — |
| AC-07 | CAP-07 | PG-030, PG-031, PG-032, PG-033, PG-034 | — |
| AC-08 | CAP-08 | PG-011, PG-035, PG-036 | — |
| AC-09 | CAP-09 | PG-011, PG-039, PG-038 | — |
| AC-10 | CAP-10 | PG-041, PG-046 | — |
| AC-11 | CAP-11 | PG-051, PG-052, PG-006, PG-011, PG-047, PG-050 | — |
| AC-12 | CAP-08, CAP-10, CAP-13 | PG-011, PG-035, PG-036, PG-041, PG-046, PG-001, PG-004, PG-065, PG-060 | — |
| AC-13 | CAP-01, CAP-13 | PG-059, PG-060, PG-061, PG-001, PG-004, PG-065 | [ROUTE_CONTRACTS](../../03-workflows/ROUTE_CONTRACTS.md) — each route's capability and scope |
| AC-14 | CAP-14 | PG-001 | [LOCALIZATION](../LOCALIZATION.md) — both languages, the key inventory |
| AC-15 | CAP-12 | PG-013 | — |
| AC-16 | CAP-03, CAP-06, CAP-10, CAP-13 | PG-056, PG-018, PG-029, PG-041, PG-046, PG-001, PG-004, PG-065, PG-060 | — |
| AC-17 | CAP-15 | PG-058 | — |
| AC-18 | CAP-16 | — | P9 — backup and recovery ([docs/09-operations](../../09-operations/README.md); SP-67) |
| AC-19 | CAP-16 | — | P9 — backup and recovery ([docs/09-operations](../../09-operations/README.md); SP-67) |
| AC-20 | CAP-04, CAP-06, CAP-14 | PG-010, PG-011, PG-013, PG-014, PG-037, PG-018, PG-029, PG-001 | — |
| AC-21 | CAP-09, CAP-18 | PG-011, PG-039, PG-038, PG-012 | — |
| AC-22 | CAP-06 | PG-018, PG-029 | — |
| AC-23 | CAP-17 | PG-006, PG-007, PG-008, PG-009 | — |

#### Q.4 Indivisible actions AX-01–AX-37

Each command with its routes and interactions ([ROUTE_CONTRACTS](../../03-workflows/ROUTE_CONTRACTS.md) §3; [INTERACTION_CONTRACTS](../../03-workflows/INTERACTION_CONTRACTS.md) §3, the same numbers).

| AX | Pages | Routes and interactions |
| --- | --- | --- |
| AX-01 | PG-025, PG-011, PG-022, PG-020, PG-034 | ROUTE-184 · INT-184; the offer inside a receipt or a restoration (IP-19): ROUTE-181 · INT-181; ROUTE-188 · INT-188; ROUTE-210 · INT-210 |
| AX-02 | PG-025 | ROUTE-185 · INT-185; the cut inside a supply-reducing action (IP-20): ROUTE-176 · INT-176; ROUTE-183 · INT-183; ROUTE-193 · INT-193; ROUTE-194 · INT-194; ROUTE-195 · INT-195; ROUTE-211 · INT-211 |
| AX-03 | PG-024 | ROUTE-186 · INT-186 |
| AX-04 | PG-022 | ROUTE-181 · INT-181 |
| AX-05 | PG-016, PG-020 | ROUTE-183 · INT-183 |
| AX-06 | PG-027, PG-026 | ROUTE-193 · INT-193; ROUTE-194 · INT-194; ROUTE-195 · INT-195 |
| AX-07 | PG-029 | ROUTE-197 · INT-197 |
| AX-08 | PG-032 | ROUTE-205 · INT-205; ROUTE-206 · INT-206 |
| AX-09 | PG-031 | ROUTE-201 · INT-201; ROUTE-204 · INT-204; ROUTE-208 · INT-208; ROUTE-209 · INT-209 |
| AX-10 | PG-034 | ROUTE-188 · INT-188; ROUTE-207 · INT-207; ROUTE-210 · INT-210 |
| AX-11 | PG-028 | ROUTE-176 · INT-176 |
| AX-12 | PG-016 | ROUTE-173 · INT-173 |
| AX-13 | PG-014, PG-011 | ROUTE-157 · INT-157; ROUTE-158 · INT-158 |
| AX-14 | PG-011 | ROUTE-159 · INT-159; ROUTE-160 · INT-160 |
| AX-15 | PG-036, PG-014, PG-016, PG-022, PG-024, PG-031, PG-033 | ROUTE-155 · INT-155; ROUTE-182 · INT-182; ROUTE-187 · INT-187; ROUTE-202 · INT-202; ROUTE-222 · INT-222 |
| AX-16 | PG-042, PG-043 | ROUTE-242 · INT-242 |
| AX-17 | PG-043 | ROUTE-243 · INT-243; ROUTE-244 · INT-244 |
| AX-18 | PG-046 | ROUTE-251 · INT-251 |
| AX-19 | PG-045, PG-048 | ROUTE-252 · INT-252; ROUTE-253 · INT-253 |
| AX-20 | PG-045 | ROUTE-254 · INT-254 |
| AX-21 | PG-047 | ROUTE-255 · INT-255 |
| AX-22 | PG-043 | ROUTE-245 · INT-245 |
| AX-23 | PG-048 | ROUTE-256 · INT-256 |
| AX-24 | PG-043 | ROUTE-247 · INT-247; ROUTE-248 · INT-248 |
| AX-25 | PG-012 | ROUTE-161 · INT-161 |
| AX-26 | PG-012 | ROUTE-162 · INT-162 |
| AX-27 |  | ROUTE-163 · INT-163 |
| AX-28 | PG-058 | ROUTE-275 · INT-275 |
| AX-29 | PG-017 | ROUTE-171 · INT-171 |
| AX-30 | PG-027 | ROUTE-194 · INT-194; ROUTE-196 · INT-196 |
| AX-31 | PG-043, PG-036, PG-046 | ROUTE-249 · INT-249; ROUTE-250 · INT-250 |
| AX-32 | PG-016 | ROUTE-174 · INT-174 |
| AX-33 | PG-047, PG-049 | ROUTE-260 · INT-260 |
| AX-34 | PG-016 | ROUTE-175 · INT-175 |
| AX-35 | PG-024, PG-011 | ROUTE-189 · INT-189 |
| AX-36 | PG-034, PG-028 | ROUTE-211 · INT-211 |
| AX-37 | PG-029 | ROUTE-198 · INT-198; ROUTE-199 · INT-199; ROUTE-200 · INT-200 |

#### Q.5 Messages MSG-01–MSG-41

Each message with its key ([LOCALIZATION](../LOCALIZATION.md) §6.1) and the contracts that use it.

| MSG | Key | Interactions |
| --- | --- | --- |
| MSG-01 | `messages.msg_01` | IG-02, every contract |
| MSG-02 | `messages.msg_02` | IG-02, every contract |
| MSG-03 | `messages.msg_03` | IG-02, every contract; INT-016 |
| MSG-04 | `messages.msg_04` | IG-02, every contract |
| MSG-05 | `messages.msg_05` | — |
| MSG-06 | `messages.msg_06` | IG-02, every contract; INT-017 |
| MSG-07 | `messages.msg_07` | IG-02, every contract; INT-017 |
| MSG-08 | `messages.msg_08` | — |
| MSG-09 | `messages.msg_09` | IG-02, every contract; INT-003, INT-014, INT-019, INT-020 |
| MSG-10 | `messages.msg_10` | IG-02, every contract; INT-014 |
| MSG-11 | `messages.msg_11` | IG-02, every contract; INT-010 |
| MSG-12 | `messages.msg_12` | IG-02, every contract; INT-101, INT-104, INT-105, INT-106, INT-107, INT-108, INT-109, INT-111 and 131 more |
| MSG-13 | `messages.msg_13` | IG-02, every contract |
| MSG-14 | `messages.msg_14` | — |
| MSG-15 | `messages.msg_15` | INT-013, INT-015, INT-186, INT-188, INT-193, INT-194, INT-195, INT-196 and 7 more |
| MSG-16 | `messages.msg_16` | — |
| MSG-17 | `messages.msg_17` | INT-137, INT-151, INT-155, INT-171, INT-222 |
| MSG-18 | `messages.msg_18` | — |
| MSG-19 | `messages.msg_19` | INT-010, INT-011, INT-101, INT-102, INT-103, INT-104, INT-105, INT-106 and 10 more |
| MSG-20 | `messages.msg_20` | IG-02, every contract; INT-011, INT-101, INT-102, INT-103, INT-104, INT-105, INT-106, INT-107 and 9 more |
| MSG-21 | `messages.msg_21` | — |
| MSG-22 | `messages.msg_22` | — |
| MSG-23 | `messages.msg_23` | INT-005 |
| MSG-24 | `messages.msg_24` | INT-005 |
| MSG-25 | `messages.msg_25` | INT-006 |
| MSG-26 | `messages.msg_26` | INT-008 |
| MSG-27 | `messages.msg_27` | INT-011, INT-112, INT-113, INT-114, INT-115, INT-116, INT-117, INT-121 and 12 more |
| MSG-28 | `messages.msg_28` | — |
| MSG-29 | `messages.msg_29` | — |
| MSG-30 | `messages.msg_30` | INT-191 |
| MSG-31 | `messages.msg_31` | INT-013, INT-228 |
| MSG-32 | `messages.msg_32` | — |
| MSG-33 | `messages.msg_33` | INT-010 |
| MSG-34 | `messages.msg_34` | INT-010 |
| MSG-35 | `messages.msg_35` | INT-009 |
| MSG-36 | `messages.msg_36` | INT-006 |
| MSG-37 | `messages.msg_37` | INT-006 |
| MSG-38 | `messages.msg_38` | — |
| MSG-39 | `messages.msg_39` | INT-121, INT-122, INT-123, INT-126, INT-130, INT-133, INT-137, INT-153 and 20 more |
| MSG-40 | `messages.msg_40` | INT-127, INT-128, INT-159, INT-160, INT-162, INT-163, INT-166, INT-168 and 42 more |
| MSG-41 | `messages.msg_41` | — |

#### Q.6 Signals QS-01–QS-24

Each signal with its place ([INFORMATION_ARCHITECTURE §9.1](../INFORMATION_ARCHITECTURE.md#91-placement-of-every-signal)) and its row's interaction, INT-019.

| QS | Group | Pages |
| --- | --- | --- |
| QS-01 | G2 · G1 | PG-025, PG-011 — INT-019 |
| QS-02 | G2 · G1 | PG-017, PG-011 — INT-019 |
| QS-03 | G2 · G1 | PG-025, PG-011 — INT-019 |
| QS-04 | G2 · G1 | PG-011 — INT-019 |
| QS-05 | G2 · G1 | PG-022, PG-016 — INT-019 |
| QS-06 | G2 · G1 | PG-031, PG-030 — INT-019 |
| QS-07 | G2 · G2 | PG-040 — INT-019 |
| QS-08 | G2 · G2 | PG-036 — INT-019 |
| QS-09 | G3 · G3 | PG-044, PG-043 — INT-019 |
| QS-10 | G3 · G3 | PG-044 — INT-019 |
| QS-11 | G3 · G3 | PG-044 — INT-019 |
| QS-12 | G3 · G3; the credit linked to a written-off invoice also in the Owner review queue | PG-048, PG-045, PG-050 — INT-019 |
| QS-13 | G3 · G3 | PG-047, PG-043 — INT-019 |
| QS-14 | G1 · absent — no entry, count or badge exists for an Admin | PG-008 — INT-019 |
| QS-15 | G4 · G4 | PG-011, PG-012, PG-042 — INT-019 |
| QS-16 | G1 · G1 | PG-011 — INT-019 |
| QS-17 | G2 · G1 | PG-018 — INT-019 |
| QS-18 | G1 (attribution) and G2 · G1 (findings, stale counts) | PG-026, PG-019, PG-029 — INT-019 |
| QS-19 | G3 · G3 | PG-036, PG-043 — INT-019 |
| QS-20 | G1 · G1 | the refused command's target record; a notice with its age that leaves the list after 14 days — no task state, no "selesai" control — INT-019 |
| QS-21 | G2 · G2 | the record — INT-019 |
| QS-22 | G1 · not in *Pekerjaan* — the pending quantities show on PG-018 and PG-019 as their own line, blocking nothing (DIR-027) | PG-029 — INT-019 |
| QS-23 | G1 · absent — no Admin page shows it; an Admin sees the deadline as the project's fact only | PG-011 — INT-019 |
| QS-24 | — · G1, in *Keluarkan dan kirim* | PG-024, PG-011, PG-023 — INT-019 |

#### Q.7 Capabilities — all 80 of PERMISSIONS_MATRIX §4

Each capability with the views and commands that declare it.

| Capability | Class | Views | Commands |
| --- | --- | --- | --- |
| `stock.view` | ADM | ROUTE-018, ROUTE-019, ROUTE-020, ROUTE-025, ROUTE-028, ROUTE-051, ROUTE-056, ROUTE-057 | ROUTE-176, ROUTE-184, ROUTE-282 |
| `projects.view` | ADM | ROUTE-010, ROUTE-011, ROUTE-012, ROUTE-014, ROUTE-037, ROUTE-020, ROUTE-025, ROUTE-030, ROUTE-035, ROUTE-039, ROUTE-040, ROUTE-051, ROUTE-054, ROUTE-055 | ROUTE-184 |
| `finance.view` | ADM | ROUTE-007, ROUTE-010, ROUTE-011, ROUTE-013, ROUTE-014, ROUTE-037, ROUTE-016, ROUTE-041, ROUTE-042, ROUTE-043, ROUTE-044, ROUTE-045, ROUTE-046, ROUTE-047, ROUTE-048, ROUTE-049, ROUTE-050, ROUTE-051 | ROUTE-151, ROUTE-152, ROUTE-154, ROUTE-241, ROUTE-251, ROUTE-255, ROUTE-261 |
| `cost.view` | ADM | ROUTE-011, ROUTE-015, ROUTE-016, ROUTE-017, ROUTE-019, ROUTE-020, ROUTE-028, ROUTE-032, ROUTE-049, ROUTE-051, ROUTE-055 | ROUTE-171, ROUTE-176, ROUTE-205, ROUTE-257 |
| `profit.view` | ADM | ROUTE-011, ROUTE-051 | — |
| `evidence.view` | ADM | ROUTE-038, ROUTE-047 | ROUTE-255 |
| `reports.export` | ADM | ROUTE-053 | ROUTE-283, ROUTE-284 |
| `reports.consolidated` | OWNER_ONLY | ROUTE-006, ROUTE-052 | ROUTE-283 |
| `review.view` | OWNER_ONLY | ROUTE-006, ROUTE-008 | — |
| `audit.view` | OWNER_ONLY | ROUTE-063 | — |
| `security_log.view` | OWNER_ONLY | ROUTE-064 | — |
| `scope.all_companies` | OWNER_ONLY | — | no control of its own — the Owner's scope in every branch (RC-03) |
| `users.manage` | OWNER_ONLY | ROUTE-060 | ROUTE-121, ROUTE-122, ROUTE-123, ROUTE-125, ROUTE-126, ROUTE-127, ROUTE-128, ROUTE-130 |
| `permissions.manage` | OWNER_ONLY | ROUTE-060 | ROUTE-124 |
| `companies.manage` | OWNER_ONLY | ROUTE-059, ROUTE-061 | ROUTE-131, ROUTE-132, ROUTE-133, ROUTE-134, ROUTE-135, ROUTE-136, ROUTE-137, ROUTE-228 |
| `tax.configure` | OWNER_ONLY | ROUTE-062 | ROUTE-138 |
| `requirements.set_waiver_eligibility` | OWNER_ONLY |  | ROUTE-167 |
| `requirements.relax` | OWNER_ONLY |  | ROUTE-168 |
| `writeoff.supersede` | OWNER_ONLY |  | ROUTE-245, ROUTE-248 |
| `stock.attribute_surplus` | OWNER_ONLY | ROUTE-029 | ROUTE-197 |
| `stock.resolve_unattributed` | OWNER_ONLY | ROUTE-029 | ROUTE-198, ROUTE-200 |
| `receivables.write_off` | OWNER_ONLY |  | ROUTE-247 |
| `projects.force_complete` | OWNER_ONLY |  | ROUTE-162 |
| `import.commit` | OWNER_ONLY | ROUTE-058 | ROUTE-275 |
| `parties.manage` | ADM |  | ROUTE-141, ROUTE-142, ROUTE-143, ROUTE-144 |
| `products.edit` | ADM |  | ROUTE-145, ROUTE-146, ROUTE-147, ROUTE-228 |
| `warehouse.manage` | ADM |  | ROUTE-148 |
| `projects.manage` | ADM | ROUTE-013 | ROUTE-151, ROUTE-152, ROUTE-153, ROUTE-160 |
| `quotation.manage` | ADM | ROUTE-014 | ROUTE-154, ROUTE-155, ROUTE-156, ROUTE-157, ROUTE-222 |
| `projects.confirm` | ADM | ROUTE-014 | ROUTE-157, ROUTE-158 |
| `stock.reserve` | ADM | ROUTE-022, ROUTE-025 | ROUTE-160, ROUTE-176, ROUTE-181, ROUTE-183, ROUTE-184, ROUTE-185, ROUTE-188, ROUTE-193, ROUTE-194, ROUTE-195, ROUTE-210, ROUTE-211 |
| `purchase.manage` | ADM | ROUTE-017 | ROUTE-171, ROUTE-172 |
| `stock.receive` | ADM | ROUTE-021, ROUTE-022 | ROUTE-181 |
| `stock.dispatch` | ADM | ROUTE-023, ROUTE-024 | ROUTE-186 |
| `stock.move` | ADM |  | ROUTE-190 |
| `stock.count` | ADM | ROUTE-026 | ROUTE-191, ROUTE-192 |
| `delivery.record` | ADM | ROUTE-031 | ROUTE-201 |
| `dropship.confirm` | ADM | ROUTE-032 | ROUTE-205 |
| `handover.record` | ADM | ROUTE-033 | ROUTE-208 |
| `documents.issue` | ADM | ROUTE-036 | ROUTE-182, ROUTE-187, ROUTE-202, ROUTE-221, ROUTE-222, ROUTE-249 |
| `evidence.upload` | ADM |  | ROUTE-228, ROUTE-229, ROUTE-230, ROUTE-231, ROUTE-261 |
| `invoice.issue` | ADM | ROUTE-042 | ROUTE-241, ROUTE-242 |
| `billing.record` | ADM |  | ROUTE-243 |
| `payment.record` | ADM | ROUTE-046 | ROUTE-250, ROUTE-251 |
| `payment.apply` | ADM |  | ROUTE-252 |
| `expense.record` | ADM | ROUTE-049 | ROUTE-257 |
| `disbursement.record` | ADM |  | ROUTE-258 |
| `cashin.record` | ADM |  | ROUTE-259 |
| `bank_statement.record` | ADM | ROUTE-050 | ROUTE-261 |
| `requirements.manage` | ADM |  | ROUTE-164, ROUTE-165 |
| `projects.complete` | ADM |  | ROUTE-161 |
| `receivables.dispute` | ADM |  | ROUTE-246 |
| `requirements.waive` | ADM |  | ROUTE-166 |
| `import.prepare` | ADM | ROUTE-058 | ROUTE-228, ROUTE-272, ROUTE-273, ROUTE-274 |
| `stock.reverse` | ADM_PLUS |  | ROUTE-183, ROUTE-188, ROUTE-189, ROUTE-196, ROUTE-212, ROUTE-213, ROUTE-211 |
| `sales_return.record` | ADM_PLUS | ROUTE-032, ROUTE-034 | ROUTE-207, ROUTE-210 |
| `purchase_return.record` | ADM_PLUS | ROUTE-028 | ROUTE-176, ROUTE-177 |
| `purchase.correct_cost` | ADM_PLUS |  | ROUTE-175 |
| `finance.contra` | ADM_PLUS |  | ROUTE-214, ROUTE-254, ROUTE-260 |
| `payment.reallocate` | ADM_PLUS |  | ROUTE-253 |
| `invoice.correct` | ADM_PLUS |  | ROUTE-245 |
| `documents.correct` | ADM_PLUS | ROUTE-036 | ROUTE-223, ROUTE-224 |
| `billing.correct` | ADM_PLUS |  | ROUTE-244 |
| `decisions.supersede` | ADM_PLUS |  | ROUTE-169 |
| `fulfillment.correct` | ADM_PLUS | ROUTE-031, ROUTE-032 | ROUTE-204, ROUTE-206, ROUTE-209 |
| `warnings.override` | ADM_PLUS |  | ROUTE-251 |
| `cash.correct` | ADM_PLUS |  | ROUTE-262 |
| `purchase.cancel` | ADM_PLUS |  | ROUTE-173 |
| `refund.record` | ADM_PLUS |  | ROUTE-256 |
| `settlement.record` | ADM_PLUS | ROUTE-047 | ROUTE-255 |
| `stock.adjust` | ADM_PLUS | ROUTE-027 | ROUTE-194, ROUTE-203 |
| `stock.condition` | ADM_PLUS | ROUTE-027 | ROUTE-195 |
| `stock.count_apply` | ADM_PLUS | ROUTE-026 | ROUTE-193 |
| `stock.resolve_unattributed_evidence` | ADM_PLUS | ROUTE-029 | ROUTE-199, ROUTE-200 |
| `stock.allocate_intercompany` | ADM_PLUS | ROUTE-024 | ROUTE-176, ROUTE-186, ROUTE-194 |
| `projects.cancel_scope` | ADM_PLUS |  | ROUTE-159 |
| `delivery.close` | ADM_PLUS | ROUTE-031 | ROUTE-203 |
| `purchase.close_remainder` | ADM_PLUS |  | ROUTE-174 |
| `cases.close` | ADM_PLUS |  | ROUTE-271 |
| `projects.cancel` | ADM_PLUS |  | ROUTE-163 |

#### Q.8 Owner-only denials OD-01–OD-16

Each denial with the Owner's page and the Admin's absence ([ROUTE_CONTRACTS](../../03-workflows/ROUTE_CONTRACTS.md) §4, intentionally hidden; NV-08).

| OD | Owner's pages | For an Admin |
| --- | --- | --- |
| OD-01 | PG-060 | absent — *Pengaturan* is not in an Admin's menu |
| OD-02 | PG-060 | absent |
| OD-03 | PG-059, PG-061 | absent; identity fields read where they print (PJ-19) |
| OD-04 | PG-062 | absent; configured values read-only in forms |
| OD-05 | PG-011, PG-039 | absent; an Admin may only add or tighten |
| OD-06 | the requirement's relaxation controls, same places | absent; an Admin may only add or tighten |
| OD-07 | PG-043 | absent |
| OD-08 | PG-029 | absent; an Admin's count application leaves the finding open |
| OD-09 | PG-029 | absent |
| OD-10 | PG-029 | present only with `stock.resolve_unattributed_evidence` (the full Admin); absent for Dimas and Bayu |
| OD-11 | PG-043, PG-044 | absent |
| OD-12 | PG-012 | absent; the address answers IP-21 |
| OD-13 | PG-058 | absent; preparation with `import.prepare` |
| OD-14 | PG-006, PG-052, PG-008, PG-063, PG-064 | absent |
| OD-15 | PG-008, PG-063 | absent; `decisions.supersede` covers ADM and ADM+ decisions only |
| OD-16 | PG-064, PG-008 | — |

#### Q.9 Totals

**299 rows** in Q.1–Q.8 — SCR 60, CAP 18, AC 23, AX 37, MSG 41, QS 24, capabilities 80, OD 16; **rows without a destination: 0** — **100 % mapped**. **Orphans:** pages no requirement reaches 0; routes without a capability or rule 0; interactions without a route or a client-only mark 0 of 165 — **0 orphan**.

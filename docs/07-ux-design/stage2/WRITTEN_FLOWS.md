# Written flows

Status: DRAFT | Updated: 2026-10-14 | Owner: Planning

**Draft — not normative.** This is an S2-1 document of Stage 2 of the P7 UX re-baseline, written under [DIR-061](../../00-governance/DECISION_LOG.md#dir-061-dir-062-obs-023-and-tech-031--p7-ux-re-baseline-stage-2-decisions-s2-0-authorization-baseline-and-prototype-setup) SD-01, SD-05 and SD-10 and [DIR-064](../../00-governance/DECISION_LOG.md#dir-063-dir-064-obs-024-and-tech-032--p7-ux-re-baseline-s2-0-acceptance-s2-1-authorization-baseline-and-stage-2-documents) §12.4. Behaviour follows MULTIPLECORP — P0–P6 and ADMIN_FLOW —, structure follows R1, and presentation follows the Golden Frontend Reference v1.0.0, which adapts to MULTIPLECORP and never the reverse (AK-1; SD-02). [INFORMATION_ARCHITECTURE](../INFORMATION_ARCHITECTURE.md) and [DESIGN_SYSTEM](../DESIGN_SYSTEM.md) stay APPROVED under APPR-008 and under replacement, and [ADMIN_FLOW](../ADMIN_FLOW/README.md) stays in force, until a later authorized stage changes them; Stage 3 uses this draft, and nothing in it is normative.

**Sources.** [ADMIN_FLOW](../ADMIN_FLOW/README.md) — the journeys of [§10](../ADMIN_FLOW/s10-a-journeys.md#10-journeys), the command model of [§5](../ADMIN_FLOW/s05-command-model.md#5-command-interaction-model) (IP-01–IP-10, IP-19, IP-21), sessions and forms of [§§6–9](../ADMIN_FLOW/s06-09-session-forms-lists.md#6-session-expiry-re-authentication-and-unsent-input) (IP-11–IP-18, IP-20), the corrections of [§11](../ADMIN_FLOW/s11-13-corrections-numbering-import.md#11-corrections-and-reversals) and the messages of [§14](../ADMIN_FLOW/s14-15-messages-scenarios.md#14-message-catalogue); [WORKFLOWS](../../03-workflows/WORKFLOWS/README.md) §5 and the indivisible actions AX-01–AX-37 of [§9](../../03-workflows/WORKFLOWS/s08-09-corrections-indivisible.md#9-indivisible-business-actions); [PERMISSIONS_MATRIX §4](../../05-security/PERMISSIONS_MATRIX.md#4-capability-catalogue); [SECURITY](../../05-security/SECURITY.md) AU-03, AU-04 and AU-07 where ADMIN_FLOW predates the authentication amendment; the R1 structure through [IA_PAGE_INVENTORY](IA_PAGE_INVENTORY.md) and [NAVIGATION_MAP](NAVIGATION_MAP.md); [QUERY_BUDGET_CHECKS](QUERY_BUDGET_CHECKS.md). No technology behaviour is stated.

## 1. How to read a flow

- **A flow changes where a step happens, never what it does.** [ADMIN_FLOW](../ADMIN_FLOW/README.md#admin-and-owner-flows--interaction-model)'s commands, confirmations, answers and messages stand; each flow places them on the pages of IA_PAGE_INVENTORY (PG). Where R1 places a step differently from ADMIN_FLOW's screens and entry points, the flow's **R1 placement** line says so: Stage 3 aligns ADMIN_FLOW.
- **What every flow shares is not repeated:** each command's answers — COMMITTED, REJECTED, CONFLICT, PENDING, FAILED — follow IP-01 with their copy patterns MSG-01–MSG-07; its date follows IP-07 ("Tanggal transaksi"); its confirmation IP-08 (irreversible) or IP-09 (corrections, closures and destructive actions, with the reason of MSG-40); its identifier IP-02; several commands on one page each answer for themselves (IP-19). A step-up follows IP-10 (WFL-09); a refusal routed to another company IP-06 (MSG-14); a duplicate warning IP-05 (MSG-15).
- **Branches and refusals** are cited from the journey's ADMIN_FLOW row, not restated: the identifiers given lead to their rules.
- **Personas** are IA_PAGE_INVENTORY §1's — Laras, the Owner, holds every capability; Dimas and Bayu hold their illustrative grant sets. "Absent" means the control or page does not exist for that account ([AZ-01](../../05-security/PERMISSIONS_MATRIX.md#2-authorization-model); [H7-03](../../05-security/SECURITY.md#p7--presentation-only)).
- **Where ADMIN_FLOW predates the authentication amendment** — the re-login dialog of IP-11 and J-ACC-01's password wording — SECURITY governs: AU-03 and AU-04 ([GAP-044](../../00-governance/GAP_REGISTER.md#triage); H7-19), and [AUTH_JOURNEYS](AUTH_JOURNEYS.md) designs the journeys.

## 2. The ADMIN_FLOW journeys

One flow per ADMIN_FLOW journey — all 39, one per [WORKFLOWS](../../03-workflows/WORKFLOWS/README.md#critical-business-workflows) §5 workflow —, in ADMIN_FLOW §10's order.

### 2.1 Access and master data — [ADMIN_FLOW §10.1](../ADMIN_FLOW/s10-a-journeys.md#101-access-and-master-data)

#### J-ACC-01 — Session and company context `[both]`

Personas: all three. Entry: PG-001; after sign-in, PG-006 (Owner) or PG-007 (Admin). R1 placement: ADMIN_FLOW's [SCR-01](../INFORMATION_ARCHITECTURE.md#81-system-account-and-beranda) → SCR-05 or SCR-06 becomes PG-001 → PG-002 → *Beranda* for the Owner and *Pekerjaan* for an Admin (R1 a)); the second factor and Google sign-in are [SECURITY](../../05-security/SECURITY.md#security-architecture)'s (AU-16; AU-22), designed in [AUTH_JOURNEYS](AUTH_JOURNEYS.md).

| Step | Page | Control | Command · capability | Answer | Next |
| --- | --- | --- | --- | --- | --- |
| 1 | PG-001 | e-mail, password, "Masuk" — or "Lanjutkan dengan Google" | sign-in, not a command ([CI-09](../../06-api-performance/CONCURRENCY_IDEMPOTENCY/s10-13-identity-failure-retry.md#10-command-identity-and-replay)) | the generic failure MSG-19 (AU-04); the limit MSG-20 (AU-05) | PG-002 |
| 2 | PG-002 | the six-digit code, "Verifikasi" — or a recovery code after a password | the challenge (AU-16; AU-19) | the generic and cooldown answers (AU-05) | the landing page; a high-risk account without TOTP, PG-004 (AU-18) |
| 3 | PG-006 or PG-007 | work — every page by its entries | — | MSG-29 on the first page after a credential event (H7-14; H7-18) | — |
| 4 | the account menu | *Keluar* | logout (AU-06) | MSG-35 first when input is unsent (WFL-06) | PG-001 |

Branches and refusals: ADMIN_FLOW J-ACC-01 — MSG-19, MSG-20, MSG-28, MSG-29, IP-21 for a revoked grant; expiry and re-authentication WFL-05. The password rule J-ACC-01 states — "at least 15 characters, no composition rule" — predates the amendment: AU-03's profile AICWDF-COMPAT-8 governs ([GAP-044](../../00-governance/GAP_REGISTER.md#triage)). Differences: a new Admin without any grant sees only *Akun Saya*, the S0 lookups and MSG-38 on *Pekerjaan* ([RG-04](../../05-security/PERMISSIONS_MATRIX.md#3-role-and-grant-model)); the Owner's *Beranda* heads its decisions with MSG-29's Owner variant when a link the Owner issued is used (AU-12). Phone · desktop: identical, one narrow column.

#### J-ACC-02 — Users, roles, capabilities and company grants `[desk]` — OWN!

Personas: Laras only (`users.manage`, `permissions.manage`). Entry: *Menu lengkap* › *Pengaturan* › *Pengguna & Akses* (PG-060); *Beranda*'s security notice. R1 placement: as ADMIN_FLOW, SCR-57 under *Pengaturan* in *Menu lengkap*.

| Step | Page | Control | Command · capability | Answer | Next |
| --- | --- | --- | --- | --- | --- |
| 1 | PG-060 | create the account; role; company grants; capability grants with each prerequisite stated (RG-06) | account and grant commands · `users.manage`, `permissions.manage` | each change confirmed, then the step-up (IP-10; WFL-09); REJECTED for the last active Owner (RG-09) | the account's detail |
| 2 | PG-060 | the preview — every granted capability against every granted company (RG-07; GAP-032) | — | — | — |
| 3 | PG-060 | "Buat Tautan Atur Kata Sandi" | link issue · `users.manage` | the link shown once with its expiry, for out-of-band delivery (AU-12) | — |
| 4 | PG-060 | end sessions, revoke a link, deactivate; H7-17's TOTP reset and Google unlink | the same capabilities | IP-10; [AUTH_JOURNEYS](AUTH_JOURNEYS.md) | — |

Branches and refusals: ADMIN_FLOW J-ACC-02 — TX-09's wait, IP-04, RG-09, OD-02, CI-05 for a replayed link. Corrections: a grant made in error is revoked on the same page, history keeping both (RG-11). Differences: the page does not exist for an Admin (OD-01; IP-21). Phone · desktop: as ADMIN_FLOW — read, "Akhiri Sesi" and "Nonaktifkan" on the phone; grant editing a desk task.

#### J-MD-01 — Company master `[desk]` — OWN!

Personas: Laras only (`companies.manage`, `tax.configure`). Entry: *Pengaturan* › *Perusahaan* (PG-059), *Penomoran Dokumen* (PG-061), *Pajak* (PG-062). R1 placement: as ADMIN_FLOW, in *Menu lengkap* › *Pengaturan*.

| Step | Page | Control | Command · capability | Answer | Next |
| --- | --- | --- | --- | --- | --- |
| 1 | PG-059 | legal data; identity assets and bank accounts, each behind the step-up | separate commands, each answered (IP-19) · `companies.manage` | IP-10 (WFL-09) for assets and bank accounts | — |
| 2 | PG-062 | tax configuration | `tax.configure` | — | — |
| 3 | PG-061 | numbering schemes and the start per type (ADMIN_FLOW §12) | NX-01; "Mulai Penomoran", "Tetapkan Nomor Awal" · `companies.manage` | IP-08's confirmation; MSG-17 meets an Admin elsewhere until a type is started (C-10) | — |
| 4 | PG-059 | activation; later deactivation | `companies.manage` | the dialog states what stops and what continues ([BR-ACC-03](../../02-domain/BUSINESS_RULES.md#access-boundary-acc)) | — |

Branches and refusals: ADMIN_FLOW J-MD-01 — C-10, BR-ACC-03, BR-SN-01. Corrections: master data is edited, never reversed; a wrong bank account is deactivated and a new one added. Differences: Admins read the company's name, state and printed identity fields (PJ-19) and never this page's commands. Phone · desktop: desktop only for editing.

#### J-MD-02 — Client organization, unit and PIC `[both]`

Personas: all three read; edits Laras (`parties.manage`) — Dimas sees units, addresses and PICs (`projects.view`). Entry: *Data Master* › *Klien* (PG-054); inline from the project form (PG-013). R1 placement: as ADMIN_FLOW.

| Step | Page | Control | Command · capability | Answer | Next |
| --- | --- | --- | --- | --- | --- |
| 1 | PG-054 or PG-013 inline | the name typed — existing organizations listed first ([PF-21](../../06-api-performance/PERFORMANCE.md#5-search)) | search, not a command | — | — |
| 2 | PG-054 | select or create the organization, unit with its address, PIC | master commands · `parties.manage` | a unit name that exists — CONFLICT (BD-16) | the record, or back to PG-013 |

Branches and refusals: ADMIN_FLOW J-MD-02 — archive with live history allowed (WF-MD-02). Differences: names are S0; units, addresses and PICs need `projects.view` and a company in scope (PJ-10). Phone · desktop: lookup and a one-column create inside the project flow on the phone.

#### J-MD-03 — Supplier master `[desk]`

Personas: all three read; edits Laras (`parties.manage`). Entry: *Data Master* › *Pemasok* (PG-055); inline from *Buat Pembelian* (PG-017). R1 placement: as ADMIN_FLOW.

| Step | Page | Control | Command · capability | Answer | Next |
| --- | --- | --- | --- | --- | --- |
| 1 | PG-055 or PG-017 inline | create or select a supplier directly — no comparison or ranking step ([V1_SCOPE](../../01-product/V1_SCOPE.md#v1-scope-and-product-constraints)) | master command · `parties.manage` | — | the record, or back to PG-017 |

Branches and refusals: archiving blocks new purchases only. Differences: price history per company with `cost.view` (PJ-09). Phone · desktop: desktop; the phone shows lookup and detail.

#### J-MD-04 — Catalog, units, barcodes, serial policy and price defaults `[desk]`

Personas: all three read; edits Laras (`products.edit`). Entry: *Data Master* › *Produk & Jasa* (PG-056); "Daftarkan Barcode" from a scan (IP-15). R1 placement: as ADMIN_FLOW.

| Step | Page | Control | Command · capability | Answer | Next |
| --- | --- | --- | --- | --- | --- |
| 1 | PG-056 | product or service → SKU → base and alternate units → barcode, or "Buat Barcode Internal" with "Cetak Label" → serial policy → price defaults → images → activate | `products.edit` | REJECTED for a barcode of another active product (BR-INV-09) or a serial switch with stock on hand (L-35); CONFLICT for open forms (ST-09) | — |

Branches and refusals: ADMIN_FLOW J-MD-04. Corrections: edit, archive; a price default changes only forward. Differences: purchase defaults `cost.view`, selling and SPJ-reference defaults `finance.view` (PJ-14). Phone · desktop: the phone for lookup, detail and barcode registration from a scan.

#### J-MD-05 — Locations and minimum stock `[desk]`

Personas: all three read (`stock.view`); maintenance Laras (`warehouse.manage`); moves need `stock.move`, which neither Admin holds. Entry: *Data Master* › *Lokasi Rak* (PG-057); "Pindah Lokasi" from PG-018 and PG-019. R1 placement: as ADMIN_FLOW.

| Step | Page | Control | Command · capability | Answer | Next |
| --- | --- | --- | --- | --- | --- |
| 1 | PG-057 | racks and the per-product minimum | `warehouse.manage` | — | — |
| 2 | PG-019 | "Pindah Lokasi" — a zero-net movement (L-36) | `stock.move` | — | PG-019 |

Branches and refusals: the restock advice of QS-17 never creates a purchase (BR-INV-10); its row links to PG-017 with the product prefilled. Corrections: the opposite move. Phone · desktop: the move is a phone flow with the scan field.

### 2.2 Project and commercial — [ADMIN_FLOW §10.2](../ADMIN_FLOW/s10-a-journeys.md#102-project-and-commercial)

#### J-PRJ-01 — Project setup and amendment `[both]`

Personas: Laras, Dimas (`projects.manage`). Entry: "Buat" › *Proyek*; *Proyek* (PG-010) › "Buat Proyek"; the journey's *Tindakan Lain* › "Ubah proyek". R1 placement: ADMIN_FLOW's SCR-09 → SCR-11 → SCR-10 becomes PG-010 or "Buat" → PG-013 → the project journey PG-011 (R1 a), e)).

| Step | Page | Control | Command · capability | Answer | Next |
| --- | --- | --- | --- | --- | --- |
| 1 | PG-013 | "Perusahaan", chosen explicitly (CS-03); client, unit and PIC (J-MD-02 inline); channel *Reguler* or *SIPLAH*, the SIPLAH fields only for that channel (BR-FIN-10); demand lines with their mode (IP-13) | — | validation beside the fields (IP-14; MSG-12) | — |
| 2 | PG-013 | save as *Draf*; *Aktif* once company, client and channel are set | NX-03 · `projects.manage` | COMMITTED with the number, assigned once (MSG-01); REJECTED MSG-17 when PROJECT numbering is not started (C-10) | PG-011 |
| 3 | PG-013 | "Buang Draf" — irreversible, the number retired (L-37) | `projects.manage` | IP-08 | PG-010 |

Branches and refusals: ADMIN_FLOW J-PRJ-01 — L-20 (after confirmation, a revision or re-confirmation), L-21, BR-PRJ-05, XL-4. Corrections: before confirmation, edit; after it, J-QUO-01's revision; cancellation J-PRJ-04. Differences: prices, tax components and SIPLAH amounts need `finance.view`. Phone · desktop: a project with a few lines on the phone; the full line editor on the desktop.

#### J-QUO-01 — Quotation cycle `[desk]`

Personas: Laras, Dimas (`quotation.manage` with `finance.view`; `projects.confirm`). Entry: the journey's *Kesepakatan* stage (PG-011) › PG-014; *Dokumen* › *Penawaran* (PG-037) across projects. R1 placement: ADMIN_FLOW's SCR-10 part *Penawaran* becomes the journey's *Kesepakatan* stage, whose next action names "Terbitkan penawaran" or "Catat Jawaban Klien" (journey model c)); SCR-12 sits in *Menu lengkap* › *Dokumen* (R1's revised map).

| Step | Page | Control | Command · capability | Answer | Next |
| --- | --- | --- | --- | --- | --- |
| 1 | PG-014 | the draft from the project's lines (IP-13) | `quotation.manage` | — | — |
| 2 | PG-014 | "Terbitkan Penawaran" — DOC-01 through SF-ISSUE; the revision becomes *Terkirim* | AX-15 · `quotation.manage` | IP-08 (MSG-39); COMMITTED with the number | PG-014 |
| 3 | PG-014 | "Catat Jawaban Klien": *Disetujui*, with evidence where available, or *Ditolak* | AX-13 · `projects.confirm` | CONFLICT for a second approval (BD-05); approval is the confirmation (J-PRJ-02) | PG-011 |

Branches and refusals: ADMIN_FLOW J-QUO-01 — *Kedaluwarsa* offers only "Buat Revisi" (L-25); only the latest sent revision can be approved; never voided (BR-CR-06); nothing implies stock or money (BR-QUO-03). Corrections: "Buat Revisi"; an approval in error CM-27. Differences: with `projects.view` alone a quotation is listed without values. Phone · desktop: the phone reads and records the client's answer.

#### J-PRJ-02 — Commercial confirmation `[desk]`

Personas: Laras, Dimas (`projects.confirm`; the reservation step `stock.reserve`). Entry: PG-014 (approval) or the journey's *Kesepakatan* stage › "Konfirmasi Proyek". R1 placement: SCR-10's "Konfirmasi Proyek" sits in the *Kesepakatan* stage, as its next action without a quotation (journey model c)).

| Step | Page | Control | Command · capability | Answer | Next |
| --- | --- | --- | --- | --- | --- |
| 1 | PG-014 | (A) *Disetujui* on the latest sent revision | AX-13 · `projects.confirm` | COMMITTED with the pinned lines | PG-011 |
| 1′ | PG-011 | (B) "Konfirmasi Proyek" with the client's order evidence (IP-16) | AX-13 · `projects.confirm` | COMMITTED with the pinned lines | PG-011 |
| 2 | PG-011 | the reservations proposed for warehouse lines, each its own command (IP-19; L-02) | AX-01 · `stock.reserve` | each answered | J-INV-02 |
| 3 | PG-036 | the optional company SPK (DOC-09) | AX-15 · `documents.issue` | IP-08 | — |

Branches and refusals: ADMIN_FLOW J-PRJ-02 — a re-confirmation below what was dispatched, delivered or invoiced is REJECTED with the path (BR-QUO-02). Corrections: CM-27. Differences: without `stock.reserve` the confirmation commits and the reservation waits in QS-01 for a colleague. Phone · desktop: the approval as a quick action on the phone.

#### J-FUL-01 — Fulfilment planning per demand line `[desk]`

Personas: Laras, Dimas (`projects.manage`; reservation `stock.reserve`; availability `stock.view`). Entry: the journey's *Rincian proyek* › *Item dan pemenuhan* (PG-011). R1 placement: ADMIN_FLOW's SCR-10 part *Pemenuhan* becomes the drill-down *Item dan pemenuhan*; the shortage's "Buat Pembelian" is also the *Gudang* stage's next action (journey model c)).

| Step | Page | Control | Command · capability | Answer | Next |
| --- | --- | --- | --- | --- | --- |
| 1 | PG-011 | per confirmed line, the mode *Gudang*, *Kirim langsung* or *Jasa* | `projects.manage` | — | — |
| 2 | PG-011 | for *Gudang*: availability from the pooled view; "Reservasi" up to it | AX-01 · `stock.reserve` | REJECTED with the current availability for a lost last-unit race (ST-06) | — |
| 3 | PG-011 | the rest shown as a shortage with "Buat Pembelian" (QS-02) | — | — | PG-017 (J-PUR-01) |
| 4 | PG-011 | "Ubah Cara Pemenuhan" — only the unstarted remainder splits, with a reason (L-21) | AX-14 · `projects.manage`; `stock.reserve` when a reservation is released | IP-09 | — |

Corrections: SF-CANCEL-LINE for a remainder (CM-29, CM-31). Differences: availability figures need `stock.view`. Phone · desktop: the phone shows per-line progress.

### 2.3 Inventory and purchasing — [ADMIN_FLOW §10.3](../ADMIN_FLOW/s10-a-journeys.md#103-inventory-and-purchasing)

#### J-INV-02 — Reservation management `[desk]`

Personas: all three (`stock.reserve`, with `stock.view` and `projects.view`). Entry: *Gudang* › *Reservasi* (PG-025); *Pekerjaan*'s QS-01 and QS-03 rows "Reservasi"; the journey's *Item dan pemenuhan*; QS-04 › PG-011. R1 placement: QS-01, QS-03 and QS-04 rows in *Pekerjaan* › *Barang* and the *Gudang* stage (R1's revised signals).

| Step | Page | Control | Command · capability | Answer | Next |
| --- | --- | --- | --- | --- | --- |
| 1 | PG-025 | "Reservasi" on a confirmed warehouse line — at most the available quantity and the line's remainder | AX-01 · `stock.reserve` | REJECTED with the current availability for a lost last-unit race (ST-06), never CONFLICT | — |
| 2 | PG-025 | "Kurangi Reservasi" or "Lepas", with a reason | AX-02 · `stock.reserve` | IP-09 | — |

Branches and refusals: ADMIN_FLOW J-INV-02 — a cut forced by shrinking supply is IP-20; a residual reservation of a completed or cancelled project is "perlu dilepas" (QS-16). Corrections: release; a consumed reservation is never reactivated (L-07). Differences: other companies' projects show the quantity and the relation marker only (PJ-04). Phone · desktop: reservations per line read-only on the phone.

#### J-PUR-01 — Project purchase `[desk]`

Personas: Laras, Dimas (`purchase.manage` with `cost.view`). Entry: *Pekerjaan*'s QS-02 row "Buat Pembelian", prefilled; the journey's *Gudang* stage; "Buat" › *Pembelian*; *Pembelian* (PG-015). R1 placement: ADMIN_FLOW's SCR-10 part *Pengadaan* or SCR-15 → SCR-17 → SCR-16 becomes these entries → PG-017 → PG-016; the purchase from a shortage shows the project's company, not chosen (R1's connected-work map; RS-9).

| Step | Page | Control | Command · capability | Answer | Next |
| --- | --- | --- | --- | --- | --- |
| 1 | PG-017 | supplier (direct choice; J-MD-03 inline) | — | — | — |
| 2 | PG-017 | lines — product, quantity and unit, actual price, tax components and treatment, the link to the demand line, "Kirim langsung ke klien" for a drop-ship line (IP-13) | — | IP-14 | — |
| 3 | PG-017 | purchase charges under "Rincian lain", each with its source bill and total and its classification — *Masuk biaya perolehan* with an allocation basis, or *Beban* (SF-CHARGE) | recorded with step 4's save; allocated by SF-CHARGE at receipt under AX-29 | REJECTED for charges beyond one bill's total (L-43) | — |
| 4 | PG-017 | "Simpan" — the purchase is *Terbuka* | NX-04 · `purchase.manage` | COMMITTED: no cash has left and no stock has arrived (BR-PUR-03; MSG-01) | PG-016 |
| 5 | PG-016 | optionally "Terbitkan PO" or its layout "Surat Pesanan" (J-DOC-01) | AX-15 · `documents.issue` | IP-08 | — |

Branches and refusals: ADMIN_FLOW J-PUR-01 — after the first receipt the lines are fixed: "Tambahkan Pembelian Susulan" (L-23). Corrections: CM-17; "Batalkan Pembelian" (`purchase.cancel`) and "Tutup Sisa Pembelian" (`purchase.close_remainder`) with a reason (CM-18). Differences: the whole journey needs `cost.view`; the company is the project's, shown and not chosen (L-05). Phone · desktop: the line editor for thirty lines by keyboard on the desktop; lookup and detail on the phone. **S2-J04** prototypes it.

#### J-PUR-02 — Replenishment purchase `[desk]`

As J-PUR-01 without a project: the company is an explicit field and the lines are goods. Entry: "Buat" › *Pembelian*; *Pembelian* (PG-015); the restock advice of QS-17 (PG-018, "Buat Pembelian", never a purchase by itself — BR-INV-10). R1 placement: as J-PUR-01.

#### J-INV-01 — Receiving, *Barang Masuk* `[floor]`

Personas: Laras, Bayu (`stock.receive`; the offer `stock.reserve`). Entry: *Pekerjaan*'s "Berikutnya" › "Mulai Terima Barang" and the QS-05 rows; *Gudang* › *Barang Masuk* (PG-021); "Buat" › *Barang Masuk*; PG-016's "Terima Barang". R1 placement: ADMIN_FLOW's SCR-21 becomes the worklist PG-021 and the task PG-022, entered directly from the warehouse home (R1 f); IA_PAGE_INVENTORY §5).

| Step | Page | Control | Command · capability | Answer | Next |
| --- | --- | --- | --- | --- | --- |
| 1 | PG-022 | the open purchase's lines as a checklist — "2 baris tercatat · 2 baris tersisa", the next line marked (D-UX-11) — under the receiving projection, no price, tax, charge or cost (PJ-18) | — | — | — |
| 2 | PG-022 | scan or "Cari Produk" (IP-15) | exact lookup (QB-06) | MSG-25, MSG-36, MSG-37 | the line's sheet |
| 3 | PG-022, the line's sheet | *Layak pakai* to a rack, *Rusak diterima*, *Ditolak* (L-13); serials; the supplier's delivery reference; evidence (IP-16) | — | — | — |
| 4 | PG-022, the line's sheet | for a project line, "Reservasi untuk Proyek Ini" — not preselected, a quantity of the usable units (D-UX-12) | inside step 5 | — | — |
| 5 | PG-022, the line's sheet | "Catat Barang Masuk" with IP-08's confirmation, a full-height sheet — the receipt and its reservation one command per line | AX-04 · `stock.receive`; the reservation `stock.reserve` | each line answered (IP-19): COMMITTED, *Tercatat*; REJECTED above the remainder (PX-03); CONFLICT "Sudah Tercatat" (BD-01; MSG-04); FAILED IP-04 (MSG-07) | the checklist |
| 6 | PG-022 | the summary of every line's confirmed outcome; "Terbitkan Nota Terima Barang" (DOC-12) for a line's receiving event, on request | AX-15 · `documents.issue` | IP-08 | "Kembali ke Pekerjaan" › PG-007 |

Branches and refusals: ADMIN_FLOW J-INV-01 — a serial already in stock is refused at the scan; an unknown barcode IP-15 step 5; the reservation offer follows S2-R01–S2-R09 ([S2-J05](journeys/S2-J05.md)). Corrections: CM-02, CM-03 (`stock.reverse`), CM-04. Differences: `stock.receive` without `cost.view` works the whole journey and never sees a price; without `stock.reserve` the offer is absent (S2-R06). Phone · desktop: scan-led, one line at a time on the phone; lines and the current line side by side on the desktop. **S2-J05** prototypes it.

#### J-INV-03 — Warehouse dispatch, *Barang Keluar* `[floor]`

Personas: Laras, Bayu (`stock.dispatch`, with `stock.view` and `projects.view`). Entry: *Pekerjaan* › *Keluarkan dan kirim* › the link to the worklist (PG-023); *Gudang* › *Barang Keluar*; the *Gudang* stage's "dispatch" next action (PG-011). R1 placement: ADMIN_FLOW's SCR-22 becomes the worklist PG-023 and the task PG-024; R1's dispatch-ready rows in *Pekerjaan* do not fit QB-10's register, so *Pekerjaan* links to the worklist (QUERY_BUDGET_CHECKS §3.4; OQ-03).

| Step | Page | Control | Command · capability | Answer | Next |
| --- | --- | --- | --- | --- | --- |
| 1 | PG-023 | a project's confirmed lines with remaining reservations (CALC-02) | — | — | PG-024 |
| 2 | PG-024 | scan products and serials (IP-15); confirm the lots — the oldest-first order a picking aid from the physical-only view (PJ-01) | — | MSG-36 for a serial that does not fit | — |
| 3 | PG-024 | "Catat Barang Keluar" | AX-03 · `stock.dispatch` | IP-08; REJECTED with the current figure for insufficient usable supply; IP-05 (MSG-15) for a likely duplicate; IP-06 (MSG-14) for another company's lot without the authority | — |
| 4 | PG-024 | "Terbitkan Surat Jalan" (DOC-03) | AX-15 · `documents.issue` | IP-08 | PG-023 |

Branches and refusals: ADMIN_FLOW J-INV-03 — another company's lot needs `stock.allocate_intercompany`, a grant on the consuming company and "Alasan alokasi antar-perusahaan" (XL-1; J-INV-07); a pending unattributed case never blocks ([DIR-027](../../00-governance/DECISION_LOG.md#dir-027-obs-007-and-tech-017--p4-authorization-unattributed-loss-clarification-and-p4-documentation)). Corrections: CM-05, CM-06, CM-07, CM-36 from the dispatch record. Differences: cost and HPP never shown. Phone · desktop: scan-led with one tap per lot on the phone; line, lots and serials side by side on the desktop.

#### J-INV-07 — Inter-company allocation, embedded

No page of its own: the reason field and the outcome inside J-INV-03, J-INV-06 and J-PUR-03; the allocation shows on the consuming project's cost with its marker (PG-011 *Pengadaan*; PJ-06) and in the Owner's allocation report (PG-052). A SYS consequence of the chosen lot or loss bearer, refused and routed without the authority (XL-1; IP-06), reversed only with its consumption (ADMIN_FLOW §11). R1 placement: as ADMIN_FLOW.

#### J-INV-04 — Condition change and quarantine `[floor]`

Personas: Laras, Bayu (`stock.condition`). Entry: *Gudang* › *Lainnya* › *Penyesuaian & Kondisi* (PG-027); a lot or serial on PG-019. R1 placement: SCR-25 under *Gudang* › *Lainnya*.

| Step | Page | Control | Command · capability | Answer | Next |
| --- | --- | --- | --- | --- | --- |
| 1 | PG-027 | "Ubah Kondisi" — lot or serials, *Layak pakai* → *Tidak layak pakai*, reason, evidence; the opposite is "Periksa Ulang" | AX-06 · `stock.condition` | IP-09; a cut of reservations IP-20; lots of several companies — the pending case, MSG-41, without naming or counting the others (PJ-07) | — |

Branches and refusals: ADMIN_FLOW J-INV-04 — L-42 for one company's candidate lots. Corrections: CM-09, CM-28. Differences: a physical-only action — one company in scope suffices (AZ-08). Phone · desktop: phone first — scan, condition, photograph.

#### J-INV-05 — Stock opname `[floor]`

Personas: Laras, Bayu (`stock.count`; applying `stock.count_apply`). Entry: *Gudang* › *Stok Opname* (PG-026); *Pekerjaan*'s QS-18 rows. R1 placement: QS-18's findings and stale counts in *Pekerjaan* › *Barang*.

| Step | Page | Control | Command · capability | Answer | Next |
| --- | --- | --- | --- | --- | --- |
| 1 | PG-026 | "Mulai Hitungan" for a scope — product, rack, condition | `stock.count` | — | — |
| 2 | PG-026 | record counted quantities and serials with the scan field | `stock.count` | MSG-30 when the scope moved — *Kedaluwarsa*, "Hitung Ulang" (ST-03) | — |
| 3 | PG-026 | "Terapkan Selisih" | AX-06 · `stock.count_apply` | IP-09; a shortage names its lots or becomes a pending case; a surplus an open finding for the Owner (QS-18) | — |

Branches and refusals: ADMIN_FLOW J-INV-05 — an unexpected serial is a finding, never registered silently. Corrections: CM-28; CM-36. Differences: applying needs ADM+; counting does not; the Owner alone attributes a surplus (PG-029). Phone · desktop: counting on the phone; applying and reviewing on the desktop.

#### J-INV-06 — Adjustment, disposal and loss `[floor]`

Personas: Laras, Bayu (`stock.adjust`). Entry: PG-027; PG-019's "Catat Penyesuaian Stok". R1 placement: as J-INV-04.

| Step | Page | Control | Command · capability | Answer | Next |
| --- | --- | --- | --- | --- | --- |
| 1 | PG-027 | "Catat Penyesuaian Stok" — disposal, shrinkage or other loss; lots or serials; reason; evidence; the loss recorded once (SF-LOSS) | AX-06, AX-30 · `stock.adjust` | IP-09; IP-20; IP-06 when the bearer is outside scope or needs the allocation authority (AZ-08) | — |

Branches and refusals: ADMIN_FLOW J-INV-06 — no increase is offered (L-15). Corrections: CM-28, CM-34. Differences: the loss amount only with `cost.view` of the bearer. Phone · desktop: phone first.

**Pending cases and count surpluses (PG-029).** ADMIN_FLOW §10.3's four commands, each answered for itself (IP-19): "Tetapkan Perusahaan" (AX-37, Laras — `stock.resolve_unattributed`), "Tetapkan dengan Bukti" (AX-37 under OD-10, an ADM+ holder of `stock.resolve_unattributed_evidence`, which neither Admin persona holds), "Tetapkan Surplus" (AX-07, Laras — `stock.attribute_surplus`) and "Balikkan Penetapan" (CM-27). Entry: *Beranda*'s QS-22 and QS-18 decision rows; *Gudang* › *Lainnya* › *Selisih & Temuan*.

#### J-PUR-03 — Purchase return `[floor]`

Personas: Laras (`purchase_return.record`, with `stock.view` and `cost.view`; closing `cases.close`). Entry: *Gudang* › *Lainnya* › *Retur ke Pemasok* (PG-028); the purchase (PG-016). R1 placement: SCR-26 under *Gudang* › *Lainnya* and on the purchase page.

| Step | Page | Control | Command · capability | Answer | Next |
| --- | --- | --- | --- | --- | --- |
| 1 | PG-028 | "Retur ke Pemasok": the case with a reason → present, unconsumed lots and serials → stock out | AX-11 · `purchase_return.record` | IP-09; IP-20 | — |
| 2 | PG-028 | the outcome *Diganti* or *Dikembalikan dananya*; later the refund or the replacement receipt | NX-16 · `purchase_return.record` | — | — |
| 3 | PG-028 | "Tutup Kasus" — only when returned cost = refunded + replaced + unrecovered | `cases.close` | REJECTED with the equation's open part (AX-11) | — |

Corrections: CM-16; a return recorded in error CM-37. Phone · desktop: selecting and scanning on the phone; the outcome and closure on the desktop.

### 2.4 Fulfilment — [ADMIN_FLOW §10.4](../ADMIN_FLOW/s10-b-journeys.md#104-fulfilment)

#### J-FUL-02 — Delivery, *Pengiriman* `[both]`

Personas: Laras, Bayu (`delivery.record`); Dimas sees the shipments (`projects.view`). Entry: *Pekerjaan*'s QS-06 row "Catat Pengiriman"; the journey's primary action "Catat Pengiriman" at the *Pengiriman* stage (PG-011); *Gudang* › *Lainnya* › *Pengiriman* (PG-030). R1 placement: ADMIN_FLOW's SCR-28 → SCR-29 gains the journey's primary action and the work row (R1 e); revised map); SCR-28 sits in *Gudang* › *Lainnya*.

| Step | Page | Control | Command · capability | Answer | Next |
| --- | --- | --- | --- | --- | --- |
| 1 | PG-031 | the shipment's lines: delivered quantity per line, recipient's name, date, condition, proof — the signed Surat Jalan or a photo (IP-16) | — | — | — |
| 2 | PG-031 | "Catat Pengiriman" | AX-09 · `delivery.record` | REJECTED above shipped; a difference or a condition problem opens the discrepancy case in the same form (SF-CASE); IP-05 for a likely duplicate | PG-011 or PG-030 |
| 3 | PG-031 | optionally the inspection facts (DOC-10) and BAST (DOC-08) | AX-15 · `documents.issue` | IP-08 | — |

Branches and refusals: ADMIN_FLOW J-FUL-02 — never suggests that a delivery changes stock (BR-INV-02). Corrections: CM-08 (`fulfillment.correct`); "Tutup Pengiriman" (`delivery.close`) with a reason; returns J-FUL-05. Differences: `projects.view` and the project's company in scope. Phone · desktop: at the client's site on the phone.

#### J-FUL-03 — Drop-ship, *Kirim Langsung* `[desk]`

Personas: Laras (`dropship.confirm`). Entry: the journey's *Kirim langsung* stage (PG-011) › PG-032. R1 placement: ADMIN_FLOW's SCR-10 part *Pemenuhan* becomes the *Kirim langsung* stage, after the drop-ship purchase (journey model c)).

| Step | Page | Control | Command · capability | Answer | Next |
| --- | --- | --- | --- | --- | --- |
| 1 | PG-032 | "Konfirmasi Kirim Langsung": supplier, shipped quantity, serials, the supplier's shipment reference, evidence; delivery in the same command when the proof arrived with it (L-40) | AX-08 · `dropship.confirm` | "Sudah Tercatat" for the same reference (BD-04) | PG-011 |

Branches and refusals: receiving and dispatch are never offered for the line (BR-INV-11). Corrections: CM-14, CM-15; CM-27. Differences: the actual cost only with `cost.view`. Phone · desktop: desktop; the phone shows the state.

#### J-FUL-04 — Service handover, *Serah Terima Jasa* `[both]`

Personas: Laras (`handover.record`). Entry: the journey's *Jasa* stage and its primary action "Catat Serah Terima Jasa" (PG-011) › PG-033. R1 placement: SCR-10 part *Pemenuhan* becomes the *Jasa* stage (journey model a)).

| Step | Page | Control | Command · capability | Answer | Next |
| --- | --- | --- | --- | --- | --- |
| 1 | PG-033 | per service line — date, scope performed, recipient, evidence | `handover.record` | REJECTED beyond the pinned demand | — |
| 2 | PG-033 | BAST (DOC-08) | AX-15 · `documents.issue` | IP-08 | PG-011 |

Corrections: a reversal line with a reason (`fulfillment.correct`); CM-27. Differences: no stock page is ever involved (FS-05). Phone · desktop: at the client's site on the phone.

#### J-FUL-05 — Sales return `[both]`

Personas: Laras (`sales_return.record`; companions by their capability). Entry: a delivery on the journey's *Rincian proyek* › *Pengiriman* or on *Pengiriman* (PG-030) › "Retur dari Klien" (PG-034). R1 placement: as IA_PAGE_INVENTORY §7.3 proposes — from the delivery it corrects (ADMIN_FLOW §11, CM-10–CM-13); its case is listed in *Kasus Koreksi* (PG-040).

| Step | Page | Control | Command · capability | Answer | Next |
| --- | --- | --- | --- | --- | --- |
| 1 | PG-034 | the case with reason and evidence | `sales_return.record` | IP-09 | — |
| 2 | PG-034 | the receipt of the goods — *Layak pakai* back to the lot, *Rusak* to *Tidak layak pakai* (SF-RESTORE) | AX-10 · `sales_return.record` | a serial that did not leave on that line refused at the scan (CM-36 first) | — |
| 3 | PG-034 | the demand — deliver again, revise, or cancel the rest; the financial companions as separate commands (IP-19): invoice revision or void, credit or refund (CM-12, CM-13) | by their capabilities | each answered | — |
| 4 | PG-034 | the damaged units' outcome; "Tutup Kasus" | `cases.close` | on a completed project the answer says it returns to *Aktif* (SF-REVAL) | PG-040 |

Corrections: CM-10–CM-13; CM-37. Differences: the financial steps need `finance.view` and their capabilities; otherwise the case stays listed for a colleague (QS-07). Phone · desktop: receiving the units on the phone; the case on the desktop.

### 2.5 Documents and administration — [ADMIN_FLOW §10.5](../ADMIN_FLOW/s10-b-journeys.md#105-documents-and-administration)

#### J-DOC-01 — Generated document lifecycle `[desk]`

Personas: Laras, Dimas; Bayu for the operational family (`documents.issue`; revision and void `documents.correct`). Entry: the journey's "Kesiapan dokumen" › an item *Siap disiapkan* (PG-011 › PG-036); *Dokumen* (PG-035). R1 placement: ADMIN_FLOW's SCR-35 from its business record gains document readiness as its entry (R1's connected work b); RS-8) and VC1's composer: carried and entered data, a preview marked "DRAF — belum terbit" (GD-07; DC-1–DC-12); the preview's producer is [DOCUMENT_PREVIEW_PRODUCER](DOCUMENT_PREVIEW_PRODUCER.md)'s (GAP-053).

| Step | Page | Control | Command · capability | Answer | Next |
| --- | --- | --- | --- | --- | --- |
| 1 | PG-036 | *Draf* — editable, unnumbered, printed with the draft marking (DC-1; DC-2) | — | — | — |
| 2 | PG-036 | "Terbitkan {jenis}" | AX-15 · `documents.issue` (the invoice layouts `invoice.issue`) | IP-08 (MSG-39); COMMITTED with the number, never changing; REJECTED MSG-17 without a series (C-10); CONFLICT for a double issue (BD-06) | — |
| 3 | PG-036 | the PDF: *PDF sedang dibuat* → *PDF siap* or *PDF gagal dibuat* (IP-12; MSG-05; MSG-08) | SF-RENDER, background | PENDING | — |
| 4 | PG-036 | "Revisi" — a new issued version linked to the old; "Batalkan Dokumen" — the version and its number retired | `documents.correct` | IP-09; a void never touches the business record (BR-CR-06) | — |

Branches and refusals: ADMIN_FLOW J-DOC-01 — a deactivated company blocks new business documents (BR-ACC-03). Corrections: CM-26; the invoice's are J-FIN-01's. Differences: payload and PDF by family (PJ-15). Phone · desktop: drafting and issue on the desktop; on the phone find, read and download, and Edit → Preview → Finalize full-screen (DC-11).

#### J-DOC-02 — External and uploaded documents `[both]`

Personas: all three, by family (`evidence.upload` with `evidence.view`). Entry: *Dokumen* › *Bukti* (PG-038); "Buat" › *Unggah Dokumen*; an upload requirement's "Unggah" in document readiness (R1's connected work b)); a record's *Bukti* part. R1 placement: the readiness panel's "Unggah" added as an entry (RS-8).

| Step | Page | Control | Command · capability | Answer | Next |
| --- | --- | --- | --- | --- | --- |
| 1 | PG-038, the upload sheet | IP-16 — choose, upload, register (NX-07), identity, warnings, viewing | NX-07 · `evidence.upload` | MSG-31 for a refused file; MSG-32 for pool evidence; IP-05 for a match the actor may view | — |

Corrections: a new version replaces; unlinking with a reason (NX-14). Differences: per evidence family (PJ-08). Phone · desktop: photograph and upload at the place of the event on the phone.

#### J-ADM-01 — Administrative checklist, *Dokumen Administrasi* `[desk]`

Personas: Laras, Dimas act (`requirements.manage`, `requirements.waive`); Bayu reads (`projects.view`); the Owner's controls Laras (OD-05, OD-06). Entry: the journey's "Kesiapan dokumen" (PG-011); *Dokumen* › *Dokumen Administrasi* (PG-039). R1 placement: the checklist joins the generated documents in one readiness view (D-IA-10 ADAPT, R1's delta); SCR-37's cross-project view stays in *Dokumen*.

| Step | Page | Control | Command · capability | Answer | Next |
| --- | --- | --- | --- | --- | --- |
| 1 | PG-011 | add a requirement — type, *Wajib* or *Opsional*, how it is satisfied, "Harus asli dari klien" | `requirements.manage` | — | — |
| 2 | PG-011 | link the issued document or upload that satisfies it | `requirements.manage` | the state derived: *Belum Ada*, *Disiapkan*, *Terpenuhi*, *Tidak Berlaku* (BR-ADM-01) | — |
| 3 | PG-011 | "Tandai Tidak Berlaku" with a reason, on an eligible item | `requirements.waive` | IP-09 | — |

Branches and refusals: ADMIN_FLOW J-ADM-01 — an Admin's form has no Owner-only control (OD-05, OD-06); a draft never satisfies a requirement and a reproduction never a client original (FS-12; IP-16). Corrections: reopened by corrections automatically (BR-ADM-03); a waiver in error CM-27. Phone · desktop: see what is missing and upload it on the phone.

### 2.6 Finance — [ADMIN_FLOW §10.6](../ADMIN_FLOW/s10-b-journeys.md#106-finance)

#### J-FIN-01 — Invoice issuance `[desk]`

Personas: Laras, Dimas (`invoice.issue` with `finance.view`). Entry: *Pekerjaan* › *Penyelesaian proyek*'s QS-15 row ("confirmed value not yet invoiced") › the journey; the journey's *Invoice* stage › "Buat invoice" (PG-011); document readiness; *Keuangan* › *Invoice* › "Buat Invoice" (PG-041). R1 placement: ADMIN_FLOW's SCR-10 part *Penagihan* → SCR-39 becomes the journey's *Invoice* stage → the composer PG-042 (R1's connected creation; VC1's composer) → PG-043; the draft is composed from the confirmed data, carried data read-only with its source (DC-5). **S2-J03** prototypes it.

| Step | Page | Control | Command · capability | Answer | Next |
| --- | --- | --- | --- | --- | --- |
| 1 | PG-042 | "Diambil dari proyek" — client and unit, billing address, PIC, basis, delivery, the confirmed lines — read-only; "Perbaiki di data proyek" leads to the project (DC-5) | — | — | — |
| 2 | PG-042 | "Diisi sekarang" — all confirmed lines or a value only (DP or termin); the layout *Invoice* or *Nota* (DOC-06 or DOC-04, one record, L-26); the transaction date line (IP-07) | — | the summary an estimate, server totals authoritative (DC-3; UXI-14) | — |
| 3 | PG-042 | the preview "DRAF — belum terbit", the number "Nomor diberikan saat terbit" (GD-07; DC-2) | — | — | — |
| 4 | PG-042 | "Simpan Draf" | the draft · `invoice.issue` | COMMITTED, *Draf* | PG-043 in the composer |
| 5 | PG-042 or PG-043 | "Terbitkan Invoice" | AX-16 · `invoice.issue` | MSG-39's one-step confirmation (IP-08); COMMITTED: *Terbit — Belum Ditagihkan* with its number (DC-8); REJECTED above the project cap with the remaining value (L-16); above delivered value allowed and flagged (QS-15); MSG-05 while the PDF is made | PG-043, read-only |

Branches and refusals: ADMIN_FLOW J-FIN-01 — the answer says no receivable is active and no cash recorded yet (BR-FIN-02). Corrections: CM-23 unbilled, CM-24 billed (`invoice.correct`), naming basis and replacement and disposing every reduction (ST-07); a standing write-off needs the Owner in the same action (OD-07). Differences: the pages do not exist without `finance.view`. Phone · desktop: `[desk]`; on the phone the invoice is read, and the issue step shows as WF-FIN-01's tag and DC-11 say — specified in [S2-J03](journeys/S2-J03.md).

#### J-FIN-02 — Billing and receivable monitoring, *Piutang* `[desk]`

Personas: Laras, Dimas (`billing.record`). Entry: PG-043 "Tagihkan"; *Pekerjaan*'s QS-09 row › *Piutang* (PG-044) *Belum Ditagihkan*; the journey's *Penagihan* stage. R1 placement: the *Penagihan* stage's next action is the oldest unbilled invoice (journey model c)).

| Step | Page | Control | Command · capability | Answer | Next |
| --- | --- | --- | --- | --- | --- |
| 1 | PG-043 | "Tagihkan" — billing date and due date | AX-17 · `billing.record` | IP-08; CONFLICT for a second billing act (BD-07); the unpaid remainder becomes active and ages from the due date | — |
| 2 | PG-036 | optionally "Surat Permintaan Pembayaran" (DOC-13) and the pre-payment Kuitansi (J-FIN-08) | AX-15, AX-31 · `documents.issue` | IP-08 | — |

Corrections: CM-25 (`billing.correct`) with entry-error evidence. Differences: no sum across companies for an Admin; the Owner's consolidated figure on PG-052. Phone · desktop: the phone looks up a client's receivables.

#### J-FIN-03 — Payment receipt and application, *Pembayaran* `[both]`

Personas: Laras, Dimas (`payment.record` — which covers same-command applications —; later applications `payment.apply`). Entry: "Buat" › *Pembayaran*; the journey's *Pembayaran* stage; *Piutang* (PG-044), the invoice (PG-043) or *Pembayaran* (PG-045) › "Catat Pembayaran" (PG-046). R1 placement: the *Pembayaran* stage's next action is a payment for the oldest due invoice (journey model c)). **S2-J06** prototypes the same-command application.

| Step | Page | Control | Command · capability | Answer | Next |
| --- | --- | --- | --- | --- | --- |
| 1 | PG-046 | company, client, date, amount, method, the company bank account, the statement line it matches, reference, proof; the claimed gross and deductions where the client paid net | — | — | — |
| 2 | PG-046 | the applications in the same form — amounts per issued invoice of that client and company, the rest staying *Kredit Pelanggan* | — | — | — |
| 3 | PG-046 | "Catat Pembayaran" — the payment and its applications one command | AX-18 (SF-APPLY) · `payment.record` | IP-08; REJECTED above the outstanding amount, above the payment, or above the statement line (C-19); IP-05 for BD-02's duplicate, continued only with `warnings.override`; a missing proof needs a reason and flags the payment (L-29) | PG-045 |
| 4 | PG-045 | later, "Terapkan" on money left unapplied; "Tautkan Kuitansi" for an open pre-payment Kuitansi (J-FIN-08) | AX-19 · `payment.apply`; AX-31 · `payment.record` | each answered (IP-19) | — |

Branches and refusals: ADMIN_FLOW J-FIN-03 — a second company's invoices are never offered (BR-FIN-07). Corrections: CM-19 (`finance.contra`), CM-20, CM-21 (`payment.reallocate`). Phone · desktop: one invoice's application on the phone; several, and the statement line, on the desktop.

#### J-FIN-04 — Deduction settlement `[desk]`

Personas: Laras (`settlement.record`, with `finance.view` and `evidence.view`). Entry: *Keuangan* › *Potongan* (PG-047); *Pekerjaan*'s QS-13 rows; the payment or the invoice. R1 placement: *Keuangan* › *Potongan*.

| Step | Page | Control | Command · capability | Answer | Next |
| --- | --- | --- | --- | --- | --- |
| 1 | PG-047 | "Catat Potongan" — fee or tax, type, amount, invoice, payment, evidence | AX-21 · `settlement.record` | IP-09; REJECTED beyond the evidence's proven amount (C-18) | — |

Branches and refusals: without evidence no settlement exists — the claim stays outstanding (QS-13); a short payment cannot be a fee. Corrections: CM-22. Phone · desktop: desktop.

#### J-FIN-05 — Dispute hold and Owner write-off `[desk]`

Personas: Laras — the dispute hold (`receivables.dispute`) and the write-off (`receivables.write_off`, OWNER_ONLY); Dimas reads the invoice and its *Sengketa* state, holding neither. Entry: the invoice (PG-043); *Beranda*'s QS-10 and QS-11 decision rows › PG-044 › PG-043. R1 placement: the Owner reaches it from *Beranda*'s decisions (R1 c)).

| Step | Page | Control | Command · capability | Answer | Next |
| --- | --- | --- | --- | --- | --- |
| 1 | PG-043 | "Tandai Sengketa", "Cabut Sengketa", each with a reason | `receivables.dispute` | IP-09; a disputed receivable stays active, keeps ageing and blocks normal completion | — |
| 2 | PG-043 | "Hapuskan Piutang" — amount, reason; the client's available credit applied first or a reason given (L-17) | AX-24 · `receivables.write_off` (OWN) | IP-08, IP-09 | — |

Branches and refusals: money received after a write-off appears for the Owner with "Terapkan dan Cabut Penghapusan" (QS-12, QS-14). Corrections: CM-27; supersession is the Owner's. Differences: the write-off controls exist only for the Owner (OD-11). Phone · desktop: desktop.

#### J-FIN-06 — Customer credit and refund `[desk]`

Personas: Laras, Dimas (`payment.apply`); the refund Laras (`refund.record`). Entry: *Keuangan* › *Lainnya* › *Kredit Pelanggan* (PG-048); *Pekerjaan*'s QS-12 rows. R1 placement: *Keuangan* › *Lainnya*.

| Step | Page | Control | Command · capability | Answer | Next |
| --- | --- | --- | --- | --- | --- |
| 1 | PG-048 | credit per client and company with its sources → "Terapkan ke Invoice" | AX-19 · `payment.apply` | REJECTED above the outstanding amount | — |
| 2 | PG-048 | "Kembalikan Dana" with transfer evidence | AX-23 · `refund.record` | IP-08, IP-09; REJECTED for unevidenced credit (L-29); an unbacked remainder blocks its case's closure (QS-07) | — |

Corrections: CM-38. Phone · desktop: desktop.

#### J-FIN-07 — Expenses, disbursements and other cash `[desk]`

Personas: Laras (`disbursement.record`, `expense.record`, `cashin.record`, `bank_statement.record`); Dimas reads the lists (`finance.view`; expenses `cost.view`). Entry: *Keuangan* › *Lainnya* › *Pengeluaran & Kas Lain* (PG-049) and *Mutasi Bank* (PG-050). R1 placement: *Keuangan* › *Lainnya*, *Mutasi Bank* dense.

| Step | Page | Control | Command · capability | Answer | Next |
| --- | --- | --- | --- | --- | --- |
| 1 | PG-049 | "Catat Pengeluaran Kas" against a purchase, expense or tax payment; "Catat Beban"; "Catat Kas Masuk Lain" | `disbursement.record`, `expense.record`, `cashin.record` | IP-08 | — |
| 2 | PG-050 | "Catat Mutasi Bank" with its statement evidence | `bank_statement.record` | — | — |

Branches and refusals: a purchase never shows as paid without a disbursement; a transfer between companies is two facts, each in its company (XL-5). Corrections: CM-38; a mistyped statement line voided and re-entered (`cash.correct`). Phone · desktop: desktop.

#### J-FIN-08 — Pre-payment Kuitansi `[desk]`

Personas: Laras, Dimas (`documents.issue`; the link `payment.record`). Entry: the invoice (PG-043) › "Kuitansi untuk Proses Pembayaran" › the Kuitansi (PG-036); *Pekerjaan*'s QS-19 rows. R1 placement: QS-19 is a blocker on the *Pembayaran* stage and a row in *Tagihan dan pembayaran* (R1's revised signals).

| Step | Page | Control | Command · capability | Answer | Next |
| --- | --- | --- | --- | --- | --- |
| 1 | PG-043 | "Terbitkan Kuitansi untuk Proses Pembayaran" — amount within the outstanding, reason | AX-31 · `documents.issue` | IP-08; the Kuitansi *Menunggu Pembayaran*, "untuk proses pembayaran"; it records no payment and the position block is unchanged | PG-036 |
| 2 | PG-036, PG-043 or PG-046 | "Tautkan Kuitansi" when the payment is recorded | AX-31 · `payment.record` | REJECTED when the amounts differ, with both figures and the path (CM-26); one link per payment and invoice | — |

Branches and refusals: ADMIN_FLOW J-FIN-08 — a later correction that lowers the linked total unlinks it (QS-19); a receipt-mode Kuitansi is refused while one is open. Corrections: CM-26. Phone · desktop: desktop.

### 2.7 Completion, cancellation and migration — [ADMIN_FLOW §10.7](../ADMIN_FLOW/s10-b-journeys.md#107-completion-cancellation-and-migration)

#### J-PRJ-03 — Project completion `[desk]`

Personas: all three read; Laras and Dimas complete (`projects.complete`); Force Complete Laras only (`projects.force_complete`). Entry: the journey's "Yang tersisa sebelum selesai" and *Penyelesaian* stage (PG-011) › PG-012; *Pekerjaan* › *Penyelesaian proyek*'s QS-15 rows; *Beranda*'s QS-15 row. R1 placement: ADMIN_FLOW's SCR-14 splits into the journey's unmet conditions — only those, each with its fact and action, the met ones folded into "N syarat lain terpenuhi" — and the completion page with all ten (R1 e); journey model d)).

| Step | Page | Control | Command · capability | Answer | Next |
| --- | --- | --- | --- | --- | --- |
| 1 | PG-011 | "Yang tersisa sebelum selesai" — each unmet predicate with its fact and the place that resolves it | — | — | the resolving page, or PG-012 |
| 2 | PG-012 | the ten conditions; "Selesaikan Proyek" when all are met | AX-25 · `projects.complete` | IP-08; REJECTED when a condition changed (ST-04) | PG-011, the outcome badge |
| 3 | PG-012 | Owner only, "Selesaikan Paksa": the unmet conditions and residual obligations as a snapshot → the mandatory reason → the statement that nothing is fabricated → confirm | AX-26 · `projects.force_complete` | IP-08, IP-09; CONFLICT with the new snapshot when it changed (ST-04) | PG-011 |

Branches and refusals: ADMIN_FLOW J-PRJ-03 — after completion only residual commands (L-11); a material correction returns the project to *Aktif* (SF-REVAL). Corrections: CM-32. Differences: Force Complete exists only for the Owner; an Admin's address for it answers as IP-21 (OD-12). Phone · desktop: reading on the phone; the Owner may force-complete there through the same confirmation.

#### J-PRJ-04 — Project cancellation `[desk]`

Personas: Laras (`projects.cancel`; "Batalkan Sisa" `projects.cancel_scope`). Entry: the journey's *Tindakan Lain* › "Batalkan Proyek"; "Batalkan Sisa" on a line in *Item dan pemenuhan*. R1 placement: SCR-10's control moves into *Tindakan Lain* (R1 e)).

| Step | Page | Control | Command · capability | Answer | Next |
| --- | --- | --- | --- | --- | --- |
| 1 | PG-011 | "Batalkan Proyek": what must be corrected first — returns, invoice voids, purchase closure — as links; then the reason | AX-27 · `projects.cancel` | IP-08, IP-09; reservations released in the same action | PG-011, *Dibatalkan* |
| 2 | PG-011 | "Batalkan Sisa" on a line | AX-14 · `projects.cancel_scope` | IP-09 | — |

Branches and refusals: facts that stay true stay shown (L-10). Corrections: CM-29, CM-30, CM-31. Phone · desktop: desktop.

#### J-MIG-01 — Opening facts `[desk]`

Personas: Laras (`import.prepare`, `import.commit`); neither Admin holds `import.prepare`. Entry: *Data Master* › *Impor Data Awal* (PG-058). R1 placement: as ADMIN_FLOW.

| Step | Page | Control | Command · capability | Answer | Next |
| --- | --- | --- | --- | --- | --- |
| 1 | PG-058 | prepare the batch, the dry run, the sign-off (ADMIN_FLOW §13) | `import.prepare` with every batch company in scope | MSG-18 for a collision outside scope (DP-18) | — |
| 2 | PG-058 | commit | AX-28 · `import.commit` | IP-08; the step-up first (IP-10; WFL-09) | — |

Corrections: CM-33. Phone · desktop: desktop.

## 3. Cross-cutting flows

### WFL-01 — Search and scan `[both]`

Personas: all three. Entry: the top-bar field "Cari atau pindai kode" and Ctrl+K ([KB-01](../DESIGN_SYSTEM.md#131-keyboard)); the phone's *Cari*; a warehouse task's scan field. Behaviour: [INFORMATION_ARCHITECTURE](../INFORMATION_ARCHITECTURE.md#information-architecture) §10, [IP-15](../ADMIN_FLOW/s06-09-session-forms-lists.md#ip-15--scanner), IP-17 (IA_PAGE_INVENTORY §6).

| Step | Page | Control | Command · capability | Answer | Next |
| --- | --- | --- | --- | --- | --- |
| 1 | any page; PG-009 | a code typed — SKU, barcode, serial, project or document number | exact lookup ([PF-19](../../06-api-performance/PERFORMANCE.md#5-search)) | one hit opens it; several within scope listed to choose; none MSG-25 | the record |
| 2 | any page; PG-009 | a name of at least three letters or digits | name search (PF-20–PF-22) | a type-ahead of at most 20 rows; PG-009 for the full result, cut at 200 with MSG-23; a shorter term MSG-24 | the record |
| 3 | any page with the field focused; the phone's *Cari* | a scan | exact lookup only (QB-06) | a product, serial or lot opens PG-019; unknown MSG-25 with "Daftarkan Barcode" for `products.edit`; ambiguous MSG-37 | PG-019 |
| 4 | PG-019, PG-022, PG-024, PG-026, PG-027, PG-028, PG-034 | the task's scan field, "Siap Memindai", with "Cari Produk" beside it | IP-15 — Enter never sends a command | the matched line and the running count, announced politely; errors assertively | the line |

Branches and refusals: the search limiter's MSG-20; company records only within scope, a result never hinting at one outside it ([CS-07](../../05-security/PERMISSIONS_MATRIX.md#6-company-scope-rules); DP-06); a term never in an address or title ([H7-10](../../05-security/SECURITY.md#p7--presentation-only)). Phone · desktop: full-screen search on the phone.

### WFL-02 — "Buat" `[both]`

Personas: Laras all five items; Dimas *Proyek*, *Pembelian*, *Pembayaran*, *Unggah Dokumen*; Bayu *Barang Masuk*, *Unggah Dokumen* (IA_PAGE_INVENTORY §6.3).

| Step | Page | Control | Command · capability | Answer | Next |
| --- | --- | --- | --- | --- | --- |
| 1 | the top bar | "Buat" › an item, present only with its capability (AZ-01) | — | — | its page: PG-013, PG-017, PG-021 › PG-022, PG-046, PG-038 |
| 2 | the item's page | a new root names its company — "Perusahaan", never prefilled from the filter for several companies —; a child follows its root's company, shown and not chosen | — | — | the flow of that item |

Phone: no "Buat" — "Buat Proyek" on PG-010 and "Mulai" on a work row (R1 a)). R1 placement: "Buat" is R1's addition to the top bar; ADMIN_FLOW's journeys keep their own entries, which stay.

### WFL-03 — The company view and the Owner's *Gabungan* `[both]`

Personas: Dimas (two companies) and Laras; Bayu, with one company, has no filter (INFORMATION_ARCHITECTURE §7).

| Step | Page | Control | Command · capability | Answer | Next |
| --- | --- | --- | --- | --- | --- |
| 1 | a list, PG-007 | "Tampilkan: Semua perusahaan" — a checklist of the companies in scope | a view, not a command | the branches narrowed (PF-41), intersected with scope (DP-02); kept in the address as a whitelisted key, so reload and Back keep it | the same page |
| 2 | PG-006 | the view switch *Gabungan* · ARJ · BTN · CKP | a view (`reports.consolidated` for *Gabungan*) | the signal groups and period figures filtered; consolidated figures labelled *Gabungan* (PF-25) | the same page |

Rules: the filter never feeds a form or authorizes ([WF-ACC-01](../../03-workflows/WORKFLOWS/s05-01-02-access-project.md#wf-acc-01--session-and-company-context-both); CS-03; AZ-01); rows of several companies carry their code and counts stay per company, never summed for an Admin (CS-08; PF-15); a company outside scope is never named (PJ-04).

### WFL-04 — The language switch `[both]`

Personas: all three. Entry: "ID | EN" at the top right of sign-in (PG-001); after sign-in, the account menu's — on the phone *Lainnya* › *Akun* — "Bahasa tampilan: Indonesia | English" (R1 k)).

| Step | Page | Control | Command · capability | Answer | Next |
| --- | --- | --- | --- | --- | --- |
| 1 | PG-001 or any page | the switch | a preference, not a business command | the interface strings change; business data, numbers, names and issued documents stay Indonesian (C-1; [CAP-14](../../01-product/V1_SCOPE.md#must-ship)) | the same page, its layout re-checked |

How the choice is kept before and after sign-in, and the prototype's mechanism within [SS-07](../evidence/P7_REBASELINE_GATE.md#stage-2--owner-decisions-dir-061), are [LOCALIZATION_ARCHITECTURE](LOCALIZATION_ARCHITECTURE.md)'s ([GAP-037](../../00-governance/GAP_REGISTER.md#triage); GR-14).

### WFL-05 — Session expiry and re-authentication `[both]`

Personas: all three. Behaviour: IP-11 (D-UX-03, DECIDE-IN-STAGE-2), with SECURITY governing where IP-11 predates the amendment: a re-login runs the same sequence as sign-in — the password, then the second factor (AU-04; H7-19; GAP-044). [AUTH_JOURNEYS](AUTH_JOURNEYS.md) proposes D-UX-03's redesign; Stage 3 aligns ADMIN_FLOW.

| Step | Page | Control | Command · capability | Answer | Next |
| --- | --- | --- | --- | --- | --- |
| 1 | any page | five minutes before the idle limit, the dialog MSG-33 with "Tetap Masuk" | one user-initiated request | the session extended (AU-07; WCAG 2.2.1) | the same page |
| 2 | any page | fifteen and five minutes before the absolute limit, MSG-34 in the notice strip | — | — | — |
| 3 | any page, a 401 or 419 answer | the page covered, history and caches cleared (H7-12); the re-authentication asks for the account's credentials and then its second factor | authentication, not a command ([CI-09](../../06-api-performance/CONCURRENCY_IDEMPOTENCY/s10-13-identity-failure-retry.md#10-command-identity-and-replay)) | MSG-19 and MSG-20 as at sign-in; the covered page's input kept in memory only | the same page: an unsent form sent as a new intent (IP-03); an unknown outcome MSG-07 with "Coba Lagi" on the same `command_id` |
| 4 | the dialog | "Keluar", or another person at the device | — | MSG-35, then a full document load | PG-001 |
| 5 | any page | the absolute limit passes | — | a full document load, history cleared | PG-001 |

Never browser storage for input: memory only (WS-07; WS-08; IP-11).

### WFL-06 — Logout `[both]`

| Step | Page | Control | Command · capability | Answer | Next |
| --- | --- | --- | --- | --- | --- |
| 1 | any page | the account menu › *Keluar* | logout (AU-06) | with unsent input, MSG-35 "Isian yang belum dikirim akan hilang. Tetap keluar?" first; without it, nothing is asked (GR-09 as [DIR-061](../../00-governance/DECISION_LOG.md#dir-061-dir-062-obs-023-and-tech-031--p7-ux-re-baseline-stage-2-decisions-s2-0-authorization-baseline-and-prototype-setup) SD-02 corrects it) | — |
| 2 | — | confirmed, or nothing to confirm | the session invalidated, the request-forgery token regenerated, history cleared (AU-06) | a full document load | PG-001 |

### WFL-07 — An address the account cannot open `[both]`

| Step | Page | Control | Command · capability | Answer | Next |
| --- | --- | --- | --- | --- | --- |
| 1 | PG-005 | an unknown, out-of-scope or Owner-only address | — | the not-found page, identical for each — MSG-09 "Halaman tidak ditemukan." with "Kembali ke Beranda" (AZ-11; IP-21; WS-10) | the landing page |
| 2 | PG-005 | a record in scope whose capability is missing | — | the forbidden state, MSG-10, nothing of the record | the landing page |
| 3 | the form concerned | a reference inside a command that does not resolve or resolves outside scope | — | an error at the field, MSG-12, input kept — never the not-found page (CS-04) | — |

R1 placement: MSG-09's way back is the account's landing page; for an Admin R1 names it *Pekerjaan*, so its label "Kembali ke Beranda" is aligned with R1's names in Stage 3, the mandated copy staying as written until then.

### WFL-08 — Continuing a list `[both]`

Personas: all three, on every list. Behaviour: IP-18, PT-08 and PF-42 canonical; the reference's toolbar and position numbers are presentation only (GR-08; GR-20 as corrected).

| Step | Page | Control | Command · capability | Answer | Next |
| --- | --- | --- | --- | --- | --- |
| 1 | a list | 25 rows, then "Muat Lagi" — no total, page number or "x dari y" (PF-15; PF-17) | a user-initiated request | the next rows appended; "Semua sudah ditampilkan." at the end | — |
| 2 | a filtered list | a branch that reached its read bound | — | the list says so and still offers "Muat Lagi" (PF-42) | — |
| 3 | a list | a cursor that fails its check — filters changed elsewhere, grants changed, a tampered address | — | MSG-26 and the offer to load from the start, never a silent first page (PF-14) | the list's start |

Sort and filter controls exist only for the keys IA_PAGE_INVENTORY and INFORMATION_ARCHITECTURE §8 register (PF-42).

### WFL-09 — Step-up confirmation `[desk]`

Personas: Laras for the Owner's commands of AU-15 — account and grant changes, a company's bank accounts or identity assets, the import commit, a credential-link issue —; every account for its own sensitive self-service (AU-15; [AUTH_JOURNEYS](AUTH_JOURNEYS.md)).

| Step | Page | Control | Command · capability | Answer | Next |
| --- | --- | --- | --- | --- | --- |
| 1 | PG-058, PG-059, PG-060, PG-065 | the command, IP-08's confirmation first where it has one | the command | a non-redirect answer the client intercepts (UXH-11) | — |
| 2 | the dialog "Konfirmasi Kata Sandi", above any open dialog or sheet (PT-16) | one password field, paste and show-password allowed (H7-04); MSG-27 | the confirmation, not a command (CI-09) | a wrong password said only as such; repeated failures MSG-20 | the same `command_id` and payload sent again |

## 4. Coverage

| Count | Value |
| --- | --- |
| [ADMIN_FLOW](../ADMIN_FLOW/README.md#admin-and-owner-flows--interaction-model) journeys | **39 of 39** — one flow each, J-ACC-01 … J-MIG-01, one per [WORKFLOWS](../../03-workflows/WORKFLOWS/README.md#critical-business-workflows) §5 workflow (ADMIN_FLOW §10), derived by script |
| Cross-cutting flows | **9** — WFL-01–WFL-09, the eight [DIR-064](../../00-governance/DECISION_LOG.md#dir-063-dir-064-obs-024-and-tech-032--p7-ux-re-baseline-s2-0-acceptance-s2-1-authorization-baseline-and-stage-2-documents) §12.4 names and the step-up |
| R1 placement differences | every flow whose **R1 placement** line names a change — the landing pages, the journey workspace and its stages, *Pekerjaan*'s rows, the composer, document readiness, the split worklists and tasks, "Buat", and *Menu lengkap* —; ADMIN_FLOW's commands, confirmations, answers and messages unchanged in every one; Stage 3 aligns ADMIN_FLOW |
| Flows prototyped | J-ACC-01 (S2-J01), J-FIN-01 (S2-J03), J-PUR-01 (S2-J04), J-INV-01 (S2-J05), J-FIN-03 (S2-J06); S2-J02's *Beranda* uses WFL-03 and the signal rows |

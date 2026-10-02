## 11. Corrections and reversals

Principle (WORKFLOWS §8): establish what really happened, then use the matching primitive; confirmations follow D-UX-10. On the screen this means: **a correction always starts from the record it corrects**, through an action on that record that names the situation in the user's words; the form states what will be reversed, contra'd or revised and what stays; a reason is mandatory (IP-09); and afterwards the original and the correction are both visible and linked — in the record's timeline and by a "Dikoreksi oleh" / "Mengoreksi" reference on each (BR-CR-02). Nothing is deleted, and no screen offers "hapus" for a committed fact. A correction on a completed project announces the revalidation (SF-REVAL); one on a cancelled project changes only what it corrects (L-11). Cross-company effects that the actor chooses follow IP-06.

| CM rows | Entry point — the record and its action | Capability | Notes for the flow |
| --- | --- | --- | --- |
| CM-01 | the scan or line not yet committed: "Hapus Baris" inside the form | none — no record exists | no reason asked; nothing is recorded |
| CM-02, CM-03 | the receipt on SCR-16 or SCR-20: "Balikkan Penerimaan" — whole, or the excess quantity | `stock.reverse` | only the lot's unconsumed quantity is offered; consumed quantity shows what must be restored first, or "Buka Kasus"; a cut of reservations follows IP-20 |
| CM-04 | the purchase line: a further "Terima Barang" | `stock.receive` | an ordinary receipt |
| CM-05, CM-06 | the dispatch on SCR-22 or SCR-10: "Batalkan Barang Keluar (Belum Berangkat)" or "Catat Barang Kembali Tanpa Terkirim" | `stock.reverse`; `delivery.close` for the closure of CM-06 | condition of the returning units chosen per unit; an optional new reservation of the restored units for the project is part of the same command, offered only with `stock.reserve` (CM-05; L-07) |
| CM-07 | the shipment on SCR-28: "Catat Hilang di Perjalanan" | `delivery.close`, `stock.adjust` | the form says that the cost stays on the project and is shown as a loss; a later "Catat Barang Ditemukan" is the exact reversal |
| CM-08 | the delivery record: "Koreksi Catatan Pengiriman" | `fulfillment.correct` | stock is untouched — the form says so |
| CM-09 | the lot or serial: "Ubah Kondisi" (J-INV-04), later "Periksa Ulang", "Retur ke Pemasok" or "Musnahkan" | `stock.condition`, `purchase_return.record`, `stock.adjust` | — |
| CM-10–CM-13 | the delivery: "Retur dari Klien" (J-FUL-05) | `sales_return.record`; `invoice.correct`, `refund.record` for the companions | the companion corrections are listed as open steps of the case until done |
| CM-14, CM-15 | the drop-ship confirmation: "Retur ke Gudang" or "Retur ke Pemasok" | `sales_return.record` | — |
| CM-16 | the purchase or the lot: "Retur ke Pemasok" (J-PUR-03) | `purchase_return.record` | — |
| CM-17, CM-35 | the purchase line or charge on SCR-16: "Koreksi Harga, Pajak atau Biaya" or "Koreksi Dasar Alokasi" | `purchase.correct_cost` | before any receipt the line is simply edited; afterwards the form lists every lot, consumption and project the cascade will re-attribute (AX-34) — a cascade command may take longer, and a lock wait is FAILED and safe to repeat (TX-02) |
| CM-18 | the purchase: "Batalkan Pembelian" or "Tutup Sisa Pembelian" | `purchase.cancel`, `purchase.close_remainder` | the form shows which applies: cancellation only with nothing received, confirmed or paid |
| CM-19 | the payment on SCR-41: "Batalkan Catatan Pembayaran (Kontra)" | `finance.contra` | the form lists the payment's applications and settlements and asks what happens to each (ST-07); the correct payment is recorded afterwards as a separate command; the case stays open until it is |
| CM-20 | the payment on SCR-41: "Koreksi Perusahaan Penerima Pembayaran" — money that entered the wrong company | `refund.record` and `payment.record`, or `disbursement.record` and `cashin.record` for a real transfer (XL-5) | two facts, one in each company, each its own command (IP-19); with one company outside scope the Owner records it; never an application across companies |
| CM-21 | the application on SCR-39 or SCR-41: "Pindahkan Penerapan" | `payment.reallocate` | an application consumed by a refund offers no move — the row says why (L-46) |
| CM-22 | the settlement: "Batalkan Potongan (Kontra)" | `finance.contra`, `settlement.record` | — |
| CM-23, CM-24 | the invoice on SCR-39: "Revisi Turun" or "Batalkan Invoice" | `invoice.correct`; `writeoff.supersede` where a write-off stands | basis — entry error, changed agreement or return — with its evidence; the replacement named in the same action; every reduction disposed (J-FIN-01) |
| CM-25 | the billing act on SCR-39: "Koreksi Penagihan" | `billing.correct` | entry-error evidence required; the superseded dates stay visible |
| CM-26 | the Kuitansi on SCR-35: "Revisi" or "Batalkan Dokumen" | `documents.correct` | a pre-payment Kuitansi whose linked money changed is shown as unlinked again (QS-19) |
| CM-27 | the decision on its record — approval, confirmation, closure, waiver, dispute, handover, drop-ship confirmation: "Koreksi Keputusan" | `decisions.supersede`, `fulfillment.correct`; Owner decisions only by the Owner (OD-15) | a superseding decision with a reason; dependent facts are listed and must be corrected first |
| CM-28, CM-34 | the adjustment, condition change, count application or loss on SCR-20: "Balikkan" | `stock.reverse` | equal and opposite, same lots and serials; a linked loss is reversed with it |
| CM-29–CM-31 | the project or its line: "Batalkan Sisa", "Batalkan Proyek" (J-PRJ-04) | `projects.cancel_scope`, `projects.cancel`; `purchase.cancel` for CM-30 | received goods stay as free stock, and paid money returns as a supplier refund — the dialog says so |
| CM-32 | any row above on a completed project | that row's | the answer states that the project is *Aktif* again |
| CM-33 | the opening fact on its record: "Koreksi Data Awal" | `stock.reverse`, `finance.contra` | never a re-import; the form says so |
| CM-36 | the dispatch: "Koreksi Nomor Seri atau Lot" | `stock.reverse`; `stock.allocate_intercompany` when the lot actually shipped is another company's | evidence required; quantities and shipment stay unchanged — stated on the form |
| CM-37 | the restoration or return: "Balikkan (Tercatat Keliru)" | `stock.reverse` | re-establishes the consumption; never a loss |
| CM-38 | the disbursement, expense or refund on SCR-45 or SCR-44: "Batalkan Catatan (Kontra)" | `finance.contra` | — |

A correction case (SCR-33) collects its linked corrections and shows its open residuals — an unbacked refund, an unresolved deduction, an unaccounted returned cost — as the reason it cannot be closed yet; "Tutup Kasus" (`cases.close`) is offered only when none remains, and the server decides (C-46).

## 12. Numbering and company onboarding

### 12.1 The numbering area

SCR-55, Owner only (`companies.manage`; OD-03). It shows, per company and per numbered type — the project number and each numbered document type — exactly what NM-03 and HO-39 name:

- the **numbering schemes** of the type with their effective dates and granularity (*Tahunan*, *Bulanan*);
- the **counters** that exist: period and next number;
- the **seed boundary** of the type — "Batas awal penomoran: {tanggal}" — or *Belum Dimulai*;
- **go-live**: "Go-live tercatat: {tanggal}" or *Go-live belum dicatat*;
- the **import batches** that cover the company, with their state, those that still hold seed rows marked "masih memuat baris nomor awal".

Counters are shown, never edited: no control raises, lowers or resets one (NM-04).

### 12.2 Start, seed and scheme commands

Each is one command of the numbering family (NX-01), confirmed by a dialog that cannot be skipped, whose statements are shown in full — never behind a link — and are acknowledged by one required tick, "Saya menyatakan hal di atas benar", before the confirming button becomes active (IP-08). The vouching statements are those of HO-39, in these words:

| Statement | Wording |
| --- | --- |
| (a) | "Setiap nomor awal lebih besar daripada semua nomor periode tersebut yang pernah diterbitkan di luar sistem ini, berapa pun tanggal dokumennya." |
| (b) | "Mulai hari ini, {jenis} tidak lagi dinomori di luar sistem ini." |
| (c) | "Tidak ada {jenis} bernomor di luar sistem ini yang bertanggal setelah hari ini." |
| (d) | "Setiap nomor di luar sistem ini termasuk dalam periode yang memuat tanggal dokumennya atau hari penerbitannya." |

| Command | Offered when | Form and confirmation |
| --- | --- | --- |
| **"Mulai Penomoran"** — the start of a type no import has seeded | go-live is recorded, the type has no seed boundary, and no batch that is not aborted holds valid seed rows of the type | one field per period into which today falls under each granularity that governs it — "Nomor pertama periode {periode}". The confirmation lists **every period the command seeds with its first number** and shows statements (a), (b), (c) and (d) |
| **"Tetapkan Nomor Awal"** — a seed for a period that is not live and has no counter | the type has its boundary; the period does not lie wholly before go-live; no batch that is not aborted holds a valid seed row for it | the period and its first number. The confirmation lists the period or periods and shows statement (a) |
| **A numbering scheme** — added or changed | always for the Owner | "Berlaku mulai" shows the **earliest date allowed**: once the type has its boundary, today — a scheme cannot take effect in the past, except "Perluas ke tanggal lebih awal yang belum memiliki format", which only extends the type's schemes backwards over dates no scheme covered. When the scheme's first period is not live and has no counter, the same form requires that period's first number and its confirmation shows statement (a) — except in such a backward extension. A scheme that takes effect today shows the notice "Format yang mulai berlaku hari ini membagi dokumen hari ini ke dalam dua seri nomor." |

| Refusal | Shown to the Owner |
| --- | --- |
| Start or seed before go-live is recorded | The commands are not offered. The area states: "Go-live belum dicatat. Penomoran dimulai setelah go-live dicatat saat peralihan sistem." Recording go-live belongs to the cutover (HO-32); this screen has no control for it |
| A start while a batch that is not aborted holds valid seed rows of the type | "Batch impor {batch} masih memuat baris nomor awal untuk {jenis}. Selesaikan atau batalkan batch itu lebih dulu." with a link to the batch |
| A seed for a period wholly before go-live | "Periode sebelum go-live hanya dapat diberi nomor awal melalui impor data awal." |
| A seed for a period a pending batch covers | as the start's refusal, naming the batch |
| A scheme effective in the past | "Format nomor tidak dapat berlaku mundur. Tanggal paling awal: {tanggal}." — and the backward-extension choice where it applies |
| A scheme that would govern the cutoff period of a batch holding no seed row for it | "Batch impor {batch} belum memuat nomor awal untuk periode {periode}. Lengkapi atau batalkan batch itu lebih dulu." |

Every start and seed confirmation repeats, expanded and above its statements, what 12.1 shows for the type — its schemes and counters, its seed boundary, go-live and the import batches that cover the company, those still holding seed rows marked (HO-39). These forms offer no choice of date: their date is shown as a fixed fact, the day of recording (NM-03; IP-07).

Nothing on these screens suggests that a number can be reused, renumbered or guaranteed without gaps (NM-10).

### 12.3 The C-10 refusal for an Admin

This section is D-UX-08. When an Admin's command needs a number of a period that has no series yet — a project created for a company whose PROJECT numbering is not started, a document issued or backdated into a period that is not live and has no counter — the answer is REJECTED (C-10) and the screen shows MSG-17: the period has no number series yet, only the Owner can start numbering or set its first number, and the action can be repeated afterwards. It names the document type, the period and the company of the Admin's own record and **nothing else** — no counter, boundary, batch or other company. The form keeps its input; the repeated submission is a new intent (CI-06). The Owner's own screens show the same refusal with the link to SCR-55.

No stored signal is added for these refusals. The Owner sees **what is not started** as a derived readiness line on the company's page — "Penomoran belum dimulai: {jenis}" — computed from schemes, boundaries and go-live; a refusal for an earlier, unseeded period reaches the Owner by the Admin's request. This residual is recorded in GAP-016's P7 note.

### 12.4 Company onboarding

A new company is set up by **separate commands**, each saved and answered on its own (IP-19); nothing is stored as "onboarding progress". SCR-54 shows a **readiness list** derived from the state that exists (WORKFLOWS §1):

| Readiness line | Derived from | Command behind it |
| --- | --- | --- |
| Data perusahaan | the legal fields of the company master | the company form |
| Aset identitas — logo, stempel, tanda tangan, direktur | the identity assets | their form, behind the step-up (IP-10) |
| Rekening bank | at least one active bank account | the bank-account form, behind the step-up |
| Pengaturan pajak | the company's tax profile | SCR-56 (`tax.configure`) |
| Format nomor | a scheme per numbered type | SCR-55 |
| Penomoran — per type, the project number included | the type's seed boundary | "Mulai Penomoran" (12.2) |
| Akses Admin | accounts granted on the company | SCR-57 |

Each line reads *Lengkap* or *Belum* with its link. The numbering lines say plainly: "Belum dimulai — {jenis} belum dapat dinomori untuk perusahaan ini." The list is an aid: the company can be activated before every line is complete, and the commands that need a missing piece refuse with their own rule.

## 13. Opening import

SCR-58 (WF-MIG-01; AX-28; NX-13). Preparation is an Admin's work (`import.prepare`, every company of the batch in scope — PJ-20); the commit is the Owner's (`import.commit`; OD-13).

| Step | Actor | Screen behaviour |
| --- | --- | --- |
| Prepare | ADM | Upload the source file (CSV or XLSX; FL-02, FL-06), choose the import kind, the cutoff date and the companies → "Siapkan Batch". The batch is *Disiapkan* |
| Dry run | BG (JB-05) | *Uji Coba Berjalan* (PENDING, polled as IP-12) → the report: rows *Valid*, *Tidak Valid*, *Duplikat*, *Dilewati*, each invalid row with its row number and reason — a row whose committed twin lies outside the preparer's scope is counted under *Tidak Valid* with MSG-18 as its reason, never under *Duplikat* — and the control totals per company — stock quantity and cost, receivables, credits — beside the signed totals to reconcile. The batch is *Uji Coba Lolos* when it passes. A failed validation job shows in the report with "Jalankan Uji Coba Lagi" |
| A collision outside the preparer's scope | — | A batch whose identity collides with a batch of another scope is refused with exactly MSG-18 — "Tidak dapat diimpor — perlu tinjauan Owner" — and a row that collides with a committed row outside scope shows the same sentence as its reason. Nothing more: no batch, company, date or count (DP-18), under the neutral face of [DESIGN_SYSTEM §12.2](../DESIGN_SYSTEM.md#122-outcome-presentation) — no "Sudah Tercatat" label and no "Buka Catatan". The Owner finds both in the review queue |
| Sign-off | ADM and OWN | "Setujui Hasil Uji Coba": each account records its own sign-off with the sign-off evidence; the batch is *Disetujui Bersama* when both exist (L-33) |
| Commit | OWN! | "Jalankan Impor" behind the irreversible confirmation (IP-08), then the step-up when it is sent (IP-10); the confirmation states the batch, its companies, its cutoff and that numbering seeds are written. A batch that lacks a seed row for a period its cutoff falls into is refused with the missing type and period (NM-03) |
| Progress | BG (JB-07) | *Sedang Diimpor* with the server's count of rows committed, polled as IP-12. A row that is refused, or stays FAILED, stops the batch: the screen shows the row, its reason and "Lanjutkan" for the Owner once the cause is removed. Rows already committed stay committed (PX-05) — the screen says so |
| Done | — | *Selesai Diimpor*. A rerun never duplicates a row (BD-11); a wrong opening fact is corrected on its record (CM-33), never by importing again |
| Abort | OWN | "Batalkan Batch" with a reason for a batch that has not committed a row; its mechanics belong to P10 (HO-33) |


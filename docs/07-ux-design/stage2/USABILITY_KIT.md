# Usability kit (S2-3)

Status: DRAFT | Updated: 2026-10-18 | Owner: Planning

**Draft — not normative.** This is S2-3, the usability kit of Stage 2 of the P7 UX re-baseline, written by run PC-1 under [DIR-075](../../00-governance/DECISION_LOG.md#dir-073dir-075-obs-029-and-tech-036--p7-ux-re-baseline-cp-1r-verdict-p7p11-completion-plan-run-pc-1-and-stage-2-completion) SP-58 for the sessions of Checkpoint C2 ([DIR-061](../../00-governance/DECISION_LOG.md#dir-061-dir-062-obs-023-and-tech-031--p7-ux-re-baseline-stage-2-decisions-s2-0-authorization-baseline-and-prototype-setup) SD-09, SD-10; DIR-053 K2). Its structure is in English; everything a participant or the moderator reads or says is in plain Indonesian. It decides nothing: the sessions (S2-4), their dates and the participants are the Owner's — the sessions run while PC-2 runs and their results, fixes and re-tests are PC-3's (DIR-074) —, and only the Owner's verdict closes C2.

**Sources.** The journey specifications [S2-J01](journeys/S2-J01.md)–[S2-J06](journeys/S2-J06.md) (§§1, 4, 5); [PROTOTYPE_PLAN](PROTOTYPE_PLAN.md) §§5, 8.1 and 8.2; [SIMULATION_REGISTER](SIMULATION_REGISTER.md); the gate's [plan](../evidence/P7_REBASELINE_GATE.md#plan) — Checkpoint C2's row and APPR-011's conditions — and its [safety rules SS-01–SS-08](../evidence/P7_REBASELINE_GATE.md#stage-2--owner-decisions-dir-061); the pilot under `site/pilot/` of the private prototype repository, as run PC-1 completes it; the dry run of §9 (SP-59).

*Note of 2026-10-18 (run PC-2, [DIR-078](../../00-governance/DECISION_LOG.md#dir-077-dir-078-obs-030-and-tech-037--p7-ux-re-baseline-cp-a-decisions-run-pc-2-with-p9s-drafting-baseline-and-drafts) §7 item 5):* the Owner approved the kit at checkpoint CP-A ([DIR-077](../../00-governance/DECISION_LOG.md#dir-077-dir-078-obs-030-and-tech-037--p7-ux-re-baseline-cp-a-decisions-run-pc-2-with-p9s-drafting-baseline-and-drafts) CA-11); §5's GR-16 sentence now states variant B, the prototype's default from its commit D (CA-04 item 1); §7.3's C2 record gains the device, browser and CSS width of each phone task (CA-02, CA-11); the sessions follow SD-09 unless a warehouse/field user is confirmed before they start (CA-12).

## 1. Purpose and C2's criteria

The sessions show whether the people who will use MultipleCorp can do its critical tasks on the prototype without help, in Indonesian, on the devices they use. They feed Checkpoint C2: the usability results and the Owner's verdict, with fixes and re-tests when needed.

APPR-011 — reserved, not granted — needs among its conditions: **the Owner's explicit verdict after C2 that the UI is user friendly (DIR-040 F6)**, and **every critical task completed without help by every participant in the final round, or an Owner-accepted disposition**. The kit serves those two conditions only; APPR-011's other conditions (non-loss, contracts, gates, findings, P0–P6 semantics) belong to Stages 3–5.

What the moderator says at the start of each session (plain Indonesian, §4) states the same purpose without these references.

## 2. Participants

| Role | Persona on the prototype | Device | Tasks |
| --- | --- | --- | --- |
| The Owner | Laras, Owner | desktop, and the phone for UT-06 | UT-01–UT-07 (S2-J01, S2-J02) |
| The Admin Operasional validator | Dimas, Admin Operasional (ARJ, BTN) | desktop, and the phone for UT-12 | UT-08–UT-17 (S2-J01, S2-J03, S2-J04, S2-J06) |
| A warehouse/field user, if reasonably available | Bayu, Admin Operasional with ARJ's warehouse grants | the phone | UT-18–UT-24 (S2-J05, S2-J01's account menu) |

- **SD-09's fallback.** If no warehouse/field user is reasonably available, DIR-053 K2's fallback applies: the Owner and the Admin Operasional validator only, and **the validator also takes Bayu's tasks UT-18–UT-24, on the phone**, in a session of their own (§8). OC-12 stays pending, not blocking.
- **Personas, not people.** Each participant acts as the persona of their role: the prototype's data are synthetic (SS-01), the sign-in values on the task cards are the prototype's synthetic ones (SS-02), and no participant uses a real account, password or e-mail address.
- **By role, never by name.** Every result, note, quote and time is recorded against the role (for example "Validator Admin Operasional, sesi 1"), never a name. No personal data is collected: no name, contact, photograph, screen or voice recording, device identifier or employer detail. A short quote is written down as said, without anything that identifies its speaker.
- **The Owner's own session** uses the self-administered variant of §4.3 with the same task cards.

## 3. The critical tasks

Each task is one card. Its **goal** is in plain Indonesian and never names the control to use; where the task needs them, the card also carries the synthetic values the persona would know (SS-01, SS-02). The moderator reads the goal aloud and hands over the card. Every task follows its journey's trace matrix (§5 of each specification): its **path** names the screens PROTOTYPE_PLAN §8.1 numbers, and its **start** is a page of the pilot's start page (`/pilot/`), which lists the six journeys with their personas and every page with its states. A step of a journey stays on the critical path unless its journey marks it optional; the one step so marked — J-PUR-01's last step, "Terbitkan PO" (S2-J04 §1) — is offered after UT-13 only if time allows and never counts.

- **Completed without help** — the success criterion is met and the moderator gave none of the help below.
- **Completed with help** — the success criterion is met after any of that help.
- **Not completed** — the participant stops, or the limit on the card passes, or the result is not the success criterion.
- **Help** is: pointing at, naming or describing a control or a place on the screen; saying which step comes next; reading or explaining what the screen says; confirming a choice before the participant makes it; taking the device; restating the goal in the interface's words. **Not help:** reading the goal again as written; the card's values; the neutral prompts of §4.2; reloading a page that failed, which is recorded as an incident.
- **Limits** are the moderator's, not targets: set from each task's steps, revisable at PC-3.

### 3.1 The Owner, as Laras (S2-J01, S2-J02)

**UT-01 — the language of the sign-in page** (S2-J01 step 2; desktop 1440×900; limit 2 minutes)
- *Start:* the sign-in page, signed out — the start page's S2-J01 row, ID.
- *Goal:* «Ubah bahasa halaman ini ke bahasa Inggris, lalu kembalikan ke bahasa Indonesia.»
- *Success:* the sign-in page in English, then in Indonesian again.
- *Path:* PS-01 (id) → PS-01 (en) → PS-01 (id).
- *Help, besides §3's:* saying where the language is changed.

**UT-02 — signing in** (S2-J01 steps 1 and 3–6; desktop; limit 4 minutes)
- *Start:* the sign-in page, in Indonesian, signed out.
- *Card:* email `laras@contoh.example`; kata sandi `Kopi#Pagi2026`; kode dari aplikasi autentikator `246802`.
- *Goal:* «Anda Laras, pemilik usaha. Masuklah ke MultipleCorp dengan email dan kata sandi di kartu. Bila diminta kode, pakai kode dari aplikasi autentikator di kartu.»
- *Success:* *Beranda* is shown.
- *Path:* PS-01 → PS-03 → PS-08; PS-02 or PS-04 only when a value is mistyped (one generic answer, AU-04).
- *Help, besides §3's:* saying which field takes the code, or that a value was mistyped.

**UT-03 — one company** (S2-J02 steps 1–2; desktop; limit 3 minutes)
- *Start:* *Beranda* after UT-02.
- *Goal:* «Beranda menampilkan semua perusahaan Anda sekaligus. Tampilkan angka-angka perusahaan BTN saja, lalu kembali ke semua perusahaan.»
- *Success:* *Beranda* for BTN alone, then for all companies again.
- *Path:* PS-08 → PS-12 → PS-08.
- *Help, besides §3's:* saying where a single company is chosen.

**UT-04 — overdue receivables** (S2-J02 step 4; desktop; limit 3 minutes)
- *Start:* *Beranda*, all companies.
- *Goal:* «Beberapa tagihan klien sudah lewat jatuh tempo. Dari Beranda, temukan daftarnya.»
- *Success:* *Piutang* with BTN's overdue invoices.
- *Path:* PS-08 → PS-14.
- *Help, besides §3's:* saying that a decision leads to its list.

**UT-05 — the project behind an invoice** (S2-J02 steps 5–6; desktop; limit 5 minutes)
- *Start:* *Piutang* after UT-04.
- *Goal:* «Cari tahu proyek mana yang ditagih invoice BTN/INV/2026/0094: di tahap apa proyek itu sekarang, apa yang menahannya, dan apakah syarat-syarat lainnya sudah terpenuhi.»
- *Success:* the project's journey is open; the participant says its stage is *Dokumen*, that the client's signed BAST is still to be uploaded and the invoice is overdue, and opens the remaining conditions (the eight others met) and the stages not needed.
- *Path:* PS-14 → PS-15, its two disclosures opened in place.
- *Help, besides §3's:* saying which link opens the project, or opening a disclosure for the participant.

**UT-06 — every decision, on the phone** (S2-J02 step 3; the phone at 390×844, §5; limit 3 minutes)
- *Start:* *Beranda* on the phone — the start page's S2-J02 row, ID, opened on the phone.
- *Goal:* «Di ponsel, lihat semua keputusan yang sedang menunggu Anda.»
- *Success:* the list of the seven decisions.
- *Path:* PS-08 (phone) → PS-13.
- *Help, besides §3's:* saying where every decision is listed.

**UT-07 — signing out** (S2-J01 step 12; desktop; limit 2 minutes)
- *Start:* *Beranda*, nothing typed.
- *Goal:* «Pekerjaan hari ini selesai. Keluarlah dari MultipleCorp.»
- *Success:* the sign-in page, with no question asked.
- *Path:* PS-08 → PS-10 → PS-01.
- *Help, besides §3's:* saying where the account menu is.

### 3.2 The Admin Operasional validator, as Dimas (S2-J01, S2-J03, S2-J04, S2-J06)

**UT-08 — signing in with the recovery code** (S2-J01 steps 9–10; desktop 1440×900; limit 4 minutes)
- *Start:* the sign-in page, signed out — the start page's S2-J01 row, ID.
- *Card:* email `dimas@contoh.example`; kata sandi `Teh#Sore2026`; kode pemulihan `PULIH-7Q4M-2KXP`.
- *Goal:* «Anda Dimas, Admin Operasional. Ponsel dengan aplikasi autentikator Anda tertinggal di rumah. Masuklah dengan email, kata sandi dan kode pemulihan di kartu.»
- *Success:* the page that asks for the authenticator to be set up again; the participant says that this is the next thing to do.
- *Path:* PS-01 → PS-03 → PS-05 → PS-06.
- *Help, besides §3's:* saying where a recovery code is entered.

**UT-09 — signing in with Google** (S2-J01 steps 7–8; desktop; limit 3 minutes)
- *Start:* the sign-in page again, opened by the moderator.
- *Card:* kode dari aplikasi autentikator `135791`.
- *Goal:* «Masuklah ke MultipleCorp dengan akun Google Anda. Bila diminta kode, pakai kode dari aplikasi autentikator di kartu.»
- *Success:* *Pekerjaan*.
- *Path:* PS-01 → PS-07 → PS-09.
- *Help, besides §3's:* saying which button signs in with Google.

**UT-10 — the display language** (S2-J01 step 11; desktop; limit 3 minutes)
- *Start:* *Pekerjaan* after UT-09.
- *Goal:* «Ubah bahasa tampilan MultipleCorp ke bahasa Inggris, lalu kembalikan ke bahasa Indonesia.»
- *Success:* *Pekerjaan* in English, then in Indonesian again.
- *Path:* PS-09 → PS-10 → PS-09 (en) → PS-10 → PS-09 (id).
- *Help, besides §3's:* saying where the account menu or the display language is.

**UT-11 — invoicing a project** (S2-J03 steps 1–10; desktop 1440×900; limit 10 minutes)
- *Start:* *Pekerjaan*.
- *Goal:* «Proyek ARJ/PRJ/2026/0139 sudah boleh ditagih. Dari daftar pekerjaan Anda, buka proyeknya dan periksa catatan syaratnya, siapkan invoice resminya untuk klien, keluarkan invoice itu, lalu bukalah.»
- *Success:* the issued invoice ARJ/INV/2026/0141 is open, the project's notes having been opened on the way.
- *Path:* PS-09 → PS-16 → PS-17 → PS-18 → PS-19 → PS-20 → PS-21 → PS-22 → PS-23 → PS-24.
- *Help, besides §3's:* saying where the project's notes are, or that the draft is saved before it is issued.

**UT-12 — the project and the invoice on the phone** (S2-J03 step 11; the phone at 390×844, §5; limit 6 minutes)
- *Start:* *Pekerjaan* on the phone — the start page's S2-J03 row, ID, opened on the phone; for the second question the moderator opens the invoice from the start page's PG-043 row (PS-24).
- *Goal:* «Di ponsel, buka proyek ARJ/PRJ/2026/0139 dari daftar pekerjaan Anda. Apa langkah berikutnya, dan di mana langkah itu dikerjakan?» — then — «Ini invoice ARJ/INV/2026/0141. Berapa nilainya, dan apakah sudah ditagihkan?»
- *Success:* the participant says that the invoice is the next step and is made on a computer (the phone names it and offers no control for it), then reads Rp48.729.000, not yet billed.
- *Path:* PS-09 → PS-16 → PS-17 → PS-18 on the phone; PS-24 on the phone.
- *Help, besides §3's:* saying that the invoice is made on a computer.

**UT-13 — a purchase for a project's shortage** (S2-J04 steps 1–6; desktop; limit 12 minutes)
- *Start:* *Pekerjaan*.
- *Card:* pemasok PT Contoh Komputindo; Laptop 14 inci 20 unit @ Rp8.500.000; Mouse nirkabel 20 unit @ Rp185.000; Tas laptop 20 unit @ Rp275.000; Printer laser 2 unit @ Rp11.500.000; ongkos kirim Rp1.250.000 menurut tagihan Contoh Ekspedisi INV-EXP-0412 (berkasnya disimulasikan).
- *Goal:* «Proyek ARJ/PRJ/2026/0147 kekurangan barang. Dari daftar pekerjaan Anda, buat pembelian ke pemasok di kartu dengan barang, jumlah, harga dan ongkos kirim di kartu, lalu pastikan pembelian itu tersimpan.»
- *Success:* the saved purchase, its answer shown and its total Rp225.692.000.
- *Path:* PS-09 → PS-25 → PS-26 → PS-27 → PS-28. Optional, never counted: the purchase order — PS-29 → PS-30.
- *Help, besides §3's:* saying where a line or the charge is added, or that the bill is attached.

**UT-14 — signing out over unsent input** (S2-J01 step 13; desktop; limit 3 minutes)
- *Start:* *Buat Pembelian* opened from *Pekerjaan* for ARJ/PRJ/2026/0147, the supplier of UT-13's card chosen, nothing saved.
- *Goal:* «Anda sedang mengisi pembelian baru, tetapi harus pergi sekarang juga. Keluarlah dari MultipleCorp.»
- *Success:* the question about the unsent input is answered by leaving, and the sign-in page is shown. Choosing first to stay keeps the input and is not a failure.
- *Path:* PS-25 → PS-11 → PS-01.
- *Help, besides §3's:* saying how to sign out, or what the question means.

**UT-15 — recording a client's payment** (S2-J06 steps 1–6; desktop; limit 8 minutes)
- *Start:* *Pembayaran* — the start page's S2-J06 row, ID.
- *Card:* perusahaan ARJ; klien Dinas Pendidikan Kota Contoh; Rp300.000 diterima 05 Okt 2026 lewat transfer bank ke rekening Bank Contoh ··· 0042, baris mutasi 05 Okt 2026 · Rp300.000; referensi TRF-051026-0042; bukti transfer `bukti-TRF-051026-0042.pdf` (disimulasikan); untuk invoice ARJ/INV/2026/0128.
- *Goal:* «Dinas Pendidikan Kota Contoh membayar Rp300.000 untuk invoice ARJ/INV/2026/0128. Masukkan pembayaran itu ke MultipleCorp dengan data di kartu.»
- *Success:* *Pembayaran* with the answer that Rp300.000 is recorded and applied to ARJ/INV/2026/0128, Rp700.000 still outstanding, and the payment first.
- *Path:* PS-45 → PS-46 → PS-47 → PS-48 → PS-49.
- *Help, besides §3's:* saying where the company, client, account or statement line is chosen, or where the amount is applied.

**UT-16 — applications the rules refuse** (S2-J06 steps 7–8; desktop; limit 6 minutes)
- *Start:* the payment form — the start page's PG-046 row, PS-46; the card of UT-15.
- *Goal:* «Klien membayar Rp300.000 dan meminta semuanya dipakai untuk invoice ARJ/INV/2026/0133. Coba masukkan. Bila MultipleCorp menolak, jelaskan mengapa.» — then — «Klien lalu meminta Rp300.000 untuk ARJ/INV/2026/0128 dan Rp250.000 untuk ARJ/INV/2026/0133. Coba masukkan. Bila ditolak, jelaskan mengapa.»
- *Success:* after each refusal the participant explains it — ARJ/INV/2026/0133 has only Rp250.000 outstanding; together the applications exceed the Rp300.000 paid — and says what would be accepted.
- *Path:* PS-46 → PS-47 → PS-50; then PS-51.
- *Help, besides §3's:* explaining a refusal.

**UT-17 — no proof yet** (S2-J06 steps 9–10; desktop; limit 5 minutes)
- *Start:* the payment form with UT-15's payment entered and applied — the start page's PG-046 row, PS-47.
- *Goal:* «Berkas bukti transfer yang terlampir ternyata salah, dan klien belum mengirim yang benar, padahal uangnya sudah masuk. Masukkan pembayaran ini sekarang tanpa bukti, dengan alasannya.»
- *Success:* *Pembayaran* with the payment flagged "Tanpa Bukti" and its answer; the reason is the participant's own.
- *Path:* PS-47 → PS-52 → PS-48 → PS-53.
- *Help, besides §3's:* saying where the proof is removed or where the reason is entered.

### 3.3 The warehouse/field user, as Bayu, on the phone (S2-J05, S2-J01's account menu)

The scanner is simulated (SIM-12): a code on the card is typed or pasted into the field where a scan would land; the moderator says so at the start (§4.1). Every task runs on the phone at 390×844 (§5).

**UT-18 — receiving the first line** (S2-J05 steps 1–6; limit 8 minutes)
- *Start:* *Pekerjaan* — the start page's S2-J05 row, ID, opened on the phone.
- *Card:* pesanan ARJ/PB/2026/0216 dari PT Contoh Komputindo; Laptop 14 inci, kode `LPT-14-I5`: 18 unit layak pakai ke rak A-02-03, 1 unit rusak tetapi diterima, 1 unit ditolak; surat jalan SJ-KOMP-7781 dengan fotonya (disimulasikan); sisihkan 15 unit untuk proyeknya.
- *Goal:* «Barang untuk pesanan ARJ/PB/2026/0216 baru datang. Mulailah menerima barang, lalu masukkan hasil penerimaan laptopnya sesuai kartu: yang layak pakai dan raknya, yang rusak tetapi diterima, yang ditolak, nomor dan foto surat jalan, dan sisihkan 15 unit untuk proyeknya.»
- *Success:* the laptop line recorded with its answer, 15 units reserved, one line of four counted.
- *Path:* PS-31 → PS-32 → PS-33 → PS-34 → PS-35 → PS-36.
- *Help, besides §3's:* saying where the code is typed, or where the reservation is entered.

**UT-19 — the other lines** (S2-J05 steps 7–10; limit 10 minutes)
- *Start:* the receipt after UT-18.
- *Card:* Mouse nirkabel, kode `MOU-NRK-01`: 20 unit layak pakai ke rak A-03-01; Tas laptop — labelnya rusak, tidak bisa dipindai: 20 unit ke rak A-03-02; Printer laser, kode `PRN-LSR-01`: 2 unit ke rak B-01-01.
- *Goal:* «Terima juga ketiga barang lainnya sesuai kartu. Label tas laptop rusak dan tidak bisa dipindai, jadi temukan barang itu dengan cara lain. Setelah semuanya selesai, baca ringkasannya.»
- *Success:* the summary of the four lines, each with its answer.
- *Path:* PS-36 → PS-37 (line 2) → PS-38 (line 3 found by its name) → line 4, each recorded through PS-35's confirmation → PS-39.
- *Help, besides §3's:* saying how a product is found by its name.

**UT-20 — the receiving note** (S2-J05 steps 11–12; limit 4 minutes)
- *Start:* the summary after UT-19.
- *Goal:* «Bagian administrasi meminta nota penerimaan untuk laptop yang tadi diterima. Keluarkan notanya.»
- *Success:* *Nota Terima Barang* ARJ/NTB/2026/0057, marked "Simulasi".
- *Path:* PS-39 → PS-40 → PS-41.
- *Help, besides §3's:* saying where the note is issued.

**UT-21 — a second order** (S2-J05 step 13; limit 4 minutes)
- *Card:* pesanan ARJ/PB/2026/0213: Proyektor, 2 unit layak pakai ke rak B-02-01.
- *Goal:* «Kembali ke daftar pekerjaan, lalu terima juga barang untuk pesanan ARJ/PB/2026/0213 sesuai kartu.»
- *Success:* the projector line recorded with its answer.
- *Path:* PS-41 → PS-31 → PS-42 → PS-35's confirmation → recorded.
- *Help, besides §3's:* saying which row leads to the second order.

**UT-22 — receiving without reserving** (S2-J05 step 16; limit 5 minutes)
- *Start:* *Pekerjaan* opened again from the start page — the prototype keeps nothing, so the order is unreceived again.
- *Card:* the laptop line of UT-18, without the reservation.
- *Goal:* «Terima lagi laptop pesanan ARJ/PB/2026/0216 sesuai kartu, tetapi kali ini tanpa menyisihkan apa pun untuk proyek. Setelah selesai, kembali ke daftar pekerjaan dan lihat apa yang berubah.»
- *Success:* *Pekerjaan* shows the row of the 15 received units waiting for a reservation.
- *Path:* PS-31 → PS-32 → PS-33 → PS-35 → PS-36 → PS-44.
- *Help, besides §3's:* saying how to return to the work list.

**UT-23 — an account without the reservation grant** (S2-J05 steps 14–15, S2-R06's recorded scenario, SIM-05; limit 5 minutes)
- *Start:* the start page's "S2-J05 · S2-R06" row, ID, opened on the phone: the receipt opens on the laptop line.
- *Card:* the laptop line of UT-18, without the reservation.
- *Goal:* «Akun yang Anda pakai sekarang tidak berhak menyisihkan barang untuk proyek. Masukkan hasil penerimaan laptop sesuai kartu, lalu lihat daftar pekerjaan Anda.»
- *Success:* the line recorded with no reservation offered and its answer read, then *Pekerjaan* with the row of the received units waiting for a reservation.
- *Path:* PS-43 → PS-35's confirmation → the answer on PS-43 → PS-44.
- *Help, besides §3's:* explaining why no reservation is offered.

**UT-24 — the display language on the phone** (S2-J01's account menu; limit 3 minutes)
- *Start:* *Pekerjaan* on the phone.
- *Goal:* «Di ponsel, ubah bahasa tampilan ke bahasa Inggris, lalu kembalikan ke bahasa Indonesia.»
- *Success:* *Pekerjaan* in English, then in Indonesian again.
- *Path:* PS-31 → PS-10 → PS-31 (en) → PS-10 → PS-31 (id).
- *Help, besides §3's:* saying where the account or the display language is on the phone.

## 4. The moderator's script

### 4.1 The opening (read aloud)

«Terima kasih sudah meluangkan waktu. Hari ini kita mencoba MultipleCorp dalam bentuk prototipe desain — bukan aplikasinya. Semua data di dalamnya hanya contoh: perusahaan, klien, angka, dan akun semuanya rekaan, dan tidak ada yang disimpan atau dikirim ke mana pun.

Di bagian atas layar ada tanda "PROTOTIPE · data contoh". Kalau diketuk, muncul penjelasan singkat tentang prototipe ini. Beberapa hasil yang di aplikasi sebenarnya dibuat oleh sistem, misalnya nomor dokumen, di sini ditandai "Simulasi". Masuk dengan Google, pemindai barang, dan berkas yang dilampirkan juga hanya tiruan: kode barang cukup diketik, dan berkas sudah disiapkan.

Yang kami uji adalah desainnya, bukan Anda. Tidak ada jawaban yang salah. Kalau ada yang membingungkan, justru itu yang ingin kami ketahui.

Saya akan memberi Anda kartu tugas satu per satu. Bacalah, lalu kerjakan seperti Anda bekerja sehari-hari. Sambil mengerjakan, tolong ucapkan apa yang Anda pikirkan: apa yang Anda cari, apa yang Anda harapkan, dan apa yang membuat Anda ragu.

Selama tugas berlangsung saya tidak akan membantu atau menjawab pertanyaan tentang cara memakainya. Itu bukan karena saya tidak mau, tetapi karena kami ingin melihat apakah desainnya cukup jelas tanpa bantuan. Kalau Anda ingin berhenti dari suatu tugas, katakan saja; kita lanjut ke tugas berikutnya. Di akhir ada dua pertanyaan singkat.

Kami tidak merekam suara ataupun layar. Catatan kami hanya menyebut peran Anda, tidak menyebut nama Anda.»

### 4.2 During a task

What the moderator may say:
- «Silakan lanjutkan seperti biasa.»
- «Apa yang sedang Anda pikirkan?»
- «Apa yang Anda harapkan terjadi?»
- «Apa yang akan Anda lakukan sekarang?»
- When asked how: «Menurut Anda bagaimana?» or «Lakukan seperti yang menurut Anda benar.»
- When the limit passes or the participant wants to stop: «Terima kasih. Kita lanjut ke tugas berikutnya.»

What the moderator never does during a task: name or point at a control; say which step comes next; say that something is right or wrong; explain a message on the screen; take the device. If help is given all the same, it is written down exactly as given, and the task counts as completed with help (§3).

After the last task, the two closing questions of §6.2, then: «Terima kasih banyak. Itu saja untuk hari ini.»

### 4.3 The self-administered variant (the Owner's own session)

The same cards, taken in order, with no moderator:
- each card shows only its goal and its data — not its path or success criterion, which stay on the observation sheet;
- start the phone's stopwatch, do the task, stop the stopwatch; then read the task's success criterion on the sheet and mark the result honestly — *completed with help* when the kit, the specifications or anyone was consulted during the task;
- note errors, doubts and short remarks as they occur, and answer the two closing questions at the end.

## 5. The setup

- **Devices and viewports.** The desktop tasks on a computer at 1440×900 or 1366×768, the browser maximised at 100 % zoom, one window, no other tab. The phone tasks — UT-06, UT-12 and UT-18–UT-24 — on a real phone of about 390×844 under SS-06 and [PROTOTYPE_PLAN §5](PROTOTYPE_PLAN.md#5-the-phone-test-sp-6-ss-06) (option (a)): only in a session the Owner moderates, on a network whose Windows profile is Private, never public Wi-Fi, any firewall allowance for the Private profile only, no port forwarding, tunnel or public hosting.
- **Starting the server** — from the prototype repository's folder:
  - desktop only: `node tools/serve.mjs`, then open `http://127.0.0.1:8780/pilot/`;
  - with the phone: `node tools/serve.mjs --host <this computer's private address>`, then open `http://<that address>:8780/pilot/` on the phone and on the computer.
- **Stopping it.** Ctrl+C in its window right after the session; the phone's tab closed.
- **Between participants.** The prototype keeps nothing (SS-07): close the browser window — on the phone, the tab — and open the start page again. The language starts in Indonesian; GR-16's variant is B — the prototype's default from its commit D, the Owner's choice at CP-A (DIR-077 CA-04 item 1).
- **Synthetic data only.** No participant's own account, data or file on the devices; the cards' values are the prototype's synthetic ones, and the files the tasks attach are simulated by the prototype (SIM-16).
- **Before each session** — open once every start the session's tasks use (§3), close every other tab, and lay the cards out in the order of §8.

## 6. The observation sheet

### 6.1 Per task

Each session's sheet starts with its header — the role (never a name), the session's number, the date, the device and browser, the language and GR-16's variant — and has one row per task:

| Tugas | Hasil | Waktu | Kesalahan | Bantuan yang diberikan | Kutipan singkat | Masalah dan tingkat keparahan |
| --- | --- | --- | --- | --- | --- | --- |
| UT-nn | tanpa bantuan · dengan bantuan · tidak selesai | mm:ss | jumlah, dan uraian singkat tiap kesalahan | persis seperti diucapkan atau dilakukan | kata-kata peserta, tanpa identitas | uraian; 1–4 |

- **Waktu** runs from the goal read aloud to the success criterion met, the participant stopping, or the limit.
- **Kesalahan** is any action off the task's path that the participant had to undo or recover from — a wrong page, a wrong value, a control tried and abandoned.
- **Tingkat keparahan** per issue: **1 kosmetik** — noticed, nothing lost; **2 kecil** — slowed the participant, who recovered alone; **3 besar** — needed help or several errors; **4 kritis** — the task not completed, or a wrong result believed to be right.

### 6.2 The two closing questions (every session)

1. «Bagian mana yang paling mudah, dan bagian mana yang paling membingungkan?»
2. «Kalau aplikasi yang sebenarnya bekerja seperti ini, seberapa yakin Anda bisa memakainya sendiri untuk pekerjaan Anda? Mengapa?»

## 7. The results template for PC-3

### 7.1 Findings

| ID | Task | Role | Severity | What was observed | Evidence (sheet, row) | Proposed fix | Status |
| --- | --- | --- | --- | --- | --- | --- | --- |
| UF-01 | UT-nn | | 1–4 | | | | open · fixed · accepted by the Owner |

### 7.2 Fixes and re-tests

| Fix | Findings | The change | Re-test (task, role, round) | Result |
| --- | --- | --- | --- | --- |

### 7.3 The C2 record

The final round only — each critical task's result per participant and, for each phone task — UT-06, UT-12 and UT-18–UT-24 —, the device, the browser and the CSS width it ran on (CA-02):

| Task | The Owner | The Admin Operasional validator | The warehouse/field user (or the validator, SD-09) | Phone task: device | Phone task: browser | Phone task: CSS width |
| --- | --- | --- | --- | --- | --- | --- |
| UT-01 … UT-24 | without help · with help · not completed · Owner-accepted disposition (its reference) | | | the phone's model, by role where participants differ; empty for a desktop task | the browser and its version | the viewport's width in CSS px, as the browser reports it |

APPR-011's condition holds when every critical task is *without help* for every participant of the final round, or carries an Owner-accepted disposition. Then the Owner's verdict (DIR-040 F6):

> Antarmuka ramah pengguna: **ya / tidak** — tanggal; catatan Owner.

## 8. The sessions

Each session is planned to fit 45 minutes: about 5 minutes for the opening (§4.1), the tasks within their limits, about 5 minutes for the closing questions. A role's tasks that cannot fit are split into two sessions (SP-58 (g)).

| Session | Role | Device | Tasks, in order | Planned time |
| --- | --- | --- | --- | --- |
| O-1 | the Owner | desktop, the phone for UT-06 | UT-01–UT-07 | 5 + 22 + 5 = 32 minutes |
| V-1 | the validator | desktop, the phone for UT-12 | UT-08–UT-12 | 5 + 26 + 5 = 36 minutes |
| V-2 | the validator | desktop | UT-13–UT-17 (the purchase order after UT-13 only if time allows) | 5 + 34 + 5 = 44 minutes |
| W-1 | the warehouse/field user | the phone | UT-18–UT-21 | 5 + 26 + 5 = 36 minutes |
| W-2 | the warehouse/field user | the phone | UT-22–UT-24 | 5 + 13 + 5 = 23 minutes |

Under SD-09's fallback the validator takes W-1 and W-2 as sessions V-3 and V-4, on the phone. The planned time is the sum of the limits, so a session runs shorter when the tasks do; the order within a session follows the journeys, and a session never carries a task over to another day without the Owner's say.

## 9. The dry run (SP-59)

On the final build, by script: each task performed along its path at its viewport, UT-01, UT-10 and UT-24 — the language switch — in both directions and from both languages, each screen of the path asserted, the duration recorded. A task that cannot be completed as scripted is a defect, fixed within the scope of run PC-1 and run again for that task. The dry run shows that the kit fits the prototype; it is not a usability result.

**Result (run PC-1, 2026-10-17, on the final build; Chrome/155.0.8059.80):** 27 task runs — every UT task, UT-01, UT-10 and UT-24 from both languages —, 27 completed as scripted; 243 checks passing, 0 failing (each screen of the path asserted with its page checks, the session hygiene per task). The record, with every check, is the prototype's `evidence/pc1/sp59/dryrun.txt`. The seconds are the script's, its checks excluded: they show that each path runs and how long the machine takes, never how long a participant will take.

| Task | Viewport | From | Seconds (the scripted path) | Completed | Path (start \| timed screens) |
| --- | --- | --- | --- | --- | --- |
| UT-01 | 1440x900 | id | 1.5 | yes | PS-01 > \| > PS-01 > PS-01 |
| UT-01 | 1440x900 | en | 1.5 | yes | PS-01 > \| > PS-01 > PS-01 |
| UT-02 | 1440x900 | id | 4 | yes | PS-01 > \| > PS-03 > PS-08 |
| UT-03 | 1440x900 | id | 0.1 | yes | PS-08 > \| > PS-12 > PS-08 |
| UT-04 | 1440x900 | id | 0.3 | yes | PS-08 > \| > PS-14 |
| UT-05 | 1440x900 | id | 0.8 | yes | PS-14 > \| > PS-15 |
| UT-06 | 390x844 | id | 0 | yes | PS-08 > \| > PS-13 |
| UT-07 | 1440x900 | id | 1.5 | yes | PS-08 > \| > PS-01 |
| UT-08 | 1440x900 | id | 3.1 | yes | PS-01 > \| > PS-03 > PS-05 > PS-06 |
| UT-09 | 1440x900 | id | 2.9 | yes | PS-01 > \| > PS-07 > PS-09 |
| UT-10 | 1440x900 | id | 4 | yes | PS-09 > \| > PS-09 > PS-09 |
| UT-10 | 1440x900 | en | 4 | yes | PS-09 > \| > PS-09 > PS-09 |
| UT-11 | 1440x900 | id | 4.3 | yes | PS-09 > \| > PS-16 > PS-17 > PS-18 > PS-19 > PS-20 > PS-21 > PS-22 > PS-23 > PS-24 |
| UT-12 | 390x844 | id | 2.3 | yes | PS-09 > \| > PS-16 > PS-17 > PS-18 > PS-24 |
| UT-13 | 1440x900 | id | 5.8 | yes | PS-09 > \| > PS-25 > PS-26 > PS-27 > PS-28 |
| UT-14 | 1440x900 | id | 1.9 | yes | PS-09 > PS-25 > \| > PS-01 |
| UT-15 | 1440x900 | id | 4.8 | yes | PS-45 > \| > PS-46 > PS-47 > PS-48 > PS-49 |
| UT-16 | 1440x900 | id | 5 | yes | PS-46 > \| > PS-47 > PS-50 > PS-51 |
| UT-17 | 1440x900 | id | 1.1 | yes | PS-47 > \| > PS-52 > PS-48 > PS-53 |
| UT-18 | 390x844 | id | 3.8 | yes | PS-31 > \| > PS-32 > PS-33 > PS-35 > PS-36 |
| UT-19 | 390x844 | id | 9.6 | yes | PS-36 > \| > PS-37 > PS-38 > PS-39 |
| UT-20 | 390x844 | id | 1.3 | yes | PS-39 > \| > PS-40 > PS-41 |
| UT-21 | 390x844 | id | 4.5 | yes | PS-41 > \| > PS-31 > PS-42 |
| UT-22 | 390x844 | id | 3.9 | yes | PS-31 > \| > PS-32 > PS-33 > PS-35 > PS-36 > PS-44 |
| UT-23 | 390x844 | id | 2.6 | yes | PS-43 > \| > PS-43 > PS-44 |
| UT-24 | 390x844 | id | 5.4 | yes | PS-31 > \| > PS-31 > PS-31 |
| UT-24 | 390x844 | en | 5.5 | yes | PS-31 > \| > PS-31 > PS-31 |

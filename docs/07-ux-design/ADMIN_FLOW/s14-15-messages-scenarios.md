## 14. Message catalogue

Patterns, not an exhaustive string table: each fixes the structure, tone and obligatory content of a class of message. Wording rules — sentence case, no exclamation marks, no blame, action verbs on buttons — are in [DESIGN_SYSTEM §16](../DESIGN_SYSTEM.md#16-copy-and-formatting). `{…}` is filled from the answer; nothing is filled from a record the viewer may not see.

| ID | Situation | Pattern | Obligatory content and rule |
| --- | --- | --- | --- |
| MSG-01 | COMMITTED | "{Objek} {kata kerja pasif}." — "Pembayaran Rp4.000.000 tercatat." · "Invoice diterbitkan dengan nomor {nomor}." | what was recorded, with its number where it has one; never a bare "Berhasil" |
| MSG-02 | REJECTED | "Tidak dapat diproses: {aturan dalam bahasa bisnis}. {fakta saat ini}. {langkah berikutnya}" — "Tidak dapat diproses: jumlah melebihi stok tersedia. Tersedia 3 rim, diminta 5 rim. Kurangi jumlah atau buat pembelian." | the failed precondition in business words and the current fact the answer carries (SQ-02); never a rule code alone |
| MSG-03 | CONFLICT — stale | "Data ini sudah berubah sejak Anda membukanya. Isian Anda tetap ada di formulir. Tinjau versi terbaru, lalu terapkan ulang." | says that the input is kept (HO-04) |
| MSG-04 | CONFLICT — already recorded | "Sudah tercatat — periksa sebelum mengulang." | never a re-apply; a link only when the record is in scope (IP-03) |
| MSG-05 | PENDING | "{Hasil turunan} sedang dibuat. {Tindakan utama} sudah tercatat." — "PDF sedang dibuat. Invoice sudah terbit." | names the committed action as done (HO-01) |
| MSG-06 | FAILED — not processed | "Sistem sedang sibuk sehingga tindakan ini belum diproses. Aman diulang." + "Coba Lagi" — a defect (IP-04): "Terjadi kesalahan sistem; tindakan ini belum diproses. Kode rujukan {kode} — laporkan bila terulang." + "Coba Lagi" | only when the answer says nothing was committed (SQ-14, SQ-15) and no earlier attempt of the intent can have reached the server (IP-02); otherwise MSG-07 |
| MSG-07 | FAILED — outcome unknown | "Belum dapat dipastikan apakah tindakan ini tersimpan. Tekan Coba Lagi — sistem tidak akan mencatat dua kali." | never asserts that the action was saved or that it was not saved (SQ-17; UXS-08) |
| MSG-08 | A failed rendition | "{Jenis} sudah terbit dengan nomor {nomor}. PDF gagal dibuat dan akan dicoba lagi otomatis (percobaan {n} dari 5)." — after the fifth: "{Jenis} sudah terbit dengan nomor {nomor}. PDF gagal dibuat setelah 5 percobaan; operator sudah diberi tahu." | the issue stands; the number is unchanged (NM-07); the exhausted variant promises no further attempt (JB-01) |
| MSG-09 | Not found — nonexistent or outside scope | "Halaman tidak ditemukan." with "Kembali ke Beranda" | identical for both cases (AZ-11) |
| MSG-10 | Forbidden | "Anda tidak memiliki kewenangan untuk tindakan ini." | nothing of the record |
| MSG-11 | Session ended, in-place | "Sesi Anda telah berakhir. Masukkan e-mail dan kata sandi untuk melanjutkan. Isian Anda di halaman ini tidak hilang." | names the account; offers "Keluar"; another account's sign-in is a full document load (section 6) |
| MSG-12 | Validation summary | "Periksa {n} isian yang ditandai." and, per field, what is expected — "Jumlah harus lebih dari 0." | beside the field; input kept |
| MSG-13 | Network loss before sending | "Koneksi terputus. Belum ada yang dikirim. Coba lagi setelah tersambung." | only when the browser was offline before the request was dispatched (IP-01); says that nothing was sent |
| MSG-14 | Routed refusal | "Tindakan ini menyangkut catatan perusahaan lain di luar akses Anda. Tidak diproses, dan diteruskan: Owner atau admin yang berwenang akan melihatnya." — a record in scope without the allocation authority: "Tindakan ini memerlukan kewenangan alokasi antar-perusahaan. Tidak diproses, dan diteruskan: Owner atau admin yang berwenang akan melihatnya." | no name of the other company (PJ-04; SQ-23) |
| MSG-15 | Duplicate warning | "Kemungkinan catatan ganda. {daftar catatan yang cocok}. Jika ini memang catatan berbeda, beri alasan dan lanjutkan." — without the authority: "… Periksa catatan di atas. Jika ini memang {catatan} yang berbeda, minta admin berwenang atau Owner untuk mencatatnya." | lists the visible matches (IP-05) |
| MSG-16 | A prefilled date that went stale | "Tanggal transaksi terisi otomatis {tanggal lama}, sedangkan hari ini {tanggal}. Pilih tanggal yang benar." | both dates (ST-08) |
| MSG-17 | C-10 for an Admin | "Nomor {jenis} untuk periode {periode} di {perusahaan} belum dimulai. Hanya Owner yang dapat memulai penomoran atau menetapkan nomor awal. Hubungi Owner, lalu ulangi tindakan ini." | nothing beyond the Admin's own record (12.3) |
| MSG-18 | An import collision outside scope | "Tidak dapat diimpor — perlu tinjauan Owner." | exactly this, nothing more (DP-18) |
| MSG-19 | Sign-in or re-authentication failed | "E-mail atau kata sandi salah." | the same for an unknown account, a wrong password and an inactive account (AU-04) |
| MSG-20 | A limit was reached | "Terlalu banyak percobaan. Coba lagi dalam {n} menit." | for a first attempt says that nothing was processed; for a retry of an open intent only when to try again (IP-02); never which key (AU-05; WS-12) |
| MSG-21 | Export above the row bound | "Data melebihi batas satu ekspor. Persempit periode, lalu minta lagi." | PF-28 |
| MSG-22 | A third export | "Dua ekspor Anda masih diproses. Tunggu salah satunya selesai." | WS-12 |
| MSG-23 | A search list was cut | "Daftar dipotong. Persempit kata kunci." | PF-21 |
| MSG-24 | A term too short for name search | "Ketik minimal 3 huruf atau angka untuk mencari nama. Kode dicari persis." | PF-20, PF-23 |
| MSG-25 | An unknown code | "Kode {kode} tidak dikenal." + "Daftarkan Barcode" for an account that may | never auto-created (SF-SCAN) |
| MSG-26 | A list cannot continue | "Daftar tidak dapat dilanjutkan. Muat ulang daftar." | never a silent first page (PF-14) |
| MSG-27 | Step-up | "Konfirmasi kata sandi untuk melanjutkan. Diminta kembali setelah 15 menit." | AU-15 |
| MSG-28 | A credential link that cannot be used | "Tautan tidak berlaku. Minta tautan baru kepada Owner." | one answer for invalid, expired and used (AU-12) |
| MSG-29 | Notice after a credential event | to the account, by kind — link issued: "Tautan atur kata sandi untuk akun ini dibuat pada {waktu} oleh {penerbit}." · link used: "Kata sandi akun ini diatur pada {waktu} melalui tautan yang dibuat {penerbit}." · password changed: "Kata sandi akun ini diubah pada {waktu}." — each followed by "Jika bukan Anda, segera beri tahu Owner." To the issuing Owner: "Tautan atur kata sandi yang Anda buat untuk {akun} dipakai pada {waktu}. Jika bukan orang tersebut yang memakainya, akhiri sesinya di Pengguna & Akses." | H7-14; AU-13; the Owner variant AU-12, placed on the Owner's Beranda ([INFORMATION_ARCHITECTURE §9](../INFORMATION_ARCHITECTURE.md#9-dashboards-and-queues)) |
| MSG-30 | A stale count | "Stok di lingkup ini berubah setelah hitungan dimulai. Hitungan ditandai kedaluwarsa — lakukan hitung ulang." | ST-03 |
| MSG-31 | An upload refused | "Berkas tidak dapat diunggah. Jenis yang diterima: {jenis}; ukuran maksimal {ukuran}." | FL-02 |
| MSG-32 | The pool-evidence confirmation | "Berkas ini hanya menampilkan kondisi fisik stok bersama, bukan dokumen milik suatu perusahaan." — a required tick | H7-11 |
| MSG-33 | Idle warning | "Sesi akan berakhir dalam {n} menit karena tidak ada aktivitas." + "Tetap Masuk" | AU-07; H7-05 |
| MSG-34 | The absolute limit ahead | "Sesi berakhir pukul {jam} dan tidak dapat diperpanjang. Kirim isian Anda sebelum itu; isian yang belum dikirim akan hilang." | AU-07; at once on a page loaded within the last 15 minutes, and with an open intent MSG-07's warning added (IP-11) |
| MSG-35 | Leaving with unsent input | "Isian yang belum dikirim akan hilang. Tetap {keluar/tinggalkan halaman}?" | H7-05 |
| MSG-36 | A scan that does not fit | "Nomor seri {seri} sudah dipindai." · "Nomor seri {seri} sudah ada di stok." · "Nomor seri {seri} tidak ada di stok." | SF-SCAN |
| MSG-37 | An ambiguous code | "Kode {kode} cocok dengan lebih dari satu barang. Pilih yang dimaksud." | never chosen by the system (SF-SCAN) |
| MSG-38 | An account without any company grant | "Akun Anda belum memiliki akses perusahaan. Hubungi Owner." | RG-04 |
| MSG-39 | Confirming an irreversible command | "{Kata kerja} {objek}? {Akibat yang tidak dapat diubah}. {Cara mengoreksi}" — "Terbitkan Invoice? Nomor akan ditetapkan dan tidak dapat diubah. Kekeliruan hanya dapat diperbaiki dengan revisi atau pembatalan dokumen." | IP-08 |
| MSG-40 | The reason field | "Alasan (wajib) — tercatat di riwayat dan terlihat oleh Owner." | IP-09 |
| MSG-41 | A pending unattributed quantity | "Selisih {jumlah} {satuan} menunggu penetapan Owner. Stok fisik sudah disesuaikan; pekerjaan gudang tidak terhambat." — in a company's stock and profit views: "Paparan perusahaan ini pada selisih yang menunggu penetapan Owner: {jumlah} {satuan}. Belum menjadi biaya atau kerugian." | SF-UNATTRIBUTED; the case's quantity only on the pool views, a company's own exposure only on its views (PJ-22); no candidate company named (PJ-07) |

## 15. Mandatory UX scenarios

The forty-four scenarios of DIR-033 §15, each with its screen and pattern, its copy, the presentation expected and the rule it satisfies. They are red-teamed and re-tested in [P7_QUALITY_GATE](../evidence/P7_QUALITY_GATE.md#ux-scenario-re-test) and become E2E scenarios in P8 (UXH-01).

### Command identity and outcomes

| ID | Scenario | Screen and pattern | Copy | Expected presentation | Rule |
| --- | --- | --- | --- | --- | --- |
| UXS-01 | A double click on a command on a slow network | any command form; IP-02 | MSG-01 | The control is busy after the first click, and a further trigger while the intent is open sends no new identifier — it is dropped or resends the same `command_id` (IP-02); the server runs one command per `command_id`, and the screen shows the one recorded outcome it receives | HO-02; HO-03; CI-04 |
| UXS-02 | The answer is lost after the commit; the user retries | IP-04 | MSG-07, then MSG-01 | "Coba Lagi" sends the same `command_id`; the recorded COMMITTED outcome comes back and is shown as the result — no second effect, no "sudah pernah" error | HO-02; CI-05 |
| UXS-03 | The answer is lost, then the session idle-expires; in-place re-authentication | section 6; IP-04 | MSG-11, then MSG-07 with "Coba Lagi" | The page stays; input and `command_id` stay in memory; history and caches are cleared after the e-mail and password of the same account are accepted — another account's sign-in is a full document load that uncovers and resends nothing; "Coba Lagi" returns or runs the same intent; nothing of the form is in browser storage | ST-05; H7-12; WS-07 |
| UXS-04 | The session expired while a form was open and never sent | section 6; IP-03 | MSG-11 | After the e-mail and password are accepted the form is as it was; its submission is a new intent and meets the stale checks — a CONFLICT if the record moved | WF-ACC-01; AU-07 |
| UXS-05 | 419 on submit | section 6 | MSG-11 | Treated as the ended session: the page is not left, nothing is lost; the resubmission carries the same `command_id` while the payload is unchanged, so a first attempt that did run cannot be doubled; over an open confirmation the dialog stacks above it (DESIGN_SYSTEM PT-16) | HO-02; WS-04 |
| UXS-06 | Absolute timeout, remote termination or logout | section 6 | MSG-34, MSG-35, MSG-11 | Absolute timeout and logout: a full document load of the sign-in page with history and caches cleared. Remote termination: history and caches cleared at the first 401 and the page covered by the opaque re-authentication dialog. In every case Back shows the sign-in page or an entry that can no longer be decrypted and is fetched afresh — and refused — never a page of the ended session | AU-06; H7-12; TM-28 |
| UXS-07 | FAILED after a lock-wait timeout | IP-04 | MSG-06 | "Sistem sedang sibuk … Aman diulang." with "Coba Lagi" on the same `command_id`; inputs frozen meanwhile | SQ-14; RY-07; TX-01 |
| UXS-08 | FAILED with the outcome unknown | IP-04 | MSG-07 | Nothing asserts that the action was saved or that it was not saved; a transport failure after dispatch is this state too (IP-01); the retry returns the outcome or runs the command, and any other answer to it keeps the intent open (IP-02) | SQ-17; HO-01; HO-02 |
| UXS-09 | A rendition fails in the background | SCR-35; IP-12 | MSG-08 | The document shows *Terbit* with its number, *PDF gagal dibuat* with the attempt count and the automatic retry, and after the fifth attempt MSG-08's exhausted variant; the number is the same in every state; no re-issue control | NM-07; JB-01; JB-02; QS-08 |
| UXS-10 | REJECTED with a failed precondition, for example C-04 | any command form; IP-01 | MSG-02 | The rule in business words with the current fact; the input kept; the corrected submission is a new intent | SQ-02; SQ-04; CI-06 |
| UXS-11 | Two Admins edit one draft quotation or invoice | SCR-13, SCR-39; IP-03 | MSG-03 | The later editor gets CONFLICT: the current state beside the kept input, changed fields marked, "Terapkan Ulang pada Versi Terbaru" | ST-01; M-38; PX-07 |
| UXS-12 | CONFLICT "already recorded" | IP-03 | MSG-04 | "Sudah tercatat — periksa sebelum mengulang", "Buka Catatan" when in scope; no re-apply control exists in this state | HO-04; CI-05; SQ-07 |
| UXS-13 | A prefilled business date crosses midnight | PT-25; IP-07 | MSG-16 | CONFLICT with both dates and the picker; a chosen date passes unchanged | ST-08 |
| UXS-14 | A backdated command with a chosen date | PT-25; IP-07 | "Tanggal Mundur" · "Nomor mengikuti periode {periode}" | The mark is visible on the form before sending and on the record's timeline after; the number comes from the date's period | NM-05; BR-DT-03 |

### Duplicates

| ID | Scenario | Screen and pattern | Copy | Expected presentation | Rule |
| --- | --- | --- | --- | --- | --- |
| UXS-15 | A likely duplicate of a fungible event | SCR-22, SCR-29, SCR-25; IP-05 | MSG-15 | The matches listed; a tick per match and a reason; the second submission is a new intent naming them | L-49; BD-13; HO-05 |
| UXS-16 | A payment duplicate | SCR-42; IP-05 | MSG-15, both forms | With `warnings.override`: reason and acknowledged matches. Without it: the matches, the advice to check them and to ask an account with the authority; nothing is recorded | BD-02; L-22 |
| UXS-17 | An evidence duplicate in scope and in a visible family | SCR-36; IP-16, IP-05 | MSG-15 | The warning with the matching evidence linked; the override with its reason | H7-07; FL-10; DP-13 |
| UXS-18 | An evidence duplicate out of scope or in an invisible family | SCR-36; IP-16 | the ordinary MSG-01 | Nothing differs for the uploader in the answer — wording, steps, timing class — and no flag, note or reason appears on the evidence (IP-16). A client-original requirement the upload was linked to stays open in its ordinary state: the residual recorded in GAP-034. The Owner finds the item in SCR-07 | DP-13; SECURITY LG-02 (`TECH-022`); TM-23 |
| UXS-19 | A batch collision outside the preparer's scope | SCR-58; section 13 | MSG-18 | Exactly "Tidak dapat diimpor — perlu tinjauan Owner" and nothing more, under the neutral face of DESIGN_SYSTEM §12.2; a row with an out-of-scope twin is listed under *Tidak Valid* with the same sentence, never under *Duplikat*. The Owner finds the item in SCR-07 | DP-18; TM-27 |

### Visibility and authority

| ID | Scenario | Screen and pattern | Copy | Expected presentation | Rule |
| --- | --- | --- | --- | --- | --- |
| UXS-20 | A Company-A-only Admin views a pooled lot of Company B | SCR-18, SCR-19; PT-04, PT-17 | "perusahaan lain" | The lot's code, product, remaining quantity, rack and condition with the relation marker; no name, code, supplier, date, kind or cost; no link | PJ-01; PJ-04; H7-02 |
| UXS-21 | An Admin with companies A and B | every list; PT-04 | — | Each row carries its company label; no figure sums S3 values of both; counts are per company | CS-08; PF-25; PF-41 |
| UXS-22 | An action on another company's record without a grant | SCR-22, SCR-25; IP-06 | MSG-14 | REJECTED and routed: the actor is told who will see it; the notice is in QS-20 for 14 days for those with the grants | SQ-23; AZ-08; PJ-13 |
| UXS-23 | A grant is revoked while a page is open | IP-21; IP-12 | MSG-09 | The next reload or command gets the not-found answer, identical to a missing page; an export of the account turns *Tidak Tersedia* and its download answers the same way | AZ-03; RV-08; DP-14 |
| UXS-24 | A deactivated user retries | section 6 | MSG-11, then MSG-19 | The request is answered as unauthenticated; the dialog cannot be passed; nothing about the account's state is disclosed | AU-04; AU-09; RV-05 |
| UXS-25 | An Admin opens the Owner review queue address | IP-21 | MSG-09 | The not-found page, identical to a nonexistent address; no menu entry, badge or count exists for the Admin | AZ-11; OD-14 |
| UXS-26 | A high-risk Owner command | IP-10 | MSG-27 | The password dialog when the last confirmation is older than 15 minutes, above any open confirmation; the form behind it keeps its input in memory, and the same `command_id` is sent after the confirmation | AU-15; H7-09 |
| UXS-27 | The Owner grants capabilities | SCR-57; J-ACC-02 | "Pratinjau Akses" | The capability-by-company matrix of the result before "Simpan Akses"; a capability whose prerequisite is removed shows "Nonaktif — prasyarat dicabut" and keeps its grant | H7-06; RG-06; RG-07; GAP-032 |

### Numbering

| ID | Scenario | Screen and pattern | Copy | Expected presentation | Rule |
| --- | --- | --- | --- | --- | --- |
| UXS-28 | A company created after go-live; an Admin creates a project | SCR-11; then SCR-55 | MSG-17; statements (a)–(d) | The Admin gets the C-10 refusal with input kept; the Owner sees "Penomoran belum dimulai: Proyek" and runs "Mulai Penomoran" through its confirmation; the Admin's repeated submission succeeds | C-10; NM-03; HO-39 |
| UXS-29 | An Admin backdates an invoice into a period that is not live and has no counter | SCR-39; SCR-55 | MSG-17; statement (a) | C-10 for the Admin naming the period; the Owner sets that period's first number; the issue then draws it | NM-03; NM-05 |
| UXS-30 | The Owner adds a scheme effective in the past | SCR-55; 12.2 | "Format nomor tidak dapat berlaku mundur. Tanggal paling awal: {tanggal}." | Refused with the earliest date; the backward extension over uncovered dates is offered and accepted | NM-03; HO-39 |
| UXS-31 | A start while a batch holds valid seed rows | SCR-55; 12.2 | "Batch impor {batch} masih memuat baris nomor awal …" | Refused with the batch linked and the commit-or-abort path | NM-03 |
| UXS-32 | Go-live is not recorded | SCR-55; 12.2 | "Go-live belum dicatat. …" | The start and seed commands are not offered; the state is explained; no control records go-live | NM-03; HO-32 |

### Lists, search, scanner and exports

| ID | Scenario | Screen and pattern | Copy | Expected presentation | Rule |
| --- | --- | --- | --- | --- | --- |
| UXS-33 | A two-character term; more than 200 matches | SCR-08; IP-17 | MSG-24; MSG-23 | Exact and prefix matches only for the short term; the cut notice and the narrowing prompt above the list for the long one | PF-20; PF-21; PF-23 |
| UXS-34 | A scan matching two serial rows; no scanner attached | SCR-21, SCR-22; IP-15 | MSG-37 | The two candidates listed for the user's choice; with no scanner the same field takes a typed SKU and "Cari Produk" sits beside it | SF-SCAN; PF-19; V1_SCOPE |
| UXS-35 | *Barang Masuk* of a multi-line purchase on a phone with a scanner; thirty lines by keyboard on a desktop | SCR-21; SCR-17; IP-13, IP-15 | — | Phone: one line at a time, scan-led, each line its own answer, a progress list; a scan while a confirmation is open is discarded; a coarse pointer gets touch-size controls at any width. Desktop: the row form with its focus order and shortcuts | WF-INV-01; AC-14; GAP-020 |
| UXS-36 | An export above the row bound; a third concurrent export | SCR-47, SCR-49; IP-12 | MSG-21; MSG-22 | Both refused with their message — a row bound the job finds before generating shows *Gagal* with MSG-21's text (IP-12); the list shows the five states | PF-28; WS-12; HO-09 |

### Workflow presentations

| ID | Scenario | Screen and pattern | Copy | Expected presentation | Rule |
| --- | --- | --- | --- | --- | --- |
| UXS-37 | Force Complete | SCR-14; J-PRJ-03 | "Selesaikan Paksa" dialog | The unmet conditions and residual obligations listed; the reason; the confirmation bound to that list; a changed list returns as CONFLICT and is shown again | AX-26; ST-04; M-27 |
| UXS-38 | A pre-payment Kuitansi | SCR-35, SCR-39; J-FIN-08 | *Menunggu Pembayaran* · "untuk proses pembayaran" | A state of its own, with its own cue, until "Tautkan Kuitansi" links it (J-FIN-08) or it is voided; the invoice's position block unchanged | QS-19; BR-DOC-04 |
| UXS-39 | A pending unattributed loss | SCR-18, SCR-27, SCR-47 | MSG-41 | A separate line, outside every company's figures: on SCR-18 and SCR-19 the case's pending quantity; in a company's stock and profit views only that company's own exposure; dispatch and receiving work as usual | QS-22; SF-UNATTRIBUTED; PJ-22; PJ-07 |
| UXS-40 | The Owner review queue | SCR-07 | — | Every QS-14 source, the two `TECH-022` kinds included; open conditions and the activity tabs; items link to their records under the Owner's projection; no approve or dismiss control | PERMISSIONS_MATRIX §10; SECURITY LG-02 |

### Design

| ID | Scenario | Screen and pattern | Copy | Expected presentation | Rule |
| --- | --- | --- | --- | --- | --- |
| UXS-41 | A keyboard-only desktop quotation with many lines | SCR-13; PT-10; KB rows | "Pintasan" help | One focus order through the row form; visible focus; no shortcut is a single printable key — Enter, Esc and the arrows act only as keys of the focused widget —, none is active in a scan field, and a scan outside a field that accepts scans is discarded | SH-02; UXI-42 |
| UXS-42 | Status in greyscale and for colour-blind users | every status; PT-13 | the status labels | Each state is a text label with a shape cue; no two states differ by hue alone | UXV-08; OB §22 |
| UXS-43 | An anti-slop audit of the dashboard | SCR-05, SCR-06; PT-21 | — | Row lists and one figures table; no KPI-card wall, chart for decoration, banner or greeting | OB §21; [DESIGN_SYSTEM §18](../DESIGN_SYSTEM.md#18-anti-ai-slop-mapping) |
| UXS-44 | Project detail within the performance rules | SCR-10 | — | At most three deferred groups; lists without totals; polling no faster than once a minute as background requests that never extend the session | QB-02; PF-15; PF-26; PF-34; RV-06 |


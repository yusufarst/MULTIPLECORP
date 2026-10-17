# Localization

Status: REVIEW — Draft of the P7 re-baseline, not normative before APPR-011 | Updated: 2026-10-18 | Owner: Planning

**Draft of the P7 re-baseline, not normative before APPR-011.** Run PC-2 wrote this new document on 2026-10-18 under [DIR-078](../00-governance/DECISION_LOG.md#dir-077-dir-078-obs-030-and-tech-037--p7-ux-re-baseline-cp-a-decisions-run-pc-2-with-p9s-drafting-baseline-and-drafts) SP-63, in Stage 3 of the P7 UX re-baseline, from Stage 2's [LOCALIZATION_ARCHITECTURE](stage2/LOCALIZATION_ARCHITECTURE.md) with the Owner's CP-A decisions — OQ-04 (a) and OQ-05 (a), the account's language kept in `users.locale` ([DIR-077](../00-governance/DECISION_LOG.md#dir-077-dir-078-obs-030-and-tech-037--p7-ux-re-baseline-cp-a-decisions-run-pc-2-with-p9s-drafting-baseline-and-drafts) CA-04 item 14; CA-08) — and its key inventory (SP-68). It is approved, or not, with APPR-011 at OG-2. It answers the design half of [GAP-037](../00-governance/GAP_REGISTER.md#gap-037--bilingual-ui-obligations-are-not-yet-designed-or-tested); P8 tests both languages.

**Sources.** [V1_SCOPE](../01-product/V1_SCOPE.md)'s language and experience contract and CAP-14; the Owner's C-1 ([source record 50](../00-governance/sources/P7_REBASELINE_OWNER_DECISIONS_2026-10-04.txt)); LOCALIZATION_ARCHITECTURE, carried here where §§3–5 say so; [SECURITY](../05-security/SECURITY.md) AU-06–AU-08, AU-13 and WS-07; [API_AND_INTEGRATIONS](../06-api-performance/API_AND_INTEGRATIONS.md) RB-10 and [DATABASE §4.1](../04-architecture/DATABASE/s04-01-iam.md#41-iam--identity--access) as drafted for CA-08; [ADMIN_FLOW §14](ADMIN_FLOW/s14-15-messages-scenarios.md#14-message-catalogue); [INFORMATION_ARCHITECTURE](INFORMATION_ARCHITECTURE.md) §§3 and 8; [DESIGN_SYSTEM](DESIGN_SYSTEM.md) §§12.1 and 16; the journeys' copy sections ([S2-J01–S2-J06](stage2/journeys/) §8) and the session copy the prototype carries — its keyed copy and pages at commit D, read, never changed.

Authority: P7 — the localization contract: the locales, how the language is chosen and kept, the keys and who owns their text, the formats, what never translates, and the inventory of every key. It adds no behaviour; a string's meaning is its source's — ADMIN_FLOW's messages, INFORMATION_ARCHITECTURE's glossary and pages, DESIGN_SYSTEM's statuses.

## 1. The localization contract

AICWDF §18.11, for every user-facing page:

| Element | Contract | Grounds |
| --- | --- | --- |
| Default locale | `id` — Indonesian (id-ID) | C-1; V1_SCOPE |
| Supported locales | `id` and `en` | C-1; CAP-14 |
| Plain-language Indonesian | required — natural, professional Bahasa Indonesia from one glossary (INFORMATION_ARCHITECTURE §3) | V1_SCOPE; UXN-01 |
| Language switch | required — before sign-in on the sign-in pages, after sign-in in the account menu and *Akun Saya* (§2) | C-1; R1 k); `DIR-077` CA-08 |
| Hard-coded localizable copy | forbidden — every string is a key with both texts (§3.6) | AICWDF §18.11 |
| Layout | holds under the longer of the two texts and under WCAG 2.2 1.4.12's spacing; mandated copy is never cut (§3.7; GR-35) | AICWDF §18.11; GR-35 |
| Values | formatted one way in both languages — DESIGN_SYSTEM §16's formats; the English interface translates labels, never values (§3.3) | OQ-04 (a), `DIR-077` CA-04 item 14 |

## 2. Locales, the choice and where it is kept

- **Before sign-in — the address (LOCALIZATION_ARCHITECTURE §3.2, way P1; Level 1, D-IA-23).** The sign-in pages PG-001–PG-004 carry the chosen language as one whitelisted address key, `bahasa=id` or `bahasa=en`, kept by each link between them (INFORMATION_ARCHITECTURE §11). Nothing is stored — no cookie and no browser storage —, so a shared office PC or floor device keeps no one's choice (GR-14; AU-08), and a new visit starts in Indonesian. Ways P2 and P3 are not taken: each would keep something on the device that SECURITY does not otherwise need.
- **After sign-in — the account's own row (`DIR-077` CA-08).** The account's language is `users.locale` — nullable, `id` or `en`, NULL meaning the default, Indonesian (DATABASE §4.1, as drafted) —, read into the session at sign-in and carried as WS-07's locale. Only the account changes it, through *Bahasa tampilan* in the account menu or on *Akun Saya* (SECURITY AU-13; API_AND_INTEGRATIONS RB-10; ROUTE-118); the change applies to the current session at once, keeps the page and its unsent input (§3.7), and is audited (RG-11).
- **Carried Level-1 rules** (LOCALIZATION_ARCHITECTURE §3.2; TECH-032): a choice made before sign-in never writes the account's language, so a device's choice never follows a person into their account; the pages before the full session — PG-001–PG-004 — show the pre-login choice, and the account's language applies from the first page after sign-in, so the language does not change in the middle of the sign-in sequence; an account that never chose gets Indonesian; the re-login dialog keeps the session's language (IP-11); at logout the session ends with its locale and the sign-in page follows the address (AU-06).
- **Documents and exports** stay Indonesian whatever the interface language (§3.4).

## 3. Keys, ownership, formats and rules

Carried from LOCALIZATION_ARCHITECTURE §2, each subsection as Stage 2 wrote it, with its links moved and its decided points stated.

### 3.1 Keys and their naming (proposed, Level 1)

One key per string, in dot syntax, grouped by area, with English identifiers that trace each string to its source:

| Group | Key, for example | Holds | Indonesian text from |
| --- | --- | --- | --- |
| `messages` | `messages.msg_19` | the message catalogue, one key per MSG | [ADMIN_FLOW §14](ADMIN_FLOW/s14-15-messages-scenarios.md#14-message-catalogue) |
| `glossary` | `glossary.gl_041` | the glossary terms used as labels, one key per GL | [INFORMATION_ARCHITECTURE §3](INFORMATION_ARCHITECTURE.md#3-glossary) |
| `status` | `status.project.draft` | the state labels, one key per family and state | [DESIGN_SYSTEM §12.1](DESIGN_SYSTEM.md#121-status-system) |
| `auth` | `auth.code_mismatch` | AU-04's generic answers and H7-16's advice | [SECURITY §2](../05-security/SECURITY.md#2-authentication-sessions-and-credentials); the gate record |
| `documents` | `documents.doc_06` | the document type names | V1_SCOPE's [document catalog](../01-product/V1_SCOPE.md#selectable-document-catalog) |
| `pages` | `pages.pg_001.heading` | each page's own copy, one group per page of [IA_PAGE_INVENTORY](stage2/IA_PAGE_INVENTORY.md) | the journey specifications' copy sections (LOCALIZATION_ARCHITECTURE §4, prototype-only) |

A string with values carries named placeholders — Laravel's `:name` form —, never concatenated pieces, so each language orders its own words. Laravel keeps strings as PHP arrays or as JSON, one file set per language (Laravel 13.x); which form, and how the strings reach React (§3.8), is P11's.

### 3.2 Who owns the copy

| Copy | Indonesian | English |
| --- | --- | --- |
| Defined by a source — MSG-01–MSG-41, the GL terms, AU-04's answers, H7-16's advice, the state labels of [DESIGN_SYSTEM §12.1](DESIGN_SYSTEM.md#121-status-system), the document type names | canonical, quoted exactly | proposed by Planning, validated by the Owner ([GAP-037](../00-governance/GAP_REGISTER.md#gap-037--bilingual-ui-obligations-are-not-yet-designed-or-tested)) |
| Recorded in the gate record — R1's and VC1's labels ([R1 k)](evidence/P7_REBASELINE_GATE.md#k-language-and-authentication) for sign-in) | quoted from there | proposed, validated by the Owner |
| Not defined by any source — a heading, a label, a helper line, the prototype's panel | proposed copy, drafted by Planning with the GL terms and within [OC-06](../00-governance/DECISION_LOG.md#dir-055-dir-056-obs-020-and-tech-028--p7-ux-re-baseline-checkpoint-c1-decision-rework-authorization-baseline-and-rework), citing the requirement it serves; validated by the Owner at the preview | proposed, validated by the Owner |

The Indonesian string is the source and the English one renders it: no English string exists without its Indonesian source, and no copy states a rule, figure or business meaning that its source does not give (DIR-064 §11.3).

### 3.3 Formats

[DESIGN_SYSTEM §16](DESIGN_SYSTEM.md#16-copy-and-formatting) formats each kind of value one way (UXV-12): Rupiah with "Rp" and no space, the dot between thousands and the comma before sen ("Rp1.250.000", "Rp1.250.000,50"); negative money with the minus sign before "Rp" ("−Rp250.000"); quantities exact, never rounded, followed by their unit ("3,5 kg"; "120 rim (24 dus)"); percentages ("0,25%"); dates ("02 Okt 2026", and "2 Oktober 2026" in a sentence); times on the 24-hour clock with a dot and "WIB" ("14.05 WIB"); periods; business numbers exactly as issued; and counts bounded where [PF-15](../06-api-performance/PERFORMANCE.md#4-pagination) bounds them. All dates and times are WIB: business dates, numbering periods and aging use Asia/Jakarta day boundaries ([BR-DT-06](../02-domain/BUSINESS_RULES.md#dates-and-backdating--dir-018)), and the application stores timestamps in UTC ([ARCHITECTURE §16](../04-architecture/ARCHITECTURE.md#16-configuration-environments-and-database-roles)). Entry follows the same convention: [IP-13](ADMIN_FLOW/s06-09-session-forms-lists.md#ip-13--entry) normalizes the Indonesian separators only, and any other dot or character is a validation error (IP-14), never a silent reinterpretation.

These sources fixed the formats for an interface they assumed Indonesian. The Owner decided at CP-A that they stay unchanged in both languages — the English interface translates labels, never values ([OQ-04](stage2/OWNER_QUESTIONS.md#oq-04--value-formats-in-the-english-interface) (a); `DIR-077` CA-04 item 14): a figure reads one way whatever the language, entry accepts one convention (IP-13), and the screen matches the issued document.

**Plurals.** An Indonesian noun does not change with its number — "1 baris", "20 baris" —, so the Indonesian strings need no plural forms. An English string with a count uses `trans_choice`, its forms separated by `|`, with ranges where needed and `:count` for the number (Laravel 13.x); each counted string in a journey specification's copy section gives its English forms.

### 3.4 What never translates

- **Business data.** Client and legal names, codes, SKUs and user-entered text "are data and are never translated" ([DESIGN_SYSTEM §16](DESIGN_SYSTEM.md#16-copy-and-formatting)); so are the names of companies, people, products and units, a note a user writes and the values a history entry records.
- **Business numbers.** Document, project and purchase numbers and lot codes appear exactly as issued (§16).
- **Issued and generated documents.** Every rendition stays Indonesian whatever the display language ([CAP-14](../01-product/V1_SCOPE.md#must-ship); C-1): a document issued while the issuer's interface is in English is the same Indonesian document. An export is a generated file of business data and stays Indonesian, its column headers included (Level-1 reading of CAP-14).
- **Document type names.** The approved names (§16) name Indonesian documents, and the proposed English rendering of each is the approved name itself, so that the screen matches the paper — proposed copy, for the Owner's validation at the preview.

### 3.5 The reference's micro-copy (GR-17; GP-5)

The reference's components carry their own micro-copy — Indonesian defaults, partial English through `L(id, en)` —, and it is never normative wording ([GP-5](evidence/P7_REBASELINE_GATE.md#checkpoint-c1--closure-on-the-golden-frontend-reference-dir-059)); [GR-17](evidence/P7_REBASELINE_GATE.md#carried-items-gr-01) carries its localization to Stage 2.

- **In the application** no string comes from the reference: each becomes a key of §3.1, its Indonesian text canonical, recorded or proposed under §2, its English rendering proposed.
- **In the prototype** the vendored scripts stay byte-identical (SD-04; SP-1), so a string they render cannot be edited. On a journey screen each such string is MultipleCorp copy of §2, or kept off the screen, or listed in [DEVIATION_REGISTER](stage2/DEVIATION_REGISTER.md) with its screen and the language it shows in — never shown unlisted. A string with no English rendering, shown on an English page, is a missing string (§3.6). Which vendored strings the journey screens show, and how those scripts choose their language, is read from the vendored files in Part 5 and recorded in DEVIATION_REGISTER and [PROTOTYPE_PLAN](stage2/PROTOTYPE_PLAN.md).

### 3.6 Both languages, no missing string

**The rule.** Every interface string exists in both languages before its screen is accepted, and no component holds a hardcoded user-facing string ([CAP-14](../01-product/V1_SCOPE.md#must-ship)). Each of these is a defect: a key shown as itself; an empty string; on an English page, an Indonesian interface string — other than §3.4's values, names and document type names —; a sentence built from concatenated pieces.

**In the application** P8 adds these checks to the definition of done and P11's Tasks fill the localization fields of the AICWDF §23 template ([GAP-037](../00-governance/GAP_REGISTER.md#gap-037--bilingual-ui-obligations-are-not-yet-designed-or-tested)); the tooling that finds a missing key is P11's choice — needs verification at implementation.

**How S2-2 checks it**, by script, the result recorded with SV-02 in S2-2's verification records:

1. Each interface string of a prototype screen sits in an element marked with its key of §3.1 — an attribute such as `data-copy` —, and each value or name of §3.4 in an element marked as data. Both pages of a screen, under `/id/` and `/en/` (LOCALIZATION_ARCHITECTURE §4, prototype-only), come from one copy table per screen, the journey specification's (LOCALIZATION_ARCHITECTURE §4, prototype-only).
2. The script loads every page of both sets in a headless browser on 127.0.0.1, with the tooling [PROTOTYPE_PLAN](stage2/PROTOTYPE_PLAN.md) plans within SS-04–SS-06, and reads the marked elements.
3. A screen passes when its two pages hold the same keys; no keyed element is empty or shows its key; on the English page no keyed text equals its Indonesian text unless the copy table marks it the same in both — a name, "PDF", "WIB", a document type name —; and no visible text stands outside a keyed or data element, except a vendored string listed under §3.5.
4. The check covers the scripted states S2-2 renders — messages, dialogs, the out-of-scope page and the panel "Tentang prototipe ini" (SS-08) included —, not only each page's first view.

### 3.7 The layout after a language change

A string's length differs between the two languages, so every screen is checked in both. In the application, [WFL-04](stage2/WRITTEN_FLOWS.md#wfl-04--the-language-switch-both) leaves the user on the same page with its layout re-checked; the switch changes the interface strings only, and the page, its data and its unsent input stay — the input kept in memory, as [IP-11](ADMIN_FLOW/s06-09-session-forms-lists.md#ip-11--session-limits-expiry-and-re-authentication) keeps it across a re-login (Level 1; needs verification at implementation). P8's layout checks follow AICWDF §27 ([GAP-037](../00-governance/GAP_REGISTER.md#gap-037--bilingual-ui-obligations-are-not-yet-designed-or-tested)).

S2-2 renders each journey screen in both languages at SV-02's viewports — 1440×900, 1366×768, 390×844 and 320 px wide — and a script checks, for each:

1. no horizontal scroll except in data tables (SV-02);
2. no clipped or truncated label — no button, tab, badge, chip, menu item or column header whose text overflows its box; a long label wraps where its pattern allows, or its copy is shortened in that language;
3. targets at their sizes (SV-05);
4. the same regions and actions in the same order in both languages, with the same primary action.

In the prototype the switch opens the same screen in the other set, a new page (LOCALIZATION_ARCHITECTURE §4, prototype-only): an input typed before the switch is not carried, which [SIMULATION_REGISTER](stage2/SIMULATION_REGISTER.md) lists as a difference from the application.

### 3.8 How strings reach the interface

[SECURITY WS-07](../05-security/SECURITY.md#4-web-and-input-security) lets the shared props hold only the account's display name, its own effective capabilities and granted companies, the locale and generic flash text, and gives the two-factor challenge page and an enrolment-only session no shared prop beyond the display name and the locale; Inertia includes shared data "with every response" (Inertia.js, *Shared data*). So every response carries the locale, and the string tables never travel as shared props. The strings reach React either with each page's props — the page's own group of §3.1 — or as a static bundle per language served from the application's own origin, which WS-08's policy admits; that choice, and any client library it would bring, is P11's under DEP-07 — needs verification at implementation. Messages, validation errors and flash text come from the server in the request's locale — needs verification at implementation.

## 4. Copy rules

Each journey specification's copy section ([DIR-064](../00-governance/DECISION_LOG.md#dir-063-dir-064-obs-024-and-tech-032--p7-ux-re-baseline-s2-0-acceptance-s2-1-authorization-baseline-and-stage-2-documents) §12.13) holds, for each screen, one table with the columns Key, Indonesian, English, Kind and Source:

1. **Mandated copy** — each canonical Indonesian text quoted exactly, with its source: a MSG text of [ADMIN_FLOW §14](ADMIN_FLOW/s14-15-messages-scenarios.md#14-message-catalogue), a GL term of [INFORMATION_ARCHITECTURE §3](INFORMATION_ARCHITECTURE.md#3-glossary), an [AU-04](../05-security/SECURITY.md#2-authentication-sessions-and-credentials) answer, H7-16's advice, a state label of [DESIGN_SYSTEM §12.1](DESIGN_SYSTEM.md#121-status-system), a document type name, or a label the gate record holds for R1 or VC1. Mandated copy stays on the screen, visible and concise ([R1 g)](evidence/P7_REBASELINE_GATE.md#g-copy-oc-06)); its English is marked "proposed".
2. **Proposed copy** — a heading, label, helper line or panel text the sources do not define, drafted in Indonesian with the GL terms within OC-06's budget — a heading, a state or number, an action; a helper sentence only when the user needs it to decide or act now —, citing the requirement it serves and marked "proposed copy", with a proposed English rendering.
3. **Data** — the names, numbers, amounts and dates the screen shows, from the synthetic set (SP-20), marked as data and never translated (§3.4), formatted under §2.
4. **Counted strings and values** — a counted string gives its English forms (§2); a string with values gives its placeholders (§3.1).
5. **Limits** — no copy states a rule, figure or business meaning beyond its source, and none is marketing language (DIR-064 §11.3).

The Owner validates the proposed copy and every English rendering at the preview ([GAP-037](../00-governance/GAP_REGISTER.md#gap-037--bilingual-ui-obligations-are-not-yet-designed-or-tested)).

## 5. Copy validation (CA-04 items 7–10)

The Owner accepted these strings for the sessions and left their final wording to Stage 3's copy validation (`DIR-077` CA-04 items 7–10). Each is checked against its canonical name and §4's rules; none changes a meaning, a business term or a glossary label. The Owner's session results (S2-4) may still name a change, which PC-3 makes (CA-15).

| Item | Key | Indonesian | English | Stage 3 |
| --- | --- | --- | --- | --- |
| 7 — D6 | MSG-01's lead as the notice's title (TB-D04) | the title is the message's lead, with no colon and no final full stop; the detail follows in the body as whole sentences | the same rule | settled as this rule (Level 1, TECH-037) |
| 8 | `pages.pg_002.back` | Kembali | Back | validated — the canonical "Kembali" of IP-08's dialogs (OQ-14) |
| 8 | `pages.pg_002.code_advice` | Jangan bagikan kode ini kepada siapa pun. | Never share this code with anyone. | validated — H7-16's advice (SP-48) |
| 8 | `pages.pg_006.ageing_table` | Tabel | Table | validated — the chart's table alternative (S2-J02 §§8–9) |
| 8 | `pages.pg_006.col_bucket` | Umur | Age | validated |
| 8 | `pages.pg_006.col_amount` | Jumlah | Amount | validated |
| 8 | `pages.pg_006.bucket_31_60` | Lewat 31–60 hari | 31–60 days overdue | validated — BR-FIN-15's buckets |
| 8 | `pages.pg_006.bucket_61_90` | Lewat 61–90 hari | 61–90 days overdue | validated — BR-FIN-15 |
| 8 | `pages.pg_006.bucket_90` | Lewat lebih dari 90 hari | More than 90 days overdue | validated — BR-FIN-15 |
| 9 | `pages.pg_017.cancel` | Batal | Cancel | validated — "Batal" canonical (PT-09) |
| 9 | `result.copied` | Nomor tersalin | Number copied | validated — Golden's result text (GR-17) |
| 9 | the palette's answer when nothing matches | Tidak ada hasil untuk “:q”. | No results for “:q”. | validated — Golden's own text, its value a named placeholder (§3.1) |
| 10 | S2-1's proposed copy and English renderings — the journeys' copy sections | as §6 lists them, kind *proposed* or *session copy* | as §6 lists them | validated as written for every key §6 holds; a value in them becomes a named placeholder (§3.1) |

## 6. Key inventory

Every key, built by script from its source when this draft was written and checked again for gate PC-2 (SP-68): the group of LOCALIZATION_ARCHITECTURE §2.2 (§3.1 here), the key, its Indonesian and English texts, its kind — *canonical* from its source, *recorded* from a measured reference, *proposed* awaiting validation, *session copy* the text the usability sessions read (CA-04 item 10) — and its source. An English text not yet written is marked **owed**: P11 writes it before the page ships, and P8 fails a page with an owed or missing string (§3.6). A *session copy* text that holds a synthetic value — an amount, a number, a name — becomes a named placeholder in P11 (§3.1). The prototype-only keys — the marker, its panel, the proposals and the out-of-scope page, 20 keys — are not part of V1 and are left out.

### 6.1 `messages` — the message catalogue and its instances

| Key | Indonesian | English | Kind | Source |
| --- | --- | --- | --- | --- |
| `messages.msg_01` | {Objek} {kata kerja pasif}. | **owed** | canonical — COMMITTED | ADMIN_FLOW §14 |
| `messages.msg_02` | Tidak dapat diproses: {aturan dalam bahasa bisnis}. {fakta saat ini}. {langkah berikutnya} | **owed** | canonical — REJECTED | ADMIN_FLOW §14 |
| `messages.msg_03` | Data ini sudah berubah sejak Anda membukanya. Isian Anda tetap ada di formulir. Tinjau versi terbaru, lalu terapkan ulang. | **owed** | canonical — CONFLICT — stale | ADMIN_FLOW §14 |
| `messages.msg_04` | Sudah tercatat — periksa sebelum mengulang. | **owed** | canonical — CONFLICT — already recorded | ADMIN_FLOW §14 |
| `messages.msg_05` | {Hasil turunan} sedang dibuat. {Tindakan utama} sudah tercatat. | **owed** | canonical — PENDING | ADMIN_FLOW §14 |
| `messages.msg_06` | Sistem sedang sibuk sehingga tindakan ini belum diproses. Aman diulang. | **owed** | canonical — FAILED — not processed | ADMIN_FLOW §14 |
| `messages.msg_07` | Belum dapat dipastikan apakah tindakan ini tersimpan. Tekan Coba Lagi — sistem tidak akan mencatat dua kali. | **owed** | canonical — FAILED — outcome unknown | ADMIN_FLOW §14 |
| `messages.msg_08` | {Jenis} sudah terbit dengan nomor {nomor}. PDF gagal dibuat dan akan dicoba lagi otomatis (percobaan {n} dari 5). | **owed** | canonical — A failed rendition | ADMIN_FLOW §14 |
| `messages.msg_09` | Halaman tidak ditemukan. | **owed** | canonical — Not found — nonexistent or outside scope | ADMIN_FLOW §14 |
| `messages.msg_10` | Anda tidak memiliki kewenangan untuk tindakan ini. | **owed** | canonical — Forbidden | ADMIN_FLOW §14 |
| `messages.msg_11` | Sesi Anda telah berakhir. Masukkan e-mail dan kata sandi untuk melanjutkan. Isian Anda di halaman ini tidak hilang. | **owed** | canonical — Session ended, in-place | ADMIN_FLOW §14 |
| `messages.msg_12` | Periksa {n} isian yang ditandai. | **owed** | canonical — Validation summary | ADMIN_FLOW §14 |
| `messages.msg_13` | Koneksi terputus. Belum ada yang dikirim. Coba lagi setelah tersambung. | **owed** | canonical — Network loss before sending | ADMIN_FLOW §14 |
| `messages.msg_14` | Tindakan ini menyangkut catatan perusahaan lain di luar akses Anda. Tidak diproses, dan diteruskan: Owner atau admin yang berwenang akan melihatnya. | **owed** | canonical — Routed refusal | ADMIN_FLOW §14 |
| `messages.msg_15` | Kemungkinan catatan ganda. {daftar catatan yang cocok}. Jika ini memang catatan berbeda, beri alasan dan lanjutkan. | **owed** | canonical — Duplicate warning | ADMIN_FLOW §14 |
| `messages.msg_16` | Tanggal transaksi terisi otomatis {tanggal lama}, sedangkan hari ini {tanggal}. Pilih tanggal yang benar. | **owed** | canonical — A prefilled date that went stale | ADMIN_FLOW §14 |
| `messages.msg_17` | Nomor {jenis} untuk periode {periode} di {perusahaan} belum dimulai. Hanya Owner yang dapat memulai penomoran atau menetapkan nomor awal. Hubungi Owner, lalu ulangi tindakan ini. | **owed** | canonical — C-10 for an Admin | ADMIN_FLOW §14 |
| `messages.msg_18` | Tidak dapat diimpor — perlu tinjauan Owner. | **owed** | canonical — An import collision outside scope | ADMIN_FLOW §14 |
| `messages.msg_19` | E-mail atau kata sandi salah. | Incorrect email or password. | canonical — Sign-in or re-authentication failed | ADMIN_FLOW §14 |
| `messages.msg_20` | Terlalu banyak percobaan. Coba lagi dalam {n} menit. | **owed** | canonical — A limit was reached | ADMIN_FLOW §14 |
| `messages.msg_21` | Data melebihi batas satu ekspor. Persempit periode, lalu minta lagi. | **owed** | canonical — Export above the row bound | ADMIN_FLOW §14 |
| `messages.msg_22` | Dua ekspor Anda masih diproses. Tunggu salah satunya selesai. | **owed** | canonical — A third export | ADMIN_FLOW §14 |
| `messages.msg_23` | Daftar dipotong. Persempit kata kunci. | **owed** | canonical — A search list was cut | ADMIN_FLOW §14 |
| `messages.msg_24` | Ketik minimal 3 huruf atau angka untuk mencari nama. Kode dicari persis. | **owed** | canonical — A term too short for name search | ADMIN_FLOW §14 |
| `messages.msg_25` | Kode {kode} tidak dikenal. | **owed** | canonical — An unknown code | ADMIN_FLOW §14 |
| `messages.msg_26` | Daftar tidak dapat dilanjutkan. Muat ulang daftar. | **owed** | canonical — A list cannot continue | ADMIN_FLOW §14 |
| `messages.msg_27` | Konfirmasi kata sandi untuk melanjutkan. Diminta kembali setelah 15 menit. | **owed** | canonical — Step-up | ADMIN_FLOW §14 |
| `messages.msg_28` | Tautan tidak berlaku. Minta tautan baru kepada Owner. | **owed** | canonical — A credential link that cannot be used | ADMIN_FLOW §14 |
| `messages.msg_29` | Tautan atur kata sandi untuk akun ini dibuat pada {waktu} oleh {penerbit}. | **owed** | canonical — Notice after a credential event | ADMIN_FLOW §14 |
| `messages.msg_30` | Stok di lingkup ini berubah setelah hitungan dimulai. Hitungan ditandai kedaluwarsa — lakukan hitung ulang. | **owed** | canonical — A stale count | ADMIN_FLOW §14 |
| `messages.msg_31` | Berkas tidak dapat diunggah. Jenis yang diterima: {jenis}; ukuran maksimal {ukuran}. | **owed** | canonical — An upload refused | ADMIN_FLOW §14 |
| `messages.msg_32` | Berkas ini hanya menampilkan kondisi fisik stok bersama, bukan dokumen milik suatu perusahaan. | **owed** | canonical — The pool-evidence confirmation | ADMIN_FLOW §14 |
| `messages.msg_33` | Sesi akan berakhir dalam {n} menit karena tidak ada aktivitas. | **owed** | canonical — Idle warning | ADMIN_FLOW §14 |
| `messages.msg_34` | Sesi berakhir pukul {jam} dan tidak dapat diperpanjang. Kirim isian Anda sebelum itu; isian yang belum dikirim akan hilang. | **owed** | canonical — The absolute limit ahead | ADMIN_FLOW §14 |
| `messages.msg_35` | Isian yang belum dikirim akan hilang. Tetap {keluar/tinggalkan halaman}? | **owed** | canonical — Leaving with unsent input | ADMIN_FLOW §14 |
| `messages.msg_36` | Nomor seri {seri} sudah dipindai. | **owed** | canonical — A scan that does not fit | ADMIN_FLOW §14 |
| `messages.msg_37` | Kode {kode} cocok dengan lebih dari satu barang. Pilih yang dimaksud. | **owed** | canonical — An ambiguous code | ADMIN_FLOW §14 |
| `messages.msg_38` | Akun Anda belum memiliki akses perusahaan. Hubungi Owner. | **owed** | canonical — An account without any company grant | ADMIN_FLOW §14 |
| `messages.msg_39` | {Kata kerja} {objek}? {Akibat yang tidak dapat diubah}. {Cara mengoreksi} | **owed** | canonical — Confirming an irreversible command | ADMIN_FLOW §14 |
| `messages.msg_40` | Alasan (wajib) — tercatat di riwayat dan terlihat oleh Owner. | **owed** | canonical — The reason field | ADMIN_FLOW §14 |
| `messages.msg_41` | Selisih {jumlah} {satuan} menunggu penetapan Owner. Stok fisik sudah disesuaikan; pekerjaan gudang tidak terhambat. | **owed** | canonical — A pending unattributed quantity | ADMIN_FLOW §14 |
| `messages.msg_01_draft` | Draf invoice tersimpan. | Invoice draft saved. | proposed | S2-J03 §8 |
| `messages.msg_01_invoice` | Invoice diterbitkan dengan nomor ARJ/INV/2026/0141. | Invoice issued with number ARJ/INV/2026/0141. | canonical | S2-J03 §8 |
| `messages.msg_01_ntb` | Nota Terima Barang diterbitkan dengan nomor ARJ/NTB/2026/0057. | Nota Terima Barang issued with number ARJ/NTB/2026/0057. | canonical | S2-J05 §8 |
| `messages.msg_01_payment` | Pembayaran Rp300.000 tercatat. Rp300.000 diterapkan ke ARJ/INV/2026/0128; sisa piutang Rp700.000. | Payment of Rp300.000 recorded. Rp300.000 applied to ARJ/INV/2026/0128; Rp700.000 outstanding. | canonical · proposed | S2-J06 §8 |
| `messages.msg_01_payment_flagged` | Pembayaran Rp300.000 tercatat tanpa bukti dan ditandai sampai buktinya diunggah. | Payment of Rp300.000 recorded without proof and flagged until its proof is uploaded. | proposed | S2-J06 §8 |
| `messages.msg_01_po` | PO diterbitkan dengan nomor ARJ/PB/2026/0216. | PO issued with number ARJ/PB/2026/0216. | canonical | S2-J04 §8 |
| `messages.msg_01_purchase` | Pembelian dari PT Contoh Komputindo tercatat. Belum ada uang keluar dan belum ada barang masuk. | The purchase from PT Contoh Komputindo is recorded. No money has gone out and no goods have arrived. | proposed | S2-J04 §8 |
| `messages.msg_01_receipt` | Barang masuk tercatat: 18 unit layak pakai di A-02-03, 1 unit rusak diterima, 1 unit ditolak. 15 unit direservasi untuk ARJ/PRJ/2026/0147. | Goods received recorded: 18 unit usable at A-02-03, 1 unit damaged and accepted, 1 unit rejected. 15 unit reserved for ARJ/PRJ/2026/0147. | proposed | S2-J05 §8 |
| `messages.msg_01_receipt_0213` | Barang masuk tercatat: 2 unit layak pakai di B-02-01. | Goods received recorded: 2 unit usable at B-02-01. | proposed | S2-J05 §8 |
| `messages.msg_01_receipt_l2` | Barang masuk tercatat: 20 unit layak pakai di A-03-01. | Goods received recorded: 20 unit usable at A-03-01. | proposed | S2-J05 §8 |
| `messages.msg_01_receipt_l3` | Barang masuk tercatat: 20 unit layak pakai di A-03-02. | Goods received recorded: 20 unit usable at A-03-02. | proposed | S2-J05 §8 |
| `messages.msg_01_receipt_l4` | Barang masuk tercatat: 2 unit layak pakai di B-01-01. | Goods received recorded: 2 unit usable at B-01-01. | proposed | S2-J05 §8 |
| `messages.msg_01_receipt_noreserve` | Barang masuk tercatat: 18 unit layak pakai di A-02-03, 1 unit rusak diterima, 1 unit ditolak. | Goods received recorded: 18 unit usable at A-02-03, 1 unit damaged and accepted, 1 unit rejected. | proposed | S2-J05 §8 |
| `messages.msg_01_receipt_noreserve_tpl` | Barang masuk tercatat: {usable} unit layak pakai di {rack}, {damaged} unit rusak diterima, {rejected} unit ditolak. | Goods received recorded: {usable} unit usable at {rack}, {damaged} unit damaged and accepted, {rejected} unit rejected. | session copy | the pilot's copy |
| `messages.msg_01_receipt_tpl` | Barang masuk tercatat: {usable} unit layak pakai di {rack}, {damaged} unit rusak diterima, {rejected} unit ditolak. {reserve} unit direservasi untuk {project}. | Goods received recorded: {usable} unit usable at {rack}, {damaged} unit damaged and accepted, {rejected} unit rejected. {reserve} unit reserved for {project}. | session copy | the pilot's copy |
| `messages.msg_02_above_outstanding` | Tidak dapat diproses: penerapan melebihi sisa piutang. Sisa ARJ/INV/2026/0133 Rp250.000, diterapkan Rp300.000. Kurangi jumlah penerapan. | Cannot be processed: the application exceeds the outstanding amount. ARJ/INV/2026/0133 has Rp250.000 outstanding, Rp300.000 applied. Reduce the amount applied. | proposed | S2-J06 §8 |
| `messages.msg_02_above_payment` | Tidak dapat diproses: jumlah penerapan melebihi pembayaran. Pembayaran Rp300.000, diterapkan Rp550.000. Kurangi penerapan atau biarkan sisanya sebagai kredit pelanggan. | Cannot be processed: the applications exceed the payment. Payment Rp300.000, applied Rp550.000. Reduce the applications or leave the rest as customer credit. | proposed | S2-J06 §8 |
| `messages.msg_05_pdf` | PDF sedang dibuat. Invoice sudah terbit. | The PDF is being created. The invoice is issued. | canonical · recorded | S2-J03 §8 |
| `messages.msg_05_po` | PDF sedang dibuat. PO sudah terbit. | The PDF is being created. The PO is issued. | canonical | S2-J04 §8 |
| `messages.msg_29_owner` | Tautan atur kata sandi yang Anda buat untuk Bayu dipakai pada 04 Okt 2026 16.20 WIB. Jika bukan orang tersebut yang memakainya, akhiri sesinya di Pengguna & Akses. | The password-setting link you created for Bayu was used on 04 Okt 2026 16.20 WIB. If it was not that person, end the session in Users & Access. | canonical | S2-J02 §8 |
| `messages.msg_29_owner_tpl` | Tautan atur kata sandi yang Anda buat untuk {v} dipakai pada {w} WIB. Jika bukan orang tersebut yang memakainya, akhiri sesinya di Pengguna & Akses. | The password-setting link you created for {v} was used on {w} WIB. If it was not that person, end the session in Users & Access. | session copy | the pilot's copy |
| `messages.msg_35_leave` | Isian yang belum dikirim akan hilang. Tetap tinggalkan halaman? | Unsent input will be lost. Leave the page anyway? | canonical | S2-J04 §8 |
| `messages.msg_35_logout` | Isian yang belum dikirim akan hilang. Tetap keluar? | Unsent input will be lost. Sign out anyway? | canonical | S2-J01 §8 |
| `messages.msg_39_invoice` | Terbitkan Invoice? Nomor akan ditetapkan dan tidak dapat diubah. Kekeliruan hanya dapat diperbaiki dengan revisi atau pembatalan dokumen. | Issue the invoice? The number will be assigned and cannot change. A mistake can only be corrected by revising or voiding the document. | canonical | S2-J03 §8 |
| `messages.msg_39_ntb` | Terbitkan Nota Terima Barang? Nomor akan ditetapkan dan tidak dapat diubah. Kekeliruan hanya dapat diperbaiki dengan revisi atau pembatalan dokumen. | Issue the Nota Terima Barang? The number will be assigned and cannot change. A mistake can only be corrected by revising or voiding the document. | canonical | S2-J05 §8 |
| `messages.msg_39_payment` | Catat pembayaran Rp300.000? Kas masuk akan tercatat dan diterapkan ke invoice yang dipilih. Kekeliruan hanya dapat diperbaiki dengan koreksi pembayaran. | Record the payment of Rp300.000? Cash in will be recorded and applied to the selected invoice. A mistake can only be corrected by a payment correction. | proposed | S2-J06 §8 |
| `messages.msg_39_po` | Terbitkan PO? Nomor akan ditetapkan dan tidak dapat diubah. Kekeliruan hanya dapat diperbaiki dengan revisi atau pembatalan dokumen. | Issue the PO? The number will be assigned and cannot change. A mistake can only be corrected by revising or voiding the document. | canonical | S2-J04 §8 |
| `messages.msg_39_receipt` | Catat barang masuk? 18 unit layak pakai di A-02-03, 1 unit rusak diterima, 1 unit ditolak; 15 unit direservasi untuk ARJ/PRJ/2026/0147. Kekeliruan hanya dapat diperbaiki dengan koreksi penerimaan. | Record the goods received? 18 unit usable at A-02-03, 1 unit damaged and accepted, 1 unit rejected; 15 unit reserved for ARJ/PRJ/2026/0147. A mistake can only be corrected by a receiving correction. | proposed | S2-J05 §8 |
| `messages.msg_39_receipt_0213` | Catat barang masuk? 2 unit layak pakai di B-02-01. Kekeliruan hanya dapat diperbaiki dengan koreksi penerimaan. | Record the goods received? 2 unit usable at B-02-01. A mistake can only be corrected by a receiving correction. | proposed | S2-J05 §8 |
| `messages.msg_39_receipt_l2` | Catat barang masuk? 20 unit layak pakai di A-03-01. Kekeliruan hanya dapat diperbaiki dengan koreksi penerimaan. | Record the goods received? 20 unit usable at A-03-01. A mistake can only be corrected by a receiving correction. | proposed | S2-J05 §8 |
| `messages.msg_39_receipt_l3` | Catat barang masuk? 20 unit layak pakai di A-03-02. Kekeliruan hanya dapat diperbaiki dengan koreksi penerimaan. | Record the goods received? 20 unit usable at A-03-02. A mistake can only be corrected by a receiving correction. | proposed | S2-J05 §8 |
| `messages.msg_39_receipt_l4` | Catat barang masuk? 2 unit layak pakai di B-01-01. Kekeliruan hanya dapat diperbaiki dengan koreksi penerimaan. | Record the goods received? 2 unit usable at B-01-01. A mistake can only be corrected by a receiving correction. | proposed | S2-J05 §8 |
| `messages.msg_39_receipt_noreserve` | Catat barang masuk? 18 unit layak pakai di A-02-03, 1 unit rusak diterima, 1 unit ditolak. Kekeliruan hanya dapat diperbaiki dengan koreksi penerimaan. | Record the goods received? 18 unit usable at A-02-03, 1 unit damaged and accepted, 1 unit rejected. A mistake can only be corrected by a receiving correction. | proposed | S2-J05 §8 |
| `messages.msg_39_receipt_noreserve_tpl` | Catat barang masuk? {usable} unit layak pakai di {rack}, {damaged} unit rusak diterima, {rejected} unit ditolak. Kekeliruan hanya dapat diperbaiki dengan koreksi penerimaan. | Record the goods received? {usable} unit usable at {rack}, {damaged} unit damaged and accepted, {rejected} unit rejected. A mistake can only be corrected by a receiving correction. | session copy | the pilot's copy |
| `messages.msg_39_receipt_tpl` | Catat barang masuk? {usable} unit layak pakai di {rack}, {damaged} unit rusak diterima, {rejected} unit ditolak; {reserve} unit direservasi untuk {project}. Kekeliruan hanya dapat diperbaiki dengan koreksi penerimaan. | Record the goods received? {usable} unit usable at {rack}, {damaged} unit damaged and accepted, {rejected} unit rejected; {reserve} unit reserved for {project}. A mistake can only be corrected by a receiving correction. | session copy | the pilot's copy |
| `messages.no_receivable_yet` | Belum ada piutang aktif dan belum ada kas yang tercatat. | No receivable is active and no cash is recorded yet. | proposed | S2-J03 §8 |

### 6.2 `glossary` — the terms used as labels

| Key | Indonesian | English | Kind | Source |
| --- | --- | --- | --- | --- |
| `glossary.gl_001` | Beranda | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Dashboard (home) |
| `glossary.gl_002` | Antrean Tindakan | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Admin action queue |
| `glossary.gl_003` | Tinjauan Owner | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Owner review queue (QS-14) |
| `glossary.gl_004` | Proyek | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Project |
| `glossary.gl_005` | Penawaran | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Quotation |
| `glossary.gl_006` | Pembelian | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Purchase |
| `glossary.gl_007` | Gudang | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Warehouse |
| `glossary.gl_008` | Stok | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Stock (availability view) |
| `glossary.gl_009` | Barang Masuk | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Receiving |
| `glossary.gl_010` | Barang Keluar | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Dispatch |
| `glossary.gl_011` | Riwayat Stok | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Stock movement history |
| `glossary.gl_012` | Stok Opname | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Stock opname (count) |
| `glossary.gl_013` | Pengiriman | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Delivery |
| `glossary.gl_014` | Dokumen | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Documents |
| `glossary.gl_015` | Dokumen Administrasi | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Administrative requirements |
| `glossary.gl_016` | Keuangan | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Finance area |
| `glossary.gl_017` | Piutang | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Receivables |
| `glossary.gl_018` | Pembayaran | Payments | canonical | INFORMATION_ARCHITECTURE §3 — Payment (received) |
| `glossary.gl_019` | Pengeluaran & Kas Lain | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Disbursements, expenses and other cash |
| `glossary.gl_020` | Laporan | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Reports |
| `glossary.gl_021` | Data Master | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Master data |
| `glossary.gl_022` | Pengaturan | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Settings |
| `glossary.gl_023` | Cari | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Search |
| `glossary.gl_024` | Pindai | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Scan |
| `glossary.gl_025` | Riwayat Aktivitas | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Activity history (audit search) |
| `glossary.gl_026` | Riwayat | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Record timeline |
| `glossary.gl_027` | Log Keamanan | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Security log |
| `glossary.gl_028` | Ekspor Saya | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Own exports |
| `glossary.gl_029` | Akun Saya | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Account page |
| `glossary.gl_030` | Lainnya | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — More (phone navigation) |
| `glossary.gl_031` | Owner | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Owner role |
| `glossary.gl_032` | Admin Operasional | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Admin Operasional role |
| `glossary.gl_033` | Perusahaan | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Legal company |
| `glossary.gl_034` | Akses Perusahaan | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Company grant |
| `glossary.gl_035` | Kewenangan | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Capability |
| `glossary.gl_036` | Kewenangan khusus | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — ADM+ capability |
| `glossary.gl_037` | Khusus Owner | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Owner-only |
| `glossary.gl_038` | perusahaan lain | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Relation marker |
| `glossary.gl_039` | Tampilkan perusahaan | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Working company filter |
| `glossary.gl_040` | Semua perusahaan | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — All companies (filter value) |
| `glossary.gl_041` | Gabungan | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Consolidated (Owner figures) |
| `glossary.gl_042` | Sesi | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Session |
| `glossary.gl_043` | Masuk / Keluar | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Sign in / sign out |
| `glossary.gl_044` | Kata sandi | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Password |
| `glossary.gl_045` | Tautan atur kata sandi | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Credential link |
| `glossary.gl_046` | Konfirmasi kata sandi | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Step-up confirmation |
| `glossary.gl_047` | Nonaktifkan | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Deactivate (account, company) |
| `glossary.gl_048` | Klien | Client | canonical | INFORMATION_ARCHITECTURE §3 — Client organization |
| `glossary.gl_049` | Unit | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Client unit |
| `glossary.gl_050` | PIC | PIC | canonical | INFORMATION_ARCHITECTURE §3 — Person in charge |
| `glossary.gl_051` | Pemasok | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Supplier |
| `glossary.gl_052` | Produk / Jasa | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Product / service |
| `glossary.gl_053` | SKU | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — SKU |
| `glossary.gl_054` | Barcode | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Barcode |
| `glossary.gl_055` | Nomor seri | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Serial number |
| `glossary.gl_056` | Satuan dasar / Satuan lain | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Base unit / alternate unit |
| `glossary.gl_057` | Lokasi rak | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Rack / location |
| `glossary.gl_058` | Arsipkan | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Archive (master) |
| `glossary.gl_059` | Item proyek | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Project item (demand line) |
| `glossary.gl_060` | Kanal: Reguler / SIPLAH | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Channel REGULAR / SIPLAH |
| `glossary.gl_061` | Konfirmasi Proyek | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Commercial confirmation |
| `glossary.gl_062` | Disetujui klien / Ditolak klien | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Client approval / rejection of a quotation |
| `glossary.gl_063` | Cara pemenuhan: Gudang / Kirim langsung / Jasa | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Fulfilment mode: WAREHOUSE / DROP-SHIP / SERVICE |
| `glossary.gl_064` | Kekurangan stok | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Shortage |
| `glossary.gl_065` | Biaya pembelian | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Purchase charge |
| `glossary.gl_066` | Tagihan sumber | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Source bill of a charge |
| `glossary.gl_067` | Tutup Sisa Pembelian | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Purchase-remainder closure |
| `glossary.gl_068` | Pembelian susulan | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Supplementary purchase |
| `glossary.gl_069` | Batalkan sisa | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Remaining-scope cancellation |
| `glossary.gl_070` | Penyelesaian Proyek | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Project completion |
| `glossary.gl_071` | Syarat penyelesaian | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Completion conditions (gate) |
| `glossary.gl_072` | Selesaikan Paksa | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Force Complete |
| `glossary.gl_073` | Kewajiban tersisa | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Residual obligations |
| `glossary.gl_074` | Batalkan Proyek | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Project cancellation |
| `glossary.gl_075` | Tenggat | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Deadline |
| `glossary.gl_076` | Stok fisik | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — ON HAND |
| `glossary.gl_077` | Direservasi | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — RESERVED |
| `glossary.gl_078` | Tidak layak pakai | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — UNUSABLE |
| `glossary.gl_079` | Tersedia | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — AVAILABLE |
| `glossary.gl_080` | Reservasi | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Reservation |
| `glossary.gl_081` | Lot | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Receiving lot |
| `glossary.gl_082` | Ambil lebih dulu | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Oldest-first picking aid |
| `glossary.gl_083` | Layak pakai / Rusak diterima / Ditolak | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Receiving outcomes |
| `glossary.gl_084` | Ubah kondisi | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Condition change |
| `glossary.gl_085` | Karantina | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Quarantine location |
| `glossary.gl_086` | Penyesuaian Stok | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Adjustment (decrease) |
| `glossary.gl_087` | Pemusnahan | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Disposal |
| `glossary.gl_088` | Kerugian persediaan | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Inventory loss |
| `glossary.gl_089` | Selisih menunggu penetapan | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Pending unattributed loss case |
| `glossary.gl_090` | Temuan lebih | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Count surplus finding |
| `glossary.gl_091` | Tetapkan perusahaan | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Owner attribution |
| `glossary.gl_092` | Hitungan kedaluwarsa | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Stale count |
| `glossary.gl_093` | Alokasi antar-perusahaan | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Inter-company allocation |
| `glossary.gl_094` | Pindah lokasi | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Location move |
| `glossary.gl_095` | Stok minimum / Saran isi ulang | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Minimum stock / restock advice |
| `glossary.gl_096` | Retur ke pemasok / Retur dari klien | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Purchase return / sales return |
| `glossary.gl_097` | Konfirmasi kirim langsung | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Drop-ship confirmation |
| `glossary.gl_098` | Serah Terima Jasa | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Service handover |
| `glossary.gl_099` | Penerima / Bukti kirim | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Recipient / delivery proof |
| `glossary.gl_100` | Selisih pengiriman | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Delivery discrepancy |
| `glossary.gl_101` | Tutup Pengiriman | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Delivery closure |
| `glossary.gl_102` | Kasus koreksi | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Correction case |
| `glossary.gl_103` | Penawaran | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — DOC-01 |
| `glossary.gl_104` | Purchase Order (PO) | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — DOC-02 |
| `glossary.gl_105` | Surat Jalan / Faktur Pengiriman | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — DOC-03 |
| `glossary.gl_106` | Nota | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — DOC-04 |
| `glossary.gl_107` | Kuitansi | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — DOC-05 |
| `glossary.gl_108` | Invoice | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — DOC-06 |
| `glossary.gl_109` | Lampiran Kuitansi | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — DOC-07 |
| `glossary.gl_110` | BAST | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — DOC-08 |
| `glossary.gl_111` | SPK | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — DOC-09 |
| `glossary.gl_112` | Berita Acara Pemeriksaan Barang | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — DOC-10 |
| `glossary.gl_113` | HPS | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — DOC-11 |
| `glossary.gl_114` | Nota Terima Barang | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — DOC-12 |
| `glossary.gl_115` | Surat Permintaan Pembayaran | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — DOC-13 |
| `glossary.gl_116` | Surat Pesanan | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — DOC-14 |
| `glossary.gl_117` | Draf | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Draft |
| `glossary.gl_118` | Terbitkan | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Issue (a document) |
| `glossary.gl_119` | Revisi | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Revision |
| `glossary.gl_120` | Batalkan Dokumen | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Void |
| `glossary.gl_121` | PDF sedang dibuat / PDF siap / PDF gagal dibuat | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Rendition states |
| `glossary.gl_122` | Bukti | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Evidence |
| `glossary.gl_123` | Unggah | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Upload |
| `glossary.gl_124` | Asli dari klien | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Client original |
| `glossary.gl_125` | Bukti gudang bersama | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Pool evidence |
| `glossary.gl_126` | Wajib / Opsional | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Requirement: required / optional |
| `glossary.gl_127` | Belum ada / Disiapkan / Terpenuhi / Tidak berlaku | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Requirement status |
| `glossary.gl_128` | Tidak Berlaku | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — N/A waiver |
| `glossary.gl_129` | Boleh ditandai tidak berlaku | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Waiver-eligible |
| `glossary.gl_130` | Nilai penjualan | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Sales / transaction value |
| `glossary.gl_131` | Belum Ditagihkan | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Issued, not billed |
| `glossary.gl_132` | Tagihkan | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Billing act |
| `glossary.gl_133` | Tanggal tagih / Jatuh tempo | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Billing date / due date |
| `glossary.gl_134` | Piutang aktif | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Active receivable |
| `glossary.gl_135` | Sisa tagihan | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Outstanding of an invoice |
| `glossary.gl_136` | Umur piutang | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Aging |
| `glossary.gl_137` | Lewat jatuh tempo | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Overdue |
| `glossary.gl_138` | Lunas | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Paid in full |
| `glossary.gl_139` | Kas masuk | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Cash-In |
| `glossary.gl_140` | Penerapan pembayaran | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Payment application |
| `glossary.gl_141` | Kredit Pelanggan | Customer credit | canonical | INFORMATION_ARCHITECTURE §3 — Customer credit |
| `glossary.gl_142` | Pengembalian dana | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Refund |
| `glossary.gl_143` | Potongan fee / Pajak dipotong atau dipungut | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Fee settlement / tax settlement |
| `glossary.gl_144` | Potongan diklaim | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Claimed deduction |
| `glossary.gl_145` | Sengketa | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Dispute hold |
| `glossary.gl_146` | Hapuskan Piutang | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Write-off |
| `glossary.gl_147` | Pengeluaran kas / Kas keluar | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Disbursement / Cash-Out |
| `glossary.gl_148` | Beban | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Expense |
| `glossary.gl_149` | Kas masuk lain | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Other Cash-In |
| `glossary.gl_150` | Mutasi bank | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Bank statement line |
| `glossary.gl_151` | Biaya / HPP | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Cost / HPP |
| `glossary.gl_152` | Laba / Margin | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Profit / margin |
| `glossary.gl_153` | Arus kas | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Managerial cashflow |
| `glossary.gl_154` | Menunggu pembayaran | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Pre-payment Kuitansi state |
| `glossary.gl_155` | Kontra | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Contra-fact |
| `glossary.gl_156` | Pindahkan penerapan | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Reallocation |
| `glossary.gl_157` | Simpan | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Save (draft, master) |
| `glossary.gl_158` | Catat | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Record (a fact) |
| `glossary.gl_159` | Tersimpan / Tercatat / Terbit | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — COMMITTED |
| `glossary.gl_160` | Tidak dapat diproses | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — REJECTED |
| `glossary.gl_161` | Data sudah berubah · Sudah tercatat | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — CONFLICT |
| `glossary.gl_162` | Sedang diproses | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — PENDING |
| `glossary.gl_163` | Belum dapat dipastikan · Belum diproses | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — FAILED |
| `glossary.gl_164` | Coba Lagi | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Retry |
| `glossary.gl_165` | Terapkan ulang | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Re-apply |
| `glossary.gl_166` | Alasan | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Reason |
| `glossary.gl_167` | Tanggal transaksi | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Business date |
| `glossary.gl_168` | Dicatat | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Recorded at |
| `glossary.gl_169` | Tanggal mundur | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Backdated |
| `glossary.gl_170` | Koreksi / Pembalikan | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Correction / reversal |
| `glossary.gl_171` | Muat lagi | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Load more |
| `glossary.gl_172` | Filter / Urutkan | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Filter / sort |
| `glossary.gl_173` | Ekspor | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Export |
| `glossary.gl_174` | Unduh / Cetak | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Download / print |
| `glossary.gl_175` | Halaman tidak ditemukan | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Not found |
| `glossary.gl_176` | Format nomor | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Numbering scheme |
| `glossary.gl_177` | Mulai Penomoran | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Start of numbering |
| `glossary.gl_178` | Nomor awal | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Seed |
| `glossary.gl_179` | Batas awal penomoran | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Seed boundary |
| `glossary.gl_180` | Go-live | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Go-live |
| `glossary.gl_181` | Impor Data Awal | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Opening import |
| `glossary.gl_182` | Disiapkan / Uji coba lolos / Disetujui bersama / Selesai diimpor / Dibatalkan | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Import batch states |
| `glossary.gl_183` | Uji coba impor | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Dry run |
| `glossary.gl_184` | Tanggal batas | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Cutoff date |
| `glossary.gl_185` | Pintasan | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Shortcut help |
| `glossary.gl_186` | Kode rujukan | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Reference code (correlation id) |
| `glossary.gl_187` | Tindakan Lain | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Overflow menu of page actions |
| `glossary.gl_188` | Bagian Lain | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Overflow menu of a record's parts |
| `glossary.gl_189` | Pengadaan | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — A project's purchasing part |
| `glossary.gl_190` | Urungkan | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Undo a line removal |
| `glossary.gl_191` | Pratinjau Akses | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Access preview |
| `glossary.gl_192` | Pekerjaan | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Work home — work grouped by what has to happen next |
| `glossary.gl_193` | Tinjauan | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — The Owner's review entry |
| `glossary.gl_194` | Menu lengkap | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Full menu |
| `glossary.gl_195` | Kerja | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Work section of the phone's *Lainnya* |
| `glossary.gl_196` | Buat | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Create menu |
| `glossary.gl_197` | Cari atau pindai kode | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Search-and-scan field |
| `glossary.gl_198` | Bahasa tampilan | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Display language |
| `glossary.gl_199` | Perjalanan Proyek | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Project journey |
| `glossary.gl_200` | Tahap proyek | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Project stages |
| `glossary.gl_201` | Sekarang · Berikutnya · Hambatan | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Now, next and blockers |
| `glossary.gl_202` | Kesiapan dokumen | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Document readiness |
| `glossary.gl_203` | Rincian proyek | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Project details |
| `glossary.gl_204` | Yang tersisa sebelum selesai | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Remaining before completion |
| `glossary.gl_205` | Posisi keuangan | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Financial position |
| `glossary.gl_206` | Proyek yang perlu perhatian | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Projects needing attention |
| `glossary.gl_207` | Perlu keputusan Anda | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Needs your decision |
| `glossary.gl_208` | Antrean operasional | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Operational queue |
| `glossary.gl_209` | Tampilkan | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Show — the company filter |
| `glossary.gl_210` | Lihat Semua | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — See all |
| `glossary.gl_211` | Proyek melewati tenggat | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Overdue project |
| `glossary.gl_212` | Siap dikeluarkan | **owed** | canonical | INFORMATION_ARCHITECTURE §3 — Dispatch-ready |
| `glossary.receivables` | Piutang | Receivables | canonical | S2-J02 §8 |

### 6.3 `status` — the state labels

| Key | Indonesian | English | Kind | Source |
| --- | --- | --- | --- | --- |
| `status.project.draft` | Draf | **owed** | canonical | DESIGN_SYSTEM §12.1 — DRAFT |
| `status.project.active` | Aktif | **owed** | canonical | DESIGN_SYSTEM §12.1 — ACTIVE |
| `status.project.completed_normal` | Selesai | **owed** | canonical | DESIGN_SYSTEM §12.1 — COMPLETED_NORMAL |
| `status.project.completed_forced` | Selesai (Paksa) | **owed** | canonical | DESIGN_SYSTEM §12.1 — COMPLETED_FORCED |
| `status.project.cancelled` | Dibatalkan | **owed** | canonical | DESIGN_SYSTEM §12.1 — CANCELLED |
| `status.quotation_revision.draft` | Draf | **owed** | canonical | DESIGN_SYSTEM §12.1 — DRAFT |
| `status.quotation_revision.sent` | Terkirim | **owed** | canonical | DESIGN_SYSTEM §12.1 — SENT |
| `status.quotation_revision.approved` | Disetujui Klien | **owed** | canonical | DESIGN_SYSTEM §12.1 — APPROVED |
| `status.quotation_revision.rejected` | Ditolak Klien | **owed** | canonical | DESIGN_SYSTEM §12.1 — REJECTED |
| `status.quotation_revision.sent_past_validity` | Kedaluwarsa | **owed** | canonical | DESIGN_SYSTEM §12.1 — SENT past validity (L-25) |
| `status.document_version.draft` | Draf | **owed** | canonical | DESIGN_SYSTEM §12.1 — DRAFT |
| `status.document_version.issued` | Terbit | Issued | canonical | DESIGN_SYSTEM §12.1 — ISSUED |
| `status.document_version.superseded` | Direvisi | **owed** | canonical | DESIGN_SYSTEM §12.1 — SUPERSEDED |
| `status.document_version.voided` | Dibatalkan | **owed** | canonical | DESIGN_SYSTEM §12.1 — VOIDED |
| `status.rendition.pending_rendering` | PDF Sedang Dibuat | PDF being created | canonical | DESIGN_SYSTEM §12.1 — PENDING, RENDERING |
| `status.rendition.ready` | PDF Siap | PDF ready | canonical | DESIGN_SYSTEM §12.1 — READY |
| `status.rendition.failed` | PDF Gagal Dibuat | **owed** | canonical | DESIGN_SYSTEM §12.1 — FAILED |
| `status.pre_payment_kuitansi.open_not_linked` | Menunggu Pembayaran | **owed** | canonical | DESIGN_SYSTEM §12.1 — open, not linked |
| `status.pre_payment_kuitansi.linked` | Terhubung ke Pembayaran | **owed** | canonical | DESIGN_SYSTEM §12.1 — linked |
| `status.invoice_and_receivable.issued_not_billed` | Belum Ditagihkan | Not yet billed | canonical | DESIGN_SYSTEM §12.1 — issued, not billed |
| `status.invoice_and_receivable.billed_outstanding` | Ditagihkan | **owed** | canonical | DESIGN_SYSTEM §12.1 — billed, outstanding |
| `status.invoice_and_receivable.past_due_date` | Lewat Jatuh Tempo | Overdue | canonical | DESIGN_SYSTEM §12.1 — past due date |
| `status.invoice_and_receivable.outstanding_zero` | Lunas | **owed** | canonical | DESIGN_SYSTEM §12.1 — outstanding zero |
| `status.invoice_and_receivable.dispute_hold` | Sengketa | Disputed | canonical | DESIGN_SYSTEM §12.1 — dispute hold |
| `status.invoice_and_receivable.written_off` | Dihapuskan | **owed** | canonical | DESIGN_SYSTEM §12.1 — written off |
| `status.payment.without_proof` | Tanpa Bukti | No proof | canonical | DESIGN_SYSTEM §12.1 — without proof (L-29) |
| `status.payment.contra_d` | Dikontra | **owed** | canonical | DESIGN_SYSTEM §12.1 — contra'd |
| `status.reservation.active` | Aktif | **owed** | canonical | DESIGN_SYSTEM §12.1 — ACTIVE |
| `status.reservation.released` | Dilepas | **owed** | canonical | DESIGN_SYSTEM §12.1 — RELEASED |
| `status.reservation.consumed` | Terpakai | **owed** | canonical | DESIGN_SYSTEM §12.1 — CONSUMED |
| `status.purchase.open` | Terbuka | Open | canonical | DESIGN_SYSTEM §12.1 — OPEN |
| `status.purchase.closed` | Sisa Ditutup | **owed** | canonical | DESIGN_SYSTEM §12.1 — CLOSED (remainder closed) |
| `status.purchase.cancelled` | Dibatalkan | **owed** | canonical | DESIGN_SYSTEM §12.1 — CANCELLED |
| `status.unit_condition.usable` | Layak Pakai | **owed** | canonical | DESIGN_SYSTEM §12.1 — usable |
| `status.unit_condition.unusable` | Tidak Layak Pakai | **owed** | canonical | DESIGN_SYSTEM §12.1 — UNUSABLE |
| `status.count.open` | Terbuka | Open | canonical | DESIGN_SYSTEM §12.1 — OPEN |
| `status.count.stale` | Kedaluwarsa | **owed** | canonical | DESIGN_SYSTEM §12.1 — STALE (ST-03) |
| `status.count.applied` | Diterapkan | Applied | canonical | DESIGN_SYSTEM §12.1 — APPLIED |
| `status.count_finding.open_owner_to_attribute` | Menunggu Owner | **owed** | canonical | DESIGN_SYSTEM §12.1 — open, Owner to attribute |
| `status.pending_unexplained_loss_case.pending` | Menunggu Penetapan | **owed** | canonical | DESIGN_SYSTEM §12.1 — pending |
| `status.pending_unexplained_loss_case.resolved` | Ditetapkan | **owed** | canonical | DESIGN_SYSTEM §12.1 — resolved |
| `status.correction_case.open` | Terbuka | Open | canonical | DESIGN_SYSTEM §12.1 — OPEN |
| `status.correction_case.closed` | Ditutup | **owed** | canonical | DESIGN_SYSTEM §12.1 — CLOSED |
| `status.delivery.partly_delivered` | Terkirim Sebagian | **owed** | canonical | DESIGN_SYSTEM §12.1 — partly delivered |
| `status.delivery.fully_delivered` | Terkirim | **owed** | canonical | DESIGN_SYSTEM §12.1 — fully delivered |
| `status.delivery.with_a_discrepancy` | Ada Selisih | **owed** | canonical | DESIGN_SYSTEM §12.1 — with a discrepancy |
| `status.delivery.closed` | Ditutup | **owed** | canonical | DESIGN_SYSTEM §12.1 — closed |
| `status.administrative_requirement.missing` | Belum Ada | **owed** | canonical | DESIGN_SYSTEM §12.1 — missing |
| `status.administrative_requirement.draft_only` | Disiapkan | **owed** | canonical | DESIGN_SYSTEM §12.1 — draft only |
| `status.administrative_requirement.satisfied` | Terpenuhi | **owed** | canonical | DESIGN_SYSTEM §12.1 — satisfied |
| `status.administrative_requirement.waived` | Tidak Berlaku | **owed** | canonical | DESIGN_SYSTEM §12.1 — waived |
| `status.export.requested` | Diminta | **owed** | canonical | DESIGN_SYSTEM §12.1 — requested |
| `status.export.running` | Diproses | **owed** | canonical | DESIGN_SYSTEM §12.1 — running |
| `status.export.ready` | Siap Diunduh | **owed** | canonical | DESIGN_SYSTEM §12.1 — READY |
| `status.export.failed` | Gagal | **owed** | canonical | DESIGN_SYSTEM §12.1 — FAILED |
| `status.export.withheld` | Tidak Tersedia | **owed** | canonical | DESIGN_SYSTEM §12.1 — WITHHELD |
| `status.import_batch.prepared_dry_run_passed_signed_off_committing_committed_stopped_aborted` | Disiapkan · Uji Coba Lolos · Disetujui Bersama · Sedang Diimpor · Selesai Diimpor · Terhenti · Dibatalkan | **owed** | canonical | DESIGN_SYSTEM §12.1 — PREPARED · DRY_RUN_PASSED · SIGNED_OFF · committing · COMMITTED · stopped · ABORTED |
| `status.account_and_company.active_inactive` | Aktif · Nonaktif | **owed** | canonical | DESIGN_SYSTEM §12.1 — active · inactive |
| `status.product_stock.below_its_minimum` | Stok Menipis | **owed** | canonical | DESIGN_SYSTEM §12.1 — below its minimum |
| `status.part_of_a_project.no_record_yet_records_with_something_still_open_nothing_left_open_not_needed_by_the_project_s_lines` | Belum Dimulai · Berjalan · Tuntas · Tidak Diperlukan | **owed** | canonical | DESIGN_SYSTEM §12.1 — no record yet · records with something still open · nothing left open · not needed by the project's lines |
| `status.readiness_item.complete_not_complete` | Lengkap · Belum | **owed** | canonical | DESIGN_SYSTEM §12.1 — complete · not complete |
| `status.line_of_a_receiving_or_dispatch_task_on_a_phone.not_yet_recorded_in_this_task_recorded` | Belum Dicatat · Tercatat | **owed** | canonical | DESIGN_SYSTEM §12.1 — not yet recorded in this task · recorded |
| `status.document.issued` | Terbit | Issued | canonical | S2-J03 §8 |
| `status.invoice.issued_unbilled` | Terbit — Belum Ditagihkan | Issued — not yet billed | recorded | S2-J03 §8 |
| `status.invoice.unbilled` | Belum Ditagihkan | Not yet billed | canonical | S2-J02 §8 |
| `status.part.done` | Tuntas | Done | session copy | the pilot's pages |
| `status.part.in_progress` | Berjalan | In progress | session copy | the pilot's pages |
| `status.part.not_needed` | Tidak Diperlukan | Not needed | session copy | the pilot's pages |
| `status.part.not_started` | Belum Dimulai | Not started | session copy | the pilot's pages |
| `status.payment.no_proof` | Tanpa Bukti | No proof | canonical | S2-J06 §8 |
| `status.receivable.disputed` | Sengketa | Disputed | canonical | S2-J02 §8 |
| `status.receivable.overdue` | Lewat Jatuh Tempo | Overdue | canonical | S2-J02 §8 |
| `status.rendition.pending` | PDF Sedang Dibuat | PDF being created | canonical | S2-J03 §8 |
| `status.task_line.not_recorded` | Belum Dicatat | Not yet recorded | canonical | S2-J05 §8 |
| `status.task_line.recorded` | Tercatat | Recorded | canonical | S2-J05 §8 |

### 6.4 `auth` — the sign-in answers and advice

| Key | Indonesian | English | Kind | Source |
| --- | --- | --- | --- | --- |
| `auth.code_mismatch` | Kode tidak cocok atau sudah tidak berlaku. Masukkan kode terbaru dari aplikasi autentikator. | The code does not match or has expired. Enter the latest code from the authenticator app. | recorded | S2-J01 §8 |
| `auth.shared_device_advice` | Di perangkat bersama, keluar dari Google setelah selesai; kunci ponsel saat tidak dipakai. | On a shared device, sign out of Google when you finish; lock your phone when it is not in use. | recorded | S2-J01 §8 |

### 6.5 `documents` — the document names and the draft marking

| Key | Indonesian | English | Kind | Source |
| --- | --- | --- | --- | --- |
| `documents.doc_01` | Penawaran | **owed** | canonical — the approved name | V1_SCOPE's document catalog |
| `documents.doc_02` | Purchase Order / PO | PO | canonical — the approved name | V1_SCOPE's document catalog |
| `documents.doc_03` | Surat Jalan / Faktur Pengiriman | **owed** | canonical — the approved name | V1_SCOPE's document catalog |
| `documents.doc_04` | Nota | **owed** | canonical — the approved name | V1_SCOPE's document catalog |
| `documents.doc_05` | Kuitansi | **owed** | canonical — the approved name | V1_SCOPE's document catalog |
| `documents.doc_06` | Invoice | Invoice | canonical — the approved name | V1_SCOPE's document catalog |
| `documents.doc_07` | Lampiran Kuitansi | **owed** | canonical — the approved name | V1_SCOPE's document catalog |
| `documents.doc_08` | BAST | BAST | canonical — the approved name | V1_SCOPE's document catalog |
| `documents.doc_09` | SPK | SPK | canonical — the approved name | V1_SCOPE's document catalog |
| `documents.doc_10` | Berita Acara Pemeriksaan Barang | Berita Acara Pemeriksaan Barang | canonical — the approved name | V1_SCOPE's document catalog |
| `documents.doc_11` | HPS | **owed** | canonical — the approved name | V1_SCOPE's document catalog |
| `documents.doc_12` | Nota Terima Barang | **owed** | canonical — the approved name | V1_SCOPE's document catalog |
| `documents.doc_13` | Surat Permintaan Pembayaran | Surat Permintaan Pembayaran | canonical — the approved name | V1_SCOPE's document catalog |
| `documents.doc_14` | Surat Pesanan | Surat Pesanan | canonical — the approved name | V1_SCOPE's document catalog |
| `documents.draft_marking` | DRAF — belum terbit | DRAF — belum terbit | never translated (§3.4) | S2-J03 §8 |
| `documents.number_line` | Nomor diberikan saat terbit | Nomor diberikan saat terbit | never translated (§3.4) | S2-J03 §8 |

### 6.6 `pages` — every page of INFORMATION_ARCHITECTURE §8

| Key | Indonesian | English | Kind | Source |
| --- | --- | --- | --- | --- |
| `pages.pg_001.title` | Masuk | Sign in | proposed | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_001.email` | E-mail | Email | recorded | S2-J01 §8 |
| `pages.pg_001.forgot` | Lupa kata sandi? | Forgot your password? | recorded | S2-J01 §8 |
| `pages.pg_001.forgot_answer` | Minta tautan atur ulang kepada Owner. | Ask the Owner for a reset link. | recorded | S2-J01 §8 |
| `pages.pg_001.google` | Lanjutkan dengan Google | Continue with Google | recorded | S2-J01 §8 |
| `pages.pg_001.heading` | Masuk ke MultipleCorp | Sign in to MultipleCorp | recorded | S2-J01 §8 |
| `pages.pg_001.language` | ID \\| EN | ID \\| EN | recorded | S2-J01 §8 |
| `pages.pg_001.language.en` | EN | EN | session copy | the pilot's pages |
| `pages.pg_001.language.id` | ID | ID | session copy | the pilot's pages |
| `pages.pg_001.or` | atau | or | recorded | S2-J01 §8 |
| `pages.pg_001.password` | Kata sandi | Password | recorded | S2-J01 §8 |
| `pages.pg_001.show` | Tampilkan | Show | recorded | S2-J01 §8 |
| `pages.pg_001.submit` | Masuk | Sign in | recorded | S2-J01 §8 |
| `pages.pg_002.title` | Verifikasi Dua Langkah | Two-step verification | proposed | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_002.back` | Kembali | Back | proposed | S2-J01 §8 |
| `pages.pg_002.code_advice` | Jangan bagikan kode ini kepada siapa pun. | Never share this code with anyone. | session copy | the pilot's pages |
| `pages.pg_002.code_label` | Kode 6 digit dari aplikasi autentikator | 6-digit code from the authenticator app | recorded | S2-J01 §8 |
| `pages.pg_002.heading` | Masukkan kode verifikasi | Enter the verification code | recorded | S2-J01 §8 |
| `pages.pg_002.no_app` | Tidak bisa memakai aplikasi? | Can't use the app? | recorded | S2-J01 §8 |
| `pages.pg_002.no_app_answer` | Masuk dengan kata sandi untuk memakai kode pemulihan. | Sign in with your password to use a recovery code. | recorded | S2-J01 §8 |
| `pages.pg_002.recovery_label` | Kode pemulihan | Recovery code | proposed | S2-J01 §8 |
| `pages.pg_002.submit` | Verifikasi | Verify | recorded | S2-J01 §8 |
| `pages.pg_002.use_recovery` | Gunakan kode pemulihan | Use a recovery code | recorded | S2-J01 §8 |
| `pages.pg_003.title` | Atur Kata Sandi | Set password | proposed | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_004.title` | Aktifkan Verifikasi Dua Langkah | Turn on two-step verification | proposed | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_004.heading` | Aktifkan Verifikasi Dua Langkah | Turn on two-step verification | proposed | S2-J01 §8 |
| `pages.pg_004.reset_line` | Atur ulang aplikasi autentikator untuk melanjutkan. | Set up your authenticator app again to continue. | proposed | S2-J01 §8 |
| `pages.pg_004.start` | Atur aplikasi autentikator | Set up the authenticator app | session copy | the pilot's pages |
| `pages.pg_005.title` | Halaman Sistem | System page | proposed | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_006.title` | Beranda | Home | proposed | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_006.ageing` | Umur piutang | Receivable ageing | recorded | S2-J02 §8 |
| `pages.pg_006.ageing_table` | Tabel | Table | proposed | S2-J02 §8 |
| `pages.pg_006.all_decisions` | Lihat semua keputusan (7) | See all decisions (7) | recorded | S2-J02 §8 |
| `pages.pg_006.all_decisions_tpl` | Lihat semua keputusan ({v}) | See all decisions ({v}) | session copy | the pilot's copy |
| `pages.pg_006.as_of` | Angka per 08.15 WIB | Figures as of 08.15 WIB | canonical | S2-J02 §8 |
| `pages.pg_006.as_of_tpl` | Angka per {v} WIB | Figures as of {v} WIB | session copy | the pilot's copy |
| `pages.pg_006.attention` | Proyek yang perlu perhatian | Projects needing attention | recorded | S2-J02 §8 |
| `pages.pg_006.bucket_1_30` | Lewat 1–30 hari | 1–30 days overdue | recorded · proposed | S2-J02 §8 |
| `pages.pg_006.bucket_31_60` | Lewat 31–60 hari | 31–60 days overdue | recorded · proposed | S2-J02 §8 |
| `pages.pg_006.bucket_61_90` | Lewat 61–90 hari | 61–90 days overdue | recorded · proposed | S2-J02 §8 |
| `pages.pg_006.bucket_90` | Lewat lebih dari 90 hari | More than 90 days overdue | recorded · proposed | S2-J02 §8 |
| `pages.pg_006.bucket_current` | Belum jatuh tempo | Not yet due | recorded · proposed | S2-J02 §8 |
| `pages.pg_006.col_amount` | Jumlah | Amount | proposed | S2-J02 §8 |
| `pages.pg_006.col_bucket` | Umur | Age | proposed | S2-J02 §8 |
| `pages.pg_006.decisions` | Perlu keputusan Anda | Needs your decision | recorded | S2-J02 §8 |
| `pages.pg_006.details` | Rincian angka | Figure details | recorded | S2-J02 §8 |
| `pages.pg_006.kpi_cash_in` | Kas masuk (uang diterima) | Cash in (money received) | recorded | S2-J02 §8 |
| `pages.pg_006.kpi_profit` | Laba manajerial | Managerial profit | recorded | S2-J02 §8 |
| `pages.pg_006.kpi_receivable` | Piutang aktif | Active receivables | recorded | S2-J02 §8 |
| `pages.pg_006.kpi_sales` | Nilai penjualan (invoice terbit) | Sales value (issued invoices) | recorded | S2-J02 §8 |
| `pages.pg_006.name` | Beranda | Home | session copy | the pilot's pages |
| `pages.pg_006.open_work` | Buka Pekerjaan | Open Work | recorded | S2-J02 §8 |
| `pages.pg_006.ops` | Antrean operasional | Operational queue | recorded | S2-J02 §8 |
| `pages.pg_006.overdue_part` | lewat jatuh tempo Rp41.250.000 | Rp41.250.000 overdue | proposed | S2-J02 §8 |
| `pages.pg_006.overdue_part_tpl` | lewat jatuh tempo {v} | {v} overdue | session copy | the pilot's copy |
| `pages.pg_006.period` | Okt 2026 | Okt 2026 | data | S2-J02 §8 |
| `pages.pg_006.period_name` | Periode | Period | proposed | S2-J02 §8 |
| `pages.pg_006.row_qs10` | Piutang lewat jatuh tempo | Overdue receivables | proposed | S2-J02 §8 |
| `pages.pg_006.row_qs11` | Piutang dalam sengketa | Disputed receivables | proposed | S2-J02 §8 |
| `pages.pg_006.row_qs14` | Aktivitas baru sejak Anda masuk sebelumnya | New activity since your previous sign-in | proposed | S2-J02 §8 |
| `pages.pg_006.row_qs16` | Kewajiban tersisa | Remaining obligations | proposed | S2-J02 §8 |
| `pages.pg_006.row_qs16_meta` | Selesai paksa · 1 reservasi tersisa untuk dilepas | Completed by force · 1 remaining reservation to release | proposed | S2-J02 §8 |
| `pages.pg_006.row_qs16_meta_tpl` | Selesai paksa · {v} reservasi tersisa untuk dilepas | Completed by force · {v} remaining reservation to release | session copy | the pilot's copy |
| `pages.pg_006.row_qs20` | Penolakan yang diteruskan | Forwarded refusals | proposed | S2-J02 §8 |
| `pages.pg_006.row_qs20_meta` | ARJ · Barang keluar menyentuh lot perusahaan lain · 2 hari lalu | ARJ · A dispatch touched another company's lot · 2 days ago | proposed | S2-J02 §8 |
| `pages.pg_006.row_qs20_meta_tpl` | Barang keluar menyentuh lot perusahaan lain · {v} hari lalu | A dispatch touched another company's lot · {v} days ago | session copy | the pilot's copy |
| `pages.pg_006.row_qs22` | Selisih Tinta printer hitam menunggu penetapan Owner. | A difference in Tinta printer hitam awaits the Owner's attribution. | proposed | S2-J02 §8 |
| `pages.pg_006.row_qs22_tpl` | Selisih {v} menunggu penetapan Owner. | A difference in {v} awaits the Owner's attribution. | session copy | the pilot's copy |
| `pages.pg_006.shortage_0088` | Laptop 14 inci kurang 20 unit | Laptop 14 inci short by 20 unit | recorded | S2-J02 §8 |
| `pages.pg_006.shortage_tpl` | {v} kurang {w} unit | {v} short by {w} unit | session copy | the pilot's copy |
| `pages.pg_006.view` | Gabungan · ARJ · BTN · CKP — the company codes are data | Consolidated · ARJ · BTN · CKP | canonical · recorded | S2-J02 §8 |
| `pages.pg_006.view.arj` | ARJ | ARJ | session copy | the pilot's pages |
| `pages.pg_006.view.btn` | BTN | BTN | session copy | the pilot's pages |
| `pages.pg_006.view.ckp` | CKP | CKP | session copy | the pilot's pages |
| `pages.pg_006.view.gabungan` | Gabungan | Consolidated | session copy | the pilot's pages |
| `pages.pg_006.view_label` | Tampilan | View | proposed | S2-J02 §8 |
| `pages.pg_007.title` | Pekerjaan | Work | proposed | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_007.awaiting_receipt` | Menunggu tanda terima klien | Awaiting the client's receipt | recorded · proposed | S2-J03 §8 |
| `pages.pg_007.count_label` | Jumlah per perusahaan | Count per company | proposed | S2-J03 §8 |
| `pages.pg_007.docs_empty` | Tidak ada dokumen atau kasus yang perlu ditindaklanjuti. | No documents or cases need follow-up. | recorded | S2-J03 §8 |
| `pages.pg_007.group_billing` | Tagihan dan pembayaran | Billing and payments | recorded | S2-J03 §8 |
| `pages.pg_007.group_completion` | Penyelesaian proyek | Project completion | recorded | S2-J03 §8 |
| `pages.pg_007.group_docs` | Dokumen dan kasus | Documents and cases | recorded | S2-J03 §8 |
| `pages.pg_007.group_goods` | Barang: terima, keluarkan, kirim | Goods: receive, dispatch, deliver | recorded | S2-J03 §8 |
| `pages.pg_007.line_not_received` | 1 baris belum diterima | 1 line not yet received | recorded | S2-J03 §8 |
| `pages.pg_007.name` | Pekerjaan | Work | session copy | the pilot's pages |
| `pages.pg_007.next_card` | Berikutnya: Terima ARJ/PB/2026/0216 — 4 baris belum dicatat | Next: Receive ARJ/PB/2026/0216 — 4 lines not yet recorded | recorded | S2-J05 §8 |
| `pages.pg_007.next_card_3` | Berikutnya: Terima ARJ/PB/2026/0216 — 3 baris belum dicatat | Next: Receive ARJ/PB/2026/0216 — 3 lines not yet recorded | proposed | S2-J05 §8 |
| `pages.pg_007.not_invoiced` | Nilai terkonfirmasi belum diinvoice | Confirmed value not yet invoiced | proposed | S2-J03 §8 |
| `pages.pg_007.not_received_3` | 3 baris belum diterima | 3 lines not yet received | proposed | S2-J05 §8 |
| `pages.pg_007.not_received_4` | 4 baris belum diterima | 4 lines not yet received | proposed | S2-J05 §8 |
| `pages.pg_007.qs06_open` | Buka pengiriman | Open delivery | proposed | S2-J03 §8 |
| `pages.pg_007.row_qs03` | 15 unit diterima menunggu reservasi | 15 unit received, awaiting reservation | proposed | S2-J05 §8 |
| `pages.pg_007.shortage` | Laptop 14 inci kurang 15 unit | Laptop 14 inci short by 15 unit | recorded · proposed | S2-J03 §8 |
| `pages.pg_007.sub_delivery` | Catat pengiriman | Record delivery | recorded | S2-J05 §8 |
| `pages.pg_007.sub_dispatch` | Keluarkan dan kirim | Dispatch and deliver | recorded | S2-J03 §8 |
| `pages.pg_007.sub_dispatch_only` | Keluarkan barang | Dispatch goods | recorded | S2-J05 §8 |
| `pages.pg_007.sub_prepare` | Siapkan barang | Prepare goods | recorded | S2-J03 §8 |
| `pages.pg_007.sub_receive` | Terima barang | Receive goods | recorded | S2-J03 §8 |
| `pages.pg_007.unmet` | 2 syarat belum terpenuhi | 2 conditions not met | proposed | S2-J03 §8 |
| `pages.pg_007.untuk` | untuk | for | proposed | S2-J03 §8 |
| `pages.pg_008.title` | Tinjauan | Review | proposed | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_009.title` | Cari | Search | proposed | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_065.title` | Akun Saya | My account | proposed | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_010.title` | Proyek | Projects | proposed | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_011.title` | Perjalanan Proyek | Project journey | proposed | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_012.title` | Penyelesaian Proyek | Project completion | proposed | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_013.title` | Formulir Proyek | Project form | proposed | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_014.title` | Penawaran | Quotation | proposed | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_037.title` | Daftar Penawaran | Quotations | proposed | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_015.title` | Pembelian | Purchases | proposed | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_016.title` | Detail Pembelian | Purchase | proposed | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_016.layout` | Tata letak | Layout | proposed | S2-J04 §8 |
| `pages.pg_016.remaining` | Sisa diterima | Remaining to receive | canonical · proposed | S2-J04 §8 |
| `pages.pg_017.title` | Buat Pembelian | New purchase | proposed | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_017.basis_line_value` | Dasar alokasi: Nilai baris | Allocation basis: line value | proposed | S2-J04 §8 |
| `pages.pg_017.cancel` | Batal | Cancel | session copy | the pilot's pages |
| `pages.pg_017.charge` | Biaya pembelian | Purchase charge | proposed | S2-J04 §8 |
| `pages.pg_017.charge_amount` | Nilai biaya | Charge amount | proposed | S2-J04 §8 |
| `pages.pg_017.class_attributable` | Masuk biaya perolehan | Part of acquisition cost | canonical | S2-J04 §8 |
| `pages.pg_017.class_expense` | Beban | Expense | canonical | S2-J04 §8 |
| `pages.pg_017.classification` | Klasifikasi | Classification | proposed | S2-J04 §8 |
| `pages.pg_017.line_link` | LPT-14-I5 · untuk item proyek: Laptop 14 inci · 20 unit · Ubah | LPT-14-I5 · for project item: Laptop 14 inci · 20 unit · Change | recorded | S2-J04 §8 |
| `pages.pg_017.line_link.change` | Ubah | Change | session copy | the pilot's pages |
| `pages.pg_017.line_link.code` | LPT-14-I5 | LPT-14-I5 | session copy | the pilot's pages |
| `pages.pg_017.line_link.for_item` | untuk item proyek: Laptop 14 inci | for project item: Laptop 14 inci | session copy | the pilot's pages |
| `pages.pg_017.line_link.qty` | 20 unit | 20 unit | session copy | the pilot's pages |
| `pages.pg_017.line_link_tpl` | untuk item proyek: {v} · {w} unit | for project item: {v} · {w} unit | session copy | the pilot's copy |
| `pages.pg_017.new_supplier` | Pemasok Baru | New supplier | proposed | S2-J04 §8 |
| `pages.pg_017.save` | Simpan Pembelian | Save purchase | proposed | S2-J04 §8 |
| `pages.pg_017.save_reason` | Pilih pemasok dan isi jumlah serta harga setiap baris. | Choose the supplier and enter each line's quantity and price. | proposed | S2-J04 §8 |
| `pages.pg_017.source_bill` | Tagihan sumber | Source bill | proposed | S2-J04 §8 |
| `pages.pg_017.supplier_quote` | Nomor penawaran pemasok | Supplier's quotation number | proposed | S2-J04 §8 |
| `pages.pg_017.use_data_from` | Gunakan data dari | Use data from | recorded | S2-J04 §8 |
| `pages.pg_018.title` | Stok | Stock | proposed | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_019.title` | Detail Stok Produk | Product stock | proposed | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_020.title` | Riwayat Stok | Stock history | proposed | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_021.title` | Barang Masuk | Goods receiving | proposed | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_022.title` | Terima Barang | Receive goods | proposed | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_022.damaged` | Rusak diterima | Damaged, accepted | canonical | S2-J05 §8 |
| `pages.pg_022.delivery_ref` | Nomor surat jalan pemasok | Supplier's delivery reference | proposed | S2-J05 §8 |
| `pages.pg_022.name` | Terima Barang | Receive goods | session copy | the pilot's pages |
| `pages.pg_022.not_received_1` | 1 unit belum diterima | 1 unit not yet received | proposed | S2-J05 §8 |
| `pages.pg_022.progress` | 0 baris tercatat · 4 baris tersisa | 0 lines recorded · 4 lines remaining | recorded | S2-J05 §8 |
| `pages.pg_022.progress.recorded` | 0 baris tercatat | 0 lines recorded | session copy | the pilot's pages |
| `pages.pg_022.progress.recorded_1` | 1 baris tercatat | 1 line recorded | proposed | S2-J05 §8 |
| `pages.pg_022.progress.recorded_2` | 2 baris tercatat | 2 lines recorded | recorded | S2-J05 §8 |
| `pages.pg_022.progress.recorded_3` | 3 baris tercatat | 3 lines recorded | proposed | S2-J05 §8 |
| `pages.pg_022.progress.recorded_4` | 4 baris tercatat | 4 lines recorded | proposed | S2-J05 §8 |
| `pages.pg_022.progress.remaining` | 4 baris tersisa | 4 lines remaining | session copy | the pilot's pages |
| `pages.pg_022.progress.remaining_0` | 0 baris tersisa | 0 lines remaining | proposed | S2-J05 §8 |
| `pages.pg_022.progress.remaining_1` | 1 baris tersisa | 1 line remaining | proposed | S2-J05 §8 |
| `pages.pg_022.progress.remaining_2` | 2 baris tersisa | 2 lines remaining | recorded | S2-J05 §8 |
| `pages.pg_022.progress.remaining_3` | 3 baris tersisa | 3 lines remaining | proposed | S2-J05 §8 |
| `pages.pg_022.rack` | Rak | Rack | proposed | S2-J05 §8 |
| `pages.pg_022.rack_choose` | Pilih rak | Choose a rack | proposed | S2-J05 §8 |
| `pages.pg_022.record` | Catat Barang Masuk | Record goods received | canonical | S2-J05 §8 |
| `pages.pg_022.record_reason` | Isi jumlah dan rak. | Enter the quantities and the rack. | proposed | S2-J05 §8 |
| `pages.pg_022.rejected` | Ditolak | Rejected | canonical | S2-J05 §8 |
| `pages.pg_022.reserve` | Reservasi untuk Proyek Ini | Reserve for this project | canonical · proposed | S2-J05 §8 |
| `pages.pg_022.reserve_limit` | Maksimal 15 unit | At most 15 unit | canonical · proposed | S2-J05 §8 |
| `pages.pg_022.reserve_limit_tpl` | Maksimal {v} unit | At most {v} unit | session copy | the pilot's copy |
| `pages.pg_022.summary` | Ringkasan | Summary | proposed | S2-J05 §8 |
| `pages.pg_022.usable` | Layak pakai | Usable | canonical | S2-J05 §8 |
| `pages.pg_023.title` | Barang Keluar | Goods dispatch | proposed | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_024.title` | Keluarkan Barang | Dispatch goods | proposed | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_025.title` | Reservasi | Reservations | proposed | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_026.title` | Stok Opname | Stock count | proposed | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_027.title` | Penyesuaian & Kondisi | Adjustments and condition | proposed | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_028.title` | Retur ke Pemasok | Supplier return | proposed | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_029.title` | Selisih & Temuan | Discrepancies and findings | proposed | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_030.title` | Pengiriman | Deliveries | proposed | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_031.title` | Catat Pengiriman | Record delivery | proposed | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_032.title` | Kirim Langsung | Direct shipment | proposed | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_033.title` | Serah Terima Jasa | Service handover | proposed | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_034.title` | Retur dari Klien | Client return | proposed | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_035.title` | Dokumen | Documents | proposed | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_036.title` | Detail Dokumen | Document | proposed | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_038.title` | Bukti | Evidence | proposed | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_039.title` | Dokumen Administrasi | Administrative documents | proposed | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_040.title` | Kasus Koreksi | Correction cases | proposed | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_041.title` | Invoice | Invoices | proposed | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_042.title` | Buat Invoice | New invoice | proposed | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_042.name` | Buat Invoice | New invoice | session copy | the pilot's pages |
| `pages.pg_043.title` | Detail Invoice | Invoice | proposed | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_043.col_subtotal` | Subtotal | Subtotal | proposed | S2-J03 §8 |
| `pages.pg_043.documents` | Dokumen | Documents | proposed | S2-J03 §8 |
| `pages.pg_043.lines` | Baris invoice | Invoice lines | proposed | S2-J03 §8 |
| `pages.pg_043.value` | Nilai invoice | Invoice value | proposed | S2-J03 §8 |
| `pages.pg_044.title` | Piutang | Receivables | proposed | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_044.col_due` | Jatuh tempo | Due date | proposed | S2-J02 §8 |
| `pages.pg_044.col_invoice` | Invoice | Invoice | proposed | S2-J02 §8 |
| `pages.pg_044.col_no` | No. | No. | proposed | S2-J02 §8 |
| `pages.pg_044.col_overdue` | Lewat | Overdue by | proposed | S2-J02 §8 |
| `pages.pg_044.days_38` | 38 hari | 38 days | proposed | S2-J02 §8 |
| `pages.pg_044.days_4` | 4 hari | 4 days | proposed | S2-J02 §8 |
| `pages.pg_044.name` | Piutang | Receivables | session copy | the pilot's pages |
| `pages.pg_045.title` | Pembayaran | Payments | proposed | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_045.col_date` | Tanggal | Date | proposed | S2-J06 §8 |
| `pages.pg_045.name` | Pembayaran | Payments | session copy | the pilot's pages |
| `pages.pg_046.title` | Catat Pembayaran | Record payment | proposed | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_046.account` | Rekening perusahaan | Company account | proposed | S2-J06 §8 |
| `pages.pg_046.amount` | Jumlah | Amount | proposed | S2-J06 §8 |
| `pages.pg_046.applications` | Penerapan ke invoice | Applied to invoices | proposed · canonical | S2-J06 §8 |
| `pages.pg_046.col_applied` | Diterapkan | Applied | proposed | S2-J06 §8 |
| `pages.pg_046.method` | Metode | Method | proposed | S2-J06 §8 |
| `pages.pg_046.method_transfer` | Transfer bank | Bank transfer | proposed | S2-J06 §8 |
| `pages.pg_046.name` | Catat Pembayaran | Record payment | session copy | the pilot's pages |
| `pages.pg_046.net_paid` | Klien membayar setelah potongan | The client paid net of deductions | proposed | S2-J06 §8 |
| `pages.pg_046.no_proof_reason` | Alasan tanpa bukti (wajib) — tercatat di riwayat dan terlihat oleh Owner. | Reason for no proof (required) — kept in the history and visible to the Owner. | proposed | S2-J06 §8 |
| `pages.pg_046.outstanding` | Sisa piutang | Outstanding | proposed | S2-J06 §8 |
| `pages.pg_046.proof` | Bukti | Proof | proposed | S2-J06 §8 |
| `pages.pg_046.record_reason` | Lengkapi data pembayaran. | Complete the payment details. | proposed | S2-J06 §8 |
| `pages.pg_046.reference` | Referensi | Reference | proposed | S2-J06 §8 |
| `pages.pg_046.statement_line` | Baris mutasi bank | Bank statement line | proposed | S2-J06 §8 |
| `pages.pg_047.title` | Potongan | Deductions | proposed | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_048.title` | Kredit Pelanggan | Customer credit | proposed | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_049.title` | Pengeluaran & Kas Lain | Disbursements and other cash | proposed | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_050.title` | Mutasi Bank | Bank statement | proposed | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_051.title` | Laporan | Reports | proposed | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_052.title` | Laporan Gabungan | Consolidated report | proposed | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_053.title` | Ekspor Saya | My exports | proposed | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_054.title` | Klien | Clients | proposed | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_055.title` | Pemasok | Suppliers | proposed | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_056.title` | Produk & Jasa | Products and services | proposed | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_057.title` | Lokasi Rak | Rack locations | proposed | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_058.title` | Impor Data Awal | Opening import | proposed | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_059.title` | Perusahaan | Companies | proposed | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_060.title` | Pengguna & Akses | Users and access | proposed | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_061.title` | Penomoran Dokumen | Document numbering | proposed | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_062.title` | Pajak | Tax | proposed | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_063.title` | Riwayat Aktivitas | Activity history | proposed | INFORMATION_ARCHITECTURE §8 |
| `pages.pg_064.title` | Log Keamanan | Security log | proposed | INFORMATION_ARCHITECTURE §8 |

### 6.7 Shared groups — forms, totals, files, dialogs, the shell, actions and the composer

| Key | Indonesian | English | Kind | Source |
| --- | --- | --- | --- | --- |
| `actions.back_to_work` | Kembali ke Pekerjaan | Back to Work | recorded | S2-J05 §8 |
| `actions.bill` | Tagihkan | Bill | proposed · canonical | S2-J03 §8 |
| `actions.copy_number` | Salin nomor | Copy number | canonical · recorded | S2-J03 §8 |
| `actions.create_invoice` | Buat Invoice | Create invoice | recorded | S2-J03 §8 |
| `actions.create_purchase` | Buat Pembelian | Create purchase | recorded · proposed | S2-J03 §8 |
| `actions.download` | Unduh | Download | canonical | S2-J03 §8 |
| `actions.issue_ntb` | Terbitkan Nota Terima Barang | Issue the goods received note | recorded | S2-J05 §8 |
| `actions.issue_po` | Terbitkan PO | Issue PO | canonical | S2-J04 §8 |
| `actions.load_more` | Muat Lagi | Load more | canonical | S2-J06 §8 |
| `actions.more` | Tindakan Lain | More actions | recorded · canonical | S2-J02 §8 |
| `actions.open_invoice` | Buka Invoice | Open invoice | proposed · canonical | S2-J03 §8 |
| `actions.open_project` | Buka Proyek | Open project | recorded · proposed | S2-J03 §8 |
| `actions.print` | Cetak | Print | canonical | S2-J03 §8 |
| `actions.record_payment` | Catat Pembayaran | Record payment | canonical | S2-J06 §8 |
| `actions.start_receiving` | Mulai Terima Barang | Start receiving | recorded | S2-J05 §8 |
| `actions.view_purchase` | Lihat Pembelian | View purchase | recorded · proposed | S2-J03 §8 |
| `composer.all_lines` | Semua baris terkonfirmasi | All confirmed lines | proposed | S2-J03 §8 |
| `composer.back_to_journey` | Kembali ke perjalanan proyek | Back to the project journey | recorded | S2-J03 §8 |
| `composer.billing_address` | Alamat penagihan | Billing address | proposed | S2-J03 §8 |
| `composer.carried` | Diambil dari proyek | Taken from the project | recorded | S2-J03 §8 |
| `composer.check_cap` | Nilai invoice tidak melebihi nilai terkonfirmasi proyek. | The invoice value is within the project's confirmed value. | proposed | S2-J03 §8 |
| `composer.check_delivered` | Nilai invoice tidak melebihi nilai yang sudah dikirim. | The invoice value is not above the delivered value. | proposed | S2-J03 §8 |
| `composer.check_uninvoiced` | Nilai terkonfirmasi yang belum diinvoice: Rp43.900.000. | Confirmed value not yet invoiced: Rp43.900.000. | proposed | S2-J03 §8 |
| `composer.checks` | Pemeriksaan | Checks | recorded | S2-J03 §8 |
| `composer.checks_ok` | 3 pemeriksaan lolos | 3 checks passed | proposed | S2-J03 §8 |
| `composer.client_block` | Klien dan penagihan | Client and billing | proposed | S2-J03 §8 |
| `composer.close_full` | Tutup | Close | proposed | S2-J03 §8 |
| `composer.entered` | Diisi sekarang | Filled in now | recorded | S2-J03 §8 |
| `composer.fit_width` | Pas lebar | Fit width | recorded | S2-J03 §8 |
| `composer.fit_width.actual` | 100% | 100% | session copy | the pilot's pages |
| `composer.fit_width.fit` | Pas lebar | Fit width | session copy | the pilot's pages |
| `composer.fix_at_source` | Perbaiki di data proyek | Fix in the project's data | recorded | S2-J03 §8 |
| `composer.full_screen` | Layar penuh | Full screen | recorded | S2-J03 §8 |
| `composer.issue` | Terbitkan Invoice | Issue invoice | recorded | S2-J03 §8 |
| `composer.layout` | Tata letak: Invoice · Nota | Layout: Invoice · Nota | proposed | S2-J03 §8 |
| `composer.layout.invoice` | Invoice | Invoice | session copy | the pilot's pages |
| `composer.layout.label` | Tata letak: | Layout: | session copy | the pilot's pages |
| `composer.layout.nota` | Nota | Nota | session copy | the pilot's pages |
| `composer.lines` | Baris terkonfirmasi | Confirmed lines | proposed | S2-J03 §8 |
| `composer.page_n` | Halaman 1 dari 1 | Page 1 of 1 | recorded | S2-J03 §8 |
| `composer.page_next` | Halaman berikutnya | Next page | proposed | S2-J03 §8 |
| `composer.page_prev` | Halaman sebelumnya | Previous page | proposed | S2-J03 §8 |
| `composer.preview` | Pratinjau | Preview | proposed | S2-J03 §8 |
| `composer.preview_label` | Pratinjau Invoice, draf belum terbit | Invoice preview, draft not issued | proposed | S2-J03 §8 |
| `composer.sales_value` | Nilai penjualan | Sales value | proposed | S2-J03 §8 |
| `composer.save_draft` | Simpan Draf | Save draft | recorded | S2-J03 §8 |
| `composer.scope` | Yang ditagihkan | What is invoiced | proposed | S2-J03 §8 |
| `composer.source` | Dari proyek ARJ/PRJ/2026/0139 | From project ARJ/PRJ/2026/0139 | proposed | S2-J03 §8 |
| `composer.summary` | Ringkasan | Summary | proposed | S2-J03 §8 |
| `composer.summary_estimate` | Ringkasan (perkiraan) | Summary (estimate) | decided | S2-J03 §8 |
| `composer.tax` | PPN 11% | PPN 11% | proposed | S2-J03 §8 |
| `composer.total` | Total invoice | Invoice total | proposed | S2-J03 §8 |
| `composer.total_estimate` | Total invoice (perkiraan) | Invoice total (estimate) | decided | S2-J03 §8 |
| `composer.unit` | Unit | Unit | proposed | S2-J03 §8 |
| `composer.value_only` | Nilai saja (DP atau termin) | Value only (down payment or instalment) | proposed | S2-J03 §8 |
| `dialogs.back` | Kembali | Back | canonical | S2-J03 §8 |
| `dialogs.leave.confirm` | Tinggalkan Halaman | Leave page | proposed | S2-J04 §8 |
| `dialogs.logout.back` | Kembali | Back | proposed | S2-J01 §8 |
| `dialogs.logout.confirm` | Keluar | Sign out | proposed | S2-J01 §8 |
| `files.cancel` | Batalkan | Cancel | canonical | S2-J04 §8 |
| `files.choose` | Pilih File | Choose file | proposed | S2-J04 §8 |
| `files.limits` | PDF, JPG, PNG atau WebP, maksimal 20 MB. | PDF, JPG, PNG or WebP, up to 20 MB. | recorded | S2-J04 §8 |
| `files.remove` | Hapus file | Remove file | proposed | S2-J04 §8 |
| `files.uploaded` | Terunggah | Uploaded | proposed | S2-J04 §8 |
| `files.uploading` | Mengunggah | Uploading | proposed | S2-J04 §8 |
| `forms.company` | Perusahaan | Company | canonical | S2-J04 §8 |
| `forms.max_exceeded` | Maksimal 15 unit. | At most 15 unit. | proposed | S2-J05 §8 |
| `forms.max_exceeded_tpl` | Maksimal {v} unit. | At most {v} unit. | session copy | the pilot's copy |
| `forms.more_details` | Rincian lain | More details | recorded | S2-J04 §8 |
| `forms.project` | Proyek | Project | canonical | S2-J04 §8 |
| `forms.qty_decrease` | Kurangi | Decrease | session copy | the pilot's copy |
| `forms.qty_increase` | Tambah | Increase | session copy | the pilot's copy |
| `forms.remove_line` | Hapus baris | Remove line | proposed | S2-J04 §8 |
| `forms.supplier` | Pemasok | Supplier | canonical | S2-J04 §8 |
| `journey.all_documents` | Semua dokumen | All documents | recorded | S2-J03 §8 |
| `journey.blocked` | Terhambat | Blocked | canonical · recorded | S2-J02 §8 |
| `journey.blocker_overdue` | Piutang BTN/INV/2026/0094 lewat jatuh tempo 4 hari | Receivable BTN/INV/2026/0094 4 days overdue | recorded | S2-J02 §8 |
| `journey.blockers` | Hambatan | Blockers | recorded | S2-J02 §8 |
| `journey.cost.direct` | Biaya/HPP | Cost / COGS | recorded | S2-J02 §8 |
| `journey.cost.margin` | Margin | Margin | proposed | S2-J02 §8 |
| `journey.cost.profit` | Laba | Profit | recorded | S2-J02 §8 |
| `journey.cost_profit` | Biaya dan laba | Cost and profit | recorded | S2-J02 §8 |
| `journey.deadline` | Tenggat 15 Okt 2026 · 10 hari lagi | Deadline 15 Okt 2026 · 10 days left | recorded | S2-J03 §8 |
| `journey.deadline.date` | Tenggat 15 Okt 2026 | Deadline 15 Okt 2026 | session copy | the pilot's pages |
| `journey.deadline.left` | 10 hari lagi | 10 days left | session copy | the pilot's pages |
| `journey.desk_only` | Langkah ini dikerjakan di komputer. | This step is done on a computer. | proposed | S2-J03 §8 |
| `journey.details` | Rincian proyek | Project details | recorded | S2-J02 §8 |
| `journey.docs_readiness` | Kesiapan dokumen | Document readiness | recorded | S2-J03 §8 |
| `journey.financial` | Posisi keuangan | Financial position | recorded | S2-J02 §8 |
| `journey.next` | Berikutnya | Next | recorded | S2-J02 §8 |
| `journey.next_bast` | Unggah BAST bertanda tangan klien (asli) | Upload the client-signed BAST (original) | recorded | S2-J02 §8 |
| `journey.next_invoice` | Buat invoice dari 3 baris terkonfirmasi | Create an invoice from 3 confirmed lines | recorded | S2-J03 §8 |
| `journey.no_blockers` | Tidak ada | None | recorded | S2-J03 §8 |
| `journey.not_needed` | Tahap yang tidak diperlukan | Stages not needed | recorded | S2-J02 §8 |
| `journey.now` | Sekarang | Now | recorded | S2-J02 §8 |
| `journey.other_met` | 8 syarat lain terpenuhi | 8 other conditions met | recorded | S2-J02 §8 |
| `journey.pos.billed` | Sudah ditagihkan | Billed | recorded | S2-J02 §8 |
| `journey.pos.cash_in` | Kas masuk | Cash in | recorded | S2-J02 §8 |
| `journey.pos.confirmed` | Nilai terkonfirmasi | Confirmed value | recorded | S2-J02 §8 |
| `journey.pos.invoiced` | Sudah diinvoice | Invoiced | recorded | S2-J02 §8 |
| `journey.pos.outstanding` | Sisa tagihan | Outstanding bill | recorded | S2-J02 §8 |
| `journey.pred.administration` | Administrasi | Administration | recorded | S2-J02 §8 |
| `journey.pred.audit` | Audit | Audit | recorded | S2-J02 §8 |
| `journey.pred.billing` | Penagihan | Billing | recorded | S2-J02 §8 |
| `journey.pred.delivery` | Pengiriman | Delivery | recorded | S2-J02 §8 |
| `journey.pred.documents` | Dokumen | Documents | recorded | S2-J02 §8 |
| `journey.pred.fulfilment` | Pemenuhan | Fulfillment | recorded | S2-J02 §8 |
| `journey.pred.payment` | Pembayaran | Payment | recorded | S2-J02 §8 |
| `journey.pred.receivable` | Piutang | Receivable | recorded | S2-J02 §8 |
| `journey.pred.returns` | Retur dan koreksi | Returns and corrections | recorded | S2-J02 §8 |
| `journey.pred.stock` | Stok | Stock | recorded | S2-J02 §8 |
| `journey.primary_bast` | Unggah BAST Klien | Upload client BAST | recorded · canonical | S2-J02 §8 |
| `journey.remaining` | Yang tersisa sebelum selesai | What remains before completion | recorded | S2-J02 §8 |
| `journey.stage.agreement` | Kesepakatan | Agreement | session copy | the pilot's pages |
| `journey.stage.billing` | Penagihan | Billing | session copy | the pilot's pages |
| `journey.stage.completion` | Penyelesaian | Completion | session copy | the pilot's pages |
| `journey.stage.delivery` | Pengiriman | Delivery | session copy | the pilot's pages |
| `journey.stage.direct` | Kirim langsung | Direct shipment | recorded | S2-J02 §8 |
| `journey.stage.documents` | Dokumen | Documents | session copy | the pilot's pages |
| `journey.stage.invoice` | Invoice | Invoice | session copy | the pilot's pages |
| `journey.stage.payment` | Pembayaran | Payment | session copy | the pilot's pages |
| `journey.stage.service` | Jasa | Service | recorded | S2-J02 §8 |
| `journey.stage.warehouse` | Gudang | Warehouse | session copy | the pilot's pages |
| `journey.step_of_0079` | (langkah 4 dari 8) | (step 4 of 8) | recorded | S2-J02 §8 |
| `journey.step_of_0139` | (langkah 6 dari 9) | (step 6 of 9) | recorded | S2-J02 §8 |
| `journey.track` | Tahap proyek | Project stages | recorded | S2-J02 §8 |
| `journey.waiting_invoice` | Menunggu: invoice terbit | Waiting: invoice issued | recorded | S2-J03 §8 |
| `pt10.columns` | Produk/Jasa · Jumlah · Satuan · Harga · Pajak · Subtotal Perkiraan | Product/Service · Quantity · Unit · Price · Tax · Subtotal Estimate | canonical | S2-J04 §8 |
| `pt10.columns.price` | Harga | Price | session copy | the pilot's pages |
| `pt10.columns.product` | Produk/Jasa | Product/Service | session copy | the pilot's pages |
| `pt10.columns.quantity` | Jumlah | Quantity | session copy | the pilot's pages |
| `pt10.columns.subtotal` | Subtotal Perkiraan | Subtotal Estimate | session copy | the pilot's pages |
| `pt10.columns.tax` | Pajak | Tax | session copy | the pilot's pages |
| `pt10.columns.unit` | Satuan | Unit | session copy | the pilot's pages |
| `pt20.placeholder` | Pindai atau ketik kode | Scan or type a code | canonical | S2-J05 §8 |
| `pt20.ready` | Siap Memindai | Ready to scan | canonical | S2-J05 §8 |
| `pt20.search` | Cari Produk | Find product | canonical | S2-J05 §8 |
| `pt25.label` | Tanggal transaksi | Transaction date | canonical | S2-J03 §8 |
| `pt25.today` | Tanggal transaksi: Hari ini, 05 Okt 2026 · Ubah | Transaction date: Today, 05 Okt 2026 · Change | canonical | S2-J03 §8 |
| `pt25.today.change` | Ubah | Change | session copy | the pilot's pages |
| `pt25.today.line_tpl` | Tanggal transaksi: Hari ini, {v} | Transaction date: Today, {v} | session copy | the pilot's copy |
| `purchase.col_actions` | Tindakan baris | Line actions | proposed | S2-J04 §8 |
| `purchase.col_no` | No. | No. | proposed | S2-J04 §8 |
| `purchase.lines` | Baris pembelian | Purchase lines | proposed | S2-J04 §8 |
| `purchase.subtotal` | Subtotal | Subtotal | proposed | S2-J04 §8 |
| `purchase.summary` | Ringkasan | Summary | proposed | S2-J04 §8 |
| `purchase.tax_ppn` | PPN 11% | PPN 11% | proposed | S2-J04 §8 |
| `purchase.total` | Total | Total | proposed | S2-J04 §8 |
| `result.copied` | Nomor tersalin | Number copied | session copy | the pilot's copy |
| `shell.account.language` | Bahasa tampilan: Indonesia \\| English | Display language: Indonesia \\| English | recorded | S2-J01 §8 |
| `shell.account.logout` | Keluar | Sign out | recorded | S2-J01 §8 |
| `shell.account.menu` | Menu akun | Account menu | session copy | the pilot's copy |
| `shell.account.my_account` | Akun Saya | My account | recorded | S2-J01 §8 |
| `shell.back` | Kembali | Back | session copy | the pilot's copy |
| `shell.collapse` | Ciutkan navigasi | Collapse navigation | session copy | the pilot's copy |
| `shell.create` | Buat | Create | session copy | the pilot's pages |
| `shell.drawer.account` | Akun | Account | session copy | the pilot's copy |
| `shell.drawer.close` | Tutup menu | Close menu | session copy | the pilot's copy |
| `shell.drawer.label` | Menu aplikasi | Application menu | session copy | the pilot's copy |
| `shell.drawer.nav` | Navigasi lengkap | Full navigation | session copy | the pilot's copy |
| `shell.expand` | Perluas navigasi | Expand navigation | session copy | the pilot's copy |
| `shell.nav.akun` | Akun | Account | session copy | the pilot's copy |
| `shell.nav.beranda` | Beranda | Home | session copy | the pilot's pages |
| `shell.nav.cari` | Cari | Search | session copy | the pilot's copy |
| `shell.nav.lainnya` | Lainnya | More | session copy | the pilot's copy |
| `shell.nav.pekerjaan` | Pekerjaan | Work | session copy | the pilot's pages |
| `shell.search` | Cari atau pindai kode | Search or scan a code | session copy | the pilot's copy |
| `shell.search_kbd` | Ctrl K | Ctrl K | session copy | the pilot's pages |
| `shell.skip` | Langsung ke konten utama | Skip to main content | session copy | the pilot's pages |
| `totals.estimate` | Perkiraan | Estimate | canonical | S2-J04 §8 |

## 7. Counts

Derived by script (SP-68): **786 keys** — 77 messages (MSG-01–MSG-41 each with its key), 213 glossary keys (GL-001–GL-212 each with its key), 75 state labels, 2 sign-in answers, 16 document keys, 233 page keys and 170 shared keys. **Pages:** 65 of 65 with a title in both languages. **Indonesian:** 786 of 786 written. **English:** 480 written, 306 marked owed. **Missing:** 0 — every key holds both texts or an "owed" mark.

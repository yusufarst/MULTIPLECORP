# Simulation register

Status: DRAFT | Updated: 2026-10-14 | Owner: Planning

**Draft — not normative.** This is an S2-1 document of Stage 2 of the P7 UX re-baseline, written under [DIR-061](../../00-governance/DECISION_LOG.md#dir-061-dir-062-obs-023-and-tech-031--p7-ux-re-baseline-stage-2-decisions-s2-0-authorization-baseline-and-prototype-setup) SD-01, SD-05 and SD-10 and [DIR-064](../../00-governance/DECISION_LOG.md#dir-063-dir-064-obs-024-and-tech-032--p7-ux-re-baseline-s2-0-acceptance-s2-1-authorization-baseline-and-stage-2-documents) §12.11 (SS-08; AK-8). Behaviour follows MULTIPLECORP — P0–P6 and ADMIN_FLOW —, structure follows R1, and presentation follows the Golden Frontend Reference v1.0.0, which adapts to MULTIPLECORP and never the reverse (AK-1; SD-02). [INFORMATION_ARCHITECTURE](../INFORMATION_ARCHITECTURE.md) and [DESIGN_SYSTEM](../DESIGN_SYSTEM.md) stay APPROVED under APPR-008 and under replacement, and [ADMIN_FLOW](../ADMIN_FLOW/README.md) stays in force, until a later authorized stage changes them; Stage 3 uses this draft, and nothing in it is normative.

**Sources.** The gate record's [Stage 2 decisions](../evidence/P7_REBASELINE_GATE.md#stage-2--owner-decisions-dir-061) — SD-05's scope, SS-01–SS-08 and the six journeys S2-J01–S2-J06 with S2-R01–S2-R09; DIR-064 SP-13, SP-19 and SP-20; [IA_PAGE_INVENTORY](IA_PAGE_INVENTORY.md)'s prototype column; [AUTH_JOURNEYS](AUTH_JOURNEYS.md), [LOCALIZATION_ARCHITECTURE](LOCALIZATION_ARCHITECTURE.md) and [DOCUMENT_PREVIEW_PRODUCER](DOCUMENT_PREVIEW_PRODUCER.md), each for its subject; the rules each entry names, in [SECURITY](../../05-security/SECURITY.md#security-architecture), [PERMISSIONS_MATRIX](../../05-security/PERMISSIONS_MATRIX.md#permissions-matrix), [WORKFLOWS](../../03-workflows/WORKFLOWS/README.md#critical-business-workflows), [BUSINESS_RULES](../../02-domain/BUSINESS_RULES.md#business-rules), [CONCURRENCY_IDEMPOTENCY](../../06-api-performance/CONCURRENCY_IDEMPOTENCY/README.md#concurrency-and-idempotency-mechanisms) and ADMIN_FLOW.

## 1. How this register works

The prototype is not a second application: no backend, generic CRUD or storage of any kind, browser storage included, and every transition scripted from fixed synthetic data ([SD-05](../../00-governance/DECISION_LOG.md#dir-061-dir-062-obs-023-and-tech-031--p7-ux-re-baseline-stage-2-decisions-s2-0-authorization-baseline-and-prototype-setup); [SS-07](../evidence/P7_REBASELINE_GATE.md#stage-2--owner-decisions-dir-061)). What it shows in place of a system effect is listed here by category, SIM-01 onward; §5 gives, for each category, the PS screens of the journey specifications where it appears. For each category: what the application does, by its rule; what the prototype shows instead; why that is safe for usability testing; and whether a "Simulasi" chip marks it — only where something could be mistaken for a real system effect, such as an issued document number (SS-08). The prototype never presents itself as a working system.

## 2. The categories

| SIM | Category | The application, by its rule | The prototype instead | Why safe for usability testing | Chip |
| --- | --- | --- | --- | --- | --- |
| SIM-01 | Authentication | the password checked against its hash, the generic answers, the cooldown and throttling ([SECURITY](../../05-security/SECURITY.md#2-authentication-sessions-and-credentials) AU-03–AU-05) | the page compares, in memory, what is typed with the persona's fixed synthetic values — an e-mail address under a reserved domain and a synthetic password —: a match leads to the next step, a mismatch to [MSG-19](../ADMIN_FLOW/s14-15-messages-scenarios.md#14-message-catalogue); the cooldown is a scripted state; nothing typed is sent or kept ([SS-01](../evidence/P7_REBASELINE_GATE.md#stage-2--owner-decisions-dir-061); SS-02; SS-07) | the screens, their order and their answers are the design's; no credential exists | — |
| SIM-02 | TOTP and recovery codes | a six-digit code verified against the enrolled secret; a recovery code used once (AU-16; AU-19) | one fixed synthetic code and one synthetic recovery code are accepted, any other value gets the generic code answer; no secret, code list or QR image exists | the participant meets the same fields and answers | — |
| SIM-03 | Google sign-in | a top-level redirect to Google, then the account's TOTP challenge (AU-22) | the button leads to the code step as if Google had answered; no request leaves 127.0.0.1 (SS-03; SS-05); the button's drawing is the Owner's choice ([PROTOTYPE_PLAN](PROTOTYPE_PLAN.md)) | the sequence after Google is the design's | — |
| SIM-04 | Session, expiry and re-login | server sessions with a 60-minute idle and a 12-hour absolute limit, the idle warning and the in-place re-login (AU-06–AU-08; IP-11) | no timer runs: the idle warning and the re-login are scripted states | the dialogs and their wording are the design's | — |
| SIM-05 | Permissions and company scope | the server decides every action and field by the account's grants and companies ([PERMISSIONS_MATRIX](../../05-security/PERMISSIONS_MATRIX.md#73-special-projections)) | each page set renders one persona's grants — an action the persona lacks is absent from the markup —; [SP-19](../../00-governance/DECISION_LOG.md#dir-063-dir-064-obs-024-and-tech-032--p7-ux-re-baseline-s2-0-acceptance-s2-1-authorization-baseline-and-stage-2-documents)'s variant of Bayu without `stock.reserve` is a scripted scenario for S2-R06 | the participant sees exactly what that persona's grants show; enforcement is P8's to test | — |
| SIM-06 | Numbering | the next available number of the company, type and business-date period, drawn at issue ([BR-DOC-02](../../02-domain/BUSINESS_RULES.md#smdocument-all-doc-0114-invoice-is-a-specialization); [NM-03](../../06-api-performance/CONCURRENCY_IDEMPOTENCY/s14-17-numbering-jobs-revocation.md#14-numbering-concurrency)) | a fixed number of the synthetic story appears after a simulated issue (SP-20) | the number's place and format are the design's | "Simulasi" beside every issued number |
| SIM-07 | Command identity and outcomes | one `command_id` per intent; COMMITTED, REJECTED, CONFLICT and FAILED, and the unknown outcome (IP-01–IP-04) | no request is sent; COMMITTED is the scripted answer, the others scripted states where a journey shows them | the messages and the screen's behaviour around them are the design's | — |
| SIM-08 | Stock posting | receipts, reservations and their guards in one transaction ([AX-04](../../03-workflows/WORKFLOWS/s08-09-corrections-indivisible.md#9-indivisible-business-actions); BR-RSV-01–BR-RSV-03) | figures change only within the page's script; nothing persists after the page closes | S2-J05's rules S2-R01–S2-R09 are shown as the design states them | — |
| SIM-09 | Finance posting | invoice issue within the project cap; a payment and its same-command applications (AX-16; AX-18; SF-APPLY) | scripted, every amount checked by arithmetic in the synthetic data (SP-20) | the figures are consistent and the rejections the journeys show are the rules' | — |
| SIM-10 | PDF and print | the rendition produced by the background job from the issued snapshot (SF-RENDER; JB-01); a draft printed with its marking (WF-DOC-01) | no PDF exists: the rendition's states are scripted, and "Unduh" or "Cetak" of an issued document shows a scripted notice; the draft's paper is composed in the page, as option A of DOCUMENT_PREVIEW_PRODUCER describes | the composer and the states are the design's | "Simulasi" on the rendition's state |
| SIM-11 | Background and derived work | derived states and signals after commit — *PDF sedang dibuat*, *PDF siap*; QS-03's row for a skipped reservation offer (IP-12; S2-R08) | scripted steps of the same page set | the participant sees the states the design defines | as SIM-10 |
| SIM-12 | The scanner | a keyboard-wedge scanner typing into the scan field; the exact lookup; a stale scan answered as CONFLICT (IP-15; [PT-20](../DESIGN_SYSTEM.md#93-forms-and-entry)) | a typed code, or an attached scanner's, resolves against the page's synthetic products; no camera (V1 promises no camera scanning) | the field, its states and its answers are the design's | — |
| SIM-13 | The clock and the business date | the server's day in WIB; the transaction date's rules (BR-DT-06; IP-07) | one fixed day in WIB for the whole story, set with the synthetic data in Part 6 | dates read consistently across the six journeys | — |
| SIM-14 | The language preference | before sign-in a device-local choice, after sign-in the account's (C-1; [GAP-056](../../00-governance/GAP_REGISTER.md#triage)) | the address segment `/id/` or `/en/`; nothing kept ([LOCALIZATION_ARCHITECTURE §4](LOCALIZATION_ARCHITECTURE.md#4-the-prototypes-mechanism-prototype-only)) | the switch and its effect are the design's | — |
| SIM-15 | Search and lookup | scoped, bounded server search, an exact code first (IP-17; [PF-19](../../06-api-performance/PERFORMANCE.md#5-search)) | results from the page's synthetic set, in the same order | the result groups and their order are the design's | — |
| SIM-16 | Uploads and evidence | the upload pipeline and its checks, then the command that cites the file (FL-01; FL-02; IP-16) | no file picker opens: choosing adds a synthetic file row with its states; no file of the participant is read or kept (SS-01; SS-07) | the file line, its limits and states are the design's | — |

## 3. What stays design-faithful

The flow order, the screens, the states, the confirmations and the messages ([SS-08](../evidence/P7_REBASELINE_GATE.md#stage-2--owner-decisions-dir-061)): each journey specification's steps follow [ADMIN_FLOW](../ADMIN_FLOW/README.md#admin-and-owner-flows--interaction-model)'s journeys and the written flows ([WRITTEN_FLOWS](WRITTEN_FLOWS.md)), its copy quotes the canonical texts, and its rules — S2-R01–S2-R09 for S2-J05, the application invariants for S2-J06 — are shown as their sources state them. Only the system effects of §2 are simulated, and only as §2 says.

## 4. The marker, the panel and the out-of-scope page

**The marker.** "PROTOTIPE · data contoh" — proposed English "PROTOTYPE · sample data" — is a permanent badge in the reference's pill, info tone, outside [OC-06](../../00-governance/DECISION_LOG.md#dir-055-dir-056-obs-020-and-tech-028--p7-ux-re-baseline-checkpoint-c1-decision-rework-authorization-baseline-and-rework)'s copy budget ([SS-08](../evidence/P7_REBASELINE_GATE.md#stage-2--owner-decisions-dir-061)): in the shell's top bar and phone app bar on every page inside the shell, and at the top of each page outside it, PG-001 and PG-002 among them, so that no page could pass for a real sign-in. It is a button that opens the panel.

**The panel "Tentang prototipe ini".** A dialog — a sheet on phones — drafted in both languages, each page set showing its own and the language switch applying to it as to every screen; proposed copy, for the Owner's validation at the preview (DIR-064 §11.3):

| Indonesian | English |
| --- | --- |
| **Tentang prototipe ini** | **About this prototype** |
| Ini prototipe desain MultipleCorp, bukan aplikasinya. | This is a design prototype of MultipleCorp, not the application. |
| Semua data adalah contoh. Tidak ada yang disimpan atau dikirim. | All data are examples. Nothing is saved or sent. |
| Disimulasikan: masuk dan verifikasi dua langkah, masuk dengan Google, hak akses, penomoran, pencatatan stok dan keuangan, pembuatan PDF, dan pemindai. | Simulated: sign-in and two-step verification, Google sign-in, permissions, numbering, stock and finance posting, PDF creation and the scanner. |
| Sesuai rancangan: urutan langkah, layar, status, konfirmasi, dan pesan. | As designed: the order of steps, the screens, states, confirmations and messages. |
| Tanda "Simulasi" menandai hasil yang di aplikasi dibuat oleh sistem, seperti nomor dokumen. | The "Simulation" mark flags what the application's system would produce, such as a document number. |
| Halaman di luar enam alur prototipe membuka halaman "Di luar cakupan prototipe". | Pages outside the prototype's six journeys open the page "Outside the prototype's scope". |
| Tutup | Close |

The chip reads "Simulasi", proposed English "Simulation". The moderator's opening explanation belongs to S2-3's usability kit and is not written here (SS-08).

**The out-of-scope page "Di luar cakupan prototipe".** One page for every destination outside S2-J01–S2-J06, so that R1's navigation entries stay visible and no control is dead (SD-05; AGENTS.md rule 12). Its proposed copy: the title "Di luar cakupan prototipe" — "Outside the prototype's scope"; one line, "Halaman ini ada di MultipleCorp, tetapi tidak termasuk dalam prototipe ini." — "This page exists in MultipleCorp but is not part of this prototype."; "Kembali" — "Back", to the page the participant came from; and the persona's start page, *Beranda* or *Pekerjaan*. It carries the marker like every page.

**What leads to it.** The 49 pages [IA_PAGE_INVENTORY](IA_PAGE_INVENTORY.md) marks "out-of-scope page" — PG-008–PG-010, PG-012–PG-015, PG-018–PG-021, PG-023–PG-041, PG-047–PG-065 —: every navigation entry, *Menu lengkap* item, "Buat" item, search result, work row and link of a journey screen whose destination is one of them opens the out-of-scope page. PG-003 and PG-005 have no entry in the prototype, and PG-004 appears only as S2-J01's PS-06 after a recovery code. Each journey specification's screen list names, per PS screen, every control that leads there.

## 5. Where each category appears

The PS screens of the six [journey specifications](journeys/S2-J01.md#4-the-screen-list), read from their trace matrices' "Simulated" column.

| SIM | PS screens |
| --- | --- |
| SIM-01 | PS-01, PS-02, PS-03 |
| SIM-02 | PS-03, PS-04, PS-05, PS-06, PS-07 |
| SIM-03 | PS-01, PS-07 |
| SIM-04 | none — the expiry, the idle warning and the re-login are documented only ([S2-J01 §7](journeys/S2-J01.md#7-out-of-scope-documented-only)) |
| SIM-05 | every screen, each page set rendering one persona's grants; the S2-R06 variant on PS-43 |
| SIM-06 | PS-22, PS-30, PS-41 |
| SIM-07 | PS-20, PS-22, PS-28, PS-30, PS-34, PS-36, PS-37, PS-41, PS-42, PS-43, PS-49, PS-50, PS-51, PS-53 |
| SIM-08 | PS-36, PS-37, PS-42, PS-43 |
| SIM-09 | PS-22, PS-49, PS-53 |
| SIM-10 | PS-19, PS-20, PS-23, PS-30, PS-41 |
| SIM-11 | PS-22, PS-23, PS-30, PS-41, PS-44 |
| SIM-12 | PS-32, PS-33, PS-37 |
| SIM-13 | every screen that shows a date or the business day — Monday, 05 Okt 2026 WIB |
| SIM-14 | every screen, in both page sets |
| SIM-15 | PS-25, PS-38 |
| SIM-16 | PS-27, PS-33, PS-34, PS-47, PS-52 |

## 6. The S2-2 build

How run S2-2 ([DIR-066](../../00-governance/DECISION_LOG.md#dir-065-dir-066-obs-025-and-tech-033--p7-ux-re-baseline-s2-2-decisions-authorization-baseline-and-journey-prototypes)) simulated where this register is silent or the build differs, each with its reason; the journeys' own notes are in each specification's §9.

- **The marker (§4)** also sits in the head of the full-height sheets of [S2-J05](journeys/S2-J05.md), which cover the phone's app bar, and on the phone it takes two lines, so that the page's title keeps its room (GR-33). The panel opens as an application dialog on the desktop and as a sheet on the phone, above any sheet already open, which closing the panel leaves as it was; beneath MSG-39's modal confirmation the marker stays shown and opens the panel once the confirmation is closed.
- **The out-of-scope page (§4)** reached from PS-06 has no shell and offers "Kembali" only: no session exists there to give a start page.
- **SIM-07:** the server's answers are scripted texts of the story's figures — a confirmation, an answer, a rejection —; figures other than the story's still show them. A rejection (S2-R05; PS-50; PS-51) comes after a short simulated send, with the input kept.
- **SIM-10:** "Unduh" and "Cetak" of an issued document show the notice "Prototipe tidak membuat PDF." beside them.
- **SIM-15:** a lookup searches the page's synthetic set — an exact code first, then names beginning with the term, then similar ones; a short term matches by prefix only; at most 20 rows. A code outside the set gets no answer, an unknown code not being prototyped (S2-J05 §7).
- **SIM-16:** "Pilih File" adds the synthetic file's row with no file picker, shows an upload bar for 1.4 seconds — 0.3 seconds under reduced motion — with "Batalkan", then *Terunggah*; "Hapus file" returns the choice. No file of the participant is read or kept.

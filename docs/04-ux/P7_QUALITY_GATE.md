# P7 quality gate

Status: REVIEW | Updated: 2026-10-02 | Owner: Planning

This is the evidence record of P7 — UX, Information Architecture & Design System, authorized by [DIR-033](../00-governance/DECISION_LOG.md#dir-033-obs-012-and-tech-022--p7-authorization-entry-baseline-and-ux-documentation) (twenty-ninth source record). The rules live in [INFORMATION_ARCHITECTURE](INFORMATION_ARCHITECTURE.md), [ADMIN_FLOW](ADMIN_FLOW.md) and [DESIGN_SYSTEM](DESIGN_SYSTEM.md); this file records how they were derived, explored, reviewed and validated. It stays REVIEW as evidence, following the P2–P6 gate convention.

## DesainPakai readiness evidence

Recorded on 2026-10-01 before any brief was sent. Non-secret evidence only; no credential, token or configuration value was printed, stored or archived.

| Item | Evidence |
| --- | --- |
| CLI available | yes — `dpai --version` answers; it was already installed and was neither installed nor upgraded in this task |
| CLI version | 0.2.2 (the tool's own banner spells the product "DesainPakeAI"; the Owner's authorization calls it DesainPakai) |
| Authenticated | yes — `dpai auth status --pretty` reports an authenticated session; its key field was never displayed |
| Active project | **MULTIPLECORP** — `dpai project current --pretty`; confirmed, and never changed by this task |
| Context revision | `sha256-8d07ff4db9d87b9648fd5a05ddc2859debf22e7fa5b7252fc7d179951d4d0b73` — `dpai context --pretty` |
| Context retrieved | 2026-10-01 08:01 WIB, kept outside the repository |
| Setup step required | none |

**Tool context versus repository.** The project's tool context held a starter design system named "Ramp" (an acid-lime "decision signal", a chat-composer component, display sizes up to 56 px) and no page or product note. It is tool context only: where it differs from the repository — the Owner's anti AI-slop constitution, the ban on chat-style decoration, operational density — the repository wins, and nothing of it was adopted without passing the review of [the exploration protocol](#desainpakai-exploration).

**What the tool is.** The CLI offers no command that generates designs from a prompt. It is an authoring workspace: prototype pages are written into the active project under the tool's own design guides, compiled and verified by `dpai preview verify`, and exported for visual inspection. "Exploration" below therefore means bounded briefs turned into alternative prototypes in that workspace and reviewed against the repository; every command was run from a temporary directory outside the repository, and `git status` showed nothing new in the repository after each session.

**Re-check on 2026-10-02.** When the task resumed after an interruption, the readiness commands showed a different key bound to another project of the account as the active project. As the Owner's resume instruction requires, the DesainPakeAI-dependent work stopped, nothing was written to any project, and the mismatch was reported in Bahasa Indonesia; the Owner restored the setup and confirmed it in-session ("sudah"). Steps 1–6 were run again at 2026-10-02 17:05 WIB:

| Item | Evidence |
| --- | --- |
| CLI | 0.2.2, not reinstalled or upgraded |
| Authenticated | yes; a different key from the 2026-10-01 check, bound to the same project; its value was never displayed |
| Active project | **MULTIPLECORP**, the same project identifier as on 2026-10-01 |
| Context revision | `sha256-4756d3b2aa158007`, retrieved 2026-10-02 17:05 WIB |
| Setup step | the Owner's own, outside the repository; recorded here only as this note |

**Re-check after the usage-limit interruption (2026-10-02, about 21:00 WIB).** At the Owner's request the four read-only commands were run again before any further DesainPakeAI operation: `dpai --version` 0.2.2; `dpai auth status --pretty` authenticated, its key and credential path never displayed; `dpai project current --pretty` **MULTIPLECORP**, role owner, the same project identifier; `dpai context --pretty` retrieved, revision `sha256-06702c02ffaf388b`, the project holding the probe page and the exploration pages. No setup step was needed.

## GAP-034 settlement and ordering evidence

### Constraints verified first (DIR-033 §9.1)

| Constraint | Where | Finding |
| --- | --- | --- |
| The two items without a durable record | PERMISSIONS_MATRIX §10, DP-13, DP-18; PERFORMANCE signal register (QS-14 row) | a cross-scope or cross-family evidence match the uploader may not see, and a whole-batch collision outside the preparer's scope; row-level collisions already have their source — `import_rows` in state DUPLICATE |
| What the actor is shown | DP-13; DP-18 | nothing for the evidence match; only "not importable — Owner review" for the batch |
| What the queue is | PERMISSIONS_MATRIX §10 | a derived view, never a stored queue; only `review.view` opens it; the Owner acts only through normal commands |
| How security events are written | SECURITY LG-02, LG-05, LG-06 | outside business transactions, never rolling a command back; a field list with a whitelisted details object; visible to `security_log.view`; kept at least 12 months |
| Typed columns | DATABASE §17, §4.13 with its `TECH-020` clause | signals read typed columns, never `jsonb` |
| Where the lookups run | SECURITY FL-10; CONCURRENCY_IDEMPOTENCY FU-10, LK-28, BD-15 | outside the business transaction, under the registration keys |
| What a refusal commits | CONCURRENCY_IDEMPOTENCY D-CC-05, §6; ST-03 and SQ-23 the only precedents for further rows | a refusal commits its outcome and nothing else |
| Oracles | SECURITY TM-23, TM-27; H8-12 | the P8 proof of the duplicate-oracle and collision probes |
| Signal mechanics | PERFORMANCE signal register; IX-13; IX-14; QB-10 | typed sources and partial indexes |

### Options weighed (planner note PN-1)

| Option | Footprint | Outcome |
| --- | --- | --- |
| (a) Two security-event kinds in the LG-02 catalogue, with typed reference columns | four narrow clauses in approved documents; no new table, so the 124-table count of DATABASE, PERMISSIONS_MATRIX §7.2 and the handoff stays true | **adopted** — LG-02 already defines records written outside business transactions with typed fields and Owner-only visibility; typed columns can be added narrowly |
| (b) An Owner-only marker record owned by DATABASE | a 125th table, a new projection row and every count that cites 124 | rejected: larger footprint for the same guarantees |
| (c) A derived Owner-only view over evidence hashes and identities | no approved change | rejected as the mechanism: a refused batch collision leaves nothing to derive, and the cross-company window query would be a new signal of its own; it stays the fallback that makes a lost evidence event recoverable (residual below) |

### The settlement (DIR-033 §9.2)

| Item | Settlement |
| --- | --- |
| a) Record shape | `security_events` kinds EVIDENCE_DUPLICATE_UNDISCLOSED and IMPORT_COLLISION_UNDISCLOSED with the typed columns `command_id`, `target_entity_type` and target public id, `related_entity_type`, `related_public_id`, `company_id`, `related_company_id`, `reason_code` naming the match basis and `user_id` the account; plain values without foreign keys; the batch's declared kind, cutoff and company scope only in the whitelisted details, for display; LG-03 redaction — no file contents or payload (SECURITY LG-02 `TECH-022`; DATABASE §4.13 `TECH-022`) |
| b) Lifecycle and retention | notices without a state of their own; kept at least 12 months (LG-06); in the queue they age within the chosen window; no review state exists, so nothing can gate, block or alter a business fact or command |
| c) Entry into QS-14 | the QS-14 row of the PERFORMANCE signal register reads both kinds in the statement that reads AUTHORIZATION_ANOMALY; the partial index of IX-14 over that kind is extended to the two kinds (PF-10 literal list); budget: SCR-07 is a list of the QB-12 class and Beranda's G1 count is one bounded statement over the security events ([INFORMATION_ARCHITECTURE §9](INFORMATION_ARCHITECTURE.md#9-dashboards-and-queues)) |
| d) Write path | the evidence-registration request (NX-07) writes its event after its command has COMMITTED; the import-preparation request writes its event after its refusal is recorded; both outside the command's transaction, after the response is sent where the runtime offers such a step and otherwise as the request's last statement; one insert that references no row, with `ON CONFLICT DO NOTHING` on the partial UNIQUE key, so it takes no business lock and none out of order. A failed write changes no outcome; it is logged with the correlation id (LG-04) |
| e) Visibility | Owner only — `review.view` in the queue and `security_log.view` in the log; never among an account's own events (PJ-17), on a record's timeline (PJ-11), in an Admin's list, search, export or notification, or among the alerts of LG-07; no Admin view lists these kinds — an account sees only its own last login and credential events (LG-05; PJ-17) |
| f) No oracle | the response's content and timing class are the same with and without a hidden match, and nothing the uploader or preparer sees later changes — no flag, note, reason, bucket or count — with one residual of two approved rules: an upload reproducing a rendition the uploader may not see can never satisfy a client original (FS-12), so a requirement it was linked to stays open in its ordinary state (recorded in GAP-034; R1-02 below); the batch answer is exactly DP-18's sentence under a neutral face (ADMIN_FLOW MSG-18, IP-16; UXS-18, UXS-19) |
| g) Outcomes | unchanged: the evidence registration commits as it would without the match; the batch preparation is refused as SQ-08 and DP-18 prescribe |
| h) Transactions and concurrency | the command envelope of CONCURRENCY_IDEMPOTENCY §6 is unchanged: the refusal still commits its outcome and nothing else (D-CC-05), unlike ST-03 and SQ-23, whose rows commit with the outcome; no envelope clause is needed; the HO-38 status clause records this |
| i) P8 proof | ADMIN_FLOW UXH-04, extending SECURITY H8-12: one event per command and matched record; absent from every Admin path and from the account's own events; identical responses with and without a hidden match; the failed-write case |
| j) GAP-034 | stays OPEN with its P7 continuation note: design settled, runtime proof in P8 |

**Residual (recorded in GAP-034).** If the event's insert fails, or the request dies between the durable outcome and the insert, the item is missing from the queue. An evidence duplicate stays derivable from the stored content hashes and identities (option c); a refused batch collision is then recoverable only from the technical log.

### Amendments and their pre-amendment hashes

Each is a narrow Level-1 clause marked `TECH-022`, with an amendment note after the document's Approval line; each document keeps APPROVED status, and its amended revision is approved only through APPR-008.

| Document | Clauses | Pre-amendment SHA-256 — equal to its latest approval record and to the committed blob |
| --- | --- | --- |
| SECURITY | the amendment note; LG-02 — the two kinds, their typed columns, write path, visibility and residual; FL-10 — a pointer to the first kind | `C93EEAB5370ADA91A1B6EE0292BB7788B3B3F9B52A98C7009C82FB5EC42E40D1` (APPR-006) |
| DATABASE | one sentence in the amendment note; §4.13 `security_events` — the typed columns and the partial UNIQUE key of the two kinds | `0CA6FF557035D2ABDFDF3147CEE98D18DE6509819C4AD9E65D0D8317DEDAA207` (APPR-007) |
| PERFORMANCE | the amendment note; the QS-14 row of the signal register; IX-14 | `50A68EEDCE34DA1CEAC000E4E1D8272211CE3E83C4C050768CA8F2E66EFEAB8D` (APPR-007) |
| CONCURRENCY_IDEMPOTENCY | the amendment note; the HO-38 status clause | `07422AAD6643CD9534B5EC8115994D7437DAF46D80DE7DD2565AB2D05F966FC3` (APPR-007) |

PERMISSIONS_MATRIX is not amended: its section 10, DP-13 and DP-18 already say that the items are listed for the Owner, and SECURITY FL-10 now names the record. None of the settlement's conditions of DIR-033 §9.6 arose: nobody sees or does anything new, DP-13 and DP-18 answer as before, no warning became a block or disappeared, and no product or domain document changed.

### Ordering evidence (DIR-033 §9.5)

| Time (WIB) | Event |
| --- | --- |
| 2026-10-01 08:17 | the four `TECH-022` amendments written (file times 08:17:18) and GAP-034's P7 continuation note added — the settlement complete and recorded |
| 2026-10-01 08:23 | the first DesainPakeAI brief, DPB-01, written |
| 2026-10-01 08:34 | the brief on the Owner review queue, DPB-15, written — after the settlement |
| 2026-10-02 17:32 | DPB-15 sent to DesainPakeAI — its pages created (DPS-09) and authored by 17:55 (DPS-12); the queue's presentation designed in [INFORMATION_ARCHITECTURE §9.2](INFORMATION_ARCHITECTURE.md#92-the-owner-review-queue-scr-07) |

## Planner-note dispositions

DIR-033 §14 notes are analysis, never Owner intent; each was verified against the repository.

| Note | Disposition |
| --- | --- |
| PN-1 GAP-034 candidates | (a) adopted after verifying that LG-02 can carry the typed columns narrowly; (b) and (c) rejected as above |
| PN-2 the queue's growth; a "reviewed" marker | a default seven-day window and filters adopted ([INFORMATION_ARCHITECTURE §9.2](INFORMATION_ARCHITECTURE.md#92-the-owner-review-queue-scr-07)); no stored marker: the divider "since your previous sign-in" is derived from the Owner's own last successful sign-in (AU-13), so no durable record and no gating exists |
| PN-3 in-place re-authentication | adopted after reading the Inertia v3 documentation: a 401 or 419 answer can be handled without leaving the page; history and caches are cleared at once when it arrives and again after the password is accepted; a full document load only at the absolute limit and on logout, a remote termination handled in place under the opaque dialog (D-UX-03); the survival of in-memory input is inferred, so P8 proves it and the fallback warns before any loss ([ADMIN_FLOW §6](ADMIN_FLOW.md#6-session-expiry-re-authentication-and-unsent-input); UXH-02) |
| PN-4 invoice list sort and filter | the need was checked against the workflows: Piutang work sorts by the receivable guard's current due date (IX-11), invoices are found by exact number and by client (PF-42), and period browsing belongs to the sales report (PF-27) — no channel amendment ([INFORMATION_ARCHITECTURE §8.5](INFORMATION_ARCHITECTURE.md#85-finance)) |
| PN-5 a compact business-date element | adopted as PT-25 "Tanggal transaksi" |
| PN-6 an Owner readiness indicator for C-10 | adopted as a derived readiness line, without a new record ([ADMIN_FLOW §12.3](ADMIN_FLOW.md#123-the-c-10-refusal-for-an-admin)) |
| PN-7 one density | adopted ([DESIGN_SYSTEM §5.3](DESIGN_SYSTEM.md#53-spacing-sizing-and-density)) |
| PN-8 scope discipline | adopted: patterns first, then the screen inventory; fields are referenced to their owners |
| PN-9 deadline | GAP-014 stays open; no scope cut was made or proposed |
| PN-10 DesainPakeAI efficiency | adopted: the design-system direction (DPB-01) first, then the shell (DPB-02) in parallel with the remaining briefs, which design the content area under the adopted tokens; related surfaces grouped in shared briefs; alternatives spent on the twelve major surfaces |

## Section 12 channel

Not used. The one known candidate — sorting or filtering invoice lists by date or issued number — is served by existing structures (PN-4), and no other screen needed a sort or filter key outside PF-42, IX-11, IX-13 and IX-14: where a filter has no registered index it uses PF-42's own bounded form, and the Owner review queue (SCR-07) and SCR-45 show one source or kind per tab, each on a registered key ending in `id` — IX-13 for audit events, otherwise `id` itself (PF-13) — instead of merging several tables into one keyset list ([INFORMATION_ARCHITECTURE §8](INFORMATION_ARCHITECTURE.md#8-screen-inventory)).

## DesainPakai exploration

### Method

The loop of DIR-033 §11.5 ran for every surface of §11.3, through bounded briefs:

1. **Constraints** — the derived requirements UXN, UXI and UXV of the three P7 documents, each citing its repository source.
2. **Brief** — one bounded brief per surface group (table below), stating only that group's users, capabilities, context tag, workflows, projection limits, states, PERFORMANCE limits, H7 rules, glossary terms, phone and desktop expectations, the visual direction and a "must not change" list; synthetic data only (CV Arunika Jaya, PT Bentara Niaga, CV Cakra Persada and fictitious clients); no credential, personal data or specification text beyond the constraints.
3. **Exploration** — alternatives authored as prototype pages in the DesainPakeAI workspace of the MULTIPLECORP project under the tool's own guides (workspace authoring, design quality, variant exploration, accessibility, layout, typography, colour, product writing, motion, prototype interactions, interface review), each set of variants on one named axis, compiled and checked by `dpai preview verify`. Design sessions worked only in a temporary directory outside the repository.
4. **Review** — every alternative checked against the repository (§11.7): semantic fidelity first, then projection and oracles, PERFORMANCE, H7; the compliant ones compared on V1_SCOPE's priorities, phone and desktop fitness, accessibility, anti AI-slop, Indonesian copy, feasibility on shadcn/ui and consistency with what was already adopted.
5. **Selection and normalization** — the strongest compliant direction rewritten as MultipleCorp specification in the owning P7 file, never as tool output; conflicts resolved toward one pattern per need, and every conflict with the repository resolved in the repository's favour.
6. **Final consistency and anti AI-slop review** — DPB-18, over the normalized design ([below](#final-consistency-and-anti-ai-slop-review-dpb-18)).

Tool output — page sources, exports, captures — stayed in the temporary directory; nothing generated by the tool entered the repository, and `git status` showed no new path after each session.

**Visual inspection and the export limit.** `dpai export` builds one prototype file holding every page of the project. Once the project held more than a few briefs' pages that file exceeded the CLI's file-read limit of 512 KB (575 KB at 17:55 WIB, 1.25 MB by 18:10 WIB on 2026-10-02, about 2.2 MB with all pages), so it could no longer be read back. Every page was therefore still compiled and verified by the tool (`dpai preview verify`: compile passed, no diagnostics), and its visual review used a local reconstruction of the compiled page — the same scoped stylesheet, content slot and page script the compiler emits, rendered by headless Chrome at 1,440 px and 390 px from the temporary directory. The reconstruction is a faithful wrapper of the verified source, not a second design; it does not reproduce the tool's own canvas, which is why canvas rendering is listed among the things P8 checks on real screens (UXH-05, UXH-06).

**Interruption and recovery.** On 2026-10-02 at about 18:25 WIB the design sessions stopped at a usage limit. On resumption the existing results were recovered rather than regenerated: each page's remote source was compared with its local source (read-only), finished local sources of six briefs — DPB-06, DPB-10, DPB-12, DPB-13, DPB-16 and DPB-17 — were uploaded and verified (fourteen pages, DPS-22), a build glitch in DPB-17 — section markers left in the page — was corrected, and only DPB-08, whose pages had been created but never authored, was run again.

### Briefs

| Brief | Surfaces (DIR-033 §11.3) | Major surface | Variants authored |
| --- | --- | --- | --- |
| DPB-01 | 35 design-system exploration · 37 typography · 38 spacing · 39 density · 40 layout · 41 visual hierarchy · 45 visual consistency | — | 3: A "Tinta", B "Kertas", C "Batu"; the tool's starter design "Ramp" evaluated as a fourth baseline |
| DPB-02 | 1 information architecture alternatives · 2 shell and global navigation · 3 desktop sidebar and top bar · 4 phone navigation · 7 company context | A1 | 3: A "Alur kerja", B "Pusat proyek", C "Bilah atas" |
| DPB-03 | 5 Owner dashboard | A2 | 3: A "Daftar kerja", B "Matriks perusahaan", C "Dua kolom" |
| DPB-04 | 6 Admin dashboard (and 7, the company labels) | A3 | 3: A "Per jenis pekerjaan", B "Per perusahaan", C "Satu daftar" |
| DPB-05 | 8 project workspace · 16 quotation | A4 | 3: A "Tab", B "Rel alur", C "Satu halaman" |
| DPB-06 | 9 client and supplier areas · 10 product and catalog · 27 forms · 28 dense operational tables | — | 2: A "Halaman", B "Panel samping" |
| DPB-07 | 11 purchasing · 12 receiving (desk) | A5 | 3: A "Dua panel", B "Tabel tunggal", C "Bertahap" |
| DPB-08 | 13 inventory | A6 | 3: A "Per produk", B "Daftar–detail", C "Per rak" |
| DPB-09 | 14 barcode and scanner · 44 phone operational workflows · 12 and 15 on the phone | A12 | 3: A "Langkah", B "Daftar centang", C "Pindai dulu" |
| DPB-10 | 15 dispatch and delivery (desk) | — | 2: A "Dua layar", B "Per kiriman" |
| DPB-11 | 17 documents and SPJ | A7 | 3: A "Daftar periksa", B "Per dokumen", C "Dua panel" |
| DPB-12 | 18 invoice · 19 receivables · 20 payment | A8 | 3: A "Tabel umur piutang", B "Per invoice", C "Per klien" |
| DPB-13 | 21 reports · 23 audit and history | — | 2: A "Ringkasan di atas", B "Panel filter samping" |
| DPB-14 | 22 settings · 25 numbering start and seed · 26 company onboarding | A10, A11 | numbering 3: A "Matriks", B "Per jenis", C "Daftar kesiapan"; onboarding 2: D "Daftar kesiapan", E "Urutan langkah"; grants 1: F "Pengguna dan akses" |
| DPB-15 | 24 Owner review queue — written after the GAP-034 settlement | A9 | 3: A "Satu alur", B "Dua bagian", C "Per pelaku atau catatan" |
| DPB-16 | 30 loading, empty, error, denied · 31 the five answers and the non-outcome answers · 32 stale state · 33 correction and reversal · 34 confirmation and destructive actions | — | 3: A "Banner di atas formulir", B "Toast", C "Di dekat tombol" |
| DPB-17 | 29 responsive patterns · 36 shadcn/ui composition · 42 accessibility · 43 keyboard-efficient desktop | — | 2: A "Tabel baris", B "Baris + panel" |
| DPB-18 | 46 anti AI-slop review · 45 visual consistency — over the normalized design | — | the final review of [its own section](#final-consistency-and-anti-ai-slop-review-dpb-18) |

All forty-six surfaces are covered and none was dropped. Variant counts: 50 alternatives across seventeen briefs, plus the tool's starter design as a baseline; every page compiled with no diagnostic.

### Traceability

One row per surface of DIR-033 §11.3. IA = INFORMATION_ARCHITECTURE, AF = ADMIN_FLOW, DS = DESIGN_SYSTEM.

| # | Surface | Repository requirements | UX requirements | Brief | Alternatives explored | Adopted · rejected (conflicts found) | Decision | Section |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | Information architecture alternatives | OB §11; CAP-01–CAP-18; WORKFLOWS §5 | UXN-01–UXN-03, UXN-26 | DPB-02 | 3 — "Alur kerja", "Pusat proyek", "Bilah atas" | adopted the business-flow areas of "Alur kerja"; rejected "Pusat proyek" — purchases, deliveries and documents as Proyek tabs imply every purchase is project-bound, against WF-PUR-01's purchases for stock (conflict, repository kept) — and "Bilah atas" | D-IA-01 | [IA §5](INFORMATION_ARCHITECTURE.md#5-product-map) |
| 2 | Application shell and global navigation | OB §11, §23; H7-03; H7-10 | UXN-03, UXN-09, UXN-18 | DPB-02 | 3 — as row 1 | adopted sidebar, slim top bar and notice strip; rejected the inline session notice and navigation badges | D-IA-05 | [IA §6](INFORMATION_ARCHITECTURE.md#6-application-shell-and-global-navigation) |
| 3 | Desktop sidebar and top bar | OB §23; KB-03 | UXN-22 | DPB-02 | 3 — as row 1 | adopted the 232 px sidebar with icon rail; rejected the horizontal menu that drops to a drawer below 1,240 px | D-IA-01 | [IA §6.1](INFORMATION_ARCHITECTURE.md#61-desktop-and-tablet) |
| 4 | Mobile navigation | V1_SCOPE language and experience contract; AC-14; GAP-020 | UXN-04, UXN-21 | DPB-02 | 3 — five-tab bar, bar with a centre scan, drawer only | adopted the five-tab bar; rejected the drawer-only pattern and a duplicate scan entry | D-IA-03 | [IA §6.2](INFORMATION_ARCHITECTURE.md#62-phone) |
| 5 | Owner dashboard | CAP-17; REF-007; QS-01–QS-22; PF-24; CS-08 | UXN-05, UXN-07, UXN-12, UXN-13 | DPB-03 | 3 — "Daftar kerja", "Matriks perusahaan", "Dua kolom" | adopted stacked signal groups; rejected the matrix (fourteen of twenty signals hidden) and two columns; the "oldest item" reference dropped — an order the signal register does not give (conflict) | D-IA-07 | [IA §9](INFORMATION_ARCHITECTURE.md#9-dashboards-and-queues) |
| 6 | Admin dashboard | REF-008; QS-01–QS-21; PF-15; PF-34 | UXN-05, UXN-12 | DPB-04 | 3 — "Per jenis pekerjaan", "Per perusahaan", "Satu daftar" | adopted grouping by kind of work; rejected per-company columns and one merged list ordered across signals (conflict with PF-24) | D-IA-08 | [IA §9](INFORMATION_ARCHITECTURE.md#9-dashboards-and-queues) |
| 7 | Company context and switching | WF-ACC-01; CS-01–CS-08; PJ-04; H7-02 | UXN-06–UXN-08 | DPB-02, DPB-04 | 3 — global filter, per-list filter, filter beside the breadcrumb | adopted the per-list filter; rejected the global filter, which reads as a workspace switch (CS-03), and a company badge in the shell; relation marker kept as plain text | D-IA-02 | [IA §7](INFORMATION_ARCHITECTURE.md#7-company-context-model) |
| 8 | Project workspace | OB §11; CAP-04; AC-04; QB-02 | UXN-02, UXN-14 | DPB-05 | 3 — "Tab", "Rel alur", "Satu halaman" | adopted tabs with a derived part strip on desktop and the drill-in part list on phones; rejected the rail and the one long page; an "estimated HPP" figure removed — no such calculation (conflict) | D-IA-09 | [IA §8.2](INFORMATION_ARCHITECTURE.md#82-projects-and-quotations) |
| 9 | Client and supplier areas | WF-MD-02; WF-MD-03; PJ-10; CS-06 | UXN-16, UXN-23 | DPB-06 | 2 — "Halaman", "Panel samping" | adopted addressable pages and search-first client creation; rejected the side panel | D-IA-14 | [IA §8.6](INFORMATION_ARCHITECTURE.md#86-reports-master-data-settings-and-owner-areas) |
| 10 | Product and catalog | WF-MD-04; PJ-10; PF-31; PF-32 | UXN-15, UXN-16 | DPB-06 | 2 — as row 9 | adopted the search-first list with thumbnails; per-company stock column, "stock at" filter and name sort removed — not keys of SCR-52 (conflict with PF-42) | D-IA-14 | [IA §8.6](INFORMATION_ARCHITECTURE.md#86-reports-master-data-settings-and-owner-areas) |
| 11 | Purchasing | WF-PUR-01; SF-CHARGE; BR-INV-04 | UXI-11, UXI-13, UXI-14 | DPB-07 | 3 — "Dua panel", "Tabel tunggal", "Bertahap" | adopted one line table with line details under the row and the charges table below; rejected the summary column and stepped sections | D-DS-07 | [AF §10.3](ADMIN_FLOW.md#103-inventory-and-purchasing) |
| 12 | Receiving | WF-INV-01; AX-04; BD-01; L-13 | UXI-02, UXI-23, UXI-33 | DPB-07, DPB-09 | 3 desk + 3 phone | adopted the shared receiving form and, on phones, the line checklist; the reservation offered as a later step corrected to AX-04's same-action reservation (conflict, repository kept) | D-UX-12 | [AF §10.3](ADMIN_FLOW.md#103-inventory-and-purchasing) |
| 13 | Inventory | CALC-01; BR-INV-01; PJ-01–PJ-04; PJ-07; PJ-22 | UXN-07, UXN-08, UXN-15, UXN-17; UXI-28 | DPB-08 | 3 — "Per produk", "Daftar–detail", "Per rak" | adopted the product list with the four quantities and expandable rows, and the detail pane's content as the product's detail page; rejected the rack-first organisation; Sekar's views showed no other company's identity, acquisition or cost and listed location moves of other companies' lots as pool movements, as PJ-03 allows | D-IA-17 | [IA §8.3](INFORMATION_ARCHITECTURE.md#83-purchasing-and-warehouse) |
| 14 | Barcode and scanner interaction | SF-SCAN; QB-06; AC-03; SH-02; WS-08 | UXN-17; UXI-33, UXI-34 | DPB-09 | 3 — "Langkah", "Daftar centang", "Pindai dulu" | adopted the bottom-fixed scan field in a line checklist; rejected the five-step stepper and the custom keypad; a scan typed into another field is rejected and restored, not used | D-DS-08 | [AF IP-15](ADMIN_FLOW.md#ip-15--scanner) |
| 15 | Dispatch and delivery | WF-INV-03; WF-FUL-02; AX-03; PJ-01 | UXI-23, UXI-35 | DPB-09, DPB-10 | 3 phone + 2 desk — "Dua layar", "Per kiriman" | adopted two linked screens; rejected one shipment stepper that merges a stock record and a client record | D-IA-16 | [AF §10.4](ADMIN_FLOW.md#104-fulfilment) |
| 16 | Quotation | WF-QUO-01; L-25; DOC-01 | UXI-11, UXI-23, UXI-29 | DPB-05 | 3 — as row 8 | adopted revisions inside the project's *Penawaran* part with the irreversible issue confirmation and the client's answer with evidence or reason | D-IA-09 | [AF §10.2](ADMIN_FLOW.md#102-project-and-commercial) |
| 17 | Documents and SPJ | WF-DOC-01; WF-DOC-02; BR-ADM-01; FL-04; FL-07 | UXI-27, UXI-29; UXN-20 | DPB-11 | 3 — "Daftar periksa", "Per dokumen", "Dua panel" | adopted the checklist for *Dokumen Administrasi* and type grouping with version chains for *Dokumen*; rejected the two-panel layout and a row count | D-IA-10 | [AF §10.5](ADMIN_FLOW.md#105-documents-and-administration) |
| 18 | Invoice | WF-FIN-01; WF-FIN-02; AX-16 | UXI-11, UXI-25 | DPB-12 | 3 — "Tabel umur piutang", "Per invoice", "Per klien" | adopted the list with the invoice detail and position block beside it | D-IA-15 | [AF §10.6](ADMIN_FLOW.md#106-finance) |
| 19 | Receivables | BR-FIN-02; BR-FIN-04; IX-11; CS-08 | UXN-07, UXN-15 | DPB-12 | 3 — as row 18 | rejected aging buckets with counts above the list (counts across companies for an Admin, PF-15) and a running-balance client statement (no CALC defines it) | D-IA-15 | [IA §8.5](INFORMATION_ARCHITECTURE.md#85-finance) |
| 20 | Payment | WF-FIN-03; SF-APPLY; BD-02 | UXI-07, UXI-13 | DPB-12, DPB-16 | 3 — as row 18, and the outcome variants of row 31 | adopted the payment form with its outcome message beside the action bar | D-DS-06 | [AF §10.6](ADMIN_FLOW.md#106-finance) |
| 21 | Reports | CAP-11; PF-27; CS-08; JB-03 | UXN-07, UXN-20; UXI-09 | DPB-13 | 2 — "Ringkasan di atas", "Panel filter samping" | adopted per-company sections with their summary figures; rejected the side filter panel and a whole-report total under a continued list; an export lifetime not in the repository dropped (conflict) | D-IA-12 | [IA §8.6](INFORMATION_ARCHITECTURE.md#86-reports-master-data-settings-and-owner-areas) |
| 22 | Settings | OD-01–OD-14; H7-06; RG-01–RG-07 | UXN-18; UXI-30 | DPB-14 | 6 — numbering A–C, onboarding D–E, grants F | adopted the readiness-grouped numbering area, the derived onboarding list and the grant preview with change marks; grants explored once — the content is fixed by PERMISSIONS_MATRIX and H7-06 | D-DS-09 | [AF §12](ADMIN_FLOW.md#12-numbering-and-company-onboarding) |
| 23 | Audit and history | PJ-11; RD-10; BR-XC-01; BR-CR-02 | UXN-24; UXI-25, UXI-36 | DPB-13 | 2 — as row 21 | adopted the record timeline with both dates, reasons and two-way correction links; a sentence implying one payment applied to another company's invoice dropped — not a V1 fact (conflict) | D-IA-12 | [DS §9.4](DESIGN_SYSTEM.md#94-actions-overlays-and-feedback) |
| 24 | Owner review queue | PERMISSIONS_MATRIX §10; QS-14; HO-38; LG-02 (`TECH-022`) | UXN-12, UXN-25; UXI-08, UXI-15 | DPB-15 | 3 — "Satu alur", "Dua bagian", "Per pelaku atau catatan" | adopted open conditions above a chronological stream; rejected condition chips and grouping by actor or record | D-IA-11 | [IA §9.2](INFORMATION_ARCHITECTURE.md#92-the-owner-review-queue-scr-07) |
| 25 | Numbering start and seed | NM-03; HO-39; C-10; NX-01 | UXI-30 | DPB-14 | 3 — "Matriks", "Per jenis", "Daftar kesiapan" | adopted readiness grouping with per-type details; rejected the company-by-type matrix and per-type tabs; statements kept in ADMIN_FLOW's HO-39 wording | D-IA-13 | [AF §12.2](ADMIN_FLOW.md#122-start-seed-and-scheme-commands) |
| 26 | Company onboarding | WF-MD-01; BR-ACC-03; NM-03 | UXI-31, UXI-37 | DPB-14 | 2 — "Daftar kesiapan", "Urutan langkah" | adopted the derived readiness list, missing items first; rejected ordered steps that imply a mandatory sequence | D-IA-13 | [AF §12.4](ADMIN_FLOW.md#124-company-onboarding) |
| 27 | Forms | AC-14; WCAG 3.3.1; HO-08 | UXI-13, UXI-14, UXI-38 | DPB-06, DPB-07 | 2 + 3 | adopted labels above fields, paired fields, a sticky action bar and errors at the field; one-column phone forms | D-DS-07 | [DS §9.3](DESIGN_SYSTEM.md#93-forms-and-entry) |
| 28 | Dense operational tables | PF-13–PF-17; AC-14 | UXN-15; UXV-06 | DPB-06 | 2 — as row 9 | adopted 36 px rows, hairline rules, right-aligned tabular figures, "Muat Lagi"; no column resizing or selection | D-DS-03 | [DS §9.2](DESIGN_SYSTEM.md#92-lists-tables-and-filters) |
| 29 | Responsive patterns | V1_SCOPE P7 handoff; OB §22 | UXN-04; UXV-06, UXV-11 | DPB-17 | 2 — "Tabel baris", "Baris + panel" | adopted the responsive pattern matrix; rejected inline line cells on phones | D-DS-07 | [DS §11](DESIGN_SYSTEM.md#11-responsive-pattern-matrix) |
| 30 | Loading, empty, error and denied states | AZ-11; WS-10; PF-34 | UXN-10; UXV-17 | DPB-16, DPB-03, DPB-04 | 3 — as row 31, with each group's loading, empty and error states | adopted skeletons per deferred group, one-sentence empty states and per-group errors with a reference code | D-DS-06 | [DS §12.3](DESIGN_SYSTEM.md#123-states-of-a-screen) |
| 31 | The five answers and the non-outcome answers | HO-01–HO-07; CI-05; RY-07 | UXI-02–UXI-07; UXV-17 | DPB-16 | 3 — "Banner di atas formulir", "Toast", "Di dekat tombol" | adopted the message above the action bar; rejected the top banner and the toast | D-DS-06 | [DS §12.2](DESIGN_SYSTEM.md#122-outcome-presentation) |
| 32 | Stale-state handling | HO-04; ST-01–ST-09 | UXI-06, UXI-18 | DPB-16 | 3 — as row 31 | adopted the inline current-versus-mine comparison with "Terapkan Ulang pada Versi Terbaru" and frozen inputs while an outcome is unknown | D-UX-02 | [AF IP-03](ADMIN_FLOW.md#ip-03--conflict-and-stale-state) |
| 33 | Correction and reversal flows | WORKFLOWS §8; BR-CR-01; BR-CR-02; AC-12 | UXI-36, UXI-25; UXV-20 | DPB-16 | 3 — as row 31 | adopted the confirmation stating what is reversed and what stays, with the reason in the same step | D-UX-10 | [AF §11](ADMIN_FLOW.md#11-corrections-and-reversals) |
| 34 | Confirmation and destructive actions | HO-39; AU-15; H7-09 | UXI-23, UXI-21; UXV-20 | DPB-16 | 3 — as row 31 | adopted the AlertDialog confirmation with the action's verb and the step-up dialog | D-UX-10 | [DS §9.4](DESIGN_SYSTEM.md#94-actions-overlays-and-feedback) |
| 35 | Design-system exploration | OB §20, §21; REF-054; REF-055 | UXV-01–UXV-05 | DPB-01 | 3 + the starter baseline — "Tinta", "Kertas", "Batu", "Ramp" | adopted "Tinta"; rejected "Kertas", "Batu" and the starter design (acid lime at 1.23:1, 19 lint findings, a chat composer) | D-DS-01 | [DS §5](DESIGN_SYSTEM.md#5-tokens) |
| 36 | shadcn/ui component composition | OB §20; ARCHITECTURE §10; V1_SCOPE P7 handoff | UXV-01 | DPB-17 | 2 — as row 29 | adopted the composition map: primitives without business logic, feature components receiving server decisions | D-DS-07 | [DS §10](DESIGN_SYSTEM.md#10-component-composition) |
| 37 | Typography | OB §21; WS-08 | UXV-05, UXV-12 | DPB-01 | 3 — as row 35 | adopted Inter alone, six styles from 12 to 20 px, tabular figures | D-DS-02 | [DS §7](DESIGN_SYSTEM.md#7-typography-rules) |
| 38 | Spacing | OB §21 | UXV-05 | DPB-01 | 3 — as row 35 | adopted the 4 px scale and the page paddings | D-DS-03 | [DS §5.3](DESIGN_SYSTEM.md#53-spacing-sizing-and-density) |
| 39 | Density | PN-7; AC-14 | UXV-02, UXV-10 | DPB-01 | 3 — as row 35 | adopted one compact desktop density with touch-sized phone targets | D-DS-03 | [DS §5.3](DESIGN_SYSTEM.md#53-spacing-sizing-and-density) |
| 40 | Layout | OB §22; WCAG 1.4.10 | UXV-06, UXV-11 | DPB-01, DPB-02 | 3 + 3 | adopted the bands of DS §6 and the shell's content area | D-IA-01 | [DS §6](DESIGN_SYSTEM.md#6-layout-grid-and-breakpoints) |
| 41 | Visual hierarchy | V1_SCOPE priorities; OB §21 | UXV-02 | DPB-01 | 3 — as row 35 | adopted weight and space over colour and size; nothing above 20 px | D-DS-02 | [DS §7](DESIGN_SYSTEM.md#7-typography-rules) |
| 42 | Accessibility | OB §22; WCAG 2.2 AA; AC-14 | UXV-08–UXV-10 | DPB-17, DPB-01 | 2 + 3 | adopted the accessibility specimen's rules: skip link, focus ring, dialog focus, announced status, error linking, reflow; contrast measured on the tokens | D-DS-04 | [DS §15](DESIGN_SYSTEM.md#15-accessibility) |
| 43 | Keyboard-efficient desktop operation | SH-02; WCAG 2.1.4; UXS-41 | UXV-07; UXI-34 | DPB-17 | 2 — as row 29 | adopted the inline line editor with modifier-only shortcuts that never fire from the scan field | D-DS-07 | [DS §13](DESIGN_SYSTEM.md#13-keyboard-and-scanner) |
| 44 | Mobile operational workflows | V1_SCOPE language and experience contract; GAP-020; context tags `[floor]` | UXN-21; UXI-01, UXI-34 | DPB-09 | 3 — as row 14 | adopted the line checklist with one command per line | D-UX-11 | [AF §10.3](ADMIN_FLOW.md#103-inventory-and-purchasing) |
| 45 | Visual consistency | OB §21, §23 | UXV-03, UXV-05 | DPB-01, DPB-18 | 3 + the final review | adopted one token set and one pattern per need; the final review's findings below | D-DS-01 | [DS §18](DESIGN_SYSTEM.md#18-anti-ai-slop-mapping) |
| 46 | Anti AI-slop review | OB §21; P1 directive list; DIR-033 §10-M | UXV-03, UXV-04 | DPB-18 | the final review over the normalized design — three reference pages | an independent review with the tool's interface-review method found one HIGH and nine MEDIUM and LOW specification findings and five findings of prototype fidelity; all corrected and the corrections verified ([below](#final-consistency-and-anti-ai-slop-review-dpb-18)) | D-DS-06 | [DS §18](DESIGN_SYSTEM.md#18-anti-ai-slop-mapping) |

### Major surfaces

| Surface | Alternatives | Outcome |
| --- | --- | --- |
| A1 | application shell and navigation — DPB-02 — 3 | "Alur kerja" adopted with the per-list company filter; D-IA-01–D-IA-06 |
| A2 | Owner dashboard — DPB-03 — 3 | "Daftar kerja" adopted; D-IA-07 |
| A3 | Admin dashboard — DPB-04 — 3 | "Per jenis pekerjaan" adopted; D-IA-08 |
| A4 | project workspace — DPB-05 — 3 | "Tab" adopted, with the drill-in list on phones; D-IA-09 |
| A5 | purchasing and receiving — DPB-07 — 3 | "Tabel tunggal" adopted; D-DS-07, D-UX-12 |
| A6 | inventory — DPB-08 — 3 | "Per produk" adopted, with the detail of "Daftar–detail" as the product page; D-IA-17 |
| A7 | documents — DPB-11 — 3 | "Daftar periksa" for the checklist and "Per dokumen" for the list; D-IA-10 |
| A8 | billing, receivables and payment — DPB-12 — 3 | "Per invoice" adopted; D-IA-15 |
| A9 | Owner review queue — DPB-15 — 3 | "Dua bagian" adopted; D-IA-11 |
| A10 | numbering initialization — DPB-14 — 3 | "Daftar kesiapan" adopted with the matrix's details; D-IA-13 |
| A11 | company onboarding — DPB-14 — 2 | "Daftar kesiapan" adopted; two compliant structures exist for a derived list — ordered or grouped by state — and both were explored; D-IA-13 |
| A12 | mobile receiving, dispatch and scanner — DPB-09 — 3 | "Daftar centang" adopted; D-UX-11, D-DS-08 |

### Session evidence

Every DesainPakeAI session, in order, with its non-secret command and result. Commands ran from the temporary working directory; `node dpw.js` is a local helper that writes one page source through the CLI's documented `dpai call write_file` operation with the project's current revision, and `node dpexport.js` wraps `dpai export` and `dpai file read`. Readiness checks are in [DesainPakai readiness evidence](#desainpakai-readiness-evidence).

| Session | Date and time | Brief | Command (non-secret) | Result |
| --- | --- | --- | --- | --- |
| DPS-01 | 2026-10-01 08:33 WIB | DPB-01 | `dpai page create --id dpb01-a` (and `-b`, `-c`) `--width 1440 --height 900` | three pages created |
| DPS-02 | 2026-10-01 08:41 WIB | DPB-02 | `dpai page create --id dpb02-a` (and `-b`, `-c`) | three pages created, authored on 2026-10-02 |
| DPS-03 | 2026-10-01 08:43 WIB | DPB-01 | `node dpw.js src/pages/dpb01-a.page.html …` for the three variants | "Tinta", "Kertas" and "Batu" written |
| DPS-04 | 2026-10-02 17:13 WIB | DPB-01 | `dpai preview verify --page dpb01-a` (and `-b`, `-c`); `node dpexport.js`; captures at 1,440 and 390 px | compile passed, no diagnostic; reviewed: "Tinta" adopted |
| DPS-05 | 2026-10-02 17:13 WIB | DPB-01 | `dpai design lint` on the tool's starter design | 19 findings (18 warnings, 1 info), among them a 1.06:1 contrast; the starter design rejected as a direction |
| DPS-06 | 2026-10-02 17:16 WIB | DPB-03, DPB-04 | `dpai page create --id dpb03-a` … `dpb04-c` | six pages created |
| DPS-07 | 2026-10-02 17:21 WIB | DPB-14 | `dpai page create --id dpb14-a` … `dpb14-f` | six pages created |
| DPS-08 | 2026-10-02 17:22 WIB | DPB-09, DPB-10, DPB-17 | `dpai page create --id dpb09-a` (390 × 844) … `dpb17-b` | seven pages created |
| DPS-09 | 2026-10-02 17:32 WIB | DPB-15 | `dpai page create --id dpb15-a` (and `-b`, `-c`) | three pages created — after the GAP-034 settlement of 2026-10-01 08:17 WIB |
| DPS-10 | 2026-10-02 17:35 WIB | DPB-07, DPB-12, DPB-16 | `dpai page create --id dpb07-a` … `dpb16-c` | nine pages created |
| DPS-11 | 2026-10-02 17:40 WIB | DPB-05, DPB-11 | `dpai page create --id dpb05-a` … `dpb11-c` | six pages created |
| DPS-12 | 2026-10-02 17:55 WIB | DPB-13, DPB-15 | `dpai page create --id dpb13-a` (and `-b`); `node dpw.js` and `dpai preview verify --page dpb15-a` (and `-b`, `-c`) | two pages created; DPB-15 written, compile passed; the shared export already above the read limit, so visual checks moved to the local reconstruction |
| DPS-13 | 2026-10-02 18:06 WIB | DPB-09 | `node dpw.js` for `dpb09-a`, `-b`, `-c`; `dpai preview verify` | compile passed, no diagnostic; timed scanner bursts checked on the reconstruction |
| DPS-14 | 2026-10-02 18:10 WIB | DPB-02, DPB-05 | `node dpw.js` and `dpai preview verify` for `dpb02-a` … `dpb05-c` | compile passed, no diagnostic or layout issue; captures at 1,440, 1,280, 1,100, 390 and 320 px |
| DPS-15 | 2026-10-02 18:11 WIB | DPB-03, DPB-04, DPB-12 | `node dpw.js` and `dpai preview verify` for nine pages | compile passed, no diagnostic; loading, empty and error states captured per group |
| DPS-16 | 2026-10-02 18:13 WIB | DPB-13, DPB-15, DPB-06 | `node dpw.js` and `dpai preview verify` for `dpb13-a`, `-b` and `dpb15-a`, `-b`, `-c`; `dpai page create --id dpb06-a` (and `-b`) | compile passed; two pages created |
| DPS-17 | 2026-10-02 18:19 WIB | DPB-07 | `node dpw.js` for `dpb07-a`, `-b`, `-c` | written; 39–40 interaction checks passed on the reconstruction |
| DPS-18 | 2026-10-02 18:21 WIB | DPB-14 | `node dpw.js` and `dpai preview verify` for `dpb14-a` … `dpb14-f` | compile passed, no diagnostic or layout issue; 23 of 23 dialog checks passed |
| DPS-19 | 2026-10-02 18:22 WIB | DPB-08 | `dpai page create --id dpb08-a` (and `-b`, `-c`) | three pages created; not authored before the interruption |
| DPS-20 | 2026-10-02 18:24 WIB | DPB-11, DPB-05 | `node dpw.js` and `dpai preview verify` for `dpb11-a`, `-b`, `-c`; `dpai file read` of six pages | compile passed; remote sources equal to local |
| DPS-21 | 2026-10-02 21:05 WIB | DPB-06, DPB-10, DPB-12, DPB-13, DPB-16, DPB-17 | `dpai file read --path src/pages/<id>.page.html --full` for 26 pages (read-only comparison) | recovery: DPB-07, DPB-09, DPB-15 complete remotely; six briefs' finished sources still local; DPB-08 not authored |
| DPS-22 | 2026-10-02 21:09 WIB | DPB-06, DPB-07, DPB-10, DPB-12, DPB-13, DPB-16, DPB-17 | `node dpw.js` for fourteen pages; `dpai preview verify` for seventeen pages | compile passed, no diagnostic, warning or layout issue |
| DPS-23 | 2026-10-02 21:20 WIB | DPB-17 | `node dpw.js` and `dpai preview verify` for `dpb17-a`, `-b` after removing leftover section markers | compile passed, no diagnostic |
| DPS-24 | 2026-10-02 21:44 WIB | DPB-08 | `node dpw.js src/pages/dpb08-a.page.html …` for the three variants; `dpai preview verify --page dpb08-a` (and `-b`, `-c`) | written; compile passed, no diagnostic, warning or layout issue; captures for the Admin and the Owner at 1,440 and 390 px; six visibility checks of the rendered text passed |
| DPS-25 | 2026-10-02 21:44 WIB | DPB-18 | `dpai page create --id dpb18-a` (1,440 × 900), `dpb18-b` (1,440 × 900), `dpb18-c` (390 × 844); `dpb18-a` retried once at 21:45 after a revision conflict | three reference pages of the normalized design created |
| DPS-26 | 2026-10-02 21:51 WIB | DPB-18 | `node dpw.js DESIGN.md …` (write of the project's design-system file); `dpai design lint` | the write refused — source writes accept pages, layouts, components and styles only, and the design-update command documents no options; the project's design context stays the tool's starter design, whose lint is unchanged (19 findings: 18 warnings, 1 info, no error); the adopted tokens are checked by their measured contrast and on the reference pages |
| DPS-27 | 2026-10-02 21:58 WIB | DPB-18 | `node dpw.js src/pages/dpb18-a.page.html …` for the three pages; `dpai preview verify --page dpb18-a` (and `-b`, `-c`) | written; compile passed, no diagnostic, warning or layout issue; 34 of 34 interaction and overflow checks passed on the reconstruction |
| DPS-28 | 2026-10-02 22:10 WIB | DPB-18 | `dpai file read --path src/pages/dpb18-a.page.html --full` (and `-b`, `-c`) and `dpai preview verify` (read-only, by the independent reviewer); captures at 1,440, 1,100, 1,024 and 800 px | sources equal to the local files; compile passed; the review of the normalized design: verdict Block on one HIGH specification finding |
| DPS-29 | 2026-10-02 22:45 WIB | DPB-18 | `node dpw.js src/pages/dpb18-a.page.html …` for the three pages after the review; `dpai preview verify --page dpb18-a` (and `-b`, `-c`) | written; compile passed, no diagnostic, warning or layout issue; 66 of 66 interaction and overflow checks passed |

### Final consistency and anti AI-slop review (DPB-18)

**Normalized design.** After every surface was normalized, the adopted design was rendered as three reference pages in the MULTIPLECORP workspace from a specification-only brief — `dpb18-a` (desktop shell and the Owner's Beranda), `dpb18-b` (desktop project detail with the quotation editor, a CONFLICT answer and an issue confirmation) and `dpb18-c` (phone receiving) — and verified by the tool (DPS-25, DPS-27). The project's own design-system file could not be replaced: the CLI accepts source writes only for pages, layouts, components and styles, and its design-update command documents no options, so it was not used (DPS-26). The adopted tokens were therefore checked on the reference pages and by their measured contrast: every text pair is at least 5.73:1, every tone at least 6.54:1 on white, the control boundary 3.35:1.

**Review.** An independent reviewer that had not built the pages applied the tool's interface-review guide with its six domain guides — accessibility, layout and responsiveness, product writing, typography, colour, polish and motion — against the reference pages and DESIGN_SYSTEM, and checked SLOP-01–SLOP-45. Verdict: **Block**, on one HIGH finding of the specification. Each finding was handled under the discipline of DIR-033 §11.7; none touched business, visibility or authority, so none was OWNER_DECISION_REQUIRED.

| Finding | Class | Disposition |
| --- | --- | --- |
| F18-07 — the outcome message "directly above the action bar" sat behind a sticky bar, out of view, for answers that do not move focus | HIGH, specification | the outcome message is now part of the sticky action region, directly above its buttons; REJECTED placed the same way ([D-DS-06](DESIGN_SYSTEM.md#4-design-decisions); PT-14; §12.2) |
| F18-08 — the accent's role named selection and links, the pages did not use it there | MEDIUM, specification | the accent is the primary action, the current-navigation rule and focus only; links, tabs, segments and options have their own treatment (§5.1) |
| F18-09 — two primary buttons in view at once | MEDIUM, specification | while a command form's action bar is in view the header action is secondary (PT-03) |
| F18-10 — part facts, the scan state and a draft revision shown outside the status system; the SENT icon misdescribed | MEDIUM, specification | the derived part strip PT-31 with its own state family; the scan state is plain text; SENT is the paper-plane shape (§12.1) |
| F18-11 — four faces for inline messages | MEDIUM, specification | one anatomy for every inline message — tone icon, label, ink text, one action; the error of a part and the session notices follow it (PT-14; §12.3; INFORMATION_ARCHITECTURE §6.1) |
| F18-12 — eleven tabs and the line editor clipped inside the desktop band | MEDIUM, specification | tabs overflow into "Bagian Lain" (D-IA-09; GL-188); the line editor scrolls inside its frame with fixed first and last columns (PT-10) |
| F18-13 — the pending line in a figures table could read as a value | MEDIUM, specification | it names products and quantities, never an amount (§14.1); review finding R1-05 later narrowed it: a company's views show only that company's own exposure, and the case's pending quantity only the pool views (§14.1, §14.2; PJ-22) |
| F18-14 — absolute spacing, a missing 14 px icon size, an inset segment radius, no scrim token | LOW, specification | the spacing scale scoped to gaps and regions; 14 px icons in badges and inline marks; the segmented control's anatomy; `--scrim` (§5.3, §5.4, SLOP-32) |
| F18-15 — heading roles and some element types had no rule | LOW, specification | heading roles by level (§5.2); case rules for headings, titles, group labels, checkbox labels and fact values (§16) |
| F18-05 — focus could fall under sticky regions | MEDIUM, both | the scroll padding equals the sticky regions (§15) |
| F18-01, F18-02, F18-03, F18-04, F18-06 — the rail without icons, the CONFLICT icon and live region, the dialog's affected items, the scan field's parts, off-scale values and date and time formats | MEDIUM and LOW, prototype fidelity | the specification already stated each; the reference pages corrected; "Diperbarui 14.05 WIB" also corrected in INFORMATION_ARCHITECTURE §9 |

Anti-slop checklist: forty-one of forty-five items passed on the first review; the four that failed — SLOP-30, SLOP-31, SLOP-32, SLOP-33 — came from F18-06, F18-10 and F18-14 above.

**Verification.** The specification was amended and the reference pages rebuilt (DPS-29); the same reviewer then checked each finding in one narrow pass (2026-10-02, about 22:50 WIB): F18-01–F18-09 and F18-11–F18-15 resolved, F18-10 resolved in the specification, and no new HIGH or MEDIUM defect. The pass left one question open — whether the numbering statements and their tick apply to the confirmation "Terbitkan Penawaran?" — which the repository answers: they belong only to the numbering commands of [ADMIN_FLOW §12.2](ADMIN_FLOW.md#122-start-seed-and-scheme-commands), while issuing a quotation is confirmed as [ADMIN_FLOW IP-08](ADMIN_FLOW.md#ip-08--confirming-an-irreversible-command) states; with it the verdict is **Approve**. Three LOW differences remain on one prototype page only — two badge shapes and one check shape that do not yet follow §12.1 — and need no change to the specification, which states the shapes; the pages are exploration evidence, never a source for build units. Runtime behaviour — screen-reader announcements, keyboard order, focus never hidden at run time, the tool's own canvas — stays with P8 (UXH-05, UXH-07).

The normative specifications were frozen after this pass; later edits come only from the reviews below.

## Obligation closure

Every item DIR-033 §16 lists, mapped to its owning P7 section. Status: **DESIGNED**, **HANDED TO** a later phase (with its owner), or **N/A** with the reason. IA = INFORMATION_ARCHITECTURE, AF = ADMIN_FLOW, DS = DESIGN_SYSTEM.

### V1_SCOPE — P7 handoff requirements

| Clause | Home | Status |
| --- | --- | --- |
| Indonesian terminology and glossary | IA §3 | DESIGNED |
| Mobile and desktop navigation | IA §6; DS PT-01, PT-02 | DESIGNED |
| Responsive layout strategy | DS §6, §11 | DESIGNED |
| Mobile table alternatives and desktop data-table standards | DS PT-05, PT-06 | DESIGNED |
| Responsive forms | DS PT-09, §11 | DESIGNED |
| Dialog versus sheet behaviour | DS PT-16, §11 | DESIGNED |
| Touch targets | DS §5.3, §11, §15 | DESIGNED |
| Keyboard and barcode interaction | DS §13; AF IP-15 | DESIGNED |
| Typography, spacing, radius, density, icons, status system and design tokens | DS §5, §7, §8, §12.1 | DESIGNED |
| Loading, error and empty states | DS PT-19, §12.3 | DESIGNED |
| Activity and history | DS PT-18; IA SCR-59 | DESIGNED |
| Anti AI-slop review | DS §18; [DPB-18](#final-consistency-and-anti-ai-slop-review-dpb-18) | DESIGNED |
| P7 cannot PASS until mobile-first and desktop-operational designs are both explicit | IA §8 (phone and desktop columns of all sixty screens); AF §10 (phone and desktop column of every journey) | DESIGNED |
| Existing shadcn primitives stay separate from feature and business components | DS §10 | DESIGNED |

### V1_SCOPE — language and experience contract

| Clause | Home | Status |
| --- | --- | --- |
| The flow image supplies sequence and branching only; its labels, gradients, cards and colours are not the design | IA §5 (the business-flow order); DS §18 | DESIGNED |
| All end-user text in natural, professional Bahasa Indonesia | IA §3; AF §14; DS §16 | DESIGNED |
| Client and legal names, codes, SKUs and user-authored content are data, never translated | IA §3 (conventions for data); DS §16 | DESIGNED |
| P7 owns the final glossary and exact copy; the Owner's examples | IA §3 GL-009–GL-011, GL-015, GL-017, GL-018, GL-022 | DESIGNED |
| Phone: dashboard and status monitoring | SCR-05, SCR-06 (phone column) | DESIGNED |
| Phone: project, product, document, billing and payment lookup and detail | SCR-08–SCR-10, SCR-18, SCR-19, SCR-34, SCR-35, SCR-38–SCR-41, SCR-52 | DESIGNED |
| Phone: quick actions and approvals | AF J-QUO-01 (the client's answer), J-FUL-02, J-FIN-03, J-PRJ-03 (Owner) | DESIGNED |
| Phone: reasonable operational forms | DS PT-09; AF IP-13 | DESIGNED |
| Phone: receiving, dispatch and barcode actions where the device permits; manual SKU lookup without a scanner | AF J-INV-01, J-INV-03, J-INV-04–J-INV-06, IP-15; DS PT-20 | DESIGNED |
| Compatibility and practical limits demonstrated on real devices (GAP-020) | AF UXH-06 | HANDED TO P8 (P8 device UAT; operator and Admin Operasional) |
| Desktop: intensive administration, large tables, keyboard and mouse, repeated and bulk entry, document creation, inventory, finance, reporting, long workflows | IA §8 desktop column; DS PT-05, PT-10, §13 | DESIGNED |
| Efficient multi-item entry without a spreadsheet clone | DS PT-10 | DESIGNED |
| Reuse known project data instead of retyping | AF IP-13 | DESIGNED |
| Visual priorities clarity → … → aesthetics; premium without losing density; a decorative design that slows work fails | DS UXV-02, §4, §5.3, §18 | DESIGNED |
| The anti-slop prohibitions stay binding | DS §18 | DESIGNED |
| The owner-selected shadcn system | DS §2, §10 | DESIGNED |

### Owner brief §23 — design system before page explosion

| Item | Home | Status |
| --- | --- | --- |
| Application shell | DS PT-01 | DESIGNED |
| Sidebar and navigation | DS PT-01, PT-02; IA §6 | DESIGNED |
| Top bar | DS PT-01 | DESIGNED |
| Page header | DS PT-03 | DESIGNED |
| Page actions | DS PT-03, PT-11 | DESIGNED |
| Data table | DS PT-05 | DESIGNED |
| Filters | DS PT-07 | DESIGNED |
| Pagination | DS PT-08 ("Muat Lagi", PF-15) | DESIGNED |
| Search | DS PT-27; IA §10 | DESIGNED |
| Form layout | DS PT-09 | DESIGNED |
| Validation states | DS PT-09; AF IP-14 | DESIGNED |
| Detail layout | DS PT-12 | DESIGNED |
| Status badges | DS PT-13, §12.1 | DESIGNED |
| Tabs | DS PT-12, §11 | DESIGNED |
| Modal and dialog | DS PT-16, PT-26, PT-28 | DESIGNED |
| Drawer and sheet | DS PT-16 | DESIGNED |
| Destructive confirmation | DS PT-11, PT-26 | DESIGNED |
| Loading | DS PT-19 | DESIGNED |
| Skeleton | DS PT-19 | DESIGNED |
| Empty states | DS PT-19; IA §9.1 empty sentences | DESIGNED |
| Error states | DS PT-19, §12.3; AF IP-21 | DESIGNED |
| Toast and feedback | DS PT-14, PT-15 | DESIGNED |
| Audit and activity timeline | DS PT-18 | DESIGNED |
| Mobile patterns | DS PT-02, PT-06, §11 | DESIGNED |

### DESIGN_SYSTEM minimum content (DIR-033 §10-M)

| Item | Home | Status |
| --- | --- | --- |
| Application shell and navigation | DS PT-01, PT-02 | DESIGNED |
| Typography; spacing; grid; density | DS §5.2, §5.3, §6, §7 | DESIGNED |
| Responsive behaviour and breakpoints | DS §6, §11 | DESIGNED |
| Forms | DS PT-09, PT-10, PT-25 | DESIGNED |
| Buttons and actions | DS PT-11 | DESIGNED |
| Tables, list patterns, filters, search, "more" | DS PT-05–PT-08, PT-27 | DESIGNED |
| Dialogs, sheets and drawers, and the dialog-versus-sheet rules | DS PT-16 | DESIGNED |
| Status representation: each state a label and a non-colour cue | DS §12.1 | DESIGNED |
| Destructive actions and their confirmation | DS PT-11, PT-26 | DESIGNED |
| Correction and reversal patterns | DS PT-26; AF §11, D-UX-10 | DESIGNED |
| Financial-data presentation | DS §14.1, PT-24 | DESIGNED |
| Inventory presentation | DS §14.2, PT-17 | DESIGNED |
| Document preview | DS §14.3, PT-22 | DESIGNED |
| Scanner UX | DS PT-20, §13.2; AF IP-15 | DESIGNED |
| Loading, skeleton, empty, error, denied, stale, CONFLICT, PENDING and FAILED states | DS §12.2, §12.3, PT-19 | DESIGNED |
| Toast and feedback | DS PT-14, PT-15 | DESIGNED |
| Audit and activity timeline and history | DS PT-18 | DESIGNED |
| Accessibility; keyboard interaction; touch behaviour and targets | DS §13, §15, §11 | DESIGNED |
| Mobile patterns and mobile table alternatives | DS PT-02, PT-06, §11 | DESIGNED |
| Print-view rules | DS §17 | DESIGNED |
| Iconography, motion, radius, elevation and non-executable tokens | DS §5, §8 | DESIGNED |
| Formatting: Rupiah, Indonesian separators, dates, times and WIB, exact quantities with units, public identifiers | DS §16 | DESIGNED |
| shadcn/ui as the foundation; composition of primitives into feature components; no business logic in primitives; no frontend implementation | DS §2, §10 | DESIGNED |
| Feel: calm, premium, professional, operationally dense; no social-media patterns | DS §4, §18 SLOP-39–SLOP-45 | DESIGNED |
| P1 directive P7 list: terminology; mobile and desktop navigation; responsive strategy; mobile table alternatives; desktop table standard; responsive forms; dialog versus sheet; touch targets; keyboard; barcode; typography; spacing; radius; density; icon system; status system; loading, error and empty states; activity and history; design tokens; anti AI-slop review | IA §3, §6; DS §5–§8, §11–§13, §18; PT-05–PT-19 | DESIGNED |

### Anti AI-slop lists and the accessibility list

| Item | Home | Status |
| --- | --- | --- |
| The 34 prohibitions and four "do not look like" items of OB §21 | DS §18 SLOP-01–SLOP-38 | DESIGNED |
| The P1 directive's 24 prohibitions | DS §18 (mapping table) | DESIGNED |
| The social-media patterns of DIR-033 §10-M | DS §18 SLOP-39–SLOP-45 | DESIGNED |
| OB §22 accessibility: keyboard navigation, visible focus, semantic labels, contrast, dialog focus management, status not by colour alone | DS §15 | DESIGNED; proof HANDED TO P8 (UXH-05) |

### WORKFLOWS §13 P7 list and L-24

| Clause | Home | Status |
| --- | --- | --- |
| Indonesian journeys per workflow and context tag | AF §10 (39 journeys) | DESIGNED |
| Presentation of COMMITTED, REJECTED, CONFLICT and PENDING outcomes | AF IP-01; DS §12.2 | DESIGNED |
| Blockers | AF J-PRJ-03; IA SCR-14; §9.1 QS-15 | DESIGNED |
| QS signals | IA §9 | DESIGNED |
| The Force Complete confirmation | AF J-PRJ-03; DS PT-26; UXS-37 | DESIGNED |
| The pre-payment Kuitansi's distinct state | DS §12.1; AF J-FIN-08; UXS-38 | DESIGNED |
| Pending unattributed losses | DS §14.2; IA §9.1 QS-22; UXS-39 | DESIGNED |
| L-24 context tags and Indonesian journey anchors | AF §10; IA §8 | DESIGNED |

### Workflows and signals

| Item | Home | Status |
| --- | --- | --- |
| Every workflow of WORKFLOWS §5 — 39 of 39 | AF §10: J-ACC-01, J-ACC-02, J-MD-01–J-MD-05, J-PRJ-01–J-PRJ-04, J-QUO-01, J-FUL-01–J-FUL-05, J-INV-01–J-INV-07, J-PUR-01–J-PUR-03, J-DOC-01, J-DOC-02, J-ADM-01, J-FIN-01–J-FIN-08, J-MIG-01 | DESIGNED; J-INV-07 has no command of its own and is presented inside J-INV-03, J-INV-06 and J-PUR-03 |
| Every user-facing subflow | AF §10 (the subflow homes); SF-CHARGE, SF-LOSS, SF-REVAL and SF-RENDER run only by SYS or BG | DESIGNED; four N/A as journeys with their reason |
| QS-01–QS-22 — 22 of 22 | IA §9.1 | DESIGNED |

### SECURITY H7-01–H7-14

| Item | Home | Status |
| --- | --- | --- |
| H7-01 generic authentication and credential messages | AF J-ACC-01, MSG-19, MSG-28 | DESIGNED |
| H7-02 the relation marker and masked labels | IA §7; DS PT-04 | DESIGNED |
| H7-03 capability-driven hiding or disabling as a hint | AF IP-21; DS PT-11; IA §5 | DESIGNED |
| H7-04 credential-link pages, password rules and feedback, paste and show-password | AF J-ACC-01; IA SCR-02; DS PT-28 | DESIGNED |
| H7-05 session-expiry warning and preservation of unsent input | AF §6 (IP-11) | DESIGNED |
| H7-06 grant screens with prerequisites and the capability-by-company preview | AF J-ACC-02; DS PT-30; IA SCR-57 | DESIGNED |
| H7-07 in-scope duplicate warnings with the override reason | AF IP-05, IP-16 | DESIGNED |
| H7-08 not-found, forbidden and error pages with the correlation id | AF IP-21; IA SCR-04 | DESIGNED |
| H7-09 the step-up confirmation | AF IP-10; DS PT-28 | DESIGNED |
| H7-10 no sensitive data in titles, addresses, notifications or history | IA §11 | DESIGNED |
| H7-11 the pool-evidence confirmation | AF IP-16, MSG-32 | DESIGNED |
| H7-12 history and caches cleared at expiry, termination and login | AF §6 (IP-11) | DESIGNED |
| H7-13 background requests marked; never extending the idle timeout | AF §6, IP-12; IA §9 | DESIGNED |
| H7-14 own credential events, the first-login notice, the Owner's notice of a link used | AF J-ACC-01, MSG-29 with its Owner variant; IA SCR-03 and §9 (the Owner's security notices heading G1, with the LG-07 in-app fallback) | DESIGNED |

### CONCURRENCY_IDEMPOTENCY HO-01–HO-10, HO-38, HO-39

| Item | Home | Status |
| --- | --- | --- |
| HO-01 truthful answers; background FAILED; routed refusal | AF IP-01, IP-04, IP-06, IP-12 | DESIGNED |
| HO-02 one `command_id` per intent | AF IP-02, §6 | DESIGNED |
| HO-03 double-submit | AF IP-02 | DESIGNED |
| HO-04 CONFLICT and stale handling; "already recorded" | AF IP-03 | DESIGNED |
| HO-05 duplicate confirmations naming their matches | AF IP-05 | DESIGNED |
| HO-06 background requests marked | AF §6, IP-12 | DESIGNED |
| HO-07 retry after FAILED with the same identifier | AF IP-04 | DESIGNED |
| HO-08 the business date | AF IP-07; DS PT-25 | DESIGNED |
| HO-09 export states | AF IP-12; IA SCR-49 | DESIGNED |
| HO-10 lists, search and scanner within PERFORMANCE | AF IP-15, IP-17, IP-18; IA §8, §10; DS PT-05–PT-08, PT-20, PT-27 | DESIGNED |
| HO-38 the GAP-034 record before the Owner review queue | [GAP-034 settlement](#gap-034-settlement-and-ordering-evidence); IA §9.2 | DESIGNED; runtime proof HANDED TO P8 (UXH-04) |
| HO-39 the numbering confirmations | AF §12.2 | DESIGNED |

### Copy for the Owner-visible Level-1 choices of P6

| P6 choice | Copy and presentation | Status |
| --- | --- | --- |
| Lock waits end in FAILED, safe to repeat | AF MSG-06, IP-04 | DESIGNED |
| A refusal is remembered; a corrected request is a new one | AF IP-02 | DESIGNED |
| Duplicate warnings and their override | AF MSG-15, IP-05 | DESIGNED |
| While the queue, cache and limiter store is down nobody can sign in | the error page with its reference code (AF IP-21; IA SCR-04); the sign-in page shows the generic error, never a reason about the account | DESIGNED |
| A login flood is held to two verifications at a time | AF MSG-20 | DESIGNED |
| Numbers — starting, seeding, schemes and imports | AF §12, MSG-17 | DESIGNED |
| Dates — a prefilled date that went stale | AF MSG-16, IP-07 | DESIGNED |
| Counts — a stale count | AF MSG-30 | DESIGNED |
| Sessions — background requests never extend one | AF §6 | DESIGNED |
| Revocation withholds exports | AF IP-12 (*Tidak Tersedia*) | DESIGNED |
| Routed refusals as 14-day notices | AF MSG-14; IA §9.1 QS-20 | DESIGNED |
| Replays after revocation or deactivation | AF IP-21, §6 | DESIGNED |
| Two Owners acting on each other | AF MSG-10 (the denial) | DESIGNED |
| Evidence replacement in two steps | AF IP-16 | DESIGNED |
| Exports: two in progress, three tries, requester-only status | AF IP-12, MSG-21, MSG-22 | DESIGNED |
| Search cut at 200 candidates | AF MSG-23 | DESIGNED |
| Brief waits under one project's row; two dashboard signals read all companies' open rows before the scope filter | no copy needed: neither reaches the user as a state | N/A |

### ARCHITECTURE §9 and §10 P7 items

| Item | Home | Status |
| --- | --- | --- |
| §9 edit forms round-trip `lock_version` and a `command_id` per intent | AF IP-02, IP-03 | DESIGNED |
| §9 lists paginated by keyset, sorted and filtered by whitelisted keys; secondary data as partial reloads or lazy props | IA §8; DS PT-08 | DESIGNED |
| §10 pages per route, feature components, shadcn primitives untouched | DS §10 | DESIGNED |
| §10 the client never computes authoritative values; decimal strings as typed | AF IP-13 | DESIGNED |
| §10 a client-generated `command_id` reused on retry; the five answers shown truthfully | AF IP-01, IP-02 | DESIGNED |
| §10 scanner and phone/desktop patterns, Indonesian copy and the design system are P7's | IA, AF, DS as a whole | DESIGNED |

### PERFORMANCE constraints (DIR-033 §10-I)

| Constraint | Home | Status |
| --- | --- | --- |
| Keyset "more" without totals or page numbers (PF-15) | DS PT-08; IA §8 | DESIGNED |
| Page size 25, at most 100 (PF-17) | DS PT-08 | DESIGNED |
| Opaque cursors; a cursor error is a validation error (PF-14) | AF IP-18, MSG-26 | DESIGNED |
| Badges and queue sizes from guards or bounded per-company counts (PF-15, PF-24) | IA §9 | DESIGNED |
| Multi-company lists merged per company branch (PF-41) | IA §7, §8 | DESIGNED |
| Sort and filter keys only as PF-42 registers them | IA §8; [section 12 channel](#section-12-channel) | DESIGNED |
| Search: exact first, three characters, 200-candidate bound and cut notice, 20-row type-ahead, scope first, 100 characters (PF-19–PF-23) | IA §10; AF IP-17 | DESIGNED |
| Scanner: exact lookup, ambiguity never auto-selected, wedge input never colliding with shortcuts, manual SKU fallback, camera only where the device permits (GAP-020) | AF IP-15, D-UX-09; DS §13 | DESIGNED; camera excluded in V1 by WS-08 and D-05; device proof HANDED TO P8 (UXH-06) |
| Thumbnails only in lists, one page, lazy below the fold (PF-31, PF-32) | DS PT-22 | DESIGNED |
| Prefetching off on S3 and S4 pages; partial reloads name their props (PF-35, PF-36) | IA §11, §9 | DESIGNED |
| Phone alternatives to tables; a desktop data-table standard | DS PT-06, PT-05 | DESIGNED |

### Gaps, acceptance, references and capabilities

| Item | Home | Status |
| --- | --- | --- |
| GAP-015 stale and expired UI and scans | AF §5, §6, IP-15 | DESIGNED; device and E2E proof HANDED TO P8 (UXH-01, UXH-02, UXH-06) |
| GAP-019, P7 part — issue experience, document wording conventions, preview | AF J-DOC-01; DS §14.3, §16 | DESIGNED; per-document templates and their content HANDED TO P11 build units and P8 UAT with the Owner and Admin (UXH-08) |
| GAP-020, P7 part — phone and scanner patterns | AF IP-15; DS §6, §11, §13 | DESIGNED; real-device proof HANDED TO P8 (UXH-06) |
| GAP-032 — the capability-by-company preview (H7-06) | AF J-ACC-02; DS PT-30 | DESIGNED; grant tests HANDED TO P8 |
| GAP-034 | the settlement above | DESIGNED; runtime proof HANDED TO P8 (UXH-04) |
| GAP-016 — the C-10 refusal and the Owner's readiness line | AF §12.3 | DESIGNED; numbering and clock tests stay P8's (HO-19) |
| GAP-029 — the pre-payment Kuitansi's distinct presentation | DS §12.1; AF J-FIN-08 | DESIGNED; no-double-recognition tests stay P8's |
| AC-03 presentation — unknown and ambiguous scans never select silently | AF IP-15 | DESIGNED |
| AC-08 presentation — selectable documents, truthful issuer, layouts without effects, drafts never claiming issuance | AF J-DOC-01; DS §14.3 | DESIGNED |
| AC-13 presentation — denials and pooled physical visibility | AF IP-21; IA §7 | DESIGNED |
| AC-14 — Indonesian copy; phone and desktop tasks; focus, keyboard and touch; clear errors and status | IA, AF, DS as a whole; DS §15 | DESIGNED; device and UAT evidence HANDED TO P8 |
| AC-20 presentation — the golden journey, an existing-stock order and a service-only project | AF §10 (J-PRJ-01 → J-PRJ-03 through J-FUL-01, J-FUL-04) | DESIGNED |
| AC-23 presentation — summaries, queues, alerts, search, filtered reports and exports, refresh and failure feedback | IA §9, §10; SCR-47, SCR-49; AF IP-12 | DESIGNED |
| REF-007 Owner consolidated and company dashboard | IA SCR-05, §9 | DESIGNED |
| REF-008 Admin next-action dashboard | IA SCR-06, §9 | DESIGNED |
| REF-048 in-app alerts for low stock, overdue and incomplete work | IA §9: the Beranda signals are the in-app alerts, derived at display time (DP-11); no notification centre and no badge counting across companies | DESIGNED |
| REF-050 fast search | IA §10 | DESIGNED |
| REF-052 natural Indonesian UI | IA §3 | DESIGNED |
| REF-053 mobile-first and desktop work | IA §6, §8; DS §11 | DESIGNED |
| REF-054 the React, TypeScript, Inertia, shadcn and Tailwind baseline | DS §2, §10 | DESIGNED |
| REF-055 restrained anti AI-slop design | DS §18 | DESIGNED |
| CAP-14 | the three documents | DESIGNED |
| CAP-17 | IA §9, §10; SCR-47–SCR-49 | DESIGNED |

## Self-review

Checked against DIR-033 §§9–16 before the independent reviews: anti-duplication, authority level, projection fidelity, PERFORMANCE, security presentation, phone and desktop completeness, glossary, anti-slop, DesainPakai discipline and traceability. Findings and their fixes:

| ID | Class | Finding | Fix |
| --- | --- | --- | --- |
| S7-01 | MEDIUM | ADMIN_FLOW presented the project reservation offered at receiving as a separate later command (J-INV-01, IP-19); AX-04 makes it an optional part of the receiving action | J-INV-01 and IP-19 corrected; D-UX-12 records it |
| S7-02 | LOW | "Selisih & Temuan" was marked Owner-only in the product map although the evidence-identified resolution belongs to ADM+ holders of `stock.resolve_unattributed_evidence` | product map corrected to PJ-07's limited view |
| S7-03 | LOW | Example document numbers read as a format, while the format is each company's scheme validated under GAP-019 | examples marked fictional and illustrative (DESIGN_SYSTEM §1, §16) |
| S7-04 | LOW | The page actions' overflow menu was labelled "Lainnya", the same word as the phone navigation's sheet | renamed "Tindakan Lain" (GL-187) |
| S7-05 | LOW | IP-11 and IP-12 were introduced in running text, so they had no addressable definition | made pattern headings |
| S7-06 | LOW | SCR-35 named its audience only by reference to SCR-34 | the capability and projection stated |
| S7-07 | LOW | The disposition of PN-3 said history is cleared after the password is accepted, while ADMIN_FLOW §6 clears it at once and again afterwards | disposition corrected |
| S7-08 | LEVEL-1 CORRECTION | Buttons, menu actions and status labels mixed sentence case and Title Case | about 160 labels normalized to DESIGN_SYSTEM §16 |
| S7-09 | LEVEL-1 CORRECTION | Beranda's refresh was "Perbarui", every other reload "Muat Ulang" | one verb, "Muat Ulang" |
| S7-10 | LEVEL-1 CORRECTION | The Lucide name `circle-half` does not exist | `contrast`; P11 confirms the names under DEP-07 |
| S7-11 | LEVEL-1 CORRECTION | Design decisions were not cited from the sections that apply them | each decision cited where it applies |

No finding was OWNER_DECISION_REQUIRED: none changes who may see or do something, turns a warning into a block, merges intents or touches business, numbering, date or document meaning.

## Independent reviews

Three fresh read-only reviewers, each given only the repository working tree and one lens of DIR-033 §18, attacked UXS-01–UXS-44 in parallel after the normative files were frozen: **R1** business, authority and visibility fidelity; **R2** interaction robustness; **R3** usability and the design system, with the quality of the DesainPakai normalization and traceability. Each finding was classified once, every valid finding was fixed, and duplicates were consolidated under the first reviewer's identifier.

| Reviewer | CRITICAL | HIGH | MEDIUM | LOW | LEVEL-1 | DEFERRED | OWNER_DECISION_REQUIRED as written |
| --- | --- | --- | --- | --- | --- | --- | --- |
| R1 | 0 | 1 | 8 | 7 | 5 | 1 (P8) | 1 — R1-02, reclassified below |
| R2 | 0 | 0 | 4 | 13 | 9 | 0 | 0 |
| R3 | 0 | 0 | 3 | 7 | 8 | 0 | 0 |
| Combined | 0 | 1 | 15 | 27 | 22 | 1 | 1 |

Duplicates: R3-03 is R1-10; R2-16 is part of R1-03; R3-11 is R2-19. Distinct: HIGH 1, MEDIUM 14, LOW 26, LEVEL-1 21, one deferred obligation.

**R1-02 — reclassified as a recorded residual.** R1 classed as OWNER_DECISION_REQUIRED the state of a client-original requirement linked to an upload that reproduces a rendition the uploader may not see. Two approved rules meet there: the upload can never satisfy a client original (FS-12; WF-DOC-02), and the match shows the uploader nothing (DP-13; FL-10). P7 changes neither and presents both: after R1-01 nothing is shown on the evidence, and the requirement stays open in its ordinary state, as it would for any upload that does not satisfy it. No visibility, authority, security control or business meaning changes, so DIR-033 §13 does not apply; removing the residual would need a change to FS-12 or DP-13, which only the Owner can make. The residual is recorded in GAP-034's P7 note and the settlement's item f). Because it departs from the letter of DIR-033 §9.2 f), the Owner was asked in session before approval and accepted it knowingly on 2026-10-02 ("Terima residu, lanjutkan"; recorded under DIR-033).

### R1 — business, authority and visibility fidelity

| ID | Class | Finding | Fix |
| --- | --- | --- | --- |
| R1-01 | HIGH | The reproduction flag and its reason were shown even when the matched rendition lay outside the uploader's projection — the duplicate oracle DP-13 forbids | flag, note and reason only when the rendition is visible (company in scope and family, PJ-15); otherwise nothing (ADMIN_FLOW IP-16, J-ADM-01, UXS-18, UXH-03, UXH-04; DESIGN_SYSTEM §14.3; GAP-034 note) |
| R1-02 | OWNER_DECISION_REQUIRED as written | The requirement linked to such an upload stays unsatisfied | recorded residual, as above |
| R1-03 | MEDIUM | DP-18's refusal could carry the "Sudah Tercatat" face, and out-of-scope-twin rows sat under *Duplikat* | a neutral face — REJECTED's label with MSG-18 only (DESIGN_SYSTEM §12.2); such rows under *Tidak Valid* (ADMIN_FLOW IP-01, §13, UXS-19) |
| R1-04 | MEDIUM | The Owner's Beranda summary of QS-14 left out pool evidence and out-of-scope DUPLICATE rows | G1 reads every SCR-07 source; nine statements (INFORMATION_ARCHITECTURE §9) |
| R1-05 | MEDIUM | Company views repeated a pending case's quantity instead of the company's own exposure | company views show only their own exposure with `cost.view` (row 69); the case's quantity only on SCR-18 and SCR-19 (DESIGN_SYSTEM §14.1, §14.2; SCR-47; MSG-41; UXS-39; F18-13) |
| R1-06 | MEDIUM | Same-command payment applications (AX-18) and CM-05's same-action reservation were split into separate commands | applications inside "Catat Pembayaran", "Terapkan" for later money; the reservation inside the restoration (J-FIN-03, SCR-42, IP-19, §11) |
| R1-07 | MEDIUM | The pre-payment Kuitansi link (AX-31) had no entry point or refusal | "Tautkan Kuitansi" on SCR-35, SCR-39 and SCR-42 with its precondition, refusal and unlink (J-FIN-08; UXS-38) |
| R1-08 | MEDIUM | SCR-27's resolutions lacked OD-10's limits | four designed commands — Owner attribution, evidence resolution with a derived company and no shortfall control, surplus attribution, exact reversal (ADMIN_FLOW §10.3; SCR-27) |
| R1-09 | MEDIUM | The grant preview said a removed prerequisite removes its dependants; RG-06 only disables them | "Nonaktif — prasyarat dicabut", grants kept (PT-30; D-DS-09; UXS-27) |
| R1-10 | MEDIUM | The Owner's notice of a used credential link (AU-12) and LG-07's in-app fallback had no place | the Owner's security notices head G1; MSG-29's Owner variant; gate H7-14 row |
| R1-11 | LOW | The numbering readiness line was promised on the Owner dashboard | promise dropped (§12.3) |
| R1-12 | LOW | Numbering forms offered a choosable date against NM-03 | their date is the fixed day of recording (IP-07, §12.2, DESIGN_SYSTEM §19) |
| R1-13 | LOW | Numbering confirmations omitted HO-39's facts | repeated, expanded, in every start and seed confirmation (§12.2; PT-26) |
| R1-14 | LOW | The company filter dropped items without a company | kept and labelled (§9.2; SCR-07) |
| R1-15 | LOW | Routed refusals and stale counts were labelled "Sistem" | the actor is named; "Sistem" only where no account acted (PT-18; §14.4) |
| R1-16 | LOW | Some signal links led to screens the audience may not open | links per capability (§9, §9.1) |
| R1-17 | LOW | *Pengadaan* read as if only its costs were gated | "`cost.view` (absent without it)" (SCR-10) |
| R1-18 | LEVEL-1 | The GAP-034 note omitted LG-02's fallback write point | mirrored |
| R1-19 | LEVEL-1 | "Admins have no security-log view at all" | settlement e) reworded |
| R1-20 | LEVEL-1 | J-FIN-05 omitted "and company" | added |
| R1-21 | LEVEL-1 | MSG-14 had no variant for missing allocation authority | variant added |
| R1-22 | LEVEL-1 | "Tutup Kasus" under the wrong capability in J-PUR-03 | `cases.close` |
| R1-23 | DEFERRED (P8) | The oracle tests named neither the rendition match nor the dry-run rows | UXH-03, UXH-04 |

### R2 — interaction robustness

| ID | Class | Finding | Fix |
| --- | --- | --- | --- |
| R2-01 | MEDIUM | A second trigger could mint a second identifier | an identifier only when no intent is open; a further trigger is dropped or resends the open one (IP-02; UXS-01) |
| R2-02 | MEDIUM | A non-terminal answer to a retry of an open intent was shown as "not processed" | the intent stays open with MSG-07; MSG-06 and MSG-20's "nothing processed" only for a first attempt (IP-02, IP-04, IP-01; MSG-06, MSG-20; UXS-08) |
| R2-03 | MEDIUM | "Nothing was sent" could not be known after dispatch | only when the browser was offline before dispatch; every later failure is the unknown branch (IP-01; MSG-13; UXH-02) |
| R2-04 | MEDIUM | The re-authentication dialog could not identify the account under WS-07 | e-mail and password; the shared props compared, a full document load on any difference (§6; MSG-11; PT-28; UXS-03; UXH-02) |
| R2-05 | LOW | The step-up had no answer that keeps input | a non-redirect answer intercepted like a 401, then the same identifier resent (IP-01, IP-10; UXH-02, UXH-11) |
| R2-06 | LOW | MSG-08 promised a retry after the fifth attempt | exhausted variant (IP-12; UXS-09) |
| R2-07 | LOW | Framework inferences unlabelled | labelled; thumbnails background by route; UXH-02 extended (IP-11, §6) |
| R2-08 | LOW | No absolute-limit warning for late pages; an open intent dropped silently | MSG-34 at once on late pages and with MSG-07's warning; residual in GAP-015 (IP-11) |
| R2-09 | LOW | Defect answers reused "system busy" | defect variant of MSG-06 (IP-04) |
| R2-10 | LOW | Re-apply could revert another editor's lines | only the user's changed fields and lines are carried (IP-03) |
| R2-11 | LOW | Master-key collisions treated as "already recorded" | own row: key field marked, input kept (IP-03) |
| R2-12 | LOW | A command's unresolvable reference had two presentations | field error for references, not-found page for addresses (IP-01) |
| R2-13 | LOW | A job-time row bound had no presentation | *Gagal* with MSG-21's text; P11 chooses where it is checked (IP-12; UXS-36; UXH-11) |
| R2-14 | LOW | SCR-10's loading model was unstated | tabs as optional props; strip facts in the first response (SCR-10) |
| R2-15 | LOW | Merged multi-table lists had no registered key | one source or kind per tab, each on its own key (SCR-07, §9.2, SCR-45; Section 12 channel) |
| R2-16 | LOW | No face for DP-18's refusal | part of R1-03 |
| R2-17 | LOW | Dot stripping could turn 3.5 into 35 | a dot only between groups of three; otherwise a validation error (IP-13) |
| R2-18 | LEVEL-1 | PN-3 said termination reloads | aligned with D-UX-03 |
| R2-19 | LEVEL-1 | "Kirim Ulang" beside "Coba Lagi" | "Coba Lagi" throughout |
| R2-20 | LEVEL-1 | "retry" where ST-08 says "replay" | corrected (IP-07) |
| R2-21 | LEVEL-1 | Not-found and forbidden rows lacked the reference code | added (IP-21) |
| R2-22 | LEVEL-1 | MSG-15's variant was payment-specific | "{catatan}" |
| R2-23 | LEVEL-1 | MSG-29 covered one credential event | a variant per kind |
| R2-24 | LEVEL-1 | PT-25's coverage narrower than HO-08 | every command form, numbering showing a fixed date (§19) |
| R2-25 | LEVEL-1 | SCR-23 and SCR-24 filters unmarked | "(bounded)" |
| R2-26 | LEVEL-1 | IP-08 claimed validation before the dialog | "format-checked"; a server error returns to the form (IP-08; PT-26) |

### R3 — usability and the design system

| ID | Class | Finding | Fix |
| --- | --- | --- | --- |
| R3-01 | MEDIUM | A scanner's Enter could confirm an open dialog | bursts outside scan-accepting fields discarded with their Enter; dialogs open on "Kembali" or the first input (§13.2; PT-16, PT-26; IP-08, IP-15; UXS-35, UXS-41) |
| R3-02 | MEDIUM | Bands mixed width and input | width-only bands; a coarse pointer gets touch sizes and the scan dock at any width (§1, §5.3, §6, §11, §15; ADMIN_FLOW §1) |
| R3-03 | MEDIUM | The Owner's link-used notice had no place | as R1-10 |
| R3-04 | LOW | Session and step-up dialogs could not stack | they stack above any overlay; confirmation first, then step-up (PT-16; IP-10; §13; J-ACC-02) |
| R3-05 | LOW | KB-08 collided inside pickers; "every shortcut has a modifier" untrue | KB-08 on the line number only; widget keys stated (§13.1; UXI-42; UXS-41) |
| R3-06 | LOW | "Kurangi" named two commands | "Kurangi Reservasi", "Catat Penyesuaian Stok" (GL-080, GL-086; screens; journeys) |
| R3-07 | LOW | Some traceability rows cited unrelated UXI; D-UX-02 and D-UX-10 lacked DPB-16 | rows 16, 23, 25, 26, 32, 33, 34 and 44 corrected; DPB-16 cited |
| R3-08 | LOW | SCR-40 kept ageing buckets D-IA-15 rejected | dropped; ageing on the receivables report |
| R3-09 | LOW | PT-31 showed a state by shape only | the state's label added |
| R3-10 | LOW | Scan feedback was not announced | polite and assertive live regions (PT-20; IP-15; §15) |
| R3-11 | LEVEL-1 | "Kirim Ulang" | as R2-19 |
| R3-12 | LEVEL-1 | UXS-08 forbade a word MSG-07 contains | "never asserts saved or not saved" |
| R3-13 | LEVEL-1 | *Lengkap*/*Belum* had no family; marks undefined; shapes reused across meanings | readiness family; "Marks are not states"; `hourglass` for *Diminta*, `archive` for closed states (§12.1) |
| R3-14 | LEVEL-1 | Phone confirmations had two rules | full-height for irreversible, correcting, step-up and re-authentication; bottom sheet for the rest (PT-16; §11) |
| R3-15 | LEVEL-1 | Action labels broke the copy rules | normalized — correction entries, "Buat Proyek", "Buat Pembelian", "Buat Invoice", "Catat Jawaban Klien", "Buat {Objek}", "Konfirmasi Kata Sandi" |
| R3-16 | LEVEL-1 | Glossary gaps and case | Label column case-neutral; GL-189 *Pengadaan*, GL-190 *Urungkan*, GL-191 *Pratinjau Akses*; "Sudah ditagihkan" |
| R3-17 | LEVEL-1 | Token and pattern drift (a)–(i) | each made one statement (PT-09, §14.2, `--sidebar`, seven styles, unit format, PT-02, §19, UXS-27, the sketch date) |
| R3-18 | LEVEL-1 | Recovery paragraph and lint counts contradicted the session table | six briefs listed; "19 findings (18 warnings, 1 info)" |

### Verification of the fixes

Each reviewer then verified the fixes of its own findings in one targeted pass (2026-10-02) — every HIGH and MEDIUM fix checked for correctness and for defects the fix itself introduced, every LOW and Level-1 fix confirmed once, and each UXS row it had failed re-tested.

| Reviewer | HIGH and MEDIUM fixes | LOW and Level-1 fixes | Introduced by the fixes | Failed UXS rows re-tested |
| --- | --- | --- | --- | --- |
| R1 | 10 of 10 RESOLVED, R1-02 as a recorded residual | 13 of 13 OK | N-1 LEVEL-1 — IP-16 tied the stored flag to the uploader rather than to the viewer; N-2 LOW — the Owner notices claimed every LG-07 alert as a single event; N-3 LOW — the evidence resolution did not say "without costs" (PJ-07); N-4 LEVEL-1 — GAP-016's note lacked the numbering exception | 18, 19, 27, 38, 39 PASS |
| R2 | 4 of 4 RESOLVED | 22 of 22 OK | LOW — a denial answering a retry showed only MSG-07; LOW — the numbering commands' fixed date was not said to travel as a prefilled date; LEVEL-1 — the navigation guard unlabelled, IP-21's "or reference", "the password is accepted" in two places, the gate's key wording for *Keamanan*; one residual recorded — two accounts with identical display name, capabilities and companies pass the same-account check (§6; UXH-02) | 01, 03, 04, 05, 08, 09, 26, 36 PASS |
| R3 | 3 of 3 RESOLVED | 14 of 15 OK; R3-17 item (i) became N2 | N1 LEVEL-1 — four places still tied touch size to the phone band; N2 LEVEL-1 — the sketch date lacked its leading zero; N3 LEVEL-1 — the discarded-scan notice named a scan field on screens without one; N4 LEVEL-1 — two rules for a confirmation's initial focus | 03, 05, 08, 26, 27, 35, 41, 42 PASS |

The verification found nothing at MEDIUM or above, so no further round ran (DIR-033 §18). Its LOW 4 and LEVEL-1 10 findings were fixed — IP-16 records the flag always and shows it per viewer; G1 derives only the single-event alerts and hands the aggregate ones to P9 (H9-10; UXH-09); "with their quantities and without costs"; a retry's denial shown beside MSG-07 with "Tinggalkan"; the numbering date carried as a prefilled date, so midnight returns CONFLICT; the wording, sizing and focus rules made one — and each reviewer confirmed its items OK in one narrow final check.

## UX scenario re-test

Every scenario of [ADMIN_FLOW §15](ADMIN_FLOW.md#15-mandatory-ux-scenarios) was re-tested once against the final text, after the verification fixes, combining the reviewers' re-tests of the rows they had failed with a full pass over the rest.

| UXS | Result | Verified against |
| --- | --- | --- |
| UXS-01 | PASS | IP-02 mints an identifier only when no intent is open; the busy control (PT-11); one recorded outcome |
| UXS-02 | PASS | IP-04 "Coba Lagi" with the same `command_id`; CI-05 returns the recorded COMMITTED |
| UXS-03 | PASS | §6 e-mail and password of the same account, a full document load otherwise; input and identifier in memory only; "Coba Lagi" |
| UXS-04 | PASS | §6: a never-sent form is a new intent meeting the stale checks of IP-03 |
| UXS-05 | PASS | §6: 419 as the ended session, the same identifier while the payload is unchanged; the dialog stacks above an open confirmation (PT-16) |
| UXS-06 | PASS | §6 and IP-11: full document load at the absolute limit and on logout, termination in place; history and caches cleared (H7-12) |
| UXS-07 | PASS | IP-04: MSG-06 on a first attempt, "Coba Lagi", inputs frozen |
| UXS-08 | PASS | MSG-07 never asserts saved or not saved; retries and post-dispatch failures keep the intent open (IP-01, IP-02) |
| UXS-09 | PASS | IP-12: MSG-08 with its count and the exhausted variant; the number unchanged; no re-issue control |
| UXS-10 | PASS | IP-01 and IP-14: MSG-02 in business words, input kept, a new intent |
| UXS-11 | PASS | IP-03: comparison with the changed fields marked; re-apply carries only the user's changes |
| UXS-12 | PASS | IP-03: "Buka Catatan" only in scope, no re-apply; master keys have their own row |
| UXS-13 | PASS | IP-07: MSG-16 with both dates; a chosen date passes; the numbering commands' fixed date returns CONFLICT across midnight |
| UXS-14 | PASS | PT-25 and §12.1: the mark "Tanggal Mundur" and the period line before sending; both dates on the timeline (PT-18) |
| UXS-15 | PASS | IP-05: matches listed, a tick per match, the reason; the second submission names them |
| UXS-16 | PASS | IP-05 and MSG-15: the override with `warnings.override`, the advice without it |
| UXS-17 | PASS | IP-05 and IP-16: the warning with the linked evidence and the override reason |
| UXS-18 | PASS | IP-16: the same answer, and no flag, note or reason for a hidden rendition match; the FS-12 residual recorded (GAP-034; R1-02) |
| UXS-19 | PASS | Section 13 and DESIGN_SYSTEM §12.2: exactly MSG-18 under the neutral face; such rows under *Tidak Valid* |
| UXS-20 | PASS | INFORMATION_ARCHITECTURE §7 and PT-04: the plain relation marker, no identity, link or cost |
| UXS-21 | PASS | INFORMATION_ARCHITECTURE §7: a company label per row; counts and subtotals per company |
| UXS-22 | PASS | IP-06: MSG-14 and its authority variant; QS-20 for 14 days |
| UXS-23 | PASS | IP-21 and IP-12: the not-found answer; an export *Tidak Tersedia*, its download refused alike |
| UXS-24 | PASS | §6: MSG-19 alike for every cause; the dialog cannot be passed |
| UXS-25 | PASS | IP-21: the not-found page; no menu entry, badge or count |
| UXS-26 | PASS | IP-10: a non-redirect step-up answer, input kept in memory, the same identifier resent; above any confirmation |
| UXS-27 | PASS | PT-30: "Pratinjau Akses" before "Simpan Akses"; dependants "Nonaktif — prasyarat dicabut" with their grants kept |
| UXS-28 | PASS | Section 12: MSG-17 for the Admin; the readiness line; "Mulai Penomoran" with its facts and statements; the repeat succeeds |
| UXS-29 | PASS | Section 12: MSG-17 naming the period; the Owner's seed; the issue draws it |
| UXS-30 | PASS | §12.2: the refusal with the earliest date and the backward extension |
| UXS-31 | PASS | §12.2: the refusal naming and linking the batch |
| UXS-32 | PASS | §12.2: the commands absent and the state explained; no go-live control |
| UXS-33 | PASS | IP-17: MSG-24 and MSG-23 |
| UXS-34 | PASS | IP-15: MSG-37 with the candidates; the typed SKU and "Cari Produk" |
| UXS-35 | PASS | J-INV-01 and §13.2: one line at a time with its own answer; a scan during a confirmation discarded; touch sizes for a coarse pointer at any width; the desktop row form |
| UXS-36 | PASS | IP-12: MSG-21 and MSG-22; a job-time bound as *Gagal*; the five states |
| UXS-37 | PASS | J-PRJ-03: the snapshot, the reason and the CONFLICT shown again (ST-04) |
| UXS-38 | PASS | J-FIN-08: *Menunggu Pembayaran* until "Tautkan Kuitansi" or a void; the position block unchanged |
| UXS-39 | PASS | DESIGN_SYSTEM §14: the case's quantity on the pool views, a company's own exposure on its views; nothing blocked |
| UXS-40 | PASS | INFORMATION_ARCHITECTURE §9.2: every QS-14 source in its tab, open conditions, no approve or dismiss control |
| UXS-41 | PASS | PT-10 and §13: one focus order; no single printable key; scans outside scan-accepting fields discarded |
| UXS-42 | PASS | §12.1: label and shape for every state; no shape with two meanings in a family; marks are not states |
| UXS-43 | PASS | PT-21 and INFORMATION_ARCHITECTURE §9: signal rows and one figures table; no tiles, charts or greeting |
| UXS-44 | PASS | SCR-10: three deferred groups, the other parts on demand; no totals; polls of a minute or more as background requests |

**44 of 44 PASS.**

## Separate Fable review

**Separate Fable review not recommended:** the one HIGH finding, R1-01, was verified resolved from the documents by the reviewer who raised it; no visibility, oracle or authority disagreement remains between the author and a reviewer; the GAP-034 settlement amended approved documents only by narrow technical clauses; and every `[floor]` and `[both]` workflow has both phone and desktop patterns (DIR-033 §19 a–d).

A later targeted review against the built screens would add value — it can observe what documents cannot: timing classes of hidden matches, screen-reader announcements, focus under sticky regions and real scanner bursts. That belongs to P8 (UXH-03, UXH-05–UXH-07).

## Deferred obligations

Recorded in [ADMIN_FLOW §16](ADMIN_FLOW.md#16-handoff-obligations), their single home; none of these phases is authorized.

| Phase | Obligations |
| --- | --- |
| P8 | UXH-01 the forty-four scenarios as end-to-end tests; UXH-02 command identity and re-authentication, the in-memory survival of unsent input after an intercepted answer, a transport failure and a step-up proved on the chosen versions; UXH-03 denial and oracle presentation; UXH-04 the GAP-034 record proof extending H8-12; UXH-05 the WCAG 2.2 AA audit; UXH-06 real devices — phone, desktop, wedge scanners, printing; UXH-07 visual consistency and the anti-slop review on built screens; UXH-08 UAT of the copy, glossary and document wording with the Owner and Admin validators |
| P9 | UXH-09 the post-response write step of the two `TECH-022` event kinds and the background-request marker on the chosen runtime |
| P10 | UXH-10 numbering, go-live and the opening import at the cutover, with UAT |
| P11 | UXH-11 build units citing SCR, J, IP, PT, MSG and GL identifiers, the 401 answer to unauthenticated Inertia requests, the non-redirect step-up answer, the session-deadline timestamp and where the export row bound is checked; UXH-12 the DesainPakai statement of DIR-033 §11.11 — intended to be used again to translate the frozen designs into build units, under a separate Owner authorization, never a source of truth, no secret in the repository and no generated artifact committed without explicit repository governance |

## Owner-visible Level-1 choices

Choices the Owner will notice in daily use; each is Level 1 under DIR-033 §13 and changes no business rule.

| Choice | Where |
| --- | --- |
| The company filter sits in each list and on Beranda, never in the shell; forms always ask for the company | D-IA-02 |
| Beranda is a list of signals with per-company counts ("ARJ 4 · BTN 20+"), no charts or KPI tiles; the Owner switches *Tampilan* between *Gabungan* and one company | D-IA-07, D-DS-05 |
| Lists end with "Muat Lagi"; there are no totals, row counts or page numbers in lists | PT-08 |
| While the outcome of a command is unknown the form is locked; "Coba Lagi" sends the same request again and can never record it twice | D-UX-02, D-UX-04 |
| Every command form shows "Tanggal transaksi"; a backdated date is visibly marked | D-UX-05 |
| A duplicate warning is accepted by naming the matches and giving a reason | D-UX-06 |
| An expired session asks for the e-mail and password in place and keeps unsent input in memory; the 12-hour limit is announced fifteen and five minutes ahead and then signs out | D-UX-03 |
| The review queue (*Tinjauan Owner*) shows the last seven days by default, with a divider at the Owner's previous sign-in; nothing is marked read or approved there | D-IA-11 |
| Numbering shows what needs the Owner first; starting numbering and setting a first number require ticking the vouching statements | D-IA-13; ADMIN_FLOW §12.2 |
| Scanning uses a keyboard-wedge scanner; the phone camera is not a barcode scanner in V1 | D-UX-09 |
| Receiving on a phone is a checklist of the purchase's lines with the scan field at the bottom; the reservation for the project is part of the same receipt | D-UX-11, D-UX-12 |
| Shortcuts: Ctrl+K search, Ctrl+/ shortcut help, Ctrl+B navigation, Alt+N new line; no single-key shortcuts | DESIGN_SYSTEM §13 |

## Gap reconciliation

P7 continuation notes are recorded in GAP-015, GAP-016, GAP-019, GAP-020, GAP-029, GAP-032 and GAP-034, and were brought in line with the fixes after the reviews and after the verification: GAP-015 records that an open intent lives in memory only and is lost with the page, after a warning; GAP-016 the numbering commands' fixed date; GAP-034 LG-02's write point and the FS-12 residual the Owner accepted. No gap is closed — the proof of each belongs to P8, P9 or P10 — and none is new: every failure scenario the reviews found is either fixed in the design or recorded as a residual in an existing gap. Totals: **34 findings — 3 CLOSED, 30 OPEN, 0 OWNER_DECISION_REQUIRED, 1 ACCEPTED_RISK**.

## Static validation

A read-only validation script kept outside the repository, following the prior gates, checked: every relative link and anchor in all Markdown files; lifecycle metadata and approval lines, with the three P7 specifications APPROVED under APPR-008; the 29 source records against SOURCE_OF_TRUTH, the 28 earlier records unchanged, record 29's LF endings and `-text` attribute; every P7 identifier family — UXN, GL, SCR, D-IA, UXI, IP, J, MSG, UXS, UXH, D-UX, UXV, PT, KB, SLOP, D-DS, DPB, DPS — defined once, contiguous and referenced (ranges expanded), and every identifier the P7 files cite resolving in its owner, every capability included; the orphan checks — every workflow with a journey, every QS with a home, every H7 and P7 HO item with a home; every screen with its capability and projection sources; every status row with a label and a shape; no list promising totals or page numbers, no sort or filter key outside PF-42, IX-11, IX-13 and IX-14 or the bounded form, and no page above its deferred-group limit; storage APIs only in prohibitions; the GAP-034 ordering evidence; the DesainPakai evidence — readiness naming MULTIPLECORP without a secret, 46 traceability rows, 12 major surfaces, every adopted decision mapped to a normative section and no rejected proposal in the normative files; gap triage against the detail sections and every totals sentence; every OWNER_DECISION_REQUIRED value; the four `TECH-022` amendments against their pre-amendment hashes; a placeholder scan and a secret-like scan of every added or new line; `git diff --check`; the changed-path census, with no binary, image, HTML, CSS, script, DesainPakai, P8+ or application file; and, in the staged mode, the APPR-008 hashes against the staged blobs.

**Results (final run, 2026-10-02) — PASS, 53 of 53 checks, in the working and the staged mode:** 1,097 relative links and anchors resolve across 40 Markdown files; lifecycle metadata valid — the three P7 specifications APPROVED with their APPR-008 approval lines, this gate REVIEW; 29 source records match their recorded hashes, the 28 earlier ones byte-identical to HEAD, and record 29 with LF endings and `-text`; eighteen P7 identifier families — UXN 26, GL 191, SCR 60, D-IA 17, UXI 42, IP 21, J 39, MSG 41, UXS 44, UXH 12, D-UX 12, UXV 22, PT 31, KB 11, SLOP 45, D-DS 9, DPB 18, DPS 29 — each defined once, contiguous and referenced; 633 distinct cited identifiers resolving in their owners, every cited capability among the 80 of the catalogue; 39 of 39 workflows with a journey; 22 of 22 signals placed; H7 14 of 14 and P7 HO 12 of 12 with a home; 44 of 44 scenarios documented; 60 complete screen rows; deferred groups within PF-34; 62 status rows each with a label, tone and shape; no list promising totals or page numbers; storage APIs only in prohibitions; the gap register with 34 rows and 34 sections, its totals sentence current everywhere and every OWNER_DECISION_REQUIRED value 0; 25 changed paths, all within the census, and no binary, generated, P8+ or application file; no secret-like string or placeholder in the added or new lines; `git diff --check` clean; P1, P2 and the untouched P4–P6 specifications unchanged; every added line of the four amended documents marked `TECH-022` or part of its amendment note, with the four pre-amendment hashes equal to the committed blobs; the readiness evidence naming MULTIPLECORP and the context revision; 46 of 46 traceability rows, each with its brief, decision and normative section; 12 of 12 major surfaces; every brief with session evidence; no rejected proposal in the normative files; the GAP-034 ordering evidence; and the APPR-008 hashes equal to the LF working blobs and, after staging, to the staged blobs.

## Changed-file census

25 paths — 20 modified (`.gitattributes`, AGENTS.md, CHANGELOG.md, CLAUDE.md, README.md, AGENT_OPERATING_MODEL, CHANGE_CONTROL, DECISION_LOG, ENGINEERING_PRINCIPLES, GAP_REGISTER, PROJECT_CHARTER, SOURCE_OF_TRUTH, CONTEXT_INDEX, SECURITY, DATABASE, PERFORMANCE, CONCURRENCY_IDEMPOTENCY, P6_QUALITY_GATE, CURRENT_STATE, NEXT_ACTION) and 5 new (source record 29, INFORMATION_ARCHITECTURE, ADMIN_FLOW, DESIGN_SYSTEM and this gate). SECURITY, DATABASE, PERFORMANCE and CONCURRENCY_IDEMPOTENCY change only by their `TECH-022` clauses, their amendment note and their date; P6_QUALITY_GATE only by its dated addendum; CLAUDE.md, AGENT_OPERATING_MODEL and CHANGE_CONTROL only in their approval pointer line and their date, following the APPR-004–APPR-007 precedent. PERMISSIONS_MATRIX, WORKFLOWS and every product and domain document are unchanged. The P7 finalization checkpoint commits exactly these 25 paths.

## Gate result and limitations

**P7 planning-quality result: PASS.** Every DIR-033 §10 item is designed or handed to its owning phase, all forty-six DesainPakai surfaces are traced, all self-review and independent findings are fixed or recorded as deferred obligations, and the re-test passes 44 of 44. **APPR-008 conditions verified:** CRITICAL, HIGH, MEDIUM and LOW unresolved = 0; OWNER_DECISION_REQUIRED = 0, the FS-12 residual of R1-02 having been accepted by the Owner in session; no separate Fable review triggered under DIR-033 §19; the GAP-034 settlement completed and recorded before the Owner review queue was designed or explored, with its ordering evidence; the DesainPakai exploration completed and evidenced — 46 of 46 surfaces, the 12 major surfaces with alternatives, the final consistency and anti AI-slop review run to Approve; phone and desktop patterns for every workflow that needs them; every H7 and P7 HO item with a home; every coverage item designed, handed over or N/A with reason; every UXS scenario documented and re-tested PASS and all validation passing; no known contradiction; P0–P6 semantics preserved with only the narrow `TECH-022` amendments; no DesainPakai output, configuration or credential in the repository; documentation only; no P8 work.

**Limitations.** The specifications are documents: what only running software shows — timing classes, screen-reader announcements, focus under sticky regions, real scanner bursts, the framework behaviours labelled inferred in [ADMIN_FLOW §2](ADMIN_FLOW.md#2-source-and-version-evidence) — is proved in P8 (UXH-01–UXH-08). The DesainPakai pages are exploration evidence held outside the repository, never a source for build units; three LOW icon differences remain on one prototype page only (DPB-18). The visual export could not be read back above 512 KB and was replaced by a local reconstruction, as the exploration section records. One residual was accepted by the Owner, knowingly and in session on 2026-10-02: the FS-12 requirement state (GAP-034; R1-02). The others are recorded residuals of the design, not Owner risk acceptances, and their gaps stay OPEN for P8 proof: an open intent lost with its page after a warning (GAP-015), a security event lost when its own write fails (GAP-034), an Admin's refusal for an earlier unseeded period that reaches the Owner only through the Admin (GAP-016), and two accounts with identical display name, capabilities and companies passing the same-account check ([ADMIN_FLOW §6](ADMIN_FLOW.md#6-session-expiry-re-authentication-and-unsent-input); UXH-02).

## Exact next safe action

Wait for the Owner's explicit authorization of P8 — Testing & Quality Strategy ([NEXT_ACTION](../07-handoff/NEXT_ACTION.md)). At P8 entry, verify that local HEAD equals live origin/main at the P7 checkpoint — resolved by `git log -1 --format='%H %s' --grep='^docs: finalize P7 UX, information architecture and design system$'`, parent `ff92c415c164f9fea3758fead256a2df53a74211` — and record its SHA literally. No P8 work before that.

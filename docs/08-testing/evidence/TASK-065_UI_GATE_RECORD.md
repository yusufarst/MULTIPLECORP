# TASK-065 UI/UX consistency gate record

Status: REVIEW | Updated: 2026-11-15 | Owner: Planning

*Written by the executor of [TASK-065](../../11-tasks/TASK-065.md) under [APPR-038](../../00-governance/DECISION_LOG.md#dir-123-and-appr-038--task-065-narrowed-for-the-initial-release-2026-11-15). It records AICWDF §27's gate on the built pages of the CI stack ([TEST_STRATEGY §9](../TEST_STRATEGY.md#9-the-uiux-consistency-gate)), the composer's tolerance, DIR-079 CD-06's screens and the anti-slop review SLOP-01–SLOP-45 ([DESIGN_SYSTEM §18](../../07-ux-design/DESIGN_SYSTEM.md#18-anti-ai-slop-mapping)). Each finding is recorded with its page, item and evidence for the Owner's decision; none changes a token, pattern, rule or page here (criterion 9). It is evidence, not a specification: where it and a source differ, the source wins.*

## 1. How the gate was run

- **Where:** the localization audit's own Indonesian visit of each page (`tests/Browser/localization-audit.ts` calls `gate` in `tests/Browser/ui-gate.ts`), one visit per page and engine, on Chromium and WebKit in the localization layer; the composer's tolerance in the responsive layer (`tests/Browser/Responsive/composer-tolerance.spec.ts`); CD-06's screens in the e2e layer (`tests/Browser/E2E/cd06-screens.spec.ts`, CJ-02, CJ-07).
- **The records:** per page and engine `storage/test-results/browser/gate/<engine>/<place>.json`, the screenshots at 390 and 1,280 px on Chromium in `gate/screens/`, the composer's comparison in `gate/composer/<engine>/<layout>.json` — each kept in its layer's `browser-evidence-<layer>` artifact of the run named in §2.
- **Measured items:** the computed tokens against every custom property of `resources/css/tokens.css` (colour, type, radius, elevation, motion, spacing on padding and gap); one primary action per region; duplicate actions outside rows, lists, menus and a field's own controls; the deferred and empty states; the responsive matrix — 320, 390, 767, 768, 1,024, 1,025, 1,200, 1,279, 1,280 and 1,680 px on Chromium, 390, 768 and 1,280 px on WebKit, the other WebKit widths NOT TESTED (APPR-038) — with horizontal page scrolling outside a table frame and mandated copy cut under WCAG 2.2 1.4.12's text spacing; a coarse pointer's targets at 390 px.
- **Reviewed items:** modern visual quality and SLOP-01–SLOP-45 on the screenshots with each page's measured marks; content density against AICWDF §18.2.
- **Elsewhere:** back action and navigation contract (TEST_STRATEGY §8's NAV- tests), the language items (TASK-067), destructive action safety (each INT- contract, TL-08) and the accessibility critical checks (TASK-066) keep their places, entered by TASK-070.

## 2. The runs

- **The work's evidence:** CI run 43685052334 on `8bb6ada` — every job PASS: Verify (TL-01–TL-08, TL-13, TL-14), the browser layers e2e, localization, responsive and accessibility with their aggregate, performance and migration (TL-15, TL-16) and the deployment definition. The gate records, screenshots and the composer's comparison of §§3–5 are that run's `browser-evidence-localization` and `browser-evidence-responsive` artifacts.
- **Earlier runs of the branch, kept as they were:** on `dd4c44a` (run 43673361767) and `f60723d` (run 43680844199) every browser layer passed and the performance job reached its 30-minute limit at 100 of 103 tests — the three unreported being one file's, `tests/Performance/IndexRegisterTest.php` the likely one (§7 GF-14); `dd4c44a` changed no PHP. On `65e3fb6` (run 43676333398) `tests/Browser/E2E/handover.spec.ts` on WebKit waited past 5 s for the redirect after *Catat Serah Terima*, its request still in flight and no error shown, and passed in the two runs after; it is recorded here, not classified away.
- **The reader's last correction:** the three dialog records of §3's *Design system consistency* read `--shadow-modal` without its 1 px ring, which the reader set apart from the element's shadow but not from the token's; this record's commit reads both alike, and its own CI run's localization records are the reading of the corrected reader.

## 3. The gate's items on the 65 pages

Each page's record holds AICWDF §27's twenty-one items. A page visited in several states — a dialog open, a sheet open, a second company — has a record per state: 85 records per engine. Counts are records, Chromium first, WebKit after; WebKit's widths are 390, 768 and 1,280 px and its coarse pointer is Chromium's (APPR-038).

| §27 item | Chromium | WebKit | Note |
| --- | --- | --- | --- |
| Modern visual quality | REVIEW 85 | REVIEW 85 | §5 |
| Design system consistency | PASS 82, FAIL 3 | PASS 82, FAIL 3 | The three are PG-011's two dialogs and PG-020's: `--shadow-modal` less its ring layer (`tokens.css`), the reader's asymmetry corrected in this record's commit (§2). No other value outside the tokens on any page |
| Primary action clear | PASS 85 | PASS 85 | At most one primary action in every region |
| Unnecessary duplicate actions: 0 | PASS 84, FAIL 1 | PASS 84, FAIL 1 | PG-056: *Ubah Harga* three times in one section (GF-04) |
| Back action clear; navigation contract valid | ELSEWHERE | ELSEWHERE | TEST_STRATEGY §8's NAV- tests (TL-09) |
| Indonesian default; plain Indonesian; English available; missing translations: 0; language switch | ELSEWHERE | ELSEWHERE | TASK-067, entered by TASK-070 |
| Mobile-first | PASS 85 | PASS 85 | The phone composition at 320 and 390 px (WebKit 390) without horizontal page scrolling outside a table frame |
| Responsive | PASS 85 | PASS 85 | All ten band edges on Chromium, three on WebKit; no horizontal page scroll outside a table frame and no mandated copy cut under 1.4.12 at any width. The review found cuts outside D-DS-18's four kinds of copy (GF-06, GF-07) |
| Adaptive | PASS 35, FAIL 32, NOT TESTED 18 | Chromium's | The 18 are later states of a page already measured at its first; the 32 FAIL are 32 pages with controls below 44 px under a coarse pointer at 390 px (GF-02, GF-03) |
| Loading, empty and error states | Loading PASS 85; Empty PASS 31, FAIL 1, NOT TESTED 53; Error NOT TESTED 85 | Loading PASS 85; Empty PASS 29, FAIL 1, NOT TESTED 55 | No region left loading once a visit settled; every empty state shown is one sentence with at most one action, but PG-005's, whose icon, larger than 24 px, the reader counts as a picture (GF-08; SLOP-23 REVIEW); an empty state where the data had none and the error states are NOT TESTED (§8) |
| Destructive action safety | ELSEWHERE | ELSEWHERE | IP-08's confirmation with each INT- contract (TL-08) |
| Accessibility critical checks | ELSEWHERE | ELSEWHERE | TASK-066, entered by TASK-070 |
| Visual breathing room | PASS 85 | PASS 85 | Every padding and gap a token |
| Content density appropriate | REVIEW 85 | REVIEW 85 | §6 |

## 4. The composer's tolerance and the CD-06 screens

**The composer and preview** (criterion 6; TEST_STRATEGY §9; D-DS-23; GAP-053). Each check of §9 and where it is proven:

| Check | Result | Evidence |
| --- | --- | --- |
| A draft never satisfies a requirement and never writes | PASS | `tests/Feature/ADM/RequirementsTest.php` — a draft prepares a checklist item and never satisfies it, nor is it linked (BR-ADM-01); CJ-20 (`tests/Browser/E2E/journey-cj20.spec.ts`) on the page; CJ-15 — no command leaves PG-036 while the draft is composed at any width (`tests/Browser/E2E/journey-cj15.spec.ts`) |
| Its totals labelled provisional, then equal to the server's | PASS | `DocumentRenditionsTest.php` — the composer's provisional totals held to PostgreSQL's numeric arithmetic (`tests/Frontend/provisional-totals.json`) |
| Only projected fields reach the browser | PASS | `tests/Feature/DOC/DocumentsTest.php` — the draft's paper marked and unnumbered with the logo at most, the stamp and the signature never in a draft's props (PJ-19; DC-4) |
| The paper Indonesian in both interface languages | PASS | `tests/Unit/DOC/DocumentPaperTest.php` — each layout's paper the same in either language |
| The desktop's form beside the live preview at 1,025, 1,279 and 1,280 px, the tablet's toggle at 1,024 px, the phone's Edit → Preview → Finalize at 390 px | PASS | `tests/Browser/Responsive/documents.spec.ts` (PG-036), `invoices.spec.ts` (PG-042, PG-043), `quotations.spec.ts` (PG-014), `handover.spec.ts` (PG-033); CJ-15 on PG-043 and PG-036 at all five widths |
| The same template, paper and blocks in the same order as the issued rendition | PASS | `DocumentRenditionsTest.php` — each of DOC-01–DOC-14's draft and issued papers alike but for the marking, the number line and the signature block; the browser composes the same template (`resources/js/features/doc/paper.ts`) |
| **Each block's position within 1 mm** | **FAIL — DOC-09; the other layouts NOT TESTED** | `tests/Browser/Responsive/composer-tolerance.spec.ts`: DOC-09's draft preview on PG-036 against the issued rendition of the same content laid out by WeasyPrint (`tests/Support/Documents/layout.py`), 30 blocks each, the same blocks in the same order, one page each, A4 210 mm — every block off by up to **12.27 mm**: the whole layout shifted **−2.11 mm** across and **+7.52 mm** down, and **4.75 mm** of difference left within it once that shift is removed. Record: `gate/composer/<engine>/DOC-09.json` |

The two causes, both in the files every layout shares: **(1)** the issued document's wrapper (`MustacheTemplates::document`) and `paper.css` leave the body's default 8 px margin, so the renderer sets every block 2.12 mm right of and below the page's margins of `@page document` (18 × 16 mm), and its text column is 4.23 mm narrower than the preview's, which wraps lines differently; **(2)** the draft's marking *DRAF — belum terbit* stands in the flow on screen (`margin: 0 0 6mm`), pushing every block down — in print `paper-draft` adds 14 mm of padding instead —, so the preview never sits where the issued paper will. A minimum fix for the Owner's decision: `body { margin: 0 }` in the issued wrapper or `paper.css`, and the marking laid over the top margin rather than in the flow; neither is changed here (criterion 9).

**The screens the Owner's decisions changed** (criterion 7; DIR-079 CD-06; TEST_STRATEGY §9):

| Decision | Page | Result | Evidence |
| --- | --- | --- | --- |
| CA-06 | PG-010 *Proyek* | PASS | `tests/Browser/E2E/cd06-screens.spec.ts` (CA-06): a project's row shows its stage with *(langkah n dari m)*, *Terhambat* for an open correction case and its next step, after the journey group arrives (QB-01); PFM-04's count in `tests/Performance/ProjectsQueriesTest.php` |
| CA-07 | PG-007 *Pekerjaan*; PG-023; PG-024 | PASS | `tests/Browser/E2E/journey-cj07.spec.ts`: the four units reserved at receipt stand on PG-007 under *Barang siap dikeluarkan* (QS-24), then PG-023 lists the line and PG-024 dispatches it, on the phone and the desktop |
| CA-08 | PG-065; the account menu | PASS | `tests/Browser/E2E/journey-cj18.spec.ts` — English through the account menu, kept across a new sign-in; `users.locale` in `tests/Feature/Account/OwnAccountTest.php` |
| CA-09 | PG-017; PG-016 | PASS | `tests/Browser/E2E/journey-cj02.spec.ts`: no business number on PG-017 before the save, the PURCHASE scheme's `UJI/BELI/<year>/<nnnn>` on PG-016 at it; C-10 in `tests/Feature/PUR/PurchasesTest.php` |
| CA-10 | PG-006 *Beranda* | PASS | `cd06-screens.spec.ts` (CA-10): an ACTIVE project three days past its deadline in the Owner's *Perlu keputusan Anda* under *Proyek melewati tenggat*, *lewat 3 hari*; the Admin's QS-23 empty in `tests/Feature/Reporting/HomePagesTest.php` |
| CB-01 | PG-058 *Impor Data Awal* | NOT RUN — CJ-16 is staging only (TEST_STRATEGY §7) until TASK-081 | The rule behind the screen: `tests/Feature/Import/OpeningImportPagesTest.php` — a kept legacy purchase number at or above its period's seed refused by the dry run (NM-08); `OpeningImportFlowTest.php` — an imported purchase keeps its number |
| CB-02 | PG-061 *Penomoran Dokumen* | NOT RUN — CJ-16 on staging (TASK-081); CJ-19 sets up PROJECT, not PURCHASE | `tests/Integration/Numbering/NumberingPeriodsTest.php` — a PURCHASE sequence seeded by the import draws after the last legacy number |
| CB-03 | PG-006 *Beranda* | PASS | `cd06-screens.spec.ts` (CB-03): one company's view shows *Piutang aktif* Rp1.000.000 in the figures strip and the ageing chart with its amounts on the bars and the same row in its table (VP-6); QB-10's count in `tests/Performance/HomeQueriesTest.php` |

## 5. The anti-slop review, SLOP-01–SLOP-45

Reviewed on the gate's screenshots of all 65 pages at 390 and 1,280 px — `gate/screens/PG-nnn-390.png` and `PG-nnn-1280.png` — against DESIGN_SYSTEM §18's preventing rules with GA1 d)'s dispositions (the REVISED and SUPERSEDED rows govern), each page's measured marks in its gate record (`slop`) and, for motion, the code. **FINDING** is a deviation from the rule as disposed, for the Owner's decision; **REVIEW** is a reading the reviewer could not settle, also for the Owner; **N/A** carries its reason. *Beranda*'s UXS-43 audit is CJ-22's (`tests/Browser/E2E/journey-cj22.spec.ts`, `antiSlop`), run in the e2e layer.

| SLOP | Result | Evidence and note |
| --- | --- | --- |
| SLOP-01 | FINDING | PG-011 at both widths: six sections of the one project — *Yang tersisa sebelum selesai*, *Kesiapan dokumen*, *Item dan pemenuhan*, *Posisi keuangan*, *Rincian proyek*, *Riwayat* — each in its own card; PG-016 shows sections of the same kind without cards. Every other page: space and rules group content |
| SLOP-02 | PASS | No card inside a card; REVIEW — the tinted note block inside the dialogs of PG-011 and PG-020 |
| SLOP-03 | PASS | No giant container; cards about 10 px, dialogs 12, the phone sheet's top corners 16 (PG-043-390) |
| SLOP-04 | PASS | Nothing above 12 px but the sheets' top corners and the badges |
| SLOP-05 | FINDING | The company tag (`CompanyTag`, an outline `Badge`, `rounded-full`) is a pill on an identifier, not a state or a count: PG-006's rows, PG-010's *Perusahaan* column, PG-051, PG-052, PG-059; PG-015 and PG-010 on the phone print the same code as text. REVIEW — *Ambil Lebih Dulu* on the first lot of PG-019, PG-024 and PG-027 is a picking recommendation in a pill |
| SLOP-06 | PASS | Flat fills; no gradient on any page (and the measured marks) |
| SLOP-07 | PASS | As SLOP-06 |
| SLOP-08 | PASS | Focus rings are crisp 3 px olive rings; no glow |
| SLOP-09 | PASS | Lime only as a fill under dark ink — *Buat*, the current navigation item, the bottom bar's marker |
| SLOP-10 | PASS | Opaque bars, dialogs and sheets; the scrim is a flat tint |
| SLOP-11 | PASS | The canvas `#f7f8f3`, plain |
| SLOP-12 | PASS | No decorative shape; the "DRAF" watermark is the paper's |
| SLOP-13 | PASS | Every empty state is text only; PG-005's line icon is no illustration |
| SLOP-14 | PASS | The resting shadow on the preview frame and overlays only |
| SLOP-15 | PASS | Motion only as feedback: no `@keyframes` in `resources/` but Tailwind's spinner (`animate-spin`, a loading state); transition durations are measured against the motion tokens in each gate record |
| SLOP-16 | PASS | No parallax: no scroll-linked animation in `resources/`; `--mc-auth-parallax` is defined in `tokens.css` and unused |
| SLOP-17 | PASS | Row menus "⋯" and row actions visible at rest (PG-025, PG-039, PG-044, PG-062–PG-064); hover never the only path |
| SLOP-18 | PASS | Four key figures in one strip on the Owner's *Beranda*, without deltas (PG-006); figures elsewhere are inline text |
| SLOP-19 | PASS | No banner |
| SLOP-20 | PASS | No greeting; the sign-in's heading *Masuk ke MultipleCorp* |
| SLOP-21 | PASS | Operational copy only |
| SLOP-22 | PASS | No emoji (and the measured marks) |
| SLOP-23 | REVIEW | Icons that may repeat their text: PG-005's 24 px icon above *Halaman Tidak Ditemukan*; PG-043's file icon before the invoice link under *Dokumen*; a send icon on *Terbitkan …* (PG-033, PG-036, PG-042), where the action issues; on the phone a back arrow both in the app bar and before the title (PG-033, PG-036, PG-042, PG-043, PG-046, PG-050, PG-052, PG-053) |
| SLOP-24 | PASS | Most buttons are text only; icons on create, overflow, upload, password, calendar, search, print, full screen and scan |
| SLOP-25 | N/A | SUPERSEDED by the reference's density (GA1 d)); content density is reviewed in §6 |
| SLOP-26 | FINDING | PG-005 on the phone: the not-found heading at about 32 px, wrapping to two lines, where every other phone title is about 25 px; desktop titles about 32 px throughout, no display size outside the sign-in |
| SLOP-27 | PASS | Breadcrumb, title and facts line; no hero |
| SLOP-28 | REVIEW | *Beranda* keeps five groups (QB-10), but inside them thirteen signal groups, most showing only their empty line (PG-006) |
| SLOP-29 | FINDING | Selection in three colours where §1.2 gives olive: the selected filter, mode or view filled with the action green `#1a5035` (PG-018 *Per Produk*, PG-026 *Semua*, PG-027 *Ubah Kondisi*); the current tab underlined green (PG-041, PG-044) or near-black (PG-030); grey on PG-006's view chip and PG-008's tab. The header's create button filled on PG-060 but outline on its siblings; a form's submit outline on PG-046 and PG-033 but filled on PG-065. PG-061's notice in the blue processing tone titled *Sedang Diproses* while the page's state is *Belum Dimulai*. REVIEW — PG-012 gives *Tidak berlaku* the satisfied items' green check |
| SLOP-30 | REVIEW | Badges that are no state: the company tag (SLOP-05), *Jasa* on PG-011's item row, *Ambil Lebih Dulu* (PG-019, PG-024, PG-027) |
| SLOP-31 | PASS | The radii seen each have a role — controls about 8, cards 10, dialogs 12, sheets 16 on top, pills |
| SLOP-32 | FINDING | The "+" icon at 16 px on *+ Siapkan Batch* (PG-058) and *+ Tambah Format Nomor* (PG-061), 10 px on every other "+"; PG-031's optional section opened by the browser's own disclosure triangle where PG-023 uses the stroked chevron |
| SLOP-33 | FINDING | PG-061's gap from the title to its content about 29 px against about 53 px on its siblings (PG-058, PG-062, PG-064); PG-016's heading-to-empty-line gap 36 px under *Biaya pembelian* and 60 px under its other sections; PG-018's stock rows about 37 px and about 105 px in one table; form-section titles at body size and weight on PG-031, PG-032 and PG-034 but bold at about 17 px on PG-033, PG-036 and PG-042. REVIEW — PG-043's *Dokumen* block on three left edges; PG-013's radios at a 60 px pitch; the sign-in's text buttons inset 17 px (PG-001, PG-002, PG-004) |
| SLOP-34 | PASS | No chart but the ageing chart of defined values with its table (PG-006 in one company's view; CB-03 in §4); the reports are tables |
| SLOP-35 | PASS | No landing-page look; the sign-in is a plain form |
| SLOP-36 | PASS | No concept-shot look |
| SLOP-37 | PASS | The reference's olive, lime and green; no framework indigo |
| SLOP-38 | PASS | As SLOP-18, SLOP-28 and SLOP-34 |
| SLOP-39 | PASS | Lists end with an explicit *Muat Lagi* or *Semua sudah ditampilkan.*; no infinite scroll |
| SLOP-40 | PASS | No reaction control |
| SLOP-41 | PASS | No like control |
| SLOP-42 | PASS | No streak or per-user counter (PG-063, PG-064) |
| SLOP-43 | PASS | No celebratory element on any page; no confetti or celebratory motion in `resources/` or the dependencies |
| SLOP-44 | PASS | Progress for processes only — *langkah 2 dari 7*, the composer's steps, checklists; no percentage, ring or level ("100%" is the preview's zoom) |
| SLOP-45 | PASS | *Riwayat* and the logs are rows, with no bubble or avatar (PG-011, PG-016, PG-033, PG-063, PG-064) |

## 6. Density and the reviewer's other observations

Content density is reviewed against AICWDF §18.2 — the comfortable density, the dense mode only on OC-09's screens — on the same screenshots; these are the reviewer's readings for the Owner, not measured failures, and each names its evidence.

- **Empty groups at full height:** *Beranda* (PG-006) shows each empty signal group with its heading and one line, about 130 px each and about 1,200 px for nine of them; PG-021 and PG-023 do the same per company (about 150 px each). A group could fold or be left out when empty.
- **Gaps above an empty line:** the one-line empty state sits about 50–72 px below its heading on PG-016, PG-028, PG-029, PG-031, PG-032, PG-034, PG-036 and PG-043.
- **Tables on the phone:** PG-060's eight-column table is laid on the phone with its e-mails wrapping to about seven lines, rows about 200 px tall; PG-062's table cuts its *Aktif* column; PT-06 sets a list's rows on the phone. Report tables scroll inside their frames on PG-051, PG-052 and PG-008 — allowed (GR-05).
- **Wrapping on the desktop:** PG-010's nine columns wrap the title, client and next step to three to five lines (rows 50–130 px); PG-018's rows reach about 105 px with its last column at the frame's edge; PG-027's lot quantity label wraps to three lines in a 160 px column.
- **The phone's header:** every phone page shows its title in the app bar and again as the page heading, about 70 px; with a second back arrow on PG-033, PG-036, PG-042, PG-043, PG-046, PG-050, PG-052 and PG-053.
- **Tabs:** the second-level tab rows of PG-025, PG-039, PG-044, PG-045, PG-047, PG-049–PG-053 and PG-062 mark no current tab; on the phone the *Keuangan* tabs wrap to three rows (PG-045–PG-050) where PG-051, PG-053 and PG-062 scroll one row, PG-025's tabs are cut ("Riway…"), and the *Data Master* tabs sit above the title (PG-054–PG-057) where *Keuangan* and *Laporan* put them below.
- **Buttons:** *Batal* bordered on PG-031–PG-034 and borderless on PG-027 and PG-042; PG-016's nine outline actions of equal weight with no primary; PG-033's enabled *Catat Serah Terima* outline-styled like *Batal*; PG-038's *Unggah* both in the header and in its empty state; desktop actions not seen on the phone captures — PG-050 *Catat Mutasi Bank*, PG-058 *Siapkan Batch*, PG-059 *Tambah Perusahaan*, PG-061 *Tambah Format Nomor* and *Naikkan Penghitung*, PG-062 *Tambah Jenis Pajak* — each to be confirmed as a desk-only action (NV-06) or a gap.
- **Not judged:** PG-011's and PG-020's dialogs appear shifted off the phone's left and top edges in the captures, taken right after the width changed while the dialog may still be moving; it is to be checked on a device (TASK-086), not recorded as a defect here.

## 7. The findings, for the Owner's decision

Each finding with its page, item, evidence and a proposed minimum fix, and the Task that owns the page (FEATURE_COVERAGE_MATRIX). The plan is frozen at 95 and those Tasks are DONE: each finding is the Owner's to decide — fix before the release, and where, or accept — and none is changed here (APPR-038; criterion 9). **GF-01–GF-08** are measured or confirmed on the screenshots; **GF-09–GF-13** are SLOP-01–SLOP-45's (§5); **GF-14** is the suite's own.

| ID | Page — owner | Item | Evidence | Proposed minimum fix |
| --- | --- | --- | --- | --- |
| GF-01 | PG-036 and every composer (PG-014, PG-033, PG-042, PG-043) — TASK-029 | The composer's tolerance (criterion 6) | §4: DOC-09's blocks up to 12.27 mm (Chromium) and 12.67 mm (WebKit) from the issued rendition's | `body { margin: 0 }` for the issued document; the draft marking over the top margin, not in the flow |
| GF-02 | Every form with a text field — PG-001, PG-003, PG-013, PG-014, PG-017, PG-046, PG-054–PG-056, PG-063–PG-065 — the design system's field (TASK-015) | Adaptive: 44 px targets under a coarse pointer (D-DS-19) | Text fields, selects and searches 42 px tall at 390 px under a coarse pointer, in each page's gate record (`targets`) | The field's height at 44 px for `pointer: coarse` |
| GF-03 | PG-006, PG-011, PG-012, PG-019, PG-025, PG-027, PG-030–PG-034, PG-039, PG-043–PG-045, PG-047–PG-049, PG-060, PG-062 — their own Tasks | Adaptive, as GF-02 | Standalone links 20 px tall (*Buka Pekerjaan*, *SPK klien*, *Selesaikan di halamannya*, a project's or invoice's number, *Unggah bukti*); PG-060's user names and PG-062's tax codes 17 px tall in their tables on the phone; tabs 27–43 px wide (*Aktif*, *Wajib*, *Semua*, *Kredit*, *Beban*, *Tarif*); checkboxes and radios 18 px (PG-027, PG-031, PG-032) | A 44 px hit area for each under a coarse pointer, the label's own included |
| GF-04 | PG-056 — TASK-027 | Unnecessary duplicate actions | *Ubah Harga* three times in one section, outside rows and lists | Read against PG-056's INT- contract: one per price row in a list, or one action for the section |
| GF-05 | PG-057 — TASK-028 | Responsive at 390 px | *Stok Minimum*'s rows cut the item's name to one letter in seven of ten rows (`gate/screens/PG-057-390.png`) | The name wrapping above or beside the quantity, the button below, as the other three rows already do |
| GF-06 | PG-044 — TASK-050 | Responsive at 1,280 px | The row's "⋯" action cut at the table frame's right edge; *Umur* wrapping to three lines (`PG-044-1280.png`) | The table's columns sized so the action column stays inside the frame at 1,280 px |
| GF-07 | PG-006 — TASK-058 | Mandated copy | *Diperbarui 10.44 WIB WIB* and *Angka per 10.44 WIB WIB* (`PG-006-1280.png`) — the zone printed twice | The time's format or the sentence's own "WIB", one of them |
| GF-08 | PG-005 — TASK-016 | Empty state; SLOP-23, SLOP-26 | The not-found page's icon above its heading, the heading about 32 px on the phone where every other phone title is about 25 px (`PG-005-390.png`) | The heading at the phone's title size; the icon left out or kept as the Owner prefers |
| GF-09 | PG-011 — TASK-032 | SLOP-01 | Six sections of the one project in six cards | The sections grouped by space and rules as on PG-016, a card only for a distinct object |
| GF-10 | Every page with a company tag — the shared `CompanyTag` (TASK-016) | SLOP-05, SLOP-30 | The company code drawn as a pill badge (PG-006, PG-007, PG-010, PG-051, PG-052, PG-059; `slop` marks on 34 pages) | The code as a 4 px rectangle tag or plain text, as PG-015 and PG-010 on the phone print it — or the pill accepted for this identifier |
| GF-11 | PG-018, PG-026, PG-027, PG-030, PG-041, PG-044, PG-046, PG-060, PG-061, PG-065 — their own Tasks and the toggle and tab components (TASK-015) | SLOP-29 | Selection in the action green, near-black and grey where §1.2 gives olive; the header's create and the form's submit filled on some pages and outline on others; PG-061's processing-blue notice on a page not yet started | One selection colour (olive) in the toggle and tab components; one emphasis per action kind; PG-061's notice in its state's tone |
| GF-12 | PG-031, PG-058, PG-061 — TASK-045, TASK-057, TASK-021 | SLOP-32 | The "+" at 16 px on PG-058 and PG-061, 10 px elsewhere; PG-031's disclosure triangle where PG-023 uses the chevron | The icon component's one size; the chevron for every disclosure |
| GF-13 | PG-016, PG-018, PG-031–PG-034, PG-036, PG-042, PG-061 — their own Tasks | SLOP-33 | PG-061's header gap about 29 px against about 53 px; PG-016's empty lines at 36 and 60 px under their headings; PG-018's rows at 37 and 105 px in one table; form-section titles at body size on PG-031, PG-032 and PG-034 and bold on PG-033, PG-036 and PG-042 | The page header's and the form section's one pattern each |
| GF-14 | TL-15 — TASK-068's suite; ReconcileGuards (PF-30) | The verification's own run time | Two of four CI runs of this branch stopped at the performance job's 30-minute limit at 100 of 103 tests (§2); in a passing run `IndexRegisterTest.php` took 142 s — every other file at most 6 s — and its reconciliation of `evidence_balances` 5.8 s, a sequential scan of `expenses` by `evidence_document_id` for each of 10,000 rows (`plans/reconcile-evidence_balances.json`); `main`'s runs have passed | An index on `expenses (evidence_document_id)` or a set-based form of that read, and a statement timeout in the test so that a slow plan fails with its plan rather than at the job's limit — a schema and test change outside this Task |

The reviewer's REVIEW readings of §5 — SLOP-23, SLOP-28 and SLOP-30, and the notes beside SLOP-02, SLOP-05, SLOP-29 and SLOP-33 — and the observations of §6 stand beside these for the same decision.

## 8. What was not tested here

- the WebKit widths other than 390, 768 and 1,280 px (APPR-038);
- an outcome's error state on a page visit — each INT- contract's tests judge it (TL-08) —; an empty state where the visit's data had none;
- the composer's tolerance for the layouts other than DOC-09, which the engine's fixture cannot draft on PG-036 — each is its issuing module's subject —; they share the document's wrapper, the open partial and `paper.css`, whose two causes §4 names;
- CB-01's and CB-02's screens, CJ-16's on staging only (TEST_STRATEGY §7), until the operator's staging run (TASK-081);
- motion as it moves — the captures are still; motion is read from the tokens and the code (SLOP-15, SLOP-16, SLOP-43);
- real devices and the Owner's confirmation of the reviewed items at UAT (TASK-070, TASK-086).

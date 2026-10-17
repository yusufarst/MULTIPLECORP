# Deviation register

Status: DRAFT | Updated: 2026-10-18 | Owner: Planning

**Draft — not normative.** This is an S2-1 document of Stage 2 of the P7 UX re-baseline, written under [DIR-061](../../00-governance/DECISION_LOG.md#dir-061-dir-062-obs-023-and-tech-031--p7-ux-re-baseline-stage-2-decisions-s2-0-authorization-baseline-and-prototype-setup) SD-01, SD-05 and SD-10 and [DIR-064](../../00-governance/DECISION_LOG.md#dir-063-dir-064-obs-024-and-tech-032--p7-ux-re-baseline-s2-0-acceptance-s2-1-authorization-baseline-and-stage-2-documents) §12.10 (AK-7). Behaviour follows MULTIPLECORP — P0–P6 and ADMIN_FLOW —, structure follows R1, and presentation follows the Golden Frontend Reference v1.0.0, which adapts to MULTIPLECORP and never the reverse (AK-1; SD-02). [INFORMATION_ARCHITECTURE](../INFORMATION_ARCHITECTURE.md) and [DESIGN_SYSTEM](../DESIGN_SYSTEM.md) stay APPROVED under APPR-008 and under replacement, and [ADMIN_FLOW](../ADMIN_FLOW/README.md) stays in force, until a later authorized stage changes them; Stage 3 uses this draft, and nothing in it is normative.

**Sources.** DIR-059 GD-02 — a deviation needs a recorded reason: accessibility, a P0–P6 rule, the R1 structure, an Owner decision or a shadcn/ui constraint —, GD-03 and GD-08; DIR-061 SD-02 and SD-04 and AK-4 and AK-7; the gate record's [GA1 reconciliation](../evidence/P7_REBASELINE_GATE.md#c1-closure--golden-frontend-reference-reconciliation) and its [carried items](../evidence/P7_REBASELINE_GATE.md#carried-items-gr-01); [DESIGN_SYSTEM_MAPPING](DESIGN_SYSTEM_MAPPING.md), whose sections each entry cites; the reference's vendored stylesheets at the prototype's `503e83b`, read-only.

## 1. How this register works

- **What it holds.** Every place where a prototype or application presentation departs from the reference — its element, the reference's form, MultipleCorp's form, the reason from [GD-02](../../00-governance/DECISION_LOG.md#dir-059-dir-060-obs-022-and-tech-030--p7-ux-re-baseline-checkpoint-c1-closure-golden-frontend-reference-adoption-authorization-baseline-and-reconciliation)'s list, the override S2-2 would write and its status. [GR-24](#2-gr-24-onward) onward are numbered here; [GR-01](../evidence/P7_REBASELINE_GATE.md#carried-items-gr-01)–GR-23, carried from GA1, are tabulated in §3.
- **Where an override lives.** The vendored files stay byte-identical (SD-04); a deviation is written only in the prototype's override layer — a stylesheet written new and loaded after the five vendored ones — or in the markup of pages written new, as [PROTOTYPE_PLAN](PROTOTYPE_PLAN.md) lays out (AK-4; AK-7). In the application the same deviation is part of the components P11 builds.
- **What a deviation never does.** It never changes business semantics: no action, state, rule, permission, message meaning or number changes with a presentation (AK-1; AK-2). An exclusion — a part of the reference that MultipleCorp does not use at all, such as its demonstration pages, greeting, hero, guidance levels or pager — is not a deviation; GA1 records them, and [DESIGN_SYSTEM_MAPPING §3](DESIGN_SYSTEM_MAPPING.md#3-components) lists them as not used.
- **Status.** "planned" until S2-2 writes the override and its fidelity audit ([DESIGN_SYSTEM_MAPPING §8](DESIGN_SYSTEM_MAPPING.md#8-the-fidelity-audit)) measures it; Part 6 added GR-31 for the journey screens; S2-2's checks fill its per-screen list. S2-2 ([DIR-066](../../00-governance/DECISION_LOG.md#dir-065-dir-066-obs-025-and-tech-033--p7-ux-re-baseline-s2-2-decisions-authorization-baseline-and-journey-prototypes), 2026-10-14) applied GR-24–GR-31 and added GR-32–GR-35; [VERIFICATION_S2-2](VERIFICATION_S2-2.md) holds the measurements.

## 2. GR-24 onward

| GR | Element | The reference's form | MultipleCorp's form | Reason | The override S2-2 would write | Status |
| --- | --- | --- | --- | --- | --- | --- |
| [GR-24](#2-gr-24-onward) | Status pill | a full-radius pill with the tone's tint, ink and a dot | the same pill with the state's 14 px Lucide shape in the dot's place, from [DESIGN_SYSTEM](../DESIGN_SYSTEM.md#design-system) §12.1 | an approved P7 rule kept by D-DS-04's ADAPT disposition: within a family two states share a tone, and only a shape keeps them apart in greyscale; WCAG 2.2 1.4.1 | the pill's markup written with the prototype's sprite icon before the label; the dot hidden in the override layer | applied (S2-2) |
| [GR-25](#2-gr-24-onward) | Live pill | the dot of a genuinely live state with a ring that breathes every 2 s | the dot without the looping ring | an Owner decision: [VP-8](../../00-governance/DECISION_LOG.md#dir-057-dir-058-obs-021-and-tech-029--p7-ux-re-baseline-checkpoint-c1-visual-direction-decisions-visual-checkpoint-authorization-baseline-and-visual-anchors)'s functional motion, no looping (DIR-058) | the ring's animation removed in the override layer | applied (S2-2) |
| [GR-26](#2-gr-24-onward) | Account placement | the account at the bottom of the sidebar | the account menu in the top bar, with *Akun Saya*, *Bahasa tampilan* and *Keluar*; on the phone *Lainnya* › *Akun* | the R1 structure: GD-03 keeps R1's account menu (GA1 e)) | none in the stylesheets: the prototype's shell markup, written new, places the menu | applied (S2-2) |
| GR-27 | Top bar | search and the period only | "Cari atau pindai kode", "Buat" and the account menu; the period and the company view as page controls | the R1 structure (GD-03); [D-IA-02](../INFORMATION_ARCHITECTURE.md#4-design-decisions) | the shell markup written new | applied (S2-2) |
| GR-28 | Phone app bar | the menu, the brand and search, with no explicit back action | the contextual back action and the page title; the menu and search in the bottom bar's *Lainnya* and *Cari* | a repository rule: AGENTS.md rule 12, AICWDF §18.3–§18.5 and C1A-2's one control per intent | the app bar's markup written new; its layout in the override layer | applied (S2-2) |
| GR-29 | Phone bottom bar | the role's four most frequent level-1 destinations | R1's bars: the Owner's *Beranda* · *Proyek* · *Cari* · *Lainnya*, the Admin's *Pekerjaan* · *Proyek* · *Cari* · *Lainnya* | the R1 structure (GD-03; D-IA-03) | the bar's markup written new | applied (S2-2) |
| GR-30 | The shortcut list's key | "?" opens it | Ctrl / opens it, as KB-02 sets; an entry "Pintasan keyboard" in the search palette ([DESIGN_SYSTEM_MAPPING §4](DESIGN_SYSTEM_MAPPING.md#4-patterns), PT-01) | an approved P7 rule: DESIGN_SYSTEM KB-02 (GA1 b)) | the key handled by the prototype's own script, no vendored handler bound — in the prototype only if a journey screen shows the list | applied (S2-2) |
| GR-31 | The vendored scripts' own strings on an English page | Indonesian defaults: patterns.js and composer.js hold Indonesian strings only, interactions.js English for some strings through `L(id, en)` and `<html lang>` | no Indonesian interface string on an `/en/` page: a vendored string a journey screen shows is either kept off the screen or listed here with its screen and its string before the Owner's preview (LOCALIZATION_ARCHITECTURE §2.6) | an approved P1 requirement: [CAP-14](../../01-product/V1_SCOPE.md#must-ship)'s English interface; GP-5; GR-17 | none in the stylesheets: the screen's markup avoids the component, or the string stays and is listed — found by the string check of LOCALIZATION_ARCHITECTURE §2.7 on every PS screen | applied (S2-2) — no vendored string left un-keyed: `prototype.js` gives the drawer, the account menu and its trigger, the navigation's labels and the dialogs' buttons their copy-table strings; the string check found no vendored string on any PS screen in either language, so the per-screen list is empty ([VERIFICATION_S2-2](VERIFICATION_S2-2.md)) |
| GR-32 | The simulated Google button of S2-J01 | none: the reference's drawn "G" is excluded (GD-08) | the reference's outline extra-large button — its frame, Inter and padding — with Google's own full-colour "G", unaltered, on the light surface, and the label "Lanjutkan dengan Google"; Google sign-in simulated | an Owner decision: [OQ-06](OWNER_QUESTIONS.md#oq-06--the-simulated-google-button-of-s2-j01) (c) under DIR-065; Google's own button rules differ in the frame, the font, the padding and the "G"'s displayed size — this deviation; the application follows them in full in P11 (GR-13) | `overrides.css`, block GR-32, and S2-J01's markup; `site/assets/mc/google-g.svg` from Google's official asset download (SP-24) | applied (S2-2) |
| GR-33 | The prototype's own elements | none | the marker "PROTOTIPE · data contoh" in the top bar, in the phone app bar in two lines and in the sheets' heads; the panel "Tentang prototipe ini"; the out-of-scope page with and without the shell; the start page | SS-08 and [SIMULATION_REGISTER §4](SIMULATION_REGISTER.md#4-the-marker-the-panel-and-the-out-of-scope-page): the prototype never passes for a working system — prototype-only, nothing for P11 | `overrides.css`, blocks GR-33, and the pages' markup | applied (S2-2) |
| GR-34 | R1's screen compositions | the reference's archetypes — list, detail, form, task, report — without these compositions | R1's approved compositions on the reference's primitives: Beranda's four regions with VP-4's segmented stage indicator; the work home's groups; the journey's stage track and now strip; the composer; the purchase's line editor on R1 j)'s column grid, the estimate beside the supplier panel; the receiving checklist, its full-height sheets and IP-16's file line; the payment form with its application table | the R1 structure (GD-03; AK-3) | `overrides.css`, blocks GR-34 | applied (S2-2) |
| GR-35 | Copy never cut | an ellipsis on long row titles, labels, the phone app bar's title, a sidebar label and a file's state; the phone's KPI tiles two by two at every width | the copy wraps, and holds under WCAG 2.2 1.4.12's text spacing; figures right-aligned in tabular figures; a trailing icon follows its label in a button of its own width; below 375 px *Beranda*'s KPI tiles one per row, each figure whole | [LOCALIZATION_ARCHITECTURE §2.8](LOCALIZATION_ARCHITECTURE.md) rule 2 — mandated copy stays visible (R1 g)); accessibility, 1.4.12; OC-09 | `overrides.css`, blocks GR-35 | applied (S2-2) |

## 3. GR-01–GR-23

The carried items as [DIR-061](../../00-governance/DECISION_LOG.md#dir-061-dir-062-obs-023-and-tech-031--p7-ux-re-baseline-stage-2-decisions-s2-0-authorization-baseline-and-prototype-setup) SD-02 corrects them, with their state after S2-1's documents; each is handled in [DESIGN_SYSTEM_MAPPING §7](DESIGN_SYSTEM_MAPPING.md#7-the-carried-items-gr-01gr-23), which says what S2-2 or the preview checks and what stays for Stage 3 or P11.

| GR | Item | Kind | State after S2-1 | Handled in |
| --- | --- | --- | --- | --- |
| [GR-01](../evidence/P7_REBASELINE_GATE.md#carried-items-gr-01) | the control edge `#898982` | deviation — accessibility, WCAG 2.2 1.4.11 | mapped: `--color-border-input` | mapping §§1.1, 1.2 |
| GR-02 | the olive mark on the bottom bar's current item, the selected segment and the sign-in toggle | deviation — accessibility, 1.4.11 | mapped | mapping §§3, 6 |
| GR-03 | the olive ring on the current stage marker | deviation — accessibility, 1.4.11 | mapped | mapping §§1.4, 3 |
| GR-04 | the per-surface greys made mandatory | rule on the reference's own tokens — accessibility, 1.4.3 | mapped | mapping §1.2 |
| GR-05 | reflow at 320 px, the reference validating from 360 | deviation — accessibility, 1.4.10 | mapped | mapping §6 |
| GR-06 | rem at a 16 px root; the 1.4.12 and 2.4.11 checks | deviation in units — accessibility, 1.4.4 | mapped; the checks planned for S2-2 | mapping §§1, 3 |
| GR-07 | 48 px controls on floor tasks; ≥ 44 px targets with a coarse pointer | deviation — a P7 rule ([UXV-10](../DESIGN_SYSTEM.md#3-derived-visual-requirements)) and R1 | mapped | mapping §§2, 6 |
| GR-08 | position numbers with "Muat Lagi"; no pager or total | deviation — a P5 and P6 rule ([WS-03](../../05-security/SECURITY.md#4-web-and-input-security); [PF-15](../../06-api-performance/PERFORMANCE.md#4-pagination)) | mapped | mapping §§3, 4 |
| GR-09 | the confirmation mapping without a typed confirmation or tick; logout asking only when input is unsent | deviation — a P7 rule ([IP-08](../ADMIN_FLOW/s05-command-model.md#ip-08--confirming-an-irreversible-command); MSG-35) as SD-02 corrects it | mapped | mapping §4 |
| GR-10 | the composer within VD-07's preview share, DC-2's number line, defined inputs, provisional totals | deviation — Owner decisions (VD-07; DC-1–DC-12) | mapped; the producer recommended | mapping §3; [DOCUMENT_PREVIEW_PRODUCER](DOCUMENT_PREVIEW_PRODUCER.md) |
| GR-11 | *Lainnya* as a drawer or a sheet; the bottom bar hidden on a focused scan step | choice within the reference | the drawer proposed; the C2 sessions confirm | mapping §6 |
| GR-12 | the abstract sign-in panel; the code entry; the authentication journeys | deviation — an Owner decision (VD-05; VP-5; GD-08) | mapped | mapping §3; [AUTH_JOURNEYS](AUTH_JOURNEYS.md) |
| GR-13 | the Google button to Google's guidelines | deviation — the reference's drawn "G" excluded (GD-08; SD-04) | the prototype's drawing an Owner question; the application's button P11's | mapping §7; [PROTOTYPE_PLAN](PROTOTYPE_PLAN.md) |
| GR-14 | whether a UI preference persists; the company view and the period out of browser storage | deviation — the reference's browser storage (SECURITY AU-08, WS-07; C-1) | the language stated in LOCALIZATION_ARCHITECTURE; the sidebar's state kept nowhere in the prototype | mapping §7; [LOCALIZATION_ARCHITECTURE §3](LOCALIZATION_ARCHITECTURE.md#3-persistence--stated-not-decided) |
| GR-15 | toasts only for events with a visible trace elsewhere | deviation — accessibility, 2.2.1 | mapped | mapping §§3, 4 |
| GR-16 | the smallest desktop text | open — the Owner's choice at the preview (SD-08) | variants A and B defined | mapping §1 |
| GR-17 | the localization of the reference's micro-copy | deviation — [CAP-14](../../01-product/V1_SCOPE.md#must-ship); GP-5 | ruled; the vendored strings per screen in Part 6 | [LOCALIZATION_ARCHITECTURE §2.6](LOCALIZATION_ARCHITECTURE.md#26-the-references-micro-copy-gr-17-gp-5) |
| GR-18 | Lucide styled to the reference's strokes | deviation — the reference's sprite not copied (GD-05; SD-04) | Lucide 1.56.0 pinned; the strokes mapped | mapping §1.4; PROTOTYPE_PLAN |
| GR-19 | date-range presets bound to the periods | deviation — a P0–P6 rule on periods | mapped | mapping §3 |
| GR-20 | the filter toolbar, presentation only | alignment of DESIGN_SYSTEM PT-07's presentation; its behaviour unchanged (SD-02) | mapped | mapping §4 |
| GR-21 | desktop disclosures where R1 folds | deviation — the R1 structure | mapped | mapping §3 |
| GR-22 | opaque phone bars | deviation — SLOP-10, contrast | mapped | mapping §§1.4, 2, 6 |
| GR-23 | the ageing chart with direct labels, amounts and a table | deviation — an Owner decision (VP-6) | mapped | mapping §§1.2, 3 |

**State after S2-2** ([DIR-066](../../00-governance/DECISION_LOG.md#dir-065-dir-066-obs-025-and-tech-033--p7-ux-re-baseline-s2-2-decisions-authorization-baseline-and-journey-prototypes)): applied in the override layer, the pages' markup or `prototype.js` — GR-01–GR-04, GR-06, GR-07, GR-09–GR-12, GR-14, GR-15, GR-17, GR-18 and GR-21–GR-23; GR-05 verified at 320 px; GR-08 on PS-45's "Muat Lagi", which leads to the out-of-scope page; GR-16 rendered in both variants for the Owner's choice at the preview (SD-08); GR-13 stays for P11, the prototype's button being GR-32; GR-19 and GR-20 not exercised by the approved screens, whose period and filter controls lead to the out-of-scope page. The measurements are in [VERIFICATION_S2-2](VERIFICATION_S2-2.md).

## 4. The pilot pages (GF-1)

Run GF-1 ([DIR-068](../../00-governance/DECISION_LOG.md#dir-067-dir-068-obs-026-and-tech-034--p7-ux-re-baseline-golden-first-decisions-pilot-authorization-baseline-and-pilot), 2026-10-14) builds four pages from Golden's own implementation under the Owner's K1 ([DIR-067](../../00-governance/DECISION_LOG.md#dir-067-dir-068-obs-026-and-tech-034--p7-ux-re-baseline-golden-first-decisions-pilot-authorization-baseline-and-pilot)): on those pages Golden's form applies, and a deviation stays only for SP-31's reasons. Each entry's status there; S2-2's pages keep §§2–3 unchanged. The pilot's overrides live in its own stylesheet, `site/pilot/assets/pilot.css`, each labelled with its entry; no new entry was needed. [GOLDEN_FIRST_PILOT](GOLDEN_FIRST_PILOT.md) §5 summarizes it.

| GR | Status on the pilot pages | Reason or where |
| --- | --- | --- |
| GR-01 | kept, applied | accessibility, 1.4.11: the control edge `#898982` on Golden's fields, selects and stepper frame |
| GR-02 | kept, **not applied** | accessibility, 1.4.11 — found after the one authorized prototype commit; the pilot shows Golden's own selection marks until the pilot is next changed ([GOLDEN_FIRST_PILOT](GOLDEN_FIRST_PILOT.md) §10 item 9) |
| GR-03 | not exercised | no stage marker on the four pages |
| GR-04 | kept as a rule | Golden's greys pass on every pilot surface (contrast measured); no override needed |
| GR-05 | kept, applied | reflow at 320 px: the six code cells keep 44 px below 360 px by a narrower gap |
| GR-06 | kept as checks | 1.4.12 and 2.4.11 measured on every state; no override needed |
| GR-07 | kept, applied | 44 px targets on the phone (segmented choices, the period control, the marker's hit area) and 48 px on the floor task (the scan field, Golden's stepper, the sheet's fields and file action) |
| GR-08 | not exercised | no list with "Muat Lagi" on the four pages |
| GR-09 | kept, applied | logout asks only over unsent input (MSG-35), through the configuration patch |
| GR-10 | not exercised | no composer on the four pages |
| GR-11 | kept, applied | *Lainnya* as Golden's drawer; Golden's action bar hides the bottom bar on the scan step |
| GR-12 | kept, applied | VD-05's abstract sign-in panel |
| GR-13 | not exercised | P11's; GR-32 stands for it in the prototype |
| GR-14 | kept, applied | no browser storage: S2-2's storage guard first on every pilot page |
| GR-15 | not exercised | no toast on the four pages |
| GR-16 | open; its switch **not applied** | the Owner's choice; SP-31 keeps variant B as a switch, which the pilot pages lack (found after the prototype commit); S2-2's pages keep it |
| GR-17 | kept, applied | Golden's strings keyed, their English rendering proposed |
| GR-18 | retired where Golden has the symbol | Golden's sprite (`icons.js`); Lucide 1.56.0 only for the symbols it lacks, under `mcp-` ids |
| GR-19, GR-20 | not exercised | the period and filter controls lead to the out-of-scope page |
| GR-21 | kept, applied | "Rincian lain" folded on every width, on Golden's disclosure recipe |
| GR-22 | retired | Golden's translucent phone bars: contrast passes |
| GR-23 | not exercised | no ageing chart in *Gabungan* |
| GR-24 | kept, applied | the line states' Lucide shapes beside their words |
| GR-25 | retired | VP-8's no-looping rule superseded on the pilot pages by K1; no live state appears there |
| GR-26 | kept, applied | R1's account menu in the top bar — a choice for the Owner |
| GR-27 | kept, applied | the top bar's content |
| GR-28 | kept, applied | the phone app bar's back action and title, the back action at Golden's leading offset |
| GR-29 | kept, applied | R1's bottom bars |
| GR-30 | not exercised | no shortcut list on the four pages |
| GR-31 | kept, applied | no Indonesian interface string on an English page (checked) |
| GR-32 | kept, applied | Google's own "G" (OQ-06 (c)) |
| GR-33 | kept, applied | the marker, the panel, the out-of-scope page and the start page |
| GR-34 | content and order kept; layout and styling retired | R1's compositions drawn with Golden's components and grid |
| GR-35 | gives way where content passes | Golden's ellipsis and nowrap stay where nothing is cut; copy wraps where it would be cut (decision rows, KPI labels, line meta, the drawer's language entry, a card head under 1.4.12) |

## 5. The pilot pages after GF-2 and GF-2A

*Note of 2026-10-17, written by run PC-1 under [DIR-075](../../00-governance/DECISION_LOG.md#dir-073dir-075-obs-029-and-tech-036--p7-ux-re-baseline-cp-1r-verdict-p7p11-completion-plan-run-pc-1-and-stage-2-completion) §10 item 2 from the evidence.* GF-2's stage 1 (commit A) and GF-2A's stage 1R (commit A2) changed the statuses below on the pilot pages; every other entry keeps §4's status. No new entry was needed: GF-2's and GF-2A's labels cite only existing entries — GR-02, GR-11, GR-23, GR-24, GR-27, GR-29 and GR-32 — and canonical sources (`evidence/gf2/labels.json`, `evidence/gf2a/labels.json` in the prototype repository); the sign-in fields' focus ring of SP-48 is the ring Golden draws round its other controls, and PS-35's facts-panel gap is a C adaptation for legibility (SP-46). The pages PC-1 builds take these statuses (DIR-075 SP-54, SP-55). [GOLDEN_FIRST_PILOT](GOLDEN_FIRST_PILOT.md) §§14, 17 and 20 describe the changes.

| GR | Status on the pilot pages after GF-2A | Reason or where |
| --- | --- | --- |
| GR-02 | kept, **applied** (GF-2) | accessibility, 1.4.11: GR-02's olive inset edge where Golden's grey selection mark fails — the segmented choices (*Gabungan* 5.65:1), the bottom bar's current item (*Beranda* 6.56:1) and the selected language; the sidebar's current mark kept as Golden's (6.03:1) |
| GR-05 | kept as a check | reflow at 320 × 640 (SP-44); the code step's one input, shown segmented (SP-48) |
| GR-07 | kept, applied as CQ-5 sets it | 48 px for the floor task's operational controls — the scan field and "Cari Produk", the line rows, "Catat Barang Masuk", "Kembali" and the sheet's controls —, 44 px for the page's other controls (SP-47; OQ-12) — a B difference from S2-J05's "every control 48 px", its source CQ-5 |
| GR-12 | the panel **retired**; the code entry changed | SP-48's concept A "Meja Kerja", drawn in code in Golden's panel (OQ-13; CQ-3), in place of VD-05's abstract panel; one input for the six digits (S2-J01 §9); the unused CSS block kept unchanged by the Owner's disposition, a cleanup for a later authorized run |
| GR-16 | open; its switch **applied** (GF-2) | `?varian=b` on the desktop pages; the Owner's choice at CP-A (OQ-15) |
| GR-23 | kept, applied (GF-2A) | PS-12, one company's view, with "Umur piutang", its direct labels, amounts and table (VP-6; SP-43) |
| GR-26 | **retired** (GF-2) | the account at the sidebar's foot with Golden's upward menu and R1's three entries (OQ-10) |
| GR-27 | kept, in Golden's slots | "Cari atau pindai kode" in Golden's search, "Buat" and the marker in its actions slot (OQ-10; SP-38); D2's icon "Buat" a switch, not approved |
| GR-28 | kept, in Golden's form | Golden's labelled back pill "Kembali" on a child flow, its brand with the placeholder mark on a top-level page, no menu or search button; the title as the page head in the content (OQ-10; IA 6.2) |
| GR-33 | kept, applied as OQ-11 sets it | the marker one line, Golden's info pill in the phone app bar's trailing slot and the top bar's actions slot, 44 px with a coarse pointer; the marker's panel names the placeholder logo |

## 6. The pilot after PC-1

*Note of 2026-10-17, written by run PC-1 under [DIR-075](../../00-governance/DECISION_LOG.md#dir-073dir-075-obs-029-and-tech-036--p7-ux-re-baseline-cp-1r-verdict-p7p11-completion-plan-run-pc-1-and-stage-2-completion) §11 item 3 from the evidence (the prototype's `evidence/pc1/`).* PC-1 finished the pilot (*Buat Pembelian*, SP-53) and built every remaining screen of PS-01–PS-53 on Golden's form ([GOLDEN_FIRST_PILOT](GOLDEN_FIRST_PILOT.md) §§24–29). The statuses below are those PC-1 changed or exercised on the new pages; every other entry keeps §5's status, and the accepted pages keep theirs (SP-54). No new entry was needed: each change cites an existing entry or a canonical source. One correction is not a deviation from Golden's look: PG-044's status tabs, which lead to other pages, are a labelled navigation with the current page marked, Golden's active-tab look re-targeted unchanged (WF-PC1 FA-13; WCAG 4.1.2).

| GR | Status on the pilot after PC-1 | Reason or where |
| --- | --- | --- |
| GR-01 | kept, applied; **extended** | the control edge `#898982` now also on the payment form's reason field (Golden's `.kb-textarea` had 1.27:1; R-03); an invalid field with the focus keeps Golden's danger edge and halo and gains Golden's 2 px focus ring outside them, the two visible together at 3:1, as GF-2A's sign-in fields (R-03) |
| GR-05 | kept as a check | reflow at 320 × 640 on every new screen (SV-02; FM-09), 0 failing |
| GR-06 | kept; its 2.4.11 check now **applied** | no control the keyboard reaches ends under a bar: the fixed action bar's height kept free below the focus from 768 px, the line sheet's sticky bar's in the sheet (R-04); 1.4.12 and 2.4.11 measured on every new screen, 0 failing |
| GR-07 | kept, applied | 44 px with a coarse pointer and on the phone on every new screen and on *Buat Pembelian* (SP-53 (b); `pc1-targets.mjs` 0 failing); 48 px on PG-022's new states' floor controls (CQ-5) |
| GR-08 | exercised, as Golden's | PG-045's "Muat Lagi" kept as Golden's control, leading outside the pilot; no pager or total |
| GR-10 | kept | PG-042, the invoice composer, on Golden's composer form with S2-2's composition: the draft preview marked, provisional totals, the number only at issue (DOCUMENT_PREVIEW_PRODUCER unchanged) |
| GR-16 | open; its switch applied on every new desktop page | `?varian=b`; the Owner's choice at CP-A (OQ-15) |
| GR-21 | kept, applied on the new pages | PG-011's notes and PG-007's groups as Golden's disclosure buttons, folded as R1 folds them |
| GR-33 | kept, applied | the marker on every page and on every sheet (WF-PC1 GF-06) |
| GR-34 | content and order kept | R1's compositions of the new pages drawn with Golden's components and grid |
| GR-35 | gives way where content passes | as §5, and on the new pages: the start page's row titles unclamped under 1.4.12, PG-044's phone rows wrapping, the line sheet's file state whole below 360 px with Golden's own phone spacing (R-02) |

**Carried, with their target stage** (the prototype's `evidence/pc1/carry-forward.txt`; CP-A's decision sheet `cp-a-sheet.txt`): every raw FM failure and noncritical visual difference of GF-2A and PC-1 — Stage 3; the card direction as built and the KPI and context cards' option A or B — CP-A, then Stage 3; GR-16, D1–D6 and the proposed strings — CP-A; Golden's and the pilot's chevron transitions that stay under reduced motion — Stage 3 (VP-8's reading); GR-12's unused CSS — a cleanup for a later authorized run.

## 7. The CP-A decisions

*Note of 2026-10-18, written by run PC-2 under [DIR-078](../../00-governance/DECISION_LOG.md#dir-077-dir-078-obs-030-and-tech-037--p7-ux-re-baseline-cp-a-decisions-run-pc-2-with-p9s-drafting-baseline-and-drafts) §7 item 5.* At checkpoint CP-A the Owner decided GR-16 and D1–D6 ([DIR-077](../../00-governance/DECISION_LOG.md#dir-077-dir-078-obs-030-and-tech-037--p7-ux-re-baseline-cp-a-decisions-run-pc-2-with-p9s-drafting-baseline-and-drafts) CA-04 items 1–7); the prototype's commit D makes variant B the pilot's default and keeps D1–D6 as decided, each already the default (SP-62). Nothing here is normative before Stage 3, whose DESIGN_SYSTEM sets the final form.

| Entry | The Owner's decision at CP-A | In the pilot from commit D |
| --- | --- | --- |
| GR-16 | variant B (item 1) — the desktop text unit at 1 px from 1,025 px, the smallest desktop text 14 px | the default on every pilot page; `?varian=b` still accepted, and the start page's links to it kept |
| D1 — BR-C03 (1), MSG-29 split into Golden's title and meta slots | reverted (item 2) | off, as before; the switch `?d=msg29` unchanged |
| D2 — BR-D02, "Buat" as Golden's icon button from 768 to 1024 px | reverted (item 3): R1's visible label stays | off, as before; the switch `?d=buat-ikon` unchanged |
| D3 — TB-D01, PS-35's card | the correction confirmed (item 4) | as built |
| D4 — TB-D02, the line's title, meta and business date in the card's head | kept (item 5) | on, as built |
| D5 — TB-D03, "Berikutnya" as Golden's floor badge | kept (item 6) | on, as built |
| D6 — TB-D04, MSG-01's lead as Golden's notice title, the colon dropped | kept (item 7); its punctuation settled in Stage 3's copy validation | on, as built |

**Carried to Stage 3** (CA-04 items 11, 12 and 15–17; CA-05): the motion accepted for the sessions, its raw differences resolved in DESIGN_SYSTEM from Golden's motion; the card direction as built, with option A or B for the KPI and context cards; FA-20, FA-21 and FA-22 as rules; WF-2R's D findings and WF-PC1's other D findings dropped as prototype refinements, adoptable only as a Level 1 choice within Golden's scales; GF-2A's further measured decisions; every noncritical visual difference, never recorded as passed.

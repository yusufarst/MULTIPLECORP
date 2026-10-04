# Design references

Status: REVIEW | Updated: 2026-10-05 | Owner: Planning

Authority: the P7 UX re-baseline, authorized for Stage 0 and Stage 1 by the Owner under [DIR-054](../00-governance/DECISION_LOG.md#dir-053-dir-054-obs-019-and-tech-027--p7-ux-re-baseline-decisions-stage-01-authorization-baseline-and-direction), with the design reference the Owner chose in [DIR-053](../00-governance/DECISION_LOG.md#dir-053-dir-054-obs-019-and-tech-027--p7-ux-re-baseline-decisions-stage-01-authorization-baseline-and-direction) K3 and the standing intent of D3 and D4 (DIR-035); revised in the C1 rework that the Owner authorized under [DIR-056](../00-governance/DECISION_LOG.md#dir-055-dir-056-obs-020-and-tech-028--p7-ux-re-baseline-checkpoint-c1-decision-rework-authorization-baseline-and-rework), after the Checkpoint C1 decisions OC-01–OC-12 of [DIR-055](../00-governance/DECISION_LOG.md#dir-055-dir-056-obs-020-and-tech-028--p7-ux-re-baseline-checkpoint-c1-decision-rework-authorization-baseline-and-rework); revised again in the C1 visual checkpoint that the Owner authorized under [DIR-058](../00-governance/DECISION_LOG.md#dir-057-dir-058-obs-021-and-tech-029--p7-ux-re-baseline-checkpoint-c1-visual-direction-decisions-visual-checkpoint-authorization-baseline-and-visual-anchors), after the visual-direction decisions of [DIR-057](../00-governance/DECISION_LOG.md#dir-057-dir-058-obs-021-and-tech-029--p7-ux-re-baseline-checkpoint-c1-visual-direction-decisions-visual-checkpoint-authorization-baseline-and-visual-anchors).

**Checkpoint C1.** Its Stage 1 direction was rejected at Checkpoint C1 for focused rework ([DIR-055](../00-governance/DECISION_LOG.md#dir-055-dir-056-obs-020-and-tech-028--p7-ux-re-baseline-checkpoint-c1-decision-rework-authorization-baseline-and-rework)). At the re-review the Owner preserved the rework's structure and did not approve its visual direction ([DIR-057](../00-governance/DECISION_LOG.md#dir-057-dir-058-obs-021-and-tech-029--p7-ux-re-baseline-checkpoint-c1-visual-direction-decisions-visual-checkpoint-authorization-baseline-and-visual-anchors)).

**C1 visual checkpoint.** This revision is VC1's (DIR-058 §12). It holds one draft visual system derived from the Owner's approved mockups REF-M1–REF-M3, applied page-scoped to the six visual anchors AN-1–AN-6 for the Owner's visual review at Checkpoint C1. The R1 pastel tokens are superseded (VD-02, VD-03); the R1 revision stays in Git at commit 4 (`git show 2996465:docs/07-ux-design/DESIGN_REFERENCES.md`) and the Stage 1 revision at commit 2 (`git show 2a5c8aa:docs/07-ux-design/DESIGN_REFERENCES.md`).

**Draft — not normative.** This record follows AICWDF §18.12. Nothing in it changes [INFORMATION_ARCHITECTURE](INFORMATION_ARCHITECTURE.md), [DESIGN_SYSTEM](DESIGN_SYSTEM.md) or [ADMIN_FLOW](ADMIN_FLOW/README.md), and no build unit may use it until a later authorized stage approves it — until then it is not the active design reference of AGENTS.md (the stage plan is in [P7_REBASELINE_GATE](evidence/P7_REBASELINE_GATE.md#plan)). The reconciliation of every reference element, the disposition delta, the document composer, the anchors and their measured checks are in that gate record, from [C1 visual checkpoint — intake](evidence/P7_REBASELINE_GATE.md#c1-visual-checkpoint--intake) to [Gate VC1](evidence/P7_REBASELINE_GATE.md#gate-vc1).

**Reference hierarchy (VD-01).** PRIMARY: the three approved MultipleCorp mockups REF-M1 (Owner Beranda), REF-M2 (sign-in) and REF-M3 (Buat Pembelian) govern palette, typography, card treatment, spacing, density, icon style, proportions, hierarchy, chart treatment and identity. SECONDARY: the application video REF-V2 refines restraint, rhythm, precision, panels, modals and transitions, never overriding the mockups. SPECIAL: the modern-login video REF-V1 informs the sign-in composition and its transition only. The repository, P0–P6 and R1's preserved structure win wherever a reference conflicts with them, and the design adapts. The five references stay outside the repository; they are recorded as text in [SOURCE_OF_TRUTH](../00-governance/SOURCE_OF_TRUTH.md#p7-ux-re-baseline-checkpoint-c1-visual-direction-decisions-handoff-corrections-and-visual-checkpoint-authorization) and the gate record's [reference inspection](evidence/P7_REBASELINE_GATE.md#c1-visual-checkpoint--reference-inspection) (RH-3).

**Precedence.** The references inform the visual and interaction direction only. They never override business truth, authorization, company scope, field projection, security or accessibility, and the approved P0–P6 specifications govern wherever they speak (DIR-054 §3; DIR-056 §3; DIR-058 §3). DesainPakeAI is an exploration workspace and never a source of truth.

## 1. Reference sources

| Reference | Role | What it is | How it is used |
| --- | --- | --- | --- |
| REF-M1 | PRIMARY | The Owner Beranda mockup, 1448 × 1086 | Measured for palette, type, radii, shadow, spacing and wells; its Beranda form restyles R1's Owner home (AN-2) |
| REF-M2 | PRIMARY | The sign-in mockup, 1448 × 1086 | Measured for the split card, controls and the soft olive primary; restyles R1's sign-in (AN-1) |
| REF-M3 | PRIMARY | The Buat Pembelian mockup, 1448 × 1086 | Measured for the form card, the line table and the summary; the benchmark of AN-5 and of every line editor |
| REF-V2 | SECONDARY | An application showcase video, 47.48 s | Rhythm, precision, panels that keep context, modals with a scrim, short purposeful transitions |
| REF-V1 | SPECIAL | A modern-login tutorial video, 11.59 s | The split card with one curved coloured panel and its slide between states — sign-in only |
| Ramp | Former primary reference, superseded by VD-01 | The DesainPakeAI project's starter design system, version alpha.3 (18 colour tokens, 8 typography roles, 8 written sections), read as tokens and written guidance only | No longer a source of the visual system; DIR-053 K3's non-copy rules stay ACTIVE (§4). Its design context and tokens were unchanged by every re-baseline session — at the C1 visual checkpoint, context revision `sha256-cfd0fe0cac39bda1` before authoring and `sha256-1da363b00129df2d` after its last session, the design context and tokens differing only by that revision |

The name "Ramp" appears in planning records only as the identifier of that DesainPakeAI starter design. It never appears in the product, its copy, its tokens or its component names. No reference, frame, crop, screenshot or render is in the repository or in a prototype; the sign-in visual panel of AN-1 is an abstract composition, not a crop of REF-M2 (VP-5).

## 2. Relevant components and screens

| From | Taken | Where |
| --- | --- | --- |
| REF-M1–REF-M3 | A warm ivory canvas with white cards; near-black ink with grey secondary text; quiet borders and a subtle resting shadow; a large card radius; the olive selected navigation fill with its rule; outline icons in round tinted wells; a deep green primary; a soft olive sign-in primary; a pale yellow-green accent fill; a pale table header; compact KPI cards; the ordered ageing bar with its legend; the breadcrumb with its back link; the project context card; the line table with metadata below the row; the summary card; the stepper's style for the journey's stage track | Every anchor |
| REF-V2 | Compact but calm density; precise alignment; a side panel or overlay that keeps context; a modal over a dimmed, blurred page; short fades and slides; one restrained accent | AN-2–AN-5; the composer's full preview |
| REF-V1 | The split sign-in card; one curved coloured panel that moves with the state change; a restrained form | AN-1 |

The element-by-element reconciliation — ADOPT, ADAPT or EXCLUDE, with reasons — is the gate record's [reference reconciliation](evidence/P7_REBASELINE_GATE.md#c1-visual-checkpoint--reference-reconciliation).

## 3. Extracted principles

1. **Work surfaces on a warm canvas.** White cards on an ivory canvas; near-black ink stays dominant on every screen.
2. **Restrained olive and green, semantic colour only where meaning requires it.** A deep green marks the primary action; a soft olive the sign-in action; olive tints mark the selected entry, the current step and secondary add actions; blue, amber and rose appear only for real states (VD-03). No multi-pastel decorative surfaces (VD-02); no saturated lime anywhere.
3. **Soft depth.** A subtle shadow at rest separates cards from the canvas; menus, sheets and dialogs rise with an overlay shadow over a scrim.
4. **A calm shape vocabulary.** A 16 px card radius, 10 px controls, pills for status badges and counts.
5. **One readable family.** Plus Jakarta Sans with tabular figures; a 15 px body, nothing essential below 13 px; hierarchy from size and weight.
6. **Density from alignment, not from small text.** One control height per density; rows aligned to their tops, bottoms and baselines.
7. **Icons in wells.** Thin outline icons, alone or in round tinted wells that mark a card's subject.
8. **Status is not colour alone.** States carry a label and an icon shape; charts carry direct labels, amounts and a data table.
9. **Functional motion only.** State changes, panels, sheets and dialogs move briefly; nothing loops, counts up or decorates, and reduced motion removes movement (VP-8).

## 4. Intentionally not copied

**Ramp (DIR-053 K3, still ACTIVE).**

| Element | Treatment | Reason |
| --- | --- | --- |
| The name, a logo, a wordmark, a screenshot or any brand asset | Not used anywhere in the product or the repository | K3; no imitation of another organization's brand |
| The chat composer recipe | Not taken over | Not a V1 capability (V1_SCOPE); K3; SLOP-45 |
| Lime as a fill, as text, as a line or as the only cue of a state | Never | It measures 1.23:1 against white (WCAG 2.2 1.4.3 and 1.4.11); OC-07's rejection of a dominant lime stays in spirit |

**The mockups.** Excluded elements and their reasons, in brief; the full list is in the reconciliation:

| Element | Reference | Reason |
| --- | --- | --- |
| The sidebar upsell card; the greeting hero with emoji, quote and photo | REF-M1 | VP-2; SLOP-21; OC-05 |
| KPI deltas and sparklines | REF-M1 | VP-3: no budgeted second period or series |
| The "Aktivitas terbaru" feed; the "Nilai proyek" donut; project photographs and percentages | REF-M1 | QB-10 and SLOP-39; overlapping categories (PT-24); VP-4; VP-5 |
| Rows for receiving validation and budget approval | REF-M1 | No such Owner workflow exists in V1 (WF-INV-01) |
| The left panel's headline, paragraph, feature bullets, security claim and quote; the supporting sentence under the heading; the building photograph | REF-M2 | Marketing copy (SLOP-21, SLOP-35), an unsupported claim, OC-06; VP-5 |
| The purchase stepper as a flow; the subtitle; "Mata uang"; "Catatan"; "Simpan sebagai draf"; a second add path | REF-M3 | One saving command and no draft state (WF-PUR-01); OC-06; no currency concept and no notes column (DATABASE §4.6); C1A-2 |
| A literal tax rate | REF-M3 | The configured treatment is shown without an asserted legal rate (DATABASE §12) |

**The videos.**

| Element | Reference | Reason |
| --- | --- | --- |
| Sign-up, the social sign-in row, the greeting copy, unlabelled inputs, the saturated gradient as it is, the code overlay and the creator's watermark | REF-V1 | No public registration (VD-05); Google only (AU-22); labels required; controlled blue instead; tutorial content |
| The assistant panel and chat bubbles; counts on navigation entries; "mine / team" assignment; goal percentages; count-up numbers; people photographs and avatars as content; the dark hero card; camera zooms and pans; the product's brand | REF-V2 | SLOP-45; D-IA-06; PL-4; SLOP-44; VP-8; VP-5; the light mockup identity; showcase motion, not interface behaviour; DIR-058 §21 |

## 5. Draft mapping to shadcn/ui

Component names and the theming mechanism are those of the official documentation verified on 2026-10-04 ([P7_REBASELINE_GATE](evidence/P7_REBASELINE_GATE.md#official-documentation-verification); Collapsible verified in the [C1 rework intake](evidence/P7_REBASELINE_GATE.md#c1-rework-intake)). The theme variables are written in OKLCH there; the hex values of §6 convert at implementation. Base UI is the current default primitive library and Radix remains supported; the choice stays with P11 (DEP-07).

| Pattern | shadcn/ui components | Theme variables |
| --- | --- | --- |
| Application shell and navigation | Sidebar with the primary entries and a Collapsible *Menu lengkap*; Sheet or Drawer for the phone's *Lainnya*; Dropdown Menu for "Buat" and the account; Tooltip | `--sidebar` surface; `--sidebar-accent` selected; `--sidebar-primary` accent rule; `--sidebar-ring` focus |
| Search, pickers and the shortcut list | Command, Combobox, Popover, Input | `--input` control edge; `--ring` focus |
| Module sub-pages and views | Tabs (as links for module pages); Toggle Group for two to four views | `--muted` track; `--card` selected segment |
| Project journey | An ordered list of stages composed from Badge and Separator; Card for the panels; Collapsible for "Tahap yang tidak diperlukan" and "N syarat lain terpenuhi" — no new component | the status fills; `--accent` the current step |
| Buttons | Button: default = deep green with white text; secondary = soft olive with dark text, for sign-in only; outline = control-edge border; ghost = quiet; an accent variant for secondary add actions | `--primary` · `--primary-foreground`; `--secondary` · `--secondary-foreground` soft olive; `--accent` · `--accent-foreground` |
| Status badges and company codes | Badge with an icon | the status fills and inks |
| Key figures and panels | Card, with an icon well | `--card`; the well tints |
| Lists and tables | Table and the Data Table guide | `--muted` for headers |
| Charts of defined values | Chart, always with a data-table alternative | `--chart-1`–`--chart-5` the ordered ageing ramp only |
| Forms | Field, Label, Input, Select, Native Select, Checkbox, Radio Group, Textarea; Collapsible for "Rincian lain" | `--input`, `--ring`, `--destructive` |
| Second factor | Input OTP, or one Input with `autocomplete="one-time-code"` — decided in Stage 2 | — |
| Dialogs, sheets and the document preview | Dialog, Alert Dialog, Sheet, Drawer; the composer's full preview as a Dialog | an overlay scrim with a blur |
| Feedback | Toast (Base UI) or Sonner (Radix), Skeleton, Alert | — |

## 6. Draft tokens

Derived from REF-M1–REF-M3 by sampling flat areas of the received mockups with a script kept outside the repository (the [reconciliation](evidence/P7_REBASELINE_GATE.md#c1-visual-checkpoint--reference-reconciliation) a)), and prototyped page-scoped in the `p7r3-` pages. Contrast computed with the WCAG 2.2 relative-luminance formula and checked again on the rendered pages ([measured checks](evidence/P7_REBASELINE_GATE.md#c1-visual-checkpoint--measured-checks)).

**Colour roles**

| Role | Value | Use |
| --- | --- | --- |
| canvas | `#F5F6F1` | the warm ivory field behind cards |
| surface · surface-subtle · surface-warm | `#FFFFFF` · `#F3F4EF` · `#FCFBF5` | cards, forms and tables; table headers, segmented tracks, quiet groups; the attachment drop zone |
| ink · ink-muted · ink-subtle | `#16181D` · `#5A5E66` · `#6E727A` | text and icons; secondary text, never below 13 px; placeholders and empty markers on a white surface only |
| border · border-strong | `#E4E5DF` · `#D2D4CC` | decorative dividers and card edges only |
| control-edge | `#878B83` | the boundary of inputs, selects and secondary buttons; the selected segment's ring |
| focus | `#2B6CB0` | the focus ring, 2 px with a 2 px offset |
| primary · primary-hover · on-primary | `#1A5034` · `#123D27` · `#FFFFFF` | the primary action in the application; links |
| soft · soft-hover · on-soft | `#C2CE8E` · `#B3C17B` · `#1C220E` | the sign-in primary only |
| accent-tint · accent-ink · accent-rule | `#EEF3CF` · `#3B4711` · `#6B7B26` | secondary add actions, the "now" tag and the next-step emphasis; their text; the selected entry's 3 px rule and the current step's ring |
| selected | `#EAEECD` | the selected navigation entry's fill |
| wells — olive · blue · amber · rose, each with its ink | `#EBEFD9` `#4A5524` · `#DFECF6` `#285781` · `#FAEBD9` `#80501A` · `#F8E4E1` `#932F27` | icon wells: olive for key figures and neutral subjects; blue, amber and rose only where a row's severity calls for them |
| ok · info · warn · danger · neutral — fill, ink and mark | `#E4F0E7` `#21593A` `#3B7F55` · `#E2EDF8` `#285781` `#4777A6` · `#FBEED6` `#76500F` `#A87420` · `#FAE4E1` `#922E26` `#BC4637` · `#EEEFEA` `#4A4E54` — | status badges, stage marks and alerts |
| ageing ramp age-0 … age-4 | `#7C8B81` · `#A4862B` · `#B16C36` · `#B0493A` · `#882A23` | the receivable-ageing buckets in their order, from not yet due to more than 90 days (VP-6) |

**Text (WCAG 1.4.3, at least 4.5:1)**

| Foreground | Background | Ratio |
| --- | --- | --- |
| ink | surface · canvas · surface-subtle · selected · olive well · accent-tint | 17.76 · 16.35 · 16.07 · 14.89 · 15.12 · 15.52 |
| ink-muted | surface · canvas · surface-subtle · selected | 6.51 · 5.99 · 5.89 · 5.46 |
| ink-subtle | surface | 4.83 |
| on-primary | primary · primary-hover | 9.37 · 12.19 |
| on-soft | soft · soft-hover | 9.74 · 8.43 |
| primary (links) | surface · canvas · surface-subtle · selected | 9.37 · 8.63 · 8.48 · 7.86 |
| accent-ink | accent-tint · surface · selected | 8.76 · 10.02 · 8.40 |
| ok · info · warn · danger · neutral ink | their fills | 7.01 · 6.39 · 6.26 · 6.53 · 7.24 |
| well inks — olive · blue · amber · rose | their wells | 6.84 · 6.30 · 5.83 · 6.42 |

The lowest token pair is ink-subtle on the surface, 4.83:1, used for placeholders; ink-subtle measures 4.44:1 on the canvas and is never used there.

**Non-text (WCAG 1.4.11, at least 3:1)**

| Indicator | Against | Ratio |
| --- | --- | --- |
| control edge | surface · canvas · surface-subtle | 3.47 · 3.20 · 3.14 |
| focus ring | surface · canvas · surface-subtle | 5.42 · 4.99 · 4.90 |
| accent rule — the selected entry's rule, the current step's ring | surface · selected · accent-tint | 4.68 · 3.93 · 4.09 |
| primary fill | surface | 9.37 |
| status marks — ok · info · warn · danger | surface | 4.82 · 4.71 · 4.05 · 5.16 |
| ageing ramp age-0 … age-4 | surface | 3.58 · 3.49 · 4.14 · 5.44 · 8.74 |
| decorative only, never an indicator: border, border-strong, the soft fill | surface | 1.27 · 1.50 · 1.68 |

The ageing bar's segments are separated by 2 px gaps, labelled in a legend with amounts and shares, and repeated in a table, so no bucket is told apart by colour alone. The soft sign-in button and the accent buttons are identified by their labels, not by their fill.

**Type** — Plus Jakarta Sans, the open-licensed family closest to the mockups' geometric sans: SIL Open Font License 1.1, version 2.7.1, by Tokotype (Gumpita Rahayu), source <https://github.com/tokotype/PlusJakartaSans>, accessed 2026-10-05; tabular figures (`tnum`) since version 2.600. The product self-hosts it (SECURITY WS-08, `font-src 'self'`); the prototypes load it from a font CDN. Weights 400, 500, 600 and 700 — 800 only for the placeholder wordmark; numbers in tabular figures.

| Role | Desktop | Phone | Use |
| --- | --- | --- | --- |
| title | 30 / 38, 700 | 24 / 30, 700 | the page heading |
| heading | 20 / 28, 700 | 18 / 24, 700 | card and section headings (18 / 26 for form cards) |
| subheading | 16 / 24, 700 | 16 / 24, 700 | groups and panels |
| figure | 24 / 30, 700 | 24 / 30, 700 | key figures |
| body | 15 / 22, 400 | 16 / 24, 400 | text and inputs |
| label | 14 / 20, 500 | 14 / 20, 500 | field labels, menu entries, compact buttons |
| table header and metadata | 13 / 18, 700 and 400 | 13 / 18 | column headers; SKU, project item and secondary lines |

Nothing essential is below 13 px. The sign-in uses a 16 px body in its 52 px controls and a 24 px, letter-spaced code field.

**Spacing** — a 4 px base with the steps 4, 8, 12, 16, 20, 24, 28, 32 and 40; page padding 28 px on desktop and 16 px on phones; card padding 20–24 px (16 px on phones); 12 px gaps inside a row of controls; at least twice as much space between groups as within them.

**Sizes** — one control height per density and pointer: 40 px with a fine pointer, 36 px in the dense line editors and for row actions, 48 px on the phone and with a coarse pointer at any width — R1's heights, kept; 52 px for the sign-in, the one change, justified by REF-M2, whose sign-in controls measure about 55 px. Text links and disclosures at least 24 px, and 44 px on the phone; rows of work lists at least 48 px; phone list rows at least 64 px.

**Shape and depth** — radii 16 px for cards, 20 px for dialogs and sheets, 12 px for sign-in controls and inner panels, 10 px for controls and buttons, 8 px for segmented items and chips, 6 px for company codes, and a pill for badges and counts. Shadow at rest `0 1px 2px` at 5% and `0 1px 1px` at 3% ink; raised bars `0 6px 18px` at 7%; overlays `0 20px 48px` at 18%, over a 32% ink scrim with a 3 px blur (36%, without blur, under the phone sheets).

**Icons** — Lucide, ISC licence, source <https://github.com/lucide-icons/lucide>, accessed 2026-10-05: outline icons with a 1.75 px stroke at 16, 18, 20 and 24 px; round wells of 40 px (48 px for a context card). The prototypes draw equivalent outline paths inline.

**Motion (VP-8)**

| Token | Value | Use |
| --- | --- | --- |
| fast | 120 ms, standard easing `cubic-bezier(.2,0,0,1)` | hover, pressed and selected states |
| handoff | 160 ms, standard easing; the exit easing `cubic-bezier(.4,0,1,1)` for leaving | the sign-in's hand-off into the application; sheets leaving |
| base | 200 ms, standard easing | menus, dialogs, sheets, toasts, pane entrances, disclosure chevrons |
| panel | 320 ms, emphasized easing `cubic-bezier(.3,0,0,1)` | the sign-in visual panel's morph between credential and verification |
| reduced motion | every transition and animation at 1 ms — an instant change, no movement | under `prefers-reduced-motion: reduce` |

No transition delays focus or input: focus moves when the state changes, and no step waits for an animation. No count-up, parallax, looping or decorative motion.

## 7. Draft responsive rules

- **Phone, below 768 px:** a 56 px top bar with the back action and the page title; a bottom bar of up to five entries — for the Owner *Beranda* · *Proyek* · *Cari* · *Lainnya*, for an Admin *Pekerjaan* · *Proyek* · *Cari* · *Lainnya*, each present only when the account may open it; *Lainnya* a sheet with *Kerja*, *Menu lengkap* and *Akun*; forms in one column with the action bar fixed at the bottom; a scan task keeps its scan field fixed at the bottom, with "Cari Produk" beside its status.
- **Tablet, 768–1023 px:** the navigation collapses; forms keep one column; a data table scrolls inside its own frame with its first column fixed.
- **Desktop, 1024 px and wider:** a 248 px sidebar with the primary entries and the collapsed *Menu lengkap*; module sub-pages as tabs on the module page; overview pages at most 1,240 px wide including the page padding; lists and tables use the available width up to 1,440 px.
- **Size follows the input:** a coarse pointer gets 48 px controls and touch-sized rows at any width.
- **Reflow and long text:** pages reflow at 320 px without horizontal page scrolling (WCAG 2.2 1.4.10); labels never wrap or truncate — a row of facts wraps between its items, never inside one; codes such as racks and SKUs never break at their hyphens; the English renderings were checked for length.

### Alignment specification (OC-09)

Kept from R1, with VC1's measured values.

- One control height per density — 40 px, 36 px dense and for row actions, 48 px touch, 52 px for the sign-in, the code field included.
- A column grid for multi-line editors, with stated proportions; for the purchase line: number 40 px · product and project item minmax(260 px, 1fr) · quantity 96 px · unit 100 px · unit price 140 px · tax 148 px · subtotal 150 px · row action 40 px, with 12 px gaps; every control of a row in one grid row, so tops, bottoms and baselines coincide within 0.5 px — the text of inputs is lifted 1 px with a 2 px bottom padding so that it shares the baseline of selects and plain text.
- Numbers right-aligned with tabular figures, in inputs, cells and summaries.
- Labels above controls with one 6 px gap; supporting text below controls with one 6 px gap.
- Secondary metadata — SKU, project item, available stock — on its own line below the control row, never shifting the row's baseline.
- Less-common fields behind "Rincian lain".
- The dense mode only on the named screens: the multi-line editors of quotations and purchases, *Riwayat Stok*, *Mutasi Bank*, *Laporan*, *Laporan Gabungan*, *Riwayat Aktivitas* and *Log Keamanan*.
- A summary beside or below the form it totals, so the line editor keeps the full width; a long form keeps its total and its actions in a bar fixed at the bottom of the view.

### Copy rules (OC-06)

Kept from R1.

- A heading, a state or number and an action; a helper sentence only when the user needs it to decide or act now — otherwise reduced, folded into a disclosure or removed.
- Mandated copy stays visible and concise: AU-04's and H7-01's generic answers, H7-16's advice, H7-11's confirmation, IP-16's file line with FL-02's types and limit, and the MSG texts ADMIN_FLOW requires.
- No purpose lines on headings, task rows or tiles; the page states its purpose through its title and its first actionable element.
- Labels and states in plain Indonesian; numbers with their unit; counts per company, never summed for an Admin.

## 8. Lint findings and dispositions

`dpai design lint` lints the project's design system, which the page-scoped prototypes do not change.

| Run | Result | Disposition |
| --- | --- | --- |
| The reference, 2026-10-04 16:09 WIB | 0 errors, 18 warnings, 1 info — the same count P7 recorded | — |
| 10 × broken-ref: `borderColor` and `borderWidth` are not recognized component sub-tokens | A limitation of the tool's design format | Not applicable to these draft tokens, which name borders as roles; no change to the reference, which is not modified |
| 1 × contrast-ratio: ink on a transparent background at 1.06:1 (the secondary button) | A false positive: the transparent background resolves to the surface (19.76:1) or the canvas (18.39:1), measured | No action |
| 7 × orphaned tokens: primary-strong, primary-quiet, text-muted, warning, on-warning, danger, on-danger | Defined in the reference but unused by its own recipes | Informational |
| 1 × info: token summary | — | — |
| After the p7r- pages were authored | the result is recorded in the gate record's [Prototypes](evidence/P7_REBASELINE_GATE.md#prototypes) section | — |
| After the `p7r2-` pages were authored, 2026-10-04 21:22 WIB | 0 errors, 18 warnings, 1 info — identical to the readiness run of the C1 rework ([C1 rework — prototypes](evidence/P7_REBASELINE_GATE.md#c1-rework--prototypes)) | No action: the pages are page-scoped and change no design asset |
| After the `p7r3-` pages were authored, sessions P7R3-S01–S05, 2026-10-05 02:31–03:32 WIB | 0 errors, 18 warnings, 1 info — the output identical to the readiness run apart from the project revision ([C1 visual checkpoint — anchors](evidence/P7_REBASELINE_GATE.md#c1-visual-checkpoint--anchors)) | No action: the pages are page-scoped and change no design asset |

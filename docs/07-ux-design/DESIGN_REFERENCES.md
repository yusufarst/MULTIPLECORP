# Design references

Status: REVIEW | Updated: 2026-10-04 | Owner: Planning

Authority: the P7 UX re-baseline, authorized for Stage 0 and Stage 1 by the Owner under [DIR-054](../00-governance/DECISION_LOG.md#dir-053-dir-054-obs-019-and-tech-027--p7-ux-re-baseline-decisions-stage-01-authorization-baseline-and-direction), with the design reference the Owner chose in [DIR-053](../00-governance/DECISION_LOG.md#dir-053-dir-054-obs-019-and-tech-027--p7-ux-re-baseline-decisions-stage-01-authorization-baseline-and-direction) K3 and the standing intent of D3 and D4 (DIR-035); revised in the C1 rework that the Owner authorized under [DIR-056](../00-governance/DECISION_LOG.md#dir-055-dir-056-obs-020-and-tech-028--p7-ux-re-baseline-checkpoint-c1-decision-rework-authorization-baseline-and-rework), after the Checkpoint C1 decisions OC-01–OC-12 of [DIR-055](../00-governance/DECISION_LOG.md#dir-055-dir-056-obs-020-and-tech-028--p7-ux-re-baseline-checkpoint-c1-decision-rework-authorization-baseline-and-rework).

**Checkpoint C1.** Its Stage 1 direction was rejected at Checkpoint C1 for focused rework ([DIR-055](../00-governance/DECISION_LOG.md#dir-055-dir-056-obs-020-and-tech-028--p7-ux-re-baseline-checkpoint-c1-decision-rework-authorization-baseline-and-rework)); this revision is the C1 rework's, under DIR-056, for the Owner's re-review at Checkpoint C1.

**C1 visual checkpoint.** Primary visual reference replaced by the Owner's approved mockups ([DIR-057](../00-governance/DECISION_LOG.md#dir-057-dir-058-obs-021-and-tech-029--p7-ux-re-baseline-checkpoint-c1-visual-direction-decisions-visual-checkpoint-authorization-baseline-and-visual-anchors) VD-01; kept outside the repository); the R1 pastel tokens are superseded; revision under DIR-058.

**Stage 1 draft, revised at Checkpoint C1 (DIR-055) — not normative.** This record follows AICWDF §18.12. Nothing in it changes [INFORMATION_ARCHITECTURE](INFORMATION_ARCHITECTURE.md), [DESIGN_SYSTEM](DESIGN_SYSTEM.md) or [ADMIN_FLOW](ADMIN_FLOW/README.md), and no build unit may use it until a later authorized stage approves it — until then it is not the active design reference of AGENTS.md (the stage plan is in [P7_REBASELINE_GATE](evidence/P7_REBASELINE_GATE.md#plan)). The diagnosis, the rejected Stage 1 direction and the revised direction of the C1 rework, with their contrast evidence and prototypes, are recorded in that gate record; [C1 rework — revised direction](evidence/P7_REBASELINE_GATE.md#c1-rework--revised-direction) is the current one. This record as drafted in Stage 1 and rejected stays in Git at commit 2 (`git show 2a5c8aa:docs/07-ux-design/DESIGN_REFERENCES.md`).

**The reference's role (OC-07).** The reference stays a source of hierarchy, spacing and interaction ideas — its canvas, surface and ink structure, flat layers, small shape vocabulary, readable 15 px body and figures beside their work. Its lime accent is not a MultipleCorp identity and is not used. DIR-053 K3's limits stay: no name, logo, wordmark, brand asset or screenshot, no chat composer, no lime as low-contrast text.

**Precedence.** The reference informs the visual and interaction direction only. It never overrides business truth, authorization, company scope, field projection, security or accessibility, and the approved P0–P6 specifications govern wherever they speak (DIR-054 §3; DIR-056 §3). DesainPakeAI is an exploration workspace and never a source of truth.

## 1. Reference source

| Item | Record |
| --- | --- |
| Workspace | DesainPakeAI project MULTIPLECORP, read with the CLI 0.2.2 from a temporary directory outside the repository |
| Reference | The project's starter design system named "Ramp", version alpha.3: 18 colour tokens, 8 typography roles, 4 radius levels, 12 spacing steps, 10 component recipes and 8 written sections (overview, colours, typography, layout, elevation, shapes, components, do's and don'ts) |
| Context revision | `sha256-3fe26282863db253`, read on 2026-10-04 at 15:44 WIB, before any p7r- page existed; unchanged in substance at the C1 rework's readiness (`sha256-c0ea150c276c0e29`, 19:01 WIB) and after its authoring (`sha256-cfd0fe0cac39bda1`, 21:22 WIB) — the same tokens and design context |
| How it was read | As tokens and written guidance only — the design context, the token list and the lint report. No screenshot, logo, wordmark, product image or brand asset of any Ramp product was obtained, and none is stored here (K3) |
| Earlier evaluation | P7 rejected this starter design in exploration brief DPB-01 (D-DS-01; [P7_QUALITY_GATE](evidence/P7_QUALITY_GATE.md#briefs)); the re-baseline re-reads it as the Owner's primary reference (D3; K3), its role narrowed by OC-07 |

The name "Ramp" appears in planning records only as the identifier of that DesainPakeAI starter design. It never appears in the product, its copy, its tokens or its component names.

## 2. Relevant components and screens

The reference holds foundations and component recipes, not screens.

| Recipe or guidance | Relevance to V1 |
| --- | --- |
| Page: canvas background, ink text, 15 px body | Used — the base of every screen, on a warm neutral canvas |
| Card and muted panel: white or muted surface, 1 px border, 8 px radius, 16 px padding, no shadow | Used — work areas, key figures, panels of Beranda, the journey and forms |
| Primary button: lime fill, near-black text, 6 px radius, 12 × 16 px padding | Adapted — its radius and padding kept; the fill is a deep slate under white text, not lime (OC-07; §6) |
| Secondary button: transparent, 1 px ink border, 4 px radius | Used, with the control edge as its border |
| Input: white, Border Strong outline, dark olive focus ring | Used, adapted — a darker control edge (§4) and a dusty-blue focus ring |
| Positive status badge: pill with label | Used, adapted — a pastel pill under dark text, with a label and an icon shape for every status family |
| Decision rail: 4 px lime bar on a selected row, card or step | Adapted — a 3 px slate or dusty-blue rule marks the selected entry, the current step and the next line; never lime |
| Focus indicator: dark olive | Adapted — a dusty-blue 2 px ring with a 2 px offset |
| Chat composer | Not taken over (§4) |
| Layout guidance: product screens with a stable navigation rail, a compact filter band and one dominant work surface; overview pages near 1,200 px; summary figures beside the table they explain | Used |

## 3. Extracted principles

1. **Work surfaces on a calm canvas.** White work surfaces sit on a faintly warm neutral canvas; dark ink stays dominant on every screen.
2. **Calm colour that carries meaning.** One restrained primary colour marks the main action and the selected place; muted tones — sage, dusty blue, soft amber, muted rose — mark states; nothing is coloured for decoration, and the reference's lime is not used (OC-07; replaces Stage 1's "One accent marks the decision").
3. **Flat structure.** No shadow at rest; layers separate by surface steps and 1 px rules.
4. **A small shape vocabulary.** Radii of 4, 6 and 8 px; pills only for status badges, counts and compact filters.
5. **Readable operational type.** A 15 px body, nothing essential below 13 px, and hierarchy from size and spacing rather than heavy weights.
6. **Density from alignment, not from small text.** Compact rows are allowed where data is heavy; text size is not the lever.
7. **Figures next to their work.** Summary figures sit beside the table or workflow they explain, never in decorative tiles.
8. **Status is not colour alone.** Done, in progress, attention and missing are operational meanings carried with labels and icon shapes.

## 4. Intentionally not copied

| Element of the reference | Treatment | Reason |
| --- | --- | --- |
| The name, a logo, a wordmark or any brand asset | Not used anywhere in the product or the repository; the name appears only as the reference's identifier in planning records | DIR-053 K3; no imitation of another organization's brand |
| Screenshots of any Ramp product | None obtained or stored | K3 |
| The chat composer recipe | Not taken over | Not a V1 capability (V1_SCOPE); K3; SLOP-45 |
| The lime accent as a fill — the primary action, the selected entry, the attention badge | Not used in the revised direction | OC-07: too dominant, and not a MultipleCorp identity; the slate primary, a weight change, a 3 px rule and `aria-current` carry the same cues |
| Lime as text, as an icon stroke, as a thin line or as a chart mark on a light surface | Never | Lime measures 1.23:1 against white and 1.15:1 against the canvas, below WCAG 2.2 1.4.3 and 1.4.11 (K3; D3) |
| Lime as the only cue of a selected state | Not alone — and, in the revised direction, not at all | WCAG 1.4.11 for state indicators |
| Lime fill as the only boundary of a control | Not used | WCAG 1.4.11 |
| Display and large-heading roles of 40–56 px | Not adopted; the largest roles are the 26 px page title and key figure | No hero, landing or marketing surface exists in V1 (AICWDF §18.2; SLOP-26, SLOP-27) |
| A second, monospace family for data | Not adopted; Inter with tabular figures serves amounts, quantities and codes | One self-hosted family (SECURITY WS-08 `font-src 'self'`); long Rupiah amounts read better in a proportional face with aligned figures |
| Border Strong `#B8BAAE` as the edge of inputs | Replaced by a darker control edge — `#80837B` in the revised draft | `#B8BAAE` measures 1.97:1 against white, below 1.4.11; the control edge measures 3.29–3.85:1 |
| Warning `#A65D00` | Not used; the revised soft amber pairs `#76500E` text with `#F7EDD6` | `#A65D00` measures 4.45:1 on its tinted badge background, below 4.5:1; the revised pair measures 6.17:1 |
| A 1,200 px width for every page | Kept for overview pages only — at most 1,240 px including the 32 px page padding, about 1,180 px of content; lists and tables use the available width | The reference's own guidance for product screens |
| Any pattern that conflicts with business, security or accessibility | None found beyond the accessibility adjustments above; figures follow CS-08 — per company for an Admin, consolidated only for the Owner | DIR-054 §3; PERMISSIONS_MATRIX CS-08 |

## 5. Draft mapping to shadcn/ui

Component names and the theming mechanism are those of the current official documentation verified on 2026-10-04 ([P7_REBASELINE_GATE](evidence/P7_REBASELINE_GATE.md#official-documentation-verification); Collapsible verified in the [C1 rework intake](evidence/P7_REBASELINE_GATE.md#c1-rework-intake)). The theme variables are written in OKLCH there; the hex values below convert at implementation. Base UI is the current default primitive library and Radix remains supported; the choice stays with P11 (DEP-07).

| Pattern | shadcn/ui components | Theme variables |
| --- | --- | --- |
| Application shell and navigation | Sidebar with the primary entries and a Collapsible *Menu lengkap*; Sheet or Drawer for the phone's *Lainnya*; Dropdown Menu for "Buat" and the account; Tooltip | `--sidebar` surface, `--sidebar-primary` slate, `--sidebar-primary-foreground` white, `--sidebar-ring` dusty blue |
| Search and pickers | Command, Combobox, Popover, Input | `--input` control edge, `--ring` dusty blue |
| Module sub-pages and views | Tabs (as links for module pages); Toggle Group for two to four views | — |
| Project journey | An ordered list of stages composed from Badge and Separator; Card for the panels; Collapsible for "Tahap yang tidak diperlukan" and "N syarat lain terpenuhi" — no new component | the status tones |
| Buttons | Button: default = slate fill with white text; outline = control-edge border; ghost = quiet | `--primary` slate, `--primary-foreground` white |
| Status badges and company labels | Badge with an icon | the status fills and inks (§6) |
| Key figures and panels | Card | `--card` surface |
| Lists and tables | Table and the Data Table guide | `--muted` for headers |
| Charts of defined values | Chart, always with a data-table alternative | `--chart-1` neutral base, `--chart-2` the one highlight; no further series |
| Forms | Field, Label, Input, Select, Native Select, Checkbox, Radio Group, Textarea; Collapsible for "Rincian lain" | `--input`, `--ring`, `--destructive` |
| Second factor | Input OTP, or one Input with `autocomplete="one-time-code"` — decided in Stage 2 | — |
| Dialogs and sheets | Dialog, Alert Dialog, Sheet, Drawer | — |
| Feedback | Toast (Base UI) or Sonner (Radix), Skeleton, Alert | — |

## 6. Draft tokens

Stage 1 values, revised in the C1 rework and prototyped page-scoped in the `p7r2-` pages; the Stage 1 draft as rejected at Checkpoint C1 — its §§3 and 5–7, with the lime roles — is kept in Git (`git show 2a5c8aa:docs/07-ux-design/DESIGN_REFERENCES.md`, commit 2), and its contrast tables stay in the gate record's [Direction proposal](evidence/P7_REBASELINE_GATE.md#direction-proposal) as history. Contrast computed with the WCAG 2.2 relative-luminance formula by a script kept outside the repository, and checked again on the rendered pages ([measured checks](evidence/P7_REBASELINE_GATE.md#c1-rework--measured-checks)).

**Colour roles**

| Role | Value | Use |
| --- | --- | --- |
| canvas | `#F6F5F1` | the warm neutral field behind work surfaces |
| surface | `#FFFFFF` | work surfaces: tables, forms, panels |
| surface-muted | `#EFEDE7` | selected navigation entry, table headers, quiet groups |
| surface-sunk | `#F2F1EC` | sub-group bands, the journey's "now" strip |
| ink | `#1C1E1B` | text and icons |
| ink-muted | `#5C5F59` | secondary text, never below 13 px |
| border · border-strong | `#E4E2DB` · `#C9C7BF` | decorative dividers only |
| control-edge | `#80837B` | the boundary of inputs, selects, checkboxes and secondary buttons |
| primary · primary-hover · on-primary | `#2C3E50` · `#22313F` · `#FFFFFF` | the primary action; the selected entry's 3 px rule |
| focus | `#2E6290` | the focus ring, 2 px with a 2 px offset |
| sage fill · ink · mark | `#E5EFE7` · `#2E5B3C` · `#5E8A6B` | done, satisfied, approved |
| dusty blue fill · ink · mark | `#E4ECF4` · `#2C4D6D` · `#5B7FA6` | in progress, information, the current step, the next line |
| soft amber fill · ink · mark | `#F7EDD6` · `#76500E` · `#A87522` | attention, waiting, overdue |
| muted rose fill · ink · mark | `#F6E6E3` · `#8B3B33` · `#B05A50` | missing, blocked, failed |
| neutral fill · ink | `#EDEBE5` · `#4C4F49` | not started, history, not applicable |
| chart base · chart highlight | `#83867E` · `#A0701F` | the neutral series and the one highlight |

**Text (WCAG 1.4.3, at least 4.5:1)**

| Foreground | Background | Ratio |
| --- | --- | --- |
| ink | surface · canvas · surface-sunk · surface-muted | 16.79 · 15.39 · 14.84 · 14.34 |
| ink | sage · dusty blue · soft amber · muted rose · neutral fills | 14.26 · 14.07 · 14.42 · 13.87 · 14.08 |
| ink-muted | surface · canvas · surface-sunk · surface-muted | 6.49 · 5.95 · 5.74 · 5.54 |
| on-primary | primary · primary-hover | 10.98 · 13.30 |
| primary | surface · surface-muted | 10.98 · 9.38 |
| sage ink | its fill · surface | 6.66 · 7.84 |
| dusty-blue ink | its fill · surface | 7.37 · 8.79 |
| soft-amber ink | its fill · surface | 6.17 · 7.18 |
| muted-rose ink | its fill · surface | 6.26 · 7.58 |
| neutral ink | its fill · surface | 6.99 · 8.33 |

The lowest token pair is ink-muted on surface-muted, 5.54:1; the lowest rendered pair, ink-muted on the dusty-blue fill, 5.44:1.

**Non-text (WCAG 1.4.11, at least 3:1)**

| Indicator | Against | Ratio |
| --- | --- | --- |
| control edge | surface · canvas · surface-sunk · surface-muted | 3.85 · 3.53 · 3.41 · 3.29 |
| focus ring | surface · canvas · surface-muted (it sits on the surface, 2 px from the control) | 6.43 · 5.89 · 5.49 |
| primary fill | surface · canvas · surface-muted | 10.98 · 10.07 · 9.38 |
| status icons in badges | their fills | sage 6.66 · dusty blue 7.37 · soft amber 6.17 · muted rose 6.26 · neutral 6.99 |
| status marks — rules, track dots | surface | sage 3.94 · dusty blue 4.17 · soft amber 4.01 · muted rose 4.75 |
| chart base · chart highlight | surface · surface-sunk | 3.70 · 4.35 and 3.27 · 3.84 |
| decorative only, never an indicator: border, border-strong | surface | 1.30 · 1.69 |

Chart marks never touch: each bar sits in its own row, labelled in words, so the base and the highlight are never told apart by colour alone.

**Type** — Inter at 400, 500, 600 and 700 with tabular figures for numbers:

| Role | Desktop | Phone | Use |
| --- | --- | --- | --- |
| figure | 22 / 28, 600 | 18 / 24, 600 | key figures on Beranda, compact beside the decisions (OC-05) |
| title | 26 / 32, 600 | 22 / 28, 600 | the page heading |
| heading | 20 / 28, 600 | 18 / 24, 600 | section headings |
| subheading | 17 / 24, 600 | 16 / 22, 600 | panel and group titles |
| body | 15 / 22, 400 | 16 / 24, 400 | text and inputs |
| data | 15 / 22 (dense 14 / 20) | 15 / 22 | table cells and line-editor controls |
| label and small | 13 / 18, 600 and 400 | 13 / 18 | field labels, column headers, badges, metadata |

**Spacing** — a 4 px base with the steps 4, 8, 12, 16, 20, 24, 32, 40, 48 and 64; page padding 32 px on desktop and 16 px on phones; at least twice as much space between groups as within them.

**Sizes** — controls 40 px with a fine pointer, 36 px in the dense mode and for row actions, 48 px on the phone and with a coarse pointer at any width; text links and disclosures at least 24 px, and 44 px on the phone; table rows at least 48 px; phone list rows at least 64 px; the phone bottom bar's entries at least 56 px tall.

**Shape and depth** — radii 4 px (compact controls), 6 px (inputs and buttons), 8 px (panels, dialogs, sheets) and a pill for status badges, counts and filter chips; no shadow at rest; overlays on a solid surface with a 1 px edge and a scrim.

**Motion** — 150–180 ms with an easing curve, used only for feedback; none under reduced motion.

## 7. Draft responsive rules

- **Phone, below 768 px:** a 56 px top bar with the back action and the page title; a bottom bar of up to five entries — for the Owner *Beranda* · *Proyek* · *Cari* · *Lainnya*, for an Admin *Pekerjaan* · *Proyek* · *Cari* · *Lainnya*, each present only when the account may open it; *Lainnya* a sheet with *Kerja*, *Menu lengkap* and *Akun*; forms in one column with the action bar fixed at the bottom; a scan task keeps its scan field fixed at the bottom.
- **Tablet, 768–1023 px:** the navigation collapses; forms keep one column; a data table scrolls inside its own frame with its first column fixed.
- **Desktop, 1024 px and wider:** a 240 px sidebar with the primary entries and the collapsed *Menu lengkap*; module sub-pages as tabs on the module page; overview pages at most 1,240 px wide including the page padding, about 1,180 px of content; lists and tables use the available width up to 1,440 px.
- **Size follows the input:** a coarse pointer gets 48 px controls and touch-sized rows at any width.
- **Reflow and long text:** pages reflow at 320 px without horizontal page scrolling (WCAG 2.2 1.4.10), labels wrap rather than truncate, and the English renderings were checked for length.

### Alignment specification (OC-09)

- One control height per density — 40 px, 36 px dense and for row actions, 48 px touch; the code field 56 px.
- A column grid for multi-line editors, with stated proportions; for the purchase line: number 40 px · product and project item minmax(260 px, 1fr) · quantity 96 px · unit 104 px · unit price 148 px · tax 120 px · subtotal 156 px · row menu 36 px, with 12 px gaps; every control of a row in one grid row, so tops, bottoms and baselines coincide.
- Numbers right-aligned with tabular figures, in inputs, cells and position blocks.
- Labels above controls with one 6 px gap; supporting text below controls with one 6 px gap.
- Secondary metadata — SKU, project item, available stock — on its own line below the control row, never shifting the row's baseline.
- Less-common fields behind "Rincian lain".
- The dense mode only on the named screens: the multi-line editors of quotations and purchases, *Riwayat Stok*, *Mutasi Bank*, *Laporan*, *Laporan Gabungan*, *Riwayat Aktivitas* and *Log Keamanan*.
- A summary beside the form it totals, so the line editor keeps the full width.

### Copy rules (OC-06)

- A heading, a state or number and an action; a helper sentence only when the user needs it to decide or act now — otherwise reduced, folded into a disclosure or removed.
- Mandated copy stays visible and concise: AU-04's and H7-01's generic answers, H7-16's advice, H7-11's confirmation and the MSG texts ADMIN_FLOW requires.
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

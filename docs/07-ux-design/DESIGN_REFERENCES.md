# Design references

Status: REVIEW | Updated: 2026-10-04 | Owner: Planning

Authority: the P7 UX re-baseline, authorized for Stage 0 and Stage 1 by the Owner under [DIR-054](../00-governance/DECISION_LOG.md#dir-053-dir-054-obs-019-and-tech-027--p7-ux-re-baseline-decisions-stage-01-authorization-baseline-and-direction), with the design reference the Owner chose in [DIR-053](../00-governance/DECISION_LOG.md#dir-053-dir-054-obs-019-and-tech-027--p7-ux-re-baseline-decisions-stage-01-authorization-baseline-and-direction) K3 and the standing intent of D3 and D4 (DIR-035).

**Stage 1 draft for Checkpoint C1 — not normative.** This record follows AICWDF §18.12. Nothing in it changes [INFORMATION_ARCHITECTURE](INFORMATION_ARCHITECTURE.md), [DESIGN_SYSTEM](DESIGN_SYSTEM.md) or [ADMIN_FLOW](ADMIN_FLOW/README.md), and no build unit may use it until a later authorized stage approves it — until then it is not the active design reference of AGENTS.md (the stage plan is in [P7_REBASELINE_GATE](evidence/P7_REBASELINE_GATE.md#plan)). The diagnosis, the direction proposal with its contrast evidence and the prototypes it draws on are recorded in that gate record.

**Precedence.** The reference informs the visual and interaction direction only. It never overrides business truth, authorization, company scope, field projection, security or accessibility, and the approved P0–P6 specifications govern wherever they speak (DIR-054 §3). DesainPakeAI is an exploration workspace and never a source of truth.

## 1. Reference source

| Item | Record |
| --- | --- |
| Workspace | DesainPakeAI project MULTIPLECORP, read with the CLI 0.2.2 from a temporary directory outside the repository |
| Reference | The project's starter design system named "Ramp", version alpha.3: 18 colour tokens, 8 typography roles, 4 radius levels, 12 spacing steps, 10 component recipes and 8 written sections (overview, colours, typography, layout, elevation, shapes, components, do's and don'ts) |
| Context revision | `sha256-3fe26282863db253`, read on 2026-10-04 at 15:44 WIB, before any p7r- page existed |
| How it was read | As tokens and written guidance only — the design context, the token list and the lint report. No screenshot, logo, wordmark, product image or brand asset of any Ramp product was obtained, and none is stored here (K3) |
| Earlier evaluation | P7 rejected this starter design in exploration brief DPB-01 (D-DS-01; [P7_QUALITY_GATE](evidence/P7_QUALITY_GATE.md#briefs)); the re-baseline re-reads it as the Owner's primary reference (D3; K3) |

The name "Ramp" appears in planning records only as the identifier of that DesainPakeAI starter design. It never appears in the product, its copy, its tokens or its component names.

## 2. Relevant components and screens

The reference holds foundations and component recipes, not screens.

| Recipe or guidance | Relevance to V1 |
| --- | --- |
| Page: canvas background, ink text, 15 px body | Used — the base of every screen |
| Card and muted panel: white or muted surface, 1 px border, 8 px radius, 16 px padding, no shadow | Used — work areas, key figures, panels of Beranda and forms |
| Primary button: lime fill, near-black text, 6 px radius, 12 × 16 px padding | Used, adapted — with a dark olive edge (§4) |
| Secondary button: transparent, 1 px ink border, 4 px radius | Used |
| Input: white, Border Strong outline, dark olive focus ring | Used, adapted — a darker control edge (§4) |
| Positive status badge: pill with label | Used, adapted — a tinted pill with a tone label and an icon shape for every status family |
| Decision rail: 4 px lime bar on a selected row, card or step | Used, adapted — the rail is dark olive where it must carry the state (§4) |
| Focus indicator: dark olive | Used — 2 px ring with 2 px offset |
| Chat composer | Not taken over (§4) |
| Layout guidance: product screens with a stable navigation rail, a compact filter band and one dominant work surface; overview pages near 1,200 px; summary figures beside the table they explain | Used |

## 3. Extracted principles

1. **Work surfaces on a calm canvas.** White work surfaces sit on a faintly warm neutral canvas; near-black ink stays dominant on every screen.
2. **One accent marks the decision.** The lime accent marks the main action, the selected place and what needs attention — never decoration, and well under a tenth of a typical screen.
3. **Flat structure.** No shadow at rest; layers separate by surface steps and 1 px rules.
4. **A small shape vocabulary.** Radii of 4, 6 and 8 px; pills only for status badges, counts and compact filters.
5. **Readable operational type.** A 15 px body, nothing essential below 13 px, and hierarchy from size and spacing rather than heavy weights.
6. **Density from alignment, not from small text.** Compact rows are allowed where data is heavy; text size is not the lever.
7. **Figures next to their work.** Summary figures sit beside the table or workflow they explain, never in decorative tiles.
8. **Status is not colour alone.** Positive, warning and danger are operational meanings carried with labels and icons.

## 4. Intentionally not copied

| Element of the reference | Treatment | Reason |
| --- | --- | --- |
| The name, a logo, a wordmark or any brand asset | Not used anywhere in the product or the repository; the name appears only as the reference's identifier in planning records | DIR-053 K3; no imitation of another organization's brand |
| Screenshots of any Ramp product | None obtained or stored | K3 |
| The chat composer recipe | Not taken over | Not a V1 capability (V1_SCOPE); K3; SLOP-45 |
| Lime as text, as an icon stroke, as a thin line or as a chart mark on a light surface | Never | Lime measures 1.23:1 against white and 1.15:1 against the canvas, below WCAG 2.2 1.4.3 and 1.4.11 (K3; D3) |
| Lime as the only cue of a selected state | Not alone — the selected entry also has a 4 px dark olive rail (6.62:1 on white), a heavier weight and `aria-current` | WCAG 1.4.11 for state indicators |
| Lime fill as the only boundary of a control | The primary button carries a 1 px dark olive edge | WCAG 1.4.11 |
| Display and large-heading roles of 40–56 px | Not adopted; the largest roles are the 26 px page title and key figure | No hero, landing or marketing surface exists in V1 (AICWDF §18.2; SLOP-26, SLOP-27) |
| A second, monospace family for data | Not adopted; Inter with tabular figures serves amounts, quantities and codes | One self-hosted family (SECURITY WS-08 `font-src 'self'`); long Rupiah amounts read better in a proportional face with aligned figures |
| Border Strong `#B8BAAE` as the edge of inputs | Replaced by the control edge `#85877B` | `#B8BAAE` measures 1.97:1 against white, below 1.4.11; the control edge measures 3.21–3.65:1 |
| Warning `#A65D00` | Adjusted to `#9A5600` | `#A65D00` measures 4.45:1 on its tinted badge background, below 4.5:1; `#9A5600` measures 5.03:1 |
| A 1,200 px width for every page | Kept for overview pages only — at most 1,240 px including the 32 px page padding, about 1,180 px of content; lists and tables use the available width | The reference's own guidance for product screens |
| Any pattern that conflicts with business, security or accessibility | None found beyond the accessibility adjustments above; figures follow CS-08 — per company for an Admin, consolidated only for the Owner | DIR-054 §3; PERMISSIONS_MATRIX CS-08 |

## 5. Draft mapping to shadcn/ui

Component names and the theming mechanism are those of the current official documentation verified on 2026-10-04 ([P7_REBASELINE_GATE](evidence/P7_REBASELINE_GATE.md#official-documentation-verification)). The theme variables are written in OKLCH there; the hex values below convert at implementation. Base UI is the current default primitive library and Radix remains supported; the choice stays with P11 (DEP-07).

| Pattern | shadcn/ui components | Theme variables |
| --- | --- | --- |
| Application shell and navigation | Sidebar, Sheet or Drawer for the phone menu, Breadcrumb, Tooltip | `--sidebar` surface, `--sidebar-primary` lime, `--sidebar-primary-foreground` ink, `--sidebar-ring` olive |
| Search and pickers | Command, Combobox, Popover, Input | `--input` control edge, `--ring` olive |
| Area sub-navigation and record parts | Tabs (as links for area pages); Toggle Group for two to four views | — |
| Buttons | Button: default = lime fill with ink text and olive edge; outline = ink border; ghost = quiet | `--primary` lime, `--primary-foreground` ink |
| Status badges and company labels | Badge with an icon | tone colours and their soft tints (draft roles below) |
| Key figures and panels | Card | `--card` surface |
| Lists and tables | Table and the Data Table guide | `--muted` for headers |
| Charts of defined values | Chart, always with a data-table alternative | `--chart-1`–`--chart-5` |
| Forms | Field, Label, Input, Select, Native Select, Checkbox, Radio Group, Textarea | `--input`, `--ring`, `--destructive` |
| Second factor | Input OTP, or one Input with `autocomplete="one-time-code"` — decided in Stage 2 | — |
| Dialogs and sheets | Dialog, Alert Dialog, Sheet, Drawer | — |
| Feedback | Toast (Base UI) or Sonner (Radix), Skeleton, Alert | — |

## 6. Draft tokens

Stage 1 values for Checkpoint C1, prototyped page-scoped in the p7r- pages. Measured contrast is in the gate record's [Direction proposal](evidence/P7_REBASELINE_GATE.md#direction-proposal).

**Colour roles**

| Role | Value | Use |
| --- | --- | --- |
| canvas | `#F7F7F2` | the field behind work surfaces |
| surface | `#FFFFFF` | work surfaces: tables, forms, panels |
| surface-muted | `#F0F1E8` | table headers, filter bands, quiet groups |
| ink | `#0C0A08` | text and icons |
| ink-muted | `#66675F` | secondary text, never below 13 px |
| border | `#E5E7EB` | decorative dividers only |
| control-edge | `#85877B` | the boundary of inputs, selects and checkboxes |
| accent | `#E4F222` | lime fill under ink: the primary action, the selected navigation entry, the attention badge |
| accent-hover | `#C9D61E` | hover and press of the accent fill |
| accent-quiet | `#F4F8B8` | the next-step band and quiet selected rows |
| accent-edge | `#596200` | the edge of accent fills, the decision rail, the focus ring |
| positive · warning · danger · neutral | `#137A4A` · `#9A5600` · `#B42318` · `#4E4F48` | status tones in labels, icons and edges |
| positive-soft · warning-soft · danger-soft · neutral-soft | `#E6F2EB` · `#FBF0DF` · `#FBE8E6` · `#F0F1E8` | status badge fills |
| chart series | `#0C0A08` · `#596200` · `#8A8C80` · `#9A5600` · `#B42318` | chart marks, each at least 3:1 against white |

**Type** — Inter at 400, 500, 600 and 700 with tabular figures for numbers:

| Role | Desktop | Phone | Use |
| --- | --- | --- | --- |
| figure | 26 / 32, 600 | 20 / 26, 600 | key figures on Beranda |
| title | 26 / 32, 600 | 22 / 28, 600 | the page heading |
| heading | 20 / 28, 600 | 18 / 24, 600 | section headings |
| subheading | 17 / 24, 600 | 16 / 22, 600 | panel and group titles |
| body | 15 / 22, 400 | 16 / 24, 400 | text and inputs |
| data | 15 / 22 (dense 14 / 20) | 15 / 22 | table cells |
| label and small | 13 / 18, 600 and 400 | 13 / 18 | field labels, column headers, badges, metadata |

**Spacing** — a 4 px base with the steps 4, 8, 12, 16, 20, 24, 32, 40, 48 and 64; page padding 32 px on desktop and 16 px on phones; at least twice as much space between groups as within them.

**Sizes** — controls 40 px with a fine pointer, 36 px in the dense mode, 48 px with a coarse pointer at any width; table rows at least 48 px (40 px dense); phone list rows at least 64 px; the phone bottom bar's entries at least 60 px tall.

**Shape and depth** — radii 4 px (compact controls), 6 px (inputs and the primary button), 8 px (panels, dialogs, sheets) and a pill for status badges, counts and filter chips; no shadow at rest; overlays on a solid surface with a 1 px edge and a scrim.

**Motion** — 150–180 ms with an easing curve, used only for feedback; none under reduced motion.

## 7. Draft responsive rules

- **Phone, below 768 px:** a 60 px top bar with the back action and the page title; a bottom bar of up to five entries — Beranda, Proyek, Gudang, Cari, Lainnya, each present only when the account may open it; area pages open as task lists; forms in one column with the action bar fixed at the bottom; focused tasks such as a scan step hide the bottom bar.
- **Tablet, 768–1023 px:** the navigation collapses; forms keep one column; a data table scrolls inside its own frame with its first column fixed.
- **Desktop, 1024 px and wider:** a 248 px sidebar of flat area entries; area sub-navigation as tabs on the area page; overview pages at most 1,240 px wide including the page padding, about 1,180 px of content; lists and tables use the available width up to 1,440 px.
- **Size follows the input:** a coarse pointer gets 48 px controls and touch-sized rows at any width.
- **Reflow and long text:** pages reflow at 320 px without horizontal page scrolling (WCAG 2.2 1.4.10), labels wrap rather than truncate, and the English renderings of sign-in and the shell were checked for length.

## 8. Lint findings and dispositions

`dpai design lint` lints the project's design system, which the page-scoped prototypes do not change.

| Run | Result | Disposition |
| --- | --- | --- |
| The reference, 2026-10-04 16:09 WIB | 0 errors, 18 warnings, 1 info — the same count P7 recorded | — |
| 10 × broken-ref: `borderColor` and `borderWidth` are not recognized component sub-tokens | A limitation of the tool's design format | Not applicable to these draft tokens, which name borders as roles; no change to the reference, which is not modified |
| 1 × contrast-ratio: ink on a transparent background at 1.06:1 (the secondary button) | A false positive: the transparent background resolves to the surface (19.76:1) or the canvas (18.39:1), measured | No action |
| 7 × orphaned tokens: primary-strong, primary-quiet, text-muted, warning, on-warning, danger, on-danger | Defined in the reference but unused by its own recipes | Informational; the draft roles use them with measured contrast |
| 1 × info: token summary | — | — |
| After the p7r- pages were authored | the result is recorded in the gate record's [Prototypes](evidence/P7_REBASELINE_GATE.md#prototypes) section | — |

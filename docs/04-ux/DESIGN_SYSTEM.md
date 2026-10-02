# Design system

Status: APPROVED | Updated: 2026-10-02 | Owner: Planning

Approval: [APPR-008](../00-governance/DECISION_LOG.md#appr-008--p7-ux-information-architecture-and-design-system-approved), explicit conditional Owner approval on 2026-10-01 (DIR-033) of this document as committed in the P7 finalization checkpoint; the approved file hash, the verified conditions and the exclusions are recorded there. Approval changes lifecycle only, not implementation authorization.

Authority: P7 — UX, Information Architecture & Design System, authorized by the Owner's fast-track directive [DIR-033](../00-governance/DECISION_LOG.md#dir-033-obs-012-and-tech-022--p7-authorization-entry-baseline-and-ux-documentation) (twenty-ninth source record). This document owns **visual rules**: tokens, typography, spacing, density, layout and breakpoints, iconography, motion, radius and elevation, the reusable patterns, the composition of shadcn/ui primitives into feature components, the status system, the state presentations, the domain presentations, keyboard and scanner conventions, accessibility, copy and formatting, print views and the anti AI-slop mapping. Navigation truth is owned by [INFORMATION_ARCHITECTURE](INFORMATION_ARCHITECTURE.md), interaction truth by [ADMIN_FLOW](ADMIN_FLOW.md), phase evidence by [P7_QUALITY_GATE](P7_QUALITY_GATE.md).

**Anti-duplication contract:** business meaning stays in [V1_SCOPE](../01-product/V1_SCOPE.md), [DOMAIN_MODEL](../02-domain/DOMAIN_MODEL.md), [BUSINESS_RULES](../02-domain/BUSINESS_RULES.md) and [WORKFLOWS](../02-domain/WORKFLOWS.md); authorization in [PERMISSIONS_MATRIX](../02-domain/PERMISSIONS_MATRIX.md) and [SECURITY](../03-architecture/SECURITY.md); mechanisms in [CONCURRENCY_IDEMPOTENCY](../03-architecture/CONCURRENCY_IDEMPOTENCY.md) and [PERFORMANCE](../03-architecture/PERFORMANCE.md). A pattern here says how something looks and behaves on the screen; it never decides who may see or do it, and never changes what a state, figure or document means.

**Boundary:** NON-EXECUTABLE specification. Token tables, anatomy lists and pattern rules are design statements, not code: no CSS, Tailwind configuration, component source, image or font file is created. shadcn/ui is the implementation foundation (OB §20); the build units of P11 implement these rules ([ADMIN_FLOW §16](ADMIN_FLOW.md#16-handoff-obligations)).

## 1. Conventions

- **Identifier families defined here:** UXV (derived visual requirements), D-DS (design-system decisions), PT (patterns), KB (keyboard shortcuts), SLOP (anti AI-slop prohibitions and their rules). GL, SCR, UXN and D-IA are defined in INFORMATION_ARCHITECTURE; UXI, IP, J, MSG, UXS and UXH in ADMIN_FLOW.
- **Must** and **never** are normative for build units; a value given as "starting value" may be tuned by P8 measurement without changing the rule.
- **Phone** means a viewport narrower than 768 px, whatever the input; **desktop** 1024 px and wider; the band between them is §6's tablet rule. Bands are chosen by width alone; target size follows the input (§6).
- Colours are given in sRGB hex with their contrast ratio against the surface they are used on, computed with the WCAG 2.2 formula.
- **Examples** use a fictional group — CV Arunika Jaya (code ARJ), PT Bentara Niaga (BTN) and CV Cakra Persada (CKP) — and no real company, person or account. Document numbers in examples are illustrative: a number's format is its company's numbering scheme, whose exact formats are validated under GAP-019 (DATABASE `numbering_schemes`).

## 2. Foundation and source evidence

| Foundation | Use | Evidence read 2026-10-02 (versions are evidence, not pins — DEP-07) |
| --- | --- | --- |
| **shadcn/ui** | the primitive component system (OB §20; REF-054): copied into the codebase, owned and adapted there; primitives stay free of business logic (§10) | ui.shadcn.com documentation as of 2026-10: primitives Sidebar, Sheet, Drawer, Dialog, Alert Dialog, Command, Combobox, Data Table (a guide built on TanStack Table), Table, Tabs, Badge, Toast (and Sonner on the Radix path), Skeleton, Spinner, Empty, Breadcrumb, Field, Input, Input Group, Select, Native Select, Checkbox, Radio Group, Switch, Textarea, Calendar, Date Picker, Popover, Dropdown Menu, Tooltip, Item, Kbd, Separator, Scroll Area, Alert; new projects default to Base UI primitives and Radix remains supported; theming through CSS variables (`--background`, `--foreground`, `--primary`, `--muted`, `--destructive`, `--border`, `--input`, `--ring`, `--radius`, the sidebar tokens) exposed to Tailwind v4 with `@theme inline`; MIT licence (repository licence file) |
| **Tailwind CSS** | utility layer behind shadcn/ui | tailwindcss.com v4.3: theme variables through `@theme`; default breakpoints 40, 48, 64, 80, 96 rem; container queries; `motion-reduce`, `print`, `forced-colors`, `pointer-coarse` variants |
| **Inter** | the one typeface — SIL Open Font License, no cost; self-hosted with the application, never from a third-party font service (SECURITY WS-08: `font-src 'self'`) | the typeface's own licence; features `tnum` (tabular figures) and `zero` (slashed zero) used for numbers and codes |
| **Lucide** | the icon set shadcn/ui uses — ISC licence, no cost; inline SVG components, no icon font | the set's own licence |
| **WCAG 2.2** | the accessibility target at level AA (§15) | W3C Recommendation, 12 December 2024 edition |

No paid font, icon set, component licence or design service is adopted; any later one is an Owner decision (V1_SCOPE cost boundary).

## 3. Derived visual requirements

| ID | Requirement | Repository source | Home |
| --- | --- | --- | --- |
| UXV-01 | shadcn/ui primitives are the foundation; primitives stay separate from feature components, and business logic never enters a primitive | OB §20; V1_SCOPE P7 handoff requirements; ENGINEERING_PRINCIPLES | §2, §10 |
| UXV-02 | Calm, premium, professional and operationally dense; function over decoration; priorities clarity → task speed → error prevention → readability → consistency → responsive usability → aesthetics | V1_SCOPE language and experience contract; P1 directive Visual Direction; REF-055 | §4, §5 |
| UXV-03 | Every prohibition of the anti AI-slop constitution and of the P1 directive's list is prevented by a rule | OB §21; P1 directive Anti AI-Slop | §18 |
| UXV-04 | No social-media interaction patterns: feeds, reactions, likes, streaks, confetti, gamification, chat-bubble decoration | DIR-033 §10-M | §18 |
| UXV-05 | Typography, spacing, radius, density, icons, status system and design tokens are defined | V1_SCOPE P7 handoff requirements; P1 directive P7 future requirement | §5, §7, §8, §12 |
| UXV-06 | Responsive layout strategy, mobile table alternatives, a desktop data-table standard, responsive forms, dialog versus sheet behaviour, touch targets | V1_SCOPE P7 handoff requirements; OB §22, §23 | §6, §9 |
| UXV-07 | Keyboard interaction and barcode interaction | V1_SCOPE P7 handoff requirements; SH-02; AC-14 | §13 |
| UXV-08 | Status is never conveyed by colour alone and stays legible in greyscale | OB §22; WCAG 1.4.1 | §12 |
| UXV-09 | WCAG 2.2 AA as the design target: keyboard navigation, visible focus, semantic labels, contrast, dialog focus management, reduced motion, outcome announcements | OB §22; DIR-033 §10-N | §15 |
| UXV-10 | Targets at least 24 by 24 CSS px everywhere and 44 px wherever the pointer is coarse | WCAG 2.5.8; V1_SCOPE (touch targets) | §6, §15 |
| UXV-11 | Phone patterns are designed separately; no essential action or datum exists only in a shrunken desktop table | V1_SCOPE language and experience contract; AC-14; OB §22 | §6, §9 |
| UXV-12 | Rupiah, Indonesian separators, dates, times and WIB, exact quantities with units, codes and identifiers are formatted one way | DIR-033 §10-M; ARCHITECTURE §10; DATABASE §17; BR-DT-06 | §16 |
| UXV-13 | Financial presentation keeps the five concepts — sales value, active receivable, Cash-In, cost/HPP, Cash-Out — apart | DIR-011; V1_SCOPE financial concepts; AC-10; AC-11 | §14 |
| UXV-14 | Inventory presentation shows ON HAND, RESERVED, UNUSABLE and AVAILABLE, the relation marker and pending unattributed losses as their own line | DIR-011; BR-INV-01; PJ-01; PJ-04; SF-UNATTRIBUTED | §14 |
| UXV-15 | Document preview: system renditions inline, uploads only as attachments | FL-07; FL-04; WF-DOC-01 | §14 |
| UXV-16 | Lists show thumbnails only, one page of them, lazily below the fold | PF-31; PF-32 | §9 |
| UXV-17 | Loading, skeleton, empty, error, denied, stale, CONFLICT, PENDING and FAILED states | OB §23; V1_SCOPE P7 handoff requirements; HO-01 | §12 |
| UXV-18 | Toast and feedback; the audit and activity timeline | OB §23 | §9 |
| UXV-19 | A print view is identical to its page | DP-19 | §17 |
| UXV-20 | Destructive actions, their confirmation and the correction and reversal patterns | OB §23; WORKFLOWS §8 | §9 |
| UXV-21 | Motion only for interaction feedback: subtle, fast, purposeful, and none under reduced motion | OB §22 | §8 |
| UXV-22 | Indonesian copy: tone, capitalization and punctuation conventions | V1_SCOPE language and experience contract; DIR-033 §10-A | §16 |

## 4. Design decisions

Each decision is Level 1 under DIR-033 §13. "Exploration" names the DesainPakeAI brief whose alternatives were compared ([P7_QUALITY_GATE](P7_QUALITY_GATE.md#desainpakai-exploration)); a decision without one follows from repository rules alone.

| ID | Decision | Alternatives rejected | Grounds · exploration |
| --- | --- | --- | --- |
| D-DS-01 | The visual foundation is "Tinta": white surfaces, ink neutrals, one deep-blue accent, five tones bound to meanings, flat colour, hairline rules, 4 px and 6 px radii | "Kertas": a second typeface and looser spacing that cost density; "Batu": the framework's default indigo with tinted pills and alert fills — the generic-template look of SLOP-37; the tool's starter design: an acid-lime accent at 1.23:1 on white, display type up to 56 px and a chat composer, with 19 findings from `dpai design lint` | UXV-02, UXV-03, UXV-08; WCAG 1.4.3, 1.4.11 · DPB-01 |
| D-DS-02 | One typeface, Inter, self-hosted; seven text styles from 12 to 20 px; tabular figures and slashed zero for numbers and codes; 16 px in touch inputs | a display or serif face beside it: one more font to load for no task value; the system font stack: figures and widths differ between devices, which breaks column alignment | WS-08 (`font-src 'self'`); SLOP-26; UXV-12 · DPB-01 |
| D-DS-03 | One density: compact with a fine pointer, touch-sized with a coarse pointer; no density setting | a user-selectable density: more states to design and test for no V1 need (PN-7); one size for both: slow desktop work or touch targets below 44 px | PN-7; UXV-10; WCAG 2.5.8 · DPB-01, DPB-17 |
| D-DS-04 | A state is a bordered rectangular badge: label, icon shape and tone; never a filled pill, dot or row tint alone | tinted pills ("Batu"): colour-dependent and pill-shaped (SLOP-05); text alone: slower to scan in dense tables | UXV-08; WCAG 1.4.1; UXS-42 · DPB-01, DPB-16 |
| D-DS-05 | No chart on any V1 screen: every figure is a CALC value or a QS signal shown as text and tables | charts on Beranda and reports: no V1 decision needs a trend picture, and decorative analytics is SLOP-34 | V1_SCOPE CAP-11, CAP-17; SLOP-18, SLOP-34 · DPB-03, DPB-13 |
| D-DS-06 | An outcome message is part of the sticky action region of the form or dialog that sent the command — directly above its buttons, the region growing to hold it — so the answer is in view whenever the buttons are; for CONFLICT and FAILED focus moves to it (PT-14) | a banner at the top of the form: out of view at the bottom of a long form and on a phone; a toast: it disappears and cannot hold a decision (WCAG 2.2.1) | HO-01; UXV-17; WCAG 4.1.3 · DPB-16 |
| D-DS-07 | Multi-line entry on desktop is a table of form rows — Tab moves between cells, Enter in the last cell opens the next row, one empty row always at the end, line details that do not fit open under their row; on a phone each line is a row that opens an editing sheet (PT-10) | read-only rows edited one at a time on desktop: one more keystroke per line for thirty lines (UXS-35); a summary column beside the lines: every line wraps to two rows; stepped sections: more clicks and step badges that read as progress; inline cells on a phone: they overflow 390 px | UXV-06, UXV-07; V1_SCOPE (no spreadsheet clone) · DPB-07, DPB-17 |
| D-DS-08 | On a phone the scan field of a scan step is fixed at the bottom, above the action bar (PT-20) | at the top of the screen: out of thumb reach while the line list scrolls | GAP-020; UXV-10 · DPB-09 |
| D-DS-09 | The capability-by-company preview marks each cell *Dapat*, *Baru*, *Dicabut* or empty, and marks the capabilities a removed prerequisite disables "Nonaktif — prasyarat dicabut", keeping their grants (PT-30; RG-06) | a plain yes/no matrix: it does not show what the save changes | H7-06; GAP-032 · DPB-14 |

## 5. Tokens

NON-EXECUTABLE. The left column names the shadcn/ui theme variable a build unit sets, so that every primitive inherits the value; the tokens below the line are this design system's own additions. shadcn/ui's `--accent` is the hover surface of menus and lists, not this design's accent colour, which is `--primary`.

### 5.1 Colour

The "Tinta" foundation of D-DS-01.

| Variable | Role | Value | Contrast |
| --- | --- | --- | --- |
| `--background` | page canvas and work surface | #FFFFFF | — |
| `--foreground` | ink — text and icons | #14171C | 17.96:1 on #FFFFFF |
| `--card` · `--card-foreground` | the rare bordered panel (§9 PT-12) | #FFFFFF · #14171C | as above |
| `--popover` · `--popover-foreground` | menus, popovers, dialogs, sheets | #FFFFFF · #14171C | as above |
| `--primary` · `--primary-foreground` | the accent: the primary action of a region and the current navigation entry's rule; through `--ring`, focus. Never links or selection | #1E3A8A · #FFFFFF | 10.36:1 on #FFFFFF; white on it 10.36:1 |
| `--secondary` · `--secondary-foreground` | secondary surfaces | #F4F5F7 · #14171C | 16.46:1 |
| `--muted` · `--muted-foreground` | table header, read-only fields, quiet text | #F4F5F7 · #5A616B | 6.26:1 on #FFFFFF; 5.73:1 on #F4F5F7 |
| `--accent` · `--accent-foreground` | hover and highlighted rows in menus and lists | #F4F5F7 · #14171C | 16.46:1 |
| `--destructive` | destructive buttons and the danger tone | #B3261E | 6.54:1 on #FFFFFF; white on it 6.54:1 |
| `--border` | decorative dividers and rules — never the only boundary of a control | #E3E5E9 | 1.26:1 (decorative) |
| `--input` | the boundary of inputs, selects and checkboxes | #868D97 | 3.35:1 on #FFFFFF (WCAG 1.4.11) |
| `--ring` | focus ring | #1E3A8A | 10.36:1 |
| `--sidebar` and its foregrounds | the navigation surface; the current entry's rule is `--primary` and its surface `--muted` ([§9 PT-01](#9-patterns)) | #FFFFFF · #14171C | 17.96:1 on #FFFFFF |
| `--chart-1`–`--chart-5` | not used in V1: no chart is part of any screen (§18 SLOP-34); a later chart needs a decision recorded here | — | — |
| `--tone-info` | information, active, in progress | #0B5E7A | 7.25:1 |
| `--tone-success` | completed, satisfied, paid, saved | #17663A | 7.00:1 |
| `--tone-warning` | needs attention, exceptional, unknown outcome | #8A4B00 | 6.80:1 |
| `--tone-danger` | refused, failed | #B3261E | 6.54:1 |
| `--tone-neutral` | draft, ended, inactive | #4B525C | 7.89:1 |
| `--accent-hover` | hover of `--primary` | #172E6E | — |
| `--danger-hover` | hover of `--destructive` | #96201A | — |

Links are ink with a 1 px underline in `--input`. A selected tab is ink at 600 weight over a 2 px ink rule; a selected segment of a segmented control is ink at 600 weight inside a 1 px ink boundary; a selected option in a list or menu has the `--accent` surface and a check. Tones colour only icons, badge labels and 1 px borders — a sentence is never set in a tone; no surface, row or message is filled with a tone (§12). Every pair above that carries text meets 4.5:1, and every boundary that identifies a control meets 3:1. P8 measures the built screens (UXH-05).

### 5.2 Typography

| Token | Size / line height / weight | Use |
| --- | --- | --- |
| `title` | 20 / 28 / 600 | the one page heading (h1) |
| `section` | 16 / 24 / 600 | h2 — page sections, Beranda groups, dialog and sheet titles |
| `subheading` | 14 / 20 / 600 | h3 — form sections, field groups, the name of an open line or row |
| `body` | 14 / 20 / 400 | text, form fields on desktop, list primary lines |
| `data` | 13 / 20 / 400 | table cells and dense rows, with tabular figures |
| `label` | 12 / 16 / 500 | field labels, column headers, badges, captions, shortcut hints (Kbd) |
| `input-touch` | 16 / 24 / 400 | text inside inputs wherever the pointer is coarse, so the browser does not zoom on focus |

One family, Inter, at 400, 500 and 600; the fallback is the system UI sans-serif. Numbers, amounts, quantities, dates and codes use tabular figures and the slashed zero (`font-variant-numeric: tabular-nums slashed-zero`). No size above 20 px exists in the application (§18 SLOP-26).

### 5.3 Spacing, sizing and density

| Token | Value | Use |
| --- | --- | --- |
| Spacing scale | 4 · 8 · 12 · 16 · 24 · 32 · 48 px | every gap between elements, every margin and the padding of regions; inside a fixed-height control, badge or row the padding follows from its height and its text |
| Page padding | 24 px desktop · 16 px phone | the content area's inner edge |
| Group gap · inside a group | 24 px · 8–12 px | the space between groups is at least twice the space within them |
| Control height | 32 px with a fine pointer · 44 px with a coarse pointer, at any width (§6) | buttons, inputs, selects, the business-date control |
| Table row | 36 px; a two-line cell grows the row | PT-05 |
| Phone list row | at least 56 px | PT-06 |
| Icon | 16 px, stroke 1.5 px; 14 px inside badges and inline marks; 20 px in the phone navigation | §8 |
| Form width | at most 720 px for a one-column form; two columns up to 1,040 px | PT-09 |
| Content width | fluid; at most 1,440 px on very wide screens, centred | §6 |

**One density.** Controls are compact with a fine pointer and touch-sized with a coarse pointer (§6); there is no user-selectable density mode (D-DS-03; PN-7).

### 5.4 Radius, elevation, layers and motion

| Token | Value | Use |
| --- | --- | --- |
| `--radius` | 4 px | controls, badges, inputs, buttons, the rare panel; a segmented control has one 4 px outer boundary with its segments separated by 1 px rules, never inset shapes |
| Overlay radius | 6 px | dialogs, popovers, menus, sheets, toasts |
| Elevation | none at rest; overlays only: `0 8px 24px rgba(20,23,28,.12)`; the one other shadow is the edge cue of a table that scrolls inside its frame (PT-05, PT-10) | §18 SLOP-14 |
| `--scrim` | `rgba(20,23,28,.32)` | behind a dialog or sheet; the re-authentication dialog's layer is opaque instead (PT-28) |
| Layers | content · sticky headers and action bars · navigation · popovers and menus · sheets · dialogs · toasts | a higher layer never hides the focused element (WCAG 2.4.11) |
| Durations | 100 ms hover and press · 140 ms menus, popovers and dialogs · 200 ms sheets | §8 |
| Easing | `cubic-bezier(0.2, 0, 0, 1)` entering; ease-in leaving | — |

## 6. Layout, grid and breakpoints

| Band | Width | Layout |
| --- | --- | --- |
| Phone | below 768 px | phone navigation ([§9 PT-02](#9-patterns)); one column; dense list rows instead of tables; full-height sheets for forms and filters; the primary action in a bottom action bar; 16 px page padding |
| Tablet | 768–1023 px | desktop patterns with the navigation collapsed to icons; forms in one column; tables keep their columns and scroll horizontally only inside their own frame, with a visible edge cue |
| Desktop | 1024 px and wider | the full shell; two-column forms; data tables; side panels and sheets beside the content; 24 px page padding |
| Wide | 1440 px and wider | content centred at most 1,440 px wide; tables may use the width, text measures stay as in §5.3 |

Breakpoints follow where content stops fitting; the Tailwind defaults at 768 and 1024 px coincide with these bands, and components with their own breakpoints use container queries. Page reflow at 320 CSS px works without horizontal scrolling of the page (WCAG 1.4.10); only a data table may scroll inside its own frame, and its first column stays visible.

**Width sets the layout, the input sets the size.** A band is chosen by width alone, so a desktop browser narrowed or zoomed to 200–400 % gets the phone patterns. A coarse pointer (`pointer: coarse`) gets 44 px controls and targets and 56 px list rows at every width, and on a scan step the phone layout of that step — the scan dock fixed at the bottom and the bottom action bar (PT-20; D-DS-08) — so a touch tablet or a landscape phone running a `[floor]` task keeps them.

**Not a shrunk desktop.** Every screen of [INFORMATION_ARCHITECTURE §8](INFORMATION_ARCHITECTURE.md#8-screen-inventory) names its phone pattern; a desktop table never appears on a phone as a scaled-down table (UXV-11).

## 7. Typography rules

- One typeface and seven styles (D-DS-02). One `title` per page; section headings in `section`; no heading above 20 px, no display sizes, no hero headings.
- Body text measure at most 72 characters; table cells wrap within their column rather than truncate, except a single-line identifier that truncates with the full value in a tooltip and in the row's detail.
- Long Indonesian institution names wrap to two lines in a list row and are never ellipsized in a form or detail.
- Emphasis by weight (600) or by the ink and muted colours; never by colour hue, italics for data, or underline except for links.
- Numbers right-aligned in tables, with tabular figures, so digits align by place value.

## 8. Iconography, motion, radius and elevation

- **Icons** are Lucide line icons at 16 px with 1.5 px strokes, inline. An icon is used only where it carries meaning a word alone would not: a status shape (§12), a navigation item that collapses to icons, a control whose meaning is universal (close, search, calendar, menu, download, scan) — always with an accessible name and, outside the navigation, beside a text label. A button with a text label has no icon unless the icon distinguishes it from a neighbour; no icon inside every label; one icon size per context (§18 SLOP-23, SLOP-24, SLOP-32).
- **Motion** is feedback only: a pressed button, a menu or dialog appearing (opacity and at most 4 px of movement), a sheet sliding in, a row being added. No decorative, looping, parallax or attention motion; the only repeating animation is a progress indicator, and under `prefers-reduced-motion: reduce` it becomes a still mark with text and every movement is removed.
- **Radius** is 4 px or 6 px (§5.4); nothing is rounded into a pill; the only circles are the shapes inside the status icons.
- **Elevation** exists only for overlays; surfaces at rest are separated by space and hairline rules, not shadows or nested panels.

## 9. Patterns

Every screen is assembled from these patterns; a build unit that needs something they do not cover proposes an addition here instead of inventing a page-level convention (OB §23; UXH-11).

### 9.1 Shell, navigation and page structure

| ID | Pattern | Desktop | Phone | Rules |
| --- | --- | --- | --- | --- |
| PT-01 | **Application shell** | a 232 px sidebar in business-flow order — groups open in place, one level — that collapses to a 56 px icon rail; a 48 px top bar with the search field, "Pintasan" and the account menu; a notice strip under the top bar only while a notice is due; the content to the right ([INFORMATION_ARCHITECTURE §6.1](INFORMATION_ARCHITECTURE.md#61-desktop-and-tablet)). The current entry: 600 weight, a 2 px accent rule on its left edge and the `--muted` surface | — | The shell never shows a business figure, a company's data or a count across companies; the session notices of [ADMIN_FLOW §6](ADMIN_FLOW.md#6-session-expiry-re-authentication-and-unsent-input) appear in its notice area |
| PT-02 | **Phone navigation** | — | a 48 px top bar with a back arrow, the page title and the account button; a 56 px bottom bar (plus the device's safe area) with five destinations, each a 20 px icon above a 12 px label; *Lainnya* as a full-height sheet of grouped rows ([INFORMATION_ARCHITECTURE §6.2](INFORMATION_ARCHITECTURE.md#62-phone)); the bar hidden during focused tasks | Designed for the thumb: the main destinations within reach, every target at least 44 px, the active destination marked by 600 weight and the accent rule, never by colour alone |
| PT-03 | **Page header and page actions** | back link or breadcrumb (at most three levels) · title · one line of record facts — number, company label (PT-04), status badge (PT-13) · one primary action on the right · secondary actions in "Tindakan Lain" (DropdownMenu) · the business-date control (PT-25) on command forms | back arrow and title in the top bar; the facts line wraps under the title; the primary action in a bottom action bar fixed above the navigation; secondary actions in "Tindakan Lain" | At most one primary action per page region; while a command form with its own action bar is in view, the header's action is shown as secondary, so one primary action is visible at a time. No description paragraph unless it carries a needed fact; no hero header, greeting or illustration |
| PT-04 | **Company label, working filter and relation marker** | company code before the record number in tables and in the facts line; the filter "Tampilkan: Semua perusahaan" in the list header — a Popover with a checklist of the companies in scope | the code as the first token of a list row's first line; the filter inside the filter sheet | The rules of [INFORMATION_ARCHITECTURE §7](INFORMATION_ARCHITECTURE.md#7-company-context-model); the relation marker is muted plain text, never a link, colour, logo or count |
| PT-31 | **Derived part strip** (D-IA-09) | one row above a record's parts: each part's name in `label` style, its part state as the 14 px shape followed by the state's label (§12.1), and one derived fact in `data` style; at most eight parts, wrapping to a second row rather than scrolling | not used — the phone's part list carries the same fact in each row | Derived each time from the parts' own figures; never a stored stage, a progress bar or a percentage (§18 SLOP-44) |
| PT-12 | **Detail layout** | PT-03 · a facts summary as a definition list in two to four columns · the record's parts · the timeline (PT-18) | PT-03 · facts stacked · parts as a list of drill-in rows or a segmented switcher · timeline | A bordered panel (4 px radius, 1 px `--border`) only where a group needs its own boundary — never a panel inside a panel, never every section in a panel |

### 9.2 Lists, tables and filters

| ID | Pattern | Desktop | Phone | Rules |
| --- | --- | --- | --- | --- |
| PT-05 | **Data table** — the desktop standard | sticky header row in `label` type, sentence case; 36 px rows separated by hairline rules, no zebra stripes; the first column is the record's number as a link; numbers right-aligned with tabular figures; a status column of badges; secondary row actions in a "⋯" menu; the hovered and the focused row tinted `--muted`, the focused row also outlined; a sort arrow and `aria-sort` only on keys the screen inventory registers | not used — PT-06 | Row click opens the record; keyboard: arrow keys move between rows, Enter opens. No column resizing, reordering, inline editing or selection checkboxes in V1, since no list command acts on several records. At tablet widths the table scrolls inside its own frame with its first column fixed and an edge shadow as the overflow cue. No total row, page numbers or "x dari y" (PF-15) |
| PT-06 | **Dense list rows** — the phone alternative to a table | — | rows of at least 56 px: line 1 the identifier (company code first when several companies are in scope) with the key figure right-aligned; line 2 muted secondary facts with the status badge right-aligned; hairline separators; the whole row is the target | Not a card per record and no boxed sections; at most two lines, the rest on the record |
| PT-07 | **Filters** | a filter bar above the table: the list's search field, at most four filter controls, the company filter, "Atur Ulang"; applied filters as removable tags under the bar | "Filter" with the number applied opens a full-height sheet with the same controls and "Terapkan"; a summary line of applied filters under the header | Only the keys the screen inventory names; values in the address (INFORMATION_ARCHITECTURE §11); a filter in PF-42's bounded form shows the partial-page note of PT-08 |
| PT-08 | **List continuation and list notices** | "Muat Lagi" below the last row; "Semua sudah ditampilkan." when nothing is left; the cut notice (ADMIN_FLOW MSG-23); a bounded filter's page that came back short: "Sebagian data sudah diperiksa. Muat lagi untuk melanjutkan."; a cursor that fails its check: MSG-26 | a full-width "Muat Lagi" button; the same notices | Never a total, page number or page-size selector; the page size is 25 (PF-17) |
| PT-27 | **Search and type-ahead** (Command, Combobox) | results grouped by type, an exact code match first with the mark "Kode Cocok" (§12.1), at most 20 rows, keyboard navigation, the cut notice; in a form picker "Tidak ditemukan." with "Buat {Objek}" — "Buat Klien" — where the account may create | full-screen search; the same groups | The rules of ADMIN_FLOW IP-17; results are chosen with KB-11; no recent-searches history is stored in the browser |

### 9.3 Forms and entry

| ID | Pattern | Desktop | Phone | Rules |
| --- | --- | --- | --- | --- |
| PT-09 | **Form layout and validation** | labels above fields; "(wajib)" after the label of a required field; helper text under the field only where it prevents an error; related fields in pairs in two columns, at most 1,040 px wide; sections under `subheading` titles, not in panels; a sticky action bar at the bottom of a long form with the primary action and "Batal" | one column; the action bar fixed at the bottom; inputs at 16 px text; the keyboard type matches the field (numeric for quantities and amounts) | Errors per ADMIN_FLOW IP-14: under the field in ink after the danger tone's icon, the field's boundary in the danger tone, `aria-invalid` and `aria-describedby`; the input kept. Placeholders show a format, never replace a label. Leaving with unsent input asks first (MSG-35); a draft form saves with KB-09 |
| PT-10 | **Multi-line entry** | a table of form rows: number · Produk/Jasa (PT-27) · Jumlah · Satuan · Harga · Pajak · Subtotal *Perkiraan* · row actions; one empty row always at the end; at tablet widths the table scrolls inside its own frame with the line number and product fixed on the left, the row actions fixed on the right and an edge cue, as PT-05; the keys KB-05–KB-08; details of a line that do not fit its row — tax, project item, "Kirim langsung ke klien" — open under that row; the keyboard flow of §13 (D-DS-07) | each line a dense row that opens a full-height editing sheet; "Tambah Baris" at the end of the list | A form laid out as rows, not a spreadsheet: no free cell grid, formulas or block paste. Totals are labelled *Perkiraan* until the server's answer (ADMIN_FLOW IP-13) |
| PT-25 | **Business date control** "Tanggal transaksi" | one compact line in the form header: "Tanggal transaksi: Hari ini, 02 Okt 2026 · Ubah"; a chosen date "28 Sep 2026 — dipilih" with the mark "Tanggal Mundur" and, on a numbered document, "Nomor mengikuti periode Sep 2026" | the same line under the title | A date picker (Popover with Calendar) that offers no date after today; the rules of ADMIN_FLOW IP-07 |
| PT-20 | **Scan field** | an input group with a scan icon, the field "Pindai atau ketik kode", the state "Siap Memindai" as plain muted text beside the scan icon — not a badge —, "Cari Produk" beside it, and the last result line below — the matched line and its running count | the same, 56 px high, fixed at the bottom of the scan step above the action bar, the line list scrolling above it (D-DS-08) | The result line is a polite live region and scan errors are announced assertively, with focus kept in the field (§15); the rules of ADMIN_FLOW IP-15 and §13 |
| PT-29 | **Derived readiness list** | rows: item · *Lengkap* or *Belum* (PT-13) · what is missing · link | the same as dense rows | Derived from state each time it is shown; no stored progress, fraction such as "2 dari 10", progress bar, percentage or checkmark animation (§18 SLOP-44); missing items first |
| PT-30 | **Capability-by-company preview** | a matrix: capabilities grouped by area as rows, the account's granted companies as columns, each cell *Dapat* (kept), *Baru* (added), *Dicabut* (removed) or empty; prerequisites stated per capability — removing one marks the capabilities that need it "Nonaktif — prasyarat dicabut": their grants are kept and take effect again when the prerequisite is granted again (RG-06), and they are revoked only when the Owner removes them; Owner-only capabilities absent; a summary sentence per company | read-only list per company with the same marks | Shown as "Pratinjau Akses" before "Simpan Akses", whose confirmation lists each change and the companies it applies to (H7-06; D-DS-09); the server decides what the grants allow |

### 9.4 Actions, overlays and feedback

| ID | Pattern | Desktop | Phone | Rules |
| --- | --- | --- | --- | --- |
| PT-11 | **Buttons and actions** | *primary* — filled `--primary`, white text; *secondary* — 1 px `--input` boundary, ink text; *quiet* — text only, hover surface `--muted`; *destructive* — filled `--destructive`, used only as the confirming button of a destructive dialog; the trigger of a destructive or correcting action on a page is a secondary button with danger-tone text; *busy* — a spinner and "Menyimpan…", disabled while the request runs; *unavailable* — disabled, with the reason as text beside it | the same, 44 px high with a coarse pointer; the primary action full-width in the bottom action bar | Labels begin with the verb, Title Case (§16); a correction entry names its situation after the verb (ADMIN_FLOW §11); never "Ya", "OK" or "Submit". A shortcut hint (Kbd) may follow a label on desktop. One primary per region. Disabling is a convenience only (HO-03) |
| PT-16 | **Dialogs and sheets** | *AlertDialog* for short confirmations — irreversible, destructive, step-up, re-authentication; *Dialog* for a form of at most five fields; *Sheet* from the right, 480–640 px wide, for longer forms, filters and record previews beside a list | forms and filters as full-height sheets; irreversible, destructive and correcting confirmations, the step-up and the re-authentication as full-height sheets (PT-26, PT-28); other short confirmations — leaving with unsent input, removing a line — as bottom sheets with the confirming button at the bottom | Focus moves into the overlay — to its first input or, when it has none, to "Kembali"; never to a confirming button —, the background is inert, Escape (KB-04) and the close control dismiss it — except the re-authentication dialog, which only "Keluar" ends — and focus returns to the trigger. Never two dialogs at once, except that a sheet may open one confirmation dialog and that the idle, re-authentication and step-up dialogs open above any dialog or sheet, are never covered, and return focus to the element focused before them. A command that needs both a confirmation (PT-26) and the step-up shows the confirmation first and the step-up when it is sent (ADMIN_FLOW IP-10) |
| PT-26 | **Confirmation of an irreversible or correcting command** | AlertDialog: title "{Kata kerja} {objek}?"; the consequence and how a mistake is corrected (MSG-39); the affected items; the reason field where required (MSG-40); for numbering the type's numbering facts expanded, the statements and the required tick of ADMIN_FLOW §12.2; the confirming button with the action's verb, "Kembali" beside it | a full-height sheet with the confirming button at the bottom | The payload's formats are checked before the dialog opens, and it opens with focus on its first input or, when it has none, on "Kembali"; KB-10 opens the dialog and only the dialog's button sends the command (ADMIN_FLOW IP-08, IP-09) |
| PT-28 | **Step-up and re-authentication dialog** | AlertDialog naming the account; for re-authentication an e-mail field and a password field, for the step-up one password field — with `autocomplete`, show-password, paste and password managers allowed, focus in the first field; "Lanjutkan"; step-up adds "Batal", re-authentication "Keluar"; for re-authentication the backdrop is opaque so the page content is not readable | the same as a full-height sheet | ADMIN_FLOW IP-10 and §6 |
| PT-14 | **Inline message** — outcomes, notices and errors of a part | one anatomy for every inline message: white, a 1 px `--border` boundary, the tone's icon, the label in 600 weight, the text in ink, and at most one action button | the same, full width | An outcome message is part of the sticky action region of the form or dialog that sent the command: it appears directly above the buttons inside that region, which grows to hold it — up to a third of the viewport, scrolling inside beyond that — so the answer is in view whenever the buttons are (D-DS-06); announced through a live region — polite for COMMITTED and PENDING, assertive for REJECTED, CONFLICT and FAILED — without moving focus, except that a CONFLICT or FAILED answer moves focus to its message (§15). Never a filled colour block (§12) |
| PT-15 | **Toast** | bottom right, above other content, 6 px radius, overlay elevation | bottom, above the navigation | Only for a quiet confirmation that needs no action and is also visible on the page, such as "Draf tersimpan." after navigating away; it disappears after five seconds unless focused or hovered. Never for REJECTED, CONFLICT, FAILED, unknown outcomes, warnings or anything that needs a decision |
| PT-18 | **Activity timeline** (*Riwayat*) | entries newest first: when recorded and — when it differs — the business date ("Tanggal transaksi 28 Sep 2026 · dicatat 01 Okt 2026 14.05 WIB"); the acting account's name where the viewer may see it; one sentence of what happened — a routed refusal "ditolak — diteruskan", a count made stale "kedaluwarsa"; the reason; changed fields as "field: lama → baru"; links "Mengoreksi" and "Dikoreksi oleh"; "Sistem" only for an entry no account caused; "Muat Lagi" | the same as stacked entries | Projected per PJ-11: keys the viewer may not see are absent, the other side of a cross-company event is the relation marker. No avatars, reactions, comments or chat bubbles (§18) |
| PT-19 | **Loading, skeleton, empty, error and denied** | a skeleton that mirrors the real layout, shown after 300 ms so fast answers do not flash; a spinner only inside a busy control; *empty*: one sentence and at most one action; a group error: the message, "Muat Ulang Bagian Ini" and the reference code; the not-found and forbidden pages of ADMIN_FLOW IP-21 | the same | No illustrations, mascots or decorative empty states; a search empty state names the term and offers clearing it |

### 9.5 Dashboard, records and media

| ID | Pattern | Desktop | Phone | Rules |
| --- | --- | --- | --- | --- |
| PT-21 | **Signal group and queue row** | a group heading with its bounded per-company counts and "Lihat Semua"; rows: the condition · company code · subject · age · link, in the order the signal's statement returns them; at most five rows per signal, the rest behind "Lihat Semua"; a group of the Owner's Beranda reads the same on phone and desktop (D-IA-07); the group's own skeleton, error and empty sentence | collapsible groups with their counts in the heading; the same rows as PT-06 | Counts are inline text, bounded per company ("20+"); no KPI tiles, charts, trend arrows, sparklines or progress rings (§18 SLOP-18, SLOP-34) |
| PT-17 | **Inventory presentation** | [§14.2](#142-inventory) | the quantity block stacked; lots as PT-06 rows | — |
| PT-24 | **Financial position block** | [§14.1](#141-financial-data) | the block first, stacked | — |
| PT-22 | **Thumbnails and previews** | product thumbnails 40 px in lists only, one page of them, loaded lazily below the fold; a neutral placeholder while a derivative is not ready; the document preview of [§14.3](#143-document-preview) | the same | PF-31, PF-32; an original image only on its record |
| PT-13 | **Status badge** | [§12.1](#121-status-system) | the same | — |
| PT-23 | **Money, quantity and date presentation** | [§16](#16-copy-and-formatting) | the same | — |

## 10. Component composition

shadcn/ui primitives are copied into the codebase and kept generic: they receive values and callbacks, never a capability check, numbering rule, state transition or business wording of their own (OB §20). Feature components compose them and receive the server's decisions as data — what may be shown, which actions are offered, the outcome of a command (AZ-01). The folders follow ARCHITECTURE §10: primitives under `components/ui`, feature components under `features/<module>`, pages under `Pages/<Module>`.

| Feature component | Built from | Business decisions it receives |
| --- | --- | --- |
| Application shell (PT-01, PT-02) | Sidebar, Sheet, Breadcrumb, DropdownMenu, Command, Tooltip | the navigation entries the account's capabilities allow — as hints (H7-03) |
| Page header (PT-03) | Breadcrumb, Button, DropdownMenu, Badge | the record's facts, status and offered actions |
| Company filter and label (PT-04) | Popover, Checkbox, Button | the companies in scope |
| Data table and dense list (PT-05, PT-06, PT-08) | Table (TanStack Table guide for sorting state only), Item, Button, DropdownMenu, Skeleton, Empty | rows, the registered sort keys, the opaque cursor |
| Filters (PT-07) | Popover, Command, Checkbox, Select, Sheet, Badge, Button | the whitelisted filter keys and values |
| Search and pickers (PT-27) | Command, Combobox, Popover, Input | results under the viewer's scope |
| Form field and form layout (PT-09) | Field, Label, Input, Input Group, Select, Native Select, Textarea, Checkbox, Radio Group, Switch | validation messages from the server |
| Line-item editor (PT-10) | Table, Combobox, Input, Input Group, Kbd, Button | product lookup results; server totals after saving |
| Business date control (PT-25) | Popover, Calendar, Button | the server's day (WIB) and the latest allowed date |
| Scan field (PT-20) | Input Group, Spinner, Kbd | exact lookup results |
| Status badge (PT-13) | Badge plus a Lucide icon | the state; its label, tone and icon come from §12.1 |
| Outcome message (PT-14) and toast (PT-15) | Alert, Button; Toast (or Sonner on the Radix path) | the command's answer |
| Confirmation, step-up and re-authentication (PT-26, PT-28) | Alert Dialog, Dialog, Sheet, Field, Checkbox | the consequence text, affected items, required statements |
| Timeline (PT-18) | Item, Separator, Button | projected audit entries |
| Signal group (PT-21) | Item, Collapsible, Skeleton, Empty, Button | bounded per-company counts and rows |
| Position block and inventory block (PT-24, PT-17) | Table or a definition list, Separator | server figures only |
| Readiness list (PT-29) and capability preview (PT-30) | Item, Table, Badge | derived state; the effective grants |
| Preview (PT-22) | Scroll Area, Button, Item | the rendition's address; the upload's file metadata |
| Derived part strip (PT-31) | Item and a Lucide icon per part | the parts' derived figures and states |
| Record parts and segmented switches (PT-12; D-IA-09) | Tabs on desktop; Item rows that drill in on a phone; Toggle Group for two to four parts and for the Owner's *Tampilan* switch (D-IA-07) | the parts the account's capabilities open |
| Dialogs and sheets (PT-16) | Dialog, Alert Dialog, Sheet (side, desktop), Drawer (bottom sheet, phone) | the form's fields and the consequence text |
| Loading, empty, error and denied (PT-19) | Skeleton, Spinner, Empty, Alert, Button | the group's state and its reference code |
| List continuation and list notices (PT-08) | Button ("Muat Lagi"), Alert | the opaque cursor and whether rows remain |

**Primitives not used in V1.** Pagination — page numbers are forbidden (PF-15), lists continue by "Muat Lagi"; Chart (D-DS-05); Progress and Carousel (SLOP-44, SLOP-35); Avatar (no avatars, SLOP-45); Hover Card and Context Menu — information or actions reachable only by hover or right-click; Resizable — no column resizing in V1 (PT-05); Navigation Menu and Menubar — the horizontal menu was rejected (D-IA-01). A build unit that needs one of them changes this section first.

Whether the build uses the Base UI or the Radix variant of the primitives, and Toast or Sonner, is decided with the versions under DEP-07 in P11; the patterns do not depend on it. SECURITY WS-08 leaves open whether a vetted primitive needs inline styles (HO-37); that question stays with P11.

## 11. Responsive pattern matrix

| Desktop | Phone | Why |
| --- | --- | --- |
| Sidebar and top bar (PT-01) | phone navigation (PT-02) | the thumb reaches the main destinations; nothing is hidden behind an unlabeled icon |
| Data table (PT-05) | dense list rows (PT-06) | a table scaled down is unreadable; two lines carry what a row needs |
| Filter bar (PT-07) | filter sheet with an applied-filter summary | the bar does not fit; the summary keeps the state visible |
| Tabs | a segmented control for two to four parts; a drill-in list for more | tabs that scroll sideways hide parts |
| Detail with side panel (PT-12) | stacked sections under a sticky header | one column reads top to bottom |
| Dialog with a form (PT-16) | full-height sheet | the keyboard covers half the screen |
| Short confirmation dialog | bottom sheet with the confirming button at the bottom; irreversible, destructive and correcting confirmations, the step-up and the re-authentication as full-height sheets (PT-16) | reach; room for the consequence and the affected items |
| Page actions in the header (PT-03) | one primary action in the bottom action bar, the rest in "Tindakan Lain" | reach and focus on the next step |
| Two-column form (PT-09) | one column with the action bar fixed at the bottom | reading order |
| Line-item table (PT-10) | line rows that open an editing sheet | entry on a small screen |
| Hover tooltips | none: the information is on the screen or behind a tap | touch has no hover |
| Keyboard shortcuts (§13) | none needed; the scan field and large targets | — |

Touch targets are at least 44 by 44 px wherever the pointer is coarse, at any width (§6), with at least 8 px between them; with a fine pointer no target is smaller than 24 by 24 px (WCAG 2.5.8).

## 12. Status, outcome and state patterns

### 12.1 Status system

Every lifecycle or derived state that a screen shows is a status badge (PT-13; D-DS-04): a 22 px high badge with a 4 px radius, a 1 px border and text in its tone, a 14 px Lucide icon whose **shape** identifies the state within its family, and its label. The label alone is sufficient; the shape is the non-colour cue that keeps two states apart in greyscale and for colour-blind viewers; the tone only reinforces (WCAG 1.4.1; UXS-42). The states and their meanings are those of BUSINESS_RULES §3 and WORKFLOWS; this table only gives them a face.

| Family | State (meaning owner) | Label | Tone | Icon shape (Lucide) |
| --- | --- | --- | --- | --- |
| Project (SM:Project) | DRAFT | Draf | neutral | dashed circle (`circle-dashed`) |
| | ACTIVE | Aktif | info | circle with a dot (`circle-dot`) |
| | COMPLETED_NORMAL | Selesai | success | check (`check`) |
| | COMPLETED_FORCED | Selesai (Paksa) | warning | check with a mark (`badge-alert`) |
| | CANCELLED | Dibatalkan | neutral | slashed circle (`ban`) |
| Quotation revision (SM:Quotation) | DRAFT | Draf | neutral | `circle-dashed` |
| | SENT | Terkirim | info | paper plane (`send`) |
| | APPROVED | Disetujui Klien | success | check in a circle (`circle-check`) |
| | REJECTED | Ditolak Klien | danger | cross in a circle (`circle-x`) |
| | SENT past validity (L-25) | Kedaluwarsa | neutral | clock (`clock`) |
| Document version (SM:Document) | DRAFT | Draf | neutral | `circle-dashed` |
| | ISSUED | Terbit | info | page (`file-text`) |
| | SUPERSEDED | Direvisi | neutral | page with history (`file-clock`) |
| | VOIDED | Dibatalkan | neutral | crossed page (`file-x`) |
| Rendition (SF-RENDER) | PENDING, RENDERING | PDF Sedang Dibuat | info | three dots (`ellipsis`) |
| | READY | PDF Siap | success | page with a check (`file-check`) |
| | FAILED | PDF Gagal Dibuat | danger | page with a warning (`file-warning`) |
| Pre-payment Kuitansi (WF-FIN-08) | open, not linked | Menunggu Pembayaran | warning | hourglass (`hourglass`) |
| | linked | Terhubung ke Pembayaran | success | link (`link`) |
| Invoice and receivable (derived, BR-FIN-02, BR-FIN-04) | issued, not billed | Belum Ditagihkan | neutral | empty square (`square`) |
| | billed, outstanding | Ditagihkan | info | half-filled circle (`contrast`) |
| | past due date | Lewat Jatuh Tempo | warning | triangle (`triangle-alert`) |
| | outstanding zero | Lunas | success | double check (`check-check`) |
| | dispute hold | Sengketa | warning | flag (`flag`) |
| | written off | Dihapuskan | neutral | eraser (`eraser`) |
| Payment (derived) | without proof (L-29) | Tanpa Bukti | warning | page with a question (`file-question`) |
| | contra'd | Dikontra | neutral | return arrow (`undo-2`) |
| Reservation (SM:Reservation) | ACTIVE | Aktif | info | `circle-dot` |
| | RELEASED | Dilepas | neutral | open lock (`lock-open`) |
| | CONSUMED | Terpakai | success | `check` |
| Purchase (decisions) | OPEN | Terbuka | info | `circle-dot` |
| | CLOSED (remainder closed) | Sisa Ditutup | neutral | archive box (`archive`) |
| | CANCELLED | Dibatalkan | neutral | `ban` |
| Unit condition | usable | Layak Pakai | success | `check` |
| | UNUSABLE | Tidak Layak Pakai | warning | `triangle-alert` |
| Count (WF-INV-05) | OPEN | Terbuka | info | `circle-dot` |
| | STALE (ST-03) | Kedaluwarsa | warning | clock with a mark (`clock-alert`) |
| | APPLIED | Diterapkan | success | `check` |
| Count finding | open, Owner to attribute | Menunggu Owner | warning | person with a question (`user-round-search`) |
| Pending unexplained-loss case (SF-UNATTRIBUTED) | pending | Menunggu Penetapan | warning | scale (`scale`) |
| | resolved | Ditetapkan | success | `check` |
| Correction case (SF-CASE) | OPEN | Terbuka | warning | open folder (`folder-open`) |
| | CLOSED | Ditutup | neutral | closed folder (`folder-check`) |
| Delivery (derived) | partly delivered | Terkirim Sebagian | info | `contrast` |
| | fully delivered | Terkirim | success | `check` |
| | with a discrepancy | Ada Selisih | warning | `triangle-alert` |
| | closed | Ditutup | neutral | `archive` |
| Administrative requirement (BR-ADM-01) | missing | Belum Ada | danger | empty circle (`circle`) |
| | draft only | Disiapkan | info | pencil (`pencil`) |
| | satisfied | Terpenuhi | success | `circle-check` |
| | waived | Tidak Berlaku | neutral | circle with a minus (`circle-minus`) |
| Export (JB-03) | requested | Diminta | neutral | `hourglass` |
| | running | Diproses | info | `ellipsis` |
| | READY | Siap Diunduh | success | download (`download`) |
| | FAILED | Gagal | danger | `circle-x` |
| | WITHHELD | Tidak Tersedia | neutral | eye with a slash (`eye-off`) |
| Import batch (WF-MIG-01) | PREPARED · DRY_RUN_PASSED · SIGNED_OFF · committing · COMMITTED · stopped · ABORTED | Disiapkan · Uji Coba Lolos · Disetujui Bersama · Sedang Diimpor · Selesai Diimpor · Terhenti · Dibatalkan | neutral · info · info · info · success · warning · neutral | `circle-dashed` · `circle-check` · `badge-check` · `ellipsis` · `check` · `circle-pause` · `ban` |
| Account and company | active · inactive | Aktif · Nonaktif | info · neutral | `circle-dot` · `circle-off` |
| Product stock (QS-17) | below its minimum | Stok Menipis | warning | `triangle-alert` |
| Part of a project (derived for PT-31 from the part's own figures) | no record yet · records with something still open · nothing left open · not needed by the project's lines | Belum Dimulai · Berjalan · Tuntas · Tidak Diperlukan | neutral · info · success · neutral | `circle-dashed` · `contrast` · `check` · `circle-minus` |
| Readiness item (PT-29; derived) | complete · not complete | Lengkap · Belum | success · neutral | `check` · `circle-dashed` |
| Line of a receiving or dispatch task on a phone (ADMIN_FLOW D-UX-11) | not yet recorded in this task · recorded | Belum Dicatat · Tercatat | neutral · success | `circle-dashed` · `check` |

Within a family no two states share an icon shape; across families the same shape means the same kind of state. A label never relies on its tone to be understood, and a state never appears as a coloured dot, pill or row tint alone. **Marks are not states.** "Tanggal Mundur" (PT-25) and "Kode Cocok" (PT-27) are inline marks: the `label` style in ink after a 14 px icon — `calendar-clock` and `scan-barcode` — with no border, tone or badge shape. The Lucide names identify the intended shapes; P11 confirms them against the icon set's version chosen under DEP-07 and keeps the shape where a name has changed.

### 12.2 Outcome presentation

The five answers of a command and the answers that are not outcomes ([ADMIN_FLOW IP-01](ADMIN_FLOW.md#ip-01--answers-of-a-command)) have one face each:

| Answer | Label | Tone and icon | Pattern |
| --- | --- | --- | --- |
| COMMITTED | Tersimpan (or the action's own word: Tercatat, Terbit) | success · `circle-check` | PT-14, focus stays; the page shows the recorded result |
| REJECTED | Tidak Dapat Diproses | danger · `circle-x` | PT-14 in the action region and messages at the fields concerned |
| CONFLICT — stale | Data Sudah Berubah | warning · `refresh-ccw` | PT-14 with the current-versus-mine comparison: the changed fields marked on both sides |
| CONFLICT — already recorded | Sudah Tercatat | info · `copy-check` | PT-14 with "Buka Catatan" only |
| CONFLICT — an import identity outside scope (DP-18) | Tidak Dapat Diproses | danger · `circle-x` | PT-14 with MSG-18 as its only text — no "Sudah Tercatat", no `copy-check`, no "Buka Catatan"; identical for a batch and a row |
| PENDING | Sedang Diproses | info · `ellipsis` | PT-14 on the record; the status badge of the derived work |
| FAILED — not processed | Belum Diproses | warning · `circle-pause` | PT-14 with "Coba Lagi"; a defect with MSG-06's defect variant and the reference code |
| FAILED — unknown | Belum Dapat Dipastikan | warning · `circle-help` | PT-14 with "Coba Lagi"; the form's inputs frozen and marked as such |
| Not found · forbidden · error | Halaman Tidak Ditemukan · Tidak Berwenang · Terjadi Kesalahan | neutral · `search-x` · `lock` · `triangle-alert` | SCR-04 with the reference code |
| Session ended | Sesi Berakhir | neutral · `lock` | PT-28 |
| Validation | Periksa Isian | danger · `circle-alert` | PT-09 field messages and the summary line |
| No connection | Koneksi Terputus | neutral · `wifi-off` | PT-14 |

### 12.3 States of a screen

| State | Presentation |
| --- | --- |
| Loading | the skeleton of PT-19 for the page or for a deferred group; a busy control's spinner |
| Empty | one sentence, at most one action (PT-19) |
| Error of a part | PT-14 with the label "Bagian Ini Tidak Dapat Dimuat", warning tone and `triangle-alert`, the reference code and "Muat Ulang Bagian Ini"; the rest of the page keeps working |
| Denied or not found | SCR-04 (ADMIN_FLOW IP-21) |
| Stale | the CONFLICT face above; a count marked *Kedaluwarsa* with "Hitung Ulang" |
| PENDING and FAILED of background work | the status badges of §12.1 on the record and in Beranda's signals |
| Frozen input during an unknown outcome | inputs read-only with the note "Menunggu kepastian — isian dikunci agar tidak tercatat dua kali", the action bar offering "Coba Lagi" and "Tinggalkan" |
| Disabled action | the control disabled with the reason as text beside it |

## 13. Keyboard and scanner

### 13.1 Keyboard

Every action is reachable by keyboard in a logical order; focus is always visible (§15). Shortcuts are an acceleration, discoverable in the "Pintasan" help, and none is a single printable key, because a barcode scanner types printable characters (WCAG 2.1.4; SH-02).

| ID | Keys | Action | Where |
| --- | --- | --- | --- |
| KB-01 | Ctrl+K | open search (Cari) | everywhere |
| KB-02 | Ctrl+/ | open the "Pintasan" help | everywhere |
| KB-03 | Ctrl+B | collapse or expand the navigation | desktop |
| KB-04 | Esc | close the open menu, popover, sheet or dialog — never the re-authentication dialog | overlays |
| KB-05 | Enter | open the focused row; in a form, move to the next field of a line-item row; never send a command from a field | lists, line editor |
| KB-06 | Alt+N | add a line | line-item editor (PT-10) |
| KB-07 | Alt+Delete | remove the focused line, with "Urungkan" offered in the editor | line-item editor |
| KB-08 | Alt+↑ / Alt+↓ | move the focused line up or down | line-item editor, with focus on the line's number — never inside a picker or select, where Alt+↓ opens the list |
| KB-09 | Ctrl+S | save the draft | draft forms only — never a command that records a fact |
| KB-10 | Ctrl+Enter | open the confirming step of the form's primary command (PT-26); the command is sent only by the dialog's button | command forms |
| KB-11 | ↑ / ↓ and Enter | move within and choose from a result list | search, pickers |

Enter, Esc and the arrow keys are not shortcuts: they act as the keys of the focused widget, which KB-04, KB-05 and KB-11 describe. All shortcuts are inactive while the scan field has focus. A financial or stock command is never sent by a shortcut alone: KB-10 opens its confirmation. The help lists the keys in the user's terms; the keys are not remappable in V1, which WCAG 2.1.4 does not require for shortcuts with a modifier.

### 13.2 Scanner

The scanner is a keyboard wedge (ADMIN_FLOW IP-15). The scan field (PT-20) holds focus during a scan step and is focused again after each action; a burst of characters arriving faster than typing and ending with Enter is treated as one scan. A burst that arrives while focus is outside a field that accepts scans — in a quantity or reason field, on a button, in a dialog — is discarded with its Enter: a field restores its previous value and says "Kode terpindai masuk ke kolom {kolom}. Pindai di kolom Pindai."; elsewhere no button is pressed and the notice reads "Kode terpindai diabaikan. Pindai di kolom Pindai." — on a screen without a scan field only "Kode terpindai diabaikan." The fields that accept scans are the scan field (PT-20), *Cari* and the product picker of a line (PT-27), each as an exact lookup. A confirmation never opens with focus on its confirming button (PT-16), and a command is sent only by its button and confirmation, so a scanner's Enter can never record a fact.

## 14. Domain presentations

### 14.1 Financial data

- **The five concepts never share a figure or a label** (DIR-011): *Nilai penjualan* (issued invoices), *Piutang aktif* (billed and unpaid), *Kas masuk* (money received), *Biaya/HPP*, *Kas keluar* (money paid out). A screen that shows several labels each; none is derived on the client.
- **Position block** (PT-24) of an invoice: *Nilai invoice* · *Sudah dibayar* · *Potongan terbukti* · *Dihapuskan* · **Sisa tagihan** — a definition list with right-aligned tabular figures, the last line in 600 weight, all server values. A project shows *Nilai terkonfirmasi* · *Sudah diinvoice* · *Sudah ditagihkan* · *Kas masuk*, and with `cost.view` and `profit.view` *Biaya/HPP* and *Laba*. In a company's profit view, that company's own exposure to pending cases appears on its own line, outside its profit — "Paparan selisih menunggu penetapan" with the products and the company's exposure quantity, never an amount and never the case's pending quantity — shown with `cost.view` of the company (PJ-22; PERMISSIONS_MATRIX §7.2 row 69; ADMIN_FLOW MSG-41; UXS-39).
- **Money** follows §16; a contra or correction shows as its own line with "Dikontra"/"Koreksi", never as a silently changed figure. Totals typed in a form are *Perkiraan* until saved (ADMIN_FLOW IP-13).
- **Per company.** An Admin sees subtotals per company and never a grand total across companies; the Owner's *Gabungan* figures are labelled as such (CS-08).

### 14.2 Inventory

- **Quantity block** (PT-17) per product, and per rack and condition: *Stok fisik* · *Direservasi* · *Tidak layak pakai* · **Tersedia** — with the line "Tersedia = Stok fisik − Direservasi − Tidak layak pakai" in muted text, quantities in base units with the unit and the alternate unit as a hint ("120 rim (24 dus)", §16).
- **Lots**: the opaque code in tabular figures with slashed zeros, rack, condition, remaining quantity and the "Ambil Lebih Dulu" marker on the oldest — a picking aid, never a rule. A lot of a company in scope names that company; a lot of another company shows the relation marker and nothing more (PJ-01, PJ-02).
- **Serials**: presence, lot, rack and condition; any other state only with the movement that explains it (PJ-03).
- **Pending unattributed loss** on its own line: on the pooled views SCR-18 and SCR-19 the case's pending quantity — "Selisih menunggu penetapan: 3 rim — menunggu keputusan Owner" (MSG-41); in a company's stock view only that company's own exposure (§14.1); never inside a company's figures, never blocking (DIR-027).
- **Restock advice**: "Di bawah minimum 20 rim" in ink after the warning tone's icon; no button buys by itself (BR-INV-10).

### 14.3 Document preview

- A system rendition is shown inline in a frame beside its version chain, with "Unduh" and "Cetak"; the header states the document type, number, version, state and rendition state (FL-07's inline profile).
- An uploaded file is never shown inline: it is a file row — name, type, size, uploaded by and when, evidence type and issuer — with "Unduh" as an attachment; an image shows the re-encoded preview (FL-03, FL-04, FL-07).
- A draft prints and previews with the marking "DRAF — bukan dokumen terbit"; a voided version is shown with "Dibatalkan" across its header and is never offered as current.
- A reproduction of a company document uploaded as evidence carries the note "Salinan dokumen terbitan perusahaan — tidak memenuhi syarat dokumen asli" (FL-05) only when the reproduced rendition is one the viewer may see — its company in scope and its family visible (FL-10; DP-13); otherwise the evidence shows as an ordinary upload (ADMIN_FLOW IP-16).

### 14.4 Timeline

PT-18. A correction and the record it corrects link to each other; a backdated entry shows both dates; an entry names its acting account where the viewer may see it — a routed refusal "ditolak — diteruskan", a count made stale "kedaluwarsa" — and "Sistem" labels only an entry no account caused, such as a background job's (SQ-23; PJ-11).

## 15. Accessibility

WCAG 2.2 at level AA is the design target; P8 audits the built screens (UXH-05).

| Area | Rule |
| --- | --- |
| Structure | one `h1` per page; headings in order; landmarks for navigation, main content and the action bar; a skip link "Langsung ke Konten" before the shell |
| Keyboard | everything operable by keyboard in reading order; no positive tab index; no keyboard trap except a dialog's focus loop; shortcuts per §13 |
| Focus | a 2 px `--ring` outline with a 2 px offset on every focusable element; never removed; never hidden under a sticky header, action bar, scan dock or toast — the scroll container's padding equals the height of its sticky regions (2.4.11) |
| Dialogs | focus moves in — to the first input or, when there is none, to "Kembali" —, the background is inert, Escape closes (except re-authentication), focus returns to the trigger |
| Contrast | text 4.5:1, large text and control boundaries 3:1 (§5.1); in forced-colors mode system colours are kept |
| Colour | never the only cue: status by label and shape (§12.1), errors by text and icon, the current navigation item by weight and a rule |
| Targets | at least 24 by 24 px; 44 px with a coarse pointer at any width (§6, §11); no dragging is required for any function (2.5.7) |
| Forms | visible labels; `autocomplete` on the account's own e-mail and password fields (1.3.5); errors tied to fields and announced; paste and password managers allowed on every password field (3.3.8); data the user already entered in the same process is not asked again (3.3.7) |
| Status messages | outcomes announced through a live region present from page load: polite for COMMITTED and PENDING, assertive for REJECTED, CONFLICT and FAILED; scan feedback — the result line of PT-20 polite, scan errors assertive — with focus kept in the scan field (4.1.3) |
| Timing | the idle-session warning with "Tetap Masuk", usable any number of times (2.2.1); the absolute limit announced in advance |
| Motion | reduced-motion removes movement (§8) |
| Reflow and text | content reflows at 320 px; text spacing overrides and 200 % zoom lose nothing (1.4.10, 1.4.12) |
| Consistent help | the "Pintasan" help and the account menu sit in the same place on every page (3.2.6) |

## 16. Copy and formatting

**Tone.** Calm, precise and professional; plain Bahasa Indonesia that an operations user reads at a glance; no marketing phrases, slogans, greetings ("Selamat datang kembali"), jokes, exclamation marks or emoji; the user is not blamed ("Jumlah melebihi sisa pesanan", not "Anda salah memasukkan jumlah"). Terms come from the glossary ([INFORMATION_ARCHITECTURE §3](INFORMATION_ARCHITECTURE.md#3-glossary)); document type names are used exactly as approved; client and legal names, codes, SKUs and user-entered text are data and are never translated.

**Capitalization.** Title Case for navigation entries, page titles, tab and part names, buttons and menu actions, and status labels — with prepositions and conjunctions such as *dan, atau, di, ke, dari, untuk, yang, pada, dengan, per, oleh, tanpa, sebagai* in lower case ("Kuitansi untuk Proses Pembayaran", "Terapkan Ulang pada Versi Terbaru"). Title Case also for headings, dialog and sheet titles and group labels. Sentence case for field labels, checkbox and radio labels, column headers, option values, fact values, helper text, messages and notices. Abbreviations as written: PDF, SKU, PIC, HPP, BAST, SPK, HPS, PO, WIB.

**Punctuation.** Messages are full sentences ending with a period; buttons, labels and headings have none; a colon only between a label and its value in running text; an em dash with spaces joins a state and its instruction ("Sudah tercatat — periksa sebelum mengulang"); an ellipsis marks work in progress ("Menyimpan…").

**Formats.**

| Value | Format | Example |
| --- | --- | --- |
| Rupiah | "Rp" with no space, dot as thousands separator, comma before sen; whole rupiah unless the value has sen — in a column where any value has sen, every value shows two decimals | Rp1.250.000 · Rp1.250.000,50 |
| Negative money | a minus sign before "Rp" | −Rp250.000 |
| Quantity | exact, never rounded, comma as decimal separator, followed by its unit; the alternate unit as a hint | 12 rim · 3,5 kg · 120 rim (24 dus) |
| Percentage and rate | comma decimal and the percent sign without a space | 11% · 0,25% |
| Date | two-digit day, Indonesian month abbreviation, four-digit year | 02 Okt 2026 (Jan Feb Mar Apr Mei Jun Jul Agu Sep Okt Nov Des) |
| Date in a sentence or document | full month name | 2 Oktober 2026 |
| Time | 24-hour clock with a dot, "WIB" where the time stands alone | 14.05 WIB |
| Date with time | date, then time | 02 Okt 2026 14.05 WIB |
| Period | month and year, or the year | Okt 2026 · 2026 |
| Business numbers and codes | exactly as issued — the format is the company's numbering scheme (GAP-019) — in tabular figures with a slashed zero; a lot code is the opaque ten-character code of H6-10 | ARJ/INV/2026/10/0031 (illustrative) · K7M2QX9H4T |
| Public identifiers | never shown: they live only in page addresses (INFORMATION_ARCHITECTURE §11) | — |
| Counts | bounded per company where PF-15 bounds them | ARJ 4 · BTN 20+ |

All dates and times are WIB (BR-DT-06). Generated documents use the same formats and the document-type names; their full wording and layouts are validated under GAP-019.

## 17. Print views

A print view is the page itself prepared for paper (DP-19): the same records, fields and figures the viewer sees, nothing more and nothing less.

- Navigation, action bars, filters' controls, buttons and toasts are not printed; applied filters are printed as a line of text under the title.
- Black on white; status badges keep their labels and shapes; tones print as their text.
- A header with the screen name, the company labels in view, "Dicetak oleh {akun} pada {tanggal} {jam} WIB"; a footer with "Halaman {n} dari {m}" — page numbers belong to paper, not to lists.
- Tables repeat their header row on every page and never truncate a cell; wide tables print in landscape.
- A list prints the rows loaded on screen and says "Menampilkan {n} baris yang dimuat"; the complete list is an export.
- Generated documents are printed from their rendition, never from a screen (§14.3).

## 18. Anti AI-slop mapping

Each prohibition of the anti AI-slop constitution (OB §21) has a rule that prevents it. The P1 directive's list and the social-media patterns of DIR-033 §10-M map onto the same rules.

| ID | Prohibition (OB §21) | Preventing rule |
| --- | --- | --- |
| SLOP-01 | every section inside a card | PT-12: panels only where a group needs a boundary; space and rules group content |
| SLOP-02 | nested cards without meaningful hierarchy | PT-12: never a panel inside a panel |
| SLOP-03 | giant rounded containers everywhere | §5.4: radius 4 px and 6 px only |
| SLOP-04 | excessive border radius | §5.4 |
| SLOP-05 | pill-shaped everything | §8: no pill shapes; badges are 4 px rectangles |
| SLOP-06 | excessive gradients | §5.1: flat colours only; no gradient token exists |
| SLOP-07 | decorative gradients | §5.1 |
| SLOP-08 | glowing UI | §5.4: overlay elevation only; no glow |
| SLOP-09 | neon | §5.1: one deep accent; tones at 6.5–10:1 contrast, no saturated fills |
| SLOP-10 | generic glassmorphism | §5.1: opaque surfaces; no blur or transparency tokens |
| SLOP-11 | generic mesh backgrounds | §5.1: the canvas is plain white |
| SLOP-12 | floating decorative blobs | §8: no decorative shapes |
| SLOP-13 | generic abstract illustrations | PT-19: no illustrations, including empty states |
| SLOP-14 | excessive shadows | §5.4: elevation only for overlays |
| SLOP-15 | excessive animation | §8: motion only as feedback; 100–200 ms |
| SLOP-16 | parallax | §8 |
| SLOP-17 | useless hover tricks | §8; §11: hover only tints a row or button and is never the only path |
| SLOP-18 | excessive dashboard KPI cards | PT-21: counts are inline text in signal rows; INFORMATION_ARCHITECTURE §9 |
| SLOP-19 | giant welcome banner | PT-03: no banners in page headers |
| SLOP-20 | "Welcome back!" SaaS clichés | §16: no greetings |
| SLOP-21 | startup marketing copy | §16: tone |
| SLOP-22 | emoji as interface decoration | §16: no emoji |
| SLOP-23 | unnecessary icons | §8: icons only where they carry meaning |
| SLOP-24 | an icon inside every label or button | §8; PT-11 |
| SLOP-25 | excessive whitespace that slows work | §5.3: one compact desktop density; 36 px rows |
| SLOP-26 | gigantic headings | §5.2, §7: nothing above 20 px |
| SLOP-27 | oversized hero-like application headers | PT-03 |
| SLOP-28 | visually noisy dashboards | PT-21; INFORMATION_ARCHITECTURE §9: grouped rows, five groups at most |
| SLOP-29 | random colour usage | §5.1: one accent, five tones bound to meanings (§12.1) |
| SLOP-30 | arbitrary badges | §12.1: a badge only for a state of the table |
| SLOP-31 | inconsistent radius | §5.4: two values |
| SLOP-32 | inconsistent icon size | §5.3: 16 px; 14 px only inside badges and inline marks; 20 px only in the phone navigation |
| SLOP-33 | inconsistent spacing | §5.3: one scale |
| SLOP-34 | fake analytics with no operational value | PT-21; INFORMATION_ARCHITECTURE §9: only QS signals and CALC figures; no charts in V1 (§5.1) |
| SLOP-35 | looking like a landing page | PT-03, PT-19, SLOP-19–SLOP-21 |
| SLOP-36 | looking like a Dribbble concept | §4 decisions grounded in task speed; [P7_QUALITY_GATE](P7_QUALITY_GATE.md#desainpakai-exploration) rejections |
| SLOP-37 | looking like a generic Tailwind SaaS template | §5.1: no default indigo or tinted pills (DPB-01 rejection of variant C) |
| SLOP-38 | looking like an AI-generated admin dashboard | SLOP-18, SLOP-28, SLOP-34 |

| P1 directive item | Rule |
| --- | --- |
| putting every section inside a Card; excessive nested cards; giant rounded containers; pill-shaped everything; excessive border radius | SLOP-01–SLOP-05 |
| decorative gradients; neon/glow; glassmorphism; floating decorative blobs | SLOP-07–SLOP-10, SLOP-12 |
| giant welcome banners; generic "Welcome Back!" patterns | SLOP-19, SLOP-20 |
| oversized typography; excessive whitespace | SLOP-26, SLOP-25 |
| random colorful KPI cards; fake analytics | SLOP-18, SLOP-29, SLOP-34 |
| emoji as UI decoration; icons on every label without purpose; generic illustrations | SLOP-22, SLOP-24, SLOP-13 |
| excessive shadows; excessive animation; parallax; decorative motion | SLOP-14–SLOP-16, §8 |
| landing-page-like layouts; Dribbble-style concepts that reduce efficiency | SLOP-35, SLOP-36 |

| ID | Social-media pattern (DIR-033 §10-M) | Preventing rule |
| --- | --- | --- |
| SLOP-39 | feeds | PT-18 and PT-21 are records and signals, chronological or by condition, with no infinite scroll — "Muat Lagi" is explicit (PT-08) |
| SLOP-40 | reactions | no reaction controls exist on any record, entry or comment |
| SLOP-41 | likes | as SLOP-40 |
| SLOP-42 | streaks | no per-user activity counters or streaks |
| SLOP-43 | confetti | §8: no celebratory motion; COMMITTED is a quiet message |
| SLOP-44 | gamification | no points, levels, progress rings, completion percentages or leaderboards (PT-29) |
| SLOP-45 | chat-bubble decoration | PT-18: timeline entries are rows, not bubbles; no avatars; the tool context's chat composer was rejected (DPB-01) |

## 19. Pattern coverage

Every screen of [INFORMATION_ARCHITECTURE §8](INFORMATION_ARCHITECTURE.md#8-screen-inventory) and every journey of [ADMIN_FLOW §10](ADMIN_FLOW.md#10-journeys) is composed of the patterns of §9; none needs a pattern that is not defined here. A build unit that finds a screen it cannot compose from them raises the gap through change control instead of inventing a pattern.

| Pattern | Used by |
| --- | --- |
| PT-01, PT-02 shell and phone navigation | every authenticated screen (SCR-03, SCR-05–SCR-60) |
| PT-03 page header and page actions | every screen except SCR-01, SCR-02 and SCR-04 |
| PT-04 company label, working filter and relation marker | every list and record that carries company-owned data; the pooled stock views SCR-18 and SCR-19 |
| PT-05 data table · PT-06 dense list rows | every list screen: desktop and phone respectively |
| PT-07 filters · PT-08 list continuation | every list screen |
| PT-09 form layout · PT-10 multi-line entry | every command form; PT-10 for project, quotation, purchase, invoice, receiving, dispatch, count and import lines (ADMIN_FLOW IP-13) |
| PT-11 buttons and actions · PT-16 dialogs and sheets | every screen with a command |
| PT-12 detail layout | every record screen; the project hub SCR-10 with its parts |
| PT-13 status badge | every list and record of a state family of §12.1 |
| PT-14 outcome message · PT-15 toast | every command (ADMIN_FLOW IP-01); PT-15 only as §9.4 allows |
| PT-17 inventory · PT-24 financial position | stock screens SCR-18, SCR-19 and the warehouse forms; invoice, receivable, payment and project figures |
| PT-18 activity timeline | every record screen's *Riwayat*; the Owner's SCR-59 |
| PT-19 loading, skeleton, empty, error and denied | every screen and every deferred group; SCR-04 |
| PT-20 scan field | SCR-19, SCR-21, SCR-22, SCR-24, SCR-25, SCR-26, SCR-32 (INFORMATION_ARCHITECTURE §10) |
| PT-21 signal group and queue row | Beranda SCR-05 and SCR-06; the Owner review queue SCR-07 |
| PT-22 thumbnails and previews | product lists and records; document and evidence screens SCR-34–SCR-37 |
| PT-23 money, quantity and date presentation | every figure on every screen and print view |
| PT-25 business date control | every command form (ADMIN_FLOW IP-07; HO-08); the numbering commands show their date as the fixed day of recording instead (ADMIN_FLOW §12.2; NM-03) |
| PT-26 confirmation of an irreversible or correcting command | every irreversible, destructive or correcting command (ADMIN_FLOW IP-08, IP-09, §11, §12.2) |
| PT-27 search and type-ahead | *Cari* and SCR-08; every picker inside a form |
| PT-28 step-up and re-authentication dialog | step-up commands (ADMIN_FLOW IP-10); every session expiry (ADMIN_FLOW §6) |
| PT-29 derived readiness list | the project's completion readiness (SCR-14), administrative requirements, company onboarding (ADMIN_FLOW §12.4) |
| PT-30 capability-by-company preview | grant editing on SCR-57 (H7-06) |
| PT-31 derived part strip | the project detail SCR-10 on desktop (D-IA-09) |

## 20. Traceability

- **V1_SCOPE and the brief:** the P7 handoff requirements and the language and experience contract → §4–§18; OB §20 and REF-054 → §2, §10; OB §21, the P1 directive's anti AI-slop list and REF-055 → §18; OB §22 → §8, §11, §15; OB §23 → §9; AC-14 → §6, §11, §13, §15; AC-10 and AC-11 → §14.1.
- **DIR-033:** §10-M minimum content → §4–§18 (the closure table is in [P7_QUALITY_GATE](P7_QUALITY_GATE.md#obligation-closure)); §10-N → §15; §13 decision levels → §4; §11 DesainPakai exploration → §4 and the gate's exploration evidence.
- **WORKFLOWS and BUSINESS_RULES:** the state machines of BUSINESS_RULES §3, WORKFLOWS' derived states and L-25, L-29 → §12.1; DIR-011 and BR-INV-01 → §14; BR-DT-06 → §16; WORKFLOWS §8 corrections → PT-26, §14.1.
- **PERMISSIONS_MATRIX and SECURITY:** PJ-01–PJ-04, PJ-07, PJ-11, PJ-22 and PJ-23 → §14 and PT-18; H6-10 → §16; H7-02 → PT-04; H7-06 → PT-30; H7-09 → PT-28; FL-03–FL-05 and FL-07 → §14.3; WS-08 (`font-src 'self'`, camera denied) → §2, §13.2; DP-19 → §17.
- **CONCURRENCY_IDEMPOTENCY and PERFORMANCE:** HO-01 → §12.2; HO-03 → PT-11; HO-08 → PT-25; HO-10 and PF-15, PF-17 → PT-05–PT-08; PF-31, PF-32 → PT-22; PF-34 → PT-19, PT-21.
- **Gaps:** GAP-019 → §1, §14.3, §16; GAP-020 → §6, §11, §13; GAP-029 → §12.1; GAP-032 → PT-30.

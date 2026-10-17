# Design references

Status: REVIEW | Updated: 2026-10-18 | Owner: Planning

Authority: the P7 UX re-baseline, authorized for Stage 0 and Stage 1 by the Owner under [DIR-054](../00-governance/DECISION_LOG.md#dir-053-dir-054-obs-019-and-tech-027--p7-ux-re-baseline-decisions-stage-01-authorization-baseline-and-direction), with the design reference the Owner chose in [DIR-053](../00-governance/DECISION_LOG.md#dir-053-dir-054-obs-019-and-tech-027--p7-ux-re-baseline-decisions-stage-01-authorization-baseline-and-direction) K3 and the standing intent of D3 and D4 (DIR-035); revised in the C1 rework that the Owner authorized under [DIR-056](../00-governance/DECISION_LOG.md#dir-055-dir-056-obs-020-and-tech-028--p7-ux-re-baseline-checkpoint-c1-decision-rework-authorization-baseline-and-rework), after the Checkpoint C1 decisions OC-01–OC-12 of [DIR-055](../00-governance/DECISION_LOG.md#dir-055-dir-056-obs-020-and-tech-028--p7-ux-re-baseline-checkpoint-c1-decision-rework-authorization-baseline-and-rework); revised again in the C1 visual checkpoint that the Owner authorized under [DIR-058](../00-governance/DECISION_LOG.md#dir-057-dir-058-obs-021-and-tech-029--p7-ux-re-baseline-checkpoint-c1-visual-direction-decisions-visual-checkpoint-authorization-baseline-and-visual-anchors), after the visual-direction decisions of [DIR-057](../00-governance/DECISION_LOG.md#dir-057-dir-058-obs-021-and-tech-029--p7-ux-re-baseline-checkpoint-c1-visual-direction-decisions-visual-checkpoint-authorization-baseline-and-visual-anchors); and revised for the C1 closure that the Owner authorized under [DIR-060](../00-governance/DECISION_LOG.md#dir-059-dir-060-obs-022-and-tech-030--p7-ux-re-baseline-checkpoint-c1-closure-golden-frontend-reference-adoption-authorization-baseline-and-reconciliation), after the Owner's decisions of [DIR-059](../00-governance/DECISION_LOG.md#dir-059-dir-060-obs-022-and-tech-030--p7-ux-re-baseline-checkpoint-c1-closure-golden-frontend-reference-adoption-authorization-baseline-and-reconciliation) closing Checkpoint C1. Brought into line with Stage 2 under [DIR-064](../00-governance/DECISION_LOG.md#dir-063-dir-064-obs-024-and-tech-032--p7-ux-re-baseline-s2-0-acceptance-s2-1-authorization-baseline-and-stage-2-documents), after the Owner's Stage 2 decisions [DIR-061](../00-governance/DECISION_LOG.md#dir-061-dir-062-obs-023-and-tech-031--p7-ux-re-baseline-stage-2-decisions-s2-0-authorization-baseline-and-prototype-setup).

**Checkpoint C1.** Its Stage 1 direction was rejected for focused rework (DIR-055); at the re-review the Owner preserved the rework's structure, R1, and left the visual direction to a focused checkpoint (DIR-057); the Owner then closed C1, approved with one named adjustment: the Golden Frontend Reference v1.0.0 as the visual foundation on R1's structure (DIR-059 GD-01).

**C1 closure.** This revision is GA1's (DIR-060 §13): one draft visual system based on the Golden Frontend Reference v1.0.0 — the reference below —, adapted to the approved stack and reconciled with the repository in the gate record's [Golden Frontend Reference reconciliation](evidence/P7_REBASELINE_GATE.md#c1-closure--golden-frontend-reference-reconciliation). VC1's draft visual system, derived from the mockups, is superseded as the visual direction; it stays in Git at commit 6 (`git show 03f61a7:docs/07-ux-design/DESIGN_REFERENCES.md`), R1's revision at commit 4 (`git show 2996465:docs/07-ux-design/DESIGN_REFERENCES.md`) and the Stage 1 revision at commit 2 (`git show 2a5c8aa:docs/07-ux-design/DESIGN_REFERENCES.md`).

**Stage 2 continuity (S2-1).** The S2-1 continuity revision, made on 2026-10-14 under DIR-064, adds DIR-061's narrowing to presentation, its prototype permission and the corrections of GR-09, GR-20 and GR-16; nothing else changes. GA1's C1-closure revision stays in Git at commit 8 (`git show e9538a5:docs/07-ux-design/DESIGN_REFERENCES.md`).

**Stage 2 continuity (S2-2).** On 2026-10-14, under [DIR-066](../00-governance/DECISION_LOG.md#dir-065-dir-066-obs-025-and-tech-033--p7-ux-re-baseline-s2-2-decisions-authorization-baseline-and-journey-prototypes), the reference hierarchy's PRIMARY list reads "the presentation of interaction and UX patterns", in line with DIR-061 SD-02; nothing else changes. The S2-1 revision stays in Git at commit 10 (`git show 0c1194d:docs/07-ux-design/DESIGN_REFERENCES.md`).

**Stage 2 continuity (GF-1).** On 2026-10-15, under [DIR-068](../00-governance/DECISION_LOG.md#dir-067-dir-068-obs-026-and-tech-034--p7-ux-re-baseline-golden-first-decisions-pilot-authorization-baseline-and-pilot) §9 item 7, the reference hierarchy states the Owner's K1 (DIR-067) in one sentence; nothing else changes. The S2-2 revision stays in Git at commit 11 (`git show ba25716:docs/07-ux-design/DESIGN_REFERENCES.md`).

**Stage 2 continuity (GF-2).** On 2026-10-17, under [DIR-075](../00-governance/DECISION_LOG.md#dir-073dir-075-obs-029-and-tech-036--p7-ux-re-baseline-cp-1r-verdict-p7p11-completion-plan-run-pc-1-and-stage-2-completion) §10 item 2 — GF-2's §11 item 7 —, the reference hierarchy states the Owner's decision 3 and OQ-10 (DIR-069) in one sentence; nothing else changes. The GF-1 revision stays in Git at commit 12 (`git show da3040a:docs/07-ux-design/DESIGN_REFERENCES.md`).

**Stage 3 completion (PC-2).** *Note of 2026-10-18:* under [DIR-078](../00-governance/DECISION_LOG.md#dir-077-dir-078-obs-030-and-tech-037--p7-ux-re-baseline-cp-a-decisions-run-pc-2-with-p9s-drafting-baseline-and-drafts) SP-63 run PC-2 completed this record as AICWDF §18.12 asks — §9 below names, for each element of the record, where it is now final in Stage 3's drafts; the record keeps its REVIEW status, and its earlier sections stay as Stage 1 and Stage 2 wrote them. The GF-2 revision stays in Git at commit 15 (`git show 4d4b867:docs/07-ux-design/DESIGN_REFERENCES.md`).

**Draft — not normative.** This record follows AICWDF §18.12. Nothing in it changes [INFORMATION_ARCHITECTURE](INFORMATION_ARCHITECTURE.md), [DESIGN_SYSTEM](DESIGN_SYSTEM.md) or [ADMIN_FLOW](ADMIN_FLOW/README.md), and no build unit may use it until a later authorized stage approves it — Stage 3 replaces the normative documents and Stage 5 grants APPR-011 (the stage plan is in [P7_REBASELINE_GATE](evidence/P7_REBASELINE_GATE.md#plan)). The intake, the reconciliation, the anchor coverage and the Stage 2 inputs are in that gate record, from [C1 closure — Golden Frontend Reference intake](evidence/P7_REBASELINE_GATE.md#c1-closure--golden-frontend-reference-intake) to [Gate GA1](evidence/P7_REBASELINE_GATE.md#gate-ga1).

**Reference hierarchy (GD-02).** ABOVE the reference: the repository's P0–P6 truth, the Owner's decisions, R1's structure and C1A-1–C1A-3 — where the reference differs from them, the project's decision governs and the difference is recorded. PRIMARY: the reference — for presentation only, as DIR-061 SD-02 narrows it (below) —, for design foundations — typography, colour roles, spacing, radius, surfaces, status tones, density and motion —, component anatomy and states, the presentation of interaction and UX patterns, accessibility rules and responsive behaviour; its visual and motion fidelity is preserved, and a deviation needs a recorded reason — accessibility, a P0–P6 rule, R1's structure, an Owner decision or a shadcn/ui constraint. Under the Owner's K1 ([DIR-067](../00-governance/DECISION_LOG.md#dir-067-dir-068-obs-026-and-tech-034--p7-ux-re-baseline-golden-first-decisions-pilot-authorization-baseline-and-pilot)) the reference governs visual presentation, components, layout, motion, animations and interaction quality, and P0–P6, ADMIN_FLOW and R1's information architecture decide what a page holds and offers — its entries, actions, steps, messages and grants —, the recorded reason for a deviation applying to the reference's implementation — its pages, layout, components and motion. Under the Owner's decision 3 and OQ-10 ([DIR-069](../00-governance/DECISION_LOG.md#dir-069-dir-070-obs-027-and-tech-035--p7-ux-re-baseline-gf-2-decisions-authorization-baseline-and-corrected-pilot)) the reference is authoritative for visual design, layout and composition, component proportions, typography, colour, surfaces, shadows, borders, icons, the presentation of navigation, responsive behaviour, visual hierarchy, motion and interaction presentation — the navigation shell's anatomy, placement and interaction quality included —, MULTIPLECORP for business workflows, information architecture, permissions, security, data and domain rules — R1's role-based labels, actions and security boundaries included —, each page reproducing the reference first, then the differences MULTIPLECORP requires, their adaptation and optional refinements kept apart. Where the reference is silent: VD-02–VD-07, VD-09 and VD-10 (DIR-057) and VP-2–VP-8 (DIR-058), as reconciled. PROVENANCE ONLY: the mockups REF-M1–REF-M3 and the videos REF-V1 and REF-V2.

**Stage 2 — presentation only (DIR-061 SD-02, SD-04).** The reference governs presentation only — the visual design, component anatomy, motion, responsive behaviour, accessibility and how an interaction is presented. Behaviour — which actions exist, when a confirmation is asked, state transitions, permissions, the meaning of messages, numbering and list rules — always follows MULTIPLECORP, and the reference adapts to MULTIPLECORP, never the reverse (AK-1–AK-8). Two GA1 results are corrected: logout follows ADMIN_FLOW MSG-35 and asks only when input is unsent (GR-09), and the reference's filter toolbar supplies presentation only, DESIGN_SYSTEM PT-07 and PT-08 and PERFORMANCE PF-42 staying canonical (GR-20). GR-16 is judged at the Owner's visual preview, in two variants (SV-03; SD-08). Byte-identical copies of the reference's runtime and font files are permitted in the private prototype repository (SD-04); none is in this repository, and GD-05 is unchanged. Stage 2's drafts, S2-1's design-system mapping among them, are indexed in [stage2/README.md](stage2/README.md).

**Precedence.** The reference informs the visual and interaction direction only. It never overrides business truth, authorization, company scope, field projection, security or accessibility; its frontend guards are never authorization; and P0–P6 semantics never change for a visual reason (DIR-060 §4). DesainPakeAI output is never a source of truth, and DesainPakeAI is discontinued for design work (GD-06).

## 1. Reference sources

| Reference | Role | Identity | How it is used |
| --- | --- | --- | --- |
| Golden Frontend Reference v1.0.0 | PRIMARY (GD-02) | The public repository <https://github.com/yusufarst/golden-frontend-reference>; release tag `v1.0.0`, the annotated tag object `9d46af300949b2dd4b2f3d443652d78c38067deb` peeled to commit `14497d997ee7994dfa9153d41b8cea636578e24c`; baseline `golden-frontend-v1.0`, FROZEN — OWNER APPROVED on 2026-10-12; its frozen scope the 41 files of `release/golden-frontend-v1.0/SHA256SUMS-scope.txt`, whose own SHA-256 is `39fa879dc304275e55b1ffecda290ebd6b481a27d89bbda67011c26e0796eca4` — verified read-only on 2026-10-13 ([identity verification](evidence/P7_REBASELINE_GATE.md#c1-closure--golden-frontend-reference-identity-verification); [intake](evidence/P7_REBASELINE_GATE.md#c1-closure--golden-frontend-reference-intake)) | Its four layers — tokens, components, patterns and principles — adapted to Laravel + Inertia + React with TypeScript and shadcn/ui on Tailwind CSS (GD-05). It stays outside the repository, recorded as text (GD-05; [SOURCE_OF_TRUTH](../00-governance/SOURCE_OF_TRUTH.md#p7-ux-re-baseline-checkpoint-c1-closure-and-golden-frontend-reference-adoption)); v1.0.0 is pinned and a successor needs change control. It has no open-source licence: the Owner, as its owner, permits MultipleCorp to quote and summarize it and to transcribe its token names and values, component anatomy, patterns and principles (GD-08) |
| REF-M1–REF-M3 | PROVENANCE ONLY | The approved MultipleCorp mockups — Owner Beranda, sign-in, Buat Pembelian —, recorded as text in [SOURCE_OF_TRUTH](../00-governance/SOURCE_OF_TRUTH.md#p7-ux-re-baseline-checkpoint-c1-visual-direction-decisions-handoff-corrections-and-visual-checkpoint-authorization) | The reference was derived from them; they no longer govern directly |
| REF-V1 and REF-V2 | PROVENANCE ONLY | The modern-login and application videos, recorded as text likewise | As REF-M1–REF-M3 |
| Ramp | Historical | The DesainPakeAI project's starter design, primary under DIR-053 K3 until VD-01 | No longer a source; K3's non-copy rules stay ACTIVE (§4) |

No reference, frame, crop, screenshot, render, font file or file of the reference is in the repository (RH-3; GD-05).

## 2. Relevant components and screens

The reference's components against the anchor areas, in summary; element by element in the gate record's [anchor coverage](evidence/P7_REBASELINE_GATE.md#c1-closure--anchor-coverage).

| Anchor area | The reference's components | Stage 2 builds |
| --- | --- | --- |
| Sign-in (AN-1) | The sign-in composition, fields, the CTA, disclosure, the verification step and its transition, the language toggle | The abstract visual panel, the code-entry control, the Google button to Google's guidelines |
| Owner Beranda (AN-2) | Signals and action rows, the KPI tile, the chart card with legend and table, row lists, the segmented control, the date range | The segmented stage indicator of the project rows |
| Admin *Pekerjaan* (AN-3) | Sections, action rows with one action, company tags, status text, the empty state, the filter toolbar | The desktop disclosure of the work groups |
| Project Journey and composer (AN-4) | Page header and "Tindakan Lain", the progress stepper, next step and prerequisite, checklist and checks, facts and metrics, row lists; the composer, the paper's draft mode, the COMMIT confirmation and the result | The *Sekarang · Berikutnya · Hambatan* strip; the desktop fold of the stages that do not apply; the draft preview's producer (GAP-053) |
| Buat Pembelian (AN-5) | Form grid, fields, the business-date line, the line's anatomy and alignment rule, the summary card, the replace-input confirmation, the sticky action bar | One add path — IP-13's empty last row; the desktop "Rincian lain" disclosure |
| Mobile warehouse (AN-6) | Row lists, status text with announcements, the bottom sheet, the result surface | The scan dock (PT-20); 48 px controls; reflow at 320 px |

## 3. Extracted principles

The reference's principles adopted as design and review criteria, with the repository's rules that stay:

1. **Calm, precise, spacious but productive.** Macro breathing room between sections, micro density within them; content widths per archetype — data 1,680, standard 1,440, settings 1,280, form 1,120, focused 960 px —, never wall-to-wall (AICWDF §18.2).
2. **Surfaces, not decoration.** White cards on a warm canvas, the edge carried by a hairline and a barely visible shadow; no card inside a card; text-first section headings, icons only for navigation, actions, status and identity.
3. **One action colour; lime only as a signal.** A dark green primary; olive for selection, focus and indicators; lime tints for the selected entry and attention, the signal lime for the current step, the soft lime CTA only on the sign-in; strong fills under 2 % of a screen; semantic colour only for meaning (VD-03; K3 — lime never as text).
4. **Hierarchy by size and colour tier.** Four weights; each grey only on the surface it was measured for; tabular figures for numbers.
5. **One dominant next action.** At most one primary per region, two quiet secondaries and "Tindakan Lain" on desktop, the primary and "⋯" on phones; a button changes data or starts a task, navigation is a place; one control per intent (C1A-2).
6. **Task before capability.** A page shows what its job needs; a capability the account lacks is absent, never disabled; a state that blocks an existing action is explained (AZ-01).
7. **Guidance on the real screens.** Derived steps and one next step earned by the record's state (PL-1–PL-3; C1A-1); no wizard, no stored progress, no explanatory sentence visible by default (OC-06).
8. **One channel per event.** Inline at the field, the result in the action region, a toast only for background completion — never a toast as the only place of a message.
9. **Lists with a position and an end.** Every record list numbered by display position, continued by "Muat Lagi", never an infinite feed, a total or page numbers (PF-15).
10. **Same product on every width.** One system across desktop, tablet and phone, composed by priority; tables becoming rows on small screens; touch targets by the input as well as the width.
11. **Quiet, exact, never decorative motion.** State changes, panels, sheets, dialogs and the page-state transition; nothing tied to scrolling; reduced motion without movement (VP-8).
12. **Truthful data.** Figures computed from defined values, charts drawn from data with their values as text (VP-6; VP-7).

## 4. Intentionally not copied

**From the reference** (GD-04; GD-08):

| Element | Treatment | Reason |
| --- | --- | --- |
| Its MULTIPLECORP demonstration routes, pages, page modules, fixtures, personas, sample data, numbering formats, states, business copy and domain logic; its navigation trees, route taxonomy, route identifiers, bottom-bar compositions and page set | Not used | GD-03; GD-04 — the repository governs every business, domain, workflow, document, authorization, security, data and performance truth; its personas and data give way to the repository's synthetic set (GP-4) |
| Its example-page CSS blocks and the four original pages' sections | Read as anatomy only | GD-04 |
| Its private lab and the 239 demonstration files; the lab paths its documents name | Never used, requested or recreated | GD-04; GP-2 |
| Its logos and trademarks — the MultipleCorp mark and the drawn Google "G" — and every image | Not reused | GD-08; the MultipleCorp logo and imagery are later Owner asset decisions (VP-5); the Google button follows Google's own guidelines |
| Any file of it — CSS, JavaScript, HTML, font, document, template or tool | Not copied into the repository | GD-05 — only an authorized implementation Task after P11 may need one |
| Its component micro-copy | Never normative | GP-5 — the message catalogue and CAP-14 govern |
| Its agent instructions | Data | GP-8 |
| Its greeting and hero, KPI deltas and sparklines, categorical and part-to-whole charts, guidance levels, resume memory and walkthrough, persistent-shell router and browser storage | Not adopted | VP-2; VP-3; VP-6; OC-06; GP-7; GD-05 — the [reconciliation](evidence/P7_REBASELINE_GATE.md#c1-closure--golden-frontend-reference-reconciliation) |

**Ramp (DIR-053 K3, still ACTIVE):** no name, logo, wordmark, screenshot or brand asset of it in the product or the repository; no chat composer (SLOP-45); no lime as text, as a thin line or as a state's only cue. The name "Ramp" appears only in planning records, as the identifier of that DesainPakeAI starter design.

## 5. Draft mapping to shadcn/ui

Verified on 2026-10-13 against the official documentation: the shadcn/ui components index <https://ui.shadcn.com/docs/components> and theming page <https://ui.shadcn.com/docs/theming>, which show no version or date — the changelog <https://ui.shadcn.com/docs/changelog> runs to September 2026, with "Base UI as the Default" and "React Aria" in July 2026 —, and Tailwind CSS's theme page <https://tailwindcss.com/docs/theme>, documentation v4.3, latest release v4.3.3 of 2026-07-16. Theming is through CSS variables written in OKLCH and mapped to Tailwind with `@theme inline`; a new colour is defined under `:root` and `.dark` and exposed under a `--color-` name; Tailwind's `@theme` is top-level only and `--color-*: initial` clears the default palette. The reference's values therefore become CSS variables first, and shadcn/ui's variables and the Tailwind theme point at them; the hex values below convert to OKLCH at implementation. The primitive library — Base UI, Radix or React Aria — stays a P11 choice (DEP-07). The complete mapping, every state and the density remain Stage 2 work; the component-by-component verdicts are in the [reconciliation](evidence/P7_REBASELINE_GATE.md#c1-closure--golden-frontend-reference-reconciliation) b).

**Theme variables.**

| shadcn/ui variable | The reference's token | Value |
| --- | --- | --- |
| `--background` · `--foreground` | `--color-canvas` · `--color-text-primary` | `#f7f8f3` · `#0f1014` |
| `--card`, `--popover` and their foregrounds | `--color-surface` · `--color-text-primary` | `#ffffff` · `#0f1014` |
| `--primary` · `--primary-foreground` | `--brand-primary` · `--color-text-on-primary` | `#1a5035` · `#ffffff` |
| `--secondary` · `--secondary-foreground` | `--color-surface-sunken` · `--color-text-primary` (the sunken button) | `#f2f3ee` · `#0f1014` |
| `--muted` · `--muted-foreground` | `--color-surface-sunken` · `--color-text-tertiary` | `#f2f3ee` · `#6b6d73` (4.64:1) |
| `--accent` · `--accent-foreground` | `--color-row-press` · `--color-text-primary` (menu and row highlights) | `#f2f3ec` · `#0f1014` |
| `--destructive` | `--color-danger-solid` | `#b4373f` |
| `--border` | `--color-border-subtle` — dividers and card edges, decorative | `#f0f1ef` |
| `--input` | the corrected control edge (GR-01) | `#898982` |
| `--ring` | `--color-focus` | `#545e21` |
| `--chart-1`–`--chart-5` | the ageing palette, in order of severity (VP-6) | `#99b1a0`, `#edd798`, `#cbac8d`, `#d56e5b`, `#a9322e` |
| `--sidebar` · `--sidebar-foreground` · `--sidebar-accent` · `--sidebar-primary` · `--sidebar-border` · `--sidebar-ring` | surface · primary ink · lime 150 (the selected entry) · olive (its indicator) · subtle border · focus | `#ffffff` · `#0f1014` · `#ecf1d2` · `#545e21` · `#f0f1ef` · `#545e21` |
| `--radius` | the card radius; the steps set to the reference's 4, 6, 8, 10 and 12 px rather than derived | 10 px |
| Added: status tones, lime tints, the sign-in CTA, the category discs | `--color-success-*`, `--color-warning-*`, `--color-pending-*`, `--color-danger-*`, `--color-info-*`, `--color-neutral-*`; `--brand-tint-*`, `--brand-signal`, `--brand-cta*`; `--tint-*` | §6 |

The Tailwind theme carries the reference's other tokens the same way: `--font-sans` pointing at Inter through `@theme inline`, the spacing base of 4 px, its shadows, its easing curves and durations, and breakpoints at the reference's classes; the default palettes are cleared, so no utility introduces a value outside the token set.

**Components.**

| Pattern | shadcn/ui | Notes |
| --- | --- | --- |
| Application shell and navigation | Sidebar collapsible to icons (the rail), Collapsible (*Menu lengkap*), Sheet or Drawer (the phone's *Lainnya*), Dropdown Menu and Avatar (the account), Tooltip | R1's entries (GD-03); the phone bottom bar is custom — no primitive — with the olive current mark (GR-02) |
| Search and scan | Command in a Dialog; Input, Input Group and Kbd | "Cari atau pindai kode", exact lookup first (D-IA-04; PF-19); the scan dock built in Stage 2 (PT-20) |
| Buttons | Button — default, outline, ghost, secondary for the sunken button, destructive —, with accent and sign-in variants added; Button Group with Dropdown Menu for the split action | One primary per region |
| Fields and forms | Field, Label, Input, Input Group, Textarea, Select, Native Select, Combobox, Checkbox, Radio Group, Date Picker (Popover and Calendar), Input OTP | Control edge `#898982`; 16 px text on touch |
| Status and tags | Badge; the status text custom (dot and label) | One dimension per badge, never colour alone |
| Cards, key figures, facts | Card, Separator | The KPI tile without delta or sparkline (VP-3) |
| Lists and tables | Table and the Data Table guide, Item; a "Muat Lagi" Button; Empty, Spinner, Alert | Position numbers; no Pagination, no total (PF-15); no Skeleton |
| Tabs and views | Tabs; Toggle Group | The selected segment with an olive mark (GR-02) |
| Stage track and stage navigator | Custom — shadcn/ui has no stepper | The reference's markers; the current one ringed in olive (GR-03) |
| Overlays | Dialog, Alert Dialog, Sheet, Drawer, Dropdown Menu, Popover, Tooltip | Application-level confirmations; sheets on phones |
| Feedback | Toast (Base UI) or Sonner (Radix), Alert | A toast never the only place of a message (GR-15) |
| Document composer and paper | A custom paper renderer with Card, Tabs or Toggle Group and Dialog | VD-07's preview share; GAP-053 |
| Charts | Chart, always with a data table | The ageing chart only (VP-6) |
| Upload | Attachment (in the index; its fit checked in Stage 2), or a file Input in a Field | Types and limits from FL-02 |

## 6. Draft tokens

The reference's key foundation values, each with its token name, read from `assets/css/tokens.css` at v1.0.0 (frozen-list SHA-256 `d6ded788fc73127e47f1247814ca66df96c4c9e5f79f0c2a3c9ddbc7a4ab252c`), with the corrections of the [reconciliation](evidence/P7_REBASELINE_GATE.md#c1-closure--golden-frontend-reference-reconciliation) a). Contrast is computed with the WCAG 2.2 relative-luminance formula: text at least 4.5:1 (1.4.3), indicators at least 3:1 (1.4.11).

**Colour roles**

| Role | Token | Value | Contrast |
| --- | --- | --- | --- |
| Canvas · surface · sunken · warm | `--color-canvas` · `--color-surface` · `--color-surface-sunken` · `--color-surface-warm` | `#f7f8f3` · `#ffffff` · `#f2f3ee` · `#fdfcf4` | — |
| Ink — primary · strong · secondary | `--color-text-primary` · `--color-text-strong` · `--color-text-secondary` | `#0f1014` · `#3a3b42` · `#55565c` | 19.01 · 11.15 · 7.31 on white; secondary 6.85 on the canvas, 6.56 on sunken |
| Ink — tertiary | `--color-text-tertiary` | `#6b6d73` | 5.17 white, 4.85 canvas, 4.64 sunken; on lime 150 use `--color-text-tertiary-lime` `#696b71`, 4.59 |
| Muted and placeholder, per surface | `--color-text-muted` (white only) · `--color-text-muted-canvas` · `--color-text-muted-tint` · `--color-text-placeholder` | `#75767a` · `#717276` · `#6d6e72` · `#75767a` | 4.54 white · 4.50 canvas, 4.67 warm · 4.57 sunken, 4.50 on `#f1f1ef` · 4.54 white |
| Disabled | `--color-text-disabled` | `#9b9da1` | Inactive only |
| Control edge (GR-01) | new — the field border's hue at 3:1 | `#898982` | 3.52 white, 3.30 canvas, 3.16 sunken, 3.42 warm |
| Dividers and card edges | `--color-border-subtle` · `--color-border` | `#f0f1ef` · `#e4e4e2` | Decorative only, never a control's edge |
| Focus and indicators | `--color-focus` = `--brand-accent` | `#545e21` | 7.00 white, 6.56 canvas, 6.28 sunken, 6.03 lime 150 |
| Primary · deep | `--brand-primary` · `--brand-primary-deep` | `#1a5035` · `#0e3b25` | White label 9.36; totals 12.57 on white |
| Accent text and links | `--brand-accent-strong` · `--brand-link-soft` | `#353e1c` · `#5a643c` | 11.31 · 6.32 on white |
| Lime tints — 50, 100, 150 (selected entry), 200 | `--brand-tint-50` · `-100` · `-150` · `-200` | `#f5f9df` · `#f0f5ca` · `#ecf1d2` · `#e4ebc2` | Fills under primary ink, 15.36–17.65; never a state's only mark |
| Signal — the current step | `--brand-signal` | `#bbdf06` | Its number 12.66; ringed in olive, 7.00 (GR-03) |
| Sign-in CTA | `--brand-cta` · `--brand-cta-ink` | `#bdca88` · `#0b1005` | 10.97 |
| Destructive | `--color-danger-solid` | `#b4373f` | White label 5.90 |

**Status tones** — a word with a dot or icon, never colour alone:

| Tone | Tint · ink · dot | Ink on tint |
| --- | --- | --- |
| Success | `#e3f4e9` · `#236b49` · `#319664` | 5.62 |
| Warning and attention | `#fff1e2` · `#8a4a0e` · `#d98a3d` | 6.17 |
| Pending | `#fef1d8` · `#7a5a22` · `#cb9d4e` | 5.67 |
| Danger | `#fde7e8` · `#b4373f` · `#d0454c` | 4.99 |
| Info | `#e2ecf4` · `#0f2b4f` · `#5b8db5` | 11.86 |
| Neutral and inactive | `#f2f3ee` · `#3a3b42` · `#9b9da1` | 9.99 |

The ageing palette, ordered by severity (VP-6): `--chart-age-1`–`--chart-age-5`, `#99b1a0` `#edd798` `#cbac8d` `#d56e5b` `#a9322e`, always with direct labels, amounts and a table.

**Type** — Inter, the reference's typeface: version 4.1 (release `v4.1`, published 2024-11-16), by The Inter Project Authors, under the SIL Open Font License, Version 1.1, with no Reserved Font Name; official sources <https://rsms.me/inter/>, <https://github.com/rsms/inter> and the licence <https://raw.githubusercontent.com/rsms/inter/v4.1/LICENSE.txt>, accessed 2026-10-13. A variable font with an optical-size axis; the reference sets the text cut at every size. Self-hosted under SECURITY WS-08's `font-src 'self'`, with no recurring cost; no font file enters the repository before an authorized implementation Task (GD-05). Weights 400, 500, 600 and 700; tabular figures for numbers.

| Role | Desktop (canvas values; runtime text unit .92–.96 above 12 px) | Phone |
| --- | --- | --- |
| Page title | `--text-title-*` 32 / 40, 700 | `--m-text-title` 25 / 31 (26 / 32 from 400 px) |
| Section heading · card title | `--text-heading-*` 20 / 28 · 16.5–21 px, 600–700 | `--m-text-heading` 18 / 24 · `--m-text-row` 15 / 20 |
| Figures | `--text-figure-xl-*` 28 / 36 · `--text-figure-lg-*` 22 / 28 | `--m-text-figure` 17 / 22 · total 22 |
| Body | `--text-body-*` 14 / 20 · large 15 / 22 | `--m-text-body` 15 / 22 |
| Label · caption · micro | 13 / 18 · 12 / 16 · 11 / 16 (the 10.5 px pill excluded) | 13 / 18 · `--m-text-meta` 12.5 / 17 |
| Sign-in | display 44 / 50 at the canvas, fitted at runtime; title 35; fields 15.5; CTA 18 | 52 px controls; text fields 16 px on touch |

**Spacing** — `--space-*`: a 4 px base with a 2 px step — 2, 4, 8, 12, 16, 20, 24, 32, 40, 48, 56, 64, 72; card padding 20 px (phone 18); 12 px between cards (phone 14); 28 px between sections on phones; 8 px from a label to its control.

**Shape and depth** — `--radius-*`: 4, 6, 8, 10 and 12 px and a pill; cards 10, controls about 0.18 × their height, menus 10 with 6 px highlights, dialogs 12, sheets 16 on the top corners. Shadows: cards `0 1px 3px` at 4 %, the border carrying the edge; menus, toasts, dialogs, drawers and the palette with their own overlay shadows; the scrim `rgba(15,16,20,.32)` with an optional 4 px blur behind the palette; phone bars opaque (GR-22).

**Sizes** — desktop controls 32, 36 and 40 px and the 42 px top-bar controls, lowered by the density scale to floors of 28, 30, 32 and 34 px on short laptops; sign-in controls 55–56 px. Phones: fields and primary buttons 48 px, every other target at least 44 px, rows at least 60 px, bottom-bar items at least 60 × 60 px, sheet rows 52 px. Warehouse and field tasks: every control 48 px (GR-07). A coarse pointer: at least 44 px targets at every width. No desktop target below 24 × 24 px.

**Icons** — the reference's outline style: boxes of 12–32 px, strokes of 1.75 px at 20–24 px, 1.5 px at 16–20 px and 1.25 px at 12–14 px, round caps and joins, `currentColor`; icon wells of 32–56 px. The draft set stays Lucide (ISC licence, <https://github.com/lucide-icons/lucide>, as VC1 recorded it on 2026-10-05), styled to these strokes; the reference's own sprite is not copied (GR-18).

**Motion (VP-8)**

| Token | Value | Use |
| --- | --- | --- |
| `--mc-dur-instant` · `--mc-dur-hover` · `--mc-dur-fast` · `--mc-dur-state` | 80 · 140 · 160 · 200 ms | press, hover, state morphs, selection, component states |
| `--mc-dur-base` · `--mc-dur-moderate` · `--mc-dur-sheet` · `--mc-dur-slow` | 240 · 320 · 320 · 400 ms | item reveal and page exit, dialogs and disclosures, bottom sheets, the navigation indicator and panel reveals |
| `--mc-dur-auth-compact` · `--mc-dur-auth-step` | 480 · 600 ms | the sign-in's step on narrow screens and on desktop |
| `--mc-ease-standard` · `-enter` · `-reveal` · `-exit` | `(.42,0,.58,1)` · `(0,0,.2,1)` · `(.25,1,.5,1)` · `(.37,0,.63,1)` | persistent moves, entrances, reveals, exits |
| Stagger and budget | 30 / 40 / 60 ms, at most 300 ms per group; data-ready to the last element ≤ 900 ms | restrained entrances |
| `--mc-dur-reduced` | 150 ms, linear | under `prefers-reduced-motion: reduce`: no translate, scale, blur, clip, parallax, stagger or rolls; a crossfade; indicators jump |

No count-up, no emphasis curve, no chart reveal, no looping, nothing tied to scrolling; focus is never animated and no transition delays focus or input.

## 7. Draft responsive rules

- **Phone, up to 767 px:** a 56 px top bar with the contextual back action and the page title (AGENTS.md rule 12); R1's bottom bar — the Owner's *Beranda* · *Proyek* · *Cari* · *Lainnya*, the Admin's *Pekerjaan* · *Proyek* · *Cari* · *Lainnya*, each entry present only when the account may open it — in the reference's item anatomy, 60 px plus the safe area, the current item with an olive mark; *Lainnya* as the reference's drawer or a full-height sheet (GR-11); one column, sections disclosed by priority; tables as rows; selects, dialogs and drawers as bottom sheets, the palette as a top sheet; one sticky action region above the bottom bar, the bar hidden on a focused scan step whose scan dock and action bar sit at the bottom.
- **Tablet, 768–1,024 px:** the icon rail, 80 px, with the drawer; tables tighten their cells before becoming rows; multi-line editors as item cards up to 1,023 px.
- **Desktop, from 1,025 px:** R1's sidebar — the primary entries and the collapsed *Menu lengkap* —, 264 px at the reference canvas and at least 220 px at runtime, collapsible to the rail with Ctrl B; the top bar with "Cari atau pindai kode", "Buat" and the account menu; the reference's composition from 1,200 px; content no wider than its archetype allows, 1,680 px at most; one density at browser zoom 100 %.
- **Size follows the input:** a coarse pointer gets targets of at least 44 px at any width, warehouse and field tasks 48 px; text fields are 16 px on touch devices.
- **Reflow and long text:** pages reflow at 320 px without horizontal page scrolling (WCAG 2.2 1.4.10; GR-05), a data table scrolling only inside its own frame; titles and meta wrap, single-line values truncate with the full text available, record numbers never truncate; codes such as racks and SKUs never break at their hyphens.

### Alignment specification (OC-09)

Kept from R1 and VC1, with the reference's values.

- One control height per density — 36 px for fields and default controls, 40 px for page actions, 32 px inside cards, each with its density floor; 48 px on phones and in warehouse and field tasks; 55–56 px on the desktop sign-in, 52 px on the phone's.
- A column grid for multi-line editors with stated proportions; for the purchase line the reference's alignment rule — text columns aligned left on the field text, the quantity centred on its field, money right on the digits, each header derived from its field's geometry so that it moves with its values, the split following the table's own frame through a container query; Stage 2 states the proportions on the reference's geometry, VC1's being superseded with its tokens. Every control of a row in one grid row, so tops, bottoms and baselines coincide.
- Numbers right-aligned with tabular figures, in inputs, cells and summaries.
- Labels above controls with one 8 px gap — the reference's; supporting text below controls with the same gap.
- Secondary metadata — SKU, project item, available stock — on its own line below the control row, never shifting the row's baseline.
- Less-common fields behind "Rincian lain".
- The dense mode only on the named screens: the multi-line editors of quotations and purchases, *Riwayat Stok*, *Mutasi Bank*, *Laporan*, *Laporan Gabungan*, *Riwayat Aktivitas* and *Log Keamanan*.
- A summary beside or below the form it totals, so the line editor keeps the full width; a long form keeps its total and its one primary action in a bar fixed at the bottom of the view.

### Copy rules (OC-06)

Kept from R1.

- A heading, a state or number and an action; a helper sentence only when the user needs it to decide or act now — otherwise reduced, folded into a disclosure or removed; no explanatory sentence visible by default.
- Mandated copy stays visible and concise: AU-04's and H7-01's generic answers, H7-16's advice, H7-11's confirmation, IP-16's file line with FL-02's types and limit, and the MSG texts ADMIN_FLOW requires.
- No purpose lines on headings, task rows or tiles; the page states its purpose through its title and its first actionable element.
- Labels and states in plain Indonesian, with English available (CAP-14); numbers with their unit; counts per company, never summed for an Admin.
- The reference's component micro-copy is never normative wording (GP-5).

## 8. Lint findings and dispositions

**Superseded — kept as history.** The entries below record `dpai design lint` runs on the DesainPakeAI project's design system during Stage 1, the C1 rework and the C1 visual checkpoint. DesainPakeAI is discontinued for design work (GD-06), so no lint applies to this Golden-based draft; the differences between the reference and the repository, and their resolutions, are the reconciliation's [conflicts](evidence/P7_REBASELINE_GATE.md#conflicts). In the table, "the reference" is the DesainPakeAI project's Ramp design system, as the revisions that wrote it named it — not the Golden Frontend Reference.

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

## 9. Stage 3 completion — the record and where each element is final

AICWDF §18.12's elements, each with the section of this record that holds it and the Stage 3 draft where it is final — a REVIEW draft until APPR-011 ([DESIGN_SYSTEM](DESIGN_SYSTEM.md), [INFORMATION_ARCHITECTURE](INFORMATION_ARCHITECTURE.md), [LOCALIZATION](LOCALIZATION.md)):

| Element (AICWDF §18.12) | This record | Final in Stage 3 |
| --- | --- | --- |
| Reference source | §1 — the Golden Frontend Reference v1.0.0, vendored with its checksums in the private prototype repository; the frozen lab read-only | DESIGN_SYSTEM §2 |
| Relevant screens | §2 — the reference's pages and components the journeys use | the 65 pages of INFORMATION_ARCHITECTURE §8, each with its pattern (DESIGN_SYSTEM §9.6, §10); the prototype's PS-01–PS-53 in both languages as the applied evidence at its commit D |
| Extracted principles | §3 | DESIGN_SYSTEM §§3, 5–8 — the reference's foundations with the Owner's choices: GR-16's variant B, the card direction, the reference's motion recipes (D-DS-10, D-DS-15, D-DS-16) |
| Intentionally not copied | §4 | DESIGN_SYSTEM §1 and §4 — with the CP-A decisions: no sparkline or delta (OQ-17, OQ-18: not part of V1), D1 and D2 reverted, no hero, no marketing copy, no invented content in the reference's empty slots (VP-3), the prototype's simulation marker and review panel absent (Annex A) |
| shadcn/ui mapping | §5 (draft) | DESIGN_SYSTEM §10 with DESIGN_SYSTEM_MAPPING §3, normative there; the primitive library — Base UI or Radix — P11's under DEP-07 |
| Tokens | §6 (draft) | DESIGN_SYSTEM §5 — the reference's names and values in three layers, GR-01's control edge, GR-16's variant B |
| Responsive rules | §7 (draft), with the alignment specification and the copy rules | DESIGN_SYSTEM §§6 and 11; the copy rules LOCALIZATION §4 and DESIGN_SYSTEM §16 |
| The carry-forward of the pilot's evidence | — | DESIGN_SYSTEM Annex A (SP-66), nothing recorded as passed |

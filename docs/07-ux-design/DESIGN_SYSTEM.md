# Design system

Status: REVIEW | Updated: 2026-10-18 | Owner: Planning

**Draft of the P7 re-baseline — not normative before APPR-011; the approved revision stays in force.** Run PC-2 replaced this document in place on 2026-10-18 under [DIR-078](../00-governance/DECISION_LOG.md#dir-077-dir-078-obs-030-and-tech-037--p7-ux-re-baseline-cp-a-decisions-run-pc-2-with-p9s-drafting-baseline-and-drafts) SP-63, as Stage 3 of the P7 UX re-baseline drafts it: not finalized, its section skeleton kept so that every link into it still holds. Until the Owner's APPR-011 at OG-2 the approved revision — APPROVED under [APPR-008](../00-governance/DECISION_LOG.md#appr-008--p7-ux-information-architecture-and-design-system-approved), under replacement since DIR-035 D3 — stays in force; its SHA-256 is recorded in TECH-037, and Git holds its text. Run PC-3 completed it on 2026-10-18 under [DIR-080](../00-governance/DECISION_LOG.md#dir-079-dir-080-obs-031-and-tech-038--p7-ux-re-baseline-c2-evidence-and-owner-decisions-run-pc-3-stage-3-completed-stage-4-and-gate-p7) SP-71 and SP-73, with only the deltas the Owner's C2 decision requires ([DIR-079](../00-governance/DECISION_LOG.md#dir-079-dir-080-obs-031-and-tech-038--p7-ux-re-baseline-c2-evidence-and-owner-decisions-run-pc-3-stage-3-completed-stage-4-and-gate-p7)): D-DS-23 — UF-01's document layout, the input form on the left and the live preview on the right on the desktop (CD-04) — with the composer pattern of §10 and the draft preview of §14.3, and D-DS-18 confirmed for the save's reason the prototype cuts at 768 px; it stays a REVIEW draft, not normative before APPR-011.

**Sources.** Stage 2's drafts are reused, never re-derived (DIR-078 §0): [DESIGN_REFERENCES](DESIGN_REFERENCES.md) — the Golden-based draft, AICWDF §18.12's record — and [DESIGN_SYSTEM_MAPPING](stage2/DESIGN_SYSTEM_MAPPING.md) — the reference's tokens, components, patterns, states and responsive rules on shadcn/ui and Tailwind —; the approved revision for every rule they do not supersede; the Owner's CP-A decisions ([DIR-077](../00-governance/DECISION_LOG.md#dir-077-dir-078-obs-030-and-tech-037--p7-ux-re-baseline-cp-a-decisions-run-pc-2-with-p9s-drafting-baseline-and-drafts) CA-04 items 1–17); the deviation register's GR rules ([DEVIATION_REGISTER](stage2/DEVIATION_REGISTER.md)); [DOCUMENT_PREVIEW_PRODUCER](stage2/DOCUMENT_PREVIEW_PRODUCER.md), made normative in §14.3 (GAP-053); and the prototype's evidence at its commit D `70539fec8e40d8fb83b3fde8a8fa808d61b40d7d` — the pilot and its carry-forward ledger, dispositioned in Annex A (SP-66). The prototype is evidence, never a source of truth.

Authority: P7 — UX, Information Architecture & Design System, re-baselined under the Owner's decisions DIR-053–DIR-077 and drafted in Stage 3 by run PC-2 (DIR-078). This document owns **visual rules**: tokens, typography, spacing, breathing room and density (AICWDF §18.2), layout and breakpoints, iconography, motion, radius and elevation, the reusable patterns, the composition of shadcn/ui primitives into feature components, the status and state patterns, keyboard and scanner behaviour, the domain presentations, accessibility, copy formatting, print views and the anti-AI-slop rules. Presentation follows the Golden Frontend Reference v1.0.0, which adapts to MULTIPLECORP and never the reverse; behaviour follows MULTIPLECORP (decision 3; OQ-10; DIR-069).

**Anti-duplication contract:** business meaning stays in [V1_SCOPE](../01-product/V1_SCOPE.md), [DOMAIN_MODEL](../02-domain/DOMAIN_MODEL.md), [BUSINESS_RULES](../02-domain/BUSINESS_RULES.md) and [WORKFLOWS](../03-workflows/WORKFLOWS/README.md); authorization in [PERMISSIONS_MATRIX](../05-security/PERMISSIONS_MATRIX.md) and [SECURITY](../05-security/SECURITY.md); mechanisms in [CONCURRENCY_IDEMPOTENCY](../06-api-performance/CONCURRENCY_IDEMPOTENCY/README.md) and [PERFORMANCE](../06-api-performance/PERFORMANCE.md); navigation truth in [INFORMATION_ARCHITECTURE](INFORMATION_ARCHITECTURE.md) and [NAVIGATION_CONTRACTS](NAVIGATION_CONTRACTS.md); interaction truth in [ADMIN_FLOW](ADMIN_FLOW/README.md), [ROUTE_CONTRACTS](../03-workflows/ROUTE_CONTRACTS.md) and [INTERACTION_CONTRACTS](../03-workflows/INTERACTION_CONTRACTS.md); strings in [LOCALIZATION](LOCALIZATION.md). This document references them and restates none.

**Boundary:** NON-EXECUTABLE specification. Token tables, anatomy lists and pattern rules are design statements, not code: no CSS, Tailwind configuration, component source, image or font file is created here. shadcn/ui is the implementation foundation (OB §20); P11's UI Tasks name the rules of this document ([ADMIN_FLOW §16](ADMIN_FLOW/s16-17-handoff-traceability.md#16-handoff-obligations)).

## 1. Conventions

- **Identifier families defined here:** UXV (derived visual requirements), D-DS (design-system decisions), PT (patterns), KB (keyboard shortcuts), SLOP (anti-AI-slop prohibitions and their rules), and DA- (the dispositions of Annex A). GR (the carried items of the reference's reconciliation) is DESIGN_SYSTEM_MAPPING's and DEVIATION_REGISTER's; GL, PG, UXN and D-IA are INFORMATION_ARCHITECTURE's; NAV- NAVIGATION_CONTRACTS'; ROUTE- and INT- P3's; UXI, IP, J, MSG, UXS and UXH ADMIN_FLOW's.
- **Must** and **never** are normative for build units; a value given as a "starting value" may be tuned by P8 measurement without changing the rule.
- **Bands.** *Phone* means a viewport narrower than 768 px, whatever the input; *desktop* 1,025 px and wider, the reference's desktop band, where GR-16's text unit applies (§5.2); the band between them is §6's tablet rule. Bands follow the width alone; target size follows the input (§§5.3, 15).
- Colours are given in sRGB hex with their contrast ratio against the surface they are used on, computed with the WCAG 2.2 formula.
- **Examples** use the fictional group — CV Arunika Jaya (ARJ), PT Bentara Niaga (BTN) and CV Cakra Persada (CKP) — and no real company, person or account. Document numbers in examples are illustrative: a number's format is its company's numbering scheme (DATABASE `numbering_schemes`).
- **What the reference shows and MULTIPLECORP does not define** — a sparkline, a delta, an eyebrow, a marketing lead — is absent, never filled with invented content (VP-3; CA-04 item 13).

## 2. Foundation and source evidence

| Foundation | Use | Evidence (versions are evidence, not pins — DEP-07) |
| --- | --- | --- |
| **The Golden Frontend Reference v1.0.0** | the presentation reference (DIR-061 SD-02; GD-02; AK-1): its tokens, components, page patterns and motion recipes, adapted to MULTIPLECORP's behaviour and content | its five stylesheets as DESIGN_SYSTEM_MAPPING read them — `tokens.css` SHA-256 `d6ded788fc73127e47f1247814ca66df96c4c9e5f79f0c2a3c9ddbc7a4ab252c`, 424 custom properties, with `base.css`, `components.css`, `patterns.css` and `motion.css` —, vendored with their checksums in the private prototype repository; the frozen lab read-only |
| **shadcn/ui** | the primitive component system (OB §20; REF-054): copied into the codebase, owned and adapted there; primitives stay free of business logic (§10) | the documentation as DESIGN_SYSTEM_MAPPING records it to September 2026: theming through CSS variables exposed to Tailwind v4 with `@theme inline`; Base UI the default primitives, Radix supported — the choice P11's under DEP-07 |
| **Tailwind CSS** | the utility layer behind shadcn/ui | v4.3 documentation: theme variables, container queries, the `motion-reduce`, `print`, `forced-colors` and `pointer-coarse` variants |
| **Inter** | the one typeface, 4.1 — SIL Open Font License 1.1, no cost; self-hosted with the application, never from a font service (SECURITY WS-08: `font-src 'self'`) | its release and licence; `tnum` and `zero` for numbers and codes |
| **Lucide** | the icon set — 1.56.0, ISC licence (MIT for the icons derived from Feather), no cost; inline SVG components, no icon font | its release and licence |
| **WCAG 2.2** | the accessibility target at level AA (§15) | W3C Recommendation, 12 December 2024 |
| **The prototype** | evidence of the reference applied to MULTIPLECORP's six journeys, in both languages, for the Owner's checkpoints and the usability sessions — not a source of truth and not application code | the private repository at commit D (`70539fe`): GR-16's variant B the default, D1–D6 as decided, its evidence directories and its ledger (Annex A) |

No paid font, icon set, component licence or design service is adopted; any later one is an Owner decision ([COST_POLICY](../00-governance/COST_POLICY.md)).

## 3. Derived visual requirements

Carried from the approved revision; §§5–18 give each requirement its form on the reference.

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
| UXV-17 | Loading, skeleton, empty, error, denied, stale, CONFLICT, PENDING and FAILED states | OB §23; V1_SCOPE P7 handoff requirements; HO-01 | §12 — the loading state is PT-19's spinner, no skeleton (§9.6) |
| UXV-18 | Toast and feedback; the audit and activity timeline | OB §23 | §9 |
| UXV-19 | A print view is identical to its page | DP-19 | §17 |
| UXV-20 | Destructive actions, their confirmation and the correction and reversal patterns | OB §23; WORKFLOWS §8 | §9 |
| UXV-21 | Motion only for interaction feedback: subtle, fast, purposeful, and none under reduced motion | OB §22 | §8 |
| UXV-22 | Indonesian copy: tone, capitalization and punctuation conventions | V1_SCOPE language and experience contract; DIR-033 §10-A | §16 |

## 4. Design decisions

Each decision is Level 1 under DIR-033 §13. "Exploration" names the DesainPakeAI brief whose alternatives were compared ([P7_QUALITY_GATE](evidence/P7_QUALITY_GATE.md#desainpakai-exploration)); a decision without one follows from repository rules alone.

| ID | Decision | Alternatives rejected | Grounds · exploration |
| --- | --- | --- | --- |
| D-DS-01 | *Superseded at Stage 3 — see below.* The visual foundation is "Tinta": white surfaces, ink neutrals, one deep-blue accent, five tones bound to meanings, flat colour, hairline rules, 4 px and 6 px radii | "Kertas": a second typeface and looser spacing that cost density; "Batu": the framework's default indigo with tinted pills and alert fills — the generic-template look of SLOP-37; the tool's starter design: an acid-lime accent at 1.23:1 on white, display type up to 56 px and a chat composer, with 19 findings from `dpai design lint` | UXV-02, UXV-03, UXV-08; WCAG 1.4.3, 1.4.11 · DPB-01 |
| D-DS-02 | One typeface, Inter, self-hosted; seven text styles from 12 to 20 px; tabular figures and slashed zero for numbers and codes; 16 px in touch inputs | a display or serif face beside it: one more font to load for no task value; the system font stack: figures and widths differ between devices, which breaks column alignment | WS-08 (`font-src 'self'`); SLOP-26; UXV-12 · DPB-01 |
| D-DS-03 | *Superseded at Stage 3 — see below.* One density: compact with a fine pointer, touch-sized with a coarse pointer; no density setting | a user-selectable density: more states to design and test for no V1 need (PN-7); one size for both: slow desktop work or touch targets below 44 px | PN-7; UXV-10; WCAG 2.5.8 · DPB-01, DPB-17 |
| D-DS-04 | A state is a bordered rectangular badge: label, icon shape and tone; never a filled pill, dot or row tint alone | tinted pills ("Batu"): colour-dependent and pill-shaped (SLOP-05); text alone: slower to scan in dense tables | UXV-08; WCAG 1.4.1; UXS-42 · DPB-01, DPB-16 |
| D-DS-05 | *Superseded at Stage 3 — see below.* No chart on any V1 screen: every figure is a CALC value or a QS signal shown as text and tables | charts on Beranda and reports: no V1 decision needs a trend picture, and decorative analytics is SLOP-34 | V1_SCOPE CAP-11, CAP-17; SLOP-18, SLOP-34 · DPB-03, DPB-13 |
| D-DS-06 | An outcome message is part of the sticky action region of the form or dialog that sent the command — directly above its buttons, the region growing to hold it — so the answer is in view whenever the buttons are; for CONFLICT and FAILED focus moves to it (PT-14) | a banner at the top of the form: out of view at the bottom of a long form and on a phone; a toast: it disappears and cannot hold a decision (WCAG 2.2.1) | HO-01; UXV-17; WCAG 4.1.3 · DPB-16 |
| D-DS-07 | Multi-line entry on desktop is a table of form rows — Tab moves between cells, Enter in the last cell opens the next row, one empty row always at the end, line details that do not fit open under their row; on a phone each line is a row that opens an editing sheet (PT-10) | read-only rows edited one at a time on desktop: one more keystroke per line for thirty lines (UXS-35); a summary column beside the lines: every line wraps to two rows; stepped sections: more clicks and step badges that read as progress; inline cells on a phone: they overflow 390 px | UXV-06, UXV-07; V1_SCOPE (no spreadsheet clone) · DPB-07, DPB-17 |
| D-DS-08 | On a phone the scan field of a scan step is fixed at the bottom, above the action bar (PT-20) | at the top of the screen: out of thumb reach while the line list scrolls | GAP-020; UXV-10 · DPB-09 |
| D-DS-09 | The capability-by-company preview marks each cell *Dapat*, *Baru*, *Dicabut* or empty, and marks the capabilities a removed prerequisite disables "Nonaktif — prasyarat dicabut", keeping their grants (PT-30; RG-06) | a plain yes/no matrix: it does not show what the save changes | H7-06; GAP-032 · DPB-14 |

**Stage 3's dispositions of D-DS-01–D-DS-09** ([NON_LOSS_MATRIX](stage2/NON_LOSS_MATRIX.md) section O): D-DS-01 superseded by the reference's tokens (§5); D-DS-02 adapted — Inter, self-hosted, tabular figures and 16 px touch inputs on the reference's scale (§§5.2, 7); D-DS-03 superseded — the reference's density with OC-09's named dense screens (§5.3); D-DS-04 adapted — label, icon shape and tone in the reference's pill (§12.1); D-DS-05 superseded — a chart only of defined values, *Beranda*'s and the report's ageing chart (VP-6; OC-08); D-DS-06–D-DS-09 kept.

**Stage 3's decisions** — the Owner's CP-A answers and the rules Stage 3 draws from them; each is Level 1 within Golden's scales unless the Owner decided it (TECH-037 lists the notable ones):

| ID | Decision | Alternatives rejected | Grounds |
| --- | --- | --- | --- |
| D-DS-10 | GR-16's variant B: from 1,025 px the desktop text unit `--tu` is fixed at 1 px, so body text stays at 14 px and no size shrinks; the sidebar's brand keeps the reference's own size beside the placeholder mark (GF-2 BR2-07) | variant A, the reference's `clamp(.92px, …, .96px)`, which renders 14 px body text at about 12.9 px at 1,366 px | the Owner's choice, `DIR-077` CA-04 item 1; OQ-15 |
| D-DS-11 | MSG-29 stays one message in the notice's body — D1 reverted | MSG-29 split into the reference's title and meta slots | `DIR-077` CA-04 item 2 |
| D-DS-12 | "Buat" keeps its visible label from 768 to 1,024 px — D2 reverted | the reference's icon button in that band | `DIR-077` CA-04 item 3; R1 |
| D-DS-13 | The receipt's result card follows the reference's floor-task result, as corrected (D3, TB-D01) | CP-1's centred card | `DIR-077` CA-04 item 4 |
| D-DS-14 | A task line's title, meta and business date sit in its card's head (D4, TB-D02); "Berikutnya" is the reference's floor badge (D5, TB-D03); MSG-01's lead is the notice's title with no colon (D6, TB-D04), its punctuation LOCALIZATION §5's | their earlier forms | `DIR-077` CA-04 items 5–7 |
| D-DS-15 | **The card direction** (DIR-073 (3), reading (a)): a card is compact and content-driven — its dimensions follow its canonical content, the reference's typography, spacing, padding, alignment, radius and hierarchy kept; **option B** for the KPI cards and the context card, as built; shown to the Owner at APPR-011 | option A — the reference's card dimensions with the lesser canonical content's empty space accepted (measured more empty and moving *Beranda*'s attention list below the fold at 1,440 × 900) | `DIR-077` CA-04 item 12; the prototype's `sp50.txt` §§5.1, 5.9 |
| D-DS-16 | **Motion is the reference's recipes**: every motion the application shows is one of `motion.css`'s, on a MULTIPLECORP state change (§8); a motion the pilot added is not part of V1; the ledger's raw differences close by this rule (Annex A, DA-04) | reworking the prototype's motion | `DIR-077` CA-04 item 11 (CQ-7); VP-8 |
| D-DS-17 | **Reduced motion**: under `prefers-reduced-motion: reduce` no movement — no translate, scale, rotation, height rise or chevron turn, the reference's own included —; a 150 ms crossfade at most; indicators jump (§8) | keeping the reference's chevron and select turns, which hold their 160 ms under reduced motion | DESIGN_SYSTEM_MAPPING §2; Annex A, DA-12; WCAG 2.3.3 read under VP-8 |
| D-DS-18 | **Mandated copy is never cut**: a select's value, a form bar's reason, a read-only value and a label wrap under WCAG 2.2 1.4.12's text spacing instead of the reference's ellipsis, one line at its height otherwise (§15); it governs the save's reason that the prototype cuts at 768 px at normal spacing — run PC-2's observation (TECH-037) —: in the application that reason wraps inside its bar, the prototype staying as it is (`DIR-080` SP-73) | the reference's ellipsis | GR-35; LOCALIZATION §3.7; the prototype's commit D |
| D-DS-19 | **Targets**: 24 × 24 CSS px at least with a fine pointer; 44 px with a coarse pointer at every width; 48 px for the floor task's operational controls (§§5.3, 15) | one size for every input | WCAG 2.5.8; GR-07; CQ-5 (DIR-071) |
| D-DS-20 | **The draft preview** is composed in the browser from the page's own props with the document layout's template, marked "DRAF — belum terbit" (§14.3) | a non-persistent server preview, or both | GAP-053; DOCUMENT_PREVIEW_PRODUCER option A |
| D-DS-21 | FA-20, FA-21 and FA-22 are rules (§15) — the current step's position inside its step, each worklist group with its own heading, an empty search saying so | — | `DIR-077` CA-04 item 16 |
| D-DS-22 | The D refinements not adopted — WF-2R's BR2R-14 and MS2R-15–MS2R-17, WF-PC1's D findings other than FA-20–FA-22 — are dropped as prototype refinements; one may come back only as a Level 1 choice within Golden's scales that changes no behaviour, content or copy meaning | implementing them now | `DIR-077` CA-04 items 15–16 |
| D-DS-23 | **The document layout on the desktop (UF-01).** On the desktop band, from 1,025 px, every page that creates or edits a generated business document — PG-014, PG-033, PG-036, PG-042, and PG-043 for a draft — shows the input form on the left and the live preview of the document on the right, side by side: the default layout, never a tab. The preview keeps VD-07's share, about 40–45 % of the workspace, and stays sticky with "Pas lebar", "100%" and "Layar penuh"; from 1,280 px it takes the reference's composer within that share (GR-10). From 1,025 to 1,279 px the sidebar opens as the rail — Ctrl B still expands it — and the preview takes the share's lower end, 40 %, the paper fitted to its column ("Pas lebar"), so the split holds at every desktop width (Level 1) — the rail being the shell's own collapsed state, which the page opens with at those widths and Ctrl B expands, the workspace then narrower and the preview keeping its 40 %; the state is not remembered across pages (§9.1; §10). The form's column takes the rest, about 540 px at 1,025 px, and keeps every input; the multi-line editor keeps the form column's full width, its summary below it, and scrolls inside its frame when it is narrower than its lines (PT-10). Below 1,025 px "Rincian \| Pratinjau" and the phone's Edit → Preview → Finalize, the preview full-screen (DC-11), as drafted. The preview stays DOCUMENT_PREVIEW_PRODUCER's option A (D-DS-20): composed in the browser from the page's own props, no server render of a draft, no number, and no route, query or prop beyond those PJ-15 and PJ-19 allow (§§10, 14.3). The pages that issue a document from recorded facts — PG-016's PO, PG-022's *Nota Terima Barang*, PG-024's *Surat Jalan*, PG-031's BAST and inspection record, PG-043's pre-payment *Kuitansi* — hold no draft or preview, so SP-71's list leaves them out; whether VD-07's preview comes before their issue is [OQ-22](stage2/OWNER_QUESTIONS.md#oq-22--documents-issued-from-recorded-facts) | "Rincian \| Pratinjau" below 1,280 px, the reference's composer threshold; the preview behind a tab or a toggle on the desktop | the Owner's finding UF-01, `DIR-079` CD-04; VD-07; DOCUMENT_PREVIEW_PRODUCER option A |

## 5. Tokens

**Three layers, one source** (Level 1). The application keeps the reference's token names and values as CSS custom properties — the `--brand-*` set remaining the only one a project re-tints —; the shadcn/ui theme variables point to them; and the Tailwind CSS theme keys expose them to utilities. Tailwind's theme variables "aren't _just_ CSS variables": a key in a namespace generates utilities, and a theme variable that references another variable belongs in `@theme inline` (Tailwind v4.3, *Theme variables*). So a token whose name already is a Tailwind key — `--color-canvas`, `--radius-lg`, `--shadow-card`, `--font-sans` — is declared once in `@theme` with its literal value, and is both; a key that points to another variable, as shadcn/ui's `--color-primary: var(--primary)`, sits in `@theme inline`. Every default namespace the tokens replace is cleared with `initial` — `--color-*: initial` removes "all of the default utilities that use that namespace" (Tailwind v4.3) —, so no utility brings a value outside the token set. Values stay the reference's hex; a conversion to OKLCH, the form shadcn/ui's default theme uses, is optional and keeps the computed colour. Sizes are written in rem at a 16 px root, the desktop text unit kept as a factor ([GR-06](evidence/P7_REBASELINE_GATE.md#carried-items-gr-01)). How the keys are generated and linted is P11's — needs verification at implementation.

**GR-16** (D-DS-10): the reference's desktop text unit `--tu` is `clamp(.92px, …, .96px)` from 1,025 px; MULTIPLECORP fixes it at 1 px there — variant B, the Owner's choice at CP-A (`DIR-077` CA-04 item 1) —, so 14 px body text stays 14 px and no size shrinks. The 12, 11 and 12.5 px sizes are fixed; the 10.5 px pill size is excluded (GR-16).

### 5.1 Colour

**The shadcn/ui theme variables.** The variables of shadcn/ui's theming page — surfaces and text, actions and emphasis, muted content, form and focus, charts, the sidebar set and `--radius` —, each pointing to a token; a new colour is added with its `-foreground` pair and exposed as `--color-` keys in `@theme inline` (shadcn/ui, *Theming*).

| shadcn/ui variable | Token | Value |
| --- | --- | --- |
| `--background` · `--foreground` | `--color-canvas` · `--color-text-primary` | `#f7f8f3` · `#0f1014` |
| `--card`, `--popover` · their `-foreground` | `--color-surface` · `--color-text-primary` | `#ffffff` · `#0f1014` |
| `--primary` · `--primary-foreground` | `--brand-primary` · `--color-text-on-primary` | `#1a5035` · `#ffffff` |
| `--secondary` · `--secondary-foreground` | `--color-surface-sunken` · `--color-text-primary` — the sunken button | `#f2f3ee` · `#0f1014` |
| `--muted` · `--muted-foreground` | `--color-surface-sunken` · `--color-text-tertiary`, 4.64:1 on sunken | `#f2f3ee` · `#6b6d73` |
| `--accent` · `--accent-foreground` | `--color-row-press` · `--color-text-primary` — menu and row highlights | `#f2f3ec` · `#0f1014` |
| `--destructive` | `--color-danger-solid` | `#b4373f` |
| `--border` | `--color-border-subtle` — dividers and card edges, never a control's edge | `#f0f1ef` |
| `--input` | the control edge of GR-01, a new token `--color-border-input` (proposed name) | `#898982` |
| `--ring` | `--color-focus`, which is `--brand-accent` | `#545e21` |
| `--chart-1`–`--chart-5` | `--chart-age-1`–`--chart-age-5`, in order of severity (VP-6; GR-23) | `#99b1a0` `#edd798` `#cbac8d` `#d56e5b` `#a9322e` |
| `--sidebar` · `--sidebar-foreground` | `--color-surface` · `--color-text-primary` | `#ffffff` · `#0f1014` |
| `--sidebar-primary` · `--sidebar-primary-foreground` | `--brand-accent` — the selected entry's indicator — · `--color-text-on-primary` | `#545e21` · `#ffffff` |
| `--sidebar-accent` · `--sidebar-accent-foreground` | `--brand-tint-150` — the selected entry's fill — · `--color-text-primary` | `#ecf1d2` · `#0f1014` |
| `--sidebar-border` · `--sidebar-ring` | `--color-border-subtle` · `--color-focus` | `#f0f1ef` · `#545e21` |
| `--radius` | the card radius, `--radius-lg`; the steps set to the reference's values rather than derived, since shadcn/ui's derived scale (0.6×, 0.8×, 1×, 1.4×) gives 14 px where the reference has 12 | 10 px (0.625rem) |
| Added, each with its pair where it has one | the status tones `--color-{success,warning,pending,danger,info,neutral}-{bg,fg,dot}`; the lime tints, the signal and the sign-in CTA; the category tints `--tint-*` | §5.1's token table |

**Colour tokens.** Each name is kept as the application's CSS custom property; the last column gives the Tailwind key, "CSS only" where no utility needs one. A token excluded here is not used by any journey screen or by the application.

| Tokens and values | Role | Disposition | Tailwind key |
| --- | --- | --- | --- |
| `--color-canvas` `#f7f8f3` · `--color-surface` `#ffffff` · `--color-surface-sunken` `#f2f3ee` · `--color-surface-warm` `#fdfcf4` | the page canvas; cards, panels and popovers; tracks, sunken buttons and disabled fields; warm panels | ADOPT | the same names |
| `--color-row-hover` `#f8f9f4` · `--color-row-press` `#f2f3ec` · `--color-sunken-hover` `#e9eae4` · `--color-avatar` `#dfe1e3` | row hover and press; a sunken control's hover; the avatar disc | ADOPT | the same names |
| `--color-surface-hero-start` `#f1f1ef` · `--color-surface-hero-end` `#f3f5f5` | the greeting hero's tint | EXCLUDE — no hero (VP-2; [SLOP-19](#18-anti-ai-slop-mapping)) | — |
| `--color-border-subtle` `#f0f1ef` · `--color-border` `#e4e4e2` · `--color-border-strong` `#d8d8d6` · `--color-border-hover` `#d6d6d4` · `--color-header-divider` `#ecedec` · `--color-addon-divider` `#efefef` · `--color-separator` `#c6c6c4` · `--color-divider-label` `#dcdcdb` · `--color-border-dashed` `#e7e7e7` · `--color-card-hover-border` `#e3e4df` · `--color-card-press-border` `#dcddd7` · `--color-link-underline` `#c9cacd` | dividers, card edges, separators, their hover and press; a link's underline — decorative, never a control's identifying edge | ADOPT | the same names |
| `--color-border-control` `#e0e0de` · `--color-border-field` `#dededc` · `--color-border-field-dense` `#d7d7d5` · `--color-border-field-xl` `#d9d9d7` · `--color-border-icon-btn` `#e3e3e2` · `--color-border-toggle` `#e8e8e8` · `--color-border-kbd` `#e7e7e7` · `--color-border-field-hover` `#c8c8c6` · `--color-border-control-hover` `#cfcfcd` | the edges of fields, selects, header controls, icon buttons, toggles and keycaps, and their hover | ADAPT — the edge that identifies an input, select, date field or author-styled checkbox or radio is `--color-border-input` `#898982`: 3.52:1 on white, 3.15–3.42:1 on the canvas, sunken, warm and row surfaces and 3.03:1 on lime 150; it falls to 2.84:1 on lime 200, where no field is placed (GR-01) | the same names; `--color-input` |
| `--color-border-outline-xl` `#cdcec9` · `--color-border-outline-xl-hover` `#bebfba` | the sign-in's outline buttons | ADOPT; the Google button follows Google's own theme (GR-13) | the same names |
| `--color-text-primary` `#0f1014` · `--color-text-strong` `#3a3b42` · `--color-text-secondary` `#55565c` · `--color-text-tertiary` `#6b6d73` | the ink tiers — primary; links and status text; secondary; tertiary | ADOPT | the same names |
| `--color-text-muted` `#75767a` · `--color-text-muted-canvas` `#717276` · `--color-text-muted-tint` `#6d6e72` · `--color-text-tertiary-lime` `#696b71` · `--color-text-placeholder` `#75767a` | muted text on white, on the canvas, on tinted panels; tertiary on a selected lime row; placeholders | ADAPT — each grey only on its own surface, where it meets 4.5:1 (GR-04) | the same names |
| `--color-text-disabled` `#9b9da1` · `--color-text-on-primary` `#ffffff` · `--color-text-on-lime` `#0d0e00` · `--color-icon-field` `#262626` | inactive text; labels on the primary fill and on lime; icons inside fields | ADOPT — disabled ink never for text a user needs | the same names |
| `--brand-primary` `#1a5035` · `--brand-primary-rim` `#12452b` · `--brand-primary-hover` `#154530` · `--brand-primary-press` `#113c29` · `--brand-primary-deep` `#0e3b25`, aliased as `--color-primary`, `--color-primary-rim`, `--color-primary-hover`, `--color-primary-press`, `--color-primary-deep` | the one action colour, its edge, hover and press; the deep green of totals | ADOPT | the aliases, with shadcn/ui's `--primary` |
| `--brand-accent` `#545e21` · `--brand-accent-strong` `#353e1c` · `--brand-accent-link` `#4c572c` · `--brand-accent-soft` `#8b936d` · `--brand-link-soft` `#5a643c` · `--brand-link-strong` `#3d4624` · `--brand-link-rule` `#969c71`, with the aliases `--color-accent-olive`, `--color-accent-ink`, `--color-accent-link`, `--color-accent-underline` | olive — the navigation indicator, focus, selection marks; accent text and links; their rules | ADOPT; olive carries the 3:1 corrections (GR-01–GR-03) | the aliases; `--color-ring`, `--color-sidebar-primary` |
| `--brand-tint-50` `#f5f9df` · `--brand-tint-100` `#f0f5ca` · `--brand-tint-100-hover` `#e9f0b9` · `--brand-tint-150` `#ecf1d2` · `--brand-tint-200` `#e4ebc2` · `--brand-on-tint` `#556a08` · `--brand-nav-hover` `#f6f9eb` · `--brand-nav-press` `#f0f4dc` · `--brand-fill-rule` `#cfd6ad` · `--brand-fill-wash` `#fbfcf6`, with the aliases `--color-lime-50`, `--color-lime-100`, `--color-lime-100-hover`, `--color-lime-150`, `--color-lime-200`, `--color-lime-700` | lime fills under dark ink — the selected option and entry, the accent button; hover and press; fill rules | ADOPT as fills only — never text, a thin line or a state's only mark (SLOP-09; GR-02) | the aliases; `--color-sidebar-accent` |
| `--brand-signal` `#bbdf06`, aliased as `--color-lime-signal` | the current step only | ADAPT — the current marker ringed in olive (GR-03) | the alias |
| `--brand-cta` `#bdca88` · `--brand-cta-band` `#b0ba7d` · `--brand-cta-hover` `#b5c37e` · `--brand-cta-ink` `#0b1005` · `--brand-cta-rim` `#a9b676` · `--brand-cta-glow` `rgba(120,140,40,.28)` · `--brand-cta-shadow` `rgba(108,122,52,.22)`, with the aliases `--color-lime-cta`, `--color-lime-cta-band`, `--color-lime-cta-hover`, `--color-lime-cta-ink` | the sign-in's primary button — fill, band, hover, ink, edge, glow | ADOPT on the sign-in only (GA1 f)); its soft shadow kept (SLOP-08 revised) | the aliases |
| `--brand-focus-halo` `rgba(84,94,33,.35)` | a focused field's halo, beside its olive border | ADOPT | CSS only |
| `--brand-progress` `#a3b26a` · `--brand-step-done-line` `#e4e9cb` · `--brand-step-done-dot` `#dde7a6` | a process's progress; completed steps' line and disc | ADOPT — progress for processes only (SLOP-44), a completed step identified by its check; the fill is 1.87:1 on its track, so every bar shows its value as text beside it (1.4.11) | `--color-progress`, `--color-step-done-line`, `--color-step-done-dot` (proposed) |
| `--color-logo-teal` `#335f65` · `--color-logo-olive` `#84a64c` | the reference's MultipleCorp mark | EXCLUDE (GD-08; VP-5) | — |
| `--color-success-bg` `#e3f4e9` · `--color-success-fg` `#236b49` · `--color-success-dot` `#319664`; `--color-warning-bg` `#fff1e2` · `--color-warning-fg` `#8a4a0e` · `--color-warning-dot` `#d98a3d`; `--color-pending-bg` `#fef1d8` · `--color-pending-fg` `#7a5a22` · `--color-pending-dot` `#cb9d4e`; `--color-danger-bg` `#fde7e8` · `--color-danger-fg` `#b4373f` · `--color-danger-icon` `#9b2e30` · `--color-danger-dot` `#d0454c`; `--color-info-bg` `#e2ecf4` · `--color-info-fg` `#0f2b4f` · `--color-info-dot` `#5b8db5`; `--color-neutral-bg` `#f2f3ee` · `--color-neutral-fg` `#3a3b42` · `--color-neutral-dot` `#9b9da1` | the six status tones — tint, ink and dot; ink on its tint 4.99–11.86:1 | ADOPT — a state is a word with its icon (§5), never colour alone (D-DS-04; WCAG 1.4.1) | the same names; shadcn/ui pairs such as `--success` and `--success-foreground` added (proposed) |
| `--color-danger-solid` `#b4373f` · `--color-danger-solid-hover` `#9e2f36` · `--color-danger-rim` `#9b2e30` · `--color-required` `#cf4747` | the destructive button, its hover and edge; the required mark — 4.52:1 on white and 4.05–4.39:1 on the other surfaces, so it is decorative beside "(wajib)", which carries the state (PT-09; 1.4.1) | ADOPT | the same names; `--color-destructive` |
| `--color-trend-up-bg` `#edf8f1` · `--color-trend-up-fg` `#2d5e3e` | the delta badge | EXCLUDE (VP-3) | — |
| `--tint-olive` `#eaf0d9` / `--tint-olive-ink` `#1a2e0d` · `--tint-blue` `#e1ecf4` / `--tint-blue-ink` `#0f2b4f` · `--tint-peach` `#f6ede1` / `--tint-peach-ink` `#432817` · `--tint-mint` `#deefe9` / `--tint-mint-ink` `#032f11` · `--tint-red` `#fce8e6` / `--tint-red-ink` `#9b2e30` · `--tint-lavender` `#ede5fc` / `--tint-lavender-ink` `#341d5d` · `--tint-tan` `#f1e6dc` / `--tint-tan-ink` `#432817` · `--tint-lime` `#f5f9df` / `--tint-lime-ink` `#556a08` · `--tint-neutral` `#f2f3ee` / `--tint-neutral-ink` `#0f1014` · `--tint-blue-strong` `#d5e7f3` · `--tint-peach-strong` `#faece0` | the category discs, a tint with its ink | ADOPT as identity wells only, never decoration (VD-02) | `--color-tint-*` (proposed) |
| `--chart-age-1` `#99b1a0` · `--chart-age-2` `#edd798` · `--chart-age-3` `#cbac8d` · `--chart-age-4` `#d56e5b` · `--chart-age-5` `#a9322e` | the receivable-ageing ramp, by severity | ADAPT — always with direct labels, amounts and a data table (VP-6; GR-23) | `--color-chart-age-1`…`-5`; shadcn/ui `--chart-1`–`--chart-5` |
| `--chart-track` `#e6e8eb` · `--chart-segment-gap` 2 px · `--chart-line-width` 1.5 px · `--progress-height` 10 px | the bar's track and gaps; a line's width; the progress bar | ADOPT for the ageing bar and process progress | CSS only |
| `--chart-cat-1` `#7e9e85` · `--chart-cat-2` `#82adc9` · `--chart-cat-3` `#e6d6c5` · `--chart-cat-4` `#dadee0` · `--chart-progress-1` `#94a469` · `--chart-progress-2` `#89b3cd` · `--chart-progress-3` `#ccaf90` · `--chart-olive-soft` `#c5d1a3` · `--chart-area-opacity-top` 0.4 · `--chart-donut-ring-ratio` 0.16 | categorical, progress, area and donut charts | EXCLUDE (VP-6; GR-23) | — |

### 5.2 Typography

| Tokens and values | Role | Disposition | Tailwind key |
| --- | --- | --- | --- |
| `--font-sans` "Inter", "Inter Fallback", "Segoe UI Variable Text", "Segoe UI", system fonts · `--font-mono` ui-monospace, "SF Mono", "Cascadia Mono", Consolas, "Liberation Mono", … · `--font-variation-text` `"opsz" 14` · `--numeric-figures` `tabular-nums` | the family with its cap-height-matched local fallback; codes and keycaps; Inter's text cut at every size; tabular figures for numbers | ADOPT — Inter 4.1 self-hosted under [WS-08](../05-security/SECURITY.md#4-web-and-input-security)'s `font-src 'self'` | `--font-sans`, `--font-mono` |
| `--font-quote` · `--font-quote-serif` | quotations | EXCLUDE — no quote (VP-2) | — |
| `--weight-regular` 400 · `--weight-medium` 500 · `--weight-semibold` 600 · `--weight-bold` 700 | the four weights | ADOPT | `--font-weight-regular`, `-medium`, `-semibold`, `-bold` |
| `--text-display-size` 44 · `--text-display-lh` 50 px · `--text-display-track` −0.02em | the sign-in display only | ADOPT on the sign-in (SLOP-26) | `--text-display` with `--text-display--line-height` and `--text-display--letter-spacing` |
| `--text-title-size` 32 · `--text-title-lh` 40 · `--text-title-track` −0.02em; `--text-figure-xl-size` 28 · `--text-figure-xl-lh` 36 · `--text-figure-xl-track` −0.02em; `--text-figure-lg-size` 22 · `--text-figure-lg-lh` 28 · `--text-figure-lg-track` −0.01em; `--text-heading-size` 20 · `--text-heading-lh` 28 · `--text-heading-track` −0.01em; `--text-heading-md-size` 20 · `--text-heading-md-lh` 26 · `--text-heading-md-track` −0.04em; `--text-heading-lg-size` 21 · `--text-heading-lg-lh` 28 · `--text-heading-lg-track` −0.01em; `--text-heading-sm-size` 16.5 · `--text-heading-sm-lh` 20 · `--text-heading-sm-track` −0.03em; `--text-lead-size` 17 · `--text-lead-lh` 26; `--text-body-lg-size` 15 · `--text-body-lg-lh` 22 · `--text-body-lg-lh-para` 24; `--text-body-size` 14 · `--text-body-lh` 20; `--text-label-size` 13 · `--text-label-lh` 18 — px at the canvas | the desktop scale at the canvas, each size × `--tu` | ADOPT; GR-16's two variants (§1) | one `--text-{role}` key per role with its `--line-height` and `--letter-spacing`, Tailwind's form for a size's defaults (Tailwind v4.3, *Font size*) |
| `--text-caption-size` 12 · `--text-caption-lh` 16 · `--text-micro-size` 11 · `--text-micro-lh` 16 · `--text-pill-size` 12 · `--text-pill-sm-size` 11 · `--text-input-sm-size` 12 px | fixed small sizes, never shrunk | ADOPT; legibility judged at the preview (GR-16) | `--text-caption`, `--text-micro`, `--text-pill`, `--text-pill-sm` |
| `--text-pill-xs-size` 10.5 px | the smallest pill | EXCLUDE — below the reference's own 11 px floor (GR-16) | — |
| `--text-btn-sm-size` 13 · `--text-btn-md-size` 14 · `--text-btn-lg-size` 14 · `--text-btn-header-size` 14 · `--text-btn-secondary-size` 12.5 · `--text-btn-outline-xl-size` 17 · `--text-input-size` 14 · `--text-field-label-size` 13 · `--text-pill-lg-size` 13 · `--text-kbd-size` 12.5 · `--text-toggle-size` 15 | component text sizes, × `--tu` | ADOPT | CSS only, read by the components |
| `--text-auth-title-size` 35 · `--text-auth-title-lh` 42 · `--text-auth-lead-size` 17.5 · `--text-auth-lead-lh` 26.5 · `--text-auth-label-size` 16 · `--text-auth-label-lh` 22 · `--text-auth-input-size` 15.5 · `--text-auth-button-size` 18 · `--text-auth-helper-size` 15 · `--text-auth-helper-lh` 24 | the sign-in's own sizes | ADOPT on the sign-in; the lead without editorial copy (SLOP-20; GA1 f)) | CSS only |
| `--text-delta-size` 12 | the delta badge | EXCLUDE (VP-3) | — |
| `--m-text-title` 25 · `--m-text-title-lh` 31 (26 and 32 from 400 px) · `--m-text-heading` 18 · `--m-text-heading-lh` 24 · `--m-text-row` 15 · `--m-text-row-lh` 20 · `--m-text-body` 15 · `--m-text-body-lh` 22 · `--m-text-meta` 12.5 · `--m-text-meta-lh` 17 · `--m-text-button` 15 · `--m-text-figure` 17 · `--m-text-figure-lh` 22 · `--m-text-total` 22 px | the phone scale | ADOPT; text fields 16 px on touch (D-DS-02) | CSS only, read by the phone compositions |

### 5.3 Spacing, sizing and density

| Tokens and values | Role | Disposition | Tailwind key |
| --- | --- | --- | --- |
| `--space-0-5` 2 · `--space-1` 4 · `--space-2` 8 · `--space-3` 12 · `--space-4` 16 · `--space-5` 20 · `--space-6` 24 · `--space-8` 32 · `--space-10` 40 · `--space-12` 48 · `--space-14` 56 · `--space-16` 64 · `--space-18` 72 px | the spacing scale, a 4 px base with a 2 px step | ADOPT (SLOP-33) | `--spacing: 0.25rem`, each step a multiple of it |
| `--page-gutter` 16 · `--grid-gap` 12 · `--card-padding` 20 · `--card-header-icon-gap` 12 · `--search-max-width` 592 · `--header-height` 76 · `--header-control-top` 18 · `--footer-height` 64 px | page gutter, card grid gap and padding, the top bar's height and search width, the sticky footer | ADOPT, geometry × `--du` on the desktop | CSS only |
| `--sidebar-width` 264 · `--sidebar-inset-x` 16 · `--nav-item-height` 44 · `--nav-item-gap` 6 · `--nav-icon-inset` 12 · `--nav-icon-label-gap` 20 · `--nav-indicator-width` 3 · `--nav-indicator-inset-y` 8 px | the sidebar and its entries, with the 3 px indicator | ADOPT — R1's entries (GD-03; GA1 e)); at least 220 px at runtime | shadcn/ui Sidebar's `--sidebar-width`, set from the token instead of its 16rem default |
| `--auth-card-width` 1336 · `--auth-split` 13fr 11fr · `--auth-form-width` 487 · `--auth-visual-inset` 48 px | the sign-in's split card | ADOPT, the visual panel abstract (GR-12) | CSS only |
| `--radius-xs` 4 · `--radius-sm` 6 · `--radius-md` 8 · `--radius-lg` 10 · `--radius-xl` 12 px · `--radius-full` 9999 px | the radius family; cards 10, dialogs 12, sheets' top corners 16 | ADOPT (SLOP-03, SLOP-31 revised) | the same names, set explicitly; `--radius` = `--radius-lg` |
| `--control-xs` 24 · `--control-sm` 32 · `--control-md` 36 · `--control-lg` 40 · `--control-header` 42 · `--control-xl` 56 · `--control-xl-field` 55 · `--control-add-row` 28 px | control heights — round chevrons, small, fields and default buttons, page actions, top-bar controls, sign-in | ADOPT with the density floors below — a round or icon control never under 24 px after `--du` —; ADAPT — 48 px for the floor task's operational controls, ≥ 44 px targets with a coarse pointer (GR-07; D-DS-19) | CSS only, read by the components |
| `--badge-height` 24 · `--badge-height-lg` 28 · `--badge-height-sm` 22 · `--badge-dot` 8 · `--badge-dot-lg` 9 · `--keycap-height` 22 · `--keycap-min-width` 23 px | status pills and dots; keycaps | ADOPT | CSS only |
| `--stepper-disc` 30 px · `--stepper-disc-bg` `#f3f4f5` · `--stepper-ring` `#d0d0d2` · `--stepper-line` `#d9d9db` | the stage track's markers and connectors | ADOPT; the current marker ringed in olive (GR-03) | CSS only |
| `--icon-lg` 24 · `--icon-md` 20 · `--icon-sm` 16 · `--icon-xs` 12 px · `--icon-stroke` 1.75 · `--icon-stroke-sm` 1.5 · `--icon-stroke-xs` 1.25 · `--icon-stroke-lg` 2 · `--icon-button-round` 24 px | icon boxes and their strokes | ADOPT — Lucide drawn at these strokes (GR-18; SP-16) | CSS only |
| `--icon-disc-lg` 56 · `--icon-disc-kpi` 52 · `--icon-disc-row` 42 · `--icon-disc-md` 40 · `--icon-disc-sm` 32 px | icon wells | ADOPT as identity markers | CSS only |
| `--shadow-xs` · `--shadow-card` `0 1px 3px` at 4 % · `--shadow-card-hover` · `--shadow-pop` · `--shadow-raised` · `--shadow-menu` · `--shadow-toast` · `--shadow-modal` · `--shadow-drawer` · `--shadow-footer` · `--shadow-cta` | controls, cards and their hover, toggles and tab bars, the sign-in card, menus, toasts, dialogs, drawers, the sticky footer, the sign-in button | ADOPT (SLOP-14) | the same names in `--shadow-*` |
| `--shadow-palette` (two drop shadows) | the command palette | ADOPT | `--drop-shadow-palette` |
| `--color-focus` = `--brand-accent` · `--focus-ring-width` 2 px · `--focus-ring-offset` 2 px · `--focus-ring` 3 px halo · `--focus-ring-danger` 3 px danger halo | the focus outline and a field's halo | ADOPT — 5.65–7.00:1 on every surface, lime 200 the lowest | `--color-focus`; `--color-ring` |
| `--z-raised` 2 · `--z-bar` 30 · `--z-popover` 80 · `--z-sheet` 90 · `--z-drawer` 95 · `--z-modal` 100 · `--z-toast` 110 · `--z-skip` 120 | the layer scale up to the skip link | ADOPT | CSS only — Tailwind's table lists no layer namespace |
| `--safe-top`, `--safe-right`, `--safe-bottom`, `--safe-left` (the safe-area insets) · `--touch-min` 44 · `--m-gutter` 18 (20 from 375 px) · `--m-section-gap` 28 · `--m-card-gap` 14 · `--m-card-pad` 18 · `--m-row-min` 60 · `--m-control-h` 48 · `--m-control-r` = `--radius-md` · `--m-appbar-h` 56 · `--m-bottom-nav-h` 60 · `--m-action-bar-h` 72 px | the phone composition — insets, gutters, rows, controls, the app bar, the bottom bar and the action bar | ADOPT; the bars opaque (GR-22) | CSS only |
| `--select-menu-pad` 8 · `--select-row-h` 42 · `--select-row-px` 14 · `--select-row-r` = `--radius-sm` · `--select-menu-r` = `--radius-lg` · `--select-menu-max-h` 320 · `--select-sheet-row-h` 52 px | the custom select's popover and its phone sheet | ADOPT | CSS only |
| `--upload-success` · `--upload-success-dot` · `--upload-success-bg` (aliases of the success tone) | the upload's success state | ADOPT | CSS only |
| `--du`, `--dt`, `--tu`, `--iu` (1 px on phones and by default) · `--density-scale` (1; .9 from 1,025 px) | the runtime units: from 1,025 px `--du` follows the viewport's height and width, × .9, `--dt` half its compression, `--tu` 1 px — GR-16's variant B (D-DS-10), the reference's `clamp(.92px, …, .96px)` not used —, `--iu` = `--tu`; tablets 768–1,024 px `--du` .86–.94 from the height; the reference's sign-in page keeps 1 px | ADOPT (GR-06; GR-16's variant B) | CSS only; the factor applied to rem keys (GR-06) |

**Spacing.** The 4 px scale with its 2 px step (SLOP-33): card padding 20 px, 18 on phones; 12 px between cards, 14 on phones; 28 px between sections on phones; 8 px from a label to its control and from a control to its supporting text — the reference's gap, which DESIGN_REFERENCES' alignment specification keeps (OC-09). Macro breathing room, micro density: sections separated by space and rules, not by a card each (SLOP-01; AICWDF §18.2).


**Density and size.**

- Desktop: the density layer `--du` with `--density-scale` 0.9 at browser zoom 100 %, control floors of 34 px for header controls, 32 large, 30 medium, 28 small and 24 px for round and icon controls (2.5.8); one control height per density — 36 px for fields and default controls, 40 px for page actions, 32 px inside cards (OC-09).
- Phone: fields and primary buttons 48 px, every other target at least 44 px, rows at least 60 px, bottom-bar items at least 60 × 60 px, sheet rows 52 px.
- Warehouse and field task screens — the pages [IA_PAGE_INVENTORY](stage2/IA_PAGE_INVENTORY.md) marks `[floor]`, PG-022 *Terima Barang* and PG-024 *Keluarkan Barang* among them —: the operational controls 48 px, the others at least 44 px (D-DS-19; CQ-5); a coarse pointer gets targets of at least 44 px at every width (GR-07; DESIGN_SYSTEM UXV-10).
- No target below 24 × 24 CSS px on the desktop — WCAG 2.2 2.5.8 Target Size (Minimum), level AA, "at least 24 by 24 CSS pixels" with its exceptions.
- The dense mode only on OC-09's named screens: the multi-line editors of quotations and purchases (PG-014, PG-017), *Riwayat Stok* (PG-020), *Mutasi Bank* (PG-050), *Laporan* and *Laporan Gabungan* (PG-051, PG-052), *Riwayat Aktivitas* and *Log Keamanan* (PG-063, PG-064).
- The alignment specification of [DESIGN_REFERENCES §7](DESIGN_REFERENCES.md#alignment-specification-oc-09): one control height per row, a stated column grid for multi-line editors — S2-2 states the purchase line's proportions on the reference's geometry —, numbers right-aligned with tabular figures, secondary metadata on its own line below the control row, less-common fields behind "Rincian lain", a summary beside or below the form it totals.


### 5.4 Radius, elevation, layers and motion

| Tokens and values | Role | Disposition | Tailwind key |
| --- | --- | --- | --- |
| `--mc-dur-instant` 80 · `--mc-dur-hover` 140 · `--mc-dur-fast` 160 · `--mc-dur-state` 200 · `--mc-dur-base` 240 · `--mc-dur-moderate` 320 · `--mc-dur-sheet` 320 · `--mc-dur-check` 360 · `--mc-dur-slow` 400 ms | press; hover; state morphs and selection; component states; item reveals and the page exit; dialogs and disclosures; bottom sheets; the success check; the navigation indicator and panel reveals | ADOPT (VP-8 as GA1 d) reconciles it) | CSS only — Tailwind's table lists no duration namespace; how utilities read them is P11's, needs verification at implementation |
| `--mc-dur-auth-compact` 480 · `--mc-dur-auth-step` 600 · `--mc-dur-auth-signature` 600 ms | the sign-in's step on narrow screens and on the desktop; its desktop hand-off | ADOPT on the sign-in (VD-05) | CSS only |
| `--mc-dur-reduced` 150 · `--mc-dur-spin` 900 ms | the crossfade under reduced motion; one spinner turn | ADOPT | CSS only |
| `--mc-ease-standard` `cubic-bezier(0.42, 0, 0.58, 1)` · `--mc-ease-enter` `cubic-bezier(0, 0, 0.2, 1)` · `--mc-ease-reveal` `cubic-bezier(0.25, 1, 0.5, 1)` · `--mc-ease-exit` `cubic-bezier(0.37, 0, 0.63, 1)` · `--mc-ease-linear` `linear` | persistent moves, entrances, reveals, exits, the crossfade | ADOPT | `--ease-standard`, `--ease-enter`, `--ease-reveal`, `--ease-exit`, `--ease-linear` |
| `--mc-ease-emphasis` `cubic-bezier(0.34, 1.45, 0.64, 1)` | an overshoot for one datum per view | EXCLUDE (VP-8) | — |
| `--mc-stagger-tight` 30 · `--mc-stagger-row` 40 · `--mc-stagger-card` 60 ms · `--mc-stagger-max-steps` 8 · `--mc-stagger-window` 300 ms · `--mc-content-offset` 120 ms · `--mc-page-budget` 900 ms | staggers within 300 ms; a container before its content; data-ready to the last element | ADOPT | CSS only |
| `--mc-enter-scale` 0.97 · `--mc-exit-scale` 0.98 · `--mc-enter-rise` 8 px · `--mc-title-rise` 6 px · `--mc-item-rise` 4 px · `--mc-item-blur` 3 px · `--mc-validation-rise` −4 px · `--mc-press-scale` 0.97 · `--mc-pop-scale` 0.8 | entrance and exit transforms; a title's and an item's rise; text-level blur; an inline message dropping in; press; the success check's pop | ADOPT; none of them under reduced motion | CSS only |
| `--mc-auth-shift` 24 px · `--mc-auth-parallax` 48 px · `--mc-auth-swap-at` 0.5 · `--mc-auth-exit-end` 0.45 · `--mc-auth-enter-start` 0.55 | the sign-in panel's one transition and its restrained parallax | ADOPT on the sign-in only (VD-05; SLOP-16 revised); removed under reduced motion | CSS only |
| `--mc-panel-start` 3 % · `--mc-scrim` `rgba(15, 16, 20, 0.32)` · `--mc-backdrop-blur` 4 px | a side panel's clip reveal; the overlay scrim and its optional blur | ADOPT | `--color-scrim` and `--blur-backdrop` (proposed) |

**Coverage.** §§5.1–5.4 name every custom property of `tokens.css` — 424 names, 436 declarations with the breakpoint overrides —, each in one row, checked by script against the file.

The radius, elevation and layer tokens are §5.3's table; their rules are §8's.

## 6. Layout, grid and breakpoints

The reference's classes, with the repository's corrections (GA1 a), e); [DESIGN_REFERENCES §7](DESIGN_REFERENCES.md#7-draft-responsive-rules)).

| Width | Composition | Tailwind key (proposed) |
| --- | --- | --- |
| Phone, to 767 px | the 56 px app bar with the back action and the title; R1's bottom bar; one column, sections disclosed by priority; tables as rows; selects, dialogs and menus as bottom sheets, the palette as a top sheet; one sticky action region above the bottom bar; the gutter 18 px, 20 px from 375 px; the title 26/32 from 400 px | the default, mobile-first |
| Tablet, 768–1,024 px | the 80 px icon rail with the drawer; tables tighten their cells, hide their low-priority columns, then become rows when they still do not fit; multi-line editors as item cards | `--breakpoint-md: 48rem` |
| Desktop, from 1,025 px | R1's sidebar, 264 px at the canvas and at least 220 px at runtime, collapsing to the rail with Ctrl B — on a page that composes a document the rail at entry to 1,279 px (D-DS-23) —; the top bar; one density at browser zoom 100 % | `--breakpoint-lg: 64.0625rem` |
| Desktop, from 1,200 px | the reference's full composition | `--breakpoint-xl: 75rem` |
| Desktop, from 1,280 px | the document composer at its full share, the sidebar as on every page (D-DS-23; GR-10) | `--breakpoint-composer: 80rem` |

The default breakpoints are cleared with `--breakpoint-*: initial` before these are set (Tailwind v4.3, *Theme variables*); these are §1's bands. Each page type takes the reference's archetype width (proposed, Level 1): data, 1,680 px, for the lists, work queues and reports; standard, 1,440 px, for *Beranda* and the record workspaces; settings, 1,280 px, for the settings pages and *Akun Saya*; form, 1,120 px, for the forms, editors and task steps; focused, 960 px, for PG-005.

**Reflow at 320 px** ([GR-05](evidence/P7_REBASELINE_GATE.md#carried-items-gr-01)). The phone composition reflows at 320 CSS px without horizontal page scrolling — WCAG 2.2 1.4.10 Reflow, "320 CSS pixels" for vertical scrolling content —, the reference validating from 360 px; a data table scrolls only inside its own frame, the criterion's exception. Titles and meta wrap; single-line values truncate with the full text available (1.4.13); record numbers never truncate; codes such as racks and SKUs never break at their hyphens.

**The phone bars** (GR-11; GR-22). Both opaque, without the reference's blur. The bottom bar holds R1's items — the Owner's *Beranda* · *Proyek* · *Cari* · *Lainnya*, the Admin's *Pekerjaan* · *Proyek* · *Cari* · *Lainnya*, each present only when the account may open it —, the current item with its olive mark (GR-02). On a focused scan step whose action bar shows, the bottom bar hides; no path is lost, the back action leads out (GA1 e)). *Lainnya* — *Kerja*, *Menu lengkap* and *Akun* — opens the reference's drawer, a navigation surface by nature, as the prototype at commit D shows it (GR-11, settled in Stage 4; no C2 confirmation is recorded — DIR-079 CD-01).

**Input, not only width** (GR-07). A coarse pointer gets targets of at least 44 px at every width, warehouse and field tasks 48 px; text fields are 16 px on touch devices (D-DS-02).

**Density** ([OC-09](../00-governance/DECISION_LOG.md#dir-055-dir-056-obs-020-and-tech-028--p7-ux-re-baseline-checkpoint-c1-decision-rework-authorization-baseline-and-rework); D-DS-03). The dense mode only on OC-09's named screens (§5.3); every other screen keeps the reference's comfortable density, judged against AICWDF §18.2 at the preview.

These rules replace the approved revision's §6; the alignment specification of [DESIGN_REFERENCES §7](DESIGN_REFERENCES.md#alignment-specification-oc-09) applies within them.

## 7. Typography rules

**Typography.**

- Inter's text cut at every size, the four weights, tabular figures for every number in an input, a cell or a summary ([OC-09](../00-governance/DECISION_LOG.md#dir-055-dir-056-obs-020-and-tech-028--p7-ux-re-baseline-checkpoint-c1-decision-rework-authorization-baseline-and-rework)). Hierarchy by size and ink tier, never by colour alone.
- Desktop roles at the canvas, × `--tu`: title 32/40; figures 28/36 and 22/28; heading 20/28 and card titles 16.5–21; lead 17/26; body 15/22 and 14/20; label 13/18; caption 12/16; micro 11/16. Phone: title 25/31 (26/32 from 400 px), heading 18/24, row 15/20, body 15/22, meta 12.5/17, button 15, figure 17/22, total 22; text fields 16 px on touch (D-DS-02).
- A display size only on the sign-in (SLOP-26); page titles on the reference's scale, never a hero (SLOP-27; VP-2).
- Nothing below 11 px; the 10.5 px pill excluded; the legibility of the smallest desktop text fixed by GR-16's variant B (D-DS-10).
- Capitalization, punctuation and formats are DESIGN_SYSTEM §16's and [LOCALIZATION_ARCHITECTURE](stage2/LOCALIZATION_ARCHITECTURE.md#24-formats)'s; the reference's micro-copy is never wording (GP-5).


## 8. Iconography, motion, radius and elevation

**Iconography.** Lucide icons as inline SVG components, never an icon font; sizes on the reference's scale — 24 px in the navigation, 16–18 px in buttons and rows, 14 px in a status badge (§12.1) —; an icon never the only cue (§15); each icon's meaning is one throughout the application.

**Radius.** Cards 10 px, controls about 0.18 × their height, menus 10 with 6 px highlights, dialogs 12, sheets 16 on their top corners; nothing else above 12 px (SLOP-03, SLOP-04).


**Elevation.** A card's barely visible resting shadow with its border carrying the edge; menus, toasts, dialogs, drawers and the palette with their own shadows over the scrim (SLOP-14); the phone's app bar and bottom bar opaque, without the reference's blur (GR-22; SLOP-10).


**Motion** (VP-8, as GA1 d) reconciles it).

- Functional only: state changes, the page-state transition with the shell still, panels, drawers, sheets, dialogs, menus and feedback; nothing tied to scrolling; focus never animated; a spinner only after 300 ms; data-ready to the last element within 900 ms; no transition delays focus or input.
- Excluded: counts rolling per digit, the emphasis curve, chart reveals, looping. The sign-in's restrained parallax stays inside its one transition between steps (VD-05; SLOP-16 revised).
- Under `prefers-reduced-motion: reduce`: no translate, scale, blur, clip, parallax, stagger, roll or stroke drawing; a 150 ms linear crossfade; indicators jump; a page swap instant (SLOP-15 revised).
- In the application the reference's router is replaced by Inertia's persistent layouts, which keep a layout "alive between visits" (Inertia v3, *Layouts*): the shell stays mounted, the page region takes the page-state transition, focus and scroll are handled on each visit (GA1 c) 10) — needs verification at implementation.


**Stage 3 rules** (Annex A; §4):

- **The reference's recipes only** (D-DS-16): every motion is one of `motion.css`'s on a MULTIPLECORP state change; a motion the pilot added is not part of V1 (DA-04).
- **Reduced motion** (D-DS-17): no movement at all under `prefers-reduced-motion: reduce` — the reference's own chevron and select turns included —; a 150 ms crossfade at most (DA-12).
- A fold opens with its chevron's turn only, as the reference's help fold; its body does not rise (GF-42).
- Each Inertia visit sets focus and scroll; a full document load is never the answer's carrier (FA-09's cause, the prototype router's).

## 9. Patterns

Every screen is assembled from these patterns; a build unit that needs something they do not cover proposes an addition here instead of inventing a page-level convention (OB §23; UXH-11).

### 9.1 Shell, navigation and page structure

| ID | Pattern | Desktop | Phone | Rules |
| --- | --- | --- | --- | --- |
| PT-01 | **Application shell** | a 264 px sidebar, at least 220 px at runtime, in business-flow order — groups open in place, one level — that collapses to the 80 px rail; the 76 px top bar with "Cari atau pindai kode", "Buat" and the account menu (§§5.3, 6, 9.6); a notice strip under the top bar only while a notice is due; the content to the right ([INFORMATION_ARCHITECTURE §6.1](INFORMATION_ARCHITECTURE.md#61-desktop-and-tablet)). The current entry: 600 weight, a 2 px accent rule on its left edge and the `--muted` surface | — | The shell never shows a business figure, a company's data or a count across companies; the session notices of [ADMIN_FLOW §6](ADMIN_FLOW/s06-09-session-forms-lists.md#6-session-expiry-re-authentication-and-unsent-input) appear in its notice area |
| PT-02 | **Phone navigation** | — | the 56 px app bar with the back action and the title; the 60 px bottom bar (plus the device's safe area) with R1's four items, each a 22 px icon above an 11.5 px label; *Lainnya* as the reference's drawer of grouped rows ([INFORMATION_ARCHITECTURE §6.2](INFORMATION_ARCHITECTURE.md#62-phone); GR-11); the bar hidden during focused tasks (§§5.3, 6) | Designed for the thumb: the main destinations within reach, each item at least 60 × 60 px and every other target at least 44 px, the active destination marked by 600 weight and the accent rule, never by colour alone |
| PT-03 | **Page header and page actions** | back link or breadcrumb (at most three levels) · title · one line of record facts — number, company label (PT-04), status badge (PT-13) · one primary action on the right · secondary actions in "Tindakan Lain" (DropdownMenu) · the business-date control (PT-25) on command forms | back arrow and title in the top bar; the facts line wraps under the title; the primary action in a bottom action bar fixed above the navigation; secondary actions in "Tindakan Lain" | At most one primary action per page region; while a command form with its own action bar is in view, the header's action is shown as secondary, so one primary action is visible at a time. No description paragraph unless it carries a needed fact; no hero header, greeting or illustration |
| PT-04 | **Company label, working filter and relation marker** | company code before the record number in tables and in the facts line; the filter "Tampilkan: Semua perusahaan" in the list header — a Popover with a checklist of the companies in scope | the code as the first token of a list row's first line; the filter inside the filter sheet | The rules of [INFORMATION_ARCHITECTURE §7](INFORMATION_ARCHITECTURE.md#7-company-context-model); the relation marker is muted plain text, never a link, colour, logo or count |
| PT-31 | **Derived part strip** (D-IA-09) | one row above a record's parts: each part's name in `label` style, its part state as the 14 px shape followed by the state's label (§12.1), and one derived fact in `data` style; at most eight parts, wrapping to a second row rather than scrolling | not used — the phone's part list carries the same fact in each row | Derived each time from the parts' own figures; never a stored stage, a progress bar or a percentage (§18 SLOP-44) |
| PT-12 | **Detail layout** | PT-03 · a facts summary as a definition list in two to four columns · the record's parts · the timeline (PT-18) | PT-03 · facts stacked · parts as a list of drill-in rows or a segmented switcher · timeline | A bordered panel (the 10 px card radius, 1 px `--border`; §5.4) only where a group needs its own boundary — never a panel inside a panel, never every section in a panel |

**Stage 3 rules** (Annex A; §4):

- One shell component set on every page, so its geometry is the same everywhere (DA-03); the sidebar's expanded or rail state is the shell's own, and a page that composes a document opens with the rail from 1,025 to 1,279 px (D-DS-23), without a shift while it is shown, the sidebar never animating under reduced motion.
- A page's secondary links sit in its actions or overflow menu, never as a loose button under the last card (GF-23).


### 9.2 Lists, tables and filters

| ID | Pattern | Desktop | Phone | Rules |
| --- | --- | --- | --- | --- |
| PT-05 | **Data table** — the desktop standard | sticky header row in `label` type, sentence case; 36 px rows separated by hairline rules, no zebra stripes; the first column is the record's number as a link; numbers right-aligned with tabular figures; a status column of badges; secondary row actions in a "⋯" menu; the hovered and the focused row tinted `--muted`, the focused row also outlined; a sort arrow and `aria-sort` only on keys the screen inventory registers | not used — PT-06 | Row click opens the record; keyboard: arrow keys move between rows, Enter opens. No column resizing, reordering, inline editing or selection checkboxes in V1, since no list command acts on several records. At tablet widths the table tightens its cells, hides its low-priority columns and then becomes PT-06's rows when it still does not fit (§6). No total row, page numbers or "x dari y" (PF-15) |
| PT-06 | **Dense list rows** — the phone alternative to a table | — | rows of at least 60 px (§5.3): line 1 the identifier (company code first when several companies are in scope) with the key figure right-aligned; line 2 muted secondary facts with the status badge right-aligned; hairline separators; the whole row is the target | Not a card per record and no boxed sections; at most two lines, the rest on the record |
| PT-07 | **Filters** | a filter bar above the table: the list's search field, at most four filter controls, the company filter, "Atur Ulang"; applied filters as removable tags under the bar | "Filter" with the number applied opens a full-height sheet with the same controls and "Terapkan"; a summary line of applied filters under the header | Only the keys the screen inventory names; values in the address (INFORMATION_ARCHITECTURE §11); a filter in PF-42's bounded form shows the partial-page note of PT-08 |
| PT-08 | **List continuation and list notices** | "Muat Lagi" below the last row; "Semua sudah ditampilkan." when nothing is left; the cut notice (ADMIN_FLOW MSG-23); a bounded filter's page that came back short: "Sebagian data sudah diperiksa. Muat lagi untuk melanjutkan."; a cursor that fails its check: MSG-26 | a full-width "Muat Lagi" button; the same notices | Never a total, page number or page-size selector; the page size is 25 (PF-17) |
| PT-27 | **Search and type-ahead** (Command, Combobox) | results grouped by type, an exact code match first with the mark "Kode Cocok" (§12.1), at most 20 rows, keyboard navigation, the cut notice; in a form picker "Tidak ditemukan." with "Buat {Objek}" — "Buat Klien" — where the account may create | full-screen search; the same groups | The rules of ADMIN_FLOW IP-17; results are chosen with KB-11; no recent-searches history is stored in the browser |

**Stage 3 rules** (Annex A; §4):

- A list's filters are the reference's filter controls — label, value, chevron — in the list's toolbar (GF-17).
- A list's columns keep their widths when a status appears in a row (GF-18).
- A tab row that overflows on the phone scrolls, the reference's edge mask its cue (GF-14).


### 9.3 Forms and entry

| ID | Pattern | Desktop | Phone | Rules |
| --- | --- | --- | --- | --- |
| PT-09 | **Form layout and validation** | labels above fields; "(wajib)" after the label of a required field; helper text under the field only where it prevents an error; related fields in pairs in two columns within the form width, 1,120 px (§6); sections under `subheading` titles, not in panels; a sticky action bar at the bottom of a long form with the primary action and "Batal" | one column; the action bar fixed at the bottom; inputs at 16 px text; the keyboard type matches the field (numeric for quantities and amounts) | Errors per ADMIN_FLOW IP-14: under the field in ink after the danger tone's icon, the field's boundary in the danger tone, `aria-invalid` and `aria-describedby`; the input kept. Placeholders show a format, never replace a label. Leaving with unsent input asks first (MSG-35); a draft form saves with KB-09 |
| PT-10 | **Multi-line entry** | a table of form rows: number · Produk/Jasa (PT-27) · Jumlah · Satuan · Harga · Pajak · Subtotal *Perkiraan* · row actions; one empty row always at the end; where its column is narrower than its lines — the composer's form column (D-DS-23) — the table scrolls inside its own frame with the line number and product fixed on the left, the row actions fixed on the right and an edge cue; at tablet widths the lines are item cards (§6); the keys KB-05–KB-08; details of a line that do not fit its row — tax, project item, "Kirim langsung ke klien" — open under that row; the keyboard flow of §13 (D-DS-07) | each line a dense row that opens a full-height editing sheet; "Tambah Baris" at the end of the list | A form laid out as rows, not a spreadsheet: no free cell grid, formulas or block paste. Totals are labelled *Perkiraan* until the server's answer (ADMIN_FLOW IP-13) |
| PT-25 | **Business date control** "Tanggal transaksi" | one compact line in the form header: "Tanggal transaksi: Hari ini, 02 Okt 2026 · Ubah"; a chosen date "28 Sep 2026 — dipilih" with the mark "Tanggal Mundur" and, on a numbered document, "Nomor mengikuti periode Sep 2026" | the same line under the title | A date picker (Popover with Calendar) that offers no date after today; the rules of ADMIN_FLOW IP-07 |
| PT-20 | **Scan field** | an input group with a scan icon, the field "Pindai atau ketik kode", the state "Siap Memindai" as plain muted text beside the scan icon — not a badge —, "Cari Produk" beside it, and the last result line below — the matched line and its running count | the same parts on two lines — "Siap Memindai" with the last result line, then the field with "Cari Produk" at 48 px —, the dock's height following its content, fixed at the bottom of the scan step above the action bar, the line list scrolling above it (D-DS-08); at 320 and 390 px, in both languages, nothing cut and nothing scrolling sideways (1.4.10; D-DS-18) | The result line is a polite live region and scan errors are announced assertively, with focus kept in the field (§15); the rules of ADMIN_FLOW IP-15 and §13 |
| PT-29 | **Derived readiness list** | rows: item · *Lengkap* or *Belum* (PT-13) · what is missing · link | the same as dense rows | Derived from state each time it is shown; no stored progress, fraction such as "2 dari 10", progress bar, percentage or checkmark animation (§18 SLOP-44); missing items first |
| PT-30 | **Capability-by-company preview** | a matrix: capabilities grouped by area as rows, the account's granted companies as columns, each cell *Dapat* (kept), *Baru* (added), *Dicabut* (removed) or empty; prerequisites stated per capability — removing one marks the capabilities that need it "Nonaktif — prasyarat dicabut": their grants are kept and take effect again when the prerequisite is granted again (RG-06), and they are revoked only when the Owner removes them; Owner-only capabilities absent; a summary sentence per company | read-only list per company with the same marks | Shown as "Pratinjau Akses" before "Simpan Akses", whose confirmation lists each change and the companies it applies to (H7-06; D-DS-09); the server decides what the grants allow |

**Stage 3 rules** (Annex A; §4):

- Every required field carries the reference's required mark (GF-16).
- A field that depends on another stays disabled, its reason beside it, until that field is chosen (GF-12).
- One-line choices are a radio group; the reference's choice cards hold a title with its description (GF-28).
- PT-25's business-date line has one form on every page that shows it, *Terima Barang*'s included, and shows the chosen date once one is chosen (PB-A12; FA-06).
- *Rincian lain*'s fields sit on the reference's widths — a reference number on the reference-field track, an amount at the money control's width, a group's heading in the reference's in-form subhead role (PB-D02).
- A save that cannot be sent yet is `aria-disabled` with its reason beside it — *Buat Pembelian*'s "Simpan Pembelian" (PB-C05) —, never silently inert.


### 9.4 Actions, overlays and feedback

| ID | Pattern | Desktop | Phone | Rules |
| --- | --- | --- | --- | --- |
| PT-11 | **Buttons and actions** | *primary* — filled `--primary`, white text; *secondary* — 1 px `--input` boundary, ink text; *quiet* — text only, hover surface `--muted`; *destructive* — filled `--destructive`, used only as the confirming button of a destructive dialog; the trigger of a destructive or correcting action on a page is a secondary button with danger-tone text; *busy* — a spinner and "Menyimpan…", disabled while the request runs; *unavailable* — disabled, with the reason as text beside it | the same, 44 px high with a coarse pointer; the primary action full-width in the bottom action bar | Labels begin with the verb, Title Case (§16); a correction entry names its situation after the verb (ADMIN_FLOW §11); never "Ya", "OK" or "Submit". A shortcut hint (Kbd) may follow a label on desktop. One primary per region. Disabling is a convenience only (HO-03) |
| PT-16 | **Dialogs and sheets** | *AlertDialog* for short confirmations — irreversible, destructive, step-up, re-authentication; *Dialog* for a form of at most five fields; *Sheet* from the right, 480–640 px wide, for longer forms, filters and record previews beside a list | forms and filters as full-height sheets; irreversible, destructive and correcting confirmations, the step-up and the re-authentication as full-height sheets (PT-26, PT-28); other short confirmations — leaving with unsent input, removing a line — as bottom sheets with the confirming button at the bottom | Focus moves into the overlay — to its first input or, when it has none, to "Kembali"; never to a confirming button —, the background is inert, Escape (KB-04) and the close control dismiss it — except the re-authentication dialog, which only "Keluar" ends — and focus returns to the trigger. Never two dialogs at once, except that a sheet may open one confirmation dialog and that the idle, re-authentication and step-up dialogs open above any dialog or sheet, are never covered, and return focus to the element focused before them. A command that needs both a confirmation (PT-26) and the step-up shows the confirmation first and the step-up when it is sent (ADMIN_FLOW IP-10) |
| PT-26 | **Confirmation of an irreversible or correcting command** | AlertDialog: title "{Kata kerja} {objek}?"; the consequence and how a mistake is corrected (MSG-39); the affected items; the reason field where required (MSG-40); for numbering the type's numbering facts expanded, the statements and the required tick of ADMIN_FLOW §12.2; the confirming button with the action's verb, "Kembali" beside it | a full-height sheet with the confirming button at the bottom | The payload's formats are checked before the dialog opens, and it opens with focus on its first input or, when it has none, on "Kembali"; KB-10 opens the dialog and only the dialog's button sends the command (ADMIN_FLOW IP-08, IP-09) |
| PT-28 | **Step-up and re-authentication dialog** | AlertDialog naming the account; for re-authentication an e-mail field and a password field, for the step-up one password field — with `autocomplete`, show-password, paste and password managers allowed, focus in the first field; "Lanjutkan"; step-up adds "Batal", re-authentication "Keluar"; for re-authentication the backdrop is opaque so the page content is not readable | the same as a full-height sheet | ADMIN_FLOW IP-10 and §6 |
| PT-14 | **Inline message** — outcomes, notices and errors of a part | one anatomy for every inline message: white, a 1 px `--border` boundary, the tone's icon, the label in 600 weight, the text in ink, and at most one action button | the same, full width | An outcome message is part of the sticky action region of the form or dialog that sent the command: it appears directly above the buttons inside that region, which grows to hold it — up to a third of the viewport, scrolling inside beyond that — so the answer is in view whenever the buttons are (D-DS-06); announced through a live region — polite for COMMITTED and PENDING, assertive for REJECTED, CONFLICT and FAILED — without moving focus, except that a CONFLICT or FAILED answer moves focus to its message (§15). Never a filled colour block (§12) |
| PT-15 | **Toast** | bottom right, above other content, the reference's radius and elevation (§8) | bottom, above the navigation | Only for a quiet confirmation that needs no action and is also visible on the page, such as "Draf tersimpan." after navigating away; it disappears after 4.5 s unless focused or hovered (§9.6; GR-15). Never for REJECTED, CONFLICT, FAILED, unknown outcomes, warnings or anything that needs a decision |
| PT-18 | **Activity timeline** (*Riwayat*) | entries newest first: when recorded and — when it differs — the business date ("Tanggal transaksi 28 Sep 2026 · dicatat 01 Okt 2026 14.05 WIB"); the acting account's name where the viewer may see it; one sentence of what happened — a routed refusal "ditolak — diteruskan", a count made stale "kedaluwarsa"; the reason; changed fields as "field: lama → baru"; links "Mengoreksi" and "Dikoreksi oleh"; "Sistem" only for an entry no account caused; "Muat Lagi" | the same as stacked entries | Projected per PJ-11: keys the viewer may not see are absent, the other side of a cross-company event is the relation marker. No avatars, reactions, comments or chat bubbles (§18) |
| PT-19 | **Loading, empty, error and denied** | a spinner, shown after 300 ms so fast answers do not flash, for the page, a deferred group or a busy control — no skeleton (§9.6); *empty*: one sentence and at most one action; a group error: the message, "Muat Ulang Bagian Ini" and the reference code; the not-found and forbidden pages of ADMIN_FLOW IP-21 | the same | No illustrations, mascots or decorative empty states; a search empty state names the term and offers clearing it |

**Stage 3 rules** (Annex A; §4):

- A page's overflow actions are the reference's overflow menu with its toggle (GF-01).
- MSG-39's dialog puts the question in its title and the consequence and the correction in its body (GF-04; PB-D01).
- A long form's sticky bar holds "Batal" before the primary action (GF-15; PT-09).


### 9.5 Dashboard, records and media

| ID | Pattern | Desktop | Phone | Rules |
| --- | --- | --- | --- | --- |
| PT-21 | **Signal group and queue row** | a group heading with its bounded per-company counts and "Lihat Semua"; rows: the condition · company code · subject · age · link, in the order the signal's statement returns them; at most five rows per signal, the rest behind "Lihat Semua"; a group of the Owner's Beranda reads the same on phone and desktop (D-IA-07); the group's own spinner (PT-19), error and empty sentence | collapsible groups with their counts in the heading; the same rows as PT-06 | Counts are inline text, bounded per company ("20+"); no KPI tiles, charts, trend arrows, sparklines or progress rings (§18 SLOP-18, SLOP-34) |
| PT-17 | **Inventory presentation** | [§14.2](#142-inventory) | the quantity block stacked; lots as PT-06 rows | — |
| PT-24 | **Financial position block** | [§14.1](#141-financial-data) | the block first, stacked | — |
| PT-22 | **Thumbnails and previews** | product thumbnails 40 px in lists only, one page of them, loaded lazily below the fold; a neutral placeholder while a derivative is not ready; the document preview of [§14.3](#143-document-preview) | the same | PF-31, PF-32; an original image only on its record |
| PT-13 | **Status badge** | [§12.1](#121-status-system) | the same | — |
| PT-23 | **Money, quantity and date presentation** | [§16](#16-copy-and-formatting) | the same | — |

**Stage 3 rules** (Annex A; §4):

- Cards are compact and content-driven — option B for the KPI cards and the context card (D-DS-15); what MULTIPLECORP does not define stays absent (§1).

### 9.6 The patterns on the reference's forms

DESIGN_SYSTEM_MAPPING §4.1, normative here: each pattern PT-01–PT-31 with the reference's form it takes.

| PT | Pattern | Presentation from the reference | shadcn/ui | What stays canonical |
| --- | --- | --- | --- | --- |
| PT-01 | Application shell | R1's entries in the reference's shell: the 264 px sidebar, at least 220 px at runtime, collapsing to the rail; the top bar with "Cari atau pindai kode", "Buat" and the account menu (GA1 e)) | Sidebar, Collapsible, Dropdown Menu, Avatar, Tooltip | groups that open in place; the notice strip only while a notice is due. The *Pintasan* help (KB-02) gets no shell control: Ctrl / and an entry "Pintasan keyboard" in the search palette open it (proposed, Level 1; [IA_PAGE_INVENTORY](stage2/IA_PAGE_INVENTORY.md)'s pending choice) |
| PT-02 | Phone navigation | the 56 px app bar with the back action and the title; R1's four bottom items, 60 px plus the safe area; *Lainnya* as the reference's drawer | custom bars; Drawer | the back action on every child flow (AGENTS.md rule 12); R1's four destinations rather than five (GD-03); GR-11; GR-22 |
| PT-03 | Page header and page actions | title, ancestors-only breadcrumb, meta line, one primary, "Tindakan Lain" | Breadcrumb, Button, Dropdown Menu | at most three levels; the facts line; the business-date control (PT-25); one primary (C1A-2) |
| PT-04 | Company label, working filter and relation marker | the company tag; "Tampilkan" | Badge, outline; Select or a Popover with a checklist | the code first; the view a page control, never in storage ([D-IA-02](INFORMATION_ARCHITECTURE.md#4-design-decisions); GA1 g)) |
| PT-05 | Data table | the reference's frame, sticky header, hairline rows, a position number | Table; the Data Table guide | the number as a link in the first column; tabular figures; row actions in "⋯"; position numbers with "Muat Lagi" (GR-08) |
| PT-06 | Dense list rows | the reference's row list, rows of at least 60 px | Item | two lines, the key figure right-aligned, the whole row the target |
| PT-07 | Filters | the toolbar: search, the company view, one or two promoted filters and "Filter (n)" | Input, Select or Toggle Group, Popover or Sheet, Badge | at most four filter controls, "Atur Ulang", applied filters as removable tags, the phone sheet, PF-42's bounded filters — the toolbar is presentation only (GR-20; SD-02) |
| PT-08 | List continuation and list notices | a "Muat Lagi" button and the reference's notice anatomy | Button; Alert | "Muat Lagi", "Semua sudah ditampilkan.", MSG-23's cut notice, the bounded filter's short page, the failed cursor (PF-14; PF-42) |
| PT-09 | Form layout and validation | labels above with an 8 px gap; inline errors; "Pemeriksaan" with "Perbaiki"; the reference's form width, 1,120 px | Field, Label, Input, Alert, Item | "(wajib)"; helper text only to prevent an error (OC-06); IP-14 |
| PT-10 | Multi-line entry | the purchase line's geometry and alignment rule; one add path (C1A-2) | Table with Field, Input, Combobox | one empty row at the end (IP-13); "Perkiraan" subtotals (UXI-14); the frame scrolling where its column is narrower than its lines (D-DS-23), item cards at tablet widths (§6) |
| PT-11 | Buttons and actions | the reference's variants and sizes | Button | destructive only as the confirming button of a destructive dialog; one primary per region |
| PT-12 | Detail layout | header facts, parts as tabs or a segmented switcher, the timeline | Tabs, Toggle Group, Separator, Card | the facts as a definition list; the record's parts; PT-18 |
| PT-13 | Status badge | the pill with its dot; the row density | Badge | [§12.1](#121-status-system) through §5 |
| PT-14 | Inline message | the reference's notice anatomy | Alert | one anatomy with at most one action; an outcome in the sticky action region (D-DS-06) |
| PT-15 | Toast | the white card with its type edge, at most three | Toast | only a quiet confirmation also visible on the page (GR-15); the reference's position and 4.5 s (PT-15) |
| PT-16 | Dialogs and sheets | the 400 px dialog; sheets with a handle; bottom sheets on phones | Alert Dialog, Dialog, Sheet, Drawer | Alert Dialog for confirmations, step-up and re-authentication; Dialog for at most five fields; Sheet for longer forms, filters and previews |
| PT-17 | Inventory presentation | the facts and metric strip | Table, Separator | DESIGN_SYSTEM §14.2 |
| PT-18 | Activity timeline | the reference's timeline, numbered | custom | the business date beside the recorded time; the acting account where visible; "Sistem" |
| PT-19 | Loading, empty, error and denied | a spinner after 300 ms, no skeleton; empty; "Muat Ulang Bagian Ini"; permission | Spinner, Empty, Alert | the 300 ms delay; one sentence and one action when empty; the reference code; IP-21 |
| PT-20 | Scan field | the input group in the scan dock | Input Group | "Pindai atau ketik kode", "Siap Memindai", "Cari Produk", the last result line; IP-15 |
| PT-21 | Signal group and queue row | the reference's signal rows, worst first | Item | at most five rows per signal in the statement's order, bounded per-company counts, "Lihat Semua" |
| PT-22 | Thumbnails and previews | 40 px thumbnails with a neutral placeholder | an image with a placeholder | PF-31, PF-32; DESIGN_SYSTEM §14.3 |
| PT-23 | Money, quantity and date presentation | tabular figures | — | DESIGN_SYSTEM §16; [LOCALIZATION_ARCHITECTURE §2.4](stage2/LOCALIZATION_ARCHITECTURE.md#24-formats) |
| PT-24 | Financial position block | the facts and metric strip | Table, Separator | DESIGN_SYSTEM §14.1; server values (UXI-14) |
| PT-25 | Business date control | the reference's business date line | Popover, Calendar | IP-07's semantics and copy |
| PT-26 | Confirmation of an irreversible or correcting command | the reference's commit and destructive dialogs | Alert Dialog with Field | the title, the consequence, the items, the reason (MSG-39, MSG-40) and the numbering statements with their required tick; the reference's typed confirmation and optional tick excluded (GR-09) |
| PT-27 | Search and type-ahead | the palette and the lookup | Command, Combobox | the exact code first with "Kode Cocok", at most 20 rows, the cut notice, "Buat {Objek}" (IP-17) |
| PT-28 | Step-up and re-authentication dialog | the reference's dialog | Alert Dialog | PT-28's fields, autocomplete, paste and password managers; the re-login of [AUTH_JOURNEYS §2.5](stage2/AUTH_JOURNEYS.md#25-the-re-login-after-an-expiry--d-ux-03s-redesign-proposed) |
| PT-29 | Derived readiness list | the checklist | Item, Badge | derived each time; no fraction, progress bar or percentage (SLOP-44) |
| PT-30 | Capability-by-company preview | a matrix | Table | *Dapat*, *Baru*, *Dicabut*; prerequisites per capability ([RG-06](../05-security/PERMISSIONS_MATRIX.md#3-role-and-grant-model)) |
| PT-31 | Derived part strip | the stage marker language on one row | custom | at most eight parts, derived each time (D-IA-09) |

### 9.7 The interaction patterns on the reference's forms

DESIGN_SYSTEM_MAPPING §4.2: ADMIN_FLOW's IP-01–IP-21 with the reference's components that carry them.

| IP | Pattern | Presentation from the reference | shadcn/ui | What stays canonical (ADMIN_FLOW) |
| --- | --- | --- | --- | --- |
| IP-01 | Answers of a command | the result anatomy; the inline message | Alert, Card | the server's answer decides what the screen says, never an optimistic result; each answer announced and never by colour alone |
| IP-02 | One `command_id` per intent | the busy button keeping its width and label | Button | the identifier minted at the trigger; the control busy while the request runs; inputs frozen while the outcome is unknown |
| IP-03 | CONFLICT and stale state | the inline message with its action | Alert | the cause and the current state where the actor may see them; the other account named only within PJ-11 |
| IP-04 | FAILED, the unknown outcome and retry | the inline message with a retry | Alert, Button | MSG-06 and MSG-07; the same identifier and payload on every retry |
| IP-05 | Duplicate warnings | the warning block with its matches | Alert, Field | a warning never a block and never skipped; the reason on the second submission |
| IP-06 | A routed refusal | the inline message | Alert | MSG-14, naming no company, lot owner or project |
| IP-07 | Business date | the business date line (PT-25) | Popover, Calendar | "Tanggal transaksi" in the form's header; the numbering commands' fixed date |
| IP-08 | Confirming an irreversible command | the centred dialog; a full-height sheet on phones | Alert Dialog; Drawer | one step, naming the object and the consequence, the verb on the button, "Kembali" |
| IP-09 | Corrections, closures and destructive actions | the dialog with its reason field | Alert Dialog with Field and Textarea | the required reason and what it is used for; evidence where the rule asks |
| IP-10 | Step-up confirmation | the dialog (PT-28) | Alert Dialog | the commands of [AU-15](../05-security/SECURITY.md#2-authentication-sessions-and-credentials); the password when not confirmed in the last 15 minutes; enforced by the server |
| IP-11 | Session limits, expiry and re-authentication | the idle warning and the re-login dialog | Alert Dialog | AU-06–AU-08; unsent input kept in memory; [AUTH_JOURNEYS §§2.5–2.6](stage2/AUTH_JOURNEYS.md#26-session-expiry-and-the-idle-warning) |
| IP-12 | Derived background work | status pills on the record | Badge; Toast only under GR-15 | the committed action shown as done; *PDF sedang dibuat*, *PDF siap*, *PDF gagal dibuat* |
| IP-13 | Entry | fields, the number parser, reuse of confirmed data | Field, Input, Combobox | decimal strings with the Indonesian separators; "Perkiraan" totals; server values after the answer |
| IP-14 | Validation | inline errors; "Pemeriksaan" | Field, Alert | a server validation error returns to the form |
| IP-15 | Scanner | the scan dock (PT-20) | Input Group | keyboard-wedge scanners; stale scans and counts answered as CONFLICT |
| IP-16 | Evidence upload | the upload component | Attachment or a file Input | FL-02's types and limit per purpose, MSG-31; the upload outside any command |
| IP-17 | Search and lookup | the palette and the lookup (PT-27) | Command, Combobox | the exact match first; a master chosen by lookup |
| IP-18 | Continuing a list | "Muat Lagi" | Button | 25 rows, no total, no page number, "Semua sudah ditampilkan." (PF-13–PF-17) |
| IP-19 | Several commands on one screen | the split action and the result list | Button Group, Dropdown Menu, Item | each command with its own button, identifier and answer |
| IP-20 | Choosing a reservation cut | the block in the same form | Alert, Radio Group or Checkbox | "Stok Tidak Cukup untuk Semua Reservasi", the shortfall and the active reservations |
| IP-21 | Denial and not-found | the system page and the region state | Empty | MSG-09, the reference code and the way back; the same answer for an unknown and an out-of-scope address (AZ-11) |

## 10. Component composition

shadcn/ui primitives are copied into the codebase and kept generic: they receive values and callbacks, never a capability check, numbering rule, state transition or business wording of their own (OB §20). Feature components compose them and receive the server's decisions as data — what may be shown, which actions are offered, the outcome of a command (AZ-01). The folders follow ARCHITECTURE §10: primitives under `components/ui`, feature components under `features/<module>`, pages under `Pages/<Module>`.

| Feature component | Built from | Business decisions it receives |
| --- | --- | --- |
| Application shell (PT-01, PT-02) | Sidebar, Sheet, Breadcrumb, DropdownMenu, Command, Tooltip | the navigation entries the account's capabilities allow — as hints (H7-03) |
| Page header (PT-03) | Breadcrumb, Button, DropdownMenu, Badge | the record's facts, status and offered actions |
| Company filter and label (PT-04) | Popover, Checkbox, Button | the companies in scope |
| Data table and dense list (PT-05, PT-06, PT-08) | Table (TanStack Table guide for sorting state only), Item, Button, DropdownMenu, Spinner, Empty | rows, the registered sort keys, the opaque cursor |
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
| Signal group (PT-21) | Item, Collapsible, Spinner, Empty, Button | bounded per-company counts and rows |
| Position block and inventory block (PT-24, PT-17) | Table or a definition list, Separator | server figures only |
| Readiness list (PT-29) and capability preview (PT-30) | Item, Table, Badge | derived state; the effective grants |
| Preview (PT-22) | Scroll Area, Button, Item | the rendition's address; the upload's file metadata |
| Derived part strip (PT-31) | Item and a Lucide icon per part | the parts' derived figures and states |
| Record parts and segmented switches (PT-12; D-IA-09) | Tabs on desktop; Item rows that drill in on a phone; Toggle Group for two to four parts and for the Owner's *Tampilan* switch (D-IA-07) | the parts the account's capabilities open |
| Dialogs and sheets (PT-16) | Dialog, Alert Dialog, Sheet (side, desktop), Drawer (bottom sheet, phone) | the form's fields and the consequence text |
| Loading, empty, error and denied (PT-19) | Spinner, Empty, Alert, Button | the group's state and its reference code |
| List continuation and list notices (PT-08) | Button ("Muat Lagi"), Alert | the opaque cursor and whether rows remain |

**Primitives not used in V1.** Pagination — page numbers are forbidden (PF-15), lists continue by "Muat Lagi"; Chart, except the ageing chart of *Beranda* and the report — a chart of defined values (D-DS-05 superseded; VP-6) —; Progress, except a process's bar (SLOP-44), and Carousel (SLOP-35); Avatar, except the account's initial in the account menu (no avatars elsewhere, SLOP-45); Hover Card and Context Menu — information or actions reachable only by hover or right-click; Resizable — no column resizing in V1 (PT-05); Navigation Menu and Menubar — the horizontal menu was rejected (D-IA-01). A build unit that needs one of them changes this section first.

Whether the build uses the Base UI or the Radix variant of the primitives, and Toast or Sonner, is decided with the versions under DEP-07 in P11; the patterns do not depend on it. SECURITY WS-08 leaves open whether a vetted primitive needs inline styles (HO-37); that question stays with P11.

**The reference's anatomy on shadcn/ui** (DESIGN_SYSTEM_MAPPING §3) — the visual form of each component above, normative here:

Every component the [page inventory](stage2/IA_PAGE_INVENTORY.md)'s 65 pages need — 61 inside the shell, PG-001–PG-004 outside it —, from the reference's anatomy (GA1 b)) to the shadcn/ui component or composition that builds it, each name checked in shadcn/ui's components index on 2026-10-14. **The states**, common to every interactive component unless its row says otherwise: *default*; *hover* — a tint within 140 ms, never the only path ([SLOP-17](#18-anti-ai-slop-mapping)); *focus-visible* — the 2 px olive outline with its 2 px offset, instant, a field's border turning olive under its 3 px halo; *active* — a press tint, buttons scaling to 0.97; *selected* — a lime fill with an olive mark and `aria-selected` or `aria-current`, never the fill alone ([GR-02](evidence/P7_REBASELINE_GATE.md#carried-items-gr-01)); *disabled* — at 48 % with the reason beside it, the state that blocks it or "Perlu kewenangan …" — an action the account may not perform is absent, not disabled ([AZ-01](../05-security/PERMISSIONS_MATRIX.md#2-authorization-model) as a hint; GA1 d)); *pending* — the control keeps its width and label, a spinner after 300 ms, one execution in flight and the inputs frozen while the outcome is unknown ([ADMIN_FLOW](ADMIN_FLOW/README.md#admin-and-owner-flows--interaction-model) IP-02); *error* — a danger border, `aria-invalid` and the message in text below (WCAG 2.2 3.3.1); *read-only* — the value as text with its source where it is carried ([DC-5](../00-governance/DECISION_LOG.md#dir-057-dir-058-obs-021-and-tech-029--p7-ux-re-baseline-checkpoint-c1-visual-direction-decisions-visual-checkpoint-authorization-baseline-and-visual-anchors)); *empty* — the Empty composition, with one action only where the account may act.

| Component | The reference's anatomy | shadcn/ui | Density, target and accessibility | Pages |
| --- | --- | --- | --- | --- |
| Shell — sidebar, *Menu lengkap*, icon rail, top bar, skip link | 24 px icon and 15/500 label; the current entry a lime 150 pill, 44 px × `--du`, with a 3 px olive indicator; the group header a disclosure only; the rail on Ctrl B; "Cari atau pindai kode", "Buat" and the account menu in the top bar; on a page that composes a document, the rail at entry from 1,025 to 1,279 px (D-DS-23) | Sidebar — `collapsible="icon"`, Ctrl or Cmd B, a Sheet on mobile —, Collapsible, Dropdown Menu, Avatar, Tooltip | exactly one `aria-current` per landmark; nothing remembered across pages; no count on an entry ([D-IA-06](INFORMATION_ARCHITECTURE.md#4-design-decisions)) | the 61 shell pages, PG-005–PG-065 |
| Phone app bar and bottom bar; *Lainnya* | a 56 px bar with the contextual back and the title; R1's four items, a 22 px icon in a 54 × 30 pill over an 11.5/500 label; a modal drawer, `min(86vw, 340px)` (GR-11) | custom bars — shadcn/ui has no bottom bar —; Drawer, built on Base UI with `swipeDirection` (shadcn/ui, *Drawer*) | items ≥ 60 × 60 px; the current item with an olive mark (GR-02); opaque (GR-22); the bar hidden on a focused scan step with its action bar (GR-11) | the shell pages below 768 px |
| Page header | title, ancestors-only breadcrumb, a meta line, one primary and "Tindakan Lain" | Breadcrumb, Button, Dropdown Menu | one primary per region (C1A-2; PT-03) | every shell page but PG-005 |
| Button | primary, outline, ghost, sunken, accent, danger, the sign-in CTA; 32/36/40/42/56 px; loading keeps width and label | Button — default, outline, ghost, secondary for sunken, destructive —, accent and sign-in variants added | icon-only at least 24 px with a fine pointer and 44 px with a coarse pointer at any width, with an accessible name (§1; D-DS-19; GR-07) | all |
| Split action | a primary segment and a chevron, "Aksi lain", opening a menu, destructive last | Button Group with Dropdown Menu | Enter, Space and the arrows; no draft entry where a form has one saving command ([WF-PUR-01](../03-workflows/WORKFLOWS/s05-03-inventory-purchasing.md#wf-pur-01--project-purchase-desk)) | record workspaces and forms with several commands |
| Field and number entry | label above, 8 px gap; the box with an optional addon; invalid with its message; 36 px with a fine pointer; 48 px on the phone and on the floor task; at least 44 px with a coarse pointer at any width; 16 px text on touch (D-DS-19; GR-07) | Field, Label, Input, Input Group, Textarea | the control edge `#898982` (GR-01); money and quantities parsed by IP-13's Indonesian separators, the formatted value shown on leaving the field | the forms, task steps and settings |
| Select and lookup | a combobox trigger over the native select, a listbox popover with 42 px rows and an olive check, a 52 px-row sheet on phones; the type-ahead lookup | Select, Native Select, Combobox, with Drawer on phones | arrows, Home, End, typeahead, Esc; a master chosen by lookup, never retyped (IP-17) | forms and toolbars |
| Date field and business date | "Tanggal transaksi" with a calendar addon, a typed fallback, no date after today | Popover with Calendar; Date Picker | IP-07's semantics and copy (PT-25) | forms with a business date |
| Date range | presets and a custom range; a start/end sheet on phones | Popover with Calendar in range mode, Toggle Group; Drawer on phones | presets bound to the repository's periods (GR-19) | PG-051, PG-052, PG-063, PG-064 and dated lists |
| Code entry | six cells with a filled-cell rule | Input OTP — built on `input-otp`, with copy and paste, `REGEXP_ONLY_DIGITS` and `maxLength` (shadcn/ui, *Input OTP*) — or one input with `autocomplete="one-time-code"` | paste and autofill work either way; the page does not state the `autocomplete` attribute, so the choice is S2-2's with AUTH_JOURNEYS (GR-12) — needs verification at implementation | PG-002, PG-004, PG-065 |
| Checkbox, radio and switch | author-styled marks | Checkbox, Radio Group, Switch | the control edge at 3:1 (GR-01) | forms and settings |
| Status pill and status text | a badge, 24 or 28 px, tint with its ink and a dot; a row density with a 7 px dot and a 13 px label | Badge; the row density custom | one state per badge, the word and the icon of §12.1, never colour alone | lists, records, the journey |
| Company tag and count badge | a company code; "perusahaan lain"; a count of derived signals | Badge, outline | counts per company outside navigation (CS-08; D-IA-06) | lists of several companies, PG-006, PG-007 |
| Card and section card | white surface, subtle border, radius 10, resting shadow; a text-first header with one action slot; no card in a card | Card | a card only for a distinct object, a group scanned as one or a region with its own state (SLOP-01, SLOP-02) | PG-006, PG-007, PG-011 and the record workspaces |
| KPI tile | disc, label and value, the whole card a link | Card | no delta, sparkline or period comparison (VP-3); four on the Owner's *Beranda* (SLOP-18) | PG-006 |
| Facts, key-value and metric strip | header facts; detail attributes; key figures in one strip with hairlines | a composition with Separator — description lists have no primitive | numbers right-aligned with tabular figures (OC-09) | the record workspaces, PG-011, PG-043 |
| Tabs | real tabs with `?tab=`, overflow into "Bagian Lain", the selected tab underlined in primary | Tabs | the address keeps the tab; arrows between tabs | the record workspaces; *Gudang*'s and *Keuangan*'s tabs |
| Segmented control | views inside one section on a sunken track | Toggle Group | the selected segment with an olive mark (GR-02) | sections with views; the sign-in's "ID \| EN" |
| Data table and row list | one data set — a table from 768 px, rows on phones; a position number on every record list; columns tighten before rows form; rows ≥ 60 px with 44 px targets on phones | Table, the Data Table guide, Item; a "Muat Lagi" Button | position numbers continue across "Muat Lagi"; no pager, total or size choice ([PF-15](../06-api-performance/PERFORMANCE.md#4-pagination); GR-08); a table scrolls only inside its frame (WCAG 1.4.10's exception) | the 25 lists, the 4 work queues and the 4 reports |
| Filter toolbar and company view | search, the company view "Tampilkan" and one or two promoted filters, the rest behind "Filter (n)"; never wraps | Input, Select or Toggle Group, Popover or Sheet, Badge | presentation only — DESIGN_SYSTEM PT-07's behaviour and PF-42 stay (GR-20; DIR-061 SD-02); the company view a page control, never in the shell or in storage (D-IA-02) | the lists and reports |
| States of a region | loading with a spinner after 300 ms; empty; filtered empty; error with "Muat Ulang Bagian Ini"; permission; done | Empty, Spinner, Alert — Skeleton not used | one region never blanks the page; status messages announced (WCAG 4.1.3) | all |
| System page | not found, forbidden and error, nothing of the record rendered | Empty | the same answer for an unknown, an out-of-scope and an Owner-only address (AZ-11; IP-21) | PG-005 |
| Timeline | activity and history, numbered chronologically; both dates of a backdated entry | custom — no primitive | PT-18; "Sistem" only for an entry no account caused | record workspaces, PG-063 |
| Checklist | conditions with state, reason and the link that fixes each | Item, Badge | derived each time; no fraction or progress bar (PT-29; SLOP-44) | PG-012 |
| Stage track and stage navigator | read-only derived steps on 30 px discs — complete, current, upcoming, attention —; the navigator's native buttons on the card it steers | custom — shadcn/ui has no stepper | `aria-current="step"` with "(langkah n dari N)"; the current marker ringed in olive (GR-03); applicable stages only, no percentage (PL-1; VP-4) | PG-011; the composer's stages |
| Segmented stage indicator | on a list row, the applicable stages as segments, the done ones filled, in the stepper's marker language | custom — no primitive | never a percentage or a weighted bar (VP-4; PL-1; SLOP-44); the stage named in words beside it | PG-006's projects needing attention |
| The now strip | *Sekarang*, *Berikutnya* and *Hambatan* side by side on the desktop, a card on the phone; the one primary action with *Berikutnya* | Card, Item, Button | each part's state once — an exception's reason only under *Hambatan* (R1 e)); *Berikutnya* doable now (C1A-1) | PG-011 |
| Dialog and confirmation | application-level, centred, 400 px; the safe action focused; Esc and the scrim cancel; focus trapped and returned; a bottom sheet on phones | Dialog, Alert Dialog; Drawer on phones | Base UI traps focus with `modal` and returns it with `finalFocus`, by default to the trigger; Radix traps it in a modal and returns it to the trigger on Esc; IP-08's one-step confirmation, IP-09's reason, IP-10's step-up; the reference's typed confirmation and tick excluded (GR-09) | every irreversible command |
| Menu and "Tindakan Lain"; account menu | a body-level popover, never clipped; 42 px rows; destructive last; a bottom action sheet with 48 px rows on phones | Dropdown Menu; Drawer on phones | Radix: Space or Enter open it on the first item, the arrows move, Esc closes and returns focus to the trigger, typeahead supported; Base UI: `modal` by default, `loopFocus`, `finalFocus` | the record workspaces; the shell |
| Popover, tooltip and long text | one line with an ellipsis and a full-text reveal; record numbers never truncate | Popover, Tooltip | dismissible, hoverable and persistent (WCAG 1.4.13) | lists and the icon rail |
| Search and scan | Ctrl or Cmd K; grouped results, an exact code match first; a top sheet on phones; the scan dock on floor tasks, the phone's scan field fixed at the bottom (D-DS-08) | Command in a Dialog; Input Group, Kbd; the scan dock custom (PT-20) | lists only what the account may open (AZ-01 as a hint); keyboard-wedge scanners as keyboards (IP-15) | the shell, PG-009, PG-022, PG-024 |
| Toast | a white card with a 3 px type edge; at most three; polite or assertive | Toast — built on Base UI's Toast, whose provider defaults to a 5,000 ms timeout, `0` for none, `limit` 3 and a `low` or `high` priority (Base UI v1.9.0) | only for an event that leaves a visible trace elsewhere, never the only place of a message (GR-15; WCAG 2.2.1) | background completions |
| Notice and alert | a notice band with its tone | Alert | the security notices and MSG-29's Owner variant on PG-006 ([AU-12](../05-security/SECURITY.md#2-authentication-sessions-and-credentials); LG-07) | PG-006, PG-007, forms |
| Checks, result, next step and prerequisite | "Pemeriksaan" with "Perbaiki"; the result with the number as headline and "Salin nomor", the PDF state and one next action; "Perlu kewenangan …" with nothing to press | Item, Alert, Card, Button | the outcome in the action region (D-DS-06); no toast for the same event | forms, task steps, the composer |
| Disclosure | a real button with `aria-expanded` and `aria-controls`, ≥ 44 px, the chevron on the right; an invalid field never left hidden | Collapsible | desktop disclosures where R1 folds — the *Pekerjaan* groups, "Rincian lain", "Tahap yang tidak diperlukan" (GR-21) | PG-007, PG-011, forms |
| Position and inventory blocks | server figures in a table or a definition list | Table, Separator | server values only (UXI-14) | PG-019, PG-043, PG-044 |
| Multi-line editor | one add path — IP-13's empty last row with the catalogue search in its product picker; the alignment grid of [DESIGN_REFERENCES §7](DESIGN_REFERENCES.md#alignment-specification-oc-09); on phones each line edited in its own sheet (D-DS-07) | Table with Field, Input, Combobox | the dense mode on its named screens (OC-09); "Kembalikan isian" restores the last saved draft after asking | PG-014, PG-017 |
| Sticky action bar | one region above the bottom bar with the total and the one primary | a sticky region with Button | scroll padding so nothing focused hides under it (WCAG 2.4.11; GR-06) | forms and task steps |
| Upload | idle, dragging, uploading, success, error, with retry and announcements; drag and drop only as an extra | Attachment, or a file Input in a Field — the fit of Attachment checked in S2-2 | types and limits per purpose from FL-02 (IP-16) | PG-031, PG-038, PG-039, PG-058 and the evidence steps |
| Document composer and paper | on the desktop band, from 1,025 px, the input form on the left and the live preview on the right, side by side — the default, never a tab (D-DS-23; `DIR-079` CD-04) —; the preview sticky with "Pas lebar", "100%" and "Layar penuh" at VD-07's share, the paper a named region after the form in reading order (§15), from 1,025 to 1,279 px at its lower end with the sidebar as the rail; "Rincian \| Pratinjau" below 1,025 px and, on the phone, Edit → Preview → Finalize (DC-11); one sticky bar; the A4 paper | custom on Card, Tabs or Toggle Group and Dialog | VD-07's preview share; [DOCUMENT_PREVIEW_PRODUCER](stage2/DOCUMENT_PREVIEW_PRODUCER.md)'s option A | PG-014, PG-033, PG-036, PG-042; PG-043 for a draft |
| Chart | the ageing bar | Chart, always with its data table | direct labels and amounts; the ramp of §5.1 (VP-6; GR-23) | PG-006, PG-044 |
| Progress bar, keycap, avatar, icon disc, link | a process's bar; shortcut keys; the account's initial; an identity well; underlined text links | Progress, Kbd, Avatar; the disc custom; a link | progress for processes only (SLOP-44); links underlined (WCAG 1.4.1) | uploads and imports; shortcut hints; the account menu |
| Sign-in compositions | the split card fitted to the viewport; the visual panel; the steps with one 600 ms clock; the language toggle | Card, Field, Input, Input OTP, Toggle Group | the panel abstract (GR-12); no editorial copy; the Google button to Google's guidelines ([PROTOTYPE_PLAN](stage2/PROTOTYPE_PLAN.md); GR-13) | PG-001–PG-004 |
| Print view | the page prepared for paper: header, applied filters as a line, page numbers | print styles | DESIGN_SYSTEM §17; a generated document printed from its rendition | lists, records and the draft paper |

**Not used** (GA1 b)): the pager and its total (PF-15), Skeleton, the delta badge (VP-3), the hub tiles, greeting and hero (VP-2; GD-04), the guidance level, resume memory and walkthrough (GA1 c) 6), and the demonstration actions (GD-04).

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
| Document composer: the form beside the live preview (D-DS-23) | Edit → Preview → Finalize, the preview full-screen (DC-11); on the tablet "Rincian \| Pratinjau" | the paper needs its share; a small screen holds one of the two at a time |
| Hover tooltips | none: the information is on the screen or behind a tap | touch has no hover |
| Keyboard shortcuts (§13) | none needed; the scan field and large targets | — |

Touch targets are at least 44 by 44 px wherever the pointer is coarse, at any width (§6), with at least 8 px between them; with a fine pointer no target is smaller than 24 by 24 px (WCAG 2.5.8).

**Stage 3 rules** (Annex A; §4):

- On a phone row an amount takes its own line below the main column before the column falls under its minimum (GF-13).
- On the phone, meta items that share a line keep a visible separator (GF-24).

## 12. Status, outcome and state patterns

### 12.1 Status system

Every lifecycle or derived state that a screen shows is a status badge (PT-13; D-DS-04 as adapted): the reference's status pill — its height, radius and tone tokens (§5; the tones below) — with text in its tone, a 14 px Lucide icon whose **shape** identifies the state within its family, and its label. The label alone is sufficient; the shape is the non-colour cue that keeps two states apart in greyscale and for colour-blind viewers; the tone only reinforces (WCAG 1.4.1; UXS-42). The states and their meanings are those of BUSINESS_RULES §3 and WORKFLOWS; this table only gives them a face.

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

**The tones on the reference's tokens** (DESIGN_SYSTEM_MAPPING §5):

[DESIGN_SYSTEM §12.1](#121-status-system) gives every state a screen shows — 74 states in 23 families, counted by script — a label, one of five tones and a Lucide shape; the meanings are [BUSINESS_RULES](../02-domain/BUSINESS_RULES.md#business-rules) §3's and [WORKFLOWS](../03-workflows/WORKFLOWS/README.md#critical-business-workflows)' (D-DS-04). The mapping keeps every state, label, tone and shape and puts them in the reference's pill — D-DS-04's ADAPT disposition ([NON_LOSS_MATRIX](stage2/NON_LOSS_MATRIX.md) section O).

**The pill.** The reference's pill: full radius, the tone's tint with its same-hue ink, 24 px with 12 px text at weight 500, 28 px with 13 px at 600 for the record in view, 22 px with 11 px in compact lists; the 10.5 px size excluded ([GR-16](evidence/P7_REBASELINE_GATE.md#carried-items-gr-01)). In the dot's place the state's 14 px Lucide shape in the ink — the shape keeps two states of one family apart in greyscale, where a dot of the same tone cannot (D-DS-04; WCAG 2.2 1.4.1) —, recorded as a deviation in [DEVIATION_REGISTER](stage2/DEVIATION_REGISTER.md). The row density keeps the reference's 13 px label with the shape before it. The label alone is sufficient; the tone only reinforces; a state never appears as a dot, pill or row tint alone. The reference's live pill — a dot whose ring breathes every 2 s — appears only for a genuinely live state and without its looping ring ([VP-8](../00-governance/DECISION_LOG.md#dir-057-dir-058-obs-021-and-tech-029--p7-ux-re-baseline-checkpoint-c1-visual-direction-decisions-visual-checkpoint-authorization-baseline-and-visual-anchors)), also a recorded deviation.

| Tone (§12.1) | States | The reference's tone | Tint · ink · shape colour | Ink on tint |
| --- | --- | --- | --- | --- |
| neutral | 25 | `neutral` | `--color-neutral-bg` · `--color-neutral-fg` · the ink | 9.99:1 |
| info | 16 | `info` | `--color-info-bg` · `--color-info-fg` · the ink | 11.86:1 |
| success | 16 | `success` | `--color-success-bg` · `--color-success-fg` · the ink | 5.62:1 |
| warning | 13 | `warning` — the reference's `attention` uses the same tokens | `--color-warning-bg` · `--color-warning-fg` · the ink | 6.17:1 |
| danger | 4 | `danger` | `--color-danger-bg` · `--color-danger-fg` · the ink | 4.99:1 |
| — | — | `pending` | `--color-pending-bg` · `--color-pending-fg` | unused: §12.1 gives no state this tone |

**Shapes.** §12.1 names 40 Lucide icons. At Lucide 1.56.0, `file-warning` (*PDF Gagal Dibuat*) and `file-question` (*Tanpa Bukti*) are published as `file-exclamation-point` and `file-question-mark`; the others are published under their names, checked against the release's `icons/` folder on 2026-10-14. §12.1 itself asks P11 to confirm the names against the icon set's version and keep the shape where a name changes; Stage 3 records the two new names. The marks "Tanggal Mundur" and "Kode Cocok" are not states: `calendar-clock` and `scan-barcode` before a `label`-style word, with no tone or pill.

**Stage 3 rules** (Annex A; §4):

- A passed check's icon is the reference's 16 px check in the success tone (GF-10).
- A company chip is followed by its context text or sits inline with the row's note (GF-25).


### 12.2 Outcome presentation

The five answers of a command and the answers that are not outcomes ([ADMIN_FLOW IP-01](ADMIN_FLOW/s05-command-model.md#ip-01--answers-of-a-command)) have one face each:

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
| Not found · forbidden · error | Halaman Tidak Ditemukan · Tidak Berwenang · Terjadi Kesalahan | neutral · `search-x` · `lock` · `triangle-alert` | PG-005 with the reference code |
| Session ended | Sesi Berakhir | neutral · `lock` | PT-28 |
| Validation | Periksa Isian | danger · `circle-alert` | PT-09 field messages and the summary line |
| No connection | Koneksi Terputus | neutral · `wifi-off` | PT-14 |

### 12.3 States of a screen

| State | Presentation |
| --- | --- |
| Loading | PT-19's spinner after 300 ms for the page or for a deferred group; a busy control's spinner |
| Empty | one sentence, at most one action (PT-19) |
| Error of a part | PT-14 with the label "Bagian Ini Tidak Dapat Dimuat", warning tone and `triangle-alert`, the reference code and "Muat Ulang Bagian Ini"; the rest of the page keeps working |
| Denied or not found | PG-005 (ADMIN_FLOW IP-21) |
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

**Stage 3 rules** (Annex A; §4):

- A composer's totals are the reference's totals band at the editor's foot (GF-22).


### 14.2 Inventory

- **Quantity block** (PT-17) per product, and per rack and condition: *Stok fisik* · *Direservasi* · *Tidak layak pakai* · **Tersedia** — with the line "Tersedia = Stok fisik − Direservasi − Tidak layak pakai" in muted text, quantities in base units with the unit and the alternate unit as a hint ("120 rim (24 dus)", §16).
- **Lots**: the opaque code in tabular figures with slashed zeros, rack, condition, remaining quantity and the "Ambil Lebih Dulu" marker on the oldest — a picking aid, never a rule. A lot of a company in scope names that company; a lot of another company shows the relation marker and nothing more (PJ-01, PJ-02).
- **Serials**: presence, lot, rack and condition; any other state only with the movement that explains it (PJ-03).
- **Pending unattributed loss** on its own line: on the pooled views PG-018 and PG-019 the case's pending quantity — "Selisih menunggu penetapan: 3 rim — menunggu keputusan Owner" (MSG-41); in a company's stock view only that company's own exposure (§14.1); never inside a company's figures, never blocking (DIR-027).
- **Restock advice**: "Di bawah minimum 20 rim" in ink after the warning tone's icon; no button buys by itself (BR-INV-10).

### 14.3 Document preview

- **An issued rendition** is shown inline in a frame beside its version chain, with "Unduh" and "Cetak"; the header states the document type, number, version, state and rendition state (FL-07's inline profile; ROUTE-225).
- **An uploaded file** is never shown inline: it is a file row — name, type, size, uploaded by and when, evidence type and issuer — with "Unduh" as an attachment; an image shows the re-encoded preview (FL-03, FL-04, FL-07).
- **A draft's preview** (D-DS-20; [GAP-053](../00-governance/GAP_REGISTER.md#triage); [DOCUMENT_PREVIEW_PRODUCER](stage2/DOCUMENT_PREVIEW_PRODUCER.md) option A, normative here): the composer page receives the draft's fields as page props — only those the viewer may see (WS-07; PJ-15; PJ-19) — and the browser composes the paper from the document layout's template, the one FL-08's renderer uses for the issued rendition, updating it as the allowed inputs change. Its totals are provisional, computed on the entered strings and labelled "perkiraan", until "Simpan Draf" returns the server's (IP-13; UXI-14; DC-3). The template's draft mode marks every page "DRAF — belum terbit", with the number line "Nomor diberikan saat terbit" and a text signature block — no stamp or signature file (DC-2–DC-4; GD-07). The browser prints the composed paper as a marked draft (§17); nothing is written — no rendition, file, number, counter or snapshot. On the desktop band the form and the preview sit side by side, the preview on the right (D-DS-23; `DIR-079` CD-04); on the tablet "Rincian \| Pratinjau"; on the phone: edit, preview, finalize, the preview full-screen (DC-11). The paper is a named region — "Pratinjau {jenis}, draf belum terbit" —, never a live region, so typing is not announced; it follows the form in reading and focus order, and its scrolling frame is reachable by keyboard (2.1.1; §15). The paper is Indonesian whatever the interface language (LOCALIZATION §3.4). Its interaction is INT-012.
- **What P11 builds** (DOCUMENT_PREVIEW_PRODUCER §3): one template per document layout, read by the browser's composer and by the renderer; the fonts served from the application's origin (WS-08); the template's draft mode; the print style — A4 with the renderer's margins, the marking on every page —; the data contract — the draft's fields as props by family, PJ-19's identity assets, without the number, the stamp and the signature —; provisional totals while typing, the server's after each save, the issued rendition reproducing the server's (BR-DOC-03). The renderer's technology is P11's under DEP-07.
- **What P8 tests** (DOCUMENT_PREVIEW_PRODUCER §4): a draft preview never satisfies a requirement; it never writes; it matches the issued layout within the tolerance P8 sets; its totals are labelled, then equal to the server's; only projected fields reach the browser; the paper stays Indonesian; the phone at 390 px; the desktop's form beside the preview at 1,025, 1,279 and 1,280 px and the tablet's toggle at 1,024 px (INT-012; `DIR-079` CD-04).
- A document's layout choice uses the reference's draft-document choice, never the preview's zoom toggle (GF-03).
- A voided version is shown with "Dibatalkan" across its header and is never offered as current.
- A reproduction of a company document uploaded as evidence carries the note "Salinan dokumen terbitan perusahaan — tidak memenuhi syarat dokumen asli" (FL-05) only when the reproduced rendition is one the viewer may see — its company in scope and its family visible (FL-10; DP-13); otherwise the evidence shows as an ordinary upload (ADMIN_FLOW IP-16).


### 14.4 Timeline

PT-18. A correction and the record it corrects link to each other; a backdated entry shows both dates; an entry names its acting account where the viewer may see it — a routed refusal "ditolak — diteruskan", a count made stale "kedaluwarsa" — and "Sistem" labels only an entry no account caused, such as a background job's (SQ-23; PJ-11).

## 15. Accessibility

WCAG 2.2 at level AA is the design target; P8 audits the built screens (UXH-05).

| Area | Rule |
| --- | --- |
| Structure | one `h1` per page; headings in order; landmarks for navigation, main content and the action bar; a skip link "Langsung ke Konten" before the shell |
| Keyboard | everything operable by keyboard in reading order; no positive tab index; no keyboard trap except a dialog's focus loop; shortcuts per §13 |
| Focus | a 2 px `--ring` outline with a 2 px offset on every focusable element; never removed; never hidden under a sticky header, action bar, scan dock or toast — the scroll container's padding equals the height of its sticky regions, and a frame with fixed columns sets its inline scroll padding to their widths, so a focused cell is never under them (2.4.11; PT-05; PT-10) |
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

**Stage 3 rules** (Annex A; §4):

- **Mandated copy is never cut** (D-DS-18; GR-35): a select's value, a form bar's reason, a read-only value and a label wrap under WCAG 2.2 1.4.12's text spacing instead of the reference's ellipsis, one line at its height otherwise; a text that wraps stays inside its bar.
- **Targets** (D-DS-19): 24 × 24 CSS px at least with a fine pointer, 44 px with a coarse pointer at every width, 48 px for the floor task's operational controls.
- The current step's position — "(langkah n dari m)" — sits inside its own step's item, so a screen reader hears it with the step (FA-20).
- Each group of a worklist has its own heading; no row is filed under another group's heading (FA-21).
- A search or filter that matches nothing says so in a status line and announces it (FA-22).
- Content in another language than the page's carries its own `lang` — an Indonesian document's paper inside the English interface (LOCALIZATION §3.4).

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

**Stage 3 rules** (Annex A; §4):

- Strings are [LOCALIZATION](LOCALIZATION.md)'s keys in both languages; the formats are this section's in both languages, values never translated (OQ-04 (a)).
- An estimate is marked in text — "(perkiraan)" —, never with a pill (GF-05; DC-3).
- A figure never breaks inside itself; its box widens or its row wraps (GF-20).

## 17. Print views

A print view is the page itself prepared for paper (DP-19): the same records, fields and figures the viewer sees, nothing more and nothing less.

- Navigation, action bars, filters' controls, buttons and toasts are not printed; applied filters are printed as a line of text under the title.
- Black on white; status badges keep their labels and shapes; tones print as their text.
- A header with the screen name, the company labels in view, "Dicetak oleh {akun} pada {tanggal} {jam} WIB"; a footer with "Halaman {n} dari {m}" — page numbers belong to paper, not to lists.
- Tables repeat their header row on every page and never truncate a cell; wide tables print in landscape.
- A list prints the rows loaded on screen and says "Menampilkan {n} baris yang dimuat"; the complete list is an export.
- Generated documents are printed from their rendition, never from a screen (§14.3).

## 18. Anti AI-slop mapping

Each prohibition of the anti AI-slop constitution (OB §21) has a rule that prevents it. The P1 directive's list and the social-media patterns of DIR-033 §10-M map onto the same rules. The first table's preventing rules are the approved revision's: where [the reference's anti-slop rules](#18-anti-ai-slop-mapping) below record a REVISED or SUPERSEDED disposition of GA1 d), that disposition governs.

| ID | Prohibition (OB §21) | Preventing rule |
| --- | --- | --- |
| SLOP-01 | every section inside a card | PT-12: panels only where a group needs a boundary; space and rules group content |
| SLOP-02 | nested cards without meaningful hierarchy | PT-12: never a panel inside a panel |
| SLOP-03 | giant rounded containers everywhere | §8: cards 10 px, controls about 0.18 × their height, menus 10, dialogs 12, sheets 16 on their top corners; nothing else above 12 px |
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
| SLOP-34 | fake analytics with no operational value | PT-21; INFORMATION_ARCHITECTURE §9: only QS signals and CALC figures; a chart only of defined values — the ageing chart (VP-6) —, never decorative |
| SLOP-35 | looking like a landing page | PT-03, PT-19, SLOP-19–SLOP-21 |
| SLOP-36 | looking like a Dribbble concept | §4 decisions grounded in task speed; [P7_QUALITY_GATE](evidence/P7_QUALITY_GATE.md#desainpakai-exploration) rejections |
| SLOP-37 | looking like a generic Tailwind SaaS template | §5.1: the reference's palette and tones, never the framework's default indigo |
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

**The reference's anti-slop rules** (DESIGN_SYSTEM_MAPPING §9 — its section numbers are that document's):

SLOP-01–SLOP-45 of [DESIGN_SYSTEM §18](#18-anti-ai-slop-mapping), with GA1 d)'s dispositions — KEPT 35, REVISED 9, SUPERSEDED 1 —, a rule protecting clarity, honesty or accessibility kept (D4); the right column says where this mapping honours each.

| SLOP | Prohibition | Disposition | Honoured in |
| --- | --- | --- | --- |
| SLOP-01 | every section inside a card | KEPT | §3, Card — a card only for a distinct object; §2, spacing |
| SLOP-02 | nested cards | KEPT | §3, Card — no card in a card |
| SLOP-03 | giant rounded containers | REVISED — the reference's radius family, still no giant container | §§1.4, 2 |
| SLOP-04 | excessive border radius | KEPT — nothing above 12 px but the sheets' top corners | §2 |
| SLOP-05 | pill-shaped everything | KEPT — pills for states, counts and the bottom bar's current marker only | §§3, 5 |
| SLOP-06 | excessive gradients | KEPT — none in the application; the sign-in panel under [VD-05](../00-governance/DECISION_LOG.md#dir-057-dir-058-obs-021-and-tech-029--p7-ux-re-baseline-checkpoint-c1-visual-direction-decisions-visual-checkpoint-authorization-baseline-and-visual-anchors) | §1.2 — the hero tints excluded; §3, sign-in |
| SLOP-07 | decorative gradients | KEPT | as SLOP-06 |
| SLOP-08 | glowing UI | REVISED — the sign-in button's soft shadow only | §1.2, the CTA row |
| SLOP-09 | neon | REVISED — lime only as a fill under dark ink, never a state's only mark | §1.2, the lime rows; [GR-02](evidence/P7_REBASELINE_GATE.md#carried-items-gr-01), GR-03 |
| SLOP-10 | generic glassmorphism | KEPT — opaque surfaces; a blurred scrim is not a surface | §§2, 6 — the opaque bars (GR-22) |
| SLOP-11 | generic mesh backgrounds | REVISED — the plain canvas `#f7f8f3` | §1.2 |
| SLOP-12 | floating decorative blobs | KEPT — the sign-in panel's curve is VD-05's | §3, sign-in |
| SLOP-13 | generic abstract illustrations | KEPT — abstract imagery only on the sign-in panel; no illustrated empty state | §3, states and sign-in (GR-12) |
| SLOP-14 | excessive shadows | KEPT — the resting shadow; overlays over the scrim | §§1.4, 2 |
| SLOP-15 | excessive animation | REVISED — functional motion on the tokens; a 150 ms crossfade under reduced motion | §§1.5, 2 |
| SLOP-16 | parallax | REVISED — none, but inside the sign-in's one transition | §§1.5, 2 |
| SLOP-17 | useless hover tricks | KEPT — hover never the only path | §3, the states |
| SLOP-18 | excessive dashboard KPI cards | KEPT — four key figures on the Owner's *Beranda*, without deltas | §3, KPI tile (VP-3) |
| SLOP-19 | giant welcome banner | KEPT — no greeting or hero | §1.2; §3, not used |
| SLOP-20 | "Welcome back!" clichés | KEPT — the sign-in's heading and one line | §3, sign-in; [LOCALIZATION_ARCHITECTURE §5](stage2/LOCALIZATION_ARCHITECTURE.md#5-copy-rules-for-the-journey-specifications) |
| SLOP-21 | startup marketing copy | KEPT | §3, sign-in — no editorial copy; LOCALIZATION_ARCHITECTURE §5 |
| SLOP-22 | emoji as decoration | KEPT | DESIGN_SYSTEM §16; LOCALIZATION_ARCHITECTURE §5 |
| SLOP-23 | unnecessary icons | KEPT — text-first headings | §3, Card |
| SLOP-24 | an icon in every label or button | KEPT | §3, Button and Card |
| SLOP-25 | excessive whitespace that slows work | SUPERSEDED — the reference's density, touch sizes on phones, the dense mode on OC-09's screens, under AICWDF §18.2 | §§2, 6 |
| SLOP-26 | gigantic headings | REVISED — the reference's scale; a display size only on the sign-in | §§1.3, 2 |
| SLOP-27 | hero-like headers | REVISED — titles at the reference's size, no hero | §2; §3, page header |
| SLOP-28 | noisy dashboards | KEPT — at most five groups ([QB-10](../06-api-performance/PERFORMANCE.md#6-query-budgets)) | §4, PT-21 |
| SLOP-29 | random colour | KEPT — one action colour, olive for selection and focus, lime only as a signal, tones bound to states | §§1.2, 5 |
| SLOP-30 | arbitrary badges | KEPT — one state per badge; no trend badge; counts per company | §§3, 5 |
| SLOP-31 | inconsistent radius | REVISED — five steps and a pill, each with a role | §§1.4, 2 |
| SLOP-32 | inconsistent icon size | KEPT — the reference's icon boxes and strokes | §1.4 |
| SLOP-33 | inconsistent spacing | KEPT — one scale | §§1.4, 2 |
| SLOP-34 | fake analytics | KEPT — charts of defined values with a table (VP-6) | §3, Chart; §1.2 |
| SLOP-35 | a landing-page look | KEPT | §3, not used |
| SLOP-36 | a concept-shot look | KEPT | §8 — one product, measured against the reference |
| SLOP-37 | a generic template look | KEPT — the default palettes cleared | §1 |
| SLOP-38 | a generated admin-dashboard look | KEPT | as SLOP-18, SLOP-28 and SLOP-34 |
| SLOP-39 | feeds | KEPT — "Muat Lagi", no infinite scroll | §4, PT-08 and [IP-18](ADMIN_FLOW/s06-09-session-forms-lists.md#ip-18--continuing-a-list) |
| SLOP-40 | reactions | KEPT | §3 holds no reaction control |
| SLOP-41 | likes | KEPT | as SLOP-40 |
| SLOP-42 | streaks | KEPT | §3 holds no streak or per-user counter |
| SLOP-43 | confetti | KEPT — no celebratory motion | §2, motion |
| SLOP-44 | gamification | KEPT — no percentage; progress for processes only | §3, progress bar and checklist |
| SLOP-45 | chat-bubble decoration | KEPT — timeline entries are rows | §3, timeline |

## 19. Pattern coverage

Every screen of [INFORMATION_ARCHITECTURE §8](INFORMATION_ARCHITECTURE.md#8-screen-inventory) and every journey of [ADMIN_FLOW §10](ADMIN_FLOW/s10-a-journeys.md#10-journeys) is composed of the patterns of §9; none needs a pattern that is not defined here. A build unit that finds a screen it cannot compose from them raises the gap through change control instead of inventing a pattern.

| Pattern | Used by |
| --- | --- |
| PT-01, PT-02 shell and phone navigation | every authenticated screen (PG-065, PG-006–PG-064) |
| PT-03 page header and page actions | every screen except PG-001, PG-003 and PG-005 |
| PT-04 company label, working filter and relation marker | every list and record that carries company-owned data; the pooled stock views PG-018 and PG-019 |
| PT-05 data table · PT-06 dense list rows | every list screen: desktop and phone respectively |
| PT-07 filters · PT-08 list continuation | every list screen |
| PT-09 form layout · PT-10 multi-line entry | every command form; PT-10 for project, quotation, purchase, invoice, receiving, dispatch, count and import lines (ADMIN_FLOW IP-13) |
| PT-11 buttons and actions · PT-16 dialogs and sheets | every screen with a command |
| PT-12 detail layout | every record screen; the project hub PG-011 with its parts |
| PT-13 status badge | every list and record of a state family of §12.1 |
| PT-14 outcome message · PT-15 toast | every command (ADMIN_FLOW IP-01); PT-15 only as §9.4 allows |
| PT-17 inventory · PT-24 financial position | stock screens PG-018, PG-019 and the warehouse forms; invoice, receivable, payment and project figures |
| PT-18 activity timeline | every record screen's *Riwayat*; the Owner's PG-063 |
| PT-19 loading, empty, error and denied | every screen and every deferred group; PG-005 |
| PT-20 scan field | PG-019, PG-022, PG-024, PG-026, PG-027, PG-028, PG-034 (INFORMATION_ARCHITECTURE §10) |
| PT-21 signal group and queue row | Beranda PG-006 and PG-007; the Owner review queue PG-008 |
| PT-22 thumbnails and previews | product lists and records; document and evidence screens PG-035–PG-039 |
| PT-23 money, quantity and date presentation | every figure on every screen and print view |
| PT-25 business date control | every command form (ADMIN_FLOW IP-07; HO-08); the numbering commands show their date as the fixed day of recording instead (ADMIN_FLOW §12.2; NM-03) |
| PT-26 confirmation of an irreversible or correcting command | every irreversible, destructive or correcting command (ADMIN_FLOW IP-08, IP-09, §11, §12.2) |
| PT-27 search and type-ahead | *Cari* and PG-009; every picker inside a form |
| PT-28 step-up and re-authentication dialog | step-up commands (ADMIN_FLOW IP-10); every session expiry (ADMIN_FLOW §6) |
| PT-29 derived readiness list | the project's completion readiness (PG-012), administrative requirements, company onboarding (ADMIN_FLOW §12.4) |
| PT-30 capability-by-company preview | grant editing on PG-060 (H7-06) |
| PT-31 derived part strip | the project detail PG-011 on desktop (D-IA-09) |

## 20. Traceability

- **V1_SCOPE and the brief:** the P7 handoff requirements and the language and experience contract → §4–§18; OB §20 and REF-054 → §2, §10; OB §21, the P1 directive's anti AI-slop list and REF-055 → §18; OB §22 → §8, §11, §15; OB §23 → §9; AC-14 → §6, §11, §13, §15; AC-10 and AC-11 → §14.1.
- **DIR-033:** §10-M minimum content → §4–§18 (the closure table is in [P7_QUALITY_GATE](evidence/P7_QUALITY_GATE.md#obligation-closure)); §10-N → §15; §13 decision levels → §4; §11 DesainPakai exploration → §4 and the gate's exploration evidence.
- **WORKFLOWS and BUSINESS_RULES:** the state machines of BUSINESS_RULES §3, WORKFLOWS' derived states and L-25, L-29 → §12.1; DIR-011 and BR-INV-01 → §14; BR-DT-06 → §16; WORKFLOWS §8 corrections → PT-26, §14.1.
- **PERMISSIONS_MATRIX and SECURITY:** PJ-01–PJ-04, PJ-07, PJ-11, PJ-22 and PJ-23 → §14 and PT-18; H6-10 → §16; H7-02 → PT-04; H7-06 → PT-30; H7-09 → PT-28; FL-03–FL-05 and FL-07 → §14.3; WS-08 (`font-src 'self'`, camera denied) → §2, §13.2; DP-19 → §17.
- **CONCURRENCY_IDEMPOTENCY and PERFORMANCE:** HO-01 → §12.2; HO-03 → PT-11; HO-08 → PT-25; HO-10 and PF-15, PF-17 → PT-05–PT-08; PF-31, PF-32 → PT-22; PF-34 → PT-19, PT-21.
- **Gaps:** GAP-019 → §1, §14.3, §16; GAP-020 → §6, §11, §13; GAP-029 → §12.1; GAP-032 → PT-30.

- **Stage 3 (DIR-078):** the Owner's CP-A decisions → §4 (D-DS-10–D-DS-22); DESIGN_SYSTEM_MAPPING → §§5–12; DESIGN_REFERENCES → §§2, 6; DOCUMENT_PREVIEW_PRODUCER → §14.3 (GAP-053); the carry-forward ledger → Annex A (SP-66); the carried GR items → Annex B; every page's pattern → INFORMATION_ARCHITECTURE §8 and [NAVIGATION_CONTRACTS](NAVIGATION_CONTRACTS.md).

## Annex A. The carry-forward disposition (SP-66)

PC-1's ledger — the prototype's `evidence/pc1/carry-forward.txt`, 7,407 lines at commit D, compiled from the evidence by script — dispositioned as [DIR-078](../00-governance/DECISION_LOG.md#dir-077-dir-078-obs-030-and-tech-037--p7-ux-re-baseline-cp-a-decisions-run-pc-2-with-p9s-drafting-baseline-and-drafts) SP-66 asks: each category a **rule** of this document, an **accepted and documented difference**, or **dropped** with its reason; WF-PC1's 33 carried findings and PB-A12, PB-D01 and PB-D02 one by one; FA-20, FA-21 and FA-22 as rules (CA-04 item 16). Nothing here is recorded as passed: a raw value stays raw in the ledger, and the application is measured by P8, never by the prototype's renders.

### A.1 By category

| DA | Ledger section | What it holds | Disposition | Where |
| --- | --- | --- | --- | --- |
| DA-01 | B — GF-2A's raw FM failures (CP-1R, commit A2) | 1,040 failing measures in each language, 852 and 853 labelled B or C, 188 and 187 unlabelled | **dropped as requirements**, with their reason: each measures the prototype against the reference, not the application; the application is built from §§5–12 and P8 measures it against the reference's recipes. A B or C label stays an **accepted and documented difference** where its cause is a decision of §4 or a canonical rule its label names — S2-J01's sign-in content, no marketing copy, OQ-13's illustration, VP-3's absent metrics | §§4–12; P8 |
| DA-02 | C — PC-1's raw FM failures (SP-60) | 689 failing measures in each language, none labelled | **dropped as requirements**, as DA-01 — the extension's screens measured against the reference's nearest pages | §§4–12; P8 |
| DA-03 | E — FM-10 beyond ±1 px | 16 rows: the shell's components across the pilot's pages | **rule**: one shell — sidebar, brand, navigation, account, top bar, search, app bar, bottom bar and action bar — as one component set on every page, so its geometry cannot vary between pages | §§9.1, 10 |
| DA-04 | D — FM-08 motion | 40 motions; 53 of the reference's animations missing on the pilot, 17 the pilot's own | **rule** D-DS-16: the reference's recipes on MULTIPLECORP's state changes; a pilot-only motion is not part of V1 | §8 |
| DA-05 | F — SV-01 and SV-04 on the new screens | values the pilot's stylesheets render outside the reference's tokens | **rule**: only §5's tokens; a value outside them is a defect (SLOP-33) | §5 |
| DA-06 | G — WF-PC1's findings carried to Stage 3 | 33 — 13 A, 10 N, 10 D | one by one — §A.2 | §A.2 |
| DA-07 | H — SP-52's items not applied in PC-1 | PB-A12, PB-D01, PB-D02 | one by one — §A.3 | §A.3 |
| DA-08 | I — GF-2A's "recorded for later work" | the KPI cards' empty share, the context card, PS-12's cards, PS-35's sheet, PS-33's chrome, the overdue line on two lines, "Antrean operasional" at 360 px, the decision count's pill, the marker on the code step, the brand under wider spacing at 320 px, the sign-in's tablet step and done transitions, GR-12's unused CSS, the tooling | **rules**: the card direction (D-DS-15) for the cards and the sheet; D-DS-16 for the transitions; mandated copy wrapping (D-DS-18) for the overdue line and the brand. **Dropped**: the marker (prototype-only), GR-12's unused CSS and the tooling notes (the prototype's own) | §§8, 9.5, 15 |
| DA-09 | J — the accepted pages' cards | *Masuk*, *Beranda* and *Terima Barang* as commit A2 built them | **rule** D-DS-15, option B | §§4, 9.5 |
| DA-10 | K — not compared, by the Owner's direction | PG-004 against the reference's expired step | **dropped**, by the Owner's direction; the expired link's answer is AUTH_JOURNEYS' | — |
| DA-11 | L — the accepted pages' differences from commit A2 | decision row 4 (SP-53 (a)); PS-32's scan field focused at the task's start (FA-03) | **accepted and documented differences** — the Owner's instructions required both | — |
| DA-12 | M — motion under reduced motion | the reference's chevron and select turns and the pilot's copies keeping their duration | **rule** D-DS-17 | §8 |
| DA-13 | A and N — the counts and the evidence run as run | the run's facts | documented in the ledger; no disposition needed | — |

### A.2 WF-PC1's 33 carried findings

| Finding | Label | Disposition | The rule, or the reason |
| --- | --- | --- | --- |
| GF-01 | A | rule | a page's overflow actions are the reference's overflow menu with its toggle, never a bare outline link (§9.4) |
| GF-02 | A | dropped | the "Simulasi" chip is the prototype's simulation marker (SIMULATION_REGISTER), absent from V1 |
| GF-03 | A | rule | a document's layout choice uses the reference's draft-document choice, never the preview's zoom toggle (§14.3) |
| GF-04 | A | rule | MSG-39's dialog: the question in the title, the consequence and the correction in the body (§9.4; with PB-D01) |
| GF-05 | A | rule | an estimate is marked in text — "(perkiraan)" —, never with a pill (§16; DC-3; UXI-14) |
| GF-10 | A | rule | a passed check's icon is the reference's 16 px check in the success tone (§12.1) |
| GF-12 | A | rule | a field that depends on another stays disabled, its reason beside it, until the field it depends on is chosen (§9.3) |
| GF-13 | A | rule | on a phone row an amount takes its own line below the main column before the column falls under its minimum (§11) |
| GF-14 | A | rule | a tab row that overflows on the phone scrolls, the reference's edge mask its cue (§9.2) |
| GF-15 | A | rule | a long form's sticky bar holds "Batal" before the primary action (PT-09; §9.4) |
| GF-16 | A | rule | every required field carries the reference's required mark (§9.3) |
| GF-17 | A | rule | a list's filters are the reference's filter controls — label, value, chevron — in its toolbar (§9.2) |
| GF-20 | A | rule | a figure never breaks inside itself; its box widens or its row wraps (§16) |
| FA-06 | N | rule | the business-date line shows the chosen date once one is chosen (PT-25; §9.3) |
| FA-25 | N | dropped | the "Simulasi" chip is prototype-only (GF-02) |
| GF-08 | N | accepted and documented difference | the floor task's sheet head holds the 48 px "Kembali" with the reference's padding, which D-DS-19 causes |
| GF-18 | N | rule | a list's columns keep their widths when a status appears in a row (§9.2) |
| GF-22 | N | rule | a composer's totals are the reference's totals band at the editor's foot (§14.1) |
| GF-23 | N | rule | a page's secondary links sit in its actions or overflow menu, never as a loose button under the last card (§9.1) |
| GF-24 | N | rule | on the phone, meta items sharing a line keep a visible separator (§11) |
| GF-25 | N | rule | a company chip is followed by its context text or sits inline with the row's note (§12.1) |
| GF-28 | N | rule | one-line choices are a radio group; the reference's choice cards hold a title with its description (§9.3) |
| GF-42 | N | rule | a fold opens with its chevron's turn only, as the reference's help fold (§8) |
| FA-09 | D | dropped (CA-04 item 16) | the full-load fallback is the prototype router's; Inertia's visits set focus and scroll on each visit (§8) |
| FA-15 | D | dropped (CA-04 item 16) | the review panel is prototype-only |
| FA-16 | D | dropped (CA-04 item 16) | the code steps' controls stay AUTH_JOURNEYS' and OQ-14's |
| FA-18 | D | dropped (CA-04 item 16) | V1's search finds codes exactly under IP-17 (INT-005); the gap was the prototype palette's |
| FA-20 | D | rule (D-DS-21) | the current step's "(langkah n dari m)" sits inside its own step's item (§15) |
| FA-21 | D | rule (D-DS-21) | each group of a worklist has its own heading (§15) |
| FA-22 | D | rule (D-DS-21) | a search or filter that matches nothing says so in a status line, announced (§15) |
| FA-23 | D | dropped (CA-04 item 16) | the prototype's fixed sentences; V1 answers with MSG-02's actual rule and facts |
| GF-21 | D | dropped (CA-04 item 16) | a presentation refinement of the result |
| GF-38 | D | dropped (CA-04 item 16) | the payment form's summary aside, a presentation refinement |

### A.3 SP-52's items on *Buat Pembelian*

| Item | Disposition | The rule |
| --- | --- | --- |
| PB-A12 | rule | PT-25's business-date line has one form on every page that shows it, *Terima Barang*'s included (§9.3) |
| PB-D01 | rule | the confirmation dialog's hierarchy: the question in the title, the consequence and the correction in the body (§9.4; GF-04) |
| PB-D02 | rule | "Rincian lain"'s fields on the reference's widths — a reference number on the reference-field track, an amount at the money control's width, the charge's heading in the reference's in-form subhead role (§9.3) |

## Annex B. The carried items GR-01–GR-23

DESIGN_SYSTEM_MAPPING §7, normative here — its section numbers are that document's —; the later GR rules are [DEVIATION_REGISTER](stage2/DEVIATION_REGISTER.md)'s, each applied where this document names it.

The [carried items](evidence/P7_REBASELINE_GATE.md#carried-items-gr-01) as [DIR-061](../00-governance/DECISION_LOG.md#dir-061-dir-062-obs-023-and-tech-031--p7-ux-re-baseline-stage-2-decisions-s2-0-authorization-baseline-and-prototype-setup) SD-02 corrects them — GR-09's clause "every logout confirmed" withdrawn, [MSG-35](ADMIN_FLOW/s14-15-messages-scenarios.md#14-message-catalogue) governing; GR-20 presentation only; GR-16 judged at the Owner's preview in two variants. [DEVIATION_REGISTER](stage2/DEVIATION_REGISTER.md) tabulates their current state; GR-24 onward are its own.

| GR | Carried item | What S2-1 settles | What S2-2 or the preview checks | What stays for Stage 3 or P11 |
| --- | --- | --- | --- | --- |
| GR-01 | the control edge `#898982` | `--color-border-input`, shadcn/ui's `--input` (§§1.1, 1.2) | SV-05: the edges of fields, selects, date fields, checkboxes and radios at 3:1 on each surface | the normative token (Stage 3) |
| GR-02 | an olive mark on the bottom bar's current item, the selected segment and the sign-in toggle | §§3, 6 | SV-05: the marks at 3:1 | Stage 3 |
| GR-03 | the olive ring on the current stage marker | §§1.4, 3 | SV-05 on S2-J03's journey | Stage 3 |
| GR-04 | the per-surface greys | §1.2 | SV-05: each grey measured on its surface | normative in Stage 3; a lint rule in P11 |
| GR-05 | reflow at 320 px | §6 | SV-02 at 320 px: no horizontal scroll but a table's | verified in Stage 4 |
| GR-06 | rem at a 16 px root; the 1.4.12 and 2.4.11 checks | §1; scroll padding under the sticky regions (§3) | SV-05: the text-spacing override and focus not obscured, as the probe's items h) and e) show possible ([PROTOTYPE_PLAN](stage2/PROTOTYPE_PLAN.md)) | the rem keys (P11) |
| GR-07 | 48 px warehouse and field controls; ≥ 44 px with a coarse pointer | §§2, 6 | SV-05: target sizes on S2-J05 at 390 and 320 px | Stage 3 |
| GR-08 | position numbers with "Muat Lagi"; no pager or total | §3; [PT-05](#92-lists-tables-and-filters), PT-08 | the lists of the journeys | PT-05, PT-06 and PT-08 aligned (Stage 3) |
| GR-09 | the confirmation mapping without a typed confirmation or tick; logout asking only when input is unsent (MSG-35) | §4: PT-26, IP-08 | S2-J01's logout with and without unsent input | ADMIN_FLOW aligned (Stage 3) |
| GR-10 | the composer within VD-07's share, DC-2's number line, defined inputs, provisional totals, [GAP-053](../00-governance/GAP_REGISTER.md#triage)'s producer | [DOCUMENT_PREVIEW_PRODUCER](stage2/DOCUMENT_PREVIEW_PRODUCER.md)'s option A; §3 | S2-J03's composer, simulated | the normative producer (Stage 3); the template (P11); P8's tests |
| GR-11 | *Lainnya* as a drawer or a sheet; the bottom bar hidden on a focused scan step | §6: the reference's drawer | S2-J05's scan step; the prototype at commit D shows the drawer — no C2 confirmation is recorded (`DIR-079` CD-01) | settled in Stage 4 |
| GR-12 | the abstract sign-in panel, the code entry, the authentication journeys | [AUTH_JOURNEYS](stage2/AUTH_JOURNEYS.md); §3's code entry | S2-J01's screens | Stage 3; P11 |
| GR-13 | the Google button to Google's guidelines, its font's self-hosting | the prototype's drawing is an Owner question in PROTOTYPE_PLAN | the preview shows the simulated button | the Architect, in P11 with GAP-042 (P9) |
| GR-14 | whether a UI preference persists; the company view and the period out of browser storage | the language in [LOCALIZATION_ARCHITECTURE §3](stage2/LOCALIZATION_ARCHITECTURE.md#3-persistence--stated-not-decided); the sidebar's state kept nowhere in the prototype (SS-07), its persistence in the application stated, not decided, under C-1 | the prototype stores nothing (SP-13's guard; the probe's item k)) | Stage 3 under C-1 |
| GR-15 | toasts only for events that leave a visible trace | §§3, 4 (PT-15) | no journey message only in a toast | PT-15 aligned (Stage 3) |
| GR-16 | the smallest desktop text | §1's variants A and B | SV-03 renders both; the Owner chooses (SD-08) | the choice recorded: variant B (D-DS-10) |
| GR-17 | the reference's micro-copy | [LOCALIZATION_ARCHITECTURE §2.6](stage2/LOCALIZATION_ARCHITECTURE.md#26-the-references-micro-copy-gr-17-gp-5) | its string check; the vendored strings in DEVIATION_REGISTER | the localization contract (Stage 3) |
| GR-18 | Lucide styled to the reference's strokes | §1.4's strokes; Lucide 1.56.0 pinned (SP-16) | the prototype's sprite from the release's SVG files | the icon set under [DEP-07](../01-product/V1_SCOPE.md#dependencies-and-evidence-needed) (P11) |
| GR-19 | date-range presets bound to the periods | §3 | a period control where a journey screen shows one (Part 6) | Stage 3 |
| GR-20 | the filter toolbar, presentation only | §4: PT-07 | the journeys' lists | PT-07's presentation aligned (Stage 3) |
| GR-21 | desktop disclosures where R1 folds | §3 | S2-J03's *Pekerjaan* and the journey's folded stages | Stage 3 |
| GR-22 | opaque phone bars | §§1.4, 2, 6 | SV-01 and SV-02 on the phone renders | Stage 3 |
| GR-23 | the ageing chart with labels, amounts and a table | §§1.2, 3 | S2-J02's *Beranda* | Stage 3 |

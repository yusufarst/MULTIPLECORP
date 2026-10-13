# Design system mapping

Status: DRAFT | Updated: 2026-10-14 | Owner: Planning

**Draft — not normative.** This is an S2-1 document of Stage 2 of the P7 UX re-baseline, written under [DIR-061](../../00-governance/DECISION_LOG.md#dir-061-dir-062-obs-023-and-tech-031--p7-ux-re-baseline-stage-2-decisions-s2-0-authorization-baseline-and-prototype-setup) SD-01, SD-05 and SD-10 and [DIR-064](../../00-governance/DECISION_LOG.md#dir-063-dir-064-obs-024-and-tech-032--p7-ux-re-baseline-s2-0-acceptance-s2-1-authorization-baseline-and-stage-2-documents) §12.9 (BP-2; BP-10). Behaviour follows MULTIPLECORP — P0–P6 and ADMIN_FLOW —, structure follows R1, and presentation follows the Golden Frontend Reference v1.0.0, which adapts to MULTIPLECORP and never the reverse (AK-1; SD-02). [INFORMATION_ARCHITECTURE](../INFORMATION_ARCHITECTURE.md) and [DESIGN_SYSTEM](../DESIGN_SYSTEM.md) stay APPROVED under APPR-008 and under replacement, and [ADMIN_FLOW](../ADMIN_FLOW/README.md) stays in force, until a later authorized stage changes them; Stage 3 uses this draft, and nothing in it is normative.

**Sources.** The reference's five vendored stylesheets in the prototype's working copy at `503e83b`, read-only (SP-17) — `tokens.css` (SHA-256 `d6ded788fc73127e47f1247814ca66df96c4c9e5f79f0c2a3c9ddbc7a4ab252c`, 424 custom properties), `base.css`, `components.css`, `patterns.css` and `motion.css`; the gate record's [GA1 reconciliation](../evidence/P7_REBASELINE_GATE.md#c1-closure--golden-frontend-reference-reconciliation) — tokens a), components b), patterns c), principles d), navigation e), sign-in f), storage g) — and its carried items GR-01–GR-23 as DIR-061 SD-02 corrects them; [DESIGN_REFERENCES §§5–7](../DESIGN_REFERENCES.md#5-draft-mapping-to-shadcnui); DESIGN_SYSTEM [§§9–17](../DESIGN_SYSTEM.md#9-patterns) — PT-01–PT-31, [§12.1](../DESIGN_SYSTEM.md#121-status-system)'s state meanings, [§15](../DESIGN_SYSTEM.md#15-accessibility)'s accessibility rules —; DIR-055 OC-06, OC-07 and OC-09; DIR-058 VP-2–VP-8; the SLOP rules as R1 and GA1 revised them. **Official documentation, accessed 2026-10-14 (SP-18; GA1's verifications done again, not copied):**

- shadcn/ui — [theming](https://ui.shadcn.com/docs/theming), [components index](https://ui.shadcn.com/docs/components), [Tailwind v4 guidance](https://ui.shadcn.com/docs/tailwind-v4), the component pages Sidebar, Sheet, Drawer, Toast and Input OTP under `https://ui.shadcn.com/docs/components/base/`, and the [changelog](https://ui.shadcn.com/docs/changelog) — entries to September 2026, among them "Base UI as the Default", "React Aria" and "Toast" in July 2026 and "shadcn/cli v4" in March 2026; no page shows a version or date.
- Tailwind CSS, documentation v4.3 — [theme variables](https://tailwindcss.com/docs/theme) and [font size](https://tailwindcss.com/docs/font-size).
- Base UI v1.9.0 — [Dialog](https://base-ui.com/react/components/dialog), [Menu](https://base-ui.com/react/components/menu) and [Toast](https://base-ui.com/react/components/toast).
- Radix UI — [Dialog](https://www.radix-ui.com/primitives/docs/components/dialog) and [Dropdown Menu](https://www.radix-ui.com/primitives/docs/components/dropdown-menu), imported from the `radix-ui` package; no version shown.
- Inertia.js — [layouts](https://inertiajs.com/docs/v3/the-basics/layouts), [partial reloads](https://inertiajs.com/partial-reloads), [deferred props](https://inertiajs.com/deferred-props) and [shared data](https://inertiajs.com/shared-data); the pages link the v3 documentation and state no version.
- Lucide 1.56.0, published 2026-10-13 ([release](https://github.com/lucide-icons/lucide/releases/tag/1.56.0); [licence](https://lucide.dev/license): ISC, and MIT for the icons derived from Feather).
- Inter 4.1, published 2024-11-16 ([release](https://github.com/rsms/inter/releases/tag/v4.1)), SIL Open Font License 1.1, no Reserved Font Name.
- [WCAG 2.2](https://www.w3.org/TR/WCAG22/), W3C Recommendation of 12 December 2024.

The choice between Base UI and Radix UI, the primitive libraries shadcn/ui offers, stays P11's under [DEP-07](../../01-product/V1_SCOPE.md#dependencies-and-evidence-needed) ([DESIGN_SYSTEM §10](../DESIGN_SYSTEM.md#10-component-composition)); where the two differ this mapping names the difference, and what the documentation does not establish is marked "needs verification at implementation".

## 1. Tokens

**Three layers, one source** (proposed, Level 1). The application keeps the reference's token names and values as CSS custom properties — the `--brand-*` set remaining the only one a project re-tints —; the shadcn/ui theme variables point to them; and the Tailwind CSS theme keys expose them to utilities. Tailwind's theme variables "aren't _just_ CSS variables": a key in a namespace generates utilities, and a theme variable that references another variable belongs in `@theme inline` (Tailwind v4.3, *Theme variables*). So a token whose name already is a Tailwind key — `--color-canvas`, `--radius-lg`, `--shadow-card`, `--font-sans` — is declared once in `@theme` with its literal value, and is both; a key that points to another variable, as shadcn/ui's `--color-primary: var(--primary)`, sits in `@theme inline`. Every default namespace the tokens replace is cleared with `initial` — `--color-*: initial` removes "all of the default utilities that use that namespace" (Tailwind v4.3) —, so no utility brings a value outside the token set. Values stay the reference's hex; a conversion to OKLCH, the form shadcn/ui's default theme uses, is optional and keeps the computed colour. Sizes are written in rem at a 16 px root, the desktop text unit kept as a factor ([GR-06](../evidence/P7_REBASELINE_GATE.md#carried-items-gr-01)). How the keys are generated and linted is P11's — needs verification at implementation.

**GR-16 for the Owner's preview** (SV-03; [SD-08](../../00-governance/DECISION_LOG.md#dir-061-dir-062-obs-023-and-tech-031--p7-ux-re-baseline-stage-2-decisions-s2-0-authorization-baseline-and-prototype-setup)): variant A is the reference's standard, the desktop text unit `--tu` at `clamp(.92px, …, .96px)` from 1,025 px, so 14 px body text renders about 12.9 px at 1,366 px; variant B fixes `--tu` at 1 px, so body text stays at 14 px and no size shrinks. The 12, 11 and 12.5 px sizes are fixed in both. The 10.5 px pill size is excluded in both (GR-16). The Owner chooses after the preview.

### 1.1 The shadcn/ui theme variables

The variables of shadcn/ui's theming page — surfaces and text, actions and emphasis, muted content, form and focus, charts, the sidebar set and `--radius` —, each pointing to a token; a new colour is added with its `-foreground` pair and exposed as `--color-` keys in `@theme inline` (shadcn/ui, *Theming*).

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
| Added, each with its pair where it has one | the status tones `--color-{success,warning,pending,danger,info,neutral}-{bg,fg,dot}`; the lime tints, the signal and the sign-in CTA; the category tints `--tint-*` | §1.2 |

### 1.2 Colour tokens

Each name is kept as the application's CSS custom property; the last column gives the Tailwind key, "CSS only" where no utility needs one. A token excluded here is not used by any journey screen or by the application.

| Tokens and values | Role | Disposition | Tailwind key |
| --- | --- | --- | --- |
| `--color-canvas` `#f7f8f3` · `--color-surface` `#ffffff` · `--color-surface-sunken` `#f2f3ee` · `--color-surface-warm` `#fdfcf4` | the page canvas; cards, panels and popovers; tracks, sunken buttons and disabled fields; warm panels | ADOPT | the same names |
| `--color-row-hover` `#f8f9f4` · `--color-row-press` `#f2f3ec` · `--color-sunken-hover` `#e9eae4` · `--color-avatar` `#dfe1e3` | row hover and press; a sunken control's hover; the avatar disc | ADOPT | the same names |
| `--color-surface-hero-start` `#f1f1ef` · `--color-surface-hero-end` `#f3f5f5` | the greeting hero's tint | EXCLUDE — no hero (VP-2; [SLOP-19](../DESIGN_SYSTEM.md#18-anti-ai-slop-mapping)) | — |
| `--color-border-subtle` `#f0f1ef` · `--color-border` `#e4e4e2` · `--color-border-strong` `#d8d8d6` · `--color-border-hover` `#d6d6d4` · `--color-header-divider` `#ecedec` · `--color-addon-divider` `#efefef` · `--color-separator` `#c6c6c4` · `--color-divider-label` `#dcdcdb` · `--color-border-dashed` `#e7e7e7` · `--color-card-hover-border` `#e3e4df` · `--color-card-press-border` `#dcddd7` · `--color-link-underline` `#c9cacd` | dividers, card edges, separators, their hover and press; a link's underline — decorative, never a control's identifying edge | ADOPT | the same names |
| `--color-border-control` `#e0e0de` · `--color-border-field` `#dededc` · `--color-border-field-dense` `#d7d7d5` · `--color-border-field-xl` `#d9d9d7` · `--color-border-icon-btn` `#e3e3e2` · `--color-border-toggle` `#e8e8e8` · `--color-border-kbd` `#e7e7e7` · `--color-border-field-hover` `#c8c8c6` · `--color-border-control-hover` `#cfcfcd` | the edges of fields, selects, header controls, icon buttons, toggles and keycaps, and their hover | ADAPT — the edge that identifies an input, select, date field or author-styled checkbox or radio is `--color-border-input` `#898982`: 3.52:1 on white, at least 3.16:1 on every surface (GR-01) | the same names; `--color-input` |
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
| `--brand-progress` `#a3b26a` · `--brand-step-done-line` `#e4e9cb` · `--brand-step-done-dot` `#dde7a6` | a process's progress; completed steps' line and disc | ADOPT — progress for processes only (SLOP-44), a completed step identified by its check | `--color-progress`, `--color-step-done-line`, `--color-step-done-dot` (proposed) |
| `--color-logo-teal` `#335f65` · `--color-logo-olive` `#84a64c` | the reference's MultipleCorp mark | EXCLUDE (GD-08; VP-5) | — |
| `--color-success-bg` `#e3f4e9` · `--color-success-fg` `#236b49` · `--color-success-dot` `#319664`; `--color-warning-bg` `#fff1e2` · `--color-warning-fg` `#8a4a0e` · `--color-warning-dot` `#d98a3d`; `--color-pending-bg` `#fef1d8` · `--color-pending-fg` `#7a5a22` · `--color-pending-dot` `#cb9d4e`; `--color-danger-bg` `#fde7e8` · `--color-danger-fg` `#b4373f` · `--color-danger-icon` `#9b2e30` · `--color-danger-dot` `#d0454c`; `--color-info-bg` `#e2ecf4` · `--color-info-fg` `#0f2b4f` · `--color-info-dot` `#5b8db5`; `--color-neutral-bg` `#f2f3ee` · `--color-neutral-fg` `#3a3b42` · `--color-neutral-dot` `#9b9da1` | the six status tones — tint, ink and dot; ink on its tint 4.99–11.86:1 | ADOPT — a state is a word with its icon (§5), never colour alone (D-DS-04; WCAG 1.4.1) | the same names; shadcn/ui pairs such as `--success` and `--success-foreground` added (proposed) |
| `--color-danger-solid` `#b4373f` · `--color-danger-solid-hover` `#9e2f36` · `--color-danger-rim` `#9b2e30` · `--color-required` `#cf4747` | the destructive button, its hover and edge; the required mark | ADOPT | the same names; `--color-destructive` |
| `--color-trend-up-bg` `#edf8f1` · `--color-trend-up-fg` `#2d5e3e` | the delta badge | EXCLUDE (VP-3) | — |
| `--tint-olive` `#eaf0d9` / `--tint-olive-ink` `#1a2e0d` · `--tint-blue` `#e1ecf4` / `--tint-blue-ink` `#0f2b4f` · `--tint-peach` `#f6ede1` / `--tint-peach-ink` `#432817` · `--tint-mint` `#deefe9` / `--tint-mint-ink` `#032f11` · `--tint-red` `#fce8e6` / `--tint-red-ink` `#9b2e30` · `--tint-lavender` `#ede5fc` / `--tint-lavender-ink` `#341d5d` · `--tint-tan` `#f1e6dc` / `--tint-tan-ink` `#432817` · `--tint-lime` `#f5f9df` / `--tint-lime-ink` `#556a08` · `--tint-neutral` `#f2f3ee` / `--tint-neutral-ink` `#0f1014` · `--tint-blue-strong` `#d5e7f3` · `--tint-peach-strong` `#faece0` | the category discs, a tint with its ink | ADOPT as identity wells only, never decoration (VD-02) | `--color-tint-*` (proposed) |
| `--chart-age-1` `#99b1a0` · `--chart-age-2` `#edd798` · `--chart-age-3` `#cbac8d` · `--chart-age-4` `#d56e5b` · `--chart-age-5` `#a9322e` | the receivable-ageing ramp, by severity | ADAPT — always with direct labels, amounts and a data table (VP-6; GR-23) | `--color-chart-age-1`…`-5`; shadcn/ui `--chart-1`–`--chart-5` |
| `--chart-track` `#e6e8eb` · `--chart-segment-gap` 2 px · `--chart-line-width` 1.5 px · `--progress-height` 10 px | the bar's track and gaps; a line's width; the progress bar | ADOPT for the ageing bar and process progress | CSS only |
| `--chart-cat-1` `#7e9e85` · `--chart-cat-2` `#82adc9` · `--chart-cat-3` `#e6d6c5` · `--chart-cat-4` `#dadee0` · `--chart-progress-1` `#94a469` · `--chart-progress-2` `#89b3cd` · `--chart-progress-3` `#ccaf90` · `--chart-olive-soft` `#c5d1a3` · `--chart-area-opacity-top` 0.4 · `--chart-donut-ring-ratio` 0.16 | categorical, progress, area and donut charts | EXCLUDE (VP-6; GR-23) | — |

### 1.3 Type tokens

| Tokens and values | Role | Disposition | Tailwind key |
| --- | --- | --- | --- |
| `--font-sans` "Inter", "Inter Fallback", "Segoe UI Variable Text", "Segoe UI", system fonts · `--font-mono` ui-monospace, "SF Mono", "Cascadia Mono", Consolas, "Liberation Mono", … · `--font-variation-text` `"opsz" 14` · `--numeric-figures` `tabular-nums` | the family with its cap-height-matched local fallback; codes and keycaps; Inter's text cut at every size; tabular figures for numbers | ADOPT — Inter 4.1 self-hosted under [WS-08](../../05-security/SECURITY.md#4-web-and-input-security)'s `font-src 'self'` | `--font-sans`, `--font-mono` |
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

### 1.4 Space, size, shape and layer tokens

| Tokens and values | Role | Disposition | Tailwind key |
| --- | --- | --- | --- |
| `--space-0-5` 2 · `--space-1` 4 · `--space-2` 8 · `--space-3` 12 · `--space-4` 16 · `--space-5` 20 · `--space-6` 24 · `--space-8` 32 · `--space-10` 40 · `--space-12` 48 · `--space-14` 56 · `--space-16` 64 · `--space-18` 72 px | the spacing scale, a 4 px base with a 2 px step | ADOPT (SLOP-33) | `--spacing: 0.25rem`, each step a multiple of it |
| `--page-gutter` 16 · `--grid-gap` 12 · `--card-padding` 20 · `--card-header-icon-gap` 12 · `--search-max-width` 592 · `--header-height` 76 · `--header-control-top` 18 · `--footer-height` 64 px | page gutter, card grid gap and padding, the top bar's height and search width, the sticky footer | ADOPT, geometry × `--du` on the desktop | CSS only |
| `--sidebar-width` 264 · `--sidebar-inset-x` 16 · `--nav-item-height` 44 · `--nav-item-gap` 6 · `--nav-icon-inset` 12 · `--nav-icon-label-gap` 20 · `--nav-indicator-width` 3 · `--nav-indicator-inset-y` 8 px | the sidebar and its entries, with the 3 px indicator | ADOPT — R1's entries (GD-03; GA1 e)); at least 220 px at runtime | shadcn/ui Sidebar's `--sidebar-width`, set from the token instead of its 16rem default |
| `--auth-card-width` 1336 · `--auth-split` 13fr 11fr · `--auth-form-width` 487 · `--auth-visual-inset` 48 px | the sign-in's split card | ADOPT, the visual panel abstract (GR-12) | CSS only |
| `--radius-xs` 4 · `--radius-sm` 6 · `--radius-md` 8 · `--radius-lg` 10 · `--radius-xl` 12 px · `--radius-full` 9999 px | the radius family; cards 10, dialogs 12, sheets' top corners 16 | ADOPT (SLOP-03, SLOP-31 revised) | the same names, set explicitly; `--radius` = `--radius-lg` |
| `--control-xs` 24 · `--control-sm` 32 · `--control-md` 36 · `--control-lg` 40 · `--control-header` 42 · `--control-xl` 56 · `--control-xl-field` 55 · `--control-add-row` 28 px | control heights — round chevrons, small, fields and default buttons, page actions, top-bar controls, sign-in | ADOPT with the density floors (§2); ADAPT — 48 px on warehouse and field tasks, ≥ 44 px targets with a coarse pointer (GR-07) | CSS only, read by the components |
| `--badge-height` 24 · `--badge-height-lg` 28 · `--badge-height-sm` 22 · `--badge-dot` 8 · `--badge-dot-lg` 9 · `--keycap-height` 22 · `--keycap-min-width` 23 px | status pills and dots; keycaps | ADOPT | CSS only |
| `--stepper-disc` 30 px · `--stepper-disc-bg` `#f3f4f5` · `--stepper-ring` `#d0d0d2` · `--stepper-line` `#d9d9db` | the stage track's markers and connectors | ADOPT; the current marker ringed in olive (GR-03) | CSS only |
| `--icon-lg` 24 · `--icon-md` 20 · `--icon-sm` 16 · `--icon-xs` 12 px · `--icon-stroke` 1.75 · `--icon-stroke-sm` 1.5 · `--icon-stroke-xs` 1.25 · `--icon-stroke-lg` 2 · `--icon-button-round` 24 px | icon boxes and their strokes | ADOPT — Lucide drawn at these strokes (GR-18; SP-16) | CSS only |
| `--icon-disc-lg` 56 · `--icon-disc-kpi` 52 · `--icon-disc-row` 42 · `--icon-disc-md` 40 · `--icon-disc-sm` 32 px | icon wells | ADOPT as identity markers | CSS only |
| `--shadow-xs` · `--shadow-card` `0 1px 3px` at 4 % · `--shadow-card-hover` · `--shadow-pop` · `--shadow-raised` · `--shadow-menu` · `--shadow-toast` · `--shadow-modal` · `--shadow-drawer` · `--shadow-footer` · `--shadow-cta` | controls, cards and their hover, toggles and tab bars, the sign-in card, menus, toasts, dialogs, drawers, the sticky footer, the sign-in button | ADOPT (SLOP-14) | the same names in `--shadow-*` |
| `--shadow-palette` (two drop shadows) | the command palette | ADOPT | `--drop-shadow-palette` |
| `--color-focus` = `--brand-accent` · `--focus-ring-width` 2 px · `--focus-ring-offset` 2 px · `--focus-ring` 3 px halo · `--focus-ring-danger` 3 px danger halo | the focus outline and a field's halo | ADOPT — 6.03–7.00:1 on every surface | `--color-focus`; `--color-ring` |
| `--z-raised` 2 · `--z-bar` 30 · `--z-popover` 80 · `--z-sheet` 90 · `--z-drawer` 95 · `--z-modal` 100 · `--z-toast` 110 · `--z-skip` 120 | the layer scale up to the skip link | ADOPT | CSS only — Tailwind's table lists no layer namespace |
| `--safe-top`, `--safe-right`, `--safe-bottom`, `--safe-left` (the safe-area insets) · `--touch-min` 44 · `--m-gutter` 18 (20 from 375 px) · `--m-section-gap` 28 · `--m-card-gap` 14 · `--m-card-pad` 18 · `--m-row-min` 60 · `--m-control-h` 48 · `--m-control-r` = `--radius-md` · `--m-appbar-h` 56 · `--m-bottom-nav-h` 60 · `--m-action-bar-h` 72 px | the phone composition — insets, gutters, rows, controls, the app bar, the bottom bar and the action bar | ADOPT; the bars opaque (GR-22) | CSS only |
| `--select-menu-pad` 8 · `--select-row-h` 42 · `--select-row-px` 14 · `--select-row-r` = `--radius-sm` · `--select-menu-r` = `--radius-lg` · `--select-menu-max-h` 320 · `--select-sheet-row-h` 52 px | the custom select's popover and its phone sheet | ADOPT | CSS only |
| `--upload-success` · `--upload-success-dot` · `--upload-success-bg` (aliases of the success tone) | the upload's success state | ADOPT | CSS only |
| `--du`, `--dt`, `--tu`, `--iu` (1 px on phones and by default) · `--density-scale` (1; .9 from 1,025 px) | the runtime units: from 1,025 px `--du` follows the viewport's height and width, × .9, `--dt` half its compression, `--tu` `clamp(.92px, …, .96px)`, `--iu` = `--tu`; tablets 768–1,024 px `--du` .86–.94 from the height; the reference's sign-in page keeps 1 px | ADOPT (GR-06; GR-16's variants) | CSS only; the factor applied to rem keys (GR-06) |

### 1.5 Motion tokens

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

**Coverage.** §§1.2–1.5 name every custom property of `tokens.css` — 424 names, 436 declarations with the breakpoint overrides —, each in one row, checked by script against the file.

## 2. Typography, spacing, radius, elevation, motion and density

The rules that use the tokens of §1; each keeps a canonical rule of DESIGN_SYSTEM where one exists ([§§5–8](../DESIGN_SYSTEM.md#5-tokens)), with the reference's values (GA1 a) and d)).

**Typography.**

- Inter's text cut at every size, the four weights, tabular figures for every number in an input, a cell or a summary ([OC-09](../../00-governance/DECISION_LOG.md#dir-055-dir-056-obs-020-and-tech-028--p7-ux-re-baseline-checkpoint-c1-decision-rework-authorization-baseline-and-rework)). Hierarchy by size and ink tier, never by colour alone.
- Desktop roles at the canvas, × `--tu`: title 32/40; figures 28/36 and 22/28; heading 20/28 and card titles 16.5–21; lead 17/26; body 15/22 and 14/20; label 13/18; caption 12/16; micro 11/16. Phone: title 25/31 (26/32 from 400 px), heading 18/24, row 15/20, body 15/22, meta 12.5/17, button 15, figure 17/22, total 22; text fields 16 px on touch (D-DS-02).
- A display size only on the sign-in (SLOP-26); page titles on the reference's scale, never a hero (SLOP-27; VP-2).
- Nothing below 11 px; the 10.5 px pill excluded; the legibility of the smallest desktop text judged at the Owner's preview in [GR-16](../evidence/P7_REBASELINE_GATE.md#carried-items-gr-01)'s two variants (§1).
- Capitalization, punctuation and formats are DESIGN_SYSTEM §16's and [LOCALIZATION_ARCHITECTURE](LOCALIZATION_ARCHITECTURE.md#24-formats)'s; the reference's micro-copy is never wording (GP-5).

**Spacing.** The 4 px scale with its 2 px step (SLOP-33): card padding 20 px, 18 on phones; 12 px between cards, 14 on phones; 28 px between sections on phones; 8 px from a label to its control and from a control to its supporting text — the reference's gap, which DESIGN_REFERENCES' alignment specification keeps (OC-09). Macro breathing room, micro density: sections separated by space and rules, not by a card each (SLOP-01; AICWDF §18.2).

**Radius.** Cards 10 px, controls about 0.18 × their height, menus 10 with 6 px highlights, dialogs 12, sheets 16 on their top corners; nothing else above 12 px (SLOP-03, SLOP-04).

**Elevation.** A card's barely visible resting shadow with its border carrying the edge; menus, toasts, dialogs, drawers and the palette with their own shadows over the scrim (SLOP-14); the phone's app bar and bottom bar opaque, without the reference's blur (GR-22; SLOP-10).

**Motion** (VP-8, as GA1 d) reconciles it).

- Functional only: state changes, the page-state transition with the shell still, panels, drawers, sheets, dialogs, menus and feedback; nothing tied to scrolling; focus never animated; a spinner only after 300 ms; data-ready to the last element within 900 ms; no transition delays focus or input.
- Excluded: counts rolling per digit, the emphasis curve, chart reveals, looping. The sign-in's restrained parallax stays inside its one transition between steps (VD-05; SLOP-16 revised).
- Under `prefers-reduced-motion: reduce`: no translate, scale, blur, clip, parallax, stagger, roll or stroke drawing; a 150 ms linear crossfade; indicators jump; a page swap instant (SLOP-15 revised).
- In the application the reference's router is replaced by Inertia's persistent layouts, which keep a layout "alive between visits" (Inertia v3, *Layouts*): the shell stays mounted, the page region takes the page-state transition, focus and scroll are handled on each visit (GA1 c) 10) — needs verification at implementation.

**Density and size.**

- Desktop: the density layer `--du` with `--density-scale` 0.9 at browser zoom 100 %, control floors of 34 px for header controls, 32 large, 30 medium and 28 small; one control height per density — 36 px for fields and default controls, 40 px for page actions, 32 px inside cards (OC-09).
- Phone: fields and primary buttons 48 px, every other target at least 44 px, rows at least 60 px, bottom-bar items at least 60 × 60 px, sheet rows 52 px.
- Warehouse and field task screens — the pages [IA_PAGE_INVENTORY](IA_PAGE_INVENTORY.md) marks `[floor]`, PG-022 *Terima Barang* and PG-024 *Keluarkan Barang* among them —: every control 48 px; a coarse pointer gets targets of at least 44 px at every width (GR-07; DESIGN_SYSTEM UXV-10).
- No target below 24 × 24 CSS px on the desktop — WCAG 2.2 2.5.8 Target Size (Minimum), level AA, "at least 24 by 24 CSS pixels" with its exceptions.
- The dense mode only on OC-09's named screens: the multi-line editors of quotations and purchases (PG-014, PG-017), *Riwayat Stok* (PG-020), *Mutasi Bank* (PG-050), *Laporan* and *Laporan Gabungan* (PG-051, PG-052), *Riwayat Aktivitas* and *Log Keamanan* (PG-063, PG-064).
- The alignment specification of [DESIGN_REFERENCES §7](../DESIGN_REFERENCES.md#alignment-specification-oc-09): one control height per row, a stated column grid for multi-line editors — S2-2 states the purchase line's proportions on the reference's geometry —, numbers right-aligned with tabular figures, secondary metadata on its own line below the control row, less-common fields behind "Rincian lain", a summary beside or below the form it totals.

## 3. Components

Every component the [page inventory](IA_PAGE_INVENTORY.md)'s 65 pages need — 61 inside the shell, PG-001–PG-004 outside it —, from the reference's anatomy (GA1 b)) to the shadcn/ui component or composition that builds it, each name checked in shadcn/ui's components index on 2026-10-14. **The states**, common to every interactive component unless its row says otherwise: *default*; *hover* — a tint within 140 ms, never the only path ([SLOP-17](../DESIGN_SYSTEM.md#18-anti-ai-slop-mapping)); *focus-visible* — the 2 px olive outline with its 2 px offset, instant, a field's border turning olive under its 3 px halo; *active* — a press tint, buttons scaling to 0.97; *selected* — a lime fill with an olive mark and `aria-selected` or `aria-current`, never the fill alone ([GR-02](../evidence/P7_REBASELINE_GATE.md#carried-items-gr-01)); *disabled* — at 48 % with the reason beside it, the state that blocks it or "Perlu kewenangan …" — an action the account may not perform is absent, not disabled ([AZ-01](../../05-security/PERMISSIONS_MATRIX.md#2-authorization-model) as a hint; GA1 d)); *pending* — the control keeps its width and label, a spinner after 300 ms, one execution in flight and the inputs frozen while the outcome is unknown ([ADMIN_FLOW](../ADMIN_FLOW/README.md#admin-and-owner-flows--interaction-model) IP-02); *error* — a danger border, `aria-invalid` and the message in text below (WCAG 2.2 3.3.1); *read-only* — the value as text with its source where it is carried ([DC-5](../../00-governance/DECISION_LOG.md#dir-057-dir-058-obs-021-and-tech-029--p7-ux-re-baseline-checkpoint-c1-visual-direction-decisions-visual-checkpoint-authorization-baseline-and-visual-anchors)); *empty* — the Empty composition, with one action only where the account may act.

| Component | The reference's anatomy | shadcn/ui | Density, target and accessibility | Pages |
| --- | --- | --- | --- | --- |
| Shell — sidebar, *Menu lengkap*, icon rail, top bar, skip link | 24 px icon and 15/500 label; the current entry a lime 150 pill, 44 px × `--du`, with a 3 px olive indicator; the group header a disclosure only; the rail on Ctrl B; "Cari atau pindai kode", "Buat" and the account menu in the top bar | Sidebar — `collapsible="icon"`, Ctrl or Cmd B, a Sheet on mobile —, Collapsible, Dropdown Menu, Avatar, Tooltip | exactly one `aria-current` per landmark; nothing remembered across pages; no count on an entry ([D-IA-06](../INFORMATION_ARCHITECTURE.md#4-design-decisions)) | the 61 shell pages, PG-005–PG-065 |
| Phone app bar and bottom bar; *Lainnya* | a 56 px bar with the contextual back and the title; R1's four items, a 22 px icon in a 54 × 30 pill over an 11.5/500 label; a modal drawer, `min(86vw, 340px)`, or a full-height sheet | custom bars — shadcn/ui has no bottom bar —; Drawer, built on Base UI with `swipeDirection` (shadcn/ui, *Drawer*), or Sheet | items ≥ 60 × 60 px; the current item with an olive mark (GR-02); opaque (GR-22); the bar hidden on a focused scan step with its action bar (GR-11) | the shell pages below 768 px |
| Page header | title, ancestors-only breadcrumb, a meta line, one primary and "Tindakan Lain" | Breadcrumb, Button, Dropdown Menu | one primary per region (C1A-2; PT-03) | every shell page but PG-005 |
| Button | primary, outline, ghost, sunken, accent, danger, the sign-in CTA; 32/36/40/42/56 px; loading keeps width and label | Button — default, outline, ghost, secondary for sunken, destructive —, accent and sign-in variants added | icon-only ≥ 24 px on the desktop and ≥ 44 px on the phone, with an accessible name | all |
| Split action | a primary segment and a chevron, "Aksi lain", opening a menu, destructive last | Button Group with Dropdown Menu | Enter, Space and the arrows; no draft entry where a form has one saving command ([WF-PUR-01](../../03-workflows/WORKFLOWS/s05-03-inventory-purchasing.md#wf-pur-01--project-purchase-desk)) | record workspaces and forms with several commands |
| Field and number entry | label above, 8 px gap; the box with an optional addon; invalid with its message; 36 px desktop, 48 px phone, 16 px text on touch | Field, Label, Input, Input Group, Textarea | the control edge `#898982` (GR-01); money and quantities parsed by IP-13's Indonesian separators, the formatted value shown on leaving the field | the forms, task steps and settings |
| Select and lookup | a combobox trigger over the native select, a listbox popover with 42 px rows and an olive check, a 52 px-row sheet on phones; the type-ahead lookup | Select, Native Select, Combobox, with Drawer on phones | arrows, Home, End, typeahead, Esc; a master chosen by lookup, never retyped (IP-17) | forms and toolbars |
| Date field and business date | "Tanggal transaksi" with a calendar addon, a typed fallback, no date after today | Popover with Calendar; Date Picker | IP-07's semantics and copy (PT-25) | forms with a business date |
| Date range | presets and a custom range; a start/end sheet on phones | Popover with Calendar in range mode, Toggle Group; Drawer on phones | presets bound to the repository's periods (GR-19) | PG-051, PG-052, PG-063, PG-064 and dated lists |
| Code entry | six cells with a filled-cell rule | Input OTP — built on `input-otp`, with copy and paste, `REGEXP_ONLY_DIGITS` and `maxLength` (shadcn/ui, *Input OTP*) — or one input with `autocomplete="one-time-code"` | paste and autofill work either way; the page does not state the `autocomplete` attribute, so the choice is S2-2's with AUTH_JOURNEYS (GR-12) — needs verification at implementation | PG-002, PG-004, PG-065 |
| Checkbox, radio and switch | author-styled marks | Checkbox, Radio Group, Switch | the control edge at 3:1 (GR-01) | forms and settings |
| Status pill and status text | a badge, 24 or 28 px, tint with its ink and a dot; a row density with a 7 px dot and a 13 px label | Badge; the row density custom | one state per badge, the word and the icon of §5, never colour alone | lists, records, the journey |
| Company tag and count badge | a company code; "perusahaan lain"; a count of derived signals | Badge, outline | counts per company outside navigation (CS-08; D-IA-06) | lists of several companies, PG-006, PG-007 |
| Card and section card | white surface, subtle border, radius 10, resting shadow; a text-first header with one action slot; no card in a card | Card | a card only for a distinct object, a group scanned as one or a region with its own state (SLOP-01, SLOP-02) | PG-006, PG-007, PG-011 and the record workspaces |
| KPI tile | disc, label and value, the whole card a link | Card | no delta, sparkline or period comparison (VP-3); four on the Owner's *Beranda* (SLOP-18) | PG-006 |
| Facts, key-value and metric strip | header facts; detail attributes; key figures in one strip with hairlines | a composition with Separator — description lists have no primitive | numbers right-aligned with tabular figures (OC-09) | the record workspaces, PG-011, PG-043 |
| Tabs | real tabs with `?tab=`, overflow into "Bagian Lain", the selected tab underlined in primary | Tabs | the address keeps the tab; arrows between tabs | the record workspaces; *Gudang*'s and *Keuangan*'s tabs |
| Segmented control | views inside one section on a sunken track | Toggle Group | the selected segment with an olive mark (GR-02) | sections with views; the sign-in's "ID \| EN" |
| Data table and row list | one data set — a table from 768 px, rows on phones; a position number on every record list; columns tighten before rows form; rows ≥ 60 px with 44 px targets on phones | Table, the Data Table guide, Item; a "Muat Lagi" Button | position numbers continue across "Muat Lagi"; no pager, total or size choice ([PF-15](../../06-api-performance/PERFORMANCE.md#4-pagination); GR-08); a table scrolls only inside its frame (WCAG 1.4.10's exception) | the 25 lists, the 4 work queues and the 4 reports |
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
| Notice and alert | a notice band with its tone | Alert | the security notices and MSG-29's Owner variant on PG-006 ([AU-12](../../05-security/SECURITY.md#2-authentication-sessions-and-credentials); LG-07) | PG-006, PG-007, forms |
| Checks, result, next step and prerequisite | "Pemeriksaan" with "Perbaiki"; the result with the number as headline and "Salin nomor", the PDF state and one next action; "Perlu kewenangan …" with nothing to press | Item, Alert, Card, Button | the outcome in the action region (D-DS-06); no toast for the same event | forms, task steps, the composer |
| Disclosure | a real button with `aria-expanded` and `aria-controls`, ≥ 44 px, the chevron on the right; an invalid field never left hidden | Collapsible | desktop disclosures where R1 folds — the *Pekerjaan* groups, "Rincian lain", "Tahap yang tidak diperlukan" (GR-21) | PG-007, PG-011, forms |
| Position and inventory blocks | server figures in a table or a definition list | Table, Separator | server values only (UXI-14) | PG-019, PG-043, PG-044 |
| Multi-line editor | one add path — IP-13's empty last row with the catalogue search in its product picker; the alignment grid of §2; on phones each line edited in its own sheet (D-DS-07) | Table with Field, Input, Combobox | the dense mode on its named screens (OC-09); "Kembalikan isian" restores the last saved draft after asking | PG-014, PG-017 |
| Sticky action bar | one region above the bottom bar with the total and the one primary | a sticky region with Button | scroll padding so nothing focused hides under it (WCAG 2.4.11; GR-06) | forms and task steps |
| Upload | idle, dragging, uploading, success, error, with retry and announcements; drag and drop only as an extra | Attachment, or a file Input in a Field — the fit of Attachment checked in S2-2 | types and limits per purpose from FL-02 (IP-16) | PG-031, PG-038, PG-039, PG-058 and the evidence steps |
| Document composer and paper | the editor beside a sticky preview with "Pas lebar", "100 %" and "Layar penuh"; "Rincian \| Pratinjau" below 1,280 px; one sticky bar; the A4 paper | custom on Card, Tabs or Toggle Group and Dialog | VD-07's preview share; [DOCUMENT_PREVIEW_PRODUCER](DOCUMENT_PREVIEW_PRODUCER.md)'s option A | PG-036, PG-042 |
| Chart | the ageing bar | Chart, always with its data table | direct labels and amounts; the ramp of §1.2 (VP-6; GR-23) | PG-006, PG-044 |
| Progress bar, keycap, avatar, icon disc, link | a process's bar; shortcut keys; the account's initial; an identity well; underlined text links | Progress, Kbd, Avatar; the disc custom; a link | progress for processes only (SLOP-44); links underlined (WCAG 1.4.1) | uploads and imports; shortcut hints; the account menu |
| Sign-in compositions | the split card fitted to the viewport; the visual panel; the steps with one 600 ms clock; the language toggle | Card, Field, Input, Input OTP, Toggle Group | the panel abstract (GR-12); no editorial copy; the Google button to Google's guidelines ([PROTOTYPE_PLAN](PROTOTYPE_PLAN.md); GR-13) | PG-001–PG-004 |
| Print view | the page prepared for paper: header, applied filters as a line, page numbers | print styles | DESIGN_SYSTEM §17; a generated document printed from its rendition | lists, records and the draft paper |

**Not used** (GA1 b)): the pager and its total (PF-15), Skeleton, the delta badge (VP-3), the hub tiles, greeting and hero (VP-2; GD-04), the guidance level, resume memory and walkthrough (GA1 c) 6), and the demonstration actions (GD-04).

## 4. Patterns

The behaviour of every pattern stays canonical — DESIGN_SYSTEM's PT rules and [ADMIN_FLOW](../ADMIN_FLOW/README.md#admin-and-owner-flows--interaction-model)'s IP rules, PT-07, PT-08 and [PF-42](../../06-api-performance/PERFORMANCE.md#4-pagination) above all ([GR-20](../evidence/P7_REBASELINE_GATE.md#carried-items-gr-01)) —; the reference gives the presentation ([GD-02](../../00-governance/DECISION_LOG.md#dir-059-dir-060-obs-022-and-tech-030--p7-ux-re-baseline-checkpoint-c1-closure-golden-frontend-reference-adoption-authorization-baseline-and-reconciliation)). Where a PT row's presentation values differ from the reference's — widths, heights, positions —, the reference's are used and Stage 3 aligns the row; no behaviour changes with them. The identifier columns come from [DESIGN_SYSTEM §9](../DESIGN_SYSTEM.md#9-patterns) (31) and ADMIN_FLOW §§5–9 (21), read by script.

### 4.1 PT-01–PT-31

| PT | Pattern | Presentation from the reference | shadcn/ui | What stays canonical |
| --- | --- | --- | --- | --- |
| PT-01 | Application shell | R1's entries in the reference's shell: the 264 px sidebar, at least 220 px at runtime, collapsing to the rail; the top bar with "Cari atau pindai kode", "Buat" and the account menu (GA1 e)) | Sidebar, Collapsible, Dropdown Menu, Avatar, Tooltip | groups that open in place; the notice strip only while a notice is due. The *Pintasan* help (KB-02) gets no shell control: Ctrl / and an entry "Pintasan keyboard" in the search palette open it (proposed, Level 1; [IA_PAGE_INVENTORY](IA_PAGE_INVENTORY.md)'s pending choice) |
| PT-02 | Phone navigation | the 56 px app bar with the back action and the title; R1's four bottom items, 60 px plus the safe area; *Lainnya* as a drawer or a sheet | custom bars; Drawer or Sheet | the back action on every child flow (AGENTS.md rule 12); R1's four destinations rather than five (GD-03); GR-11; GR-22 |
| PT-03 | Page header and page actions | title, ancestors-only breadcrumb, meta line, one primary, "Tindakan Lain" | Breadcrumb, Button, Dropdown Menu | at most three levels; the facts line; the business-date control (PT-25); one primary (C1A-2) |
| PT-04 | Company label, working filter and relation marker | the company tag; "Tampilkan" | Badge, outline; Select or a Popover with a checklist | the code first; the view a page control, never in storage ([D-IA-02](../INFORMATION_ARCHITECTURE.md#4-design-decisions); GA1 g)) |
| PT-05 | Data table | the reference's frame, sticky header, hairline rows, a position number | Table; the Data Table guide | the number as a link in the first column; tabular figures; row actions in "⋯"; position numbers with "Muat Lagi" (GR-08) |
| PT-06 | Dense list rows | the reference's row list, rows of at least 60 px | Item | two lines, the key figure right-aligned, the whole row the target |
| PT-07 | Filters | the toolbar: search, the company view, one or two promoted filters and "Filter (n)" | Input, Select or Toggle Group, Popover or Sheet, Badge | at most four filter controls, "Atur Ulang", applied filters as removable tags, the phone sheet, PF-42's bounded filters — the toolbar is presentation only (GR-20; SD-02) |
| PT-08 | List continuation and list notices | a "Muat Lagi" button and the reference's notice anatomy | Button; Alert | "Muat Lagi", "Semua sudah ditampilkan.", MSG-23's cut notice, the bounded filter's short page, the failed cursor (PF-14; PF-42) |
| PT-09 | Form layout and validation | labels above with an 8 px gap; inline errors; "Pemeriksaan" with "Perbaiki"; the reference's form width, 1,120 px | Field, Label, Input, Alert, Item | "(wajib)"; helper text only to prevent an error (OC-06); IP-14 |
| PT-10 | Multi-line entry | the purchase line's geometry and alignment rule; one add path (C1A-2) | Table with Field, Input, Combobox | one empty row at the end (IP-13); "Perkiraan" subtotals (UXI-14); the frame scrolling at tablet widths |
| PT-11 | Buttons and actions | the reference's variants and sizes | Button | destructive only as the confirming button of a destructive dialog; one primary per region |
| PT-12 | Detail layout | header facts, parts as tabs or a segmented switcher, the timeline | Tabs, Toggle Group, Separator, Card | the facts as a definition list; the record's parts; PT-18 |
| PT-13 | Status badge | the pill with its dot; the row density | Badge | [§12.1](../DESIGN_SYSTEM.md#121-status-system) through §5 |
| PT-14 | Inline message | the reference's notice anatomy | Alert | one anatomy with at most one action; an outcome in the sticky action region (D-DS-06) |
| PT-15 | Toast | the white card with its type edge, at most three | Toast | only a quiet confirmation also visible on the page (GR-15); the reference's position and 4.5 s replace PT-15's in Stage 3 |
| PT-16 | Dialogs and sheets | the 400 px dialog; sheets with a handle; bottom sheets on phones | Alert Dialog, Dialog, Sheet, Drawer | Alert Dialog for confirmations, step-up and re-authentication; Dialog for at most five fields; Sheet for longer forms, filters and previews |
| PT-17 | Inventory presentation | the facts and metric strip | Table, Separator | DESIGN_SYSTEM §14.2 |
| PT-18 | Activity timeline | the reference's timeline, numbered | custom | the business date beside the recorded time; the acting account where visible; "Sistem" |
| PT-19 | Loading, skeleton, empty, error and denied | a spinner after 300 ms, no skeleton; empty; "Muat Ulang Bagian Ini"; permission | Spinner, Empty, Alert | the 300 ms delay; one sentence and one action when empty; the reference code; IP-21. PT-19's skeleton is replaced by the reference's spinner in Stage 3 |
| PT-20 | Scan field | the input group in the scan dock | Input Group | "Pindai atau ketik kode", "Siap Memindai", "Cari Produk", the last result line; IP-15 |
| PT-21 | Signal group and queue row | the reference's signal rows, worst first | Item | at most five rows per signal in the statement's order, bounded per-company counts, "Lihat Semua" |
| PT-22 | Thumbnails and previews | 40 px thumbnails with a neutral placeholder | an image with a placeholder | PF-31, PF-32; DESIGN_SYSTEM §14.3 |
| PT-23 | Money, quantity and date presentation | tabular figures | — | DESIGN_SYSTEM §16; [LOCALIZATION_ARCHITECTURE §2.4](LOCALIZATION_ARCHITECTURE.md#24-formats) |
| PT-24 | Financial position block | the facts and metric strip | Table, Separator | DESIGN_SYSTEM §14.1; server values (UXI-14) |
| PT-25 | Business date control | the reference's business date line | Popover, Calendar | IP-07's semantics and copy |
| PT-26 | Confirmation of an irreversible or correcting command | the reference's commit and destructive dialogs | Alert Dialog with Field | the title, the consequence, the items, the reason (MSG-39, MSG-40) and the numbering statements with their required tick; the reference's typed confirmation and optional tick excluded (GR-09) |
| PT-27 | Search and type-ahead | the palette and the lookup | Command, Combobox | the exact code first with "Kode Cocok", at most 20 rows, the cut notice, "Buat {Objek}" (IP-17) |
| PT-28 | Step-up and re-authentication dialog | the reference's dialog | Alert Dialog | PT-28's fields, autocomplete, paste and password managers; the re-login of [AUTH_JOURNEYS §2.5](AUTH_JOURNEYS.md#25-the-re-login-after-an-expiry--d-ux-03s-redesign-proposed) |
| PT-29 | Derived readiness list | the checklist | Item, Badge | derived each time; no fraction, progress bar or percentage (SLOP-44) |
| PT-30 | Capability-by-company preview | a matrix | Table | *Dapat*, *Baru*, *Dicabut*; prerequisites per capability ([RG-06](../../05-security/PERMISSIONS_MATRIX.md#3-role-and-grant-model)) |
| PT-31 | Derived part strip | the stage marker language on one row | custom | at most eight parts, derived each time (D-IA-09) |

### 4.2 IP-01–IP-21

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
| IP-10 | Step-up confirmation | the dialog (PT-28) | Alert Dialog | the commands of [AU-15](../../05-security/SECURITY.md#2-authentication-sessions-and-credentials); the password when not confirmed in the last 15 minutes; enforced by the server |
| IP-11 | Session limits, expiry and re-authentication | the idle warning and the re-login dialog | Alert Dialog | AU-06–AU-08; unsent input kept in memory; [AUTH_JOURNEYS §§2.5–2.6](AUTH_JOURNEYS.md#26-session-expiry-and-the-idle-warning) |
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

## 5. States and tones

[DESIGN_SYSTEM §12.1](../DESIGN_SYSTEM.md#121-status-system) gives every state a screen shows — 74 states in 23 families, counted by script — a label, one of five tones and a Lucide shape; the meanings are [BUSINESS_RULES](../../02-domain/BUSINESS_RULES.md#business-rules) §3's and [WORKFLOWS](../../03-workflows/WORKFLOWS/README.md#critical-business-workflows)' (D-DS-04). The mapping keeps every state, label, tone and shape and puts them in the reference's pill — D-DS-04's ADAPT disposition ([NON_LOSS_MATRIX](NON_LOSS_MATRIX.md) section O).

**The pill.** The reference's pill: full radius, the tone's tint with its same-hue ink, 24 px with 12 px text at weight 500, 28 px with 13 px at 600 for the record in view, 22 px with 11 px in compact lists; the 10.5 px size excluded ([GR-16](../evidence/P7_REBASELINE_GATE.md#carried-items-gr-01)). In the dot's place the state's 14 px Lucide shape in the ink — the shape keeps two states of one family apart in greyscale, where a dot of the same tone cannot (D-DS-04; WCAG 2.2 1.4.1) —, recorded as a deviation in [DEVIATION_REGISTER](DEVIATION_REGISTER.md). The row density keeps the reference's 13 px label with the shape before it. The label alone is sufficient; the tone only reinforces; a state never appears as a dot, pill or row tint alone. The reference's live pill — a dot whose ring breathes every 2 s — appears only for a genuinely live state and without its looping ring ([VP-8](../../00-governance/DECISION_LOG.md#dir-057-dir-058-obs-021-and-tech-029--p7-ux-re-baseline-checkpoint-c1-visual-direction-decisions-visual-checkpoint-authorization-baseline-and-visual-anchors)), also a recorded deviation.

| Tone (§12.1) | States | The reference's tone | Tint · ink · shape colour | Ink on tint |
| --- | --- | --- | --- | --- |
| neutral | 25 | `neutral` | `--color-neutral-bg` · `--color-neutral-fg` · the ink | 9.99:1 |
| info | 16 | `info` | `--color-info-bg` · `--color-info-fg` · the ink | 11.86:1 |
| success | 16 | `success` | `--color-success-bg` · `--color-success-fg` · the ink | 5.62:1 |
| warning | 13 | `warning` — the reference's `attention` uses the same tokens | `--color-warning-bg` · `--color-warning-fg` · the ink | 6.17:1 |
| danger | 4 | `danger` | `--color-danger-bg` · `--color-danger-fg` · the ink | 4.99:1 |
| — | — | `pending` | `--color-pending-bg` · `--color-pending-fg` | unused: §12.1 gives no state this tone |

**Shapes.** §12.1 names 40 Lucide icons. At Lucide 1.56.0, `file-warning` (*PDF Gagal Dibuat*) and `file-question` (*Tanpa Bukti*) are published as `file-exclamation-point` and `file-question-mark`; the others are published under their names, checked against the release's `icons/` folder on 2026-10-14. §12.1 itself asks P11 to confirm the names against the icon set's version and keep the shape where a name changes; Stage 3 records the two new names. The marks "Tanggal Mundur" and "Kode Cocok" are not states: `calendar-clock` and `scan-barcode` before a `label`-style word, with no tone or pill.

## 6. Responsive rules

The reference's classes, with the repository's corrections (GA1 a), e); [DESIGN_REFERENCES §7](../DESIGN_REFERENCES.md#7-draft-responsive-rules)).

| Width | Composition | Tailwind key (proposed) |
| --- | --- | --- |
| Phone, to 767 px | the 56 px app bar with the back action and the title; R1's bottom bar; one column, sections disclosed by priority; tables as rows; selects, dialogs and menus as bottom sheets, the palette as a top sheet; one sticky action region above the bottom bar; the gutter 18 px, 20 px from 375 px; the title 26/32 from 400 px | the default, mobile-first |
| Tablet, 768–1,024 px | the 80 px icon rail with the drawer; tables tighten their cells, hide their low-priority columns, then become rows when they still do not fit; multi-line editors as item cards | `--breakpoint-md: 48rem` |
| Desktop, from 1,025 px | R1's sidebar, 264 px at the canvas and at least 220 px at runtime, collapsing to the rail with Ctrl B; the top bar; one density at browser zoom 100 % | `--breakpoint-lg: 64.0625rem` |
| Desktop, from 1,200 px | the reference's full composition | `--breakpoint-xl: 75rem` |

The default breakpoints are cleared with `--breakpoint-*: initial` before these are set (Tailwind v4.3, *Theme variables*); [DESIGN_SYSTEM](../DESIGN_SYSTEM.md#design-system)'s 768 and 1,024 px bands align in Stage 3. Each page type takes the reference's archetype width (proposed, Level 1): data, 1,680 px, for the lists, work queues and reports; standard, 1,440 px, for *Beranda* and the record workspaces; settings, 1,280 px, for the settings pages and *Akun Saya*; form, 1,120 px, for the forms, editors and task steps; focused, 960 px, for PG-005.

**Reflow at 320 px** ([GR-05](../evidence/P7_REBASELINE_GATE.md#carried-items-gr-01)). The phone composition reflows at 320 CSS px without horizontal page scrolling — WCAG 2.2 1.4.10 Reflow, "320 CSS pixels" for vertical scrolling content —, the reference validating from 360 px; a data table scrolls only inside its own frame, the criterion's exception. Titles and meta wrap; single-line values truncate with the full text available (1.4.13); record numbers never truncate; codes such as racks and SKUs never break at their hyphens.

**The phone bars** (GR-11; GR-22). Both opaque, without the reference's blur. The bottom bar holds R1's items — the Owner's *Beranda* · *Proyek* · *Cari* · *Lainnya*, the Admin's *Pekerjaan* · *Proyek* · *Cari* · *Lainnya*, each present only when the account may open it —, the current item with its olive mark (GR-02). On a focused scan step whose action bar shows, the bottom bar hides; no path is lost, the back action leads out (GA1 e)). *Lainnya* — *Kerja*, *Menu lengkap* and *Akun* — opens the reference's drawer, a navigation surface by nature; a full-height sheet remains the alternative the C2 sessions may prefer (proposed; GR-11).

**Input, not only width** (GR-07). A coarse pointer gets targets of at least 44 px at every width, warehouse and field tasks 48 px; text fields are 16 px on touch devices (D-DS-02).

**Density** ([OC-09](../../00-governance/DECISION_LOG.md#dir-055-dir-056-obs-020-and-tech-028--p7-ux-re-baseline-checkpoint-c1-decision-rework-authorization-baseline-and-rework); D-DS-03). The dense mode only on the named screens of §2; every other screen keeps the reference's comfortable density, judged against AICWDF §18.2 at the preview.

## 7. The carried items GR-01–GR-23

The [carried items](../evidence/P7_REBASELINE_GATE.md#carried-items-gr-01) as [DIR-061](../../00-governance/DECISION_LOG.md#dir-061-dir-062-obs-023-and-tech-031--p7-ux-re-baseline-stage-2-decisions-s2-0-authorization-baseline-and-prototype-setup) SD-02 corrects them — GR-09's clause "every logout confirmed" withdrawn, [MSG-35](../ADMIN_FLOW/s14-15-messages-scenarios.md#14-message-catalogue) governing; GR-20 presentation only; GR-16 judged at the Owner's preview in two variants. [DEVIATION_REGISTER](DEVIATION_REGISTER.md) tabulates their current state; GR-24 onward are its own.

| GR | Carried item | What S2-1 settles | What S2-2 or the preview checks | What stays for Stage 3 or P11 |
| --- | --- | --- | --- | --- |
| GR-01 | the control edge `#898982` | `--color-border-input`, shadcn/ui's `--input` (§§1.1, 1.2) | SV-05: the edges of fields, selects, date fields, checkboxes and radios at 3:1 on each surface | the normative token (Stage 3) |
| GR-02 | an olive mark on the bottom bar's current item, the selected segment and the sign-in toggle | §§3, 6 | SV-05: the marks at 3:1 | Stage 3 |
| GR-03 | the olive ring on the current stage marker | §§1.4, 3 | SV-05 on S2-J03's journey | Stage 3 |
| GR-04 | the per-surface greys | §1.2 | SV-05: each grey measured on its surface | normative in Stage 3; a lint rule in P11 |
| GR-05 | reflow at 320 px | §6 | SV-02 at 320 px: no horizontal scroll but a table's | verified in Stage 4 |
| GR-06 | rem at a 16 px root; the 1.4.12 and 2.4.11 checks | §1; scroll padding under the sticky regions (§3) | SV-05: the text-spacing override and focus not obscured, as the probe's items h) and e) show possible ([PROTOTYPE_PLAN](PROTOTYPE_PLAN.md)) | the rem keys (P11) |
| GR-07 | 48 px warehouse and field controls; ≥ 44 px with a coarse pointer | §§2, 6 | SV-05: target sizes on S2-J05 at 390 and 320 px | Stage 3 |
| GR-08 | position numbers with "Muat Lagi"; no pager or total | §3; [PT-05](../DESIGN_SYSTEM.md#92-lists-tables-and-filters), PT-08 | the lists of the journeys | PT-05, PT-06 and PT-08 aligned (Stage 3) |
| GR-09 | the confirmation mapping without a typed confirmation or tick; logout asking only when input is unsent (MSG-35) | §4: PT-26, IP-08 | S2-J01's logout with and without unsent input | ADMIN_FLOW aligned (Stage 3) |
| GR-10 | the composer within VD-07's share, DC-2's number line, defined inputs, provisional totals, [GAP-053](../../00-governance/GAP_REGISTER.md#triage)'s producer | [DOCUMENT_PREVIEW_PRODUCER](DOCUMENT_PREVIEW_PRODUCER.md)'s option A; §3 | S2-J03's composer, simulated | the normative producer (Stage 3); the template (P11); P8's tests |
| GR-11 | *Lainnya* as a drawer or a sheet; the bottom bar hidden on a focused scan step | §6 proposes the drawer | S2-J05's scan step; the C2 sessions confirm | Stage 3 |
| GR-12 | the abstract sign-in panel, the code entry, the authentication journeys | [AUTH_JOURNEYS](AUTH_JOURNEYS.md); §3's code entry | S2-J01's screens | Stage 3; P11 |
| GR-13 | the Google button to Google's guidelines, its font's self-hosting | the prototype's drawing is an Owner question in PROTOTYPE_PLAN | the preview shows the simulated button | the Architect, in P11 with GAP-042 (P9) |
| GR-14 | whether a UI preference persists; the company view and the period out of browser storage | the language in [LOCALIZATION_ARCHITECTURE §3](LOCALIZATION_ARCHITECTURE.md#3-persistence--stated-not-decided); the sidebar's state kept nowhere in the prototype (SS-07), its persistence in the application stated, not decided, under C-1 | the prototype stores nothing (SP-13's guard; the probe's item k)) | Stage 3 under C-1 |
| GR-15 | toasts only for events that leave a visible trace | §§3, 4 (PT-15) | no journey message only in a toast | PT-15 aligned (Stage 3) |
| GR-16 | the smallest desktop text | §1's variants A and B | SV-03 renders both; the Owner chooses (SD-08) | the choice recorded (Stage 3) |
| GR-17 | the reference's micro-copy | [LOCALIZATION_ARCHITECTURE §2.6](LOCALIZATION_ARCHITECTURE.md#26-the-references-micro-copy-gr-17-gp-5) | its string check; the vendored strings in DEVIATION_REGISTER | the localization contract (Stage 3) |
| GR-18 | Lucide styled to the reference's strokes | §1.4's strokes; Lucide 1.56.0 pinned (SP-16) | the prototype's sprite from the release's SVG files | the icon set under [DEP-07](../../01-product/V1_SCOPE.md#dependencies-and-evidence-needed) (P11) |
| GR-19 | date-range presets bound to the periods | §3 | a period control where a journey screen shows one (Part 6) | Stage 3 |
| GR-20 | the filter toolbar, presentation only | §4: PT-07 | the journeys' lists | PT-07's presentation aligned (Stage 3) |
| GR-21 | desktop disclosures where R1 folds | §3 | S2-J03's *Pekerjaan* and the journey's folded stages | Stage 3 |
| GR-22 | opaque phone bars | §§1.4, 2, 6 | SV-01 and SV-02 on the phone renders | Stage 3 |
| GR-23 | the ageing chart with labels, amounts and a table | §§1.2, 3 | S2-J02's *Beranda* | Stage 3 |

## 8. The fidelity audit

What S2-2 runs for [SV-01](../evidence/P7_REBASELINE_GATE.md#stage-2--owner-decisions-dir-061) (BP-10), against the reference's own rendering; the tooling is [PROTOTYPE_PLAN](PROTOTYPE_PLAN.md)'s, with the probe's results.

1. **The reference rendered.** From a temporary clone of tag v1.0.0, verified as [DIR-064](../../00-governance/DECISION_LOG.md#dir-063-dir-064-obs-024-and-tech-032--p7-ux-re-baseline-s2-0-acceptance-s2-1-authorization-baseline-and-stage-2-documents) §13.1 requires and never altered, served on 127.0.0.1 outside both repositories: its own pages and components at the same viewports as the prototype's — the sign-in for S2-J01's screens, the forms and lists for the others —, read as anatomy, their demonstration content never copied (AK-5).
2. **The prototype rendered.** Every screen of the approved screen list, at SV-02's viewports, in both languages, in GR-16's two variants where text size matters.
3. **Measured.** For each component on a screen, the computed font family, size, weight, colour, background, border, radius, shadow and height (SV-01), compared with the token values of §1 — exact for colours, borders, radii and shadows; for sizes, the token times the runtime unit the viewport gives.
4. **Judged.** A difference passes only when [DEVIATION_REGISTER](DEVIATION_REGISTER.md) lists it, with its reason; any other difference is a defect for S2-2 to fix before its gate. Side-by-side captures, written to the prototype repository (SV-06), let the Owner see what the measurements say.
5. **Recorded.** Per screen and component: the property, the expected token and value, the measured value, and PASS, FAIL or the deviation's GR number — in S2-2's verification records, with the capture files' hashes in MULTIPLECORP and the files themselves in the prototype repository (SV-06; P-4).

## 9. The anti-slop rules

SLOP-01–SLOP-45 of [DESIGN_SYSTEM §18](../DESIGN_SYSTEM.md#18-anti-ai-slop-mapping), with GA1 d)'s dispositions — KEPT 35, REVISED 9, SUPERSEDED 1 —, a rule protecting clarity, honesty or accessibility kept (D4); the right column says where this mapping honours each.

| SLOP | Prohibition | Disposition | Honoured in |
| --- | --- | --- | --- |
| SLOP-01 | every section inside a card | KEPT | §3, Card — a card only for a distinct object; §2, spacing |
| SLOP-02 | nested cards | KEPT | §3, Card — no card in a card |
| SLOP-03 | giant rounded containers | REVISED — the reference's radius family, still no giant container | §§1.4, 2 |
| SLOP-04 | excessive border radius | KEPT — nothing above 12 px but the sheets' top corners | §2 |
| SLOP-05 | pill-shaped everything | KEPT — pills for states, counts and the bottom bar's current marker only | §§3, 5 |
| SLOP-06 | excessive gradients | KEPT — none in the application; the sign-in panel under [VD-05](../../00-governance/DECISION_LOG.md#dir-057-dir-058-obs-021-and-tech-029--p7-ux-re-baseline-checkpoint-c1-visual-direction-decisions-visual-checkpoint-authorization-baseline-and-visual-anchors) | §1.2 — the hero tints excluded; §3, sign-in |
| SLOP-07 | decorative gradients | KEPT | as SLOP-06 |
| SLOP-08 | glowing UI | REVISED — the sign-in button's soft shadow only | §1.2, the CTA row |
| SLOP-09 | neon | REVISED — lime only as a fill under dark ink, never a state's only mark | §1.2, the lime rows; [GR-02](../evidence/P7_REBASELINE_GATE.md#carried-items-gr-01), GR-03 |
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
| SLOP-20 | "Welcome back!" clichés | KEPT — the sign-in's heading and one line | §3, sign-in; [LOCALIZATION_ARCHITECTURE §5](LOCALIZATION_ARCHITECTURE.md#5-copy-rules-for-the-journey-specifications) |
| SLOP-21 | startup marketing copy | KEPT | §3, sign-in — no editorial copy; LOCALIZATION_ARCHITECTURE §5 |
| SLOP-22 | emoji as decoration | KEPT | DESIGN_SYSTEM §16; LOCALIZATION_ARCHITECTURE §5 |
| SLOP-23 | unnecessary icons | KEPT — text-first headings | §3, Card |
| SLOP-24 | an icon in every label or button | KEPT | §3, Button and Card |
| SLOP-25 | excessive whitespace that slows work | SUPERSEDED — the reference's density, touch sizes on phones, the dense mode on OC-09's screens, under AICWDF §18.2 | §§2, 6 |
| SLOP-26 | gigantic headings | REVISED — the reference's scale; a display size only on the sign-in | §§1.3, 2 |
| SLOP-27 | hero-like headers | REVISED — titles at the reference's size, no hero | §2; §3, page header |
| SLOP-28 | noisy dashboards | KEPT — at most five groups ([QB-10](../../06-api-performance/PERFORMANCE.md#6-query-budgets)) | §4, PT-21 |
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
| SLOP-39 | feeds | KEPT — "Muat Lagi", no infinite scroll | §4, PT-08 and [IP-18](../ADMIN_FLOW/s06-09-session-forms-lists.md#ip-18--continuing-a-list) |
| SLOP-40 | reactions | KEPT | §3 holds no reaction control |
| SLOP-41 | likes | KEPT | as SLOP-40 |
| SLOP-42 | streaks | KEPT | §3 holds no streak or per-user counter |
| SLOP-43 | confetti | KEPT — no celebratory motion | §2, motion |
| SLOP-44 | gamification | KEPT — no percentage; progress for processes only | §3, progress bar and checklist |
| SLOP-45 | chat-bubble decoration | KEPT — timeline entries are rows | §3, timeline |

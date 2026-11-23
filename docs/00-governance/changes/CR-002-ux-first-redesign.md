# CR-002 — The UX-first redesign: the contract amendments behind K-2–K-7

Status: PROPOSED — OWNER DECISION REQUIRED, one approval per package on its exact text | Date: drafted 2026-11-22, recorded 2026-11-23 | Raised by: the executor, under CHANGE_CONTROL ([Lightweight records](../CHANGE_CONTROL.md#lightweight-records)) | Owner decisions: [DIR-131](../DECISION_LOG.md#dir-131--the-owners-review-of-the-ux-first-v2-blueprint-2026-11-22), in principle; [DIR-134 and APPR-042](../DECISION_LOG.md#dir-134-and-appr-042--the-ux-first-redesigns-decisions-e1e9-and-its-isolated-pilot-2026-11-23), the decisions E1–E9 and the isolated pilot

Nothing in this record is in force. Each package changes its documents only through the Owner's approval of its exact text; until then the approved contracts govern every merged change.

## Problem

The approved presentation contracts make the Golden Frontend Reference the primary visual reference, fold the modules into a collapsed *Menu lengkap* with module tabs, forbid skeletons, and forbid stage progress and multi-action empty states. The Owner's reviews decide in principle to change each (DIR-131) and require acceptable, consistent, beginner-friendly UX before the production GO-LIVE authorization, the release governance and its freezes kept (DIR-134 E9).

## Packages, documents and levels

| Package | Document | Clauses | Level |
|---|---|---|---|
| K-2 Golden as inspiration | DECISION_LOG | DIR-059 GD-01, GD-02; DIR-061 SD-02 (presentation); DIR-067 K1; DIR-069 decision 3 / OQ-10; DIR-071's Golden clause; DIR-077 CA-04 items 1, 4–7, 11, 12, 15–16 | 3 |
| | DESIGN_REFERENCES | The reference hierarchy (GD-02), "Stage 2 — presentation only", the Golden PRIMARY row, Precedence, §2, §5, §6, §7, §9 | 3 (+1) |
| | DESIGN_SYSTEM | Authority, Sources, §2's Golden row, §5 "Three layers, one source", D-DS-16 (motion), §9.6–§9.7's presentation columns, §10's anatomy, §12.1's tones, §18's SLOP fulfilment column, Annex A, Annex B (kept as MULTIPLECORP's own rules) | 3 (+1) |
| | IA, TEST_STRATEGY §9, TOOLCHAIN, AGENTS.md rule 10, EXECUTION_CONTEXT | The Golden references; the UI gate measured against §5 and §§9–10 alone | 1 (consequence) |
| K-3 visual direction, after the final verdict (DIR-134 E8) | DESIGN_SYSTEM | §5.1's palette, §5.2's type scale, the shell row, the stepper, §12.1's pill; GR-01 and GR-04's contrast intent kept | 3 |
| K-4 navigation, with K-1's ordering | DECISION_LOG | DIR-059 GD-03; R1 a) as DIR-057 kept it (OC-02 and D-IA-03 unchanged) | 3 |
| | INFORMATION_ARCHITECTURE | §4 D-IA-01, §5.2, §5.3 (the 37 sub-pages as leaves), §6.1, §6.2, §8's and §13's paths, GL-194 | 2 + 3 |
| | DESIGN_SYSTEM | PT-01 (business-flow order → frequent tasks, responsibility and flow), PT-02, §6's phone bars, §9.6, §10's Shell and Tabs rows, GR-11 | 2 + 3 |
| | NAVIGATION_CONTRACTS | NV-06; the Parent, Entry and Back columns (NAV-020, NAV-021, NAV-029, NAV-041, NAV-048–NAV-050 among them); the 65 contracts and NV-01–NV-08 kept | 1 (consequence) |
| | LOCALIZATION | `glossary.gl_194`, "Lainnya" → "Menu", the three group labels, proposed copy validated with novice users (DIR-134 E7) | 1 |
| K-5 skeleton | DESIGN_SYSTEM; IA; DESIGN_REFERENCES | UXV-17, PT-19, §10 "States of a region", the not-used list, §12.3; IA §9 "Groups and budget"; DESIGN_REFERENCES' "no Skeleton" | 1 in nature, the Owner's approval as the release freeze requires |
| K-6 stage indicator and first steps, validated in the pilot only (DIR-134 E4) | DESIGN_SYSTEM | SLOP-44 (both tables), PT-31, the stage track, the segmented stage indicator, `--brand-progress`, PT-29 and its Checklist row, PT-19's empty state, §10 "Primitives not used" (Progress) | 2 |
| | INFORMATION_ARCHITECTURE; PERFORMANCE | IA §9 (one sentence per empty group; five groups); QB-10 if Beranda's budget changes | 2 |
| | DECISION_LOG | DIR-071 (the segmented stage indicator), PL-1/VP-4 (no percentage or weighted bar), VP-2/GD-04 (no hub tiles or greeting) — their interpretation restated | 2 |
| K-7 clarification | DESIGN_SYSTEM | §2's shadcn/ui row: Base UI only, Radix no longer "supported", as TASK-015 chose | 1 |

## Evidence first — the isolated pilot

[APPR-042](../DECISION_LOG.md#dir-134-and-appr-042--the-ux-first-redesigns-decisions-e1e9-and-its-isolated-pilot-2026-11-23)'s pilot, on a non-merge branch, demonstrates the packages through functional workflows in four areas — Masuk with two-step verification, Beranda, Proyek, Gudang › Stok — with the shared navigation shell, tokens, typography, spacing, responsive behaviour and feedback components. Its results feed each package's text: K-3's final palette (E8); K-4's sidebar hierarchy and group labels, validated with novice users and on real devices (E6, E7); K-6's stage indicator and the pilot's behaviour variants — the recovery-code acknowledgement and the empty-group summary (E4, E5).

## Gates before any package's approval

- Nothing lost: the 37 sub-pages, the 65 NAV contracts and NV-01–NV-08 reachable as before, each authorized feature discoverable.
- P0–P6's semantics, every role, permission and company boundary, and the financial and inventory rules unchanged.
- WCAG 2.2 AA; every SLOP rule kept except SLOP-44 as amended; QB-10 for Beranda.
- New copy marked proposed until the Owner validates it.
- For K-4, the usability validation's results recorded, as APPR-011's condition 2 set the precedent.

## Options per package

Each package: approve its exact text, approve it with changes, or keep the current contract. K-6 also: (a) the stage indicator only, (b) the first steps only, (c) both, (d) neither — the variants that conform to the current contract (the stage's name with "sekarang"; one sentence and one action) stay available.

## Implementation

After a package's approval, an Owner-approved TASK_PLAN Change — its Task IDs given by that Change, packaged by coherent navigation and workflow groups, never one Task per visual adjustment — implements the approved redesign, reusing the pilot's components and tests. It lands before the production GO-LIVE authorization (DIR-134 E9), within the release governance: the release freeze is excepted for the approved redesign only by the Owner's decision recorded with that Change, and the UI, accessibility and localization gates and the release regression run again. The shared-foundation corrections that conform to the current contracts (DIR-134 E2) are not part of this record; they follow their own minimum compliant Change.

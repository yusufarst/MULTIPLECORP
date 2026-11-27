# CR-002 — The UX-first redesign: the contract amendments behind K-2–K-7

Status: APPROVED by package on 2026-11-28 — K-7, K-2, K-5, K-6 (variant c, with E5), D-5 and D-6 on their exact texts, K-4's structure and K-3's working direction ahead of their validations (E6, E7 and E8 open as gates) | Date: drafted 2026-11-22, recorded 2026-11-23, approved 2026-11-28 | Raised by: the executor, under CHANGE_CONTROL ([Lightweight records](../CHANGE_CONTROL.md#lightweight-records)) | Owner decisions: [DIR-140–DIR-148](../DECISION_LOG.md#approval-register), the approvals of 2026-11-28; [DIR-131](../DECISION_LOG.md#dir-131--the-owners-review-of-the-ux-first-v2-blueprint-2026-11-22), in principle; [DIR-134 and APPR-042](../DECISION_LOG.md#dir-134-and-appr-042--the-ux-first-redesigns-decisions-e1e9-and-its-isolated-pilot-2026-11-23), the decisions E1–E9 and the isolated pilot

Each package changed its documents only through the Owner's approval of its exact text, given on 2026-11-28 and recorded below; what the approvals leave open — K-4's term *Menu* and group labels, K-3's final values — stays *proposed* and governs nothing as final. The approved contracts govern every merged change; the pilot and the notes behind the packages are not in force.

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

## Gates before any package's approval — and the order the Owner changed

- Nothing lost: the 37 sub-pages, the 65 NAV contracts and NV-01–NV-08 reachable as before, each authorized feature discoverable.
- P0–P6's semantics, every role, permission and company boundary, and the financial and inventory rules unchanged.
- WCAG 2.2 AA; every SLOP rule kept except SLOP-44 as amended; QB-10 for Beranda.
- New copy marked proposed until the Owner validates it.
- For K-4, the usability validation's results recorded, as APPR-011's condition 2 set the precedent — **changed on the Owner's explicit permission on 2026-11-28:** the approval of K-4's structure and of K-3's working direction precedes the validation (E6, E7) and the visual verdict (E8). Those three stay mandatory gates, none is taken as passed, and the Executor builds the foundation first and the navigation after it, so that the results exist before the navigation is final ([APPR-050](../DECISION_LOG.md#dir-146-and-appr-050--cr-002s-package-k-4-the-three-level-menu-2026-11-28); [APPR-051](../DECISION_LOG.md#dir-147-and-appr-051--cr-002s-package-k-3-grafit--kobalt-as-the-working-direction-2026-11-28)).

## Options per package

Each package: approve its exact text, approve it with changes, or keep the current contract. K-6 also: (a) the stage indicator only, (b) the first steps only, (c) both, (d) neither — the variants that conform to the current contract (the stage's name with "sekarang"; one sentence and one action) stay available.

## Implementation

After a package's approval, an Owner-approved TASK_PLAN Change — its Task IDs given by that Change, packaged by coherent navigation and workflow groups, never one Task per visual adjustment — implements the approved redesign, reusing the pilot's components and tests. It lands before the production GO-LIVE authorization (DIR-134 E9), within the release governance: the release freeze is excepted for the approved redesign only by the Owner's decision recorded with that Change, and the UI, accessibility and localization gates and the release regression run again. The shared-foundation corrections that conform to the current contracts (DIR-134 E2) are not part of this record; they follow their own minimum compliant Change.

## The Owner's approval of 2026-11-28

One approval, on the seven documents of this record, taken per package; one DIR and one APPR per package, the entries of one Change Control record and one governance pull request ([source](../sources/EXECUTION_OWNER_APPROVAL_CR-002-CONSOLIDATED_2026-11-28.txt)).

| Package | Approved as | Entry |
|---|---|---|
| K-7 | on its exact text, with K7-C1 to K7-C3 | [DIR-140; APPR-044](../DECISION_LOG.md#dir-140-and-appr-044--cr-002s-package-k-7-the-primitives-are-base-ui-only-2026-11-28) |
| K-2 | on its exact text, with K2-DS-X, K2-CQ-6 and the supersession rows S-9 and S-10 | [DIR-141; APPR-045](../DECISION_LOG.md#dir-141-and-appr-045--cr-002s-package-k-2-the-golden-frontend-reference-as-inspiration-2026-11-28) |
| K-5 | on its exact text | [DIR-142; APPR-046](../DECISION_LOG.md#dir-142-and-appr-046--cr-002s-package-k-5-skeletons-for-a-region-that-is-loading-2026-11-28) |
| K-6 | variant (c), with E5, on its exact text | [DIR-143; APPR-047](../DECISION_LOG.md#dir-143-and-appr-047--cr-002s-package-k-6-the-stage-indicator-the-first-step-and-the-recovery-codes-stage-2026-11-28) |
| D-5 | on its exact text (a package of its own, Level 2) | [DIR-144; APPR-048](../DECISION_LOG.md#dir-144-and-appr-048--cr-002s-addition-d-5-a-wide-table-becomes-labelled-rows-below-its-threshold-2026-11-28) |
| D-6 | on its exact text (Level 2) | [DIR-145; APPR-049](../DECISION_LOG.md#dir-145-and-appr-049--cr-002s-addition-d-6-the-arrangements-of-pg-010-pg-011-pg-018-and-pg-019-2026-11-28) |
| K-4 | its structure — its contract text applied with TASK-099's pull request, with the code and the key catalogue it must agree with —; the term *Menu*, the group labels and the hierarchy's acceptance *proposed* until the novice validation, the real devices and E6/E7 | [DIR-146; APPR-050](../DECISION_LOG.md#dir-146-and-appr-050--cr-002s-package-k-4-the-three-level-menu-2026-11-28) |
| K-3 | the working direction Grafit & Kobalt — its contract text applied with TASK-098's pull request, with the tokens it must agree with —; every value a working direction until the Owner's visual verdict and E8 | [DIR-147; APPR-051](../DECISION_LOG.md#dir-147-and-appr-051--cr-002s-package-k-3-grafit--kobalt-as-the-working-direction-2026-11-28) |
| Change 33 | TASK-098 to TASK-104; the release-freeze exception; D-8's minimum corrections | [DIR-148; APPR-052](../DECISION_LOG.md#dir-148-and-appr-052--change-33-the-redesigns-seven-tasks-d-8s-minimum-corrections-and-the-release-freeze-exception-2026-11-28) |

**Why K-4's and K-3's text waits for its Task:** the tests TL-08 (the tokens against DESIGN_SYSTEM §5) and the key catalogue and the copy-list generator (against LOCALIZATION) read the documents as written; a document changed without its code fails them, and no gate is weakened. The approvals are recorded now; the exact texts are applied to the documents in the pull requests of TASK-098 (K-3) and TASK-099 (K-4), each with the code and tests it must agree with.

**The clauses beyond the first table**, each in its package: K-2's §4 (K2-DS-X), CONTEXT_INDEX and SOURCE_OF_TRUTH (K2-CQ-6), and the rows S-9 and S-10; K-7's consequences K7-C1 to K7-C3; K-4's twelve clauses found outside the table (GL-030, GL-187, GL-195, §§5.4, 5.5, 13.6–13.8, 14.4, DESIGN_SYSTEM §6, §10's phone-bar row, INT-003, the route declarations); K-3's GR-02, GR-03 and §7 and DIR-057 VD-03's note; K-5's six consequence clauses; K-6's four.

**The further decisions 1–18**, as the Executor recommended them, and the Owner's six corrections: (1) K-2 reads as one reading rule in DESIGN_SYSTEM's Authority; (2) the outcomes of CA-04 stay, their premise withdrawn; (3) S-9 and S-10 included and reconciled with K-4, GD-03 and GD-05 leaving no Golden as a binding visual reference — the Owner's correction 1; (4) the clauses found outside the table follow their packages; (5) the 300 ms rule for every skeleton, a stage column included, and (6) no placeholder for a figure without its capability — correction 4; (7)–(9) no visible "Sekarang", the first step limited to what the pilot proved ("Atur Perusahaan"), PT-31 kept as a pattern without a page; (10) E5 in K-6, its codes in no `history.state`, persistent storage, log or form memory, reload and Back tested — correction 5; (11) D-6's PG-018 scope-line sentence kept as an exception; (12)–(13) K-4's structure now, the sidebar at 248 px, GL-030 retired; (14) `#6f7074` for the canvas's muted text and a done-stage marker at 3:1 with a cue that is not colour — correction 3; (15) DIR-057 VD-03's note; (16) the 12 px floor and the 20/28 title the Owner's visual decision; (17) K-2 applied first where clauses overlap, the later text rebased on it, its substance unchanged — correction 6; (18) one DIR and one APPR per package. The business rules, the security, the authorization, the accessibility and every earlier decision not expressly amended are kept — correction 2.

**Implementation:** Change 33 — [TASK_PLAN §7](../../11-tasks/TASK_PLAN.md#7-controlled-task-plan-changes) — the seven Tasks TASK-098 to TASK-104, in the order foundation, navigation, the list pattern with the four pilot areas, the other modules, the gate; the release-freeze exception for this scope only, every release gate remaining; D-8's six findings corrected minimally in TASK-098 and TASK-100, no Task added. The pilot is reused by copy and review, never merged or rebased. E6, E7 and E8 are recorded in TASK-104 as they happen.

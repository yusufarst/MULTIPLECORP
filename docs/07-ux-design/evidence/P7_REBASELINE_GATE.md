# P7 UX re-baseline — evidence and gates

Status: REVIEW | Updated: 2026-10-04 | Owner: Planning

Authority: the Owner's authorization [DIR-054](../../00-governance/DECISION_LOG.md#dir-053-dir-054-obs-019-and-tech-027--p7-ux-re-baseline-decisions-stage-01-authorization-baseline-and-direction) (fifty-first source record) for Stage 0 and Stage 1 of the P7 UX re-baseline only, ending at Checkpoint C1, under the Owner's decisions K1–K4, C-1 and C-2 of [DIR-053](../../00-governance/DECISION_LOG.md#dir-053-dir-054-obs-019-and-tech-027--p7-ux-re-baseline-decisions-stage-01-authorization-baseline-and-direction) (fiftieth source record) and the standing intent of D3, D4 and D7 and DIR-040 F6. After the Owner's verdict at Checkpoint C1, [DIR-055](../../00-governance/DECISION_LOG.md#dir-055-dir-056-obs-020-and-tech-028--p7-ux-re-baseline-checkpoint-c1-decision-rework-authorization-baseline-and-rework) (fifty-second source record), the Owner's authorization [DIR-056](../../00-governance/DECISION_LOG.md#dir-055-dir-056-obs-020-and-tech-028--p7-ux-re-baseline-checkpoint-c1-decision-rework-authorization-baseline-and-rework) (fifty-third source record) governs the focused C1 rework, which ends again at Checkpoint C1.

This is the evidence record of the re-baseline: its baseline, the DesainPakeAI readiness, the stage plan with its checkpoints, the Stage 1 diagnosis and direction for Checkpoint C1, the Owner's verdict at Checkpoint C1 and the C1 rework, and the gates. It is evidence, not a specification, and nothing in it is normative: [INFORMATION_ARCHITECTURE](../INFORMATION_ARCHITECTURE.md) and [DESIGN_SYSTEM](../DESIGN_SYSTEM.md) stay APPROVED under APPR-008 and under replacement (D3), and [ADMIN_FLOW](../ADMIN_FLOW/README.md) stays in force, until a later authorized stage replaces or aligns them. DesainPakeAI is an exploration workspace outside the repository and never a source of truth. The record stays REVIEW, following the gate convention.

## Baseline

Verified read-only on 2026-10-04 before any change and recorded as OBS-019:

| Item (DIR-054 §4) | Result |
| --- | --- |
| 1. Repository and origin | Root `C:\Projects\MultipleCorp`; origin `https://github.com/yusufarst/MULTIPLECORP.git` (fetch and push) |
| 2. Live remote | After `git fetch origin`, `git ls-remote origin` showed `HEAD` and `refs/heads/main` at `a526daf47444d12b4ae5c51e1ae14c9dfe3f4978`, `refs/heads/amendment/p5-auth` at `a526daf47444d12b4ae5c51e1ae14c9dfe3f4978`, `refs/heads/migration/aicwdf` at `c26f31e9b053689b07a29877fe1016c6b8103e37` and `refs/tags/pre-aicwdf-migration` as tag object `2f8875e8b92d64b2db6c8e8dbc5991899f5d92de`, peeled to `c511d7b0d4683e07717c962927c9f113854f227b`; no `refs/heads/rebaseline/p7-ux` and no other ref |
| 3. Local branches | `main` and `amendment/p5-auth` at `a526daf47444d12b4ae5c51e1ae14c9dfe3f4978`, `amendment/p5-auth` checked out; `origin/main` and `origin/amendment/p5-auth` equal to the live branches; ahead/behind 0 and 0 for both |
| 4. Published P5 publication-record commit | `git log -1 --format='%H %P %s' a526daf` gives `a526daf47444d12b4ae5c51e1ae14c9dfe3f4978`, parent `e6cb6ac8d89f9a50670b75cd053699cc7ebede4d`, subject `docs: record P5 authentication amendment publication` — the commit whose SHA [OBS-018](../../00-governance/DECISION_LOG.md#obs-018--p5-authentication-amendment-publication-verified) left to the next authorized task, recorded here literally |
| 5. Working tree and index | `git status` clean; `git diff --ignore-cr-at-eol --stat`, `git diff --stat` and `git diff --cached --stat` empty; no untracked file; no merge, rebase, cherry-pick, revert or bisect state |
| 6. Source records | All 49 files under `docs/00-governance/sources/` match their SHA-256 in [SOURCE_OF_TRUTH](../../00-governance/SOURCE_OF_TRUTH.md#authority-and-provenance) and every size and line count stated there (four early records state lines only, the PDF and the PNG bytes only); the committed blobs at `a526daf` give the same result |
| 7. APPR-010 hashes | The 24 SHA-256 values of [APPR-010](../../00-governance/DECISION_LOG.md#appr-010--p5-authentication-amendment-approved) equal the committed blobs at `a526daf` |
| 8. Gap register | 51 findings — 4 CLOSED, 38 OPEN, 0 OWNER_DECISION_REQUIRED, 9 ACCEPTED_RISK, counted from the triage table and equal to the stated totals |
| 9. Identifiers | DIR-053, DIR-054, OBS-019, TECH-027, APPR-011, GAP-052 and RISK-010 occur nowhere at `a526daf` (`git grep`); the highest in use were DIR-052, OBS-018, TECH-026, APPR-010, GAP-051 and RISK-009 |
| 10. Paths | The seven paths of DIR-054 §4 item 10 do not exist; no application code — the tracked files are 105 Markdown files (one of them a source record), the 46 text, one PDF and one PNG source records, `.gitattributes` and `.gitignore` |
| Informational | `git config user.name` and `user.email` are set (the e-mail address is not recorded); `core.autocrlf` is `true` |

All ten items passed. The authorization arrived as one pasted text block without an accompanying sentence; after these checks the executor asked in session whether to execute it in full, exactly as written, and the Owner selected "Ya, jalankan penuh" (DIR-054). The branch `rebaseline/p7-ux` was then created from `a526daf47444d12b4ae5c51e1ae14c9dfe3f4978` with `git switch -c`.

## DesainPakeAI readiness

The four read-only commands of DIR-054 §7, run on 2026-10-04 from a temporary directory outside the repository, before any file of the repository was edited. Non-secret evidence only: the key field, which the tool shows only masked, was not recorded, and the credential path was redacted before display; nothing was installed, upgraded, re-authenticated or reconfigured, the active project was not switched, and no paid plan was used.

| Item | Evidence |
| --- | --- |
| CLI version | 0.2.2 — `dpai --version`; the tool's banner names the product "DesainPakeAI CLI" |
| Authenticated | yes — `dpai auth status --pretty` |
| Active project | **MULTIPLECORP**, role owner — `dpai project current --pretty`; not switched |
| Context revision | `sha256-3fe26282863db253` — `dpai context --pretty` |
| Retrieval time | 2026-10-04 15:44 WIB; the context kept outside the repository |
| Ramp design system | found — the context's design summary names the design system "Ramp", version alpha.3, with 18 colour tokens, 8 typography tokens and 8 sections: the K3 reference |
| Project pages | 54 — the probe page `dpb-probe` and the 53 DPB exploration pages that [P7_QUALITY_GATE](P7_QUALITY_GATE.md#desainpakai-exploration) records (DPB-01 to DPB-18); no page in a working state |
| Setup steps needed | none — no stop |

`git status` showed nothing new in the repository after the commands.

## Plan

The stage plan of the re-baseline as DIR-054 §§9, 20 and 21 record it. K1 places two Owner checkpoints before the normative documents are frozen. Only Stage 0 and Stage 1 are authorized; the rest is the approved plan, recorded and not authorized. After the Owner's verdict at Checkpoint C1 (DIR-055), DIR-056 adds the C1 rework, which ends again at Checkpoint C1; Stages 2–5 stay unauthorized (DIR-056 §21).

| Stage | Content | Authorization |
| --- | --- | --- |
| Stage 0 — baseline and records | The baseline (OBS-019); source records 50 and 51; DIR-053, DIR-054, OBS-019 and TECH-027; the DesainPakeAI readiness; the narrow continuity cleanup of CONTEXT_INDEX, SECURITY, ENGINEERING_PRINCIPLES and TOOLCHAIN; gate G0; commit 1 `docs: open P7 UX re-baseline`; a normal push of `rebaseline/p7-ux` | Authorized — DIR-054 |
| Stage 1 — diagnosis and direction | Intake; official-documentation verification; the diagnosis of the approved P7 baseline, with the Planning hypotheses H-1 to H-5 verified (K4); the Ramp reference record DESIGN_REFERENCES as a Stage 1 draft; the persona task analysis; provisional dispositions of the 83 earlier P7 decisions and anti-slop rules; a non-normative direction proposal; prototypes of eight key surfaces in DesainPakeAI; a preliminary non-loss map; the C1 package; a zero-context check; gate G1; commit 2 `docs: record P7 UX re-baseline direction for checkpoint C1`; a normal push of the branch | Authorized — DIR-054 |
| Checkpoint C1 | The Owner's design-direction review: approve, approve with named adjustments or reject the direction; one choice per contentious axis; whether a suitable warehouse/field user is reasonably available for Stage 2 | The Owner |
| C1 rework (DIR-056) | After the Owner's verdict at Checkpoint C1 — the Stage 1 direction rejected for focused rework under OC-01–OC-12 (DIR-055). R0: the baseline (OBS-020), source records 52 and 53, DIR-055, DIR-056, OBS-020 and TECH-028, the DesainPakeAI readiness, gate R0, commit 3 `docs: record P7 checkpoint C1 rework decision` and a normal push of the branch. R1: the retrospective and persona extension, the project journey model, the connected-work map and document readiness, the revised direction, the disposition delta, `p7r2-` prototypes of RS-1–RS-10, measured checks, the revised non-loss map, the revised C1 package, a zero-context check, gate R1, commit 4 `docs: record revised P7 UX direction for checkpoint C1` and a normal push of the branch; then STOP again at Checkpoint C1 for the Owner's re-review | Authorized — DIR-056 |
| Stage 2 — core design and usability | The complete IA and page inventory; the design system — tokens, shadcn/ui mapping, patterns, states, density; the localization architecture under C-1; the authentication journeys (SECURITY H7-15–H7-19, AICWDF §4A.10, GAP-044, J-ACC-01 under AU-03); end-to-end prototypes of the critical journeys on phone and desktop in both languages; the usability kit in Indonesian with the K2 participants | Not authorized |
| Checkpoint C2 | The usability results and the Owner's verdict, with fixes and re-tests when needed. K2: the Owner, the Admin Operasional validator and one real warehouse/field mobile user if reasonably available, otherwise the first two only; short task-based sessions on the prototype with synthetic data, moderated by the Owner from the executor's script and recorded by role without personal names | The Owner |
| Stage 3 — normative documents | INFORMATION_ARCHITECTURE and DESIGN_SYSTEM replaced in place with stable IDs; NAVIGATION_CONTRACTS, LOCALIZATION and DESIGN_REFERENCES; ROUTE_CONTRACTS and INTERACTION_CONTRACTS as a P3 addition; the ADMIN_FLOW alignment; the full non-loss matrix and the governance records; any schema change only through C-1 and change control | Not authorized |
| Stage 4 — review and validation | Self-review, three independent adversarial reviews (UX, usability and accessibility; business, security and authorization fidelity; contracts, localization and zero-context) and one final focused review, a further one only under the DIR-049 convergence rule applied to UX, C-2 governing LOW non-material findings; the gates of AICWDF §18.2, §27, §4B.5, §25 and WCAG 2.2 AA at design level; a stop for the Owner's approval | Not authorized |
| Stage 5 — approval and publication | APPR-011; the approval commit, a fast-forward of `main`, normal pushes and the live-remote verification, then a separate publication-record commit; P7 DONE, P8 still needing its own authorization | Not authorized |

APPR-011, reserved and not granted, requires: the Owner's explicit verdict after C2 that the UI is user friendly (DIR-040 F6); every critical task completed without help by every participant in the final round, or an Owner-accepted disposition; 100% non-loss, with no V1 capability lost; the contracts complete; the gates PASS; 0 unresolved CRITICAL, HIGH, MEDIUM and LOW findings and 0 OWNER_DECISION_REQUIRED, "unresolved" as C-2 defines it; P0–P6 semantics unchanged except for recorded narrow amendments; and no DesainPakeAI output or credential in the repository.

## Gate G0

Run on the complete staged change of commit 1 before the commit (DIR-054 §9.2), with scripts kept outside the repository. Every item passed.

| Item | Check | Result | Evidence |
| --- | --- | --- | --- |
| G0-01 | The baseline equals DIR-054 §4 | PASS | [Baseline](#baseline): items 1–10 verified before any change (OBS-019) |
| G0-02 | The staged diff touches only the listed files | PASS | `git diff --cached --name-status a526daf` lists 14 paths — `.gitattributes`, CHANGELOG, the two new source records (added), SOURCE_OF_TRUTH, DECISION_LOG, DECISION_INDEX, CONTEXT_INDEX, SECURITY, ENGINEERING_PRINCIPLES, TOOLCHAIN, this record (added), PHASE_STATUS and CURRENT_HANDOFF; GAP_REGISTER unchanged, Stage 0 having found no new gap |
| G0-03 | Records 50 and 51 | PASS | Both follow the header convention of records 48 and 49 — the first line, Received, Subject and Delivery, the transcript sentence, a separator of 68 `=` characters and a blank line before the transcript — with LF bytes, no byte-order mark and a final line break; their lines, bytes and SHA-256 in SOURCE_OF_TRUTH equal the staged blobs; the 49 earlier records are byte-identical to `a526daf`; the folder holds 51 source records |
| G0-04 | The four amended approved documents | PASS | A word-level diff of CONTEXT_INDEX, SECURITY, ENGINEERING_PRINCIPLES and TOOLCHAIN shows only the change of DIR-054 §8, the new "Amendment:" note and, in all but SECURITY, the `Updated` date; their last-approved, before and after hashes under TECH-027 equal the APPR-009 or APPR-010 record, the blobs at `a526daf` and the staged blobs |
| G0-05 | Readiness evidence | PASS | [DesainPakeAI readiness](#desainpakeai-readiness) records the CLI version, authentication, the active project, the context revision, the retrieval time in WIB, the Ramp design system found and no setup step; no key, key identifier, token, credential path or project identifier is recorded |
| G0-06 | The current-state records agree | PASS | PHASE_STATUS, CURRENT_HANDOFF, DECISION_INDEX and DECISION_LOG record DIR-053 and DIR-054, Stage 0 done on `rebaseline/p7-ux`, Stage 1 next and `main` at `a526daf`; none claims Stage 1 work, a push or publication |
| G0-07 | Links and identifiers | PASS | 0 broken relative links and anchors in every Markdown file outside `docs/00-governance/sources/`; DIR-053, DIR-054, OBS-019 and TECH-027 each have one row in the decision log's index and one entry heading, and no other new identifier is defined |
| G0-08 | No secret, placeholder, binary, image or DesainPakeAI output | PASS | The new files are two LF text records and this Markdown record; a search of the staged diff finds no key, token, credential path, key or project identifier, e-mail address or placeholder — the angle-bracket templates inside record 51 are the Owner's verbatim text; no page source, export or capture of DesainPakeAI is in the repository |
| G0-09 | Content-truth counts unchanged | PASS | V1_SCOPE, ACCEPTANCE_CRITERIA, PERMISSIONS_MATRIX, DATABASE and GAP_REGISTER are unchanged: 18 MUST capabilities and 14 document types (V1_SCOPE), 80 capabilities (PERMISSIONS_MATRIX), 125 logical tables in 13 modules (DATABASE §4) and the gap totals 51 — 4 CLOSED, 38 OPEN, 0 OWNER_DECISION_REQUIRED, 9 ACCEPTED_RISK |
| G0-10 | No P7 normative document and no other P0–P6 change | PASS | INFORMATION_ARCHITECTURE, DESIGN_SYSTEM and ADMIN_FLOW are unchanged; among the P0–P6 specifications only the DIR-054 §8 lines and notes of CONTEXT_INDEX, SECURITY, ENGINEERING_PRINCIPLES and TOOLCHAIN change, and SOURCE_OF_TRUTH only as the continuity record of DIR-054 §6.2 and §9.1 |

## Stage 1 intake

Read on 2026-10-04, after the verified push of commit 1, as DIR-054 §10 lists; further sections only where a specific question needed them.

- **Governance and decisions:** AGENTS.md, [EXECUTION_CONTEXT](../../11-tasks/EXECUTION_CONTEXT.md), [CURRENT_HANDOFF](../../handoff/CURRENT_HANDOFF.md) and [PHASE_STATUS](../../PHASE_STATUS.md); SOURCE_OF_TRUTH — the hierarchy, the registry rows for navigation and interaction, and the document lifecycle; DECISION_INDEX; GAP_REGISTER — the triage table and GAP-015, GAP-019, GAP-020, GAP-032, GAP-034, GAP-035, GAP-037 and GAP-044; CHANGE_CONTROL, AGENT_OPERATING_MODEL, TOOLCHAIN and COST_POLICY; the decision-log entries DIR-033 and APPR-008, DIR-034–039 (D1–D4, D7), DIR-040 and DIR-051 (F6), DIR-053 and DIR-054; source records 31 (its UX re-evaluation list), 32 (D3 and D4) and 50.
- **Product and specifications:** V1_SCOPE — capabilities and roles, the language and experience contract, the P7 handoff requirements, DEP-03, DEP-06 and DEP-09; ACCEPTANCE_CRITERIA AC-14 and the completion obligations; the old P7 documents — INFORMATION_ARCHITECTURE, DESIGN_SYSTEM, every part of ADMIN_FLOW and P7_QUALITY_GATE with its exploration, briefs, traceability, obligation closure and reviews; PERMISSIONS_MATRIX §2, §6, §7 and §9; SECURITY AU-03–AU-24 and H7-01–H7-19; the WORKFLOWS README and the context tags — 38 tagged workflows, 6 `[floor]`, 24 `[desk]` and 8 `[both]`, with WF-INV-07 embedded in dispatch; PERFORMANCE QB-10, PF-15, PF-24, PF-34 and PF-43, on which the Beranda direction depends.
- **For specific questions:** BUSINESS_RULES CALC-01–CALC-14, for the figures; DATABASE §4.1, ARCHITECTURE §16 and SECURITY WS-07 and WS-08, for the C-1 observation and browser storage.
- **AICWDF v4.3** (`docs/00-governance/sources/AI_First_Complex_WebApp_Task_Framework_v4.3_EN.md`): §1, §4A.10, §4B, §9, §14.2, §14.3, §18, §25 and §27.

Stage 1 worked only from the repository, the branch, the DesainPakeAI project MULTIPLECORP and official documentation; nothing was recovered from session transcripts or conversation history (DIR-046 §2).

## Official documentation verification

Verified on 2026-10-04 through the web access of the session (DIR-054 §11). Context7 is not installed and was not installed; nothing was installed and no package was added, and no i18n library was chosen. A sub-agent fetched the official pages and reported their addresses; the executor fetched the WCAG 2.5.8 Understanding page, the Laravel 13.x localization page and the shadcn/ui components index and Native Select page directly. No fetched page asked the agent to act.

| Subject | Official source (accessed 2026-10-04) | Version shown | Verified for the direction |
| --- | --- | --- | --- |
| shadcn/ui components | `https://ui.shadcn.com/docs/components`; the pages of the components named here, among them `…/components/native-select`, `…/components/field` and `…/components/radix/sonner`; the guide `https://ui.shadcn.com/docs/forms` | the site shows no release number; changelog entries 2026-03 (CLI v4) and 2026-07 (Base UI as the default; Toast) | Present: Sidebar, Navigation Menu, Tabs, Table, Data Table, Card, Badge, Button, Input, Textarea, Select, Native Select, Combobox, Command, Sheet, Drawer, Dialog, Alert Dialog, Alert, Dropdown Menu, Field, Label, Checkbox, Radio Group, Input OTP, Breadcrumb, Chart, Skeleton, Separator, Tooltip, Popover, Toggle Group, Avatar, Scroll Area, Progress. Base UI is the default primitive library and Radix stays documented; feedback is Toast with Base UI and Sonner with Radix, whose own Toast is deprecated; forms are built with Field, Form being a guide; React Aria was added in 2026-07 |
| shadcn/ui theming and installation | `https://ui.shadcn.com/docs/theming`; `…/docs/installation/manual`; `…/docs/tailwind-v4`; `…/docs/react-19`; `…/docs/components-json`; `…/docs/cli`; `…/docs/installation/laravel`; `…/docs/changelog` | CLI v4 (March 2026) | Theming through CSS variables — `--background`, `--foreground`, `--card`, `--primary`, `--primary-foreground`, `--muted`, `--accent`, `--destructive`, `--border`, `--input`, `--ring`, `--chart-1`–`--chart-5` and `--sidebar-*` — written in OKLCH and exposed to Tailwind with `@theme inline`; dark mode through `@custom-variant`; the list has no `--destructive-foreground`; one discrepancy: the Sidebar page still shows HSL values. The installation target is Tailwind CSS v4 with React 19; the CLI offers a Laravel template, and the Laravel guide places components in `resources/js/components/ui/` |
| Laravel starter kit | `https://laravel.com/docs/starter-kits` | 13.x | The React starter kit uses Inertia 3, React 19, Tailwind 4 and shadcn/ui |
| Tailwind CSS | `https://tailwindcss.com/docs/theme`; the release announcement on `https://tailwindcss.com/blog` | v4.3, announced 8 May 2026; patch v4.3.3 of 16 July 2026 from GitHub's releases API | Theme variables are declared in `@theme`; each namespace becomes CSS variables and utilities; `@theme inline` and `@theme static` exist; `@theme` must be top-level |
| Laravel localization | `https://laravel.com/docs/localization` (the same as `/docs/13.x/localization`); `https://laravel.com/docs/releases` | 13.x, released 17 March 2026 | Translation strings in `lang/` as PHP arrays or JSON files; `php artisan lang:publish`; `App::setLocale`, `App::currentLocale` and `App::isLocale`; the default and fallback locales from `APP_LOCALE` and `APP_FALLBACK_LOCALE` (`fallback_locale` in the 13.x skeleton's `config/app.php`) |
| Inertia.js shared data | `https://inertiajs.com/docs/v3/getting-started`; `…/docs/v3/data-props/shared-data`; `…/docs/v3/data-props/partial-reloads`; `…/docs/v3/the-basics/manual-visits`; the v3 upgrade guide | v3, released 26 March 2026 | Shared data reaches every page through `HandleInertiaRequests::share` or `Inertia::share`, with lazily evaluated closures and `shareOnce`, and is read with `usePage().props`; partial reloads and `router.reload` refresh named props; `Inertia::lazy` was removed in favour of `optional`. The current locale can therefore travel as a shared prop — SECURITY WS-07 already allows "locale" there — and a language switch can reload the props; the mechanism is Stage 2 and Stage 3 work |
| WCAG 2.2 | `https://www.w3.org/TR/WCAG22/`; the Understanding pages under `https://www.w3.org/WAI/WCAG22/Understanding/` | W3C Recommendation of 12 December 2024 (the history also lists 5 October 2023; errata exist) | **1.4.3** Contrast (Minimum), AA: 4.5:1, and 3:1 for large text — at least 18 pt (24 px) or 14 pt (about 18.5 px) bold; ratios are not rounded. **1.4.10** Reflow, AA: no two-dimensional scrolling at 320 CSS px wide (256 px high for horizontal scrolling), except content such as data tables that needs a two-dimensional layout. **1.4.11** Non-text Contrast, AA: 3:1 for user-interface components and their states and for graphical objects needed to understand content. **1.4.12** Text Spacing, AA: no loss with line height 1.5, paragraph spacing 2, letter spacing 0.12 and word spacing 0.16 times the font size. **2.4.11** Focus Not Obscured (Minimum), AA: a focused component is not entirely hidden by author-created content. **2.5.8** Target Size (Minimum), AA: 24 × 24 CSS px, with the exceptions Spacing, Equivalent, Inline, User agent control and Essential |

Limitations: the shadcn/ui site states no release number; the Tailwind patch number comes from GitHub's API rather than the documentation; the WCAG Recommendation page was truncated in the fetch, so 2.5.8 was read on its Understanding page. Every version-dependent statement of [DESIGN_REFERENCES §5](../DESIGN_REFERENCES.md#5-draft-mapping-to-shadcnui) rests on this table.

## Diagnosis

The approved P7 baseline was inspected read-only (DIR-054 §12): the three documents, and the old exploration pages in the DesainPakeAI project — the page sources read with `dpai file read` (`dpb01-a`, `dpb02-a`, `dpb03-a`, `dpb04-a`, `dpb05-a`, `dpb09-b` and the three reference pages of the normalized design, `dpb18-a` desktop shell and Owner's Beranda, `dpb18-b` desktop project detail and `dpb18-c` phone receiving), `dpai preview verify` of the `dpb18` pages, and local captures at 1,440 × 900 and 390 px with the Chrome already installed, in the temporary directory, as on 2026-10-02. No old page was modified (see [Prototypes](#prototypes)). Each finding cites a document section, decision, page or measured value; nothing is attributed to the Owner beyond source records 31, 32 and 50 (K4).

### Planner hypotheses H-1 to H-5

Recorded in DIR-054 §2 as planner input, the lowest authority level (K4), and verified here.

| Hypothesis | Result | Evidence |
| --- | --- | --- |
| H-1 Typography and density | CONFIRMED | DESIGN_SYSTEM §5.2, §5.3 and §7: seven text styles from 12 to 20 px and nothing above 20 px; 32 px controls with a fine pointer; 36 px rows; one density (D-DS-02, D-DS-03). Measured in the source of `dpb18-a`: title 20, section 16, sub 14, body 14, data 13 and label 12 px; control 32 px; row 36 px. `dpb02-a` sets 11 px text five times, below the system's own 12 px floor |
| H-2 Module-oriented navigation | CONFIRMED | INFORMATION_ARCHITECTURE §5 and §6.1: ten areas and, for the Owner, four entries under *Khusus Owner*; Gudang alone holds nine entries, Keuangan seven, Data Master five. The capture of `dpb18-a` shows 23 sidebar rows with Gudang open |
| H-3 Duplicate navigation in the project detail | CONFIRMED | D-IA-09 and PT-31: the facts, a next step, a strip of derived facts per part and the eleven parts as tabs. The capture of `dpb18-b` shows the next step, an eight-part strip and eleven tabs above the content, so each part's state appears twice before any part is opened |
| H-4 A Beranda that is hard to scan | CONFIRMED | D-DS-05 (no chart on any V1 screen) and D-IA-07 (stacked signal groups; the period figures as a plain table, *Gabungan* last). In the capture of `dpb18-a` at 1,440 × 900 the period figures start at about y = 969, below the fold; every signal row has the same weight and its counts are small inline text |
| H-5 The Ramp direction rejected for lime text, display sizes and a chat composer | CONFIRMED, with a qualification | D-DS-01 and DPB-01 give exactly those reasons, and session DPS-05 of P7_QUALITY_GATE records 19 lint findings; lime measures 1.23:1 on white; the reference defines a display role `clamp(44px, 6vw, 56px)` and a chat-composer recipe. But the reference itself uses lime as a fill under near-black text (16.02:1), never as text; the 19 findings are 10 schema warnings, 7 orphaned tokens, 1 false positive and 1 information line ([DESIGN_REFERENCES §8](../DESIGN_REFERENCES.md#8-lint-findings-and-dispositions)); and DPB-01 evaluated the reference only as "a fourth baseline", without a page of its own (P7_QUALITY_GATE, Briefs; the project holds `dpb01-a` to `dpb01-c` only). Its usable core was discarded with its unusable parts |

### The re-evaluation list of source record 31

| Question | Finding | Evidence |
| --- | --- | --- |
| What makes the current UX confusing | Three structures make the user carry the system's map: a module sidebar of up to 23 rows (H-2); the parts of a project shown three times (H-3); a Beranda whose rows all weigh the same, with the figures below the fold (H-4) | INFORMATION_ARCHITECTURE §5, §6.1; D-IA-07; D-IA-09; captures of `dpb18-a`, `dpb18-b` |
| What creates excessive density | Small type, small controls and one compact density on every screen, including those with little data — Beranda, forms and sign-in | H-1; D-DS-03; DESIGN_SYSTEM §18, SLOP-25 mapped to "one compact desktop density; 36 px rows" |
| Where navigation hierarchy is too complex | Two levels at once in the sidebar, groups opening in place; fourteen top-level entries for the Owner; the phone's *Lainnya* sheet repeats the whole tree | INFORMATION_ARCHITECTURE §5, §6.1, §6.2; D-IA-01 |
| Where too many concepts or actions are exposed at once | The project detail (H-3); the Owner's Beranda with five groups of up to 9, 12, 8 and 12 statements and up to six period figures — up to 47 rows, all expanded — with QS-15's ten completion predicates as a group of its own | INFORMATION_ARCHITECTURE §9 (groups and budget); D-IA-07 |
| Where the IA reflects backend or domain structure | The areas follow record families in business-flow order rather than tasks. *Pengiriman* is a top-level area although every delivery, drop-ship, handover and client return belongs to a project's demand line; *Kasus Koreksi* is one although corrections start from the record they correct; the eleven project parts mirror record families — *Item* and *Pemenuhan*, *Penagihan* and *Pembayaran*, *Dokumen* and *Dokumen Administrasi* are separate tabs for one task each | INFORMATION_ARCHITECTURE §5 ("in the order of the business flow"; "Correction entry points are not an area"); SCR-10; CAP-07; WF-FUL-01–WF-FUL-05 |
| Where progressive disclosure is insufficient | Deferred loading exists, but nothing is folded for the reader: every group shows every statement, the period table every figure for every company, and the project detail every part's derived fact before a part is opened | PF-34; INFORMATION_ARCHITECTURE §9; D-IA-07; D-IA-09 |
| Where mobile and desktop composition can be simpler | The Owner's Beranda is one stack "in the same order on phone and desktop", so the desktop leaves its width unused and the phone gets the desktop's full list. What works on the phone is kept: the bottom bar, the project parts as drill-in rows, the fixed scan field and receiving as a checklist | D-IA-07; D-IA-03; D-IA-09; D-DS-08; D-UX-11; capture of `dpb18-a` |
| Where the selected DesainPakeAI direction was discarded unnecessarily | Its canvas, surface and ink structure, the lime decision accent as a fill, the 15 px body, 8 px panels, the flat structure and the decision rail all work under WCAG 2.2; only lime as text, the display sizes and the chat composer did not | H-5; D-DS-01; DPB-01; [DESIGN_REFERENCES §2–§4](../DESIGN_REFERENCES.md#2-relevant-components-and-screens); [contrast](#b-visual-direction-and-draft-tokens) |
| Where visual hierarchy, spacing, typography, grouping, action priority and onboarding are weak | Hierarchy: with nothing above 20 px the page title, section headings and figures compete. Spacing and grouping: hairline rules in one 36 px rhythm, panels only "where a group needs its own boundary". Action priority: the primary action is a 32 px deep-blue button among 32 px controls. Onboarding: companies have a derived readiness list, but people get no orientation — the page header holds a title, a facts line and actions, and an empty state is one quiet sentence | DESIGN_SYSTEM §7; SLOP-26 and SLOP-01 mappings; PT-12; D-DS-01; ADMIN_FLOW §12.4; PT-03; PT-19; DESIGN_SYSTEM §16 |
| Whether "Tinta" should remain, be adapted or be superseded | **Superseded** as the visual foundation: D3 replaces it with the Owner's reference (K3), and its compact scale is the cause of H-1. **Its accessibility discipline stays**: measured contrast, status never by colour alone, the focus ring, tabular figures and one self-hosted family | D3; K3; D-DS-01, D-DS-02, D-DS-04; SECURITY WS-08; [DESIGN_REFERENCES §3–§6](../DESIGN_REFERENCES.md#3-extracted-principles) |
| Whether P7/APPR-008 should be amended or superseded | **Superseded through a new baseline**, as D3 decides: the findings are structural — navigation, Beranda, project detail, scale and density — not material for a narrow amendment. APPR-008 stays historical provenance; Stage 3 replaces INFORMATION_ARCHITECTURE and DESIGN_SYSTEM in place and aligns ADMIN_FLOW, for approval under APPR-011 | D3 (source record 32 §3); DIR-054 §21; [Plan](#plan) |

### The qualities D3 asks for

Each quality of D3 (source record 32 §3) against the old baseline. *Met* qualities are carried into the direction; the others drive it.

| D3 quality | Old baseline | Evidence |
| --- | --- | --- |
| Modern | Partly met — flat and restrained, but a dense, monochrome page with one deep-blue accent | D-DS-01; captures of `dpb18-a`, `dpb18-b` |
| Clean | Met — no decoration, under the anti-slop rules | DESIGN_SYSTEM §18 |
| Professional | Met | DESIGN_SYSTEM §16 copy and tone |
| Visually calm | Partly met — calm colour, but crowded rows of equal weight | D-IA-07; H-4 |
| Easier for new users | Not met — module navigation, a three-fold project detail and no orientation | H-2; H-3; PT-03; PT-19 |
| Visual breathing room | Not met — one compact density | D-DS-03; H-1 |
| Larger, more readable type | Not met — 12–20 px | H-1 |
| Comfortable control size | Not met on desktop (32 px); met for touch (44 px) | DESIGN_SYSTEM §5.3 |
| Clear whitespace and section separation | Partly met — hairline rules within one tight rhythm | PT-12; SLOP-01 mapping |
| Strong hierarchy | Not met — nothing above 20 px | DESIGN_SYSTEM §7 |
| Progressive disclosure | Partly met — deferred groups load late but show everything | PF-34; D-IA-07 |
| Simpler navigation | Not met | H-2 |
| Task-oriented IA | Partly met — areas in business-flow order | INFORMATION_ARCHITECTURE §5 |
| Fewer top-level entries | Not met — fourteen for the Owner | H-2 |
| No duplicate navigation such as stage plus tab | Not met | H-3 |
| Role dashboards easy to scan | Partly met — a composition per role exists, but rows weigh the same and the figures sit below the fold | D-IA-07; D-IA-08; H-4 |
| Important figures and statuses more visible | Not met — a plain table below the fold; counts as small inline text | D-IA-07; PT-21 |
| Clear primary action | Partly met — one primary action per region, small and deep blue | PT-03; D-DS-01 |
| Mobile-first | Met in structure — bottom bar, drill-in parts, fixed scan field, checklist receiving | D-IA-03; D-DS-08; D-UX-11 |
| Desktop stays productive | Met — keyboard, the multi-line editor, search by number | D-DS-07; D-IA-04; DESIGN_SYSTEM §13 |
| Denser only on data-heavy screens that need it | Not met — the reverse: compact everywhere | D-DS-03 |
| Lime accent used accessibly | Not applicable — no lime; the direction uses it as a fill under ink | D-DS-01; [Direction proposal](#direction-proposal) |
| Suitable for shadcn/ui | Met — an explicit composition of shadcn/ui primitives | DESIGN_SYSTEM §10 |

**Summary.** The old baseline is faithful to business, authorization and accessibility, and its phone floor patterns and desktop editor work; its weaknesses are the module structure, the compact scale and density, and the Beranda and project-detail compositions. The direction keeps the first and replaces the second.

## Persona task analysis

Personas are not roles (DIR-054 §14). V1 has two launch roles, Owner and Admin Operasional, with individual capability and company grants (PERMISSIONS_MATRIX RG-01–RG-03). The three personas below use the fictitious names of the prototypes. Every task comes from V1 scope; "J-" names the old ADMIN_FLOW journey with its WORKFLOWS context tag.

### Owner — desktop and phone

| Item | Content | Sources |
| --- | --- | --- |
| Most frequent tasks | Check the period position and what needs attention, for all companies or one; review recorded activity; look up a project, invoice or receivable, often on the phone; read reports per company and consolidated | CAP-17; CAP-11; J-ACC-01 `[both]`; QS-14; SCR-05, SCR-07, SCR-47, SCR-48; V1_SCOPE language and experience contract (mobile monitoring and lookup) |
| Most critical tasks | The Owner-only decisions: accounts, grants and credential links; attribution of unexplained losses and count surpluses; write-off; Force Complete; numbering start and seed; tax configuration; the opening-import commit; acting on a security notice | OD-01–OD-04, OD-08, OD-09, OD-11–OD-13; J-ACC-02 `[desk]`; WF-FIN-05 `[desk]`; WF-PRJ-03 `[desk]`; NM-03; AU-12; LG-07 |
| Sees first | The period figures for *Gabungan* or one company — sales value, Cash-In, active receivables with their overdue part, managerial profit; then the attention items — security notices, new activity to review, shortfalls awaiting attribution, routed refusals, surplus findings, residual obligations; then the work queues | CALC-05, CALC-07, CALC-08, CALC-11, CALC-12, CALC-14; QS-14, QS-16, QS-18, QS-20, QS-22; INFORMATION_ARCHITECTURE §9 |
| Reaches quickly | The review and activity pages, *Selisih & Temuan*, *Pengguna & Akses*, a project and its completion, the consolidated report, search | SCR-07, SCR-59, SCR-60, SCR-27, SCR-57, SCR-10, SCR-14, SCR-48, SCR-08 |

### Admin Operasional — desk work

| Item | Content | Sources |
| --- | --- | --- |
| Most frequent tasks | Quotations with many lines; purchases with lines and charges; reservations; invoices and billing; payments with their applications; documents and *Dokumen Administrasi*; project setup | J-QUO-01, J-PUR-01, J-PUR-02, J-INV-02, J-FIN-01, J-FIN-02, J-DOC-01, J-ADM-01 `[desk]`; J-FIN-03, J-PRJ-01 `[both]`; CAP-04, CAP-05, CAP-08–CAP-10 |
| Most critical tasks | Issuing documents and invoices (irreversible, numbered); recording a payment with its applications; corrections that state what is reversed and what stays; project completion; confirming a duplicate warning | IP-08; NM-03; AX-18; D-UX-06; D-UX-10; CM-01–CM-38; SCR-14; QS-15 |
| Sees first | The action queue by kind of work, each count per company and never summed, each group present only with its capability | QS-01–QS-13, QS-15–QS-21 by read class (PJ-13); CS-08; D-IA-08 |
| Reaches quickly | The project list and detail; the multi-line editors; search by document number; the Keuangan lists | SCR-09, SCR-10, SCR-13, SCR-17, SCR-38, SCR-40, SCR-41; PF-19; D-IA-04; D-DS-07 |

### Warehouse/field — an Admin Operasional account with warehouse grants, phone-first

| Item | Content | Sources |
| --- | --- | --- |
| Most frequent tasks | Receiving (*Barang Masuk*); dispatch (*Barang Keluar*); checking stock and racks; delivery records; stock opname | J-INV-01, J-INV-03, J-INV-05 `[floor]`; J-FUL-02 `[both]`; SCR-18, SCR-19, SCR-21, SCR-22, SCR-24, SCR-28, SCR-29 |
| Most critical tasks | The right quantities and condition at receipt — *Layak pakai*, *Rusak diterima*, *Ditolak*; the right lots and serials at dispatch, another company's lot only with `stock.allocate_intercompany`; counts and their findings; condition changes and losses | J-INV-01 (AX-04); J-INV-03, J-INV-07 (XL-1); J-INV-04, J-INV-06 `[floor]`; AX-06; QS-18 |
| Sees first | Purchases waiting for goods; lines ready to dispatch; counts in progress; low stock | QS-05 (the receiving worklist); the SCR-22 worklist of remaining reservations (CALC-02); SCR-24; QS-17 |
| Reaches quickly | The scan field, which also takes typed codes, with manual product lookup when no scanner is attached; *Barang Masuk*, *Barang Keluar*, *Stok* | D-IA-04; IP-15; D-UX-09 (keyboard wedge only); V1_SCOPE language and experience contract |
| Devices | USB scanners exist; phones, browsers and scanner pairing are not verified | DEP-03; GAP-020 (P8 device UAT) |

## Provisional dispositions

Each earlier decision and anti-slop rule, with a one-line reason (DIR-054 §15). Re-derived count: 17 (D-IA-01–D-IA-17, [INFORMATION_ARCHITECTURE §4](../INFORMATION_ARCHITECTURE.md#4-design-decisions)) + 9 (D-DS-01–D-DS-09, [DESIGN_SYSTEM §4](../DESIGN_SYSTEM.md#4-design-decisions)) + 12 (D-UX-01–D-UX-12, [ADMIN_FLOW §4](../ADMIN_FLOW/s01-04-foundations.md#4-design-decisions)) + 45 (SLOP-01–SLOP-45, [DESIGN_SYSTEM §18](../DESIGN_SYSTEM.md#18-anti-ai-slop-mapping)) = **83**: KEEP 64, ADAPT 12, SUPERSEDE 6, DECIDE-IN-STAGE-2 1. These are provisional; the final dispositions belong to Stage 3, and the DECISION_INDEX statuses of D-IA, D-DS and D-UX do not change in Stage 1.

### D-IA — navigation and information architecture

| ID | Disposition | Reason |
| --- | --- | --- |
| D-IA-01 | ADAPT | Keep a desktop sidebar that collapses to an icon rail; replace the groups that open in place by flat area entries whose pages carry their sub-pages as tabs (H-2; D3 simpler navigation) |
| D-IA-02 | KEEP | The company filter stays in the header of lists and Beranda, never in the shell, and never feeds a form (CS-01–CS-03) |
| D-IA-03 | KEEP | The phone bottom bar Beranda · Proyek · Gudang · Cari · Lainnya serves the floor tasks (GAP-020); an entry the account cannot open is absent, as a hint only (AZ-01) |
| D-IA-04 | KEEP | One entry for typed and scanned codes (PF-19; QB-06) |
| D-IA-05 | KEEP | Session notices in a strip, the idle warning as a dialog (H7-05; AU-07; AU-08) |
| D-IA-06 | KEEP | No badge or count on a navigation entry: across companies it is the total CS-08 forbids for an Admin (CS-08; PF-15) |
| D-IA-07 | SUPERSEDE | The Owner's Beranda becomes figures first, then attention, two charts of defined values and the queues in three panels (H-4; D3 role dashboards easy to scan) |
| D-IA-08 | ADAPT | The Admin queue stays grouped by kind of work with every count per company and never summed (CS-08; PF-24); the groups become panels and the first worklist opens in place |
| D-IA-09 | SUPERSEDE | Facts and one next step stay; eight tabs replace the eleven, the part states appear only in *Ringkasan*, and the duplicate strip goes (H-3; D3 no duplicate navigation) |
| D-IA-10 | KEEP | The *Dokumen Administrasi* checklist with derived states (BR-ADM-01; FL-07) — a section of the project's *Dokumen* tab and a cross-project tab under Proyek |
| D-IA-11 | KEEP | The review queue's two parts and its chronological stream (PERMISSIONS_MATRIX §10; IX-13) |
| D-IA-12 | KEEP | One section per company in a report, company and range required (CS-08; PF-27) |
| D-IA-13 | KEEP | The numbering area ordered by what each type needs (NM-03; C-10) |
| D-IA-14 | KEEP | Master records as their own addressable pages, search-first against duplicates (PJ-10; PF-21) |
| D-IA-15 | ADAPT | Receivables stay a list of invoices; ageing is shown per company on the Owner's Beranda and in the report, never as a cross-company count for an Admin (CS-08; CALC-07) |
| D-IA-16 | KEEP | Dispatch and delivery stay two linked screens (GL-010, GL-013) |
| D-IA-17 | KEEP | Stock by product with the four quantities and the pending line, under the pooled projection (PJ-01–PJ-04; CALC-01) |

### D-DS — visual design

| ID | Disposition | Reason |
| --- | --- | --- |
| D-DS-01 | SUPERSEDE | "Tinta" gives way to the Ramp-based direction with lime used only accessibly (D3; K3) |
| D-DS-02 | ADAPT | Inter stays, self-hosted, with tabular figures and 16 px in touch inputs (SECURITY WS-08); the 12–20 px scale becomes 13–26 px (H-1) |
| D-DS-03 | SUPERSEDE | One compact density becomes a comfortable default with a dense mode on named screens (AICWDF §18.2; D3) |
| D-DS-04 | ADAPT | A state keeps label, icon shape and tone, never colour alone (WCAG 1.4.1); the bordered rectangle becomes the reference's tinted pill |
| D-DS-05 | SUPERSEDE | Charts are allowed for defined CALC values and QS signals only, each with a table alternative (D3 important figures visible); fake metrics stay banned (D4) |
| D-DS-06 | KEEP | The outcome message in the sticky action region (HO-01; WCAG 4.1.3) |
| D-DS-07 | KEEP | The desktop multi-line editor and the phone's line sheets (V1_SCOPE multi-item entry; UXS-35) — a named dense screen |
| D-DS-08 | KEEP | The phone scan field fixed at the bottom (GAP-020; PT-20) |
| D-DS-09 | KEEP | The capability-by-company preview (H7-06; GAP-032; RG-06, RG-07) |

### D-UX — interaction

| ID | Disposition | Reason |
| --- | --- | --- |
| D-UX-01 | KEEP | No optimistic result (HO-01) |
| D-UX-02 | KEEP | Inputs frozen while an answer is unknown (HO-02; CI-02; CI-05) |
| D-UX-03 | DECIDE-IN-STAGE-2 | The in-place re-authentication must now carry the second factor and Google-linked accounts; it is redesigned with the authentication journeys (GAP-044; H7-19; AU-06–AU-08) |
| D-UX-04 | KEEP | One automatic retry with the same identifier (HO-07; RY-07) |
| D-UX-05 | KEEP | The compact "Tanggal transaksi" line (HO-08; ST-08) — prototyped in P7 and P8 |
| D-UX-06 | KEEP | A duplicate warning confirmed by naming its matches (HO-05; CONCURRENCY_IDEMPOTENCY §11) |
| D-UX-07 | KEEP | Commands answered one by one (SF-CMD step 8; PX-05) |
| D-UX-08 | KEEP | The C-10 refusal names only the Admin's own record (C-10; NM-03) |
| D-UX-09 | KEEP | Keyboard-wedge scanning only (GAP-020; SECURITY WS-08; V1_SCOPE D-05) |
| D-UX-10 | KEEP | A correction states what is reversed and what stays (BR-CR-01; BR-CR-02; AC-12) |
| D-UX-11 | KEEP | Receiving and dispatch as a line checklist with the scan field at the bottom (GAP-020; SF-SCAN; IP-19) — prototyped in P8 |
| D-UX-12 | KEEP | The project reservation offered inside the receipt, one command (AX-04; WF-INV-01) — prototyped in P8 |

### SLOP-01–SLOP-45 — anti AI-slop rules

D4 keeps the rules that protect clarity, consistency, accessibility, professional appearance and freedom from decorative clutter, fake metrics, meaningless cards, duplicate actions and gimmicks, and revises those that force excessive density, small type or tight spacing. A kept rule whose old mapping names a superseded value — two radii, a white canvas — keeps its prohibition; Stage 3 re-points the mapping.

| ID | Disposition | Reason |
| --- | --- | --- |
| SLOP-01 | ADAPT | Panels where a group of work needs a boundary — figures, queue groups, form sections — as the reference's card recipe; never a card for every section (D4 meaningless cards) |
| SLOP-02 | KEEP | No panel inside a panel: nesting hides the hierarchy (D4 clarity; meaningless cards) |
| SLOP-03 | KEEP | No giant rounded containers; radii stay 4–8 px (D4 professional appearance) |
| SLOP-04 | KEEP | No excessive radius (D4 professional appearance; consistency) |
| SLOP-05 | ADAPT | Pills only for status badges, counts and compact filters, as in the reference — never "pill-shaped everything" |
| SLOP-06 | KEEP | No gradients (D4 decorative clutter) |
| SLOP-07 | KEEP | No decorative gradients (D4 decorative clutter) |
| SLOP-08 | KEEP | No glow effects (D4 decorative clutter) |
| SLOP-09 | ADAPT | The bright lime is allowed only as a fill under ink on the decision element, never as text or a thin line (K3; WCAG 1.4.3, 1.4.11); the other tones stay deep |
| SLOP-10 | KEEP | Opaque surfaces, no glassmorphism: transparency costs contrast (D4 accessibility; decorative clutter) |
| SLOP-11 | KEEP | A plain canvas — the warm neutral `#F7F7F2` replaces white, without pattern or mesh (D4 decorative clutter) |
| SLOP-12 | KEEP | No decorative shapes (D4 decorative clutter) |
| SLOP-13 | KEEP | No illustrations, empty states included (D4 gimmicks) |
| SLOP-14 | KEEP | No shadow at rest, elevation only for overlays — the reference's own flat structure (D4 decorative clutter) |
| SLOP-15 | KEEP | Motion only as feedback, none under reduced motion (D4 gimmicks; accessibility) |
| SLOP-16 | KEEP | No parallax (D4 gimmicks; accessibility) |
| SLOP-17 | KEEP | Hover is never the only path (D4 accessibility; touch use) |
| SLOP-18 | ADAPT | At most four key figures, on the Owner's Beranda only, each a defined CALC value with its report link; none without a definition (D3; D4 fake metrics) |
| SLOP-19 | KEEP | No welcome banner (D4 decorative clutter) |
| SLOP-20 | ADAPT | Greetings and SaaS clichés stay banned; the sign-in page has AICWDF §4A.10's plain heading and one supporting line, and a page may state its purpose plainly for new users (D3) |
| SLOP-21 | KEEP | No marketing copy; plain operational Indonesian (D4 professional appearance; D7) |
| SLOP-22 | KEEP | No emoji as decoration (D4 professional appearance) |
| SLOP-23 | KEEP | Icons only where they carry meaning (D4 clarity) |
| SLOP-24 | KEEP | Not an icon in every label or button (D4 clarity; decorative clutter) |
| SLOP-25 | SUPERSEDE | Its mapping — one compact density, 36 px rows — conflicts with AICWDF §18.2; the direction's density policy replaces it and keeps §18.2's own guard, "not artificially empty" (D4) |
| SLOP-26 | ADAPT | Headings may exceed 20 px where hierarchy needs it — title and figure 26 px; display sizes of 40–56 px stay banned (D3; §18.2) |
| SLOP-27 | KEEP | No hero-like application headers; titles stay 26 px (D4 clarity; AICWDF §18.2, not artificially empty) |
| SLOP-28 | KEEP | No noisy dashboards: at most five groups (QB-10), figures and charts from defined values only (D4 clarity; fake metrics) |
| SLOP-29 | KEEP | One accent, tones bound to meanings (D4 consistency) |
| SLOP-30 | KEEP | A badge only for a state (D4 clarity) |
| SLOP-31 | KEEP | Consistent radius — three values and a pill, each with a role (D4 consistency) |
| SLOP-32 | KEEP | Consistent icon sizes (D4 consistency) |
| SLOP-33 | KEEP | One spacing scale (D4 consistency; AICWDF §18.2 spacing consistency) |
| SLOP-34 | ADAPT | Charts only for defined CALC values and QS signals, with a table alternative; fake analytics stays banned (D3; D4) |
| SLOP-35 | KEEP | No landing-page look: V1 is an operational application (D4 professional appearance) |
| SLOP-36 | KEEP | No concept-shot look; decisions grounded in task speed (D4 clarity; DIR-040 F6) |
| SLOP-37 | KEEP | No generic template look (D4 professional appearance) |
| SLOP-38 | KEEP | No generated admin-dashboard look (D4 fake metrics; meaningless cards) |
| SLOP-39 | KEEP | No feeds or infinite scroll; "Muat Lagi" is explicit (PT-08; D4 gimmicks) |
| SLOP-40 | KEEP | No reactions (D4 gimmicks) |
| SLOP-41 | KEEP | No likes (D4 gimmicks) |
| SLOP-42 | KEEP | No streaks (D4 gimmicks) |
| SLOP-43 | KEEP | No confetti (D4 gimmicks) |
| SLOP-44 | KEEP | No gamification or completion percentages (PT-29; D4 gimmicks) |
| SLOP-45 | KEEP | No chat bubbles; the reference's chat composer is not taken over (K3; D4 gimmicks) |

## Direction proposal

**Rejected at Checkpoint C1 for focused rework — DIR-055.** See [Checkpoint C1 — Owner verdict](#checkpoint-c1--owner-verdict).

**Non-normative — for Checkpoint C1 (DIR-054 §16).** This proposal changes no normative document: INFORMATION_ARCHITECTURE, DESIGN_SYSTEM and ADMIN_FLOW stay as approved until Stage 3. It preserves every P0–P6 rule it touches — company scope and projection, authentication, the command model and the performance budgets — and proposes no schema change. In this record P1–P8 name the eight prototyped surfaces of DIR-054 §17 — sign-in, the shell, the two Beranda compositions, the project list and detail, the multi-line form and the phone warehouse task — listed under [Prototypes](#prototypes), not phases; the reference record is [DESIGN_REFERENCES](../DESIGN_REFERENCES.md).

### a) IA skeleton

**Desktop — flat areas with tabs.** The sidebar holds one level of entries, in this order: Beranda · Proyek · Pembelian · Gudang · Keuangan · Dokumen · Laporan · Data Master, and for the Owner, under *Khusus Owner*: Tinjauan & Aktivitas · Pengaturan. That is ten entries for the Owner, where the old shell has fourteen and up to 23 rows with a group open, and at most eight for an Admin. An entry appears only when one of its pages is open to the account — a hint, the server decides (AZ-01) — and no entry carries a count (D-IA-06). Each area page carries its sub-pages as tabs, at most five visible and the rest under *Lainnya*:

| Area | Tabs (*Lainnya* after the fifth) | Old screens |
| --- | --- | --- |
| Beranda | — (one page per role) | SCR-05, SCR-06 |
| Proyek | Semua Proyek · Penawaran · Pengiriman · Dokumen Administrasi · Kasus Koreksi | SCR-09–SCR-14, SCR-28–SCR-33, SCR-37 |
| Pembelian | — (the list, its detail and form) | SCR-15–SCR-17 |
| Gudang | Stok · Barang Masuk · Barang Keluar · Stok Opname · Reservasi · *Lainnya*: Penyesuaian & Kondisi · Retur ke Pemasok · Riwayat Stok · Selisih & Temuan | SCR-18–SCR-27 |
| Keuangan | Invoice · Piutang · Pembayaran · Potongan · *Lainnya*: Kredit Pelanggan · Pengeluaran & Kas Lain · Mutasi Bank | SCR-38–SCR-46 |
| Dokumen | Dokumen · Bukti | SCR-34–SCR-36 |
| Laporan | Laporan · Laporan Gabungan (Owner) · Ekspor Saya | SCR-47–SCR-49 |
| Data Master | Klien · Pemasok · Produk & Jasa · Lokasi Rak · Impor Data Awal | SCR-50–SCR-53, SCR-58 |
| Tinjauan & Aktivitas (Owner) | Tinjauan · Riwayat Aktivitas · Log Keamanan | SCR-07, SCR-59, SCR-60 |
| Pengaturan (Owner) | Perusahaan · Pengguna & Akses · Penomoran Dokumen · Pajak | SCR-54–SCR-57 |

*Pengiriman* joins Proyek because every delivery, drop-ship, handover and client return belongs to a project's demand line (WF-FUL-01–WF-FUL-05; CAP-07); dispatch stays in Gudang and delivery stays its own record, linked (D-IA-16). Purchases keep their own area because a replenishment purchase has no project (WF-PUR-02), as D-IA-01 found. The top bar keeps the search field "Cari atau pindai kode" with Ctrl K, *Pintasan* and the account menu; the account menu holds *Akun Saya*, the display language and *Keluar*.

**Phone.** The bottom bar stays Beranda · Proyek · Gudang · Cari · Lainnya (D-IA-03). An area opens as a task list — Gudang shows *Barang Masuk*, *Barang Keluar*, *Stok Opname*, *Cek Stok* and *Reservasi* as rows with a line of purpose each, the rest under "Lainnya di gudang". Its task rows carry the per-company counts of their worklists — QS-05, the SCR-22 worklist and SCR-24's counts in progress — as page content, not on a navigation entry (D-IA-06); their budget is checked in Stage 2. *Lainnya* opens a sheet with the other areas, the account, the language and *Keluar*. Focused tasks — a scan step, a form sheet — hide the bottom bar.

**Project detail.** The header holds the title, one facts line — company code, number, client and unit, PIC, deadline with days left, status — the primary action and *Tindakan Lain*; under it one next-step band; then eight tabs: *Ringkasan · Item & Pemenuhan · Penawaran · Pengadaan · Pengiriman · Dokumen · Keuangan · Riwayat*. The eleven old parts merge where they serve one task: *Item* with *Pemenuhan*, *Dokumen* with *Dokumen Administrasi*, *Penagihan* with *Pembayaran*. Each part's state appears once, in *Ringkasan*'s "Kemajuan proyek" list with a link to its tab — the strip above the tabs goes (H-3). A tab whose capability the account lacks is absent: *Pengadaan* without `cost.view`, *Keuangan* without `finance.view` (SCR-10 audience; PJ rules). On the phone the parts are the drill-in list of D-IA-09.

**Beranda per role.**

- **Owner** (P3): the view switch *Gabungan* · ARJ · BTN · CKP and the period; four key figures; *Perlu perhatian Anda*; the charts "Umur piutang" and "Penjualan dan kas"; *Antrean pekerjaan* in three panels — Operasional, Penagihan & kas, Penyelesaian proyek. At 1,440 × 900 the figures and the first attention rows are above the fold. The five deferred groups of QB-10 remain: attention (signal group G1 of INFORMATION_ARCHITECTURE §9), the three queue panels (its groups G2–G4) and the period figures with their charts (its group G5, one snapshot, TX-10).
- **Admin Operasional** (P4): the working filter "Tampilkan: Semua perusahaan" with the line "Angka per perusahaan; tidak dijumlahkan antar perusahaan."; a panel *Gudang & proyek* whose first group opens to its worklist (open purchases with "Catat Barang Masuk"); beside it *Penagihan & kas* (present only with `finance.view`), *Dokumen & kasus* and *Penyelesaian proyek*. Four deferred groups, as QB-10. No money figure.
- **Warehouse/field** (P4, phone): the worklists "Barang masuk menunggu" (QS-05) and "Siap dikeluarkan" (the SCR-22 worklist), each row with "Mulai", then counts in progress and low stock. Whether these worklists fit the Admin budget of QB-10 is checked in Stage 2.

A signal with nothing pending is not listed; a group with nothing pending shows its one quiet sentence; "Lihat semua" opens every statement of the group with its count or its empty sentence (INFORMATION_ARCHITECTURE §9.1), so no statement is lost.

**Company context.** The company selector is a view filter of Beranda and of lists, never in the shell (D-IA-02). It never feeds a form: a form takes its company explicitly or from its root record — the purchase form of P7 states "Perusahaan PT Bentara Niaga (BTN) — mengikuti proyek" (CS-01–CS-03). An Admin's counts are per company with the company code and never summed (CS-08; PF-15); *Gabungan* and every consolidated figure exist only for the Owner (`reports.consolidated`; CALC-12).

### b) Visual direction and draft tokens

The draft tokens are in [DESIGN_REFERENCES §6](../DESIGN_REFERENCES.md#6-draft-tokens) and its responsive rules in [§7](../DESIGN_REFERENCES.md#7-draft-responsive-rules); all 28 prototype pages apply them page-scoped.

- **Type scale.** Body 15 px on desktop and 16 px on phones, data 15 px (14 px in the dense mode), labels and small text 13 px — nothing essential below 13 px; subheadings 17 px, headings 20 px, the page title and key figures 26 px on desktop (22 and 20 px on phones). Against H-1 every role is larger, and the title and figures rise above the old 20 px cap because hierarchy needs them there (AICWDF §18.2 "clear hierarchy", "readability"); the reference's 15 px body is kept. Display sizes of 40–56 px are not adopted: V1 has no hero or landing surface, and §18.2 also asks "not artificially empty".
- **Spacing and rhythm.** A 4 px base with the steps 4–64; 32 px page padding on desktop and 16 px on phones; at least twice as much space between groups as within them; panels with 16–20 px padding.
- **Control sizes.** 40 px by default with a fine pointer (old: 32 px), 36 px in the dense mode, 48 px with a coarse pointer at any width (old: 44 px) — above WCAG 2.5.8's 24 px. Table rows at least 48 px (40 px dense); phone list rows at least 64 px.
- **Density policy.** Comfortable by default. The dense mode — 36 px controls, 40 px rows, 14 px data — is allowed only on these screens, each because its work is reading or entering long tables row by row: the multi-line editors of quotations and purchases (SCR-13, SCR-17; D-DS-07; prototyped in P7 as "Tampilan padat untuk entri banyak baris"); *Riwayat Stok* (SCR-20); *Mutasi Bank* (SCR-46); *Laporan* and *Laporan Gabungan* (SCR-47, SCR-48); *Riwayat Aktivitas* and *Log Keamanan* (SCR-59, SCR-60). No other screen is dense.
- **Colour roles and the lime accent.** The roles are in DESIGN_REFERENCES §6. Lime `#E4F222` appears only as a fill under ink — the primary action, the selected navigation entry, the selected segment and the attention badge — where ink reads at 16.02:1. It is never text, an icon stroke, a thin line or a chart mark, and never the only cue: the selected entry also has a 4 px dark olive rail, a heavier weight and `aria-current`; the primary button has a 1 px olive edge; the selected segment an olive underline.
- **Radius, elevation and iconography.** Radii of 4 px (compact controls), 6 px (inputs, the primary button) and 8 px (panels, dialogs, sheets), and a pill for status badges, counts and filter chips; no shadow at rest, overlays on a solid surface with a 1 px edge and a scrim. One outline icon family, 16 and 20 px (24 px in the phone bottom bar), always beside a text label except in the collapsed rail, where each icon has its accessible name and a tooltip.
- **Contrast.** Computed with the WCAG 2.2 relative-luminance formula by a script kept outside the repository, for every text and background pair and every non-text indicator of the tokens.

**Text (WCAG 1.4.3, at least 4.5:1)**

| Foreground | Background | Ratio |
| --- | --- | --- |
| ink `#0C0A08` | surface `#FFFFFF` · canvas `#F7F7F2` · surface-muted `#F0F1E8` | 19.76 · 18.39 · 17.37 |
| ink `#0C0A08` | accent `#E4F222` · accent-hover `#C9D61E` · accent-quiet `#F4F8B8` | 16.02 · 12.35 · 17.85 |
| ink-muted `#66675F` | surface · canvas · surface-muted · accent-quiet | 5.72 · 5.32 · 5.03 · 5.17 |
| positive `#137A4A` | positive-soft `#E6F2EB` · surface | 4.67 · 5.37 |
| warning `#9A5600` | warning-soft `#FBF0DF` · surface · canvas | 5.03 · 5.67 · 5.27 |
| danger `#B42318` | danger-soft `#FBE8E6` · surface | 5.57 · 6.57 |
| neutral `#4E4F48` | neutral-soft `#F0F1E8` · surface | 7.28 · 8.28 |
| white `#FFFFFF` | ink | 19.76 |
| accent-edge `#596200` | accent-quiet | 5.98 |
| The reference's warning `#A65D00`, not adopted | warning-soft · surface | 4.45 (fails) · 5.02 |

The lowest adopted text pair is positive on its tint, 4.67:1; every pair meets 4.5:1 at any size.

**Non-text (WCAG 1.4.11, at least 3:1)**

| Indicator | Against | Ratio |
| --- | --- | --- |
| control edge `#85877B` — inputs, selects, checkboxes | surface · canvas · surface-muted | 3.65 · 3.40 · 3.21 |
| accent edge `#596200` — the decision rail, the focus ring, the primary button's edge | surface · canvas · accent · accent-quiet | 6.62 · 6.16 · 5.37 · 5.98 |
| ink — icons, the secondary button's border, the selected tab's rule, chart series 1 | surface · canvas | 19.76 · 18.39 |
| status icons in badges | their soft tints | positive 4.67 · warning 5.03 · danger 5.57 · neutral 7.28 |
| chart series 2–5: `#596200` · `#8A8C80` · `#9A5600` · `#B42318` | surface | 6.62 · 3.42 · 5.67 · 6.57 |
| decorative only, never an indicator: border `#E5E7EB`, border-strong `#B8BAAE`, lime `#E4F222` | surface | 1.24 · 1.97 · 1.23 |

Chart marks are separated by 2 px (stacked) or 6 px (grouped) gaps of the surface, so each mark meets 3:1 against what touches it, and every chart has a data table ("Lihat angka").

### c) Charts and figures

Only values the repository defines; no new metric and no fake metric (D4).

| Figure or chart | Where | Definition |
| --- | --- | --- |
| *Nilai penjualan* — invoices issued in the period, "belum tentu uang masuk" | Owner's Beranda | CALC-08 summed per company by issue date (CALC-13); CALC-12's consolidation for *Gabungan* is the Owner's |
| *Kas masuk* — money actually received in the period | Owner's Beranda | CALC-14, Cash-In by payment date (CALC-13) |
| *Piutang aktif* with its overdue part ("lewat jatuh tempo") | Owner's Beranda | CALC-05 per invoice, summed per company; the overdue part is CALC-07's overdue buckets (QS-10) |
| *Laba manajerial* | Owner's Beranda | CALC-11 per company; CALC-12 for *Gabungan* (Owner only) |
| Chart "Umur piutang" — stacked bars per company | Owner's Beranda | CALC-07 buckets: not yet due, 1–30, 31–60, 61–90 and over 90 days |
| Chart "Penjualan dan kas" — sales value, Cash-In and Cash-Out per company | Owner's Beranda | CALC-08 and CALC-14 for the period (CALC-13) |
| *Nilai proyek* — confirmed value, invoiced, billed, Cash-In | project detail, *Ringkasan* | the project position block (DESIGN_SYSTEM §14.1, PT-24) — server figures |
| Counts per company | both Beranda compositions | QS-01–QS-22 as their statements define them, bounded per company (PF-15) |
| Quantities *Stok fisik · Direservasi · Tidak layak pakai · Tersedia* | Gudang › Stok | CALC-01 (BR-INV-01) |

**Needs definition — not proposed:** multi-month trends (no series, snapshot or budget is defined: CALC-13 defines one period, TX-10 and QB-10 one snapshot), a quotation win rate, collection days (DSO), a stock-value trend and client rankings. Whether the charts fit QB-10 and PF-24 with the period figures in one snapshot is checked in Stage 2.

### d) Language

- **Switch placement.** On the sign-in page an "ID | EN" switch at the top right, on desktop and phone (P1). After sign-in, in the account menu: "Bahasa tampilan: Indonesia | English" with the line "Berlaku untuk akun Anda. Dokumen dan data bisnis tetap berbahasa Indonesia." (P2, desktop); on the phone in *Lainnya* under the account (P2, phone).
- **Behaviour (C-1).** Indonesian (id-ID) is the default and English secondary. Before sign-in the choice may be kept on the device or browser; after sign-in the account's preference applies, per account, and a choice made on the sign-in page does not change it. Issued documents and business data stay Indonesian whatever the display language (D7); names, codes and user-written content are data and are never translated. Every user-facing string is managed copy: the English renderings of P1 and P2 have no missing string.
- **No storage decision.** How the device-local choice and the account preference are kept is not decided here; it is Stage 2 and Stage 3 work under C-1 and SECURITY WS-07 and WS-08.
- **Observation for later change control, read-only, not a proposal** (C-1: the approved data model is inspected first): the approved `users` table (DATABASE §4.1) has no locale or preference column, and none of the 125 logical tables holds a per-account preference; SECURITY WS-07 already lists the locale among the shared props; ARCHITECTURE §16 states that the user-facing locale is Indonesian (P7). A per-account preference therefore has no approved storage place today; if Stage 2 confirms that a new field is genuinely needed, it goes through database change control with its exact change, reason and impact before anything becomes normative, as C-1 requires. Recorded in GAP-037.

### e) Authentication screens

The AICWDF §4A.10 pattern, with AU-04's generic answers and no change to any authentication or security rule (P1):

- **Sign-in.** The heading "Masuk ke MultipleCorp" and one supporting line, "Gunakan akun yang diberikan Owner perusahaan Anda."; *E-mail*; *Kata sandi* with "Tampilkan"; the primary "Masuk"; "Lupa kata sandi? Minta tautan atur ulang kepada Owner." — there is no self-service reset, only the Owner's credential link (AU-12); the divider "atau"; "Lanjutkan dengan Google" (AICWDF §4A.4) with the note "Hanya untuk akun yang sudah ditautkan ke Google. Di perangkat bersama, keluar dari Google setelah selesai." (AU-22, existing accounts only; H7-16). A failed sign-in shows only "E-mail atau kata sandi salah." (AU-04; MSG-19).
- **Second factor, the second screen.** "Masukkan kode verifikasi", *Kode 6 digit*, "Verifikasi" and "Kode berganti setiap 30 detik." After a password the link "Gunakan kode pemulihan" is offered; after Google the screen asks for a code only and offers no recovery code ("Kode pemulihan hanya dapat dipakai setelah masuk dengan kata sandi.") — AU-16, AU-19, AU-22; H7-15. A wrong or expired code gets one generic answer, "Kode tidak cocok atau sudah tidak berlaku. Masukkan kode terbaru dari aplikasi autentikator." "Kembali ke halaman masuk" leads back.
- **Not designed here.** Enrolment, recovery codes, device replacement, the enrolment-only session, the throttled state, the in-place re-authentication and the alignment of ADMIN_FLOW's journeys stay Stage 2 and Stage 3 work (GAP-044; H7-15–H7-19; D-UX-03).

### f) Alternatives

One recommended direction — **A on every axis**, as prototyped — and three genuinely contentious axes, each with two alternatives:

| Axis | A — recommended | B | Recommendation |
| --- | --- | --- | --- |
| 1. Top-level navigation | Ten flat entries for the Owner; each area's sub-pages as tabs on the area page | A grouped sidebar of nine entries that open in place to their sub-pages, as the old shell did, with the merged areas | A: one level to scan, and the sub-pages sit where the work is. B is closer to the old habit but rebuilds the long two-level list behind H-2 |
| 2. Project detail | Eight tabs, part states only in *Ringkasan* | An overview-first page whose parts open as pages of their own, with no tabs | A: every part one click away on desktop and no duplicate. B is simpler at first sight but adds a page load and a back step for every part. The phone uses the drill-in list either way |
| 3. Owner's Beranda | Figures first — the four figures, then attention, the two charts and the queues | Attention first, with the figures and charts on a separate *Keuangan* tab of Beranda | A: D3 asks for important figures to be visible, and attention still starts above the fold at 1,440 × 900. B suits an Owner who opens Beranda mainly to act; the figures are then one tab away |

### g) Direction self-check

The quality gates of AICWDF §18.2 and §27 applied to the eight prototyped surfaces — **PROVISIONAL**: a judgement on Stage 1 prototypes, not the Stage 4 gate. P = PASS, provisional; — = not applicable to the surface; S2 = not yet shown, Stage 2.

| Item | P1 | P2 | P3 | P4 | P5 | P6 | P7 | P8 |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Visual breathing room (§18.2, §27) | P | P | P | P | P | P | P | P |
| Content density appropriate (§18.2, §27) | P | P | P | P | P | P | P | P |
| Auth screen simplicity (§18.2) | P | — | — | — | — | — | — | — |
| Google login visual consistency (§18.2) | P | — | — | — | — | — | — | — |
| Section separation (§18.2) | P | P | P | P | P | P | P | P |
| Spacing consistency (§18.2) | P | P | P | P | P | P | P | P |
| Readability (§18.2) | P | P | P | P | P | P | P | P |
| Modern visual quality | P | P | P | P | P | P | P | P |
| Design system consistency | P | P | P | P | P | P | P | P |
| Primary action clear | P | — | — | P | P | P | P | P |
| Unnecessary duplicate actions: 0 | P | P | P | P | P | P | P | P |
| Back action clear | P | — | — | — | — | P | P | P |
| Navigation contract valid | S2 | S2 | S2 | S2 | S2 | S2 | S2 | S2 |
| Indonesian default | P | P | P | P | P | P | P | P |
| Plain Indonesian | P | P | P | P | P | P | P | P |
| English available | P | P | S2 | S2 | S2 | S2 | S2 | S2 |
| Missing translations: 0 | P | P | S2 | S2 | S2 | S2 | S2 | S2 |
| Language switch | P | P | P | P | P | P | P | P |
| Mobile-first | P | P | P | P | P | P | — | P |
| Responsive | P | P | P | P | P | P | S2 | P |
| Adaptive | P | P | P | P | P | P | S2 | P |
| Loading state | S2 | S2 | S2 | S2 | S2 | S2 | S2 | S2 |
| Empty state | — | S2 | S2 | S2 | S2 | S2 | S2 | S2 |
| Error state | P | S2 | S2 | S2 | S2 | S2 | S2 | S2 |
| Destructive action safety | — | — | — | — | — | — | S2 | S2 |
| Accessibility critical checks | P | P | P | P | P | P | P | P |

Notes: the navigation contract is Stage 3 work (NAVIGATION_CONTRACTS; GAP-035), and the prototypes' links lead nowhere. P3–P8 are Indonesian only, as DIR-054 §17 asks; their language switch is the shell's account menu, shown open in P2. Surface P7 exists only on desktop by DIR-054 §17; its phone form is D-DS-07's line sheets. "Accessibility critical checks" covers what Stage 1 can show — the measured contrast above, target sizes, labelled fields and landmarks, the focus ring, `aria-current` and status never by colour alone; keyboard and screen-reader checks belong to Stage 2 and the Stage 4 gate.

## Prototypes

Twenty-eight pages of the eight key surfaces (DIR-054 §17), authored in the DesainPakeAI project MULTIPLECORP from the temporary directory outside the repository. Every ID carries the prefix `p7r-` and was checked free before the first page was created; every page is page-scoped (`<!-- dpai:page {"scoped":true} -->`) and changes no design asset of the project. Desktop pages are 1,440 × 900 and phone pages 390 × 844. The pages load Inter from a font CDN, as the tool's authoring guide allows for prototypes; the product self-hosts its font (SECURITY WS-08).

| Surface | Desktop | Phone | Content |
| --- | --- | --- | --- |
| P1 Sign-in | `p7r-p1-masuk-d`, `p7r-p1-masuk-d-en`, `p7r-p1-kode-d`, `p7r-p1-kode-d-en` | `p7r-p1-masuk-p`, `p7r-p1-masuk-p-en`, `p7r-p1-kode-p`, `p7r-p1-kode-p-en` | Password and "Lanjutkan dengan Google", the language switch, the generic error on submit; the second-factor screen after a password and, as a state of the same page, after Google; Indonesian and English |
| P2 Application shell | `p7r-p2-shell-d`, `p7r-p2-shell-d-en` | `p7r-p2-shell-p`, `p7r-p2-shell-p-en`, `p7r-p2-menu-p`, `p7r-p2-menu-p-en` | The Owner's sidebar, top bar and account menu with the display language, on Gudang › Stok with its tabs, the four quantities and their formula, and the pending line (QS-22); the phone's Gudang task list and the *Lainnya* sheet; Indonesian and English |
| P3 Owner's Beranda | `p7r-p3-owner-d` (*Gabungan*), `p7r-p3-owner-arj-d` (ARJ) | `p7r-p3-owner-p`, `p7r-p3-owner-arj-p` | The view switch, four figures, *Perlu perhatian Anda*, the two charts with their tables, *Antrean pekerjaan* |
| P4 Admin's Beranda | `p7r-p4-admin-d` | `p7r-p4-admin-p`; `p7r-p4-gudang-p` (warehouse persona) | The action queue of an Admin with ARJ and BTN; the warehouse worklists |
| P5 Project list | `p7r-p5-proyek-d` | `p7r-p5-proyek-p` | The Proyek tabs, search and filters with the company as a view filter, the list with company code, client, channel, deadline, status and confirmed value |
| P6 Project detail | `p7r-p6-detail-d` | `p7r-p6-detail-p` | The facts line, the primary action and *Tindakan Lain*, the next-step band, eight tabs, *Ringkasan* with "Kemajuan proyek", "Syarat penyelesaian", "Nilai proyek" and "Info proyek"; the phone's drill-in list |
| P7 Multi-line form | `p7r-p7-pembelian-d` | — (desktop only, DIR-054 §17) | *Buat Pembelian* for a project: the company from the project, the supplier, the lines in the dense editor with their project items, the charges with treatment and allocation, an estimated summary, the transaction date, "Batal" and "Simpan Pembelian" |
| P8 Phone warehouse task | — | `p7r-p8-masuk-p`, `p7r-p8-baris-p` | *Barang Masuk*: the purchase's lines as a checklist, the scan field fixed at the bottom with "Cari Produk"; one line's sheet with *Layak pakai*, *Rusak diterima* and *Ditolak*, the rack, the supplier's delivery number, photo evidence and the project reservation in the same command |

**Synthetic data and H7.** The companies are the fictitious CV Arunika Jaya (ARJ), PT Bentara Niaga (BTN) and CV Cakra Persada (CKP); clients, suppliers and products are fictitious and named with "Contoh" where they could resemble a real organization; the personas are Laras (Owner), Dimas (Admin Operasional with ARJ and BTN and `finance.view`) and Bayu (Admin Operasional with ARJ warehouse grants). No real person, client or credential appears. One supplier first named without "Contoh" was renamed on five pages in session P7R-S09 so that no name resembles a real shop. The authentication messages are AU-04's generic ones; a phone title is the screen name, never a business number or name (H7-10); page IDs and routes carry no business data; no surface shows another company's record, so the relation marker (PJ-04) does not arise here — it belongs to SCR-19 and SCR-22 in Stage 2. The counts of P3 and P4 are separate examples, not one consistent data set; Stage 2's end-to-end prototypes use one.

**Verification.** `dpai preview verify` passed for all 28 pages — compile passed, 0 diagnostics, 0 warnings and 0 layout issues. The tool reports its browser, layout and responsive checks as not run, so they are *Not verified* by the tool; the executor captured every final page locally with the installed headless Chrome — a fold view, and a full-length view where the page is longer — at 1,440 px and, through a 390 px frame, for the phone pages, and inspected them. A read-back of all 28 sources (`dpai file read --full`) is byte-identical to the local build. The working state of every page was closed with `dpai work finish`.

**Sessions.** Non-secret commands only, run from the temporary directory; times in WIB on 2026-10-04.

| Session | Time | Commands | Result |
| --- | --- | --- | --- |
| P7R-S01 | 15:44 | `dpai --version`; `dpai auth status --pretty`; `dpai project current --pretty`; `dpai context --pretty` | Readiness as recorded under [DesainPakeAI readiness](#desainpakeai-readiness) |
| P7R-S02 | 16:09–16:12 | `dpai design context --detail full`; `dpai token list --format css` and `--format json`; `dpai file list`; `dpai file read` of the nine old page sources and `src/styles/tokens.css` | The reference and the old baseline read; nothing written |
| P7R-S03 | 16:09 | `dpai design lint` | The reference: 0 errors, 18 warnings, 1 info ([DESIGN_REFERENCES §8](../DESIGN_REFERENCES.md#8-lint-findings-and-dispositions)) |
| P7R-S04 | 16:16 | `dpai preview verify` of the three `dpb18` pages together, then `dpai preview verify --page dpb18-a` (and `-b`, `-c`) | Together: CLI_OPERATION_FAILED (HTTP 500); one page at a time: compile passed, no diagnostic |
| P7R-S05 | 16:43 | `dpai context --pretty` | No page ID with the prefix `p7r-` existed |
| P7R-S06 | 16:43–16:44 | `dpai page create --id p7r-p1-masuk-d … --width 1440 --height 900`; `dpai file write`; `dpai call write_file` with the expected revision and overwrite; `dpai preview verify --page p7r-p1-masuk-d` | Created; `file write` refused with FILE_ALREADY_EXISTS for the created page, so the documented `write_file` operation with overwrite was used, as the tool advised; written; compile passed |
| P7R-S07 | 16:45–16:56 | For each of the other 27 pages: `dpai page create`, `dpai call write_file`, `dpai preview verify --page` (one retry on failure) | 27 created and written; 26 passed at once; `p7r-p4-gudang-p` returned no result twice and passed on its own run at 16:56; a repeated write of `p7r-p1-masuk-d` returned no confirmation and left its verified content unchanged |
| P7R-S08 | 16:57–17:01 | `dpai work finish --page` for the 28 pages; `dpai file read --full` of the 28 sources; `dpai project current`; `dpai context`; `dpai design lint`; `dpai token list`; `dpai design context --detail full`; `dpai file list`; `dpai file read` of the nine old pages and `src/styles/tokens.css` | Working state cleared; read-back identical; lint unchanged; assets and old pages unchanged |
| P7R-S09 | 17:21–17:24 | `dpai call write_file` and `dpai preview verify --page` for `p7r-p4-admin-d`, `p7r-p4-admin-p`, `p7r-p4-gudang-p`, `p7r-p8-masuk-p` and `p7r-p8-baris-p` (the supplier renamed); `dpai work finish`; the read-back and the checks of P7R-S08 again | Compile passed with 0 diagnostics for all five; the 28 sources identical to the build; the results below |

After the sessions `git status` showed no path written by the tool; the only untracked path was this stage's own DESIGN_REFERENCES.md.

**After authoring (P7R-S09, 17:24).** The active project was still MULTIPLECORP (role owner); the context revision is `sha256-c0ea150c276c0e29` with 82 pages — the 54 earlier ones and the 28 `p7r-` pages — and none in a working state. `dpai design lint` gives 0 errors, 18 warnings and 1 info, the same findings as before authoring, because the pages change no design asset. The reference is untouched: its tokens (CSS and JSON) and its full design context are identical to the copies read in P7R-S02, apart from the project revision and the redacted path field. The old pages are untouched: the nine sources read before authoring and `src/styles/tokens.css` are byte-identical, and no write command addressed any old page. The project's file list gained the 28 `p7r-` page sources and the tool's own working-state file `.prototype/agent-state.json`, now empty; page registration changed only through `dpai page create`, as the tool documents. Nothing of DesainPakeAI — no page source, export, capture or configuration — is in the repository.

**Viewing.** In the DesainPakeAI project MULTIPLECORP, open the pages by the IDs above. The local captures (48 images and an index page) are in the session's temporary directory outside the repository, folder `scratchpad\p7r\renders-final\`; they are working files, not records, and may not persist — the page IDs are the durable pointers.

## Preliminary non-loss map

Every old screen and every Beranda signal, placed in the IA skeleton of the [Direction proposal](#a-ia-skeleton) (DIR-054 §18). No screen purpose or capability disappears. The full non-loss matrix — journeys, messages, scenarios, H7, HO and UXH, the V1_SCOPE P7 handoff list and DIR-033's coverage — is Stage 3 work.

### Screens SCR-01–SCR-60

| Screen | Old area | Location in the skeleton | Stage 2 |
| --- | --- | --- | --- |
| SCR-01 Masuk | Global | The sign-in page (P1), with the second-factor screen | — |
| SCR-02 Atur Kata Sandi | Global | The credential-link page outside the shell | DECIDE-IN-STAGE-2: the enrolment of the second factor in the same journey for a high-risk account (H7-15; GAP-044) |
| SCR-03 Akun Saya | Global | The account menu › *Akun Saya*; on the phone *Lainnya* › Akun | DECIDE-IN-STAGE-2: AU-13's self-service grows with TOTP, recovery codes and the Google link (H7-15, H7-16, H7-18) |
| SCR-04 Halaman Sistem | Global | Unchanged: not found, forbidden and error (AZ-11; IP-21) | — |
| SCR-05 Beranda (Owner) | Beranda | Beranda, the Owner's composition (P3) | — |
| SCR-06 Beranda — Antrean Tindakan | Beranda | Beranda, the Admin's composition (P4) | — |
| SCR-07 Tinjauan Owner | Khusus Owner | Tinjauan & Aktivitas › Tinjauan | — |
| SCR-08 Cari | Global | The top-bar search; the phone's *Cari* | — |
| SCR-09 Daftar Proyek | Proyek | Proyek › Semua Proyek (P5) | — |
| SCR-10 Detail Proyek | Proyek | The project page with eight tabs (P6) | — |
| SCR-11 Formulir Proyek | Proyek | "Buat Proyek" on Proyek; "Ubah" in the project's *Tindakan Lain* | — |
| SCR-12 Daftar Penawaran | Proyek | Proyek › Penawaran | — |
| SCR-13 Penawaran | Proyek | From Proyek › Penawaran and the project's *Penawaran* tab; dense editor | — |
| SCR-14 Penyelesaian Proyek | Proyek | From the project's *Ringkasan* ("Syarat penyelesaian", P6) and the QS-15 rows | — |
| SCR-15 Daftar Pembelian | Pembelian | Pembelian | — |
| SCR-16 Detail Pembelian | Pembelian | The purchase page; "Terima Barang" leads to Gudang › Barang Masuk | — |
| SCR-17 Formulir Pembelian | Pembelian | "Buat Pembelian" on Pembelian, the project's *Pengadaan* tab, QS-02 and QS-17 (P7) | — |
| SCR-18 Stok | Gudang | Gudang › Stok (P2) | — |
| SCR-19 Detail Stok Produk | Gudang | From Gudang › Stok and search | — |
| SCR-20 Riwayat Stok | Gudang | Gudang › *Lainnya* › Riwayat Stok (dense) | — |
| SCR-21 Barang Masuk | Gudang | Gudang › Barang Masuk; the phone's task list (P8) | — |
| SCR-22 Barang Keluar | Gudang | Gudang › Barang Keluar; the phone's task list | — |
| SCR-23 Reservasi | Gudang | Gudang › Reservasi; the project's *Item & Pemenuhan* tab | — |
| SCR-24 Stok Opname | Gudang | Gudang › Stok Opname | — |
| SCR-25 Penyesuaian & Kondisi | Gudang | Gudang › *Lainnya* | — |
| SCR-26 Retur ke Pemasok | Gudang | Gudang › *Lainnya*; from the purchase page | — |
| SCR-27 Selisih & Temuan | Gudang | Gudang › *Lainnya* — for the Owner, and for an Admin with `stock.resolve_unattributed_evidence`; the Owner's attention rows (QS-18, QS-22) | — |
| SCR-28 Pengiriman | Pengiriman | Proyek › Pengiriman; the project's *Pengiriman* tab | — |
| SCR-29 Catat Pengiriman | Pengiriman | From Proyek › Pengiriman and the project's *Pengiriman* tab | — |
| SCR-30 Kirim Langsung | Pengiriman | From Proyek › Pengiriman and the project's *Pengiriman* tab | — |
| SCR-31 Serah Terima Jasa | Pengiriman | From Proyek › Pengiriman and the project's *Pengiriman* tab | — |
| SCR-32 Retur dari Klien | Pengiriman | From the project's *Pengiriman* tab | DECIDE-IN-STAGE-2: its cross-project entry — Proyek › Pengiriman or Kasus Koreksi, since a return opens a case |
| SCR-33 Kasus Koreksi | Kasus Koreksi | Proyek › Kasus Koreksi, as prototyped | DECIDE-IN-STAGE-2: cases also arise from purchases and stock (SCR-26), so the home may be Proyek or another area |
| SCR-34 Dokumen | Dokumen | Dokumen › Dokumen; the project's *Dokumen* tab | — |
| SCR-35 Detail Dokumen | Dokumen | The document page | — |
| SCR-36 Bukti | Dokumen | Dokumen › Bukti | — |
| SCR-37 Dokumen Administrasi | Dokumen | Proyek › Dokumen Administrasi (cross-project); the checklist in the project's *Dokumen* tab | — |
| SCR-38 Daftar Invoice | Keuangan | Keuangan › Invoice | — |
| SCR-39 Detail Invoice | Keuangan | The invoice page | — |
| SCR-40 Piutang | Keuangan | Keuangan › Piutang | — |
| SCR-41 Pembayaran | Keuangan | Keuangan › Pembayaran | — |
| SCR-42 Catat Pembayaran | Keuangan | "Catat Pembayaran" on Keuangan › Pembayaran; from the invoice and the project's *Keuangan* tab | — |
| SCR-43 Potongan | Keuangan | Keuangan › Potongan | — |
| SCR-44 Kredit Pelanggan | Keuangan | Keuangan › *Lainnya* | — |
| SCR-45 Pengeluaran & Kas Lain | Keuangan | Keuangan › *Lainnya* | — |
| SCR-46 Mutasi Bank | Keuangan | Keuangan › *Lainnya* (dense) | — |
| SCR-47 Laporan | Laporan | Laporan › Laporan (dense) | — |
| SCR-48 Laporan Gabungan | Laporan | Laporan › Laporan Gabungan, Owner only (dense) | — |
| SCR-49 Ekspor Saya | Laporan | Laporan › Ekspor Saya | — |
| SCR-50 Klien | Data Master | Data Master › Klien | — |
| SCR-51 Pemasok | Data Master | Data Master › Pemasok | — |
| SCR-52 Produk & Jasa | Data Master | Data Master › Produk & Jasa | — |
| SCR-53 Lokasi Rak | Data Master | Data Master › Lokasi Rak | — |
| SCR-54 Perusahaan | Pengaturan | Pengaturan › Perusahaan | — |
| SCR-55 Penomoran Dokumen | Pengaturan | Pengaturan › Penomoran Dokumen | — |
| SCR-56 Pajak | Pengaturan | Pengaturan › Pajak | — |
| SCR-57 Pengguna & Akses | Pengaturan | Pengaturan › Pengguna & Akses | DECIDE-IN-STAGE-2: the Owner's account screens of H7-17 — TOTP status and reset, the Google unlink and its history (GAP-044) |
| SCR-58 Impor Data Awal | Data Master | Data Master › Impor Data Awal | — |
| SCR-59 Riwayat Aktivitas | Khusus Owner | Tinjauan & Aktivitas › Riwayat Aktivitas (dense) | — |
| SCR-60 Log Keamanan | Khusus Owner | Tinjauan & Aktivitas › Log Keamanan (dense) | — |

All 60 screens are placed; five carry a DECIDE-IN-STAGE-2 note — two about a location (SCR-32, SCR-33) and three about content the authentication amendment adds (SCR-02, SCR-03, SCR-57).

### Signals QS-01–QS-22

The Owner's groups are those of surface P3 — *Perlu perhatian Anda* and the panels Operasional, Penagihan & kas and Penyelesaian proyek, which carry the Owner's signal groups G1–G4 of INFORMATION_ARCHITECTURE §9 in that order; the Admin's are those of surface P4 — *Gudang & proyek*, *Dokumen & kasus*, *Penagihan & kas* (with `finance.view`) and *Penyelesaian proyek*, which carry the Admin's groups G1–G4 of the same section in that order. Each count is per company (CS-08).

| QS | Signal | Owner | Admin | Also |
| --- | --- | --- | --- | --- |
| QS-01 | Warehouse demand not yet reserved | Operasional | Gudang & proyek › Gudang | The project's *Item & Pemenuhan* tab |
| QS-02 | Shortage, purchase needed | Operasional | Gudang & proyek › Proyek | "Buat Pembelian" prefilled |
| QS-03 | Received for a project, awaiting reservation | Operasional | Gudang & proyek › Gudang | Gudang › Reservasi |
| QS-04 | Reservations cut | Operasional | Gudang & proyek › Proyek | The project's *Item & Pemenuhan* tab |
| QS-05 | Open purchase remainders | Operasional | Gudang & proyek › Gudang, its worklist open | The warehouse Beranda; Gudang › Barang Masuk; the phone task list |
| QS-06 | Dispatched or shipped, not delivered | Operasional | Gudang & proyek › Gudang | Proyek › Pengiriman |
| QS-07 | Discrepancies and correction cases | Operasional | Dokumen & kasus | Kasus Koreksi |
| QS-08 | Rendition pending or failed | Operasional | Dokumen & kasus | The document page |
| QS-09 | Invoices *Belum Ditagihkan* | Penagihan & kas | Penagihan & kas | Keuangan › Piutang |
| QS-10 | Overdue receivables by ageing bucket | Penagihan & kas | Penagihan & kas | The chart "Umur piutang" and the overdue part of *Piutang aktif* (Owner) |
| QS-11 | Disputed receivables | Penagihan & kas | Penagihan & kas | Keuangan › Piutang |
| QS-12 | Unapplied credit, payments without proof, uncited bank lines | Penagihan & kas | Penagihan & kas | Credit linked to a written-off invoice also in Tinjauan (QS-14) |
| QS-13 | Deductions awaiting evidence | Penagihan & kas | Penagihan & kas | Keuangan › Potongan |
| QS-14 | The Owner review queue | Perlu perhatian Anda ("Aktivitas baru untuk ditinjau") | Absent — no entry, count or badge for an Admin | Tinjauan & Aktivitas |
| QS-15 | Completion blockers; invoicing above delivered value; confirmed value not invoiced | Penyelesaian proyek | Penyelesaian proyek (money items with `finance.view`) | The project's "Syarat penyelesaian" (P6) |
| QS-16 | Residual obligations of force-completed or cancelled projects | Perlu perhatian Anda | Gudang & proyek › Proyek | The project page |
| QS-17 | Low stock and restock advice | Operasional | Gudang & proyek › Gudang | Gudang › Stok, "Hanya di bawah minimum"; the warehouse Beranda |
| QS-18 | Count findings, stale counts, unattributed surplus | Attribution: Perlu perhatian Anda; findings and stale counts: Operasional | Gudang & proyek › Gudang (findings, stale counts) | Gudang › Stok Opname; Selisih & Temuan |
| QS-19 | Pre-payment Kuitansi not linked or voided | Penagihan & kas | Penagihan & kas | The Kuitansi's document page |
| QS-20 | Routed refusals | Perlu perhatian Anda | Gudang & proyek › Proyek | The refused command's record |
| QS-21 | Duplicate warnings overridden or confirmed | Operasional | Dokumen & kasus | Each also in QS-14 |
| QS-22 | Pending unexplained-loss cases | Perlu perhatian Anda ("Selisih menunggu penetapan") | Not in the Admin queue | The pending line on Gudang › Stok (P2) and the product page, blocking nothing (DIR-027) |

All 22 signals are placed, with the old group of each kept.

## Checkpoint C1 package

**Rejected at Checkpoint C1 for focused rework — DIR-055.** See [Checkpoint C1 — Owner verdict](#checkpoint-c1--owner-verdict).

The English equivalent of the package the final report gives the Owner in Bahasa Indonesia (DIR-054 §19). No question found in Stage 1 would change P0–P6 semantics, so nothing is OWNER_DECISION_REQUIRED.

**1. The most important diagnosis findings**

1. **Navigation follows modules, not tasks.** The Owner's sidebar has fourteen top-level entries and up to 23 rows with a group open; Gudang alone has nine (H-2; INFORMATION_ARCHITECTURE §5, §6.1; `dpb18-a`).
2. **The project detail repeats itself.** The next step, a strip of part states and eleven tabs show the same parts three times before any part is opened (H-3; D-IA-09; `dpb18-b`).
3. **The Owner's Beranda is hard to scan.** Up to 47 rows of equal weight, no chart, and the period figures start below the fold at 1,440 × 900 (H-4; D-IA-07; D-DS-05; `dpb18-a`).
4. **Type is small and density is compact everywhere.** Text from 12 to 20 px, 32 px controls and 36 px rows on every screen, even where there is little data; one page sets 11 px text (H-1; DESIGN_SYSTEM §5.2, §5.3; `dpb02-a`).
5. **The IA mirrors record families.** *Pengiriman* and *Kasus Koreksi* are top-level areas, and a project's work is split over eleven parts — *Item* apart from *Pemenuhan*, *Penagihan* apart from *Pembayaran*, *Dokumen* apart from *Dokumen Administrasi* (INFORMATION_ARCHITECTURE §5; SCR-10).
6. **The Ramp reference was discarded with its usable core.** It uses lime as a fill under near-black text (16.02:1), never as text; its 19 lint findings are format warnings, unused tokens and one false positive; and it was judged without a page of its own (H-5; D-DS-01; DPB-01).
7. **New users get no orientation.** Page headers say nothing about a page's purpose and empty states are one sentence (PT-03; PT-19).
8. **What is sound stays.** The business, authorization and accessibility rules of the old baseline, its command model and its phone floor patterns are correct and are kept.

**2. The direction as the user will experience it**

- **Navigation.** Ten flat entries for the Owner (eight for an Admin at most): Beranda, Proyek, Pembelian, Gudang, Keuangan, Dokumen, Laporan, Data Master, and under *Khusus Owner* Tinjauan & Aktivitas and Pengaturan. Each area shows its sub-pages as tabs on its page. Deliveries, handovers and client returns live under Proyek. On the phone the bottom bar stays Beranda · Proyek · Gudang · Cari · Lainnya, and an area opens as a list of tasks.
- **Beranda.** The Owner sees four figures first — sales value, Cash-In, active receivables with their overdue part and managerial profit — for all companies or one; then what needs the Owner's attention; then two charts, receivable ageing and sales and cash per company, each with its table; then the work queues in three panels. An Admin sees the action queue in panels, every count per company and never summed, the first worklist opened with its buttons. A warehouse user sees the goods to receive and to dispatch with a "Mulai" button.
- **Project detail.** The project's facts on one line, the main action, one "next step" band and eight tabs; the state of each part appears once, in *Ringkasan*.
- **Typography and spacing.** Body text 15 px on desktop and 16 px on phones, labels at least 13 px, page titles and figures 26 px; controls 40 px (48 px for touch); clear space between groups. Only long tables — line editors, stock history, bank statements, reports and the activity and security logs — use a denser mode.
- **Colour and lime.** Near-black text on white and warm-neutral surfaces. Lime only as a fill under dark text — the main button, the selected menu entry and the attention badge — always with a second cue: a dark olive rail or edge, a heavier weight. Lime is never text. Every text pair measures at least 4.67:1 and every indicator at least 3.21:1.
- **Phone use.** The same tasks, one column, 48 px controls; receiving works as a checklist with the scan field at the bottom and manual product search when no scanner is attached; the reservation for the project is offered in the same receipt.
- **Language switching.** "ID | EN" at the top right of the sign-in page; "Bahasa tampilan: Indonesia | English" in the account menu, and on the phone under *Lainnya*. Indonesian is the default; after sign-in the account's own choice applies; issued documents and business data always stay Indonesian. How the choice is stored is not decided yet.

**3. How to view the prototypes**

- In the DesainPakeAI project MULTIPLECORP, the 28 pages whose IDs start with `p7r-`: P1 `p7r-p1-masuk-d`, `p7r-p1-kode-d` and their phone (`-p`) and English (`-en`) versions; P2 `p7r-p2-shell-d`, `p7r-p2-shell-p`, `p7r-p2-menu-p` and their English versions; P3 `p7r-p3-owner-d`, `p7r-p3-owner-arj-d`, `p7r-p3-owner-p`, `p7r-p3-owner-arj-p`; P4 `p7r-p4-admin-d`, `p7r-p4-admin-p`, `p7r-p4-gudang-p`; P5 `p7r-p5-proyek-d`, `p7r-p5-proyek-p`; P6 `p7r-p6-detail-d`, `p7r-p6-detail-p`; P7 `p7r-p7-pembelian-d`; P8 `p7r-p8-masuk-p`, `p7r-p8-baris-p`.
- Local captures of all 28 pages, with an index page, in the session's temporary directory outside the repository, folder `scratchpad\p7r\renders-final\` — working files that may not persist; the page IDs above are the durable pointers.
- All data is fictitious; the pages show "Prototipe C1 · data contoh fiktif".

**4. The alternatives — A recommended on each axis**

1. **Top-level navigation.** A: ten flat entries with tabs on each area page. B: a grouped sidebar of nine entries that open in place, as before. A gives one level to scan; B is more familiar but rebuilds the long list.
2. **Project detail.** A: eight tabs with the part states only in *Ringkasan*. B: an overview page whose parts open as separate pages. A keeps every part one click away; B looks simpler at first but costs a page and a back step per part.
3. **Owner's Beranda.** A: figures first, then attention, charts and queues. B: attention first, with the figures and charts on a separate *Keuangan* tab of Beranda. A answers "where do we stand?" at once and still shows the first attention items without scrolling; B suits an Owner who opens Beranda mainly to act.

**5. What is kept from the old baseline, and why**

The company context — a view filter that never feeds a form, counts per company, *Gabungan* only for the Owner (CS-01–CS-03, CS-08); no counts on navigation entries; one search field for typed and scanned codes; the phone bottom bar; session notices; the *Dokumen Administrasi* checklist; the review queue's two parts; reports per company; the numbering area; master records as their own pages; dispatch and delivery as two records; stock by product with its four quantities; the whole command model — answers only from the server, frozen inputs while an answer is unknown, one automatic retry, the transaction date line, named duplicate confirmations, corrections that state what stays; the desktop multi-line editor; the phone scan field at the bottom; the outcome message beside its buttons; the capability preview; Inter with tabular figures; status never by colour alone; and 37 of the 45 anti-slop rules. They are kept because business, security or accessibility requires them, or because they already work (64 of the 83 earlier decisions and rules are KEEP).

**6. The C1 questions**

1. Do you **approve** the direction, **approve it with named adjustments** (name them — for example the four Beranda figures, the place of *Kasus Koreksi*, or the screens allowed the dense mode), or **reject** it?
2. Axis 1, top-level navigation: **A** or **B**?
3. Axis 2, project detail: **A** or **B**?
4. Axis 3, the Owner's Beranda: **A** or **B**?
5. Is a suitable warehouse or field user — someone who receives or dispatches goods with a phone — reasonably available for short usability sessions in Stage 2? If not, Stage 2 proceeds with the Owner and the Admin Operasional validator only (K2).

**7. After C1**

Stage 2 — the complete IA, the design system, the localization architecture under C-1, the authentication journeys, the end-to-end prototypes and the usability kit — needs a separate Owner authorization; nothing of it starts on its own. The re-baseline continues on the branch `rebaseline/p7-ux`; `main` and its handoff predate the re-baseline until Stage 5. The Owner's C1 answers are recorded as a decision when they are given.

## Zero-context check

Run on 2026-10-04 (DIR-054 §20.2) by a fresh, read-only sub-agent that started at AGENTS.md and read only through its reading order and the documents those point to. It was told not to open source record 51 or anything of this session, and was given the DIR-046 §2 hard rule in its own words; it used only file reads and read-only Git commands. Its verbatim report is kept outside the repository: 27,102 bytes, SHA-256 `186549806B140F24D2A6A57D2F3B279319B9B9BD5D5CE93D3BE79C74DB748DA3`. It ran on the working tree before commit 2, so it also saw what was still missing then — commit 2, this section, Gate G1 and the Stage 1 hash table.

| Question | The check's answer | Result |
| --- | --- | --- |
| (a) The current phase and the state of the re-baseline | P7 IN_PROGRESS; the re-baseline authorized under DIR-054 for Stages 0–1 only; Stage 0 committed and its branch push verified; Stage 1 recorded as done, with Checkpoint C1 pending the Owner's review and its questions named | Correct |
| (b) K1–K4, C-1 and C-2 | Each decision as DIR-053 defines it, with source record 50 and DIR-054 §2 as its sources; the other K1–K4 (DIR-042), C1 and C2 (DIR-038), the checkpoints and the C- constraints told apart | Correct |
| (c) What is authorized next and what is not | STOP at Checkpoint C1 after the push of commit 2; Stage 2 only under a separate authorization; the whole "Do not do" list, APPR-011 reserved | Correct |
| (d) `main` and the re-baseline work | `main` at `a526daf` locally and on the live remote; `rebaseline/p7-ux` at commit 1, also live; commit 2 resolved by its `git log --grep` command and its push not claimed; `git ls-remote origin` before acting | Correct |
| (e) Where the Stage 1 results are, and their status | The diagnosis and the direction proposal in this record, DESIGN_REFERENCES, the `p7r-` pages in DesainPakeAI — all non-normative, the dispositions provisional | Correct |
| (f) The old P7 documents in force | INFORMATION_ARCHITECTURE and DESIGN_SYSTEM APPROVED (APPR-008) and under replacement; ADMIN_FLOW APPROVED and in force, its login journeys deferred under GAP-044 | Correct |

Its 21 navigation findings and their handling:

| Finding | Handling |
| --- | --- |
| 1–4. Commit 2, this section, Gate G1 and the Stage 1 hash table did not exist yet, so `#gate-g1` was broken and a placeholder stood in the log | Expected before commit 2: the records are written for the state they are committed in. Resolved by this section, [Gate G1](#gate-g1), the hash table and commit 2 |
| 5. `main`, commit 1 and the working tree show three states, and the reading order names no branch | Out of scope: `main` is not changed before Stage 5 (DIR-054 §23). CURRENT_HANDOFF and the C1 package name the branch `rebaseline/p7-ux`; reported |
| 6. The resume rules of DIR-054 §25 were named but not restated | Fixed: TECH-027's Stage 1 record restates them |
| 7. "C-1 a", "C-1 d" and "§16 d" had no readable definition | Fixed: replaced by words in this record and in GAP-037 |
| 8. Source record 50 holds only "K1: A" and "K3: benar." | By design: the blueprint is not archived (DIR-053); DIR-054 §2 states the decisions, as DECISION_LOG and DECISION_INDEX summarize them; reported |
| 9. K references without their decision | Fixed in DECISION_INDEX ("K1–K4 of DIR-042") and CURRENT_HANDOFF ("DIR-053 K2"); EXECUTION_CONTEXT's "(DIR-037 D6; K2)" lies outside the files Stage 1 may change — reported; the identifier map tells the sets apart |
| 10. Two F1–F6 sets, one in the identifier map | Fixed: CONTEXT_INDEX's F1–F6 row names both sets; CURRENT_HANDOFF cites "DIR-051 F6" and this record "DIR-040 F6" |
| 11. The surface labels P1–P8 read like phases | Fixed: the direction proposal says that P1–P8 name the prototyped surfaces; "surface P7" where it matters |
| 12. "G1" named both a Beranda signal group and the gate | Fixed: the signal groups are named as the groups of INFORMATION_ARCHITECTURE §9 |
| 13. C-1 and C-2 are close to C1, C2 and the constraints C-01… | Fixed: the identifier-map row also excludes DATABASE's constraints |
| 14. No context-index row for the current authorization | Fixed: CONTEXT_INDEX gains a row for the re-baseline's decisions and authorization |
| 15. CONTEXT_INDEX's approval chain ends at APPR-009 | Reported, unchanged — outside DIR-054 §§8 and 13, as Stage 0 reported |
| 16. The captures lie in a session's temporary folder | Fixed: marked as working files that may not persist, the page IDs as the durable pointers; DIR-054 §19 asks for the path, so it stays |
| 17. CURRENT_HANDOFF said the Stage 1 tool use was recorded in TOOLCHAIN | Fixed: TOOLCHAIN holds the Stage 0 readiness, this record the Stage 1 sessions and the documentation verification. TOOLCHAIN's "Current documentation (§5.1)" row still reads "Not yet verified" — outside the files Stage 1 may change; reported |
| 18. "The active design reference" of AGENTS.md is not designated | Clarified in DESIGN_REFERENCES: it is not that reference until approved; AGENTS.md lies outside the files Stage 1 may change — reported |
| 19. ADMIN_FLOW's J-ACC-01 states the superseded password rule | Known and recorded (GAP-044; DIR-051 F6); ADMIN_FLOW is not edited in Stage 1 — reported |
| 20. The anchor `auth-profile--decided-security-amendment-pending` reads as stale | Kept deliberately (TECH-027, Stage 0) — reported |
| 21. DESIGN_REFERENCES gave 1,200 px and 1,240 px for overview pages | Fixed: 1,240 px including the page padding, about 1,180 px of content, in §4 and §7 |

**Result: PASS** — all six answers are correct; the navigation findings within scope are fixed at Level 1, and the rest are reported.

## Gate G1

Run on the complete staged change of commit 2 before the commit (DIR-054 §20.3), with scripts kept outside the repository. Every item passed.

| Item | Check | Result | Evidence |
| --- | --- | --- | --- |
| G1-01 | The staged diff touches only the listed files | PASS | `git diff --cached --name-status` against commit 1 lists ten paths: DESIGN_REFERENCES (added), this record, SOURCE_OF_TRUTH, CONTEXT_INDEX, DECISION_LOG, DECISION_INDEX, GAP_REGISTER — only the GAP-037 continuation line that §20.1 needs —, PHASE_STATUS, CURRENT_HANDOFF and CHANGELOG |
| G1-02 | The diagnosis is complete and evidenced | PASS | [Diagnosis](#diagnosis): the eleven questions of source record 31 answered, D3's 23 qualities assessed, H-1 to H-5 dispositioned CONFIRMED with evidence; every finding cites a section, decision, page or measured value; nothing is attributed to the Owner beyond records 31, 32 and 50 |
| G1-03 | The reference record is complete | PASS | [DESIGN_REFERENCES](../DESIGN_REFERENCES.md): the source, the components, the principles, the not-copied list with reasons — brand, screenshots, chat composer, lime as text, display sizes and the accessibility conflicts —, the shadcn/ui mapping, draft tokens, responsive rules and the lint findings with dispositions, before and after authoring |
| G1-04 | The persona task analysis is complete with sources | PASS | [Persona task analysis](#persona-task-analysis): the Owner, the Admin at the desk and the warehouse/field user, each with frequent and critical tasks, what comes first and what must be quick, and sources; no task outside V1 |
| G1-05 | All 83 dispositions, with reasons and sources | PASS | [Provisional dispositions](#provisional-dispositions): 17 + 9 + 12 + 45 rows, counted from the tables — KEEP 64, ADAPT 12, SUPERSEDE 6, DECIDE-IN-STAGE-2 1; each with its reason, and every kept item with its constraint — a business, authorization, security, performance or accessibility rule (CS, AZ, PJ, H7, HO, PF, NM, GL, WCAG and others) or the D4 protection it serves |
| G1-06 | The direction proposal is complete | PASS | [Direction proposal](#direction-proposal): every text pair at least 4.5:1 (lowest 4.67:1) and every indicator at least 3:1 (lowest 3.21:1), against the verified WCAG 2.2 thresholds; body 15 and 16 px, headings and figures to 26 px above the old 20 px cap, desktop controls 40 px above the old 32 px, and a density policy that names its screens; every figure cites CALC-01, CALC-05, CALC-07, CALC-08, CALC-11–CALC-14, PT-24 or a QS signal, and the rest is listed as needing definition; the language UX follows C-1 with no storage decision |
| G1-07 | All eight surfaces prototyped and verified, data synthetic, nothing touched, no output in the repository | PASS | [Prototypes](#prototypes): 28 `p7r-` pages, `preview verify` passed for each; the Ramp tokens and design context and nine old page sources identical, the lint unchanged; the staged diff holds no page source, export, capture or image |
| G1-08 | The non-loss map covers 60 screens and 22 signals | PASS | [Preliminary non-loss map](#preliminary-non-loss-map): SCR-01–SCR-60 and QS-01–QS-22, each placed; five DECIDE-IN-STAGE-2 notes, each with its reason |
| G1-09 | No normative P7 document or P0–P6 specification changed; no schema change proposed | PASS | INFORMATION_ARCHITECTURE, DESIGN_SYSTEM, ADMIN_FLOW and every P0–P6 specification are byte-identical to commit 1; beyond the records of G1-01 nothing changed; the C-1 storage finding is an observation in GAP-037 and the direction, not a proposal |
| G1-10 | The documentation verification is recorded | PASS | [Official documentation verification](#official-documentation-verification): addresses, versions, the access date and the limitations |
| G1-11 | Links, identifiers, secrets | PASS | 1,883 relative links and anchors checked in every Markdown file outside `docs/00-governance/sources/`, 0 broken; Stage 1 defines no DIR, OBS, TECH, APPR, GAP or RISK identifier — APPR-011, GAP-052 and RISK-010 stay unused —, and its session and gate labels are defined once in this record; a scan of the staged diff finds no key, token, credential path, e-mail address, binary, image or unfilled placeholder |
| G1-12 | The zero-context check passes | PASS | [Zero-context check](#zero-context-check) |
| G1-13 | The C1 package is complete | PASS | [Checkpoint C1 package](#checkpoint-c1-package): its seven parts |

## Checkpoint C1 — Owner verdict

Recorded from the fifty-second and fifty-third source records ([DIR-055 and DIR-056](../../00-governance/DECISION_LOG.md#dir-055-dir-056-obs-020-and-tech-028--p7-ux-re-baseline-checkpoint-c1-decision-rework-authorization-baseline-and-rework)), received on 2026-10-04 with the branch at commit 2.

**Verdict.** The Stage 1 direction is not approved: it is **rejected for focused rework**. The direction consists of this record's [Direction proposal](#direction-proposal) and [Checkpoint C1 package](#checkpoint-c1-package), [DESIGN_REFERENCES](../DESIGN_REFERENCES.md) §§3 and 5–7 as drafted, and the 28 `p7r-` pages. A technically passing [Gate G1](#gate-g1) is not design approval, and Stage 2 stays unauthorized. The valid Stage 0–1 research, evidence and useful prototype foundations are preserved. The rejected direction and its A/B alternatives stay in this record as history, marked REJECTED AT C1 (DIR-055), and are not deleted; the `p7r-` pages stay untouched as their record.

**The Owner's decisions OC-01–OC-12 (DIR-055)**, binding within their subjects:

| Decision | What it requires | What it replaces in the Stage 1 direction |
| --- | --- | --- |
| OC-01 Real administrative process | The client's staff create many connected documents manually in Excel, and MultipleCorp does not merely turn them into separate digital modules. After sign-in an Admin understands at once what needs attention, which project it belongs to, what is done, what blocks it, the next valid action, which document or operational step becomes ready next and what remains before the project is truly complete — never having to remember which module or menu to open next | The Admin's Beranda as an action queue grouped by kind of work, with counts per signal (D-IA-08 ADAPT; surface P4), which does not tie an item to its project's progress, blocker and next step |
| OC-02 Navigation — direction C | A workflow/task-first primary experience; module navigation only as reduced, secondary navigation for browsing, reporting or direct access, never the primary mental model | Ten flat area entries for the Owner and up to eight for an Admin, with each area's sub-pages as tabs (axis 1 A; D-IA-01 ADAPT), and its alternative B |
| OC-03 Project detail — direction C | A guided project journey / progress workspace with contextual drill-down whose primary experience answers "What happens next on this project?" — overall progress, completed stages, current work, blockers or missing prerequisites, the next recommended valid action, document readiness, the relevant financial/payment state and what remains before completion; secondary tabs or drill-down may exist; no stage forced onto a project where it does not apply | The facts line, one next-step band and eight equal tabs with the part states in *Ringkasan* (axis 2 A; D-IA-09 SUPERSEDE), and its alternative B, every part as a separate page |
| OC-04 Connected documents | No re-entry of data that P0–P6 already provide across quotation, procurement, receiving, delivery, administrative documents, invoicing, payment and completion; a document-readiness/checklist experience derived from the approved document lifecycle — completed, ready to prepare, waiting for prerequisite, finalized, revision/void where applicable; no business automation beyond P0–P6 | Documents as an area of their own and as one of the eight project tabs, merged with *Dokumen Administrasi*, without a derived view of what is complete, ready, waiting or final across the project |
| OC-05 Owner and Admin experiences; Owner Beranda — direction C | Admin: work queues, next actions and workflow guidance. Owner: decisions requiring the Owner, exceptions, risks and overdue work, project progress needing attention, compact business-health and financial KPIs, drill-down only when needed. The Owner's Beranda as a hybrid: decisions and exceptions with strong priority, key KPIs visible and easy to scan in the same experience, normal Admin operational work never competing visually | The Owner's Beranda with the four figures first, then attention, two charts and the work queues in three panels (axis 3 A; D-IA-07 SUPERSEDE), and its alternative B with the figures on a separate tab |
| OC-06 Less explanatory text | Short headings, a clear state or number, an obvious action, one short supporting sentence only when it materially helps, progressive disclosure for secondary explanation; every helper text passes "Does the user need this text to decide or act right now?" or is reduced, moved behind contextual detail or removed — on dashboards, project detail, forms, authentication screens and phone warehouse flows | The explanatory lines the Stage 1 surfaces add beyond mandated copy — purpose lines on task rows, supporting lines under headings and notes — written in answer to diagnosis finding 7 and SLOP-20's allowance for a page to state its purpose; counted in the C1 rework |
| OC-07 Colour | The bright lime is too visually dominant; Ramp stays a reference for useful hierarchy, spacing and interaction ideas, and its lime is no required MultipleCorp identity; a calmer, professional direction of accessible muted or pastel colours — a warm or neutral canvas, soft neutral surfaces, dark readable text, muted sage, dusty blue, soft amber, muted rose or equivalents; saturated colour limited; lime at most a small accent where it genuinely helps; calm, premium, professional and operationally clear, not colourful for decoration | Lime `#E4F222` as the fill of the primary action, the selected navigation entry, the selected segment and the attention badge, with its olive edges and rail (DESIGN_REFERENCES §§3 and 6; SLOP-09 ADAPT; principle 2 "One accent marks the decision") |
| OC-08 Charts | A neutral baseline series, one meaningful highlight when useful, muted accessible colours, direct labels where practical, no rainbow, colour never the only carrier of meaning, no new metric | The five chart series ink, olive, grey, amber and red of "Umur piutang" and "Penjualan dan kas" ([Direction proposal](#b-visual-direction-and-draft-tokens), b and c) |
| OC-09 Form and layout alignment | Consistent control heights, deliberate column proportions, aligned control baselines, consistent numeric alignment, secondary SKU/item metadata separated from the control baseline, consistent spacing between labels, controls and supporting text, progressive disclosure for less-common fields, dense layouts only when operationally justified — reviewed on every screen | The line row of `p7r-p7-pembelian-d`, whose product/project item, quantity, unit, unit price, tax and subtotal do not read as one deliberate row, the same issue on the other Stage 1 forms, and the list of dense screens, re-checked |
| OC-10 Phone warehouse | The task focus is kept and the text simplified; better progress visibility, the next item requiring action, a scan-first flow, the manual-search fallback and clear completion feedback | The copy and the progress and completion cues of `p7r-p4-gudang-p`, `p7r-p8-masuk-p` and `p7r-p8-baris-p`; the pattern itself is preserved |
| OC-11 The three axes | Not forced into the Stage 1 A/B alternatives: navigation C (OC-02), project detail C (OC-03), Owner Beranda C (OC-05) | The A and B alternatives of the three axes and the recommendation "A on every axis" ([Direction proposal](#f-alternatives) f; C1 package parts 4 and 6) |
| OC-12 Warehouse/field participant | Availability not yet confirmed; it does not block the C1 rework; if Stage 2 is later authorized and no suitable participant is available, the approved K2 fallback applies | Nothing — it answers C1 question 5 as still pending |

**Relation to earlier decisions.** DIR-053 K1, K2, K4, C-1 and C-2 are unchanged. DIR-053 K3 stays ACTIVE: the reference is still not copied literally — no name, logo, wordmark, brand asset or screenshot, no chat composer, lime never as low-contrast text — and OC-07 narrows its role to hierarchy, spacing and interaction ideas and sets the colour direction. DIR-035 D3 allows lime only when used accessibly; it never required lime. D3, D4, D7 and DIR-040 F6 stay the standing intent.

**Planning interpretations PL-1–PL-5** (Level 1, recorded with DIR-056), each changing presentation only:

| ID | Interpretation |
| --- | --- |
| PL-1 Overall progress | The sequence of the project's applicable stages, each with its derived state, the current stage marked; no percentage, weighted progress bar, ring, score or animation, SLOP-44's protection against fake metrics and gamification staying; a plain count of remaining operational items, such as lines left to receive, is an operational fact and allowed |
| PL-2 Non-applicable stages | A stage that does not apply is never shown as pending, missing or blocking; it is omitted from the track and at most named in a collapsed note |
| PL-3 The next recommended valid action | From one deterministic, recorded precedence over the project's derived facts and signals; a recommendation the user starts — nothing runs automatically, and Beranda and the journey run no command; offered as an action only to an account holding its capability, a hint only, the server deciding (AZ-01); otherwise the workspace says what is needed, without naming another company's records |
| PL-4 Admin work | Scoped by the account's grants, never by personal assignment; records never locked to their creator (WORKFLOWS §11); counts per company and never summed for an Admin (CS-08) |
| PL-5 One recommended direction per axis | No new A/B alternatives; a sub-question the rework genuinely cannot settle within P0–P6 and these decisions goes to the C1 package as one precise question |

**What is preserved** (DIR-056 §10). The rework reuses these rather than redoing them, and changes one only where OC-01–OC-12 require it, recording the change in its disposition delta:

- **The Stage 0–1 evidence:** the baseline records; the documentation verification; the diagnosis — H-1 to H-5, the answers to source record 31's list and D3's qualities; the persona task analysis, extended rather than replaced; the preliminary non-loss method with its 60 screens and 22 signals; the 83 provisional dispositions as the starting point.
- **From the Stage 1 direction:** the type scale 13–26 px with the body at 15 px on desktop and 16 px on phones; controls of 40 px with a fine pointer, 48 px with a coarse pointer and 36 px in the dense mode; the 4 px spacing scale; radii of 4, 6 and 8 px with pills for badges; no shadow at rest; one outline icon family; comfortable density by default with a dense mode only on named, justified screens, the list re-checked under OC-09; the contrast method and the WCAG 2.2 thresholds of 1.4.3, 1.4.10, 1.4.11 and 2.5.8; status never by colour alone; the focus ring; Inter with tabular figures; the four Owner KPIs with their CALC definitions (CALC-05, CALC-07, CALC-08, CALC-11–CALC-14) and the "needs definition — not proposed" list; the company context — a view filter that never feeds a form, counts per company, *Gabungan* for the Owner only (CS-01–CS-03, CS-08); no counts on navigation entries (D-IA-06) and one search field for typed and scanned codes (D-IA-04); C-1's language behaviour and the switch placements; the AICWDF §4A.10 sign-in and second-factor screens with AU-04's generic answers; the command model D-UX-01, D-UX-02 and D-UX-04–D-UX-12, D-UX-03 staying DECIDE-IN-STAGE-2; the phone warehouse pattern — the line checklist, the scan field fixed at the bottom, "Cari Produk" when no scanner is attached, the three receiving outcomes and the project reservation inside the same command (AX-04); the desktop multi-line editor (D-DS-07), its layout reworked under OC-09; the not-copied list of DESIGN_REFERENCES §4; the fictitious companies CV Arunika Jaya, PT Bentara Niaga and CV Cakra Persada, the "Contoh" names and the personas Laras, Dimas and Bayu.
- **In DesainPakeAI:** the 28 `p7r-` pages, untouched as the record of the rejected direction.

## C1 rework — readiness

The five read-only commands of DIR-056 §7, run on 2026-10-04 from a temporary directory outside the repository, before any file of the repository was edited. Non-secret evidence only: the key field, which the tool shows only masked, the credential path and the project identifier are not recorded; nothing was installed, upgraded, re-authenticated or reconfigured, the active project was not switched, and no paid plan was used.

| Item | Evidence |
| --- | --- |
| CLI version | 0.2.2 — `dpai --version` |
| Authenticated | yes — `dpai auth status --pretty` |
| Active project | **MULTIPLECORP**, role owner — `dpai project current --pretty`; not switched |
| Context revision | `sha256-c0ea150c276c0e29` — `dpai context --pretty`; the revision [Prototypes](#prototypes) records after Stage 1 authoring |
| Retrieval time | 2026-10-04 19:01 WIB; the context kept outside the repository |
| Ramp design system | found — the context's design summary names "Ramp", version alpha.3, with 18 colour tokens, 8 typography tokens and 8 sections |
| Page count | 82 — the count after Stage 1 authoring; no page in a working state |
| `p7r-` pages | all 28 IDs listed under [Prototypes](#prototypes) present, and no other `p7r-` page |
| Earlier pages | all 54 present — `dpb-probe` and the 53 DPB pages |
| `p7r2-` pages | none |
| Project files | `dpai file list`: 87 entries — the 82 page sources, `DESIGN.md`, `PRODUCT.md`, `prototype.json`, `.prototype/canvas.json` and `src/styles/tokens.css` |
| Setup steps needed | none — no stop |

Observation, recorded and not acted on (DIR-056 §7): the tool's working-state file `.prototype/agent-state.json`, which [Prototypes](#prototypes) lists in the project's files after Stage 1 authoring, is no longer in the file list; the page count and the context revision are unchanged, and nothing was modified to restore it. At `dpai auth status` the executor's local output filter let the tool's masked key field through to the session's own tool output; it was not written to any file or to the repository, and the filter was tightened before the next command. `git status` showed nothing new in the repository after the commands.

## Gate R0

Run on the complete staged change of commit 3 before the commit (DIR-056 §8.2), with scripts kept outside the repository. Every item passed.

| Item | Check | Result | Evidence |
| --- | --- | --- | --- |
| R0-01 | The baseline equals DIR-056 §4 | PASS | Items 1–11 verified read-only before any change and recorded as [OBS-020](../../00-governance/DECISION_LOG.md#dir-055-dir-056-obs-020-and-tech-028--p7-ux-re-baseline-checkpoint-c1-decision-rework-authorization-baseline-and-rework): the live remote and the local branches as stated, commit 2 `2a5c8aa5ee0ae35bd65f0c1432a9caea2981710a` and commit 1 under the Owner's identity without a trailer, a clean tree and index, the 51 source hashes, the commit-2 hashes of TECH-027 with DECISION_LOG's `93AAA781CDD708925AA6FAD60E4C2F22E62CC1A6C11BEB8B367461392DED3718`, the gap totals, the unused identifiers, the absent paths and the unchanged specifications |
| R0-02 | The staged diff touches only the listed files | PASS | `git diff --cached --name-status 2a5c8aa` lists 11 paths — `.gitattributes`, CHANGELOG, the two new source records (added), SOURCE_OF_TRUTH, DECISION_LOG, DECISION_INDEX, this record, DESIGN_REFERENCES — its header note only, by a word-level diff —, PHASE_STATUS and CURRENT_HANDOFF; GAP_REGISTER unchanged, R0 having found no new gap |
| R0-03 | Records 52 and 53 | PASS | Both follow the header convention of records 48–51 — the first line, Received, Subject, for record 52 a Note on the unarchived executor report, Delivery, the transcript sentence, a separator of 68 `=` characters and a blank line before the transcript — with LF bytes, no byte-order mark and a final line break; their lines, bytes and SHA-256 in SOURCE_OF_TRUTH — 253 / 9,001 and 1,686 / 70,614 — equal the staged blobs; the 51 earlier records are byte-identical to `2a5c8aa`; the folder holds 53 source records |
| R0-04 | Readiness evidence | PASS | [C1 rework — readiness](#c1-rework--readiness) records the CLI version, authentication, the active project, the context revision, the retrieval time in WIB, the Ramp design system, the page count, the `p7r-` pages, the absence of `p7r2-` pages and no setup step; no key, key identifier, token, credential path or project identifier is recorded |
| R0-05 | The current-state records agree | PASS | PHASE_STATUS, CURRENT_HANDOFF, DECISION_INDEX, DECISION_LOG and this record give Checkpoint C1 decided as rejected for focused rework (DIR-055), R0 done and R1 next, and Stage 2 not authorized; none claims R1 work, a push of commit 3 or publication |
| R0-06 | Links and identifiers | PASS | 1,920 relative links and anchors checked in every Markdown file outside `docs/00-governance/sources/`, 0 broken; DIR-055, DIR-056, OBS-020 and TECH-028 each have one row in the decision log's index and one entry heading; the labels OC-01–OC-12 and PL-1–PL-5 belong to DIR-055 and DIR-056, which state them, and are tabulated once, in [Checkpoint C1 — Owner verdict](#checkpoint-c1--owner-verdict); the items R0-01–R0-09 are defined here; no GAP, RISK or APPR identifier is defined — GAP-052, RISK-010 and APPR-011 stay unused |
| R0-07 | No secret, placeholder, binary, image or DesainPakeAI output | PASS | The new files are two LF text records; a search of the staged diff finds no key, token, credential path, key or project identifier, e-mail address or unfilled placeholder — the angle-bracket templates inside record 53 are the Owner's verbatim text; no page source, export or capture of DesainPakeAI is in the repository |
| R0-08 | Content-truth counts unchanged | PASS | V1_SCOPE, PERMISSIONS_MATRIX, DATABASE and GAP_REGISTER are byte-identical to `2a5c8aa` and were counted again: 18 MUST capabilities and 14 document types (V1_SCOPE), 80 capabilities (PERMISSIONS_MATRIX), 125 logical tables in 13 modules (DATABASE §4) and the gap totals 51 — 4 CLOSED, 38 OPEN, 0 OWNER_DECISION_REQUIRED, 9 ACCEPTED_RISK |
| R0-09 | No P7 normative document and no P0–P6 specification changed | PASS | INFORMATION_ARCHITECTURE, DESIGN_SYSTEM, ADMIN_FLOW, everything under `docs/01-product/`–`docs/06-api-performance/`, CONTEXT_INDEX, AGENTS.md and the other P0 specifications are byte-identical to `2a5c8aa`; SOURCE_OF_TRUTH changes only as the continuity record of DIR-056 §§6.2 and 8.1 |

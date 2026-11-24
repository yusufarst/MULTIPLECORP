# TASK-097 — Shared-foundation defects and GF-01 — the responsive bands, the spinner, touch input size, one heading, reference codes, the tablet rail, one "WIB", the composer's tolerance (§6; D-DS-02; GF-01)

Status: APPROVED | Updated: 2026-11-23 | Owner: Planning

Approval: the one consolidated shared-foundation defect Task of [TASK_PLAN §7](TASK_PLAN.md#7-controlled-task-plan-changes) Change 31, which the Owner approved explicitly ([APPR-043](../00-governance/DECISION_LOG.md#dir-135-and-appr-043--change-31-one-consolidated-shared-foundation-defect-task-2026-11-23); DIR-135), from the defects the proposal A–F verified on `main` 36f7036 (DIR-134 E2) and TASK-065's GF-01 (DIR-134 E3).

**STATUS:** DONE

**RISK CLASS:** HIGH

**EXECUTOR:** AGENT

**OBJECTIVE:**

The shared foundation does what its approved contracts say on every page: the bands of [DESIGN_SYSTEM §6](../07-ux-design/DESIGN_SYSTEM.md#6-layout-grid-and-breakpoints) apply as written, a busy control's spinner turns, touch text fields are 16 px, a page and each of its parts have one heading, an error shows a reference code only when it has one, the tablet band shows the 80 px rail, a time carries one "WIB", and an issued document matches its composer's preview within TASK-029's tolerance (GF-01). No visual direction, navigation model, contract text or business behaviour changes.

**WHY THIS EXISTS:**

The proposal A–F verified these defects on `main` 36f7036 (DIR-134 E2); each is classed by its actual impact under DIR-111, none P0 — no data lost or exposed, authentication works, the main flows complete:

- **P2** — `resources/css/tokens.css:26` resets `--breakpoint-*` and defines no `sm`, so the 65 `sm:` utilities in `resources/js` never apply.
- **P2** — `tokens.css:25` resets `--animate-*` and no `@keyframes` exists, so `animate-spin` (`resources/js/components/ui/spinner.tsx:41`) never turns: every busy control loses its motion.
- **P2** — `tokens.css:472` gives `--text-input-size` 14 px, and the sign-in fields 15.5 px (`:484`); the `text-input-size` utility outranks `resources/css/app.css:148`'s 16 px rule for a coarse pointer, so iOS zooms into every field (D-DS-02; TASK-065 GF-02).
- **P2** — duplicate headings: PG-011 names its financial part twice (`resources/js/Pages/PRJ/Journey.tsx:339–340`, `journey.financial` on the section and on its block); on the phone the shell's top bar repeats the page's title beside its heading (`resources/js/layouts/AppShell.tsx:109`; 12 of 14 shelled pages in the audit of 2026-11-22).
- **P2** — `resources/js/components/outcome-message.tsx:77` and `resources/js/components/region-state.tsx:67, 98` print "Kode referensi:" when the reference is empty.
- **P2** — from 768 to 1,024 px the 264 px sidebar shows where `resources/js/components/shell/navigation.tsx:16` and §6 give the 80 px rail.
- **P3** — `resources/js/lib/format.ts:100–103`'s `time()` already ends in "WIB", and the templates that append "WIB" to it — `pages.pg_006.as_of_tpl` among them — print it twice (TASK-065 GF-07 on PG-006; the audit on Pekerjaan and Laporan).
- **P1, release-blocking (DIR-124; DIR-134 E3)** — GF-01: DOC-09's blocks lie up to 12.27 mm (Chromium) and 12.67 mm (WebKit) from the issued rendition's, against TASK-029's criterion 6, on PG-036 and every composer — PG-014, PG-033, PG-042, PG-043 ([gate record](../08-testing/evidence/TASK-065_UI_GATE_RECORD.md#7-the-findings-for-the-owners-decision)); the record's remedy: the issued document without the body's margin, the draft marking over the top margin rather than in the flow.

**DEPENDS ON:**

- TASK-014 — Localization — locales, the language switch and the key catalogue
- TASK-015 — Design system — tokens, components, patterns and states
- TASK-016 — Application shell, navigation and the company context
- TASK-029 — Document engine — drafts, issue, revision, void, renditions and the composer
- TASK-032 — Projects — list, form, journey page and amendment

**BLOCKS:**

- TASK-086 — UAT with the Owner and the validators

**PARALLEL SAFE:** YES — with the operator's TASK-078 alone: disjoint files apart from TASK_PLAN and CURRENT_HANDOFF, whose concurrent changes are kept by merging `main` into the branch before the merge; with no other AGENT Task. The UX pilot under APPR-042 is no Task and never merges; its validated foundation code is reused here where it conforms to the current contracts (DIR-135).

**IN SCOPE:**

- Bands: each of the 65 `sm:` utilities given the band §6 defines for its intent; no band added that §6 does not name
- Spinner: the spin keyframes in the tokens, a turn of `--mc-dur-spin`, after the 300 ms delay, still under reduced motion
- Touch input: 16 px on a coarse pointer for every text field, select and textarea, the sign-in fields included; the desktop sizes unchanged
- Headings: PG-011's financial part named once; on the phone one visible title per page, as §6 and [§11](../07-ux-design/DESIGN_SYSTEM.md#11-responsive-pattern-matrix) place it
- Reference code: "Kode referensi" only with a code the server gave
- Tablet: the 80 px rail from 768 to 1,024 px, as §6 and KB-03 give it
- Time: one "WIB" in every template that shows a formatted time
- GF-01: the issued rendition's page box without the body's margin and the draft marking outside the flow, DOC-09's blocks within criterion 6's tolerance of the preview on Chromium and WebKit, for every composer
- The pilot's validated foundation code reused where it conforms to the current contracts, without the pilot's provisional tokens

**OUT OF SCOPE:**

- The redesign (CR-002): palette, type scale, navigation model, sidebar hierarchy, page layouts, skeletons, stage indicators
- Page-specific findings — GF-04, GF-05, GF-06, GF-08, GF-09–GF-13, GAP-073's follow-ups, SH-01 past about 200 lines —: the redesign's groups before GO-LIVE (DIR-134 E3, E9)
- Any new business function, route, capability or copy beyond removing the second "WIB"
- TASK-078's files; staging, production, deployment

**REFERENCES:**

- DESIGN_SYSTEM §6, §11, D-DS-02, D-DS-19, the spinner and region states; KB-03
- TASK-029's criterion 6; TASK-065's gate record, GF-01, GF-02 and GF-07
- DIR-111's classes; DIR-124; [DIR-134](../00-governance/DECISION_LOG.md#dir-134-and-appr-042--the-ux-first-redesigns-decisions-e1e9-and-its-isolated-pilot-2026-11-23) E2, E3; [APPR-043](../00-governance/DECISION_LOG.md#dir-135-and-appr-043--change-31-one-consolidated-shared-foundation-defect-task-2026-11-23)

**EXPECTED MODULES / FILE AREAS:**

- `resources/css/tokens.css`, `resources/css/app.css`
- `resources/js/components/ui/spinner.tsx`, `input.tsx` and the sign-in fields; `resources/js/components/outcome-message.tsx`, `region-state.tsx`
- `resources/js/layouts/AppShell.tsx`, `resources/js/components/shell/navigation.tsx`, the page header
- `resources/js/lib/format.ts`; `lang/id.json`, `lang/en.json`
- `resources/js/Pages/PRJ/Journey.tsx`; the files holding `sm:` utilities
- TASK-029's issued rendition and its draft marking; the tests below

**BUSINESS RULES:**

- unchanged — no command, payload, posting, balance, capability or search semantics changes

**ARCHITECTURE RULES:**

- the existing tokens, components, shell and document renderer; no new package, component family or abstraction; shadcn/ui and Base UI kept, no Radix or Vaul

**SECURITY / PERMISSION REQUIREMENTS:**

- unchanged — no route, capability or admission changes; a reference code shown only as the server gave it

**COMPANY SCOPE AND FIELD PROJECTION:**

- unchanged

**CONCURRENCY / IDEMPOTENCY:**

- N/A — no command changes

**AUDIT:**

- N/A — no audit event changes

**MCP SESSION PROFILE:**

- Required MCP servers: none — [TOOLCHAIN](../00-governance/TOOLCHAIN.md#mcp-servers) records none installed
- MCP servers that should remain OFF: every server not required
- Native CLI/package preferred over MCP: YES
- Post-Task deactivation/cleanup: none

**AUTH IMPACT:**

- Authentication change: NONE — the sign-in fields' text size only
- Authentication implementation: LARAVEL_NATIVE — decided once ([EXECUTION_CONTEXT](EXECUTION_CONTEXT.md)), reused, never redesigned
- Auth profile: N/A
- Google sign-in: N/A — not touched
- Google registration behavior: N/A — not touched
- Google credentials owner action required: NO
- Authorization change: NONE
- Provider/library-specific leakage into domain: NO

**COST IMPACT:**

- New recurring cost: NONE
- Paid third-party service: NO
- Free/open-source alternative: N/A — nothing paid is considered
- Estimated additional monthly production cost: 0
- Owner approval required: NO

**DATABASE IMPACT ASSESSMENT:**

- Schema change: NONE
- Migration required: NO
- Existing data transformation: NONE
- Direct production DB access: FORBIDDEN
- Data-loss risk: NONE — presentation, tokens and the issued rendition's layout only

**MODULARITY / EXTENSIBILITY:**

- Module boundary affected: the shared tokens and components, the shell, the localization formatter, PG-011's page, the document renderer's issued rendition
- Stable core kept provider-neutral: YES
- External/vendor-specific code isolated: YES
- Replaceable adapter/contract required: NO
- Provider lock-in risk: NONE
- Future extension impact: NONE — the redesign builds on the corrected foundation
- Speculative abstraction avoided: YES

**TOOL REQUIREMENTS:**

- Context7: OPTIONAL
- Graphify: OPTIONAL
- Browser E2E: REQUIRED — the tests below on Chromium and WebKit, in CI (DIR-105); locally one Playwright worker and only MULTIPLECORP's services
- Other: none — Modern Web Guidance stays outside the repository; the shadcn CLI is not needed
- Missing safe tool behavior: AUTO-INSTALL project-locally (AICWDF §6.3); a paid tier STOPS for the Owner

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: N/A — the call sites are found by search
- Pre-change questions: every `sm:` utility and its intended band; every template that receives `time()`; every caller of `outcome.reference`; every page whose phone title the shell repeats; every composer and its issued rendition
- Expected affected areas: the files named above; no route, query or command

**UI / UX REQUIREMENTS:**

- as IN SCOPE, measured against the current approved contracts; no visual change beyond the corrected behaviours

**ROUTE IMPACT:**

- N/A — no route added or changed

**INTERACTION CONTRACTS:**

- unchanged

**NAVIGATION CONTRACT:**

- unchanged — the tablet rail as §6 and KB-03 already give it

**ACCEPTANCE CRITERIA:**

1. At every band edge from 320 to 1,440 px the layout follows §6, no `sm:` utility is left without a defined band and no page scrolls sideways.
2. A busy control's spinner turns, with a computed animation, after the 300 ms delay; under reduced motion it stays still with its label.
3. On a coarse pointer every text field, select and textarea, the sign-in fields included, computes 16 px; the desktop sizes are unchanged.
4. On the phone each page shows one visible title; PG-011's financial part has one heading.
5. "Kode referensi" appears only with a non-empty code.
6. From 768 to 1,024 px the navigation is the 80 px rail; the other bands unchanged.
7. Every formatted time in a template carries one "WIB".
8. GF-01: in the issued rendition DOC-09's blocks lie within criterion 6's tolerance of the composer's preview on Chromium and WebKit, and every composer issues the same way.

**TEST REQUIREMENTS:**

- Unit: none beyond the frontend tests below
- Frontend (TL-03): the templates that receive `time()` render one "WIB"
- Feature: N/A — no server behaviour changes beyond the issued rendition, which the browser tests measure
- Integration: N/A
- Authorization: the route audit (TL-07) and every denial suite unchanged and green
- Route integrity: N/A — no route added or changed
- Interaction: the browser tests below
- Browser E2E: TL-09 — the spinner's computed animation on a busy command; a coarse pointer's 16 px fields on the sign-in and a form page; "Kode referensi" absent without a code; GF-01's block positions, preview against issued rendition, within tolerance — on Chromium and WebKit
- Localization/language switch: the key catalogue and copy lists green; the changed templates in both languages
- Visual breathing room/density: N/A — no visual change beyond the corrected behaviours
- Responsive: TL-11 — the band edges, the tablet rail, one title on the phone
- Accessibility: the existing gate; PG-011's headings
- Production build: TL-13
- Security: no capability change; the reference code as given
- Performance: N/A — no query changes
- Migration: N/A

**TEST CHECKLIST:**

- [x] Lint: Pint and ESLint in this head's CI (TL-01)
- [x] Typecheck: PHPStan and TypeScript in this head's CI (TL-02)
- [x] Relevant automated tests: the frontend and Browser tests above in this head's CI
- [x] Authorization: the route audit (TL-07) and every denial suite unchanged and green
- [x] Route integrity: N/A — no route added or changed
- [x] Interaction behavior: the Browser tests above
- [x] Browser E2E if required: on Chromium and WebKit (TL-09)
- [x] Responsive/adaptive if required: the band edges, the tablet rail and the phone's one title (TL-11)
- [x] Visual breathing room/density if UI: N/A — no visual change beyond the corrected behaviours
- [x] Production build: TL-13 in this head's CI
- [x] Security checks if required: no capability change; the reference code as given
- [x] Database safety review: no schema change, no migration, no write path; production not touched
- [x] Final diff review: ER-06's independent review and Codex's review within DIR-096's bounded loop

**COMPLETION EVIDENCE:**

- Test outputs (TEST_STRATEGY §16): this record's head, its fresh CI on GitHub's runner, every job required to PASS before the merge, the head containing `main` (DIR-118); the run named in the squash commit's message (DIR-127)
- Build result: TL-13 in the same run
- Route evidence: N/A — no route added or changed
- Interaction evidence: the Browser tests above
- Browser evidence: the Browser tests above on Chromium and WebKit, as the pull request's checks show before the merge
- Localization evidence: the changed templates in both languages
- Cost evidence/exception if applicable: ADDITIONAL RECURRING COST CHANGE: NONE
- Files changed: the tokens and the coarse pointer's rule — `resources/css/tokens.css`, `resources/css/app.css` —; `resources/js/components/outcome-message.tsx`, `region-state.tsx`, `record-blocks.tsx`; `resources/js/layouts/AppShell.tsx`; `resources/js/lib/format.ts`; PG-006's, the signals', PG-051's and PG-053's calls (`Beranda.tsx`, `features/home/signals.tsx`, `features/reporting/report-view.tsx`, `EksporSaya.tsx`); `resources/js/Pages/PRJ/Journey.tsx`; the 37 files holding the 65 `sm:` utilities; `app/Modules/DOC/Services/MustacheTemplates.php` and `resources/documents/paper.css`; the gallery's specimen without a code (`Pages/Fixtures/Components.tsx`); `tests/Frontend/foundation.test.ts`, `tests/Browser/Responsive/foundation.spec.ts`, `tests/Browser/Responsive/composer-tolerance.spec.ts`, `tests/Unit/DOC/DocumentPaperTest.php`
- Commit SHA: this branch's commits from `main`, the Owner's identity, no AI attribution
- Notes: each defect's DIR-111 class and its evidence as WHY THIS EXISTS gives them — seven P2/P3 and GF-01 P1 —, all eight corrected. **Bands**: the 65 `sm:` utilities on §6's tablet band `md:`, the band each one's intent names — beyond the phone's one column —, the variant names `sm` of the size maps untouched; a frontend test refuses a utility on a band §6 does not define. **Spinner**: `--animate-spin` and the `spin` keyframes in the tokens' theme, one turn per `--mc-dur-spin`, `motion-reduce:animate-none` kept. **Touch input**: D-DS-02's rule moved outside the cascade layers, so it outranks `text-(length:--text-input-size)`, `text-body` and every other size utility of a field on a coarse pointer; a fine pointer keeps 14 px. **Headings**: PG-011's financial part names itself once, `PositionBlock`'s title optional; on the phone the app bar's copy of the title is out of sight where the page's header names the page (PT-03: the back arrow and the title lead), its place kept. **Reference code**: "Kode referensi" only with a non-empty code in `OutcomeMessage`, `RegionError` and `SystemPage`. **Tablet**: the 80 px rail from 768 to 1,024 px from the band's media query, Ctrl B unchanged from 1,025 px. **Time**: `clock()` and `dateClock()` for the six templates that name "WIB" themselves — PG-006, the signals' "Diperbarui", PG-051's position and print footer, PG-053's two —, the catalogue's copy unchanged. **GF-01**: the renderer's document without the body's margin (in its wrapper, not in `paper.css`, which the bundle also imports); the draft marking over the sheet's top margin on screen, out of the flow; the meta block's references on one line in both engines, as §6 keeps a record number unbroken — the first run (45331716781) left the layout's shift at 0.01 mm and one row beyond 1 mm, the browser breaking `CTH/PRJ/2026/0001` at its slashes where the renderer did not, corrected in DIR-127's one batch; the tolerance asserted, no longer only recorded, on DOC-09 — the layout the fixture drafts on PG-036 —, and every one of the fourteen layouts issued through the same wrapper, paper and marking, held by the unit test, so DOC-09's measurement stands for every composer; ER-06 found the composers' own layouts beyond the fixture's subjects, a fixture change outside this contract. **Pilot code reused** (DIR-135), without its provisional tokens: the reference-code guard of `region-state.tsx` and `outcome-message.tsx`, the spin keyframes, the tablet rail's `rail || tablet` and the phone title's `group-has` visibility, `clock()`, PG-011's single heading; not taken: the pilot's `--breakpoint-sm`, its wordmark, skeletons, navigation and palette
- Notes, DOD: each non-applicable item with its reason (DOD-03); the DONE update of TASK_PLAN, this file and CURRENT_HANDOFF (AICWDF §22.3)

**SAFE NEXT TASK:**

- TASK-086 once its other dependencies allow; the UX pilot continues under APPR-042

**DO NOT DO:**

- change the palette, type scale, navigation model or page layouts — CR-002's
- fix a page-specific finding outside this contract
- add a package, a band §6 does not name, or a new component family
- weaken an assertion, a gate or a test to pass
- touch TASK-078's files, staging or production

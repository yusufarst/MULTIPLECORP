# TASK-099 — Navigation — the three-level Menu: the shell, rail, sidebar and phone menu, the 37 pages and the 65 NAV contracts (K-4)

Status: APPROVED | Updated: 2026-11-28 | Owner: Planning

Approval: one of the seven Tasks of [TASK_PLAN §7](TASK_PLAN.md#7-controlled-task-plan-changes) Change 33, which the Owner approved explicitly ([APPR-052](../00-governance/DECISION_LOG.md#dir-148-and-appr-052--change-33-the-redesigns-seven-tasks-d-8s-minimum-corrections-and-the-release-freeze-exception-2026-11-28); DIR-148), within the approvals of CR-002's packages ([DECISION_LOG](../00-governance/DECISION_LOG.md#approval-register), APPR-044–APPR-051).

**STATUS:** DONE

**RISK CLASS:** HIGH

**EXECUTOR:** AGENT

**OBJECTIVE:**

The navigation of the approved redesign ([APPR-050](../00-governance/DECISION_LOG.md#dir-146-and-appr-050--cr-002s-package-k-4-the-three-level-menu-2026-11-28)) is built: *Menu* in three levels — eight modules in three groups, their 37 pages as leaves — as a 248 px sidebar from 1,025 px, an 80 px labelled rail with a 20 rem drawer from 768 to 1,024 px, and a full-screen phone *Menu*; the path convention "*Menu* › module › page"; and the non-loss proof: every account sees exactly the pages it was authorised to see, the 37 pages, the 65 NAV contracts and NV-01–NV-08 reachable as before. The term *Menu* and the three group labels are **proposed** until the novice validation.

**WHY THIS EXISTS:**

- The Owner approved K-4's structure ahead of the validation ([APPR-052](../00-governance/DECISION_LOG.md#dir-148-and-appr-052--change-33-the-redesigns-seven-tasks-d-8s-minimum-corrections-and-the-release-freeze-exception-2026-11-28) records the changed order); E6 and E7 stay mandatory gates. The structure is built after the foundation, so the validation's results exist before the labels are final.
- The pilot proved the tree, the rail, the drawer and the phone sheet in its harness; its CI layer asserts only Beranda, Proyek, Stok and a few orders — the navigation tree itself has no real-stack evidence yet.

**DEPENDS ON:**

- TASK-016 — Application shell, navigation and the company context
- TASK-098 — Design foundation — the working tokens (Grafit & Kobalt), the skeleton and motion, Base UI only, and D-8's quantity and link-button corrections (K-3; K-5; K-7; D-8)

**BLOCKS:**

- TASK-100 — The list pattern and the four pilot areas — labelled rows, the stage indicator and first step, the recovery codes' stage; Masuk, Beranda, Proyek and Stok (D-5; D-6; K-6; E5; D-8)

**PARALLEL SAFE:** NO — the one Autonomous Executor runs the redesign's Tasks in their order (DIR-138); they share the tokens, the components and the key catalogue

**IN SCOPE:**

- The contract text of K-4 — INFORMATION_ARCHITECTURE §§4–6, 8, 13, 14.4 and GL-030/187/194/195, DESIGN_SYSTEM PT-01, PT-02, §§6, 9.6, 10 and GR-11, NAVIGATION_CONTRACTS NV-06 and the fifteen rows' Parent and Entry cells, LOCALIZATION's navigation rows and keys, INT-003 and the route declarations as the Owner approved it — applied to the documents in this Task's pull request together with the code, `lang/` and tests it must agree with (the key catalogue and the copy-list generator read LOCALIZATION as written, the navigation contracts are traced to their tests)
- `resources/js/lib/navigation.ts` (the tree: 8 modules, 3 groups, 37 leaves, the server's permitted entries deciding what each account sees — AZ-01), `components/shell/navigation.tsx`, `layouts/AppShell.tsx`: sidebar, rail and drawer, the phone *Menu* (Kerja → groups → Akun), the bottom bars (*Beranda*/*Pekerjaan* · *Proyek* · *Cari* · *Menu*)
- The path convention, the page headers' breadcrumbs and the NAV contracts' Parent, Entry and Back columns as amended; the group label never a step of a path
- Copy: `shell.nav.menu` and `shell.nav.group_*` and the retired `glossary.gl_030` and `shell.nav.lainnya`, marked *proposed — final after novice validation (DIR-134 E7)*; the two leaf titles *Pembelian* and *Daftar Penawaran*
- The language switch reachable in the phone's *Akun* (the contract places it there; `main` shows only the name, the role, *Akun Saya* and *Keluar*)
- The non-loss test: an account with every capability gets exactly 37 leaves and every NAV route; each leaf's capability matched to the access table of INFORMATION_ARCHITECTURE §8; Owner without a company, Admin with and without capabilities as in the pilot's S1-11
- The old specifications that encode the old menu (`tests/Browser/E2E/shell.spec.ts`, `journey-cj02`, `journey-cj14`, `tests/Frontend/shell.test.ts`) reconciled against the new contract — updated, never removed or skipped

**OUT OF SCOPE:**

- The list pattern and the pages' bodies (TASK-100–TASK-103)
- A page, route, capability or rule added, moved or removed
- The final group labels and the term *Menu* — one Level 1 edit after the novice validation
- The pilot's Docker and CI files

**REFERENCES:**

- [APPR-050](../00-governance/DECISION_LOG.md#dir-146-and-appr-050--cr-002s-package-k-4-the-three-level-menu-2026-11-28); [APPR-045](../00-governance/DECISION_LOG.md#dir-141-and-appr-045--cr-002s-package-k-2-the-golden-frontend-reference-as-inspiration-2026-11-28) (S-9: GD-03's sentence read as inspiration)
- INFORMATION_ARCHITECTURE §§4–6, 8, 13, 14.4; DESIGN_SYSTEM PT-01, PT-02, §6, §9.6, §10, GR-11; NAVIGATION_CONTRACTS NV-01–NV-08 and the 65 contracts; LOCALIZATION's navigation keys
- The pilot (read only): `lib/navigation.ts`, `components/shell/navigation.tsx`, `layouts/AppShell.tsx` and the specs S1-01–S1-11

**EXPECTED MODULES / FILE AREAS:**

- `resources/js/lib/navigation.ts`, `resources/js/components/shell/`, `resources/js/layouts/AppShell.tsx`
- `lang/id.json`, `lang/en.json`, `lang/registry/`
- `tests/Frontend/shell.test.ts`, `tests/Browser/E2E/shell.spec.ts`, the journeys' specs; a new non-loss test

**BUSINESS RULES:**

- unchanged — no command, payload, posting, balance, capability, calculation or search semantics changes

**ARCHITECTURE RULES:**

- the existing tokens, components, shell and renderer; shadcn/ui and its Base UI variant only (K-7), no Radix, Vaul or Sonner; no new package or component family beyond what the Task names; the pilot branch is never merged or rebased, its code reused by copy and review

**SECURITY / PERMISSION REQUIREMENTS:**

- unchanged — no route, capability or admission changes; a figure, an action or a region the account holds no capability for is not shown, and no skeleton or placeholder stands in for it

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

- Authentication change: NONE
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
- Data-loss risk: NONE — presentation, tokens, copy and tests only

**MODULARITY / EXTENSIBILITY:**

- Module boundary affected: the presentation layer only
- Stable core kept provider-neutral: YES
- External/vendor-specific code isolated: YES
- Replaceable adapter/contract required: NO
- Provider lock-in risk: NONE
- Future extension impact: NONE
- Speculative abstraction avoided: YES

**TOOL REQUIREMENTS:**

- Context7: OPTIONAL
- Graphify: OPTIONAL
- Browser E2E: REQUIRED — the tests below on Chromium and WebKit, in CI (DIR-105); locally one Playwright worker and only MULTIPLECORP's services, no Docker Desktop
- Other: none — Modern Web Guidance stays outside the repository; every shadcn CLI `add` after `--dry-run`
- Missing safe tool behavior: AUTO-INSTALL project-locally (AICWDF §6.3); a paid tier STOPS for the Owner

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: N/A — the call sites are found by search
- Pre-change questions: every caller of a shared component or helper this Task changes; every page and test that encodes the old behaviour
- Expected affected areas: the files named above; no route, query or command

**UI / UX REQUIREMENTS:**

- as IN SCOPE, measured against DESIGN_SYSTEM §5 and §§9–10 alone and the Owner's approved packages (CR-002); every value of the palette and type scale a working direction until the Owner's visual verdict (DIR-134 E8); the proposed copy marked proposed

**ROUTE IMPACT:**

- N/A — no route added or changed

**INTERACTION CONTRACTS:**

- unchanged except the presentation the approved packages state

**NAVIGATION CONTRACT:**

- the 65 NAV contracts and NV-01–NV-08 as K-4 amends their Parent and Entry cells and NV-06 — no contract added or removed

**ACCEPTANCE CRITERIA:**

1. An account with every capability reaches exactly the 37 pages through the *Menu* at 320, 390, 768, 1,024, 1,025 and 1,440 px, each in no more than the taps or clicks its path states; no page is reachable by a path the NAV contracts do not state.
2. An account sees only the entries the server permits (AZ-01): Owner, Admin with and without capabilities, and an Owner with no company produce the entries of the pilot's S1-11, none extra, none missing.
3. The 65 NAV contracts and NV-01–NV-08 hold; the Parent and Entry cells follow §5.3 as amended; no back path changes.
4. The rail from 768 to 1,024 px is 80 px and labelled, its drawer 20 rem, the sidebar 248 px from 1,025 px; the phone *Menu* is a full-screen sheet with focus kept inside it and returned on close.
5. The language is switchable from the phone's *Akun*.
6. *Menu* and the group labels read as proposed; a single Level 1 edit can replace them without a structure change.

**TEST REQUIREMENTS:**

- Frontend (TL-03): the tree's 37 leaves and the capability match
- Browser E2E (TL-09): the navigation on Chromium and WebKit at the six widths; the keyboard (Ctrl B, Esc, focus return); the route audit unchanged
- Interaction and navigation contracts: every NAV and NV test of the traceability matrix green (`check-traceability`)
- Accessibility: axe on the shell at 390, 820 and 1,280 px, the drawer and the phone sheet
- Localization: both languages; the proposed copy listed
- Production build: TL-13
- Migration: N/A

**TEST CHECKLIST:**

- [x] Lint: Pint and ESLint in this head's CI (TL-01)
- [x] Typecheck: PHPStan and TypeScript in this head's CI (TL-02)
- [x] Relevant automated tests: the frontend and Browser tests above in this head's CI
- [x] Authorization: the route audit (TL-07) and every denial suite unchanged and green
- [x] Route integrity: N/A — no route added or changed
- [x] Interaction behavior: the Browser tests above
- [x] Browser E2E if required: on Chromium and WebKit (TL-09)
- [x] Responsive/adaptive if required: the band edges and the labelled-rows thresholds (TL-11)
- [x] Visual breathing room/density if UI: against §5 and §§9–10; the density decisions (the 12 px floor, the 20/28 title) the Owner's, at the walkthrough
- [x] Production build: TL-13 in this head's CI
- [x] Security checks if required: no capability change; nothing shown that the account may not read
- [x] Database safety review: no schema change, no migration, no write path; production not touched
- [x] Final diff review: ER-06's independent review within DIR-096's bounded loop

**COMPLETION EVIDENCE:**

- Test outputs (TEST_STRATEGY §16): this record's head, its fresh CI on GitHub's runner, every job required to PASS before the merge, the head containing `main` (DIR-118); the run named in the squash commit's message (DIR-127)
- Build result: TL-13 in the same run
- Route evidence: N/A — no route added or changed
- Interaction evidence: the Browser tests above
- Browser evidence: the Browser tests above on Chromium and WebKit, as the pull request's checks show before the merge
- Localization evidence: the changed templates in both languages
- Cost evidence/exception if applicable: ADDITIONAL RECURRING COST CHANGE: NONE
- Files changed: `resources/js/lib/navigation.ts` (the three groups, the 37 leaves, `navigationTree`, `currentLeaf`; `PG-039` and `PG-058` added to `BUILT`), `resources/js/components/shell/navigation.tsx` (the sidebar's groups and module disclosures, the labelled rail, the *Menu* drawer and the phone's full-screen *Menu*), `resources/js/layouts/AppShell.tsx`, `resources/js/lib/title.ts`, `resources/js/lib/utils.ts`; `resources/css/tokens.css` (the 248 px sidebar, the 80 px rail, the 20 rem drawer, the guide line's inset); `lang/id.json`, `lang/en.json` (`shell.nav.menu` and the three `shell.nav.group_*`); `tests/Unit/Localization/KeyCatalogueTest.php` (902 keys); `tests/Frontend/navigation-tree.test.ts` (new), `tests/Browser/Responsive/navigation.spec.ts` (new), `tests/Browser/E2E/shell.spec.ts`, `journey-cj02.spec.ts`, `journey-cj14.spec.ts`, `tests/Browser/Accessibility/shell.spec.ts`; K-4's exact text in `docs/07-ux-design/` (INFORMATION_ARCHITECTURE, DESIGN_SYSTEM, NAVIGATION_CONTRACTS, LOCALIZATION) and `docs/03-workflows/` (INTERACTION_CONTRACTS, ROUTE_CONTRACTS), with `FEATURE_COVERAGE_MATRIX`'s two component names
- Commit SHA: this branch's commits from `main`, the Owner's identity, no AI attribution
- Notes: **K-4's contract text with its code** — the Owner's approved structure (APPR-050) lands here with the navigation, the key catalogue and the tests it must agree with; the term *Menu* and the three group labels stay *proposed — final after novice validation (DIR-134 E7)*, `shell.nav.*` marked `DIR-146` in LOCALIZATION §6 and the catalogue's count 898 → 902 recorded in §7 (the two retired keys kept as retired rows). **The tree** — eight modules in three groups, 37 leaves, every page by its capability and company scope (AZ-01), the server deciding each request; the sidebar 248 px, the rail 80 px labelled with a 20 rem drawer beside it, the phone's bottom bar of four and a full-screen *Menu* (*Kerja*, the groups, *Akun*), a page opened from it closing it. **Non-loss found a defect already in `main`:** the test that every leaf is reachable for an account that holds every capability failed on two pages — *Dokumen Administrasi* (PG-039; TASK-035) and *Impor* (PG-058; TASK-057) were built, with routes, but never added to `BUILT`, so the shell hid their entries; both are added, so the 37 are reachable. **Two lines the notes left out** (ER-06's M4): `--sidebar-width` 264 → 248 and the navigation icon 24 → 20 px in DESIGN_SYSTEM. **Old specifications reconciled, none weakened:** `shell.spec.ts`, `journey-cj02`, `journey-cj14` and the accessibility shell spec now open a module by its button and read the groups; the new `navigation.spec.ts` measures the sidebar, the rail, the drawer and the phone sheet at their bands. Not done and recorded as such: the novice validation (E6, E7), real devices and screen readers — TASK-104's gate.
- Notes, DOD: each non-applicable item with its reason (DOD-03); the DONE update of TASK_PLAN, this file and CURRENT_HANDOFF (AICWDF §22.3)

**SAFE NEXT TASK:**

- TASK-100 once this Task is DONE; the novice validation (E6, E7) runs on the built navigation

**DO NOT DO:**

- add, move or remove a page, route or capability
- show an entry the server did not permit
- present *Menu* or a group label as final
- weaken, skip or delete a test
- touch TASK-078's files, staging or production, or start a deployment

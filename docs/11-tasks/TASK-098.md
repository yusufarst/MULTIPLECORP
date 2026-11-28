# TASK-098 — Design foundation — the working tokens (Grafit & Kobalt), the skeleton and motion, Base UI only, and D-8's quantity and link-button corrections (K-3; K-5; K-7; D-8)

Status: APPROVED | Updated: 2026-11-28 | Owner: Planning

Approval: one of the seven Tasks of [TASK_PLAN §7](TASK_PLAN.md#7-controlled-task-plan-changes) Change 33, which the Owner approved explicitly ([APPR-052](../00-governance/DECISION_LOG.md#dir-148-and-appr-052--change-33-the-redesigns-seven-tasks-d-8s-minimum-corrections-and-the-release-freeze-exception-2026-11-28); DIR-148), within the approvals of CR-002's packages ([DECISION_LOG](../00-governance/DECISION_LOG.md#approval-register), APPR-044–APPR-051).

**STATUS:** DONE

**RISK CLASS:** STANDARD

**EXECUTOR:** AGENT

**OBJECTIVE:**

The design foundation of the approved redesign stands, on the shared components every later Task uses: the working tokens of Grafit & Kobalt ([APPR-051](../00-governance/DECISION_LOG.md#dir-147-and-appr-051--cr-002s-package-k-3-grafit--kobalt-as-the-working-direction-2026-11-28)), the skeleton and its 300 ms rule ([APPR-046](../00-governance/DECISION_LOG.md#dir-142-and-appr-046--cr-002s-package-k-5-skeletons-for-a-region-that-is-loading-2026-11-28)), the primitives stated and kept as Base UI only ([APPR-044](../00-governance/DECISION_LOG.md#dir-140-and-appr-044--cr-002s-package-k-7-the-primitives-are-base-ui-only-2026-11-28)), and D-8's two shared corrections — a quantity of "100" never shown as "1", and a link drawn as a button never typed as one. No page is redesigned, no navigation changes, no business behaviour, permission, calculation or datum.

**WHY THIS EXISTS:**

- The Owner approved the packages on 2026-11-28 and Change 33 splits the redesign into seven Tasks in dependency order; the tokens, the skeleton and the shared helpers come first because every later Task builds on them (the Owner's order: foundation, navigation, list pattern, the four pilot areas, the other modules, the gate).
- **D-8 #2, verified on `main` 18a6b664** — the server sends a quantity whole (for example "100", with no decimal point), and fourteen of seventeen lines in six files trim trailing zeros without a guard, so "100" can be shown as "1" (`Reservations.tsx`, `Purchase.tsx`, `receipt-reversal.tsx`, `Journey.tsx`, `fulfilment.tsx` and `Stock.tsx`, found by search; the shared formatter `quantity()` trims only the fraction and is correct). It is a defect against the old contract too (a figure is shown whole). The correction touches the display only: the source value, the business rounding and the server's calculation are untouched.
- **D-8 #6, read in the code** — `resources/js/components/ui/button.tsx` hands a link drawn as a button to Base UI's button, whose default makes it `type="button"` (unchanged since TASK-015); its impact is low and the correction is at the component.

**DEPENDS ON:**

- TASK-015 — Design system — tokens, components, patterns and states
- TASK-097 — Shared-foundation defects and GF-01 — the responsive bands, the spinner, touch input size, one heading, reference codes, the tablet rail, one "WIB", the composer's tolerance (§6; D-DS-02; GF-01)

**BLOCKS:**

- TASK-099 — Navigation — the three-level Menu: the shell, rail, sidebar and phone menu, the 37 pages and the 65 NAV contracts (K-4)

**PARALLEL SAFE:** NO — the one Autonomous Executor runs the redesign's Tasks in their order (DIR-138); they share the tokens, the components and the key catalogue

**IN SCOPE:**

- The contract text of K-3 — DESIGN_SYSTEM §§5.1, 5.2, 7, the shell row, the stage marker, §12.1's pill and Annex B GR-01–GR-04 as the Owner approved it — applied to the document in this Task's pull request together with the tokens and components it must agree with: the tokens test (TL-08) reads §5 as written, so neither side changes alone
- Tokens: the colour tokens, the type scale, the shell row, the stage marker and the pill as `DESIGN_SYSTEM` §§5.1, 5.2, 12.1 now state them — each value a **working direction** until the Owner's visual verdict (DIR-134 E8); `--color-text-muted-canvas` at `#6f7074`; the contrast of every pair checked by a test, a done-stage marker that carries information at least 3:1 with a cue that is not colour (APPR-051)
- Skeleton: `components/ui/skeleton.tsx` and the region's loading state — shapes in the proportions of the content, after 300 ms, hidden from assistive technology, announced once through the region's own status line, reduced motion respected; **never for a region the account may not read** (no placeholder for a figure that needs a capability the account lacks) and the 300 ms rule for every skeleton, a stage column included (the Owner's correction 4)
- Motion: the pulse and fade-in tokens of the pilot, reused by copy and review; the spinner of a busy control as TASK-097 left it
- Base UI only: the primitives kept as they are; no `shadcn init`, `--force`, `--reinstall` or `add sidebar`; each `add` after `--dry-run` and an import check for `radix-ui`, `@radix-ui/*` or `vaul`; no new dependency
- D-8 #2: one shared quantity helper, every one of its call sites moved to it, with a test shown **red then green** on "100", "1000", "0.50", "10.000" and a value with a unit; D-8 #6: the link drawn as a button without `type="button"`, with its test
- The component gallery's specimens and the tokens' tests (`tests/Frontend/tokens.test.ts`) brought to the approved contract, none weakened
- The pilot's code reused by copy and review, never by merge or rebase of `pilot/ux-v2`; without its provisional tokens (`pilot.css`) and without its Docker and CI files

**OUT OF SCOPE:**

- The navigation (TASK-099), the list pattern and the pages (TASK-100–TASK-103), the stage indicator and the first step (TASK-100)
- The final palette values — the Owner's visual verdict (E8) sets them as one-row Level 3 edits
- A change to a business rule, a permission, a calculation, a stored value or the server's formatting
- Any new package, component family or abstraction beyond the skeleton

**REFERENCES:**

- [APPR-051](../00-governance/DECISION_LOG.md#dir-147-and-appr-051--cr-002s-package-k-3-grafit--kobalt-as-the-working-direction-2026-11-28); [APPR-046](../00-governance/DECISION_LOG.md#dir-142-and-appr-046--cr-002s-package-k-5-skeletons-for-a-region-that-is-loading-2026-11-28); [APPR-044](../00-governance/DECISION_LOG.md#dir-140-and-appr-044--cr-002s-package-k-7-the-primitives-are-base-ui-only-2026-11-28); the Owner's correction 3 and 4 (the source of the approval)
- DESIGN_SYSTEM §§5.1, 5.2, 8, 9.4, 9.6, 10, 12.1, 12.3, Annex B GR-01–GR-04; D-DS-16, D-DS-17, D-DS-19
- The pilot `pilot/ux-v2` (read only): `resources/js/components/ui/skeleton.tsx`, the region state, the quantity display of `Stock.tsx`

**EXPECTED MODULES / FILE AREAS:**

- `resources/css/tokens.css`, `resources/css/app.css`
- `resources/js/components/ui/skeleton.tsx`, `button.tsx`, `resources/js/components/region-state.tsx`
- `resources/js/lib/format.ts` and the nine files whose seventeen lines trim a quantity
- `tests/Frontend/`, `tests/Browser/Responsive/`; the gallery (`Pages/Fixtures/Components.tsx`)

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

- unchanged — the paths follow the contracts as TASK-099 builds them

**ACCEPTANCE CRITERIA:**

1. The tokens are the approved working direction; each text pair reaches 4.5:1 and each control edge, focus ring, mark and bar 3:1 on the surface it sits on; `--color-text-muted-canvas` is `#6f7074`; a done-stage marker that carries information reaches 3:1 and keeps a cue that is not colour.
2. A region that loads shows a skeleton only after 300 ms, hidden from assistive technology and announced once; a fast answer never flashes one; a region the account may not read shows none and no placeholder figure; reduced motion keeps the shapes still.
3. A quantity the server sends as "100" is shown as "100" at every one of its former call sites and everywhere else that formats a quantity; "1000", "0.50" and "10.000" are shown as the contract states; the test is red on `main`'s helper and green on the new one; no source value, rounding or calculation changes.
4. A link drawn as a button is not typed as a button; a real button keeps its type.
5. No dependency is added and no Radix, Vaul or Sonner import exists.

**TEST REQUIREMENTS:**

- Frontend (TL-03): the tokens' contrast table, the skeleton's delay, the quantity helper on its cases and each caller, the link-button
- Browser E2E (TL-09): a deferred region on Chromium and WebKit — no skeleton under 300 ms, one status announcement after; an account without `cost.view` sees no placeholder
- Accessibility: axe on the gallery; contrast per surface (SV-05's method); reduced motion
- Localization: the key catalogue unchanged or extended without a missing key
- Security: no capability changes; the route audit (TL-07) unchanged and green
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
- Files changed: the tokens — `resources/css/tokens.css` (§5's colour values, the type scale's title 20/28 and floor of 12 px, the phone's title 18/24 and heading 16/22, the CTA's glow and shadow, the focus halo, `--animate-pulse` and `--animate-fade-in` with their keyframes) —; `resources/js/components/ui/skeleton.tsx` (new), `button.tsx`, `resources/js/components/region-state.tsx`, `record-blocks.tsx`; `resources/js/lib/format.ts` (`plainQuantity`) and the nine files that called a quantity's zeros away themselves (`components/form.tsx`, `features/inv/receipt-reversal.tsx`, `features/inv/receive-line.tsx`, `features/prj/fulfilment.tsx`, `Pages/INV/Reservations.tsx`, `Pages/INV/Stock.tsx`, `Pages/PRJ/Journey.tsx`, `Pages/PRJ/ProjectForm.tsx`, `Pages/PUR/Purchase.tsx`); the gallery's specimens (`Pages/Fixtures/Components.tsx`); `docs/07-ux-design/DESIGN_SYSTEM.md` (K-3's exact text, applied with the tokens it agrees with); `tests/Frontend/design-foundation.test.ts` (new), `tests/Browser/Responsive/design-foundation.spec.ts` (new); the review's P1: `app/Modules/CST/Queries/CostFigures.php` (`mayViewStockValues`), `app/Modules/INV/Http/Controllers/StockController.php`, `tests/Feature/INV/StockViewsTest.php`.
- Commit SHA: this branch's commits from `main`, the Owner's identity, no AI attribution
- Notes: **K-3's contract text with its tokens** — DESIGN_SYSTEM's §5.1, §5.2, §7, the shell row, the stage marker, §12.1's pill and Annex B GR-01–GR-04 as the Owner approved them (APPR-051) land in this pull request with `tokens.css`, the tokens test (TL-08) reading §5 as written; every value stays a working direction until the Owner's visual verdict (E8). **Tokens** — graphite for the primary action, cobalt for links, focus, selection and progress; `--color-text-muted-canvas` `#6f7074`; a text floor of 12 px and the title 20/28; the contrast of every text pair, control edge and the done marker held by a test (4.5:1 and 3:1). **The pale done token** `--brand-step-done-dot` (2.06:1 on white) draws nothing: the stage marker and the segmented indicator use cobalt (6.47:1) and no component names the token (the Owner's correction 3). **Skeleton (K-5)** — `RegionLoading` shows lines in the shape of its content after the spinner's 300 ms, its `role="status"` there from the first render and its one word said once, the shapes `aria-hidden` and still under reduced motion; the 43 deferred regions use it; a region the account may not read is never rendered through it (the server defers a group only to an account that may read it — INFORMATION_ARCHITECTURE §9; PJ-13), so no placeholder stands in for a figure without its capability. **D-8 #2** — the server sends a quantity whole ("100"); seventeen lines trimmed its trailing zeros with `/\.?0+$/`, fourteen of them without a guard, so "100" became "1"; one helper, `plainQuantity`, trims only after a decimal point and every call site uses it — the new test fails on `main` (no helper, seventeen sites) and passes here; no source value, rounding or calculation changed. **D-8 #6** — a link drawn as a button is cloned as a plain link and no longer typed `button`. Base UI kept: no dependency added, no `shadcn` command run. **Review resolution (P1, Codex, on `resources/js/components/region-state.tsx`)** — confirmed: PG-018 deferred its `values` group to every account and checked `cost.view` only when the deferred request resolved, so an account without it was shown a skeleton after 300 ms for figures it may not read, against DESIGN_SYSTEM §10 and K-5. `StockController::index` now asks `CostFigures::mayViewStockValues` (the same `cost.view` the figures require) and defers the group only to an account that holds it; any other receives `values` as an empty array on the first response, so the client has no pending deferred request and Inertia's `Deferred` renders its children at once, never its fallback — at any delay. The decision is the server's, not client-side hiding. A feature test proves both sides: without `cost.view`, `values` is on the page and not among `deferredProps`; with it, `values` stays deferred and arrives with the figures. The other deferred groups were inspected only to rule out the same defect: *Beranda*'s groups belong to a `review.view` route; *Pekerjaan* defers a group only when it is granted; the per-lot cost group of PG-019 draws no skeleton; none repeats it.
- Notes, DOD: each non-applicable item with its reason (DOD-03); the DONE update of TASK_PLAN, this file and CURRENT_HANDOFF (AICWDF §22.3)

**SAFE NEXT TASK:**

- TASK-099 once this Task is DONE

**DO NOT DO:**

- change a business rule, permission, calculation or stored value
- weaken, skip or delete an assertion to pass
- merge or rebase the pilot branch, or copy its `pilot.css` tokens or its Docker and CI files
- run `shadcn init`, `--force`, `--reinstall` or `add sidebar`, or add Radix, Vaul or Sonner
- touch TASK-078's files, staging or production, or start a deployment

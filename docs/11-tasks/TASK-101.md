# TASK-101 — Sales and billing on the redesigned patterns — quotations, commercial confirmation, invoices, billing, payments and customer credit

Status: APPROVED | Updated: 2026-11-28 | Owner: Planning

Approval: one of the seven Tasks of [TASK_PLAN §7](TASK_PLAN.md#7-controlled-task-plan-changes) Change 33, which the Owner approved explicitly ([APPR-052](../00-governance/DECISION_LOG.md#dir-148-and-appr-052--change-33-the-redesigns-seven-tasks-d-8s-minimum-corrections-and-the-release-freeze-exception-2026-11-28); DIR-148), within the approvals of CR-002's packages ([DECISION_LOG](../00-governance/DECISION_LOG.md#approval-register), APPR-044–APPR-051).

**STATUS:** TODO

**RISK CLASS:** STANDARD

**EXECUTOR:** AGENT

**OBJECTIVE:**

The pages of the sales and billing pages — quotations (TASK-033), commercial confirmation and fulfillment planning (TASK-034), invoices and the Kuitansi (TASK-049), billing and receivables (TASK-050), payments (TASK-051), settlements (TASK-052) and customer credit (TASK-053) follow the redesigned patterns — the foundation of TASK-098, the *Menu* of TASK-099 and the list pattern of TASK-100 — without a change of business behaviour, permission, calculation or datum: the same pages, routes, actions and rules, presented under the approved contract, each old specification reconciled against it.

**WHY THIS EXISTS:**

- [Change 33](../00-governance/DECISION_LOG.md#dir-148-and-appr-052--change-33-the-redesigns-seven-tasks-d-8s-minimum-corrections-and-the-release-freeze-exception-2026-11-28) bounds the redesign to seven Tasks; this one carries the pages named in its scope after the four pilot areas have set the patterns, grouped by workflow, never by visual tweak.
- The pages are shown the same way the pilot's areas are: labelled rows below their threshold, the foundation's tokens and states, the navigation's paths. A defect found on a page against its **old** contract is corrected minimally here and told apart from a change D-5, D-6 or the other approved packages newly authorize.

**DEPENDS ON:**

- TASK-034 — Commercial confirmation and fulfillment planning
- TASK-049 — Invoices and the pre-payment Kuitansi
- TASK-053 — Customer credit and refunds
- TASK-100 — The list pattern and the four pilot areas — labelled rows, the stage indicator and first step, the recovery codes' stage; Masuk, Beranda, Proyek and Stok (D-5; D-6; K-6; E5; D-8)

**BLOCKS:**

- TASK-104 — The redesign's gate — UI consistency, accessibility, localization, the responsive matrix, the release regression and the recorded validations (E6; E7; E8)

**PARALLEL SAFE:** NO — the one Autonomous Executor runs the redesign's Tasks in their order (DIR-138); they share the tokens, the components and the key catalogue

**IN SCOPE:**

- The sales and billing pages — quotations (TASK-033), commercial confirmation and fulfillment planning (TASK-034), invoices and the Kuitansi (TASK-049), billing and receivables (TASK-050), payments (TASK-051), settlements (TASK-052) and customer credit (TASK-053) — each page's list, form, detail and states on the patterns
- Region states: the skeleton after 300 ms and never for a region the account may not read; the empty, error and denied states of PT-19
- The page's copy in both languages; the path of each page as the NAV contract states it
- The old specifications of these pages reconciled against the new contract, each with its evidence — none weakened, skipped or deleted

**OUT OF SCOPE:**

- The purchasing, receiving and warehouse pages (TASK-102); administration, settings and reports (TASK-103)
- The document composer's renditions and the issued documents' layout (TASK-029; TASK-097)
- A change to a financial rule, a posting, a balance or an amount shown

**REFERENCES:**

- [APPR-045](../00-governance/DECISION_LOG.md#dir-141-and-appr-045--cr-002s-package-k-2-the-golden-frontend-reference-as-inspiration-2026-11-28); [APPR-051](../00-governance/DECISION_LOG.md#dir-147-and-appr-051--cr-002s-package-k-3-grafit--kobalt-as-the-working-direction-2026-11-28); [APPR-050](../00-governance/DECISION_LOG.md#dir-146-and-appr-050--cr-002s-package-k-4-the-three-level-menu-2026-11-28); [APPR-046](../00-governance/DECISION_LOG.md#dir-142-and-appr-046--cr-002s-package-k-5-skeletons-for-a-region-that-is-loading-2026-11-28); [APPR-048](../00-governance/DECISION_LOG.md#dir-144-and-appr-048--cr-002s-addition-d-5-a-wide-table-becomes-labelled-rows-below-its-threshold-2026-11-28); [APPR-049](../00-governance/DECISION_LOG.md#dir-145-and-appr-049--cr-002s-addition-d-6-the-arrangements-of-pg-010-pg-011-pg-018-and-pg-019-2026-11-28)
- DESIGN_SYSTEM §§5, 9, 10, 11, 14; INFORMATION_ARCHITECTURE §8 for each page; NAVIGATION_CONTRACTS
- The Tasks that built the pages (their contracts are the behaviour that does not change)

**EXPECTED MODULES / FILE AREAS:**

- `resources/js/Pages/` of the sales and billing modules (FIN and PRJ commercial pages)
- `resources/js/features/` of those pages
- `tests/Browser/`, `tests/Frontend/`; `lang/`

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

1. Every page of the scope shows its figures whole with their labels and units at every width, each table as labelled rows below its threshold and a table above it; an amount is never summed across companies or units (TASK-096's guards intact).
2. The path of each page is its NAV contract's; the primary action stays reachable on the phone (PT-03).
3. No financial figure, posting or balance changes; no page, route or capability is added or removed; every old specification of the scope is reconciled with its evidence.

**TEST REQUIREMENTS:**

- Frontend (TL-03): the changed components
- Browser E2E (TL-09): the pages' specifications, reconciled, on Chromium and WebKit
- Responsive (TL-11): the band edges and the labelled-rows threshold of each table
- Accessibility: axe on each page at 390 and 1,280 px
- Authorization: the route audit and every denial suite unchanged and green; Localization: both languages
- Production build: TL-13
- Migration: N/A

**TEST CHECKLIST:**

- [ ] Lint: Pint and ESLint in this head's CI (TL-01)
- [ ] Typecheck: PHPStan and TypeScript in this head's CI (TL-02)
- [ ] Relevant automated tests: the frontend and Browser tests above in this head's CI
- [ ] Authorization: the route audit (TL-07) and every denial suite unchanged and green
- [ ] Route integrity: N/A — no route added or changed
- [ ] Interaction behavior: the Browser tests above
- [ ] Browser E2E if required: on Chromium and WebKit (TL-09)
- [ ] Responsive/adaptive if required: the band edges and the labelled-rows thresholds (TL-11)
- [ ] Visual breathing room/density if UI: against §5 and §§9–10; the density decisions (the 12 px floor, the 20/28 title) the Owner's, at the walkthrough
- [ ] Production build: TL-13 in this head's CI
- [ ] Security checks if required: no capability change; nothing shown that the account may not read
- [ ] Database safety review: no schema change, no migration, no write path; production not touched
- [ ] Final diff review: ER-06's independent review within DIR-096's bounded loop

**COMPLETION EVIDENCE:**

- Test outputs (TEST_STRATEGY §16): this record's head, its fresh CI on GitHub's runner, every job required to PASS before the merge, the head containing `main` (DIR-118); the run named in the squash commit's message (DIR-127)
- Build result: TL-13 in the same run
- Route evidence: N/A — no route added or changed
- Interaction evidence: the Browser tests above
- Browser evidence: the Browser tests above on Chromium and WebKit, as the pull request's checks show before the merge
- Localization evidence: the changed templates in both languages
- Cost evidence/exception if applicable: ADDITIONAL RECURRING COST CHANGE: NONE
- Files changed: —
- Commit SHA: this branch's commits from `main`, the Owner's identity, no AI attribution
- Notes: —
- Notes, DOD: each non-applicable item with its reason (DOD-03); the DONE update of TASK_PLAN, this file and CURRENT_HANDOFF (AICWDF §22.3)

**SAFE NEXT TASK:**

- TASK-104 once TASK-101, TASK-102 and TASK-103 are DONE

**DO NOT DO:**

- change a business rule, a permission, a calculation or a stored value
- add a page, route or capability
- weaken, skip or delete a test to pass
- merge or rebase the pilot branch
- touch TASK-078's files, staging or production, or start a deployment

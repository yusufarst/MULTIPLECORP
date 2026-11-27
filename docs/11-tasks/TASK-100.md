# TASK-100 — The list pattern and the four pilot areas — labelled rows, the stage indicator and first step, the recovery codes' stage; Masuk, Beranda, Proyek and Stok (D-5; D-6; K-6; E5; D-8)

Status: APPROVED | Updated: 2026-11-28 | Owner: Planning

Approval: one of the seven Tasks of [TASK_PLAN §7](TASK_PLAN.md#7-controlled-task-plan-changes) Change 33, which the Owner approved explicitly ([APPR-052](../00-governance/DECISION_LOG.md#dir-148-and-appr-052--change-33-the-redesigns-seven-tasks-d-8s-minimum-corrections-and-the-release-freeze-exception-2026-11-28); DIR-148), within the approvals of CR-002's packages ([DECISION_LOG](../00-governance/DECISION_LOG.md#approval-register), APPR-044–APPR-051).

**STATUS:** TODO

**RISK CLASS:** HIGH

**EXECUTOR:** AGENT

**OBJECTIVE:**

The list pattern and the four pilot areas of the approved redesign are built on the foundation and the navigation: wide tables as PT-06's labelled rows below their threshold ([APPR-048](../00-governance/DECISION_LOG.md#dir-144-and-appr-048--cr-002s-addition-d-5-a-wide-table-becomes-labelled-rows-below-its-threshold-2026-11-28)), the arrangements of PG-010, PG-011, PG-018 and PG-019 ([APPR-049](../00-governance/DECISION_LOG.md#dir-145-and-appr-049--cr-002s-addition-d-6-the-arrangements-of-pg-010-pg-011-pg-018-and-pg-019-2026-11-28)), the stage indicator "Tahap n dari N" and the first step ([APPR-047](../00-governance/DECISION_LOG.md#dir-143-and-appr-047--cr-002s-package-k-6-the-stage-indicator-the-first-step-and-the-recovery-codes-stage-2026-11-28)), the recovery codes' stage (E5), and the four areas — *Masuk* with two-step verification, *Beranda*, *Proyek*, *Gudang › Stok* — with D-8's remaining corrections. No business behaviour, permission, calculation or datum changes.

**WHY THIS EXISTS:**

- The pilot demonstrated these areas on its isolated branch with synthetic data (APPR-042); this Task takes its validated code by copy and review, never by merge or rebase of the branch.
- **D-8 #1, verified in the code** — `Projects.tsx` ignores the data carried by the browser's history, so a list restored by a Back navigation can lose its stage; **D-8 #3–#5** (the phone's units on PG-018, the SKU, the rack unit) — the pilot's red evidence (S4-09, S4-18) is on the old bundle; each is verified against the old contract first, and a change D-5 and D-6 newly authorize is told from a defect of the old contract.
- The ten failures of the old specifications are reconciled in this and the later Tasks against the new contract with the traceable classification of the pilot's runs 45986909823 and 46002677044, none weakened, skipped or deleted.

**DEPENDS ON:**

- TASK-018 — Two-step verification (TOTP) and recovery codes
- TASK-032 — Projects — list, form, journey page and amendment
- TASK-040 — Stock views, stock history and movement reversal
- TASK-058 — Home, Work and Review — signals and the action queue
- TASK-099 — Navigation — the three-level Menu: the shell, rail, sidebar and phone menu, the 37 pages and the 65 NAV contracts (K-4)

**BLOCKS:**

- TASK-101 — Sales and billing on the redesigned patterns — quotations, commercial confirmation, invoices, billing, payments and customer credit
- TASK-102 — Purchasing, receiving and the other warehouse pages on the redesigned patterns
- TASK-103 — Administration, settings, records and reports on the redesigned patterns

**PARALLEL SAFE:** NO — the one Autonomous Executor runs the redesign's Tasks in their order (DIR-138); they share the tokens, the components and the key catalogue

**IN SCOPE:**

- The list pattern: `DataList` labelled rows (`denseBelow`) — a table from 768 px by default, 1,025 px for *Stok* and 1,200 px for the comparison of companies on *Beranda*; every figure whole, with its label and unit, none hidden; a long unit code and a long product name in the tests' data (a 320 px overflow is invisible without them)
- PG-010: six columns, the stage column behind the 300 ms rule; PG-011: one card with its stage and its one next step; the read-only stage track (a status, never a control — no focusable element, no pointer, no hover change); on the phone folded under "Tahap proyek"; PG-018: the scope line, the read time, the *Perhatian* column, the related warehouse pages from the navigation tree, the list before the company values on a phone; PG-019: the SKU and the unit
- The first step on *Beranda* for an Owner with no company — one heading, one sentence, one action, derived each time from the account's records and stored nowhere; never a percentage, a weighted bar, a hub tile or a greeting; the single empty-signal sentence
- E5: PG-004 shows the recovery codes once, on a stage titled "Simpan kode pemulihan", "Lanjutkan" open only after the account confirms; the codes in no `history.state`, no persistent storage, no log and no form memory; a reload and a Back navigation tested; no authentication rule weakened (the Owner's correction 5)
- D-8: #1 the stage of a list restored from history (red then green); #3–#5 as verified
- The pilot's Browser specs (`tests/Browser/Pilot/`) carried as Browser tests of the repository's layers; the old specifications of these areas reconciled

**OUT OF SCOPE:**

- Pages outside the four areas (TASK-101–TASK-103)
- A percentage, a weighted bar, a hub tile, a greeting, a guidance level, a resume memory or a walkthrough
- The "Per Produk / Per Rak" control and the pilot's other items outside D-6
- A change to a business rule, a permission, a calculation or a stored value

**REFERENCES:**

- [APPR-048](../00-governance/DECISION_LOG.md#dir-144-and-appr-048--cr-002s-addition-d-5-a-wide-table-becomes-labelled-rows-below-its-threshold-2026-11-28); [APPR-049](../00-governance/DECISION_LOG.md#dir-145-and-appr-049--cr-002s-addition-d-6-the-arrangements-of-pg-010-pg-011-pg-018-and-pg-019-2026-11-28); [APPR-047](../00-governance/DECISION_LOG.md#dir-143-and-appr-047--cr-002s-package-k-6-the-stage-indicator-the-first-step-and-the-recovery-codes-stage-2026-11-28); [APPR-046](../00-governance/DECISION_LOG.md#dir-142-and-appr-046--cr-002s-package-k-5-skeletons-for-a-region-that-is-loading-2026-11-28)
- DESIGN_SYSTEM PT-05, PT-06, PT-19, PT-29, PT-31, §10; INFORMATION_ARCHITECTURE §§8.1, 9; AUTH_JOURNEYS; the classification of the ten old failures of the pilot's runs 45986909823 and 46002677044, which this Task commits to its gate record so that it is traceable from the repository
- The pilot (read only): `record-blocks.tsx` (`StageTrack`), `Stock.tsx`, `Projects.tsx`, `Beranda.tsx`, `TotpEnrolment.tsx`

**EXPECTED MODULES / FILE AREAS:**

- `resources/js/components/` (the list pattern, the stage track, the region states)
- `resources/js/Pages/Auth/`, `Reporting/Beranda.tsx`, `PRJ/Projects.tsx`, `PRJ/Journey.tsx`, `INV/Stock.tsx`, the product page
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

- Authentication change: NONE — the recovery-code stage's presentation and its no-storage rule only; no authentication rule is weakened
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

1. At 320, 390, 768, 820, 1,024, 1,025, 1,200 and 1,440 px each of the four areas shows its labelled rows or its table where the contract says, with no sideways scroll and every figure whole; *Stok* switches at 1,025 px and the comparison of companies at 1,200 px.
2. "Tahap n dari N" reads the server's own numbers; the stage track is a status — nothing in it can be focused, clicked or mistaken for a control; a Back navigation to the list restores it with its stage (D-8 #1, red then green).
3. An Owner with no company sees the first step and no figure; an account sees no action it holds no capability for; no region the account may not read shows a skeleton.
4. The recovery codes are shown once on their own stage; "Lanjutkan" opens only after the confirmation; after a reload or a Back navigation they are gone and not recoverable from the browser (`history.state`, storage, logs, form memory); the sign-in and two-step rules behave as before.
5. PG-018 and PG-019 show the SKU and the unit at every width; the D-8 #3–#5 findings are each closed as a defect of the old contract or as D-5/D-6's new presentation, with evidence.
6. The old specifications of these areas are reconciled against the new contract, each with its evidence.

**TEST REQUIREMENTS:**

- Frontend (TL-03): the list pattern, the stage track, the first step's derivation
- Browser E2E (TL-09): the four areas on Chromium and WebKit, including the pilot's specs ported; the recovery-code stage under reload and Back; the history-restored list
- Responsive (TL-11): the eight widths; long unit code and product name
- Accessibility: axe on the four areas at 390 and 1,280 px; the stage track's non-interactivity
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

- TASK-101, TASK-102 and TASK-103 once this Task is DONE

**DO NOT DO:**

- store a recovery code anywhere in the browser
- draw a stage as something a reader could take for a control
- add a percentage, weighted bar, hub tile or greeting
- weaken, skip or delete an old specification instead of reconciling it
- merge or rebase the pilot branch
- touch TASK-078's files, staging or production, or start a deployment

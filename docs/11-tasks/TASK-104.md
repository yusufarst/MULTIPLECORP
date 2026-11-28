# TASK-104 — The redesign's gate — UI consistency, accessibility, localization, the responsive matrix, the release regression and the recorded validations (E6; E7; E8)

Status: APPROVED | Updated: 2026-11-28 | Owner: Planning

Approval: one of the seven Tasks of [TASK_PLAN §7](TASK_PLAN.md#7-controlled-task-plan-changes) Change 33, which the Owner approved explicitly ([APPR-052](../00-governance/DECISION_LOG.md#dir-148-and-appr-052--change-33-the-redesigns-seven-tasks-d-8s-minimum-corrections-and-the-release-freeze-exception-2026-11-28); DIR-148), within the approvals of CR-002's packages ([DECISION_LOG](../00-governance/DECISION_LOG.md#approval-register), APPR-044–APPR-051).

**STATUS:** TODO

**RISK CLASS:** HIGH

**EXECUTOR:** AGENT

**OBJECTIVE:**

The redesign passes its gate: the UI consistency gate, WCAG 2.2 AA on every page, localization completeness, the responsive matrix, the performance budgets and the full release regression run again on the redesigned application, the ten failures of the old specifications reconciled with traceable evidence, and the recorded validations that CR-002 keeps as mandatory gates — the novice validation of the *Menu* and its labels (E6, E7), the real-device and screen-reader checks and the Owner's visual verdict on the palette and type scale (E8) — each recorded as it happens and none taken as passed.

**WHY THIS EXISTS:**

- [Change 33](../00-governance/DECISION_LOG.md#dir-148-and-appr-052--change-33-the-redesigns-seven-tasks-d-8s-minimum-corrections-and-the-release-freeze-exception-2026-11-28) requires every release gate to hold for the approved redesign and the release freeze's exception to end in the gate; [APPR-050](../00-governance/DECISION_LOG.md#dir-146-and-appr-050--cr-002s-package-k-4-the-three-level-menu-2026-11-28) and [APPR-051](../00-governance/DECISION_LOG.md#dir-147-and-appr-051--cr-002s-package-k-3-grafit--kobalt-as-the-working-direction-2026-11-28) were approved ahead of their validations, which therefore stay open until this Task records them.
- The UI gate is measured against DESIGN_SYSTEM §5 and §§9–10 alone (K-2); the reference is no longer a measure.

**DEPENDS ON:**

- TASK-101 — Sales and billing on the redesigned patterns — quotations, commercial confirmation, invoices, billing, payments and customer credit
- TASK-102 — Purchasing, receiving and the other warehouse pages on the redesigned patterns
- TASK-103 — Administration, settings, records and reports on the redesigned patterns

**BLOCKS:**

- TASK-086 — UAT with the Owner and the validators

**PARALLEL SAFE:** NO — the one Autonomous Executor runs the redesign's Tasks in their order (DIR-138); they share the tokens, the components and the key catalogue

**IN SCOPE:**

- The UI/UX consistency gate, the responsive matrix and the anti-slop audit of TASK-065 re-run on the redesigned application against the amended contract; every SLOP rule kept except SLOP-44 as amended
- Accessibility across every page (WCAG 2.2 AA) including non-text contrast per surface (1.4.11), high-contrast mode and colour-blindness; localization completeness and the per-page copy lists, the *proposed* copy listed with its status; performance targets and query budgets (QB-01, QB-10 for *Beranda*)
- The full release regression and the device UAT's preparation re-run; the security test matrix, route integrity and zero dead interactions green
- The ten failures of the old specifications reconciled with the classification of the pilot's runs 45986909823 and 46002677044 — updated against the new contract, none weakened, skipped or deleted
- The recorded validations: the novice validation (E6, E7) of the *Menu*, its label and the group labels, with the result and the one Level 1 edit that follows; the real-device checks and the screen-reader checks; the Owner's visual verdict (E8), its final values set as one-row Level 3 edits — each recorded in the gate record with its date and evidence; an item that has not happened is recorded as **not done**, never as passed
- The gate record of this Task, and TASK_PLAN, this Task and CURRENT_HANDOFF updated at DONE

**OUT OF SCOPE:**

- A new feature, page, route or capability
- The production cutover, GO-LIVE and operations (TASK-084–TASK-087)
- A change to the approved contract other than the validations' recorded edits

**REFERENCES:**

- [APPR-051](../00-governance/DECISION_LOG.md#dir-147-and-appr-051--cr-002s-package-k-3-grafit--kobalt-as-the-working-direction-2026-11-28); [APPR-050](../00-governance/DECISION_LOG.md#dir-146-and-appr-050--cr-002s-package-k-4-the-three-level-menu-2026-11-28); [Change 33](../00-governance/DECISION_LOG.md#dir-148-and-appr-052--change-33-the-redesigns-seven-tasks-d-8s-minimum-corrections-and-the-release-freeze-exception-2026-11-28)
- TASK-065's gate record; TASK-066, TASK-067, TASK-068, TASK-070; TEST_STRATEGY §9; DIR-134 E6–E9
- The usability kit (`docs/07-ux-design/stage2/USABILITY_KIT.md`) for the novice validation

**EXPECTED MODULES / FILE AREAS:**

- `docs/08-testing/evidence/` — the redesign's gate record
- `tests/Browser/`, `tests/Frontend/`; the CI workflow only for the jobs that already exist
- `lang/`; `docs/07-ux-design/` for the recorded edits

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

1. Every mandatory CI job is green on the head, including the Browser aggregate on Chromium and WebKit; the UI, accessibility and localization gates and the release regression pass on the redesigned application.
2. Every page reaches WCAG 2.2 AA with the 1.4.11 contrast measured per surface; every text pair 4.5:1 and every control edge, focus ring, mark and bar 3:1.
3. The ten failures are each reconciled with traceable evidence; no assertion is weakened, skipped or deleted anywhere in the redesign's Tasks.
4. E6 and E7 are recorded — the participants, the tasks, the result for *Menu* and each group label, the one Level 1 edit that follows — or recorded as not done with the Task not DONE; the real-device and screen-reader checks likewise; E8 is recorded as the Owner's verdict with the final values.
5. No business behaviour, permission, calculation or datum changed anywhere in the redesign.

**TEST REQUIREMENTS:**

- Every layer of TEST_STRATEGY as the release regression defines it
- Browser E2E (TL-09), Responsive (TL-11), Accessibility, Localization, Performance, Security matrix: all green on the head
- The validations' records as evidence documents — not automated
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

- TASK-086 (UAT with the Owner and the validators) once its other dependencies allow

**DO NOT DO:**

- record a validation that has not happened as passed, or delete E6, E7 or E8 as a gate
- weaken, skip or delete a test
- add a feature or change the contract beyond the validations' recorded edits
- touch TASK-078's files, staging or production, or start a deployment

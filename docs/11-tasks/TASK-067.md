# TASK-067 — Localization completeness and the per-page copy lists

Status: APPROVED | Updated: 2026-10-20 | Owner: Planning

Approval: [APPR-016](../00-governance/DECISION_LOG.md#appr-016--p11-task-plan-tasks-and-feature-coverage-matrix-approved), explicit Owner approval on 2026-10-20 (DIR-085 OF-07) of this document as committed in the first execution session's approval commit; the approved file hash and the verified conditions are recorded there. Approval changes lifecycle only, not implementation authorization.

Written by WF-PC5-W's writer of bundle W3 of run PC-5 on 2026-10-19 and 2026-10-20 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99; the Owner approved P11 with the [Task plan](TASK_PLAN.md) at OG-4 (DIR-085 OF-07). **No Task starts before the freeze record, from which the execution authorization is in force (DIR-085 OF-09).** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

Amendment: on 2026-11-14 the Owner approved, under [APPR-032](../00-governance/DECISION_LOG.md#dir-116-and-appr-032--task-067s-four-minimal-product-fixes-of-2026-11-14) (DIR-116; TASK_PLAN Change 21), the minimal fix inside this Task of the four product defects its audit found — A, the navigation entry *Aktivitas & Keamanan* cut at 1280 px (TASK-016); B, PG-052's English title cut at 390 px (TASK-060); C, PG-017's "Cancel" target at 390 px (TASK-037), once confirmed; D, PG-013's "Mulai Penomoran" after MSG-17 (TASK-095) —, with the narrowly scoped laravel/framework security patch; the owners stay DONE and every acceptance criterion and release requirement is kept.

**STATUS:** DONE

**RISK CLASS:** STANDARD

**EXECUTOR:** AGENT

**OBJECTIVE:**

Prove localization on every built page in both languages and compile the per-page copy lists for the Owner: no missing string over LOCALIZATION's key inventory and every key the P11 Tasks added; the layout after a language change; what stays Indonesian — documents, business data, formats —; Indonesian by default; and the Owner's rule for copy written after APPR-011 applied page by page — a new key accepted by construction or marked proposed and listed with its page's review —, each defect sent to the Task that owns the page.

**WHY THIS EXISTS:**

CAP-14 and AC-14 require natural Indonesian by default, English through a switch and no uncontrolled hardcoded copy, with documents and business data in Indonesian (DIR-037 D7; LOCALIZATION §1); GAP-037 stays OPEN for P8's localization tests and the per-page copy review, and LOCALIZATION §5 records the Owner's OB-04 rule for the copy P11 writes. Every UI Task checks both languages on its own pages (DOD-16); this Task proves the whole set — the 889 keys, the keys added since, and every page — and gives the Owner one review list per page (TEST_STRATEGY §10, §12).

**DEPENDS ON:**

- TASK-004 — Verification and release tooling check
- TASK-023 — My account — password, two-step verification, Google link and language
- TASK-024 — Users and access — accounts, roles, capabilities and company grants
- TASK-056 — Project cancellation
- TASK-057 — Opening import — preparation, dry run, sign-off and commit
- TASK-058 — Home, Work and Review — signals and the action queue
- TASK-059 — Search and scanning
- TASK-060 — Reports, the consolidated report and exports
- TASK-061 — Activity history and security log

**BLOCKS:**

- TASK-070 — Full release regression and the device UAT's preparation

**PARALLEL SAFE:** NO — it reads every page and the string files every UI Task writes, and its findings reopen those Tasks

**IN SCOPE:**

- **no missing string** (LOCALIZATION §3.6; TEST_STRATEGY §10): every key a page uses present in both string files with its named placeholders, read from the inventory and the string files, never sampled — the 889 keys of LOCALIZATION §6, 881 validated and 8 data or never translated, and every key the P11 Tasks added —; a page failing on a key shown as itself, an empty string, an unfilled placeholder, a hard-coded user-facing string, a sentence of concatenated pieces, or an Indonesian interface string on an English page other than §3.4's values, names and document type names
- **the layout after a language change** on every page at the phone and desktop widths: no horizontal scroll except in a data table, no clipped or truncated label, targets at their sizes, the same regions and actions in the same order with the same primary action in both languages (LOCALIZATION §3.7); the switch keeping the page, its data and its unsent input, and the language kept in `users.locale` across a re-login (WFL-04; `DIR-077` CA-08)
- **what stays Indonesian**: generated and issued documents, their renditions, an export and its headers, business data and the paper of a draft's preview, whatever the interface language (LOCALIZATION §3.4; DIR-037 D7; AC-14); dates, numbers and money in one format in both languages (OQ-04 (a)); a counted English string with its two forms (§3.3)
- **Indonesian by default**: a new account and one without a stored language see Indonesian; a visit to the sign-in pages starts in Indonesian, the pre-login choice living only in the `bahasa` address key and never written to the account (LOCALIZATION §2)
- **copy written after APPR-011** (`DIR-081` OB-04; LOCALIZATION §5): each page's new keys gathered from the DOD-16 localization evidence of the Task that built the page; a key accepted by construction when its Indonesian is a GL term or a label of an approved source and its English that term's or label's validated rendering; any other new key marked proposed and listed for the Owner with its page's P8 review, accepted except the keys the Owner marks; a marked key corrected before its release (TEST_STRATEGY §10)
- the per-page copy lists — every page's new keys with their Indonesian, English, kind and source — compiled for the Owner's review at the UAT (TEST_STRATEGY §12; RELEASE_PLAN §8), with LOCALIZATION §5.1's 881 validated keys kept validated and each new key's state recorded
- the tool that finds a missing key, chosen and verified here (LOCALIZATION §3.6; DEP-07)
- the language items of AICWDF §27 — Indonesian default, plain Indonesian, English available, missing translations 0, language switch — per page, recorded as this Task's evidence, which TASK-070 enters in TASK-065's gate record
- each defect recorded with its page, key, evidence and proposed minimum fix and sent through the executor feedback loop to the Task that owns it — a DONE Task's regression becoming a new defect Task under TASK_PLAN §7 (AGENT_OPERATING_MODEL)

**OUT OF SCOPE:**

- writing or translating a page's copy — the page's own Task; changing a validated key's text — Planning's, under LOCALIZATION §5
- the Owner's marking of proposed keys and the UAT itself (TASK-086); CJ-18's journey (TASK-064)
- translating business data or a document — never done (LOCALIZATION §3.4)

**REFERENCES:**

- [LOCALIZATION §1](../07-ux-design/LOCALIZATION.md#1-the-localization-contract); [§2](../07-ux-design/LOCALIZATION.md#2-locales-the-choice-and-where-it-is-kept); [§3.3](../07-ux-design/LOCALIZATION.md#33-formats); [§3.4](../07-ux-design/LOCALIZATION.md#34-what-never-translates); [§3.6](../07-ux-design/LOCALIZATION.md#36-both-languages-no-missing-string); [§3.7](../07-ux-design/LOCALIZATION.md#37-the-layout-after-a-language-change); [§4](../07-ux-design/LOCALIZATION.md#4-copy-rules); [§5](../07-ux-design/LOCALIZATION.md#5-copy-validation-ca-04-items-710); [§5.1](../07-ux-design/LOCALIZATION.md#51-the-review-list-cb-15-cd-07); [§6](../07-ux-design/LOCALIZATION.md#6-key-inventory); [§7](../07-ux-design/LOCALIZATION.md#7-counts)
- [TEST_STRATEGY §10](../08-testing/TEST_STRATEGY.md#10-localization); [§2](../08-testing/TEST_STRATEGY.md#2-test-layers) TL-10; [§12](../08-testing/TEST_STRATEGY.md#12-devices-and-uat) the per-page lists at UAT; [§16](../08-testing/TEST_STRATEGY.md#16-evidence)
- [V1_SCOPE](../01-product/V1_SCOPE.md#must-ship) CAP-14; [ACCEPTANCE_CRITERIA](../01-product/ACCEPTANCE_CRITERIA.md#product-acceptance-contracts) AC-14; [INFORMATION_ARCHITECTURE §3](../07-ux-design/INFORMATION_ARCHITECTURE.md#3-glossary) the glossary; [§8](../07-ux-design/INFORMATION_ARCHITECTURE.md#8-screen-inventory) the 65 pages
- [RELEASE_PLAN §8](../10-release/RELEASE_PLAN.md#8-uat); [DEFINITION_OF_DONE §2](../08-testing/DEFINITION_OF_DONE.md#2-the-checklist) DOD-16
- [GAP_REGISTER](../00-governance/GAP_REGISTER.md#gap-037--bilingual-ui-obligations-are-not-yet-designed-or-tested) GAP-037; [AGENT_OPERATING_MODEL](../00-governance/AGENT_OPERATING_MODEL.md#executor-feedback-loop)
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (8): O-07; CAP-14; AC-14; IMG-09; TL-10; l10n-rule LOCALIZATION §5; l10n-rule LOCALIZATION §5.1; GAP-037

**EXPECTED MODULES / FILE AREAS:**

- the missing-key check over the string files and the inventory, the two-language page runs and layout checks in the browser suite, and the per-page copy lists in the test evidence area TASK-003 set up; no string file changed, and no application code but the four minimal corrections APPR-032 approves (defects A–D, their owners TASK-016, TASK-060, TASK-037 and TASK-095) with their regression tests, and the lockfile's laravel/framework security patch

**BUSINESS RULES:**

- DIR-037 D7: generated or issued business documents and business data stay Indonesian; names, codes, numbers and user-entered text are data and are never translated (LOCALIZATION §3.4)
- the Owner's OB-04 rule for copy written after APPR-011, quoted in LOCALIZATION §5, applied as written — never extended or relaxed here

**ARCHITECTURE RULES:**

- the Indonesian string is the source and the English one renders it; no English string without its Indonesian source and no copy stating a rule, figure or business meaning its source does not give (LOCALIZATION §3.2)
- the checks run on the built pages and the application's string files, never on the prototype's copy tables (LOCALIZATION §3.6)

**SECURITY / PERMISSION REQUIREMENTS:**

- each page checked as the personas who reach it, both languages for each; the pre-login choice never stored on the device — no cookie, no browser storage — and never written to the account (LOCALIZATION §2; AU-08; GR-14)
- synthetic data only; no secret in the evidence (DOD-21)

**COMPANY SCOPE AND FIELD PROJECTION:**

- N/A — the checks concern copy and layout; scope and projection are TASK-069's matrix

**CONCURRENCY / IDEMPOTENCY:**

- N/A — no command is built or raced; the account's own language change is RB-10's request, checked by CJ-18 (TASK-064)

**AUDIT:**

- N/A — the checks write no business record; the account's language change is audited by its own Task (RG-11; TASK-023)

**DATABASE IMPACT ASSESSMENT:**

- Schema change: NONE — test data only
- Migration required: NO
- Existing data transformation: NONE
- Direct production DB access: FORBIDDEN
- Data-loss risk: NONE KNOWN

**MODULARITY / EXTENSIBILITY:**

- Module boundary affected: none — test code and copy lists only
- Stable core kept provider-neutral: YES
- External/vendor-specific code isolated: YES — the missing-key tool stays in the test tree
- Replaceable adapter/contract required: NO
- Provider lock-in risk: NONE — a free tool verified under DEP-07
- Future extension impact: LOW — a further key or page joins the check by the inventory and the string files
- Speculative abstraction avoided: YES — no translation-management service

**AUTH IMPACT:**

- Authentication change: NONE
- Authentication implementation: LARAVEL_NATIVE — decided once ([EXECUTION_CONTEXT](EXECUTION_CONTEXT.md)), reused, never redesigned
- Auth profile: N/A — the sign-in pages are checked for their copy only
- Google sign-in: N/A — PG-001's Google control checked for its copy only
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

**MCP SESSION PROFILE:**

- Required MCP servers: none — [TOOLCHAIN](../00-governance/TOOLCHAIN.md#mcp-servers) records none installed
- MCP servers that should remain OFF: every server not required
- Native CLI/package preferred over MCP: YES — the framework's and package managers' own commands
- Post-Task deactivation/cleanup: none

**TOOL REQUIREMENTS:**

- Context7: REQUIRED — the framework's translation files, `trans_choice` and named placeholders, and how the strings reach React as LOCALIZATION §3.8 leaves to P11, at the installed versions
- Graphify: OPTIONAL — to find every component that renders a literal string
- Browser E2E: REQUIRED — every page rendered in both languages in the browser suite
- Other: a missing-key and hard-coded-string checker chosen and verified here (LOCALIZATION §3.6; DEP-07)
- Missing safe tool behavior: AUTO-INSTALL project-locally inside this Task (AICWDF §6.3) and record it in TOOLCHAIN; a paid tier or a privileged installation STOPS for the Owner (COST_POLICY)

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: refreshed before the run when Graphify is available ([TOOLCHAIN](../00-governance/TOOLCHAIN.md)); otherwise a search of the frontend for literal user-facing strings
- Pre-change questions: which keys each page uses; which Task added each new key
- Expected affected areas: the test tree and the copy lists only; a defect's fix lands in its owning Task

**CONTEXT7:**

- Topics to verify: the framework's JSON and PHP translation loading, pluralization ranges and missing-key handling
- Version-sensitive assumptions: none from memory

**UI / UX REQUIREMENTS:**

- Follow global design rules: YES — the language rules of EXECUTION_CONTEXT checked on every page
- Indonesian default: YES — checked for new accounts and the sign-in pages
- English support: YES — every page in English with no missing string
- Plain-language Indonesian: YES — the new keys checked against the glossary and LOCALIZATION §4's copy rules
- Language switch impact: checked — the page, its data and its unsent input kept, the layout re-checked (LOCALIZATION §3.7)
- Mobile-first: YES — both languages at the phone width
- Responsive: YES — both languages at the phone and desktop widths; the full band matrix is TASK-065's
- Adaptive: N/A — TASK-065 judges the compositions; the regions and actions are compared between languages here
- Visual breathing room: N/A — TASK-065's gate
- Comfortable density: N/A — TASK-065's gate
- shadcn/ui reuse: N/A — no component is built
- Design reference: N/A — the checks judge against LOCALIZATION, not a design reference
- Primary action: checked — the same primary action in both languages (LOCALIZATION §3.7)
- Secondary actions: N/A — no page
- Destructive actions: N/A — no page
- Redundancy constraints: N/A — TASK-065 counts duplicates
- Loading state: N/A — no page; the loading copy checked as keys
- Empty state: N/A — no page; the signals' empty sentences checked as keys in both languages
- Error state: N/A — no page; the MSG- texts checked as keys in both languages

**ROUTE IMPACT:**

- none — no route is added or changed; the checks request every page's view route in both languages

**INTERACTION CONTRACTS:**

- N/A — no INT- contract is built; INT-001's and INT-002's switches are exercised as their contracts state (TASK-014, TASK-023)

**NAVIGATION CONTRACT:**

- Parent: N/A — no page; NV-07's `bahasa` key between the sign-in pages checked
- Entry: N/A — no page
- Back action: N/A — no page
- Cancel: N/A — no page
- Success destination: N/A — no page
- Error behavior: N/A — no page
- State preservation: N/A — no page; the unsent input across the switch checked as above
- Mobile behavior: N/A — no page

**ACCEPTANCE CRITERIA:**

1. Every key every page uses exists in both languages with its named placeholders — the 889 keys of the inventory and every key the P11 Tasks added —, and no page shows a key as itself, an empty string, an unfilled placeholder, a hard-coded string or a concatenated sentence; missing translations: 0 (LOCALIZATION §3.6; TEST_STRATEGY §10).
2. On an English page no Indonesian interface string appears other than LOCALIZATION §3.4's values, names and document type names (LOCALIZATION §3.6).
3. Every page in both languages keeps its regions, actions and primary action in the same order, with no clipped label and no horizontal scroll outside a table (LOCALIZATION §3.7).
4. The switch keeps the page, its data and its unsent input, and the account's language comes back after a re-login (LOCALIZATION §3.7; `DIR-077` CA-08).
5. A document, its rendition, an export and a draft's paper stay Indonesian with the interface in English, and dates, numbers and money read the same in both languages (DIR-037 D7; OQ-04 (a)).
6. A new account and one without a stored language see Indonesian; a pre-login choice is stored nowhere and never written to the account (LOCALIZATION §2).
7. Every page has its list of new keys, each accepted by construction under OB-04 or marked proposed for the Owner's review with the page, and no key the Owner marks is released uncorrected (LOCALIZATION §5; `DIR-081` OB-04).
8. Every defect found is sent to its owning Task with its evidence; no key's text is changed in this Task (AGENT_OPERATING_MODEL).

**TEST REQUIREMENTS:**

- Lint: TL-01 on the checker's code
- Typecheck: TL-02 on the checker's code
- Unit: the checker's own rules — a missing key, an unfilled placeholder, a literal string (TL-03)
- Feature: N/A — no route or command in this Task
- Integration: N/A — no database, queue or job path in this Task
- Authorization: N/A — pages rendered as the personas; the denial suites are TASK-069's
- Route integrity: N/A — the crawl is TASK-069's
- Interaction: INT-001 and INT-002 exercised as their contracts state (TL-08's contracts)
- Browser E2E: every page in both languages in the browser suite (TL-09's harness)
- Localization/language switch: TL-10 — TEST_STRATEGY §10's checks on every page
- Visual breathing room/density: N/A — TASK-065's gate
- Responsive: both languages at the phone and desktop widths (TL-11's widths)
- Accessibility: N/A — TASK-066's audit, which checks `lang`
- Production build: TL-13 for the checker's code; the pages from the CI stack's build
- Security: N/A — TASK-069's matrix; the suite's secret-like scan (SM-15)
- Performance: N/A — TASK-068's budgets
- Migration: N/A — no migration or import code in this Task
- Other: the per-page copy lists handed to the UAT (TEST_STRATEGY §12)

**TEST CHECKLIST:**

- [x] Lint: ESLint on the checker, the generator, the audit and the specs in this head's CI (TL-01); no PHP changed
- [x] Typecheck: TypeScript over the browser and frontend test trees in this head's CI (TL-02)
- [x] Relevant automated tests: `tests/Frontend/i18n-keys.test.ts` — the missing-key tool and the concatenated-sentence check, each with its own rules' cases —; `tests/Frontend/copy-lists.test.ts` — the generator's rules on fixtures, every page's list written and checked, a stale list failing, a page no Localization spec audits failing —; with `tests/Unit/Localization/KeyCatalogueTest.php` — LOCALIZATION §6's keys in both languages with §6's texts, every added key through the registry — and TASK-014's `multiplecorp/hardcoded-copy` rule (TL-03; TL-01)
- [x] Authorization: N/A — no permission changed. Each page is checked as the personas who reach it: rendered as the Owner, whose role carries every capability of the catalogue (PERMISSIONS_MATRIX RG-02), so every control a persona can reach is shown, and the states only an account without access sees — PG-005, and PG-007 with MSG-38 — by `without-access.spec.ts`; the denial suites are TASK-069's
- [x] Route integrity: N/A — no route added or changed; the crawl is TASK-069's
- [x] Interaction behavior: INT-001 — `tests/Browser/Localization/sign-in.spec.ts`, `tests/Browser/E2E/language-switch.spec.ts` — and INT-002 — `tests/Browser/E2E/own-account.spec.ts`, CJ-18 (TASK-064) — as their contracts state, unchanged
- [x] Browser E2E if required: the 46 Localization specs, every page PG-001–PG-065 in both languages on Chromium and WebKit (TL-10), in this head's CI
- [x] Responsive/adaptive if required: each page in both languages at its spec's desktop width and at 390 px — no horizontal scroll outside a table, no clipped label, targets at their sizes, the same regions and actions in the same order (LOCALIZATION §3.7); the band matrix is TASK-065's
- [x] Visual breathing room/density if UI: N/A — TASK-065's gate
- [x] Production build: TL-13 in this head's CI
- [x] Security checks if required: synthetic data only, no secret in the evidence (DOD-21); the pre-login choice stored nowhere and never written to the account — `tests/Feature/LocaleTest.php`, `tests/Browser/E2E/language-switch.spec.ts`
- [x] Database safety review: no schema change, no migration, no write path but the fixtures' synthetic runs; production not touched
- [x] Final diff review: Codex's review of this pull request before the merge (DIR-096's bounded loop)

**COMPLETION EVIDENCE:**

- Test outputs (TEST_STRATEGY §16): this record's head, its fresh CI on GitHub's `ubuntu-24.04` runner — the push run and the pull request's run —, every job required to PASS before the merge, as the pull request's checks show (DIR-103)
- Build result: TL-13 PASS in the same runs
- Route evidence: N/A — no route added or changed
- Interaction evidence: N/A — no INT- contract built; INT-001 and INT-002 exercised by their existing tests, named above
- Browser evidence: `E2E: PASS — Evidence: this head, CI ubuntu-24.04, the localization layer — tests/Browser/Localization/*.spec.ts, 46 specs on Chromium and WebKit, every page PG-001–PG-065 audited in both languages by tests/Browser/localization-audit.ts — in the run's browser-evidence-localization artifact`
- Localization evidence: every page in both languages at the desktop width and at 390 px (above); the per-page copy lists — each page's new keys with their state under OB-04 and its language items of AICWDF §27 with the checks behind each — in the run's `verify-evidence` artifact, `storage/logs/verify/copy-lists`, for the Owner's review at the UAT (TEST_STRATEGY §12) and for TASK-070, which enters the language items in TASK-065's gate record
- Cost evidence/exception if applicable: ADDITIONAL RECURRING COST CHANGE: NONE — the project's own Node.js test runner, TypeScript, Playwright and axe-core, nothing installed
- Files changed: the four corrections APPR-032 approves — `resources/js/components/shell/navigation.tsx` (A), `resources/js/components/page-header.tsx` (B), `resources/js/components/action-bar.tsx` and `resources/js/layouts/AppShell.tsx` (C), `resources/js/lib/use-command.ts` and `resources/js/Pages/PRJ/ProjectForm.tsx` (D) —; `composer.lock` (laravel/framework 13.40.0 for GHSA-63hm-g96r-2m2q); `scripts/i18n/copy-lists.mjs`; `tests/Frontend/i18n-keys.test.ts`; `tests/Frontend/copy-lists.test.ts`; `tests/Browser/localization-audit.ts`; `tests/Browser/Localization/*.spec.ts` — the 45 existing specs with their pages' audits, PG-041 added to `invoices.spec.ts`, `without-access.spec.ts` new for PG-005 and PG-007 without a company grant —; `docs/00-governance/TOOLCHAIN.md`; `docs/00-governance/DECISION_LOG.md`, `docs/00-governance/sources/EXECUTION_OWNER_DIRECTIVE_TASK-071-EVIDENCE_2026-11-13.txt` and `docs/handoff/OPERATOR_INPUT_REQUEST.md` (DIR-115); `docs/00-governance/sources/EXECUTION_OWNER_DECISION_TASK-067-MINIMAL-FIXES_2026-11-14.txt` (DIR-116, APPR-032); `docs/11-tasks/TASK_PLAN.md` (Change 21), this file; `docs/handoff/CURRENT_HANDOFF.md`
- Commit SHA: this branch's commits on `task/TASK-067-localization-completeness`, `main` merged in — never rebased —, the Owner's identity, no AI attribution
- Notes: criteria 1–8 PASS. **1** — every key the interface's code names literally is in both string files, not empty, with the same named placeholders, and no sentence is built from pieces (`tests/Frontend/i18n-keys.test.ts`); LOCALIZATION §6's keys are in both languages with §6's texts and every added key came through the registry (`KeyCatalogueTest`); no hard-coded string (`multiplecorp/hardcoded-copy`); on every rendered page no key shown as itself, no control without a name and no unfilled placeholder (the audit) — missing translations: 0. **2** — on every English page no Indonesian interface string beyond LOCALIZATION §3.4's values, names, document type names and a document's paper (the audit, which also finds the server's Indonesian answer to a missing English key). **3** — every page in both languages, at its spec's desktop width and at 390 px: the same landmarks, headings, actions and primary action in the same order, no clipped label, no horizontal scroll outside a table, targets at their sizes (the audit; axe's WCAG 2.5.8 rule). **4** — INT-001's switch keeps PG-001's typed e-mail and never its password, and PG-003's token (`Localization/sign-in.spec.ts`); INT-002's keeps the page, its data and its unsent input, and the language returns after a re-login (`E2E/own-account.spec.ts`; CJ-18, TASK-064). **5** — the paper of every draft and issued document stays Indonesian with the interface in English (the Localization specs that assert it, the audit skipping only the paper), a rendition (`Localization/files.spec.ts`; `LocaleTest`'s §3.4 case), an export with its header row (`tests/Integration/Reporting/ExportJobTest.php`); dates, numbers and money in one format in both languages (`tests/Frontend/format.test.ts`, LOCALIZATION §3.3's table; OQ-04 (a)). **6** — `tests/Feature/LocaleTest.php`: Indonesian without a stored language and before any choice, the `bahasa` address key stored nowhere and never written to the account; `E2E/language-switch.spec.ts`: no cookie and no browser storage. **7** — the per-page copy lists of the 65 pages, with the shell's and the unmapped keys: 3,462 new keys — 222 accepted by construction under OB-04, 3,240 proposed for the Owner's review at the UAT —; 110 keys reach no page's list (server answers, validation messages and keys built from recorded data among them) and are listed in `unmapped.md`; LOCALIZATION §5.1's 881 keys stay validated; no key's text changed. The Owner's marks are TASK-086's, and correcting a marked key before the release is Planning's under LOCALIZATION §5. **8** — defects recorded for their Tasks and not fixed here (no string file changed in this Task; DIR-111 and DIR-114: no new Task): **P3** — 27 keys named nowhere in the code, copy no page can show: TASK-015 `actions.leave`, `actions.open_record`, `finance.cash_out`, `forms.value_taken`, `inventory.condition`, `nav.pagination_free`, `states.frozen`, `timeline.dates`; TASK-025 `companies.account.reason`; TASK-028 `locations.cut`; TASK-031 `cases.field.kind`, `cases.field.opened`, `cases.field.state`; TASK-032 `projects.line.price`, `projects.line.product`, `projects.line.quantity`; TASK-040 `stock.column.actor`, `stock.column.direction`, `stock.column.serial`, `stock.value.line`; TASK-049 `invoices.action.issue_nota`, `invoices.kuitansi.confirm_title`, `invoices.composer.client`; TASK-058 `signals.amount_row`; TASK-061 `activity.record.open`, `security_log.focus_unavailable`; and §6's `actions.abort_batch` (DIR-100), which PG-058 renders through its duplicate `import.action.abort` (TASK-057). The minimum fix is to remove each key from both string files and the registry, or to use `actions.abort_batch` where its duplicate stands; it is Planning's under LOCALIZATION §5. Each was found by the copy lists' `unmapped.md` (named nowhere) and confirmed by a search of the code. **CI before this head (TEST_STRATEGY §16):** `7839de3` (runs 43488584748, 43488621484) — the audit's first run found A–D, fixed here under APPR-032 (DIR-116; TASK_PLAN Change 21) and proven by the same audit and by D10's E2E test on Chromium and WebKit, and its own false positives; TL-14 failed on GHSA-63hm-g96r-2m2q, published that day, answered by laravel/framework 13.40.0 in the lockfile, `composer.json` unchanged. `7e0b0ec` (runs 43579445229, 43579449240) — every layer but localization passed, the E2E layer with D10's test on both engines among them; the localization failures were the audit's own — the switch back read before the account menu had opened, a label with a value not named by its key (Codex's finding), *Beranda* read while its groups loaded. `991d0f3` (runs 43584679991, 43584682876) — 186 of 190 localization tests passed; the four others compared runs of company values whose length the shared database changed between the two reads of one page, the language changing none of them; fixed in this head by reading a run of values as one place, a missing, added or exchanged catalogue action still failing the comparison; every other layer — Verify, E2E, responsive, accessibility, performance and migration — passed on both runs.
- Notes, DOD: each non-applicable item with its reason (DOD-03); the DONE update of TASK_PLAN, this file and CURRENT_HANDOFF (AICWDF §22.3)

**SAFE NEXT TASK:**

- TASK-070 — when its other dependencies are DONE and its READY gate holds

**DO NOT DO:**

- add to V1 — a capability, page, route, field, integration, document type, report or paid service — or drop, defer or narrow a V1_REQUIRED requirement; a Task that would need either raises an Owner question and waits ([DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-20)
- access the VPS, production, its data or a credential, or put a secret, real data, a host detail or production configuration in the repository ([INFRASTRUCTURE](../09-operations/INFRASTRUCTURE.md) INF-01, INF-05; SECURITY SX-01, SX-04, SX-06; [PRODUCTION_DATA_SAFETY](../00-governance/PRODUCTION_DATA_SAFETY.md))
- add AI, model or tool attribution to a commit — the Owner's configured identity only ([AGENTS.md](../../AGENTS.md#git-commit-attribution); DIR-022)
- start before the Owner's execution authorization at OG-4, or mark DONE without the required tests passing and the completion evidence ([TASK_PLAN](TASK_PLAN.md#2-rules-of-the-plan); [DEFINITION_OF_DONE](../08-testing/DEFINITION_OF_DONE.md#2-the-checklist))
- weaken, skip or delete a valid test to obtain a green run, or adopt a paid tier without the Owner's COST_POLICY decision (DOD-05; [COST_POLICY](../00-governance/COST_POLICY.md))
- translate business data or a document, or change a key's text, a validated key included, inside this Task (LOCALIZATION §3.4, §5)
- sample the keys instead of reading the inventory and the string files, or accept a new key the OB-04 rule does not accept by construction without listing it for the Owner (TEST_STRATEGY §10; OB-04)
- store the pre-login language choice on the device (LOCALIZATION §2)

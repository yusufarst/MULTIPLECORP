# TASK-014 — Localization — locales, the language switch and the key catalogue

Status: APPROVED | Updated: 2026-10-20 | Owner: Planning

Approval: [APPR-016](../00-governance/DECISION_LOG.md#appr-016--p11-task-plan-tasks-and-feature-coverage-matrix-approved), explicit Owner approval on 2026-10-20 (DIR-085 OF-07) of this document as committed in the first execution session's approval commit; the approved file hash and the verified conditions are recorded there. Approval changes lifecycle only, not implementation authorization.

Written by the executor of run PC-5 on 2026-10-19 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99; the Owner approved P11 with the [Task plan](TASK_PLAN.md) at OG-4 (DIR-085 OF-07). **No Task starts before the freeze record, from which the execution authorization is in force (DIR-085 OF-09).** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** DONE

**RISK CLASS:** STANDARD

**EXECUTOR:** AGENT

**OBJECTIVE:**

Give the application its two locales — Indonesian by default, English second — with the language switch, the key catalogue of LOCALIZATION §6 (889 keys: the MSG- messages, the GL- glossary, status labels, sign-in answers, documents, pages and shared groups), the formats and the checks that no string is missing or hardcoded.

**WHY THIS EXISTS:**

CAP-14 and AC-14 require natural Indonesian by default with English through a switch and no uncontrolled hardcoded copy (DIR-037 D7); GAP-037 stays open until the localization tests pass.

**DEPENDS ON:**

- TASK-003 — CI pipeline, test layers and the synthetic fixture harness

**BLOCKS:**

- TASK-015 — Design system — tokens, components, patterns and states
- TASK-023 — My account — password, two-step verification, Google link and language

**PARALLEL SAFE:** NO — every UI Task adds keys to the same catalogue

**IN SCOPE:**

- the locales `id` and `en`, the choice and where it is kept as LOCALIZATION §2 states; the switch on the sign-in pages (INT-001) and the account menu's switch with its storage (INT-002 with TASK-023's command)
- the key files as LOCALIZATION §3.1 states them, with `:name` placeholders and counted forms; the formats of §3.3; what never translates (§3.4)
- the 889 keys of §6 loaded as written: the 85 message keys of MSG-01–MSG-41, the glossary GL-001–GL-212, status, auth, documents, pages and the shared groups
- how strings reach the interface (§3.8): the locale in WS-07's shared props, the page's keys loaded with it
- the checks of §3.6 and TL-10: no missing string in either language, no hardcoded copy in components, the layout after a language change (§3.7)
- the rule for keys a later Task adds (§3.2; `DIR-081` OB-04; OQ-24 (a)): a new key's Indonesian is a GL term or validated copy, listed for the Owner's per-page review

**OUT OF SCOPE:**

- the account's own locale command and PG-065 (TASK-023)
- each page's own copy beyond the catalogue — its page's Task
- the per-page copy lists' completeness check (TASK-067)

**REFERENCES:**

- [LOCALIZATION §1](../07-ux-design/LOCALIZATION.md#1-the-localization-contract)
- [LOCALIZATION §2](../07-ux-design/LOCALIZATION.md#2-locales-the-choice-and-where-it-is-kept)
- [LOCALIZATION §3](../07-ux-design/LOCALIZATION.md#3-keys-ownership-formats-and-rules)
- [LOCALIZATION §4](../07-ux-design/LOCALIZATION.md#4-copy-rules)
- [LOCALIZATION §5](../07-ux-design/LOCALIZATION.md#5-copy-validation-ca-04-items-710)
- [LOCALIZATION §6](../07-ux-design/LOCALIZATION.md#6-key-inventory)
- [ADMIN_FLOW §14](../07-ux-design/ADMIN_FLOW/s14-15-messages-scenarios.md#14-message-catalogue) MSG-01–MSG-41
- [INFORMATION_ARCHITECTURE §3](../07-ux-design/INFORMATION_ARCHITECTURE.md#3-glossary) GL-001–GL-212
- [TEST_STRATEGY §10](../08-testing/TEST_STRATEGY.md#10-localization)
- [V1_SCOPE](../01-product/V1_SCOPE.md#must-ship) CAP-14
- the architecture challenge's sequences 17a–17b ([P11 gate record](evidence/P11_QUALITY_GATE.md#whole-system-architecture-challenge-sp-98))
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (288): O-07; CAP-14; AC-14; AC-20; REF-052–REF-055; IMG-04; IMG-09; INT-001–INT-002; GL-001–GL-212; MSG-01–MSG-41; GR-17; l10n-rule LOCALIZATION §1; l10n-rule LOCALIZATION §2; l10n-rule LOCALIZATION §3; l10n-rule LOCALIZATION §3.1; l10n-rule LOCALIZATION §3.2; l10n-rule LOCALIZATION §3.3; l10n-rule LOCALIZATION §3.4; l10n-rule LOCALIZATION §3.5; l10n-rule LOCALIZATION §3.6; l10n-rule LOCALIZATION §3.7; l10n-rule LOCALIZATION §3.8; l10n-rule LOCALIZATION §4; l10n-rule LOCALIZATION §5; l10n-rule LOCALIZATION §5.1; l10n-group messages; l10n-group glossary; l10n-group status; l10n-group auth; l10n-group documents; l10n-group pages; l10n-group shared; GAP-037

**EXPECTED MODULES / FILE AREAS:**

- `lang/id/`, `lang/en/` (or the JSON files §3.1 chooses), the locale middleware, the frontend translation helper, the missing-key and hardcoded-copy checks

**BUSINESS RULES:**

- N/A — no business rule; documents and business data stay Indonesian (DIR-037 D7; LOCALIZATION §3.4)

**ARCHITECTURE RULES:**

- strings reach React only as keys resolved from the locale's files; no copy in a component outside a key

**SECURITY / PERMISSION REQUIREMENTS:**

- the locale in shared props as WS-07 allows, nothing more; no user input becomes a key

**COMPANY SCOPE AND FIELD PROJECTION:**

- N/A — copy carries no company data; data inside strings arrives through placeholders from projected props

**CONCURRENCY / IDEMPOTENCY:**

- N/A — no command; the account locale command is TASK-023's

**AUDIT:**

- N/A — a language change is not a business action (TASK-023 records the account's preference)

**DATABASE IMPACT ASSESSMENT:**

- Schema change: NONE — no table or column
- Migration required: NO
- Existing data transformation: NONE
- Direct production DB access: FORBIDDEN
- Data-loss risk: NONE KNOWN

**MODULARITY / EXTENSIBILITY:**

- Module boundary affected: platform presentation
- Stable core kept provider-neutral: YES
- External/vendor-specific code isolated: YES — framework facilities or a small interface (ARCHITECTURE §2)
- Replaceable adapter/contract required: NO
- Provider lock-in risk: NONE beyond the Owner's baseline stack
- Future extension impact: LOW
- Speculative abstraction avoided: YES — no repository class per model, no generic workflow or rules engine (ARCHITECTURE §1)

**AUTH IMPACT:**

- Authentication change: NONE
- Authentication implementation: LARAVEL_NATIVE — decided once ([EXECUTION_CONTEXT](EXECUTION_CONTEXT.md)), reused, never redesigned
- Auth profile: N/A — this Task does not touch authentication
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

**MCP SESSION PROFILE:**

- Required MCP servers: none — [TOOLCHAIN](../00-governance/TOOLCHAIN.md#mcp-servers) records none installed
- MCP servers that should remain OFF: every server not required
- Native CLI/package preferred over MCP: YES — the framework's and package managers' own commands
- Post-Task deactivation/cleanup: none

**TOOL REQUIREMENTS:**

- Context7: REQUIRED — the framework's localization files and pluralization; the frontend translation helper chosen
- Graphify: N/A — the codebase is young
- Browser E2E: REQUIRED — the switch on a page with unsent input (CJ-18 is TASK-064's; this Task's own check on a sample page)
- Other: none
- Missing safe tool behavior: AUTO-INSTALL project-locally inside this Task (AICWDF §6.3) and record it in TOOLCHAIN; a paid tier or a privileged installation STOPS for the Owner (COST_POLICY)

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: N/A — the codebase is too young for a graph to add value; the impact is read from the diff and ARCHITECTURE §3–§4
- Pre-change questions: which components render strings
- Expected affected areas: the IN SCOPE areas only

**CONTEXT7:**

- Topics to verify: the framework's JSON and PHP translation loading, `:name` placeholders and choice strings
- Version-sensitive assumptions: none from memory

**UI / UX REQUIREMENTS:**

- Follow global design rules: YES
- Indonesian default: YES
- English support: YES
- Plain-language Indonesian: YES — the GL terms and the copy rules of §4
- Language switch impact: the switch keeps unsent input and the layout (§3.7)
- Mobile-first: YES — the switch on the phone layout
- Responsive: YES
- Adaptive: N/A — no device-specific behaviour
- Visual breathing room: REQUIRED — longer English strings do not crowd the layout
- Comfortable density: REQUIRED
- shadcn/ui reuse: the switch built from the design system's components (TASK-015)
- Design reference: LOCALIZATION §3.7 and DESIGN_SYSTEM §16
- Primary action: N/A — no command
- Secondary actions: the switch
- Destructive actions: none
- Redundancy constraints: one switch per surface
- Loading state: N/A — the keys arrive with the page
- Empty state: N/A — the switch always shows both languages
- Error state: a missing key fails the checks, never shows its key name to a user

**ROUTE IMPACT:**

- none — the account's locale route ROUTE-118 is TASK-023's

**INTERACTION CONTRACTS:**

- INT-001 — the switch on PG-001–PG-004; INT-002 — the account menu's switch, with TASK-023

**NAVIGATION CONTRACT:**

- Parent: N/A — the switch keeps the current page (NV-07); no navigation of its own
- Entry: N/A — the switch keeps the current page (NV-07); no navigation of its own
- Back action: N/A — the switch keeps the current page (NV-07); no navigation of its own
- Cancel: N/A — the switch keeps the current page (NV-07); no navigation of its own
- Success destination: N/A — the switch keeps the current page (NV-07); no navigation of its own
- Error behavior: N/A — the switch keeps the current page (NV-07); no navigation of its own
- State preservation: N/A — the switch keeps the current page (NV-07); no navigation of its own
- Mobile behavior: N/A — the switch keeps the current page (NV-07); no navigation of its own

**ACCEPTANCE CRITERIA:**

1. Every one of the 889 keys exists in both languages with the text LOCALIZATION §6 gives.
2. A component with a hardcoded user-facing string fails the hardcoded-copy check.
3. A missing key in either language fails TL-10 before release.
4. Switching the language on a page with unsent input keeps the input and the layout (LOCALIZATION §3.7; CJ-18 in TASK-064; architecture challenge 17b).
5. Dates, numbers and money render in the formats of §3.3 in both languages; for an account using English, generated and issued documents and business data stay in Indonesian, and data is never translated (DIR-037 D7; LOCALIZATION §3.4; architecture challenge 17a).

**TEST REQUIREMENTS:**

- Lint: TL-01 with the hardcoded-copy rule
- Typecheck: TL-02
- Unit: the formats of §3.3 and the counted forms (TL-03)
- Feature: the locale middleware and shared props (TL-04)
- Integration: N/A — no data path
- Authorization: N/A — no route
- Route integrity: N/A — no route added or changed
- Interaction: INT-001 and INT-002 as far as this Task builds them (TL-08)
- Browser E2E: the switch on a sample page (TL-09)
- Localization/language switch: both languages complete, no missing string (TL-10)
- Visual breathing room/density: English strings within the layout (TEST_STRATEGY §9)
- Responsive: the switch at the bands of TL-11
- Accessibility: the switch's name and state announced (TL-12)
- Production build: TL-13
- Security: N/A — no data path
- Performance: the page's keys loaded without a per-key request (TL-15)
- Migration: N/A — no migration or import code in this Task
- Other: GAP-037's localization tests start here

**CRITERIA DISPOSITIONS:**

- Criterion 1: `KeyCatalogueTest` holds both files to §6 key by key. Criterion 2: the `multiplecorp/hardcoded-copy` rule, its fixtures failing each kind of hardcoded string, in TL-01. Criterion 3: `KeyCatalogueTest` (no key missing or empty, the same keys and placeholders in both languages, each resolved never as itself) and TL-10's browser spec. Criterion 4: the switch keeping the typed input and the page through a state-keeping visit, on a fixture page before the sign-in pages exist (TASK-017), CJ-18 TASK-064's. Criterion 5: §3.3's formats in both languages (`FormatsTest`, `tests/Frontend/`), and documents and data in Indonesian whatever the interface (`LocaleTest`, `Locales::forDocuments`); the documents themselves are their Tasks'.

**TEST CHECKLIST:**

- [x] Lint: Pint and ESLint with the hardcoded-copy rule PASS, locally and in CI (TL-01)
- [x] Typecheck: Larastan/PHPStan at level `max` and the TypeScript compiler — the catalogue's keys typed — PASS on the workstation and in CI (TL-02)
- [x] Relevant automated tests: Unit — the 889 keys against §6, both languages complete, the counted forms, §3.3's formats (TL-03) —; the frontend's formats and counted forms under Node (TL-03); Feature — the locale middleware, the shared locale, documents and data in Indonesian, the fixture page with no session and no query (TL-04) —; PASS locally and in CI
- [x] Authorization: N/A — no route
- [x] Route integrity: N/A — no route added or changed; the fixture page is the `local` environment's only, never in testing, staging or production
- [x] Interaction behavior: INT-001's switch — the address key, the page's state kept, nothing stored —; INT-002's `onSelect` for TASK-023's command (TL-08 with TL-09)
- [x] Browser E2E if required: the switch on a sample page with unsent input, on Chromium and WebKit (TL-09)
- [x] Responsive/adaptive if required: the page at every band edge of TL-11 in both languages, the switch's targets at 44 px
- [x] Visual breathing room/density if UI: English strings within the layout at every band — no horizontal scroll, no clipped label (TEST_STRATEGY §9)
- [x] Production build: TL-13 — one chunk per language — PASS on the workstation and in CI
- [x] Security checks if required: N/A — no data path; the locale the only shared prop added (WS-07), the address key whitelisted, no user input a key; the secret-like scan and both dependency audits PASS
- [x] Database safety review: no migration and no schema change; `users.locale` read only; local and CI databases only, no production connection
- [x] Final diff review: each key and text against §6 by test, nothing invented; STANDARD and outside ER-06's areas, no reviewer

**COMPLETION EVIDENCE:**

- Test outputs (TEST_STRATEGY §16), tested commit `5de872d`: fresh CI on GitHub's `ubuntu-24.04` runner, runs 40195497025 (push) and 40195499432 (pull request #17), the three jobs PASS in each — "Verify" (every check of `scripts/verify.sh`, the ESLint rules, the frontend tests and Larastan/PHPStan included), "Browser" (TL-09–TL-12 on Chromium and WebKit) and "Performance and migration" (TL-15, TL-16 from empty). The first run, on `9777a43`, failed the browser job: the fixture page started the web group's session on the browser stack's unmigrated database; `5de872d` serves it without a session, a Feature test holding it to no query. On the Owner's workstation, 2026-10-25: `tests/Unit/Localization/`, `LocaleTest`, the ESLint rule's fixtures, `tests/Frontend/` and the browser specs PASS; the suites Unit 253, Feature 50, Interaction 4, Authorization 95, Architecture 50, Routes 11 and Integration 873; ESLint, TypeScript, Pint, Larastan/PHPStan, the frontend build and the repository gates PASS
- Build result: `TL-13 Frontend build: PASS` and `TL-13 Production caches and configuration: PASS — Evidence: 5de872d, CI ubuntu-24.04 run 40195497025`
- Route evidence: N/A — no route added or changed
- Interaction evidence: INT-001 by `tests/Browser/E2E/language-switch.spec.ts` and `LocaleTest`; INT-002's switch takes TASK-023's `onSelect`
- Browser evidence: `TL-09 E2E/browser: PASS — Evidence: 5de872d, CI ubuntu-24.04 run 40195497025`; TL-10, TL-11 and TL-12 PASS in the same run
- Localization evidence: both languages on the fixture page, the only page this Task adds (DOD-16)
- Cost evidence/exception if applicable: ADDITIONAL RECURRING COST CHANGE: NONE — nothing installed or hosted
- Files changed: `lang/id.json`, `lang/en.json`; `app/Support/Localization/`, `app/Http/Middleware/SetLocale.php` and `HandleInertiaRequests.php`, `app/Providers/AppServiceProvider.php`, `bootstrap/app.php`, `config/app.php`, `.env.example`, `routes/fixtures.php`; `resources/js/lib/` (`i18n.ts`, `i18n-core.ts`, `format.ts`), `resources/js/components/LanguageSwitch.tsx`, `resources/js/Pages/Fixtures/LanguageSwitch.tsx`, `resources/js/app.tsx`; `eslint/`, `eslint.config.js`, `tsconfig.json`, `package.json`, `scripts/verify.sh`; the tests of `tests/Unit/Localization/`, `tests/Feature/LocaleTest.php`, `tests/Frontend/`, `tests/Fixtures/Localization/` and `tests/Browser/`; TOOLCHAIN's row; this file, TASK_PLAN, CURRENT_HANDOFF and CHANGELOG
- Commit SHA: `9777a43` and `5de872d`, the Owner's identity, no signature and no AI attribution; this record's commit follows them on `task/TASK-014-localization`
- Notes: criteria as CRITERIA DISPOSITIONS states. Environment: the Owner's Windows workstation, Claude Code Desktop, 2026-10-25 (TOOLCHAIN, "Execution environments (TASK-001)"); Context7 not available, each facility verified from the installed sources and by the tests (TOOLCHAIN's TASK-014 row); the local runs used a container-local copy of the working tree and two browser workers, CI the final proof. Readings within the approved text are listed in CURRENT_HANDOFF. GAP-037 stays open until TASK-067's per-page checks. The READY gates of TASK-015 and TASK-023 were re-checked at this DONE: TASK-015 READY, TASK-023 waiting on TASK-016 and TASK-019. Each non-applicable item above carries its reason; TASK_PLAN, this file and CURRENT_HANDOFF updated for DONE

**SAFE NEXT TASK:**

- TASK-015 — when its other dependencies are DONE and its READY gate holds

**DO NOT DO:**

- add to V1 — a capability, page, route, field, integration, document type, report or paid service — or drop, defer or narrow a V1_REQUIRED requirement; a Task that would need either raises an Owner question and waits ([DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-20)
- access the VPS, production, its data or a credential, or put a secret, real data, a host detail or production configuration in the repository ([INFRASTRUCTURE](../09-operations/INFRASTRUCTURE.md) INF-01, INF-05; SECURITY SX-01, SX-04, SX-06; [PRODUCTION_DATA_SAFETY](../00-governance/PRODUCTION_DATA_SAFETY.md))
- add AI, model or tool attribution to a commit — the Owner's configured identity only ([AGENTS.md](../../AGENTS.md#git-commit-attribution); DIR-022)
- start before the Owner's execution authorization at OG-4, or mark DONE without the required tests passing and the completion evidence ([TASK_PLAN](TASK_PLAN.md#2-rules-of-the-plan); [DEFINITION_OF_DONE](../08-testing/DEFINITION_OF_DONE.md#2-the-checklist))
- weaken, skip or delete a valid test to obtain a green run, or adopt a paid tier without the Owner's COST_POLICY decision (DOD-05; [COST_POLICY](../00-governance/COST_POLICY.md))
- translate business data, a document or a legal name
- invent copy where a GL term or a MSG text exists

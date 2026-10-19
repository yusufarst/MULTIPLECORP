# TASK-067 — Localization completeness and the per-page copy lists

Status: REVIEW | Updated: 2026-10-20 | Owner: Planning

Written by WF-PC5-W's writer of bundle W3 of run PC-5 on 2026-10-19 and 2026-10-20 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99 — a REVIEW draft of P11, approved, or not, with the [Task plan](TASK_PLAN.md) at OG-4. **No Task starts before the Owner's execution authorization at OG-4.** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** TODO

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

- the missing-key check over the string files and the inventory, the two-language page runs and layout checks in the browser suite, and the per-page copy lists in the test evidence area TASK-003 set up; no application code and no string file changed

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

- [ ] Lint
- [ ] Typecheck
- [ ] Relevant automated tests
- [ ] Authorization
- [ ] Route integrity
- [ ] Interaction behavior
- [ ] Browser E2E if required
- [ ] Responsive/adaptive if required
- [ ] Visual breathing room/density if UI
- [ ] Production build
- [ ] Security checks if required
- [ ] Database safety review
- [ ] Final diff review

**COMPLETION EVIDENCE:**

- Test outputs: each required layer's result in [TEST_STRATEGY §16](../08-testing/TEST_STRATEGY.md#16-evidence)'s form, tied to the tested commit (DOD-04)
- Build result: the production build of TL-13
- Route evidence: the route audit for the routes this Task adds or changes, or N/A with its reason
- Interaction evidence: the INT- tests of this Task, or N/A with its reason
- Browser evidence: `E2E: PASS — Evidence: …` for its journeys (DOD-18), or N/A with its reason
- Localization evidence: both languages on every page it changes (DOD-16), or N/A with its reason
- Cost evidence/exception if applicable: ADDITIONAL RECURRING COST CHANGE: NONE (DOD-20)
- Files changed: the list, every one inside the IN SCOPE areas
- Commit SHA: the commit under the Owner's identity, with no AI attribution
- Notes: each non-applicable item with its reason (DOD-03); the DONE update of TASK_PLAN, this file and CURRENT_HANDOFF (AICWDF §22.3)

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

# TASK-064 — Critical journeys C — isolation, sign-in, access, search, approvals, import, language, masters, command identity and status

Status: REVIEW | Updated: 2026-10-20 | Owner: Planning

Written by WF-PC5-W's writer of bundle W3 of run PC-5 on 2026-10-19 and 2026-10-20 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99 — a REVIEW draft of P11, approved, or not, with the [Task plan](TASK_PLAN.md) at OG-4. **No Task starts before the Owner's execution authorization at OG-4.** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** TODO

**RISK CLASS:** STANDARD

**EXECUTOR:** AGENT

**OBJECTIVE:**

Write and run, in a real browser, the critical journeys CJ-09–CJ-13, CJ-16, CJ-18, CJ-19, CJ-21 and CJ-22 over the built system — company isolation as the pages show it, sign-in with its second factor and Google, users and grants, search and filter, approvals and Owner decisions, the opening import, the language switch, master data and numbering, command identity and session behaviour, and status and quality —, each with the ADMIN_FLOW journeys and UX scenarios TEST_STRATEGY §7 runs in it, on the CI stack except CJ-16, which runs on staging only, its evidence in TEST_STRATEGY §16's form, and every defect sent to the Task that owns it.

**WHY THIS EXISTS:**

These journeys prove the cross-cutting promises no single module owns: isolation as the user meets it (AC-13; architecture challenge 8a), authentication with TOTP and Google on every path (GAP-044), the language switch keeping unsent input (AC-14; architecture challenge 17b), and one command identity per intent through a lost answer, a 401, a 419 and a re-login (AC-16; GAP-008; architecture challenge 19a). AICWDF §28's Layer 2 examples — login and logout, search and filter, approval, permissions — are among them (TEST_STRATEGY §3). A verification Task never changes a requirement and never records a check as passed without its evidence (TS-08; AGENT_OPERATING_MODEL).

**DEPENDS ON:**

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

**PARALLEL SAFE:** NO — its suites share the CI stack, the browser harness and the synthetic fixtures with the other verification Tasks, and its session and failure injections must not run beside them

**IN SCOPE:**

- the browser suites, in Playwright on the Chromium and WebKit engines, on the CI stack with FX-02's synthetic group and the accounts of SECURITY_TEST_MATRIX §2, each journey walked through its pages, their INT- controls and their NAV- paths at the widths its devices name, the phone at 390 CSS px (TEST_STRATEGY §7, §12; IG-05):
  - **CJ-09**, company isolation as seen on the pages — the Company-A-only Admin's pooled view, a revoked grant on an open page, the Owner review queue's address —; J-INV-07; UXS-18, UXS-20–UXS-23, UXS-25; desktop and phone (AC-13; SM-23; architecture challenge 8a)
  - **CJ-10**, sign-in with a password and with Google, the second-factor challenge, enrolment, the in-place re-login, logout, expiry and remote termination; J-ACC-01; UXS-06, UXS-24; desktop and phone, Google's provider simulated — no test calls Google (AC-13; SM-20; GAP-044)
  - **CJ-11**, users, roles, capabilities and company grants by the Owner; J-ACC-02; UXS-27; desktop (AC-13; GAP-032)
  - **CJ-12**, search and filter — products, SKU, barcode, projects, clients and documents; a two-character term; more than 200 matches —; UXS-33; desktop and phone (AC-23; FX-07)
  - **CJ-13**, approvals and Owner decisions — quotation approval, commercial confirmation, the Owner review queue, a high-risk Owner command with step-up —; UXS-26, UXS-40; desktop
  - **CJ-16**, the opening import — prepare, dry run, quarantine, joint sign-off, commit, numbering and go-live — on FX-09; J-MIG-01; UXS-19, UXS-31, UXS-32; desktop; **staging only** (TEST_STRATEGY §7): its specification and batches written here, its run the operator's on staging (TASK-081), recorded here as not run until that evidence exists (AC-17; UXH-10)
  - **CJ-18**, the language switch on a page with unsent input and the language kept across a re-login; desktop and phone (AC-14; LOCALIZATION §3.7; architecture challenge 17b)
  - **CJ-19**, master data — the company master, client organization, unit and PIC, suppliers, the catalogue with units, barcodes and serial policy, locations and minimum stock — and numbering schemes; J-MD-01–J-MD-05; UXS-28, UXS-30; desktop (AC-01–AC-03)
  - **CJ-21**, command identity and session behaviour — a double click, a lost answer and its retry, 401 and 419 with in-place re-authentication, a lock-wait failure, an outcome unknown, two Admins on one draft, "already recorded", a prefilled date crossing midnight —; UXS-01–UXS-05, UXS-07, UXS-08, UXS-11–UXS-13; with UXH-02's cases: one `command_id` per intent across each, the unsent input surviving in memory after an intercepted answer, a transport failure and a step-up, ADMIN_FLOW §6's fallback if it fails, the background marker on deferred-group and prefetch requests, the in-app guard and the browser's leave prompt, and two accounts with the same display name at the re-authentication dialog; desktop and phone (AC-16; GAP-008; architecture challenge 19a)
  - **CJ-22**, status in greyscale and for colour-blind users, and the anti-slop audit of the dashboard; UXS-42, UXS-43; desktop (UXH-07)
- the failure injections CJ-21 needs on the CI stack — a dropped answer after commit, an expired session, a stale CSRF token, a lock-wait timeout, an unknown outcome, the controlled clock across WIB midnight (FX-08) —, each asserted by the recorded outcome and never by a second effect (CI-05; M-10)
- each journey's console and network captured, a critical console error or failed request failing it; its evidence in TEST_STRATEGY §16's form; `ReconcileGuards` clean after each (AICWDF §25; TS-11)
- TestSprite's UI and E2E regression runs over the same journeys where TASK-004 recorded its entitlement at no cost, never as the sole proof (DIR-083 OE-01)
- the suites registered as Layer 2's regression for the groups they touch and as part of Layer 3 (TEST_STRATEGY §3)
- each defect recorded with its reproducer, evidence, affected behaviour and proposed minimum fix, and sent through the executor feedback loop to the Task that owns it — a regression of a DONE Task becoming a new defect Task recorded in TASK_PLAN §7 —; a requirement that looks wrong goes to Planning, never changed here (AGENT_OPERATING_MODEL; DEFINITION_OF_DONE §1)

**OUT OF SCOPE:**

- the other journeys (TASK-062, TASK-063); the security matrix's denial cases behind CJ-09 and CJ-10 (TASK-069); the localization, accessibility and UI/UX suites (TASK-065–TASK-067); the full regression and the device UAT's preparation (TASK-070)
- the real Google redirect checked once on staging with the staging OAuth client, and CJ-16's staging run — the operator's (SM-20; TASK-081, TASK-082)
- fixing any module behaviour — the owning Task's; runs on the VPS, production or real data

**REFERENCES:**

- [TEST_STRATEGY §7](../08-testing/TEST_STRATEGY.md#7-browser-end-to-end-tests-and-critical-journeys) CJ-09–CJ-13, CJ-16, CJ-18, CJ-19, CJ-21, CJ-22 with the journeys, UX scenarios and UXH-02's command-identity cases; [§2](../08-testing/TEST_STRATEGY.md#2-test-layers) TL-09; [§3](../08-testing/TEST_STRATEGY.md#3-the-three-regression-layers); [§4](../08-testing/TEST_STRATEGY.md#4-environments-and-data) FX-02, FX-07–FX-09; [§10](../08-testing/TEST_STRATEGY.md#10-localization); [§16](../08-testing/TEST_STRATEGY.md#16-evidence); [§17](../08-testing/TEST_STRATEGY.md#17-tooling); [§1](../08-testing/TEST_STRATEGY.md#1-principles) TS-05, TS-06, TS-08, TS-11
- [ADMIN_FLOW §5](../07-ux-design/ADMIN_FLOW/s05-command-model.md#5-command-interaction-model) and [§6](../07-ux-design/ADMIN_FLOW/s06-09-session-forms-lists.md#6-session-expiry-re-authentication-and-unsent-input); [§10.1](../07-ux-design/ADMIN_FLOW/s10-a-journeys.md#101-access-and-master-data) J-ACC-01, J-ACC-02, J-MD-01–J-MD-05; [§10.3](../07-ux-design/ADMIN_FLOW/s10-a-journeys.md#103-inventory-and-purchasing) J-INV-07; [§10.7](../07-ux-design/ADMIN_FLOW/s10-b-journeys.md#107-completion-cancellation-and-migration) J-MIG-01; [§15](../07-ux-design/ADMIN_FLOW/s14-15-messages-scenarios.md#15-mandatory-ux-scenarios) UXS-01–UXS-08, UXS-11–UXS-13, UXS-18–UXS-28, UXS-30–UXS-33, UXS-40, UXS-42, UXS-43; [§16](../07-ux-design/ADMIN_FLOW/s16-17-handoff-traceability.md#16-handoff-obligations) UXH-02, UXH-07, UXH-10
- [ACCEPTANCE_CRITERIA](../01-product/ACCEPTANCE_CRITERIA.md#product-acceptance-contracts) AC-01–AC-03, AC-13, AC-14, AC-16, AC-17, AC-23
- [CONCURRENCY_IDEMPOTENCY §10](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s10-13-identity-failure-retry.md#10-command-identity-and-replay) CI-01–CI-09; [§16](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s14-17-numbering-jobs-revocation.md#16-authorization-and-revocation-races) RV-01–RV-08; [§18](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s18-scenarios.md#18-mandatory-concurrency-and-failure-scenarios) M-10, M-38
- [LOCALIZATION §3.7](../07-ux-design/LOCALIZATION.md#37-the-layout-after-a-language-change); [SECURITY §2](../05-security/SECURITY.md#2-authentication-sessions-and-credentials) AU-04–AU-24
- [SECURITY_TEST_MATRIX §2](../08-testing/SECURITY_TEST_MATRIX.md#2-fixtures), [§3](../08-testing/SECURITY_TEST_MATRIX.md#3-the-matrix) SM-13, SM-20, SM-23
- [AGENT_OPERATING_MODEL](../00-governance/AGENT_OPERATING_MODEL.md#executor-feedback-loop); [DEFINITION_OF_DONE §1](../08-testing/DEFINITION_OF_DONE.md#1-statuses-and-the-done-rule)
- [GAP_REGISTER](../00-governance/GAP_REGISTER.md#gap-008--retry-safety-is-undefined-after-a-lost-response-or-key-reuse) GAP-008; [GAP-044](../00-governance/GAP_REGISTER.md#gap-044--admin_flows-login-and-session-journeys-predate-totp-and-google-sign-in)
- Architecture challenge — the sequences 8a, 17b, 19a of the [P11 gate record](evidence/P11_QUALITY_GATE.md#whole-system-architecture-challenge-sp-98), their assertions carried into this Task's acceptance criteria and tests
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (13): TL-09; CJ-09–CJ-13; CJ-16; CJ-18–CJ-19; CJ-21–CJ-22; GAP-008; GAP-044

**EXPECTED MODULES / FILE AREAS:**

- the browser suite's journey specifications for CJ-09–CJ-13, CJ-16, CJ-18, CJ-19, CJ-21 and CJ-22, their page helpers and the CI stack's failure injections, in the end-to-end test area TASK-003 set up; CJ-16's FX-09 batches; no application code

**BUSINESS RULES:**

- the journeys assert, never redefine: BR-ACC-01 and BR-ACC-02 — the pooled physical view without another company's costs or identity (UXS-20) —; AC-13's two Admins of one company differing only by their grants; BR-DT-02–BR-DT-04 and ST-08 for the prefilled date across midnight (UXS-13); C-10 and NM-03 for the numbering steps of CJ-16 and CJ-19 (UXS-28, UXS-30–UXS-32)

**ARCHITECTURE RULES:**

- TS-05: the browser suite adds to the module Tasks' deterministic, concurrency and security tests and never replaces them; TestSprite is never the sole proof (OE-01)
- the failure injections live in the test environment only, behind the test harness, never in the production build (TL-13's "no test route")

**SECURITY / PERMISSION REQUIREMENTS:**

- each journey runs as the accounts of SECURITY_TEST_MATRIX §2 — the Company-A-only Admin, the Admin with A and B, the high-risk Admin with TOTP, the account revoked while its page is open, the deactivated account, the account with a linked Google identity —; secrets generated per run and never committed, the secret-like scan of SM-15 over the suites (DOD-21)
- the journeys show the denials as the pages present them — PG-005 with MSG-09 identical for out-of-scope and nonexistent, MSG-10 within scope (IP-21; AZ-11) —; their server-side cases are TASK-069's

**COMPANY SCOPE AND FIELD PROJECTION:**

- CJ-09 asserts on the pages what CS-04–CS-08 and PJ-01–PJ-04 state: the pooled lot of Company B with the relation marker and no name, supplier, date or cost; each row's company label for the Admin with A and B and no S3 sum across them; the routed refusal's notice; the review queue's address not found for an Admin (UXS-20–UXS-22, UXS-25)

**CONCURRENCY / IDEMPOTENCY:**

- CJ-21 proves on the pages what CI-01–CI-09 state: one `command_id` per intent, kept across a re-login for a submission that may have reached the server and newly minted after a terminal outcome (IP-02); a lost answer's retry returning the recorded outcome (CI-05; M-10); a lock-wait FAILED safe to repeat (SQ-14; RY-07); two Admins on one draft answered CONFLICT (ST-01; M-38); "already recorded" never offering a re-apply (SQ-07); a stale prefilled date CONFLICT (ST-08); in-command re-authorization refusing a revoked grant (RV-03; SQ-22; architecture challenge 8a); the races themselves are TEST_STRATEGY §6's

**AUDIT:**

- CJ-11 asserts the grant changes' audit events with their reason (RG-11); CJ-13 the Owner decision's audit and its review-queue listing (QS-14); CJ-10 the security events of its sign-in outcomes as the Owner's log shows them (LG-02) — the event content's tests are TASK-010's —; the suites write nothing outside the CI stack

**DATABASE IMPACT ASSESSMENT:**

- Schema change: NONE — test data only
- Migration required: NO
- Existing data transformation: NONE
- Direct production DB access: FORBIDDEN
- Data-loss risk: NONE KNOWN

**MODULARITY / EXTENSIBILITY:**

- Module boundary affected: none — test code only, outside the application's modules
- Stable core kept provider-neutral: YES
- External/vendor-specific code isolated: YES — Playwright, TestSprite and the simulated Google provider stay in the test tree (TEST_STRATEGY §17; SM-20)
- Replaceable adapter/contract required: NO
- Provider lock-in risk: NONE — Playwright is free and self-hosted in CI; TestSprite only as OE-01 allows
- Future extension impact: LOW — a further journey is one specification
- Speculative abstraction avoided: YES — page helpers only where two journeys share a page

**AUTH IMPACT:**

- Authentication change: NONE — CJ-10 and CJ-21 exercise the existing authentication
- Authentication implementation: LARAVEL_NATIVE — decided once ([EXECUTION_CONTEXT](EXECUTION_CONTEXT.md)), reused, never redesigned
- Auth profile: AICWDF-COMPAT-8 — exercised by CJ-10's sign-in and set-password steps with synthetic passwords (AU-03)
- Google sign-in: ON — exercised in CJ-10 against a simulated provider, existing accounts only (AU-22; SM-20)
- Google registration behavior: EXISTING_ACCOUNT_ONLY — CJ-10 asserts that an unknown Google identity creates nothing (AU-22)
- Google credentials owner action required: NO — no real OAuth client is used in CI; the staging client is TASK-082's
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

- Context7: REQUIRED — Playwright's request interception and route fulfilment for the injected failures, its clock control, its multi-context sessions for two Admins and two accounts, at the installed version; Inertia's handling of 401 and 419
- Graphify: OPTIONAL — to map each journey step to the Task that owns it when a defect is found
- Browser E2E: REQUIRED — this Task is the browser proof of its ten journeys
- Other: TestSprite where TASK-004 recorded its entitlement at no cost (OE-01); FX-02, FX-07, FX-08 and FX-09 from TASK-003's harness; a simulated OpenID provider for CJ-10 (SM-20)
- Missing safe tool behavior: AUTO-INSTALL project-locally inside this Task (AICWDF §6.3) and record it in TOOLCHAIN; a paid tier or a privileged installation STOPS for the Owner (COST_POLICY)

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: refreshed before the run when Graphify is available ([TOOLCHAIN](../00-governance/TOOLCHAIN.md)); otherwise each step's page traced to its Task through the FEATURE_COVERAGE_MATRIX
- Pre-change questions: which Task owns each page, route and command a journey crosses; where each failure can be injected without a test route in the production build
- Expected affected areas: the test tree only; a defect's fix lands in its owning Task

**CONTEXT7:**

- Topics to verify: Playwright's `route` interception, `clock` API and browser contexts; Inertia's 401 and 419 interception and partial reloads with the background marker
- Version-sensitive assumptions: the in-memory survival of unsent input after an intercepted answer, a transport failure and a step-up is implied, not stated, by the documentation — proved here on the chosen versions, with ADMIN_FLOW §6's fallback if it fails (TEST_STRATEGY §7)

**UI / UX REQUIREMENTS:**

- Follow global design rules: N/A — no page is built; the gate of AICWDF §27 is TASK-065's
- Indonesian default: YES — the journeys run in the default Indonesian interface; CJ-18 switches it
- English support: YES — CJ-18 switches to English and back on a page with unsent input (AC-14)
- Plain-language Indonesian: YES — each step's answer asserted by its MSG- text — MSG-03, MSG-04, MSG-06, MSG-07, MSG-09–MSG-11, MSG-14, MSG-16, MSG-17, MSG-19, MSG-23, MSG-24, MSG-27
- Language switch impact: CJ-18 asserts the page, its data and its unsent input kept after the switch, and the language kept in `users.locale` across a re-login (LOCALIZATION §3.7; CA-08)
- Mobile-first: YES — the phone steps of CJ-09, CJ-10, CJ-12, CJ-18 and CJ-21 at 390 CSS px
- Responsive: N/A — the band edges are TASK-065's matrix
- Adaptive: YES — each journey walks the phone and desktop compositions its devices name (IG-05)
- Visual breathing room: N/A — judged by TASK-065's gate
- Comfortable density: N/A — judged by TASK-065's gate
- shadcn/ui reuse: N/A — no component is built
- Design reference: N/A — the journeys follow the built pages and ADMIN_FLOW's journeys
- Primary action: N/A — no page; each step presses its page's primary action as its journey states
- Secondary actions: N/A — no page
- Destructive actions: N/A — no page; the confirmations, reason steps and step-ups are walked as steps (UXS-26)
- Redundancy constraints: N/A — duplicate actions are counted by TASK-065
- Loading state: N/A — no page; CJ-21 asserts the busy control and the frozen inputs while an outcome is unknown (IP-02)
- Empty state: N/A — no page; CJ-12 asserts the empty search with the term kept
- Error state: N/A — no page; the journeys assert the denial, conflict, failure and re-login answers listed above

**ROUTE IMPACT:**

- none — no route is added or changed; the journeys request the routes their pages use, captured in the network log

**INTERACTION CONTRACTS:**

- N/A — no INT- contract is built; the journeys press INT-001, INT-002, INT-005, INT-010, INT-011, INT-016, INT-017 and the command controls of their pages, whose tests are their Tasks'

**NAVIGATION CONTRACT:**

- Parent: N/A — no page; each journey walks its pages' NAV- contracts
- Entry: N/A — no page
- Back action: N/A — no page; CJ-10 asserts that Back after logout or expiry shows nothing of the ended session (UXS-06)
- Cancel: N/A — no page
- Success destination: N/A — no page; each step's destination asserted as its NAV- contract names
- Error behavior: N/A — no page; NV-04's answers asserted in CJ-09 and CJ-21
- State preservation: N/A — no page; CJ-18 and CJ-21 assert the unsent input kept in memory and never in browser storage (NV-05; H7-12)
- Mobile behavior: N/A — no page; the phone steps as above

**ACCEPTANCE CRITERIA:**

1. CJ-09: the Company-A-only Admin sees Company B's pooled lot with the relation marker only; the Admin with A and B sees each row's company and no sum across them; an action on B without a grant is refused and routed; a grant revoked while a page is open makes the next reload and command answer not found and the account's export *Tidak Tersedia*; the review queue's address is not found for an Admin (AC-13; UXS-20–UXS-23; UXS-25; architecture challenge 8a).
2. CJ-10 signs in with a password and with the simulated Google provider, reaches the second-factor challenge on every path, enrols a high-risk account, re-logs in place with the page and its input kept, and ends the session at logout, expiry and remote termination with Back showing nothing of it; an unknown Google identity creates nothing (AC-13; UXS-06; UXS-24; GAP-044).
3. CJ-11 lets the Owner create an account, grant capabilities and companies with the capability-by-company preview, and revoke them, a prerequisite's removal disabling its dependants (AC-13; UXS-27).
4. CJ-12 finds products, SKU, barcode, projects, clients and documents, answers a two-character term with MSG-24 and more than 200 matches with MSG-23, never showing an out-of-scope record (AC-23; UXS-33).
5. CJ-13 records a quotation approval, a confirmation and a high-risk Owner command through the step-up after 15 minutes, and shows the Owner review queue with every QS-14 source and no approve control (UXS-26; UXS-40).
6. CJ-18 switches the language on a page with unsent input, keeping the page, its data and its input with the layout intact, and keeps the chosen language across a re-login (AC-14; architecture challenge 17b).
7. CJ-19 sets up a company, a client organization with unit and PIC, a supplier, a product with units, barcodes and serial policy, a location with minimum stock and a numbering scheme, an Admin's project in an unstarted period refused with MSG-17 and accepted after the Owner's start (AC-01–AC-03; UXS-28; UXS-30).
8. CJ-21: a double click, a lost answer retried, a 401 and a 419 with in-place re-authentication each record one effect under one `command_id`; a lock-wait FAILED and an unknown outcome retried safely; two Admins on one draft give the later one CONFLICT with its input kept; "already recorded" offers no re-apply; a prefilled date crossing midnight is CONFLICT with both dates (AC-16; UXS-01–UXS-05; UXS-07; UXS-08; UXS-11–UXS-13; GAP-008; architecture challenge 19a).
9. CJ-21 also proves UXH-02's cases on the chosen versions — the unsent input surviving in memory, never in browser storage; background requests never extending the session; a second account at the re-authentication dialog seeing only its own projection — or records the failure and ADMIN_FLOW §6's fallback (TEST_STRATEGY §7).
10. CJ-22 shows every state with its text label and shape cue, distinguishable in greyscale and for colour-blind users, and the dashboard's anti-slop audit finds no KPI-card wall, decorative chart, banner or greeting (UXS-42; UXS-43).
11. CJ-16's specification and FX-09 batches exist and its run is recorded as not run until the operator's staging run supplies its evidence; it is never reported as passed without it (TEST_STRATEGY §7, §16).
12. Every CI journey's run records its evidence with no critical console error and no critical failed request, and every failure found is sent to its owning Task without changing a requirement (TEST_STRATEGY §8; TS-06; TS-08).

**TEST REQUIREMENTS:**

- Lint: TL-01 on the suite's code
- Typecheck: TL-02 on the suite's code
- Unit: N/A — no domain logic in this Task
- Feature: N/A — the routes and commands are each module Task's (TL-04)
- Integration: N/A — the races are TEST_STRATEGY §6's and the module Tasks'; CJ-21 injects failures at the browser and asserts the recorded outcomes
- Authorization: N/A — the denial suites are TASK-069's; CJ-09 asserts the denials as the pages present them
- Route integrity: N/A — the route crawl and audit are TASK-069's; each journey's network capture flags an unexpected 404, 405 or 500
- Interaction: N/A — the INT- tests are their Tasks'; the journeys walk them
- Browser E2E: TL-09 — CJ-09–CJ-13, CJ-18, CJ-19, CJ-21 and CJ-22 on Chromium and WebKit in CI; CJ-16 written for its staging run (TASK-081)
- Localization/language switch: TL-10's switch case in CJ-18; the full two-language check is TASK-067's
- Visual breathing room/density: N/A — TASK-065's gate
- Responsive: N/A — TASK-065's band-edge matrix
- Accessibility: the engine of TASK-066 runs on every page state these journeys reach; CJ-22's status cues (TEST_STRATEGY §11)
- Production build: TL-13 for the suite's code — no failure-injection route in the production build
- Security: SM-13's browser part — no form data, identifier or S3 or S4 value in any browser storage, history unreadable after every authentication boundary; SM-20's simulated provider; SM-23's identical denial pages — the full cases TASK-069's (TL-14)
- Performance: N/A — TASK-068's budgets; the journeys' network capture is kept for it
- Migration: N/A — CJ-16's import runs on staging; the import's TL-16 suite is TASK-057's
- Other: `ReconcileGuards` clean after each journey (TS-11); TestSprite's regression runs where OE-01 allows

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
- change a requirement, an acceptance criterion or a fixture's expected value to make a journey pass, or fix a module's behaviour inside this Task (TS-06; AGENT_OPERATING_MODEL)
- report CJ-16, or any journey, as passed without its run's evidence; run CJ-16 or any check on staging, the VPS or production (TS-08; TEST_STRATEGY §4; INF-01)
- call Google or the breached-password service from a test, or leave a failure-injection hook reachable in the production build (SM-16; SM-20; TL-13)

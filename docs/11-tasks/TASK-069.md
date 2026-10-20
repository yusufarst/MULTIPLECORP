# TASK-069 — Security test matrix, route integrity and zero dead interactions

Status: APPROVED | Updated: 2026-10-20 | Owner: Planning

Approval: [APPR-016](../00-governance/DECISION_LOG.md#appr-016--p11-task-plan-tasks-and-feature-coverage-matrix-approved), explicit Owner approval on 2026-10-20 (DIR-085 OF-07) of this document as committed in the first execution session's approval commit; the approved file hash and the verified conditions are recorded there. Approval changes lifecycle only, not implementation authorization.

Written by WF-PC5-W's writer of bundle W3 of run PC-5 on 2026-10-19 and 2026-10-20 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99; the Owner approved P11 with the [Task plan](TASK_PLAN.md) at OG-4 (DIR-085 OF-07). **No Task starts before the freeze record, from which the execution authorization is in force (DIR-085 OF-09).** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** TODO

**RISK CLASS:** HIGH

**EXECUTOR:** AGENT

**OBJECTIVE:**

Run SECURITY_TEST_MATRIX in full over the built system — SM-01–SM-25 with every data path, W4 row, Owner-only denial and threat TM-01–TM-73, each denial with its positive twin, on real PostgreSQL and real sessions —, prove route integrity by the route audit and a crawl of all 210 routes, check that each of the 165 interaction contracts and 65 navigation contracts has its passing test, and count AICWDF §25's nine zeros for the release integrity report; every case that must run on staging prepared for the operator, and every defect sent to the Task that owns it.

**WHY THIS EXISTS:**

Company isolation and default-deny on the server are invariant 2 of EXECUTION_CONTEXT, and SECURITY §10's H8-01–H8-22 hand P8 the proofs that SECURITY_TEST_MATRIX turns into cases; with no row-level security in PostgreSQL these cases are the isolation proof (D-SEC-07; EN-06; architecture challenge 1a). AICWDF §25 makes a dead button, a broken route or an unexpected 404, 405 or 500 a release blocker, and GAP-035's contracts are closed only when every route and control is proved against its contract (TEST_STRATEGY §8). Each module Task proves its own cases; this Task runs the whole matrix at Layer 3 (TL-14) and counts what the release integrity report reads (RELEASE_PLAN §11).

**DEPENDS ON:**

- TASK-004 — Verification and release tooling check
- TASK-020 — Installation step, the first Owner and the Owner-recovery path
- TASK-023 — My account — password, two-step verification, Google link and language
- TASK-024 — Users and access — accounts, roles, capabilities and company grants
- TASK-056 — Project cancellation
- TASK-057 — Opening import — preparation, dry run, sign-off and commit
- TASK-058 — Home, Work and Review — signals and the action queue
- TASK-059 — Search and scanning
- TASK-060 — Reports, the consolidated report and exports
- TASK-061 — Activity history and security log
- TASK-062 — Critical journeys A — the golden flow, purchasing, fulfilment, counts, scanning and SIPLAH
- TASK-063 — Critical journeys B — finance, corrections, reports, documents, completion and the checklist
- TASK-064 — Critical journeys C — isolation, sign-in, access, search, approvals, import, language, masters, command identity and status
- TASK-065 — UI/UX consistency gate, the responsive matrix and the anti-slop audit
- TASK-066 — Accessibility — WCAG 2.2 AA across every page
- TASK-068 — Performance targets and query budgets in CI

**BLOCKS:**

- TASK-070 — Full release regression and the device UAT's preparation

**PARALLEL SAFE:** NO — its race and revocation cases need the CI stack's sessions and database alone, and its findings reopen security-critical Tasks

**IN SCOPE:**

- **the matrix**, SM-01–SM-25, each element of its H8 row its own case with its own assertion, the row passing only when every element passes; a case that cannot run BLOCKED, never N/A (SECURITY_TEST_MATRIX §1; DOD-03): SM-01–SM-22 answering H8-01–H8-22 one to one, SM-23 UXH-03's denial and oracle presentation, SM-24 GAP-049's first-Owner installation event, SM-25 AC-13's field projection inside scope (§3)
- the matrix's tables, each built from its source and counted: the 20 data paths DP-01–DP-20 with their denial obligations, SM-01 carrying each for Company B (§4); the 27 W4 rows with their positive and denial cases by class (§5; SM-04); the 16 Owner-only denials OD-01–OD-16 (§6); the 73 threats TM-01–TM-73, each with at least one case (§7); the accepted risks' controls — GAP-038–GAP-041 and GAP-045–GAP-048 — proved without testing a risk as removed (§8)
- the judging rules of §1: the expected result the control's — out of scope not found and indistinguishable from a nonexistent record in status, wording, layout and timing class, in scope without the capability forbidden —; a refused command writing only its refusal events, the case asserting the answer and the absence of every other effect; every denial asserted at the server; a positive twin for every denial; real PostgreSQL with the project's database roles and real sessions, a concurrent case with its barrier as its M row states — M-13, M-20–M-25, M-39–M-46 —; `ReconcileGuards` clean after every case that writes and SM-05's OD-16 reconciliation after every authorization suite
- SECURITY_TEST_MATRIX §2's fixtures created by the suite each run — ARJ, BTN and CKP with every record family and a shared pool holding lots of A and B, the two Owners, the Company-A-only Admin, the Admin with A and B, the ADM+ high-risk Admin with TOTP, the Admin without `stock.view`, the Admins each lacking one family view capability, the new Admin with no grant, the deactivated account with a session and a queued export, the account revoked while its page is open, the high-risk account without TOTP, the Google-linked accounts, the operator without a user row — and their secrets generated per run, never committed
- **route integrity** (TL-07; TEST_STRATEGY §8): SM-02's route audit — every authenticated route declaring a capability or one of EN-03's three declared requirements, a route and its contract in ROUTE_CONTRACTS one to one (GAP-035) —, and the crawl of all 210 routes as an actor allowed to use each: a command refusing GET, a page answering 200, a file route streaming with its header profile, nothing answering an unexpected 404, 405 or 500
- **interaction and navigation integrity** (TL-08): each of INTERACTION_CONTRACTS' 165 contracts present with its *Test* written as its test and passing — the control present exactly for the actors it names and absent otherwise, doing what it says, showing its success, validation and errors, never submitting without feedback (IG-05) —, and each of NAVIGATION_CONTRACTS' 65 contracts with its back, cancel, success and error paths tested with its page; ROUTE_CONTRACTS §4's controls without a route checked as marked — client-only, intentionally disabled with its reason, intentionally hidden
- **AICWDF §25's nine zeros** counted from these suites and the journeys' console and network captures — orphan UI actions, broken routes, dead buttons, unhandled critical actions, unexpected 404, 405 and 500, critical console errors, critical failed network requests — for the release integrity report's ROUTES, INTERACTIONS and BROWSER sections (TEST_STRATEGY §8; RELEASE_PLAN §11)
- H8-15's CI gates — the secret-like scan, the dependency audits, lockfile-only installs and SHA-pinned actions — as required checks that fail the pipeline, each gate's detection proved on a fixture inside the suite (SM-15)
- the end-of-recovery race of GAP-050 in SM-21 — the end against a role change and against an activation, both orders, a barrier before LK-02 —: the case written, its expected result the end of recovery atomic under the OWNER role row that TASK-020 builds (BACKUP_RECOVERY TT-F30) once RV-18 adopts it, GAP-050 OPEN until then (SM-21)
- the P8 handoffs mapped here: HO-17's limiter exactness and store-down behaviour proved by SM-14, UXH-02's security part by SM-13, UXH-03 by SM-23 and UXH-04 by SM-12; the others checked for their evidence where they are answered — HO-11 and HO-12 in TEST_STRATEGY §6's module suites, HO-13 in TASK-013 and TASK-068, HO-20 in TASK-013, TASK-017, TASK-022 and TASK-024, HO-16 in TASK-068, HO-19 in TASK-021's numbering tests, UXH-01 in TASK-062–TASK-064, UXH-05 in TASK-066, UXH-07 in TASK-065 —, a missing one reported, never run a second time here; UXH-06, HO-35 and UXH-08, answered after this Task by TASK-070 and at the UAT (TASK-086), are checked there
- the staging-only cases prepared for the operator — the Owner-recovery procedure of SM-07, the operator's commands of SM-19 and SM-21, the real Google redirect of SM-20 with the staging OAuth client, the TLS and HSTS configuration of SM-08 on the staging host — and recorded as not run until the operator's evidence exists (SECURITY_TEST_MATRIX §9; TASK-078, TASK-081, TASK-082)
- each defect recorded with its case, evidence and proposed minimum fix and sent through the executor feedback loop to the Task that owns the control — a DONE Task's regression becoming a new defect Task under TASK_PLAN §7 —; a control that looks wrong goes to Planning, never changed here; a test showing an accepted residual larger than accepted is a finding for the Owner (§8; AGENT_OPERATING_MODEL)

**OUT OF SCOPE:**

- each control's implementation and its module's own security cases (TASK-010–TASK-024 and the module Tasks); the critical journeys, the UI/UX, accessibility, localization and performance suites (TASK-062–TASK-068)
- running a case on staging, the VPS or production — the operator's on staging, none on production (SECURITY_TEST_MATRIX §9; INF-01)
- amending RV-18 or any control — Planning's under change control

**REFERENCES:**

- [SECURITY_TEST_MATRIX §1](../08-testing/SECURITY_TEST_MATRIX.md#1-how-a-case-is-written-and-judged)–[§10](../08-testing/SECURITY_TEST_MATRIX.md#10-coverage) SM-01–SM-25, with [§2](../08-testing/SECURITY_TEST_MATRIX.md#2-fixtures), [§4](../08-testing/SECURITY_TEST_MATRIX.md#4-data-paths), [§5](../08-testing/SECURITY_TEST_MATRIX.md#5-capability-rows), [§6](../08-testing/SECURITY_TEST_MATRIX.md#6-owner-only-denials), [§7](../08-testing/SECURITY_TEST_MATRIX.md#7-threats), [§8](../08-testing/SECURITY_TEST_MATRIX.md#8-accepted-risks), [§9](../08-testing/SECURITY_TEST_MATRIX.md#9-where-each-case-runs)
- [SECURITY §8](../05-security/SECURITY.md#8-threat-model) TM-01–TM-73; [§10](../05-security/SECURITY.md#p8--required-denial-and-security-tests-p8-creates-its-own-files) H8-01–H8-22; [§3](../05-security/SECURITY.md#3-enforcement-architecture) EN-01–EN-08; [§9](../05-security/SECURITY.md#9-design-decisions) D-SEC-07
- [PERMISSIONS_MATRIX §2](../05-security/PERMISSIONS_MATRIX.md#2-authorization-model) AZ-01–AZ-13; [§5](../05-security/PERMISSIONS_MATRIX.md#5-workflows-4-mapping) W4-01–W4-27; [§6](../05-security/PERMISSIONS_MATRIX.md#6-company-scope-rules) CS-01–CS-08; [§7](../05-security/PERMISSIONS_MATRIX.md#7-resource-and-field-projection); [§8](../05-security/PERMISSIONS_MATRIX.md#8-cross-company-link-authorization) XL-1–XL-5; [§9](../05-security/PERMISSIONS_MATRIX.md#9-owner-only-denials) OD-01–OD-16; [§11](../05-security/PERMISSIONS_MATRIX.md#11-data-path-coverage-matrix) DP-01–DP-20
- [TEST_STRATEGY §8](../08-testing/TEST_STRATEGY.md#8-route-navigation-and-interaction-integrity); [§13](../08-testing/TEST_STRATEGY.md#13-security-tests); [§2](../08-testing/TEST_STRATEGY.md#2-test-layers) TL-06–TL-08, TL-14; [§6](../08-testing/TEST_STRATEGY.md#6-concurrency-and-failure-proofs) the M- rows and the HO- proofs; [§16](../08-testing/TEST_STRATEGY.md#16-evidence)
- [ROUTE_CONTRACTS](../03-workflows/ROUTE_CONTRACTS.md#1-conventions) RC-01–RC-06, [§4](../03-workflows/ROUTE_CONTRACTS.md#4-visible-controls-without-a-route-of-their-own), [§5](../03-workflows/ROUTE_CONTRACTS.md#5-counts); [INTERACTION_CONTRACTS](../03-workflows/INTERACTION_CONTRACTS.md#1-conventions) IG-01–IG-05, [§4](../03-workflows/INTERACTION_CONTRACTS.md#4-counts); [NAVIGATION_CONTRACTS](../07-ux-design/NAVIGATION_CONTRACTS.md#1-conventions) NV-01–NV-08, [§3](../07-ux-design/NAVIGATION_CONTRACTS.md#3-counts)
- [CONCURRENCY_IDEMPOTENCY §16](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s14-17-numbering-jobs-revocation.md#16-authorization-and-revocation-races) RV-01–RV-18; [§18](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s18-scenarios.md#18-mandatory-concurrency-and-failure-scenarios); [§19](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s19-20-handoff-traceability.md#19-handoff-obligations) HO-11–HO-13, HO-16, HO-17, HO-19, HO-20, HO-35
- [ADMIN_FLOW §16](../07-ux-design/ADMIN_FLOW/s16-17-handoff-traceability.md#16-handoff-obligations) UXH-01–UXH-08
- [RELEASE_PLAN §11](../10-release/RELEASE_PLAN.md#11-the-release-integrity-report); [BACKUP_RECOVERY §6](../09-operations/BACKUP_RECOVERY.md#the-exercise-held-in-pc-4) TT-F30
- [GAP_REGISTER](../00-governance/GAP_REGISTER.md#gap-003--one-stock-pool-must-preserve-source-and-consuming-company-attribution) GAP-003; [GAP-005](../00-governance/GAP_REGISTER.md#gap-005--cancellation-may-encounter-delivered-billed-or-paid-downstream-records); [GAP-006](../00-governance/GAP_REGISTER.md#gap-006--global-masters-can-leak-company-specific-relationships-and-prices); [GAP-035](../00-governance/GAP_REGISTER.md#gap-035--route-and-interaction-contracts-do-not-exist-yet); [GAP-050](../00-governance/GAP_REGISTER.md#gap-050--post-incident-recovery-cases-carried-to-p8-and-p9)
- Architecture challenge — the sequence 1a of the [P11 gate record](evidence/P11_QUALITY_GATE.md#whole-system-architecture-challenge-sp-98), their assertions carried into this Task's acceptance criteria and tests
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (173): O-06; CAP-13; AC-13; OS-03; REF-001; REF-004; REF-056; OWN-02; OWN-05; TM-01–TM-73; H8-01–H8-22; M-13; M-20–M-25; M-39–M-46; HO-11–HO-13; HO-16–HO-17; HO-19–HO-20; HO-35; HO-38; UXH-01–UXH-08; TL-06–TL-08; TL-14; SM-01–SM-25; TT-F30; GAP-003; GAP-005–GAP-006; GAP-034–GAP-035; GAP-050; GAP-067

**EXPECTED MODULES / FILE AREAS:**

- the security suites by SM- row, the data-path, W4, OD and threat tables generated from their sources, the route audit and the route crawl, the interaction and navigation inventories with their pass checks, the nine-zero counter and the staging case scripts, in the test areas TASK-003 set up; no application code

**BUSINESS RULES:**

- BR-ACC-01–BR-ACC-03 and BR-XC-02 as the cases assert them: default deny, the pooled physical view without another company's protected categories, a deactivated company still collectable, every action naming its individual account; no case changes a rule

**ARCHITECTURE RULES:**

- every denial asserted at the server — route, command, query class and file controller —, never only through a hidden control; an end-to-end case adds the presentation and never replaces the server assertion (SECURITY_TEST_MATRIX §1; AZ-01)
- real PostgreSQL with the project's database roles, never an in-memory substitute (ARCHITECTURE §18; TS-03); no layer the only proof (TS-05)

**SECURITY / PERMISSION REQUIREMENTS:**

- the whole of SECURITY's controls under test — authentication AU-01–AU-25, enforcement EN-01–EN-08, web and input WS-01–WS-12, files FL-01–FL-11, logging LG-01–LG-07, secrets SX-01–SX-07 — through their SM- rows and threats; nothing here weakens or bypasses a control to make a case run
- synthetic keys, passwords and tokens only, generated per run; no test calls Google or the breached-password range service — both simulated (SM-16; SM-20); no real secret in a fixture or evidence, the secret-like scan over the suite (SM-15; DOD-21)

**COMPANY SCOPE AND FIELD PROJECTION:**

- SM-01: the Company-A-only Admin against Company B on every data path and record route — forged and foreign public ids, foreign references in filters, payloads, includes, sort keys and cursors, nested resources, masters reached through a shared master, search and exact lookups, exports, report filters, dashboard signals, downloads, print views —, with the Admin of A and B as its positive twin (CS-03; CS-04; CS-07; architecture challenge 1a)
- SM-25: an Admin of Company A lacking each family view capability in turn — money, cost, profit and evidence absent or refused on every page, prop, deferred group, export, print and document payload; the Owner-only signals and the raw stamp and signature files never served —, each with its positive twin (PJ-01–PJ-23); a cross-company reference only as XL-1–XL-5 state (GAP-003; GAP-006)

**CONCURRENCY / IDEMPOTENCY:**

- the race cases with real sessions and their barriers as the M rows state: M-13 (a `command_id` reused by another account and a replay after revocation), M-20 and M-21 (revocation and deactivation during commands), M-22 (the last two Owners), M-23 (an export delivered after revocation), M-24 (one credential token twice), M-25 (the verification cap flood), M-39–M-46 (TOTP, recovery codes, Google links and callbacks, the second-factor limit, the high-risk reissue, the OWNER-restoration command), with RV-01–RV-18's lock sequences; a replay re-authorized and disclosing nothing to another account (CI-05; DP-16)

**AUDIT:**

- SM-05's OD-16 reconciliation returning no row after every authorization and concurrency suite — each audit event's authority class the highest its command exercised (AZ-10), no OWNER_ONLY capability outside the OWNER role, no OWNER_ONLY event by an account without it, ST-03's and SQ-23's events read as refusals —, a seeded anomaly of each kind found and logged as AUTHORIZATION_ANOMALY
- every refused command writing only the refusal events SECURITY §6 names (SECURITY_TEST_MATRIX §1); SM-12's undisclosed-duplicate and import-collision events, one per command and matched record, visible only in the Owner review queue

**DATABASE IMPACT ASSESSMENT:**

- Schema change: NONE — test data only
- Migration required: NO
- Existing data transformation: NONE
- Direct production DB access: FORBIDDEN
- Data-loss risk: NONE KNOWN

**MODULARITY / EXTENSIBILITY:**

- Module boundary affected: none — test code, generated tables and counters only
- Stable core kept provider-neutral: YES
- External/vendor-specific code isolated: YES — the simulated OpenID provider and range service, the secret scanner and the audit tools stay in the test tree (TEST_STRATEGY §17)
- Replaceable adapter/contract required: NO
- Provider lock-in risk: NONE — Pest, Playwright, composer audit, npm audit and a free secret scanner
- Future extension impact: LOW — a further route, control or threat joins its generated table
- Speculative abstraction avoided: YES — the tables generated from the sources, no security-testing service

**AUTH IMPACT:**

- Authentication change: NONE — the cases exercise SECURITY §2 as built
- Authentication implementation: LARAVEL_NATIVE — decided once ([EXECUTION_CONTEXT](EXECUTION_CONTEXT.md)), reused, never redesigned; SM-22 re-verifies the framework behaviours read in source (GAP-043)
- Auth profile: AICWDF-COMPAT-8 — SM-06 and SM-16 test it with the blocklist and the simulated breached-password check (AU-03)
- Google sign-in: ON — SM-20 tests it against a simulated provider; the real redirect once on staging by the operator
- Google registration behavior: EXISTING_ACCOUNT_ONLY — SM-20 asserts no account created from Google (AU-22)
- Google credentials owner action required: NO — no real OAuth client in CI; the staging client is TASK-082's
- Authorization change: NONE
- Provider/library-specific leakage into domain: NO — SM-03's architecture tests check that none leaks (H8-03)

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

- Context7: REQUIRED — the framework's route listing for the audit and the crawl, its CSRF, cookie and header middleware, Fortify's registered features; Playwright's network capture; the dependency-audit and secret-scanning tools' exit codes; at the installed versions
- Graphify: OPTIONAL — to map each route, control and query class to the Task that owns it when a defect is sent
- Browser E2E: REQUIRED — the presentation cases of SM-23, SM-13's browser part and the INT- and NAV- tests in the browser suite
- Other: the simulated OpenID provider and range service (SM-16; SM-20); the two-connection harness for the M- races (TASK-003); the fixtures of SECURITY_TEST_MATRIX §2
- Missing safe tool behavior: AUTO-INSTALL project-locally inside this Task (AICWDF §6.3) and record it in TOOLCHAIN; a paid tier or a privileged installation STOPS for the Owner (COST_POLICY)

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: refreshed before the run when Graphify is available ([TOOLCHAIN](../00-governance/TOOLCHAIN.md)); otherwise the registered routes, query classes and policies listed by the framework
- Pre-change questions: which route, query class or command each data path reaches; which control each threat's case exercises
- Expected affected areas: the test tree only; a defect's fix lands in the Task that owns the control

**CONTEXT7:**

- Topics to verify: the framework's route enumeration including the packages' routes; Fortify's feature flags and the routes they register; `Sec-Fetch-Site` handling; cookie attributes; the CSP nonce
- Version-sensitive assumptions: every framework behaviour SECURITY records as *source* re-verified in the chosen versions, one behaviour test per item (SM-22; GAP-043)

**UI / UX REQUIREMENTS:**

- Follow global design rules: N/A — no page is built; the security presentation is SM-23's
- Indonesian default: N/A — no copy is built; MSG-09 and MSG-10 asserted as written
- English support: N/A — no copy is built
- Plain-language Indonesian: N/A — no copy is built
- Language switch impact: NONE
- Mobile-first: N/A — no page is built; the browser cases run at the desktop and phone widths
- Responsive: N/A — no page is built
- Adaptive: N/A — no page is built
- Visual breathing room: N/A — no page is built
- Comfortable density: N/A — no page is built
- shadcn/ui reuse: N/A — no component is built
- Design reference: N/A — no page is built
- Primary action: N/A — no page; IG-01's presence and absence of controls tested per contract
- Secondary actions: N/A — no page
- Destructive actions: N/A — no page; IP-08 before every irreversible command checked through its INT- test
- Redundancy constraints: N/A — TASK-065 counts duplicates; orphan UI actions counted here
- Loading state: N/A — no page
- Empty state: N/A — no page; SM-23 asserts that no empty state hints at an out-of-scope record
- Error state: N/A — no page; SM-23 asserts identical not-found answers and MSG-10 within scope

**ROUTE IMPACT:**

- none — no route is added or changed; all 210 routes audited and crawled (SM-02; TEST_STRATEGY §8)

**INTERACTION CONTRACTS:**

- N/A — no INT- contract is built; all 165 INT- contracts checked for their passing test (TL-08)

**NAVIGATION CONTRACT:**

- Parent: N/A — no page; all 65 NAV- contracts checked for their tested paths
- Entry: N/A — no page
- Back action: N/A — no page
- Cancel: N/A — no page
- Success destination: N/A — no page
- Error behavior: N/A — no page; NV-04's identical not-found answer asserted by SM-23
- State preservation: N/A — no page; SM-13 asserts no form data or S3 or S4 value in browser storage and history unreadable after every authentication boundary
- Mobile behavior: N/A — no page

**ACCEPTANCE CRITERIA:**

1. Every element of every H8 row is its own passing case, SM-01–SM-25 each pass, and a case that cannot run is BLOCKED and reported, never N/A (SECURITY_TEST_MATRIX §1, §3).
2. A Company-A-only Admin gets not found, identical in status, wording, layout and timing class to a nonexistent record, on every data path and record route of Company B, while the Admin of A and B succeeds on each (SM-01; SM-23; architecture challenge 1a).
3. Every W4 row and capability class has its positive and denial cases, every Owner-only denial OD-01–OD-15 its cases, and SM-05's reconciliation returns no row after every authorization and concurrency suite (SM-04; SM-05).
4. Every one of the 73 threats has at least one passing case and every one of the 20 data paths its denial case (SECURITY_TEST_MATRIX §4, §7).
5. Every refused command writes only its refusal events and no business row, file, job, cache entry or audit row beyond them (SECURITY_TEST_MATRIX §1).
6. The route audit fails on a route without a declared requirement, a route without a contract or a contract without a route, and the crawl finds no unexpected 404, 405 or 500 on any of the 210 routes (SM-02; TEST_STRATEGY §8; GAP-035).
7. Every one of the 165 interaction contracts and 65 navigation contracts has its test, and each passes (TEST_STRATEGY §8; IG-05).
8. AICWDF §25's nine counts are 0 and recorded for the release integrity report (TEST_STRATEGY §8; RELEASE_PLAN §11).
9. The race cases M-13, M-20–M-25 and M-39–M-46 pass in both orders with real sessions; SM-21's end-of-recovery race is written against the atomic end of BACKUP_RECOVERY TT-F30 in both orders, with GAP-050 OPEN until RV-18 adopts it (SECURITY_TEST_MATRIX §3).
10. The CI gates of H8-15 fail the pipeline on a seeded secret-like string, a vulnerable dependency, a non-lockfile install and an unpinned action — each proved on a fixture, never by committing one (SM-15).
11. The staging-only cases are written with their scripts and recorded as not run until the operator's evidence exists; none is reported as passed from CI (SECURITY_TEST_MATRIX §9; TS-08).
12. Every defect found is sent to its owning Task with its evidence, and no control, case expectation or accepted residual is changed in this Task (AGENT_OPERATING_MODEL; SECURITY_TEST_MATRIX §8).

**TEST REQUIREMENTS:**

- Lint: TL-01 on the suites' code
- Typecheck: TL-02 on the suites' code
- Unit: SM-03's static architecture tests and SM-16's password profile (TL-03)
- Feature: the authentication, credential-link, upload, export and revocation cases over HTTP (TL-04)
- Integration: the race and revocation cases with real PostgreSQL and real sessions, `ReconcileGuards` after every case that writes (TL-05)
- Authorization: TL-06 — every positive and denial case of SM-01, SM-04, SM-05 and SM-25
- Route integrity: TL-07 — SM-02's audit and the crawl of all 210 routes
- Interaction: TL-08 — the 165 INT- tests checked and run
- Browser E2E: the presentation cases of SM-23 and SM-13's browser part (TL-09)
- Localization/language switch: N/A — no copy in this Task; MSG-09 and MSG-10 asserted in Indonesian as written
- Visual breathing room/density: N/A — no page in this Task
- Responsive: N/A — no page in this Task
- Accessibility: N/A — TASK-066's audit
- Production build: TL-13 — SM-08's production configuration check, debug off and no test route
- Security: TL-14 — SM-01–SM-25 with every data path, W4 row, Owner-only denial and threat, and H8-15's gates
- Performance: N/A — TASK-068's budgets; SM-14's limiter values measured as PFM-12 states
- Migration: N/A — no migration; the import's DP-18 cases run within SM-04 and SM-12
- Other: the nine zeros counted for RELEASE_PLAN §11; the P8 handoffs' evidence checked where they are answered

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
- weaken, disable or bypass a control to let a case run, test an accepted risk as if it were removed, or change a case's expected result (SECURITY_TEST_MATRIX §1, §8)
- commit a secret, a vulnerable dependency or a real credential to prove a gate, or call Google or the range service from a test (SM-15; SM-16; SM-20)
- run any case against staging, the VPS or production — the operator's on staging, none on production (SECURITY_TEST_MATRIX §9; INF-01)

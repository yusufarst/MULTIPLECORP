# TASK-010 — Audit and security logging

Status: APPROVED | Updated: 2026-10-20 | Owner: Planning

Approval: [APPR-016](../00-governance/DECISION_LOG.md#appr-016--p11-task-plan-tasks-and-feature-coverage-matrix-approved), explicit Owner approval on 2026-10-20 (DIR-085 OF-07) of this document as committed in the first execution session's approval commit; the approved file hash and the verified conditions are recorded there. Approval changes lifecycle only, not implementation authorization.

Amendment: on 2026-10-24 the Owner's decision [DIR-093](../00-governance/DECISION_LOG.md#dir-093--task-010s-discoveries) (1) places this Task's two writers in Support, so every module's commands — IAM's identity steps among them — call them: the EXPECTED MODULES line; no other line changes. It is a Level 1 record under CHANGE_CONTROL, as the Owner directs.

Written by the executor of run PC-5 on 2026-10-19 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99; the Owner approved P11 with the [Task plan](TASK_PLAN.md) at OG-4 (DIR-085 OF-07). **No Task starts before the freeze record, from which the execution authorization is in force (DIR-085 OF-09).** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** DONE

**RISK CLASS:** HIGH

**EXECUTOR:** AGENT

**OBJECTIVE:**

Write the business audit and the security events as SECURITY §6 states them — in the command's own transaction, with actor, company, action, entity, reason, correlation and before/after —, kept apart, append-only, with their retention and the query classes the activity pages read.

**WHY THIS EXISTS:**

Every sensitive action keeps its individual actor, time, reason, before/after and linkage, and security events stay separate (EXECUTION_CONTEXT invariant 9; BR-XC-01–BR-XC-03; LG-01–LG-07); an audit lost after commit breaks AU-25's recovery and every correction's proof.

**DEPENDS ON:**

- TASK-005 — Schema foundations — IAM and OPS tables, history and types

**BLOCKS:**

- TASK-011 — Authorization core — capabilities, roles, grants, company scope and the route audit
- TASK-013 — Command envelope, command identity, idempotency and the lock order
- TASK-017 — Password sign-in, sessions and credential links
- TASK-020 — Installation step, the first Owner and the Owner-recovery path
- TASK-031 — Correction cases
- TASK-061 — Activity history and security log
- TASK-075 — Monitoring — the business heartbeat, alerts and the uptime check

**PARALLEL SAFE:** NO — every command Task depends on the audit writer

**IN SCOPE:**

- the business audit writer for `audit_events`, called inside each command's transaction (LG-01), with the actor attribution and correction linkage BR-XC-01–BR-XC-03 require
- the security event writer for `security_events` and the events LG-02–LG-07 name, kept apart from the business audit
- retention and log-file permissions as H9-08 states, within the disk budget
- the query classes PG-063 and PG-064 read, under their capabilities `audit.view` and `security_log.view` (OWNER_ONLY), built as read models only

**OUT OF SCOPE:**

- the activity and security log pages (TASK-061)
- the alert delivery of security events to people (TASK-075, TASK-080)

**REFERENCES:**

- [SECURITY §6](../05-security/SECURITY.md#6-audit-and-security-logging)
- [BUSINESS_RULES §1](../02-domain/BUSINESS_RULES.md#1-cross-cutting-rules) BR-XC-01–BR-XC-03
- [DATABASE §23](../04-architecture/DATABASE/s20-24-index-storage-migration.md#23-audit-persistence)
- [SECURITY §10](../05-security/SECURITY.md#10-handoff-obligations) H9-08
- the architecture challenge's sequences 10a–10b ([P11 gate record](evidence/P11_QUALITY_GATE.md#whole-system-architecture-challenge-sp-98))
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (35): O-06; CAP-13; REF-049; BR-XC-01–BR-XC-03; C-53; LG-01–LG-07; TM-01; TM-05–TM-06; TM-44; TM-48; TM-51–TM-53; TM-57; TM-60; TM-63; TM-66–TM-67; TM-70–TM-73; H8-20; H9-08; HO-38; GAP-016

**EXPECTED MODULES / FILE AREAS:**

- the audit and security-event writers shared in `app/Support` (ARCHITECTURE §4; DIR-093); the OPS module's query classes

**BUSINESS RULES:**

- BR-XC-01–BR-XC-03: every committed business action records its actor, time, action, entity, before/after and reason; corrections keep their linkage

**ARCHITECTURE RULES:**

- the writers are infrastructure services called by actions (ARCHITECTURE §5); no model event hides an audit write (ARCHITECTURE §2)

**SECURITY / PERMISSION REQUIREMENTS:**

- the threats of [SECURITY §8](../05-security/SECURITY.md#8-threat-model) mapped to this Task, each with the controls its row names: TM-01, Brute force and credential stuffing (AU-03, AU-05, AU-16, AU-18, WS-11, LG-07); TM-05, Credential-link interception, replay or leakage in logs, Referer, history or flashed input (AU-11, AU-12, LG-03, LG-04); TM-06, Owner-account takeover (AU-16, AU-18, AU-02, AU-03, AU-05, AU-15, AU-20, LG-07); TM-44, Backdating used to evade audit (LG-01); TM-48, Secrets in logs, repository or client bundles (LG-03, SX-01, SX-06); TM-51, Log injection through user input (LG-04); TM-52, Credential link consumed by someone other than its holder — the issuing Owner or anyone who saw the delivery message — to set the password and act as the account (AU-11, AU-12, AU-13, PJ-17, LG-02); TM-53, Allocation probing: dispatching single units from another company's lots to read their allocated cost, then reversing (RG-08, XL-1, LG-07); TM-57, Operator abuse of the Owner-recovery command, which now also resets TOTP and unlinks Google, and of the OWNER-restoration command, whose honest use also reverses a removal of a pre-window Owner made legitimately during the compromise window, as a restored state does for an Owner removed legitimately between its backup and the window's start (AU-14, AU-20, AU-25, LG-07, H9-06, H9-07, H9-14, RV-04); TM-60, Google link takeover — an attacker's Google identity linked to a victim's account, a victim's identity linked to an attacker's account, or a link derived from a matching e-mail (AU-23, AU-22, AU-24, LG-07); TM-63, Guessing TOTP or recovery codes from one or many addresses, concurrently or in sequence (AU-16, AU-05, AU-19, LG-07); TM-66, Theft or reuse of recovery codes — viewed on an unattended session, then used to replace the device (AU-19, AU-05, AU-20, LG-07); TM-67, Enrolment bypass or hijack — a high-risk account acting without TOTP, or a password holder enrolling a device before the person (AU-18, AU-16, AU-12, AU-13, LG-07); TM-70, Real-time phishing that relays the password, or a Google sign-in, together with the TOTP code (AU-16, AU-22, LG-07); TM-71, The breached-password check unavailable or tampered with (AU-03, LG-07); TM-72, One phone holding both the person's Google session and the authenticator, so that whoever holds it unlocked can pass both steps (AU-22, AU-20, AU-24, LG-07); TM-73, Anyone who can reach an account's second-factor step — its password holder, a browser still signed in to the person's linked Google account, or an abandoned authenticated session — failing codes on purpose to delay that account's sign-in (AU-05, AU-20, AU-24, AU-10, LG-07)
- security events never mixed with business audit; no secret, token, password or TOTP value in either (LG-03–LG-05 as stated); the log files readable only by the operator account (H9-08)

**COMPANY SCOPE AND FIELD PROJECTION:**

- audit rows carry their company; the read classes of PG-063 and PG-064 follow PJ-11 and PJ-21 (TASK-012 applies them)

**CONCURRENCY / IDEMPOTENCY:**

- the audit row commits or rolls back with its command — one transaction (TX-; LG-01)
- security events written outside business transactions, so that a refused or rolled-back command's event persists, and a failed security-event write never rolls a command back or changes its outcome, logged with its correlation id (LG-02, LG-04)
- the two TECH-022 kinds — EVIDENCE_DUPLICATE_UNDISCLOSED and IMPORT_COLLISION_UNDISCLOSED — written only after the command's outcome is durable, as one insert that references no row and takes no lock, under the partial UNIQUE key (`event_kind`, `command_id`, `related_public_id`) with `ON CONFLICT DO NOTHING`, so that a repeated attempt adds nothing (LG-02)

**AUDIT:**

- this Task builds the writers; every later command Task calls them and proves its own events

**DATABASE IMPACT ASSESSMENT:**

- Schema change: NONE — uses TASK-005
- Migration required: NO
- Existing data transformation: NONE
- Direct production DB access: FORBIDDEN
- Data-loss risk: NONE KNOWN

**MODULARITY / EXTENSIBILITY:**

- Module boundary affected: OPS, tier 0
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

- Context7: REQUIRED — the framework's database transaction and logging facilities
- Graphify: N/A — the codebase is young
- Browser E2E: N/A — no page
- Other: none
- Missing safe tool behavior: AUTO-INSTALL project-locally inside this Task (AICWDF §6.3) and record it in TOOLCHAIN; a paid tier or a privileged installation STOPS for the Owner (COST_POLICY)

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: N/A — the codebase is too young for a graph to add value; the impact is read from the diff and ARCHITECTURE §3–§4
- Pre-change questions: which later commands call the writers
- Expected affected areas: the IN SCOPE areas only

**CONTEXT7:**

- Topics to verify: the framework's log channels and file permissions
- Version-sensitive assumptions: none from memory

**UI / UX REQUIREMENTS:**

- Follow global design rules: N/A — no page — the pages are TASK-061's
- Indonesian default: N/A — no page — the pages are TASK-061's
- English support: N/A — no page — the pages are TASK-061's
- Plain-language Indonesian: N/A — no page — the pages are TASK-061's
- Language switch impact: NONE
- Mobile-first: N/A — no page — the pages are TASK-061's
- Responsive: N/A — no page — the pages are TASK-061's
- Adaptive: N/A — no page — the pages are TASK-061's
- Visual breathing room: N/A — no page — the pages are TASK-061's
- Comfortable density: N/A — no page — the pages are TASK-061's
- shadcn/ui reuse: N/A — no page — the pages are TASK-061's
- Design reference: N/A — no page — the pages are TASK-061's
- Primary action: N/A — no page — the pages are TASK-061's
- Secondary actions: N/A — no page — the pages are TASK-061's
- Destructive actions: N/A — no page — the pages are TASK-061's
- Redundancy constraints: N/A — no page — the pages are TASK-061's
- Loading state: N/A — no page — the pages are TASK-061's
- Empty state: N/A — no page — the pages are TASK-061's
- Error state: N/A — no page — the pages are TASK-061's

**ROUTE IMPACT:**

- none — the read routes are TASK-061's

**INTERACTION CONTRACTS:**

- N/A — none

**NAVIGATION CONTRACT:**

- Parent: N/A — no page
- Entry: N/A — no page
- Back action: N/A — no page
- Cancel: N/A — no page
- Success destination: N/A — no page
- Error behavior: N/A — no page
- State preservation: N/A — no page
- Mobile behavior: N/A — no page

**ACCEPTANCE CRITERIA:**

1. A test command writes its business audit row in its own transaction: a rollback leaves no audit row, and a commit always has one, also when the process ends right after the commit (LG-01; architecture challenge 10b).
2. Security events are written for the cases LG-02–LG-07 name and never into `audit_events`.
3. A refused or rolled-back command's security event persists; a failed security-event write changes no outcome and is logged with its correlation id; the two TECH-022 kinds are written after the outcome is durable, under their partial UNIQUE key, and a repeated attempt adds nothing (LG-02).
4. No secret, password, token or code value appears in any audit or security row or log line.
5. The query classes return rows only through the capabilities `audit.view` and `security_log.view`.

**TEST REQUIREMENTS:**

- Lint: TL-01
- Typecheck: TL-02
- Unit: the before/after and linkage builders (TL-03)
- Feature: N/A — no endpoint
- Integration: audit in the same transaction: commit and rollback; a refused and a rolled-back command's security event persisting; a failed security-event write changing no outcome; the TECH-022 kinds written after the outcome, a repeat adding nothing (TL-05; LG-02)
- Authorization: the query classes refuse without their capability (TL-06)
- Route integrity: N/A — no route added or changed
- Interaction: N/A — no INT- contract in this Task
- Browser E2E: N/A — no page in this Task
- Localization/language switch: N/A — no copy
- Visual breathing room/density: N/A — no page in this Task
- Responsive: N/A — no page in this Task
- Accessibility: N/A — no page in this Task
- Production build: TL-13
- Security: no secret in a log line; the log file's permissions (TL-14; H9-08)
- Performance: the query classes within their QB- budgets once their pages exist (TL-15)
- Migration: N/A — the tables are TASK-005's
- Other: GAP-016's audit ordering checked across a midnight boundary

**CRITERIA DISPOSITIONS (DIR-093):**

- Criteria 1–5 as their tests prove them, the two writers shared in `app/Support` (DIR-093 (1); APPR-022) and the query classes in OPS. Criterion 3's TECH-022 insert references no row of the matched records; its `user_id` keeps TASK-005's foreign key, read within the approved text (DIR-093 (2)). Writing the TECH-022 events after the response is sent is their callers' step (LG-02), not claimed here.

**TEST CHECKLIST:**

- [x] Lint: Pint and ESLint PASS, locally and in CI (TL-01)
- [x] Typecheck: Larastan/PHPStan at level `max` and the TypeScript compiler PASS on the workstation and in CI (TL-02)
- [x] Relevant automated tests: Unit — the before/after and linkage builders, redaction, keyed hashes, user-agent summary (TL-03) —; Integration — the audit's commit, rollback, refused write and process end right after the commit (10b); the security events after a rollback, a refusal, a savepoint and a held account lock; a failed write logged with the correlation id; the TECH-022 kinds once after the commit, none on a rollback; the 39 kinds apart from the audit; GAP-016's order across a WIB midnight, the keyset cursor and the backdated gap (TL-05) —; Architecture; PASS locally and in CI
- [x] Authorization: the two query classes refuse without `audit.view` and `security_log.view`, and one capability does not open the other read (TL-06)
- [x] Route integrity: N/A — no route added or changed
- [x] Interaction behavior: N/A — no INT- contract
- [x] Browser E2E if required: N/A — no page; the smoke run still PASS on Chromium and WebKit in CI
- [x] Responsive/adaptive if required: N/A — no page
- [x] Visual breathing room/density if UI: N/A — no page
- [x] Production build: TL-13 — the frontend build and the production caches and configuration — PASS on the workstation and in CI
- [x] Security checks if required: no secret, token, password or code value in an audit row, a security event or a log line — redacted keys, keyed HMACs under a dedicated key refused when it is APP_KEY, short or a repository placeholder in production, a logged exception without bound values —; line breaks neutralized (TM-51); the log file mode 0600 and a bounded rotation (H9-08); the secret-like scan and both dependency audits PASS (TL-14)
- [x] Database safety review: no migration and no schema change; the writers insert only into TASK-005's `audit_events` and `security_events` under the runtime's INSERT and SELECT; a second runtime connection, `security`, for the events; local and CI databases only, no production connection
- [x] Final diff review: each writer and read against SECURITY §6 and DATABASE §23 as written, the gaps raised as discoveries and decided by the Owner (DIR-093, APPR-022), nothing invented; ER-06's independent review, five of its six findings fixed and tested, the sixth decided as DIR-093 (1)

**COMPLETION EVIDENCE:**

- Test outputs (TEST_STRATEGY §16), tested commit `6572563`: fresh CI on GitHub's `ubuntu-24.04` runner, runs 40103084563 (push) and 40103088282 (pull request #11), the three jobs PASS in each — "Verify" (every check of `scripts/verify.sh`, the Integration suite and Larastan/PHPStan included), "Browser" (TL-09–TL-12 on Chromium and WebKit) and "Performance and migration" (TL-15, TL-16 from empty); before it, the build commit `49cf1a5`, runs 40091988863 and 40092011893, PASS. On the Owner's workstation, 2026-10-24: Unit 201, `tests/Integration/Audit/` 29, Authorization 5, Feature 25 and Architecture 48, Pint, Larastan/PHPStan and the repository gates PASS, after ER-06's fixes and after DIR-093's move
- Build result: `TL-13 Frontend build: PASS` and `TL-13 Production caches and configuration: PASS — Evidence: 6572563, CI ubuntu-24.04 run 40103084563`
- Route evidence: N/A — no route added or changed; the correlation-id middleware is global and adds no route
- Interaction evidence: N/A — no INT- contract
- Browser evidence: N/A — no page or journey; the smoke run still passes on both engines (`TL-09 E2E/browser: PASS — Evidence: 6572563, CI ubuntu-24.04 run 40103084563`)
- Localization evidence: N/A — no page or copy
- Cost evidence/exception if applicable: ADDITIONAL RECURRING COST CHANGE: NONE — nothing installed or hosted
- Files changed: `app/Support/Audit/` and `app/Support/SecurityLog/` (the writers, builders, enumerations and keyed hashes), `app/Support/CorrelationId.php`, `app/Support/Redaction.php`, `app/Support/Database/Transactions.php`, `app/Support/Logging/StructuredLog.php`, `app/Http/Middleware/AssignCorrelationId.php`, `bootstrap/app.php`, `app/Modules/OPS/Queries/` and its README, `config/database.php`, `config/logging.php`, `config/security.php`, `.env.example`, `phpunit.xml`; tests — `tests/Unit/Support/`, `tests/Integration/Audit/`, `tests/Authorization/LogQueriesTest.php`, `tests/Feature/TechnicalLogTest.php`, `tests/TestCase.php`, `tests/Support/RefreshDatabase.php`, `tests/Support/Concurrency/Sessions.php` —; TOOLCHAIN's row; DIR-093's and APPR-022's records — the source record, DECISION_LOG, DECISION_INDEX, ARCHITECTURE §3, §4 and §7 —; this file, TASK_PLAN, CURRENT_HANDOFF and CHANGELOG
- Commit SHA: `49cf1a5` and `6572563`, the Owner's identity, no signature and no AI attribution; this record's commit follows them on `task/TASK-010-audit-security-logging`
- Notes: criteria as CRITERIA DISPOSITIONS states. Environment: the Owner's Windows workstation, Claude Code Desktop, 2026-10-24 (TOOLCHAIN, "Execution environments (TASK-001)"); Context7 not available, each framework facility verified from the installed sources and by the tests (TOOLCHAIN's TASK-010 row). Readings within the approved text are listed in CURRENT_HANDOFF. Each non-applicable item above carries its reason; TASK_PLAN, this file and CURRENT_HANDOFF updated for DONE

**SAFE NEXT TASK:**

- TASK-011 — when its other dependencies are DONE and its READY gate holds

**DO NOT DO:**

- add to V1 — a capability, page, route, field, integration, document type, report or paid service — or drop, defer or narrow a V1_REQUIRED requirement; a Task that would need either raises an Owner question and waits ([DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-20)
- access the VPS, production, its data or a credential, or put a secret, real data, a host detail or production configuration in the repository ([INFRASTRUCTURE](../09-operations/INFRASTRUCTURE.md) INF-01, INF-05; SECURITY SX-01, SX-04, SX-06; [PRODUCTION_DATA_SAFETY](../00-governance/PRODUCTION_DATA_SAFETY.md))
- add AI, model or tool attribution to a commit — the Owner's configured identity only ([AGENTS.md](../../AGENTS.md#git-commit-attribution); DIR-022)
- start before the Owner's execution authorization at OG-4, or mark DONE without the required tests passing and the completion evidence ([TASK_PLAN](TASK_PLAN.md#2-rules-of-the-plan); [DEFINITION_OF_DONE](../08-testing/DEFINITION_OF_DONE.md#2-the-checklist))
- weaken, skip or delete a valid test to obtain a green run, or adopt a paid tier without the Owner's COST_POLICY decision (DOD-05; [COST_POLICY](../00-governance/COST_POLICY.md))
- write an audit row outside the command's transaction
- log a secret, password, token, TOTP secret or recovery code

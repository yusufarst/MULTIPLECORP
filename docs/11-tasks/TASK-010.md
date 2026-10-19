# TASK-010 — Audit and security logging

Status: REVIEW | Updated: 2026-10-20 | Owner: Planning

Written by the executor of run PC-5 on 2026-10-19 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99 — a REVIEW draft of P11, approved, or not, with the [Task plan](TASK_PLAN.md) at OG-4. **No Task starts before the Owner's execution authorization at OG-4.** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** TODO

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
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (31): BR-XC-01–BR-XC-03; C-53; LG-01–LG-07; TM-01; TM-05–TM-06; TM-44; TM-48; TM-51–TM-53; TM-57; TM-60; TM-63; TM-66–TM-67; TM-70–TM-73; H8-20; H9-08; GAP-016

**EXPECTED MODULES / FILE AREAS:**

- the OPS module's audit and security-event services; their query classes

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
3. No secret, password, token or code value appears in any audit or security row or log line.
4. The query classes return rows only through the capabilities `audit.view` and `security_log.view`.

**TEST REQUIREMENTS:**

- Lint: TL-01
- Typecheck: TL-02
- Unit: the before/after and linkage builders (TL-03)
- Feature: N/A — no endpoint
- Integration: audit in the same transaction: commit and rollback (TL-05)
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

- TASK-011 — when its other dependencies are DONE and its READY gate holds

**DO NOT DO:**

- add to V1 — a capability, page, route, field, integration, document type, report or paid service — or drop, defer or narrow a V1_REQUIRED requirement; a Task that would need either raises an Owner question and waits ([DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-20)
- access the VPS, production, its data or a credential, or put a secret, real data, a host detail or production configuration in the repository ([INFRASTRUCTURE](../09-operations/INFRASTRUCTURE.md) INF-01, INF-05; SECURITY SX-01, SX-04, SX-06; [PRODUCTION_DATA_SAFETY](../00-governance/PRODUCTION_DATA_SAFETY.md))
- add AI, model or tool attribution to a commit — the Owner's configured identity only ([AGENTS.md](../../AGENTS.md#git-commit-attribution); DIR-022)
- start before the Owner's execution authorization at OG-4, or mark DONE without the required tests passing and the completion evidence ([TASK_PLAN](TASK_PLAN.md#2-rules-of-the-plan); [DEFINITION_OF_DONE](../08-testing/DEFINITION_OF_DONE.md#2-the-checklist))
- weaken, skip or delete a valid test to obtain a green run, or adopt a paid tier without the Owner's COST_POLICY decision (DOD-05; [COST_POLICY](../00-governance/COST_POLICY.md))
- write an audit row outside the command's transaction
- log a secret, password, token, TOTP secret or recovery code

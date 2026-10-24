# TASK-011 — Authorization core — capabilities, roles, grants, company scope and the route audit

Status: APPROVED | Updated: 2026-10-20 | Owner: Planning

Approval: [APPR-016](../00-governance/DECISION_LOG.md#appr-016--p11-task-plan-tasks-and-feature-coverage-matrix-approved), explicit Owner approval on 2026-10-20 (DIR-085 OF-07) of this document as committed in the first execution session's approval commit; the approved file hash and the verified conditions are recorded there. Approval changes lifecycle only, not implementation authorization.

Written by the executor of run PC-5 on 2026-10-19 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99; the Owner approved P11 with the [Task plan](TASK_PLAN.md) at OG-4 (DIR-085 OF-07). **No Task starts before the freeze record, from which the execution authorization is in force (DIR-085 OF-09).** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** DONE

**RISK CLASS:** HIGH

**EXECUTOR:** AGENT

**OBJECTIVE:**

Build the server-side authorization pipeline — authentication → company scope → capability → resource → preconditions —, the 80 capabilities, roles and grants, the company-scope resolver, the Owner-only denials and the route audit with every route's declared capability.

**WHY THIS EXISTS:**

Company isolation and default-deny on the server are invariant 2 of EXECUTION_CONTEXT (AZ-01–AZ-13; CS-01–CS-08; OD-01–OD-16; EN-01–EN-08); OB-03 requires every route to declare its capability, and SM-01–SM-05 test it.

**DEPENDS ON:**

- TASK-005 — Schema foundations — IAM and OPS tables, history and types
- TASK-010 — Audit and security logging

**BLOCKS:**

- TASK-012 — Field projection, read classes and cross-company links
- TASK-013 — Command envelope, command identity, idempotency and the lock order
- TASK-016 — Application shell, navigation and the company context

**PARALLEL SAFE:** NO — every route and command Task relies on it

**IN SCOPE:**

- the capability catalogue of PERMISSIONS_MATRIX §4 — 80 capabilities with their classes and prerequisites —, roles and grants (RG-), and the WORKFLOWS §4 mapping (W4-)
- the authorization order AZ-01–AZ-13 and the enforcement architecture EN-01–EN-08, default deny
- the company-scope resolver of CS-01–CS-08: every public identifier resolved through it, the scope filter inside queries
- the Owner-only denials OD-01–OD-16, OWNER_ONLY capabilities never grantable
- the route audit: every route declares its capability, "every active account (S0)", "SELF", "the enrolment-only session" or EN-03's "none" (OB-03; RC-; IG-01); an undeclared route fails the build (SM-02)
- the revocation races of RV- with TASK-013 (in-command re-authorization)

**OUT OF SCOPE:**

- field projection and read classes (TASK-012)
- the users and access pages (TASK-024)
- authentication itself (TASK-017–TASK-019)

**REFERENCES:**

- [PERMISSIONS_MATRIX §2](../05-security/PERMISSIONS_MATRIX.md#2-authorization-model)
- [PERMISSIONS_MATRIX §3](../05-security/PERMISSIONS_MATRIX.md#3-role-and-grant-model)
- [PERMISSIONS_MATRIX §4](../05-security/PERMISSIONS_MATRIX.md#4-capability-catalogue)
- [PERMISSIONS_MATRIX §5](../05-security/PERMISSIONS_MATRIX.md#5-workflows-4-mapping)
- [PERMISSIONS_MATRIX §6](../05-security/PERMISSIONS_MATRIX.md#6-company-scope-rules)
- [PERMISSIONS_MATRIX §9](../05-security/PERMISSIONS_MATRIX.md#9-owner-only-denials)
- [PERMISSIONS_MATRIX §11](../05-security/PERMISSIONS_MATRIX.md#11-data-path-coverage-matrix)
- [SECURITY §3](../05-security/SECURITY.md#3-enforcement-architecture)
- [ROUTE_CONTRACTS §1](../03-workflows/ROUTE_CONTRACTS.md#1-conventions) RC-01–RC-06
- [INTERACTION_CONTRACTS §1](../03-workflows/INTERACTION_CONTRACTS.md#1-conventions) IG-01–IG-05
- [CONCURRENCY_IDEMPOTENCY §16](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s14-17-numbering-jobs-revocation.md#16-authorization-and-revocation-races)
- the architecture challenge's sequences 1a, 8a–8b ([P11 gate record](evidence/P11_QUALITY_GATE.md#whole-system-architecture-challenge-sp-98))
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (304): O-01; O-06; CAP-01; CAP-13; AC-13; OS-03; REF-002–REF-004; REF-056; IMG-01; OWN-02; BR-ACC-01–BR-ACC-03; L-03; RC-01–RC-06; IG-01–IG-05; C-32; C-40; C-54; AZ-01–AZ-13; RG-01–RG-12; CS-01–CS-08; OD-01–OD-16; DP-01–DP-20; W4-01–W4-27; D-PM-01–D-PM-12; EN-01–EN-08; WS-02; TM-09–TM-12; TM-15; TM-17; TM-19; TM-27; TM-29–TM-36; TM-45; TM-56; D-SEC-01–D-SEC-05; D-SEC-07–D-SEC-13; D-SEC-19; H6-01; H6-08; H8-02–H8-05; RV-01–RV-18; RB-01–RB-02; RB-04–RB-06; RB-09; UXN-10; UXI-03; UXI-15–UXI-16; IP-06; IP-21; UXS-22–UXS-23; UXS-25; SM-01–SM-05; capability stock.view; capability projects.view; capability finance.view; capability cost.view; capability profit.view; capability evidence.view; capability reports.export; capability reports.consolidated; capability review.view; capability audit.view; capability security_log.view; capability scope.all_companies; capability users.manage; capability permissions.manage; capability companies.manage; capability tax.configure; capability requirements.set_waiver_eligibility; capability requirements.relax; capability writeoff.supersede; capability stock.attribute_surplus; capability stock.resolve_unattributed; capability receivables.write_off; capability projects.force_complete; capability import.commit; capability parties.manage; capability products.edit; capability warehouse.manage; capability projects.manage; capability quotation.manage; capability projects.confirm; capability stock.reserve; capability purchase.manage; capability stock.receive; capability stock.dispatch; capability stock.move; capability stock.count; capability delivery.record; capability dropship.confirm; capability handover.record; capability documents.issue; capability evidence.upload; capability invoice.issue; capability billing.record; capability payment.record; capability payment.apply; capability expense.record; capability disbursement.record; capability cashin.record; capability bank_statement.record; capability requirements.manage; capability projects.complete; capability receivables.dispute; capability requirements.waive; capability import.prepare; capability stock.reverse; capability sales_return.record; capability purchase_return.record; capability purchase.correct_cost; capability finance.contra; capability payment.reallocate; capability invoice.correct; capability documents.correct; capability billing.correct; capability decisions.supersede; capability fulfillment.correct; capability warnings.override; capability cash.correct; capability purchase.cancel; capability refund.record; capability settlement.record; capability stock.adjust; capability stock.condition; capability stock.count_apply; capability stock.resolve_unattributed_evidence; capability stock.allocate_intercompany; capability projects.cancel_scope; capability delivery.close; capability purchase.close_remainder; capability cases.close; capability projects.cancel; GAP-032; GAP-035

**EXPECTED MODULES / FILE AREAS:**

- the IAM module's capability registry, policies and the scope resolver; the route-audit test

**BUSINESS RULES:**

- BR-ACC-01–BR-ACC-03: the access boundary

**ARCHITECTURE RULES:**

- authorization re-checked inside each action (ARCHITECTURE §5, §8); controllers authorize, never decide business rules

**SECURITY / PERMISSION REQUIREMENTS:**

- the threats of [SECURITY §8](../05-security/SECURITY.md#8-threat-model) mapped to this Task, each with the controls its row names: TM-09, Mass assignment of company, authority or state fields (WS-01, WS-02, DP-03); TM-10, Forged reference to another company's record in a payload (CS-04, C-32); TM-11, Direct URL or public_id of a Company B record (AZ-11, DP-01); TM-12, Nested or related resources of Company B (CS-04, CS-07); TM-15, Search results or counts of Company B (DP-06, CS-07); TM-17, Report filters naming Company B (DP-08, CS-08); TM-19, Inertia partial reload, deferred props or shared props (WS-07, DP-15, CS-07); TM-27, Error messages, validation or unique import keys as an existence oracle (WS-10, AZ-11, CS-04, DP-18, DP-20); TM-29, Revoked grant or deactivated account with an open session or a queued export (AZ-03, AU-09, DP-09, DP-14); TM-30, Admin or API Force Complete (OD-12); TM-31, Admin write-off or its supersession (OD-07, OD-11); TM-32, Admin found-stock attribution (OD-08); TM-33, Admin waiver eligibility or required-item relaxation (OD-05, OD-06); TM-34, Admin unattributed-loss resolution without definitive evidence (OD-09, OD-10); TM-35, Self-granting, an OWNER_ONLY capability through a role mapping, or demoting the last Owner (OD-02, RG-02, RG-09, OD-16); TM-36, ADM without ADM+ performing corrections (AZ-05, RG-08); TM-45, Shared accounts or impersonation hiding the actor (AZ-12, AU-01, AU-17); TM-56, Loss, disposal or count loss booked on another company's lot without a grant on it (AZ-08, OD-10)
- default deny; a capability never inferred from the UI (IG-01); OWNER_ONLY never grantable (OD-); the route audit is a build gate (SM-02)

**COMPANY SCOPE AND FIELD PROJECTION:**

- CS-01–CS-08 applied in every resolver and query; MASTER and POOL records as CS-05 and CS-06 state; denial and not-found presented as IP-21 states without an oracle

**CONCURRENCY / IDEMPOTENCY:**

- a grant revoked during a command is refused by the in-command re-authorization (RV-; SQ-22)

**AUDIT:**

- grant, role and capability changes are audited by their commands (TASK-024); denials of note are security events (LG-)

**DATABASE IMPACT ASSESSMENT:**

- Schema change: NONE — uses TASK-005
- Migration required: NO
- Existing data transformation: NONE
- Direct production DB access: FORBIDDEN
- Data-loss risk: NONE KNOWN

**MODULARITY / EXTENSIBILITY:**

- Module boundary affected: IAM, tier 0 — the authorization boundary of ARCHITECTURE §8
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
- Authorization change: PROPOSED — builds the designed authorization of P5, nothing beyond it
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

- Context7: REQUIRED — the framework's gates, policies and route middleware
- Graphify: N/A — the codebase is young
- Browser E2E: N/A — no page
- Other: none
- Missing safe tool behavior: AUTO-INSTALL project-locally inside this Task (AICWDF §6.3) and record it in TOOLCHAIN; a paid tier or a privileged installation STOPS for the Owner (COST_POLICY)

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: N/A — the codebase is too young for a graph to add value; the impact is read from the diff and ARCHITECTURE §3–§4
- Pre-change questions: which routes and actions each capability guards
- Expected affected areas: the IN SCOPE areas only

**CONTEXT7:**

- Topics to verify: policy registration and route middleware in the installed framework
- Version-sensitive assumptions: none from memory

**UI / UX REQUIREMENTS:**

- Follow global design rules: N/A — no page — the shell shows capabilities as hints only (TASK-016)
- Indonesian default: N/A — no page — the shell shows capabilities as hints only (TASK-016)
- English support: N/A — no page — the shell shows capabilities as hints only (TASK-016)
- Plain-language Indonesian: N/A — no page — the shell shows capabilities as hints only (TASK-016)
- Language switch impact: NONE
- Mobile-first: N/A — no page — the shell shows capabilities as hints only (TASK-016)
- Responsive: N/A — no page — the shell shows capabilities as hints only (TASK-016)
- Adaptive: N/A — no page — the shell shows capabilities as hints only (TASK-016)
- Visual breathing room: N/A — no page — the shell shows capabilities as hints only (TASK-016)
- Comfortable density: N/A — no page — the shell shows capabilities as hints only (TASK-016)
- shadcn/ui reuse: N/A — no page — the shell shows capabilities as hints only (TASK-016)
- Design reference: N/A — no page — the shell shows capabilities as hints only (TASK-016)
- Primary action: N/A — no page — the shell shows capabilities as hints only (TASK-016)
- Secondary actions: N/A — no page — the shell shows capabilities as hints only (TASK-016)
- Destructive actions: N/A — no page — the shell shows capabilities as hints only (TASK-016)
- Redundancy constraints: N/A — no page — the shell shows capabilities as hints only (TASK-016)
- Loading state: N/A — no page — the shell shows capabilities as hints only (TASK-016)
- Empty state: N/A — no page — the shell shows capabilities as hints only (TASK-016)
- Error state: N/A — no page — the shell shows capabilities as hints only (TASK-016)

**ROUTE IMPACT:**

- no business route; the route audit runs over every route the later Tasks register

**INTERACTION CONTRACTS:**

- IG-01–IG-05 enforced as conventions: a control is present with its capability and absent without it, never disabled

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

1. Every route registered declares what admits it — a capability, every active account (S0), SELF, the enrolment-only session or EN-03's none (OB-03) —; one without a declaration fails the build.
2. A Company-A-only Admin is refused on every data path of Company B (SM-01 for the paths that exist).
3. Each OD- denial holds for every non-Owner, and no OWNER_ONLY capability can be granted.
4. A grant revoked while a page is open and a command is in flight refuses the command at its in-command re-authorization, and the page shows the denial (SQ-22; the RV- races; IP-21; architecture challenge 8a, with TASK-013 and TASK-016).
5. Each W4- row's capability class is enforced (SM-04).

**TEST REQUIREMENTS:**

- Lint: TL-01
- Typecheck: TL-02
- Unit: the resolver and the order AZ-01–AZ-13 (TL-03)
- Feature: denials on sample routes (TL-04)
- Integration: the scope filter inside a query before pagination (TL-05; CS-07)
- Authorization: SM-01–SM-05 for the paths that exist; each later Task extends them (TL-06)
- Route integrity: the route audit with OB-03's declarations (TL-07; SM-02)
- Interaction: IG-01's presence rule on a sample control (TL-08)
- Browser E2E: N/A — no page
- Localization/language switch: N/A — no user-facing copy in this Task
- Visual breathing room/density: N/A — no page in this Task
- Responsive: N/A — no page in this Task
- Accessibility: N/A — no page in this Task
- Production build: TL-13
- Security: the architecture tests of SM-03 (TL-14)
- Performance: the resolver adds no per-row query (PF-; TL-15)
- Migration: N/A — no migration or import code in this Task
- Other: the RV- races as two-connection tests

**CRITERIA DISPOSITIONS (DIR-094):**

- Criteria 1–3 and 5 as their tests prove them, for the routes and data paths that exist. Criterion 4's in-command re-authorization (RV-01–RV-03) is proven under two connections; the command envelope that calls it is TASK-013's and the page that shows the denial TASK-016's, as the criterion names them. A contextual prerequisite is its action's check and a command's company an Admin's need (DIR-094 (E2), (E3)); the catalogue's rows reach the server through the sync INFRASTRUCTURE §5 and TASK-020 now run (DIR-094 (E1); APPR-023).

**TEST CHECKLIST:**

- [x] Lint: Pint and ESLint PASS, locally and in CI (TL-01)
- [x] Typecheck: Larastan/PHPStan at level `max` and the TypeScript compiler PASS on the workstation and in CI (TL-02)
- [x] Relevant automated tests: Unit — the catalogue's counts, classes, prerequisites and WORKFLOWS §4 rows (TL-03) —; Integration — the catalogue's rows, the resolver (Owner, default deny, prerequisites, a command's company, OWNER_ONLY anomalies, deactivation, per-request reads), the scope inside the query and around its or-conditions, CS-04's one answer, RV-03 under two connections (TL-05) —; Feature — denials on sample routes (TL-04) —; Architecture; PASS locally and in CI
- [x] Authorization: SM-04 for each of the 80 capabilities by class, RG-08, default deny and SM-05's reconciliation of OWNER_ONLY grants (TL-06); SM-01 on the data paths that exist
- [x] Route integrity: SM-02's route audit over every registered route, and each refused declaration on fixture routes (TL-07)
- [x] Interaction behavior: IG-01's presence rule on a sample control through the shared capabilities (TL-08)
- [x] Browser E2E if required: N/A — no page; the smoke run still PASS on Chromium and WebKit in CI
- [x] Responsive/adaptive if required: N/A — no page
- [x] Visual breathing room/density if UI: N/A — no page
- [x] Production build: TL-13 — the frontend build and the production caches and configuration — PASS on the workstation and in CI
- [x] Security checks if required: SM-03's scans — no role-name check outside the resolver and the catalogue sync, no global-scope bypass —; default deny; an out-of-scope record answering exactly as a missing one, the capability checked before any route binding; denials and anomalies as security events (LG-02); the secret-like scan and both dependency audits PASS (TL-14)
- [x] Database safety review: no migration and no schema change; the catalogue sync only inserts, under the runtime's SELECT and INSERT on the catalogue tables, refusing a stored row it does not list; the re-authorization's row lock under the runtime's column UPDATE grant on `users`; local and CI databases only, no production connection
- [x] Final diff review: each capability, class, prerequisite and mapping against PERMISSIONS_MATRIX as written, the gap raised as a discovery and decided by the Owner (DIR-094, APPR-023), nothing invented; ER-06's independent review, six of its seven points fixed and tested, the seventh DIR-094 (E2)

**COMPLETION EVIDENCE:**

- Test outputs (TEST_STRATEGY §16), tested commit `dcabec2`: fresh CI on GitHub's `ubuntu-24.04` runner, runs 40129706787 (push) and 40129715438 (pull request #12), the three jobs PASS in each — "Verify" (every check of `scripts/verify.sh`, the Integration suite, the route audit and Larastan/PHPStan included), "Browser" (TL-09–TL-12 on Chromium and WebKit) and "Performance and migration" (TL-15, TL-16 from empty). On the Owner's workstation, 2026-10-25: Unit 206, `tests/Integration/Authorization/` 19, Authorization 88, Routes 11, Feature 29, Interaction 1, Architecture 50 and `tests/Integration/Audit/` 29, Pint, Larastan/PHPStan and the repository gates PASS, after ER-06's fixes
- Build result: `TL-13 Frontend build: PASS` and `TL-13 Production caches and configuration: PASS — Evidence: dcabec2, CI ubuntu-24.04 run 40129706787`
- Route evidence: no business route added; the route audit (SM-02) runs over every registered route — none today — and fails a route without its declaration
- Interaction evidence: N/A — no INT- contract; IG-01's sample control proven by `tests/Interaction/CapabilityHintsTest.php`
- Browser evidence: N/A — no page or journey; the smoke run still passes on both engines (`TL-09 E2E/browser: PASS — Evidence: dcabec2, CI ubuntu-24.04 run 40129706787`)
- Localization evidence: N/A — no page or copy
- Cost evidence/exception if applicable: ADDITIONAL RECURRING COST CHANGE: NONE — nothing installed or hosted
- Files changed: `app/Modules/IAM/` (`Data/`, `Enums/`, `Services/`, `Policies/`, `Queries/` and the README), `app/Console/Commands/SyncAuthorizationCatalogue.php`, `app/Http/Middleware/RequireCapability.php`, `DeclareAccess.php` and `HandleInertiaRequests.php`, `app/Providers/AppServiceProvider.php`, `bootstrap/app.php`, the security events' detail whitelist; the tests of `tests/Unit/IAM/`, `tests/Integration/Authorization/`, `tests/Authorization/`, `tests/Routes/`, `tests/Feature/` and `tests/Interaction/`, `tests/Support/Authorization.php` and `RouteAudit.php`, the architecture scan and its fixture; TOOLCHAIN's row; DIR-094's and APPR-023's records — the source record, DECISION_LOG, DECISION_INDEX, INFRASTRUCTURE §5, TASK-020 —; TASK-012's and TASK-013's READY status; this file, TASK_PLAN, CURRENT_HANDOFF and CHANGELOG
- Commit SHA: `dcabec2`, the Owner's identity, no signature and no AI attribution; this record's commit, with DIR-094's records, follows it on `task/TASK-011-authorization-core`
- Notes: criteria as CRITERIA DISPOSITIONS states. Environment: the Owner's Windows workstation, Claude Code Desktop, 2026-10-25 (TOOLCHAIN, "Execution environments (TASK-001)"); Context7 not available, each framework facility verified from the installed sources and by the tests (TOOLCHAIN's TASK-011 row); the local runs used a container-local copy of the working tree, synchronized before each run, the bind mount from the Windows disk costing about five times the time, CI the final proof. The READY gates of TASK-012 and TASK-013 were re-checked at this DONE, and TASK-011's own at its start, TASK-010's DONE record having left it TODO. Each non-applicable item above carries its reason; TASK_PLAN, this file and CURRENT_HANDOFF updated for DONE

**SAFE NEXT TASK:**

- TASK-012 — when its other dependencies are DONE and its READY gate holds

**DO NOT DO:**

- add to V1 — a capability, page, route, field, integration, document type, report or paid service — or drop, defer or narrow a V1_REQUIRED requirement; a Task that would need either raises an Owner question and waits ([DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-20)
- access the VPS, production, its data or a credential, or put a secret, real data, a host detail or production configuration in the repository ([INFRASTRUCTURE](../09-operations/INFRASTRUCTURE.md) INF-01, INF-05; SECURITY SX-01, SX-04, SX-06; [PRODUCTION_DATA_SAFETY](../00-governance/PRODUCTION_DATA_SAFETY.md))
- add AI, model or tool attribution to a commit — the Owner's configured identity only ([AGENTS.md](../../AGENTS.md#git-commit-attribution); DIR-022)
- start before the Owner's execution authorization at OG-4, or mark DONE without the required tests passing and the completion evidence ([TASK_PLAN](TASK_PLAN.md#2-rules-of-the-plan); [DEFINITION_OF_DONE](../08-testing/DEFINITION_OF_DONE.md#2-the-checklist))
- weaken, skip or delete a valid test to obtain a green run, or adopt a paid tier without the Owner's COST_POLICY decision (DOD-05; [COST_POLICY](../00-governance/COST_POLICY.md))
- infer a capability from the interface or hide a denial behind a disabled control
- add a capability, role or grant kind PERMISSIONS_MATRIX does not define

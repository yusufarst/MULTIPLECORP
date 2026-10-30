# TASK-061 — Activity history and security log

Status: APPROVED | Updated: 2026-10-20 | Owner: Planning

Approval: [APPR-016](../00-governance/DECISION_LOG.md#appr-016--p11-task-plan-tasks-and-feature-coverage-matrix-approved), explicit Owner approval on 2026-10-20 (DIR-085 OF-07) of this document as committed in the first execution session's approval commit; the approved file hash and the verified conditions are recorded there. Approval changes lifecycle only, not implementation authorization.

Written by WF-PC5-W's writer of bundle W3 of run PC-5 on 2026-10-19 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99; the Owner approved P11 with the [Task plan](TASK_PLAN.md) at OG-4 (DIR-085 OF-07). **No Task starts before the freeze record, from which the execution authorization is in force (DIR-085 OF-09).** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** DONE

**RISK CLASS:** HIGH

**EXECUTOR:** AGENT

**OBJECTIVE:**

Build the Owner's two log pages: PG-063 *Riwayat Aktivitas*, the workspace-wide search of the business audit — who did what, when, on which record, with which reason and authority class, before and after — across every company and the events without a company; and PG-064 *Log Keamanan*, the security events — sign-ins, failures, throttling, links, step-ups, denials, exports, anomalies — filtered by kind, period and company, the target of *Beranda*'s security notices (ROUTE-063, ROUTE-064; INT-020).

**WHY THIS EXISTS:**

Every committed business action keeps its individual actor, time, reason, before/after and linkage, and security events stay separate from business audit (BR-XC-01–BR-XC-03; EXECUTION_CONTEXT invariant 9; SECURITY LG-01, LG-02); the Owner must be able to search them, and only the Owner — workspace audit search and the security log are Owner-only views (OD-14; PERMISSIONS_MATRIX §4). TASK-010 writes both tables; this Task makes them readable under their projections without exposing a secret, a raw address or another account's private factor data (LG-03; PJ-17; PJ-21).

**DEPENDS ON:**

- TASK-010 — Audit and security logging
- TASK-016 — Application shell, navigation and the company context

**BLOCKS:**

- TASK-062 — Critical journeys A — the golden flow, purchasing, fulfilment, counts, scanning and SIPLAH
- TASK-063 — Critical journeys B — finance, corrections, reports, documents, completion and the checklist
- TASK-064 — Critical journeys C — isolation, sign-in, access, search, approvals, import, language, masters, command identity and status
- TASK-065 — UI/UX consistency gate, the responsive matrix and the anti-slop audit
- TASK-066 — Accessibility — WCAG 2.2 AA across every page
- TASK-067 — Localization completeness and the per-page copy lists
- TASK-068 — Performance targets and query budgets in CI
- TASK-069 — Security test matrix, route integrity and zero dead interactions

**PARALLEL SAFE:** NO — it reads the audit and security tables every command writes and registers their list indexes' query paths (IX-13)

**IN SCOPE:**

- PG-063 with NAV-063 and its view ROUTE-063 (`OPS ShowActivityHistory`; `audit.view`, OWNER_ONLY; SCR-59): the audit events newest first as activity rows (PT-18) — the time recorded and, when it differs, the business date; the acting account, or "Sistem", or the operator's recorded identity for the console commands; the company or *Tanpa perusahaan*; the action in one plain sentence; the record with its link; the reason; the authority class —, an event's before and after and its metadata on demand; filters by period, company, actor and record, each on its registered index (IX-13; DATABASE §20) and a further filter only as PF-42 admits it, one bounded branch per company and one for the events without a company, "Muat Lagi" and no total (IA §8.6; PF-13; PF-41; PF-42)
- PG-064 with NAV-064 and its view ROUTE-064 (`OPS ShowSecurityLog`; `security_log.view`, OWNER_ONLY; SCR-60): the security events newest first — kind, time, the account concerned and the acting account when different, the reason code, the route and target, the hashed identifier and address, the user-agent summary and the whitelisted details —, the filter by kind with period and company, "Muat Lagi" and no total (LG-02; IA §8.6)
- INT-020: *Beranda*'s security notice opening its record on PG-064, or on PG-060 for an account's notice, the notice derived at display time; PG-064's "Kembali" → the origin when it was opened from a notice (NAV-064; DP-11)
- both pages `[desk]` and dense, readable on the phone; a print view as DESIGN_SYSTEM §17 states it (DP-19)

**OUT OF SCOPE:**

- writing the audit and security events, their redaction and retention (TASK-010); each record's own *Riwayat* timeline on its page (the record's Task; PJ-11); the account's own events on PG-065 (TASK-023)
- the Owner review queue PG-008 and the Owner's notices on *Beranda* (TASK-058); LG-07's detection signals and their delivery (TASK-075, TASK-080)
- any export of the audit or security events — no route or report contracts one (ROUTE_CONTRACTS ROUTE-283)

**REFERENCES:**

- [BUSINESS_RULES §1](../02-domain/BUSINESS_RULES.md#audit-convention) BR-XC-01–BR-XC-03; [EXECUTION_CONTEXT](EXECUTION_CONTEXT.md#project-invariants) invariant 9
- [SECURITY §6](../05-security/SECURITY.md#6-audit-and-security-logging) LG-01–LG-06; [§4](../05-security/SECURITY.md#4-web-and-input-security) WS-03, WS-07
- [DATABASE §4.13](../04-architecture/DATABASE/s04-13-ops.md#413-ops--operations) `audit_events`, `security_events`; [§23](../04-architecture/DATABASE/s20-24-index-storage-migration.md#23-audit-persistence)
- [PERMISSIONS_MATRIX §4](../05-security/PERMISSIONS_MATRIX.md#4-capability-catalogue) `audit.view`, `security_log.view`; [§7.2](../05-security/PERMISSIONS_MATRIX.md#72-table-classification) rows 118, 119; [§7.3](../05-security/PERMISSIONS_MATRIX.md#73-special-projections) PJ-11, PJ-17, PJ-21; [§7.4](../05-security/PERMISSIONS_MATRIX.md#74-read-classes) RD-10; [§9](../05-security/PERMISSIONS_MATRIX.md#9-owner-only-denials) OD-14; [§11](../05-security/PERMISSIONS_MATRIX.md#11-data-path-coverage-matrix) DP-11, DP-12, DP-19
- [PERFORMANCE §4](../06-api-performance/PERFORMANCE.md#4-pagination) PF-13–PF-17, PF-41; [§6](../06-api-performance/PERFORMANCE.md#6-query-budgets) QB-12; [§11](../06-api-performance/PERFORMANCE.md#11-growth-thresholds) GT-01; [§12](../06-api-performance/PERFORMANCE.md#12-index-register-and-verification-procedure) IX-13, IX-14, IX-19
- [ROUTE_CONTRACTS §2](../03-workflows/ROUTE_CONTRACTS.md#2-page-views--route-001route-065) ROUTE-063, ROUTE-064; [INTERACTION_CONTRACTS §2](../03-workflows/INTERACTION_CONTRACTS.md#2-shared-interactions--int-001int-020) INT-020
- [NAVIGATION_CONTRACTS §2.6](../07-ux-design/NAVIGATION_CONTRACTS.md#26-reports-master-data-settings-and-owner-areas) NAV-063, NAV-064
- [INFORMATION_ARCHITECTURE §8.6](../07-ux-design/INFORMATION_ARCHITECTURE.md#86-reports-master-data-settings-and-owner-areas) PG-063, PG-064; [§8.7](../07-ux-design/INFORMATION_ARCHITECTURE.md#87-the-approved-screens-scr-01scr-60-and-their-pages) SCR-59, SCR-60; [§9](../07-ux-design/INFORMATION_ARCHITECTURE.md#9-dashboards-and-queues) the Owner's security notices; [§11](../07-ux-design/INFORMATION_ARCHITECTURE.md#11-page-addressing)
- [DESIGN_SYSTEM §9.4](../07-ux-design/DESIGN_SYSTEM.md#94-actions-overlays-and-feedback) PT-18, PT-19; [§9.2](../07-ux-design/DESIGN_SYSTEM.md#92-lists-tables-and-filters) PT-05–PT-08; [§17](../07-ux-design/DESIGN_SYSTEM.md#17-print-views)
- [SECURITY_TEST_MATRIX §3](../08-testing/SECURITY_TEST_MATRIX.md#3-the-matrix) SM-01, SM-04, SM-05; [PERFORMANCE_TARGETS §2](../08-testing/PERFORMANCE_TARGETS.md#2-the-query-budgets) QB-12
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (10): REF-049; ROUTE-063–ROUTE-064; INT-020; PG-063–PG-064; SCR-59–SCR-60; NAV-063–NAV-064

**EXPECTED MODULES / FILE AREAS:**

- the OPS module's query classes `ShowActivityHistory` and `ShowSecurityLog` with their keyset branches (RC-02; ARCHITECTURE §3, §14); `resources/js/Pages/OPS` for PG-063 and PG-064; `tests/Feature/OPS`, the query-count tests of TL-15

**BUSINESS RULES:**

- BR-XC-01–BR-XC-03: every committed business action records its individual actor, time, action, entity, before/after, reason and linkage; multiple users of one role stay separate identities; business audit is append-only, holds no secret, and technical logs are separate — this Task only reads and shows them
- BR-DT-05: audit order is `recorded_at`; a backdated event shows its business date beside its recorded time (PT-18)

**ARCHITECTURE RULES:**

- read-only query classes; no application role updates or deletes an audit or security row (DATABASE §23, §28; C-53); wide columns — before, after, metadata — selected only by the view that shows them, never `SELECT *` (PF-06)
- lists keyed per company on (`company_id`, `occurred_at`, `id`), the events without a company as one more branch, the security events on their own time keys, each verified by PERFORMANCE §12's procedure (PF-13; IX-13; GT-01)

**SECURITY / PERMISSION REQUIREMENTS:**

- ROUTE-063 `audit.view` and ROUTE-064 `security_log.view`, both OWNER_ONLY: an Admin's request answers PG-005 with MSG-09, identical to a nonexistent page, and no entry exists for an Admin (OD-14; AZ-11; NV-08)
- nothing shown that LG-03 keeps out of every record — no password, hash, token, session identifier, TOTP secret or code, recovery code, OAuth artefact, cookie or file content; a login identifier and an address only as their keyed hashes (LG-03; D-SEC-13); a provider subject never shown (PJ-17)
- WS-07's shared props unchanged; prefetch off on these pages (PF-35); the filters whitelisted address keys holding no name, amount or term (WS-03; IA §11)

**COMPANY SCOPE AND FIELD PROJECTION:**

- the Owner's scope is every company (CS-01): PG-063 reads one branch per company and one for the events without a company, labelled *Tanpa perusahaan* (PF-13; PF-41)
- projection rows 118 and 119: `audit_events` workspace search OWN (`audit.view`), its keys of before, after and metadata each projected like its source column (PJ-11) — for the Owner every key a user may see —; `security_events` OWN (`security_log.view`) (PJ-21; RD-10); `command_log`, session payloads, tokens and raw addresses projected to no one (PJ-21)

**CONCURRENCY / IDEMPOTENCY:**

- N/A — both pages are read-only views outside SF-CMD with no `command_id`; a page read takes no row lock and never waits on one (PF-12)

**AUDIT:**

- N/A — viewing the logs commits nothing and writes no business audit event; a denied request is recorded as TASK-010 and TASK-011 write denials (SECURITY LG-02)

**DATABASE IMPACT ASSESSMENT:**

- Schema change: NONE — uses the tables of TASK-005–TASK-009; a missing column is an executor discovery (AGENT_OPERATING_MODEL)
- Migration required: NO
- Existing data transformation: NONE
- Direct production DB access: FORBIDDEN
- Data-loss risk: NONE KNOWN

**MODULARITY / EXTENSIBILITY:**

- Module boundary affected: OPS, tier 0, its own audit and security tables read through its query classes (ARCHITECTURE §3)
- Stable core kept provider-neutral: YES
- External/vendor-specific code isolated: YES — framework facilities or a small interface (ARCHITECTURE §2)
- Replaceable adapter/contract required: NO
- Provider lock-in risk: NONE beyond the Owner's baseline stack
- Future extension impact: LOW
- Speculative abstraction avoided: YES — no log-analytics engine or external log service (COST_POLICY; ARCHITECTURE §17)

**AUTH IMPACT:**

- Authentication change: NONE
- Authentication implementation: LARAVEL_NATIVE — decided once ([EXECUTION_CONTEXT](EXECUTION_CONTEXT.md)), reused, never redesigned
- Auth profile: N/A — this Task does not touch authentication; it shows the security events authentication writes
- Google sign-in: N/A — not touched
- Google registration behavior: N/A — not touched
- Google credentials owner action required: NO
- Authorization change: NONE — `audit.view` and `security_log.view` exist in the catalogue (TASK-011)
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

- Context7: REQUIRED — PostgreSQL row-value keyset predicates and JSONB selection; the framework's query builder for per-company branches merged in one statement
- Graphify: N/A — two read-only pages over one module's tables; the impact is read from the diff and ARCHITECTURE §3–§4
- Browser E2E: REQUIRED — INT-020's path from *Beranda* to PG-064, the filters and "Muat Lagi" on both pages
- Other: the scale fixtures FX-06 at 1x, 10x and 100x — 300,000 to 30 million audit rows (AS-04; AS-05; TASK-003)
- Missing safe tool behavior: AUTO-INSTALL project-locally inside this Task (AICWDF §6.3) and record it in TOOLCHAIN; a paid tier or a privileged installation STOPS for the Owner (COST_POLICY)

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: N/A — the change touches OPS's read paths only; a targeted search of the audit and security writers suffices
- Pre-change questions: which index serves each offered filter; which event kinds and fields LG-02 records
- Expected affected areas: OPS's two query classes and pages; no write path

**CONTEXT7:**

- Topics to verify: keyset pagination with row-value comparison and its index use; selecting wide JSONB columns only on demand (PF-06)
- Version-sensitive assumptions: none from memory

**UI / UX REQUIREMENTS:**

- Follow global design rules: YES
- Indonesian default: YES
- English support: YES — the interface; the values an event records stay as recorded (LOCALIZATION §3.4)
- Plain-language Indonesian: YES — each action and event kind as one plain sentence or label, with the glossary's terms
- Language switch impact: NONE — the filters are address keys and stay
- Mobile-first: YES — `[desk]`, dense, readable on the phone (NAV-063; NAV-064)
- Responsive: YES
- Adaptive: N/A — one desktop layout, readable on the phone
- Visual breathing room: REQUIRED — the dense mode where OC-09 names these screens (TEST_STRATEGY §9)
- Comfortable density: REQUIRED
- shadcn/ui reuse: the data table, dense rows, filters and list continuation (PT-05–PT-08), the activity timeline's row form (PT-18), the loading, empty and error states (PT-19), the print view (DESIGN_SYSTEM §17)
- Design reference: PG-063 and PG-064 as INFORMATION_ARCHITECTURE §8.6 states them; the Golden Frontend Reference for presentation only (DIR-061 SD-02)
- Primary action: N/A — read-only pages without a command; an event's record link is its only action
- Secondary actions: the filters, "Muat Lagi", an event's before and after on demand, the print view
- Destructive actions: none — no control edits, hides or deletes an event (C-53)
- Redundancy constraints: one filter bar per page (PT-07)
- Loading state: the list's spinner after 300 ms; "Muat Lagi" busy (PT-19)
- Empty state: no event in the period — one sentence with the filters kept (PT-19)
- Error state: NV-04; a cursor that fails its check answered with MSG-26 and the offer to reload the list (IP-18)

**ROUTE IMPACT:**

- ROUTE-063 (PG-063's view), ROUTE-064 (PG-064's view); no command route

**INTERACTION CONTRACTS:**

- INT-020; INT-007 and INT-008 for the filters and "Muat Lagi" (TASK-015, TASK-016)

**NAVIGATION CONTRACT:**

- Parent: PG-063 — *Menu lengkap* › *Aktivitas & Keamanan*; PG-064 — *Aktivitas & Keamanan* › *Log Keamanan*, or PG-006 (NAV-063; NAV-064)
- Entry: *Aktivitas & Keamanan* › *Riwayat Aktivitas*; *Aktivitas & Keamanan* › *Log Keamanan*; *Beranda*'s security notice
- Back action: PG-064's "Kembali" → the origin when opened from a notice; PG-063 none, a primary page of its area
- Cancel: N/A — nothing is entered to discard
- Success destination: N/A — no command; an event opens its record
- Error behavior: NV-04; a record out of reach → PG-005 (IP-21)
- State preservation: period, company and filters in the address (NV-05)
- Mobile behavior: `[desk]`, dense, readable on the phone

**ACCEPTANCE CRITERIA:**

1. Only the Owner reaches PG-063 and PG-064; an Admin's request for either address answers exactly like a nonexistent page, and no entry exists for an Admin (OD-14; AZ-11).
2. PG-063 lists audit events of every company and those without a company, newest first, each with its actor or "Sistem" or the operator's recorded identity, its time and business date, its action, record, reason and authority class, and its before and after on demand (BR-XC-01; LG-01; PT-18).
3. PG-064 lists the security events by kind, period and company with the fields LG-02 records, the identifier and the address only as their keyed hashes (LG-02; LG-03).
4. No page, prop, deferred part or print view of either page carries a password, hash, token, session identifier, TOTP secret, recovery code, OAuth artefact or raw address (LG-03; PJ-17; PJ-21).
5. A security notice on *Beranda* opens its record on PG-064 or PG-060, and PG-064's "Kembali" returns to *Beranda* (INT-020; NAV-064).
6. Both lists continue by keyset with "Muat Lagi", without totals or page numbers, a tampered cursor answered with MSG-26; their query count stays within QB-12 and independent of the rows shown, and at 100x their plans stay sub-linear (PF-13–PF-15; QB-12; PFM-02; PFM-03).
7. No control on either page edits, hides or deletes an event (C-53; BR-XC-03).

**TEST REQUIREMENTS:**

- Lint: TL-01
- Typecheck: TL-02
- Unit: the event sentence per action and kind; the keyset cursor's checks (TL-03)
- Feature: ROUTE-063 and ROUTE-064 with each filter (TL-04)
- Integration: the per-company branches with the no-company branch merged in one statement (TL-05)
- Authorization: OWNER_ONLY `audit.view` and `security_log.view`; the redaction of every forbidden field (TL-06)
- Route integrity: ROUTE-063 and ROUTE-064 audited (TL-07)
- Interaction: INT-020 with its *Test* — the notice derived at display time (TL-08)
- Browser E2E: INT-020's path, both pages' filters and continuation (TL-09)
- Localization/language switch: both languages on PG-063 and PG-064 (TL-10)
- Visual breathing room/density: TEST_STRATEGY §9 — the dense mode
- Responsive: TL-11
- Accessibility: TL-12 — table headers, the on-demand before and after, keyboard reach
- Production build: TL-13
- Security: SM-04 for OD-14; SM-01 for a forged event identifier and filter; SM-05 — the authority class of every listed event read as recorded (TL-14)
- Performance: QB-12 through PFM-01 and PFM-02; PFM-03's plans at 1x, 10x and 100x, GT-01's BRIN threshold watched (TL-15)
- Migration: N/A — no migration or import code in this Task
- Other: N/A — no further obligation; LG-06's retention is TASK-010's

**CRITERIA DISPOSITIONS:**

- Criterion 1: `tests/Authorization/LogPagesAccessTest.php` — an Admin's request for `/aktivitas/riwayat` and `/aktivitas/log-keamanan` answering 404 with the same page as an unknown address, the Owner's 200; `tests/Frontend/shell.test.ts` — *Aktivitas & Keamanan* absent without `audit.view` or `security_log.view`, opening *Riwayat Aktivitas* with `audit.view` and *Log Keamanan* with `security_log.view` alone
- Criterion 2: `tests/Feature/OPS/ActivityHistoryTest.php` — two companies' events and one without a company newest first, each with its actor, "Sistem" or the operator's recorded identity, its time and its business date when it differs, its action, record with its link, reason and authority class; the period, company, *Tanpa perusahaan*, actor and record filters; an event's before, after and metadata as a partial reload of `detail`
- Criterion 3: `tests/Feature/OPS/SecurityLogTest.php` — the events newest first with LG-02's fields, the account and the acting account when different, the identifier and the address as the first characters of their keyed hashes; the kind, period and company filters
- Criterion 4: `tests/Authorization/LogPagesAccessTest.php` — a failed sign-in's login identifier and address and a Google sign-in's provider subject absent from PG-064's page, an audit metadata token absent from PG-063's, no wide column in the list; `tests/Feature/OPS/ActivityHistoryTest.php` — a `session_id` key dropped from an event's shown metadata
- Criterion 5: `tests/Interaction/LogsTest.php` — a notice's event opened above PG-064's list with `from_notice` for "Kembali", an account's notice on PG-060, a target that no longer resolves answered 404 (MSG-09); `tests/Browser/E2E/logs.spec.ts` — the notice's address opening its event with "Kembali" (*Beranda*'s notice itself is TASK-058's)
- Criterion 6: `tests/Feature/OPS/ActivityHistoryTest.php` and `SecurityLogTest.php` — "Muat Lagi" continuing by the signed cursor without a total, a tampered cursor answered with MSG-26 (`cursor_failed`); `tests/Integration/Audit/ActivityBranchesTest.php` — one statement for the three companies' branches and the branch without a company, ties ordered by id; `tests/Performance/LogQueriesTest.php` — the same number of statements for three events and more than a page, filtered or not, within QB-12; `tests/Performance/IndexRegisterTest.php` — the branch without a company, the security log and its kind filter on their indexes (the scale procedure at 1x, 10x and 100x is TASK-068's, PFM-03)
- Criterion 7: `tests/Interaction/LogsTest.php` — no write method on either address (405); neither page offers a control that edits, hides or deletes an event

**TEST CHECKLIST:**

- [x] Lint: Pint and ESLint PASS in CI (TL-01)
- [x] Typecheck: Larastan/PHPStan at level `max` and TypeScript PASS in CI (TL-02)
- [x] Relevant automated tests: Unit — every recorded audit action's sentence and every security event kind's label in both languages, the cursor's checks (TL-03); Feature — ROUTE-063 and ROUTE-064 with each filter (TL-04); Integration — the merged branches (TL-05); PASS in CI and locally
- [x] Authorization: OWNER_ONLY `audit.view` and `security_log.view`, the redaction of every forbidden field (TL-06)
- [x] Route integrity: ROUTE-063 and ROUTE-064 in the route audit and the boot test's route list (TL-07)
- [x] Interaction behavior: INT-020 and the read-only routes (TL-08)
- [x] Browser E2E if required: both pages' filters and continuation, an event's change, the notice's event with "Kembali" (TL-09)
- [x] Responsive/adaptive if required: both pages at 320, 390, 768 and 1280 px (TL-11)
- [x] Visual breathing room/density if UI: the shared dense data list, filters and dialog (TEST_STRATEGY §9)
- [x] Production build: TL-13 PASS in CI
- [x] Security checks if required: SM-04 for OD-14; SM-01 for a forged event key, cursor and filter; SM-05 — each event's authority class as recorded (TL-14)
- [x] Database safety review: one expand-only migration of two indexes on `security_events`; read-only query classes, no application role updates or deletes a log row (C-53); local and CI databases only
- [x] Final diff review: each rule against the contract and its sources; ER-06's independent review and Codex's points dispositioned

**COMPLETION EVIDENCE:**

- Test outputs (TEST_STRATEGY §16), tested commit `56cdda8`: fresh CI on GitHub's `ubuntu-24.04` runner, runs 40995932604 (push) and 40995937496 (pull request #44), the three jobs PASS in each — "Verify", "Browser" and "Performance and migration"; the TASK-061 PHP tests, PHPStan, Pint, ESLint and TypeScript also PASS on the workstation's stack
- Build result: `TL-13 Frontend build: PASS — Evidence: 56cdda8, CI ubuntu-24.04 run 40995932604`
- Route evidence: ROUTE-063 and ROUTE-064 registered with their OWNER_ONLY capabilities and in `tests/Feature/BootTest.php`'s list; the route audit PASS in CI run 40995932604
- Interaction evidence: `tests/Interaction/LogsTest.php` — INT-020 PASS in CI run 40995932604
- Browser evidence: `E2E: PASS — Evidence: 56cdda8, CI ubuntu-24.04 run 40995932604` — `tests/Browser/E2E/logs.spec.ts` and the localization, responsive and accessibility specs of both pages in Chromium and WebKit
- Localization evidence: `tests/Browser/Localization/logs.spec.ts` — both pages in Indonesian and English; the new keys in the registry as TASK-061's, an event's recorded values shown as recorded
- Performance evidence: `tests/Performance/LogQueriesTest.php` and `IndexRegisterTest.php` (TL-15) — a page's statements independent of the rows, within QB-12, every query path on a registered index
- Cost evidence/exception if applicable: ADDITIONAL RECURRING COST CHANGE: NONE
- Files changed: `app/Modules/OPS/` — `Queries/ActivityHistory`, `SecurityLog`, `LogCursor`, `Http/Controllers/LogsController` —; `routes/web.php` and `routes/fixtures.php`; `database/migrations/2026_10_30_000146_ops_add_security_event_time_indexes.php`; `resources/js/Pages/OPS/ActivityHistory.tsx` and `SecurityLog.tsx`, `lib/navigation.ts`, `lib/i18n.ts`'s `has`; the catalogues and their registry; GAP_REGISTER's GAP-070; the tests and the browser fixture
- Commit SHA: `8ff4a98`, `5033091`, `6cdd9d8`, `2241159`, `56cdda8`, the Owner's identity, no signature and no AI attribution; this record's commit follows them on `task/TASK-061-activity-logs`
- Notes: criteria as CRITERIA DISPOSITIONS states. ER-06's independent read-only review of the branch before the pull request found no HIGH point; its MEDIUM and LOW points were fixed and tested on the branch: a console event's operator shown as the recorded object — now its stated name with the host account, as `OperatorIdentity::describe()` writes it, tested with the real shape; an actor's or record's timeline narrowed by company read without PF-42's bound — one read now takes at most 1,000 rows and returns short with "more" from the last row read, tested; the index migration in a Task contracted without a schema change — recorded as GAP-070 for Planning's register row; an event's detail reload recomputing the whole list — the list's props are now read only when asked for; a redaction stricter than the application's — `Redaction::isSensitiveKey` now decides, a token key dropped and an ordinary key kept, tested through the detail's partial reload; "Muat Lagi" left busy or appending after a cancelled or failed reload, and the change dialog's stale "cannot be shown" — both reset; the security details' flags shown in words as on PG-063; and two weak browser checks made exact. Kept as a reading: a filter change on PG-064 leaves the notice's event and its "Kembali", the Owner then browsing the log. Codex's review of `2241159` (COMPLETED) found one P2 point, fixed in `56cdda8` and its thread answered: a bounded read of a timeline narrowed by company that found no matching row yet showed PG-063's empty state and hid "Muat Lagi" — the empty state now shows only when nothing is left to read, the short-page notice and "Muat Lagi" kept otherwise, the server side of the case tested. Executor readings within the approved text: the query classes are `ActivityHistory` and `SecurityLog` (`OPS ShowActivityHistory`, `OPS ShowSecurityLog`) beside TASK-010's read models; the filters are `dari`, `sampai`, `perusahaan` (a code or `tanpa`), `pelaku`, `catatan` (a record's type and public identifier) and `jenis`, the cursor and an event's key one signed position (`occurred_at`, `id`); an actor reads its own index and a record its timeline's, each with the companies as a predicate; PG-064's company filter reads the two kinds that carry a company, on IX-14's index; an event's before, after and metadata are a partial reload, their nested values shown as JSON and any key LG-03 names dropped should one ever be recorded; the security log shows the first twelve characters of each keyed hash; a notice's event is `peristiwa` with `asal=beranda`, "Kembali" returning to the page shown before; the print view is the browser's print of the list. Executor discovery: `security_events` had no time key for an unfiltered list — only IX-14's and IX-19's partial indexes —, so one expand-only migration adds `(occurred_at, id)` and `(event_kind, occurred_at, id)` (a Level 1 safeguard of the contract's own PF-13 and criterion 6, registered in the index test and recorded as GAP-070 for Planning's register row); an actor's or a record's timeline narrowed by company reads at most 1,000 rows at a time, its page returning short with "more" (PF-42)

**SAFE NEXT TASK:**

- TASK-062 — when its other dependencies are DONE and its READY gate holds

**DO NOT DO:**

- add to V1 — a capability, page, route, field, integration, document type, report or paid service — or drop, defer or narrow a V1_REQUIRED requirement; a Task that would need either raises an Owner question and waits ([DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-20)
- access the VPS, production, its data or a credential, or put a secret, real data, a host detail or production configuration in the repository ([INFRASTRUCTURE](../09-operations/INFRASTRUCTURE.md) INF-01, INF-05; SECURITY SX-01, SX-04, SX-06; [PRODUCTION_DATA_SAFETY](../00-governance/PRODUCTION_DATA_SAFETY.md))
- add AI, model or tool attribution to a commit — the Owner's configured identity only ([AGENTS.md](../../AGENTS.md#git-commit-attribution); DIR-022)
- start before the Owner's execution authorization at OG-4, or mark DONE without the required tests passing and the completion evidence ([TASK_PLAN](TASK_PLAN.md#2-rules-of-the-plan); [DEFINITION_OF_DONE](../08-testing/DEFINITION_OF_DONE.md#2-the-checklist))
- weaken, skip or delete a valid test to obtain a green run, or adopt a paid tier without the Owner's COST_POLICY decision (DOD-05; [COST_POLICY](../00-governance/COST_POLICY.md))
- show a secret, a token, a raw address or a provider subject, or select the wide columns in a list query (LG-03; PF-06)
- offer any control that edits, hides, deletes or marks an event, or open either page to an Admin (C-53; OD-14)

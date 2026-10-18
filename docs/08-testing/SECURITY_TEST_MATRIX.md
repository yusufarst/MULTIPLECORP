# Security test matrix

Status: REVIEW — Draft of P8, not normative before its approval | Updated: 2026-10-19 | Owner: Planning

**Draft of P8, not normative before its approval.** Run PC-4 drafted this document on 2026-10-19 under [DIR-082](../00-governance/DECISION_LOG.md#dir-081-dir-082-obs-032-and-tech-039--og-2-decisions-appr-011-run-pc-4-p8-and-p10-drafted-and-p9-completed) SP-85, P8 being authorized by the Owner's [DIR-081](../00-governance/DECISION_LOG.md#dir-081-dir-082-obs-032-and-tech-039--og-2-decisions-appr-011-run-pc-4-p8-and-p10-drafted-and-p9-completed) OB-09; the Owner approves P8, or not, by a separate decision at OG-3. It specifies tests; it is not one. No test, test file or fixture exists, nothing here has run and no result is claimed.

**Sources.** [SECURITY](../05-security/SECURITY.md) §10's H8-01–H8-22 and the P8 proof of each threat TM-01–TM-73 ([§8](../05-security/SECURITY.md#8-threat-model)), with the controls they test; [PERMISSIONS_MATRIX](../05-security/PERMISSIONS_MATRIX.md)'s W4-01–W4-27, OD-01–OD-16 and data paths DP-01–DP-20 (§§5, 9, 11 and 13); [ACCEPTANCE_CRITERIA](../01-product/ACCEPTANCE_CRITERIA.md) AC-13; [CONCURRENCY_IDEMPOTENCY](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s19-20-handoff-traceability.md) HO-15, HO-17 and HO-41 with the scenarios M-13, M-20–M-25 and M-39–M-46; [DATABASE §18](../04-architecture/DATABASE/s18-19-constraints-integrity.md#18-constraint-catalogue) C-62–C-65; [ADMIN_FLOW](../07-ux-design/ADMIN_FLOW/s16-17-handoff-traceability.md) UXH-02–UXH-04; and the gaps whose resolution names P8's security evidence — GAP-006, GAP-032–GAP-034, GAP-038–GAP-041, GAP-043–GAP-050 and GAP-061 ([GAP_REGISTER](../00-governance/GAP_REGISTER.md)).

Authority: P8 — the security and denial test cases with their fixtures, layers and evidence. It adds no control, capability or rule and changes no expected result: each case expects what the approved control states. The strategy around it is [TEST_STRATEGY](TEST_STRATEGY.md), whose layers TL-01–TL-16 this document cites and whose [§19](TEST_STRATEGY.md#19-coverage) maps every requirement P8 receives to the sections of this document (STM) and of the other P8 documents.

**Public repository.** Every fixture is synthetic — the fictional group ARJ, BTN and CKP of [INFRASTRUCTURE §3](../09-operations/INFRASTRUCTURE.md#3-environments) —; no real account, credential, address, token or key appears in a test, a fixture or its evidence, and no security test runs against production or the VPS.

## 1. How a case is written and judged

- **One element, one case.** Every behaviour an H8 row names — each element of its list — is its own test case with its own assertion; the row passes only when every element passes. A case that cannot run is BLOCKED, never N/A ([DEFINITION_OF_DONE](DEFINITION_OF_DONE.md) DOD-03).
- **The expected result is the control's.** A denial is AZ-11's: outside the actor's scope a record is not found and indistinguishable from one that does not exist — the same status, wording, layout and timing class (UXH-03) —; inside the scope without the capability it is forbidden. A refused command writes only the refusal events SECURITY §6 names; the case asserts the answer **and** the absence of every other effect — no business row, file, job, cache entry or audit row beyond the refusal's.
- **On the server, always.** Each denial is asserted at the server — the route, the command, the query class and the file controller (AZ-01) —, never only through a control the page hides. An end-to-end case adds the page's presentation and never replaces the server assertion.
- **A positive twin for every denial.** The same request with the scope or capability granted must succeed, so that a suite which refuses everything cannot pass (PERMISSIONS_MATRIX §13).
- **Real PostgreSQL and real sessions.** Authorization, scope and race cases run against PostgreSQL with the project's database roles, never an in-memory substitute ([ARCHITECTURE §18](../04-architecture/ARCHITECTURE.md#18-testing-seams-for-p8)); a concurrent case opens parallel sessions with a barrier, as its M row of CONCURRENCY_IDEMPOTENCY §18 states.
- **Reconciled afterwards.** After every case that writes, `ReconcileGuards` reports no difference (HO-12); after every authorization suite the OD-16 reconciliation of SM-05 returns no row.
- **Evidence.** Each run records the commit, the environment, the command, the suite and every case's result in the form of [TEST_STRATEGY §16](TEST_STRATEGY.md#16-evidence); "should be denied" is never evidence (AICWDF §26).

## 2. Fixtures

Created by the suite in each run, never copied from production ([PRODUCTION_DATA_SAFETY](../00-governance/PRODUCTION_DATA_SAFETY.md)), and part of the synthetic data set FX-02 of [TEST_STRATEGY §4](TEST_STRATEGY.md#4-environments-and-data):

- **Companies** ARJ (A), BTN (B) and CKP (C), each with masters, projects, purchases, lots, invoices, payments, evidence of every family, issued documents with their renditions, exports and an opening-import batch; a shared stock pool holding lots of A and of B in one scope, so that pooled-view, allocation and unattributed-loss cases have a victim company.
- **Accounts** — the holders and the attackers of SECURITY §8: two Owners, so that RG-09's last-Owner rule and M-22 can run; an Admin with Company A only and ADM capabilities including `stock.view` — AC-13's "Company-A-only Admin operating against the shared pool"; an Admin with A and B; an Admin with A holding ADM+ capabilities, high-risk and TOTP-enrolled; an Admin with A without `stock.view`, for whom the pooled view is denied (AC-13); Admins of Company A each lacking one family view capability — `finance.view`, `cost.view`, `profit.view`, `evidence.view`, `projects.view` — for SM-25; a new Admin with no grant (RG-04); a deactivated account with an open session and a queued export; an account whose grant is revoked while its page is open; a high-risk account without TOTP in its enrolment-only session (AU-18); an account with a linked Google identity and TOTP, and an unlinked Google identity; the operator, who has no user row (AU-14; RG-11).
- **Secrets** — keys, TOTP secrets, tokens and passwords — are generated per run and never committed; the secret-like scan of SM-15 also runs over the fixtures.

## 3. The matrix

SM-01–SM-22 answer H8-01–H8-22 one to one; SM-23–SM-25 carry the security tests that other approved documents hand to P8. The *Cases* column gives what P8 adds to the obligation's own elements — the H8 text stays the list of what must be proved. Layers are TEST_STRATEGY's TL rows.

| SM | Obligation | Cases | Layers | Also proves |
| --- | --- | --- | --- | --- |
| SM-01 | H8-01 — a Company-A-only Admin against Company B on every data path and record route | For each data path of §4 and each record route of [ROUTE_CONTRACTS](../03-workflows/ROUTE_CONTRACTS.md): a forged or foreign `public_id`; a foreign reference in a filter, payload, include, sort key or cursor; nested resources; masters reached through a shared master; search and exact lookup by SKU, barcode, serial and number; export; report filter; dashboard signal; download; print view; cached aggregate. The positive twin is the same request by the Admin with A and B | TL-06, TL-14; TL-09 for the page | AC-13; OWN-02; REF-056; GAP-003's and GAP-006's denial tests; EN-06 and D-SEC-07 — with no row-level security these cases are the isolation proof |
| SM-02 | H8-02 — the route audit, with OB-03's declarations | A test enumerates the registered routes and fails when: an authenticated route declares neither a capability nor one of EN-03's three declared requirements — "every active account (S0)", "SELF" or "the enrolment-only session" (`DIR-081` OB-03) —; a route declaring S0 answers the new Admin of RG-04 with any part above S0; a SELF route reaches a row other than the account's own; an enrolment-only route answers a full session or reaches anything else; an unauthenticated route outside EN-03's list exists or reaches a command; a Fortify two-factor route is registered without a project route replacing it; any prohibition of AU-16 that H8-02 lists is broken; or a registered route has no contract in ROUTE_CONTRACTS, or a contracted route is not registered | TL-07, TL-06 | GAP-061; RB-02; RC-03 |
| SM-03 | H8-03 — architecture tests | Static tests over the code: every query over company-owned rows goes through a query class that applies the scope resolver first (AZ-04; EN-04); no role-name check outside the uses AZ-02 lists; no whole-request mass assignment and no model left unguarded; DP-03's mass assignment of company, authority and state fields refused at the request and inside the action | TL-03, TL-06 | EN-04 |
| SM-04 | H8-04 — every W4 row and capability class | §5's 27 rows, each with its positive and denial cases by class; §6's denials OD-01–OD-15; H8-04's own elements — Admin and API Force Complete, a write-off and its supersession, found-stock attribution, requirement relaxation, an unattributed-loss resolution without evidence with OD-10's conditions one by one, a loss, disposal or count loss on another company's lot without a grant on it and a condition change as a pool effect (AZ-08), self-granting and the last Owner (RG-09) —; and RG-07: a capability applies to every company of the account's grants, run on the Admin with A and B for each class | TL-06, TL-04 | AC-13; OWN-03; GAP-005, GAP-022, GAP-026, GAP-030 and GAP-032's grant and denial tests |
| SM-05 | H8-05 — the OD-16 reconciliation | A reconciliation query that returns no row, run after every authorization and concurrency suite: each audit event's recorded authority class equals the highest class its command exercised (AZ-10); no OWNER_ONLY capability in a user grant or in the `role_capabilities` of a role other than OWNER; no OWNER_ONLY event by an account without the OWNER role; the audit events of ST-03 and SQ-23 read as records of a refusal — authority class SYSTEM with a human actor —, not as anomalies (HO-15). A seeded anomaly of each kind is found and logged as AUTHORIZATION_ANOMALY | TL-05 | OD-16; HO-15 |
| SM-06 | H8-06 — authentication | H8-06's elements against SECURITY §2: the hash driver and parameters; the rehash at login (RV-10); the driver-matched dummy hash; enumeration by content, status and timing class across login, reset and the second-factor step; NFC normalization; AU-05's throttling without lockout, its three keys and IPv6 counted per /64; session regeneration; logout invalidation; the 60-minute idle and 12-hour absolute limits, background requests never extending them (RV-06); termination on deactivation and on a password change; no remember-me | TL-04, TL-06, TL-14; TL-09 for the login journeys | AC-13; REF-001; GAP-044 |
| SM-07 | H8-07 — credential links | H8-07's elements, single use under concurrent submission as M-24; the Owner-issued flow end to end; the Owner-recovery procedure end to end on staging, with the operator running the command — never on production | TL-04, TL-05, TL-09 | M-24 |
| SM-08 | H8-08 — forgery, origin and headers | CSRF on every state-changing route, none excluded and no GET changing state; `Sec-Fetch-Site` verification with the token fallback, and the measurement WS-04 waits for — whether every supported browser of [P8Q-02](TEST_STRATEGY.md#p8q-02--the-supported-browsers-and-devices) sends the header —, after which origin-only mode may replace the fallback as a reviewed change; cookie flags; the CSP nonce and directives; frame-ancestors on pages and on both file header profiles; nosniff; Referrer-Policy; the HSTS configuration; `APP_DEBUG=false` in the production build's configuration check | TL-14, TL-09 | WS-04 |
| SM-09 | H8-09 — uploads | H8-09's elements for each purpose and limit of FL-02; the device UAT's confirmation of the formats the users really hold (FL-02; DEP-04; DEP-05; [TEST_STRATEGY §12](TEST_STRATEGY.md#12-devices-and-uat)) | TL-04, TL-14 | FL-02 |
| SM-10 | H8-10 — the renderer and the image decoders | H8-10's elements against the renderer's isolation and the decoder policy: SSRF and file-access attempts from document content, crafted decoder inputs, stored XSS in React pages and in PDFs | TL-05, TL-14 | — |
| SM-11 | H8-11 — exports | Formula neutralization of every exported cell as SECURITY specifies it; an export generated before a revocation and delivered after it — of one filtered company, of a view capability, a role change and a revocation during generation (M-23) | TL-05 | M-23 |
| SM-12 | H8-12 — oracles, cross-family writes and projections | H8-12's elements; GAP-034's record (UXH-04): one EVIDENCE_DUPLICATE_UNDISCLOSED or IMPORT_COLLISION_UNDISCLOSED event per command and matched record — for the evidence match, a rendition-checksum match included, and for the import collision —, visible in the Owner review queue and in no Admin path or account's own events, identical responses with and without a hidden match, and the case where the event cannot be written | TL-05, TL-06 | GAP-034; HO-38; UXH-04 |
| SM-13 | H8-13 — revoked access, caches and history | H8-13's elements with M-13, M-20 and M-21; UXH-02's security part: no form data, identifier or S3 or S4 value in any browser storage, history unreadable after every authentication boundary, and a sign-in by another account at the re-authentication dialog that never uncovers the page or resends its `command_id`; a cache added later gets its key-isolation case before it is used (DP-10; CP-04) | TL-05, TL-09 | M-13, M-20, M-21; UXH-02 |
| SM-14 | H8-14 — rate limits | H8-14's elements with HO-17: each limiter exact under concurrency (H6-07's single atomic increment); each limiter with its store down — the authentication limiters closed, the others open with one aggregated THROTTLED event —; both funnels of the verification cap (M-25); a login racing a password change, a rehash, a deactivation and a reactivation (RV-05; RV-10). The limits' values are measured as [PERFORMANCE_TARGETS](PERFORMANCE_TARGETS.md) PFM-12 states, not assumed | TL-05 | HO-17; M-25 |
| SM-15 | H8-15 — CI gates | The secret-like scan, the dependency audits of PHP and JavaScript packages, lockfile-only installs and SHA-pinned CI actions as required checks that fail the pipeline; each gate's detection proved on a fixture inside the suite — never by committing a secret or a vulnerable dependency to any branch | TL-14 | — |
| SM-16 | H8-16 — the password profile | H8-16's elements; the range service simulated — no test calls the real service —, its timeout, transport error and unsuccessful answer each recording PASSWORD_CHECK_SKIPPED and accepting the password only on the local list and every other rule (DEP-11) | TL-03, TL-04 | AU-03; GAP-038; GAP-046; DEP-11 |
| SM-17 | H8-17 — TOTP and the second-factor limit | H8-17's elements with M-39, M-44 and M-45 as real-session tests; AU-05's second-factor limit as its own text states it; a controlled clock for the period, the window, the 15-minute attempt window and the 24-hour escalation; the login page, the in-place re-login and Google sign-in each reaching the challenge, end to end | TL-04, TL-05, TL-09 | AU-05; AU-16; AU-18; C-62; C-65; GAP-033; GAP-039; GAP-044; GAP-045 |
| SM-18 | H8-18 — recovery codes | H8-18's elements with M-40 | TL-04, TL-05 | AU-19; C-65 |
| SM-19 | H8-19 — reset and recovery paths a–f | H8-19's elements; the operator's Owner-recovery command run on staging only | TL-04, TL-05 | AU-14; AU-20; AU-24; GAP-040; GAP-041 |
| SM-20 | H8-20 — Google | H8-20's elements with M-41–M-43; the provider simulated in local and CI runs — no test calls Google —; the real redirect checked once on staging with the staging OAuth client when P9 has set it up (GAP-042) | TL-04, TL-05, TL-09 | AU-22–AU-24; C-63; C-64; GAP-047 |
| SM-21 | H8-21 — key management and post-incident recovery | H8-21's elements; HO-41's incident command against active sessions and requests, its restart after a stop, a restart with another window start refused; the OWNER-restoration command of RV-18 with its refusals and M-46's cases — twice for one account in one run, again after the restored account changed, two runs with a barrier before LK-02 and a run while the incident command still runs —; the end-of-recovery race GAP-050 names — the end against a role change and against an activation, both orders, with a barrier before LK-02 —, the case expecting the atomic end GAP-050 recommends once RV-18 adopts it under GAP-050 (P11), the gap open until then; synthetic keys only, never a production key | TL-05 | AU-21; AU-25; H9-14's protocol; GAP-048; GAP-050; HO-41; M-46 |
| SM-22 | H8-22 — framework behaviours read in source | The re-verification in the source of the versions chosen, recorded in TOOLCHAIN and the Task (GAP-043), followed by one behaviour test per item, so that a changed version fails a test rather than a reading | TL-04 | GAP-043 |
| SM-23 | UXH-03 — denial and oracle presentation | Not-found and out-of-scope pages identical in wording, layout, status and timing class; an invisible duplicate — a rendition-checksum match included — with no flag, note or reason on the evidence; a hidden batch collision indistinguishable for the actor; out-of-scope DUPLICATE rows of a dry-run report listed only under *Tidak Valid* with MSG-18; no count, badge, empty state or search result revealing an out-of-scope record; the C-10 message limited to the actor's own record | TL-09, TL-06 | UXH-03; UXS-18; UXS-19; UXS-22; UXS-25 |
| SM-24 | GAP-049 — the first-Owner installation event | The installation step writes one audit event of the OWNER assignment with the operator's identity — the host account and the stated name —, creates no user row for the operator and is refused when run again on an installed workspace, as P9 states the rule ([INFRASTRUCTURE §5](../09-operations/INFRASTRUCTURE.md#5-the-deployment-path); AU-25; RG-11) | TL-04 | GAP-049 |
| SM-25 | AC-13 and PERMISSIONS_MATRIX §7 — field projection inside the actor's scope | For an Admin of Company A lacking each family view capability in turn: S3 money, cost and profit absent from every page, prop, deferred group, export, print and document payload of Company A or refused, as the projection states (§7.1; PJ-15; PJ-22); evidence of a family it may not view refused (PJ-08); the Owner-only signals QS-14 and QS-23 and the attribution parts of QS-18 and QS-22 absent from its queue (PJ-13); the raw stamp and signature files never served to it (PJ-19); a partial reload or deferred prop asking for a field above its projection refused (DP-15). Each with its positive twin — the same Admin granted the family capability; for the Owner-only items of PJ-13 and PJ-19 the twin is the Owner | TL-06, TL-04; TL-09 for the page | AC-13; OWN-02 |

## 4. Data paths

Every path of PERMISSIONS_MATRIX §11, its denial obligation and the cases that carry it; SM-01 carries each one for Company B.

| DP | Data path | P8 denial obligation (PERMISSIONS_MATRIX §11) | Cases here |
| --- | --- | --- | --- |
| DP-01 | URL and route identifiers | forged and foreign public_id on every record route (H8-01) | SM-01 |
| DP-02 | Query parameters (filter, sort, include, page size, cursor) | foreign company filter, unknown sort key, oversized page | SM-01 |
| DP-03 | Payload references | foreign reference in each command; mass assignment of company, authority and state fields | SM-01, SM-03 |
| DP-04 | API | route audit: no unauthenticated or token route to a command | SM-01, SM-02 |
| DP-05 | Downloads (evidence, renditions, identity assets, exports, import sources) | foreign file, rendition, export and import source | SM-01, SM-09 |
| DP-06 | Search and lookup (SKU, barcode, serial, project, document, client, supplier) | wrong-company search, serial and number probes | SM-01, SM-14 |
| DP-07 | Exports | export after revoking one filtered company or a view capability; foreign filter; formula payloads | SM-01, SM-11 |
| DP-08 | Reports and dashboards | wrong-company report filter; consolidated view as Admin | SM-01 |
| DP-09 | Queued jobs | queued export of a revoked or deactivated actor | SM-01, SM-13 |
| DP-10 | Caches | cache key isolation test when a cache is added | SM-01, SM-13 |
| DP-11 | In-app alerts and notifications | alert for a revoked company | SM-01, SM-13 |
| DP-12 | Audit and activity timelines | cross-company event keys; out-of-scope actor names | SM-01, SM-12 |
| DP-13 | Evidence duplicate warnings | duplicate oracle: an upload matching evidence of another company or of an invisible family reveals nothing | SM-01, SM-12, SM-23 |
| DP-14 | Revoked access | revoked grant with an open session, a partial reload and a queued export | SM-01, SM-13 |
| DP-15 | Inertia shared, partial, optional and deferred props and validation errors | partial reload asking for an unauthorized prop; deferred prop across companies; error bag leakage | SM-01, SM-13, SM-25 |
| DP-16 | Command outcomes and replay | replay after revocation; replay by another account | SM-01, SM-13 |
| DP-17 | Rendering and issued PDFs | foreign rendition; SSRF payloads | SM-01, SM-10 |
| DP-18 | Opening import | batch spanning an out-of-scope company; collision probe against another company's batch | SM-01, SM-12, SM-23 |
| DP-19 | Print views | print of a foreign record | SM-01 |
| DP-20 | Error pages and messages | error-based existence probe | SM-01, SM-23 |

## 5. Capability rows

Every row of PERMISSIONS_MATRIX §5 gets the positive and denial cases of its class (SM-04); the capability codes are the row's own.

| W4 | Action family | Class | Cases (SM-04) |
| --- | --- | --- | --- |
| W4-01 | Users, roles, capabilities, company grants | OWN! | positive: the Owner; denial: every Admin — with ADM and ADM+ capabilities on the company — by page, command and API, and a role mapping that would carry the capability (OD-16) |
| W4-02 | Company master: create, identity assets, bank accounts, numbering rules, deactivate | OWN! | positive: the Owner; denial: every Admin — with ADM and ADM+ capabilities on the company — by page, command and API, and a role mapping that would carry the capability (OD-16) |
| W4-03 | Set waiver eligibility | OWN! | positive: the Owner; denial: every Admin — with ADM and ADM+ capabilities on the company — by page, command and API, and a role mapping that would carry the capability (OD-16) |
| W4-04 | Remove, make optional, relax or clear the client-original flag of a required non-waivable requirement | OWN! | positive: the Owner; denial: every Admin — with ADM and ADM+ capabilities on the company — by page, command and API, and a role mapping that would carry the capability (OD-16) |
| W4-05 | Supersede an Owner write-off | OWN! | positive: the Owner; denial: every Admin — with ADM and ADM+ capabilities on the company — by page, command and API, and a role mapping that would carry the capability (OD-16) |
| W4-06 | Attribute unexplained found stock | OWN! | positive: the Owner; denial: every Admin — with ADM and ADM+ capabilities on the company — by page, command and API, and a role mapping that would carry the capability (OD-16) |
| W4-07 | Resolve a pending unexplained-loss case (ADM+ only for an evidence-identified resolution, `DIR-027`) | OWN! | positive: the Owner; denial: every Admin — with ADM and ADM+ capabilities on the company — by page, command and API, and a role mapping that would carry the capability (OD-16) |
| W4-08 | Receivable write-off / formal disposition | OWN! | positive: the Owner; denial: every Admin — with ADM and ADM+ capabilities on the company — by page, command and API, and a role mapping that would carry the capability (OD-16) |
| W4-09 | Force Complete (denied to Admin, also through any API) | OWN! | positive: the Owner; denial: every Admin — with ADM and ADM+ capabilities on the company — by page, command and API, and a role mapping that would carry the capability (OD-16) |
| W4-10 | Client, supplier, product/service, unit, barcode, location and minimum-stock masters; archive | ADM | positive: an Admin holding the capability on the company; denial: the same Admin without it, and on a company outside its grants |
| W4-11 | Project setup and amendment, quotation, approval, commercial confirmation | ADM | positive: an Admin holding the capability on the company; denial: the same Admin without it, and on a company outside its grants |
| W4-12 | Reservation create/adjust/release; reservation cut on shrinkage | ADM (inventory) | positive: an Admin holding `stock.reserve` with its prerequisite `stock.view` on the company; denial: without the prerequisite (a disabled grant, RG-06), without the capability, and on a company outside its grants |
| W4-13 | Purchase, PO, receiving, dispatch, delivery, drop-ship confirmation, service handover | ADM | positive: an Admin holding the capability on the company; denial: the same Admin without it, and on a company outside its grants |
| W4-14 | Document issue, uploads, invoice issue, billing act, payment record/application, expense, disbursement, other Cash-In | ADM | positive: an Admin holding the capability on the company; denial: the same Admin without it, and on a company outside its grants |
| W4-15 | Add or tighten administrative requirements | ADM | positive: an Admin holding the capability on the company; denial: the same Admin without it, and on a company outside its grants |
| W4-16 | Normal completion decision | ADM | positive: an Admin holding the capability on the company; denial: the same Admin without it, and on a company outside its grants |
| W4-17 | Dispute hold set/clear | ADM | positive: an Admin holding the capability on the company; denial: the same Admin without it, and on a company outside its grants |
| W4-18 | Corrections and reversals, including warning overrides | ADM+ | positive: an Admin holding the ADM+ capability on the company; denial: an Admin with only ADM capabilities, and on a company outside its grants |
| W4-19 | Purchase cancellation | ADM+ | positive: an Admin holding the ADM+ capability on the company; denial: an Admin with only ADM capabilities, and on a company outside its grants |
| W4-20 | Refunds, fee settlements, tax settlements, expense/disbursement corrections | ADM+ | positive: an Admin holding the ADM+ capability on the company; denial: an Admin with only ADM capabilities, and on a company outside its grants |
| W4-21 | Stock adjustments, condition changes, opname variance, loss recognition, pending case, evidence-identified resolution | ADM+ | positive: an Admin holding the ADM+ capability on the company; denial: an Admin with only ADM capabilities, and on a company outside its grants |
| W4-22 | Inter-company allocation (grant on the consuming company only) | ADM+ | positive: an Admin holding the ADM+ capability on the company; denial: an Admin with only ADM capabilities, and on a company outside its grants |
| W4-23 | Operational closures; project cancellation | ADM+ | positive: an Admin holding the ADM+ capability on the company; denial: an Admin with only ADM capabilities, and on a company outside its grants |
| W4-24 | Admin N/A on a waiver-eligible requirement | ADM | positive: an Admin holding the capability on the company; denial: the same Admin without it, and on a company outside its grants |
| W4-25 | Opening import: prepare and dry-run / commit after sign-off | ADM / OWN | positive: `import.prepare` for an Admin with the company in scope and `import.commit` for the Owner after the joint sign-off (OD-13); denial: an Admin committing, and a batch spanning a company outside the preparer's scope (DP-18) |
| W4-26 | Availability checks, numbering, snapshots, HPP/loss attribution, revalidation | SYS | no capability: positive inside the authorized command that causes it; denial: no route or job reaches it except through that command (AZ-08) |
| W4-27 | Rendering, exports, notifications, restock/overdue alerts | BG | positive: run in the system context or after the authorized command (AZ-09); denial: the export and notification paths of DP-07, DP-09 and DP-11 after a revocation |

## 6. Owner-only denials

| OD | Cases here |
| --- | --- |
| OD-01 | SM-04 |
| OD-02 | SM-04 |
| OD-03 | SM-04 |
| OD-04 | SM-04 |
| OD-05 | SM-04 |
| OD-06 | SM-04 |
| OD-07 | SM-04 |
| OD-08 | SM-04 |
| OD-09 | SM-04 |
| OD-10 | SM-04 — each condition one by one |
| OD-11 | SM-04 |
| OD-12 | SM-04 |
| OD-13 | SM-04 |
| OD-14 | SM-04; SM-01 for the views |
| OD-15 | SM-04 |
| OD-16 | SM-05 |

## 7. Threats

Every threat of SECURITY §8 with the P8 proof it names and the cases that give it. **73 threats, each with at least one case; 0 without.**

| TM | Threat | P8 proof (SECURITY §8) | Cases here |
| --- | --- | --- | --- |
| TM-01 | Brute force and credential stuffing (S) | H8-06, H8-14, H8-16 | SM-06, SM-14, SM-16 |
| TM-02 | User enumeration through login, reset, the two-factor step, Google sign-in or timing (S, I) | H8-06, H8-07, H8-17, H8-20 | SM-06, SM-07, SM-17, SM-20 |
| TM-03 | Session fixation, also across the steps of a two-factor or Google login (S) | H8-06, H8-17 | SM-06, SM-17 |
| TM-04 | Session theft on a shared or unattended device, a previous user's Google session included (S) | H8-06, H8-20 | SM-06, SM-20 |
| TM-05 | Credential-link interception, replay or leakage in logs, Referer, history or flashed input (S) | H8-07 | SM-07 |
| TM-06 | Owner-account takeover (S, E) | H8-06, H8-17, H8-19 | SM-06, SM-17, SM-19 |
| TM-07 | Host-header poisoning of links (S) | H8-07 | SM-07 |
| TM-08 | CSRF on a state change (T) | H8-08 | SM-08 |
| TM-09 | Mass assignment of company, authority or state fields (T, E) | H8-03, H8-04 | SM-03, SM-04 |
| TM-10 | Forged reference to another company's record in a payload (T, I) | H8-01 | SM-01 |
| TM-11 | Direct URL or public_id of a Company B record (I) | H8-01 | SM-01 |
| TM-12 | Nested or related resources of Company B (I) | H8-01 | SM-01 |
| TM-13 | Shared masters as a route to price history or client relationships (I) | H8-01, H8-12 | SM-01, SM-12 |
| TM-14 | Pooled stock view → lot → movement history, lot code or cost revealing another company's acquisitions (I) | H8-12 | SM-12 |
| TM-15 | Search results or counts of Company B (I) | H8-01 | SM-01 |
| TM-16 | Export of Company B data, including a file generated before a partial revocation (I) | H8-11 | SM-11 |
| TM-17 | Report filters naming Company B (I) | H8-01 | SM-01 |
| TM-18 | Dashboards and action queues (I) | H8-01 | SM-01 |
| TM-19 | Inertia partial reload, deferred props or shared props (I) | H8-13 | SM-13 |
| TM-20 | Download of an issued PDF, evidence or import file of Company B (I) | H8-01, H8-09 | SM-01, SM-09 |
| TM-21 | Audit timeline of a cross-company event (I) | H8-12 | SM-12 |
| TM-22 | Alerts and notifications about Company B (I) | H8-13 | SM-13 |
| TM-23 | Duplicate-evidence oracle across companies or evidence families (I) | H8-12 | SM-12 |
| TM-24 | Unattributed-loss snapshot of other candidates (I) | H8-12 | SM-12 |
| TM-25 | Inter-company allocation views leaking source-company data (I) | H8-12 | SM-12 |
| TM-26 | Cached aggregates shared across scopes (I) | H8-13 | SM-13 |
| TM-27 | Error messages, validation or unique import keys as an existence oracle (I) | H8-01, H8-12 | SM-01, SM-12 |
| TM-28 | Browser history, back-forward cache or client cache after logout, expiry or remote termination (I) | H8-13 | SM-13 |
| TM-29 | Revoked grant or deactivated account with an open session or a queued export (E, I) | H8-13 | SM-13 |
| TM-30 | Admin or API Force Complete (E) | H8-04 | SM-04 |
| TM-31 | Admin write-off or its supersession (E) | H8-04 | SM-04 |
| TM-32 | Admin found-stock attribution (E) | H8-04 | SM-04 |
| TM-33 | Admin waiver eligibility or required-item relaxation (E) | H8-04 | SM-04 |
| TM-34 | Admin unattributed-loss resolution without definitive evidence (E) | H8-04 | SM-04 |
| TM-35 | Self-granting, an OWNER_ONLY capability through a role mapping, or demoting the last Owner (E) | H8-04, H8-05 | SM-04, SM-05 |
| TM-36 | ADM without ADM+ performing corrections (E) | H8-04 | SM-04 |
| TM-37 | Stored XSS rendered in React or a PDF (T) | H8-10 | SM-10 |
| TM-38 | SVG, polyglot or active office upload (T) | H8-09 | SM-09 |
| TM-39 | Path traversal on storage or download (T, I) | H8-09 | SM-09 |
| TM-40 | SSRF through the renderer (I) | H8-10 | SM-10 |
| TM-41 | Command injection, SSRF or file reads through the renderer or image decoders (E, I) | H8-10 | SM-10 |
| TM-42 | Decompression, zip or size bombs (D) | H8-09 | SM-09 |
| TM-43 | Expensive search or report exhaustion (D) | H8-14 | SM-14 |
| TM-44 | Backdating used to evade audit (R) | H8-12 | SM-12 |
| TM-45 | Shared accounts or impersonation hiding the actor (R) | H8-04 | SM-04 |
| TM-46 | CSV or spreadsheet formula injection into the Owner's workbook (T) | H8-11 | SM-11 |
| TM-47 | Replay of a command after revocation or by another account (E, I) | H8-13 | SM-13 |
| TM-48 | Secrets in logs, repository or client bundles (I) | H8-15 | SM-15 |
| TM-49 | Vulnerable or malicious dependency (T, E) | H8-15 | SM-15 |
| TM-50 | Clickjacking (T) | H8-08 | SM-08 |
| TM-51 | Log injection through user input (R) | H8-12 | SM-12 |
| TM-52 | Credential link consumed by someone other than its holder — the issuing Owner or anyone who saw the delivery message — to set the password and act as the account (S, R) | H8-07 | SM-07 |
| TM-53 | Allocation probing: dispatching single units from another company's lots to read their allocated cost, then reversing (I) | H8-12 | SM-12 |
| TM-54 | A company's private document uploaded as pool evidence and reaching every company (I) | H8-12 | SM-12 |
| TM-55 | Evidence of an invisible family uploaded, versioned or matched to tamper with it or probe for it (T, I) | H8-12 | SM-12 |
| TM-56 | Loss, disposal or count loss booked on another company's lot without a grant on it (T, E) | H8-04 | SM-04 |
| TM-57 | Operator abuse of the Owner-recovery command, which now also resets TOTP and unlinks Google, and of the OWNER-restoration command, whose honest use also reverses a removal of a pre-window Owner made legitimately during the compromise window, as a restored state does for an Owner removed legitimately between its backup and the window's start (S, E) | H8-07, H8-19, H8-21 | SM-07, SM-19, SM-21 |
| TM-58 | Password-hashing exhaustion by a distributed login flood (D) | H8-14 | SM-14 |
| TM-59 | A forged, replayed or injected Google callback — a stolen authorization code, a sign-in or link started on another site, a replayed ID token (S, T) | H8-20 | SM-20 |
| TM-60 | Google link takeover — an attacker's Google identity linked to a victim's account, a victim's identity linked to an attacker's account, or a link derived from a matching e-mail (S, E) | H8-20 | SM-20 |
| TM-61 | A previous user's Google session on a shared office PC or floor device used to sign in (S) | H8-17, H8-20 | SM-17, SM-20 |
| TM-62 | Google outage or loss of the person's Google account (D) | H8-20 | SM-20 |
| TM-63 | Guessing TOTP or recovery codes from one or many addresses, concurrently or in sequence (S) | H8-14, H8-17, H8-18 | SM-14, SM-17, SM-18 |
| TM-64 | Replay of an observed TOTP code, concurrently or later (S) | H8-17 | SM-17 |
| TM-65 | Theft of TOTP secrets — a database or backup leak, the secret shown again by a framework route, the key leaked with the database (I, S) | H8-02, H8-17, H8-21 | SM-02, SM-17, SM-21 |
| TM-66 | Theft or reuse of recovery codes — viewed on an unattended session, then used to replace the device (S) | H8-18 | SM-18 |
| TM-67 | Enrolment bypass or hijack — a high-risk account acting without TOTP, or a password holder enrolling a device before the person (E) | H8-17, H8-19 | SM-17, SM-19 |
| TM-68 | Reset abuse — an Owner persuaded to reset an Admin's TOTP, or a reset or recovery link used to take an account over (S, E) | H8-19 | SM-19 |
| TM-69 | Offline guessing of 8-character passwords after a database leak (I) | H8-06, H8-16 | SM-06, SM-16 |
| TM-70 | Real-time phishing that relays the password, or a Google sign-in, together with the TOTP code (S) | H8-17 | SM-17 |
| TM-71 | The breached-password check unavailable or tampered with (T, D) | H8-16 | SM-16 |
| TM-72 | One phone holding both the person's Google session and the authenticator, so that whoever holds it unlocked can pass both steps (S) | H8-20 | SM-20 |
| TM-73 | Anyone who can reach an account's second-factor step — its password holder, a browser still signed in to the person's linked Google account, or an abandoned authenticated session — failing codes on purpose to delay that account's sign-in (D) | H8-17 | SM-17 |

## 8. Accepted risks

An accepted risk is never tested as if it were removed. P8 proves the controls the Owner's acceptance rests on, and a test that shows the residual larger than accepted is a finding for the Owner, never a reason to weaken the control.

| Gap | The residual accepted | What P8 proves | Cases |
| --- | --- | --- | --- |
| GAP-038 | an 8-character password is weaker against offline guessing after a database leak (RISK-002) | the profile, the local and breached lists and the hash parameters | SM-06, SM-16 |
| GAP-039 | TOTP does not resist real-time phishing (RISK-003) | the challenge after every password and Google sign-in, single use, the alerts | SM-17 |
| GAP-040 | an Owner deceived into resetting an Admin's TOTP (RISK-004) | the reset's step-up, reason and out-of-band link, and its alerts | SM-19 |
| GAP-041 | operator abuse of the Owner-recovery command (RISK-005) | every use traced to the operator and noticed by the Owner | SM-07, SM-19, SM-21 |
| GAP-045 | a deliberate cooldown delaying an account's sign-in (RISK-006) | the cooldown's bounds, its escalation and the breach alert | SM-17 |
| GAP-046 | a breached password set while the range service is unreachable (RISK-007) | the recorded skip and the local list still applied | SM-16 |
| GAP-047 | one phone holding the Google session and the authenticator (RISK-008) | H8-20's cases and the advice of H7-16 on its page | SM-20 |
| GAP-048 | recovery reinstating an Owner removed legitimately (RISK-009) | the restored Owners listed with the removal they reversed before the review | SM-21 |

## 9. Where each case runs

- **Local and CI** — every case except those below; CI runs them on the production-like stack of [INFRASTRUCTURE §3](../09-operations/INFRASTRUCTURE.md#3-environments) at every pull request, fresh each time.
- **Staging** — the ephemeral, synthetic stack the operator starts before a release (DIR-079 CB-04), never touching the second production application on the VPS (INFRASTRUCTURE INF-07): the operator-run commands of SM-07, SM-19 and SM-21, the real Google redirect of SM-20 and the TLS and HSTS configuration of SM-08 on the staging host. The operator runs them; agents never reach the VPS.
- **Production** — no case. The post-deployment smoke check of [RELEASE_PLAN](../10-release/RELEASE_PLAN.md) is the operator's and reads only.

## 10. Coverage

Every requirement this document answers is a row of [TEST_STRATEGY §19](TEST_STRATEGY.md#19-coverage), which lists its section here; the tables of §§4–7 are built by script from their sources and their counts are checked there — 20 data paths, 27 W4 rows, 16 Owner-only denials and 73 threats, none without a case.

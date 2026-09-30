# P5 security and authorization quality gate

Status: REVIEW | Updated: 2026-09-30 | Owner: Planning

Scope: P5 — Security & Authorization, authorized and fast-tracked by [DIR-031](../00-governance/DECISION_LOG.md#dir-031-obs-010-and-tech-020--p5-authorization-entry-baseline-and-security-documentation) (twenty-seventh source record) on the published handoff baseline `6e640137901e0d16193e03004e142e9ea07b39ad`. This report records planning evidence for [PERMISSIONS_MATRIX](../02-domain/PERMISSIONS_MATRIX.md) and [SECURITY](SECURITY.md), for the narrow TECH-020 amendment of [DATABASE §4.13](DATABASE.md#413-ops--operations) and for the APPR-006 approval conditions. PERMISSIONS_MATRIX and SECURITY are APPROVED under [APPR-006](../00-governance/DECISION_LOG.md#appr-006--p5-security-and-authorization-approved) together with the `TECH-020`-amended DATABASE revision; this gate stays REVIEW as evidence, following the P2–P4 gate convention. It is not implementation acceptance and not permission to begin P6. No application, policy, migration, runtime, penetration or device evidence exists or is claimed.

## Baseline verification (OBS-010)

Verified before any edit: repository root `C:\Projects\MultipleCorp` with origin `https://github.com/yusufarst/MULTIPLECORP.git`; branch `main` tracking `origin/main`; after `git fetch origin`, HEAD, the local origin/main ref and live origin/main (`git ls-remote origin refs/heads/main`) all equal `6e640137901e0d16193e03004e142e9ea07b39ad` (`docs: record P4 handoff checkpoint`, parent `e95d083d7114a1d0c43f6e9cb6a439c93a70134b`); ahead/behind 0; `git diff --ignore-cr-at-eol --stat` empty, empty index, no untracked file; no merge, rebase, cherry-pick, revert or bisect state; no PERMISSIONS_MATRIX, SECURITY, P6+ or application file; OWNER_DECISION_REQUIRED = 0; gaps 31 — 3 CLOSED, 27 OPEN, 0 OWNER_DECISION_REQUIRED, 1 ACCEPTED_RISK; all 26 source files matched SOURCE_OF_TRUTH; the five APPR-005 hashes equalled the committed blobs. The repository matched the Owner's expected baseline, so work proceeded.

## Inputs read

The directive (source record 27) in full, with its section 10 planner notes treated as analysis only; the DIR-031 §3 intake files (AGENTS, CLAUDE, README, CONTEXT_INDEX, CURRENT_STATE, NEXT_ACTION, SOURCE_OF_TRUTH, DECISION_LOG, GAP_REGISTER, CHANGE_CONTROL, AGENT_OPERATING_MODEL, ENGINEERING_PRINCIPLES, PROJECT_CHARTER); the canonical specifications they lead to — V1_SCOPE, ACCEPTANCE_CRITERIA and REFERENCE_COVERAGE for the security clauses, DOMAIN_MODEL (concept catalogue and sensitivity classes), BUSINESS_RULES, WORKFLOWS (§2, §4, §9–§14 and every workflow the matrix cites), DATABASE (§1, §3–§5, §15–§16, §19–§28, §31) and ARCHITECTURE (§2, §5, §8–§14, §17, §19–§20); the P4 gate; and the current official documentation below.

## Framework and standards evidence

Read on 2026-09-30 from official sources and recorded in the [SECURITY source table](SECURITY.md#source-and-version-evidence): Laravel 13.x (Hashing, Authentication, Resetting Passwords, HTTP Session, CSRF Protection, Validation, Rate Limiting, Vite), Inertia v3 (shared data, partial reloads, history encryption), NIST SP 800-63B-4 (final, July 2025), the OWASP Password Storage and CSV Injection cheat sheets. Reviewer B re-checked every claim against the official pages on the same date; four were corrected (B-15: the NIST date and NFC normalization, the formats the `image` rule admits, the scope of the framework's previous-key support). Versions are evidence, not pins; exact versions are chosen and re-verified under DEP-07.

## Planner notes — dispositions

The section 10 notes are analysis, never Owner intent; each was verified against the repository, which wins where they differ.

| Note | Disposition | Where |
| --- | --- | --- |
| PN-1 role versus individual capability | Adopted in the variant the approved tables support: the OWNER role holds every capability, ADMIN_OPERASIONAL none, so every Admin capability is an individual grant; OWNER_ONLY takes effect only through the OWNER role; a new Admin has nothing until granted. DATABASE §4.1 is not contradicted ("Rules served" names what a table serves; reviewer B: NO DEFECT), so it is not amended. | RG-01–RG-12, D-PM-01 |
| PN-2 owning-company identity in physical views | **Not adopted.** DOMAIN_MODEL classes the Legal Company S2 and the Receiving Lot, with its source company, S3, so the identity is protected; the approved workflows need only whether a lot or reservation lies outside scope, which WF-INV-03, SF-RSV-CUT, QS-20, L-42 and SF-UNATTRIBUTED already reveal. A non-identifying relation marker suffices, so no workflow needs a protected category and no Owner decision arises (reviewer A: N-37). | PJ-04, D-PM-02 |
| PN-3 unattributed-loss snapshot | Adopted: physical result to `stock.view`; each company's candidate, exposure and group rows to its `cost.view` holders; the evidence resolver sees candidate lots and quantities of companies in its scope without costs; the Owner sees all. | PJ-07, rows 67–72 |
| PN-4 duplicate-evidence oracle | Adopted in its strictest form: a match the actor may not view — another company or an invisible family — gives the actor no signal at all and is listed for the Owner. | DP-13, FL-10, D-PM-04 |
| PN-5 consuming project's allocated cost | The approved baseline exactly: the consuming project's allocated cost in full to entitled viewers; only source-side identifiers masked; no source-company grant implied. | PJ-05, PJ-06, D-PM-03 |
| PN-6 reset and onboarding channel | Adopted without any paid service: Owner-issued, single-use, short-lived, keyed-hash credential links delivered out of band; the person sets the password; every step audited; self-service e-mail reset only if P9 confirms a zero-cost channel. WF-ACC-01's responsibilities are unchanged — onboarding by the Owner through the approved channel. | AU-11–AU-14, D-SEC-05, H9-06 |
| PN-7 audit timelines and QS-14 | Adopted: audit keys projected by class and family, cross-company events masked per side; only `review.view` opens QS-14. | PJ-11, PJ-12, section 10 of PERMISSIONS_MATRIX |
| PN-8 DIR-027 evidence exception | Adopted: six server-verifiable conditions, the assigned company derived, never chosen; what the evidence proves is the actor's audited judgment, reviewable by the Owner, as with every evidence-based ADM+ correction, so no policy is invented. | OD-10 |
| PN-9 jobs, exports, caches, alerts, revocation | Adopted: authorization per request and per job step, exports bound to their generation context, scope-keyed caches, alerts derived at display time. | AZ-03, AZ-09, DP-07, DP-09–DP-11, DP-14 |
| PN-10 Inertia | Adopted: shared props hold no S2–S4 data; partial, optional and deferred props pass the same policies, scope-first query classes and resources. | WS-07, DP-15 |
| PN-11 sessions | A server-side PostgreSQL session store (the framework `database` driver) chosen at Level 1, so deactivation ends every session by user id; provisioning handed to P9. | AU-06, AU-09, D-SEC-02, H9-04 |

## Required coverage (DIR-031 §7)

| Item | Designed in | Result |
| --- | --- | --- |
| A. Authorization model | AZ-01–AZ-12; CS-01–CS-08; EN-01–EN-05 | COVERED — server order, scope resolver, per-command re-check of every company-owned reference, capabilities never roles, per-request evaluation, revoked access rejected on the next request |
| B. Capability catalogue | PERMISSIONS_MATRIX §4 and §5; RG-08, RG-12 | COVERED — 80 capabilities in the OB §17 form, all 27 WORKFLOWS §4 rows mapped to their class, view, report and export capabilities, OWNER_ONLY never grantable, ADM+ separately grantable, no new launch role, custom roles deferred but compatible |
| C. Role and grant model | RG-01–RG-12; the AC-13 resolution | COVERED — default deny, least privilege, Owner-controlled grants, L-34 |
| D. Field projection | §7.1–§7.4: 124 tables, PJ-01–PJ-23, RD-01–RD-10 | COVERED — physical-only pooled views, S3/S4 separation, pool evidence, unattributed exposure, allocation, supplier price history, shared masters, audit values, consolidated views, cross-company Admin queues, allocated-cost treatment |
| E. Cross-company links | XL-1–XL-5 | COVERED — allocation needs ADM+ and the consuming company only and implies no source visibility |
| F. Owner-only and ADM+ enforcement | OD-01–OD-16; §4 ADM_PLUS rows; section 10 of PERMISSIONS_MATRIX | COVERED on every path — web, Inertia, replay, import, job, console and any future API |
| G. Authentication and credentials | AU-01–AU-17; D-SEC-01–D-SEC-06, D-SEC-12, D-SEC-14 | COVERED |
| H. Web and input | WS-01–WS-12; AZ-11; DP-01–DP-03, DP-15, DP-20 | COVERED |
| I. Files | FL-01–FL-11; DP-05; PJ-15, PJ-19, PJ-20 | COVERED |
| J. Data paths | DP-01–DP-20 | COVERED — every path of the list, including formula neutralization, revoked access and duplicate warnings |
| K. Other threat classes | FL-03, FL-07, FL-08; WS-12; SX-05; EN-06 and D-SEC-07 | COVERED — no row-level security in V1, with reasons |
| L. Audit and security logging | LG-01–LG-07; PJ-11, PJ-21 | COVERED |
| M. Evidence duplicate warnings | FL-10; DP-13; D-PM-04 | COVERED |
| N. Threat model and handoffs | TM-01–TM-58; H6-01–H6-12, H7-01–H7-14, H8-01–H8-15, H9-01–H9-12 | COVERED |

## Gap and deferred-obligation closure (DIR-031 §8)

Status values: DESIGNED (the P5 design exists); HANDED TO a later phase with its owner; N/A with reason. No gap is closed: each still needs runtime proof in P6, P8 or P9.

| Item | P5 home | Status |
| --- | --- | --- |
| GAP-006 visibility and field projection (CRITICAL) | PERMISSIONS_MATRIX §6–§8, §11; SECURITY EN-04 | DESIGNED; cache and job mechanisms HANDED TO P6 (H6-04, H6-05); denial proof HANDED TO P8 (H8-01, H8-12) |
| GAP-022 completion authority, Force Complete denial (CRITICAL) | OD-05, OD-06, OD-12; WS-02 | DESIGNED; AX-26 mechanics HANDED TO P6; denial and audit tests HANDED TO P8 (H8-04) |
| GAP-005 correction capabilities (DIR-024 D-5) | §4 ADM_PLUS rows; W4-18–W4-23; RG-08; OD-15 | DESIGNED; tests HANDED TO P8 (H8-04) |
| GAP-026 tax evidence (S4) and settlement authority | PJ-08 finance family; `settlement.record` (W4-20); OD-04 | DESIGNED; fixtures HANDED TO P8 |
| GAP-030 Owner-only resolution and the DIR-027 exception | OD-09, OD-10; PJ-07; AZ-08 | DESIGNED; atomicity HANDED TO P6; one test per OD-10 condition HANDED TO P8 (H8-04) |
| GAP-003 P5 scope aspects | XL-1; PJ-05, PJ-06; AZ-08 | DESIGNED; atomicity HANDED TO P6, cross-company fixtures to P8 |
| GAP-031 privilege requirements | EN-07; SX-03, SX-04 | HANDED TO P9 (H9-01, H9-03) |
| GAP-013 security-event detection | LG-02, LG-07 | DESIGNED; delivery and retention HANDED TO P9 (H9-08, H9-10) |
| GAP-015 session expiry versus stale forms | AU-07; H7-05, H7-13 | DESIGNED; presentation HANDED TO P7, device and E2E proof to P8 |
| DATABASE §31 — capability catalogue | PERMISSIONS_MATRIX §4 | DESIGNED |
| DATABASE §31 — S1 versus S3/S4 projection, pool evidence and exposure rows | §7; PJ-01–PJ-08; rows 48–72 and 90–95 | DESIGNED |
| DATABASE §31 — scoping of queries, searches, exports and downloads | CS-07; DP-02, DP-05–DP-07 | DESIGNED |
| DATABASE §31 — ADM+ and Owner-only enforcement including DIR-027 | §4; §9 (OD-01–OD-16) | DESIGNED |
| DATABASE §31 — upload validation | FL-01–FL-07 | DESIGNED |
| DATABASE §31 — duplicate warnings | FL-10; DP-13 | DESIGNED |
| DATABASE §31 — the RLS option | EN-06; D-SEC-07 | DESIGNED — not used in V1, with reasons; remains a compatible later defence |
| WORKFLOWS §13 — section 4 into capabilities, default grants and ADM+ authorities | §3–§5 | DESIGNED |
| WORKFLOWS §13 — physical-only lot projection | PJ-01–PJ-04, PJ-23 | DESIGNED |
| WORKFLOWS §13 — S3/S4 protection of costs, settlements and tax evidence | PJ-05, PJ-06, PJ-08; §7.2 | DESIGNED |
| WORKFLOWS §13 — Owner review queue access | section 10 of PERMISSIONS_MATRIX; OD-14 | DESIGNED |
| WORKFLOWS §13 — denial of Admin/API Force Complete | OD-12 | DESIGNED |
| WORKFLOWS §13 — write-off and its supersession | OD-07, OD-11, OD-15 | DESIGNED |
| WORKFLOWS §13 — surplus attribution | OD-08 | DESIGNED |
| WORKFLOWS §13 — unexplained-loss attribution, except the DIR-027 ADM+ resolution | OD-09, OD-10 | DESIGNED |
| WORKFLOWS §13 — required-item removal | OD-06 | DESIGNED |
| WORKFLOWS §13 — content-level duplicate detection (L-45 hardening) | FL-10; DP-13 | DESIGNED; lookups HANDED TO P6 (H6-09) |
| WORKFLOWS §14 — masking of a consuming project's own allocated cost | PJ-06; D-PM-03 | DESIGNED — the baseline, with only source-side identifiers masked |
| WORKFLOWS §14 — the P5 part of tax configuration | OD-04; rows 13–16; PJ-08 | DESIGNED |
| ARCHITECTURE §8 — server order, scope resolver, capabilities, field projection, downloads and exports | AZ-01, AZ-02, AZ-04; EN-02; §4; §7; DP-05, DP-07; FL-07 | DESIGNED |
| ARCHITECTURE §20 — policies, capabilities, field projection, upload controls and denial paths | AZ-06; EN-01; §4; §7; FL-01–FL-07; §9, §11 | DESIGNED |
| DATABASE §22 upload validation | FL-01–FL-07 | DESIGNED |
| DATABASE §23 security events | LG-02 (TECH-020 amendment), LG-03, LG-06 | DESIGNED; storage HANDED TO P9 (H9-08) |
| DATABASE §28 privileged-change audit | LG-01; RG-11 | DESIGNED |
| DEP-08 | AU-12; D-SEC-05 | DESIGNED — Owner-issued out-of-band links, no paid service; a zero-cost mail channel for the optional self-service reset HANDED TO P9 (H9-06) |
| WF-ACC-01 Owner-account recovery (P5 part) | AU-14; TM-57 | DESIGNED; runbook HANDED TO P9 (H9-07) |
| L-34 last active Owner | RG-09; OD-02 | DESIGNED; serialization HANDED TO P6 (H6-02) |
| AC-13 — Owner access to all companies and consolidated views | RG-02, RG-03; RD-07 | DESIGNED |
| AC-13 — Admin access server-enforced, capability-based, limited to explicit grants | AZ-01–AZ-05; RG-03, RG-04 | DESIGNED |
| AC-13 — with inventory permission, necessary shared physical availability | PJ-01; RD-02 | DESIGNED |
| AC-13 — never another company's cost, profit, banks, invoices, billing, payments, financial records, private documents or unrelated transaction detail | §7; PJ-02–PJ-08 | DESIGNED |
| AC-13 — without inventory permission the pooled view is denied | the AC-13 resolution; RD-02 | DESIGNED |
| AC-13 — forged URL, ID, query, payload, API, file download, search, export or master traversal | DP-01–DP-07; CS-04; PJ-10 | DESIGNED |
| AC-13 — no unauthorized mutation | AZ-05, AZ-08; CS-04 | DESIGNED |
| AC-13 — login, logout, reset, hashing, throttling, session renewal and invalidation, revoked access | AU-02–AU-12; AZ-03 | DESIGNED; proof HANDED TO P8 (H8-06, H8-07, H8-13) |
| AC-09 authority clause (waiver only of eligible requirements; ineligible attempts denied) | `requirements.waive`; OD-05, OD-06 | DESIGNED |
| AC-21 authority clauses (Owner-only Force Complete; Admin and API attempts denied) | OD-12; WS-02 | DESIGNED |
| AC-12 authority clause (authorized corrections keep reason, actor and linked history) | §4 ADM_PLUS rows; LG-01; AZ-10 | DESIGNED |
| AC-23 authority and scope clauses (Owner summaries, permitted Admin queues, authorized search, filtered reports and safe exports) | RD-07; PJ-13; CS-07, CS-08; DP-02, DP-06–DP-08; FL-09 | DESIGNED |
| REF-001 authentication | AU-02–AU-09 | DESIGNED |
| REF-002 built-in roles | RG-01 | DESIGNED |
| REF-003 dynamic capabilities, custom roles later | RG-01, RG-12 | DESIGNED — custom roles deferred, model compatible |
| REF-049 audit of sensitive changes | LG-01; RG-11 | DESIGNED |
| REF-056 web security dimensions | WS-01–WS-12; FL-01–FL-11; AZ-11 | DESIGNED |
| CAP-01 security aspects (users, capabilities, grants, company master) | RG-05; OD-01–OD-03; AU-01 | DESIGNED |
| CAP-09 security aspects (checklist files, waiver authority) | PJ-08; OD-05, OD-06 | DESIGNED |
| CAP-10 security aspects (financial facts and audited corrections) | §4 finance capabilities; PJ-08; RD-04 | DESIGNED |
| CAP-11 security aspects (financial views, tax configuration) | RD-04–RD-06; OD-04 | DESIGNED |
| CAP-13 secure access, default deny, private files, audit | SECURITY §2–§6; PERMISSIONS_MATRIX §2–§11 | DESIGNED |
| CAP-17 security aspects (dashboards, queues, alerts, lookup, reports, exports) | PJ-13; RD-01–RD-10; DP-06–DP-08, DP-11 | DESIGNED |
| CAP-18 security aspects (Owner-only Force Complete) | OD-12 | DESIGNED |
| OB §17 roles and permissions | PERMISSIONS_MATRIX §1–§4; AZ-01, AZ-02 | DESIGNED |
| OB §24 security | SECURITY §2, §4, §5 | DESIGNED |
| OB §25 leak assumption | AU-02; LG-03; D-SEC-01; SECURITY §1 principles | DESIGNED |
| OB §36 auditability | LG-01–LG-07 | DESIGNED |
| OB §37 operations boundary | AU-14; SX-03, SX-04 | DESIGNED; runbooks HANDED TO P9 |
| OB §41 authorization tests | AZ-11; H8-01–H8-15 | HANDED TO P8 |

## Re-derived counts

Recomputed from the final text, not reused:

- **Capabilities:** 80 — ADM 37 (7 view and 30 command), ADM_PLUS 26, OWNER_ONLY 17 (5 view and 12 command); all 27 WORKFLOWS §4 rows mapped (W4-01–W4-27), each ADM, ADM+ and OWN! row only to capabilities of its class.
- **Tables:** all 124 DATABASE §4 tables classified — 101 with one class (S0 7, S1 11, S2 34, S3 47, S4 2), 19 split, 3 following their owning record or version and 1 operational record outside the classes (`command_log`), under the one rule stated in PERMISSIONS_MATRIX §7.2.
- **Rules:** AZ 12, RG 12, CS 8, PJ 23, RD 10, XL 5, OD 16, DP 20, D-PM 12; AU 17, EN 8, WS 12, FL 11, LG 7 (19 security-event kinds), SX 7, TM 58, D-SEC 14; handoffs H6 12, H7 14, H8 15, H9 12.
- **Findings:** self-review 13 (all LEVEL-1, fixed); independent review 44 — CRITICAL 0, HIGH 0, MEDIUM 9, LOW 19, LEVEL-1 CORRECTION 14, DEFERRED OBLIGATION 2, OWNER_DECISION_REQUIRED 0; independent verification of the fixes 17 — MEDIUM 1, LOW 3, LEVEL-1 CORRECTION 12, DEFERRED OBLIGATION 1, OWNER_DECISION_REQUIRED 0; final check of those fixes 10 — LOW 2, LEVEL-1 CORRECTION 8, OWNER_DECISION_REQUIRED 0; unresolved defects 0.
- **Gaps:** 33 findings — 3 CLOSED, 29 OPEN, 0 OWNER_DECISION_REQUIRED, 1 ACCEPTED_RISK.
- **Source records:** 27, each hash recorded once in SOURCE_OF_TRUTH; record 27 is 1,142 lines / 41,384 bytes, SHA-256 `9220904F8AB5A2A801223B7C4DA621679C33B0E1E39057648AC96CDB73F3C282`.

## Self-review

Checked against DIR-031 §7 and §8, for anti-duplication (the P5 files cite business rules and never restate, relax or extend them), authority classification (every control is Level 1; nothing widens or narrows approved visibility, authority, responsibilities, cost or scope) and traceability. Thirteen LEVEL-1 findings, all fixed before the independent review:

| ID | Finding | Fix |
| --- | --- | --- |
| SR-01 | Role-and-grant identifiers were not contiguous | Renumbered RG-08–RG-12 and every reference |
| SR-02 | View capabilities were listed against §4 rows of another class | Mapped to their read classes instead |
| SR-03 | Classification counts were written before derivation | Replaced by a measured tally |
| SR-04 | Pool and master commands by an account without any company grant were undefined | CS-05 |
| SR-05 | CS-08 was broader than the approved Admin action queue across granted companies | Narrowed to figures combining companies |
| SR-06 | Reservation-cut authority was implicit | AZ-08 names `stock.reserve` |
| SR-07 | OD-01's "own display profile" disagreed with AU-13 | Aligned |
| SR-08 | `projects.manage` could set selling prices without `finance.view` | Stated in the catalogue |
| SR-09 | QS-05 sat in the project read class | Moved to RD-05 with PJ-18 |
| SR-10 | Operational layouts printing prices had no family | PJ-15 |
| SR-11 | Serial lookups could show history out of scope | DP-06 |
| SR-12 | Argon2id measurement and trusted-proxy configuration had no P9 owner | H9-11, H9-02 |
| SR-13 | GAP-033 cited a new-address Owner-login alert missing from LG-07 | Added to LG-07 |

## Independent adversarial review

Two fresh read-only reviewers that did not write the documents, each given only the repository and told to ignore this gate: **A** — company isolation, projection and authority; **B** — authentication, sessions, credentials, web and input, files, logging, secrets, threat model, handoffs and governance continuity. Together they red-teamed every DIR-031 §14 scenario. A: CRITICAL 0, HIGH 0, MEDIUM 7, LOW 10, LEVEL-1 8, OWNER_DECISION_REQUIRED 0, DEFERRED 1 (P6), NO DEFECT 37. B: CRITICAL 0, HIGH 0, MEDIUM 2, LOW 9, LEVEL-1 6, OWNER_DECISION_REQUIRED 0, DEFERRED 1 (P6/P7), NO DEFECT 39. Every finding was verified against the repository and is valid; the author and the reviewers disagree on no isolation finding. Each was fixed, or recorded as a deferred obligation, as follows.

| ID | Class | Finding | Disposition |
| --- | --- | --- | --- |
| A-01 | MEDIUM | Movement history showed another company's lot acquisition kind, date and quantity | PJ-03 itemizes only movements of companies in scope and pool movements; another company's movements reach the actor only through pooled figures; PJ-01, PJ-02 and rows 48, 50 and 51 aligned with DOMAIN_MODEL (Receiving Lot S3; movement "S1 qty; S3 cost/refs"); the purchase reference to `cost.view` and the supplier delivery reference as on the receiving line (audiences aligned under V-02); D-PM-11; TM-14; H8-12 |
| A-02 | MEDIUM | Lot codes shown across companies had no format constraint | PJ-23 opaque workspace-wide lot codes; H6-10; H8-12 |
| A-03 | MEDIUM | Pool evidence could carry a company's private document to every company | PJ-08: pool WAREHOUSE_RECORD and OTHER evidence holds physical content of shared stock only, confirmed by the uploader (H7-11), and every pool upload reaches the Owner's review queue; OD-10(b) limited to the pool evidence DATABASE §4.10 admits; SECURITY §8 residual corrected; TM-54; D-PM-12 (a first case-linked visibility restriction was withdrawn under V-01) |
| A-04 | MEDIUM | Evidence of a family could be written without being readable, and matching was an oracle | PJ-08 and `evidence.upload`: writing needs the family's view capability; DP-13 and FL-10 disclose only matches visible under PJ-08; identity matching only against viewable evidence; TM-55 |
| A-05 | MEDIUM | The authority for a loss on another company's lot was implicit | AZ-08: a loss, disposal, count loss, L-42 loss confirmation or its reversal needs the bearer company in scope (XL-1 when the bearer differs), else REJECTED and routed; condition changes, re-inspections, moves and counting stay pool effects; OD-10(e) cites it; TM-56; H8-04 |
| A-06 | MEDIUM | Export delivery after a partial revocation was unspecified | DP-07: each export records its company set, view capabilities and masking context and is delivered only while all still hold, otherwise withheld and purged; H6-04; H8-11; TM-16 |
| A-07 | MEDIUM | The QS-14 definition narrowed the approved queue | Section 10 of PERMISSIONS_MATRIX cites WORKFLOWS §10 and DATABASE §23, including OWNER_ONLY events and the WF-FIN-04/05/06 items; AZ-10 records the highest authority class exercised; OD-16; H8-05 |
| A-08 | LOW | PJ-04 claimed no count could be inferred | PJ-04 reworded: non-identifying, disclosing only what the approved workflows reveal |
| A-09 | LOW | Deferred props were only capability-checked | WS-07 and DP-15: the same scope-first query classes and resources as the page; TM-19; H8-13 |
| A-10 | LOW | Cache keys were too coarse | DP-10 and H6-05: the full effective scope set and capability set |
| A-11 | LOW | Validation before scope was an existence oracle | CS-04 and EN-01: references resolved through the scope resolver during validation; TM-27 |
| A-12 | LOW | OWNER_ONLY exclusivity covered only user grants | RG-02, AZ-02, OD-02, OD-16 and EN-02: honoured only through the built-in OWNER role; H8-05; TM-35 |
| A-13 | LOW | AZ-08's rationale and the masking of cascade results | AZ-08 states its test with citations; D-PM-09 rationale corrected; DP-16 masks COMMITTED result identifiers; row 38 masks `correction_reference` |
| A-14 | LOW | QS-21 had been made Owner-only without grounds | QS-21 per family within scope (RD-02, RD-03, RD-04, RD-08) and to the Owner through QS-14 |
| A-15 | LOW | CS-08 could forbid sorting approved queues | CS-08 limited to aggregate figures over several companies' S3 values; D-PM-10 |
| A-16 | LOW | Workspace-wide import keys revealed other companies' batches | DP-18: "not importable — Owner review", listed for the Owner; TM-27; H8-12 |
| A-17 | LOW | Allocation probing was missing from the threat model | TM-53 with the LG-07 signal |
| A-18 | LEVEL-1 | Security-event definitions diverged from DATABASE §4.13 | The TECH-020 amendment below; LG-02 |
| A-19 | LEVEL-1 | Table classification labels and counts followed no single rule | One rule stated in §7.2; 13 rows relabelled; counts re-derived from the table |
| A-20 | LEVEL-1 | Row 27 cited FL-04 | FL-03 |
| A-21 | LEVEL-1 | PJ-02 and PJ-04 disagreed on naming the source company | PJ-04 prevails — named within scope; an in-scope lot's acquisition kind, date and quantity to `stock.view`, its basis and cost to `cost.view` and its source reference as the referenced record (aligned under V-02); §7.1 S3 row; D-PM-05 |
| A-22 | LEVEL-1 | PJ-07 contradicted rows 68–70 | Quantities without costs to the evidence resolver for companies in scope; rows 68–70 |
| A-23 | LEVEL-1 | `parties.manage` had no prerequisite | `projects.view`; supplier details to `projects.view` or `cost.view` (row 21, PJ-10) |
| A-24 | LEVEL-1 | Re-plan was split across two authority classes | Re-plan under `projects.manage`, its release also needing `stock.reserve`; `projects.cancel_scope` is SF-CANCEL-LINE only |
| A-25 | LEVEL-1 | Reference and class-claim wording | AZ-05 and CS-04 say "company-owned reference"; the §5 sentence corrected |
| A-26 | DEFERRED (P6) | Command replay must be bound to its actor | Requirement in DP-16; mechanism H6-03; TM-47 |
| B-01 | MEDIUM | Inertia history outlived expiry and remote termination | AU-06 and WS-07: history and client caches cleared, or a full reload, at every authentication boundary; H7-12; TM-28; H8-13 |
| B-02 | MEDIUM | Another party could consume a credential link undetectably | AU-12 and AU-13: own credential events, a first-login notice and an Owner notice on use; PJ-17, PJ-21, row 119; TM-52 rewritten; residual in SECURITY §8; H7-14; H8-07 |
| B-03 | LOW | Credential-link residue and framework-broker traps | AU-11 purpose-bound store with its own purge and no stock broker; AU-12 fragment removed from history, no token in page state, flash or limiter keys, the issued link as non-persisted flash; WS-10; TM-05 |
| B-04 | LOW | The throttling claim was overstated and had bypass gaps | AU-05 claim narrowed with the shared-address residual and IPv6 per /64; WS-12 adds step-up and link endpoints and separate scanner limits; H8-14; TM-01 |
| B-05 | LOW | The dummy hash and hash-algorithm verification interplay | AU-04 driver-matched dummy hash; AU-02 planned driver migration; H9-11; TM-02 |
| B-06 | LOW | Export download after a partial revocation | As A-06 |
| B-07 | LOW | No allowlist for the ARCHIVE purpose or office formats | FL-02 ARCHIVE allowlist and an attachment-only office class; H8-09; TM-38 |
| B-08 | LOW | No header profile for inline renditions | FL-07 two profiles, frame-ancestors on every file response; WS-08 report-only on staging with a throttled same-origin endpoint; H8-08, H8-09 |
| B-09 | LOW | Image tools were not restricted to allowlisted decoders | FL-03 allowlisted coders and header-first dimensions; H9-12; H8-10; TM-41 |
| B-10 | LOW | Operator abuse of Owner recovery and hashing exhaustion were missing | AU-14 (Owner present, operator identity audited, alert channel); TM-57, TM-58; H6-12; H9-02, H9-06; the operator added to the actor list |
| B-11 | LOW | Supply-chain controls caught only known advisories | SX-05 lockfile-only installs, reviewed update diffs with install scripts, SHA-pinned CI actions, no production secrets in CI; H8-15 |
| B-12 | DEFERRED (P6/P7) | Atomic token consumption, limiter failure behaviour and background requests | H6-11; H6-07; AU-07's idle definition; H7-13 |
| B-13 | LEVEL-1 | DATABASE §4.13 contradicted LG-02 | The TECH-020 amendment below |
| B-14 | LEVEL-1 | AU-10 contradicted PJ-17 and PJ-21 | Owner session and credential-link summaries in PJ-17 and PJ-21; the §7.2 note; raw session address and user agent live only with the session (LG-06, H9-04) |
| B-15 | LEVEL-1 | Seven evidence and cross-reference errors | NIST dated July 2025; NFC normalization in AU-03; the `image` rule's formats in the source table and FL-01; SX-02 rotation; TM-44 cites BR-DT-02; SX-01 and EN-07 name the optional reporting role; H7-06 gains the capability-by-company preview |
| B-16 | LEVEL-1 | FL-10's "in scope" was ambiguous | FL-10 and DP-13: visible under PJ-08 |
| B-17 | LEVEL-1 | Stale handoff and continuity wording | NEXT_ACTION, the CURRENT_STATE gap lines and the TECH-020 report list; the CHANGELOG entry is written with the continuity freeze, before the commit (V-11) |
| B-18 | LEVEL-1 | Forward-dated claims | This gate written with the content it is credited with before any commit; freeze wording replaced at approval |

One draft correction was itself caught before it was applied: a first A-01 fix would have shown every lot's arrival date and acquired quantity as S1, widening DOMAIN_MODEL's Receiving Lot class. It was discarded; the applied fix keeps those facts S3 outside scope.

## Adversarial re-test

After the fixes each scenario the findings touched was re-run against the corrected text. The scenarios the reviewers recorded as NO DEFECT stay so: most fixes tighten a rule or add a detective control, and the fixes that open a projection further — an in-scope lot's provenance and a movement's own references to `stock.view`, supplier details to `projects.view`, company identity assets to `cost.view`, candidate quantities to the evidence resolver, QS-21 per family — stay inside company grants and open no path out of scope.

| # | Scenario | Result against the corrected text |
| --- | --- | --- |
| 1 | A Company-A-only `stock.view` Admin opens one of B's lots and its history | PASS — physical fields and the relation marker only; B's movements not itemized; no acquisition kind, date, quantity, reference or cost (PJ-01–PJ-04) |
| 2 | B's lot code read on screen or on a printed label | PASS — opaque, carrying no company, supplier, purchase or date component (PJ-23, H6-10) |
| 3 | A resolver re-uploads B's supplier delivery note as pool evidence to cite it | PASS with a recorded residual — the content rule and the upload confirmation forbid it, and the Owner's review of every pool upload, with DP-13 duplicate detection, brings it to light; a copy uploaded anyway stays visible to holders of `stock.view` and `evidence.view` (SECURITY §8); a resolver holding the assigned company cites genuine pool evidence whoever linked it (PJ-08, OD-10(b)) |
| 4 | An A Admin without `finance.view` uploads a BANK_STATEMENT matching an existing one | PASS — refused without the family capability; no match disclosed (PJ-08, DP-13, FL-10) |
| 5 | An A-only ADM+ disposes of units of B's lot | PASS — REJECTED and routed; a condition change remains a pool effect (AZ-08) |
| 6 | An export filtered to A and B is delivered after B is revoked | PASS — withheld and purged (DP-07, H6-04) |
| 7 | A dispatch carrying an allocation, or a payment with an overridden warning, is audited as ADM | PASS — the highest class exercised is recorded, so it reaches QS-14; OWNER_ONLY events and the WF-FIN-04/05/06 items are listed (AZ-10, section 10 of PERMISSIONS_MATRIX) |
| 8 | A deferred "related projects" prop on a master page after a partial reload | PASS — scope-first query class and resource; no B project returned (WS-07, DP-15) |
| 9 | A cache filled for an A+B actor read by an A-only actor | PASS — keyed by the full scope and capability sets (DP-10, H6-05) |
| 10 | B's public identifier submitted to probe validation | PASS — same error as a nonexistent identifier (CS-04, EN-01) |
| 11 | An OWNER_ONLY capability seeded into the ADMIN_OPERASIONAL role | PASS — ignored, security-logged and reconciled (RG-02, OD-16, H8-05) |
| 12 | An A correction under CM-17/AX-34 touches B's completed project | PASS — a deterministic SYS consequence; B's identifiers masked in the COMMITTED response and in `correction_reference` (AZ-08, DP-16, row 38) |
| 13 | An A-scoped preparer imports a file matching B's batch | PASS — "not importable — Owner review", listed for the Owner (DP-18) |
| 14 | Single-unit allocations from another company's lots, then reversals, to read costs | PASS — separately granted ADM+, mandatory reason, QS-14 review and an LG-07 signal (TM-53); the allocated cost itself is the approved baseline |
| 15 | On a shared PC a session expires and the next person presses Back | PASS — history and client caches cleared, or the document reloaded, at the boundary (AU-06, H7-12) |
| 16 | Someone other than the holder uses an onboarding link | PASS — the holder sees the event and a first-login notice; the Owner is notified of the use; residual recorded (AU-12, AU-13) |
| 17 | An abandoned onboarding link in browser history | PASS — fragment removed on load; the token is never in page state, flashed input or a limiter key (AU-12) |
| 18 | Failed logins from the Owner's office NAT, or rotating IPv6 addresses | PASS — no account lockout; throttling per address with IPv6 counted per /64; the shared-address delay is a recorded residual and alerts fire (AU-05) |
| 19 | Login probing for unknown accounts by timing or error | PASS — driver-matched dummy hash (AU-04) |
| 20 | A legacy XLSX or DOCX archive item | PASS — accepted only as an attachment-only office file, rejected when it carries macros, ActiveX, OLE, external links, data connections or DDE fields, and never parsed, previewed or rendered (FL-02, FL-07) |
| 21 | An uploaded PDF opened inline, or a rendition framed by another site | PASS — attachment profile with sandbox for uploads; inline profile only for renditions; frame-ancestors on both (FL-07) |
| 22 | A crafted image using MVG, URL or indirect-read coders | PASS — only allowlisted decoders under the library policy (FL-03, H9-12) |
| 23 | The operator issues an Owner-recovery link unasked | PASS with a recorded residual — the Owner must be present and the operator's identity is audited; the alert reaches the Owner outside the operator's sole control once P9 confirms such a channel, otherwise at the Owner's next login, as SECURITY §8 records (AU-14, TM-57) |
| 24 | A distributed login flood with unknown identifiers | PASS — global verification cap and proxy login limit (TM-58, H6-12, H9-02) |
| 25 | A hijacked dependency release | PASS — lockfile-only installs, reviewed diffs including install scripts, SHA-pinned CI actions (SX-05) |
| 26 | Two concurrent submissions of one credential token | PASS — atomic single use (AU-11; mechanism H6-11) |
| 27 | A P6 implementer builds the `security_events` CHECK from DATABASE | PASS — the amended row points to the full LG-02 catalogue (TECH-020) |

**Re-test: 27 of 27 PASS.**

## Independent verification of the fixes

A third fresh read-only reviewer, given the repository, the two review reports and the drafts before the fixes, checked every disposition above, hunted for fixes that widen or narrow approved visibility or authority, re-derived every count and checked identifiers, links and cross-document consistency. Verdict: CRITICAL 0, HIGH 0, MEDIUM 1, LOW 3, LEVEL-1 CORRECTION 12, OWNER_DECISION_REQUIRED 0, DEFERRED OBLIGATION 1, NO DEFECT 51. It confirmed 31 dispositions complete, the rest present with the residuals below, no widening or narrowing in the in-scope lot provenance, the non-itemized movements of other companies, AZ-08, OD-10, the supplier and company-identity projections, the QS-21 and QS-14 rules, the OWNER_ONLY role rule or the credential-event notices, the DATABASE §4.13 amendment narrow and technical, and every count correct. Each finding was verified against the repository and fixed:

| ID | Class | Finding | Disposition |
| --- | --- | --- | --- |
| V-01 | MEDIUM | The case-linked pool-evidence restriction narrowed the DIR-027 evidence path: a resolver holding the assigned company could not see evidence another user had linked | Restriction withdrawn: pool WAREHOUSE_RECORD and OTHER evidence — linked to a case, an import batch or a pool movement — is visible to holders of `stock.view` and `evidence.view` under the content rule, the upload confirmation and the Owner's review of every pool upload, and only a pool OPENING_SIGNOFF follows PJ-20; OD-10(b) evaluates visibility for the resolver whoever linked the evidence (PJ-08, PJ-20, D-PM-12) |
| V-02 | LEVEL-1 | The supplier delivery reference and the receiving-line reference had different audiences in different rows | One audience: the supplier delivery reference to `projects.view`, or to `stock.receive` through the receiving projection, within scope — as on the receiving line, with DOC-12 printing it for `projects.view`; a lot's source reference projected as the referenced record (PJ-02, PJ-03, PJ-18, rows 48, 50, 52) |
| V-03 | LEVEL-1 | §7.1 and D-PM-05 omitted capabilities that open S3 fields | §7.1 S2 and S3 rows and D-PM-05 list `projects.view` for identity assets and supplier delivery references and `stock.receive` for the receiving projection |
| V-04 | LEVEL-1 | PJ-23 called time-ordered public identifiers opaque | PJ-23 covers lot codes; public identifiers are UUIDv7 and reveal only their creation time |
| V-05 | LEVEL-1 | AZ-02's "exactly two places" missed the OWNER-role checks of AU-14, OD-16 and LG-07 | AZ-02 lists them as non-authorizing checks; H8-03 exempts them |
| V-06 | LEVEL-1 | The credential-token store had no owner | AU-11 defines it as an application infrastructure table outside DATABASE §4 with its content; H6-11 hands its keys and indexes to P6; the §7.2 note corrected |
| V-07 | LEVEL-1 | OPENING_SIGNOFF was described as physical content | PJ-08 and H7-11 limit the content rule and the confirmation to WAREHOUSE_RECORD and OTHER; a pool OPENING_SIGNOFF follows PJ-20 |
| V-08 | LEVEL-1 | AU-13's "nothing else about accounts" contradicted PJ-17 and WS-07 | AU-13: nothing else is self-editable; the account's own profile, role, capabilities and scope are shown read-only |
| V-09 | LEVEL-1 | The CSP report endpoint contradicted EN-03 | EN-03 and H8-02 list a staging-only, throttled, size-capped report endpoint |
| V-10 | LEVEL-1 | No event kind recorded use of an Owner-recovery link | LG-02: PASSWORD_RESET_COMPLETED records RESET and OWNER_RECOVERY use; still 19 kinds |
| V-11 | LEVEL-1 | B-17 claimed a CHANGELOG entry not yet written | The entry is written with the continuity freeze before the commit; B-17 row corrected |
| V-12 | LEVEL-1 | The re-test preamble said every fix tightens a rule | Reworded: the fixes that open projections stay inside company grants |
| V-13 | LEVEL-1 | The Owner-visible list omitted behaviour the fixes add | Completed below |
| V-14 | LOW | The pool-evidence residual claimed a correction path that does not exist, and the restriction depended on changeable links | Residual restated (immutable bytes; prevention and Owner detection); the link-dependent restriction withdrawn (V-01) |
| V-15 | LOW | Attachment-only office files could carry active content | FL-02 rejects macros, ActiveX, OLE, external links, data connections and DDE fields; CSV only as an import source; H8-09 cases; residual in SECURITY §8 |
| V-16 | LOW | The recovery alert channel was stated unconditionally | AU-14 and TM-57 conditional on P9 confirming a channel outside the operator's control; the residual stated in SECURITY §8; re-test row 23 |
| V-17 | DEFERRED (P6) | No server-side handoff for the idle rule | H6-06: last activity updated only for user-initiated requests |

A final read-only check of the V-fixes, diffed against the text before them, found CRITICAL 0, HIGH 0, MEDIUM 0, LOW 2, LEVEL-1 CORRECTION 8, OWNER_DECISION_REQUIRED 0, DEFERRED OBLIGATION 0, NO DEFECT 15 — the V-01 rule consistent with DIR-027, SF-UNATTRIBUTED and DATABASE §4.10, every count and identifier correct — and each finding was fixed:

| ID | Class | Finding | Disposition |
| --- | --- | --- | --- |
| F-01 | LOW | LG-07 and H9-06 still stated the operator-independent Owner alert unconditionally | LG-07, H9-06 and AU-14 aligned: the Owner through such a channel once P9 confirms one, otherwise an in-app notice at the next login; the operator always alerted |
| F-02 | LOW | Inspecting office packages contradicted "never parsed", lacked XML hardening and would reject every hyperlink | FL-02: structural inspection only with a hardened XML parser; hyperlinks allowed; H8-09 and re-test row 20 |
| F-03 | LEVEL-1 | `stock.receive` saw receiving lines beyond PJ-18, and DOC-12 was named as a `stock.receive` audience | PJ-18 includes the receiving lines and their supplier delivery reference; PJ-03 names DOC-12 for `projects.view` |
| F-04 | LEVEL-1 | The S3 family list omitted the evidence resolver, and "a movement's own references" named nothing | §7.1 S3 row and D-PM-05 list `stock.resolve_unattributed_evidence` and a movement's itemized fields and company |
| F-05 | LEVEL-1 | Re-test row 3 hid a residual; "pool viewers" was undefined | Row 3 marked PASS with a recorded residual; "holders of `stock.view` and `evidence.view`" in D-PM-12 and SECURITY §8 |
| F-06 | LEVEL-1 | The move of import-batch pool evidence to the pool rule was unrecorded | V-01 records it; PJ-20 names OPENING_SIGNOFF |
| F-07 | LEVEL-1 | The A-01, A-21 and V-02 dispositions no longer matched the aligned audiences | Rows updated |
| F-08 | LEVEL-1 | The Owner-visible list misstated AZ-08's grant | The bearer company's grant, with `stock.allocate_intercompany` when the bearer is not the lot's company |
| F-09 | LEVEL-1 | PJ-02's sentence was ambiguous and row 48 lost its scope qualifier | PJ-02 restructured; row 48 restricts source references to the source company's scope |
| F-10 | LEVEL-1 | AZ-02 said request authorization never reads a role although the resolver runs in every request | "no policy or gate reads a role" |

## Approved-document amendment (TECH-020)

One narrow Level-1 amendment, answering A-18 and B-13: in DATABASE §4.13 the `security_events` row alone gains two clauses marked `TECH-020` — its seven event kinds become the starter entries of the catalogue SECURITY LG-02 completes, and LG-02's further columns are named — plus an amendment note after DATABASE's Approval line. DATABASE keeps its APPROVED status. Pre-amendment SHA-256 `F2FED166C8E6891CFDD6E96AD34C4CD6BFE6BB3415BD41941D4EFC70CC708E9D`, equal to the APPR-005 record and the committed blob. No business meaning changes: DATABASE §4.13 ("P5 owns content and retention") and §23 already delegated security-event content to P5, and without the amendment the seven-value CHECK list (DATABASE §1) would contradict LG-02. DATABASE §4.1 is used as approved and not amended. The amended revision takes effect only with APPR-006.

## Separate Fable review

**Separate Fable review not recommended:** no CRITICAL or HIGH finding arose, the author and both reviewers agree on every isolation finding, and the only approved-document amendment is the narrow technical clarification of DATABASE §4.13.

## Owner-visible Level-1 choices

Each is delegated by DIR-031 §7-G, §7-I or §9, or applies approved authority, and is named here so the approval is informed: a 15-character password minimum with paste and password managers allowed and no composition or rotation rules (AU-03); no remember-me (AU-08); a 60-minute idle and 12-hour absolute session timeout (AU-07); Owner-issued, out-of-band credential links as the DEP-08 low-cost route, with self-service e-mail reset off (AU-12); a notice to the account at its first login after any credential event and to the Owner when a link is used (AU-12, AU-13); the Owner present at an Owner-account recovery (AU-14); a 15-minute password step-up for the highest-risk Owner commands (AU-15); file-type allowlists and size limits, with office files accepted only as attachments (FL-02); the uploader's confirmation that pool evidence shows only shared stock (H7-11); a loss, disposal or count loss on another company's lot routed to QS-20 unless the actor holds the bearer company's grant (with `stock.allocate_intercompany` when the bearer is not the lot's company), as WORKFLOWS §4 and §11 already require (AZ-08); the QS-14 additions of PERMISSIONS_MATRIX section 10; 12-month security-event retention (LG-06). MFA stays the Owner's later choice (AU-16, GAP-033).

## Gap reconciliation

P5 continuation notes are recorded in GAP-003/005/006/008/013/015/022/024/026/030/031; after the review the notes of GAP-006, GAP-008, GAP-030 and GAP-031 were brought in line with the fixes. New: GAP-032 (capability grants apply to every granted company; MEDIUM) and GAP-033 (the Owner account has no second factor in V1; HIGH, MFA adoption reserved to the Owner). None is closed — runtime proof belongs to P6, P8 or P9. Totals: **33 findings — 3 CLOSED, 29 OPEN, 0 OWNER_DECISION_REQUIRED, 1 ACCEPTED_RISK**.

## Deferred obligations (P6/P7/P8/P9)

Recorded in [SECURITY §10](SECURITY.md#10-handoff-obligations); none is implemented now.

- **P6 (H6-01–H6-12):** in-command re-authorization and its place in the lock order; the last-Owner serialization; replay bound to actor and company; job and export context; cache keys; session ending on deactivation and the server-side idle rule; limiter atomicity and failure behaviour; scope-first query plans; duplicate lookups; opaque lot codes; the credential-token store and atomic consumption; a global password-verification cap.
- **P7 (H7-01–H7-14):** generic authentication copy; the relation marker; capability hints; credential pages; expiry warnings; grant screens with the capability-by-company preview; duplicate warnings; denial pages; the step-up; no sensitive data in titles or URLs; the pool-evidence confirmation; history clearing at authentication boundaries; background requests; credential-event notices.
- **P8 (H8-01–H8-15):** the denial and security test obligations, every data path and every W4 row included; P8 creates its own files.
- **P9 (H9-01–H9-12):** database roles, TLS and headers, operator-only commands, session store and token purge, the malware-scanner decision, DEP-08 channels, the recovery runbook, retention, secrets, alert delivery, Argon2id tuning with the driver-change runbook, the image-library policy.

## Static validation

A read-only validation script kept outside the repository, following the prior gates, checked: every relative link and anchor in all Markdown files; lifecycle metadata and approval lines; all 27 source hashes, earlier records unchanged, record 27's size, LF endings and `-text` attribute; every P5 identifier defined once and referenced beyond its definition (ranges expanded); the capability catalogue, its class counts and the class of every W4 mapping; all 124 DATABASE tables classified once, with the classification counts recomputed from the table; the rule-family counts; every cited identifier of the owning documents (BR, CALC, FS, WF, SF, PX, CM, AX, L, QS, C, D-DB, CAP, DOC, DEP, AC, OS, REF, OWN, GAP, DIR, OBS, TECH and APPR) resolving in its owner; the gate's finding, re-test and planner-note rows; gap triage against detail sections and the totals sentences; every OWNER_DECISION_REQUIRED value; placeholder markers; a secret-like scan of every added or new line; `git diff --check` and trailing whitespace; the changed-path census and forbidden P6+ or application paths; the approved P1–P4 specifications and earlier gates unchanged except P4_QUALITY_GATE's addendum and the DATABASE lines marked `TECH-020`; the three governance files outside DIR-031 §5 changing only in their approval pointer line; APPR-006 recording the committed SHA-256 of its three approved files; table column counts and code fences.

**Results (final run, 2026-09-30) — PASS, 51 of 51 checks:** 589 relative links and anchors resolve across 32 Markdown files; metadata valid — PERMISSIONS_MATRIX and SECURITY APPROVED with APPR-006 approval lines, DATABASE APPROVED with its `TECH-020` amendment note, this gate REVIEW; 27 source files match their recorded hashes, no tracked source changed, and source record 27 is 1,142 lines / 41,384 bytes with LF endings and `-text`; 332 P5 identifiers, each defined once and referenced beyond its definition; 80 capabilities (ADM 37, ADM_PLUS 26, OWNER_ONLY 17), all 27 WORKFLOWS §4 rows mapped to their class and every command capability mapped; all 124 DATABASE tables classified once, the classification sentence equal to the recount (101 single, 19 split, 3 following their owner, 1 operational); every rule family contiguous at its stated count; 58 threat scenarios, each with controls and a P8 proof; LG-02 with 19 kinds; 390 distinct cited identifiers resolving in their owners; this gate's A-01–A-26, B-01–B-18, SR-01–SR-13, V-01–V-17, F-01–F-10 and PN-1–PN-11 rows and 27 PASS re-test rows; gap register 33 rows = 33 sections (3 CLOSED, 29 OPEN, 1 ACCEPTED_RISK, 0 OWNER_DECISION_REQUIRED); every OWNER_DECISION_REQUIRED statement 0; no placeholder marker; no secret-like content in 2,385 added or new lines; `git diff --check` clean; 21 changed paths, all within the census, and no P6+ or application file; the approved P1–P4 specifications, earlier gates and ADR-001 unchanged except the P4_QUALITY_GATE addendum and the DATABASE lines marked `TECH-020`, whose pre-amendment SHA-256 equals the committed blob; CLAUDE.md, CHANGE_CONTROL and AGENT_OPERATING_MODEL changed only in their approval pointer line; APPR-006 recording the current normalized SHA-256 of its three approved files; consistent table column counts and code fences.

## Changed-file census

21 paths — 17 modified (`.gitattributes`, AGENTS.md, CHANGELOG.md, CLAUDE.md, README.md, AGENT_OPERATING_MODEL, CHANGE_CONTROL, DECISION_LOG, ENGINEERING_PRINCIPLES, GAP_REGISTER, PROJECT_CHARTER, SOURCE_OF_TRUTH, DATABASE, P4_QUALITY_GATE, CURRENT_STATE, NEXT_ACTION, CONTEXT_INDEX) and 4 new (source record 27, PERMISSIONS_MATRIX, SECURITY and this gate). DATABASE changes only by the `TECH-020` amendment, P4_QUALITY_GATE only by its dated addendum, and CLAUDE.md, CHANGE_CONTROL and AGENT_OPERATING_MODEL only in their approval pointer line, following the APPR-004/005 precedent. The P5 finalization checkpoint commits exactly these 21 paths.

## Gate result and limitations

**P5 planning-quality result: PASS.** Every DIR-031 §7 item is covered and every §8 item is designed or handed to its owning phase; all self-review and independent-review findings are fixed or recorded as deferred obligations; the re-test passes 27 of 27. **APPR-006 conditions verified:** CRITICAL, HIGH, MEDIUM and LOW unresolved = 0; OWNER_DECISION_REQUIRED = 0; no separate Fable review triggered; the adversarial re-test and all validation PASS; no known contradiction remains in the P5 package — the pre-existing stale AGENT_OPERATING_MODEL sentence "The current assignment authorizes P1 only", outside the DIR-031 cleanup scope, is reported under TECH-020 and flagged in CURRENT_STATE; P1–P4 approved business semantics are preserved, the only approved-text change being the narrow `TECH-020` amendment of DATABASE §4.13; P5 remains documentation only; no P6 work exists. Limitations: documentation evidence only — no policy code, application, runtime, penetration, device or restore evidence exists or is claimed; framework behaviour relied on is re-verified at DEP-07; the V1 residual risks are listed in SECURITY §8; deadline feasibility remains unproven (GAP-014).

## Exact next safe action

Wait for the Owner's explicit authorization of P6 — Concurrency, Idempotency & Performance ([NEXT_ACTION](../07-handoff/NEXT_ACTION.md)). At P6 entry, verify that local HEAD equals live origin/main at the P5 checkpoint — resolved by `git log -1 --format='%H %s' --grep='^docs: finalize P5 security and authorization$'`, parent `6e640137901e0d16193e03004e142e9ea07b39ad` — and record its SHA literally. No P6 work before that.

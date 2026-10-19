# Test strategy

Status: APPROVED | Updated: 2026-10-19 | Owner: Planning

Approval: [APPR-012](../00-governance/DECISION_LOG.md#appr-012--p8-testing-quality-and-definition-of-done-approved), explicit Owner approval on 2026-10-19 (DIR-083 OE-15) of this document as committed in run PC-5's approval commit; the approved file hash and the verified conditions are recorded there. Approval changes lifecycle only, not implementation authorization.

Run PC-4 drafted this document on 2026-10-19 under [DIR-082](../00-governance/DECISION_LOG.md#dir-081-dir-082-obs-032-and-tech-039--og-2-decisions-appr-011-run-pc-4-p8-and-p10-drafted-and-p9-completed) SP-85, P8 being authorized by the Owner's [DIR-081](../00-governance/DECISION_LOG.md#dir-081-dir-082-obs-032-and-tech-039--og-2-decisions-appr-011-run-pc-4-p8-and-p10-drafted-and-p9-completed) OB-09; the Owner approved P8 at OG-3 (DIR-083 OE-15). It plans tests; it runs none. No test, fixture, tool or pipeline exists, nothing is installed or assumed verified, and no result is claimed: every check here is **planned**, never reported as run.

**OG-3 decisions** (note of 2026-10-19, written by run PC-5 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-90–SP-93). At OG-3 the Owner decided P8Q-01 (a) and P8Q-02 (a) under [DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-01 and OE-02, applied in §§7, 12, 17 and 18; OE-09–OE-11 add the off-server copy's drill and the numbering raise's tests (§15) and the recovery halt's path ([SECURITY_TEST_MATRIX](SECURITY_TEST_MATRIX.md)). P8's approval is APPR-012, in effect only in PC-5's approval commit after gate PC5-A (OE-15); approving P8 approves its plan, never a result (OE-14).

**Sources.** [AICWDF](../00-governance/AICWDF_ADOPTION.md) §19 and §§25–28 as AICWDF_ADOPTION maps them, with §4B's bilingual proof; the Owner brief's §§40–44 ([source record](../00-governance/sources/OWNER_BRIEF_2026-09-27.txt)), the inputs the [ownership registry](../00-governance/SOURCE_OF_TRUTH.md#canonical-ownership-registry) names for P8; every requirement an approved document hands to P8 — found by a scripted search of P0–P7's normative documents and P9's drafts for "P8", sources and evidence excluded, and mapped in §19 —, chief among them [ACCEPTANCE_CRITERIA](../01-product/ACCEPTANCE_CRITERIA.md), [BUSINESS_RULES](../02-domain/BUSINESS_RULES.md)' numeric annex, [WORKFLOWS §13](../03-workflows/WORKFLOWS/s10-14-signals-decisions-traceability.md#13-downstream-obligations), [ROUTE_CONTRACTS](../03-workflows/ROUTE_CONTRACTS.md) and [INTERACTION_CONTRACTS](../03-workflows/INTERACTION_CONTRACTS.md), [ARCHITECTURE §18](../04-architecture/ARCHITECTURE.md#18-testing-seams-for-p8), [DATABASE §§18 and 31](../04-architecture/DATABASE/s29-31-diagrams-traceability.md#31-downstream-obligations-and-open-technical-items), [CONCURRENCY_IDEMPOTENCY §§18–19](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s19-20-handoff-traceability.md#19-handoff-obligations), [PERFORMANCE](../06-api-performance/PERFORMANCE.md), [ADMIN_FLOW §16](../07-ux-design/ADMIN_FLOW/s16-17-handoff-traceability.md#16-handoff-obligations), [DESIGN_SYSTEM](../07-ux-design/DESIGN_SYSTEM.md), [LOCALIZATION](../07-ux-design/LOCALIZATION.md), [NAVIGATION_CONTRACTS](../07-ux-design/NAVIGATION_CONTRACTS.md), [TOOLCHAIN](../00-governance/TOOLCHAIN.md) and the gaps whose resolution names P8 ([GAP_REGISTER](../00-governance/GAP_REGISTER.md)).

Authority: P8 — how the application is tested: the principles, layers and regression levels, environments and data, the suites each approved requirement receives, the evidence, the tooling's capabilities and the Owner questions of testing. It adds no business rule, capability, control or budget and relaxes none: a test expects what its approved source states. P8's other documents are [SECURITY_TEST_MATRIX](SECURITY_TEST_MATRIX.md) (STM), [PERFORMANCE_TARGETS](PERFORMANCE_TARGETS.md) (PFT) and [DEFINITION_OF_DONE](DEFINITION_OF_DONE.md) (DOD), the definition every P11 Task cites; this document is TST. P10's [RELEASE_PLAN](../10-release/RELEASE_PLAN.md) aggregates modules and releases from them.

**Public repository.** Test data is synthetic, always — the fictional group ARJ, BTN and CKP of [INFRASTRUCTURE §3](../09-operations/INFRASTRUCTURE.md#3-environments) —, and production data never enters a test, a fixture, a pipeline or this repository; no test runs against production or the VPS outside the ephemeral staging stack the operator starts (DIR-079 CB-04).

## 1. Principles

| TS | Principle | Grounds |
| --- | --- | --- |
| TS-01 | **Testing is inside each Task.** A Task's tests are written with it, belong to it and must pass for it to be DONE; no "testing phase" follows the build | AICWDF §19; [DEFINITION_OF_DONE](DEFINITION_OF_DONE.md) |
| TS-02 | **Deterministic assertions establish correctness, never an opinion.** A test asserts an exact value, state, refusal or count; an agent's "looks correct" or "should work" is not evidence, and AI may write and run tests without its judgement becoming proof | brief §§40, 43; AICWDF §26; ENGINEERING_PRINCIPLES; OS-06 |
| TS-03 | **Real PostgreSQL** under every test that touches data, so that CHECK, unique, exclusion and foreign-key guarantees are exercised; never an in-memory substitute | ARCHITECTURE §18 |
| TS-04 | **Synthetic data only.** Fixtures are generated by the test code with fixed seeds; production data, a copy of it or a sample of it never enters a test | PRODUCTION_DATA_SAFETY; INFRASTRUCTURE §3; SP-85 |
| TS-05 | **No layer is the only proof.** The layers of §2 add to each other; no vendor tool, no end-to-end suite and no passing screen replaces the deterministic backend, integration and concurrency tests, and no UI-only pass replaces an acceptance contract | brief §40; ACCEPTANCE_CRITERIA, the coverage and failure gate and the completion obligations |
| TS-06 | **A valid test is never weakened or deleted to make CI pass.** A failing test is fixed in the code; a test found wrong is corrected through review with its reason recorded | brief §44; ENGINEERING_PRINCIPLES |
| TS-07 | **Failure paths are tested as much as success.** Missing data, a failed rendition, a queue retry, a network interruption, a stale edit and an expired session each yield a truthful pending, failed or conflict outcome with a safe recovery path — never a reported success that did not occur, a duplicated effect or a leak | ACCEPTANCE_CRITERIA, the coverage and failure gate |
| TS-08 | **Planned and run are kept apart.** A check is reported as run only with its evidence (§16); a planned check is never written as passed, and a prototype's or a reference's result never stands for the application's | ENGINEERING_PRINCIPLES; DESIGN_SYSTEM Annex A |
| TS-09 | **Every budget, limit and starting value is an assumption until measured.** P8 measures it; a budget the measurement misses is corrected through review — the query, the index or the budget —, never a reason to cut approved scope | PERFORMANCE AS-07; DESIGN_SYSTEM §1; the boundary of CONCURRENCY_IDEMPOTENCY (s00-03) |
| TS-10 | **Verify locally, then again in fresh CI.** One verification command runs the deterministic checks on the agent's machine, and CI runs them again in a fresh environment; a Task is complete only when the required gates pass in both | brief §43; OS-06 |
| TS-11 | **The guards are reconciled after every test that writes.** `ReconcileGuards` reports no difference after each scenario, and a seeded drift is found | ARCHITECTURE §7; CONCURRENCY_IDEMPOTENCY §8; HO-12 |
| TS-12 | **VERIFYING is not DONE.** A Task whose required checks are still running, failing or unrun is not DONE: it stays VERIFYING, or BLOCKED when a check cannot run | AICWDF §19; AGENT_OPERATING_MODEL |

## 2. Test layers

AICWDF §19's sixteen layers, each with what it holds in this project. "Required" is when a Task must run the layer — DEFINITION_OF_DONE states it per Task; tools are capabilities with a preferred tool (§17).

| TL | Layer | What it holds here | Runs | Required |
| --- | --- | --- | --- | --- |
| TL-01 | Lint | PHP style (Laravel Pint) and ESLint over the TypeScript and React code | local, CI | every Task with code |
| TL-02 | Typecheck | Larastan/PHPStan on PHP, the TypeScript compiler on the frontend | local, CI | every Task with code |
| TL-03 | Unit | Pest unit tests: calculators against the numeric annex (FX-01), money and quantity arithmetic, the conversion and split rules; the architecture tests of STM SM-03 | local, CI | every Task with logic |
| TL-04 | Feature | Pest feature tests over HTTP: validation, policies, commands, rule codes and messages, file controllers, the authentication flows of STM | local, CI | every Task with a route or command |
| TL-05 | Integration | Pest with real PostgreSQL, workers and the queue store: the concurrency suite `Concurrency/`, the reconciliation suite `Reconciliation/` (ARCHITECTURE §4), jobs, the renderer and exports | CI; locally where the agent's machine has the stack (§17) | every Task that writes, races or runs a job |
| TL-06 | Authorization | The positive and denial cases of [SECURITY_TEST_MATRIX](SECURITY_TEST_MATRIX.md) | local, CI | every Task with a route, query class or command |
| TL-07 | Route integrity | STM SM-02's route audit, and the route crawl of §8 — every route answering its contract's method and status | CI | every Task that adds or changes a route; all at Layer 3 |
| TL-08 | Interaction | Each INT- contract's *Test* written as its test (§8) | local, CI | every Task with an important control |
| TL-09 | E2E/browser | The critical journeys CJ-01–CJ-22, the UX scenarios UXS-01–UXS-44 and the navigation contracts' paths, in a real browser (§7) | CI; staging at Layer 3 | every Task with a page; its journeys at Layers 2 and 3 |
| TL-10 | Localization and language switch | §10's checks in both languages | CI | every Task with user-facing copy |
| TL-11 | Responsive | Each page at the edges of DESIGN_SYSTEM §6's bands — 320, 390, 767, 768, 1,024, 1,025, 1,200, 1,279, 1,280 and 1,680 CSS px (§9) | CI | every Task with a page |
| TL-12 | Accessibility critical checks | §11's automated and manual checks against WCAG 2.2 AA | CI; manual at Layer 3 | every Task with a page |
| TL-13 | Production build | The frontend production build, the PHP autoloader and caches in production mode, and the production configuration check — debug off, no test route, no development dependency | CI | every Task with code |
| TL-14 | Security | STM's cases and H8-15's CI gates | CI | every Task; all at Layer 3 |
| TL-15 | Performance | [PERFORMANCE_TARGETS](PERFORMANCE_TARGETS.md)' query counts in CI, its plans and times on the scale fixtures and on staging | CI; staging | every Task with a page, query, job or command on a budget |
| TL-16 | Migration | Schema migrations up and the previous release's code against the expanded schema, and the opening import's dry run (§15) | CI; staging | every Task with a migration or import code |

## 3. The three regression layers

AICWDF §28, applied here:

- **Layer 1 — Task verification.** The Task's own tests and the layers DEFINITION_OF_DONE requires of it, with the mandatory static and build gates — TL-01, TL-02, TL-13 and H8-15's gates —, run by the verification command locally and again in CI.
- **Layer 2 — Critical journey regression.** After a meaningful group of Tasks — at the latest when a module's Tasks are DONE, and before every staging rehearsal: the critical journeys of §7 that the group touches, with AICWDF §28's examples as they apply here — login and logout (CJ-10), create, edit and delete (CJ-01, CJ-19), search and filter (CJ-12), approval (CJ-13), permissions (CJ-09, CJ-11 and STM's suites), payments (CJ-04) and reporting (CJ-14); examinations do not apply to this product.
- **Layer 3 — Full release regression.** Before production: every critical journey on the phone and on the desktop; the major roles — the Owner and an Admin of each grant shape of STM §2; every route through TL-07; the core forms; the critical integrations — the renderer, the queue, exports, the opening import, Google sign-in on staging and the breached-password check simulated; every STM case; and PERFORMANCE_TARGETS' release-critical checks. Its results are the inputs of RELEASE_PLAN's release integrity report (AICWDF §39).

## 4. Environments and data

**Environments** are INFRASTRUCTURE §3's: *local*, on the agent's or developer's machine; *CI*, the public repository's pipeline at no cost, with a production-like stack — web, workers, PostgreSQL and the queue store — started fresh for the integration and end-to-end suites; *staging*, the ephemeral stack the operator starts on the VPS before a release, isolated from production — and from the client's second production application on the same VPS, which no test touches (INFRASTRUCTURE INF-07) — and removed after (DIR-079 CB-04), the only place where the VPS's own hardware, TLS and the operator's commands are exercised; *production*, where no test runs. Agents work locally and through CI only; staging is the operator's.

**Fixture sets.** Built by the test code from fixed seeds, versioned with it, and synthetic: names, tax numbers, bank accounts, telephone numbers and addresses are invented, e-mail addresses use the reserved example domains, and no value is copied from a real document.

| FX | Fixture set | Source | Used by |
| --- | --- | --- | --- |
| FX-01 | The numeric annex's sixteen examples, each with its exact expected values | BUSINESS_RULES, numeric annex (GAP-004) | TL-03, TL-05; §5 |
| FX-02 | The synthetic group: ARJ, BTN and CKP with their masters, a shared stock pool and the accounts of STM §2 | INFRASTRUCTURE §3; BACKUP_RECOVERY §6; STM §2 | every suite |
| FX-03 | The eighteen adversarial scenarios of P3's final verification, each with its walk-through and end state | [P3_QUALITY_GATE](../03-workflows/evidence/P3_QUALITY_GATE.md#final-adversarial-verification-dir-026-8) | TL-05; §5 |
| FX-04 | The targeted-review fixtures of P4: price and charge correction between partial receipts, the Rp1 refund-shortfall attack, the −5/+6 location move, replacement versus normal receipt caps, overlapping unattributed-loss cases, replacement invoices after partial payment, residual settlements, alternate-type evidence, corrective receipts, drop-ship reversals, cross-company bank accounts, multi-project revalidation, numbering with retired numbers, guard rebuild after corrections and serial-state reconciliation including drop-shipped serials | DATABASE §31 | TL-05; §5 |
| FX-05 | DIR-027's unattributed-loss cases: a pending case with dispatch continuing, an Owner resolution with and without substitution, an evidence resolution, stock found before and after resolution, an error reversal and a condition-change conversion | DATABASE §31; GAP-030 | TL-05; §5 |
| FX-06 | The scale fixtures 1x, 10x and 100x of AS-04 and AS-05, generated and analyzed | PERFORMANCE §1 | TL-15 |
| FX-07 | Indonesian institution names and fragments for search — "SMAN 1", "UGM", "SD 1" and their like | PERFORMANCE PF-21 | TL-15; CJ-12 |
| FX-08 | The controlled clock: WIB midnight, a month boundary, backdating and the seeded and unseeded periods of HO-19 | CONCURRENCY_IDEMPOTENCY HO-19 | TL-05 |
| FX-09 | Opening-import batches of synthetic legacy data — clean rows, malformed rows, duplicates, rows of an out-of-scope company and legacy numbers, because historical data is never assumed clean | brief §45; DATABASE §24; AC-17 | TL-16; CJ-16 |
| FX-10 | A synthetic sample of each generated document type DOC-01–DOC-14 with representative content, for layout and content review | GAP-019; GAP-014 | CJ-15; §12 |

A fixture that fails to represent its source — an example whose expected value differs from the annex, a scenario whose end state differs from P3's walk-through — is a defect of the fixture, corrected through review; the source's value prevails.

## 5. Domain and data integrity

**Every constraint is a test obligation.** Each row of [DATABASE §18](../04-architecture/DATABASE/s18-19-constraints-integrity.md#18-constraint-catalogue) is tested at its layer — a PostgreSQL guarantee by a violating write under the runtime role, a guard balance by its CHECK, its posting path and its DQ query, an application rule by its refusal —, and each of the 23 rows marked ⚡ by real concurrent sessions as well (§6). No layer is claimed against the table-owning migration role, which DATABASE §28 restricts operationally.

| C | Constraint (DATABASE §18) | Layer | Test |
| --- | --- | --- | --- |
| C-01 | Barcode resolves to at most one active product | DB | the PostgreSQL constraint refuses a violating write under the runtime role |
| C-02 | One serial identity; never in stock twice ⚡ | APP+DB, P6 | the application refuses first; the PostgreSQL backstop refuses a direct violating write under the runtime role; ⚡ real concurrent sessions (§6) |
| C-03 | Non-negative base quantities per lot, scope and product; every pending reduction backed by lot claims of its scope ⚡ | DB+APP+DQ, P6 | the guard CHECK refuses a violating write; the posting path moves the guard with its facts; the DQ query returns no row; ⚡ real concurrent sessions (§6) |
| C-04 | AVAILABLE = ON HAND − RESERVED − UNUSABLE ≥ 0 ⚡ | DB+APP+DQ, P6 | the guard CHECK refuses a violating write; the posting path moves the guard with its facts; the DQ query returns no row; ⚡ real concurrent sessions (§6) |
| C-05 | Reservation bounds: remaining ≥ 0, one ACTIVE per line, per-line conservation ⚡ | DB (reservation row), DB+APP+DQ (line guard), P6 | the guard CHECK refuses a violating write; the posting path moves the guard with its facts; the DQ query returns no row; the PostgreSQL constraint refuses a violating write under the runtime role; ⚡ real concurrent sessions (§6) |
| C-06 | A supply reduction below reservations carries a human cut | APP + DB+APP+DQ | the guard CHECK refuses a violating write; the posting path moves the guard with its facts; the DQ query returns no row; the application rule refuses, with its rule code and message |
| C-07 | Stock increases only through permitted movement types; lot-less entries only on case movements; net-zero movements conserve quantity | DB | the PostgreSQL constraint refuses a violating write under the runtime role |
| C-08 | One lot per acquisition; provenance immutable and keyed to its acquisition | DB | the PostgreSQL constraint refuses a violating write under the runtime role |
| C-09 | Quantity conversion exact; no fractional base quantity of a counted or serialized product anywhere | DB | the PostgreSQL constraint refuses a violating write under the runtime role |
| C-10 | Document, project and purchase numbers (`DIR-077` CA-09) unique per company/type/period, monotonic, append-only and never reused, legacy numbers included; retired project numbers tombstoned ⚡ | DB, P6 | the PostgreSQL constraint refuses a violating write under the runtime role; ⚡ real concurrent sessions (§6) |
| C-11 | Issued content, number and issue data immutable; one issued and one draft version per chain; one chain per record-bound subject; one issue per document per command | DB | the PostgreSQL constraint refuses a violating write under the runtime role |
| C-12 | Render never reissues | DB | the PostgreSQL constraint refuses a violating write under the runtime role |
| C-13 | Σ invoice applications + refund-backed consumption ≤ the source's real money ⚡ | DB+APP+DQ, P6 | the guard CHECK refuses a violating write; the posting path moves the guard with its facts; the DQ query returns no row; ⚡ real concurrent sessions (§6) |
| C-14 | Applications stay within one company and one client | DB | the PostgreSQL constraint refuses a violating write under the runtime role |
| C-15 | Receivable reductions only by applications, fee/tax settlements and net write-offs; outstanding ≥ 0 ⚡ | DB+APP+DQ, P6 | the guard CHECK refuses a violating write; the posting path moves the guard with its facts; the DQ query returns no row; ⚡ real concurrent sessions (§6) |
| C-16 | Void or downward revision disposes every reduction | DB+APP+DQ | the guard CHECK refuses a violating write; the posting path moves the guard with its facts; the DQ query returns no row |
| C-17 | Replacement named explicitly; aging inherited from the replaced invoice's current act | DB | the PostgreSQL constraint refuses a violating write under the runtime role |
| C-18 | Evidence amount caps (settlements, expenses and charges per proof) ⚡ | DB+APP+DQ, P6 | the guard CHECK refuses a violating write; the posting path moves the guard with its facts; the DQ query returns no row; ⚡ real concurrent sessions (§6) |
| C-19 | Σ Cash-In per bank statement line ≤ line; one identity per real bank line; a mistyped line voidable only once uncited ⚡ | DB+APP+DQ, P6 | the guard CHECK refuses a violating write; the posting path moves the guard with its facts; the DQ query returns no row; ⚡ real concurrent sessions (§6) |
| C-20 | Source-bill cap: Σ charges (current amounts) and expenses citing a bill ≤ its total, also after a charge correction ⚡ | DB+APP+DQ, P6 | the guard CHECK refuses a violating write; the posting path moves the guard with its facts; the DQ query returns no row; ⚡ real concurrent sessions (§6) |
| C-21 | Charge conservation: allocated + pending + expensed = charge; any amount allocated or expensed, never both; no rounding overshoot per charge or per line share | DB+APP+DQ | the guard CHECK refuses a violating write; the posting path moves the guard with its facts; the DQ query returns no row |
| C-22 | One active settlement per (payment, invoice, class, type); payment + settlements ≤ claimed gross; collected tax ≤ invoice tax component; a fully contra'd payment keeps no active settlement | DB; DB+APP+DQ | the guard CHECK refuses a violating write; the posting path moves the guard with its facts; the DQ query returns no row; the PostgreSQL constraint refuses a violating write under the runtime role |
| C-23 | A fee is expensed exactly once — at most its evidence — and never by manual entry | DB+APP+DQ + APP | the guard CHECK refuses a violating write; the posting path moves the guard with its facts; the DQ query returns no row; the application rule refuses, with its rule code and message |
| C-24 | Loss recognized once per lost unit ⚡ | APP+DB, P6 | the application refuses first; the PostgreSQL backstop refuses a direct violating write under the runtime role; ⚡ real concurrent sessions (§6) |
| C-25 | HPP attributed once per consumption, drop-ship line and non-stock line | DB | the PostgreSQL constraint refuses a violating write under the runtime role |
| C-26 | Lot cost conservation; the zeroing draw takes the remaining cost | DB+APP+DQ | the guard CHECK refuses a violating write; the posting path moves the guard with its facts; the DQ query returns no row |
| C-27 | Restored, reclassified and substituted ≤ consumed, for warehouse consumptions and drop-ship lines (movement-less drop-ship restorations included); contras on the reversed class and open quantity ⚡ | DB+APP+DQ, P6 | the guard CHECK refuses a violating write; the posting path moves the guard with its facts; the DQ query returns no row; ⚡ real concurrent sessions (§6) |
| C-28 | Cross-company attribution conservation: net allocation = net cross-company consumption; both economic roots keyed to their causes | DB (by construction) + DQ | the PostgreSQL constraint refuses a violating write under the runtime role; the DQ query returns no row |
| C-29 | Normal receipts + closures + drop-ship confirmations ≤ ordered; replacement receipts ≤ replacement expected, separately ⚡ | DB+APP+DQ, P6 | the guard CHECK refuses a violating write; the posting path moves the guard with its facts; the DQ query returns no row; ⚡ real concurrent sessions (§6) |
| C-30 | Delivered ≤ shipped; handed over ≤ demand; reversal lines exact and once ⚡ | DB+APP+DQ, P6 | the guard CHECK refuses a violating write; the posting path moves the guard with its facts; the DQ query returns no row; ⚡ real concurrent sessions (§6) |
| C-31 | Project invoice cap ⚡ | DB+APP+DQ, P6 | the guard CHECK refuses a violating write; the posting path moves the guard with its facts; the DQ query returns no row; ⚡ real concurrent sessions (§6) |
| C-32 | Company-scope references, including document bank destinations and invoice clients | DB + P5 | the PostgreSQL constraint refuses a violating write under the runtime role; its denial cases in the security test matrix |
| C-33 | Stale counts are never applied ⚡ | APP+DB, P6 | the application refuses first; the PostgreSQL backstop refuses a direct violating write under the runtime role; ⚡ real concurrent sessions (§6) |
| C-34 | One open count finding per scope and product | DB | the PostgreSQL constraint refuses a violating write under the runtime role |
| C-35 | Pending unattributed quantity ≥ 0; no double found/resolved/reversed; only loss cases can be found ⚡ | DB+APP+DQ, P6 | the guard CHECK refuses a violating write; the posting path moves the guard with its facts; the DQ query returns no row; ⚡ real concurrent sessions (§6) |
| C-36 | Recognition snapshot immutable and complete; later movements never change it; a recognition fits the claims not already pending | DB | the PostgreSQL constraint refuses a violating write under the runtime role |
| C-37 | Assignment within each company's recognition exposure and, across overlapping cases, within the group bound; evidence resolution closes only identified lots; an attribution error is reversed exactly and once | DB+APP+DQ + APP | the guard CHECK refuses a violating write; the posting path moves the guard with its facts; the DQ query returns no row; the application rule refuses, with its rule code and message |
| C-38 | Force Complete carries reason, snapshots and the confirmed blocker hash | DB | the PostgreSQL constraint refuses a violating write under the runtime role |
| C-39 | Normal completion only with all ten predicates true at commit ⚡ | APP, P6 | the application rule refuses, with its rule code and message; ⚡ real concurrent sessions (§6) |
| C-40 | Owner-only and ADM+ authorities | APP (P5) | the application rule refuses, with its rule code and message; its denial cases in the security test matrix |
| C-41 | Billing once per invoice; one root act and a linear correction chain; a replacement's root act inherits the replaced invoice's current act; opening receivables carry an OPENING act | DB, P6 | the PostgreSQL constraint refuses a violating write under the runtime role; its mechanism's test (§6) |
| C-42 | One uncleared dispute hold per invoice | DB | the PostgreSQL constraint refuses a violating write under the runtime role |
| C-43 | Pre-payment Kuitansi: Σ open ≤ outstanding at issue; one link per (payment, invoice); no receipt-mode Kuitansi while a pre-payment one is open ⚡ | APP, P6 + DB | the PostgreSQL constraint refuses a violating write under the runtime role; the application rule refuses, with its rule code and message; ⚡ real concurrent sessions (§6) |
| C-44 | Refund-backed consumption stays binding while its refund stands; a refund is fully backed at creation; backing is released only by the refund's contra; a source with a refund shortfall keeps no invoice application and no capacity ⚡ | DB+APP+DQ | the guard CHECK refuses a violating write; the posting path moves the guard with its facts; the DQ query returns no row; ⚡ real concurrent sessions (§6) |
| C-45 | A payment contra forces disposition of applications and settlements; Σ contras per money fact ≤ the fact | DB+APP+DQ | the guard CHECK refuses a violating write; the posting path moves the guard with its facts; the DQ query returns no row |
| C-46 | Correction cases close only without residuals; purchase-return closure equation over posted figures; a change to a closed case's figures reopens it | DB+APP+DQ | the guard CHECK refuses a violating write; the posting path moves the guard with its facts; the DQ query returns no row |
| C-47 | Duplicate migration batch and row identity | DB | the PostgreSQL constraint refuses a violating write under the runtime role |
| C-48 | One effect per command, even after the command log expires | DB + P6 | the PostgreSQL constraint refuses a violating write under the runtime role; its mechanism's test (§6) |
| C-49 | Business uniqueness of externally identified events, without blocking their own corrections | DB | the PostgreSQL constraint refuses a violating write under the runtime role |
| C-50 | No committed business_date later than today (WIB) | APP + trigger | the trigger refuses a violating write; the application rule refuses, with its rule code and message |
| C-51 | Channel locked after the first channel-dependent commit | APP + trigger | the trigger refuses a violating write; the application rule refuses, with its rule code and message |
| C-52 | Effective tax configuration never overlaps, globally or per company | DB | the PostgreSQL constraint refuses a violating write under the runtime role |
| C-53 | Append-only facts, decisions, snapshots and audit | DB (application roles) + P9 | the PostgreSQL constraint refuses a violating write under the runtime role; the privilege test on the provisioned database roles (H9-01) |
| C-54 | Deactivated company blocks new business, allows collection | APP | the application rule refuses, with its rule code and message |
| C-55 | Reversals never restore more than was removed | DB+APP+DQ | the guard CHECK refuses a violating write; the posting path moves the guard with its facts; the DQ query returns no row |
| C-56 | A void or downward revision states a typed, existing basis; a billed entry-error void commits only with its replacement, an unbilled one needs none | DB | the PostgreSQL constraint refuses a violating write under the runtime role |
| C-57 | Cumulative posted goods, tax and charge values are net of receipt reversals, confirmation reversals and corrections; after any cost correction Σ attributed economic cost = corrected source cost ⚡ | DB+APP+DQ, P6 | the guard CHECK refuses a violating write; the posting path moves the guard with its facts; the DQ query returns no row; ⚡ real concurrent sessions (§6) |
| C-58 | Evidence type bound to its citing role; pool evidence belongs to no company | DB | the PostgreSQL constraint refuses a violating write under the runtime role |
| C-59 | Every correction path is representable and capped: corrective receipts, drop-ship supplier returns and confirmation reversals, superseding confirmations and closures, claimed-deduction supersession, citation correction, bank-line void | DB | the PostgreSQL constraint refuses a violating write under the runtime role |
| C-60 | Settlement states follow ACTIVE → RESIDUAL, ACTIVE → CONTRAED and RESIDUAL → CONTRAED only, each once; a residual always has an exit | DB | the PostgreSQL constraint refuses a violating write under the runtime role |
| C-61 | Every guard has one formula, its discriminators and one normal writer; drift is reported and repaired only by the audited maintenance rebuild | DQ + APP + P9 | the application rule refuses, with its rule code and message; the DQ query returns no row; the privilege test on the provisioned database roles (H9-01) |
| C-62 | An account's four TOTP fields are written together and cleared together; confirmation requires a secret, and an unconfirmed secret is never stored | DB | the PostgreSQL constraint refuses a violating write under the runtime role |
| C-63 | A Google link names its creator and, once unlinked, its time, reason and actor under the unlink contract; no operator, system identity or placeholder user is ever an actor | DB | the PostgreSQL constraint refuses a violating write under the runtime role |
| C-64 | At most one active Google link per provider subject and per account ⚡ | DB, P6 | the PostgreSQL constraint refuses a violating write under the runtime role; ⚡ real concurrent sessions (§6) |
| C-65 | A TOTP code, and a recovery code, is accepted at most once ⚡ | APP+DB, P6 | the application refuses first; the PostgreSQL backstop refuses a direct violating write under the runtime role; ⚡ real concurrent sessions (§6) |

**The reconciliation suite** (`Reconciliation/`) is driven by DATABASE §3.1: one verification per guard registry row, each recomputing the guard from its facts; it runs after every scenario that writes (TS-11) and on guard-missing and drift fixtures, a deleted guard row of each family included (HO-12; M-29). It never repairs: `GuardMaintenance` is tested separately, as the one audited exception, recomputing the affected rows inside one transaction with an audit event per row (ARCHITECTURE §7; GAP-031).

**The numeric fixtures** (FX-01) are asserted value by value: the AC-10 baseline, the advance, the overpayment, the reallocation, the void after partial payment and SIPLAH's net settlement (1–6; GAP-004); stock, the reservation bound and shrinkage (7–9; GAP-023); the multi-unit split 41,667 and 58,333 and the ratio change (10–11; GAP-025); inter-company attribution (12; GAP-003); the tax settlement (13; GAP-026); charge allocation (14; GAP-027); loss in transit and the client-damaged return (15–16; GAP-028). The P3 scenarios (FX-03) add refunds and reallocation, contras, cross-company returns, the unknown-company loss, serial substitution, erroneous restorations, charges across receipts, remainder closure, duplicate bills, voids with settlements, replacement invoices, tax and fee on one payment, duplicate evidence, recovery after a write-off, a late correction on a cancelled project and the alternate-unit partial return — scenario 18 among GAP-025's proofs.

**Identities and arguments that are proved, not assumed:** the eight identities by which a unit of cost is never counted twice (DATABASE §6.4; GAP-025, GAP-027, GAP-028); the transportation argument by which an overlap group's bounds can never deadlock a resolution, and the evidence resolution that closes only the identified company's lots (DATABASE §5.8; FX-05; GAP-030); conservation under every correction — Σ allocated = charge, allocated or expensed never both, late charges after consumption (GAP-027); no cost lost or counted twice in a loss or a damaged return (GAP-028); no double recognition of a pre-payment Kuitansi (GAP-029; C-43); receipt and dispatch in one unit converted symmetrically, the migration's conversion snapshots kept and the base quantities of counted and serialized products integral (GAP-025; C-09); net allocation equal to net cross-company consumption, a source company's provenance kept and the consuming project's allocated cost projected in full while the source side stays masked (GAP-003; C-08; C-28; PJ-05; PJ-06).

**Workflows and corrections** (WORKFLOWS §13): a scenario family per workflow WF-ACC-01–WF-MIG-01 and per correction row CM-01–CM-38, each asserting the row's expected end state with the numeric annex; a real concurrent test per indivisible action AX-01–AX-37 (WORKFLOWS §9; BR-XD-07); the denial tests of WORKFLOWS §4 through STM's W4 rows; each CM row also under concurrency (GAP-005), a correction committing with all its dispositions or not at all. A project with no purchase and no warehouse work completes on the paths its scope needs, so no purchase or warehouse step is forced on it (GAP-021).

**The capability traceability of DATABASE §30**, each row's P8 entry: the scanner flow (CAP-03; CJ-07), charge conservation and correction fixtures (CAP-05), races and DQ (CAP-06), numbering races (CAP-08), the annex 1–6 and 13 and the refund-shortfall attack (CAP-10), profitability reconciliation (CAP-11; CJ-14), denial and authentication tests (CAP-13; STM) and the completion predicate tests (CAP-18; C-39; GAP-022).

**The brief's required critical tests** (§41), each a deterministic test here:

| Area | Required test | Where |
| --- | --- | --- |
| Authorization | a Company-A Admin cannot view a Company-B project, edit a Company-B invoice or download an unauthorized file; a denied request returns the proper failure | STM SM-01, SM-23 |
| Inventory | stock never negative; concurrent dispatches against stock 1 leave one success; a duplicate dispatch request duplicates no movement; an adjustment needs a reason and an audit event; a reversal keeps the history | C-03, C-04; M-01; M-05 and HO-15's replay of a dispatch command; WF-INV-06's reason and audit event; C-53, C-55 |
| Payment | partial, full and over-payment; a duplicate idempotency key records one payment; an unauthorized payment is refused; a wrong invoice reassignment is audited | FX-01 1–5; M-08–M-12; STM SM-04; CM-21 |
| Numbering | concurrent issues never produce a duplicate number | C-10; M-15–M-19 |
| Documents | an issued invoice is immutable; a revision keeps the previous version; a void keeps the history | C-11, C-12, C-53, C-56; the CM rows of revision and void |
| Validation | negative amounts, invalid quantities, malformed identifiers and a foreign company's key are refused | TL-04; C-09; STM SM-01, SM-03 |
| Files | an invalid type, an oversized upload and unauthorized access are refused | STM SM-09, SM-01 |

**The acceptance contracts.** Every AC is proved where this table says, and P8 expands their failure tests rather than replacing them with a UI pass (ACCEPTANCE_CRITERIA, the coverage and failure gate):

| AC | Capability | Where P8 proves it |
| --- | --- | --- |
| AC-01 | CAP-01 | CJ-19; §5 |
| AC-02 | CAP-02, SH-01 | CJ-19; STM SM-01, SM-12 (TM-13) |
| AC-03 | CAP-03 | CJ-19, CJ-07; C-01, C-02, C-09 |
| AC-04 | CAP-04 | CJ-01 |
| AC-05 | CAP-05 | CJ-02; §5 (FX-04) |
| AC-06 | CAP-06 | CJ-05, CJ-06, CJ-07; §5 |
| AC-07 | CAP-07 | CJ-03; C-30 |
| AC-08 | CAP-08 | CJ-15; C-10–C-12; M-15–M-19; M-31; §12 (GAP-019) |
| AC-09 | CAP-09 | CJ-20; STM SM-04 (W4-24; OD-05; OD-06) |
| AC-10 | CAP-10 | CJ-04; FX-01 1–6 |
| AC-11 | CAP-11 | CJ-14; FX-01; §5 |
| AC-12 | CAP-08, CAP-10, CAP-13 | CJ-06; §5 |
| AC-13 | CAP-01, CAP-13 | STM SM-01–SM-25, SM-25 for the projection within scope; CJ-09, CJ-10 |
| AC-14 | CAP-14, SH-02 | §§9–12; CJ-18 |
| AC-15 | CAP-12 | CJ-08; FX-01 6 |
| AC-16 | CAP-03, CAP-06, CAP-10, CAP-13 | §6 (M-01–M-05, M-08–M-10, M-38); CJ-21 |
| AC-17 | CAP-15 | §15; CJ-16 |
| AC-18 | CAP-16 | §15; BACKUP_RECOVERY §5; RELEASE_PLAN §7 |
| AC-19 | CAP-16 | §15; BACKUP_RECOVERY §§2, 5 |
| AC-20 | CAP-04, CAP-06, CAP-14 | CJ-01 |
| AC-21 | CAP-09, CAP-18 | CJ-17; C-38, C-39; STM SM-04 (W4-09; OD-12); M-26, M-27 |
| AC-22 | CAP-06 | CJ-05, CJ-07; FX-01 7–9 |
| AC-23 | CAP-17 | CJ-12, CJ-14; STM SM-01, SM-11; PFT |

The Owner's final P1 decisions are proved by the same suites: the five financial concepts by FX-01 and CJ-04 (OWN-01); visibility and the denial of manipulated requests by SECURITY_TEST_MATRIX (OWN-02); normal completion, Admin N/A and Force Complete by C-38, C-39, SM-04 and CJ-17 (OWN-03); reservation and usable stock by M-01–M-03 and FX-01 7–9 (OWN-04); the shared pool's provenance and inter-company attribution by FX-01 12, C-08 and C-28 (OWN-05).

**Traceability for P11.** A test is named by the requirement it proves — the AC, CAP, REF, C-, M-, SM-, PFM-, CJ-, UXS-, INT-, NAV- or ROUTE- identifier —, so that each P11 Task's TEST CHECKLIST and the FEATURE_COVERAGE_MATRIX P11 creates map requirement → canonical specification → workflow or rule → Task → acceptance → required test without a hole (GAP-024; AGENT_OPERATING_MODEL; REFERENCE_COVERAGE's P8 row): actual test identifiers, expected assertions, the eight-dimension definition of done and explicit reasons for a non-applicable check, the critical examples included and not only the UI flow.

## 6. Concurrency and failure proofs

Every scenario M-01–M-46 is a test with real PostgreSQL sessions, run as its row states — parallel sessions with a barrier at the named point, each order, each variant —, asserting its expected end state and each participant's outcome; `ReconcileGuards` reports no difference after each (HO-11; HO-12). One further test per ⚡ row of §5's table races its constraint directly. These are the race, replay, failure and boundary tests that GAP-008, GAP-009, GAP-015, GAP-016, GAP-017 and GAP-023 wait for — the last unit among them (OWN-04).

| M | Scenario (CONCURRENCY_IDEMPOTENCY §18) | How it is run |
| --- | --- | --- |
| M-01 | Stock 1; two concurrent dispatches of 1 by different Admins for different projects | two sessions with a barrier before LK-13; both orders; serialized and quantity products |
| M-02 | Stock 1; two concurrent reservations for different projects | as M-01 |
| M-03 | A reservation racing a dispatch for another project | both orders |
| M-04 | A dispatch or receipt racing a count of the same scope | both orders; assert no overwrite |
| M-05 | One serial scanned twice in a command, or dispatched by two commands | duplicate scan; two sessions |
| M-06 | Unattributed-loss recognition or resolution racing a dispatch in the scope | recognition and resolution against a dispatch, both orders |
| M-07 | A cost-correction cascade racing a dispatch of an affected lot | dispatch before step 1, between steps 1 and 2, and after the cascade's locks |
| M-08 | The same payment submitted twice with one `command_id`, in sequence and concurrently | sequential; concurrent with a barrier inside the first transaction |
| M-09 | The same `command_id` with a different payload | alter one field |
| M-10 | The response is lost after commit | drop the response after COMMIT; then let the session expire; a discard command; a commit left in doubt |
| M-11 | Two `command_id`s cite one bank statement line and together exceed it | two sessions |
| M-12 | Two Admins record the same real payment concurrently, without a bank line | two sessions; then the override path; an override that names another match |
| M-13 | A `command_id` reused by another account; a replay by the original actor after revocation or deactivation | each case; compare responses with those for an unknown record |
| M-14 | A payment contra racing an application or refund on the same source | each pair, both orders; the Rp1 contra; an application that resolves a shortfall racing the refund's contra |
| M-15 | N concurrent issues in one company, type and period | N sessions; assert uniqueness and order of commit |
| M-16 | Two first issues of a new period | barrier before the insert |
| M-17 | An issue rolls back | force a failure after allocation |
| M-18 | A backdated issue into a seeded period, and into an unseeded pre-cutover period | both; an unseeded period between two seeded ones; a month inside a seeded year; a type the cutover left without a seed; an issue before the company's seeds exist; a numbering scheme set up without a start; an issue backdated to before a start, refused until its period is seeded; the Owner's seed refused for a period wholly before go-live and for one a batch holds a VALID seed row for; a later import whose cutoff is earlier than the type's seed boundary and falls inside a period, which may not seed that period; a start refused while a batch holds seed rows, and a batch validated while a start commits, whose straddling row is SKIPPED; a scheme set up before a start that differs from the numbering practised outside; once the type has its boundary, a scheme that takes effect before the day it is recorded, refused unless it only covers dates no scheme covered, and a scheme whose first period is not live and has no counter, refused unless the Owner seeds that period with it or the scheme only covers such dates; a scheme added while a batch holds seed rows, refused when it would govern the batch's cutoff period without a seed row; a lost counter under which a number was issued here, answered SQ-18, never seeded again below that number and never moving the boundary; a document dated in a freeze period that lies wholly before go-live and that no batch seeded; a delta that raises a seed without an alert; the first issue of any live period, which starts at 1 |
| M-19 | Two concurrent issues of one draft | different `command_id`s |
| M-20 | A grant or capability is revoked during an in-flight command | both orders; a continuous stream of the target's commands; a long cascade of the target |
| M-21 | A deactivation racing a command, a login and an open session | with an open page, a queued job and a login in flight; reactivate afterwards |
| M-22 | Two concurrent demotions or deactivations of the last two active Owners | the same Owner twice; two Owners against each other |
| M-23 | An export generated before a revocation and delivered after it | revoke one filtered company, then a view capability, then change the role; a revocation during generation |
| M-24 | One credential token consumed concurrently | two submissions |
| M-25 | A distributed login flood against the verification cap | many addresses; a step-up during the flood; the store stopped (fail closed) |
| M-26 | A completion racing a correction that touches the project | both orders |
| M-27 | The Force Complete snapshot changed after confirmation | change one blocker |
| M-28 | Two multi-anchor actions that could deadlock | opposite payload orders; an injected deadlock retried |
| M-29 | A guard update matches no row | delete a guard row of each family in a fixture, born and first-use |
| M-30 | Concurrent first use of one scope, lot, charge share or overlap group | barrier before each insert |
| M-31 | A render job crashes, the sweeper re-queues it and it retries | kill the worker while rendering; two claimants at once |
| M-32 | A worker crash after commit and before dispatch | drop the dispatch of each kind; empty the store |
| M-33 | An import batch is retried | rerun a chunk and a whole batch; refuse a row, fix it, resume; resume as another Owner |
| M-34 | The queue, cache or limiter store is unavailable during a committed action | stop the store at each phase; restart it empty |
| M-35 | Two ORIGINAL receipts of one purchase line and delivery reference; concurrent partial receipts of one line | both cases |
| M-36 | A purchase-remainder closure racing a receipt of the line | both orders |
| M-37 | An invoice void or downward revision racing an application or settlement on it | both orders |
| M-38 | Two Admins edit one mutable draft quotation revision or invoice version | edits of different lines |
| M-39 | One TOTP code submitted twice — in sequence and concurrently, at the challenge and at a step that asks for a code | sequential; two sessions with a barrier before the update; a code of an older step after a newer one |
| M-40 | One recovery code submitted twice — concurrently from two pending challenges of the account, and again later | two challenges with a barrier before the update; a later reuse |
| M-41 | One Google subject linked to two accounts at once | two sessions with a barrier before the insert; both orders |
| M-42 | A Google link racing the account's deactivation | both orders |
| M-43 | A duplicate, stale or replayed Google callback — a double submission, a browser back, a callback after its marker expired, a callback replayed in another session | each case; a callback after a password challenge began in the same session |
| M-44 | Many codes for one account submitted at once from many client IPs, across the challenge and other code-accepting steps, and more during the cooldown | many sessions and addresses with a barrier before the increment; the progression over successive breaches with its cap; the decay of the count and of the escalation level; the limiter store down refusing every covered step; a restart, an eviction and a failover of the store during a cooldown and at each escalation level, the limit kept from the durable floor; the floor unreadable or an event unwritable refusing the step; concurrent submissions immediately after a restart, an eviction or a failover, bounded by the store's seeding from the floor; failed enrolment confirmations taking no place in the account's second-factor count and writing no TWO_FACTOR_FAILED, while still counted by the per-account-and-IP and per-IP throttles; after a cooldown, five more failures needed for the next breach and a correct code after the cooldown evaluated and accepted; a seeded failure leaving the count 15 minutes after it was made and a seeded breach the escalation level 24 hours after it occurred; a duplicate fifth submission carrying nothing into the next window; the progression of 1, 2, 4, 8 and 15 minutes reproduced while the floor is merged at every submission, no breach counted twice; each failure's path in its event and the breach alert naming its path and listing the counted failures by path |
| M-45 | A grant, a role assignment or an activation that makes an account without TOTP high-risk, racing that account's login, its own password change and an optional enrolment confirmation | every order of each pair; a session written by a login verified while the command commits; an activation of an account given its grants while inactive; a step in a Google-established session racing the reissue |
| M-46 | The OWNER-restoration command run twice for one account in the same run, and again after the restored account was removed once more; run for an account that first became Owner during the window, one inactive at the window start, one whose role and flag were never removed, or one whose role was removed and given back during the window; run with a window start other than the run's recorded one, before the incident command completes, or after the run's recovery has ended; racing a role change of the account; and the Owner-recovery command's guard (SECURITY AU-14) for a pre-window Owner whose role was removed and reassigned during the window, a reactivated former Owner, a named confirmer that was not a pre-window Owner and one removed or deactivated since, and an Owner-recovery issue for a deactivated pre-window Owner before its restoration; the end of the run's recovery recorded while an active window Owner has no issue naming a confirmer; and a restoration of an account touched since the incident command, or whose role a recovered Owner removed after the window | each case; two runs with a barrier before LK-02; a run while the incident command still runs; the role row's values checked against the before-image of the window's first change of the row; the confirmer's row locked with the account's in id order |

**The other proof obligations of CONCURRENCY_IDEMPOTENCY §19 for P8:**

| HO | What is proved | Suite |
| --- | --- | --- |
| HO-13 | Lock order measured from the locks actually held — implicit locks of updates and foreign keys included — in ascending canonical order; the plan of every multi-row lock statement sorts below its row-locking node, and the single-row form is used wherever that cannot be shown (LR-05) | `Concurrency/` |
| HO-14 | The database behaviours the design relies on beyond the manual, each as its row lists them — the wait of `ON CONFLICT` behind a concurrent inserter, foreign-key checks under FOR NO KEY UPDATE, the savepoint release, `lock_timeout` inside the identity insert, the session-level keys of evidence registration (FU-10), `SET CONSTRAINTS ALL IMMEDIATE`, each timeout's SQLSTATE, the driver's constraint name and the overlap group's reconciliation | `Concurrency/` |
| HO-15 | The replay table of CI-05 case by case; a lost response followed by session expiry; retention expiry (SQ-07); deterministic identities, a refused and re-driven one included (CI-08); the OD-16 reading of ST-03 and SQ-23 events (STM SM-05) | `Concurrency/` |
| HO-17 | Limiter exactness and store-down behaviour (STM SM-14) | `Concurrency/` |
| HO-18 | Job crash, retry, poison, lost-dispatch and payload-version tests for JB-01–JB-09 as its row lists them — a rendition claimed after a long outage, a claim while the store lost its locks, an export behind another, a worker killed holding the overlap lock, a request whose runs keep dying ending after three claims, a dispatch lost after a resume | TL-05 |
| HO-19 | Numbering and dates with the controlled clock (FX-08), every case of its row — WIB midnight and a month boundary, backdating, seeded and unseeded periods, schemes and granularity changes, imports moving the boundary, a lost counter, a stale prefilled date (ST-08) | TL-05 |
| HO-20 | The framework behaviours recorded as source-only or undocumented — the concurrency-error detector, savepoint nesting, the raw lock string, batched jobs and transactions, cache locks in a transaction, session deletion by account, the session write skipped for a background request, the rehash at login (RV-10), reconnection between the registration keys and the transaction (FU-10), the scheduler with its store down | TL-05 |
| HO-41 | RV-11–RV-18 with real sessions (STM SM-17 and SM-21) | `Concurrency/` |

The behaviours read only in source are re-read in the versions chosen before they are relied on, and their tests stay so that a changed version fails a test (GAP-043; STM SM-22). **Failure cases that are not races** have their own tests: a crash after commit and before dispatch, and a rendition retried after a worker crash, never reissuing a number (GAP-007; M-31, M-32; JB-01, JB-02); a queue, cache or limiter store lost during a committed action, the action staying committed and its owed work re-dispatched from durable rows (M-34; JB-09); a lost response retried as the same intent (GAP-008; M-10). D-CC-04's project-row lock is kept unless P8 measures contention ([PERFORMANCE_TARGETS](PERFORMANCE_TARGETS.md) PFM-14).

## 7. Browser end-to-end tests and critical journeys

Critical UI journeys run in a real browser driven by automation — Playwright, on the Chromium and WebKit engines, the supported browsers of §12 joining on real devices at Layer 3 (DIR-083 OE-01, OE-02) —, on the CI stack and, at Layer 3, on staging (AICWDF §26). A journey passes only with its evidence in the form `E2E: PASS — Evidence: <run, commit, browser and width, trace or screenshots, network and console capture>`; "should work" is refused. Each journey also captures the browser console and network: a critical console error or a critical failed request fails it (AICWDF §25).

| CJ | Journey | What it covers | Devices |
| --- | --- | --- | --- |
| CJ-01 | **The golden business flow** — login → create project → add or select client → add items → quotation → approval → purchase → receive *or* drop-ship → deliver → generate invoice → mark billed → record payment → complete project | brief §42; AC-20; both branches, and a project served from stock with no purchase (GAP-021) | desktop; the phone for its [both] steps |
| CJ-02 | Purchase with and without a PO; several suppliers for one project | brief §42; AC-05 | desktop |
| CJ-03 | Partial delivery and service handover | brief §42; AC-07 | desktop, phone |
| CJ-04 | Invoices, billing, partial and full payment, DP and termin, overpayment, credit and refund, a payment duplicate, the pre-payment Kuitansi | brief §42; AC-10; FX-01 1–5 | desktop, phone |
| CJ-05 | Stock opname, condition change, adjustment, disposal and loss, a pending unattributed loss | brief §42; AC-06; AC-22 | phone (floor), desktop |
| CJ-06 | Correction and void: reversals, returns, invoice revision and void, backdating | brief §42; AC-12 | desktop |
| CJ-07 | The barcode flow: receiving and dispatch with the scanner, a duplicate scan, an unknown code, manual lookup | brief §42; AC-03; GAP-015 | phone and desktop with the real scanners at Layer 3 (§12) |
| CJ-08 | A SIPLAH-channel project with its references and net settlement | brief §42; AC-15; FX-01 6 | desktop |
| CJ-09 | Company isolation as seen on the pages: the Company-A-only Admin's pooled view, a revoked grant on an open page, the Owner review queue's address | brief §42; AC-13; STM SM-23 | desktop, phone |
| CJ-10 | Sign-in with a password and with Google, the second-factor challenge, enrolment, the in-place re-login, logout, expiry and remote termination | AICWDF §28; AC-13; GAP-044 | desktop, phone |
| CJ-11 | Users, roles, capabilities and company grants by the Owner | AC-13; GAP-032 | desktop |
| CJ-12 | Search and filter: products, SKU, barcode, projects, clients and documents; a two-character term; more than 200 matches | AICWDF §28; AC-23; FX-07 | desktop, phone |
| CJ-13 | Approvals and Owner decisions: quotation approval, commercial confirmation, the Owner review queue, a high-risk Owner command with step-up | AICWDF §28 | desktop |
| CJ-14 | Reporting and exports: receivables and ageing, the reports with company and date filters, an export, its row bound and a third concurrent export | AICWDF §28; AC-11; AC-23 | desktop |
| CJ-15 | The generated document lifecycle: a draft prepared from the recorded facts and opened on PG-036 with the form beside the live preview on the desktop and Edit → Preview → Finalize on the phone, issue with confirmation, the rendition, a failed rendition | AC-08; DESIGN_SYSTEM §14.3; `DIR-081` OB-02 | desktop at the widths of §9, phone |
| CJ-16 | The opening import: prepare, dry run, quarantine, joint sign-off, commit, numbering and go-live (staging only, FX-09) | AC-17; UXH-10 | desktop |
| CJ-17 | Project completion with its predicates, Force Complete by the Owner, cancellation | AC-21 | desktop |
| CJ-18 | The language switch on a page with unsent input; the language kept across a re-login | AC-14; LOCALIZATION §3.7 | desktop, phone |
| CJ-19 | Master data: the company master, client organization, unit and PIC, suppliers, the catalogue with units, barcodes and serial policy, locations and minimum stock; numbering schemes | AC-01–AC-03 | desktop |
| CJ-20 | The administrative checklist and external documents: evidence uploads, an in-scope duplicate warning | AC-09 | desktop, phone |
| CJ-21 | Command identity and session behaviour: a double click, a lost answer and its retry, 401 and 419 with in-place re-authentication, a lock-wait failure, an outcome unknown, two Admins on one draft, "already recorded", a prefilled date crossing midnight | ADMIN_FLOW §§5–6; UXH-02; AC-16 | desktop, phone |
| CJ-22 | Status and quality scenarios: status in greyscale and for colour-blind users, the anti-slop audit of the dashboard | ADMIN_FLOW §15; UXH-07 | desktop |

**Every journey of ADMIN_FLOW** runs end to end in one of them:

| Journey (ADMIN_FLOW §10) | Title | Run in |
| --- | --- | --- |
| J-ACC-01 | Session and company context `[both]` | CJ-10 |
| J-ACC-02 | Users, roles, capabilities and company grants `[desk]` — OWN! | CJ-11 |
| J-MD-01 | Company master `[desk]` — OWN! | CJ-19 |
| J-MD-02 | Client organization, unit and PIC `[both]` | CJ-19 |
| J-MD-03 | Supplier master `[desk]` | CJ-19 |
| J-MD-04 | Catalog, units, barcodes, serial policy and price defaults `[desk]` | CJ-19 |
| J-MD-05 | Locations and minimum stock `[desk]` | CJ-19 |
| J-PRJ-01 | Project setup and amendment `[both]` | CJ-01 |
| J-QUO-01 | Quotation cycle `[desk]` | CJ-01 |
| J-PRJ-02 | Commercial confirmation `[desk]` | CJ-01 |
| J-FUL-01 | Fulfilment planning per demand line `[desk]` | CJ-01 |
| J-INV-02 | Reservation management `[desk]` | CJ-01 |
| J-PUR-01 | Project purchase `[desk]` | CJ-02 |
| J-PUR-02 | Replenishment purchase `[desk]` | CJ-02 |
| J-INV-01 | Receiving — *Barang Masuk* `[floor]` | CJ-07 |
| J-INV-03 | Warehouse dispatch — *Barang Keluar* `[floor]` | CJ-07 |
| J-INV-07 | Inter-company allocation (embedded in dispatch) | CJ-09 |
| J-INV-04 | Condition change and quarantine `[floor]` | CJ-05 |
| J-INV-05 | Stock opname `[floor]` | CJ-05 |
| J-INV-06 | Adjustment, disposal and loss `[floor]` | CJ-05 |
| J-PUR-03 | Purchase return `[floor]` | CJ-06 |
| J-FUL-02 | Delivery — *Pengiriman* `[both]` | CJ-03 |
| J-FUL-03 | Drop-ship — *Kirim Langsung* `[desk]` | CJ-01 |
| J-FUL-04 | Service handover — *Serah Terima Jasa* `[both]` | CJ-03 |
| J-FUL-05 | Sales return `[both]` | CJ-06 |
| J-DOC-01 | Generated document lifecycle `[desk]` | CJ-15 |
| J-DOC-02 | External and uploaded documents `[both]` | CJ-20 |
| J-ADM-01 | Administrative checklist — *Dokumen Administrasi* `[desk]` | CJ-20 |
| J-FIN-01 | Invoice issuance `[desk]` | CJ-04 |
| J-FIN-02 | Billing and receivable monitoring — *Piutang* `[desk]` | CJ-14 |
| J-FIN-03 | Payment receipt and application — *Pembayaran* `[both]` | CJ-04 |
| J-FIN-04 | Deduction settlement `[desk]` | CJ-08 |
| J-FIN-05 | Dispute hold and Owner write-off `[desk]` | CJ-04 |
| J-FIN-06 | Customer credit and refund `[desk]` | CJ-04 |
| J-FIN-07 | Expenses, disbursements and other cash `[desk]` | CJ-04 |
| J-FIN-08 | Pre-payment Kuitansi `[desk]` | CJ-04 |
| J-PRJ-03 | Project completion `[desk]` | CJ-17 |
| J-PRJ-04 | Project cancellation `[desk]` | CJ-17 |
| J-MIG-01 | Opening facts `[desk]` | CJ-16 |

**Every UX scenario** of ADMIN_FLOW §15 is an end-to-end scenario with its screen, copy and expected presentation (UXH-01):

| UXS | Scenario (ADMIN_FLOW §15) | Run in |
| --- | --- | --- |
| UXS-01 | A double click on a command on a slow network | CJ-21 |
| UXS-02 | The answer is lost after the commit; the user retries | CJ-21 |
| UXS-03 | The answer is lost, then the session idle-expires; in-place re-authentication | CJ-21 |
| UXS-04 | The session expired while a form was open and never sent | CJ-21 |
| UXS-05 | 419 on submit | CJ-21 |
| UXS-06 | Absolute timeout, remote termination or logout | CJ-10 |
| UXS-07 | FAILED after a lock-wait timeout | CJ-21 |
| UXS-08 | FAILED with the outcome unknown | CJ-21 |
| UXS-09 | A rendition fails in the background | CJ-15 |
| UXS-10 | REJECTED with a failed precondition, for example C-04 | CJ-07 |
| UXS-11 | Two Admins edit one draft quotation or invoice | CJ-21 |
| UXS-12 | CONFLICT "already recorded" | CJ-21 |
| UXS-13 | A prefilled business date crosses midnight | CJ-21 |
| UXS-14 | A backdated command with a chosen date | CJ-06 |
| UXS-15 | A likely duplicate of a fungible event | CJ-07 |
| UXS-16 | A payment duplicate | CJ-04 |
| UXS-17 | An evidence duplicate in scope and in a visible family | CJ-20 |
| UXS-18 | An evidence duplicate out of scope or in an invisible family | CJ-09 |
| UXS-19 | A batch collision outside the preparer's scope | CJ-16 |
| UXS-20 | A Company-A-only Admin views a pooled lot of Company B | CJ-09 |
| UXS-21 | An Admin with companies A and B | CJ-09 |
| UXS-22 | An action on another company's record without a grant | CJ-09 |
| UXS-23 | A grant is revoked while a page is open | CJ-09 |
| UXS-24 | A deactivated user retries | CJ-10 |
| UXS-25 | An Admin opens the Owner review queue address | CJ-09 |
| UXS-26 | A high-risk Owner command | CJ-13 |
| UXS-27 | The Owner grants capabilities | CJ-11 |
| UXS-28 | A company created after go-live; an Admin creates a project | CJ-19 |
| UXS-29 | An Admin backdates an invoice into a period that is not live and has no counter | CJ-06 |
| UXS-30 | The Owner adds a scheme effective in the past | CJ-19 |
| UXS-31 | A start while a batch holds valid seed rows | CJ-16 |
| UXS-32 | Go-live is not recorded | CJ-16 |
| UXS-33 | A two-character term; more than 200 matches | CJ-12 |
| UXS-34 | A scan matching two serial rows; no scanner attached | CJ-07 |
| UXS-35 | *Barang Masuk* of a multi-line purchase on a phone with a scanner; thirty lines by keyboard on a desktop | CJ-07 |
| UXS-36 | An export above the row bound; a third concurrent export | CJ-14 |
| UXS-37 | Force Complete | CJ-17 |
| UXS-38 | A pre-payment Kuitansi | CJ-04 |
| UXS-39 | A pending unattributed loss | CJ-05 |
| UXS-40 | The Owner review queue | CJ-13 |
| UXS-41 | A keyboard-only desktop quotation with many lines | CJ-01 |
| UXS-42 | Status in greyscale and for colour-blind users | CJ-22 |
| UXS-43 | An anti-slop audit of the dashboard | CJ-22 |
| UXS-44 | Project detail within the performance rules | CJ-01 |

**Command identity and re-authentication** (UXH-02) — beyond its security part in STM SM-13: one `command_id` per intent across a double click, a lost answer, a retry, 401 and 419; the unsent input surviving in memory after the intercepted answer, after a transport failure and after a step-up, proved on the chosen versions because the documentation implies it without stating it, with the fallback of ADMIN_FLOW §6 — the full document load after a warning, never browser storage — if it fails; the background marker on deferred-group and prefetch requests, which never extend the session (IP-11); the application's own guard for in-app navigation with unsent input and the browser's leave prompt; two accounts with the same display name, capabilities and companies at the re-authentication dialog, the second seeing only its own projection.

**The deterministic suite and the browser suite.** Pest asserts every rule, state and refusal; the browser suite proves the journeys through the pages. Beside them TestSprite runs the UI and E2E regression in the role [P8Q-01](#p8q-01--the-browser-e2e-capability-and-testsprites-role) settles — decided by DIR-083 OE-01 (§17) —, within ENGINEERING_PRINCIPLES' rule that TestSprite is not the sole layer (UXH-01).

## 8. Route, navigation and interaction integrity

AICWDF §25 makes a dead interaction a release blocker. The contracts are counted by script: **210 routes** in ROUTE_CONTRACTS, each with its declaration (STM SM-02), **165 interaction contracts** in INTERACTION_CONTRACTS and **65 navigation contracts** in NAVIGATION_CONTRACTS.

- **Routes** — STM SM-02's audit, and a route crawl that requests every route as an actor allowed to use it and asserts its contract's method, status and answer: a command route refuses GET, a page answers 200, a file route streams with its header profile, and nothing answers an unexpected 404, 405 or 500. A route added without a contract, or a contract without its route, fails (GAP-035).
- **Interactions** — each INT- contract's *Test* is written as its test (INTERACTION_CONTRACTS): the control is visible exactly to the actors its contract names, does what its contract says, shows its success and error results and validation messages, and never submits without feedback.
- **Navigation** — each NAV- contract's back, cancel, success and error paths are tested with its page (NAVIGATION_CONTRACTS), the list state preserved where the contract says (AICWDF §18.6).

| AICWDF §25 blocker | The test that catches it |
| --- | --- |
| visible button does nothing; visible action with an unusable backend path | its INT- test and its CJ step; STM's positive twin |
| broken link; missing route; wrong HTTP method; wrong handler | the route crawl; SM-02; the INT- test |
| unexpected 404, 405 or 500 | the route crawl; every journey's network capture |
| authorization mismatch | SM-02; SM-04 |
| form submit without feedback; wrong success redirect; missing or unclear back or cancel | the INT- test; the NAV- paths |
| critical browser console errors; critical failed network requests | every journey's console and network capture |

**Targets**, each counted by the suites and reported in the release integrity report: orphan UI actions 0; broken routes 0; dead buttons 0; unhandled critical actions 0; unexpected 404 0; unexpected 405 0; unexpected 500 0; critical console errors 0; critical failed network requests 0.

## 9. The UI/UX consistency gate

Every UI Task answers AICWDF §27's twenty-one items on the built screens, never on the prototype or a reference render (DESIGN_SYSTEM Annex A): each item **PASS**, **FAIL** or a count, the optional states **N/A** only with their reason, and nothing recorded as passed that was not judged. A measured item is checked by the browser suite; a reviewed item is judged by the reviewer against the named rule and confirmed by the Owner at UAT.

| §27 item | How it is judged | Rule |
| --- | --- | --- |
| Modern visual quality | reviewed on the built screens, with the anti-slop review repeated on them (UXH-07; UXS-43) | DESIGN_SYSTEM §§3–4, §18 |
| Design system consistency | measured: computed tokens — colour, type, spacing, radius, elevation, motion — equal §5's values; components and patterns as §§9–10 compose them; the reference's recipes where DA-01 and DA-02 name them. A difference is a finding; an accepted and documented difference of Annex A stays one, never a pass (CA-05; CD-05) | DESIGN_SYSTEM §§5, 9, 10; Annex A |
| Primary action clear | one primary action per page or region, present and enabled when its INT- contract says | DESIGN_SYSTEM §9.4; INT- |
| Unnecessary duplicate actions: 0 | counted per page against its INT- contracts | INTERACTION_CONTRACTS |
| Back action clear; navigation contract valid | the NAV- contract's back, cancel, success and error paths (§8) | NAVIGATION_CONTRACTS |
| Indonesian default; plain Indonesian; English available; missing translations: 0; language switch | §10 | LOCALIZATION |
| Mobile-first; responsive; adaptive | the phone composition as the default; TL-11 at every band edge; a coarse pointer's 44 px targets and the floor task's 48 px, 16 px text fields on touch | DESIGN_SYSTEM §6; D-DS-19 |
| Loading, empty and error states | each state of the page as §12.3 lists it, deferred groups included; an empty search saying so (FA-22); outcomes as §12.2 presents them | DESIGN_SYSTEM §12 |
| Destructive action safety | IP-08's confirmation before every irreversible command, tested with its INT- contract | ADMIN_FLOW IP-08 |
| Accessibility critical checks | §11 | DESIGN_SYSTEM §15 |
| Visual breathing room; content density appropriate | measured spacing tokens; the dense mode only on OC-09's named screens, every other screen in the comfortable density, reviewed against AICWDF §18.2 | DESIGN_SYSTEM §§5.3, 6 |

**Starting values.** A value DESIGN_SYSTEM gives as a starting value may be tuned by measurement without changing its rule (DESIGN_SYSTEM §1); the tuning is a Level-1 change recorded with its evidence.

**Mandated copy never cut** (D-DS-18; OB-08): a select's value, a form bar's reason, a read-only value and a label wrap under WCAG 2.2 1.4.12's text spacing instead of an ellipsis — tested at 768 px, where the prototype cut the save's reason, and at every band edge.

**The document composer and preview** (DESIGN_SYSTEM §14.3; D-DS-20; D-DS-23; UF-01; `DIR-081` OB-02; GR-10). On every page that creates or edits a generated business document — PG-014, PG-033, PG-036, PG-042 and PG-043, and PG-036 for the drafts that the issuing controls of PG-016, PG-022, PG-024, PG-031 and PG-043 prepare — the tests prove that a draft preview never satisfies a requirement — no completion predicate, checklist item or document requirement counts a draft (GAP-053) —; that it never writes; that it matches the issued layout within P8's tolerance; that its totals are labelled provisional and then equal the server's; that only projected fields reach the browser; that the paper stays Indonesian in both interface languages; the phone's Edit → Preview → Finalize at 390 px, DOC-12's floor step included; the desktop's form beside the live preview at 1,025, 1,279 and 1,280 px and the tablet's toggle at 1,024 px (INT-012; CD-04). **The tolerance:** the same template, paper size, orientation, margins and page count as the issued rendition of the same content; the same blocks in the same order with the same values, except the draft marking "DRAF — belum terbit", the number line, which an issue fills, and the draft's text signature block, which stands where the issued rendition has its stamp and signature — the renderer alone uses those files (DESIGN_SYSTEM §14.3; PJ-19) —; each block's position on the page within 1 mm.

**The screens the Owner's business decisions changed** (DIR-079 CD-06), never shown in the prototype, are verified on the application:

| Decision | What changed | Page | Verified by |
| --- | --- | --- | --- |
| CA-06 | each project row's journey stage, blocker marker and next action | PG-010 *Proyek* | CJ-01; NAV-010, ROUTE-010; PFT PFM-04 |
| CA-07 | the dispatch-ready lines (QS-24) among the Admin's goods work | PG-007 *Pekerjaan*; PG-024 *Keluarkan Barang*; PG-023 *Barang Keluar* | CJ-07; PFT PFM-07 |
| CA-08 | the display language kept in `users.locale` | PG-065 *Akun Saya*; the account menu | CJ-18 |
| CA-09 | every purchase's business number at its save | PG-017 *Buat Pembelian*; PG-016 *Detail Pembelian* | CJ-02; C-10 |
| CA-10 | the Owner-only overdue projects (QS-23) in the decision group | PG-006 *Beranda* | CJ-13; STM SM-04 (OD-14) and SM-25 for its Owner-only audience |
| CB-01 | an imported purchase's number | PG-058 *Impor Data Awal* | CJ-16; §15 |
| CB-02 | the PURCHASE sequence's scheme and seed | PG-061 *Penomoran Dokumen* | CJ-16, CJ-19; HO-19 |
| CB-03 | the receivables KPI and the ageing chart within QB-10 | PG-006 *Beranda* | CJ-14; PFT PFM-05 |

## 10. Localization

[LOCALIZATION](../07-ux-design/LOCALIZATION.md)'s checks, in both languages, are part of every UI Task's definition of done (LOCALIZATION §3.6; GAP-037):

- **No missing string.** Every key a page uses exists in both string files with its named placeholders; a missing key, a hard-coded user-facing string or a placeholder left unfilled fails the page. The inventory holds **889 keys**, of which **881** are validated and 8 are data or never translated (LOCALIZATION §6–§7); the check reads the keys from the inventory, not from a sample.
- **The layout after a language change.** Every screen is checked in both languages, because a string's length differs between them (§3.7; AICWDF §27); the switch keeps the page, its data and its unsent input (WFL-04), and the language is kept in `users.locale` across a re-login (CA-08; CJ-18).
- **What stays Indonesian.** Generated and issued business documents, business data and the paper of a preview stay Indonesian whatever the interface language (DIR-037 D7; AC-14); dates, numbers and money keep their formats in both languages (OQ-04 (a)); a counted string shows its two English forms (§3.3).
- **Indonesian by default.** A new account, and one without a stored language, sees Indonesian.
- **Copy written after APPR-011** (`DIR-081` OB-04; LOCALIZATION §5): each page's P8 review checks every key its Task added — accepted by construction when its Indonesian is a GL term or a label of an approved source and its English that term's or label's validated rendering; any other new key marked proposed and listed for the Owner with the page's review, accepted except the keys the Owner marks; a marked key corrected before its release. The Task template's list of new copy is P11's.

## 11. Accessibility

WCAG 2.2 at level AA, audited on the built screens against DESIGN_SYSTEM §15 with measured contrast (UXH-05; AC-14):

- **Automated** — an accessibility engine runs inside the browser suite on every page state the journeys reach, at the phone and desktop widths, and fails the run on a violation of an AA criterion it can decide.
- **Measured** — rendered contrast of text, large text and control boundaries; focus visible and never obscured by a sticky region (2.4.11); targets of 24, 44 and 48 px by pointer (D-DS-19); reflow at 320 CSS px without horizontal page scrolling (1.4.10); text spacing (1.4.12, D-DS-18); reduced motion (D-DS-17).
- **Manual, at Layer 3** — keyboard-only use of every critical journey, a keyboard-only desktop quotation with many lines included (UXS-41); a screen reader on the critical journeys on the desktop and on a phone; status by more than colour, in greyscale and for colour-blind users (UXS-42); the idle-session warning (2.2.1); status messages announced as §15 states (4.1.3); dialogs' focus and return.
- **The critical checks** of AICWDF §27, required of every UI Task: keyboard operability without a trap, visible and unobscured focus, contrast, names and labels, errors identified and announced, target size, reflow, text spacing and status not by colour alone. The full audit of every AA criterion runs at Layer 3.

## 12. Devices and UAT

**Real-device checks**, on the application and on real devices, never emulated (GAP-020; UXH-06; DEP-03):

- **The phone** — S2-J01's sign-in, S2-J03's work home and project journey at 390 CSS px and S2-J05's receiving at 390 and 320 CSS px (DIR-077 CA-02 item (2)), with the journeys of every C2 phone task — UT-06 (S2-J02 step 3), UT-12 (S2-J03 step 11) and UT-18–UT-24 (S2-J05 and S2-J01's account menu) — at 390 CSS px (DIR-079 CD-03); each record names the device, the browser and the CSS width.
- **The desktop composer** — the document composer on the application on the desktop, form beside live preview, at the widths of §9 (DIR-079 CD-04).
- **Scanners and printing** — the actual USB scanners on a desktop and the phone: wedge speed, the Enter suffix, the scan field's focus, Bluetooth pairing; the phone's camera application through the file picker; label and document printing by the method the office already has, no new printer assumed (DEP-03); camera barcode scanning stays outside V1 (D-UX-09).
- **Files** — the formats the users really hold, against FL-02's allowlist (DEP-04; DEP-05; STM SM-09).
- **Time on the device** — rendering and network time measured on the devices, beside the server budgets (AS-06; PFT PFM-17); office connectivity and workload as the operator observes them (DEP-09).

**The supported browsers and devices** ([P8Q-02](#p8q-02--the-supported-browsers-and-devices), decided by DIR-083 OE-02): the current stable Google Chrome and Microsoft Edge on the office PCs, Chrome on Android phones and Safari on iPhones; CI runs the Chromium and WebKit engines, the real devices joining at Layer 3; the device UAT records what the users actually hold and corrects the list with that evidence. They are the supported browsers of SECURITY WS-04 and H8-20.

**UAT** with the Owner and the Admin Operasional validators, named at C2 (GAP-014), on staging with synthetic data:

- the copy of ADMIN_FLOW's message catalogue and of the glossary, and the presentation of the payment duplicate window (UXH-08);
- the seven-day window of the payment duplicate check against the Owner's finance practice (HO-35; BD-02);
- each generated document's content and layout, DOC-01–DOC-14, with FX-10 (GAP-019; UXH-08);
- each high-risk account holder's phone with an authenticator app (DEP-11);
- representative daily work, completed with data entered once and reused through the flow, with correct outputs (OS-01) and the phone and desktop tasks of AC-14;
- the per-page lists of new copy (§10).

A UAT record names the validator, the date, the build, the tasks, each result and its disposition, with an issue log; nothing is recorded that did not happen, and an unavailable validator is a blocker reported to the Owner, never a skipped item. The validators' review time is GAP-014's open capacity item, which [RELEASE_PLAN](../10-release/RELEASE_PLAN.md) schedules with the UAT of the numbering and of the opening import (UXH-10).

## 13. Security tests

[SECURITY_TEST_MATRIX](SECURITY_TEST_MATRIX.md): SM-01–SM-25 with every data path, W4 row, Owner-only denial and threat; H8-15's CI gates are required checks of every pipeline run.

## 14. Performance

[PERFORMANCE_TARGETS](PERFORMANCE_TARGETS.md): the query budgets as query-count tests, the index verification procedure at 1x, 10x and 100x, the measured checks P6 and P7 leave to P8 and the release-critical set.

## 15. Migration, backup and restore tests

**Schema migrations** (TL-16; AICWDF §29.3): each migration runs up on the previous release's schema with the 10x fixture; a release's migrations are expand-only, the contract step coming in a later release (HO-31), and an architecture test refuses a drop, rename or type change in an expand migration; the previous release's code passes its smoke suite against the expanded schema, so that workers and pages of the previous version keep working while the release rolls (GAP-012); a worker of the previous release releases a job payload newer than it understands, and a command posted from a page loaded before the deployment stays valid for one release (CONCURRENCY_IDEMPOTENCY §15); each migration's duration on the 100x fixture is recorded for the release window. A migration is designed, tested and rehearsed outside production; no agent runs one against production.

**The opening import** (CJ-16; AC-17; WF-MIG-01; DATABASE §24; AX-28), on FX-09:

- validation and quarantine: malformed and duplicate rows surfaced and kept out, a row of a company outside the preparer's scope refused (DP-18), control totals per company reconciled to the batch's signed totals;
- the dry run on an isolated copy, the batch left DRY_RUN_PASSED; the joint sign-off; the Owner's commit (OD-13);
- the commit row by row through the ordinary actions with deterministic command identities in resumable chunks — a retried chunk finding its committed rows done, a refused row keeping its refusal and running again once its cause is removed, a lost chunk re-dispatched from the batch's commit request, a resume by another Owner (M-33; JB-07; JB-09; CI-01; CI-08) —, and the abort of a batch that has committed no row (ADMIN_FLOW §13);
- the numbering at the cutover: the seeds the import writes, the Owner's starts for types no import covers, go-live recorded once, and HO-19's import cases; an imported purchase's number as RELEASE_PLAN's import design states it (DIR-079 CB-01; GAP-058); a company's PURCHASE seed before go-live as `DIR-081` OB-05 reads it;
- AX-28's refusal of an opening purchase whose kept legacy number lies at or above its period's committed seed (NM-08); opening lots with their conversion snapshots and serials, integral base quantities for counted and serialized products (BR-INV-12; C-09; GAP-025);
- opening customer credit as an explicit migration fact, never live-period Cash-In (L-50); historical references kept apart from current effects, so that no replayed purchase duplicates opening stock and no fresh invoice duplicates an imported remainder (GAP-010);
- after the commit, `ReconcileGuards` reports no difference, stock per company equals its opening lots, receivables equal the imported ones, credits equal the signed opening evidence, and the hot plans are re-checked on the imported fixture (GAP-010's reconciliation tests).

**The backup and restore tooling** (AC-18; AC-19; BACKUP_RECOVERY §§2, 5), tested on synthetic data in CI and on staging: a run's manifest and checksums; a corrupt, a missing and an incomplete generation reported failed, never successful (BK-07); a missed or stale run alerting (BK-04; BK-10); rotation across generations and the retention boundary (BK-05; BK-06); a failed cleanup deleting nothing required (BK-12); disk pressure, by a lowered threshold (BK-11); a restore into isolation with the generation's paired key (BKR-08); and each drill's record — the recovery point, the elapsed time, the reconciliation and whether business use was possible — in §16's form. The drills themselves are the operator's — the off-server copy's restore drill before go-live among them, a copied generation brought back from its destination and restored into isolation with its keys (DIR-083 OE-09; RELEASE_PLAN GLC-20) — ([BACKUP_RECOVERY §5](../09-operations/BACKUP_RECOVERY.md#5-restore-testing); [RELEASE_PLAN §7](../10-release/RELEASE_PLAN.md#7-rehearsal-and-drills)).

**The numbering raise after a restore** (DIR-083 OE-11; GAP-064; CONCURRENCY_IDEMPOTENCY NM-03, NM-04 and NX-01; ADMIN_FLOW §12.1), on real PostgreSQL with a restored fixture and the controlled clock (FX-08): the Owner's raise lifts each affected counter — and creates the counter of a live period that began after the recovery point — to the number after the highest the Owner records from the papers, and the next issue draws that number; a raise that would lower a counter, and a second raise of a counter in the same recovery, are refused and write nothing; each raise writes its audit event naming the incident; the raise moves no seed value or seed boundary and raises no defect alert; an Admin is refused (OD-03); no number is drawn twice, NM-09's UNIQUE backstops never fire and every counter stays monotonic (C-10); the facts recorded again take new numbers.

## 16. Evidence

- **Form.** Every check reports `<layer or ID>: PASS | FAIL — Evidence: <commit>, <environment>, <command>, <artefact>`; a journey reports `E2E: PASS — Evidence: …` (AICWDF §26). An artefact is the run's log, its machine-readable test report, the browser trace and screenshots, the console and network capture, the query plans and the measurement output — all of synthetic data.
- **Where it is kept.** As CI artefacts of the run and summarized in the Task's COMPLETION EVIDENCE (AGENT_OPERATING_MODEL); staging evidence is the operator's record, entered without secrets; nothing holding real data is ever an artefact.
- **Run and planned apart.** A planned check is never reported as run; a failed check is recorded with its output, never hidden or retried until green, and the Task stays VERIFYING — or is BLOCKED when the check cannot run — until it passes (TS-06; TS-08; TS-12).
- **Into the release integrity report.** TASKS, ROUTES, INTERACTIONS, CRITICAL JOURNEYS, UI/UX, BROWSER and BUILD of AICWDF §39 are counted from these suites' evidence by [RELEASE_PLAN](../10-release/RELEASE_PLAN.md).

## 17. Tooling

Capabilities first, each with its preferred tool (AICWDF §5); **none is installed and none is assumed verified.** Each is verified — availability, version, licence and cost — by the first P11 Task that needs it, recorded in [TOOLCHAIN](../00-governance/TOOLCHAIN.md) (AICWDF §6.8) and installed project-locally inside an authorized Task; a paid tier follows [COST_POLICY](../00-governance/COST_POLICY.md) and is never adopted silently (DEP-07; REF-061).

| Capability | Preferred tool | Layers |
| --- | --- | --- |
| Unit, feature, integration, authorization and concurrency tests | Pest on PHPUnit (brief §40) | TL-03–TL-06, TL-08 |
| Static analysis and style | Larastan/PHPStan, Laravel Pint, the TypeScript compiler, ESLint (brief §40) | TL-01, TL-02 |
| Production build | the frontend production build and the framework's production caches | TL-13 |
| Dependency audit | composer audit and npm audit (brief §40) | TL-14 |
| Secret-like scan, lockfile-only installs, SHA-pinned actions | a secret scanner and the package managers' locked installs in CI (H8-15) | TL-14 |
| Browser end-to-end | Playwright, AICWDF §5.3's preferred tool, for the deterministic browser suite, on the Chromium and WebKit engines in CI (§12) — [P8Q-01](#p8q-01--the-browser-e2e-capability-and-testsprites-role), decided by DIR-083 OE-01 | TL-07, TL-09–TL-12 |
| UI and E2E regression | TestSprite, for the UI and E2E regression runs the completion obligations name, once a P11 Task has verified its entitlement at no cost or the Owner has approved a cost exception under COST_POLICY; never the sole proof, and if it cannot be used the Owner is told before the gate that needs it (P8Q-01, DIR-083 OE-01; ENGINEERING_PRINCIPLES; ACCEPTANCE_CRITERIA) | TL-09 |
| Accessibility | an automated accessibility engine inside the browser suite, with the manual checks of §11 | TL-12 |
| Performance and load | query-count assertions in Pest; `EXPLAIN (ANALYZE, BUFFERS)`; k6 or an equivalent load tool (brief §40); `pg_stat_statements` and `auto_explain` on staging (PERFORMANCE §12) | TL-15 |
| CI | the public repository's CI at no cost, on the production-like stack of INFRASTRUCTURE §3 | all |

**One verification command** (brief §43): a script such as `scripts/verify.sh` runs the deterministic checks an agent can run locally — TL-01–TL-04, TL-06, the route audit of TL-07, TL-08, TL-13 and the static gates of TL-14, and TL-05 where the agent's machine has the stack —, and CI runs it again in a fresh environment with the integration, end-to-end and performance suites; the script is P11's to write. TOOLCHAIN's row "Static and automated verification", whose strategy is P8's, is this section.

## 18. Owner questions

Each P8 matter that is the Owner's, with its options and one recommendation; nothing waits on the answer to keep drafting, and nothing is installed or adopted before it. Both were decided at OG-3 under DIR-083.

### P8Q-01 — The browser E2E capability and TestSprite's role

- **Context.** TOOLCHAIN leaves the browser E2E capability to P8 (AICWDF §5.3, preferred tool Playwright). ENGINEERING_PRINCIPLES records the brief's UI and E2E regression tool, TestSprite, and ACCEPTANCE_CRITERIA's completion obligations name "relevant TestSprite flows" and "TestSprite regression and fresh CI", with no silent TestSprite omission, no vendor tool as the sole proof and any substitution reviewed under governance (DEP-07). TestSprite is an external service whose entitlement, cost and data handling are unverified; it could run only against CI with synthetic data — staging runs on the production host and is the operator's alone (INFRASTRUCTURE §3). API behaviour is proved by Pest's feature tests; TestSprite's API checks, where useful, stay inside the same role and are never the proof.
- **Options.** (a) **Playwright for the deterministic browser suite** — the journeys, the UX scenarios, the navigation paths, the console and network capture and the accessibility engine — **and TestSprite for the UI and E2E regression runs** the completion obligations name, over the same journeys, once a P11 Task has verified its entitlement at no cost or the Owner has approved a cost exception under COST_POLICY; neither is the sole proof, and if TestSprite cannot be used, the Owner is told before the gate that needs it. (b) **TestSprite as the only browser tool**, beside Pest: fewer tools, but the browser evidence — traces, console and network capture, the accessibility engine — would depend on a vendor service and its free tier. (c) **Playwright only**, TestSprite omitted: a change to ACCEPTANCE_CRITERIA under change control, raised then as an Owner question with its gap.
- **Recommendation.** (a): AICWDF's preferred capability gives deterministic, self-hosted and zero-cost evidence in every CI run, and TestSprite keeps the role the brief and ACCEPTANCE_CRITERIA give it without becoming the sole proof; the cost question stays visible (DEP-07).
- **Decided — [DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-01.** Option (a): Playwright for the deterministic browser suite; TestSprite for the UI and E2E regression runs once a P11 Task has verified its entitlement at no cost, or the Owner has approved a cost exception under COST_POLICY. Neither is the sole proof; if TestSprite cannot be used, the Owner is told before the gate that needs it (§17).

### P8Q-02 — The supported browsers and devices

- **Context.** SECURITY WS-04 and H8-20 speak of "every supported browser", and the end-to-end suite needs a browser matrix, but no approved document lists the supported browsers; DEP-03 leaves phones, browsers and printers to be verified.
- **Options.** (a) **The current stable Google Chrome and Microsoft Edge on the office PCs, Chrome on Android phones and Safari on iPhones**, the device UAT recording what the users actually hold and correcting the list with that evidence; CI runs the Chromium and WebKit engines, the real devices joining at Layer 3. (b) **Chromium-based browsers only**, on every device: a narrower matrix, an iPhone user unsupported — a narrowing of V1_SCOPE's and AC-14's phone support, so choosing it is raised then as an Owner question with its gap. (c) **No list before the device UAT**, the users' own browsers discovered there: WS-04's origin check and H8-20's redirect test have no target until then.
- **Recommendation.** (a): a list that covers the usual office and phone browsers, keeps Safari's engine in every CI run and lets the device UAT correct it with evidence.
- **Decided — [DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-02.** Option (a): the current stable Chrome and Edge on the office PCs, Chrome on Android, Safari on iPhone; CI runs Chromium and WebKit; the device UAT corrects the list with evidence (§12).

## 19. Coverage

Every requirement P8 receives, each mapped by script to the sections of this document (TST), of [SECURITY_TEST_MATRIX](SECURITY_TEST_MATRIX.md) (STM), of [PERFORMANCE_TARGETS](PERFORMANCE_TARGETS.md) (PFT) and of [DEFINITION_OF_DONE](DEFINITION_OF_DONE.md) (DOD) that answer it: AICWDF §19, §§25–28 and §4B's proof as AICWDF_ADOPTION maps them; the brief's §§40–44, the ownership registry's inputs for P8; SP-85's list; and every hit of a scripted search of P0–P7's normative documents and P9's drafts for "P8", sources and evidence excluded, grouped by its identifier or, where a line carries none, by its section. Gate OG-3 checks it. Run PC-5 re-ran it on 2026-10-19 after OG-3's changes (DIR-084 SP-90–SP-93), the search compared by script with its result at `2cff23e`: the one new unit, GAP-064's P8 tests, and the five answers of the Owner's DIR-083 that bind P8 — OE-01, OE-02 and the P8 parts of OE-09–OE-11 — each gain a row; every changed line that held "P8" before keeps its row. **341 requirements, 341 answered, 0 missing.**

| Source | Requirement | Answered in |
| --- | --- | --- |
| AICWDF §19 | AICWDF §19 — the lint layer | TST §2 |
| AICWDF §19 | AICWDF §19 — the typecheck layer | TST §2 |
| AICWDF §19 | AICWDF §19 — the unit layer | TST §2 |
| AICWDF §19 | AICWDF §19 — the feature layer | TST §2 |
| AICWDF §19 | AICWDF §19 — the integration layer | TST §2 |
| AICWDF §19 | AICWDF §19 — the authorization layer | TST §2 |
| AICWDF §19 | AICWDF §19 — the route integrity layer | TST §2 |
| AICWDF §19 | AICWDF §19 — the interaction layer | TST §2 |
| AICWDF §19 | AICWDF §19 — the E2E/browser layer | TST §2 |
| AICWDF §19 | AICWDF §19 — the localization and language-switch tests layer | TST §2 |
| AICWDF §19 | AICWDF §19 — the responsive layer | TST §2 |
| AICWDF §19 | AICWDF §19 — the accessibility critical checks layer | TST §2 |
| AICWDF §19 | AICWDF §19 — the production build layer | TST §2 |
| AICWDF §19 | AICWDF §19 — the security layer | TST §2 |
| AICWDF §19 | AICWDF §19 — the performance layer | TST §2 |
| AICWDF §19 | AICWDF §19 — the migration tests layer | TST §2 |
| AICWDF §19 | AICWDF §19 — the Task statuses; VERIFYING is not DONE | DOD §1, TST §1 |
| AICWDF §25 | AICWDF §25 — release blocker: visible button does nothing | TST §8 |
| AICWDF §25 | AICWDF §25 — release blocker: broken link | TST §8 |
| AICWDF §25 | AICWDF §25 — release blocker: missing route | TST §8 |
| AICWDF §25 | AICWDF §25 — release blocker: wrong HTTP method | TST §8 |
| AICWDF §25 | AICWDF §25 — release blocker: wrong handler | TST §8 |
| AICWDF §25 | AICWDF §25 — release blocker: unexpected 404 | TST §8 |
| AICWDF §25 | AICWDF §25 — release blocker: unexpected 405 | TST §8 |
| AICWDF §25 | AICWDF §25 — release blocker: unexpected 500 | TST §8 |
| AICWDF §25 | AICWDF §25 — release blocker: authorization mismatch | TST §8 |
| AICWDF §25 | AICWDF §25 — release blocker: unusable backend path | TST §8 |
| AICWDF §25 | AICWDF §25 — release blocker: form submit without feedback | TST §8 |
| AICWDF §25 | AICWDF §25 — release blocker: wrong success redirect | TST §8 |
| AICWDF §25 | AICWDF §25 — release blocker: back or cancel | TST §8 |
| AICWDF §25 | AICWDF §25 — release blocker: console errors | TST §8 |
| AICWDF §25 | AICWDF §25 — release blocker: failed network requests | TST §8 |
| AICWDF §25 | AICWDF §25 — the nine zero targets | TST §8 |
| AICWDF §26 | AICWDF §26 — browser automation for critical journeys; "E2E: PASS / Evidence" | TST §7 |
| AICWDF §27 | AICWDF §27 — Modern visual quality | TST §9 |
| AICWDF §27 | AICWDF §27 — Design system consistency | TST §9 |
| AICWDF §27 | AICWDF §27 — Primary action clear | TST §9 |
| AICWDF §27 | AICWDF §27 — Unnecessary duplicate actions | TST §9 |
| AICWDF §27 | AICWDF §27 — Back action clear | TST §9 |
| AICWDF §27 | AICWDF §27 — navigation contract valid | TST §9 |
| AICWDF §27 | AICWDF §27 — Indonesian default | TST §9 |
| AICWDF §27 | AICWDF §27 — plain Indonesian | TST §9 |
| AICWDF §27 | AICWDF §27 — English available | TST §9 |
| AICWDF §27 | AICWDF §27 — missing translations | TST §9 |
| AICWDF §27 | AICWDF §27 — language switch | TST §9 |
| AICWDF §27 | AICWDF §27 — Mobile-first | TST §9 |
| AICWDF §27 | AICWDF §27 — responsive | TST §9 |
| AICWDF §27 | AICWDF §27 — adaptive | TST §9 |
| AICWDF §27 | AICWDF §27 — Loading | TST §9 |
| AICWDF §27 | AICWDF §27 — empty | TST §9 |
| AICWDF §27 | AICWDF §27 — error states | TST §9 |
| AICWDF §27 | AICWDF §27 — Destructive action safety | TST §9 |
| AICWDF §27 | AICWDF §27 — Accessibility critical checks | TST §9 |
| AICWDF §27 | AICWDF §27 — Visual breathing room | TST §9 |
| AICWDF §27 | AICWDF §27 — content density appropriate | TST §9 |
| AICWDF §28 | AICWDF §28 — Layer 1, Task verification | TST §3 |
| AICWDF §28 | AICWDF §28 — Layer 2, login and logout | TST §3 |
| AICWDF §28 | AICWDF §28 — Layer 2, create, edit and delete | TST §3 |
| AICWDF §28 | AICWDF §28 — Layer 2, search and filter | TST §3 |
| AICWDF §28 | AICWDF §28 — Layer 2, approval | TST §3 |
| AICWDF §28 | AICWDF §28 — Layer 2, permissions | TST §3 |
| AICWDF §28 | AICWDF §28 — Layer 2, payments | TST §3 |
| AICWDF §28 | AICWDF §28 — Layer 2, reporting | TST §3 |
| AICWDF §28 | AICWDF §28 — Layer 2, examinations | TST §3 |
| AICWDF §28 | AICWDF §28 — Layer 3, every critical journey | TST §3 |
| AICWDF §28 | AICWDF §28 — Layer 3, major roles | TST §3 |
| AICWDF §28 | AICWDF §28 — Layer 3, every route | TST §3 |
| AICWDF §28 | AICWDF §28 — Layer 3, core forms | TST §3 |
| AICWDF §28 | AICWDF §28 — Layer 3, the phone and on the desktop | TST §3 |
| AICWDF §28 | AICWDF §28 — Layer 3, critical integrations | TST §3 |
| AICWDF §28 | AICWDF §28 — Layer 3, STM case | TST §3 |
| AICWDF §28 | AICWDF §28 — Layer 3, release-critical checks | TST §3 |
| AICWDF §4B | AICWDF §4B — the bilingual experience, proof in P8 | TST §10 |
| AICWDF_ADOPTION | AICWDF_ADOPTION — §4B, §19, §25, §26, §27 and §28 pending P8 | TST §2, TST §3, TST §7, TST §8, TST §9, TST §10 |
| Brief §40 | Brief §40 — AI opinion is not proof; deterministic assertions | TST §1 |
| Brief §40 | Brief §40 — Pest/PHPUnit for unit | TST §17, TST §2, TST §5, TST §6, STM §3 |
| Brief §40 | Brief §40 — Pest/PHPUnit for feature | TST §17, TST §2, TST §5, TST §6, STM §3 |
| Brief §40 | Brief §40 — Pest/PHPUnit for integration | TST §17, TST §2, TST §5, TST §6, STM §3 |
| Brief §40 | Brief §40 — Pest/PHPUnit for authorization | TST §17, TST §2, TST §5, TST §6, STM §3 |
| Brief §40 | Brief §40 — Pest/PHPUnit for validation | TST §17, TST §2, TST §5, TST §6, STM §3 |
| Brief §40 | Brief §40 — Pest/PHPUnit for company isolation | TST §17, TST §2, TST §5, TST §6, STM §3 |
| Brief §40 | Brief §40 — Pest/PHPUnit for database invariants | TST §17, TST §2, TST §5, TST §6, STM §3 |
| Brief §40 | Brief §40 — Pest/PHPUnit for idempotency | TST §17, TST §2, TST §5, TST §6, STM §3 |
| Brief §40 | Brief §40 — Pest/PHPUnit for concurrency | TST §17, TST §2, TST §5, TST §6, STM §3 |
| Brief §40 | Brief §40 — Pest/PHPUnit for stock integrity | TST §17, TST §2, TST §5, TST §6, STM §3 |
| Brief §40 | Brief §40 — Pest/PHPUnit for payment integrity | TST §17, TST §2, TST §5, TST §6, STM §3 |
| Brief §40 | Brief §40 — TestSprite for UI | TST §17, TST §18 |
| Brief §40 | Brief §40 — TestSprite for E2E | TST §17, TST §18 |
| Brief §40 | Brief §40 — TestSprite for regression user flows | TST §17, TST §18 |
| Brief §40 | Brief §40 — TestSprite for API behaviour where useful | TST §17, TST §18 |
| Brief §40 | Brief §40 — Larastan/PHPStan | TST §17 |
| Brief §40 | Brief §40 — Laravel Pint | TST §17 |
| Brief §40 | Brief §40 — TypeScript | TST §17 |
| Brief §40 | Brief §40 — ESLint | TST §17 |
| Brief §40 | Brief §40 — frontend production build | TST §17 |
| Brief §40 | Brief §40 — composer audit | TST §17 |
| Brief §40 | Brief §40 — npm audit | TST §17 |
| Brief §40 | Brief §40 — k6 | TST §17 |
| Brief §40 | Brief §40 — TestSprite never the only test layer | TST §1, TST §18 |
| Brief §41 | Brief §41 — the required critical tests: Authorization | TST §5 |
| Brief §41 | Brief §41 — the required critical tests: Inventory | TST §5 |
| Brief §41 | Brief §41 — the required critical tests: Payment | TST §5 |
| Brief §41 | Brief §41 — the required critical tests: Numbering | TST §5 |
| Brief §41 | Brief §41 — the required critical tests: Documents | TST §5 |
| Brief §41 | Brief §41 — the required critical tests: Validation | TST §5 |
| Brief §41 | Brief §41 — the required critical tests: Files | TST §5 |
| Brief §42 | Brief §42 — the golden business flow | TST §7 |
| Brief §42 | Brief §42 — additional scenario: without a PO | TST §7 |
| Brief §42 | Brief §42 — additional scenario: Partial delivery | TST §7 |
| Brief §42 | Brief §42 — additional scenario: partial and full payment | TST §7 |
| Brief §42 | Brief §42 — additional scenario: Stock opname | TST §7 |
| Brief §42 | Brief §42 — additional scenario: Correction and void | TST §7 |
| Brief §42 | Brief §42 — additional scenario: The barcode flow | TST §7 |
| Brief §42 | Brief §42 — additional scenario: SIPLAH | TST §7 |
| Brief §42 | Brief §42 — additional scenario: Company isolation | TST §7 |
| Brief §43 | Brief §43 — one verification command | TST §17 |
| Brief §43 | Brief §43 — local verify, then fresh CI; complete only when the gates pass | TST §1 |
| Brief §44 | Brief §44 — every category considered, its applicability explained | DOD §3 |
| Brief §44 | Brief §44 — never weaken or delete a valid test to pass CI | DOD §2, TST §1 |
| SP-85 | The route audit with OB-03's declarations | STM §3 |
| SP-85 | PERFORMANCE's budgets and measured checks | PFT §2, PFT §3 |
| SP-85 | CA-06's PF-42 | PFT §3 |
| SP-85 | CB-03's QB-10 bound | PFT §3 |
| SP-85 | CONCURRENCY_IDEMPOTENCY's proofs and scenarios | TST §6 |
| SP-85 | DATABASE's integrity checks | TST §5 |
| SP-85 | ACCEPTANCE_CRITERIA | TST §5, DOD §2 |
| SP-85 | GAP-020's real-device and composer checks (CA-02; CD-03; CD-04) | TST §12 |
| SP-85 | CD-06's screens | TST §9 |
| SP-85 | GAP-037 with OB-04's per-page copy review | TST §10 |
| SP-85 | GAP-043, GAP-049 and GAP-050 (M-46) | STM §3 |
| SP-85 | WCAG 2.2 AA on the application | TST §11 |
| SP-85 | LOCALIZATION's checks | TST §10 |
| SP-85 | The INT- contracts' tests | TST §8 |
| SP-85 | The definition of done that every P11 Task cites | DOD §2 |
| SP-85 | Synthetic test data only, production data never | TST §1, TST §4 |
| SP-85 | The test tooling TOOLCHAIN plans, none installed or assumed verified, its verification a P11 Task | TST §17 |
| SP-85 | Nothing of P8 runs a test | TST §1 |
| AGENT_OPERATING_MODEL | AGENT_OPERATING_MODEL — the eight DoD dimensions for P8; a missing tool is not N/A; a working UI alone is not DONE | DOD §1, DOD §2 |
| ENGINEERING_PRINCIPLES | ENGINEERING_PRINCIPLES — the controls and tests of brief §§24–25 and 41 | STM §3, TST §5 |
| ENGINEERING_PRINCIPLES | ENGINEERING_PRINCIPLES — budgets are assumptions until P8 measures them | PFT §1, PFT §3 |
| ENGINEERING_PRINCIPLES | ENGINEERING_PRINCIPLES — P8 formalizes the eight dimensions, the dispatch, payment and invoice examples, module and release gates | DOD §2, DOD §4, DOD §5 |
| PROJECT_CHARTER | PROJECT_CHARTER — P8, testing, quality and definition of done | TST §2, DOD §1 |
| TOOLCHAIN | TOOLCHAIN — the browser E2E capability and the static and automated verification strategy | TST §17, TST §18 |
| GAP_REGISTER | GAP-003 | TST §5, STM §3 |
| GAP_REGISTER | GAP-004 | TST §4, TST §5 |
| GAP_REGISTER | GAP-005 | TST §5, STM §3 |
| GAP_REGISTER | GAP-006 | STM §3 |
| GAP_REGISTER | GAP-007 | TST §6, DOD §4 |
| GAP_REGISTER | GAP-008 | TST §6 |
| GAP_REGISTER | GAP-009 | TST §6 |
| GAP_REGISTER | GAP-010 | TST §15 |
| GAP_REGISTER | GAP-014 | TST §12 |
| GAP_REGISTER | GAP-015 | TST §6, TST §7 |
| GAP_REGISTER | GAP-016 | TST §6, DOD §4 |
| GAP_REGISTER | GAP-017 | TST §6, PFT §1 |
| GAP_REGISTER | GAP-019 | TST §4, TST §12 |
| GAP_REGISTER | GAP-020 | TST §12 |
| GAP_REGISTER | GAP-021 | TST §5, TST §7 |
| GAP_REGISTER | GAP-022 | TST §5, STM §3 |
| GAP_REGISTER | GAP-023 | TST §5, TST §6 |
| GAP_REGISTER | GAP-024 | TST §5 |
| GAP_REGISTER | GAP-025 | TST §5 |
| GAP_REGISTER | GAP-026 | TST §5, STM §3 |
| GAP_REGISTER | GAP-027 | TST §5 |
| GAP_REGISTER | GAP-028 | TST §5 |
| GAP_REGISTER | GAP-029 | TST §5 |
| GAP_REGISTER | GAP-030 | TST §4, TST §5, STM §3 |
| GAP_REGISTER | GAP-031 | TST §5 |
| GAP_REGISTER | GAP-032 | STM §3, TST §7 |
| GAP_REGISTER | GAP-033 | STM §3 |
| GAP_REGISTER | GAP-034 | STM §3 |
| GAP_REGISTER | GAP-035 | TST §8 |
| GAP_REGISTER | GAP-036 | STM §3 |
| GAP_REGISTER | GAP-037 | TST §10 |
| GAP_REGISTER | GAP-038 | STM §8 |
| GAP_REGISTER | GAP-039 | STM §8 |
| GAP_REGISTER | GAP-040 | STM §8 |
| GAP_REGISTER | GAP-041 | STM §8 |
| GAP_REGISTER | GAP-043 | STM §3, TST §6 |
| GAP_REGISTER | GAP-044 | STM §3, TST §7 |
| GAP_REGISTER | GAP-045 | STM §8 |
| GAP_REGISTER | GAP-046 | STM §3, STM §8 |
| GAP_REGISTER | GAP-047 | STM §3, STM §8 |
| GAP_REGISTER | GAP-048 | STM §3, STM §8 |
| GAP_REGISTER | GAP-050 | STM §3 |
| GAP_REGISTER | GAP-051 | STM §3 |
| GAP_REGISTER | GAP-064 — P8's tests of the raise after a restore | TST §15 |
| GAP_REGISTER | GAP-053 | TST §9 |
| GAP_REGISTER | GAP-054 | PFT §3 |
| GAP_REGISTER | GAP-055 | TST §9, PFT §3 |
| GAP_REGISTER | GAP-056 | TST §9, TST §10 |
| GAP_REGISTER | GAP-057 | TST §9 |
| GAP_REGISTER | GAP-060 | PFT §3 |
| GAP_REGISTER | GAP-061 | STM §3 |
| GAP_REGISTER | GAP_REGISTER — every open gap's P8 evidence | TST §5, TST §6, STM §3 |
| ACCEPTANCE_CRITERIA | ACCEPTANCE_CRITERIA — P8 assigns deterministic test cases and evidence | TST §5 |
| ACCEPTANCE_CRITERIA | AC-13 | TST §5, STM §3 |
| ACCEPTANCE_CRITERIA | AC-14 | TST §5, TST §10, TST §12 |
| ACCEPTANCE_CRITERIA | AC-16 | TST §5, TST §7 |
| ACCEPTANCE_CRITERIA | AC-23 | TST §5, PFT §3 |
| ACCEPTANCE_CRITERIA | ACCEPTANCE_CRITERIA — numeric examples as fixtures; workloads; recovery measurements | TST §5, TST §15, PFT §3 |
| ACCEPTANCE_CRITERIA | ACCEPTANCE_CRITERIA — P8 establishes measurable workloads and targets | PFT §1, PFT §3 |
| ACCEPTANCE_CRITERIA | ACCEPTANCE_CRITERIA — P8 expands failure tests, never a UI-only pass | TST §1, TST §5 |
| ACCEPTANCE_CRITERIA | ACCEPTANCE_CRITERIA — P8 owns the definition of done and the test strategy; no silent TestSprite omission | DOD §2, DOD §5, TST §18 |
| REFERENCE_COVERAGE | REF-001 | STM §3 |
| REFERENCE_COVERAGE | REF-056 | STM §3 |
| REFERENCE_COVERAGE | REF-059 | PFT §3 |
| REFERENCE_COVERAGE | REF-061 | TST §17 |
| REFERENCE_COVERAGE | REF-G06 — every category and reasoned N/A preserved | DOD §2 |
| REFERENCE_COVERAGE | OWN-01 | TST §5 |
| REFERENCE_COVERAGE | OWN-02 | TST §5, STM §3 |
| REFERENCE_COVERAGE | OWN-03 | TST §5, STM §3 |
| REFERENCE_COVERAGE | OWN-04 | TST §5, TST §6 |
| REFERENCE_COVERAGE | OWN-05 | TST §5 |
| REFERENCE_COVERAGE | REFERENCE_COVERAGE — deterministic fixtures and assertions reused across modules | TST §4 |
| REFERENCE_COVERAGE | REFERENCE_COVERAGE's P8 row — test identifiers, assertions, the eight-dimension DoD, N/A reasons, critical examples | TST §5 |
| V1_SCOPE | DEP-03 | TST §12 |
| V1_SCOPE | DEP-07 | TST §17 |
| V1_SCOPE | DEP-09 | TST §12, PFT §3 |
| V1_SCOPE | DEP-11 | STM §3, TST §12 |
| BUSINESS_RULES | BUSINESS_RULES — P8 proves the rules with the numeric annex | TST §5 |
| BUSINESS_RULES | The numeric annex, binding examples for P8 fixtures | TST §4, TST §5 |
| BUSINESS_RULES | BR-XD-07 — races proved with real concurrent assertions | TST §5 |
| INTERACTION_CONTRACTS | INTERACTION_CONTRACTS — each contract's Test written as its test | TST §8 |
| WORKFLOWS | WORKFLOWS — test identifiers are P8's | TST §5 |
| WORKFLOWS | WF-ACC-01 — denial and revoked-access tests | TST §5, STM §3 |
| WORKFLOWS | WF-INV-02 — last-unit races | TST §6 |
| WORKFLOWS §9 | WORKFLOWS §9 — each indivisible action proved with real concurrent tests | TST §5 |
| WORKFLOWS §13 | WORKFLOWS §13 — scenario families per workflow and CM row, P3's scenarios as fixtures, concurrency per AX, denial per §4 | TST §5, TST §4 |
| ARCHITECTURE | ARCHITECTURE — P8 owns the tests | TST §2 |
| ARCHITECTURE §4 | ARCHITECTURE §4 — the Feature, Concurrency and Reconciliation suites | TST §2 |
| ARCHITECTURE §7 | ARCHITECTURE §7 — `ReconcileGuards` after every scenario; `GuardMaintenance` the audited exception | TST §5, TST §1 |
| ARCHITECTURE §18 | ARCHITECTURE §18 — real PostgreSQL, parallel connections, guard verification, annex unit tests, policy tests | TST §1, TST §2 |
| ARCHITECTURE §20 | ARCHITECTURE §20 — P8 builds §18's suites | TST §2 |
| DATABASE | DATABASE — P8 owns the tests | TST §5 |
| DATABASE §3.1 | DATABASE §3.1 — the registry drives the reconciliation suite | TST §5 |
| DATABASE §5.8 | DATABASE §5.8 — the transportation argument and the evidence resolution proved on fixtures | TST §5 |
| DATABASE §6.4 | DATABASE §6.4 — the eight identities proved with the annex fixtures | TST §5 |
| DATABASE §18 | DATABASE §18 — every C-row a test obligation; ⚡ rows raced; DQ queries | TST §5 |
| DATABASE §20 | DATABASE §20 — indexes confirmed by measured plans | PFT §3 |
| DATABASE §30 | CAP-03 | TST §5 |
| DATABASE §30 | CAP-05 | TST §5 |
| DATABASE §30 | CAP-06 | TST §5 |
| DATABASE §30 | CAP-08 | TST §5 |
| DATABASE §30 | CAP-10 | TST §5 |
| DATABASE §30 | CAP-11 | TST §5 |
| DATABASE §30 | CAP-13 | TST §5 |
| DATABASE §30 | CAP-18 | TST §5 |
| DATABASE §31 | DATABASE §31 — every C-row, the races, the DQ suite, annex 1–16, P3 scenarios 1–18, DIR-027 fixtures, the P4 adversarial fixtures; the authentication amendment's C-62–C-65 | TST §4, TST §5, STM §3 |
| PERMISSIONS_MATRIX | PERMISSIONS_MATRIX — tests are P8's | STM §3 |
| PERMISSIONS_MATRIX §9 | OD-16 | STM §3, STM §6 |
| PERMISSIONS_MATRIX §11 | PERMISSIONS_MATRIX §11 — the P8 denial obligation of every data path | STM §4 |
| PERMISSIONS_MATRIX §13 | PERMISSIONS_MATRIX §13 — every W4 row, OD rule and DP path with positive and denial tests | STM §4, STM §5, STM §6 |
| SECURITY | SECURITY — P8 tests, as §10 lists | STM §3 |
| SECURITY §2 | AU-05 | STM §3 |
| SECURITY §3 | EN-06 | STM §3 |
| SECURITY §4 | WS-04 | STM §3 |
| SECURITY §5 | FL-02 | STM §3, TST §12 |
| SECURITY §8 | SECURITY §8 — the P8 proof of every threat | STM §7 |
| SECURITY §9 | D-SEC-07 | STM §3 |
| SECURITY §10 | H8-01–H8-22 | STM §3 |
| SECURITY §10 | H9-14 — the P8 obligations of the recovery path stay in force | STM §3 |
| CONCURRENCY_IDEMPOTENCY | CONCURRENCY_IDEMPOTENCY — P8 proves the mechanisms with real PostgreSQL sessions | TST §6 |
| CONCURRENCY_IDEMPOTENCY | CONCURRENCY_IDEMPOTENCY — facts not plainly documented are P8 proof obligations | TST §6 |
| CONCURRENCY_IDEMPOTENCY §2 | D-CC-04 | TST §6, PFT §3 |
| CONCURRENCY_IDEMPOTENCY §4 | LR-05 | TST §6 |
| CONCURRENCY_IDEMPOTENCY §8 | CONCURRENCY_IDEMPOTENCY §8 — `ReconcileGuards` after every P8 scenario | TST §1 |
| CONCURRENCY_IDEMPOTENCY §17 | H6-07 | STM §3, PFT §3 |
| CONCURRENCY_IDEMPOTENCY §18 | M-01–M-46 | TST §6 |
| CONCURRENCY_IDEMPOTENCY §19 | HO-11 | TST §6 |
| CONCURRENCY_IDEMPOTENCY §19 | HO-13 | TST §6 |
| CONCURRENCY_IDEMPOTENCY §19 | HO-14 | TST §6 |
| CONCURRENCY_IDEMPOTENCY §19 | HO-15 | TST §6 |
| CONCURRENCY_IDEMPOTENCY §19 | HO-17 | TST §6 |
| CONCURRENCY_IDEMPOTENCY §19 | HO-18 | TST §6 |
| CONCURRENCY_IDEMPOTENCY §19 | HO-19 | TST §6 |
| CONCURRENCY_IDEMPOTENCY §19 | HO-20 | TST §6 |
| CONCURRENCY_IDEMPOTENCY §19 | HO-41 | TST §6 |
| CONCURRENCY_IDEMPOTENCY §19 | HO-12 | TST §1, TST §5 |
| CONCURRENCY_IDEMPOTENCY §19 | HO-16 | PFT §3 |
| CONCURRENCY_IDEMPOTENCY §19 | HO-35 | TST §12 |
| CONCURRENCY_IDEMPOTENCY §19 | HO-38 | STM §3 |
| CONCURRENCY_IDEMPOTENCY §20 | CONCURRENCY_IDEMPOTENCY §20 — the gaps' runtime proof is P8's | TST §6 |
| PERFORMANCE | PERFORMANCE — P8 owns the measured targets; CA-06's stage filter only when bounded | PFT §1, PFT §3 |
| PERFORMANCE | PERFORMANCE — facts marked source or old are P8 verification items | TST §6, PFT §3 |
| PERFORMANCE §1 | AS-03 | PFT §1, PFT §3 |
| PERFORMANCE §1 | AS-06 | PFT §1, PFT §3 |
| PERFORMANCE §1 | AS-07 | PFT §1, PFT §3 |
| PERFORMANCE | PF-09 | PFT §3 |
| PERFORMANCE | PF-21 | PFT §3 |
| PERFORMANCE | PF-24 | PFT §3 |
| PERFORMANCE | PF-30 | PFT §3 |
| PERFORMANCE §6 | QB-01 | PFT §2, PFT §3 |
| PERFORMANCE §8 | PERFORMANCE §8 — the cost of uncached thumbnails measured first | PFT §3 |
| PERFORMANCE §9 | CP-04 | PFT §3 |
| PERFORMANCE §12 | PERFORMANCE §12 — the verification procedure and the known candidates | PFT §3 |
| PERFORMANCE §12 | IX-09 | PFT §3 |
| PERFORMANCE §14 | PERFORMANCE §14 — budgets are assumptions until P8 measures them | PFT §1 |
| ADMIN_FLOW | ADMIN_FLOW — P8 owns the tests | TST §7 |
| ADMIN_FLOW §2 | ADMIN_FLOW §2 — inferred consequences P8 must prove | TST §7 |
| ADMIN_FLOW §6 | IP-11 — the background marker, the leave guard and the input kept across re-authentication | TST §7 |
| ADMIN_FLOW §15 | UXS-01–UXS-44 as end-to-end scenarios | TST §7 |
| ADMIN_FLOW §16 | UXH-01 | TST §7 |
| ADMIN_FLOW §16 | UXH-02 | TST §7, STM §3 |
| ADMIN_FLOW §16 | UXH-03 | STM §3 |
| ADMIN_FLOW §16 | UXH-04 | STM §3 |
| ADMIN_FLOW §16 | UXH-05 | TST §11 |
| ADMIN_FLOW §16 | UXH-06 | TST §12 |
| ADMIN_FLOW §16 | UXH-07 | TST §9 |
| ADMIN_FLOW §16 | UXH-08 | TST §12 |
| DESIGN_SYSTEM §1 | DESIGN_SYSTEM §1 — starting values tuned by measurement | TST §9 |
| DESIGN_SYSTEM §14.3 | DESIGN_SYSTEM §14.3 — what P8 tests of the draft preview | TST §9 |
| DESIGN_SYSTEM §15 | DESIGN_SYSTEM §15 — WCAG 2.2 AA audited on the built screens | TST §11 |
| DESIGN_SYSTEM Annex A | DESIGN_SYSTEM Annex A — the application measured by P8, never the prototype | TST §9 |
| DESIGN_SYSTEM Annex A | DA-01 | TST §9 |
| DESIGN_SYSTEM Annex A | DA-02 | TST §9 |
| DESIGN_SYSTEM Annex B | GR-10 | TST §9 |
| INFORMATION_ARCHITECTURE | INFORMATION_ARCHITECTURE — P8 owns the tests | TST §9 |
| INFORMATION_ARCHITECTURE §4 | D-IA-19 | PFT §3 |
| INFORMATION_ARCHITECTURE §8 | PG-010 | PFT §3, TST §9 |
| INFORMATION_ARCHITECTURE §9 | INFORMATION_ARCHITECTURE §9 — the Owner's security notices, their plan verified | PFT §3 |
| LOCALIZATION | LOCALIZATION — P8 tests both languages | TST §10 |
| LOCALIZATION §3.6 | LOCALIZATION §3.6 — the checks in the definition of done | TST §10, DOD §2 |
| LOCALIZATION §3.7 | LOCALIZATION §3.7 — the layout checks after a language change | TST §10 |
| LOCALIZATION §5 | LOCALIZATION §5 — each page's P8 review of new copy (OB-04) | TST §10 |
| LOCALIZATION §6 | LOCALIZATION §6 — a page with a missing string fails | TST §10 |
| NAVIGATION_CONTRACTS | NAVIGATION_CONTRACTS — each contract's back, cancel, success and error paths | TST §8 |
| BACKUP_RECOVERY §6 | TT-11 — what stays open for P8 (M-46) | STM §3, TST §6 |
| INFRASTRUCTURE | INFRASTRUCTURE — it operates what P0–P8 define | TST §4 |
| INFRASTRUCTURE §3 | INFRASTRUCTURE §3 — the CI stack for P8's integration and E2E suites | TST §4 |
| BACKUP_RECOVERY §6 | TT-F30 — the end-of-recovery race tested in P8 | STM §3 |
| BACKUP_RECOVERY §9 | BACKUP_RECOVERY §9 — the recovery measurements and workloads P8 operationalizes with P9 | TST §15, PFT §3 |
| INFRASTRUCTURE §6 | INFRASTRUCTURE §6 — the sizes P8 measures at 100x as the first baseline | PFT §3 |
| INFRASTRUCTURE §11 | INFRASTRUCTURE §11 — the rows P8 shares with P9 | PFT §3, STM §3 |
| INFRASTRUCTURE §7.3 | INFRASTRUCTURE §7.3 step 8 — the end-of-recovery race P8 tests | STM §3 |
| Owner in session | The second production application on the VPS untouched by every test and load run | TST §4, PFT §1 |
| OG-3 (DIR-083) | OE-01 — Playwright for the deterministic browser suite, TestSprite for the UI and E2E regression runs, neither the sole proof | TST §7, TST §17, TST §18 |
| OG-3 (DIR-083) | OE-02 — the supported browsers and devices, CI on Chromium and WebKit, the list corrected by the device UAT | TST §7, TST §12, TST §18 |
| OG-3 (DIR-083) | OE-09 — the off-server copy's drill before go-live, named | TST §15 |
| OG-3 (DIR-083) | OE-10 — P8 tests the recovery halt's path | STM §3 |
| OG-3 (DIR-083) | OE-11 — P8 tests the raise: never lowering, once per recovery, audited, C-10 kept | TST §15 |

# Proactive gap register

Status: REVIEW | Updated: 2026-09-27 | Owner: Planning

This is the single index of discovered gaps, risk treatment and pending owner decisions. Findings use the [owner records and supporting references](SOURCE_OF_TRUTH.md#authority-and-provenance) and current P0/P1 documents. They are planning risks, not claims of vulnerabilities in an application that does not yet exist. P1 decisions and follow-up answers resolve parts of earlier gaps without proving runtime mitigation. Recommendations remain proposals unless explicitly adopted or delegated under [decision authority](CHANGE_CONTROL.md#decision-authority).

## Operating rules

Keep only meaningful failure scenarios. Every entry identifies area, evidence/description, severity, probability, impact, when it matters, mitigation, status, responsible role/decision owner and affected phase or build units. Severity expresses consequence: BLOCKER stops the named gate, CRITICAL threatens serious security/data integrity or recovery, HIGH threatens core operation, MEDIUM causes material disruption, LOW is limited. Probability is qualitative: OBSERVED, LIKELY, POSSIBLE or UNKNOWN; these are reasoned judgments, not measured incident rates.

Statuses: OPEN (unresolved); MITIGATED (evidence of reduced risk, residual risk stated); OWNER_DECISION_REQUIRED (Level 2/3 choice pending); ACCEPTED_RISK (explicit authorized risk acceptance with reason and review trigger); DEFERRED (scope/dependency reason and revisit gate); CLOSED (resolution evidence). A mitigation proposal or planned test alone is not mitigation evidence. Do not accept away non-negotiable safeguards or defer a dependency beyond its build/release gate. Changing risk tolerance requires the owner.

Add findings throughout planning/execution; review at every phase end and new discovery. Move actual rules into their canonical concern, link evidence here and avoid creating a second risk register. Build-unit IDs do not yet exist; phase/domain references below must be mapped to real unit IDs in P11.

## Triage

| ID | Area | Severity | Probability | Status | Resolve before |
| --- | --- | --- | --- | --- | --- |
| GAP-001 | Delegated technical authority | HIGH | OBSERVED | CLOSED | P0 handoff |
| GAP-002 | Durable repository handoff | HIGH | OBSERVED | OPEN | Another checkout/agent takeover |
| GAP-003 | Pooled stock with preserved source attribution | CRITICAL | POSSIBLE | OPEN | P2 rules / P4 stock design |
| GAP-004 | Financial meaning and rounding | CRITICAL | UNKNOWN | OWNER_DECISION_REQUIRED | P2 rules / financial schema |
| GAP-005 | Downstream corrections | HIGH | POSSIBLE | OPEN | P3 transition design |
| GAP-006 | Shared masters and data visibility | CRITICAL | UNKNOWN | OWNER_DECISION_REQUIRED | P2 ownership / P5 policies |
| GAP-007 | Issued document and file consistency | HIGH | POSSIBLE | OPEN | P4/P6 issue design |
| GAP-008 | Retry after uncertain completion | CRITICAL | POSSIBLE | OPEN | P4/P6 command design |
| GAP-009 | Stock count, dispatch and scanning races | CRITICAL | POSSIBLE | OPEN | P3/P4/P6 inventory design |
| GAP-010 | Migration opening-state overlap | CRITICAL | UNKNOWN | OPEN | P4 model; cutover in P10 |
| GAP-011 | Proving the agreed recovery targets | CRITICAL | UNKNOWN | OPEN | P9 plan; release gate |
| GAP-012 | Deployment and mixed worker versions | HIGH | POSSIBLE | OPEN | P9/P10 deployment plan |
| GAP-013 | Failure detection and operational ownership | HIGH | POSSIBLE | OPEN | P9 operations; release gate |
| GAP-014 | Deadline, capacity and business acceptance | HIGH | UNKNOWN | OPEN | Scope commitment / P10 readiness |
| GAP-015 | Stale/expired operational UI and scans | HIGH | POSSIBLE | OPEN | P7 interactions / P8 E2E |
| GAP-016 | Dates, period boundaries and audit ordering | HIGH | POSSIBLE | OPEN | P2/P4/P6 date contracts |
| GAP-017 | Evidence, workload and independent verification | HIGH | UNKNOWN | OPEN | P6/P8 targets; P11 units |
| GAP-018 | Offsite recovery versus near-zero incremental cost | CRITICAL | UNKNOWN | OWNER_DECISION_REQUIRED | P9 resource choice / release commitment |
| GAP-019 | Complete document catalog and content validation | HIGH | POSSIBLE | OPEN | P3/P7 documents / P8 UAT |
| GAP-020 | Mobile/scanner practicality and desktop productivity | HIGH | UNKNOWN | OPEN | P7 design gate / P8 device UAT |
| GAP-021 | Forced purchase/warehouse work for all projects | HIGH | POSSIBLE | CLOSED | P1 scope clarification |
| GAP-022 | Completion exceptions and subsequent corrections | CRITICAL | UNKNOWN | OWNER_DECISION_REQUIRED | P2/P3 completion rules / P5 permissions |
| GAP-023 | Allocation, reservation and usable stock | HIGH | UNKNOWN | OWNER_DECISION_REQUIRED | P2/P3 stock policy / dependent P4 design |
| GAP-024 | Reference requirements lost before build/test mapping | HIGH | POSSIBLE | OPEN | P8 test coverage / P11 freeze |

No finding prevents preparing the P1 product/scope package for review. Product scope acceptance and release readiness remain separate from that document gate. Remaining owner decisions are GAP-004/006/018/022/023; stock pooling, correction principles, access baseline and recovery targets must not be asked again as undecided. Later design and runtime evidence still have to satisfy their gates. Register totals: **24 findings — 2 CLOSED, 17 OPEN, 5 OWNER_DECISION_REQUIRED**.

## GAP-001 — Blanket approval rules could preserve a known-bad plan

- **Description/evidence:** Original P0 change control required owner approval for every substantive technical change; source governance instructed executors to keep using the approved revision until a replacement was approved. That could preserve an unsafe revision and contradict the new delegation.
- **Impact:** Avoidable waiting, unsafe execution and provider-dependent interpretation of authority.
- **Mitigation/evidence:** Applied TECH-001: [change control](CHANGE_CONTROL.md#decision-authority) now distinguishes three levels; [lifecycle](SOURCE_OF_TRUTH.md#document-lifecycle) and [feedback loop](AGENT_OPERATING_MODEL.md#executor-feedback-loop) permit direct technical correction and pause only unsafe/dependent work. No business approval was invented.
- **Owner:** Planner; no owner decision required. **Affected:** All planning phases, execution feedback. **Closure:** Document consistency check and authority examples recorded in the P0 extension review.

## GAP-002 — Local documents alone cannot support another checkout

- **Description/evidence:** At the start of this extension all P0 files were untracked on unborn `main`; no remote checkpoint contained them. Losing the workstation or switching agents to another checkout would lose the canonical context.
- **Impact:** Lost decisions, conflicting baselines or agents accidentally rebuilding the plan from chat.
- **Mitigation/residual:** Local P0 checkpoint `b425584` exists and was accepted. Preserve P1 in a further documentation checkpoint after validation. Share an identified commit through an authorized repository workflow before remote handoff; receiver confirms commit plus CURRENT_STATE. No push was performed; a local commit alone is not an off-machine copy.
- **Owner:** Planner maintains local checkpoint; owner/repository maintainer controls publication as needed. **Affected:** P0 handoff and every agent replacement. **Residual/closure:** See CURRENT_STATE for actual commit/share state; keep OPEN until a receiving checkout can obtain the same checkpoint.

## GAP-003 — One stock pool must preserve source and consuming-company attribution

- **Description/evidence:** P1 Warehouse now settles physical stock as one pool and requires explicit source/cost-owning attribution when B uses A's purchase. The earlier P0 recommendation of company-segregated consumption was a proposal and is superseded, not an approved invariant.
- **Impact:** Incorrect availability, project cost attribution and company reporting despite nonnegative physical stock.
- **Recommended mitigation:** P2/P3 must define the simplest explicit and auditable link between source company/purchase and consuming company/project, preserving pooled physical availability. No enterprise transfer/settlement engine is authorized. Any need for reservations or a financial reattribution policy beyond this direction is surfaced separately; cost meaning remains GAP-004 and visibility GAP-006.
- **Impact on planning:** This direction permits cross-company use with attribution; it does not settle a valuation algorithm or internal settlement. Record the distinction before inventory/report design.
- **Owner:** Planner for technical/domain formalization; owner only for new financial/ownership decisions. **Affected:** P2/P3 rules, P4 stock design, P5 scope, P6 concurrency, inventory/project-cost units. **Status rationale:** OPEN for unbuilt safeguards, not a request to decide pooling again.

## GAP-004 — Exact arithmetic does not define managerial financial meaning

- **Description/evidence:** P1 Cost vs Cash-Out explicitly settles the distinction: acquisition/direct cost supports profitability; cash-out records actual disbursement, not purchase creation. Remaining recognition, landed/source-cost allocation, DP/credit handling and rounding are not fully defined. SPJ/SIPLAH metadata must not silently become revenue.
- **Impact:** Plausible-looking but misleading margin, receivables and cash reports; opening balances and payment allocations can disagree.
- **Recommendation:** During P2 obtain a small metric dictionary with concrete examples for agreed/invoiced/collected revenue, landed/source/direct cost attribution, DP/customer credit, taxes/fees/VA adjustments, rounding and currency. REF PDF #39–40 and the image add an ambiguity: "Receivable Created" follows billing, while unbilled receivables must remain visible. Confirm obligation recognition, due-date/aging basis and rebilling/cancellation effects without treating issue/submission/payment as the same event. Exceptional debt resolution links to GAP-022. Preserve the settled cost/cash distinction; do not ask whether purchase creation counts as payment again.
- **Alternatives / scope-time-risk:** A simpler report limited to evidenced inflows/outflows versus broader project profitability. Removing a requested report or changing recognition requires owner agreement. Avoid a general ledger; determining whether supplier settlement evidence is required is a scope decision, not permission to invent payables/accounting.
- **Owner:** **Owner — OWNER DECISION REQUIRED**. **Affected:** P1 report acceptance, P2 rules, P4 financial model, P8 fixtures, payment/cost/report units.

## GAP-005 — Cancellation may encounter delivered, billed or paid downstream records

- **Description/evidence:** P1 Correction Semantics now mandates preserved history, reason and audit after downstream effects. Partial delivery/payment/returns still require detailed compatible correction paths; blind cascading cancellation could restore stock twice or leave money allocated to a void invoice.
- **Impact:** Broken audit chain, wrong receivables and duplicated stock/customer credit.
- **Recommended mitigation:** P3 develops a concise correction matrix within the confirmed revision/void/reversal/return/reallocation principle. Preserve linked reasons/history; identify any changed financial meaning or user responsibility for owner decision rather than assuming it. Do not design that state machine in P1.
- **Alternatives / scope-time-risk:** More restrictive correction permissions or more self-service correction paths change user responsibilities and UX. Both require owner choice; the matrix avoids implementing unrelated generalized workflow machinery. No legal/tax treatment is asserted.
- **Owner:** Planner; owner if a specific proposed path changes business meaning/responsibility. **Affected:** P3 workflows, P4 references, P5 capabilities, P6 transitions, correction units. **Status rationale:** OPEN; the general correction principle is settled, detailed design/testing is not.

## GAP-006 — Global masters can leak company-specific relationships and prices

- **Description/evidence:** P1 explicitly settles default deny, owner full access and admin access only to granted companies, including manipulated URLs/IDs/bodies/queries. One pooled stock location still raises an unresolved product question: what operational availability/master information may be shared without revealing another company's sensitive quantities, purchases, prices or history? Lists/counts/files/exports can otherwise bypass the intended boundary.
- **Impact:** Cross-company disclosure or accidental denial of needed operational access.
- **Recommendation:** Keep sensitive transactions/history denied outside granted companies. In P2/P5 ask only for the unresolved shared-master/availability disclosure granularity needed for pooled-stock operation, then formalize the resource/field matrix. Do not infer that physical pooling grants all companies' data. Carry checks through jobs, caches, searches, reports and files after revocation.
- **Alternatives / scope-time-risk:** Global operational visibility versus selective master sharing changes business access and support burden; do not silently select either. Matrix precedes authorization schema and denial tests.
- **Owner:** **Owner — OWNER DECISION REQUIRED** for visibility; planner handles enforcement. **Affected:** P2 ownership, P5 permissions/security, P6 cache/jobs, all lists/search/download/export units.

## GAP-007 — An issued database document may have no matching PDF

- **Description/evidence:** Brief §§14, 31, 35 require immutable issued records and slow rendering outside transactions. A process can commit issue/number allocation then crash before queue publication; retries or later logo changes can produce no file, duplicate work or a PDF differing from the issued snapshot.
- **Impact:** User cannot print/send an issued document, or different files claim the same number/version.
- **Recommended mitigation:** Design one durable snapshot/version identity, distinct artifact readiness, immutable asset references/content, and recoverable rendering keyed to that version. Keep issue transactions short. Choose the simplest durable pending-work/reconciliation mechanism; justify an outbox only if simpler recovery cannot meet the contract. Test crash-after-commit, missing queue, retry and missing asset; never silently reissue/renumber to repair a PDF.
- **Owner:** Planner (Level 1); any change in legal issue timing goes to GAP-005/owner. **Affected:** P4 document model, P6 jobs/idempotency, P8 failure tests, P9 file recovery; document-engine units.

## GAP-008 — Retry safety is undefined after a lost response or key reuse

- **Description/evidence:** Brief §28 requires idempotency, but two keys for one semantic action, one key reused with different input, an expired key, or success followed by a lost response can still duplicate payments/stock. Replaying a stored response after access revocation can disclose data.
- **Impact:** Duplicate financial/stock effects or unauthorized result disclosure despite an idempotency table.
- **Recommended mitigation:** Define command-specific key scope, canonical payload fingerprint, uniqueness of the business effect, atomic result/mutation boundary, in-flight handling, retryable failure behavior and retention. Reauthorize replays; mismatch is a conflict, not success. Derive retention from the real retry horizon and preserve durable business uniqueness beyond key expiry. Test timeout-after-commit and simultaneous duplicate/different-payload requests.
- **Owner:** Planner (Level 1); no specific mechanism installed. **Affected:** P4 constraints, P6 protocol, P8 concurrency/integration tests; receiving/dispatch/payment/issue units.

## GAP-009 — Dispatch locks alone do not protect stock counts and serial scans

- **Description/evidence:** Brief §§5–6, 27 include opname, adjustments and serials. A count recorded before a concurrent receipt/dispatch may overwrite newer stock if applied as an absolute number. Repeated scanner input or receiving the same serial twice can corrupt the movement trail while quantity remains nonnegative.
- **Impact:** Silent balance/ledger divergence and impossible serial locations.
- **Recommended mitigation:** Plan one consistent balance/ledger mutation boundary across movement types. Record count basis/version and detect stale reconciliation; never overwrite current balance directly. Enforce agreed serial/barcode uniqueness, ambiguity handling and ledger reconciliation. Test count-versus-dispatch, return-versus-dispatch, opposite lock orders and duplicates. REF partial/damaged receiving, allocation and delivery add: dispatch plus delivery must not subtract stock twice; returned/damaged quantities must not become usable through a label change. Source attribution depends on GAP-003; reservation/eligibility policy on GAP-023.
- **Owner:** Planner for safeguards; owner for unresolved stock semantics. **Affected:** P2/P3 stock rules, P4 constraints, P6 locks, P8 concurrency tests; opname/barcode/serial/dispatch units.

## GAP-010 — Opening stock and open receivables can be counted twice at migration

- **Description/evidence:** Brief §45 permits current masters, opening stock, open projects and open receivables. Replaying imported purchases/receipts alongside an opening balance duplicates stock; creating a fresh full invoice alongside an imported unpaid remainder duplicates debt. Live operations during import create a moving baseline.
- **Impact:** Wrong day-one quantities, aging, payment allocation and reports even when every imported row is individually valid.
- **Recommended mitigation:** Identify source snapshot/cutoff, stable import identity and rerun behavior; separate historical references from current effects. Use staging validation/quarantine, batch checkpoints, row counts/control totals by company and reconciliation of files. Require an isolated dry run and bounded resume/abort procedure. Propose a cutover freeze or explicit delta reconciliation for owner agreement; do not silently stop client operations. Keep raw records private.
- **Owner:** Planner plus business data steward; owner if cutover workflow/financial interpretation changes. **Affected:** P4 import compatibility, P8 reconciliation tests, P10 release/migration; opening-balance and import units.

## GAP-011 — A database restore alone cannot recover usable business documents

- **Description/evidence:** P1 Recovery Target fixes RPO ≤24 hours and RTO ≤4 hours on the existing VPS. A restore still needs consistent files/asset versions, recoverable keys and safe job resumption. Retention, offsite resources, current free space and the production recovery operator remain unconfirmed.
- **Impact:** Data loss, unverifiable issued documents, duplicate operations or a nominal restore that cannot resume business.
- **Recommended mitigation:** Design and rehearse coordinated database/files/config recovery and job reconciliation against the agreed targets. Verify retention, human-held key/access recovery and an offsite copy. Nominal bandwidth/disk specifications do not demonstrate restore time or an independent backup.
- **Scope/time/risk:** No new availability promise or relaxed target; resource/cost selection is GAP-018. Keep one-VPS recoverability distinct from HA and production credentials with humans.
- **Owner:** Planner and human operator; owner only for changed tolerance/resources. **Affected:** P9 recovery/ops, P10 release, document/file/job designs. **Status rationale:** OPEN for proof, not unresolved RPO/RTO.

## GAP-012 — Rolling code changes may leave workers executing incompatible jobs

- **Description/evidence:** Laravel/queue/Compose are only a baseline; no deployment design exists. A worker from the old version can run against changed schema or payload while HTTP processes run new code. Code rollback does not undo recorded stock or issued documents.
- **Impact:** Partial outages, silent corruption or irreversible changes after a superficially successful rollback.
- **Recommended mitigation:** Plan version-compatible migrations/jobs, bounded worker drain/restart, health/smoke gates, schema/commit identification and a human-run abort/recovery decision. Separate code rollback from data recovery. Rehearse interrupted migration/deploy on staging; add tooling only if this simple sequence cannot meet the agreed outage tolerance.
- **Owner:** Planner (Level 1); new infrastructure/cost or changed outage tolerance requires owner decision. **Affected:** P9 topology, P10 runbook, migration/job units.

## GAP-013 — Successful HTTP health checks can hide stalled business processing

- **Description/evidence:** Existing backup/health requirements do not yet assign an alert recipient or define queue-age, failed-job, disk, database-connection, storage and balance-reconciliation signals. Cache/queue unavailability may coincide with a committed business action.
- **Impact:** Missing documents, unavailable sessions or failing backups persist unnoticed; blind retries can worsen an outage.
- **Recommended mitigation:** Define a small actionable signal set and human response owner, correlation across request/job/business action, redacted logs, retry limits and re-drive procedure. Verify alerts reach the intended operator using a staging failure drill. Define per-dependency behavior; do not pretend a committed command rolled back because a cache or notification failed. Avoid introducing a new observability platform unless justified.
- **Owner:** Planner for controls; owner names the accountable operator. **Affected:** P6 dependency contracts, P9 operations, P10 release gate; jobs/storage/health units.

## GAP-014 — Eighteen days is a target, not a measured delivery capacity

- **Description/evidence:** At P1 entry there is no application. C2 confirms Owner and Admin Operasional as UAT/migration validators, but review hours and execution/review capacity are unknown. C1 expands required generated outputs to the bounded 14-type recommendation; representative template/data evidence and implementation estimates are still needed.
- **Impact:** Feature-complete claims hide unusable documents, dirty opening data or missing operational readiness on 15 October.
- **Recommended mitigation:** Use the named validators; secure review slots and representative sanitized data/templates early. Include all 14 outputs, two device contexts, failure tests, migration and restore rehearsal in later effort estimates. No feature cut or shortened testing is authorized by an unproven deadline. Escalate a concrete scope/date tradeoff only when evidence supports it; detailed schedule remains P10.
- **Alternatives / scope-time-risk:** Owner-approved narrower scope versus a changed target. Neither is chosen here; removing requested capabilities or weakening integrity/testing to hit the date is prohibited. No fabricated day estimates are supplied without task/data evidence.
- **Owner:** Planner tracks feasibility; Owner/Admin confirm review availability. Any actual scope/date tradeoff is OWNER_DECISION_REQUIRED. **Affected:** P1 scope, P7 documents/flows, P8 UAT, P10 critical path. **Status rationale:** OPEN for missing capacity evidence; validator identity is settled.

## GAP-015 — Scanner/session behavior can create false success or duplicate intent

- **Description/evidence:** Brief §§6, 22 require USB scanning and usable mobile flows. A scanner may send Enter while a dialog/form is in the wrong state; an expired session or response timeout may invite a second submit. Two admins can edit the same draft without seeing a conflict.
- **Impact:** Wrong product/quantity, duplicate operations, lost edits or operator belief that an uncommitted action succeeded.
- **Recommended mitigation:** Plan explicit scan target/resolution, duplicate/unknown/ambiguous feedback, visible committed/pending/failed outcomes and stale-edit conflict handling. Exercise refresh/back, expired login, slow network and retry using actual scanner behavior during UAT. Do not assume offline writes or native mobile scope. Any proposed material workflow change is flagged under Level 2.
- **Owner:** Planner for safeguards, operator for device observations. **Affected:** P7 interaction patterns, P8 E2E, barcode/forms/document/payment units.

## GAP-016 — Event time and business date may cross different numbering periods

- **Description/evidence:** Payments have business dates; issue sequences have periods. A retry/job after midnight or a backdated correction may use a different period/date than the committed record if calculated twice. The business timezone/period policy is not confirmed by the developer workstation timezone.
- **Impact:** Inconsistent report boundaries, overdue status, document numbering and audit sequence.
- **Recommended mitigation:** Preserve event timestamp and business date separately where needed; choose a single committed period basis and reuse it in rendering/retries. Ask for the business timezone and allowed backdating/date-change rules during P2 if not already confirmed. Test midnight, month/year changes and replay with controlled clocks. Financial rounding policy remains GAP-004.
- **Owner:** Planner for technical consistency; owner for date/numbering meaning. **Affected:** P2 rules, P4 types, P6 sequences, P8 boundary tests; billing/payment/document units.

## GAP-017 — Green checks may miss the actual concurrency and workload risks

- **Description/evidence:** Tools are named in brief §§40–43, but no executable test environment, workload or assertions exist. Sequential mocks cannot prove stock-1 concurrency; small fixtures cannot expose 100x query growth; UI-only checks can miss unauthorized exports/files.
- **Impact:** False confidence before release and defects discovered by the first real concurrent operators.
- **Recommended mitigation:** Specify real PostgreSQL transaction/concurrency assertions, lost-response and worker-crash tests, cross-company denials across every output channel, ledger/payment control totals and a fresh CI environment. Collect record/concurrency/file-size estimates, then set measured query/latency/job targets and realistic generated fixtures. Verify tool availability; a missing optional tool does not erase deterministic tests. Map every critical invariant/gap to an assertion and future unit, not a badge saying tested.
- **Owner:** Planner plus executor/reviewer. **Affected:** P6 performance, P8 strategy/targets, P11 unit contracts and verification command; critical operational pages/actions.

## GAP-018 — Near-zero cost does not yet establish an independent recoverable backup

- **Description/evidence:** P1 requires near-zero incremental monthly infrastructure cost and offsite recovery within agreed targets. C3 reports 4 vCPU, 16 GB RAM, 200 GB NVMe and 16 TB bandwidth, but does not identify an off-VPS backup destination, free disk, utilization or transfer speed. A copy on the same VPS fails with that VPS.
- **Impact:** False Rp0 feasibility or failure to restore after host/storage loss; an unapproved service may become a hidden subscription.
- **Recommendation:** Owner/operator identifies existing independent storage, access/key custody and capacity/availability. Prefer a suitable already-owned destination if P9 can prove targets. Do not select a paid service, unsuitable free tier or reduced backup coverage silently.
- **Alternatives / scope-time-risk:** Reuse a verified existing destination (Rp0 incremental if truly already available); if absent/inadequate, bring a concrete low-cost option with estimated recurring price, free/self-hosted alternative, operational benefit and mandatory/optional classification. No vendor or price is proposed before resource facts. Recovery cannot be declared feasible yet.
- **Owner:** **Owner — OWNER_DECISION_REQUIRED** for destination/resources or any new recurring cost; planner/operator verify. **Affected:** CAP-16, DEP-01/02, P9 recovery/cost, P10 go-live. **When:** Before recovery design is accepted and release resources committed.

## GAP-019 — Complete selectable documents need bounded coverage and validated content

- **Description/evidence:** C1 requests a complete recommended set selectable by Owner/Admin. The planner has bounded this to DOC-01–14 in [V1_SCOPE](../01-product/V1_SCOPE.md#selectable-document-catalog), covering the business-document examples, with authentic external evidence as attachments. Actual client wording, required fields and acceptable layouts are still unvalidated.
- **Impact:** Unlimited template expectations or a nominal PDF feature that cannot satisfy real client submissions; generated records might incorrectly imply payment, inspection or external issuance.
- **Recommended mitigation:** Keep one maintained baseline per listed type with reusable data and selectable outputs, no new workflow per layout. REF PDF #34/image list external client SPK/order and third-party HPS; C1 generation breadth is retained for company-prepared outputs with truthful issuer/draft identification. Client-issued originals remain uploads and cannot be satisfied by a company draft. Owner/Admin validate issuer, samples, clauses and actual event/input requirements; generation alone creates no purchase/payment/warehouse effect. A requested change to issuer or business effect requires owner decision; routine content validation remains OPEN. Extra client variants are assessed explicitly rather than unlimited.
- **Owner:** Planner organizes coverage; Owner/Admin validate business content. **Affected:** CAP-08/09, AC-08/09, P3 document applicability, P7 layouts, P8 UAT, P10 capacity. **Status rationale:** OPEN; breadth is settled through delegated recommendation, runtime/content acceptance is not. No need to ask again whether BAST/SPK are included.

## GAP-020 — Mobile-first and existing USB scanners do not prove device compatibility

- **Description/evidence:** P1 strengthens mobile tasks and desktop bulk/keyboard productivity. USB scanners exist, but phone/browser/input focus/connection and printer/media behavior have not been observed. A single shrunken table or camera assumption would fail the required operational experience.
- **Impact:** Stock/document work unusable on the actual device, mistaken scan submits or slow desktop entry despite a responsive label.
- **Recommended mitigation:** P7 defines separate responsive patterns and supported device/input evidence; P8 tests a representative phone, desktop/scanner and print output. Keep practical manual SKU/product lookup and safe feedback; no offline/native-app or camera promise. P7 cannot PASS by demonstrating only one device context.
- **Owner:** Planner and Admin Operasional; owner only if an actual scope/hardware-cost tradeoff emerges. **Affected:** CAP-03/14, DEP-03/09, AC-03/14/20, P7/P8. **When:** Before P7 acceptance and device UAT. **Status:** OPEN; no device tests have run.

## GAP-021 — Treating the reference journey as mandatory would create dummy transactions

- **Description/evidence:** The linear reference flow includes purchase/warehouse/delivery, while OB explicitly supports services, existing inventory and drop-ship. Forcing every project through every step would require fictitious purchases or receipts.
- **Impact:** Unnecessary data entry and false stock/cost/cash records; expanded workflow complexity.
- **Mitigation/evidence:** Level 1 clarification in [PRODUCT_OVERVIEW](../01-product/PRODUCT_OVERVIEW.md#reference-business-journey) makes the journey contextual and AC-20 explicitly covers service-only and existing-stock paths without fake records. P2/P3 still define exact applicability, not P1.
- **Owner:** Planner; no business capability removed or new module added. **Affected:** CAP-04/06, AC-20, later P3 workflows. **Closure:** CLOSED in product planning; application verification remains outstanding in P8/execution.

## GAP-022 — Completion can hide unresolved obligations or become stale after correction

- **Description/evidence:** DIR-008 and PDF §§6.12–7 require aggregate completion across fulfillment, delivery, documents/admin, billing/receivable, returns, stock/payment corrections and audit. Earlier P1 only named completion in AC-20. "Formally closed", "waived" and "approved resolution" do not identify who may authorize each exception, what evidence is needed, or how a later return/payment correction affects a completed project.
- **Impact:** Premature closure, hidden debt or missing client documents; a completed badge contradicts reopened stock/payment obligations.
- **Recommendation:** Preserve all ten conditions in CAP-18/AC-21 now. In P2/P3 have Owner confirm exception authority, permitted reasons/evidence, treatment of unresolved balances and completion revalidation/reopening after later changes. Recommend explicit reason/approval and visible residual obligations; no silent write-off or automatic waiver. Planner chooses technical consistency only after policy is known.
- **Alternatives / scope-time-risk:** Owner-only exception approval versus selected admin capabilities changes operational responsibility; neither is selected. A single truthful checklist and existing audit/history can support the required outcome without a rules engine. Unknown policy blocks dependent rules, not P1 reference ingestion.
- **Owner:** **Owner — OWNER DECISION REQUIRED**; planner formalizes approved rules. **Affected:** CAP-09/10/18, AC-09/10/21, P2/P3/P5, completion/correction UAT. **When:** Before dependent workflow/permission design and completion implementation. Severity CRITICAL; probability UNKNOWN, not a measured incident.

## GAP-023 — Available stock does not specify reservation or usable-stock policy

- **Description/evidence:** Image stage 2 says "Reserve / Allocate Stock"; PDF §5 says "Allocate / Continue". PDF #24 also requires partial/damaged receiving. Prior P1 omitted allocation and did not distinguish physical, usable, allocated and available quantities. It is unclear whether assigning a project exclusively reserves supply, when it releases, and how damaged/returned goods qualify for use.
- **Impact:** Two projects promised the same supply, stranded reservations, damaged stock dispatched, misleading restock warnings or migration of incompatible opening allocations.
- **Recommendation:** Preserve simple project allocation and quantity distinctions in CAP-06/AC-22. P2/P3 asks Owner to choose advisory assignment versus exclusive reservation, release/cancellation responsibility, shortage/mixed-source handling and usable/damaged/returned-stock policy. P4/P6 then protect chosen invariants under concurrent allocation/dispatch/returns and source attribution. Do not invent timed holds or an enterprise inventory subsystem.
- **Alternatives / scope-time-risk:** Advisory allocation is simpler but does not promise exclusive availability; exclusive reservation needs reliable release/reallocation and additional concurrency cases. Both preserve one physical warehouse. Business promises determine the choice; nonnegative stock and traceable movements remain mandatory either way.
- **Owner:** **Owner — OWNER DECISION REQUIRED** for policy; planner for safe implementation design. **Affected:** CAP-05/06/17, AC-05/16/22/23, P2/P3/P4/P6, P8 race tests and P10 opening allocation reconciliation. **When:** Before dependent stock rules/design. Severity HIGH; probability UNKNOWN.

## GAP-024 — Broad capability groups can hide missing reference requirements before execution

- **Description/evidence:** Full inspection found explicit rack/minimum-stock/export/alerts, aggregate completion and module/release evidence absent or incomplete in the prior P1 synthesis. Eighteen CAP groups still compress 62 PDF capabilities, image branches, 14 output types and cross-cutting requirements; a single broad CAP→AC link cannot prove eventual build/test coverage.
- **Impact:** A required behavior disappears without an owner decision, or a module is called DONE based only on its screens.
- **Recommended mitigation:** [REFERENCE_COVERAGE](../01-product/REFERENCE_COVERAGE.md) records each REF/IMG requirement, before/after disposition and owning P1 contract. P2–P8 carry identifiers into actual rules/workflows/tests; P11 creates the reserved FEATURE_COVERAGE_MATRIX mapping requirement→canonical spec→rule/workflow→Build Unit→AC→required test before freeze. Include exact owner evidence for exclusion/deferment and reject unexplained holes. P8/units explicitly preserve all eight DoD dimensions, module integration and release evidence.
- **Owner:** Planner maintains coverage; executor/reviewer verify actual mappings and evidence. **Affected:** All phases, P8 quality, P10 release, P11 units. **When:** Mapping checks at each phase; complete before freeze. **Status rationale:** OPEN; P1 inventory is complete but later units/tests do not yet exist. Severity HIGH; probability POSSIBLE.

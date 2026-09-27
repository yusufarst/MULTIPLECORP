# Proactive gap register

Status: REVIEW | Updated: 2026-09-27 | Owner: Planning

This is the single index of discovered gaps, risk treatment and pending owner decisions. Findings below come from the two [owner sources](SOURCE_OF_TRUTH.md#authority-and-provenance) and the actual P0 documents. They are planning risks, not claims of vulnerabilities in an application that does not yet exist. Recommendations are proposals until incorporated under [decision authority](CHANGE_CONTROL.md#decision-authority).

## Operating rules

Keep only meaningful failure scenarios. Every entry identifies area, evidence/description, severity, probability, impact, when it matters, mitigation, status, responsible role/decision owner and affected phase or build units. Severity expresses consequence: BLOCKER stops the named gate, CRITICAL threatens serious security/data integrity or recovery, HIGH threatens core operation, MEDIUM causes material disruption, LOW is limited. Probability is qualitative: OBSERVED, LIKELY, POSSIBLE or UNKNOWN; these are reasoned judgments, not measured incident rates.

Statuses: OPEN (unresolved); MITIGATED (evidence of reduced risk, residual risk stated); OWNER_DECISION_REQUIRED (Level 2/3 choice pending); ACCEPTED_RISK (explicit authorized risk acceptance with reason and review trigger); DEFERRED (scope/dependency reason and revisit gate); CLOSED (resolution evidence). A mitigation proposal or planned test alone is not mitigation evidence. Do not accept away non-negotiable safeguards or defer a dependency beyond its build/release gate. Changing risk tolerance requires the owner.

Add findings throughout planning/execution; review at every phase end and new discovery. Move actual rules into their canonical concern, link evidence here and avoid creating a second risk register. Build-unit IDs do not yet exist; phase/domain references below must be mapped to real unit IDs in P11.

## Triage

| ID | Area | Severity | Probability | Status | Resolve before |
| --- | --- | --- | --- | --- | --- |
| GAP-001 | Delegated technical authority | HIGH | OBSERVED | CLOSED | P0 handoff |
| GAP-002 | Durable repository handoff | HIGH | OBSERVED | OPEN | Another checkout/agent takeover |
| GAP-003 | Shared warehouse ownership | CRITICAL | UNKNOWN | OWNER_DECISION_REQUIRED | P2 rules / P4 stock keys |
| GAP-004 | Financial meaning and rounding | CRITICAL | UNKNOWN | OWNER_DECISION_REQUIRED | P2 rules / financial schema |
| GAP-005 | Downstream corrections | HIGH | POSSIBLE | OWNER_DECISION_REQUIRED | P3 transition design |
| GAP-006 | Shared masters and data visibility | CRITICAL | UNKNOWN | OWNER_DECISION_REQUIRED | P2 ownership / P5 policies |
| GAP-007 | Issued document and file consistency | HIGH | POSSIBLE | OPEN | P4/P6 issue design |
| GAP-008 | Retry after uncertain completion | CRITICAL | POSSIBLE | OPEN | P4/P6 command design |
| GAP-009 | Stock count, dispatch and scanning races | CRITICAL | POSSIBLE | OPEN | P3/P4/P6 inventory design |
| GAP-010 | Migration opening-state overlap | CRITICAL | UNKNOWN | OPEN | P4 model; cutover in P10 |
| GAP-011 | Recoverable state and recovery tolerance | CRITICAL | UNKNOWN | OWNER_DECISION_REQUIRED | P9 plan; release gate |
| GAP-012 | Deployment and mixed worker versions | HIGH | POSSIBLE | OPEN | P9/P10 deployment plan |
| GAP-013 | Failure detection and operational ownership | HIGH | POSSIBLE | OPEN | P9 operations; release gate |
| GAP-014 | Deadline, capacity and business acceptance | HIGH | UNKNOWN | OWNER_DECISION_REQUIRED | P1 feasibility; P10 commitment |
| GAP-015 | Stale/expired operational UI and scans | HIGH | POSSIBLE | OPEN | P7 interactions / P8 E2E |
| GAP-016 | Dates, period boundaries and audit ordering | HIGH | POSSIBLE | OPEN | P2/P4/P6 date contracts |
| GAP-017 | Evidence, workload and independent verification | HIGH | UNKNOWN | OPEN | P6/P8 targets; P11 units |

No unresolved finding blocks this P0 extension. Open findings block only their named dependent decisions/build or release gates. Prioritize GAP-003/004/006 and GAP-014 during early planning; do not defer their meaning until code exists.

## GAP-001 — Blanket approval rules could preserve a known-bad plan

- **Description/evidence:** Original P0 change control required owner approval for every substantive technical change; source governance instructed executors to keep using the approved revision until a replacement was approved. That could preserve an unsafe revision and contradict the new delegation.
- **Impact:** Avoidable waiting, unsafe execution and provider-dependent interpretation of authority.
- **Mitigation/evidence:** Applied TECH-001: [change control](CHANGE_CONTROL.md#decision-authority) now distinguishes three levels; [lifecycle](SOURCE_OF_TRUTH.md#document-lifecycle) and [feedback loop](AGENT_OPERATING_MODEL.md#executor-feedback-loop) permit direct technical correction and pause only unsafe/dependent work. No business approval was invented.
- **Owner:** Planner; no owner decision required. **Affected:** All planning phases, execution feedback. **Closure:** Document consistency check and authority examples recorded in the P0 extension review.

## GAP-002 — Local documents alone cannot support another checkout

- **Description/evidence:** At the start of this extension all P0 files were untracked on unborn `main`; no remote checkpoint contained them. Losing the workstation or switching agents to another checkout would lose the canonical context.
- **Impact:** Lost decisions, conflicting baselines or agents accidentally rebuilding the plan from chat.
- **Recommended mitigation:** Create a local documentation checkpoint after validation; inspect tracked files for sensitive content before any publication. Share an identified commit through an authorized repository workflow before remote handoff. Require the receiver to confirm that commit plus CURRENT_STATE. A local commit alone does not supply an off-machine copy.
- **Owner:** Planner maintains local checkpoint; owner/repository maintainer controls publication as needed. **Affected:** P0 handoff and every agent replacement. **Residual/closure:** See CURRENT_STATE for actual commit/share state; keep OPEN until a receiving checkout can obtain the same checkpoint.

## GAP-003 — One physical warehouse does not define who owns available stock

- **Description/evidence:** Brief §§4–5 require company traceability and a shared location but do not decide whether Company B may consume stock purchased by A. A single quantity can look sufficient while B has no entitlement; reserving stock for two projects adds another ambiguity.
- **Impact:** Incorrect availability, project cost attribution and company reporting despite nonnegative physical stock.
- **Recommendation:** Confirm company ownership separately from physical location; proposed default is purchase-company ownership with no implicit cross-company consumption. Decide whether project allocation/reservation is required for launch. Do not add a cross-company transfer engine as a silent workaround.
- **Alternatives / scope-time-risk:** Owner-confirmed group pooling needs explicit cost/attribution and access rules; controlled exceptional related projects need clear handling. The choice changes balance keys, availability checks and receiving/dispatch tests; choosing after migrations creates rework. No choice is implemented here.
- **Owner:** **Owner — OWNER DECISION REQUIRED**; planner prepares rules. **Affected:** P2 domain/rules, P4 balances/ledger, P5 scope, P6 locks, inventory and project-cost units.

## GAP-004 — Exact arithmetic does not define managerial financial meaning

- **Description/evidence:** Brief §§9, 13, 15–16 distinguish buy/sell/SPJ values, deposits, overpayments, expenses and managerial cashflow but do not define recognition, cost allocation or rounding. A purchase order/commitment is not evidence of cash paid; a customer DP is not automatically project profit. SPJ/SIPLAH metadata could otherwise leak into revenue.
- **Impact:** Plausible-looking but misleading margin, receivables and cash reports; opening balances and payment allocations can disagree.
- **Recommendation:** Have the owner approve a small metric dictionary with examples: agreed/invoiced/collected revenue, actual paid versus committed supplier cost, landed/direct cost allocation, DP/customer-credit allocation, taxes/fees, rounding stage and supported currency. Label source/recognition consistently; keep administrative values out of operational money calculations unless explicitly approved.
- **Alternatives / scope-time-risk:** A simpler report limited to evidenced inflows/outflows versus broader project profitability. Removing a requested report or changing recognition requires owner agreement. Avoid a general ledger; determining whether supplier settlement evidence is required is a scope decision, not permission to invent payables/accounting.
- **Owner:** **Owner — OWNER DECISION REQUIRED**. **Affected:** P1 report acceptance, P2 rules, P4 financial model, P8 fixtures, payment/cost/report units.

## GAP-005 — Cancellation may encounter delivered, billed or paid downstream records

- **Description/evidence:** Brief §§11, 14–15 allow revisions, voids and reassignment, but a project/invoice can have partial delivery, a payment or returned serials when corrected. Cascading cancellation could restore stock twice or leave allocated money against a void invoice.
- **Impact:** Broken audit chain, wrong receivables and duplicated stock/customer credit.
- **Recommendation:** Approve a concise correction matrix keyed by downstream state. Prefer explicit compensating actions with reasons and linked history; prevent silent cascades. Specify paid-invoice correction, payment reassignment/refund, partial returns, and when project company may change. Technical locks/uniqueness can then protect the agreed transitions.
- **Alternatives / scope-time-risk:** More restrictive correction permissions or more self-service correction paths change user responsibilities and UX. Both require owner choice; the matrix avoids implementing unrelated generalized workflow machinery. No legal/tax treatment is asserted.
- **Owner:** **Owner — OWNER DECISION REQUIRED**. **Affected:** P3 workflows, P4 references, P5 capabilities, P6 transitions, invoice/payment/stock correction units.

## GAP-006 — Global masters can leak company-specific relationships and prices

- **Description/evidence:** A group workspace and company-restricted admins coexist (brief §§3, 6, 8, 17). Shared products/clients do not determine whether supplier prices, client contacts, stock quantities, profit, documents, search counts or product-supplier history are shared. A scoped project page alone will not protect exports, autocomplete or background downloads.
- **Impact:** Cross-company disclosure or accidental denial of needed operational access.
- **Recommendation:** Owner approves a concise resource/field visibility matrix: genuinely shared masters versus company transactions/history and sensitive fields. Recommended baseline is scoped transactions and capabilities for cost/profit; shared labels only where approved. Define technical scope propagation and permission recheck for requests, jobs, caches, reports and files after revocation.
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
- **Recommended mitigation:** Plan one consistent balance/ledger mutation boundary across all movement types. Record count basis/version and detect stale reconciliation; do not overwrite current balance directly. Enforce agreed serial/barcode uniqueness scopes, scanner resolution ambiguity handling and ledger reconciliation. Test count-versus-dispatch, return-versus-dispatch, opposite lock orders and duplicates. Reservation/ownership behavior depends on GAP-003, not a technical assumption.
- **Owner:** Planner for safeguards; owner for unresolved stock semantics. **Affected:** P2/P3 stock rules, P4 constraints, P6 locks, P8 concurrency tests; opname/barcode/serial/dispatch units.

## GAP-010 — Opening stock and open receivables can be counted twice at migration

- **Description/evidence:** Brief §45 permits current masters, opening stock, open projects and open receivables. Replaying imported purchases/receipts alongside an opening balance duplicates stock; creating a fresh full invoice alongside an imported unpaid remainder duplicates debt. Live operations during import create a moving baseline.
- **Impact:** Wrong day-one quantities, aging, payment allocation and reports even when every imported row is individually valid.
- **Recommended mitigation:** Identify source snapshot/cutoff, stable import identity and rerun behavior; separate historical references from current effects. Use staging validation/quarantine, batch checkpoints, row counts/control totals by company and reconciliation of files. Require an isolated dry run and bounded resume/abort procedure. Propose a cutover freeze or explicit delta reconciliation for owner agreement; do not silently stop client operations. Keep raw records private.
- **Owner:** Planner plus business data steward; owner if cutover workflow/financial interpretation changes. **Affected:** P4 import compatibility, P8 reconciliation tests, P10 release/migration; opening-balance and import units.

## GAP-011 — A database restore alone cannot recover usable business documents

- **Description/evidence:** Brief §§37–39 require backups and human production control. Restored rows may reference missing files/asset versions; keys may be unavailable; old queue jobs may replay effects after recovery. RPO, RTO, retention and a capable human operator are not yet established.
- **Impact:** Data loss, unverifiable issued documents, duplicate operations or a nominal restore that cannot resume business.
- **Recommendation:** Owner chooses tolerable data loss, outage and retention with an identified operator. Propose coordinated database/files/config recovery evidence, secure human-held key recovery, offsite copies and job reconciliation before workers resume. Restore to isolation, reconcile balances/documents and prove the business flow within chosen targets.
- **Alternatives / scope-time-risk:** Simpler scheduled backup versus more frequent recovery points changes potential loss, infrastructure/cost and operator burden. Do not assert a default RPO/RTO or pretend one VPS is HA. Technical restore design follows the chosen tolerance; production credentials remain with humans.
- **Owner:** **Owner — OWNER DECISION REQUIRED** for tolerance/resources; human operator for execution; planner for procedure. **Affected:** P9 recovery/ops, P10 release, file/document/job designs and restore acceptance.

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

- **Description/evidence:** Initial repository is empty; staffing, review latency, sanitized migration samples and client document acceptance are unconfirmed. Waiting until P10 to expose these dependencies could leave no time for UAT or a restore rehearsal.
- **Impact:** Feature-complete claims hide unusable documents, dirty opening data or missing operational readiness on 15 October.
- **Recommendation:** During P1 identify the real operator/UAT availability, mandatory document examples, data steward and execution capacity; estimate the critical path with validation/recovery/cutover work included. Surface a minimum safe release and explicit deferral options for owner choice. Start evidence collection early while keeping detailed roadmap authorship in P10.
- **Alternatives / scope-time-risk:** Owner-approved narrower scope versus a changed target. Neither is chosen here; removing requested capabilities or weakening integrity/testing to hit the date is prohibited. No fabricated day estimates are supplied without task/data evidence.
- **Owner:** **Owner — OWNER DECISION REQUIRED** on acceptance priorities/capacity and any tradeoff. **Affected:** P1 scope, P7 documents/flows, P8 UAT, P10 critical path and release.

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

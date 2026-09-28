# Proactive gap register

Status: REVIEW | Updated: 2026-09-28 | Owner: Planning

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
| GAP-004 | Financial meaning and rounding | CRITICAL | UNKNOWN | OPEN | Business decision resolved; technical design/evidence at P2 rules / financial schema |
| GAP-005 | Downstream corrections | HIGH | POSSIBLE | OPEN | P3 transition design |
| GAP-006 | Shared masters and data visibility | CRITICAL | UNKNOWN | OPEN | Business decision resolved; technical design/evidence at P2 ownership / P5 policies |
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
| GAP-018 | Local-only backup: total VPS/storage loss exposure | CRITICAL | UNKNOWN | ACCEPTED_RISK | Decision resolved by DIR-009/RISK-001; policy changes only by explicit Owner decision |
| GAP-019 | Complete document catalog and content validation | HIGH | POSSIBLE | OPEN | P3/P7 documents / P8 UAT |
| GAP-020 | Mobile/scanner practicality and desktop productivity | HIGH | UNKNOWN | OPEN | P7 design gate / P8 device UAT |
| GAP-021 | Forced purchase/warehouse work for all projects | HIGH | POSSIBLE | CLOSED | P1 scope clarification |
| GAP-022 | Completion exceptions and subsequent corrections | CRITICAL | UNKNOWN | OPEN | Business decision resolved; technical design/evidence at P2/P3 completion rules / P5 permissions |
| GAP-023 | Allocation, reservation and usable stock | HIGH | UNKNOWN | OPEN | Business decision resolved; technical design/evidence at P2/P3 stock policy / dependent P4 design |
| GAP-024 | Reference requirements lost before build/test mapping | HIGH | POSSIBLE | OPEN | P8 test coverage / P11 freeze |

APPR-002 approves the P1 product/scope baseline. Remaining OPEN technical/evidence findings retain their later gates and do not reopen P1; release readiness remains separate. DIR-011 resolves all four P1 business decisions in GAP-004/006/022/023. Each is **OPEN for technical design/evidence only — BUSINESS DECISION RESOLVED**, not OWNER_DECISION_REQUIRED. Backup location and conditional recovery targets are settled by DIR-009; do not ask for offsite resources again. Later local-backup design/evidence must still satisfy GAP-011/013. Register totals: **24 findings — 2 CLOSED, 21 OPEN, 0 OWNER_DECISION_REQUIRED, 1 ACCEPTED_RISK**. No P1 business choice remains open. OPEN does not mean the settled choice must be asked again; it preserves the technical failure scenario until its design/evidence gate is satisfied.

## GAP-001 — Blanket approval rules could preserve a known-bad plan

- **Description/evidence:** Original P0 change control required owner approval for every substantive technical change; source governance instructed executors to keep using the approved revision until a replacement was approved. That could preserve an unsafe revision and contradict the new delegation.
- **Impact:** Avoidable waiting, unsafe execution and provider-dependent interpretation of authority.
- **Mitigation/evidence:** Applied TECH-001: [change control](CHANGE_CONTROL.md#decision-authority) now distinguishes three levels; [lifecycle](SOURCE_OF_TRUTH.md#document-lifecycle) and [feedback loop](AGENT_OPERATING_MODEL.md#executor-feedback-loop) permit direct technical correction and pause only unsafe/dependent work. No business approval was invented.
- **Owner:** Planner; no owner decision required. **Affected:** All planning phases, execution feedback. **Closure:** Document consistency check and authority examples recorded in the P0 extension review.

## GAP-002 — Local documents alone cannot support another checkout

- **Description/evidence:** At the start of this extension all P0 files were untracked on unborn `main`; no remote checkpoint contained them. Losing the workstation or switching agents to another checkout would lose the canonical context.
- **Impact:** Lost decisions, conflicting baselines or agents accidentally rebuilding the plan from chat.
- **Mitigation/residual:** Local P0 checkpoint `b425584` is accepted; approved P1 checkpoint `a740ed2ed893539bc02c4f95b538f90ae7ceb319` now exists with 25 reviewed files. Read-only inspection on 2026-09-28 confirms intended origin is accessible but has no advertised refs; the checkpoint remains unpublished. Share an identified commit through an authorized repository workflow before remote handoff; receiver confirms commit plus CURRENT_STATE. No push was performed; a local commit alone is not an off-machine copy.
- **Owner:** Planner maintains local checkpoint; owner/repository maintainer controls publication as needed. **Affected:** P0 handoff and every agent replacement. **Residual/closure:** See CURRENT_STATE for actual commit/share state; keep OPEN until a receiving checkout can obtain the same checkpoint.

## GAP-003 — One stock pool must preserve source and consuming-company attribution

- **Description/evidence:** P1 Warehouse now settles physical stock as one pool and requires explicit source/cost-owning attribution when B uses A's purchase. The earlier P0 recommendation of company-segregated consumption was a proposal and is superseded, not an approved invariant.
- **Impact:** Incorrect availability, project cost attribution and company reporting despite nonnegative physical stock.
- **Recommended mitigation:** P2/P3 must define the simplest explicit and auditable link between source company/purchase and consuming company/project, preserving pooled physical availability. No enterprise transfer/settlement engine is authorized. DIR-011 confirms explicit auditable inter-company stock allocation and excludes automatic inter-company invoices, formal journals and automatic cash transfers. Reservation, financial meaning and physical-versus-business visibility are settled; GAP-004/006/023 retain their technical obligations.
- **Impact on planning:** DIR-011 preserves source/acquisition provenance and managerial cost attribution without automatic settlement. A detailed valuation/representation algorithm remains P2/P4 formalization, not permission to reassign provenance. Record the distinction before inventory/report design.
- **Owner:** Planner for technical/domain formalization; owner only for new financial/ownership decisions. **Affected:** P2/P3 rules, P4 stock design, P5 scope, P6 concurrency, inventory/project-cost units. **Status rationale:** OPEN for unbuilt safeguards, not a request to decide pooling again.

## GAP-004 — Exact arithmetic does not define managerial financial meaning

- **Description/evidence:** DIR-011 explicitly settles separate sales/transaction value, active receivable, Cash-In, actual/direct Cost/HPP and Cash-Out; issue records value without payment, explicit billing activates the unpaid receivable with billed_at/due_date, and actual payment/disbursement creates the appropriate cash movement. The canonical contract is [V1_SCOPE](../01-product/V1_SCOPE.md#financial-concepts-and-billing), with AC-10/11 evidence.
- **Impact:** Implementing those meanings incorrectly can still misstate margin, debt, advances/credits, company allocation or cash; a recorded decision is not runtime mitigation.
- **Recommended mitigation:** P2/P4 formalize the metric/rule dictionary and representative numeric cases for transaction value, actual/direct source cost, prior/partial/excess payments, credit/refund/corrections, configurable taxes/fees/VA, currency/rounding and billing/aging dates. Preserve unbilled visibility and avoid duplicate Cash-In on billing or reallocation. No valuation/tax method or accounting engine is selected in P1. Validate the formalization against DIR-011; a genuinely new change to economic meaning follows change control, not an automatic reopening of the five settled concepts.
- **Owner:** Planner for technical formalization; Owner/Admin validate examples at the later gate. **Affected:** CAP-10/11, AC-10/11, P2 rules, P4 financial representation, P8 fixtures; dates GAP-016 and corrections GAP-005.
- **Resolution/status:** **BUSINESS DECISION RESOLVED by DIR-011. OPEN — TECHNICAL DESIGN TO BE COMPLETED IN P2/P4**, then proved in P8/execution. No current OWNER_DECISION_REQUIRED item; no reduced report scope or relaxed integrity.

## GAP-005 — Cancellation may encounter delivered, billed or paid downstream records

- **Description/evidence:** P1 Correction Semantics now mandates preserved history, reason and audit after downstream effects. Partial delivery/payment/returns still require detailed compatible correction paths; blind cascading cancellation could restore stock twice or leave money allocated to a void invoice.
- **Impact:** Broken audit chain, wrong receivables and duplicated stock/customer credit.
- **Recommended mitigation:** P3 develops a concise correction matrix within the confirmed revision/void/reversal/return/reallocation principle. Preserve linked reasons/history; identify any changed financial meaning or user responsibility for owner decision rather than assuming it. Do not design that state machine in P1.
- **Alternatives / scope-time-risk:** More restrictive correction permissions or more self-service correction paths change user responsibilities and UX. Both require owner choice; the matrix avoids implementing unrelated generalized workflow machinery. No legal/tax treatment is asserted.
- **Owner:** Planner; owner if a specific proposed path changes business meaning/responsibility. **Affected:** P3 workflows, P4 references, P5 capabilities, P6 transitions, correction units. **Status rationale:** OPEN; the general correction principle is settled, detailed design/testing is not.

## GAP-006 — Global masters can leak company-specific relationships and prices

- **Description/evidence:** DIR-011 settles default deny, Owner all-company/consolidated and attribution access, and server-side Admin company/capability grants. Inventory permission allows the physical availability necessary for shared-warehouse operations, not other-company business/financial access. [V1_SCOPE](../01-product/V1_SCOPE.md#physical-visibility-and-company-isolation) owns the exact boundary; AC-13 supplies positive and denial evidence.
- **Impact:** Related master/history, count, cache, export, API or file paths can still expose other-company purchase cost, profit, banks, invoices, billing, payments, financial records, private documents or unrelated transactions; denying all pooled visibility would instead block the approved operational use.
- **Recommended mitigation:** P2/P5 define the minimum permitted physical projection and per-resource/field grants without silently adding sensitive fields. Enforce it on URL/ID/query/payload/API/download, search/export, jobs/caches and revoked access. Physical visibility does not grant cross-company business mutations. Test the same permitted warehouse lookup alongside denied source-company financial/detail traversal.
- **Owner:** Planner for field mapping and server enforcement; reviewer for denial evidence. **Affected:** P2 ownership, P5 permissions/security, P6 cache/jobs, P8 tests and every output path.
- **Resolution/status:** **BUSINESS DECISION RESOLVED by DIR-011. OPEN — TECHNICAL DESIGN TO BE COMPLETED IN P2/P5/P6**, then proved in P8/execution. No unresolved shared-visibility policy question remains.

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
- **Recommended mitigation:** Plan one consistent balance/ledger mutation boundary across movement types. Record count basis/version and detect stale reconciliation; never overwrite current balance directly. Enforce agreed serial/barcode uniqueness, ambiguity handling and ledger reconciliation. Test count-versus-dispatch, return-versus-dispatch, opposite lock orders and duplicates. REF partial/damaged receiving, allocation and delivery add: dispatch plus delivery must not subtract stock twice; returned/damaged quantities must not become usable through a label change. Source attribution depends on GAP-003; DIR-011 settles reservation/eligibility, with technical proof under GAP-023. Add simultaneous reservation/reservation and reservation/dispatch cases, release/adjustment and damaged-stock transitions against the approved availability invariant.
- **Owner:** Planner for safeguards; owner for unresolved stock semantics. **Affected:** P2/P3 stock rules, P4 constraints, P6 locks, P8 concurrency tests; opname/barcode/serial/dispatch units.

## GAP-010 — Opening stock and open receivables can be counted twice at migration

- **Description/evidence:** Brief §45 permits current masters, opening stock, open projects and open receivables. Replaying imported purchases/receipts alongside an opening balance duplicates stock; creating a fresh full invoice alongside an imported unpaid remainder duplicates debt. Live operations during import create a moving baseline.
- **Impact:** Wrong day-one quantities, aging, payment allocation and reports even when every imported row is individually valid.
- **Recommended mitigation:** Identify source snapshot/cutoff, stable import identity and rerun behavior; separate historical references from current effects. Use staging validation/quarantine, batch checkpoints, row counts/control totals by company and reconciliation of files. Require an isolated dry run and bounded resume/abort procedure. Propose a cutover freeze or explicit delta reconciliation for owner agreement; do not silently stop client operations. Keep raw records private.
- **Owner:** Planner plus business data steward; owner if cutover workflow/financial interpretation changes. **Affected:** P4 import compatibility, P8 reconciliation tests, P10 release/migration; opening-balance and import units.

## GAP-011 — A database restore alone cannot recover usable business documents

- **Description/evidence:** DIR-009 settles same-production-VPS backup only. RPO ≤24h/RTO ≤4h apply as operational targets only when the VPS and local backup data remain recoverable. Consistent PostgreSQL/files/assets, usable multiple recovery points, retention/cleanup, scheduled/pre-deploy verification, periodic restore proof, safe job resumption, actual free disk and human operator responsibilities still need P9 evidence.
- **Impact:** Data loss, unverifiable issued documents, duplicate operations or a nominal restore that cannot resume business.
- **Recommended mitigation:** P9 defines and demonstrates BK-01–12 in [V1_SCOPE](../01-product/V1_SCOPE.md#v1-local-backup-and-p9-handoff-contract). Model PostgreSQL, uploads, images, PDFs, logs, exports/temp, Docker if used, backup growth/retention and transient backup/restore space on the same 200 GB nominal disk. Set numerical warning/critical thresholds, operating headroom and bounded cleanup from measured usage. Protect production data and required generations; surface failed/stale backups rather than hiding them. Rehearse scoped local restore and verify checksums plus business/file consistency; secrets and production operation remain with humans.
- **Scope/time/risk:** GAP-018 accepts total-host/storage-loss exposure only; it does not waive these local controls or justify a disk-full outage. No offsite resource is missing. Report evidence of a local implementation constraint here; under DIR-010 it does not reopen the offsite requirement unless Owner explicitly changes the decision.
- **Owner:** Planner and human operator; owner only for an evidenced constraint requiring a changed decision. **Affected:** CAP-16, AC-18/19, P9 recovery/disk budget, P10 release, document/file/job designs. **Status rationale:** OPEN for local design/testing proof, not an unresolved backup-location choice.

## GAP-012 — Rolling code changes may leave workers executing incompatible jobs

- **Description/evidence:** Laravel/queue/Compose are only a baseline; no deployment design exists. A worker from the old version can run against changed schema or payload while HTTP processes run new code. Code rollback does not undo recorded stock or issued documents.
- **Impact:** Partial outages, silent corruption or irreversible changes after a superficially successful rollback.
- **Recommended mitigation:** Plan version-compatible migrations/jobs, bounded worker drain/restart, health/smoke gates, schema/commit identification and a human-run abort/recovery decision. Separate code rollback from data recovery. Rehearse interrupted migration/deploy on staging; add tooling only if this simple sequence cannot meet the agreed outage tolerance.
- **Owner:** Planner (Level 1); new infrastructure/cost or changed outage tolerance requires owner decision. **Affected:** P9 topology, P10 runbook, migration/job units.

## GAP-013 — Successful HTTP health checks can hide stalled business processing

- **Description/evidence:** Existing backup/health requirements do not yet assign an alert recipient or define queue-age, failed-job, disk, database-connection, storage and balance-reconciliation signals. DIR-009 adds explicit local backup failure/staleness, retention cleanup and shared-disk pressure evidence; backup growth must not exhaust production space. Cache/queue unavailability may coincide with a committed business action.
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

## GAP-018 — Owner accepts total VPS or storage loss exposure with local-only backup

- **Description/evidence:** The previous offsite-resource decision is resolved. On 2026-09-27 the Owner explicitly reported the client's decision: V1 backups stay only on the existing production VPS; no offsite requirement. DIR-009 and RISK-001 in [DECISION_LOG](DECISION_LOG.md) preserve that authority and the [source message](sources/P1_OWNER_BACKUP_POLICY_2026-09-27.txt). Previous assumptions are superseded, not silently deleted from source history.
- **Impact / accepted exposure:** Local-only backup may not protect against total VPS loss, total disk failure, VPS deletion, provider-level loss, account compromise that destroys both production and backups, or catastrophic filesystem/storage failure affecting the whole VPS. Production and all local recovery points can be lost together. Recovery and a four-hour RTO are not guaranteed in these scenarios.
- **Mitigation boundary:** Same-VPS backups can support accidental-change, application-failure, failed-deployment, selected database-corruption and accidental-deletion recovery when a suitable backup remains intact and the VPS is recoverable. This reduces some operational failure consequences; it does not mitigate the accepted common-host loss exposure. RPO ≤24h/RTO ≤4h are targets only for that recoverable local scenario.
- **Decision / accepted risk:** **ACCEPTED_RISK**, accepted by Owner/requester on behalf of the client as expressly stated in the message, 2026-09-27 (RISK-001). This is an explicit risk-tolerance/location decision, not planner acceptance and not a runtime test result. External object storage, another server, NAS, external cloud backup or paid offsite service are not V1 requirements.
- **Residual required work:** BK-01–12 remain mandatory under GAP-011/013: database/files, pre-deploy/scheduled generations, rotation/retention, checksum verification, procedure/periodic restore tests, logging/failure detection, disk monitoring and safe automatic cleanup. No single continuously overwritten backup. Shared 200 GB capacity must be modeled and tested; disk-full production failure is not accepted by this decision.
- **Owner / review trigger:** Owner owns the policy and accepted exposure; planner/operator own local compliance evidence. DIR-010 reiterates: do not reopen the offsite requirement unless Owner explicitly changes the decision. New local implementation constraints belong to GAP-011, without automatic policy reconsideration or renewed offsite-resource requests. **Affected:** CAP-16, DEP-01/02, AC-18/19, OS-04, REF-060, P9/P10. **Status rationale:** Owner decision resolved; exposure remains visible as ACCEPTED_RISK rather than falsely CLOSED or MITIGATED.

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

- **Description/evidence:** DIR-011 settles system-evaluated normal completion, bounded permissioned Admin N/A for waiver-eligible administrative requirements, and Owner-only exceptional Force Complete. The [canonical completion contract](../01-product/V1_SCOPE.md#normal-completion-and-exceptional-override) and AC-09/21 preserve required confirmation, reason, actor, timestamp, before/after state and audit.
- **Impact:** An incorrectly implemented override can conceal debt, fake documents/stock/payment, let Admin bypass mandatory invariants, lose audit evidence, or leave a stale completed badge after a material correction.
- **Recommended mitigation:** P2/P3/P5 formalize normal-versus-forced outcome, waiver eligibility, authorization, atomic audit and revalidation after later changes. Keep unmet conditions and residual obligations visible; override alone does not settle debt or manufacture fulfillment/evidence. Test missing confirmation/reason/audit, forged Admin/API override, duplicate/concurrent requests and post-completion returns/corrections. No generalized rules engine is needed.
- **Owner:** Planner formalizes the approved authority; reviewer verifies denial/audit evidence. **Affected:** CAP-09/10/18, AC-09/10/21, P2/P3/P5/P6, completion/correction UAT.
- **Resolution/status:** **BUSINESS DECISION RESOLVED by DIR-011. OPEN — TECHNICAL DESIGN TO BE COMPLETED IN P2/P3/P5/P6**, then proved in P8/execution. No separate Owner choice between Admin and Owner override authority remains.

## GAP-023 — Available stock does not specify reservation or usable-stock policy

- **Description/evidence:** DIR-011 settles no reservation from a quotation alone; simple confirmed-project/order reservation; AVAILABLE = ON HAND - RESERVED - UNUSABLE; exclusion of damaged/rejected/quarantined stock; release/adjustment on cancellation, changed quantities or no further need. The [canonical stock contract](../01-product/V1_SCOPE.md#reservation-and-usable-stock) and AC-16/22 preserve the physical-movement and concurrency boundaries.
- **Impact:** Incorrect transitions can still double-promise final supply, strand reservations, subtract reserved/unusable quantities twice, dispatch quarantined goods, lose source attribution or import incompatible opening allocations.
- **Recommended mitigation:** P2/P3/P4/P6 define disjoint quantity accounting, confirmed-demand links, release/adjustment, reservation consumption on actual dispatch, condition/return transitions and atomic availability checks. Protect reservation/reservation, dispatch/dispatch and mixed races so two projects cannot both obtain the final supply. Preserve no physical stock-out from reservation, one physical effect on dispatch, and auditable source/consuming allocation. Do not introduce timed holds or an enterprise allocation engine.
- **Owner:** Planner for representation, transitions and concurrency evidence. **Affected:** CAP-05/06/17, AC-05/16/22/23, P2/P3/P4/P6, P8 race tests and P10 opening allocations.
- **Resolution/status:** **BUSINESS DECISION RESOLVED by DIR-011. OPEN — TECHNICAL DESIGN TO BE COMPLETED IN P2/P4/P6**, with P3 transitions and later tests. No advisory-versus-exclusive reservation choice remains open.

## GAP-024 — Broad capability groups can hide missing reference requirements before execution

- **Description/evidence:** Full inspection found explicit rack/minimum-stock/export/alerts, aggregate completion and module/release evidence absent or incomplete in the prior P1 synthesis. Eighteen CAP groups still compress 62 PDF capabilities, image branches, 14 output types and cross-cutting requirements; a single broad CAP→AC link cannot prove eventual build/test coverage.
- **Impact:** A required behavior disappears without an owner decision, or a module is called DONE based only on its screens.
- **Recommended mitigation:** [REFERENCE_COVERAGE](../01-product/REFERENCE_COVERAGE.md) records each REF/IMG requirement, before/after disposition and owning P1 contract. P2–P8 carry identifiers into actual rules/workflows/tests; P11 creates the reserved FEATURE_COVERAGE_MATRIX mapping requirement→canonical spec→rule/workflow→Build Unit→AC→required test before freeze. Include exact owner evidence for exclusion/deferment and reject unexplained holes. P8/units explicitly preserve all eight DoD dimensions, module integration and release evidence.
- **Owner:** Planner maintains coverage; executor/reviewer verify actual mappings and evidence. **Affected:** All phases, P8 quality, P10 release, P11 units. **When:** Mapping checks at each phase; complete before freeze. **Status rationale:** OPEN; P1 inventory is complete but later units/tests do not yet exist. Severity HIGH; probability POSSIBLE.

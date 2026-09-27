# Decision and approval log

Status: REVIEW | Updated: 2026-09-27 | Owner: Planning

This is an evidence index. Rules live in the linked owning documents. Entries distinguish directives, delegated technical improvements, proposals, observations and approvals. P0 governance is accepted; no approval of the full P1 synthesis, implementation or release is implied.

| ID | Date | Classification | Evidence and owning record |
| --- | --- | --- | --- |
| DIR-001 | 2026-09-27 | Owner directive | Plan only; inspect first and execute P0 only. [Brief](sources/OWNER_BRIEF_2026-09-27.txt), opening and §§55–57; [charter](PROJECT_CHARTER.md) |
| DIR-002 | 2026-09-27 | Owner directive | Repository continuity and one owner per concern. Brief §§1, 48–51; [source of truth](SOURCE_OF_TRUTH.md) |
| DIR-003 | 2026-09-27 | Owner directive | Distinct planner/executor responsibilities, approval gates, and controlled changes. Brief opening, §§50, 52–54; [operating model](AGENT_OPERATING_MODEL.md), [change control](CHANGE_CONTROL.md) |
| DIR-004 | 2026-09-27 | Owner directive | Integrity, security, production restrictions and evidence-based quality. Brief §§24–44, 58; [engineering principles](ENGINEERING_PRINCIPLES.md) |
| OBS-001 | 2026-09-27 | Verified observation | Public GitHub repository; configured default `main`; no remote refs or commits at inspection. [P0 inspection evidence](P0_QUALITY_GATE.md#repository-inspection) |
| PROP-001 | 2026-09-27 | Historical proposal; accepted by APPR-001 | P0 governance package and [ADR-001](../adr/ADR-001-repository-governance.md); proposal state preserved in `b425584`, acceptance recorded below |
| DIR-005 | 2026-09-27 | Owner directive and bounded delegation; effective | [Architect mandate](sources/ARCHITECT_MANDATE_2026-09-27.txt): Decision Authority, Proactive Gap Register, Continuous Red-Team Review, Executor Feedback Loop, Pre-Build Challenge and Pre-Mortem. Supersedes blanket approval barriers for Level 1 changes; preserves Level 2/3 owner decisions and planning-only role |
| TECH-001 | 2026-09-27 | Level 1 improvement applied by planner under DIR-005 | Corrected approval overreach and unsafe-revision fallback; introduced one gap register, recurring review, feedback and cross-module freeze gates. [Change control](CHANGE_CONTROL.md), [source governance](SOURCE_OF_TRUTH.md), [operating model](AGENT_OPERATING_MODEL.md); evidence below |
| TECH-002 | 2026-09-27 | Level 1 sequencing improvement applied by planner under DIR-005 | Discover feasibility, data and acceptance dependencies before their detailed design phases rather than waiting until P10. [GAP-014](GAP_REGISTER.md#gap-014--eighteen-days-is-a-target-not-a-measured-delivery-capacity). No scope cut or deadline change selected |
| APPR-001 | 2026-09-27 | Owner acceptance — P0 governance | [P1 directive](sources/P1_OWNER_DIRECTIVE_2026-09-27.txt), opening sentence; exact baseline and limits below |
| DIR-006 | 2026-09-27 | Owner directive — P1 only and binding product input | [P1 directive](sources/P1_OWNER_DIRECTIVE_2026-09-27.txt): identity/alias, Indonesian UI, mobile-first/desktop optimization, pooled stock with attribution, cost versus cash-out, corrections/access, RPO/RTO and near-zero incremental cost; canonical product concerns in `docs/01-product/` |
| DIR-007 | 2026-09-27 | Owner clarifications and bounded recommendation authority | [C1–C3](sources/P1_OWNER_CLARIFICATIONS_2026-09-27.txt): complete selectable document recommendation, Owner/Admin validation, nominal VPS capacity. Catalog treatment belongs to [V1_SCOPE](../01-product/V1_SCOPE.md#selectable-document-catalog); offsite backup remains unanswered |
| TECH-003 | 2026-09-27 | Level 1 continuity/clarification updates | Record P0 acceptance, reconcile old proposals with DIR-006/007, link 16 capabilities to 20 acceptance contracts, preserve later-phase boundaries and contextual service/existing-stock paths (GAP-021). No owner requirement removed, price/cost policy invented, package installed or application code written |
| DIR-008 | 2026-09-27 | Owner directive — supporting-reference ingestion; P1 only | [Exact request](sources/P1_REFERENCE_INGESTION_DIRECTIVE_2026-09-27.txt): full PDF/image inspection, exact copies/provenance, six-level conflict hierarchy, coverage, completion gate and eight-dimension DoD, no silent scope cuts; P1 remains REVIEW, no P2/code |
| TECH-004 | 2026-09-27 | Level 1 coverage/continuity correction under DIR-005/008 | [Reference comparison](../01-product/REFERENCE_COVERAGE.md) maps 62 PDF capabilities and all image branches; strengthens P1 to 18 CAP/23 AC, preserves 14 document types with truthful issuer treatment, adds completion/module/release evidence and future traceability gates. GAP-022/023 explicitly leave business policy to Owner; GAP-024 tracks later full mapping |

## Approval register

P0 governance specifications are APPROVED and ADR-001 is ACCEPTED under APPR-001. Current logs, risk/evidence records and handoff remain live records; their status does not accept risks. P1 deliverables are REVIEW. No approved implementation specification, planning freeze or production release approval exists. DIR-005/006 authorize Level 1 corrections without repeated approval.

## APPR-001 — P0 governance accepted

- **Approver/date:** Owner/requester, 2026-09-27. Evidence: exact source statement, “P0 has been accepted as the governance baseline.”
- **Reviewed baseline:** `b425584daa80af2c1342252407c95e08024d3173`, the last delivered P0 commit, verified as HEAD at P1 entry with a clean working tree.
- **Scope:** P0 governance/continuity conventions, entry points, charter, source ownership/lifecycle, operating model, change control, engineering guardrails and ADR-001. Supporting factual/source/risk records are part of the checkpoint; acceptance does not convert unresolved gap proposals into business decisions or accept their risks.
- **Current metadata:** AGENTS, README, CLAUDE, CONTEXT_INDEX and the five governing P0 specification files carry APPROVED with this reference; ADR-001 carries ACCEPTED. Routine logs, gaps, gate reports and handoff stay REVIEW as live records. Owner-source records remain immutable.
- **Subsequent updates:** DIR-006–008 instructions and TECH-003/004 continuity corrections update current files with a visible decision trail. DIR-008 explicitly updates source precedence; TECH-004 supplies coverage/navigation/evidence gates within delegated authority. They do not retroactively alter the approved baseline or claim owner acceptance of every newly drafted P1 sentence.
- **Exclusions:** P2, implementation, packages/scaffolding, production access, full P1 scope approval, runtime test success, risk acceptance, planning freeze and release approval.

Future entries follow the [approval-evidence contract](CHANGE_CONTROL.md#approval-evidence). Preserve superseded/rejected entries and link their replacement rather than deleting the trail.

## TECH-004 evidence and limits

- **Affected revision:** The local P1 documentation checkpoint containing this entry; source byte hashes in SOURCE_OF_TRUTH identify immutable inputs independently of commit identity.
- **Classification:** Applied the owner's explicit hierarchy directly; reconciled omissions in REVIEW product documents and extended navigation/traceability obligations under Level 1. No changed stock-reservation promise, completion-waiver permission, financial formula or external issuer authority was selected.
- **Evidence:** All 12 PDF pages and the full diagram inspected; immutable copies hash-checked; requirement/acceptance/link/status checks recorded in [P1_QUALITY_GATE](../01-product/P1_QUALITY_GATE.md). Text extraction and rendered-page inspection were analysis only; originals not edited/re-exported.
- **Business limits:** Five OWNER_DECISION_REQUIRED gaps remain GAP-004/006/018/022/023. Owner-directed ingestion does not equal full P1 approval. Reference PASS labels are desired gates, not run results. No scope cut, paid dependency, new phase, application code or production activity.

## Open decisions

Open findings and OWNER_DECISION_REQUIRED choices have one home in [GAP_REGISTER](GAP_REGISTER.md). [NEXT_ACTION](../07-handoff/NEXT_ACTION.md) identifies the safe follow-up. Do not ask again for owner choices already settled by DIR-006/007; track residual mechanisms/evidence separately. P1 gate status is owned by its quality report.

## TECH-001 evidence and impact

- **Trigger:** Earlier P0 wording required owner approval for every substantive technical change and could leave an executor following an unsafe approved revision. DIR-005 expressly permits direct technical improvements and forbids implementing known-bad plans.
- **Action/authority:** Planner classified the correction as Level 1: it changes governance mechanics, preserves business behavior, scope, company visibility, financial/stock meaning, deadline and production boundary. Applied directly; no redundant owner request.
- **Affected revision:** The initial local documentation checkpoint containing DIR-005/TECH-001; resolve it with `git log --oneline -- docs/00-governance/DECISION_LOG.md`. Owner-source hashes are recorded in SOURCE_OF_TRUTH. Before that checkpoint exists, files remain local changes, not a published revision.
- **Changed concerns:** CHANGE_CONTROL owns authority; SOURCE_OF_TRUTH owns classification/lifecycle; AGENT_OPERATING_MODEL owns review/feedback; GAP_REGISTER owns findings. Root navigation, engineering guidance, ADR-001 proposal and handoff now link consistently. No separate authority-policy file or new ADR was added.
- **Impact:** No application/database/deployment changes, dependencies or recurring cost. Technical clarification reduces approval waiting and duplicate documentation. Risks are incorrect classification and outdated handoff; mitigated by strongest-boundary examples, explicit owner-decision gaps and linked phase gates.
- **Verification:** [P0 extension review](P0_QUALITY_GATE.md#autonomy-extension-review). Source preservation, link checks and boundary scenarios verify the documentation; runtime safeguards remain unbuilt and untested.

# Decision and approval log

Status: REVIEW | Updated: 2026-09-27 | Owner: Planning

This is an evidence index. Rules live in the linked owning documents. Entries distinguish owner directives, delegated technical improvements, proposals, observations and approvals. No product/scope acceptance or implementation authorization is recorded here.

| ID | Date | Classification | Evidence and owning record |
| --- | --- | --- | --- |
| DIR-001 | 2026-09-27 | Owner directive | Plan only; inspect first and execute P0 only. [Brief](sources/OWNER_BRIEF_2026-09-27.txt), opening and §§55–57; [charter](PROJECT_CHARTER.md) |
| DIR-002 | 2026-09-27 | Owner directive | Repository continuity and one owner per concern. Brief §§1, 48–51; [source of truth](SOURCE_OF_TRUTH.md) |
| DIR-003 | 2026-09-27 | Owner directive | Distinct planner/executor responsibilities, approval gates, and controlled changes. Brief opening, §§50, 52–54; [operating model](AGENT_OPERATING_MODEL.md), [change control](CHANGE_CONTROL.md) |
| DIR-004 | 2026-09-27 | Owner directive | Integrity, security, production restrictions and evidence-based quality. Brief §§24–44, 58; [engineering principles](ENGINEERING_PRINCIPLES.md) |
| OBS-001 | 2026-09-27 | Verified observation | Public GitHub repository; configured default `main`; no remote refs or commits at inspection. [P0 inspection evidence](P0_QUALITY_GATE.md#repository-inspection) |
| PROP-001 | 2026-09-27 | Planner proposal; pending review | Minimal ownership/lifecycle and continuity package. [ADR-001](../adr/ADR-001-repository-governance.md) is PROPOSED; all new P0 documents are REVIEW |
| DIR-005 | 2026-09-27 | Owner directive and bounded delegation; effective | [Architect mandate](sources/ARCHITECT_MANDATE_2026-09-27.txt): Decision Authority, Proactive Gap Register, Continuous Red-Team Review, Executor Feedback Loop, Pre-Build Challenge and Pre-Mortem. Supersedes blanket approval barriers for Level 1 changes; preserves Level 2/3 owner decisions and planning-only role |
| TECH-001 | 2026-09-27 | Level 1 improvement applied by planner under DIR-005 | Corrected approval overreach and unsafe-revision fallback; introduced one gap register, recurring review, feedback and cross-module freeze gates. [Change control](CHANGE_CONTROL.md), [source governance](SOURCE_OF_TRUTH.md), [operating model](AGENT_OPERATING_MODEL.md); evidence below |
| TECH-002 | 2026-09-27 | Level 1 sequencing improvement applied by planner under DIR-005 | Discover feasibility, data and acceptance dependencies before their detailed design phases rather than waiting until P10. [GAP-014](GAP_REGISTER.md#gap-014--eighteen-days-is-a-target-not-a-measured-delivery-capacity). No scope cut or deadline change selected |

## Approval register

No APPROVED/LOCKED implementation specification, accepted ADR, planning freeze, or production release approval exists. DIR-005 authorizes Level 1 planning changes directly; it is not blanket acceptance of all proposed business/design decisions. REVIEW labels do not suspend that delegation.

Future entries follow the [approval-evidence contract](CHANGE_CONTROL.md#approval-evidence). Preserve superseded/rejected entries and link their replacement rather than deleting the trail.

## Open decisions

Open technical findings and OWNER_DECISION_REQUIRED choices have one home in [GAP_REGISTER](GAP_REGISTER.md). The [next task](../07-handoff/NEXT_ACTION.md) links to priorities. There is no unresolved blocker to the P0 extension; unresolved business choices must be resolved before their named dependent gates.

## TECH-001 evidence and impact

- **Trigger:** Earlier P0 wording required owner approval for every substantive technical change and could leave an executor following an unsafe approved revision. DIR-005 expressly permits direct technical improvements and forbids implementing known-bad plans.
- **Action/authority:** Planner classified the correction as Level 1: it changes governance mechanics, preserves business behavior, scope, company visibility, financial/stock meaning, deadline and production boundary. Applied directly; no redundant owner request.
- **Affected revision:** The initial local documentation checkpoint containing DIR-005/TECH-001; resolve it with `git log --oneline -- docs/00-governance/DECISION_LOG.md`. Owner-source hashes are recorded in SOURCE_OF_TRUTH. Before that checkpoint exists, files remain local changes, not a published revision.
- **Changed concerns:** CHANGE_CONTROL owns authority; SOURCE_OF_TRUTH owns classification/lifecycle; AGENT_OPERATING_MODEL owns review/feedback; GAP_REGISTER owns findings. Root navigation, engineering guidance, ADR-001 proposal and handoff now link consistently. No separate authority-policy file or new ADR was added.
- **Impact:** No application/database/deployment changes, dependencies or recurring cost. Technical clarification reduces approval waiting and duplicate documentation. Risks are incorrect classification and outdated handoff; mitigated by strongest-boundary examples, explicit owner-decision gaps and linked phase gates.
- **Verification:** [P0 extension review](P0_QUALITY_GATE.md#autonomy-extension-review). Source preservation, link checks and boundary scenarios verify the documentation; runtime safeguards remain unbuilt and untested.

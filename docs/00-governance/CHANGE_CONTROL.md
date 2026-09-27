# Decision authority, change control and ADR rules

Status: APPROVED | Updated: 2026-09-27 | Owner: Planning

Approval: [APPR-001](DECISION_LOG.md), P0 governance at b425584. DIR-006–008 and TECH-003/004 record subsequent owner-directed and Level 1 continuity updates; P1 specifications remain REVIEW.

## Decision authority

Authority comes from the [architect mandate](sources/ARCHITECT_MANDATE_2026-09-27.txt), reaffirmed by the [P1 directive](sources/P1_OWNER_DIRECTIVE_2026-09-27.txt), recorded as DIR-005/006. This delegation is effective; REVIEW metadata is not an approval barrier to using it. Classify owner intent, baselines and proposals under [source governance](SOURCE_OF_TRUTH.md#classify-statements-before-changing-them).

**Level 1 — improve directly.** The planner may improve planning without requesting owner approval when business behavior and approved scope remain intact and the change simplifies, removes duplication, resolves technical contradictions, clarifies documentation, improves sequencing, or strengthens security, integrity, testability, maintainability, observability or recovery. Add missing technical safeguards. Record meaningful changes in the owning document and a short decision/gap entry; use an ADR only for significant architectural tradeoffs. No separate change-request form is needed for a routine improvement.

**Level 2 — OWNER DECISION REQUIRED.** Propose and flag any change to business workflow, financial meaning, stock ownership, material user responsibilities, requested capabilities, meaningful V1 scope, release target, material UX behavior, significant infrastructure, recurring costs, legal/document behavior, permitted data visibility or production risk tolerance. State the problem, why it matters, recommended option, useful alternatives, and scope/time/risk impact. Do not enact the business decision while awaiting the owner.

**Level 3 — locked owner decisions.** Do not silently override explicit latest owner choices. The original source locks the product name, normal-client treatment of UNY/UGM, SIPLAH as channel, supplier-comparison exclusion, optional PO, managerial finance, and two launch roles with future custom-role capability (brief §§2, 7–9, 12, 16–17). These examples do not limit the owner's authority over other explicit requirements. If a locked decision creates a serious issue, record **ARCHITECTURE/PRODUCT CONCERN** with the current decision, discovered problem, evidence/reasoning, consequence if unchanged, recommended alternative and implementation impact; request the owner's decision. Do not suppress the concern because the decision is locked.

Apply the strongest relevant boundary: a claimed security/recovery improvement that changes visibility, workflow, recurring cost or accepted data loss is Level 2. Strengthening an existing company-scope check is Level 1; deciding that shared client contacts must become private to each company is Level 2. A constraint enforcing an agreed invariant is Level 1; rejecting a previously valid business operation requires review of its business impact. Unknown meaning remains an open decision, not a convenient technical default.

Delegation attaches to the planning architect role for continuity across agents, consistent with the mandate's replacement criteria. It does not give an executor authority to redefine business rules, grant production access, approve an entire unreviewed product baseline, or bypass planning freeze/execution authorization.

## Changes before and after planning freeze

Before freeze, refine documents through the [lifecycle](SOURCE_OF_TRUTH.md#document-lifecycle). Fix Level 1 weaknesses promptly. After freeze, keep the same authority classification and decision trail; LOCKED does not require retaining a known technical defect.

New V1 capabilities after freeze require owner acceptance under brief §47: blocking core operation, critical security/integrity issue or mandatory client requirement. A technical safeguard for existing approved behavior is not automatically a new feature. Scope cuts, workflow simplification that changes behavior, and date changes still need owner decisions. Never trade authorization, stock/payment integrity, constraints, backups, automated tests or production isolation for the deadline.

For a meaningful change:

1. Classify authority level and affected canonical concern. Record the failure scenario and evidence.
2. Analyze relevant business/scope, database/migration, security, timeline, performance/concurrency, regression and recovery effects. Use `N/A — reason` where useful without padding trivial edits.
3. Apply Level 1 directly and record rationale/verification. For Level 2/3, flag the owner decision with a concrete recommendation and useful alternatives; keep dependent choices pending.
4. Update canonical documents, affected build units/tests, ADR if justified, gap status and handoff before affected execution continues. Preserve history and identify the changed revision.

## Lightweight records

Use the [gap register](GAP_REGISTER.md) for risk treatment and decision requests, and [decision log](DECISION_LOG.md) for decision evidence. Link them instead of duplicating rules. Create `docs/00-governance/changes/CR-NNN-<subject>.md` only when analysis outgrows an entry.

A substantive record identifies date/actor, authority/source, evidence, affected concern/revision, action/options, impact, verification, decision owner if needed, and affected phase/build units. Pending owner requests explicitly say OWNER DECISION REQUIRED. Routine editorial edits need no ADR or owner confirmation.

## ADR contract

Use monotonically assigned `ADR-NNN-<subject>.md` filenames and check existing IDs before adding one. ADRs describe significant decisions and alternatives, not every implementation detail.

Each ADR contains: ID/title/date/status; context; proposed or accepted decision; alternatives; consequences/tradeoffs; affected canonical owners; approval evidence; and successor/predecessor links when relevant.

ADR statuses: **PROPOSED**, **ACCEPTED**, **SUPERSEDED**, **REJECTED**. The planner may accept a technical-only Level 1 ADR with delegated authority and rationale recorded. Level 2/3 ADRs require the owner's decision. Accepted ADRs retain historical rationale; significant changes create a successor and mark the old one SUPERSEDED. Rejected proposals retain their reason. Revise a still-PROPOSED ADR directly with a change note instead of multiplying ADRs.

The decision log indexes ADRs and approvals; it does not restate the full decision. Documentation status and ADR status are different lifecycles. ACCEPTED is not proof that implementation exists or tests pass. The owning specification remains the working rule and must agree with the ADR.

## Approval evidence

Record who decided, date, owner instruction or delegated authority, exact files/revision, conditions and excluded items. Before a commit exists, identify files and content hashes; afterwards link the Git checkpoint. Avoid self-referential commit hashes inside the same commit. Transcribe owner approval into the repository; a chat identifier alone is insufficient.

Owner approval and delegated Level 1 technical acceptance are distinct. Do not infer owner acceptance from silence, a status label or a planning request. Conversely, do not repeatedly ask for permission already granted by DIR-005. Document status does not prevent ongoing authorized planning improvements.

P0 governance acceptance is APPR-001. Product/scope acceptance, planning freeze and execution authorization remain distinct gates; none is completed merely by authorizing P1 drafting.

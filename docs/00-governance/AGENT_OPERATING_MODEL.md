# Agent operating model

Status: APPROVED | Updated: 2026-09-29 | Owner: Planning

Approval: [APPR-001](DECISION_LOG.md), P0 governance at b425584. Subsequent directives and continuity updates remain recorded in the decision log. [APPR-002](DECISION_LOG.md#appr-002--p1-product-definition-and-v1-scope-approved) approves the three P1 canonical product specifications, [APPR-003](DECISION_LOG.md#appr-003--p2-domain-baseline-approved) the two P2 normative domain specifications and [APPR-004](DECISION_LOG.md#appr-004--p3-critical-business-workflows-approved) the P3 workflow specification with its amended P1/P2 revisions; evidence, gap and handoff records remain REVIEW.

## Responsibilities and limits

| Role | Owns | Boundary |
| --- | --- | --- |
| Project owner | Business priorities, scope acceptance, approval/delegation, release go/no-go | Approval must identify what was reviewed |
| Planner | Active gap discovery, requirements, architecture, quality/release planning, direct Level 1 improvements, decision proposals and execution support | Uses the delegated authority levels; does not implement the application, deploy production, modify production data, or handle production credentials |
| Executor | Bounded implementation, deterministic tests, review, relevant documentation and handoff updates | Implements approved specifications; escalates material design/scope changes through change control |
| Reviewer | Evidence-based review of requirements, changes, tests, risks | An agent review is evidence, not owner acceptance or proof that unrun tests pass |
| Authorized human production operator | Production credentials, approved migrations/deployment, backups and restoration | Operational details are planned in P9; agents work only with development/test/staging |

The brief identifies Astra as initial planner and Claude Opus 5.5 as initial executor. These labels allocate roles; they are not technical dependencies, authorization checks, or claims about tool availability. Any capable replacement follows this same contract.

## Execution eligibility

Planning may continue within the authorized assignment and after resolving blocking predecessor contradictions. P0 governance is accepted. The current assignment authorizes P1 only; proactive risk discovery and Level 1 corrections remain allowed without generating P2+ specifications. A review label is not a reason to seek new permission for those corrections.

Before application implementation, the repository must identify the owner-authorized execution phase/planning freeze, a bounded task, its APPROVED/LOCKED specification and dependencies, and approval evidence under the [document lifecycle](SOURCE_OF_TRUTH.md#document-lifecycle). An approved P0 governance file alone is insufficient.

Executor loop: **PLAN → IMPLEMENT → TEST → REVIEW → COMMIT**. Each unit reads its governing docs; validates framework behavior with current documentation; makes the minimum safe change; runs required checks; reviews authorization, company isolation, validation, constraints, N+1, concurrency, idempotency, auditability and regression; and updates the handoff. Each applicable completion category needs evidence; non-applicable categories need a reason. See [engineering principles](ENGINEERING_PRINCIPLES.md).

Do not introduce dependencies without justification, refactor unrelated modules, weaken valid tests to make checks pass, or change architecture implicitly. A failed check remains a failed check in the record. Do not reset, clean, or overwrite another contributor's work to simplify a handoff.

## Task contract for P11

Every future implementation unit must provide the fields required by brief §54: ID/title/purpose/context; scope and exclusions; dependencies; business-rule references; data impact; authorization/validation; concurrency/idempotency; audit; UI/routes; errors; acceptance criteria and deterministic tests; performance; definition of done.

Specify WHAT must hold, WHY, constraints, important failure cases, acceptance criteria, protections and deterministic tests. Leave routine function names, internal code organization and implementation details to the executor within the approved architecture. Prefer references over copied business rules. Use `N/A — reason` where a concern does not apply. Do not create build units before P11.

DIR-008 additionally requires the full Requirement → Canonical specification → Business workflow/rule → Build Unit → Acceptance criteria → Required test chain. Carry the identifiers from [REFERENCE_COVERAGE](../01-product/REFERENCE_COVERAGE.md#traceability-handoff-and-non-loss-gate) through later planning; P11 must create FEATURE_COVERAGE_MATRIX before freeze, covering original owner requirements as well as references. The interim [feature/module/release obligations](../01-product/ACCEPTANCE_CRITERIA.md#feature-module-and-release-completion-obligations) preserve eight dimensions of DoD for P8/P11. A missing tool or failed check is not N/A; a working UI alone is not DONE.

## Continuous adversarial review

At the end of each major planning phase, review the actual deliverable from these perspectives: business, data, security, authorization, company isolation, concurrency, idempotency, performance, UX, operations, recovery, migration, testability, maintainability and agent continuity. Record concise findings/evidence and non-applicable areas in that phase's existing gate or handoff; do not create a separate checklist file for every view.

Try interrupted/repeated requests, stale sessions/forms, simultaneous edits, retries and restarts between effects, dependency loss, mistakes and hostile input, 100x data, malformed legacy records, recovery and future maintenance. Look for contradictions, duplicate truth, hidden coupling, missing invariants, irreversible operations, fragile integrations, excessive abstraction and operational burden. This is a starting lens, not an exhaustive checklist.

Apply Level 1 fixes immediately; record residual gaps or owner decisions in [GAP_REGISTER](GAP_REGISTER.md). A discovered risk may belong to a later phase without blocking today's independent work. It must have a resolution gate before affected design/execution. Do not label a safeguard mitigated simply because a future test is planned.

Before planning freeze, perform a whole-system architecture challenge: Project–Company/Client/Purchase; Purchase–Inventory; Inventory–Delivery; Delivery–Invoice; Invoice–Billing; Billing–Payment; Documents–Revision; Permissions–Company scope; SIPLAH–Project; Audit–Corrections; Migration–Schema. Add newly discovered boundaries. Record failure sequences across modules and the required assertions/decisions. Individual module reviews do not replace this check.

Before execution, run the pre-mortem: assume the 15 October release failed. For each plausible cause record warning signs, prevention, detection and recovery, then update the relevant build, test, security, release and operations plans. Use the gap register as the finding index; store the cross-module challenge/pre-mortem evidence in the planned `docs/06-delivery/RELEASE_PLAN.md`, not a second risk register. No claim is made that this full-V1 review has already happened.

Freeze requires no unresolved blocker to the proposed build, no silent Level 2/3 assumption and explicit handling of each material risk at its gate. New post-freeze discoveries still follow the feedback loop; safety does not stop at plan completion.

## Executor feedback loop

EXECUTION DISCOVERY → classify → direct low-risk planning correction or OWNER DECISION REQUIRED → update canonical documents and affected units/tests → verify → continue.

The executor reports the violated assumption, reproducer/evidence, affected behavior/data/tests, proposed minimum fix and whether any operation is unsafe. Routine implementation details within the approved plan remain the executor's choice. The planner handles changed specifications under [decision authority](CHANGE_CONTROL.md#decision-authority). Pause only unsafe/dependent work; do not force known-bad behavior because a plan predates the discovery. Record changed units, safe resumption criteria and actual verification. Architectural significance, not the mere existence of feedback, determines whether an ADR is needed.

## Replacement and continuity

The start procedure is owned by [AGENTS.md](../../AGENTS.md). A replacement agent verifies repository facts before acting, then resumes the exact authorized task. It must not infer completed work from planned filenames or a model's earlier statement.

Record unknowns and partial work candidly. Keep business state, decisions, and task progress independent of chat IDs, vendor memory, local absolute paths, or private model notes. Provider adapters contain links and tool preferences only. Missing tools must be recorded as limitations; do not invent tool results or substitute an unapproved architecture.

## Session-end checklist

1. Review the final diff and affected document links. Preserve work and inspect the actual branch/commit/dirty state.
2. Update each changed canonical concern once. Record source decisions and ADR/change-request references; update gap treatment and phase-review findings. Do not duplicate the rule in the handoff.
3. Update `CURRENT_STATE.md`: target/phase, last completed unit, branch/baseline commit or unborn state, verification run and results, blockers, warnings, protected boundaries, pending approval, and next action reference.
4. Update `NEXT_ACTION.md` with one bounded task, dependencies/inputs, stop conditions, and expected outputs. Distinguish a recommendation from authorization.
5. Record changed deliverables in `CHANGELOG.md` and decision evidence in `DECISION_LOG.md` when applicable. Never write secrets or real client records into these logs.
6. State whether changes are committed and whether they are pushed. An uncommitted local file is not yet available to an agent on another machine. If no commit is made, identify the complete pending change set and preserve it for review.
7. End with a concise report of outcome, evidence, remaining decisions, blockers, and exact next safe action. Never claim a later phase, test suite, deployment, or release completed without evidence.

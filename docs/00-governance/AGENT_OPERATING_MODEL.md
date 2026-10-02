# Agent operating model

Status: APPROVED | Updated: 2026-10-03 | Owner: Planning

Approval: [APPR-001](DECISION_LOG.md), P0 governance at b425584. Subsequent directives and continuity updates remain recorded in the decision log. [APPR-002](DECISION_LOG.md#appr-002--p1-product-definition-and-v1-scope-approved) approves the three P1 canonical product specifications, [APPR-003](DECISION_LOG.md#appr-003--p2-domain-baseline-approved) the two P2 normative domain specifications and [APPR-004](DECISION_LOG.md#appr-004--p3-critical-business-workflows-approved) the P3 workflow specification with its amended P1/P2 revisions and [APPR-005](DECISION_LOG.md#appr-005--p4-database-architecture-approved) the two P4 normative architecture specifications with the amended P2/P3 revisions and [APPR-006](DECISION_LOG.md#appr-006--p5-security-and-authorization-approved) the two P5 normative security and authorization specifications with the amended DATABASE revision and [APPR-007](DECISION_LOG.md#appr-007--p6-concurrency-idempotency-and-performance-approved) the three P6 normative concurrency, performance and integration-boundary specifications with the amended DATABASE, ARCHITECTURE, WORKFLOWS and PERMISSIONS_MATRIX revisions and [APPR-008](DECISION_LOG.md#appr-008--p7-ux-information-architecture-and-design-system-approved) the three P7 normative UX, information-architecture and design-system specifications with the amended SECURITY, DATABASE, PERFORMANCE and CONCURRENCY_IDEMPOTENCY revisions; evidence, gap and handoff records remain REVIEW.

Amendment — REVIEW, pending Owner approval: on 2026-10-03 this model adopted the AICWDF v4.3 agent roles, execution flow, Task model and Task contract and the Task terminology (DIR-035 D2, DIR-036, DIR-039 §10.1; [TECH-023](DECISION_LOG.md#dir-034039-obs-013-and-tech-023--aicwdf-adoption-directives-migration-baseline-and-structural-migration), where the SHA-256 of the last approved revision is recorded). The amended wording is not approved until the Owner approves it; the Owner decisions it applies are binding within their subjects.

## Roles and limits

Every agent identifies its role before acting (AICWDF §9). Role labels allocate work; they are not technical dependencies, authorization checks or claims about tool availability, and any capable replacement follows the same contract. The brief named Astra as initial planner and Claude Opus 5.5 as initial executor.

| Role | Owns (AICWDF §9) | Project boundary |
| --- | --- | --- |
| Project Owner | Business priorities, scope acceptance, approvals and delegation, every phase authorization, release go/no-go | An approval identifies what was reviewed |
| Planning Agent (§9.1) | Project state, gap discovery, P0–P11 planning, route and interaction contracts, the Task baseline and its dependencies, direct Level 1 improvements, decision proposals | Uses the authority levels of [CHANGE_CONTROL](CHANGE_CONTROL.md#decision-authority); does not implement the application, deploy, modify production data or handle production credentials |
| Execution Agent (§9.2) | One READY Task at a time: minimum context, prerequisites, impact analysis, implementation within scope, required tests, evidence, status and progress updates, handoff | Implements approved specifications; never silently redefines requirements, reopens decisions or widens scope; escalates through the [feedback loop](#executor-feedback-loop) |
| Review / QA Agent (§9.3) | Comparing work with its Task contract, tests and evidence; route, interaction, design and navigation consistency; architecture drift; rejecting false completion | A review is evidence, not Owner acceptance or proof that unrun tests pass |
| Release Agent (§9.4) | Release Tasks, regression, staging and UAT, database declaration, backup and rollback readiness, health and smoke checks | Releases only through the approved path; production operations stay with the human operator |
| Maintenance / Incident Agent (§9.5) | Reproducing issues safely, maintenance Tasks, targeted and regression tests, long-term data preservation | Database impact defaults to NONE; never touches production data ([PRODUCTION_DATA_SAFETY](PRODUCTION_DATA_SAFETY.md)) |
| Authorized human production operator | Production credentials, approved migrations and deployments, backups and restoration | Operational details are planned in P9 and P10; agents work only with development, test and staging |

A planning agent's first report and an execution agent's first response follow AICWDF §37 and §38.

## Phase authorization

Every phase P0–P11 needs its own explicit Owner authorization, its quality gate, the Owner's approval and a published checkpoint; [PHASE_STATUS](../PHASE_STATUS.md) shows where each phase stands. Proactive risk discovery and Level 1 corrections continue without new permission and without producing specifications of an unauthorized phase. Planning freeze and execution are separate Owner gates after P11.

## Execution eligibility

Before application implementation the repository must hold the Owner-authorized planning freeze, the P11 Task plan, a READY Task whose specifications and dependencies are APPROVED or LOCKED, and the approval evidence of the [document lifecycle](SOURCE_OF_TRUTH.md#document-lifecycle). An approved governance file alone is insufficient.

Do not introduce dependencies without justification, refactor unrelated modules, weaken valid tests to make checks pass, or change architecture implicitly. A failed check remains a failed check in the record. Do not reset, clean, or overwrite another contributor's work to simplify a handoff.

## Execution flow

An Execution Agent follows the flow of AICWDF §24: select a READY Task; read [AGENTS.md](../../AGENTS.md#read-in-this-order), [EXECUTION_CONTEXT](../11-tasks/EXECUTION_CONTEXT.md), [CURRENT_HANDOFF](../handoff/CURRENT_HANDOFF.md), the Task plan and the active Task, then only the exact sections it references; check the required tools ([TOOLCHAIN](TOOLCHAIN.md)) and current documentation for version-sensitive behaviour; analyse impact; implement the smallest safe diff; run the verification layers the Task requires; review; record evidence; then mark the Task DONE or leave it VERIFYING or BLOCKED.

Every diff is also reviewed for the project's own list: **authorization, company isolation, validation, constraints, N+1, concurrency, idempotency, auditability and regression**. Each applicable completion category needs evidence and each non-applicable one a reason ([ENGINEERING_PRINCIPLES](ENGINEERING_PRINCIPLES.md#verification-and-completion)).

## Task model

Tasks exist only from P11 (AICWDF §22). The rules:

- **Statuses** — TODO, READY, IN_PROGRESS, BLOCKED, VERIFYING, DONE, SUPERSEDED (AICWDF §19); VERIFYING is not DONE.
- **READY gate** — dependencies DONE, requirement clear, scope bounded, acceptance criteria and tests defined, database, route, interaction, UI, localization and cost impact known, tools available or safely installable, no blocking ambiguity (AICWDF §22.8).
- **Stable IDs** — a Task ID is permanent; a split Task becomes SUPERSEDED and names its successors (AICWDF §22.4).
- **Totals** — the Task plan keeps the baseline total and the current total, and its counters reconcile with the Task list (AICWDF §22.1, §22.5, §35).
- **Controlled plan changes** — adding, splitting or merging Tasks is recorded with its reason, totals, dependencies, scope effect and whether the Owner must approve (AICWDF §22.6).
- **One READY Task at a time** per Execution Agent; parallel work only for Tasks marked PARALLEL SAFE: YES, with DEPENDS ON and BLOCKS declared (AICWDF §22.7, §22.9).
- **DONE** means the required implementation is complete, the required tests pass and the completion evidence exists (AICWDF §22.2).
- **Automatic progress update** — when a Task reaches DONE the agent sets its status, checks it in the Task plan, updates the counters and updates CURRENT_HANDOFF (AICWDF §22.3).
- **Size and risk** — one coherent outcome verifiable end to end; risk class HIGH for authentication, authorization, money, inventory truth, schema evolution, company isolation, critical concurrency, security, production infrastructure or major architecture (AICWDF §22.10, §22.11).

## Task contract

Every Task uses the template of AICWDF §23 plus three project fields:

- **CONCURRENCY / IDEMPOTENCY** — the lock classes and positions, command identity, replay, duplicate and stale-state behaviour, citing [CONCURRENCY_IDEMPOTENCY](../06-api-performance/CONCURRENCY_IDEMPOTENCY/README.md) and the AX rows of [WORKFLOWS §9](../03-workflows/WORKFLOWS/s08-09-corrections-indivisible.md#9-indivisible-business-actions).
- **AUDIT** — the business-audit and security events, correction linkage and actor attribution, citing BUSINESS_RULES and [SECURITY §6](../05-security/SECURITY.md#6-audit-and-security-logging).
- **COMPANY SCOPE AND FIELD PROJECTION** — the capability, company-scope rules and projection of every path the Task touches, citing [PERMISSIONS_MATRIX](../05-security/PERMISSIONS_MATRIX.md).

The contract still covers every field brief §54 requires:

| Brief §54 field | Contract field |
| --- | --- |
| ID, title | TASK-XXX — TITLE |
| Purpose; business context | OBJECTIVE; WHY THIS EXISTS; REFERENCES |
| Scope; explicitly out of scope | IN SCOPE; OUT OF SCOPE; DO NOT DO |
| Dependencies | DEPENDS ON; BLOCKS; PARALLEL SAFE |
| Business rules | BUSINESS RULES |
| Data model impact | DATABASE IMPACT ASSESSMENT |
| Authorization; validation | SECURITY / PERMISSION REQUIREMENTS; COMPANY SCOPE AND FIELD PROJECTION; validation results of the INTERACTION CONTRACTS |
| Concurrency and idempotency considerations | CONCURRENCY / IDEMPOTENCY |
| Audit requirements | AUDIT |
| UI expectations | UI / UX REQUIREMENTS; NAVIGATION CONTRACT |
| API and route expectations | ROUTE IMPACT; INTERACTION CONTRACTS |
| Error cases | Error state and error results of UI / UX REQUIREMENTS and the INTERACTION CONTRACTS; ACCEPTANCE CRITERIA |
| Acceptance criteria | ACCEPTANCE CRITERIA |
| Required automated tests | TEST REQUIREMENTS; TEST CHECKLIST |
| Performance considerations | the performance line of TEST REQUIREMENTS, citing [PERFORMANCE](../06-api-performance/PERFORMANCE.md) |
| Definition of done | STATUS under the DONE rule; TEST CHECKLIST; COMPLETION EVIDENCE |

Specify WHAT must hold, WHY, constraints, important failure cases, acceptance criteria, protections and deterministic tests. Leave routine function names, internal code organization and implementation details to the executor within the approved architecture. Prefer references over copied business rules. Use `N/A — reason` where a concern does not apply.

## Terminology

"Build Unit" — also "implementation unit" or "unit" — is the historical name of a **Task**; from this adoption point (2026-10-03, DIR-036, DIR-039) the execution term is Task. Historical text is not rewritten: read "build unit" in earlier documents and records as Task. Likewise CURRENT_STATE and NEXT_ACTION, the handoff pair archived on 2026-10-02, read as [CURRENT_HANDOFF](../handoff/CURRENT_HANDOFF.md) and [PHASE_STATUS](../PHASE_STATUS.md).

DIR-008's traceability chain keeps its meaning with the Task in the unit position: Requirement → Canonical specification → Business workflow/rule → Task → Acceptance criteria → Required test. Carry the identifiers from [REFERENCE_COVERAGE](../01-product/REFERENCE_COVERAGE.md#traceability-handoff-and-non-loss-gate) through later planning; P11 must create FEATURE_COVERAGE_MATRIX before freeze, covering original Owner requirements as well as references. The interim [feature/module/release obligations](../01-product/ACCEPTANCE_CRITERIA.md#feature-module-and-release-completion-obligations) preserve eight dimensions of DoD for P8/P11. A missing tool or failed check is not N/A; a working UI alone is not DONE. No Task is created before P11.

## Continuous adversarial review

At the end of each major planning phase, review the actual deliverable from these perspectives: business, data, security, authorization, company isolation, concurrency, idempotency, performance, UX, operations, recovery, migration, testability, maintainability and agent continuity. Record concise findings/evidence and non-applicable areas in that phase's existing gate or handoff; do not create a separate checklist file for every view.

Try interrupted/repeated requests, stale sessions/forms, simultaneous edits, retries and restarts between effects, dependency loss, mistakes and hostile input, 100x data, malformed legacy records, recovery and future maintenance. Look for contradictions, duplicate truth, hidden coupling, missing invariants, irreversible operations, fragile integrations, excessive abstraction and operational burden. This is a starting lens, not an exhaustive checklist.

Apply Level 1 fixes immediately; record residual gaps or owner decisions in [GAP_REGISTER](GAP_REGISTER.md). A discovered risk may belong to a later phase without blocking today's independent work. It must have a resolution gate before affected design/execution. Do not label a safeguard mitigated simply because a future test is planned.

Before planning freeze, perform a whole-system architecture challenge: Project–Company/Client/Purchase; Purchase–Inventory; Inventory–Delivery; Delivery–Invoice; Invoice–Billing; Billing–Payment; Documents–Revision; Permissions–Company scope; SIPLAH–Project; Audit–Corrections; Migration–Schema. Add newly discovered boundaries. Record failure sequences across modules and the required assertions/decisions. Individual module reviews do not replace this check.

Before execution, run the pre-mortem: assume the V1 release failed. For each plausible cause record warning signs, prevention, detection and recovery, then update the relevant Task, test, security, release and operations plans. Use the gap register as the finding index; store the cross-module challenge/pre-mortem evidence in the planned `docs/10-release/RELEASE_PLAN.md`, not a second risk register. No claim is made that this full-V1 review has already happened.

Freeze requires no unresolved blocker to the proposed build, no silent Level 2/3 assumption and explicit handling of each material risk at its gate. New post-freeze discoveries still follow the feedback loop; safety does not stop at plan completion.

## Executor feedback loop

EXECUTION DISCOVERY → classify → direct low-risk planning correction or OWNER DECISION REQUIRED → update canonical documents and affected Tasks/tests → verify → continue.

The executor reports the violated assumption, reproducer/evidence, affected behavior/data/tests, proposed minimum fix and whether any operation is unsafe. Routine implementation details within the approved plan remain the executor's choice. The planner handles changed specifications under [decision authority](CHANGE_CONTROL.md#decision-authority). Pause only unsafe/dependent work; do not force known-bad behavior because a plan predates the discovery. Record changed Tasks, safe resumption criteria and actual verification. Architectural significance, not the mere existence of feedback, determines whether an ADR is needed.

## Replacement and continuity

The start procedure and reading order are owned by [AGENTS.md](../../AGENTS.md). A replacement agent verifies repository facts before acting, then resumes the exact authorized task. It must not infer completed work from planned filenames or a model's earlier statement.

Record unknowns and partial work candidly. Keep business state, decisions, and task progress independent of chat IDs, vendor memory, local absolute paths, or private model notes. Provider adapters contain links and tool preferences only. Missing tools must be recorded as limitations; do not invent tool results or substitute an unapproved architecture.

## Session-end checklist

1. Review the final diff and affected document links. Preserve work and inspect the actual branch/commit/dirty state.
2. Update each changed canonical concern once. Record source decisions and ADR/change-request references; update gap treatment and phase-review findings. Do not duplicate the rule in the handoff.
3. Update [CURRENT_HANDOFF](../handoff/CURRENT_HANDOFF.md) in the format of AICWDF §36: current phase and Task, last completed action with commit SHAs, evidence, blockers, decisions made and pending, files changed, safe next action and what not to do — the current state only, no history.
4. Update [PHASE_STATUS](../PHASE_STATUS.md) when a phase changes status; after P11, update the Task, its checkbox and the counters of the Task plan when a Task reaches DONE. Distinguish a recommendation from an authorization.
5. Record changed deliverables in `CHANGELOG.md` and decision evidence in `DECISION_LOG.md` when applicable. Never write secrets or real client records into these logs.
6. State whether changes are committed and whether they are pushed. An uncommitted local file is not yet available to an agent on another machine. If no commit is made, identify the complete pending change set and preserve it for review.
7. End with a concise report of outcome, evidence, remaining decisions, blockers, and exact next safe action. Never claim a later phase, test suite, deployment, or release completed without evidence.

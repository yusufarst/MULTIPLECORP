# Project charter

Status: APPROVED | Updated: 2026-10-03 | Owner: Planning

Approval: [APPR-001](DECISION_LOG.md), P0 governance at b425584. Subsequent directives and continuity updates remain recorded in the decision log. [APPR-002](DECISION_LOG.md#appr-002--p1-product-definition-and-v1-scope-approved) approves the three P1 canonical product specifications, [APPR-003](DECISION_LOG.md#appr-003--p2-domain-baseline-approved) the two P2 normative domain specifications and [APPR-004](DECISION_LOG.md#appr-004--p3-critical-business-workflows-approved) the P3 workflow specification with its amended P1/P2 revisions and [APPR-005](DECISION_LOG.md#appr-005--p4-database-architecture-approved) the two P4 normative architecture specifications with the amended P2/P3 revisions and [APPR-006](DECISION_LOG.md#appr-006--p5-security-and-authorization-approved) the two P5 normative security and authorization specifications with the amended DATABASE revision and [APPR-007](DECISION_LOG.md#appr-007--p6-concurrency-idempotency-and-performance-approved) the three P6 normative concurrency, performance and integration-boundary specifications with the amended DATABASE, ARCHITECTURE, WORKFLOWS and PERMISSIONS_MATRIX revisions and [APPR-008](DECISION_LOG.md#appr-008--p7-ux-information-architecture-and-design-system-approved) the three P7 normative UX, information-architecture and design-system specifications with the amended SECURITY, DATABASE, PERFORMANCE and CONCURRENCY_IDEMPOTENCY revisions; evidence, gap and handoff records remain REVIEW.

Amendment: on 2026-10-03 the V1 target, the authorized-work statement and the planning sequence were amended to apply the Owner's decisions of no fixed delivery date (DIR-035 D1) and the AICWDF v4.3 phase model with PHASE_STATUS as the progress view (D2, DIR-036, DIR-039 §10.1; [TECH-023](DECISION_LOG.md#dir-034039-obs-013-and-tech-023--aicwdf-adoption-directives-migration-baseline-and-structural-migration), where the SHA-256 of the last approved revision is recorded); the amended revision is approved under [APPR-009](DECISION_LOG.md#appr-009--aicwdf-structural-migration-approved).

## Identity and outcome

- Name: **MultipleCorp — Company Management System**.
- Repository: [yusufarst/MULTIPLECORP](https://github.com/yusufarst/MULTIPLECORP).
- Product: one internal workspace for a group of legal companies, organized around projects and reusable business data.
- Outcome: operational work, trustworthy inventory, business documents, visible billing/receivables, accurate payments, owner visibility, and recoverable data.
- Target: **operational production V1 with no fixed delivery date** ([DIR-035](DECISION_LOG.md#dir-034039-obs-013-and-tech-023--aicwdf-adoption-directives-migration-baseline-and-structural-migration) D1, 2026-10-03). 15 October 2026 is no longer a constraint; any future target is subject to the quality gates and completion criteria and never justifies reduced quality, completeness, structure, security, data integrity, UX quality, testing or implementation readiness. Baseline planning date: 2026-09-27.
- Decision authority: the project owner/requester, with bounded technical planning authority delegated to the planner by the latest mandate. Boundaries belong to [change control](CHANGE_CONTROL.md#decision-authority); delegation evidence is DIR-005 in the decision log.

These are owner-provided facts from the [brief](sources/OWNER_BRIEF_2026-09-27.txt), opening and §§1–2, 46–47, reaffirmed in the P1 directive. P0 governance acceptance is APPR-001; binding product clarifications are DIR-006–007. DIR-008 then required full PDF/image reconciliation, source precedence and completion/DoD preservation, within the P1-only boundary that applied at the time.

Subsequent DIR-009 expressly chose same-production-VPS backups only for V1 and accepted the specified host/storage-loss exposure (RISK-001); that decision remains in force. Recovery targets apply only while VPS/local backup data remain recoverable. The local control/disk-safety obligations are owned by V1_SCOPE; the policy update was made within P1 and authorized neither P2 nor implementation at the time.

## Authorized work

Phase status, approvals and open items are recorded only in [PHASE_STATUS](../PHASE_STATUS.md); the current authorized work and the safe next action are in [CURRENT_HANDOFF](../handoff/CURRENT_HANDOFF.md). P0 governance was accepted at commit `b425584daa80af2c1342252407c95e08024d3173` (APPR-001); later approvals are indexed in the [decision log](DECISION_LOG.md#approval-register). Every phase needs its own explicit Owner authorization; nothing in this charter authorizes a phase.

P0 owns governance, authority, continuity and guardrails. The concern owners are indexed in CONTEXT_INDEX. Physical schema and migrations, test strategy, infrastructure, release planning and Tasks remain later-phase work.

Application code, Laravel/frontend generation, dependency installation, production infrastructure, deployment, and production database changes are outside this assignment. See [role boundaries](AGENT_OPERATING_MODEL.md) and [engineering constraints](ENGINEERING_PRINCIPLES.md).

## Planning sequence

The project follows the AICWDF v4.3 phase model (AICWDF §10; DIR-035 D2, DIR-036): P0 governance, foundation and agent continuity → P1 product definition, scope and acceptance → P2 domain model and business rules → P3 critical workflows, routes and interactions → P4 database and application architecture → P5 security, authentication and authorization → P6 concurrency, idempotency, API and performance → P7 UX, information architecture, design system, navigation and localization → P8 testing, quality and definition of done → P9 infrastructure, observability, backup and recovery → P10 release, migration, cutover, rollback and UAT → P11 Task planning, dependencies and execution specs → planning freeze → Task execution → verification → release. Where each phase stands is shown only in [PHASE_STATUS](../PHASE_STATUS.md); after P11 the Task plan is the execution dashboard.

Do not advance dependent planning through a blocking contradiction. Phase ordering sequences deliverables, not risk discovery: flag hidden dependencies and feasibility threats as soon as found, then resolve them before the affected decision/build gate. Record open assumptions explicitly. A deadline must not be used to waive data integrity, authorization, recoverability, or required tests. Scope cuts and target changes require the owner's decision; technical simplification within existing behavior uses Level 1 authority.

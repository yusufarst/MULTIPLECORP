# Phase status

Status: REVIEW | Updated: 2026-10-03 | Owner: Planning

The Owner's progress view of the AICWDF v4.3 phases until P11 creates the Task plan ([DIR-037](00-governance/DECISION_LOG.md#dir-034039-obs-013-and-tech-023--aicwdf-adoption-directives-migration-baseline-and-structural-migration); AICWDF §10). This is the only place phase status is recorded. Every phase needs its own explicit Owner authorization, quality gate, approval and checkpoint ([operating model](00-governance/AGENT_OPERATING_MODEL.md#phase-authorization)); a status here authorizes nothing.

| Phase | Scope (AICWDF section) | Status | Approval and open items |
| --- | --- | --- | --- |
| P0 | Governance, foundation and agent continuity (§11) | DONE | [APPR-001](00-governance/DECISION_LOG.md#appr-001--p0-governance-accepted). AICWDF adoption amendment: APPROVED ([APPR-009](00-governance/DECISION_LOG.md#appr-009--aicwdf-structural-migration-approved)) and PUBLISHED |
| P1 | Product definition, scope and acceptance (§12) | DONE | [APPR-002](00-governance/DECISION_LOG.md#appr-002--p1-product-definition-and-v1-scope-approved). Amendment — target, language and experience: APPROVED ([APPR-009](00-governance/DECISION_LOG.md#appr-009--aicwdf-structural-migration-approved)) and PUBLISHED |
| P2 | Domain model and business rules (§13) | DONE | [APPR-003](00-governance/DECISION_LOG.md#appr-003--p2-domain-baseline-approved) |
| P3 | Critical workflows, routes and interactions (§14) | DONE | [APPR-004](00-governance/DECISION_LOG.md#appr-004--p3-critical-business-workflows-approved). Open addition: route and interaction contracts, with P7 ([GAP-035](00-governance/GAP_REGISTER.md#gap-035--route-and-interaction-contracts-do-not-exist-yet)) |
| P4 | Database and application architecture (§15) | DONE | [APPR-005](00-governance/DECISION_LOG.md#appr-005--p4-database-architecture-approved). Open amendment: IAM tables, with the authentication amendment |
| P5 | Security, authentication and authorization (§16) | DONE | [APPR-006](00-governance/DECISION_LOG.md#appr-006--p5-security-and-authorization-approved). Open amendment: password, TOTP and Google sign-in — READY ([GAP-036](00-governance/GAP_REGISTER.md#gap-036--decided-authentication-changes-are-not-yet-designed)) |
| P6 | Concurrency, idempotency, API and performance (§17) | DONE | [APPR-007](00-governance/DECISION_LOG.md#appr-007--p6-concurrency-idempotency-and-performance-approved). Open amendment: minor, with the authentication amendment |
| P7 | UX, information architecture, design system, navigation and localization (§18) | IN_PROGRESS | Reopened by the Owner for the UX re-baseline (D3; DIR-037); the re-baseline work awaits its own Owner authorization. [ADMIN_FLOW](07-ux-design/ADMIN_FLOW/README.md) in force — its references to screens and patterns under replacement are revisited by the re-baseline; [DESIGN_SYSTEM](07-ux-design/DESIGN_SYSTEM.md) and [INFORMATION_ARCHITECTURE](07-ux-design/INFORMATION_ARCHITECTURE.md) under replacement; [APPR-008](00-governance/DECISION_LOG.md#appr-008--p7-ux-information-architecture-and-design-system-approved) kept as provenance |
| P8 | Testing, quality and definition of done (§19) | BLOCKED | Waits for the P5 authentication amendment and P7 |
| P9 | Infrastructure, observability, backup and recovery (§20) | TODO | — |
| P10 | Release, migration, cutover, rollback and UAT (§21) | TODO | — |
| P11 | Task planning, dependencies and execution specs (§22) | TODO | Creates the Task plan, the Tasks and the feature coverage matrix ([reservation](11-tasks/README.md)) |

| Milestone | State |
| --- | --- |
| Planning freeze | Not reached |
| Tasks | None; created in P11 |
| Structural migration ([DIR-039](00-governance/DECISION_LOG.md#dir-034039-obs-013-and-tech-023--aicwdf-adoption-directives-migration-baseline-and-structural-migration)) | DONE — approved ([APPR-009](00-governance/DECISION_LOG.md#appr-009--aicwdf-structural-migration-approved)) and published: `main` fast-forwarded from the P7 checkpoint to the finalization commit `565fbb48e1c2d64f7f3fcdc780b9983ceed36cad`, verified on the live remote ([OBS-015](00-governance/DECISION_LOG.md#obs-015--aicwdf-migration-publication-verified); [migration map](00-governance/MIGRATION_MAP.md#publication)) |

## Status values

The seven AICWDF statuses (AICWDF §19), read for phases as follows:

| Status | Meaning for a phase |
| --- | --- |
| TODO | Not started; prerequisites not yet met |
| READY | Prerequisites met; awaiting the Owner's explicit authorization |
| IN_PROGRESS | Opened by the Owner and under way; each piece of work inside the phase still needs the Owner's authorization |
| BLOCKED | Waiting for a named prerequisite |
| VERIFYING | Drafted and its gates passed; awaiting the Owner's approval |
| DONE | Approved by the Owner and its checkpoint published |
| SUPERSEDED | Replaced; the successor is linked |

An open item after a DONE phase is a separately authorized amendment or addition; it does not reopen the approved baseline. After P11 the Task plan becomes the primary execution dashboard and this view keeps only the phase rows.

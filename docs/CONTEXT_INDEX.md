# Context index

Status: APPROVED | Updated: 2026-09-30 | Owner: Planning

Approval: [APPR-001](00-governance/DECISION_LOG.md), P0 governance at b425584. Subsequent directives and continuity updates remain recorded in the decision log. [APPR-002](00-governance/DECISION_LOG.md#appr-002--p1-product-definition-and-v1-scope-approved) approves the three P1 canonical product specifications, [APPR-003](00-governance/DECISION_LOG.md#appr-003--p2-domain-baseline-approved) the two P2 normative domain specifications and [APPR-004](00-governance/DECISION_LOG.md#appr-004--p3-critical-business-workflows-approved) the P3 workflow specification with its amended P1/P2 revisions and [APPR-005](00-governance/DECISION_LOG.md#appr-005--p4-database-architecture-approved) the two P4 normative architecture specifications with the amended P2/P3 revisions; evidence, gap and handoff records remain REVIEW.

This is a navigation map, not a second specification. Read [AGENTS.md](../AGENTS.md), [current state](07-handoff/CURRENT_STATE.md), and the relevant documents below. Use [next action](07-handoff/NEXT_ACTION.md) to find the current task.

| Concern | Canonical document |
| --- | --- |
| Identity, objective, deadline, current phase boundary | [Project charter](00-governance/PROJECT_CHARTER.md) |
| Ownership, document lifecycle, provenance, conflict resolution | [Source of truth](00-governance/SOURCE_OF_TRUTH.md) |
| Roles, execution eligibility, session start/end, agent replacement | [Agent operating model](00-governance/AGENT_OPERATING_MODEL.md) |
| Three authority levels, change requests, approvals, ADR handling | [Change control](00-governance/CHANGE_CONTROL.md) |
| Discovered risks, mitigations and owner decisions | [Gap register](00-governance/GAP_REGISTER.md) |
| Decision and approval evidence index | [Decision log](00-governance/DECISION_LOG.md) |
| Non-negotiable engineering and production safety constraints | [Engineering principles](00-governance/ENGINEERING_PRINCIPLES.md) |
| P0 completion evidence | [P0 quality gate](00-governance/P0_QUALITY_GATE.md) |
| Product definition, users and outcomes | [Product overview](01-product/PRODUCT_OVERVIEW.md) |
| V1 priorities, exclusions, cost/dependencies and P7 contract | [V1 scope](01-product/V1_SCOPE.md) |
| Product acceptance and operational success | [Acceptance criteria](01-product/ACCEPTANCE_CRITERIA.md) |
| P1 adversarial review and planning verification | [P1 quality gate](01-product/P1_QUALITY_GATE.md) |
| Full PDF/image comparison and future requirement traceability | [Reference coverage](01-product/REFERENCE_COVERAGE.md) |
| Domain entities, terminology, relationships, scope/sensitivity classes | [Domain model](02-domain/DOMAIN_MODEL.md) |
| Business invariants, domain lifecycles, correction/snapshot/calculation rules | [Business rules](02-domain/BUSINESS_RULES.md) |
| P2 adversarial review and traceability verification | [P2 quality gate](02-domain/P2_QUALITY_GATE.md) |
| Workflow orchestration, actor/authority matrix, correction matrix, indivisible actions | [Workflows](02-domain/WORKFLOWS.md) |
| P3 adversarial review, aggregate traceability and validation evidence | [P3 quality gate](02-domain/P3_QUALITY_GATE.md) |
| Logical database design: records, derived-versus-authoritative data, guard registry, constraints, snapshots, scope, types, indexes, transaction map | [Database](03-architecture/DATABASE.md) (APPROVED, APPR-005) |
| Application structure: modules, dependency tiers, actions, posting services, web/React/job/file/integration/reporting boundaries | [Architecture](03-architecture/ARCHITECTURE.md) (APPROVED, APPR-005) |
| P4 adversarial review, targeted-review dispositions (RT-01–37), re-test, traceability and validation evidence | [P4 quality gate](03-architecture/P4_QUALITY_GATE.md) |
| P4 targeted review, corrections, conditional approval, checkpoint and publication | [Targeted review directive](00-governance/sources/P4_OWNER_TARGETED_REVIEW_DIRECTIVE_2026-09-30.txt); [Owner finalization](00-governance/sources/P4_OWNER_CORRECTION_APPROVAL_AND_PUBLICATION_2026-09-30.txt); [DIR-028/029, TECH-018](00-governance/DECISION_LOG.md#dir-028-obs-008-dir-029-and-tech-018--p4-targeted-review-corrections-and-finalization); [APPR-005](00-governance/DECISION_LOG.md#appr-005--p4-database-architecture-approved) |
| P4 fast-track authorization and the unattributed-loss clarification (no freezing of physical stock) | [P4 authorization](00-governance/sources/P4_OWNER_AUTHORIZATION_2026-09-29.txt); [DIR-027/OBS-007/TECH-017](00-governance/DECISION_LOG.md#dir-027-obs-007-and-tech-017--p4-authorization-unattributed-loss-clarification-and-p4-documentation) |
| P3 authorization, Owner decisions D-1–D-5 and documentation-execution boundary | [P3 authorization](00-governance/sources/P3_OWNER_AUTHORIZATION_2026-09-29.txt); [P3 Owner decisions](00-governance/sources/P3_OWNER_DECISIONS_2026-09-29.txt); [DIR-023/024](00-governance/DECISION_LOG.md#dir-023-dir-024-and-tech-015--p3-authorization-owner-decisions-and-documentation-execution) |
| Targeted conservation review, Owner unexplained-loss attribution decision, P3 approval and checkpoint/push boundary | [Targeted review directive](00-governance/sources/P3_OWNER_TARGETED_REVIEW_DIRECTIVE_2026-09-29.txt); [Owner decision and finalization](00-governance/sources/P3_OWNER_LOSS_ATTRIBUTION_AND_FINALIZATION_2026-09-29.txt); [DIR-025/026, TECH-016](00-governance/DECISION_LOG.md#dir-025-obs-006-dir-026-and-tech-016--targeted-review-owner-loss-attribution-decision-and-corrections); [APPR-004](00-governance/DECISION_LOG.md#appr-004--p3-critical-business-workflows-approved) |
| P2 authorization, deep review, dates/numbering, final and fee-generalization decisions | [DIR-016–020 sources](00-governance/SOURCE_OF_TRUTH.md#p2-authorization-deep-review-and-final-decisions) |
| Explicit P2 approval, archived checkpoint-publication authorization and finalization boundary | [P2 Owner approval](00-governance/sources/P2_OWNER_APPROVAL_2026-09-29.txt); [APPR-003](00-governance/DECISION_LOG.md#appr-003--p2-domain-baseline-approved) |
| Live handoff, blockers, verification state | [Current state](07-handoff/CURRENT_STATE.md) |
| Exact next task and input questions | [Next action](07-handoff/NEXT_ACTION.md) |
| Accepted governance decision and rationale | [ADR-001](adr/ADR-001-repository-governance.md) |
| Original owner requirements, all 58 sections | [Owner brief](00-governance/sources/OWNER_BRIEF_2026-09-27.txt) |
| Owner delegation and active gap-hunting mandate | [Architect mandate](00-governance/sources/ARCHITECT_MANDATE_2026-09-27.txt) |
| P0 acceptance, P1 scope and latest product constraints | [P1 owner directive](00-governance/sources/P1_OWNER_DIRECTIVE_2026-09-27.txt) |
| Document breadth, validators and VPS facts | [P1 owner clarifications](00-governance/sources/P1_OWNER_CLARIFICATIONS_2026-09-27.txt) |
| Latest reference-ingestion instructions and phase boundary | [Reference-ingestion directive](00-governance/sources/P1_REFERENCE_INGESTION_DIRECTIVE_2026-09-27.txt) |
| Exact PDF/image archive, hashes and source hierarchy | [Reference provenance](00-governance/SOURCE_OF_TRUTH.md#owner-reference-ingestion-and-provenance) |
| Latest local-only backup decision, scoped targets and accepted risk | [Owner backup policy](00-governance/sources/P1_OWNER_BACKUP_POLICY_2026-09-27.txt); [local backup contract](01-product/V1_SCOPE.md#v1-local-backup-and-p9-handoff-contract) |
| Final P1 financial, visibility, completion, reservation and attribution decisions | [Final Owner decisions](00-governance/sources/P1_FINAL_OWNER_DECISIONS_2026-09-28.txt); [canonical business contract](01-product/V1_SCOPE.md#final-owner-business-decisions) |
| Previous recovery, scope preservation and Git boundary | [Recovery instruction](00-governance/sources/P1_FINAL_DECISIONS_RECOVERY_2026-09-28.txt); [latest audit](01-product/P1_QUALITY_GATE.md#final-decision-interruption-recovery--dir-012) |
| Explicit P1 product approval and local checkpoint/remote inspection boundary | [Owner approval](00-governance/sources/P1_OWNER_APPROVAL_2026-09-28.txt); [APPR-002](00-governance/DECISION_LOG.md#appr-002--p1-product-definition-and-v1-scope-approved) |
| Latest local checkpoint authorization and stop-on-rejection boundary | [DIR-014 source](00-governance/sources/P1_OWNER_CHECKPOINT_AUTHORIZATION_2026-09-28.txt) |
| Owner publication confirmation, receiver verification and bounded continuity authorization | [DIR-015 source](00-governance/sources/P1_OWNER_PUBLICATION_CONFIRMATION_2026-09-29.txt); [OBS-003](00-governance/DECISION_LOG.md#dir-015-and-obs-003--p1-publication-recorded-and-receiver-verification) |

The [ownership registry](00-governance/SOURCE_OF_TRUTH.md#canonical-ownership-registry) distinguishes existing P0–P4 files from reserved P5–P11 concern paths. Future specifications do not exist yet; create them only in their authorized phase.

For this handoff, a replacement agent reads AGENTS.md, this index, [current state](07-handoff/CURRENT_STATE.md) and [next action](07-handoff/NEXT_ACTION.md), then the canonical specifications in order — product (P1), domain (P2), workflows (P3), database and architecture (P4) — and uses the twenty-six owner source records as provenance, never as new task authorization. Approval chain: P0 APPR-001; P1 APPR-002; P2 APPR-003 (checkpoint `1392966bfb89581d705e0394424705978e1d3db8`); P3 APPR-004 (checkpoint `7c6549e88ba8538aa6e08d0fb9720589705e1dd0`); P4 APPR-005 — DATABASE and ARCHITECTURE APPROVED after the targeted Fable review DIR-028 and the corrections TECH-018 under DIR-029, together with the `DIR-027`-amended WORKFLOWS, BUSINESS_RULES and DOMAIN_MODEL revisions, checkpointed by the commit `docs: finalize P4 database architecture` (child of `7c6549e`) and published by a normal push. Apply the DIR-008 source hierarchy; the latest explicit Owner decision within a subject wins (DIR-009, DIR-011, DIR-018–020, DIR-024, DIR-026 and its DIR-027 clarification). Quality gates, coverage, gaps, logs and the handoff remain REVIEW evidence. P5 is NOT STARTED and requires a separate Owner authorization after the handoff/bootstrap task. Use canonical specifications rather than conversation memory. Phase-end review and the pre-build challenge are owned by the [operating model](00-governance/AGENT_OPERATING_MODEL.md#continuous-adversarial-review).

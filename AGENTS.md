# Agent entry point

Status: APPROVED | Updated: 2026-09-30 | Owner: Planning

Approval: [APPR-001](docs/00-governance/DECISION_LOG.md), P0 governance at b425584. Subsequent directives and continuity updates remain recorded in the decision log. [APPR-002](docs/00-governance/DECISION_LOG.md#appr-002--p1-product-definition-and-v1-scope-approved) approves the three P1 canonical product specifications, [APPR-003](docs/00-governance/DECISION_LOG.md#appr-003--p2-domain-baseline-approved) the two P2 normative domain specifications and [APPR-004](docs/00-governance/DECISION_LOG.md#appr-004--p3-critical-business-workflows-approved) the P3 workflow specification with its amended P1/P2 revisions and [APPR-005](docs/00-governance/DECISION_LOG.md#appr-005--p4-database-architecture-approved) the two P4 normative architecture specifications with the amended P2/P3 revisions; evidence, gap and handoff records remain REVIEW.

These instructions route every planning or execution agent to the same repository context. Owner sources and precedence are recorded in [SOURCE_OF_TRUTH](docs/00-governance/SOURCE_OF_TRUTH.md). The P1 directive accepts the P0 governance baseline; the latest final Owner decisions also authorize P1 only. Review status does not suspend explicit owner instructions or delegated Level 1 planning improvements, and does not authorize implementation.

## Start every session

1. Read this file, [CONTEXT_INDEX](docs/CONTEXT_INDEX.md), and [CURRENT_STATE](docs/07-handoff/CURRENT_STATE.md).
2. Check the actual repository, branch, commit, and working tree. Preserve existing changes; report differences from the handoff.
3. Read the relevant canonical concern documents, their status and approval evidence, related ADRs, and the current task in [NEXT_ACTION](docs/07-handoff/NEXT_ACTION.md).
4. Establish the authorized role, phase, and bounded task using the [agent operating model](docs/00-governance/AGENT_OPERATING_MODEL.md). Do not infer authority from a model name or a previous agent's confidence.
5. Resolve blocking contradictions under [SOURCE_OF_TRUTH](docs/00-governance/SOURCE_OF_TRUTH.md) before dependent work.

## Work within the authorized phase

Read the current phase/task in [CURRENT_STATE](docs/07-handoff/CURRENT_STATE.md). **P2 — Domain Model & Business Rules is complete**: [DOMAIN_MODEL](docs/02-domain/DOMAIN_MODEL.md) and [BUSINESS_RULES](docs/02-domain/BUSINESS_RULES.md) are APPROVED under APPR-003, checkpointed and published; [P2_QUALITY_GATE](docs/02-domain/P2_QUALITY_GATE.md) remains REVIEW evidence. **P3 — Critical Business Workflows is complete** (DIR-023–026): [WORKFLOWS](docs/02-domain/WORKFLOWS.md) is APPROVED under APPR-004 together with the narrow `DIR-024`/`DIR-026`/`TECH-016` amendments to approved P1/P2 files, checkpointed by the P3 finalization commit whose normal push DIR-026 authorizes; [P3_QUALITY_GATE](docs/02-domain/P3_QUALITY_GATE.md) remains REVIEW evidence. **P4 — Database Architecture is complete** (DIR-027–029): authorized by the fast-track directive DIR-027, which also clarifies DIR-026 (a pending unexplained-loss case never freezes physical stock), red-teamed under DIR-028 and corrected under DIR-029; [DATABASE](docs/03-architecture/DATABASE.md) and [ARCHITECTURE](docs/03-architecture/ARCHITECTURE.md) are APPROVED under APPR-005 with the `DIR-027`-amended WORKFLOWS, BUSINESS_RULES and DOMAIN_MODEL revisions, checkpointed by the commit `docs: finalize P4 database architecture` and published; [P4_QUALITY_GATE](docs/03-architecture/P4_QUALITY_GATE.md) remains REVIEW evidence. **P5 has NOT STARTED**; it begins only after a separate handoff/bootstrap task and its own Owner authorization. Record later-phase risks without designing permission matrices, concurrency mechanisms, UI or implementation. Migrations, application code, Laravel scaffolding, installation and infrastructure setup remain unauthorized.

Follow the [engineering principles](docs/00-governance/ENGINEERING_PRINCIPLES.md), [change control](docs/00-governance/CHANGE_CONTROL.md), and [documentation ownership/lifecycle](docs/00-governance/SOURCE_OF_TRUTH.md). Canonical rules belong in their owning documents; link instead of copying them into tool-specific files.

Actively challenge assumptions and simplify planning. Classify findings under the [three authority levels](docs/00-governance/CHANGE_CONTROL.md#decision-authority): apply Level 1 improvements directly; flag Level 2 decisions; do not override Level 3 owner intent. Maintain the [gap register](docs/00-governance/GAP_REGISTER.md) and perform the [phase review and executor feedback loop](docs/00-governance/AGENT_OPERATING_MODEL.md#continuous-adversarial-review). Do not turn REVIEW labels into an approval request for an already-authorized technical correction.

Use current official documentation when exact framework behavior matters; use Context7 if available. Record relevant version and source evidence in the affected task. P1 is product planning and does not require inventing framework APIs or choosing versions.

Do not launch executor work merely because planning files exist. The execution eligibility rules are in the [operating model](docs/00-governance/AGENT_OPERATING_MODEL.md).

## End every session

Use the [session-end checklist](docs/00-governance/AGENT_OPERATING_MODEL.md#session-end-checklist). Record actual changes, verification evidence, unresolved issues, and the next safe action in the repository before relying on a chat summary.

## Git commit attribution

Every commit uses only the Owner's existing configured Git author and committer identity. Never name an AI model, agent, coding assistant or automation tool as author, committer or co-author, and never add `Co-Authored-By`, `Generated-By`, `Assisted-By` or equivalent AI-attribution trailers; commit messages contain only repository-relevant content. Historical commits are not rewritten. Owner directive: [DIR-022](docs/00-governance/DECISION_LOG.md#dir-022--permanent-git-commit-attribution-rule).

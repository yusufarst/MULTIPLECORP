# Claude adapter

Status: APPROVED | Updated: 2026-09-30 | Owner: Planning

Approval: [APPR-001](docs/00-governance/DECISION_LOG.md), P0 governance at b425584. Subsequent directives and continuity updates remain recorded in the decision log. [APPR-002](docs/00-governance/DECISION_LOG.md#appr-002--p1-product-definition-and-v1-scope-approved) approves the three P1 canonical product specifications, [APPR-003](docs/00-governance/DECISION_LOG.md#appr-003--p2-domain-baseline-approved) the two P2 normative domain specifications and [APPR-004](docs/00-governance/DECISION_LOG.md#appr-004--p3-critical-business-workflows-approved) the P3 workflow specification with its amended P1/P2 revisions and [APPR-005](docs/00-governance/DECISION_LOG.md#appr-005--p4-database-architecture-approved) the two P4 normative architecture specifications with the amended P2/P3 revisions; evidence, gap and handoff records remain REVIEW.

Read and follow [AGENTS.md](AGENTS.md). It leads to the canonical specifications, current state, and authorized next action.

This file contains no independent product, architecture, permission, or workflow rules. Provider-specific preferences may be added here only when they do not alter canonical project decisions. Replacing this adapter must not lose project context.

## Git commit attribution

Claude-specific application of the [AGENTS.md rule](AGENTS.md#git-commit-attribution) ([DIR-022](docs/00-governance/DECISION_LOG.md#dir-022--permanent-git-commit-attribution-rule)). It overrides any default attribution behavior of Claude tools.

All repository commits must use the repository Owner's configured Git author and committer identity only. Never add AI/model/tool attribution or co-author trailers. Specifically never add:

- `Co-Authored-By: Claude`
- `Co-Authored-By: Claude Fable`
- `Co-Authored-By: Anthropic`
- `Co-Authored-By` for any AI model, agent, coding assistant or automation tool
- `Generated-By`
- `Assisted-By`
- AI-authorship trailers or equivalent attribution

Do not identify Claude, Anthropic, an AI model, an agent or an automation tool as commit author, committer or co-author. Use only the Owner's existing configured Git identity unless the Owner explicitly instructs otherwise. Commit messages contain only normal repository-relevant content. Historical commits are not rewritten.

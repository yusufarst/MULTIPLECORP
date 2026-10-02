# Claude adapter

Status: APPROVED | Updated: 2026-10-03 | Owner: Planning

Approval: [APPR-001](docs/00-governance/DECISION_LOG.md#appr-001--p0-governance-accepted), P0 governance at b425584; later approvals are indexed in the [approval register](docs/00-governance/DECISION_LOG.md#approval-register). Amendment — REVIEW, pending Owner approval: this approval paragraph was shortened on 2026-10-03 (DIR-039; [TECH-023](docs/00-governance/DECISION_LOG.md#dir-034039-obs-013-and-tech-023--aicwdf-adoption-directives-migration-baseline-and-structural-migration)).

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

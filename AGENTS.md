# Agent entry point

Status: APPROVED | Updated: 2026-10-03 | Owner: Planning

Approval: [APPR-001](docs/00-governance/DECISION_LOG.md#appr-001--p0-governance-accepted); later approvals are indexed in the [approval register](docs/00-governance/DECISION_LOG.md#approval-register). Amendment — REVIEW, pending Owner approval: rewritten on 2026-10-03 for the AICWDF v4.3 operating structure (DIR-039; [TECH-023](docs/00-governance/DECISION_LOG.md#dir-034039-obs-013-and-tech-023--aicwdf-adoption-directives-migration-baseline-and-structural-migration)).

This file routes every planning, execution, review and release agent to the same repository context. The repository is the source of truth; chat, memory and generated output are not.

## Read in this order

1. This file.
2. [EXECUTION_CONTEXT](docs/11-tasks/EXECUTION_CONTEXT.md) — the stable minimum context.
3. [CURRENT_HANDOFF](docs/handoff/CURRENT_HANDOFF.md) — the current operational state and the safe next action.
4. [PHASE_STATUS](docs/PHASE_STATUS.md) — phase progress; once P11 exists, also the Task plan.
5. The active Task — before P11, the Owner's current authorization — and then only the exact sections it references, found through the [context index](docs/CONTEXT_INDEX.md).

Check the actual branch, commit and working tree before acting; preserve existing changes and report any difference from the handoff.

## Authority

Every phase needs its own explicit Owner authorization, quality gate, approval and checkpoint, and nothing beyond the authorized task is started ([phase authorization](docs/00-governance/AGENT_OPERATING_MODEL.md#phase-authorization)). Classify findings under the [three authority levels](docs/00-governance/CHANGE_CONTROL.md#decision-authority): apply Level 1 improvements directly; flag Level 2 decisions; do not override Level 3 owner intent. Resolve contradictions with the [source hierarchy](docs/00-governance/SOURCE_OF_TRUTH.md#owner-reference-ingestion-and-provenance). Follow the [engineering principles](docs/00-governance/ENGINEERING_PRINCIPLES.md) and the [document lifecycle](docs/00-governance/SOURCE_OF_TRUTH.md#document-lifecycle); canonical rules belong in their owning documents — link, do not copy. Maintain the [gap register](docs/00-governance/GAP_REGISTER.md) and run the [phase review and executor feedback loop](docs/00-governance/AGENT_OPERATING_MODEL.md#continuous-adversarial-review).

## Rules (AICWDF §34, adapted)

1. The repository is the source of truth; write a chat decision into its owning document before relying on it — [SOURCE_OF_TRUTH](docs/00-governance/SOURCE_OF_TRUTH.md#authority-and-provenance).
2. Preserve approved decisions, approval chains and source records; reopen a binding decision only with the Owner — [DECISION_INDEX](docs/00-governance/DECISION_INDEX.md).
3. Inspect and reuse existing documents before creating replacements; one owner per concern — [ownership registry](docs/00-governance/SOURCE_OF_TRUTH.md#canonical-ownership-registry).
4. Before coding read EXECUTION_CONTEXT, CURRENT_HANDOFF, the Task plan and the active Task, then only the exact referenced sections — [execution flow](docs/00-governance/AGENT_OPERATING_MODEL.md#execution-flow).
5. Work only on a READY Task, one at a time unless PARALLEL SAFE; no Task exists before P11 — [Task model](docs/00-governance/AGENT_OPERATING_MODEL.md#task-model).
6. A Task is DONE only when its implementation is complete, its required tests pass and its completion evidence exists — [Task model](docs/00-governance/AGENT_OPERATING_MODEL.md#task-model).
7. On DONE, check the Task in the Task plan, update the counters and update CURRENT_HANDOFF before stopping — [session-end checklist](docs/00-governance/AGENT_OPERATING_MODEL.md#session-end-checklist).
8. Use current official documentation, through Context7 when available, for version-sensitive behaviour, and targeted impact analysis for significant changes — [TOOLCHAIN](docs/00-governance/TOOLCHAIN.md).
9. Install or configure a safe development tool only inside an authorized task, reuse an existing equivalent, and activate MCP servers only on demand — [TOOLCHAIN](docs/00-governance/TOOLCHAIN.md#mcp-servers).
10. Use shadcn/ui before new primitives and follow the active design reference; the visual baseline is being replaced in P7 — [PHASE_STATUS](docs/PHASE_STATUS.md).
11. Mobile-first, responsive and adaptive, with desktop productivity — [V1_SCOPE](docs/01-product/V1_SCOPE.md#language-and-experience-contract).
12. No dead button, route, form, link or interaction; a clear contextual back action for every child flow; no redundant action — AICWDF §18.3–§18.5, §25; contracts pending in [GAP-035](docs/00-governance/GAP_REGISTER.md#gap-035--route-and-interaction-contracts-do-not-exist-yet).
13. User-facing UI defaults to plain Indonesian with English available; business documents and data stay Indonesian — [V1_SCOPE CAP-14](docs/01-product/V1_SCOPE.md#must-ship).
14. Additional recurring production cost target 0 outside the client-funded domain and VPS; a paid dependency needs explicit Owner approval — [COST_POLICY](docs/00-governance/COST_POLICY.md).
15. Direct production database access is forbidden to agents — [PRODUCTION_DATA_SAFETY](docs/00-governance/PRODUCTION_DATA_SAFETY.md).
16. Reuse the recorded authentication profile — password AICWDF-COMPAT-8 with the blocklist and mandatory TOTP, Google sign-in for existing accounts only — and never redesign it per Task — [EXECUTION_CONTEXT](docs/11-tasks/EXECUTION_CONTEXT.md#auth-profile--decided-security-amendment-pending).
17. Do not silently change the stack, architecture, scope, schema or design direction — [CHANGE_CONTROL](docs/00-governance/CHANGE_CONTROL.md#decision-authority).
18. End every session with the checklist and record changes, evidence, open issues and the next safe action in the repository — [session-end checklist](docs/00-governance/AGENT_OPERATING_MODEL.md#session-end-checklist).

## Public repository

[yusufarst/MULTIPLECORP](https://github.com/yusufarst/MULTIPLECORP) is public. Never commit real client or personal data, secrets, credentials, live `.env` files or production configuration; follow the [data and production safety rules](docs/00-governance/ENGINEERING_PRINCIPLES.md#secrets-public-repository-and-production-boundary).

## Git commit attribution

Every commit uses only the Owner's existing configured Git author and committer identity. Never name an AI model, agent, coding assistant or automation tool as author, committer or co-author, and never add `Co-Authored-By`, `Generated-By`, `Assisted-By` or equivalent AI-attribution trailers; commit messages contain only repository-relevant content. Historical commits are not rewritten. Owner directive: [DIR-022](docs/00-governance/DECISION_LOG.md#dir-022--permanent-git-commit-attribution-rule).

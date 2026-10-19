# Agent entry point

Status: APPROVED | Updated: 2026-10-20 | Owner: Planning

Approval: [APPR-001](docs/00-governance/DECISION_LOG.md#appr-001--p0-governance-accepted); later approvals are indexed in the [approval register](docs/00-governance/DECISION_LOG.md#approval-register). Amendment: rewritten on 2026-10-03 for the AICWDF v4.3 operating structure (DIR-039; [TECH-023](docs/00-governance/DECISION_LOG.md#dir-034039-obs-013-and-tech-023--aicwdf-adoption-directives-migration-baseline-and-structural-migration), where its hashes are recorded); the amended revision is approved under [APPR-009](docs/00-governance/DECISION_LOG.md#appr-009--aicwdf-structural-migration-approved).

Amendment: on 2026-10-13 rule 10's pointer names the active design reference — the Golden Frontend Reference v1.0.0, recorded in DESIGN_REFERENCES and non-normative until Stage 3 of the P7 re-baseline ([DIR-059](docs/00-governance/DECISION_LOG.md#dir-059-dir-060-obs-022-and-tech-030--p7-ux-re-baseline-checkpoint-c1-closure-golden-frontend-reference-adoption-authorization-baseline-and-reconciliation)); no other rule changes. It is a narrow continuity record authorized by [DIR-060](docs/00-governance/DECISION_LOG.md#dir-059-dir-060-obs-022-and-tech-030--p7-ux-re-baseline-checkpoint-c1-closure-golden-frontend-reference-adoption-authorization-baseline-and-reconciliation) §14 ([TECH-030](docs/00-governance/DECISION_LOG.md#dir-059-dir-060-obs-022-and-tech-030--p7-ux-re-baseline-checkpoint-c1-closure-golden-frontend-reference-adoption-authorization-baseline-and-reconciliation), where its hashes are recorded), and the document keeps the APPROVED status of the last approved revision (APPR-009).

Amendment: on 2026-10-14 rule 10 states that the Golden Frontend Reference v1.0.0 governs presentation only, behaviour always following the canonical specifications (DIR-061 SD-02), and that it stays non-normative until Stage 3 replaces the normative documents and Stage 5 grants APPR-011 (DIR-059 GD-01); no other rule changes. It is a narrow continuity record authorized by [DIR-064](docs/00-governance/DECISION_LOG.md#dir-063-dir-064-obs-024-and-tech-032--p7-ux-re-baseline-s2-0-acceptance-s2-1-authorization-baseline-and-stage-2-documents) ([TECH-032](docs/00-governance/DECISION_LOG.md#dir-063-dir-064-obs-024-and-tech-032--p7-ux-re-baseline-s2-0-acceptance-s2-1-authorization-baseline-and-stage-2-documents), where its hashes are recorded), and the document keeps the APPROVED status of the last approved revision (APPR-009).

Amendment: on 2026-10-20 the reading order's items 4 and 5 and rules 5, 7, 10, 12 and 17 name the Task plan, the active Task, the scope rule of DIR-083 OE-20, the operator's and the Owner's Tasks, the Task plan's progress rule and the approved design system and contracts the Tasks build from, which P11's existence requires; no other rule changes. It is a narrow continuity record authorized by [DIR-084](docs/00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99 and SP-100 (TECH-040, where its hashes are recorded), and the document keeps the APPROVED status of the last approved revision (APPR-009).

This file routes every planning, execution, review and release agent to the same repository context. The repository is the source of truth; chat, memory and generated output are not.

## Read in this order

1. This file.
2. [EXECUTION_CONTEXT](docs/11-tasks/EXECUTION_CONTEXT.md) — the stable minimum context.
3. [CURRENT_HANDOFF](docs/handoff/CURRENT_HANDOFF.md) — the current operational state and the safe next action.
4. [PHASE_STATUS](docs/PHASE_STATUS.md) — phase progress — and the [Task plan](docs/11-tasks/TASK_PLAN.md), P11's execution dashboard.
5. The active Task — its contract `docs/11-tasks/TASK-nnn.md`, once the Owner's execution authorization at OG-4 is given; until then the Owner's current authorization — and then only the exact sections it references, found through the [context index](docs/CONTEXT_INDEX.md).

Check the actual branch, commit and working tree before acting; preserve existing changes and report any difference from the handoff.

## Authority

Every phase needs its own explicit Owner authorization, quality gate, approval and checkpoint, and nothing beyond the authorized task is started ([phase authorization](docs/00-governance/AGENT_OPERATING_MODEL.md#phase-authorization)). Classify findings under the [three authority levels](docs/00-governance/CHANGE_CONTROL.md#decision-authority): apply Level 1 improvements directly; flag Level 2 decisions; do not override Level 3 owner intent. Resolve contradictions with the [source hierarchy](docs/00-governance/SOURCE_OF_TRUTH.md#owner-reference-ingestion-and-provenance). Follow the [engineering principles](docs/00-governance/ENGINEERING_PRINCIPLES.md) and the [document lifecycle](docs/00-governance/SOURCE_OF_TRUTH.md#document-lifecycle); canonical rules belong in their owning documents — link, do not copy. Maintain the [gap register](docs/00-governance/GAP_REGISTER.md) and run the [phase review and executor feedback loop](docs/00-governance/AGENT_OPERATING_MODEL.md#continuous-adversarial-review).

## Rules (AICWDF §34, adapted)

1. The repository is the source of truth; write a chat decision into its owning document before relying on it — [SOURCE_OF_TRUTH](docs/00-governance/SOURCE_OF_TRUTH.md#authority-and-provenance).
2. Preserve approved decisions, approval chains and source records; reopen a binding decision only with the Owner — [DECISION_INDEX](docs/00-governance/DECISION_INDEX.md).
3. Inspect and reuse existing documents before creating replacements; one owner per concern — [ownership registry](docs/00-governance/SOURCE_OF_TRUTH.md#canonical-ownership-registry).
4. Before coding read EXECUTION_CONTEXT, CURRENT_HANDOFF, the Task plan and the active Task, then only the exact referenced sections — [execution flow](docs/00-governance/AGENT_OPERATING_MODEL.md#execution-flow).
5. Work only on a READY Task, one at a time unless PARALLEL SAFE; no Task starts before the Owner's execution authorization at OG-4, and a Task whose EXECUTOR is HUMAN OPERATOR or OWNER is never run by an agent — [Task model](docs/00-governance/AGENT_OPERATING_MODEL.md#task-model); [Task plan](docs/11-tasks/TASK_PLAN.md#2-rules-of-the-plan).
6. A Task is DONE only when its implementation is complete, its required tests pass and its completion evidence exists — [Task model](docs/00-governance/AGENT_OPERATING_MODEL.md#task-model).
7. On DONE, set the Task's status and check it in the Task plan, re-check the READY gate of the Tasks it blocks, update the counters and update CURRENT_HANDOFF before stopping — [session-end checklist](docs/00-governance/AGENT_OPERATING_MODEL.md#session-end-checklist); [Task plan §2](docs/11-tasks/TASK_PLAN.md#2-rules-of-the-plan).
8. Use current official documentation, through Context7 when available, for version-sensitive behaviour, and targeted impact analysis for significant changes — [TOOLCHAIN](docs/00-governance/TOOLCHAIN.md).
9. Install or configure a safe development tool only inside an authorized task, reuse an existing equivalent, and activate MCP servers only on demand — [TOOLCHAIN](docs/00-governance/TOOLCHAIN.md#mcp-servers).
10. Use shadcn/ui before new primitives and follow [DESIGN_SYSTEM](docs/07-ux-design/DESIGN_SYSTEM.md), normative under APPR-011, with [the design reference](docs/07-ux-design/DESIGN_REFERENCES.md) — the Golden Frontend Reference v1.0.0 — for presentation only, behaviour always following the canonical specifications (DIR-061 SD-02).
11. Mobile-first, responsive and adaptive, with desktop productivity — [V1_SCOPE](docs/01-product/V1_SCOPE.md#language-and-experience-contract).
12. No dead button, route, form, link or interaction; a clear contextual back action for every child flow; no redundant action — AICWDF §18.3–§18.5, §25; each Task cites its rows of [ROUTE_CONTRACTS](docs/03-workflows/ROUTE_CONTRACTS.md), [INTERACTION_CONTRACTS](docs/03-workflows/INTERACTION_CONTRACTS.md) and [NAVIGATION_CONTRACTS](docs/07-ux-design/NAVIGATION_CONTRACTS.md), approved under APPR-011, and [GAP-035](docs/00-governance/GAP_REGISTER.md#gap-035--route-and-interaction-contracts-do-not-exist-yet) stays open for their tests.
13. User-facing UI defaults to plain Indonesian with English available; business documents and data stay Indonesian — [V1_SCOPE CAP-14](docs/01-product/V1_SCOPE.md#must-ship).
14. Additional recurring production cost target 0 outside the client-funded domain and VPS; a paid dependency needs explicit Owner approval — [COST_POLICY](docs/00-governance/COST_POLICY.md).
15. Direct production database access is forbidden to agents — [PRODUCTION_DATA_SAFETY](docs/00-governance/PRODUCTION_DATA_SAFETY.md).
16. Reuse the recorded authentication profile — password AICWDF-COMPAT-8 with the blocklist and mandatory TOTP, Google sign-in for existing accounts only — and never redesign it per Task — [EXECUTION_CONTEXT](docs/11-tasks/EXECUTION_CONTEXT.md#auth-profile--in-force-under-appr-010).
17. Do not silently change the stack, architecture, scope, schema or design direction; no Task adds to V1 — a capability, page, route, field, integration, document type, report or paid service — or drops, defers or narrows a V1_REQUIRED requirement: it raises an Owner question and waits (DIR-083 OE-20) — [CHANGE_CONTROL](docs/00-governance/CHANGE_CONTROL.md#decision-authority).
18. End every session with the checklist and record changes, evidence, open issues and the next safe action in the repository — [session-end checklist](docs/00-governance/AGENT_OPERATING_MODEL.md#session-end-checklist).

## Public repository

[yusufarst/MULTIPLECORP](https://github.com/yusufarst/MULTIPLECORP) is public. Never commit real client or personal data, secrets, credentials, live `.env` files or production configuration; follow the [data and production safety rules](docs/00-governance/ENGINEERING_PRINCIPLES.md#secrets-public-repository-and-production-boundary).

## Git commit attribution

Every commit uses only the Owner's existing configured Git author and committer identity. Never name an AI model, agent, coding assistant or automation tool as author, committer or co-author, and never add `Co-Authored-By`, `Generated-By`, `Assisted-By` or equivalent AI-attribution trailers; commit messages contain only repository-relevant content. Historical commits are not rewritten. Owner directive: [DIR-022](docs/00-governance/DECISION_LOG.md#dir-022--permanent-git-commit-attribution-rule).

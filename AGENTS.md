# Agent entry point

Status: REVIEW | Updated: 2026-09-27 | Owner: Planning

These instructions route every planning or execution agent to the same repository context. The [original brief](docs/00-governance/sources/OWNER_BRIEF_2026-09-27.txt) and latest [architect autonomy mandate](docs/00-governance/sources/ARCHITECT_MANDATE_2026-09-27.txt) establish owner authority. REVIEW does not suspend explicit owner instructions or delegated Level 1 planning improvements. It also does not approve business scope or authorize implementation.

## Start every session

1. Read this file, [CONTEXT_INDEX](docs/CONTEXT_INDEX.md), and [CURRENT_STATE](docs/07-handoff/CURRENT_STATE.md).
2. Check the actual repository, branch, commit, and working tree. Preserve existing changes; report differences from the handoff.
3. Read the relevant canonical concern documents, their status and approval evidence, related ADRs, and the current task in [NEXT_ACTION](docs/07-handoff/NEXT_ACTION.md).
4. Establish the authorized role, phase, and bounded task using the [agent operating model](docs/00-governance/AGENT_OPERATING_MODEL.md). Do not infer authority from a model name or a previous agent's confidence.
5. Resolve blocking contradictions under [SOURCE_OF_TRUTH](docs/00-governance/SOURCE_OF_TRUTH.md) before dependent work.

## Work within the authorized phase

Read the current phase/task in [CURRENT_STATE](docs/07-handoff/CURRENT_STATE.md). The latest assignment extends P0 with architect autonomy, proactive gap discovery, and corrections to governance. Record later-phase risks now without treating them as settled business specifications. Application implementation, installation, scaffolding and infrastructure setup remain outside the planning role.

Follow the [engineering principles](docs/00-governance/ENGINEERING_PRINCIPLES.md), [change control](docs/00-governance/CHANGE_CONTROL.md), and [documentation ownership/lifecycle](docs/00-governance/SOURCE_OF_TRUTH.md). Canonical rules belong in their owning documents; link instead of copying them into tool-specific files.

Actively challenge assumptions and simplify planning. Classify findings under the [three authority levels](docs/00-governance/CHANGE_CONTROL.md#decision-authority): apply Level 1 improvements directly; flag Level 2 decisions; do not override Level 3 owner intent. Maintain the [gap register](docs/00-governance/GAP_REGISTER.md) and perform the [phase review and executor feedback loop](docs/00-governance/AGENT_OPERATING_MODEL.md#continuous-adversarial-review). Do not turn REVIEW labels into an approval request for an already-authorized technical correction.

Use current official documentation when exact framework behavior matters; use Context7 if available. Record relevant version and source evidence in the affected task. P0 requires no framework selection or API investigation.

Do not launch executor work merely because planning files exist. The execution eligibility rules are in the [operating model](docs/00-governance/AGENT_OPERATING_MODEL.md).

## End every session

Use the [session-end checklist](docs/00-governance/AGENT_OPERATING_MODEL.md#session-end-checklist). Record actual changes, verification evidence, unresolved issues, and the next safe action in the repository before relying on a chat summary.

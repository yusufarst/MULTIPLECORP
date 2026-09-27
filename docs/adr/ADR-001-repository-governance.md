# ADR-001 — Repository-owned governance and agent continuity

Status: ACCEPTED | Date: 2026-09-27 | Owner: Planning

Approval: [APPR-001](../00-governance/DECISION_LOG.md), owner acceptance of P0 governance at `b425584daa80af2c1342252407c95e08024d3173`. No accepted predecessor. Acceptance does not authorize application execution.

## Context

The owner requires agents to be replaceable without losing business or engineering decisions. The repository was empty at inspection. The delivery window is short; documentation must remain purposeful. Brief §§1, 48–57 establish the governing direction but do not approve every new procedural detail.

## Decision

Use root `AGENTS.md` as the common entry point and a thin `CLAUDE.md` adapter. Keep concern specifications under `docs/`, assign each concern an owning document, and use links instead of repeated rules. Preserve the complete supplied brief as immutable provenance while progressively formalizing requirements in authorized phases.

Use status/approval evidence for specifications, a decision log and ADR history for significant decisions, and concise current-state/next-action records for handoff. Apply the owner's delegated technical authority and maintain one gap register with continuous review and executor feedback. Reserve later concern paths without scaffolding empty documents or application code.

The operative details belong to [SOURCE_OF_TRUTH](../00-governance/SOURCE_OF_TRUTH.md), [AGENT_OPERATING_MODEL](../00-governance/AGENT_OPERATING_MODEL.md), and [CHANGE_CONTROL](../00-governance/CHANGE_CONTROL.md).

## Alternatives

- Chat or vendor-specific memory as canonical state: rejected as a proposal because replacement agents would lack durable context and the owner explicitly prohibits it.
- One constantly rewritten giant brief: retained as source evidence, but unsuitable as the sole working specification because concern ownership and approval boundaries become unclear.
- Full P0–P11 document scaffolding immediately: deferred because it creates unused artifacts and exceeds this phase's authorization.

## Consequences

Continuity no longer requires a particular provider. Reviews can identify which rules and revisions authorize work. The tradeoff is that agents must maintain links, approval evidence, and handoff state; stale records are defects to correct. A committed/published checkpoint is needed before agents on another checkout can use the same state.

This governance decision does not approve application architecture details, authorize implementation, or claim production readiness. Future supersession follows the [ADR contract](../00-governance/CHANGE_CONTROL.md#adr-contract).

## Proposal revision — 2026-09-27

The owner supplied an autonomy mandate after the first P0 draft. Revised this still-PROPOSED ADR to remove the implication that every meaningful technical correction needs owner approval. Authority classification is owned by change control; DIR-005/TECH-001 record the source and applied correction. No new ADR is needed for this clarification, and no historical accepted decision was overwritten.

## Acceptance — 2026-09-27

The subsequent P1 instruction explicitly accepted P0 governance. Recorded APPR-001 and transitioned this ADR to ACCEPTED without changing its decision/rationale. Historical proposal wording above describes the earlier event.

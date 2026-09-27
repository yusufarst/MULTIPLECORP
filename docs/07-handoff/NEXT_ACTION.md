# Next action

Status: REVIEW | Updated: 2026-09-27 | Owner: Planning

## Next planning task

The autonomy mandate has been applied to P0. Level 1 improvements proceed directly under DIR-005; do not ask for package approval as a prerequisite to fixing technical planning weaknesses. Verify the [local checkpoint](CURRENT_STATE.md#repository-checkpoint) and arrange an authorized shared checkpoint before a different checkout takes over (GAP-002).

The next phase remains **P1 — Product Definition & V1 Scope**. This mandate-integration session did not generate those specifications. When continuing with P1, apply the existing authority delegation without new permission requests for Level 1 work. Product/scope acceptance and unresolved Level 2/3 business choices still need owner decisions; silence is not acceptance.

## Bounded P1 task

- Role: planner only.
- Read: [AGENTS.md](../../AGENTS.md), [context index](../CONTEXT_INDEX.md), [current state](CURRENT_STATE.md), P0 governance, ADR-001, both owner sources, and [GAP_REGISTER](../00-governance/GAP_REGISTER.md).
- Produce only the P1 concern documents reserved in the [ownership registry](../00-governance/SOURCE_OF_TRUTH.md#canonical-ownership-registry): product overview, V1 scope including exclusions, and product acceptance criteria.
- Map requirements to source section IDs. Distinguish owner-confirmed requirements, planner proposals, unresolved decisions, and explicit exclusions. Include acceptance outcomes and dependency questions without prescribing a database schema or inventing unconfirmed business formulas.
- Prioritize GAP-014 acceptance/capacity/data dependencies in P1; surface GAP-003/004/006 ownership, financial and visibility choices before later schema/policy decisions. Check feasibility against the release target and make scope tradeoffs visible for owner decision. Do not invent formulas, drop requested capabilities or weaken integrity.
- Fix Level 1 contradictions directly; stop only choices dependent on an unresolved owner decision. Complete the phase-end adversarial review, update gaps, and record handoff/decision evidence.
- Excluded: P2+ design, implementation units, production source, package installation, scaffolding, infrastructure and deployment.

## Owner decisions and operational inputs

The [gap register](../00-governance/GAP_REGISTER.md) owns decision descriptions, recommendations, alternatives and impacts; do not duplicate them here. Prioritize the decisions when their phase starts:

1. P1: mandatory client acceptance examples, operator/UAT availability, execution capacity and data steward (GAP-014; migration detail in GAP-010).
2. Before P2/P4 decisions: stock ownership and allocation (GAP-003), financial metric/payment/rounding meaning (GAP-004), shared versus company-restricted visibility (GAP-006).
3. Before P3 transitions: corrections with downstream activity (GAP-005); before P9: recovery tolerance, resources and human operator (GAP-011/013).

Use only sanitized examples/aggregate counts for planning; do not collect live business records or secrets into the public repository. Recommendations in the register are not adopted business rules until the owner decides.

Detailed architecture mechanisms, framework versions, import mappings and runtime tests remain future design/execution work. The final cross-module challenge and pre-mortem must be evidenced before freeze/execution as defined in the operating model; this initial risk scan does not replace them.

# Cost policy

Status: APPROVED | Updated: 2026-10-03 | Owner: Planning

Approval: [APPR-009](DECISION_LOG.md#appr-009--aicwdf-structural-migration-approved), explicit Owner approval on 2026-10-03 (DIR-040) of this document as committed in the AICWDF migration finalization commit; the approved file hash is recorded there. Approval changes lifecycle only, not implementation authorization.

This document owns the procedure of AICWDF §4C for technology and service choices. The project's cost facts — the near-zero incremental monthly target, the existing VPS and domain, the cost areas and the evidence still needed — are owned by the [V1_SCOPE cost boundary](../01-product/V1_SCOPE.md#cost-boundary) and are not restated here. A recurring cost or a significant infrastructure change is a Level 2 decision ([CHANGE_CONTROL](CHANGE_CONTROL.md#decision-authority)).

## Procedure

1. **Selection order (§4C.1).** Prefer an existing project capability, then a native framework or platform capability, then a free open-source package, then a self-hosted open-source capability on the existing infrastructure, then a reliable free tier; a paid external service comes last and only with the Owner's explicit approval.
2. **Paid-service stop condition (§4C.2).** When a paid service is proposed, that decision stops. Record the requirement, the free, open-source and self-hosted alternatives, the trade-offs and the estimated recurring cost, and ask the Owner; dependent work waits, independent work continues.
3. **No paid production AI dependency (§4C.3).** None is introduced unless it is a real product requirement the Owner approves.
4. **Free-tier caution (§4C.4).** A critical free-tier dependency records its limits, quota behaviour, lock-in, exit strategy and data portability before it is adopted.

Development and QA tools follow the same order; their availability and installation are recorded in [TOOLCHAIN](TOOLCHAIN.md).

## Recurring-cost inventory

Additional recurring production services selected: none.

| Service | Purpose | Monthly cost | Approval |
| --- | --- | --- | --- |
| — | — | — | — |

## Paid-service exception registry

Approved paid-service exceptions: none.

| Exception | Requirement | Alternatives considered | Recurring cost | Owner approval |
| --- | --- | --- | --- | --- |
| — | — | — | — | — |

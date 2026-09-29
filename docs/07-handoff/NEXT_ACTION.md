# Next action

Status: REVIEW | Updated: 2026-09-29 | Owner: Planning

## Stop boundary

DIR-021 authorized only the P2 approval-continuity task: archive the nineteenth source record, record APPR-003/OBS-004, apply the two lifecycle changes, update continuity records, validate, create one commit (`docs: approve P2 domain baseline`) and perform one normal non-force push after a divergence re-check. That task ends the current authorization. Do not start P3, create WORKFLOWS.md, design schema/ERD/migrations/permission matrices/concurrency mechanisms, or write application code. Do not reopen decisions settled by DIR-009/011/018/019/020 or the PLANNER-DETERMINED Level-1 decisions.

## Current state and immediate follow-up

P2 is APPROVED (APPR-003), checkpointed at `1392966bfb89581d705e0394424705978e1d3db8` and published; the approval continuity commit follows it on main (resolve with `git log -1 --grep='^docs: approve P2 domain baseline$'`). OWNER_DECISION_REQUIRED = 0. Details and evidence: [CURRENT_STATE](CURRENT_STATE.md), [APPR-003](../00-governance/DECISION_LOG.md#appr-003--p2-domain-baseline-approved), [P2_QUALITY_GATE](../02-domain/P2_QUALITY_GATE.md).

**Awaiting the Owner:**

1. **A separate explicit P3 authorization** naming the P3 task boundary, following the DIR-016 pattern (read-only baseline verification first, planner only, plan-first, stop boundary). No planning or Git action is pending without it. If review changes to the approved P2 baseline are requested instead, classify them under [change control](../00-governance/CHANGE_CONTROL.md#decision-authority) before editing.

## Proposed next phase after authorization

**P3 — Critical Business Workflows**, planner only, in a separately authorized task. Inputs: the P2 rules (BR/CALC/FS IDs), REFERENCE_COVERAGE identifiers, GAP-005/007/008/009/015/019/022/023 obligations. Expected output: the reserved `docs/02-domain/WORKFLOWS.md` — user/process workflows and transition orchestration over the P2 lifecycles (actor sequences, procedural correction matrix, document applicability flows, completion/override and revalidation procedures, migration-adjacent operational flows), consuming P2 states without adding, removing or relaxing any transition. Exclude P4 schema/ERD, P5 matrix, P6 mechanisms, P7 UI, packages, scaffolding, code. Apply Level 1 improvements directly; run the phase-end adversarial review; update handoff. This paragraph specifies a future bounded task; no P3 work has been performed.

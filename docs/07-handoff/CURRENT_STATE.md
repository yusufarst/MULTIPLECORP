# Current state

Status: REVIEW | Updated: 2026-09-29 | Owner: Planning

## Target and phase

- Release: MultipleCorp operational production V1, target 2026-10-15; [charter](../00-governance/PROJECT_CHARTER.md).
- Current phase: **P2 — Domain Model & Business Rules COMPLETE: authored under DIR-016–020, checkpointed and published at `1392966bfb89581d705e0394424705978e1d3db8`, and explicitly APPROVED by the Owner under APPR-003 (DIR-021).** P3 — Critical Business Workflows and application execution remain unauthorized and NOT STARTED; P3 requires a separate explicit Owner authorization.
- Last completed implementation unit: none; no application exists. Canonical P2 deliverables: [DOMAIN_MODEL](../02-domain/DOMAIN_MODEL.md) (APPROVED), [BUSINESS_RULES](../02-domain/BUSINESS_RULES.md) (APPROVED), [P2_QUALITY_GATE](../02-domain/P2_QUALITY_GATE.md) (REVIEW evidence), linked from CONTEXT_INDEX.
- Binding inputs unchanged: P0/P1 approvals, Indonesian UI, mobile-first plus desktop productivity, pooled stock with attribution, cost versus cash-out, audited corrections, default-deny access, DIR-009 local-only backup with accepted host-loss exposure, near-zero incremental recurring cost.
- Binding P2 business decisions unchanged: **DIR-018** (dual dates recorded_at/business_date; equal Owner/Admin backdating over real business dates with individual-actor audit; no future-dating; document numbering periods follow business_date with next-available-number, append-only sequences; multiple Admin accounts stay separate audited identities), **DIR-019** (Owner-only receivable write-off leaving active/aging while permanently preserved as disposed history; dispute hold stays active and aging; genuinely received money is reconstructed as a real payment; SIPLAH net-fee settlement YES with fee-expense-exactly-once; Level-1 planner decisions need no ratification list) and **DIR-020** (fee-deduction settlement generalized to other verified intermediary deductions — bank/VA/payment-intermediary fees — under seven explicit conditions; no arbitrary deductions or formulas; not extended to unsupported cashback/discounts/write-offs). OWNER_DECISION_REQUIRED = 0.

## P2 deliverable summary

- Twelve domains + two cross-cutting conventions; SIPLAH stays channel metadata; no institution modules, GL, WMS or supplier comparison.
- Derived-truth strategy: stored facts + explicit decisions + snapshots; all balances/progress/outstanding/credit derived; four stored-decision state models (Project, Quotation, Document incl. Invoice, Reservation); no universal status enum.
- Key formalizations: receiving lots carry acquisition provenance/cost (incl. opening stock and drop-ship returns); AVAILABLE = ON HAND − RESERVED − UNUSABLE with AVAILABLE ≥ 0 and human reservation-cut on shrinkage; payment-application conservation across invoice/refund/credit targets; fee-deduction settlement per DIR-019, generalized by DIR-020; contra-facts for entry errors; multi-unit base-unit arithmetic with pinned conversion snapshots and residual-absorbing cost rounding; eight-primitive correction taxonomy + legality matrix; ten completion predicates evaluable over facts/decisions; fourteen forbidden shortcuts.
- BUSINESS_RULES carries exactly **14 marked PLANNER-DETERMINED Level-1 decisions** (BR-DT-06 WIB; §3 derived-vs-stored strategy; BR-PRJ-03 waiver-eligibility authority; BR-PRJ-05 channel-mutability window; BR-PRJ-06 no Order entity; BR-RSV-03 reservation bound; BR-RSV-04 human reservation-cut; BR-PUR-01 projectless replenishment purchase; BR-INV-03 multi-unit base-unit model; BR-INV-04 lot picking-aid attribution; BR-FIN-13 residual-absorbing rounding; BR-FIN-15 aging; BR-ACC-03 deactivation carve-out; CALC-13 period-view convention), each with inline grounds. BR-FIN-07 is deliberately unmarked: it is a settled consequence of DIR-011, not a planner choice.

## Repository checkpoint, publication and approval

- P2 checkpoint `1392966bfb89581d705e0394424705978e1d3db8` (`docs: establish P2 domain model and business rules`, 18 paths: 8 new + 10 modified) was committed and published with a normal non-force push; at approval-task entry, local main == origin/main == that SHA with a clean tree and index (OBS-004).
- The Owner's approval / checkpoint-publication authorization is archived as the **nineteenth transcribed LOCKED SOURCE RECORD** ([P2_OWNER_APPROVAL_2026-09-29.txt](../00-governance/sources/P2_OWNER_APPROVAL_2026-09-29.txt); SHA-256/line/byte provenance in [SOURCE_OF_TRUTH](../00-governance/SOURCE_OF_TRUTH.md#p2-owner-approval-and-finalization-authorization)), closing the continuity obligation the checkpoint report had noted.
- **APPR-003** changes only DOMAIN_MODEL and BUSINESS_RULES to APPROVED (pre-approval file hashes recorded in [DECISION_LOG](../00-governance/DECISION_LOG.md#appr-003--p2-domain-baseline-approved)); substantive P2 content is unchanged. The approval continuity package is committed as `docs: approve P2 domain baseline` (resolve its SHA with `git log -1 --grep='^docs: approve P2 domain baseline$'`) and pushed under DIR-021's normal non-force authorization after a fresh divergence re-check.
- Publication boundary unchanged: only normal non-force pushes of reviewed checkpoints, only under explicit Owner authorization; on divergence stop and report.

## Verification and approval state

- P0 APPROVED (APPR-001), ADR-001 ACCEPTED; P1 three product documents APPROVED (APPR-002), published and receiver-verified (GAP-002 CLOSED); P2 two normative documents APPROVED (APPR-003), checkpointed and published.
- [P2_QUALITY_GATE](../02-domain/P2_QUALITY_GATE.md) remains REVIEW as evidence: it records the phase-end adversarial passes, the CAP/DOC/OWN traceability verification, the zero-reopened-decisions check, static verification results and the checkpoint/publication/approval addendum. GAP_REGISTER, DECISION_LOG, CHANGELOG and this handoff pair remain REVIEW living records.
- No application tests, device UAT, migration, restore or runtime evidence exists; deadline feasibility remains unproven (GAP-014).

## Risks and boundaries

- Register unchanged by the approval: **25 findings — 3 CLOSED, 21 OPEN, 0 OWNER_DECISION_REQUIRED, 1 ACCEPTED_RISK.** GAP-016 stays BUSINESS DECISION RESOLVED by DIR-018 (technical date/sequence/test work remains); GAP-025 tracks multi-unit arithmetic proof; GAP-003/004/005/006/009/022/023 keep their canonical P2 rules and stay OPEN only for P3/P4/P5/P6/P8 mechanisms and evidence.
- Do not reopen settled decisions (DIR-009/011/018/019/020, Q1/Q2/Q3, the PLANNER-DETERMINED Level-1 decisions); keep all nineteen source records immutable; no real data/secrets in the public repository; agents never receive production credentials.
- Preserve phase boundaries going forward: no P3 workflow orchestration, P4 schema/ERD, P5 permission matrix, P6 mechanisms, UI design, roadmap, build units, packages or code until authorized.

Next safe action: **P3 — Critical Business Workflows**, only under a separate explicit Owner authorization per [NEXT_ACTION](NEXT_ACTION.md). No planning or Git action is pending without it.

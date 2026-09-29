# Project charter

Status: APPROVED | Updated: 2026-09-29 | Owner: Planning

Approval: [APPR-001](DECISION_LOG.md), P0 governance at b425584. Subsequent directives and continuity updates remain recorded in the decision log. [APPR-002](DECISION_LOG.md#appr-002--p1-product-definition-and-v1-scope-approved) approves the three P1 canonical product specifications, [APPR-003](DECISION_LOG.md#appr-003--p2-domain-baseline-approved) the two P2 normative domain specifications and [APPR-004](DECISION_LOG.md#appr-004--p3-critical-business-workflows-approved) the P3 workflow specification with its amended P1/P2 revisions; evidence, gap and handoff records remain REVIEW.

## Identity and outcome

- Name: **MultipleCorp — Company Management System**.
- Repository: [yusufarst/MULTIPLECORP](https://github.com/yusufarst/MULTIPLECORP).
- Product: one internal workspace for a group of legal companies, organized around projects and reusable business data.
- Outcome: operational work, trustworthy inventory, business documents, visible billing/receivables, accurate payments, owner visibility, and recoverable data.
- Target: **operational production V1 on 2026-10-15**. Baseline planning date: 2026-09-27; approximately 18 elapsed days. This is a delivery constraint, not evidence that the scope is feasible or a production-readiness claim.
- Decision authority: the project owner/requester, with bounded technical planning authority delegated to the planner by the latest mandate. Boundaries belong to [change control](CHANGE_CONTROL.md#decision-authority); delegation evidence is DIR-005 in the decision log.

These are owner-provided facts from the [brief](sources/OWNER_BRIEF_2026-09-27.txt), opening and §§1–2, 46–47, reaffirmed in the P1 directive. P0 governance acceptance is APPR-001; binding product clarifications are DIR-006–007. Latest DIR-008 requires full PDF/image reconciliation, source precedence and completion/DoD preservation while retaining the P1-only boundary.

Subsequent DIR-009 expressly chooses same-production-VPS backups only for V1 and accepts the specified host/storage-loss exposure (RISK-001). Recovery targets apply only while VPS/local backup data remain recoverable. The local control/disk-safety obligations are owned by V1_SCOPE; this policy update stays within P1 and does not authorize P2 or implementation.

## Authorized work

**P2 — Domain Model & Business Rules is complete** (DIR-016–021). P0 governance was accepted at commit `b425584daa80af2c1342252407c95e08024d3173`; P1 is APPROVED under APPR-002; the P2 normative documents are APPROVED under APPR-003, checkpointed and published. **P3 — Critical Business Workflows is complete** (DIR-023–026): WORKFLOWS is APPROVED under APPR-004 and checkpointed by the P3 finalization commit; P3_QUALITY_GATE remains REVIEW evidence. P4+ design and implementation are not authorized; P4 — Database Architecture needs its own Owner authorization.

P0 owns governance, authority, continuity and guardrails. P1, P2 and P3 concern owners are indexed in CONTEXT_INDEX. Schema design, permission matrices, concurrency mechanisms, UX patterns/tokens, roadmap and build units remain later-phase work.

Application code, Laravel/frontend generation, dependency installation, production infrastructure, deployment, and production database changes are outside this assignment. See [role boundaries](AGENT_OPERATING_MODEL.md) and [engineering constraints](ENGINEERING_PRINCIPLES.md).

## Planning sequence

The owner-defined order is P0 governance → P1 product/scope → P2 domain/rules → P3 workflows → P4 database → P5 security/authorization → P6 concurrency/idempotency/performance → P7 UX → P8 quality → P9 infrastructure/recovery → P10 delivery/release plan → P11 implementation units → planning freeze → execution.

Do not advance dependent planning through a blocking contradiction. Phase ordering sequences deliverables, not risk discovery: flag hidden dependencies and feasibility threats as soon as found, then resolve them before the affected decision/build gate. Record open assumptions explicitly. A deadline must not be used to waive data integrity, authorization, recoverability, or required tests. Scope cuts and target changes require the owner's decision; technical simplification within existing behavior uses Level 1 authority.

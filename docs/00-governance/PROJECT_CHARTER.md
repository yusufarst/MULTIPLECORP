# Project charter

Status: REVIEW | Updated: 2026-09-27 | Owner: Planning

## Identity and outcome

- Name: **MultipleCorp — Company Management System**.
- Repository: [yusufarst/MULTIPLECORP](https://github.com/yusufarst/MULTIPLECORP).
- Product: one internal workspace for a group of legal companies, organized around projects and reusable business data.
- Outcome: operational work, trustworthy inventory, business documents, visible billing/receivables, accurate payments, owner visibility, and recoverable data.
- Target: **operational production V1 on 2026-10-15**. Baseline planning date: 2026-09-27; approximately 18 elapsed days. This is a delivery constraint, not evidence that the scope is feasible or a production-readiness claim.
- Decision authority: the project owner/requester, with bounded technical planning authority delegated to the planner by the latest mandate. Boundaries belong to [change control](CHANGE_CONTROL.md#decision-authority); delegation evidence is DIR-005 in the decision log.

These are owner-provided facts from the [brief](sources/OWNER_BRIEF_2026-09-27.txt), opening instructions and §§1–2, 46–47. Approval of this document's new wording is still pending.

## Authorized work

The active assignment is **P0 — Repository Governance & Agent Continuity**, extended by the owner's architect autonomy and gap-hunting mandate. Maintain the foundation, correct unnecessary approval barriers, and identify cross-phase risks before implementation. This extension does not produce the full P1–P11 specifications.

P0 covers identity, canonical ownership, lifecycle, decisions, change control, role boundaries, continuity, safety constraints, handoff, and its quality gate. Business specifications, schema design, architecture elaboration, UX designs, roadmaps, and implementation units belong to later phases.

Application code, Laravel/frontend generation, dependency installation, production infrastructure, deployment, and production database changes are outside this assignment. See [role boundaries](AGENT_OPERATING_MODEL.md) and [engineering constraints](ENGINEERING_PRINCIPLES.md).

## Planning sequence

The owner-defined order is P0 governance → P1 product/scope → P2 domain/rules → P3 workflows → P4 database → P5 security/authorization → P6 concurrency/idempotency/performance → P7 UX → P8 quality → P9 infrastructure/recovery → P10 delivery/release plan → P11 implementation units → planning freeze → execution.

Do not advance dependent planning through a blocking contradiction. Phase ordering sequences deliverables, not risk discovery: flag hidden dependencies and feasibility threats as soon as found, then resolve them before the affected decision/build gate. Record open assumptions explicitly. A deadline must not be used to waive data integrity, authorization, recoverability, or required tests. Scope cuts and target changes require the owner's decision; technical simplification within existing behavior uses Level 1 authority.

# Execution context

Status: APPROVED | Updated: 2026-10-03 | Owner: Planning

Approval: [APPR-009](../00-governance/DECISION_LOG.md#appr-009--aicwdf-structural-migration-approved), explicit Owner approval on 2026-10-03 (DIR-040) of this document as committed in the AICWDF migration finalization commit; the approved file hash is recorded there. Approval changes lifecycle only, not implementation authorization.

The stable minimum context for execution agents (AICWDF §34A). Every line points to its owning document; where a line and its owner differ, the owner wins.

## Project

- PROJECT: MultipleCorp — Company Management System — [PROJECT_CHARTER](../00-governance/PROJECT_CHARTER.md#identity-and-outcome)
- CURRENT RELEASE: operational production V1, no fixed delivery date (DIR-035 D1) — [PROJECT_CHARTER](../00-governance/PROJECT_CHARTER.md#identity-and-outcome)
- CURRENT PHASE: [PHASE_STATUS](../PHASE_STATUS.md); no Task exists before P11
- DEFAULT STACK: Laravel, Inertia, React with TypeScript, shadcn/ui on Tailwind CSS, PostgreSQL and Nginx, a Valkey/Redis-compatible queue and cache that is never a system of record, a modular monolith — [ARCHITECTURE §1](../04-architecture/ARCHITECTURE.md#1-baseline-and-constraints)

## Auth profile — decided, SECURITY amendment pending

- implementation: LARAVEL_NATIVE (AICWDF §4A.1, §4A.7) — [ARCHITECTURE §1](../04-architecture/ARCHITECTURE.md#1-baseline-and-constraints), [SECURITY §2](../05-security/SECURITY.md#2-authentication-sessions-and-credentials)
- password profile: AICWDF-COMPAT-8 with the common/breached-password blocklist retained (DIR-037 D5; D-SEC-06 superseded, pending) — [DECISION_INDEX](../00-governance/DECISION_INDEX.md#approved-design-decisions)
- second factor: TOTP mandatory at minimum for Owner and high-risk accounts (D5; D-SEC-14 superseded, pending) — [GAP-033](../00-governance/GAP_REGISTER.md#gap-033--the-owner-account-has-no-second-authentication-factor-in-v1)
- Google sign-in: ON, EXISTING_ACCOUNT_ONLY, no auto-registration; Google never defines roles, capabilities or company scope (DIR-037 D6) — [DECISION_INDEX](../00-governance/DECISION_INDEX.md#owner-decisions)
- until the SECURITY amendment is approved its text is not amended; design nothing from the superseded parts — [GAP-036](../00-governance/GAP_REGISTER.md#gap-036--decided-authentication-changes-are-not-yet-designed)

## Global UI rules

- language: Indonesian default, English secondary, a language switch, no uncontrolled hardcoded copy; generated or issued documents and business data in Indonesian (DIR-037 D7) — [V1_SCOPE CAP-14](../01-product/V1_SCOPE.md#must-ship), [GAP-037](../00-governance/GAP_REGISTER.md#gap-037--bilingual-ui-obligations-are-not-yet-designed-or-tested)
- mobile-first operation with desktop productivity; comfortable density and visual breathing room under AICWDF §18.2 with the retained anti-slop discipline (DIR-035 D4) — [V1_SCOPE](../01-product/V1_SCOPE.md#language-and-experience-contract)
- visual direction: pending the P7 re-baseline with the Owner-chosen Ramp reference (DIR-035 D3); interaction rules in force — [ADMIN_FLOW](../07-ux-design/ADMIN_FLOW/README.md), [PHASE_STATUS](../PHASE_STATUS.md)
- route and interaction contracts: pending — [GAP-035](../00-governance/GAP_REGISTER.md#gap-035--route-and-interaction-contracts-do-not-exist-yet)

## Cost, safety and toolchain

- cost: additional recurring production cost target 0; a paid service needs the Owner's approval — [COST_POLICY](../00-governance/COST_POLICY.md)
- production data: zero-touch for agents — [PRODUCTION_DATA_SAFETY](../00-governance/PRODUCTION_DATA_SAFETY.md)
- tools and Git staging — [TOOLCHAIN](../00-governance/TOOLCHAIN.md)

## Execution entry

- read the active Task, then only the exact sections it references, in the reading order of [AGENTS.md](../../AGENTS.md#read-in-this-order); the [Task model](../00-governance/AGENT_OPERATING_MODEL.md#task-model) governs status and completion

## Project invariants

1. Money is exact `numeric(18,2)`, quantities exact base-unit `numeric(18,3)`, splits deterministic and residual-absorbing — [DATABASE §17](../04-architecture/DATABASE/s13-17-history-scope-types.md#17-money-quantity-and-date-types), BR-FIN-13, [numeric annex](../02-domain/BUSINESS_RULES.md#numeric-annex-binding-examples-for-p8-fixtures-gap-004)
2. Company isolation and default-deny on the server in the order authentication → company scope → capability → resource → preconditions; OWNER_ONLY capabilities are never grantable — AZ-01–AZ-12 ([PERMISSIONS_MATRIX §2](../05-security/PERMISSIONS_MATRIX.md#2-authorization-model)), CS-01–CS-08 ([§6](../05-security/PERMISSIONS_MATRIX.md#6-company-scope-rules)), OD-01–OD-16 ([§9](../05-security/PERMISSIONS_MATRIX.md#9-owner-only-denials)); EN-01–EN-08 ([SECURITY §3](../05-security/SECURITY.md#3-enforcement-architecture))
3. Field projection limits what each account sees of every table — [PERMISSIONS_MATRIX §7](../05-security/PERMISSIONS_MATRIX.md#7-resource-and-field-projection) PJ-01–PJ-23
4. History is append-only: corrections revise, void, reverse or return with reason and linkage, never hard-delete — BR-CR-01, FS-02 ([BUSINESS_RULES](../02-domain/BUSINESS_RULES.md#2-correction-taxonomy)); CM-01–CM-38 ([WORKFLOWS §8](../03-workflows/WORKFLOWS/s08-09-corrections-indivisible.md#8-correction-reversal-and-cancellation-matrix)); [DATABASE §13](../04-architecture/DATABASE/s13-17-history-scope-types.md#13-correction-and-history-representation)
5. One client `command_id` per intent with re-authorized replay and durable business identities — CI-01–CI-09 ([CONCURRENCY_IDEMPOTENCY §10](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s10-13-identity-failure-retry.md#10-command-identity-and-replay)); SF-CMD ([WORKFLOWS §2](../03-workflows/WORKFLOWS/s01-04-foundations.md#2-standard-command-envelope--sf-cmd))
6. Locks follow the canonical order LK-00–LK-31 and the data-dependent lock-set protocol — [CONCURRENCY_IDEMPOTENCY §4](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s04-06-locks-envelope.md#4-canonical-lock-order), [§5](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s04-06-locks-envelope.md#5-data-dependent-lock-set-protocol)
7. Each indivisible action commits in one transaction with its guards — AX-01–AX-37 ([WORKFLOWS §9](../03-workflows/WORKFLOWS/s08-09-corrections-indivisible.md#9-indivisible-business-actions)); [DATABASE §25](../04-architecture/DATABASE/s25-28-transactions-handoffs.md#25-transaction-boundary-map); [ARCHITECTURE §15](../04-architecture/ARCHITECTURE.md#15-transactions-and-failure-semantics)
8. AVAILABLE = ON HAND − RESERVED − UNUSABLE, never negative — [V1_SCOPE](../01-product/V1_SCOPE.md#reservation-and-usable-stock); C-04–C-06 ([DATABASE §18](../04-architecture/DATABASE/s18-19-constraints-integrity.md#18-constraint-catalogue))
9. Sensitive actions keep the individual actor, time, reason, before/after and linkage; security events stay separate — BR-XC-02, BR-CR-01 ([BUSINESS_RULES](../02-domain/BUSINESS_RULES.md#1-cross-cutting-rules)); LG-01–LG-07 ([SECURITY §6](../05-security/SECURITY.md#6-audit-and-security-logging))
10. Commits use only the Owner's identity, never AI attribution — DIR-022 ([AGENTS.md](../../AGENTS.md#git-commit-attribution))

## Do not reopen

Binding decisions and their current status are indexed in [DECISION_INDEX](../00-governance/DECISION_INDEX.md); reopening one needs the Owner.

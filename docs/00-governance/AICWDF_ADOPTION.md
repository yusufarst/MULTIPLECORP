# AICWDF v4.3 adoption

Status: APPROVED | Updated: 2026-10-04 | Owner: Planning

Approval: [APPR-009](DECISION_LOG.md#appr-009--aicwdf-structural-migration-approved), explicit Owner approval on 2026-10-03 (DIR-040) of this document as committed in the AICWDF migration finalization commit; the approved file hash is recorded there. Approval changes lifecycle only, not implementation authorization.

Amendment — REVIEW, pending Owner approval: on 2026-10-03, under [DIR-043](DECISION_LOG.md#dir-042-dir-043-obs-016-and-tech-025--p5-authentication-amendment-directives-baseline-and-amendment), the section-map rows of §4A, §15 and §16 and the two authentication rows of the project exceptions were updated: the authentication amendment that designs the Owner's decisions D5 and D6 ([DIR-037](DECISION_LOG.md#dir-034039-obs-013-and-tech-023--aicwdf-adoption-directives-migration-baseline-and-structural-migration)) and K1–K4 (DIR-042) is written and awaits the Owner's approval; on 2026-10-04, under [DIR-045](DECISION_LOG.md#dir-044-dir-045-risk-006-risk-007-and-tech-025--p5-authentication-amendment-continuation-after-the-adversarial-review), the password-profile exception row cites the Owner's decisions on the amendment's first adversarial review (DIR-044). [TECH-025](DECISION_LOG.md#dir-042-dir-043-obs-016-and-tech-025--p5-authentication-amendment-directives-baseline-and-amendment) records the SHA-256 of the last approved revision. The amended wording is not approved until the Owner approves it; the Owner decisions it applies are binding within their subjects.

This record maps the AI-First Complex Web Application Delivery Framework to the repository documents that apply it. It owns the mapping, the project exceptions and the terminology rule; it does not restate the framework.

## Framework and adoption directives

| Item | Value |
| --- | --- |
| Framework ID | AICWDF-4.3 |
| Version | 4.3 — English |
| Source | [AI_First_Complex_WebApp_Task_Framework_v4.3_EN.md](sources/AI_First_Complex_WebApp_Task_Framework_v4.3_EN.md), the thirtieth LOCKED SOURCE RECORD ([provenance](SOURCE_OF_TRUTH.md#aicwdf-v43-framework-source-adoption-directives-and-migration-authorization)) |
| Size | 3,587 lines, 76,025 bytes |
| SHA-256 | `BB578ADABCD8EBDDCB97C278E6589B935137F26F851B7218DD86C141744B70FE` |
| Adoption directives | DIR-034 (correction), DIR-035 (D1–D4, D10), DIR-036 (target operating structure), DIR-037 (D5–D7, structure approval), DIR-038 (C1, C2), DIR-039 (stages 0–3) — [decision log](DECISION_LOG.md#dir-034039-obs-013-and-tech-023--aicwdf-adoption-directives-migration-baseline-and-structural-migration) |

**Provenance versus operative rules (C1, DIR-038).** The framework source is provenance. The operative rules are the active repository documents named below, which cite framework sections instead of copying them. Where a framework default conflicts with a project rule, the [source-of-truth hierarchy](SOURCE_OF_TRUTH.md#owner-reference-ingestion-and-provenance) decides: the latest explicit Owner decision first, then the approved canonical specification for content truth, then the framework for operating structure and default policies (D2).

## Section map

Status values: **ADOPTED** — applied as written; **ADOPTED WITH PROJECT RULE** — applied with a project rule listed below; **PROJECT EXCEPTION** — not applied, with its authority; **PENDING** — owed by the stage or phase named.

| Section | Subject | Owning repository document | Status |
| --- | --- | --- | --- |
| §0 | Purpose of the framework | This record | ADOPTED |
| §1, §1.1, §1.2 | Adapt to the active project; existing complex-project adoption; compatibility over conformity | This record; [MIGRATION_MAP](MIGRATION_MAP.md) | ADOPTED WITH PROJECT RULE — P0–P6 preserved; P7 re-baselined by the Owner's D3; existing document names kept |
| §2 | Repository is the source of truth | [SOURCE_OF_TRUTH](SOURCE_OF_TRUTH.md#authority-and-provenance) | ADOPTED |
| §3 | Source-of-truth authority hierarchy | [SOURCE_OF_TRUTH](SOURCE_OF_TRUTH.md#owner-reference-ingestion-and-provenance) | ADOPTED WITH PROJECT RULE — the seven-level hierarchy of D2 |
| §4 | Default technology stack | [ARCHITECTURE §1](../04-architecture/ARCHITECTURE.md#1-baseline-and-constraints) | ADOPTED — the Owner baseline (brief §19) is the same stack |
| §4A.1, §4A.2, §4A.6, §4A.7 | One-time authentication decision; default core; library profiles | [EXECUTION_CONTEXT](../11-tasks/EXECUTION_CONTEXT.md#auth-profile--decided-security-amendment-pending); [ARCHITECTURE §1](../04-architecture/ARCHITECTURE.md#1-baseline-and-constraints); [SECURITY §2, D-SEC-17](../05-security/SECURITY.md#2-authentication-sessions-and-credentials) | ADOPTED WITH PROJECT RULE, PENDING OWNER APPROVAL (TECH-025) — Laravel-native: Fortify and Socialite; of the default core, password reset stays the Owner-issued credential links (AU-11, AU-12), e-mail verification and profile update are off, and TOTP is on for high-risk accounts ([GAP-036](GAP_REGISTER.md#gap-036--decided-authentication-changes-are-not-yet-designed)) |
| §4A.3, §4A.4 | Google sign-in and its implementation contract | [SECURITY AU-22–AU-24](../05-security/SECURITY.md#2-authentication-sessions-and-credentials) | ADOPTED WITH PROJECT RULE, PENDING OWNER APPROVAL (TECH-025) — D6, EXISTING_ACCOUNT_ONLY; the client ID and secret environment-managed (SX-01); the account-linking rule and the failure behaviour in AU-22–AU-24; the labels are P7's (H7-16) |
| §4A.5 | Password profile AICWDF-COMPAT-8 | [SECURITY AU-03](../05-security/SECURITY.md#2-authentication-sessions-and-credentials) | ADOPTED WITH PROJECT RULE, PENDING OWNER APPROVAL (TECH-025) — D5 with the stronger project rule below |
| §4A.8, §4A.9 | Authentication separate from authorization; provider boundary | [PERMISSIONS_MATRIX AZ-13](../05-security/PERMISSIONS_MATRIX.md#2-authorization-model); [ARCHITECTURE §8](../04-architecture/ARCHITECTURE.md#8-authorization-boundary-p5) | ADOPTED — the boundary written out in the amendment pending Owner approval (TECH-025) |
| §4A.10 | Login screen pattern | P7 re-baseline | PENDING — P7 |
| §4A.11 | Cost and current-documentation rule for authentication | [COST_POLICY](COST_POLICY.md); [TOOLCHAIN](TOOLCHAIN.md) | ADOPTED |
| §4B.1–§4B.5 | Indonesian-first bilingual user experience | [V1_SCOPE](../01-product/V1_SCOPE.md#language-and-experience-contract); planned `docs/07-ux-design/LOCALIZATION.md` | ADOPTED WITH PROJECT RULE — D7; design PENDING P7, proof P8 ([GAP-037](GAP_REGISTER.md#gap-037--bilingual-ui-obligations-are-not-yet-designed-or-tested)) |
| §4C.1–§4C.4 | Near-zero additional production cost | [COST_POLICY](COST_POLICY.md); [V1_SCOPE cost boundary](../01-product/V1_SCOPE.md#cost-boundary) | ADOPTED |
| §5.1–§5.6 | Engineering capabilities | [TOOLCHAIN](TOOLCHAIN.md) | ADOPTED — nothing installed or verified yet; each row names the phase that verifies it |
| §6, §6A, §7 | Toolchain bootstrap; MCP on-demand activation; toolchain registry | [TOOLCHAIN](TOOLCHAIN.md) | ADOPTED WITH PROJECT RULE — installation only inside an authorized task |
| §8 | Token-efficient working rules | [AGENTS.md](../../AGENTS.md); [EXECUTION_CONTEXT](../11-tasks/EXECUTION_CONTEXT.md) | ADOPTED |
| §9 | Agent roles | [AGENT_OPERATING_MODEL](AGENT_OPERATING_MODEL.md#roles-and-limits) | ADOPTED WITH PROJECT RULE — agents never hold production credentials; a human operator runs production |
| §10 | Master delivery model | [PROJECT_CHARTER](PROJECT_CHARTER.md#planning-sequence); [PHASE_STATUS](../PHASE_STATUS.md) | ADOPTED WITH PROJECT RULE — per-phase Owner authorization; deployment path PENDING P9 and P10 |
| §11 | P0 — governance, foundation and agent continuity | [AGENTS.md](../../AGENTS.md); this folder | ADOPTED — the exit questions are answered from AGENTS.md and [CURRENT_HANDOFF](../handoff/CURRENT_HANDOFF.md) |
| §12 | P1 — product definition, scope and acceptance | [PRODUCT_OVERVIEW](../01-product/PRODUCT_OVERVIEW.md), [V1_SCOPE](../01-product/V1_SCOPE.md), [ACCEPTANCE_CRITERIA](../01-product/ACCEPTANCE_CRITERIA.md) | ADOPTED — the D1 and D7 amendments approved under APPR-009 |
| §13 | P2 — domain model and business rules | [DOMAIN_MODEL](../02-domain/DOMAIN_MODEL.md), [BUSINESS_RULES](../02-domain/BUSINESS_RULES.md) | ADOPTED |
| §14.1 | Workflow contracts | [WORKFLOWS](../03-workflows/WORKFLOWS/README.md) | ADOPTED |
| §14.2, §14.3 | Route and interaction contracts | planned `docs/03-workflows/ROUTE_CONTRACTS.md`, `INTERACTION_CONTRACTS.md` | PENDING — P7 re-baseline ([GAP-035](GAP_REGISTER.md#gap-035--route-and-interaction-contracts-do-not-exist-yet)) |
| §15, §15.1 | Database and application architecture; replaceable integrations | [DATABASE](../04-architecture/DATABASE/README.md), [ARCHITECTURE](../04-architecture/ARCHITECTURE.md), [API_AND_INTEGRATIONS](../06-api-performance/API_AND_INTEGRATIONS.md) | ADOPTED — the IAM amendment, with the security amendment, PENDING OWNER APPROVAL (TECH-025) |
| §16 | Security, authentication and authorization | [SECURITY](../05-security/SECURITY.md), [PERMISSIONS_MATRIX](../05-security/PERMISSIONS_MATRIX.md) | ADOPTED — the D5 and D6 amendment written and PENDING OWNER APPROVAL (TECH-025) |
| §17 | Concurrency, idempotency, API and performance | [CONCURRENCY_IDEMPOTENCY](../06-api-performance/CONCURRENCY_IDEMPOTENCY/README.md), [PERFORMANCE](../06-api-performance/PERFORMANCE.md), [API_AND_INTEGRATIONS](../06-api-performance/API_AND_INTEGRATIONS.md) | ADOPTED |
| §18.1, §18.3–§18.10, §18.12 | Design quality, actions, back actions, navigation contracts, page patterns, states, UI inventory, design references | [ADMIN_FLOW](../07-ux-design/ADMIN_FLOW/README.md) (in force); [DESIGN_SYSTEM](../07-ux-design/DESIGN_SYSTEM.md) and [INFORMATION_ARCHITECTURE](../07-ux-design/INFORMATION_ARCHITECTURE.md) under replacement; planned `NAVIGATION_CONTRACTS.md`, `DESIGN_REFERENCES.md` | PENDING — P7 re-baseline (D3) |
| §18.2 | Visual breathing room and comfortable density | [V1_SCOPE](../01-product/V1_SCOPE.md#language-and-experience-contract) | ADOPTED WITH PROJECT RULE — D4 keeps the anti-slop discipline; the design is PENDING P7 |
| §18.11 | Localization contract | planned `docs/07-ux-design/LOCALIZATION.md` | PENDING — P7 re-baseline ([GAP-037](GAP_REGISTER.md#gap-037--bilingual-ui-obligations-are-not-yet-designed-or-tested)) |
| §19 | P8 — testing, quality and definition of done; Task statuses | [Task model](AGENT_OPERATING_MODEL.md#task-model); [P8 reservation](../08-testing/README.md) | Task statuses ADOPTED; testing PENDING — P8 |
| §20 | P9 — infrastructure, observability, backup and recovery | [P9 reservation](../09-operations/README.md) | PENDING — P9; offsite backup is a PROJECT EXCEPTION |
| §21 | P10 — release, migration, cutover, rollback and UAT | [P10 reservation](../10-release/README.md) | PENDING — P10 |
| §22 | P11 — Task planning, dependencies and execution specs | [Task model](AGENT_OPERATING_MODEL.md#task-model); [P11 reservation](../11-tasks/README.md) | Model ADOPTED; Task plan and Tasks PENDING — P11 |
| §23 | Task contract template | [Task contract](AGENT_OPERATING_MODEL.md#task-contract) | ADOPTED WITH PROJECT RULE — three project fields |
| §24 | Task execution flow | [Execution flow](AGENT_OPERATING_MODEL.md#execution-flow) | ADOPTED WITH PROJECT RULE — the project's review list |
| §25 | Route and interaction integrity | [ACCEPTANCE_CRITERIA](../01-product/ACCEPTANCE_CRITERIA.md#feature-module-and-release-completion-obligations); planned contracts | PENDING — contracts P7, proof P8 |
| §26 | Browser E2E | [TOOLCHAIN](TOOLCHAIN.md) | PENDING — P8 |
| §27 | UI/UX consistency gate | P7 re-baseline; P8 | PENDING — P7 and P8 |
| §28 | Testing layers | [P8 reservation](../08-testing/README.md) | PENDING — P8 |
| §29, §30 | Production database zero-touch; forbidden production data actions | [PRODUCTION_DATA_SAFETY](PRODUCTION_DATA_SAFETY.md) | ADOPTED |
| §31 | Long-term maintenance | [PRODUCTION_DATA_SAFETY](PRODUCTION_DATA_SAFETY.md); [AGENT_OPERATING_MODEL](AGENT_OPERATING_MODEL.md#roles-and-limits) | ADOPTED |
| §32 | Branch and environment model | P9 and P10 | PENDING — P9 and P10; until then only Owner-authorized branches and normal pushes |
| §33 | Recommended documentation structure | [ownership registry](SOURCE_OF_TRUTH.md#canonical-ownership-registry); [MIGRATION_MAP](MIGRATION_MAP.md) | ADOPTED WITH PROJECT RULE — existing names and records retained |
| §34 | AGENTS.md minimum contract | [AGENTS.md](../../AGENTS.md) | ADOPTED WITH PROJECT RULE |
| §34A | EXECUTION_CONTEXT | [EXECUTION_CONTEXT](../11-tasks/EXECUTION_CONTEXT.md) | ADOPTED |
| §35 | TASK_PLAN format | planned `docs/11-tasks/TASK_PLAN.md` | PENDING — P11; [PHASE_STATUS](../PHASE_STATUS.md) until then |
| §36 | CURRENT_HANDOFF format | [CURRENT_HANDOFF](../handoff/CURRENT_HANDOFF.md) | ADOPTED — Task fields N/A before P11 |
| §37, §38 | First responses of planning and execution agents | [AGENT_OPERATING_MODEL](AGENT_OPERATING_MODEL.md#roles-and-limits) | ADOPTED — the execution response applies from P11 |
| §39 | Release integrity report | [P10 reservation](../10-release/README.md) | PENDING — P10 |
| §40 | Maintenance flow after release | [AGENT_OPERATING_MODEL](AGENT_OPERATING_MODEL.md#roles-and-limits); [PRODUCTION_DATA_SAFETY](PRODUCTION_DATA_SAFETY.md) | ADOPTED — applies after release |
| §41 | Framework adaptation rule | This record | ADOPTED |
| §42 | Final agent operating contract | [AGENTS.md](../../AGENTS.md); [AGENT_OPERATING_MODEL](AGENT_OPERATING_MODEL.md) | ADOPTED WITH PROJECT RULE |
| §43 | Final principle | [AGENTS.md](../../AGENTS.md) | ADOPTED |

## Project exceptions and stronger invariants

| Rule | Framework section | Authority |
| --- | --- | --- |
| V1 backups stay only on the production VPS; the offsite backup item does not apply, with the accepted total-host-loss exposure | §20 | DIR-009, RISK-001; [GAP-018](GAP_REGISTER.md#gap-018--owner-accepts-total-vps-or-storage-loss-exposure-with-local-only-backup) |
| Every phase needs its own explicit Owner authorization, quality gate, approval and checkpoint; planning freeze and execution are separate gates | §10, §22 | DIR-001, DIR-003, DIR-039; [CHANGE_CONTROL](CHANGE_CONTROL.md#approval-evidence) |
| The decision log, gap register and per-phase evidence folders are kept | §33 | DIR-002, DIR-005, DIR-039 |
| Password AICWDF-COMPAT-8 keeps the common/breached-password blocklist, password-manager and paste support, no forced rotation and the existing throttling, and adds mandatory TOTP at minimum for Owner and high-risk accounts — stronger than the framework's default with 2FA off; not EXTERNAL_COMPLIANCE | §4A.2, §4A.5 | D5 (DIR-037), K1 (DIR-042), DIR-044 R-01, R-05–R-07; designed in SECURITY AU-03, AU-05, AU-16, AU-18–AU-21, pending Owner approval (TECH-025; GAP-036) |
| Google sign-in on for V1, EXISTING_ACCOUNT_ONLY, no auto-registration; Google never defines roles, capabilities or company scope; existing authorization, session and step-up controls stay authoritative | §4A.3, §4A.4 | D6 (DIR-037), K2 (DIR-042); designed in SECURITY AU-22–AU-24 and PERMISSIONS_MATRIX AZ-13, pending Owner approval (TECH-025; GAP-036) |
| Application UI Indonesian by default with English secondary and a language switch; generated or issued business documents and business data stay Indonesian for V1 | §4B | D7 (DIR-037) |
| PHASE_STATUS is the Owner's progress view until the Task plan exists | §35 | DIR-037 |
| Existing document names are kept to avoid cosmetic renaming — for example PRODUCT_OVERVIEW, V1_SCOPE and ACCEPTANCE_CRITERIA rather than a product blueprint, WORKFLOWS rather than critical flows, SECURITY with PERMISSIONS_MATRIX rather than a security model, CONCURRENCY_IDEMPOTENCY, PERFORMANCE and API_AND_INTEGRATIONS rather than technical rules, and BACKUP_RECOVERY rather than backup-restore | §1.2, §33 | D10 (DIR-035) |
| The source hierarchy has seven levels and separates content truth from operating structure | §3 | D2 (DIR-035; DIR-039 §10.1) |
| Agents never receive production credentials; a human operator runs approved production operations | §9, §29 | Brief §37; [ENGINEERING_PRINCIPLES](ENGINEERING_PRINCIPLES.md#secrets-public-repository-and-production-boundary) |
| Commits carry only the Owner's identity and no AI attribution | — | DIR-022 |

## Terminology and planned paths

"Build Unit" is the historical name of a Task: from this adoption point (2026-10-03) the execution term is **Task**. Historical text is not rewritten; the rule is owned by the [operating model](AGENT_OPERATING_MODEL.md#terminology). Moved and split files are resolved by the [migration map](MIGRATION_MAP.md); planned paths changed as follows ([ownership registry](SOURCE_OF_TRUTH.md#canonical-ownership-registry)):

| Earlier planned path | Planned path now |
| --- | --- |
| `docs/05-quality/TEST_STRATEGY.md`, `SECURITY_TEST_MATRIX.md`, `PERFORMANCE_TARGETS.md`, `DEFINITION_OF_DONE.md` | `docs/08-testing/` with the same names |
| `docs/03-architecture/BACKUP_RECOVERY.md`, `INFRASTRUCTURE.md` | `docs/09-operations/` with the same names |
| `docs/06-delivery/ROADMAP_18_DAYS.md` | dropped — there is no fixed delivery date (D1) |
| `docs/06-delivery/IMPLEMENTATION_ORDER.md` | replaced by the dependency graph of `docs/11-tasks/TASK_PLAN.md` |
| `docs/06-delivery/RELEASE_PLAN.md` | `docs/10-release/RELEASE_PLAN.md` |
| `docs/06-delivery/units/<ID>.md` | `docs/11-tasks/TASK-XXX.md` |
| `docs/06-delivery/FEATURE_COVERAGE_MATRIX.md` | `docs/11-tasks/FEATURE_COVERAGE_MATRIX.md` |
| `docs/07-handoff/CURRENT_STATE.md`, `NEXT_ACTION.md` | [CURRENT_HANDOFF](../handoff/CURRENT_HANDOFF.md) and [PHASE_STATUS](../PHASE_STATUS.md); the 2026-10-02 pair is [archived](../handoff/archive/README.md) |

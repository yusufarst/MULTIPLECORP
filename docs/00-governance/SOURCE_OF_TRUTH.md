# Source of truth and documentation governance

Status: REVIEW | Updated: 2026-09-27 | Owner: Planning

## Authority and provenance

The Git repository is the canonical project record. Chat, model memory, task summaries, generated output, and vendor adapters are not alternative sources of project truth. Capture important instructions and decisions in the owning document before dependent implementation.

The complete [owner brief](sources/OWNER_BRIEF_2026-09-27.txt) was supplied on 2026-09-27 as `Pasted text.txt` (attachment identifier `11bf7809-d372-467e-9fa5-99b9d2c66b0a`). It contains 58 numbered sections and 2,089 lines. It is preserved byte-for-byte with SHA-256:

`D455EBA17A9D596BEFCC0C374F68F9EF6B077214E0D279498EBC090AC2FA7A55`

Archive classification: **LOCKED SOURCE RECORD**, meaning immutable provenance, not an approved implementation specification. The source is evidence of the owner's directives and retained requirements. Do not edit it to match a later decision; capture changes in canonical documents and the decision log.

The archive and specifications have different jobs. Until a concern is formalized, its brief sections remain the requirements baseline. A later APPROVED/LOCKED concern document becomes the working specification only when it records source coverage and explicit decisions for omissions or changes. An omission does not cancel a requirement. The archive remains historical evidence rather than a competing editable specification.

The subsequent [architect mandate](sources/ARCHITECT_MANDATE_2026-09-27.txt) was supplied on the same date in attachment `ad37c23f-8ec5-4fea-8e65-5c5b9659196f`. Its 586 lines are preserved byte-for-byte with SHA-256:

`1FAB6231FB214E0147C0F22C4FEA357B72BA7C5118D5D24C9770F093901EB56E`

Both files are LOCKED SOURCE RECORDS. The later mandate supersedes earlier blanket owner-approval wording for Level 1 technical planning improvements. It does not approve the full P0 package, change business requirements, authorize production access, or start application execution. Its authority applies now, independently of draft document labels.

P0 documents organize these inputs. They do not claim to have resolved all business details or authorize construction from the brief alone.

## Classify statements before changing them

| Class | Meaning | Treatment |
| --- | --- | --- |
| OWNER INTENT | Explicit business requirements and latest owner decisions | Preserve; changing them follows Level 2 or a Level 3 challenge |
| CURRENT TECHNICAL BASELINE | Preferred engineering approach with reasons and constraints | Critique and improve directly when Level 1 conditions hold; identify affected evidence/dependencies |
| PLANNER PROPOSAL | Unaccepted design or assumption | Revise or remove when better reasoning emerges; never present it as owner intent |

Do not relabel an explicit owner constraint as a mere preference to bypass it. Conversely, a baseline or proposal is not immutable merely because it appeared in an earlier file. Status describes review maturity; statement class describes authority. Classification and escalation rules have one home in [CHANGE_CONTROL](CHANGE_CONTROL.md#decision-authority).

## Canonical ownership registry

One document owns each concern. Other files link to that owner. Paths listed as planned are reservations, not existing or approved specifications.

| Concern | Owning path | State / phase | Source sections |
| --- | --- | --- | --- |
| Identity and phase boundary | `docs/00-governance/PROJECT_CHARTER.md` | Exists / P0 | Opening, 2, 46–47, 55–56, 58 |
| Provenance, ownership, lifecycle, conflicts | This document | Exists / P0 | 1, 48–50 |
| Agent conduct and handoff process | `docs/00-governance/AGENT_OPERATING_MODEL.md` | Exists / P0 | Opening, 48, 51, 53–54 |
| Decision authority and change/ADR process | `docs/00-governance/CHANGE_CONTROL.md` | Exists / P0 | 47, 50, 52; mandate: Decision Authority |
| Gaps, risk treatment and decision requests | `docs/00-governance/GAP_REGISTER.md` | Exists / P0 extension | Mandate: Proactive Gap Register |
| Decision/approval evidence | `docs/00-governance/DECISION_LOG.md` | Exists / P0 | 1, 50, 52 |
| Cross-cutting engineering guardrails | `docs/00-governance/ENGINEERING_PRINCIPLES.md` | Exists / P0 | 18–44, 58; detailed designs reserved below |
| P0 gate evidence | `docs/00-governance/P0_QUALITY_GATE.md` | Exists / P0 | 56–57 |
| Current progress and next task | `docs/07-handoff/CURRENT_STATE.md`, `NEXT_ACTION.md` | Exists / P0 | 51, 55–57 |
| Product explanation | `docs/01-product/PRODUCT_OVERVIEW.md` | Planned / P1 | 2–17, 46 |
| V1 inclusion and exclusion decisions | `docs/01-product/V1_SCOPE.md` | Planned / P1 | 2–17, 45–47; use an exclusion section in this file |
| Product acceptance criteria | `docs/01-product/ACCEPTANCE_CRITERIA.md` | Planned / P1 | 41–42, 46 |
| Entities and relationships | `docs/02-domain/DOMAIN_MODEL.md` | Planned / P2 | 3–17 |
| Business invariants and calculations | `docs/02-domain/BUSINESS_RULES.md` | Planned / P2 | 3–17, 26, 36, 45 |
| State transitions and user workflows | `docs/02-domain/WORKFLOWS.md` | Planned / P3 | 11–16, 42 |
| Database design and constraints | `docs/03-architecture/DATABASE.md` | Planned / P4 | 5, 13–15, 26–32, 38, 45 |
| Application structure and stack | `docs/03-architecture/ARCHITECTURE.md` | Planned / P4 | 18–19, 35 |
| Permissions and company-scope rules | `docs/02-domain/PERMISSIONS_MATRIX.md` | Planned / P5 | 17, 24, 41 |
| Security control design | `docs/03-architecture/SECURITY.md` | Planned / P5 | 24–25, 36–38 |
| Concurrency and idempotency mechanisms | `docs/03-architecture/CONCURRENCY_IDEMPOTENCY.md` | Planned / P6 | 14, 27–28, 31–32, 35 |
| Query/runtime performance design | `docs/03-architecture/PERFORMANCE.md` | Planned / P6 | 29–31, 35 |
| Routes and integration boundaries | `docs/03-architecture/API_AND_INTEGRATIONS.md` | Planned / P6 | 9, 33–35 |
| Navigation, admin interaction, visual patterns | `docs/04-ux/INFORMATION_ARCHITECTURE.md`, `ADMIN_FLOW.md`, `DESIGN_SYSTEM.md` | Planned / P7 | 11, 20–23; navigation / interaction / visual rules respectively |
| Testing, security matrix, performance targets, completion criteria | `docs/05-quality/TEST_STRATEGY.md`, `SECURITY_TEST_MATRIX.md`, `PERFORMANCE_TARGETS.md`, `DEFINITION_OF_DONE.md` | Planned / P8 | 40–44; strategy / security cases / measurable targets / completion respectively |
| Recovery procedures and targets | `docs/03-architecture/BACKUP_RECOVERY.md` | Planned / P9 | 37–39 |
| Deployment topology and operational controls | `docs/03-architecture/INFRASTRUCTURE.md` | Planned / P9 | 19, 37–39 |
| Schedule, dependencies, release and migration execution | `docs/06-delivery/ROADMAP_18_DAYS.md`, `IMPLEMENTATION_ORDER.md`, `RELEASE_PLAN.md` | Planned / P10 | 43, 45–47, 55; dates / ordering / release respectively |
| Bounded task specifications | `docs/06-delivery/units/<ID>.md` | Planned / P11 | 44, 53–54 |
| Decision rationale and alternatives | `docs/adr/ADR-NNN-<subject>.md` | As needed | 50, 52 |

No separate `OUT_OF_SCOPE.md` is needed initially: scope exclusions have one home in `V1_SCOPE.md`. Split only if navigation becomes materially clearer and the ownership map is updated.

Detailed phase documents refine guardrails; they do not silently repeal them. When content moves between owners, update links, source coverage, and decision evidence in the same change.

## Document lifecycle

Use `Status`, `Updated` (ISO date), and `Owner` (responsible role) metadata. APPROVED/LOCKED specifications additionally require `Approval` pointing to a decision-log entry with the approver, date, and exact reviewed revision. Source records and append-only factual logs are not implementation specifications.

| Status | Meaning | Implementation eligible? |
| --- | --- | --- |
| DRAFT | Work in progress; assumptions may be unresolved | No |
| REVIEW | Coherent proposal ready for review | No |
| APPROVED | Authorized reviewer accepted the identified revision | Yes, subject to role, phase and dependency gates |
| LOCKED | Approved baseline frozen for controlled changes | Yes, under the same gates |
| SUPERSEDED | Replaced; link the successor and decision | No new work from it |

Normal flow: DRAFT → REVIEW → APPROVED → LOCKED. REVIEW may return to DRAFT. Replacements mark old artifacts SUPERSEDED and identify the successor. LOCKED means change-controlled, not unchangeable.

Agents may prepare proposals and record verifiable facts. The planner may also directly accept bounded Level 1 technical changes under DIR-005, recording classification, affected revision, rationale and verification; new owner confirmation is unnecessary. They must not manufacture owner/business approval or use silence as consent. A status label alone supplies no authority. The owner may approve a reviewed set together; record the complete file/revision set in one approval entry. REVIEW documents may be improved without seeking permission; implementation eligibility still requires an authorized execution phase and approved specifications/dependencies.

For a substantive edit to an approved document, identify the last approved revision and classify the change. A Level 1 technical amendment may retain approval after the planner records delegated acceptance and verifies unchanged business behavior/scope. Level 2/3 changes remain REVIEW until the owner decides. Stop only affected execution if the existing approved plan is unsafe or contradicted by new evidence; never force implementation of a known-bad revision. Mark the unsafe revision and affected tasks in the gap register/handoff. Editorial corrections retain status with a short record. No initial product or implementation baseline has been approved yet.

## Conflict resolution

1. Check task/platform constraints and explicit owner direction. Record a new owner instruction and its impact before using it to change repository policy; do not hide it in chat.
2. Locate the owning concern document, status, exact approved revision, relevant decision entry, and ADR. File recency, code behavior, or model preference alone confer no authority.
3. The latest explicit owner instruction and valid delegated technical decision govern their recorded scope. Preserve owner intent when a baseline/proposal conflicts with it. Classify technical contradictions under the authority levels instead of automatically escalating all corrections.
4. Record competing statements, affected tasks, and a proposed resolution in the decision log or a change request. Stop only the work that depends on that unresolved contradiction. Continue safe independent work within the authorized phase.
5. Resolve Level 1 conflicts directly with evidence. For Level 2 or Level 3 conflicts, flag OWNER DECISION REQUIRED and wait only on dependent choices. Update the owning document, relevant ADR/links, and handoff. Do not silently change business meaning or permissions.

An accepted ADR explains why a decision was made. The concern document states the current rule. They must be updated together when a decision changes; neither wins silently if they disagree. README, adapters, examples, tests, and generated files do not override canonical specifications. Report code/spec drift rather than weakening a valid test.

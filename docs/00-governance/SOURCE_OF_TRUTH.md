# Source of truth and documentation governance

Status: APPROVED | Updated: 2026-09-29 | Owner: Planning

Approval: [APPR-001](DECISION_LOG.md), P0 governance at b425584. Subsequent directives and continuity updates remain recorded in the decision log. [APPR-002](DECISION_LOG.md#appr-002--p1-product-definition-and-v1-scope-approved) approves the three P1 canonical product specifications and [APPR-003](DECISION_LOG.md#appr-003--p2-domain-baseline-approved) the two P2 normative domain specifications; evidence, gap and handoff records remain REVIEW.

## Authority and provenance

The Git repository is the canonical project record. Chat, model memory, task summaries, generated output, and vendor adapters are not alternative sources of project truth. Capture important instructions and decisions in the owning document before dependent implementation.

The complete [owner brief](sources/OWNER_BRIEF_2026-09-27.txt) was supplied on 2026-09-27 as `Pasted text.txt` (attachment identifier `11bf7809-d372-467e-9fa5-99b9d2c66b0a`). It contains 58 numbered sections and 2,089 lines. It is preserved byte-for-byte with SHA-256:

`D455EBA17A9D596BEFCC0C374F68F9EF6B077214E0D279498EBC090AC2FA7A55`

Archive classification: **LOCKED SOURCE RECORD**, meaning immutable provenance, not an approved implementation specification. The source is evidence of the owner's directives and retained requirements. Do not edit it to match a later decision; capture changes in canonical documents and the decision log.

The archive and specifications have different jobs. Until a concern is formalized, its brief sections remain the requirements baseline. A later APPROVED/LOCKED concern document becomes the working specification only when it records source coverage and explicit decisions for omissions or changes. An omission does not cancel a requirement. The archive remains historical evidence rather than a competing editable specification.

The subsequent [architect mandate](sources/ARCHITECT_MANDATE_2026-09-27.txt) was supplied on the same date in attachment `ad37c23f-8ec5-4fea-8e65-5c5b9659196f`. Its 586 lines are preserved byte-for-byte with SHA-256:

`1FAB6231FB214E0147C0F22C4FEA357B72BA7C5118D5D24C9770F093901EB56E`

These first two files are LOCKED SOURCE RECORDS. The autonomy mandate superseded blanket owner-approval wording for Level 1 improvements. At that point it did not approve the full P0 package or application execution; the subsequent P1 instruction below expressly accepts P0 governance.

The [P1 owner directive](sources/P1_OWNER_DIRECTIVE_2026-09-27.txt), attachment `669acc93-0089-41a5-ac5e-d371ee5c4d25`, has 791 lines and SHA-256:

`B3DBFCE778AA4CEED126ED16AC0113F983BC66595604CD893FC5AB391C566B0E`

It accepts P0 governance at the last delivered commit `b425584daa80af2c1342252407c95e08024d3173` (APPR-001), authorizes **P1 only**, and provides binding product constraints. It supersedes weaker/contradictory earlier mobile-priority wording and the unaccepted company-segregated-stock proposal; explicit owner intent prevails. The full product/implementation plan is not thereby approved.

The subsequent [P1 owner clarifications](sources/P1_OWNER_CLARIFICATIONS_2026-09-27.txt) record the exact wording of three in-session questions/answers in 25 lines. This is a transcribed source record, not a claim of byte identity to an attachment. SHA-256:

`C89ED0128EFA759C33AA8A1E88024420A08526F735308ECCA66838D8D657AA51`

C1 delegates a complete recommended selectable document set; the planner's bounded catalog is owned by V1_SCOPE. C2 names Owner/Admin Operasional as UAT/migration validators. C3 reports VPS capacity; at that point it did not answer the offsite-backup or free-disk question. DIR-009 below subsequently settles local-only backup; free capacity still requires measurement. Both new records are also LOCKED SOURCE RECORDS. The chronological sequence is original brief → autonomy mandate → P1 directive → explicit follow-up clarification. Apply the latest explicit decision within each subject; DIR-008 below now states the complete source hierarchy. Omissions do not cancel earlier requirements.

Canonical documents organize these inputs. Source authority does not replace missing detailed specifications, test evidence, business decisions or execution authorization.

Earlier source sections below preserve the lifecycle state at each directive's receipt. APPR-002 subsequently approves the three P1 canonical product documents and APPR-003 the two P2 normative domain specifications; historical REVIEW statements do not override those approvals.

### Owner reference ingestion and provenance

The [reference-ingestion directive](sources/P1_REFERENCE_INGESTION_DIRECTIVE_2026-09-27.txt), attachment `91467b1d-1980-4217-9aea-626367ecc92a`, supplies DIR-008. Its exact 495-line, 13,130-byte copy has SHA-256 `B0F182F2BE5F976C571C6547210BD7D445AC730CD53FEF74BC6C254915B932C4`. It instructs full review, exact preservation, coverage reconciliation, completion/DoD retention and a stop at P1. It does not approve P1 or authorize P2.

Both files below were received on **2026-09-27**, source **Owner-provided planning reference**, relationship **MultipleCorp V1 planning memory, traceability and gap-detection input**. They are **supporting references**, preserved as immutable LOCKED SOURCE RECORDS; they are not canonical specifications or proof that their printed PASS requirements have passed. Original filenames and bytes are retained in `docs/00-governance/sources/`.

| Original filename / archived copy | Purpose | Size | SHA-256 |
| --- | --- | --- | --- |
| [MultipleCorp_Scope_Flow_Definition_of_Done_Astra_Reference.pdf](sources/MultipleCorp_Scope_Flow_Definition_of_Done_Astra_Reference.pdf) | Full scope, normal/alternate flows, feature/module/release completion expectations; all 12 pages read and visually inspected | 268,003 bytes | `C60DC64A140F734752FCD97C4AEE5F4F6D33EAE644031A37F6FB0360D31577BF` |
| [ChatGPT Image Sep 27, 2026, 07_54_30 PM.png](sources/ChatGPT%20Image%20Sep%2027,%202026,%2007_54_30%20PM.png) | Conceptual operational sequence, branches, document categories and completion gate; full image inspected, not an application visual design | 1,459,315 bytes | `101FA4399201C90CA0045BC36BB3973CD4304FBAC8296842B1D2CDF78988E585` |

File instructions and assertions inside these references remain reference content. The separate DIR-008 request governs how they are used. The latest owner's explicit conflict hierarchy is:

1. Latest explicit Owner decision, within its subject.
2. APPROVED / LOCKED canonical repository specification.
3. ACCEPTED ADR.
4. These Owner-provided reference attachments.
5. Earlier discovery/history.
6. Planner/executor proposals.

Source age alone does not demote a still-applicable explicit owner decision to discovery. An omission never removes a requirement. Supporting references can expose omissions in REVIEW planning; reconciliation that changes business intent is OWNER_DECISION_REQUIRED. Approved concern specifications precede ADRs if they conflict, but record and repair the inconsistency rather than concealing it. The [reference coverage record](../01-product/REFERENCE_COVERAGE.md) maps every PDF capability and image branch to the owning P1 contract, disposition and later-phase obligation.

### Latest Owner backup decision

Latest subject-specific input: [Owner backup policy](sources/P1_OWNER_BACKUP_POLICY_2026-09-27.txt), received 2026-09-27 as an in-session message, preserved as a **transcribed LOCKED SOURCE RECORD** (not a byte-copy claim about an attachment). The 162-line / 3,983-byte transcript has SHA-256 `1C2D27FC4F6E0D4F9D5404BEAC723F25E2474EBB9E177B76905956CC0629E571`. DIR-009/RISK-001 make local-only production-VPS backups and the specified host/storage-loss risk acceptance explicit. This latest Owner decision overrides earlier offsite requirements, including the PDF's REF-060 and approved engineering baseline; the six-level hierarchy itself is unchanged. P1 remains REVIEW and P2 unauthorized. Earlier source files retain their original wording/hashes; current policy belongs to V1_SCOPE and the decision/risk record.

### Interruption recovery instruction

The Owner's in-session request received 2026-09-28 is captured as DIR-010 in [DECISION_LOG](DECISION_LOG.md#dir-010-and-tech-006--interrupted-p1-recovery), with an exact operative quote and a summary of the authorized recovery scope. It requires inspecting and preserving pending work, repairing unfinished P1 only, rechecking evidence and stopping before P2/implementation. It reaffirms DIR-009/RISK-001 and specifies that the offsite requirement must not reopen unless Owner changes the decision. Local technical constraints remain reportable under GAP-011 without changing that policy. This continuation is not P1 approval; all eight archived source records retain their bytes and classifications.

### Final P1 Owner decisions and provenance

The [final Owner decisions](sources/P1_FINAL_OWNER_DECISIONS_2026-09-28.txt) were received on 2026-09-28 in attachment `ece7b1ba-aade-4c49-8ff7-cd6269ff6679` (`Pasted text.txt`). The exact **310-line / 8,347-byte** copy is a **LOCKED SOURCE RECORD**, with SHA-256:

`8F6ACB2A9849AAF49FEA6410A4C2DC2AA927CB8F36808B79FE5B67E1D7406E98`

DIR-011 supplies binding financial, company-visibility, completion-exception, reservation and source-attribution decisions and reaffirms local-only backup. It resolves the four P1 Owner choices without pretending later technical design or runtime evidence exists. The latest explicit instruction takes precedence over older unanswered-policy wording and reference interpretations; all earlier eight archives remain immutable. V1_SCOPE owns business meaning, ACCEPTANCE_CRITERIA owns observable outcomes, REFERENCE_COVERAGE maps OWN-01–06, and GAP_REGISTER owns technical residuals. This decision approval is distinct from approval of the whole reconciled P1 package; P1 remains REVIEW and P2/implementation are unauthorized.

### Final-decision recovery instruction and provenance

The [latest recovery instruction](sources/P1_FINAL_DECISIONS_RECOVERY_2026-09-28.txt), attachment `96bbb1ed-9b69-4a6e-9dcb-3ca60fcdb2b8`, was received on 2026-09-28. Its exact **375-line / 10,681-byte** copy is the tenth **LOCKED SOURCE RECORD**, SHA-256:

`443B64CCD1ED92445682EF7F8F157DB75D1FDAFD0E37E10042D25B5FEB3E30C3`

DIR-012 preserves DIR-011 decisions, full required scope, Golden Flow/DoD and local-only backup risk. It requires inspection before recovery, final reporting and a stop at P1. Its final prohibition on staging/commit/push until explicitly instructed after the recovery report controls the immediate handoff. This does not approve P1 or authorize P2; all nine earlier source bytes remain unchanged. TECH-008 completes interrupted evidence and continuity work. Business meaning remains owned by V1_SCOPE; this source records provenance.

### Explicit P1 Owner approval and checkpoint instruction

The [P1 Owner approval](sources/P1_OWNER_APPROVAL_2026-09-28.txt), attachment `1236307c-556b-48b3-96ff-ab73dacbf3e7`, was received on 2026-09-28. The exact **206-line / 5,782-byte** copy is the eleventh **LOCKED SOURCE RECORD**, SHA-256:

`755041DE544AAF00277DC584E0FB04664001F9AE468D2387E449BC804137CA17`

APPR-002 approves the recovered PRODUCT_OVERVIEW, V1_SCOPE and ACCEPTANCE_CRITERIA; DECISION_LOG records their exact reviewed pre-approval hashes and conditions. Only lifecycle/approval wording changes, not business requirements. REFERENCE_COVERAGE, P1_QUALITY_GATE, logs, gaps and handoff retain REVIEW. All ten earlier source records remain immutable.

DIR-013 supplies the explicit post-recovery instruction required by DIR-012: verify and create the local approval checkpoint, then inspect remote origin/default refs/tracking/history. It does not authorize push, remote changes, P2 or application implementation. Later technical gaps remain traceable and do not suspend P1 approval.

### Latest explicit local checkpoint authorization

The [checkpoint authorization](sources/P1_OWNER_CHECKPOINT_AUTHORIZATION_2026-09-28.txt), attachment `d340c6be-985c-46af-8ae7-4f55c24c856d`, was received on 2026-09-28 after two automatically rejected staging requests. The exact **559-line / 15,225-byte** copy is the twelfth **LOCKED SOURCE RECORD**, SHA-256:

`AE18F2F6A5E5AE0A6F013AD7638D77A86520937D2F946383F0AAAE8B76E77E65`

DIR-014 reaffirms APPR-002 and explicitly authorizes verified local staging, complete staged-diff review, the named checkpoint commit and only then read-only remote diagnosis. Its description of the earlier REVIEW state is historical: the prior attempt had already correctly applied APPROVED metadata locally. Preserve those completed changes. If automatic review rejects again, record the exact blocked action/reason and STOP without bypass or variants. No push, remote configuration/history change, P2 or application implementation is authorized. All eleven earlier archives remain unchanged.

### Owner publication confirmation and bounded continuity authorization

The [publication confirmation](sources/P1_OWNER_PUBLICATION_CONFIRMATION_2026-09-29.txt) was received on 2026-09-29 as an in-session Owner message and is preserved as a **transcribed LOCKED SOURCE RECORD** (not a byte-copy claim about an attachment), the thirteenth source record. The 348-line / 8,988-byte transcript has SHA-256:

`1939FED4EF714C75FC1A3762FC56F6ADE2DB66CCB3F835DD3044EABE8C632931`

DIR-015 confirms that the Owner personally and intentionally executed the normal, non-force initial push (`git push --set-upstream origin main`) after commit `f31baf768550e8249cb8073c6ea879c2f17c470b`, creating remote `main` at that commit. The earlier "origin has no refs / no push executed" statements in the committed handoff were accurate at their commit time and are superseded by this later Owner action, not corrected as errors. DIR-015 authorizes exactly: recording the publication, independent receiver-checkout verification, the evidenced GAP-002 update, one bounded documentation checkpoint commit and its normal non-force push to origin/main. It does not authorize P2, changes to approved product content, business semantics, coverage, Golden Flow, DoD, backup policy or application work. Verified results are OBS-003 in [DECISION_LOG](DECISION_LOG.md); all twelve earlier archives remain immutable.

### P2 authorization, deep review and final decisions

Five in-session Owner messages received 2026-09-29 are preserved as **transcribed LOCKED SOURCE RECORDS** fourteen through eighteen (transcripts, not byte-copy claims about attachments). They authorize P2, order its deep review with bounded Level-1 autonomy and the multi-unit modelling requirement, decide the dates/backdating/numbering, residual-disposition and SIPLAH-fee questions, and — after the pre-checkpoint audit — decide the fee-settlement generalization with its evidence-correction instructions; the fourth transcript also contains the Owner's micro-correction, and the plan-approval exchange authorized documentation execution (DIR-016–020 in [DECISION_LOG](DECISION_LOG.md#dir-016019-and-tech-012--p2-authorization-deep-review-and-final-business-decisions)).

| Archived transcript | Lines / bytes | SHA-256 |
| --- | --- | --- |
| [P2_OWNER_AUTHORIZATION_2026-09-29.txt](sources/P2_OWNER_AUTHORIZATION_2026-09-29.txt) | 994 / 20,841 | `A710619AFD59D788E042E7D55308164CE1F0FC16B8C85AB43909A1E95C837535` |
| [P2_OWNER_DEEP_REVIEW_DIRECTIVE_2026-09-29.txt](sources/P2_OWNER_DEEP_REVIEW_DIRECTIVE_2026-09-29.txt) | 431 / 11,891 | `97C0528D6B25F0760F9CDDA9E70829419F64F40D80E8BA1C1B5D674129DE1B8E` |
| [P2_OWNER_Q1_DATES_NUMBERING_DECISION_2026-09-29.txt](sources/P2_OWNER_Q1_DATES_NUMBERING_DECISION_2026-09-29.txt) | 89 / 2,881 | `FF04E60CB98CA5C7BAF47E933C22B0D2062D40FA6016929FE2B4916DE421DF1D` |
| [P2_OWNER_FINAL_DECISIONS_2026-09-29.txt](sources/P2_OWNER_FINAL_DECISIONS_2026-09-29.txt) | 191 / 5,863 | `71EA03F2930380069E97C0BF706B0BA5841E29A55394509CFBD3BE981A5272A5` |
| [P2_OWNER_FEE_GENERALIZATION_DECISION_2026-09-29.txt](sources/P2_OWNER_FEE_GENERALIZATION_DECISION_2026-09-29.txt) | 169 / 4,913 | `4F361A7F4E57343D1347879FBE85AA0939EAF4D0AC17E835CF219F594769ED44` |

DIR-018/019/020 are binding within their subjects under the standing hierarchy; earlier records remain immutable. Canonical business meaning continues to live in V1_SCOPE and, for the new P2 formalization, in [DOMAIN_MODEL](../02-domain/DOMAIN_MODEL.md)/[BUSINESS_RULES](../02-domain/BUSINESS_RULES.md) (REVIEW at these directives' receipt; subsequently APPROVED under APPR-003 below). All thirteen earlier source records retain their bytes and classifications.

### P2 Owner approval and finalization authorization

The [P2 Owner approval](sources/P2_OWNER_APPROVAL_2026-09-29.txt) was received on 2026-09-29 as an in-session Owner message after the P2 checkpoint `1392966bfb89581d705e0394424705978e1d3db8` was committed and published. It is preserved as a **transcribed LOCKED SOURCE RECORD** (not a byte-copy claim about an attachment), the nineteenth source record. The 280-line / 7,466-byte transcript has SHA-256:

`E414A6C2304329930A7F81D10F1D9E5588EC6EBC279B279C99025DDD5AA685CA`

APPR-003/DIR-021 in [DECISION_LOG](DECISION_LOG.md#appr-003--p2-domain-baseline-approved) record its effect: the Owner explicitly approves the completed P2 domain baseline; [DOMAIN_MODEL](../02-domain/DOMAIN_MODEL.md) and [BUSINESS_RULES](../02-domain/BUSINESS_RULES.md) change REVIEW → APPROVED with unchanged substantive content; [P2_QUALITY_GATE](../02-domain/P2_QUALITY_GATE.md) and the live registers/logs/handoff stay REVIEW; and one bounded continuity commit plus a normal non-force push are authorized. This record also serves as the Owner's archived confirmation of the earlier in-session checkpoint-and-publication authorization that the checkpoint report had noted as unarchived. It does not reopen Q1/Q2/Q3, DIR-020 or the PLANNER-DETERMINED Level-1 decisions, and does not authorize P3, WORKFLOWS.md, schema/ERD/migrations or application work. All eighteen earlier source records retain their bytes and classifications.

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
| Product explanation, users and goals | `docs/01-product/PRODUCT_OVERVIEW.md` | Exists, REVIEW / P1 | 2–17, 46; P1 identity and user/product direction |
| V1 priorities, exclusions, product cost/dependencies and P7 obligations | `docs/01-product/V1_SCOPE.md` | Exists, REVIEW / P1 | 2–17, 45–47; P1 constraints and C1–C3 |
| Product acceptance and operational success | `docs/01-product/ACCEPTANCE_CRITERIA.md` | Exists, REVIEW / P1 | 41–42, 46; P1 targets and user/product outcomes |
| P1 adversarial review and quality evidence | `docs/01-product/P1_QUALITY_GATE.md` | Exists, REVIEW / P1 | P1 objective/review/gate/report |
| Reference comparison and requirement identifiers | `docs/01-product/REFERENCE_COVERAGE.md` | Exists, REVIEW / P1 | DIR-008; PDF §§1–14; complete flow image |
| Entities, terminology, relationships, scope/sensitivity classes | `docs/02-domain/DOMAIN_MODEL.md` | Exists, REVIEW / P2 | 2–17; DIR-016–019 |
| Business invariants, domain lifecycles/state rules and calculation semantics | `docs/02-domain/BUSINESS_RULES.md` | Exists, REVIEW / P2 | 3–17, 26–28, 36, 45; DIR-018/019 |
| P2 adversarial review, traceability verification and gate evidence | `docs/02-domain/P2_QUALITY_GATE.md` | Exists, REVIEW / P2 | DIR-016 §§18–22 |
| User/process workflows and transition orchestration over P2 lifecycles | `docs/02-domain/WORKFLOWS.md` | Planned / P3 | 11–16, 42 |
| Database design and constraints | `docs/03-architecture/DATABASE.md` | Planned / P4 | 5, 13–15, 26–32, 38, 45 |
| Application structure and stack | `docs/03-architecture/ARCHITECTURE.md` | Planned / P4 | 18–19, 35 |
| Permissions and company-scope rules | `docs/02-domain/PERMISSIONS_MATRIX.md` | Planned / P5 | 17, 24, 41 |
| Security control design | `docs/03-architecture/SECURITY.md` | Planned / P5 | 24–25, 36–38 |
| Concurrency and idempotency mechanisms | `docs/03-architecture/CONCURRENCY_IDEMPOTENCY.md` | Planned / P6 | 14, 27–28, 31–32, 35 |
| Query/runtime performance design | `docs/03-architecture/PERFORMANCE.md` | Planned / P6 | 29–31, 35 |
| Routes and integration boundaries | `docs/03-architecture/API_AND_INTEGRATIONS.md` | Planned / P6 | 9, 33–35 |
| Navigation, admin interaction, visual patterns | `docs/04-ux/INFORMATION_ARCHITECTURE.md`, `ADMIN_FLOW.md`, `DESIGN_SYSTEM.md` | Planned / P7 | 11, 20–23; navigation / interaction / visual rules respectively |
| Testing, security matrix, performance targets, completion criteria | `docs/05-quality/TEST_STRATEGY.md`, `SECURITY_TEST_MATRIX.md`, `PERFORMANCE_TARGETS.md`, `DEFINITION_OF_DONE.md` | Planned / P8 | 40–44; strategy / security cases / measurable targets / completion respectively |
| Recovery procedures and conditional local targets | `docs/03-architecture/BACKUP_RECOVERY.md` | Planned / P9 | 37–39 as superseded by DIR-009; BK-01–12 and shared-disk safety |
| Deployment topology and operational controls | `docs/03-architecture/INFRASTRUCTURE.md` | Planned / P9 | 19, 37–39 |
| Schedule, dependencies, release and migration execution | `docs/06-delivery/ROADMAP_18_DAYS.md`, `IMPLEMENTATION_ORDER.md`, `RELEASE_PLAN.md` | Planned / P10 | 43, 45–47, 55; dates / ordering / release respectively |
| Bounded task specifications | `docs/06-delivery/units/<ID>.md` | Planned / P11 | 44, 53–54 |
| Requirement-to-rule-to-unit-to-test traceability | `docs/06-delivery/FEATURE_COVERAGE_MATRIX.md` | Planned / P11; all rows required before freeze | DIR-008; seed identifiers and phase handoff in REFERENCE_COVERAGE |
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

For a substantive edit to an approved document, identify the last approved revision and classify the change. A Level 1 technical amendment may retain approval after the planner records delegated acceptance and verifies unchanged business behavior/scope. Level 2/3 changes remain REVIEW until the owner decides. Stop only affected execution if the existing approved plan is unsafe or contradicted by new evidence; never force implementation of a known-bad revision. Mark the unsafe revision and affected tasks in the gap register/handoff. Editorial corrections retain status with a short record. The initial P1 product baseline is approved under APPR-002; implementation, planning freeze and release remain separate gates.

## Conflict resolution

1. Check task/platform constraints and explicit owner direction. Record a new owner instruction and its impact before using it to change repository policy; do not hide it in chat.
2. Locate the owning concern document, status, exact approved revision, relevant decision entry, and ADR. File recency, code behavior, or model preference alone confer no authority.
3. Apply the six-level hierarchy in the provenance section. Valid delegated technical changes operate within their recorded authority; a planner proposal cannot override explicit owner intent. Classify technical contradictions under the authority levels instead of automatically escalating all corrections.
4. Record competing statements, affected tasks, and a proposed resolution in the decision log or a change request. Stop only the work that depends on that unresolved contradiction. Continue safe independent work within the authorized phase.
5. Resolve Level 1 conflicts directly with evidence. For Level 2 or Level 3 conflicts, flag OWNER DECISION REQUIRED and wait only on dependent choices. Update the owning document, relevant ADR/links, and handoff. Do not silently change business meaning or permissions.

An accepted ADR explains why a decision was made. The approved concern document states the current rule and has precedence under DIR-008. Update both when a decision changes and record any discovered contradiction. README, adapters, examples, tests, and generated files do not override canonical specifications. Report code/spec drift rather than weakening a valid test.

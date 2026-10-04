# Source of truth and documentation governance

Status: APPROVED | Updated: 2026-10-04 | Owner: Planning

Approval: [APPR-001](DECISION_LOG.md), P0 governance at b425584. Subsequent directives and continuity updates remain recorded in the decision log. [APPR-002](DECISION_LOG.md#appr-002--p1-product-definition-and-v1-scope-approved) approves the three P1 canonical product specifications, [APPR-003](DECISION_LOG.md#appr-003--p2-domain-baseline-approved) the two P2 normative domain specifications and [APPR-004](DECISION_LOG.md#appr-004--p3-critical-business-workflows-approved) the P3 workflow specification with its amended P1/P2 revisions and [APPR-005](DECISION_LOG.md#appr-005--p4-database-architecture-approved) the two P4 normative architecture specifications with the amended P2/P3 revisions and [APPR-006](DECISION_LOG.md#appr-006--p5-security-and-authorization-approved) the two P5 normative security and authorization specifications with the amended DATABASE revision and [APPR-007](DECISION_LOG.md#appr-007--p6-concurrency-idempotency-and-performance-approved) the three P6 normative concurrency, performance and integration-boundary specifications with the amended DATABASE, ARCHITECTURE, WORKFLOWS and PERMISSIONS_MATRIX revisions and [APPR-008](DECISION_LOG.md#appr-008--p7-ux-information-architecture-and-design-system-approved) the three P7 normative UX, information-architecture and design-system specifications with the amended SECURITY, DATABASE, PERFORMANCE and CONCURRENCY_IDEMPOTENCY revisions; evidence, gap and handoff records remain REVIEW.

Amendment: on 2026-10-03 the conflict hierarchy and the ownership registry were amended to apply the Owner's adoption of AICWDF v4.3 as the target operating structure (DIR-035 D2, DIR-036, DIR-039 §10.1; [TECH-023](DECISION_LOG.md#dir-034039-obs-013-and-tech-023--aicwdf-adoption-directives-migration-baseline-and-structural-migration), where the SHA-256 of the last approved revision is recorded); the amended revision is approved under [APPR-009](DECISION_LOG.md#appr-009--aicwdf-structural-migration-approved).

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

Earlier source sections below preserve the lifecycle state at each directive's receipt. APPR-002 subsequently approves the three P1 canonical product documents, APPR-003 the two P2 normative domain specifications, APPR-004 the P3 workflow specification with its amended P1/P2 revisions, APPR-005 the two P4 normative architecture specifications with the amended P2/P3 revisions, APPR-006 the two P5 normative security and authorization specifications with the amended DATABASE revision and APPR-007 the three P6 normative concurrency, performance and integration-boundary specifications with the amended DATABASE, ARCHITECTURE, WORKFLOWS and PERMISSIONS_MATRIX revisions and APPR-008 the three P7 normative UX, information-architecture and design-system specifications with the amended SECURITY, DATABASE, PERFORMANCE and CONCURRENCY_IDEMPOTENCY revisions; historical REVIEW statements do not override those approvals.

### Owner reference ingestion and provenance

The [reference-ingestion directive](sources/P1_REFERENCE_INGESTION_DIRECTIVE_2026-09-27.txt), attachment `91467b1d-1980-4217-9aea-626367ecc92a`, supplies DIR-008. Its exact 495-line, 13,130-byte copy has SHA-256 `B0F182F2BE5F976C571C6547210BD7D445AC730CD53FEF74BC6C254915B932C4`. It instructs full review, exact preservation, coverage reconciliation, completion/DoD retention and a stop at P1. It does not approve P1 or authorize P2.

Both files below were received on **2026-09-27**, source **Owner-provided planning reference**, relationship **MultipleCorp V1 planning memory, traceability and gap-detection input**. They are **supporting references**, preserved as immutable LOCKED SOURCE RECORDS; they are not canonical specifications or proof that their printed PASS requirements have passed. Original filenames and bytes are retained in `docs/00-governance/sources/`.

| Original filename / archived copy | Purpose | Size | SHA-256 |
| --- | --- | --- | --- |
| [MultipleCorp_Scope_Flow_Definition_of_Done_Astra_Reference.pdf](sources/MultipleCorp_Scope_Flow_Definition_of_Done_Astra_Reference.pdf) | Full scope, normal/alternate flows, feature/module/release completion expectations; all 12 pages read and visually inspected | 268,003 bytes | `C60DC64A140F734752FCD97C4AEE5F4F6D33EAE644031A37F6FB0360D31577BF` |
| [ChatGPT Image Sep 27, 2026, 07_54_30 PM.png](sources/ChatGPT%20Image%20Sep%2027,%202026,%2007_54_30%20PM.png) | Conceptual operational sequence, branches, document categories and completion gate; full image inspected, not an application visual design | 1,459,315 bytes | `101FA4399201C90CA0045BC36BB3973CD4304FBAC8296842B1D2CDF78988E585` |

File instructions and assertions inside these references remain reference content. The separate DIR-008 request governs how they are used. DIR-008 set an explicit six-level conflict hierarchy; on 2026-10-03 the Owner's adoption of AICWDF v4.3 (DIR-035 D2; DIR-039 §10.1) distinguished content truth from operating structure, and the current hierarchy is:

1. Latest explicit Owner decision, within its subject.
2. For **content truth** — business, domain, financial, inventory, document and authorization semantics; database, concurrency and audit guarantees — the APPROVED / LOCKED canonical repository specification.
3. For **operating structure** and default policies — AICWDF v4.3 as mapped by [AICWDF_ADOPTION](AICWDF_ADOPTION.md). It prevails over older operating structure and over planner-determined (Level 1) choices and technical defaults, through controlled amendment, never silently.
4. ACCEPTED ADR.
5. Owner-provided reference attachments, such as the two files above.
6. Earlier discovery/history.
7. Planner/executor proposals.

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

APPR-003/DIR-021 in [DECISION_LOG](DECISION_LOG.md#appr-003--p2-domain-baseline-approved) record its effect: the Owner explicitly approves the completed P2 domain baseline; [DOMAIN_MODEL](../02-domain/DOMAIN_MODEL.md) and [BUSINESS_RULES](../02-domain/BUSINESS_RULES.md) change REVIEW → APPROVED with unchanged substantive content; [P2_QUALITY_GATE](../02-domain/evidence/P2_QUALITY_GATE.md) and the live registers/logs/handoff stay REVIEW; and one bounded continuity commit plus a normal non-force push are authorized. This record also serves as the Owner's archived confirmation of the earlier in-session checkpoint-and-publication authorization that the checkpoint report had noted as unarchived. It does not reopen Q1/Q2/Q3, DIR-020 or the PLANNER-DETERMINED Level-1 decisions, and does not authorize P3, WORKFLOWS.md, schema/ERD/migrations or application work. All eighteen earlier source records retain their bytes and classifications.

### P3 authorization, Owner decisions and documentation execution

Two in-session Owner messages received 2026-09-29 are preserved as **transcribed LOCKED SOURCE RECORDS** twenty and twenty-one (transcripts, not byte-copy claims about attachments). The first authorizes the permanent Git commit attribution rule (executed as DIR-022 in commit `b921c8b07cd49351c73b0cf7f71375cc274aeddd`) and P3 planning only (DIR-023). The second decides D-1–D-5 — NET tax treatment with evidenced tax settlement, attributable purchase charges in acquisition cost, recorded inventory loss, the *Kuitansi untuk Proses Pembayaran*, and the Owner-only/ADM+ correction-authority split — and authorizes P3 documentation execution without staging, commit or push (DIR-024; [DECISION_LOG](DECISION_LOG.md#dir-023-dir-024-and-tech-015--p3-authorization-owner-decisions-and-documentation-execution)).

| Archived transcript | Lines / bytes | SHA-256 |
| --- | --- | --- |
| [P3_OWNER_AUTHORIZATION_2026-09-29.txt](sources/P3_OWNER_AUTHORIZATION_2026-09-29.txt) | 1,239 / 28,850 | `310426188804BFF3963DBC18493E2F5FFB288803B397DA072633C16AAA7228EA` |
| [P3_OWNER_DECISIONS_2026-09-29.txt](sources/P3_OWNER_DECISIONS_2026-09-29.txt) | 450 / 12,967 | `24B54C7A5087D140CEAC89172E0399D87379A718657DA5924341782BF3008A20` |

DIR-024 is binding within its subjects under the standing hierarchy. Its business meanings are formalized narrowly in [BUSINESS_RULES](../02-domain/BUSINESS_RULES.md) (BR-FIN-16/17, BR-PUR-05, BR-INV-13, amended BR-DOC-04/BR-FIN-02/04, CALC-08/09/11, annex 13–16), [DOMAIN_MODEL](../02-domain/DOMAIN_MODEL.md), the V1_SCOPE DOC-05 row and one PRODUCT_OVERVIEW sentence, each marked `DIR-024` with pre-amendment hashes in the decision log; D-5 authority is owned by [WORKFLOWS](../03-workflows/WORKFLOWS/s01-04-foundations.md#4-actor-and-authority-matrix). All nineteen earlier source records retain their bytes and classifications.

### P3 targeted review, Owner loss-attribution decision and approval

Two further in-session Owner messages received 2026-09-29 are preserved as **transcribed LOCKED SOURCE RECORDS** twenty-two and twenty-three (transcripts, not byte-copy claims about attachments). The first orders the targeted read-only Fable red-team of "conservation under correction" (DIR-025), which reported NEEDS CORRECTION with findings F-01–F-22. The second decides F-03 — for an unexplained loss of fungible shared-pool stock whose economic owner provenance cannot establish, the Owner attributes each case and no automatic rule decides — and authorizes the corrections, conditional P3 approval, one checkpoint commit and a normal non-force push (DIR-026; [DECISION_LOG](DECISION_LOG.md#dir-025-obs-006-dir-026-and-tech-016--targeted-review-owner-loss-attribution-decision-and-corrections); [APPR-004](DECISION_LOG.md#appr-004--p3-critical-business-workflows-approved)).

| Archived transcript | Lines / bytes | SHA-256 |
| --- | --- | --- |
| [P3_OWNER_TARGETED_REVIEW_DIRECTIVE_2026-09-29.txt](sources/P3_OWNER_TARGETED_REVIEW_DIRECTIVE_2026-09-29.txt) | 379 / 9,562 | `71BBF59B622808756069A161B19B9BF4EC55D2C91FC0E351E6453875D9770D97` |
| [P3_OWNER_LOSS_ATTRIBUTION_AND_FINALIZATION_2026-09-29.txt](sources/P3_OWNER_LOSS_ATTRIBUTION_AND_FINALIZATION_2026-09-29.txt) | 526 / 13,622 | `22784B8CB8741A23965B0669DAFC52B88D451826E8692779DFB98E22F6D28FDE` |

DIR-026 is binding within its subject under the standing hierarchy. Its business meaning is formalized narrowly in [BUSINESS_RULES](../02-domain/BUSINESS_RULES.md) BR-INV-13 (marked `DIR-026`) and orchestrated with Owner-only authority in [WORKFLOWS](../03-workflows/WORKFLOWS/README.md) (section 4, SF-UNATTRIBUTED, AX-37). All twenty-one earlier source records retain their bytes and classifications.

### P4 fast-track authorization and unattributed-loss clarification

One in-session Owner message received 2026-09-29, after the P3 finalization checkpoint `7c6549e88ba8538aa6e08d0fb9720589705e1dd0` was published, is preserved as a **transcribed LOCKED SOURCE RECORD** (a transcript, not a byte-copy claim about an attachment), the twenty-fourth source record:

| Archived transcript | Lines / bytes | SHA-256 |
| --- | --- | --- |
| [P4_OWNER_AUTHORIZATION_2026-09-29.txt](sources/P4_OWNER_AUTHORIZATION_2026-09-29.txt) | 1,646 / 37,652 | `FFD64781EFB95B5A8E12D77105E1ABC60EFF6011EEE7ACAD3A6E9F6DABBBD337` |

It authorizes fast-track P4 — Database Architecture documentation, self-review and validation, left uncommitted for one targeted review, and clarifies DIR-026: economic attribution ambiguity must not freeze valid physical warehouse operations (DIR-027 in [DECISION_LOG](DECISION_LOG.md#dir-027-obs-007-and-tech-017--p4-authorization-unattributed-loss-clarification-and-p4-documentation)). The clarification is formalized narrowly in WORKFLOWS (text marked `DIR-027`), BUSINESS_RULES BR-INV-13 and one DOMAIN_MODEL relationship row; the P4 outputs are [DATABASE](../04-architecture/DATABASE/README.md), [ARCHITECTURE](../04-architecture/ARCHITECTURE.md) and the evidence record [P4_QUALITY_GATE](../04-architecture/evidence/P4_QUALITY_GATE.md). It authorizes no staging, commit, push, P5 or implementation. All twenty-three earlier source records retain their bytes and classifications.

### P4 targeted review, corrections, approval and publication

Two further in-session Owner messages received 2026-09-30 are preserved as **transcribed LOCKED SOURCE RECORDS** twenty-five and twenty-six (transcripts, not byte-copy claims about attachments). The first, given in a separate read-only session, orders the targeted Fable red-team "database integrity under concurrency and correction" of the uncommitted P4 package (DIR-028), which reported NEEDS CORRECTION BEFORE P4 APPROVAL with findings RT-01–RT-37. The second authorizes the correction of those findings, the conditional approval of the P4 normative architecture baseline, one checkpoint commit, one normal non-force push and the continuity freeze for a zero-context handoff (DIR-029; [DECISION_LOG](DECISION_LOG.md#dir-028-obs-008-dir-029-and-tech-018--p4-targeted-review-corrections-and-finalization); [APPR-005](DECISION_LOG.md#appr-005--p4-database-architecture-approved)).

| Archived transcript | Lines / bytes | SHA-256 |
| --- | --- | --- |
| [P4_OWNER_TARGETED_REVIEW_DIRECTIVE_2026-09-30.txt](sources/P4_OWNER_TARGETED_REVIEW_DIRECTIVE_2026-09-30.txt) | 781 / 17,225 | `B83B06F8C5129EB41920A20E1CB3BB955B89B98647571B25736317831B3F35C8` |
| [P4_OWNER_CORRECTION_APPROVAL_AND_PUBLICATION_2026-09-30.txt](sources/P4_OWNER_CORRECTION_APPROVAL_AND_PUBLICATION_2026-09-30.txt) | 899 / 21,971 | `7743A14D60360A550E3C006E2DF430C6DB1608EC62DD7CFBFF9C197588EE9A90` |

DIR-028 changed no file; its report is review evidence whose findings and dispositions are owned by [P4_QUALITY_GATE](../04-architecture/evidence/P4_QUALITY_GATE.md). DIR-029 is binding within its subject under the standing hierarchy: it requires no Owner business decision (OWNER_DECISION_REQUIRED = 0) and its approval is conditional on the verified gate result. All twenty-four earlier source records retain their bytes and classifications.

### P5 fast-track authorization

One in-session Owner message received 2026-09-30, after the handoff continuity commit `6e640137901e0d16193e03004e142e9ea07b39ad` was published, is preserved as a **transcribed LOCKED SOURCE RECORD** (a transcript, not a byte-copy claim about an attachment), the twenty-seventh source record:

| Archived transcript | Lines / bytes | SHA-256 |
| --- | --- | --- |
| [P5_OWNER_AUTHORIZATION_2026-09-30.txt](sources/P5_OWNER_AUTHORIZATION_2026-09-30.txt) | 1,142 / 41,384 | `9220904F8AB5A2A801223B7C4DA621679C33B0E1E39057648AC96CDB73F3C282` |

It authorizes fast-track P5 — Security, Authentication & Authorization documentation with self-review, an independent adversarial review and validation, the conditional approval of [PERMISSIONS_MATRIX](../05-security/PERMISSIONS_MATRIX.md) and [SECURITY](../05-security/SECURITY.md), one checkpoint commit, one normal non-force push and the continuity freeze (DIR-031 in [DECISION_LOG](DECISION_LOG.md#dir-031-obs-010-and-tech-020--p5-authorization-entry-baseline-and-security-documentation)). Its section 10 planner notes are analysis, never Owner intent. It authorizes no P6 work. All twenty-six earlier source records retain their bytes and classifications.

### P6 fast-track authorization

One in-session Owner message received 2026-09-30, after the P5 checkpoint `b09e70f3a867d58b431c7ae0369b7a432c4fd1a1` was published, is preserved as a **transcribed LOCKED SOURCE RECORD** (a transcript, not a byte-copy claim about an attachment), the twenty-eighth source record:

| Archived transcript | Lines / bytes | SHA-256 |
| --- | --- | --- |
| [P6_OWNER_AUTHORIZATION_2026-09-30.txt](sources/P6_OWNER_AUTHORIZATION_2026-09-30.txt) | 1,415 / 58,143 | `38F89EE64D3FA138322875DB10771C50D0BC8F0268785F37A6351558AC2021DC` |

It authorizes fast-track P6 — Concurrency, Idempotency & Performance documentation with self-review, an independent adversarial review and validation, the conditional approval of [CONCURRENCY_IDEMPOTENCY](../06-api-performance/CONCURRENCY_IDEMPOTENCY/README.md), [PERFORMANCE](../06-api-performance/PERFORMANCE.md) and [API_AND_INTEGRATIONS](../06-api-performance/API_AND_INTEGRATIONS.md), one checkpoint commit, one normal non-force push and the continuity freeze (DIR-032 in [DECISION_LOG](DECISION_LOG.md#dir-032-obs-011-and-tech-021--p6-authorization-entry-baseline-and-concurrency-documentation)). The message arrived as one pasted text block whose code-fence lines are not part of the transcript, and the Owner confirmed its full execution in-session before any edit. Its section 12 planner notes are analysis, never Owner intent. It authorizes no P7 work. All twenty-seven earlier source records retain their bytes and classifications.

### P7 fast-track authorization

One in-session Owner message received 2026-10-01, after the P6 checkpoint `ff92c415c164f9fea3758fead256a2df53a74211` was published, is preserved as a **transcribed LOCKED SOURCE RECORD** (a transcript, not a byte-copy claim about an attachment), the twenty-ninth source record:

| Archived transcript | Lines / bytes | SHA-256 |
| --- | --- | --- |
| [P7_OWNER_AUTHORIZATION_2026-10-01.txt](sources/P7_OWNER_AUTHORIZATION_2026-10-01.txt) | 2,278 / 95,681 | `BA5CDD8ADDA52803B1FE8187ECE9D4E65AA88690933BFAD2053E984C7BEF8F60` |

It authorizes fast-track P7 — UX, Information Architecture & Design System documentation, with DesainPakai as the primary design-exploration tool and never a source of truth, the GAP-034 settlement before the Owner review queue, self-review, three independent adversarial reviews and validation, the conditional approval of [INFORMATION_ARCHITECTURE](../07-ux-design/INFORMATION_ARCHITECTURE.md), [ADMIN_FLOW](../07-ux-design/ADMIN_FLOW/README.md) and [DESIGN_SYSTEM](../07-ux-design/DESIGN_SYSTEM.md), one checkpoint commit, one normal non-force push and the continuity freeze (DIR-033 in [DECISION_LOG](DECISION_LOG.md#dir-033-obs-012-and-tech-022--p7-authorization-entry-baseline-and-ux-documentation)). Its §1 records the Owner's acceptance of the P6 Level-1 baseline as Owner intent. The message arrived as one pasted text block whose code-fence lines are not part of the transcript, and the Owner confirmed its full execution in-session before any edit. It contains no credential. Its section 14 planner notes are analysis, never Owner intent. It authorizes no P8 work. All twenty-eight earlier source records retain their bytes and classifications.

### AICWDF v4.3 framework source, adoption directives and migration authorization

Seven records received on 2026-10-03, after the P7 checkpoint `c511d7b0d4683e07717c962927c9f113854f227b` was published, are preserved as LOCKED SOURCE RECORDS thirty to thirty-six.

The thirtieth is the Owner-supplied framework source, preserved byte-for-byte under its original filename. It arrived attached to the migration authorization and is byte-identical to the planning-workspace copy that the authorization cites (76,025 bytes, the same SHA-256):

| Original filename / archived copy | Framework ID / version | Lines / bytes | SHA-256 |
| --- | --- | --- | --- |
| [AI_First_Complex_WebApp_Task_Framework_v4.3_EN.md](sources/AI_First_Complex_WebApp_Task_Framework_v4.3_EN.md) | AICWDF-4.3 / 4.3 — English | 3,587 / 76,025 | `BB578ADABCD8EBDDCB97C278E6589B935137F26F851B7218DD86C141744B70FE` |

The other six are **transcribed LOCKED SOURCE RECORDS** (transcripts, not byte-copy claims about attachments): five Owner messages sent to the planning chat on 2026-10-03 and relayed by the Owner as Appendices A–E of the migration authorization, and that authorization itself, transcribed from its first line up to its APPENDICES heading. The ZIP of the P7 prototype that Appendix A refers to was not relayed and is not archived. None of the seven records contains a credential.

| Archived transcript | Lines / bytes | SHA-256 |
| --- | --- | --- |
| [AICWDF_OWNER_CORRECTION_2026-10-03.txt](sources/AICWDF_OWNER_CORRECTION_2026-10-03.txt) | 225 / 7,250 | `06E412EBF9E21899C2FCCBFD880921472B0A595463DC7DF3CA67A77005340588` |
| [AICWDF_OWNER_DECISIONS_ROUND1_2026-10-03.txt](sources/AICWDF_OWNER_DECISIONS_ROUND1_2026-10-03.txt) | 188 / 6,475 | `4201B95357520A469295A76EBF87B774289B79D199A97308E1014DEBD28E431A` |
| [AICWDF_OWNER_STRUCTURAL_CONFORMANCE_2026-10-03.txt](sources/AICWDF_OWNER_STRUCTURAL_CONFORMANCE_2026-10-03.txt) | 308 / 9,041 | `5568517B8CB31B54B1A97EEE36BBDADBE853A08BD380FCB66C01F30D8CF40875` |
| [AICWDF_OWNER_FINAL_DECISIONS_2026-10-03.txt](sources/AICWDF_OWNER_FINAL_DECISIONS_2026-10-03.txt) | 143 / 4,582 | `313E25A7BB8BD9FDFF9E32EE121EE02B8DED1CF417E0D34B209367BDF58063DD` |
| [AICWDF_OWNER_BLUEPRINT_APPROVAL_2026-10-03.txt](sources/AICWDF_OWNER_BLUEPRINT_APPROVAL_2026-10-03.txt) | 85 / 3,227 | `22DCBA9ED1F4E262DC9DD26BB0243EAF09DAAD009C56716C41E55885CD837143` |
| [AICWDF_MIGRATION_OWNER_AUTHORIZATION_2026-10-03.txt](sources/AICWDF_MIGRATION_OWNER_AUTHORIZATION_2026-10-03.txt) | 779 / 42,730 | `9458DBBA8CB50B95AC04DF9C4D49656FEFD08305233D7204E295414ECE3C1A05` |

They are DIR-034–DIR-039 in [DECISION_LOG](DECISION_LOG.md#dir-034039-obs-013-and-tech-023--aicwdf-adoption-directives-migration-baseline-and-structural-migration). The decisions of Appendices A–E are binding within their subjects under the standing hierarchy; their instructions to the planning chat (for example not to modify the repository yet) are archived source text, not instructions to a repository executor. The migration authorization (DIR-039) authorizes stages 0–3 of the Owner-approved structural migration only. The framework source is provenance: the operative rules are the active repository documents that map and apply it (DIR-038). All twenty-nine earlier source records retain their bytes and classifications.

### AICWDF migration approval and finalization authorization

Two records received on 2026-10-03, with `migration/aicwdf` at its stage-3 commit `0dbbb44f3ceb167a6243c8cad796a89c4d394239` and `main` at the P7 checkpoint `c511d7b0d4683e07717c962927c9f113854f227b`, are preserved as **transcribed LOCKED SOURCE RECORDS** thirty-seven and thirty-eight (transcripts, not byte-copy claims about attachments): the Owner's decision sent to the planning chat and relayed by the Owner as Appendix A of the finalization authorization, and that authorization itself, transcribed from its first line up to its APPENDIX heading. The authorization arrived as one pasted text block whose code-fence lines are not part of the transcript, and the Owner confirmed its full execution in-session before any edit. Neither record contains a credential.

| Archived transcript | Lines / bytes | SHA-256 |
| --- | --- | --- |
| [AICWDF_OWNER_MIGRATION_APPROVAL_2026-10-03.txt](sources/AICWDF_OWNER_MIGRATION_APPROVAL_2026-10-03.txt) | 73 / 3,067 | `76220AEFA9DA72464FCD947FD4793440677CFA62266E5DBE565BBDEE4243054A` |
| [AICWDF_MIGRATION_FINALIZATION_OWNER_AUTHORIZATION_2026-10-03.txt](sources/AICWDF_MIGRATION_FINALIZATION_OWNER_AUTHORIZATION_2026-10-03.txt) | 428 / 21,760 | `4E053025F6281D615799134FB0BB43C9CD0BB46F9BD1F4B5FFE153162F0B0C2E` |

They are DIR-040 and DIR-041 in [DECISION_LOG](DECISION_LOG.md#dir-040-dir-041-obs-014-and-tech-024--aicwdf-migration-approval-directives-finalization-baseline-and-corrections). The decisions of Appendix A, labelled F1–F6 in §2 of the authorization, are binding within their subjects under the standing hierarchy, and its approval of the migration is APPR-009; its instruction to the planning chat to prepare a prompt and not to execute is archived source text, not an instruction to a repository executor. The finalization authorization (DIR-041) authorizes the finalization commit, the fast-forward publication of `main` with its live-remote verification and the publication-confirmation commit only. All thirty-six earlier source records retain their bytes and classifications.

### P5 authentication amendment decisions and authorization

Two records received on 2026-10-03, with `main` at the published migration-confirmation commit `c26f31e9b053689b07a29877fe1016c6b8103e37` locally and on the live remote, are preserved as **transcribed LOCKED SOURCE RECORDS** thirty-nine and forty (transcripts, not byte-copy claims about attachments): the Owner's decisions on the P5 authentication blueprint with the approval of that blueprint, sent to the planning chat and relayed by the Owner as Appendix A of the amendment authorization, and that authorization itself, transcribed from its first line up to its APPENDIX heading. The authorization arrived as one pasted text block without an accompanying sentence, and the Owner confirmed its full execution in-session before any edit. Neither record contains a credential.

| Archived transcript | Lines / bytes | SHA-256 |
| --- | --- | --- |
| [P5_AUTH_OWNER_DECISIONS_AND_APPROVAL_2026-10-03.txt](sources/P5_AUTH_OWNER_DECISIONS_AND_APPROVAL_2026-10-03.txt) | 19 / 955 | `0D1754CE5A159AE68A1C65B980A4415E90CD1C47EE1C94597D47338F3CD450D6` |
| [P5_AUTH_AMENDMENT_OWNER_AUTHORIZATION_2026-10-03.txt](sources/P5_AUTH_AMENDMENT_OWNER_AUTHORIZATION_2026-10-03.txt) | 665 / 37,060 | `E5662EE0AF2A5F4887203D7C605C606C80303B539BAC79A4D1485BBF99D6670E` |

They are DIR-042 and DIR-043 in [DECISION_LOG](DECISION_LOG.md#dir-042-dir-043-obs-016-and-tech-025--p5-authentication-amendment-directives-baseline-and-amendment). The decisions of Appendix A, stated as K1–K4 in §2 of the authorization, are binding within their subjects under the standing hierarchy, together with D5 and D6 (DIR-037). Its closing sentence approves the P5 blueprint — the design of §7 of the authorization — and not the amended documents that express it, which stay PENDING OWNER APPROVAL. The authorization (DIR-043) authorizes that documentation-only amendment on the branch `amendment/p5-auth`, its gates and a normal push of the branch; it moves no `main` and approves nothing. All thirty-eight earlier source records retain their bytes and classifications.

### P5 authentication amendment review decisions and continuation authorization

Two records received on 2026-10-03, with the branch `amendment/p5-auth` local only at `6dbc49a60e6d3f015ba01ad3d5335b61d0ea7c98` and `main` at `c26f31e9b053689b07a29877fe1016c6b8103e37` locally and on the live remote, are preserved as **transcribed LOCKED SOURCE RECORDS** forty-one and forty-two (transcripts, not byte-copy claims about attachments): the Owner's decisions on the Owner-level findings of the amendment's first adversarial review, sent to the planning chat and relayed by the Owner as Appendix A of the continuation authorization, and that authorization itself, transcribed from its first line up to its APPENDIX heading. The authorization arrived as one pasted text block without an accompanying sentence; the expected state was verified read-only and the Owner confirmed its full execution in-session before any edit. Neither record contains a credential.

| Archived transcript | Lines / bytes | SHA-256 |
| --- | --- | --- |
| [P5_AUTH_REVIEW_OWNER_DECISIONS_2026-10-03.txt](sources/P5_AUTH_REVIEW_OWNER_DECISIONS_2026-10-03.txt) | 47 / 2,072 | `87B8E1E746CB2B7BD6CAA9359C7D575334BAEAF7BE33558E340B23A41E48A491` |
| [P5_AUTH_CONTINUATION_OWNER_AUTHORIZATION_2026-10-03.txt](sources/P5_AUTH_CONTINUATION_OWNER_AUTHORIZATION_2026-10-03.txt) | 354 / 17,840 | `820E121468477939B60ED95EE0820523FF1B3F672D4F36B0B1C82AC041A8E07C` |

They are DIR-044 and DIR-045 in [DECISION_LOG](DECISION_LOG.md#dir-044-dir-045-risk-006-risk-007-and-tech-025--p5-authentication-amendment-continuation-after-the-adversarial-review). Appendix A answers the Owner-level findings — "R-01: A", "R-02: A", "R-05: tautan RESET saat grant", "R-06: ya", "R-07: terima bila lolos daftar lokal" — and agrees with the planning recommendations that §2 of the authorization states; those decisions, and the residual risks (e) and (f) that §2 accepts, are binding within their subjects under the standing hierarchy. Where the continuation and DIR-043 conflict, the continuation prevails, as it states. It authorizes completing the same documentation-only amendment on the same branch — its corrections, a second adversarial review, the gate, the fourth commit and a normal push of the branch; it moves no `main` and approves nothing, and the amendment stays PENDING OWNER APPROVAL. All forty earlier source records retain their bytes and classifications.

### P5 authentication amendment resume instruction

One record received on 2026-10-04, in a new session, with the branch `amendment/p5-auth` local only at `6dbc49a60e6d3f015ba01ad3d5335b61d0ea7c98` and `main` at `c26f31e9b053689b07a29877fe1016c6b8103e37` locally and on the live remote, is preserved as the **transcribed LOCKED SOURCE RECORD** forty-three (a transcript, not a byte-copy claim about an attachment): the Owner's instruction to resume the continuation of the P5 authentication amendment after a model safeguard stopped the previous session, transcribed in full; it has no appendix. It arrived as one pasted text block without an accompanying sentence; the state was verified read-only, the uncommitted work was inventoried and reported, and the Owner confirmed its full execution in-session before any edit. It contains no credential.

| Archived transcript | Lines / bytes | SHA-256 |
| --- | --- | --- |
| [P5_AUTH_RESUME_OWNER_INSTRUCTION_2026-10-04.txt](sources/P5_AUTH_RESUME_OWNER_INSTRUCTION_2026-10-04.txt) | 196 / 9,040 | `FB7D46BE24AE597C497EFBB9A67111652D3448D1470E9E4936BC8A1B39C55661` |

It is DIR-046 in [DECISION_LOG](DECISION_LOG.md#dir-046-and-tech-025--p5-authentication-amendment-resume-in-a-new-session). It keeps DIR-043 and DIR-045 in force except where it changes them, and prevails where they conflict: it forbids access to any session transcript, conversation history or agent reasoning, replaces the expected-state check of DIR-045 and its step 2 — review round 1 set deterministically from the verified scratchpad summary — and orders the rest of DIR-045 carried out. It moves no `main` and approves nothing, and the amendment stays PENDING OWNER APPROVAL. All forty-two earlier source records retain their bytes and classifications.

### P5 authentication amendment round-2 decision and second continuation authorization

Two records received on 2026-10-04, with the branch `amendment/p5-auth` local only at `6dbc49a60e6d3f015ba01ad3d5335b61d0ea7c98` and `main` at `c26f31e9b053689b07a29877fe1016c6b8103e37` locally and on the live remote, are preserved as **transcribed LOCKED SOURCE RECORDS** forty-four and forty-five (transcripts, not byte-copy claims about attachments): the Owner's decision on the Owner-level finding R2-04 of the amendment's second adversarial review, relayed by the Owner as Appendix A of the second continuation authorization, and that authorization itself, transcribed from its first line up to its APPENDIX heading. The authorization arrived as one pasted text block without an accompanying sentence; the expected state was verified read-only and the Owner confirmed its full execution in-session before any edit. Neither record contains a credential.

| Archived transcript | Lines / bytes | SHA-256 |
| --- | --- | --- |
| [P5_AUTH_ROUND2_OWNER_DECISION_2026-10-04.txt](sources/P5_AUTH_ROUND2_OWNER_DECISION_2026-10-04.txt) | 16 / 945 | `B8A49FBC0D9964F8902BCC64125D1FFA09200C629ABC6E795D5F3B0BB0667B60` |
| [P5_AUTH_SECOND_CONTINUATION_OWNER_AUTHORIZATION_2026-10-04.txt](sources/P5_AUTH_SECOND_CONTINUATION_OWNER_AUTHORIZATION_2026-10-04.txt) | 317 / 15,686 | `C63F72E149CAF50AB1090AF27AEC7480034A5151D8E5CE97734FF0FC2FDB3219` |

They are DIR-047 and DIR-048 in [DECISION_LOG](DECISION_LOG.md#dir-047-dir-048-risk-006-and-tech-025--p5-authentication-amendment-second-continuation-after-review-round-2). Appendix A answers "R2-04: 1" and accepts the extension of the residual risk (e) as option 1, which §2 of the authorization states; that decision is binding within its subject under the standing hierarchy. DIR-043, DIR-045, DIR-046 and the second continuation are in force in that order of precedence, a later one prevailing on a conflict. It authorizes completing the same documentation-only amendment on the same branch — the round-2 dispositions, a third adversarial review, the gate, the fourth commit and a normal push of the branch; it moves no `main` and approves nothing, and the amendment stays PENDING OWNER APPROVAL. All forty-three earlier source records retain their bytes and classifications.

### P5 authentication amendment round-4 decisions and third continuation authorization

Two records received on 2026-10-04, with the branch `amendment/p5-auth` local only at `6dbc49a60e6d3f015ba01ad3d5335b61d0ea7c98` and `main` at `c26f31e9b053689b07a29877fe1016c6b8103e37` locally and on the live remote, are preserved as **transcribed LOCKED SOURCE RECORDS** forty-six and forty-seven (transcripts, not byte-copy claims about attachments): the Owner's decisions on the Owner-level finding R4-01 of the amendment's focused fourth adversarial review, relayed by the Owner as Appendix A of the third continuation authorization, and that authorization itself, transcribed from its first line up to its APPENDIX heading. The authorization arrived as one pasted text block without an accompanying sentence; the expected state was verified read-only and the Owner confirmed its full execution in-session before any edit. Neither record contains a credential.

| Archived transcript | Lines / bytes | SHA-256 |
| --- | --- | --- |
| [P5_AUTH_ROUND4_OWNER_DECISIONS_2026-10-04.txt](sources/P5_AUTH_ROUND4_OWNER_DECISIONS_2026-10-04.txt) | 61 / 3,480 | `347C9436C1128FAD6373D396961435313951B38FD9F43ED039885F7830789209` |
| [P5_AUTH_THIRD_CONTINUATION_OWNER_AUTHORIZATION_2026-10-04.txt](sources/P5_AUTH_THIRD_CONTINUATION_OWNER_AUTHORIZATION_2026-10-04.txt) | 370 / 18,017 | `3723862BC6CC41548189A0AE8DCDED7EFE75C82EF79FF30CF46B58BA52EBBD15` |

They are DIR-049 and DIR-050 in [DECISION_LOG](DECISION_LOG.md#dir-049-dir-050-risk-005-and-tech-025--p5-authentication-amendment-third-continuation-after-review-round-4). Appendix A answers "R4-01: 1" and "Owner jendela kebocoran tanpa tinjauan: tidak" and agrees to the Scenario A/B boundary and the convergence rule with clarifications, which §§2–3 of the authorization state; those decisions are binding within their subjects under the standing hierarchy, and they amend DIR-044 R-02's "existing paths only" by the OWNER-restoration command. DIR-043, DIR-045, DIR-046, DIR-048 and the third continuation are in force in that order of precedence. It authorizes completing the same documentation-only amendment on the same branch — the post-incident recovery design, the round-4 dispositions, a final focused review, the gate, the fourth commit and a normal push of the branch; it moves no `main` and approves nothing, and the amendment stays PENDING OWNER APPROVAL. All forty-five earlier source records retain their bytes and classifications.

### P5 authentication amendment approval decisions and finalization authorization

Two records received on 2026-10-04, with the branch `amendment/p5-auth` at `9b0b03be06dc44e208d9c2e8935e4949bbd241d2` and `main` at `c26f31e9b053689b07a29877fe1016c6b8103e37`, each locally and on the live remote, are preserved as **transcribed LOCKED SOURCE RECORDS** forty-eight and forty-nine (transcripts, not byte-copy claims about attachments): the Owner's decisions at the approval of the P5 authentication amendment, sent to the planning chat and relayed by the Owner as Appendix A of the finalization and publication authorization, and that authorization itself, transcribed from its first line up to its APPENDIX heading. The authorization arrived as one pasted text block without an accompanying sentence; the baseline was verified read-only and the Owner confirmed its full execution in-session before any edit. Neither record contains a credential.

| Archived transcript | Lines / bytes | SHA-256 |
| --- | --- | --- |
| [P5_AUTH_APPROVAL_OWNER_DECISIONS_2026-10-04.txt](sources/P5_AUTH_APPROVAL_OWNER_DECISIONS_2026-10-04.txt) | 64 / 3,643 | `57ACA64893C1C932EFDD18EDB02270863161301AD6FFC1B58EFBAAC4F32E87F6` |
| [P5_AUTH_FINALIZATION_OWNER_AUTHORIZATION_2026-10-04.txt](sources/P5_AUTH_FINALIZATION_OWNER_AUTHORIZATION_2026-10-04.txt) | 637 / 33,148 | `FEB3CD3306825F93D7ADEA36024998FAE40C83020B76322DC373B1F4DA4F010A` |

They are DIR-051 and DIR-052 in [DECISION_LOG](DECISION_LOG.md#dir-051-dir-052-obs-017-risk-008-risk-009-and-tech-026--p5-authentication-amendment-approval-finalization-baseline-and-accepted-risks). Appendix A answers "GAP-047: accept", "GAP-048: accept" and "P5 amendment at 9b0b03b: approve", which §2 of the authorization states as F1–F6; those decisions are binding within their subjects under the standing hierarchy, and its approval of the amendment is APPR-010. Its instructions to the planning chat to prepare the authorization are archived source text, not instructions to a repository executor. The authorization (DIR-052) authorizes the approval commit, the fast-forward publication of `main` with its live-remote verification and the publication-record commit only. All forty-seven earlier source records retain their bytes and classifications.

### P7 UX re-baseline decisions and Stage 0–1 authorization

Two records received on 2026-10-04, with `main` and `amendment/p5-auth` at the published P5 publication-record commit `a526daf47444d12b4ae5c51e1ae14c9dfe3f4978` and `migration/aicwdf` at `c26f31e9b053689b07a29877fe1016c6b8103e37`, each locally and on the live remote, are preserved as **transcribed LOCKED SOURCE RECORDS** fifty and fifty-one (transcripts, not byte-copy claims about attachments), so that the repository holds 51 source records: the Owner's decisions on the Planning blueprint for the P7 UX re-baseline, sent to the planning chat and relayed by the Owner as Appendix A of the Stage 0–1 authorization, and that authorization itself, transcribed from its first line up to its APPENDIX heading. The authorization arrived as one pasted text block without an accompanying sentence; the baseline was verified read-only and the Owner confirmed its full execution in-session before any edit. Neither record contains a credential.

| Archived transcript | Lines / bytes | SHA-256 |
| --- | --- | --- |
| [P7_REBASELINE_OWNER_DECISIONS_2026-10-04.txt](sources/P7_REBASELINE_OWNER_DECISIONS_2026-10-04.txt) | 97 / 3,985 | `9BEA827E251840F749C7AC8A4235CA42F249C8B94980CF7CFA2727F4FD92D86B` |
| [P7_REBASELINE_STAGE01_OWNER_AUTHORIZATION_2026-10-04.txt](sources/P7_REBASELINE_STAGE01_OWNER_AUTHORIZATION_2026-10-04.txt) | 1,119 / 50,964 | `003117E2F3E8AAA8A3F74CC95C7EEA85A4498C0C0F72BD192B2F38AD3F2A6734` |

They are DIR-053 and DIR-054 in [DECISION_LOG](DECISION_LOG.md#dir-053-dir-054-obs-019-and-tech-027--p7-ux-re-baseline-decisions-stage-01-authorization-baseline-and-direction). Appendix A answers "K1: A", "K2: A", "K3: benar." and "K4: Tidak ada tambahan saat ini", approves the direction of the Planning blueprint in principle and adds two clarifications, which §2 of the authorization states as K1–K4, C-1 (language-preference storage) and C-2 (review convergence); those decisions are binding within their subjects under the standing hierarchy. The blueprint that Appendix A answers is not archived: its operative content is restated in the authorization, which governs. Under K4 the hypotheses H-1 to H-5 that §2 of the authorization states are planner input — the lowest authority level — derived by Planning from repository documents, not observations of the Owner, and Stage 1 verifies them. Appendix A's closing instructions to the planning chat to prepare the authorization are archived source text, not instructions to a repository executor. The authorization (DIR-054) authorizes Stage 0 and Stage 1 of the re-baseline only, on the branch `rebaseline/p7-ux`, ending at Checkpoint C1; it records Stages 2–5 as the approved plan without authorizing them, moves no `main` and approves nothing. All forty-nine earlier source records retain their bytes and classifications.

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
| Identity, V1 target and phase model | `docs/00-governance/PROJECT_CHARTER.md` | Exists / P0 | Opening, 2, 46–47, 55–56, 58 |
| Provenance, ownership, lifecycle, conflicts | This document | Exists / P0 | 1, 48–50 |
| Agent roles, phase authorization, Task model and contract, execution flow and handoff process | `docs/00-governance/AGENT_OPERATING_MODEL.md` | Exists / P0 | Opening, 48, 51, 53–54 |
| Decision authority and change/ADR process | `docs/00-governance/CHANGE_CONTROL.md` | Exists / P0 | 47, 50, 52; mandate: Decision Authority |
| Gaps, risk treatment and decision requests | `docs/00-governance/GAP_REGISTER.md` | Exists / P0 extension | Mandate: Proactive Gap Register |
| Decision/approval evidence | `docs/00-governance/DECISION_LOG.md` | Exists / P0 | 1, 50, 52 |
| Cross-cutting engineering guardrails | `docs/00-governance/ENGINEERING_PRINCIPLES.md` | Exists / P0 | 18–44, 58; detailed designs reserved below |
| P0 gate evidence | `docs/00-governance/evidence/P0_QUALITY_GATE.md` | Exists / P0 | 56–57 |
| AICWDF v4.3 section map, project exceptions, terminology and planned-path mapping | `docs/00-governance/AICWDF_ADOPTION.md` | Exists, APPROVED (APPR-009; the P5 authentication amendment approved under APPR-010) / P0 (AICWDF adoption) | DIR-034–039, DIR-043; AICWDF §1, §41 |
| Binding decisions and their status, without rule text | `docs/00-governance/DECISION_INDEX.md` | Exists, REVIEW / P0 (AICWDF adoption) | DIR-039 |
| Capabilities and tools, MCP activation, Git line endings and staging, public-repository rule | `docs/00-governance/TOOLCHAIN.md` | Exists, APPROVED (APPR-009) / P0 (AICWDF adoption) | 40–43; AICWDF §5–§7, §6A |
| Technology and service cost procedure; recurring-cost inventory and exception registry | `docs/00-governance/COST_POLICY.md` | Exists, APPROVED (APPR-009; the P5 authentication amendment approved under APPR-010) / P0 (AICWDF adoption) | 47; AICWDF §4C; DIR-043 |
| Production database zero-touch for agents | `docs/00-governance/PRODUCTION_DATA_SAFETY.md` | Exists, APPROVED (APPR-009) / P0 (AICWDF adoption) | 37; AICWDF §29–§31 |
| Old → new paths of the structural migration and their proofs | `docs/00-governance/MIGRATION_MAP.md` | Exists, REVIEW / P0 (AICWDF adoption) | DIR-039 |
| Phase progress P0–P11 until the Task plan exists | `docs/PHASE_STATUS.md` | Exists, REVIEW / P0 (AICWDF adoption) | 51, 55–57; DIR-037; AICWDF §10 |
| Current operational handoff and safe next action | `docs/handoff/CURRENT_HANDOFF.md` | Exists, REVIEW / P0 (AICWDF adoption) | 51; AICWDF §36 |
| Historical handoff snapshots of 2026-10-02 | `docs/handoff/archive/CURRENT_STATE_2026-10-02.md`, `NEXT_ACTION_2026-10-02.md` | Archive, REVIEW / P0 | 51, 55–57 |
| Stable minimum context for execution agents | `docs/11-tasks/EXECUTION_CONTEXT.md` | Exists, APPROVED (APPR-009; the P5 authentication amendment approved under APPR-010) / P11 | AICWDF §34A; DIR-043 |
| Product explanation, users and goals | `docs/01-product/PRODUCT_OVERVIEW.md` | Exists, APPROVED (APPR-002; DIR-024 amendment approved under APPR-004; the D1 correction approved under APPR-009) / P1 | 2–17, 46; P1 identity and user/product direction |
| V1 priorities, exclusions, product cost/dependencies and P7 obligations | `docs/01-product/V1_SCOPE.md` | Exists, APPROVED (APPR-002; DIR-024 amendment approved under APPR-004; the D1, D4 and D7 amendment approved under APPR-009; the P5 authentication amendment approved under APPR-010) / P1 | 2–17, 45–47; P1 constraints and C1–C3; DIR-043 |
| Product acceptance and operational success | `docs/01-product/ACCEPTANCE_CRITERIA.md` | Exists, APPROVED (APPR-002; the D7 amendment approved under APPR-009; the P5 authentication amendment approved under APPR-010) / P1 | 41–42, 46; P1 targets and user/product outcomes; DIR-043 |
| P1 adversarial review and quality evidence | `docs/01-product/evidence/P1_QUALITY_GATE.md` | Exists, REVIEW / P1 | P1 objective/review/gate/report |
| Reference comparison and requirement identifiers | `docs/01-product/REFERENCE_COVERAGE.md` | Exists, REVIEW / P1 | DIR-008; PDF §§1–14; complete flow image |
| Entities, terminology, relationships, scope/sensitivity classes | `docs/02-domain/DOMAIN_MODEL.md` | Exists, APPROVED (APPR-003; DIR-024/026 and TECH-016 amendments approved under APPR-004; the Owner-decided DIR-027 amendment approved under APPR-005) / P2 | 2–17; DIR-016–019, DIR-024/026/027 |
| Business invariants, domain lifecycles/state rules and calculation semantics | `docs/02-domain/BUSINESS_RULES.md` | Exists, APPROVED (APPR-003; DIR-024/026 and TECH-016 amendments approved under APPR-004; the Owner-decided DIR-027 amendment approved under APPR-005) / P2 | 3–17, 26–28, 36, 45; DIR-018/019/020/024/026/027 |
| P2 adversarial review, traceability verification and gate evidence | `docs/02-domain/evidence/P2_QUALITY_GATE.md` | Exists, REVIEW / P2 | DIR-016 §§18–22 |
| User/process workflows, responsibility boundaries and transition orchestration over P2 lifecycles | `docs/03-workflows/WORKFLOWS/README.md` | Exists, APPROVED (APPR-004; the Owner-decided DIR-027 amendment approved under APPR-005; the TECH-021 amendment approved under APPR-007) / P3 | 11–16, 42; DIR-023–027, DIR-032 |
| P3 adversarial review, aggregate traceability verification and gate evidence | `docs/03-workflows/evidence/P3_QUALITY_GATE.md` | Exists, REVIEW / P3 | DIR-023–026 |
| Database design and constraints | `docs/04-architecture/DATABASE/README.md` | Exists, APPROVED (APPR-005; the TECH-020 amendment approved under APPR-006; the TECH-021 amendment approved under APPR-007; the TECH-022 amendment approved under APPR-008; the P5 authentication amendment approved under APPR-010) / P4 | 5, 13–15, 26–32, 38, 45; DIR-027–029, DIR-032, DIR-033, DIR-043 |
| Application structure and stack | `docs/04-architecture/ARCHITECTURE.md` | Exists, APPROVED (APPR-005; the TECH-021 amendment approved under APPR-007; the P5 authentication amendment approved under APPR-010) / P4 | 18–19, 24, 28–36; DIR-027–029, DIR-032, DIR-043 |
| P4 adversarial review, traceability verification and gate evidence | `docs/04-architecture/evidence/P4_QUALITY_GATE.md` | Exists, REVIEW / P4 | DIR-027 §§39–40; DIR-028/029 |
| Permissions and company-scope rules | `docs/05-security/PERMISSIONS_MATRIX.md` | Exists, APPROVED (APPR-006; the TECH-021 amendment approved under APPR-007; the P5 authentication amendment approved under APPR-010) / P5 | 17, 24, 41; DIR-031, DIR-032, DIR-043 |
| Security control design | `docs/05-security/SECURITY.md` | Exists, APPROVED (APPR-006; the TECH-022 amendment approved under APPR-008; the P5 authentication amendment of D5, D6 and K1–K4 approved under APPR-010) / P5 | 24–25, 36–38; DIR-031, DIR-033, DIR-042, DIR-043 |
| P5 adversarial review, traceability verification and gate evidence | `docs/05-security/evidence/P5_QUALITY_GATE.md` | Exists, REVIEW / P5 | DIR-031 |
| P5 authentication amendment: external verification, adversarial review, gate and zero-context evidence | `docs/05-security/evidence/P5_AUTH_AMENDMENT_GATE.md` | Exists, REVIEW / P5 (authentication amendment) | DIR-042–DIR-052 |
| Concurrency and idempotency mechanisms | `docs/06-api-performance/CONCURRENCY_IDEMPOTENCY/README.md` | Exists, APPROVED (APPR-007; the TECH-022 amendment approved under APPR-008; the P5 authentication amendment approved under APPR-010) / P6 | 14, 27–28, 31–32, 35; DIR-032, DIR-033, DIR-043 |
| Query/runtime performance design | `docs/06-api-performance/PERFORMANCE.md` | Exists, APPROVED (APPR-007; the TECH-022 amendment approved under APPR-008; the P5 authentication amendment approved under APPR-010) / P6 | 29–31, 35; DIR-032, DIR-033, DIR-043 |
| Routes and integration boundaries | `docs/06-api-performance/API_AND_INTEGRATIONS.md` | Exists, APPROVED (APPR-007; the P5 authentication amendment approved under APPR-010) / P6 | 9, 33–35; DIR-032, DIR-043 |
| P6 adversarial review, obligation closure, traceability verification and gate evidence | `docs/06-api-performance/evidence/P6_QUALITY_GATE.md` | Exists, REVIEW / P6 | DIR-032 |
| Navigation, admin interaction, visual patterns | `docs/07-ux-design/INFORMATION_ARCHITECTURE.md`, `ADMIN_FLOW/README.md`, `DESIGN_SYSTEM.md` | Exists, APPROVED (APPR-008); INFORMATION_ARCHITECTURE and DESIGN_SYSTEM under replacement by the P7 re-baseline (D3) / P7 | 11, 20–23; DIR-033; navigation / interaction / visual rules respectively |
| P7 DesainPakai readiness and exploration evidence, GAP-034 settlement, obligation closure and gate evidence | `docs/07-ux-design/evidence/P7_QUALITY_GATE.md` | Exists, REVIEW / P7 | DIR-033 |
| P7 UX re-baseline evidence: baseline, DesainPakeAI readiness, the stage plan with its checkpoints, and the gates | `docs/07-ux-design/evidence/P7_REBASELINE_GATE.md` | Exists, REVIEW / P7 re-baseline | DIR-053, DIR-054 |
| Route and interaction contracts | `docs/03-workflows/ROUTE_CONTRACTS.md`, `INTERACTION_CONTRACTS.md` | Planned / P7 re-baseline, as a P3 addition | AICWDF §14.2–§14.3; GAP-035 |
| Navigation contracts, localization and design references | `docs/07-ux-design/NAVIGATION_CONTRACTS.md`, `LOCALIZATION.md`, `DESIGN_REFERENCES.md` | Planned / P7 re-baseline | AICWDF §18.5, §18.11, §18.12; DIR-035 D3, DIR-037 D7; GAP-037 |
| Testing, security matrix, performance targets, completion criteria | `docs/08-testing/TEST_STRATEGY.md`, `SECURITY_TEST_MATRIX.md`, `PERFORMANCE_TARGETS.md`, `DEFINITION_OF_DONE.md` | Planned / P8; folder reserved by `docs/08-testing/README.md` | 40–44; AICWDF §19, §26–§28; strategy / security cases / measurable targets / completion respectively |
| Recovery procedures and conditional local targets | `docs/09-operations/BACKUP_RECOVERY.md` | Planned / P9; folder reserved by `docs/09-operations/README.md` | 37–39 as superseded by DIR-009; BK-01–12 and shared-disk safety |
| Deployment topology and operational controls | `docs/09-operations/INFRASTRUCTURE.md` | Planned / P9 | 19, 37–39; AICWDF §20 |
| Release, migration, cutover, rollback and UAT | `docs/10-release/RELEASE_PLAN.md` | Planned / P10; folder reserved by `docs/10-release/README.md` | 43, 45–47, 55; AICWDF §21, §39 |
| Task plan: baseline and current totals, status counts, master checklist and the dependency graph that replaces a separate implementation order | `docs/11-tasks/TASK_PLAN.md` | Planned / P11; folder reserved by `docs/11-tasks/README.md` | 44, 53–54; AICWDF §22, §35 |
| Bounded Task specifications | `docs/11-tasks/TASK-XXX.md` | Planned / P11 | 44, 53–54; AICWDF §23 |
| Requirement-to-rule-to-Task-to-test traceability | `docs/11-tasks/FEATURE_COVERAGE_MATRIX.md` | Planned / P11; all rows required before freeze | DIR-008; seed identifiers and phase handoff in REFERENCE_COVERAGE |
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
3. Apply the seven-level hierarchy in the provenance section. Valid delegated technical changes operate within their recorded authority; a planner proposal cannot override explicit owner intent. Classify technical contradictions under the authority levels instead of automatically escalating all corrections.
4. Record competing statements, affected tasks, and a proposed resolution in the decision log or a change request. Stop only the work that depends on that unresolved contradiction. Continue safe independent work within the authorized phase.
5. Resolve Level 1 conflicts directly with evidence. For Level 2 or Level 3 conflicts, flag OWNER DECISION REQUIRED and wait only on dependent choices. Update the owning document, relevant ADR/links, and handoff. Do not silently change business meaning or permissions.

An accepted ADR explains why a decision was made. The approved concern document states the current rule and has precedence under the source hierarchy (DIR-008, as amended by DIR-035 D2). Update both when a decision changes and record any discovered contradiction. README, adapters, examples, tests, and generated files do not override canonical specifications. Report code/spec drift rather than weakening a valid test.

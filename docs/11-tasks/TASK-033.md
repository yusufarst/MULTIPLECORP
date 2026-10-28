# TASK-033 — Quotations

Status: APPROVED | Updated: 2026-10-20 | Owner: Planning

Approval: [APPR-016](../00-governance/DECISION_LOG.md#appr-016--p11-task-plan-tasks-and-feature-coverage-matrix-approved), explicit Owner approval on 2026-10-20 (DIR-085 OF-07) of this document as committed in the first execution session's approval commit; the approved file hash and the verified conditions are recorded there. Approval changes lifecycle only, not implementation authorization.

Written by WF-PC5-W's writer of bundle W1 of run PC-5 on 2026-10-19 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99; the Owner approved P11 with the [Task plan](TASK_PLAN.md) at OG-4 (DIR-085 OF-07). **No Task starts before the freeze record, from which the execution authorization is in force (DIR-085 OF-09).** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** READY

**RISK CLASS:** STANDARD

**EXECUTOR:** AGENT

**OBJECTIVE:**

Build the quotation cycle on PG-014 *Penawaran* — the draft from the project's lines in the document composer, "Terbitkan Penawaran" issuing DOC-01 and making the revision *Terkirim*, "Buat Revisi", and "Catat Jawaban Klien" recording the client's approval or rejection — with PG-037 *Daftar Penawaran* across projects and the company-prepared HPS (DOC-11) as the project's selectable document (ROUTE-014, ROUTE-037, ROUTE-154–ROUTE-157; WF-QUO-01; J-QUO-01).

**WHY THIS EXISTS:**

CAP-04 and AC-04 require quotation drafts, sent revisions with their validity, revisions, approval and history to stay distinguishable, rejected or expired proposals never silently approved (BR-QUO-01; REF-018; IMG-02); a quotation alone never reserves stock or moves money (BR-QUO-03), and its issued revision keeps the client data it was issued with (BR-SN-01; architecture challenge 1b). The client's approval of the latest sent revision is the project's commercial confirmation (WF-PRJ-02 path A; BR-PRJ-06), which TASK-034 builds on.

**DEPENDS ON:**

- TASK-029 — Document engine — drafts, issue, revision, void, renditions and the composer
- TASK-032 — Projects — list, form, journey page and amendment

**BLOCKS:**

- TASK-034 — Commercial confirmation and fulfillment planning

**PARALLEL SAFE:** NO — the quotation revisions live under the project's row (LK-01) and their approval is the confirmation TASK-034 extends

**IN SCOPE:**

- PG-014 with NAV-014 and its view ROUTE-014 (`quotation.manage` with `finance.view`; for the client's answer alone `projects.confirm` with `projects.view`; SCR-13): the quotation in TASK-029's composer — the form on the left and the live preview of the *Penawaran* on the right from 1,025 px, never a tab (D-DS-23; INT-012) —, its revision chain with each revision's state — *Draf*, *Terkirim*, *Disetujui*, *Ditolak*, *Kedaluwarsa* derived from the validity (L-25) — and the prior versions
- ROUTE-154 with INT-154: the draft saved from the project's lines, editable while DRAFT, two editors of one draft meeting CONFLICT with "Terapkan Ulang pada Versi Terbaru" (ST-01; UXS-11); keyboard entry of many lines in the line editor (PT-10; UXS-41)
- ROUTE-155 with INT-155, "Terbitkan Penawaran": after its confirmation (IP-08), DOC-01 issued through SF-ISSUE — the number, the immutable snapshot and the validity date —, the revision becoming *Terkirim* and its lines frozen, the rendition following (SF-RENDER); REJECTED with MSG-17 when the period's numbering may create no counter (NM-03); DOC-01's subject, precondition and payload supplied through `DocumentSubjectProvider` (TASK-029)
- ROUTE-156 with INT-156, "Buat Revisi": a new DRAFT revision from any non-draft revision, the chain preserved; partial acceptance through a revision to the accepted subset (L-20); a revision after approval needing re-approval (BR-QUO-02)
- ROUTE-157 with INT-157, "Catat Jawaban Klien" after its confirmation (IP-08): *Ditolak* recorded by `quotation.manage`; *Disetujui* on the latest sent revision within its validity, with evidence where available, by `projects.confirm` — the approval committing as the commercial confirmation AX-13, whose pins, snapshots and reservation adjustments are TASK-034's (WF-PRJ-02; J-PRJ-02) —; a quick action on the phone (J-QUO-01)
- PG-037 with NAV-037 and its view ROUTE-037 (`projects.view`; values `finance.view`; SCR-12): quotation revisions across projects by branch, listed with their state and without values for a viewer without `finance.view`
- DOC-11, the company-prepared HPS, as a selectable document of the project on PG-036 with its truthful company issuer and its draft marking, a client's or third party's HPS staying an upload (WF-DOC-01; BR-DOC-04; REF-033; IMG-06); its subject and payload supplied through `DocumentSubjectProvider`

**OUT OF SCOPE:**

- the commercial confirmation itself — AX-13's pins, the SIPLAH lock and the reservation adjustments —, the no-quotation confirmation and fulfilment planning (TASK-034)
- the composer, the template mechanism and the document issue engine (TASK-029); numbering (TASK-021)
- an approval recorded in error, superseded under CM-27 (`decisions.supersede`; TASK-035's ROUTE-169)

**REFERENCES:**

- [WORKFLOWS §5.2](../03-workflows/WORKFLOWS/s05-01-02-access-project.md#wf-quo-01--quotation-cycle-desk) WF-QUO-01; [WF-PRJ-02](../03-workflows/WORKFLOWS/s05-01-02-access-project.md#wf-prj-02--commercial-confirmation-desk); [§5.5](../03-workflows/WORKFLOWS/s05-04-05-fulfillment-documents.md#wf-doc-01--generated-document-lifecycle-desk) DOC-01 and DOC-11 in WF-DOC-01's table; [§12](../03-workflows/WORKFLOWS/s10-14-signals-decisions-traceability.md#12-planner-determined-level-1-decisions) L-20, L-25
- [BUSINESS_RULES SM:Quotation](../02-domain/BUSINESS_RULES.md#smquotation) BR-QUO-01–BR-QUO-03; [BR-DOC-04](../02-domain/BUSINESS_RULES.md#smdocument-all-doc-0114-invoice-is-a-specialization); [BR-SN-01](../02-domain/BUSINESS_RULES.md#historical-immutability-and-snapshots); [§2](../02-domain/BUSINESS_RULES.md#2-correction-taxonomy) BR-CR-06
- [V1_SCOPE](../01-product/V1_SCOPE.md#selectable-document-catalog) DOC-01, DOC-11; [CAP-04](../01-product/V1_SCOPE.md#must-ship); [ACCEPTANCE_CRITERIA](../01-product/ACCEPTANCE_CRITERIA.md#product-acceptance-contracts) AC-04, AC-20; [OS-01](../01-product/ACCEPTANCE_CRITERIA.md#operational-success-and-go-live-evidence); [REFERENCE_COVERAGE](../01-product/REFERENCE_COVERAGE.md#pdf-capability-inventory) REF-017, REF-018, REF-033; [IMG-02, IMG-06](../01-product/REFERENCE_COVERAGE.md#image-and-non-tabular-pdf-inventory)
- [DATABASE §4.5](../04-architecture/DATABASE/s04-05-prj.md#45-prj--projects--commercial) `quotation_revisions`, `quotation_revision_lines`; [§9](../04-architecture/DATABASE/s07-10-representations.md#9-project-and-commercial-representation)
- [PERMISSIONS_MATRIX §4](../05-security/PERMISSIONS_MATRIX.md#4-capability-catalogue) `quotation.manage`, `projects.confirm`; [§7.2](../05-security/PERMISSIONS_MATRIX.md#72-table-classification) rows 33, 34; [§7.3](../05-security/PERMISSIONS_MATRIX.md#73-special-projections) PJ-15
- [ROUTE_CONTRACTS](../03-workflows/ROUTE_CONTRACTS.md#projects--proyek-formulir-proyek-penawaran-the-journey-and-its-completion-prj-j-prj-01j-prj-05) ROUTE-154–ROUTE-157; [§2](../03-workflows/ROUTE_CONTRACTS.md#2-page-views--route-001route-065) ROUTE-014, ROUTE-037
- [INTERACTION_CONTRACTS](../03-workflows/INTERACTION_CONTRACTS.md#projects--proyek-formulir-proyek-penawaran-the-journey-and-its-completion-prj-j-prj-01j-prj-05) INT-154–INT-157; [§2](../03-workflows/INTERACTION_CONTRACTS.md#2-shared-interactions--int-001int-020) INT-012
- [NAVIGATION_CONTRACTS §2.2](../07-ux-design/NAVIGATION_CONTRACTS.md#22-projects-and-quotations) NAV-014, NAV-037; [INFORMATION_ARCHITECTURE §8.2](../07-ux-design/INFORMATION_ARCHITECTURE.md#82-projects-and-quotations) PG-014, PG-037
- [ADMIN_FLOW §10.2](../07-ux-design/ADMIN_FLOW/s10-a-journeys.md#102-project-and-commercial) J-QUO-01, J-PRJ-02; [§15](../07-ux-design/ADMIN_FLOW/s14-15-messages-scenarios.md#command-identity-and-outcomes) UXS-11; [UXS-41](../07-ux-design/ADMIN_FLOW/s14-15-messages-scenarios.md#design)
- [DESIGN_SYSTEM §4](../07-ux-design/DESIGN_SYSTEM.md#4-design-decisions) D-DS-23; [§14.3](../07-ux-design/DESIGN_SYSTEM.md#143-document-preview)
- [CONCURRENCY_IDEMPOTENCY §7.3](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s07-statement-sequences.md#73-command-families-outside-ax-0137) NX-03; [§7.1](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s07-statement-sequences.md#71-lock-sets-of-ax-01ax-37) AX-13, AX-15; [§11](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s10-13-identity-failure-retry.md#11-separate-duplicates) BD-05, BD-06; [§18](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s18-scenarios.md#18-mandatory-concurrency-and-failure-scenarios) M-38
- Architecture challenge — the sequence 1b of the [P11 gate record](evidence/P11_QUALITY_GATE.md#whole-system-architecture-challenge-sp-98), their assertions carried into this Task's acceptance criteria and tests
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (43): O-01; CAP-04; DOC-01; DOC-11; AC-04; AC-20; OS-01; REF-017–REF-018; REF-033; IMG-01–IMG-02; IMG-04; IMG-06; BR-QUO-01–BR-QUO-03; WF-QUO-01; L-20; L-25; ROUTE-014; ROUTE-037; ROUTE-154–ROUTE-157; INT-154–INT-157; NX-03; M-19; M-38; PG-014; PG-037; SCR-12–SCR-13; J-QUO-01; J-PRJ-02; UXS-11; UXS-41; NAV-014; NAV-037

**EXPECTED MODULES / FILE AREAS:**

- the PRJ module's SaveQuotationDraft, IssueQuotation, ReviseQuotation, ApproveQuotation and RecordQuotationRejection actions, PRJ's `DocumentSubjectProvider` for DOC-01 and DOC-11, the quotation query classes (RC-02); `resources/js/Pages/PRJ` for PG-014 and PG-037 on TASK-029's composer; `tests/Feature/PRJ`

**BUSINESS RULES:**

- BR-QUO-01: approval is a decision of an authorized individual; a rejected or expired quotation is never silently approved; validity dates enforce EXPIRED (L-25)
- BR-QUO-02 and L-20: a revision after approval needs re-approval, and confirmed quantities that change adjust the reservations (through TASK-034); the latest APPROVED revision governs
- BR-QUO-03: a quotation alone never changes stock or money; BR-CR-06: a quotation is edited only as a draft, revised, rejected or expired — never voided, so no void control exists
- BR-SN-01 and BR-DOC-01: an issued revision's content, number and client data stay as issued (architecture challenge 1b); BR-DOC-04: a company HPS carries its truthful issuer and never stands for a client's or third party's

**ARCHITECTURE RULES:**

- a SENT revision's lines are frozen by trigger and EXPIRED is derived, never stored (DATABASE §4.5, §9); DOC-01 renders the revision through `quotation_revision_id` (DATABASE §10)
- the issue goes through TASK-029's engine and template (AX-15); the approval through AX-13 (TASK-034) — this Task adds no second issue or confirmation path

**SECURITY / PERMISSION REQUIREMENTS:**

- ROUTE-154–ROUTE-156 and the rejection `quotation.manage`, whose prerequisites are `projects.view` and `finance.view`; the approval `projects.confirm` (prerequisite `projects.view`), viewing values only with `finance.view` (RG-06); DOC-01's issue `quotation.manage` (ROUTE-222; PJ-15)
- with `projects.view` alone a quotation is listed with its state and without values (J-QUO-01)

**COMPANY SCOPE AND FIELD PROJECTION:**

- a quotation belongs to its project's company; the command's company is the project's (CS-03)
- rows 33–34: `quotation_revisions` PV(c); `quotation_revision_lines` PV(c) with prices and values FV(c); DOC-01 and DOC-11 payloads and renditions in the finance family, `finance.view` within the company (PJ-15); PG-037 one bounded branch per company, no total across companies (CS-07; CS-08)

**CONCURRENCY / IDEMPOTENCY:**

- ROUTE-154 and ROUTE-156 are NX-03 with `command_id` (CI-01) under the project's row (LK-01), the revision's `lock_version` covering its lines (ST-01; M-38); ROUTE-155 issues through AX-15 — LK-30 before LK-31, a double issue CONFLICT with the issued number (BD-06; M-19), the revision's DRAFT → SENT a conditional transition (ST-02)
- ROUTE-157's approval is AX-13 (LK-00, LK-01, LK-03, LK-13, LK-18, LK-19, LK-20, LK-29; FU-09) with one root confirmation per approved revision, a duplicate or concurrent approval CONFLICT (BD-05); a prefilled date that went stale is CONFLICT (ST-08); replay re-authorized (CI-05)

**AUDIT:**

- every save, issue, revision, approval and rejection is a business audit event in its transaction with the individual actor (BR-XC-01; LG-01); the decision recorded with `decided_by`, `decided_at` and its reason where given on the revision (DATABASE §4.5); the issue's version audited by TASK-029's engine

**DATABASE IMPACT ASSESSMENT:**

- Schema change: NONE — uses the tables of TASK-005–TASK-009; a missing column is an executor discovery (AGENT_OPERATING_MODEL)
- Migration required: NO
- Existing data transformation: NONE
- Direct production DB access: FORBIDDEN
- Data-loss risk: NONE KNOWN

**MODULARITY / EXTENSIBILITY:**

- Module boundary affected: PRJ, tier 2, implementing DOC's `DocumentSubjectProvider` for DOC-01 and DOC-11 (ARCHITECTURE §3)
- Stable core kept provider-neutral: YES
- External/vendor-specific code isolated: YES — framework facilities or a small interface (ARCHITECTURE §2)
- Replaceable adapter/contract required: NO — the provider is TASK-029's contract, implemented here
- Provider lock-in risk: NONE beyond the Owner's baseline stack
- Future extension impact: LOW
- Speculative abstraction avoided: YES — no separate Order entity (BR-PRJ-06), no quotation template editor

**AUTH IMPACT:**

- Authentication change: NONE
- Authentication implementation: LARAVEL_NATIVE — decided once ([EXECUTION_CONTEXT](EXECUTION_CONTEXT.md)), reused, never redesigned
- Auth profile: N/A — this Task does not touch authentication
- Google sign-in: N/A — not touched
- Google registration behavior: N/A — not touched
- Google credentials owner action required: NO
- Authorization change: NONE
- Provider/library-specific leakage into domain: NO

**COST IMPACT:**

- New recurring cost: NONE
- Paid third-party service: NO
- Free/open-source alternative: N/A — nothing paid is considered
- Estimated additional monthly production cost: 0
- Owner approval required: NO

**MCP SESSION PROFILE:**

- Required MCP servers: none — [TOOLCHAIN](../00-governance/TOOLCHAIN.md#mcp-servers) records none installed
- MCP servers that should remain OFF: every server not required
- Native CLI/package preferred over MCP: YES — the framework's and package managers' own commands
- Post-Task deactivation/cleanup: none

**TOOL REQUIREMENTS:**

- Context7: OPTIONAL — the framework's database triggers for the frozen lines, at the installed version
- Graphify: OPTIONAL — once TASK-034's confirmation exists
- Browser E2E: REQUIRED — J-QUO-01 and the approval of J-PRJ-02
- Other: a 30-line quotation fixture for the composer's widths (INT-012)
- Missing safe tool behavior: AUTO-INSTALL project-locally inside this Task (AICWDF §6.3) and record it in TOOLCHAIN; a paid tier or a privileged installation STOPS for the Owner (COST_POLICY)

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: refreshed before the change when Graphify is available ([TOOLCHAIN](../00-governance/TOOLCHAIN.md)); otherwise a targeted search of PRJ and DOC's issue engine
- Pre-change questions: which reads use the latest APPROVED revision, and where the approval hands over to AX-13
- Expected affected areas: PRJ's quotation actions; DOC's provider for DOC-01 and DOC-11

**CONTEXT7:**

- Topics to verify: BEFORE UPDATE triggers freezing a SENT revision's lines; the WIB day boundary for validity dates
- Version-sensitive assumptions: none from memory

**UI / UX REQUIREMENTS:**

- Follow global design rules: YES
- Indonesian default: YES
- English support: YES — for the page; the paper stays Indonesian (LOCALIZATION §3.4)
- Plain-language Indonesian: YES — MSG-17, MSG-39 and the *Kedaluwarsa* path as J-QUO-01 states it
- Language switch impact: keeps unsent input; the paper unchanged
- Mobile-first: YES — PG-014 `[desk]`, on the phone read and "Catat Jawaban Klien"; PG-037 `[desk]` (NAV-014, NAV-037)
- Responsive: YES
- Adaptive: YES — the composer's desktop split, the tablet's "Rincian | Pratinjau" and the phone's quick action (D-DS-23; J-QUO-01)
- Visual breathing room: REQUIRED
- Comfortable density: REQUIRED
- shadcn/ui reuse: TASK-029's composer, the line editor (PT-10), the confirmation (PT-26), status badges (PT-13), the data table for PG-037 (PT-05)
- Design reference: PG-014 and PG-037 as INFORMATION_ARCHITECTURE §8.2 states them; the composer as D-DS-23; the Golden Frontend Reference for presentation only (DIR-061 SD-02)
- Primary action: on a draft "Terbitkan Penawaran"; on a sent revision "Catat Jawaban Klien"
- Secondary actions: "Simpan Draf", "Buat Revisi"
- Destructive actions: none — a quotation is never voided (BR-CR-06); a rejection is a decision
- Redundancy constraints: one issue control per draft; no void control; nothing implying a reservation or a payment (J-QUO-01)
- Loading state: PT-19; the rendition's PENDING state with MSG-05 after the issue (IP-12)
- Empty state: a project without a quotation shows the draft's creation from its lines
- Error state: MSG-17 when numbering has not started; BD-05's and BD-06's CONFLICT; CONFLICT with "Terapkan Ulang pada Versi Terbaru" (UXS-11); AX-13's refusals in words

**ROUTE IMPACT:**

- ROUTE-014 (PG-014's view), ROUTE-037 (PG-037's view); ROUTE-154 (save the draft), ROUTE-155 (issue), ROUTE-156 (revise), ROUTE-157 (the client's answer); DOC-11 through ROUTE-221 and ROUTE-222 (TASK-029)

**INTERACTION CONTRACTS:**

- INT-154–INT-157; INT-012 for the live preview (TASK-029)

**NAVIGATION CONTRACT:**

- Parent: PG-014 — PG-011 (NAV-014); PG-037 — *Dokumen* › *Penawaran* (NAV-037)
- Entry: the journey's *Kesepakatan* stage; *Rincian proyek*; *Dokumen* › *Penawaran*
- Back action: PG-014's "Kembali" → PG-011; PG-037 none (NAV-037)
- Cancel: PG-014's "Batal" → PG-011, MSG-35 first with unsent input; the confirmation's "Kembali" → the form (NV-02)
- Success destination: PG-014 with the issued revision and its number; after "Catat Jawaban Klien", PG-011 confirmed (NAV-014)
- Error behavior: NV-04 — validation at the field, a rejected command in place, the input kept
- State preservation: unsent input kept across re-login; PG-037's filters and cursor in the address (NV-05)
- Mobile behavior: `[desk]` — read and "Catat Jawaban Klien" on the phone (NAV-014)

**ACCEPTANCE CRITERIA:**

1. Issuing a quotation draws DOC-01's number, freezes the revision's lines and makes it *Terkirim* with its validity date in one transaction, and a second issue of the same draft is CONFLICT with the number (AX-15; BD-06; L-25).
2. A *Terkirim* revision past its validity shows *Kedaluwarsa*, offers only "Buat Revisi", and its approval is refused (L-25; BR-QUO-01).
3. Only the latest sent revision can be approved, a second approval of it is CONFLICT, and a rejected or expired revision is never approved (BR-QUO-01; BD-05; IMG-02).
4. "Buat Revisi" from any non-draft revision creates a new DRAFT revision while the earlier revisions and their DOC-01 versions stay retrievable, and no control voids a quotation (WF-QUO-01; BR-CR-06; REF-018).
5. Issuing, revising, approving or rejecting a quotation changes no stock quantity, reservation or money by itself, and nothing on PG-014 or PG-037 implies that it does (BR-QUO-03; J-QUO-01).
6. After the client's unit, PIC or address changes, an issued quotation keeps the values it was issued with and only a later draft shows the new ones (BR-SN-01; C-11; architecture challenge 1b).
7. Two Admins editing one draft: the later save is CONFLICT with "Terapkan Ulang pada Versi Terbaru" and the input kept (UXS-11; ST-01).
8. A 30-line quotation entered by keyboard alone keeps one focus order, every input reachable beside the live preview at 1,025, 1,279 and 1,280 px (UXS-41; INT-012; D-DS-23).
9. With `projects.view` alone a quotation is listed with its state and without values (J-QUO-01; PJ-15).
10. A company HPS (DOC-11) carries its company issuer and its draft marking until issued and never satisfies a requirement for a client's or a third party's HPS (BR-DOC-04; FS-12; REF-033).
11. The approved revision's lines are what the confirmation pins and the later steps reuse, without retyping (AC-04; OS-01; REF-017).

**TEST REQUIREMENTS:**

- Lint: TL-01
- Typecheck: TL-02
- Unit: the EXPIRED derivation at the WIB day boundary (TL-03)
- Feature: ROUTE-014, ROUTE-037 and ROUTE-154–ROUTE-157 with each refusal (TL-04)
- Integration: concurrent issues of one draft (M-19), concurrent approvals of one revision (BD-05) and concurrent edits of one draft (M-38) (TL-05)
- Authorization: `quotation.manage`, `projects.confirm` and the `finance.view` family (TL-06)
- Route integrity: ROUTE-014, ROUTE-037 and ROUTE-154–ROUTE-157 audited (TL-07)
- Interaction: INT-154–INT-157, each with one `command_id` replayed, and INT-012 with the 30-line quotation (TL-08; IG-05)
- Browser E2E: J-QUO-01 and J-PRJ-02's approval — CJ-01's quotation and approval steps, CJ-13's quotation approval —, the phone's quick action (TL-09)
- Localization/language switch: both languages on PG-014 and PG-037; the paper Indonesian (TL-10)
- Visual breathing room/density: TEST_STRATEGY §9, its composer checks
- Responsive: TL-11, the composer at 1,024, 1,025, 1,279 and 1,280 px
- Accessibility: TL-12 with UXS-41's keyboard-only entry
- Production build: TL-13
- Security: SM-25 for the values and DOC-01's payload; SM-04 for `projects.confirm` (TL-14)
- Performance: PFM-01 and PFM-02 for PG-014 and PG-037; QB-17 for DOC-01's rendition; PFM-20 for the commands (TL-15)
- Migration: N/A — no migration or import code in this Task
- Other: DOC-01's and DOC-11's layouts compared with their previews (DOCUMENT_PREVIEW_PRODUCER §4) and their samples listed for the Owner's content validation (GAP-019)

**TEST CHECKLIST:**

- [ ] Lint
- [ ] Typecheck
- [ ] Relevant automated tests
- [ ] Authorization
- [ ] Route integrity
- [ ] Interaction behavior
- [ ] Browser E2E if required
- [ ] Responsive/adaptive if required
- [ ] Visual breathing room/density if UI
- [ ] Production build
- [ ] Security checks if required
- [ ] Database safety review
- [ ] Final diff review

**COMPLETION EVIDENCE:**

- Test outputs: each required layer's result in [TEST_STRATEGY §16](../08-testing/TEST_STRATEGY.md#16-evidence)'s form, tied to the tested commit (DOD-04)
- Build result: the production build of TL-13
- Route evidence: the route audit for the routes this Task adds or changes, or N/A with its reason
- Interaction evidence: the INT- tests of this Task, or N/A with its reason
- Browser evidence: `E2E: PASS — Evidence: …` for its journeys (DOD-18), or N/A with its reason
- Localization evidence: both languages on every page it changes (DOD-16), or N/A with its reason
- Cost evidence/exception if applicable: ADDITIONAL RECURRING COST CHANGE: NONE (DOD-20)
- Files changed: the list, every one inside the IN SCOPE areas
- Commit SHA: the commit under the Owner's identity, with no AI attribution
- Notes: each non-applicable item with its reason (DOD-03); the DONE update of TASK_PLAN, this file and CURRENT_HANDOFF (AICWDF §22.3)

**SAFE NEXT TASK:**

- TASK-034 — when its other dependencies are DONE and its READY gate holds

**DO NOT DO:**

- add to V1 — a capability, page, route, field, integration, document type, report or paid service — or drop, defer or narrow a V1_REQUIRED requirement; a Task that would need either raises an Owner question and waits ([DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-20)
- access the VPS, production, its data or a credential, or put a secret, real data, a host detail or production configuration in the repository ([INFRASTRUCTURE](../09-operations/INFRASTRUCTURE.md) INF-01, INF-05; SECURITY SX-01, SX-04, SX-06; [PRODUCTION_DATA_SAFETY](../00-governance/PRODUCTION_DATA_SAFETY.md))
- add AI, model or tool attribution to a commit — the Owner's configured identity only ([AGENTS.md](../../AGENTS.md#git-commit-attribution); DIR-022)
- start before the Owner's execution authorization at OG-4, or mark DONE without the required tests passing and the completion evidence ([TASK_PLAN](TASK_PLAN.md#2-rules-of-the-plan); [DEFINITION_OF_DONE](../08-testing/DEFINITION_OF_DONE.md#2-the-checklist))
- weaken, skip or delete a valid test to obtain a green run, or adopt a paid tier without the Owner's COST_POLICY decision (DOD-05; [COST_POLICY](../00-governance/COST_POLICY.md))
- offer a void for a quotation, approve a rejected, expired or superseded revision, or approve without the client's acceptance (BR-CR-06; BR-QUO-01)
- reserve stock or record money from a quotation, or build a second confirmation path beside AX-13 (BR-QUO-03; BR-PRJ-06)
- let a company HPS claim a client's or third party's issuance (BR-DOC-04)

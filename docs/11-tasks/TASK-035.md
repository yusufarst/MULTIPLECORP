# TASK-035 — Administrative checklist and document readiness

Status: APPROVED | Updated: 2026-10-20 | Owner: Planning

Approval: [APPR-016](../00-governance/DECISION_LOG.md#appr-016--p11-task-plan-tasks-and-feature-coverage-matrix-approved), explicit Owner approval on 2026-10-20 (DIR-085 OF-07) of this document as committed in the first execution session's approval commit; the approved file hash and the verified conditions are recorded there. Approval changes lifecycle only, not implementation authorization.

Written by WF-PC5-W's writer of bundle W1 of run PC-5 on 2026-10-19 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99; the Owner approved P11 with the [Task plan](TASK_PLAN.md) at OG-4 (DIR-085 OF-07). **No Task starts before the freeze record, from which the execution authorization is in force (DIR-085 OF-09).** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** READY

**RISK CLASS:** HIGH

**EXECUTOR:** AGENT

**OBJECTIVE:**

Build each project's administrative checklist and the cross-project view of its unmet items: requirements added and tightened in the journey's "Kesiapan dokumen" — a catalog document, an external document type or a free description; *Wajib* or *Opsional*; generated, uploaded or either; "Harus asli dari klien" —, the issued document or the upload linked to satisfy each, "Tandai Tidak Berlaku" on a waiver-eligible item, the Owner's waiver eligibility, relaxation and removal, the supersession of a decision recorded in error, the derived statuses *Belum Ada*, *Disiapkan*, *Terpenuhi* and *Tidak Berlaku*, and PG-039 *Dokumen Administrasi* (ROUTE-039, ROUTE-164–ROUTE-169; WF-ADM-01; J-ADM-01).

**WHY THIS EXISTS:**

CAP-09 and AC-09 need a checklist per project — per client and unit, never one universal SPJ package — in which required, prepared, missing, generated and uploaded material stay distinguishable, a required generated form never silently becomes attachment-only and a company draft never satisfies a client-issued original (BR-ADM-01; REF-037). The administrative N/A is the Admin's only bypass and it is bounded: only on items the Owner made waiver-eligible, with a reason and an audit, while relaxing a required item is the Owner's (BR-PRJ-03; BR-ADM-02; OD-05; OD-06; OWN-03). The checklist is an input of the completion gate, so a voided or replaced document or evidence must reopen what it satisfied (BR-ADM-03; L-41; AC-21; IMG-08; architecture challenge 7b, 20a).

**DEPENDS ON:**

- TASK-030 — Evidence and uploaded documents
- TASK-032 — Projects — list, form, journey page and amendment
- TASK-034 — Commercial confirmation and fulfillment planning

**BLOCKS:**

- TASK-055 — Project completion and Force Complete

**PARALLEL SAFE:** NO — its commands change an input of the completion predicates under the project's row (LK-01; NX-03), and its participants run inside the document, evidence and completion transactions

**IN SCOPE:**

- the checklist inside PG-011's "Kesiapan dokumen" (D-IA-10; J-ADM-01): each item a derived readiness row — the item, its status, what is missing and a link —, missing items first, with no stored progress, fraction, bar or percentage (PT-29); the status *Belum Ada*, *Disiapkan* (draft only), *Terpenuhi* or *Tidak Berlaku* derived each time from the item's links, waivers and linked records (WF-ADM-01; DESIGN_SYSTEM §12.1); on the phone what is missing, with its upload (IP-16; J-ADM-01)
- ROUTE-164 with INT-164, add or tighten a requirement (`requirements.manage`): its kind — a catalog document, an external document type or a free description —, *Wajib* or *Opsional*, the satisfaction mode generated, uploaded or either, and "Harus asli dari klien" only with the uploaded mode (DATABASE §4.11's CHECK); an Admin adding and tightening only — required, a stricter mode, the client-original flag —, an edit that touches waiver eligibility, or makes a required non-waivable item optional, looser or removed, REJECTED (OD-05; OD-06); an item edited by `lock_version` while not yet satisfied (DATABASE §13; ST-01)
- ROUTE-165 with INT-165, link the satisfying document or evidence: a generated item by an issued and rendered version of its type, an uploaded item by an authentic attachment from the correct issuer, an either item by one of them, and a client-original item only by an authentic upload — a draft, a company document or a reproduction of a system rendition never satisfying one (BR-ADM-01; BR-DOC-04; FS-12; architecture challenge 15b) —, the link locking what it links (NX-03), and why a client-original item stays open shown only to a viewer who may see the reproduced rendition (IP-16; GAP-034)
- ROUTE-166 with INT-166, "Tandai Tidak Berlaku" (`requirements.waive`): only on a waiver-eligible item, with MSG-40's reason (IP-09), refused on any other (BR-ADM-02); a required waiver-eligible item handled by *Tidak Berlaku* and never by removal (WF-ADM-01)
- ROUTE-167 with INT-167, the Owner's waiver eligibility (`requirements.set_waiver_eligibility`), every item non-waivable by default (BR-PRJ-03; OD-05), and ROUTE-168 with INT-168, the Owner's relaxation or removal of a required non-waivable item with its reason (`requirements.relax`; DIR-024 D-5; OD-06) — both absent from an Admin's form (J-ADM-01)
- ROUTE-169 with INT-169, a decision recorded in error superseded by one of the same authority with its reason (`decisions.supersede`; CM-27): a waiver in readiness or on PG-039 through NX-03, and an approval on PG-014 through TASK-034's AX-13 step, each with the lock set of the action that recorded the decision and the projects of both (CONCURRENCY_IDEMPOTENCY §7.3), the dependent facts corrected first, the pins following and reservations changing only through SF-RESERVE, never by reactivation (L-32); an Owner decision superseded only by the Owner (OD-15)
- ADM's parts of the inversion contracts (ARCHITECTURE §3): `DocumentChangeParticipant`, re-evaluating the items linked to a voided version, a revision's issue or a replaced evidence inside DOC's transaction (L-41; BR-ADM-03; architecture challenge 7b, 20a); `ProjectLifecycleParticipant`'s revalidation, reopening the affected items (SF-REVAL; BR-ADM-03); and `CompletionPredicateProvider`'s administration predicate — every required item satisfied, validly waived or removed by the Owner (WF-PRJ-03, predicate 5)
- the default invoice item (L-27; `is_default_invoice_item`): a project with confirmed commercial value carries the invoice record as a required item, satisfied only when the non-void invoiced sales value equals the confirmed sales value less cancelled scope and returns (WF-ADM-01), read from PRJ's commercial guard and the invoices (TASK-034; TASK-049)
- PG-039 with NAV-039 and its view ROUTE-039 (`projects.view`; SCR-37): the unmet items across projects, one bounded branch per company in scope, each row with its company; filters, sort, tab and cursor in the address (IP-18); "Tandai Tidak Berlaku" with its reason for `requirements.waive` and the Owner's relaxations (INFORMATION_ARCHITECTURE §8.4)

**OUT OF SCOPE:**

- the documents — drafts, issue, revision, void, renditions — and DOC's `DocumentChangeParticipant` contract itself (TASK-029); evidence upload, registration, linking to records, versions and replacement (TASK-030)
- completion, the evaluation of the ten predicates inside AX-25, Force Complete and SF-REVAL's flow (TASK-055); the invoices whose value the default item reads (TASK-049); QS-15 and the action queue (TASK-058)
- reusable client checklist templates and an advanced template editor — deferred (V1_SCOPE D-02)

**REFERENCES:**

- [WORKFLOWS §5.5](../03-workflows/WORKFLOWS/s05-04-05-fulfillment-documents.md#wf-adm-01--administrative-checklist-dokumen-administrasi--spj-desk) WF-ADM-01; [WF-DOC-01](../03-workflows/WORKFLOWS/s05-04-05-fulfillment-documents.md#wf-doc-01--generated-document-lifecycle-desk); [WF-DOC-02](../03-workflows/WORKFLOWS/s05-04-05-fulfillment-documents.md#wf-doc-02--external-and-uploaded-documents-both); [WF-PRJ-03](../03-workflows/WORKFLOWS/s05-07-08-completion-migration.md#wf-prj-03--project-completion-penyelesaian-desk) predicate 5; [§8](../03-workflows/WORKFLOWS/s08-09-corrections-indivisible.md#8-correction-reversal-and-cancellation-matrix) CM-27; [§12](../03-workflows/WORKFLOWS/s10-14-signals-decisions-traceability.md#12-planner-determined-level-1-decisions) L-27, L-32, L-41
- [BUSINESS_RULES Administration (ADM)](../02-domain/BUSINESS_RULES.md#administration-adm) BR-ADM-01–BR-ADM-03; [SM:Project](../02-domain/BUSINESS_RULES.md#smproject) BR-PRJ-02–BR-PRJ-04; [BR-DOC-04](../02-domain/BUSINESS_RULES.md#smdocument-all-doc-0114-invoice-is-a-specialization); [§7](../02-domain/BUSINESS_RULES.md#7-forbidden-shortcuts) FS-12
- [V1_SCOPE](../01-product/V1_SCOPE.md#must-ship) CAP-09; [D-02](../01-product/V1_SCOPE.md#deferred); [ACCEPTANCE_CRITERIA](../01-product/ACCEPTANCE_CRITERIA.md#product-acceptance-contracts) AC-09, AC-21; [REFERENCE_COVERAGE](../01-product/REFERENCE_COVERAGE.md#pdf-capability-inventory) REF-037; [IMG-08](../01-product/REFERENCE_COVERAGE.md#image-and-non-tabular-pdf-inventory); [OWN-03](../01-product/REFERENCE_COVERAGE.md#final-owner-decision-coverage)
- [DATABASE §4.11](../04-architecture/DATABASE/s04-11-adm.md#411-adm--administration) `admin_requirements`, `admin_requirement_links`, `admin_requirement_waivers`; [§10](../04-architecture/DATABASE/s07-10-representations.md#10-document-architecture) external evidence and authenticity; [§13](../04-architecture/DATABASE/s13-17-history-scope-types.md#13-correction-and-history-representation); [§28](../04-architecture/DATABASE/s25-28-transactions-handoffs.md#28-security-ready-data-design)
- [ARCHITECTURE §3](../04-architecture/ARCHITECTURE.md#3-modules) `DocumentChangeParticipant`, `ProjectLifecycleParticipant`, `CompletionPredicateProvider`
- [PERMISSIONS_MATRIX §4](../05-security/PERMISSIONS_MATRIX.md#4-capability-catalogue) `requirements.manage`, `requirements.waive`, `requirements.set_waiver_eligibility`, `requirements.relax`, `decisions.supersede`; [§5](../05-security/PERMISSIONS_MATRIX.md#5-workflows-4-mapping) W4-03, W4-04, W4-15, W4-18, W4-24; [§6](../05-security/PERMISSIONS_MATRIX.md#6-company-scope-rules) CS-03, CS-04, CS-07, CS-08; [§7.2](../05-security/PERMISSIONS_MATRIX.md#72-table-classification) rows 96–98; [§7.3](../05-security/PERMISSIONS_MATRIX.md#73-special-projections) PJ-08, PJ-11, PJ-15; [§9](../05-security/PERMISSIONS_MATRIX.md#9-owner-only-denials) OD-05, OD-06, OD-15
- [ROUTE_CONTRACTS](../03-workflows/ROUTE_CONTRACTS.md#requirements--kesiapan-dokumen-and-dokumen-administrasi-adm-pg-011-pg-039-wf-adm-01) ROUTE-164–ROUTE-169; [§2](../03-workflows/ROUTE_CONTRACTS.md#2-page-views--route-001route-065) ROUTE-039; [INTERACTION_CONTRACTS](../03-workflows/INTERACTION_CONTRACTS.md#requirements--kesiapan-dokumen-and-dokumen-administrasi-adm-pg-011-pg-039-wf-adm-01) INT-164–INT-169
- [NAVIGATION_CONTRACTS §2.4](../07-ux-design/NAVIGATION_CONTRACTS.md#24-fulfilment-cases-and-documents) NAV-039; [§2.2](../07-ux-design/NAVIGATION_CONTRACTS.md#22-projects-and-quotations) NAV-011; [INFORMATION_ARCHITECTURE §8.4](../07-ux-design/INFORMATION_ARCHITECTURE.md#84-fulfilment-cases-and-documents) PG-039; [§8.7](../07-ux-design/INFORMATION_ARCHITECTURE.md#87-the-approved-screens-scr-01scr-60-and-their-pages) SCR-37; [§4](../07-ux-design/INFORMATION_ARCHITECTURE.md#4-design-decisions) D-IA-10
- [ADMIN_FLOW §10.5](../07-ux-design/ADMIN_FLOW/s10-b-journeys.md#105-documents-and-administration) J-ADM-01; [IP-09](../07-ux-design/ADMIN_FLOW/s05-command-model.md#ip-09--corrections-closures-and-destructive-actions); [IP-16](../07-ux-design/ADMIN_FLOW/s06-09-session-forms-lists.md#ip-16--evidence-upload); [§14](../07-ux-design/ADMIN_FLOW/s14-15-messages-scenarios.md#14-message-catalogue) MSG-40
- [DESIGN_SYSTEM §9.3](../07-ux-design/DESIGN_SYSTEM.md#93-forms-and-entry) PT-29; [§12.1](../07-ux-design/DESIGN_SYSTEM.md#121-status-system) the administrative requirement's statuses
- [CONCURRENCY_IDEMPOTENCY §7.3](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s07-statement-sequences.md#73-command-families-outside-ax-0137) NX-03, NX-12, NX-14; [§7.1](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s07-statement-sequences.md#71-lock-sets-of-ax-01ax-37) notes, AX-13, AX-15; [§9](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s08-09-guards-stale-state.md#9-stale-state) ST-01; [§10](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s10-13-identity-failure-retry.md#10-command-identity-and-replay) CI-01, CI-05
- Architecture challenge — the sequences 7b, 15b, 20a of the [P11 gate record](evidence/P11_QUALITY_GATE.md#whole-system-architecture-challenge-sp-98), their assertions carried into this Task's acceptance criteria and tests
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (36): O-03; CAP-09; AC-09; AC-21; REF-034; REF-037; IMG-05–IMG-06; IMG-08; OWN-03; BR-ADM-01–BR-ADM-03; WF-ADM-01; CM-27; L-41; ROUTE-039; ROUTE-164–ROUTE-169; INT-164–INT-169; NX-03; D-IA-10; PG-039; SCR-37; J-ADM-01; PT-29; NAV-039

**EXPECTED MODULES / FILE AREAS:**

- the ADM module's AddRequirement, TightenRequirement, LinkRequirementEvidence, WaiveRequirement, SetWaiverEligibility, RelaxRequirement, RemoveRequirement and SupersedeDecision actions, its implementations of the three contracts and `ShowAdministrativeDocuments` with its query classes (RC-02); `resources/js/Pages/ADM` for PG-039 and the checklist inside PG-011's "Kesiapan dokumen"; `tests/Feature/ADM` and `tests/Concurrency/`

**BUSINESS RULES:**

- BR-ADM-01: each project has its own checklist; an item is satisfied by generation, upload or either; a required generated form never silently becomes attachment-only; a company draft never satisfies a required client original — and with BR-DOC-04, FS-12 and L-41 a reproduction of a company-issued document never counts as a client or third-party original
- BR-PRJ-03 and BR-ADM-02: only the Owner sets waiver eligibility, every item non-waivable by default; TIDAK BERLAKU only on an eligible item, with reason, individual actor and audit; an ineligible attempt is denied
- WF-ADM-01's authority (DIR-024 D-5): an Admin adds and tightens; removing, making optional, relaxing the mode or clearing the client-original flag of a required non-waivable item is the Owner-only override; a required waiver-eligible item is handled by *Tidak Berlaku* and not by removal
- BR-ADM-03 and L-41: material corrections — a void or revision of a satisfying document and a replacement of satisfying evidence among them — reopen the affected items and re-trigger completion revalidation (BR-CR-05)
- WF-ADM-01: the status — required, prepared (draft only), missing, satisfied, waived — is derived, never stored separately
- L-27 with L-20: a project with confirmed commercial value carries the invoice record as a default required item, satisfied only when the non-void invoiced sales value equals the confirmed sales value less cancelled scope and returns; a lower value needs a revision or a re-confirmation
- CM-27 and L-32: a decision recorded in error is superseded by one of the same authority with its reason, its dependent facts corrected first; truth is restored without deletion
- BR-PRJ-02, BR-PRJ-04 and AC-21: administration is one of the ten completion predicates; an Admin satisfies permitted items or uses the eligible N/A and never bypasses an invariant or forces completion

**ARCHITECTURE RULES:**

- ADM is tier 4: it reads documents, evidence and the project through their query classes and never writes them; DOC reaches ADM only through `DocumentChangeParticipant` inside DOC's transaction, and PRJ through `ProjectLifecycleParticipant` and `CompletionPredicateProvider` (ARCHITECTURE §3)
- satisfaction is derived from the linked issued and rendered version or authentic upload, never stored (DATABASE §4.11); a link is unlinked once with its reason and never deleted, a waiver is an append-only decision with its supersession chain, and a removed requirement keeps its row (DATABASE §13)

**SECURITY / PERMISSION REQUIREMENTS:**

- ROUTE-164 and ROUTE-165 `requirements.manage` (ADM; prerequisite `projects.view`); ROUTE-166 `requirements.waive` (ADM; prerequisite `projects.view`); ROUTE-167 `requirements.set_waiver_eligibility` and ROUTE-168 `requirements.relax` (OWNER_ONLY); ROUTE-169 `decisions.supersede` (ADM_PLUS; prerequisite the decision's view capability), an Owner decision only by the Owner (OD-15); ROUTE-039 `projects.view` (PERMISSIONS_MATRIX §4; W4-03, W4-04, W4-15, W4-18, W4-24)
- `waiver_eligible` readable and writable only by the Owner (row 96; OD-05); an Admin's edit touching it, or relaxing a required non-waivable item, REJECTED (OD-05; OD-06); the Owner's controls absent from an Admin's form (J-ADM-01; NV-08), the server deciding either way (AZ-01)
- a linked document opened only with its family's view capability and evidence only with `evidence.view` and its family's (PJ-15; PJ-08); server-derived fields never taken from the payload (DP-03)

**COMPANY SCOPE AND FIELD PROJECTION:**

- a requirement belongs to its project's company; the command's company is the project's and every document version or evidence it links resolves inside it, an out-of-scope one failing exactly like a nonexistent one (CS-03; CS-04; AZ-11)
- rows 96–98: `admin_requirements`, `admin_requirement_links` and `admin_requirement_waivers` PV(c), `waiver_eligible` readable and writable by the Owner only (row 96); the audit's before and after projected by family (PJ-11)
- PG-039: one bounded branch per company in scope, each row labelled with its company, the scope filter inside the query before ordering and pagination, no count including an item the actor cannot see (CS-07; CS-08; RD-03)

**CONCURRENCY / IDEMPOTENCY:**

- ROUTE-164–ROUTE-168 are NX-03 (LK-00, LK-01, LK-03, LK-06, LK-29, LK-30, LK-31) under the project's row, a link locking what it links — the evidence at LK-29, the document version at LK-30 — so that a void, a revision or a replacement sees every link (CONCURRENCY_IDEMPOTENCY §7.1 notes, §7.3)
- ROUTE-169 takes the lock set of the action that recorded the superseded decision — NX-03 for a waiver, AX-13 for an approval — with the projects of both (§7.3); `supersedes_waiver_id` UNIQUE keeps one linear chain (DATABASE §4.11)
- an edit by `lock_version`, a stale one CONFLICT (ST-01); `command_id` on every command with re-authorized replay (CI-01; CI-05); the participants' re-evaluation inside the void's, revision's or replacement's transaction under LK-01 (NX-12; NX-14; AX-15)

**AUDIT:**

- every add, tightening, link, unlink, waiver, eligibility change, relaxation, removal and supersession is a business audit event in its transaction with the individual actor, before and after, and the reason where required (BR-XC-01; BR-CR-01; LG-01); waiver eligibility and requirement relaxation are privileged changes always audited with their authority class (DATABASE §28; AZ-10), OWNER_ONLY and ADM_PLUS events feeding the Owner review queue
- the waiver's `reason`, `decided_by` and `supersedes_waiver_id`, the link's `linked_by` and `unlinked_at/by/reason` and the requirement's `removed_by/at/reason` are the items' own history (DATABASE §4.11)

**DATABASE IMPACT ASSESSMENT:**

- Schema change: NONE — uses the tables of TASK-005–TASK-009; a missing column is an executor discovery (AGENT_OPERATING_MODEL)
- Migration required: NO
- Existing data transformation: NONE
- Direct production DB access: FORBIDDEN
- Data-loss risk: NONE KNOWN

**MODULARITY / EXTENSIBILITY:**

- Module boundary affected: ADM, tier 4 — implementing DOC's `DocumentChangeParticipant` and PRJ's `ProjectLifecycleParticipant` and `CompletionPredicateProvider` for its part (ARCHITECTURE §3)
- Stable core kept provider-neutral: YES
- External/vendor-specific code isolated: YES — framework facilities only (ARCHITECTURE §2)
- Replaceable adapter/contract required: NO — the three contracts exist (TASK-029, TASK-032)
- Provider lock-in risk: NONE beyond the Owner's baseline stack
- Future extension impact: LOW
- Speculative abstraction avoided: YES — no reusable checklist templates (V1_SCOPE D-02), no stored status (WF-ADM-01)

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

- Context7: OPTIONAL — CHECK constraints with an exclusive arc and UNIQUE supersession keys, at the installed version
- Graphify: OPTIONAL — once the completion Task reads the predicate
- Browser E2E: REQUIRED — J-ADM-01 on the desktop and on the phone
- Other: two projects with different checklists (AC-09); an item of each satisfaction mode and a client-original item with a reproduction fixture; seeded confirmed and invoiced values for the default invoice item (L-27)
- Missing safe tool behavior: AUTO-INSTALL project-locally inside this Task (AICWDF §6.3) and record it in TOOLCHAIN; a paid tier or a privileged installation STOPS for the Owner (COST_POLICY)

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: refreshed before the change when Graphify is available ([TOOLCHAIN](../00-governance/TOOLCHAIN.md)); otherwise a targeted search of ADM and of the callers of DOC's and PRJ's contracts
- Pre-change questions: which reads derive an item's status, and where a void, a revision's issue and an evidence replacement call ADM's participant
- Expected affected areas: ADM's actions, query classes and contract implementations

**CONTEXT7:**

- Topics to verify: CHECK constraints over an exclusive arc; UNIQUE keys of a linear supersession chain
- Version-sensitive assumptions: none from memory

**UI / UX REQUIREMENTS:**

- Follow global design rules: YES
- Indonesian default: YES
- English support: YES
- Plain-language Indonesian: YES — the statuses *Belum Ada*, *Disiapkan*, *Terpenuhi*, *Tidak Berlaku* (GL-127); MSG-40 for each reason; why a client-original item stays open, where the viewer may see it (J-ADM-01)
- Language switch impact: keeps unsent input
- Mobile-first: YES — J-ADM-01 `[desk]`: the checklist built on the desktop, the phone showing what is missing and taking its upload; PG-039 `[desk]` (NAV-039)
- Responsive: YES
- Adaptive: YES — the readiness rows on the desktop, dense rows on the phone (PT-29; PT-06); PG-039's table and its phone rows (PT-05; PT-06)
- Visual breathing room: REQUIRED
- Comfortable density: REQUIRED
- shadcn/ui reuse: the derived readiness list (PT-29), status badges (PT-13; DESIGN_SYSTEM §12.1), the data table and filters of PG-039 (PT-05; PT-07; PT-08), the confirmation with its reason (PT-26), dialogs and sheets (PT-16)
- Design reference: the checklist in "Kesiapan dokumen" (D-IA-10) and PG-039 as INFORMATION_ARCHITECTURE §8.4 states it; the Golden Frontend Reference for presentation only (DIR-061 SD-02)
- Primary action: on an open item its satisfying step — link the issued document or the upload
- Secondary actions: add or tighten a requirement; "Tandai Tidak Berlaku" on an eligible item; the Owner's eligibility and relaxation
- Destructive actions: the Owner's removal of a required item, with its reason (IP-09; PT-11)
- Redundancy constraints: no fraction, progress bar or percentage (PT-29; SLOP-44); no Owner-only control on an Admin's form (J-ADM-01)
- Loading state: PT-19
- Empty state: a project without requirements shows one sentence and the action to add one; PG-039 without unmet items one sentence (PT-19)
- Error state: OD-05's, OD-06's and OD-15's refusals and the ineligible waiver's in business words; CONFLICT on a stale edit (ST-01); MSG-12 at the fields (IP-14)

**ROUTE IMPACT:**

- ROUTE-039 (PG-039's view); ROUTE-164 (add or tighten), ROUTE-165 (link), ROUTE-166 (N/A), ROUTE-167 (waiver eligibility), ROUTE-168 (relaxation or removal), ROUTE-169 (supersession); PG-011's view ROUTE-011 (TASK-032) carrying the checklist

**INTERACTION CONTRACTS:**

- INT-164–INT-169

**NAVIGATION CONTRACT:**

- Parent: PG-039 — *Dokumen* › *Dokumen Administrasi* (NAV-039); the checklist — PG-011's "Kesiapan dokumen" (NAV-011)
- Entry: *Dokumen* › *Dokumen Administrasi*; PG-011 › "Kesiapan dokumen"; from an item to its document on PG-036 or its evidence
- Back action: PG-039 none (NAV-039); PG-011's "Kembali" → PG-010 (NAV-011)
- Cancel: the reason step's "Kembali" → the form, the input kept (NAV-039; IP-09; NV-02)
- Success destination: in place — the requirement, its link, its waiver or the new decision with its status (ROUTE-164–ROUTE-169; NV-03)
- Error behavior: NV-04 — validation at the field, a refusal in place with the input kept
- State preservation: PG-039's filters, sort, tab and cursor in the address (NAV-039; IP-18); PG-011's open section (NAV-011)
- Mobile behavior: `[desk]` — PG-039 opens on the phone for reading (NV-06); the project's checklist shows what is missing and takes its upload (J-ADM-01)

**ACCEPTANCE CRITERIA:**

1. Two projects can carry different checklists, and each item shows *Belum Ada*, *Disiapkan*, *Terpenuhi* or *Tidak Berlaku* derived from its links, waivers and linked records, with no stored status, fraction or progress bar, and no universal SPJ package (CAP-09; AC-09; WF-ADM-01; PT-29; REF-037).
2. A generated item is satisfied only by an issued and rendered version of its type: while its document is a draft it shows *Disiapkan*, printed or not, and the draft never satisfies it (BR-ADM-01; WF-DOC-01; architecture challenge 15b).
3. A client-original item is satisfied only by an authentic upload; a company draft, an issued company document or an upload equal to a stored rendition leaves it open, the reason shown only to a viewer who may see that rendition (BR-ADM-01; FS-12; IP-16; SM-23).
4. An Admin can add requirements and make them stricter, but an edit that touches waiver eligibility, or makes a required non-waivable item optional, looser or removed, is REJECTED, and the controls for them are absent from the Admin's form (OD-05; OD-06; J-ADM-01).
5. "Tandai Tidak Berlaku" with a reason marks a waiver-eligible item *Tidak Berlaku*, is refused on an ineligible one, and without a reason is answered at the field (BR-ADM-02; AC-09; IP-09).
6. Only the Owner sets waiver eligibility — a new item is non-waivable by default — and relaxes or removes a required item with a reason, each an audited OWNER_ONLY event that reaches the Owner review queue (BR-PRJ-03; OD-05; OD-06; AZ-10; OWN-03).
7. Voiding an issued document that satisfies an item re-evaluates that item inside the void's transaction, so it no longer shows *Terpenuhi* on that version and the administration predicate reads it open (L-41; BR-ADM-03; architecture challenge 7b).
8. Unlinking or replacing evidence that satisfies an item re-evaluates the item in the same transaction — an unlinked item is open again, a replaced one judged on the new current version (L-41; BR-ADM-03; architecture challenge 20a).
9. A revalidation after a material correction reopens the affected items, a reopened waiver kept as history and marked as reopened by that correction (BR-ADM-03; SF-REVAL; DATABASE §4.11).
10. ROUTE-169 supersedes a waiver recorded in error by a new decision of the same authority with its reason, both decisions visible, and an Owner decision is refused to an Admin (CM-27; OD-15).
11. Superseding a quotation approval runs TASK-034's confirmation step with AX-13's lock set and the projects of both, the pins following and no reservation reactivated (CM-27; L-32; CONCURRENCY_IDEMPOTENCY §7.3).
12. The default invoice item of a project with confirmed value stays open until the non-void invoiced value equals the confirmed value less cancelled scope and returns (L-27; WF-ADM-01).
13. PG-039 lists the unmet items of the companies in scope only, each with its company, no count or page including an item the actor cannot see, and its filters and cursor return with Back and reload (CS-07; CS-08; NAV-039; SCR-37).
14. The administration predicate ADM provides is false while a required item is open, whatever the project's delivery or payment state, and an Admin has no path past it (WF-PRJ-03; AC-21; IMG-08).
15. Each of ROUTE-164–ROUTE-169 replayed with one `command_id` returns the recorded outcome, and a stale edit is CONFLICT (CI-05; ST-01).

**TEST REQUIREMENTS:**

- Lint: TL-01
- Typecheck: TL-02
- Unit: the derived status of each satisfaction mode, of a client-original item and of the default invoice item (TL-03)
- Feature: ROUTE-039 and ROUTE-164–ROUTE-169 with each refusal — OD-05, OD-06, the ineligible waiver, OD-15 —; a generated item with only a draft of its document staying *Disiapkan* (architecture challenge 15b) (TL-04)
- Integration: a void, a revision's issue and an evidence replacement or unlink re-evaluating a linked item inside DOC's transaction (NX-12; NX-14; AX-15; architecture challenge 7b, 20a); a link racing a void of the linked version under LK-30 (TL-05)
- Authorization: `requirements.manage`, `requirements.waive`, the OWNER_ONLY `requirements.set_waiver_eligibility` and `requirements.relax`, and `decisions.supersede` with OD-15 (TL-06)
- Route integrity: ROUTE-039 and ROUTE-164–ROUTE-169 audited (TL-07)
- Interaction: INT-164–INT-169, each with one `command_id` replayed (TL-08; IG-05)
- Browser E2E: J-ADM-01 — CJ-20's checklist and uploads, CJ-17's N/A eligibility cases —; the phone's missing items with their upload (TL-09)
- Localization/language switch: both languages on PG-039 and the checklist (TL-10)
- Visual breathing room/density: TEST_STRATEGY §9 for PG-039 and the readiness rows
- Responsive: TL-11 at DESIGN_SYSTEM §6's band edges
- Accessibility: TL-12, each status carried by its shape and label as well as its colour (DESIGN_SYSTEM §12.1)
- Production build: TL-13
- Security: SM-04 for W4-03, W4-04, W4-15, W4-18 and W4-24 with OD-05, OD-06 and OD-15; SM-23 for the undisclosed reproduction; SM-01 for a foreign document or evidence reference (TL-14)
- Performance: PFM-01 and PFM-02 for PG-039 (QB-12) and PG-011's documents group (QB-02); PFM-20 for the commands (TL-15)
- Migration: N/A — no migration or import code in this Task
- Other: the administration predicate handed to TASK-055 with one blocker per kind of item (AC-21)

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

- TASK-055 — when its other dependencies are DONE and its READY gate holds

**DO NOT DO:**

- add to V1 — a capability, page, route, field, integration, document type, report or paid service — or drop, defer or narrow a V1_REQUIRED requirement; a Task that would need either raises an Owner question and waits ([DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-20)
- access the VPS, production, its data or a credential, or put a secret, real data, a host detail or production configuration in the repository ([INFRASTRUCTURE](../09-operations/INFRASTRUCTURE.md) INF-01, INF-05; SECURITY SX-01, SX-04, SX-06; [PRODUCTION_DATA_SAFETY](../00-governance/PRODUCTION_DATA_SAFETY.md))
- add AI, model or tool attribution to a commit — the Owner's configured identity only ([AGENTS.md](../../AGENTS.md#git-commit-attribution); DIR-022)
- start before the Owner's execution authorization at OG-4, or mark DONE without the required tests passing and the completion evidence ([TASK_PLAN](TASK_PLAN.md#2-rules-of-the-plan); [DEFINITION_OF_DONE](../08-testing/DEFINITION_OF_DONE.md#2-the-checklist))
- weaken, skip or delete a valid test to obtain a green run, or adopt a paid tier without the Owner's COST_POLICY decision (DOD-05; [COST_POLICY](../00-governance/COST_POLICY.md))
- let a draft, a company document or a reproduction satisfy a requirement, or a required generated form become attachment-only (BR-ADM-01; FS-12)
- give an Admin a control or a path to waiver eligibility, relaxation or removal, or a waiver on an ineligible item (OD-05; OD-06; BR-ADM-02)
- store a requirement's status, or delete a requirement, link or waiver row (WF-ADM-01; DATABASE §13)
- add reusable checklist templates or a template editor (V1_SCOPE D-02)

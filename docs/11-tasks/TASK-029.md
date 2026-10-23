# TASK-029 — Document engine — drafts, issue, revision, void, renditions and the composer

Status: APPROVED | Updated: 2026-10-23 | Owner: Planning

Approval: [APPR-016](../00-governance/DECISION_LOG.md#appr-016--p11-task-plan-tasks-and-feature-coverage-matrix-approved), explicit Owner approval on 2026-10-20 (DIR-085 OF-07) of this document as committed in the first execution session's approval commit; the approved file hash and the verified conditions are recorded there. Approval changes lifecycle only, not implementation authorization.

Amendment: on 2026-10-23 the Owner's decision [DIR-089](../00-governance/DECISION_LOG.md#dir-089-and-appr-018--task-006s-discoveries-and-the-files-company-and-purpose) (3) gives this Task the fourteen rows of `document_types` — a scope line, the database impact's migration, criterion 16 and its integration test; no other line changes. It is a Level 1 record under CHANGE_CONTROL, as the Owner directs.

Written by WF-PC5-W's writer of bundle W1 of run PC-5 on 2026-10-19 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99; the Owner approved P11 with the [Task plan](TASK_PLAN.md) at OG-4 (DIR-085 OF-07). **No Task starts before the freeze record, from which the execution authorization is in force (DIR-085 OF-09).** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** TODO

**RISK CLASS:** HIGH

**EXECUTOR:** AGENT

**OBJECTIVE:**

Build the generated-document engine and its pages: the document chain with one draft and one issued version per chain, the draft's save, the issue that draws the number and freezes the snapshot, revision and void, the rendition's state and inline view, PG-035 *Dokumen* and PG-036 *Detail Dokumen* with the composer — the form beside the live preview on the desktop, Edit → Preview → Finalize on the phone — and the one template per document layout that both the browser's draft preview and the renderer read, for the fourteen types DOC-01–DOC-14 (WF-DOC-01; SF-ISSUE; AX-15; ROUTE-221–ROUTE-225).

**WHY THIS EXISTS:**

CAP-08 and AC-08 require a company-correct catalogue of fourteen selectable documents with draft and final, PDF and print, revision, void and history, stable identity and numbers, and no stock, cash or obligation from generating a layout (DOC-01–DOC-14; BR-DOC-01–BR-DOC-04; BR-XD-04). An issued document must match what the user previewed and confirmed (architecture challenge 15a: D-DS-23; DESIGN_SYSTEM §14.3), a draft must never pass for an issued one (15b; GAP-053), and an issued document must keep its PDF through any crash (GAP-007). The Owner's answer to OQ-22 makes every document issued from recorded facts open its draft on PG-036 first (`DIR-081` OB-02), so one engine serves every issuing Task.

**DEPENDS ON:**

- TASK-021 — Numbering, its settings page and the Owner's raise after a restore
- TASK-022 — Jobs, files and the document renderer
- TASK-025 — Companies, identity assets, bank accounts and tax configuration

**BLOCKS:**

- TASK-033 — Quotations
- TASK-037 — Purchases — project and replenishment purchases
- TASK-039 — Receiving (Barang Masuk)
- TASK-041 — Dispatch (Barang Keluar) and inter-company allocation
- TASK-045 — Deliveries (Pengiriman) and delivery closure
- TASK-046 — Direct shipment (drop-ship)
- TASK-047 — Service fulfillment and handover
- TASK-049 — Invoices and the pre-payment Kuitansi
- TASK-059 — Search and scanning
- TASK-074 — Backup tooling, the off-server copy and the drill procedures

**PARALLEL SAFE:** NO — it owns the document chain (LK-30), the issue's draw at LK-31 and the template contract that every issuing Task builds on

**IN SCOPE:**

- the DOC records of DATABASE §4.10 and §10 in use: `documents` with their record-bound subject arc — one chain per subject and type (FU-12) —, `document_versions` with one DRAFT and one ISSUED version per chain, `document_renditions`; and the two inversion contracts DOC declares — `DocumentSubjectProvider`, each issuing module's record-bound precondition and payload, and `DocumentChangeParticipant`, which re-evaluates requirements and revalidates completion inside DOC's transaction when a version is voided, a revision is issued or evidence is replaced (ARCHITECTURE §3; L-41; BR-ADM-03; SF-REVAL)
- ROUTE-221 with INT-221 (`documents.issue`; NX-12): the draft saved on PG-036; the draft a "Terbitkan …" control creates first from the recorded facts, or saves again when an earlier press left one for the same subject; a draft left by a closed confirmation or a refused issue staying a DRAFT, open on PG-036 (WF-DOC-01; `DIR-081` OB-02)
- ROUTE-222 with INT-222 (AX-15; SF-ISSUE): the confirmation (IP-08; MSG-39), the record-bound precondition, the company active when the issue starts a new chain of a document other than a collection document (C-54; BR-ACC-03), the next number of the company, type and business-date period drawn through TASK-021's numbering service (NM-01–NM-10; C-10), the immutable payload with the company identity, the bank destination by the version's typed account reference and the number (BR-DOC-01; BR-DOC-02; BR-SN-01–BR-SN-03), and the PENDING rendition; PG-036's confirmation sending the issuing route of the draft's origin — ROUTE-222, or ROUTE-182, ROUTE-187, ROUTE-202 and ROUTE-249, which their Tasks build (OB-02)
- ROUTE-223 and ROUTE-224 with INT-223 and INT-224 (`documents.correct`; NX-12; CM-26), each with its reason (IP-09; MSG-40): "Revisi" — a new version linked to its predecessor, which stays retrievable, NEW_NUMBER or BASE_SUFFIX as the company's scheme states (NM-07; GAP-019) — and "Batalkan Dokumen" — the version and its number retired forever, never cascading to the business record (BR-CR-06; L-41)
- ROUTE-225 with INT-225: the issued rendition inline under FL-07's RENDITION profile, with "Unduh" and "Cetak"; the rendition's states on PG-036 — PENDING, READY, FAILED with the attempt count — with MSG-05 and MSG-08 and no re-issue control (UXI-29; UXS-09; IP-12); a voided version shown with "Dibatalkan" and never offered as current (DESIGN_SYSTEM §14.3)
- the composer (component Document composer and paper; D-DS-23; GR-10; INT-012): from 1,025 px the form on the left and the live preview on the right at VD-07's share, sticky with "Pas lebar", "100%" and "Layar penuh"; from 1,025 to 1,279 px the sidebar as the rail and the preview at 40 %; "Rincian | Pratinjau" below 1,025 px; on the phone Edit → Preview → Finalize, the preview full-screen, DOC-12's floor step included (DC-11; AN-6) — one component that the composer pages of TASK-033, TASK-047 and TASK-049 also use
- the draft preview's producer — DOCUMENT_PREVIEW_PRODUCER option A, normative in DESIGN_SYSTEM §14.3 — with the Architect's part of its §3: one template per document layout, read by the browser's composer and by TASK-022's renderer; the fonts served from the application's origin (WS-08); the template's draft mode — "DRAF — belum terbit", "Nomor diberikan saat terbit", the text signature block, totals labelled "perkiraan" until the server's —; the A4 print style with the renderer's margins and the marking on every page; the data contract — the draft's fields as props by family (PJ-15) with PJ-19's identity assets, never the number, the stamp or the signature; a preview and its print writing nothing (D-DS-20; GAP-053)
- the baseline layout of each of DOC-01–DOC-14 as one template on that mechanism, with the company's identity applied (V1_SCOPE selectable document catalog; DEP-04), each layout placed in its payload family — an operational layout that prints a price or cost belonging to the finance or cost family (PJ-15); the record each type renders as WF-DOC-01's table states, supplied by its issuing Task through `DocumentSubjectProvider`, so the record's verified data is reused and never typed again (O-01; O-03; REF-032; REF-033); two layouts of one record never a second transaction (BR-DOC-04; L-19; L-26); external originals stay uploads, never generated (REF-034; IMG-06)
- PG-035 with NAV-035 and ROUTE-035, and PG-036 with NAV-036 and ROUTE-036 (SCR-34, SCR-35): the generated documents by branch with the exact number lookup (PF-19), metadata with `projects.view` and payload by family; a document's draft composer, its versions, rendition state, issue, revision and void; the pre-payment Kuitansi's own state *Menunggu Pembayaran* with "untuk proses pembayaran" until it is linked or voided (UXS-38; J-FIN-08)
- the paper Indonesian whatever the interface language (DIR-037 D7; LOCALIZATION §3.4; architecture challenge 17a); an issued document printed or downloaded from its rendition, never from a screen, a draft printed by the browser as a marked draft (component Print view; DESIGN_SYSTEM §17; REF-047)
- the fourteen rows of `document_types`, DOC-01–DOC-14, seeded by a migration from the approved V1 text only ([DIR-089](../00-governance/DECISION_LOG.md#dir-089-and-appr-018--task-006s-discoveries-and-the-files-company-and-purpose) (3)): each `name` from V1_SCOPE's catalogue; `is_numbered` true for every type, each issue drawing its type's next number (WORKFLOWS WF-DOC-01); `allowed_modes` RECEIPT and PRE_PAYMENT for DOC-05 and STANDARD for the others (DATABASE §4.10); `subject_kind` the subject DATABASE §4.10's arc and WF-DOC-01's table give each type; a value those texts do not determine raised as an Owner question, never invented

**OUT OF SCOPE:**

- the queue, the renderer's pipeline and sweeper, file storage and the download and thumbnail routes ROUTE-226 and ROUTE-227 (TASK-022); the numbering service and its counters (TASK-021)
- each layout's subject, record-bound precondition and payload, and the issuing pages' "Terbitkan …" controls with ROUTE-155, ROUTE-182, ROUTE-187, ROUTE-202, ROUTE-242, ROUTE-249 and ROUTE-250 (the issuing Tasks)
- external and uploaded documents (TASK-030); requirements and their links (TASK-035)
- validating each layout's content — wording, clauses, the signatory — with the Owner and the validators (GAP-019; DEP-04; TASK-086)

**REFERENCES:**

- [WORKFLOWS §5.5](../03-workflows/WORKFLOWS/s05-04-05-fulfillment-documents.md#wf-doc-01--generated-document-lifecycle-desk) WF-DOC-01; [§6](../03-workflows/WORKFLOWS/s06-07-subflows-partial.md#6-reusable-subflows) SF-ISSUE, SF-RENDER, SF-REVAL; [§8](../03-workflows/WORKFLOWS/s08-09-corrections-indivisible.md#8-correction-reversal-and-cancellation-matrix) CM-26; [§9](../03-workflows/WORKFLOWS/s08-09-corrections-indivisible.md#9-indivisible-business-actions) AX-15; [§12](../03-workflows/WORKFLOWS/s10-14-signals-decisions-traceability.md#12-planner-determined-level-1-decisions) L-19, L-26, L-41
- [BUSINESS_RULES SM:Document](../02-domain/BUSINESS_RULES.md#smdocument-all-doc-0114-invoice-is-a-specialization) BR-DOC-01–BR-DOC-04; [§1](../02-domain/BUSINESS_RULES.md#historical-immutability-and-snapshots) BR-SN-01–BR-SN-03; [§2](../02-domain/BUSINESS_RULES.md#2-correction-taxonomy) BR-CR-06; [§6](../02-domain/BUSINESS_RULES.md#6-cross-domain-interaction-rules) BR-XD-04
- [V1_SCOPE](../01-product/V1_SCOPE.md#selectable-document-catalog) DOC-01–DOC-14; [CAP-08](../01-product/V1_SCOPE.md#must-ship); [DEP-04](../01-product/V1_SCOPE.md#dependencies-and-evidence-needed); [ACCEPTANCE_CRITERIA](../01-product/ACCEPTANCE_CRITERIA.md#product-acceptance-contracts) AC-08, AC-12; [PRODUCT_OVERVIEW](../01-product/PRODUCT_OVERVIEW.md#problems-and-operational-outcomes) O-01, O-03
- [REFERENCE_COVERAGE](../01-product/REFERENCE_COVERAGE.md#pdf-capability-inventory) REF-032–REF-036, REF-047, REF-049, REF-057, REF-058; [IMG-06](../01-product/REFERENCE_COVERAGE.md#image-and-non-tabular-pdf-inventory)
- [DATABASE §4.10](../04-architecture/DATABASE/s04-10-doc.md#410-doc--documents--evidence); [§10](../04-architecture/DATABASE/s07-10-representations.md#10-document-architecture); [§14](../04-architecture/DATABASE/s13-17-history-scope-types.md#14-snapshot-matrix); [§18](../04-architecture/DATABASE/s18-19-constraints-integrity.md#18-constraint-catalogue) C-10–C-12, C-54
- [ARCHITECTURE §3](../04-architecture/ARCHITECTURE.md#3-modules) `DocumentSubjectProvider`, `DocumentChangeParticipant`; [§11](../04-architecture/ARCHITECTURE.md#11-queue-and-background-jobs)
- [CONCURRENCY_IDEMPOTENCY §7.1](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s07-statement-sequences.md#71-lock-sets-of-ax-01ax-37) and [§7.2](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s07-statement-sequences.md#72-checks-effects-and-outcomes-of-ax-01ax-37) AX-15; [§7.3](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s07-statement-sequences.md#73-command-families-outside-ax-0137) NX-12; [§8](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s08-09-guards-stale-state.md#8-guards-and-first-use-rows) FU-07, FU-12; [§11](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s10-13-identity-failure-retry.md#11-separate-duplicates) BD-06; [§14](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s14-17-numbering-jobs-revocation.md#14-numbering-concurrency) NM-01–NM-10; [§15](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s14-17-numbering-jobs-revocation.md#15-jobs-scheduler-and-queue) JB-01, JB-02; [§18](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s18-scenarios.md#18-mandatory-concurrency-and-failure-scenarios) M-15–M-19, M-31, M-38
- [PERMISSIONS_MATRIX §7.2](../05-security/PERMISSIONS_MATRIX.md#72-table-classification) rows 86–90; [§7.3](../05-security/PERMISSIONS_MATRIX.md#73-special-projections) PJ-15, PJ-19; [§11](../05-security/PERMISSIONS_MATRIX.md#11-data-path-coverage-matrix) DP-05, DP-17, DP-19
- [SECURITY §4](../05-security/SECURITY.md#4-web-and-input-security) WS-07, WS-08; [§5](../05-security/SECURITY.md#5-file-security) FL-07, FL-08
- [ROUTE_CONTRACTS](../03-workflows/ROUTE_CONTRACTS.md#documents-files-and-evidence-doc-pg-035-pg-036-pg-038-wf-doc-01-wf-doc-02) ROUTE-221–ROUTE-225; [§2](../03-workflows/ROUTE_CONTRACTS.md#2-page-views--route-001route-065) ROUTE-035, ROUTE-036; [§4](../03-workflows/ROUTE_CONTRACTS.md#4-visible-controls-without-a-route-of-their-own) the draft's live preview
- [INTERACTION_CONTRACTS](../03-workflows/INTERACTION_CONTRACTS.md#documents-files-and-evidence-doc-pg-035-pg-036-pg-038-wf-doc-01-wf-doc-02) INT-221–INT-225; [§2](../03-workflows/INTERACTION_CONTRACTS.md#2-shared-interactions--int-001int-020) INT-012
- [NAVIGATION_CONTRACTS §2.4](../07-ux-design/NAVIGATION_CONTRACTS.md#24-fulfilment-cases-and-documents) NAV-035, NAV-036; [INFORMATION_ARCHITECTURE §8.4](../07-ux-design/INFORMATION_ARCHITECTURE.md#84-fulfilment-cases-and-documents) PG-035, PG-036
- [DESIGN_SYSTEM §4](../07-ux-design/DESIGN_SYSTEM.md#4-design-decisions) D-DS-20, D-DS-23; [§10](../07-ux-design/DESIGN_SYSTEM.md#10-component-composition) the components Document composer and paper and Print view; [§14.3](../07-ux-design/DESIGN_SYSTEM.md#143-document-preview); [§17](../07-ux-design/DESIGN_SYSTEM.md#17-print-views); [§9.5](../07-ux-design/DESIGN_SYSTEM.md#95-dashboard-records-and-media) PT-22; [Annex B](../07-ux-design/DESIGN_SYSTEM.md#annex-b-the-carried-items-gr-01gr-23) GR-10
- [DOCUMENT_PREVIEW_PRODUCER §3](../07-ux-design/stage2/DOCUMENT_PREVIEW_PRODUCER.md#3-the-recommendation) and [§4](../07-ux-design/stage2/DOCUMENT_PREVIEW_PRODUCER.md#4-what-p8-must-test); [OQ-22](../07-ux-design/stage2/OWNER_QUESTIONS.md#oq-22--documents-issued-from-recorded-facts) as `DIR-081` OB-02 decided it
- [ADMIN_FLOW J-DOC-01](../07-ux-design/ADMIN_FLOW/s10-b-journeys.md#105-documents-and-administration); [J-FIN-08](../07-ux-design/ADMIN_FLOW/s10-b-journeys.md#106-finance); [IP-08](../07-ux-design/ADMIN_FLOW/s05-command-model.md#ip-08--confirming-an-irreversible-command); [IP-12](../07-ux-design/ADMIN_FLOW/s06-09-session-forms-lists.md#ip-12--derived-background-work); [§12.3](../07-ux-design/ADMIN_FLOW/s11-13-corrections-numbering-import.md#123-the-c-10-refusal-for-an-admin); [§15](../07-ux-design/ADMIN_FLOW/s14-15-messages-scenarios.md#command-identity-and-outcomes) UXS-09; [UXS-38](../07-ux-design/ADMIN_FLOW/s14-15-messages-scenarios.md#workflow-presentations); [§3](../07-ux-design/ADMIN_FLOW/s01-04-foundations.md#3-derived-interaction-requirements) UXI-29
- [LOCALIZATION §3.4](../07-ux-design/LOCALIZATION.md#34-what-never-translates); [§6.5](../07-ux-design/LOCALIZATION.md#65-documents--the-document-names-and-the-draft-marking)
- [TEST_STRATEGY §9](../08-testing/TEST_STRATEGY.md#9-the-uiux-consistency-gate) the document composer and preview; [§7](../08-testing/TEST_STRATEGY.md#7-browser-end-to-end-tests-and-critical-journeys) CJ-15; [SECURITY_TEST_MATRIX](../08-testing/SECURITY_TEST_MATRIX.md#3-the-matrix) SM-10, SM-25; [PERFORMANCE_TARGETS §2](../08-testing/PERFORMANCE_TARGETS.md#2-the-query-budgets) QB-12, QB-16, QB-17; [§3](../08-testing/PERFORMANCE_TARGETS.md#3-the-measured-targets) PFM-16
- [GAP_REGISTER](../00-governance/GAP_REGISTER.md#gap-007--an-issued-database-document-may-have-no-matching-pdf) GAP-007; [GAP-019](../00-governance/GAP_REGISTER.md#gap-019--complete-selectable-documents-need-bounded-coverage-and-validated-content); [GAP-053](../00-governance/GAP_REGISTER.md#gap-053--a-draft-documents-preview-and-print-have-no-defined-producer)
- Architecture challenge — the sequences 1b, 4b, 7a, 7b, 15a, 15b, 15c, 17a of the [P11 gate record](evidence/P11_QUALITY_GATE.md#whole-system-architecture-challenge-sp-98), their assertions carried into this Task's acceptance criteria and tests
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (95): O-01; O-03; CAP-08; DOC-01–DOC-14; DEP-04; AC-08; AC-12; REF-032–REF-036; REF-047; REF-049; REF-057–REF-058; IMG-02; IMG-06; OWN-01; BR-SN-01–BR-SN-03; BR-DOC-01–BR-DOC-04; WF-DOC-01; SF-ISSUE; SF-RENDER; CM-26; AX-15; L-19; L-26; ROUTE-035–ROUTE-036; ROUTE-221–ROUTE-225; INT-012; INT-221–INT-225; C-10–C-12; NX-12; BD-06; M-15–M-19; M-31–M-32; M-38; PG-035–PG-036; SCR-34–SCR-35; UXI-29; J-FIN-08; J-DOC-01; UXS-01–UXS-02; UXS-09; UXS-38; D-DS-23; PT-22; DA-06–DA-07; GR-10; NAV-035–NAV-036; component Document composer and paper; component Print view; GAP-007; GAP-019; GAP-053

**EXPECTED MODULES / FILE AREAS:**

- the DOC module's actions SaveDocumentDraft, IssueDocument, ReviseDocument and VoidDocument, its `Contracts/` for the two inversion contracts, its query classes and `ShowDocuments`, `ShowDocument` and `ShowRendition` (RC-02; ARCHITECTURE §4); the template set read by the composer and the renderer; `resources/js/Pages/DOC` and the composer component in `resources/js/features/doc`; `tests/Feature/DOC` and `tests/Concurrency/`

**BUSINESS RULES:**

- BR-DOC-01–BR-DOC-04 and BR-SN-01–BR-SN-03: an issued version is an immutable snapshot of content, identity assets and number; revision is a new version, void retires the number; rendering is derived and retryable and never re-issues; one business record, many layouts
- BR-XD-04 with FS-03, FS-04 and FS-06: generating or printing any layout changes no stock, cash or obligation, settles nothing and never renumbers or reuses a number (AC-08; architecture challenge 4b)
- FS-12: a company draft never stands in for a required client-issued original, and no external document is generated (WF-DOC-02; GAP-019); BR-CR-06: a document is edited only as a draft

**ARCHITECTURE RULES:**

- issue is a business transaction, rendering derived work: the PENDING rendition row is the durable record of work owed, so a crash after commit loses nothing (DATABASE §10; GAP-007); no financial or stock computation reads a payload — amounts come from the business records it rendered (DATABASE §10)
- DOC is tier 1 and never calls a higher tier: the subject through `DocumentSubjectProvider`, the consequences of a void, a revision's issue or an evidence replacement through `DocumentChangeParticipant`, inside DOC's transaction (ARCHITECTURE §3)
- one template per layout, one source for the composer and the renderer — the renderer of TASK-022 rendering it with JavaScript disabled, as FL-08 requires, verified at implementation (DOCUMENT_PREVIEW_PRODUCER §3 item 1)

**SECURITY / PERMISSION REQUIREMENTS:**

- ROUTE-221 and ROUTE-222 `documents.issue`, whose prerequisite is the subject family's view capability — DOC-01 `quotation.manage`, the invoice layouts `invoice.issue` (PERMISSIONS_MATRIX §4) —; ROUTE-223 and ROUTE-224 `documents.correct` (ADM_PLUS); the views by family (PJ-15); ROUTE-225 authorized at request time, never by a signed link alone (FL-07; DP-05)
- the renderer reads only the immutable payload and allowlisted assets in system context (DP-17; FL-08); document content cannot reach a file or a network address and stored XSS never executes (SM-10)
- a draft's props carry no stamp or signature file, no number and no field above the viewer's projection, partial reloads included (PJ-19; DC-4; DP-15; WS-07)

**COMPANY SCOPE AND FIELD PROJECTION:**

- a document belongs to its subject's company: the command's company is the document's (CS-03), the subject and the bank account bound to it by composite keys (C-32; DATABASE §10)
- PJ-15: metadata — type, number, state, dates — with `projects.view`; payload and rendition by family within the document's company — finance DOC-01, DOC-04–DOC-07, DOC-09, DOC-11, DOC-13 `finance.view`; cost DOC-02, DOC-14 `cost.view`; operational DOC-03, DOC-08, DOC-10, DOC-12 `projects.view`; the version's bank account FV(c) (PERMISSIONS_MATRIX §7.2 rows 87–89); the identity assets per PJ-19; `file_objects` only through the owning record (row 90)
- PG-035 reads one company at a time with the scope filter inside the query, before the number lookup, ranking and pagination (CS-07; PF-13; PF-22); a number of another company's document is never shown (PJ-23)

**CONCURRENCY / IDEMPOTENCY:**

- AX-15, DATA-DEPENDENT, lock set LK-00, LK-01 (the subject's project and the projects of the requirements linked to the version), LK-03, LK-06 (a new chain of a non-collection document), LK-21 (a purchase subject), LK-25 (an invoice or a Kuitansi), LK-30 (the document, then the version, then the rendition), LK-31 (the counter, last — NM-01); first-use rows FU-07 and FU-12; re-read under lock: the version DRAFT, the record-bound precondition, the company active, NM-03's period rule; stale checks ST-01, ST-02, ST-08; refusals C-43, C-10, C-54; retry class A (CONCURRENCY_IDEMPOTENCY §7.1–§7.2)
- BD-06: the version is locked before the counter, so a second issue of one draft finds it ISSUED and is CONFLICT with the issued number, consuming none (M-19); N concurrent issues draw N distinct consecutive numbers (M-15, M-16); a rolled-back issue consumes nothing (M-17); a backdated issue draws its period's next number or is REJECTED in an unseeded period (M-18; NM-05)
- NX-12 (LK-00, LK-01, LK-03, LK-21, LK-25, LK-30): draft edits by `lock_version` on the version (ST-01; M-38), a revision's draft and a void, data-dependent through the linked requirements; "Terbitkan" pressed twice or retried after a lost answer replays one `command_id` and issues once (CI-01–CI-09; architecture challenge 15c; UXS-01; UXS-02)

**AUDIT:**

- document issue and void are critical audit areas (BR-XC-01): every draft save, issue, revision and void writes its audit event in its transaction with the individual actor, the authority class, the version and its number, and for a revision or void its reason and linkage to the predecessor (LG-01; BR-CR-01); the version records `issued_by`, `issue_command_id` and `voided_by`, `voided_at`, `voided_reason` (DATABASE §4.10); an ADM_PLUS correction reaches the Owner's review queue (AZ-10; QS-14)

**DATABASE IMPACT ASSESSMENT:**

- Schema change: NONE — uses the tables of TASK-005–TASK-009; a missing column is an executor discovery (AGENT_OPERATING_MODEL)
- Migration required: YES — a data migration seeding the fourteen rows of `document_types` (DIR-089 (3)), expand-only
- Existing data transformation: NONE
- Direct production DB access: FORBIDDEN
- Data-loss risk: NONE KNOWN

**MODULARITY / EXTENSIBILITY:**

- Module boundary affected: DOC, tier 1, with its two inversion contracts implemented by PRJ, PUR, INV, FUL, FIN and ADM (ARCHITECTURE §3)
- Stable core kept provider-neutral: YES
- External/vendor-specific code isolated: YES — the renderer behind TASK-022's small interface; the templates hold no renderer-specific logic beyond the shared print style
- Replaceable adapter/contract required: YES — `DocumentSubjectProvider` and `DocumentChangeParticipant`, the two contracts ARCHITECTURE §3 already names, nothing more
- Provider lock-in risk: NONE beyond the Owner's baseline stack and TASK-022's licence-free renderer (DEP-07)
- Future extension impact: MEDIUM — every issuing Task adds its subject through the contract; the fourteen types stay the bounded catalogue (V1_SCOPE)
- Speculative abstraction avoided: YES — no template editor, no client-specific variants, no electronic signature (V1_SCOPE selectable document catalog)

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

- Context7: REQUIRED — the renderer TASK-022 chose, rendering HTML and CSS with JavaScript disabled, A4 page boxes, repeated headers and fonts from the origin; the framework's dispatch after commit
- Graphify: OPTIONAL — once the issuing modules exist
- Browser E2E: REQUIRED — J-DOC-01 and CJ-15 at the widths of TEST_STRATEGY §9 and on the phone
- Other: a synthetic subject per layout until its issuing Task supplies the real one; a comparison of the composed draft with the issued rendition within P8's tolerance (DOCUMENT_PREVIEW_PRODUCER §4)
- Missing safe tool behavior: AUTO-INSTALL project-locally inside this Task (AICWDF §6.3) and record it in TOOLCHAIN; a paid tier or a privileged installation STOPS for the Owner (COST_POLICY)

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: refreshed before the change when Graphify is available ([TOOLCHAIN](../00-governance/TOOLCHAIN.md)); otherwise a targeted search of DOC, ORG's numbering service and the renderer
- Pre-change questions: which modules implement the two contracts; which pages open PG-036 with a prepared draft and which route issues each (OB-02)
- Expected affected areas: DOC; TASK-021's numbering service and TASK-022's renderer called, never changed

**CONTEXT7:**

- Topics to verify: the renderer's support for `@page`, page breaks and table headers, embedded fonts from the application's origin; print media in the supported browsers (P8Q-02)
- Version-sensitive assumptions: the renderer's behaviour with JavaScript disabled re-verified at implementation, never assumed (DOCUMENT_PREVIEW_PRODUCER §3 item 1)

**UI / UX REQUIREMENTS:**

- Follow global design rules: YES
- Indonesian default: YES
- English support: YES — for the pages and the composer's controls; the paper stays Indonesian (LOCALIZATION §3.4)
- Plain-language Indonesian: YES — MSG-05, MSG-08, MSG-17, MSG-39, MSG-40 and the documents' keys of LOCALIZATION §6.5 as written
- Language switch impact: the controls switch, the paper never does; unsent input kept (LOCALIZATION §3.7)
- Mobile-first: YES — PG-035 `[both]`; PG-036 `[desk]`, its draft on the phone in DC-11's three steps (NAV-036)
- Responsive: YES
- Adaptive: YES — the desktop split from 1,025 px, the tablet's "Rincian | Pratinjau" below it, the phone's Edit → Preview → Finalize (D-DS-23; DC-11)
- Visual breathing room: REQUIRED
- Comfortable density: REQUIRED
- shadcn/ui reuse: the composer, custom on Card, Tabs or Toggle Group and Dialog (DESIGN_SYSTEM §10); the confirmation (PT-26); dialogs (PT-16); the data table (PT-05); the inline outcome (PT-14); previews (PT-22)
- Design reference: PG-035 and PG-036 as INFORMATION_ARCHITECTURE §8.4 states them; the composer as D-DS-23 and DESIGN_SYSTEM §14.3 define it; the Golden Frontend Reference for presentation only (DIR-061 SD-02)
- Primary action: on a draft, "Terbitkan {jenis}" through its confirmation, "Simpan Draf" before it
- Secondary actions: "Revisi", "Batalkan Dokumen", "Unduh", "Cetak"; the preview's "Pas lebar", "100%" and "Layar penuh"
- Destructive actions: "Batalkan Dokumen" with its reason, the dialog saying the void never touches the business record (J-DOC-01)
- Redundancy constraints: one issue control per draft; the preview part of the page from 1,025 px, never a tab (D-DS-23); no re-issue control on a failed rendition (UXS-09)
- Loading state: PT-19; the rendition's PENDING state with MSG-05 (IP-12)
- Empty state: PG-035 without documents says so in one sentence (PT-19)
- Error state: MSG-17 for an Admin and the numbering link for the Owner when the period has no counter (ADMIN_FLOW §12.3); BD-06's CONFLICT with the issued number; MSG-08 for a failed rendition; CONFLICT on a stale draft (IP-03)

**ROUTE IMPACT:**

- ROUTE-035 (PG-035's view), ROUTE-036 (PG-036's view); ROUTE-221 (save a draft), ROUTE-222 (issue), ROUTE-223 (revise), ROUTE-224 (void), ROUTE-225 (the rendition inline); the client-only draft preview (ROUTE_CONTRACTS §4); ROUTE-226 and ROUTE-227 stay TASK-022's

**INTERACTION CONTRACTS:**

- INT-012; INT-221–INT-225; INT-014 for the download (with TASK-022)

**NAVIGATION CONTRACT:**

- Parent: PG-036 — PG-035 · PG-011 (NAV-036); PG-035 — *Menu lengkap* › *Dokumen* (NAV-035)
- Entry: *Menu lengkap* › *Dokumen*; "Semua dokumen"; document readiness; the document list; the "Terbitkan …" controls of PG-016, PG-022, PG-024, PG-031 and PG-043 with the draft they prepare (`DIR-081` OB-02)
- Back action: "Kembali" → the origin (NAV-035, NAV-036; NV-01)
- Cancel: "Batal" on the draft → the origin, MSG-35 first with unsent input; the confirmation's "Kembali" → the draft, the input kept (NV-02)
- Success destination: PG-036 with the issued version, its number and the rendition's state (NAV-036)
- Error behavior: NV-04 — validation at the field, a rejected command in place, the input kept
- State preservation: unsent input kept across re-login; the list's filters, sort and cursor in the address (NV-05)
- Mobile behavior: PG-035 `[both]`; PG-036 Edit → Preview → Finalize full-screen on the phone, DOC-12's floor step included (DC-11; AN-6)

**ACCEPTANCE CRITERIA:**

1. Issuing a draft draws the next number of its company, type and business-date period, freezes the payload with the company identity, the bank destination and the number, and writes one PENDING rendition in one transaction; a later change of the company, client, unit, PIC, product or price changes no issued version (AX-15; BR-DOC-01; BR-SN-01; C-11; architecture challenge 1b).
2. Two concurrent issues of one draft give one issued version and one CONFLICT naming the issued number, consuming one number; N concurrent issues of different drafts in one company, type and period draw N distinct consecutive numbers (BD-06; M-15; M-19; REF-036).
3. "Terbitkan" pressed twice, or retried with the same `command_id` after a lost answer, returns the recorded issue and issues once (CI-05; REF-058; architecture challenge 15c).
4. A rendition that fails or whose worker crashes is retried in the background from the issued row; it never re-issues, renumbers or changes the version, and the page shows *Terbit* with its number and MSG-08 (C-12; NM-07; GAP-007; UXS-09).
5. A revision is a new version linked to its predecessor, which stays retrievable with its own rendition, also while the predecessor's rendition is pending; a void retires the version and its number forever, keeps its reason, actor and link, and never changes the business record (BR-CR-06; C-11; C-12; AC-12; REF-035; architecture challenge 7a).
6. Voiding or replacing a version that satisfies a requirement re-evaluates that requirement and revalidates a completed project inside the same transaction (L-41; BR-ADM-03; SF-REVAL; architecture challenge 7b).
7. For each layout, a draft composed from fixed data and then issued gives a rendition whose blocks, lines, totals and page breaks equal the preview's within P8's tolerance, only the marking, the number line and the signature block differing (DOCUMENT_PREVIEW_PRODUCER §4; architecture challenge 15a).
8. A draft's preview and print carry "DRAF — belum terbit" on every page, write no rendition, file, number, counter or version, and never satisfy a requirement or a completion predicate (D-DS-20; GAP-053; architecture challenge 15b).
9. The composer receives only the fields the viewer's family and PJ-19 allow — never the stamp or signature files —, and its totals are labelled "perkiraan" until a save returns the server's, which the issued rendition shows (PJ-15; PJ-19; UXI-14).
10. The paper stays Indonesian when the interface is in English (DIR-037 D7; architecture challenge 17a).
11. From 1,025 px the form and the live preview sit side by side — at 1,025, 1,279 and 1,280 px every input reachable and the paper readable through "100%" and "Layar penuh" —, at 1,024 px "Rincian | Pratinjau" applies, and on the phone Edit → Preview → Finalize (D-DS-23; INT-012).
12. Generating, issuing or printing any of DOC-01–DOC-14 changes no stock, cash, settlement or obligation (BR-XD-04; FS-03; FS-04; AC-08; architecture challenge 4b).
13. An issue in a period whose numbering has not started is REJECTED — MSG-17 for an Admin, the numbering link for the Owner —, and so is an issue that starts a new chain of a non-collection document of a deactivated company (C-10; C-54; BR-ACC-03).
14. A payload or rendition reaches only its family's view capability within the document's company, and PG-035's list and number lookup never show a document outside scope (PJ-15; CS-07).
15. Each issue, revision and void commits all its effects or none and writes its audit event in the same transaction (REF-049; REF-057; LG-01).
16. The fourteen rows of `document_types` exist with only the values DIR-089 (3) states — each name, numbering flag, mode set and subject as the approved V1 text determines it —, each type's subject agreeing with the subject arc `documents` enforces, proven by an integration test.

**TEST REQUIREMENTS:**

- Lint: TL-01
- Typecheck: TL-02
- Unit: the period key and number format per scheme; the provisional totals against the server's (TL-03)
- Feature: ROUTE-035, ROUTE-036 and ROUTE-221–ROUTE-225 with each refusal — C-10, C-43, C-54, a stale `lock_version`, BD-06 (TL-04)
- Integration: M-15–M-19 and M-38 on real PostgreSQL; a crash after the issue's commit and before dispatch, and a render retry, never reissuing (GAP-007, with TASK-022's M-31 and M-32); `DocumentChangeParticipant` inside a void's transaction; the fourteen `document_types` rows against V1_SCOPE's catalogue and each type's subject arc (DIR-089 (3)) (TL-05)
- Authorization: `documents.issue` and `documents.correct` with and without each family's view capability; DOC-01 by `quotation.manage` (TL-06)
- Route integrity: ROUTE-035, ROUTE-036 and ROUTE-221–ROUTE-225 audited (TL-07)
- Interaction: INT-012 and INT-221–INT-225, each command with one `command_id` replayed (TL-08; IG-05)
- Browser E2E: J-DOC-01 and CJ-15 — a draft prepared from recorded facts and opened on PG-036, the confirmation, the issue, the rendition, a failed rendition — at 1,024, 1,025, 1,279 and 1,280 px and at 390 px (TL-09; TEST_STRATEGY §9)
- Localization/language switch: both languages on PG-035 and PG-036, the paper Indonesian in both (TL-10)
- Visual breathing room/density: TEST_STRATEGY §9, with its document composer and preview checks
- Responsive: TL-11 at every band edge
- Accessibility: TL-12 — the paper a named region, never a live region, after the form in reading order, its scrolling frame reachable by keyboard, focus never obscured (DESIGN_SYSTEM §15)
- Production build: TL-13
- Security: SM-10 for the renderer and the templates — no file or network access from content, no stored XSS in pages or PDFs —, SM-25 for the payloads, the draft props and the renditions (TL-14)
- Performance: QB-12 for PG-035; QB-16 for the rendition's request; QB-17 and PFM-16 — issue to READY within 30 s at 1x; PFM-20 for the commands (TL-15)
- Migration: N/A — no migration or import code in this Task
- Other: DOCUMENT_PREVIEW_PRODUCER §4's seven tests with P8's tolerance recorded; GAP-019 — each layout's sample listed for the Owner's and the validators' acceptance (DEP-04)

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

- TASK-033 — when its other dependencies are DONE and its READY gate holds

**DO NOT DO:**

- add to V1 — a capability, page, route, field, integration, document type, report or paid service — or drop, defer or narrow a V1_REQUIRED requirement; a Task that would need either raises an Owner question and waits ([DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-20)
- access the VPS, production, its data or a credential, or put a secret, real data, a host detail or production configuration in the repository ([INFRASTRUCTURE](../09-operations/INFRASTRUCTURE.md) INF-01, INF-05; SECURITY SX-01, SX-04, SX-06; [PRODUCTION_DATA_SAFETY](../00-governance/PRODUCTION_DATA_SAFETY.md))
- add AI, model or tool attribution to a commit — the Owner's configured identity only ([AGENTS.md](../../AGENTS.md#git-commit-attribution); DIR-022)
- start before the Owner's execution authorization at OG-4, or mark DONE without the required tests passing and the completion evidence ([TASK_PLAN](TASK_PLAN.md#2-rules-of-the-plan); [DEFINITION_OF_DONE](../08-testing/DEFINITION_OF_DONE.md#2-the-checklist))
- weaken, skip or delete a valid test to obtain a green run, or adopt a paid tier without the Owner's COST_POLICY decision (DOD-05; [COST_POLICY](../00-governance/COST_POLICY.md))
- render a draft on the server, write a rendition, file, number or counter for a draft, or let a draft satisfy a requirement (D-DS-20; GAP-053)
- keep a second copy of a layout for the renderer or the composer, or re-issue or renumber to repair a rendition (BR-DOC-03; GAP-007)
- edit an issued version, compute a number as `MAX(number)+1`, or generate an external document or a client's original (C-11; NM-10; FS-12)
- add a template editor, a client-specific variant or an electronic signature (V1_SCOPE selectable document catalog)

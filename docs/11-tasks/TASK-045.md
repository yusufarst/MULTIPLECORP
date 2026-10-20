# TASK-045 — Deliveries (Pengiriman) and delivery closure

Status: APPROVED | Updated: 2026-10-20 | Owner: Planning

Approval: [APPR-016](../00-governance/DECISION_LOG.md#appr-016--p11-task-plan-tasks-and-feature-coverage-matrix-approved), explicit Owner approval on 2026-10-20 (DIR-085 OF-07) of this document as committed in the first execution session's approval commit; the approved file hash and the verified conditions are recorded there. Approval changes lifecycle only, not implementation authorization.

Written by WF-PC5-W's writer of bundle W2 of run PC-5 on 2026-10-19 and 2026-10-20 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99; the Owner approved P11 with the [Task plan](TASK_PLAN.md) at OG-4 (DIR-085 OF-07). **No Task starts before the freeze record, from which the execution authorization is in force (DIR-085 OF-09).** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** TODO

**RISK CLASS:** HIGH

**EXECUTOR:** AGENT

**OBJECTIVE:**

Record what reached the client against what was shipped: the delivery per shipment and line with recipient, date, condition and proof, a difference or condition problem opening its discrepancy case in the same form, the optional inspection facts and the BAST or inspection record drafted from them and issued, the formal closure of shipped quantity that will not be delivered — refused or returned undelivered, or lost in transit with its loss reclassification —, and the entry-error correction of a delivery record, on PG-030 *Pengiriman* and PG-031 *Catat Pengiriman* (WF-FUL-02; AX-09; NX-15; ROUTE-201–ROUTE-204).

**WHY THIS EXISTS:**

CAP-07 and AC-07 require partial and full deliveries with recipient, condition, proof and visible discrepancies, a failed or repeated submit that never double-delivers, and dispatch and delivery that never subtract the same stock (BR-INV-02; FS-03; REF-031; IMG-05). Dispatch and delivery stay two linked records (D-IA-16): the Surat Jalan renders the outbound shipment and its signed copy is the delivery proof (L-19). A shipment refused or lost after dispatch must end by an explicit closure, its goods restored once, never twice (architecture challenge 3b), and a serial substituted after dispatch must carry its delivery and documents with it (3c). The client return (TASK-048) and the completion predicates (TASK-055) read the net delivered quantity this Task records (PX-08).

**DEPENDS ON:**

- TASK-029 — Document engine — drafts, issue, revision, void, renditions and the composer
- TASK-041 — Dispatch (Barang Keluar) and inter-company allocation

**BLOCKS:**

- TASK-048 — Client returns (sales return)
- TASK-055 — Project completion and Force Complete

**PARALLEL SAFE:** NO — the delivery and its closure write the project-line guard (LK-19) that dispatch, drop-ship, handover and returns post, and a loss in transit writes the consumption and lot-cost guards of TASK-041's dispatch

**IN SCOPE:**

- the FUL records of DATABASE §4.9 in use: `deliveries` with recipient, business date and condition note, `delivery_lines` against a dispatch line or a drop-ship confirmation line, each reversed at most once and exactly (`reverses_delivery_line_id`), and `delivery_closures` of kind LOST_IN_TRANSIT, RETURNED_UNDELIVERED or REFUSED with reason, case and `supersedes_id`; deliveries never touch stock (BR-INV-02)
- ROUTE-201 with INT-201 (`FUL RecordDelivery`; `delivery.record`; AX-09): PG-031 opened by the journey's primary action or the QS-06 row; delivered quantity per line ≤ net shipped − net delivered (PX-03; PX-08; C-30), the recipient's name, business date, condition and proof — the signed Surat Jalan or a photo uploaded first (IP-16) and linked as DELIVERY_PROOF evidence (L-19; PJ-08) —; a difference or condition problem opening its DELIVERY_DISCREPANCY case in the same form (SF-CASE); the optional inspection facts from the client's inspection; the likely-duplicate warning of a fungible event (L-49; BD-13; INT-015; UXS-15); repeated and partial deliveries (PX-01; CALC-04)
- ROUTE-202 with INT-202 (`DOC IssueDocument` on PG-031; `documents.issue`; AX-15): "Terbitkan" for DOC-08 BAST — confirmed handover or accepted delivery recorded — or DOC-10 *Berita Acara Pemeriksaan Barang* — inspection facts recorded —, its draft first created or saved again from the recorded facts by TASK-029's ROUTE-221 and opened on PG-036, where the confirmation issues it (`DIR-081` OB-02; WF-DOC-01); this Task supplies their record-bound precondition and payload through `DocumentSubjectProvider` (ARCHITECTURE §3); user-confirmed facts only, never auto-certified by a status or a print (BR-DOC-04)
- ROUTE-203 with INT-203 (`FUL CloseDelivery`; `delivery.close`; NX-15): "Tutup Pengiriman" for shipped quantity that will not be delivered — refused, or returned undelivered (CM-06) —, with its reason; the goods come back through the dispatch's restoration (ROUTE-188; TASK-041), never through the closure; and "Catat Hilang di Perjalanan" on PG-030 with `stock.adjust` (CM-07): the closure for the lost quantity with SF-LOSS's reclassification to the shipment's project in the same action — the dispatch's HPP contra-attributed and a loss of the same amount recorded, the dispatch's allocation kept, net dispatched falling by the lost quantity (PX-08; DIR-024 D-3; annex 15); a closure recorded in error superseded (`supersedes_id`; CM-27)
- ROUTE-204 with INT-204 (`FUL CorrectDelivery`; `fulfillment.correct`; AX-09): "Koreksi Catatan Pengiriman" for an entry error — the exact reversal line, then the correct record —, stock untouched, the form saying so; an issued DOC-08 or DOC-10 that the correction affects revised through TASK-029's revision (CM-08; ADMIN_FLOW §11)
- PG-030 with NAV-030 and ROUTE-030 (SCR-28): shipments not yet delivered (QS-06) and delivery records, the dispatch and the delivery shown as two linked records (D-IA-16); PG-031 with NAV-031 and ROUTE-031 (SCR-29; J-FUL-02)
- after a dispatch's identity substitution (CM-36; AX-35; TASK-041) the delivery's lines and quantities are unchanged and show the serial the shipment actually carried; a BAST or inspection record listing the old serial is revised or kept with its reason, as WORKFLOWS §8 states for record-bound documents (architecture challenge 3c)

**OUT OF SCOPE:**

- the dispatch, its Surat Jalan (DOC-03; ROUTE-187), its reversals and restorations (ROUTE-188) and its identity substitution (ROUTE-189) — TASK-041; the drop-ship confirmation and its DOC-03 (TASK-046); the service handover (TASK-047)
- the client return "Retur dari Klien" that starts from a delivered record on PG-030 (PG-034; ROUTE-210; TASK-048; INFORMATION_ARCHITECTURE §14.3)
- the document engine — drafts, issue, revision, void, renditions (ROUTE-221–ROUTE-225; TASK-029); the upload and evidence routes ROUTE-228–ROUTE-231, which the matrix maps to TASK-030
- the found-goods reversal of a loss in transit, "Catat Barang Ditemukan" (ROUTE-196; TASK-042)

**REFERENCES:**

- [WORKFLOWS §5.4](../03-workflows/WORKFLOWS/s05-04-05-fulfillment-documents.md#wf-ful-02--delivery-pengiriman-both) WF-FUL-02; [§5.5](../03-workflows/WORKFLOWS/s05-04-05-fulfillment-documents.md#wf-doc-01--generated-document-lifecycle-desk) WF-DOC-01's DOC-03, DOC-08 and DOC-10 rows; [§6](../03-workflows/WORKFLOWS/s06-07-subflows-partial.md#6-reusable-subflows) SF-CASE, SF-LOSS, SF-RESTORE, SF-EVIDENCE; [§7](../03-workflows/WORKFLOWS/s06-07-subflows-partial.md#7-partial-processing-rules) PX-01–PX-08; [§8](../03-workflows/WORKFLOWS/s08-09-corrections-indivisible.md#8-correction-reversal-and-cancellation-matrix) CM-06–CM-08, CM-27, CM-36; [§9](../03-workflows/WORKFLOWS/s08-09-corrections-indivisible.md#9-indivisible-business-actions) AX-09, AX-10, AX-15, AX-30, AX-35; [§10](../03-workflows/WORKFLOWS/s10-14-signals-decisions-traceability.md#10-derived-signals-and-action-queue) QS-06, QS-07, QS-15; [§12](../03-workflows/WORKFLOWS/s10-14-signals-decisions-traceability.md#12-planner-determined-level-1-decisions) L-16, L-19, L-26, L-38, L-49
- [BUSINESS_RULES SM:Document](../02-domain/BUSINESS_RULES.md#smdocument-all-doc-0114-invoice-is-a-specialization) BR-DOC-04; [Inventory](../02-domain/BUSINESS_RULES.md#inventory-inv) BR-INV-02, BR-INV-13; [§5](../02-domain/BUSINESS_RULES.md#5-calculation-semantics) CALC-04 and [annex](../02-domain/BUSINESS_RULES.md#numeric-annex-binding-examples-for-p8-fixtures-gap-004) 15; [§6](../02-domain/BUSINESS_RULES.md#6-cross-domain-interaction-rules) BR-XD-04; [§7](../02-domain/BUSINESS_RULES.md#7-forbidden-shortcuts) FS-03
- [V1_SCOPE](../01-product/V1_SCOPE.md#must-ship) CAP-07; [DOC-03, DOC-08, DOC-10](../01-product/V1_SCOPE.md#selectable-document-catalog); [ACCEPTANCE_CRITERIA](../01-product/ACCEPTANCE_CRITERIA.md#product-acceptance-contracts) AC-07; [REFERENCE_COVERAGE](../01-product/REFERENCE_COVERAGE.md#pdf-capability-inventory) REF-031; [IMG-05](../01-product/REFERENCE_COVERAGE.md#image-and-non-tabular-pdf-inventory)
- [DATABASE §4.9](../04-architecture/DATABASE/s04-09-ful.md#49-ful--fulfillment); [§6.1](../04-architecture/DATABASE/s06-lot-cost-hpp.md#61-the-chain) the loss in transit; [§18](../04-architecture/DATABASE/s18-19-constraints-integrity.md#18-constraint-catalogue) C-24, C-27, C-30
- [ARCHITECTURE §3](../04-architecture/ARCHITECTURE.md#3-modules) `DocumentSubjectProvider`, `CompletionPredicateProvider`
- [CONCURRENCY_IDEMPOTENCY §7.1](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s07-statement-sequences.md#71-lock-sets-of-ax-01ax-37) and [§7.2](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s07-statement-sequences.md#72-checks-effects-and-outcomes-of-ax-01ax-37) AX-09, AX-15, AX-30; [§7.3](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s07-statement-sequences.md#73-command-families-outside-ax-0137) NX-15; [§11](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s10-13-identity-failure-retry.md#11-separate-duplicates) BD-06, BD-13
- [PERMISSIONS_MATRIX §4](../05-security/PERMISSIONS_MATRIX.md#4-capability-catalogue) `delivery.record`, `delivery.close`, `fulfillment.correct`, `stock.adjust`, `documents.issue`; [§7.2](../05-security/PERMISSIONS_MATRIX.md#72-table-classification) rows 81–83, 87–89, 91, 95; [§7.3](../05-security/PERMISSIONS_MATRIX.md#73-special-projections) PJ-08, PJ-15; [§2](../05-security/PERMISSIONS_MATRIX.md#2-authorization-model) AZ-08
- [ROUTE_CONTRACTS](../03-workflows/ROUTE_CONTRACTS.md#fulfilment--deliveries-direct-shipments-handovers-and-client-returns-ful-pg-030pg-034) ROUTE-201–ROUTE-204; [§2](../03-workflows/ROUTE_CONTRACTS.md#2-page-views--route-001route-065) ROUTE-030, ROUTE-031
- [INTERACTION_CONTRACTS](../03-workflows/INTERACTION_CONTRACTS.md#fulfilment--deliveries-direct-shipments-handovers-and-client-returns-ful-pg-030pg-034) INT-201–INT-204; [§2](../03-workflows/INTERACTION_CONTRACTS.md#2-shared-interactions--int-001int-020) INT-013, INT-015
- [NAVIGATION_CONTRACTS §2.4](../07-ux-design/NAVIGATION_CONTRACTS.md#24-fulfilment-cases-and-documents) NAV-030, NAV-031, NAV-036; [INFORMATION_ARCHITECTURE §8.4](../07-ux-design/INFORMATION_ARCHITECTURE.md#84-fulfilment-cases-and-documents) PG-030, PG-031; [§4](../07-ux-design/INFORMATION_ARCHITECTURE.md#4-design-decisions) D-IA-16; [§8.7](../07-ux-design/INFORMATION_ARCHITECTURE.md#87-the-approved-screens-scr-01scr-60-and-their-pages) SCR-28, SCR-29
- [ADMIN_FLOW J-FUL-02 and J-FUL-05](../07-ux-design/ADMIN_FLOW/s10-b-journeys.md#104-fulfilment); [§11](../07-ux-design/ADMIN_FLOW/s11-13-corrections-numbering-import.md#11-corrections-and-reversals) CM-06–CM-08; [IP-05](../07-ux-design/ADMIN_FLOW/s05-command-model.md#ip-05--duplicate-warnings); [IP-08](../07-ux-design/ADMIN_FLOW/s05-command-model.md#ip-08--confirming-an-irreversible-command); [IP-09](../07-ux-design/ADMIN_FLOW/s05-command-model.md#ip-09--corrections-closures-and-destructive-actions); [IP-16](../07-ux-design/ADMIN_FLOW/s06-09-session-forms-lists.md#ip-16--evidence-upload); [UXS-15](../07-ux-design/ADMIN_FLOW/s14-15-messages-scenarios.md#duplicates)
- [DESIGN_SYSTEM §12.1](../07-ux-design/DESIGN_SYSTEM.md#121-status-system) the delivery family; [§14.3](../07-ux-design/DESIGN_SYSTEM.md#143-document-preview)
- [TEST_STRATEGY §7](../08-testing/TEST_STRATEGY.md#7-browser-end-to-end-tests-and-critical-journeys) CJ-01, CJ-03, CJ-15; [§5](../08-testing/TEST_STRATEGY.md#5-domain-and-data-integrity); [SECURITY_TEST_MATRIX](../08-testing/SECURITY_TEST_MATRIX.md#3-the-matrix) SM-01, SM-04, SM-09, SM-25; [PERFORMANCE_TARGETS §3](../08-testing/PERFORMANCE_TARGETS.md#3-the-measured-targets) PFM-01, PFM-02, PFM-20
- Architecture challenge — the sequences 3b, 3c of the [P11 gate record](evidence/P11_QUALITY_GATE.md#whole-system-architecture-challenge-sp-98), their assertions carried into this Task's acceptance criteria and tests
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (52): O-02; CAP-07; DOC-03; DOC-08; DOC-10; AC-07; REF-031; IMG-05–IMG-06; BR-DOC-04; WF-FUL-02; PX-01–PX-08; CM-06–CM-08; CM-27; CM-36; AX-09; L-19; L-26; ROUTE-030–ROUTE-031; ROUTE-201–ROUTE-204; INT-201–INT-204; C-30; NX-15; BD-13; D-IA-16; PG-030–PG-031; SCR-28–SCR-29; J-INV-06; J-FUL-02; J-FUL-05; UXS-15; NAV-030–NAV-031; GAP-067

**EXPECTED MODULES / FILE AREAS:**

- the FUL module's actions RecordDelivery, CloseDelivery and CorrectDelivery with `ShowDeliveries` and `ShowRecordDelivery` and their query classes; FUL's `DocumentSubjectProvider` for DOC-08 and DOC-10 and its `CompletionPredicateProvider` part for delivery (ARCHITECTURE §3); the loss reclassification posted through CST's `CostPosting`; `resources/js/Pages/FUL` for PG-030 and PG-031; `tests/Feature/FUL`, `tests/Concurrency/`

**BUSINESS RULES:**

- BR-INV-02 and FS-03: dispatch is the single physical subtraction; a delivery, its correction, its closure and any layout never touch stock (AC-07)
- PX-01–PX-08: deliveries are additive and a zero-delivered line is a valid resting state with no timed closure (PX-02; FS-09); delivered > shipped is REJECTED until the source is amended (PX-03); an undeliverable shipped quantity ends only by an explicit closure with reason and authority (PX-04); a committed partial delivery is never undone implicitly (PX-05); any authorized Admin continues the work (PX-07); net delivered = delivered − returned by the client, a sales return never reverses the delivery record and CM-08 serves entry errors only (PX-08)
- L-19, BR-DOC-04 and BR-XD-04: DOC-03 renders the outbound shipment and its signed copy is the delivery proof; DOC-08 and DOC-10 render user-confirmed facts, never automatic certification; generating a layout changes no stock, cash or obligation
- BR-INV-13, SF-LOSS and L-38: a loss in transit is a reclassification on the shipment's project — HPP contra and a loss of the same amount, the dispatch's allocation kept, never a second cost (annex 15)
- PX-06 with L-16 and L-26: the delivered quantity is what QS-15 compares invoicing with — invoicing above it is flagged, never blocked (TASK-049); L-49: a fungible delivery without an external identity raises the likely-duplicate warning (QS-21)

**ARCHITECTURE RULES:**

- FUL is tier 6: it reads dispatch and drop-ship through INV's and FUL's query classes and posts the line guard through PRJ's `DemandPosting` and a reclassification through CST's `CostPosting` (ARCHITECTURE §3, §7); DOC (tier 1) obtains DOC-08's and DOC-10's precondition and payload through FUL's `DocumentSubjectProvider`, never calling FUL
- a delivery's proof is evidence linked under LK-29 in the delivery's transaction (CONCURRENCY_IDEMPOTENCY §7.1 notes); the file itself arrives first through the upload request (FL-01)

**SECURITY / PERMISSION REQUIREMENTS:**

- ROUTE-030 `projects.view`; ROUTE-031 `delivery.record`, the closure's control only with `delivery.close` and the correction's only with `fulfillment.correct` (RG-06); ROUTE-201 `delivery.record` (ADM); ROUTE-203 `delivery.close` (ADM_PLUS), a loss in transit also `stock.adjust` (ADM_PLUS); ROUTE-204 `fulfillment.correct` (ADM_PLUS); ROUTE-202 `documents.issue`
- every ADM_PLUS command asks its reason (IP-09; MSG-40); a loss on a lot needs its bearer company — the shipment's project's — in scope (AZ-08)

**COMPANY SCOPE AND FIELD PROJECTION:**

- a delivery belongs to its project's company (CS-03); its lines cite only that project's dispatch or confirmation lines (CS-04; C-32)
- rows 81–83: deliveries, lines and closures PV(c); the proof through its evidence link — DELIVERY_PROOF of the project family, visible with `evidence.view` and `projects.view` in scope (PJ-08; row 95: a link visible only when both its evidence and its record are)
- PJ-15: DOC-03, DOC-08 and DOC-10 are the operational family — metadata and payload with `projects.view` within the document's company; an operational layout that would print a price or cost belongs to the finance or cost family instead
- PG-030 lists by branch, every row carrying its company where several are in scope, the scope filter inside the query (CS-07; CS-08); the loss amount of a loss in transit shown only with `cost.view` of the bearer (J-INV-06's rule)

**CONCURRENCY / IDEMPOTENCY:**

- AX-09 for ROUTE-201 and ROUTE-204, STATIC: LK-00, LK-01 (the delivery's project), LK-03, LK-10 (the discrepancy case), LK-19, LK-29 (the proof); re-read under lock: net shipped − net delivered ≥ the quantity; a reversal line exact and unused; guard: the project line; refusals C-30, L-49; identity BD-13 — the likely duplicate by the line, REJECTED L-49 unless the payload carries the actor's reasoned confirmation naming the matches —; retry class A (CONCURRENCY_IDEMPOTENCY §7.1–§7.2; §11)
- NX-15 for ROUTE-203's closure (LK-00, LK-01, LK-03, LK-10, LK-19): it changes the delivery predicate only; the loss in transit commits as AX-30's reclassification pair with its delivery closure in one action — LK-00, LK-01 (the dispatch's project), LK-03, LK-10, LK-13, LK-14, LK-15, LK-17, LK-18, LK-19, LK-23, LK-24, LK-29; a reclassification ≤ the consumption's open quantity (C-27); refusals C-24, C-27, C-06, L-49, AZ-08; retry class A — ROUTE-203 and INT-203 name NX-15 alone for "Catat Hilang di Perjalanan", while CONCURRENCY_IDEMPOTENCY §7.2 places the reclassification with its delivery closure under AX-30 (reported for Planning; the effects and guards stated here are the sources')
- AX-15 for ROUTE-202 as TASK-029 builds it: the version locked before the counter, so a second issue of one draft is CONFLICT with the issued number (BD-06)
- a delivery racing the dispatch's restoration of the same line meets it at LK-01 and LK-19: the first commits, the second is REJECTED against the new net figure, so a unit is never both delivered and restored (C-30; C-27; SF-RESTORE; architecture challenge 3b); one `command_id` replayed returns the recorded outcome and never delivers twice (CI-05; AC-07)

**AUDIT:**

- every delivery, correction line, closure and its supersession is a business audit event with the individual actor, business date, reason where required and links to the shipment, the case and the original line (BR-XC-01; BR-CR-01; LG-01); ADM_PLUS closures, corrections and losses reach the Owner's review queue (AZ-10; QS-14); a likely-duplicate confirmation commits under the acknowledged variant of its action code, listed in QS-21 (CONCURRENCY_IDEMPOTENCY §11); the document's issue is audited by TASK-029's AX-15

**DATABASE IMPACT ASSESSMENT:**

- Schema change: NONE — uses the tables of TASK-005–TASK-009; a missing column is an executor discovery (AGENT_OPERATING_MODEL)
- Migration required: NO
- Existing data transformation: NONE
- Direct production DB access: FORBIDDEN
- Data-loss risk: NONE KNOWN

**MODULARITY / EXTENSIBILITY:**

- Module boundary affected: FUL, tier 6, with DOC (tier 1) through `DocumentSubjectProvider` and CST through `CostPosting` (ARCHITECTURE §3)
- Stable core kept provider-neutral: YES
- External/vendor-specific code isolated: YES — framework facilities or a small interface (ARCHITECTURE §2)
- Replaceable adapter/contract required: NO
- Provider lock-in risk: NONE beyond the Owner's baseline stack
- Future extension impact: LOW
- Speculative abstraction avoided: YES — no generic workflow or rules engine (ARCHITECTURE §1)

**AUTH IMPACT:**

- Authentication change: NONE
- Authentication implementation: LARAVEL_NATIVE — decided once ([EXECUTION_CONTEXT](EXECUTION_CONTEXT.md)), reused, never redesigned
- Auth profile: N/A — this Task does not touch authentication
- Google sign-in: N/A — not touched
- Google registration behavior: N/A — not touched
- Google credentials owner action required: NO
- Authorization change: NONE — uses TASK-011's capabilities and policies as PERMISSIONS_MATRIX states them
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

- Context7: REQUIRED — PostgreSQL partial UNIQUE keys and composite foreign keys over quantity columns, the framework's file upload handling, at the installed versions
- Graphify: OPTIONAL — once FUL's other Tasks exist
- Browser E2E: REQUIRED — J-FUL-02 at the client's site on the phone and at the desk, with the proof photo and a DOC-08 issue through PG-036
- Other: the two-connection harness (TASK-003); the camera or file picker of the supported phones at Layer 3 (TEST_STRATEGY §12)
- Missing safe tool behavior: AUTO-INSTALL project-locally inside this Task (AICWDF §6.3) and record it in TOOLCHAIN; a paid tier or a privileged installation STOPS for the Owner (COST_POLICY)

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: refreshed before the change when Graphify is available ([TOOLCHAIN](../00-governance/TOOLCHAIN.md)); otherwise a targeted search of FUL, the project-line guard's writers and DOC's subject providers
- Pre-change questions: which commands post the project line's net shipped and delivered figures (AX-03, AX-08, AX-09, AX-10, AX-30), and which completion predicate reads the delivery closures
- Expected affected areas: FUL's delivery actions; `DemandPosting`; `CostPosting`'s reclassification; PG-030, PG-031; PG-036's DOC-08 and DOC-10 drafts

**CONTEXT7:**

- Topics to verify: composite foreign keys that pin a reversal's quantity; file references passed from an upload to the command that follows
- Version-sensitive assumptions: none from memory

**UI / UX REQUIREMENTS:**

- Follow global design rules: YES
- Indonesian default: YES
- English support: YES
- Plain-language Indonesian: YES — the form never suggesting that a delivery changes stock (J-FUL-02); "Catat Hilang di Perjalanan" saying that the cost stays on the project and is shown as a loss (ADMIN_FLOW §11); the correction saying stock is untouched
- Language switch impact: keeps unsent input — the recipient, quantities and attached proof
- Mobile-first: YES — `[both]`: at the client's site on the phone, one column with photo proof; list and form on the desktop (NAV-031)
- Responsive: YES
- Adaptive: YES — one column with the camera's proof on the phone, the shipment's lines beside the form on the desktop
- Visual breathing room: REQUIRED
- Comfortable density: REQUIRED
- shadcn/ui reuse: the form layout and multi-line entry (PT-09, PT-10), the evidence upload (IP-16), data table and dense rows (PT-05, PT-06), the confirmation with its reason (PT-26), status badges *Terkirim Sebagian*, *Terkirim*, *Ada Selisih* and *Ditutup* (PT-13), the timeline (PT-18)
- Design reference: PG-030 and PG-031 as INFORMATION_ARCHITECTURE §8.4 and J-FUL-02 state them; the Golden Frontend Reference for presentation only (DIR-061 SD-02)
- Primary action: "Catat Pengiriman"
- Secondary actions: "Terbitkan" DOC-08 or DOC-10; "Tutup Pengiriman"; "Catat Hilang di Perjalanan" on PG-030; "Koreksi Catatan Pengiriman"
- Destructive actions: none — no control deletes a delivery; corrections are exact reversal lines (DATABASE §4.9)
- Redundancy constraints: one delivery record per real receipt, a likely duplicate warned (L-49); the closure offered only for shipped-but-undelivered quantity; no stock control on these pages (FS-03)
- Loading state: PT-19; the upload's progress before the command (IP-16)
- Empty state: no shipment awaiting delivery — one sentence (PT-19)
- Error state: more than shipped REJECTED with the shipped figure (MSG-02); MSG-15 for a likely duplicate; MSG-31 for a refused upload

**ROUTE IMPACT:**

- ROUTE-030 (PG-030's view, GET `/gudang/pengiriman`) and ROUTE-031 (PG-031's view, GET `/gudang/pengiriman/{id}`, the shipment)
- ROUTE-201 (POST `…/{id}/catat`), ROUTE-202 (POST `…/{id}/dokumen`), ROUTE-203 (POST `…/{id}/tutup`), ROUTE-204 (POST `…/{id}/koreksi`) under `/gudang/pengiriman`; each answering RC-05's errors; ROUTE-221 (TASK-029) creates ROUTE-202's draft first, and ROUTE-228 the proof's file

**INTERACTION CONTRACTS:**

- INT-201–INT-204, each with its *Test* as its test (IG-05); INT-013 for the proof's upload; INT-015 for the likely-duplicate second submission of ROUTE-201, ROUTE-203 and ROUTE-204; INT-016 and INT-017 for CONFLICT and FAILED

**NAVIGATION CONTRACT:**

- Parent: PG-030 — *Gudang* › *Lainnya* › *Pengiriman* · PG-011 (NAV-030); PG-031 — PG-030 · PG-011 · PG-007 (NAV-031)
- Entry: QS-06 rows; *Rincian proyek* › *Pengiriman*; *Gudang* › *Lainnya* › *Pengiriman*; the journey's primary action; the work row
- Back action: "Kembali" → the origin
- Cancel: "Batal" → the origin, MSG-35 first with unsent input; the reason step's "Kembali" → the form, the input kept
- Success destination: PG-030 or the origin, the delivery recorded; "Terbitkan" → PG-036 with the BAST's or the inspection record's draft (`DIR-081` OB-02)
- Error behavior: NV-04 — validation at the field, a rejected command in place, the input kept
- State preservation: PG-030's filters, sort, tab and cursor in the address (IP-18); PG-031's unsent input across the re-login, never in browser history
- Mobile behavior: `[both]` — one column with photo proof

**ACCEPTANCE CRITERIA:**

1. For an order of 5 units shipped, a delivery of 2 leaves 3 undelivered; later deliveries match actual quantities, each with recipient, condition and the proof attached, and no delivery, correction or closure changes ON HAND (AC-07; BR-INV-02; FS-03).
2. A delivery of more than net shipped − net delivered is REJECTED with the shipped figure; a failed or repeated submit with one `command_id` delivers once (C-30; PX-03; CI-05; AC-07).
3. A difference or condition problem opens its discrepancy case in the same command, visible in QS-07, never auto-accepted (SF-CASE; AC-07).
4. Closing a refused or undelivered shipment changes no stock; its goods come back once through the dispatch's restoration, capped at net dispatched − net delivered, and a delivery and a restoration racing on one line never both take the same units (NX-15; SF-RESTORE; AX-10; PX-08; architecture challenge 3b).
5. "Catat Hilang di Perjalanan" commits the closure of the lost quantity with the HPP contra and a loss of the same amount on the shipment's project in one action, the project's cost total unchanged, the dispatch's allocation kept and net dispatched lowered by the lost quantity (CM-07; SF-LOSS; annex 15; C-24).
6. "Koreksi Catatan Pengiriman" posts the exact reversal of the wrong line once and the correct record after it, stock untouched, and an issued BAST or inspection record it affects is revised (CM-08; DATABASE §4.9).
7. After a dispatch's identity substitution the delivery's lines and quantities are unchanged and show the serial actually shipped, and a BAST or inspection record listing the old serial is revised or kept with its reason (AX-35; CM-36; architecture challenge 3c).
8. "Terbitkan" opens DOC-08's or DOC-10's draft on PG-036 prepared from the recorded facts; it issues only when its handover, accepted delivery or inspection facts are recorded, and a second issue of the same draft is CONFLICT with the issued number (WF-DOC-01; BD-06; `DIR-081` OB-02).
9. A likely duplicate delivery without a distinguishing reference is REJECTED with the matches until the actor confirms it with a reason, and the confirmation appears in QS-21 (L-49; BD-13; UXS-15).
10. The closure, the loss and the correction are absent without `delivery.close`, `stock.adjust` and `fulfillment.correct`, each asking its reason (AZ-01; IP-09; SM-04).

**TEST REQUIREMENTS:**

- Lint: TL-01
- Typecheck: TL-02
- Unit: the net shipped and net delivered figures under PX-08, with the alternate-unit conversion (TL-03; C-09)
- Feature: ROUTE-201–ROUTE-204 with each refusal; DOC-08's and DOC-10's preconditions through ROUTE-202 (TL-04)
- Integration: a delivery racing a dispatch restoration of the same line; a loss in transit with annex 15's figures and the reconciliation suite after it; a replayed delivery (TL-05; TEST_STRATEGY §5)
- Authorization: `delivery.record`, `delivery.close` (ADM_PLUS), `fulfillment.correct` (ADM_PLUS), `stock.adjust` (ADM_PLUS) and `documents.issue`; a loss whose bearer company is out of scope routed (AZ-08) (TL-06; SM-04)
- Route integrity: ROUTE-030, ROUTE-031, ROUTE-201–ROUTE-204 audited (TL-07; SM-02)
- Interaction: INT-201–INT-204 per IG-05, with one `command_id` replayed; INT-015's fungible confirmation (TL-08)
- Browser E2E: J-FUL-02 on the phone and the desktop; CJ-03's partial delivery; CJ-15's DOC-08 draft and issue from PG-031 (TL-09)
- Localization/language switch: both languages on PG-030 and PG-031; DOC-08 and DOC-10 in Indonesian whatever the interface language (TL-10; LOCALIZATION §3.4)
- Visual breathing room/density: TEST_STRATEGY §9
- Responsive: TL-11
- Accessibility: TL-12
- Production build: TL-13
- Security: SM-01 for another company's shipment; SM-09 for the proof upload's allowlist; SM-25 for the loss amount without `cost.view` (TL-14)
- Performance: PFM-01 and PFM-02 for PG-030 and PG-031; PFM-20 with QB-13 for AX-09, NX-15 and AX-30 (TL-15)
- Migration: N/A — no migration or import code in this Task
- Other: CM-06, CM-07 and CM-08's expected end states asserted (TEST_STRATEGY §5)

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

- TASK-048 — when its other dependencies are DONE and its READY gate holds

**DO NOT DO:**

- add to V1 — a capability, page, route, field, integration, document type, report or paid service — or drop, defer or narrow a V1_REQUIRED requirement; a Task that would need either raises an Owner question and waits ([DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-20)
- access the VPS, production, its data or a credential, or put a secret, real data, a host detail or production configuration in the repository ([INFRASTRUCTURE](../09-operations/INFRASTRUCTURE.md) INF-01, INF-05; SECURITY SX-01, SX-04, SX-06; [PRODUCTION_DATA_SAFETY](../00-governance/PRODUCTION_DATA_SAFETY.md))
- add AI, model or tool attribution to a commit — the Owner's configured identity only ([AGENTS.md](../../AGENTS.md#git-commit-attribution); DIR-022)
- start before the Owner's execution authorization at OG-4, or mark DONE without the required tests passing and the completion evidence ([TASK_PLAN](TASK_PLAN.md#2-rules-of-the-plan); [DEFINITION_OF_DONE](../08-testing/DEFINITION_OF_DONE.md#2-the-checklist))
- weaken, skip or delete a valid test to obtain a green run, or adopt a paid tier without the Owner's COST_POLICY decision (DOD-05; [COST_POLICY](../00-governance/COST_POLICY.md))
- change stock from a delivery, its correction, its closure or a printed layout, or subtract warehouse goods twice (BR-INV-02; FS-03)
- restore goods through the delivery closure, or let a closure and a restoration take the same units (NX-15; AX-10)
- reverse a delivery record for a client return — the return lowers net delivered instead (PX-08) —, or edit a committed delivery line
- issue DOC-08 or DOC-10 from a status or a print without the confirmed facts, or treat a draft as issued (BR-DOC-04; GAP-053)

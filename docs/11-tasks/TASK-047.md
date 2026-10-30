# TASK-047 — Service fulfillment and handover

Status: APPROVED | Updated: 2026-10-20 | Owner: Planning

Approval: [APPR-016](../00-governance/DECISION_LOG.md#appr-016--p11-task-plan-tasks-and-feature-coverage-matrix-approved), explicit Owner approval on 2026-10-20 (DIR-085 OF-07) of this document as committed in the first execution session's approval commit; the approved file hash and the verified conditions are recorded there. Approval changes lifecycle only, not implementation authorization.

Written by WF-PC5-W's writer of bundle W2 of run PC-5 on 2026-10-19 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99; the Owner approved P11 with the [Task plan](TASK_PLAN.md) at OG-4 (DIR-085 OF-07). **No Task starts before the freeze record, from which the execution authorization is in force (DIR-085 OF-09).** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** IN_PROGRESS

**RISK CLASS:** STANDARD

**EXECUTOR:** AGENT

**OBJECTIVE:**

Record the performance and handover of service lines — per line the business date, scope performed, recipient or acceptance and evidence, never above the pinned demand —, with the BAST (DOC-08) composed beside the handover from the confirmed facts and issued in place, and the exact reversal line of a handover recorded in error, on PG-033 *Serah Terima Jasa* (WF-FUL-04; AX-09; ROUTE-208, ROUTE-209).

**WHY THIS EXISTS:**

A service-only project must complete without fictitious warehouse movements (AC-20; BR-XD-02): services create no stock event (BR-INV-11; FS-05), and the handover is the delivery equivalent that the completion predicates read for SERVICE lines (L-39; TASK-055). The BAST renders confirmed handover facts and is never certified by a status or a print (BR-DOC-04; DOC-08), and the Owner's layout decision puts its live preview beside the handover's form (`DIR-079` CD-04; D-DS-23).

**DEPENDS ON:**

- TASK-029 — Document engine — drafts, issue, revision, void, renditions and the composer
- TASK-034 — Commercial confirmation and fulfillment planning

**BLOCKS:**

- TASK-055 — Project completion and Force Complete
- TASK-056 — Project cancellation

**PARALLEL SAFE:** NO — the handover writes the project-line guard (LK-19) that confirmation, cancellation and completion read and that TASK-045's deliveries also post

**IN SCOPE:**

- the FUL records of DATABASE §4.9 in use: `service_handovers` with business date and recipient, `service_handover_lines` with the entered-unit snapshot, the scope performed and `reverses_line_id` — a reversal exact and once (C-30)
- ROUTE-208 with INT-208 (`FUL RecordHandover`; `handover.record`; AX-09): the handover per service line from the journey's *Jasa* stage — business date, scope performed, recipient or acceptance, evidence uploaded first (IP-16) —; Σ handed over ≤ the pinned demand, beyond it REJECTED (C-30; PX-03); repeated partial handovers (PX-01); the likely-duplicate warning of a fungible event (L-49; BD-13; INT-015); the answer, then PG-011 with the handover recorded (NAV-033)
- the BAST on PG-033 as the second command of the screen (IP-19): its draft beside the handover — on the desktop the handover's form on the left and the live preview "DRAF — belum terbit" on the right from 1,025 px, part of the page and never a tab; Edit → Preview → Finalize on the phone, the preview full-screen (D-DS-23; `DIR-079` CD-04; DC-11) — saved by TASK-029's ROUTE-221 and issued in place by its ROUTE-222 after the confirmation (IP-08; MSG-39); this Task supplies DOC-08's record-bound precondition — a handover recorded — and its payload of confirmed handover facts through FUL's `DocumentSubjectProvider` (WF-DOC-01; ARCHITECTURE §3)
- ROUTE-209 with INT-209 (`FUL ReverseHandover`; `fulfillment.correct`; AX-09): the reversal line of a handover recorded in error, exact and once, with its reason (CM-27; IP-09); an issued BAST it affects revised or voided through TASK-029's routes, as WORKFLOWS §8 states for record-bound documents
- PG-033 with NAV-033 and ROUTE-033 (SCR-31; J-FUL-04): the service lines with handed-over and remaining quantities, the handovers with their reversal lines, the BAST's state; no stock screen ever involved (FS-05)

**OUT OF SCOPE:**

- the service's cost, which arises only from non-stock project-attributed purchase lines or expenses (L-18; TASK-037, TASK-054); materials used for a service are WAREHOUSE or DROP-SHIP lines of the project (TASK-041, TASK-046)
- the document engine, the composer and INT-012's preview (TASK-029); the upload routes ROUTE-228–ROUTE-231 (TASK-030)
- the completion predicates that read the handover (TASK-055) and the cancellation that bounds a remainder by it (TASK-056; SF-CANCEL-LINE)

**REFERENCES:**

- [WORKFLOWS §5.4](../03-workflows/WORKFLOWS/s05-04-05-fulfillment-documents.md#wf-ful-04--service-fulfillment-and-handover-both) WF-FUL-04; [§5.5](../03-workflows/WORKFLOWS/s05-04-05-fulfillment-documents.md#wf-doc-01--generated-document-lifecycle-desk) WF-DOC-01's DOC-08 row; [§7](../03-workflows/WORKFLOWS/s06-07-subflows-partial.md#7-partial-processing-rules) PX-01, PX-03; [§8](../03-workflows/WORKFLOWS/s08-09-corrections-indivisible.md#8-correction-reversal-and-cancellation-matrix) CM-27; [§9](../03-workflows/WORKFLOWS/s08-09-corrections-indivisible.md#9-indivisible-business-actions) AX-09, AX-15; [§12](../03-workflows/WORKFLOWS/s10-14-signals-decisions-traceability.md#12-planner-determined-level-1-decisions) L-18, L-19, L-26, L-39, L-49
- [BUSINESS_RULES SM:Document](../02-domain/BUSINESS_RULES.md#smdocument-all-doc-0114-invoice-is-a-specialization) BR-DOC-04; [Inventory](../02-domain/BUSINESS_RULES.md#inventory-inv) BR-INV-03, BR-INV-11; [§6](../02-domain/BUSINESS_RULES.md#6-cross-domain-interaction-rules) BR-XD-02–BR-XD-04; [§7](../02-domain/BUSINESS_RULES.md#7-forbidden-shortcuts) FS-05
- [V1_SCOPE DOC-08](../01-product/V1_SCOPE.md#selectable-document-catalog); [CAP-07](../01-product/V1_SCOPE.md#must-ship); [ACCEPTANCE_CRITERIA](../01-product/ACCEPTANCE_CRITERIA.md#product-acceptance-contracts) AC-07, AC-08, AC-20
- [DATABASE §4.9](../04-architecture/DATABASE/s04-09-ful.md#49-ful--fulfillment); [§5.7](../04-architecture/DATABASE/s05-inventory.md#57-drop-ship-and-services); [§18](../04-architecture/DATABASE/s18-19-constraints-integrity.md#18-constraint-catalogue) C-09, C-30
- [ARCHITECTURE §3](../04-architecture/ARCHITECTURE.md#3-modules) `DocumentSubjectProvider`, `CompletionPredicateProvider`
- [CONCURRENCY_IDEMPOTENCY §7.1](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s07-statement-sequences.md#71-lock-sets-of-ax-01ax-37) and [§7.2](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s07-statement-sequences.md#72-checks-effects-and-outcomes-of-ax-01ax-37) AX-09; [§11](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s10-13-identity-failure-retry.md#11-separate-duplicates) BD-06, BD-13
- [PERMISSIONS_MATRIX §4](../05-security/PERMISSIONS_MATRIX.md#4-capability-catalogue) `handover.record`, `fulfillment.correct`, `documents.issue`, `documents.correct`; [§7.2](../05-security/PERMISSIONS_MATRIX.md#72-table-classification) rows 84–89; [§7.3](../05-security/PERMISSIONS_MATRIX.md#73-special-projections) PJ-08, PJ-15, PJ-19
- [ROUTE_CONTRACTS](../03-workflows/ROUTE_CONTRACTS.md#fulfilment--deliveries-direct-shipments-handovers-and-client-returns-ful-pg-030pg-034) ROUTE-208, ROUTE-209; [§2](../03-workflows/ROUTE_CONTRACTS.md#2-page-views--route-001route-065) ROUTE-033; [ROUTE-221, ROUTE-222](../03-workflows/ROUTE_CONTRACTS.md#documents-files-and-evidence-doc-pg-035-pg-036-pg-038-wf-doc-01-wf-doc-02); [§4](../03-workflows/ROUTE_CONTRACTS.md#4-visible-controls-without-a-route-of-their-own) the draft's live preview
- [INTERACTION_CONTRACTS](../03-workflows/INTERACTION_CONTRACTS.md#fulfilment--deliveries-direct-shipments-handovers-and-client-returns-ful-pg-030pg-034) INT-208, INT-209; [INT-221, INT-222](../03-workflows/INTERACTION_CONTRACTS.md#documents-files-and-evidence-doc-pg-035-pg-036-pg-038-wf-doc-01-wf-doc-02); [§2](../03-workflows/INTERACTION_CONTRACTS.md#2-shared-interactions--int-001int-020) INT-012, INT-013, INT-015
- [NAVIGATION_CONTRACTS §2.4](../07-ux-design/NAVIGATION_CONTRACTS.md#24-fulfilment-cases-and-documents) NAV-033; [INFORMATION_ARCHITECTURE §8.4](../07-ux-design/INFORMATION_ARCHITECTURE.md#84-fulfilment-cases-and-documents) PG-033; [§8.7](../07-ux-design/INFORMATION_ARCHITECTURE.md#87-the-approved-screens-scr-01scr-60-and-their-pages) SCR-31
- [ADMIN_FLOW J-FUL-04](../07-ux-design/ADMIN_FLOW/s10-b-journeys.md#104-fulfilment); [IP-08](../07-ux-design/ADMIN_FLOW/s05-command-model.md#ip-08--confirming-an-irreversible-command); [IP-09](../07-ux-design/ADMIN_FLOW/s05-command-model.md#ip-09--corrections-closures-and-destructive-actions); [IP-19](../07-ux-design/ADMIN_FLOW/s05-command-model.md#ip-19--several-commands-on-one-screen); [IP-16](../07-ux-design/ADMIN_FLOW/s06-09-session-forms-lists.md#ip-16--evidence-upload)
- [DESIGN_SYSTEM §4](../07-ux-design/DESIGN_SYSTEM.md#4-design-decisions) D-DS-23; [§14.3](../07-ux-design/DESIGN_SYSTEM.md#143-document-preview); [§10](../07-ux-design/DESIGN_SYSTEM.md#10-component-composition) the component Document composer and paper
- [TEST_STRATEGY §7](../08-testing/TEST_STRATEGY.md#7-browser-end-to-end-tests-and-critical-journeys) CJ-01's service-only project, CJ-03, CJ-15; [SECURITY_TEST_MATRIX](../08-testing/SECURITY_TEST_MATRIX.md#3-the-matrix) SM-01, SM-04; [PERFORMANCE_TARGETS §3](../08-testing/PERFORMANCE_TARGETS.md#3-the-measured-targets) PFM-01, PFM-02, PFM-20
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (24): O-02; CAP-07; DOC-08; AC-07–AC-08; AC-20; IMG-02; IMG-04–IMG-06; BR-DOC-04; WF-FUL-04; CM-27; L-19; L-26; ROUTE-033; ROUTE-208–ROUTE-209; INT-208–INT-209; PG-033; SCR-31; J-FUL-04; NAV-033

**EXPECTED MODULES / FILE AREAS:**

- the FUL module's actions RecordHandover and ReverseHandover with `ShowServiceHandover` and its query class; FUL's `DocumentSubjectProvider` part for the handover's DOC-08 and its `CompletionPredicateProvider` part for SERVICE lines (ARCHITECTURE §3); the line guard through PRJ's `DemandPosting` (ARCHITECTURE §7); `resources/js/Pages/FUL` for PG-033 with TASK-029's composer component; `tests/Feature/FUL`

**BUSINESS RULES:**

- BR-INV-11, FS-05 and BR-XD-02: services never create stock events; a service-only project needs no warehouse step (AC-20)
- L-39: the handover is the delivery equivalent for SERVICE lines — they need no delivery record; Σ handed over ≤ the pinned demand (WF-FUL-04; C-30)
- L-18: a service's cost arises only from non-stock project-attributed purchase lines or expenses; materials used for it are stock or drop-ship lines of the project, never expensed while still in a lot
- BR-DOC-04, L-19, L-26 and BR-XD-04: one business record, its layouts — the BAST renders the confirmed handover facts, never a status or a print, and generating it changes no stock, cash or obligation
- BR-INV-03: handover quantities in base units with the entered-unit snapshot (C-09); CM-27: a handover recorded in error is superseded by its exact reversal line with a reason

**ARCHITECTURE RULES:**

- FUL is tier 6; DOC (tier 1) reads DOC-08's precondition and payload through FUL's `DocumentSubjectProvider` and never calls FUL; the composer is TASK-029's one component, reused here (ARCHITECTURE §3)
- the BAST's preview is composed in the browser from the page's props by the layout's template, marked "DRAF — belum terbit", writing nothing (DESIGN_SYSTEM §14.3; ROUTE_CONTRACTS §4)

**SECURITY / PERMISSION REQUIREMENTS:**

- ROUTE-033 and ROUTE-208 `handover.record` (ADM, prerequisite `projects.view`); ROUTE-209 `fulfillment.correct` (ADM_PLUS) with its reason (IP-09; MSG-40); the BAST's draft and issue `documents.issue`, its revision and void `documents.correct` (TASK-029's routes)
- the draft's props carry no stamp or signature file, no number and no field above the viewer's projection (PJ-19; WS-07)

**COMPANY SCOPE AND FIELD PROJECTION:**

- a handover belongs to its project's company (CS-03); its lines cite only that project's SERVICE lines (CS-04)
- rows 84–85: handovers and lines PV(c); DOC-08 is the operational family — metadata and payload with `projects.view` within the document's company, its identity assets per PJ-19 (PJ-15; rows 87–89); the handover's evidence by its family's view capability (PJ-08)

**CONCURRENCY / IDEMPOTENCY:**

- AX-09 for ROUTE-208 and ROUTE-209, STATIC: LK-00, LK-01 (the handover's project), LK-03, LK-10, LK-19, LK-29 (the evidence); re-read under lock: Σ handed over ≤ the pinned demand; a reversal line exact and unused; guard: the project line; refusals C-30, L-49; identity BD-13 by the line — a likely duplicate REJECTED L-49 unless the actor confirms it with a reason naming the matches —; retry class A (CONCURRENCY_IDEMPOTENCY §7.1–§7.2; §11)
- the BAST's issue is TASK-029's AX-15: one issue per draft, a second CONFLICT with the issued number (BD-06); a handover racing a remaining-scope cancellation of its line meets it at LK-01 and LK-19, the cancellation never below the handed-over quantity (AX-14; PX-08)
- one `command_id` replayed returns the recorded outcome, re-authorized (CI-05)

**AUDIT:**

- every handover and reversal line is a business audit event with the individual actor, business date, reason where required and links to the line and to the handover it reverses (BR-XC-01; BR-CR-01; LG-01); a reversal is ADM_PLUS and reaches the Owner's review queue (AZ-10; QS-14); a likely-duplicate confirmation commits under the acknowledged variant of its action code, listed in QS-21

**DATABASE IMPACT ASSESSMENT:**

- Schema change: NONE — uses the tables of TASK-005–TASK-009; a missing column is an executor discovery (AGENT_OPERATING_MODEL)
- Migration required: NO
- Existing data transformation: NONE
- Direct production DB access: FORBIDDEN
- Data-loss risk: NONE KNOWN

**MODULARITY / EXTENSIBILITY:**

- Module boundary affected: FUL, tier 6, with DOC (tier 1) through `DocumentSubjectProvider` (ARCHITECTURE §3)
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

- Context7: REQUIRED — the framework's file upload handling and Inertia's partial reloads for the composer's props, at the installed versions
- Graphify: OPTIONAL — once FUL's other Tasks exist
- Browser E2E: REQUIRED — J-FUL-04 on the phone at the client's site and at the desk with the BAST beside the form
- Other: none beyond TASK-003's harness
- Missing safe tool behavior: AUTO-INSTALL project-locally inside this Task (AICWDF §6.3) and record it in TOOLCHAIN; a paid tier or a privileged installation STOPS for the Owner (COST_POLICY)

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: refreshed before the change when Graphify is available ([TOOLCHAIN](../00-governance/TOOLCHAIN.md)); otherwise a targeted search of FUL and DOC's subject providers
- Pre-change questions: which commands post the SERVICE line's handed-over quantity, and which predicates read it
- Expected affected areas: FUL's handover actions; `DemandPosting`; PG-033 with the composer

**CONTEXT7:**

- Topics to verify: Inertia props for a live preview without a second request; upload-then-command file references
- Version-sensitive assumptions: none from memory

**UI / UX REQUIREMENTS:**

- Follow global design rules: YES
- Indonesian default: YES
- English support: YES
- Plain-language Indonesian: YES — the BAST's paper in Indonesian whatever the interface language (LOCALIZATION §3.4); the reversal saying what it reverses
- Language switch impact: keeps unsent input; the paper stays Indonesian
- Mobile-first: YES — `[both]`: at the client's site on the phone, the BAST as Edit → Preview → Finalize with the preview full-screen (DC-11); at the desk the handover's form beside the BAST's live preview (D-DS-23)
- Responsive: YES — the composer's widths of INT-012: the preview beside the form from 1,025 px, "Rincian | Pratinjau" below
- Adaptive: YES — the composer's three arrangements by band (DESIGN_SYSTEM §14.3)
- Visual breathing room: REQUIRED
- Comfortable density: REQUIRED
- shadcn/ui reuse: TASK-029's component Document composer and paper, the form layout (PT-09), the evidence upload (IP-16), the confirmation (PT-26), the timeline (PT-18)
- Design reference: PG-033 as INFORMATION_ARCHITECTURE §8.4 states it — the handover's form on the left and the BAST's live preview on the right (`DIR-079` CD-04); the Golden Frontend Reference for presentation only (DIR-061 SD-02)
- Primary action: record the handover; then "Terbitkan" the BAST
- Secondary actions: the handover's reversal line
- Destructive actions: none — corrections are exact reversal lines
- Redundancy constraints: one BAST draft per chain (DATABASE §4.10); no stock control on the page (FS-05)
- Loading state: PT-19; the preview updating as the allowed inputs change (DESIGN_SYSTEM §14.3)
- Empty state: no handover yet — one sentence (PT-19)
- Error state: beyond the pinned demand REJECTED with the remainder (MSG-02); MSG-15 for a likely duplicate; the BAST's issue refused while no handover is recorded

**ROUTE IMPACT:**

- ROUTE-033 (PG-033's view, GET `/proyek/{id}/serah-terima`)
- ROUTE-208 (POST `/proyek/{id}/serah-terima`) and ROUTE-209 (POST `/proyek/{id}/serah-terima/{ho}/balikkan`); TASK-029's ROUTE-221 and ROUTE-222 for the BAST on this page; each answering RC-05's errors

**INTERACTION CONTRACTS:**

- INT-208 and INT-209, each with its *Test* as its test (IG-05); INT-012 for the BAST's preview, INT-221 and INT-222 on PG-033, INT-013 for the evidence, INT-015 for a likely duplicate's second submission; INT-016 and INT-017 for CONFLICT and FAILED

**NAVIGATION CONTRACT:**

- Parent: PG-011 (NAV-033)
- Entry: the journey's *Jasa* stage
- Back action: "Kembali" → PG-011
- Cancel: "Batal" → PG-011, MSG-35 first with unsent input; the confirmation's and the reason step's "Kembali" → the form, the input kept
- Success destination: PG-011 with the handover recorded; the BAST issued in place (NAV-033; ROUTE-222)
- Error behavior: NV-04 — validation at the field, a rejected command in place, the input kept
- State preservation: unsent input kept across the re-login; the BAST's live preview beside the form from 1,025 px, part of the page and never a tab
- Mobile behavior: `[both]` — Edit → Preview → Finalize, the preview full-screen (DC-11)

**ACCEPTANCE CRITERIA:**

1. A handover of a SERVICE line records its business date, scope performed, recipient and evidence and creates no stock event; a service-only project reaches its handovers without any warehouse step (BR-INV-11; FS-05; AC-20).
2. Σ handed over above the pinned demand is REJECTED with the remainder; one `command_id` replayed hands over once (C-30; PX-03; CI-05).
3. On the desktop from 1,025 px the BAST's live preview "DRAF — belum terbit" sits beside the handover's form, never in a tab; on the phone it is Edit → Preview → Finalize with the preview full-screen; the preview writes nothing (D-DS-23; `DIR-079` CD-04; DC-11; INT-012).
4. The BAST issues only when a handover is recorded, from its confirmed facts, with its number at issue, and a second issue of the same draft is CONFLICT with the issued number; its paper stays Indonesian in either interface language (BR-DOC-04; BD-06; LOCALIZATION §3.4).
5. A handover recorded in error is reversed by one exact reversal line with its reason, at most once, and an issued BAST it affects is revised or voided (CM-27; C-30).
6. A likely duplicate handover without a distinguishing reference is REJECTED with the matches until the actor confirms it with a reason (L-49; BD-13).
7. The reversal is absent without `fulfillment.correct`; PG-033 is absent without `handover.record` (AZ-01; SM-04).

**TEST REQUIREMENTS:**

- Lint: TL-01
- Typecheck: TL-02
- Unit: the remaining-to-hand-over figure in base units (TL-03; C-09)
- Feature: ROUTE-208 and ROUTE-209 with each refusal; DOC-08's precondition on PG-033 through ROUTE-222 (TL-04)
- Integration: a handover racing a remaining-scope cancellation of its line (TL-05)
- Authorization: `handover.record` against `fulfillment.correct` (ADM_PLUS); `documents.issue` for the BAST (TL-06; SM-04)
- Route integrity: ROUTE-033, ROUTE-208, ROUTE-209 audited (TL-07; SM-02)
- Interaction: INT-208 and INT-209 per IG-05 with one `command_id` replayed; INT-012 on PG-033 at 1,025, 1,279 and 1,280 px and the tablet's toggle at 1,024 px (TL-08)
- Browser E2E: J-FUL-04 on the phone and the desktop; CJ-03's service handover; CJ-15's BAST from PG-033 (TL-09)
- Localization/language switch: both languages on PG-033, the paper Indonesian in both (TL-10)
- Visual breathing room/density: TEST_STRATEGY §9 — the composer beside the form
- Responsive: TL-11
- Accessibility: TL-12 — the paper a named region, never a live region, its frame reachable by keyboard (DESIGN_SYSTEM §15)
- Production build: TL-13
- Security: SM-01 for another company's project (TL-14)
- Performance: PFM-01 and PFM-02 for PG-033; PFM-20 with QB-13 for AX-09 (TL-15)
- Migration: N/A — no migration or import code in this Task
- Other: DOCUMENT_PREVIEW_PRODUCER §4's checks on the BAST's preview, as TASK-029 states them

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
- create a stock event, a delivery record or a fictitious purchase for a service line (BR-INV-11; FS-05; L-39)
- issue the BAST from a status or a print without a recorded handover, or let its draft pass for an issued document (BR-DOC-04; GAP-053)
- put the BAST's preview in a tab on the desktop, or translate its paper (D-DS-23; LOCALIZATION §3.4)
- edit or delete a handover instead of reversing it with its reason (CM-27)

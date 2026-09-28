# Owner reference coverage and reconciliation

Status: REVIEW | Updated: 2026-09-28 | Owner: Planning

This is the P1 comparison record requested by DIR-008. It owns reference identifiers, coverage findings and the later traceability handoff; product commitments live in [V1_SCOPE](V1_SCOPE.md), journeys in [PRODUCT_OVERVIEW](PRODUCT_OVERVIEW.md), observable evidence in [ACCEPTANCE_CRITERIA](ACCEPTANCE_CRITERIA.md), decisions in [DECISION_LOG](../00-governance/DECISION_LOG.md), and unresolved risk in [GAP_REGISTER](../00-governance/GAP_REGISTER.md). No Feature Coverage Matrix or Build Units exist yet.

## Sources and review method

The complete 12-page PDF was text-extracted and every page visually inspected, including table continuations on pages 3–6 and 9/12. The full-resolution operational-flow image was inspected, including all branches, the generated/external-document panels and completion box. Both original files and the separate ingestion request were copied without alteration; provenance and SHA-256 are in [SOURCE_OF_TRUTH](../00-governance/SOURCE_OF_TRUTH.md#owner-reference-ingestion-and-provenance). The request supplies instructions; the attachments supply supporting planning content under its six-level authority hierarchy.

Compared against the pre-ingestion REVIEW versions of PRODUCT_OVERVIEW, V1_SCOPE, ACCEPTANCE_CRITERIA, GAP_REGISTER, DECISION_LOG, CURRENT_STATE, NEXT_ACTION, approved P0 governance/ADR-001 and all four earlier owner records. The comparison below preserves the *finding before correction* and the resulting contract, rather than claiming omitted detail was already present. "Correct" means correctly represented in planning, never implemented/tested.

Subsequent DIR-009 explicitly changes the backup policy after reference checkpoint `20f8ef7`: local-only backups on the production VPS, conditional RPO/RTO and accepted total-VPS-loss exposure. REF-060 now records this Owner removal of the offsite requirement; the source PDF is unchanged. This is an authorized scope/risk decision, not a missing requirement or unapproved planner cut.

DIR-011 subsequently resolves the four P1 business choices and confirms source-company attribution plus local-only backup. Historical pre-ingestion findings remain visible; current residuals in GAP-004/006/022/023 are technical design/evidence only. OWN-01–06 below trace the final Owner instruction without creating a second policy specification.

Dispositions: **Correct** = already represented correctly; **Incomplete** = represented but incomplete; **Missing** = no explicit usable P1 contract; **Contradicted** = conflicting interpretation found; **Owner removed** / **Owner deferred** = explicit owner evidence; **Decision required** = business policy not settled. No row may disappear because of the 18-day window. Rows can be represented with a pending policy decision without pretending that policy is settled.

## PDF capability inventory

REF-001–062 correspond exactly to PDF capability numbers 1–62 (pages 2–6). CAP and AC identifiers resolve in the owning documents linked above. Future rules/tests are phase obligations, not fabricated unit or test IDs.

| Requirement | PDF capability / expected outcome | Pre-ingestion finding | Reconciled P1 coverage / residual |
| --- | --- | --- | --- |
| REF-001 | Authentication: login/logout/reset, hashing, throttle, secure session | Correct | CAP-13; AC-13; P5/P8 explicit auth checks, approved engineering guardrails |
| REF-002 | Owner and Admin Operasional built-in roles | Correct | CAP-01; AC-01/13; no new launch role |
| REF-003 | Dynamic capability model, Owner custom roles later | Owner deferred for designer; permission model Correct | CAP-01; D-01; AC-13. P1 directive Roles and OB §17 explicitly say future custom roles; PDF #3 also says later |
| REF-004 | Explicit company grants; Owner all-company access | Correct | CAP-01/13; AC-13; DIR-011 physical-versus-business visibility resolved; GAP-006 field/enforcement proof |
| REF-005 | Existing and future CV/PT/legal entities | Correct | CAP-01; AC-01 |
| REF-006 | Company NPWP/bank/address/logo/stamp/signature/director/document identity | Incomplete: grouped assets lacked explicit checklist | CAP-01/08; AC-01/08; identity checklist below, GAP-019 |
| REF-007 | Owner consolidated and company dashboard | Incomplete | CAP-17; AC-23; DIR-011 five financial concepts; GAP-004 formalization |
| REF-008 | Admin next-action dashboard across operational work | Incomplete | CAP-17; AC-23; P7 actionable queues |
| REF-009 | Generic client master; UNY/UGM normal clients | Correct | CAP-02; AC-02 |
| REF-010 | Organization/unit/PIC and unit-specific address | Incomplete | CAP-02; AC-02; address selection refinement below |
| REF-011 | Supplier/product history, previous/last purchase price | Correct | CAP-02; AC-02; SH-01 only changes inline presentation |
| REF-012 | Products/services, SKU, units, images, activation | Correct | CAP-03; AC-03 |
| REF-013 | Manufacturer/internal barcode, unknown/duplicate protection | Correct | CAP-03/13; AC-03/16; GAP-009/015/020 |
| REF-014 | Optional serial tracking | Correct | CAP-03; AC-03/16 |
| REF-015 | Purchase/selling/SPJ reference prices, snapshots/history | Incomplete | CAP-03/11; AC-03/11; GAP-004 preserves distinct meanings |
| REF-016 | Configurable tax/fee/VA/adjustment values | Incomplete | CAP-11/12; AC-11/15; no hard-coded financial formula, GAP-004 |
| REF-017 | Project number/company/client/items/status/deadlines/history | Incomplete | CAP-04; AC-04; explicit number/deadline and journey added |
| REF-018 | Quotation draft/sent/validity/revision/approval/PDF/history | Incomplete | CAP-04/08; AC-04/08 |
| REF-019 | Purchase actual costs/tax/shipping/company/project | Correct | CAP-05; AC-05; no cash or receipt implied |
| REF-020 | Optional PO, multiple suppliers/POs per project | Incomplete | CAP-05; AC-05 multi-supplier case added |
| REF-021 | One shared physical warehouse | Correct | CAP-06; AC-06/20 |
| REF-022 | Source/cost company and consuming project attribution | Correct | CAP-06/11; AC-06/11; GAP-003/004/006 remain |
| REF-023 | Rack/storage location lookup | Missing | CAP-06; AC-22; simple location in shared warehouse, no WMS expansion |
| REF-024 | Partial/damaged receiving, scan/serial/evidence | Incomplete | CAP-06; AC-03/06/16/22; DIR-011 excludes damaged/rejected/quarantined supply; GAP-023 technical proof |
| REF-025 | Dispatch barcode, project/client reason, user, safe reversal | Correct | CAP-06/13; AC-06/12/13/16; dispatch DoD in acceptance |
| REF-026 | Movement ledger and optimized current balance | Correct | CAP-06; AC-06/16/22; mechanism remains P4/P6 |
| REF-027 | Opname scan/count/variance, reasoned audited adjustment | Correct | CAP-06; AC-06/16; stale count GAP-009 |
| REF-028 | Per-item minimum stock, low-stock and restock suggestion | Missing | CAP-06/17; AC-22/23; advisory, no automatic purchase |
| REF-029 | Purchase/sales returns and reversal/correction | Incomplete: return direction implicit | CAP-06/13; AC-06/12/21; GAP-005/022 |
| REF-030 | Drop-ship without false warehouse movements | Correct | CAP-06; AC-06/20 |
| REF-031 | Partial/full delivery, proof/signature/name/condition | Incomplete | CAP-07; AC-07/21; evidence and discrepancy preserved |
| REF-032 | Reusable per-company document generation | Correct | CAP-08; AC-01/08; shared capability, detailed design P4 |
| REF-033 | Company-issued documents including BAST and other outputs | Correct | CAP-08; DOC-01–14; AC-08; eight named outputs in image are not a cap on C1 |
| REF-034 | External SPK/order/tax/NPWP/NIB/bank/HPS originals | Contradicted if title alone defines generated issuer | CAP-08/09; AC-08/09; issuer-specific reconciliation below and GAP-019 |
| REF-035 | Draft/issued/final/revision/void and prior versions | Correct | CAP-08/13; AC-08/12 |
| REF-036 | Company/type/period-aware concurrent unique numbering | Incomplete: exact scope not explicit in P1 | CAP-08; AC-08; invoice DoD in acceptance; period GAP-016 |
| REF-037 | Flexible project/client admin/SPJ checklist | Correct | CAP-09; AC-09/21; DIR-011 Admin eligible N/A and Owner override; GAP-022 technical proof |
| REF-038 | SIPLAH channel/manual metadata/future integration boundary | Correct; API Owner deferred | CAP-12; D-03; AC-15 |
| REF-039 | Invoice document distinct from billed/date/due date | Incomplete: dates implicit | CAP-10; AC-10; DIR-011 billing activates unpaid receivable; GAP-004 technical examples |
| REF-040 | Unbilled/billed/unpaid/partial/paid/overdue and aging | Incomplete: aging not explicit | CAP-10/17; AC-10/23; GAP-004/016 |
| REF-041 | Full/partial/DP/termin, method/company bank/reference/proof | Correct | CAP-10; AC-10/16; optional chronology preserved |
| REF-042 | Excess payment is credit/refund, never profit | Correct | CAP-10/11; AC-10/11; DIR-011 actual-payment/credit boundary; GAP-004 technical examples |
| REF-043 | Permissioned audited payment reallocation/correction | Correct | CAP-10/13; AC-10/12/13 |
| REF-044 | Revenue/HPP/expenses/cash/AR/managerial finance | Correct | CAP-11; AC-11; DIR-011 five concepts; residual technical formalization GAP-004 |
| REF-045 | Project/company/period/group profit and margin | Incomplete: period and coverage implicit | CAP-11/17; AC-11/23 |
| REF-046 | Company/date-filtered stock/purchase/sales/AR/payment/profit/cashflow reports | Incomplete | CAP-17; AC-23; complete named report families retained |
| REF-047 | Operational Excel/PDF/print export | Missing explicit report-export acceptance | CAP-17; AC-23; existing document PDF/print AC-08 retained |
| REF-048 | In-app/dashboard low-stock/overdue/incomplete-work alerts | Missing explicit user-facing alert contract | CAP-17; AC-23; distinct from operator failure detection AC-19 |
| REF-049 | Audit of stock/price/invoice/payment/permissions/void/documents | Correct | CAP-13; AC-12/13/16/21; applicable DoD audit proof |
| REF-050 | Fast product/SKU/barcode/project/client/document search | Incomplete | CAP-17; AC-23; bounded indexed query design P6, no new search service |
| REF-051 | Masters/prices/opening stock/open work/AR/useful legacy data | Correct | CAP-15; AC-17; GAP-010 |
| REF-052 | Natural Indonesian end-user UI | Correct | CAP-14; AC-14; P7 glossary |
| REF-053 | Mobile-first and desktop intensive work | Correct | CAP-14; AC-14/20/23; GAP-020 |
| REF-054 | React/TypeScript/Inertia/shadcn/Tailwind baseline | Correct in approved governance | ENGINEERING_PRINCIPLES baseline; CAP-14; AC-14; versions not chosen in P1 |
| REF-055 | Modern restrained operational anti AI-slop design | Correct | CAP-14; AC-14; image styling is not application UI |
| REF-056 | Auth/company/validation/CSRF/cookies/XSS/SQL injection/clickjacking/files/IDOR | Correct in governance, P1 grouped | CAP-13; AC-13; explicit eight-dimension obligations, P5/P8 controls |
| REF-057 | Transactions/atomicity/constraints/safe correction | Correct | CAP-13; AC-12/16; P4/P6 mechanisms, no schema now |
| REF-058 | Idempotent payment/receipt/dispatch/issue | Correct | CAP-06/08/10/13; AC-08/16; GAP-007–009 |
| REF-059 | No N+1; pagination/index/search/bounded loading | Incomplete at product evidence level | AC-23 and eight-dimension DoD; P6/P8 measurable evidence, GAP-017 |
| REF-060 | PDF DB/files/offsite backup and restore RPO24h/RTO4h | Previously Correct; offsite requirement subsequently Owner removed by DIR-009 | CAP-16/BK-01–12; AC-18/19; only same-VPS backups, conditional recoverable-local-data targets. GAP-011 local proof OPEN; GAP-018 ACCEPTED_RISK (RISK-001), no total-host-loss guarantee |
| REF-061 | Pest/PHPUnit, TestSprite, CI, justified load testing | Correct in governance; module/release aggregation incomplete | OS-06 plus feature/module/release obligations; P8/P10, DEP-07 entitlement evidence |
| REF-062 | Existing VPS, near-zero incremental cost, self-hosted preference | Correct | CAP-16; AC-19; cost/DEP-01–09; no paid provider selected |

REF-006/010 required explicit identity/address detail in CAP-01/02 and AC-01/02; those contracts were strengthened in this reconciliation. Table references alone are not a substitute for changing the owning specification.

## Image and non-tabular PDF inventory

| Requirement | Source / capability or principle | Finding and canonical treatment |
| --- | --- | --- |
| IMG-01 | Stage 1 login/auth/scope/dashboard/project/company/client/unit/PIC/channel/items/pricing | Already represented but the journey was abbreviated; full ordered orientation and permission scope now in PRODUCT_OVERVIEW, CAP-01–04/12/13/17; AC-01–04/13/15/23 |
| IMG-02 | Quotation approval yes/no and revision loop | Incomplete loop/validity detail; CAP-04/08 and AC-04/08 strengthened |
| IMG-03 | Stock yes→Reserve/Allocate; no→Purchase/Supplier/Price/optional PO | Allocation was Missing and reservation meaning was Decision required; DIR-011 now resolves confirmed-demand reservation, availability invariant and release. CAP-05/06; AC-05/16/22; GAP-023 technical proof. Existing stock plus purchased shortage remains supported |
| IMG-04 | Warehouse receiving/scan/movement/balance versus supplier direct confirmation | Correct outcome, misleading join could force re-receipt; contextual branch in PRODUCT_OVERVIEW, AC-03/06/16/20/22, GAP-021 closed in planning |
| IMG-05 | Delivery partial/full, delivery note and proof | Incomplete proof/discrepancy; CAP-07, AC-07/21; controlled repeated delivery loop |
| IMG-06 | Generated and external document panels | Issuer interpretation Contradicted if conflated; DOC-01–14 and authentic external treatment, AC-08/09, GAP-019 |
| IMG-07 | Invoice→admin→billed→receivable | Invoice/billing separation Correct; previously undecided trigger resolved by DIR-011: issue records sales value, explicit billing activates unpaid receivable with billed_at/due_date. CAP-10/11; AC-10/11; BELUM DITAGIHKAN stays separately visible; no universal SPJ prerequisite inferred |
| IMG-08 | Payment full/partial loop/outstanding/validation/completed | Aggregate completion was Missing; CAP-18/AC-21 now distinguish normal gate and DIR-011 Owner-only audited exception. Advances and partial payments remain real cash events; completion override never erases residual receivable. GAP-004/022 technical proof |
| IMG-09 | Completion checklist, automatic reports, footer principles | CAP-18 ten conditions and CAP-17/AC-23 continuous updates; latest DIR-008 explicitly adds payment corrections. English/gradient styling is conceptual; CAP-14 governs Indonesian UI |
| REF-G01 | PDF §§1–2 scope/roles/UX/principles, §§13–14 continuity/simplification | Correct governance basis; no silent cuts or execution authorization. DIR-008 hierarchy, eight-dimension evidence and build-path recommendations below |
| REF-G02 | PDF §4 supplier comparison exclusion | Owner removed; V1_SCOPE OUT OF SCOPE, P1 directive Suppliers and OB §7; supplier history remains |
| REF-G03 | PDF §4 SIPLAH API/formal accounting/native/AI nuance | Owner deferred API; accounting excluded by P1 Finance; native/AI not current V1, OB §46. V1_SCOPE D-03/D-05/exclusions; no new inference that all future features are approved |
| REF-G04 | PDF §§5–6 detailed normal path and project hub | Incomplete explicit dashboard/quotation/billing detail, now PRODUCT_OVERVIEW branches and CAP-04/17; all 12 detailed stages traced to IMG-01–09 and REF-001–062 |
| REF-G05 | PDF §7 ten completion conditions across pages 8–9 | Missing aggregate product gate; CAP-18/AC-21 and completion-condition table preserve all ten; DIR-011 normal gate, Admin N/A and exceptional Owner override; GAP-022 technical proof |
| REF-G06 | PDF §§8–10 eight gates, dispatch/payment/invoice and BU checklist | P0 safeguards existed; explicit eight-way evidence and checklist aggregation incomplete. ACCEPTANCE_CRITERIA completion obligations now own interim P1 requirement; P8/P11 must preserve every category and reasoned N/A |
| REF-G07 | PDF §11 module completion, inventory integration | Missing explicit integrated module gate; acceptance module paragraph covers the inventory set and cross-module evidence; future P10 aggregation |
| REF-G08 | PDF §12 release gates including configuration/deploy/smoke | OS outcomes existed; explicit traceability/all-units and release checklist incomplete. ACCEPTANCE_CRITERIA release obligations include every gate on pages 11–12; none marked executed |

## Contradictions, source decisions and boundaries

1. **Document issuer:** PDF #34 names external SPK/order/HPS while C1 delegates a full generation recommendation. Preserve the 14 company-prepared outputs and authentic external originals; label issuer/draft truthfully. No client signature or issuance is inferred. GAP-019 covers actual examples; changed business meaning goes to Owner.
2. **Warehouse join:** The diagram visually joins available stock to a receiving path. Latest explicit optionality and actual physical events govern: existing stock does not need another receipt; service/drop-ship do not fabricate warehouse events. This is already settled by GAP-021, not a new permission question.
3. **Receivable trigger resolved by DIR-011:** Issue records agreed sales/transaction value without Cash-In; BELUM DITAGIHKAN stays visible. Explicit billing activates only the unpaid receivable and records billed_at/due_date. Actual payments create Cash-In and reduce receivable. The diagram no longer leaves the P1 trigger undecided; GAP-004/016 cover numeric/date formalization and proof.
4. **Document/payment order:** A kuitansi pictured before invoice/payment is a catalog possibility, not proof of payment. Advances/termin may precede delivery. AC-08/10 require real facts; no strict universal sequence is invented.
5. **Completion arrow resolved by DIR-011:** Normal completion requires the full gate; permissioned Admin N/A is only for eligible administrative requirements with reason/audit. Owner-only exceptional Force Complete requires confirmation, reason, actor, timestamp, before/after and audit while preserving unmet conditions/residual obligations. GAP-022 is technical revalidation/applicability work, not unresolved authority.
6. **Roles and styling:** Custom role designer stays future under explicit owner wording; permission architecture remains mandatory. Diagram English/gradients do not override Indonesian/shadcn/anti-slop UI. These are reconciled interpretations, not new owner questions.
7. **Reference PASS labels:** All feature/module/release PASS cells are expectations. P1 verifies documents only; zero application, UAT, migration, recovery or release checks have run.
8. **Backup policy supersession:** PDF #60 and earlier baseline requested offsite retention; latest DIR-009 explicitly replaces this with same-production-VPS backup only. Preserve the PDF as history, remove offsite as a release dependency, retain BK-01–12 controls and conditional local-recovery targets. Known total-host/storage-loss exposure is accepted, not a recurring decision request.

The reference ingestion did not approve deadline/access/financial changes. Subsequent DIR-009 is an explicit Owner backup-scope/risk decision and overrides the earlier offsite baseline within that subject. Other REF findings remain reconciliations of REVIEW proposals; ADR-001 governance is unchanged. DIR-011 now resolves the Owner choices in GAP-004/006/022/023; they remain OPEN for technical design/evidence only. There are zero current OWNER_DECISION_REQUIRED entries. GAP-018 remains ACCEPTED_RISK; other local technical/content/evidence gaps remain OPEN.

## Final Owner decision coverage

The [final Owner source](../00-governance/sources/P1_FINAL_OWNER_DECISIONS_2026-09-28.txt) is binding within its subject. These identifiers index coverage; the normative rules live only in V1_SCOPE. P11 must carry OWN-01–06 along with REF/IMG/BK and original source requirements into the eventual matrix.

| Requirement | Owner source section and retained obligations | Canonical specification / acceptance / remaining technical gate |
| --- | --- | --- |
| OWN-01 | GAP-004: five financial concepts; issued value, BELUM DITAGIHKAN, billed unpaid receivable, billed_at/due_date/aging, actual payments/partial/full/excess/correction/reallocation, direct HPP, actual cash-out; managerial finance only | V1_SCOPE financial concepts and CAP-10/11; AC-10/11/12/16; GAP-004/016, P2/P4/P8 |
| OWN-02 | GAP-006: Owner all-company/consolidated/attribution; Admin explicit grants and capabilities; necessary physical availability with inventory permission; all nine protected business/financial categories and manipulated URL/ID/query/payload/API/download denial | V1_SCOPE visibility and CAP-01/13; AC-13; GAP-006, P2/P5/P6/P8 |
| OWN-03 | GAP-022: system-evaluated normal gate, Admin eligible N/A with reason/audit, no silent invariant bypass; Owner exceptional Force Complete with confirmation, reason, actor, timestamp, before/after and audit | V1_SCOPE completion and CAP-09/18; AC-09/21; GAP-022, P2/P3/P5/P6/P8 |
| OWN-04 | GAP-023: quote alone never reserves; confirmed-project/order reservation; four quantity concepts/invariant; unusable exclusions; release/adjustment; physical movement distinction; concurrent final-stock protection; simple V1 | V1_SCOPE reservation and CAP-06; AC-16/22; GAP-023/009, P2/P3/P4/P6/P8 |
| OWN-05 | Shared pool with source-company acquisition provenance and explicit auditable inter-company allocation to consuming project/company; managerial costs; no automatic inter-company invoices, journals or cash transfer | V1_SCOPE attribution and CAP-06/11; AC-06/11/13; GAP-003/004/006, P2/P4/P5/P8 |
| OWN-06 | Reaffirm local-only production-VPS backup, no offsite requirement, accepted total-loss risk and Owner-only policy change | V1_SCOPE CAP-16/BK-01–12; AC-18/19; REF-060; GAP-018 accepted, GAP-011/013 technical work, P9 |

Finalization instructions are covered by DECISION_LOG/SOURCE_OF_TRUTH provenance, P1_QUALITY_GATE review/evidence, CURRENT_STATE/NEXT_ACTION phase boundaries and this coverage record. They do not create an application feature.

## Cross-module findings and simpler build path

| Boundary | Finding / simpler safe product or planning response | Owning contract / remaining gate |
| --- | --- | --- |
| Project ↔ Company/Client/Product | Reuse confirmed company identity, unit address, item/value snapshot and one activity history; avoid repeated data entry | CAP-01–04/08; P2/P3/P7; GAP-006/019 |
| Project ↔ Purchase ↔ Inventory | Multiple suppliers, partial receipts and existing stock can jointly fulfill one demand; preserve sourcing and avoid duplicate purchasing | CAP-05/06; AC-05/22; GAP-003/009/023 |
| Inventory ↔ Delivery ↔ Document | Dispatch, delivery and printing must not each subtract stock; damaged/returned quantities cannot become available by a label change | AC-06/07/08/22; GAP-005/009/023 |
| Document ↔ Invoice ↔ Billing ↔ Payment | Reuse one document capability with immutable versions/numbering and distinct money events; rendering failure cannot create a second issue/payment | AC-08/10/12/16; GAP-004/007/008 |
| Payment ↔ Profitability | Separate cost, cash, credit and receivable meaning in one future money/metric convention | CAP-10/11; GAP-004 before formulas |
| Admin/SPJ ↔ Client; SIPLAH ↔ Project | Selectable per-project checklist and channel metadata use the same workspace; no institution-specific module or universal SPJ sequence | CAP-09/12; AC-09/15; GAP-019/022 |
| Permissions ↔ Company ↔ Search/Export | Reuse consistent server authorization/company scope across views, files, jobs and exports | CAP-13/17; AC-13/23; GAP-006 |
| Corrections ↔ Audit ↔ Completion | Common audit/reason conventions and completion revalidation reduce inconsistent module behavior | CAP-13/18; AC-12/21; GAP-005/022 |
| Migration ↔ Model ↔ Reporting/Recovery | One opening baseline and durable source references; preserve file links, stock allocations and obligations without replaying history | CAP-15/16; AC-17/18; GAP-010/011/023 |

Later architecture should evaluate shared business Actions, document generation/numbering, money representation, validation, authorization/company scoping, idempotency, audit/timeline and status conventions before duplicating implementations. Prefer constraints and ordinary framework facilities that remove failure paths; standard shadcn form/table/error patterns can reduce screens/clicks. Reuse deterministic fixtures and denial/retry/concurrency assertions across modules. These are bounded simplification candidates for P4–P8, not class/schema/state-machine designs or a generic workflow engine. No version/API claim or new dependency is made in P1.

## Traceability handoff and non-loss gate

Required chain: **Requirement → canonical specification → business workflow/rule → Build Unit → acceptance criteria → required test**. REF/IMG IDs supplement, not replace, OB/P1/C1–C3/DIR-008 source requirements. All 18 CAP groups, 14 document types, 23 AC and six OS outcomes must remain traceable; a broad CAP link alone cannot hide a missing sub-capability.

DIR-009 adds BK-01–12 as sub-requirement identifiers under CAP-16/AC-18–19, with its explicit offsite removal and accepted-risk evidence carried into the future matrix. No new application capability group or build unit is created.

| Phase | Required continuation |
| --- | --- |
| P2/P3 | Preserve REF/IMG identifiers while assigning real business-rule/workflow references; formalize the settled DIR-011 financial/waiver/allocation meanings without reopening them; report only genuinely new business conflicts |
| P4–P7 | Link data/security/concurrency/performance/UX design to the same requirements and accepted decisions, including exceptions and device behavior |
| P8 | Define actual test IDs and expected assertions, eight-dimension DoD and explicit N/A reasons; include critical examples, not just UI flow |
| P10 | Aggregate module/release checks, all reference scope, UAT/migration/recovery/deployment evidence and actual effort; no undocumented cuts |
| P11 before freeze | Create the reserved `docs/06-delivery/FEATURE_COVERAGE_MATRIX.md`, mapping every requirement to actual canonical/rule/workflow/unit/AC/test references. Include declared exclusions/deferments and exact owner evidence; validate zero unexplained missing mappings |

GAP-024 remains OPEN until the chain exists and coverage is checked. P1's inventory is complete as a reference comparison; the later full Feature Coverage Matrix, application and evidence are deliberately absent. Stop at P1 REVIEW.

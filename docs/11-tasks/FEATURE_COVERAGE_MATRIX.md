# Feature coverage matrix

Status: APPROVED | Updated: 2026-10-31 | Owner: Planning

Approval: [APPR-016](../00-governance/DECISION_LOG.md#appr-016--p11-task-plan-tasks-and-feature-coverage-matrix-approved), explicit Owner approval on 2026-10-20 (DIR-085 OF-07) of this document as committed in the first execution session's approval commit; the approved file hash and the verified conditions are recorded there. Approval changes lifecycle only, not implementation authorization.

Amendment: on 2026-10-20 the Owner's decision [DIR-088](../00-governance/DECISION_LOG.md#dir-088--task-005s-discoveries-the-forward-foreign-keys-ix-13s-deferred-indexes-and-the-export-identifier) maps IX-13 also to TASK-058, which builds its two partial indexes TASK-005 could not; no other row changes and the entry count is unchanged. It is a Level 1 record under CHANGE_CONTROL, as the Owner directs.

Amendment: on 2026-10-31, under [DIR-100](../00-governance/DECISION_LOG.md#dir-100--change-4-the-contract-rows-and-the-consistency-edit), Change 4 of [TASK_PLAN §7](TASK_PLAN.md#7-controlled-task-plan-changes) adds its eighteen entries and the counts they change. It takes effect at the Owner's merge.

Amendment: on 2026-10-31, under [DIR-101](../00-governance/DECISION_LOG.md#dir-101--the-owners-decisions-on-p11q-07-of-04-and-the-labels), Change 15 of [TASK_PLAN §7](TASK_PLAN.md#7-controlled-task-plan-changes) adds its four entries and the counts they change.

Run PC-5 wrote this matrix on 2026-10-19 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-95 and SP-97, P11 being authorized by the Owner at OG-3 (DIR-083 OE-19); the Owner approved it with P11 at OG-4 (DIR-085 OF-07), which satisfies PRQ-13. It is the FEATURE_COVERAGE_MATRIX that P11 creates before the planning freeze ([REFERENCE_COVERAGE](../01-product/REFERENCE_COVERAGE.md); [AGENT_OPERATING_MODEL](../00-governance/AGENT_OPERATING_MODEL.md#terminology); RELEASE_PLAN PRQ-13): every requirement that an approved document of P1–P10 defines by an identifier, inventoried by script by the method of §1, mapped to the Task or Tasks of the [Task plan](TASK_PLAN.md) that build or verify it, or to a recorded non-Task disposition with its reason — never "N/A" alone. A requirement stated only in prose is carried by the identified rows of its section and by the Tasks that cite that section; one found without a contract is raised to the Owner, as P11Q-02 is ([TASK_PLAN §8](TASK_PLAN.md#8-owner-questions)). The normative rule stays in its source; this matrix only says where each one is built.

## 1. The requirement inventory (SP-95)

**Method.** By script, from the working tree at `planning/p8-p11` after run PC-5's publication of P8, P9 and P10: every identifier that an APPROVED normative document of P1–P10 defines, in a family of the [identifier map](../CONTEXT_INDEX.md#identifier-map) that states a requirement, rule, contract, target, test, obligation or handoff; REFERENCE_COVERAGE's identifiers, with the original Owner requirements of DIR-008 (OWN-) — a REVIEW record, read because its traceability handoff asks P11 to carry REF-, IMG-, BK- and OWN- (REFERENCE_COVERAGE, "Traceability handoff and non-loss gate" and "Final Owner decision coverage"); and every OPEN gap of the [gap register](../00-governance/GAP_REGISTER.md). An identifier is taken where its document defines it — a table row's first cell, a heading or a bold lead —; items its document names without a prefix are taken by their own rows or headings: DATABASE §4's logical tables, PERMISSIONS_MATRIX §4's capabilities, DATABASE §24's migration rules, DESIGN_SYSTEM §10's components, and LOCALIZATION's key groups (§6) and rules (§1–§5). Each entry carries its identifier, its family and its source — the document and the section that defines it. **3234 entries.**

### 1.1 Families included

| Family | Defined in | Entries |
| --- | --- | --- |
| O- | [PRODUCT_OVERVIEW](../01-product/PRODUCT_OVERVIEW.md) | 7 |
| CAP- | [V1_SCOPE](../01-product/V1_SCOPE.md) | 18 |
| SH- | [V1_SCOPE](../01-product/V1_SCOPE.md) | 2 |
| D-0n (deferred) | [V1_SCOPE](../01-product/V1_SCOPE.md) | 6 |
| DOC- | [V1_SCOPE](../01-product/V1_SCOPE.md) | 14 |
| DEP- | [V1_SCOPE](../01-product/V1_SCOPE.md) | 11 |
| BK- | [V1_SCOPE](../01-product/V1_SCOPE.md) | 12 |
| AC- | [ACCEPTANCE_CRITERIA](../01-product/ACCEPTANCE_CRITERIA.md) | 23 |
| OS- | [ACCEPTANCE_CRITERIA](../01-product/ACCEPTANCE_CRITERIA.md) | 6 |
| REF- | [REFERENCE_COVERAGE](../01-product/REFERENCE_COVERAGE.md) | 62 |
| IMG- | [REFERENCE_COVERAGE](../01-product/REFERENCE_COVERAGE.md) | 9 |
| OWN- | [REFERENCE_COVERAGE](../01-product/REFERENCE_COVERAGE.md) | 6 |
| BR- | [BUSINESS_RULES](../02-domain/BUSINESS_RULES.md) | 83 |
| CALC- | [BUSINESS_RULES](../02-domain/BUSINESS_RULES.md) | 14 |
| FS- | [BUSINESS_RULES](../02-domain/BUSINESS_RULES.md) | 14 |
| WF- | [WORKFLOWS](../03-workflows/WORKFLOWS/README.md) | 39 |
| SF- | [WORKFLOWS](../03-workflows/WORKFLOWS/README.md) | 18 |
| PX- | [WORKFLOWS](../03-workflows/WORKFLOWS/README.md) | 8 |
| CM- | [WORKFLOWS](../03-workflows/WORKFLOWS/README.md) | 38 |
| AX- | [WORKFLOWS](../03-workflows/WORKFLOWS/README.md) | 37 |
| QS- | [WORKFLOWS](../03-workflows/WORKFLOWS/README.md) | 24 |
| L- | [WORKFLOWS](../03-workflows/WORKFLOWS/README.md) | 50 |
| ROUTE- | [ROUTE_CONTRACTS](../03-workflows/ROUTE_CONTRACTS.md) | 220 |
| RC- | [ROUTE_CONTRACTS](../03-workflows/ROUTE_CONTRACTS.md) | 6 |
| INT- | [INTERACTION_CONTRACTS](../03-workflows/INTERACTION_CONTRACTS.md) | 176 |
| IG- | [INTERACTION_CONTRACTS](../03-workflows/INTERACTION_CONTRACTS.md) | 5 |
| D-DB- | [DATABASE](../04-architecture/DATABASE/README.md) | 14 |
| C- | [DATABASE](../04-architecture/DATABASE/README.md) | 65 |
| AZ- | [PERMISSIONS_MATRIX](../05-security/PERMISSIONS_MATRIX.md) | 13 |
| RG- | [PERMISSIONS_MATRIX](../05-security/PERMISSIONS_MATRIX.md) | 12 |
| CS- | [PERMISSIONS_MATRIX](../05-security/PERMISSIONS_MATRIX.md) | 8 |
| PJ- | [PERMISSIONS_MATRIX](../05-security/PERMISSIONS_MATRIX.md) | 23 |
| RD- | [PERMISSIONS_MATRIX](../05-security/PERMISSIONS_MATRIX.md) | 10 |
| XL- | [PERMISSIONS_MATRIX](../05-security/PERMISSIONS_MATRIX.md) | 5 |
| OD- | [PERMISSIONS_MATRIX](../05-security/PERMISSIONS_MATRIX.md) | 16 |
| DP- | [PERMISSIONS_MATRIX](../05-security/PERMISSIONS_MATRIX.md) | 20 |
| W4- | [PERMISSIONS_MATRIX](../05-security/PERMISSIONS_MATRIX.md) | 27 |
| D-PM- | [PERMISSIONS_MATRIX](../05-security/PERMISSIONS_MATRIX.md) | 12 |
| AU- | [SECURITY](../05-security/SECURITY.md) | 25 |
| EN- | [SECURITY](../05-security/SECURITY.md) | 8 |
| WS- | [SECURITY](../05-security/SECURITY.md) | 12 |
| FL- | [SECURITY](../05-security/SECURITY.md) | 11 |
| LG- | [SECURITY](../05-security/SECURITY.md) | 7 |
| SX- | [SECURITY](../05-security/SECURITY.md) | 7 |
| TM- | [SECURITY](../05-security/SECURITY.md) | 73 |
| D-SEC- | [SECURITY](../05-security/SECURITY.md) | 21 |
| H6- | [SECURITY](../05-security/SECURITY.md) | 16 |
| H7- | [SECURITY](../05-security/SECURITY.md) | 19 |
| H8- | [SECURITY](../05-security/SECURITY.md) | 22 |
| H9- | [SECURITY](../05-security/SECURITY.md) | 15 |
| TX- | [CONCURRENCY_IDEMPOTENCY](../06-api-performance/CONCURRENCY_IDEMPOTENCY/README.md) | 10 |
| D-CC- | [CONCURRENCY_IDEMPOTENCY](../06-api-performance/CONCURRENCY_IDEMPOTENCY/README.md) | 12 |
| LR- | [CONCURRENCY_IDEMPOTENCY](../06-api-performance/CONCURRENCY_IDEMPOTENCY/README.md) | 12 |
| LK- | [CONCURRENCY_IDEMPOTENCY](../06-api-performance/CONCURRENCY_IDEMPOTENCY/README.md) | 32 |
| NX- | [CONCURRENCY_IDEMPOTENCY](../06-api-performance/CONCURRENCY_IDEMPOTENCY/README.md) | 16 |
| GU- | [CONCURRENCY_IDEMPOTENCY](../06-api-performance/CONCURRENCY_IDEMPOTENCY/README.md) | 7 |
| FU- | [CONCURRENCY_IDEMPOTENCY](../06-api-performance/CONCURRENCY_IDEMPOTENCY/README.md) | 13 |
| ST- | [CONCURRENCY_IDEMPOTENCY](../06-api-performance/CONCURRENCY_IDEMPOTENCY/README.md) | 9 |
| CI- | [CONCURRENCY_IDEMPOTENCY](../06-api-performance/CONCURRENCY_IDEMPOTENCY/README.md) | 9 |
| BD- | [CONCURRENCY_IDEMPOTENCY](../06-api-performance/CONCURRENCY_IDEMPOTENCY/README.md) | 19 |
| SQ- | [CONCURRENCY_IDEMPOTENCY](../06-api-performance/CONCURRENCY_IDEMPOTENCY/README.md) | 23 |
| RY- | [CONCURRENCY_IDEMPOTENCY](../06-api-performance/CONCURRENCY_IDEMPOTENCY/README.md) | 9 |
| NM- | [CONCURRENCY_IDEMPOTENCY](../06-api-performance/CONCURRENCY_IDEMPOTENCY/README.md) | 10 |
| JB- | [CONCURRENCY_IDEMPOTENCY](../06-api-performance/CONCURRENCY_IDEMPOTENCY/README.md) | 9 |
| RV- | [CONCURRENCY_IDEMPOTENCY](../06-api-performance/CONCURRENCY_IDEMPOTENCY/README.md) | 18 |
| M- | [CONCURRENCY_IDEMPOTENCY](../06-api-performance/CONCURRENCY_IDEMPOTENCY/README.md) | 46 |
| HO- | [CONCURRENCY_IDEMPOTENCY](../06-api-performance/CONCURRENCY_IDEMPOTENCY/README.md) | 41 |
| AS- | [PERFORMANCE](../06-api-performance/PERFORMANCE.md) | 8 |
| D-PF- | [PERFORMANCE](../06-api-performance/PERFORMANCE.md) | 8 |
| PF- | [PERFORMANCE](../06-api-performance/PERFORMANCE.md) | 43 |
| QB- | [PERFORMANCE](../06-api-performance/PERFORMANCE.md) | 17 |
| CP- | [PERFORMANCE](../06-api-performance/PERFORMANCE.md) | 7 |
| GT- | [PERFORMANCE](../06-api-performance/PERFORMANCE.md) | 7 |
| IX- | [PERFORMANCE](../06-api-performance/PERFORMANCE.md) | 19 |
| SZ- | [PERFORMANCE](../06-api-performance/PERFORMANCE.md) | 9 |
| RB- | [API_AND_INTEGRATIONS](../06-api-performance/API_AND_INTEGRATIONS.md) | 10 |
| IC- | [API_AND_INTEGRATIONS](../06-api-performance/API_AND_INTEGRATIONS.md) | 14 |
| UXN- | [INFORMATION_ARCHITECTURE](../07-ux-design/INFORMATION_ARCHITECTURE.md) | 26 |
| D-IA- | [INFORMATION_ARCHITECTURE](../07-ux-design/INFORMATION_ARCHITECTURE.md) | 23 |
| GL- | [INFORMATION_ARCHITECTURE](../07-ux-design/INFORMATION_ARCHITECTURE.md) | 212 |
| PG- | [INFORMATION_ARCHITECTURE](../07-ux-design/INFORMATION_ARCHITECTURE.md) | 65 |
| SCR- | [INFORMATION_ARCHITECTURE](../07-ux-design/INFORMATION_ARCHITECTURE.md) | 60 |
| UXI- | [ADMIN_FLOW](../07-ux-design/ADMIN_FLOW/README.md) | 42 |
| D-UX- | [ADMIN_FLOW](../07-ux-design/ADMIN_FLOW/README.md) | 12 |
| IP- | [ADMIN_FLOW](../07-ux-design/ADMIN_FLOW/README.md) | 21 |
| J- | [ADMIN_FLOW](../07-ux-design/ADMIN_FLOW/README.md) | 39 |
| MSG- | [ADMIN_FLOW](../07-ux-design/ADMIN_FLOW/README.md) | 41 |
| UXS- | [ADMIN_FLOW](../07-ux-design/ADMIN_FLOW/README.md) | 44 |
| UXH- | [ADMIN_FLOW](../07-ux-design/ADMIN_FLOW/README.md) | 12 |
| UXV- | [DESIGN_SYSTEM](../07-ux-design/DESIGN_SYSTEM.md) | 22 |
| D-DS- | [DESIGN_SYSTEM](../07-ux-design/DESIGN_SYSTEM.md) | 23 |
| PT- | [DESIGN_SYSTEM](../07-ux-design/DESIGN_SYSTEM.md) | 31 |
| KB- | [DESIGN_SYSTEM](../07-ux-design/DESIGN_SYSTEM.md) | 11 |
| SLOP- | [DESIGN_SYSTEM](../07-ux-design/DESIGN_SYSTEM.md) | 45 |
| DA- | [DESIGN_SYSTEM](../07-ux-design/DESIGN_SYSTEM.md) | 13 |
| GR- | [DESIGN_SYSTEM](../07-ux-design/DESIGN_SYSTEM.md) | 23 |
| NAV- | [NAVIGATION_CONTRACTS](../07-ux-design/NAVIGATION_CONTRACTS.md) | 65 |
| NV- | [NAVIGATION_CONTRACTS](../07-ux-design/NAVIGATION_CONTRACTS.md) | 8 |
| TS- | [TEST_STRATEGY](../08-testing/TEST_STRATEGY.md) | 12 |
| TL- | [TEST_STRATEGY](../08-testing/TEST_STRATEGY.md) | 16 |
| CJ- | [TEST_STRATEGY](../08-testing/TEST_STRATEGY.md) | 22 |
| FX- | [TEST_STRATEGY](../08-testing/TEST_STRATEGY.md) | 10 |
| SM- | [SECURITY_TEST_MATRIX](../08-testing/SECURITY_TEST_MATRIX.md) | 25 |
| PFM- | [PERFORMANCE_TARGETS](../08-testing/PERFORMANCE_TARGETS.md) | 20 |
| DOD- | [DEFINITION_OF_DONE](../08-testing/DEFINITION_OF_DONE.md) | 22 |
| INF- | [INFRASTRUCTURE](../09-operations/INFRASTRUCTURE.md) | 8 |
| OPI- | [INFRASTRUCTURE](../09-operations/INFRASTRUCTURE.md) | 15 |
| OPI- | [BACKUP_RECOVERY](../09-operations/BACKUP_RECOVERY.md) | 6 |
| BKR- | [BACKUP_RECOVERY](../09-operations/BACKUP_RECOVERY.md) | 9 |
| TT-F | [BACKUP_RECOVERY](../09-operations/BACKUP_RECOVERY.md) | 30 |
| RL- | [RELEASE_PLAN](../10-release/RELEASE_PLAN.md) | 8 |
| REL- | [RELEASE_PLAN](../10-release/RELEASE_PLAN.md) | 13 |
| IMP- | [RELEASE_PLAN](../10-release/RELEASE_PLAN.md) | 13 |
| CUT- | [RELEASE_PLAN](../10-release/RELEASE_PLAN.md) | 8 |
| RBK- | [RELEASE_PLAN](../10-release/RELEASE_PLAN.md) | 6 |
| UAT- | [RELEASE_PLAN](../10-release/RELEASE_PLAN.md) | 4 |
| GLC- | [RELEASE_PLAN](../10-release/RELEASE_PLAN.md) | 26 |
| PRQ- | [RELEASE_PLAN](../10-release/RELEASE_PLAN.md) | 15 |
| table (logical table) | [DATABASE](../04-architecture/DATABASE/README.md) | 125 |
| capability | [PERMISSIONS_MATRIX](../05-security/PERMISSIONS_MATRIX.md) | 80 |
| migration rule (DATABASE §24) | [DATABASE](../04-architecture/DATABASE/README.md) | 10 |
| component (DESIGN_SYSTEM §10) | [DESIGN_SYSTEM](../07-ux-design/DESIGN_SYSTEM.md) | 44 |
| localization rule | [LOCALIZATION](../07-ux-design/LOCALIZATION.md) | 14 |
| localization key group | [LOCALIZATION](../07-ux-design/LOCALIZATION.md) | 7 |
| GAP- (OPEN) | [GAP_REGISTER](../00-governance/GAP_REGISTER.md) | 47 |

### 1.2 Families left out, with their reason

- **Governance, not a requirement of the build.** The decision log's DIR-, APPR-, TECH-, OBS-, PROP- and RISK- (an accepted risk is an ACCEPTED_RISK gap, not an OPEN one); the Owner's decision sets — D1–D7, D10, C1, C2, F1–F6, both K1–K4 sets, K1–K3, C-1, C-2, D-1–D-5, C1–C3, OC-, C1A-, OV, VD-, RH-, GD-, SD-, P-1–P-7, CQ-, CA-, CD-, CB-, OB- and OE- —, each decision's effect being stated in the normative document this inventory reads; the Planning interpretations H-1–H-5, PL-, VP-, GP- and SP-nn; Planning's names G-P7, G-P8–10 and G-P11 for the runs and gates of the seventy-third source record; the Owner's checkpoints CP-1R and CP-A; the runs, workflows and gates with their items — S2-0–S2-4, PC-1–PC-5, OG-0–OG-4, WF-1–WF-4, WF-2R, WF-PC1, WF-PC3, WF-PC4, WF-PC5-W, WF-PC5-R, R0, R1, VC0, VC1, GA0, GA1, G0, G1, gate P7, P8-nn, P9-nn, P10-nn, PC1A-, PC1-, PC2-, PC3-, PC4A-, PC4-, PC5A-, PC5-, P11-, S20-…, S21-…, S22-…, GF1-, GF2- and GF2A-; the review findings R1-01…, S4F-, A1-–A3-, PC4F-, PC-4's labels C1-nn, B1-nn and B2-nn, BR2R-, MS2R- and P11F-; FA- and GF-, the design reviews' findings: those DESIGN_SYSTEM Annex A.2 carries as rules, with PB-A12, PB-D01 and PB-D02 of Annex A.3, reach the Tasks through DA-06 and DA-07, mapped to the design-system Task and to the page Tasks the page-specific ones bind — TASK-029 for GF-22's composer totals band, TASK-059 for FA-22's empty-search status line; the Owner questions OQ-, P8Q-, P9Q- and P10Q-, each decided answer applied in its document; P11's own open questions P11Q-01–P11Q-06 and the gaps GAP-065–GAP-069 recorded for the five of them that would change P0–P10, which TASK_PLAN §8 states with their recommendations and each affected Task names in its DEPENDS ON; the source records, "DIR-046 §2", OB §n and AICWDF §n — the brief's and the framework's requirements are carried here by REF-, IMG- and OWN- and by [AICWDF_ADOPTION](../00-governance/AICWDF_ADOPTION.md); TASK-nnn, P11's own.
- **Historical.** TT-, the tabletop exercise's steps, held in run PC-4 (its findings TT-F are included); the pilot's plan labels BR-, TB- and PB- (PB-A12, PB-D01 and PB-D02 carried through DA-07, above); Planning's reservation checks RSV-1–RSV-9, superseded by S2-R01–S2-R09; the pilot's page prefix `p7r3-`; UF-01, R-01–R-04, D1–D6, M1–M8, FM-, CP-0–CP-2, GF-2B, WF-4B and R1–R5.
- **Defined only in a draft.** The Stage 2 drafts' AK-, S2-J, S2-R, SV-, SS-, BP-, WFL-, PS-, SIM-, RS-, the sessions P7R2-S… and P7R3-S…, AN-, REF-M and REF-V, DC- and UT-, and GR-24 onward of the Stage 2 deviation register: their normative outcome is in the Stage 3 documents read here — DESIGN_SYSTEM's Annexes A and B (DA-, GR-01–GR-23) among them.

### 1.3 How an entry is mapped

By script, in this order: **structural rules** — a page (PG-) to the Task that builds it; a navigation contract (NAV-) by its page; a route (ROUTE-) — a page view by its page, a command by its action, `MODULE Action`, to the Task that implements it; an interaction contract (INT-) by its route, the shared ones INT-001–INT-020 to the platform Tasks; a workflow (WF-) to its Task; a logical table to its schema Task; a capability to the authorization core; a critical journey (CJ-) to its journey Task; a document type (DOC-) to the document engine and the Task of the module that issues it. **Explicit rules** for the families mapped one by one — the indivisible actions (AX-), subflows (SF-), capabilities (CAP-), authentication (AU-), web security (WS-), secrets (SX-), P9 handoffs (H9-), command families (NX-), interaction patterns (IP-), design patterns (PT-), invariants (INF-), operator inputs (OPI-), go-live items (GLC-), production-release requirements (PRQ-), dependencies (DEP-), backup contract (BK-), backup rules (BKR-), carried items (GR-), test layers (TL-) and the OPEN gaps. **Derived** — every other entry takes the Tasks of the identifiers its own row cites, restricted per family to identifiers of the same kind of obligation: a constraint by its tables, actions and business rules; an acceptance criterion by its capabilities; a threat or a security test by its controls; a journey or a screen by its workflows and pages. **Family default** — an entry whose row cites nothing that resolves takes its family's default: a correction row the correction cases' Task, a handoff the Tasks of the phase it hands to, a tabletop finding the runbooks' Task. **Corrected by the contracts** — once the Tasks were written, run PC-5's review found mappings that named the wrong Tasks (P11F-): each row of the V1-level, handoff, correction, tabletop, scenario, journey and UX-scenario families is also mapped to the Tasks whose contracts cite it in scope — slash and range lists expanded, an identifier cited only as a contrast ignored, a default replaced where the citing Task builds it —, and, after the convergence review of the fixes, the V1-level rows also to the Tasks that build their sub-capability without citing them, a Task that reached a row only through the broad sets of AC-16 or CAP-13 dropped; each constraint the database enforces, each index and each migration rule also to the schema Task that creates its tables, each schema Task for its own part of one that spans several — a trigger that carries a command's rule, as C-50's and C-51's, staying with that rule's Task, and a constraint the application alone enforces with the Tasks that enforce it —; and the rows the review named one by one, as the [P11 gate record](evidence/P11_QUALITY_GATE.md#pc-5-review) records. **Disposition** — §3.

## 2. The matrix

| Identifier | Family | Source | Task or disposition |
| --- | --- | --- | --- |
| O-01 | O- | PRODUCT_OVERVIEW · Problems and operational outcomes | TASK-011, TASK-024, TASK-025, TASK-026, TASK-027, TASK-029, TASK-032, TASK-033, TASK-034, TASK-037, TASK-056, TASK-059 |
| O-02 | O- | PRODUCT_OVERVIEW · Problems and operational outcomes | TASK-008, TASK-012, TASK-028, TASK-038, TASK-039, TASK-040, TASK-041, TASK-042, TASK-043, TASK-044, TASK-045, TASK-046, TASK-047, TASK-048 |
| O-03 | O- | PRODUCT_OVERVIEW · Problems and operational outcomes | TASK-029, TASK-035 |
| O-04 | O- | PRODUCT_OVERVIEW · Problems and operational outcomes | TASK-049, TASK-050, TASK-051, TASK-052, TASK-053 |
| O-05 | O- | PRODUCT_OVERVIEW · Problems and operational outcomes | TASK-036, TASK-051, TASK-054, TASK-060 |
| O-06 | O- | PRODUCT_OVERVIEW · Problems and operational outcomes | TASK-010, TASK-011, TASK-012, TASK-013, TASK-017, TASK-018, TASK-019, TASK-022, TASK-024, TASK-031, TASK-069 |
| O-07 | O- | PRODUCT_OVERVIEW · Problems and operational outcomes | TASK-001, TASK-014, TASK-015, TASK-057, TASK-066, TASK-067, TASK-074, TASK-081, TASK-084 |
| CAP-14 | CAP- | V1_SCOPE · MUST SHIP | TASK-014, TASK-015, TASK-066, TASK-067 |
| CAP-13 | CAP- | V1_SCOPE · MUST SHIP | TASK-010, TASK-011, TASK-012, TASK-013, TASK-017, TASK-018, TASK-019, TASK-020, TASK-022, TASK-023, TASK-024, TASK-031, TASK-069 |
| CAP-16 | CAP- | V1_SCOPE · MUST SHIP | TASK-001, TASK-074, TASK-075, TASK-080, TASK-081, TASK-084 |
| CAP-01 | CAP- | V1_SCOPE · MUST SHIP | TASK-011, TASK-024, TASK-025 |
| CAP-02 | CAP- | V1_SCOPE · MUST SHIP | TASK-026, TASK-037 |
| CAP-03 | CAP- | V1_SCOPE · MUST SHIP | TASK-027, TASK-059 |
| CAP-04 | CAP- | V1_SCOPE · MUST SHIP | TASK-032, TASK-033, TASK-034, TASK-056 |
| CAP-05 | CAP- | V1_SCOPE · MUST SHIP | TASK-037 |
| CAP-06 | CAP- | V1_SCOPE · MUST SHIP | TASK-008, TASK-012, TASK-028, TASK-038, TASK-039, TASK-040, TASK-041, TASK-042, TASK-043, TASK-044, TASK-046, TASK-048 |
| CAP-07 | CAP- | V1_SCOPE · MUST SHIP | TASK-045, TASK-047 |
| CAP-08 | CAP- | V1_SCOPE · MUST SHIP | TASK-021, TASK-022, TASK-029, TASK-063 |
| CAP-09 | CAP- | V1_SCOPE · MUST SHIP | TASK-030, TASK-035 |
| CAP-10 | CAP- | V1_SCOPE · MUST SHIP | TASK-049, TASK-050, TASK-051, TASK-052, TASK-053 |
| CAP-11 | CAP- | V1_SCOPE · MUST SHIP | TASK-025, TASK-036, TASK-051, TASK-054, TASK-060 |
| CAP-12 | CAP- | V1_SCOPE · MUST SHIP | TASK-032, TASK-052 |
| CAP-15 | CAP- | V1_SCOPE · MUST SHIP | TASK-057 |
| CAP-17 | CAP- | V1_SCOPE · MUST SHIP | TASK-016, TASK-058, TASK-059, TASK-060 |
| CAP-18 | CAP- | V1_SCOPE · MUST SHIP | TASK-055, TASK-056 |
| SH-01 | SH- | V1_SCOPE · SHOULD SHIP | TASK-037 |
| SH-02 | SH- | V1_SCOPE · SHOULD SHIP | TASK-015 |
| D-01 | D-0n | V1_SCOPE · DEFERRED | disposition DEFERRED |
| D-02 | D-0n | V1_SCOPE · DEFERRED | disposition DEFERRED |
| D-03 | D-0n | V1_SCOPE · DEFERRED | disposition DEFERRED |
| D-04 | D-0n | V1_SCOPE · DEFERRED | disposition DEFERRED |
| D-05 | D-0n | V1_SCOPE · DEFERRED | disposition DEFERRED |
| D-06 | D-0n | V1_SCOPE · DEFERRED | disposition DEFERRED |
| DOC-05 | DOC- | V1_SCOPE · Selectable document catalog | TASK-029, TASK-049, TASK-051, TASK-070, TASK-086 |
| DOC-01 | DOC- | V1_SCOPE · Selectable document catalog | TASK-029, TASK-033, TASK-070, TASK-086 |
| DOC-02 | DOC- | V1_SCOPE · Selectable document catalog | TASK-029, TASK-034, TASK-037, TASK-070, TASK-086 |
| DOC-03 | DOC- | V1_SCOPE · Selectable document catalog | TASK-029, TASK-034, TASK-041, TASK-045, TASK-046, TASK-070, TASK-086 |
| DOC-04 | DOC- | V1_SCOPE · Selectable document catalog | TASK-029, TASK-034, TASK-049, TASK-070, TASK-086 |
| DOC-06 | DOC- | V1_SCOPE · Selectable document catalog | TASK-029, TASK-034, TASK-049, TASK-070, TASK-086 |
| DOC-07 | DOC- | V1_SCOPE · Selectable document catalog | TASK-029, TASK-051, TASK-070, TASK-086 |
| DOC-08 | DOC- | V1_SCOPE · Selectable document catalog | TASK-029, TASK-045, TASK-047, TASK-070, TASK-086 |
| DOC-09 | DOC- | V1_SCOPE · Selectable document catalog | TASK-029, TASK-034, TASK-070, TASK-086 |
| DOC-10 | DOC- | V1_SCOPE · Selectable document catalog | TASK-029, TASK-045, TASK-070, TASK-086 |
| DOC-11 | DOC- | V1_SCOPE · Selectable document catalog | TASK-029, TASK-033, TASK-070, TASK-086 |
| DOC-12 | DOC- | V1_SCOPE · Selectable document catalog | TASK-029, TASK-039, TASK-065, TASK-070, TASK-086 |
| DOC-13 | DOC- | V1_SCOPE · Selectable document catalog | TASK-029, TASK-050, TASK-070, TASK-086 |
| DOC-14 | DOC- | V1_SCOPE · Selectable document catalog | TASK-029, TASK-034, TASK-037, TASK-070, TASK-086 |
| DEP-10 | DEP- | V1_SCOPE · Dependencies and evidence needed | TASK-082 |
| DEP-11 | DEP- | V1_SCOPE · Dependencies and evidence needed | TASK-084, TASK-085 |
| DEP-02 | DEP- | V1_SCOPE · Dependencies and evidence needed | TASK-074, TASK-084 |
| DEP-01 | DEP- | V1_SCOPE · Dependencies and evidence needed | TASK-071, TASK-079 |
| DEP-03 | DEP- | V1_SCOPE · Dependencies and evidence needed | TASK-059, TASK-070, TASK-086 |
| DEP-04 | DEP- | V1_SCOPE · Dependencies and evidence needed | TASK-029, TASK-086 |
| DEP-05 | DEP- | V1_SCOPE · Dependencies and evidence needed | TASK-057, TASK-081 |
| DEP-06 | DEP- | V1_SCOPE · Dependencies and evidence needed | TASK-086, TASK-087 |
| DEP-07 | DEP- | V1_SCOPE · Dependencies and evidence needed | TASK-001, TASK-004 |
| DEP-08 | DEP- | V1_SCOPE · Dependencies and evidence needed | TASK-017, TASK-071, TASK-080 |
| DEP-09 | DEP- | V1_SCOPE · Dependencies and evidence needed | TASK-079, TASK-086 |
| BK-01 | BK- | V1_SCOPE · V1 local backup and P9 handoff contract | TASK-074, TASK-084 |
| BK-02 | BK- | V1_SCOPE · V1 local backup and P9 handoff contract | TASK-074, TASK-084 |
| BK-03 | BK- | V1_SCOPE · V1 local backup and P9 handoff contract | TASK-072, TASK-073, TASK-074, TASK-084 |
| BK-04 | BK- | V1_SCOPE · V1 local backup and P9 handoff contract | TASK-074, TASK-075, TASK-079, TASK-084 |
| BK-05 | BK- | V1_SCOPE · V1 local backup and P9 handoff contract | TASK-074, TASK-084 |
| BK-06 | BK- | V1_SCOPE · V1 local backup and P9 handoff contract | TASK-074, TASK-084, TASK-087 |
| BK-07 | BK- | V1_SCOPE · V1 local backup and P9 handoff contract | TASK-074, TASK-084 |
| BK-08 | BK- | V1_SCOPE · V1 local backup and P9 handoff contract | TASK-074, TASK-076, TASK-084 |
| BK-09 | BK- | V1_SCOPE · V1 local backup and P9 handoff contract | TASK-074, TASK-081, TASK-084, TASK-087 |
| BK-10 | BK- | V1_SCOPE · V1 local backup and P9 handoff contract | TASK-074, TASK-075, TASK-080, TASK-084 |
| BK-11 | BK- | V1_SCOPE · V1 local backup and P9 handoff contract | TASK-074, TASK-075, TASK-079, TASK-080, TASK-084 |
| BK-12 | BK- | V1_SCOPE · V1 local backup and P9 handoff contract | TASK-074, TASK-084 |
| AC-14 | AC- | ACCEPTANCE_CRITERIA · Product acceptance contracts | TASK-014, TASK-015, TASK-064, TASK-066, TASK-067, TASK-086 |
| AC-13 | AC- | ACCEPTANCE_CRITERIA · Product acceptance contracts | TASK-011, TASK-012, TASK-024, TASK-025, TASK-040, TASK-064, TASK-069 |
| AC-18 | AC- | ACCEPTANCE_CRITERIA · Product acceptance contracts | TASK-074, TASK-081, TASK-084, TASK-087 |
| AC-01 | AC- | ACCEPTANCE_CRITERIA · Product acceptance contracts | TASK-025, TASK-064 |
| AC-02 | AC- | ACCEPTANCE_CRITERIA · Product acceptance contracts | TASK-026, TASK-037, TASK-064 |
| AC-03 | AC- | ACCEPTANCE_CRITERIA · Product acceptance contracts | TASK-027, TASK-059, TASK-062, TASK-064 |
| AC-04 | AC- | ACCEPTANCE_CRITERIA · Product acceptance contracts | TASK-032, TASK-033, TASK-034, TASK-062 |
| AC-05 | AC- | ACCEPTANCE_CRITERIA · Product acceptance contracts | TASK-037, TASK-062 |
| AC-06 | AC- | ACCEPTANCE_CRITERIA · Product acceptance contracts | TASK-039, TASK-040, TASK-041, TASK-043, TASK-044, TASK-046, TASK-048, TASK-062 |
| AC-07 | AC- | ACCEPTANCE_CRITERIA · Product acceptance contracts | TASK-045, TASK-047, TASK-062 |
| AC-08 | AC- | ACCEPTANCE_CRITERIA · Product acceptance contracts | TASK-029, TASK-047, TASK-063 |
| AC-09 | AC- | ACCEPTANCE_CRITERIA · Product acceptance contracts | TASK-035, TASK-063 |
| AC-10 | AC- | ACCEPTANCE_CRITERIA · Product acceptance contracts | TASK-049, TASK-050, TASK-051, TASK-052, TASK-053, TASK-063 |
| AC-11 | AC- | ACCEPTANCE_CRITERIA · Product acceptance contracts | TASK-036, TASK-051, TASK-054, TASK-060, TASK-063 |
| AC-12 | AC- | ACCEPTANCE_CRITERIA · Product acceptance contracts | TASK-013, TASK-029, TASK-031, TASK-040, TASK-044, TASK-048, TASK-049, TASK-050, TASK-051, TASK-056, TASK-063 |
| AC-15 | AC- | ACCEPTANCE_CRITERIA · Product acceptance contracts | TASK-032, TASK-052, TASK-062 |
| AC-16 | AC- | ACCEPTANCE_CRITERIA · Product acceptance contracts | TASK-013, TASK-027, TASK-034, TASK-038, TASK-039, TASK-040, TASK-041, TASK-043, TASK-044, TASK-049, TASK-050, TASK-051, TASK-053, TASK-064 |
| AC-17 | AC- | ACCEPTANCE_CRITERIA · Product acceptance contracts | TASK-057, TASK-064, TASK-087 |
| AC-19 | AC- | ACCEPTANCE_CRITERIA · Product acceptance contracts | TASK-074, TASK-081, TASK-084 |
| AC-20 | AC- | ACCEPTANCE_CRITERIA · Product acceptance contracts | TASK-014, TASK-032, TASK-033, TASK-034, TASK-039, TASK-040, TASK-041, TASK-046, TASK-047, TASK-062 |
| AC-21 | AC- | ACCEPTANCE_CRITERIA · Product acceptance contracts | TASK-035, TASK-048, TASK-055, TASK-056, TASK-063 |
| AC-22 | AC- | ACCEPTANCE_CRITERIA · Product acceptance contracts | TASK-028, TASK-038, TASK-039, TASK-040, TASK-041, TASK-043, TASK-062 |
| AC-23 | AC- | ACCEPTANCE_CRITERIA · Product acceptance contracts | TASK-016, TASK-058, TASK-059, TASK-060, TASK-063, TASK-064, TASK-068 |
| OS-01 | OS- | ACCEPTANCE_CRITERIA · Operational success and go-live evidence | TASK-032, TASK-033, TASK-034, TASK-070, TASK-086 |
| OS-02 | OS- | ACCEPTANCE_CRITERIA · Operational success and go-live evidence | TASK-013, TASK-036, TASK-038, TASK-039, TASK-040, TASK-041, TASK-043, TASK-044, TASK-046, TASK-048, TASK-049, TASK-050, TASK-051, TASK-052, TASK-053, TASK-054, TASK-057, TASK-060, TASK-062, TASK-063, TASK-064, TASK-070, TASK-086, TASK-087 |
| OS-03 | OS- | ACCEPTANCE_CRITERIA · Operational success and go-live evidence | TASK-011, TASK-012, TASK-016, TASK-022, TASK-060, TASK-064, TASK-069, TASK-086 |
| OS-04 | OS- | ACCEPTANCE_CRITERIA · Operational success and go-live evidence | TASK-074, TASK-081, TASK-084, TASK-086 |
| OS-05 | OS- | ACCEPTANCE_CRITERIA · Operational success and go-live evidence | TASK-086 |
| OS-06 | OS- | ACCEPTANCE_CRITERIA · Operational success and go-live evidence | TASK-003, TASK-062, TASK-063, TASK-064, TASK-070, TASK-073, TASK-086 |
| REF-060 | REF- | REFERENCE_COVERAGE · PDF capability inventory | TASK-074, TASK-081, TASK-084 |
| REF-001 | REF- | REFERENCE_COVERAGE · PDF capability inventory | TASK-017, TASK-069 |
| REF-002 | REF- | REFERENCE_COVERAGE · PDF capability inventory | TASK-011, TASK-024, TASK-025 |
| REF-003 | REF- | REFERENCE_COVERAGE · PDF capability inventory | TASK-011, TASK-024, TASK-025 |
| REF-004 | REF- | REFERENCE_COVERAGE · PDF capability inventory | TASK-011, TASK-012, TASK-024, TASK-025, TASK-069 |
| REF-005 | REF- | REFERENCE_COVERAGE · PDF capability inventory | TASK-025 |
| REF-006 | REF- | REFERENCE_COVERAGE · PDF capability inventory | TASK-025 |
| REF-007 | REF- | REFERENCE_COVERAGE · PDF capability inventory | TASK-058 |
| REF-008 | REF- | REFERENCE_COVERAGE · PDF capability inventory | TASK-058 |
| REF-009 | REF- | REFERENCE_COVERAGE · PDF capability inventory | TASK-026, TASK-037 |
| REF-010 | REF- | REFERENCE_COVERAGE · PDF capability inventory | TASK-026, TASK-037 |
| REF-011 | REF- | REFERENCE_COVERAGE · PDF capability inventory | TASK-026, TASK-037 |
| REF-012 | REF- | REFERENCE_COVERAGE · PDF capability inventory | TASK-027 |
| REF-013 | REF- | REFERENCE_COVERAGE · PDF capability inventory | TASK-027 |
| REF-014 | REF- | REFERENCE_COVERAGE · PDF capability inventory | TASK-027 |
| REF-015 | REF- | REFERENCE_COVERAGE · PDF capability inventory | TASK-027 |
| REF-016 | REF- | REFERENCE_COVERAGE · PDF capability inventory | TASK-025, TASK-032, TASK-036, TASK-037, TASK-049, TASK-051, TASK-052, TASK-054, TASK-060 |
| REF-017 | REF- | REFERENCE_COVERAGE · PDF capability inventory | TASK-032, TASK-033, TASK-034 |
| REF-018 | REF- | REFERENCE_COVERAGE · PDF capability inventory | TASK-032, TASK-033, TASK-034 |
| REF-019 | REF- | REFERENCE_COVERAGE · PDF capability inventory | TASK-037 |
| REF-020 | REF- | REFERENCE_COVERAGE · PDF capability inventory | TASK-037 |
| REF-021 | REF- | REFERENCE_COVERAGE · PDF capability inventory | TASK-039, TASK-040, TASK-041 |
| REF-022 | REF- | REFERENCE_COVERAGE · PDF capability inventory | TASK-039, TASK-040, TASK-041 |
| REF-023 | REF- | REFERENCE_COVERAGE · PDF capability inventory | TASK-028, TASK-039, TASK-040, TASK-041 |
| REF-024 | REF- | REFERENCE_COVERAGE · PDF capability inventory | TASK-027, TASK-039, TASK-040, TASK-041 |
| REF-025 | REF- | REFERENCE_COVERAGE · PDF capability inventory | TASK-039, TASK-040, TASK-041 |
| REF-026 | REF- | REFERENCE_COVERAGE · PDF capability inventory | TASK-039, TASK-040, TASK-041 |
| REF-027 | REF- | REFERENCE_COVERAGE · PDF capability inventory | TASK-039, TASK-040, TASK-041, TASK-043 |
| REF-028 | REF- | REFERENCE_COVERAGE · PDF capability inventory | TASK-028, TASK-039, TASK-040, TASK-041 |
| REF-029 | REF- | REFERENCE_COVERAGE · PDF capability inventory | TASK-039, TASK-040, TASK-041, TASK-044, TASK-048 |
| REF-030 | REF- | REFERENCE_COVERAGE · PDF capability inventory | TASK-039, TASK-040, TASK-041, TASK-046 |
| REF-031 | REF- | REFERENCE_COVERAGE · PDF capability inventory | TASK-045 |
| REF-032 | REF- | REFERENCE_COVERAGE · PDF capability inventory | TASK-025, TASK-029 |
| REF-033 | REF- | REFERENCE_COVERAGE · PDF capability inventory | TASK-029, TASK-033 |
| REF-034 | REF- | REFERENCE_COVERAGE · PDF capability inventory | TASK-025, TASK-029, TASK-030, TASK-035 |
| REF-035 | REF- | REFERENCE_COVERAGE · PDF capability inventory | TASK-029 |
| REF-036 | REF- | REFERENCE_COVERAGE · PDF capability inventory | TASK-021, TASK-029 |
| REF-037 | REF- | REFERENCE_COVERAGE · PDF capability inventory | TASK-035 |
| REF-038 | REF- | REFERENCE_COVERAGE · PDF capability inventory | TASK-032, TASK-052 |
| REF-039 | REF- | REFERENCE_COVERAGE · PDF capability inventory | TASK-049, TASK-050 |
| REF-040 | REF- | REFERENCE_COVERAGE · PDF capability inventory | TASK-049, TASK-050 |
| REF-041 | REF- | REFERENCE_COVERAGE · PDF capability inventory | TASK-049, TASK-050, TASK-051, TASK-053 |
| REF-042 | REF- | REFERENCE_COVERAGE · PDF capability inventory | TASK-049, TASK-050, TASK-051, TASK-053 |
| REF-043 | REF- | REFERENCE_COVERAGE · PDF capability inventory | TASK-049, TASK-050, TASK-051, TASK-053 |
| REF-044 | REF- | REFERENCE_COVERAGE · PDF capability inventory | TASK-036, TASK-051, TASK-054, TASK-060 |
| REF-045 | REF- | REFERENCE_COVERAGE · PDF capability inventory | TASK-036, TASK-051, TASK-054, TASK-060 |
| REF-046 | REF- | REFERENCE_COVERAGE · PDF capability inventory | TASK-058, TASK-060 |
| REF-047 | REF- | REFERENCE_COVERAGE · PDF capability inventory | TASK-029, TASK-058, TASK-060 |
| REF-048 | REF- | REFERENCE_COVERAGE · PDF capability inventory | TASK-058 |
| REF-049 | REF- | REFERENCE_COVERAGE · PDF capability inventory | TASK-010, TASK-024, TASK-027, TASK-029, TASK-040, TASK-049, TASK-050, TASK-051, TASK-061 |
| REF-050 | REF- | REFERENCE_COVERAGE · PDF capability inventory | TASK-016, TASK-058, TASK-059 |
| REF-051 | REF- | REFERENCE_COVERAGE · PDF capability inventory | TASK-057 |
| REF-052 | REF- | REFERENCE_COVERAGE · PDF capability inventory | TASK-014, TASK-015 |
| REF-053 | REF- | REFERENCE_COVERAGE · PDF capability inventory | TASK-014, TASK-015 |
| REF-054 | REF- | REFERENCE_COVERAGE · PDF capability inventory | TASK-014, TASK-015 |
| REF-055 | REF- | REFERENCE_COVERAGE · PDF capability inventory | TASK-014, TASK-015 |
| REF-056 | REF- | REFERENCE_COVERAGE · PDF capability inventory | TASK-001, TASK-011, TASK-012, TASK-013, TASK-017, TASK-022, TASK-069 |
| REF-057 | REF- | REFERENCE_COVERAGE · PDF capability inventory | TASK-005, TASK-006, TASK-007, TASK-008, TASK-009, TASK-013, TASK-029, TASK-031, TASK-049, TASK-050 |
| REF-058 | REF- | REFERENCE_COVERAGE · PDF capability inventory | TASK-013, TASK-029, TASK-039, TASK-040, TASK-041, TASK-051 |
| REF-059 | REF- | REFERENCE_COVERAGE · PDF capability inventory | TASK-058, TASK-068 |
| REF-061 | REF- | REFERENCE_COVERAGE · PDF capability inventory | TASK-003, TASK-004, TASK-068 |
| REF-062 | REF- | REFERENCE_COVERAGE · PDF capability inventory | TASK-071, TASK-074, TASK-079, TASK-081, TASK-084 |
| IMG-01 | IMG- | REFERENCE_COVERAGE · Image and non-tabular PDF inventory | TASK-011, TASK-017, TASK-024, TASK-025, TASK-026, TASK-027, TASK-032, TASK-033, TASK-034, TASK-037, TASK-056, TASK-059, TASK-062, TASK-064 |
| IMG-02 | IMG- | REFERENCE_COVERAGE · Image and non-tabular PDF inventory | TASK-029, TASK-032, TASK-033, TASK-034, TASK-047, TASK-056, TASK-062, TASK-063 |
| IMG-03 | IMG- | REFERENCE_COVERAGE · Image and non-tabular PDF inventory | TASK-008, TASK-012, TASK-013, TASK-027, TASK-028, TASK-034, TASK-037, TASK-038, TASK-039, TASK-040, TASK-041, TASK-042, TASK-043, TASK-044, TASK-046, TASK-048, TASK-062, TASK-064 |
| IMG-04 | IMG- | REFERENCE_COVERAGE · Image and non-tabular PDF inventory | TASK-013, TASK-014, TASK-027, TASK-028, TASK-032, TASK-033, TASK-034, TASK-038, TASK-039, TASK-040, TASK-041, TASK-043, TASK-044, TASK-046, TASK-047, TASK-048, TASK-059, TASK-062, TASK-064 |
| IMG-05 | IMG- | REFERENCE_COVERAGE · Image and non-tabular PDF inventory | TASK-035, TASK-045, TASK-047, TASK-048, TASK-055, TASK-056, TASK-062, TASK-063 |
| IMG-06 | IMG- | REFERENCE_COVERAGE · Image and non-tabular PDF inventory | TASK-029, TASK-030, TASK-033, TASK-034, TASK-035, TASK-037, TASK-039, TASK-041, TASK-045, TASK-047, TASK-049, TASK-050, TASK-051, TASK-063, TASK-065, TASK-070, TASK-086 |
| IMG-07 | IMG- | REFERENCE_COVERAGE · Image and non-tabular PDF inventory | TASK-036, TASK-049, TASK-050, TASK-051, TASK-052, TASK-053, TASK-054, TASK-060, TASK-063 |
| IMG-08 | IMG- | REFERENCE_COVERAGE · Image and non-tabular PDF inventory | TASK-035, TASK-048, TASK-055, TASK-056, TASK-063 |
| IMG-09 | IMG- | REFERENCE_COVERAGE · Image and non-tabular PDF inventory | TASK-014, TASK-015, TASK-016, TASK-055, TASK-056, TASK-058, TASK-059, TASK-060, TASK-063, TASK-064, TASK-066, TASK-067, TASK-068 |
| OWN-06 | OWN- | REFERENCE_COVERAGE · Final Owner decision coverage | TASK-001, TASK-074, TASK-081, TASK-084, TASK-087 |
| OWN-01 | OWN- | REFERENCE_COVERAGE · Final Owner decision coverage | TASK-013, TASK-027, TASK-029, TASK-034, TASK-036, TASK-038, TASK-039, TASK-040, TASK-041, TASK-043, TASK-044, TASK-048, TASK-049, TASK-050, TASK-051, TASK-052, TASK-053, TASK-054, TASK-056, TASK-060, TASK-063, TASK-064 |
| OWN-02 | OWN- | REFERENCE_COVERAGE · Final Owner decision coverage | TASK-011, TASK-012, TASK-013, TASK-016, TASK-022, TASK-024, TASK-025, TASK-040, TASK-060, TASK-064, TASK-069 |
| OWN-03 | OWN- | REFERENCE_COVERAGE · Final Owner decision coverage | TASK-035, TASK-048, TASK-055, TASK-056, TASK-063 |
| OWN-04 | OWN- | REFERENCE_COVERAGE · Final Owner decision coverage | TASK-008, TASK-012, TASK-013, TASK-027, TASK-028, TASK-034, TASK-038, TASK-039, TASK-040, TASK-041, TASK-042, TASK-043, TASK-044, TASK-046, TASK-048, TASK-062, TASK-064 |
| OWN-05 | OWN- | REFERENCE_COVERAGE · Final Owner decision coverage | TASK-008, TASK-012, TASK-024, TASK-025, TASK-028, TASK-036, TASK-038, TASK-039, TASK-040, TASK-041, TASK-042, TASK-043, TASK-044, TASK-046, TASK-048, TASK-051, TASK-054, TASK-060, TASK-062, TASK-063, TASK-064, TASK-069 |
| BR-INV-13 | BR- | BUSINESS_RULES · Inventory (INV) | TASK-038, TASK-039, TASK-041, TASK-042, TASK-043 |
| BR-XD-06 | BR- | BUSINESS_RULES · 6. Cross-domain interaction rules | TASK-034, TASK-049, TASK-055 |
| BR-FIN-05 | BR- | BUSINESS_RULES · Finance (FIN) | TASK-049, TASK-050, TASK-051, TASK-052, TASK-053, TASK-054 |
| BR-FIN-11 | BR- | BUSINESS_RULES · Finance (FIN) | TASK-049, TASK-050, TASK-051, TASK-052, TASK-053, TASK-054 |
| BR-FIN-16 | BR- | BUSINESS_RULES · Finance (FIN) | TASK-049, TASK-050, TASK-051, TASK-052, TASK-053, TASK-054 |
| BR-XC-01 | BR- | BUSINESS_RULES · Audit convention | TASK-010 |
| BR-XC-02 | BR- | BUSINESS_RULES · Audit convention | TASK-010 |
| BR-XC-03 | BR- | BUSINESS_RULES · Audit convention | TASK-010 |
| BR-DT-01 | BR- | BUSINESS_RULES · Dates and backdating — DIR-018 | TASK-013, TASK-015 |
| BR-DT-02 | BR- | BUSINESS_RULES · Dates and backdating — DIR-018 | TASK-013, TASK-015 |
| BR-DT-03 | BR- | BUSINESS_RULES · Dates and backdating — DIR-018 | TASK-013, TASK-015 |
| BR-DT-04 | BR- | BUSINESS_RULES · Dates and backdating — DIR-018 | TASK-013, TASK-015 |
| BR-DT-05 | BR- | BUSINESS_RULES · Dates and backdating — DIR-018 | TASK-013, TASK-015 |
| BR-DT-06 | BR- | BUSINESS_RULES · Dates and backdating — DIR-018 | TASK-013, TASK-015 |
| BR-SN-01 | BR- | BUSINESS_RULES · Historical immutability and snapshots | TASK-005, TASK-029 |
| BR-SN-02 | BR- | BUSINESS_RULES · Historical immutability and snapshots | TASK-005, TASK-029 |
| BR-SN-03 | BR- | BUSINESS_RULES · Historical immutability and snapshots | TASK-005, TASK-029 |
| BR-CR-01 | BR- | BUSINESS_RULES · 2. Correction taxonomy | TASK-013, TASK-031 |
| BR-CR-02 | BR- | BUSINESS_RULES · 2. Correction taxonomy | TASK-013, TASK-031 |
| BR-CR-03 | BR- | BUSINESS_RULES · 2. Correction taxonomy | TASK-013, TASK-031 |
| BR-CR-04 | BR- | BUSINESS_RULES · 2. Correction taxonomy | TASK-013, TASK-031 |
| BR-CR-05 | BR- | BUSINESS_RULES · 2. Correction taxonomy | TASK-013, TASK-031 |
| BR-CR-06 | BR- | BUSINESS_RULES · 2. Correction taxonomy | TASK-013, TASK-031 |
| BR-ACC-03 | BR- | BUSINESS_RULES · Access boundary (ACC) | TASK-011, TASK-016, TASK-024 |
| BR-INV-08 | BR- | BUSINESS_RULES · Inventory (INV) | TASK-038, TASK-039, TASK-041, TASK-042, TASK-043 |
| BR-PRJ-02 | BR- | BUSINESS_RULES · SM:Project | TASK-032, TASK-034, TASK-055, TASK-056 |
| BR-PRJ-04 | BR- | BUSINESS_RULES · SM:Project | TASK-032, TASK-034, TASK-055, TASK-056 |
| BR-PRJ-01 | BR- | BUSINESS_RULES · SM:Project | TASK-032, TASK-034, TASK-055, TASK-056 |
| BR-PRJ-03 | BR- | BUSINESS_RULES · SM:Project | TASK-032, TASK-034, TASK-055, TASK-056 |
| BR-PRJ-05 | BR- | BUSINESS_RULES · SM:Project | TASK-032, TASK-034, TASK-055, TASK-056 |
| BR-PRJ-06 | BR- | BUSINESS_RULES · SM:Project | TASK-032, TASK-034, TASK-055, TASK-056 |
| BR-QUO-01 | BR- | BUSINESS_RULES · SM:Quotation | TASK-033 |
| BR-QUO-02 | BR- | BUSINESS_RULES · SM:Quotation | TASK-033 |
| BR-QUO-03 | BR- | BUSINESS_RULES · SM:Quotation | TASK-033 |
| BR-DOC-01 | BR- | BUSINESS_RULES · SM:Document (all DOC-01–14; Invoice is a specialization) | TASK-029 |
| BR-DOC-02 | BR- | BUSINESS_RULES · SM:Document (all DOC-01–14; Invoice is a specialization) | TASK-029 |
| BR-DOC-03 | BR- | BUSINESS_RULES · SM:Document (all DOC-01–14; Invoice is a specialization) | TASK-029 |
| BR-DOC-04 | BR- | BUSINESS_RULES · SM:Document (all DOC-01–14; Invoice is a specialization) | TASK-029, TASK-034, TASK-037, TASK-045, TASK-047, TASK-049, TASK-051 |
| BR-RSV-01 | BR- | BUSINESS_RULES · SM:Reservation | TASK-038 |
| BR-RSV-02 | BR- | BUSINESS_RULES · SM:Reservation | TASK-038 |
| BR-RSV-03 | BR- | BUSINESS_RULES · SM:Reservation | TASK-038 |
| BR-RSV-04 | BR- | BUSINESS_RULES · SM:Reservation | TASK-038 |
| BR-INV-01 | BR- | BUSINESS_RULES · Inventory (INV) | TASK-038, TASK-039, TASK-041, TASK-042, TASK-043 |
| BR-INV-02 | BR- | BUSINESS_RULES · Inventory (INV) | TASK-038, TASK-039, TASK-041, TASK-042, TASK-043 |
| BR-INV-03 | BR- | BUSINESS_RULES · Inventory (INV) | TASK-038, TASK-039, TASK-041, TASK-042, TASK-043 |
| BR-INV-04 | BR- | BUSINESS_RULES · Inventory (INV) | TASK-038, TASK-039, TASK-041, TASK-042, TASK-043 |
| BR-PUR-05 | BR- | BUSINESS_RULES · Procurement (PUR) | TASK-037, TASK-044 |
| BR-FIN-17 | BR- | BUSINESS_RULES · Finance (FIN) | TASK-049, TASK-050, TASK-051, TASK-052, TASK-053, TASK-054 |
| BR-INV-05 | BR- | BUSINESS_RULES · Inventory (INV) | TASK-038, TASK-039, TASK-041, TASK-042, TASK-043 |
| BR-INV-06 | BR- | BUSINESS_RULES · Inventory (INV) | TASK-038, TASK-039, TASK-041, TASK-042, TASK-043 |
| BR-INV-07 | BR- | BUSINESS_RULES · Inventory (INV) | TASK-038, TASK-039, TASK-041, TASK-042, TASK-043 |
| BR-INV-09 | BR- | BUSINESS_RULES · Inventory (INV) | TASK-038, TASK-039, TASK-041, TASK-042, TASK-043 |
| BR-INV-10 | BR- | BUSINESS_RULES · Inventory (INV) | TASK-038, TASK-039, TASK-041, TASK-042, TASK-043 |
| BR-INV-11 | BR- | BUSINESS_RULES · Inventory (INV) | TASK-038, TASK-039, TASK-041, TASK-042, TASK-043 |
| BR-INV-12 | BR- | BUSINESS_RULES · Inventory (INV) | TASK-038, TASK-039, TASK-041, TASK-042, TASK-043 |
| BR-PUR-01 | BR- | BUSINESS_RULES · Procurement (PUR) | TASK-037, TASK-044 |
| BR-PUR-02 | BR- | BUSINESS_RULES · Procurement (PUR) | TASK-037, TASK-044 |
| BR-PUR-03 | BR- | BUSINESS_RULES · Procurement (PUR) | TASK-037, TASK-044 |
| BR-PUR-04 | BR- | BUSINESS_RULES · Procurement (PUR) | TASK-037, TASK-044 |
| BR-FIN-13 | BR- | BUSINESS_RULES · Finance (FIN) | TASK-049, TASK-050, TASK-051, TASK-052, TASK-053, TASK-054 |
| BR-FIN-01 | BR- | BUSINESS_RULES · Finance (FIN) | TASK-049, TASK-050, TASK-051, TASK-052, TASK-053, TASK-054 |
| BR-FIN-02 | BR- | BUSINESS_RULES · Finance (FIN) | TASK-049, TASK-050, TASK-051, TASK-052, TASK-053, TASK-054 |
| BR-FIN-03 | BR- | BUSINESS_RULES · Finance (FIN) | TASK-049, TASK-050, TASK-051, TASK-052, TASK-053, TASK-054 |
| BR-FIN-04 | BR- | BUSINESS_RULES · Finance (FIN) | TASK-049, TASK-050, TASK-051, TASK-052, TASK-053, TASK-054 |
| BR-FIN-06 | BR- | BUSINESS_RULES · Finance (FIN) | TASK-049, TASK-050, TASK-051, TASK-052, TASK-053, TASK-054 |
| BR-FIN-07 | BR- | BUSINESS_RULES · Finance (FIN) | TASK-049, TASK-050, TASK-051, TASK-052, TASK-053, TASK-054 |
| BR-FIN-08 | BR- | BUSINESS_RULES · Finance (FIN) | TASK-049, TASK-050, TASK-051, TASK-052, TASK-053, TASK-054 |
| BR-FIN-09 | BR- | BUSINESS_RULES · Finance (FIN) | TASK-049, TASK-050, TASK-051, TASK-052, TASK-053, TASK-054 |
| BR-FIN-10 | BR- | BUSINESS_RULES · Finance (FIN) | TASK-049, TASK-050, TASK-051, TASK-052, TASK-053, TASK-054 |
| BR-FIN-12 | BR- | BUSINESS_RULES · Finance (FIN) | TASK-049, TASK-050, TASK-051, TASK-052, TASK-053, TASK-054 |
| BR-FIN-14 | BR- | BUSINESS_RULES · Finance (FIN) | TASK-049, TASK-050, TASK-051, TASK-052, TASK-053, TASK-054 |
| BR-FIN-15 | BR- | BUSINESS_RULES · Finance (FIN) | TASK-049, TASK-050, TASK-051, TASK-052, TASK-053, TASK-054 |
| BR-ADM-01 | BR- | BUSINESS_RULES · Administration (ADM) | TASK-035 |
| BR-ADM-02 | BR- | BUSINESS_RULES · Administration (ADM) | TASK-035 |
| BR-ADM-03 | BR- | BUSINESS_RULES · Administration (ADM) | TASK-035 |
| BR-ACC-01 | BR- | BUSINESS_RULES · Access boundary (ACC) | TASK-011, TASK-016, TASK-024 |
| BR-ACC-02 | BR- | BUSINESS_RULES · Access boundary (ACC) | TASK-011, TASK-016, TASK-024 |
| BR-XD-01 | BR- | BUSINESS_RULES · 6. Cross-domain interaction rules | TASK-034, TASK-049, TASK-055 |
| BR-XD-02 | BR- | BUSINESS_RULES · 6. Cross-domain interaction rules | TASK-034, TASK-049, TASK-055 |
| BR-XD-03 | BR- | BUSINESS_RULES · 6. Cross-domain interaction rules | TASK-034, TASK-049, TASK-055 |
| BR-XD-04 | BR- | BUSINESS_RULES · 6. Cross-domain interaction rules | TASK-034, TASK-049, TASK-055 |
| BR-XD-05 | BR- | BUSINESS_RULES · 6. Cross-domain interaction rules | TASK-034, TASK-049, TASK-055 |
| BR-XD-07 | BR- | BUSINESS_RULES · 6. Cross-domain interaction rules | TASK-034, TASK-049, TASK-055 |
| CALC-11 | CALC- | BUSINESS_RULES · 5. Calculation semantics | TASK-038, TASK-039, TASK-041, TASK-042, TASK-043, TASK-049, TASK-050, TASK-051, TASK-052, TASK-053, TASK-054 |
| CALC-01 | CALC- | BUSINESS_RULES · 5. Calculation semantics | TASK-038, TASK-039, TASK-041, TASK-042, TASK-043 |
| CALC-02 | CALC- | BUSINESS_RULES · 5. Calculation semantics | TASK-060 |
| CALC-03 | CALC- | BUSINESS_RULES · 5. Calculation semantics | TASK-037, TASK-044 |
| CALC-04 | CALC- | BUSINESS_RULES · 5. Calculation semantics | TASK-060 |
| CALC-05 | CALC- | BUSINESS_RULES · 5. Calculation semantics | TASK-049, TASK-050, TASK-051, TASK-052, TASK-053, TASK-054 |
| CALC-06 | CALC- | BUSINESS_RULES · 5. Calculation semantics | TASK-049, TASK-050, TASK-051, TASK-052, TASK-053, TASK-054 |
| CALC-07 | CALC- | BUSINESS_RULES · 5. Calculation semantics | TASK-049, TASK-050, TASK-051, TASK-052, TASK-053, TASK-054 |
| CALC-08 | CALC- | BUSINESS_RULES · 5. Calculation semantics | TASK-049, TASK-050, TASK-051, TASK-052, TASK-053, TASK-054 |
| CALC-09 | CALC- | BUSINESS_RULES · 5. Calculation semantics | TASK-037, TASK-038, TASK-039, TASK-041, TASK-042, TASK-043, TASK-044 |
| CALC-10 | CALC- | BUSINESS_RULES · 5. Calculation semantics | TASK-060 |
| CALC-12 | CALC- | BUSINESS_RULES · 5. Calculation semantics | TASK-060 |
| CALC-13 | CALC- | BUSINESS_RULES · 5. Calculation semantics | TASK-060 |
| CALC-14 | CALC- | BUSINESS_RULES · 5. Calculation semantics | TASK-060 |
| FS-01 | FS- | BUSINESS_RULES · 7. Forbidden shortcuts | TASK-038, TASK-039, TASK-041, TASK-042, TASK-043 |
| FS-13 | FS- | BUSINESS_RULES · 5. Calculation semantics | TASK-049, TASK-050, TASK-051, TASK-052, TASK-053, TASK-054 |
| FS-02 | FS- | BUSINESS_RULES · 7. Forbidden shortcuts | TASK-013 |
| FS-03 | FS- | BUSINESS_RULES · 7. Forbidden shortcuts | TASK-013 |
| FS-04 | FS- | BUSINESS_RULES · 7. Forbidden shortcuts | TASK-013 |
| FS-05 | FS- | BUSINESS_RULES · 7. Forbidden shortcuts | TASK-013 |
| FS-06 | FS- | BUSINESS_RULES · 7. Forbidden shortcuts | TASK-013 |
| FS-07 | FS- | BUSINESS_RULES · 7. Forbidden shortcuts | TASK-013 |
| FS-08 | FS- | BUSINESS_RULES · 7. Forbidden shortcuts | TASK-013 |
| FS-09 | FS- | BUSINESS_RULES · 7. Forbidden shortcuts | TASK-013 |
| FS-10 | FS- | BUSINESS_RULES · 7. Forbidden shortcuts | TASK-013 |
| FS-11 | FS- | BUSINESS_RULES · 7. Forbidden shortcuts | TASK-013 |
| FS-12 | FS- | BUSINESS_RULES · 7. Forbidden shortcuts | TASK-013 |
| FS-14 | FS- | BUSINESS_RULES · 7. Forbidden shortcuts | TASK-013 |
| WF-PUR-01 | WF- | WORKFLOWS · WF-PUR-01 — Project purchase `[desk]` | TASK-037 |
| WF-MIG-01 | WF- | WORKFLOWS · WF-MIG-01 — Opening facts `[desk]` | TASK-057 |
| WF-ACC-01 | WF- | WORKFLOWS · WF-ACC-01 — Session and company context `[both]` | TASK-016 |
| WF-PRJ-01 | WF- | WORKFLOWS · WF-PRJ-01 — Project setup and amendment `[both]` | TASK-032 |
| WF-QUO-01 | WF- | WORKFLOWS · WF-QUO-01 — Quotation cycle `[desk]` | TASK-033 |
| WF-PRJ-02 | WF- | WORKFLOWS · WF-PRJ-02 — Commercial confirmation `[desk]` | TASK-034 |
| WF-FUL-01 | WF- | WORKFLOWS · WF-FUL-01 — Fulfillment planning per demand line `[desk]` | TASK-034 |
| WF-FUL-02 | WF- | WORKFLOWS · WF-FUL-02 — Delivery (*Pengiriman*) `[both]` | TASK-045 |
| WF-FIN-01 | WF- | WORKFLOWS · WF-FIN-01 — Invoice (sales record) issuance `[desk]` | TASK-049 |
| WF-FIN-02 | WF- | WORKFLOWS · WF-FIN-02 — Billing activation and receivable monitoring (*Piutang*) `[desk]` | TASK-050 |
| WF-FIN-03 | WF- | WORKFLOWS · WF-FIN-03 — Payment receipt and application (*Pembayaran*) `[both]` | TASK-051 |
| WF-PRJ-03 | WF- | WORKFLOWS · WF-PRJ-03 — Project completion (*Penyelesaian*) `[desk]` | TASK-055 |
| WF-ADM-01 | WF- | WORKFLOWS · WF-ADM-01 — Administrative checklist (*Dokumen Administrasi* / SPJ) `[desk]` | TASK-035 |
| WF-INV-02 | WF- | WORKFLOWS · WF-INV-02 — Reservation management `[desk]` | TASK-038 |
| WF-INV-01 | WF- | WORKFLOWS · WF-INV-01 — Receiving (*Barang Masuk*) `[floor]` | TASK-039 |
| WF-INV-03 | WF- | WORKFLOWS · WF-INV-03 — Warehouse dispatch (*Barang Keluar*) `[floor]` | TASK-041 |
| WF-FUL-03 | WF- | WORKFLOWS · WF-FUL-03 — Drop-ship / direct fulfillment `[desk]` | TASK-046 |
| WF-FUL-04 | WF- | WORKFLOWS · WF-FUL-04 — Service fulfillment and handover `[both]` | TASK-047 |
| WF-ACC-02 | WF- | WORKFLOWS · WF-ACC-02 — Users, roles, capabilities and company grants `[desk]` — OWN! | TASK-024 |
| WF-MD-01 | WF- | WORKFLOWS · WF-MD-01 — Company master `[desk]` — OWN! | TASK-025 |
| WF-MD-02 | WF- | WORKFLOWS · WF-MD-02 — Client organization → unit → PIC `[both]` | TASK-026 |
| WF-MD-03 | WF- | WORKFLOWS · WF-MD-03 — Supplier master `[desk]` | TASK-026 |
| WF-MD-04 | WF- | WORKFLOWS · WF-MD-04 — Catalog, units, barcodes, serial policy and price defaults `[desk]` | TASK-027, TASK-043 |
| WF-MD-05 | WF- | WORKFLOWS · WF-MD-05 — Locations and minimum stock `[desk]` | TASK-028 |
| WF-FIN-07 | WF- | WORKFLOWS · WF-FIN-07 — Expenses, disbursements and other cash `[desk]` | TASK-054 |
| WF-PUR-02 | WF- | WORKFLOWS · WF-PUR-02 — Replenishment purchase `[desk]` | TASK-037 |
| WF-INV-07 | WF- | WORKFLOWS · WF-INV-07 — Inter-company allocation (embedded in dispatch) | TASK-041 |
| WF-INV-04 | WF- | WORKFLOWS · WF-INV-04 — Condition change and quarantine `[floor]` | TASK-042 |
| WF-INV-06 | WF- | WORKFLOWS · WF-INV-06 — Inventory adjustment, disposal and loss `[floor]` | TASK-042 |
| WF-FUL-05 | WF- | WORKFLOWS · WF-FUL-05 — Sales return (after delivery) `[both]` | TASK-048 |
| WF-INV-05 | WF- | WORKFLOWS · WF-INV-05 — Stock opname `[floor]` | TASK-043 |
| WF-PUR-03 | WF- | WORKFLOWS · WF-PUR-03 — Purchase return to supplier `[floor]` | TASK-044 |
| WF-DOC-01 | WF- | WORKFLOWS · WF-DOC-01 — Generated document lifecycle `[desk]` | TASK-029 |
| WF-FIN-08 | WF- | WORKFLOWS · WF-FIN-08 — Kuitansi untuk Proses Pembayaran (pre-payment Kuitansi) `[desk]` | TASK-049 |
| WF-DOC-02 | WF- | WORKFLOWS · WF-DOC-02 — External and uploaded documents `[both]` | TASK-030 |
| WF-FIN-05 | WF- | WORKFLOWS · WF-FIN-05 — Dispute hold and Owner write-off `[desk]` | TASK-050 |
| WF-FIN-04 | WF- | WORKFLOWS · WF-FIN-04 — Deduction settlement: verified intermediary fee and tax withheld/collected `[d | TASK-052 |
| WF-FIN-06 | WF- | WORKFLOWS · WF-FIN-06 — Customer credit and refund `[desk]` | TASK-053 |
| WF-PRJ-04 | WF- | WORKFLOWS · WF-PRJ-04 — Project cancellation `[desk]` | TASK-056 |
| SF-UNATTRIBUTED | SF- | WORKFLOWS · 6. Reusable subflows | TASK-043 |
| SF-CMD | SF- | WORKFLOWS · 2. Standard command envelope — SF-CMD | TASK-013 |
| SF-DATE | SF- | WORKFLOWS · 6. Reusable subflows | TASK-013, TASK-015 |
| SF-ISSUE | SF- | WORKFLOWS · 6. Reusable subflows | TASK-029 |
| SF-EVIDENCE | SF- | WORKFLOWS · 6. Reusable subflows | TASK-030 |
| SF-RESERVE | SF- | WORKFLOWS · 6. Reusable subflows | TASK-038 |
| SF-RSV-CUT | SF- | WORKFLOWS · 6. Reusable subflows | TASK-038, TASK-042 |
| SF-SCAN | SF- | WORKFLOWS · 6. Reusable subflows | TASK-039, TASK-059 |
| SF-CHARGE | SF- | WORKFLOWS · 6. Reusable subflows | TASK-036, TASK-037 |
| SF-RESTORE | SF- | WORKFLOWS · 6. Reusable subflows | TASK-041, TASK-048 |
| SF-LOSS | SF- | WORKFLOWS · 6. Reusable subflows | TASK-042 |
| SF-CASE | SF- | WORKFLOWS · 6. Reusable subflows | TASK-031 |
| SF-CANCEL-LINE | SF- | WORKFLOWS · 6. Reusable subflows | TASK-034 |
| SF-REVAL | SF- | WORKFLOWS · WF-PRJ-03 — Project completion (*Penyelesaian*) `[desk]` | TASK-055 |
| SF-RENDER | SF- | WORKFLOWS · 6. Reusable subflows | TASK-022, TASK-029 |
| SF-APPLY | SF- | WORKFLOWS · 6. Reusable subflows | TASK-051 |
| SF-CONTRA | SF- | WORKFLOWS · 6. Reusable subflows | TASK-031, TASK-052 |
| SF-SETTLE | SF- | WORKFLOWS · 6. Reusable subflows | TASK-052 |
| PX-03 | PX- | WORKFLOWS · 7. Partial-processing rules | TASK-034, TASK-038, TASK-039, TASK-041, TASK-045, TASK-051 |
| PX-08 | PX- | WORKFLOWS · 7. Partial-processing rules | TASK-034, TASK-038, TASK-039, TASK-041, TASK-045, TASK-051 |
| PX-01 | PX- | WORKFLOWS · 7. Partial-processing rules | TASK-034, TASK-038, TASK-039, TASK-041, TASK-045, TASK-051 |
| PX-02 | PX- | WORKFLOWS · 7. Partial-processing rules | TASK-034, TASK-038, TASK-039, TASK-041, TASK-045, TASK-051 |
| PX-04 | PX- | WORKFLOWS · 7. Partial-processing rules | TASK-034, TASK-038, TASK-039, TASK-041, TASK-045, TASK-051 |
| PX-05 | PX- | WORKFLOWS · 7. Partial-processing rules | TASK-034, TASK-038, TASK-039, TASK-041, TASK-045, TASK-051 |
| PX-06 | PX- | WORKFLOWS · 7. Partial-processing rules | TASK-034, TASK-038, TASK-039, TASK-041, TASK-045, TASK-051 |
| PX-07 | PX- | WORKFLOWS · 7. Partial-processing rules | TASK-034, TASK-038, TASK-039, TASK-041, TASK-045, TASK-051 |
| CM-36 | CM- | WORKFLOWS · 8. Correction, reversal and cancellation matrix | TASK-013, TASK-031, TASK-038, TASK-041, TASK-042, TASK-043, TASK-045, TASK-048 |
| CM-37 | CM- | WORKFLOWS · 8. Correction, reversal and cancellation matrix | TASK-013, TASK-031, TASK-041, TASK-044, TASK-046, TASK-048 |
| CM-24 | CM- | WORKFLOWS · 4. Actor and authority matrix | TASK-013, TASK-031, TASK-049, TASK-050, TASK-052 |
| CM-27 | CM- | WORKFLOWS · 8. Correction, reversal and cancellation matrix | TASK-013, TASK-031, TASK-034, TASK-035, TASK-037, TASK-038, TASK-043, TASK-045, TASK-046, TASK-047, TASK-050 |
| CM-17 | CM- | WORKFLOWS · 8. Correction, reversal and cancellation matrix | TASK-013, TASK-031, TASK-036, TASK-037, TASK-046, TASK-055 |
| CM-18 | CM- | WORKFLOWS · 8. Correction, reversal and cancellation matrix | TASK-013, TASK-031, TASK-037, TASK-054 |
| CM-02 | CM- | WORKFLOWS · 8. Correction, reversal and cancellation matrix | TASK-013, TASK-031, TASK-038, TASK-039, TASK-041, TASK-042, TASK-048 |
| CM-05 | CM- | WORKFLOWS · 8. Correction, reversal and cancellation matrix | TASK-013, TASK-031, TASK-038, TASK-041, TASK-048 |
| CM-06 | CM- | WORKFLOWS · 8. Correction, reversal and cancellation matrix | TASK-013, TASK-031, TASK-041, TASK-045, TASK-048 |
| CM-07 | CM- | WORKFLOWS · 8. Correction, reversal and cancellation matrix | TASK-013, TASK-031, TASK-042, TASK-045 |
| CM-28 | CM- | WORKFLOWS · 8. Correction, reversal and cancellation matrix | TASK-013, TASK-031, TASK-038, TASK-040, TASK-042, TASK-043 |
| CM-08 | CM- | WORKFLOWS · 8. Correction, reversal and cancellation matrix | TASK-013, TASK-031, TASK-045, TASK-046 |
| CM-14 | CM- | WORKFLOWS · 8. Correction, reversal and cancellation matrix | TASK-013, TASK-031, TASK-041, TASK-046, TASK-048 |
| CM-15 | CM- | WORKFLOWS · 8. Correction, reversal and cancellation matrix | TASK-013, TASK-031, TASK-042, TASK-046, TASK-048 |
| CM-12 | CM- | WORKFLOWS · 8. Correction, reversal and cancellation matrix | TASK-013, TASK-031, TASK-048, TASK-049 |
| CM-13 | CM- | WORKFLOWS · 8. Correction, reversal and cancellation matrix | TASK-013, TASK-031, TASK-048, TASK-053 |
| CM-23 | CM- | WORKFLOWS · 8. Correction, reversal and cancellation matrix | TASK-013, TASK-031, TASK-049 |
| CM-25 | CM- | WORKFLOWS · 8. Correction, reversal and cancellation matrix | TASK-013, TASK-031, TASK-049, TASK-050 |
| CM-19 | CM- | WORKFLOWS · 8. Correction, reversal and cancellation matrix | TASK-013, TASK-031, TASK-051, TASK-052, TASK-053 |
| CM-20 | CM- | WORKFLOWS · 8. Correction, reversal and cancellation matrix | TASK-013, TASK-031, TASK-051, TASK-053, TASK-054 |
| CM-21 | CM- | WORKFLOWS · 8. Correction, reversal and cancellation matrix | TASK-013, TASK-031, TASK-051, TASK-052, TASK-053 |
| CM-22 | CM- | WORKFLOWS · 8. Correction, reversal and cancellation matrix | TASK-013, TASK-031, TASK-052, TASK-054 |
| CM-38 | CM- | WORKFLOWS · 8. Correction, reversal and cancellation matrix | TASK-013, TASK-031, TASK-052, TASK-053, TASK-054 |
| CM-26 | CM- | WORKFLOWS · 8. Correction, reversal and cancellation matrix | TASK-013, TASK-029, TASK-031, TASK-049 |
| CM-33 | CM- | WORKFLOWS · 8. Correction, reversal and cancellation matrix | TASK-013, TASK-031, TASK-038, TASK-042, TASK-057, TASK-077, TASK-081, TASK-087 |
| CM-35 | CM- | WORKFLOWS · 8. Correction, reversal and cancellation matrix | TASK-013, TASK-031, TASK-036, TASK-037 |
| CM-09 | CM- | WORKFLOWS · 8. Correction, reversal and cancellation matrix | TASK-013, TASK-031, TASK-038, TASK-042, TASK-043, TASK-044 |
| CM-01 | CM- | WORKFLOWS · 8. Correction, reversal and cancellation matrix | TASK-013, TASK-031 |
| CM-03 | CM- | WORKFLOWS · 8. Correction, reversal and cancellation matrix | TASK-013, TASK-031, TASK-039 |
| CM-04 | CM- | WORKFLOWS · 8. Correction, reversal and cancellation matrix | TASK-013, TASK-031, TASK-039 |
| CM-10 | CM- | WORKFLOWS · 8. Correction, reversal and cancellation matrix | TASK-013, TASK-031, TASK-041, TASK-048 |
| CM-11 | CM- | WORKFLOWS · 8. Correction, reversal and cancellation matrix | TASK-013, TASK-031, TASK-042, TASK-044, TASK-048 |
| CM-16 | CM- | WORKFLOWS · 8. Correction, reversal and cancellation matrix | TASK-013, TASK-031, TASK-038, TASK-042, TASK-044 |
| CM-29 | CM- | WORKFLOWS · 8. Correction, reversal and cancellation matrix | TASK-013, TASK-031, TASK-034, TASK-038, TASK-056 |
| CM-30 | CM- | WORKFLOWS · 8. Correction, reversal and cancellation matrix | TASK-013, TASK-031, TASK-034, TASK-037, TASK-054, TASK-056 |
| CM-31 | CM- | WORKFLOWS · 8. Correction, reversal and cancellation matrix | TASK-013, TASK-031, TASK-034, TASK-056 |
| CM-32 | CM- | WORKFLOWS · 8. Correction, reversal and cancellation matrix | TASK-013, TASK-031, TASK-048, TASK-055, TASK-056 |
| CM-34 | CM- | WORKFLOWS · 8. Correction, reversal and cancellation matrix | TASK-013, TASK-031, TASK-042, TASK-043 |
| AX-13 | AX- | WORKFLOWS · 9. Indivisible business actions | TASK-034 |
| AX-01 | AX- | WORKFLOWS · 9. Indivisible business actions | TASK-038 |
| AX-02 | AX- | WORKFLOWS · 9. Indivisible business actions | TASK-038 |
| AX-04 | AX- | WORKFLOWS · 9. Indivisible business actions | TASK-039 |
| AX-12 | AX- | WORKFLOWS · 9. Indivisible business actions | TASK-037 |
| AX-32 | AX- | WORKFLOWS · 9. Indivisible business actions | TASK-037 |
| AX-05 | AX- | WORKFLOWS · 9. Indivisible business actions | TASK-039 |
| AX-03 | AX- | WORKFLOWS · 9. Indivisible business actions | TASK-041 |
| AX-11 | AX- | WORKFLOWS · 9. Indivisible business actions | TASK-044 |
| AX-30 | AX- | WORKFLOWS · 9. Indivisible business actions | TASK-042 |
| AX-10 | AX- | WORKFLOWS · 9. Indivisible business actions | TASK-041, TASK-048 |
| AX-06 | AX- | WORKFLOWS · 9. Indivisible business actions | TASK-042, TASK-043 |
| AX-07 | AX- | WORKFLOWS · 9. Indivisible business actions | TASK-043 |
| AX-09 | AX- | WORKFLOWS · 9. Indivisible business actions | TASK-045 |
| AX-08 | AX- | WORKFLOWS · 9. Indivisible business actions | TASK-046 |
| AX-15 | AX- | WORKFLOWS · 9. Indivisible business actions | TASK-029 |
| AX-16 | AX- | WORKFLOWS · 9. Indivisible business actions | TASK-049 |
| AX-17 | AX- | WORKFLOWS · 9. Indivisible business actions | TASK-050 |
| AX-22 | AX- | WORKFLOWS · 9. Indivisible business actions | TASK-049 |
| AX-18 | AX- | WORKFLOWS · 9. Indivisible business actions | TASK-051 |
| AX-19 | AX- | WORKFLOWS · 9. Indivisible business actions | TASK-051 |
| AX-21 | AX- | WORKFLOWS · 9. Indivisible business actions | TASK-052 |
| AX-24 | AX- | WORKFLOWS · 9. Indivisible business actions | TASK-050 |
| AX-23 | AX- | WORKFLOWS · 9. Indivisible business actions | TASK-053 |
| AX-33 | AX- | WORKFLOWS · 9. Indivisible business actions | TASK-043, TASK-052, TASK-054 |
| AX-25 | AX- | WORKFLOWS · 9. Indivisible business actions | TASK-055 |
| AX-26 | AX- | WORKFLOWS · 9. Indivisible business actions | TASK-055 |
| AX-28 | AX- | WORKFLOWS · 9. Indivisible business actions | TASK-057 |
| AX-37 | AX- | WORKFLOWS · 9. Indivisible business actions | TASK-043 |
| AX-14 | AX- | WORKFLOWS · 9. Indivisible business actions | TASK-034 |
| AX-34 | AX- | WORKFLOWS · 9. Indivisible business actions | TASK-036, TASK-037 |
| AX-35 | AX- | WORKFLOWS · 9. Indivisible business actions | TASK-041 |
| AX-36 | AX- | WORKFLOWS · 9. Indivisible business actions | TASK-048 |
| AX-20 | AX- | WORKFLOWS · 9. Indivisible business actions | TASK-051 |
| AX-27 | AX- | WORKFLOWS · 9. Indivisible business actions | TASK-056 |
| AX-29 | AX- | WORKFLOWS · 9. Indivisible business actions | TASK-036, TASK-037 |
| AX-31 | AX- | WORKFLOWS · 9. Indivisible business actions | TASK-049 |
| QS-23 | QS- | WORKFLOWS · 10. Derived signals and action queue | TASK-058 |
| QS-24 | QS- | WORKFLOWS · 10. Derived signals and action queue | TASK-058 |
| QS-14 | QS- | WORKFLOWS · 10. Derived signals and action queue | TASK-058 |
| QS-17 | QS- | WORKFLOWS · 10. Derived signals and action queue | TASK-058 |
| QS-02 | QS- | WORKFLOWS · 10. Derived signals and action queue | TASK-058 |
| QS-04 | QS- | WORKFLOWS · 10. Derived signals and action queue | TASK-058 |
| QS-20 | QS- | WORKFLOWS · 10. Derived signals and action queue | TASK-058 |
| QS-16 | QS- | WORKFLOWS · 10. Derived signals and action queue | TASK-058 |
| QS-18 | QS- | WORKFLOWS · 10. Derived signals and action queue | TASK-058 |
| QS-15 | QS- | WORKFLOWS · 10. Derived signals and action queue | TASK-058 |
| QS-10 | QS- | WORKFLOWS · 10. Derived signals and action queue | TASK-058 |
| QS-21 | QS- | WORKFLOWS · 10. Derived signals and action queue | TASK-058 |
| QS-12 | QS- | WORKFLOWS · 10. Derived signals and action queue | TASK-058 |
| QS-13 | QS- | WORKFLOWS · 10. Derived signals and action queue | TASK-058 |
| QS-07 | QS- | WORKFLOWS · 10. Derived signals and action queue | TASK-058 |
| QS-19 | QS- | WORKFLOWS · 10. Derived signals and action queue | TASK-058 |
| QS-08 | QS- | WORKFLOWS · 10. Derived signals and action queue | TASK-058 |
| QS-22 | QS- | WORKFLOWS · 10. Derived signals and action queue | TASK-058 |
| QS-01 | QS- | WORKFLOWS · 10. Derived signals and action queue | TASK-058 |
| QS-03 | QS- | WORKFLOWS · 10. Derived signals and action queue | TASK-058 |
| QS-05 | QS- | WORKFLOWS · 10. Derived signals and action queue | TASK-058 |
| QS-06 | QS- | WORKFLOWS · 10. Derived signals and action queue | TASK-058 |
| QS-09 | QS- | WORKFLOWS · 10. Derived signals and action queue | TASK-058 |
| QS-11 | QS- | WORKFLOWS · 10. Derived signals and action queue | TASK-058 |
| L-42 | L- | WORKFLOWS · 12. Planner-determined Level-1 decisions | TASK-038, TASK-039, TASK-041, TASK-042, TASK-043 |
| L-45 | L- | WORKFLOWS · 12. Planner-determined Level-1 decisions | TASK-049, TASK-050, TASK-051, TASK-052, TASK-053, TASK-054 |
| L-49 | L- | WORKFLOWS · 12. Planner-determined Level-1 decisions | TASK-034, TASK-049, TASK-055 |
| L-17 | L- | WORKFLOWS · 12. Planner-determined Level-1 decisions | TASK-013, TASK-031, TASK-049, TASK-050, TASK-051, TASK-052, TASK-053, TASK-054 |
| L-25 | L- | WORKFLOWS · 12. Planner-determined Level-1 decisions | TASK-033 |
| L-12 | L- | WORKFLOWS · 12. Planner-determined Level-1 decisions | TASK-032, TASK-034, TASK-049, TASK-050, TASK-051, TASK-052, TASK-053, TASK-054, TASK-055, TASK-056 |
| L-03 | L- | WORKFLOWS · 12. Planner-determined Level-1 decisions | TASK-011, TASK-016, TASK-024, TASK-038, TASK-039, TASK-041, TASK-042, TASK-043 |
| L-34 | L- | WORKFLOWS · 12. Planner-determined Level-1 decisions | TASK-013 |
| L-31 | L- | WORKFLOWS · 12. Planner-determined Level-1 decisions | TASK-038, TASK-039, TASK-041, TASK-042, TASK-043, TASK-049, TASK-050, TASK-051, TASK-052, TASK-053, TASK-054 |
| L-35 | L- | WORKFLOWS · 12. Planner-determined Level-1 decisions | TASK-038, TASK-039, TASK-041, TASK-042, TASK-043 |
| L-36 | L- | WORKFLOWS · 12. Planner-determined Level-1 decisions | TASK-038, TASK-039, TASK-041, TASK-042, TASK-043 |
| L-20 | L- | WORKFLOWS · 12. Planner-determined Level-1 decisions | TASK-032, TASK-033, TASK-034, TASK-055, TASK-056 |
| L-21 | L- | WORKFLOWS · 12. Planner-determined Level-1 decisions | TASK-032, TASK-034, TASK-049, TASK-055, TASK-056 |
| L-37 | L- | WORKFLOWS · 12. Planner-determined Level-1 decisions | TASK-013, TASK-031 |
| L-02 | L- | WORKFLOWS · 12. Planner-determined Level-1 decisions | TASK-038 |
| L-04 | L- | WORKFLOWS · WF-FUL-01 — Fulfillment planning per demand line `[desk]` | TASK-013 |
| L-13 | L- | WORKFLOWS · 12. Planner-determined Level-1 decisions | TASK-038, TASK-039, TASK-041, TASK-042, TASK-043 |
| L-07 | L- | WORKFLOWS · 12. Planner-determined Level-1 decisions | TASK-038, TASK-039, TASK-041, TASK-042, TASK-043, TASK-049, TASK-050, TASK-051, TASK-052, TASK-053, TASK-054 |
| L-05 | L- | WORKFLOWS · 12. Planner-determined Level-1 decisions | TASK-049, TASK-050, TASK-051, TASK-052, TASK-053, TASK-054 |
| L-43 | L- | WORKFLOWS · 12. Planner-determined Level-1 decisions | TASK-036, TASK-037, TASK-049, TASK-050, TASK-051, TASK-052, TASK-053, TASK-054 |
| L-18 | L- | WORKFLOWS · 12. Planner-determined Level-1 decisions | TASK-049, TASK-050, TASK-051, TASK-052, TASK-053, TASK-054 |
| L-23 | L- | WORKFLOWS · 12. Planner-determined Level-1 decisions | TASK-013 |
| L-47 | L- | WORKFLOWS · 12. Planner-determined Level-1 decisions | TASK-037, TASK-044, TASK-049, TASK-050, TASK-051, TASK-052, TASK-053, TASK-054 |
| L-19 | L- | WORKFLOWS · 12. Planner-determined Level-1 decisions | TASK-029, TASK-034, TASK-037, TASK-045, TASK-047, TASK-049, TASK-051 |
| L-44 | L- | WORKFLOWS · 12. Planner-determined Level-1 decisions | TASK-038, TASK-039, TASK-041, TASK-042, TASK-043 |
| L-14 | L- | WORKFLOWS · 12. Planner-determined Level-1 decisions | TASK-013, TASK-015, TASK-038, TASK-039, TASK-041, TASK-042, TASK-043 |
| L-38 | L- | WORKFLOWS · 12. Planner-determined Level-1 decisions | TASK-013 |
| L-15 | L- | WORKFLOWS · 12. Planner-determined Level-1 decisions | TASK-038, TASK-039, TASK-041, TASK-042, TASK-043 |
| L-08 | L- | WORKFLOWS · 12. Planner-determined Level-1 decisions | TASK-049, TASK-050, TASK-051, TASK-052, TASK-053, TASK-054 |
| L-39 | L- | WORKFLOWS · 12. Planner-determined Level-1 decisions | TASK-034, TASK-049, TASK-055 |
| L-40 | L- | WORKFLOWS · 12. Planner-determined Level-1 decisions | TASK-038, TASK-039, TASK-041, TASK-042, TASK-043, TASK-046 |
| L-26 | L- | WORKFLOWS · 12. Planner-determined Level-1 decisions | TASK-029, TASK-034, TASK-037, TASK-045, TASK-047, TASK-049, TASK-050, TASK-051, TASK-052, TASK-053, TASK-054 |
| L-41 | L- | WORKFLOWS · 12. Planner-determined Level-1 decisions | TASK-035 |
| L-27 | L- | WORKFLOWS · 12. Planner-determined Level-1 decisions | TASK-013 |
| L-16 | L- | WORKFLOWS · 12. Planner-determined Level-1 decisions | TASK-049, TASK-050, TASK-051, TASK-052, TASK-053, TASK-054 |
| L-30 | L- | WORKFLOWS · 12. Planner-determined Level-1 decisions | TASK-031, TASK-049, TASK-050, TASK-051, TASK-052, TASK-053, TASK-054 |
| L-22 | L- | WORKFLOWS · 12. Planner-determined Level-1 decisions | TASK-049, TASK-050, TASK-051, TASK-052, TASK-053, TASK-054 |
| L-29 | L- | WORKFLOWS · 12. Planner-determined Level-1 decisions | TASK-049, TASK-050, TASK-051, TASK-052, TASK-053, TASK-054 |
| L-46 | L- | WORKFLOWS · 12. Planner-determined Level-1 decisions | TASK-013, TASK-031, TASK-049, TASK-050, TASK-051, TASK-052, TASK-053, TASK-054 |
| L-28 | L- | WORKFLOWS · 12. Planner-determined Level-1 decisions | TASK-049, TASK-050, TASK-051, TASK-052, TASK-053, TASK-054 |
| L-11 | L- | WORKFLOWS · 12. Planner-determined Level-1 decisions | TASK-013, TASK-031, TASK-032, TASK-034, TASK-055, TASK-056 |
| L-10 | L- | WORKFLOWS · 12. Planner-determined Level-1 decisions | TASK-032, TASK-034, TASK-055, TASK-056 |
| L-33 | L- | WORKFLOWS · 12. Planner-determined Level-1 decisions | TASK-013 |
| L-50 | L- | WORKFLOWS · 12. Planner-determined Level-1 decisions | TASK-034, TASK-049, TASK-050, TASK-051, TASK-052, TASK-053, TASK-054, TASK-055 |
| L-09 | L- | WORKFLOWS · 12. Planner-determined Level-1 decisions | TASK-013, TASK-015 |
| L-32 | L- | WORKFLOWS · 12. Planner-determined Level-1 decisions | TASK-013, TASK-031 |
| L-06 | L- | WORKFLOWS · 12. Planner-determined Level-1 decisions | TASK-013, TASK-031, TASK-038, TASK-039, TASK-041, TASK-042, TASK-043, TASK-049, TASK-050, TASK-051, TASK-052, TASK-053, TASK-054 |
| L-01 | L- | WORKFLOWS · 12. Planner-determined Level-1 decisions | TASK-034, TASK-049, TASK-055 |
| L-24 | L- | WORKFLOWS · 12. Planner-determined Level-1 decisions | TASK-013 |
| L-48 | L- | WORKFLOWS · 12. Planner-determined Level-1 decisions | TASK-013, TASK-031, TASK-038, TASK-039, TASK-041, TASK-042, TASK-043 |
| ROUTE-182 | ROUTE- | ROUTE_CONTRACTS · Warehouse — receiving, reservations, dispatch, counts, adjustments and discrepancies (INV; | TASK-039 |
| ROUTE-187 | ROUTE- | ROUTE_CONTRACTS · Warehouse — receiving, reservations, dispatch, counts, adjustments and discrepancies (INV; | TASK-041 |
| ROUTE-202 | ROUTE- | ROUTE_CONTRACTS · Fulfilment — deliveries, direct shipments, handovers and client returns (FUL; PG-030–PG-03 | TASK-045 |
| ROUTE-222 | ROUTE- | ROUTE_CONTRACTS · Documents, files and evidence (DOC; PG-035, PG-036, PG-038; WF-DOC-01, WF-DOC-02) | TASK-029 |
| ROUTE-249 | ROUTE- | ROUTE_CONTRACTS · Finance — invoices, payments, deductions, credit, other cash and bank statements (FIN; PG- | TASK-049 |
| ROUTE-137 | ROUTE- | ROUTE_CONTRACTS · Companies and settings — *Perusahaan*, *Penomoran Dokumen*, *Pajak* (PG-059, PG-061, PG-06 | TASK-021 |
| ROUTE-123 | ROUTE- | ROUTE_CONTRACTS · Users and access — *Pengguna & Akses* (PG-060; OWNER_ONLY) | TASK-024 |
| ROUTE-221 | ROUTE- | ROUTE_CONTRACTS · Documents, files and evidence (DOC; PG-035, PG-036, PG-038; WF-DOC-01, WF-DOC-02) | TASK-029 |
| ROUTE-001 | ROUTE- | ROUTE_CONTRACTS · 2. Page views — ROUTE-001–ROUTE-065 | TASK-017 |
| ROUTE-065 | ROUTE- | ROUTE_CONTRACTS · 2. Page views — ROUTE-001–ROUTE-065 | TASK-023 |
| ROUTE-285 | ROUTE- | ROUTE_CONTRACTS · Reading, search, scanning and exports (Reporting; PG-009, PG-051–PG-053) | TASK-022 |
| ROUTE-002 | ROUTE- | ROUTE_CONTRACTS · 2. Page views — ROUTE-001–ROUTE-065 | TASK-018 |
| ROUTE-003 | ROUTE- | ROUTE_CONTRACTS · 2. Page views — ROUTE-001–ROUTE-065 | TASK-017 |
| ROUTE-004 | ROUTE- | ROUTE_CONTRACTS · 2. Page views — ROUTE-001–ROUTE-065 | TASK-018 |
| ROUTE-005 | ROUTE- | ROUTE_CONTRACTS · 2. Page views — ROUTE-001–ROUTE-065 | TASK-016 |
| ROUTE-006 | ROUTE- | ROUTE_CONTRACTS · 2. Page views — ROUTE-001–ROUTE-065 | TASK-058 |
| ROUTE-007 | ROUTE- | ROUTE_CONTRACTS · 2. Page views — ROUTE-001–ROUTE-065 | TASK-058 |
| ROUTE-008 | ROUTE- | ROUTE_CONTRACTS · 2. Page views — ROUTE-001–ROUTE-065 | TASK-058 |
| ROUTE-009 | ROUTE- | ROUTE_CONTRACTS · 2. Page views — ROUTE-001–ROUTE-065 | TASK-059 |
| ROUTE-010 | ROUTE- | ROUTE_CONTRACTS · 2. Page views — ROUTE-001–ROUTE-065 | TASK-032 |
| ROUTE-011 | ROUTE- | ROUTE_CONTRACTS · 2. Page views — ROUTE-001–ROUTE-065 | TASK-032 |
| ROUTE-012 | ROUTE- | ROUTE_CONTRACTS · 2. Page views — ROUTE-001–ROUTE-065 | TASK-055 |
| ROUTE-013 | ROUTE- | ROUTE_CONTRACTS · 2. Page views — ROUTE-001–ROUTE-065 | TASK-032 |
| ROUTE-014 | ROUTE- | ROUTE_CONTRACTS · 2. Page views — ROUTE-001–ROUTE-065 | TASK-033 |
| ROUTE-037 | ROUTE- | ROUTE_CONTRACTS · 2. Page views — ROUTE-001–ROUTE-065 | TASK-033 |
| ROUTE-015 | ROUTE- | ROUTE_CONTRACTS · 2. Page views — ROUTE-001–ROUTE-065 | TASK-037 |
| ROUTE-016 | ROUTE- | ROUTE_CONTRACTS · 2. Page views — ROUTE-001–ROUTE-065 | TASK-037 |
| ROUTE-017 | ROUTE- | ROUTE_CONTRACTS · 2. Page views — ROUTE-001–ROUTE-065 | TASK-037 |
| ROUTE-018 | ROUTE- | ROUTE_CONTRACTS · 2. Page views — ROUTE-001–ROUTE-065 | TASK-040 |
| ROUTE-019 | ROUTE- | ROUTE_CONTRACTS · 2. Page views — ROUTE-001–ROUTE-065 | TASK-040 |
| ROUTE-020 | ROUTE- | ROUTE_CONTRACTS · 2. Page views — ROUTE-001–ROUTE-065 | TASK-040 |
| ROUTE-021 | ROUTE- | ROUTE_CONTRACTS · 2. Page views — ROUTE-001–ROUTE-065 | TASK-039 |
| ROUTE-022 | ROUTE- | ROUTE_CONTRACTS · 2. Page views — ROUTE-001–ROUTE-065 | TASK-039 |
| ROUTE-023 | ROUTE- | ROUTE_CONTRACTS · 2. Page views — ROUTE-001–ROUTE-065 | TASK-041 |
| ROUTE-024 | ROUTE- | ROUTE_CONTRACTS · 2. Page views — ROUTE-001–ROUTE-065 | TASK-041 |
| ROUTE-025 | ROUTE- | ROUTE_CONTRACTS · 2. Page views — ROUTE-001–ROUTE-065 | TASK-038 |
| ROUTE-026 | ROUTE- | ROUTE_CONTRACTS · 2. Page views — ROUTE-001–ROUTE-065 | TASK-043 |
| ROUTE-027 | ROUTE- | ROUTE_CONTRACTS · 2. Page views — ROUTE-001–ROUTE-065 | TASK-042 |
| ROUTE-028 | ROUTE- | ROUTE_CONTRACTS · 2. Page views — ROUTE-001–ROUTE-065 | TASK-044 |
| ROUTE-029 | ROUTE- | ROUTE_CONTRACTS · 2. Page views — ROUTE-001–ROUTE-065 | TASK-043 |
| ROUTE-030 | ROUTE- | ROUTE_CONTRACTS · 2. Page views — ROUTE-001–ROUTE-065 | TASK-045 |
| ROUTE-031 | ROUTE- | ROUTE_CONTRACTS · 2. Page views — ROUTE-001–ROUTE-065 | TASK-045 |
| ROUTE-032 | ROUTE- | ROUTE_CONTRACTS · 2. Page views — ROUTE-001–ROUTE-065 | TASK-046 |
| ROUTE-033 | ROUTE- | ROUTE_CONTRACTS · 2. Page views — ROUTE-001–ROUTE-065 | TASK-047 |
| ROUTE-034 | ROUTE- | ROUTE_CONTRACTS · 2. Page views — ROUTE-001–ROUTE-065 | TASK-048 |
| ROUTE-035 | ROUTE- | ROUTE_CONTRACTS · 2. Page views — ROUTE-001–ROUTE-065 | TASK-029 |
| ROUTE-036 | ROUTE- | ROUTE_CONTRACTS · 2. Page views — ROUTE-001–ROUTE-065 | TASK-029 |
| ROUTE-038 | ROUTE- | ROUTE_CONTRACTS · 2. Page views — ROUTE-001–ROUTE-065 | TASK-030 |
| ROUTE-039 | ROUTE- | ROUTE_CONTRACTS · 2. Page views — ROUTE-001–ROUTE-065 | TASK-035 |
| ROUTE-040 | ROUTE- | ROUTE_CONTRACTS · 2. Page views — ROUTE-001–ROUTE-065 | TASK-031 |
| ROUTE-041 | ROUTE- | ROUTE_CONTRACTS · 2. Page views — ROUTE-001–ROUTE-065 | TASK-049 |
| ROUTE-042 | ROUTE- | ROUTE_CONTRACTS · 2. Page views — ROUTE-001–ROUTE-065 | TASK-049 |
| ROUTE-043 | ROUTE- | ROUTE_CONTRACTS · 2. Page views — ROUTE-001–ROUTE-065 | TASK-049 |
| ROUTE-044 | ROUTE- | ROUTE_CONTRACTS · 2. Page views — ROUTE-001–ROUTE-065 | TASK-050 |
| ROUTE-045 | ROUTE- | ROUTE_CONTRACTS · 2. Page views — ROUTE-001–ROUTE-065 | TASK-051 |
| ROUTE-046 | ROUTE- | ROUTE_CONTRACTS · 2. Page views — ROUTE-001–ROUTE-065 | TASK-051 |
| ROUTE-047 | ROUTE- | ROUTE_CONTRACTS · 2. Page views — ROUTE-001–ROUTE-065 | TASK-052 |
| ROUTE-048 | ROUTE- | ROUTE_CONTRACTS · 2. Page views — ROUTE-001–ROUTE-065 | TASK-053 |
| ROUTE-049 | ROUTE- | ROUTE_CONTRACTS · 2. Page views — ROUTE-001–ROUTE-065 | TASK-054 |
| ROUTE-050 | ROUTE- | ROUTE_CONTRACTS · 2. Page views — ROUTE-001–ROUTE-065 | TASK-051 |
| ROUTE-051 | ROUTE- | ROUTE_CONTRACTS · 2. Page views — ROUTE-001–ROUTE-065 | TASK-060 |
| ROUTE-052 | ROUTE- | ROUTE_CONTRACTS · 2. Page views — ROUTE-001–ROUTE-065 | TASK-060 |
| ROUTE-053 | ROUTE- | ROUTE_CONTRACTS · 2. Page views — ROUTE-001–ROUTE-065 | TASK-060 |
| ROUTE-054 | ROUTE- | ROUTE_CONTRACTS · 2. Page views — ROUTE-001–ROUTE-065 | TASK-026 |
| ROUTE-055 | ROUTE- | ROUTE_CONTRACTS · 2. Page views — ROUTE-001–ROUTE-065 | TASK-026 |
| ROUTE-056 | ROUTE- | ROUTE_CONTRACTS · 2. Page views — ROUTE-001–ROUTE-065 | TASK-027 |
| ROUTE-057 | ROUTE- | ROUTE_CONTRACTS · 2. Page views — ROUTE-001–ROUTE-065 | TASK-028 |
| ROUTE-058 | ROUTE- | ROUTE_CONTRACTS · 2. Page views — ROUTE-001–ROUTE-065 | TASK-057 |
| ROUTE-059 | ROUTE- | ROUTE_CONTRACTS · 2. Page views — ROUTE-001–ROUTE-065 | TASK-025 |
| ROUTE-060 | ROUTE- | ROUTE_CONTRACTS · 2. Page views — ROUTE-001–ROUTE-065 | TASK-024 |
| ROUTE-061 | ROUTE- | ROUTE_CONTRACTS · 2. Page views — ROUTE-001–ROUTE-065 | TASK-021 |
| ROUTE-062 | ROUTE- | ROUTE_CONTRACTS · 2. Page views — ROUTE-001–ROUTE-065 | TASK-025 |
| ROUTE-063 | ROUTE- | ROUTE_CONTRACTS · 2. Page views — ROUTE-001–ROUTE-065 | TASK-061 |
| ROUTE-064 | ROUTE- | ROUTE_CONTRACTS · 2. Page views — ROUTE-001–ROUTE-065 | TASK-061 |
| ROUTE-101 | ROUTE- | ROUTE_CONTRACTS · 3. Commands and other requests — ROUTE-101 onward | TASK-017 |
| ROUTE-102 | ROUTE- | ROUTE_CONTRACTS · Sign-in and the session (EN-03 exceptions; SECURITY AU-04–AU-24) | TASK-019 |
| ROUTE-103 | ROUTE- | ROUTE_CONTRACTS · Sign-in and the session (EN-03 exceptions; SECURITY AU-04–AU-24) | TASK-019 |
| ROUTE-104 | ROUTE- | ROUTE_CONTRACTS · Sign-in and the session (EN-03 exceptions; SECURITY AU-04–AU-24) | TASK-018 |
| ROUTE-105 | ROUTE- | ROUTE_CONTRACTS · Sign-in and the session (EN-03 exceptions; SECURITY AU-04–AU-24) | TASK-018 |
| ROUTE-106 | ROUTE- | ROUTE_CONTRACTS · Sign-in and the session (EN-03 exceptions; SECURITY AU-04–AU-24) | TASK-017 |
| ROUTE-107 | ROUTE- | ROUTE_CONTRACTS · Sign-in and the session (EN-03 exceptions; SECURITY AU-04–AU-24) | TASK-018 |
| ROUTE-108 | ROUTE- | ROUTE_CONTRACTS · Sign-in and the session (EN-03 exceptions; SECURITY AU-04–AU-24) | TASK-017 |
| ROUTE-109 | ROUTE- | ROUTE_CONTRACTS · Sign-in and the session (EN-03 exceptions; SECURITY AU-04–AU-24) | TASK-017 |
| ROUTE-111 | ROUTE- | ROUTE_CONTRACTS · The account itself — *Akun Saya* (SELF under SECURITY AU-13; PERMISSIONS_MATRIX PJ-17) | TASK-023 |
| ROUTE-112 | ROUTE- | ROUTE_CONTRACTS · The account itself — *Akun Saya* (SELF under SECURITY AU-13; PERMISSIONS_MATRIX PJ-17) | TASK-023 |
| ROUTE-113 | ROUTE- | ROUTE_CONTRACTS · The account itself — *Akun Saya* (SELF under SECURITY AU-13; PERMISSIONS_MATRIX PJ-17) | TASK-023 |
| ROUTE-114 | ROUTE- | ROUTE_CONTRACTS · The account itself — *Akun Saya* (SELF under SECURITY AU-13; PERMISSIONS_MATRIX PJ-17) | TASK-023 |
| ROUTE-115 | ROUTE- | ROUTE_CONTRACTS · The account itself — *Akun Saya* (SELF under SECURITY AU-13; PERMISSIONS_MATRIX PJ-17) | TASK-023 |
| ROUTE-116 | ROUTE- | ROUTE_CONTRACTS · The account itself — *Akun Saya* (SELF under SECURITY AU-13; PERMISSIONS_MATRIX PJ-17) | TASK-023 |
| ROUTE-117 | ROUTE- | ROUTE_CONTRACTS · The account itself — *Akun Saya* (SELF under SECURITY AU-13; PERMISSIONS_MATRIX PJ-17) | TASK-023 |
| ROUTE-118 | ROUTE- | ROUTE_CONTRACTS · The account itself — *Akun Saya* (SELF under SECURITY AU-13; PERMISSIONS_MATRIX PJ-17) | TASK-023 |
| ROUTE-121 | ROUTE- | ROUTE_CONTRACTS · Users and access — *Pengguna & Akses* (PG-060; OWNER_ONLY) | TASK-024 |
| ROUTE-125 | ROUTE- | ROUTE_CONTRACTS · Users and access — *Pengguna & Akses* (PG-060; OWNER_ONLY) | TASK-024 |
| ROUTE-122 | ROUTE- | ROUTE_CONTRACTS · Users and access — *Pengguna & Akses* (PG-060; OWNER_ONLY) | TASK-024 |
| ROUTE-124 | ROUTE- | ROUTE_CONTRACTS · Users and access — *Pengguna & Akses* (PG-060; OWNER_ONLY) | TASK-024 |
| ROUTE-126 | ROUTE- | ROUTE_CONTRACTS · Users and access — *Pengguna & Akses* (PG-060; OWNER_ONLY) | TASK-024 |
| ROUTE-127 | ROUTE- | ROUTE_CONTRACTS · Users and access — *Pengguna & Akses* (PG-060; OWNER_ONLY) | TASK-024 |
| ROUTE-128 | ROUTE- | ROUTE_CONTRACTS · Users and access — *Pengguna & Akses* (PG-060; OWNER_ONLY) | TASK-024 |
| ROUTE-130 | ROUTE- | ROUTE_CONTRACTS · Users and access — *Pengguna & Akses* (PG-060; OWNER_ONLY) | TASK-024 |
| ROUTE-131 | ROUTE- | ROUTE_CONTRACTS · Companies and settings — *Perusahaan*, *Penomoran Dokumen*, *Pajak* (PG-059, PG-061, PG-06 | TASK-025 |
| ROUTE-132 | ROUTE- | ROUTE_CONTRACTS · Companies and settings — *Perusahaan*, *Penomoran Dokumen*, *Pajak* (PG-059, PG-061, PG-06 | TASK-025 |
| ROUTE-133 | ROUTE- | ROUTE_CONTRACTS · Companies and settings — *Perusahaan*, *Penomoran Dokumen*, *Pajak* (PG-059, PG-061, PG-06 | TASK-025 |
| ROUTE-134 | ROUTE- | ROUTE_CONTRACTS · Companies and settings — *Perusahaan*, *Penomoran Dokumen*, *Pajak* (PG-059, PG-061, PG-06 | TASK-025 |
| ROUTE-135 | ROUTE- | ROUTE_CONTRACTS · Companies and settings — *Perusahaan*, *Penomoran Dokumen*, *Pajak* (PG-059, PG-061, PG-06 | TASK-025 |
| ROUTE-228 | ROUTE- | ROUTE_CONTRACTS · Documents, files and evidence (DOC; PG-035, PG-036, PG-038; WF-DOC-01, WF-DOC-02) | TASK-030 |
| ROUTE-136 | ROUTE- | ROUTE_CONTRACTS · Companies and settings — *Perusahaan*, *Penomoran Dokumen*, *Pajak* (PG-059, PG-061, PG-06 | TASK-021 |
| ROUTE-138 | ROUTE- | ROUTE_CONTRACTS · Companies and settings — *Perusahaan*, *Penomoran Dokumen*, *Pajak* (PG-059, PG-061, PG-06 | TASK-025 |
| ROUTE-141 | ROUTE- | ROUTE_CONTRACTS · Master data — *Klien*, *Pemasok*, *Produk & Jasa*, *Lokasi Rak* (PG-054–PG-057; J-MD-02–J- | TASK-026 |
| ROUTE-142 | ROUTE- | ROUTE_CONTRACTS · Master data — *Klien*, *Pemasok*, *Produk & Jasa*, *Lokasi Rak* (PG-054–PG-057; J-MD-02–J- | TASK-026 |
| ROUTE-143 | ROUTE- | ROUTE_CONTRACTS · Master data — *Klien*, *Pemasok*, *Produk & Jasa*, *Lokasi Rak* (PG-054–PG-057; J-MD-02–J- | TASK-026 |
| ROUTE-144 | ROUTE- | ROUTE_CONTRACTS · Master data — *Klien*, *Pemasok*, *Produk & Jasa*, *Lokasi Rak* (PG-054–PG-057; J-MD-02–J- | TASK-026 |
| ROUTE-145 | ROUTE- | ROUTE_CONTRACTS · Master data — *Klien*, *Pemasok*, *Produk & Jasa*, *Lokasi Rak* (PG-054–PG-057; J-MD-02–J- | TASK-027 |
| ROUTE-146 | ROUTE- | ROUTE_CONTRACTS · Master data — *Klien*, *Pemasok*, *Produk & Jasa*, *Lokasi Rak* (PG-054–PG-057; J-MD-02–J- | TASK-027 |
| ROUTE-147 | ROUTE- | ROUTE_CONTRACTS · Master data — *Klien*, *Pemasok*, *Produk & Jasa*, *Lokasi Rak* (PG-054–PG-057; J-MD-02–J- | TASK-027 |
| ROUTE-148 | ROUTE- | ROUTE_CONTRACTS · Master data — *Klien*, *Pemasok*, *Produk & Jasa*, *Lokasi Rak* (PG-054–PG-057; J-MD-02–J- | TASK-028 |
| ROUTE-151 | ROUTE- | ROUTE_CONTRACTS · Projects — *Proyek*, *Formulir Proyek*, *Penawaran*, the journey and its completion (PRJ;  | TASK-032 |
| ROUTE-152 | ROUTE- | ROUTE_CONTRACTS · Projects — *Proyek*, *Formulir Proyek*, *Penawaran*, the journey and its completion (PRJ;  | TASK-032 |
| ROUTE-153 | ROUTE- | ROUTE_CONTRACTS · Projects — *Proyek*, *Formulir Proyek*, *Penawaran*, the journey and its completion (PRJ;  | TASK-032 |
| ROUTE-154 | ROUTE- | ROUTE_CONTRACTS · Projects — *Proyek*, *Formulir Proyek*, *Penawaran*, the journey and its completion (PRJ;  | TASK-033 |
| ROUTE-155 | ROUTE- | ROUTE_CONTRACTS · Projects — *Proyek*, *Formulir Proyek*, *Penawaran*, the journey and its completion (PRJ;  | TASK-033 |
| ROUTE-156 | ROUTE- | ROUTE_CONTRACTS · Projects — *Proyek*, *Formulir Proyek*, *Penawaran*, the journey and its completion (PRJ;  | TASK-033 |
| ROUTE-157 | ROUTE- | ROUTE_CONTRACTS · Projects — *Proyek*, *Formulir Proyek*, *Penawaran*, the journey and its completion (PRJ;  | TASK-033 |
| ROUTE-158 | ROUTE- | ROUTE_CONTRACTS · Projects — *Proyek*, *Formulir Proyek*, *Penawaran*, the journey and its completion (PRJ;  | TASK-034 |
| ROUTE-159 | ROUTE- | ROUTE_CONTRACTS · Projects — *Proyek*, *Formulir Proyek*, *Penawaran*, the journey and its completion (PRJ;  | TASK-034 |
| ROUTE-160 | ROUTE- | ROUTE_CONTRACTS · Projects — *Proyek*, *Formulir Proyek*, *Penawaran*, the journey and its completion (PRJ;  | TASK-034 |
| ROUTE-161 | ROUTE- | ROUTE_CONTRACTS · Projects — *Proyek*, *Formulir Proyek*, *Penawaran*, the journey and its completion (PRJ;  | TASK-055 |
| ROUTE-162 | ROUTE- | ROUTE_CONTRACTS · Projects — *Proyek*, *Formulir Proyek*, *Penawaran*, the journey and its completion (PRJ;  | TASK-055 |
| ROUTE-163 | ROUTE- | ROUTE_CONTRACTS · Projects — *Proyek*, *Formulir Proyek*, *Penawaran*, the journey and its completion (PRJ;  | TASK-056 |
| ROUTE-164 | ROUTE- | ROUTE_CONTRACTS · Requirements — *Kesiapan dokumen* and *Dokumen Administrasi* (ADM; PG-011, PG-039; WF-ADM- | TASK-035 |
| ROUTE-165 | ROUTE- | ROUTE_CONTRACTS · Requirements — *Kesiapan dokumen* and *Dokumen Administrasi* (ADM; PG-011, PG-039; WF-ADM- | TASK-035 |
| ROUTE-166 | ROUTE- | ROUTE_CONTRACTS · Requirements — *Kesiapan dokumen* and *Dokumen Administrasi* (ADM; PG-011, PG-039; WF-ADM- | TASK-035 |
| ROUTE-167 | ROUTE- | ROUTE_CONTRACTS · Requirements — *Kesiapan dokumen* and *Dokumen Administrasi* (ADM; PG-011, PG-039; WF-ADM- | TASK-035 |
| ROUTE-168 | ROUTE- | ROUTE_CONTRACTS · Requirements — *Kesiapan dokumen* and *Dokumen Administrasi* (ADM; PG-011, PG-039; WF-ADM- | TASK-035 |
| ROUTE-169 | ROUTE- | ROUTE_CONTRACTS · Requirements — *Kesiapan dokumen* and *Dokumen Administrasi* (ADM; PG-011, PG-039; WF-ADM- | TASK-035 |
| ROUTE-171 | ROUTE- | ROUTE_CONTRACTS · Purchases — *Buat Pembelian* and *Detail Pembelian* (PUR; PG-016, PG-017; WF-PUR-01–WF-PUR | TASK-037 |
| ROUTE-172 | ROUTE- | ROUTE_CONTRACTS · Purchases — *Buat Pembelian* and *Detail Pembelian* (PUR; PG-016, PG-017; WF-PUR-01–WF-PUR | TASK-037 |
| ROUTE-173 | ROUTE- | ROUTE_CONTRACTS · Purchases — *Buat Pembelian* and *Detail Pembelian* (PUR; PG-016, PG-017; WF-PUR-01–WF-PUR | TASK-037 |
| ROUTE-174 | ROUTE- | ROUTE_CONTRACTS · Purchases — *Buat Pembelian* and *Detail Pembelian* (PUR; PG-016, PG-017; WF-PUR-01–WF-PUR | TASK-037 |
| ROUTE-175 | ROUTE- | ROUTE_CONTRACTS · Purchases — *Buat Pembelian* and *Detail Pembelian* (PUR; PG-016, PG-017; WF-PUR-01–WF-PUR | TASK-037 |
| ROUTE-176 | ROUTE- | ROUTE_CONTRACTS · Purchases — *Buat Pembelian* and *Detail Pembelian* (PUR; PG-016, PG-017; WF-PUR-01–WF-PUR | TASK-044 |
| ROUTE-177 | ROUTE- | ROUTE_CONTRACTS · Purchases — *Buat Pembelian* and *Detail Pembelian* (PUR; PG-016, PG-017; WF-PUR-01–WF-PUR | TASK-044 |
| ROUTE-178 | ROUTE- | ROUTE_CONTRACTS · Purchases — *Buat Pembelian* and *Detail Pembelian* (PUR; PG-016, PG-017; WF-PUR-01–WF-PUR | TASK-037 |
| ROUTE-179 | ROUTE- | ROUTE_CONTRACTS · Purchases — *Buat Pembelian* and *Detail Pembelian* (PUR; PG-016, PG-017; WF-PUR-01–WF-PUR | TASK-037 |
| ROUTE-180 | ROUTE- | ROUTE_CONTRACTS · Purchases — *Buat Pembelian* and *Detail Pembelian* (PUR; PG-016, PG-017; WF-PUR-01–WF-PUR | TASK-037 |
| ROUTE-181 | ROUTE- | ROUTE_CONTRACTS · Warehouse — receiving, reservations, dispatch, counts, adjustments and discrepancies (INV; | TASK-039 |
| ROUTE-183 | ROUTE- | ROUTE_CONTRACTS · Warehouse — receiving, reservations, dispatch, counts, adjustments and discrepancies (INV; | TASK-039 |
| ROUTE-184 | ROUTE- | ROUTE_CONTRACTS · Warehouse — receiving, reservations, dispatch, counts, adjustments and discrepancies (INV; | TASK-038 |
| ROUTE-188 | ROUTE- | ROUTE_CONTRACTS · Warehouse — receiving, reservations, dispatch, counts, adjustments and discrepancies (INV; | TASK-041 |
| ROUTE-210 | ROUTE- | ROUTE_CONTRACTS · Fulfilment — deliveries, direct shipments, handovers and client returns (FUL; PG-030–PG-03 | TASK-048 |
| ROUTE-185 | ROUTE- | ROUTE_CONTRACTS · Warehouse — receiving, reservations, dispatch, counts, adjustments and discrepancies (INV; | TASK-038 |
| ROUTE-186 | ROUTE- | ROUTE_CONTRACTS · Warehouse — receiving, reservations, dispatch, counts, adjustments and discrepancies (INV; | TASK-041 |
| ROUTE-189 | ROUTE- | ROUTE_CONTRACTS · Warehouse — receiving, reservations, dispatch, counts, adjustments and discrepancies (INV; | TASK-041 |
| ROUTE-190 | ROUTE- | ROUTE_CONTRACTS · Warehouse — receiving, reservations, dispatch, counts, adjustments and discrepancies (INV; | TASK-042 |
| ROUTE-191 | ROUTE- | ROUTE_CONTRACTS · Warehouse — receiving, reservations, dispatch, counts, adjustments and discrepancies (INV; | TASK-043 |
| ROUTE-192 | ROUTE- | ROUTE_CONTRACTS · Warehouse — receiving, reservations, dispatch, counts, adjustments and discrepancies (INV; | TASK-043 |
| ROUTE-193 | ROUTE- | ROUTE_CONTRACTS · Warehouse — receiving, reservations, dispatch, counts, adjustments and discrepancies (INV; | TASK-043 |
| ROUTE-194 | ROUTE- | ROUTE_CONTRACTS · Warehouse — receiving, reservations, dispatch, counts, adjustments and discrepancies (INV; | TASK-042, TASK-043 |
| ROUTE-195 | ROUTE- | ROUTE_CONTRACTS · Warehouse — receiving, reservations, dispatch, counts, adjustments and discrepancies (INV; | TASK-042, TASK-043 |
| ROUTE-196 | ROUTE- | ROUTE_CONTRACTS · Warehouse — receiving, reservations, dispatch, counts, adjustments and discrepancies (INV; | TASK-042 |
| ROUTE-197 | ROUTE- | ROUTE_CONTRACTS · Warehouse — receiving, reservations, dispatch, counts, adjustments and discrepancies (INV; | TASK-043 |
| ROUTE-198 | ROUTE- | ROUTE_CONTRACTS · Warehouse — receiving, reservations, dispatch, counts, adjustments and discrepancies (INV; | TASK-043 |
| ROUTE-199 | ROUTE- | ROUTE_CONTRACTS · Warehouse — receiving, reservations, dispatch, counts, adjustments and discrepancies (INV; | TASK-043 |
| ROUTE-200 | ROUTE- | ROUTE_CONTRACTS · Warehouse — receiving, reservations, dispatch, counts, adjustments and discrepancies (INV; | TASK-043 |
| ROUTE-212 | ROUTE- | ROUTE_CONTRACTS · Warehouse — receiving, reservations, dispatch, counts, adjustments and discrepancies (INV; | TASK-040 |
| ROUTE-213 | ROUTE- | ROUTE_CONTRACTS · Warehouse — receiving, reservations, dispatch, counts, adjustments and discrepancies (INV; | TASK-057 |
| ROUTE-214 | ROUTE- | ROUTE_CONTRACTS · Warehouse — receiving, reservations, dispatch, counts, adjustments and discrepancies (INV; | TASK-057 |
| ROUTE-215 | ROUTE- | ROUTE_CONTRACTS · Warehouse — receiving, reservations, dispatch, counts, adjustments and discrepancies (INV; | TASK-043 |
| ROUTE-201 | ROUTE- | ROUTE_CONTRACTS · Fulfilment — deliveries, direct shipments, handovers and client returns (FUL; PG-030–PG-03 | TASK-045 |
| ROUTE-203 | ROUTE- | ROUTE_CONTRACTS · Fulfilment — deliveries, direct shipments, handovers and client returns (FUL; PG-030–PG-03 | TASK-045 |
| ROUTE-204 | ROUTE- | ROUTE_CONTRACTS · Fulfilment — deliveries, direct shipments, handovers and client returns (FUL; PG-030–PG-03 | TASK-045 |
| ROUTE-205 | ROUTE- | ROUTE_CONTRACTS · Fulfilment — deliveries, direct shipments, handovers and client returns (FUL; PG-030–PG-03 | TASK-046 |
| ROUTE-206 | ROUTE- | ROUTE_CONTRACTS · Fulfilment — deliveries, direct shipments, handovers and client returns (FUL; PG-030–PG-03 | TASK-046 |
| ROUTE-216 | ROUTE- | ROUTE_CONTRACTS · Fulfilment — deliveries, direct shipments, handovers and client returns (FUL; PG-030–PG-03 | TASK-046 |
| ROUTE-207 | ROUTE- | ROUTE_CONTRACTS · Fulfilment — deliveries, direct shipments, handovers and client returns (FUL; PG-030–PG-03 | TASK-046 |
| ROUTE-208 | ROUTE- | ROUTE_CONTRACTS · Fulfilment — deliveries, direct shipments, handovers and client returns (FUL; PG-030–PG-03 | TASK-047 |
| ROUTE-209 | ROUTE- | ROUTE_CONTRACTS · Fulfilment — deliveries, direct shipments, handovers and client returns (FUL; PG-030–PG-03 | TASK-047 |
| ROUTE-211 | ROUTE- | ROUTE_CONTRACTS · Fulfilment — deliveries, direct shipments, handovers and client returns (FUL; PG-030–PG-03 | TASK-048 |
| ROUTE-223 | ROUTE- | ROUTE_CONTRACTS · Documents, files and evidence (DOC; PG-035, PG-036, PG-038; WF-DOC-01, WF-DOC-02) | TASK-029 |
| ROUTE-224 | ROUTE- | ROUTE_CONTRACTS · Documents, files and evidence (DOC; PG-035, PG-036, PG-038; WF-DOC-01, WF-DOC-02) | TASK-029 |
| ROUTE-225 | ROUTE- | ROUTE_CONTRACTS · Documents, files and evidence (DOC; PG-035, PG-036, PG-038; WF-DOC-01, WF-DOC-02) | TASK-029 |
| ROUTE-226 | ROUTE- | ROUTE_CONTRACTS · Documents, files and evidence (DOC; PG-035, PG-036, PG-038; WF-DOC-01, WF-DOC-02) | TASK-022 |
| ROUTE-227 | ROUTE- | ROUTE_CONTRACTS · Documents, files and evidence (DOC; PG-035, PG-036, PG-038; WF-DOC-01, WF-DOC-02) | TASK-022 |
| ROUTE-229 | ROUTE- | ROUTE_CONTRACTS · Documents, files and evidence (DOC; PG-035, PG-036, PG-038; WF-DOC-01, WF-DOC-02) | TASK-030 |
| ROUTE-230 | ROUTE- | ROUTE_CONTRACTS · Documents, files and evidence (DOC; PG-035, PG-036, PG-038; WF-DOC-01, WF-DOC-02) | TASK-030 |
| ROUTE-231 | ROUTE- | ROUTE_CONTRACTS · Documents, files and evidence (DOC; PG-035, PG-036, PG-038; WF-DOC-01, WF-DOC-02) | TASK-030 |
| ROUTE-241 | ROUTE- | ROUTE_CONTRACTS · Finance — invoices, payments, deductions, credit, other cash and bank statements (FIN; PG- | TASK-049 |
| ROUTE-242 | ROUTE- | ROUTE_CONTRACTS · Finance — invoices, payments, deductions, credit, other cash and bank statements (FIN; PG- | TASK-049 |
| ROUTE-243 | ROUTE- | ROUTE_CONTRACTS · Finance — invoices, payments, deductions, credit, other cash and bank statements (FIN; PG- | TASK-050 |
| ROUTE-244 | ROUTE- | ROUTE_CONTRACTS · Finance — invoices, payments, deductions, credit, other cash and bank statements (FIN; PG- | TASK-050 |
| ROUTE-245 | ROUTE- | ROUTE_CONTRACTS · Finance — invoices, payments, deductions, credit, other cash and bank statements (FIN; PG- | TASK-049 |
| ROUTE-246 | ROUTE- | ROUTE_CONTRACTS · Finance — invoices, payments, deductions, credit, other cash and bank statements (FIN; PG- | TASK-050 |
| ROUTE-247 | ROUTE- | ROUTE_CONTRACTS · Finance — invoices, payments, deductions, credit, other cash and bank statements (FIN; PG- | TASK-050 |
| ROUTE-248 | ROUTE- | ROUTE_CONTRACTS · Finance — invoices, payments, deductions, credit, other cash and bank statements (FIN; PG- | TASK-050 |
| ROUTE-250 | ROUTE- | ROUTE_CONTRACTS · Finance — invoices, payments, deductions, credit, other cash and bank statements (FIN; PG- | TASK-049 |
| ROUTE-251 | ROUTE- | ROUTE_CONTRACTS · Finance — invoices, payments, deductions, credit, other cash and bank statements (FIN; PG- | TASK-051 |
| ROUTE-252 | ROUTE- | ROUTE_CONTRACTS · Finance — invoices, payments, deductions, credit, other cash and bank statements (FIN; PG- | TASK-051 |
| ROUTE-253 | ROUTE- | ROUTE_CONTRACTS · Finance — invoices, payments, deductions, credit, other cash and bank statements (FIN; PG- | TASK-051 |
| ROUTE-254 | ROUTE- | ROUTE_CONTRACTS · Finance — invoices, payments, deductions, credit, other cash and bank statements (FIN; PG- | TASK-051 |
| ROUTE-255 | ROUTE- | ROUTE_CONTRACTS · Finance — invoices, payments, deductions, credit, other cash and bank statements (FIN; PG- | TASK-052 |
| ROUTE-256 | ROUTE- | ROUTE_CONTRACTS · Finance — invoices, payments, deductions, credit, other cash and bank statements (FIN; PG- | TASK-053 |
| ROUTE-257 | ROUTE- | ROUTE_CONTRACTS · Finance — invoices, payments, deductions, credit, other cash and bank statements (FIN; PG- | TASK-054 |
| ROUTE-258 | ROUTE- | ROUTE_CONTRACTS · Finance — invoices, payments, deductions, credit, other cash and bank statements (FIN; PG- | TASK-054 |
| ROUTE-259 | ROUTE- | ROUTE_CONTRACTS · Finance — invoices, payments, deductions, credit, other cash and bank statements (FIN; PG- | TASK-054 |
| ROUTE-260 | ROUTE- | ROUTE_CONTRACTS · Finance — invoices, payments, deductions, credit, other cash and bank statements (FIN; PG- | TASK-052 |
| ROUTE-261 | ROUTE- | ROUTE_CONTRACTS · Finance — invoices, payments, deductions, credit, other cash and bank statements (FIN; PG- | TASK-051 |
| ROUTE-262 | ROUTE- | ROUTE_CONTRACTS · Finance — invoices, payments, deductions, credit, other cash and bank statements (FIN; PG- | TASK-051 |
| ROUTE-263 | ROUTE- | ROUTE_CONTRACTS · Finance — invoices, payments, deductions, credit, other cash and bank statements (FIN; PG- | TASK-052 |
| ROUTE-264 | ROUTE- | ROUTE_CONTRACTS · Finance — invoices, payments, deductions, credit, other cash and bank statements (FIN; PG- | TASK-053 |
| ROUTE-271 | ROUTE- | ROUTE_CONTRACTS · Operations — correction cases and the opening import (OPS; PG-040, PG-058; WF-MIG-01) | TASK-031 |
| ROUTE-272 | ROUTE- | ROUTE_CONTRACTS · Operations — correction cases and the opening import (OPS; PG-040, PG-058; WF-MIG-01) | TASK-057 |
| ROUTE-273 | ROUTE- | ROUTE_CONTRACTS · Operations — correction cases and the opening import (OPS; PG-040, PG-058; WF-MIG-01) | TASK-057 |
| ROUTE-274 | ROUTE- | ROUTE_CONTRACTS · Operations — correction cases and the opening import (OPS; PG-040, PG-058; WF-MIG-01) | TASK-057 |
| ROUTE-275 | ROUTE- | ROUTE_CONTRACTS · Operations — correction cases and the opening import (OPS; PG-040, PG-058; WF-MIG-01) | TASK-057 |
| ROUTE-276 | ROUTE- | ROUTE_CONTRACTS · Operations — correction cases and the opening import (OPS; PG-040, PG-058; WF-MIG-01) | TASK-057 |
| ROUTE-277 | ROUTE- | ROUTE_CONTRACTS · Operations — correction cases and the opening import (OPS; PG-040, PG-058; WF-MIG-01) | TASK-072 |
| ROUTE-278 | ROUTE- | ROUTE_CONTRACTS · Operations — correction cases and the opening import (OPS; PG-040, PG-058; WF-MIG-01) | TASK-072 |
| ROUTE-281 | ROUTE- | ROUTE_CONTRACTS · Reading, search, scanning and exports (Reporting; PG-009, PG-051–PG-053) | TASK-016, TASK-059 |
| ROUTE-282 | ROUTE- | ROUTE_CONTRACTS · Reading, search, scanning and exports (Reporting; PG-009, PG-051–PG-053) | TASK-039, TASK-059 |
| ROUTE-283 | ROUTE- | ROUTE_CONTRACTS · Reading, search, scanning and exports (Reporting; PG-009, PG-051–PG-053) | TASK-060 |
| ROUTE-284 | ROUTE- | ROUTE_CONTRACTS · Reading, search, scanning and exports (Reporting; PG-009, PG-051–PG-053) | TASK-060 |
| RC-03 | RC- | ROUTE_CONTRACTS · 1. Conventions | TASK-011, TASK-016 |
| RC-01 | RC- | ROUTE_CONTRACTS · 1. Conventions | TASK-011, TASK-016 |
| RC-02 | RC- | ROUTE_CONTRACTS · 1. Conventions | TASK-011, TASK-016 |
| RC-04 | RC- | ROUTE_CONTRACTS · 1. Conventions | TASK-011, TASK-016 |
| RC-05 | RC- | ROUTE_CONTRACTS · 1. Conventions | TASK-011, TASK-016 |
| RC-06 | RC- | ROUTE_CONTRACTS · 1. Conventions | TASK-011, TASK-016 |
| INT-012 | INT- | INTERACTION_CONTRACTS · 2. Shared interactions — INT-001–INT-020 | TASK-029 |
| INT-182 | INT- | INTERACTION_CONTRACTS · Warehouse — receiving, reservations, dispatch, counts, adjustments and discrepancies (INV; | TASK-039 |
| INT-187 | INT- | INTERACTION_CONTRACTS · Warehouse — receiving, reservations, dispatch, counts, adjustments and discrepancies (INV; | TASK-041 |
| INT-202 | INT- | INTERACTION_CONTRACTS · Fulfilment — deliveries, direct shipments, handovers and client returns (FUL; PG-030–PG-03 | TASK-045 |
| INT-222 | INT- | INTERACTION_CONTRACTS · Documents, files and evidence (DOC; PG-035, PG-036, PG-038; WF-DOC-01, WF-DOC-02) | TASK-029 |
| INT-249 | INT- | INTERACTION_CONTRACTS · Finance — invoices, payments, deductions, credit, other cash and bank statements (FIN; PG- | TASK-049 |
| INT-137 | INT- | INTERACTION_CONTRACTS · Companies and settings — *Perusahaan*, *Penomoran Dokumen*, *Pajak* (PG-059, PG-061, PG-06 | TASK-021 |
| INT-001 | INT- | INTERACTION_CONTRACTS · 2. Shared interactions — INT-001–INT-020 | TASK-014, TASK-017 |
| INT-020 | INT- | INTERACTION_CONTRACTS · 2. Shared interactions — INT-001–INT-020 | TASK-058, TASK-061 |
| INT-002 | INT- | INTERACTION_CONTRACTS · 2. Shared interactions — INT-001–INT-020 | TASK-014, TASK-023 |
| INT-003 | INT- | INTERACTION_CONTRACTS · 2. Shared interactions — INT-001–INT-020 | TASK-016 |
| INT-004 | INT- | INTERACTION_CONTRACTS · 2. Shared interactions — INT-001–INT-020 | TASK-016 |
| INT-005 | INT- | INTERACTION_CONTRACTS · 2. Shared interactions — INT-001–INT-020 | TASK-015, TASK-016, TASK-059 |
| INT-006 | INT- | INTERACTION_CONTRACTS · 2. Shared interactions — INT-001–INT-020 | TASK-039, TASK-059 |
| INT-007 | INT- | INTERACTION_CONTRACTS · 2. Shared interactions — INT-001–INT-020 | TASK-015, TASK-016 |
| INT-008 | INT- | INTERACTION_CONTRACTS · 2. Shared interactions — INT-001–INT-020 | TASK-015 |
| INT-009 | INT- | INTERACTION_CONTRACTS · 2. Shared interactions — INT-001–INT-020 | TASK-015 |
| INT-010 | INT- | INTERACTION_CONTRACTS · 2. Shared interactions — INT-001–INT-020 | TASK-016, TASK-017 |
| INT-011 | INT- | INTERACTION_CONTRACTS · 2. Shared interactions — INT-001–INT-020 | TASK-015, TASK-017 |
| INT-013 | INT- | INTERACTION_CONTRACTS · 2. Shared interactions — INT-001–INT-020 | TASK-022, TASK-030 |
| INT-014 | INT- | INTERACTION_CONTRACTS · 2. Shared interactions — INT-001–INT-020 | TASK-022 |
| INT-015 | INT- | INTERACTION_CONTRACTS · 2. Shared interactions — INT-001–INT-020 | TASK-013 |
| INT-016 | INT- | INTERACTION_CONTRACTS · 2. Shared interactions — INT-001–INT-020 | TASK-013 |
| INT-017 | INT- | INTERACTION_CONTRACTS · 2. Shared interactions — INT-001–INT-020 | TASK-013 |
| INT-018 | INT- | INTERACTION_CONTRACTS · 2. Shared interactions — INT-001–INT-020 | TASK-017 |
| INT-019 | INT- | INTERACTION_CONTRACTS · 2. Shared interactions — INT-001–INT-020 | TASK-058 |
| INT-101 | INT- | INTERACTION_CONTRACTS · 3. Command interactions — INT-101 onward | TASK-017 |
| INT-102 | INT- | INTERACTION_CONTRACTS · Sign-in and the session (EN-03 exceptions; SECURITY AU-04–AU-24) | TASK-019 |
| INT-103 | INT- | INTERACTION_CONTRACTS · Sign-in and the session (EN-03 exceptions; SECURITY AU-04–AU-24) | TASK-019 |
| INT-104 | INT- | INTERACTION_CONTRACTS · Sign-in and the session (EN-03 exceptions; SECURITY AU-04–AU-24) | TASK-018 |
| INT-105 | INT- | INTERACTION_CONTRACTS · Sign-in and the session (EN-03 exceptions; SECURITY AU-04–AU-24) | TASK-018 |
| INT-106 | INT- | INTERACTION_CONTRACTS · Sign-in and the session (EN-03 exceptions; SECURITY AU-04–AU-24) | TASK-017 |
| INT-107 | INT- | INTERACTION_CONTRACTS · Sign-in and the session (EN-03 exceptions; SECURITY AU-04–AU-24) | TASK-018 |
| INT-108 | INT- | INTERACTION_CONTRACTS · Sign-in and the session (EN-03 exceptions; SECURITY AU-04–AU-24) | TASK-017 |
| INT-109 | INT- | INTERACTION_CONTRACTS · Sign-in and the session (EN-03 exceptions; SECURITY AU-04–AU-24) | TASK-017 |
| INT-111 | INT- | INTERACTION_CONTRACTS · The account itself — *Akun Saya* (SELF under SECURITY AU-13; PERMISSIONS_MATRIX PJ-17) | TASK-023 |
| INT-112 | INT- | INTERACTION_CONTRACTS · The account itself — *Akun Saya* (SELF under SECURITY AU-13; PERMISSIONS_MATRIX PJ-17) | TASK-023 |
| INT-113 | INT- | INTERACTION_CONTRACTS · The account itself — *Akun Saya* (SELF under SECURITY AU-13; PERMISSIONS_MATRIX PJ-17) | TASK-023 |
| INT-114 | INT- | INTERACTION_CONTRACTS · The account itself — *Akun Saya* (SELF under SECURITY AU-13; PERMISSIONS_MATRIX PJ-17) | TASK-023 |
| INT-115 | INT- | INTERACTION_CONTRACTS · The account itself — *Akun Saya* (SELF under SECURITY AU-13; PERMISSIONS_MATRIX PJ-17) | TASK-023 |
| INT-116 | INT- | INTERACTION_CONTRACTS · The account itself — *Akun Saya* (SELF under SECURITY AU-13; PERMISSIONS_MATRIX PJ-17) | TASK-023 |
| INT-117 | INT- | INTERACTION_CONTRACTS · The account itself — *Akun Saya* (SELF under SECURITY AU-13; PERMISSIONS_MATRIX PJ-17) | TASK-023 |
| INT-118 | INT- | INTERACTION_CONTRACTS · The account itself — *Akun Saya* (SELF under SECURITY AU-13; PERMISSIONS_MATRIX PJ-17) | TASK-023 |
| INT-121 | INT- | INTERACTION_CONTRACTS · Users and access — *Pengguna & Akses* (PG-060; OWNER_ONLY) | TASK-024 |
| INT-122 | INT- | INTERACTION_CONTRACTS · Users and access — *Pengguna & Akses* (PG-060; OWNER_ONLY) | TASK-024 |
| INT-123 | INT- | INTERACTION_CONTRACTS · Users and access — *Pengguna & Akses* (PG-060; OWNER_ONLY) | TASK-024 |
| INT-124 | INT- | INTERACTION_CONTRACTS · Users and access — *Pengguna & Akses* (PG-060; OWNER_ONLY) | TASK-024 |
| INT-125 | INT- | INTERACTION_CONTRACTS · Users and access — *Pengguna & Akses* (PG-060; OWNER_ONLY) | TASK-024 |
| INT-126 | INT- | INTERACTION_CONTRACTS · Users and access — *Pengguna & Akses* (PG-060; OWNER_ONLY) | TASK-024 |
| INT-127 | INT- | INTERACTION_CONTRACTS · Users and access — *Pengguna & Akses* (PG-060; OWNER_ONLY) | TASK-024 |
| INT-128 | INT- | INTERACTION_CONTRACTS · Users and access — *Pengguna & Akses* (PG-060; OWNER_ONLY) | TASK-024 |
| INT-130 | INT- | INTERACTION_CONTRACTS · Users and access — *Pengguna & Akses* (PG-060; OWNER_ONLY) | TASK-024 |
| INT-131 | INT- | INTERACTION_CONTRACTS · Companies and settings — *Perusahaan*, *Penomoran Dokumen*, *Pajak* (PG-059, PG-061, PG-06 | TASK-025 |
| INT-132 | INT- | INTERACTION_CONTRACTS · Companies and settings — *Perusahaan*, *Penomoran Dokumen*, *Pajak* (PG-059, PG-061, PG-06 | TASK-025 |
| INT-133 | INT- | INTERACTION_CONTRACTS · Companies and settings — *Perusahaan*, *Penomoran Dokumen*, *Pajak* (PG-059, PG-061, PG-06 | TASK-025 |
| INT-134 | INT- | INTERACTION_CONTRACTS · Companies and settings — *Perusahaan*, *Penomoran Dokumen*, *Pajak* (PG-059, PG-061, PG-06 | TASK-025 |
| INT-135 | INT- | INTERACTION_CONTRACTS · Companies and settings — *Perusahaan*, *Penomoran Dokumen*, *Pajak* (PG-059, PG-061, PG-06 | TASK-025 |
| INT-136 | INT- | INTERACTION_CONTRACTS · Companies and settings — *Perusahaan*, *Penomoran Dokumen*, *Pajak* (PG-059, PG-061, PG-06 | TASK-021 |
| INT-138 | INT- | INTERACTION_CONTRACTS · Companies and settings — *Perusahaan*, *Penomoran Dokumen*, *Pajak* (PG-059, PG-061, PG-06 | TASK-025 |
| INT-141 | INT- | INTERACTION_CONTRACTS · Master data — *Klien*, *Pemasok*, *Produk & Jasa*, *Lokasi Rak* (PG-054–PG-057; J-MD-02–J- | TASK-026 |
| INT-142 | INT- | INTERACTION_CONTRACTS · Master data — *Klien*, *Pemasok*, *Produk & Jasa*, *Lokasi Rak* (PG-054–PG-057; J-MD-02–J- | TASK-026 |
| INT-143 | INT- | INTERACTION_CONTRACTS · Master data — *Klien*, *Pemasok*, *Produk & Jasa*, *Lokasi Rak* (PG-054–PG-057; J-MD-02–J- | TASK-026 |
| INT-144 | INT- | INTERACTION_CONTRACTS · Master data — *Klien*, *Pemasok*, *Produk & Jasa*, *Lokasi Rak* (PG-054–PG-057; J-MD-02–J- | TASK-026 |
| INT-145 | INT- | INTERACTION_CONTRACTS · Master data — *Klien*, *Pemasok*, *Produk & Jasa*, *Lokasi Rak* (PG-054–PG-057; J-MD-02–J- | TASK-027 |
| INT-146 | INT- | INTERACTION_CONTRACTS · Master data — *Klien*, *Pemasok*, *Produk & Jasa*, *Lokasi Rak* (PG-054–PG-057; J-MD-02–J- | TASK-027 |
| INT-147 | INT- | INTERACTION_CONTRACTS · Master data — *Klien*, *Pemasok*, *Produk & Jasa*, *Lokasi Rak* (PG-054–PG-057; J-MD-02–J- | TASK-027 |
| INT-148 | INT- | INTERACTION_CONTRACTS · Master data — *Klien*, *Pemasok*, *Produk & Jasa*, *Lokasi Rak* (PG-054–PG-057; J-MD-02–J- | TASK-028 |
| INT-151 | INT- | INTERACTION_CONTRACTS · Projects — *Proyek*, *Formulir Proyek*, *Penawaran*, the journey and its completion (PRJ;  | TASK-032 |
| INT-152 | INT- | INTERACTION_CONTRACTS · Projects — *Proyek*, *Formulir Proyek*, *Penawaran*, the journey and its completion (PRJ;  | TASK-032 |
| INT-153 | INT- | INTERACTION_CONTRACTS · Projects — *Proyek*, *Formulir Proyek*, *Penawaran*, the journey and its completion (PRJ;  | TASK-032 |
| INT-154 | INT- | INTERACTION_CONTRACTS · Projects — *Proyek*, *Formulir Proyek*, *Penawaran*, the journey and its completion (PRJ;  | TASK-033 |
| INT-155 | INT- | INTERACTION_CONTRACTS · Projects — *Proyek*, *Formulir Proyek*, *Penawaran*, the journey and its completion (PRJ;  | TASK-033 |
| INT-156 | INT- | INTERACTION_CONTRACTS · Projects — *Proyek*, *Formulir Proyek*, *Penawaran*, the journey and its completion (PRJ;  | TASK-033 |
| INT-157 | INT- | INTERACTION_CONTRACTS · Projects — *Proyek*, *Formulir Proyek*, *Penawaran*, the journey and its completion (PRJ;  | TASK-033 |
| INT-158 | INT- | INTERACTION_CONTRACTS · Projects — *Proyek*, *Formulir Proyek*, *Penawaran*, the journey and its completion (PRJ;  | TASK-034 |
| INT-159 | INT- | INTERACTION_CONTRACTS · Projects — *Proyek*, *Formulir Proyek*, *Penawaran*, the journey and its completion (PRJ;  | TASK-034 |
| INT-160 | INT- | INTERACTION_CONTRACTS · Projects — *Proyek*, *Formulir Proyek*, *Penawaran*, the journey and its completion (PRJ;  | TASK-034 |
| INT-161 | INT- | INTERACTION_CONTRACTS · Projects — *Proyek*, *Formulir Proyek*, *Penawaran*, the journey and its completion (PRJ;  | TASK-055 |
| INT-162 | INT- | INTERACTION_CONTRACTS · Projects — *Proyek*, *Formulir Proyek*, *Penawaran*, the journey and its completion (PRJ;  | TASK-055 |
| INT-163 | INT- | INTERACTION_CONTRACTS · Projects — *Proyek*, *Formulir Proyek*, *Penawaran*, the journey and its completion (PRJ;  | TASK-056 |
| INT-164 | INT- | INTERACTION_CONTRACTS · Requirements — *Kesiapan dokumen* and *Dokumen Administrasi* (ADM; PG-011, PG-039; WF-ADM- | TASK-035 |
| INT-165 | INT- | INTERACTION_CONTRACTS · Requirements — *Kesiapan dokumen* and *Dokumen Administrasi* (ADM; PG-011, PG-039; WF-ADM- | TASK-035 |
| INT-166 | INT- | INTERACTION_CONTRACTS · Requirements — *Kesiapan dokumen* and *Dokumen Administrasi* (ADM; PG-011, PG-039; WF-ADM- | TASK-035 |
| INT-167 | INT- | INTERACTION_CONTRACTS · Requirements — *Kesiapan dokumen* and *Dokumen Administrasi* (ADM; PG-011, PG-039; WF-ADM- | TASK-035 |
| INT-168 | INT- | INTERACTION_CONTRACTS · Requirements — *Kesiapan dokumen* and *Dokumen Administrasi* (ADM; PG-011, PG-039; WF-ADM- | TASK-035 |
| INT-169 | INT- | INTERACTION_CONTRACTS · Requirements — *Kesiapan dokumen* and *Dokumen Administrasi* (ADM; PG-011, PG-039; WF-ADM- | TASK-035 |
| INT-171 | INT- | INTERACTION_CONTRACTS · Purchases — *Buat Pembelian* and *Detail Pembelian* (PUR; PG-016, PG-017; WF-PUR-01–WF-PUR | TASK-037 |
| INT-172 | INT- | INTERACTION_CONTRACTS · Purchases — *Buat Pembelian* and *Detail Pembelian* (PUR; PG-016, PG-017; WF-PUR-01–WF-PUR | TASK-037 |
| INT-173 | INT- | INTERACTION_CONTRACTS · Purchases — *Buat Pembelian* and *Detail Pembelian* (PUR; PG-016, PG-017; WF-PUR-01–WF-PUR | TASK-037 |
| INT-174 | INT- | INTERACTION_CONTRACTS · Purchases — *Buat Pembelian* and *Detail Pembelian* (PUR; PG-016, PG-017; WF-PUR-01–WF-PUR | TASK-037 |
| INT-175 | INT- | INTERACTION_CONTRACTS · Purchases — *Buat Pembelian* and *Detail Pembelian* (PUR; PG-016, PG-017; WF-PUR-01–WF-PUR | TASK-037 |
| INT-176 | INT- | INTERACTION_CONTRACTS · Purchases — *Buat Pembelian* and *Detail Pembelian* (PUR; PG-016, PG-017; WF-PUR-01–WF-PUR | TASK-044 |
| INT-177 | INT- | INTERACTION_CONTRACTS · Purchases — *Buat Pembelian* and *Detail Pembelian* (PUR; PG-016, PG-017; WF-PUR-01–WF-PUR | TASK-044 |
| INT-178 | INT- | INTERACTION_CONTRACTS · Purchases — *Buat Pembelian* and *Detail Pembelian* (PUR; PG-016, PG-017; WF-PUR-01–WF-PUR | TASK-037 |
| INT-179 | INT- | INTERACTION_CONTRACTS · Purchases — *Buat Pembelian* and *Detail Pembelian* (PUR; PG-016, PG-017; WF-PUR-01–WF-PUR | TASK-037 |
| INT-180 | INT- | INTERACTION_CONTRACTS · Purchases — *Buat Pembelian* and *Detail Pembelian* (PUR; PG-016, PG-017; WF-PUR-01–WF-PUR | TASK-037 |
| INT-181 | INT- | INTERACTION_CONTRACTS · Warehouse — receiving, reservations, dispatch, counts, adjustments and discrepancies (INV; | TASK-039 |
| INT-221 | INT- | INTERACTION_CONTRACTS · Documents, files and evidence (DOC; PG-035, PG-036, PG-038; WF-DOC-01, WF-DOC-02) | TASK-029 |
| INT-183 | INT- | INTERACTION_CONTRACTS · Warehouse — receiving, reservations, dispatch, counts, adjustments and discrepancies (INV; | TASK-039 |
| INT-184 | INT- | INTERACTION_CONTRACTS · Warehouse — receiving, reservations, dispatch, counts, adjustments and discrepancies (INV; | TASK-038 |
| INT-188 | INT- | INTERACTION_CONTRACTS · Warehouse — receiving, reservations, dispatch, counts, adjustments and discrepancies (INV; | TASK-041 |
| INT-210 | INT- | INTERACTION_CONTRACTS · Fulfilment — deliveries, direct shipments, handovers and client returns (FUL; PG-030–PG-03 | TASK-048 |
| INT-185 | INT- | INTERACTION_CONTRACTS · Warehouse — receiving, reservations, dispatch, counts, adjustments and discrepancies (INV; | TASK-038 |
| INT-186 | INT- | INTERACTION_CONTRACTS · Warehouse — receiving, reservations, dispatch, counts, adjustments and discrepancies (INV; | TASK-041 |
| INT-189 | INT- | INTERACTION_CONTRACTS · Warehouse — receiving, reservations, dispatch, counts, adjustments and discrepancies (INV; | TASK-041 |
| INT-190 | INT- | INTERACTION_CONTRACTS · Warehouse — receiving, reservations, dispatch, counts, adjustments and discrepancies (INV; | TASK-042 |
| INT-191 | INT- | INTERACTION_CONTRACTS · Warehouse — receiving, reservations, dispatch, counts, adjustments and discrepancies (INV; | TASK-043 |
| INT-192 | INT- | INTERACTION_CONTRACTS · Warehouse — receiving, reservations, dispatch, counts, adjustments and discrepancies (INV; | TASK-043 |
| INT-193 | INT- | INTERACTION_CONTRACTS · Warehouse — receiving, reservations, dispatch, counts, adjustments and discrepancies (INV; | TASK-043 |
| INT-194 | INT- | INTERACTION_CONTRACTS · Warehouse — receiving, reservations, dispatch, counts, adjustments and discrepancies (INV; | TASK-042, TASK-043 |
| INT-195 | INT- | INTERACTION_CONTRACTS · Warehouse — receiving, reservations, dispatch, counts, adjustments and discrepancies (INV; | TASK-042, TASK-043 |
| INT-196 | INT- | INTERACTION_CONTRACTS · Warehouse — receiving, reservations, dispatch, counts, adjustments and discrepancies (INV; | TASK-042 |
| INT-197 | INT- | INTERACTION_CONTRACTS · Warehouse — receiving, reservations, dispatch, counts, adjustments and discrepancies (INV; | TASK-043 |
| INT-198 | INT- | INTERACTION_CONTRACTS · Warehouse — receiving, reservations, dispatch, counts, adjustments and discrepancies (INV; | TASK-043 |
| INT-199 | INT- | INTERACTION_CONTRACTS · Warehouse — receiving, reservations, dispatch, counts, adjustments and discrepancies (INV; | TASK-043 |
| INT-200 | INT- | INTERACTION_CONTRACTS · Warehouse — receiving, reservations, dispatch, counts, adjustments and discrepancies (INV; | TASK-043 |
| INT-212 | INT- | INTERACTION_CONTRACTS · Warehouse — receiving, reservations, dispatch, counts, adjustments and discrepancies (INV; | TASK-040 |
| INT-213 | INT- | INTERACTION_CONTRACTS · Warehouse — receiving, reservations, dispatch, counts, adjustments and discrepancies (INV; | TASK-057 |
| INT-214 | INT- | INTERACTION_CONTRACTS · Warehouse — receiving, reservations, dispatch, counts, adjustments and discrepancies (INV; | TASK-057 |
| INT-215 | INT- | INTERACTION_CONTRACTS · Warehouse — receiving, reservations, dispatch, counts, adjustments and discrepancies (INV; | TASK-043 |
| INT-217 | INT- | INTERACTION_CONTRACTS · Warehouse — receiving, reservations, dispatch, counts, adjustments and discrepancies (INV; | TASK-043 |
| INT-201 | INT- | INTERACTION_CONTRACTS · Fulfilment — deliveries, direct shipments, handovers and client returns (FUL; PG-030–PG-03 | TASK-045 |
| INT-203 | INT- | INTERACTION_CONTRACTS · Fulfilment — deliveries, direct shipments, handovers and client returns (FUL; PG-030–PG-03 | TASK-045 |
| INT-204 | INT- | INTERACTION_CONTRACTS · Fulfilment — deliveries, direct shipments, handovers and client returns (FUL; PG-030–PG-03 | TASK-045 |
| INT-205 | INT- | INTERACTION_CONTRACTS · Fulfilment — deliveries, direct shipments, handovers and client returns (FUL; PG-030–PG-03 | TASK-046 |
| INT-206 | INT- | INTERACTION_CONTRACTS · Fulfilment — deliveries, direct shipments, handovers and client returns (FUL; PG-030–PG-03 | TASK-046 |
| INT-216 | INT- | INTERACTION_CONTRACTS · Fulfilment — deliveries, direct shipments, handovers and client returns (FUL; PG-030–PG-03 | TASK-046 |
| INT-207 | INT- | INTERACTION_CONTRACTS · Fulfilment — deliveries, direct shipments, handovers and client returns (FUL; PG-030–PG-03 | TASK-046 |
| INT-208 | INT- | INTERACTION_CONTRACTS · Fulfilment — deliveries, direct shipments, handovers and client returns (FUL; PG-030–PG-03 | TASK-047 |
| INT-209 | INT- | INTERACTION_CONTRACTS · Fulfilment — deliveries, direct shipments, handovers and client returns (FUL; PG-030–PG-03 | TASK-047 |
| INT-211 | INT- | INTERACTION_CONTRACTS · Fulfilment — deliveries, direct shipments, handovers and client returns (FUL; PG-030–PG-03 | TASK-048 |
| INT-223 | INT- | INTERACTION_CONTRACTS · Documents, files and evidence (DOC; PG-035, PG-036, PG-038; WF-DOC-01, WF-DOC-02) | TASK-029 |
| INT-224 | INT- | INTERACTION_CONTRACTS · Documents, files and evidence (DOC; PG-035, PG-036, PG-038; WF-DOC-01, WF-DOC-02) | TASK-029 |
| INT-225 | INT- | INTERACTION_CONTRACTS · Documents, files and evidence (DOC; PG-035, PG-036, PG-038; WF-DOC-01, WF-DOC-02) | TASK-029 |
| INT-226 | INT- | INTERACTION_CONTRACTS · Documents, files and evidence (DOC; PG-035, PG-036, PG-038; WF-DOC-01, WF-DOC-02) | TASK-022 |
| INT-227 | INT- | INTERACTION_CONTRACTS · Documents, files and evidence (DOC; PG-035, PG-036, PG-038; WF-DOC-01, WF-DOC-02) | TASK-022 |
| INT-228 | INT- | INTERACTION_CONTRACTS · Documents, files and evidence (DOC; PG-035, PG-036, PG-038; WF-DOC-01, WF-DOC-02) | TASK-030 |
| INT-229 | INT- | INTERACTION_CONTRACTS · Documents, files and evidence (DOC; PG-035, PG-036, PG-038; WF-DOC-01, WF-DOC-02) | TASK-030 |
| INT-230 | INT- | INTERACTION_CONTRACTS · Documents, files and evidence (DOC; PG-035, PG-036, PG-038; WF-DOC-01, WF-DOC-02) | TASK-030 |
| INT-231 | INT- | INTERACTION_CONTRACTS · Documents, files and evidence (DOC; PG-035, PG-036, PG-038; WF-DOC-01, WF-DOC-02) | TASK-030 |
| INT-241 | INT- | INTERACTION_CONTRACTS · Finance — invoices, payments, deductions, credit, other cash and bank statements (FIN; PG- | TASK-049 |
| INT-242 | INT- | INTERACTION_CONTRACTS · Finance — invoices, payments, deductions, credit, other cash and bank statements (FIN; PG- | TASK-049 |
| INT-243 | INT- | INTERACTION_CONTRACTS · Finance — invoices, payments, deductions, credit, other cash and bank statements (FIN; PG- | TASK-050 |
| INT-244 | INT- | INTERACTION_CONTRACTS · Finance — invoices, payments, deductions, credit, other cash and bank statements (FIN; PG- | TASK-050 |
| INT-245 | INT- | INTERACTION_CONTRACTS · Finance — invoices, payments, deductions, credit, other cash and bank statements (FIN; PG- | TASK-049 |
| INT-246 | INT- | INTERACTION_CONTRACTS · Finance — invoices, payments, deductions, credit, other cash and bank statements (FIN; PG- | TASK-050 |
| INT-247 | INT- | INTERACTION_CONTRACTS · Finance — invoices, payments, deductions, credit, other cash and bank statements (FIN; PG- | TASK-050 |
| INT-248 | INT- | INTERACTION_CONTRACTS · Finance — invoices, payments, deductions, credit, other cash and bank statements (FIN; PG- | TASK-050 |
| INT-250 | INT- | INTERACTION_CONTRACTS · Finance — invoices, payments, deductions, credit, other cash and bank statements (FIN; PG- | TASK-049 |
| INT-251 | INT- | INTERACTION_CONTRACTS · Finance — invoices, payments, deductions, credit, other cash and bank statements (FIN; PG- | TASK-051 |
| INT-252 | INT- | INTERACTION_CONTRACTS · Finance — invoices, payments, deductions, credit, other cash and bank statements (FIN; PG- | TASK-051 |
| INT-253 | INT- | INTERACTION_CONTRACTS · Finance — invoices, payments, deductions, credit, other cash and bank statements (FIN; PG- | TASK-051 |
| INT-254 | INT- | INTERACTION_CONTRACTS · Finance — invoices, payments, deductions, credit, other cash and bank statements (FIN; PG- | TASK-051 |
| INT-255 | INT- | INTERACTION_CONTRACTS · Finance — invoices, payments, deductions, credit, other cash and bank statements (FIN; PG- | TASK-052 |
| INT-256 | INT- | INTERACTION_CONTRACTS · Finance — invoices, payments, deductions, credit, other cash and bank statements (FIN; PG- | TASK-053 |
| INT-257 | INT- | INTERACTION_CONTRACTS · Finance — invoices, payments, deductions, credit, other cash and bank statements (FIN; PG- | TASK-054 |
| INT-258 | INT- | INTERACTION_CONTRACTS · Finance — invoices, payments, deductions, credit, other cash and bank statements (FIN; PG- | TASK-054 |
| INT-259 | INT- | INTERACTION_CONTRACTS · Finance — invoices, payments, deductions, credit, other cash and bank statements (FIN; PG- | TASK-054 |
| INT-260 | INT- | INTERACTION_CONTRACTS · Finance — invoices, payments, deductions, credit, other cash and bank statements (FIN; PG- | TASK-052 |
| INT-261 | INT- | INTERACTION_CONTRACTS · Finance — invoices, payments, deductions, credit, other cash and bank statements (FIN; PG- | TASK-051 |
| INT-262 | INT- | INTERACTION_CONTRACTS · Finance — invoices, payments, deductions, credit, other cash and bank statements (FIN; PG- | TASK-051 |
| INT-263 | INT- | INTERACTION_CONTRACTS · Finance — invoices, payments, deductions, credit, other cash and bank statements (FIN; PG- | TASK-052 |
| INT-264 | INT- | INTERACTION_CONTRACTS · Finance — invoices, payments, deductions, credit, other cash and bank statements (FIN; PG- | TASK-053 |
| INT-271 | INT- | INTERACTION_CONTRACTS · Operations — correction cases and the opening import (OPS; PG-040, PG-058; WF-MIG-01) | TASK-031 |
| INT-272 | INT- | INTERACTION_CONTRACTS · Operations — correction cases and the opening import (OPS; PG-040, PG-058; WF-MIG-01) | TASK-057 |
| INT-273 | INT- | INTERACTION_CONTRACTS · Operations — correction cases and the opening import (OPS; PG-040, PG-058; WF-MIG-01) | TASK-057 |
| INT-274 | INT- | INTERACTION_CONTRACTS · Operations — correction cases and the opening import (OPS; PG-040, PG-058; WF-MIG-01) | TASK-057 |
| INT-275 | INT- | INTERACTION_CONTRACTS · Operations — correction cases and the opening import (OPS; PG-040, PG-058; WF-MIG-01) | TASK-057 |
| INT-276 | INT- | INTERACTION_CONTRACTS · Operations — correction cases and the opening import (OPS; PG-040, PG-058; WF-MIG-01) | TASK-057 |
| INT-277 | INT- | INTERACTION_CONTRACTS · Operations — correction cases and the opening import (OPS; PG-040, PG-058; WF-MIG-01) | TASK-072 |
| INT-278 | INT- | INTERACTION_CONTRACTS · Operations — correction cases and the opening import (OPS; PG-040, PG-058; WF-MIG-01) | TASK-072 |
| INT-281 | INT- | INTERACTION_CONTRACTS · Reading, search, scanning and exports (Reporting; PG-009, PG-051–PG-053) | TASK-016, TASK-059 |
| INT-282 | INT- | INTERACTION_CONTRACTS · Reading, search, scanning and exports (Reporting; PG-009, PG-051–PG-053) | TASK-039, TASK-059 |
| INT-283 | INT- | INTERACTION_CONTRACTS · Reading, search, scanning and exports (Reporting; PG-009, PG-051–PG-053) | TASK-060 |
| INT-284 | INT- | INTERACTION_CONTRACTS · Reading, search, scanning and exports (Reporting; PG-009, PG-051–PG-053) | TASK-060 |
| INT-285 | INT- | INTERACTION_CONTRACTS · Reading, search, scanning and exports (Reporting; PG-009, PG-051–PG-053) | TASK-022 |
| IG-01 | IG- | INTERACTION_CONTRACTS · 1. Conventions | TASK-011, TASK-015 |
| IG-02 | IG- | INTERACTION_CONTRACTS · 1. Conventions | TASK-011, TASK-015 |
| IG-03 | IG- | INTERACTION_CONTRACTS · 1. Conventions | TASK-011, TASK-015 |
| IG-04 | IG- | INTERACTION_CONTRACTS · 1. Conventions | TASK-011, TASK-015 |
| IG-05 | IG- | INTERACTION_CONTRACTS · 1. Conventions | TASK-011, TASK-015 |
| D-DB-12 | D-DB- | DATABASE · 2. Design decisions | TASK-005, TASK-006, TASK-007, TASK-008, TASK-009 |
| D-DB-10 | D-DB- | DATABASE · 2. Design decisions | TASK-005, TASK-006, TASK-007, TASK-008, TASK-009 |
| D-DB-01 | D-DB- | DATABASE · 2. Design decisions | TASK-005, TASK-006, TASK-007, TASK-008, TASK-009 |
| D-DB-02 | D-DB- | DATABASE · 2. Design decisions | TASK-005, TASK-006, TASK-007, TASK-008, TASK-009 |
| D-DB-03 | D-DB- | DATABASE · 2. Design decisions | TASK-005, TASK-006, TASK-007, TASK-008, TASK-009 |
| D-DB-04 | D-DB- | DATABASE · 2. Design decisions | TASK-005, TASK-006, TASK-007, TASK-008, TASK-009 |
| D-DB-05 | D-DB- | DATABASE · 2. Design decisions | TASK-005, TASK-006, TASK-007, TASK-008, TASK-009 |
| D-DB-06 | D-DB- | DATABASE · 2. Design decisions | TASK-005, TASK-006, TASK-007, TASK-008, TASK-009 |
| D-DB-07 | D-DB- | DATABASE · 2. Design decisions | TASK-005, TASK-006, TASK-007, TASK-008, TASK-009 |
| D-DB-08 | D-DB- | DATABASE · 2. Design decisions | TASK-005, TASK-006, TASK-007, TASK-008, TASK-009 |
| D-DB-09 | D-DB- | DATABASE · 2. Design decisions | TASK-005, TASK-006, TASK-007, TASK-008, TASK-009 |
| D-DB-11 | D-DB- | DATABASE · 2. Design decisions | TASK-005, TASK-006, TASK-007, TASK-008, TASK-009 |
| D-DB-13 | D-DB- | DATABASE · 2. Design decisions | TASK-005, TASK-006, TASK-007, TASK-008, TASK-009 |
| D-DB-14 | D-DB- | DATABASE · 2. Design decisions | TASK-005, TASK-006, TASK-007, TASK-008, TASK-009 |
| C-62 | C- | DATABASE · 18. Constraint catalogue | TASK-005 |
| C-65 | C- | DATABASE · 18. Constraint catalogue | TASK-005, TASK-018, TASK-023 |
| C-10 | C- | DATABASE · 18. Constraint catalogue | TASK-006, TASK-029 |
| C-63 | C- | DATABASE · 18. Constraint catalogue | TASK-005 |
| C-64 | C- | DATABASE · 18. Constraint catalogue | TASK-005 |
| C-28 | C- | DATABASE · 18. Constraint catalogue | TASK-007, TASK-008, TASK-038, TASK-039, TASK-041, TASK-042, TASK-043, TASK-048 |
| C-01 | C- | DATABASE · 18. Constraint catalogue | TASK-006, TASK-038, TASK-039, TASK-041, TASK-042, TASK-043 |
| C-02 | C- | DATABASE · 18. Constraint catalogue | TASK-008, TASK-038, TASK-039, TASK-041, TASK-042, TASK-043 |
| C-03 | C- | DATABASE · 18. Constraint catalogue | TASK-008, TASK-038, TASK-039, TASK-041, TASK-042, TASK-043 |
| C-04 | C- | DATABASE · 18. Constraint catalogue | TASK-008, TASK-038, TASK-039, TASK-041, TASK-042, TASK-043 |
| C-05 | C- | DATABASE · 18. Constraint catalogue | TASK-007, TASK-008, TASK-038 |
| C-06 | C- | DATABASE · 18. Constraint catalogue | TASK-008, TASK-038 |
| C-07 | C- | DATABASE · 18. Constraint catalogue | TASK-008, TASK-038, TASK-039, TASK-041, TASK-042, TASK-043 |
| C-08 | C- | DATABASE · 18. Constraint catalogue | TASK-008, TASK-038, TASK-039, TASK-041, TASK-042, TASK-043 |
| C-09 | C- | DATABASE · 18. Constraint catalogue | TASK-007, TASK-008, TASK-009, TASK-038, TASK-039, TASK-041, TASK-042, TASK-043 |
| C-11 | C- | DATABASE · 18. Constraint catalogue | TASK-006, TASK-029 |
| C-12 | C- | DATABASE · 18. Constraint catalogue | TASK-006, TASK-029 |
| C-13 | C- | DATABASE · 18. Constraint catalogue | TASK-009, TASK-049, TASK-050, TASK-051, TASK-052, TASK-053, TASK-054 |
| C-14 | C- | DATABASE · 18. Constraint catalogue | TASK-009, TASK-049, TASK-050, TASK-051, TASK-052, TASK-053, TASK-054 |
| C-15 | C- | DATABASE · 18. Constraint catalogue | TASK-009, TASK-049, TASK-050, TASK-051, TASK-052, TASK-053, TASK-054 |
| C-16 | C- | DATABASE · 18. Constraint catalogue | TASK-009, TASK-013, TASK-031, TASK-049 |
| C-17 | C- | DATABASE · 18. Constraint catalogue | TASK-009 |
| C-18 | C- | DATABASE · 18. Constraint catalogue | TASK-006, TASK-049, TASK-050, TASK-051, TASK-052, TASK-053, TASK-054 |
| C-58 | C- | DATABASE · 18. Constraint catalogue | TASK-006 |
| C-19 | C- | DATABASE · 18. Constraint catalogue | TASK-006, TASK-051 |
| C-20 | C- | DATABASE · 18. Constraint catalogue | TASK-006, TASK-036, TASK-037 |
| C-21 | C- | DATABASE · 18. Constraint catalogue | TASK-007, TASK-036, TASK-037, TASK-044 |
| C-22 | C- | DATABASE · 18. Constraint catalogue | TASK-009, TASK-052 |
| C-23 | C- | DATABASE · 18. Constraint catalogue | TASK-006, TASK-007, TASK-049, TASK-050, TASK-051, TASK-052, TASK-053, TASK-054 |
| C-24 | C- | DATABASE · 18. Constraint catalogue | TASK-008, TASK-038, TASK-039, TASK-041, TASK-042, TASK-043 |
| C-25 | C- | DATABASE · 18. Constraint catalogue | TASK-007, TASK-049, TASK-050, TASK-051, TASK-052, TASK-053, TASK-054 |
| C-26 | C- | DATABASE · 18. Constraint catalogue | TASK-007, TASK-049, TASK-050, TASK-051, TASK-052, TASK-053, TASK-054 |
| C-27 | C- | DATABASE · 18. Constraint catalogue | TASK-008, TASK-041, TASK-048 |
| C-29 | C- | DATABASE · 18. Constraint catalogue | TASK-007, TASK-039 |
| C-30 | C- | DATABASE · 18. Constraint catalogue | TASK-007, TASK-045 |
| C-31 | C- | DATABASE · 18. Constraint catalogue | TASK-007, TASK-049 |
| C-32 | C- | DATABASE · 18. Constraint catalogue | TASK-005, TASK-006, TASK-007, TASK-008, TASK-009, TASK-011, TASK-016, TASK-024 |
| C-33 | C- | DATABASE · 18. Constraint catalogue | TASK-008, TASK-038, TASK-039, TASK-041, TASK-042, TASK-043 |
| C-34 | C- | DATABASE · 18. Constraint catalogue | TASK-008, TASK-043 |
| C-35 | C- | DATABASE · 18. Constraint catalogue | TASK-008, TASK-043 |
| C-36 | C- | DATABASE · 18. Constraint catalogue | TASK-008 |
| C-37 | C- | DATABASE · 18. Constraint catalogue | TASK-008 |
| C-38 | C- | DATABASE · 18. Constraint catalogue | TASK-007, TASK-032, TASK-034, TASK-055, TASK-056 |
| C-39 | C- | DATABASE · 18. Constraint catalogue | TASK-032, TASK-034, TASK-055, TASK-056 |
| C-40 | C- | DATABASE · 18. Constraint catalogue | TASK-005, TASK-011 |
| C-41 | C- | DATABASE · 18. Constraint catalogue | TASK-009, TASK-049, TASK-050, TASK-051, TASK-052, TASK-053, TASK-054 |
| C-42 | C- | DATABASE · 18. Constraint catalogue | TASK-009, TASK-049, TASK-050, TASK-051, TASK-052, TASK-053, TASK-054 |
| C-43 | C- | DATABASE · 18. Constraint catalogue | TASK-009, TASK-049 |
| C-44 | C- | DATABASE · 18. Constraint catalogue | TASK-009, TASK-051 |
| C-45 | C- | DATABASE · 18. Constraint catalogue | TASK-009, TASK-013, TASK-031, TASK-051 |
| C-46 | C- | DATABASE · 18. Constraint catalogue | TASK-005, TASK-013, TASK-031, TASK-044 |
| C-47 | C- | DATABASE · 18. Constraint catalogue | TASK-005, TASK-007, TASK-008, TASK-009, TASK-038, TASK-039, TASK-041, TASK-042, TASK-043, TASK-049, TASK-050, TASK-051, TASK-052, TASK-053, TASK-054, TASK-057 |
| C-48 | C- | DATABASE · 18. Constraint catalogue | TASK-005, TASK-006, TASK-007, TASK-008, TASK-009, TASK-013 |
| C-49 | C- | DATABASE · 18. Constraint catalogue | TASK-006, TASK-007, TASK-008, TASK-009, TASK-039 |
| C-50 | C- | DATABASE · 18. Constraint catalogue | TASK-013, TASK-015 |
| C-51 | C- | DATABASE · 18. Constraint catalogue | TASK-032, TASK-034, TASK-055, TASK-056 |
| C-52 | C- | DATABASE · 18. Constraint catalogue | TASK-006, TASK-049, TASK-050, TASK-051, TASK-052, TASK-053, TASK-054 |
| C-53 | C- | DATABASE · 18. Constraint catalogue | TASK-005, TASK-006, TASK-007, TASK-008, TASK-009, TASK-010, TASK-013, TASK-031 |
| C-54 | C- | DATABASE · 18. Constraint catalogue | TASK-011, TASK-016, TASK-024 |
| C-55 | C- | DATABASE · 18. Constraint catalogue | TASK-008, TASK-013, TASK-031, TASK-048 |
| C-56 | C- | DATABASE · 18. Constraint catalogue | TASK-009, TASK-049 |
| C-57 | C- | DATABASE · 18. Constraint catalogue | TASK-007, TASK-036, TASK-037, TASK-049, TASK-050, TASK-051, TASK-052, TASK-053, TASK-054 |
| C-59 | C- | DATABASE · 18. Constraint catalogue | TASK-006, TASK-007, TASK-008, TASK-009 |
| C-60 | C- | DATABASE · 18. Constraint catalogue | TASK-009, TASK-052 |
| C-61 | C- | DATABASE · 18. Constraint catalogue | TASK-038, TASK-039, TASK-041, TASK-042, TASK-043 |
| AZ-02 | AZ- | PERMISSIONS_MATRIX · 2. Authorization model | TASK-011 |
| AZ-13 | AZ- | PERMISSIONS_MATRIX · 2. Authorization model | TASK-011 |
| AZ-08 | AZ- | PERMISSIONS_MATRIX · 2. Authorization model | TASK-011 |
| AZ-09 | AZ- | PERMISSIONS_MATRIX · 2. Authorization model | TASK-011 |
| AZ-01 | AZ- | PERMISSIONS_MATRIX · 2. Authorization model | TASK-011 |
| AZ-03 | AZ- | PERMISSIONS_MATRIX · 2. Authorization model | TASK-011 |
| AZ-04 | AZ- | PERMISSIONS_MATRIX · 2. Authorization model | TASK-011 |
| AZ-05 | AZ- | PERMISSIONS_MATRIX · 2. Authorization model | TASK-011 |
| AZ-06 | AZ- | PERMISSIONS_MATRIX · 2. Authorization model | TASK-011 |
| AZ-07 | AZ- | PERMISSIONS_MATRIX · 2. Authorization model | TASK-011 |
| AZ-10 | AZ- | PERMISSIONS_MATRIX · 2. Authorization model | TASK-011 |
| AZ-11 | AZ- | PERMISSIONS_MATRIX · 2. Authorization model | TASK-011 |
| AZ-12 | AZ- | PERMISSIONS_MATRIX · 2. Authorization model | TASK-011 |
| RG-11 | RG- | PERMISSIONS_MATRIX · 3. Role and grant model | TASK-011, TASK-017, TASK-018, TASK-019, TASK-024 |
| RG-02 | RG- | PERMISSIONS_MATRIX · 3. Role and grant model | TASK-011, TASK-024 |
| RG-03 | RG- | PERMISSIONS_MATRIX · 3. Role and grant model | TASK-011, TASK-024 |
| RG-09 | RG- | PERMISSIONS_MATRIX · 3. Role and grant model | TASK-011, TASK-024 |
| RG-06 | RG- | PERMISSIONS_MATRIX · 3. Role and grant model | TASK-011, TASK-024 |
| RG-01 | RG- | PERMISSIONS_MATRIX · 3. Role and grant model | TASK-011, TASK-024 |
| RG-04 | RG- | PERMISSIONS_MATRIX · 3. Role and grant model | TASK-011, TASK-024 |
| RG-05 | RG- | PERMISSIONS_MATRIX · 3. Role and grant model | TASK-011, TASK-024 |
| RG-07 | RG- | PERMISSIONS_MATRIX · 3. Role and grant model | TASK-011, TASK-024 |
| RG-08 | RG- | PERMISSIONS_MATRIX · 3. Role and grant model | TASK-011, TASK-024 |
| RG-10 | RG- | PERMISSIONS_MATRIX · 3. Role and grant model | TASK-011, TASK-024 |
| RG-12 | RG- | PERMISSIONS_MATRIX · 3. Role and grant model | TASK-011, TASK-024 |
| CS-01 | CS- | PERMISSIONS_MATRIX · 6. Company-scope rules | TASK-011, TASK-016 |
| CS-02 | CS- | PERMISSIONS_MATRIX · 6. Company-scope rules | TASK-011, TASK-016 |
| CS-07 | CS- | PERMISSIONS_MATRIX · 6. Company-scope rules | TASK-011, TASK-016 |
| CS-05 | CS- | PERMISSIONS_MATRIX · 6. Company-scope rules | TASK-011, TASK-016 |
| CS-06 | CS- | PERMISSIONS_MATRIX · 6. Company-scope rules | TASK-011, TASK-016 |
| CS-03 | CS- | PERMISSIONS_MATRIX · 6. Company-scope rules | TASK-011, TASK-016 |
| CS-04 | CS- | PERMISSIONS_MATRIX · 6. Company-scope rules | TASK-011, TASK-016 |
| CS-08 | CS- | PERMISSIONS_MATRIX · 6. Company-scope rules | TASK-011, TASK-016 |
| PJ-17 | PJ- | PERMISSIONS_MATRIX · 7.3 Special projections | TASK-012 |
| PJ-13 | PJ- | PERMISSIONS_MATRIX · 7.3 Special projections | TASK-012 |
| PJ-18 | PJ- | PERMISSIONS_MATRIX · 7.3 Special projections | TASK-012 |
| PJ-08 | PJ- | PERMISSIONS_MATRIX · 7.3 Special projections | TASK-012 |
| PJ-01 | PJ- | PERMISSIONS_MATRIX · 7.3 Special projections | TASK-012 |
| PJ-14 | PJ- | PERMISSIONS_MATRIX · 7.3 Special projections | TASK-012 |
| PJ-20 | PJ- | PERMISSIONS_MATRIX · 7.3 Special projections | TASK-012 |
| PJ-10 | PJ- | PERMISSIONS_MATRIX · 7.3 Special projections | TASK-012 |
| PJ-04 | PJ- | PERMISSIONS_MATRIX · 7.3 Special projections | TASK-012 |
| PJ-02 | PJ- | PERMISSIONS_MATRIX · 7.3 Special projections | TASK-012 |
| PJ-19 | PJ- | PERMISSIONS_MATRIX · 7.3 Special projections | TASK-012 |
| PJ-03 | PJ- | PERMISSIONS_MATRIX · 7.3 Special projections | TASK-012 |
| PJ-07 | PJ- | PERMISSIONS_MATRIX · 7.3 Special projections | TASK-012 |
| PJ-15 | PJ- | PERMISSIONS_MATRIX · 7.3 Special projections | TASK-012 |
| PJ-12 | PJ- | PERMISSIONS_MATRIX · 7.3 Special projections | TASK-012 |
| PJ-05 | PJ- | PERMISSIONS_MATRIX · 7.3 Special projections | TASK-012 |
| PJ-06 | PJ- | PERMISSIONS_MATRIX · 7.3 Special projections | TASK-012 |
| PJ-11 | PJ- | PERMISSIONS_MATRIX · 7.3 Special projections | TASK-012 |
| PJ-23 | PJ- | PERMISSIONS_MATRIX · 7.3 Special projections | TASK-012 |
| PJ-09 | PJ- | PERMISSIONS_MATRIX · 7.3 Special projections | TASK-012 |
| PJ-16 | PJ- | PERMISSIONS_MATRIX · 7.3 Special projections | TASK-012 |
| PJ-21 | PJ- | PERMISSIONS_MATRIX · 7.3 Special projections | TASK-012 |
| PJ-22 | PJ- | PERMISSIONS_MATRIX · 7.3 Special projections | TASK-012 |
| RD-02 | RD- | PERMISSIONS_MATRIX · 7.4 Read classes | TASK-012 |
| RD-03 | RD- | PERMISSIONS_MATRIX · 7.4 Read classes | TASK-012 |
| RD-09 | RD- | PERMISSIONS_MATRIX · 7.4 Read classes | TASK-012 |
| RD-04 | RD- | PERMISSIONS_MATRIX · 7.4 Read classes | TASK-012 |
| RD-05 | RD- | PERMISSIONS_MATRIX · 7.4 Read classes | TASK-012 |
| RD-06 | RD- | PERMISSIONS_MATRIX · 7.4 Read classes | TASK-012 |
| RD-08 | RD- | PERMISSIONS_MATRIX · 7.4 Read classes | TASK-012 |
| RD-01 | RD- | PERMISSIONS_MATRIX · 7.4 Read classes | TASK-012 |
| RD-07 | RD- | PERMISSIONS_MATRIX · 7.4 Read classes | TASK-012 |
| RD-10 | RD- | PERMISSIONS_MATRIX · 7.4 Read classes | TASK-012 |
| XL-1 | XL- | PERMISSIONS_MATRIX · 8. Cross-company link authorization | TASK-012, TASK-041 |
| XL-4 | XL- | PERMISSIONS_MATRIX · 8. Cross-company link authorization | TASK-012, TASK-041 |
| XL-5 | XL- | PERMISSIONS_MATRIX · 8. Cross-company link authorization | TASK-012, TASK-041 |
| XL-3 | XL- | PERMISSIONS_MATRIX · 8. Cross-company link authorization | TASK-012, TASK-041 |
| XL-2 | XL- | PERMISSIONS_MATRIX · 8. Cross-company link authorization | TASK-012, TASK-041 |
| OD-01 | OD- | PERMISSIONS_MATRIX · 9. Owner-only denials | TASK-011 |
| OD-16 | OD- | PERMISSIONS_MATRIX · 9. Owner-only denials | TASK-011 |
| OD-05 | OD- | PERMISSIONS_MATRIX · 9. Owner-only denials | TASK-011 |
| OD-06 | OD- | PERMISSIONS_MATRIX · 9. Owner-only denials | TASK-011 |
| OD-07 | OD- | PERMISSIONS_MATRIX · 9. Owner-only denials | TASK-011 |
| OD-15 | OD- | PERMISSIONS_MATRIX · 9. Owner-only denials | TASK-011 |
| OD-10 | OD- | PERMISSIONS_MATRIX · 9. Owner-only denials | TASK-011 |
| OD-09 | OD- | PERMISSIONS_MATRIX · 9. Owner-only denials | TASK-011 |
| OD-02 | OD- | PERMISSIONS_MATRIX · 9. Owner-only denials | TASK-011 |
| OD-03 | OD- | PERMISSIONS_MATRIX · 9. Owner-only denials | TASK-011 |
| OD-04 | OD- | PERMISSIONS_MATRIX · 9. Owner-only denials | TASK-011 |
| OD-08 | OD- | PERMISSIONS_MATRIX · 9. Owner-only denials | TASK-011 |
| OD-11 | OD- | PERMISSIONS_MATRIX · 9. Owner-only denials | TASK-011 |
| OD-12 | OD- | PERMISSIONS_MATRIX · 9. Owner-only denials | TASK-011 |
| OD-13 | OD- | PERMISSIONS_MATRIX · 9. Owner-only denials | TASK-011 |
| OD-14 | OD- | PERMISSIONS_MATRIX · 9. Owner-only denials | TASK-011 |
| DP-07 | DP- | PERMISSIONS_MATRIX · 11. Data-path coverage matrix | TASK-011, TASK-012 |
| DP-09 | DP- | PERMISSIONS_MATRIX · 11. Data-path coverage matrix | TASK-011, TASK-012 |
| DP-14 | DP- | PERMISSIONS_MATRIX · 11. Data-path coverage matrix | TASK-011, TASK-012 |
| DP-17 | DP- | PERMISSIONS_MATRIX · 11. Data-path coverage matrix | TASK-011, TASK-012 |
| DP-11 | DP- | PERMISSIONS_MATRIX · 11. Data-path coverage matrix | TASK-011, TASK-012 |
| DP-13 | DP- | PERMISSIONS_MATRIX · 11. Data-path coverage matrix | TASK-011, TASK-012 |
| DP-16 | DP- | PERMISSIONS_MATRIX · 11. Data-path coverage matrix | TASK-011, TASK-012 |
| DP-18 | DP- | PERMISSIONS_MATRIX · 11. Data-path coverage matrix | TASK-011, TASK-012 |
| DP-01 | DP- | PERMISSIONS_MATRIX · 11. Data-path coverage matrix | TASK-011, TASK-012 |
| DP-02 | DP- | PERMISSIONS_MATRIX · 11. Data-path coverage matrix | TASK-011, TASK-012 |
| DP-03 | DP- | PERMISSIONS_MATRIX · 11. Data-path coverage matrix | TASK-011, TASK-012 |
| DP-04 | DP- | PERMISSIONS_MATRIX · 11. Data-path coverage matrix | TASK-011, TASK-012 |
| DP-05 | DP- | PERMISSIONS_MATRIX · 11. Data-path coverage matrix | TASK-011, TASK-012 |
| DP-06 | DP- | PERMISSIONS_MATRIX · 11. Data-path coverage matrix | TASK-011, TASK-012 |
| DP-08 | DP- | PERMISSIONS_MATRIX · 11. Data-path coverage matrix | TASK-011, TASK-012 |
| DP-10 | DP- | PERMISSIONS_MATRIX · 11. Data-path coverage matrix | TASK-011, TASK-012 |
| DP-12 | DP- | PERMISSIONS_MATRIX · 11. Data-path coverage matrix | TASK-011, TASK-012 |
| DP-15 | DP- | PERMISSIONS_MATRIX · 11. Data-path coverage matrix | TASK-011, TASK-012 |
| DP-19 | DP- | PERMISSIONS_MATRIX · 11. Data-path coverage matrix | TASK-011, TASK-012 |
| DP-20 | DP- | PERMISSIONS_MATRIX · 11. Data-path coverage matrix | TASK-011, TASK-012 |
| W4-01 | W4- | PERMISSIONS_MATRIX · 5. WORKFLOWS §4 mapping | TASK-011 |
| W4-27 | W4- | PERMISSIONS_MATRIX · 5. WORKFLOWS §4 mapping | TASK-011 |
| W4-26 | W4- | PERMISSIONS_MATRIX · 5. WORKFLOWS §4 mapping | TASK-011 |
| W4-12 | W4- | PERMISSIONS_MATRIX · 5. WORKFLOWS §4 mapping | TASK-011 |
| W4-02 | W4- | PERMISSIONS_MATRIX · 5. WORKFLOWS §4 mapping | TASK-011 |
| W4-03 | W4- | PERMISSIONS_MATRIX · 5. WORKFLOWS §4 mapping | TASK-011 |
| W4-04 | W4- | PERMISSIONS_MATRIX · 5. WORKFLOWS §4 mapping | TASK-011 |
| W4-05 | W4- | PERMISSIONS_MATRIX · 5. WORKFLOWS §4 mapping | TASK-011 |
| W4-06 | W4- | PERMISSIONS_MATRIX · 5. WORKFLOWS §4 mapping | TASK-011 |
| W4-07 | W4- | PERMISSIONS_MATRIX · 5. WORKFLOWS §4 mapping | TASK-011 |
| W4-08 | W4- | PERMISSIONS_MATRIX · 5. WORKFLOWS §4 mapping | TASK-011 |
| W4-09 | W4- | PERMISSIONS_MATRIX · 5. WORKFLOWS §4 mapping | TASK-011 |
| W4-25 | W4- | PERMISSIONS_MATRIX · 5. WORKFLOWS §4 mapping | TASK-011 |
| W4-10 | W4- | PERMISSIONS_MATRIX · 5. WORKFLOWS §4 mapping | TASK-011 |
| W4-11 | W4- | PERMISSIONS_MATRIX · 5. WORKFLOWS §4 mapping | TASK-011 |
| W4-13 | W4- | PERMISSIONS_MATRIX · 5. WORKFLOWS §4 mapping | TASK-011 |
| W4-14 | W4- | PERMISSIONS_MATRIX · 5. WORKFLOWS §4 mapping | TASK-011 |
| W4-15 | W4- | PERMISSIONS_MATRIX · 5. WORKFLOWS §4 mapping | TASK-011 |
| W4-16 | W4- | PERMISSIONS_MATRIX · 5. WORKFLOWS §4 mapping | TASK-011 |
| W4-17 | W4- | PERMISSIONS_MATRIX · 5. WORKFLOWS §4 mapping | TASK-011 |
| W4-24 | W4- | PERMISSIONS_MATRIX · 5. WORKFLOWS §4 mapping | TASK-011 |
| W4-18 | W4- | PERMISSIONS_MATRIX · 5. WORKFLOWS §4 mapping | TASK-011 |
| W4-20 | W4- | PERMISSIONS_MATRIX · 5. WORKFLOWS §4 mapping | TASK-011 |
| W4-19 | W4- | PERMISSIONS_MATRIX · 5. WORKFLOWS §4 mapping | TASK-011 |
| W4-21 | W4- | PERMISSIONS_MATRIX · 5. WORKFLOWS §4 mapping | TASK-011 |
| W4-22 | W4- | PERMISSIONS_MATRIX · 5. WORKFLOWS §4 mapping | TASK-011 |
| W4-23 | W4- | PERMISSIONS_MATRIX · 5. WORKFLOWS §4 mapping | TASK-011 |
| D-PM-08 | D-PM- | PERMISSIONS_MATRIX · 12. Design decisions | TASK-011, TASK-012 |
| D-PM-06 | D-PM- | PERMISSIONS_MATRIX · 12. Design decisions | TASK-011, TASK-012 |
| D-PM-02 | D-PM- | PERMISSIONS_MATRIX · 12. Design decisions | TASK-011, TASK-012 |
| D-PM-01 | D-PM- | PERMISSIONS_MATRIX · 12. Design decisions | TASK-011, TASK-012 |
| D-PM-03 | D-PM- | PERMISSIONS_MATRIX · 12. Design decisions | TASK-011, TASK-012 |
| D-PM-04 | D-PM- | PERMISSIONS_MATRIX · 12. Design decisions | TASK-011, TASK-012 |
| D-PM-05 | D-PM- | PERMISSIONS_MATRIX · 12. Design decisions | TASK-011, TASK-012 |
| D-PM-07 | D-PM- | PERMISSIONS_MATRIX · 12. Design decisions | TASK-011, TASK-012 |
| D-PM-09 | D-PM- | PERMISSIONS_MATRIX · 12. Design decisions | TASK-011, TASK-012 |
| D-PM-10 | D-PM- | PERMISSIONS_MATRIX · 12. Design decisions | TASK-011, TASK-012 |
| D-PM-11 | D-PM- | PERMISSIONS_MATRIX · 12. Design decisions | TASK-011, TASK-012 |
| D-PM-12 | D-PM- | PERMISSIONS_MATRIX · 12. Design decisions | TASK-011, TASK-012 |
| AU-03 | AU- | SECURITY · 2. Authentication, sessions and credentials | TASK-017 |
| AU-04 | AU- | SECURITY · 2. Authentication, sessions and credentials | TASK-017 |
| AU-06 | AU- | SECURITY · 2. Authentication, sessions and credentials | TASK-017 |
| AU-09 | AU- | SECURITY · 2. Authentication, sessions and credentials | TASK-017, TASK-024 |
| AU-11 | AU- | SECURITY · 2. Authentication, sessions and credentials | TASK-005, TASK-017 |
| AU-15 | AU- | SECURITY · 2. Authentication, sessions and credentials | TASK-017 |
| AU-16 | AU- | SECURITY · 2. Authentication, sessions and credentials | TASK-018 |
| AU-18 | AU- | SECURITY · 2. Authentication, sessions and credentials | TASK-018 |
| AU-24 | AU- | SECURITY · 2. Authentication, sessions and credentials | TASK-019, TASK-023, TASK-024 |
| AU-05 | AU- | SECURITY · 2. Authentication, sessions and credentials | TASK-017 |
| AU-21 | AU- | SECURITY · 2. Authentication, sessions and credentials | TASK-018, TASK-020 |
| AU-12 | AU- | SECURITY · 2. Authentication, sessions and credentials | TASK-017, TASK-024 |
| AU-19 | AU- | SECURITY · 2. Authentication, sessions and credentials | TASK-018, TASK-023 |
| AU-20 | AU- | SECURITY · 2. Authentication, sessions and credentials | TASK-018, TASK-020, TASK-024 |
| AU-22 | AU- | SECURITY · 2. Authentication, sessions and credentials | TASK-019 |
| AU-14 | AU- | SECURITY · 2. Authentication, sessions and credentials | TASK-020, TASK-076, TASK-085 |
| AU-25 | AU- | SECURITY · 2. Authentication, sessions and credentials | TASK-020 |
| AU-02 | AU- | SECURITY · 2. Authentication, sessions and credentials | TASK-017 |
| AU-07 | AU- | SECURITY · 2. Authentication, sessions and credentials | TASK-016, TASK-017 |
| AU-08 | AU- | SECURITY · 2. Authentication, sessions and credentials | TASK-017 |
| AU-17 | AU- | SECURITY · 2. Authentication, sessions and credentials | TASK-017, TASK-024 |
| AU-13 | AU- | SECURITY · 2. Authentication, sessions and credentials | TASK-017, TASK-023 |
| AU-01 | AU- | SECURITY · 2. Authentication, sessions and credentials | TASK-017, TASK-024 |
| AU-23 | AU- | SECURITY · 2. Authentication, sessions and credentials | TASK-019, TASK-023 |
| AU-10 | AU- | SECURITY · 2. Authentication, sessions and credentials | TASK-017 |
| EN-03 | EN- | SECURITY · 3. Enforcement architecture | TASK-011 |
| EN-01 | EN- | SECURITY · 3. Enforcement architecture | TASK-011 |
| EN-02 | EN- | SECURITY · 3. Enforcement architecture | TASK-011 |
| EN-04 | EN- | SECURITY · 3. Enforcement architecture | TASK-011 |
| EN-05 | EN- | SECURITY · 3. Enforcement architecture | TASK-011 |
| EN-06 | EN- | SECURITY · 3. Enforcement architecture | TASK-011 |
| EN-07 | EN- | SECURITY · 3. Enforcement architecture | TASK-011 |
| EN-08 | EN- | SECURITY · 3. Enforcement architecture | TASK-011 |
| WS-08 | WS- | SECURITY · 4. Web and input security | TASK-001, TASK-015, TASK-072 |
| WS-12 | WS- | SECURITY · 4. Web and input security | TASK-017 |
| WS-07 | WS- | SECURITY · 4. Web and input security | TASK-012, TASK-016 |
| WS-11 | WS- | SECURITY · 4. Web and input security | TASK-071, TASK-072 |
| WS-10 | WS- | SECURITY · 4. Web and input security | TASK-072, TASK-084 |
| WS-04 | WS- | SECURITY · 4. Web and input security | TASK-001, TASK-017 |
| WS-01 | WS- | SECURITY · 4. Web and input security | TASK-002, TASK-013 |
| WS-02 | WS- | SECURITY · 4. Web and input security | TASK-011, TASK-013 |
| WS-03 | WS- | SECURITY · 4. Web and input security | TASK-013, TASK-016, TASK-059 |
| WS-05 | WS- | SECURITY · 4. Web and input security | TASK-017 |
| WS-06 | WS- | SECURITY · 4. Web and input security | TASK-001, TASK-072 |
| WS-09 | WS- | SECURITY · 4. Web and input security | TASK-002, TASK-015 |
| FL-10 | FL- | SECURITY · 5. File security | TASK-022, TASK-030 |
| FL-08 | FL- | SECURITY · 5. File security | TASK-022, TASK-030 |
| FL-07 | FL- | SECURITY · 5. File security | TASK-022, TASK-030 |
| FL-01 | FL- | SECURITY · 5. File security | TASK-022, TASK-030 |
| FL-02 | FL- | SECURITY · 5. File security | TASK-022, TASK-030 |
| FL-03 | FL- | SECURITY · 5. File security | TASK-022, TASK-030 |
| FL-06 | FL- | SECURITY · 5. File security | TASK-022, TASK-030 |
| FL-04 | FL- | SECURITY · 5. File security | TASK-022, TASK-030 |
| FL-05 | FL- | SECURITY · 5. File security | TASK-022, TASK-030 |
| FL-09 | FL- | SECURITY · 5. File security | TASK-022, TASK-030 |
| FL-11 | FL- | SECURITY · 5. File security | TASK-022, TASK-030 |
| LG-02 | LG- | SECURITY · 6. Audit and security logging | TASK-010 |
| LG-01 | LG- | SECURITY · 6. Audit and security logging | TASK-010, TASK-017, TASK-018, TASK-019 |
| LG-04 | LG- | SECURITY · 6. Audit and security logging | TASK-010 |
| LG-07 | LG- | SECURITY · 6. Audit and security logging | TASK-010 |
| LG-03 | LG- | SECURITY · 6. Audit and security logging | TASK-010 |
| LG-06 | LG- | SECURITY · 6. Audit and security logging | TASK-010 |
| LG-05 | LG- | SECURITY · 6. Audit and security logging | TASK-010 |
| SX-01 | SX- | SECURITY · 7. Secrets, production boundary and supply chain | TASK-001, TASK-072 |
| SX-02 | SX- | SECURITY · 7. Secrets, production boundary and supply chain | TASK-076, TASK-085 |
| SX-04 | SX- | SECURITY · 7. Secrets, production boundary and supply chain | TASK-020, TASK-076 |
| SX-03 | SX- | SECURITY · 7. Secrets, production boundary and supply chain | TASK-072, TASK-084 |
| SX-05 | SX- | SECURITY · 7. Secrets, production boundary and supply chain | TASK-002, TASK-004 |
| SX-06 | SX- | SECURITY · 7. Secrets, production boundary and supply chain | TASK-002, TASK-003 |
| SX-07 | SX- | SECURITY · 7. Secrets, production boundary and supply chain | TASK-074, TASK-084 |
| TM-01 | TM- | SECURITY · 8. Threat model | TASK-010, TASK-017, TASK-018, TASK-069, TASK-071, TASK-072 |
| TM-04 | TM- | SECURITY · 8. Threat model | TASK-016, TASK-017, TASK-019, TASK-069 |
| TM-06 | TM- | SECURITY · 8. Threat model | TASK-010, TASK-017, TASK-018, TASK-020, TASK-024, TASK-069 |
| TM-57 | TM- | SECURITY · 8. Threat model | TASK-010, TASK-018, TASK-020, TASK-024, TASK-069, TASK-076 |
| TM-59 | TM- | SECURITY · 8. Threat model | TASK-019, TASK-023, TASK-069 |
| TM-73 | TM- | SECURITY · 8. Threat model | TASK-010, TASK-017, TASK-018, TASK-019, TASK-020, TASK-023, TASK-024, TASK-069 |
| TM-61 | TM- | SECURITY · 8. Threat model | TASK-016, TASK-017, TASK-019, TASK-069 |
| TM-72 | TM- | SECURITY · 8. Threat model | TASK-010, TASK-018, TASK-019, TASK-020, TASK-023, TASK-024, TASK-069 |
| TM-63 | TM- | SECURITY · 8. Threat model | TASK-010, TASK-017, TASK-018, TASK-023, TASK-069 |
| TM-53 | TM- | SECURITY · 8. Threat model | TASK-010, TASK-012, TASK-041, TASK-069 |
| TM-02 | TM- | SECURITY · 8. Threat model | TASK-017, TASK-019, TASK-024, TASK-069 |
| TM-03 | TM- | SECURITY · 8. Threat model | TASK-017, TASK-018, TASK-019, TASK-069 |
| TM-05 | TM- | SECURITY · 8. Threat model | TASK-010, TASK-017, TASK-024, TASK-069 |
| TM-69 | TM- | SECURITY · 8. Threat model | TASK-017, TASK-018, TASK-069 |
| TM-70 | TM- | SECURITY · 8. Threat model | TASK-010, TASK-018, TASK-019, TASK-069 |
| TM-07 | TM- | SECURITY · 8. Threat model | TASK-017, TASK-024, TASK-069, TASK-071, TASK-072 |
| TM-08 | TM- | SECURITY · 8. Threat model | TASK-001, TASK-017, TASK-069 |
| TM-09 | TM- | SECURITY · 8. Threat model | TASK-002, TASK-011, TASK-013, TASK-069 |
| TM-10 | TM- | SECURITY · 8. Threat model | TASK-011, TASK-016, TASK-069 |
| TM-11 | TM- | SECURITY · 8. Threat model | TASK-011, TASK-069 |
| TM-12 | TM- | SECURITY · 8. Threat model | TASK-011, TASK-016, TASK-069 |
| TM-13 | TM- | SECURITY · 8. Threat model | TASK-012, TASK-069 |
| TM-14 | TM- | SECURITY · 8. Threat model | TASK-012, TASK-069 |
| TM-15 | TM- | SECURITY · 8. Threat model | TASK-011, TASK-016, TASK-069 |
| TM-16 | TM- | SECURITY · 8. Threat model | TASK-022, TASK-030, TASK-069 |
| TM-17 | TM- | SECURITY · 8. Threat model | TASK-011, TASK-016, TASK-069 |
| TM-18 | TM- | SECURITY · 8. Threat model | TASK-012, TASK-069 |
| TM-19 | TM- | SECURITY · 8. Threat model | TASK-011, TASK-012, TASK-016, TASK-069 |
| TM-20 | TM- | SECURITY · 8. Threat model | TASK-012, TASK-022, TASK-030, TASK-069 |
| TM-21 | TM- | SECURITY · 8. Threat model | TASK-012, TASK-069 |
| TM-22 | TM- | SECURITY · 8. Threat model | TASK-069 |
| TM-23 | TM- | SECURITY · 8. Threat model | TASK-022, TASK-030, TASK-069 |
| TM-24 | TM- | SECURITY · 8. Threat model | TASK-012, TASK-069 |
| TM-25 | TM- | SECURITY · 8. Threat model | TASK-012, TASK-041, TASK-069 |
| TM-26 | TM- | SECURITY · 8. Threat model | TASK-069 |
| TM-27 | TM- | SECURITY · 8. Threat model | TASK-011, TASK-016, TASK-069, TASK-072, TASK-084 |
| TM-28 | TM- | SECURITY · 8. Threat model | TASK-001, TASK-012, TASK-015, TASK-016, TASK-017, TASK-069, TASK-072 |
| TM-29 | TM- | SECURITY · 8. Threat model | TASK-011, TASK-017, TASK-024, TASK-069 |
| TM-30 | TM- | SECURITY · 8. Threat model | TASK-011, TASK-069 |
| TM-31 | TM- | SECURITY · 8. Threat model | TASK-011, TASK-069 |
| TM-32 | TM- | SECURITY · 8. Threat model | TASK-011, TASK-069 |
| TM-33 | TM- | SECURITY · 8. Threat model | TASK-011, TASK-069 |
| TM-34 | TM- | SECURITY · 8. Threat model | TASK-011, TASK-069 |
| TM-35 | TM- | SECURITY · 8. Threat model | TASK-011, TASK-069 |
| TM-36 | TM- | SECURITY · 8. Threat model | TASK-011, TASK-069 |
| TM-37 | TM- | SECURITY · 8. Threat model | TASK-002, TASK-015, TASK-022, TASK-030, TASK-069 |
| TM-38 | TM- | SECURITY · 8. Threat model | TASK-022, TASK-030, TASK-069 |
| TM-39 | TM- | SECURITY · 8. Threat model | TASK-022, TASK-030, TASK-069 |
| TM-40 | TM- | SECURITY · 8. Threat model | TASK-022, TASK-030, TASK-069 |
| TM-41 | TM- | SECURITY · 8. Threat model | TASK-022, TASK-030, TASK-069 |
| TM-42 | TM- | SECURITY · 8. Threat model | TASK-017, TASK-022, TASK-030, TASK-069 |
| TM-43 | TM- | SECURITY · 8. Threat model | TASK-013, TASK-016, TASK-017, TASK-059, TASK-069 |
| TM-44 | TM- | SECURITY · 8. Threat model | TASK-010, TASK-069 |
| TM-45 | TM- | SECURITY · 8. Threat model | TASK-011, TASK-017, TASK-024, TASK-069 |
| TM-46 | TM- | SECURITY · 8. Threat model | TASK-022, TASK-030, TASK-069 |
| TM-47 | TM- | SECURITY · 8. Threat model | TASK-069 |
| TM-48 | TM- | SECURITY · 8. Threat model | TASK-001, TASK-002, TASK-003, TASK-010, TASK-069, TASK-072 |
| TM-49 | TM- | SECURITY · 8. Threat model | TASK-002, TASK-004, TASK-069 |
| TM-50 | TM- | SECURITY · 8. Threat model | TASK-001, TASK-015, TASK-069, TASK-072 |
| TM-51 | TM- | SECURITY · 8. Threat model | TASK-010, TASK-069 |
| TM-52 | TM- | SECURITY · 8. Threat model | TASK-010, TASK-012, TASK-017, TASK-023, TASK-024, TASK-069 |
| TM-54 | TM- | SECURITY · 8. Threat model | TASK-012, TASK-069 |
| TM-55 | TM- | SECURITY · 8. Threat model | TASK-012, TASK-022, TASK-030, TASK-069 |
| TM-56 | TM- | SECURITY · 8. Threat model | TASK-011, TASK-069 |
| TM-58 | TM- | SECURITY · 8. Threat model | TASK-017, TASK-069 |
| TM-60 | TM- | SECURITY · 8. Threat model | TASK-010, TASK-019, TASK-023, TASK-024, TASK-069 |
| TM-62 | TM- | SECURITY · 8. Threat model | TASK-019, TASK-023, TASK-024, TASK-069 |
| TM-64 | TM- | SECURITY · 8. Threat model | TASK-018, TASK-069 |
| TM-65 | TM- | SECURITY · 8. Threat model | TASK-001, TASK-018, TASK-069, TASK-072, TASK-074, TASK-076, TASK-084, TASK-085 |
| TM-66 | TM- | SECURITY · 8. Threat model | TASK-010, TASK-017, TASK-018, TASK-020, TASK-023, TASK-024, TASK-069 |
| TM-67 | TM- | SECURITY · 8. Threat model | TASK-010, TASK-017, TASK-018, TASK-023, TASK-024, TASK-069 |
| TM-68 | TM- | SECURITY · 8. Threat model | TASK-017, TASK-018, TASK-020, TASK-024, TASK-069, TASK-076 |
| TM-71 | TM- | SECURITY · 8. Threat model | TASK-010, TASK-017, TASK-069 |
| D-SEC-06 | D-SEC- | SECURITY · 9. Design decisions | disposition SUPERSEDED — superseded by D-SEC-15, which TASK-017 builds |
| D-SEC-14 | D-SEC- | SECURITY · 9. Design decisions | disposition SUPERSEDED — superseded by D-SEC-16, which TASK-017, TASK-018 and TASK-023 build |
| D-SEC-15 | D-SEC- | SECURITY · 9. Design decisions | TASK-017 |
| D-SEC-16 | D-SEC- | SECURITY · 9. Design decisions | TASK-017, TASK-018, TASK-023 |
| D-SEC-17 | D-SEC- | SECURITY · 9. Design decisions | TASK-017, TASK-018, TASK-023 |
| D-SEC-21 | D-SEC- | SECURITY · 9. Design decisions | TASK-019, TASK-023 |
| D-SEC-18 | D-SEC- | SECURITY · 9. Design decisions | TASK-018 |
| D-SEC-19 | D-SEC- | SECURITY · 9. Design decisions | TASK-011, TASK-018, TASK-020, TASK-024 |
| D-SEC-01 | D-SEC- | SECURITY · 9. Design decisions | TASK-011, TASK-017 |
| D-SEC-04 | D-SEC- | SECURITY · 9. Design decisions | TASK-011, TASK-017 |
| D-SEC-02 | D-SEC- | SECURITY · 9. Design decisions | TASK-011, TASK-017 |
| D-SEC-03 | D-SEC- | SECURITY · 9. Design decisions | TASK-011, TASK-017 |
| D-SEC-05 | D-SEC- | SECURITY · 9. Design decisions | TASK-011, TASK-017 |
| D-SEC-12 | D-SEC- | SECURITY · 9. Design decisions | TASK-011, TASK-017 |
| D-SEC-20 | D-SEC- | SECURITY · 9. Design decisions | TASK-018, TASK-019, TASK-023, TASK-024 |
| D-SEC-07 | D-SEC- | SECURITY · 9. Design decisions | TASK-011, TASK-017 |
| D-SEC-08 | D-SEC- | SECURITY · 9. Design decisions | TASK-011, TASK-017 |
| D-SEC-09 | D-SEC- | SECURITY · 9. Design decisions | TASK-011, TASK-017 |
| D-SEC-10 | D-SEC- | SECURITY · 9. Design decisions | TASK-011, TASK-017 |
| D-SEC-11 | D-SEC- | SECURITY · 9. Design decisions | TASK-011, TASK-017 |
| D-SEC-13 | D-SEC- | SECURITY · 9. Design decisions | TASK-011, TASK-017 |
| H6-13 | H6- | SECURITY · P6 — mechanisms only | TASK-018, TASK-023 |
| H6-16 | H6- | SECURITY · P6 — mechanisms only | TASK-013, TASK-017 |
| H6-15 | H6- | SECURITY · P6 — mechanisms only | TASK-017, TASK-018, TASK-019, TASK-020, TASK-023, TASK-024 |
| H6-07 | H6- | SECURITY · P6 — mechanisms only | TASK-017 |
| H6-11 | H6- | SECURITY · P6 — mechanisms only | TASK-017 |
| H6-14 | H6- | SECURITY · P6 — mechanisms only | TASK-019, TASK-023 |
| H6-01 | H6- | SECURITY · P6 — mechanisms only | TASK-011 |
| H6-12 | H6- | SECURITY · P6 — mechanisms only | TASK-013 |
| H6-02 | H6- | SECURITY · P6 — mechanisms only | TASK-013 |
| H6-03 | H6- | SECURITY · P6 — mechanisms only | TASK-013 |
| H6-04 | H6- | SECURITY · P6 — mechanisms only | TASK-005, TASK-013, TASK-060 |
| H6-05 | H6- | SECURITY · P6 — mechanisms only | TASK-013 |
| H6-06 | H6- | SECURITY · P6 — mechanisms only | TASK-016, TASK-017, TASK-024 |
| H6-08 | H6- | SECURITY · P6 — mechanisms only | TASK-011 |
| H6-09 | H6- | SECURITY · P6 — mechanisms only | TASK-013 |
| H6-10 | H6- | SECURITY · P6 — mechanisms only | TASK-013 |
| H7-15 | H7- | SECURITY · P7 — presentation only | TASK-017, TASK-018, TASK-020, TASK-024 |
| H7-19 | H7- | SECURITY · P7 — presentation only | TASK-016, TASK-017 |
| H7-17 | H7- | SECURITY · P7 — presentation only | TASK-017, TASK-018, TASK-019, TASK-020, TASK-023, TASK-024 |
| H7-12 | H7- | SECURITY · P7 — presentation only | TASK-017 |
| H7-13 | H7- | SECURITY · P7 — presentation only | TASK-016, TASK-017 |
| H7-05 | H7- | SECURITY · P7 — presentation only | TASK-016, TASK-017 |
| H7-14 | H7- | SECURITY · P7 — presentation only | TASK-017, TASK-023, TASK-024 |
| H7-18 | H7- | SECURITY · P7 — presentation only | TASK-017, TASK-018, TASK-019, TASK-023 |
| H7-11 | H7- | SECURITY · P7 — presentation only | TASK-015, TASK-016 |
| H7-16 | H7- | SECURITY · P7 — presentation only | TASK-019, TASK-023, TASK-024 |
| H7-01 | H7- | SECURITY · P7 — presentation only | TASK-017, TASK-024 |
| H7-02 | H7- | SECURITY · P7 — presentation only | TASK-015, TASK-016 |
| H7-03 | H7- | SECURITY · P7 — presentation only | TASK-015, TASK-016 |
| H7-04 | H7- | SECURITY · P7 — presentation only | TASK-017, TASK-024 |
| H7-06 | H7- | SECURITY · P7 — presentation only | TASK-015, TASK-016 |
| H7-07 | H7- | SECURITY · P7 — presentation only | TASK-015, TASK-016 |
| H7-08 | H7- | SECURITY · P7 — presentation only | TASK-015, TASK-016 |
| H7-09 | H7- | SECURITY · P7 — presentation only | TASK-017 |
| H7-10 | H7- | SECURITY · P7 — presentation only | TASK-015, TASK-016 |
| H8-16 | H8- | SECURITY · P8 — required denial and security tests (P8 creates its own files) | TASK-017, TASK-069 |
| H8-22 | H8- | SECURITY · P8 — required denial and security tests (P8 creates its own files) | TASK-018, TASK-069 |
| H8-17 | H8- | SECURITY · P8 — required denial and security tests (P8 creates its own files) | TASK-017, TASK-018, TASK-069 |
| H8-20 | H8- | SECURITY · P8 — required denial and security tests (P8 creates its own files) | TASK-001, TASK-010, TASK-015, TASK-019, TASK-023, TASK-024, TASK-069, TASK-072 |
| H8-19 | H8- | SECURITY · P8 — required denial and security tests (P8 creates its own files) | TASK-018, TASK-019, TASK-020, TASK-023, TASK-024, TASK-069, TASK-076 |
| H8-21 | H8- | SECURITY · P8 — required denial and security tests (P8 creates its own files) | TASK-018, TASK-020, TASK-069, TASK-076 |
| H8-02 | H8- | SECURITY · P8 — required denial and security tests (P8 creates its own files) | TASK-011, TASK-018, TASK-069 |
| H8-03 | H8- | SECURITY · P8 — required denial and security tests (P8 creates its own files) | TASK-011, TASK-069 |
| H8-01 | H8- | SECURITY · P8 — required denial and security tests (P8 creates its own files) | TASK-069 |
| H8-08 | H8- | SECURITY · P8 — required denial and security tests (P8 creates its own files) | TASK-069 |
| H8-15 | H8- | SECURITY · P8 — required denial and security tests (P8 creates its own files) | TASK-069 |
| H8-06 | H8- | SECURITY · P8 — required denial and security tests (P8 creates its own files) | TASK-069 |
| H8-14 | H8- | SECURITY · P8 — required denial and security tests (P8 creates its own files) | TASK-069 |
| H8-07 | H8- | SECURITY · P8 — required denial and security tests (P8 creates its own files) | TASK-069 |
| H8-04 | H8- | SECURITY · P8 — required denial and security tests (P8 creates its own files) | TASK-011, TASK-069 |
| H8-12 | H8- | SECURITY · P8 — required denial and security tests (P8 creates its own files) | TASK-069 |
| H8-11 | H8- | SECURITY · P8 — required denial and security tests (P8 creates its own files) | TASK-069 |
| H8-13 | H8- | SECURITY · P8 — required denial and security tests (P8 creates its own files) | TASK-069 |
| H8-09 | H8- | SECURITY · P8 — required denial and security tests (P8 creates its own files) | TASK-069 |
| H8-05 | H8- | SECURITY · P8 — required denial and security tests (P8 creates its own files) | TASK-011, TASK-069 |
| H8-10 | H8- | SECURITY · P8 — required denial and security tests (P8 creates its own files) | TASK-069 |
| H8-18 | H8- | SECURITY · P8 — required denial and security tests (P8 creates its own files) | TASK-017, TASK-018, TASK-020, TASK-023, TASK-024, TASK-069 |
| H9-13 | H9- | SECURITY · P9 — operations | TASK-082 |
| H9-15 | H9- | SECURITY · P9 — operations | TASK-075, TASK-080 |
| H9-07 | H9- | SECURITY · P9 — operations | TASK-020, TASK-076 |
| H9-03 | H9- | SECURITY · P9 — operations | TASK-013, TASK-076 |
| H9-14 | H9- | SECURITY · P9 — operations | TASK-076, TASK-085 |
| H9-10 | H9- | SECURITY · P9 — operations | TASK-075, TASK-080 |
| H9-11 | H9- | SECURITY · P9 — operations | TASK-017, TASK-079 |
| H9-04 | H9- | SECURITY · P9 — operations | TASK-005, TASK-017, TASK-072 |
| H9-06 | H9- | SECURITY · P9 — operations | TASK-071 |
| H9-01 | H9- | SECURITY · P9 — operations | TASK-005, TASK-072 |
| H9-02 | H9- | SECURITY · P9 — operations | TASK-072 |
| H9-12 | H9- | SECURITY · P9 — operations | TASK-022 |
| H9-05 | H9- | SECURITY · P9 — operations | TASK-022, TASK-079 |
| H9-08 | H9- | SECURITY · P9 — operations | TASK-010, TASK-072 |
| H9-09 | H9- | SECURITY · P9 — operations | TASK-072, TASK-074, TASK-076 |
| TX-07 | TX- | CONCURRENCY_IDEMPOTENCY · 3. Transaction and isolation model | TASK-013 |
| TX-08 | TX- | CONCURRENCY_IDEMPOTENCY · 3. Transaction and isolation model | TASK-013 |
| TX-01 | TX- | CONCURRENCY_IDEMPOTENCY · 3. Transaction and isolation model | TASK-013 |
| TX-02 | TX- | CONCURRENCY_IDEMPOTENCY · 3. Transaction and isolation model | TASK-013 |
| TX-03 | TX- | CONCURRENCY_IDEMPOTENCY · 3. Transaction and isolation model | TASK-013 |
| TX-04 | TX- | CONCURRENCY_IDEMPOTENCY · 3. Transaction and isolation model | TASK-013 |
| TX-05 | TX- | CONCURRENCY_IDEMPOTENCY · 3. Transaction and isolation model | TASK-013 |
| TX-06 | TX- | CONCURRENCY_IDEMPOTENCY · 3. Transaction and isolation model | TASK-013 |
| TX-09 | TX- | CONCURRENCY_IDEMPOTENCY · 3. Transaction and isolation model | TASK-013 |
| TX-10 | TX- | CONCURRENCY_IDEMPOTENCY · 3. Transaction and isolation model | TASK-013 |
| D-CC-10 | D-CC- | CONCURRENCY_IDEMPOTENCY · 2. Design decisions | TASK-013 |
| D-CC-02 | D-CC- | CONCURRENCY_IDEMPOTENCY · 2. Design decisions | TASK-013 |
| D-CC-01 | D-CC- | CONCURRENCY_IDEMPOTENCY · 2. Design decisions | TASK-013 |
| D-CC-03 | D-CC- | CONCURRENCY_IDEMPOTENCY · 2. Design decisions | TASK-013 |
| D-CC-04 | D-CC- | CONCURRENCY_IDEMPOTENCY · 2. Design decisions | TASK-013 |
| D-CC-05 | D-CC- | CONCURRENCY_IDEMPOTENCY · 2. Design decisions | TASK-013 |
| D-CC-06 | D-CC- | CONCURRENCY_IDEMPOTENCY · 2. Design decisions | TASK-013 |
| D-CC-07 | D-CC- | CONCURRENCY_IDEMPOTENCY · 2. Design decisions | TASK-013 |
| D-CC-08 | D-CC- | CONCURRENCY_IDEMPOTENCY · 2. Design decisions | TASK-013 |
| D-CC-09 | D-CC- | CONCURRENCY_IDEMPOTENCY · 2. Design decisions | TASK-013 |
| D-CC-11 | D-CC- | CONCURRENCY_IDEMPOTENCY · 2. Design decisions | TASK-013 |
| D-CC-12 | D-CC- | CONCURRENCY_IDEMPOTENCY · 2. Design decisions | TASK-013 |
| LR-08 | LR- | CONCURRENCY_IDEMPOTENCY · 4.1 Lock rules | TASK-013 |
| LR-05 | LR- | CONCURRENCY_IDEMPOTENCY · 4.1 Lock rules | TASK-013 |
| LR-03 | LR- | CONCURRENCY_IDEMPOTENCY · 4.1 Lock rules | TASK-013 |
| LR-12 | LR- | CONCURRENCY_IDEMPOTENCY · 4.1 Lock rules | TASK-013 |
| LR-01 | LR- | CONCURRENCY_IDEMPOTENCY · 4.1 Lock rules | TASK-013 |
| LR-02 | LR- | CONCURRENCY_IDEMPOTENCY · 4.1 Lock rules | TASK-013 |
| LR-06 | LR- | CONCURRENCY_IDEMPOTENCY · 4.1 Lock rules | TASK-013 |
| LR-09 | LR- | CONCURRENCY_IDEMPOTENCY · 4.1 Lock rules | TASK-013 |
| LR-04 | LR- | CONCURRENCY_IDEMPOTENCY · 4.1 Lock rules | TASK-013 |
| LR-07 | LR- | CONCURRENCY_IDEMPOTENCY · 4.1 Lock rules | TASK-013 |
| LR-10 | LR- | CONCURRENCY_IDEMPOTENCY · 4.1 Lock rules | TASK-013 |
| LR-11 | LR- | CONCURRENCY_IDEMPOTENCY · 4.1 Lock rules | TASK-013 |
| LK-03 | LK- | CONCURRENCY_IDEMPOTENCY · 4.2 Lock classes | TASK-013 |
| LK-31 | LK- | CONCURRENCY_IDEMPOTENCY · 4.2 Lock classes | TASK-013, TASK-021 |
| LK-00 | LK- | CONCURRENCY_IDEMPOTENCY · 4.2 Lock classes | TASK-013 |
| LK-28 | LK- | CONCURRENCY_IDEMPOTENCY · 4.2 Lock classes | TASK-013 |
| LK-04 | LK- | CONCURRENCY_IDEMPOTENCY · 4.2 Lock classes | TASK-013 |
| LK-05 | LK- | CONCURRENCY_IDEMPOTENCY · 4.2 Lock classes | TASK-013 |
| LK-06 | LK- | CONCURRENCY_IDEMPOTENCY · 4.2 Lock classes | TASK-013 |
| LK-07 | LK- | CONCURRENCY_IDEMPOTENCY · 4.2 Lock classes | TASK-013 |
| LK-11 | LK- | CONCURRENCY_IDEMPOTENCY · 4.2 Lock classes | TASK-013 |
| LK-13 | LK- | CONCURRENCY_IDEMPOTENCY · 4.2 Lock classes | TASK-013 |
| LK-21 | LK- | CONCURRENCY_IDEMPOTENCY · 4.2 Lock classes | TASK-013 |
| LK-01 | LK- | CONCURRENCY_IDEMPOTENCY · 4.2 Lock classes | TASK-013 |
| LK-02 | LK- | CONCURRENCY_IDEMPOTENCY · 4.2 Lock classes | TASK-013 |
| LK-08 | LK- | CONCURRENCY_IDEMPOTENCY · 4.2 Lock classes | TASK-013 |
| LK-09 | LK- | CONCURRENCY_IDEMPOTENCY · 4.2 Lock classes | TASK-013 |
| LK-10 | LK- | CONCURRENCY_IDEMPOTENCY · 4.2 Lock classes | TASK-013 |
| LK-12 | LK- | CONCURRENCY_IDEMPOTENCY · 4.2 Lock classes | TASK-013 |
| LK-14 | LK- | CONCURRENCY_IDEMPOTENCY · 4.2 Lock classes | TASK-013 |
| LK-15 | LK- | CONCURRENCY_IDEMPOTENCY · 4.2 Lock classes | TASK-013 |
| LK-16 | LK- | CONCURRENCY_IDEMPOTENCY · 4.2 Lock classes | TASK-013 |
| LK-17 | LK- | CONCURRENCY_IDEMPOTENCY · 4.2 Lock classes | TASK-013 |
| LK-18 | LK- | CONCURRENCY_IDEMPOTENCY · 4.2 Lock classes | TASK-013 |
| LK-19 | LK- | CONCURRENCY_IDEMPOTENCY · 4.2 Lock classes | TASK-013 |
| LK-20 | LK- | CONCURRENCY_IDEMPOTENCY · 4.2 Lock classes | TASK-013 |
| LK-22 | LK- | CONCURRENCY_IDEMPOTENCY · 4.2 Lock classes | TASK-013 |
| LK-23 | LK- | CONCURRENCY_IDEMPOTENCY · 4.2 Lock classes | TASK-013 |
| LK-24 | LK- | CONCURRENCY_IDEMPOTENCY · 4.2 Lock classes | TASK-013 |
| LK-25 | LK- | CONCURRENCY_IDEMPOTENCY · 4.2 Lock classes | TASK-013 |
| LK-26 | LK- | CONCURRENCY_IDEMPOTENCY · 4.2 Lock classes | TASK-013 |
| LK-27 | LK- | CONCURRENCY_IDEMPOTENCY · 4.2 Lock classes | TASK-013 |
| LK-29 | LK- | CONCURRENCY_IDEMPOTENCY · 4.2 Lock classes | TASK-013 |
| LK-30 | LK- | CONCURRENCY_IDEMPOTENCY · 4.2 Lock classes | TASK-013 |
| NX-04 | NX- | CONCURRENCY_IDEMPOTENCY · 7.3 Command families outside AX-01–37 | TASK-037 |
| NX-01 | NX- | CONCURRENCY_IDEMPOTENCY · 7.3 Command families outside AX-01–37 | TASK-021, TASK-025, TASK-026, TASK-027, TASK-028 |
| NX-02 | NX- | CONCURRENCY_IDEMPOTENCY · 7.3 Command families outside AX-01–37 | TASK-023, TASK-024 |
| NX-03 | NX- | CONCURRENCY_IDEMPOTENCY · 7.3 Command families outside AX-01–37 | TASK-032, TASK-033, TASK-035 |
| NX-14 | NX- | CONCURRENCY_IDEMPOTENCY · 5. Data-dependent lock-set protocol | TASK-030 |
| NX-12 | NX- | CONCURRENCY_IDEMPOTENCY · 5. Data-dependent lock-set protocol | TASK-029 |
| NX-08 | NX- | CONCURRENCY_IDEMPOTENCY · 5. Data-dependent lock-set protocol | TASK-051 |
| NX-11 | NX- | CONCURRENCY_IDEMPOTENCY · 5. Data-dependent lock-set protocol | TASK-040, TASK-057 |
| NX-07 | NX- | CONCURRENCY_IDEMPOTENCY · 7.3 Command families outside AX-01–37 | TASK-030 |
| NX-10 | NX- | CONCURRENCY_IDEMPOTENCY · 7.3 Command families outside AX-01–37 | TASK-054 |
| NX-16 | NX- | CONCURRENCY_IDEMPOTENCY · 7.3 Command families outside AX-01–37 | TASK-044 |
| NX-06 | NX- | CONCURRENCY_IDEMPOTENCY · 7.3 Command families outside AX-01–37 | TASK-031 |
| NX-05 | NX- | CONCURRENCY_IDEMPOTENCY · 7.3 Command families outside AX-01–37 | TASK-042, TASK-043 |
| NX-09 | NX- | CONCURRENCY_IDEMPOTENCY · 7.3 Command families outside AX-01–37 | TASK-049, TASK-050 |
| NX-13 | NX- | CONCURRENCY_IDEMPOTENCY · 7.3 Command families outside AX-01–37 | TASK-021, TASK-057 |
| NX-15 | NX- | CONCURRENCY_IDEMPOTENCY · 7.3 Command families outside AX-01–37 | TASK-045 |
| GU-02 | GU- | CONCURRENCY_IDEMPOTENCY · 8. Guards and first-use rows | TASK-013 |
| GU-01 | GU- | CONCURRENCY_IDEMPOTENCY · 8. Guards and first-use rows | TASK-013 |
| GU-06 | GU- | CONCURRENCY_IDEMPOTENCY · 8. Guards and first-use rows | TASK-013 |
| GU-03 | GU- | CONCURRENCY_IDEMPOTENCY · 8. Guards and first-use rows | TASK-013 |
| GU-04 | GU- | CONCURRENCY_IDEMPOTENCY · 8. Guards and first-use rows | TASK-013 |
| GU-05 | GU- | CONCURRENCY_IDEMPOTENCY · 8. Guards and first-use rows | TASK-013 |
| GU-07 | GU- | CONCURRENCY_IDEMPOTENCY · 8. Guards and first-use rows | TASK-013 |
| FU-01 | FU- | CONCURRENCY_IDEMPOTENCY · 8. Guards and first-use rows | TASK-013 |
| FU-13 | FU- | CONCURRENCY_IDEMPOTENCY · 8. Guards and first-use rows | TASK-013 |
| FU-10 | FU- | CONCURRENCY_IDEMPOTENCY · 8. Guards and first-use rows | TASK-013 |
| FU-09 | FU- | CONCURRENCY_IDEMPOTENCY · 8. Guards and first-use rows | TASK-013 |
| FU-02 | FU- | CONCURRENCY_IDEMPOTENCY · 8. Guards and first-use rows | TASK-013 |
| FU-04 | FU- | CONCURRENCY_IDEMPOTENCY · 8. Guards and first-use rows | TASK-013 |
| FU-05 | FU- | CONCURRENCY_IDEMPOTENCY · 8. Guards and first-use rows | TASK-013 |
| FU-06 | FU- | CONCURRENCY_IDEMPOTENCY · 8. Guards and first-use rows | TASK-013 |
| FU-11 | FU- | CONCURRENCY_IDEMPOTENCY · 8. Guards and first-use rows | TASK-013 |
| FU-07 | FU- | CONCURRENCY_IDEMPOTENCY · 8. Guards and first-use rows | TASK-013 |
| FU-12 | FU- | CONCURRENCY_IDEMPOTENCY · 8. Guards and first-use rows | TASK-013 |
| FU-03 | FU- | CONCURRENCY_IDEMPOTENCY · 8. Guards and first-use rows | TASK-013 |
| FU-08 | FU- | CONCURRENCY_IDEMPOTENCY · 8. Guards and first-use rows | TASK-013 |
| ST-09 | ST- | CONCURRENCY_IDEMPOTENCY · 9. Stale state | TASK-013 |
| ST-03 | ST- | CONCURRENCY_IDEMPOTENCY · 9. Stale state | TASK-013 |
| ST-08 | ST- | CONCURRENCY_IDEMPOTENCY · 9. Stale state | TASK-013 |
| ST-01 | ST- | CONCURRENCY_IDEMPOTENCY · 9. Stale state | TASK-013 |
| ST-02 | ST- | CONCURRENCY_IDEMPOTENCY · 9. Stale state | TASK-013 |
| ST-07 | ST- | CONCURRENCY_IDEMPOTENCY · 9. Stale state | TASK-013 |
| ST-04 | ST- | CONCURRENCY_IDEMPOTENCY · 9. Stale state | TASK-013 |
| ST-05 | ST- | CONCURRENCY_IDEMPOTENCY · 9. Stale state | TASK-013 |
| ST-06 | ST- | CONCURRENCY_IDEMPOTENCY · 9. Stale state | TASK-013 |
| CI-09 | CI- | CONCURRENCY_IDEMPOTENCY · 10. Command identity and replay | TASK-013, TASK-017, TASK-018, TASK-019 |
| CI-03 | CI- | CONCURRENCY_IDEMPOTENCY · 10. Command identity and replay | TASK-013 |
| CI-01 | CI- | CONCURRENCY_IDEMPOTENCY · 10. Command identity and replay | TASK-013 |
| CI-02 | CI- | CONCURRENCY_IDEMPOTENCY · 10. Command identity and replay | TASK-013 |
| CI-05 | CI- | CONCURRENCY_IDEMPOTENCY · 10. Command identity and replay | TASK-013 |
| CI-08 | CI- | CONCURRENCY_IDEMPOTENCY · 10. Command identity and replay | TASK-013 |
| CI-07 | CI- | CONCURRENCY_IDEMPOTENCY · 10. Command identity and replay | TASK-013 |
| CI-04 | CI- | CONCURRENCY_IDEMPOTENCY · 10. Command identity and replay | TASK-013 |
| CI-06 | CI- | CONCURRENCY_IDEMPOTENCY · 10. Command identity and replay | TASK-013 |
| BD-19 | BD- | CONCURRENCY_IDEMPOTENCY · 11. Separate duplicates | TASK-013, TASK-019, TASK-023 |
| BD-02 | BD- | CONCURRENCY_IDEMPOTENCY · 11. Separate duplicates | TASK-049, TASK-050, TASK-051, TASK-052, TASK-053, TASK-054 |
| BD-13 | BD- | CONCURRENCY_IDEMPOTENCY · 11. Separate duplicates | TASK-034, TASK-041, TASK-042, TASK-043, TASK-045, TASK-048, TASK-049, TASK-055 |
| BD-01 | BD- | CONCURRENCY_IDEMPOTENCY · 11. Separate duplicates | TASK-039 |
| BD-09 | BD- | CONCURRENCY_IDEMPOTENCY · 11. Separate duplicates | TASK-043 |
| BD-04 | BD- | CONCURRENCY_IDEMPOTENCY · 11. Separate duplicates | TASK-046 |
| BD-05 | BD- | CONCURRENCY_IDEMPOTENCY · 11. Separate duplicates | TASK-034 |
| BD-06 | BD- | CONCURRENCY_IDEMPOTENCY · 11. Separate duplicates | TASK-029, TASK-049 |
| BD-07 | BD- | CONCURRENCY_IDEMPOTENCY · 11. Separate duplicates | TASK-050 |
| BD-03 | BD- | CONCURRENCY_IDEMPOTENCY · 11. Separate duplicates | TASK-051 |
| BD-08 | BD- | CONCURRENCY_IDEMPOTENCY · 11. Separate duplicates | TASK-052 |
| BD-10 | BD- | CONCURRENCY_IDEMPOTENCY · 11. Separate duplicates | TASK-055 |
| BD-11 | BD- | CONCURRENCY_IDEMPOTENCY · 11. Separate duplicates | TASK-057 |
| BD-17 | BD- | CONCURRENCY_IDEMPOTENCY · 11. Separate duplicates | TASK-036, TASK-037 |
| BD-12 | BD- | CONCURRENCY_IDEMPOTENCY · 11. Separate duplicates | TASK-043 |
| BD-15 | BD- | CONCURRENCY_IDEMPOTENCY · 11. Separate duplicates | TASK-022, TASK-030 |
| BD-14 | BD- | CONCURRENCY_IDEMPOTENCY · 11. Separate duplicates | TASK-030, TASK-049, TASK-050, TASK-051, TASK-052, TASK-053, TASK-054 |
| BD-16 | BD- | CONCURRENCY_IDEMPOTENCY · 11. Separate duplicates | TASK-013 |
| BD-18 | BD- | CONCURRENCY_IDEMPOTENCY · 11. Separate duplicates | TASK-039, TASK-046 |
| SQ-05 | SQ- | CONCURRENCY_IDEMPOTENCY · 12. Failure mapping | TASK-013 |
| SQ-15 | SQ- | CONCURRENCY_IDEMPOTENCY · 12. Failure mapping | TASK-013 |
| SQ-13 | SQ- | CONCURRENCY_IDEMPOTENCY · 12. Failure mapping | TASK-013 |
| SQ-23 | SQ- | CONCURRENCY_IDEMPOTENCY · 12. Failure mapping | TASK-013 |
| SQ-04 | SQ- | CONCURRENCY_IDEMPOTENCY · 12. Failure mapping | TASK-013 |
| SQ-08 | SQ- | CONCURRENCY_IDEMPOTENCY · 12. Failure mapping | TASK-013 |
| SQ-10 | SQ- | CONCURRENCY_IDEMPOTENCY · 12. Failure mapping | TASK-013 |
| SQ-21 | SQ- | CONCURRENCY_IDEMPOTENCY · 12. Failure mapping | TASK-013 |
| SQ-09 | SQ- | CONCURRENCY_IDEMPOTENCY · 12. Failure mapping | TASK-013 |
| SQ-11 | SQ- | CONCURRENCY_IDEMPOTENCY · 12. Failure mapping | TASK-013 |
| SQ-20 | SQ- | CONCURRENCY_IDEMPOTENCY · 12. Failure mapping | TASK-013 |
| SQ-22 | SQ- | CONCURRENCY_IDEMPOTENCY · 12. Failure mapping | TASK-013 |
| SQ-18 | SQ- | CONCURRENCY_IDEMPOTENCY · 12. Failure mapping | TASK-013 |
| SQ-02 | SQ- | CONCURRENCY_IDEMPOTENCY · 12. Failure mapping | TASK-013 |
| SQ-03 | SQ- | CONCURRENCY_IDEMPOTENCY · 12. Failure mapping | TASK-013 |
| SQ-16 | SQ- | CONCURRENCY_IDEMPOTENCY · 12. Failure mapping | TASK-013 |
| SQ-14 | SQ- | CONCURRENCY_IDEMPOTENCY · 12. Failure mapping | TASK-013 |
| SQ-07 | SQ- | CONCURRENCY_IDEMPOTENCY · 12. Failure mapping | TASK-013 |
| SQ-01 | SQ- | CONCURRENCY_IDEMPOTENCY · 12. Failure mapping | TASK-013 |
| SQ-06 | SQ- | CONCURRENCY_IDEMPOTENCY · 12. Failure mapping | TASK-013 |
| SQ-19 | SQ- | CONCURRENCY_IDEMPOTENCY · 12. Failure mapping | TASK-013 |
| SQ-12 | SQ- | CONCURRENCY_IDEMPOTENCY · 12. Failure mapping | TASK-013 |
| SQ-17 | SQ- | CONCURRENCY_IDEMPOTENCY · 12. Failure mapping | TASK-013 |
| RY-04 | RY- | CONCURRENCY_IDEMPOTENCY · 13. Retry and timeouts | TASK-013 |
| RY-03 | RY- | CONCURRENCY_IDEMPOTENCY · 13. Retry and timeouts | TASK-013 |
| RY-02 | RY- | CONCURRENCY_IDEMPOTENCY · 13. Retry and timeouts | TASK-013 |
| RY-01 | RY- | CONCURRENCY_IDEMPOTENCY · 13. Retry and timeouts | TASK-013 |
| RY-05 | RY- | CONCURRENCY_IDEMPOTENCY · 13. Retry and timeouts | TASK-013 |
| RY-06 | RY- | CONCURRENCY_IDEMPOTENCY · 13. Retry and timeouts | TASK-013 |
| RY-07 | RY- | CONCURRENCY_IDEMPOTENCY · 13. Retry and timeouts | TASK-013 |
| RY-08 | RY- | CONCURRENCY_IDEMPOTENCY · 13. Retry and timeouts | TASK-013 |
| RY-09 | RY- | CONCURRENCY_IDEMPOTENCY · 13. Retry and timeouts | TASK-013 |
| NM-08 | NM- | CONCURRENCY_IDEMPOTENCY · 14. Numbering concurrency | TASK-021 |
| NM-01 | NM- | CONCURRENCY_IDEMPOTENCY · 14. Numbering concurrency | TASK-021 |
| NM-03 | NM- | CONCURRENCY_IDEMPOTENCY · 14. Numbering concurrency | TASK-021 |
| NM-04 | NM- | CONCURRENCY_IDEMPOTENCY · 14. Numbering concurrency | TASK-021 |
| NM-07 | NM- | CONCURRENCY_IDEMPOTENCY · 14. Numbering concurrency | TASK-021 |
| NM-02 | NM- | CONCURRENCY_IDEMPOTENCY · 14. Numbering concurrency | TASK-021 |
| NM-05 | NM- | CONCURRENCY_IDEMPOTENCY · 14. Numbering concurrency | TASK-021 |
| NM-06 | NM- | CONCURRENCY_IDEMPOTENCY · 14. Numbering concurrency | TASK-021 |
| NM-09 | NM- | CONCURRENCY_IDEMPOTENCY · 14. Numbering concurrency | TASK-021 |
| NM-10 | NM- | CONCURRENCY_IDEMPOTENCY · 14. Numbering concurrency | TASK-021 |
| JB-08 | JB- | CONCURRENCY_IDEMPOTENCY · 15. Jobs, scheduler and queue | TASK-022 |
| JB-01 | JB- | CONCURRENCY_IDEMPOTENCY · 5. Data-dependent lock-set protocol | TASK-022 |
| JB-07 | JB- | CONCURRENCY_IDEMPOTENCY · 15. Jobs, scheduler and queue | TASK-022 |
| JB-06 | JB- | CONCURRENCY_IDEMPOTENCY · 15. Jobs, scheduler and queue | TASK-022 |
| JB-02 | JB- | CONCURRENCY_IDEMPOTENCY · 15. Jobs, scheduler and queue | TASK-022 |
| JB-09 | JB- | CONCURRENCY_IDEMPOTENCY · 15. Jobs, scheduler and queue | TASK-022 |
| JB-03 | JB- | CONCURRENCY_IDEMPOTENCY · 15. Jobs, scheduler and queue | TASK-022 |
| JB-04 | JB- | CONCURRENCY_IDEMPOTENCY · 15. Jobs, scheduler and queue | TASK-022 |
| JB-05 | JB- | CONCURRENCY_IDEMPOTENCY · 15. Jobs, scheduler and queue | TASK-022 |
| RV-05 | RV- | CONCURRENCY_IDEMPOTENCY · 16. Authorization and revocation races | TASK-011, TASK-013 |
| RV-10 | RV- | CONCURRENCY_IDEMPOTENCY · 16. Authorization and revocation races | TASK-011, TASK-013 |
| RV-11 | RV- | CONCURRENCY_IDEMPOTENCY · 16. Authorization and revocation races | TASK-011, TASK-013 |
| RV-16 | RV- | CONCURRENCY_IDEMPOTENCY · 16. Authorization and revocation races | TASK-011, TASK-013 |
| RV-12 | RV- | CONCURRENCY_IDEMPOTENCY · 16. Authorization and revocation races | TASK-011, TASK-013 |
| RV-13 | RV- | CONCURRENCY_IDEMPOTENCY · 16. Authorization and revocation races | TASK-011, TASK-013 |
| RV-15 | RV- | CONCURRENCY_IDEMPOTENCY · 16. Authorization and revocation races | TASK-011, TASK-013 |
| RV-17 | RV- | CONCURRENCY_IDEMPOTENCY · 16. Authorization and revocation races | TASK-011, TASK-013 |
| RV-14 | RV- | CONCURRENCY_IDEMPOTENCY · 16. Authorization and revocation races | TASK-011, TASK-013 |
| RV-18 | RV- | CONCURRENCY_IDEMPOTENCY · 16. Authorization and revocation races | TASK-011, TASK-013 |
| RV-02 | RV- | CONCURRENCY_IDEMPOTENCY · 16. Authorization and revocation races | TASK-011, TASK-013 |
| RV-08 | RV- | CONCURRENCY_IDEMPOTENCY · 16. Authorization and revocation races | TASK-011, TASK-013 |
| RV-01 | RV- | CONCURRENCY_IDEMPOTENCY · 10. Command identity and replay | TASK-011, TASK-013 |
| RV-07 | RV- | CONCURRENCY_IDEMPOTENCY · 16. Authorization and revocation races | TASK-011, TASK-013 |
| RV-03 | RV- | CONCURRENCY_IDEMPOTENCY · 16. Authorization and revocation races | TASK-011, TASK-013 |
| RV-04 | RV- | CONCURRENCY_IDEMPOTENCY · 16. Authorization and revocation races | TASK-011, TASK-013 |
| RV-06 | RV- | CONCURRENCY_IDEMPOTENCY · 16. Authorization and revocation races | TASK-011, TASK-013 |
| RV-09 | RV- | CONCURRENCY_IDEMPOTENCY · 16. Authorization and revocation races | TASK-011, TASK-013 |
| M-39 | M- | CONCURRENCY_IDEMPOTENCY · 18. Mandatory concurrency and failure scenarios | TASK-013, TASK-018, TASK-023, TASK-069, TASK-070 |
| M-43 | M- | CONCURRENCY_IDEMPOTENCY · 18. Mandatory concurrency and failure scenarios | TASK-013, TASK-069, TASK-070, TASK-082 |
| M-44 | M- | CONCURRENCY_IDEMPOTENCY · 18. Mandatory concurrency and failure scenarios | TASK-013, TASK-069, TASK-070 |
| M-45 | M- | CONCURRENCY_IDEMPOTENCY · 18. Mandatory concurrency and failure scenarios | TASK-013, TASK-024, TASK-069, TASK-070 |
| M-46 | M- | CONCURRENCY_IDEMPOTENCY · 18. Mandatory concurrency and failure scenarios | TASK-013, TASK-020, TASK-069, TASK-070, TASK-076, TASK-081 |
| M-12 | M- | CONCURRENCY_IDEMPOTENCY · 18. Mandatory concurrency and failure scenarios | TASK-013, TASK-051, TASK-070 |
| M-01 | M- | CONCURRENCY_IDEMPOTENCY · 18. Mandatory concurrency and failure scenarios | TASK-013, TASK-041, TASK-044, TASK-070 |
| M-38 | M- | CONCURRENCY_IDEMPOTENCY · 18. Mandatory concurrency and failure scenarios | TASK-013, TASK-029, TASK-033, TASK-049, TASK-064, TASK-070 |
| M-13 | M- | CONCURRENCY_IDEMPOTENCY · 18. Mandatory concurrency and failure scenarios | TASK-013, TASK-024, TASK-051, TASK-069, TASK-070 |
| M-22 | M- | CONCURRENCY_IDEMPOTENCY · 18. Mandatory concurrency and failure scenarios | TASK-013, TASK-024, TASK-069, TASK-070 |
| M-02 | M- | CONCURRENCY_IDEMPOTENCY · 18. Mandatory concurrency and failure scenarios | TASK-013, TASK-038, TASK-070 |
| M-03 | M- | CONCURRENCY_IDEMPOTENCY · 18. Mandatory concurrency and failure scenarios | TASK-013, TASK-038, TASK-041, TASK-070 |
| M-04 | M- | CONCURRENCY_IDEMPOTENCY · 18. Mandatory concurrency and failure scenarios | TASK-013, TASK-039, TASK-041, TASK-042, TASK-043, TASK-070 |
| M-05 | M- | CONCURRENCY_IDEMPOTENCY · 18. Mandatory concurrency and failure scenarios | TASK-013, TASK-039, TASK-041, TASK-070 |
| M-06 | M- | CONCURRENCY_IDEMPOTENCY · 18. Mandatory concurrency and failure scenarios | TASK-013, TASK-041, TASK-043, TASK-070 |
| M-07 | M- | CONCURRENCY_IDEMPOTENCY · 18. Mandatory concurrency and failure scenarios | TASK-013, TASK-036, TASK-037, TASK-041, TASK-044, TASK-048, TASK-070 |
| M-08 | M- | CONCURRENCY_IDEMPOTENCY · 18. Mandatory concurrency and failure scenarios | TASK-013, TASK-051, TASK-070 |
| M-09 | M- | CONCURRENCY_IDEMPOTENCY · 18. Mandatory concurrency and failure scenarios | TASK-013, TASK-034, TASK-037, TASK-038, TASK-042, TASK-051, TASK-070 |
| M-10 | M- | CONCURRENCY_IDEMPOTENCY · 18. Mandatory concurrency and failure scenarios | TASK-013, TASK-034, TASK-039, TASK-041, TASK-051, TASK-064, TASK-070 |
| M-11 | M- | CONCURRENCY_IDEMPOTENCY · 18. Mandatory concurrency and failure scenarios | TASK-013, TASK-051, TASK-070 |
| M-14 | M- | CONCURRENCY_IDEMPOTENCY · 18. Mandatory concurrency and failure scenarios | TASK-013, TASK-051, TASK-052, TASK-053, TASK-070 |
| M-15 | M- | CONCURRENCY_IDEMPOTENCY · 18. Mandatory concurrency and failure scenarios | TASK-013, TASK-021, TASK-029, TASK-037, TASK-049, TASK-070 |
| M-16 | M- | CONCURRENCY_IDEMPOTENCY · 18. Mandatory concurrency and failure scenarios | TASK-013, TASK-029, TASK-049, TASK-070 |
| M-17 | M- | CONCURRENCY_IDEMPOTENCY · 18. Mandatory concurrency and failure scenarios | TASK-013, TASK-021, TASK-029, TASK-049, TASK-070 |
| M-18 | M- | CONCURRENCY_IDEMPOTENCY · 18. Mandatory concurrency and failure scenarios | TASK-013, TASK-021, TASK-029, TASK-049, TASK-057, TASK-070 |
| M-19 | M- | CONCURRENCY_IDEMPOTENCY · 18. Mandatory concurrency and failure scenarios | TASK-013, TASK-029, TASK-033, TASK-049, TASK-070 |
| M-20 | M- | CONCURRENCY_IDEMPOTENCY · 18. Mandatory concurrency and failure scenarios | TASK-013, TASK-024, TASK-069, TASK-070 |
| M-21 | M- | CONCURRENCY_IDEMPOTENCY · 18. Mandatory concurrency and failure scenarios | TASK-013, TASK-024, TASK-069, TASK-070 |
| M-23 | M- | CONCURRENCY_IDEMPOTENCY · 18. Mandatory concurrency and failure scenarios | TASK-013, TASK-060, TASK-069, TASK-070 |
| M-24 | M- | CONCURRENCY_IDEMPOTENCY · 18. Mandatory concurrency and failure scenarios | TASK-013, TASK-069, TASK-070 |
| M-25 | M- | CONCURRENCY_IDEMPOTENCY · 18. Mandatory concurrency and failure scenarios | TASK-013, TASK-069, TASK-070 |
| M-26 | M- | CONCURRENCY_IDEMPOTENCY · 18. Mandatory concurrency and failure scenarios | TASK-013, TASK-050, TASK-055, TASK-070 |
| M-27 | M- | CONCURRENCY_IDEMPOTENCY · 18. Mandatory concurrency and failure scenarios | TASK-013, TASK-055, TASK-070 |
| M-28 | M- | CONCURRENCY_IDEMPOTENCY · 18. Mandatory concurrency and failure scenarios | TASK-013, TASK-051, TASK-070 |
| M-29 | M- | CONCURRENCY_IDEMPOTENCY · 18. Mandatory concurrency and failure scenarios | TASK-013, TASK-070 |
| M-30 | M- | CONCURRENCY_IDEMPOTENCY · 18. Mandatory concurrency and failure scenarios | TASK-013, TASK-039, TASK-042, TASK-043, TASK-070 |
| M-31 | M- | CONCURRENCY_IDEMPOTENCY · 18. Mandatory concurrency and failure scenarios | TASK-013, TASK-021, TASK-022, TASK-029, TASK-070 |
| M-32 | M- | CONCURRENCY_IDEMPOTENCY · 18. Mandatory concurrency and failure scenarios | TASK-013, TASK-022, TASK-029, TASK-070 |
| M-33 | M- | CONCURRENCY_IDEMPOTENCY · 18. Mandatory concurrency and failure scenarios | TASK-013, TASK-022, TASK-057, TASK-070, TASK-077, TASK-081, TASK-087 |
| M-34 | M- | CONCURRENCY_IDEMPOTENCY · 18. Mandatory concurrency and failure scenarios | TASK-013, TASK-057, TASK-070 |
| M-35 | M- | CONCURRENCY_IDEMPOTENCY · 18. Mandatory concurrency and failure scenarios | TASK-013, TASK-039, TASK-046, TASK-070 |
| M-36 | M- | CONCURRENCY_IDEMPOTENCY · 18. Mandatory concurrency and failure scenarios | TASK-013, TASK-037, TASK-039, TASK-070 |
| M-37 | M- | CONCURRENCY_IDEMPOTENCY · 18. Mandatory concurrency and failure scenarios | TASK-013, TASK-049, TASK-051, TASK-052, TASK-070 |
| M-40 | M- | CONCURRENCY_IDEMPOTENCY · 18. Mandatory concurrency and failure scenarios | TASK-013, TASK-069, TASK-070 |
| M-41 | M- | CONCURRENCY_IDEMPOTENCY · 18. Mandatory concurrency and failure scenarios | TASK-013, TASK-023, TASK-069, TASK-070, TASK-082 |
| M-42 | M- | CONCURRENCY_IDEMPOTENCY · 18. Mandatory concurrency and failure scenarios | TASK-013, TASK-023, TASK-024, TASK-069, TASK-070, TASK-082 |
| HO-38 | HO- | CONCURRENCY_IDEMPOTENCY · 19. Handoff obligations | TASK-010, TASK-058, TASK-069 |
| HO-11 | HO- | CONCURRENCY_IDEMPOTENCY · 19. Handoff obligations | TASK-013, TASK-069 |
| HO-40 | HO- | CONCURRENCY_IDEMPOTENCY · 19. Handoff obligations | TASK-017 |
| HO-41 | HO- | CONCURRENCY_IDEMPOTENCY · 19. Handoff obligations | TASK-023, TASK-024 |
| HO-24 | HO- | CONCURRENCY_IDEMPOTENCY · 19. Handoff obligations | TASK-072, TASK-075 |
| HO-14 | HO- | CONCURRENCY_IDEMPOTENCY · 19. Handoff obligations | TASK-013 |
| HO-20 | HO- | CONCURRENCY_IDEMPOTENCY · 19. Handoff obligations | TASK-013, TASK-017, TASK-022, TASK-024, TASK-069 |
| HO-21 | HO- | CONCURRENCY_IDEMPOTENCY · 19. Handoff obligations | TASK-072, TASK-074, TASK-075, TASK-076 |
| HO-13 | HO- | CONCURRENCY_IDEMPOTENCY · 19. Handoff obligations | TASK-013, TASK-068, TASK-069 |
| HO-25 | HO- | CONCURRENCY_IDEMPOTENCY · 19. Handoff obligations | TASK-072, TASK-074, TASK-075, TASK-076, TASK-079, TASK-080 |
| HO-02 | HO- | CONCURRENCY_IDEMPOTENCY · 19. Handoff obligations | TASK-013, TASK-016, TASK-017 |
| HO-31 | HO- | CONCURRENCY_IDEMPOTENCY · 19. Handoff obligations | TASK-072, TASK-077, TASK-078 |
| HO-27 | HO- | CONCURRENCY_IDEMPOTENCY · 19. Handoff obligations | TASK-072 |
| HO-35 | HO- | CONCURRENCY_IDEMPOTENCY · 19. Handoff obligations | TASK-069, TASK-070, TASK-086 |
| HO-01 | HO- | CONCURRENCY_IDEMPOTENCY · 19. Handoff obligations | TASK-013 |
| HO-07 | HO- | CONCURRENCY_IDEMPOTENCY · 19. Handoff obligations | TASK-013, TASK-015, TASK-016 |
| HO-32 | HO- | CONCURRENCY_IDEMPOTENCY · 19. Handoff obligations | TASK-021, TASK-057, TASK-077, TASK-087 |
| HO-39 | HO- | CONCURRENCY_IDEMPOTENCY · 19. Handoff obligations | TASK-021 |
| HO-22 | HO- | CONCURRENCY_IDEMPOTENCY · 19. Handoff obligations | TASK-072 |
| HO-29 | HO- | CONCURRENCY_IDEMPOTENCY · 19. Handoff obligations | TASK-072, TASK-074, TASK-075, TASK-076 |
| HO-26 | HO- | CONCURRENCY_IDEMPOTENCY · 19. Handoff obligations | TASK-072, TASK-074, TASK-075, TASK-076, TASK-079 |
| HO-12 | HO- | CONCURRENCY_IDEMPOTENCY · 19. Handoff obligations | TASK-013, TASK-043, TASK-051, TASK-069 |
| HO-03 | HO- | CONCURRENCY_IDEMPOTENCY · 19. Handoff obligations | TASK-015, TASK-016 |
| HO-04 | HO- | CONCURRENCY_IDEMPOTENCY · 19. Handoff obligations | TASK-013, TASK-015 |
| HO-05 | HO- | CONCURRENCY_IDEMPOTENCY · 19. Handoff obligations | TASK-013, TASK-015 |
| HO-06 | HO- | CONCURRENCY_IDEMPOTENCY · 19. Handoff obligations | TASK-016, TASK-022 |
| HO-08 | HO- | CONCURRENCY_IDEMPOTENCY · 19. Handoff obligations | TASK-013, TASK-015 |
| HO-09 | HO- | CONCURRENCY_IDEMPOTENCY · 19. Handoff obligations | TASK-060 |
| HO-10 | HO- | CONCURRENCY_IDEMPOTENCY · 19. Handoff obligations | TASK-015, TASK-016, TASK-039 |
| HO-15 | HO- | CONCURRENCY_IDEMPOTENCY · 19. Handoff obligations | TASK-013 |
| HO-16 | HO- | CONCURRENCY_IDEMPOTENCY · 19. Handoff obligations | TASK-068, TASK-069 |
| HO-17 | HO- | CONCURRENCY_IDEMPOTENCY · 19. Handoff obligations | TASK-068, TASK-069 |
| HO-18 | HO- | CONCURRENCY_IDEMPOTENCY · 19. Handoff obligations | TASK-057, TASK-060 |
| HO-19 | HO- | CONCURRENCY_IDEMPOTENCY · 19. Handoff obligations | TASK-021, TASK-057, TASK-069 |
| HO-23 | HO- | CONCURRENCY_IDEMPOTENCY · 19. Handoff obligations | TASK-072, TASK-074, TASK-075, TASK-076 |
| HO-28 | HO- | CONCURRENCY_IDEMPOTENCY · 19. Handoff obligations | TASK-072, TASK-074, TASK-075, TASK-076, TASK-084 |
| HO-30 | HO- | CONCURRENCY_IDEMPOTENCY · 19. Handoff obligations | TASK-068, TASK-072, TASK-074, TASK-075, TASK-076 |
| HO-33 | HO- | CONCURRENCY_IDEMPOTENCY · 19. Handoff obligations | TASK-057, TASK-077, TASK-087 |
| HO-34 | HO- | CONCURRENCY_IDEMPOTENCY · 19. Handoff obligations | TASK-072, TASK-077 |
| HO-36 | HO- | CONCURRENCY_IDEMPOTENCY · 19. Handoff obligations | TASK-077, TASK-087 |
| HO-37 | HO- | CONCURRENCY_IDEMPOTENCY · 19. Handoff obligations | TASK-001, TASK-015 |
| AS-01 | AS- | PERFORMANCE · 1. Assumptions and workload | TASK-068, TASK-079 |
| AS-02 | AS- | PERFORMANCE · 1. Assumptions and workload | TASK-068, TASK-079 |
| AS-03 | AS- | PERFORMANCE · 1. Assumptions and workload | TASK-068, TASK-079 |
| AS-04 | AS- | PERFORMANCE · 1. Assumptions and workload | TASK-068, TASK-079 |
| AS-05 | AS- | PERFORMANCE · 1. Assumptions and workload | TASK-068, TASK-079 |
| AS-06 | AS- | PERFORMANCE · 1. Assumptions and workload | TASK-068, TASK-079 |
| AS-07 | AS- | PERFORMANCE · 1. Assumptions and workload | TASK-068, TASK-079 |
| AS-08 | AS- | PERFORMANCE · 1. Assumptions and workload | TASK-068, TASK-079 |
| D-PF-01 | D-PF- | PERFORMANCE · 2. Design decisions | TASK-068 |
| D-PF-02 | D-PF- | PERFORMANCE · 2. Design decisions | TASK-068 |
| D-PF-03 | D-PF- | PERFORMANCE · 2. Design decisions | TASK-068 |
| D-PF-04 | D-PF- | PERFORMANCE · 2. Design decisions | TASK-068 |
| D-PF-05 | D-PF- | PERFORMANCE · 2. Design decisions | TASK-068 |
| D-PF-06 | D-PF- | PERFORMANCE · 2. Design decisions | TASK-068 |
| D-PF-07 | D-PF- | PERFORMANCE · 2. Design decisions | TASK-068 |
| D-PF-08 | D-PF- | PERFORMANCE · 2. Design decisions | TASK-068 |
| PF-43 | PF- | PERFORMANCE · 7. Dashboards, reports and exports | TASK-058, TASK-060 |
| PF-01 | PF- | PERFORMANCE · 3. N+1 prevention and loading rules | TASK-002, TASK-068 |
| PF-02 | PF- | PERFORMANCE · 3. N+1 prevention and loading rules | TASK-002, TASK-068 |
| PF-09 | PF- | PERFORMANCE · 3. N+1 prevention and loading rules | TASK-002, TASK-068 |
| PF-03 | PF- | PERFORMANCE · 3. N+1 prevention and loading rules | TASK-002, TASK-068 |
| PF-04 | PF- | PERFORMANCE · 3. N+1 prevention and loading rules | TASK-002, TASK-068 |
| PF-05 | PF- | PERFORMANCE · 3. N+1 prevention and loading rules | TASK-002, TASK-068 |
| PF-06 | PF- | PERFORMANCE · 3. N+1 prevention and loading rules | TASK-002, TASK-068 |
| PF-07 | PF- | PERFORMANCE · 3. N+1 prevention and loading rules | TASK-002, TASK-068 |
| PF-08 | PF- | PERFORMANCE · 3. N+1 prevention and loading rules | TASK-002, TASK-068 |
| PF-10 | PF- | PERFORMANCE · 3. N+1 prevention and loading rules | TASK-002, TASK-068 |
| PF-11 | PF- | PERFORMANCE · 3. N+1 prevention and loading rules | TASK-002, TASK-068 |
| PF-12 | PF- | PERFORMANCE · 3. N+1 prevention and loading rules | TASK-002, TASK-068 |
| PF-13 | PF- | PERFORMANCE · 4. Pagination | TASK-015 |
| PF-14 | PF- | PERFORMANCE · 4. Pagination | TASK-015 |
| PF-42 | PF- | PERFORMANCE · 4. Pagination | TASK-015 |
| PF-15 | PF- | PERFORMANCE · 4. Pagination | TASK-015 |
| PF-24 | PF- | PERFORMANCE · 7. Dashboards, reports and exports | TASK-058, TASK-060 |
| PF-25 | PF- | PERFORMANCE · 7. Dashboards, reports and exports | TASK-058, TASK-060 |
| PF-16 | PF- | PERFORMANCE · 4. Pagination | TASK-015 |
| PF-17 | PF- | PERFORMANCE · 4. Pagination | TASK-015 |
| PF-18 | PF- | PERFORMANCE · 4. Pagination | TASK-015 |
| PF-41 | PF- | PERFORMANCE · 4. Pagination | TASK-015 |
| PF-19 | PF- | PERFORMANCE · 5. Search | TASK-016, TASK-059 |
| PF-22 | PF- | PERFORMANCE · 5. Search | TASK-016, TASK-059 |
| PF-20 | PF- | PERFORMANCE · 5. Search | TASK-016, TASK-059 |
| PF-23 | PF- | PERFORMANCE · 5. Search | TASK-016, TASK-059 |
| PF-21 | PF- | PERFORMANCE · 5. Search | TASK-016, TASK-059 |
| PF-26 | PF- | PERFORMANCE · 7. Dashboards, reports and exports | TASK-058, TASK-060 |
| PF-27 | PF- | PERFORMANCE · 7. Dashboards, reports and exports | TASK-058, TASK-060 |
| PF-28 | PF- | PERFORMANCE · 7. Dashboards, reports and exports | TASK-058, TASK-060 |
| PF-29 | PF- | PERFORMANCE · 7. Dashboards, reports and exports | TASK-058, TASK-060 |
| PF-30 | PF- | PERFORMANCE · 7. Dashboards, reports and exports | TASK-058, TASK-060 |
| PF-31 | PF- | PERFORMANCE · 8. Images and Inertia props | TASK-012, TASK-022 |
| PF-32 | PF- | PERFORMANCE · 8. Images and Inertia props | TASK-012, TASK-022 |
| PF-33 | PF- | PERFORMANCE · 8. Images and Inertia props | TASK-012, TASK-022 |
| PF-34 | PF- | PERFORMANCE · 8. Images and Inertia props | TASK-012, TASK-022 |
| PF-35 | PF- | PERFORMANCE · 8. Images and Inertia props | TASK-012, TASK-022 |
| PF-36 | PF- | PERFORMANCE · 8. Images and Inertia props | TASK-012, TASK-022 |
| PF-37 | PF- | PERFORMANCE · 10. Session and authentication load | TASK-017 |
| PF-38 | PF- | PERFORMANCE · 10. Session and authentication load | TASK-017 |
| PF-39 | PF- | PERFORMANCE · 10. Session and authentication load | TASK-017 |
| PF-40 | PF- | PERFORMANCE · 10. Session and authentication load | TASK-017 |
| QB-01 | QB- | PERFORMANCE · 6. Query budgets | TASK-068 |
| QB-10 | QB- | PERFORMANCE · 6. Query budgets | TASK-068 |
| QB-16 | QB- | PERFORMANCE · 6. Query budgets | TASK-068 |
| QB-06 | QB- | PERFORMANCE · 6. Query budgets | TASK-039, TASK-068 |
| QB-03 | QB- | PERFORMANCE · 6. Query budgets | TASK-068 |
| QB-02 | QB- | PERFORMANCE · 6. Query budgets | TASK-068 |
| QB-04 | QB- | PERFORMANCE · 6. Query budgets | TASK-068 |
| QB-05 | QB- | PERFORMANCE · 6. Query budgets | TASK-068 |
| QB-07 | QB- | PERFORMANCE · 6. Query budgets | TASK-068 |
| QB-08 | QB- | PERFORMANCE · 6. Query budgets | TASK-068 |
| QB-09 | QB- | PERFORMANCE · 6. Query budgets | TASK-068 |
| QB-11 | QB- | PERFORMANCE · 6. Query budgets | TASK-068 |
| QB-12 | QB- | PERFORMANCE · 6. Query budgets | TASK-068 |
| QB-13 | QB- | PERFORMANCE · 6. Query budgets | TASK-068 |
| QB-14 | QB- | PERFORMANCE · 6. Query budgets | TASK-068 |
| QB-15 | QB- | PERFORMANCE · 6. Query budgets | TASK-068 |
| QB-17 | QB- | PERFORMANCE · 6. Query budgets | TASK-068 |
| CP-01 | CP- | PERFORMANCE · 9. Cache policy | TASK-013 |
| CP-02 | CP- | PERFORMANCE · 9. Cache policy | TASK-013 |
| CP-03 | CP- | PERFORMANCE · 9. Cache policy | TASK-013 |
| CP-04 | CP- | PERFORMANCE · 9. Cache policy | TASK-013 |
| CP-05 | CP- | PERFORMANCE · 9. Cache policy | TASK-013 |
| CP-06 | CP- | PERFORMANCE · 9. Cache policy | TASK-013 |
| CP-07 | CP- | PERFORMANCE · 9. Cache policy | TASK-013 |
| GT-01 | GT- | PERFORMANCE · 11. Growth thresholds | TASK-068, TASK-079 |
| GT-02 | GT- | PERFORMANCE · 11. Growth thresholds | TASK-068, TASK-079 |
| GT-03 | GT- | PERFORMANCE · 11. Growth thresholds | TASK-068, TASK-079 |
| GT-07 | GT- | PERFORMANCE · 11. Growth thresholds | TASK-068, TASK-079 |
| GT-04 | GT- | PERFORMANCE · 11. Growth thresholds | TASK-068, TASK-079 |
| GT-05 | GT- | PERFORMANCE · 11. Growth thresholds | TASK-068, TASK-079 |
| GT-06 | GT- | PERFORMANCE · 11. Growth thresholds | TASK-068, TASK-079 |
| IX-14 | IX- | PERFORMANCE · 12. Index register and verification procedure | TASK-005, TASK-006, TASK-007, TASK-008, TASK-009 |
| IX-18 | IX- | PERFORMANCE · 12. Index register and verification procedure | TASK-005 |
| IX-19 | IX- | PERFORMANCE · 12. Index register and verification procedure | TASK-005 |
| IX-13 | IX- | PERFORMANCE · 12. Index register and verification procedure | TASK-005, TASK-058 |
| IX-11 | IX- | PERFORMANCE · 12. Index register and verification procedure | TASK-006, TASK-007, TASK-008, TASK-009 |
| IX-17 | IX- | PERFORMANCE · 12. Index register and verification procedure | TASK-006, TASK-007 |
| IX-07 | IX- | PERFORMANCE · 12. Index register and verification procedure | TASK-006 |
| IX-01 | IX- | PERFORMANCE · 12. Index register and verification procedure | TASK-009 |
| IX-02 | IX- | PERFORMANCE · 12. Index register and verification procedure | TASK-006 |
| IX-03 | IX- | PERFORMANCE · 12. Index register and verification procedure | TASK-005 |
| IX-04 | IX- | PERFORMANCE · 12. Index register and verification procedure | TASK-005 |
| IX-05 | IX- | PERFORMANCE · 12. Index register and verification procedure | TASK-005 |
| IX-06 | IX- | PERFORMANCE · 12. Index register and verification procedure | TASK-009 |
| IX-08 | IX- | PERFORMANCE · 12. Index register and verification procedure | TASK-006, TASK-007, TASK-008, TASK-009 |
| IX-09 | IX- | PERFORMANCE · 12. Index register and verification procedure | TASK-005 |
| IX-10 | IX- | PERFORMANCE · 12. Index register and verification procedure | TASK-008 |
| IX-12 | IX- | PERFORMANCE · 12. Index register and verification procedure | TASK-008 |
| IX-15 | IX- | PERFORMANCE · 12. Index register and verification procedure | TASK-005, TASK-006 |
| IX-16 | IX- | PERFORMANCE · 12. Index register and verification procedure | TASK-007, TASK-008 |
| SZ-09 | SZ- | PERFORMANCE · 13. Resource inputs for P9 | TASK-079 |
| SZ-02 | SZ- | PERFORMANCE · 13. Resource inputs for P9 | TASK-079 |
| SZ-01 | SZ- | PERFORMANCE · 13. Resource inputs for P9 | TASK-079 |
| SZ-03 | SZ- | PERFORMANCE · 13. Resource inputs for P9 | TASK-079 |
| SZ-04 | SZ- | PERFORMANCE · 13. Resource inputs for P9 | TASK-079 |
| SZ-05 | SZ- | PERFORMANCE · 13. Resource inputs for P9 | TASK-079 |
| SZ-06 | SZ- | PERFORMANCE · 13. Resource inputs for P9 | TASK-079 |
| SZ-07 | SZ- | PERFORMANCE · 13. Resource inputs for P9 | TASK-079 |
| SZ-08 | SZ- | PERFORMANCE · 13. Resource inputs for P9 | TASK-079 |
| RB-02 | RB- | API_AND_INTEGRATIONS · 1. Route boundaries | TASK-011 |
| RB-03 | RB- | API_AND_INTEGRATIONS · 1. Route boundaries | TASK-001, TASK-017, TASK-019, TASK-023 |
| RB-10 | RB- | API_AND_INTEGRATIONS · 1. Route boundaries | TASK-001, TASK-017, TASK-023 |
| RB-01 | RB- | API_AND_INTEGRATIONS · 1. Route boundaries | TASK-011, TASK-016 |
| RB-04 | RB- | API_AND_INTEGRATIONS · 1. Route boundaries | TASK-011, TASK-016 |
| RB-05 | RB- | API_AND_INTEGRATIONS · 1. Route boundaries | TASK-011, TASK-016 |
| RB-06 | RB- | API_AND_INTEGRATIONS · 1. Route boundaries | TASK-011, TASK-016 |
| RB-07 | RB- | API_AND_INTEGRATIONS · 1. Route boundaries | TASK-017, TASK-024 |
| RB-08 | RB- | API_AND_INTEGRATIONS · 1. Route boundaries | TASK-017 |
| RB-09 | RB- | API_AND_INTEGRATIONS · 1. Route boundaries | TASK-011, TASK-016 |
| IC-01 | IC- | API_AND_INTEGRATIONS · 2. Contract for a future external integration | disposition FUTURE |
| IC-02 | IC- | API_AND_INTEGRATIONS · 2. Contract for a future external integration | disposition FUTURE |
| IC-03 | IC- | API_AND_INTEGRATIONS · 2. Contract for a future external integration | disposition FUTURE |
| IC-04 | IC- | API_AND_INTEGRATIONS · 2. Contract for a future external integration | disposition FUTURE |
| IC-05 | IC- | API_AND_INTEGRATIONS · 2. Contract for a future external integration | disposition FUTURE |
| IC-06 | IC- | API_AND_INTEGRATIONS · 2. Contract for a future external integration | disposition FUTURE |
| IC-07 | IC- | API_AND_INTEGRATIONS · 2. Contract for a future external integration | disposition FUTURE |
| IC-08 | IC- | API_AND_INTEGRATIONS · 2. Contract for a future external integration | disposition FUTURE |
| IC-09 | IC- | API_AND_INTEGRATIONS · 2. Contract for a future external integration | disposition FUTURE |
| IC-10 | IC- | API_AND_INTEGRATIONS · 2. Contract for a future external integration | disposition FUTURE |
| IC-11 | IC- | API_AND_INTEGRATIONS · 2. Contract for a future external integration | disposition FUTURE |
| IC-12 | IC- | API_AND_INTEGRATIONS · 2. Contract for a future external integration | disposition FUTURE |
| IC-13 | IC- | API_AND_INTEGRATIONS · 2. Contract for a future external integration | disposition FUTURE |
| IC-14 | IC- | API_AND_INTEGRATIONS · 2. Contract for a future external integration | TASK-032 |
| UXN-01 | UXN- | INFORMATION_ARCHITECTURE · 2. Derived navigation requirements | TASK-016 |
| UXN-02 | UXN- | INFORMATION_ARCHITECTURE · 2. Derived navigation requirements | TASK-016, TASK-032 |
| UXN-03 | UXN- | INFORMATION_ARCHITECTURE · 2. Derived navigation requirements | TASK-016 |
| UXN-04 | UXN- | INFORMATION_ARCHITECTURE · 2. Derived navigation requirements | TASK-016 |
| UXN-05 | UXN- | INFORMATION_ARCHITECTURE · 2. Derived navigation requirements | TASK-016 |
| UXN-06 | UXN- | INFORMATION_ARCHITECTURE · 2. Derived navigation requirements | TASK-016 |
| UXN-07 | UXN- | INFORMATION_ARCHITECTURE · 2. Derived navigation requirements | TASK-016 |
| UXN-08 | UXN- | INFORMATION_ARCHITECTURE · 2. Derived navigation requirements | TASK-016 |
| UXN-09 | UXN- | INFORMATION_ARCHITECTURE · 2. Derived navigation requirements | TASK-016 |
| UXN-10 | UXN- | INFORMATION_ARCHITECTURE · 2. Derived navigation requirements | TASK-011, TASK-016 |
| UXN-11 | UXN- | INFORMATION_ARCHITECTURE · 2. Derived navigation requirements | TASK-016 |
| UXN-12 | UXN- | INFORMATION_ARCHITECTURE · 2. Derived navigation requirements | TASK-016 |
| UXN-13 | UXN- | INFORMATION_ARCHITECTURE · 2. Derived navigation requirements | TASK-016 |
| UXN-14 | UXN- | INFORMATION_ARCHITECTURE · 2. Derived navigation requirements | TASK-016 |
| UXN-15 | UXN- | INFORMATION_ARCHITECTURE · 2. Derived navigation requirements | TASK-016 |
| UXN-16 | UXN- | INFORMATION_ARCHITECTURE · 2. Derived navigation requirements | TASK-016 |
| UXN-17 | UXN- | INFORMATION_ARCHITECTURE · 2. Derived navigation requirements | TASK-016 |
| UXN-18 | UXN- | INFORMATION_ARCHITECTURE · 2. Derived navigation requirements | TASK-016 |
| UXN-19 | UXN- | INFORMATION_ARCHITECTURE · 2. Derived navigation requirements | TASK-016 |
| UXN-20 | UXN- | INFORMATION_ARCHITECTURE · 2. Derived navigation requirements | TASK-016 |
| UXN-21 | UXN- | INFORMATION_ARCHITECTURE · 2. Derived navigation requirements | TASK-016 |
| UXN-22 | UXN- | INFORMATION_ARCHITECTURE · 2. Derived navigation requirements | TASK-016 |
| UXN-23 | UXN- | INFORMATION_ARCHITECTURE · 2. Derived navigation requirements | TASK-016, TASK-026, TASK-027 |
| UXN-24 | UXN- | INFORMATION_ARCHITECTURE · 2. Derived navigation requirements | TASK-016 |
| UXN-25 | UXN- | INFORMATION_ARCHITECTURE · 2. Derived navigation requirements | TASK-016 |
| UXN-26 | UXN- | INFORMATION_ARCHITECTURE · 2. Derived navigation requirements | TASK-016 |
| D-IA-09 | D-IA- | INFORMATION_ARCHITECTURE · 4. Design decisions | TASK-032 |
| D-IA-04 | D-IA- | INFORMATION_ARCHITECTURE · 4. Design decisions | TASK-016 |
| D-IA-01 | D-IA- | INFORMATION_ARCHITECTURE · 4. Design decisions | TASK-016 |
| D-IA-17 | D-IA- | INFORMATION_ARCHITECTURE · 4. Design decisions | TASK-040 |
| D-IA-02 | D-IA- | INFORMATION_ARCHITECTURE · 4. Design decisions | TASK-016 |
| D-IA-03 | D-IA- | INFORMATION_ARCHITECTURE · 4. Design decisions | TASK-016 |
| D-IA-05 | D-IA- | INFORMATION_ARCHITECTURE · 4. Design decisions | TASK-016 |
| D-IA-06 | D-IA- | INFORMATION_ARCHITECTURE · 4. Design decisions | TASK-016 |
| D-IA-07 | D-IA- | INFORMATION_ARCHITECTURE · 4. Design decisions | TASK-058 |
| D-IA-08 | D-IA- | INFORMATION_ARCHITECTURE · 4. Design decisions | TASK-058 |
| D-IA-10 | D-IA- | INFORMATION_ARCHITECTURE · 4. Design decisions | TASK-035 |
| D-IA-11 | D-IA- | INFORMATION_ARCHITECTURE · 4. Design decisions | TASK-058 |
| D-IA-12 | D-IA- | INFORMATION_ARCHITECTURE · 4. Design decisions | TASK-060 |
| D-IA-13 | D-IA- | INFORMATION_ARCHITECTURE · 4. Design decisions | TASK-021 |
| D-IA-14 | D-IA- | INFORMATION_ARCHITECTURE · 4. Design decisions | TASK-026, TASK-027 |
| D-IA-15 | D-IA- | INFORMATION_ARCHITECTURE · 4. Design decisions | TASK-050, TASK-058, TASK-060 |
| D-IA-16 | D-IA- | INFORMATION_ARCHITECTURE · 4. Design decisions | TASK-041, TASK-045 |
| D-IA-18 | D-IA- | INFORMATION_ARCHITECTURE · 4. Design decisions | TASK-016 |
| D-IA-19 | D-IA- | INFORMATION_ARCHITECTURE · 4. Design decisions | TASK-016 |
| D-IA-20 | D-IA- | INFORMATION_ARCHITECTURE · 4. Design decisions | TASK-041 |
| D-IA-21 | D-IA- | INFORMATION_ARCHITECTURE · 4. Design decisions | TASK-016 |
| D-IA-22 | D-IA- | INFORMATION_ARCHITECTURE · 4. Design decisions | TASK-016 |
| D-IA-23 | D-IA- | INFORMATION_ARCHITECTURE · 4. Design decisions | TASK-016 |
| GL-001 | GL- | INFORMATION_ARCHITECTURE · 3.1 Areas and navigation | TASK-014 |
| GL-002 | GL- | INFORMATION_ARCHITECTURE · 3.1 Areas and navigation | TASK-014 |
| GL-003 | GL- | INFORMATION_ARCHITECTURE · 3.1 Areas and navigation | TASK-014 |
| GL-004 | GL- | INFORMATION_ARCHITECTURE · 3.1 Areas and navigation | TASK-014 |
| GL-005 | GL- | INFORMATION_ARCHITECTURE · 3.1 Areas and navigation | TASK-014 |
| GL-006 | GL- | INFORMATION_ARCHITECTURE · 3.1 Areas and navigation | TASK-014 |
| GL-007 | GL- | INFORMATION_ARCHITECTURE · 3.1 Areas and navigation | TASK-014 |
| GL-008 | GL- | INFORMATION_ARCHITECTURE · 3.1 Areas and navigation | TASK-014 |
| GL-009 | GL- | INFORMATION_ARCHITECTURE · 3.1 Areas and navigation | TASK-014 |
| GL-010 | GL- | INFORMATION_ARCHITECTURE · 3.1 Areas and navigation | TASK-014 |
| GL-011 | GL- | INFORMATION_ARCHITECTURE · 3.1 Areas and navigation | TASK-014 |
| GL-012 | GL- | INFORMATION_ARCHITECTURE · 3.1 Areas and navigation | TASK-014 |
| GL-013 | GL- | INFORMATION_ARCHITECTURE · 3.1 Areas and navigation | TASK-014 |
| GL-014 | GL- | INFORMATION_ARCHITECTURE · 3.1 Areas and navigation | TASK-014 |
| GL-015 | GL- | INFORMATION_ARCHITECTURE · 3.1 Areas and navigation | TASK-014 |
| GL-016 | GL- | INFORMATION_ARCHITECTURE · 3.1 Areas and navigation | TASK-014 |
| GL-017 | GL- | INFORMATION_ARCHITECTURE · 3.1 Areas and navigation | TASK-014 |
| GL-018 | GL- | INFORMATION_ARCHITECTURE · 3.1 Areas and navigation | TASK-014 |
| GL-019 | GL- | INFORMATION_ARCHITECTURE · 3.1 Areas and navigation | TASK-014 |
| GL-020 | GL- | INFORMATION_ARCHITECTURE · 3.1 Areas and navigation | TASK-014 |
| GL-021 | GL- | INFORMATION_ARCHITECTURE · 3.1 Areas and navigation | TASK-014 |
| GL-022 | GL- | INFORMATION_ARCHITECTURE · 3.1 Areas and navigation | TASK-014 |
| GL-023 | GL- | INFORMATION_ARCHITECTURE · 3.1 Areas and navigation | TASK-014 |
| GL-024 | GL- | INFORMATION_ARCHITECTURE · 3.1 Areas and navigation | TASK-014 |
| GL-025 | GL- | INFORMATION_ARCHITECTURE · 3.1 Areas and navigation | TASK-014 |
| GL-026 | GL- | INFORMATION_ARCHITECTURE · 3.1 Areas and navigation | TASK-014 |
| GL-027 | GL- | INFORMATION_ARCHITECTURE · 3.1 Areas and navigation | TASK-014 |
| GL-028 | GL- | INFORMATION_ARCHITECTURE · 3.1 Areas and navigation | TASK-014 |
| GL-029 | GL- | INFORMATION_ARCHITECTURE · 3.1 Areas and navigation | TASK-014 |
| GL-030 | GL- | INFORMATION_ARCHITECTURE · 3.1 Areas and navigation | TASK-014 |
| GL-031 | GL- | INFORMATION_ARCHITECTURE · 3.2 People, access and companies | TASK-014 |
| GL-032 | GL- | INFORMATION_ARCHITECTURE · 3.2 People, access and companies | TASK-014 |
| GL-033 | GL- | INFORMATION_ARCHITECTURE · 3.2 People, access and companies | TASK-014 |
| GL-034 | GL- | INFORMATION_ARCHITECTURE · 3.2 People, access and companies | TASK-014 |
| GL-035 | GL- | INFORMATION_ARCHITECTURE · 3.2 People, access and companies | TASK-014 |
| GL-036 | GL- | INFORMATION_ARCHITECTURE · 3.2 People, access and companies | TASK-014 |
| GL-037 | GL- | INFORMATION_ARCHITECTURE · 3.2 People, access and companies | TASK-014 |
| GL-038 | GL- | INFORMATION_ARCHITECTURE · 3.2 People, access and companies | TASK-014 |
| GL-039 | GL- | INFORMATION_ARCHITECTURE · 3.2 People, access and companies | TASK-014 |
| GL-040 | GL- | INFORMATION_ARCHITECTURE · 3.2 People, access and companies | TASK-014 |
| GL-041 | GL- | INFORMATION_ARCHITECTURE · 3.2 People, access and companies | TASK-014 |
| GL-042 | GL- | INFORMATION_ARCHITECTURE · 3.2 People, access and companies | TASK-014 |
| GL-043 | GL- | INFORMATION_ARCHITECTURE · 3.2 People, access and companies | TASK-014 |
| GL-044 | GL- | INFORMATION_ARCHITECTURE · 3.2 People, access and companies | TASK-014 |
| GL-045 | GL- | INFORMATION_ARCHITECTURE · 3.2 People, access and companies | TASK-014 |
| GL-046 | GL- | INFORMATION_ARCHITECTURE · 3.2 People, access and companies | TASK-014 |
| GL-047 | GL- | INFORMATION_ARCHITECTURE · 3.2 People, access and companies | TASK-014 |
| GL-048 | GL- | INFORMATION_ARCHITECTURE · 3.2 People, access and companies | TASK-014 |
| GL-049 | GL- | INFORMATION_ARCHITECTURE · 3.2 People, access and companies | TASK-014 |
| GL-050 | GL- | INFORMATION_ARCHITECTURE · 3.2 People, access and companies | TASK-014 |
| GL-051 | GL- | INFORMATION_ARCHITECTURE · 3.2 People, access and companies | TASK-014 |
| GL-052 | GL- | INFORMATION_ARCHITECTURE · 3.2 People, access and companies | TASK-014 |
| GL-053 | GL- | INFORMATION_ARCHITECTURE · 3.2 People, access and companies | TASK-014 |
| GL-054 | GL- | INFORMATION_ARCHITECTURE · 3.2 People, access and companies | TASK-014 |
| GL-055 | GL- | INFORMATION_ARCHITECTURE · 3.2 People, access and companies | TASK-014 |
| GL-056 | GL- | INFORMATION_ARCHITECTURE · 3.2 People, access and companies | TASK-014 |
| GL-057 | GL- | INFORMATION_ARCHITECTURE · 3.2 People, access and companies | TASK-014 |
| GL-058 | GL- | INFORMATION_ARCHITECTURE · 3.2 People, access and companies | TASK-014 |
| GL-059 | GL- | INFORMATION_ARCHITECTURE · 3.3 Project, commercial and purchasing | TASK-014 |
| GL-060 | GL- | INFORMATION_ARCHITECTURE · 3.3 Project, commercial and purchasing | TASK-014 |
| GL-061 | GL- | INFORMATION_ARCHITECTURE · 3.3 Project, commercial and purchasing | TASK-014 |
| GL-062 | GL- | INFORMATION_ARCHITECTURE · 3.3 Project, commercial and purchasing | TASK-014 |
| GL-063 | GL- | INFORMATION_ARCHITECTURE · 3.3 Project, commercial and purchasing | TASK-014 |
| GL-064 | GL- | INFORMATION_ARCHITECTURE · 3.3 Project, commercial and purchasing | TASK-014 |
| GL-065 | GL- | INFORMATION_ARCHITECTURE · 3.3 Project, commercial and purchasing | TASK-014 |
| GL-066 | GL- | INFORMATION_ARCHITECTURE · 3.3 Project, commercial and purchasing | TASK-014 |
| GL-067 | GL- | INFORMATION_ARCHITECTURE · 3.3 Project, commercial and purchasing | TASK-014 |
| GL-068 | GL- | INFORMATION_ARCHITECTURE · 3.3 Project, commercial and purchasing | TASK-014 |
| GL-069 | GL- | INFORMATION_ARCHITECTURE · 3.3 Project, commercial and purchasing | TASK-014 |
| GL-070 | GL- | INFORMATION_ARCHITECTURE · 3.3 Project, commercial and purchasing | TASK-014 |
| GL-071 | GL- | INFORMATION_ARCHITECTURE · 3.3 Project, commercial and purchasing | TASK-014 |
| GL-072 | GL- | INFORMATION_ARCHITECTURE · 3.3 Project, commercial and purchasing | TASK-014 |
| GL-073 | GL- | INFORMATION_ARCHITECTURE · 3.3 Project, commercial and purchasing | TASK-014 |
| GL-074 | GL- | INFORMATION_ARCHITECTURE · 3.3 Project, commercial and purchasing | TASK-014 |
| GL-075 | GL- | INFORMATION_ARCHITECTURE · 3.3 Project, commercial and purchasing | TASK-014 |
| GL-076 | GL- | INFORMATION_ARCHITECTURE · 3.4 Warehouse | TASK-014 |
| GL-077 | GL- | INFORMATION_ARCHITECTURE · 3.4 Warehouse | TASK-014 |
| GL-078 | GL- | INFORMATION_ARCHITECTURE · 3.4 Warehouse | TASK-014 |
| GL-079 | GL- | INFORMATION_ARCHITECTURE · 3.4 Warehouse | TASK-014 |
| GL-080 | GL- | INFORMATION_ARCHITECTURE · 3.4 Warehouse | TASK-014 |
| GL-081 | GL- | INFORMATION_ARCHITECTURE · 3.4 Warehouse | TASK-014 |
| GL-082 | GL- | INFORMATION_ARCHITECTURE · 3.4 Warehouse | TASK-014 |
| GL-083 | GL- | INFORMATION_ARCHITECTURE · 3.4 Warehouse | TASK-014 |
| GL-084 | GL- | INFORMATION_ARCHITECTURE · 3.4 Warehouse | TASK-014 |
| GL-085 | GL- | INFORMATION_ARCHITECTURE · 3.4 Warehouse | TASK-014 |
| GL-086 | GL- | INFORMATION_ARCHITECTURE · 3.4 Warehouse | TASK-014 |
| GL-087 | GL- | INFORMATION_ARCHITECTURE · 3.4 Warehouse | TASK-014 |
| GL-088 | GL- | INFORMATION_ARCHITECTURE · 3.4 Warehouse | TASK-014 |
| GL-089 | GL- | INFORMATION_ARCHITECTURE · 3.4 Warehouse | TASK-014 |
| GL-090 | GL- | INFORMATION_ARCHITECTURE · 3.4 Warehouse | TASK-014 |
| GL-091 | GL- | INFORMATION_ARCHITECTURE · 3.4 Warehouse | TASK-014 |
| GL-092 | GL- | INFORMATION_ARCHITECTURE · 3.4 Warehouse | TASK-014 |
| GL-093 | GL- | INFORMATION_ARCHITECTURE · 3.4 Warehouse | TASK-014 |
| GL-094 | GL- | INFORMATION_ARCHITECTURE · 3.4 Warehouse | TASK-014 |
| GL-095 | GL- | INFORMATION_ARCHITECTURE · 3.4 Warehouse | TASK-014 |
| GL-096 | GL- | INFORMATION_ARCHITECTURE · 3.4 Warehouse | TASK-014 |
| GL-097 | GL- | INFORMATION_ARCHITECTURE · 3.4 Warehouse | TASK-014 |
| GL-098 | GL- | INFORMATION_ARCHITECTURE · 3.4 Warehouse | TASK-014 |
| GL-099 | GL- | INFORMATION_ARCHITECTURE · 3.4 Warehouse | TASK-014 |
| GL-100 | GL- | INFORMATION_ARCHITECTURE · 3.4 Warehouse | TASK-014 |
| GL-101 | GL- | INFORMATION_ARCHITECTURE · 3.4 Warehouse | TASK-014 |
| GL-102 | GL- | INFORMATION_ARCHITECTURE · 3.4 Warehouse | TASK-014 |
| GL-103 | GL- | INFORMATION_ARCHITECTURE · 3.5 Documents and administration | TASK-014 |
| GL-104 | GL- | INFORMATION_ARCHITECTURE · 3.5 Documents and administration | TASK-014 |
| GL-105 | GL- | INFORMATION_ARCHITECTURE · 3.5 Documents and administration | TASK-014 |
| GL-106 | GL- | INFORMATION_ARCHITECTURE · 3.5 Documents and administration | TASK-014 |
| GL-107 | GL- | INFORMATION_ARCHITECTURE · 3.5 Documents and administration | TASK-014 |
| GL-108 | GL- | INFORMATION_ARCHITECTURE · 3.5 Documents and administration | TASK-014 |
| GL-109 | GL- | INFORMATION_ARCHITECTURE · 3.5 Documents and administration | TASK-014 |
| GL-110 | GL- | INFORMATION_ARCHITECTURE · 3.5 Documents and administration | TASK-014 |
| GL-111 | GL- | INFORMATION_ARCHITECTURE · 3.5 Documents and administration | TASK-014 |
| GL-112 | GL- | INFORMATION_ARCHITECTURE · 3.5 Documents and administration | TASK-014 |
| GL-113 | GL- | INFORMATION_ARCHITECTURE · 3.5 Documents and administration | TASK-014 |
| GL-114 | GL- | INFORMATION_ARCHITECTURE · 3.5 Documents and administration | TASK-014 |
| GL-115 | GL- | INFORMATION_ARCHITECTURE · 3.5 Documents and administration | TASK-014 |
| GL-116 | GL- | INFORMATION_ARCHITECTURE · 3.5 Documents and administration | TASK-014 |
| GL-117 | GL- | INFORMATION_ARCHITECTURE · 3.5 Documents and administration | TASK-014 |
| GL-118 | GL- | INFORMATION_ARCHITECTURE · 3.5 Documents and administration | TASK-014 |
| GL-119 | GL- | INFORMATION_ARCHITECTURE · 3.5 Documents and administration | TASK-014 |
| GL-120 | GL- | INFORMATION_ARCHITECTURE · 3.5 Documents and administration | TASK-014 |
| GL-121 | GL- | INFORMATION_ARCHITECTURE · 3.5 Documents and administration | TASK-014 |
| GL-122 | GL- | INFORMATION_ARCHITECTURE · 3.5 Documents and administration | TASK-014 |
| GL-123 | GL- | INFORMATION_ARCHITECTURE · 3.5 Documents and administration | TASK-014 |
| GL-124 | GL- | INFORMATION_ARCHITECTURE · 3.5 Documents and administration | TASK-014 |
| GL-125 | GL- | INFORMATION_ARCHITECTURE · 3.5 Documents and administration | TASK-014 |
| GL-126 | GL- | INFORMATION_ARCHITECTURE · 3.5 Documents and administration | TASK-014 |
| GL-127 | GL- | INFORMATION_ARCHITECTURE · 3.5 Documents and administration | TASK-014 |
| GL-128 | GL- | INFORMATION_ARCHITECTURE · 3.5 Documents and administration | TASK-014 |
| GL-129 | GL- | INFORMATION_ARCHITECTURE · 3.5 Documents and administration | TASK-014 |
| GL-130 | GL- | INFORMATION_ARCHITECTURE · 3.6 Finance | TASK-014 |
| GL-131 | GL- | INFORMATION_ARCHITECTURE · 3.6 Finance | TASK-014 |
| GL-132 | GL- | INFORMATION_ARCHITECTURE · 3.6 Finance | TASK-014 |
| GL-133 | GL- | INFORMATION_ARCHITECTURE · 3.6 Finance | TASK-014 |
| GL-134 | GL- | INFORMATION_ARCHITECTURE · 3.6 Finance | TASK-014 |
| GL-135 | GL- | INFORMATION_ARCHITECTURE · 3.6 Finance | TASK-014 |
| GL-136 | GL- | INFORMATION_ARCHITECTURE · 3.6 Finance | TASK-014 |
| GL-137 | GL- | INFORMATION_ARCHITECTURE · 3.6 Finance | TASK-014 |
| GL-138 | GL- | INFORMATION_ARCHITECTURE · 3.6 Finance | TASK-014 |
| GL-139 | GL- | INFORMATION_ARCHITECTURE · 3.6 Finance | TASK-014 |
| GL-140 | GL- | INFORMATION_ARCHITECTURE · 3.6 Finance | TASK-014 |
| GL-141 | GL- | INFORMATION_ARCHITECTURE · 3.6 Finance | TASK-014 |
| GL-142 | GL- | INFORMATION_ARCHITECTURE · 3.6 Finance | TASK-014 |
| GL-143 | GL- | INFORMATION_ARCHITECTURE · 3.6 Finance | TASK-014 |
| GL-144 | GL- | INFORMATION_ARCHITECTURE · 3.6 Finance | TASK-014 |
| GL-145 | GL- | INFORMATION_ARCHITECTURE · 3.6 Finance | TASK-014 |
| GL-146 | GL- | INFORMATION_ARCHITECTURE · 3.6 Finance | TASK-014 |
| GL-147 | GL- | INFORMATION_ARCHITECTURE · 3.6 Finance | TASK-014 |
| GL-148 | GL- | INFORMATION_ARCHITECTURE · 3.6 Finance | TASK-014 |
| GL-149 | GL- | INFORMATION_ARCHITECTURE · 3.6 Finance | TASK-014 |
| GL-150 | GL- | INFORMATION_ARCHITECTURE · 3.6 Finance | TASK-014 |
| GL-151 | GL- | INFORMATION_ARCHITECTURE · 3.6 Finance | TASK-014 |
| GL-152 | GL- | INFORMATION_ARCHITECTURE · 3.6 Finance | TASK-014 |
| GL-153 | GL- | INFORMATION_ARCHITECTURE · 3.6 Finance | TASK-014 |
| GL-154 | GL- | INFORMATION_ARCHITECTURE · 3.6 Finance | TASK-014 |
| GL-155 | GL- | INFORMATION_ARCHITECTURE · 3.6 Finance | TASK-014 |
| GL-156 | GL- | INFORMATION_ARCHITECTURE · 3.6 Finance | TASK-014 |
| GL-157 | GL- | INFORMATION_ARCHITECTURE · 3.7 Commands, answers, dates and system | TASK-014 |
| GL-158 | GL- | INFORMATION_ARCHITECTURE · 3.7 Commands, answers, dates and system | TASK-014 |
| GL-159 | GL- | INFORMATION_ARCHITECTURE · 3.7 Commands, answers, dates and system | TASK-014 |
| GL-160 | GL- | INFORMATION_ARCHITECTURE · 3.7 Commands, answers, dates and system | TASK-014 |
| GL-161 | GL- | INFORMATION_ARCHITECTURE · 3.7 Commands, answers, dates and system | TASK-014 |
| GL-162 | GL- | INFORMATION_ARCHITECTURE · 3.7 Commands, answers, dates and system | TASK-014 |
| GL-163 | GL- | INFORMATION_ARCHITECTURE · 3.7 Commands, answers, dates and system | TASK-014 |
| GL-164 | GL- | INFORMATION_ARCHITECTURE · 3.7 Commands, answers, dates and system | TASK-014 |
| GL-165 | GL- | INFORMATION_ARCHITECTURE · 3.7 Commands, answers, dates and system | TASK-014 |
| GL-166 | GL- | INFORMATION_ARCHITECTURE · 3.7 Commands, answers, dates and system | TASK-014 |
| GL-167 | GL- | INFORMATION_ARCHITECTURE · 3.7 Commands, answers, dates and system | TASK-014 |
| GL-168 | GL- | INFORMATION_ARCHITECTURE · 3.7 Commands, answers, dates and system | TASK-014 |
| GL-169 | GL- | INFORMATION_ARCHITECTURE · 3.7 Commands, answers, dates and system | TASK-014 |
| GL-170 | GL- | INFORMATION_ARCHITECTURE · 3.7 Commands, answers, dates and system | TASK-014 |
| GL-171 | GL- | INFORMATION_ARCHITECTURE · 3.7 Commands, answers, dates and system | TASK-014 |
| GL-172 | GL- | INFORMATION_ARCHITECTURE · 3.7 Commands, answers, dates and system | TASK-014 |
| GL-173 | GL- | INFORMATION_ARCHITECTURE · 3.7 Commands, answers, dates and system | TASK-014 |
| GL-174 | GL- | INFORMATION_ARCHITECTURE · 3.7 Commands, answers, dates and system | TASK-014 |
| GL-175 | GL- | INFORMATION_ARCHITECTURE · 3.7 Commands, answers, dates and system | TASK-014 |
| GL-176 | GL- | INFORMATION_ARCHITECTURE · 3.7 Commands, answers, dates and system | TASK-014 |
| GL-177 | GL- | INFORMATION_ARCHITECTURE · 3.7 Commands, answers, dates and system | TASK-014 |
| GL-178 | GL- | INFORMATION_ARCHITECTURE · 3.7 Commands, answers, dates and system | TASK-014 |
| GL-179 | GL- | INFORMATION_ARCHITECTURE · 3.7 Commands, answers, dates and system | TASK-014 |
| GL-180 | GL- | INFORMATION_ARCHITECTURE · 3.7 Commands, answers, dates and system | TASK-014 |
| GL-181 | GL- | INFORMATION_ARCHITECTURE · 3.7 Commands, answers, dates and system | TASK-014 |
| GL-182 | GL- | INFORMATION_ARCHITECTURE · 3.7 Commands, answers, dates and system | TASK-014 |
| GL-183 | GL- | INFORMATION_ARCHITECTURE · 3.7 Commands, answers, dates and system | TASK-014 |
| GL-184 | GL- | INFORMATION_ARCHITECTURE · 3.7 Commands, answers, dates and system | TASK-014 |
| GL-185 | GL- | INFORMATION_ARCHITECTURE · 3.7 Commands, answers, dates and system | TASK-014 |
| GL-186 | GL- | INFORMATION_ARCHITECTURE · 3.7 Commands, answers, dates and system | TASK-014 |
| GL-187 | GL- | INFORMATION_ARCHITECTURE · 3.7 Commands, answers, dates and system | TASK-014 |
| GL-188 | GL- | INFORMATION_ARCHITECTURE · 3.7 Commands, answers, dates and system | TASK-014 |
| GL-189 | GL- | INFORMATION_ARCHITECTURE · 3.7 Commands, answers, dates and system | TASK-014 |
| GL-190 | GL- | INFORMATION_ARCHITECTURE · 3.7 Commands, answers, dates and system | TASK-014 |
| GL-191 | GL- | INFORMATION_ARCHITECTURE · 3.7 Commands, answers, dates and system | TASK-014 |
| GL-192 | GL- | INFORMATION_ARCHITECTURE · 3.8 Labels of the re-baselined structure (Stage 3) | TASK-014 |
| GL-193 | GL- | INFORMATION_ARCHITECTURE · 3.8 Labels of the re-baselined structure (Stage 3) | TASK-014 |
| GL-194 | GL- | INFORMATION_ARCHITECTURE · 3.8 Labels of the re-baselined structure (Stage 3) | TASK-014 |
| GL-195 | GL- | INFORMATION_ARCHITECTURE · 3.8 Labels of the re-baselined structure (Stage 3) | TASK-014 |
| GL-196 | GL- | INFORMATION_ARCHITECTURE · 3.8 Labels of the re-baselined structure (Stage 3) | TASK-014 |
| GL-197 | GL- | INFORMATION_ARCHITECTURE · 3.8 Labels of the re-baselined structure (Stage 3) | TASK-014 |
| GL-198 | GL- | INFORMATION_ARCHITECTURE · 3.8 Labels of the re-baselined structure (Stage 3) | TASK-014 |
| GL-199 | GL- | INFORMATION_ARCHITECTURE · 3.8 Labels of the re-baselined structure (Stage 3) | TASK-014 |
| GL-200 | GL- | INFORMATION_ARCHITECTURE · 3.8 Labels of the re-baselined structure (Stage 3) | TASK-014 |
| GL-201 | GL- | INFORMATION_ARCHITECTURE · 3.8 Labels of the re-baselined structure (Stage 3) | TASK-014 |
| GL-202 | GL- | INFORMATION_ARCHITECTURE · 3.8 Labels of the re-baselined structure (Stage 3) | TASK-014 |
| GL-203 | GL- | INFORMATION_ARCHITECTURE · 3.8 Labels of the re-baselined structure (Stage 3) | TASK-014 |
| GL-204 | GL- | INFORMATION_ARCHITECTURE · 3.8 Labels of the re-baselined structure (Stage 3) | TASK-014 |
| GL-205 | GL- | INFORMATION_ARCHITECTURE · 3.8 Labels of the re-baselined structure (Stage 3) | TASK-014 |
| GL-206 | GL- | INFORMATION_ARCHITECTURE · 3.8 Labels of the re-baselined structure (Stage 3) | TASK-014 |
| GL-207 | GL- | INFORMATION_ARCHITECTURE · 3.8 Labels of the re-baselined structure (Stage 3) | TASK-014 |
| GL-208 | GL- | INFORMATION_ARCHITECTURE · 3.8 Labels of the re-baselined structure (Stage 3) | TASK-014 |
| GL-209 | GL- | INFORMATION_ARCHITECTURE · 3.8 Labels of the re-baselined structure (Stage 3) | TASK-014 |
| GL-210 | GL- | INFORMATION_ARCHITECTURE · 3.8 Labels of the re-baselined structure (Stage 3) | TASK-014 |
| GL-211 | GL- | INFORMATION_ARCHITECTURE · 3.8 Labels of the re-baselined structure (Stage 3) | TASK-014 |
| GL-212 | GL- | INFORMATION_ARCHITECTURE · 3.8 Labels of the re-baselined structure (Stage 3) | TASK-014 |
| PG-014 | PG- | INFORMATION_ARCHITECTURE · 8.2 Projects and quotations | TASK-033 |
| PG-033 | PG- | INFORMATION_ARCHITECTURE · 8.4 Fulfilment, cases and documents | TASK-047 |
| PG-036 | PG- | INFORMATION_ARCHITECTURE · 8.4 Fulfilment, cases and documents | TASK-029 |
| PG-042 | PG- | INFORMATION_ARCHITECTURE · 8.5 Finance | TASK-049 |
| PG-043 | PG- | INFORMATION_ARCHITECTURE · 8.5 Finance | TASK-049 |
| PG-058 | PG- | INFORMATION_ARCHITECTURE · 8.6 Reports, master data, settings and Owner areas | TASK-057 |
| PG-061 | PG- | INFORMATION_ARCHITECTURE · 8.6 Reports, master data, settings and Owner areas | TASK-021 |
| PG-006 | PG- | INFORMATION_ARCHITECTURE · 8.1 System, account and Beranda | TASK-058 |
| PG-016 | PG- | INFORMATION_ARCHITECTURE · 8.3 Purchasing and warehouse | TASK-037 |
| PG-022 | PG- | INFORMATION_ARCHITECTURE · 8.3 Purchasing and warehouse | TASK-039 |
| PG-024 | PG- | INFORMATION_ARCHITECTURE · 8.3 Purchasing and warehouse | TASK-041 |
| PG-031 | PG- | INFORMATION_ARCHITECTURE · 8.4 Fulfilment, cases and documents | TASK-045 |
| PG-001 | PG- | INFORMATION_ARCHITECTURE · 8.1 System, account and Beranda | TASK-017 |
| PG-065 | PG- | INFORMATION_ARCHITECTURE · 8.1 System, account and Beranda | TASK-023 |
| PG-011 | PG- | INFORMATION_ARCHITECTURE · 8.2 Projects and quotations | TASK-032 |
| PG-054 | PG- | INFORMATION_ARCHITECTURE · 8.6 Reports, master data, settings and Owner areas | TASK-026 |
| PG-056 | PG- | INFORMATION_ARCHITECTURE · 8.6 Reports, master data, settings and Owner areas | TASK-027 |
| PG-007 | PG- | INFORMATION_ARCHITECTURE · 8.1 System, account and Beranda | TASK-058 |
| PG-008 | PG- | INFORMATION_ARCHITECTURE · 8.1 System, account and Beranda | TASK-058 |
| PG-039 | PG- | INFORMATION_ARCHITECTURE · 8.4 Fulfilment, cases and documents | TASK-035 |
| PG-051 | PG- | INFORMATION_ARCHITECTURE · 8.6 Reports, master data, settings and Owner areas | TASK-060 |
| PG-055 | PG- | INFORMATION_ARCHITECTURE · 8.6 Reports, master data, settings and Owner areas | TASK-026 |
| PG-044 | PG- | INFORMATION_ARCHITECTURE · 8.5 Finance | TASK-050 |
| PG-023 | PG- | INFORMATION_ARCHITECTURE · 8.3 Purchasing and warehouse | TASK-041 |
| PG-030 | PG- | INFORMATION_ARCHITECTURE · 8.4 Fulfilment, cases and documents | TASK-045 |
| PG-018 | PG- | INFORMATION_ARCHITECTURE · 8.3 Purchasing and warehouse | TASK-040 |
| PG-019 | PG- | INFORMATION_ARCHITECTURE · 8.3 Purchasing and warehouse | TASK-040 |
| PG-015 | PG- | INFORMATION_ARCHITECTURE · 8.3 Purchasing and warehouse | TASK-037 |
| PG-017 | PG- | INFORMATION_ARCHITECTURE · 8.3 Purchasing and warehouse | TASK-037 |
| PG-041 | PG- | INFORMATION_ARCHITECTURE · 8.5 Finance | TASK-049 |
| PG-050 | PG- | INFORMATION_ARCHITECTURE · 8.5 Finance | TASK-051 |
| PG-035 | PG- | INFORMATION_ARCHITECTURE · 8.4 Fulfilment, cases and documents | TASK-029 |
| PG-040 | PG- | INFORMATION_ARCHITECTURE · 8.4 Fulfilment, cases and documents | TASK-031 |
| PG-053 | PG- | INFORMATION_ARCHITECTURE · 8.6 Reports, master data, settings and Owner areas | TASK-060 |
| PG-063 | PG- | INFORMATION_ARCHITECTURE · 8.6 Reports, master data, settings and Owner areas | TASK-061 |
| PG-064 | PG- | INFORMATION_ARCHITECTURE · 8.6 Reports, master data, settings and Owner areas | TASK-061 |
| PG-059 | PG- | INFORMATION_ARCHITECTURE · 8.6 Reports, master data, settings and Owner areas | TASK-025 |
| PG-062 | PG- | INFORMATION_ARCHITECTURE · 8.6 Reports, master data, settings and Owner areas | TASK-025 |
| PG-005 | PG- | INFORMATION_ARCHITECTURE · 8.1 System, account and Beranda | TASK-016 |
| PG-002 | PG- | INFORMATION_ARCHITECTURE · 8.1 System, account and Beranda | TASK-018 |
| PG-003 | PG- | INFORMATION_ARCHITECTURE · 8.1 System, account and Beranda | TASK-017 |
| PG-004 | PG- | INFORMATION_ARCHITECTURE · 8.1 System, account and Beranda | TASK-018 |
| PG-052 | PG- | INFORMATION_ARCHITECTURE · 8.6 Reports, master data, settings and Owner areas | TASK-060 |
| PG-025 | PG- | INFORMATION_ARCHITECTURE · 8.3 Purchasing and warehouse | TASK-038 |
| PG-020 | PG- | INFORMATION_ARCHITECTURE · 8.3 Purchasing and warehouse | TASK-040 |
| PG-049 | PG- | INFORMATION_ARCHITECTURE · 8.5 Finance | TASK-054 |
| PG-009 | PG- | INFORMATION_ARCHITECTURE · 8.1 System, account and Beranda | TASK-059 |
| PG-010 | PG- | INFORMATION_ARCHITECTURE · 8.2 Projects and quotations | TASK-032 |
| PG-012 | PG- | INFORMATION_ARCHITECTURE · 8.2 Projects and quotations | TASK-055 |
| PG-013 | PG- | INFORMATION_ARCHITECTURE · 8.2 Projects and quotations | TASK-032 |
| PG-037 | PG- | INFORMATION_ARCHITECTURE · 8.2 Projects and quotations | TASK-033 |
| PG-021 | PG- | INFORMATION_ARCHITECTURE · 8.3 Purchasing and warehouse | TASK-039 |
| PG-026 | PG- | INFORMATION_ARCHITECTURE · 8.3 Purchasing and warehouse | TASK-043 |
| PG-027 | PG- | INFORMATION_ARCHITECTURE · 8.3 Purchasing and warehouse | TASK-042 |
| PG-028 | PG- | INFORMATION_ARCHITECTURE · 8.3 Purchasing and warehouse | TASK-044 |
| PG-029 | PG- | INFORMATION_ARCHITECTURE · 8.3 Purchasing and warehouse | TASK-043 |
| PG-032 | PG- | INFORMATION_ARCHITECTURE · 8.4 Fulfilment, cases and documents | TASK-046 |
| PG-034 | PG- | INFORMATION_ARCHITECTURE · 8.4 Fulfilment, cases and documents | TASK-048 |
| PG-038 | PG- | INFORMATION_ARCHITECTURE · 8.4 Fulfilment, cases and documents | TASK-030 |
| PG-045 | PG- | INFORMATION_ARCHITECTURE · 8.5 Finance | TASK-051 |
| PG-046 | PG- | INFORMATION_ARCHITECTURE · 8.5 Finance | TASK-051 |
| PG-047 | PG- | INFORMATION_ARCHITECTURE · 8.5 Finance | TASK-052 |
| PG-048 | PG- | INFORMATION_ARCHITECTURE · 8.5 Finance | TASK-053 |
| PG-057 | PG- | INFORMATION_ARCHITECTURE · 8.6 Reports, master data, settings and Owner areas | TASK-028 |
| PG-060 | PG- | INFORMATION_ARCHITECTURE · 8.6 Reports, master data, settings and Owner areas | TASK-024 |
| SCR-01 | SCR- | INFORMATION_ARCHITECTURE · 8.7 The approved screens SCR-01–SCR-60 and their pages | TASK-017, TASK-018 |
| SCR-60 | SCR- | INFORMATION_ARCHITECTURE · 8.7 The approved screens SCR-01–SCR-60 and their pages | TASK-061 |
| SCR-10 | SCR- | INFORMATION_ARCHITECTURE · 8.7 The approved screens SCR-01–SCR-60 and their pages | TASK-032 |
| SCR-14 | SCR- | INFORMATION_ARCHITECTURE · 8.7 The approved screens SCR-01–SCR-60 and their pages | TASK-032, TASK-055 |
| SCR-02 | SCR- | INFORMATION_ARCHITECTURE · 8.7 The approved screens SCR-01–SCR-60 and their pages | TASK-017, TASK-018 |
| SCR-04 | SCR- | INFORMATION_ARCHITECTURE · 8.7 The approved screens SCR-01–SCR-60 and their pages | TASK-016 |
| SCR-05 | SCR- | INFORMATION_ARCHITECTURE · 8.7 The approved screens SCR-01–SCR-60 and their pages | TASK-058 |
| SCR-06 | SCR- | INFORMATION_ARCHITECTURE · 8.7 The approved screens SCR-01–SCR-60 and their pages | TASK-058 |
| SCR-07 | SCR- | INFORMATION_ARCHITECTURE · 8.7 The approved screens SCR-01–SCR-60 and their pages | TASK-058 |
| SCR-08 | SCR- | INFORMATION_ARCHITECTURE · 8.7 The approved screens SCR-01–SCR-60 and their pages | TASK-059 |
| SCR-03 | SCR- | INFORMATION_ARCHITECTURE · 8.7 The approved screens SCR-01–SCR-60 and their pages | TASK-023 |
| SCR-09 | SCR- | INFORMATION_ARCHITECTURE · 8.7 The approved screens SCR-01–SCR-60 and their pages | TASK-032 |
| SCR-11 | SCR- | INFORMATION_ARCHITECTURE · 8.7 The approved screens SCR-01–SCR-60 and their pages | TASK-032 |
| SCR-13 | SCR- | INFORMATION_ARCHITECTURE · 8.7 The approved screens SCR-01–SCR-60 and their pages | TASK-033 |
| SCR-12 | SCR- | INFORMATION_ARCHITECTURE · 8.7 The approved screens SCR-01–SCR-60 and their pages | TASK-033 |
| SCR-15 | SCR- | INFORMATION_ARCHITECTURE · 8.7 The approved screens SCR-01–SCR-60 and their pages | TASK-037 |
| SCR-16 | SCR- | INFORMATION_ARCHITECTURE · 8.7 The approved screens SCR-01–SCR-60 and their pages | TASK-037 |
| SCR-17 | SCR- | INFORMATION_ARCHITECTURE · 8.7 The approved screens SCR-01–SCR-60 and their pages | TASK-037 |
| SCR-18 | SCR- | INFORMATION_ARCHITECTURE · 8.7 The approved screens SCR-01–SCR-60 and their pages | TASK-040 |
| SCR-19 | SCR- | INFORMATION_ARCHITECTURE · 8.7 The approved screens SCR-01–SCR-60 and their pages | TASK-040 |
| SCR-20 | SCR- | INFORMATION_ARCHITECTURE · 8.7 The approved screens SCR-01–SCR-60 and their pages | TASK-040 |
| SCR-21 | SCR- | INFORMATION_ARCHITECTURE · 8.7 The approved screens SCR-01–SCR-60 and their pages | TASK-039 |
| SCR-22 | SCR- | INFORMATION_ARCHITECTURE · 8.7 The approved screens SCR-01–SCR-60 and their pages | TASK-041 |
| SCR-23 | SCR- | INFORMATION_ARCHITECTURE · 8.7 The approved screens SCR-01–SCR-60 and their pages | TASK-038 |
| SCR-24 | SCR- | INFORMATION_ARCHITECTURE · 8.7 The approved screens SCR-01–SCR-60 and their pages | TASK-043 |
| SCR-25 | SCR- | INFORMATION_ARCHITECTURE · 8.7 The approved screens SCR-01–SCR-60 and their pages | TASK-042 |
| SCR-26 | SCR- | INFORMATION_ARCHITECTURE · 8.7 The approved screens SCR-01–SCR-60 and their pages | TASK-044 |
| SCR-27 | SCR- | INFORMATION_ARCHITECTURE · 8.7 The approved screens SCR-01–SCR-60 and their pages | TASK-043 |
| SCR-28 | SCR- | INFORMATION_ARCHITECTURE · 8.7 The approved screens SCR-01–SCR-60 and their pages | TASK-045 |
| SCR-29 | SCR- | INFORMATION_ARCHITECTURE · 8.7 The approved screens SCR-01–SCR-60 and their pages | TASK-045 |
| SCR-30 | SCR- | INFORMATION_ARCHITECTURE · 8.7 The approved screens SCR-01–SCR-60 and their pages | TASK-046 |
| SCR-31 | SCR- | INFORMATION_ARCHITECTURE · 8.7 The approved screens SCR-01–SCR-60 and their pages | TASK-047 |
| SCR-32 | SCR- | INFORMATION_ARCHITECTURE · 8.7 The approved screens SCR-01–SCR-60 and their pages | TASK-048 |
| SCR-34 | SCR- | INFORMATION_ARCHITECTURE · 8.7 The approved screens SCR-01–SCR-60 and their pages | TASK-029 |
| SCR-35 | SCR- | INFORMATION_ARCHITECTURE · 8.7 The approved screens SCR-01–SCR-60 and their pages | TASK-029 |
| SCR-36 | SCR- | INFORMATION_ARCHITECTURE · 8.7 The approved screens SCR-01–SCR-60 and their pages | TASK-030 |
| SCR-37 | SCR- | INFORMATION_ARCHITECTURE · 8.7 The approved screens SCR-01–SCR-60 and their pages | TASK-035 |
| SCR-33 | SCR- | INFORMATION_ARCHITECTURE · 8.7 The approved screens SCR-01–SCR-60 and their pages | TASK-031 |
| SCR-38 | SCR- | INFORMATION_ARCHITECTURE · 8.7 The approved screens SCR-01–SCR-60 and their pages | TASK-049 |
| SCR-39 | SCR- | INFORMATION_ARCHITECTURE · 8.7 The approved screens SCR-01–SCR-60 and their pages | TASK-049 |
| SCR-40 | SCR- | INFORMATION_ARCHITECTURE · 8.7 The approved screens SCR-01–SCR-60 and their pages | TASK-050 |
| SCR-41 | SCR- | INFORMATION_ARCHITECTURE · 8.7 The approved screens SCR-01–SCR-60 and their pages | TASK-051 |
| SCR-42 | SCR- | INFORMATION_ARCHITECTURE · 8.7 The approved screens SCR-01–SCR-60 and their pages | TASK-051 |
| SCR-43 | SCR- | INFORMATION_ARCHITECTURE · 8.7 The approved screens SCR-01–SCR-60 and their pages | TASK-052 |
| SCR-44 | SCR- | INFORMATION_ARCHITECTURE · 8.7 The approved screens SCR-01–SCR-60 and their pages | TASK-053 |
| SCR-45 | SCR- | INFORMATION_ARCHITECTURE · 8.7 The approved screens SCR-01–SCR-60 and their pages | TASK-054 |
| SCR-46 | SCR- | INFORMATION_ARCHITECTURE · 8.7 The approved screens SCR-01–SCR-60 and their pages | TASK-051 |
| SCR-47 | SCR- | INFORMATION_ARCHITECTURE · 8.7 The approved screens SCR-01–SCR-60 and their pages | TASK-060 |
| SCR-48 | SCR- | INFORMATION_ARCHITECTURE · 8.7 The approved screens SCR-01–SCR-60 and their pages | TASK-060 |
| SCR-49 | SCR- | INFORMATION_ARCHITECTURE · 8.7 The approved screens SCR-01–SCR-60 and their pages | TASK-060 |
| SCR-50 | SCR- | INFORMATION_ARCHITECTURE · 8.7 The approved screens SCR-01–SCR-60 and their pages | TASK-026 |
| SCR-51 | SCR- | INFORMATION_ARCHITECTURE · 8.7 The approved screens SCR-01–SCR-60 and their pages | TASK-026 |
| SCR-52 | SCR- | INFORMATION_ARCHITECTURE · 8.7 The approved screens SCR-01–SCR-60 and their pages | TASK-027 |
| SCR-53 | SCR- | INFORMATION_ARCHITECTURE · 8.7 The approved screens SCR-01–SCR-60 and their pages | TASK-028 |
| SCR-58 | SCR- | INFORMATION_ARCHITECTURE · 8.7 The approved screens SCR-01–SCR-60 and their pages | TASK-057 |
| SCR-54 | SCR- | INFORMATION_ARCHITECTURE · 8.7 The approved screens SCR-01–SCR-60 and their pages | TASK-025 |
| SCR-57 | SCR- | INFORMATION_ARCHITECTURE · 8.7 The approved screens SCR-01–SCR-60 and their pages | TASK-024 |
| SCR-55 | SCR- | INFORMATION_ARCHITECTURE · 8.7 The approved screens SCR-01–SCR-60 and their pages | TASK-021 |
| SCR-56 | SCR- | INFORMATION_ARCHITECTURE · 8.7 The approved screens SCR-01–SCR-60 and their pages | TASK-025 |
| SCR-59 | SCR- | INFORMATION_ARCHITECTURE · 8.7 The approved screens SCR-01–SCR-60 and their pages | TASK-061 |
| UXI-30 | UXI- | ADMIN_FLOW · 3. Derived interaction requirements | TASK-015 |
| UXI-01 | UXI- | ADMIN_FLOW · 3. Derived interaction requirements | TASK-015 |
| UXI-02 | UXI- | ADMIN_FLOW · 3. Derived interaction requirements | TASK-013, TASK-015 |
| UXI-03 | UXI- | ADMIN_FLOW · 3. Derived interaction requirements | TASK-011, TASK-013, TASK-015, TASK-016 |
| UXI-04 | UXI- | ADMIN_FLOW · 3. Derived interaction requirements | TASK-013 |
| UXI-05 | UXI- | ADMIN_FLOW · 3. Derived interaction requirements | TASK-013 |
| UXI-06 | UXI- | ADMIN_FLOW · 3. Derived interaction requirements | TASK-013 |
| UXI-07 | UXI- | ADMIN_FLOW · 3. Derived interaction requirements | TASK-013 |
| UXI-08 | UXI- | ADMIN_FLOW · 3. Derived interaction requirements | TASK-013 |
| UXI-09 | UXI- | ADMIN_FLOW · 3. Derived interaction requirements | TASK-022, TASK-060 |
| UXI-10 | UXI- | ADMIN_FLOW · 3. Derived interaction requirements | TASK-013 |
| UXI-11 | UXI- | ADMIN_FLOW · 3. Derived interaction requirements | TASK-013, TASK-015 |
| UXI-12 | UXI- | ADMIN_FLOW · 3. Derived interaction requirements | TASK-022, TASK-060 |
| UXI-13 | UXI- | ADMIN_FLOW · 3. Derived interaction requirements | TASK-015 |
| UXI-14 | UXI- | ADMIN_FLOW · 3. Derived interaction requirements | TASK-015 |
| UXI-15 | UXI- | ADMIN_FLOW · 3. Derived interaction requirements | TASK-011, TASK-016 |
| UXI-16 | UXI- | ADMIN_FLOW · 3. Derived interaction requirements | TASK-011, TASK-013, TASK-016, TASK-017, TASK-024 |
| UXI-17 | UXI- | ADMIN_FLOW · 3. Derived interaction requirements | TASK-016, TASK-017 |
| UXI-18 | UXI- | ADMIN_FLOW · 3. Derived interaction requirements | TASK-016 |
| UXI-19 | UXI- | ADMIN_FLOW · 3. Derived interaction requirements | TASK-017 |
| UXI-20 | UXI- | ADMIN_FLOW · 3. Derived interaction requirements | TASK-016, TASK-017 |
| UXI-21 | UXI- | ADMIN_FLOW · 3. Derived interaction requirements | TASK-015, TASK-017 |
| UXI-22 | UXI- | ADMIN_FLOW · 3. Derived interaction requirements | TASK-017, TASK-023, TASK-024 |
| UXI-23 | UXI- | ADMIN_FLOW · 3. Derived interaction requirements | TASK-015 |
| UXI-24 | UXI- | ADMIN_FLOW · 3. Derived interaction requirements | TASK-055 |
| UXI-25 | UXI- | ADMIN_FLOW · 3. Derived interaction requirements | TASK-015, TASK-031 |
| UXI-26 | UXI- | ADMIN_FLOW · 3. Derived interaction requirements | TASK-055 |
| UXI-27 | UXI- | ADMIN_FLOW · 3. Derived interaction requirements | TASK-049 |
| UXI-28 | UXI- | ADMIN_FLOW · 3. Derived interaction requirements | TASK-015 |
| UXI-29 | UXI- | ADMIN_FLOW · 3. Derived interaction requirements | TASK-022, TASK-029, TASK-060 |
| UXI-31 | UXI- | ADMIN_FLOW · 3. Derived interaction requirements | TASK-024, TASK-025 |
| UXI-32 | UXI- | ADMIN_FLOW · 3. Derived interaction requirements | TASK-017, TASK-057 |
| UXI-33 | UXI- | ADMIN_FLOW · 3. Derived interaction requirements | TASK-022, TASK-030 |
| UXI-34 | UXI- | ADMIN_FLOW · 3. Derived interaction requirements | TASK-039, TASK-059 |
| UXI-35 | UXI- | ADMIN_FLOW · 3. Derived interaction requirements | TASK-038 |
| UXI-36 | UXI- | ADMIN_FLOW · 3. Derived interaction requirements | TASK-015 |
| UXI-37 | UXI- | ADMIN_FLOW · 3. Derived interaction requirements | TASK-015 |
| UXI-38 | UXI- | ADMIN_FLOW · 3. Derived interaction requirements | TASK-015 |
| UXI-39 | UXI- | ADMIN_FLOW · 3. Derived interaction requirements | TASK-015, TASK-059 |
| UXI-40 | UXI- | ADMIN_FLOW · 3. Derived interaction requirements | TASK-055 |
| UXI-41 | UXI- | ADMIN_FLOW · 3. Derived interaction requirements | TASK-013 |
| UXI-42 | UXI- | ADMIN_FLOW · 3. Derived interaction requirements | TASK-015 |
| D-UX-01 | D-UX- | ADMIN_FLOW · 4. Design decisions | TASK-015, TASK-016 |
| D-UX-02 | D-UX- | ADMIN_FLOW · 4. Design decisions | TASK-015, TASK-016 |
| D-UX-03 | D-UX- | ADMIN_FLOW · 4. Design decisions | TASK-016, TASK-017 |
| D-UX-04 | D-UX- | ADMIN_FLOW · 4. Design decisions | TASK-015, TASK-016 |
| D-UX-05 | D-UX- | ADMIN_FLOW · 4. Design decisions | TASK-015, TASK-016 |
| D-UX-06 | D-UX- | ADMIN_FLOW · 4. Design decisions | TASK-015, TASK-016 |
| D-UX-07 | D-UX- | ADMIN_FLOW · 4. Design decisions | TASK-015, TASK-016 |
| D-UX-08 | D-UX- | ADMIN_FLOW · 4. Design decisions | TASK-015, TASK-016 |
| D-UX-09 | D-UX- | ADMIN_FLOW · 4. Design decisions | TASK-015, TASK-016 |
| D-UX-10 | D-UX- | ADMIN_FLOW · 4. Design decisions | TASK-015, TASK-016 |
| D-UX-11 | D-UX- | ADMIN_FLOW · 4. Design decisions | TASK-015 |
| D-UX-12 | D-UX- | ADMIN_FLOW · 4. Design decisions | TASK-039 |
| IP-11 | IP- | ADMIN_FLOW · IP-11 — Session limits, expiry and re-authentication | TASK-016, TASK-017 |
| IP-01 | IP- | ADMIN_FLOW · IP-01 — Answers of a command | TASK-013, TASK-015 |
| IP-21 | IP- | ADMIN_FLOW · IP-21 — Denial and not-found | TASK-011, TASK-016 |
| IP-02 | IP- | ADMIN_FLOW · IP-02 — One `command_id` per intent | TASK-013 |
| IP-03 | IP- | ADMIN_FLOW · IP-03 — CONFLICT and stale state | TASK-013 |
| IP-05 | IP- | ADMIN_FLOW · IP-05 — Duplicate warnings | TASK-013 |
| IP-12 | IP- | ADMIN_FLOW · IP-12 — Derived background work | TASK-022, TASK-060 |
| IP-04 | IP- | ADMIN_FLOW · IP-04 — FAILED, the unknown outcome and retry | TASK-013 |
| IP-07 | IP- | ADMIN_FLOW · IP-07 — Business date | TASK-013, TASK-015 |
| IP-19 | IP- | ADMIN_FLOW · IP-19 — Several commands on one screen | TASK-015 |
| IP-13 | IP- | ADMIN_FLOW · IP-13 — Entry | TASK-015 |
| IP-06 | IP- | ADMIN_FLOW · IP-06 — A routed refusal | TASK-011, TASK-016 |
| IP-10 | IP- | ADMIN_FLOW · IP-10 — Step-up confirmation | TASK-015, TASK-017 |
| IP-08 | IP- | ADMIN_FLOW · IP-08 — Confirming an irreversible command | TASK-015 |
| IP-09 | IP- | ADMIN_FLOW · IP-09 — Corrections, closures and destructive actions | TASK-015, TASK-031 |
| IP-16 | IP- | ADMIN_FLOW · IP-16 — Evidence upload | TASK-022, TASK-030 |
| IP-15 | IP- | ADMIN_FLOW · IP-15 — Scanner | TASK-039, TASK-059 |
| IP-20 | IP- | ADMIN_FLOW · IP-20 — Choosing a reservation cut | TASK-038 |
| IP-14 | IP- | ADMIN_FLOW · IP-14 — Validation | TASK-015 |
| IP-17 | IP- | ADMIN_FLOW · IP-17 — Search and lookup | TASK-015, TASK-016, TASK-059 |
| IP-18 | IP- | ADMIN_FLOW · IP-18 — Continuing a list | TASK-015 |
| J-ACC-01 | J- | ADMIN_FLOW · 10.1 Access and master data | TASK-017, TASK-023, TASK-058, TASK-064 |
| J-INV-01 | J- | ADMIN_FLOW · 10.3 Inventory and purchasing | TASK-037, TASK-039, TASK-062 |
| J-PRJ-03 | J- | ADMIN_FLOW · 10.7 Completion, cancellation and migration | TASK-032, TASK-055, TASK-063 |
| J-FIN-08 | J- | ADMIN_FLOW · 10.6 Finance | TASK-029, TASK-049, TASK-051, TASK-063 |
| J-INV-04 | J- | ADMIN_FLOW · 10.3 Inventory and purchasing | TASK-040, TASK-042, TASK-043, TASK-062 |
| J-INV-06 | J- | ADMIN_FLOW · 10.3 Inventory and purchasing | TASK-042, TASK-045, TASK-062 |
| J-DOC-01 | J- | ADMIN_FLOW · 10.5 Documents and administration | TASK-029, TASK-063 |
| J-ACC-02 | J- | ADMIN_FLOW · 10.1 Access and master data | TASK-024, TASK-064 |
| J-MD-01 | J- | ADMIN_FLOW · 10.1 Access and master data | TASK-021, TASK-025, TASK-064 |
| J-MD-02 | J- | ADMIN_FLOW · 10.1 Access and master data | TASK-026, TASK-032, TASK-064 |
| J-MD-03 | J- | ADMIN_FLOW · 10.1 Access and master data | TASK-026, TASK-037, TASK-064 |
| J-MD-04 | J- | ADMIN_FLOW · 10.1 Access and master data | TASK-027, TASK-064 |
| J-MD-05 | J- | ADMIN_FLOW · 10.1 Access and master data | TASK-028, TASK-037, TASK-040, TASK-042, TASK-064 |
| J-PRJ-01 | J- | ADMIN_FLOW · 10.2 Project and commercial | TASK-032, TASK-062 |
| J-QUO-01 | J- | ADMIN_FLOW · 10.2 Project and commercial | TASK-032, TASK-033, TASK-062 |
| J-PRJ-04 | J- | ADMIN_FLOW · 10.7 Completion, cancellation and migration | TASK-032, TASK-034, TASK-056, TASK-063 |
| J-PRJ-02 | J- | ADMIN_FLOW · 10.2 Project and commercial | TASK-032, TASK-033, TASK-034, TASK-038, TASK-062 |
| J-INV-02 | J- | ADMIN_FLOW · 10.3 Inventory and purchasing | TASK-032, TASK-034, TASK-038, TASK-062 |
| J-FUL-01 | J- | ADMIN_FLOW · 10.2 Project and commercial | TASK-032, TASK-034, TASK-038, TASK-062 |
| J-PUR-01 | J- | ADMIN_FLOW · 10.3 Inventory and purchasing | TASK-032, TASK-037, TASK-062 |
| J-PUR-02 | J- | ADMIN_FLOW · 10.3 Inventory and purchasing | TASK-037, TASK-062 |
| J-INV-03 | J- | ADMIN_FLOW · 10.3 Inventory and purchasing | TASK-032, TASK-041, TASK-062 |
| J-INV-07 | J- | ADMIN_FLOW · 10.3 Inventory and purchasing | TASK-041, TASK-060, TASK-064 |
| J-PUR-03 | J- | ADMIN_FLOW · 10.3 Inventory and purchasing | TASK-037, TASK-044, TASK-063 |
| J-INV-05 | J- | ADMIN_FLOW · 10.3 Inventory and purchasing | TASK-043, TASK-062 |
| J-FUL-02 | J- | ADMIN_FLOW · 10.4 Fulfilment | TASK-032, TASK-045, TASK-062 |
| J-FUL-05 | J- | ADMIN_FLOW · 10.4 Fulfilment | TASK-032, TASK-045, TASK-048, TASK-063 |
| J-FUL-03 | J- | ADMIN_FLOW · 10.4 Fulfilment | TASK-032, TASK-046, TASK-062 |
| J-FUL-04 | J- | ADMIN_FLOW · 10.4 Fulfilment | TASK-032, TASK-047, TASK-062 |
| J-FIN-01 | J- | ADMIN_FLOW · 10.6 Finance | TASK-032, TASK-049, TASK-063 |
| J-DOC-02 | J- | ADMIN_FLOW · 10.5 Documents and administration | TASK-030, TASK-063 |
| J-ADM-01 | J- | ADMIN_FLOW · 10.5 Documents and administration | TASK-032, TASK-035, TASK-063 |
| J-FIN-02 | J- | ADMIN_FLOW · 10.6 Finance | TASK-049, TASK-050, TASK-060, TASK-063 |
| J-FIN-03 | J- | ADMIN_FLOW · 10.6 Finance | TASK-049, TASK-050, TASK-051, TASK-063 |
| J-FIN-04 | J- | ADMIN_FLOW · 10.6 Finance | TASK-052, TASK-062 |
| J-FIN-05 | J- | ADMIN_FLOW · 10.6 Finance | TASK-049, TASK-050, TASK-063 |
| J-FIN-06 | J- | ADMIN_FLOW · 10.6 Finance | TASK-053, TASK-063 |
| J-FIN-07 | J- | ADMIN_FLOW · 10.6 Finance | TASK-051, TASK-054, TASK-063 |
| J-MIG-01 | J- | ADMIN_FLOW · 10.7 Completion, cancellation and migration | TASK-057, TASK-064 |
| MSG-01 | MSG- | ADMIN_FLOW · 14. Message catalogue | TASK-014 |
| MSG-02 | MSG- | ADMIN_FLOW · 14. Message catalogue | TASK-014 |
| MSG-18 | MSG- | ADMIN_FLOW · 14. Message catalogue | TASK-014 |
| MSG-03 | MSG- | ADMIN_FLOW · 14. Message catalogue | TASK-014 |
| MSG-04 | MSG- | ADMIN_FLOW · 14. Message catalogue | TASK-013, TASK-014 |
| MSG-05 | MSG- | ADMIN_FLOW · 14. Message catalogue | TASK-014 |
| MSG-06 | MSG- | ADMIN_FLOW · 14. Message catalogue | TASK-013, TASK-014 |
| MSG-07 | MSG- | ADMIN_FLOW · 14. Message catalogue | TASK-014 |
| MSG-09 | MSG- | ADMIN_FLOW · 14. Message catalogue | TASK-014 |
| MSG-12 | MSG- | ADMIN_FLOW · 14. Message catalogue | TASK-014 |
| MSG-10 | MSG- | ADMIN_FLOW · 14. Message catalogue | TASK-014 |
| MSG-11 | MSG- | ADMIN_FLOW · 14. Message catalogue | TASK-014 |
| MSG-13 | MSG- | ADMIN_FLOW · 14. Message catalogue | TASK-013, TASK-014, TASK-015 |
| MSG-20 | MSG- | ADMIN_FLOW · 14. Message catalogue | TASK-013, TASK-014, TASK-017 |
| MSG-27 | MSG- | ADMIN_FLOW · 14. Message catalogue | TASK-014, TASK-017 |
| MSG-15 | MSG- | ADMIN_FLOW · 14. Message catalogue | TASK-013, TASK-014 |
| MSG-14 | MSG- | ADMIN_FLOW · 14. Message catalogue | TASK-014 |
| MSG-16 | MSG- | ADMIN_FLOW · 14. Message catalogue | TASK-014 |
| MSG-33 | MSG- | ADMIN_FLOW · 14. Message catalogue | TASK-014, TASK-016, TASK-017 |
| MSG-34 | MSG- | ADMIN_FLOW · 14. Message catalogue | TASK-014, TASK-016, TASK-017 |
| MSG-35 | MSG- | ADMIN_FLOW · 14. Message catalogue | TASK-014 |
| MSG-19 | MSG- | ADMIN_FLOW · 14. Message catalogue | TASK-014, TASK-017 |
| MSG-08 | MSG- | ADMIN_FLOW · 14. Message catalogue | TASK-014 |
| MSG-22 | MSG- | ADMIN_FLOW · 14. Message catalogue | TASK-014 |
| MSG-21 | MSG- | ADMIN_FLOW · 14. Message catalogue | TASK-014 |
| MSG-26 | MSG- | ADMIN_FLOW · 14. Message catalogue | TASK-014 |
| MSG-25 | MSG- | ADMIN_FLOW · 14. Message catalogue | TASK-014 |
| MSG-24 | MSG- | ADMIN_FLOW · 14. Message catalogue | TASK-014 |
| MSG-23 | MSG- | ADMIN_FLOW · 14. Message catalogue | TASK-014 |
| MSG-36 | MSG- | ADMIN_FLOW · 14. Message catalogue | TASK-014 |
| MSG-31 | MSG- | ADMIN_FLOW · 14. Message catalogue | TASK-014 |
| MSG-32 | MSG- | ADMIN_FLOW · 14. Message catalogue | TASK-014 |
| MSG-28 | MSG- | ADMIN_FLOW · 14. Message catalogue | TASK-014, TASK-017, TASK-024 |
| MSG-29 | MSG- | ADMIN_FLOW · 14. Message catalogue | TASK-014, TASK-017, TASK-023, TASK-024 |
| MSG-38 | MSG- | ADMIN_FLOW · 14. Message catalogue | TASK-014 |
| MSG-17 | MSG- | ADMIN_FLOW · 14. Message catalogue | TASK-014 |
| MSG-30 | MSG- | ADMIN_FLOW · 14. Message catalogue | TASK-014 |
| MSG-37 | MSG- | ADMIN_FLOW · 14. Message catalogue | TASK-014 |
| MSG-39 | MSG- | ADMIN_FLOW · 14. Message catalogue | TASK-014, TASK-015 |
| MSG-40 | MSG- | ADMIN_FLOW · 14. Message catalogue | TASK-014, TASK-015, TASK-031 |
| MSG-41 | MSG- | ADMIN_FLOW · 14. Message catalogue | TASK-014 |
| UXS-24 | UXS- | ADMIN_FLOW · Visibility and authority | TASK-015, TASK-064 |
| UXS-08 | UXS- | ADMIN_FLOW · Command identity and outcomes | TASK-013, TASK-015, TASK-064 |
| UXS-01 | UXS- | ADMIN_FLOW · Command identity and outcomes | TASK-013, TASK-029, TASK-049, TASK-051, TASK-064 |
| UXS-02 | UXS- | ADMIN_FLOW · Command identity and outcomes | TASK-013, TASK-029, TASK-049, TASK-064 |
| UXS-03 | UXS- | ADMIN_FLOW · Command identity and outcomes | TASK-013, TASK-064 |
| UXS-04 | UXS- | ADMIN_FLOW · Command identity and outcomes | TASK-013, TASK-016, TASK-064 |
| UXS-05 | UXS- | ADMIN_FLOW · Command identity and outcomes | TASK-015, TASK-064 |
| UXS-06 | UXS- | ADMIN_FLOW · Command identity and outcomes | TASK-015, TASK-064 |
| UXS-07 | UXS- | ADMIN_FLOW · Command identity and outcomes | TASK-013, TASK-064 |
| UXS-09 | UXS- | ADMIN_FLOW · Command identity and outcomes | TASK-022, TASK-029, TASK-060, TASK-063 |
| UXS-10 | UXS- | ADMIN_FLOW · Command identity and outcomes | TASK-013, TASK-015, TASK-062 |
| UXS-11 | UXS- | ADMIN_FLOW · Command identity and outcomes | TASK-013, TASK-033, TASK-049, TASK-064 |
| UXS-12 | UXS- | ADMIN_FLOW · Command identity and outcomes | TASK-013, TASK-064 |
| UXS-13 | UXS- | ADMIN_FLOW · Command identity and outcomes | TASK-013, TASK-015, TASK-064 |
| UXS-14 | UXS- | ADMIN_FLOW · Command identity and outcomes | TASK-013, TASK-015, TASK-063 |
| UXS-15 | UXS- | ADMIN_FLOW · Duplicates | TASK-013, TASK-041, TASK-042, TASK-045, TASK-062 |
| UXS-16 | UXS- | ADMIN_FLOW · Duplicates | TASK-013, TASK-051, TASK-063 |
| UXS-17 | UXS- | ADMIN_FLOW · Duplicates | TASK-013, TASK-022, TASK-030, TASK-063 |
| UXS-18 | UXS- | ADMIN_FLOW · Duplicates | TASK-022, TASK-030, TASK-058, TASK-064 |
| UXS-19 | UXS- | ADMIN_FLOW · Duplicates | TASK-057, TASK-058, TASK-064 |
| UXS-20 | UXS- | ADMIN_FLOW · Visibility and authority | TASK-040, TASK-064 |
| UXS-21 | UXS- | ADMIN_FLOW · Visibility and authority | TASK-015, TASK-064 |
| UXS-22 | UXS- | ADMIN_FLOW · Visibility and authority | TASK-011, TASK-016, TASK-041, TASK-042, TASK-064 |
| UXS-23 | UXS- | ADMIN_FLOW · Visibility and authority | TASK-011, TASK-016, TASK-022, TASK-060, TASK-064 |
| UXS-25 | UXS- | ADMIN_FLOW · Visibility and authority | TASK-011, TASK-016, TASK-058, TASK-064 |
| UXS-26 | UXS- | ADMIN_FLOW · Visibility and authority | TASK-015, TASK-017, TASK-064 |
| UXS-27 | UXS- | ADMIN_FLOW · Visibility and authority | TASK-024, TASK-064 |
| UXS-28 | UXS- | ADMIN_FLOW · Numbering | TASK-021, TASK-032, TASK-064 |
| UXS-29 | UXS- | ADMIN_FLOW · Numbering | TASK-021, TASK-049, TASK-063 |
| UXS-30 | UXS- | ADMIN_FLOW · Numbering | TASK-021, TASK-064 |
| UXS-31 | UXS- | ADMIN_FLOW · Numbering | TASK-021, TASK-064 |
| UXS-32 | UXS- | ADMIN_FLOW · Numbering | TASK-021, TASK-064 |
| UXS-33 | UXS- | ADMIN_FLOW · Lists, search, scanner and exports | TASK-015, TASK-059, TASK-064 |
| UXS-34 | UXS- | ADMIN_FLOW · Lists, search, scanner and exports | TASK-039, TASK-041, TASK-059, TASK-062 |
| UXS-35 | UXS- | ADMIN_FLOW · Lists, search, scanner and exports | TASK-015, TASK-037, TASK-039, TASK-059, TASK-062 |
| UXS-36 | UXS- | ADMIN_FLOW · Lists, search, scanner and exports | TASK-022, TASK-060, TASK-063 |
| UXS-37 | UXS- | ADMIN_FLOW · Workflow presentations | TASK-055, TASK-063 |
| UXS-38 | UXS- | ADMIN_FLOW · Workflow presentations | TASK-029, TASK-049, TASK-063 |
| UXS-39 | UXS- | ADMIN_FLOW · Workflow presentations | TASK-040, TASK-043, TASK-060, TASK-062 |
| UXS-40 | UXS- | ADMIN_FLOW · Workflow presentations | TASK-058, TASK-064 |
| UXS-41 | UXS- | ADMIN_FLOW · Design | TASK-033, TASK-062, TASK-065, TASK-066 |
| UXS-42 | UXS- | ADMIN_FLOW · Design | TASK-015, TASK-064, TASK-065, TASK-066 |
| UXS-43 | UXS- | ADMIN_FLOW · Design | TASK-058, TASK-064, TASK-065 |
| UXS-44 | UXS- | ADMIN_FLOW · Design | TASK-032, TASK-062, TASK-065 |
| UXH-11 | UXH- | ADMIN_FLOW · 16. Handoff obligations | TASK-015, TASK-016, TASK-017, TASK-060, TASK-065 |
| UXH-02 | UXH- | ADMIN_FLOW · 16. Handoff obligations | TASK-064, TASK-069 |
| UXH-01 | UXH- | ADMIN_FLOW · 16. Handoff obligations | TASK-062, TASK-063, TASK-064, TASK-069 |
| UXH-03 | UXH- | ADMIN_FLOW · 16. Handoff obligations | TASK-069 |
| UXH-04 | UXH- | ADMIN_FLOW · 16. Handoff obligations | TASK-069 |
| UXH-05 | UXH- | ADMIN_FLOW · 16. Handoff obligations | TASK-066, TASK-069 |
| UXH-06 | UXH- | ADMIN_FLOW · 16. Handoff obligations | TASK-069, TASK-070 |
| UXH-07 | UXH- | ADMIN_FLOW · 16. Handoff obligations | TASK-064, TASK-065, TASK-069 |
| UXH-08 | UXH- | ADMIN_FLOW · 16. Handoff obligations | TASK-069, TASK-070, TASK-086 |
| UXH-09 | UXH- | ADMIN_FLOW · 16. Handoff obligations | TASK-072, TASK-076 |
| UXH-10 | UXH- | ADMIN_FLOW · 16. Handoff obligations | TASK-021, TASK-064, TASK-070, TASK-077, TASK-086, TASK-087 |
| UXH-12 | UXH- | ADMIN_FLOW · 16. Handoff obligations | disposition SATISFIED — a statement of intent, not an authorization; DesainPakeAI was discontinued for design work (DIR-059 GD-06) — no Task uses it |
| UXV-01 | UXV- | DESIGN_SYSTEM · 3. Derived visual requirements | TASK-015 |
| UXV-02 | UXV- | DESIGN_SYSTEM · 3. Derived visual requirements | TASK-015 |
| UXV-03 | UXV- | DESIGN_SYSTEM · 3. Derived visual requirements | TASK-015 |
| UXV-04 | UXV- | DESIGN_SYSTEM · 3. Derived visual requirements | TASK-015 |
| UXV-05 | UXV- | DESIGN_SYSTEM · 3. Derived visual requirements | TASK-015 |
| UXV-06 | UXV- | DESIGN_SYSTEM · 3. Derived visual requirements | TASK-015 |
| UXV-07 | UXV- | DESIGN_SYSTEM · 3. Derived visual requirements | TASK-015 |
| UXV-08 | UXV- | DESIGN_SYSTEM · 3. Derived visual requirements | TASK-015 |
| UXV-09 | UXV- | DESIGN_SYSTEM · 3. Derived visual requirements | TASK-015 |
| UXV-10 | UXV- | DESIGN_SYSTEM · 3. Derived visual requirements | TASK-015 |
| UXV-11 | UXV- | DESIGN_SYSTEM · 3. Derived visual requirements | TASK-015 |
| UXV-12 | UXV- | DESIGN_SYSTEM · 3. Derived visual requirements | TASK-015 |
| UXV-13 | UXV- | DESIGN_SYSTEM · 3. Derived visual requirements | TASK-015 |
| UXV-14 | UXV- | DESIGN_SYSTEM · 3. Derived visual requirements | TASK-015 |
| UXV-15 | UXV- | DESIGN_SYSTEM · 3. Derived visual requirements | TASK-015 |
| UXV-16 | UXV- | DESIGN_SYSTEM · 3. Derived visual requirements | TASK-015 |
| UXV-17 | UXV- | DESIGN_SYSTEM · 3. Derived visual requirements | TASK-015 |
| UXV-18 | UXV- | DESIGN_SYSTEM · 3. Derived visual requirements | TASK-015 |
| UXV-19 | UXV- | DESIGN_SYSTEM · 3. Derived visual requirements | TASK-015 |
| UXV-20 | UXV- | DESIGN_SYSTEM · 3. Derived visual requirements | TASK-015 |
| UXV-21 | UXV- | DESIGN_SYSTEM · 3. Derived visual requirements | TASK-015 |
| UXV-22 | UXV- | DESIGN_SYSTEM · 3. Derived visual requirements | TASK-015 |
| D-DS-23 | D-DS- | DESIGN_SYSTEM · 4. Design decisions | TASK-015, TASK-029 |
| D-DS-18 | D-DS- | DESIGN_SYSTEM · 4. Design decisions | TASK-015 |
| D-DS-01 | D-DS- | DESIGN_SYSTEM · 4. Design decisions | TASK-015 |
| D-DS-02 | D-DS- | DESIGN_SYSTEM · 4. Design decisions | TASK-015 |
| D-DS-03 | D-DS- | DESIGN_SYSTEM · 4. Design decisions | TASK-015 |
| D-DS-04 | D-DS- | DESIGN_SYSTEM · 4. Design decisions | TASK-015 |
| D-DS-05 | D-DS- | DESIGN_SYSTEM · 4. Design decisions | TASK-015 |
| D-DS-06 | D-DS- | DESIGN_SYSTEM · 4. Design decisions | TASK-015 |
| D-DS-07 | D-DS- | DESIGN_SYSTEM · 4. Design decisions | TASK-015 |
| D-DS-08 | D-DS- | DESIGN_SYSTEM · 4. Design decisions | TASK-015, TASK-059 |
| D-DS-09 | D-DS- | DESIGN_SYSTEM · 4. Design decisions | TASK-015, TASK-024 |
| D-DS-10 | D-DS- | DESIGN_SYSTEM · 4. Design decisions | TASK-015 |
| D-DS-11 | D-DS- | DESIGN_SYSTEM · 4. Design decisions | TASK-015 |
| D-DS-12 | D-DS- | DESIGN_SYSTEM · 4. Design decisions | TASK-015 |
| D-DS-13 | D-DS- | DESIGN_SYSTEM · 4. Design decisions | TASK-015 |
| D-DS-14 | D-DS- | DESIGN_SYSTEM · 4. Design decisions | TASK-015 |
| D-DS-15 | D-DS- | DESIGN_SYSTEM · 4. Design decisions | TASK-015 |
| D-DS-16 | D-DS- | DESIGN_SYSTEM · 4. Design decisions | TASK-015 |
| D-DS-17 | D-DS- | DESIGN_SYSTEM · 4. Design decisions | TASK-015 |
| D-DS-19 | D-DS- | DESIGN_SYSTEM · 4. Design decisions | TASK-015 |
| D-DS-20 | D-DS- | DESIGN_SYSTEM · 4. Design decisions | TASK-015 |
| D-DS-21 | D-DS- | DESIGN_SYSTEM · 4. Design decisions | TASK-015 |
| D-DS-22 | D-DS- | DESIGN_SYSTEM · 4. Design decisions | TASK-015 |
| PT-19 | PT- | DESIGN_SYSTEM · 9.4 Actions, overlays and feedback | TASK-015 |
| PT-14 | PT- | DESIGN_SYSTEM · 9.4 Actions, overlays and feedback | TASK-015 |
| PT-10 | PT- | DESIGN_SYSTEM · 9.3 Forms and entry | TASK-015 |
| PT-20 | PT- | DESIGN_SYSTEM · 9.3 Forms and entry | TASK-015, TASK-059 |
| PT-30 | PT- | DESIGN_SYSTEM · 9.3 Forms and entry | TASK-015, TASK-024 |
| PT-09 | PT- | DESIGN_SYSTEM · 9.3 Forms and entry | TASK-015 |
| PT-01 | PT- | DESIGN_SYSTEM · 9.1 Shell, navigation and page structure | TASK-015, TASK-016 |
| PT-02 | PT- | DESIGN_SYSTEM · 9.1 Shell, navigation and page structure | TASK-015, TASK-016 |
| PT-03 | PT- | DESIGN_SYSTEM · 9.1 Shell, navigation and page structure | TASK-015 |
| PT-04 | PT- | DESIGN_SYSTEM · 9.1 Shell, navigation and page structure | TASK-015, TASK-016 |
| PT-13 | PT- | DESIGN_SYSTEM · 9.5 Dashboard, records and media | TASK-015 |
| PT-25 | PT- | DESIGN_SYSTEM · 9.3 Forms and entry | TASK-013, TASK-015 |
| PT-31 | PT- | DESIGN_SYSTEM · 9.1 Shell, navigation and page structure | TASK-015, TASK-032 |
| PT-12 | PT- | DESIGN_SYSTEM · 9.1 Shell, navigation and page structure | TASK-015 |
| PT-18 | PT- | DESIGN_SYSTEM · 9.4 Actions, overlays and feedback | TASK-015 |
| PT-05 | PT- | DESIGN_SYSTEM · 9.2 Lists, tables and filters | TASK-015 |
| PT-06 | PT- | DESIGN_SYSTEM · 9.2 Lists, tables and filters | TASK-015 |
| PT-07 | PT- | DESIGN_SYSTEM · 9.2 Lists, tables and filters | TASK-015 |
| PT-08 | PT- | DESIGN_SYSTEM · 9.2 Lists, tables and filters | TASK-015 |
| PT-27 | PT- | DESIGN_SYSTEM · 9.2 Lists, tables and filters | TASK-015, TASK-059 |
| PT-29 | PT- | DESIGN_SYSTEM · 9.3 Forms and entry | TASK-015, TASK-035 |
| PT-11 | PT- | DESIGN_SYSTEM · 9.4 Actions, overlays and feedback | TASK-015 |
| PT-16 | PT- | DESIGN_SYSTEM · 9.4 Actions, overlays and feedback | TASK-015 |
| PT-26 | PT- | DESIGN_SYSTEM · 9.4 Actions, overlays and feedback | TASK-015 |
| PT-28 | PT- | DESIGN_SYSTEM · 9.4 Actions, overlays and feedback | TASK-015, TASK-017 |
| PT-15 | PT- | DESIGN_SYSTEM · 9.4 Actions, overlays and feedback | TASK-015 |
| PT-21 | PT- | DESIGN_SYSTEM · 9.5 Dashboard, records and media | TASK-015, TASK-058 |
| PT-17 | PT- | DESIGN_SYSTEM · 9.5 Dashboard, records and media | TASK-015 |
| PT-24 | PT- | DESIGN_SYSTEM · 9.5 Dashboard, records and media | TASK-015 |
| PT-22 | PT- | DESIGN_SYSTEM · 9.5 Dashboard, records and media | TASK-015, TASK-022, TASK-029 |
| PT-23 | PT- | DESIGN_SYSTEM · 9.5 Dashboard, records and media | TASK-015 |
| KB-11 | KB- | DESIGN_SYSTEM · 13.1 Keyboard | TASK-015 |
| KB-09 | KB- | DESIGN_SYSTEM · 13.1 Keyboard | TASK-015 |
| KB-05 | KB- | DESIGN_SYSTEM · 13.1 Keyboard | TASK-015 |
| KB-08 | KB- | DESIGN_SYSTEM · 13.1 Keyboard | TASK-015 |
| KB-04 | KB- | DESIGN_SYSTEM · 13.1 Keyboard | TASK-015 |
| KB-10 | KB- | DESIGN_SYSTEM · 13.1 Keyboard | TASK-015 |
| KB-02 | KB- | DESIGN_SYSTEM · 13.1 Keyboard | TASK-015 |
| KB-01 | KB- | DESIGN_SYSTEM · 13.1 Keyboard | TASK-015 |
| KB-03 | KB- | DESIGN_SYSTEM · 13.1 Keyboard | TASK-015 |
| KB-06 | KB- | DESIGN_SYSTEM · 13.1 Keyboard | TASK-015 |
| KB-07 | KB- | DESIGN_SYSTEM · 13.1 Keyboard | TASK-015 |
| SLOP-37 | SLOP- | DESIGN_SYSTEM · 18. Anti AI-slop mapping | TASK-015, TASK-065 |
| SLOP-26 | SLOP- | DESIGN_SYSTEM · 18. Anti AI-slop mapping | TASK-015, TASK-065 |
| SLOP-05 | SLOP- | DESIGN_SYSTEM · 18. Anti AI-slop mapping | TASK-015, TASK-065 |
| SLOP-34 | SLOP- | DESIGN_SYSTEM · 18. Anti AI-slop mapping | TASK-015, TASK-065 |
| SLOP-18 | SLOP- | DESIGN_SYSTEM · 18. Anti AI-slop mapping | TASK-015, TASK-065 |
| SLOP-19 | SLOP- | DESIGN_SYSTEM · 18. Anti AI-slop mapping | TASK-015, TASK-065 |
| SLOP-09 | SLOP- | DESIGN_SYSTEM · 18. Anti AI-slop mapping | TASK-015, TASK-065 |
| SLOP-08 | SLOP- | DESIGN_SYSTEM · 18. Anti AI-slop mapping | TASK-015, TASK-065 |
| SLOP-44 | SLOP- | DESIGN_SYSTEM · 18. Anti AI-slop mapping | TASK-015, TASK-065 |
| SLOP-20 | SLOP- | DESIGN_SYSTEM · 18. Anti AI-slop mapping | TASK-015, TASK-065 |
| SLOP-33 | SLOP- | DESIGN_SYSTEM · 18. Anti AI-slop mapping | TASK-015, TASK-065 |
| SLOP-03 | SLOP- | DESIGN_SYSTEM · 18. Anti AI-slop mapping | TASK-015, TASK-065 |
| SLOP-31 | SLOP- | DESIGN_SYSTEM · 18. Anti AI-slop mapping | TASK-015, TASK-065 |
| SLOP-14 | SLOP- | DESIGN_SYSTEM · 18. Anti AI-slop mapping | TASK-015, TASK-065 |
| SLOP-01 | SLOP- | DESIGN_SYSTEM · 18. Anti AI-slop mapping | TASK-015, TASK-065 |
| SLOP-16 | SLOP- | DESIGN_SYSTEM · 18. Anti AI-slop mapping | TASK-015, TASK-065 |
| SLOP-27 | SLOP- | DESIGN_SYSTEM · 18. Anti AI-slop mapping | TASK-015, TASK-065 |
| SLOP-04 | SLOP- | DESIGN_SYSTEM · 18. Anti AI-slop mapping | TASK-015, TASK-065 |
| SLOP-10 | SLOP- | DESIGN_SYSTEM · 18. Anti AI-slop mapping | TASK-015, TASK-065 |
| SLOP-15 | SLOP- | DESIGN_SYSTEM · 18. Anti AI-slop mapping | TASK-015, TASK-065 |
| SLOP-35 | SLOP- | DESIGN_SYSTEM · 18. Anti AI-slop mapping | TASK-015, TASK-065 |
| SLOP-45 | SLOP- | DESIGN_SYSTEM · 18. Anti AI-slop mapping | TASK-015, TASK-065 |
| SLOP-17 | SLOP- | DESIGN_SYSTEM · 18. Anti AI-slop mapping | TASK-015, TASK-065 |
| SLOP-02 | SLOP- | DESIGN_SYSTEM · 18. Anti AI-slop mapping | TASK-015, TASK-065 |
| SLOP-06 | SLOP- | DESIGN_SYSTEM · 18. Anti AI-slop mapping | TASK-015, TASK-065 |
| SLOP-07 | SLOP- | DESIGN_SYSTEM · 18. Anti AI-slop mapping | TASK-015, TASK-065 |
| SLOP-11 | SLOP- | DESIGN_SYSTEM · 18. Anti AI-slop mapping | TASK-015, TASK-065 |
| SLOP-12 | SLOP- | DESIGN_SYSTEM · 18. Anti AI-slop mapping | TASK-015, TASK-065 |
| SLOP-13 | SLOP- | DESIGN_SYSTEM · 18. Anti AI-slop mapping | TASK-015, TASK-065 |
| SLOP-21 | SLOP- | DESIGN_SYSTEM · 18. Anti AI-slop mapping | TASK-015, TASK-065 |
| SLOP-22 | SLOP- | DESIGN_SYSTEM · 18. Anti AI-slop mapping | TASK-015, TASK-065 |
| SLOP-23 | SLOP- | DESIGN_SYSTEM · 18. Anti AI-slop mapping | TASK-015, TASK-065 |
| SLOP-24 | SLOP- | DESIGN_SYSTEM · 18. Anti AI-slop mapping | TASK-015, TASK-065 |
| SLOP-25 | SLOP- | DESIGN_SYSTEM · 18. Anti AI-slop mapping | TASK-015, TASK-065 |
| SLOP-28 | SLOP- | DESIGN_SYSTEM · 18. Anti AI-slop mapping | TASK-015, TASK-065 |
| SLOP-29 | SLOP- | DESIGN_SYSTEM · 18. Anti AI-slop mapping | TASK-015, TASK-065 |
| SLOP-30 | SLOP- | DESIGN_SYSTEM · 18. Anti AI-slop mapping | TASK-015, TASK-065 |
| SLOP-32 | SLOP- | DESIGN_SYSTEM · 18. Anti AI-slop mapping | TASK-015, TASK-065 |
| SLOP-36 | SLOP- | DESIGN_SYSTEM · 18. Anti AI-slop mapping | TASK-015, TASK-065 |
| SLOP-38 | SLOP- | DESIGN_SYSTEM · 18. Anti AI-slop mapping | TASK-015, TASK-065 |
| SLOP-39 | SLOP- | DESIGN_SYSTEM · 18. Anti AI-slop mapping | TASK-015, TASK-065 |
| SLOP-40 | SLOP- | DESIGN_SYSTEM · 18. Anti AI-slop mapping | TASK-015, TASK-065 |
| SLOP-41 | SLOP- | DESIGN_SYSTEM · 18. Anti AI-slop mapping | TASK-015, TASK-065 |
| SLOP-42 | SLOP- | DESIGN_SYSTEM · 18. Anti AI-slop mapping | TASK-015, TASK-065 |
| SLOP-43 | SLOP- | DESIGN_SYSTEM · 18. Anti AI-slop mapping | TASK-015, TASK-065 |
| DA-04 | DA- | DESIGN_SYSTEM · A.1 By category | TASK-015 |
| DA-12 | DA- | DESIGN_SYSTEM · A.1 By category | TASK-015 |
| DA-03 | DA- | DESIGN_SYSTEM · A.1 By category | TASK-015 |
| DA-01 | DA- | DESIGN_SYSTEM · A.1 By category | disposition DROPPED |
| DA-02 | DA- | DESIGN_SYSTEM · A.1 By category | disposition DROPPED |
| DA-05 | DA- | DESIGN_SYSTEM · A.1 By category | TASK-015, TASK-065 |
| DA-06 | DA- | DESIGN_SYSTEM · A.1 By category | TASK-015, TASK-029, TASK-059 |
| DA-07 | DA- | DESIGN_SYSTEM · A.1 By category | TASK-015, TASK-029, TASK-059 |
| DA-08 | DA- | DESIGN_SYSTEM · A.1 By category | TASK-015 |
| DA-09 | DA- | DESIGN_SYSTEM · A.1 By category | TASK-015 |
| DA-10 | DA- | DESIGN_SYSTEM · A.1 By category | disposition DROPPED |
| DA-11 | DA- | DESIGN_SYSTEM · A.1 By category | TASK-015 |
| DA-13 | DA- | DESIGN_SYSTEM · A.1 By category | disposition SATISFIED — documented in the ledger; no disposition needed (DESIGN_SYSTEM Annex A) |
| GR-16 | GR- | DESIGN_SYSTEM · 5. Tokens | TASK-015 |
| GR-07 | GR- | DESIGN_SYSTEM · Annex B. The carried items GR-01–GR-23 | TASK-015 |
| GR-10 | GR- | DESIGN_SYSTEM · Annex B. The carried items GR-01–GR-23 | TASK-029 |
| GR-06 | GR- | DESIGN_SYSTEM · Annex B. The carried items GR-01–GR-23 | TASK-015 |
| GR-01 | GR- | DESIGN_SYSTEM · Annex B. The carried items GR-01–GR-23 | TASK-015 |
| GR-23 | GR- | DESIGN_SYSTEM · Annex B. The carried items GR-01–GR-23 | TASK-058, TASK-060 |
| GR-13 | GR- | DESIGN_SYSTEM · Annex B. The carried items GR-01–GR-23 | TASK-019, TASK-082 |
| GR-04 | GR- | DESIGN_SYSTEM · Annex B. The carried items GR-01–GR-23 | TASK-002, TASK-015 |
| GR-03 | GR- | DESIGN_SYSTEM · Annex B. The carried items GR-01–GR-23 | TASK-032 |
| GR-02 | GR- | DESIGN_SYSTEM · Annex B. The carried items GR-01–GR-23 | TASK-016 |
| GR-12 | GR- | DESIGN_SYSTEM · Annex B. The carried items GR-01–GR-23 | TASK-017, TASK-018 |
| GR-18 | GR- | DESIGN_SYSTEM · Annex B. The carried items GR-01–GR-23 | TASK-015 |
| GR-22 | GR- | DESIGN_SYSTEM · Annex B. The carried items GR-01–GR-23 | TASK-016 |
| GR-05 | GR- | DESIGN_SYSTEM · Annex B. The carried items GR-01–GR-23 | TASK-015, TASK-066 |
| GR-11 | GR- | DESIGN_SYSTEM · Annex B. The carried items GR-01–GR-23 | TASK-016 |
| GR-15 | GR- | DESIGN_SYSTEM · Annex B. The carried items GR-01–GR-23 | TASK-015 |
| GR-08 | GR- | DESIGN_SYSTEM · Annex B. The carried items GR-01–GR-23 | TASK-015 |
| GR-20 | GR- | DESIGN_SYSTEM · Annex B. The carried items GR-01–GR-23 | TASK-015 |
| GR-09 | GR- | DESIGN_SYSTEM · Annex B. The carried items GR-01–GR-23 | TASK-015 |
| GR-19 | GR- | DESIGN_SYSTEM · Annex B. The carried items GR-01–GR-23 | TASK-015, TASK-060 |
| GR-21 | GR- | DESIGN_SYSTEM · Annex B. The carried items GR-01–GR-23 | TASK-015 |
| GR-14 | GR- | DESIGN_SYSTEM · Annex B. The carried items GR-01–GR-23 | TASK-016 |
| GR-17 | GR- | DESIGN_SYSTEM · Annex B. The carried items GR-01–GR-23 | TASK-014 |
| NAV-014 | NAV- | NAVIGATION_CONTRACTS · 2.2 Projects and quotations | TASK-033 |
| NAV-033 | NAV- | NAVIGATION_CONTRACTS · 2.4 Fulfilment, cases and documents | TASK-047 |
| NAV-036 | NAV- | NAVIGATION_CONTRACTS · 2.4 Fulfilment, cases and documents | TASK-029 |
| NAV-042 | NAV- | NAVIGATION_CONTRACTS · 2.5 Finance | TASK-049 |
| NAV-043 | NAV- | NAVIGATION_CONTRACTS · 2.5 Finance | TASK-049 |
| NAV-016 | NAV- | NAVIGATION_CONTRACTS · 2.3 Purchasing and warehouse | TASK-037 |
| NAV-022 | NAV- | NAVIGATION_CONTRACTS · 2.3 Purchasing and warehouse | TASK-039 |
| NAV-024 | NAV- | NAVIGATION_CONTRACTS · 2.3 Purchasing and warehouse | TASK-041 |
| NAV-031 | NAV- | NAVIGATION_CONTRACTS · 2.4 Fulfilment, cases and documents | TASK-045 |
| NAV-001 | NAV- | NAVIGATION_CONTRACTS · 2.1 System, account and Beranda | TASK-017 |
| NAV-002 | NAV- | NAVIGATION_CONTRACTS · 2.1 System, account and Beranda | TASK-018 |
| NAV-003 | NAV- | NAVIGATION_CONTRACTS · 2.1 System, account and Beranda | TASK-017 |
| NAV-004 | NAV- | NAVIGATION_CONTRACTS · 2.1 System, account and Beranda | TASK-018 |
| NAV-005 | NAV- | NAVIGATION_CONTRACTS · 2.1 System, account and Beranda | TASK-016 |
| NAV-006 | NAV- | NAVIGATION_CONTRACTS · 2.1 System, account and Beranda | TASK-058 |
| NAV-007 | NAV- | NAVIGATION_CONTRACTS · 2.1 System, account and Beranda | TASK-058 |
| NAV-008 | NAV- | NAVIGATION_CONTRACTS · 2.1 System, account and Beranda | TASK-058 |
| NAV-009 | NAV- | NAVIGATION_CONTRACTS · 2.1 System, account and Beranda | TASK-059 |
| NAV-065 | NAV- | NAVIGATION_CONTRACTS · 2.1 System, account and Beranda | TASK-023 |
| NAV-010 | NAV- | NAVIGATION_CONTRACTS · 2.2 Projects and quotations | TASK-032 |
| NAV-011 | NAV- | NAVIGATION_CONTRACTS · 2.2 Projects and quotations | TASK-032 |
| NAV-012 | NAV- | NAVIGATION_CONTRACTS · 2.2 Projects and quotations | TASK-055 |
| NAV-013 | NAV- | NAVIGATION_CONTRACTS · 2.2 Projects and quotations | TASK-032 |
| NAV-037 | NAV- | NAVIGATION_CONTRACTS · 2.2 Projects and quotations | TASK-033 |
| NAV-015 | NAV- | NAVIGATION_CONTRACTS · 2.3 Purchasing and warehouse | TASK-037 |
| NAV-017 | NAV- | NAVIGATION_CONTRACTS · 2.3 Purchasing and warehouse | TASK-037 |
| NAV-018 | NAV- | NAVIGATION_CONTRACTS · 2.3 Purchasing and warehouse | TASK-040 |
| NAV-019 | NAV- | NAVIGATION_CONTRACTS · 2.3 Purchasing and warehouse | TASK-040 |
| NAV-020 | NAV- | NAVIGATION_CONTRACTS · 2.3 Purchasing and warehouse | TASK-040 |
| NAV-021 | NAV- | NAVIGATION_CONTRACTS · 2.3 Purchasing and warehouse | TASK-039 |
| NAV-023 | NAV- | NAVIGATION_CONTRACTS · 2.3 Purchasing and warehouse | TASK-041 |
| NAV-025 | NAV- | NAVIGATION_CONTRACTS · 2.3 Purchasing and warehouse | TASK-038 |
| NAV-026 | NAV- | NAVIGATION_CONTRACTS · 2.3 Purchasing and warehouse | TASK-043 |
| NAV-027 | NAV- | NAVIGATION_CONTRACTS · 2.3 Purchasing and warehouse | TASK-042 |
| NAV-028 | NAV- | NAVIGATION_CONTRACTS · 2.3 Purchasing and warehouse | TASK-044 |
| NAV-029 | NAV- | NAVIGATION_CONTRACTS · 2.3 Purchasing and warehouse | TASK-043 |
| NAV-030 | NAV- | NAVIGATION_CONTRACTS · 2.4 Fulfilment, cases and documents | TASK-045 |
| NAV-032 | NAV- | NAVIGATION_CONTRACTS · 2.4 Fulfilment, cases and documents | TASK-046 |
| NAV-034 | NAV- | NAVIGATION_CONTRACTS · 2.4 Fulfilment, cases and documents | TASK-048 |
| NAV-035 | NAV- | NAVIGATION_CONTRACTS · 2.4 Fulfilment, cases and documents | TASK-029 |
| NAV-038 | NAV- | NAVIGATION_CONTRACTS · 2.4 Fulfilment, cases and documents | TASK-030 |
| NAV-039 | NAV- | NAVIGATION_CONTRACTS · 2.4 Fulfilment, cases and documents | TASK-035 |
| NAV-040 | NAV- | NAVIGATION_CONTRACTS · 2.4 Fulfilment, cases and documents | TASK-031 |
| NAV-041 | NAV- | NAVIGATION_CONTRACTS · 2.5 Finance | TASK-049 |
| NAV-044 | NAV- | NAVIGATION_CONTRACTS · 2.5 Finance | TASK-050 |
| NAV-045 | NAV- | NAVIGATION_CONTRACTS · 2.5 Finance | TASK-051 |
| NAV-046 | NAV- | NAVIGATION_CONTRACTS · 2.5 Finance | TASK-051 |
| NAV-047 | NAV- | NAVIGATION_CONTRACTS · 2.5 Finance | TASK-052 |
| NAV-048 | NAV- | NAVIGATION_CONTRACTS · 2.5 Finance | TASK-053 |
| NAV-049 | NAV- | NAVIGATION_CONTRACTS · 2.5 Finance | TASK-054 |
| NAV-050 | NAV- | NAVIGATION_CONTRACTS · 2.5 Finance | TASK-051 |
| NAV-051 | NAV- | NAVIGATION_CONTRACTS · 2.6 Reports, master data, settings and Owner areas | TASK-060 |
| NAV-052 | NAV- | NAVIGATION_CONTRACTS · 2.6 Reports, master data, settings and Owner areas | TASK-060 |
| NAV-053 | NAV- | NAVIGATION_CONTRACTS · 2.6 Reports, master data, settings and Owner areas | TASK-060 |
| NAV-054 | NAV- | NAVIGATION_CONTRACTS · 2.6 Reports, master data, settings and Owner areas | TASK-026 |
| NAV-055 | NAV- | NAVIGATION_CONTRACTS · 2.6 Reports, master data, settings and Owner areas | TASK-026 |
| NAV-056 | NAV- | NAVIGATION_CONTRACTS · 2.6 Reports, master data, settings and Owner areas | TASK-027 |
| NAV-057 | NAV- | NAVIGATION_CONTRACTS · 2.6 Reports, master data, settings and Owner areas | TASK-028 |
| NAV-058 | NAV- | NAVIGATION_CONTRACTS · 2.6 Reports, master data, settings and Owner areas | TASK-057 |
| NAV-059 | NAV- | NAVIGATION_CONTRACTS · 2.6 Reports, master data, settings and Owner areas | TASK-025 |
| NAV-060 | NAV- | NAVIGATION_CONTRACTS · 2.6 Reports, master data, settings and Owner areas | TASK-024 |
| NAV-061 | NAV- | NAVIGATION_CONTRACTS · 2.6 Reports, master data, settings and Owner areas | TASK-021 |
| NAV-062 | NAV- | NAVIGATION_CONTRACTS · 2.6 Reports, master data, settings and Owner areas | TASK-025 |
| NAV-063 | NAV- | NAVIGATION_CONTRACTS · 2.6 Reports, master data, settings and Owner areas | TASK-061 |
| NAV-064 | NAV- | NAVIGATION_CONTRACTS · 2.6 Reports, master data, settings and Owner areas | TASK-061 |
| NV-01 | NV- | NAVIGATION_CONTRACTS · 1. Conventions | TASK-016 |
| NV-02 | NV- | NAVIGATION_CONTRACTS · 1. Conventions | TASK-016 |
| NV-03 | NV- | NAVIGATION_CONTRACTS · 1. Conventions | TASK-016 |
| NV-04 | NV- | NAVIGATION_CONTRACTS · 1. Conventions | TASK-016 |
| NV-05 | NV- | NAVIGATION_CONTRACTS · 1. Conventions | TASK-016 |
| NV-06 | NV- | NAVIGATION_CONTRACTS · 1. Conventions | TASK-016 |
| NV-07 | NV- | NAVIGATION_CONTRACTS · 1. Conventions | TASK-016 |
| NV-08 | NV- | NAVIGATION_CONTRACTS · 1. Conventions | TASK-016 |
| TS-01 | TS- | TEST_STRATEGY · 1. Principles | TASK-003, TASK-070 |
| TS-02 | TS- | TEST_STRATEGY · 1. Principles | TASK-003, TASK-070 |
| TS-03 | TS- | TEST_STRATEGY · 1. Principles | TASK-003, TASK-070 |
| TS-04 | TS- | TEST_STRATEGY · 1. Principles | TASK-003, TASK-070 |
| TS-05 | TS- | TEST_STRATEGY · 1. Principles | TASK-003, TASK-070 |
| TS-06 | TS- | TEST_STRATEGY · 1. Principles | TASK-003, TASK-070 |
| TS-07 | TS- | TEST_STRATEGY · 1. Principles | TASK-003, TASK-070 |
| TS-08 | TS- | TEST_STRATEGY · 1. Principles | TASK-003, TASK-070 |
| TS-09 | TS- | TEST_STRATEGY · 1. Principles | TASK-003, TASK-070 |
| TS-10 | TS- | TEST_STRATEGY · 1. Principles | TASK-001, TASK-003, TASK-070 |
| TS-11 | TS- | TEST_STRATEGY · 1. Principles | TASK-003, TASK-070 |
| TS-12 | TS- | TEST_STRATEGY · 1. Principles | TASK-003, TASK-070 |
| TL-01 | TL- | TEST_STRATEGY · 2. Test layers | TASK-002, TASK-003 |
| TL-02 | TL- | TEST_STRATEGY · 2. Test layers | TASK-002, TASK-003 |
| TL-03 | TL- | TEST_STRATEGY · 2. Test layers | TASK-003 |
| TL-04 | TL- | TEST_STRATEGY · 2. Test layers | TASK-003 |
| TL-05 | TL- | TEST_STRATEGY · 2. Test layers | TASK-003 |
| TL-06 | TL- | TEST_STRATEGY · 2. Test layers | TASK-003, TASK-069 |
| TL-07 | TL- | TEST_STRATEGY · 2. Test layers | TASK-003, TASK-069 |
| TL-08 | TL- | TEST_STRATEGY · 2. Test layers | TASK-003, TASK-069 |
| TL-09 | TL- | TEST_STRATEGY · 2. Test layers | TASK-003, TASK-062, TASK-063, TASK-064 |
| TL-10 | TL- | TEST_STRATEGY · 2. Test layers | TASK-003, TASK-067 |
| TL-11 | TL- | TEST_STRATEGY · 2. Test layers | TASK-003, TASK-065 |
| TL-12 | TL- | TEST_STRATEGY · 2. Test layers | TASK-003, TASK-066 |
| TL-13 | TL- | TEST_STRATEGY · 2. Test layers | TASK-003 |
| TL-14 | TL- | TEST_STRATEGY · 2. Test layers | TASK-003, TASK-069 |
| TL-15 | TL- | TEST_STRATEGY · 2. Test layers | TASK-003, TASK-068 |
| TL-16 | TL- | TEST_STRATEGY · 2. Test layers | TASK-003, TASK-005 |
| CJ-01 | CJ- | TEST_STRATEGY · 7. Browser end-to-end tests and critical journeys | TASK-062 |
| CJ-22 | CJ- | TEST_STRATEGY · 7. Browser end-to-end tests and critical journeys | TASK-064 |
| CJ-10 | CJ- | TEST_STRATEGY · 7. Browser end-to-end tests and critical journeys | TASK-064 |
| CJ-19 | CJ- | TEST_STRATEGY · 7. Browser end-to-end tests and critical journeys | TASK-064 |
| CJ-12 | CJ- | TEST_STRATEGY · 7. Browser end-to-end tests and critical journeys | TASK-064 |
| CJ-13 | CJ- | TEST_STRATEGY · 7. Browser end-to-end tests and critical journeys | TASK-064 |
| CJ-09 | CJ- | TEST_STRATEGY · 7. Browser end-to-end tests and critical journeys | TASK-064 |
| CJ-11 | CJ- | TEST_STRATEGY · 7. Browser end-to-end tests and critical journeys | TASK-064 |
| CJ-04 | CJ- | TEST_STRATEGY · 7. Browser end-to-end tests and critical journeys | TASK-063 |
| CJ-14 | CJ- | TEST_STRATEGY · 7. Browser end-to-end tests and critical journeys | TASK-063 |
| CJ-16 | CJ- | TEST_STRATEGY · 7. Browser end-to-end tests and critical journeys | TASK-064, TASK-081 |
| CJ-15 | CJ- | TEST_STRATEGY · 7. Browser end-to-end tests and critical journeys | TASK-063 |
| CJ-07 | CJ- | TEST_STRATEGY · 7. Browser end-to-end tests and critical journeys | TASK-062 |
| CJ-02 | CJ- | TEST_STRATEGY · 7. Browser end-to-end tests and critical journeys | TASK-062 |
| CJ-05 | CJ- | TEST_STRATEGY · 7. Browser end-to-end tests and critical journeys | TASK-062 |
| CJ-06 | CJ- | TEST_STRATEGY · 7. Browser end-to-end tests and critical journeys | TASK-063 |
| CJ-03 | CJ- | TEST_STRATEGY · 7. Browser end-to-end tests and critical journeys | TASK-062 |
| CJ-20 | CJ- | TEST_STRATEGY · 7. Browser end-to-end tests and critical journeys | TASK-063 |
| CJ-18 | CJ- | TEST_STRATEGY · 7. Browser end-to-end tests and critical journeys | TASK-064 |
| CJ-08 | CJ- | TEST_STRATEGY · 7. Browser end-to-end tests and critical journeys | TASK-062 |
| CJ-21 | CJ- | TEST_STRATEGY · 7. Browser end-to-end tests and critical journeys | TASK-064 |
| CJ-17 | CJ- | TEST_STRATEGY · 7. Browser end-to-end tests and critical journeys | TASK-063 |
| FX-01 | FX- | TEST_STRATEGY · 4. Environments and data | TASK-003 |
| FX-02 | FX- | TEST_STRATEGY · 4. Environments and data | TASK-003 |
| FX-03 | FX- | TEST_STRATEGY · 4. Environments and data | TASK-003 |
| FX-04 | FX- | TEST_STRATEGY · 4. Environments and data | TASK-003 |
| FX-05 | FX- | TEST_STRATEGY · 4. Environments and data | TASK-003 |
| FX-06 | FX- | TEST_STRATEGY · 4. Environments and data | TASK-003 |
| FX-07 | FX- | TEST_STRATEGY · 4. Environments and data | TASK-003 |
| FX-08 | FX- | TEST_STRATEGY · 4. Environments and data | TASK-003 |
| FX-09 | FX- | TEST_STRATEGY · 4. Environments and data | TASK-003 |
| FX-10 | FX- | TEST_STRATEGY · 4. Environments and data | TASK-003 |
| SM-21 | SM- | SECURITY_TEST_MATRIX · 3. The matrix | TASK-018, TASK-020, TASK-069 |
| SM-05 | SM- | SECURITY_TEST_MATRIX · 3. The matrix | TASK-011, TASK-069 |
| SM-25 | SM- | SECURITY_TEST_MATRIX · 3. The matrix | TASK-012, TASK-069 |
| SM-15 | SM- | SECURITY_TEST_MATRIX · 3. The matrix | TASK-001, TASK-003, TASK-069 |
| SM-01 | SM- | SECURITY_TEST_MATRIX · 3. The matrix | TASK-011, TASK-069 |
| SM-22 | SM- | SECURITY_TEST_MATRIX · 3. The matrix | TASK-069 |
| SM-23 | SM- | SECURITY_TEST_MATRIX · 3. The matrix | TASK-069 |
| SM-02 | SM- | SECURITY_TEST_MATRIX · 3. The matrix | TASK-011, TASK-018, TASK-069 |
| SM-03 | SM- | SECURITY_TEST_MATRIX · 3. The matrix | TASK-011, TASK-069 |
| SM-04 | SM- | SECURITY_TEST_MATRIX · 3. The matrix | TASK-011, TASK-069 |
| SM-06 | SM- | SECURITY_TEST_MATRIX · 3. The matrix | TASK-017, TASK-069 |
| SM-07 | SM- | SECURITY_TEST_MATRIX · 3. The matrix | TASK-069 |
| SM-08 | SM- | SECURITY_TEST_MATRIX · 3. The matrix | TASK-001, TASK-017, TASK-069 |
| SM-09 | SM- | SECURITY_TEST_MATRIX · 3. The matrix | TASK-022, TASK-030, TASK-069 |
| SM-10 | SM- | SECURITY_TEST_MATRIX · 3. The matrix | TASK-069 |
| SM-11 | SM- | SECURITY_TEST_MATRIX · 3. The matrix | TASK-069 |
| SM-12 | SM- | SECURITY_TEST_MATRIX · 3. The matrix | TASK-069 |
| SM-13 | SM- | SECURITY_TEST_MATRIX · 3. The matrix | TASK-069 |
| SM-14 | SM- | SECURITY_TEST_MATRIX · 3. The matrix | TASK-069 |
| SM-16 | SM- | SECURITY_TEST_MATRIX · 3. The matrix | TASK-017, TASK-069 |
| SM-17 | SM- | SECURITY_TEST_MATRIX · 3. The matrix | TASK-017, TASK-018, TASK-069 |
| SM-18 | SM- | SECURITY_TEST_MATRIX · 3. The matrix | TASK-018, TASK-023, TASK-069 |
| SM-19 | SM- | SECURITY_TEST_MATRIX · 3. The matrix | TASK-018, TASK-019, TASK-020, TASK-023, TASK-024, TASK-069, TASK-076 |
| SM-20 | SM- | SECURITY_TEST_MATRIX · 3. The matrix | TASK-019, TASK-023, TASK-024, TASK-069 |
| SM-24 | SM- | SECURITY_TEST_MATRIX · 3. The matrix | TASK-020, TASK-069 |
| PFM-01 | PFM- | PERFORMANCE_TARGETS · 3. The measured targets | TASK-068, TASK-079 |
| PFM-02 | PFM- | PERFORMANCE_TARGETS · 3. The measured targets | TASK-068, TASK-079 |
| PFM-03 | PFM- | PERFORMANCE_TARGETS · 3. The measured targets | TASK-068 |
| PFM-04 | PFM- | PERFORMANCE_TARGETS · 3. The measured targets | TASK-032, TASK-068 |
| PFM-05 | PFM- | PERFORMANCE_TARGETS · 3. The measured targets | TASK-068 |
| PFM-06 | PFM- | PERFORMANCE_TARGETS · 3. The measured targets | TASK-068 |
| PFM-07 | PFM- | PERFORMANCE_TARGETS · 3. The measured targets | TASK-068 |
| PFM-08 | PFM- | PERFORMANCE_TARGETS · 3. The measured targets | TASK-068 |
| PFM-09 | PFM- | PERFORMANCE_TARGETS · 3. The measured targets | TASK-068 |
| PFM-10 | PFM- | PERFORMANCE_TARGETS · 3. The measured targets | TASK-068 |
| PFM-11 | PFM- | PERFORMANCE_TARGETS · 3. The measured targets | TASK-068 |
| PFM-12 | PFM- | PERFORMANCE_TARGETS · 3. The measured targets | TASK-068, TASK-079 |
| PFM-13 | PFM- | PERFORMANCE_TARGETS · 3. The measured targets | TASK-068, TASK-079 |
| PFM-14 | PFM- | PERFORMANCE_TARGETS · 3. The measured targets | TASK-068 |
| PFM-15 | PFM- | PERFORMANCE_TARGETS · 3. The measured targets | TASK-068 |
| PFM-16 | PFM- | PERFORMANCE_TARGETS · 3. The measured targets | TASK-068 |
| PFM-17 | PFM- | PERFORMANCE_TARGETS · 3. The measured targets | TASK-068, TASK-079 |
| PFM-18 | PFM- | PERFORMANCE_TARGETS · 3. The measured targets | TASK-068 |
| PFM-19 | PFM- | PERFORMANCE_TARGETS · 3. The measured targets | TASK-068 |
| PFM-20 | PFM- | PERFORMANCE_TARGETS · 3. The measured targets | TASK-068 |
| DOD-03 | DOD- | DEFINITION_OF_DONE · 2. The checklist | disposition GOVERNANCE |
| DOD-01 | DOD- | DEFINITION_OF_DONE · 2. The checklist | disposition GOVERNANCE |
| DOD-02 | DOD- | DEFINITION_OF_DONE · 2. The checklist | disposition GOVERNANCE |
| DOD-04 | DOD- | DEFINITION_OF_DONE · 2. The checklist | disposition GOVERNANCE |
| DOD-05 | DOD- | DEFINITION_OF_DONE · 2. The checklist | disposition GOVERNANCE |
| DOD-06 | DOD- | DEFINITION_OF_DONE · 2. The checklist | disposition GOVERNANCE |
| DOD-07 | DOD- | DEFINITION_OF_DONE · 2. The checklist | disposition GOVERNANCE |
| DOD-08 | DOD- | DEFINITION_OF_DONE · 2. The checklist | disposition GOVERNANCE |
| DOD-09 | DOD- | DEFINITION_OF_DONE · 2. The checklist | disposition GOVERNANCE |
| DOD-10 | DOD- | DEFINITION_OF_DONE · 2. The checklist | disposition GOVERNANCE |
| DOD-11 | DOD- | DEFINITION_OF_DONE · 2. The checklist | disposition GOVERNANCE |
| DOD-12 | DOD- | DEFINITION_OF_DONE · 2. The checklist | disposition GOVERNANCE |
| DOD-13 | DOD- | DEFINITION_OF_DONE · 2. The checklist | disposition GOVERNANCE |
| DOD-14 | DOD- | DEFINITION_OF_DONE · 2. The checklist | disposition GOVERNANCE |
| DOD-15 | DOD- | DEFINITION_OF_DONE · 2. The checklist | disposition GOVERNANCE |
| DOD-16 | DOD- | DEFINITION_OF_DONE · 2. The checklist | disposition GOVERNANCE |
| DOD-17 | DOD- | DEFINITION_OF_DONE · 2. The checklist | disposition GOVERNANCE |
| DOD-18 | DOD- | DEFINITION_OF_DONE · 2. The checklist | disposition GOVERNANCE |
| DOD-19 | DOD- | DEFINITION_OF_DONE · 2. The checklist | disposition GOVERNANCE |
| DOD-20 | DOD- | DEFINITION_OF_DONE · 2. The checklist | disposition GOVERNANCE |
| DOD-21 | DOD- | DEFINITION_OF_DONE · 2. The checklist | disposition GOVERNANCE |
| DOD-22 | DOD- | DEFINITION_OF_DONE · 2. The checklist | disposition GOVERNANCE |
| INF-06 | INF- | INFRASTRUCTURE · 1. Invariants | TASK-074, TASK-084 |
| INF-07 | INF- | INFRASTRUCTURE · 1. Invariants | TASK-072, TASK-078 |
| INF-01 | INF- | INFRASTRUCTURE · 1. Invariants | TASK-071, TASK-078, TASK-084, TASK-085, TASK-087 |
| INF-02 | INF- | INFRASTRUCTURE · 1. Invariants | TASK-071, TASK-083 |
| INF-03 | INF- | INFRASTRUCTURE · 1. Invariants | TASK-073, TASK-087 |
| INF-04 | INF- | INFRASTRUCTURE · 1. Invariants | TASK-004, TASK-075 |
| INF-05 | INF- | INFRASTRUCTURE · 1. Invariants | TASK-002, TASK-003 |
| INF-08 | INF- | INFRASTRUCTURE · 1. Invariants | TASK-078, TASK-084 |
| OPI-03 | OPI- | INFRASTRUCTURE · 9. The operator's inputs | TASK-071 |
| OPI-27 | OPI- | INFRASTRUCTURE · 9. The operator's inputs | TASK-071 |
| OPI-29 | OPI- | INFRASTRUCTURE · 9. The operator's inputs | TASK-071, TASK-084 |
| OPI-04 | OPI- | INFRASTRUCTURE · 9. The operator's inputs | TASK-084 |
| OPI-26 | OPI- | INFRASTRUCTURE · 9. The operator's inputs | TASK-071 |
| OPI-07 | OPI- | INFRASTRUCTURE · 9. The operator's inputs | TASK-079 |
| OPI-09 | OPI- | INFRASTRUCTURE · 9. The operator's inputs | TASK-085 |
| OPI-02 | OPI- | INFRASTRUCTURE · 9. The operator's inputs | TASK-071 |
| OPI-05 | OPI- | INFRASTRUCTURE · 9. The operator's inputs | TASK-071, TASK-080 |
| OPI-01 | OPI- | INFRASTRUCTURE · 9. The operator's inputs | TASK-083 |
| OPI-06 | OPI- | INFRASTRUCTURE · 9. The operator's inputs | TASK-082 |
| OPI-08 | OPI- | INFRASTRUCTURE · 9. The operator's inputs | TASK-084 |
| OPI-28 | OPI- | INFRASTRUCTURE · 9. The operator's inputs | TASK-084 |
| OPI-30 | OPI- | INFRASTRUCTURE · 9. The operator's inputs | TASK-071 |
| OPI-31 | OPI- | INFRASTRUCTURE · 9. The operator's inputs | TASK-084 |
| OPI-32 | OPI- | BACKUP_RECOVERY · 7. The operator's inputs | TASK-084 |
| OPI-25 | OPI- | BACKUP_RECOVERY · 7. The operator's inputs | TASK-079 |
| OPI-21 | OPI- | BACKUP_RECOVERY · 7. The operator's inputs | TASK-071, TASK-079 |
| OPI-22 | OPI- | BACKUP_RECOVERY · 7. The operator's inputs | TASK-071 |
| OPI-23 | OPI- | BACKUP_RECOVERY · 7. The operator's inputs | TASK-084 |
| OPI-24 | OPI- | BACKUP_RECOVERY · 7. The operator's inputs | TASK-083 |
| BKR-09 | BKR- | BACKUP_RECOVERY · 1. The decision and its limits | TASK-074, TASK-081, TASK-084 |
| BKR-01 | BKR- | BACKUP_RECOVERY · 1. The decision and its limits | TASK-074 |
| BKR-02 | BKR- | BACKUP_RECOVERY · 1. The decision and its limits | TASK-074 |
| BKR-03 | BKR- | BACKUP_RECOVERY · 1. The decision and its limits | TASK-074 |
| BKR-04 | BKR- | BACKUP_RECOVERY · 1. The decision and its limits | TASK-074 |
| BKR-05 | BKR- | BACKUP_RECOVERY · 1. The decision and its limits | TASK-074 |
| BKR-06 | BKR- | BACKUP_RECOVERY · 1. The decision and its limits | TASK-074 |
| BKR-07 | BKR- | BACKUP_RECOVERY · 1. The decision and its limits | TASK-074 |
| BKR-08 | BKR- | BACKUP_RECOVERY · 1. The decision and its limits | TASK-074, TASK-076 |
| TT-F21 | TT-F | BACKUP_RECOVERY · The exercise held in PC-4 | TASK-074, TASK-076, TASK-081 |
| TT-F15 | TT-F | BACKUP_RECOVERY · The exercise held in PC-4 | TASK-076, TASK-083 |
| TT-F17 | TT-F | BACKUP_RECOVERY · The exercise held in PC-4 | TASK-076, TASK-083 |
| TT-F19 | TT-F | BACKUP_RECOVERY · The exercise held in PC-4 | TASK-076 |
| TT-F20 | TT-F | BACKUP_RECOVERY · The exercise held in PC-4 | TASK-076, TASK-081, TASK-084 |
| TT-F26 | TT-F | BACKUP_RECOVERY · The exercise held in PC-4 | TASK-076, TASK-085 |
| TT-F29 | TT-F | BACKUP_RECOVERY · The exercise held in PC-4 | TASK-076, TASK-085 |
| TT-F01 | TT-F | BACKUP_RECOVERY · The exercise held in PC-4 | TASK-071, TASK-076, TASK-080, TASK-081, TASK-087 |
| TT-F09 | TT-F | BACKUP_RECOVERY · The exercise held in PC-4 | TASK-076 |
| TT-F13 | TT-F | BACKUP_RECOVERY · The exercise held in PC-4 | TASK-076 |
| TT-F02 | TT-F | BACKUP_RECOVERY · The exercise held in PC-4 | TASK-076 |
| TT-F06 | TT-F | BACKUP_RECOVERY · The exercise held in PC-4 | TASK-076 |
| TT-F14 | TT-F | BACKUP_RECOVERY · The exercise held in PC-4 | TASK-076 |
| TT-F16 | TT-F | BACKUP_RECOVERY · The exercise held in PC-4 | TASK-076, TASK-083 |
| TT-F18 | TT-F | BACKUP_RECOVERY · The exercise held in PC-4 | TASK-076 |
| TT-F25 | TT-F | BACKUP_RECOVERY · The exercise held in PC-4 | TASK-020, TASK-076, TASK-083 |
| TT-F03 | TT-F | BACKUP_RECOVERY · The exercise held in PC-4 | TASK-076, TASK-084, TASK-087 |
| TT-F04 | TT-F | BACKUP_RECOVERY · The exercise held in PC-4 | TASK-076, TASK-085, TASK-087 |
| TT-F05 | TT-F | BACKUP_RECOVERY · The exercise held in PC-4 | TASK-076, TASK-085 |
| TT-F07 | TT-F | BACKUP_RECOVERY · The exercise held in PC-4 | TASK-076 |
| TT-F08 | TT-F | BACKUP_RECOVERY · The exercise held in PC-4 | TASK-076 |
| TT-F10 | TT-F | BACKUP_RECOVERY · The exercise held in PC-4 | TASK-072, TASK-076 |
| TT-F11 | TT-F | BACKUP_RECOVERY · The exercise held in PC-4 | TASK-076 |
| TT-F12 | TT-F | BACKUP_RECOVERY · The exercise held in PC-4 | TASK-076, TASK-083, TASK-084, TASK-087 |
| TT-F22 | TT-F | BACKUP_RECOVERY · The exercise held in PC-4 | TASK-076 |
| TT-F23 | TT-F | BACKUP_RECOVERY · The exercise held in PC-4 | TASK-076 |
| TT-F24 | TT-F | BACKUP_RECOVERY · The exercise held in PC-4 | TASK-074, TASK-076, TASK-081 |
| TT-F27 | TT-F | BACKUP_RECOVERY · The exercise held in PC-4 | TASK-076, TASK-083 |
| TT-F28 | TT-F | BACKUP_RECOVERY · The exercise held in PC-4 | TASK-076 |
| TT-F30 | TT-F | BACKUP_RECOVERY · The exercise held in PC-4 | TASK-020, TASK-069, TASK-076 |
| RL-01 | RL- | RELEASE_PLAN · 1. Principles | TASK-073 |
| RL-02 | RL- | RELEASE_PLAN · 1. Principles | TASK-073 |
| RL-03 | RL- | RELEASE_PLAN · 1. Principles | TASK-073 |
| RL-04 | RL- | RELEASE_PLAN · 1. Principles | TASK-073 |
| RL-05 | RL- | RELEASE_PLAN · 1. Principles | TASK-073 |
| RL-06 | RL- | RELEASE_PLAN · 1. Principles | TASK-073 |
| RL-07 | RL- | RELEASE_PLAN · 1. Principles | TASK-073 |
| RL-08 | RL- | RELEASE_PLAN · 1. Principles | TASK-073 |
| REL-01 | REL- | RELEASE_PLAN · 3. The release sequence | TASK-072, TASK-073 |
| REL-02 | REL- | RELEASE_PLAN · 3. The release sequence | TASK-072, TASK-073 |
| REL-03 | REL- | RELEASE_PLAN · 3. The release sequence | TASK-072, TASK-073 |
| REL-04 | REL- | RELEASE_PLAN · 3. The release sequence | TASK-072, TASK-073, TASK-081 |
| REL-05 | REL- | RELEASE_PLAN · 3. The release sequence | TASK-072, TASK-073 |
| REL-06 | REL- | RELEASE_PLAN · 3. The release sequence | TASK-072, TASK-073 |
| REL-07 | REL- | RELEASE_PLAN · 3. The release sequence | TASK-072, TASK-073 |
| REL-08 | REL- | RELEASE_PLAN · 3. The release sequence | TASK-072, TASK-073 |
| REL-09 | REL- | RELEASE_PLAN · 3. The release sequence | TASK-072, TASK-073 |
| REL-10 | REL- | RELEASE_PLAN · 3. The release sequence | TASK-072, TASK-073 |
| REL-11 | REL- | RELEASE_PLAN · 3. The release sequence | TASK-072, TASK-073 |
| REL-12 | REL- | RELEASE_PLAN · 3. The release sequence | TASK-072, TASK-073 |
| REL-13 | REL- | RELEASE_PLAN · 3. The release sequence | TASK-072, TASK-073 |
| IMP-01 | IMP- | RELEASE_PLAN · 5. The opening import | TASK-057, TASK-077 |
| IMP-02 | IMP- | RELEASE_PLAN · 5. The opening import | TASK-057, TASK-077 |
| IMP-03 | IMP- | RELEASE_PLAN · 5. The opening import | TASK-057, TASK-077 |
| IMP-04 | IMP- | RELEASE_PLAN · 5. The opening import | TASK-057, TASK-077 |
| IMP-05 | IMP- | RELEASE_PLAN · 5. The opening import | TASK-057, TASK-077 |
| IMP-06 | IMP- | RELEASE_PLAN · 5. The opening import | TASK-057, TASK-077 |
| IMP-07 | IMP- | RELEASE_PLAN · 5. The opening import | TASK-057, TASK-077 |
| IMP-08 | IMP- | RELEASE_PLAN · 5. The opening import | TASK-057, TASK-077 |
| IMP-09 | IMP- | RELEASE_PLAN · 5. The opening import | TASK-057, TASK-077 |
| IMP-10 | IMP- | RELEASE_PLAN · 5. The opening import | TASK-057, TASK-077 |
| IMP-11 | IMP- | RELEASE_PLAN · 5. The opening import | TASK-057, TASK-077 |
| IMP-12 | IMP- | RELEASE_PLAN · 5. The opening import | TASK-057, TASK-077 |
| IMP-13 | IMP- | RELEASE_PLAN · 5. The opening import | TASK-057, TASK-077 |
| CUT-01 | CUT- | RELEASE_PLAN · 6. Cutover and rollback | TASK-077, TASK-087 |
| CUT-02 | CUT- | RELEASE_PLAN · 6. Cutover and rollback | TASK-077, TASK-087 |
| CUT-03 | CUT- | RELEASE_PLAN · 6. Cutover and rollback | TASK-077, TASK-087 |
| CUT-04 | CUT- | RELEASE_PLAN · 6. Cutover and rollback | TASK-077, TASK-087 |
| CUT-05 | CUT- | RELEASE_PLAN · 6. Cutover and rollback | TASK-077, TASK-087 |
| CUT-06 | CUT- | RELEASE_PLAN · 6. Cutover and rollback | TASK-077, TASK-087 |
| CUT-07 | CUT- | RELEASE_PLAN · 6. Cutover and rollback | TASK-077, TASK-087 |
| CUT-08 | CUT- | RELEASE_PLAN · 6. Cutover and rollback | TASK-077, TASK-087 |
| RBK-01 | RBK- | RELEASE_PLAN · 6. Cutover and rollback | TASK-076, TASK-077 |
| RBK-02 | RBK- | RELEASE_PLAN · 6. Cutover and rollback | TASK-076, TASK-077 |
| RBK-03 | RBK- | RELEASE_PLAN · 6. Cutover and rollback | TASK-076, TASK-077 |
| RBK-04 | RBK- | RELEASE_PLAN · 6. Cutover and rollback | TASK-076, TASK-077 |
| RBK-05 | RBK- | RELEASE_PLAN · 6. Cutover and rollback | TASK-076, TASK-077 |
| RBK-06 | RBK- | RELEASE_PLAN · 6. Cutover and rollback | TASK-076, TASK-077 |
| UAT-01 | UAT- | RELEASE_PLAN · 8. UAT | TASK-070, TASK-086 |
| UAT-02 | UAT- | RELEASE_PLAN · 8. UAT | TASK-070, TASK-086 |
| UAT-03 | UAT- | RELEASE_PLAN · 8. UAT | TASK-070, TASK-086 |
| UAT-04 | UAT- | RELEASE_PLAN · 8. UAT | TASK-070, TASK-086 |
| GLC-07 | GLC- | RELEASE_PLAN · 9. The go-live checklist | TASK-084 |
| GLC-20 | GLC- | RELEASE_PLAN · 9. The go-live checklist | TASK-081, TASK-084 |
| GLC-06 | GLC- | RELEASE_PLAN · 9. The go-live checklist | TASK-084 |
| GLC-11 | GLC- | RELEASE_PLAN · 9. The go-live checklist | TASK-080, TASK-084 |
| GLC-24 | GLC- | RELEASE_PLAN · 9. The go-live checklist | TASK-078, TASK-084 |
| GLC-26 | GLC- | RELEASE_PLAN · 9. The go-live checklist | TASK-081 |
| GLC-16 | GLC- | RELEASE_PLAN · 9. The go-live checklist | TASK-076, TASK-080, TASK-081, TASK-084, TASK-085, TASK-087 |
| GLC-21 | GLC- | RELEASE_PLAN · 9. The go-live checklist | TASK-083 |
| GLC-23 | GLC- | RELEASE_PLAN · 9. The go-live checklist | TASK-085 |
| GLC-01 | GLC- | RELEASE_PLAN · 9. The go-live checklist | TASK-087 |
| GLC-02 | GLC- | RELEASE_PLAN · 9. The go-live checklist | TASK-071, TASK-084 |
| GLC-03 | GLC- | RELEASE_PLAN · 9. The go-live checklist | TASK-080, TASK-081 |
| GLC-04 | GLC- | RELEASE_PLAN · 9. The go-live checklist | TASK-087 |
| GLC-05 | GLC- | RELEASE_PLAN · 9. The go-live checklist | TASK-079, TASK-087 |
| GLC-08 | GLC- | RELEASE_PLAN · 9. The go-live checklist | TASK-084 |
| GLC-09 | GLC- | RELEASE_PLAN · 9. The go-live checklist | TASK-082 |
| GLC-10 | GLC- | RELEASE_PLAN · 9. The go-live checklist | TASK-085 |
| GLC-12 | GLC- | RELEASE_PLAN · 9. The go-live checklist | TASK-084 |
| GLC-13 | GLC- | RELEASE_PLAN · 9. The go-live checklist | TASK-085 |
| GLC-14 | GLC- | RELEASE_PLAN · 9. The go-live checklist | TASK-087 |
| GLC-15 | GLC- | RELEASE_PLAN · 9. The go-live checklist | TASK-086 |
| GLC-17 | GLC- | RELEASE_PLAN · 9. The go-live checklist | TASK-086 |
| GLC-18 | GLC- | RELEASE_PLAN · 9. The go-live checklist | TASK-087 |
| GLC-19 | GLC- | RELEASE_PLAN · 9. The go-live checklist | TASK-078, TASK-081 |
| GLC-22 | GLC- | RELEASE_PLAN · 9. The go-live checklist | TASK-084 |
| GLC-25 | GLC- | RELEASE_PLAN · 9. The go-live checklist | TASK-021, TASK-087 |
| PRQ-15 | PRQ- | RELEASE_PLAN · 10. Production-release requirements for P11 | TASK-076 |
| PRQ-01 | PRQ- | RELEASE_PLAN · 10. Production-release requirements for P11 | TASK-072 |
| PRQ-02 | PRQ- | RELEASE_PLAN · 10. Production-release requirements for P11 | TASK-072, TASK-078 |
| PRQ-03 | PRQ- | RELEASE_PLAN · 10. Production-release requirements for P11 | TASK-072, TASK-075 |
| PRQ-04 | PRQ- | RELEASE_PLAN · 10. Production-release requirements for P11 | TASK-013, TASK-072 |
| PRQ-05 | PRQ- | RELEASE_PLAN · 10. Production-release requirements for P11 | TASK-013, TASK-072 |
| PRQ-06 | PRQ- | RELEASE_PLAN · 10. Production-release requirements for P11 | TASK-057 |
| PRQ-07 | PRQ- | RELEASE_PLAN · 10. Production-release requirements for P11 | TASK-021, TASK-077 |
| PRQ-08 | PRQ- | RELEASE_PLAN · 10. Production-release requirements for P11 | TASK-077 |
| PRQ-09 | PRQ- | RELEASE_PLAN · 10. Production-release requirements for P11 | TASK-020, TASK-085 |
| PRQ-10 | PRQ- | RELEASE_PLAN · 10. Production-release requirements for P11 | TASK-074, TASK-081 |
| PRQ-11 | PRQ- | RELEASE_PLAN · 10. Production-release requirements for P11 | TASK-073 |
| PRQ-12 | PRQ- | RELEASE_PLAN · 10. Production-release requirements for P11 | TASK-004 |
| PRQ-13 | PRQ- | RELEASE_PLAN · 10. Production-release requirements for P11 | disposition SATISFIED — met by the Owner's approval of P11 with this matrix at OG-4 (APPR-016; DIR-085 OF-07), gate P11-02 having checked it first |
| PRQ-14 | PRQ- | RELEASE_PLAN · 10. Production-release requirements for P11 | TASK-073 |
| table:users | table | DATABASE · 4.1 IAM — Identity & Access | TASK-005 |
| table:roles | table | DATABASE · 4.1 IAM — Identity & Access | TASK-005 |
| table:capabilities | table | DATABASE · 4.1 IAM — Identity & Access | TASK-005 |
| table:role_capabilities | table | DATABASE · 4.1 IAM — Identity & Access | TASK-005 |
| table:user_roles | table | DATABASE · 4.1 IAM — Identity & Access | TASK-005 |
| table:user_capability_grants | table | DATABASE · 4.1 IAM — Identity & Access | TASK-005 |
| table:user_company_grants | table | DATABASE · 4.1 IAM — Identity & Access | TASK-005 |
| table:user_external_identities | table | DATABASE · 4.1 IAM — Identity & Access | TASK-005 |
| table:companies | table | DATABASE · 4.2 ORG — Organization | TASK-006 |
| table:company_bank_accounts | table | DATABASE · 4.2 ORG — Organization | TASK-006 |
| table:numbering_schemes | table | DATABASE · 4.2 ORG — Organization | TASK-006 |
| table:number_sequences | table | DATABASE · 4.2 ORG — Organization | TASK-006 |
| table:retired_numbers | table | DATABASE · 4.2 ORG — Organization | TASK-006 |
| table:tax_types | table | DATABASE · 4.2 ORG — Organization | TASK-006 |
| table:tax_rate_versions | table | DATABASE · 4.2 ORG — Organization | TASK-006 |
| table:company_tax_profiles | table | DATABASE · 4.2 ORG — Organization | TASK-006 |
| table:tax_treatment_rules | table | DATABASE · 4.2 ORG — Organization | TASK-006 |
| table:client_organizations | table | DATABASE · 4.3 PTY — Parties | TASK-006 |
| table:client_units | table | DATABASE · 4.3 PTY — Parties | TASK-006 |
| table:client_addresses | table | DATABASE · 4.3 PTY — Parties | TASK-006 |
| table:client_contacts | table | DATABASE · 4.3 PTY — Parties | TASK-006 |
| table:suppliers | table | DATABASE · 4.3 PTY — Parties | TASK-006 |
| table:products | table | DATABASE · 4.4 CAT — Catalog | TASK-006 |
| table:units | table | DATABASE · 4.4 CAT — Catalog | TASK-006 |
| table:product_units | table | DATABASE · 4.4 CAT — Catalog | TASK-006 |
| table:product_barcodes | table | DATABASE · 4.4 CAT — Catalog | TASK-006 |
| table:product_price_history | table | DATABASE · 4.4 CAT — Catalog | TASK-006 |
| table:product_images | table | DATABASE · 4.4 CAT — Catalog | TASK-006 |
| table:projects | table | DATABASE · 4.5 PRJ — Projects & Commercial | TASK-007 |
| table:project_siplah_details | table | DATABASE · 4.5 PRJ — Projects & Commercial | TASK-007 |
| table:project_lines | table | DATABASE · 4.5 PRJ — Projects & Commercial | TASK-007 |
| table:commercial_confirmations | table | DATABASE · 4.5 PRJ — Projects & Commercial | TASK-007 |
| table:confirmation_line_pins | table | DATABASE · 4.5 PRJ — Projects & Commercial | TASK-007 |
| table:quotation_revisions | table | DATABASE · 4.5 PRJ — Projects & Commercial | TASK-007 |
| table:quotation_revision_lines | table | DATABASE · 4.5 PRJ — Projects & Commercial | TASK-007 |
| table:line_scope_cancellations | table | DATABASE · 4.5 PRJ — Projects & Commercial | TASK-007 |
| table:project_line_balances | table | DATABASE · 4.5 PRJ — Projects & Commercial | TASK-007 |
| table:project_commercial_balances | table | DATABASE · 4.5 PRJ — Projects & Commercial | TASK-007 |
| table:project_state_transitions | table | DATABASE · 4.5 PRJ — Projects & Commercial | TASK-007 |
| table:purchases | table | DATABASE · 4.6 PUR — Procurement | TASK-007 |
| table:purchase_lines | table | DATABASE · 4.6 PUR — Procurement | TASK-007 |
| table:purchase_line_tax_components | table | DATABASE · 4.6 PUR — Procurement | TASK-007 |
| table:purchase_charges | table | DATABASE · 4.6 PUR — Procurement | TASK-007 |
| table:purchase_charge_line_shares | table | DATABASE · 4.6 PUR — Procurement | TASK-007 |
| table:purchase_line_balances | table | DATABASE · 4.6 PUR — Procurement | TASK-007 |
| table:purchase_line_closures | table | DATABASE · 4.6 PUR — Procurement | TASK-007 |
| table:purchase_line_adjustments | table | DATABASE · 4.6 PUR — Procurement | TASK-007 |
| table:locations | table | DATABASE · 4.7 INV — Inventory | TASK-008 |
| table:inventory_lots | table | DATABASE · 4.7 INV — Inventory | TASK-008 |
| table:serial_units | table | DATABASE · 4.7 INV — Inventory | TASK-008 |
| table:stock_movements | table | DATABASE · 4.7 INV — Inventory | TASK-008 |
| table:stock_movement_entries | table | DATABASE · 4.7 INV — Inventory | TASK-008 |
| table:receiving_lines | table | DATABASE · 4.7 INV — Inventory | TASK-008 |
| table:dispatch_lines | table | DATABASE · 4.7 INV — Inventory | TASK-008 |
| table:stock_consumptions | table | DATABASE · 4.7 INV — Inventory | TASK-008 |
| table:stock_consumption_balances | table | DATABASE · 4.7 INV — Inventory | TASK-008 |
| table:stock_restorations | table | DATABASE · 4.7 INV — Inventory | TASK-008 |
| table:stock_product_balances | table | DATABASE · 4.7 INV — Inventory | TASK-008 |
| table:stock_scope_balances | table | DATABASE · 4.7 INV — Inventory | TASK-008 |
| table:stock_lot_balances | table | DATABASE · 4.7 INV — Inventory | TASK-008 |
| table:stock_reversal_balances | table | DATABASE · 4.7 INV — Inventory | TASK-008 |
| table:reservations | table | DATABASE · 4.7 INV — Inventory | TASK-008 |
| table:reservation_events | table | DATABASE · 4.7 INV — Inventory | TASK-008 |
| table:stock_counts | table | DATABASE · 4.7 INV — Inventory | TASK-008 |
| table:stock_count_scopes | table | DATABASE · 4.7 INV — Inventory | TASK-008 |
| table:stock_count_lines | table | DATABASE · 4.7 INV — Inventory | TASK-008 |
| table:stock_count_findings | table | DATABASE · 4.7 INV — Inventory | TASK-008 |
| table:unattributed_loss_cases | table | DATABASE · 4.7 INV — Inventory | TASK-008 |
| table:unattributed_loss_candidates | table | DATABASE · 4.7 INV — Inventory | TASK-008 |
| table:unattributed_loss_exposures | table | DATABASE · 4.7 INV — Inventory | TASK-008 |
| table:unattributed_loss_group_exposures | table | DATABASE · 4.7 INV — Inventory | TASK-008 |
| table:unattributed_loss_resolutions | table | DATABASE · 4.7 INV — Inventory | TASK-008 |
| table:unattributed_loss_resolution_lines | table | DATABASE · 4.7 INV — Inventory | TASK-008 |
| table:lot_cost_entries | table | DATABASE · 4.8 CST — Costing & Expenses | TASK-007 |
| table:lot_cost_balances | table | DATABASE · 4.8 CST — Costing & Expenses | TASK-007 |
| table:cost_attributions | table | DATABASE · 4.8 CST — Costing & Expenses | TASK-007 |
| table:purchase_charge_balances | table | DATABASE · 4.8 CST — Costing & Expenses | TASK-007 |
| table:purchase_charge_share_balances | table | DATABASE · 4.8 CST — Costing & Expenses | TASK-007 |
| table:expenses | table | DATABASE · 4.8 CST — Costing & Expenses | TASK-007 |
| table:dropship_confirmations | table | DATABASE · 4.9 FUL — Fulfillment | TASK-009 |
| table:dropship_confirmation_lines | table | DATABASE · 4.9 FUL — Fulfillment | TASK-009 |
| table:deliveries | table | DATABASE · 4.9 FUL — Fulfillment | TASK-009 |
| table:delivery_lines | table | DATABASE · 4.9 FUL — Fulfillment | TASK-009 |
| table:delivery_closures | table | DATABASE · 4.9 FUL — Fulfillment | TASK-009 |
| table:service_handovers | table | DATABASE · 4.9 FUL — Fulfillment | TASK-009 |
| table:service_handover_lines | table | DATABASE · 4.9 FUL — Fulfillment | TASK-009 |
| table:document_types | table | DATABASE · 4.10 DOC — Documents & Evidence | TASK-006 |
| table:documents | table | DATABASE · 4.10 DOC — Documents & Evidence | TASK-006 |
| table:document_versions | table | DATABASE · 4.10 DOC — Documents & Evidence | TASK-006 |
| table:document_renditions | table | DATABASE · 4.10 DOC — Documents & Evidence | TASK-006 |
| table:file_objects | table | DATABASE · 4.10 DOC — Documents & Evidence | TASK-006 |
| table:evidence_documents | table | DATABASE · 4.10 DOC — Documents & Evidence | TASK-006 |
| table:evidence_document_versions | table | DATABASE · 4.10 DOC — Documents & Evidence | TASK-006 |
| table:bank_statement_lines | table | DATABASE · 4.10 DOC — Documents & Evidence | TASK-006 |
| table:evidence_balances | table | DATABASE · 4.10 DOC — Documents & Evidence | TASK-006 |
| table:evidence_links | table | DATABASE · 4.10 DOC — Documents & Evidence | TASK-006 |
| table:admin_requirements | table | DATABASE · 4.11 ADM — Administration | TASK-007 |
| table:admin_requirement_links | table | DATABASE · 4.11 ADM — Administration | TASK-007 |
| table:admin_requirement_waivers | table | DATABASE · 4.11 ADM — Administration | TASK-007 |
| table:invoices | table | DATABASE · 4.12 FIN — Finance | TASK-009 |
| table:invoice_versions | table | DATABASE · 4.12 FIN — Finance | TASK-009 |
| table:invoice_version_lines | table | DATABASE · 4.12 FIN — Finance | TASK-009 |
| table:invoice_tax_components | table | DATABASE · 4.12 FIN — Finance | TASK-009 |
| table:invoice_balances | table | DATABASE · 4.12 FIN — Finance | TASK-009 |
| table:invoice_billing_acts | table | DATABASE · 4.12 FIN — Finance | TASK-009 |
| table:payments | table | DATABASE · 4.12 FIN — Finance | TASK-009 |
| table:payment_claimed_deductions | table | DATABASE · 4.12 FIN — Finance | TASK-009 |
| table:application_source_balances | table | DATABASE · 4.12 FIN — Finance | TASK-009 |
| table:payment_applications | table | DATABASE · 4.12 FIN — Finance | TASK-009 |
| table:deduction_settlements | table | DATABASE · 4.12 FIN — Finance | TASK-009 |
| table:receivable_writeoffs | table | DATABASE · 4.12 FIN — Finance | TASK-009 |
| table:receivable_dispute_holds | table | DATABASE · 4.12 FIN — Finance | TASK-009 |
| table:opening_customer_credits | table | DATABASE · 4.12 FIN — Finance | TASK-009 |
| table:prepayment_kuitansi_links | table | DATABASE · 4.12 FIN — Finance | TASK-009 |
| table:disbursements | table | DATABASE · 4.12 FIN — Finance | TASK-009 |
| table:other_cash_receipts | table | DATABASE · 4.12 FIN — Finance | TASK-009 |
| table:financial_contras | table | DATABASE · 4.12 FIN — Finance | TASK-009 |
| table:money_fact_balances | table | DATABASE · 4.12 FIN — Finance | TASK-009 |
| table:audit_events | table | DATABASE · 4.13 OPS — Operations | TASK-005 |
| table:security_events | table | DATABASE · 4.13 OPS — Operations | TASK-005 |
| table:command_log | table | DATABASE · 4.13 OPS — Operations | TASK-005 |
| table:correction_cases | table | DATABASE · 4.13 OPS — Operations | TASK-005 |
| table:import_batches | table | DATABASE · 4.13 OPS — Operations | TASK-005 |
| table:import_rows | table | DATABASE · 4.13 OPS — Operations | TASK-005 |
| table:legacy_archive_items | table | DATABASE · 4.13 OPS — Operations | TASK-005 |
| capability:stock.view | capability | PERMISSIONS_MATRIX · 4. Capability catalogue | TASK-011 |
| capability:projects.view | capability | PERMISSIONS_MATRIX · 4. Capability catalogue | TASK-011 |
| capability:finance.view | capability | PERMISSIONS_MATRIX · 4. Capability catalogue | TASK-011 |
| capability:cost.view | capability | PERMISSIONS_MATRIX · 4. Capability catalogue | TASK-011 |
| capability:profit.view | capability | PERMISSIONS_MATRIX · 4. Capability catalogue | TASK-011 |
| capability:evidence.view | capability | PERMISSIONS_MATRIX · 4. Capability catalogue | TASK-011 |
| capability:reports.export | capability | PERMISSIONS_MATRIX · 4. Capability catalogue | TASK-011 |
| capability:reports.consolidated | capability | PERMISSIONS_MATRIX · 4. Capability catalogue | TASK-011 |
| capability:review.view | capability | PERMISSIONS_MATRIX · 4. Capability catalogue | TASK-011 |
| capability:audit.view | capability | PERMISSIONS_MATRIX · 4. Capability catalogue | TASK-011 |
| capability:security_log.view | capability | PERMISSIONS_MATRIX · 4. Capability catalogue | TASK-011 |
| capability:scope.all_companies | capability | PERMISSIONS_MATRIX · 4. Capability catalogue | TASK-011 |
| capability:users.manage | capability | PERMISSIONS_MATRIX · 4. Capability catalogue | TASK-011 |
| capability:permissions.manage | capability | PERMISSIONS_MATRIX · 4. Capability catalogue | TASK-011 |
| capability:companies.manage | capability | PERMISSIONS_MATRIX · 4. Capability catalogue | TASK-011 |
| capability:tax.configure | capability | PERMISSIONS_MATRIX · 4. Capability catalogue | TASK-011 |
| capability:requirements.set_waiver_eligibility | capability | PERMISSIONS_MATRIX · 4. Capability catalogue | TASK-011 |
| capability:requirements.relax | capability | PERMISSIONS_MATRIX · 4. Capability catalogue | TASK-011 |
| capability:writeoff.supersede | capability | PERMISSIONS_MATRIX · 4. Capability catalogue | TASK-011 |
| capability:stock.attribute_surplus | capability | PERMISSIONS_MATRIX · 4. Capability catalogue | TASK-011 |
| capability:stock.resolve_unattributed | capability | PERMISSIONS_MATRIX · 4. Capability catalogue | TASK-011 |
| capability:receivables.write_off | capability | PERMISSIONS_MATRIX · 4. Capability catalogue | TASK-011 |
| capability:projects.force_complete | capability | PERMISSIONS_MATRIX · 4. Capability catalogue | TASK-011 |
| capability:import.commit | capability | PERMISSIONS_MATRIX · 4. Capability catalogue | TASK-011 |
| capability:parties.manage | capability | PERMISSIONS_MATRIX · 4. Capability catalogue | TASK-011 |
| capability:products.edit | capability | PERMISSIONS_MATRIX · 4. Capability catalogue | TASK-011 |
| capability:warehouse.manage | capability | PERMISSIONS_MATRIX · 4. Capability catalogue | TASK-011 |
| capability:projects.manage | capability | PERMISSIONS_MATRIX · 4. Capability catalogue | TASK-011 |
| capability:quotation.manage | capability | PERMISSIONS_MATRIX · 4. Capability catalogue | TASK-011 |
| capability:projects.confirm | capability | PERMISSIONS_MATRIX · 4. Capability catalogue | TASK-011 |
| capability:stock.reserve | capability | PERMISSIONS_MATRIX · 4. Capability catalogue | TASK-011 |
| capability:purchase.manage | capability | PERMISSIONS_MATRIX · 4. Capability catalogue | TASK-011 |
| capability:stock.receive | capability | PERMISSIONS_MATRIX · 4. Capability catalogue | TASK-011 |
| capability:stock.dispatch | capability | PERMISSIONS_MATRIX · 4. Capability catalogue | TASK-011 |
| capability:stock.move | capability | PERMISSIONS_MATRIX · 4. Capability catalogue | TASK-011 |
| capability:stock.count | capability | PERMISSIONS_MATRIX · 4. Capability catalogue | TASK-011 |
| capability:delivery.record | capability | PERMISSIONS_MATRIX · 4. Capability catalogue | TASK-011 |
| capability:dropship.confirm | capability | PERMISSIONS_MATRIX · 4. Capability catalogue | TASK-011 |
| capability:handover.record | capability | PERMISSIONS_MATRIX · 4. Capability catalogue | TASK-011 |
| capability:documents.issue | capability | PERMISSIONS_MATRIX · 4. Capability catalogue | TASK-011 |
| capability:evidence.upload | capability | PERMISSIONS_MATRIX · 4. Capability catalogue | TASK-011 |
| capability:invoice.issue | capability | PERMISSIONS_MATRIX · 4. Capability catalogue | TASK-011 |
| capability:billing.record | capability | PERMISSIONS_MATRIX · 4. Capability catalogue | TASK-011 |
| capability:payment.record | capability | PERMISSIONS_MATRIX · 4. Capability catalogue | TASK-011 |
| capability:payment.apply | capability | PERMISSIONS_MATRIX · 4. Capability catalogue | TASK-011 |
| capability:expense.record | capability | PERMISSIONS_MATRIX · 4. Capability catalogue | TASK-011 |
| capability:disbursement.record | capability | PERMISSIONS_MATRIX · 4. Capability catalogue | TASK-011 |
| capability:cashin.record | capability | PERMISSIONS_MATRIX · 4. Capability catalogue | TASK-011 |
| capability:bank_statement.record | capability | PERMISSIONS_MATRIX · 4. Capability catalogue | TASK-011 |
| capability:requirements.manage | capability | PERMISSIONS_MATRIX · 4. Capability catalogue | TASK-011 |
| capability:projects.complete | capability | PERMISSIONS_MATRIX · 4. Capability catalogue | TASK-011 |
| capability:receivables.dispute | capability | PERMISSIONS_MATRIX · 4. Capability catalogue | TASK-011 |
| capability:requirements.waive | capability | PERMISSIONS_MATRIX · 4. Capability catalogue | TASK-011 |
| capability:import.prepare | capability | PERMISSIONS_MATRIX · 4. Capability catalogue | TASK-011 |
| capability:stock.reverse | capability | PERMISSIONS_MATRIX · 4. Capability catalogue | TASK-011 |
| capability:sales_return.record | capability | PERMISSIONS_MATRIX · 4. Capability catalogue | TASK-011 |
| capability:purchase_return.record | capability | PERMISSIONS_MATRIX · 4. Capability catalogue | TASK-011 |
| capability:purchase.correct_cost | capability | PERMISSIONS_MATRIX · 4. Capability catalogue | TASK-011 |
| capability:finance.contra | capability | PERMISSIONS_MATRIX · 4. Capability catalogue | TASK-011 |
| capability:payment.reallocate | capability | PERMISSIONS_MATRIX · 4. Capability catalogue | TASK-011 |
| capability:invoice.correct | capability | PERMISSIONS_MATRIX · 4. Capability catalogue | TASK-011 |
| capability:documents.correct | capability | PERMISSIONS_MATRIX · 4. Capability catalogue | TASK-011 |
| capability:billing.correct | capability | PERMISSIONS_MATRIX · 4. Capability catalogue | TASK-011 |
| capability:decisions.supersede | capability | PERMISSIONS_MATRIX · 4. Capability catalogue | TASK-011 |
| capability:fulfillment.correct | capability | PERMISSIONS_MATRIX · 4. Capability catalogue | TASK-011 |
| capability:warnings.override | capability | PERMISSIONS_MATRIX · 4. Capability catalogue | TASK-011 |
| capability:cash.correct | capability | PERMISSIONS_MATRIX · 4. Capability catalogue | TASK-011 |
| capability:purchase.cancel | capability | PERMISSIONS_MATRIX · 4. Capability catalogue | TASK-011 |
| capability:refund.record | capability | PERMISSIONS_MATRIX · 4. Capability catalogue | TASK-011 |
| capability:settlement.record | capability | PERMISSIONS_MATRIX · 4. Capability catalogue | TASK-011 |
| capability:stock.adjust | capability | PERMISSIONS_MATRIX · 4. Capability catalogue | TASK-011 |
| capability:stock.condition | capability | PERMISSIONS_MATRIX · 4. Capability catalogue | TASK-011 |
| capability:stock.count_apply | capability | PERMISSIONS_MATRIX · 4. Capability catalogue | TASK-011 |
| capability:stock.resolve_unattributed_evidence | capability | PERMISSIONS_MATRIX · 4. Capability catalogue | TASK-011 |
| capability:stock.allocate_intercompany | capability | PERMISSIONS_MATRIX · 4. Capability catalogue | TASK-011 |
| capability:projects.cancel_scope | capability | PERMISSIONS_MATRIX · 4. Capability catalogue | TASK-011 |
| capability:delivery.close | capability | PERMISSIONS_MATRIX · 4. Capability catalogue | TASK-011 |
| capability:purchase.close_remainder | capability | PERMISSIONS_MATRIX · 4. Capability catalogue | TASK-011 |
| capability:cases.close | capability | PERMISSIONS_MATRIX · 4. Capability catalogue | TASK-011 |
| capability:projects.cancel | capability | PERMISSIONS_MATRIX · 4. Capability catalogue | TASK-011 |
| migration-rule:DB24-01 | migration rule | DATABASE · 24. Migration and opening data — Batch identity and source provenance | TASK-005, TASK-057 |
| migration-rule:DB24-02 | migration rule | DATABASE · 24. Migration and opening data — Row-level results and errors | TASK-005, TASK-057 |
| migration-rule:DB24-03 | migration rule | DATABASE · 24. Migration and opening data — Idempotent re-import | TASK-005, TASK-006, TASK-007, TASK-008, TASK-009, TASK-057 |
| migration-rule:DB24-04 | migration rule | DATABASE · 24. Migration and opening data — Opening stock and lots | TASK-008, TASK-057 |
| migration-rule:DB24-05 | migration rule | DATABASE · 24. Migration and opening data — Opening receivables | TASK-009, TASK-057 |
| migration-rule:DB24-06 | migration rule | DATABASE · 24. Migration and opening data — Opening customer credit | TASK-009, TASK-057 |
| migration-rule:DB24-07 | migration rule | DATABASE · 24. Migration and opening data — Open projects and purchases | TASK-007, TASK-057 |
| migration-rule:DB24-08 | migration rule | DATABASE · 24. Migration and opening data — Useful current-year documents | TASK-005, TASK-006, TASK-057 |
| migration-rule:DB24-09 | migration rule | DATABASE · 24. Migration and opening data — Number sequences | TASK-006, TASK-021, TASK-057 |
| migration-rule:DB24-10 | migration rule | DATABASE · 24. Migration and opening data — Dry run | TASK-005, TASK-057 |
| component:Shell — sidebar, *Menu lengkap*, icon rail, top bar, skip link | component | DESIGN_SYSTEM · 10. Component composition | TASK-015, TASK-016 |
| component:Phone app bar and bottom bar; *Lainnya* | component | DESIGN_SYSTEM · 10. Component composition | TASK-015, TASK-016 |
| component:Page header | component | DESIGN_SYSTEM · 10. Component composition | TASK-015 |
| component:Button | component | DESIGN_SYSTEM · 10. Component composition | TASK-015 |
| component:Split action | component | DESIGN_SYSTEM · 10. Component composition | TASK-015 |
| component:Field and number entry | component | DESIGN_SYSTEM · 10. Component composition | TASK-015 |
| component:Select and lookup | component | DESIGN_SYSTEM · 10. Component composition | TASK-015 |
| component:Date field and business date | component | DESIGN_SYSTEM · 10. Component composition | TASK-015 |
| component:Date range | component | DESIGN_SYSTEM · 10. Component composition | TASK-015 |
| component:Code entry | component | DESIGN_SYSTEM · 10. Component composition | TASK-015 |
| component:Checkbox, radio and switch | component | DESIGN_SYSTEM · 10. Component composition | TASK-015 |
| component:Status pill and status text | component | DESIGN_SYSTEM · 10. Component composition | TASK-015 |
| component:Company tag and count badge | component | DESIGN_SYSTEM · 10. Component composition | TASK-015 |
| component:Card and section card | component | DESIGN_SYSTEM · 10. Component composition | TASK-015 |
| component:KPI tile | component | DESIGN_SYSTEM · 10. Component composition | TASK-015 |
| component:Facts, key-value and metric strip | component | DESIGN_SYSTEM · 10. Component composition | TASK-015 |
| component:Tabs | component | DESIGN_SYSTEM · 10. Component composition | TASK-015 |
| component:Segmented control | component | DESIGN_SYSTEM · 10. Component composition | TASK-015 |
| component:Data table and row list | component | DESIGN_SYSTEM · 10. Component composition | TASK-015 |
| component:Filter toolbar and company view | component | DESIGN_SYSTEM · 10. Component composition | TASK-015, TASK-016 |
| component:States of a region | component | DESIGN_SYSTEM · 10. Component composition | TASK-015 |
| component:System page | component | DESIGN_SYSTEM · 10. Component composition | TASK-015 |
| component:Timeline | component | DESIGN_SYSTEM · 10. Component composition | TASK-015 |
| component:Checklist | component | DESIGN_SYSTEM · 10. Component composition | TASK-015 |
| component:Stage track and stage navigator | component | DESIGN_SYSTEM · 10. Component composition | TASK-015 |
| component:Segmented stage indicator | component | DESIGN_SYSTEM · 10. Component composition | TASK-015 |
| component:The now strip | component | DESIGN_SYSTEM · 10. Component composition | TASK-015 |
| component:Dialog and confirmation | component | DESIGN_SYSTEM · 10. Component composition | TASK-015 |
| component:Menu and "Tindakan Lain"; account menu | component | DESIGN_SYSTEM · 10. Component composition | TASK-015, TASK-016 |
| component:Popover, tooltip and long text | component | DESIGN_SYSTEM · 10. Component composition | TASK-015 |
| component:Search and scan | component | DESIGN_SYSTEM · 10. Component composition | TASK-015, TASK-059 |
| component:Toast | component | DESIGN_SYSTEM · 10. Component composition | TASK-015 |
| component:Notice and alert | component | DESIGN_SYSTEM · 10. Component composition | TASK-015 |
| component:Checks, result, next step and prerequisite | component | DESIGN_SYSTEM · 10. Component composition | TASK-015 |
| component:Disclosure | component | DESIGN_SYSTEM · 10. Component composition | TASK-015 |
| component:Position and inventory blocks | component | DESIGN_SYSTEM · 10. Component composition | TASK-015 |
| component:Multi-line editor | component | DESIGN_SYSTEM · 10. Component composition | TASK-015 |
| component:Sticky action bar | component | DESIGN_SYSTEM · 10. Component composition | TASK-015 |
| component:Upload | component | DESIGN_SYSTEM · 10. Component composition | TASK-022, TASK-030 |
| component:Document composer and paper | component | DESIGN_SYSTEM · 10. Component composition | TASK-029 |
| component:Chart | component | DESIGN_SYSTEM · 10. Component composition | TASK-058, TASK-060 |
| component:Progress bar, keycap, avatar, icon disc, link | component | DESIGN_SYSTEM · 10. Component composition | TASK-015 |
| component:Sign-in compositions | component | DESIGN_SYSTEM · 10. Component composition | TASK-017, TASK-018 |
| component:Print view | component | DESIGN_SYSTEM · 10. Component composition | TASK-029 |
| l10n-rule:LOCALIZATION §1 | localization rule | LOCALIZATION · The localization contract | TASK-014 |
| l10n-rule:LOCALIZATION §2 | localization rule | LOCALIZATION · Locales, the choice and where it is kept | TASK-014 |
| l10n-rule:LOCALIZATION §3 | localization rule | LOCALIZATION · Keys, ownership, formats and rules | TASK-014 |
| l10n-rule:LOCALIZATION §3.1 | localization rule | LOCALIZATION · Keys and their naming (proposed, Level 1) | TASK-014 |
| l10n-rule:LOCALIZATION §3.2 | localization rule | LOCALIZATION · Who owns the copy | TASK-014 |
| l10n-rule:LOCALIZATION §3.3 | localization rule | LOCALIZATION · Formats | TASK-014 |
| l10n-rule:LOCALIZATION §3.4 | localization rule | LOCALIZATION · What never translates | TASK-014 |
| l10n-rule:LOCALIZATION §3.5 | localization rule | LOCALIZATION · The reference's micro-copy (GR-17; GP-5) | TASK-014 |
| l10n-rule:LOCALIZATION §3.6 | localization rule | LOCALIZATION · Both languages, no missing string | TASK-014 |
| l10n-rule:LOCALIZATION §3.7 | localization rule | LOCALIZATION · The layout after a language change | TASK-014 |
| l10n-rule:LOCALIZATION §3.8 | localization rule | LOCALIZATION · How strings reach the interface | TASK-014 |
| l10n-rule:LOCALIZATION §4 | localization rule | LOCALIZATION · Copy rules | TASK-014 |
| l10n-rule:LOCALIZATION §5 | localization rule | LOCALIZATION · Copy validation (CA-04 items 7–10) | TASK-014, TASK-067, TASK-086 |
| l10n-rule:LOCALIZATION §5.1 | localization rule | LOCALIZATION · The review list (CB-15, CD-07) | TASK-014, TASK-067, TASK-086 |
| l10n-group:messages | localization key group | LOCALIZATION · 6.1 | TASK-014 |
| l10n-group:glossary | localization key group | LOCALIZATION · 6.2 | TASK-014 |
| l10n-group:status | localization key group | LOCALIZATION · 6.3 | TASK-014 |
| l10n-group:auth | localization key group | LOCALIZATION · 6.4 | TASK-014 |
| l10n-group:documents | localization key group | LOCALIZATION · 6.5 | TASK-014 |
| l10n-group:pages | localization key group | LOCALIZATION · 6.6 | TASK-014 |
| l10n-group:shared | localization key group | LOCALIZATION · 6.7 | TASK-014 |
| GAP-003 | GAP- | GAP_REGISTER · Pooled stock with preserved source attribution | TASK-012, TASK-041, TASK-069 |
| GAP-004 | GAP- | GAP_REGISTER · Financial meaning and rounding | TASK-003, TASK-036, TASK-049, TASK-051, TASK-052 |
| GAP-005 | GAP- | GAP_REGISTER · Downstream corrections | TASK-013, TASK-031, TASK-069 |
| GAP-006 | GAP- | GAP_REGISTER · Shared masters and data visibility | TASK-012, TASK-069 |
| GAP-007 | GAP- | GAP_REGISTER · Issued document and file consistency | TASK-022, TASK-029, TASK-075 |
| GAP-008 | GAP- | GAP_REGISTER · Retry after uncertain completion | TASK-013, TASK-064 |
| GAP-009 | GAP- | GAP_REGISTER · Stock count, dispatch and scanning races | TASK-039, TASK-041, TASK-043, TASK-059 |
| GAP-010 | GAP- | GAP_REGISTER · Migration opening-state overlap | TASK-057, TASK-077, TASK-081, TASK-087 |
| GAP-011 | GAP- | GAP_REGISTER · Proving the agreed recovery targets | TASK-074, TASK-081, TASK-087 |
| GAP-012 | GAP- | GAP_REGISTER · Deployment and mixed worker versions | TASK-072 |
| GAP-013 | GAP- | GAP_REGISTER · Failure detection and operational ownership | TASK-075, TASK-080, TASK-083 |
| GAP-014 | GAP- | GAP_REGISTER · Capacity and business acceptance (deadline component superseded by D1) | TASK-079, TASK-086, TASK-087 |
| GAP-015 | GAP- | GAP_REGISTER · Stale/expired operational UI and scans | TASK-013, TASK-059, TASK-070, TASK-086 |
| GAP-016 | GAP- | GAP_REGISTER · Dates, period boundaries and audit ordering | TASK-010, TASK-013, TASK-021 |
| GAP-017 | GAP- | GAP_REGISTER · Evidence, workload and independent verification | TASK-068, TASK-073 |
| GAP-019 | GAP- | GAP_REGISTER · Complete document catalog and content validation | TASK-029, TASK-086 |
| GAP-020 | GAP- | GAP_REGISTER · Mobile/scanner practicality and desktop productivity | TASK-059, TASK-070, TASK-086 |
| GAP-022 | GAP- | GAP_REGISTER · Completion exceptions and subsequent corrections | TASK-055 |
| GAP-023 | GAP- | GAP_REGISTER · Allocation, reservation and usable stock | TASK-038, TASK-041 |
| GAP-024 | GAP- | GAP_REGISTER · Reference requirements lost before build/test mapping | TASK-070 |
| GAP-025 | GAP- | GAP_REGISTER · Multi-unit conversion and cost-split integrity | TASK-027, TASK-036 |
| GAP-026 | GAP- | GAP_REGISTER · Taxes withheld/collected at payment and tax basis of revenue/cost | TASK-025, TASK-049, TASK-052 |
| GAP-027 | GAP- | GAP_REGISTER · Purchase charges in acquisition cost | TASK-036, TASK-037 |
| GAP-028 | GAP- | GAP_REGISTER · Inventory loss and damaged-return cost | TASK-036, TASK-042, TASK-048 |
| GAP-029 | GAP- | GAP_REGISTER · Kuitansi required before payment | TASK-049 |
| GAP-030 | GAP- | GAP_REGISTER · Unexplained fungible loss across companies | TASK-043 |
| GAP-031 | GAP- | GAP_REGISTER · Guard balances drifting from their source facts | TASK-013, TASK-072, TASK-076 |
| GAP-032 | GAP- | GAP_REGISTER · Capability grants apply to every granted company | TASK-011, TASK-024 |
| GAP-033 | GAP- | GAP_REGISTER · Owner account without a second authentication factor | TASK-018, TASK-085 |
| GAP-034 | GAP- | GAP_REGISTER · Owner review items without a stored record | TASK-030, TASK-057, TASK-058, TASK-069 |
| GAP-035 | GAP- | GAP_REGISTER · Route and interaction contracts | TASK-011, TASK-069 |
| GAP-037 | GAP- | GAP_REGISTER · Bilingual UI obligations | TASK-014, TASK-067 |
| GAP-042 | GAP- | GAP_REGISTER · Google OAuth client not set up for any environment | TASK-082 |
| GAP-043 | GAP- | GAP_REGISTER · Framework behaviour read only in source | TASK-017, TASK-018, TASK-019 |
| GAP-044 | GAP- | GAP_REGISTER · ADMIN_FLOW login journeys predate TOTP and Google sign-in | TASK-017, TASK-018, TASK-064 |
| GAP-049 | GAP- | GAP_REGISTER · The first-Owner installation event has no actor rule | TASK-020, TASK-085 |
| GAP-050 | GAP- | GAP_REGISTER · Post-incident recovery cases carried to P8 and P9 | TASK-020, TASK-069, TASK-076 |
| GAP-053 | GAP- | GAP_REGISTER · A draft document's preview and print have no defined producer | TASK-029 |
| GAP-054 | GAP- | GAP_REGISTER · Project list journey summary beyond its query budget | TASK-032, TASK-068 |
| GAP-058 | GAP- | GAP_REGISTER · An imported purchase's number undefined | TASK-021, TASK-057 |
| GAP-060 | GAP- | GAP_REGISTER · *Beranda*'s receivables figure and ageing chart outside QB-10 | TASK-058, TASK-068 |
| GAP-063 | GAP- | GAP_REGISTER · Off-server backup against total VPS loss | TASK-074, TASK-081, TASK-084 |
| GAP-064 | GAP- | GAP_REGISTER · Numbers used on paper after a restore's recovery point | TASK-021, TASK-076, TASK-087 |
| GAP-066 | GAP- | GAP_REGISTER · Route and interaction rows the specifications require | TASK-037, TASK-043, TASK-046, TASK-052, TASK-053, TASK-055, TASK-057, TASK-072 |
| GAP-067 | GAP- | GAP_REGISTER · Approved statements that disagree | TASK-020, TASK-039, TASK-043, TASK-045, TASK-046, TASK-053, TASK-054, TASK-057, TASK-069 |
| GAP-068 | GAP- | GAP_REGISTER · Facts the specifications use but do not define | TASK-037, TASK-043, TASK-077 |
| GAP-071 | GAP- | GAP_REGISTER · The credit hold's capability and column | TASK-053, TASK-055 |

## 3. Non-Task dispositions

| Disposition | Meaning | Entries |
| --- | --- | --- |
| DEFERRED | Deferred out of V1 by V1_SCOPE — no Task builds it; building it would add to V1 (OE-20) | 6 |
| SUPERSEDED | A design decision its own document marks SUPERSEDED — its successor is mapped in its place; no Task builds it, and nothing derives from it (EXECUTION_CONTEXT) | 2 |
| FUTURE | The contract of a future external integration — V1 contains none (API_AND_INTEGRATIONS §3); no Task builds it (OE-20) | 13 |
| SATISFIED | A planning item already satisfied, its reason stated in the row — PRQ-13 by the Owner's approval of P11 (APPR-016) | 3 |
| DROPPED | Dropped as a requirement by DESIGN_SYSTEM Annex A, its reason recorded there — no requirement to build | 3 |
| GOVERNANCE | A rule every Task applies — carried by each Task's TEST CHECKLIST and COMPLETION EVIDENCE under DEFINITION_OF_DONE, and by TASK_PLAN's rules | 22 |

## 4. Entries per Task

| Task | Title | Entries mapped |
| --- | --- | --- |
| TASK-001 | Repository scaffold and module skeleton | 21 |
| TASK-002 | Coding conventions and architecture guardrails | 24 |
| TASK-003 | CI pipeline, test layers and the synthetic fixture harness | 45 |
| TASK-004 | Verification and release tooling check | 6 |
| TASK-005 | Schema foundations — IAM and OPS tables, history and types | 62 |
| TASK-006 | Schema — ORG, PTY, CAT and DOC tables | 70 |
| TASK-007 | Schema — PRJ, CST, PUR and ADM tables | 68 |
| TASK-008 | Schema — INV tables, inventory and lot-cost representations | 77 |
| TASK-009 | Schema — FUL and FIN tables, finance and tax representations | 69 |
| TASK-010 | Audit and security logging | 35 |
| TASK-011 | Authorization core — capabilities, roles, grants, company scope and the route audit | 304 |
| TASK-012 | Field projection, read classes and cross-company links | 105 |
| TASK-013 | Command envelope, command identity, idempotency and the lock order | 382 |
| TASK-014 | Localization — locales, the language switch and the key catalogue | 288 |
| TASK-015 | Design system — tokens, components, patterns and states | 302 |
| TASK-016 | Application shell, navigation and the company context | 160 |
| TASK-017 | Password sign-in, sessions and credential links | 146 |
| TASK-018 | Two-step verification (TOTP) and recovery codes | 66 |
| TASK-019 | Google sign-in for existing accounts | 37 |
| TASK-020 | Installation step, the first Owner and the Owner-recovery path | 30 |
| TASK-021 | Numbering, its settings page and the Owner's raise after a restore | 44 |
| TASK-022 | Jobs, files and the document renderer | 75 |
| TASK-023 | My account — password, two-step verification, Google link and language | 64 |
| TASK-024 | Users and access — accounts, roles, capabilities and company grants | 109 |
| TASK-025 | Companies, identity assets, bank accounts and tax configuration | 41 |
| TASK-026 | Clients and suppliers | 30 |
| TASK-027 | Catalog — products, services, units, barcodes, prices and images | 31 |
| TASK-028 | Rack locations and minimum stock | 18 |
| TASK-029 | Document engine — drafts, issue, revision, void, renditions and the composer | 95 |
| TASK-030 | Evidence and uploaded documents | 52 |
| TASK-031 | Correction cases | 74 |
| TASK-032 | Projects — list, form, journey page and amendment | 74 |
| TASK-033 | Quotations | 43 |
| TASK-034 | Commercial confirmation and fulfillment planning | 80 |
| TASK-035 | Administrative checklist and document readiness | 36 |
| TASK-036 | Costing foundations — lot cost basis, charge allocation and HPP attribution | 25 |
| TASK-037 | Purchases — project and replenishment purchases | 88 |
| TASK-038 | Reservations | 98 |
| TASK-039 | Receiving (Barang Masuk) | 118 |
| TASK-040 | Stock views, stock history and movement reversal | 48 |
| TASK-041 | Dispatch (Barang Keluar) and inter-company allocation | 129 |
| TASK-042 | Condition, location moves, adjustments and losses | 86 |
| TASK-043 | Stock count (opname) and discrepancies | 113 |
| TASK-044 | Purchase return to supplier | 40 |
| TASK-045 | Deliveries (Pengiriman) and delivery closure | 52 |
| TASK-046 | Direct shipment (drop-ship) | 38 |
| TASK-047 | Service fulfillment and handover | 24 |
| TASK-048 | Client returns (sales return) | 46 |
| TASK-049 | Invoices and the pre-payment Kuitansi | 148 |
| TASK-050 | Billing, receivables, dispute hold and Owner write-off | 101 |
| TASK-051 | Payments, applications and bank statement lines | 142 |
| TASK-052 | Deduction settlements and contras | 95 |
| TASK-053 | Customer credit and refunds | 86 |
| TASK-054 | Expenses, disbursements and other cash | 86 |
| TASK-055 | Project completion and Force Complete | 57 |
| TASK-056 | Project cancellation | 35 |
| TASK-057 | Opening import — preparation, dry run, sign-off and commit | 70 |
| TASK-058 | Home, Work and Review — signals and the action queue | 72 |
| TASK-059 | Search and scanning | 44 |
| TASK-060 | Reports, the consolidated report and exports | 67 |
| TASK-061 | Activity history and security log | 10 |
| TASK-062 | Critical journeys A — the golden flow, purchasing, fulfilment, counts, scanning and SIPLAH | 48 |
| TASK-063 | Critical journeys B — finance, corrections, reports, documents, completion and the checklist | 49 |
| TASK-064 | Critical journeys C — isolation, sign-in, access, search, approvals, import, language, masters, command identity and status | 76 |
| TASK-065 | UI/UX consistency gate, the responsive matrix and the anti-slop audit | 55 |
| TASK-066 | Accessibility — WCAG 2.2 AA across every page | 9 |
| TASK-067 | Localization completeness and the per-page copy lists | 8 |
| TASK-068 | Performance targets and query budgets in CI | 84 |
| TASK-069 | Security test matrix, route integrity and zero dead interactions | 173 |
| TASK-070 | Full release regression and the device UAT's preparation | 88 |
| TASK-071 | The operator's inputs before staging | 20 |
| TASK-072 | Deployment definition, health and smoke checks, maintenance mode and mixed-version safeguards | 60 |
| TASK-073 | Release process — branch protection, release tags, the release record and integrity report | 27 |
| TASK-074 | Backup tooling, the off-server copy and the drill procedures | 46 |
| TASK-075 | Monitoring — the business heartbeat, alerts and the uptime check | 18 |
| TASK-076 | Operator runbooks — Owner-account recovery, key compromise, Scenario B and key rotation | 65 |
| TASK-077 | Cutover tooling — the numbering go-live command, the post-import refresh and the rehearsal procedure | 39 |
| TASK-078 | Staging stack, the two applications' isolation and the provider features | 7 |
| TASK-079 | Staging measurements — capacity, Argon2id, durations and the disk budget | 43 |
| TASK-080 | Alert channel adopted and monitoring live | 13 |
| TASK-081 | Staging drills and rehearsals — failure, restore, Scenario B and the import rehearsal | 29 |
| TASK-082 | Google OAuth clients for staging and production | 9 |
| TASK-083 | The alternate operator named and the dispute's governance recorded | 11 |
| TASK-084 | Production preparation — configuration, outbound HTTPS, branch protection, backups and the off-server copy | 53 |
| TASK-085 | Installation, the first Owner, two-step enrolment and the APP_KEY rotation | 18 |
| TASK-086 | UAT with the Owner and the validators | 41 |
| TASK-087 | Cutover and go-live | 38 |

## 5. Checks

- **Entries:** 3259, in 125 family-and-document pairs.
- **Mapped:** 3259 of 3259 — 3210 to one or more Tasks and 49 to a recorded disposition; unmapped: 0.
- **Tasks:** 87 of 87 map at least one entry; orphan Tasks: none.
- **Method:** 2138 entries by an explicit rule, 934 derived from the identifiers their own row cites, 138 by their family's default when their row cites none that resolves, 49 by a disposition.
- **After OG-4:** GAP-066, GAP-067 and GAP-068, OPEN since the Owner's answers to P11Q-02–P11Q-04 ([DIR-085](../00-governance/DECISION_LOG.md#dir-085-dir-086-obs-034-and-tech-041--og-4-decisions-appr-016-the-planning-freeze-and-the-execution-authorization)), entered by the explicit rule for the OPEN gaps with the Tasks their register entries name — TASK_PLAN's Change 3, which re-checked this section by script.
- **Change 4 ([DIR-100](../00-governance/DECISION_LOG.md#dir-100--change-4-the-contract-rows-and-the-consistency-edit)):** eighteen entries added by the explicit rule, ROUTE-178, ROUTE-179, ROUTE-215, ROUTE-216, ROUTE-263, ROUTE-276–ROUTE-278, their INT-, INT-217 and GAP-071; GAP-066 and GAP-067 stay mapped, closed at its merge.
- **Change 15 ([DIR-101](../00-governance/DECISION_LOG.md#dir-101--the-owners-decisions-on-p11q-07-of-04-and-the-labels)):** four entries added by the explicit rule, ROUTE-180, INT-180, ROUTE-264 and INT-264; GAP-068 and GAP-071 stay mapped, closed.

Every count above is computed by script from the matrix in §2 and the Task list of [TASK_PLAN](TASK_PLAN.md#4-the-task-list).

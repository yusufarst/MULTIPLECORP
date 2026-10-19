# TASK-060 — Reports, the consolidated report and exports

Status: REVIEW | Updated: 2026-10-20 | Owner: Planning

Written by WF-PC5-W's writer of bundle W3 of run PC-5 on 2026-10-19 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99 — a REVIEW draft of P11, approved, or not, with the [Task plan](TASK_PLAN.md) at OG-4. **No Task starts before the Owner's execution authorization at OG-4.** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** TODO

**RISK CLASS:** HIGH

**EXECUTOR:** AGENT

**OBJECTIVE:**

Build the reports and their exports: PG-051 *Laporan* — the stock, purchase, sales, receivable, payment, profit and cash-flow reports, each by its read class, filtered by period and company, one section per company for an Admin —; PG-052 *Laporan Gabungan*, the Owner's consolidated figures and the inter-company allocation report; and PG-053 *Ekspor Saya*, the requester's own export requests with their states — "Ekspor" requesting an Excel or PDF export as a job under the page's own query and filters (`Reporting RequestExport`; ROUTE-283), "Unduh" downloading it re-authorized at request time (`Reporting DownloadExport`; ROUTE-284).

**WHY THIS EXISTS:**

CAP-11 and AC-11 require reports that keep the five concepts apart — sales value, active receivable, Cash-In, actual Cost/HPP and Cash-Out — and reconcile with their source records, with no SPJ or SIPLAH value turned into profit (O-05; REF-044; REF-045; BR-FIN-01); CAP-17 and AC-23 require company- and date-filtered reports whose Excel, PDF and print outputs reconcile with those filters and protect private data (REF-046; REF-047). A report or export computed before the scope filter, or delivered after a revocation, leaks another company's S3 data (CS-07; DP-07; architecture challenge 8b), and a spreadsheet cell can carry a formula payload (FL-09).

**DEPENDS ON:**

- TASK-022 — Jobs, files and the document renderer
- TASK-037 — Purchases — project and replenishment purchases
- TASK-051 — Payments, applications and bank statement lines
- TASK-054 — Expenses, disbursements and other cash

**BLOCKS:**

- TASK-062 — Critical journeys A — the golden flow, purchasing, fulfilment, counts, scanning and SIPLAH
- TASK-063 — Critical journeys B — finance, corrections, reports, documents, completion and the checklist
- TASK-064 — Critical journeys C — isolation, sign-in, access, search, approvals, import, language, masters, command identity and status
- TASK-065 — UI/UX consistency gate, the responsive matrix and the anti-slop audit
- TASK-066 — Accessibility — WCAG 2.2 AA across every page
- TASK-067 — Localization completeness and the per-page copy lists
- TASK-068 — Performance targets and query budgets in CI
- TASK-069 — Security test matrix, route integrity and zero dead interactions

**PARALLEL SAFE:** NO — its report query classes and the single export job read every module's facts and share the one export overlap lock

**IN SCOPE:**

- PG-051 with NAV-051 and its view ROUTE-051 (any of `stock.view`, `projects.view`, `finance.view`, `cost.view` and `profit.view`, each report by its read class RD-02–RD-06; SCR-47): the stock report — ON HAND, RESERVED, UNUSABLE, AVAILABLE and the remaining reservations (CALC-01, CALC-02), a pending unattributed quantity as its own line, a company's view showing only its own exposure (UXS-39; MSG-41) —; the purchase report with remaining receipts and deliveries (CALC-03, CALC-04); the sales report on issued non-void invoice values without output tax (CALC-08); the receivable report with ageing per company — the chart with its table (D-IA-15; GR-23) —; the payment report; project, company and period profit and margin (CALC-09–CALC-11; REF-045); managerial cash flow, Cash-In against Cash-Out by business date and company (CALC-14); each concept on its own business date (CALC-13); one section per company for an Admin, no total across companies (D-IA-12; CS-08); configured tax, fee and VA components shown as recorded, distinct from prices (REF-016; BR-FIN-17)
- the period control with presets bound to the periods (GR-19) and the company filter, both whitelisted address keys on the registered date indexes; each report read in one snapshot so its totals agree (PF-27; TX-10)
- PG-052 with NAV-052 and its view ROUTE-052 (`reports.consolidated`; SCR-48): the consolidated figures CALC-12 across every company, and the inter-company allocation report — the source's draws and the consuming projects' allocated costs (J-INV-07; RD-07); entered from *Laporan* and *Beranda*'s "Rincian angka"; the Owner's consolidated receivable figure (J-FIN-02)
- ROUTE-283 with INT-283, "Ekspor" on PG-051 and PG-052 and "Minta Lagi" on PG-053 (`Reporting RequestExport`; `reports.export`, the consolidated report `reports.consolidated`): the request admitted in one step under the requester's row lock — a third request while two are in progress refused with MSG-22, ten per hour (WS-12; H6-04) —; the `export_requests` record holding the requester, report, whitelisted filters, company set, view-capability set and masking context (DP-07); the row bound of 200,000 checked before generation — at admission where its count is bounded, otherwise by the job before it generates, shown *Gagal* with MSG-21's text —, the choice IP-12 leaves to P11 made in this Task and recorded with its evidence (PF-28; UXH-11)
- the export job `GenerateExport` (JB-03): one export at a time across accounts under the shared overlap lock, re-authorized before producing (RV-07), the page's own query class and filters in one snapshot (TX-05; ARCHITECTURE §14), keyed chunks through a streaming writer, Excel cells typed and every text cell neutralized as FL-09 states, the file a private EXPORT file of the requester, Indonesian whatever the interface language, its column headers included (LOCALIZATION §3.4)
- PG-053 with NAV-053 and its view ROUTE-053 (`reports.export`, the requester only; SCR-49): *Diminta*, *Diproses*, *Siap Diunduh*, *Gagal* and *Tidak Tersedia*, polled as background work (IP-12; UXI-12); "Unduh" by ROUTE-284 with INT-284 — an ordinary browser download, served only READY and only after the requester's current scope, capabilities and masking context are compared with the recorded ones, refused as AZ-11 otherwise; a withheld request offering no download (RV-08; UXS-23); "Minta Lagi" a new request
- the print view of each report — the page prepared for paper with its filters as a line of text, its header and footer (DESIGN_SYSTEM §17; DP-19)

**OUT OF SCOPE:**

- the dashboards' period figures and signals (TASK-058); the renditions of business documents and their IP-12 states (TASK-029; UXI-29; UXS-09); the lists of each module (their Tasks)
- RV-08's WITHHELD marking inside the access-changing commands (TASK-024); the queue, the private storage and the status reads of ROUTE-285 (TASK-022); the cleanup of expired and withheld export files (P9; HO-29)
- CJ-14 end to end (TASK-063)

**REFERENCES:**

- [BUSINESS_RULES §5](../02-domain/BUSINESS_RULES.md#5-calculation-semantics) CALC-01–CALC-14; [FIN](../02-domain/BUSINESS_RULES.md#finance-fin) BR-FIN-01, BR-FIN-17; [§6](../02-domain/BUSINESS_RULES.md#6-cross-domain-interaction-rules) BR-XD-05
- [PRODUCT_OVERVIEW](../01-product/PRODUCT_OVERVIEW.md#problems-and-operational-outcomes) O-05; [V1_SCOPE](../01-product/V1_SCOPE.md#must-ship) CAP-11, CAP-17; [ACCEPTANCE_CRITERIA](../01-product/ACCEPTANCE_CRITERIA.md#product-acceptance-contracts) AC-11, AC-23; [REFERENCE_COVERAGE](../01-product/REFERENCE_COVERAGE.md#pdf-capability-inventory) REF-016, REF-044–REF-047
- [PERFORMANCE §7](../06-api-performance/PERFORMANCE.md#7-dashboards-reports-and-exports) PF-24–PF-30, PF-43; [§6](../06-api-performance/PERFORMANCE.md#6-query-budgets) QB-11, QB-15; [§12](../06-api-performance/PERFORMANCE.md#12-index-register-and-verification-procedure) IX-05, IX-11 and the sales-path candidate
- [ARCHITECTURE §11](../04-architecture/ARCHITECTURE.md#11-queue-and-background-jobs) `GenerateExport`; [§14](../04-architecture/ARCHITECTURE.md#14-reporting-and-query-layer)
- [PERMISSIONS_MATRIX §4](../05-security/PERMISSIONS_MATRIX.md#4-capability-catalogue) `reports.export`, `reports.consolidated`; [§6](../05-security/PERMISSIONS_MATRIX.md#6-company-scope-rules) CS-07, CS-08; [§7.3](../05-security/PERMISSIONS_MATRIX.md#73-special-projections) PJ-05–PJ-07, PJ-22; [§7.4](../05-security/PERMISSIONS_MATRIX.md#74-read-classes) RD-02–RD-07; [§11](../05-security/PERMISSIONS_MATRIX.md#11-data-path-coverage-matrix) DP-05, DP-07–DP-09, DP-19
- [SECURITY §5](../05-security/SECURITY.md#5-file-security) FL-07, FL-09; [§4](../05-security/SECURITY.md#4-web-and-input-security) WS-12; [§6](../05-security/SECURITY.md#6-audit-and-security-logging) LG-02
- [CONCURRENCY_IDEMPOTENCY §15](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s14-17-numbering-jobs-revocation.md#15-jobs-scheduler-and-queue) JB-03, JB-09; [§16](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s14-17-numbering-jobs-revocation.md#16-authorization-and-revocation-races) RV-07, RV-08; [§17](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s14-17-numbering-jobs-revocation.md#17-p5-obligations-h6-01h6-12) H6-04; [§18](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s18-scenarios.md#18-mandatory-concurrency-and-failure-scenarios) M-23
- [ROUTE_CONTRACTS](../03-workflows/ROUTE_CONTRACTS.md#reading-search-scanning-and-exports-reporting-pg-009-pg-051pg-053) ROUTE-283, ROUTE-284; [§2](../03-workflows/ROUTE_CONTRACTS.md#2-page-views--route-001route-065) ROUTE-051–ROUTE-053
- [INTERACTION_CONTRACTS](../03-workflows/INTERACTION_CONTRACTS.md#reading-search-scanning-and-exports-reporting-pg-009-pg-051pg-053) INT-283, INT-284
- [NAVIGATION_CONTRACTS §2.6](../07-ux-design/NAVIGATION_CONTRACTS.md#26-reports-master-data-settings-and-owner-areas) NAV-051–NAV-053
- [INFORMATION_ARCHITECTURE §8.6](../07-ux-design/INFORMATION_ARCHITECTURE.md#86-reports-master-data-settings-and-owner-areas) PG-051–PG-053; [§4](../07-ux-design/INFORMATION_ARCHITECTURE.md#4-design-decisions) D-IA-12, D-IA-15; [§8.7](../07-ux-design/INFORMATION_ARCHITECTURE.md#87-the-approved-screens-scr-01scr-60-and-their-pages) SCR-47–SCR-49; [§11](../07-ux-design/INFORMATION_ARCHITECTURE.md#11-page-addressing)
- [ADMIN_FLOW §8](../07-ux-design/ADMIN_FLOW/s06-09-session-forms-lists.md#ip-12--derived-background-work) IP-12; [§3](../07-ux-design/ADMIN_FLOW/s01-04-foundations.md#3-derived-interaction-requirements) UXI-09, UXI-12; [§10.3](../07-ux-design/ADMIN_FLOW/s10-a-journeys.md#103-inventory-and-purchasing) J-INV-07; [§10.6](../07-ux-design/ADMIN_FLOW/s10-b-journeys.md#106-finance) J-FIN-02; [§15](../07-ux-design/ADMIN_FLOW/s14-15-messages-scenarios.md#lists-search-scanner-and-exports) UXS-36, [UXS-23](../07-ux-design/ADMIN_FLOW/s14-15-messages-scenarios.md#visibility-and-authority), [UXS-39](../07-ux-design/ADMIN_FLOW/s14-15-messages-scenarios.md#workflow-presentations); [§16](../07-ux-design/ADMIN_FLOW/s16-17-handoff-traceability.md#16-handoff-obligations) UXH-11; [§14](../07-ux-design/ADMIN_FLOW/s14-15-messages-scenarios.md#14-message-catalogue) MSG-21, MSG-22, MSG-41
- [DESIGN_SYSTEM §10](../07-ux-design/DESIGN_SYSTEM.md#10-component-composition) Chart; [§17](../07-ux-design/DESIGN_SYSTEM.md#17-print-views); [Annex B](../07-ux-design/DESIGN_SYSTEM.md#annex-b-the-carried-items-gr-01gr-23) GR-19, GR-23
- [PERFORMANCE_TARGETS §2](../08-testing/PERFORMANCE_TARGETS.md#2-the-query-budgets) QB-11, QB-15; [§3](../08-testing/PERFORMANCE_TARGETS.md#3-the-measured-targets) PFM-03, PFM-16; [SECURITY_TEST_MATRIX §3](../08-testing/SECURITY_TEST_MATRIX.md#3-the-matrix) SM-01, SM-11, SM-25; [TEST_STRATEGY §7](../08-testing/TEST_STRATEGY.md#7-browser-end-to-end-tests-and-critical-journeys) CJ-14
- Architecture challenge — the sequence 8b of the [P11 gate record](evidence/P11_QUALITY_GATE.md#whole-system-architecture-challenge-sp-98), their assertions carried into this Task's acceptance criteria and tests
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (67): O-05; CAP-11; CAP-17; AC-11; AC-23; OS-02–OS-03; REF-016; REF-044–REF-047; IMG-07; IMG-09; OWN-01–OWN-02; OWN-05; CALC-02; CALC-04; CALC-10; CALC-12–CALC-14; ROUTE-051–ROUTE-053; ROUTE-283–ROUTE-284; INT-283–INT-284; H6-04; M-23; HO-09; HO-18; PF-24–PF-30; PF-43; D-IA-12; D-IA-15; PG-051–PG-053; SCR-47–SCR-49; UXI-09; UXI-12; UXI-29; IP-12; J-INV-07; J-FIN-02; UXS-09; UXS-23; UXS-36; UXS-39; UXH-11; GR-19; GR-23; NAV-051–NAV-053; component Chart

**EXPECTED MODULES / FILE AREAS:**

- the Reporting module's report query classes — one per report, each implementing its CALC- definitions —, `ShowReports`, `ShowConsolidatedReport`, `ShowOwnExports`, RequestExport and DownloadExport, the `GenerateExport` job and its Excel and PDF writers (RC-02; ARCHITECTURE §3, §11, §14); `resources/js/Pages/Reporting` for PG-051–PG-053 and their print views; `tests/Feature/Reporting`, `tests/Integration` for the export job

**BUSINESS RULES:**

- BR-FIN-01 and AC-11: sales value, active receivable, Cash-In, actual Cost/HPP and Cash-Out stay five figures; cost recognition alone creates no Cash-Out; no formula turns SPJ, SIPLAH or reference values into revenue or profit; output tax is not revenue (BR-FIN-17; CALC-08)
- CALC-01–CALC-14 as BUSINESS_RULES §5 defines them, each concept on its own business date (CALC-13), CALC-12 the Owner's alone; a pending unattributed quantity outside every company's figures, a company's view showing its own exposure only (BR-INV-13; PJ-07)
- BR-XD-05: committed payments, stock changes and corrections show in the reports without waiting for completion; every figure resolves to a CALC- definition and its scope filter

**ARCHITECTURE RULES:**

- read-only query classes applying the scope resolver first; an export reuses the page's own query class and filters, so it can never show more than the page (ARCHITECTURE §14; PF-28); heavy work is a job (D-PF-05) and nothing slow runs inside a transaction (ARCHITECTURE §15)
- an on-screen report bounded by a date range and a company filter on the registered date indexes and read in one snapshot; a report that misses QB-11 is corrected under AS-07 and stays on the screen, an export beside it, never instead (PF-27)

**SECURITY / PERMISSION REQUIREMENTS:**

- ROUTE-051 by each report's read class; ROUTE-052 `reports.consolidated` (OWNER_ONLY; OD-14), an Admin's request answering PG-005; ROUTE-053, ROUTE-283 and ROUTE-284 `reports.export` with the report's view capabilities (PERMISSIONS_MATRIX §4), the requester only (H6-04); exports ten per hour and two at once per account (WS-12)
- DP-07: each export records its company set, view capabilities and masking context and is re-authorized at job start (RV-07) and at delivery (RV-08), withheld and purged otherwise; a download an ordinary request through the authorizing controller, never a signed link alone, with FL-07's attachment headers (PF-29; RB-04; DP-05); FL-09's formula neutralization on every exported cell (TM-16; TM-46)
- prefetch off on these S3 pages (PF-35); the print view identical to its page (DP-19)

**COMPANY SCOPE AND FIELD PROJECTION:**

- every report and export statement carries the scope filter before aggregation, ranking and limits (CS-07; architecture challenge 8b); an Admin's report one section per company, no figure summed or ranked across companies (CS-08; D-IA-12; PF-25); a company filter intersected with scope (DP-02)
- each figure takes the highest class and every family of its inputs — profit with `profit.view`, a company's stock value with `cost.view` (PJ-22); the allocation report's source and consuming sides as PJ-05 and PJ-06 project them, the Owner seeing both (RD-07); the export file holding only what its recorded context allowed (DP-07)

**CONCURRENCY / IDEMPOTENCY:**

- ROUTE-283 carries no `command_id` (RB-03): admission is one TX-04 step under the requester's row lock (LK-03) counting the requests PENDING or RUNNING and not stale (H6-04); JB-03 runs one export at a time across accounts under the overlap lock, claims its request by a conditional update and is swept by JB-09 when stale (CONCURRENCY_IDEMPOTENCY §15)
- an export generated before a revocation and delivered after it is withheld and its download refused (M-23; RV-08); the export reads one snapshot (TX-05); a report reads one snapshot (TX-10) and takes no row lock (PF-12)

**AUDIT:**

- EXPORT_REQUESTED and EXPORT_DOWNLOADED security events with the requester, the report and its context, written outside business transactions (SECURITY LG-02; DP-07); export volume one of LG-07's detection signals delivered by P9 (H9-10)
- a report or a download writes no business audit event (BR-XC-01 covers committed actions)

**DATABASE IMPACT ASSESSMENT:**

- Schema change: NONE — uses the tables of TASK-005–TASK-009; a missing column is an executor discovery (AGENT_OPERATING_MODEL)
- Migration required: NO
- Existing data transformation: NONE
- Direct production DB access: FORBIDDEN
- Data-loss risk: NONE KNOWN

**MODULARITY / EXTENSIBILITY:**

- Module boundary affected: Reporting, tier 7, reading every module through its query classes; the export job on TASK-022's queue and storage (ARCHITECTURE §3, §11)
- Stable core kept provider-neutral: YES
- External/vendor-specific code isolated: YES — the Excel and PDF writers behind the export job, typed cells only (FL-09)
- Replaceable adapter/contract required: NO
- Provider lock-in risk: NONE beyond the Owner's baseline stack and the writers verified under DEP-07
- Future extension impact: LOW — a further report is one query class
- Speculative abstraction avoided: YES — no report designer, materialized summary or data warehouse (D-PF-07)

**AUTH IMPACT:**

- Authentication change: NONE
- Authentication implementation: LARAVEL_NATIVE — decided once ([EXECUTION_CONTEXT](EXECUTION_CONTEXT.md)), reused, never redesigned
- Auth profile: N/A — this Task does not touch authentication
- Google sign-in: N/A — not touched
- Google registration behavior: N/A — not touched
- Google credentials owner action required: NO
- Authorization change: NONE — `reports.export` and `reports.consolidated` exist in the catalogue (TASK-011)
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

- Context7: REQUIRED — the framework's queued jobs and overlap locks; a streaming Excel writer with typed cells and a PDF path for tables, chosen and verified under DEP-07; PostgreSQL snapshot reads
- Graphify: OPTIONAL — every module's facts exist by now
- Browser E2E: REQUIRED — CJ-14's reports and exports, UXS-36, UXS-23's export
- Other: FX-01's numeric annex for the figures and the scale fixtures FX-06 for QB-11 and QB-15 (TASK-003)
- Missing safe tool behavior: AUTO-INSTALL project-locally inside this Task (AICWDF §6.3) and record it in TOOLCHAIN; a paid tier or a privileged installation STOPS for the Owner (COST_POLICY)

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: refreshed before the change when Graphify is available ([TOOLCHAIN](../00-governance/TOOLCHAIN.md)); otherwise a targeted search of the facts each CALC reads and their date indexes
- Pre-change questions: which date and company indexes serve each report; which families each figure needs; how the sales path joins invoices to their current versions
- Expected affected areas: Reporting's read paths and the export job; no write path beyond `export_requests`

**CONTEXT7:**

- Topics to verify: job overlap locks and their expiry, release-with-delay; streaming Excel writing with typed cells; `REPEATABLE READ` read-only snapshots in the framework's database layer
- Version-sensitive assumptions: none from memory

**UI / UX REQUIREMENTS:**

- Follow global design rules: YES
- Indonesian default: YES
- English support: YES — the interface; the export file stays Indonesian (LOCALIZATION §3.4)
- Plain-language Indonesian: YES — the report and column names from the glossary; MSG-21, MSG-22 and MSG-41 as written; the export states of IP-12
- Language switch impact: NONE — the filters are address keys and stay; no unsent input
- Mobile-first: YES — PG-051 and PG-052 `[desk]` and dense, readable on the phone; PG-053 `[both]` (NAV-051–NAV-053)
- Responsive: YES
- Adaptive: YES — PG-053's list as dense rows on the phone (PT-06)
- Visual breathing room: REQUIRED — the dense mode on the reports only where OC-09 names them (TEST_STRATEGY §9)
- Comfortable density: REQUIRED
- shadcn/ui reuse: the data table (PT-05), filters with the period presets and the company filter (PT-07; GR-19), Chart with its table (DESIGN_SYSTEM §10; GR-23), status badges for the export states (PT-13), the loading, empty and error states (PT-19), the print view (DESIGN_SYSTEM §17)
- Design reference: PG-051–PG-053 as INFORMATION_ARCHITECTURE §8.6 states them; the Golden Frontend Reference for presentation only (DIR-061 SD-02)
- Primary action: "Ekspor" on PG-051 and PG-052; "Unduh" on a ready request of PG-053
- Secondary actions: the print view; "Minta Lagi" on a failed request; *Laporan Gabungan* and *Ekspor Saya* from *Laporan*
- Destructive actions: none
- Redundancy constraints: one "Ekspor" per report; the consolidated export only on PG-052
- Loading state: a report's spinner after 300 ms; the export states *Diminta* and *Diproses* polled as background work (PT-19; IP-12)
- Empty state: a period without facts — one sentence with the filter kept; no export yet on PG-053 — one sentence (PT-19)
- Error state: MSG-22 and MSG-21 in place; *Gagal* with "Minta Lagi"; *Tidak Tersedia* with no download, its address answering as AZ-11 (IP-12; RV-08)

**ROUTE IMPACT:**

- ROUTE-051, ROUTE-052, ROUTE-053 (the views); ROUTE-283 (export request); ROUTE-284 (download); ROUTE-285 for the export states' polls (TASK-022)

**INTERACTION CONTRACTS:**

- INT-283, INT-284; INT-014 for the download's headers (TASK-022); INT-007 for the filters (TASK-016)

**NAVIGATION CONTRACT:**

- Parent: PG-051 — *Menu lengkap* › *Laporan*; PG-052 — PG-051 or PG-006; PG-053 — PG-051 (NAV-051–NAV-053)
- Entry: *Menu lengkap* › *Laporan*; *Laporan* › *Laporan Gabungan* and *Beranda*'s "Rincian angka"; *Laporan* › *Ekspor Saya*
- Back action: PG-052's "Kembali" → the origin; PG-053's "Kembali" → PG-051
- Cancel: N/A — a report takes no input to discard; its filters are address keys
- Success destination: "Ekspor" → PG-053 with the request (NAV-051, NAV-052)
- Error behavior: NV-04; a limit reached answers in place (ROUTE-283; WS-12)
- State preservation: period, company and filters in the address on PG-051 and PG-052; filters, sort, tab and cursor on PG-053 (IP-18; NV-05)
- Mobile behavior: PG-051 and PG-052 `[desk]`, dense, readable on the phone; PG-053 `[both]`

**ACCEPTANCE CRITERIA:**

1. Each report keeps sales value, active receivable, Cash-In, actual Cost/HPP and Cash-Out apart: a purchase or a cost attribution creates no Cash-Out, an evidenced disbursement does, without counting the cost twice, and no SPJ, SIPLAH or reference value becomes revenue or profit (AC-11; BR-FIN-01; O-05).
2. The figures equal FX-01's numeric annex where it applies and reconcile with their source records for every project, company and period, each concept on its own business date (AC-11; CALC-13; REF-044; REF-045).
3. An Admin's report shows one section per company in scope and no figure summed or ranked across companies; *Laporan Gabungan* and its export open only for the Owner (CS-08; D-IA-12; OD-14).
4. A report's period and company filters apply inside its statements before aggregation; a filter naming a company outside scope is ignored or refused, never widening the result (CS-07; DP-02; architecture challenge 8b).
5. A pending unattributed quantity appears as its own line, outside every company's figures, a company's view showing only its own exposure (UXS-39; PJ-07).
6. An export's content equals the report's rows for the same filters and context; every text cell starting with `=`, `+`, `-`, `@`, a tab, a carriage return or a line feed, or a full-width variant, is neutralized; numbers stay numbers; the file and its headers are Indonesian (AC-23; FL-09; LOCALIZATION §3.4).
7. A third concurrent request is refused with MSG-22 and a request above the row bound with MSG-21 — at admission or as *Gagal* before generation, as this Task records —; PG-053 shows the five states to the requester only (PF-28; WS-12; UXS-36; IP-12).
8. An export generated before a revocation of one filtered company, of a view capability or of a role, or during generation, is withheld and its download refused exactly like a nonexistent one (M-23; RV-08; UXS-23; SM-11).
9. A download is an ordinary browser request authorized at request time with the attachment headers of FL-07; no signed link authorizes it alone (PF-29; DP-05).
10. The allocation report shows each source draw and each consuming project's allocated cost to the Owner, and each side only its own identifiers to an entitled Admin (J-INV-07; PJ-05; PJ-06).
11. A report on screen answers within QB-11's count — at most six statements in one snapshot — and an export of 100,000 rows runs with bounded memory, a rendition never waiting behind two exports; QB-11's 1 s and PFM-16's 60 s at 10x are measured on staging by TASK-068's scripts in TASK-079, never reported as met from CI (QB-11; QB-15; PFM-16; PERFORMANCE_TARGETS §1).
12. The print view prints the same records, fields and figures as the page with its filters as text (DESIGN_SYSTEM §17; DP-19).

**TEST REQUIREMENTS:**

- Lint: TL-01
- Typecheck: TL-02
- Unit: each report's CALC- figures on FX-01; FL-09's cell neutralization (TL-03)
- Feature: ROUTE-051–ROUTE-053, ROUTE-283 and ROUTE-284 per persona and read class (TL-04)
- Integration: the export job end to end — admission, the overlap lock, a stale claim swept, the row bound, a revocation during generation (M-23; HO-18); the snapshot's agreement of totals (TL-05)
- Authorization: each report's read class; `reports.consolidated`; `reports.export` with the report's view capabilities; the requester-only download (TL-06)
- Route integrity: ROUTE-051–ROUTE-053, ROUTE-283 and ROUTE-284 audited (TL-07)
- Interaction: INT-283 and INT-284 with their *Test* — authorization at request time, the denied and unknown cases alike (TL-08)
- Browser E2E: CJ-14's reports with company and date filters, an export, its row bound and a third concurrent export; UXS-36 (TL-09)
- Localization/language switch: both languages on PG-051–PG-053; the export Indonesian in both (TL-10)
- Visual breathing room/density: TEST_STRATEGY §9 — the dense mode only on OC-09's screens
- Responsive: TL-11
- Accessibility: TL-12 — the chart's table, table headers and the print view's structure
- Production build: TL-13
- Security: SM-01 for the report filters and the export; SM-11 for formula neutralization and the export delivered after a revocation; SM-25 for the money, cost and profit families on reports and exports; SM-04 for OD-14's consolidated view (TL-14)
- Performance: QB-11 and QB-15 through PFM-01, PFM-02 and PFM-16; PFM-03's sales-path and revenue-figure candidates measured at 10x (TL-15)
- Migration: N/A — no migration or import code in this Task
- Other: AC-23's export-content and filter assertions; the export-admission choice of UXH-11 recorded

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

- TASK-062 — when its other dependencies are DONE and its READY gate holds

**DO NOT DO:**

- add to V1 — a capability, page, route, field, integration, document type, report or paid service — or drop, defer or narrow a V1_REQUIRED requirement; a Task that would need either raises an Owner question and waits ([DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-20)
- access the VPS, production, its data or a credential, or put a secret, real data, a host detail or production configuration in the repository ([INFRASTRUCTURE](../09-operations/INFRASTRUCTURE.md) INF-01, INF-05; SECURITY SX-01, SX-04, SX-06; [PRODUCTION_DATA_SAFETY](../00-governance/PRODUCTION_DATA_SAFETY.md))
- add AI, model or tool attribution to a commit — the Owner's configured identity only ([AGENTS.md](../../AGENTS.md#git-commit-attribution); DIR-022)
- start before the Owner's execution authorization at OG-4, or mark DONE without the required tests passing and the completion evidence ([TASK_PLAN](TASK_PLAN.md#2-rules-of-the-plan); [DEFINITION_OF_DONE](../08-testing/DEFINITION_OF_DONE.md#2-the-checklist))
- weaken, skip or delete a valid test to obtain a green run, or adopt a paid tier without the Owner's COST_POLICY decision (DOD-05; [COST_POLICY](../00-governance/COST_POLICY.md))
- compute a figure by a formula the CALC- definitions do not state, or turn SPJ, SIPLAH or reference values into revenue or profit (BR-FIN-01; FS-13)
- serve an export through a signed link alone, to anyone but its requester, or after its recorded context is no longer covered (DP-07; RV-08)
- write a formula into an exported cell or export more than the page shows (FL-09; ARCHITECTURE §14)

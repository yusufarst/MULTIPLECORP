# TASK-058 — Home, Work and Review — signals and the action queue

Status: REVIEW | Updated: 2026-10-19 | Owner: Planning

Written by WF-PC5-W's writer of bundle W3 of run PC-5 on 2026-10-19 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99 — a REVIEW draft of P11, approved, or not, with the [Task plan](TASK_PLAN.md) at OG-4. **No Task starts before the Owner's execution authorization at OG-4.** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** TODO

**RISK CLASS:** STANDARD

**EXECUTOR:** AGENT

**OBJECTIVE:**

Build the three home pages over the derived signals QS-01–QS-24: PG-006 *Beranda*, the Owner's landing page — the decisions and exceptions first with the Owner's security notices, the four KPIs, the projects needing attention, the operational queues as one folded line and the period figures with the receivables position and the ageing chart —; PG-007 *Pekerjaan*, every account's work grouped by what has to happen next; and PG-008 *Tinjauan*, the Owner review queue — each signal one bounded statement read scope-first, shown per company, each row leading to where its next action happens (ROUTE-006–ROUTE-008; INT-019; INT-020).

**WHY THIS EXISTS:**

CAP-17 and AC-23 give the Owner group and company summaries and every Admin the actionable queues and alerts for incomplete work, low stock and overdue bills, updated by committed actions without waiting for completion (BR-XD-05; REF-007; REF-008; REF-048; IMG-09). Signals are projections over facts and decisions, never stored truth (WORKFLOWS §10), so each must stay one bounded statement within QB-10 and never count, sum or reveal another company's records (CS-07; CS-08; PF-24; PF-25). The review queue lists what ADM+ authority already did, with nothing to approve (PERMISSIONS_MATRIX §10), and two of its items exist only as security events (GAP-034); *Beranda*'s receivables position must stay inside QB-10 (GAP-060).

**DEPENDS ON:**

- TASK-043 — Stock count (opname) and discrepancies
- TASK-054 — Expenses, disbursements and other cash
- TASK-055 — Project completion and Force Complete

**BLOCKS:**

- TASK-062 — Critical journeys A — the golden flow, purchasing, fulfilment, counts, scanning and SIPLAH
- TASK-063 — Critical journeys B — finance, corrections, reports, documents, completion and the checklist
- TASK-064 — Critical journeys C — isolation, sign-in, access, search, approvals, import, language, masters, command identity and status
- TASK-065 — UI/UX consistency gate, the responsive matrix and the anti-slop audit
- TASK-066 — Accessibility — WCAG 2.2 AA across every page
- TASK-067 — Localization completeness and the per-page copy lists
- TASK-068 — Performance targets and query budgets in CI
- TASK-069 — Security test matrix, route integrity and zero dead interactions

**PARALLEL SAFE:** NO — its signal statements read the guards and roots of every module and set the shared query budget QB-10

**IN SCOPE:**

- the signal register of PERFORMANCE §7: QS-01–QS-24, each a read-only query class in the Reporting module — one bounded statement, QS-15 one set query per predicate (TASK-055's predicates) —, reading its source rows by the index or open root the register names, one branch per company in scope, a pool signal (QS-17, QS-18, QS-22) read once (PF-24; ARCHITECTURE §14); each row's destination and empty sentence as INFORMATION_ARCHITECTURE §9.1 places them, and its audience as its read class (PJ-13; RD-02–RD-09)
- PG-006 *Beranda* with NAV-006 and its view ROUTE-006 (`review.view`, OWNER_ONLY; *Gabungan* `reports.consolidated`; SCR-05; D-IA-07): its five deferred groups — G1 *Perlu Perhatian Owner* (the Owner's security notices, QS-14's new items since the previous sign-in, QS-16, QS-18's attribution, QS-20, QS-22 and the Owner-only overdue projects QS-23), G2 *Operasional* folded into *Antrean operasional* with "Buka Pekerjaan", G3 *Penagihan & Kas*, G4 *Penyelesaian Proyek* and G5 *Angka Periode* —; *Perlu keputusan Anda*, two rows on the phone, then "Lihat semua keputusan"; the four KPIs — *Nilai penjualan (invoice terbit)*, *Kas masuk (uang diterima)*, *Piutang aktif*, *Laba manajerial* — as KPI tiles without delta or sparkline (SLOP-18); *Proyek yang perlu perhatian*; the view switch *Gabungan* · one company filtering the groups and figures (IA §9)
- G5's period figures CALC-08–CALC-14 per company and *Gabungan*, "Angka per {waktu} WIB", read in one snapshot (TX-10; PF-43), with the receivables position — one grouped statement over `invoice_balances` per company in scope, CALC-05's outstanding total and CALC-07's due-date buckets, the *migrasi* bucket included and a disputed amount kept with its indicator (`DIR-079` CB-03; GAP-060) —; in the one-company view the chart *Umur piutang* with its table (Chart; GR-23; D-IA-15); "Rincian angka" → PG-052 (NAV-052)
- the Owner's security notices heading G1: a credential link the Owner issued being used (MSG-29's Owner variant) and LG-07's single-event alerts — OWNER_RECOVERY_LINK_ISSUED, AUTHORIZATION_ANOMALY, an Owner sign-in from an address not seen in 30 days —, each its neutral type, its time and a link to PG-064 or PG-060, derived by one bounded statement over the security events since the Owner's previous sign-in, nothing marked read (IA §9; INT-020; DP-11)
- PG-007 *Pekerjaan* with NAV-007 and its view ROUTE-007 (every active account, S0; `DIR-081` OB-03; SCR-06; D-IA-08): its four signal groups — *Barang: terima, keluarkan, kirim* (QS-01–QS-06, QS-16, QS-17, QS-18's findings and stale counts, QS-20 and the dispatch-ready lines QS-24 with "Lihat Semua" → PG-023), *Dokumen dan kasus* (QS-07, QS-08, QS-21), *Tagihan dan pembayaran* (QS-09–QS-13, QS-19) only with `finance.view`, *Penyelesaian proyek* (QS-15, its money items only with `finance.view`) —, each present only with its read class; Bayu's "Berikutnya" card first on the phone; a view filter over the companies in scope with counts per company, never summed (CS-08; PF-15); an account without a company grant shown MSG-38 (RG-04; J-ACC-01)
- PG-008 *Tinjauan* with NAV-008 and its view ROUTE-008 (`review.view`; `OPS ShowReviewQueue`; SCR-07; D-IA-11): *Kondisi yang Masih Terbuka* — recovered money linked to a written-off invoice, refunds with an unbacked remainder, invoices left outstanding after a payment —; *Aktivitas untuk Ditinjau* in four tabs, *Aktivitas*, *Keamanan*, *Bukti Gudang Bersama*, *Impor* — ADM_PLUS and OWNER_ONLY audit events, acknowledged duplicate warnings, pool-evidence uploads, out-of-scope import collisions and the security kinds AUTHORIZATION_ANOMALY, EVIDENCE_DUPLICATE_UNDISCLOSED and IMPORT_COLLISION_UNDISCLOSED —, newest first in the chosen window (7 days by default), filtered by company and actor, the divider "Tercatat setelah Anda masuk sebelumnya ({waktu})", "Buka Catatan", "Muat Lagi" and no total; no approve, dismiss or read control (IA §9.2; UXS-18; UXS-19; UXS-40; GAP-034)
- INT-019, a decision or signal row opening its record or worklist for an account holding that next action's capability and otherwise the record's view; INT-020, a security notice opening its record (IA §9.1; PJ-13)
- refresh by the user ("Muat Ulang") or a poll no faster than once a minute naming its groups' props, as background requests that never extend the session, with the quiet "Diperbarui {waktu} WIB" (PF-26; PF-36; H7-13)

**OUT OF SCOPE:**

- each signal's source facts and the modules' guards (their Tasks); the completion predicates' composition (TASK-055); the reports, the consolidated report and exports behind "Rincian angka" (TASK-060); search and the shell's *Cari* (TASK-059; REF-050); PG-063 and PG-064 themselves (TASK-061)
- LG-07's aggregate alerts — bursts, spikes, volumes, allocation patterns — delivered by P9 (H9-10; TASK-075); alert delivery outside the application (TASK-080)
- any command on these pages: a dashboard runs none (IA §9)

**REFERENCES:**

- [WORKFLOWS §10](../03-workflows/WORKFLOWS/s10-14-signals-decisions-traceability.md#10-derived-signals-and-action-queue) QS-01–QS-24; [§11](../03-workflows/WORKFLOWS/s10-14-signals-decisions-traceability.md#11-cross-company-and-multi-admin-rules)
- [BUSINESS_RULES §6](../02-domain/BUSINESS_RULES.md#6-cross-domain-interaction-rules) BR-XD-05; [§5](../02-domain/BUSINESS_RULES.md#5-calculation-semantics) CALC-05, CALC-07–CALC-14
- [V1_SCOPE](../01-product/V1_SCOPE.md#must-ship) CAP-17; [ACCEPTANCE_CRITERIA](../01-product/ACCEPTANCE_CRITERIA.md#product-acceptance-contracts) AC-23; [REFERENCE_COVERAGE](../01-product/REFERENCE_COVERAGE.md#pdf-capability-inventory) REF-007, REF-008, REF-046–REF-048, REF-050, REF-059; [IMG-09](../01-product/REFERENCE_COVERAGE.md#image-and-non-tabular-pdf-inventory)
- [PERFORMANCE §6](../06-api-performance/PERFORMANCE.md#6-query-budgets) QB-10; [§7](../06-api-performance/PERFORMANCE.md#7-dashboards-reports-and-exports) PF-24–PF-30, PF-43 and the signal register; [§8](../06-api-performance/PERFORMANCE.md#8-images-and-inertia-props) PF-34, PF-36; [§4](../06-api-performance/PERFORMANCE.md#4-pagination) PF-15, PF-41
- [ARCHITECTURE §14](../04-architecture/ARCHITECTURE.md#14-reporting-and-query-layer)
- [PERMISSIONS_MATRIX §4](../05-security/PERMISSIONS_MATRIX.md#4-capability-catalogue) `review.view`, `reports.consolidated`; [§6](../05-security/PERMISSIONS_MATRIX.md#6-company-scope-rules) CS-07, CS-08; [§7.3](../05-security/PERMISSIONS_MATRIX.md#73-special-projections) PJ-11, PJ-13, PJ-22; [§7.4](../05-security/PERMISSIONS_MATRIX.md#74-read-classes) RD-02–RD-10; [§9](../05-security/PERMISSIONS_MATRIX.md#9-owner-only-denials) OD-14; [§10](../05-security/PERMISSIONS_MATRIX.md#10-owner-review-queue-access); [§11](../05-security/PERMISSIONS_MATRIX.md#11-data-path-coverage-matrix) DP-08, DP-11
- [SECURITY §6](../05-security/SECURITY.md#6-audit-and-security-logging) LG-02, LG-07
- [ROUTE_CONTRACTS §2](../03-workflows/ROUTE_CONTRACTS.md#2-page-views--route-001route-065) ROUTE-006–ROUTE-008; [INTERACTION_CONTRACTS §2](../03-workflows/INTERACTION_CONTRACTS.md#2-shared-interactions--int-001int-020) INT-019, INT-020
- [NAVIGATION_CONTRACTS §2.1](../07-ux-design/NAVIGATION_CONTRACTS.md#21-system-account-and-beranda) NAV-006–NAV-008
- [INFORMATION_ARCHITECTURE §8.1](../07-ux-design/INFORMATION_ARCHITECTURE.md#81-system-account-and-beranda) PG-006–PG-008; [§9](../07-ux-design/INFORMATION_ARCHITECTURE.md#9-dashboards-and-queues), [§9.1](../07-ux-design/INFORMATION_ARCHITECTURE.md#91-placement-of-every-signal), [§9.2](../07-ux-design/INFORMATION_ARCHITECTURE.md#92-the-owner-review-queue-scr-07); [§4](../07-ux-design/INFORMATION_ARCHITECTURE.md#4-design-decisions) D-IA-07, D-IA-08, D-IA-11, D-IA-15, D-IA-20, D-IA-21; [§8.7](../07-ux-design/INFORMATION_ARCHITECTURE.md#87-the-approved-screens-scr-01scr-60-and-their-pages) SCR-05–SCR-07
- [ADMIN_FLOW §10.1](../07-ux-design/ADMIN_FLOW/s10-a-journeys.md#101-access-and-master-data) J-ACC-01; [§15](../07-ux-design/ADMIN_FLOW/s14-15-messages-scenarios.md#workflow-presentations) UXS-40, [UXS-43](../07-ux-design/ADMIN_FLOW/s14-15-messages-scenarios.md#design), [UXS-18, UXS-19](../07-ux-design/ADMIN_FLOW/s14-15-messages-scenarios.md#duplicates); [§14](../07-ux-design/ADMIN_FLOW/s14-15-messages-scenarios.md#14-message-catalogue) MSG-29, MSG-38
- [DESIGN_SYSTEM §9.5](../07-ux-design/DESIGN_SYSTEM.md#95-dashboard-records-and-media) PT-21; [§10](../07-ux-design/DESIGN_SYSTEM.md#10-component-composition) Chart, KPI tile; [§18](../07-ux-design/DESIGN_SYSTEM.md#18-anti-ai-slop-mapping) SLOP-18, SLOP-28, SLOP-34, SLOP-38; [Annex B](../07-ux-design/DESIGN_SYSTEM.md#annex-b-the-carried-items-gr-01gr-23) GR-23
- [PERFORMANCE_TARGETS §2](../08-testing/PERFORMANCE_TARGETS.md#2-the-query-budgets) QB-10; [§3](../08-testing/PERFORMANCE_TARGETS.md#3-the-measured-targets) PFM-05, PFM-07, PFM-15; [TEST_STRATEGY §9](../08-testing/TEST_STRATEGY.md#9-the-uiux-consistency-gate) CA-10 and CB-03; [§7](../08-testing/TEST_STRATEGY.md#7-browser-end-to-end-tests-and-critical-journeys) CJ-09, CJ-13, CJ-22
- [GAP_REGISTER](../00-governance/GAP_REGISTER.md#gap-034--two-owner-review-items-have-no-record-to-be-listed-from) GAP-034; [GAP-060](../00-governance/GAP_REGISTER.md#gap-060--berandas-receivables-figure-and-ageing-chart-lie-outside-qb-10)
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (70): CAP-17; AC-23; REF-007–REF-008; REF-046–REF-048; REF-050; REF-059; IMG-09; QS-01–QS-24; ROUTE-006–ROUTE-008; INT-019–INT-020; PF-24–PF-30; PF-43; D-IA-07–D-IA-08; D-IA-11; D-IA-15; PG-006–PG-008; SCR-05–SCR-07; J-ACC-01; UXS-18–UXS-19; UXS-40; UXS-43; PT-21; GR-23; NAV-006–NAV-008; component Chart; GAP-034; GAP-060

**EXPECTED MODULES / FILE AREAS:**

- the Reporting module's query classes — one per signal, the period figures and the receivables position, the security notices — and `ShowBeranda`, `ShowWorkQueue`; OPS's `ShowReviewQueue` with its four sources (RC-02; ARCHITECTURE §3, §14); `resources/js/Pages/Reporting` and `resources/js/Pages/OPS` for PG-006–PG-008; `tests/Feature/Reporting`, the query-count tests of TL-15

**BUSINESS RULES:**

- WORKFLOWS §10 and BR-XD-05: signals are projections over facts and decisions, never stored truth; committed payments, stock changes and corrections update them without waiting for completion; every figure resolves to a CALC- or QS- definition and its scope filter (AC-23)
- CALC-05, CALC-07–CALC-14 as BUSINESS_RULES §5 defines them, each concept on its own business date (CALC-13); CALC-12 the Owner's alone; no formula converts SIPLAH or reference values into any figure (BR-FIN-01)
- QS-23 and QS-24 as `DIR-077` CA-10 and CA-07 define them: ACTIVE projects whose one governing deadline is before today at the WIB day boundary, for the Owner alone; the remaining reservations of ACTIVE projects' WAREHOUSE lines (BR-DT-06; CALC-02)
- the review queue is a derived view, never an approval step: every listed action is already in effect and the Owner acts only through the normal commands and correction rows (PERMISSIONS_MATRIX §10)

**ARCHITECTURE RULES:**

- read-only query classes applying the company-scope resolver before anything else; no queue, badge state or "read" mark is stored (ARCHITECTURE §14; WORKFLOWS §10); no S2–S4 result cached (CP-01; DP-10)
- each signal one bounded statement on the register's index or open root, QS-15 one per predicate, the five only-growing sets — QS-08, QS-12, QS-16, QS-18, QS-19 — measured at 10x and 100x; signals in at most four deferred groups on *Pekerjaan* and five on *Beranda* (PF-24; PF-34); the guards they read kept true by `ReconcileGuards` within PF-30's budget

**SECURITY / PERMISSION REQUIREMENTS:**

- ROUTE-006 and ROUTE-008 `review.view` (OWNER_ONLY): an Admin's request for either address answers PG-005 with MSG-09, identical to a nonexistent page, and no entry, badge or count exists for an Admin (OD-14; AZ-11; UXS-25); *Gabungan* and every figure summed across companies only with `reports.consolidated` (CS-08; PF-25)
- ROUTE-007 every active account (S0), each group present only with its read class, *Tagihan dan pembayaran* with `finance.view`; a notice or row derived at display time under the viewer's projection, so a revoked company's row disappears (DP-11; AZ-03)
- polls and deferred groups are background requests that never extend the session (RB-05; H7-13); prefetch off on these S3 pages (PF-35)

**COMPANY SCOPE AND FIELD PROJECTION:**

- every signal statement carries the scope filter inside the query, before ranking, aggregation and limits (CS-07); counts bounded per company — "ARJ 4 · BTN 20+" —, never summed across companies for an Admin (CS-08; PF-15); three reads go beyond scope and return nothing from there — QS-08, QS-12's bank lines, QS-20's every-company test (PF-24)
- PJ-13: an Admin's queue is the union over companies in scope of the signals its read classes allow; QS-14, QS-23 and the attribution parts of QS-18 and QS-22 Owner-only; QS-20 to the Owner and to accounts with every affected company in scope; QS-21 per family (RD-02, RD-03, RD-04, RD-08); a money item absent without its family (PJ-22)
- PG-008's items under the Owner's projection, the audit keys per PJ-11; the two undisclosed-duplicate items showing both records, their companies, the account and the match basis — to the Owner alone (IA §9.2; SECURITY LG-02); items without a company labelled *Gudang bersama* or *Tanpa perusahaan*

**CONCURRENCY / IDEMPOTENCY:**

- N/A — these pages run no command, so no lock, command identity or replay applies; G5's figures read in one snapshot so revenue, cost and profit are figures of one instant (TX-10; PF-43); a page read takes no row lock and never waits on one (PF-12)

**AUDIT:**

- N/A — reading writes no business audit or security event; the pages read audit events (QS-14, QS-20, QS-21) and security events (the Owner's notices; the *Keamanan* and *Impor* tabs) through their projections, nothing marked read or done (PJ-11; LG-02; IA §9.2)

**DATABASE IMPACT ASSESSMENT:**

- Schema change: NONE — uses the tables of TASK-005–TASK-009; a missing column is an executor discovery (AGENT_OPERATING_MODEL)
- Migration required: NO
- Existing data transformation: NONE
- Direct production DB access: FORBIDDEN
- Data-loss risk: NONE KNOWN

**MODULARITY / EXTENSIBILITY:**

- Module boundary affected: Reporting, tier 7, reading every module through its query classes; OPS's review-queue view (ARCHITECTURE §3)
- Stable core kept provider-neutral: YES
- External/vendor-specific code isolated: YES — the chart through shadcn's Chart composition only (DESIGN_SYSTEM §10)
- Replaceable adapter/contract required: NO
- Provider lock-in risk: NONE beyond the Owner's baseline stack
- Future extension impact: LOW — a further signal is one query class in the register
- Speculative abstraction avoided: YES — no stored queue, notification table or dashboard engine (WORKFLOWS §10; D-PF-04)

**AUTH IMPACT:**

- Authentication change: NONE
- Authentication implementation: LARAVEL_NATIVE — decided once ([EXECUTION_CONTEXT](EXECUTION_CONTEXT.md)), reused, never redesigned
- Auth profile: N/A — this Task does not touch authentication; it reads the security events authentication writes (TASK-010, TASK-017–TASK-019)
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

- Context7: REQUIRED — Inertia's deferred props, partial reloads and polling naming their props; shadcn's Chart composition; PostgreSQL snapshot reads for the period figures
- Graphify: OPTIONAL — every module's guards exist by now
- Browser E2E: REQUIRED — the signal rows' destinations, *Beranda* on the phone and desktop, UXS-40 and UXS-43
- Other: the scale fixtures FX-06 at 1x, 10x and 100x for QB-10 and PFM-07 (TASK-003)
- Missing safe tool behavior: AUTO-INSTALL project-locally inside this Task (AICWDF §6.3) and record it in TOOLCHAIN; a paid tier or a privileged installation STOPS for the Owner (COST_POLICY)

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: refreshed before the change when Graphify is available ([TOOLCHAIN](../00-governance/TOOLCHAIN.md)); otherwise a targeted search of the guards and roots the signal register names
- Pre-change questions: which index or open root serves each signal; which rows each read class admits; which signal reads beyond scope
- Expected affected areas: Reporting and OPS read paths only; no write path

**CONTEXT7:**

- Topics to verify: deferred and optional props and the background marker of polls (RB-05); the chart component's accessible data table; `REPEATABLE READ` snapshot reads in the framework's database layer
- Version-sensitive assumptions: none from memory

**UI / UX REQUIREMENTS:**

- Follow global design rules: YES
- Indonesian default: YES
- English support: YES
- Plain-language Indonesian: YES — the signals' empty sentences of INFORMATION_ARCHITECTURE §9.1 and the keyed labels of `pages.pg_006`, `pages.pg_007` and `pages.pg_008` as written
- Language switch impact: NONE — no input on these pages; the open groups and view are address keys and stay
- Mobile-first: YES — `[both]` for PG-006 and PG-007; `[desk]` for PG-008 (NAV-006–NAV-008)
- Responsive: YES
- Adaptive: YES — on the phone *Beranda*'s two decision rows then "Lihat semua keputusan", the KPIs two by two; *Pekerjaan*'s groups collapsible with Bayu's "Berikutnya" card first
- Visual breathing room: REQUIRED
- Comfortable density: REQUIRED
- shadcn/ui reuse: signal groups and queue rows (PT-21), the KPI tile and Chart with its table (DESIGN_SYSTEM §10), the company view (PT-04), the loading, empty and error states per group (PT-19), dense rows on the phone (PT-06)
- Design reference: PG-006–PG-008 as INFORMATION_ARCHITECTURE §§8.1 and 9 state them; the Golden Frontend Reference for presentation only (DIR-061 SD-02)
- Primary action: none of their own — a dashboard runs no command; each row's link is its next action's page (IA §9)
- Secondary actions: "Lihat Semua" per signal, "Lihat semua keputusan", "Buka Pekerjaan", "Rincian angka", the window and filters on PG-008
- Destructive actions: none
- Redundancy constraints: at most five rows per signal, the rest behind "Lihat Semua"; one entry per signal row (PT-21)
- Loading state: each deferred group its own spinner after 300 ms, the others staying (PT-19; IP-12)
- Empty state: each signal's empty sentence of INFORMATION_ARCHITECTURE §9.1, one quiet sentence, never an illustration; a group without its capability does not exist (PT-19)
- Error state: a group's own error with "Muat Ulang Bagian Ini", the others staying (NAV-006; NAV-007); a record gone out of scope MSG-09 (INT-019)

**ROUTE IMPACT:**

- ROUTE-006, ROUTE-007, ROUTE-008 (the three views); ROUTE-285 for the deferred groups' and polls' reads (TASK-022); no command route

**INTERACTION CONTRACTS:**

- INT-019, INT-020; INT-007 for the company view and PG-008's filters; INT-008 for PG-008's "Muat Lagi" (TASK-015, TASK-016)

**NAVIGATION CONTRACT:**

- Parent: none — PG-006 the Owner's landing page, PG-007 an Admin's landing page and the Owner's entry, PG-008 the Owner's entry (NAV-006–NAV-008)
- Entry: the primary entries; PG-008 also from *Beranda*'s QS-14 row
- Back action: N/A — landing and primary pages; a row's destination returns here by Back (INT-019)
- Cancel: N/A — nothing is entered on these pages
- Success destination: N/A — no command; a row opens its record or worklist (INT-019)
- Error behavior: NV-04; a group that fails shows its own error and retry, the others stay (NAV-006; NAV-007; NAV-008)
- State preservation: the folded or open groups, the company view and PG-008's window and filters in the address (NV-05)
- Mobile behavior: `[both]` for PG-006 and PG-007 as above; PG-008 `[desk]`, rows with the window switch

**ACCEPTANCE CRITERIA:**

1. Each signal is one bounded statement that reads no row of another company beyond the three reads PF-24 names, and returns nothing from them; a count shows per company, bounded, never summed across companies for an Admin (PF-24; CS-07; CS-08; PFM-07).
2. An Admin's *Pekerjaan* holds exactly the groups and signals its read classes allow, a money item absent without its family; QS-14, QS-23 and the attribution parts of QS-18 and QS-22 never appear for an Admin, and QS-20 only with every affected company in scope (PJ-13; SM-25).
3. An Admin's request for `/beranda` or `/tinjauan` answers the not-found page, identical to a nonexistent address, with no entry, badge or count for it anywhere (OD-14; AZ-11; UXS-25).
4. A committed payment, stock change or correction appears in its signals at the next refresh, without waiting for completion (BR-XD-05; AC-23).
5. Each row opens the page where its next action happens for an account holding that capability, otherwise the record's view; a record gone out of scope answers MSG-09 (INT-019; IA §9.1).
6. *Beranda* loads its frame and group headings at once and five deferred groups within QB-10 — at most twelve statements per signal group and seven for G5 with the receivables position, the bound rising by at most one —, its period figures read in one snapshot within 1 s (QB-10; PF-43; PFM-05; GAP-060).
7. The one-company view shows *Umur piutang* as a chart of defined values with its table, the *migrasi* bucket included; no KPI shows a delta, trend or sparkline (GR-23; SLOP-18; SLOP-34; D-IA-15).
8. The Owner's security notices list the events since the Owner's previous sign-in as neutral types with time and link, derived by one bounded statement, nothing marked read (IA §9; PFM-15; INT-020).
9. *Tinjauan* lists every QS-14 source in its four tabs — the hidden evidence duplicate and import collision with both records under the Owner's projection —, newest first within the window, with the previous-sign-in divider and no approve, dismiss or read control (UXS-18; UXS-19; UXS-40; GAP-034).
10. The anti-slop audit of *Beranda* and *Pekerjaan* finds row lists and one figures table, no KPI-card wall, decorative chart, banner or greeting (UXS-43; SLOP-18; SLOP-28; SLOP-38).
11. A poll runs no faster than once a minute as a background request that names its props and never extends the session (PF-26; PF-36; H7-13).
12. A new Admin without any company grant sees MSG-38 on *Pekerjaan* and nothing above S0 (RG-04; J-ACC-01).

**TEST REQUIREMENTS:**

- Lint: TL-01
- Typecheck: TL-02
- Unit: each signal's derivation on fixed facts; CALC-05 and CALC-07's buckets on BR-FIN-15's boundaries (TL-03; FX-01)
- Feature: ROUTE-006–ROUTE-008 per persona; each signal's rows and empty state (TL-04)
- Integration: G5's single snapshot; a revoked company's rows gone at the next read; the routed refusal's every-company test (TL-05)
- Authorization: OWNER_ONLY views, the read classes per group and signal, the view switch's `reports.consolidated` (TL-06)
- Route integrity: ROUTE-006, ROUTE-007 and ROUTE-008 audited (TL-07)
- Interaction: INT-019 per signal destination, INT-020 (TL-08)
- Browser E2E: the home pages on the desktop and the phone; UXS-40, UXS-43; the signal steps of CJ-09, CJ-13 and CJ-22 (TL-09)
- Localization/language switch: both languages on PG-006–PG-008 and every empty sentence (TL-10)
- Visual breathing room/density: TEST_STRATEGY §9 — CA-07's dispatch-ready lines, CA-10's overdue projects and CB-03's receivables position verified on the application
- Responsive: TL-11
- Accessibility: TL-12 — the chart's table alternative and status not by colour alone
- Production build: TL-13
- Security: SM-01 for the dashboard signals; SM-04 for OD-14; SM-25 for the money items and the Owner-only signals; SM-23 for counts and empty states that never hint at an out-of-scope record (TL-14)
- Performance: QB-10 through PFM-01 and PFM-02; PFM-05; PFM-07 with the five only-growing sets at 10x and 100x; PFM-15 (TL-15)
- Migration: N/A — no migration or import code in this Task
- Other: GAP-034's two kinds listed from their security events; GAP-060's bound

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
- store a queue, a badge, a count or a "read" or "done" mark, or put a command on a dashboard (WORKFLOWS §10; IA §9.2)
- sum or rank S3 values across companies outside the Owner's consolidated query classes, or show a figure without its CALC definition (CS-08; PF-25; SLOP-34)
- add a KPI, chart, delta or sparkline beyond the four tiles and the ageing chart (SLOP-18; OQ-17; OE-20)

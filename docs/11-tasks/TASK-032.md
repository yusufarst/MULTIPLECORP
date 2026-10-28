# TASK-032 — Projects — list, form, journey page and amendment

Status: APPROVED | Updated: 2026-10-20 | Owner: Planning

Approval: [APPR-016](../00-governance/DECISION_LOG.md#appr-016--p11-task-plan-tasks-and-feature-coverage-matrix-approved), explicit Owner approval on 2026-10-20 (DIR-085 OF-07) of this document as committed in the first execution session's approval commit; the approved file hash and the verified conditions are recorded there. Approval changes lifecycle only, not implementation authorization.

Written by WF-PC5-W's writer of bundle W1 of run PC-5 on 2026-10-19 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99; the Owner approved P11 with the [Task plan](TASK_PLAN.md) at OG-4 (DIR-085 OF-07). **No Task starts before the freeze record, from which the execution authorization is in force (DIR-085 OF-09).** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** IN_PROGRESS

**RISK CLASS:** HIGH

**EXECUTOR:** AGENT

**OBJECTIVE:**

Build the project as the operational hub: PG-010 *Proyek*, each row with its journey stage, blocker marker and next action; PG-013 *Formulir Proyek* to create and amend a project — company, client, unit, address and PIC, channel with its manual SIPLAH values, demand lines with their mode and prices; the discard of a draft; and PG-011 *Perjalanan Proyek*, the project as a guided journey from which every later step starts (ROUTE-010, ROUTE-011, ROUTE-013, ROUTE-151–ROUTE-153; WF-PRJ-01; J-PRJ-01).

**WHY THIS EXISTS:**

CAP-04 and AC-04 need one hub where staff find a project's number, company, client, unit, PIC, items, deadline and current work, and reuse its confirmed data downstream without retyping (UXN-02; REF-017; OS-01); CAP-12 and AC-15 put the SIPLAH channel on the same flow with manual values only, never a formula or a dependency (IC-14; BR-FIN-10; architecture challenge 9a). A project is single-company, so every reference it names must resolve inside the command's company (CS-03; CS-04; architecture challenge 1a). Quotations, the administrative checklist, the import and search (TASK-033, TASK-035, TASK-057, TASK-059) build on the project.

**DEPENDS ON:**

- TASK-021 — Numbering, its settings page and the Owner's raise after a restore
- TASK-025 — Companies, identity assets, bank accounts and tax configuration
- TASK-026 — Clients and suppliers
- TASK-027 — Catalog — products, services, units, barcodes, prices and images

**BLOCKS:**

- TASK-033 — Quotations
- TASK-035 — Administrative checklist and document readiness
- TASK-057 — Opening import — preparation, dry run, sign-off and commit
- TASK-059 — Search and scanning

**PARALLEL SAFE:** NO — the project row (LK-01) and PG-011's journey frame are what every later project Task extends

**IN SCOPE:**

- PG-013 with NAV-013 and its view ROUTE-013 (`projects.manage`; prices `finance.view`; SCR-11) and ROUTE-151 with INT-151, "Buat Proyek": the company as an explicit field, in scope and active (CS-03; BR-ACC-03); client, unit, address and PIC, created inline when needed (J-MD-02); the channel REGULAR or SIPLAH, the SIPLAH fields shown only for that channel as plain entered values (CAP-12; BR-FIN-10; IC-14); the title and the one governing deadline (`DIR-077` CA-10); demand lines — product or service, quantity, unit, selling price, tax components under the configured treatment, proposed mode — in the line editor (PT-10; IP-13); the number drawn at creation from the company's PROJECT counter and never reused, a creation whose period has no counter REJECTED with MSG-17 for an Admin (NM-08; NM-03; C-10; UXS-28); DRAFT → ACTIVE once company, client, unit, PIC and channel are set (SM:Project)
- ROUTE-152 with INT-152, "Ubah proyek": lines freely editable before confirmation, and afterwards commercial changes only through a quotation revision or a re-confirmation, the form saying so and linking there (L-20; BR-QUO-02); the channel changing only before its first channel-dependent committed record and locked after it (BR-PRJ-05; C-51); the company only while DRAFT with no committed downstream record (L-21); a linked project of another company with its reason, both companies in scope (XL-4)
- ROUTE-153 with INT-153, "Buang Draf": a DRAFT with no committed downstream record discarded after its confirmation, its number kept as a `retired_numbers` tombstone in the same transaction (L-37)
- PG-010 with NAV-010 and its view ROUTE-010 (`projects.view`; values `finance.view`; SCR-09): each row's company code, number, title, client, state and deadline, and its journey stage, blocker marker and next action from QB-01's deferred journey-summary group — the completion predicates and the documents readiness as set queries over the page's project ids, QS-15's money items omitted without `finance.view` (`DIR-077` CA-06; D-IA-19; PJ-13; PJ-22; GAP-054) —; no stage filter unless P8 shows a bounded one (PFM-04; PF-42)
- PG-011 with NAV-011 and its view ROUTE-011 (SCR-10, SCR-14; UXN-02; D-IA-09): the header and its primary action, *Tahap proyek* with the current stage marked (GR-03), *Sekarang · Berikutnya · Hambatan*, "Yang tersisa sebelum selesai", "Kesiapan dokumen", "Posisi keuangan" with `finance.view`, *Biaya dan laba* with `cost.view` and `profit.view`, *Pengadaan* with `cost.view`, availability with `stock.view`, and "Rincian proyek" with *Riwayat*; the derived part strip on the desktop (PT-31); at most three deferred groups — predicates, documents, *Riwayat* (QB-02; UXS-44); *Tindakan Lain* with "Ubah proyek" and "Buang Draf"
- the journey's entries — its primary action and the part each journey starts from — to J-QUO-01 with the quotation's states and history (REF-018; IMG-02), J-PRJ-02–J-PRJ-04, J-FUL-01–J-FUL-05, J-INV-02, J-INV-03, J-PUR-01, J-FIN-01 and J-ADM-01, each journey's steps built by its own Task, each part filled as its Task arrives and absent for a viewer without its family (INFORMATION_ARCHITECTURE §5.5, §13.7)
- PRJ's inversion contracts `ProjectLifecycleParticipant` and `CompletionPredicateProvider` declared (ARCHITECTURE §3) — the journey summary and "Yang tersisa sebelum selesai" read the predicates through the second —, each module implementing its part in its own Task

**OUT OF SCOPE:**

- quotations (TASK-033); commercial confirmation, fulfilment planning, remaining-scope cancellation and the re-plan split (TASK-034); requirements and document readiness (TASK-035)
- completion and Force Complete with PG-012 (TASK-055); cancellation (TASK-056)
- each journey's own pages and commands (their Tasks); the QS signals on the home pages (TASK-058); the project search (TASK-059)

**REFERENCES:**

- [WORKFLOWS §5.2](../03-workflows/WORKFLOWS/s05-01-02-access-project.md#wf-prj-01--project-setup-and-amendment-both) WF-PRJ-01; [§12](../03-workflows/WORKFLOWS/s10-14-signals-decisions-traceability.md#12-planner-determined-level-1-decisions) L-10–L-12, L-20, L-21, L-37; [§10](../03-workflows/WORKFLOWS/s10-14-signals-decisions-traceability.md#10-derived-signals-and-action-queue) QS-15
- [BUSINESS_RULES SM:Project](../02-domain/BUSINESS_RULES.md#smproject) BR-PRJ-01–BR-PRJ-06; [BR-FIN-10](../02-domain/BUSINESS_RULES.md#finance-fin); [BR-QUO-02](../02-domain/BUSINESS_RULES.md#smquotation)
- [V1_SCOPE](../01-product/V1_SCOPE.md#must-ship) CAP-04, CAP-12; [ACCEPTANCE_CRITERIA](../01-product/ACCEPTANCE_CRITERIA.md#product-acceptance-contracts) AC-04, AC-15, AC-20; [OS-01](../01-product/ACCEPTANCE_CRITERIA.md#operational-success-and-go-live-evidence); [REFERENCE_COVERAGE](../01-product/REFERENCE_COVERAGE.md#pdf-capability-inventory) REF-017, REF-018, REF-038; [IMG-02](../01-product/REFERENCE_COVERAGE.md#image-and-non-tabular-pdf-inventory)
- [DATABASE §4.5](../04-architecture/DATABASE/s04-05-prj.md#45-prj--projects--commercial); [§9](../04-architecture/DATABASE/s07-10-representations.md#9-project-and-commercial-representation); [§18](../04-architecture/DATABASE/s18-19-constraints-integrity.md#18-constraint-catalogue) C-10, C-38, C-39, C-51
- [ARCHITECTURE §3](../04-architecture/ARCHITECTURE.md#3-modules) PRJ's contracts; [API_AND_INTEGRATIONS §2](../06-api-performance/API_AND_INTEGRATIONS.md#2-contract-for-a-future-external-integration) IC-14
- [PERMISSIONS_MATRIX §6](../05-security/PERMISSIONS_MATRIX.md#6-company-scope-rules) CS-03, CS-04, CS-07, CS-08; [§7.2](../05-security/PERMISSIONS_MATRIX.md#72-table-classification) rows 28–38; [§7.3](../05-security/PERMISSIONS_MATRIX.md#73-special-projections) PJ-12, PJ-13, PJ-22; [§8](../05-security/PERMISSIONS_MATRIX.md#8-cross-company-link-authorization) XL-4
- [ROUTE_CONTRACTS](../03-workflows/ROUTE_CONTRACTS.md#projects--proyek-formulir-proyek-penawaran-the-journey-and-its-completion-prj-j-prj-01j-prj-05) ROUTE-151–ROUTE-153; [§2](../03-workflows/ROUTE_CONTRACTS.md#2-page-views--route-001route-065) ROUTE-010, ROUTE-011, ROUTE-013
- [INTERACTION_CONTRACTS](../03-workflows/INTERACTION_CONTRACTS.md#projects--proyek-formulir-proyek-penawaran-the-journey-and-its-completion-prj-j-prj-01j-prj-05) INT-151–INT-153
- [NAVIGATION_CONTRACTS §2.2](../07-ux-design/NAVIGATION_CONTRACTS.md#22-projects-and-quotations) NAV-010, NAV-011, NAV-013
- [INFORMATION_ARCHITECTURE §8.2](../07-ux-design/INFORMATION_ARCHITECTURE.md#82-projects-and-quotations) PG-010, PG-011, PG-013; [§5.5](../07-ux-design/INFORMATION_ARCHITECTURE.md#55-the-project-journey-as-the-hub-and-what-has-no-entry); [§13.7](../07-ux-design/INFORMATION_ARCHITECTURE.md#137-variations-that-change-navigation); [§2](../07-ux-design/INFORMATION_ARCHITECTURE.md#2-derived-navigation-requirements) UXN-02; [§4](../07-ux-design/INFORMATION_ARCHITECTURE.md#4-design-decisions) D-IA-09, D-IA-19
- [ADMIN_FLOW §10.2](../07-ux-design/ADMIN_FLOW/s10-a-journeys.md#102-project-and-commercial) J-PRJ-01, J-PRJ-02, J-QUO-01, J-FUL-01; [§10.3](../07-ux-design/ADMIN_FLOW/s10-a-journeys.md#103-inventory-and-purchasing) J-INV-02, J-INV-03, J-PUR-01; [§10.4](../07-ux-design/ADMIN_FLOW/s10-b-journeys.md#104-fulfilment) J-FUL-02–J-FUL-05; [§10.5](../07-ux-design/ADMIN_FLOW/s10-b-journeys.md#105-documents-and-administration) J-ADM-01; [§10.6](../07-ux-design/ADMIN_FLOW/s10-b-journeys.md#106-finance) J-FIN-01; [§10.7](../07-ux-design/ADMIN_FLOW/s10-b-journeys.md#107-completion-cancellation-and-migration) J-PRJ-03, J-PRJ-04; [§10.1](../07-ux-design/ADMIN_FLOW/s10-a-journeys.md#101-access-and-master-data) J-MD-02; [§15](../07-ux-design/ADMIN_FLOW/s14-15-messages-scenarios.md#numbering) UXS-28; [UXS-44](../07-ux-design/ADMIN_FLOW/s14-15-messages-scenarios.md#design)
- [DESIGN_SYSTEM §9.1](../07-ux-design/DESIGN_SYSTEM.md#91-shell-navigation-and-page-structure) PT-31; [§9.3](../07-ux-design/DESIGN_SYSTEM.md#93-forms-and-entry) PT-10; [Annex B](../07-ux-design/DESIGN_SYSTEM.md#annex-b-the-carried-items-gr-01gr-23) GR-03
- [CONCURRENCY_IDEMPOTENCY §7.3](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s07-statement-sequences.md#73-command-families-outside-ax-0137) NX-03; [§14](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s14-17-numbering-jobs-revocation.md#14-numbering-concurrency) NM-03, NM-08; [§9](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s08-09-guards-stale-state.md#9-stale-state) ST-01
- [PERFORMANCE_TARGETS §2](../08-testing/PERFORMANCE_TARGETS.md#2-the-query-budgets) QB-01, QB-02; [§3](../08-testing/PERFORMANCE_TARGETS.md#3-the-measured-targets) PFM-04; [PERFORMANCE §4](../06-api-performance/PERFORMANCE.md#4-pagination) PF-41, PF-42
- [GAP_REGISTER](../00-governance/GAP_REGISTER.md#gap-054--the-project-list-cannot-show-each-projects-journey-stage-blocker-and-next-action-within-its-query-budget) GAP-054
- Architecture challenge — the sequences 1a, 9a of the [P11 gate record](evidence/P11_QUALITY_GATE.md#whole-system-architecture-challenge-sp-98), their assertions carried into this Task's acceptance criteria and tests
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (74): O-01; CAP-04; CAP-12; AC-04; AC-15; AC-20; OS-01; REF-016–REF-018; REF-038; IMG-01–IMG-02; IMG-04; BR-PRJ-01–BR-PRJ-06; WF-PRJ-01; L-10–L-12; L-20–L-21; ROUTE-010–ROUTE-011; ROUTE-013; ROUTE-151–ROUTE-153; INT-151–INT-153; C-38–C-39; C-51; NX-03; IC-14; UXN-02; D-IA-09; PG-010–PG-011; PG-013; SCR-09–SCR-11; SCR-14; J-PRJ-01–J-PRJ-04; J-MD-02; J-QUO-01; J-INV-02–J-INV-03; J-FUL-01–J-FUL-05; J-PUR-01; J-FIN-01; J-ADM-01; UXS-28; UXS-44; PT-31; GR-03; NAV-010–NAV-011; NAV-013; PFM-04; GAP-054

**EXPECTED MODULES / FILE AREAS:**

- the PRJ module's CreateProject, UpdateProject and DiscardDraftProject actions, its `Contracts/` for the two inversion contracts and its query classes — the list with its journey-summary group, the journey page (RC-02; ARCHITECTURE §4); `resources/js/Pages/PRJ` for PG-010, PG-011 and PG-013; `tests/Feature/PRJ`

**BUSINESS RULES:**

- SM:Project and BR-PRJ-05: DRAFT → ACTIVE once company, client, unit, PIC and channel are set; the channel fixed per project, changeable with audit only before the first channel-dependent committed record (C-51); a DRAFT with no committed downstream record discarded with audit, its number retired (L-37)
- BR-PRJ-01–BR-PRJ-04 and BR-PRJ-06 as the journey shows them: the confirmation as the reservation trigger (BR-PRJ-06; TASK-034), the waiver authority (BR-PRJ-03; TASK-035), the ten predicates and Force Complete (BR-PRJ-02, BR-PRJ-04; C-38, C-39; TASK-055) and cancellation releasing reservations with its companion corrections (BR-PRJ-01; L-10; TASK-056); after completion or cancellation the journey offers only the residual commands (L-11), normal completion being an explicit decision (L-12)
- L-20 and L-21: after confirmation commercial changes need a revision or re-confirmation; the company changes only in a DRAFT without committed records; SIPLAH metadata never moves money and no formula exists (BR-FIN-10; FS-13; IC-14)

**ARCHITECTURE RULES:**

- PRJ is tier 2 (ARCHITECTURE §3); progress, stage and "what is next" are derived from facts and decisions, never stored (WORKFLOWS §1; L-01; FS-01); the journey summary reads QS-15's predicates as set queries over the page's ids, never one query per row (QB-01; PF-01)
- one company per project; a rare cross-company need is a linked project with its reason, never a shared record (WF-PRJ-01; DATABASE §9)

**SECURITY / PERMISSION REQUIREMENTS:**

- the views `projects.view`; ROUTE-151–ROUTE-153 `projects.manage` (prerequisite `projects.view`); selling prices, tax components and SIPLAH values also `finance.view` — without it the lines show product, quantity and mode only (J-PRJ-01); a linked project `projects.manage` with both companies in scope (XL-4)
- `projects.state` and transition kinds never accepted from a client payload (OD-12; WS-02); every public identifier resolved through the scope resolver, an out-of-scope reference failing like a nonexistent one (CS-04; AZ-11)

**COMPANY SCOPE AND FIELD PROJECTION:**

- a new project's company is the one chosen in the payload, in scope and active (CS-03); its client, unit, address and PIC are masters (CS-06); every other reference it names resolves inside that company, a cross-company reference existing only as a linked project (XL-4; C-32; architecture challenge 1a)
- projection rows 28–38: `projects` PV(c), a linked project per XL-4 — named when in scope, otherwise "a project of another company"; `project_siplah_details` PV(c) with its money columns FV(c); `project_lines` PV(c) with draft prices FV(c); `project_state_transitions` PV(c) with money keys FV(c) (PJ-12); the journey's parts by family (ROUTE-011; PJ-22)
- PG-010 one bounded branch per company in scope, each row with its company code, no total or count summed across companies (CS-07; CS-08; PF-41); the journey summary omits QS-15's money items without `finance.view` (PJ-13)

**CONCURRENCY / IDEMPOTENCY:**

- ROUTE-151–ROUTE-153 are NX-03 with `command_id` (CI-01): lock set LK-00, LK-01, LK-03, LK-06 (the company of a new project, BR-ACC-03), LK-29, LK-30, LK-31 — the number allocated at creation under LK-31, the last lock (NM-01; NM-08); a project naming a linked project locks both; a discard writes its tombstone in the same transaction (CONCURRENCY_IDEMPOTENCY §7.3)
- an amendment carries `lock_version` on the project, which covers its draft lines, so two Admins editing different lines of one draft still conflict and the stale editor gets CONFLICT (ST-01; PX-07); a prefilled business date that went stale is CONFLICT (ST-08; MSG-16); replay re-authorized (CI-05)

**AUDIT:**

- every creation, amendment, activation and discard is a business audit event in its transaction with before/after and the individual actor (BR-XC-01; LG-01); the activation a `project_state_transitions` row of kind ACTIVATE written with the state (DATABASE §9); a discard keeps the draft's last content in its audit event and the number's tombstone (DATABASE §13; L-37); SIPLAH value changes audited until the lock (DATABASE §4.5)

**DATABASE IMPACT ASSESSMENT:**

- Schema change: NONE — uses the tables of TASK-005–TASK-009; a missing column is an executor discovery (AGENT_OPERATING_MODEL)
- Migration required: NO
- Existing data transformation: NONE
- Direct production DB access: FORBIDDEN
- Data-loss risk: NONE KNOWN

**MODULARITY / EXTENSIBILITY:**

- Module boundary affected: PRJ, tier 2, with its two inversion contracts implemented by INV, FUL, FIN, ADM and PUR in their Tasks (ARCHITECTURE §3)
- Stable core kept provider-neutral: YES
- External/vendor-specific code isolated: YES — no SIPLAH adapter in V1; a future one sits behind ARCHITECTURE §13's module interface (IC-14)
- Replaceable adapter/contract required: YES — `ProjectLifecycleParticipant` and `CompletionPredicateProvider`, the contracts ARCHITECTURE §3 names, nothing more
- Provider lock-in risk: NONE beyond the Owner's baseline stack
- Future extension impact: MEDIUM — every later project Task fills a part of the journey through these contracts
- Speculative abstraction avoided: YES — no stored stage or workflow status, no generic workflow engine (L-01; ARCHITECTURE §1)

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

- Context7: REQUIRED — Inertia's deferred props and partial reloads, and the framework's eager loading, at the installed versions
- Graphify: OPTIONAL — once the modules that fill the journey's parts exist
- Browser E2E: REQUIRED — J-PRJ-01, CJ-01's project steps and CJ-08's SIPLAH project
- Other: the scale fixtures at 1x, 10x and 100x for the list's journey-summary group (PFM-04; TASK-003)
- Missing safe tool behavior: AUTO-INSTALL project-locally inside this Task (AICWDF §6.3) and record it in TOOLCHAIN; a paid tier or a privileged installation STOPS for the Owner (COST_POLICY)

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: refreshed before the change when Graphify is available ([TOOLCHAIN](../00-governance/TOOLCHAIN.md)); otherwise a targeted search of PRJ, ORG's numbering and the party and catalogue lookups
- Pre-change questions: which modules will implement the two contracts and fill which part of the journey; which predicates the journey summary reads
- Expected affected areas: PRJ; the journey frame every later project Task extends

**CONTEXT7:**

- Topics to verify: deferred and optional props, partial reloads naming their props (PF-36), and strict lazy-loading checks outside production (PFM-02)
- Version-sensitive assumptions: none from memory

**UI / UX REQUIREMENTS:**

- Follow global design rules: YES
- Indonesian default: YES
- English support: YES
- Plain-language Indonesian: YES — MSG-17, MSG-39 and the refusals of L-20, L-21 and BR-PRJ-05 in words, as J-PRJ-01 states them
- Language switch impact: keeps unsent input in the form
- Mobile-first: YES — all three pages `[both]` (NAV-010, NAV-011, NAV-013)
- Responsive: YES
- Adaptive: YES — on the phone the journey's header, primary action, the now card and the track first, a project created with a few lines; the full line editor on the desktop (J-PRJ-01)
- Visual breathing room: REQUIRED
- Comfortable density: REQUIRED
- shadcn/ui reuse: the data table, dense rows and filter sheet (PT-05–PT-07), the page header with one primary action (PT-03), the detail layout and part strip (PT-12, PT-31), the line editor (PT-10), the business date control (PT-25), status badges (PT-13), deferred groups' states (PT-19)
- Design reference: PG-010, PG-011 and PG-013 as INFORMATION_ARCHITECTURE §8.2 and §5.5 state them; the Golden Frontend Reference for presentation only (DIR-061 SD-02)
- Primary action: PG-010 "Buat Proyek"; PG-013 its save; PG-011 the journey's one primary action, the next step's
- Secondary actions: *Tindakan Lain* — "Ubah proyek", "Buang Draf" —; the parts' entries
- Destructive actions: "Buang Draf" with its confirmation stating that the number is retired (IP-08)
- Redundancy constraints: one primary action per page (PT-03); the other entries in their parts or *Tindakan Lain*
- Loading state: the journey-summary group and each of the three deferred groups with its own loading state and error, the others staying (PT-19; QB-01; QB-02)
- Empty state: no project yet — one sentence with "Buat Proyek" (PT-19); a project without lines says so in the form
- Error state: MSG-17 for an Admin's creation in a period without numbering (UXS-28); CONFLICT on a stale draft (IP-03); the refusals of L-20, L-21 and BR-PRJ-05 in words

**ROUTE IMPACT:**

- ROUTE-010 (PG-010's view), ROUTE-011 (PG-011's view), ROUTE-013 (PG-013's view, new and amend); ROUTE-151 (create), ROUTE-152 (amend), ROUTE-153 (discard a draft); ROUTE-285 for the deferred groups (TASK-022)

**INTERACTION CONTRACTS:**

- INT-151–INT-153; INT-007 and INT-008 for the list (TASK-015, TASK-016)

**NAVIGATION CONTRACT:**

- Parent: PG-010 — the primary entry *Proyek*; PG-011 — PG-010; PG-013 — PG-010 for a new project, PG-011 for an amendment (NAV-010, NAV-011, NAV-013)
- Entry: *Proyek*; PG-011 from *Proyek*, *Pekerjaan*, *Beranda* and search; PG-013 from "Buat" › *Proyek*, "Buat Proyek" and *Tindakan Lain*
- Back action: PG-011's "Kembali" → PG-010 at its address, or the work row's page it was opened from; PG-013's "Kembali" → the origin
- Cancel: PG-013's "Batal" → the origin, MSG-35 first with unsent input (NV-02)
- Success destination: PG-011 of the created or amended project (NAV-013); after "Buang Draf", PG-010
- Error behavior: NV-04; a record outside scope → PG-005 (IP-21)
- State preservation: PG-010's filters, sort, tab and cursor and PG-011's open section or tab in the address; PG-013's unsent input kept across re-login (NV-05)
- Mobile behavior: `[both]` — PG-010 rows and a filter sheet; PG-011 header, primary action, the now card and the track first; PG-013 one column, lines as rows

**ACCEPTANCE CRITERIA:**

1. A project is created in the company chosen, which must be in the actor's scope and active, and draws its number from that company's PROJECT counter at creation; in a period whose numbering has not started an Admin's creation is REJECTED with MSG-17, the input kept, and succeeds once the Owner has started it (NM-08; C-10; UXS-28).
2. A payload naming a record of another company, or a linked project outside the actor's scope, fails exactly like a nonexistent reference, and a linked project needs both companies in scope (CS-04; XL-4; architecture challenge 1a).
3. SIPLAH fields appear only for a SIPLAH project, are stored as entered and computed nowhere, move no money, and a REGULAR project never needs them; no project operation waits on SIPLAH (CAP-12; AC-15; REF-038; BR-FIN-10; IC-14; architecture challenge 9a).
4. The channel changes only before the project's first channel-dependent committed record and is locked after it; the company changes only while the project is a DRAFT without committed records (BR-PRJ-05; C-51; L-21).
5. After confirmation an amendment of commercial values is refused with the path to a quotation revision or a re-confirmation (L-20).
6. "Buang Draf" discards only a DRAFT without committed downstream records, after its confirmation, and its number stays retired and is never drawn again (L-37; C-10).
7. Two Admins editing different lines of one draft: the later save is CONFLICT with the input kept (ST-01; PX-07).
8. Each PG-010 row shows its company code, number, title, client, state and deadline at once, and its stage, blocker marker and next action when the journey-summary group arrives, within twelve statements in CI — the group's 500 ms measured on staging by TASK-068's scripts in TASK-079 (PERFORMANCE_TARGETS §1) —; without `finance.view` the group omits QS-15's money items (`DIR-077` CA-06; QB-01; PJ-13; GAP-054).
9. PG-010 offers no stage filter unless one is shown bounded by PF-42 at 10x and 100x (PFM-04).
10. PG-011 shows its header, primary action and parts at once and loads at most three deferred groups within QB-02's budgets; a part of a family the viewer lacks is absent, never masked or shown as zero (QB-02; UXS-44; INFORMATION_ARCHITECTURE §8).
11. The project's confirmed data — client, unit, PIC, lines, prices — reaches the quotation, purchase, delivery, invoice and documents without being typed again (AC-04; OS-01; REF-017).
12. Selling prices, tax components and SIPLAH values are shown and edited only with `finance.view` (PJ-12; J-PRJ-01).

**TEST REQUIREMENTS:**

- Lint: TL-01
- Typecheck: TL-02
- Unit: the journey stage, blocker marker and next action derived from facts and decisions (TL-03)
- Feature: ROUTE-010, ROUTE-011, ROUTE-013 and ROUTE-151–ROUTE-153 with each refusal (TL-04)
- Integration: concurrent creations drawing distinct numbers; a discard with its tombstone; the channel lock at the first channel-dependent commit (TL-05)
- Authorization: `projects.view`, `projects.manage` and the `finance.view` family; XL-4's two-company rule (TL-06)
- Route integrity: ROUTE-010, ROUTE-011, ROUTE-013 and ROUTE-151–ROUTE-153 audited (TL-07)
- Interaction: INT-151–INT-153, each with one `command_id` replayed (TL-08; IG-05)
- Browser E2E: J-PRJ-01 on the desktop and the phone; CJ-01's project steps; CJ-08's SIPLAH project setup; UXS-28 (TL-09)
- Localization/language switch: both languages on PG-010, PG-011 and PG-013 (TL-10)
- Visual breathing room/density: TEST_STRATEGY §9 — CA-06's changed list verified on the application
- Responsive: TL-11
- Accessibility: TL-12
- Production build: TL-13
- Security: SM-01's forged and foreign identifiers on ROUTE-011 and in the payloads; SM-25's money families; SM-04 for `projects.manage` (TL-14)
- Performance: QB-01 and QB-02 through PFM-01 and PFM-02; PFM-04's bounded stage filter at 10x and 100x (TL-15)
- Migration: N/A — no migration or import code in this Task
- Other: AC-20's golden journey and its existing-stock and service-only projects start here and run end to end in TASK-062 (CJ-01)

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
- store a project's stage, progress or next action, or offer a stage filter without P8's bounded plan (L-01; PFM-04)
- compute any SIPLAH value, make an operation depend on SIPLAH, or add a SIPLAH integration (IC-14; FS-13)
- let a project reference another company's record outside a linked project, or reuse a discarded project's number (CS-04; L-37)

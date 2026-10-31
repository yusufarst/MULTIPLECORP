# TASK-055 — Project completion and Force Complete

Status: APPROVED | Updated: 2026-10-20 | Owner: Planning

Approval: [APPR-016](../00-governance/DECISION_LOG.md#appr-016--p11-task-plan-tasks-and-feature-coverage-matrix-approved), explicit Owner approval on 2026-10-20 (DIR-085 OF-07) of this document as committed in the first execution session's approval commit; the approved file hash and the verified conditions are recorded there. Approval changes lifecycle only, not implementation authorization.

Written by WF-PC5-W's writer of bundle W3 of run PC-5 on 2026-10-19 and 2026-10-20 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99; the Owner approved P11 with the [Task plan](TASK_PLAN.md) at OG-4 (DIR-085 OF-07). **No Task starts before the freeze record, from which the execution authorization is in force (DIR-085 OF-09).** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** BLOCKED — waiting on P11Q-07

**RISK CLASS:** HIGH

**EXECUTOR:** AGENT

**OBJECTIVE:**

Build the project's completion: PG-012 *Penyelesaian Proyek* with the ten conditions of WF-PRJ-03 derived each time, each unmet one saying what is missing and where it is resolved; the explicit normal completion "Selesaikan Proyek" (AX-25), committed only when all ten predicates are true when re-read under the project's lock; the Owner's "Selesaikan Paksa" (AX-26), bound to the blocker snapshot the Owner confirmed, with its mandatory reason and its residual obligations kept visible; the closed list of residual commands a completed or cancelled project still accepts; and the revalidation SF-REVAL that returns a completed project to *Aktif* when a material correction touches it (ROUTE-012, ROUTE-161, ROUTE-162; J-PRJ-03).

**WHY THIS EXISTS:**

CAP-18 and AC-21 require a system-evaluated completion gate that delivery or full payment alone never passes, an Owner-only exceptional override that never pretends the normal gate passed or erases an obligation, and a truthful revalidation after later corrections (BR-PRJ-02; BR-PRJ-04; BR-CR-05; IMG-08; IMG-09). GAP-022 names the failure modes — hidden debt, fabricated evidence, an Admin or API bypass, lost audit, a stale completed badge —, and the architecture challenge's sequences 4a and 7b require that a client return or a voided document reopens what completion read. The project row is the completion anchor every predicate-changing command holds first (CONCURRENCY_IDEMPOTENCY §7.1), so completion races the corrections that would invalidate it (M-26).

**DEPENDS ON:**

- TASK-031 — Correction cases
- TASK-035 — Administrative checklist and document readiness
- TASK-043 — Stock count (opname) and discrepancies
- TASK-044 — Purchase return to supplier
- TASK-045 — Deliveries (Pengiriman) and delivery closure
- TASK-046 — Direct shipment (drop-ship)
- TASK-047 — Service fulfillment and handover
- TASK-048 — Client returns (sales return)
- TASK-050 — Billing, receivables, dispute hold and Owner write-off
- TASK-052 — Deduction settlements and contras
- TASK-053 — Customer credit and refunds
- TASK-054 — Expenses, disbursements and other cash
- the Owner's answer to P11Q-07 recorded and the credit hold's row and column made, which P11Q-02 (a) leaves to it ([TASK_PLAN §8](TASK_PLAN.md#8-owner-questions)) — BLOCKED until then

**BLOCKS:**

- TASK-056 — Project cancellation
- TASK-058 — Home, Work and Review — signals and the action queue

**PARALLEL SAFE:** NO — it writes the project's completion transitions under LK-01 and the revalidation every correcting module calls inside its own transaction

**IN SCOPE:**

- PG-012 with NAV-012 and its view ROUTE-012 (`projects.view`; SCR-14): the ten conditions of WF-PRJ-03 — fulfilment, delivery, returns and corrections, documents, administration, billing, receivable, stock, payment and audit — as a derived readiness list (PT-29), stacked condition rows, each with its state and, for an unmet one, what is missing and where it is resolved (J-PRJ-03; UXI-26); the condition rows filled from the facts and decisions each time the page is shown, never stored (L-01; FS-11); "Selesaikan Proyek" for `projects.complete` and, only for the Owner, the "Selesaikan Paksa" section (J-PRJ-03; OD-12)
- the ten predicates of WF-PRJ-03, complete once every module's part exists: read through the `CompletionPredicateProvider` parts of PUR, INV, FUL, FIN and ADM and from DOC's and OPS's query classes for the documents and correction-case predicates (ARCHITECTURE §3), in QS-15's set-query form — one set query per predicate over a set of project ids, never one per project (PERFORMANCE PF-24, signal register), the form PG-010's journey summary and PG-011's predicates group read (TASK-032) and QS-15 reads (TASK-058) —: for one project on PG-012, and re-read after LK-01 is held inside AX-25 and AX-26; predicate 8's count findings (TASK-043) and predicate 9's disbursement correction cases (TASK-054) come from Tasks the plan does not order before this one, raised to Planning before this Task is READY
- ROUTE-161 with INT-161, "Selesaikan Proyek" (`PRJ CompleteProject`; AX-25): MSG-39's confirmation first (IP-08); inside the command the ten predicates re-read after LK-01 is held; COMPLETED_NORMAL with its COMPLETE_NORMAL transition only when all ten are true at commit, otherwise REJECTED with the unmet predicates (C-39); an explicit decision of the Owner or an Admin with `projects.complete`, never automatic (L-12)
- ROUTE-162 with INT-162, "Selesaikan Paksa" (`PRJ ForceCompleteProject`; AX-26), the dialog of UXS-37: the unmet conditions and the residual obligations as a snapshot — open receivables that stay active and ageing, release-required reservations, open cases, missing documents —, the mandatory reason (MSG-40; IP-09), the statement that nothing is fabricated and every obligation stays, then the confirmation bound to the hash of the snapshot shown (ST-04); a changed snapshot answered CONFLICT and shown again to confirm (J-PRJ-03); the FORCE_COMPLETE transition with the predicate snapshot, unmet predicates, residual obligations, reason, confirmed hash and actor (C-38; BR-PRJ-04)
- SF-REVAL, PRJ's revalidation service: the materiality test — a correction is material when it changes a predicate input or a project CALC value —; a material correction on a COMPLETED_NORMAL or COMPLETED_FORCED project returns it to ACTIVE inside the correcting command's transaction with a REVALIDATE transition linked to the correction, one per affected project, the completion or Force Complete record kept (BR-CR-05; CM-32; DATABASE §9); a CANCELLED project stays CANCELLED; a residual command never triggers it (L-11); called by each module's correcting commands — CM-17's cost cascade across several projects among them (AX-34) — and run for a document void, a revision's issue or an evidence replacement through PRJ's part of DOC's `DocumentChangeParticipant` (L-41; architecture challenge 7b), with ADM's reopening of requirements running through `ProjectLifecycleParticipant` (TASK-035)
- the closed residual-command list of WF-PRJ-03 and L-11 as PRJ's state precondition that every project-bound command reads under LK-01 (UXI-40), for the completed states — the CANCELLED state reads the same list (TASK-056) —: on COMPLETED_* projects only the listed commands that reduce residual obligations are accepted without reopening; anything that would raise the project's outstanding or change a completed result is a correction (SF-REVAL); anything else REJECTED; PG-011 offering after completion only those commands (J-PRJ-03), each module's command applying the precondition in its own Task
- the projects' completion states on the journey: COMPLETED_NORMAL and COMPLETED_FORCED distinguishable, a force-completed project's unmet conditions and residual obligations kept visible on PG-011 (BR-PRJ-04; AC-21)
- **Held for the Owner's answer to P11Q-07, to which P11Q-02 (a) leaves the credit hold (DIR-100), built here once the answer and its row are approved, which comes before this Task is READY** ([TASK_PLAN §8](TASK_PLAN.md#8-owner-questions)): under option (a), predicate 9's "project-originated credit dispositioned" counts credit held with its reason — the disposition TASK-053 records once the amendment adds it — as dispositioned, beside credit applied or refunded (WF-PRJ-03 predicate 9; WF-FIN-06); under option (b), only apply and refund dispose of it, as the answer records (DIR-083 OE-20)

**OUT OF SCOPE:**

- each predicate's facts and its `CompletionPredicateProvider` part — fulfilment and stock (TASK-034, TASK-038–TASK-048), purchases and their remainders (TASK-037, TASK-044), billing, receivables, payments, settlements and credit (TASK-049–TASK-053), disbursements and their correction cases (TASK-054), administration (TASK-035) —, the documents predicate's issue and rendition states (TASK-029) and the correction cases' closure (TASK-031)
- the correcting commands themselves and their shared correction service (each module's Task; TASK-031); remaining-scope cancellation and the re-plan split (TASK-034); project cancellation (TASK-056)
- PG-011's frame, its predicates group and PG-010's journey summary (TASK-032); QS-15 and QS-16 on *Beranda* and *Pekerjaan* (TASK-058); CJ-17 end to end (TASK-063)

**REFERENCES:**

- [WORKFLOWS §5.7](../03-workflows/WORKFLOWS/s05-07-08-completion-migration.md#wf-prj-03--project-completion-penyelesaian-desk) WF-PRJ-03; [§6](../03-workflows/WORKFLOWS/s06-07-subflows-partial.md#6-reusable-subflows) SF-REVAL; [§8](../03-workflows/WORKFLOWS/s08-09-corrections-indivisible.md#8-correction-reversal-and-cancellation-matrix) CM-17, CM-32; [§9](../03-workflows/WORKFLOWS/s08-09-corrections-indivisible.md#9-indivisible-business-actions) AX-25, AX-26; [§10](../03-workflows/WORKFLOWS/s10-14-signals-decisions-traceability.md#10-derived-signals-and-action-queue) QS-15, QS-16; [§12](../03-workflows/WORKFLOWS/s10-14-signals-decisions-traceability.md#12-planner-determined-level-1-decisions) L-01, L-10–L-12, L-20, L-21, L-39, L-49, L-50
- [BUSINESS_RULES SM:Project](../02-domain/BUSINESS_RULES.md#smproject) BR-PRJ-01–BR-PRJ-06; [§2](../02-domain/BUSINESS_RULES.md#2-correction-taxonomy) BR-CR-04, BR-CR-05; [§6](../02-domain/BUSINESS_RULES.md#6-cross-domain-interaction-rules) BR-XD-01–BR-XD-07; [§7](../02-domain/BUSINESS_RULES.md#7-forbidden-shortcuts) FS-08, FS-11
- [V1_SCOPE](../01-product/V1_SCOPE.md#must-ship) CAP-18 and its [completion contract](../01-product/V1_SCOPE.md#normal-completion-and-exceptional-override); [ACCEPTANCE_CRITERIA](../01-product/ACCEPTANCE_CRITERIA.md#product-acceptance-contracts) AC-21 with its [completion conditions](../01-product/ACCEPTANCE_CRITERIA.md#product-completion-conditions); [REFERENCE_COVERAGE](../01-product/REFERENCE_COVERAGE.md#image-and-non-tabular-pdf-inventory) IMG-08, IMG-09
- [DATABASE §4.5](../04-architecture/DATABASE/s04-05-prj.md#45-prj--projects--commercial) `project_state_transitions`; [§9](../04-architecture/DATABASE/s07-10-representations.md#9-project-and-commercial-representation); [§18](../04-architecture/DATABASE/s18-19-constraints-integrity.md#18-constraint-catalogue) C-38, C-39, C-51
- [ARCHITECTURE §3](../04-architecture/ARCHITECTURE.md#3-modules) `CompletionPredicateProvider`, `ProjectLifecycleParticipant`, `DocumentChangeParticipant`
- [PERMISSIONS_MATRIX §2](../05-security/PERMISSIONS_MATRIX.md#2-authorization-model) AZ-08, AZ-10, AZ-11; [§4](../05-security/PERMISSIONS_MATRIX.md#4-capability-catalogue) `projects.complete`, `projects.force_complete`; [§6](../05-security/PERMISSIONS_MATRIX.md#6-company-scope-rules) CS-01, CS-03, CS-04; [§7.2](../05-security/PERMISSIONS_MATRIX.md#72-table-classification) rows 28, 38; [§7.3](../05-security/PERMISSIONS_MATRIX.md#73-special-projections) PJ-04, PJ-12, PJ-22; [§9](../05-security/PERMISSIONS_MATRIX.md#9-owner-only-denials) OD-12
- [SECURITY §4](../05-security/SECURITY.md#4-web-and-input-security) WS-02; [§6](../05-security/SECURITY.md#6-audit-and-security-logging) LG-01, LG-02
- [CONCURRENCY_IDEMPOTENCY §7.1](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s07-statement-sequences.md#71-lock-sets-of-ax-01ax-37) and [§7.2](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s07-statement-sequences.md#72-checks-effects-and-outcomes-of-ax-01ax-37) AX-25, AX-26; [§9](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s08-09-guards-stale-state.md#9-stale-state) ST-04; [§10](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s10-13-identity-failure-retry.md#10-command-identity-and-replay) CI-01, CI-05; [§11](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s10-13-identity-failure-retry.md#11-separate-duplicates) BD-10, BD-13; [§18](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s18-scenarios.md#18-mandatory-concurrency-and-failure-scenarios) M-26, M-27
- [ROUTE_CONTRACTS](../03-workflows/ROUTE_CONTRACTS.md#projects--proyek-formulir-proyek-penawaran-the-journey-and-its-completion-prj-j-prj-01j-prj-05) ROUTE-161, ROUTE-162; [§2](../03-workflows/ROUTE_CONTRACTS.md#2-page-views--route-001route-065) ROUTE-012
- [INTERACTION_CONTRACTS](../03-workflows/INTERACTION_CONTRACTS.md#projects--proyek-formulir-proyek-penawaran-the-journey-and-its-completion-prj-j-prj-01j-prj-05) INT-161, INT-162
- [NAVIGATION_CONTRACTS §2.2](../07-ux-design/NAVIGATION_CONTRACTS.md#22-projects-and-quotations) NAV-012
- [INFORMATION_ARCHITECTURE §8.2](../07-ux-design/INFORMATION_ARCHITECTURE.md#82-projects-and-quotations) PG-012; [§8.7](../07-ux-design/INFORMATION_ARCHITECTURE.md#87-the-approved-screens-scr-01scr-60-and-their-pages) SCR-14
- [ADMIN_FLOW §3](../07-ux-design/ADMIN_FLOW/s01-04-foundations.md#3-derived-interaction-requirements) UXI-24, UXI-26, UXI-40; [§10.7](../07-ux-design/ADMIN_FLOW/s10-b-journeys.md#107-completion-cancellation-and-migration) J-PRJ-03; [§11](../07-ux-design/ADMIN_FLOW/s11-13-corrections-numbering-import.md#11-corrections-and-reversals) CM-32's answer; [§14](../07-ux-design/ADMIN_FLOW/s14-15-messages-scenarios.md#14-message-catalogue) MSG-39, MSG-40; [§15](../07-ux-design/ADMIN_FLOW/s14-15-messages-scenarios.md#workflow-presentations) UXS-37
- [DESIGN_SYSTEM §9.3](../07-ux-design/DESIGN_SYSTEM.md#93-forms-and-entry) PT-29; [§9.4](../07-ux-design/DESIGN_SYSTEM.md#94-actions-overlays-and-feedback) PT-16, PT-19, PT-26
- [TEST_STRATEGY §5](../08-testing/TEST_STRATEGY.md#5-domain-and-data-integrity) C-38, C-39 and the acceptance contracts; [§6](../08-testing/TEST_STRATEGY.md#6-concurrency-and-failure-proofs) M-26, M-27; [§7](../08-testing/TEST_STRATEGY.md#7-browser-end-to-end-tests-and-critical-journeys) CJ-17; [SECURITY_TEST_MATRIX §3](../08-testing/SECURITY_TEST_MATRIX.md#3-the-matrix) SM-04, SM-05
- [GAP_REGISTER](../00-governance/GAP_REGISTER.md#gap-022--completion-can-hide-unresolved-obligations-or-become-stale-after-correction) GAP-022
- Architecture challenge — the sequences 4a, 7b of the [P11 gate record](evidence/P11_QUALITY_GATE.md#whole-system-architecture-challenge-sp-98), their assertions carried into this Task's acceptance criteria and tests
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (57): CAP-18; AC-21; IMG-05; IMG-08–IMG-09; OWN-03; BR-XD-01–BR-XD-07; BR-PRJ-01–BR-PRJ-06; WF-PRJ-03; SF-REVAL; CM-17; CM-32; AX-25–AX-26; L-01; L-10–L-12; L-20–L-21; L-39; L-49–L-50; ROUTE-012; ROUTE-161–ROUTE-162; INT-161–INT-162; C-38–C-39; C-51; BD-10; BD-13; M-26–M-27; PG-012; SCR-14; UXI-24; UXI-26; UXI-40; J-PRJ-03; UXS-37; NAV-012; GAP-022; GAP-066; GAP-071

**EXPECTED MODULES / FILE AREAS:**

- the PRJ module's CompleteProject and ForceCompleteProject actions, its completion query class for PG-012 with the predicate set queries, the snapshot serializer and hash, the revalidation service and the residual-command precondition, and PRJ's part of `DocumentChangeParticipant` (RC-02; ARCHITECTURE §4); `resources/js/Pages/PRJ` for PG-012; `tests/Feature/PRJ`, `tests/Concurrency/` for M-26 and M-27

**BUSINESS RULES:**

- SM:Project and BR-PRJ-02: ACTIVE → COMPLETED_NORMAL only with all ten gate predicates true, each evaluated over facts and decisions only; prohibited: completion with an unresolved predicate outside Force Complete, an Admin or API Force Complete, deletion of a project with committed history; COMPLETED_* → ACTIVE only by revalidation (BR-CR-05; ADM's requirements reopened, BR-ADM-03)
- BR-PRJ-04: Force Complete is the Owner's, with explicit confirmation, mandatory reason, individual actor, timestamp, before/after state and audit; it keeps unmet conditions and residual obligations visible, fabricates no stock, cash, settlement or document, and a force-completed project's surviving reservations stay visible as release-required residuals (QS-16); a receivable never disappears at completion (FS-08; IMG-08)
- the predicates' inputs as their own rules state them: BR-PRJ-03's waiver and BR-ADM-02 for the administration predicate; BR-CR-04's case closure for the returns predicate; the fulfilment predicate per line against its pinned demand under the latest APPROVED revision (L-20), a re-planned remainder on its split lines (L-21), a SERVICE line's handover as its delivery (L-39) and a drop-ship line by its recorded delivery, each line by its own mode (BR-XD-02; BR-XD-03); a document counted only issued, not voided and rendered — a draft never (BR-XD-04; predicate 4); a SIPLAH project's net remittance settled through its deduction settlement under predicate 7, its locked channel unchanged (BR-PRJ-05; C-51; BR-FIN-09); a migrated open project's opening receivables read as the facts the import recorded and opening customer credit an application source, never Cash-In (BR-XD-06; L-50); a fungible event committed after a reasoned likely-duplicate confirmation counted as recorded (L-49; BD-13) — completion adds no rule of its own to any of them
- L-11 and L-12: completion is an explicit decision that a permitted Admin may take; after completion or cancellation only the closed residual-command list applies; "material" means changing a predicate input or a project CALC value; on a CANCELLED project a correction changes only what it corrects (L-10; CM-32)
- BR-XD-01, BR-XD-05 and BR-XD-07: a return after delivery, billing or payment is linked but distinct corrections ending in the case's closure, and completion revalidates; committed effects update the signals without waiting for completion; the completion-versus-correction race is P6's mechanism and P8's proof (M-26)

**ARCHITECTURE RULES:**

- PRJ is tier 2 and reads the higher tiers' predicates only through `CompletionPredicateProvider` inside AX-25's transaction; the lower-tier DOC and OPS predicates through their query classes; revalidation runs inside the correcting command's transaction, through `DocumentChangeParticipant` for DOC's changes and `ProjectLifecycleParticipant` for ADM's reopening (ARCHITECTURE §3; §15)
- completion state is a decision: `projects.state` changes only with its `project_state_transitions` row in the same transaction; the predicates, blockers and residual obligations are derived, never stored as truth (DATABASE §9; L-01; FS-11)

**SECURITY / PERMISSION REQUIREMENTS:**

- ROUTE-012 `projects.view`; ROUTE-161 `projects.complete` (ADM, prerequisite `projects.view`); ROUTE-162 `projects.force_complete` (OWNER_ONLY), never grantable to an Admin (RG-02); an Admin request for ROUTE-162, and every import, job, console or API path, denied and security-logged (OD-12; LG-02)
- `projects.state`, transition kinds and completion snapshots never accepted from a client payload (OD-12; WS-02); the Force Complete section and its control absent for an Admin, its address answering as IP-21 (J-PRJ-03; AZ-11)

**COMPANY SCOPE AND FIELD PROJECTION:**

- the command's company is the project's (CS-03); the project's public identifier resolved through the scope resolver, a project outside scope answering PG-005 with MSG-09, identical to a nonexistent one (CS-04; AZ-11); the Owner's scope is every company (CS-01); a revalidation that a correction of another company's record triggers is a SYS consequence needing no further grant (AZ-08)
- projection rows 28 and 38: `projects` PV(c); `project_state_transitions` PV(c) with the snapshots' money keys FV(c) or CV(c) by family and a `correction_reference` to another company's record masked (PJ-12; PJ-04); on PG-012 each condition's state and what is missing for `projects.view`, the amounts of the billing, receivable and payment conditions only with `finance.view`, a family the viewer lacks absent, never masked or shown as zero (PJ-22; INFORMATION_ARCHITECTURE §8)

**CONCURRENCY / IDEMPOTENCY:**

- AX-25: STATIC lock set LK-00, LK-01, LK-03, the project held at LK-01; the ten predicates read through `CompletionPredicateProvider` after LK-01 is held; a conditional state change; REJECTED C-39 with the unmet predicates; result a transition, retry class A (CONCURRENCY_IDEMPOTENCY §7.1, §7.2); business identity project + intent (AX-25)
- AX-26: the same lock set; the predicate snapshot, unmet predicates and residual obligations recomputed after LK-01 is held, serialized canonically and hashed, a hash other than the confirmed one CONFLICT with the current snapshot (ST-04; BD-10; M-27); business identity project + snapshot (AX-26)
- every command that can change a predicate input or trigger SF-REVAL holds the affected projects at LK-01 before any lower anchor (§7.1's notes), so a completion and a correction serialize: correction first, the completion is REJECTED; completion first, the correction revalidates (M-26); SF-REVAL's fan-out follows the data-dependent protocol — every completed project whose predicate input or CALC value the action changes derived, then re-derived under the locks, a grown set restarting the action (§5) —, one REVALIDATE transition per project under its LK-01 lock, UNIQUE (`command_id`, `project_id`) (DATABASE §4.5)
- both commands carry `command_id` (CI-01); a replay returns the recorded outcome, re-authorized, never a second transition (CI-05); D-CC-04's project-row lock kept unless PFM-14 measures contention

**AUDIT:**

- each completion and Force Complete one business audit event in its transaction with the individual actor, the before/after state and its transition row — COMPLETE_NORMAL with its predicate snapshot; FORCE_COMPLETE with the predicate snapshot, unmet predicates, residual obligations, reason and confirmed hash (C-38) — at the highest authority class exercised, OWNER_ONLY for Force Complete, which therefore reaches the Owner review queue (BR-XC-01; AZ-10; LG-01; QS-14)
- a revalidation a REVALIDATE transition with its `correction_reference`, written and audited in the correcting command's transaction, the earlier completion record kept as immutable history (BR-CR-05; DATABASE §9); an Admin or API Force Complete attempt an ACCESS_DENIED security event (OD-12; LG-02)

**DATABASE IMPACT ASSESSMENT:**

- Schema change: NONE — uses the tables of TASK-005–TASK-009; a missing column is an executor discovery (AGENT_OPERATING_MODEL)
- Migration required: NO
- Existing data transformation: NONE
- Direct production DB access: FORBIDDEN
- Data-loss risk: NONE KNOWN

**MODULARITY / EXTENSIBILITY:**

- Module boundary affected: PRJ, tier 2 — reading the `CompletionPredicateProvider` parts of PUR, INV, FUL, FIN and ADM and implementing PRJ's part of DOC's `DocumentChangeParticipant`; its revalidation service called by the higher tiers' correcting actions (ARCHITECTURE §3)
- Stable core kept provider-neutral: YES
- External/vendor-specific code isolated: YES — framework facilities or a small interface (ARCHITECTURE §2)
- Replaceable adapter/contract required: NO — the contracts ARCHITECTURE §3 names exist (TASK-029, TASK-032); no new one
- Provider lock-in risk: NONE beyond the Owner's baseline stack
- Future extension impact: LOW
- Speculative abstraction avoided: YES — no stored stage, workflow status or generic rules engine (L-01; ARCHITECTURE §1; GAP-022)

**AUTH IMPACT:**

- Authentication change: NONE
- Authentication implementation: LARAVEL_NATIVE — decided once ([EXECUTION_CONTEXT](EXECUTION_CONTEXT.md)), reused, never redesigned
- Auth profile: N/A — this Task does not touch authentication
- Google sign-in: N/A — not touched
- Google registration behavior: N/A — not touched
- Google credentials owner action required: NO
- Authorization change: NONE — `projects.complete` and `projects.force_complete` exist in the catalogue (TASK-011)
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

- Context7: REQUIRED — the framework's database transactions with row locks and the affected-row count, canonical JSON encoding for the snapshot hash, and Inertia's deferred props, at the installed versions
- Graphify: OPTIONAL — once the predicate providers and the correcting commands exist
- Browser E2E: REQUIRED — J-PRJ-03, UXS-37 and CJ-17's completion and Force Complete steps
- Other: the two-connection harness (TASK-003) for M-26 and M-27; AC-21's fixtures — one blocker per condition
- Missing safe tool behavior: AUTO-INSTALL project-locally inside this Task (AICWDF §6.3) and record it in TOOLCHAIN; a paid tier or a privileged installation STOPS for the Owner (COST_POLICY)

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: refreshed before the change when Graphify is available ([TOOLCHAIN](../00-governance/TOOLCHAIN.md)); otherwise a targeted search of PRJ, the `CompletionPredicateProvider` implementations and every correcting action
- Pre-change questions: which commands change a predicate input or a project CALC value and must call SF-REVAL; which project-bound commands must read the residual-command precondition; which modules implement which predicate
- Expected affected areas: PRJ; the correcting actions of INV, FUL, FIN, PUR, CST, ADM and DOC, which call the revalidation

**CONTEXT7:**

- Topics to verify: pessimistic row locks inside the framework's transactions; JSON encoding with sorted keys and decimal strings for a stable hash; deferred and partial reloads naming their props (PF-36)
- Version-sensitive assumptions: none from memory

**UI / UX REQUIREMENTS:**

- Follow global design rules: YES
- Indonesian default: YES
- English support: YES
- Plain-language Indonesian: YES — each condition and what is missing in business words; MSG-39, MSG-40 and the REJECTED answer naming the unmet predicates (MSG-02)
- Language switch impact: keeps the unsent reason in the Force Complete dialog
- Mobile-first: YES — `[desk]`, readable on the phone: the conditions, and the Owner's Force Complete through the same confirmation (J-PRJ-03)
- Responsive: YES
- Adaptive: YES — stacked condition rows on the phone (NAV-012)
- Visual breathing room: REQUIRED
- Comfortable density: REQUIRED
- shadcn/ui reuse: the derived readiness list (PT-29), the page header with one primary action (PT-03), status badges (PT-13), the confirmation of an irreversible command with its reason field (PT-26) in an AlertDialog (PT-16), the loading and error states (PT-19)
- Design reference: PG-012 as INFORMATION_ARCHITECTURE §8.2 and J-PRJ-03 state it; the Golden Frontend Reference for presentation only (DIR-061 SD-02)
- Primary action: "Selesaikan Proyek", present with `projects.complete`; with a condition unmet the page lists it and the command answers REJECTED with the unmet predicates — no disabled state is contracted for it (ROUTE_CONTRACTS §4)
- Secondary actions: the Owner's "Selesaikan Paksa" in its own section; each unmet condition's link to where it is resolved
- Destructive actions: none; both commands are irreversible and confirmed first (IP-08), Force Complete with its reason (IP-09)
- Redundancy constraints: one primary action on the page (PT-03); "Selesaikan Paksa" only in its section, only for the Owner
- Loading state: PT-19 — a spinner after 300 ms for the conditions; the busy confirming button with one `command_id` (IP-02)
- Empty state: N/A — a project always shows its ten conditions
- Error state: REJECTED with the unmet predicates (MSG-02); CONFLICT on a changed snapshot with the new snapshot shown to confirm again (MSG-03; UXS-37); the reason required (MSG-12); IG-02's answers

**ROUTE IMPACT:**

- ROUTE-012 (PG-012's view); ROUTE-161 (normal completion); ROUTE-162 (Force Complete); no route is added for the revalidation, which runs inside the correcting commands

**INTERACTION CONTRACTS:**

- INT-161, INT-162; INT-019 for *Beranda*'s QS-15 row into PG-012 (TASK-058); IG-02's answers and IG-03's confirmations

**NAVIGATION CONTRACT:**

- Parent: PG-011 (NAV-012)
- Entry: the journey's *Penyelesaian* stage; *Pekerjaan* › *Penyelesaian proyek*; *Beranda*'s QS-15 row
- Back action: "Kembali" → PG-011
- Cancel: "Kembali" before the confirmation; the confirmation's "Kembali" returns to the page with the reason kept (IP-08; NV-02)
- Success destination: PG-011 with the project COMPLETED_NORMAL or COMPLETED_FORCED
- Error behavior: NV-04, each step answering in place (IP-19); a project outside scope → PG-005 (IP-21)
- State preservation: unsent input kept across a re-login; MSG-35 before leaving with an unsent reason (NV-05)
- Mobile behavior: `[desk]` — stacked condition rows; the Owner's Force Complete through the same confirmation

**ACCEPTANCE CRITERIA:**

1. PG-012 shows all ten conditions of the project, each with its state and, when unmet, what is missing and where it is resolved, derived from the current facts and decisions every time it is shown (J-PRJ-03; UXI-26; L-01).
2. "Selesaikan Proyek" commits COMPLETED_NORMAL only when all ten predicates are true when re-read under the project's lock; with any one false it is REJECTED with the unmet predicates and nothing is written — one fixture per condition, delivery or full payment alone never passing (AX-25; C-39; AC-21).
3. No command, job, import or last fulfilling fact completes a project by itself: a project whose ten predicates are all true stays ACTIVE until the explicit decision (L-12; UXI-26).
4. Only the Owner force-completes: the confirmation carries the hash of the snapshot shown and a mandatory reason; the FORCE_COMPLETE transition holds the predicate snapshot, unmet predicates, residual obligations, reason, confirmed hash, actor and time; a missing reason is a validation answer, and a write without reason, snapshot or hash is refused by C-38 (AX-26; BR-PRJ-04; AC-21).
5. A blocker that changes between the page and the Force Complete command returns CONFLICT with the current snapshot, which the Owner must confirm again; the project is unchanged (ST-04; M-27; UXS-37).
6. An Admin's Force Complete — through the route, a forged payload, an import, a job, the console or any API path — is denied and security-logged; no payload sets `projects.state`, a transition kind or a snapshot (OD-12; WS-02; SM-04).
7. A force-completed project shows its unmet conditions and residual obligations: its open receivables stay active and ageing, its surviving reservations are release-required residuals, its open cases and missing documents stay listed, and no stock, cash, settlement or document was created (BR-PRJ-04; FS-08; IMG-08).
8. A completion racing a correction of the same project ends without a stale completion in both orders: correction first, the completion is REJECTED; completion first, the correction revalidates the project in its own transaction (M-26; BR-XD-07).
9. A material correction on a completed project — a client return after delivery, billing and payment, a CM-17 cost cascade over several projects, a document void or an evidence replacement — returns each affected project to ACTIVE with one REVALIDATE transition linked to the correction, the completion record kept; on a CANCELLED project it changes only the facts it corrects (SF-REVAL; CM-32; BR-XD-01; L-11; architecture challenge 4a, 7b).
10. On a COMPLETED_NORMAL or COMPLETED_FORCED project only the residual commands of WF-PRJ-03 are accepted without reopening, a residual command never revalidates, a command that would raise the outstanding runs as a correction, and anything else is REJECTED (L-11; UXI-40).
11. A project served from stock without a purchase, a service-only project and a drop-ship project each complete on the predicates their lines' modes need, with no dummy purchase, receipt or warehouse movement (BR-XD-02; BR-XD-03).
12. A draft, a voided version or a failed rendition never satisfies the documents predicate (BR-XD-04; predicate 4).
13. The same `command_id` replayed for either command returns its recorded outcome and never writes a second transition (CI-05; INT-161; INT-162).
14. Once the row and column of P11Q-02 (a) are approved, a project whose project-originated credit is held with its reason meets predicate 9's credit condition, and credit neither applied, refunded nor held is one blocker (WF-PRJ-03 predicate 9; WF-FIN-06; TASK_PLAN §8).

**TEST REQUIREMENTS:**

- Lint: TL-01
- Typecheck: TL-02
- Unit: the materiality test; the canonical snapshot serialization and its hash, an amount change changing the hash (TL-03; ST-04)
- Feature: ROUTE-012, ROUTE-161 and ROUTE-162 with each refusal; one blocker per condition; the residual-command precondition on each state; predicate 9 with held credit once the hold exists (TL-04)
- Integration: M-26 in both orders and M-27 on real PostgreSQL; revalidation inside a correction's, a document void's and an evidence replacement's transaction; C-38 and C-39 refusals; `ReconcileGuards` clean after each (TL-05)
- Authorization: `projects.view`, `projects.complete` and the Owner's `projects.force_complete`; the Admin and API denials (TL-06)
- Route integrity: ROUTE-012, ROUTE-161 and ROUTE-162 audited (TL-07)
- Interaction: INT-161 and INT-162, each with one `command_id` replayed (TL-08; IG-05)
- Browser E2E: J-PRJ-03 and UXS-37 on the desktop; the Owner's Force Complete on the phone; CJ-17's completion steps (TL-09)
- Localization/language switch: both languages on PG-012 and its dialogs (TL-10)
- Visual breathing room/density: TEST_STRATEGY §9
- Responsive: TL-11
- Accessibility: TL-12
- Production build: TL-13
- Security: SM-04 for W4-09 and W4-16 with OD-12; SM-01's forged and foreign identifiers on ROUTE-012, ROUTE-161 and ROUTE-162; SM-25 for the snapshots' money keys; SM-05's authority class of Force Complete (TL-14)
- Performance: PFM-02 — PG-012's query count independent of the project's lines, the predicates as set queries (PF-24); PFM-20 for the two class-A commands; PFM-14's project-row contention (TL-15)
- Migration: N/A — no migration or import code in this Task
- Other: GAP-022's cases — missing confirmation, reason or audit, a forged Admin or API override, duplicate and concurrent requests, post-completion returns and corrections

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

- TASK-058 — when its other dependencies are DONE and its READY gate holds

**DO NOT DO:**

- add to V1 — a capability, page, route, field, integration, document type, report or paid service — or drop, defer or narrow a V1_REQUIRED requirement; a Task that would need either raises an Owner question and waits ([DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-20)
- access the VPS, production, its data or a credential, or put a secret, real data, a host detail or production configuration in the repository ([INFRASTRUCTURE](../09-operations/INFRASTRUCTURE.md) INF-01, INF-05; SECURITY SX-01, SX-04, SX-06; [PRODUCTION_DATA_SAFETY](../00-governance/PRODUCTION_DATA_SAFETY.md))
- add AI, model or tool attribution to a commit — the Owner's configured identity only ([AGENTS.md](../../AGENTS.md#git-commit-attribution); DIR-022)
- start before the Owner's execution authorization at OG-4, or mark DONE without the required tests passing and the completion evidence ([TASK_PLAN](TASK_PLAN.md#2-rules-of-the-plan); [DEFINITION_OF_DONE](../08-testing/DEFINITION_OF_DONE.md#2-the-checklist))
- weaken, skip or delete a valid test to obtain a green run, or adopt a paid tier without the Owner's COST_POLICY decision (DOD-05; [COST_POLICY](../00-governance/COST_POLICY.md))
- complete a project automatically, from a job, import, console or API path, or with a state, transition kind or snapshot taken from the client (L-12; OD-12; WS-02)
- let Force Complete create, settle, write off or hide any stock, cash, receivable, settlement or document, or present it as a normal completion (BR-PRJ-04; FS-08)
- store a predicate, blocker or residual obligation as truth, reactivate a CANCELLED project, or revalidate on a residual command (L-01; L-11)

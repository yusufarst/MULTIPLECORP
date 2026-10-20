# TASK-031 — Correction cases

Status: APPROVED | Updated: 2026-10-20 | Owner: Planning

Approval: [APPR-016](../00-governance/DECISION_LOG.md#appr-016--p11-task-plan-tasks-and-feature-coverage-matrix-approved), explicit Owner approval on 2026-10-20 (DIR-085 OF-07) of this document as committed in the first execution session's approval commit; the approved file hash and the verified conditions are recorded there. Approval changes lifecycle only, not implementation authorization.

Written by WF-PC5-W's writer of bundle W1 of run PC-5 on 2026-10-19 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99; the Owner approved P11 with the [Task plan](TASK_PLAN.md) at OG-4 (DIR-085 OF-07). **No Task starts before the freeze record, from which the execution authorization is in force (DIR-085 OF-09).** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** TODO

**RISK CLASS:** HIGH

**EXECUTOR:** AGENT

**OBJECTIVE:**

Build the correction case and the shared correction service every correcting command uses: a case opened with its reason and links by the correction or discrepancy it records, its linked corrections gathered, its residuals — an unbacked refund, an unresolved deduction, an unaccounted returned cost — posted as guard counters and shown as the reason it cannot close, "Tutup Kasus" when none remains, the reopening when a later fact changes its figures, the reason step and the visible link between each original and its correction, and PG-040 *Kasus Koreksi* (SF-CASE; NX-06; ROUTE-040, ROUTE-271).

**WHY THIS EXISTS:**

Committed history never disappears: every correction revises, voids, reverses or returns with reason and linkage, and returns, discrepancies and other corrections end with an explicit closure that the completion predicates read (BR-CR-01–BR-CR-06; FS-02; architecture challenge 10a). A return after delivery, billing or payment is several linked but distinct corrections, none automatic, ending at the case's closure (BR-XD-01; architecture challenge 4a; GAP-005). Receiving, adjustments and completion (TASK-039, TASK-042, TASK-055) build on the case and the correction service before the other correcting modules do.

**DEPENDS ON:**

- TASK-010 — Audit and security logging
- TASK-013 — Command envelope, command identity, idempotency and the lock order
- TASK-016 — Application shell, navigation and the company context

**BLOCKS:**

- TASK-039 — Receiving (Barang Masuk)
- TASK-042 — Condition, location moves, adjustments and losses
- TASK-055 — Project completion and Force Complete

**PARALLEL SAFE:** NO — the case row (LK-10) and its guard counters are written by every correcting command of later Tasks through this Task's service

**IN SCOPE:**

- `correction_cases` in use (DATABASE §4.13): the case kinds, OPEN and CLOSED, the reason, the causal project and source lot set by SYS from the cited facts (XL-3), and the guard counters written only by the posting services in the transactions of the linked facts; every corrective row carrying `correction_case_id` keyed with the case's company, so one case gathers every linked action (DATABASE §13)
- the case's opening by the correcting command that records it, under that command's capability — there is no standalone case capability (PERMISSIONS_MATRIX §4) —, and its closure ROUTE-271 with INT-271 (`cases.close`; NX-06): the reason step (IP-09; MSG-40), the outcome recorded (SF-CASE; BR-CR-04), REJECTED while a residual stands (C-46); "Tutup Kasus" offered on PG-040, PG-028 and PG-034 only when none remains, the server deciding (ADMIN_FLOW §11)
- the reopening CLOSED → OPEN only by the posting service whose facts change the case's figures, in that same transaction, audited (C-46; L-06; L-46); the purchase-return closure equation — returned cost = refunded + replaced + unrecovered over posted figures — as the closure's CHECK (C-46; AX-11)
- the shared correction service for every module's correcting commands: reason, individual actor, recorded_at and linkage to the original (BR-CR-01); a contra or reversal referencing the original, both permanently visible (BR-CR-02); the legality matrix of BR-CR-06 with BR-CR-03's disposition of a billed invoice's reductions — only a draft deleted, with audit, a discarded draft project's number retired (L-37); a decision recorded in error superseded by one of the same authority (L-32), a write-off superseded only as L-17 states; a material correction on a completed project revalidating it, one on a cancelled project changing only what it corrects (BR-CR-05; L-11; SF-REVAL); C-53's model-level guards on append-only facts, decisions, snapshots and audit
- the correction's presentation every record page uses: the action on the record it corrects, named in the user's words; the form stating what will be reversed, contra'd or revised and what stays; the mandatory reason (IP-09; UXI-25; MSG-40); afterwards "Dikoreksi oleh" and "Mengoreksi" on each record and both in its timeline; no "hapus" for a committed fact (ADMIN_FLOW §11)
- the case side of the correction rows mapped here — CM-01–CM-04, CM-08, CM-10, CM-12, CM-19, CM-20, CM-23–CM-26, CM-29–CM-31 and CM-36–CM-38 —, each row's own command built by its module's Task (ADMIN_FLOW §11) on this service: CM-01's discard inside the form recording nothing; the companion corrections of a return listed as the case's open steps until done (CM-10, CM-12); an unbacked refund kept as the case's residual (CM-19; L-46); an unresolved deduction kept on the invoice correction's case (CM-24; L-06; L-30); SF-CONTRA opening a case when a correct fact must be re-recorded; a dispatch's identity substitution and a return recorded in error linked to what they correct (CM-36; CM-37; L-48); the caps those rows meet — C-16, C-45, C-55 — being their guards' CHECKs
- PG-040 with NAV-040 and its view ROUTE-040 (`projects.view` of the case's company; money counters by family, PJ-16; SCR-33): the cases with their residuals by branch, listing cases and starting none, each case reached from the record that opened it (INFORMATION_ARCHITECTURE §14.4)

**OUT OF SCOPE:**

- each correcting command with its own effects and statement sequence — its module's Task (ADMIN_FLOW §11; CONCURRENCY_IDEMPOTENCY §7) —, and a discarded draft project's tombstone (L-37; TASK-032)
- the command envelope's common checks (TASK-013) and the audit writer (TASK-010); the completion predicates and revalidation's project transition (TASK-055)
- QS-07's open cases on *Pekerjaan* (TASK-058) and the journey's *Retur dan koreksi* condition (TASK-032)

**REFERENCES:**

- [WORKFLOWS §6](../03-workflows/WORKFLOWS/s06-07-subflows-partial.md#6-reusable-subflows) SF-CASE, SF-CONTRA, SF-REVAL; [§8](../03-workflows/WORKFLOWS/s08-09-corrections-indivisible.md#8-correction-reversal-and-cancellation-matrix) CM-01–CM-38; [§12](../03-workflows/WORKFLOWS/s10-14-signals-decisions-traceability.md#12-planner-determined-level-1-decisions) L-06, L-11, L-17, L-30, L-32, L-37, L-46, L-48
- [BUSINESS_RULES §2](../02-domain/BUSINESS_RULES.md#2-correction-taxonomy) BR-CR-01–BR-CR-06; [§6](../02-domain/BUSINESS_RULES.md#6-cross-domain-interaction-rules) BR-XD-01; [§7](../02-domain/BUSINESS_RULES.md#7-forbidden-shortcuts) FS-02
- [DATABASE §4.13](../04-architecture/DATABASE/s04-13-ops.md#413-ops--operations) `correction_cases`; [§13](../04-architecture/DATABASE/s13-17-history-scope-types.md#13-correction-and-history-representation); [§18](../04-architecture/DATABASE/s18-19-constraints-integrity.md#18-constraint-catalogue) C-16, C-45, C-46, C-53, C-55
- [PERMISSIONS_MATRIX §4](../05-security/PERMISSIONS_MATRIX.md#4-capability-catalogue) `cases.close`; [§7.2](../05-security/PERMISSIONS_MATRIX.md#72-table-classification) row 121; [§7.3](../05-security/PERMISSIONS_MATRIX.md#73-special-projections) PJ-04, PJ-16; [§8](../05-security/PERMISSIONS_MATRIX.md#8-cross-company-link-authorization) XL-2, XL-3
- [ROUTE_CONTRACTS](../03-workflows/ROUTE_CONTRACTS.md#operations--correction-cases-and-the-opening-import-ops-pg-040-pg-058-wf-mig-01) ROUTE-271; [§2](../03-workflows/ROUTE_CONTRACTS.md#2-page-views--route-001route-065) ROUTE-040
- [INTERACTION_CONTRACTS](../03-workflows/INTERACTION_CONTRACTS.md#operations--correction-cases-and-the-opening-import-ops-pg-040-pg-058-wf-mig-01) INT-271
- [NAVIGATION_CONTRACTS §2.4](../07-ux-design/NAVIGATION_CONTRACTS.md#24-fulfilment-cases-and-documents) NAV-040; [INFORMATION_ARCHITECTURE §8.4](../07-ux-design/INFORMATION_ARCHITECTURE.md#84-fulfilment-cases-and-documents) PG-040; [§14.4](../07-ux-design/INFORMATION_ARCHITECTURE.md#144-scr-33--kasus-koreksi-its-cross-project-home)
- [ADMIN_FLOW §11](../07-ux-design/ADMIN_FLOW/s11-13-corrections-numbering-import.md#11-corrections-and-reversals); [IP-09](../07-ux-design/ADMIN_FLOW/s05-command-model.md#ip-09--corrections-closures-and-destructive-actions); [§3](../07-ux-design/ADMIN_FLOW/s01-04-foundations.md#3-derived-interaction-requirements) UXI-25; [§14](../07-ux-design/ADMIN_FLOW/s14-15-messages-scenarios.md#14-message-catalogue) MSG-40
- [CONCURRENCY_IDEMPOTENCY §7.3](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s07-statement-sequences.md#73-command-families-outside-ax-0137) NX-06; [§4.2](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s04-06-locks-envelope.md#42-lock-classes) LK-10
- [SECURITY_TEST_MATRIX](../08-testing/SECURITY_TEST_MATRIX.md#3-the-matrix) SM-04, SM-25
- [GAP_REGISTER](../00-governance/GAP_REGISTER.md#gap-005--cancellation-may-encounter-delivered-billed-or-paid-downstream-records) GAP-005
- Architecture challenge — the sequences 4a, 10a of the [P11 gate record](evidence/P11_QUALITY_GATE.md#whole-system-architecture-challenge-sp-98), their assertions carried into this Task's acceptance criteria and tests
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (74): O-06; CAP-13; AC-12; REF-057; BR-CR-01–BR-CR-06; SF-CASE; SF-CONTRA; CM-01–CM-38; L-06; L-11; L-17; L-30; L-32; L-37; L-46; L-48; ROUTE-040; ROUTE-271; INT-271; C-16; C-45–C-46; C-53; C-55; NX-06; PG-040; SCR-33; UXI-25; IP-09; MSG-40; NAV-040; GAP-005

**EXPECTED MODULES / FILE AREAS:**

- the OPS module's OpenCorrectionCase and CloseCorrectionCase actions, the case's posting service for its guard counters and its query classes (ARCHITECTURE §3); the shared correction service and the "Dikoreksi oleh" / "Mengoreksi" presentation; `resources/js/Pages/OPS` for PG-040; `tests/Feature/OPS` and `tests/Reconciliation/`

**BUSINESS RULES:**

- BR-CR-01–BR-CR-06: every correction carries its reason, actor, time and linkage; immutable facts are corrected only by contra-facts or reversals that reference them, both visible; returns, discrepancies and other cases end with an explicit closure decision; material corrections after completion force revalidation; the legality matrix decides which primitive applies
- BR-XD-01: a return after delivery, billing or payment is linked but distinct stock, delivery and financial corrections, each explicit, none automatic, ending with the case's closure (architecture challenge 4a)
- FS-02: no hard or cascading delete of referenced history; SF-CASE: a case carrying an unbacked-refund, unresolved-deduction or unaccounted returned-cost residual cannot close (L-06; L-46)

**ARCHITECTURE RULES:**

- OPS is tier 0 (ARCHITECTURE §3): higher modules post the case's counters through its posting service in their own transactions; the counters are never written by a user and are rebuildable and reconciled (DATABASE §3.1; C-61)
- a correction is always a new row referencing what it corrects, never the reverse; no table has an `is_deleted` flag (DATABASE §13)

**SECURITY / PERMISSION REQUIREMENTS:**

- ROUTE-040 `projects.view` of the case's company; ROUTE-271 `cases.close` (ADM_PLUS, separately granted, RG-08), with the reason mandatory (UXI-25); opening a case needs the capability of the correction or discrepancy it records (PERMISSIONS_MATRIX §4)
- a case-chosen effect on another company's record needs a grant on that company or is refused and routed (AZ-08; IP-06; QS-20)

**COMPANY SCOPE AND FIELD PROJECTION:**

- a case belongs to its company; its causal project or source lot may name another company's record — an intentional link set by SYS, never chosen (XL-3; DATABASE §15) —, and rows for the other side are written only as SYS consequences of the case's commands (XL-2)
- PJ-16 and row 121: the case with `projects.view` of its company; the residual and equation counters by family — finance counters `finance.view`, cost counters `cost.view`; another company's project or lot named only when that company is in scope, otherwise the relation marker (PJ-04)
- PG-040 read one company at a time, the scope inside the query (PF-13; CS-07)

**CONCURRENCY / IDEMPOTENCY:**

- NX-06 (LK-00, LK-01, LK-03, LK-10) with `command_id` (CI-01): LK-01 holds the case's project and the causal project it names, of whichever company, before LK-10, so a closure never races a correction that changes a predicate (CONCURRENCY_IDEMPOTENCY §7.1 notes); the closure is refused by the case's CHECKs while a residual remains (C-46)
- a command that can reopen a closed case holds the projects that case names at LK-01 (AX-05, AX-20, AX-22, AX-29, AX-32–AX-34, AX-36; NX-10, NX-16), and the reopen commits in its transaction; a replay returns the recorded outcome, re-authorized (CI-05)

**AUDIT:**

- every case open, closure and reopen is a business audit event with the reason, the individual actor, the outcome and the linked records (BR-XC-01; BR-CR-01; LG-01); a closure is ADM_PLUS and reaches the Owner's review (AZ-10; QS-14); the correction's timeline shows the original and its correction linked (PJ-11; ADMIN_FLOW §11)

**DATABASE IMPACT ASSESSMENT:**

- Schema change: NONE — uses the tables of TASK-005–TASK-009; a missing column is an executor discovery (AGENT_OPERATING_MODEL)
- Migration required: NO
- Existing data transformation: NONE
- Direct production DB access: FORBIDDEN
- Data-loss risk: NONE KNOWN

**MODULARITY / EXTENSIBILITY:**

- Module boundary affected: OPS, tier 0, called by the correcting actions of every higher module (ARCHITECTURE §3)
- Stable core kept provider-neutral: YES
- External/vendor-specific code isolated: YES — framework facilities or a small interface (ARCHITECTURE §2)
- Replaceable adapter/contract required: NO
- Provider lock-in risk: NONE beyond the Owner's baseline stack
- Future extension impact: MEDIUM — every later correcting Task posts to the case through this service
- Speculative abstraction avoided: YES — no generic workflow or rules engine for corrections (ARCHITECTURE §1); each CM row stays its module's action

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

- Context7: REQUIRED — PostgreSQL CHECK constraints over guard counters and the framework's model events for append-only guards, at the installed versions
- Graphify: OPTIONAL — once the correcting modules exist
- Browser E2E: REQUIRED — PG-040's paths and CJ-06's case closure
- Other: synthetic linked facts that post the counters until the correcting Tasks exist; the reconciliation suite `Reconciliation/` (TASK-003)
- Missing safe tool behavior: AUTO-INSTALL project-locally inside this Task (AICWDF §6.3) and record it in TOOLCHAIN; a paid tier or a privileged installation STOPS for the Owner (COST_POLICY)

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: refreshed before the change when Graphify is available ([TOOLCHAIN](../00-governance/TOOLCHAIN.md)); otherwise a targeted search of OPS and the posting services
- Pre-change questions: which posting services write which case counter, and which commands can reopen a closed case
- Expected affected areas: OPS; every later correcting action that opens or posts to a case

**CONTEXT7:**

- Topics to verify: CHECK constraints referencing several columns of one row; deferred checks inside one transaction; the framework's model update guards
- Version-sensitive assumptions: none from memory

**UI / UX REQUIREMENTS:**

- Follow global design rules: YES
- Indonesian default: YES
- English support: YES
- Plain-language Indonesian: YES — each residual named in business words as the reason a case cannot close; the reason field's MSG-40; the correction forms' statement of what changes and what stays (IP-09)
- Language switch impact: keeps unsent input
- Mobile-first: YES — a `[desk]` page readable on the phone (NV-06)
- Responsive: YES
- Adaptive: N/A — one list and record layout, readable on the phone
- Visual breathing room: REQUIRED
- Comfortable density: REQUIRED
- shadcn/ui reuse: the data table and dense rows (PT-05, PT-06), the detail layout (PT-12), the confirmation with its reason (PT-26), the activity timeline with the linked correction (PT-18), status badges (PT-13)
- Design reference: PG-040 as INFORMATION_ARCHITECTURE §8.4 and §14.4 state it, the correction flows as ADMIN_FLOW §11; the Golden Frontend Reference for presentation only (DIR-061 SD-02)
- Primary action: "Tutup Kasus" when no residual remains
- Secondary actions: opening a case's linked record; the correction's own action on its record (its module's Task)
- Destructive actions: none — no control deletes a case or a fact; corrections are new rows (DATABASE §13)
- Redundancy constraints: a case is never started from PG-040, only from the record it corrects (INFORMATION_ARCHITECTURE §14.4)
- Loading state: PT-19
- Empty state: no open case — one sentence (PT-19)
- Error state: the closure's refusal naming the residual that stands (C-46); MSG-02 with the rule in words

**ROUTE IMPACT:**

- ROUTE-040 (PG-040's view); ROUTE-271 (close a case, also from PG-028 and PG-034); every correcting route of the module Tasks opens or posts to a case through this service

**INTERACTION CONTRACTS:**

- INT-271; the reason step of every correcting INT- contract (IG-03; IP-09)

**NAVIGATION CONTRACT:**

- Parent: none — *Dokumen* › *Kasus Koreksi*; PG-007; PG-011 (NAV-040)
- Entry: *Pekerjaan* › *Dokumen dan kasus*; the journey; *Dokumen* › *Kasus Koreksi*
- Back action: "Kembali" → the origin (NAV-040)
- Cancel: the reason step's "Kembali" → the case, the input kept (IP-09; NV-02)
- Success destination: the case closed with its outcome in place (ROUTE-271)
- Error behavior: NV-04 — the refusal in place with the residual named
- State preservation: filters, sort, tab and cursor in the address (IP-18; NV-05)
- Mobile behavior: `[desk]` — readable on the phone

**ACCEPTANCE CRITERIA:**

1. A case opens only inside the correcting command that records it, with its reason and links and under that command's capability (SF-CASE; PERMISSIONS_MATRIX §4).
2. "Tutup Kasus" appears only when no residual remains; a closure sent while an unbacked refund, an unresolved deduction or an unaccounted returned cost stands is REJECTED with the residual in words, and a purchase-return case closes only when its returned cost equals refunded + replaced + unrecovered (C-46; ROUTE-271).
3. A later fact that changes a closed case's figures reopens it in the same transaction, audited, and the completion predicate it feeds is false again (C-46; BR-CR-04).
4. Every correction records its reason, actor, time and link to the original; the original stays visible with "Dikoreksi oleh", the correction with "Mengoreksi", and no screen offers deleting a committed fact (BR-CR-01; BR-CR-02; FS-02; architecture challenge 10a).
5. A return after delivery and billing produces linked but distinct stock, delivery and financial corrections, none automatic, listed as the case's open steps until done, and the case's closure is what the completion predicate reads (BR-XD-01; architecture challenge 4a).
6. A material correction on a completed project returns it to ACTIVE with a link, and one on a cancelled project changes only what it corrects (BR-CR-05; L-11; CM-32).
7. A closure racing a correction of the same case or project serializes at LK-01: the closure sees the correction, or the correction reopens the closed case (NX-06; C-46).
8. A case's money counters reach only their family's viewers, and another company's causal project or lot shows only the relation marker (PJ-16; PJ-04; XL-3).
9. The case counters reconcile with the facts that posted them, and a drift is reported, never repaired silently (C-61; DATABASE §3.1).

**TEST REQUIREMENTS:**

- Lint: TL-01
- Typecheck: TL-02
- Unit: the closure's residual checks and the purchase-return equation (TL-03)
- Feature: ROUTE-040 and ROUTE-271 with each refusal (TL-04)
- Integration: a closure racing a correction at LK-01; a reopen when a posted figure changes; the case counters in the reconciliation suite (TL-05)
- Authorization: `cases.close` as a separately granted ADM_PLUS capability; PJ-16's families on the counters (TL-06)
- Route integrity: ROUTE-040 and ROUTE-271 audited (TL-07)
- Interaction: INT-271 with one `command_id` replayed (TL-08; IG-05)
- Browser E2E: PG-040 from *Pekerjaan* and from the journey, and CJ-06's case closure (TL-09)
- Localization/language switch: both languages on PG-040 and the correction forms' shared parts (TL-10)
- Visual breathing room/density: TEST_STRATEGY §9
- Responsive: TL-11
- Accessibility: TL-12
- Production build: TL-13
- Security: SM-04 for `cases.close`; SM-25 for the counters; SM-01 for another company's case (TL-14)
- Performance: PFM-01 and PFM-02 for PG-040; PFM-20 for ROUTE-271 (TL-15)
- Migration: N/A — no migration or import code in this Task
- Other: each listed CM row's expected end state (WORKFLOWS §8) asserted by its module's Task through this service; here on synthetic linked facts (TEST_STRATEGY §5)

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

- TASK-039 — when its other dependencies are DONE and its READY gate holds

**DO NOT DO:**

- add to V1 — a capability, page, route, field, integration, document type, report or paid service — or drop, defer or narrow a V1_REQUIRED requirement; a Task that would need either raises an Owner question and waits ([DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-20)
- access the VPS, production, its data or a credential, or put a secret, real data, a host detail or production configuration in the repository ([INFRASTRUCTURE](../09-operations/INFRASTRUCTURE.md) INF-01, INF-05; SECURITY SX-01, SX-04, SX-06; [PRODUCTION_DATA_SAFETY](../00-governance/PRODUCTION_DATA_SAFETY.md))
- add AI, model or tool attribution to a commit — the Owner's configured identity only ([AGENTS.md](../../AGENTS.md#git-commit-attribution); DIR-022)
- start before the Owner's execution authorization at OG-4, or mark DONE without the required tests passing and the completion evidence ([TASK_PLAN](TASK_PLAN.md#2-rules-of-the-plan); [DEFINITION_OF_DONE](../08-testing/DEFINITION_OF_DONE.md#2-the-checklist))
- weaken, skip or delete a valid test to obtain a green run, or adopt a paid tier without the Owner's COST_POLICY decision (DOD-05; [COST_POLICY](../00-governance/COST_POLICY.md))
- delete, overwrite or cascade-delete a committed fact, decision, snapshot or audit row, or add an `is_deleted` flag (FS-02; C-53; DATABASE §13)
- let a user write a case counter, close a case with a residual, or start a case from PG-040 (C-46; INFORMATION_ARCHITECTURE §14.4)
- make a companion correction automatic, or record a correction without its reason and its link to the original (BR-XD-01; BR-CR-01)

# TASK-056 — Project cancellation

Status: REVIEW | Updated: 2026-10-20 | Owner: Planning

Written by WF-PC5-W's writer of bundle W3 of run PC-5 on 2026-10-19 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99 — a REVIEW draft of P11, approved, or not, with the [Task plan](TASK_PLAN.md) at OG-4. **No Task starts before the Owner's execution authorization at OG-4.** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** TODO

**RISK CLASS:** HIGH

**EXECUTOR:** AGENT

**OBJECTIVE:**

Build the project's cancellation: "Batalkan Proyek" in PG-011's *Tindakan Lain*, whose dialog lists first, as links, the companion corrections for whatever is really being undone and then takes the mandatory reason; the command `PRJ CancelProject` (AX-27), which moves an ACTIVE project to CANCELLED and releases every active reservation of its lines in the same transaction; and the CANCELLED state afterwards — the facts that stay true kept and shown, only the residual commands and the section 8 corrections accepted, never a reactivation (ROUTE-163; INT-163; WF-PRJ-04; J-PRJ-04).

**WHY THIS EXISTS:**

BR-PRJ-01 allows cancellation at any stage but unwinds nothing silently: delivered, billed or paid effects need their own explicit companion corrections, while debt that stays owed stays collectable (L-10; FS-08; GAP-005). A cancellation that left reservations active would keep phantom demand on the shared pool, so it releases them atomically, and a racing dispatch or receipt either wins or loses whole (AX-27). P2 defines no transition out of CANCELLED, so later corrections must change only the facts they correct (L-11).

**DEPENDS ON:**

- TASK-038 — Reservations
- TASK-041 — Dispatch (Barang Keluar) and inter-company allocation
- TASK-046 — Direct shipment (drop-ship)
- TASK-047 — Service fulfillment and handover
- TASK-049 — Invoices and the pre-payment Kuitansi
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

**PARALLEL SAFE:** NO — it changes the project's state under LK-01 and releases reservations under the product guards that dispatch and receiving also hold

**IN SCOPE:**

- "Batalkan Proyek" in PG-011's *Tindakan Lain* for `projects.cancel` (J-PRJ-04): its dialog lists, as links to their own commands, the companion corrections for what is actually being undone — returns, invoice voids or revisions with their disposition, purchase cancellation or remainder closure, remaining-scope cancellation —, each committed separately with its own answer (IP-19; WF-PRJ-04), then takes the reason (MSG-40; IP-09); it says that received goods stay as free pool stock and that paid money returns as a supplier refund (ADMIN_FLOW §11, CM-29–CM-31)
- ROUTE-163 with INT-163, `PRJ CancelProject` (AX-27): an ACTIVE project, not already cancelled or completed, moves to CANCELLED with its CANCEL transition and its reason; SYS releases every active reservation of its lines in the same action, through INV's part of `ProjectLifecycleParticipant` (TASK-038; ARCHITECTURE §3), so RESERVED falls and AVAILABLE is restored (CM-29; BR-RSV-02)
- the facts that stay true after cancellation, recorded and shown on PG-011: goods kept by the client, value legitimately owed such as a retained DP, received goods as free pool stock with their original provenance, open receivables active, ageing and collectable (L-10; CM-30; CM-31; FS-08)
- the CANCELLED state's precondition, read under LK-01 by every project-bound command: only the residual commands of WF-PRJ-03's closed list and the section 8 corrections apply, each correction changing only the facts it corrects with the project staying CANCELLED and its effects visible in QS-16; no transition leaves CANCELLED (L-11; CM-32; SM:Project) — the same list TASK-055 applies to the completed states
- WF-PRJ-04's guidance, without a refusal: a substantially fulfilled deal normally ends by remaining-scope cancellation and normal completion (TASK-034; TASK-055; L-12)

**OUT OF SCOPE:**

- the companion corrections themselves — returns (TASK-044, TASK-048), invoice voids and revisions (TASK-049), purchase cancellation and remainder closure (TASK-037), remaining-scope cancellation (TASK-034) — and the reservation release's posting inside INV (TASK-038)
- completion, Force Complete and SF-REVAL (TASK-055); a DRAFT project's discard (TASK-032; L-37)
- QS-16's residual obligations on the home pages (TASK-058); CJ-17's cancellation end to end (TASK-063)

**REFERENCES:**

- [WORKFLOWS §5.7](../03-workflows/WORKFLOWS/s05-07-08-completion-migration.md#wf-prj-04--project-cancellation-desk) WF-PRJ-04, and [WF-PRJ-03](../03-workflows/WORKFLOWS/s05-07-08-completion-migration.md#wf-prj-03--project-completion-penyelesaian-desk)'s residual commands and corrections on a CANCELLED project; [§8](../03-workflows/WORKFLOWS/s08-09-corrections-indivisible.md#8-correction-reversal-and-cancellation-matrix) CM-29–CM-32; [§9](../03-workflows/WORKFLOWS/s08-09-corrections-indivisible.md#9-indivisible-business-actions) AX-27; [§12](../03-workflows/WORKFLOWS/s10-14-signals-decisions-traceability.md#12-planner-determined-level-1-decisions) L-10–L-12, L-20, L-21
- [BUSINESS_RULES SM:Project](../02-domain/BUSINESS_RULES.md#smproject) BR-PRJ-01–BR-PRJ-06; [SM:Reservation](../02-domain/BUSINESS_RULES.md#smreservation) BR-RSV-02; [§7](../02-domain/BUSINESS_RULES.md#7-forbidden-shortcuts) FS-08
- [V1_SCOPE](../01-product/V1_SCOPE.md#must-ship) CAP-04, CAP-18; [ACCEPTANCE_CRITERIA](../01-product/ACCEPTANCE_CRITERIA.md#product-acceptance-contracts) AC-12, AC-21
- [DATABASE §4.5](../04-architecture/DATABASE/s04-05-prj.md#45-prj--projects--commercial) `project_state_transitions`; [§9](../04-architecture/DATABASE/s07-10-representations.md#9-project-and-commercial-representation); [§18](../04-architecture/DATABASE/s18-19-constraints-integrity.md#18-constraint-catalogue) C-04, C-05, C-38, C-39, C-51
- [ARCHITECTURE §3](../04-architecture/ARCHITECTURE.md#3-modules) `ProjectLifecycleParticipant`
- [PERMISSIONS_MATRIX §2](../05-security/PERMISSIONS_MATRIX.md#2-authorization-model) AZ-08, AZ-10, AZ-11; [§4](../05-security/PERMISSIONS_MATRIX.md#4-capability-catalogue) `projects.cancel`; [§5](../05-security/PERMISSIONS_MATRIX.md#5-workflows-4-mapping) W4-23; [§6](../05-security/PERMISSIONS_MATRIX.md#6-company-scope-rules) CS-03, CS-04; [§7.2](../05-security/PERMISSIONS_MATRIX.md#72-table-classification) rows 28, 38; [§7.3](../05-security/PERMISSIONS_MATRIX.md#73-special-projections) PJ-01, PJ-12
- [SECURITY §6](../05-security/SECURITY.md#6-audit-and-security-logging) LG-01
- [CONCURRENCY_IDEMPOTENCY §7.1](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s07-statement-sequences.md#71-lock-sets-of-ax-01ax-37) and [§7.2](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s07-statement-sequences.md#72-checks-effects-and-outcomes-of-ax-01ax-37) AX-27; [§10](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s10-13-identity-failure-retry.md#10-command-identity-and-replay) CI-01, CI-05
- [ROUTE_CONTRACTS](../03-workflows/ROUTE_CONTRACTS.md#projects--proyek-formulir-proyek-penawaran-the-journey-and-its-completion-prj-j-prj-01j-prj-05) ROUTE-163; [INTERACTION_CONTRACTS](../03-workflows/INTERACTION_CONTRACTS.md#projects--proyek-formulir-proyek-penawaran-the-journey-and-its-completion-prj-j-prj-01j-prj-05) INT-163
- [NAVIGATION_CONTRACTS §2.2](../07-ux-design/NAVIGATION_CONTRACTS.md#22-projects-and-quotations) NAV-011
- [ADMIN_FLOW §10.7](../07-ux-design/ADMIN_FLOW/s10-b-journeys.md#107-completion-cancellation-and-migration) J-PRJ-04; [§11](../07-ux-design/ADMIN_FLOW/s11-13-corrections-numbering-import.md#11-corrections-and-reversals) CM-29–CM-31; [§14](../07-ux-design/ADMIN_FLOW/s14-15-messages-scenarios.md#14-message-catalogue) MSG-40
- [DESIGN_SYSTEM §9.4](../07-ux-design/DESIGN_SYSTEM.md#94-actions-overlays-and-feedback) PT-16, PT-26
- [TEST_STRATEGY §5](../08-testing/TEST_STRATEGY.md#5-domain-and-data-integrity) — the CM-row families, the real concurrent test per AX and FX-03's late correction on a cancelled project; [§7](../08-testing/TEST_STRATEGY.md#7-browser-end-to-end-tests-and-critical-journeys) CJ-17; [SECURITY_TEST_MATRIX §5](../08-testing/SECURITY_TEST_MATRIX.md#5-capability-rows) W4-23
- [GAP_REGISTER](../00-governance/GAP_REGISTER.md#gap-005--cancellation-may-encounter-delivered-billed-or-paid-downstream-records) GAP-005
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (35): O-01; CAP-04; CAP-18; AC-12; AC-21; IMG-01–IMG-02; IMG-05; IMG-08–IMG-09; OWN-01; OWN-03; BR-PRJ-01–BR-PRJ-06; WF-PRJ-04; CM-29–CM-32; AX-27; L-10–L-12; L-20–L-21; ROUTE-163; INT-163; C-38–C-39; C-51; J-PRJ-04

**EXPECTED MODULES / FILE AREAS:**

- the PRJ module's CancelProject action and the CANCELLED state's precondition (RC-02; ARCHITECTURE §4); the cancellation dialog on PG-011 in `resources/js/Pages/PRJ`; `tests/Feature/PRJ`, `tests/Concurrency/` for AX-27's races

**BUSINESS RULES:**

- BR-PRJ-01 and L-10: cancellation at any stage of an ACTIVE project releases its reservations and requires the section 2 companion corrections for delivered, billed or paid effects; nothing is silently unwound; retained facts stay true and debt stays collectable (FS-08)
- SM:Project: the transition ACTIVE → CANCELLED with its reason and audit; no transition leaves CANCELLED — neither COMPLETE_NORMAL nor FORCE_COMPLETE, so C-39 and C-38 never apply to a cancelled project —, and a correction on it applies its section 8 row without a state change (L-11; CM-32)
- BR-PRJ-06 and BR-RSV-02: the reservations that confirmation made possible are released, never consumed or moved, by the cancellation; a reservation is never a physical movement
- L-20, L-21, BR-PRJ-05 and C-51: cancellation revises nothing — the latest APPROVED revision, its pins, the commercial snapshot, the company and the locked channel stay as recorded; the requirements and their waivers (BR-PRJ-03) stay as history, and the completion rules BR-PRJ-02 and BR-PRJ-04 never apply to a cancelled project
- L-12: normal completion stays an explicit decision; WF-PRJ-04's guidance — remaining-scope cancellation and normal completion for a substantially fulfilled deal — is shown, never enforced

**ARCHITECTURE RULES:**

- PRJ, tier 2, changes the state with its `project_state_transitions` row in the same transaction; INV releases the reservations through `ProjectLifecycleParticipant` inside PRJ's transaction (ARCHITECTURE §3; DATABASE §9)
- the companion corrections are separate commands of their modules, each with its own answer; the cancellation never performs them implicitly (IP-19; BR-PRJ-01)

**SECURITY / PERMISSION REQUIREMENTS:**

- ROUTE-163 `projects.cancel` (ADM_PLUS, W4-23; prerequisite `projects.view`), a separately grantable authority (RG-08); the control absent without it (AZ-01); an Admin with only ADM capabilities forbidden (SECURITY_TEST_MATRIX §5)
- `projects.state` and the transition kind derived on the server, never taken from the payload (WS-02)

**COMPANY SCOPE AND FIELD PROJECTION:**

- the command's company is the project's (CS-03); the project's public identifier resolved through the scope resolver, a project outside scope answering PG-005 with MSG-09 (CS-04; AZ-11); the release of the project's own reservations is a SYS consequence inside the authorized command (WF-PRJ-04; AZ-08)
- projection rows 28 and 38: `projects` PV(c); the CANCEL transition PV(c), its reason with the record (PJ-12); the released quantities reach a pooled view only as S1 figures (PJ-01)

**CONCURRENCY / IDEMPOTENCY:**

- AX-27: STATIC lock set LK-00, LK-01, LK-03, LK-13, LK-18, LK-19, the project held at LK-01; re-read under lock: the project not already cancelled or completed and its ACTIVE reservations; guards product, reservation and line; facts: the CANCEL transition, the project state and the RELEASE events; a changed project state answered CONFLICT; result a transition, retry class B (CONCURRENCY_IDEMPOTENCY §7.1, §7.2)
- a racing dispatch or receipt wins or the cancellation wins, never both: they meet at LK-01 — dispatch first, the cancellation releases only the remaining reservation; cancellation first, the dispatch is refused and a receipt on a still-open project purchase is taken as a residual command, its goods to the free pool with no reservation offer (AX-27; WF-PRJ-03)
- `command_id` (CI-01) with business identity project + intent (AX-27); a replay returns the recorded outcome, re-authorized (CI-05)

**AUDIT:**

- the cancellation one business audit event in its transaction with the individual actor, the reason, the before/after state and its CANCEL transition, at authority class ADM_PLUS, which reaches the Owner review queue (BR-XC-01; AZ-10; LG-01; QS-14); the RELEASE events written in the same transaction (AX-27)
- a later correction on the cancelled project keeps its own audit and linkage, the cancellation's record unchanged (BR-CR-01; L-11)

**DATABASE IMPACT ASSESSMENT:**

- Schema change: NONE — uses the tables of TASK-005–TASK-009; a missing column is an executor discovery (AGENT_OPERATING_MODEL)
- Migration required: NO
- Existing data transformation: NONE
- Direct production DB access: FORBIDDEN
- Data-loss risk: NONE KNOWN

**MODULARITY / EXTENSIBILITY:**

- Module boundary affected: PRJ, tier 2, calling INV's part of `ProjectLifecycleParticipant` inside its transaction (ARCHITECTURE §3)
- Stable core kept provider-neutral: YES
- External/vendor-specific code isolated: YES — framework facilities or a small interface (ARCHITECTURE §2)
- Replaceable adapter/contract required: NO — `ProjectLifecycleParticipant` exists (TASK-032)
- Provider lock-in risk: NONE beyond the Owner's baseline stack
- Future extension impact: LOW
- Speculative abstraction avoided: YES — no cascade engine; the companion corrections stay their modules' commands (BR-PRJ-01)

**AUTH IMPACT:**

- Authentication change: NONE
- Authentication implementation: LARAVEL_NATIVE — decided once ([EXECUTION_CONTEXT](EXECUTION_CONTEXT.md)), reused, never redesigned
- Auth profile: N/A — this Task does not touch authentication
- Google sign-in: N/A — not touched
- Google registration behavior: N/A — not touched
- Google credentials owner action required: NO
- Authorization change: NONE — `projects.cancel` exists in the catalogue (TASK-011)
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

- Context7: REQUIRED — the framework's database transactions with row locks, at the installed version
- Graphify: OPTIONAL — once the companion corrections' commands exist
- Browser E2E: REQUIRED — J-PRJ-04 and CJ-17's cancellation step
- Other: the two-connection harness (TASK-003) for AX-27's races
- Missing safe tool behavior: AUTO-INSTALL project-locally inside this Task (AICWDF §6.3) and record it in TOOLCHAIN; a paid tier or a privileged installation STOPS for the Owner (COST_POLICY)

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: refreshed before the change when Graphify is available ([TOOLCHAIN](../00-governance/TOOLCHAIN.md)); otherwise a targeted search of PRJ, INV's reservation release and the project-bound commands
- Pre-change questions: which commands must refuse on a CANCELLED project and which are residual; which records the dialog lists as companion corrections
- Expected affected areas: PRJ; INV's `ProjectLifecycleParticipant` part; every project-bound command's state precondition

**CONTEXT7:**

- Topics to verify: row locks and the lock order inside one transaction in the installed framework version
- Version-sensitive assumptions: none from memory

**UI / UX REQUIREMENTS:**

- Follow global design rules: YES
- Indonesian default: YES
- English support: YES
- Plain-language Indonesian: YES — the dialog's companion corrections, its statement on free pool stock and supplier refunds, and MSG-40, in business words (ADMIN_FLOW §11)
- Language switch impact: keeps the unsent reason
- Mobile-first: YES — `[desk]`; on the phone PG-011 shows the cancelled state readably (NV-06)
- Responsive: YES
- Adaptive: N/A — one dialog, the phone's full-screen form of NV-06
- Visual breathing room: REQUIRED
- Comfortable density: REQUIRED
- shadcn/ui reuse: the confirmation of a correcting command with its reason (PT-26) in an AlertDialog (PT-16); the status badge for *Dibatalkan* (PT-13)
- Design reference: J-PRJ-04 and PG-011's *Tindakan Lain* (INFORMATION_ARCHITECTURE §8.2); the Golden Frontend Reference for presentation only (DIR-061 SD-02)
- Primary action: the dialog's confirming button, after the reason
- Secondary actions: the links to each companion correction
- Destructive actions: "Batalkan Proyek" itself — irreversible, its consequence stated and its reason required (IP-09; PT-26)
- Redundancy constraints: one entry, in *Tindakan Lain*; no second cancellation control on the page
- Loading state: the busy confirming button with one `command_id` (IP-02)
- Empty state: N/A — a dialog, not a list; with nothing to undo it shows the reason alone
- Error state: REJECTED by AX-27's checks in business words (MSG-02); CONFLICT on a changed state (MSG-03); the reason required (MSG-12); IG-02's answers

**ROUTE IMPACT:**

- ROUTE-163 (cancel); PG-011's view ROUTE-011 shows the CANCELLED state (TASK-032); no new route

**INTERACTION CONTRACTS:**

- INT-163; IG-03's reason step and IG-02's answers

**NAVIGATION CONTRACT:**

- Parent: PG-011 (NAV-011)
- Entry: PG-011 *Tindakan Lain* › "Batalkan Proyek"
- Back action: the dialog's "Kembali" → PG-011, the reason kept (NV-02)
- Cancel: "Kembali" — nothing sent; MSG-35 first with an unsent reason
- Success destination: PG-011 with the project CANCELLED (ROUTE-163)
- Error behavior: NV-04 — the answer in the dialog, the reason kept; a project outside scope → PG-005 (IP-21)
- State preservation: the reason kept across a re-login (IP-11; NV-05)
- Mobile behavior: `[desk]` — the dialog as a full-screen form with its actions in a sticky bar (NV-06)

**ACCEPTANCE CRITERIA:**

1. "Batalkan Proyek" with `projects.cancel` and a reason moves an ACTIVE project to CANCELLED with one CANCEL transition, and releases every active reservation of its lines in the same transaction, RESERVED falling and AVAILABLE rising by the released quantity (AX-27; CM-29; BR-PRJ-01).
2. The dialog lists, as links to their own commands, the companion corrections for what is being undone — returns, invoice voids or revisions with their disposition, purchase cancellation or remainder closure, remaining-scope cancellation — and none of them is performed by the cancellation itself (J-PRJ-04; IP-19).
3. After cancellation the facts that stay true stay recorded and shown: delivered goods, a retained DP, received goods as free pool stock with their provenance, and open receivables active, ageing and collectable (L-10; CM-30; CM-31; FS-08).
4. A cancelled project is never reactivated or completed: a later correction applies its section 8 row with the project staying CANCELLED, a residual command of WF-PRJ-03's list is accepted, and any other command is REJECTED (L-11; CM-32; SM:Project).
5. A cancellation of a project already CANCELLED, COMPLETED_NORMAL or COMPLETED_FORCED is REJECTED, and one whose project state changed meanwhile is CONFLICT; nothing is written (AX-27).
6. A cancellation racing a dispatch for the project, and one racing a receipt on its purchase, end with one winner in both orders, no reservation both consumed and released, AVAILABLE ≥ 0 and `ReconcileGuards` clean (AX-27; C-04; C-05).
7. Without the reason the command is refused at the field; an Admin holding only ADM capabilities sees no control and is forbidden on the route; a project outside scope is not found (INT-163; W4-23; AZ-11).
8. The same `command_id` replayed returns the recorded outcome and never a second transition or release (CI-05).
9. A late correction on a cancelled project — FX-03's scenario — changes only the facts it corrects (L-11; TEST_STRATEGY §5).

**TEST REQUIREMENTS:**

- Lint: TL-01
- Typecheck: TL-02
- Unit: the CANCELLED state's command precondition (TL-03)
- Feature: ROUTE-163 with each refusal; CM-29, CM-30 and CM-31's end states (TL-04)
- Integration: AX-27's real concurrent tests against a dispatch and a receipt, both orders; the reservation release with its guards; `ReconcileGuards` clean (TL-05)
- Authorization: `projects.cancel` (ADM_PLUS) positive and denial, and outside the grants (TL-06)
- Route integrity: ROUTE-163 audited (TL-07)
- Interaction: INT-163 with one `command_id` replayed (TL-08; IG-05)
- Browser E2E: J-PRJ-04 on the desktop; CJ-17's cancellation step (TL-09)
- Localization/language switch: both languages on the dialog and PG-011's cancelled state (TL-10)
- Visual breathing room/density: TEST_STRATEGY §9
- Responsive: TL-11
- Accessibility: TL-12 on the dialog — focus, its return and the reason's label
- Production build: TL-13
- Security: SM-04 for W4-23; SM-01's forged and foreign identifiers on ROUTE-163 (TL-14)
- Performance: PFM-20 — AX-27, retry class B, proportional to the reservations it releases and within 2 s at 1x (QB-14) (TL-15)
- Migration: N/A — no migration or import code in this Task
- Other: FX-03's late correction on a cancelled project (TEST_STRATEGY §5)

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
- perform a companion correction implicitly, unwind a delivered, billed or paid effect silently, or make an open receivable disappear at cancellation (BR-PRJ-01; L-10; FS-08)
- reactivate, complete or force-complete a CANCELLED project, or leave one of its reservations active (SM:Project; AX-27)

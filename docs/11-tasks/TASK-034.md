# TASK-034 — Commercial confirmation and fulfillment planning

Status: REVIEW | Updated: 2026-10-20 | Owner: Planning

Written by WF-PC5-W's writer of bundle W1 of run PC-5 on 2026-10-19 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99 — a REVIEW draft of P11, approved, or not, with the [Task plan](TASK_PLAN.md) at OG-4. **No Task starts before the Owner's execution authorization at OG-4.** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** TODO

**RISK CLASS:** HIGH

**EXECUTOR:** AGENT

**OBJECTIVE:**

Build the commercial confirmation and the per-line fulfilment planning of a project: AX-13 for both paths — the client's approval of the latest sent revision (ROUTE-157) and "Konfirmasi Proyek" without a quotation, on the client's order evidence (ROUTE-158) — pinning each demand line's conversion, price and tax in the same transaction, re-confirmation and supersession included; PG-011's *Item dan pemenuhan* by mode — *Gudang*, *Kirim langsung*, *Jasa* —; AX-14's "Batalkan Sisa" and "Ubah Cara Pemenuhan" (ROUTE-159, ROUTE-160); and the company-issued SPK (DOC-09) as the confirmation's optional output (WF-PRJ-02; WF-FUL-01; SF-CANCEL-LINE).

**WHY THIS EXISTS:**

The commercial confirmation is the reservation trigger and the point where a project's demand becomes binding — normally the quotation's approval, otherwise an explicit decision on the client's order evidence, with no separate Order entity (BR-PRJ-06; CAP-04; AC-04). Its pins keep each line in base units with the price and tax as confirmed, whatever the masters do later (BR-INV-03; BR-SN-01), and every later step — reservation, purchase, dispatch, direct shipment, handover, invoice — cites the confirmed line instead of retyping it (OS-01; REF-017). Each line's mode decides which domains it touches, so an order served from stock needs no dummy purchase and a service-only project no warehouse movement (BR-XD-02; BR-XD-03; AC-20), and an unfillable remainder ends only by an explicit cancellation with its reason (PX-04).

**DEPENDS ON:**

- TASK-030 — Evidence and uploaded documents
- TASK-033 — Quotations

**BLOCKS:**

- TASK-035 — Administrative checklist and document readiness
- TASK-037 — Purchases — project and replenishment purchases
- TASK-038 — Reservations
- TASK-046 — Direct shipment (drop-ship)
- TASK-047 — Service fulfillment and handover
- TASK-049 — Invoices and the pre-payment Kuitansi
- TASK-057 — Opening import — preparation, dry run, sign-off and commit

**PARALLEL SAFE:** NO — it owns AX-13 and AX-14 under the project's row (LK-01) and the per-line guards (LK-19) that the reservation, purchase and fulfilment Tasks build on

**IN SCOPE:**

- AX-13, the commercial confirmation, as one PRJ step for its paths (WF-PRJ-02; DATABASE §25): the QUOTATION_APPROVAL kind when ROUTE-157 records *Disetujui* on the latest SENT revision within its validity (TASK-033's route and handler), the NO_QUOTATION kind by ROUTE-158, a later revision's approval as the re-confirmation, and a superseding confirmation of the same authority under CM-27, which TASK-035's ROUTE-169 starts — each writing in one transaction the confirmation, one `confirmation_line_pins` row per line with its entered quantity, unit, ratio, base quantity, unit price, sales value and tax components, the current pin pointers, the revision APPROVED, the line demand and commercial guards, the reservation adjustments and the SIPLAH lock (BR-INV-03; BR-SN-01; C-51)
- the MIGRATION kind's writing — an open project carried over at cutover, with pins for its remaining demand and its reservations recreated afterwards through normal commands —, reused by TASK-057's import (DATABASE §9, §24; WF-MIG-01)
- ROUTE-158 with INT-158, "Konfirmasi Proyek" on PG-011 for a project without a quotation: the client's SPK, Surat Pesanan or SIPLAH order uploaded and registered first as project-family evidence — CLIENT_SPK, CLIENT_ORDER, SIPLAH_ORDER — through TASK-030's ROUTE-228 and NX-07 (IP-16; SF-EVIDENCE; PJ-08), then MSG-39's confirmation (IP-08), the confirmation linking the evidence (LK-29)
- the re-confirmation's limits: the reservations a lower demand affects adjusted in the same action (BR-QUO-02); a demand below what is dispatched, confirmed, handed over, delivered or invoiced REJECTED with the path — a return or an invoice revision first (L-20; BR-XD-01; DATABASE §9; J-PRJ-02); a confirmed sales value never below the invoiced one (C-31)
- the confirmation's answer showing the pinned lines, then — as separate commands with their own answers (IP-19) — the reservations proposed for the warehouse lines (L-02; J-INV-02; TASK-038) and the optional company SPK; an account without `stock.reserve` and `stock.view` confirming alone, its warehouse lines left unreserved for a colleague (QS-01; J-PRJ-02)
- DOC-09, the company-issued SPK: PRJ's `DocumentSubjectProvider` for it — the project as subject, its confirmed inputs as precondition, the truthful company issuer and the draft marking until issued, never impersonating a client-issued SPK (WF-PRJ-02; WF-DOC-01; BR-DOC-04) —, drafted and issued on PG-036 through ROUTE-221 and ROUTE-222 (TASK-029)
- DOC-14 as this Task meets it: a client-issued Surat Pesanan is uploaded as the no-quotation path's order evidence and never generated (V1_SCOPE DOC-14; BR-DOC-04); the company-issued Surat Pesanan is a layout of the purchase record beside DOC-02 (WF-DOC-01; DATABASE §4.10, §10), whose subject is the purchase that TASK-037 builds
- PG-011's *Item dan pemenuhan* (J-FUL-01; WF-FUL-01): per confirmed line its mode — *Gudang*, *Kirim langsung* or *Jasa*, proposed on the demand line (WF-PRJ-01) and changed after confirmation only by ROUTE-160 (L-21) — and its progress by mode against the pinned demand, derived each time (L-01; PX-01; PX-08): for *Gudang* reserved, net dispatched, delivered and cancelled, with availability from the pooled view for `stock.view` (PJ-01; RD-02) and the rest shown as a shortage (QS-02) — "Reservasi" arriving with TASK-038 and "Buat Pembelian" with TASK-037, never a control without its route (RC-06) —; for *Kirim langsung* net confirmed, delivered and cancelled (TASK-046); for *Jasa* handed over and cancelled (L-39; TASK-047); without `stock.view` the line's own progress only
- ROUTE-159 with INT-159, "Batalkan Sisa" (ADM+): the unfilled remainder of one or several lines cancelled in place, one `line_scope_cancellations` row per line, with MSG-40's reason (IP-09), client or supplier evidence where it exists and the reservation cut (IP-20) — the remainder's reservation released in the same action, never below net shipped, confirmed or handed-over quantity (SF-CANCEL-LINE; AX-14; PX-08; J-PRJ-04) —, the dialog saying that received goods stay as free stock and paid money returns as a supplier refund (ADMIN_FLOW §11)
- ROUTE-160 with INT-160, "Ubah Cara Pemenuhan": only a line's unstarted remainder split into a new line with the same commercial snapshot, its pins and the new mode, `split_from_line_id` naming its origin and the remainder's reservation released, with a reason and no re-approval (L-21; WF-FUL-01; AX-14)
- per-line conservation as the line guard holds it (L-04; C-05; DATABASE §9): *Gudang* reserved remaining + net dispatched + cancelled ≤ pinned demand, *Kirim langsung* net confirmed + cancelled ≤ pinned demand, *Jasa* handed over + cancelled ≤ pinned demand, each mode's other columns zero — so an order from stock needs no purchase, a service line no warehouse movement and a direct shipment no receipt (BR-XD-02; BR-XD-03)
- the line state the completion reads — WF-PRJ-03's fulfilment predicate (every line delivered or handed over = pinned demand, or its remainder cancelled), the stock predicate's active reservations and Force Complete's residual obligations (BR-PRJ-02; BR-PRJ-04; C-38; C-39) —, the lines and their current pins exposed to the higher tiers through PRJ's query classes (ARCHITECTURE §3); the predicates and the completion are TASK-055's

**OUT OF SCOPE:**

- the quotation cycle, ROUTE-157's decision and PG-014 (TASK-033); the superseding decision's route ROUTE-169 (TASK-035); project cancellation with ROUTE-163 (TASK-056); completion, Force Complete and the predicates (TASK-055)
- reservations — SF-RESERVE, SF-RSV-CUT, AX-01, AX-02, PG-025 and INV's part of `ProjectLifecycleParticipant` (TASK-038); purchases with DOC-02 (TASK-037); dispatch, delivery, direct shipment and handover (TASK-041, TASK-045, TASK-046, TASK-047); invoices (TASK-049); the opening import (TASK-057)
- the evidence upload and registration (TASK-030); the document engine, the composer and the layouts (TASK-029); the signals QS-01, QS-02 and QS-04 and the action queue (TASK-058)

**REFERENCES:**

- [WORKFLOWS §5.2](../03-workflows/WORKFLOWS/s05-01-02-access-project.md#wf-prj-02--commercial-confirmation-desk) WF-PRJ-02; [WF-FUL-01](../03-workflows/WORKFLOWS/s05-01-02-access-project.md#wf-ful-01--fulfillment-planning-per-demand-line-desk); [§6](../03-workflows/WORKFLOWS/s06-07-subflows-partial.md#6-reusable-subflows) SF-CANCEL-LINE; [§7](../03-workflows/WORKFLOWS/s06-07-subflows-partial.md#7-partial-processing-rules) PX-01–PX-08; [§8](../03-workflows/WORKFLOWS/s08-09-corrections-indivisible.md#8-correction-reversal-and-cancellation-matrix) CM-27, CM-29–CM-31; [§9](../03-workflows/WORKFLOWS/s08-09-corrections-indivisible.md#9-indivisible-business-actions) AX-13, AX-14
- [WORKFLOWS §12](../03-workflows/WORKFLOWS/s10-14-signals-decisions-traceability.md#12-planner-determined-level-1-decisions) L-01, L-02, L-04, L-10–L-12, L-19–L-21, L-23, L-26, L-39, L-49, L-50; [§10](../03-workflows/WORKFLOWS/s10-14-signals-decisions-traceability.md#10-derived-signals-and-action-queue) QS-01, QS-02, QS-14; [WF-PRJ-03](../03-workflows/WORKFLOWS/s05-07-08-completion-migration.md#wf-prj-03--project-completion-penyelesaian-desk) predicates 1 and 8 and the residual commands; [WF-MIG-01](../03-workflows/WORKFLOWS/s05-07-08-completion-migration.md#wf-mig-01--opening-facts-desk); [§5.5](../03-workflows/WORKFLOWS/s05-04-05-fulfillment-documents.md#wf-doc-01--generated-document-lifecycle-desk) DOC-09 and DOC-14 in WF-DOC-01's table
- [BUSINESS_RULES SM:Project](../02-domain/BUSINESS_RULES.md#smproject) BR-PRJ-01–BR-PRJ-06; [§6](../02-domain/BUSINESS_RULES.md#6-cross-domain-interaction-rules) BR-XD-01–BR-XD-07; [BR-DOC-04](../02-domain/BUSINESS_RULES.md#smdocument-all-doc-0114-invoice-is-a-specialization); [BR-INV-03](../02-domain/BUSINESS_RULES.md#inventory-inv); [BR-SN-01](../02-domain/BUSINESS_RULES.md#historical-immutability-and-snapshots); [BR-RSV-01](../02-domain/BUSINESS_RULES.md#smreservation); [the numeric annex](../02-domain/BUSINESS_RULES.md#numeric-annex-binding-examples-for-p8-fixtures-gap-004), example 11
- [V1_SCOPE](../01-product/V1_SCOPE.md#selectable-document-catalog) DOC-09, DOC-14; [CAP-04](../01-product/V1_SCOPE.md#must-ship); [ACCEPTANCE_CRITERIA](../01-product/ACCEPTANCE_CRITERIA.md#product-acceptance-contracts) AC-04, AC-20; [OS-01](../01-product/ACCEPTANCE_CRITERIA.md#operational-success-and-go-live-evidence); [REFERENCE_COVERAGE](../01-product/REFERENCE_COVERAGE.md#pdf-capability-inventory) REF-017, REF-018; [IMG-02](../01-product/REFERENCE_COVERAGE.md#image-and-non-tabular-pdf-inventory)
- [DATABASE §4.5](../04-architecture/DATABASE/s04-05-prj.md#45-prj--projects--commercial) `commercial_confirmations`, `confirmation_line_pins`, `line_scope_cancellations`, `project_line_balances`, `project_commercial_balances`; [§9](../04-architecture/DATABASE/s07-10-representations.md#9-project-and-commercial-representation); [§10](../04-architecture/DATABASE/s07-10-representations.md#10-document-architecture); [§4.10](../04-architecture/DATABASE/s04-10-doc.md#410-doc--documents--evidence); [§18](../04-architecture/DATABASE/s18-19-constraints-integrity.md#18-constraint-catalogue) C-05, C-31, C-38, C-39, C-51; [§24](../04-architecture/DATABASE/s20-24-index-storage-migration.md#24-migration-and-opening-data); [§25](../04-architecture/DATABASE/s25-28-transactions-handoffs.md#25-transaction-boundary-map) AX-13, AX-14
- [ARCHITECTURE §3](../04-architecture/ARCHITECTURE.md#3-modules) the tiers, `ProjectLifecycleParticipant` and `DocumentSubjectProvider`
- [PERMISSIONS_MATRIX §4](../05-security/PERMISSIONS_MATRIX.md#4-capability-catalogue) `projects.confirm`, `projects.cancel_scope`, `projects.manage`, `stock.reserve`; [§5](../05-security/PERMISSIONS_MATRIX.md#5-workflows-4-mapping) W4-11, W4-23; [§6](../05-security/PERMISSIONS_MATRIX.md#6-company-scope-rules) CS-03–CS-05; [§7.2](../05-security/PERMISSIONS_MATRIX.md#72-table-classification) rows 29–32, 35–37; [§7.3](../05-security/PERMISSIONS_MATRIX.md#73-special-projections) PJ-01, PJ-04, PJ-08, PJ-11, PJ-12, PJ-15; [§7.4](../05-security/PERMISSIONS_MATRIX.md#74-read-classes) RD-02, RD-03
- [ROUTE_CONTRACTS](../03-workflows/ROUTE_CONTRACTS.md#projects--proyek-formulir-proyek-penawaran-the-journey-and-its-completion-prj-j-prj-01j-prj-05) ROUTE-157–ROUTE-160; [INTERACTION_CONTRACTS](../03-workflows/INTERACTION_CONTRACTS.md#projects--proyek-formulir-proyek-penawaran-the-journey-and-its-completion-prj-j-prj-01j-prj-05) INT-158–INT-160; [§1](../03-workflows/INTERACTION_CONTRACTS.md#1-conventions) IG-01–IG-05
- [NAVIGATION_CONTRACTS §2.2](../07-ux-design/NAVIGATION_CONTRACTS.md#22-projects-and-quotations) NAV-011; [§1](../07-ux-design/NAVIGATION_CONTRACTS.md#1-conventions) NV-02–NV-08; [INFORMATION_ARCHITECTURE §8.2](../07-ux-design/INFORMATION_ARCHITECTURE.md#82-projects-and-quotations) PG-011
- [ADMIN_FLOW §10.2](../07-ux-design/ADMIN_FLOW/s10-a-journeys.md#102-project-and-commercial) J-PRJ-02, J-FUL-01; [§10.7](../07-ux-design/ADMIN_FLOW/s10-b-journeys.md#107-completion-cancellation-and-migration) J-PRJ-04's "Batalkan Sisa"; [§11](../07-ux-design/ADMIN_FLOW/s11-13-corrections-numbering-import.md#11-corrections-and-reversals) CM-29–CM-31; [IP-08](../07-ux-design/ADMIN_FLOW/s05-command-model.md#ip-08--confirming-an-irreversible-command), [IP-09](../07-ux-design/ADMIN_FLOW/s05-command-model.md#ip-09--corrections-closures-and-destructive-actions), [IP-19](../07-ux-design/ADMIN_FLOW/s05-command-model.md#ip-19--several-commands-on-one-screen), [IP-16](../07-ux-design/ADMIN_FLOW/s06-09-session-forms-lists.md#ip-16--evidence-upload), [IP-20](../07-ux-design/ADMIN_FLOW/s06-09-session-forms-lists.md#ip-20--choosing-a-reservation-cut); [§14](../07-ux-design/ADMIN_FLOW/s14-15-messages-scenarios.md#14-message-catalogue) MSG-39, MSG-40
- [DESIGN_SYSTEM §14.2](../07-ux-design/DESIGN_SYSTEM.md#142-inventory) PT-17
- [CONCURRENCY_IDEMPOTENCY §7.1](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s07-statement-sequences.md#71-lock-sets-of-ax-01ax-37) and [§7.2](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s07-statement-sequences.md#72-checks-effects-and-outcomes-of-ax-01ax-37) AX-13, AX-14; [§4.2](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s04-06-locks-envelope.md#42-lock-classes) LK-00, LK-01, LK-03, LK-13, LK-18–LK-20, LK-29; [§8](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s08-09-guards-stale-state.md#8-guards-and-first-use-rows) FU-09; [§9](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s08-09-guards-stale-state.md#9-stale-state) ST-01, ST-06, ST-08; [§10](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s10-13-identity-failure-retry.md#10-command-identity-and-replay) CI-01, CI-05; [§11](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s10-13-identity-failure-retry.md#11-separate-duplicates) BD-05, BD-13
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (80): O-01; CAP-04; DOC-02–DOC-04; DOC-06; DOC-09; DOC-14; AC-04; AC-16; AC-20; OS-01; REF-017–REF-018; IMG-01–IMG-04; IMG-06; OWN-01; OWN-04; BR-XD-01–BR-XD-07; BR-PRJ-01–BR-PRJ-06; BR-DOC-04; WF-PRJ-02; WF-FUL-01; SF-CANCEL-LINE; PX-01–PX-08; CM-27; CM-29–CM-31; AX-13–AX-14; L-01; L-10–L-12; L-19–L-21; L-26; L-39; L-49–L-50; ROUTE-158–ROUTE-160; INT-158–INT-160; C-38–C-39; C-51; BD-05; BD-13; M-09–M-10; J-PRJ-02; J-PRJ-04; J-INV-02; J-FUL-01

**EXPECTED MODULES / FILE AREAS:**

- the PRJ module's ConfirmWithoutQuotation, CancelRemainingScope and SplitReplanLine actions and the AX-13 confirmation step that ApproveQuotation (TASK-033) and ROUTE-169's supersession also run; PRJ's `DocumentSubjectProvider` for DOC-09; PRJ's query classes for the lines, pins and progress (RC-02); `resources/js/Pages/PRJ` for PG-011's *Item dan pemenuhan* and its dialogs; `tests/Feature/PRJ` and `tests/Concurrency/`

**BUSINESS RULES:**

- BR-PRJ-06 and BR-RSV-01: the confirmation is the reservation trigger — the quotation's approval or, without one, an explicit decision on the client's order evidence —, no separate Order entity exists, and a reservation is created only from confirmed demand
- BR-INV-03 and BR-SN-01: demand lines pin their conversion snapshot, price and tax at confirmation, quantities in base units; pins are immutable and a re-confirmation adds new ones (DATABASE §9); a master ratio change never rewrites them (numeric annex 11)
- BR-QUO-02 and L-20: a later revision's approval re-pins the changed lines and the latest APPROVED revision governs; reductions below executed quantities need returns or revisions first — and under BR-XD-01 each such correction is linked, explicit and never automatic
- BR-PRJ-05 and C-51: the channel is fixed per project and its SIPLAH details lock at the first channel-dependent commit; the confirmation writes that lock (DATABASE §25)
- BR-XD-02 and BR-XD-03 with L-39: one project may mix modes, each line's mode deciding which domains it touches; existing stock needs no dummy purchase, a service-only project no warehouse movement, a direct shipment no fictitious receipt; the service handover is the SERVICE line's delivery equivalent
- PX-01–PX-08 with L-04 and L-23: quantity events are additive; zero processed is a valid resting state and nothing happens because time passes; excess is rejected at the command until the source is amended by a revision and re-confirmation; an unfillable remainder ends only by an explicit closure with reason and authority; a committed partial effect is never undone implicitly; another authorized Admin can continue any open work; caps use net quantities, as the Owner's examples show (PX-06)
- SF-CANCEL-LINE with BR-PRJ-01, L-10 and CM-29–CM-31: a remaining-scope cancellation is ADM+, carries its reason and the evidence where it exists, releases its reservation in the same action and never goes below net shipped, confirmed or handed-over quantity; nothing is silently unwound, facts that stay true stay shown and debt stays collectable
- L-21: the re-plan splits only the unstarted remainder into a new line with the same commercial snapshot, audited, without re-approval
- L-01 and BR-XD-05: progress is derived, never a workflow status field; committed changes reach queues and reports without waiting for completion, every figure resolving to a CALC-ID and its scope filter (CALC-02; CALC-04)
- BR-PRJ-02, BR-PRJ-04, C-38 and C-39: the pins, balances and cancellations are inputs of the ten predicates and of Force Complete's snapshot and residual obligations; L-12 with PX-02: completion stays an explicit decision, so cancelling the last remainder completes nothing; L-11: after completion or cancellation ROUTE-158–ROUTE-160 are not residual commands — a section 8 correction applies, revalidating a completed project (SF-REVAL) and leaving a CANCELLED one CANCELLED, and anything else is REJECTED (WF-PRJ-03); BR-PRJ-03: no command of this Task waives a requirement
- BR-DOC-04 and BR-XD-04 with L-19 and L-26: one business record, many layouts — the SPK renders the confirmed project, as DOC-03 renders the shipment and the invoice record carries DOC-04 and DOC-06 —; a company-prepared SPK or Surat Pesanan carries its truthful issuer and draft identity and never satisfies a required client-issued original; generating a layout changes no stock, cash or obligation
- BR-XD-06 and L-50: migration's day-one effects are opening lots, receivables and customer credit — the credit an application source, never a live-period Cash-In —; an open project carries over as a MIGRATION confirmation with pins for its remaining demand (DATABASE §24)
- BR-XD-07 and L-49: a race on a line's last unit is decided by the guards at LK-13, LK-18 and LK-19 (C-05; AC-16); the likely-duplicate warning on fungible events is AX-03's and AX-09's, queried under this Task's line (BD-13)

**ARCHITECTURE RULES:**

- PRJ is tier 2 and never writes INV's reservation rows: AX-13's adjustments and AX-14's releases run through `ProjectLifecycleParticipant` inside PRJ's transaction under LK-01 (ARCHITECTURE §3; CONCURRENCY_IDEMPOTENCY §7.1 notes); the higher tiers read the lines and pins through PRJ's query classes
- one confirmation step for every path and kind, writing the confirmation, its pins, the pointers and the guards in one transaction (DATABASE §25); pins are SN, confirmations and cancellations AD and the balances GB (DATABASE §4.5) — no second confirmation path and no Order entity (BR-PRJ-06)
- progress is read from `project_line_balances` and the facts, never stored as a status (L-01; DATABASE §9); DOC-09 is issued through TASK-029's engine and template (AX-15), this Task adding its subject only

**SECURITY / PERMISSION REQUIREMENTS:**

- ROUTE-158 and the approval's AX-13 `projects.confirm` (ADM; prerequisite `projects.view`); ROUTE-159 `projects.cancel_scope` (ADM_PLUS; prerequisite `projects.view`); ROUTE-160 `projects.manage`, releasing the remainder's reservation also `stock.reserve` (prerequisites `stock.view` and `projects.view`) (PERMISSIONS_MATRIX §4; W4-11; W4-23)
- prices, sales values and tax components only with `finance.view` (rows 30, 32; PJ-12); availability only with `stock.view` (PJ-01); the order evidence with `evidence.view` and `projects.view` (PJ-08); DOC-09's payload and rendition with `finance.view` (PJ-15); each control absent without its capability (IG-01; NV-08), the server deciding either way (AZ-01)
- references resolved through the scope resolver, and server-derived fields — the kind, `decided_by`, the company, the state — never taken from the payload (DP-03; CS-04)

**COMPANY SCOPE AND FIELD PROJECTION:**

- the command's company is the project's (CS-03); every line, revision and evidence reference resolves inside it, an out-of-scope one failing exactly like a nonexistent one (CS-04; AZ-11)
- rows 29–32 and 35–37: `project_siplah_details` PV(c) with money FV(c); `project_lines` PV(c) with draft prices FV(c); `commercial_confirmations` PV(c); `confirmation_line_pins` PV(c) with prices, sales value and tax FV(c), the tax components keyed by family (PJ-12); `line_scope_cancellations` and `project_line_balances` PV(c); `project_commercial_balances` FV(c)
- availability is pooled physical data for `stock.view` (CS-05; PJ-01; RD-02), another company's reservation or lot shown only by the relation marker (PJ-04); the line's progress is project operations data (RD-03; CALC-04); DOC-09's payload in the finance family (PJ-15) and the order evidence in the project family (PJ-08), each within the project's company

**CONCURRENCY / IDEMPOTENCY:**

- AX-13: LK-00, LK-01, LK-03, LK-13, LK-18, LK-19, LK-20 and LK-29, STATIC, with first-use row FU-09; ST-01 on the revision or, without a quotation, on the project, and ST-08 for a prefilled date; one root confirmation per approved revision by its partial UNIQUE key and one per project on the no-quotation path re-read under LK-01, a further one CONFLICT unless it is CM-27's superseding confirmation (BD-05); refusals C-05 and C-31; retry class A (CONCURRENCY_IDEMPOTENCY §7.1, §7.2)
- AX-14: LK-00, LK-01, LK-03, LK-13, LK-18 and LK-19, with LK-29 at its position when evidence is linked; the unfilled remainder re-read under the lock, a quantity above it REJECTED with the current state and never CONFLICT (ST-06; PX-08); identity the line, UNIQUE (`command_id`, `project_line_id`); refusal C-05; retry class A
- every command carries `command_id` (CI-01) and a replay returns the recorded outcome after re-authorization, a changed payload being CONFLICT (CI-05; M-09; M-10); the guard statements in canonical order (GU-01); the participants run inside the caller's transaction under LK-01 (§7.1 notes); AX-03's and AX-09's likely-duplicate query runs under this Task's line guard (BD-13)

**AUDIT:**

- each confirmation, cancellation and split is one business audit event in its transaction with the individual actor, before and after, the reason where required and the records it affects (BR-XC-01; BR-CR-01; LG-01), with the highest authority class exercised — ADM_PLUS for "Batalkan Sisa", which feeds the Owner review queue QS-14 (AZ-10)
- the confirmation's `decided_by`, `business_date`, `reason` and `supersedes_confirmation_id`, the cancellation's `decided_by`, `reason` and `business_date`, and the split line's `split_from_line_id` are the decisions' own history (DATABASE §4.5); the audit's before and after projected by family (PJ-11; LG-05)

**DATABASE IMPACT ASSESSMENT:**

- Schema change: NONE — uses the tables of TASK-005–TASK-009; a missing column is an executor discovery (AGENT_OPERATING_MODEL)
- Migration required: NO
- Existing data transformation: NONE
- Direct production DB access: FORBIDDEN
- Data-loss risk: NONE KNOWN

**MODULARITY / EXTENSIBILITY:**

- Module boundary affected: PRJ, tier 2 — calling `ProjectLifecycleParticipant`, which PRJ declares (TASK-032), inside its transaction, and implementing DOC's `DocumentSubjectProvider` for DOC-09 (ARCHITECTURE §3)
- Stable core kept provider-neutral: YES
- External/vendor-specific code isolated: YES — framework facilities only (ARCHITECTURE §2)
- Replaceable adapter/contract required: NO — both contracts exist (TASK-029, TASK-032)
- Provider lock-in risk: NONE beyond the Owner's baseline stack
- Future extension impact: LOW
- Speculative abstraction avoided: YES — no Order entity (BR-PRJ-06), no automatic reservation or priority (L-02; BR-RSV-04), no stored workflow status (L-01)

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

- Context7: OPTIONAL — PostgreSQL CHECK constraints per mode and partial UNIQUE indexes, at the installed version
- Graphify: OPTIONAL — once INV, PUR and FUL cite the line
- Browser E2E: REQUIRED — J-PRJ-02's no-quotation path and J-FUL-01 with "Batalkan Sisa" and "Ubah Cara Pemenuhan"
- Other: a mixed-mode project fixture — *Gudang*, *Kirim langsung* and *Jasa* lines — with the numeric annex's ratio change (example 11); a test participant of `ProjectLifecycleParticipant` until INV's exists (TASK-038)
- Missing safe tool behavior: AUTO-INSTALL project-locally inside this Task (AICWDF §6.3) and record it in TOOLCHAIN; a paid tier or a privileged installation STOPS for the Owner (COST_POLICY)

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: refreshed before the change when Graphify is available ([TOOLCHAIN](../00-governance/TOOLCHAIN.md)); otherwise a targeted search of PRJ and of the participant contract's callers
- Pre-change questions: which reads use the current pin, and where AX-13 and AX-14 hand over to the participants
- Expected affected areas: PRJ's confirmation and line actions and its query classes; DOC's provider for DOC-09

**CONTEXT7:**

- Topics to verify: CHECK constraints per fulfilment mode; the partial UNIQUE index of the root confirmation; row locks taken in the canonical order
- Version-sensitive assumptions: none from memory

**UI / UX REQUIREMENTS:**

- Follow global design rules: YES
- Indonesian default: YES
- English support: YES — for the pages; DOC-09's paper stays Indonesian (LOCALIZATION §3.4)
- Plain-language Indonesian: YES — MSG-39 for "Konfirmasi Proyek", MSG-40 for the reasons, the re-confirmation's refusal naming the return or invoice revision to do first (J-PRJ-02)
- Language switch impact: keeps unsent input; the paper unchanged
- Mobile-first: YES — J-PRJ-02 and J-FUL-01 are `[desk]` on the `[both]` PG-011; the phone shows the per-line progress (J-FUL-01; NAV-011)
- Responsive: YES
- Adaptive: YES — the lines as a table on the desktop (PT-05) and dense rows on the phone (PT-06); the dialogs as full-height sheets on the phone (IP-08; PT-16)
- Visual breathing room: REQUIRED
- Comfortable density: REQUIRED
- shadcn/ui reuse: the confirmation with its reason (PT-26), dialogs and sheets (PT-16), the evidence upload (IP-16; TASK-030), the quantity block for availability (PT-17; DESIGN_SYSTEM §14.2), status badges (PT-13), inline messages (PT-14), the business date control (PT-25)
- Design reference: PG-011 and its *Item dan pemenuhan* part as INFORMATION_ARCHITECTURE §8.2 and J-FUL-01 state them; the Golden Frontend Reference for presentation only (DIR-061 SD-02)
- Primary action: "Konfirmasi Proyek" on a project to be confirmed without a quotation
- Secondary actions: on a line "Ubah Cara Pemenuhan"; after the confirmation its next steps — the reservation proposal (TASK-038) and the company SPK (DOC-09)
- Destructive actions: "Batalkan Sisa", its reason required and its confirming button destructive (IP-09; PT-11)
- Redundancy constraints: one confirmation control per project; no combined "Tersimpan" for the confirmation and its next steps (IP-19); nothing implying that the confirmation reserved stock or recorded money
- Loading state: PT-19
- Empty state: a project without confirmed lines shows its draft lines and the way to confirm — the quotation or "Konfirmasi Proyek"
- Error state: AX-13's and AX-14's refusals in business words — C-05, C-31, the re-confirmation's path —; BD-05's CONFLICT; ST-01's CONFLICT with the current state; MSG-12 at the fields (IP-14)

**ROUTE IMPACT:**

- ROUTE-158 (the no-quotation confirmation), ROUTE-159 (remaining-scope cancellation), ROUTE-160 (the re-plan split); AX-13 behind ROUTE-157's approval (TASK-033) and ROUTE-169's supersession (TASK-035); DOC-09 through ROUTE-221 and ROUTE-222 (TASK-029); PG-011's view ROUTE-011 (TASK-032) carrying the part's data

**INTERACTION CONTRACTS:**

- INT-158–INT-160; INT-157's approval completing through AX-13 (TASK-033)

**NAVIGATION CONTRACT:**

- Parent: PG-011 — PG-010 (NAV-011); the dialogs open over PG-011
- Entry: PG-011's "Konfirmasi Proyek" for a project without a quotation; the *Item dan pemenuhan* part's line actions; the journey's *Kesepakatan* stage
- Back action: a dialog's "Kembali" → the form, the input kept (NV-02; INT-158–INT-160); PG-011's "Kembali" → PG-010 (NAV-011)
- Cancel: "Batal" discards only unsent input, MSG-35 first (NV-02)
- Success destination: in place on PG-011 — the confirmation with its pinned lines and its next steps as separate commands (IP-19; NV-03); the cancelled remainder and the split line in place (ROUTE-159; ROUTE-160)
- Error behavior: NV-04 — validation at the field, a refusal in place with the input kept, a retry under the same `command_id`
- State preservation: the open section in the address (NAV-011; NV-05); unsent input kept across re-login
- Mobile behavior: `[desk]` journeys on a `[both]` page — the phone reads the per-line progress (J-FUL-01; NV-06)

**ACCEPTANCE CRITERIA:**

1. "Konfirmasi Proyek" on a project without a quotation, with the client's SPK, Surat Pesanan or SIPLAH order registered as evidence, commits one NO_QUOTATION confirmation, one pin per demand line and the evidence's link in one transaction; without that evidence it is REJECTED and nothing is committed (WF-PRJ-02; BR-PRJ-06; AX-13).
2. Recording *Disetujui* on the latest sent revision within its validity commits its QUOTATION_APPROVAL confirmation, the revision APPROVED and the pins in one transaction; a rejected, expired or earlier revision yields no confirmation, and a second root confirmation of the same revision, or of a no-quotation project, is CONFLICT (BD-05; IMG-02; REF-018).
3. A demand of 2 boxes confirmed at a ratio of 12 is pinned at 24 base units with its price and tax components, and after the master ratio becomes 10 the pin is unchanged and the line still compares in 24 base units (BR-INV-03; BR-SN-01; numeric annex 11).
4. Approving a later revision adds pins for the changed lines and moves their current pins while the earlier pins stay; a reduction below what is dispatched, delivered or invoiced is REJECTED naming the return or invoice revision to do first, and a confirmed sales value below the invoiced one is REJECTED (L-20; BR-QUO-02; BR-XD-01; C-31).
5. After the first confirmation of a SIPLAH project, its channel and SIPLAH values can no longer be changed (C-51; BR-PRJ-05).
6. The confirmation's answer shows the pinned lines, and the reservation proposal and the company SPK follow as separate commands with their own answers; an account without `stock.reserve` confirms and its warehouse lines stay unreserved for a colleague (L-02; IP-19; J-PRJ-02; PX-05).
7. In one project with *Gudang*, *Kirim langsung* and *Jasa* lines each line's balance takes only its mode's quantities, the other columns staying zero, and an existing-stock *Gudang* line is confirmed and planned without any purchase (BR-XD-02; BR-XD-03; AC-20).
8. "Batalkan Sisa" on two lines with a reason records one cancellation per line and releases each remainder's reservation in the same transaction, and a quantity above a line's unfilled remainder, net of restorations and returns, is REJECTED with the current remainder (SF-CANCEL-LINE; AX-14; PX-08; ST-06).
9. Without `projects.cancel_scope` "Batalkan Sisa" is absent and its route refused, and its audit event records the ADM_PLUS authority class (AZ-10; IG-01).
10. "Ubah Cara Pemenuhan" on a partly dispatched *Gudang* line creates a *Kirim langsung* line for the unstarted remainder only, with the same price and tax and a link to its origin, and asks no re-approval; without `stock.reserve` a split that would release a reservation is refused (L-21; WF-FUL-01).
11. Cancelling the last open remainder leaves the project ACTIVE — no completion follows by itself — and the quantities already executed on the line stay as recorded (L-12; PX-02; L-10).
12. A line's progress — reserved, net dispatched, net confirmed, handed over, delivered, cancelled and remaining — is derived each time it is shown and no status is stored; without `stock.view` only the line's own progress shows (L-01; BR-XD-05; J-FUL-01).
13. A confirmation sent from a page whose revision or project changed meanwhile is CONFLICT with the current state, and one whose prefilled date went stale is CONFLICT with today's date (ST-01; ST-08; PX-07).
14. Without `finance.view` no price, sales value or tax component of a pin reaches the part, its props or its dialogs (PJ-12; SM-25).
15. A company SPK drafted from the confirmed project carries the company as issuer and the draft marking until issued, never presents itself as the client's SPK, and issuing it changes no stock, cash or obligation; a client-issued Surat Pesanan is accepted only as uploaded evidence, never generated (DOC-09; DOC-14; BR-DOC-04; BR-XD-04).
16. A MIGRATION confirmation written for an opening project pins its remaining demand and creates no reservation, stock or money effect (DATABASE §24; BR-XD-06).
17. Each of ROUTE-158–ROUTE-160 replayed with one `command_id` returns the recorded outcome, and with a changed payload is CONFLICT (CI-05; M-09).
18. Each confirmed line exposes its current pin — base and entered quantity, price and tax — through PRJ's query classes as the demand the later steps cite, so no later form retypes it (CAP-04; AC-04; OS-01; REF-017).

**TEST REQUIREMENTS:**

- Lint: TL-01
- Typecheck: TL-02
- Unit: the pin's base quantity with the numeric annex's ratio change; the conservation of each mode with net quantities (PX-08) (TL-03)
- Feature: ROUTE-158–ROUTE-160 with each refusal — C-05, C-31, L-20's path —; ROUTE-157's approval through AX-13; the superseding and MIGRATION kinds through the step (TL-04)
- Integration: BD-05 — concurrent confirmations of one revision and of one no-quotation project —; a cancellation racing a re-confirmation of the same line under LK-01 and LK-19; the guard CHECKs by mode (C-05) and the invoice cap (C-31); the participant inside the transaction, its failure rolling the command back; BD-13's query anchored at LK-19 (TL-05)
- Authorization: `projects.confirm`, `projects.cancel_scope` (ADM_PLUS), `projects.manage` with and without `stock.reserve`, and the `finance.view` and `stock.view` families (TL-06)
- Route integrity: ROUTE-158–ROUTE-160 audited, with ROUTE-011's changed props (TL-07)
- Interaction: INT-158–INT-160, each with one `command_id` replayed (TL-08; IG-05)
- Browser E2E: J-PRJ-02's no-quotation path and J-FUL-01 — CJ-01's approval-to-confirmation step on both branches and its project served from stock with no purchase, CJ-13's commercial confirmation —; the phone's per-line progress (TL-09)
- Localization/language switch: both languages on PG-011's part and its dialogs; DOC-09's paper Indonesian (TL-10)
- Visual breathing room/density: TEST_STRATEGY §9 for PG-011's part
- Responsive: TL-11 at DESIGN_SYSTEM §6's band edges
- Accessibility: TL-12, each dialog opening on its first input or on "Kembali" (IP-08)
- Production build: TL-13
- Security: SM-01 for foreign line, revision and evidence references; SM-04 for W4-11 and W4-23; SM-25 for the pins' values and DOC-09's payload (TL-14)
- Performance: PFM-01 and PFM-02 for PG-011's part; PFM-14 for the project row; PFM-20 for a ten-line confirmation (TL-15)
- Migration: N/A — no migration or import code in this Task; the MIGRATION kind is tested at TL-04 and the import that writes it is TASK-057's (TL-16)
- Other: the line state WF-PRJ-03's predicates 1 and 8 read, asserted for TASK-055 (C-38; C-39); DOC-09's layout compared with its preview (DOCUMENT_PREVIEW_PRODUCER §4) and its sample listed for the Owner's content validation (GAP-019)

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

- TASK-037 — when its other dependencies are DONE and its READY gate holds

**DO NOT DO:**

- add to V1 — a capability, page, route, field, integration, document type, report or paid service — or drop, defer or narrow a V1_REQUIRED requirement; a Task that would need either raises an Owner question and waits ([DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-20)
- access the VPS, production, its data or a credential, or put a secret, real data, a host detail or production configuration in the repository ([INFRASTRUCTURE](../09-operations/INFRASTRUCTURE.md) INF-01, INF-05; SECURITY SX-01, SX-04, SX-06; [PRODUCTION_DATA_SAFETY](../00-governance/PRODUCTION_DATA_SAFETY.md))
- add AI, model or tool attribution to a commit — the Owner's configured identity only ([AGENTS.md](../../AGENTS.md#git-commit-attribution); DIR-022)
- start before the Owner's execution authorization at OG-4, or mark DONE without the required tests passing and the completion evidence ([TASK_PLAN](TASK_PLAN.md#2-rules-of-the-plan); [DEFINITION_OF_DONE](../08-testing/DEFINITION_OF_DONE.md#2-the-checklist))
- weaken, skip or delete a valid test to obtain a green run, or adopt a paid tier without the Owner's COST_POLICY decision (DOD-05; [COST_POLICY](../00-governance/COST_POLICY.md))
- reserve stock automatically or by a priority, or join the confirmation and its reservations in one command (L-02; BR-RSV-04; IP-19)
- write INV's reservation rows from PRJ, or add a confirmation path or an Order entity beside AX-13 (ARCHITECTURE §3; BR-PRJ-06)
- change the mode of a started quantity, cancel below net shipped, confirmed or handed-over quantity, or lower demand below executed quantities (L-21; PX-08; L-20)
- let a company SPK or Surat Pesanan stand for a client-issued original, or generate an external document (BR-DOC-04)
- store a line's workflow status, or complete a project when its last remainder is cancelled (L-01; L-12; PX-02)

# Query-budget checks

Status: DRAFT | Updated: 2026-10-14 | Owner: Planning

**Draft — not normative.** This is an S2-1 document of Stage 2 of the P7 UX re-baseline, written under [DIR-061](../../00-governance/DECISION_LOG.md#dir-061-dir-062-obs-023-and-tech-031--p7-ux-re-baseline-stage-2-decisions-s2-0-authorization-baseline-and-prototype-setup) SD-01, SD-05 and SD-10 and [DIR-064](../../00-governance/DECISION_LOG.md#dir-063-dir-064-obs-024-and-tech-032--p7-ux-re-baseline-s2-0-acceptance-s2-1-authorization-baseline-and-stage-2-documents) §12.8. Behaviour follows MULTIPLECORP — P0–P6 and ADMIN_FLOW —, structure follows R1, and presentation follows the Golden Frontend Reference v1.0.0, which adapts to MULTIPLECORP and never the reverse (AK-1; SD-02). [INFORMATION_ARCHITECTURE](../INFORMATION_ARCHITECTURE.md) and [DESIGN_SYSTEM](../DESIGN_SYSTEM.md) stay APPROVED under APPR-008 and under replacement, and [ADMIN_FLOW](../ADMIN_FLOW/README.md) stays in force, until a later authorized stage changes them; Stage 3 uses this draft, and nothing in it is normative.

**Sources.** [PERFORMANCE](../../06-api-performance/PERFORMANCE.md) §§3, 4, 6, 7 and 12 — PF-01–PF-05, PF-15, PF-24, PF-34, PF-41, PF-42, QB-01, QB-02, QB-10, the signal register, IX-11 and IX-14; [DATABASE §20](../../04-architecture/DATABASE/s20-24-index-storage-migration.md#20-index-and-query-design) with the guards of [§4.5](../../04-architecture/DATABASE/s04-05-prj.md#45-prj--projects--commercial) and [§4.12](../../04-architecture/DATABASE/s04-12-fin.md#412-fin--finance); [WORKFLOWS §10](../../03-workflows/WORKFLOWS/s10-14-signals-decisions-traceability.md#10-derived-signals-and-action-queue) (QS-01–QS-22) and the ten predicates of [WF-PRJ-03](../../03-workflows/WORKFLOWS/s05-07-08-completion-migration.md#wf-prj-03--project-completion-penyelesaian-desk); [BUSINESS_RULES](../../02-domain/BUSINESS_RULES.md#5-calculation-semantics) CALC-02 and CALC-07, BR-FIN-15 and BR-DT-06; INFORMATION_ARCHITECTURE [§8](../INFORMATION_ARCHITECTURE.md#8-screen-inventory) (SCR-09, SCR-10, SCR-22) and [§9](../INFORMATION_ARCHITECTURE.md#9-dashboards-and-queues) as behaviour sources; the gate record's R1 sections — the [project journey model](../evidence/P7_REBASELINE_GATE.md#c1-rework--project-journey-model) and the [revised direction](../evidence/P7_REBASELINE_GATE.md#c1-rework--revised-direction), parts b) and d). No technology behaviour is stated here: the checks rest on the canonical budgets, and every plan stays P8's measurement (HO-16).

## 1. Method

R1 left three checks to Stage 2 ([DIR-061](../../00-governance/DECISION_LOG.md#dir-061-dir-062-obs-023-and-tech-031--p7-ux-re-baseline-stage-2-decisions-s2-0-authorization-baseline-and-prototype-setup) SD-01; BP-6): QB-01 for the project list, PF-24 for the dispatch-ready rows of *Pekerjaan*, and QB-02 for an overdue receivable in the journey's *Hambatan*. Each check asks whether an R1 element can be shown within its canonical budget from what that budget already reads — root and guard columns, and the statements PERFORMANCE registers —, adding no query and no registered statement.

- **The budgets count data queries beyond the envelope**, constant in the rows shown (PF-01), with the deferred groups each budget names ([PERFORMANCE §6](../../06-api-performance/PERFORMANCE.md#6-query-budgets)); changing a budget is a reviewed change to PERFORMANCE (PF-09).
- **A list row** reads aggregate-root columns and guard totals and never issues a per-row child query (PF-05); counts, sums and existence come from guard columns or from aggregate sub-selects in the same query (PF-04).
- **A signal** is one bounded statement registered in PERFORMANCE's signal register; the action queue's groups hold the register's statements and nothing else (PF-24; QB-10); no queue is stored ([WORKFLOWS §10](../../03-workflows/WORKFLOWS/s10-14-signals-decisions-traceability.md#10-derived-signals-and-action-queue)).
- **The verdict** is FITS or DOES NOT FIT. Where a design does not fit, the IA and the journey specifications use a design that fits the current budgets, named below, and the richer design becomes an Owner question and a gap with status OWNER_DECISION_REQUIRED for P6 change control, never enacted (DIR-064 §12.8; AK-2).

## 2. QB-01 — stage, blocker marker and next action on the project list

### 2.1 The question

R1 draws the project list as a list of journeys: each row shows the company code, the project number and title, the client, the current stage as a badge, a blocker marker — a warning icon with its words, never colour alone —, the next action and the deadline with days left; a stage filter offers the journey's stages ([R1 d)](../evidence/P7_REBASELINE_GATE.md#d-project-list-as-a-list-of-journeys)). Can the list query and its filter lookups derive the stage, the blocker marker and the next action within [QB-01](../../06-api-performance/PERFORMANCE.md#6-query-budgets), from root and guard columns and the registered signals, adding no query?

### 2.2 The budget

| Rule | What it allows |
| --- | --- |
| QB-01 | Projects list: at most 4 data queries in the first response, the list query holding one branch per company; one deferred group, the filter lookups, of at most 2; 300 ms |
| PF-41 | One bounded branch per company in scope, merged by (sort key, `id`) in the same statement; cost = branches × page size |
| PF-42 | A sort key or filter is offered only with a registered index (`company_id`, key, `id`) or a stated bound; the project list's registered filters are its state and its client, and its sort keys newest first and the deadline of open projects, NULL last (IX-11) |
| PF-05, PF-04 | Root columns and guard totals per row; existence and sums from guards or aggregate sub-selects in the same query |
| PF-34 | At most three deferred groups on a page — QB-01 names one |

INFORMATION_ARCHITECTURE's project list (SCR-09) applies this budget: QB-01 with one deferred group, the filter lookups; filters state, client and company; values with `finance.view` ([§8](../INFORMATION_ARCHITECTURE.md#8-screen-inventory)).

### 2.3 Derivation, element by element

The journey model derives every stage state each time and stores none (L-01; [BR-XD-05](../../02-domain/BUSINESS_RULES.md#6-cross-domain-interaction-rules)). Its inputs ([project journey model](../evidence/P7_REBASELINE_GATE.md#c1-rework--project-journey-model), a) and c)) set against what a QB-01 row can read:

| R1 element | Inputs the journey model names | Within QB-01? |
| --- | --- | --- |
| Company code, number, title, client | the root `projects` and its client ([DATABASE §4.5](../../04-architecture/DATABASE/s04-05-prj.md#45-prj--projects--commercial)) | **Fits** — root columns (PF-05) |
| Deadline with days left | the root's deadline; days counted on WIB day boundaries (BR-DT-06) | **Fits** — root column, sorted NULL last (PF-42; IX-11) |
| Project state | the root's `state` — DRAFT, ACTIVE, CANCELLED, COMPLETED_NORMAL, COMPLETED_FORCED | **Fits** — root column, a registered filter (IX-11) |
| Current stage | the first applicable stage, in track order, that is not *Tuntas*: *Kesepakatan* from the root; *Gudang*, *Kirim langsung* and *Jasa* from `project_line_balances`; *Pengiriman* from predicate 2 — every shipment delivered or formally closed, no open discrepancy case; *Dokumen* from predicates 4 and 5 and the documents group; *Invoice* from `project_commercial_balances`; *Penagihan* from predicate 6; *Pembayaran* from predicates 7 and 9 and [QS-19](../../03-workflows/WORKFLOWS/s10-14-signals-decisions-traceability.md#10-derived-signals-and-action-queue); *Penyelesaian* from all ten predicates | **Does not fit** — from *Pengiriman* on, the inputs are the completion predicates and the documents group, which QB-02 reads as deferred groups of up to ten statements each on the project's own page; they are neither root nor guard columns, and QB-01 has no such group |
| Blocker marker | a shortage (QS-02) or a cut reservation (QS-04) on *Gudang*; an open discrepancy or correction case (predicates 2 and 3); a failed rendition (the documents group); an overdue receivable or a dispute hold (predicate 7); an unlinked pre-payment Kuitansi (QS-19) | **Does not fit** — only the shortage reads guards alone (`project_line_balances` against `stock_product_balances`, as QS-02 does); the others read reservation events, cases, renditions, dispute holds and document chains |
| Next action | the current stage's first open item, oldest record first, with its record named ("Terima barang ARJ/PB/2026/0216") — the precedence of the journey model's part c) | **Does not fit** — it needs the current stage and the open items behind QS-01–QS-06, the dispatch worklist of SCR-22, QS-09, QS-15 and the predicates |
| Stage filter | the journey's stages | **Does not fit** — a derived stage is stored nowhere, so no index (`company_id`, stage, `id`) can exist and PF-42 does not offer the filter |

Even the stages guards alone determine cannot give the current stage alone: the track puts *Pengiriman* before *Jasa* and *Dokumen* before *Invoice*, so once *Gudang* and *Kirim langsung* are *Tuntas* — or do not apply — the current stage depends on predicate 2 or on predicates 4 and 5. Reading them for every row of a page would need the ten predicate statements and the documents group over the page's project ids — the set-query form QS-15 already registers for a page of projects (PERFORMANCE's signal register) — in a second deferred group on the list: more statements than QB-01's four, and a group its budget does not name.

### 2.4 Verdict

**DOES NOT FIT.** The company code, number, title, client, state and deadline fit QB-01; the current stage, the blocker marker, the next action and the stage filter do not, without a change to QB-01 — a P6 change (PF-09).

### 2.5 The design that fits, used by the S2-1 documents

The project list is INFORMATION_ARCHITECTURE's SCR-09 list within QB-01: each row shows the company code, the project number and title, the client, the project state and the deadline with days left, and, for an account holding `finance.view`, the values SCR-09 shows; its filters are the state, the client and the company, its sort newest first or the deadline of open projects, NULL last (PF-42; IX-11). The journey's stage, its blockers and its next action appear one step away, on the project journey workspace (SCR-10, within QB-02), and the work that waits on a project appears in *Pekerjaan* and on the Owner's *Beranda* through the registered signals (QB-10), each row with its project and its stage taken from its signal (R1 b)). IA_PAGE_INVENTORY and the journey specifications use this design; nothing of R1's journey is lost, it is shown where its budget allows.

### 2.6 The richer design — an Owner question and a gap

R1's per-row stage, blocker marker, next action and stage filter need P6 change control — for example a second deferred group on the list that runs the ten predicate set queries and the documents readiness over the page's project ids, within PF-34's three groups, measured by P8 at 10x and 100x (HO-16). It is put to the Owner as [OQ-02](OWNER_QUESTIONS.md#oq-02--the-project-lists-journey-summary-per-row) and registered as [GAP-054](../../00-governance/GAP_REGISTER.md#gap-054--the-project-list-cannot-show-each-projects-journey-stage-blocker-and-next-action-within-its-query-budget), OWNER_DECISION_REQUIRED; nothing is enacted.

## 3. PF-24 — dispatch-ready rows in *Pekerjaan*

### 3.1 The question

R1's Admin work home groups goods work as *Barang: terima, keluarkan, kirim*, with the sub-groups *Terima barang*, *Keluarkan dan kirim* and *Siapkan barang*, and composes it from QS signal rows only — [QS-05](../../03-workflows/WORKFLOWS/s10-14-signals-decisions-traceability.md#10-derived-signals-and-action-queue), QS-06 and QS-02 —, inside [QB-10](../../06-api-performance/PERFORMANCE.md#6-query-budgets)'s four signal groups of at most twelve statements ([R1 b)](../evidence/P7_REBASELINE_GATE.md#b-admin-work-home-oc-01-oc-05-pl-4)). Its dispatch-ready rows — the SCR-22 worklist of remaining reservations, [CALC-02](../../02-domain/BUSINESS_RULES.md#5-calculation-semantics) — are no QS signal. Can they appear there within QB-10 and PF-24 without a new registered statement?

### 3.2 Derivation

| Element | Source | Within QB-10 and PF-24? |
| --- | --- | --- |
| A dispatch-ready line | a WAREHOUSE line of an ACTIVE project with a remaining reservation — reservation quantity less what dispatch consumed and what was released or adjusted (CALC-02) —, which `project_line_balances.reserved_qty_base` holds as the reserved remainder (L-04) | the source is a guard, but no registered statement returns these lines |
| QS-01 | lines whose guard shows WAREHOUSE demand **not yet reserved** | a fully reserved line is outside its result |
| QS-03 | received goods for a project **awaiting** reservation | before the reservation, not after it |
| QS-04 | reservations **cut** | a cut, not a ready dispatch |
| QS-06 | a dispatched or confirmed quantity **not yet delivered** | after the dispatch |
| QS-16 | residual obligations of force-completed or cancelled projects, release-required reservations among them | not ACTIVE projects' dispatch work |
| The group's count | the Admin's G1 *Proyek & Gudang* holds QS-01–QS-06, QS-16, QS-17, QS-18 (open findings, stale counts) and QS-20 — eleven statements ([INFORMATION_ARCHITECTURE §9](../INFORMATION_ARCHITECTURE.md#9-dashboards-and-queues)) | one more statement would make twelve, inside QB-10's bound — but that statement is not in the register |

### 3.3 Verdict

**DOES NOT FIT.** The rows would need a new registered statement: PF-24 admits to the action queue only the register's statements, each a QS signal of WORKFLOWS §10, and none of the twenty-two returns a line that is reserved and not yet dispatched. The budget itself would hold one more statement; the register would not.

### 3.4 The design that fits, used by the S2-1 documents

In *Pekerjaan*, *Keluarkan dan kirim* shows QS-06's rows — dispatched or confirmed and not yet delivered — and leads, with one link and no count (D-IA-06), to the dispatch worklist of *Barang Keluar* (SCR-22), where the remaining reservations are listed for `stock.dispatch` and the dispatch is recorded (AX-03). The project journey's *Gudang* stage keeps "dispatch (the SCR-22 worklist)" as its next action (the journey model's part c)), reached from the journey within QB-02. No statement is added to QB-10.

### 3.5 The richer design — an Owner question and a gap

Dispatch-ready rows listed in *Pekerjaan* itself would need a new signal — a statement over the remaining reservations of ACTIVE projects (CALC-02), starting, like QS-01, from `projects (company_id, state)` (IX-14) and reading `project_line_balances` by key, with one branch per company in scope (PF-24; PF-41), registered in WORKFLOWS §10 and PERFORMANCE's signal register and placed in the Admin's G1, which would then hold twelve statements, within QB-10 — through P3 and P6 change control. It is put to the Owner as [OQ-03](OWNER_QUESTIONS.md#oq-03--dispatch-ready-rows-in-pekerjaan) and registered as [GAP-055](../../00-governance/GAP_REGISTER.md#gap-055--dispatch-ready-work-has-no-registered-signal-for-the-admins-work-home), OWNER_DECISION_REQUIRED; nothing is enacted.

## 4. QB-02 — an overdue receivable in the journey's *Hambatan*

### 4.1 The question

R1 lists an overdue receivable among the *Pembayaran* blockers and shows it under *Hambatan* — scenario C, "Piutang BTN/INV/2026/0094 lewat jatuh tempo 4 hari" —, assuming it is read from predicate 7's own statement in the predicates group, the way QS-10 reads `invoice_balances`, adding no query; QS-10 is not one of SCR-10's signals ([R1 f)](../evidence/P7_REBASELINE_GATE.md#f-fit-with-qb-02-and-what-p0p6-do-not-define)). Is it?

### 4.2 Derivation

| Element | Source | Within [QB-02](../../06-api-performance/PERFORMANCE.md#6-query-budgets)? |
| --- | --- | --- |
| The budget | QB-02: at most 10 data queries in the first response; three deferred groups — the timeline, the completion predicates and the documents —, at most 10 statements each; 400 ms, 500 ms per group | SCR-10 uses exactly these three groups, its other parts optional props ([INFORMATION_ARCHITECTURE §8](../INFORMATION_ARCHITECTURE.md#8-screen-inventory)) |
| Predicate 7 | each invoice's outstanding is 0 through applications and fee or tax settlements, or Owner-disposed; no dispute hold ([WF-PRJ-03](../../03-workflows/WORKFLOWS/s05-07-08-completion-migration.md#wf-prj-03--project-completion-penyelesaian-desk)) | one statement of the predicates group, over the project's invoices (`invoices (project_id)`, DATABASE §20) |
| The rows it reads | `invoice_balances` — `outstanding` and `current_due_date`, the due date copied from the current billing act for aging queries — and the uncleared `receivable_dispute_holds` of those invoices ([DATABASE §4.12](../../04-architecture/DATABASE/s04-12-fin.md#412-fin--finance)) | the same rows QS-10 and QS-11 read on the dashboard, by the guard's primary key and the dispute holds' partial UNIQUE key of C-42 |
| Overdue | an outstanding receivable past its current due date, days counted on WIB day boundaries ([BR-FIN-15](../../02-domain/BUSINESS_RULES.md#finance-fin); BR-DT-06; CALC-07) | a comparison on a column the statement already reads |
| The invoice's number | the formatted number of the invoice's current version | a column of a row the statement joins, not a statement |

### 4.3 Verdict

**FITS.** The overdue receivable — and the dispute hold beside it — is derived from predicate 7's own rows when the predicates group arrives: the statement selects `current_due_date` and the invoice's current number with the rows it already reads, so the group stays at ten statements and QB-02 is unchanged. The *Hambatan* area fills when that group arrives, its skeleton shown until then ([PT-19](../DESIGN_SYSTEM.md#94-actions-overlays-and-feedback)). Naming the columns of each statement is the query class's own declaration (PF-03), so no budget changes; P8 registers the statement and verifies its plan and its query count (PF-09; HO-16).

## 5. Summary

| Check | Verdict | Design the S2-1 documents use | Owner question | Gap |
| --- | --- | --- | --- | --- |
| [QB-01](../../06-api-performance/PERFORMANCE.md#6-query-budgets) — the project list's stage, blocker marker, next action and stage filter | DOES NOT FIT | [SCR-09](../INFORMATION_ARCHITECTURE.md#82-projects-and-quotations)'s list within QB-01; the journey's stage, blockers and next action on the journey workspace (QB-02) and the waiting work in *Pekerjaan* and *Beranda* (QB-10) | [OQ-02](OWNER_QUESTIONS.md#oq-02--the-project-lists-journey-summary-per-row) | [GAP-054](../../00-governance/GAP_REGISTER.md#gap-054--the-project-list-cannot-show-each-projects-journey-stage-blocker-and-next-action-within-its-query-budget) |
| PF-24 — dispatch-ready rows in *Pekerjaan* | DOES NOT FIT | [QS-06](../../03-workflows/WORKFLOWS/s10-14-signals-decisions-traceability.md#10-derived-signals-and-action-queue)'s rows in *Keluarkan dan kirim* and one link to the dispatch worklist of *Barang Keluar* (SCR-22) | [OQ-03](OWNER_QUESTIONS.md#oq-03--dispatch-ready-rows-in-pekerjaan) | [GAP-055](../../00-governance/GAP_REGISTER.md#gap-055--dispatch-ready-work-has-no-registered-signal-for-the-admins-work-home) |
| QB-02 — an overdue receivable in *Hambatan* | FITS | predicate 7's statement with its due dates, as R1 assumed | — | — |

The two gaps are registered in GAP_REGISTER with S2-1's other records ([DIR-064](../../00-governance/DECISION_LOG.md#dir-063-dir-064-obs-024-and-tech-032--p7-ux-re-baseline-s2-0-acceptance-s2-1-authorization-baseline-and-stage-2-documents) §§9 and 14.4); until then their links are pending in the [progress ledger](README.md#s2-1-documents). No query, statement, signal or budget is changed by this document.

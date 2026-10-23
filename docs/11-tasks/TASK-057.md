# TASK-057 — Opening import — preparation, dry run, sign-off and commit

Status: APPROVED | Updated: 2026-10-23 | Owner: Planning

Approval: [APPR-016](../00-governance/DECISION_LOG.md#appr-016--p11-task-plan-tasks-and-feature-coverage-matrix-approved), explicit Owner approval on 2026-10-20 (DIR-085 OF-07) of this document as committed in the first execution session's approval commit; the approved file hash and the verified conditions are recorded there. Approval changes lifecycle only, not implementation authorization.

Amendment: on 2026-10-23 the Owner's decision [DIR-089](../00-governance/DECISION_LOG.md#dir-089-and-appr-018--task-006s-discoveries-and-the-files-company-and-purpose) (5) adds criterion 15 and its authorization test — the batch's source file its preparer's own upload; no other line changes. It is a Level 1 record under CHANGE_CONTROL, as the Owner directs.

Written by WF-PC5-W's writer of bundle W3 of run PC-5 on 2026-10-19 and 2026-10-20 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-99; the Owner approved P11 with the [Task plan](TASK_PLAN.md) at OG-4 (DIR-085 OF-07). **No Task starts before the freeze record, from which the execution authorization is in force (DIR-085 OF-09).** References are by identifier and link; a rule is never copied beyond the sentence this Task needs, and where a line and its source differ, the source wins.

**STATUS:** BLOCKED — waiting on P11Q-02

**RISK CLASS:** HIGH

**EXECUTOR:** AGENT

**OBJECTIVE:**

Build the opening import of WF-MIG-01 on PG-058 *Impor Data Awal*: an Admin prepares a batch from a private CSV or XLSX source with an import identity per row, the dry run validates it on an isolated copy and reconciles it to the signed control totals per company, the Admin and the Owner sign it off jointly, and the Owner commits it — row by row through each module's ordinary action with deterministic command identities, in resumable chunks — writing the opening facts of DATABASE §24 and the numbering seeds; a wrong opening fact is corrected on its record by "Koreksi Data Awal", never by importing again (ROUTE-058, ROUTE-272–ROUTE-275, ROUTE-213, ROUTE-214; AX-28; J-MIG-01).

**WHY THIS EXISTS:**

CAP-15 and AC-17 require day one to start from reconciled current masters, opening stock, open projects, purchases and receivables and agreed useful documents, with repeatable, resumable preparation that never replays stock or debt and keeps old history as an identified archive (REF-051; BR-XD-06). Legacy data exists in unknown condition and is never assumed clean (DEP-05; brief §45), and GAP-010 names the failure: stock or debt counted twice when history is replayed beside an opening balance or a batch runs again. The numbering seeds and an imported purchase's number come from this import (GAP-058; DIR-079 CB-01), and the cutover tooling and go-live wait for it (TASK-077; TASK-087).

**DEPENDS ON:**

- TASK-022 — Jobs, files and the document renderer
- TASK-026 — Clients and suppliers
- TASK-027 — Catalog — products, services, units, barcodes, prices and images
- TASK-028 — Rack locations and minimum stock
- TASK-030 — Evidence and uploaded documents
- TASK-032 — Projects — list, form, journey page and amendment
- TASK-034 — Commercial confirmation and fulfillment planning
- TASK-037 — Purchases — project and replenishment purchases
- TASK-039 — Receiving (Barang Masuk)
- TASK-040 — Stock views, stock history and movement reversal
- TASK-042 — Condition, location moves, adjustments and losses
- TASK-050 — Billing, receivables, dispute hold and Owner write-off
- TASK-052 — Deduction settlements and contras
- TASK-053 — Customer credit and refunds
- the Owner's answer to P11Q-02 recorded, with what it calls for done — a step run or a narrow amendment approved ([TASK_PLAN §8](TASK_PLAN.md#8-owner-questions)) — BLOCKED until then

**BLOCKS:**

- TASK-062 — Critical journeys A — the golden flow, purchasing, fulfilment, counts, scanning and SIPLAH
- TASK-063 — Critical journeys B — finance, corrections, reports, documents, completion and the checklist
- TASK-064 — Critical journeys C — isolation, sign-in, access, search, approvals, import, language, masters, command identity and status
- TASK-065 — UI/UX consistency gate, the responsive matrix and the anti-slop audit
- TASK-066 — Accessibility — WCAG 2.2 AA across every page
- TASK-067 — Localization completeness and the per-page copy lists
- TASK-068 — Performance targets and query budgets in CI
- TASK-069 — Security test matrix, route integrity and zero dead interactions
- TASK-077 — Cutover tooling — the numbering go-live command, the post-import refresh and the rehearsal procedure
- TASK-087 — Cutover and go-live

**PARALLEL SAFE:** NO — its commit drives every module's opening-fact actions and writes the numbering seeds under LK-31

**IN SCOPE:**

- PG-058 with NAV-058 and its view ROUTE-058 (`import.prepare` with every batch company in scope; `import.commit`; SCR-58): the batches with their states — *Disiapkan*, *Uji Coba Berjalan*, *Uji Coba Lolos*, *Disetujui Bersama*, *Sedang Diimpor*, *Selesai Diimpor* — and each batch's report, sign-offs and progress, the job states polled through ROUTE-285 as IP-12 states (ADMIN_FLOW §13; UXI-32)
- the import kinds of IMP-01 in IMP-03's order — current masters (clients with units and PICs, suppliers, products with units, barcodes, serial policy and prices, locations); opening lots; open projects with their MIGRATION confirmations and remaining demand lines; open purchases with their remaining quantities; opening receivables; opening customer credit with its signed evidence; useful current-year documents as LEGACY_DOCUMENT evidence and identified archive items —, each kind's source layout defined from DATABASE §24's records and checked on FX-09, the data steward's sanitized structures and counts informing it (DEP-05; IMP-12)
- ROUTE-272 with INT-272, "Siapkan Batch" (`IMPORT PrepareImport`; NX-13): the source uploaded first by ROUTE-228 as a private import source — UTF-8 CSV or XLSX within FL-02's limits, parsed as data only, no formula, external entity or macro, XLSX size and entry count checked first, row and column limits (FL-06) —, the kind, the cutoff date and the companies; an `import_identity` per row; malformed and duplicate rows surfaced (WF-MIG-01); the same source of the same kind refused (C-47); a batch identity colliding with a batch outside the preparer's scope answered with exactly MSG-18 under the neutral face of DESIGN_SYSTEM §12.2 and recorded for the Owner (DP-18; UXS-19)
- ROUTE-273 with INT-273, the dry run (`IMPORT DryRunImport`; NX-13; JB-05): the same validation path on an isolated copy, read-only, as a job; the report — rows *Valid*, *Tidak Valid*, *Duplikat*, *Dilewati*, each invalid row with its number and reason, a row whose committed twin lies outside the preparer's scope under *Tidak Valid* with MSG-18 —, the control totals per company — stock quantity and cost, receivables, credits — beside the signed totals; the batch DRY_RUN_PASSED when it passes; a failed validation job in the report with "Jalankan Uji Coba Lagi" (IMP-04; DATABASE §24)
- ROUTE-274 with INT-274, "Setujui Hasil Uji Coba" (`IMPORT SignOffImport`; NX-13): the Admin's and the Owner's sign-off, each with its OPENING_SIGNOFF evidence; SIGNED_OFF, *Disetujui Bersama*, when both exist (L-33; IMP-05)
- ROUTE-275 with INT-275, "Jalankan Impor" (`IMPORT CommitImport`; AX-28): the Owner's commit behind MSG-39's confirmation — the batch, its companies, its cutoff and that numbering seeds are written — and the step-up (IP-08; IP-10; AU-15); the validation run again at the commit request, a batch lacking a seed row for a period its cutoff falls into refused with the missing type and period (NM-03; NX-13); JB-07 then committing each row through its module's ordinary action with its deterministic `command_id`, in chunks sized from the rehearsal (IMP-06; ARCHITECTURE §11); a refused or FAILED row stopping the batch with its reason and the Owner's "Lanjutkan" — a further commit request by any Owner — once its cause is removed; committed rows staying committed (PX-05); a lost chunk re-dispatched by JB-09; *Sedang Diimpor* with the server's count; COMMITTED when no VALID row remains
- the opening facts of DATABASE §24 written by each module's action for its fact — built here inside that module's boundary unless its Task already wrote it, the MIGRATION confirmation being TASK-034's —: opening lots per company, product, location and condition with cost, conversion snapshot, serials and integral base quantities for counted and serialized products, their source company keyed to the row's typed company (BR-INV-12; C-09; IMP-09); opening receivables as OPENING invoices with the remaining value and an OPENING billing act carrying the legacy dates or the *migrasi* bucket, outside live-period revenue and the project invoice cap (BR-FIN-14); opening customer credit as an application source with its signed evidence, never Cash-In (L-50); open projects and purchases of origin OPENING; LEGACY_DOCUMENT evidence and `legacy_archive_items`, never current effects (BR-XD-06; IMP-02); `import_row_id` on every created fact (C-47; BD-11)
- the numbering seeds: AX-28's seed rows writing each period's counter after every legacy number of that period, or 1 where there is none, under NM-03's period rule, a seed row the rule no longer admits recorded SKIPPED (NM-03; HO-32; DB24-09); an opening purchase keeping its legacy number when it fits its company's PURCHASE scheme, any other drawing its number at the commit from its business date's period after that period's seed row commits, a kept number at or above its period's committed seed REJECTED, and a row whose period may create no counter REJECTED (C-10) (`DIR-079` CB-01; NM-08; IMP-07)
- after the commit, the control-total report per company against the committed facts — stock per company equal to its opening lots, receivables equal to the imported remainders, credits equal to the signed opening evidence — for the validators' signature, with `ReconcileGuards` clean (IMP-11; PRQ-06)
- ROUTE-213 with INT-213, "Koreksi Data Awal" on an opening stock fact (`INV ReverseOpeningMovement`; `stock.reverse`), and ROUTE-214 with INT-214 on an opening receivable or credit (`FIN ContraOpeningFact`; `finance.contra`), each with its reason (IP-09): a reversal or contra by import identity, the stock limited to the unconsumed quantity with dependents restored first and SF-RSV-CUT when needed, a receivable or credit only after its applications are disposed; the form saying that a wrong opening fact is never imported again (CM-33; ADMIN_FLOW §11); both reuse the movement reversal of NX-11 (TASK-040), CM-33's stock part (TASK-038, TASK-042) and SF-CONTRA with AX-33 (TASK-031, TASK-052), TASK-040, TASK-042 and TASK-052 not ordered before this Task by the plan — raised to Planning before this Task is READY, never rebuilt here
- **Held for the Owner's answer to P11Q-02, built here once the answer and its row are approved, which comes before this Task is READY** ([TASK_PLAN §8](TASK_PLAN.md#8-owner-questions)): under option (a), once the narrow amendment adds its row, the Owner's batch abort, "Batalkan Batch", with its reason, for a batch that committed no row (IMP-06; PRQ-06; ADMIN_FLOW §13), as its row states; under option (b), only the Owner's V1 decision can take it out, as the answer records (DIR-083 OE-20)

**OUT OF SCOPE:**

- the batch abort's route and interaction until the row of P11Q-02 (a) exists — never built without them (RC-06); the abort is never dropped by this Task (AGENT_OPERATING_MODEL, executor feedback loop)
- the reservations for open projects, re-created after the import through the ordinary reservation commands before go-live (IMP-08; TASK-038); the statistics refresh and plan check after the commit (IMP-10; PRQ-08; TASK-077); the rehearsal on staging (IMP-13; TASK-077, TASK-081); the real data's dry run, sign-off, commit and reconciliation at the cutover (IMP-04; IMP-11; TASK-087); go-live and the Owner's starts on PG-061 (TASK-021; TASK-077)
- the upload route ROUTE-228 (TASK-030); the jobs' infrastructure and the sweeper (TASK-022); the Owner review queue's *Impor* tab (TASK-058); CJ-16 end to end (TASK-064)

**REFERENCES:**

- [WORKFLOWS §5.8](../03-workflows/WORKFLOWS/s05-07-08-completion-migration.md#wf-mig-01--opening-facts-desk) WF-MIG-01; [§8](../03-workflows/WORKFLOWS/s08-09-corrections-indivisible.md#8-correction-reversal-and-cancellation-matrix) CM-33; [§9](../03-workflows/WORKFLOWS/s08-09-corrections-indivisible.md#9-indivisible-business-actions) AX-28; [§12](../03-workflows/WORKFLOWS/s10-14-signals-decisions-traceability.md#12-planner-determined-level-1-decisions) L-33, L-50
- [BUSINESS_RULES](../02-domain/BUSINESS_RULES.md#inventory-inv) BR-INV-12; [FIN](../02-domain/BUSINESS_RULES.md#finance-fin) BR-FIN-05, BR-FIN-14; [§6](../02-domain/BUSINESS_RULES.md#6-cross-domain-interaction-rules) BR-XD-06; [SM:Document](../02-domain/BUSINESS_RULES.md#smdocument-all-doc-0114-invoice-is-a-specialization) BR-DOC-02
- [V1_SCOPE](../01-product/V1_SCOPE.md#must-ship) CAP-15; [DEP-05](../01-product/V1_SCOPE.md#dependencies-and-evidence-needed); [ACCEPTANCE_CRITERIA](../01-product/ACCEPTANCE_CRITERIA.md#product-acceptance-contracts) AC-17; [REFERENCE_COVERAGE](../01-product/REFERENCE_COVERAGE.md#pdf-capability-inventory) REF-051
- [DATABASE §24](../04-architecture/DATABASE/s20-24-index-storage-migration.md#24-migration-and-opening-data) DB24-01–DB24-10; [§4.13](../04-architecture/DATABASE/s04-13-ops.md#413-ops--operations) `import_batches`, `import_rows`, `legacy_archive_items`; [§18](../04-architecture/DATABASE/s18-19-constraints-integrity.md#18-constraint-catalogue) C-09, C-10, C-47
- [ARCHITECTURE §3](../04-architecture/ARCHITECTURE.md#3-modules) the import orchestration; [§11](../04-architecture/ARCHITECTURE.md#11-queue-and-background-jobs)
- [PERMISSIONS_MATRIX §4](../05-security/PERMISSIONS_MATRIX.md#4-capability-catalogue) `import.prepare`, `import.commit`, `stock.reverse`, `finance.contra`; [§7.2](../05-security/PERMISSIONS_MATRIX.md#72-table-classification) rows 122–124; [§7.3](../05-security/PERMISSIONS_MATRIX.md#73-special-projections) PJ-08, PJ-20; [§9](../05-security/PERMISSIONS_MATRIX.md#9-owner-only-denials) OD-13; [§11](../05-security/PERMISSIONS_MATRIX.md#11-data-path-coverage-matrix) DP-05, DP-18
- [SECURITY §5](../05-security/SECURITY.md#5-file-security) FL-02, FL-06; [§6](../05-security/SECURITY.md#6-audit-and-security-logging) LG-01, LG-02
- [CONCURRENCY_IDEMPOTENCY §7.1](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s07-statement-sequences.md#71-lock-sets-of-ax-01ax-37), [§7.2](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s07-statement-sequences.md#72-checks-effects-and-outcomes-of-ax-01ax-37) AX-28 and [§7.3](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s07-statement-sequences.md#73-command-families-outside-ax-0137) NX-11, NX-13; [§10](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s10-13-identity-failure-retry.md#10-command-identity-and-replay) CI-01, CI-08; [§11](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s10-13-identity-failure-retry.md#11-separate-duplicates) BD-11; [§14](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s14-17-numbering-jobs-revocation.md#14-numbering-concurrency) NM-03, NM-08; [§15](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s14-17-numbering-jobs-revocation.md#15-jobs-scheduler-and-queue) JB-05, JB-07, JB-09; [§18](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s18-scenarios.md#18-mandatory-concurrency-and-failure-scenarios) M-18, M-33, M-34; [§19](../06-api-performance/CONCURRENCY_IDEMPOTENCY/s19-20-handoff-traceability.md#19-handoff-obligations) HO-32, HO-33
- [ROUTE_CONTRACTS](../03-workflows/ROUTE_CONTRACTS.md#operations--correction-cases-and-the-opening-import-ops-pg-040-pg-058-wf-mig-01) ROUTE-272–ROUTE-275; [warehouse](../03-workflows/ROUTE_CONTRACTS.md#warehouse--receiving-reservations-dispatch-counts-adjustments-and-discrepancies-inv-pg-018pg-029) ROUTE-213; [finance](../03-workflows/ROUTE_CONTRACTS.md#finance--invoices-payments-deductions-credit-other-cash-and-bank-statements-fin-pg-041pg-050) ROUTE-214; [§2](../03-workflows/ROUTE_CONTRACTS.md#2-page-views--route-001route-065) ROUTE-058
- [INTERACTION_CONTRACTS](../03-workflows/INTERACTION_CONTRACTS.md#operations--correction-cases-and-the-opening-import-ops-pg-040-pg-058-wf-mig-01) INT-272–INT-275, and INT-213, INT-214 in its warehouse and finance groups
- [NAVIGATION_CONTRACTS §2.6](../07-ux-design/NAVIGATION_CONTRACTS.md#26-reports-master-data-settings-and-owner-areas) NAV-058
- [INFORMATION_ARCHITECTURE §8.6](../07-ux-design/INFORMATION_ARCHITECTURE.md#86-reports-master-data-settings-and-owner-areas) PG-058; [§9.2](../07-ux-design/INFORMATION_ARCHITECTURE.md#92-the-owner-review-queue-scr-07) the *Impor* tab
- [ADMIN_FLOW §13](../07-ux-design/ADMIN_FLOW/s11-13-corrections-numbering-import.md#13-opening-import); [§10.7](../07-ux-design/ADMIN_FLOW/s10-b-journeys.md#107-completion-cancellation-and-migration) J-MIG-01; [§11](../07-ux-design/ADMIN_FLOW/s11-13-corrections-numbering-import.md#11-corrections-and-reversals) CM-33's entry; [§3](../07-ux-design/ADMIN_FLOW/s01-04-foundations.md#3-derived-interaction-requirements) UXI-32; [§15](../07-ux-design/ADMIN_FLOW/s14-15-messages-scenarios.md#duplicates) UXS-19; [§14](../07-ux-design/ADMIN_FLOW/s14-15-messages-scenarios.md#14-message-catalogue) MSG-18, MSG-27, MSG-39, MSG-40
- [RELEASE_PLAN §5](../10-release/RELEASE_PLAN.md#5-the-opening-import) IMP-01–IMP-13; [§10](../10-release/RELEASE_PLAN.md#10-production-release-requirements-for-p11) PRQ-06
- [TEST_STRATEGY §15](../08-testing/TEST_STRATEGY.md#15-migration-backup-and-restore-tests) the opening import's tests; [§4](../08-testing/TEST_STRATEGY.md#4-environments-and-data) FX-09; [SECURITY_TEST_MATRIX §3](../08-testing/SECURITY_TEST_MATRIX.md#3-the-matrix) SM-04, SM-09, SM-12, SM-23
- [GAP_REGISTER](../00-governance/GAP_REGISTER.md#gap-010--opening-stock-and-open-receivables-can-be-counted-twice-at-migration) GAP-010; [GAP-058](../00-governance/GAP_REGISTER.md#gap-058--an-imported-purchases-number-is-undefined)
- Architecture challenge — the sequences 11b, 11c, 14a of the [P11 gate record](evidence/P11_QUALITY_GATE.md#whole-system-architecture-challenge-sp-98), their assertions carried into this Task's acceptance criteria and tests
- Coverage — the [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) entries mapped to this Task (68): O-07; CAP-15; DEP-05; AC-17; OS-02; REF-051; WF-MIG-01; CM-33; AX-28; ROUTE-058; ROUTE-213–ROUTE-214; ROUTE-272–ROUTE-275; INT-213–INT-214; INT-272–INT-275; C-47; NX-11; NX-13; BD-11; M-18; M-33–M-34; HO-18–HO-19; HO-32–HO-33; PG-058; SCR-58; UXI-32; J-MIG-01; UXS-19; NAV-058; IMP-01–IMP-13; PRQ-06; migration-rule DB24-01; migration-rule DB24-02; migration-rule DB24-03; migration-rule DB24-04; migration-rule DB24-05; migration-rule DB24-06; migration-rule DB24-07; migration-rule DB24-08; migration-rule DB24-09; migration-rule DB24-10; GAP-010; GAP-034; GAP-058; GAP-066–GAP-067

**EXPECTED MODULES / FILE AREAS:**

- the tier-7 import orchestration — PrepareImport, DryRunImport, SignOffImport, CommitImport, the per-kind parsers and validators, the control-total report — and the jobs `ValidateImportBatch` and `CommitImportRows` (ARCHITECTURE §3, §11); the opening-fact actions inside INV, FIN, PRJ, PUR, PTY, CAT and DOC where their Tasks did not write them; INV ReverseOpeningMovement and FIN ContraOpeningFact; `resources/js/Pages/IMPORT` for PG-058; `tests/Feature/Import`, `tests/Concurrency/` for M-33 and the import cases of M-18, the migration suite of TL-16

**BUSINESS RULES:**

- WF-MIG-01, BR-XD-06 and L-33: opening lots, opening receivables and opening customer credit are the only day-one effects; legacy history is identified archive, never replayed as current effects; reruns are idempotent by import identity; the Admin prepares and dry-runs, the Owner commits after the joint sign-off
- BR-INV-12, BR-FIN-14, BR-FIN-05 and L-50: an opening lot carries owning-company provenance, cost, its migration-time conversion snapshot and its import identity; an opening receivable is a first-class fact — in the billed sum, a valid application target, aged from its real due date or the *migrasi* bucket; opening customer credit is an application source per client and company with signed opening evidence, never a live-period Cash-In
- BR-DOC-02, NM-03, NM-08 and `DIR-079` CB-01: numbers unique per company, type and period and never reused — sequences seeded after the last legacy number used, an imported purchase keeping a fitting legacy number and drawing a new one otherwise
- CM-33: a wrong opening fact is corrected by contra or reversal by import identity — limited to the unconsumed quantity, dependents restored first, SF-RSV-CUT when needed, an opening receivable or credit only after its applications are disposed —, never by importing it again (BR-CR-02)

**ARCHITECTURE RULES:**

- the import orchestration is tier 7: it changes data only by invoking each module's actions with deterministic command identities, so a retried job cannot duplicate an effect, and owns no tables beyond OPS's import rows (ARCHITECTURE §3, §11); bulk reads in keyed chunks, business writes row by row (PF-11)
- the dry run reads the same validation path on an isolated copy and writes only uncommitted rows' results and the control totals (JB-05; DATABASE §24); nothing runs inside a transaction that parses a file (ARCHITECTURE §15)
- raw legacy files live only in private storage as S4 import sources; tests use FX-09's synthetic batches, never a real file (IMP-12; TS-04)

**SECURITY / PERMISSION REQUIREMENTS:**

- ROUTE-058, ROUTE-272–ROUTE-274 `import.prepare` (ADM; prerequisites `projects.view` and the batch kind's view capabilities) with every batch company in scope (PJ-20); ROUTE-275 `import.commit` (OWNER_ONLY) after the joint sign-off, with the step-up (OD-13; AU-15); ROUTE-213 `stock.reverse` and ROUTE-214 `finance.contra` (ADM_PLUS)
- the import source and the sign-off evidence served only through their records to the Owner and the batch's preparers at request time (DP-05; PJ-08; PJ-20); FL-06's parsing hardening; a collision outside scope disclosing nothing beyond MSG-18 (DP-18; SECURITY LG-02)

**COMPANY SCOPE AND FIELD PROJECTION:**

- a batch spanning a company outside the preparer's scope is refused (DP-18); each row's typed company is in the batch's scope, and every fact it creates belongs to that company, a reference in the row — a project, a client, a purchase — resolving inside it (CS-03; CS-04; DATABASE §24)
- projection rows 122–124: `import_batches` (S3) to `import.prepare` holders with every batch company in scope and the Owner; `import_rows` (S3) by the row's company; `legacy_archive_items` FV(c), its file with `evidence.view` as well (PJ-08; PJ-20); raw source files S4; the control totals' money by family within the batch's companies (PJ-22)

**CONCURRENCY / IDEMPOTENCY:**

- NX-13 for preparation, the dry-run result, sign-off and the commit request: lock set LK-00, LK-03, LK-06, LK-09; the commit request locks the batch scope's company rows (LK-06), checks the seed rows of the cutoff's periods, records `committed_by/at` on the SIGNED_OFF batch and starts JB-07; a resume is a further commit request that clears the refusal or failure recorded on its row (CONCURRENCY_IDEMPOTENCY §7.3)
- each row is AX-28: STATIC lock set LK-00, LK-01, LK-03, LK-09, LK-13, LK-14, LK-15, LK-17, LK-31, first-use rows FU-01, FU-02, FU-04, FU-07 and FU-13, LK-01 for an existing project the row refers to; re-read under lock: the batch SIGNED_OFF with its commit request, the row VALID and not COMMITTED, the import identity free, NM-03's period rule for a seed row and a drawing purchase, a kept legacy number below its period's committed seed (NM-08); REJECTED C-10, CONFLICT BD-11; retry class C (§7.1, §7.2; RY-02)
- the deterministic version-5 `command_id` `import:{batch public id}:{row number}:{import identity}` (CI-01); only a COMMITTED outcome recorded, a refusal rolled back and written on the import row so the row can run again once its cause is removed (CI-08); BD-11's committed import identity and `import_row_id` on every created fact make a rerun find the row done (C-47; M-33); JB-05, JB-07 and JB-09 sweep a lost dispatch or a dead chunk (M-34)
- ROUTE-213 and ROUTE-214 are NX-11 as ROUTE_CONTRACTS names them — its lock set holds LK-10–LK-24, capped by LK-16 or the case counters, data-dependent for a reversed movement of a pending case, a count or a finding, FU-03 (§7.3); ROUTE-214's contra of an opening receivable or credit also takes LK-25, LK-26 and LK-27 in the order of §4 — AX-20's and AX-33's classes for the facts it writes, which NX-11's set does not list —, the stricter reading P11Q-03 asks the Owner to confirm (GAP-067)

**AUDIT:**

- preparation, the dry run's result, each sign-off and the commit request are business audit events with the individual actor, the import commit among LG-01's privileged changes; each committed row is audited by the module action that writes its fact, with its import row as linkage (BR-XC-01; LG-01; AZ-10)
- a batch collision outside the preparer's scope writes IMPORT_COLLISION_UNDISCLOSED, and a DUPLICATE row whose twin lies outside scope is listed for the Owner in *Tinjauan*'s *Impor* tab (SECURITY LG-02; DP-18; QS-14); a "Koreksi Data Awal" carries its reason and its linkage to the opening fact, at authority class ADM_PLUS (BR-CR-01; QS-14)

**DATABASE IMPACT ASSESSMENT:**

- Schema change: NONE — uses the tables of TASK-005–TASK-009; a missing column is an executor discovery (AGENT_OPERATING_MODEL)
- Migration required: NO
- Existing data transformation: NONE
- Direct production DB access: FORBIDDEN
- Data-loss risk: NONE KNOWN

**MODULARITY / EXTENSIBILITY:**

- Module boundary affected: the tier-7 import orchestration over every module's actions; INV and FIN for the CM-33 corrections (ARCHITECTURE §3)
- Stable core kept provider-neutral: YES
- External/vendor-specific code isolated: YES — the CSV and XLSX readers behind the parsers, data only (FL-06)
- Replaceable adapter/contract required: NO
- Provider lock-in risk: NONE beyond the Owner's baseline stack and a spreadsheet reader verified under DEP-07
- Future extension impact: LOW — a further import kind is a parser and its module's action
- Speculative abstraction avoided: YES — no generic ETL framework; one parser per kind of IMP-01

**AUTH IMPACT:**

- Authentication change: NONE
- Authentication implementation: LARAVEL_NATIVE — decided once ([EXECUTION_CONTEXT](EXECUTION_CONTEXT.md)), reused, never redesigned
- Auth profile: N/A — this Task does not touch authentication; the commit's step-up is AU-15's existing confirmation (TASK-017)
- Google sign-in: N/A — not touched
- Google registration behavior: N/A — not touched
- Google credentials owner action required: NO
- Authorization change: NONE — `import.prepare`, `import.commit`, `stock.reverse` and `finance.contra` exist in the catalogue (TASK-011)
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

- Context7: REQUIRED — the framework's queued jobs, chunked dispatch and unique jobs; a CSV and XLSX reader that parses data only, chosen and verified under DEP-07; UUID version 5 generation
- Graphify: OPTIONAL — once every module's opening-fact action exists
- Browser E2E: REQUIRED — J-MIG-01 and UXS-19; CJ-16's steps on staging only (TEST_STRATEGY §7)
- Other: FX-09's synthetic batches — clean rows, malformed rows, duplicates, an out-of-scope company's rows and legacy numbers — and the controlled clock FX-08 (TASK-003)
- Missing safe tool behavior: AUTO-INSTALL project-locally inside this Task (AICWDF §6.3) and record it in TOOLCHAIN; a paid tier or a privileged installation STOPS for the Owner (COST_POLICY)

**GRAPHIFY / IMPACT ANALYSIS:**

- Graph freshness: refreshed before the change when Graphify is available ([TOOLCHAIN](../00-governance/TOOLCHAIN.md)); otherwise a targeted search of each module's create, opening and numbering actions
- Pre-change questions: which module action writes each opening fact and whether it exists; which guards each kind's facts move; which seeds each batch must carry (HO-32)
- Expected affected areas: the import orchestration; the opening-fact actions of INV, FIN, PRJ, PUR, PTY, CAT and DOC; ORG's numbering seeds

**CONTEXT7:**

- Topics to verify: job chunking, retries, backoff and unique jobs in the installed queue; the spreadsheet reader's data-only mode, its formula, entity and macro handling, and its memory limits; the framework's UUID helpers
- Version-sensitive assumptions: none from memory

**UI / UX REQUIREMENTS:**

- Follow global design rules: YES
- Indonesian default: YES
- English support: YES — the interface; the import's business data and the report's values stay as recorded (LOCALIZATION §3.4)
- Plain-language Indonesian: YES — each invalid row's reason in business words; MSG-18 exactly as written; MSG-39, MSG-27 and MSG-40
- Language switch impact: keeps the unsent preparation form and reason
- Mobile-first: YES — `[desk]`, readable on the phone (NV-06)
- Responsive: YES
- Adaptive: N/A — one desktop page, readable on the phone
- Visual breathing room: REQUIRED
- Comfortable density: REQUIRED
- shadcn/ui reuse: the data table and list continuation (PT-05, PT-08), the upload field (IP-16), status badges for the batch states (PT-13), the confirmation of an irreversible command (PT-26), the step-up dialog (PT-28), inline messages (PT-14), the loading, empty and error states (PT-19)
- Design reference: PG-058 as INFORMATION_ARCHITECTURE §8.6 and ADMIN_FLOW §13 state it; the Golden Frontend Reference for presentation only (DIR-061 SD-02)
- Primary action: "Siapkan Batch" on the page; in a batch, its next step's one action — "Jalankan Uji Coba", "Setujui Hasil Uji Coba", "Jalankan Impor" or "Lanjutkan"
- Secondary actions: "Jalankan Uji Coba Lagi" on a failed validation; the report's invalid rows; "Koreksi Data Awal" on an opening fact's record
- Destructive actions: none; the commit is irreversible and confirmed first with the step-up (IP-08; IP-10)
- Redundancy constraints: one action per batch step; the commit only for the Owner
- Loading state: the dry run and the commit as job states with the server's count, polled as background requests (IP-12; RB-05)
- Empty state: no batch yet — one sentence with "Siapkan Batch" (PT-19)
- Error state: the row reasons and the reconciliation differences in the report; MSG-18 for a collision outside scope; the missing seed's type and period; REJECTED and FAILED rows with their reason and "Lanjutkan"; IG-02's answers

**ROUTE IMPACT:**

- ROUTE-058 (PG-058's view); ROUTE-272 (prepare), ROUTE-273 (dry run), ROUTE-274 (sign-off), ROUTE-275 (commit and resume); ROUTE-213 and ROUTE-214 (CM-33); ROUTE-228 for the source (TASK-030) and ROUTE-285 for the job states (TASK-022)

**INTERACTION CONTRACTS:**

- INT-272–INT-275, INT-213, INT-214; INT-013 for the source's upload (TASK-030); IG-02 and IG-03

**NAVIGATION CONTRACT:**

- Parent: none — *Data Master* › *Impor Data Awal* (NAV-058)
- Entry: *Data Master* › *Impor Data Awal*
- Back action: N/A — a primary page of its area; its dialogs return to it (NAV-058)
- Cancel: the step-up's and the confirmation's "Kembali", the input kept (NV-02)
- Success destination: PG-058 with the batch's state
- Error behavior: NV-04 — each command answering in place; a batch outside scope → PG-005 (IP-21)
- State preservation: filters, sort, tab and cursor in the address (IP-18; NV-05)
- Mobile behavior: `[desk]` — readable on the phone; the commands on the desktop

**ACCEPTANCE CRITERIA:**

1. A batch is prepared only by an `import.prepare` holder with every batch company in scope, from a UTF-8 CSV or XLSX source parsed as data only, each row with its import identity; malformed and duplicate rows are surfaced with their row number and reason (WF-MIG-01; FL-06; PJ-20).
2. A second batch of the same kind and source is refused (C-47); a batch whose identity collides with a batch outside the preparer's scope answers exactly MSG-18 and nothing more, writes IMPORT_COLLISION_UNDISCLOSED and reaches the Owner's *Impor* tab, and a row whose committed twin lies outside scope is listed under *Tidak Valid* with MSG-18, never under *Duplikat* (DP-18; UXS-19).
3. The dry run on the isolated copy leaves the batch *Uji Coba Lolos* only when its validation passes, and its report gives each row's state and the control totals per company — stock quantity and cost, receivables, credits — beside the signed totals; a failed validation job shows in the report with "Jalankan Uji Coba Lagi" (IMP-04; JB-05).
4. Only after both the Admin's and the Owner's sign-offs with their evidence may the Owner commit, behind the confirmation and the step-up; an Admin's commit is denied (L-33; OD-13; AU-15).
5. A commit request for a batch without a seed row for a period its cutoff falls into is refused, naming the missing type and period (NM-03; IMP-05).
6. Rerunning a chunk or a whole batch, resuming after a refused row as another Owner, and re-dispatching a lost chunk never create a second lot, receivable, credit or project for one legacy row (AX-28; BD-11; C-47; M-33; architecture challenge 11b).
7. A refused or FAILED row stops the batch with its reason while committed rows stay committed, and the row commits once its cause is removed and the Owner resumes (CI-08; JB-07; PX-05).
8. An opening purchase whose legacy number fits its company's PURCHASE scheme keeps it, and the sequence is seeded after the last legacy number; any other draws its number at the commit from its business date's period after that period's seed row; a kept number at or above its period's committed seed is REJECTED and the dry run surfaces it; a row whose period may create no counter is REJECTED (`DIR-079` CB-01; NM-08; C-10; GAP-058; architecture challenge 14a).
9. Opening lots carry their company, cost, conversion snapshot and serials with integral base quantities for counted and serialized products; opening receivables carry the remaining value and their due date or the *migrasi* bucket and stay out of live-period revenue and the project invoice cap; opening customer credit is an application source, never Cash-In; archived history creates no current effect (BR-INV-12; BR-FIN-14; L-50; BR-XD-06; C-09).
10. After the commit `ReconcileGuards` reports no difference, and the report shows stock per company equal to its opening lots, receivables equal to the imported remainders and credits equal to the signed opening evidence (IMP-11; AC-17; GAP-010).
11. A wrong opening fact is corrected only on its record by "Koreksi Data Awal" with a reason — a stock reversal by import identity within the unconsumed quantity, dependents restored first and SF-RSV-CUT when needed; a receivable or credit contra only after its applications are disposed — and no path re-imports it (CM-33; architecture challenge 11c).
12. No opening fact reserves stock (IMP-08).
13. Each NX-13 command replayed with its `command_id` returns its recorded outcome (CI-05; IG-05).
14. Once the row of P11Q-02 (a) is approved, "Batalkan Batch" aborts with its reason a batch that committed no row, so that none of its rows commits afterwards, and is refused for a batch with a committed row (IMP-06; PRQ-06; ADMIN_FLOW §13; TASK_PLAN §8).
15. A batch takes as its source only an import-source upload its own preparer made; another account's upload answers as not found, proven by an authorization test — the database holds a source file to one batch, not to its preparer (DIR-089 (5); FL-01; PJ-20; IDOR).

**TEST REQUIREMENTS:**

- Lint: TL-01
- Typecheck: TL-02
- Unit: each kind's validator and parser on malformed, duplicate and out-of-scope rows; the seed period rule and the legacy-number fit (TL-03)
- Feature: ROUTE-058, ROUTE-272–ROUTE-275, ROUTE-213 and ROUTE-214 with each refusal; the batch abort once its row exists (TL-04)
- Integration: M-33's cases — a chunk and a whole batch rerun, a refused row fixed and resumed, a resume by another Owner —; M-34 with the store stopped during a commit; M-18's import cases and HO-19 with the controlled clock; JB-05, JB-07 and JB-09 crash and lost-dispatch tests (HO-18); `ReconcileGuards` clean (TL-05)
- Authorization: `import.prepare` with every batch company in scope, `import.commit` for the Owner after the sign-off, `stock.reverse` and `finance.contra` (TL-06); criterion 15's source file of another preparer (DIR-089 (5))
- Route integrity: ROUTE-058, ROUTE-213, ROUTE-214 and ROUTE-272–ROUTE-275 audited, and the abort's route once its row exists (TL-07)
- Interaction: INT-213, INT-214 and INT-272–INT-275, and the abort's INT- row once it exists, each with one `command_id` replayed and INT-275's step-up expiring (TL-08; IG-05)
- Browser E2E: J-MIG-01 and UXS-19 on the CI stack; CJ-16's steps rehearsed on staging by TASK-064 and TASK-081 (TL-09)
- Localization/language switch: both languages on PG-058 and its dialogs; the report's values unchanged (TL-10)
- Visual breathing room/density: TEST_STRATEGY §9 — CB-01's imported purchase number on PG-058 verified on the application
- Responsive: TL-11
- Accessibility: TL-12
- Production build: TL-13
- Security: SM-04 for W4-25 — an Admin committing, a batch spanning an out-of-scope company —; SM-12 and SM-23 for the hidden collision; SM-09 for the import-source purpose; SM-01 for a foreign import source and batch (DP-05) (TL-14)
- Performance: PF-11's keyed chunks within JB-07's timeout at AS-04's 30,000 rows; IX-15's commit-request sweep; the hot plans re-checked on the imported fixture with PFM-03 (TL-15)
- Migration: TEST_STRATEGY §15's opening-import suite on FX-09 — validation and quarantine, the dry run, the joint sign-off and the commit, chunking and resume, the numbering seeds, AX-28's refusal of a kept legacy number, opening lots, credit and receivables, and the reconciliation (TL-16)
- Other: the batch abort's test waits for its contract (OUT OF SCOPE); GAP-010's and GAP-058's evidence recorded

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
- open, copy or commit a real legacy file, or run the real data's dry run or commit — the Admin's, the Owner's and the operator's at the cutover (IMP-04; IMP-12; RL-05)
- replay archived history as current effects, import a wrong opening fact again, or let an opening fact reserve stock (BR-XD-06; CM-33; IMP-08)
- add a route or control for the batch abort before Planning contracts it (OE-20; RC-06)

# Task plan

Status: APPROVED | Updated: 2026-10-20 | Owner: Planning

Approval: [APPR-016](../00-governance/DECISION_LOG.md#appr-016--p11-task-plan-tasks-and-feature-coverage-matrix-approved), explicit Owner approval on 2026-10-20 (DIR-085 OF-07) of this document as committed in the first execution session's approval commit; the approved file hash and the verified conditions are recorded there. Approval changes lifecycle only, not implementation authorization.

Run PC-5 wrote this plan on 2026-10-19 under [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) SP-96 and SP-97, P11 being authorized by the Owner at OG-3 ([DIR-083](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) OE-19). The Owner approved P11 at OG-4 (DIR-085 OF-07); the planning freeze and the execution authorization, separate decisions, are in force from the freeze record (OF-08, OF-09). It is the Owner's execution dashboard of AICWDF §35; each Task's contract is its own file `docs/11-tasks/TASK-nnn.md` ([Tasks (P11)](README.md)), and every requirement P1–P10 defines by an identifier is mapped to a Task, or to a recorded disposition, in [FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md).

**No Task starts before the freeze record, from which the execution authorization is in force (DIR-085 OF-09).**

## 1. Project task status

```text
PROJECT TASK STATUS

Baseline Total Tasks: 87
Current Total Tasks: 87

DONE: 3
READY: 2
IN_PROGRESS: 0
BLOCKED: 13
VERIFYING: 1
TODO: 68
SUPERSEDED: 0

Progress:
3 / 87
Percentage: 3.4 %

CURRENT TASK: TASK-004 — Verification and release tooling check, VERIFYING: built on its branch `task/TASK-004-tooling-check` from `main` after TASK-003's merge (record 84 ER-03), its pull request into `main` waiting on its fresh CI run (ER-07)
NEXT READY TASK: TASK-005 — Schema foundations — IAM and OPS tables, history and types, taken after the Owner merges TASK-004's pull request (ER-07), then TASK-014, also READY; TASK-071 once the client's access OPI-26 is recorded
LAST TASK PLAN CHANGE: Change 3 of 2026-10-20 — the pre-mortem's additions to TASK-001, TASK-002 and TASK-070, DIR-085 OF-06's edit of TASK-020 and TASK-085, and the answered P11Q-01, P11Q-05 and P11Q-06 removed from the Tasks they blocked; totals unchanged (§7)
ADDITIONAL RECURRING PRODUCTION COST: 0 — target 0 (COST_POLICY)
PAID SERVICE EXCEPTIONS: none (TestSprite only as DIR-083 OE-01 states; TASK-004)
```

The counters are computed by script from the list in §4 and reconcile with it.

## 2. Rules of the plan

- **Scope (DIR-083 OE-20), binding on every Task:** «Scope: no Task may add to V1 — a capability, page, route, field, integration, document type, report or paid service — without my approval, and no Task may drop, defer or narrow a V1_REQUIRED requirement. A Task that would need either raises an Owner question and waits.»
- **Authorization.** Nothing here authorizes execution by itself: the Owner gave the planning freeze and the execution authorization at OG-4 ([DIR-085](../00-governance/DECISION_LOG.md#dir-085-dir-086-obs-034-and-tech-041--og-4-decisions-appr-016-the-planning-freeze-and-the-execution-authorization) OF-08, OF-09), each in force from the freeze record, under the standing rules of [record 84](../00-governance/sources/EXECUTION_AUTHORIZATION_2026-10-20.txt) (DIR-086); from it an Execution Agent takes one READY Task at a time ([AGENT_OPERATING_MODEL](../00-governance/AGENT_OPERATING_MODEL.md#task-model)), reads [AGENTS.md](../../AGENTS.md), [EXECUTION_CONTEXT](EXECUTION_CONTEXT.md), [CURRENT_HANDOFF](../handoff/CURRENT_HANDOFF.md), this plan and its Task, and then only the sections the Task references.
- **Statuses at creation.** No Task is READY at creation. A Task is BLOCKED on an input that someone else must give first and that is not yet recorded — an operator's, the Owner's or the client's input of RELEASE_PLAN §9 (OPI-), such as the client's grant of access OPI-26 for TASK-071; the Owner's answer to a P11 question, with what it calls for done — a step run, or a narrow amendment approved and the Task's contract updated (P11Q-; §8) —, P11Q-01's pre-mortem gating TASK-001 and TASK-071 and P11Q-05's recording rule the operator's and the Owner's Tasks that record facts in approved documents, both answered at OG-4 (DIR-085 OF-01, OF-05) and lifted by Change 3; or the data steward's legacy structures (DEP-05) —, each named in the Status column and in the Task's DEPENDS ON. A Task is never BLOCKED on what it records itself, as TASK-079, TASK-082, TASK-083 and TASK-085 record their own inputs and TASK-084 its own test of OPI-08. Every other Task is TODO; nothing is assumed. A Task becomes READY only when its dependencies are DONE and its READY gate holds (AICWDF §22.8) — for TASK-001 also the development machine's tools its TOOL REQUIREMENTS name, available or their installation approved by the Owner —; it is DONE only when its required tests pass, locally and again in fresh CI, and its completion evidence exists ([DEFINITION_OF_DONE](../08-testing/DEFINITION_OF_DONE.md#1-statuses-and-the-done-rule); AICWDF §22.2), TASK-001's minimal CI workflow giving the first Tasks their fresh CI.
- **The operator's and the Owner's Tasks.** Every step that needs the VPS — staging, the measurements, the backups and the off-server copy, the drills, the alert channel, the two applications' isolation, the provider features, the installation, the go-live command and the deployment — is a Task with EXECUTOR: HUMAN OPERATOR; a decision or action that only the Owner or the client can give is EXECUTOR: OWNER. An agent may prepare such a Task's artifacts in the repository; it never runs the Task, and the Task's DONE evidence is the operator's or the Owner's record, never an agent's claim ([INFRASTRUCTURE](../09-operations/INFRASTRUCTURE.md) INF-01; DIR-083 OE-14). No Task gives an agent access to the VPS, production or a credential.
- **Order and parallel work.** Every dependency points to an earlier Task, so the order runs from the foundation to go-live; DEPENDS ON and BLOCKS are declared both ways and checked by script, and the graph is acyclic. One Execution Agent works on one Task at a time; Tasks run in parallel only where both are PARALLEL SAFE: YES (AICWDF §22.7, §22.9). That rule binds the Execution Agents' Tasks: an operator's or the Owner's Task may run beside an agent Task when it changes no file the agent Task changes, as TASK-071 runs beside the foundation.
- **Stable IDs and controlled changes.** A Task ID is permanent; a split Task becomes SUPERSEDED and names its successors; every addition, split or merge is recorded in §7 in AICWDF §22.6's form, with the Owner's approval where the scope changes (AICWDF §22.4–§22.6).
- **Progress.** When a Task reaches DONE its executor sets its status, checks its box in §3, re-checks the READY gate of each Task it blocks and sets those Tasks' statuses in §4 and in their contracts, updates §1's counters, CURRENT TASK and NEXT READY TASK, and updates CURRENT_HANDOFF (AICWDF §22.3); an operator's or the Owner's Task is recorded DONE from that person's record, as the operator's and the Owner's Tasks rule above states.

## 3. Master Task checklist

- [x] [TASK-001](TASK-001.md) — Repository scaffold and module skeleton
- [x] [TASK-002](TASK-002.md) — Coding conventions and architecture guardrails
- [x] [TASK-003](TASK-003.md) — CI pipeline, test layers and the synthetic fixture harness
- [ ] [TASK-004](TASK-004.md) — Verification and release tooling check
- [ ] [TASK-005](TASK-005.md) — Schema foundations — IAM and OPS tables, history and types
- [ ] [TASK-006](TASK-006.md) — Schema — ORG, PTY, CAT and DOC tables
- [ ] [TASK-007](TASK-007.md) — Schema — PRJ, CST, PUR and ADM tables
- [ ] [TASK-008](TASK-008.md) — Schema — INV tables, inventory and lot-cost representations
- [ ] [TASK-009](TASK-009.md) — Schema — FUL and FIN tables, finance and tax representations
- [ ] [TASK-010](TASK-010.md) — Audit and security logging
- [ ] [TASK-011](TASK-011.md) — Authorization core — capabilities, roles, grants, company scope and the route audit
- [ ] [TASK-012](TASK-012.md) — Field projection, read classes and cross-company links
- [ ] [TASK-013](TASK-013.md) — Command envelope, command identity, idempotency and the lock order
- [ ] [TASK-014](TASK-014.md) — Localization — locales, the language switch and the key catalogue
- [ ] [TASK-015](TASK-015.md) — Design system — tokens, components, patterns and states
- [ ] [TASK-016](TASK-016.md) — Application shell, navigation and the company context
- [ ] [TASK-017](TASK-017.md) — Password sign-in, sessions and credential links
- [ ] [TASK-018](TASK-018.md) — Two-step verification (TOTP) and recovery codes
- [ ] [TASK-019](TASK-019.md) — Google sign-in for existing accounts
- [ ] [TASK-020](TASK-020.md) — Installation step, the first Owner and the Owner-recovery path
- [ ] [TASK-021](TASK-021.md) — Numbering, its settings page and the Owner's raise after a restore
- [ ] [TASK-022](TASK-022.md) — Jobs, files and the document renderer
- [ ] [TASK-023](TASK-023.md) — My account — password, two-step verification, Google link and language
- [ ] [TASK-024](TASK-024.md) — Users and access — accounts, roles, capabilities and company grants
- [ ] [TASK-025](TASK-025.md) — Companies, identity assets, bank accounts and tax configuration
- [ ] [TASK-026](TASK-026.md) — Clients and suppliers
- [ ] [TASK-027](TASK-027.md) — Catalog — products, services, units, barcodes, prices and images
- [ ] [TASK-028](TASK-028.md) — Rack locations and minimum stock
- [ ] [TASK-029](TASK-029.md) — Document engine — drafts, issue, revision, void, renditions and the composer
- [ ] [TASK-030](TASK-030.md) — Evidence and uploaded documents
- [ ] [TASK-031](TASK-031.md) — Correction cases
- [ ] [TASK-032](TASK-032.md) — Projects — list, form, journey page and amendment
- [ ] [TASK-033](TASK-033.md) — Quotations
- [ ] [TASK-034](TASK-034.md) — Commercial confirmation and fulfillment planning
- [ ] [TASK-035](TASK-035.md) — Administrative checklist and document readiness
- [ ] [TASK-036](TASK-036.md) — Costing foundations — lot cost basis, charge allocation and HPP attribution
- [ ] [TASK-037](TASK-037.md) — Purchases — project and replenishment purchases
- [ ] [TASK-038](TASK-038.md) — Reservations
- [ ] [TASK-039](TASK-039.md) — Receiving (Barang Masuk)
- [ ] [TASK-040](TASK-040.md) — Stock views, stock history and movement reversal
- [ ] [TASK-041](TASK-041.md) — Dispatch (Barang Keluar) and inter-company allocation
- [ ] [TASK-042](TASK-042.md) — Condition, location moves, adjustments and losses
- [ ] [TASK-043](TASK-043.md) — Stock count (opname) and discrepancies
- [ ] [TASK-044](TASK-044.md) — Purchase return to supplier
- [ ] [TASK-045](TASK-045.md) — Deliveries (Pengiriman) and delivery closure
- [ ] [TASK-046](TASK-046.md) — Direct shipment (drop-ship)
- [ ] [TASK-047](TASK-047.md) — Service fulfillment and handover
- [ ] [TASK-048](TASK-048.md) — Client returns (sales return)
- [ ] [TASK-049](TASK-049.md) — Invoices and the pre-payment Kuitansi
- [ ] [TASK-050](TASK-050.md) — Billing, receivables, dispute hold and Owner write-off
- [ ] [TASK-051](TASK-051.md) — Payments, applications and bank statement lines
- [ ] [TASK-052](TASK-052.md) — Deduction settlements and contras
- [ ] [TASK-053](TASK-053.md) — Customer credit and refunds
- [ ] [TASK-054](TASK-054.md) — Expenses, disbursements and other cash
- [ ] [TASK-055](TASK-055.md) — Project completion and Force Complete
- [ ] [TASK-056](TASK-056.md) — Project cancellation
- [ ] [TASK-057](TASK-057.md) — Opening import — preparation, dry run, sign-off and commit
- [ ] [TASK-058](TASK-058.md) — Home, Work and Review — signals and the action queue
- [ ] [TASK-059](TASK-059.md) — Search and scanning
- [ ] [TASK-060](TASK-060.md) — Reports, the consolidated report and exports
- [ ] [TASK-061](TASK-061.md) — Activity history and security log
- [ ] [TASK-062](TASK-062.md) — Critical journeys A — the golden flow, purchasing, fulfilment, counts, scanning and SIPLAH
- [ ] [TASK-063](TASK-063.md) — Critical journeys B — finance, corrections, reports, documents, completion and the checklist
- [ ] [TASK-064](TASK-064.md) — Critical journeys C — isolation, sign-in, access, search, approvals, import, language, masters, command identity and status
- [ ] [TASK-065](TASK-065.md) — UI/UX consistency gate, the responsive matrix and the anti-slop audit
- [ ] [TASK-066](TASK-066.md) — Accessibility — WCAG 2.2 AA across every page
- [ ] [TASK-067](TASK-067.md) — Localization completeness and the per-page copy lists
- [ ] [TASK-068](TASK-068.md) — Performance targets and query budgets in CI
- [ ] [TASK-069](TASK-069.md) — Security test matrix, route integrity and zero dead interactions
- [ ] [TASK-070](TASK-070.md) — Full release regression and the device UAT's preparation
- [ ] [TASK-071](TASK-071.md) — The operator's inputs before staging
- [ ] [TASK-072](TASK-072.md) — Deployment definition, health and smoke checks, maintenance mode and mixed-version safeguards
- [ ] [TASK-073](TASK-073.md) — Release process — branch protection, release tags, the release record and integrity report
- [ ] [TASK-074](TASK-074.md) — Backup tooling, the off-server copy and the drill procedures
- [ ] [TASK-075](TASK-075.md) — Monitoring — the business heartbeat, alerts and the uptime check
- [ ] [TASK-076](TASK-076.md) — Operator runbooks — Owner-account recovery, key compromise, Scenario B and key rotation
- [ ] [TASK-077](TASK-077.md) — Cutover tooling — the numbering go-live command, the post-import refresh and the rehearsal procedure
- [ ] [TASK-078](TASK-078.md) — Staging stack, the two applications' isolation and the provider features
- [ ] [TASK-079](TASK-079.md) — Staging measurements — capacity, Argon2id, durations and the disk budget
- [ ] [TASK-080](TASK-080.md) — Alert channel adopted and monitoring live
- [ ] [TASK-081](TASK-081.md) — Staging drills and rehearsals — failure, restore, Scenario B and the import rehearsal
- [ ] [TASK-082](TASK-082.md) — Google OAuth clients for staging and production
- [ ] [TASK-083](TASK-083.md) — The alternate operator named and the dispute's governance recorded
- [ ] [TASK-084](TASK-084.md) — Production preparation — configuration, outbound HTTPS, branch protection, backups and the off-server copy
- [ ] [TASK-085](TASK-085.md) — Installation, the first Owner, two-step enrolment and the APP_KEY rotation
- [ ] [TASK-086](TASK-086.md) — UAT with the Owner and the validators
- [ ] [TASK-087](TASK-087.md) — Cutover and go-live

## 4. The Task list

| Task | Title | Stage · module | Risk | Status | Depends on (TASK-) | Blocks (TASK-) | Parallel safe | Executor | DB impact | UI impact |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| [TASK-001](TASK-001.md) | Repository scaffold and module skeleton | Foundation · Foundation | HIGH | DONE | — | 002 | NO | AGENT | NONE | NO |
| [TASK-002](TASK-002.md) | Coding conventions and architecture guardrails | Foundation · Foundation | STANDARD | DONE | 001 | 003 | NO | AGENT | NONE | NO |
| [TASK-003](TASK-003.md) | CI pipeline, test layers and the synthetic fixture harness | Foundation · Foundation | STANDARD | DONE | 002 | 004, 005, 014, 072, 073 | NO | AGENT | NONE | NO |
| [TASK-004](TASK-004.md) | Verification and release tooling check | Foundation · Foundation | STANDARD | VERIFYING | 003 | 062, 063, 064, 065, 066, 067, 068, 069, 073 | NO | AGENT | NONE | NO |
| [TASK-005](TASK-005.md) | Schema foundations — IAM and OPS tables, history and types | Platform · Platform · IAM · OPS | HIGH | READY | 003 | 006, 010, 011, 017 | NO | AGENT | PROPOSED | NO |
| [TASK-006](TASK-006.md) | Schema — ORG, PTY, CAT and DOC tables | Platform · Platform · ORG · PTY · CAT · DOC | HIGH | TODO | 005 | 007, 021, 022 | NO | AGENT | PROPOSED | NO |
| [TASK-007](TASK-007.md) | Schema — PRJ, CST, PUR and ADM tables | Platform · Platform · PRJ · CST · PUR · ADM | HIGH | TODO | 006 | 008 | NO | AGENT | PROPOSED | NO |
| [TASK-008](TASK-008.md) | Schema — INV tables, inventory and lot-cost representations | Platform · Platform · INV | HIGH | TODO | 007 | 009, 028, 036 | NO | AGENT | PROPOSED | NO |
| [TASK-009](TASK-009.md) | Schema — FUL and FIN tables, finance and tax representations | Platform · Platform · FUL · FIN | HIGH | TODO | 008 | 012, 013 | NO | AGENT | PROPOSED | NO |
| [TASK-010](TASK-010.md) | Audit and security logging | Platform · OPS · Platform | HIGH | TODO | 005 | 011, 013, 017, 020, 031, 061, 075 | NO | AGENT | NONE | NO |
| [TASK-011](TASK-011.md) | Authorization core — capabilities, roles, grants, company scope and the route audit | Platform · IAM · Platform | HIGH | TODO | 005, 010 | 012, 013, 016 | NO | AGENT | NONE | NO |
| [TASK-012](TASK-012.md) | Field projection, read classes and cross-company links | Platform · Platform | HIGH | TODO | 009, 011 | 016, 022 | NO | AGENT | NONE | NO |
| [TASK-013](TASK-013.md) | Command envelope, command identity, idempotency and the lock order | Platform · Platform | HIGH | TODO | 009, 010, 011 | 016, 017, 021, 022, 024, 025, 026, 027, 028, 031, 036, 072, 074, 075, 076 | NO | AGENT | PROPOSED | NO |
| [TASK-014](TASK-014.md) | Localization — locales, the language switch and the key catalogue | Platform · Platform | STANDARD | READY | 003 | 015, 023 | NO | AGENT | NONE | YES |
| [TASK-015](TASK-015.md) | Design system — tokens, components, patterns and states | Platform · Platform · UI | STANDARD | TODO | 014 | 016, 017, 022 | NO | AGENT | NONE | YES |
| [TASK-016](TASK-016.md) | Application shell, navigation and the company context | Platform · Platform · UI | HIGH | TODO | 011, 012, 013, 015 | 017, 021, 023, 024, 025, 026, 027, 028, 030, 031, 061 | NO | AGENT | NONE | YES |
| [TASK-017](TASK-017.md) | Password sign-in, sessions and credential links | Platform · IAM | HIGH | TODO | 005, 010, 013, 015, 016 | 018, 022 | NO | AGENT | NONE | YES |
| [TASK-018](TASK-018.md) | Two-step verification (TOTP) and recovery codes | Platform · IAM | HIGH | TODO | 017 | 019, 020, 021, 074 | NO | AGENT | NONE | YES |
| [TASK-019](TASK-019.md) | Google sign-in for existing accounts | Platform · IAM | HIGH | TODO | 018 | 020, 023, 024, 082 | NO | AGENT | NONE | YES |
| [TASK-020](TASK-020.md) | Installation step, the first Owner and the Owner-recovery path | Platform · IAM · OPS | HIGH | TODO | 010, 018, 019 | 069, 076, 085 | NO | AGENT | NONE | NO |
| [TASK-021](TASK-021.md) | Numbering, its settings page and the Owner's raise after a restore | Platform · ORG · Platform | HIGH | TODO | 006, 013, 016, 018 | 029, 032, 074, 076, 077 | NO | AGENT | NONE | YES |
| [TASK-022](TASK-022.md) | Jobs, files and the document renderer | Platform · Platform · DOC | HIGH | TODO | 006, 012, 013, 015, 017 | 025, 027, 029, 030, 057, 060, 072, 074, 075 | NO | AGENT | NONE | NO |
| [TASK-023](TASK-023.md) | My account — password, two-step verification, Google link and language | Module · IAM | HIGH | TODO | 014, 016, 019 | 062, 063, 064, 065, 066, 067, 068, 069 | NO | AGENT | NONE | YES |
| [TASK-024](TASK-024.md) | Users and access — accounts, roles, capabilities and company grants | Module · IAM | HIGH | TODO | 013, 016, 019 | 062, 063, 064, 065, 066, 067, 068, 069 | NO | AGENT | NONE | YES |
| [TASK-025](TASK-025.md) | Companies, identity assets, bank accounts and tax configuration | Module · ORG | HIGH | TODO | 013, 016, 022 | 029, 032, 049 | NO | AGENT | NONE | YES |
| [TASK-026](TASK-026.md) | Clients and suppliers | Module · PTY | STANDARD | TODO | 013, 016 | 032, 037, 057 | NO | AGENT | NONE | YES |
| [TASK-027](TASK-027.md) | Catalog — products, services, units, barcodes, prices and images | Module · CAT | STANDARD | TODO | 013, 016, 022 | 032, 037, 057, 059 | NO | AGENT | NONE | YES |
| [TASK-028](TASK-028.md) | Rack locations and minimum stock | Module · INV | STANDARD | TODO | 008, 013, 016 | 038, 057 | NO | AGENT | NONE | YES |
| [TASK-029](TASK-029.md) | Document engine — drafts, issue, revision, void, renditions and the composer | Module · DOC | HIGH | TODO | 021, 022, 025 | 033, 037, 039, 041, 045, 046, 047, 049, 059, 074 | NO | AGENT | NONE | YES |
| [TASK-030](TASK-030.md) | Evidence and uploaded documents | Module · DOC | HIGH | TODO | 016, 022 | 034, 035, 036, 051, 054, 057 | NO | AGENT | NONE | YES |
| [TASK-031](TASK-031.md) | Correction cases | Module · OPS | HIGH | TODO | 010, 013, 016 | 039, 042, 055 | NO | AGENT | NONE | YES |
| [TASK-032](TASK-032.md) | Projects — list, form, journey page and amendment | Module · PRJ | HIGH | TODO | 021, 025, 026, 027 | 033, 035, 057, 059 | NO | AGENT | NONE | YES |
| [TASK-033](TASK-033.md) | Quotations | Module · PRJ | STANDARD | TODO | 029, 032 | 034 | NO | AGENT | NONE | YES |
| [TASK-034](TASK-034.md) | Commercial confirmation and fulfillment planning | Module · PRJ | HIGH | TODO | 030, 033 | 035, 037, 038, 046, 047, 049, 057 | NO | AGENT | NONE | YES |
| [TASK-035](TASK-035.md) | Administrative checklist and document readiness | Module · ADM | HIGH | TODO | 030, 032, 034 | 055 | NO | AGENT | NONE | YES |
| [TASK-036](TASK-036.md) | Costing foundations — lot cost basis, charge allocation and HPP attribution | Module · CST | HIGH | TODO | 008, 013, 030 | 037, 042, 054 | NO | AGENT | NONE | NO |
| [TASK-037](TASK-037.md) | Purchases — project and replenishment purchases | Module · PUR | HIGH | BLOCKED — P11Q-02, P11Q-04 | 026, 027, 029, 034, 036 | 039, 044, 046, 054, 057, 060 | NO | AGENT | NONE | YES |
| [TASK-038](TASK-038.md) | Reservations | Module · INV | HIGH | TODO | 028, 034 | 039, 041, 056 | NO | AGENT | NONE | YES |
| [TASK-039](TASK-039.md) | Receiving (Barang Masuk) | Module · INV | HIGH | TODO | 029, 031, 037, 038 | 040, 041, 042, 044, 057 | NO | AGENT | NONE | YES |
| [TASK-040](TASK-040.md) | Stock views, stock history and movement reversal | Module · INV | HIGH | TODO | 039 | 042, 044, 057, 059 | NO | AGENT | NONE | YES |
| [TASK-041](TASK-041.md) | Dispatch (Barang Keluar) and inter-company allocation | Module · INV | HIGH | TODO | 029, 038, 039 | 045, 056 | NO | AGENT | NONE | YES |
| [TASK-042](TASK-042.md) | Condition, location moves, adjustments and losses | Module · INV | HIGH | TODO | 031, 036, 039, 040 | 043, 048, 057 | NO | AGENT | NONE | YES |
| [TASK-043](TASK-043.md) | Stock count (opname) and discrepancies | Module · INV | HIGH | BLOCKED — P11Q-02, P11Q-04 | 042 | 055, 058 | NO | AGENT | NONE | YES |
| [TASK-044](TASK-044.md) | Purchase return to supplier | Module · PUR · INV | HIGH | TODO | 037, 039, 040 | 048, 054, 055 | NO | AGENT | NONE | YES |
| [TASK-045](TASK-045.md) | Deliveries (Pengiriman) and delivery closure | Module · FUL | HIGH | TODO | 029, 041 | 048, 055 | NO | AGENT | NONE | YES |
| [TASK-046](TASK-046.md) | Direct shipment (drop-ship) | Module · FUL | HIGH | BLOCKED — P11Q-02 | 029, 034, 037 | 054, 055, 056 | NO | AGENT | NONE | YES |
| [TASK-047](TASK-047.md) | Service fulfillment and handover | Module · FUL | STANDARD | TODO | 029, 034 | 055, 056 | NO | AGENT | NONE | YES |
| [TASK-048](TASK-048.md) | Client returns (sales return) | Module · INV · FUL | HIGH | TODO | 042, 044, 045 | 053, 055 | NO | AGENT | NONE | YES |
| [TASK-049](TASK-049.md) | Invoices and the pre-payment Kuitansi | Module · FIN | HIGH | TODO | 025, 029, 034 | 050, 056 | NO | AGENT | NONE | YES |
| [TASK-050](TASK-050.md) | Billing, receivables, dispute hold and Owner write-off | Module · FIN | HIGH | TODO | 049 | 051, 055, 057 | NO | AGENT | NONE | YES |
| [TASK-051](TASK-051.md) | Payments, applications and bank statement lines | Module · FIN · DOC | HIGH | TODO | 030, 050 | 052, 053, 060 | NO | AGENT | NONE | YES |
| [TASK-052](TASK-052.md) | Deduction settlements and contras | Module · FIN | HIGH | BLOCKED — P11Q-02 | 051 | 053, 054, 055, 057 | NO | AGENT | NONE | YES |
| [TASK-053](TASK-053.md) | Customer credit and refunds | Module · FIN | HIGH | BLOCKED — P11Q-02 | 048, 051, 052 | 055, 057 | NO | AGENT | NONE | YES |
| [TASK-054](TASK-054.md) | Expenses, disbursements and other cash | Module · CST · FIN | HIGH | TODO | 030, 036, 037, 044, 046, 052 | 055, 058, 060 | NO | AGENT | NONE | YES |
| [TASK-055](TASK-055.md) | Project completion and Force Complete | Module · PRJ | HIGH | BLOCKED — P11Q-02 | 031, 035, 043, 044, 045, 046, 047, 048, 050, 052, 053, 054 | 056, 058 | NO | AGENT | NONE | YES |
| [TASK-056](TASK-056.md) | Project cancellation | Module · PRJ | HIGH | TODO | 038, 041, 046, 047, 049, 055 | 062, 063, 064, 065, 066, 067, 068, 069 | NO | AGENT | NONE | YES |
| [TASK-057](TASK-057.md) | Opening import — preparation, dry run, sign-off and commit | Module · Import orchestration · OPS | HIGH | BLOCKED — P11Q-02 | 022, 026, 027, 028, 030, 032, 034, 037, 039, 040, 042, 050, 052, 053 | 062, 063, 064, 065, 066, 067, 068, 069, 077, 087 | NO | AGENT | NONE | YES |
| [TASK-058](TASK-058.md) | Home, Work and Review — signals and the action queue | Module · Reporting | STANDARD | TODO | 043, 054, 055 | 062, 063, 064, 065, 066, 067, 068, 069, 075 | NO | AGENT | NONE | YES |
| [TASK-059](TASK-059.md) | Search and scanning | Module · Reporting · INV | HIGH | TODO | 027, 029, 032, 040 | 062, 063, 064, 065, 066, 067, 068, 069 | NO | AGENT | NONE | YES |
| [TASK-060](TASK-060.md) | Reports, the consolidated report and exports | Module · Reporting | HIGH | TODO | 022, 037, 051, 054 | 062, 063, 064, 065, 066, 067, 068, 069 | NO | AGENT | NONE | YES |
| [TASK-061](TASK-061.md) | Activity history and security log | Module · OPS | HIGH | TODO | 010, 016 | 062, 063, 064, 065, 066, 067, 068, 069 | NO | AGENT | NONE | YES |
| [TASK-062](TASK-062.md) | Critical journeys A — the golden flow, purchasing, fulfilment, counts, scanning and SIPLAH | Verification · Verification | STANDARD | TODO | 004, 023, 024, 056, 057, 058, 059, 060, 061 | 066, 069, 070 | NO | AGENT | NONE | YES |
| [TASK-063](TASK-063.md) | Critical journeys B — finance, corrections, reports, documents, completion and the checklist | Verification · Verification | STANDARD | TODO | 004, 023, 024, 056, 057, 058, 059, 060, 061 | 066, 069, 070 | NO | AGENT | NONE | YES |
| [TASK-064](TASK-064.md) | Critical journeys C — isolation, sign-in, access, search, approvals, import, language, masters, command identity and status | Verification · Verification | STANDARD | TODO | 004, 023, 024, 056, 057, 058, 059, 060, 061 | 066, 069, 070, 081 | NO | AGENT | NONE | YES |
| [TASK-065](TASK-065.md) | UI/UX consistency gate, the responsive matrix and the anti-slop audit | Verification · Verification | STANDARD | TODO | 004, 023, 024, 056, 057, 058, 059, 060, 061 | 066, 069, 070 | NO | AGENT | NONE | YES |
| [TASK-066](TASK-066.md) | Accessibility — WCAG 2.2 AA across every page | Verification · Verification | STANDARD | TODO | 004, 023, 024, 056, 057, 058, 059, 060, 061, 062, 063, 064, 065 | 069, 070 | NO | AGENT | NONE | YES |
| [TASK-067](TASK-067.md) | Localization completeness and the per-page copy lists | Verification · Verification | STANDARD | TODO | 004, 023, 024, 056, 057, 058, 059, 060, 061 | 070 | NO | AGENT | NONE | YES |
| [TASK-068](TASK-068.md) | Performance targets and query budgets in CI | Verification · Verification | STANDARD | TODO | 004, 023, 024, 056, 057, 058, 059, 060, 061 | 069, 070, 079 | NO | AGENT | NONE | YES |
| [TASK-069](TASK-069.md) | Security test matrix, route integrity and zero dead interactions | Verification · Verification | HIGH | TODO | 004, 020, 023, 024, 056, 057, 058, 059, 060, 061, 062, 063, 064, 065, 066, 068 | 070 | NO | AGENT | NONE | YES |
| [TASK-070](TASK-070.md) | Full release regression and the device UAT's preparation | Verification · Verification | STANDARD | TODO | 062, 063, 064, 065, 066, 067, 068, 069 | 081, 086 | NO | AGENT | NONE | YES |
| [TASK-071](TASK-071.md) | The operator's inputs before staging | Operations · Operations | HIGH | BLOCKED — OPI-26 | — | 072, 075, 078, 082 | YES | HUMAN OPERATOR | NONE | NO |
| [TASK-072](TASK-072.md) | Deployment definition, health and smoke checks, maintenance mode and mixed-version safeguards | Release · Release | HIGH | BLOCKED — OPI-03, P11Q-02 | 003, 013, 022, 071 | 074, 075, 077, 078 | NO | AGENT | NONE | NO |
| [TASK-073](TASK-073.md) | Release process — branch protection, release tags, the release record and integrity report | Release · Release | HIGH | TODO | 003, 004 | 084 | NO | AGENT | NONE | NO |
| [TASK-074](TASK-074.md) | Backup tooling, the off-server copy and the drill procedures | Release · Release | HIGH | TODO | 013, 018, 021, 022, 029, 072 | 075, 076, 079, 081, 084 | NO | AGENT | NONE | NO |
| [TASK-075](TASK-075.md) | Monitoring — the business heartbeat, alerts and the uptime check | Release · Release | HIGH | BLOCKED — OPI-05 | 010, 013, 022, 058, 071, 072, 074 | 080 | NO | AGENT | NONE | NO |
| [TASK-076](TASK-076.md) | Operator runbooks — Owner-account recovery, key compromise, Scenario B and key rotation | Release · Release | HIGH | TODO | 013, 020, 021, 074 | 081, 083 | NO | AGENT | NONE | NO |
| [TASK-077](TASK-077.md) | Cutover tooling — the numbering go-live command, the post-import refresh and the rehearsal procedure | Release · Release | HIGH | BLOCKED — P11Q-04 | 021, 057, 072 | 081, 087 | NO | AGENT | NONE | NO |
| [TASK-078](TASK-078.md) | Staging stack, the two applications' isolation and the provider features | Operations · Operations | HIGH | TODO | 071, 072 | 079, 080, 081, 082, 086 | NO | HUMAN OPERATOR | NONE | NO |
| [TASK-079](TASK-079.md) | Staging measurements — capacity, Argon2id, durations and the disk budget | Operations · Operations | HIGH | TODO | 068, 074, 078 | 084 | NO | HUMAN OPERATOR | NONE | NO |
| [TASK-080](TASK-080.md) | Alert channel adopted and monitoring live | Operations · Operations | HIGH | TODO | 075, 078 | 081, 083 | NO | HUMAN OPERATOR | NONE | NO |
| [TASK-081](TASK-081.md) | Staging drills and rehearsals — failure, restore, Scenario B and the import rehearsal | Operations · Operations | HIGH | BLOCKED — DEP-05 | 064, 070, 074, 076, 077, 078, 080 | 084 | NO | HUMAN OPERATOR | NONE | NO |
| [TASK-082](TASK-082.md) | Google OAuth clients for staging and production | Operations · Operations | HIGH | TODO | 019, 071, 078 | — | YES | OWNER | NONE | NO |
| [TASK-083](TASK-083.md) | The alternate operator named and the dispute's governance recorded | Operations · Operations | HIGH | TODO | 076, 080 | 087 | YES | OWNER | NONE | NO |
| [TASK-084](TASK-084.md) | Production preparation — configuration, outbound HTTPS, branch protection, backups and the off-server copy | Operations · Operations | HIGH | BLOCKED — OPI-04, OPI-28, OPI-31, OPI-32 | 073, 074, 079, 081 | 085 | NO | HUMAN OPERATOR | NONE | NO |
| [TASK-085](TASK-085.md) | Installation, the first Owner, two-step enrolment and the APP_KEY rotation | Operations · Operations | HIGH | TODO | 020, 084 | 087 | NO | HUMAN OPERATOR | NONE | NO |
| [TASK-086](TASK-086.md) | UAT with the Owner and the validators | Operations · Operations | STANDARD | TODO | 070, 078 | 087 | NO | OWNER | NONE | NO |
| [TASK-087](TASK-087.md) | Cutover and go-live | Operations · Operations | HIGH | TODO | 057, 077, 083, 085, 086 | — | NO | HUMAN OPERATOR | NONE | NO |

Stage: Foundation, Platform, Module (the modules in their workflows' order), Verification, Release, Operations. Executor: AGENT, an Execution Agent under this plan's rules; HUMAN OPERATOR, the named operator; OWNER, the Owner — with the client where the Task says so. DB impact: PROPOSED for the five schema Tasks, which create every table of DATABASE §4 expand-only — TASK-005 also the infrastructure stores outside the 125 business tables that [PERMISSIONS_MATRIX §7.2](../05-security/PERMISSIONS_MATRIX.md#72-table-classification) names: the session table, the credential-token store of SECURITY AU-11, the export requests of CONCURRENCY_IDEMPOTENCY H6-04 and the failed-job table, but not the queue, cache, lock and job-batch tables it also names, their stores being Valkey and V1 dispatching no framework batch —, and for TASK-013, which adds C-50's trigger; NONE for every other Task, which uses those tables — a missing column is an executor discovery for Planning, never a silent schema change. A BLOCKED status names the input it waits on — an operator's, the Owner's or the client's input of [RELEASE_PLAN §9](../10-release/RELEASE_PLAN.md#9-the-go-live-checklist), a P11 question or DEP-05 (§2).

## 5. Milestones

No milestone has a date; none reduces scope or skips a check to be reached.

| Milestone | Tasks | Reached when |
| --- | --- | --- |
| M1 — Foundation | TASK-001–TASK-004 | the repository, its guardrails, CI with TEST_STRATEGY's layers and the verified tooling exist |
| M2 — Platform | TASK-005–TASK-022 | the schema, sign-in, authorization, projection, audit, the command envelope, numbering, jobs and files, localization, the design system and the shell are DONE |
| M3 — Access, master data, documents and cases | TASK-023–TASK-031 | the account and access pages, the masters, the document engine, evidence and correction cases are DONE |
| M4 — Projects and the commercial flow | TASK-032–TASK-035 | projects, quotations, confirmation with fulfillment planning and the administrative checklist are DONE |
| M5 — Purchasing and inventory | TASK-036–TASK-044 | costing, purchases, reservations, receiving, stock, dispatch, adjustments, counts and supplier returns are DONE |
| M6 — Fulfilment | TASK-045–TASK-048 | deliveries, direct shipment, service handover and client returns are DONE |
| M7 — Finance | TASK-049–TASK-054 | invoices, billing and receivables, payments, settlements, credit and other cash are DONE |
| M8 — Completion, import, home and reports | TASK-055–TASK-061 | completion, cancellation, the opening import, the home pages, search, reports and the logs are DONE |
| M9 — Verification | TASK-062–TASK-070 | the critical journeys, the UI/UX, accessibility, localization, performance and security suites and the full regression pass |
| M10 — Release readiness and staging | TASK-071–TASK-083 | the release tooling exists, staging has run its measurements, drills and rehearsals, the alert channel is live, and the Owner's Google clients and alternate operator are in place |
| M11 — Production and go-live | TASK-084–TASK-087 | production is prepared, installed and accepted in UAT, and the cutover and go-live of RELEASE_PLAN pass every go-live checklist item with its evidence |

## 6. Coverage

[FEATURE_COVERAGE_MATRIX](FEATURE_COVERAGE_MATRIX.md) holds the requirement inventory of SP-95 and maps each entry to its Task or Tasks, or to a recorded non-Task disposition; every Task maps at least one entry.

## 7. Controlled Task-plan changes

**Baseline, 2026-10-19 (run PC-5, DIR-084 SP-96 and SP-97):** 87 Tasks — 4 foundation, 18 platform, 39 module, 9 verification, 6 release and 11 operations Tasks; Baseline Total equal to Current Total. Later changes are recorded here, newest last, in AICWDF §22.6's form: reason, affected Tasks, new Tasks, the baseline, previous and new current totals, dependencies updated, scope change, Owner approval required.

**Change 1, 2026-10-20 (run PC-5, Part 4, before the Owner's approval; DIR-084 SP-99).** *Reason:* WF-PC5-W's writers found Tasks placed before work they need, two lookup routes owned by a Task that comes after the pages using them, and inputs no Task waited on — each finding a row of the P11 gate record's PC-5 review. *Affected Tasks:* the ones below. *New Tasks:* none. *Totals:* baseline 87, previous current 87, new current 87. *Dependencies updated:* TASK-030 before TASK-034 and TASK-036, so evidence exists before the inventory and fulfilment Tasks that record it; TASK-040 before TASK-042 and TASK-044; TASK-044 before TASK-048; TASK-052 before TASK-053 and TASK-054; TASK-044 and TASK-046 before TASK-054; TASK-043 and TASK-054 before TASK-055; TASK-055 before TASK-056; TASK-040, TASK-042 and TASK-052 before TASK-057; TASK-004 before TASK-062–TASK-069; TASK-020 before TASK-069. *Work moved:* the search request ROUTE-281, with INT-005 and INT-281, from TASK-059 to TASK-016 — the forms before PG-009 choose masters by lookup (IP-17) —; the scan lookup ROUTE-282, with INT-006, INT-282 and SF-SCAN, from TASK-059 to TASK-039, the first scan-led page; the pending-case branch of ROUTE-194 and ROUTE-195 to TASK-043; a few go-live items' later halves to the operator's Tasks that can run them (FEATURE_COVERAGE_MATRIX). *Statuses:* TASK-037, TASK-043, TASK-046, TASK-052, TASK-053, TASK-057 and TASK-072 BLOCKED on P11Q-02, TASK-037, TASK-043 and TASK-077 on P11Q-04, TASK-081 on DEP-05. *Scope change:* none (DIR-083 OE-20). *Owner approval required:* with P11 at OG-4.
**Change 2, 2026-10-20 (run PC-5, Part 5, before the Owner's approval; DIR-084 SP-100).** *Reason:* WF-PC5-R's review found Tasks whose risk class understated an area AICWDF §22.11 names, Tasks placed before the Tasks that produce what they test, verification Tasks waiting on each other's outputs both ways, inputs that gate execution named by no Task, and the human Tasks' BLOCKED rule applied two ways; the executor's fixes of its own contracts found four more producers; and the convergence review of the HIGH findings' fixes found two verification Tasks still reading outputs of Tasks they did not depend on, and the Owner's Tasks writing rows of approved documents as the operator's do — each finding a row of the P11 gate record's PC-5 review. *Affected Tasks:* the ones below. *New Tasks:* none. *Totals:* baseline 87, previous current 87, new current 87. *Risk class:* TASK-016, TASK-032, TASK-035 and TASK-059 HIGH (SP-96). *Dependencies updated:* TASK-009 before TASK-013; TASK-010 before TASK-075; TASK-013 before TASK-016, TASK-017, TASK-072, TASK-074, TASK-075 and TASK-076; TASK-015 before TASK-022; TASK-016 before TASK-017; TASK-017 before TASK-022; TASK-018 before TASK-021 and TASK-074; TASK-019 before TASK-020 and TASK-082; TASK-021 before TASK-074 and TASK-076; TASK-022 before TASK-072, TASK-074 and TASK-075; TASK-029 before TASK-074; TASK-034 before TASK-035; TASK-048 before TASK-053; TASK-058 before TASK-075; TASK-062–TASK-065 before TASK-066; TASK-062–TASK-066 and TASK-068 before TASK-069; TASK-064 and TASK-070 before TASK-081; TASK-074 before TASK-075; TASK-078 before TASK-082. *Work moved:* the entry of TASK-066's and TASK-067's evidence in TASK-065's gate record to TASK-070, which depends on them all, and the accessibility engine's run over the journeys' page states to TASK-066, which now depends on the journeys and the gate, so that no verification Task waits on an output it does not depend on; a minimal CI workflow into TASK-001, so that its DONE and TASK-002's have their fresh CI (DEFINITION_OF_DONE §1); the staging times of agent Tasks' criteria to the release-critical set's record — TASK-068's scripts, run in TASK-079 —, the uptime check's live state and record to TASK-080; HO-20's and HO-13's framework checks to TASK-013, TASK-017, TASK-022 and TASK-024; the identity steps' business audit to TASK-017–TASK-019; the infrastructure stores to TASK-005; C-50's trigger to TASK-013; each constraint the database enforces, index and migration rule to the schema Task that creates its tables. *Statuses:* no Task READY — TASK-001 BLOCKED on P11Q-01; TASK-071 on OPI-26, P11Q-01 and P11Q-05; TASK-078–TASK-080 and TASK-087 on P11Q-05, TASK-081 also on it; TASK-084 on P11Q-05 beside the clients' and the Owner's inputs, no longer on OPI-08 and OPI-23, which it records itself; TASK-020 on P11Q-06, TASK-085 on P11Q-05 and P11Q-06; TASK-055 on P11Q-02; the Owner's TASK-082, TASK-083 and TASK-086 on P11Q-05, which their entries in approved documents need, no longer on the inputs TASK-082 and TASK-083 record themselves. *Scope change:* none (DIR-083 OE-20). *Owner approval required:* with P11 at OG-4.

**Change 3, 2026-10-20 (the first execution session, before APPR-016 takes effect; [DIR-085](../00-governance/DECISION_LOG.md#dir-085-dir-086-obs-034-and-tech-041--og-4-decisions-appr-016-the-planning-freeze-and-the-execution-authorization) OF-01, OF-05 and OF-06; DIR-086 §3.3).** *Reason:* the pre-mortem of [RELEASE_PLAN §12.1](../10-release/RELEASE_PLAN.md#121-the-pre-mortem) (OF-01) found three items no Task held — the project runnable in both execution environments and the Task plan's integrity checked in CI (PMC-15), and each cause confirmed answered at the release regression (PMC-01–PMC-15) ([§12.2](../10-release/RELEASE_PLAN.md#122-what-the-mapping-showed-missing)) —; and the Owner's answers to P11Q-01, P11Q-05 and P11Q-06 are recorded with what they call for done — the pre-mortem run, nothing further for OF-05, OF-06's edit of TASK-085 made here as record 84 §3.3 directs (§8). *Affected Tasks:* TASK-001, TASK-002, TASK-020, TASK-070, TASK-071, TASK-078–TASK-087. *New Tasks:* none. *Totals:* baseline 87, previous current 87, new current 87. *Dependencies updated:* P11Q-01 removed from TASK-001 and TASK-071; P11Q-05 from TASK-071 and TASK-078–TASK-087; P11Q-06 from TASK-020 and TASK-085; no dependency between Tasks changed. *Acceptance criteria and tests (Level 1):* TASK-001's criteria 6 and 8 and its tests — the versions pinned and recorded for each environment the Task runs in, `DEVELOPMENT.md` for Linux and the Owner's Windows workstation, text files LF (record 84 §1.4, §3.6) —; TASK-002's criterion 7 and its test — the plan-integrity check in CI —; TASK-070's criterion 10 and its test — PMC-01–PMC-15 answered on the release candidate —; TASK-085's first-Owner step stated with TASK-020's command and its criterion 8, and TASK-020's first-Owner path, as OF-06 decides. *Statuses:* TASK-001 TODO, READY only after the freeze record once its tools are verified (record 84 §3.6); TASK-020, TASK-078–TASK-080, TASK-082, TASK-083 and TASK-085–TASK-087 TODO; TASK-071 BLOCKED on OPI-26 alone, TASK-081 on DEP-05 alone and TASK-084 on OPI-04, OPI-28, OPI-31 and OPI-32; TASK-037, TASK-043, TASK-046, TASK-052, TASK-053, TASK-055, TASK-057 and TASK-072 still BLOCKED on P11Q-02 and TASK-037, TASK-043 and TASK-077 on P11Q-04 until OF-02's change and the Owner's approval of OF-04's text (record 84 ER-09) — 13 BLOCKED and 74 TODO. *Matrix:* GAP-066, GAP-067 and GAP-068, OPEN now, entered with their Tasks; 3,237 entries, all mapped, no orphan Task, re-checked by script with the counters and the dependency graph. *Scope change:* none (DIR-083 OE-20). *Owner approval required:* none beyond APPR-016, which covers this change (OF-07).

## 8. Owner questions

P11's Owner questions, P11Q-nn, each with its options and one recommendation; nothing waits on an answer to keep planning. The Owner answered P11Q-01–P11Q-06 at OG-4 (DIR-085 OF-01–OF-06); each answer follows its question. A Task BLOCKED on a question becomes READY only once the Owner's answer is recorded and what it calls for is done — the step run, or the narrow amendment approved and the Task's contract updated to build what the answer adds, each held item already stated in the contract as a conditional IN SCOPE item with its acceptance criterion and tests (§2).

### P11Q-01 — The pre-mortem before execution

- **Question.** [AGENT_OPERATING_MODEL](../00-governance/AGENT_OPERATING_MODEL.md#continuous-adversarial-review) requires a pre-mortem before execution — assume the V1 release failed; for each plausible cause its warning signs, prevention, detection and recovery, with the Tasks, tests, security, release and operations plans updated —, and [RELEASE_PLAN §12](../10-release/RELEASE_PLAN.md#12-architecture-challenge-and-pre-mortem) places it, with the whole-system architecture challenge, "with P11, in PC-5", its evidence kept in that section. [DIR-084](../00-governance/DECISION_LOG.md#dir-083-dir-084-obs-033-and-tech-040--og-3-decisions-appr-012appr-015-run-pc-5-and-p11) authorizes the architecture challenge alone (SP-98), recorded in the [P11 gate record](evidence/P11_QUALITY_GATE.md#whole-system-architecture-challenge-sp-98), and no change to P0–P10 beyond SP-90–SP-94: run PC-5 can neither run the pre-mortem nor write RELEASE_PLAN §12, which still names itself as the challenge's place of record.
- **Options.** (a) One short planning step, authorized with or before the execution authorization at OG-4, runs the pre-mortem in RELEASE_PLAN §12's form, records it with the challenge's place — RELEASE_PLAN §12 by a narrow continuity edit, or the P11 gate record —, and applies its findings to the Tasks before TASK-001 starts; (b) the pre-mortem is the first step of the first execution session, before TASK-001, under the execution authorization; (c) the Owner waives it for V1, with the reason recorded.
- **Recommendation.** (a) — its findings can change Tasks, tests and the release plan, which is planning work that belongs before the freeze; it needs no tool, cost or access beyond the repository. TASK-001 and TASK-071 are BLOCKED on it. Its gap: [GAP-065](../00-governance/GAP_REGISTER.md#gap-065--the-pre-mortem-required-before-execution-has-not-been-run).
- **Decided — DIR-085 OF-01.** Option (a), as one short step of the first execution session, before the approval commit and TASK-001 — not a review cycle: the pre-mortem ran on 2026-10-20 and is recorded in [RELEASE_PLAN §12.1](../10-release/RELEASE_PLAN.md#121-the-pre-mortem), its additions in Change 3 (§7); TASK-001 and TASK-071 no longer wait on it, and GAP-065 is CLOSED.

### P11Q-02 — Rows the route and interaction contracts lack

- **Question.** WF-PC5-W's writers found behaviour the approved specifications require that has no row in [ROUTE_CONTRACTS](../03-workflows/ROUTE_CONTRACTS.md) or [INTERACTION_CONTRACTS](../03-workflows/INTERACTION_CONTRACTS.md), so no Task may build its control (RC-06; AGENTS.md rule 12): a charge recorded on a purchase after its first effect (DATABASE §6.1; AX-29; ARCHITECTURE §3's RecordPurchaseCharge — TASK-037); "Koreksi Dasar Alokasi", the allocation-basis correction's own control on PG-016 (ADMIN_FLOW §11, CM-35 — TASK-037); cancelling an open stock count (PERMISSIONS_MATRIX §4, `stock.count`; DATABASE §4.7 — TASK-043); the count sheet's print control on PG-026 (INFORMATION_ARCHITECTURE §8.3 — TASK-043); the company-issued *Surat Jalan* of a direct shipment from PG-032 (WF-FUL-03; WF-DOC-01 — TASK-046); the resolution of a residual settlement (ARCHITECTURE §3, ResolveResidualSettlement; AX-33 — TASK-052); holding project-originated customer credit with a reason, which also needs its column (WF-FIN-06; completion predicate 9 — TASK-053, TASK-055); the opening import's batch abort, "Batalkan Batch" (IMP-06; PRQ-06; ADMIN_FLOW §13 — TASK-057); and the unauthenticated health check and staging's CSP report endpoint (SECURITY EN-03, SX-03, WS-08 — TASK-072). None adds to V1: each implements a rule already approved.
- **Options.** (a) One narrow amendment adding these rows — with their LOCALIZATION keys, and for the credit hold its column — drafted by Planning and approved by the Owner before the affected Tasks start; (b) the Owner takes a behaviour out of V1 instead, each such removal a V1 decision under OE-20.
- **Recommendation.** (a). TASK-037, TASK-043, TASK-046, TASK-052, TASK-053, TASK-055, TASK-057 and TASK-072 are BLOCKED on it; each contract states its held behaviour as a conditional IN SCOPE item with its acceptance criterion and tests, built once the rows are approved, and every other part of the contracts stands. Its gap: [GAP-066](../00-governance/GAP_REGISTER.md#gap-066--behaviour-the-specifications-require-has-no-route-or-interaction-contract).
- **Decided — DIR-085 OF-02.** Option (a): one narrow amendment adds to ROUTE_CONTRACTS and INTERACTION_CONTRACTS the rows of the nine behaviours listed above, with their LOCALIZATION keys and, for the credit hold, the one column WF-FIN-06's rule needs, each row derived from the approved rule cited for it and adding nothing to V1 (OE-20); the Owner approved it within those words. It is made as a controlled change, Change 4, before the first Task it blocks becomes READY ([record 84](../00-governance/sources/EXECUTION_AUTHORIZATION_2026-10-20.txt) ER-09); a row that needs a choice its rule does not make becomes an Owner question and its Task stays BLOCKED. Until Change 4 is merged the Tasks above stay BLOCKED, and GAP-066 OPEN.

### P11Q-03 — Approved statements that disagree

- **Question.** The writers found approved statements that disagree, and each contract follows the stricter or governing one and reports the other, as [SOURCE_OF_TRUTH's conflict resolution](../00-governance/SOURCE_OF_TRUTH.md#conflict-resolution) asks: ROUTE-193 names `stock.count_apply` and the cut's `stock.reserve`, while AZ-08 and XL-1 also require `stock.allocate_intercompany` with a grant on the bearer company when a count loss's bearer differs (TASK-043); ROUTE-203 gives "Catat Hilang di Perjalanan" the identity family NX-15, while CONCURRENCY_IDEMPOTENCY §7.2 puts the reclassification among AX-30's facts and lock set (TASK-045); ROUTE-206 names AX-08 for a direct shipment's confirmation reversal, while §7.2 lists its guards under AX-10 with LK-24 (TASK-046); ROUTE-214 classes the contra of an opening receivable or credit NX-11, whose lock set lacks the LK-25–LK-27 that AX-20 and AX-33 hold (TASK-057); INFORMATION_ARCHITECTURE §8.3 lists IP-05's duplicate warning on PG-022, while BD-13 gives AX-04 none (TASK-039); ADMIN_FLOW §11 places a refund's contra on PG-049 or PG-048, while ROUTE-260 names PG-047 and PG-049 (TASK-053, TASK-054); and SM-21 expects the atomic end of a recovery that BACKUP_RECOVERY TT-F30 and GAP-050 recommend, while RV-18 is not amended for it (TASK-020, TASK-069).
- **Options.** (a) The Owner confirms the readings the contracts apply — AZ-08's capability with ROUTE-193, the lock set covering every guard the command writes (AX-30's, AX-10's and AX-20's and AX-33's classes), no duplicate warning on PG-022 (BD-13), the contra on ROUTE-260's pages, the atomic end of TT-F30 — and authorizes one narrow consistency edit of the subordinate statements; (b) the Owner decides a pair differently, and the affected Task follows the answer.
- **Recommendation.** (a) — each reading keeps the stricter guard, so no Task waits on it. Its gap: [GAP-067](../00-governance/GAP_REGISTER.md#gap-067--approved-statements-disagree-on-a-capability-a-lock-set-a-warning-a-controls-page-and-a-recoverys-end).
- **Decided — DIR-085 OF-03.** Option (a): the readings the contracts apply are confirmed — AZ-08's capability with ROUTE-193; each lock set covering every guard its command writes (AX-30's, AX-10's, AX-20's and AX-33's classes); no duplicate warning on PG-022 (BD-13); a refund's contra on ROUTE-260's pages; the atomic end of a recovery of TT-F30 —, and one narrow consistency edit of the subordinate statements is made with OF-02's change (Change 4). GAP-067 OPEN until then.

### P11Q-04 — Facts the approved documents use but do not define

- **Question.** A rule depends on a fact no document defines: what makes a non-stock purchase line "performed", which AX-12's cancellation guard reads (WF-PUR-01; DATABASE §4.6 — TASK-037); how a registration count assigns a serial to every unit on hand before a product becomes serialized — its movement, serial transition and posting (L-35; WF-MD-04; DATABASE §5.3, §8 — TASK-043); and where the go-live setting is stored and which actor the audit event of its one-time command carries (INFRASTRUCTURE §4.2; HO-32; PRQ-07; RG-11 — TASK-077).
- **Options.** (a) One narrow amendment defining each — the performed fact and its column, the registration count's movement and serial transition, the setting's store and the event's actor —, drafted by Planning and approved by the Owner before the affected Tasks start; (b) the Owner decides each differently.
- **Recommendation.** (a). TASK-037, TASK-043 and TASK-077 are BLOCKED on it; each contract states the held fact's use as a conditional IN SCOPE item with its acceptance criterion and tests. Its gap: [GAP-068](../00-governance/GAP_REGISTER.md#gap-068--facts-a-rule-depends-on-that-no-approved-document-defines).
- **Decided — DIR-085 OF-04.** Option (a): the executor drafts, with OF-02's change, one narrow REVIEW amendment defining the three facts — the performed fact of a non-stock purchase line and its column, the registration count's movement and serial transition, the go-live setting's store and its event's actor —, each the minimum its cited rules need; it takes effect only on the Owner's separate approval of its text, given before TASK-037, TASK-043 or TASK-077 starts, and those three Tasks stay BLOCKED until then; nothing else waits on it. GAP-068 OPEN.

### P11Q-05 — Recording the operator's facts in approved documents

- **Question.** [INFRASTRUCTURE §9](../09-operations/INFRASTRUCTURE.md#9-the-operators-inputs) defines "recorded" as written into the document itself, so the human operator's and the Owner's Tasks record their inputs and measurements by changing rows of INFRASTRUCTURE §9, BACKUP_RECOVERY §3 and §7, RELEASE_PLAN §9 and COST_POLICY's inventory, all APPROVED. No source says whether such a fact entry is a Level 1 continuity record, or who writes it for a Task no agent runs — the operator's TASK-071, TASK-078–TASK-081, TASK-084, TASK-085 and TASK-087, and the Owner's TASK-082, TASK-083 and TASK-086.
- **Options.** (a) Each fact entry is a Level 1 continuity record under [CHANGE_CONTROL](../00-governance/CHANGE_CONTROL.md), written from the operator's or the Owner's own record in a session the Owner authorizes for it, without a secret, its hash in the decision log; (b) the facts are kept in one operator register outside the approved documents, which point to it.
- **Recommendation.** (a) — each fact keeps one owning document, and no agent runs the operator's Task. Those ten Tasks are BLOCKED on it. It changes no P0–P10 rule — the facts are those the approved documents already ask for, only how and by whom they are entered is open —, so no gap is opened for it.
- **Decided — DIR-085 OF-05.** Option (a): each fact an operator's or the Owner's Task records in an approved document is a Level 1 continuity record under CHANGE_CONTROL, written by the executor from that person's own record in an execution session where the record is given to it, without a secret or host detail, its hash in the decision log; the decision authorizes it for every such session (record 84 ER-10), so nothing more is called for, and the ten Tasks no longer wait on it (Change 3).

### P11Q-06 — The first Owner's password

- **Question.** The installation step creates the first Owner with its audit event only ([INFRASTRUCTURE §5](../09-operations/INFRASTRUCTURE.md#5-the-deployment-path); RELEASE_PLAN PRQ-09; SECURITY_TEST_MATRIX SM-24), and no approved document states how that Owner then sets a password: [SECURITY](../05-security/SECURITY.md#2-authentication-sessions-and-credentials) AU-12's credential links are issued by an Owner, and AU-14's OWNER_RECOVERY link is the last-resort recovery path (K3). Run PC-5's review found TASK-085 relying on a flow TASK-020 did not build.
- **Options.** (a) The operator issues the first Owner's OWNER_RECOVERY link through AU-14's command right after the installation step, with the Owner present, who opens it on the Owner's own device; the operator's statement of why is recorded in the issue's audit event, and AU-14's alerts follow — no new mechanism; (b) the installation step issues a one-time onboarding link (AU-11) for the first Owner — a narrow amendment of INFRASTRUCTURE §5 and SECURITY AU-12, built by TASK-020.
- **Recommendation.** (a) — it uses an approved, audited and alerted mechanism, needs the Owner present as AU-14 does, and adds nothing to V1; TASK-020 and TASK-085 then stand as written, TASK-085 stating the step with TASK-020's command. TASK-020 and TASK-085 are BLOCKED on it. Its gap: [GAP-069](../00-governance/GAP_REGISTER.md#gap-069--the-first-owners-way-to-set-a-password-is-not-stated).
- **Decided — DIR-085 OF-06.** Option (a): the operator issues the first Owner's OWNER_RECOVERY link through AU-14's command right after the installation step, with the Owner present, who opens it on the Owner's own device; the operator's reason is recorded in the issue's audit event, and AU-14's alerts follow; no new mechanism. TASK-085 states the step with TASK-020's command, edited in Change 3 as record 84 §3.3 directs; TASK-020 and TASK-085 no longer wait on it, and GAP-069 is CLOSED.

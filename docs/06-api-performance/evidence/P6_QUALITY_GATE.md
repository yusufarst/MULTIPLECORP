# P6 concurrency, idempotency and performance quality gate

Status: REVIEW | Updated: 2026-10-01 | Owner: Planning

Scope: P6 — Concurrency, Idempotency & Performance, authorized and fast-tracked by [DIR-032](../../00-governance/DECISION_LOG.md#dir-032-obs-011-and-tech-021--p6-authorization-entry-baseline-and-concurrency-documentation) (twenty-eighth source record) on the published P5 checkpoint `b09e70f3a867d58b431c7ae0369b7a432c4fd1a1`. This report records planning evidence for [CONCURRENCY_IDEMPOTENCY](../CONCURRENCY_IDEMPOTENCY.md), [PERFORMANCE](../PERFORMANCE.md) and [API_AND_INTEGRATIONS](../API_AND_INTEGRATIONS.md), for the narrow `TECH-021` amendments of [DATABASE](../../04-architecture/DATABASE.md), [ARCHITECTURE](../../04-architecture/ARCHITECTURE.md), [WORKFLOWS](../../03-workflows/WORKFLOWS/README.md) and [PERMISSIONS_MATRIX](../../05-security/PERMISSIONS_MATRIX.md) and for the APPR-007 approval conditions. The three specifications are APPROVED under [APPR-007](../../00-governance/DECISION_LOG.md#appr-007--p6-concurrency-idempotency-and-performance-approved) together with the `TECH-021`-amended revisions; this gate stays REVIEW as evidence, following the P2–P5 gate convention. It is not implementation acceptance and not permission to begin P7. No application, measurement, concurrency-test, load, runtime or device evidence exists or is claimed.

## Baseline verification (OBS-011)

Verified before any edit: repository root `C:\Projects\MultipleCorp` with origin `https://github.com/yusufarst/MULTIPLECORP.git`; branch `main` tracking `origin/main`; after `git fetch origin`, HEAD, the local origin/main ref and live origin/main (`git ls-remote origin refs/heads/main`) all equal `b09e70f3a867d58b431c7ae0369b7a432c4fd1a1` (`docs: finalize P5 security and authorization`, parent `6e640137901e0d16193e03004e142e9ea07b39ad`); ahead/behind 0; `git diff --ignore-cr-at-eol --stat` empty, empty index, no untracked file; no merge, rebase, cherry-pick, revert or bisect state; no CONCURRENCY_IDEMPOTENCY, PERFORMANCE, API_AND_INTEGRATIONS, P7+ or application file; OWNER_DECISION_REQUIRED = 0; gaps 33 — 3 CLOSED, 29 OPEN, 0 OWNER_DECISION_REQUIRED, 1 ACCEPTED_RISK; all 27 source files matched SOURCE_OF_TRUTH; the three APPR-006 hashes equalled the committed blobs. Informational: AGENT_OPERATING_MODEL still carried "The current assignment authorizes P1 only". The repository matched the Owner's expected baseline, so work proceeded.

## Inputs read

The directive (source record 28) in full, with its section 12 planner notes treated as analysis only; the DIR-032 §4 intake files (AGENTS, CLAUDE, README, CONTEXT_INDEX, CURRENT_STATE, NEXT_ACTION, SOURCE_OF_TRUTH, DECISION_LOG, GAP_REGISTER, CHANGE_CONTROL, AGENT_OPERATING_MODEL, ENGINEERING_PRINCIPLES, PROJECT_CHARTER); the approved inputs P6 consumes — DATABASE (§1, §3 and §3.1, §4, §5–§13, §17–§28, §31), ARCHITECTURE (in full), WORKFLOWS (§1, §2, §4, §7–§13 and the workflows and L-decisions the sequences cite), the BUSINESS_RULES, PERMISSIONS_MATRIX and SECURITY rows DIR-032 names, V1_SCOPE (DEP-01–DEP-09), ACCEPTANCE_CRITERIA (AC-08, AC-10, AC-16, AC-22, AC-23), the gaps DIR-032 names, the Owner brief §14, §27–§35 and §40–§41, ADR-001 and the P4 and P5 gates; and the current official documentation below.

## Framework and version evidence

Read on 2026-09-30 from official sources and recorded in the three evidence tables ([concurrency](../CONCURRENCY_IDEMPOTENCY.md#source-and-version-evidence), [performance](../PERFORMANCE.md#source-and-version-evidence), [routes](../API_AND_INTEGRATIONS.md#source-and-version-evidence)): the PostgreSQL manual, current at 18.6 (transaction isolation, explicit and row locking, advisory locks, timeouts, deadlocks, INSERT with ON CONFLICT, constraint triggers and SET CONSTRAINTS, error codes, EXPLAIN, pg_trgm, BRIN, partitioning, indexes), the 9.3 and 8.2 release notes and the source notes `README.tuplock`; Laravel 13.x at 13.34.0 (database transactions, pessimistic locking, queues, job batches, cache locks, scheduling, rate limiting, lazy-loading prevention, the database session driver, pagination, deployment); Inertia v3 at 3.7.1 (partial reloads, deferred and optional props, polling, the protocol). Each table says whether a fact is documented, source-level or inferred. The third reviewer re-checked 38 claims against the official pages on the same date: one was wrong and was corrected (G-32), several were labelled "documented" although they are source-level and were relabelled (G-33), and three Laravel details could not be re-fetched by the reviewer and rest on the intake reading. The second verifier re-checked the tables after the fixes and confirmed every claim it could reach. The pages the verification round added — session-level advisory locks and the wording of `lock_timeout`, pattern operator classes, `btree_gin`, automatic password rehashing, the `WithoutOverlapping` job middleware and the release of a unique-job lock — were read on the same date. Every source-level or inferred fact is a P8 proof obligation (HO-14, HO-20), and versions are re-verified when they are chosen (DEP-07; HO-30).

## Planner notes — dispositions

The section 12 notes of DIR-032 are analysis, never Owner intent; each was verified against the repository, which wins where they differ.

| Note | Disposition | Where |
| --- | --- | --- |
| PN-1 revocation linearization | Adopted in its structure — the actor's and the targets' user rows are one lock class, locked at its canonical position in ascending id — but with one lock mode instead of the proposed pair: a shared lock for the actor gives a waiting revocation no place in a queue (README.tuplock; findings A-02, B-08), and FOR UPDATE would block foreign-key checks. Checked against AZ-03: a command that holds the row first stands, a later one is denied on its next request. | D-CC-02; LK-03; RV-01–RV-03 |
| PN-2 `command_log` placement and terminal outcomes | The insert as the first statement of the business transaction is adopted. The separate short transaction after a rollback is **not** the primary mechanism: a savepoint directly after the identity row lets the same transaction commit the REJECTED or CONFLICT outcome, so no window exists in which a waiting duplicate could run again before the outcome is recorded. The separate transaction remains only as the fallback for a deferred check that fails at COMMIT itself. | D-CC-05; section 6; SQ-05 |
| PN-3 warnings under concurrency | Adopted: the L-49 warning is re-checked under the project line, the case or the scope, the payment warning under the company bank account row; each stays a warning with its approved override. The FL-10 lookups stay outside the business transaction: the registration request takes its session-level keys, runs the lookups and only then opens its transaction. | BD-02, BD-13, BD-15; D-CC-08, D-CC-09; FU-10 |
| PN-4 stock = 1 | Adopted: both dispatches meet on the product guard row; the loser re-reads under the lock and is REJECTED as SF-CMD step 4 defines; the CHECKs are the backstop. | LK-13; ST-06; M-01 |
| PN-5 numbering | Adopted: the counter is the last lock; first use by insert-or-lock; every issuing action holds its project rows first. Strengthened beyond the note: the allocator fails closed — the first counter of a company and type is always a seed, and first use is allowed only for a live period, one that begins after the seed boundary and does not lie wholly before go-live. | NM-01–NM-03; FU-07 |
| PN-6 AX volume | Adopted: one envelope, two tables, a mechanical acyclicity check over the declared lock sets and a conformance check for the data-dependent ones. | sections 6 and 7; [Static validation](#static-validation) |
| PN-7 timeouts | Adopted: a short lock wait for interactive commands, longer ones for cascades, driven commands and access changes; P9 owns the values. | TX-01–TX-10; RY-09 |
| PN-8 password-verification cap | The atomic-lock limiter on the existing limiter store is chosen — no new dependency, fail closed like the login limiters; the database-backed counter is not used. Two limiters instead of one, so a login flood cannot take the slot of a step-up. | H6-12; D-CC-10 |

## Required coverage (DIR-032 §8)

| Item | Designed in | Result |
| --- | --- | --- |
| A. Transaction and isolation model | CC §2 and §3; D-CC-01–D-CC-12; TX-01–TX-10 | COVERED — READ COMMITTED confirmed; each stronger mechanism named with its reason; no SERIALIZABLE; advisory locks only as the registration keys of evidence, in the 7101 and 7102 namespaces; short transactions |
| B. Canonical lock order | CC §4: LR-01–LR-12, LK-00–LK-31 | COVERED — the DATABASE §26 order kept and extended; every §3.1 guard and every editable or state-bearing family has a position; user rows are one class at LK-03; the order is acyclic over the declared sets |
| C. Per-AX statement sequences | CC §5, §6, §7.1, §7.2, §7.3 | COVERED — 37 of 37, 20 STATIC and 17 DATA-DEPENDENT; the project row at position 1; the five-step protocol for every data-dependent set; the candidate snapshot under the scope and lot locks |
| D. Guards | CC §8: GU-01–GU-07, FU-01–FU-13 | COVERED — a guard update that affects too few rows is a failure; insert-or-lock with a proof of first use; nightly verify-only reconciliation |
| E. Stale state | CC §9: ST-01–ST-09 | COVERED |
| F. Command identity and replay | CC §10: CI-01–CI-09 and the replay table | COVERED — terminal REJECTED and CONFLICT outcomes are durable |
| G. Separate duplicates | CC §11: BD-01–BD-18 | COVERED — every warning stays a warning |
| H. SQLSTATE to outcome | CC §12: SQ-01–SQ-23 | COVERED — no new outcome vocabulary |
| I. Retry and timeouts | CC §13: RY-01–RY-09 | COVERED |
| J. Numbering concurrency | CC §14: NM-01–NM-10 | COVERED |
| K. Jobs, scheduler and queue | CC §15: JB-01–JB-09 | COVERED |
| L. H6-01–H6-12 | CC §17 | COVERED — 12 of 12 |
| M. Authorization and revocation races | CC §16: RV-01–RV-10 | COVERED |
| N. Performance | PERFORMANCE §1–§13: AS-01–AS-08, D-PF-01–D-PF-08, PF-01–PF-43, QB-01–QB-17, CP-01–CP-07, GT-01–GT-07, IX-01–IX-17, SZ-01–SZ-09 | COVERED — budgets and sizes as assumptions with a verification procedure; nothing measured |
| O. Handoff obligations | CC §19: HO-01–HO-39 | COVERED — P7, P8, P9, P10 and one P11 item |
| P. Integration seam | API_AND_INTEGRATIONS: RB-01–RB-09, IC-01–IC-14 | COVERED — no integration invented |

## Obligation closure

Status values: DESIGNED (the P6 design exists in the named place); HANDED TO a later phase with its obligation; N/A with reason. "CC" is CONCURRENCY_IDEMPOTENCY. No gap is closed: every runtime proof belongs to P8, P9 or P10.

| Item | P6 home | Status |
| --- | --- | --- |
| DATABASE §31 — statements, lock order and retry policy for every guard and AX, corrected actions included | CC §4 (LK-00–LK-31), §7.1, §7.2, §13 (RY-01–RY-09) | DESIGNED; proof HANDED TO P8 (HO-11, HO-13) |
| DATABASE §31 — the completion anchor | LK-01; D-CC-04; AX-25 and AX-26 in CC §7 | DESIGNED |
| DATABASE §31 — zero-row guard updates treated as failure | GU-02; SQ-18; M-29 | DESIGNED |
| DATABASE §31 — race-safe creation of first-use guard and counter rows | FU-01–FU-13; D-CC-07; M-16, M-30 | DESIGNED |
| DATABASE §31 — `command_log` protocol and retention | CI-01–CI-09; D-CC-05; JB-08; the `TECH-021` clauses of DATABASE §4.13 and §19 | DESIGNED; retention values and the purge's operation HANDED TO P9 (HO-27) |
| DATABASE §31 — SQLSTATE → outcome mapping including deferred-trigger failures | SQ-01–SQ-23 | DESIGNED |
| DATABASE §31 — the render sweeper | JB-01, JB-02 | DESIGNED; monitoring HANDED TO P9 (HO-25) |
| DATABASE §31 — count staleness | ST-03; M-04 | DESIGNED |
| DATABASE §31 — sequence allocation | NM-01–NM-10; FU-07 | DESIGNED; seeding order, legacy schemes and the go-live record HANDED TO P10 (HO-32), the start and seed confirmations to P7 (HO-39) |
| DATABASE §31 — the cross-row preconditions of §26 under their anchors | CC §7.2, closing paragraph | DESIGNED |
| DATABASE §31 — reconciliation cadence driven by the §3.1 registry | CC §8, reconciliation cadence; JB-06 | DESIGNED; scheduling and the post-restore run HANDED TO P9 (HO-28) |
| DATABASE §31 — partitioning thresholds (open technical item) | PERFORMANCE GT-01–GT-03, GT-07 | DESIGNED as candidates with thresholds; measurement HANDED TO P8 (HO-16) and P9 |
| DATABASE §26 — command identity: replay, in-flight handling, retention | CI-03–CI-08 and the replay table | DESIGNED |
| DATABASE §26 — business identity of each AX, the AX-04 receiving identity included | BD-01–BD-13, BD-17, BD-18 | DESIGNED |
| DATABASE §26 — stale state | ST-01–ST-09 | DESIGNED |
| DATABASE §26 — guards as lock anchors: the proposed order, isolation, bounded retry, the 23514 and 23505 mapping | CC §4.2 (the eighteen families kept in order and extended); D-CC-01; RY-01, RY-02; SQ-04, SQ-05, SQ-07, SQ-08 | DESIGNED |
| DATABASE §26 — obligations deferred by the targeted review | GU-02; FU-01–FU-13; LK-01; CC §7 | DESIGNED |
| DATABASE §26 — cross-row preconditions that remain application checks | completion predicates under LK-01 (AX-25); the pre-payment Kuitansi cap under LK-25 (AX-15, AX-31); an evidence resolution under LK-11 and LK-13–LK-15 (AX-37); the reservation-cut choice under LK-13 and LK-18 | DESIGNED |
| ARCHITECTURE §20 — lock order, isolation, retry | CC §4, §3, §13 | DESIGNED |
| ARCHITECTURE §20 — command-log replay | CC §10 | DESIGNED |
| ARCHITECTURE §20 — render sweeper cadence; guard verification cadence | JB-02; CC §8 and JB-06 | DESIGNED |
| ARCHITECTURE §5 — SF-CMD steps 4 and 6 and the outcome mapping | CC §6 phases A–E; §10; §12; the `TECH-021` clause of ARCHITECTURE §5 | DESIGNED |
| ARCHITECTURE §11 — the six jobs and deterministic command ids | JB-01–JB-06, with JB-07–JB-09; CI-01, CI-08; the `TECH-021` clauses of ARCHITECTURE §11 | DESIGNED |
| ARCHITECTURE §14 — materialized summaries only when measurements justify them | D-PF-07 | N/A in V1 — nothing is measured yet and none is introduced |
| ARCHITECTURE §15 — a lost response after commit | SQ-17; CI-05; M-10 | DESIGNED |
| "Measured" sentences — DATABASE §20 and §27, ARCHITECTURE §14 and §20, SECURITY WS-12 and H6-07 | PERFORMANCE boundary, QB-01–QB-17 and §12; CC H6-07 row | DESIGNED as budgets and starting values stated as assumptions with their verification procedure, as DIR-032 §7-B directs — no application exists to measure; measurement HANDED TO P8 (HO-16, HO-17) |
| P4_QUALITY_GATE — deferred P6 obligations (extended lock order, completion anchor, zero-row guard update, first-use rows, corrected AX sequences) | as the DATABASE §26 rows above | DESIGNED |
| P4_QUALITY_GATE P4-F21 — chunking of the opening import | JB-07, JB-09; AX-28 | DESIGNED; rehearsal and resume HANDED TO P10 (HO-33) |
| P4_QUALITY_GATE — exact SQL of triggers, deferred constraint triggers and column grants | the behaviour the mechanisms rely on: SQ-05, SQ-11; CC §3 | N/A in P6 — DIR-032 allows no executable SQL; behaviour proof HANDED TO P8 (HO-14), roles and grants to P9 (H9-01), the statements to execution |
| SECURITY H6-01 | RV-01; LK-03; RY-05 | DESIGNED |
| SECURITY H6-02 | RV-04; LK-02 | DESIGNED |
| SECURITY H6-03 | CI-03, CI-05; RV-09 | DESIGNED |
| SECURITY H6-04 | JB-03, JB-09; RV-07, RV-08; the `export_requests` record, named by the `TECH-021` clauses of DATABASE §4 and PERMISSIONS_MATRIX §7.2 | DESIGNED; cleanup HANDED TO P9 (HO-29) |
| SECURITY H6-05 | PERFORMANCE CP-01–CP-07 | DESIGNED |
| SECURITY H6-06 | RV-05, RV-06, RV-10 | DESIGNED; marking of background requests HANDED TO P7 (HO-06) |
| SECURITY H6-07 | CC §17 row H6-07; SQ-21 | DESIGNED; measured limit values HANDED TO P8 (HO-17) and P9 |
| SECURITY H6-08 | PERFORMANCE PF-19–PF-23, PF-41, IX-01–IX-17 and the §12 procedure | DESIGNED; plans HANDED TO P8 (HO-16) |
| SECURITY H6-09 | BD-15; NX-07; FU-10 — the lookups run before the registration transaction opens | DESIGNED; proof of the keys HANDED TO P8 (HO-14), request-scoped sessions to P9 (HO-22) |
| SECURITY H6-10 | FU-13 | DESIGNED |
| SECURITY H6-11 | CC §17 row H6-11; RV-10; PERFORMANCE IX-04 | DESIGNED |
| SECURITY H6-12 | CC §17 row H6-12; PERFORMANCE PF-39 | DESIGNED; Argon2id and slot tuning HANDED TO P9 (HO-26) |
| SECURITY WS-08 — whether a UI primitive needs `'unsafe-inline'` styles (P6 or P11) | — | N/A in P6 — no UI exists to examine; HANDED TO P11 (HO-37) |
| WORKFLOWS §13 — mechanisms for AX-01–37 | CC §7.1, §7.2 | DESIGNED |
| WORKFLOWS §13 — stale-state detection | ST-01–ST-09 | DESIGNED |
| WORKFLOWS §13 — retry identities | CI-01–CI-09 | DESIGNED |
| WORKFLOWS §13 — likely-duplicate warnings (L-49) | BD-13 | DESIGNED; the confirmation step HANDED TO P7 (HO-05) |
| WORKFLOWS §13 — atomic recognition and resolution of pending unexplained-loss cases without retention | AX-06, AX-07, AX-37; CC §5; M-06 | DESIGNED |
| WORKFLOWS §13 — render durability | JB-01, JB-02; M-31, M-32 | DESIGNED |
| WORKFLOWS §13 — count staleness | ST-03 | DESIGNED |
| WORKFLOWS §13 — sequence allocation | NM-01–NM-10 | DESIGNED |
| WORKFLOWS §11 and PERMISSIONS_MATRIX AZ-08 — a refusal that is routed (QS-20) | SQ-23; RV-01; the `TECH-021` clause of DATABASE §23 | DESIGNED; presentation HANDED TO P7 (HO-01) |
| SF-CMD step 6 — replay semantics and separate duplicates | CI-05 replay table; D-CC-05; BD-01–BD-18 | DESIGNED |
| C-02 ⚡ one serial identity, never in stock twice | LK-17; FU-04; BD-18; AX-03, AX-04, AX-08; M-05 | DESIGNED; race test HANDED TO P8 (HO-11) |
| C-03 ⚡ non-negative lot, scope and product quantities | LK-13–LK-15; GU-01, GU-03; AX-03, AX-05, AX-06, AX-11, AX-30, AX-37; M-01, M-06 | DESIGNED; HANDED TO P8 (HO-11) |
| C-04 ⚡ AVAILABLE ≥ 0 | LK-13; ST-06; AX-01, AX-03; M-01–M-03 | DESIGNED; HANDED TO P8 (HO-11) |
| C-05 ⚡ reservation bounds | LK-18, LK-19; FU-09; AX-01, AX-02, AX-13, AX-14; M-02, M-03 | DESIGNED; HANDED TO P8 (HO-11) |
| C-10 ⚡ numbers unique, monotonic, never reused | LK-31; FU-07; NM-01–NM-10; M-15–M-19 | DESIGNED; HANDED TO P8 (HO-11, HO-19) |
| C-13 ⚡ applications and refund backing within real money | LK-26; AX-18, AX-19, AX-20, AX-23; M-14 | DESIGNED; HANDED TO P8 (HO-11) |
| C-15 ⚡ outstanding ≥ 0 | LK-25; AX-19, AX-21, AX-22, AX-24; M-37 | DESIGNED; HANDED TO P8 (HO-11) |
| C-18 ⚡ evidence amount caps | LK-29; BD-08; AX-21, AX-29 | DESIGNED; HANDED TO P8 (HO-11) |
| C-19 ⚡ Cash-In per bank statement line | LK-29; BD-03; AX-18; M-11 | DESIGNED; HANDED TO P8 (HO-11) |
| C-20 ⚡ source-bill cap | LK-29; BD-17; AX-29, AX-34 | DESIGNED; HANDED TO P8 (HO-11) |
| C-24 ⚡ loss recognized once per lost unit | LK-15, LK-17, LK-24; BD-13; AX-30 | DESIGNED; HANDED TO P8 (HO-11) |
| C-27 ⚡ restored, reclassified and substituted ≤ consumed | LK-24; AX-10, AX-35, AX-36 | DESIGNED; HANDED TO P8 (HO-11) |
| C-29 ⚡ receipts, closures and confirmations ≤ ordered | LK-21; BD-01; AX-04, AX-08, AX-12, AX-32; M-35, M-36 | DESIGNED; HANDED TO P8 (HO-11) |
| C-30 ⚡ delivered ≤ shipped, handed over ≤ demand | LK-19; AX-09; NX-15 | DESIGNED; HANDED TO P8 (HO-11) |
| C-31 ⚡ project invoice cap | LK-20; AX-13, AX-16, AX-22 | DESIGNED; HANDED TO P8 (HO-11) |
| C-33 ⚡ stale counts never applied | ST-03; LK-12, LK-14; AX-06; M-04 | DESIGNED; HANDED TO P8 (HO-11) |
| C-35 ⚡ pending unattributed quantity ≥ 0, no double effect | LK-11; BD-12; AX-06, AX-07, AX-37; NX-11; M-06 | DESIGNED; HANDED TO P8 (HO-11) |
| C-39 ⚡ completion only with every predicate true at commit | LK-01; AX-25; CC §5 (document changes); M-26 | DESIGNED; HANDED TO P8 (HO-11) |
| C-43 ⚡ pre-payment Kuitansi cap and links | LK-25; AX-15, AX-31 | DESIGNED; HANDED TO P8 (HO-11) |
| C-44 ⚡ refund backing | LK-26, LK-27; AX-19, AX-20, AX-23, AX-33; M-14 | DESIGNED; HANDED TO P8 (HO-11) |
| C-57 ⚡ corrected cost conservation | LK-21–LK-23; AX-34 under CC §5; M-07 | DESIGNED; HANDED TO P8 (HO-11) |
| C-41 and C-48 (P6 layer without ⚡) | BD-07 and AX-17; CI-07, CI-08, SQ-07 | DESIGNED |
| §3.1 `stock_product_balances` | LK-13 | DESIGNED |
| §3.1 `stock_scope_balances` | LK-14; FU-01 | DESIGNED |
| §3.1 `stock_lot_balances` | LK-15; FU-02 | DESIGNED |
| §3.1 `stock_reversal_balances` | LK-16; FU-03 | DESIGNED |
| §3.1 `stock_consumption_balances` | LK-24 | DESIGNED |
| §3.1 `serial_units` | LK-17; FU-04 | DESIGNED |
| §3.1 `reservations` | LK-18; FU-09 | DESIGNED |
| §3.1 `project_line_balances` | LK-19 | DESIGNED |
| §3.1 `project_commercial_balances` | LK-20 | DESIGNED |
| §3.1 `purchase_line_balances` | LK-21 | DESIGNED |
| §3.1 `purchase_charge_balances` | LK-22 | DESIGNED |
| §3.1 `purchase_charge_share_balances` | LK-22; FU-05 | DESIGNED |
| §3.1 `lot_cost_balances` | LK-23 | DESIGNED |
| §3.1 `evidence_balances` | LK-29 | DESIGNED |
| §3.1 `invoice_balances` | LK-25 | DESIGNED |
| §3.1 `application_source_balances` | LK-26 | DESIGNED |
| §3.1 `money_fact_balances` | LK-27 | DESIGNED |
| §3.1 `unattributed_loss_cases` counters | LK-11 | DESIGNED |
| §3.1 `unattributed_loss_exposures` | LK-11 | DESIGNED |
| §3.1 `unattributed_loss_group_exposures` | LK-11; FU-06 | DESIGNED |
| §3.1 `correction_cases` counters | LK-10 | DESIGNED |
| §3.1 `number_sequences` | LK-31; FU-07 | DESIGNED |
| AX-01–AX-37 | CC §7.1 (lock set, type, first-use rows, project rows) and §7.2 (re-read, stale check, guard statements, facts, identity, deferred checks, refusals, result and retry class) | DESIGNED — 37 of 37; tests HANDED TO P8 (HO-11) |
| M-01–M-38 | CC §18; re-tested below | DESIGNED — 38 of 38; tests HANDED TO P8 (HO-11, HO-12) |
| GAP-003 | AX-03, AX-10, AX-30, AX-37; SQ-23 | DESIGNED; fixtures HANDED TO P8 |
| GAP-004 | AX-34, AX-20, AX-18, AX-19; M-07, M-14 | DESIGNED; numeric and concurrency fixtures HANDED TO P8 |
| GAP-005 | CC §6, §7, ST-07 | DESIGNED; CM-row tests HANDED TO P8 |
| GAP-006 | H6-04 and H6-05 rows; RV-07, RV-08; PERFORMANCE PF-13, PF-22, PF-41, CP-01–CP-07 | DESIGNED; denial tests HANDED TO P8 |
| GAP-007 | JB-01, JB-02; NM-07; M-31, M-32 | DESIGNED; crash tests HANDED TO P8 (HO-18), monitoring to P9 (HO-25) |
| GAP-008 | CC §10, §11; M-08–M-13 | DESIGNED; replay tests HANDED TO P8 (HO-15) |
| GAP-009 | CC §4; ST-03, ST-09; M-01–M-05 | DESIGNED; race tests HANDED TO P8 (HO-11) |
| GAP-010 | AX-28; JB-07, JB-09; CI-01, CI-08; M-33 | DESIGNED; rehearsal, counter seeds and cutover HANDED TO P10 (HO-32, HO-33) |
| GAP-012 | CC §15 payload versioning | DESIGNED as an input; topology HANDED TO P9, runbook to P10 (HO-31) |
| GAP-013 | SQ-21; CC §15; H6-07 and H6-12 rows; JB-09; M-34 | DESIGNED; alert delivery and monitoring HANDED TO P9 (HO-24, HO-25) |
| GAP-015 | ST-01, ST-05, ST-08; RV-06; M-10, M-38 | DESIGNED; interaction design HANDED TO P7 (HO-01–HO-10), device and E2E evidence to P8 |
| GAP-016 | CC §14; ST-08; CC §6 (`business_date`, `recorded_at`); M-15–M-19 | DESIGNED; controlled-clock tests HANDED TO P8 (HO-19) |
| GAP-017 | PERFORMANCE AS-01–AS-08, QB-01–QB-17, §12; HO-13, HO-14, HO-20 | DESIGNED as assumptions; measurement HANDED TO P8 (HO-16), units to P11 |
| GAP-022 | LK-01; AX-25, AX-26; ST-04; M-26, M-27 | DESIGNED; tests HANDED TO P8 |
| GAP-023 | LK-13; AX-01–AX-06; M-01–M-03 | DESIGNED; last-unit races HANDED TO P8 |
| GAP-030 | CC §5; AX-06, AX-07, AX-37; NX-11; FU-06; M-06 | DESIGNED; fixtures HANDED TO P8 |
| GAP-031 | GU-02; FU-01–FU-13 with the first-use proof; CC §8 reconciliation cadence; JB-06 | DESIGNED; reconciliation tests HANDED TO P8 (HO-12), roles and the post-restore run to P9 (HO-28) |
| GAP-034 (new) | PERFORMANCE signal register, QS-14 row; BD-15; HO-38 | N/A in P6 — the record would be an addition to SECURITY LG-02 and DATABASE §4.13 beyond a mechanism reference; HANDED TO change control at the P7 entry (HO-38), proof to P8 (H8-12) |
| AC-08 | NM-01–NM-10; JB-01; M-15–M-19, M-31 | DESIGNED; evidence HANDED TO P8 |
| AC-10 | AX-16–AX-24 in CC §7 | DESIGNED; evidence HANDED TO P8 |
| AC-16 | M-01–M-05, M-08–M-10, M-38 | DESIGNED; evidence HANDED TO P8 (HO-11) |
| AC-22 | AX-01–AX-06; M-35 | DESIGNED; evidence HANDED TO P8 |
| AC-23 | PERFORMANCE §4–§7 | DESIGNED as budgets; evidence HANDED TO P8 (HO-16) |
| REF-050 | PERFORMANCE PF-19–PF-23 | DESIGNED |
| REF-057 | CC §3–§8 | DESIGNED |
| REF-058 | CC §10, §11 | DESIGNED |
| REF-059 | PERFORMANCE §3, §4, §12 | DESIGNED; measurable evidence HANDED TO P8 (HO-16) |
| OB §14 numbering | CC §14 | DESIGNED |
| OB §27 race conditions | CC §3–§8; M-01 | DESIGNED; real concurrent tests HANDED TO P8 (HO-11) |
| OB §28 idempotency | CC §10, §11; API_AND_INTEGRATIONS IC-03–IC-05 | DESIGNED |
| OB §29 N+1 | PERFORMANCE PF-01–PF-12, QB-01–QB-12 | DESIGNED; regression checks HANDED TO P8 (HO-16) |
| OB §30 performance | PERFORMANCE §4, §5, §8, §9, §11 | DESIGNED as assumptions |
| OB §31 transactions | CC §3 (short transactions); PERFORMANCE PF-28 | DESIGNED |
| OB §32 optimistic concurrency | ST-01; M-38 | DESIGNED |
| OB §33 API strategy | API_AND_INTEGRATIONS RB-01, RB-06, RB-07 | DESIGNED |
| OB §34 integrations | API_AND_INTEGRATIONS IC-01–IC-14 | DESIGNED as a contract; nothing built in V1 |
| OB §35 queue | CC §15; API_AND_INTEGRATIONS IC-10 | DESIGNED; operation HANDED TO P9 (HO-23, HO-24) |

## Mandatory scenario labels

The Owner's working labels M-01–M-38 collide with no identifier of the repository and were kept unchanged in [CONCURRENCY_IDEMPOTENCY §18](../CONCURRENCY_IDEMPOTENCY.md#18-mandatory-concurrency-and-failure-scenarios); no label was renamed, so no mapping table is needed and every reference of DIR-032 resolves as written. Two stated outcomes are qualified. DIR-032 §9 writes for M-13 that a replay by the original actor after revocation or deactivation gets "the AZ-11 denial response": a revoked actor does; a deactivated actor has no session any more and is answered as unauthenticated, before any command runs — nothing is disclosed in either case (AU-09). And it writes for M-22 that "the last one is REJECTED (RG-09)". That holds when the second command's actor still has the authority — one Owner sending both. When two Owners act on each other, the first command removes the second actor's authority before the second command runs, so the second is denied (AZ-11) or unauthenticated instead: authorization is re-evaluated inside every command before any precondition is read (AZ-05; RV-01). The end state DIR-032 asks for is the same in both cases — at least one active Owner remains.

## Re-derived counts

Recomputed from the final text, not reused:

- **Identifiers:** 415 P6 identifiers in 27 families, each defined once and contiguous — AS 8, BD 18, CI 9, CP 7, D-CC 12, D-PF 8, FU 13, GT 7, GU 7, HO 39, IC 14, IX 17, JB 9, LK 32, LR 12, M 38, NM 10, NX 16, PF 43, QB 17, RB 9, RV 10, RY 9, SQ 23, ST 9, SZ 9, TX 10 — beside the twelve H6 rows that answer SECURITY's obligations.
- **Actions:** AX-01–AX-37, each with a lock-set row and a checks-and-effects row — 20 STATIC and 17 DATA-DEPENDENT (AX-05, AX-06, AX-07, AX-12, AX-15, AX-16, AX-18, AX-19, AX-20, AX-22, AX-23, AX-29, AX-31, AX-32, AX-33, AX-34, AX-37); 16 further command families (NX-01–NX-16); 32 lock classes (LK-00–LK-31), whose declared sets give 270 hold-then-acquire edges, none backward.
- **Scenarios:** M-01–M-38, each with its mechanism, end state, outcomes and P8 obligation; the re-test passes 38 of 38.
- **Handoffs:** HO-01–HO-39 — P7 12 (HO-01–HO-10, HO-38, HO-39), P8 11 (HO-11–HO-20, HO-35), P9 10 (HO-21–HO-30), P10 5 (HO-31–HO-34, HO-36), P11 1 (HO-37).
- **Performance and routes:** 43 PF rules, 17 budgets, 8 assumptions, 17 index candidates, 7 growth thresholds, 9 sizing inputs and 7 cache rules; the signal register covers QS-01–QS-22 in 19 rows; 9 route rules and 14 integration rules.
- **Findings:** self-review 8, all fixed; independent review — CRITICAL 0, HIGH 1, MEDIUM 18, LOW 50, LEVEL-1 CORRECTION 23, DEFERRED OBLIGATION 7, one finding classed OWNER_DECISION_REQUIRED as written and none after its disposition, NO DEFECT 72; verification of the fixes — MEDIUM 3, LOW 16, LEVEL-1 CORRECTION 24, DEFERRED OBLIGATION 3, NO DEFECT 14; final check — LOW 2, LEVEL-1 CORRECTION 16, DEFERRED OBLIGATION 5, NO DEFECT 11; confirmation rounds on the number allocator and the other corrections — C: LOW 1, LEVEL-1 CORRECTION 7, DEFERRED OBLIGATION 1; N: LOW 2, LEVEL-1 CORRECTION 2, DEFERRED OBLIGATION 1; Q: LOW 1, LEVEL-1 CORRECTION 7; R: LOW 5, LEVEL-1 CORRECTION 4, DEFERRED OBLIGATION 1; S: LOW 2, LEVEL-1 CORRECTION 5, the deferred obligation of R carried; T: LEVEL-1 CORRECTION 5; U: LEVEL-1 CORRECTION 4, DEFERRED OBLIGATION 1; Y: LOW 1, LEVEL-1 CORRECTION 3; Z: DEFERRED OBLIGATION 1 — together LOW 12, LEVEL-1 CORRECTION 37, DEFERRED OBLIGATION 5, and each round recorded the attacks that held; unresolved defects 0; OWNER_DECISION_REQUIRED 0.
- **Amendments:** DATABASE 8 lines, ARCHITECTURE 4 clauses and 1 note, WORKFLOWS 2 lines, PERMISSIONS_MATRIX 1 clause and 1 note, every one marked `TECH-021`; SECURITY unchanged.
- **Gaps:** 34 findings — 3 CLOSED, 30 OPEN, 0 OWNER_DECISION_REQUIRED, 1 ACCEPTED_RISK.

## Self-review

Checked against DIR-032 §8–§10, for lock-order acyclicity and protocol conformance, anti-duplication (the P6 files cite rules and never restate, relax or extend them), authority classification (every choice is Level 1) and traceability. Eight findings, all fixed before the independent review:

| ID | Finding | Fix |
| --- | --- | --- |
| SR-01 | Lock sets listed classes whose rows are only born in the action (a lot-cost row in AX-04, money-fact guards in AX-05, AX-12, AX-23, AX-29 and AX-32, lot-cost, invoice and source rows in AX-28) | Removed; the 7.1 note says a born guard row is no lock |
| SR-02 | The savepoint sat before the writes only, so a refused command would have committed the first-use rows and kept the locks it took | The savepoint follows the identity row directly (D-CC-05; section 6) |
| SR-03 | Section 4.3 said the product guard fixes serial states | A serial's state is fixed by its own row (LK-17) |
| SR-04 | The replay rule ended in a dangling clause | CI-05 reworded |
| SR-05 | The render job's claim was described as covering retries | Claim and retry separated in JB-01 |
| SR-06 | The position list named only `lock_version` rows | Broadened to every state-bearing family |
| SR-07 | The CHECK list of `command_log.state` in DATABASE §4.13 could not store a CONFLICT outcome | The `TECH-021` amendment below |
| SR-08 | WORKFLOWS §1 reserved FAILED for background work, while DIR-032 §8 maps a command's technical failure to FAILED | The `TECH-021` note in WORKFLOWS §1 |

While the reviewers worked, the author found five further points that the reviewers then reported independently and that are dispositioned with their findings: section 6 against ST-03 (A-18, B-23), a sweeper that wrote rows (B-21, G-37), export tracking against ARCHITECTURE §11 (A-17, B-26, G-03), an incomplete gap list (G-36) and NX-11's unwritten lock set (A-04).

## Independent adversarial review

Three fresh read-only reviewers that did not write the documents, each given only the repository and told to ignore this gate: **A** — isolation, the lock order, the data-dependent protocol, conservation and scenarios M-01–M-07, M-14–M-19, M-26–M-30 and M-35–M-38; **B** — command identity, replay, failure mapping, revocation, jobs and scenarios M-08–M-13, M-20–M-25 and M-31–M-34; **the third** — performance, route and integration boundaries, governance consistency and a fact-check of the evidence tables. The third reviewer's findings are labelled **G** here, because C-rows are DATABASE's constraint catalogue. The reviews were interrupted once by a usage limit of the session; B had finished, and A and the third reviewer were resumed from their own transcripts against an unchanged working tree. Together they red-teamed every scenario and the interleavings DIR-032 §16 lists.

A: CRITICAL 0, HIGH 0, MEDIUM 2, LOW 14, LEVEL-1 5, OWNER_DECISION_REQUIRED 0, DEFERRED 2, NO DEFECT 20. B: CRITICAL 0, HIGH 0, MEDIUM 6, LOW 16, LEVEL-1 9, OWNER_DECISION_REQUIRED 0, DEFERRED 1, NO DEFECT 31. G: CRITICAL 0, HIGH 1, MEDIUM 10, LOW 20, LEVEL-1 9, one finding classed OWNER_DECISION_REQUIRED as written, DEFERRED 4, NO DEFECT 21. No reviewer found a way to double-count stock or money, to make a balance negative or to leak data across companies. Every finding was verified against the repository and is valid. One point divided the reviewers: A and B read the draft's duplicate check for cash payments as a Level-1 transient wait, the third as an extension of an approved warning that only the Owner may make (G-01). The author follows the stricter reading and removed the extension, so no Owner decision remains and no disagreement about the final text.

| ID | Class | Finding | Disposition |
| --- | --- | --- | --- |
| A-01 | MEDIUM | The counters of a correction case were moved without their lock class by a replacement receipt, by charge draws on returned units, by the application that resolves a refund shortfall and by a supplier refund — a deadlock path, or a guard that never moves | LK-10, the case counters and the case's project added to AX-04, AX-05, AX-19, AX-29, AX-32 and NX-10; AX-19 gains the shortfall-resolving sequence; section 5 and the 7.1 note cover the reopening of a closed case. Completed by V-03 and V-06 |
| A-02 | MEDIUM | A shared lock on the actor's or a company's row gives a waiting exclusive request no place in a queue, so overlapping commands could starve a revocation or a deactivation | D-CC-02: every explicit lock is FOR NO KEY UPDATE, the actor's and the company's row included; RV-02, RV-03; access changes run in their own class TX-09; the source note is in the evidence table; HO-14; M-20, M-21 |
| A-03 | LOW | LK-22 was missing from the reversal of a drop-ship confirmation | AX-10 and AX-36 lock and write the charge and share guards |
| A-04 | LOW | NX-11 had neither LK-11 nor LK-12 | NX-11 has its own lock set with both and is data-dependent through its case, count or finding |
| A-05 | LOW | A lost first-use guard row was re-created silently | The first-use proof of section 8: a row created although facts exist for its key is SQ-18; PERFORMANCE IX-16; M-29 |
| A-06 | LOW | A document void or revision locked only its subject's project, although a requirement of another project may be linked to the version | Document changes are data-dependent through the requirement links (AX-12, AX-15, AX-16, AX-22, AX-31, NX-12, JB-01), and a link is created only under the lock of what it links (NX-03) |
| A-07 | LOW | Command families without a row | NX-15 delivery closure, NX-16 purchase-return outcome; the superseding-decision rule of 7.3; NX-06 names the causal project |
| A-08 | LOW | "No command updates a key column" was false | LR-03 lists the statements for which PostgreSQL takes FOR UPDATE by itself and why each is safe; LR-04; section 4.4; HO-14 |
| A-09 | LOW | LK-17 was ordered by id although a first-use serial has none | LK-17 and LR-05 order by the natural key; LR-08 inserts unique keys in key order; FU-04 names the placeholder state |
| A-10 | LOW | The go-live rule of the number allocator failed open | NM-03: the allocator fails closed and never creates the first counter of a company and type; NM-02, FU-07, NX-01, HO-32, M-18. Completed by V-01 |
| A-11 | LOW | The Force Complete hash covered identities and counts only | ST-04 hashes the three snapshot documents with their amounts |
| A-12 | LOW | Recorded order was the order of transaction start, not of the lock | Phase C stamps `recorded_at` after the locks are granted; the reconciliation paragraph of section 8 |
| A-13 | LOW | A confirmation without a quotation had no stale check and no identity | AX-13 carries the project's `lock_version`; ST-01; BD-05 |
| A-14 | LOW | No movement re-read the serial policy under the product lock | ST-09; LK-13 |
| A-15 | LOW | Who reverses a positive count variance was not settled | AX-06 posts the found and loss-reversal rows of its own variance: LK-24 and the bearer projects added, LK-16 and FU-03 removed |
| A-16 | LOW | "Already done" was unreachable when a check failed first | Every command without a log row probes the command key of its primary fact before its preconditions (section 6 phase C; CI-08; SQ-07) |
| A-17 | LEVEL-1 | Approved text contradicted without amendment: the purge's DELETE, export tracking, deferred checks | JB-08 deletes expired rows under the `TECH-021` clause of DATABASE §19 (W-20; HO-27); ARCHITECTURE §11 and DATABASE §25 carry `TECH-021` clauses |
| A-18 | LEVEL-1 | Section 6 contradicted ST-03 | Section 6 names the two refusals that commit further rows |
| A-19 | LEVEL-1 | Omitted first-use rows and lock classes | FU-01 in AX-06, FU-13 in AX-10, FU-05 in AX-34, the NX-05 note; NX-14 locks the anchor of the linked record; NX-08 takes LK-27; LK-08 is locked in phase B; LR-02 on LK-04 and LK-05 |
| A-20 | LEVEL-1 | Eight wording points | The AX-37 note and project rows; the AX-34 guard cell; 4.3 for LK-13, LK-29 and LK-30; M-14; GU-05 and the illustration; the expiry in CI-03; zero first-use rows in reconciliation |
| A-21 | LEVEL-1 | The statement budget contradicted one statement per guard row | GU-01 lets one statement carry several rows of one guard table; PERFORMANCE QB-13 |
| A-22 | DEFERRED (P8) | Proofs to add | HO-13, HO-14 |
| A-23 | DEFERRED (P10) | The cutover order for counter seeds | HO-32 |
| B-01 | MEDIUM | A session authenticated with the old password survived a password change; login and password change had no sequence | RV-10: the session keeps a keyed digest of the verified password hash, compared on every request; the change re-reads the verified hash under the account's lock; RV-05 covers a login racing a deactivation and a reactivation; CI-09; HO-17. Completed by V-05 |
| B-02 | MEDIUM | A refused import row, job step or event could never run again under its deterministic identity | CI-06, CI-08: a deterministic identity records only COMMITTED and the refusal stays on the driver row; JB-07; API_AND_INTEGRATIONS IC-05, IC-06; M-33 |
| B-03 | MEDIUM | Only renditions recovered lost work | JB-09 sweeps export requests and import batches; the commit request is the durable marker (NX-13, JB-07); M-32, M-34 |
| B-04 | MEDIUM | "REJECTED and routed (QS-20)" had no place in the envelope and no source | SQ-23: REJECTED with rule AZ-08, recorded, with the audit events QS-20 reads — one per affected company after W-01; RV-01; the Refusals column of 7.2; the DATABASE §23 clause; the signal register of PERFORMANCE |
| B-05 | MEDIUM | The verification cap moved a login flood from the processor to the worker pool and blocked step-up | H6-12: two limiters, a 250 ms wait for unauthenticated verifications, a slot of their own for authenticated ones; aggregated THROTTLED events; PERFORMANCE PF-39; HO-24, HO-26; M-25 |
| B-06 | MEDIUM | A first-use counter could start at 1 in the period that contains the cutover | As A-10 |
| B-07 | LOW | The FL-10 lookups ran inside a command that held project rows | Linking and replacement are NX-14 and run no lookup; evidence registration is NX-07. Completed by V-08: the lookups now run before the registration transaction opens, under session-level keys (FU-10; BD-15) |
| B-08 | LOW | A revocation could time out or be starved | TX-09; as A-02; HO-14, HO-25 |
| B-09 | LOW | FAILED could be reported for a command that then commits | SQ-17 resolves through the identity insert, which waits; an unresolved case is FAILED as outcome unknown; HO-01; the WORKFLOWS note |
| B-10 | LOW | HO-02 disabled the replay that ST-05 relies on | As G-02 |
| B-11 | LOW | The replay lookup came after state-dependent resolution | Phase A: the replay probe precedes reference resolution; CI-02, CI-05 |
| B-12 | LOW | The foreign-key backstop was distinguishable from the normal path | SQ-09 answers as the unknown reference of phase A and records nothing |
| B-13 | LOW | After retention a client identity could get an outcome that contradicts the facts | SQ-07: CONFLICT without state for a client identity; the probe of CI-08 |
| B-14 | LOW | Version 5 identifiers were not refused from clients | CI-01 |
| B-15 | LOW | The L-49 key was narrowed for restorations | BD-13 keys AX-10 by the case, otherwise the line |
| B-16 | LOW | An override was not bound to the matches shown | Section 11: an override names the matches it acknowledges |
| B-17 | LOW | The warning for a document without a reference had no registration key | FU-10, BD-14 |
| B-18 | LOW | The upload budget failed open; the export count was check-then-act | H6-07: both bounds are also enforced from durable rows, the upload count under the uploader's row lock (W-24); H6-04: admission under the requester's row lock |
| B-19 | LOW | The per-chunk check of an export could not see a revocation | RV-07, JB-03 |
| B-20 | LOW | A form left open across midnight backdated silently | ST-08; HO-08 |
| B-21 | LOW | The sweeper wrote rows without a key; the stale threshold ignored the job timeout; daily tasks could be skipped | JB-01 claims under the version lock; JB-02 only re-queues; thresholds; HO-25 |
| B-22 | LOW | An event outside the replay window was acknowledged | API_AND_INTEGRATIONS IC-03 |
| B-23 | LEVEL-1 | Section 6 against ST-03 | As A-18 |
| B-24 | LEVEL-1 | The order of the replay table; a replayed REJECTED without current state | Replay table |
| B-25 | LEVEL-1 | The outcome label of M-22 | M-22 |
| B-26 | LEVEL-1 | Approved text refined without a note | The `TECH-021` clauses of ARCHITECTURE §5 and §11 and DATABASE §25 and §26; the H6-04 row |
| B-27 | LEVEL-1 | Session-ending timeouts were retried; the scope of the restart count was unstated | SQ-15, SQ-16, RY-03, RY-07 |
| B-28 | LEVEL-1 | RB-02, RB-03 and CI-01 drifted from their owners | API_AND_INTEGRATIONS RB-02, RB-03; CI-01, CI-09 |
| B-29 | LEVEL-1 | The WORKFLOWS note contradicted its own sentence | Note reworded |
| B-30 | LEVEL-1 | Four gaps in SQ-07, SQ-08, the fingerprint version and a replayed link issue | SQ-07, SQ-08, CI-02, the note under the replay table |
| B-31 | LEVEL-1 | HO-05 omitted the L-45 override; RV-08 purged inside a transaction | HO-05; RV-08 |
| B-32 | DEFERRED (P9) | Store sizing and eviction | HO-24; PERFORMANCE SZ-09 |
| G-01 | OWNER_DECISION_REQUIRED as written | The payment duplicate warning was widened to cash payments | Not kept: BD-02, PERFORMANCE IX-01 and the LK-06 note return to the approved criteria — the same company bank account, amount and window; AX-18 no longer takes LK-06; M-12; HO-35 for the window. No Owner decision remains |
| G-02 | HIGH | HO-02 told P7 to mint a new identity after re-authentication, which opened a second-effect path after a lost response | HO-02 keeps the identity across re-authentication; ST-05; M-10; HO-15. One handoff sentence was wrong; the server protocol was not |
| G-03 | MEDIUM | `export_requests` had no owner in the approved set | H6-04 defines it as an operational record with its content and its requester-only status, which PERMISSIONS_MATRIX DP-07 and SECURITY FL-09 already give an export; ARCHITECTURE §11 amended, DATABASE §4 and PERMISSIONS_MATRIX §7.2 after V-14; the WS-12 bounds stay on the limiter and are also enforced from durable rows |
| G-04 | MEDIUM | Approved sentences contradicted without amendment | `TECH-021` clauses in ARCHITECTURE §5 and §11 and DATABASE §23, §25 and §26 — and, after the verification pass, in DATABASE §4, §4.13, §19 and §20; the "measured" sentences are closed in the table above under DIR-032 §7-B |
| G-05 | MEDIUM | Keyset lists were not servable for a scope of several companies | PERFORMANCE PF-13, PF-41, D-PF-08, IX-11 |
| G-06 | MEDIUM | Whitelisted and nullable sort keys were not covered | PERFORMANCE PF-42 |
| G-07 | MEDIUM | The framework paginator met neither cursor rule | PERFORMANCE PF-14, PF-18, D-PF-08; evidence row |
| G-08 | MEDIUM | "Each QS signal is one indexed query over guard rows" did not hold | PERFORMANCE PF-24 and the signal register; PF-43; IX-13, IX-14; the source of QS-20 (SQ-23) |
| G-09 | MEDIUM | The workload assumption contradicted the image rules | PERFORMANCE AS-03, QB-16, PF-32; the open technical item |
| G-10 | MEDIUM | The command budget contradicted the guard rule | PERFORMANCE QB-13; GU-01 |
| G-11 | MEDIUM | The verification cap was sized on the processor only | As B-05 |
| G-12 | MEDIUM | A deterministic identity could not recover from a refusal | As B-02 |
| G-13 | LOW | Movement history and stock by location had no index path | PERFORMANCE IX-12; the known candidates of its §12 |
| G-14 | LOW | Ranked search read every match | PERFORMANCE PF-21 |
| G-15 | LOW | Three characters do not guarantee a trigram | PERFORMANCE PF-20, PF-23 |
| G-16 | LOW | A plan that is not scope-first was accepted | PERFORMANCE PF-22. Completed by W-08 |
| G-17 | LOW | "At most one row per key" held for two lookups only | PERFORMANCE PF-19 |
| G-18 | LOW | Page reads had no carrier for their timeout; the class of an on-screen report was undefined | TX-06, TX-10; PERFORMANCE QB-11, PF-27 |
| G-19 | LOW | The per-chunk check was void | As B-19 |
| G-20 | LOW | The export count was check-then-act; the hourly limiter was unclassified | H6-04, H6-07 |
| G-21 | LOW | Background requests need a variant of the session middleware | RV-06; HO-20; API_AND_INTEGRATIONS RB-05; PERFORMANCE IX-09 |
| G-22 | LOW | Key-column updates take FOR UPDATE | As A-08 |
| G-23 | LOW | Lost export and import work | As B-03 |
| G-24 | LOW | Exports could occupy both workers | JB-03; PERFORMANCE SZ-02, QB-17. Completed by W-25 |
| G-25 | LOW | Budget rules that would remove an approved output | PERFORMANCE QB-11, PF-27, PF-28 |
| G-26 | LOW | The date was fixed when the form opened | As B-20 |
| G-27 | LOW | QB-12 could not hold over an exclusive arc | PERFORMANCE QB-12 |
| G-28 | LOW | An event outside the window was acknowledged | As B-22 |
| G-29 | LOW | A cache clear at deployment would flush limiter and lock keys | PERFORMANCE CP-07; HO-23, HO-31 |
| G-43 | LOW | Counter first use in the period that contains the cutover | As A-10 |
| G-44 | LOW | Deletes and overwrites the runtime role may not perform | JB-08 under the DATABASE §19 clause (W-20; HO-27); RV-08 (HO-29); JB-04 |
| G-45 | LOW | FL-10 lookups inside a command transaction | As B-07 |
| G-30 | LEVEL-1 | Sentences that read as measurements | Reworded in PERFORMANCE and in D-CC-04 |
| G-31 | LEVEL-1 | Three against four deferred groups | PERFORMANCE PF-34, QB-10 |
| G-32 | LEVEL-1 | Framework caches are files, not store entries | PERFORMANCE CP-02; the H6-05 row |
| G-33 | LEVEL-1 | Evidence standing overstated or misattributed | Both evidence tables |
| G-34 | LEVEL-1 | Restatements that drifted from their owners | As B-28; RV-08 |
| G-35 | LEVEL-1 | Three clarifications in PERFORMANCE | PF-02, PF-22, PF-15, PF-25 |
| G-36 | LEVEL-1 | Traceability and handoff text | CC §20; PERFORMANCE §14; HO-01, HO-09, HO-25; PF-21 |
| G-37 | LEVEL-1 | JB-02, section 6 and IX-07 | JB-02; section 6; IX-07 reads `recorded_at` |
| G-38 | LEVEL-1 | Wording of the two amendments | The WORKFLOWS note; DATABASE cites CI-06 |
| G-39 | DEFERRED (P8) | Proofs without a handoff row | HO-14, HO-16, HO-35 |
| G-40 | DEFERRED (P9) | Operations without a handoff row | HO-21, HO-23, HO-25, HO-27, HO-28 |
| G-41 | DEFERRED (P10) | Planner statistics after the import | HO-36 |
| G-42 | DEFERRED (P11) | The `'unsafe-inline'` question of SECURITY WS-08 | HO-37 |

Two draft corrections were themselves rejected before they were applied. Making the export concurrency bound a durable count *instead of* the limiter of WS-12 would have contradicted SECURITY; it is enforced from durable rows *as well*. And dropping the serialization of the evidence duplicate lookups would have let one actor bypass the override by firing two registrations at once; the lookups stay under their registration keys — which, after the verification pass, are taken before the transaction, so that the lookups themselves stay outside it (V-08).

## Independent verification of the fixes

Two fresh read-only verifiers that had written none of the documents received the repository, the three review reports and the author's dispositions. **V** took the concurrency document, the amendments and an independent re-test of M-01–M-38; **W** took PERFORMANCE, API_AND_INTEGRATIONS, the amendments and cross-document consistency, and re-checked the evidence tables against the official pages. Of the earlier findings V judged 68 of 75 COMPLETE and 7 PARTIAL, W 47 of 56 COMPLETE and 9 PARTIAL; none was NOT FIXED. Both found new defects, and V's re-test failed one scenario — M-18 in the Owner's wording. V: CRITICAL 0, HIGH 0, MEDIUM 1, LOW 6, LEVEL-1 10, OWNER_DECISION_REQUIRED 0, DEFERRED 2, NO DEFECT 1. W: CRITICAL 0, HIGH 0, MEDIUM 2, LOW 10, LEVEL-1 14, OWNER_DECISION_REQUIRED 0, DEFERRED 1, NO DEFECT 13. Neither found a way to double-count stock or money or to leak data, and neither found an Owner-level question. Every finding was verified against the repository, is valid and is corrected:

| ID | Class | Finding | Disposition |
| --- | --- | --- | --- |
| V-01 | MEDIUM | The number allocator did not fail closed: an unseeded period lying after a seeded one started at 1, and the numbering-scheme command seeded a counter, so a type the cutover forgot would have issued over legacy numbers | NM-03: the first counter of a company and type is always a seed — the cutover's, or the Owner's explicit start — a scheme creates none, and first use is allowed only after the latest seeded period; FU-07, NX-01, M-18 in the Owner's wording, HO-19, HO-32 |
| V-02 | LOW | The first-use proof of an overlap-group row refused a legitimate first use | FU-06 |
| V-03 | LOW | The reversal of a return and of a replacement receipt could reopen a closed case without its project row | AX-05, AX-22 and AX-36 hold the projects the case names; the 7.1 note lists every command that can reopen a case; NX-10, NX-16 |
| V-04 | LOW | A document revision was issued without its subject's anchor | NX-12 covers draft edits, the draft of a revision and void, under the subject's anchor; issuing a revision is AX-15, AX-31 or AX-22 |
| V-05 | LOW | The session digest ignored the rehash a login may write | RV-10: a conditional rehash, the session stamped after it, the changing session re-stamped; HO-17, HO-20, HO-26 |
| V-06 | LOW | The application that resolves a refund shortfall was placed inconsistently | AX-18 and AX-19 are DATA-DEPENDENT and take LK-10 with the case's project; section 5 |
| V-07 | LOW | One implicit-lock cycle lay outside the order: the identity row's foreign keys against the change of a company code | `command_log` records its actor and company without foreign keys (CI-03; the DATABASE §4.13 clause); LR-03 rewritten with its argument for master rows; HO-14 |
| V-08 | LEVEL-1 | The FL-10 lookups ran inside the registration command's transaction, against the letter of SECURITY H6-09 and DIR-032 §8-G | The design changes, not the approved sentence: the registration request takes session-level keys before its transaction, runs the lookups outside it and only then opens it (FU-10, NX-07, BD-14, BD-15, D-CC-08). SECURITY is not amended |
| V-09 | LEVEL-1 | Omitted first-use rows and one refusal | FU-05 in AX-12 and AX-32, FU-09 in AX-04, C-54 in AX-15 |
| V-10 | LEVEL-1 | "Open export request" had two meanings, so a READY export escaped the mark | RV-08 marks every request that is PENDING, RUNNING or READY; JB-03; M-21, M-23 |
| V-11 | LEVEL-1 | Amendment wording: "only", the `GenerateExport` cell, the `RenderDocument` key | The clauses of ARCHITECTURE §5 and DATABASE §25 corrected; the approved words of the cell restored, with the supersession inside the marked clause; JB-01 keyed by the rendition id, as ARCHITECTURE §11 says |
| V-12 | LEVEL-1 | The prefilled-date check was listed on four rows only | The 7.2 preamble |
| V-13 | LEVEL-1 | Five envelope wordings | Phase A repeats the replay probe before answering not-found; CI-05; SQ-17 with RY-01; SQ-09; section 6 |
| V-14 | LEVEL-1 | `export_requests` was unknown to the two approved enumerations of tables | `TECH-021` clauses in DATABASE §4 and PERMISSIONS_MATRIX §7.2 |
| V-15 | LEVEL-1 | QS-20 never cleared, did not collapse repeats and had no rule for the class of its events | SQ-23 and the paragraph under section 12: a notice of 14 days, one item per actor, action and target; PJ-11 cited; HO-15 |
| V-16 | LEVEL-1 | Five small mechanics | JB-01 and JB-02 (the age of a first attempt); born rows (the line and commercial guards are born at AX-13); QB-13; AX-08 takes LK-13 (ST-09, LR-09); JB-05, JB-07 and JB-09 stop after spent attempts |
| V-17 | LEVEL-1 | Section 18 said the scenario texts were unchanged | Section 18; M-18; the M-22 qualification under [Mandatory scenario labels](#mandatory-scenario-labels) |
| V-18 | NO DEFECT | Thirteen attacks held — a deadlock after the move to one lock mode, the replay probe as an oracle, the disclosure of a routed refusal, a refusal leaving a lock behind, ST-08 against DIR-018 and others | — |
| V-19 | DEFERRED (P8) | Proofs to add | HO-14, HO-17 |
| V-20 | DEFERRED (P7) | A re-apply offered on a CONFLICT that means "already recorded" | HO-04 |
| W-01 | MEDIUM | The record behind QS-20 could not serve it: the affected companies lived in untyped data, a pool command had no company, and repeats never left the list | SQ-23: one audit event per affected company in typed columns, under the routed variant of the action code; acknowledged warnings the same way (section 11); the signal register; IX-13; the DATABASE §23 clause |
| W-02 | MEDIUM | Rows that belong to no company fell out of every per-company list | PERFORMANCE PF-13, PF-41 |
| W-03 | LOW | The audit list, master and pool lists and keys living on another table had no key rule | PF-13, PF-42, IX-11, IX-13 |
| W-04 | LOW | Filters had no index rule | PF-42; step 2 of the procedure |
| W-05 | LOW | An index on a column that does not exist | IX-14; the QS-15 row |
| W-06 | LOW | "One query per company" could not hold for guards without a company | PF-24: such a signal starts from the company's open roots; two signals read the workspace's open rows, and say so |
| W-07 | LOW | The Owner's queue was registered with part of its sources, and two P5 items have no durable source | The QS-14 row; the two items are handed over (HO-38; the GAP-006 note) |
| W-08 | LOW | The accepted search plan still read other companies' index entries | PF-22: the company is the index condition; a bitmap AND is refused |
| W-09 | LOW | The ranked candidate set was arbitrary; prefix lookups had no index | PF-21, PF-23, IX-17 |
| W-10 | LOW | Downloads were classed as background, which narrowed SECURITY AU-07 | RV-06, HO-06; API_AND_INTEGRATIONS RB-04, RB-05; QB-16 |
| W-11 | LOW | The age of a claimed row was measured from its creation | The export request records its claim time (H6-04, JB-03, JB-09, IX-05); JB-01, JB-02 |
| W-12 | LOW | Date indexes were missing for purchases and sales | IX-11; the known candidates of PERFORMANCE section 12; PF-27, PF-43 |
| W-13 | LEVEL-1 | Three register rows named a source that cannot produce the signal | QS-03, QS-04, QS-12 |
| W-14 | LEVEL-1 | The dashboard budget, the multiplication by companies and the snapshot of the period figures | QB-10, PF-24, PF-43, TX-10 |
| W-15 | LEVEL-1 | The ARCHITECTURE §5 clause said "only" | As V-11 |
| W-16 | LEVEL-1 | The ARCHITECTURE amendment note against its `GenerateExport` cell | As V-11 |
| W-17 | LEVEL-1 | The `RenderDocument` key | As V-11 |
| W-18 | LEVEL-1 | `export_requests` and the approved enumerations | As V-14 |
| W-19 | LEVEL-1 | DATABASE §20 had no pointer to the index register | The `TECH-021` clause of DATABASE §20 |
| W-20 | LEVEL-1 | No approved role could purge `command_log` | The `TECH-021` clause of DATABASE §19; JB-08; HO-27 |
| W-21 | LEVEL-1 | The wording of NX-07 and H6-09 | As V-08 |
| W-22 | LEVEL-1 | QB-13 against LR-05 | QB-13 |
| W-23 | LEVEL-1 | "Open export request" | As V-10 |
| W-24 | LEVEL-1 | The upload limiter's behaviour with the store down; the lock of the durable count | H6-07 |
| W-25 | LEVEL-1 | "One export at a time" had no mechanism | JB-03, JB-09; SZ-02 |
| W-26 | LEVEL-1 | Nine factual slips | IX-11, GT-02, GT-05, the boundary paragraph and steps 2, 4 and 6 of PERFORMANCE; HO-25; the DATABASE amendment note; API_AND_INTEGRATIONS RB-03 |
| W-27 | DEFERRED (P8) | The OD-16 reconciliation and the two refusal events | HO-15; ST-03 |
| W-28–W-40 | NO DEFECT | Thirteen attacks held — the merged keyset, NULL buckets, the envelope arithmetic, the verification cap, the cache policy, the integration contract, the meaning of the amendments, identifier and number consistency and others | — |

While correcting these the author changed two more things of the same kind: the project column of AX-22 for a case that takes a residual, and the GAP_REGISTER notes that repeated corrected claims (GAP-003, GAP-004, GAP-006, GAP-008, GAP-009, GAP-016, GAP-017).

## Final check of the corrections

Two more fresh read-only verifiers checked the corrections of the verification pass. **F** took the concurrency document, the amendments and a full re-test of M-01–M-38; **E** took PERFORMANCE, API_AND_INTEGRATIONS, the signal and index design, the gap notes, the amendments and the evidence facts added in that round, re-read against the official pages. F judged 30 of 33 corrections COMPLETE and 3 PARTIAL, E 26 of 27 COMPLETE and 1 PARTIAL; none was NOT FIXED. F: CRITICAL 0, HIGH 0, MEDIUM 0, LOW 1, LEVEL-1 8, OWNER_DECISION_REQUIRED 0, DEFERRED 3, NO DEFECT 1. E: CRITICAL 0, HIGH 0, MEDIUM 0, LOW 1, LEVEL-1 8, OWNER_DECISION_REQUIRED 0, DEFERRED 2, NO DEFECT 10. Both confirmed that SECURITY is byte-identical to its approved blob, that every changed line of the four amended documents is an insertion marked `TECH-021`, and that SECURITY H6-09 and DIR-032 §8-G now hold in their letter. Every finding was verified against the repository, is valid and is corrected:

| ID | Class | Finding | Disposition |
| --- | --- | --- | --- |
| F-01 | LOW | The numbering rule failed across a change of granularity: a month inside a seeded year could start at 1 over legacy numbers, and a coarser scheme set at go-live could never issue | NM-03 decides on dates: the seed boundary is the cutoff date of the seeding import or the day of the Owner's start; a period is live when no date up to that boundary maps to its key; first use only for a live period; a further scheme whose first period is neither live nor seeded is refused. FU-07, NX-01, M-18, HO-19, HO-32 |
| F-02 | LEVEL-1 | The registration keys die with their session, while the text held them across attempts and gave their wait no bound | FU-10: every attempt takes the keys in a short step that sets the lock wait, and its transaction confirms the session; a lost session is SQ-16. HO-14, HO-20, HO-22 |
| F-03 | LEVEL-1 | The session stamp could be read as a hash read after a password change | RV-10: the digest of the hash the login verified, or of the hash its own rehash wrote |
| F-04 | LEVEL-1 | The C-54 cell of AX-15 would have blocked the collection documents of a deactivated company | AX-15 and the 7.1 note follow WF-DOC-01: collection documents stay possible |
| F-05 | LEVEL-1 | Three imprecisions in clauses that become approved text | The clauses of ARCHITECTURE §5 and of DATABASE §23 and §25 |
| F-06 | LEVEL-1 | The export job's retries fitted neither its lock nor its claim | JB-03: a release spends no failed run, the lock expires between the job's timeout and the sweep's bound, and a retry claims its own RUNNING request |
| F-07 | LEVEL-1 | A resumed import was not covered by the sweep after a lost dispatch | JB-07, NX-13: the resume clears what the row recorded |
| F-08 | LEVEL-1 | A key update refused by a foreign key was answered as an unknown reference | ST-09, SQ-03, SQ-04, SQ-09: the scale key maps to CONFLICT or to REJECTED (C-09); other violations on the referenced side are defects |
| F-09 | LEVEL-1 | Seven wordings | The project cells of AX-04, AX-05 and AX-08; LR-02 and LR-03; FU-07; section 18; JB-01 and JB-02; the contract paragraph of the concurrency document; NM-03 states that it narrows DATABASE §10 |
| F-10 | DEFERRED (P7 entry) | The Owner's listing of out-of-scope duplicates and collisions has no stored record | GAP-034; HO-38 |
| F-11 | DEFERRED (P8) | Proofs the corrections need | HO-14, HO-17–HO-20 |
| F-12 | DEFERRED (P9) | Request-scoped database sessions; lock lifetimes | HO-22; PERFORMANCE SZ-03 |
| F-13 | NO DEFECT | Twelve attacks held — H6-09 in its letter, a deadlock through the registration keys, numbering within one granularity, implicit-lock cycles, the completion anchor, the routed-refusal events, the jobs and others | — |
| E-01 | LOW | A request waiting behind another export could end FAILED without having run, and the lock of a dead worker blocked every later export | As F-06; the evidence row records both documented facts |
| E-02 | LEVEL-1 | General statements of PF-24 were contradicted by register rows | PF-24; QS-08, QS-12, QS-16, QS-18; the known candidates |
| E-03 | LEVEL-1 | The QS-14 row read ordinary import duplicates and named no index for two sources | The QS-14 row; IX-14; AS-04 |
| E-04 | LEVEL-1 | IX-11 excluded the pool movements that PF-13 serves from it | IX-11 |
| E-05 | LEVEL-1 | The acceptance rule of the procedure rejected plans the design prescribes | Step 4 of the procedure |
| E-06 | LEVEL-1 | The registration keys | As F-02 |
| E-07 | LEVEL-1 | JB-01 relied on the unique-job lock against a documented fact | JB-01, JB-02: the residual of a long queue wait is stated; HO-18, HO-22 |
| E-08 | LEVEL-1 | The PERMISSIONS_MATRIX amendment note left out part of its clause | The note |
| E-09 | LEVEL-1 | Three statements the round left behind | CP-02; the contract paragraph of the concurrency document; the visibility test of QS-20, stated once |
| E-10 | DEFERRED (P7 entry) | The two P5 items | As F-10 |
| E-11 | DEFERRED (P8, P9) | PostgreSQL 18 makes a generated column virtual unless it is declared STORED | HO-30; the PERFORMANCE evidence table |
| E-12–E-21 | NO DEFECT | Ten attacks held — the PERMISSIONS_MATRIX clause and visibility, the purge against DATABASE §28, the routes, the keyset design, search, the budget arithmetic, measurement and cost claims, the amendments, the gap notes and the Owner-level boundary | — |

## Confirmation of the corrections

A further fresh read-only verifier, **C**, received the repository and the reports of the final check, verified each of its 23 corrections, re-ran M-13, M-18, M-23, M-31 and M-32, and attacked the numbering rule, the registration keys, the export job, the amendments and the Owner-level boundary again. It judged all 23 corrections COMPLETE. Its findings are labelled **K** here. C: CRITICAL 0, HIGH 0, MEDIUM 0, LOW 1, LEVEL-1 7, OWNER_DECISION_REQUIRED 0, DEFERRED 1, NO DEFECT 1 (a table of attacks that held). Every finding was verified against the repository, is valid and is corrected:

| ID | Class | Finding | Disposition |
| --- | --- | --- | --- |
| K-01 | LOW | The Owner's start of numbering could be lost or left incomplete: an import recorded after the start with an earlier cutoff made a month before the start live, so a real document dated in it would start at 1 over numbers issued outside; and a real-dated document backdated to before the start could never be issued, because only the migration could seed | NM-03: the seed boundary is the latest of the import cutoffs and the day of the Owner's start, in whatever order they were recorded; once a type has its boundary, the numbering command also seeds, with the first number the Owner states, a period that is not live and has no counter — never one that DATABASE §10 leaves to the migration. NX-01, FU-07, M-18, HO-19 |
| K-02 | DEFERRED (P10) | The premise that no number issued outside carries a date after the boundary holds only if the cutover makes it hold | HO-32: a company's latest import cutoff is not earlier than the last day on which a number of that company is issued outside; under the delta option the delta is imported with its own cutoff and seeds before the company issues |
| K-03 | LEVEL-1 | Two sentences of NM-03: the go-live period was said always to be seeded, and only a scheme added, not one changed before it takes effect, was checked | NM-03: the period into which the cutoff date falls; "a scheme it adds or changes"; HO-19 |
| K-04 | LEVEL-1 | Phase A placed the registration keys outside any transaction, where `SET LOCAL` has no effect | Phase A: the keys are taken in a short transaction of their own (FU-10), before the command's transaction |
| K-05 | LEVEL-1 | The type-free registration key included the company, while FL-10 matches across companies, so two companies' uploads of one bill could both miss the warning | FU-10: where a reference exists, the key is issuer, reference and date whatever the company; BD-14; BD-15, whose lookup probes the index once per company and once for pool evidence |
| K-06 | LEVEL-1 | The ARCHITECTURE §5 clause answered every foreign-key violation of a referencing row as an unknown reference, although the scale key of ST-09 is a CONFLICT | The clause names that exception |
| K-07 | LEVEL-1 | LR-03 said that an inserter never locks the master row; commands that lock a company or a bank account do, at the master's own position | LR-03 |
| K-08 | LEVEL-1 | PF-24 named four sets that only grow; pre-payment Kuitansi chains are a fifth | PF-24 |
| K-09 | LEVEL-1 | A request whose runs kept dying could be claimed again without end, because each claim restamped the time the sweep measures and a killed worker need not count as a failed run | JB-03: the claims are counted on the request, and one already claimed three times is marked FAILED; H6-04; HO-18 |
| K-10 | NO DEFECT | The attacks that held — numbering within its boundary, the registration keys for one content or one company's identity, SECURITY H6-09 and DIR-032 §8-G in their letter, every interleaving of RV-10, AX-15 against WF-DOC-01 and others | — |

C found no Owner-level question: the numbering guarantees and date rules of the approved text hold, and only leaving K-01 unfixed would have changed one. Correcting K-01 the author added a limit C had not named: the Owner's seed never reaches a period that DATABASE §10 leaves to the migration — one whose dates all precede go-live, or one that a date on or before the cutoff of a batch covering the company, not ABORTED, falls into.

A second fresh read-only verifier, **N**, checked the corrections of the K findings, re-ran M-13, M-18, M-22 and M-32 and attacked the numbering rule again, now also with an in-memory model of it — scripted cases and 7,500 random runs of scheme histories, paper numbering, imports, starts, seeds and issues. It judged K-02–K-09 COMPLETE and K-01 PARTIAL. N: CRITICAL 0, HIGH 0, MEDIUM 0, LOW 2, LEVEL-1 2, OWNER_DECISION_REQUIRED 0, DEFERRED 1, NO DEFECT 1 (eighteen attacks held). Every finding was verified against the repository, is valid and is corrected:

| ID | Class | Finding | Disposition |
| --- | --- | --- | --- |
| N-01 | LOW | After an Owner's start, a later import whose cutoff fell inside a period could seed that period after the last number up to its cutoff, over paper numbers issued in the same period after the cutoff | NM-03: every seed states the first number after every number of its period issued outside, whatever its date; an import seeds only periods that lie wholly on or before its cutoff and, while the type has no later boundary, the periods into which its cutoff falls — so such a period is left to the Owner's seed. HO-32, M-18, HO-19 |
| N-02 | LOW | "Live" could be read under the scheme in force on each date; a scheme set up before a start that differed from the paper practice then let a later scheme restart a period at 1 over paper numbers | NM-03: a period is live only when it begins after the seed boundary, whatever its granularity and whichever scheme was in force — a YEAR and a MONTH key never coincide; the start seeds the period of its day under every granularity the type's schemes use, as the cutover does for its cutoff (HO-32) |
| N-03 | LEVEL-1 | The delta that HO-32 prescribes raised a defect alert on every re-seeded period, and a batch without seeds could block the Owner's seed | NM-03: an alert only when the counter met was created on first use or has drawn a number since its seed; the Owner's seed is excluded only by a batch's VALID or COMMITTED seed row for the period. HO-32: the delta seeds again every period in which it finds a number issued outside after the earlier data was taken |
| N-04 | LEVEL-1 | Go-live was stored nowhere, the start could not be told from other counters, and neither the start nor first use was bounded by go-live | NM-03: go-live is recorded once at the cutover (HO-32); the boundary reads only recorded values — the cutoffs of the seeding imports and, for each counter the numbering command seeded, the earlier of the day it did so and its period's last day — and a counter created on first use carries no seed value; the start and first use are bounded by go-live, so NM-03 is strictly narrower than DATABASE §10 |
| N-05 | DEFERRED (P7) | The start and the Owner's seeds rest on the Owner's statements, and no handoff asked P7 to show them | HO-39 |
| N-06 | NO DEFECT | Eighteen attacks held — backdating into unseeded pre-cutover periods, a scheme change after the boundary, the Owner's seed against an import seed and against a batch's preparation, an issue against a scheme change, midnight and month end, K-05 across companies, the ARCHITECTURE clause on the referenced side and others | — |

Before the next verification the corrected rule was tested with a separate in-memory model — scripted N-01 and N-02 cases and 4,800 random runs over a cutover with freeze or delta, a company started by the Owner with and without a later import, a company whose first counters came from a later import, and a new company: no number was reused and no valid backdated issue on or after go-live stayed without a permitted seed. The model first exposed one more case — a delta that re-seeded only the period of its own cutoff left an earlier straddling seed too low — which the delta rule of HO-32 now closes.

A third fresh read-only verifier, **Q**, checked the corrections of the N findings and attacked the rule with a model of its own — scripted cases and 5,300 randomized runs under several readings of the text. It judged N-01–N-03 COMPLETE and N-04 and N-05 PARTIAL; with every premise NM-03 states kept, its runs showed no silent reuse. Q: CRITICAL 0, HIGH 0, MEDIUM 0, LOW 1, LEVEL-1 7, OWNER_DECISION_REQUIRED 0, DEFERRED 0, NO DEFECT 1 (twenty-three attacks held). Every finding was verified against the repository, is valid and is corrected:

| ID | Class | Finding | Disposition |
| --- | --- | --- | --- |
| Q-01 | LEVEL-1 | It was not settled whether the import commit applies the import period rule again, or what becomes of a seed row it no longer admits; a start could race a pending batch; HO-32's cutoff invariant clashed with its own sentence on later imports | NM-03 and AX-28: the commit applies the rule again and records such a row SKIPPED, with no counter and no refusal; the start is refused while a batch holds a VALID seed row of the type; only VALID rows exclude the Owner's seed. HO-32: the invariant holds for every type an import seeds while the type has no later boundary. M-18, HO-19 |
| Q-02 | LEVEL-1 | A scheme of a new granularity added while no boundary existed escaped the scheme check — after a dry run, or at the moment of a start | NM-03, NX-01, NX-13: the numbering command and the Owner's commit request lock the company row (LK-06) and read schemes, counters and batch rows under it; while a batch holds seed rows, a scheme whose first period it would leave without a seed is refused, and the commit request checks the batch against the schemes then in force. HO-32, M-18, HO-19 |
| Q-03 | LOW | HO-39 and HO-32 left out the date premise NM-03 rests on, so reuse was reachable under the premises the Owner was asked to confirm | HO-39 and HO-32 state it: no number issued outside carries a date after the start day or the cutoff, and nothing is numbered outside from the start day on, or after the data of the import that set the boundary |
| Q-04 | LEVEL-1 | The first-use illustration wrote a seed value | The illustration writes none |
| Q-05 | LEVEL-1 | "Committed imports" in the boundary could be read as batches in state COMMITTED | The boundary reads every batch, whatever its state, that holds a COMMITTED seed row of the type |
| Q-06 | LEVEL-1 | The verification of DATABASE §3.1 knows only the migration seed | NM-03 and the contract paragraph of CC: a counter's seed value is its migration seed, whoever wrote it, and a raise moves the seed value with the counter |
| Q-07 | LEVEL-1 | The rows that restate the rule were incomplete | AX-16 and AX-22 name the period rule and C-10; NX-03 names C-10; NX-01 applies the scheme refusal once a type has its boundary |
| Q-08 | LEVEL-1 | A correct seed meeting a counter that had drawn raised a false alert | NM-03: an alert only when the new seed exceeds the first number of a counter that has drawn, the only case in which a number can have been reused |
| Q-09 | NO DEFECT | Twenty-three attacks held — the N-01 and N-02 cases, the delta, unseeded periods, the go-live bound, the monotonic boundary, the first-use race, seeds against each other and against issues, two starts, midnight, scheme changes against issues, keys across granularities and others | — |

The corrected rule was run again through the separate model, extended with pending batches, the SKIPPED rule, a dry run committing while a start commits, scheme changes between a dry run and a commit, and the new alert rule: 2,400 runs over the same kinds of company, with no reuse, no valid issue on or after go-live left without a permitted seed and no alert without a reuse.

A fourth fresh read-only verifier, **R**, checked the corrections of the Q findings and attacked the rule with a model of its own — 12,000 randomized worlds with every stated premise kept, and scripted cases. It judged Q-01–Q-05, Q-07 and Q-08 COMPLETE and Q-06 COMPLETE in substance; its runs showed no silent reuse. R: CRITICAL 0, HIGH 0, MEDIUM 0, LOW 5, LEVEL-1 4, OWNER_DECISION_REQUIRED 0, DEFERRED 1, NO DEFECT 1 (twenty attacks held). Its report numbered its findings F-1–F-10; they are R-01–R-10 here. Every finding was verified against the repository, is valid and is corrected:

| ID | Class | Finding | Disposition |
| --- | --- | --- | --- |
| R-01 | LOW | NM-03 stated the import's premise more weakly than HO-32, so an outside practice that numbers by the day of issue could reuse a number | NM-03 states the premise as HO-32 does: once the type issues here, no number has been issued outside after the boundary, none carries a later date, and each belongs to a period that contains its date or the day it was issued. HO-32, HO-39 |
| R-02 | LOW | A scheme taking effect before the day it was recorded could leave a period straddling the boundary without a counter, blocking current documents | NM-03, NX-01: once a type has its boundary, a scheme may not take effect before the day it is recorded; the Owner seeds only periods that a granularity governs |
| R-03 | LOW | Once numbering runs, a scheme whose first period straddles the boundary can never be accepted, and the Owner was not told | Kept as a Level-1 restriction — a change of granularity takes effect from a period that begins after the boundary — and made visible: HO-39 shows the earliest date from which it may take effect, and the Owner-visible choices below list it |
| R-04 | LEVEL-1 | NX-13 disagreed with NM-03 on the seeds its commit request checks | NX-13 follows NM-03 |
| R-05 | LEVEL-1 | NX-01 omitted "not ABORTED"; AX-31 did not name the period rule | Both added |
| R-06 | LOW | A seed could re-create a lost counter below numbers already issued, and a refusal did not look for a lost counter | NM-03, FU-07: a seed that creates its counter proves that no version issued here, live project or tombstone carries a number of its period at or above its first number; an issue that finds no counter for a period that is not live proves that none of them carries the period; a failed proof is SQ-18 |
| R-07 | LEVEL-1 | The contract paragraph of CC called the reading of the §3.1 migration seed a narrowing | It says instead that NM-03 applies the `seed_value` of DATABASE §4.2 as defined |
| R-08 | LOW | Cutover seeds were required for every granularity the type's schemes use, while the pending-batch check tested only a scheme's first period, so a harmless scheme could stall a cutover commit | NM-03, NX-13, HO-32: a seed is required — and a scheme added while a batch waits is refused — only for a granularity that governs the period into which the cutoff falls |
| R-09 | LEVEL-1 | HO-39 did not make the start's confirmation cover every period it seeds | The start seeds only governed periods, and HO-39 lists every period the command seeds |
| R-10 | DEFERRED (P10) | A freeze that ends after the cutoff leaves its periods unseeded | HO-32: a batch whose cutoff is the freeze's last day seeds them, or documents dated in them stay REJECTED, as DATABASE §10 says |
| R-11 | NO DEFECT | Twenty attacks held — every numbering guarantee, DATABASE §10 and §24 read as upper bounds, the Owner's authority, the monotonic boundary, the exact alert, the start against a pending batch and a dry run, concurrent batches, seeds against issues and against each other, lock order and others | — |

The corrected rule ran again through the separate model, with scheme changes that try to take effect in the past, an outside practice that numbers by the day of issue and counters deliberately lost: 1,600 runs with the premises kept showed no reuse and no valid issue left without a permitted seed, and in 400 runs with lost counters no number was issued twice — every lost counter surfaced as SQ-18.

A fifth fresh read-only verifier, **S**, checked the corrections of the R findings and attacked the rule with a model of its own — four premise-keeping modes of 2,500 runs each, and scripted cases. It judged R-01, R-02 and R-05–R-09 COMPLETE, R-03 and R-04 PARTIAL, and R-10 still deferred; in its runs no premise-keeping world reused an outside number. S: CRITICAL 0, HIGH 0, MEDIUM 0, LOW 2, LEVEL-1 5, OWNER_DECISION_REQUIRED 0, DEFERRED 1, NO DEFECT 1 (eighteen attacks held). Its report numbered its findings R-1–R-8; they are S-01–S-08 here. Every finding was verified against the repository, is valid and is corrected:

| ID | Class | Finding | Disposition |
| --- | --- | --- | --- |
| S-01 | LOW | The seed boundary was read partly from counter rows, so a start counter lost by a defect — which the runtime role cannot cause — could move the boundary back and let a first use restart at 1, or erase it | NM-03: the boundary reads the day of the start from the start's own audit event, apart from its counters, and no other seed of the numbering command counts; a lost counter's period stays not live — REJECTED, or SQ-18 where numbers were issued under it — and the Owner may seed it again under the seed proof. M-18, HO-19; HO-27 keeps those audit events |
| S-02 | LOW | Once numbering ran, a scheme could not cover dates before the type's first scheme, so documents dated there could never be numbered | NM-03, NX-01: a scheme may extend the type's schemes backwards over dates no scheme covered; the Owner may seed any period that is not live, so a scheme whose first period is not live is accepted together with the Owner's seed of that period — which also relaxes the restriction of R-03 |
| S-03 | LEVEL-1 | NX-13 lacked NM-03's qualifier | "wherever NM-03 admits one" |
| S-04 | LEVEL-1 | The scheme rules were shown only in the start and seed confirmations | HO-39: for every type with a boundary, the numbering-scheme form and its refusals state them; the Owner-visible choices below list them |
| S-05 | LEVEL-1 | HO-32 did not say where a later import with an earlier cutoff takes its seed values from | HO-32: from the outside numbering as it stood at the boundary, not only from the batch's own extract |
| S-06 | LEVEL-1 | M-18's scheme-refusal clause disagreed with NM-03 | M-18 |
| S-07 | LEVEL-1 | HO-39 made every seed confirmation vouch for a start day | HO-39: the start day's clauses belong to the start alone |
| S-08 | DEFERRED (P10) | Freeze periods (R-10), carried | HO-32 |
| S-09 | NO DEFECT | Eighteen attacks held — an issue-day practice at the cutover, retroactive schemes, harmless future schemes beside a pending batch, the start against a dry run, stale boundaries, concurrent batches, the delta, later imports around a start, the Owner's seed limits, the exact alert, lost counters with numbers issued, lock order and the approved texts | — |

The corrected rule ran again through the separate model, now with backward extensions and a start record kept apart from the counters: 1,600 runs with the premises kept and 600 with counters deliberately lost showed no reuse; the only dates left without a scheme were those a pending batch held until it was committed or aborted.

A sixth fresh read-only verifier, **T**, checked the corrections of the S findings and attacked the rule with a model of its own — 9,000 randomized worlds with about 406,000 issues, 15,900 counters deleted by a simulated defect among them, and a search over every permitted remedy for 900 real dates. It judged S-01–S-05 and S-07 COMPLETE and S-06 PARTIAL, and carried S-08. It found no reuse over outside numbers while the stated premises hold, no real-dated document on or after go-live that no permitted action can number, no race that breaks a guarantee silently and no lock-order problem; M-15–M-19 hold in the Owner's wording. T: CRITICAL 0, HIGH 0, MEDIUM 0, LOW 0, LEVEL-1 5, OWNER_DECISION_REQUIRED 0, DEFERRED 0 new, NO DEFECT 1 (twenty-seven attacks held). Its report numbered its findings N-1–N-5; they are T-01–T-05 here, and all are corrected:

| ID | Class | Finding | Disposition |
| --- | --- | --- | --- |
| T-01 | LEVEL-1 | NX-01, M-18 and HO-39 left out NM-03's exception for a backward extension, and M-18 said "once numbering runs" | NX-01, M-18, HO-39 |
| T-02 | LEVEL-1 | The boundary's inputs had no named typed columns: `jsonb` would break DATABASE §17, and the counters would bring S-01 back | NM-03: the boundary reads only typed columns — a registered `import_kind` per sequence type on a seed row, and the company and a registered action code per sequence type on the start's audit event |
| T-03 | LEVEL-1 | "An issue never creates the first counter" was false for a start that seeds no period | NM-03 and the GAP-016 note: an issue never creates a counter for a type that has no seed boundary |
| T-04 | LEVEL-1 | NM-03 and NX-13 required cutoff-period seed rows that HO-32 exempts | Both use HO-32's condition: unless the type's seed boundary is later than the cutoff |
| T-05 | LEVEL-1 | HO-32 and M-18 overstated which freeze-dated documents stay REJECTED | Only those of freeze periods that are not live and have no counter |
| T-06 | NO DEFECT | Twenty-seven attacks held — premise-keeping reuse searches, live periods, lost counters drawn and undrawn, the boundary's immunity to them, the alert, a broken delta premise, issue-day practice, starts whose schemes differ from the practice outside, the races, same-day schemes, concurrent imports, backward extensions, granularity changes, gaps before go-live, companies added after go-live, later imports, go-live as a setting, opening projects, replays of a refusal, lock order, M-15–M-19 and the approved texts | — |

A seventh fresh read-only verifier, **U**, checked the T corrections and the numbering rows around them, narrowly. It judged T-01, T-02, T-04 and T-05 COMPLETE and T-03 PARTIAL. U: CRITICAL 0, HIGH 0, MEDIUM 0, LOW 0, LEVEL-1 4, OWNER_DECISION_REQUIRED 0, DEFERRED 1, NO DEFECT 1 (eighteen checks held). Its report numbered its findings F-1–F-5; they are U-01–U-05 here, and each is corrected with the wording it proposed:

| ID | Class | Finding | Disposition |
| --- | --- | --- | --- |
| U-01 | LEVEL-1 | NM-03 still said that a type's first counter is always seeded — the rest of T-03 | NM-03: no counter exists before the type has its seed boundary, and every period that is not live is seeded, never created on demand |
| U-02 | LEVEL-1 | NM-03's summary of a change of granularity left out the backward extension | NM-03: a backward extension is admitted without a seed, its periods REJECTED until they are seeded |
| U-03 | LEVEL-1 | M-18 forbade a later import to seed the period of its cutoff without the condition HO-32 states | M-18: a later import whose cutoff is earlier than the type's seed boundary |
| U-04 | LEVEL-1 | The lock-set row of AX-28 did not name FU-07 among its first-use rows | §7.1 |
| U-05 | DEFERRED (P10) | Nothing defined the import identity of a seed row, so a delta's second seed of a period could collide with the first batch's under C-47 | HO-33: the identity includes the batch's cutoff |
| U-06 | NO DEFECT | Eighteen checks held — the rows that restate the rule, the typed-column sentence against DATABASE and SECURITY, SQ-23's registered variants, and AX-28's re-check without the company lock | — |

An eighth fresh read-only reviewer, **Y**, confirmed the U corrections as applied — all five COMPLETE — and re-read the rows around them. Y: CRITICAL 0, HIGH 0, MEDIUM 0, LOW 1, LEVEL-1 3, OWNER_DECISION_REQUIRED 0, DEFERRED 0, NO DEFECT 1. Its report numbered its findings W-1–W-5; they are Y-01–Y-05 here, and they are corrected with the wording it proposed:

| ID | Class | Finding | Disposition |
| --- | --- | --- | --- |
| Y-01 | LEVEL-1 | Two NM-03 sentences and the GAP-016 note were false once a later import moves the boundary past a period that already has an on-demand counter | NM-03: a period that is not live gets a counter only from a seed; the GAP-016 note speaks of a period without a counter |
| Y-02 | LEVEL-1 | HO-39 and the Owner-visible choices said that a first period that is not live needs the Owner's seed, without "and has no counter" | HO-39; the choices below |
| Y-03 | LOW | A cutover split into batches that share one cutoff could stall: the second batch's seed row matched the first batch's committed identity | HO-33: a seed row's identity includes its batch; seeding again is harmless, because a seed only ever raises a counter |
| Y-04 | LEVEL-1 | The backward-extension clause of NM-03 said all its periods stay REJECTED | NM-03: only those that are not live and have no counter |
| Y-05 | NO DEFECT | FU-07, NX-01, NX-13, M-18, HO-19, HO-32, HO-33 and HO-39 agree with NM-03 and with DATABASE §10, §24 and C-10 | — |

A ninth fresh read-only reviewer, **Z**, confirmed Y-01–Y-04 as applied and consistent with NM-03, NX-01, NX-13, FU-07, M-18, HO-19, HO-32, HO-33, HO-39 and the GAP-016 note; found that a seed identity which includes its batch contradicts nothing in DATABASE §4, §24 or C-47 and cannot let a resumed batch seed twice; and confirmed the APPR-007 hash of CONCURRENCY_IDEMPOTENCY as the file then stood. Z: CRITICAL 0, HIGH 0, MEDIUM 0, LOW 0, LEVEL-1 0, OWNER_DECISION_REQUIRED 0, DEFERRED 1, NO DEFECT 1 (seven checks held). Its one finding, Z-01 (DEFERRED, P8), asks for a test of a later import that moves the boundary past periods already numbered here — a counter created on first use continues, and a period without a counter up to the new boundary is REJECTED until it is seeded; HO-19 now names it, and after that edit the hash was recomputed and the validation run again. No round after the review found a CRITICAL or HIGH defect, and no defect of any class is left unresolved.

## Adversarial re-test

Every mandatory scenario was run against the text after each round of corrections, each time for the same points: mechanism present, anchors and locks named, end state reached, outcome of each participant as DIR-032 §9 requires, P8 obligation stated. The author ran all 38 after the review, after the verification pass, after the final check and against the final text; the later corrections changed only the mechanisms behind M-15–M-19, M-29 and M-32, which the confirmation rounds attacked again. Two verifiers ran all 38 independently: V, after the fixes of the review, passed 37 and failed M-18 in the Owner's wording; F, after the corrections of the verification pass, passed 38, with a reservation on M-18 (a change of granularity) and on M-32 (a dispatch lost after a resume), both corrected since. C re-ran M-13, M-18, M-23, M-31 and M-32 on the next text and failed M-18 in the Owner's wording for a period before an Owner's start (K-01); N re-ran M-13, M-18, M-22 and M-32 on the text after that and passed them, M-18 with a caveat on a cutoff inside a period (N-01); both are corrected. Q re-ran M-15–M-19 on the text after that and passed them, and so did R, S and T on the texts after that; T's text differed from the final one only by the wording corrections U-01–U-04. The rows below are the result on the final text.

| Scenario | Result against the final text |
| --- | --- |
| M-01 | PASS — both dispatches meet at LK-13; the second re-reads AVAILABLE 0 and is REJECTED (PX-03, C-04) with the current state; one movement, one consumption, ON HAND 0; the CHECKs are the backstop; never both, never −1 |
| M-02 | PASS — as M-01 through AX-01: one ACTIVE reservation, the other REJECTED (C-04) |
| M-03 | PASS — a dispatch consumes only its own line's reservation plus AVAILABLE, both read under LK-13 |
| M-04 | PASS — the application compares every basis version under LK-12 to LK-14; a moved scope gives CONFLICT and the STALE mark, and no stock row changes (ST-03; C-33) |
| M-05 | PASS — LK-13, then the serial row by its natural key, and a conditional transition: the second command is REJECTED (C-02) |
| M-06 | PASS — the candidate snapshot is taken under the scope and lot locks; nothing is retained; a resolution whose lots were consumed meanwhile is REJECTED (C-37); the pending CHECK excludes a double effect |
| M-07 | PASS — AX-34 under the protocol: a consumption committed after derivation forces a restart with its project; Σ attributed = the corrected cost |
| M-08 | PASS — the duplicate waits at LK-00 or is found by the replay probe; one payment; the second receives the same COMMITTED result after re-authorization |
| M-09 | PASS — the replay table answers a different fingerprint with CONFLICT; nothing new commits |
| M-10 | PASS — the retry replays the COMMITTED result, also after re-authentication (HO-02), for a command that removed its own target (the probe runs before resolution and again before a not-found answer) and for a commit left in doubt (SQ-17) |
| M-11 | PASS — the line's evidence guard under LK-29: the second is REJECTED (C-19) |
| M-12 | PASS — the second submission queries under LK-07 after the first committed and is REJECTED (L-22) with the match; it commits only with the ADM+ override, its reason and the acknowledged match; the warning stays a warning |
| M-13 | PASS — another account gets CONFLICT with no state; the original actor after a revocation gets the AZ-11 denial before any content check; a deactivated one is unauthenticated, as the qualification above records |
| M-14 | PASS — both meet at LK-25 and LK-26; a contra after a new application is CONFLICT (ST-07); an application or refund after the contra commits only within what is left (C-13); an application that resolves a shortfall takes the case at LK-10 first, recorded with a payment or alone (AX-18, AX-19) |
| M-15 | PASS — each issuer holds its own version row, then the counter: N distinct consecutive numbers |
| M-16 | PASS — insert-or-lock on the counter of a live period: one row, numbers 1 and 2 |
| M-17 | PASS — the increment is inside the savepoint; a rolled-back issue consumes nothing |
| M-18 | PASS — a seeded period gives its next number; an unseeded pre-cutover period — before the first seed, between two, or a month inside a seeded year — begins on or before the seed boundary, so it is not live, may create no counter and is REJECTED (C-10), consuming nothing; a type without a seed is refused, because a numbering scheme creates no counter; a period before an Owner's start is refused until it is seeded, a later import whose cutoff falls inside a period may not seed it, and a scheme may not re-map dates in the past |
| M-19 | PASS — the second issuer finds the version ISSUED under LK-30 and is CONFLICT with the issued number; no number is consumed |
| M-20 | PASS — command and revocation take the account's row in turn; the command that held it first stands, the next one is denied; a revocation that outwaits TX-09 is FAILED and repeated |
| M-21 | PASS — one transaction revokes grants and tokens, deletes sessions and withholds the account's export requests; a session written by a racing login is refused and deleted; a reactivation deletes sessions again |
| M-22 | PASS — LK-02 serializes both; at least one active Owner remains; the second is REJECTED (RG-09) when its actor still has the authority and is otherwise denied, as the qualification above records |
| M-23 | PASS — the revocation marks the request WITHHELD in its own transaction, whether it is READY or still being generated, and the download re-checks: the AZ-11 answer, the file removed |
| M-24 | PASS — the account's row lock and one conditional update: one password set, the other submission gets the generic rejection |
| M-25 | PASS — two unauthenticated verifications at once, a wait of at most 250 ms, a slot of its own for step-up and password change; no account locked; fail closed when the store is down |
| M-26 | PASS — both hold LK-01; a document or evidence change reaches the projects of linked requirements, and a command that reopens a closed case holds the projects that case names; never a stale completion |
| M-27 | PASS — the hash over the three snapshot documents with their amounts is recomputed under LK-01: CONFLICT |
| M-28 | PASS — one total order for the declared sets, the protocol for the data-dependent ones, no foreign-key lock ahead of the order, `40P01` retried as the backstop |
| M-29 | PASS — a born row: GU-02; a first-use row: the proof of first use; both FAILED (SQ-18) with nothing committed; a seed that would re-create a lost counter below a number issued here fails the same kind of proof, and a lost counter never moves the seed boundary |
| M-30 | PASS — insert-or-lock at the canonical position: one row, both effects |
| M-31 | PASS — the claim under the version lock adds at most one attempt; a second claimant that meets a live render costs one wasted attempt, never a second document; the render reads only the issued row; one READY |
| M-32 | PASS — the durable row and its sweep: JB-02 for renditions, JB-09 for exports and import chunks, a dispatch lost after a resume included |
| M-33 | PASS — committed rows are found done; a refused row is recorded on the import row only and runs again; no duplicate effect |
| M-34 | PASS — the action stays committed (SQ-21); the sweeps resume every kind of owed work; limiters as H6-07 |
| M-35 | PASS — LK-21 and BD-01: one ORIGINAL receipt per reference, the other CONFLICT; partial receipts stop at the cap (C-29) |
| M-36 | PASS — receipt and closure serialize at LK-21; the second is REJECTED (C-29) |
| M-37 | PASS — LK-01, then LK-25; ST-07; every disposition in one action (C-16); outstanding never negative (C-15) |
| M-38 | PASS — the aggregate root's `lock_version`: the stale edit is CONFLICT |

**Re-test: 38 of 38 PASS.**

## Approved-document amendments (TECH-021)

Four approved documents are amended, narrowly and at Level 1, as DIR-032 §15 allows where approved text would otherwise contradict the P6 design. Every clause is marked `TECH-021`; each document keeps its APPROVED status and carries an amendment note after its Approval line; each pre-amendment SHA-256 equals its approval record and the committed blob.

| Document | Clauses added | Approved text it reconciles | Pre-amendment SHA-256 |
| --- | --- | --- | --- |
| DATABASE | §4: the export-request record among the tables outside the count; §4.13 `command_log`: the state `CONFLICT`, and its actor and company recorded without foreign keys; §19: the purge of expired `command_log` rows; §20: a pointer to the index register; §23: the audit rows of a stale count and of a routed refusal; §25: deferred checks are forced as the last step before COMMIT, inside the savepoint; §26: a pointer to the confirmed order and the refined mapping | the enumeration of excluded tables; a CHECK list that could not store a terminal CONFLICT (SF-CMD step 6); the convention that an `<entity>_id` column is a foreign key, which would have made the first statement of every command take a lock ahead of the order; "every other table grants the application role no DELETE at all", against the log retention §26 hands to P6; "keyset pagination on (`business_date`, `id`) or (`id`)"; "a rolled-back command leaves no audit row"; "run at the COMMIT of the same transaction"; the mapping in general terms | `88091B62CD3061DF14027AF11A0C6F39CCF2A46792377103F12BEF9EA4E14531` (APPR-006) |
| ARCHITECTURE | the amendment note; §5: the mapping refined per code, and what a refusal commits; §11: the sweeper also finds an abandoned RENDERING attempt, an export request is recorded on the `export_requests` record instead of a job-batch record, and a pointer to the job register | "anything else → failure"; "PENDING or FAILED renditions"; "tracked by the framework's job-batch records"; the list of six jobs | `82D31BC1644758C6ABC26C9ADEA7E75A91F68BE6395A8A629640F5BC7EE6A8C8` (APPR-005) |
| WORKFLOWS | the amendment sentence; §1: a note on how a command's technical failure is answered | "for background work only, PENDING/FAILED", against DIR-032 §8-F, §8-H and §8-I, which answer a command's technical failure with FAILED | `A608C529D7C69FFD7A23929AE1959262F4F370961541FE30ADF7EF1D6FD1ECD3` (APPR-005) |
| PERMISSIONS_MATRIX | the amendment note; §7.2: the export-request record among the records that are never projected raw | the closing enumeration of §7.2 | `BB085B2E7A4C4A46637500F4DFF224B8CA1ECA94DF5CDA50D2DE1F28D65C7D8C` (APPR-006) |

No business meaning, authority, visibility, workflow, numbering guarantee or date rule changes: each clause points to a mechanism and restates none. The PERMISSIONS_MATRIX clause says of the new operational record only what DP-07 and SECURITY FL-09 already give every export — it is the requester's alone. SECURITY is used exactly as approved and is not amended: where the verification pass found the FL-10 lookups inside a command transaction, the design was changed to meet H6-09 in its letter (V-08). The amended revisions are approved under APPR-007.

## Separate Fable review

**Separate Fable review not recommended.** None of the four triggers of DIR-032 §17 is present. (a) The one HIGH finding (G-02) concerned a handoff sentence on command identity after re-authentication; it was corrected in HO-02 and every later round confirmed it, and no CRITICAL finding arose. The number allocator took the most rounds: after the review, the verification pass and the final check, nine further rounds of fresh reviewers checked the corrections — eight of them on NM-03, five with in-memory models of their own — and every finding they made was LOW or Level-1, none CRITICAL or HIGH, and was corrected and checked again; the last full round found nothing above Level-1 in about 406,000 simulated issues, and the narrow checks after it corrected wording and one import identity. (b) The reviewers' one disagreement (G-01) was settled by adopting the stricter reading, so no conflict remains. (c) Every amendment is a narrow technical clarification marked `TECH-021`. (d) Lock-order acyclicity and data-dependent conformance are checked mechanically over the declared lock sets ([Static validation](#static-validation)). A later targeted review would add value at two points, not now: when P8 has real concurrent PostgreSQL tests — for the data-dependent sets of AX-34, AX-06, AX-07 and AX-37, AX-18–AX-20 and AX-33, the fan-out of document changes, and the number allocator's seeds and boundary across imports, starts and changes of granularity — and when P9 fixes versions and configuration, for the behaviours this design records as source-level or inferred.

## Owner-visible Level-1 choices

Each is delegated by DIR-032 §11 or applies an approved rule, and is named here so the approval is informed:

- **Brief waits.** Every command of a project holds that project's row, commands of one account run one after another, and commands that start new business for one company — issuing a document, creating a project or a purchase — run one after another (D-CC-02, D-CC-04). At DEP-09's scale the waits are assumed to be short; none refuses work.
- **Lock waits end in FAILED, which is safe to repeat.** An interactive command waits at most 3 s for a lock, a cascade 5 s, an access change 20 s; then it is answered FAILED with nothing saved, and repeating it cannot double an effect (TX-01–TX-10; HO-01).
- **A refusal is remembered.** A REJECTED or CONFLICT command keeps that outcome for its `command_id`; a corrected request is a new one (CI-06). Commands are remembered for 30 days (CI-07); a daily job then deletes the expired records, under a delete right that the `TECH-021` clause of DATABASE §19 limits to expired rows (JB-08). One exception, which changes no effect: a command driven by an import or a job records only its success, so a refused import row runs again once its cause is removed instead of replaying the refusal (CI-08).
- **Duplicate warnings.** The payment warning looks seven days either side of the business date for the same company bank account and amount — a starting value to confirm in UAT (BD-02; HO-35). An override or confirmation must name the matches it acknowledges. A cash payment has no company bank account, so the approved warning does not reach it; extending the warning to cash would be an Owner decision and is not made here.
- **Evidence duplicates.** The duplicate lookups for an uploaded document run before its registration transaction, as SECURITY H6-09 and DIR-032 §8-G require. So that two uploads of one document cannot both miss the warning, the second waits for the first on a registration key — also when two companies register the same bill (BD-15; FU-10).
- **While the queue, cache and limiter store is down** nobody can log in, confirm a step-up or change a password; open sessions keep working and committed work is never lost (D-CC-10; H6-07). This is SECURITY's fail-closed rule; P9 may shorten such an outage.
- **A login flood** is held to two password verifications at a time, each waiting at most a quarter of a second; step-up and password change keep a slot of their own (H6-12).
- **Numbers — starting and seeding.** A company cannot issue a numbered document of a type until that type has a seed boundary: the cutover's seeds, or — for a company or a type no import covers — the Owner's explicit start, recorded on or after go-live and stating the first number of the current period under each granularity its numbering schemes use in that period. Setting up a numbering scheme alone starts nothing. A document backdated into a period that has no counter and is not live is refused rather than restarted at 1 (NM-03): a period wholly before go-live stays refused until a migration seeds it, as DATABASE §10 says, and any other such period until the Owner seeds it with the first number to use. Every seed — the migration's or the Owner's — must follow every number its period received outside the system, whatever that number's date, and the Owner vouches that nothing is numbered outside once the start is recorded. The numbering command never raises an existing counter; only an import seed raises one, with an alert if numbers were already drawn below it. Go-live is recorded once at the cutover and never changes.
- **Numbers — schemes and imports.** Once a type has its boundary, a numbering scheme cannot take effect in the past, except to cover dates no scheme covered yet, and a scheme whose first period began on or before the boundary and has no counter needs, in the same step, the Owner's first number for that period; a scheme that takes effect on the day it is recorded splits that day's documents between two series. Every import batch carries the cutoff-period seeds of every numbered type of every company in its scope, unless the type's boundary is later than its cutoff, and a later import with a later cutoff moves the boundary, so periods up to it without a counter then need seeds. A start is refused while an import batch still holds seed rows for the type — the Owner commits or aborts that batch first — and a type's numbering schemes are settled before its import's dry run: a later scheme that the batch would leave without a seed is refused. Lot codes are ten random characters (H6-10).
- **Dates.** Every command carries its business date; a date that the form only prefilled and that is no longer today comes back for confirmation instead of backdating silently (ST-08).
- **Counts.** A count whose scope has moved is marked STALE and must be recounted (ST-03), as the approved workflow says.
- **Sessions.** Background requests never extend a session; a download the user starts does (RV-06). A password change ends every other session of the account, and so does the first login after P9 changes the password-hashing parameters (RV-10).
- **Revocation.** Revoking a grant or deactivating an account waits for that account's command in flight and withholds at once every export of the account that its grants no longer cover, finished ones included (RV-02, RV-08).
- **Routed refusals.** A cross-company action refused for lack of a grant leaves audit records; the routed-blockers queue shows them for 14 days as notices, not as tasks with a state (SQ-23).
- **Replays after revocation or deactivation.** A retry by an actor whose grant was revoked gets the AZ-11 denial; one whose account was deactivated has no session any more and is answered as unauthenticated. Nothing is disclosed either way (M-13).
- **Two Owners acting on each other.** When two Owners try to demote or deactivate each other at once, the first succeeds and the second is denied for lack of authority rather than REJECTED under RG-09; one active Owner always remains (M-22).
- **Evidence.** Replacing evidence that is already in use is two steps — register, then make current (NX-07, NX-14).
- **Exports.** At most two requests per account in progress, as SECURITY sets, and one export running at a time; an export is tried at most three times and then reported FAILED to its requester, who may ask again. Only the requester sees the status of a request; the Owner sees each export's request and download in the security log (SECURITY LG-02; H6-04; JB-03).
- **Search.** A name search that matches more than 200 rows says that its list is cut and asks for a narrower term; names that equal the term or begin with it are always shown first (PERFORMANCE PF-21).
- **Two dashboard signals** — renditions still owed and bank lines not fully cited — are computed from the open rows of all companies and then filtered to the actor's scope; nothing outside scope is shown (PERFORMANCE PF-24).
- **Two items of the Owner review queue have no stored record yet.** P5 promises the Owner a list of evidence duplicates the uploader may not see and of import batch collisions outside the preparer's scope; nothing in the approved model records them. P6 registers this as GAP-034 and hands it to change control before P7 designs that queue (HO-38). It needs no Owner decision.

## Gap reconciliation

P6 continuation notes are recorded in GAP-003/004/005/006/007/008/009/010/012/013/015/016/017/022/023/030/031 and were brought in line with the fixes after the review and again after the verification pass. DIR-032 §10 lists fifteen of them; GAP-004 and GAP-010 also name P6 mechanisms in the repository and received notes. No gap is closed — runtime proof belongs to P8, P9 or P10. One gap is new, because the verification pass found one genuinely new failure scenario: **GAP-034**, the two Owner-review items without a stored record. The other new scenario the review found — a routed refusal without a recorded source (QS-20) — is closed by design (SQ-23). Totals: **34 findings — 3 CLOSED, 30 OPEN, 0 OWNER_DECISION_REQUIRED, 1 ACCEPTED_RISK**.

## Deferred obligations (P7/P8/P9/P10/P11)

Recorded in [CONCURRENCY_IDEMPOTENCY §19](../CONCURRENCY_IDEMPOTENCY.md#19-handoff-obligations); none is implemented now and no file of a later phase is created.

- **P7 (HO-01–HO-10, HO-38, HO-39):** truthful presentation of the five answers and of a routed refusal; one command identity per intent, kept across re-authentication; double-submit; stale and conflict handling, with "already recorded" never offered as a re-apply; duplicate confirmations that name their matches; background requests; retry after FAILED; the business date and whether it was chosen; export states; lists, search and scanner patterns within the PERFORMANCE rules; and, before the Owner review queue is designed, the record GAP-034 asks for; the start and seed confirmations of the numbering command with the premises the Owner vouches for.
- **P8 (HO-11–HO-20, HO-35):** the 38 scenarios as real-PostgreSQL tests with `ReconcileGuards` clean after each; lock-order conformance from the locks actually held; the database and framework behaviours recorded as source-level or inferred; replay and retention cases; budgets, plans and load evidence; limiters and the verification cap; job tests; numbering and dates with a controlled clock; the UAT confirmation of the duplicate window.
- **P9 (HO-21–HO-30):** timeouts and retry budgets; connection, worker and queue sizing; scheduler and deployment hygiene for limiter and lock keys; the store's availability, sizing and eviction; monitoring and alerts; Argon2id and slot tuning; `command_log` retention and its purge; reconciliation after restore; cleanups; version re-verification.
- **P10 (HO-31–HO-34, HO-36):** mixed-version deployment; the cutover order and counter seeds; import chunking and resume; draining cascades and reconciling after a deployment; planner statistics after the import.
- **P11 (HO-37):** the style-source question SECURITY WS-08 leaves open.

## Static validation

A read-only validation script kept outside the repository, following the prior gates, checked: every relative link and anchor in all Markdown files; lifecycle metadata and approval lines; the 28 source records against SOURCE_OF_TRUTH, the 27 earlier records unchanged, record 28's size, LF endings and `-text` attribute; every P6 identifier defined once, referenced beyond its definition (ranges expanded) and contiguous in its family; the lock order — AX-01–AX-37 each with a lock-set row, every declared set strictly ascending over defined classes with the project row at position 1 after the identity row, and the graph of hold-then-acquire edges over all declared AX and NX sets acyclic; every data-dependent AX covered by the five-step protocol; the first-use rows the AX rows cite; AX-01–AX-37 each with a checks-and-effects row; M-01–M-38 each with scenario, mechanism, end state, outcomes and P8 obligation; H6-01–H6-12 each with a mechanism row; every DATABASE §3.1 guard with a lock position; every cited identifier of the owning documents resolving in its owner; gap triage against detail sections and the totals sentences; every OWNER_DECISION_REQUIRED value; this gate's coverage of every ⚡ C-row of DATABASE §18, every §3.1 guard, AX-01–AX-37, M-01–M-38 and H6-01–H6-12; the changed-path census and forbidden P7+ or application paths; a secret-like scan and a placeholder scan of every added or new line; `git diff --check` and trailing whitespace; the four amended documents changed only in their `TECH-021` lines, their approval pointer and their date, with their pre-amendment SHA-256 equal to the committed blobs; the other approved specifications, earlier gates and ADR-001 unchanged except P5_QUALITY_GATE's dated addendum; approval pointer lines; APPR-007 recording the normalized SHA-256 of its approved files; the handoff naming the checkpoint and P7 NOT STARTED; table column counts and code fences.

**Results (final run, 2026-10-01) — PASS, 66 of 66 checks:** 768 relative links and anchors resolve across 36 Markdown files; lifecycle metadata valid — CONCURRENCY_IDEMPOTENCY, PERFORMANCE and API_AND_INTEGRATIONS APPROVED with their APPR-007 approval lines, this gate REVIEW; 28 source records match their recorded hashes, the 27 earlier ones are byte-identical to HEAD, and record 28 is 1,415 lines / 58,143 bytes with LF endings and `-text`; 415 P6 identifiers in 27 families, each defined once, referenced beyond its definition and contiguous; 37 lock-set rows, every declared set ascending over defined classes with the project row at position 1, and 270 hold-then-acquire edges, none backward; all 17 data-dependent actions under the five-step protocol; the cited first-use rows defined; 37 checks-and-effects rows; 38 complete scenario rows; 12 H6 rows; all 22 §3.1 guards with a lock position; 324 distinct cited identifiers resolving in their owners; DIR-032, OBS-011, TECH-021 and APPR-007 each with an index row and a detail section, and the P5 checkpoint SHA recorded literally; the gap register with 34 rows and 34 sections, its totals sentence equal to the recount everywhere, and every OWNER_DECISION_REQUIRED value 0; this gate covering all 21 ⚡ C-rows, every §3.1 guard, AX-01–AX-37, M-01–M-38 and H6-01–H6-12; 25 changed paths, all within the census, and no P7+ or application file; no secret-like content or placeholder in 3,318 added or new lines; `git diff --check` clean; DATABASE, WORKFLOWS, ARCHITECTURE and PERMISSIONS_MATRIX changed only in their `TECH-021` lines and their date, with their pre-amendment SHA-256 equal to the committed blobs; the other approved specifications, earlier gates and ADR-001 unchanged except P5_QUALITY_GATE's dated addendum; CLAUDE.md and CHANGE_CONTROL changed only in their approval pointer line and their date; APPR-007 recording the normalized SHA-256 of its seven approved files; every approval pointer carrying APPR-007; the stale AGENT_OPERATING_MODEL sentence removed; the phase chain naming P6 by DIR-032; and the handoff naming the P6 checkpoint commit and P7 NOT STARTED. After staging, the staged blobs were checked again, as the checkpoint procedure requires.

## Changed-file census

25 paths — 20 modified (`.gitattributes`, AGENTS.md, CHANGELOG.md, CLAUDE.md, README.md, AGENT_OPERATING_MODEL, CHANGE_CONTROL, DECISION_LOG, ENGINEERING_PRINCIPLES, GAP_REGISTER, PROJECT_CHARTER, SOURCE_OF_TRUTH, PERMISSIONS_MATRIX, WORKFLOWS, ARCHITECTURE, DATABASE, P5_QUALITY_GATE, CURRENT_STATE, NEXT_ACTION, CONTEXT_INDEX) and 5 new (source record 28, CONCURRENCY_IDEMPOTENCY, PERFORMANCE, API_AND_INTEGRATIONS and this gate). DATABASE, ARCHITECTURE, WORKFLOWS and PERMISSIONS_MATRIX change only by their `TECH-021` amendments, their approval pointer and their date; P5_QUALITY_GATE only by its dated addendum; CLAUDE.md and CHANGE_CONTROL only in their approval pointer line and their date, following the APPR-004/005/006 precedent. SECURITY is unchanged. The P6 finalization checkpoint commits exactly these 25 paths.

## Gate result and limitations

**P6 planning-quality result: PASS.** Every DIR-032 §8 item is covered and every obligation above is designed or handed to its owning phase; all self-review and independent findings are fixed or recorded as deferred obligations; the re-test passes 38 of 38. **APPR-007 conditions verified:** CRITICAL, HIGH, MEDIUM and LOW unresolved = 0; OWNER_DECISION_REQUIRED = 0; no separate Fable review triggered under DIR-032 §17; all 38 mandatory scenarios documented with mechanism and end state, and the re-test and all validation PASS; the statically known lock sets are acyclic by mechanical check, and every data-dependent set follows the derive → lock → re-verify → restart protocol; every action that can change a completion predicate anchors on the project row at position 1; no known contradiction remains — every approved sentence the P6 design refines carries its `TECH-021` clause; P0–P5 approved business, authorization and security semantics are preserved, the only approved-text changes being the narrow `TECH-021` amendments; P6 remains documentation only; no P7 work exists. Limitations: documentation evidence only — no application, measurement, concurrency test, load, runtime or device evidence exists or is claimed; every budget is an assumption until P8 measures it (GAP-017); the PostgreSQL and framework behaviours marked source-level or inferred are P8 proof obligations (HO-14, HO-20) and are re-verified when versions are chosen (DEP-07; HO-30); two items of the Owner review queue wait for the record GAP-034 asks for (HO-38); deadline feasibility remains unproven (GAP-014).

## Exact next safe action

Wait for the Owner's explicit authorization of P7 — UX, Information Architecture & Design System ([NEXT_ACTION](../../handoff/archive/NEXT_ACTION_2026-10-02.md)). At P7 entry, verify that local HEAD equals live origin/main at the P6 checkpoint — resolved by `git log -1 --format='%H %s' --grep='^docs: finalize P6 concurrency, idempotency and performance$'`, parent `b09e70f3a867d58b431c7ae0369b7a432c4fd1a1` — and record its SHA literally. No P7 work before that; before P7 designs the Owner review queue, the record GAP-034 asks for is settled under change control (HO-38).

**Addendum (2026-10-01):** this action was discharged by [DIR-033](../../00-governance/DECISION_LOG.md#dir-033-obs-012-and-tech-022--p7-authorization-entry-baseline-and-ux-documentation): P7 was authorized, and its entry observation OBS-012 verified and recorded the P6 checkpoint `ff92c415c164f9fea3758fead256a2df53a74211` and its publication; this gate otherwise stays unchanged as P6 evidence.

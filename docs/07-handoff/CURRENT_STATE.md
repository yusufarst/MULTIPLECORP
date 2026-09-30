# Current state

Status: REVIEW | Updated: 2026-09-30 | Owner: Planning

This is the live handoff. It is written to be complete for an agent with no chat history: read [AGENTS.md](../../AGENTS.md), [CONTEXT_INDEX](../CONTEXT_INDEX.md), this file and [NEXT_ACTION](NEXT_ACTION.md), then verify the repository before acting.

## Target and phase

- Release: MultipleCorp — Company Management System, operational production V1, target 2026-10-15 ([charter](../00-governance/PROJECT_CHARTER.md)). Internal, project-centred operations for a group of legal companies sharing one physical warehouse with company-attributed cost ([product overview](../01-product/PRODUCT_OVERVIEW.md)).
- **Completed phases:** P0 governance APPROVED (APPR-001); P1 product and V1 scope APPROVED (APPR-002); P2 domain model and business rules APPROVED (APPR-003); P3 critical business workflows APPROVED (APPR-004); **P4 database and application architecture APPROVED (APPR-005), checkpointed and published**; **P5 security and authorization APPROVED (APPR-006) and checkpointed**.
- **P4 checkpoint:** `e95d083d7114a1d0c43f6e9cb6a439c93a70134b` (`docs: finalize P4 database architecture`), whose parent is the P3 checkpoint `7c6549e88ba8538aa6e08d0fb9720589705e1dd0`, published to origin/main by a normal non-force push and verified equal to live origin/main at the zero-context handoff ([OBS-009](../00-governance/DECISION_LOG.md#dir-030-obs-009-and-tech-019--zero-context-handoff-acceptance-and-p4-checkpoint-record)). Earlier checkpoints: P2 `1392966bfb89581d705e0394424705978e1d3db8`, P3 `7c6549e88ba8538aa6e08d0fb9720589705e1dd0`.
- **Zero-context handoff: ACCEPTED** (DIR-030, OBS-009, TECH-019). A newly attached Claude Project session reconstructed P0–P4 from this repository alone, found no material contradiction and recorded the checkpoint above in the continuity commit `docs: record P4 handoff checkpoint` (parent `e95d083`).
- **P5 — Security & Authorization: COMPLETE** under the Owner's fast-track authorization DIR-031 (source record 27): [PERMISSIONS_MATRIX](../02-domain/PERMISSIONS_MATRIX.md) and [SECURITY](../03-architecture/SECURITY.md) APPROVED under APPR-006, together with the narrow `TECH-020` amendment of DATABASE §4.13; [P5_QUALITY_GATE](../03-architecture/P5_QUALITY_GATE.md) is REVIEW evidence.
- **P5 checkpoint:** the commit `docs: finalize P5 security and authorization`, resolved by `git log -1 --format='%H %s' --grep='^docs: finalize P5 security and authorization$'`, whose parent is the handoff continuity commit `6e640137901e0d16193e03004e142e9ea07b39ad`. The P5 task performs one normal non-force push of it to origin/main (DIR-031 §20); publication is not self-attested — the next task verifies it against live origin/main and records its SHA literally (a commit cannot contain its own hash).
- **P6 — Concurrency, Idempotency & Performance: NOT STARTED**; it requires the Owner's explicit authorization.
- Last completed implementation unit: none; no application, migration or infrastructure exists.

## Canonical specifications (all APPROVED)

| Concern | Document | Approval |
| --- | --- | --- |
| Product purpose, users, journey | [PRODUCT_OVERVIEW](../01-product/PRODUCT_OVERVIEW.md) | APPR-002 (DIR-024 amendment APPR-004) |
| V1 scope: 18 MUST capabilities, 14 document types, exclusions, local-only backup contract | [V1_SCOPE](../01-product/V1_SCOPE.md) | APPR-002 (DIR-024 amendment APPR-004) |
| Observable acceptance | [ACCEPTANCE_CRITERIA](../01-product/ACCEPTANCE_CRITERIA.md) | APPR-002 |
| Domain concepts, scope and sensitivity classes | [DOMAIN_MODEL](../02-domain/DOMAIN_MODEL.md) | APPR-003 (amendments APPR-004, APPR-005) |
| Business rules, lifecycles, calculations, numeric annex | [BUSINESS_RULES](../02-domain/BUSINESS_RULES.md) | APPR-003 (amendments APPR-004, APPR-005) |
| Workflows, authority matrix, correction matrix, AX-01–37 | [WORKFLOWS](../02-domain/WORKFLOWS.md) | APPR-004 (DIR-027 amendment APPR-005) |
| Logical database: 124 tables in 13 modules, guard registry, C-01–C-61, AX transaction map, P6 handoff | [DATABASE](../03-architecture/DATABASE.md) | APPR-005 (TECH-020 amendment APPR-006) |
| Application structure: modular monolith, dependency tiers, actions, posting services, boundaries | [ARCHITECTURE](../03-architecture/ARCHITECTURE.md) | APPR-005 |
| Authorization: capabilities, role and grant model, company scope, field projection, cross-company links, Owner-only denials, data paths | [PERMISSIONS_MATRIX](../02-domain/PERMISSIONS_MATRIX.md) | APPR-006 |
| Security: authentication, sessions, credentials, enforcement, web, files, logging, secrets, threat model, P6–P9 handoffs | [SECURITY](../03-architecture/SECURITY.md) | APPR-006 |

Evidence and living records stay REVIEW by convention: the P0–P5 quality gates, REFERENCE_COVERAGE, GAP_REGISTER, DECISION_LOG, CHANGELOG and this handoff pair.

## Binding inputs (never reopen without the Owner)

DIR-009 local-only backup on the production VPS with the accepted total-host-loss exposure (RISK-001); DIR-011 five financial concepts, physical-versus-financial visibility, completion authority, reservation contract and auditable inter-company allocation; DIR-018 dates, backdating and numbering; DIR-019/020 write-off, dispute, SIPLAH and intermediary fee settlement; DIR-024 D-1–D-5 (NET tax, purchase charges in cost, loss once, pre-payment Kuitansi, Owner-only/ADM+ authority); DIR-026 Owner attribution of unexplained cross-company fungible loss and its DIR-027 clarification (physical truth corrected at once, immutable recognition snapshot, no freezing of stock); the P2/P3 PLANNER-DETERMINED decisions; Indonesian UI; mobile-first plus desktop productivity; default-deny access; near-zero incremental cost; the Git attribution rule (DIR-022). OWNER_DECISION_REQUIRED = 0.

## P4 finalization record

- **DIR-028** (source record 25): a targeted read-only Fable red-team, "database integrity under concurrency and correction", reported NEEDS CORRECTION BEFORE P4 APPROVAL — 0 CRITICAL, 2 HIGH, 15 MEDIUM, 20 LOW, 0 OWNER_DECISION_REQUIRED (RT-01–RT-37).
- **DIR-029** (source record 26) authorized the corrections, the conditional approval, one checkpoint commit, a normal push and this continuity freeze. **TECH-018** verified all 37 findings valid and corrected them, five further defects found during disposition and 36 found by an independent consistency review of the corrections; the 14-scenario adversarial re-test and the static/governance validation passed; **APPR-005** approved DATABASE and ARCHITECTURE with the `DIR-027`-amended WORKFLOWS, BUSINESS_RULES and DOMAIN_MODEL revisions.
- Main corrections: cost corrections move every cumulative posted-value, charge, source-bill and case guard (RT-01); refund-backed consumption stays binding so a payment correction never frees capacity beyond real money (RT-02); net-zero movements, separate replacement caps, per-company and overlap-group exposure guards for unattributed loss, settlement transitions with residual exits, aging-safe replacement invoices, typed void bases, role-bound evidence types, pool evidence, corrective receipts, drop-ship reversals, company-scoped document bank accounts, scoped command keys and an honest DB+APP+DQ guarantee layer (RT-03–RT-17); the guard registry with an audited maintenance rebuild and the remaining LOW items (RT-18–RT-37). Dispositions, re-test and validation: [P4_QUALITY_GATE](../03-architecture/P4_QUALITY_GATE.md).

## P5 finalization record

- **DIR-031** (source record 27) authorized P5 on the fast-track pattern. OBS-010 verified the entry baseline; TECH-020 created PERMISSIONS_MATRIX, SECURITY and P5_QUALITY_GATE, applied the DIR-031 §5 continuity cleanup and the narrow DATABASE §4.13 amendment, and added GAP-032 and GAP-033; **APPR-006** approved PERMISSIONS_MATRIX and SECURITY with the amended DATABASE revision.
- Design: server-side authorization in the order authentication → company scope → capability → resource → preconditions; 80 capabilities (ADM 37, ADM_PLUS 26, OWNER_ONLY 17) over all 27 WORKFLOWS §4 rows; the OWNER role holds every capability and each Admin capability is an individual grant; the projection of all 124 tables with physical-only pooled views, a non-identifying relation marker, opaque lot codes and no itemized movements of companies outside scope; five cross-company link families; 16 Owner-only denial rules including the server-verifiable DIR-027 evidence conditions; 20 data paths; Argon2id, a 15-character minimum, throttling without lockout, PostgreSQL sessions (60-minute idle, 12-hour absolute, no remember-me), Owner-issued out-of-band credential links under DEP-08 with credential-event notices, operator Owner recovery and an MFA seam; 58 threat scenarios; the P6–P9 handoffs.
- Reviews: 13 self-review findings; two independent adversarial reviews (CRITICAL 0, HIGH 0, MEDIUM 9, LOW 19, LEVEL-1 14, 2 deferred obligations, OWNER_DECISION_REQUIRED 0), all fixed or recorded; an independent verification of the fixes and a final check of those fixes (together MEDIUM 1, LOW 5, LEVEL-1 20, 1 deferred obligation, OWNER_DECISION_REQUIRED 0), all fixed or recorded; re-test 27/27 and validation PASS; separate Fable review not recommended. Dispositions: [P5_QUALITY_GATE](../03-architecture/P5_QUALITY_GATE.md).

## Repository state

- Remote: `https://github.com/yusufarst/MULTIPLECORP.git` (public — never commit real data, secrets or credentials). Branch `main` tracks `origin/main`.
- Published HEAD at P5 entry: the handoff continuity commit `6e640137901e0d16193e03004e142e9ea07b39ad` (`docs: record P4 handoff checkpoint`), whose parent is the P4 checkpoint `e95d083d7114a1d0c43f6e9cb6a439c93a70134b`, verified equal to the local origin/main ref and to live origin/main by `git ls-remote` with a clean tree and index (OBS-010). A new session re-verifies local HEAD == live origin/main before acting and reports any difference.
- Line endings: the Owner's Windows checkout keeps CRLF working copies of CHANGELOG, DECISION_LOG, DATABASE and ARCHITECTURE whose committed blobs are LF. A Git without CRLF normalization (for example a Linux shell over the same folder) lists them as modified although each equals its blob after CR removal; confirm with `git diff --ignore-cr-at-eol --stat` (empty) before reporting a dirty tree, and stage with CRLF normalization (for example `git -c core.autocrlf=input add`) so Markdown blobs stay LF.
- Known stale wording outside the DIR-031 cleanup scope, reported under TECH-020: AGENT_OPERATING_MODEL's "The current assignment authorizes P1 only". It only narrows authority; AGENTS.md, this file and NEXT_ACTION govern the current phase, and a later Owner-authorized cleanup may correct it.
- Git rules: commits use only the Owner's configured identity with no AI/model/tool attribution or co-author trailer (DIR-022); pushes are normal and non-force; on divergence stop and report; historical commits are never rewritten.

## Gaps and accepted risks

- [Gap register](../00-governance/GAP_REGISTER.md): **33 findings — 3 CLOSED, 29 OPEN, 0 OWNER_DECISION_REQUIRED, 1 ACCEPTED_RISK** (P5 added GAP-032 and GAP-033). Every OPEN gap is a technical or evidence obligation of a later phase, not a pending business choice.
- Accepted risk: GAP-018 / RISK-001 — V1 backups live only on the production VPS; total VPS, disk, provider or account loss may leave no recovery copy (DIR-009).
- Most consequential open gaps: GAP-006 visibility and field projection — designed in P5, denial proof in P8; GAP-033 the Owner account without a second factor (V1 mitigations proven in P8/P9; MFA only by a later Owner choice); GAP-008, GAP-009 and GAP-031 retry, races and guard drift (P6/P8); GAP-011 and GAP-013 local backup proof and operational ownership (P9); GAP-010 migration overlap and cutover (P10); GAP-014 deadline feasibility, still unproven.
- Deferred P5 obligations ([SECURITY §10](../03-architecture/SECURITY.md#10-handoff-obligations)): **P6** H6-01–H6-12 mechanisms; **P7** H7-01–H7-14 presentation; **P8** H8-01–H8-15 denial and security tests; **P9** H9-01–H9-12 operations.
- Deferred P4 obligations (DATABASE §31; P4_QUALITY_GATE): the **P5** items — field projection, the DIR-027 evidence-resolution authority, evidence duplicate warnings — are designed in PERMISSIONS_MATRIX (PJ-01–PJ-23, OD-10, DP-13) and SECURITY (FL-10); **P6** extended lock order, the completion anchor, zero-row guard updates as failures, race-safe creation of first-use guard and counter rows, mechanisms for every corrected AX action; **P8** adversarial fixtures for the targeted-review findings, numbering, reconciliation and serial-state tests; **P9** a separate migration database connection and role, owner/TRUNCATE privilege handling, guard verification after restore.

## Not authorized

P6–P11 design, migrations, executable SQL, Laravel or React files, packages, Docker or deployment files, infrastructure, production access, editing any locked source record, reopening the binding inputs above, force pushes or history rewriting, and AI attribution in commits.

Next safe action: [NEXT_ACTION](NEXT_ACTION.md).

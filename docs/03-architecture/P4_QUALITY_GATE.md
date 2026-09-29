# P4 adversarial review and quality gate

Status: REVIEW | Updated: 2026-09-30 | Owner: Planning

Scope: P4 — Database Architecture, authorized and fast-tracked by DIR-027 (twenty-fourth source record) on the published P3 baseline `7c6549e88ba8538aa6e08d0fb9720589705e1dd0`, red-teamed under DIR-028 (twenty-fifth source record) and corrected, approved and checkpointed under DIR-029 (twenty-sixth source record; TECH-018). This report records planning evidence for [DATABASE](DATABASE.md) and [ARCHITECTURE](ARCHITECTURE.md), for the narrow `DIR-027` amendments and for the APPR-005 approval conditions. DATABASE and ARCHITECTURE are APPROVED under [APPR-005](../00-governance/DECISION_LOG.md#appr-005--p4-database-architecture-approved); this gate stays REVIEW as evidence, following the P2 and P3 gate convention. It is not implementation acceptance and not permission to begin P5. No application, migration, runtime, concurrency or recovery evidence exists or is claimed.

## Baseline verification (OBS-007)

Verified at task entry: branch `main` tracking `origin/main`, ahead/behind 0; HEAD `7c6549e88ba8538aa6e08d0fb9720589705e1dd0` equal to the live `refs/heads/main` of `https://github.com/yusufarst/MULTIPLECORP.git` by `git ls-remote`; clean tree and index; no merge, rebase or cherry-pick state; no P4 file; OWNER_DECISION_REQUIRED = 0. The repository matched the Owner's expected baseline, so work proceeded. P3 is CHECKPOINTED and PUBLISHED (OBS-007 in DECISION_LOG).


## Finalization baseline (OBS-008)

Verified at DIR-029 entry, before any edit: branch `main` tracking `origin/main`, ahead/behind 0 after a fetch; HEAD `7c6549e88ba8538aa6e08d0fb9720589705e1dd0` equal to the live `refs/heads/main` by `git ls-remote`; exactly the 19 uncommitted P4 paths (15 modified, 4 new); empty index; no merge, rebase or cherry-pick state. The repository matched the Owner's expected baseline, so the finalization proceeded.

## Inputs read

AGENTS, CLAUDE, README, CHANGELOG, CONTEXT_INDEX, CURRENT_STATE, NEXT_ACTION; all P0 governance documents including SOURCE_OF_TRUTH, DECISION_LOG, GAP_REGISTER, CHANGE_CONTROL, PROJECT_CHARTER, AGENT_OPERATING_MODEL and ENGINEERING_PRINCIPLES; PRODUCT_OVERVIEW, V1_SCOPE, ACCEPTANCE_CRITERIA and the REFERENCE_COVERAGE requirement index; DOMAIN_MODEL, BUSINESS_RULES, WORKFLOWS, P2_QUALITY_GATE and P3_QUALITY_GATE in full; the Owner brief sections that own P4 concerns (§§2–6, 9–15, 17–19, 24–38, 45); the latest Owner transcripts (source records 19–24), their format and hashes; ADR-001.

**Finalization intake (DIR-029 §2).** The targeted reviewer reported that it had not opened README, CONTEXT_INDEX, SOURCE_OF_TRUTH, V1_SCOPE and P3_QUALITY_GATE. Before any correction they were read in full, and DATABASE, ARCHITECTURE, this gate, BUSINESS_RULES, WORKFLOWS, DECISION_LOG, GAP_REGISTER, CURRENT_STATE and NEXT_ACTION were re-read, together with the red-team report and its directive recovered from the review session. None of the five unread files invalidates a finding: V1_SCOPE CAP-08 ("applicable unique numbers") and BR-DOC-02 confirm RT-29; SOURCE_OF_TRUTH's registry lists PERMISSIONS_MATRIX and SECURITY as planned P5 paths, confirming that RT deferrals to P5 stay deferred; P3_QUALITY_GATE's scenario 2 (payment contra after a refund) confirms the business meaning RT-02 protects; README and CONTEXT_INDEX only needed their status updated. One finding needed a repository-truth adjustment rather than the reviewer's literal fix: L-45 defines evidence identity with the document type, so RT-11 is corrected by binding types to citing roles instead of removing type from the identity.

## DIR-027 clarification applied

The Owner's clarification supersedes only the P3 retention safeguard (approved L-42 and SF-UNATTRIBUTED), which could reject an unrelated dispatch while a pending unexplained-loss case awaited attribution. Applied narrowly, each change marked `DIR-027`:

| File | Changed text | Pre-amendment SHA-256 (HEAD `7c6549e`) |
| --- | --- | --- |
| WORKFLOWS | amendment note; §1 marker; §4 authority rows (Owner resolution; ADM+ only for a resolution fully identified by definitive evidence); §5.3 note; WF-INV-03 exception list; SF-UNATTRIBUTED; AX-03, AX-06, AX-37; QS-22; L-42; §12 introduction; residual-command list; §13 P5/P6 obligations | `370F6F5662AD0C2D704B56F7844E63A55A62B07C05599FB59F72B9861CA018C8` |
| BUSINESS_RULES | amendment note; one added `DIR-027` sentence in BR-INV-13 | `E39FB763DDCC5D632CFBB8330898F297608ECDFDC779212F0F29C647307D66A8` |
| DOMAIN_MODEL | amendment note; one clause in the loss-expense relationship row | `BA71393C79B46208D8F70C80DBDB71C55BCE6FBB0AA92262BD48E2A9D72BC9B6` |
| P3_QUALITY_GATE (REVIEW evidence) | a dated addendum; historical sections unchanged | — |

**Non-reopening check:** the validation script confirms every added line of the three approved files carries `DIR-027` (metadata lines excepted); WORKFLOWS still defines 39 workflows, 18 subflows, 8 partial rules, 38 correction rows, 37 indivisible actions, 22 signals and 50 Level-1 decisions. No other P3 rule, approval or PLANNER-DETERMINED decision changed. The three files keep APPROVED status with amendment notes (DIR-024 precedent); the amended revisions are reviewed with this package.

**Conservation under the clarification** (DATABASE §5.8): (1) physical truth is corrected immediately by lot-less ledger entries of the case; (2) because the scope guard includes those entries and stays ≥ 0, Σ lot claims in the scope ≥ the pending quantity under every later receipt, dispatch, move or return, so a resolution can always close enough claims; (3) the resolution closes the assigned company's own claims first and any shortfall from other lots with an inter-company allocation, so lot cost is conserved exactly and later movements are never rewritten; (4) the recognition snapshot is INSERT-only; (5) found stock reverses the pending quantity (no recognition) or the recognized loss exactly; (6) the generated pending quantity ≥ 0 blocks double finding, resolution or reversal. **Recorded consequence:** when later dispatches consumed the assigned company's claims, the substitution values that company's loss at the substituted lot's cost; company totals stay conserved while the project-level HPP/loss split follows the recorded movements — exactly the "does not rewrite subsequent stock movements" rule of DIR-027 item 9. P3 final-verification scenario 6 re-run under DIR-027 (all dispatches proceed; conservation holds in both assignment variants) is recorded in DATABASE §5.8 and the P3 gate addendum. This is Owner-decided meaning, not a Level-1 relaxation.

## Canonical-file completeness

| DIR-027 §5 question | Answered in |
| --- | --- |
| What persistent records exist? | DATABASE §4 (124 logical tables in 13 modules; 119 at the first draft, five added by the targeted-review corrections) |
| Authoritative versus derived? | DATABASE §1 classes, §3, §3.1 guard registry |
| Relationships? | DATABASE §4 keys, §15 composite keys and the inventory of intentional cross-company links, §29 diagrams |
| Constraints protecting invariants? | DATABASE §18 (C-01–C-61, with the DB / DB+APP+DQ / APP / P6 / DQ layers) |
| Immutable? Revisable or superseded? | DATABASE §1, §13, §28 |
| Unique? | DATABASE §4, §18, §26 |
| Historical snapshots? | DATABASE §14 |
| Append-only? | DATABASE §1, §28 |
| Multi-company scope? | DATABASE §15 |
| Shared warehouse versus company attribution? | DATABASE §5.1, §5.8, §6.5 |
| Exact money and quantities? | DATABASE §7, §17 |
| Corrections without destructive history? | DATABASE §13, §19 |
| Migrations and opening facts? | DATABASE §24 |
| Indexes and query structures? | DATABASE §20, §21 |
| Actions needing P6 concurrency mechanisms? | DATABASE §25, §26 (⚡ rows of §18) |
| Modular-monolith mapping? | ARCHITECTURE §3–§7 |

Owner topics §9–§36 of the authorization each have a home: source-of-truth classification (§3), inventory (§5), lot/cost (§6), multi-unit (§7), serial (§8), project/commercial (§9), documents (§10), finance (§11), tax (§12), receivable/payment conservation (§11.2–§11.4), unattributed loss (§5.8), correction (§13), snapshots (§14), company scope (§15), identifiers (§16), types (§17), constraint catalogue (§18), delete policy (§19), indexes (§20), search (§21), files (§22), audit (§23), migration (§24), application architecture (ARCHITECTURE), transaction boundaries (§25), idempotency boundary (§26), scale (§27), security-ready design (§28). P4_QUALITY_GATE is created because every earlier phase keeps its evidence in its own gate beside its canonical documents; it is registered in SOURCE_OF_TRUTH and owns no rule.

## Traceability verification

- **Capabilities:** CAP-01–18 each map to P2 rules, P3 workflows and actions, P4 structures and constraints, and later obligations in DATABASE §30 (18/18; CAP-14 is presentation-only and CAP-16 is P9, each with a stated reason).
- **Acceptance contracts (through their capabilities):** AC-01 §4.2 · AC-02 §4.3 · AC-03 §4.4, §7, §8 · AC-04 §4.5, §9 · AC-05 §4.6, §6 · AC-06 §5, §6 · AC-07 §4.9 · AC-08 §10 · AC-09 §4.11 · AC-10 §11 · AC-11 §6, §11 · AC-12 §13 · AC-13 §15, §28 · AC-14 N/A for schema (P7) · AC-15 §4.5 · AC-16 §5.2, §18 ⚡ rows, §26 · AC-17 §24 · AC-18/19 §22 metadata, P9 · AC-20 §5.7, §9 · AC-21 §9 · AC-22 §5 · AC-23 §14, §20 — 23/23.
- **Documents:** DOC-01–14 each have a subject in the `documents` exclusive arc and a rendering business version (DATABASE §4.10, §10) — 14/14.
- **Owner decisions:** OWN-01–06, DIR-018/019/020, DIR-024 D-1–D-5 and DIR-026/027 each map to a section (DATABASE §30).
- **P2 E-DB rules:** all 15 rules tagged E-DB in BUSINESS_RULES map to database guarantees (DATABASE §30).
- **P3 indivisible actions:** AX-01–37 each have a transaction row (DATABASE §25) and a business identity or guard (§26) — 37/37.
- **Reference and image identifiers** stay attached through the P1 and P3 layers (REFERENCE_COVERAGE, P3_QUALITY_GATE); the P4-specific references are carried explicitly: REF-021/022 (§5, §6.5), REF-026 (§5.2), REF-036 (§10), REF-050 (§21), REF-057/058 (§25, §26), REF-059 (§20).
- **Orphan check:** no V1_REQUIRED capability, document type, acceptance contract, E-DB rule or indivisible action lacks a P4 home; the script checks identifier resolution across the P4 documents, WORKFLOWS, the gap register and the handoff (Static validation below).

## Invariant, relationship and duplicated-truth checks

- **Invariant-to-constraint mapping:** C-01–C-61 assign every invariant the Owner listed (unique barcode, serial identity, non-negative quantities, reservation bounds, document numbers, payment conservation, evidence caps, charge conservation, source-bill cap, receivable reductions, one-time loss, cross-company attribution, company scope, immutable issue/revision, migration and idempotency boundaries) and every targeted-review correction to DB, DB+APP+DQ, APP+DB, APP, P6 or DQ layers; 25 rows rely on a guard coupled to its facts by the posting path and are labelled DB+APP+DQ, never "DB" alone. Cross-row equations are never CHECKs: they are guard rows, deferred constraint triggers or reconciliation queries.
- **Relationships and orphan foreign keys:** every table named in DATABASE and in ARCHITECTURE's posting-service map exists in the catalogue; module counts equal their sections; the 18 guard-class tables (16 GB, one SR + GB and one SN + GB) plus the GB-like serial state, reservation remainder, correction-case counters and the authoritative number counters are the 22 rows of the §3.1 registry, each with a formula, its discriminators and one normal writer in ARCHITECTURE §7; `GuardMaintenance` is the single audited exceptional writer.
- **Duplicated truth (PASS 1):** no stored receivable, credit, paid state, stage, aging bucket, supplier price history or profit; one row family per money concept (§11.1); HPP and loss in one ledger; allocation as an overlay; document payloads presentation-only; guards classified as controlled materializations with rebuild rules (§3.1; GAP-031).
- **Delete/archive matrix:** DATABASE §19 covers every family the Owner listed and all other table families; draft-only deletion is enforced by BEFORE DELETE triggers.
- **Snapshot matrix:** DATABASE §14 covers every element the Owner listed (company and issuer identity, client/unit/PIC/address, supplier, description, unit and ratio, prices, tax, SPJ/admin and SIPLAH values, bank destination, channel metadata, document content, counted basis) and states what is deliberately live.
- **Transaction boundaries:** DATABASE §25 lists 37/37 actions with every fact and guard that commits together, re-audited after the corrections; ARCHITECTURE §15 states the no-network/no-PDF/no-external-call rule.
- **Index and query coverage:** DATABASE §20 serves every query need the Owner listed — barcode, SKU, serial, product/client/supplier/project search, open projects, availability, reservations, receiving/dispatch history, project documents, invoice status, active receivables, aging, payment lookup, company/date reporting and audit history — plus the QS queues and the correction lineage added by the targeted review.

## Adversarial database review

Six passes were run against the draft, as DIR-027 §39 requires; every confirmed finding was fixed in the documents before this record was written. No Fable model was used (DIR-027 §43). All findings are Level-1 technical; none changes business meaning, so **OWNER_DECISION_REQUIRED = 0**.

| ID | Pass | Finding | Disposition |
| --- | --- | --- | --- |
| P4-F01 | 1 Duplicate truth | Recording a loss both on the stock consumption and as an expense row would store the same cost twice and let corrections update only one | Fixed: one cost ledger (`cost_attributions`) holds every lot draw with class HPP/LOSS/SUPPLIER_RETURN; consumptions carry quantity only (DATABASE §6) |
| P4-F02 | 1 | A separate inter-company allocation table could drift from the consumption it describes | Fixed: allocation is an overlay on the consumption and cost rows (D-DB-14; C-28) |
| P4-F03 | 1 | Stored receivable, credit or paid-status tables would compete with the facts | Fixed: derived views plus guard rows only (§3) |
| P4-F04 | 1 | Issued document payloads repeat invoice amounts | Fixed: payloads are presentation snapshots; no computation reads them (§10) |
| P4-F05 | 2 Inventory | A nullable `revision_suffix` inside the document-number UNIQUE key would let two issued rows share a number | Fixed: `revision_suffix` NOT NULL DEFAULT 0 (§4.10) |
| P4-F06 | 2 | "Stock from nothing" through a mis-typed movement | Fixed: movement type copied onto ledger entries through a composite key and sign CHECKs per type (§5.3; C-07) |
| P4-F07 | 2 | A drop-ship return to the warehouse had no valid restoration source (restorations could only cite stock consumptions) | Fixed: restoration source is an exclusive arc of consumption or drop-ship confirmation line (§4.7) |
| P4-F08 | 2 | Without retention (DIR-027), later dispatch could consume the claims an Owner attribution needs | Resolved by design: scope guard ⇒ Σ claims ≥ pending; resolution closes own claims first, shortfall with allocation (§5.8) |
| P4-F09 | 2 | A pending unexplained-loss case could be opened on a serialized product, whose units are always identified | Fixed: composite key to `products.is_serialized` with CHECK false (§4.7) |
| P4-F10 | 3 Financial | A non-attributable charge would cite its source bill twice (charge row and its expense row), halving the bill's usable cap | Fixed: an expense created from a charge cites no bill (CHECK) — the charge cites it once (§4.8; C-20) |
| P4-F11 | 3 | The one-settlement-per-type key used nullable type columns, which PostgreSQL treats as distinct | Fixed: `specific_type_key` NOT NULL in the partial UNIQUE key (§4.12; C-22) |
| P4-F12 | 3 | A bank-line identity with a nullable reference would allow duplicate lines and double Cash-In | Fixed: `line_reference` NOT NULL (§4.10; C-19) |
| P4-F13 | 3 | Opening receivables stored as invoices would inflate live revenue and consume the project invoice cap | Fixed: origin OPENING excluded from CALC-08 and the cap, per BR-XD-06 (§24) |
| P4-F14 | 3 | A transfer payment without a destination account would escape the bank-line reconciliation | Fixed: CHECK bank account required unless CASH (§4.12) |
| P4-F15 | 4 History | A settlement contra must mark the settlement inactive on an INSERT-only table | Fixed: column-level UPDATE grant on that one state column (§28) |
| P4-F16 | 4 | Issued documents, invoice versions and SENT quotation lines live in updatable tables | Fixed: immutability triggers on issued columns plus privileges (§10, §28; C-11) |
| P4-F17 | 5 Tenancy | Evidence, document-subject, requirement-link and case references lacked company keys | Fixed: the composite-key rule extended to every company-owned reference except three explicit cross-company links (§15) |
| P4-F18 | 5 | Showing a lot's source company or a consumption's project in the pooled view could reveal another company's business | Routed: physical-only query classes never select S3 tables; field exposure is P5's decision (ARCHITECTURE §8) |
| P4-F19 | 6 Implementability | Procurement ↔ Finance dependency cycle through expenses; Projects ↔ Inventory through reservation adjustments | Fixed: expenses owned by Costing; tiered dependencies; three inversion contracts (ARCHITECTURE §3) |
| P4-F20 | 6 | Thirteen guard tables are a drift and implementation risk in an 18-day window | Mitigated in design: one `GuardUpdate` helper, a single writer per guard, verify commands, P8 reconciliation; recorded as GAP-031 |
| P4-F21 | 6 | Committing a large opening import in one transaction would be long and fragile | Designed: each row atomic and idempotent by import identity; chunking decided by P6/P10 (§24–§25) |
| P4-F22 | 6 | Identity-version tables for every master would add work without integrity gain | Simplified: payload snapshots plus immutable file objects and bank-account rows (D-DB-09) |
| P4-F23 | 2 | Converting a pending condition-change case into a loss case left its pending quantity in the unusable scope, away from the unclosed usable claims | Fixed: conversion reverses the condition-change entries and records the loss case in the usable scope (DATABASE §5.8) |
| P4-F24 | 6 | Lower-tier DOC and OPS were listed as implementers of a Projects contract — an upward dependency | Fixed: Projects reads DOC and OPS directly; only higher tiers implement its contracts (ARCHITECTURE §3) |

**Attacks that fail against the final design (PASS 2–5):**

| Attempt | Blocked by |
| --- | --- |
| Stock from nothing | increase-only movement types (C-07); one lot per acquisition (C-08); restorations capped by consumption (C-27) |
| Negative AVAILABLE; last unit sold twice | product guard CHECK (C-04); lot and scope CHECKs (C-03) |
| Duplicate lot cost | partial UNIQUE basis entries per lot; charge shares unique per lot; lot-cost guard (C-26) |
| Duplicate serial | UNIQUE identity and conditional state transition (C-02) |
| Lost provenance | lot provenance INSERT-only; consumption source company keyed to the lot (C-08, C-28) |
| Cross-company leakage | composite company keys (C-14, C-32); no money in physical tables |
| Unresolved loss corrupting availability | lot-less entries inside the guards; pending ≥ 0 (C-35); snapshot immutable (C-36) |
| Duplicate Cash-In | command identity, bank-line cap (C-19, C-48) |
| Application beyond payment | payment-source guard (C-13) |
| Duplicated tax/fee evidence | evidence identity and cap (C-18); active-settlement key (C-22) |
| Duplicate purchase charge | source-bill cap cited once (C-20) |
| Disappearing receivable | receivable guard with four reduction columns only (C-15, C-16) |
| Duplicate write-off | receivable guard; Owner-only authority (C-15, C-40) |
| Credit becoming profit | credit derived; income only through categorized other Cash-In (§11.1, §11.5) |
| Cost counted twice | one INITIAL attribution per source (C-25); charge allocated or expensed (C-21); fee expense once (C-23) |
| Erasing or silently mutating an issued document, invoice, payment, movement, loss, allocation or settlement | INSERT-only privileges, immutability triggers, correction rows only (C-11, C-53; §13) |
| Company B's financial row referencing company A's | composite keys (C-14, C-32) |

## Owner-decision preservation

DIR-009 (local-only backup), DIR-011 (five financial concepts, visibility boundary, completion authority, reservation contract, auditable allocation), DIR-018 (dual dates, business-date numbering periods, append-only sequences), DIR-019/020 (write-off, dispute, reconstructed payments, fee settlement generalization), DIR-024 D-1–D-5 and DIR-026 are represented without change; DIR-027 is applied as written. The Level-3 exclusions stay out: no general ledger or journal, no enterprise WMS, no supplier comparison, SIPLAH only as channel metadata, no automatic inter-company invoices or cash transfers, no database per company, no microservices or event sourcing. No tax rate, valuation method or tax algorithm is introduced (FS-13). The P2/P3 PLANNER-DETERMINED decisions are untouched except L-42, whose retention clause the Owner superseded. DIR-028 and DIR-029 require no Owner business decision: every targeted-review finding was a Level-1 technical correction, and none changes an approved business meaning.

## Independent read-only review

After the self-review, two independent read-only reviewers (Opus sub-agents, not Fable; no file changed by them) attacked the draft: one on inventory, lot cost, losses, allocation and unattributed loss; one on finance, documents, numbering, tax, tenancy and module dependencies. Every finding was re-verified against BUSINESS_RULES, WORKFLOWS and the draft before editing; **all 23 were valid Level-1 schema defects** (1 CRITICAL, 8 HIGH, 12 MEDIUM, 2 LOW) and all were fixed. None changes business meaning, so none needed the Owner.

| ID | Class | Verified finding | Fix (DATABASE unless noted) |
| --- | --- | --- | --- |
| FR-01 | CRITICAL | A refund pointed at one application through an immutable column: a legal payment contra (CM-19) could not commit, or left the moved application unprotected so a reallocation could spend refunded money; a refund backed by two payments was unrepresentable | Applications target the refund; `money_fact_balances.backed_amount ≤ net`; transition trigger with the three legal exits of L-46; the shortfall is a posted case counter (§4.12, §11.3; C-44) |
| FR-02 | HIGH | INSERT-only privileges contradicted updates the design needed: late bank-line citation, late remittance advice, import-row states, unlink columns, draft invoice lines | One-shot column grants with transition triggers; claimed gross derived from claimed deductions; import rows and link tables reclassified SR; invoice lines SN once issued (§1, §4, §28) |
| FR-03 | HIGH | The §15 composite keys could not be built: parents lacked UNIQUE (company, id) and several children lacked `company_id` | Company-keys convention for every company-owned table and child row; composite subject keys on documents (§1, §4.10, §15) |
| FR-04 | HIGH | CM-24 settlement residuals were unrepresentable; re-recording could expense a fee twice; a fully contra'd payment could keep active settlements | RESIDUAL state; evidence `expensed_amount ≤ proven_amount`; CHECK (`net_amount > 0 OR settled_amount = 0`); posted residual counter (§4.12, §11.4; C-22/C-23) |
| FR-05 | MEDIUM | Applications were fully updatable (amounts, double flips); contras were bounded only for payments | State-only column grant with a one-shot trigger and conditional update; `money_fact_balances` caps contras of every money fact (C-45) |
| FR-06 | MEDIUM | Several root billing acts per invoice; inherited dates unenforced; no typed void basis, so aging could restart | Partial UNIQUE root act; composite key on inherited dates; `void_basis` CHECK and replacement trigger (C-41, C-56) |
| FR-07 | MEDIUM | A counter "rebuilt" from issued numbers lost seeds and retired numbers; unseeded legacy periods restarted at 1; two root drafts; UNIQUE over nullable arc columns bound nothing | Authoritative SR counters never lowered, created only for seeded or post-go-live periods; one ISSUED and one DRAFT version per chain; per-type partial UNIQUE subjects (§10; C-10/C-11) |
| FR-08 | MEDIUM | The pre-payment Kuitansi amount lived only in the payload; receipt and pre-payment issues in different periods could race; unlinks missing from AX rows | Typed `kuitansi_amount`; both modes lock the invoice guard; unlinks in AX-19/20/22/33 (C-43) |
| FR-09 | MEDIUM | A positional bank-line identity let overlapping statements duplicate a receipt; applications and expenses lacked command uniqueness after log expiry | Bank reference or running-balance identity; universal UNIQUE (`command_id`, `command_line_no`) (C-19, C-48) |
| FR-10 | MEDIUM | The treatment-rule exclusion ignored global rules (NULL company); precedence undefined; settlements and remittances lacked the rule applied; tax expenses uncapped | `coalesce(company_id, 0)` exclusion and company-over-global precedence; `treatment_rule_id` snapshots; evidence expense cap (§12; C-52) |
| FR-11 | MEDIUM | Document voids, revisions and evidence replacements needed higher-tier revalidation (a dependency cycle); import commit sat in tier 0 | `DocumentChangeParticipant` contract; import orchestration moved to tier 7 (ARCHITECTURE §3) |
| FR-12 | LOW | A written-off invoice looked paid; no recovery link; a transfer-in could not be applied | *Paid* requires no net write-off; `recovery_for_invoice_id`; the transfer's Cash-In in company B may be a client payment (§3, §11.3, §11.5) |
| IR-01 | HIGH | Reversals had no database cap: a wrong decrease could be undone twice; a restoration reversal could exceed its restoration; drop-ship returns were uncapped | Consumption-backed movements reversed only by capped restorations; MOVEMENT_REVERSAL typed and capped by `stock_reversal_balances`; drop-ship lines in the consumption guard (§5.3; C-55) |
| IR-02 | HIGH | A return after a loss-in-transit reclassification used a base that already included the loss, recognizing 150,000 from 100,000 | Contra base = net cost of the reversed class over the open quantity; fixture added (§6.2; C-27) |
| IR-03 | HIGH | Positive lot-less quantity of a pending condition change could back another case in an unusable scope, leaving it unresolvable; found stock on a condition-change case; candidate rows insertable later | Scope guard separates claims, pending reductions and additions with `claims ≥ reductions`; explicit split rule; found only on LOSS cases; exposure fixed at recognition (§5.8; C-03, C-35, C-36) |
| IR-04 | HIGH | An attribution recorded in error (CM-27) had no representation | UNATTRIBUTED_RESOLUTION_REVERSAL as exact inverse; `supersedes_resolution_id` (§5.8; C-37) |
| IR-05 | HIGH | Per-receipt rounding of charge shares could overshoot (third receipt rejected) or strand Rp1; partial receipts of box-priced lines had no split rule | Cumulative rounding with posted-value columns on the purchase-line guard (§6.1; C-21) |
| IR-06 | MEDIUM | A charge recorded after consumption landed on later consumers or was rejected on a fully consumed lot | LATE_CHARGE attributions for consumed units in the same transaction; lot-cost guard in AX-29/32 (§6.1) |
| IR-07 | MEDIUM | One movement per command prevented atomic counts that yield several cases or movements | (`command_id`, `command_line_no`); movements cite their count (§4.7) |
| IR-08 | MEDIUM | Cost rows were not keyed to their consumption, so a contra could drop an allocation or name another lot; the case company key was ambiguous | Composite key (consumption, lot, source); bearer equality trigger with the RETURN_SETTLEMENT exception; `case_company_id` (§4.8, §6.5; C-28) |
| IR-09 | MEDIUM | Receiving, dispatch and drop-ship lines and lots lacked the entered-unit snapshot BR-INV-03 requires | Entered snapshot with CHECK on every quantity-bearing row (§7; C-09) |
| IR-10 | MEDIUM | The purchase-return equation checked typed copies; RETURN_* adjustments could re-enter the cost cascade; replacement receipts were indistinguishable | Posted guard counters; cascade limited to *_CORRECTION kinds; `is_replacement` with REPLACEMENT_BASIS and a replacement cap (§6.1; C-46) |
| IR-11 | LOW | Circular lot/receiving keys; per-statement CHECK ordering; basis uniqueness per kind; lot-less entries on any movement; guards missing from some AX rows | One-direction key; single-statement net deltas; one basis per lot; lot-less type CHECK and case key; AX rows completed (§1, §4, §25) |

The fixes themselves were checked by the static validation below, not by a second independent review; the targeted review recommended below should concentrate on them.

The targeted review below re-examined these fixes and found the regressions listed under *Previous-fix regressions*; all are corrected.

## Targeted Fable review (DIR-028)

A read-only Fable red-team, authorized by the Owner in a separate session (twenty-fifth source record), attacked the uncommitted package for "database integrity under concurrency and correction": guard balances, inventory and reservations, lots and cost, the DIR-027 unattributed-loss case, payments and refunds, settlements, receivables, evidence identity, numbering, corrections, company isolation, uniqueness, transaction boundaries, trigger and privilege realism and the earlier fixes. It changed no file. **Verdict: NEEDS CORRECTION BEFORE P4 APPROVAL — CRITICAL 0, HIGH 2, MEDIUM 15, LOW 20, OWNER_DECISION_REQUIRED 0.** It confirmed the core design: the last-unit race, application conservation, receivable conservation, the no-freeze loss model and issue/render separation held.

Under DIR-029 every finding was re-verified against the canonical files before editing; **all 37 were valid**, none was applied mechanically, and none needed the Owner.

| ID | Class | Verified finding | Disposition (TECH-018) |
| --- | --- | --- | --- |
| RT-01 | HIGH | After a partial receipt, a price or charge correction moved lot cost but not the purchase line's cumulative posted values, so the next receipt over-posted (12 @ 100,000 corrected to 120,000: lots 128,333); charge, source-bill and case figures were omitted and a CLOSED return case would reject the correction | Confirmed. Posted values defined net of reversals and corrections beside the current corrected goods and tax values; per-line `purchase_charge_share_balances`; AX-34 moves line, charge, charge-share, source-bill, lot-cost, consumption and case guards together; replacement basis follows the returned cost; an unbalanced CLOSED case reopens in the same transaction; one REVALIDATE per project; worked example (DATABASE §4.6, §4.8, §6.1, §6.3, §25; C-57) |
| RT-02 | HIGH | A Rp1 payment contra could release a whole 200,000 refund backing and free 199,999 of capacity although the refunded money had left; a refund could be created unbacked; the exits relied on untyped "same transaction" and "re-recorded payment" | Confirmed. Source guard with gross, net, invoice applied, refund backed, released and shortfall and a generated capacity; CHECK that a source with a shortfall holds no invoice application and no capacity; refund guard backed + shortfall = net, released ≤ the refund's contra, shortfall ⇒ residual case; typed exits (`end_reason`, `end_contra_id`), `resolves_application_id`, `rerecords_payment_id`; worked example proving no extra 199,999 (DATABASE §4.12, §11.3; C-13, C-44) |
| RT-03 | MEDIUM | A LOCATION_MOVE of −5/+6 raised ON HAND by 1; RESTORATION and COUNT_VARIANCE signs were unconstrained | Confirmed. Per-row sign CHECKs extended; deferred net-zero constraint trigger per movement and group key (DATABASE §5.3; C-07) |
| RT-04 | MEDIUM | A lot's source company and a consumption's bearer were not keyed to the records that caused them | Confirmed. Composite provenance keys from lots to receiving line, import row, restoration or finding; bearer keyed to the dispatch line or resolution line; purchase-return bearer = source (DATABASE §4.7, §6.5; C-08, C-28) |
| RT-05 | MEDIUM | Replacement headroom admitted normal receipts (ordered 10, 8 received, 2 returned: four more normal receipts passed), posting 120% of line value | Confirmed. Separate normal and replacement caps; cumulative rounding on the normal quantity (DATABASE §4.6, §6.1; C-29) |
| RT-06 | MEDIUM | The per-company recognition-exposure cap had no database guard | Confirmed. `unattributed_loss_exposures` per (case, company) with assigned ≤ exposure; resolution lines keyed to it (DATABASE §4.7, §5.8; C-37) |
| RT-07 | MEDIUM | Overlapping cases in one scope reused the same claims: cases of 2, 3 and 2 each snapshot A = 5, letting A absorb 7 | Confirmed. Prior pending recorded with recognized ≤ exposure − prior pending; overlap-group bound per company, raised at each recognition to member exposure + group assignment already committed, never lowered; feasibility argument (DATABASE §4.7, §5.8; C-36, C-37) |
| RT-08 | MEDIUM | RESIDUAL settlements could never exit ("allows one change"), so the case and normal completion stayed blocked | Confirmed. Transition matrix ACTIVE → RESIDUAL, ACTIVE → CONTRAED, RESIDUAL → CONTRAED with guard effects and the residual-resolution posting (DATABASE §4.12, §11.4; C-60) |
| RT-09 | MEDIUM | A replacement invoice could reset aging with a BILLING root, no act, or an act copied from a superseded act | Confirmed. Inherited act keyed to the replaced invoice through (`invoice_id`, `inherited_from_invoice_id`) → (`id`, `replaces_invoice_id`) and to its dates; deferred constraint trigger requiring an INHERITED root citing the replaced invoice's current act (DATABASE §4.12, §11.2; C-17, C-41) |
| RT-10 | MEDIUM | The void basis reference was untyped and downward revisions had no typed basis | Confirmed. Typed exclusive arcs for `void_basis` and `revision_basis` (DATABASE §4.12; C-56) |
| RT-11 | MEDIUM | Evidence type was not bound to its citing role: one fee advice as FEE_ADVICE and OTHER gave two caps | Confirmed; corrected within L-45, which keeps type in the identity: UNIQUE (`id`, `evidence_type`), composite citation keys and role CHECKs; the cross-type duplicate warning is P5 (DATABASE §4.10; C-58) |
| RT-12 | MEDIUM | Pool-scope evidence (pending cases, pool movements, import batches) had no valid company key | Confirmed. `evidence_scope` POOL with no company for a closed type list; pool arcs by plain keys; pool evidence files likewise (DATABASE §4.10; C-58) |
| RT-13 | MEDIUM | The receiving business identity rejected a CM-04 supplement from the same delivery note and a CM-02 re-receipt after reversal | Confirmed. `receipt_kind` with `corrects_receiving_line_id`; UNIQUE only over ORIGINAL receipts (DATABASE §4.7; C-49, C-59) |
| RT-14 | MEDIUM | Drop-ship corrections (CM-15, CM-27) had to cite a warehouse movement that never existed | Confirmed. Movement-less DROPSHIP_SUPPLIER_RETURN and DROPSHIP_CONFIRMATION_REVERSAL restorations under the confirmation's consumption cap; AX-10 row (DATABASE §4.7, §5.7; C-27, C-59) |
| RT-15 | MEDIUM | A document's bank destination lived only in the payload, so a Company B invoice could print Company A's account | Confirmed. Typed `company_bank_account_id` on `document_versions` with the company composite key (DATABASE §4.10, §10, §15; C-32) |
| RT-16 | MEDIUM | One-row-per-command keys blocked a correction revalidating two completed projects and multi-document issue | Confirmed. (`command_id`, `project_id`), (`issue_command_id`, `document_id`) and scoped keys on cancellations, closures and write-offs (DATABASE §1, §4; C-11) |
| RT-17 | MEDIUM | "Enforced by PostgreSQL" overstated guarantees that rely on the posting path coupling facts and guards | Confirmed. DB+APP+DQ layer defined and applied to 25 catalogue rows; wording corrected in DATABASE §1, §4, §5.2, §18 and ARCHITECTURE §7 |
| RT-18 | LOW | Rebuild formulas were incomplete: no goods/tax discriminator on reversal entries, drop-shipped serials without ledger entries, an unnamed second writer | Confirmed. `cost_component` discriminator; §3.1 registry of formulas, discriminators and writers; serial rule including drop-ship events; `GuardMaintenance` as the single audited exceptional writer (DATABASE §3.1; ARCHITECTURE §7; C-61) |
| RT-19 | LOW | A dispatch's reservation was not keyed to its project line | Confirmed. Composite (`reservation_id`, `project_line_id`) (DATABASE §4.7) |
| RT-20 | LOW | Quantity scale was enforced only on ledger entries | Confirmed. Scale key and CHECK on every quantity-bearing fact row (DATABASE §1, §7; C-09) |
| RT-21 | LOW | The count basis lived in JSON | Confirmed. Typed `stock_count_scopes` (DATABASE §4.7, §5.6; C-33) |
| RT-22 | LOW | A condition-change resolution could be reversed twice | Confirmed. Partial UNIQUE on the resolution reversal (DATABASE §4.7, §5.8; C-35) |
| RT-23 | LOW | Conversion of a condition-change case into a loss case was under-specified | Confirmed. UNATTRIBUTED_CONVERSION movement with explicit rows; successor case with copied snapshot, same group, no bound raise (DATABASE §5.3, §5.8) |
| RT-24 | LOW | Exposure, recognized quantity and scope sat on an updatable row | Confirmed. Column grants limited to the four counters (DATABASE §4.7; C-36) |
| RT-25 | LOW | The `is_serialized` composite key blocked the L-35 switch for any product with case history | Confirmed. Recognition-time trigger; no historical key on the flag (DATABASE §4.4, §4.7, §8) |
| RT-26 | LOW | A wrong claimed deduction or bank-line citation could only be fixed by contra of the whole payment | Confirmed. Superseding claimed-deduction rows; reasoned citation correction moving both line guards (DATABASE §4.12, §11.3, §11.4; C-59) |
| RT-27 | LOW | One transfer-out could back two Cash-Ins; kind and amount were unchecked | Confirmed. Kind-typed composite key and `transfer_cited_amount` cap (DATABASE §4.12, §11.5) |
| RT-28 | LOW | Bank statement lines had no void path (checksum warning deferred to P5) | Confirmed. VOIDED state proving 0, only once uncited; checksum warning DEFERRED TO P5 (DATABASE §4.10; C-19) |
| RT-29 | LOW | "Gapless" over-claimed; a discarded draft project left an unexplained hole | Confirmed (BR-DOC-02 and V1_SCOPE CAP-08). Guarantees restated; `retired_numbers` tombstone (DATABASE D-DB-12, §4.2, §10; C-10) |
| RT-30 | LOW | Opening receivables needed an impossible "INHERITED-style" act; MIGRASI had no due date | Confirmed. OPENING act kind, due date optional only for MIGRASI (DATABASE §4.12, §24; C-41) |
| RT-31 | LOW | Delivery and handover reversals were uncapped; the reverse of AX-32 was unmapped; a superseding confirmation was blocked | Confirmed. Exact, once-only reversal lines; superseded closure posts the exact inverse; one root confirmation per revision with a superseding chain (DATABASE §4.5, §4.6, §4.9, §13; C-30, C-59) |
| RT-32 | LOW | §15 undercounted the cross-company links; an invoice's client was not keyed to its project's client | Confirmed. Audited inventory of five link families; (company, project, client) key (DATABASE §15; C-32) |
| RT-33 | LOW | Barcodes had no normalized key | Confirmed. Generated `code_key` with partial UNIQUE (DATABASE §4.4; C-01) |
| RT-34 | LOW | A restoration's ratio was undefined for a third unit | Confirmed. Cited unit at its snapshot ratio or base unit only (DATABASE §4.7, §7; C-09) |
| RT-35 | LOW | Purchase cancellation did not close the line guards, so a racing receipt relied on an application check | Confirmed. Cancellation closes each line to its ordered quantity (DATABASE §4.6, §25; C-29) |
| RT-36 | LOW | "No role can update or delete" ignored the table owner; draft-only deletion needed triggers | Confirmed. Role table (owner, runtime, administration) and BEFORE DELETE draft triggers (DATABASE §1, §13, §19, §28; ARCHITECTURE §16; C-53) |
| RT-37 | LOW | MATCH SIMPLE composite keys lapse when one member is NULL | Confirmed. All-or-none convention with the affected pairs listed (DATABASE §1; C-32) |

**After correction:** CRITICAL 0; HIGH 0 unresolved; MEDIUM 0 unresolved; LOW 0 unresolved; OWNER_DECISION_REQUIRED 0. The review's deferred items are recorded below and in DATABASE §31.

## Previous-fix regressions

The review traced its findings to the earlier independent fixes; each regression was confirmed and corrected.

| Earlier fix | Regression it introduced | Status |
| --- | --- | --- |
| FR-01 refund backing through applications | RT-02 unbounded refund shortfall | Corrected |
| FR-02 / FR-04 column grants and settlement residuals | RT-08 residual without exit; RT-26 citations and claims fixable only by contra | Corrected |
| FR-03 company composite keys | RT-04 unkeyed attribution roots; RT-12 pool evidence without a valid key; RT-15 payload-only bank destination | Corrected |
| FR-06 billing-act keys and void basis | RT-09 aging reset by the replacement's root act; RT-10 untyped basis; RT-30 opening act impossible | Corrected |
| IR-05 / IR-10 cumulative rounding and replacement cap | RT-01 corrections not moving posted values; RT-05 replacement headroom admitting normal receipts | Corrected |
| IR-03 / IR-04 pending-case representation and attribution reversal | RT-07 reused exposure; RT-22 double reversal; RT-23 conversion under-specified | Corrected |
| P4-F07 / P4-F09 drop-ship restorations and the serialized-product key | RT-14 drop-ship corrections needing a movement; RT-25 L-35 blocked | Corrected |

## Additional defects found during disposition

| ID | Finding | Correction |
| --- | --- | --- |
| V-P4-01 | Once a receipt reversal re-spreads a charge share (P3 AX-05), re-receiving the same units would post that share again and fail the charge guard, blocking CM-02 | Charge posting is capped by the share still pending on the line, so the re-spread share is never posted twice (DATABASE §6.1) |
| V-P4-02 | A converted case copies the old snapshot; raising the overlap-group bound by it would count the same exposure twice | The successor joins the group without raising its bound (DATABASE §5.8) |
| V-P4-03 | Per-component direct attributions made the INITIAL partial UNIQUE depend on a nullable charge reference | Keyed on `coalesce(purchase_charge_id, 0)` (DATABASE §4.8) |
| V-P4-04 | Service handover lines lacked the entered-unit snapshot BR-INV-03 requires of every transaction line | Entered snapshot and CHECK added (DATABASE §4.9, §7) |
| V-P4-05 | CURRENT_STATE described "three inversion contracts" although ARCHITECTURE §3 defines four since FR-11 | Handoff rewritten from the current documents |

## Independent consistency review of the corrections

Because the corrections themselves are the riskiest new text, an independent read-only reviewer (an Opus sub-agent that changed no file) re-read DATABASE and ARCHITECTURE in full and the re-test sections of this gate against WORKFLOWS and BUSINESS_RULES after the RT fixes. In three passes it reported 36 concrete defects — 5 HIGH, 14 MEDIUM, 17 LOW (25 in the first pass, nine follow-on defects in the verification pass, two wording contradictions in the last check) — and confirmed the arithmetic of every worked example and re-test scenario. Each finding was verified against the text before editing; **all 36 were valid and all are corrected**. None changes business meaning or needs the Owner.

| ID | Class | Verified finding | Correction |
| --- | --- | --- | --- |
| CR-01 | HIGH | An identity substitution lowered net dispatched (SUBSTITUTION restorations were subtracted), contradicting PX-08 and AX-35 and blocking CM-36 after delivery | Net dispatched subtracts only non-SUBSTITUTION restorations; §9 wording corrected (DATABASE §3.1, §9) |
| CR-02 | HIGH | Drop-ship client returns never lowered net delivered, so CM-14/15 after delivery failed the DROPSHIP line CHECK | `after_delivery` on drop-ship return restorations; net delivered subtracts client returns after delivery (§3.1, §4.7, §5.7) |
| CR-03 | HIGH | A price or tax correction was spread over gross acquired quantities, landing cost on fully reversed lots | Spread over net received and net confirmed quantities; dependents by net quantity (§6.3) |
| CR-04 | HIGH | Drop-ship replacement confirmations counted against the normal cap and had no cost basis | Replacement lines count only against the replacement cap and post the case's replacement basis, raising replaced value (§3.1, §4.6, §4.9, AX-08) |
| CR-05 | HIGH | Registry formulas for lot-less entries were wrong: pending reductions and additions were not netted per case; a condition-change case's reversal summed to zero; a found-stock reversal had no counter or cap | Per-case netting; reversal and found counters measured in the case's own scope and net of their reversals; lot-less reversals capped by the case counters (§3.1, §5.8) |
| CR-06 | MEDIUM | NULL partner columns switched off typed keys and CHECKs (reversal type, evidence types, transfer kind, inherited invoice, cause keys, the restoration unit CHECK), and a per-row lot-less sign rule was not expressible | Typed-pair all-or-none rule and NULL-safe CHECKs; RECEIPT_REVERSAL type CHECK; kind ⇔ cause-key CHECKs; lot-less signs checked by the deferred trigger (§1, §4.7, §5.3) |
| CR-07 | MEDIUM | `capacity_amount` referenced another generated column, which PostgreSQL rejects | Expression over base columns (§4.12) |
| CR-08 | MEDIUM | Returned cost netted the RETURN_SETTLEMENT pair, so a case with an unrecovered share could never close; replaced value could be counted twice | Returned cost excludes RETURN_SETTLEMENT; replaced counts replacement bases only (§3.1, §6.1) |
| CR-09 | MEDIUM | A conversion successor could fail its own recognition CHECK after new pending cases | The successor copies the source case's prior pending (§4.7, §5.8) |
| CR-10 | MEDIUM | An unbilled invoice could not be voided without a replacement (CM-23) | `UNBILLED_ENTRY_ERROR` basis, admitted only while the invoice has no billing act (§4.12) |
| CR-11 | MEDIUM | Expenses citing a bill escaped the source-bill cap | Bill-citing expenses raise the bill's cited amount (§3.1, §4.8) |
| CR-12 | MEDIUM | A receipt reversal could not split a lot's charge between its share and re-spread parts and had no expense fallback | One reversal row per share and per re-spread source; expense when nothing received remains (§6.1, AX-05) |
| CR-13 | MEDIUM | A movement-less drop-ship restoration recorded in error could not be reversed | DROPSHIP_ERROR_REVERSAL, exact and once (§4.7, §13, AX-10/36) |
| CR-14 | MEDIUM | The restoration's unit-snapshot key used one column for two tables and was not tied to its own consumption | Typed cited dispatch line keyed through the consumption; drop-ship key on the confirmation line (§4.7) |
| CR-15 | MEDIUM | Reversing an opening, surplus or drop-ship return lot left cost without quantity | BASIS_REVERSAL lot-cost entries (§4.8, §5.3, AX-36) |
| CR-16 | MEDIUM | A disposal that converted a condition-change case could not be reversed | UNATTRIBUTED_CONVERSION in the reversible list with its counter effects (§4.7, §5.8) |
| CR-17 | LOW | ACTIVE → RESIDUAL could not set its required case under the grants | `correction_case_id` in the one-shot grant (§4.12, §28) |
| CR-18 | LOW | Draft editing of draft-editable AF/SN tables was impossible under §28 | UPDATE (and draft DELETE) through the draft trigger (§28) |
| CR-19 | LOW | The serial-state rebuild formula missed substitutions and reversals and depended on entry order | Evaluated on the serial's net entry per movement with every type mapped (§3.1) |
| CR-20 | LOW | Rejected units counted as received | Received = usable + damaged (§3.1, §4.6) |
| CR-21 | LOW | Self-contradicting late-charge and replacement-share wording | Reworded (§6.1, §6.3) |
| CR-22 | LOW | The revision basis arc lacked the project-transition reference | `basis_project_transition_id` (§4.12) |
| CR-23 | LOW | A circular key between a count finding and its surplus lot | `stock_count_findings.lot_id` removed; the lot cites the finding (§4.7) |
| CR-24 | LOW | Stale net-zero key and file-object wording | Corrected (§5.3, §22) |
| CR-25 | LOW | This gate still carried two result markers awaiting measurement | Filled with the measured results of the final validation run |

**Verification of the corrections.** The same reviewer re-read the fixed passages: 24 of the 25 findings were resolved and the 25th (this gate's result markers) is resolved by the final validation run recorded below. It then reported nine follow-on defects introduced by, or surfaced by, the fixes (CR-26–CR-34), and a last check of those edits found two wording contradictions (CR-35, CR-36); all were verified and corrected exactly as proposed, and the last check found nothing else.

| ID | Class | Verified finding | Correction |
| --- | --- | --- | --- |
| CR-26 | MEDIUM | The receipt-reversal expense fallback left the expensed share pending on the line, so a CM-02 re-receipt posted it again and the charge guard rejected it | `expensed_out_amount` on the share guard; pending = share − posted − re-spread out − expensed out (§4.8, §3.1, §6.1) |
| CR-27 | MEDIUM | A DROPSHIP_ERROR_REVERSAL was both summed and netted in two registry formulas, so it had no effect | Error reversals only net; positive terms list the reversible kinds (§3.1) |
| CR-28 | MEDIUM | The error reversal's target was untyped and could point at any restoration of equal quantity | Typed pair with a four-column key to the same confirmation line and a kind CHECK (§4.7) |
| CR-29 | LOW | The serial state after an error reversal of a supplier return was unmapped | Any DROPSHIP_ERROR_REVERSAL → DROPSHIPPED (§3.1) |
| CR-30 | LOW | Sign error in the found-counter formula | Found = the net of findings and their (negative) reversals (§3.1) |
| CR-31 | LOW | C-56 still required a replacement for every entry-error void | Billed entry-error voids only (§18) |
| CR-32 | LOW | Four sentences still described pre-fix rules (case columns, the CM-15 cost path, the count of movement-less restorations, the reversal cap) | Aligned with §3.1 and §5.7 (§4.13, §5.3, §6.3) |
| CR-33 | LOW | Purchase-line tax components were not draft-editable with their line | Included in the draft grant (§28) |
| CR-34 | LOW | A purchase charge could be recorded without a source bill (the all-or-none pair allowed two NULLs), contrary to L-43 | Source bill pair NOT NULL (§4.6) |
| CR-35 | LOW | The §6.1 receiving rule still quoted the pending share without the expensed-out part, and three places stated the charge identity without the expensed amount | Pending = share − posted − re-spread out − expensed out; allocated + pending + expensed = charge (§4.8, §6.1, C-21) |
| CR-36 | LOW | §28 granted draft edits on purchase-line tax components that §4.6 did not mark draft-editable, and deleting a wrong component had no path | Marked draft-editable in §4.6; deletable before the line's first effect (§19) |

## Final adversarial re-test (DIR-029 §18)

Each scenario was re-run against the corrected text. Amounts in rupiah.

| # | Scenario | Walk-through and end state | Result |
| --- | --- | --- | --- |
| 1 | Partial receipt → price correction → later receipt | A box of 12 for 100,000: receive 5 → GOODS 41,667; 2 dispatched (HPP 16,667). Price → 120,000: target(5) = 50,000 → COST_CORRECTION +8,333 on lot 1, dispatch share 3,333 (HPP 20,000), lot keeps 30,000; posted 50,000. Receive 7 → 70,000. Total 120,000 exactly | PASS |
| 2 | Partial receipt → charge correction → later receipt | Lines A 10 and B 30 @ 100,000; freight 100,000 on value (A 25,000, B 75,000); receipts A 10 (+25,000), B 20 (+50,000; B pending 25,000). The bill is really 120,000: its evidence is corrected first, then CHARGE_CORRECTION +20,000 — charge 120,000, bill cited 120,000 ≤ 120,000, new shares A 30,000, B 90,000, old postings contra'd and re-posted (A 30,000; B 60,000; B pending 30,000). Receive B 10 → 30,000. Σ = 120,000; a correction above the bill's total without correcting the bill is REJECTED (C-20) | PASS |
| 3 | Payment → application → refund → Rp1 contra → reallocation | Payment 1,200,000; invoice application 1,000,000; refund 200,000. Contra Rp1: releasing the refund backing into a shortfall is REJECTED (a source with a shortfall must hold no invoice application and no capacity); the only committable disposition releases Rp1 of the invoice application. Capacity 0, so any further application of 199,999 is REJECTED. Cash-In 1,199,999 = settled 999,999 + refunded 200,000 | PASS |
| 4 | Location move −5/+6 | Deferred net-zero trigger: Σ per (lot, condition) = +1 → REJECTED at commit; −5/+5 commits | PASS |
| 5 | Replacement receipt versus normal receipt cap | Ordered 10, 8 received, 2 returned with replacement expected 2: a normal receipt of 4 → REJECTED (8 + 4 > 10); normal 2 → accepted; replacement 2 → accepted (≤ 2); a third replacement unit → REJECTED. GOODS posted = the line value at q = 10; replacement lots carry REPLACEMENT_BASIS | PASS |
| 6 | Overlapping unattributed-loss cases | A 5, B 5 claims; cases of 2, 3 and 2 (prior pending 0, 2, 5; CHECKs 2 ≤ 10, 3 ≤ 8, 2 ≤ 5); group bound A 5, B 5. Assigning 2 and 3 to A fills A's bound; assigning the third case to A → REJECTED (7 > 5); to B → accepted. A fourth case of 4 → REJECTED (4 > 10 − 7) | PASS |
| 7 | Replacement invoice after partial payment | Billed 10,000,000 (billed 1 Aug, due 31 Aug), paid 4,000,000; void-and-replace with 9,000,000 and an INHERITED root citing the current act → commits; 4,000,000 re-targeted; outstanding 5,000,000 ages from 31 Aug. A BILLING root dated 1 Oct, a missing act, or an act citing a superseded act → REJECTED at commit | PASS |
| 8 | Settlement ACTIVE → RESIDUAL → resolution | Fee 300,000 on invoice X; X voided for a return (typed case basis): ACTIVE → RESIDUAL — invoice and payment legs released, evidence still cited, fee expense kept, case residual 300,000 (no closure). The platform's corrected advice places the fee on invoice Y of the same payment: RESIDUAL → CONTRAED (citation and expense released) with a new ACTIVE settlement on Y (cited again, expense once); case residual 0 → closable. RESIDUAL → ACTIVE or a second exit → REJECTED | PASS |
| 9 | The same real evidence under an alternate type | Fee advice FA-77 (300,000) as FEE_ADVICE is cited in full; registered again as OTHER it gains a second identity row but no role accepts OTHER for fees → no second capacity; registered again as FEE_ADVICE it versions the existing evidence. The cross-type warning is P5 | PASS |
| 10 | Corrective receiving under the same delivery reference | ORIGINAL receipt 8 under DN-100; CM-04 SUPPLEMENT 2 under DN-100 citing it → accepted; a second ORIGINAL under DN-100 → REJECTED; after reversing the original's lot, a RE_RECEIPT under DN-100 → accepted; a RE_RECEIPT citing an unreversed receipt → REJECTED | PASS |
| 11 | Drop-ship confirmation reversal and return after delivery | A confirmation of 5 (50,000) recorded in error → DROPSHIP_CONFIRMATION_REVERSAL without a movement: HPP −50,000, project and purchase confirmed −5, posted goods −50,000, serial NEVER_RECEIVED; a second reversal → REJECTED by the consumption cap; the reversal itself recorded in error → one exact DROPSHIP_ERROR_REVERSAL. Another confirmation of 5 is delivered (L-40); the client returns 2 straight to the supplier → DROPSHIP_SUPPLIER_RETURN with `after_delivery`: confirmed net 3 and delivered net 3 (the DROPSHIP CHECK holds), HPP of the 2 units moved to SUPPLIER_RETURN under a PURCHASE_RETURN case, settled by refund, replacement confirmation or unrecovered loss; no ledger entry | PASS |
| 12 | Cross-company bank-account selection | A Company B DOC-06 version naming Company A's account → REJECTED by the (company, account) key; a Company B payment likewise | PASS |
| 13 | Multi-project cost-correction revalidation | A lot consumed by completed projects P1 (3 units) and P2 (2 units); a correction of +5,000 on the lot re-attributes 3,000 and 2,000 and writes two REVALIDATE rows in one command under (`command_id`, `project_id`) → commits (previously the second row violated `command_id` UNIQUE) | PASS |
| 14 | Guard rebuild after corrections | The §3.1 formulas reproduce the live guards after scenarios 1, 3 and 6: posted goods 41,667 + 8,333 + 70,000 = 120,000; the payment's invoice applied 999,999, refund backed 200,000, shortfall 0, capacity 0; A's exposure and group rows 5 ≤ 5; drop-shipped serial states from their confirmation events. `GuardMaintenance` has nothing to write | PASS |

## Conservation after correction

- **Inventory:** ON HAND, RESERVED, UNUSABLE and AVAILABLE move only through typed movements, reservation events or a pending case; AVAILABLE = ON HAND − RESERVED − UNUSABLE ≥ 0 on the product guard; net-zero movements conserve quantity at commit; no fractional base quantity of a counted product can be stored.
- **Cost / HPP:** per lot Σ entries = remaining + net draws; per purchase line the posted goods and tax values equal the cumulative target of the corrected value, and Σ over lots and drop-ship attributions = the corrected line value once fully received; per charge allocated + pending + expensed = the corrected amount and Σ = amount once every remainder ends; after any correction Σ attributed economic cost = the corrected source cost.
- **Unattributed loss:** pending = recognized − found − resolved − reversed − converted ≥ 0; each company's assignment ≤ its recognition-time exposure; the assignments of cases pending together ≤ one group bound per company; claims ≥ pending in every scope; snapshots never change and nothing is frozen.
- **Payment / refund:** per source, invoice applications + refund-backed consumption ≤ real money (gross − contras); a refund's backing + shortfall = its net amount, backing released only by the refund's own contra; a shortfall exists only on a source with no invoice application and no free capacity, and sits on a case that cannot close.
- **Receivable / settlement:** billed = applications + fee and tax settlements + net write-offs + outstanding, outstanding ≥ 0; settlements follow the three-transition matrix, and a residual keeps its evidence citation until its resolution; replacements inherit the current act, so aging never resets.
- **Company isolation:** every company-owned reference is a composite key with all-or-none nullable members; document bank destinations and invoice clients are scoped; only the five inventoried link families cross companies.
- **Numbering:** unique per (company, type, period), monotonic, append-only, never reused, concurrency-safe, no `MAX+1`; retired project numbers are tombstoned; no gapless claim.
- **Transaction boundaries:** AX-01–37 each list every fact and guard that commits together, deferred triggers included; nothing slow runs inside a transaction.
- **Trigger and privilege realism:** guarantees are stated per role — DB against the runtime role, DB+APP+DQ where a guard is coupled by the posting path, nothing against the owner role, which only deployments and audited maintenance use.

## Gap reconciliation

P4 continuations are recorded in GAP-003/004/005/006/007/008/009/010/016/023/025/026/027/028/029/030 and the TECH-018 finalization notes in GAP-003/004/005/006/008/009/016/025/027/030/031. None is closed: each still needs its P5 enforcement, P6 mechanism, P8 proof, P9 control or P10 rehearsal, and the Owner asked not to close gaps whose runtime proof belongs to later phases. GAP-030 records the DIR-027 clarification; GAP-031 (guard drift) now points to the §3.1 registry and the audited maintenance path. Totals unchanged: **31 findings — 3 CLOSED, 27 OPEN, 0 OWNER_DECISION_REQUIRED, 1 ACCEPTED_RISK**.

## Deferred obligations (P5/P6/P8/P9)

Recorded in DATABASE §31 and ARCHITECTURE §20; none is implemented now.

- **P5:** the evidence duplicate warnings (same checksum under two identities; same issuer, reference and date under two types); field projection of pooled physical data versus company financial data, pool evidence and exposure rows included; the DIR-027 evidence-resolution authority (ADM+ only for a resolution fully identified by definitive evidence).
- **P6:** the extended lock order of DATABASE §26; the completion anchor (the project row); a guard update matching no row treated as failure; race-safe creation of first-use guard and counter rows; concrete statement sequences for every AX action corrected here.
- **P8:** adversarial fixtures for every HIGH and MEDIUM finding (the fourteen scenarios above as a minimum); numbering tests including retired numbers; the reconciliation suite driven by the §3.1 registry; serial-state tests including drop-shipped serials.
- **P9:** a separate migration database connection and role for Laravel; owner and TRUNCATE privileges kept out of the runtime; guard verification after every restore before reopening.

Open technical items that need no Owner decision: the revision-number format (GAP-019), exact PostgreSQL/Laravel versions and extension availability including deferred constraint triggers, generated key columns and partial-UNIQUE NULL handling (DEP-07), partitioning thresholds, optional row-level security.

## Static validation

A read-only validation script (kept outside the repository) checked: every relative link and anchor in all Markdown files; lifecycle metadata and approval lines, including APPROVED with APPR-005 on DATABASE and ARCHITECTURE and REVIEW on this gate; every source record's SHA-256 against SOURCE_OF_TRUTH, the immutability of tracked sources, the size, LF endings and `-text` attribute of the new records; resolution of every BR/CALC/FS/WF/SF/PX/CM/AX/QS/L/C/D-DB identifier cited in the P4 documents, WORKFLOWS, the gap register, the decision log and the handoff (slash lists and ranges expanded); WORKFLOWS identifier counts; the DATABASE catalogue (distinct tables, module map and per-module rows), table references in DATABASE and ARCHITECTURE, guard-registry coverage of every guard-class table, AX-01–37 in the transaction map, CAP-01–18, the 15 E-DB rules and C-57–C-61 in the traceability section, and stale pre-correction wording; the RT-01–RT-37 disposition rows and the fourteen re-test rows of this gate; balanced code fences; consistent column counts in every Markdown table; gap triage/detail agreement and totals; placeholder and review markers; every OWNER_DECISION_REQUIRED value in the repository; secret-like content in every added or new line; `git diff --check`; trailing whitespace in new files; forbidden application, schema, migration, deployment and P5+ paths; that unrelated approved files and earlier gates are unchanged and the four governance files beyond the draft's census change only their status and approval lines; that every added line in the three amended approved files carries `DIR-027`; and that APPR-005 records the current normalized SHA-256 of its five approved files.

**Results (final run, 2026-09-30) — PASS, 57 of 57 checks:** 491 relative links and anchors resolve across 29 Markdown files; metadata valid (DATABASE and ARCHITECTURE APPROVED with APPR-005; WORKFLOWS, BUSINESS_RULES and DOMAIN_MODEL APPROVED; this gate, P3_QUALITY_GATE, the gap register, the decision log and the handoff REVIEW); 26 source files match their recorded hashes, no tracked source changed, and source records 24, 25 and 26 are 1,646 / 37,652, 781 / 17,225 and 899 / 21,971 lines / bytes with LF endings and `-text`; 395 distinct cited identifiers resolve; WORKFLOWS still defines 39 workflows, 18 subflows, 8 partial rules, 38 correction rows, 37 indivisible actions, 22 signals and 50 Level-1 decisions; DATABASE defines 124 distinct tables whose module counts match (IAM 7, ORG 9, PTY 5, CAT 6, PRJ 11, PUR 8, INV 26, CST 6, FUL 7, DOC 10, ADM 3, FIN 19, OPS 7), C-01–C-61 contiguous (25 of them DB+APP+DQ), D-DB-01–14, a guard registry covering all 18 guard-class tables in 22 rows, all 37 AX rows, all 18 CAP rows, all 15 E-DB rules and C-57–C-61 in the traceability section, and no stale pre-correction wording; this gate carries RT-01–RT-37 (2 HIGH, 15 MEDIUM, 20 LOW) and 14 PASS re-test rows; gap register 31 rows = 31 sections (3 CLOSED, 27 OPEN, 1 ACCEPTED_RISK, 0 OWNER_DECISION_REQUIRED); no placeholder or review marker; every OWNER_DECISION_REQUIRED statement in the repository is 0; no secret-like content in 5,300 added or new lines; `git diff --check` clean; no trailing whitespace in new files; no application, schema, migration, deployment or P5+ file; ACCEPTANCE_CRITERIA, REFERENCE_COVERAGE, PRODUCT_OVERVIEW, V1_SCOPE, the P0/P1/P2 gates and ADR-001 unchanged; CLAUDE.md, CHANGE_CONTROL, AGENT_OPERATING_MODEL and ENGINEERING_PRINCIPLES change only their status and approval lines, and CLAUDE.md's attribution rule is intact; every added line in WORKFLOWS, BUSINESS_RULES and DOMAIN_MODEL carries `DIR-027`; APPR-005 records the normalized SHA-256 of its five files exactly as they are committed. Every Markdown table has a consistent column count.

## Changed-file census

25 paths before staging — 19 modified (`.gitattributes`, AGENTS.md, CHANGELOG.md, CLAUDE.md, README.md, AGENT_OPERATING_MODEL, CHANGE_CONTROL, DECISION_LOG, ENGINEERING_PRINCIPLES, GAP_REGISTER, PROJECT_CHARTER, SOURCE_OF_TRUTH, BUSINESS_RULES, DOMAIN_MODEL, P3_QUALITY_GATE, WORKFLOWS, CURRENT_STATE, NEXT_ACTION, CONTEXT_INDEX) and 6 new (source records 24, 25 and 26, DATABASE, ARCHITECTURE and this gate). Against the 19-path draft census, the six additional paths are the two new source records and the four governance files whose approval line gains APPR-005, following the APPR-004 precedent. The P4 finalization checkpoint commits exactly these 25 paths.

## Gate result and limitations

**P4 planning-quality result: PASS.** The logical database and application architecture answer every DIR-027 §5 question; the Owner's clarification is applied narrowly with recorded provenance; traceability is complete; every self-review, independent-review and targeted-review finding, and every finding of the consistency review of the corrections, is fixed. **APPR-005 conditions verified:** all valid RT findings resolved; HIGH = 0, MEDIUM = 0 and LOW = 0 unresolved (CRITICAL 0); OWNER_DECISION_REQUIRED = 0; the final adversarial re-test (14/14) and the static/governance validation PASS; no known contradiction remains; P1/P2/P3 approved business semantics are preserved — the only approved-text changes are the Owner-decided `DIR-027` amendments and their approval pointer; P4 remains architecture documentation only, with no migration, executable SQL, application code or P5 file. Limitations: documentation evidence only — no migration, application, runtime, concurrency, restore or device evidence exists or is claimed; the exact SQL of triggers, deferred constraint triggers and column grants is P6/P9 work and PostgreSQL behaviour relied on is confirmed at DEP-07; deadline feasibility remains unproven (GAP-014). DATABASE and ARCHITECTURE are APPROVED under APPR-005; this gate stays REVIEW.

## Targeted review scope (as recommended before DIR-028)

One read-only Fable review, **"DATABASE INTEGRITY UNDER CONCURRENCY AND CORRECTION"**, limited to DATABASE with ARCHITECTURE §3/§5/§7 against BUSINESS_RULES and WORKFLOWS: (1) stock ledger, guard balances and reservations under concurrent dispatch/reserve/release/cut (§5; C-03–C-07); (2) receiving lots and cost — cumulative rounding, class-specific contras, late charges, re-spread, returns and corrections (§6; C-21, C-24–C-27); (3) inter-company attribution and the DIR-027 unattributed-loss case, including substitution, conversion, found stock and attribution reversal (§5.8, §6.5; C-28, C-35–C-37); (4) payment, application and refund conservation, contras and refund finality (§11.3; C-13, C-14, C-44, C-45); (5) tax and fee settlements, residuals and evidence caps (§11.4, §12; C-15–C-23); (6) document numbering and issue/render separation (§10; C-10–C-12); (7) whether every UNIQUE, CHECK, trigger and column grant can hold as written (§18, §28); (8) correction and contra relationships (§13; C-16, C-46, C-55, C-56); (9) company isolation through composite keys (§15; C-14, C-32); (10) transaction boundaries and command identity (§25–§26). It should not re-review product requirements, P1–P3 business decisions or UI. The review was run under DIR-028 and is recorded above.

## Exact next safe action

The separately authorized handoff/bootstrap task in [NEXT_ACTION](../07-handoff/NEXT_ACTION.md): verify that local HEAD equals origin/main at the P4 checkpoint, record the checkpoint SHA literally, then await the Owner's explicit P5 authorization. No P5 work before that.

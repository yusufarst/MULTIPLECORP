# P2 adversarial review and quality gate

Status: REVIEW | Updated: 2026-09-29 | Owner: Planning

Scope: P2 — Domain Model & Business Rules only, authored under DIR-016–019 and corrected under the Owner's pre-checkpoint audit decision DIR-020 (TECH-013), on the published P1 baseline `afeda00a9893770743fdc879cade9479cb849033`. This report records planning evidence; it is not acceptance of an implemented application, not approval of the P2 documents (they remain REVIEW), and not permission to begin P3.

## Input and baseline verification

Read before drafting: AGENTS, CONTEXT_INDEX, CURRENT_STATE, NEXT_ACTION, all P0 governance/ADR documents, the three APPROVED P1 product documents, REFERENCE_COVERAGE, P1_QUALITY_GATE, GAP_REGISTER, DECISION_LOG, SOURCE_OF_TRUTH and all eighteen source records (the 58-section brief, mandate, directives, clarifications, backup policy, final decisions, recoveries, approval, checkpoint/publication records, and the five P2 transcripts). Read-only baseline checks all passed at entry: P0/P1 approved, GAP-002 CLOSED, clean tree, local main == origin/main == expected HEAD, no P2 files, 0 OWNER_DECISION_REQUIRED. A raw-source extraction pass over the owner brief and directives cross-checked the canonical digest for missed entities, rules, terminology and ambiguities before modelling.

## P1 → P2 traceability

Every CAP maps to concept homes in [DOMAIN_MODEL](../DOMAIN_MODEL.md) (domain codes) and rule IDs in [BUSINESS_RULES](../BUSINESS_RULES.md). A justified no-new-rule disposition is stated where P2 correctly adds nothing. REF/IMG identifiers stay attached through the cited CAP/AC anchors in each rule (REFERENCE_COVERAGE owns that layer); P3+ must carry BR/CALC/FS IDs into workflows, schema, tests and units (GAP-024).

| P1 | P2 home (concepts → rules) |
| --- | --- |
| CAP-01 | ACC + CO catalogues → BR-XC-02, BR-ACC-01/03; identity-asset snapshots BR-SN-01/02 |
| CAP-02 | PT catalogue (org→unit→PIC, supplier; company-scoped price history) → BR-ACC-02; address-as-used snapshots BR-SN-02 |
| CAP-03 | CAT catalogue (SKU, barcode, serial policy, base/alternate units, price defaults+history) → BR-INV-03/09, BR-SN-01/02 |
| CAP-04 | PRJ + QUO catalogues (project hub, demand lines, confirmation) → SM:Project, SM:Quotation, BR-PRJ-05/06, BR-QUO-01–03 |
| CAP-05 | PUR catalogue → BR-PUR-01–04; annex 12 |
| CAP-06 | INV catalogue (pool, movements, lots, reservation, allocation, opname, racks, min-stock, opening lots) → BR-INV-01–12, SM:Reservation, BR-RSV-01–04; annex 7–9, 12 |
| CAP-07 | FUL catalogue (delivery, discrepancy, closure) → BR-INV-02, BR-XD-01, CALC-04 |
| CAP-08 | DOC catalogue (instance/version/render/numbering) → SM:Document, BR-DOC-01–04, BR-SN-01/02 |
| CAP-09 | ADM catalogue (checklist, eligibility, waiver, reopening) → BR-ADM-01–03, BR-PRJ-03 |
| CAP-10 | FIN catalogue (invoice, billing, receivable, payments, applications, credit, refund, disposition) → BR-FIN-01–08/15, BR-CR-03; annex 1–6 |
| CAP-11 | FIN catalogue (expenses, HPP attribution, disbursements, non-project Cash-In) → BR-FIN-09–14, CALC-08–14; annex 6, 10, 12 |
| CAP-12 | PRJ channel + SIPLAH metadata → BR-PRJ-05, BR-FIN-09/10, FS-13 |
| CAP-13 | Sensitivity classes (DOMAIN_MODEL) + audit convention → BR-XC-01–03, BR-ACC-01–03, correction taxonomy §2. Enforcement matrix is P5 (justified: P2 defines the boundary, not the mechanism) |
| CAP-14 | Glossary terms only; copy/patterns are P7 (justified disposition — no domain rule exists to write) |
| CAP-15 | Opening lots + opening receivables + archive semantics → BR-INV-12, BR-FIN-14, BR-XD-06; cutover execution P10 |
| CAP-16 | No new domain content beyond durable issued-artifact identity (BR-DOC-01/03); backup/recovery is P9 (justified disposition) |
| CAP-17 | Projections rule (BR-XD-05) + CALC-01–14 + force-complete residual queue (BR-PRJ-04); no dashboard domain invented |
| CAP-18 | Completion predicates BR-PRJ-02, waiver BR-PRJ-03, override BR-PRJ-04, revalidation BR-CR-05/BR-ADM-03 |

DOC-01–14: all fourteen render business records through the single Document capability (BR-DOC-01–04); record-vs-layout pairs are disambiguated in the glossary; DOC-02/DOC-14 share one order record; DOC-05 requires a real payment; DOC-08/10 render confirmed facts; DOC-09/11/14 carry truthful company-issuer/draft identity. OWN-01→BR-FIN-01–08; OWN-02→BR-ACC-01/02 + sensitivity classes; OWN-03→BR-PRJ-02–04; OWN-04→BR-RSV-01–04 + BR-INV-01; OWN-05→BR-INV-04/05 + BR-FIN-12; OWN-06→unchanged P9 scope (no P2 content). DIR-018→BR-DT-01–06, BR-DOC-02, BR-XC-02; DIR-019/020→BR-FIN-08–10. BK-01–12 remain P9 obligations untouched by P2. **No CAP, DOC, OWN or BK requirement lost its home; zero unexplained dispositions.**

## Adversarial review evidence

Three conceptual passes were performed as DIR-017 §12 requires, with independent sub-agent reviews for the design pass and a dedicated failure pass.

**PASS 1 — model.** Confirmed and fixed: overloaded terms (two "allocations", three "revisions", opposite "returns", record-vs-layout pairs) → glossary disambiguation; missing concepts (project item as reconciliation spine, receiving lot, payment application, contra-facts, expense, disbursement, opening facts, closure decisions, condition-change event, render artifact, waiver record, Force Complete record, discrepancy record) → catalogue rows; rejected: third canonical file, universal status enum, stored credit ledger, Payment/Receivable state machines, timed holds, payment-terms engine, A/P subledger, order entity, SIPLAH module.

**PASS 2 — failure.** Confirmed breaks in the draft model, all fixed in the final rules: derived credit double-spend (→ BR-FIN-03 unified application conservation); billed-invoice void/revision leaving orphaned allocations (→ BR-CR-03); no provenance carrier for fungible pooled stock incl. opname-found stock, drop-ship returns and return-after-allocation (→ BR-INV-04/06 lots + contra-attributions); RESERVED ≤ ON HAND permitting negative AVAILABLE (→ BR-INV-01/BR-RSV-01 AVAILABLE ≥ 0); four unevaluable completion predicates (→ closure decisions, BR-PUR-04/BR-CR-04 + remaining-scope cancellation); immutable-payment wrong-amount trap (→ BR-CR-02 contra-facts); migration formulas without opening facts (→ BR-INV-12/BR-FIN-14); SIPLAH net remittance forcing routine Owner write-offs (→ DIR-019, BR-FIN-09); unbounded backdating/numbering ambiguity (→ DIR-018, BR-DT/BR-DOC-02); Admin self-granted waiver eligibility (→ BR-PRJ-03); deactivation stranding live debt (→ BR-ACC-03); force-complete silently shrinking sellable stock (→ BR-PRJ-04 residual queue); box-split rounding non-reconciliation and mid-project ratio changes (→ BR-INV-03/BR-FIN-13, annex 10–11).

**PASS 3 — execution/authority.** Verified P3/P4/P5/P6/P8 need not guess business meaning: every lifecycle transition, invariant, correction legality, calculation and date convention resolves to a BR/CALC ID with source anchors; E-DB tags say a database guarantee is needed without choosing one; sensitivity classes give P5 its input; race points name their P6 obligation (BR-XD-07). Authority audit: three draft Level-1 claims crossed the Level-2 line and went to the Owner instead of being decided silently — all three returned as DIR-018/019 decisions. The Owner's subsequent pre-checkpoint audit of every PLANNER-DETERMINED rule flagged one further clause (the BR-FIN-09 generalization) as Owner authority; the Owner decided it YES under seven explicit conditions (DIR-020) and it is no longer planner-determined.

**Final consistency pass (DIR-019 mandate), eleven areas — PASS, no contradiction, no new Owner decision:** Q1 backdating × numbering (append-only business-date periods; accepted chronology divergence); individual attribution across multiple Admin accounts; disposition × derived receivable (BR-FIN-04 conservation); dispute-hold × aging × completion (blocks normal completion, Force Complete remains the escape); reconstructed payments × backdating; application conservation × fee settlement (two composing conservation laws, no overlap); Cash-In vs Sales Value vs Expense/HPP (annex 6 reconciles profit and cashflow); completion-gate evaluability; inventory conservation; multi-unit arithmetic; historical immutability. The fee-settlement generalization examined in this pass was subsequently ruled on by the Owner (DIR-020): it stands as an explicit Owner decision with seven binding conditions, not a Level-1 determination.

## Settled-decision non-reopening check

PASS. The P2 documents formalize and cite, without altering: DIR-006–008 product identity/scope/hierarchy; DIR-009/RISK-001 local-only backup and accepted exposure; DIR-011 five financial concepts, billing trigger, visibility boundary, completion authority, reservation contract, inter-company attribution; DIR-018/019/020 as received (DIR-020's seven conditions and its exclusions — no arbitrary deductions, no unsupported cashback/discounts/write-offs — reproduced verbatim in BR-FIN-09). Supplier comparison, institution modules, SIPLAH-as-module, formal accounting, enterprise WMS and offsite backup remain excluded; all V1_REQUIRED capabilities remain in scope; no deadline-driven cut appears anywhere in the P2 rules (FS-14).

## Gap register reconciliation

GAP-016 marked BUSINESS DECISION RESOLVED (DIR-018) with technical residue at P4/P6/P8; GAP-025 added (multi-unit arithmetic proof); GAP-003/004/005/006/009/022/023 now reference their canonical BR rules and remain OPEN only for later mechanisms/evidence; GAP-024 traceability chain extended with the P2 identifier layer. Totals: **25 findings — 3 CLOSED, 21 OPEN, 0 OWNER_DECISION_REQUIRED, 1 ACCEPTED_RISK.**

## Static verification

- **PASS — package integrity (Git-verified census):** 44 repository files — 36 tracked at HEAD `afeda00a…` plus 8 new files (three `docs/02-domain/` documents, five source transcripts). Changed paths: **18 = 8 new + 10 modified tracked files; nothing staged** (the ten modifications are the bounded governance/entry/handoff updates named in TECH-012/013). 24 Markdown documents all carry lifecycle metadata; the three P2 documents carry REVIEW; the five transcripts (records 14–18) have Git text conversion disabled and recorded SHA-256/line/byte provenance in SOURCE_OF_TRUTH; all thirteen earlier source records are untouched (byte-identical to HEAD).
- **PASS — link/anchor check:** 281 relative links/anchors across all 24 Markdown documents resolve, including the new P2 cross-references and registry/navigation updates; no conflict markers or trailing whitespace.
- **PASS — inventory preservation:** 18 CAP, 23 AC, 14 DOC, six OS, 62 REF, nine IMG and six OWN rows verified intact in their owning P1 documents (two SHOULD, six deferred groups, eight REF-G and 12 BK blocks unchanged with them); no approved product content changed other than phase-boundary wording in AGENTS/charter recorded under TECH-012.
- **PASS — rule catalog counts (measured):** 3 audit (BR-XC), 6 dates/backdating (BR-DT), 3 snapshot (BR-SN), 6 correction (BR-CR) rules + the legality matrix; 4 stored-decision state models carrying 6 PRJ, 3 QUO, 4 DOC and 4 RSV lifecycle rules; 12 INV, 4 PUR, 15 FIN, 3 ADM, 3 ACC invariant-bearing rules; 7 cross-domain rules; 14 CALC definitions; 12 binding numeric-annex examples; 14 forbidden shortcuts. **Exactly 14 marked PLANNER-DETERMINED Level-1 decisions** (enumerated in CURRENT_STATE) plus the marker definition in Conventions — decisions counted, not string occurrences; BR-FIN-07 deliberately unmarked as a settled DIR-011 consequence; BR-FIN-09 is Owner-decided (DIR-019/020), not planner-determined. Zero PENDING-OWNER markers (OWNER_DECISION_REQUIRED = 0). Gap register: 25 triage rows = 25 detail sections.
- Checks are documentation verification only; no application, runtime, concurrency, device, migration or recovery evidence exists or is claimed.

## Gate result and limitations

**P2 planning-quality result: PASS.** Domain truth, lifecycles, invariants, correction/snapshot/calculation semantics and traceability are documented without reopening settled decisions and with zero pending Owner business decisions. The Owner's pre-checkpoint audit (DIR-020/TECH-013) is complete: the flagged generalization is Owner-decided, markers are normalized and every recorded count above is re-derived from Git/file actuals. The P2 documents remain **REVIEW**; the working tree is uncommitted pending explicit checkpoint authorization. This PASS is not implementation approval, not schema/workflow/security design, not deadline feasibility, and not release readiness.

## Exact next safe action

Owner review of the P2 package, then an explicit checkpoint authorization per [NEXT_ACTION](../../handoff/archive/NEXT_ACTION_2026-10-02.md). Stop: no P3, P4+, commit, push or application work in this turn.

## Checkpoint, publication and approval — 2026-09-29

The sections above are pre-checkpoint evidence and remain accurate at their writing time. Subsequently, the reviewed P2 package was committed as checkpoint `1392966bfb89581d705e0394424705978e1d3db8` (`docs: establish P2 domain model and business rules`) and published with a normal non-force push. At the approval task's entry, local main == origin/main == that checkpoint with a clean tree and index, no P3 file and OWNER_DECISION_REQUIRED = 0 (OBS-004). The Owner's approval / checkpoint-publication authorization is archived as the nineteenth source record and recorded as [APPR-003/DIR-021](../../00-governance/DECISION_LOG.md#appr-003--p2-domain-baseline-approved): [DOMAIN_MODEL](../DOMAIN_MODEL.md) and [BUSINESS_RULES](../BUSINESS_RULES.md) are APPROVED with unchanged substantive content; this gate report remains REVIEW evidence per the Owner's instruction. P3 stays NOT STARTED pending a separate Owner authorization.

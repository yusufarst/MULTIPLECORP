# Permissions matrix

Status: APPROVED | Updated: 2026-10-01 | Owner: Planning

Approval: [APPR-006](../00-governance/DECISION_LOG.md#appr-006--p5-security-and-authorization-approved), explicit conditional Owner approval on 2026-09-30 (DIR-031) of this document as committed in the P5 finalization checkpoint; the approved file hash, the verified conditions and the exclusions are recorded there. Approval changes lifecycle only, not implementation authorization.

Amendment: narrowly amended on 2026-09-30 as a Level-1 technical clarification under [TECH-021](../00-governance/DECISION_LOG.md#dir-032-obs-011-and-tech-021--p6-authorization-entry-baseline-and-concurrency-documentation): the closing sentence of §7.2 gains one clause, marked `TECH-021`, that names the operational export-request record [CONCURRENCY_IDEMPOTENCY](../03-architecture/CONCURRENCY_IDEMPOTENCY.md) defines for DP-07 among the records that are never projected raw, and states that only its requester sees the status of its own requests, as DP-07 and SECURITY FL-09 make every export the requester's. No capability, grant, scope rule, existing projection or authority changes; the pre-amendment SHA-256 is recorded in the decision log, and the amended revision is approved under [APPR-007](../00-governance/DECISION_LOG.md#appr-007--p6-concurrency-idempotency-and-performance-approved).

Authority: P5 — Security & Authorization, authorized by the Owner's fast-track directive [DIR-031](../00-governance/DECISION_LOG.md#dir-031-obs-010-and-tech-020--p5-authorization-entry-baseline-and-security-documentation) (twenty-seventh source record). This document owns **authorization truth**: the capability catalogue and authority classes, the role and grant model, company-scope rules, the resource and field projection matrix, cross-company link authorization, Owner-only denials, Owner review-queue access and the data-path coverage matrix. Security mechanisms — authentication, sessions, credentials, input, files, logging, secrets and the threat model — are owned by [SECURITY](../03-architecture/SECURITY.md); phase evidence by [P5_QUALITY_GATE](../03-architecture/P5_QUALITY_GATE.md).

**Anti-duplication contract:** business meaning stays in [V1_SCOPE](../01-product/V1_SCOPE.md), [DOMAIN_MODEL](DOMAIN_MODEL.md) (including the [scope and sensitivity classes](DOMAIN_MODEL.md#scope-and-sensitivity-classes)), [BUSINESS_RULES](BUSINESS_RULES.md) and [WORKFLOWS](WORKFLOWS.md) (including the [actor and authority matrix](WORKFLOWS.md#4-actor-and-authority-matrix)); the logical model in [DATABASE](../03-architecture/DATABASE.md); application structure in [ARCHITECTURE](../03-architecture/ARCHITECTURE.md); guardrails in [ENGINEERING_PRINCIPLES](../00-governance/ENGINEERING_PRINCIPLES.md). This document cites their identifiers and never restates, relaxes or extends a business rule: it turns approved authority and visibility into capabilities, grants, scope checks and projections. On conflict the owning document wins and the conflict goes to [change control](../00-governance/CHANGE_CONTROL.md).

**Boundary:** documentation only — no policy code, migration, seeder, package or test. Mechanisms (lock order, isolation, retries, command-log replay, caching) belong to P6, screens and copy to P7, tests to P8, and database roles, TLS, session-store provisioning and log storage to P9; each is handed over in [SECURITY §10](../03-architecture/SECURITY.md#10-handoff-obligations).

## 1. Conventions

- **Authority classes** are the values of `capabilities.authority_class` ([DATABASE §4.1](../03-architecture/DATABASE.md#41-iam--identity--access)): `ADM`, `ADM_PLUS` and `OWNER_ONLY`, matching the WORKFLOWS actors ADM, ADM+ and OWN!. SYS and BG are not capabilities: they run inside, or after, an authorized command (AZ-08, AZ-09).
- **Capability codes** follow the OB §17 form `area.action` — lower-case ASCII, dot-separated, snake_case segments. OB §17's own examples are used verbatim where they denote a V1 capability (`projects.view`, `products.edit`, `stock.receive`, `stock.dispatch`, `stock.adjust`, `invoice.issue`, `payment.record`, `profit.view`, `users.manage`). `roles.manage` is reserved for the deferred custom-role designer (V1_SCOPE D-01) and is not part of the V1 catalogue.
- **Audience notation** in the projection tables: **ALL** every active account; **SV** holders of `stock.view` (pool-wide, no company grant needed); **SV(c)**, **PV(c)**, **FV(c)** and **CV(c)** holders of `stock.view`, `projects.view`, `finance.view` or `cost.view` with company c in scope, and **(any)** in place of c at least one company in scope; **EV(c)** holders of `evidence.view` who may also view the evidence family (PJ-08); **OWN** the Owner; **SELF** the account itself; **none** never projected to any user. The Owner holds every capability and all companies (RG-02, RG-03), so every audience includes the Owner unless the row says **none**.
- **Identifier families** defined here: W4-01–W4-27 (the WORKFLOWS §4 rows, in table order), AZ (authorization model), RG (roles and grants), CS (company scope), PJ (projection), RD (read classes), XL (cross-company links), OD (Owner-only denials), DP (data paths) and D-PM (design decisions). Security controls cited as AU, EN, WS, FL, LG, SX, TM and H6–H9 are defined in SECURITY.

## 2. Authorization model

| ID | Rule | Grounds |
| --- | --- | --- |
| AZ-01 | Every request and command is authorized on the server in the order **authentication → company scope → capability → resource authorization → business preconditions**. Hidden buttons, disabled controls, route names and role labels are never authorization. | OB §17, §24; ENGINEERING_PRINCIPLES; ARCHITECTURE §8 |
| AZ-02 | Policies and gates test **capabilities**, never role names, so no policy or gate reads a role. Role identity is read only by the effective-capability resolver (RG-02), which honours an OWNER_ONLY capability only through the built-in OWNER role, by the last-active-Owner rule (RG-09) and by checks that concern the OWNER role itself without authorizing a request — the Owner-recovery command's target check (AU-14), the anomaly reconciliation (OD-16) and the Owner-login signal (LG-07). | OB §17; ARCHITECTURE §8, §19 |
| AZ-03 | Authorization is evaluated **per request** against the current committed grants, capabilities and account state. Grants are never cached across requests or stored in the session (per-request memoization only). A revoked grant or capability, or a deactivated account, is REJECTED on its next request — reads included — and on the next step of any job it started (DP-09, DP-14). | WF-ACC-01 exceptions; WF-ACC-02; AC-13 |
| AZ-04 | A **company-scope resolver** yields the actor's scope (CS-01, CS-02). Every query class, search, export, report, policy and action applies it first, before filtering, ranking, aggregation or pagination (CS-07). | ARCHITECTURE §8, §14; DATABASE §21 |
| AZ-05 | Every action re-checks inside its command, against current grants (the transaction mechanism is P6): the target record's company is in scope (Owner: all); **every company-owned reference** in the payload is resolved by public identifier and belongs to the same company unless it is one of the intentional links of section 8 (master and pool references follow CS-05 and CS-06); the actor holds the capability; the capability's prerequisites still hold (RG-06). | SF-CMD steps 2–3; ARCHITECTURE §5; C-32 |
| AZ-06 | Resource authorization is per record family in one policy per module; records are never locked to their creator — any authorized Admin continues open work (PX-07; WORKFLOWS §11). | BR-XC-02; PX-07 |
| AZ-07 | Business preconditions (SF-CMD steps 4–5) are evaluated after authorization; a precondition failure returns REJECTED or CONFLICT with the rule code and never discloses out-of-scope data. | SF-CMD; ARCHITECTURE §5 |
| AZ-08 | **Deterministic consequences versus chosen effects.** SYS consequences (W4-26) run inside the authorized command and may follow the approved automatic links of existing records — the allocation overlay of an existing consumption, cost re-attribution under BR-FIN-12/13 and AX-34, SF-CHARGE shares, revalidation (SF-REVAL) — without further grants, because the approved single action already fixes them (CM-17; AX-34). An effect the actor chooses on another company's record — a reservation cut, a restoration or reversal of its consumption, a closure, a new allocation — needs a grant on that company (and, for a new allocation, XL-1); otherwise the command is REJECTED and routed (QS-20; WORKFLOWS §11; CM-36). A stock-out that recognizes a **loss** on a lot — an adjustment decrease, a disposal, a count loss, an L-42 single-company confirmation of a loss — and its exact reversal (CM-28) need the bearer company in scope (SF-LOSS: the causal project's company, otherwise the lot's owning company), with XL-1 when the bearer differs from the lot's company; otherwise the command is REJECTED and routed. A condition change, re-inspection, location move or count recording changes only physical pool state and needs only its capability and one company in scope (CS-05). A reservation cut carried by a supply-reducing command also needs `stock.reserve`. | WORKFLOWS §11; SF-RSV-CUT; SF-LOSS; WF-INV-04–07; CM-17; CM-28; CM-36; AX-34; BR-ACC-01 |
| AZ-09 | BG work (W4-27): rendering runs in system context over the immutable issued snapshot and grants nothing (DP-17); exports and notifications run in the requesting or receiving actor's context and are re-authorized before output is produced and again when it is delivered (DP-07, DP-11). | ARCHITECTURE §11; BR-DOC-03 |
| AZ-10 | Every audit event records the **highest** authority class exercised in its command (DATABASE §23 `authority_class`) — a dispatch that carries an inter-company allocation is ADM_PLUS, and so is a payment whose duplicate warning is overridden; ADM_PLUS and OWNER_ONLY events feed the Owner review queue (section 10). | BR-XC-01; QS-14; WORKFLOWS §4 |
| AZ-11 | Denial responses (D-PM-08): a record outside scope is indistinguishable from a nonexistent one (not found); a record in scope without the capability is forbidden; a failed precondition is REJECTED with its rule code. | OB §41 authorization tests |
| AZ-12 | No impersonation, "login as", shared account or acting on behalf of another account exists; every action names the exact individual account. | BR-XC-02; DIR-018; FS-10 |

## 3. Role and grant model

| ID | Rule | Grounds |
| --- | --- | --- |
| RG-01 | Exactly two built-in launch roles exist, OWNER and ADMIN_OPERASIONAL; each user holds one role in V1 (`user_roles`). No launch role is added; custom roles and additional role packages stay deferred (D-01). | OB §17; CAP-01; REF-002/003 |
| RG-02 | **Effective capabilities** = the capabilities of the user's role in `role_capabilities` **plus** the user's active `user_capability_grants`. The built-in OWNER role carries every capability of the catalogue; the built-in ADMIN_OPERASIONAL role carries none, so every Admin capability is an individual grant. An OWNER_ONLY capability takes effect only through the built-in OWNER role: a grant of one to another account is refused at grant time, and the resolver ignores and security-logs one found in a user grant or in the `role_capabilities` of any other role, a future custom role included (LG-02; OD-16). | DIR-011; DIR-024 D-5; OB §17 |
| RG-03 | **Company scope:** holders of `scope.all_companies` (OWNER_ONLY) have every company; everyone else has the companies of their active `user_company_grants`. No row means no company (default deny). | DIR-011; BR-ACC-01 |
| RG-04 | **Default deny for a new Admin:** no company grant and no capability until the Owner grants them; the account sees only S0 master identity (DOMAIN_MODEL "S0 always") and its own profile. | DIR-011; BR-ACC-01 |
| RG-05 | Only `permissions.manage` (OWNER_ONLY) assigns the role and grants or revokes company grants and capability grants; `users.manage` (OWNER_ONLY) creates, activates and deactivates accounts. No account can grant anything to itself or to another account unless it holds these capabilities. | W4-01; OB §17 |
| RG-06 | **Prerequisites:** a capability is effective only while its prerequisites (section 4) are effective; a grant without its prerequisites is refused, and revoking a prerequisite silently disables its dependants until it is granted again. | least privilege |
| RG-07 | Capability grants are **global to the user**, not per company: an action on a record is allowed when the user holds the capability **and** the record's company is in scope. A person needing different capability sets in different companies cannot be expressed in V1; the resulting over-grant risk is GAP-032 (D-PM-06; future seam: a company-scoped capability grant, expand-only). | DATABASE §4.1 as approved |
| RG-08 | Separately grantable authority: every ADM_PLUS capability is its own grant row, given, reviewed and revoked per individual account with a reason; holding one ADM_PLUS capability confers no other. | DIR-024 D-5; WORKFLOWS §4 |
| RG-09 | **Last active Owner (L-34):** the command that would deactivate, or change the role of, the last active account holding the OWNER role is REJECTED; the check reads current committed state inside the command (P6 serializes it, H6-02). | L-34; WF-ACC-02 |
| RG-10 | **Deactivation** revokes the account's active grants in the same command, ends all its sessions (AU-09) and keeps every historical attribution; reactivation requires fresh grants. | WF-ACC-02; DATABASE §19 |
| RG-11 | Every account, role, grant and credential-link change is a business audit event with before/after, actor, reason and authority class (LG-01). | BR-XC-01; DATABASE §28 |
| RG-12 | Future custom roles (D-01) are additive rows of `roles` with their own `role_capabilities`; OWNER_ONLY capabilities stay exclusive to the OWNER role, and per-user grants keep working unchanged. | REF-003; OB §17 |

**AC-13 resolution.** Two Admin accounts with the same company grant differ only by their individual grants: an Admin holding `stock.view` sees the pooled physical availability (S1) and a Company-A-only Admin without it receives a denial on every pooled view, search facet and prop; neither obtains another company's S2–S4 data (section 7). Nothing depends on a role label, and neither case needs a new role or a custom-role designer.

**Mapping onto the approved tables** ([DATABASE §4.1](../03-architecture/DATABASE.md#41-iam--identity--access)): `capabilities` holds the catalogue of section 4 with its authority class; `role_capabilities` holds, for OWNER, every capability and, for ADMIN_OPERASIONAL, none; `user_capability_grants` holds each Admin's ADM and ADM_PLUS grants (the separately grantable ADM+ authorities of DIR-024 D-5 among them); `user_company_grants` holds company grants; `user_roles` holds the single launch role. No approved table or column changes.

## 4. Capability catalogue

Prerequisites (RG-06) are listed per capability; "grant" means at least one company in scope. Every command capability also needs at least one company in scope (CS-05) and the view capability of the records it shows (section 7).

| Code | Class | WORKFLOWS §4 row | Prerequisites | Covers |
| --- | --- | --- | --- | --- |
| `stock.view` | ADM | — (read class RD-02; the inventory permission of W4-12 and AC-13) | — | S1 pooled physical view: availability, locations, lots' physical attributes, serial presence, counts, pending-case quantities (PJ-01) |
| `projects.view` | ADM | — (read class RD-03) | grant | S2 business records of companies in scope (projects, demand, quotations without values, fulfilment, documents metadata, checklists, cases) |
| `finance.view` | ADM | — (read class RD-04) | grant | S3 sales, receivable and cash family: values, invoices, billing, receivables, payments, applications, credit, settlements, write-offs, disputes, bank accounts, statement lines, disbursements, other Cash-In, cashflow |
| `cost.view` | ADM | — (read class RD-05) | grant | S3 procurement and cost family: purchase prices, taxes and charges, supplier price history, lot costs, HPP, losses, expenses, allocation costs, own-company unattributed exposure |
| `profit.view` | ADM | — (read class RD-06) | `finance.view`, `cost.view` | Project and company profitability and margin |
| `evidence.view` | ADM | — (read class RD-08) | grant | S4 private files, per evidence family (PJ-08) |
| `reports.export` | ADM | W4-27 (exports) | the report's view capabilities | Excel/PDF/print exports of lists and reports (DP-07) |
| `reports.consolidated` | OWNER_ONLY | — (CALC-12) | — | Consolidated and group views, cross-company totals and the inter-company allocation report |
| `review.view` | OWNER_ONLY | — (QS-14) | — | The Owner review queue (section 10) |
| `audit.view` | OWNER_ONLY | — (BR-XC-01) | — | Workspace-wide business-audit search |
| `security_log.view` | OWNER_ONLY | — (BR-XC-03) | — | The security event log (LG-02) |
| `scope.all_companies` | OWNER_ONLY | — (BR-ACC-01) | — | All-company scope (RG-03) |
| `users.manage` | OWNER_ONLY | W4-01 | — | Accounts: create, edit, activate, deactivate; issue and revoke credential links (AU-12); user directory |
| `permissions.manage` | OWNER_ONLY | W4-01 | — | Role assignment, company grants and capability grants (OB §17 "manage permissions") |
| `companies.manage` | OWNER_ONLY | W4-02 | — | WF-MD-01: legal company, identity assets, bank accounts, numbering schemes, activation and deactivation |
| `tax.configure` | OWNER_ONLY | W4-02 | — | WF-MD-01 tax configuration: tax types, rate versions, company tax profiles and treatment rules (DIR-024 D-1) |
| `requirements.set_waiver_eligibility` | OWNER_ONLY | W4-03 | — | BR-PRJ-03 waiver eligibility |
| `requirements.relax` | OWNER_ONLY | W4-04 | — | Remove, make optional, relax the satisfaction mode or clear the client-original flag of a required non-waivable requirement |
| `writeoff.supersede` | OWNER_ONLY | W4-05 | — | Owner write-off supersession, alone or inside AX-22/AX-24 (L-17) |
| `stock.attribute_surplus` | OWNER_ONLY | W4-06 | — | AX-07 count-surplus attribution with a flagged cost basis (QS-18) |
| `stock.resolve_unattributed` | OWNER_ONLY | W4-07 | — | AX-37 Owner attribution, including shortfall closures from other lots, and its exact reversal (CM-27); QS-22 with snapshots |
| `receivables.write_off` | OWNER_ONLY | W4-08 | — | AX-24 write-off (DIR-019; BR-FIN-08) |
| `projects.force_complete` | OWNER_ONLY | W4-09 | — | AX-26 Force Complete |
| `import.commit` | OWNER_ONLY | W4-25 (commit) | — | Owner sign-off and commit of an opening import (AX-28; L-33) |
| `parties.manage` | ADM | W4-10 | `projects.view` | Client organization, unit, address and PIC masters; suppliers; archive |
| `products.edit` | ADM | W4-10 | — | Products and services, units, conversions, barcodes (including registration from a scan), serial policy, images, archive; price defaults also need their view capability (PJ-14) |
| `warehouse.manage` | ADM | W4-10 | `stock.view` | WF-MD-05 rack/location masters and per-product minimum stock |
| `projects.manage` | ADM | W4-11 | `projects.view` | WF-PRJ-01 project setup and amendment, demand lines, channel, SIPLAH metadata, the re-plan split of an unstarted remainder (WF-FUL-01, L-21; releasing its reservation also needs `stock.reserve`), draft discard (L-37), linked project (XL-4); selling prices, tax components and SIPLAH values also need `finance.view` |
| `quotation.manage` | ADM | W4-11 | `projects.view`, `finance.view` | WF-QUO-01 draft, revise, issue (DOC-01 through SF-ISSUE) and record rejection |
| `projects.confirm` | ADM | W4-11 | `projects.view` | WF-PRJ-02 quotation approval as confirmation, no-quotation confirmation, re-confirmation (AX-13) |
| `stock.reserve` | ADM | W4-12 | `stock.view`, `projects.view` | AX-01/02 reservation create, adjust and release; the reservation cut carried by a supply-reducing command (AZ-08) |
| `purchase.manage` | ADM | W4-13 | `cost.view` | WF-PUR-01/02 purchases, lines, taxes, charges (AX-29), supplementary purchases |
| `stock.receive` | ADM | W4-13 | `stock.view` | WF-INV-01 receiving (AX-04) through the receiving projection (PJ-18); the same-action reservation offer needs `stock.reserve` |
| `stock.dispatch` | ADM | W4-13 | `stock.view`, `projects.view` | WF-INV-03 dispatch (AX-03); a lot of another company additionally needs `stock.allocate_intercompany` (XL-1) |
| `stock.move` | ADM | W4-13 (warehouse work; WF-MD-05, L-36) | `stock.view` | Zero-net location moves |
| `stock.count` | ADM | W4-13 (warehouse work; WF-INV-05 counting) | `stock.view` | Open a count, record count lines, cancel an open count; applying the variance is `stock.count_apply` |
| `delivery.record` | ADM | W4-13 | `projects.view` | WF-FUL-02 delivery records (AX-09), discrepancies and their case, inspection facts |
| `dropship.confirm` | ADM | W4-13 | `projects.view` | WF-FUL-03 drop-ship confirmation (AX-08), with delivery when proof arrives together (L-40) |
| `handover.record` | ADM | W4-13 | `projects.view` | WF-FUL-04 service handover |
| `documents.issue` | ADM | W4-13 (PO), W4-14 | the subject family's view capability | WF-DOC-01 draft and issue of DOC-01–14 through SF-ISSUE, including the pre-payment Kuitansi (WF-FIN-08); invoice layouts go through `invoice.issue` |
| `evidence.upload` | ADM | W4-14 | `evidence.view` | WF-DOC-02 / SF-EVIDENCE uploads, versions and links within scope, each evidence type only with its family's view capability (PJ-08) |
| `invoice.issue` | ADM | W4-14 | `finance.view` | WF-FIN-01 invoice issue (AX-16) as DOC-06 or DOC-04 |
| `billing.record` | ADM | W4-14 | `finance.view` | WF-FIN-02 billing act (AX-17) |
| `payment.record` | ADM | W4-14 | `finance.view` | WF-FIN-03 payment fact (AX-18), same-command applications, remittance advice, Kuitansi link |
| `payment.apply` | ADM | W4-14 | `finance.view` | New applications of unapplied money or opening credit (SF-APPLY, AX-19); reallocation is `payment.reallocate` |
| `expense.record` | ADM | W4-14 | `cost.view` | WF-FIN-07 manual expense records |
| `disbursement.record` | ADM | W4-14 | `finance.view` | Cash-Out for purchases, expenses, tax remittances and real inter-company transfers (XL-5); refunds are `refund.record` |
| `cashin.record` | ADM | W4-14 | `finance.view` | Other Cash-In: supplier refunds, received cashback, transfer in (XL-5) |
| `bank_statement.record` | ADM | W4-14 | `finance.view`, `evidence.upload` | Bank statement lines with their BANK_STATEMENT evidence (L-22) |
| `requirements.manage` | ADM | W4-15 | `projects.view` | WF-ADM-01 add and tighten requirements, link satisfying documents or evidence; never the Owner-only fields (OD-05, OD-06) |
| `projects.complete` | ADM | W4-16 | `projects.view` | AX-25 normal completion |
| `receivables.dispute` | ADM | W4-17 | `finance.view` | Dispute hold set and clear (BR-FIN-08) |
| `requirements.waive` | ADM | W4-24 | `projects.view` | TIDAK BERLAKU on waiver-eligible requirements only (BR-ADM-02) |
| `import.prepare` | ADM | W4-25 (prepare) | `projects.view` and the view capabilities of the batch kind | WF-MIG-01 prepare, dry run and Admin sign-off, only for batches whose every company is in scope (PJ-20) |
| `stock.reverse` | ADM_PLUS | W4-18 | `stock.view` | Receipt reversal (AX-05), dispatch reversal and undelivered return (CM-05/06), movement reversals (CM-28/33), reversal of an erroneous restoration or return (CM-37, AX-36), identity substitution (CM-36, AX-35), exact loss reversal (CM-34) |
| `sales_return.record` | ADM_PLUS | W4-18 | `stock.view`, `projects.view` | WF-FUL-05 sales returns and drop-ship returns (CM-10–14/15) with their case |
| `purchase_return.record` | ADM_PLUS | W4-18 | `stock.view`, `cost.view` | WF-PUR-03 purchase returns (AX-11), replacement or refund declaration |
| `purchase.correct_cost` | ADM_PLUS | W4-18 | `cost.view` | CM-17/35 cost-correction cascade (AX-34) |
| `finance.contra` | ADM_PLUS | W4-18, W4-20 | `finance.view` | SF-CONTRA on payments, other Cash-In, disbursements, expenses, settlements and opening credits (CM-19/22/33/38; AX-20/33) |
| `payment.reallocate` | ADM_PLUS | W4-18 | `finance.view` | CM-21 reallocation (AX-19 superseding) |
| `invoice.correct` | ADM_PLUS | W4-18 | `finance.view` | CM-23/24 void or downward revision with typed basis and named replacement (AX-22); a standing write-off also needs `writeoff.supersede` (OD-07) |
| `documents.correct` | ADM_PLUS | W4-18 | the subject family's view capability | Revision and void of non-invoice documents (CM-26) |
| `billing.correct` | ADM_PLUS | W4-18 | `finance.view` | CM-25 billing-act correction with entry-error evidence |
| `decisions.supersede` | ADM_PLUS | W4-18 | the decision's view capability | CM-27 superseding decisions of ADM or ADM+ authority; OWN! decisions only by the Owner (OD-15) |
| `fulfillment.correct` | ADM_PLUS | W4-18 | `projects.view` | CM-08 delivery corrections, handover reversal lines, drop-ship confirmation reversal (CM-27) and its error reversal (CM-37) |
| `warnings.override` | ADM_PLUS | W4-18 | the record's view capability | Duplicate-payment and duplicate-evidence warning overrides (QS-21; DP-13) |
| `cash.correct` | ADM_PLUS | W4-18 | `finance.view` | Bank-line void, bank-citation correction, claimed-deduction supersession |
| `purchase.cancel` | ADM_PLUS | W4-19 | `cost.view` | AX-12 purchase cancellation |
| `refund.record` | ADM_PLUS | W4-20 | `finance.view` | AX-23 refund disbursement |
| `settlement.record` | ADM_PLUS | W4-20 | `finance.view`, `evidence.view` | AX-21 fee and tax settlements and the resolution of a residual settlement |
| `stock.adjust` | ADM_PLUS | W4-21 | `stock.view` | Adjustment decrease, disposal and loss recognition (AX-06/30), including a pending unexplained-loss case (SF-UNATTRIBUTED) |
| `stock.condition` | ADM_PLUS | W4-21 | `stock.view` | Condition change and re-inspection (WF-INV-04), including a pending condition-change case |
| `stock.count_apply` | ADM_PLUS | W4-21 | `stock.view` | Opname variance application (AX-06), found stock, pending cases and open findings |
| `stock.resolve_unattributed_evidence` | ADM_PLUS | W4-21 (W4-07 exception) | `stock.view`, `evidence.view` | AX-37 resolution fully identified by definitive later evidence, under OD-10, and its exact reversal |
| `stock.allocate_intercompany` | ADM_PLUS | W4-22 | `stock.view` | Inter-company allocation on a consumption, a loss or an unrecovered share (XL-1) |
| `projects.cancel_scope` | ADM_PLUS | W4-23 | `projects.view` | SF-CANCEL-LINE remaining-scope cancellation (AX-14) |
| `delivery.close` | ADM_PLUS | W4-23 | `projects.view` | Delivery formal closure (WF-FUL-02; CM-06/07) |
| `purchase.close_remainder` | ADM_PLUS | W4-23 | `cost.view` | AX-32 purchase-remainder closure |
| `cases.close` | ADM_PLUS | W4-23 | `projects.view` | SF-CASE correction-case closure |
| `projects.cancel` | ADM_PLUS | W4-23 | `projects.view` | AX-27 project cancellation |

**Counts:** 80 capabilities — ADM 37 (7 view/report, 30 command), ADM_PLUS 26, OWNER_ONLY 17 (5 view/scope, 12 command). Opening a correction case needs the capability of the correction or discrepancy it records; there is no standalone case capability. `GuardMaintenance` (DATABASE §3.1) is an audited operator console command under the administration database role, never a user capability (H9-03).

## 5. WORKFLOWS §4 mapping

| Row | WORKFLOWS §4 action family | Class | Capabilities |
| --- | --- | --- | --- |
| W4-01 | Users, roles, capabilities, company grants | OWN! | `users.manage`, `permissions.manage` |
| W4-02 | Company master: create, identity assets, bank accounts, numbering rules, deactivate | OWN! | `companies.manage`, `tax.configure` |
| W4-03 | Set waiver eligibility | OWN! | `requirements.set_waiver_eligibility` |
| W4-04 | Remove, make optional, relax or clear the client-original flag of a required non-waivable requirement | OWN! | `requirements.relax` |
| W4-05 | Supersede an Owner write-off | OWN! | `writeoff.supersede` |
| W4-06 | Attribute unexplained found stock | OWN! | `stock.attribute_surplus` |
| W4-07 | Resolve a pending unexplained-loss case (ADM+ only for an evidence-identified resolution, `DIR-027`) | OWN! | `stock.resolve_unattributed`; the ADM+ exception is `stock.resolve_unattributed_evidence` under W4-21 |
| W4-08 | Receivable write-off / formal disposition | OWN! | `receivables.write_off` |
| W4-09 | Force Complete (denied to Admin, also through any API) | OWN! | `projects.force_complete` |
| W4-10 | Client, supplier, product/service, unit, barcode, location and minimum-stock masters; archive | ADM | `parties.manage`, `products.edit`, `warehouse.manage` |
| W4-11 | Project setup and amendment, quotation, approval, commercial confirmation | ADM | `projects.manage`, `quotation.manage`, `projects.confirm` |
| W4-12 | Reservation create/adjust/release; reservation cut on shrinkage | ADM (inventory) | `stock.reserve` (prerequisite `stock.view`) |
| W4-13 | Purchase, PO, receiving, dispatch, delivery, drop-ship confirmation, service handover | ADM | `purchase.manage`, `stock.receive`, `stock.dispatch`, `delivery.record`, `dropship.confirm`, `handover.record`, `stock.move`, `stock.count`; the PO layout through `documents.issue` |
| W4-14 | Document issue, uploads, invoice issue, billing act, payment record/application, expense, disbursement, other Cash-In | ADM | `documents.issue`, `evidence.upload`, `invoice.issue`, `billing.record`, `payment.record`, `payment.apply`, `expense.record`, `disbursement.record`, `cashin.record`, `bank_statement.record` |
| W4-15 | Add or tighten administrative requirements | ADM | `requirements.manage` |
| W4-16 | Normal completion decision | ADM | `projects.complete` |
| W4-17 | Dispute hold set/clear | ADM | `receivables.dispute` |
| W4-18 | Corrections and reversals, including warning overrides | ADM+ | `stock.reverse`, `sales_return.record`, `purchase_return.record`, `purchase.correct_cost`, `finance.contra`, `payment.reallocate`, `invoice.correct`, `documents.correct`, `billing.correct`, `decisions.supersede`, `fulfillment.correct`, `warnings.override`, `cash.correct` |
| W4-19 | Purchase cancellation | ADM+ | `purchase.cancel` |
| W4-20 | Refunds, fee settlements, tax settlements, expense/disbursement corrections | ADM+ | `refund.record`, `settlement.record`, `finance.contra` |
| W4-21 | Stock adjustments, condition changes, opname variance, loss recognition, pending case, evidence-identified resolution | ADM+ | `stock.adjust`, `stock.condition`, `stock.count_apply`, `stock.resolve_unattributed_evidence` |
| W4-22 | Inter-company allocation (grant on the consuming company only) | ADM+ | `stock.allocate_intercompany` |
| W4-23 | Operational closures; project cancellation | ADM+ | `projects.cancel_scope`, `delivery.close`, `purchase.close_remainder`, `cases.close`, `projects.cancel` |
| W4-24 | Admin N/A on a waiver-eligible requirement | ADM | `requirements.waive` |
| W4-25 | Opening import: prepare and dry-run / commit after sign-off | ADM / OWN | `import.prepare` (ADM); `import.commit` (OWNER_ONLY) |
| W4-26 | Availability checks, numbering, snapshots, HPP/loss attribution, revalidation | SYS | none — inside the authorized command (AZ-08) |
| W4-27 | Rendering, exports, notifications, restock/overdue alerts | BG | rendering: system context (DP-17); exports: `reports.export` (DP-07); notifications and alerts: the recipient's projection (DP-11) |

Every row whose actor is ADM, ADM+ or OWN! maps to capabilities of that class; W4-25 is split exactly as the row splits its actors, and the SYS and BG rows map as stated. `stock.move` and `stock.count` sit under W4-13 because WORKFLOWS §4 lists only the variance application (W4-21) as ADM+ and treats location moves (L-36) and counting (WF-INV-05) as ordinary warehouse work.

## 6. Company-scope rules

| ID | Rule | Grounds |
| --- | --- | --- |
| CS-01 | The Owner's scope is every company, active or deactivated (`scope.all_companies`). | DIR-011 |
| CS-02 | An Admin's scope is the set of companies with an active company grant. A deactivated company stays in scope for history, collection and corrections; new business is refused by command preconditions (C-54). | BR-ACC-01/03 |
| CS-03 | A command's company is its target record's company; for a new aggregate root it is the company chosen in the payload, which must be in scope and active. A selected "current company" filter never substitutes for this check. | SF-CMD step 2; WF-ACC-01 |
| CS-04 | Every public identifier of a company-owned record in a payload, query or route is resolved through the scope resolver and must belong to the command's company, unless it is an intentional link of section 8 authorized by its XL rule. Resolution happens before or during validation, so an out-of-scope reference fails exactly like a nonexistent one — same message, status and timing (AZ-11). | AZ-05; C-32 |
| CS-05 | POOL records (locations, physical balances, lots' physical attributes, serials, counts, pending-case quantities) belong to no company: reads need `stock.view`; pool commands need their capability; any company-attributed effect inside a pool command (a named-lot loss, a cut, an allocation) follows the rule of that effect (AZ-08). Every command — pool and master commands included — also needs at least one company in scope: an Admin operates only within explicit company grants, so an account without any grant can read S0 and, with `stock.view`, S1, and nothing more. | DATABASE §15; BR-ACC-01; V1_SCOPE physical visibility |
| CS-06 | MASTER records show S0 identity to every active account; master S2 relations (client units, addresses, PICs; supplier addresses and contacts) need the view capability of PJ-10 and at least one company in scope; records reached from a master are filtered by CS-07. | DOMAIN_MODEL; GAP-006 |
| CS-07 | Lists, searches, counts, totals, reports and exports apply the scope filter inside the query, before ranking, aggregation and pagination; a result, count, facet or total never includes a record the actor cannot see. | DATABASE §21; ARCHITECTURE §14 |
| CS-08 | An Admin may list records and queue items of several companies in scope, each labelled with its company and sortable or filterable by its own row values, as the approved action queue across granted companies does (WF-ACC-01); an aggregate figure computed over S3 values of several companies — a sum, total, average, ratio or ranking — is a consolidated view reserved to `reports.consolidated` (CALC-12; DATABASE §15 "Owner consolidated views"). | CALC-12; DATABASE §15; WF-ACC-01 |

## 7. Resource and field projection

### 7.1 Classes and entitlements

Projection enforces the [DOMAIN_MODEL classes](DOMAIN_MODEL.md#scope-and-sensitivity-classes): Owner all; Admin S0 always, S1 only with inventory permission (`stock.view`), S2–S4 only within explicit company grants — and, within those grants, only with the view capability of the family (section 4). Pages receive explicit prop resources listing each field, never raw models (ARCHITECTURE §8); a field is projected only when the actor is entitled to its class, scope and family. A reference to another record is projected with that record's class and family, within the scope of the record that holds it — a project reference with `projects.view`, a purchase reference with `cost.view` — and a reference to a company outside scope shows the relation marker (PJ-04). The nine protected categories of DIR-011 (S3/S4) are never projected for a company outside scope.

| Class | Admin entitlement | Family view capabilities |
| --- | --- | --- |
| S0 | every active account | none |
| S1 | `stock.view`, pool-wide | none |
| S2 | company in scope | `projects.view`; `stock.receive` for the receiving projection (PJ-18) |
| S3 | company in scope | `finance.view`, `cost.view`, `profit.view` by family; also `stock.view` for a lot's non-cost provenance and, within scope, a movement's itemized fields and company (PJ-02–PJ-04), `projects.view` for the company identity assets printed on documents (PJ-19) and a receipt's supplier delivery reference (PJ-03), `stock.receive` for the receiving projection (PJ-18), and `stock.resolve_unattributed_evidence` for candidate lots and quantities without costs (PJ-07) |
| S4 | company in scope (pool evidence: PJ-08) | `evidence.view` plus the evidence family (PJ-08) |

### 7.2 Table classification

Each of the 124 tables of [DATABASE §4](../03-architecture/DATABASE.md#4-module-map-and-logical-model), with its default class, the Admin audience and field exceptions. The Owner sees every row except those marked **none** and never sees a password hash or token. The class column gives the classes of the table's own rows and columns: a table is split where DOMAIN_MODEL splits its concept ("S0 name; S2 relations", "S2 (S3 prices)") or where its own columns or rows carry different classes; a column that only references another record is projected as that record (section 7.1), and a restriction inside one class — a field shown only in some contexts — does not split a table.

| # | Table | Scope | Class | Admin audience and field exceptions |
| --- | --- | --- | --- | --- |
| 1 | `users` | GLOBAL | S2 | SELF; OWN (`users.manage`) directory; actor display name wherever a visible record shows attribution; `password` none |
| 2 | `roles` | GLOBAL | S2 | SELF (own role); OWN |
| 3 | `capabilities` | GLOBAL | S2 | SELF (own effective set); OWN |
| 4 | `role_capabilities` | GLOBAL | S2 | OWN |
| 5 | `user_roles` | GLOBAL | S2 | SELF; OWN |
| 6 | `user_capability_grants` | GLOBAL | S2 | SELF (active own grants); OWN |
| 7 | `user_company_grants` | GLOBAL | S2 | SELF (names of own granted companies); OWN |
| 8 | `companies` | COMPANY | S2 / S3 | PV(c) name, code and state; identity assets — NPWP, address, contact, director and logo — S3, shown within scope to PV(c), FV(c) or CV(c) because they print on every document the company issues (PJ-15, PJ-19); stamp and signature files OWN only (PJ-19) |
| 9 | `company_bank_accounts` | COMPANY | S3 | FV(c) |
| 10 | `numbering_schemes` | COMPANY | S2 | PV(c) read; changes OWN |
| 11 | `number_sequences` | COMPANY | S2 | OWN (numbering administration, W4-02); Admins see issued numbers on their records |
| 12 | `retired_numbers` | COMPANY | S2 | PV(c) |
| 13 | `tax_types` | GLOBAL | S0 | ALL read; changes OWN |
| 14 | `tax_rate_versions` | GLOBAL | S0 | ALL read; changes OWN |
| 15 | `company_tax_profiles` | COMPANY | S3 | FV(c) or CV(c) |
| 16 | `tax_treatment_rules` | GLOBAL / COMPANY | S0 / S3 | global rows ALL; company rows FV(c) or CV(c); changes OWN |
| 17 | `client_organizations` | MASTER | S0 / S2 | ALL name; `npwp` PV(any) |
| 18 | `client_units` | MASTER | S2 | PV(any) (PJ-10) |
| 19 | `client_addresses` | MASTER | S2 | PV(any) |
| 20 | `client_contacts` | MASTER | S2 | PV(any) (personal data) |
| 21 | `suppliers` | MASTER | S0 / S2 | ALL name; tax id, address and contact PV(any) or CV(any) (PJ-10) |
| 22 | `products` | MASTER | S0 / S1 | ALL; `min_stock_qty_base` SV |
| 23 | `units` | MASTER | S0 | ALL |
| 24 | `product_units` | MASTER | S0 | ALL |
| 25 | `product_barcodes` | MASTER | S0 | ALL |
| 26 | `product_price_history` | MASTER | S3 | PURCHASE_DEFAULT CV(any); SELLING_DEFAULT and SPJ_REFERENCE FV(any) (PJ-14) |
| 27 | `product_images` | MASTER | S0 | ALL (stored stripped, FL-03) |
| 28 | `projects` | PROJECT | S2 | PV(c); linked project per XL-4 |
| 29 | `project_siplah_details` | PROJECT | S2 / S3 | PV(c); money columns FV(c) |
| 30 | `project_lines` | PROJECT | S2 / S3 | PV(c); draft prices FV(c) |
| 31 | `commercial_confirmations` | PROJECT | S2 | PV(c) |
| 32 | `confirmation_line_pins` | PROJECT | S2 / S3 | PV(c); prices, sales value and tax FV(c) |
| 33 | `quotation_revisions` | PROJECT | S2 | PV(c) |
| 34 | `quotation_revision_lines` | PROJECT | S2 / S3 | PV(c); prices and values FV(c) |
| 35 | `line_scope_cancellations` | PROJECT | S2 | PV(c) |
| 36 | `project_line_balances` | PROJECT | S2 | PV(c) |
| 37 | `project_commercial_balances` | PROJECT | S3 | FV(c) |
| 38 | `project_state_transitions` | PROJECT | S2 / S3 | PV(c); money keys of the snapshots FV(c) (PJ-12); a `correction_reference` to another company's record masked (PJ-04) |
| 39 | `purchases` | COMPANY | S3 | CV(c); receiving projection (PJ-18) to `stock.receive` in scope |
| 40 | `purchase_lines` | COMPANY | S3 | CV(c); product and quantities in the receiving projection |
| 41 | `purchase_line_tax_components` | COMPANY | S3 | CV(c) |
| 42 | `purchase_charges` | COMPANY | S3 | CV(c) |
| 43 | `purchase_charge_line_shares` | COMPANY | S3 | CV(c) |
| 44 | `purchase_line_balances` | COMPANY | S3 | CV(c); quantity columns in the receiving projection |
| 45 | `purchase_line_closures` | COMPANY | S3 | CV(c); closed quantities in the receiving projection (PJ-18) |
| 46 | `purchase_line_adjustments` | COMPANY | S3 | CV(c) |
| 47 | `locations` | POOL | S1 | SV |
| 48 | `inventory_lots` | POOL × COMPANY | S1 / S3 | SV code and product, with remainders from the balance tables, and the relation marker (PJ-01, PJ-04); source company, acquisition kind, business date and acquired quantity SV(source c); source references, within the source company's scope, as the referenced records; flagged basis CV(source c) (PJ-02) |
| 49 | `serial_units` | POOL | S1 | SV presence — in stock or not, and in stock its lot code, location and condition; any other state only when the movement or drop-ship event that explains it is shown to the actor (PJ-03) |
| 50 | `stock_movements` | POOL | S1 / S3 | itemized per PJ-03 — a movement of a company in scope SV(c), a pool movement without a company SV: direction, type, date, reason and actor; project PV(c); `supplier_delivery_reference` PV(c) or `stock.receive` in scope, as row 52; purchase CV(c); a movement of a company outside scope never itemized |
| 51 | `stock_movement_entries` | POOL | S1 | SV, itemized with their movement (PJ-03); consumption and restoration references as rows 54 and 56 |
| 52 | `receiving_lines` | COMPANY | S2 / S3 | PV(c) or `stock.receive` in scope, its `supplier_delivery_reference` included; purchase-line cost links CV(c) |
| 53 | `dispatch_lines` | PROJECT | S2 | PV(c) |
| 54 | `stock_consumptions` | POOL × COMPANY | S3 | bearer side CV(bearer c) with source identifiers masked (PJ-05, PJ-06); source side CV(source c) with bearer masked |
| 55 | `stock_consumption_balances` | POOL × COMPANY | S2 | PV(bearer c) |
| 56 | `stock_restorations` | PROJECT / COMPANY | S2 | PV(c) of the case or bearer company |
| 57 | `stock_product_balances` | POOL | S1 | SV |
| 58 | `stock_scope_balances` | POOL | S1 | SV |
| 59 | `stock_lot_balances` | POOL | S1 | SV |
| 60 | `stock_reversal_balances` | POOL | S1 | SV |
| 61 | `reservations` | PROJECT ↔ POOL | S1 / S2 | SV quantity with the relation marker; project PV(c) |
| 62 | `reservation_events` | PROJECT ↔ POOL | S1 / S2 | SV quantities; references PV(c) |
| 63 | `stock_counts` | POOL | S1 | SV |
| 64 | `stock_count_scopes` | POOL | S1 | SV |
| 65 | `stock_count_lines` | POOL | S1 | SV |
| 66 | `stock_count_findings` | POOL | S1 / S3 | SV surplus quantity and state; attributed company and flagged basis CV(attributed c) |
| 67 | `unattributed_loss_cases` | POOL | S1 | SV product, scope, quantities, state, date, reason and actor; causal project reference PV(c), otherwise the relation marker (PJ-07) |
| 68 | `unattributed_loss_candidates` | POOL × COMPANY | S3 | CV(c) own candidate rows; lots and quantities without costs to `stock.resolve_unattributed_evidence` holders for companies in scope (PJ-07) |
| 69 | `unattributed_loss_exposures` | POOL × COMPANY | S3 | CV(c) own row; quantities to `stock.resolve_unattributed_evidence` holders for companies in scope (PJ-07) |
| 70 | `unattributed_loss_group_exposures` | POOL × COMPANY | S3 | CV(c) own row; quantities to `stock.resolve_unattributed_evidence` holders for companies in scope (PJ-07) |
| 71 | `unattributed_loss_resolutions` | POOL | S1 / S3 | SV kind and date; decider and reason to holders of CV of an assigned company |
| 72 | `unattributed_loss_resolution_lines` | POOL × COMPANY | S3 | CV(assigned c) |
| 73 | `lot_cost_entries` | POOL × COMPANY | S3 | CV(source c) |
| 74 | `lot_cost_balances` | POOL × COMPANY | S3 | CV(source c) |
| 75 | `cost_attributions` | POOL × COMPANY | S3 | CV(bearer c) with source identifiers masked (PJ-06); lot draws also CV(source c) with bearer masked (PJ-05) |
| 76 | `purchase_charge_balances` | COMPANY | S3 | CV(c) |
| 77 | `purchase_charge_share_balances` | COMPANY | S3 | CV(c) |
| 78 | `expenses` | COMPANY | S3 | CV(c) |
| 79 | `dropship_confirmations` | PROJECT | S2 | PV(c); purchase reference CV(c) |
| 80 | `dropship_confirmation_lines` | PROJECT | S2 | PV(c) |
| 81 | `deliveries` | PROJECT | S2 | PV(c); its proof through evidence links (PJ-08) |
| 82 | `delivery_lines` | PROJECT | S2 | PV(c) |
| 83 | `delivery_closures` | PROJECT | S2 | PV(c) |
| 84 | `service_handovers` | PROJECT | S2 | PV(c) |
| 85 | `service_handover_lines` | PROJECT | S2 | PV(c) |
| 86 | `document_types` | GLOBAL | S0 | ALL |
| 87 | `documents` | COMPANY / PROJECT | S2 | PV(c) metadata |
| 88 | `document_versions` | COMPANY | S2 / S3 | PV(c) metadata and number; payload by document family (PJ-15); bank account FV(c) |
| 89 | `document_renditions` | COMPANY | as its version | payload family of its version (PJ-15) |
| 90 | `file_objects` | per owner record | S0–S4 | through the owning record only (PJ-08, PJ-15, PJ-19); never listed directly |
| 91 | `evidence_documents` | COMPANY / POOL | S4 | EV(c) by evidence family; pool evidence per PJ-08 |
| 92 | `evidence_document_versions` | COMPANY / POOL | S4 | as its evidence |
| 93 | `bank_statement_lines` | COMPANY | S3 | FV(c) |
| 94 | `evidence_balances` | COMPANY | S3 | family of its evidence (FV(c) or CV(c)) |
| 95 | `evidence_links` | COMPANY / POOL | S2 | visible only when both the evidence and the linked record are visible |
| 96 | `admin_requirements` | PROJECT | S2 | PV(c); `waiver_eligible` readable, writable OWN only |
| 97 | `admin_requirement_links` | PROJECT | S2 | PV(c) |
| 98 | `admin_requirement_waivers` | PROJECT | S2 | PV(c) |
| 99 | `invoices` | COMPANY | S3 | FV(c) |
| 100 | `invoice_versions` | COMPANY | S3 | FV(c) |
| 101 | `invoice_version_lines` | COMPANY | S3 | FV(c) |
| 102 | `invoice_tax_components` | COMPANY | S3 | FV(c) |
| 103 | `invoice_balances` | COMPANY | S3 | FV(c) |
| 104 | `invoice_billing_acts` | COMPANY | S3 | FV(c) |
| 105 | `payments` | COMPANY | S3 | FV(c); transfer citation per XL-5 |
| 106 | `payment_claimed_deductions` | COMPANY | S3 | FV(c) |
| 107 | `application_source_balances` | COMPANY | S3 | FV(c) |
| 108 | `payment_applications` | COMPANY | S3 | FV(c) |
| 109 | `deduction_settlements` | COMPANY | S3 | FV(c); the cited evidence per PJ-08 |
| 110 | `receivable_writeoffs` | COMPANY | S3 | FV(c) |
| 111 | `receivable_dispute_holds` | COMPANY | S3 | FV(c) |
| 112 | `opening_customer_credits` | COMPANY | S3 | FV(c) |
| 113 | `prepayment_kuitansi_links` | COMPANY | S3 | FV(c) |
| 114 | `disbursements` | COMPANY | S3 | FV(c); linked purchase or expense details CV(c) |
| 115 | `other_cash_receipts` | COMPANY | S3 | FV(c); transfer citation per XL-5 |
| 116 | `financial_contras` | COMPANY | S3 | FV(c) |
| 117 | `money_fact_balances` | COMPANY | S3 | FV(c) |
| 118 | `audit_events` | per audited entity | S0–S4 | record timelines projected per PJ-11; workspace search OWN (`audit.view`) |
| 119 | `security_events` | GLOBAL | S2 | OWN (`security_log.view`); SELF own last successful login and own credential events (PJ-17) |
| 120 | `command_log` | GLOBAL | — (operational) | none: the P6 idempotency record, never business truth (DATABASE §4.13); its effects are visible through their records and audit events, and a replay re-authorizes (DP-16) |
| 121 | `correction_cases` | COMPANY | S2 / S3 | PV(c); money counters FV(c) or CV(c) by family; context links per XL-3 |
| 122 | `import_batches` | COMPANY set | S3 | `import.prepare` holders with every batch company in scope; the source file through its record (PJ-20) |
| 123 | `import_rows` | COMPANY | S3 | `import.prepare` holders with the row's company in scope |
| 124 | `legacy_archive_items` | COMPANY | S3 | FV(c); its file through the record to FV(c) holders who also hold `evidence.view` (PJ-08) |

Framework infrastructure tables outside the 124 (sessions, queue, job batches, failed jobs, cache, cache locks, migrations) and the application credential-token store of SECURITY AU-11 are never projected raw and hold identifiers rather than business data (DP-09; AU-11; H9-04); the Owner's session and credential-link views are the summaries of PJ-17, and a session row's raw address and user agent live only as long as the session. `TECH-021`: the operational export-request record of [CONCURRENCY_IDEMPOTENCY H6-04](../03-architecture/CONCURRENCY_IDEMPOTENCY.md#17-p5-obligations-h6-01h6-12) is likewise never projected raw: its requester alone sees the status of its own requests (DP-07; SECURITY FL-09).

**Classification counts:** 124 tables — 101 with one class (S0 7, S1 11, S2 34, S3 47, S4 2); 19 split (`products` S0/S1; `client_organizations` and `suppliers` S0/S2; `tax_treatment_rules` S0/S3; `reservations` and `reservation_events` S1/S2; `inventory_lots`, `stock_movements`, `stock_count_findings` and `unattributed_loss_resolutions` S1/S3; `companies`, `project_siplah_details`, `project_lines`, `confirmation_line_pins`, `quotation_revision_lines`, `project_state_transitions`, `receiving_lines`, `document_versions` and `correction_cases` S2/S3); 3 whose class follows each owning record or version (`document_renditions`, `file_objects` and `audit_events`); and 1 operational record outside the classes (`command_log`).

### 7.3 Special projections

| ID | Projection rule | Grounds |
| --- | --- | --- |
| PJ-01 | **Pooled physical view** (S1, `stock.view`): per product, location and condition — ON HAND, RESERVED, UNUSABLE, AVAILABLE; per lot its opaque code (PJ-23), remaining quantity per location and condition, oldest-first order (a picking aid, never a date or valuation), serials in stock, and the relation marker (PJ-04). It is served by physical-only query classes that never select S3 tables and never return a lot's acquisition kind, source references, acquired quantity, acquisition date, supplier, price or cost, nor the identity of a source company outside scope. | BR-ACC-01; WF-INV-03; DATABASE §5.1; ARCHITECTURE §8 |
| PJ-02 | **Lot detail:** the physical fields of PJ-01 to `stock.view`, with the source company named when it is in scope (PJ-04). For a lot whose source company is in scope: its acquisition kind, acquisition date and acquired quantity also go to `stock.view`, being that company's own warehouse facts, which its movement history shows (PJ-03); its source reference (receiving line, import row, restoration or count finding) is projected as that record (section 7.1); and its flagged cost basis and lot cost go to `cost.view`. For a lot whose source company is outside scope the path ends at the physical fields of PJ-01. | BR-ACC-02; DOMAIN_MODEL Receiving Lot S3; DATABASE §5; GAP-006 |
| PJ-03 | **Movement and serial history** (`stock.view`): a movement is itemized — direction, type, date, product, lot code, location, condition, quantity, serial, reason and actor — when its company is in scope or when it is a pool movement without a company (location moves, counts, pending cases); its project and its supplier delivery reference need `projects.view` — the reference also goes to `stock.receive` holders through the receiving projection (PJ-18), and DOC-12 prints it for `projects.view` (PJ-15) — and its purchase `cost.view`, each for that company (DOMAIN_MODEL stock movement "S1 qty; S3 cost/refs"). A movement of a company outside scope is that company's transaction detail (DIR-011): it is never itemized, and its quantity reaches the actor only through the pooled figures of PJ-01, so another company's lot shows its current physical state and never its acquisition kind, date or quantity (DOMAIN_MODEL Receiving Lot S3). A serial's history follows the same rule, while its presence stays S1 (row 49). Costs never appear in movement history. | DOMAIN_MODEL stock movement, Receiving Lot; BR-ACC-01/02; DIR-011 |
| PJ-04 | **Relation marker:** where a physical row relates to a company outside the actor's scope — a lot's source company, a reservation's or movement's company — the projection shows only "another company", never its name, code or any other identifier; within scope the company is named. The marker is non-identifying and discloses nothing beyond what the approved workflows themselves reveal: WF-INV-03, SF-RSV-CUT and QS-20 disclose by their REJECTED-and-routed outcome that a lot or reservation lies outside scope; L-42 and SF-UNATTRIBUTED disclose whether one or several other companies hold lots in a scope; and in a group of two companies "another company" can only be the other one. | DOMAIN_MODEL (Legal Company S2; Receiving Lot S3); D-PM-02 |
| PJ-05 | **Allocation, source side:** holders of `cost.view` for the lot's source company see the draw from its lot (quantity, date, cost drawn, "allocated to another company") with the bearer company, project, reason and actor masked unless the bearer company is also in scope. | WF-INV-07; L-03 |
| PJ-06 | **Allocated-cost treatment, consuming side:** holders of `cost.view` for the consuming (bearer) company see the allocated cost in full as the consuming project's own HPP, loss or unrecovered share — product, quantity, amount, date, allocation marker, reason and actor. Only source-side identifiers are masked unless the source company is also in scope: the source company's identity, the lot's acquisition references, supplier, purchase, price and the source's other lots. The cost itself is never hidden from an entitled viewer, and the per-unit cost derivable from it is the approved consequence of WF-INV-07. The lot code is shown only to holders of `stock.view`. | WF-INV-07; BR-ACC-02; WORKFLOWS §14 (masking deferred to P5) |
| PJ-07 | **Unattributed-loss case:** the recognizing actor and every `stock.view` holder see the physical result — product, scope, recognized, pending, found and resolved quantities, state, date, reason and actor; the candidate companies, their lots, quantities in scope and costs are S3 of each candidate: holders of `cost.view` see their own companies' candidate, exposure and group rows; holders of `stock.resolve_unattributed_evidence` see, for companies in their scope, the candidate lots and quantities without costs; the Owner sees all (QS-22). The number and identity of other candidate companies are never shown to a non-Owner. | DIR-026/027; SF-UNATTRIBUTED; DATABASE §5.8 |
| PJ-08 | **Evidence and private files:** evidence is visible with `evidence.view`, its company in scope and the view capability of its family: finance family (BANK_STATEMENT, PAYMENT_PROOF, TRANSFER_PROOF, FEE_ADVICE, TAX_WITHHOLDING_PROOF, TAX_COLLECTION_PROOF, TAX_PAYMENT_PROOF, LEGACY_DOCUMENT) `finance.view`; cost family (SUPPLIER_BILL) `cost.view`; TAX_INVOICE `finance.view` or `cost.view`; project family (CLIENT_SPK, CLIENT_ORDER, SIPLAH_ORDER, THIRD_PARTY_HPS, DELIVERY_PROOF, NPWP, NIB, OTHER) `projects.view`; company WAREHOUSE_RECORD `stock.view`; OPENING_SIGNOFF the import rule (PJ-20). Uploading, versioning or linking evidence of a type needs the same family capability. **Pool evidence** (no company) of the types WAREHOUSE_RECORD and OTHER holds physical content of shared stock only — count sheets, damage reports and photos of shared stock (DATABASE §4.10) — never a company's own document: its uploader confirms that scope (H7-11), and every pool upload reaches the Owner through QS-14 (section 10). It links only to pool records and is visible with `stock.view` and `evidence.view`, whichever pool record it is linked to. A pool OPENING_SIGNOFF — the multi-company opening sign-off — follows PJ-20. Links never widen visibility: the type family and these rules decide. | DATABASE §4.10, §22; C-58; FS-12 |
| PJ-09 | **Supplier price history** (S3 per company) is derived only from purchases of companies in the actor's scope and shown per company, with `cost.view`; it is never pooled across companies and never part of the supplier master (S0). | BR-ACC-02; CAP-02; SH-01 |
| PJ-10 | **Shared masters:** a master detail page shows S0 identity to everyone and master S2 relations (client units, addresses and PICs to `projects.view`; supplier tax id, address and contact to `projects.view` or `cost.view`, both with at least one company in scope); related projects, purchases, invoices, prices and documents appear only through CS-07-filtered queries, and no count or total reveals out-of-scope relationships. | GAP-006; DOMAIN_MODEL partners |
| PJ-11 | **Audit before/after:** each key of `before`, `after` and `metadata` carries the class and family of its source column and is projected like that column; an event about an out-of-scope company is invisible; a cross-company event (an allocation, a transfer, an unattributed resolution) shows each side only its own keys, with the other side's identifiers masked (PJ-04); actor names follow the visibility of the event. Workspace-wide audit search is `audit.view`. | BR-XC-01; DATABASE §23; PN-7 is planner analysis only |
| PJ-12 | **Snapshots in JSON** (`predicate_snapshot`, `residual_obligations`, `unmet_predicates`, tax components, payloads): each key is classified like PJ-11; money keys need `finance.view` or `cost.view` by family. | DATABASE §4.5 |
| PJ-13 | **Admin action queue:** the union, over companies in scope, of the QS signals whose read class (section 7.4) the actor may see, shown per company; QS-14 and the attribution parts of QS-18 and QS-22 are Owner-only; QS-20 items are visible to the Owner and to actors with every affected company in scope; the originating actor sees only its own REJECTED outcome. | CAP-17; AC-23; WORKFLOWS §10 |
| PJ-14 | **Price defaults** (MASTER, S3): purchase default with `cost.view`, selling and SPJ-reference defaults with `finance.view`, each with at least one company in scope; editing needs `products.edit` as well. | DOMAIN_MODEL price defaults S3 |
| PJ-15 | **Documents and renditions:** metadata (type, number, state, dates) with `projects.view`; payload and rendition by family — finance (DOC-01, DOC-04–07, DOC-09, DOC-11, DOC-13) `finance.view`; cost (DOC-02, DOC-14) `cost.view`; operational (DOC-03, DOC-08, DOC-10, DOC-12) `projects.view` — always within the document's company. An operational layout that prints a price or cost belongs to the finance or cost family instead (P7/P11 templates). Company identity assets embedded by the renderer are part of the rendered document. | BR-DOC-01/04; DATABASE §10 |
| PJ-16 | **Correction cases:** the case record with `projects.view` for the case company; residual and equation counters by family; the causal project or source lot of another company per XL-3. | BR-CR-04; DATABASE §4.13 |
| PJ-17 | **Accounts, grants, sessions and credential events:** each account sees its own profile, role, effective capabilities and scope (to shape the UI; the server still decides) and its own credential events — link issued (when and by whom), link used (when, device summary), password set or changed — with a notice at the first login after any of them (AU-13); the Owner (`users.manage`) sees the directory, grants, each account's active sessions as a device summary with start and last activity, and each credential link's purpose, issuer, issue time, expiry and use or revocation — never a session identifier, session payload, raw address or token. | BR-XC-02; OB §17; AU-10; AU-12 |
| PJ-18 | **Receiving projection:** holders of `stock.receive` with the purchase's company in scope see the open purchase lines needed to receive — purchase number, supplier name (S0), project link, product, ordered, received and remaining quantities — without prices, taxes, charges or costs, and the receiving lines recorded against them, with the supplier delivery reference that also shows on the receipt's movement (PJ-03; rows 50, 52). | WF-INV-01; minimum operational projection |
| PJ-19 | **Company master:** within scope, name, code and state with `projects.view`; the identity assets — NPWP, addresses, contacts, director and logo (DOMAIN_MODEL S3) — with `projects.view`, `finance.view` or `cost.view`, because they print on every document the company issues (PJ-15); bank accounts with `finance.view`; raw stamp and signature files only to the Owner — the renderer uses them in system context. | DOMAIN_MODEL company identity assets; AC-01 |
| PJ-20 | **Opening import:** batches, rows, control totals, dry-run reports, sign-off evidence (OPENING_SIGNOFF, PJ-08) and source files are visible to the Owner and to `import.prepare` holders with every company of the batch in scope (rows: the row's company); raw source files are S4 private files. | WF-MIG-01; L-33; DATABASE §24 |
| PJ-21 | **Security and operational records:** security events OWN (`security_log.view`); each account its own last successful login and credential events (PJ-17); `command_log`, session payloads and identifiers, tokens and raw addresses none. | BR-XC-03; LG-02 |
| PJ-22 | **Derived figures:** a figure takes the highest class and every family of its inputs (CALC-10 profit needs `profit.view`); an S1 quantity is never combined with a cost for a viewer not entitled to that cost (company stock value is `cost.view` of that company); pending unattributed quantities appear in company stock and profit views as the company's own exposure only (PJ-07). | BR-XD-05; CALC-01–14 |
| PJ-23 | **Opaque lot codes:** a lot code — on screen or on a printed label — is opaque and workspace-wide, carrying no company, supplier, purchase, receipt, acquisition-kind, date or cost component (generation H6-10). Public identifiers are time-ordered UUIDv7 values (DATABASE §16) that reveal only when their record was created, which the record itself shows; business numbers that encode a company, such as project and document numbers, are shown only within scope. | GAP-006; PJ-04; DATABASE §16 |

### 7.4 Read classes

| ID | Read class | Capability and scope | CALC and QS served |
| --- | --- | --- | --- |
| RD-01 | Master lookup (S0) | ALL | product, unit, barcode, client-organization and supplier lookup; tax types and rates; document types |
| RD-02 | Pooled physical (S1) | `stock.view` | CALC-01, CALC-02 (quantity), QS-01–QS-04 (their project parts under RD-03), QS-17, QS-18 (stale counts and open findings), pending quantities of QS-22, QS-21 items on stock events of companies in scope |
| RD-03 | Project operations (S2) | `projects.view` in scope | CALC-04, QS-06–QS-08, QS-15 and QS-16 (non-money items), QS-20 (all affected companies in scope), QS-21 items on delivery events |
| RD-04 | Sales, receivables and cash (S3) | `finance.view` in scope | CALC-05–CALC-08, CALC-14, QS-09–QS-13, QS-19, money items of QS-07/15/16, QS-21 payment items |
| RD-05 | Procurement and cost (S3) | `cost.view` in scope | CALC-03, CALC-09, QS-05 (its quantities also to `stock.receive` holders through PJ-18), purchase and lot-cost views, supplier price history, own exposure of QS-22 |
| RD-06 | Profitability (S3) | `profit.view` in scope | CALC-10, CALC-11 |
| RD-07 | Consolidated (Owner) | `reports.consolidated` | CALC-12, group dashboards, the inter-company allocation report |
| RD-08 | Private files (S4) | `evidence.view` + family (PJ-08) | evidence, proofs, uploads; QS-21 evidence items |
| RD-09 | Owner review | `review.view`, `stock.resolve_unattributed`, `stock.attribute_surplus` | QS-14, QS-22 with snapshots, QS-18 attribution |
| RD-10 | Audit and security | per record (PJ-11); `audit.view`, `security_log.view` | activity timelines; audit and security search |

CALC-13 is a period convention and applies inside each class. QS-21 (duplicate warnings overridden or confirmed) is shown per family within scope — stock events under RD-02, delivery events under RD-03, payments under RD-04, evidence under RD-08 — and every override and confirmation also reaches the Owner through QS-14 (section 10).

## 8. Cross-company link authorization

The five intentional link families of the [DATABASE §15 inventory](../03-architecture/DATABASE.md#15-company-scope-and-tenancy) (C-32). No other cross-company reference is creatable (CS-04).

| ID | Link family | Create or change | Visibility |
| --- | --- | --- | --- |
| XL-1 | **Inter-company allocation overlay** — consumptions and lot-draw cost rows whose source ≠ bearer; their restorations, substitutions and corrections; the cost-only RETURN_SETTLEMENT loss; UNATTRIBUTED_LOSS shortfall closures | A new allocation (dispatch AX-03, loss charged to another company's project AX-30/L-44, unrecovered share charged to another company's causal project AX-11) needs `stock.allocate_intercompany` and a grant on the **consuming (bearer) company only**, plus the command's own capability; the allocation reason is mandatory and the authorizing actor recorded (C-28). Shortfall closures inside an Owner resolution are Owner-only (OD-09). Reversal or re-attribution along the same consumption needs the correcting command's capability and a grant on the company owning its case or record (AZ-08); creating a new allocation in a correction (a substitution onto another company's lot) needs `stock.allocate_intercompany` again. No source-company grant is needed or implied. | PJ-05, PJ-06; never source-company visibility (WF-INV-07) |
| XL-2 | **Correction-case side** (`case_company_id` on consumptions, restorations and cost rows) | The actor needs the case company in scope; rows for the other side are written only as SYS consequences of that case's commands. | Each side sees its own rows; the other company's case identity is masked (PJ-04) |
| XL-3 | **Correction-case context** (`causal_project_id`, `source_lot_id`) | Set by SYS from the cited facts (L-38; the restored consumption's lot), never chosen freely. | The case company sees the other company's project or lot as named only when that company is in scope, otherwise the relation marker; the lot code is S1 |
| XL-4 | **Linked project** (`linked_project_id` + `link_reason`) | `projects.manage` with **both** companies in scope — an actor cannot link to a project it cannot see. | The link target is named when in scope, otherwise "a project of another company" with the viewer's own reason |
| XL-5 | **Real inter-company transfer** (a Cash-In citing another company's INTERCOMPANY_TRANSFER_OUT) | The Cash-Out needs `disbursement.record` in the paying company; the citing Cash-In needs `payment.record` or `cashin.record` in the receiving company and `finance.view` of the cited disbursement, so both companies must be in scope — otherwise the Owner records it. | Each side sees its own fact; the other side's disbursement or receipt identity is masked unless in scope |

## 9. Owner-only denials

Each rule holds on every path — web route, Inertia visit or partial reload, form replay, import pipeline, queued job, console and any future API — because each Owner-only effect exists only inside one action class that checks the capability (AZ-05) and records `authority_class` OWNER_ONLY (AZ-10); no job, import, console or API path performs it for another account.

| ID | Denial | Enforcement |
| --- | --- | --- |
| OD-01 | Accounts: only `users.manage` creates, edits, activates or deactivates accounts or issues credential links; an account itself changes only its own password (AU-13). | W4-01; AU-12 |
| OD-02 | Grants: only `permissions.manage` assigns roles and grants; nobody grants to itself; OWNER_ONLY capabilities take effect only through the built-in OWNER role and are never granted to an Admin (RG-02); the last active Owner is protected (RG-09). | W4-01; L-34 |
| OD-03 | Company master, bank accounts, numbering and identity assets: `companies.manage` only; raw stamp and signature files are Owner-only (PJ-19). | W4-02 |
| OD-04 | Tax configuration: `tax.configure` only; Admins read configured values and snapshot them on their transactions. | W4-02; DIR-024 D-1 |
| OD-05 | Waiver eligibility: `waiver_eligible` is writable only through `requirements.set_waiver_eligibility`; an Admin requirement edit that touches it is REJECTED (field-level rule). | W4-03; BR-PRJ-03 |
| OD-06 | Requirement relaxation: an Admin change may only add or tighten; removing, making optional, relaxing the satisfaction mode or clearing the client-original flag of a required non-waivable requirement is REJECTED without `requirements.relax`. | W4-04; DIR-024 D-5 |
| OD-07 | Write-off supersession: an `invoice.correct` void or revision of an invoice with a standing write-off, or an application of recovered money that supersedes a write-off, is REJECTED unless the same command carries `writeoff.supersede` (AX-22, AX-24; L-06, L-17). | W4-05 |
| OD-08 | Found stock: a count application by an Admin leaves surplus findings OPEN; only `stock.attribute_surplus` attributes them (AX-07). | W4-06 |
| OD-09 | Unexplained-loss resolution: only `stock.resolve_unattributed` assigns a pending case by choice, closes a shortfall from other lots with an allocation, or reverses an Owner resolution. | W4-07; DIR-026/027 |
| OD-10 | **DIR-027 evidence exception** — `stock.resolve_unattributed_evidence` records a resolution only when the server verifies all of: (a) resolution kind EVIDENCE_IDENTIFIED and a mandatory reason; (b) at least one authentic pool evidence document — the only kind DATABASE §4.10 lets a pending case cite — linked to the case with role PRIMARY_PROOF, recorded or versioned after the case's recognition (the "later" evidence), visible to the actor under PJ-08 whoever linked it, never a generated document version or rendition and not a checksum reproduction of one (FS-12); (c) every resolved unit named by lot with its quantity, each lot a candidate of the immutable snapshot, all named lots of **one** source company, which becomes the assigned company — derived, never chosen; (d) each named lot still holding at least its named quantity of claims in the case's scope at commit, with no shortfall closure from any other lot; (e) the assigned company in the actor's scope — the loss authority of AZ-08; (f) Σ ≤ pending, within the company's exposure and the group bound (C-37). Every such resolution appears in QS-14; its exact reversal is by the same capability or the Owner. What the evidence proves is the actor's audited judgment, reviewable by the Owner, as with every evidence-based ADM+ correction. | W4-07, W4-21; DIR-027; DATABASE §4.10, §5.8; L-45 |
| OD-11 | Write-off: only `receivables.write_off` (AX-24). | W4-08; DIR-019 |
| OD-12 | Force Complete: only `projects.force_complete`, with the confirmation bound to the shown blocker snapshot (AX-26); `projects.state`, transition kinds and completion snapshots are never accepted from a client payload (WS-02); no import, job, console or API path can complete a project by force; Admin and API attempts are denied and security-logged (LG-02). | W4-09; BR-PRJ-04; AC-21 |
| OD-13 | Opening import commit: only `import.commit`, after the joint sign-off (L-33). | W4-25 |
| OD-14 | Owner-only views: consolidated views, the review queue, workspace audit search, the security log and all-company scope are `reports.consolidated`, `review.view`, `audit.view`, `security_log.view` and `scope.all_companies`. | CALC-12; QS-14; BR-XC-03 |
| OD-15 | A decision of OWN! authority recorded in error is superseded only by the Owner — a write-off, a surplus attribution or an Owner resolution (CM-27 "same authority"); `decisions.supersede` covers ADM and ADM+ decisions only. | CM-27; L-32 |
| OD-16 | Consistency: an audit event whose recorded authority class differs from the highest class its command exercised (AZ-10), an OWNER_ONLY capability in a user grant or in the `role_capabilities` of any role but the built-in OWNER role, or an OWNER_ONLY event by an account without the OWNER role is an authorization anomaly — security-logged as AUTHORIZATION_ANOMALY and checked by a P8 reconciliation (H8-05). | AZ-10; RG-02; LG-02 |

**Count:** 16 Owner-only denial rules (OD-01–OD-16).

## 10. Owner review queue access

QS-14 is a derived view, never a stored queue (DATABASE §3), as WORKFLOWS §10 and DATABASE §23 define it: every ADM+ correction or closure, deduction settlements and overridden warnings, read from the audit events of authority class ADM_PLUS or OWNER_ONLY (AZ-10), evidence-identified resolutions included (OD-10), together with the items WORKFLOWS lists for the Owner — credit linked to a written-off invoice (WF-FIN-05), unbacked-refund residuals (WF-FIN-06) and the short-payment exception view (WF-FIN-04). P5 adds the likely-duplicate confirmations of QS-21 (L-49), pool-evidence uploads (PJ-08), cross-scope and cross-family evidence duplicates (DP-13), out-of-scope import collisions (DP-18) and authorization anomalies (OD-16). Only `review.view` opens it; an Admin request for it is denied (AZ-11). Items link to their records under the Owner's full projection; the Owner acts on them only through the normal commands and CM rows.

## 11. Data-path coverage matrix

| ID | Data path | Control | Enforcement point | P8 denial obligation |
| --- | --- | --- | --- | --- |
| DP-01 | URL and route identifiers | public identifiers only (DATABASE §16); resolve → scope → capability → projection; AZ-11 responses | route binding + policy + query class | forged and foreign public_id on every record route (H8-01) |
| DP-02 | Query parameters (filter, sort, include, page size, cursor) | whitelisted keys and values; a company filter is intersected with scope; unknown keys rejected; opaque cursors re-validated (WS-03) | form request + query class | foreign company filter, unknown sort key, oversized page |
| DP-03 | Payload references | CS-04 per reference; server-derived fields never accepted (WS-02) | form request + action | foreign reference in each command; mass assignment of company, authority and state fields |
| DP-04 | API | no external API and no API tokens in V1; any future `/api/v1` reuses the same actions, policies and projections; OWNER_ONLY commands are never exposed to token clients | route registry | route audit: no unauthenticated or token route to a command |
| DP-05 | Downloads (evidence, renditions, identity assets, exports, import sources) | authorization through the owning record at request time; no signed URL as sole authorization (FL-07) | download controller | foreign file, rendition, export and import source |
| DP-06 | Search and lookup (SKU, barcode, serial, project, document, client, supplier) | S0 lookup open; company records scoped before ranking (CS-07); serial lookup returns S1 presence to `stock.view` and history only within scope (PJ-03); document-number lookup scoped | query class | wrong-company search, serial and number probes |
| DP-07 | Exports | same query classes and filters as the page (ARCHITECTURE §14); `reports.export`; each export records the company set, view capabilities and masking context it was generated under and is re-authorized at job start and at delivery, when the requester's current scope and capabilities must still include all of them — otherwise the file is withheld and purged; formula neutralization (FL-09); export events logged (LG-02) | export job + download controller | export after revoking one filtered company or a view capability; foreign filter; formula payloads |
| DP-08 | Reports and dashboards | read classes of section 7.4; per-company figures (CS-08); consolidated Owner-only | query class | wrong-company report filter; consolidated view as Admin |
| DP-09 | Queued jobs | payloads carry identifiers, the actor and company context — never S3 values or secrets; authorization re-checked before producing and before delivering output | job middleware + action | queued export of a revoked or deactivated actor |
| DP-10 | Caches | V1 caches no S2–S4 query result (DATABASE §27); any later cache is keyed by the actor's full effective scope set and capability set, or holds only S0/S1 or already-masked data filtered per request | P6 mechanism (H6-05) | cache key isolation test when a cache is added |
| DP-11 | In-app alerts and notifications | derived from QS queries at display time with the recipient's projection; stored notifications hold identifiers and a neutral type only | query class | alert for a revoked company |
| DP-12 | Audit and activity timelines | PJ-11 | query class | cross-company event keys; out-of-scope actor names |
| DP-13 | Evidence duplicate warnings | detection across the workspace (FL-10) but disclosure only for evidence the actor may view under PJ-08 — company in scope, `evidence.view` and the family's view capability: such a match shows the warning and needs `warnings.override` with a reason; any other match, in another company or in an invisible family, shows the actor nothing and is listed for the Owner (section 10) | upload action + review view | duplicate oracle: an upload matching evidence of another company or of an invisible family reveals nothing |
| DP-14 | Revoked access | AZ-03; deactivation ends sessions (AU-09); open pages cannot fetch new data; exports and jobs re-check | auth middleware + resolver | revoked grant with an open session, a partial reload and a queued export |
| DP-15 | Inertia shared, partial, optional and deferred props and validation errors | WS-07; a deferred or optional prop runs the same scope-first query class and resource as its page (AZ-04, CS-07) | controller + resources | partial reload asking for an unauthorized prop; deferred prop across companies; error bag leakage |
| DP-16 | Command outcomes and replay | a command identifier is bound to its original actor and company, and a replay returns the recorded outcome only to that actor after re-authorization — reuse by another account is a CONFLICT that discloses nothing (GAP-008); COMMITTED, REJECTED and CONFLICT responses carry only in-scope state and identifiers, cross-company result identifiers masked (PJ-04) | action envelope (P6 H6-03) | replay after revocation; replay by another account |
| DP-17 | Rendering and issued PDFs | the renderer reads only the immutable payload and allowlisted assets in system context; access to the output follows PJ-15 | render job (FL-08) | foreign rendition; SSRF payloads |
| DP-18 | Opening import | PJ-20; parsing hardening (FL-06); a batch or row identity that collides with a batch or committed row outside the preparer's scope is reported only as "not importable — Owner review" and listed for the Owner (section 10) | import actions | batch spanning an out-of-scope company; collision probe against another company's batch |
| DP-19 | Print views | identical to their pages | controller | print of a foreign record |
| DP-20 | Error pages and messages | no stack traces, SQL or out-of-scope data; one response for missing and out-of-scope (WS-10) | exception handler | error-based existence probe |

**Coverage:** 20 data paths; every clause of V1_SCOPE's enforcement list (URLs, IDs, query parameters, payloads, API calls, file-download paths, search, exports, caches and jobs) maps to DP-01–DP-10 and the remaining paths come from AC-13, AC-23 and the P5 authorization.

## 12. Design decisions

| ID | Decision | Alternatives rejected |
| --- | --- | --- |
| D-PM-01 | Role and grant model: the OWNER role carries every capability; each Admin capability is an individual grant; OWNER_ONLY never grantable (RG-02). It satisfies AC-13 per account with the approved §4.1 tables unchanged. | Role as ceiling with a per-role baseline for Admins: every Admin would hold the baseline, so an Admin without inventory permission would need a second role (a new launch role, excluded) |
| D-PM-02 | Other companies' identity is never shown in physical views; a non-identifying relation marker is (PJ-04). DOMAIN_MODEL classes the Legal Company as S2 and lot provenance and movement references as S3, so the identity is protected outside grants; the workflows need only the marker. | Showing the owning company's name or code as S0: contradicts DOMAIN_MODEL and would widen visibility (Owner-level) without an operational need |
| D-PM-03 | Allocated cost: the consuming project's cost is shown in full to entitled viewers; only source-side identifiers are masked (PJ-06). | Hiding the allocated cost: narrows approved visibility; showing the source company: exposes source S3 |
| D-PM-04 | Duplicate warnings are disclosed only inside the actor's scope; out-of-scope matches go to the Owner with no signal to the actor, not even a non-identifying notice (DP-13). | A generic notice: still confirms that another company holds the document |
| D-PM-05 | View capabilities per family: `projects.view` (S2, and within scope the company identity assets printed on documents and a receipt's supplier delivery reference — PJ-19, PJ-03), `finance.view`, `cost.view`, `profit.view` (S3), `evidence.view` (S4), `stock.view` (S1, and within scope a lot's non-cost provenance and a movement's itemized fields and company — PJ-02–PJ-04); `stock.receive` opens only the receiving projection (PJ-18) and `stock.resolve_unattributed_evidence` only candidate lots and quantities without costs (PJ-07). | One S3 capability: forces over-grant; per-table capabilities: unmanageable |
| D-PM-06 | Capability grants are global per user (RG-07), matching the approved `user_capability_grants` shape; the limitation is tracked as GAP-032. | Company-scoped capability grants: a schema change beyond a narrow amendment |
| D-PM-07 | Framework-native gates and policies over the approved §4.1 tables; no third-party permission package. | A role/permission package: duplicate tables and role-name APIs that invite role checks (ARCHITECTURE §19) |
| D-PM-08 | Out of scope → not found; in scope without capability → forbidden (AZ-11). | Forbidden for everything: an existence oracle for other companies' records |
| D-PM-09 | SYS propagation along approved automatic links needs no extra grant; actor-chosen cross-company effects need grants or routing, and a loss on a lot needs its bearer company (AZ-08). | Grants on every touched company: would route approved single-action corrections such as CM-17/AX-34 to QS-20 although their cross-company effects are deterministic consequences of the corrected fact |
| D-PM-10 | Admin lists may span companies in scope; aggregate figures over S3 values of several companies are Owner-only (CS-08). | Admin totals across granted companies: a partial consolidation, and CALC-12 is Owner-only |
| D-PM-11 | Movements of a company outside scope are not itemized; their quantities reach the actor only through pooled figures (PJ-03). | Itemizing them with the relation marker: exposes another company's acquisitions, sales, losses and returns by date and quantity (DIR-011 unrelated transaction detail) |
| D-PM-12 | Pool evidence of shared stock holds physical content only and stays visible to holders of `stock.view` and `evidence.view`, with an uploader confirmation and the Owner's review of every pool upload (PJ-08). | Restricting case-linked pool evidence to actors with every candidate company in scope: blocks DIR-027 resolvers who hold the assigned company and depends on links that can change; pool-wide visibility without the content rule: a channel for company documents |

## 13. Traceability and downstream obligations

- **Approved inputs:** DIR-011 and the V1_SCOPE [physical visibility](../01-product/V1_SCOPE.md#physical-visibility-and-company-isolation) and [completion](../01-product/V1_SCOPE.md#normal-completion-and-exceptional-override) contracts; BR-ACC-01–03, BR-XC-01–03, BR-ADM-01–03, BR-PRJ-02–04, BR-FIN-08; WORKFLOWS §2, §4, §10, §11, §13, §14, WF-ACC-01/02, WF-INV-07, SF-UNATTRIBUTED, L-03, L-34, L-42, L-45, L-49; DATABASE §4.1, §5.1, §5.8, §6.5, §15, §21–§23, §28, §31; ARCHITECTURE §8, §9, §11, §14.
- **P1 anchors:** CAP-01, CAP-09, CAP-10, CAP-11, CAP-13, CAP-17, CAP-18; AC-09, AC-12, AC-13, AC-21, AC-23; OWN-02; REF-002, REF-003, REF-004.
- **Downstream:** P6 implements the mechanisms named in AZ-03, AZ-05, RG-09, DP-09, DP-10 and DP-16 (H6); P7 presents masks, markers, denials and grant screens (H7); P8 proves every W4 row, OD rule and DP path with positive and denial tests (H8); P9 provisions what SECURITY §10 lists (H9). The section-8 closure table and all counts are verified in [P5_QUALITY_GATE](../03-architecture/P5_QUALITY_GATE.md).

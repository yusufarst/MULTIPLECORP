## 13. Correction and history representation

Committed history never disappears; a correction is always a new row that references what it corrects, never the reverse (BR-CR-01/02; FS-02). No table has an `is_deleted` flag.

| Primitive (BR-CR-06) | Persistent structure | Applies to |
| --- | --- | --- |
| EDIT | UPDATE with `lock_version` and audit before/after, only while a row is a draft or has no committed effect; a stale `lock_version` returns CONFLICT (SF-CMD step 5) | DRAFT projects and lines, DRAFT quotation/invoice/document versions, purchase lines before the first effect, requirements before satisfaction, masters |
| REVISION | a new version row with `supersedes_…_id` UNIQUE (linear chain), the parent's `current_…_id` pointer moved in the same transaction | `quotation_revisions`, `document_versions`, `invoice_versions`, `confirmation_line_pins`, `purchase_charge_line_shares` |
| VOID | state VOIDED with reason, actor and time on the version (and the invoice); the number stays occupied; never cascades | documents and invoices |
| REVERSAL | a linked row ≤ the original net of prior reversals: restorations (DISPATCH_REVERSAL, LOSS_REVERSAL, RETURN_ERROR_REVERSAL, and the movement-less DROPSHIP_CONFIRMATION_REVERSAL) capped by `stock_consumption_balances` for movements and confirmations that created consumptions; MOVEMENT_REVERSAL capped by `stock_reversal_balances` for condition changes, restorations (CM-37), drop-ship return receipts, surplus lots and opening lots (with BASIS_REVERSAL cost rows for the lots' basis), and by the case counters for pending-case recognitions, findings and conversions; a DROPSHIP_ERROR_REVERSAL once and exactly for a movement-less drop-ship restoration recorded in error; UNATTRIBUTED_RESOLUTION_REVERSAL once per resolution; `reverses_*` delivery and handover lines, each exact and at most once; receipt reversal with its RE_RECEIPT correction; cost contra rows | stock movements, drop-ship confirmations, deliveries, handovers, losses, unexplained-loss attributions |
| RETURN | a commercial event with its own rows: sales-return restorations, PURCHASE_RETURN consumptions, drop-ship return lots, each inside a `correction_cases` row | returns (BR-XD-01) |
| REALLOCATION | the old `payment_applications` row SUPERSEDED plus a new ACTIVE row citing it | payment applications |
| CONTRA | `financial_contras` for money facts and write-off supersession; signed contra rows in `cost_attributions` and `lot_cost_entries` for cost | payments, other Cash-In, disbursements, expenses, settlements, opening credits, costs |
| COMPENSATING ACTION | a superseding decision row of the same authority (`supersedes_…_id`, L-32) and REVALIDATE transitions (SF-REVAL); a superseded purchase-remainder closure posts the exact inverse of AX-32 (closed quantity restored, re-spread contra'd, the share pending again); a superseding claimed-deduction row; a reasoned bank-line citation correction; a residual settlement's resolution | confirmations, closures, waivers, billing acts, attributions, completion, remittance advice, citations, residuals |
| ARCHIVE / INACTIVE | `is_active` false with `deactivated_at/by/reason` on masters; company deactivation (BR-ACC-03) | masters and companies only |

- **Current version:** the row not referenced by any `supersedes_…_id`; hot paths use the maintained pointer (`current_version_id`, `current_pin_id`, `current_billing_act_id`), written in the same transaction as the new version.
- **Correction cases:** corrective rows carry `correction_case_id` keyed with the company that owns the case (movements, restorations, consumptions, cost attributions, contras, purchase adjustments, delivery closures, settlements, invoice versions through their revision action), so one case gathers every linked action; its residual and equation figures are guard counters posted by those rows, its closure CHECKs refuse to close while a residual remains, and a later fact that changes a CLOSED case's figures reopens it in the same transaction (BR-CR-04; L-06; L-46).
- **Before/after:** for UPDATEs of SR and MD rows the audit event stores before and after values; for append-only rows the original and its correction are themselves the before/after pair.
- **Deleting drafts:** only a DRAFT project, draft version or unconfirmed line with no committed dependent may be deleted, with an audit event holding its last content. Because PostgreSQL grants DELETE per table, not per row state, each draft-bearing table carries a BEFORE DELETE trigger that rejects the delete unless the row is in its draft state (DRAFT project, DRAFT quotation, invoice or document version and their lines, an unconfirmed project line) — the RESTRICT foreign keys then reject it if anything committed references it. A discarded project's number stays consumed in its counter, is recorded by a `retired_numbers` tombstone in the same transaction and is never reused (L-37).

## 14. Snapshot matrix

Snapshot at commitment, never at render (BR-SN-01/02). Two techniques, chosen per need: **value snapshots** copied into typed columns when later computations read them, and **immutable references** when the referenced row itself can never change.

| Historically significant data | Where it is fixed | Technique | Why |
| --- | --- | --- | --- |
| Company legal identity, issuer identity | document payload; identity asset files by immutable id and checksum | value + immutable reference | Issued documents must survive master changes (AC-01) |
| Client organization, unit, address, PIC as used | document payload; `invoice_versions` client columns | value | Outputs and receivable records keep the counterparty as used (AC-02) |
| Supplier identity | `purchases.supplier_snapshot`; PO payload | value | Purchase history keeps the supplier as used |
| Product/service description | line `description` columns; payload | value | Masters are "true now" |
| Transaction unit, conversion ratio, base quantity | line `entered_*`, `ratio_to_base`, `qty_base` (CHECKed) | value | Annex 11 ratio change |
| Prices (purchase, selling, SPJ reference) | pins, quotation, purchase and invoice lines; payload for SPJ values printed | value | Master price changes never rewrite history (OB §13) |
| Tax | `*_tax_components` rows and pin/quotation JSON with rule id, treatment, rate, base, amount | value | Configuration is effective-dated and may change (D-1) |
| SPJ/admin values and SIPLAH metadata | `project_siplah_details` locked after first channel-dependent commit; payload | value, locked | BR-PRJ-05 |
| Bank destination | payments, disbursements and issued document versions reference immutable `company_bank_accounts` rows through company-scoped composite keys; document payloads print the referenced account | immutable reference + value | The destination actually used, never another company's account |
| Document content | `document_versions.payload` + `payload_sha256` | value | Render retries reproduce the issued version (BR-DOC-03) |
| Lot cost and candidate exposure | `lot_cost_entries`; `unattributed_loss_candidates` and the immutable `exposure_qty_base` of `unattributed_loss_exposures` | value | Provenance and DIR-027 snapshot |
| Counted basis | `stock_count_scopes.basis_version` and `system_qty_at_start` | value | Staleness is judged against the basis actually counted (L-14) |

Not snapshotted by design: operational screens show live master names; user identity is referenced by id (users are never deleted); product images and locations are live references.

## 15. Company scope and tenancy

One workspace, one database, logical company scope (DIR-027 §22; OB §3–4).

| Category | Tables | Representation |
| --- | --- | --- |
| Company-owned business records | projects and their children, purchases, invoices, payments, applications, settlements, write-offs, disbursements, other Cash-In, expenses, documents, evidence, bank accounts and statement lines, correction cases | `company_id NOT NULL` on every aggregate root and on rows that must stay within one company; child lines inherit through their root |
| Shared physical inventory | locations, ledger entries, stock balances, serials, counts, reservations' quantities, pending cases' quantities | no company partition; quantity only |
| Economic attribution | `inventory_lots.source_company_id`; consumption and cost-row bearers; `lot_cost_*`; candidate companies of pending cases | explicit columns, composite keys to lots and projects |
| Global/platform records | users, roles, capabilities, grants, tax types and rates, document types, audit and security events, command log | no company or an explicit `company_id` context column |
| Masters | products, units, barcodes, price defaults, clients, suppliers | shared (S0 identity); company-specific history is derived per company |
| Owner consolidated views | derived queries across companies | Owner-only (P5); no stored consolidation |

**Composite keys that make cross-company references impossible** (D-DB-08): purchases → projects; purchase lines, reservations and dispatch lines → project lines; invoices → projects **and the project's client**; payment applications → (company, client, payment or opening credit) and (company, client, invoice); deduction settlements → payment and invoice of the same company and client; write-offs → invoices; payments, disbursements, statement lines **and issued document versions** → the company's own bank accounts (a Company B invoice cannot print Company A's account); expenses → projects; consumption and cost-row bearers → projects of the bearer company, keyed to the dispatch or resolution line that caused them; consumption and cost-row sources → the lot's source company, itself keyed to the acquisition that created the lot; drop-ship confirmations and deliveries → purchases and projects of the company; documents → subjects of the same company; company evidence, source-bill and bank-line citations → the citing company's own evidence and statement lines; requirement links → documents and evidence of the requirement's company; `correction_case_id` references → cases of the same company or of the case-owning side (`case_company_id`). Nullable scope members are all-or-none (§1), so a NULL company can never smuggle a scoped reference past MATCH SIMPLE. The general rule: every reference from a company-owned row to another company-owned record carries `company_id` in the key, except the intentional links listed next.

**Audited inventory of intentional cross-company links** (the earlier "three links" statement undercounted):

| Link | Where | Control |
| --- | --- | --- |
| Inter-company allocation overlay | `stock_consumptions` and lot-draw `cost_attributions` with source ≠ bearer; the restorations, substitutions and corrections that reverse or re-attribute them; the cost-only RETURN_SETTLEMENT loss charged to another company's causal project; UNATTRIBUTED_LOSS shortfall closures | reason required (CHECK), authorizing actor, composite keys to both the lot's source and the bearer's project; allocation follows consumption (C-28) |
| Correction-case side | `case_company_id` on consumptions, restorations and cost rows citing a case owned by the source or bearer company | CHECK `case_company_id IN (source, bearer)`; composite key to that company's case |
| Correction-case context | `correction_cases.causal_project_id` (a purchase-return case of the lot's company naming the causal project of another company) and `correction_cases.source_lot_id` (a sales-return or damaged-unit case of the bearer company naming another company's lot) | informational links for the case's figures; costs move only through the overlay rows above |
| Linked project | `projects.linked_project_id` with `link_reason` (OB §4) | reason required; no financial effect |
| Real inter-company transfer | `payments.transfer_from_disbursement_id` and `other_cash_receipts.transfer_from_disbursement_id` citing the other company's INTERCOMPANY_TRANSFER_OUT | kind-typed composite key; capped by the disbursement's `transfer_cited_amount` (BR-FIN-07) |

Pool-to-company references are not cross-company links between company records: lots carry their source company, pending cases name candidate companies and assigned companies, count findings name the attributed company, and pool evidence belongs to no company. Physical tables carry no money, supplier price history is derived per company, and lot costs sit in separate S3 tables, so shared physical inventory cannot become a path to another company's financial records (GAP-006). P5 enforces default deny on top of this shape.

## 16. Identifier strategy

- **Internal key:** `id bigint GENERATED ALWAYS AS IDENTITY` on every table — compact, index-local, readable in debugging, the only foreign-key target.
- **Public identifier:** `public_id uuid` (UUIDv7: time-ordered, so its unique index stays local) only on routable aggregate roots — users, companies, client organizations, suppliers, products, projects, quotation revisions, purchases, stock movements, stock counts, unattributed-loss cases, drop-ship confirmations, deliveries, service handovers, documents, document versions, file objects, evidence documents, invoices, payments, disbursements, correction cases and import batches. URLs, exports, file downloads and route binding use it, so internal ids are never enumerable. Child lines, ledgers, guards and snapshots never get one. UUIDv7 is generated by the application (framework support verified at DEP-07) or by the database where the chosen PostgreSQL version provides it.
- **Business numbers:** project numbers, formatted document numbers, lot codes, SKUs, barcodes and serials are unique within their scope and human-facing, but never primary or foreign keys; SKUs, barcodes and serials are unique by their normalized key (`lower(sku)`, `code_key`, `serial_key`), and retired project numbers stay recorded in `retired_numbers`.
- **External identifiers:** SIPLAH references, supplier delivery/shipment references, bank line references and issuer references are attributes with business uniqueness where they identify an event (§26), never primary keys (OB §9).
- **Import identity:** `import_rows.import_identity` per import kind, the only link to legacy keys (§24).

## 17. Money, quantity and date types

| Logical type | PostgreSQL representation | Rules |
| --- | --- | --- |
| Money (rupiah) | `numeric(18,2)` — up to about 10^16 rupiah with sen | Exact; sign constrained per column (`> 0` for facts, `>= 0` for values, signed only in ledgers); derived splits round to the Rp1 increment with residual absorption (D-DB-03; annex 10) |
| Percentage / tax rate | `numeric(9,6)` percent (e.g. 11.000000, 0.250000) | Configuration data only; margins and ratios are computed on read, never stored |
| Tax base fraction | two integers `base_numerator`, `base_denominator` | Represents configured fractional bases exactly |
| Quantity | `numeric(18,3)` base units, limited by `products.quantity_scale` | Serialized and counted goods scale 0; never float |
| Conversion ratio | `bigint ≥ 1` | Integer multiples (L-31) |
| Business date | `date` (WIB calendar day) | Periods, aging and effective dates follow it (BR-DT-05/06) |
| recorded_at | `timestamptz` stored in UTC, displayed in WIB | Immutable audit order (BR-DT-01) |
| Due, validity, deadline, planned dates | `date` attributes | Forward-looking; not business dates (L-09) |
| Effective range | `daterange` `[from, to)` with exclusion constraints | Requires the `btree_gist` extension (DEP-07) |
| Document period | `text period_key` (`YYYY` or `YYYY-MM`) | Derived from business_date at issue and stored |
| Counters and versions | `bigint` | Sequences, guard versions, ledger order |
| Content hashes | `text` of 64 lowercase hex characters | SHA-256 of files and payloads |
| Snapshots and reports | `jsonb` | Never for a field a constraint or computation needs |

Integer minor units were rejected for money (D-DB-03): `numeric` is exact in PostgreSQL, aggregates exactly, reads naturally in reports, and the application handles it as a decimal string through an arbitrary-precision decimal type — never PHP float (ARCHITECTURE §6).


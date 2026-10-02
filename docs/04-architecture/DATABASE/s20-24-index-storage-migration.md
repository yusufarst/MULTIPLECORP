## 20. Index and query design

Indexes follow the P3 workflows, not every column. Rules: every primary, unique and exclusion constraint already indexes its columns; every foreign-key column that is joined from its parent or checked by a restricted delete gets an index; partial indexes serve "open" queues; list pages use keyset pagination on (`business_date`, `id`) or (`id`); P6 confirms each with measured query plans (OB §29; GAP-017). `TECH-021`: [PERFORMANCE §4 and §12](../../06-api-performance/PERFORMANCE.md#12-index-register-and-verification-procedure) refine the keyset key per company and register the indexes P6 adds or extends; nothing is measured before P8.

| Workflow need | Index (logical) |
| --- | --- |
| Exact barcode scan | partial UNIQUE `product_barcodes (code_key) WHERE is_active`, the scan normalized the same way |
| Exact SKU | UNIQUE `products (lower(sku))` |
| Serial scan (product unknown) | `serial_units (serial_key)` plus the UNIQUE (`product_id`, `serial_key`) |
| Product, client, supplier name search | GIN trigram on `products.name`, `client_organizations.name`, `client_units.name`, `suppliers.name` (§21) |
| Project search and open-project lists | UNIQUE (`company_id`, `project_number`); GIN trigram on `projects.title`; partial `projects (company_id, deadline) WHERE state IN ('DRAFT', 'ACTIVE')` |
| Current availability | primary keys of `stock_product_balances`, `stock_scope_balances`, `stock_lot_balances` |
| Picking suggestion (oldest-first aid) | partial `stock_lot_balances (product_id, location_id) WHERE qty > 0`; `inventory_lots (product_id, business_date)` |
| Reservations | partial `reservations (product_id) WHERE state = 'ACTIVE'`; partial UNIQUE (`project_line_id`) WHERE ACTIVE |
| Receiving and dispatch history | `stock_movements (movement_type, business_date)`, `(project_id, business_date)`, `(purchase_id)`; `stock_movement_entries (lot_id, id)`, `(product_id, id)`, `(serial_unit_id, id)`, `(unattributed_case_id)` |
| Project documents | `documents (project_id, document_type_code)`; `document_versions (document_id, version_no)`; UNIQUE (`company_id`, `document_type_code`, `formatted_number`) |
| Invoice status, active receivables, aging | partial `invoice_balances (company_id, current_due_date) WHERE outstanding > 0`; `invoices (project_id)`, `(company_id, client_organization_id)` |
| Payment lookup and applications | `payments (company_id, business_date)`, `(company_id, client_organization_id)`, `(bank_statement_line_id)`, `(reference)`; partial `payment_applications (invoice_id) WHERE state = 'ACTIVE'` and `(payment_id) WHERE state = 'ACTIVE'` |
| Company and date reporting | `(company_id, business_date)` on `invoice_versions` (through invoice), `payments`, `other_cash_receipts`, `disbursements`, `expenses`; `cost_attributions (bearer_company_id, business_date)` and `(bearer_project_id)` |
| Action-queue signals (QS) | partial indexes: `purchase_line_balances` with an open remainder (QS-05), `correction_cases WHERE state = 'OPEN'`, `deduction_settlements WHERE state = 'RESIDUAL'` and refunds with `money_fact_balances.shortfall_amount > 0` (QS-07), `document_renditions WHERE state IN ('PENDING', 'FAILED')` (QS-08), `payments WHERE bank_statement_line_id IS NULL` and `payments WHERE recovery_for_invoice_id IS NOT NULL` (QS-12/14), `prepayment_kuitansi_links` open (QS-19), `unattributed_loss_cases WHERE pending_qty_base > 0` (QS-22) |
| Audit history | `audit_events (entity_type, entity_id, occurred_at)`, `(actor_user_id, occurred_at)`, `(company_id, occurred_at)`; BRIN on `occurred_at` when the table grows |
| Correction lineage and guards added by the targeted review | `receiving_lines (corrects_receiving_line_id)`; `payments (rerecords_payment_id)`; `payment_applications (resolves_application_id)` and partial `(refund_disbursement_id) WHERE end_reason = 'SOURCE_CONTRA'`; `lot_cost_entries (purchase_line_id, cost_component)` and `(purchase_charge_id, allocation_version)`; `unattributed_loss_cases (exposure_group_case_id)` and partial `(product_id, location_id, condition) WHERE pending_qty_base > 0` (prior pending at recognition); the type-free evidence identity index (duplicate warning); the primary keys of the new guard and snapshot tables |

N+1 remains an application defect (ARCHITECTURE §9); the schema makes efficient loading possible by keeping list columns on the aggregate root (numbers, dates, state, current pointers, guard totals) so a list page never needs per-row child queries.

## 21. Search

PostgreSQL alone serves V1 search (OB §30; D-DB-11): exact B-tree lookups for barcodes, SKUs, serials, project and document numbers; `pg_trgm` GIN indexes for substring and similarity search on names and titles (Indonesian institution names are matched by fragments such as "SMAN 1" or "UGM", where trigrams outperform stemming). Full-text search is not justified in V1: no long free-text corpus is searched. No external search service is introduced. Search results for company-owned records are always filtered by company scope before ranking (P5); S0 master names are searchable by any authorized user.

## 22. File and document storage metadata

File bytes live outside PostgreSQL on private local storage (P9 decides the volume layout and backups under DIR-009); PostgreSQL is authoritative for linkage and access. `file_objects` records the generated storage key (never the uploaded name), the sanitized original filename for display, the server-detected MIME type, size, SHA-256, uploader and time, the owning company (NULL only for S0 product images and pool-scope evidence files, §4.10) and sensitivity class. Relations are explicit: evidence versions, renditions, product images, company identity assets and import sources reference `file_objects`; access is always decided through the owning record (P5). Bytes are immutable — a replacement is a new object and a new evidence version (L-41). Upload validation (size, type, dimension limits, SVG active content, EXIF/GPS stripping) is P5's control design (OB §24); cleanup of never-linked uploads and backup coherence between files and database are P9's.

## 23. Audit persistence

`audit_events` is written by the same transaction as the change it records (a rolled-back command leaves no audit row and an audit failure rolls the command back): actor, company context, action, entity type/id/public id, reason, correlation (request) id, command id, authority class (ADM, ADM+, Owner-only, system), and before/after values for updates of state and master rows. Append-only facts are their own "after"; their corrections are linked rows, so the audit event references rather than copies them. It never stores passwords, password hashes, tokens, secrets, file contents or full request payloads; S3 values appear only where the change itself is financial and remain protected by P5. No application role can update or delete audit rows (§28). The Owner review queue (QS-14) reads ADM+ and Owner-only events. Security events (logins, throttling, resets, session invalidation) are a separate table, kept apart from business audit (OB §36; BR-XC-03). `TECH-021`: two refusals also leave audit rows, written by the transaction that records their outcome — a stale count, whose STALE mark commits with its audit row (C-33), and a refusal that an approved workflow routes (PERMISSIONS_MATRIX AZ-08; QS-20), which leaves one row per affected company naming the actor, the refused command and its target, and records no other change ([CONCURRENCY_IDEMPOTENCY ST-03 and SQ-23](../../06-api-performance/CONCURRENCY_IDEMPOTENCY/s10-13-identity-failure-retry.md#12-failure-mapping)).

## 24. Migration and opening data

| Need (CAP-15; AC-17; WF-MIG-01) | Structure |
| --- | --- |
| Batch identity and source provenance | `import_batches` — kind, source file (private `file_objects`), source SHA-256 (UNIQUE per kind), cutoff date, company scope, control totals per company, dry-run report, Admin and Owner sign-off, Owner commit (L-33) |
| Row-level results and errors | `import_rows` — row number, stable `import_identity`, normalized payload, validation state, errors, created record |
| Idempotent re-import | partial UNIQUE (`import_kind`, `import_identity`) over committed rows plus UNIQUE `import_row_id` on every created fact: a rerun can never create a second lot, receivable, credit or project for the same legacy row (AX-28) |
| Opening stock and lots | OPENING movement, `inventory_lots` (OPENING) per company/product/location/condition with conversion snapshot and serials, whose source company is keyed to the import row's typed `company_id`, `lot_cost_entries` OPENING_COST (BR-INV-12) |
| Opening receivables | `invoices` of origin OPENING with the remaining value, a billing act of kind OPENING carrying the legacy billed date and due date — or no due date when `aging_basis = 'MIGRASI'` — and `invoice_balances` (BR-FIN-14) — receivables only: their sales happened before cutover, so they are excluded from live-period revenue (CALC-08) and from the project invoice cap (BR-XD-06) |
| Opening customer credit | `opening_customer_credits` with signed evidence — an application source, never Cash-In (L-50) |
| Open projects and purchases | projects and purchases of origin OPENING; MIGRATION confirmations with pins for the remaining demand; remaining purchase quantities as ordered quantities; reservations recreated afterwards through normal commands |
| Useful current-year documents | `evidence_documents` of type LEGACY_DOCUMENT linked to their projects or invoices, and `legacy_archive_items` for identified archive — never current effects (BR-XD-06) |
| Number sequences | `number_sequences.next_value` seeded after the last legacy number per company, type and period |
| Dry run | the same validation path on an isolated copy with the batch left in DRY_RUN_PASSED; P10 owns the rehearsal and cutover freeze versus delta decision (GAP-010) |

Raw legacy files are private business data: they live in private storage and never in the repository (ENGINEERING_PRINCIPLES). Wrong opening facts are corrected by contras/reversals by import identity, never by re-import (CM-33).


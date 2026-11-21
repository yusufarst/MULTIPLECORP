<?php

namespace App\Support\Performance;

/**
 * PERFORMANCE §12's index register (PF-10; PFM-03): each registered hot query with the index that serves it, a list
 * query's company key written `{company}`. TL-15 proves each one chooses its index on the synthetic tables
 * (`tests/Performance/IndexRegisterTest.php`), and the operator's post-import refresh explains each one on the imported
 * data before go-live (TASK-077; HO-36; PRQ-08), read queries only.
 */
final class HotQueries
{
    /** @var array<string, array{string, string}> use => [index, query] */
    public const REGISTER = [
        // TASK-005
        'IX-03 the purge' => ['command_log_ix03_expires_at', 'select command_id from command_log where expires_at < now() order by expires_at limit 500'],
        'IX-04 the token lookup' => ['credential_tokens_ix04_token_hash', "select id from credential_tokens where token_hash = md5('7') || md5('7')"],
        'IX-04 the open link' => ['credential_tokens_ix04_open_link', "select id from credential_tokens where user_id = (select min(id) from users) and purpose = 'RESET' and used_at is null and revoked_at is null"],
        'IX-04 the purge' => ['credential_tokens_ix04_expires_at', "select id from credential_tokens where expires_at < now() - interval '1 day'"],
        'IX-05 admission' => ['export_requests_ix05_requester_state', "select count(*) from export_requests where requested_by = (select min(id) from users) and state in ('PENDING', 'RUNNING')"],
        'IX-05 the stale claim' => ['export_requests_ix05_state_claimed', "select id from export_requests where state = 'RUNNING' and claimed_at < now() - interval '15 minutes'"],
        'IX-05 the oldest waiting request' => ['export_requests_ix05_state_recorded', "select id from export_requests where state = 'PENDING' and recorded_at < now() - interval '2 minutes' order by recorded_at limit 1"],
        'IX-09 an account\'s sessions' => ['sessions_ix09_user_id', 'select id from sessions where user_id = (select min(id) from users)'],
        'IX-13 company history' => ['audit_events_ix13_company', 'select id from audit_events where company_id = (select min(id) from companies) order by occurred_at desc, id desc limit 25'],
        'IX-13 entity history' => ['audit_events_ix13_entity', "select id from audit_events where entity_type = 'user' and entity_id = 3 order by occurred_at desc, id desc limit 25"],
        'IX-13 the Owner review' => ['audit_events_ix13_privileged', "select id from audit_events where company_id = (select min(id) from companies) and authority_class in ('ADM_PLUS', 'OWNER_ONLY') order by occurred_at desc, id desc limit 25"],
        '§20 the actor\'s history' => ['audit_events_actor', 'select id from audit_events where actor_user_id = (select min(id) from users) order by occurred_at desc limit 25'],
        'IX-14 duplicate rows' => ['import_rows_ix14_duplicate', "select id from import_rows where import_batch_id = (select min(id) from import_batches) and validation_state = 'DUPLICATE' order by row_number"],
        'IX-14 the Owner-review signals' => ['security_events_ix14_owner_review', "select id from security_events where event_kind in ('AUTHORIZATION_ANOMALY', 'EVIDENCE_DUPLICATE_UNDISCLOSED', 'IMPORT_COLLISION_UNDISCLOSED') and occurred_at > now() - interval '30 days' order by occurred_at desc"],
        'IX-15 the committing batches' => ['import_batches_ix15_commit_requested', "select id from import_batches where state = 'SIGNED_OFF' and committed_at is not null"],
        'IX-18 the sign-in lookup' => ['user_external_identities_c64_active_subject', "select user_id from user_external_identities where provider = 'google' and provider_subject = '100000000000000000007' and unlinked_at is null"],
        'IX-18 the link history' => ['user_external_identities_ix18_user', 'select id from user_external_identities where user_id = (select min(id) from users) order by recorded_at'],
        'IX-19 the second-factor floor' => ['security_events_ix19_two_factor', "select count(*) from security_events where user_id = (select min(id) from users) and event_kind in ('TWO_FACTOR_FAILED', 'TWO_FACTOR_COOLDOWN_STARTED') and occurred_at > now() - interval '15 minutes'"],
        '§20 the open cases (QS-07)' => ['correction_cases_open', "select id from correction_cases where company_id = (select min(id) from companies) and state = 'OPEN' order by id"],
        'C-47 the committed identity' => ['import_rows_c47_committed_identity', "select id from import_rows where import_kind = 'CONTOH' and import_identity = 'L-7' and validation_state = 'COMMITTED'"],
        // TASK-006
        'IX-02 the content lookup' => ['file_objects_ix02_sha256', "select id from file_objects where sha256 = md5('7') || md5('7')"],
        'IX-07 the sweep of owed renditions' => ['document_renditions_ix07_owed', "select id from document_renditions where state in ('PENDING', 'RENDERING', 'FAILED') and recorded_at < now() - interval '5 minutes' order by recorded_at limit 100"],
        'IX-07 the renditions of QS-08' => ['document_renditions_ix07_owed', "select document_version_id from document_renditions where state in ('PENDING', 'FAILED')"],
        'IX-08 a correction case\'s evidence' => ['evidence_links_ix08_correction_case', 'select id from evidence_links where correction_case_id = (select min(id) from correction_cases)'],
        'IX-11 a company\'s documents' => ['documents_c32_company_key', 'select id from documents where company_id = {company} order by id desc limit 25'],
        'IX-11 a company\'s evidence' => ['evidence_documents_c32_company_key', 'select id from evidence_documents where company_id = {company} order by id desc limit 25'],
        'IX-11 the product list' => ['products_pkey', 'select id from products order by id desc limit 25'],
        'IX-14 the products with a minimum (QS-17)' => ['products_ix14_minimum', 'select id from products where min_stock_qty_base is not null'],
        'IX-14 the pre-payment Kuitansi (QS-19)' => ['documents_ix14_prepayment_kuitansi', "select id from documents where company_id = (select min(id) from companies) and document_type_code = 'DOC-05' and mode = 'PRE_PAYMENT'"],
        'IX-14 the bank lines not fully cited (QS-12)' => ['evidence_balances_ix14_bank_line_open', 'select bank_statement_line_id from evidence_balances where bank_statement_line_id is not null and cited_amount < proven_amount'],
        'IX-15 the upload budget' => ['file_objects_ix15_uploaded_by', "select count(*) from file_objects where uploaded_by = (select min(id) from users) and recorded_at > now() - interval '1 hour'"],
        'IX-17 a product name\'s prefix' => ['products_ix17_name_prefix', "select id from products where lower(name) like 'barang contoh 12%'"],
        'IX-17 a client\'s name' => ['client_organizations_ix17_name_prefix', "select id from client_organizations where lower(name) = 'sma contoh 7'"],
        'IX-17 a unit name\'s prefix' => ['client_units_ix17_name_prefix', "select id from client_units where lower(name) like 'bagian contoh 12%'"],
        'IX-17 a supplier name\'s prefix' => ['suppliers_ix17_name_prefix', "select id from suppliers where lower(name) like 'pemasok contoh 12%'"],
        '§21 a product name\'s fragment' => ['products_name_trigram', "select id from products where name ilike '%contoh 123%'"],
        '§20 the exact barcode scan (C-01)' => ['product_barcodes_c01_active_code', "select product_id from product_barcodes where code_key = '0099000000123' and is_active"],
        '§20 the exact SKU' => ['products_sku_unique', "select id from products where lower(sku) = 'brg-123'"],
        '§20 a project\'s documents' => ['documents_project_type', 'select id from documents where project_id = 123'],
        '§20 a document\'s versions' => ['document_versions_document_version', 'select id from document_versions where document_id = (select min(id) from documents) order by version_no'],
        // TASK-007
        'IX-11 a company\'s projects by state' => ['projects_ix11_company_state', "select id from projects where company_id = {company} and state = 'ACTIVE' order by id desc limit 25"],
        // The open projects of QS-01 and QS-24 are served by the smaller open-deadline index too.
        'IX-14 a company\'s force-completed and cancelled projects (QS-16)' => ['projects_ix11_company_state', "select id from projects where company_id = (select min(id) from companies) and state in ('COMPLETED_FORCED', 'CANCELLED')"],
        'IX-14 a company\'s active projects (QS-01, QS-24)' => ['projects_ix11_open_deadline', "select id from projects where company_id = (select min(id) from companies) and state = 'ACTIVE'"],
        'IX-11 a client\'s projects' => ['projects_ix11_company_client', 'select id from projects where company_id = {company} and client_organization_id = (select min(id) from client_organizations) order by id desc limit 25'],
        'IX-11 the open projects by deadline' => ['projects_ix11_open_deadline', "select id from projects where company_id = {company} and state in ('DRAFT', 'ACTIVE') order by deadline, id limit 25"],
        'IX-16 a numbering period\'s first project' => ['projects_ix16_company_period', "select exists (select from projects where company_id = {company} and period_key = '2025')"],
        'IX-17 a project title\'s prefix' => ['projects_ix17_title_prefix', "select id from projects where company_id = {company} and lower(title) like 'pengadaan contoh 12%'"],
        '§21 a project title\'s fragment' => ['projects_title_trigram', "select id from projects where title ilike '%contoh 123%'"],
        'IX-14 the lines with an open remainder (QS-01, QS-06)' => ['project_line_balances_ix14_open', 'select project_line_id from project_line_balances where demand_qty_base > reserved_qty_base + dispatched_net_qty_base + dropship_confirmed_net_qty_base + handed_over_qty_base + cancelled_qty_base or delivered_net_qty_base < dispatched_net_qty_base + dropship_confirmed_net_qty_base'],
        'IX-14 the confirmed value not yet invoiced (QS-15)' => ['project_commercial_balances_ix14_uninvoiced', 'select project_id from project_commercial_balances where confirmed_sales_value > invoiced_sales_value'],
        'IX-11 a company\'s purchases' => ['purchases_ix11_company_business_date', 'select id from purchases where company_id = {company} order by business_date desc, id desc limit 25'],
        'IX-14 a company\'s open purchases (QS-05)' => ['purchases_ix14_company_state', "select id from purchases where company_id = (select min(id) from companies) and state = 'OPEN'"],
        '§20 the purchase lines with an open remainder (QS-05)' => ['purchase_line_balances_open_remainder', 'select purchase_line_id from purchase_line_balances where normal_received_net_qty_base + closed_qty_base + dropship_confirmed_net_qty_base < ordered_qty_base'],
        'IX-11 a company\'s expenses' => ['expenses_ix11_company_business_date', 'select id from expenses where company_id = {company} order by business_date desc, id desc limit 25'],
        'IX-08 a purchase line\'s lot cost' => ['lot_cost_entries_ix08_purchase_line', "select id from lot_cost_entries where purchase_line_id = (select min(id) from purchase_lines) and cost_component = 'GOODS'"],
        'IX-08 a consumption\'s cost' => ['cost_attributions_ix08_consumption', 'select id from cost_attributions where stock_consumption_id = 123'],
        'IX-08 a lot\'s draws' => ['cost_attributions_ix08_lot', 'select id from cost_attributions where lot_id = 123'],
        'IX-16 a share\'s lot postings' => ['lot_cost_entries_share', 'select exists (select from lot_cost_entries where purchase_charge_line_share_id = 123)'],
        'IX-16 a share\'s direct postings' => ['cost_attributions_share', 'select exists (select from cost_attributions where purchase_charge_line_share_id = 123)'],
        '§20 a bearer\'s cost by date' => ['cost_attributions_bearer_business_date', "select id from cost_attributions where bearer_company_id = {company} and business_date >= '2026-09-25'"],
        // TASK-008
        'IX-08 a lot\'s consumptions' => ['stock_consumptions_ix08_lot', 'select id from stock_consumptions where lot_id = 123'],
        'IX-08 a consumption\'s restorations' => ['stock_restorations_ix08_consumption', 'select id from stock_restorations where stock_consumption_id = 123'],
        'IX-08 a project line\'s dispatches' => ['dispatch_lines_ix08_project_line', 'select id from dispatch_lines where project_line_id = 123'],
        'IX-10 a scope\'s pending cases' => ['unattributed_loss_cases_ix10_pending', "select id from unattributed_loss_cases where product_id = (select min(id) from products) and location_id = (select min(id) from locations) and condition = 'USABLE' and pending_qty_base > 0"],
        'IX-11 a company\'s movements' => ['stock_movements_ix11_company_business_date', 'select id from stock_movements where company_id = {company} order by business_date desc, id desc limit 25'],
        'IX-11 the pool\'s movements' => ['stock_movements_ix11_business_date', 'select id from stock_movements order by business_date desc, id desc limit 25'],
        'IX-12 stock by location' => ['stock_scope_balances_ix12_location', 'select product_id from stock_scope_balances where location_id = (select min(id) from locations)'],
        'IX-14 the STALE counts (QS-18)' => ['stock_counts_ix14_stale', "select id from stock_counts where state = 'STALE'"],
        'IX-16 a scope\'s first entry' => ['stock_movement_entries_ix16_scope', "select exists (select from stock_movement_entries where product_id = 123 and location_id = (select min(id) from locations) and condition = 'USABLE')"],
        '§20 a lot\'s entries' => ['stock_movement_entries_lot', 'select id from stock_movement_entries where lot_id = 123 order by id'],
        '§20 the oldest-first picking aid' => ['stock_lot_balances_picking', 'select lot_id from stock_lot_balances where product_id = 123 and location_id = (select min(id) from locations) and qty > 0'],
        '§20 a product\'s active reservations' => ['reservations_active_product', "select id from reservations where product_id = 123 and state = 'ACTIVE'"],
        '§20 a product\'s lots by date' => ['inventory_lots_product_business_date', 'select id from inventory_lots where product_id = 123 order by business_date'],
        // TASK-009
        'IX-01 the duplicate payment (BD-02)' => ['payments_ix01_duplicate', "select id from payments where company_bank_account_id = (select min(id) from company_bank_accounts) and amount = 1500000 and business_date = '2026-10-01'"],
        'IX-06 a payment\'s applications' => ['payment_applications_ix06_payment', 'select id from payment_applications where payment_id = 123'],
        'IX-06 an opening credit\'s applications' => ['payment_applications_ix06_credit', 'select id from payment_applications where opening_customer_credit_id = 123'],
        'IX-08 a project line\'s deliveries' => ['delivery_lines_ix08_project_line', 'select id from delivery_lines where project_line_id = 123'],
        'IX-11 a company\'s payments' => ['payments_ix11_business_date', 'select id from payments where company_id = {company} order by business_date desc, id desc limit 25'],
        'IX-11 a company\'s disbursements' => ['disbursements_ix11_business_date', 'select id from disbursements where company_id = {company} order by business_date desc, id desc limit 25'],
        'IX-11 a company\'s other Cash-In' => ['other_cash_receipts_ix11_business_date', 'select id from other_cash_receipts where company_id = {company} order by business_date desc, id desc limit 25'],
        'IX-11 the receivables by due date' => ['invoice_balances_ix11_due', 'select invoice_id from invoice_balances where company_id = {company} and outstanding > 0 order by current_due_date, invoice_id limit 25'],
        'IX-14 the sources with capacity or unsettled deductions (QS-13)' => ['application_source_balances_ix14_open', 'select id from application_source_balances where company_id = (select min(id) from companies) and (capacity_amount > 0 or settled_amount < claimed_deductions_amount)'],
        'IX-14 the bank payments citing no statement line (QS-12)' => ['payments_ix14_uncited', 'select id from payments where company_id = (select min(id) from companies) and company_bank_account_id is not null and bank_statement_line_id is null'],
        'IX-14 the payments without proof' => ['payments_ix14_unevidenced', 'select id from payments where company_id = (select min(id) from companies) and unevidenced_reason is not null'],
        '§20 an invoice\'s active applications' => ['payment_applications_active_invoice', "select id from payment_applications where invoice_id = 123 and state = 'ACTIVE'"],
        '§20 a client\'s invoices' => ['invoices_client', 'select id from invoices where company_id = {company} and client_organization_id = (select min(id) from client_organizations)'],
        // TASK-061: PG-063's branch without a company, PG-064's time keys (PF-13; PF-42)
        'IX-13 the events without a company' => ['audit_events_ix13_company', 'select id from audit_events where company_id is null order by occurred_at desc, id desc limit 51'],
        'the security log' => ['security_events_time', 'select id from security_events order by occurred_at desc, id desc limit 51'],
        'the security log by kind' => ['security_events_kind_time', "select id from security_events where event_kind = 'LOGOUT' order by occurred_at desc, id desc limit 51"],
    ];
}

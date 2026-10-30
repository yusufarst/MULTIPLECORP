<?php

use App\Support\Database\DatabaseRole;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Tests\Support\RefreshDatabase;

// TL-15 (TASK-005–TASK-009): each index PERFORMANCE §12 registers on these tables and stores exists and
// is chosen by the query it serves in `EXPLAIN`, a partial index's predicate written as the same literal
// (PF-10). The tables hold synthetic rows, analysed, inside a transaction of the migration role that is
// rolled back; sequential scans are disabled so a plan shows whether an index can serve the query at all.
// The scale procedure at 1x, 10x and 100x (PERFORMANCE §12 steps 1–5) is TASK-068's (PFM-03).

uses(RefreshDatabase::class);

const INDEX_REGISTER = [
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

function seedIndexRegister(Connection $owner): void
{
    $owner->unprepared(<<<'SQL'
        insert into users (name, email, password)
            select 'Pengguna Contoh ' || g, 'pengguna' || g || '@example.com', 'tidak-dipakai' from generate_series(1, 200) g;
        create temporary table seed_users on commit drop as select array_agg(id order by id) as ids from users;
        insert into companies (code, legal_name) select 'CTH' || g, 'CV Contoh ' || g from generate_series(1, 10) g;
        create temporary table seed_companies on commit drop as select array_agg(id order by id) as ids from companies;
        insert into file_objects (storage_disk, storage_key, mime_type, size_bytes, sha256, company_id, sensitivity, purpose, uploaded_by, recorded_at)
            select 'local', 'berkas/' || g, 'application/pdf', 1024, md5(g::text) || md5(g::text), c.ids[1 + g % 10], 'S3', 'UPLOAD',
                s.ids[1 + g % 200], now() - (g || ' minutes')::interval
            from generate_series(1, 20000) g, seed_users s, seed_companies c;
        -- The import batches' sources, of no company as their purpose takes (DIR-089 (5)).
        insert into file_objects (storage_disk, storage_key, mime_type, size_bytes, sha256, company_id, sensitivity, purpose, uploaded_by, recorded_at)
            select 'local', 'impor/' || g, 'text/csv', 1024, md5('impor' || g) || md5('impor' || g), null, 'S4', 'IMPORT_SOURCE',
                s.ids[1 + g % 200], now() - (g || ' minutes')::interval
            from generate_series(1, 2000) g, seed_users s;
        create temporary table seed_files on commit drop as select array_agg(id order by id) as ids from file_objects where purpose = 'IMPORT_SOURCE';

        insert into audit_events (occurred_at, actor_user_id, company_id, action, entity_type, entity_id, authority_class)
            select now() - (g || ' minutes')::interval, s.ids[1 + g % 200], c.ids[1 + g % 10], 'contoh.tindakan', 'user', g % 500,
                case when g % 20 = 0 then 'OWNER_ONLY' when g % 20 = 1 then 'ADM_PLUS' else 'ADM' end
            from generate_series(1, 20000) g, seed_users s, seed_companies c;
        insert into security_events (occurred_at, event_kind, user_id)
            select now() - (g || ' minutes')::interval,
                case when g % 50 = 0 then 'AUTHORIZATION_ANOMALY' when g % 50 = 1 then 'TWO_FACTOR_FAILED' else 'LOGIN_SUCCEEDED' end,
                s.ids[1 + g % 200]
            from generate_series(1, 20000) g, seed_users s;
        insert into command_log (command_id, command_type, payload_fingerprint, state, completed_at, expires_at)
            select uuidv7(), 'contoh.perintah', 'v1:' || md5(g::text) || md5(g::text), 'COMMITTED', now() - (g || ' minutes')::interval, now() + ((g - 1000) || ' minutes')::interval
            from generate_series(1, 20000) g;
        insert into credential_tokens (user_id, purpose, issued_by, token_hash, issued_at, expires_at, used_at)
            select s.ids[1 + g % 200], p.purpose, case when p.purpose = 'OWNER_RECOVERY' then null else s.ids[1 + g % 200] end,
                md5(g::text) || md5(g::text), now() - (g || ' minutes')::interval, now() + ((g - 1000) || ' minutes')::interval,
                case when g >= 600 then now() end
            from generate_series(0, 9999) g, seed_users s,
                lateral (select (array['ONBOARDING', 'RESET', 'OWNER_RECOVERY'])[1 + (g / 200) % 3] as purpose) p;
        insert into export_requests (requested_by, report_code, company_ids, capability_codes, masking_context, state, claimed_at, recorded_at)
            select s.ids[1 + g % 200], 'contoh.laporan', '{1}', '{contoh.lihat}', '{}',
                (array['READY', 'READY', 'READY', 'READY', 'READY', 'READY', 'FAILED', 'WITHHELD', 'PENDING', 'RUNNING'])[1 + g % 10],
                now() - ((g % 20) || ' minutes')::interval,
                case when g % 10 = 8 then now() - ((g % 3) || ' minutes')::interval else now() - (g || ' minutes')::interval end
            from generate_series(1, 20000) g, seed_users s;
        insert into sessions (id, user_id, payload, last_activity)
            select 'sesi-' || g, s.ids[1 + g % 200], 'e30=', g from generate_series(1, 20000) g, seed_users s;
        insert into user_external_identities (user_id, provider, provider_subject, linked_at, linked_by, unlinked_at, unlinked_by, unlink_reason)
            select u, 'google', (100000000000000000000 + g)::text, now(), u,
                case when g > 200 then now() end, case when g > 200 then u end, case when g > 200 then 'SELF' end
            from generate_series(1, 2000) g, seed_users s, lateral (select s.ids[1 + g % 200] as u) a;
        insert into correction_cases (company_id, case_kind, state)
            select c.ids[1 + g % 10], 'OTHER', case when g % 25 = 0 then 'OPEN' else 'CLOSED' end from generate_series(1, 20000) g, seed_companies c;
        insert into import_batches (import_kind, source_file_id, source_sha256, cutoff_date, company_scope, state, committed_at)
            select 'CONTOH', f.ids[g], md5(g::text) || md5(g::text), '2026-09-30', '{1}',
                case when g % 100 = 0 then 'SIGNED_OFF' else 'COMMITTED' end, case when g % 200 = 0 then now() end
            from generate_series(1, 2000) g, seed_files f;
        insert into import_rows (import_batch_id, row_number, import_kind, import_identity, validation_state)
            select b.id, r, 'CONTOH', 'L-' || ((b.id - 1) * 20 + r), case when r = 20 then 'DUPLICATE' else 'COMMITTED' end
            from import_batches b, generate_series(1, 20) r;

        insert into units (code, name) select 'SAT' || g, 'Satuan ' || g from generate_series(1, 50) g;
        insert into products (kind, sku, name, base_unit_id, min_stock_qty_base)
            select 'GOODS', 'BRG-' || g, 'Barang Contoh ' || g, (select min(id) from units) + g % 50, case when g % 100 = 0 then 10 end
            from generate_series(1, 20000) g;
        insert into product_barcodes (product_id, code, kind)
            select p.id, '0099' || lpad(p.id::text, 9, '0'), 'MANUFACTURER' from products p;
        insert into client_organizations (name) select 'SMA Contoh ' || g from generate_series(1, 20000) g;
        insert into client_units (client_organization_id, name) select o.id, 'Bagian Contoh ' || o.id from client_organizations o;
        insert into suppliers (name) select 'Pemasok Contoh ' || g from generate_series(1, 20000) g;
        create temporary table seed_masters on commit drop as select
            (select array_agg(id order by id) from client_organizations) as clients,
            (select array_agg(id order by id) from suppliers) as suppliers,
            (select array_agg(id order by id) from products) as products;

        -- The first company holds one row in a hundred, as one company among many does (PF-13, PF-41).
        insert into projects (company_id, project_number, period_key, sequence_no, client_organization_id, channel, state, title, deadline)
            select c.ids[case when g % 100 = 0 then 1 else 2 + g % 9 end], 'CTH/PRJ/' || g, case when g % 2 = 0 then '2025' else '2026' end, g,
                m.clients[1 + g % 20000], 'REGULAR', (array['ACTIVE', 'ACTIVE', 'ACTIVE', 'COMPLETED_NORMAL', 'COMPLETED_NORMAL', 'COMPLETED_NORMAL', 'COMPLETED_NORMAL', 'CANCELLED', 'DRAFT', 'COMPLETED_FORCED'])[1 + g % 10],
                'Pengadaan Contoh ' || g, date '2026-11-01' + g % 60
            from generate_series(1, 20000) g, seed_companies c, seed_masters m;
        insert into project_lines (project_id, company_id, line_no, kind, product_id, quantity_scale, description, entered_qty, entered_unit_id, ratio_to_base, qty_base, fulfillment_mode)
            select p.id, p.company_id, 1, 'GOODS', pr.id, 0, 'Barang contoh', 10, pr.base_unit_id, 1, 10, 'WAREHOUSE'
            from projects p, seed_masters m, lateral (select id, base_unit_id from products where id = m.products[1 + p.sequence_no % 20000]) pr;
        -- One line in fifty is still open: half dispatched and delivered.
        insert into project_line_balances (project_line_id, fulfillment_mode, demand_qty_base, dispatched_net_qty_base, delivered_net_qty_base)
            select l.id, 'WAREHOUSE', 10, case when l.id % 50 = 0 then 5 else 10 end, case when l.id % 50 = 0 then 5 else 10 end from project_lines l;
        insert into project_commercial_balances (project_id, confirmed_sales_value, invoiced_sales_value)
            select p.id, 1500000, case when p.id % 50 = 0 then 0 else 1500000 end from projects p;
        insert into purchases (company_id, purchase_number, period_key, sequence_no, supplier_id, supplier_snapshot, business_date, state)
            select c.ids[case when g % 100 = 0 then 1 else 2 + g % 9 end], 'CTH/PO/' || g, '2026', g, m.suppliers[1 + g % 20000], '{}',
                date '2026-10-01' - g % 300, case when g % 25 = 0 then 'OPEN' else 'CLOSED' end
            from generate_series(1, 20000) g, seed_companies c, seed_masters m;
        insert into purchase_lines (purchase_id, company_id, line_no, kind, product_id, quantity_scale, delivery_mode, entered_qty, entered_unit_id, ratio_to_base, qty_base, unit_price, line_value, description)
            select p.id, p.company_id, 1, 'GOODS', pr.id, 0, 'WAREHOUSE', 10, pr.base_unit_id, 1, 10, 100000, 1000000, 'Barang contoh dibeli'
            from purchases p, seed_masters m, lateral (select id, base_unit_id from products where id = m.products[1 + p.sequence_no % 20000]) pr;
        insert into purchase_line_balances (purchase_line_id, ordered_qty_base, normal_received_net_qty_base, goods_value, posted_goods_value)
            select l.id, 10, case when l.id % 50 = 0 then 0 else 10 end, 1000000, case when l.id % 50 = 0 then 0 else 1000000 end from purchase_lines l;
        insert into locations (code, name) select 'RAK-' || g, 'Rak ' || g from generate_series(1, 50) g;
        create temporary table seed_locations on commit drop as select array_agg(id order by id) as ids from locations;
        -- A receipt, its lot and its ledger entry per purchase line, and one loss per lot.
        with m as (
            insert into stock_movements (movement_type, business_date, company_id, supplier_delivery_reference)
                select 'RECEIPT', '2026-10-01', l.company_id, 'SJ-' || l.id from purchase_lines l returning id, supplier_delivery_reference
        )
        insert into receiving_lines (stock_movement_id, company_id, purchase_line_id, product_id, quantity_scale, entered_qty, entered_unit_id, ratio_to_base, qty_base,
                usable_qty_base, damaged_qty_base, rejected_qty_base, receipt_kind, supplier_delivery_reference)
            select m.id, l.company_id, l.id, l.product_id, l.quantity_scale, 10, l.entered_unit_id, 1, 10, 10, 0, 0, 'ORIGINAL', m.supplier_delivery_reference
            from m join purchase_lines l on 'SJ-' || l.id = m.supplier_delivery_reference;
        insert into inventory_lots (lot_code, product_id, quantity_scale, source_company_id, acquisition_kind, receiving_line_id, entered_qty, entered_unit_id, ratio_to_base,
                acquired_qty_base, business_date)
            select 'LOT-' || r.id, r.product_id, r.quantity_scale, r.company_id, 'RECEIPT', r.id, 10, r.entered_unit_id, 1, 10, date '2026-10-01' - (r.id % 300)::int
            from receiving_lines r;
        insert into stock_movement_entries (stock_movement_id, movement_type, product_id, quantity_scale, location_id, condition, lot_id, qty_delta_base)
            select r.stock_movement_id, 'RECEIPT', l.product_id, l.quantity_scale, s.ids[1 + l.id % 50], 'USABLE', l.id, 10
            from inventory_lots l join receiving_lines r on r.id = l.receiving_line_id, seed_locations s;
        insert into stock_lot_balances (lot_id, location_id, condition, product_id, qty)
            select l.id, s.ids[1 + l.id % 50], 'USABLE', l.product_id, case when l.id % 50 = 0 then 5 else 0 end from inventory_lots l, seed_locations s;
        insert into lot_cost_entries (lot_id, source_company_id, entry_kind, cost_component, amount, purchase_line_id, business_date)
            select l.id, l.source_company_id, 'GOODS', 'GOODS', 1000000, r.purchase_line_id, '2026-10-01'
            from inventory_lots l join receiving_lines r on r.id = l.receiving_line_id where r.purchase_line_id % 50 <> 0;
        insert into stock_movements (movement_type, business_date, company_id) select 'LOSS', '2026-10-01', c from unnest((select ids from seed_companies)) c;
        insert into stock_consumptions (stock_movement_id, lot_id, source_company_id, product_id, quantity_scale, qty_base, kind, bearer_company_id)
            select (select m.id from stock_movements m where m.movement_type = 'LOSS' and m.company_id = l.source_company_id), l.id, l.source_company_id, l.product_id,
                l.quantity_scale, 1, 'LOSS', l.source_company_id
            from inventory_lots l;
        insert into cost_attributions (cost_class, cause, amount, stock_consumption_id, lot_id, source_company_id, bearer_company_id, business_date)
            select 'LOSS', 'INITIAL', 100000, c.id, c.lot_id, c.source_company_id, c.bearer_company_id, date '2026-10-01' - (c.id % 300)::int from stock_consumptions c;
        insert into stock_movements (movement_type, business_date, company_id) select 'RESTORATION', '2026-10-02', c from unnest((select ids from seed_companies)) c;
        insert into stock_restorations (stock_movement_id, stock_consumption_id, source_company_id, product_id, quantity_scale, entered_qty, entered_unit_id, ratio_to_base,
                qty_base, kind, returned_condition)
            select (select m.id from stock_movements m where m.movement_type = 'RESTORATION' and m.company_id = c.source_company_id), c.id, c.source_company_id,
                c.product_id, c.quantity_scale, 1, p.base_unit_id, 1, 1, 'LOSS_REVERSAL', 'USABLE'
            from stock_consumptions c join products p on p.id = c.product_id where c.id % 50 = 0;
        -- The pool: stock per scope, counts, and pending cases recognized by pool movements.
        insert into stock_scope_balances (product_id, location_id, condition, claims_qty)
            select p.id, s.ids[1 + p.id % 50], 'USABLE', 10 from products p, seed_locations s;
        insert into stock_counts (location_id, condition, state, started_at)
            select s.ids[1 + g % 50], 'USABLE', case when g % 100 = 0 then 'STALE' else 'APPLIED' end, now() - (g || ' minutes')::interval
            from generate_series(1, 2000) g, seed_locations s;
        with m as (
            insert into stock_movements (movement_type, business_date, reason)
                select 'UNATTRIBUTED_RECOGNITION', '2026-10-01', 'KASUS-' || g from generate_series(1, 2000) g returning id, reason
        )
        insert into unattributed_loss_cases (case_kind, product_id, quantity_scale, location_id, condition, recognized_qty_base, exposure_qty_base, resolved_qty_base,
                recognition_movement_id, business_date, recognized_by, reason)
            select 'LOSS', ms.products[1 + n % 20000], 0, s.ids[1 + n % 50], 'USABLE', 1, 2, case when n % 50 = 0 then 0 else 1 end, m.id, '2026-10-01', u.ids[1], 'Selisih'
            from m, lateral (select substr(m.reason, 7)::int as n) k, seed_masters ms, seed_locations s, seed_users u;
        -- The demand side: a dispatch of one line in ten, and the active reservations of one line in fifty.
        insert into stock_movements (movement_type, business_date, company_id) select 'DISPATCH', '2026-10-02', c from unnest((select ids from seed_companies)) c;
        insert into dispatch_lines (stock_movement_id, project_line_id, project_id, company_id, product_id, quantity_scale, entered_qty, entered_unit_id, ratio_to_base,
                qty_base, reserved_qty_base, unreserved_qty_base)
            select (select m.id from stock_movements m where m.movement_type = 'DISPATCH' and m.company_id = l.company_id), l.id, l.project_id, l.company_id, l.product_id,
                l.quantity_scale, 1, l.entered_unit_id, 1, 1, 0, 1
            from project_lines l where l.id % 10 = 0;
        insert into reservations (project_line_id, company_id, product_id, quantity_scale, state, remaining_qty_base)
            select l.id, l.company_id, l.product_id, l.quantity_scale, case when l.id % 50 = 0 then 'ACTIVE' else 'RELEASED' end, case when l.id % 50 = 0 then 5 else 0 end
            from project_lines l where l.id % 10 = 0;
        insert into expenses (company_id, kind, category, amount, business_date)
            select c.ids[case when g % 100 = 0 then 1 else 2 + g % 9 end], 'MANUAL', 'Operasional', 250000, date '2026-10-01' - g % 300
            from generate_series(1, 20000) g, seed_companies c;

        -- An issued invoice per project to its client, billed once and partly settled in ten (C-15; C-41).
        insert into invoices (company_id, project_id, client_organization_id, state) select p.company_id, p.id, p.client_organization_id, 'ISSUED' from projects p;
        insert into invoice_billing_acts (invoice_id, billed_at, due_date, kind, decided_by)
            select i.id, date '2026-10-01' - (i.id % 300)::int, date '2026-11-01' - (i.id % 300)::int, 'BILLING', s.ids[1 + i.id % 200] from invoices i, seed_users s;
        insert into invoice_balances (invoice_id, company_id, gross_value, sales_value, output_tax_value, applied_amount, current_billing_act_id, current_due_date)
            select i.id, i.company_id, 1110000, 1000000, 110000, case when i.id % 10 = 0 then 1110000 else 0 end, a.id, a.due_date
            from invoices i join invoice_billing_acts a on a.invoice_id = i.id;

        insert into document_types (code, name, subject_kind, is_numbered, allowed_modes)
            values ('DOC-01', 'Penawaran', 'CONTOH', true, '{STANDARD}'), ('DOC-05', 'Kuitansi', 'CONTOH', true, '{RECEIPT,PRE_PAYMENT}')
            on conflict (code) do nothing;
        -- A quotation chain per project, its company the project's (C-32), and a pre-payment Kuitansi in fifty.
        insert into documents (company_id, document_type_code, mode, project_id, invoice_id)
            select p.company_id, case when p.sequence_no % 50 = 0 then 'DOC-05' else 'DOC-01' end,
                case when p.sequence_no % 50 = 0 then 'PRE_PAYMENT' else 'STANDARD' end,
                case when p.sequence_no % 50 = 0 then null else p.id end, case when p.sequence_no % 50 = 0 then (select i.id from invoices i where i.project_id = p.id) end
            from projects p;
        insert into document_versions (document_id, company_id, document_type_code, version_no, state, business_date, period_key, sequence_no,
                formatted_number, kuitansi_amount, payload, payload_sha256)
            select d.id, d.company_id, d.document_type_code, 1, 'ISSUED', '2026-10-01', '2026', d.id, 'CTH/' || d.id,
                case when d.document_type_code = 'DOC-05' then 100000 end, '{}', md5(d.id::text) || md5(d.id::text)
            from documents d;
        insert into document_renditions (document_version_id, attempt_no, state, recorded_at)
            select v.id, 1, case when v.id % 100 = 0 then 'FAILED' when v.id % 100 = 1 then 'PENDING' else 'READY' end, now() - (v.id || ' minutes')::interval
            from document_versions v;

        insert into company_bank_accounts (company_id, bank_name, account_number, account_holder)
            select c.id, 'Bank Contoh', '9900' || c.id, 'CV Contoh' from companies c;
        insert into evidence_documents (evidence_scope, company_id, evidence_type, declared_issuer, issuer_reference, document_date, identity_key, proven_amount)
            select 'COMPANY', c.ids[case when g % 100 = 0 then 1 else 2 + g % 9 end], case when g % 2 = 0 then 'BANK_STATEMENT' else 'CLIENT_ORDER' end, 'Penerbit Contoh', 'REF-' || g,
                '2026-10-01', 'penerbit contoh|' || g, 1000000
            from generate_series(1, 20000) g, seed_companies c;
        insert into bank_statement_lines (company_id, company_bank_account_id, statement_evidence_id, statement_evidence_type, business_date, amount, line_reference)
            select e.company_id, a.id, e.id, 'BANK_STATEMENT', '2026-10-01', 1500000, 'TRX-' || e.id
            from evidence_documents e join company_bank_accounts a on a.company_id = e.company_id
            where e.evidence_type = 'BANK_STATEMENT';
        insert into evidence_balances (bank_statement_line_id, proven_amount, cited_amount)
            select l.id, l.amount, case when l.id % 50 = 0 then 0 else l.amount end from bank_statement_lines l;
        -- A payment per invoice from its client, cited by a statement line but in fifty, without proof in a hundred, applied to
        -- its invoice — reallocated in five —, each with its source guard (C-13; C-14).
        insert into payments (company_id, client_organization_id, business_date, amount, method, company_bank_account_id, bank_statement_line_id, unevidenced_reason, reference)
            select i.company_id, i.client_organization_id, date '2026-10-01' - (i.id % 300)::int, 1500000, 'TRANSFER', a.id,
                case when i.id % 50 = 0 then null else (select min(l.id) from bank_statement_lines l where l.company_id = i.company_id) end,
                case when i.id % 100 = 0 then 'Bukti transfer belum diterima' end, 'INV-' || i.id
            from invoices i join company_bank_accounts a on a.company_id = i.company_id;
        insert into application_source_balances (payment_id, company_id, client_organization_id, gross_amount, invoice_applied_amount, claimed_deductions_amount)
            select p.id, p.company_id, p.client_organization_id, p.amount, case when p.id % 10 = 0 then 0 else 500000 end, case when p.id % 20 = 0 then 15000 else 0 end
            from payments p;
        insert into payment_applications (company_id, client_organization_id, payment_id, invoice_id, target_kind, amount, state, end_reason, end_command_id, business_date)
            select p.company_id, p.client_organization_id, p.id, substr(p.reference, 5)::bigint, 'INVOICE', 500000,
                case when p.id % 5 = 0 then 'SUPERSEDED' else 'ACTIVE' end, case when p.id % 5 = 0 then 'REALLOCATED' end, case when p.id % 5 = 0 then gen_random_uuid() end, p.business_date
            from payments p;
        insert into disbursements (company_id, business_date, amount, kind, company_bank_account_id)
            select c.ids[1 + g % 10], date '2026-10-01' - g % 300, 200000, 'INTERCOMPANY_TRANSFER_OUT', (select a.id from company_bank_accounts a where a.company_id = c.ids[1 + g % 10])
            from generate_series(1, 20000) g, seed_companies c;
        insert into other_cash_receipts (company_id, business_date, amount, category, income_class, company_bank_account_id)
            select c.ids[1 + g % 10], date '2026-10-01' - g % 300, 300000, 'OTHER_INCOME', 'INCOME', (select a.id from company_bank_accounts a where a.company_id = c.ids[1 + g % 10])
            from generate_series(1, 20000) g, seed_companies c;
        -- A delivery of each dispatched line, on its project (C-30).
        insert into deliveries (company_id, project_id, business_date, recipient_name) select distinct d.company_id, d.project_id, date '2026-10-03', 'Penerima Contoh' from dispatch_lines d;
        insert into delivery_lines (delivery_id, company_id, project_line_id, product_id, quantity_scale, dispatch_line_id, entered_qty, entered_unit_id, ratio_to_base, qty_base)
            select v.id, d.company_id, d.project_line_id, d.product_id, d.quantity_scale, d.id, d.entered_qty, d.entered_unit_id, d.ratio_to_base, d.qty_base
            from dispatch_lines d join deliveries v on v.project_id = d.project_id;
        insert into evidence_links (evidence_document_id, evidence_scope, company_id, role, correction_case_id)
            select e.id, 'COMPANY', e.company_id, 'PRIMARY_PROOF', k.id
            from evidence_documents e, lateral (select id from correction_cases where company_id = e.company_id order by id limit 1 offset (e.id % 100)) k;
        analyze;
        set local enable_seqscan = off;
        SQL);
}

it('registers each index of these tables with its query, and the query uses it', function () {
    $owner = DB::connection(DatabaseRole::MIGRATION_CONNECTION);
    $owner->beginTransaction();

    try {
        seedIndexRegister($owner);
        // A list query carries its company's key as a value, as the application binds it (PF-13).
        $company = (string) $owner->scalar('select min(id) from companies');

        foreach (INDEX_REGISTER as $use => [$index, $query]) {
            $plan = (string) $owner->scalar('explain (format json) '.str_replace('{company}', $company, $query));

            expect($owner->table('pg_indexes')->where('schemaname', 'public')->where('indexname', $index)->exists())->toBeTrue("{$use}: {$index} does not exist")
                ->and(str_contains($plan, "\"Index Name\": \"{$index}\""))->toBeTrue("{$use}: the plan does not use {$index}:\n{$plan}");
        }
    } finally {
        $owner->rollBack();
    }
});

it('indexes every evidence link target for the cascade derivations (IX-08)', function () {
    $targets = [
        'project', 'commercial_confirmation', 'purchase', 'stock_movement', 'delivery', 'dropship_confirmation', 'service_handover',
        'payment', 'other_cash_receipt', 'disbursement', 'expense', 'deduction_settlement', 'receivable_writeoff', 'correction_case',
        'admin_requirement', 'unattributed_loss_case', 'import_batch',
    ];

    expect(DB::table('pg_indexes')->where('tablename', 'evidence_links')->where('indexname', 'like', 'evidence_links_ix08_%')->orderBy('indexname')->pluck('indexname')->all())
        ->toBe(collect($targets)->map(fn (string $target): string => "evidence_links_ix08_{$target}")->sort()->values()->all());
});

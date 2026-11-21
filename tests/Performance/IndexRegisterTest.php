<?php

use App\Support\Database\DatabaseRole;
use App\Support\Guards\Families;
use App\Support\Guards\Reconciler;
use App\Support\Performance\HotQueries;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Tests\Support\RefreshDatabase;

// TL-15 (TASK-005–TASK-009): each index PERFORMANCE §12 registers on these tables and stores exists and
// is chosen by the query it serves in `EXPLAIN`, a partial index's predicate written as the same literal
// (PF-10). The tables hold synthetic rows, analysed, inside a transaction of the migration role that is
// rolled back; sequential scans are disabled so a plan shows whether an index can serve the query at all.
// The scale procedure at 1x, 10x and 100x (PERFORMANCE §12 steps 1–5) is TASK-068's (PFM-03).

uses(RefreshDatabase::class);

const INDEX_REGISTER = HotQueries::REGISTER;

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
        -- GF-14: each table is analysed once filled, before a later statement joins it. Autovacuum, woken by the dead
        -- rows of an earlier rolled-back test, counts none of this transaction's rows, so a table it vacuums meanwhile
        -- is planned as empty and two such tables are joined row by row — 20,000 × 20,000 index scans, past the CI
        -- job's limit. ANALYZE counts them, and holds its lock until the rollback, so autovacuum skips the table.
        analyze correction_cases, products;

        -- The first company holds one row in a hundred, as one company among many does (PF-13, PF-41).
        insert into projects (company_id, project_number, period_key, sequence_no, client_organization_id, channel, state, title, deadline)
            select c.ids[case when g % 100 = 0 then 1 else 2 + g % 9 end], 'CTH/PRJ/' || g, case when g % 2 = 0 then '2025' else '2026' end, g,
                m.clients[1 + g % 20000], 'REGULAR', (array['ACTIVE', 'ACTIVE', 'ACTIVE', 'COMPLETED_NORMAL', 'COMPLETED_NORMAL', 'COMPLETED_NORMAL', 'COMPLETED_NORMAL', 'CANCELLED', 'DRAFT', 'COMPLETED_FORCED'])[1 + g % 10],
                'Pengadaan Contoh ' || g, date '2026-11-01' + g % 60
            from generate_series(1, 20000) g, seed_companies c, seed_masters m;
        analyze projects;
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
        analyze project_lines, purchases;
        insert into purchase_lines (purchase_id, company_id, line_no, kind, product_id, quantity_scale, delivery_mode, entered_qty, entered_unit_id, ratio_to_base, qty_base, unit_price, line_value, description)
            select p.id, p.company_id, 1, 'GOODS', pr.id, 0, 'WAREHOUSE', 10, pr.base_unit_id, 1, 10, 100000, 1000000, 'Barang contoh dibeli'
            from purchases p, seed_masters m, lateral (select id, base_unit_id from products where id = m.products[1 + p.sequence_no % 20000]) pr;
        insert into purchase_line_balances (purchase_line_id, ordered_qty_base, normal_received_net_qty_base, goods_value, posted_goods_value)
            select l.id, 10, case when l.id % 50 = 0 then 0 else 10 end, 1000000, case when l.id % 50 = 0 then 0 else 1000000 end from purchase_lines l;
        insert into locations (code, name) select 'RAK-' || g, 'Rak ' || g from generate_series(1, 50) g;
        create temporary table seed_locations on commit drop as select array_agg(id order by id) as ids from locations;
        analyze purchase_lines;
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
        analyze stock_movements, receiving_lines, inventory_lots;
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
        analyze stock_consumptions;
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
        analyze invoices, invoice_billing_acts;
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
        analyze company_bank_accounts, evidence_documents;
        insert into bank_statement_lines (company_id, company_bank_account_id, statement_evidence_id, statement_evidence_type, business_date, amount, line_reference)
            select e.company_id, a.id, e.id, 'BANK_STATEMENT', '2026-10-01', 1500000, 'TRX-' || e.id
            from evidence_documents e join company_bank_accounts a on a.company_id = e.company_id
            where e.evidence_type = 'BANK_STATEMENT';
        insert into evidence_balances (bank_statement_line_id, proven_amount, cited_amount)
            select l.id, l.amount, case when l.id % 50 = 0 then 0 else l.amount end from bank_statement_lines l;
        analyze bank_statement_lines;
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
        analyze dispatch_lines, deliveries;
        insert into delivery_lines (delivery_id, company_id, project_line_id, product_id, quantity_scale, dispatch_line_id, entered_qty, entered_unit_id, ratio_to_base, qty_base)
            select v.id, d.company_id, d.project_line_id, d.product_id, d.quantity_scale, d.id, d.entered_qty, d.entered_unit_id, d.ratio_to_base, d.qty_base
            from dispatch_lines d join deliveries v on v.project_id = d.project_id;
        insert into evidence_links (evidence_document_id, evidence_scope, company_id, role, correction_case_id)
            select e.id, 'COMPANY', e.company_id, 'PRIMARY_PROOF', k.id
            from evidence_documents e, lateral (select id from correction_cases where company_id = e.company_id order by id limit 1 offset (e.id % 100)) k;
        -- The GIN indexes' pending lists merged, as autovacuum merges them on a live table: every scan reads a pending
        -- list whole, and with this seed's rows left in it the trigram indexes cost more than a sequential scan (GF-14).
        select gin_clean_pending_list(c.oid) from pg_class c join pg_am a on a.oid = c.relam
            where a.amname = 'gin' and c.relnamespace = 'public'::regnamespace;
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

/** The relations a plan reads by a sequential scan, at any depth. */
function sequentialScans(array $node): array
{
    $found = ($node['Node Type'] ?? '') === 'Seq Scan' ? [(string) $node['Relation Name']] : [];

    foreach ($node['Plans'] ?? [] as $child) {
        $found = [...$found, ...sequentialScans($child)];
    }

    return $found;
}

it('keeps each registered query\'s own plan on PERFORMANCE §12\'s step-4 rule, its EXPLAIN (ANALYZE, BUFFERS) kept as evidence (TASK-068; PFM-03)', function () {
    // The register's synthetic tables — 20,000 rows each, at or above 1x but for the audit, security, ledger, stock and
    // import rows —, the planner free to choose; 100x is NOT TESTED for the initial release (APPR-035).
    $owner = DB::connection(DatabaseRole::MIGRATION_CONNECTION);
    $directory = storage_path('logs/verify/plans');
    is_dir($directory) || mkdir($directory, 0775, true);
    $owner->beginTransaction();

    try {
        seedIndexRegister($owner);
        $owner->statement('set local enable_seqscan = on');
        $company = (string) $owner->scalar('select min(id) from companies');
        $scans = [];

        foreach (INDEX_REGISTER as $use => [$index, $query]) {
            $json = (string) $owner->scalar('explain (analyze, buffers, format json) '.str_replace('{company}', $company, $query));
            file_put_contents($directory.'/'.preg_replace('/[^a-z0-9]+/', '-', strtolower($use)).'.json', $json);
            $plan = json_decode($json, true)[0]['Plan'] ?? [];

            foreach (sequentialScans($plan) as $relation) {
                $rows = (int) $owner->scalar('select reltuples::bigint from pg_class where relname = ?', [$relation]);

                if ($rows > 10_000) {
                    $scans[] = "{$use}: a sequential scan of {$relation} ({$rows} rows)";
                }
            }
        }

        expect($scans)->toBe([]);

        // PFM-08 (PF-30): each guard family's ReconcileGuards statement on the same synthetic data — one set-based read per
        // family after the catalogue's column types —, its plan and its guard table's size kept as evidence; a batch job's
        // whole-table read, so no scan rule applies. Its duration is staging's (TASK-079).
        $reconciler = app(Reconciler::class);
        $sizes = [];

        foreach (Families::all() as $family) {
            $owner->flushQueryLog();
            $owner->enableQueryLog();
            $reconciler->differences($owner, $family);
            $reads = array_values(array_filter($owner->getQueryLog(), fn (array $query): bool => preg_match('/^\s*(select|with)\b/i', (string) $query['query']) === 1
                && ! str_contains((string) $query['query'], 'pg_attribute')));
            $owner->disableQueryLog();

            expect($reads)->toHaveCount(1, "{$family->table()}: one set-based statement");

            file_put_contents("{$directory}/reconcile-{$family->table()}.json", (string) $owner->scalar('explain (analyze, buffers, format json) '.$reads[0]['query'], $reads[0]['bindings']));
            $sizes[] = $family->table().': '.(int) $owner->scalar('select count(*) from '.$family->table()).' guard rows';
        }

        file_put_contents("{$directory}/reconcile-sizes.txt", implode("\n", $sizes)."\n");
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

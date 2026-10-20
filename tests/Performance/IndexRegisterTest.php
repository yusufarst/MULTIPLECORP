<?php

use App\Support\Database\DatabaseRole;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Tests\Support\RefreshDatabase;

// TL-15 (TASK-005, TASK-006): each index PERFORMANCE §12 registers on these tables and stores exists and
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
        create temporary table seed_files on commit drop as select array_agg(id order by id) as ids from file_objects;

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

        insert into document_types (code, name, subject_kind, is_numbered, allowed_modes)
            values ('DOC-01', 'Penawaran', 'CONTOH', true, '{STANDARD}'), ('DOC-05', 'Kuitansi', 'CONTOH', true, '{RECEIPT,PRE_PAYMENT}');
        -- The first company holds one row in a hundred, as one company among many does (PF-13, PF-41).
        insert into documents (company_id, document_type_code, mode, project_id, invoice_id)
            select c.ids[case when g % 100 = 0 then 1 else 2 + g % 9 end],
                case when g % 50 = 0 then 'DOC-05' else 'DOC-01' end, case when g % 50 = 0 then 'PRE_PAYMENT' else 'STANDARD' end,
                case when g % 50 = 0 then null else g end, case when g % 50 = 0 then g end
            from generate_series(1, 20000) g, seed_companies c;
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

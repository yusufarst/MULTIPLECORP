<?php

use App\Support\Database\DatabaseRole;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Tests\Support\RefreshDatabase;

// TL-15 (TASK-005): each index PERFORMANCE §12 registers on these tables and stores exists and is chosen
// by the query it serves in `EXPLAIN`, a partial index's predicate written as the same literal (PF-10).
// The tables hold synthetic rows, analysed, inside a transaction of the migration role that is rolled
// back; sequential scans are disabled so a plan shows whether an index can serve the query at all. The
// scale procedure at 1x, 10x and 100x (PERFORMANCE §12 steps 1–5) is the performance Task's.

uses(RefreshDatabase::class);

const INDEX_REGISTER = [
    'IX-03 the purge' => ['command_log_ix03_expires_at', 'select command_id from command_log where expires_at < now() order by expires_at limit 500'],
    'IX-04 the token lookup' => ['credential_tokens_ix04_token_hash', "select id from credential_tokens where token_hash = md5('7') || md5('7')"],
    'IX-04 the open link' => ['credential_tokens_ix04_open_link', "select id from credential_tokens where user_id = (select min(id) from users) and purpose = 'RESET' and used_at is null and revoked_at is null"],
    'IX-04 the purge' => ['credential_tokens_ix04_expires_at', "select id from credential_tokens where expires_at < now() - interval '1 day'"],
    'IX-05 admission' => ['export_requests_ix05_requester_state', "select count(*) from export_requests where requested_by = (select min(id) from users) and state in ('PENDING', 'RUNNING')"],
    'IX-05 the stale claim' => ['export_requests_ix05_state_claimed', "select id from export_requests where state = 'RUNNING' and claimed_at < now() - interval '15 minutes'"],
    'IX-05 the oldest waiting request' => ['export_requests_ix05_state_recorded', "select id from export_requests where state = 'PENDING' and recorded_at < now() - interval '2 minutes' order by recorded_at limit 1"],
    'IX-09 an account\'s sessions' => ['sessions_ix09_user_id', 'select id from sessions where user_id = (select min(id) from users)'],
    'IX-13 company history' => ['audit_events_ix13_company', 'select id from audit_events where company_id = 3 order by occurred_at desc, id desc limit 25'],
    'IX-13 entity history' => ['audit_events_ix13_entity', "select id from audit_events where entity_type = 'user' and entity_id = 3 order by occurred_at desc, id desc limit 25"],
    'IX-13 the Owner review' => ['audit_events_ix13_privileged', "select id from audit_events where company_id = 3 and authority_class in ('ADM_PLUS', 'OWNER_ONLY') order by occurred_at desc, id desc limit 25"],
    '§20 the actor\'s history' => ['audit_events_actor', 'select id from audit_events where actor_user_id = (select min(id) from users) order by occurred_at desc limit 25'],
    'IX-14 duplicate rows' => ['import_rows_ix14_duplicate', "select id from import_rows where import_batch_id = (select min(id) from import_batches) and validation_state = 'DUPLICATE' order by row_number"],
    'IX-14 the Owner-review signals' => ['security_events_ix14_owner_review', "select id from security_events where event_kind in ('AUTHORIZATION_ANOMALY', 'EVIDENCE_DUPLICATE_UNDISCLOSED', 'IMPORT_COLLISION_UNDISCLOSED') and occurred_at > now() - interval '30 days' order by occurred_at desc"],
    'IX-15 the committing batches' => ['import_batches_ix15_commit_requested', "select id from import_batches where state = 'SIGNED_OFF' and committed_at is not null"],
    'IX-18 the sign-in lookup' => ['user_external_identities_c64_active_subject', "select user_id from user_external_identities where provider = 'google' and provider_subject = '100000000000000000007' and unlinked_at is null"],
    'IX-18 the link history' => ['user_external_identities_ix18_user', 'select id from user_external_identities where user_id = (select min(id) from users) order by recorded_at'],
    'IX-19 the second-factor floor' => ['security_events_ix19_two_factor', "select count(*) from security_events where user_id = (select min(id) from users) and event_kind in ('TWO_FACTOR_FAILED', 'TWO_FACTOR_COOLDOWN_STARTED') and occurred_at > now() - interval '15 minutes'"],
    '§20 the open cases (QS-07)' => ['correction_cases_open', "select id from correction_cases where company_id = 3 and state = 'OPEN' order by id"],
    'C-47 the committed identity' => ['import_rows_c47_committed_identity', "select id from import_rows where import_kind = 'CONTOH' and import_identity = 'L-7' and validation_state = 'COMMITTED'"],
];

function seedIndexRegister(Connection $owner): void
{
    $owner->unprepared(<<<'SQL'
        insert into users (name, email, password)
            select 'Pengguna Contoh ' || g, 'pengguna' || g || '@example.com', 'tidak-dipakai' from generate_series(1, 200) g;
        create temporary table seed_users on commit drop as select array_agg(id order by id) as ids from users;

        insert into audit_events (occurred_at, actor_user_id, company_id, action, entity_type, entity_id, authority_class)
            select now() - (g || ' minutes')::interval, s.ids[1 + g % 200], 1 + g % 10, 'contoh.tindakan', 'user', g % 500,
                case when g % 20 = 0 then 'OWNER_ONLY' when g % 20 = 1 then 'ADM_PLUS' else 'ADM' end
            from generate_series(1, 20000) g, seed_users s;
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
            select 1 + g % 10, 'OTHER', case when g % 25 = 0 then 'OPEN' else 'CLOSED' end from generate_series(1, 20000) g;
        insert into import_batches (import_kind, source_file_id, source_sha256, cutoff_date, company_scope, state, committed_at)
            select 'CONTOH', g, md5(g::text) || md5(g::text), '2026-09-30', '{1}',
                case when g % 100 = 0 then 'SIGNED_OFF' else 'COMMITTED' end, case when g % 200 = 0 then now() end
            from generate_series(1, 2000) g;
        insert into import_rows (import_batch_id, row_number, import_kind, import_identity, validation_state)
            select b.id, r, 'CONTOH', 'L-' || ((b.id - 1) * 20 + r), case when r = 20 then 'DUPLICATE' else 'COMMITTED' end
            from import_batches b, generate_series(1, 20) r;
        analyze;
        set local enable_seqscan = off;
        SQL);
}

it('registers each index of these tables with its query, and the query uses it', function () {
    $owner = DB::connection(DatabaseRole::MIGRATION_CONNECTION);
    $owner->beginTransaction();

    try {
        seedIndexRegister($owner);

        foreach (INDEX_REGISTER as $use => [$index, $query]) {
            $plan = (string) $owner->scalar("explain (format json) {$query}");

            expect($owner->table('pg_indexes')->where('schemaname', 'public')->where('indexname', $index)->exists())->toBeTrue("{$use}: {$index} does not exist")
                ->and(str_contains($plan, "\"Index Name\": \"{$index}\""))->toBeTrue("{$use}: the plan does not use {$index}:\n{$plan}");
        }
    } finally {
        $owner->rollBack();
    }
});

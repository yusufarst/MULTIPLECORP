<?php

use App\Modules\IAM\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Support\RefreshDatabase;
use Tests\Support\Refusal;

// TL-05 (TASK-005): the keys of the infrastructure stores outside the 125 business tables, violated as
// the application runtime — the credential-token store of SECURITY AU-11 (IX-04; H6-11), the export
// requests of CONCURRENCY_IDEMPOTENCY H6-04, and the framework's session and failed-job tables.

uses(RefreshDatabase::class);

function credentialLink(int $userId, string $purpose, array $overrides = []): array
{
    static $number = 0;
    $number++;

    return [
        'user_id' => $userId,
        'purpose' => $purpose,
        'issued_by' => $purpose === 'OWNER_RECOVERY' ? null : $userId,
        'token_hash' => hash('sha256', "tautan-contoh-{$number}"),
        'expires_at' => now()->addHour(),
        ...$overrides,
    ];
}

it('keeps one unused, unrevoked link per account and purpose, so a reissue revokes the old one (IX-04)', function () {
    $user = User::factory()->create();
    $first = DB::table('credential_tokens')->insertGetId(credentialLink($user->id, 'RESET'));

    Refusal::assert(Refusal::UNIQUE, 'credential_tokens_ix04_open_link', fn () => DB::table('credential_tokens')->insert(credentialLink($user->id, 'RESET')));

    DB::table('credential_tokens')->insert(credentialLink($user->id, 'ONBOARDING'));
    DB::table('credential_tokens')->where('id', $first)->update(['revoked_at' => now()]);
    DB::table('credential_tokens')->insert(credentialLink($user->id, 'RESET'));

    expect(DB::table('credential_tokens')->where('user_id', $user->id)->count())->toBe(3);
});

it('refuses a repeated token hash (IX-04)', function () {
    $user = User::factory()->create();
    $link = credentialLink($user->id, 'RESET');
    DB::table('credential_tokens')->insert($link);

    Refusal::assert(Refusal::UNIQUE, 'credential_tokens_ix04_token_hash', fn () => DB::table('credential_tokens')->insert(credentialLink(User::factory()->create()->id, 'RESET', ['token_hash' => $link['token_hash']])));
});

it('records an issuer for every link but an OWNER_RECOVERY one, and ends a link once (AU-11, AU-14)', function () {
    $user = User::factory()->create();

    Refusal::assert(Refusal::CHECK, 'credential_tokens_issuer', fn () => DB::table('credential_tokens')->insert(credentialLink($user->id, 'OWNER_RECOVERY', ['issued_by' => $user->id])));
    Refusal::assert(Refusal::CHECK, 'credential_tokens_issuer', fn () => DB::table('credential_tokens')->insert(credentialLink($user->id, 'ONBOARDING', ['issued_by' => null])));
    Refusal::assert(Refusal::CHECK, 'credential_tokens_purpose_code', fn () => DB::table('credential_tokens')->insert(credentialLink($user->id, 'LOGIN')));

    $link = DB::table('credential_tokens')->insertGetId(credentialLink($user->id, 'OWNER_RECOVERY'));
    DB::table('credential_tokens')->where('id', $link)->update(['used_at' => now()]);

    Refusal::assert(Refusal::CHECK, 'credential_tokens_used_or_revoked', fn () => DB::table('credential_tokens')->where('id', $link)->update(['revoked_at' => now()]));
});

it('refuses an export request in an unknown state (H6-04)', function () {
    $request = [
        'requested_by' => User::factory()->create()->id,
        'report_code' => 'contoh.laporan',
        'company_ids' => '{1001}',
        'capability_codes' => '{contoh.lihat}',
        'masking_context' => '{}',
    ];
    $id = DB::table('export_requests')->insertGetId($request);

    expect(DB::table('export_requests')->where('id', $id)->value('state'))->toBe('PENDING');

    Refusal::assert(Refusal::CHECK, 'export_requests_state_code', fn () => DB::table('export_requests')->insert([...$request, 'state' => 'CANCELLED']));
    Refusal::assert(Refusal::CHECK, 'export_requests_state_code', fn () => DB::table('export_requests')->where('id', $id)->update(['state' => 'DONE']));
});

it('lets the runtime write and end sessions, and record and forget failed jobs (AU-06; H9-04)', function () {
    $user = User::factory()->create();
    DB::table('sessions')->insert(['id' => 'sesi-contoh', 'user_id' => $user->id, 'payload' => 'e30=', 'last_activity' => now()->getTimestamp()]);

    expect(DB::table('sessions')->where('user_id', $user->id)->delete())->toBe(1);

    $failed = DB::table('failed_jobs')->insertGetId(['uuid' => 'gagal-contoh', 'connection' => 'redis', 'queue' => 'default', 'payload' => '{"id":1}', 'exception' => 'Contoh']);

    expect(DB::table('failed_jobs')->where('id', $failed)->delete())->toBe(1);
});

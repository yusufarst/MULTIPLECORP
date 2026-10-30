<?php

use App\Modules\OPS\Queries\ActivityHistory;
use App\Modules\ORG\Models\Company;
use Carbon\CarbonImmutable;
use Database\Factories\OPS\AuditEventFactory;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\Support\Authorization;
use Tests\Support\RefreshDatabase;

// TL-04 (TASK-061; ROUTE-063; BR-XC-01; LG-01; PT-18; criteria 2, 6, 7): PG-063 lists the business audit of every company
// and the events without a company, newest first, each with its actor or "Sistem" or the operator's recorded identity,
// its time and business date, its action, record, reason and authority class; the period, company, actor and record
// filters; an event's before and after on demand; "Muat Lagi" by a signed cursor, a tampered one answered with MSG-26.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->owner = Authorization::owner();
    $this->first = Company::factory()->create(['code' => 'AAA']);
    $this->second = Company::factory()->create(['code' => 'BBB']);
    $this->admin = Authorization::admin([$this->first->id], ['projects.view']);
});

afterEach(fn () => @unlink($this->hotFile));

function auditEvent(array $attributes): void
{
    AuditEventFactory::new()->createOne(['authority_class' => 'ADM', ...$attributes]);
}

it('lists every company\'s events and those without a company, newest first, with their actor, time, action, record, reason and authority (BR-XC-01; LG-01; PT-18; criterion 2)', function () {
    $project = (string) Str::uuid();
    auditEvent(['occurred_at' => '2026-10-28 03:00:00+00', 'company_id' => $this->first->id, 'actor_user_id' => $this->admin->id, 'action' => 'PRJ.ProjectCreated',
        'entity_type' => 'project', 'entity_id' => 7, 'entity_public_id' => $project, 'reason' => 'Pesanan sekolah']);
    auditEvent(['occurred_at' => '2026-10-28 04:00:00+00', 'company_id' => $this->second->id, 'action' => 'ORG.NumberingSeeded', 'authority_class' => 'SYSTEM',
        'metadata' => ['operator' => 'Budi (konsol)']]);
    // Recorded on 29 October WIB for a business date of 25 October: the date shows beside the time (BR-DT-05).
    auditEvent(['occurred_at' => '2026-10-28 18:00:00+00', 'action' => 'IAM.WorkspaceInstalled', 'authority_class' => 'OWNER_ONLY',
        'metadata' => ['business_date' => '2026-10-25']]);

    $this->actingAs($this->owner)->get('/aktivitas/riwayat')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('OPS/ActivityHistory', false)->has('rows', 3)->where('more', null)->where('cursor_failed', false)
        ->where('rows.0.action', 'IAM.WorkspaceInstalled')->where('rows.0.company', null)->where('rows.0.actor', null)->where('rows.0.operator', null)
        ->where('rows.0.business_date', '2026-10-25')->where('rows.0.authority', 'OWNER_ONLY')
        ->where('rows.1.action', 'ORG.NumberingSeeded')->where('rows.1.company', 'BBB')->where('rows.1.operator', 'Budi (konsol)')->where('rows.1.business_date', null)
        ->where('rows.2.actor', $this->admin->name)->where('rows.2.company', 'AAA')->where('rows.2.reason', 'Pesanan sekolah')
        ->where('rows.2.record', ['type' => 'project', 'href' => "/proyek/{$project}", 'filter' => "project:{$project}"])
        // The wide columns stay out of the list (PF-06).
        ->missing('rows.0.before')->missing('rows.0.metadata')->missing('detail'));
});

it('filters by period, company, the events without a company, actor and record (IX-13; PF-42; criterion 2)', function () {
    auditEvent(['occurred_at' => '2026-10-20 03:00:00+00', 'company_id' => $this->first->id, 'action' => 'PRJ.ProjectCreated']);
    auditEvent(['occurred_at' => '2026-10-27 03:00:00+00', 'company_id' => $this->first->id, 'actor_user_id' => $this->admin->id, 'action' => 'IAM.UserUpdated',
        'entity_type' => 'user', 'entity_id' => $this->admin->id, 'entity_public_id' => $this->admin->public_id]);
    auditEvent(['occurred_at' => '2026-10-27 04:00:00+00', 'company_id' => $this->second->id, 'action' => 'ORG.CompanyUpdated']);
    auditEvent(['occurred_at' => '2026-10-27 05:00:00+00', 'action' => 'IAM.LocaleChanged']);
    $actions = fn (string $query): array => $this->actingAs($this->owner)->get("/aktivitas/riwayat?{$query}")->viewData('page')['props']['rows'];
    $codes = fn (string $query): array => array_column($actions($query), 'action');

    expect($codes('dari=2026-10-27'))->toBe(['IAM.LocaleChanged', 'ORG.CompanyUpdated', 'IAM.UserUpdated'])
        ->and($codes('sampai=2026-10-20'))->toBe(['PRJ.ProjectCreated'])
        ->and($codes('perusahaan=AAA'))->toBe(['IAM.UserUpdated', 'PRJ.ProjectCreated'])
        ->and($codes('perusahaan=tanpa'))->toBe(['IAM.LocaleChanged'])
        ->and($codes("pelaku={$this->admin->public_id}"))->toBe(['IAM.UserUpdated'])
        ->and($codes("catatan=user:{$this->admin->public_id}"))->toBe(['IAM.UserUpdated'])
        ->and($codes("catatan=user:{$this->admin->public_id}&perusahaan=BBB"))->toBe([])
        // A filter naming nothing of the scope reads nothing (SM-01).
        ->and($codes('perusahaan=ZZZ'))->toBe([])
        ->and($codes('pelaku='.Str::uuid()))->toBe([])
        ->and($codes('catatan=invoice:'.Str::uuid()))->toBe([]);
});

it('shows an event\'s before and after on demand, nothing LG-03 keeps out (PF-06; PJ-11; LG-03; criteria 2, 4)', function () {
    auditEvent(['occurred_at' => '2026-10-27 03:00:00+00', 'company_id' => $this->first->id, 'action' => 'IAM.UserUpdated',
        'before' => ['name' => 'Rani', 'is_active' => true], 'after' => ['name' => 'Rani Uji', 'is_active' => true],
        'metadata' => ['business_date' => '2026-10-27', 'session_id' => 'tidak-boleh-tampil']]);
    $key = $this->actingAs($this->owner)->get('/aktivitas/riwayat')->viewData('page')['props']['rows'][0]['key'];
    $partial = fn (string $event) => $this->actingAs($this->owner)->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Partial-Component' => 'OPS/ActivityHistory', 'X-Inertia-Partial-Data' => 'detail'])
        ->get('/aktivitas/riwayat?peristiwa='.urlencode($event));

    $partial($key)->assertOk()
        ->assertJsonPath('props.detail.before', ['name' => 'Rani', 'is_active' => 'true'])
        ->assertJsonPath('props.detail.after', ['name' => 'Rani Uji', 'is_active' => 'true'])
        ->assertJsonPath('props.detail.metadata', ['business_date' => '2026-10-27'])
        ->assertJsonMissingPath('props.rows');

    // A forged key is no event (SM-01).
    $partial(substr($key, 0, -3).'abc')->assertOk()->assertJsonPath('props.detail', null);
});

it('continues by keyset with "Muat Lagi", without a total, and answers a tampered cursor with MSG-26 (PF-13–PF-15; criterion 6)', function () {
    $start = CarbonImmutable::parse('2026-10-01 00:00:00+00');

    foreach (range(1, ActivityHistory::PAGE + 5) as $minute) {
        auditEvent(['occurred_at' => $start->addMinutes($minute)->toIso8601String(), 'company_id' => $minute % 2 === 0 ? $this->first->id : $this->second->id, 'action' => 'PRJ.ProjectCreated', 'reason' => "n{$minute}"]);
    }

    $first = $this->actingAs($this->owner)->get('/aktivitas/riwayat')->viewData('page')['props'];
    expect($first['rows'])->toHaveCount(ActivityHistory::PAGE)
        ->and($first['rows'][0]['reason'])->toBe('n'.(ActivityHistory::PAGE + 5))
        ->and($first)->not->toHaveKey('total');

    $rest = $this->actingAs($this->owner)->get('/aktivitas/riwayat?setelah='.urlencode((string) $first['more']))->viewData('page')['props'];
    expect(array_column($rest['rows'], 'reason'))->toBe(['n5', 'n4', 'n3', 'n2', 'n1'])->and($rest['more'])->toBeNull();

    $this->actingAs($this->owner)->get('/aktivitas/riwayat?setelah='.urlencode(str_replace('.', 'x.', (string) $first['more'])))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('cursor_failed', true)->has('rows', 0)->where('more', null));
});

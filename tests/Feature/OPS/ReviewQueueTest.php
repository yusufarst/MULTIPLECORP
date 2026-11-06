<?php

use App\Modules\OPS\Queries\ReviewQueue;
use Illuminate\Support\Facades\DB;
use Tests\Support\Authorization;
use Tests\Support\RefreshDatabase;

// TL-04 (TASK-058; IA §9.2; PF-15): PG-008's *Aktivitas* continued by "Muat Lagi" over events recorded within one
// second, their microseconds kept by the cursor, so no event is skipped (Codex P2).

uses(RefreshDatabase::class);

beforeEach(function () {
    Authorization::catalogue();
});

it('continues the activity past events of the same second without skipping any (IA §9.2; Codex P2)', function () {
    $owner = Authorization::owner()->id;
    $second = now()->subHour()->startOfSecond();
    $rows = [];

    foreach (range(1, ReviewQueue::PAGE + 3) as $index) {
        $rows[] = ['occurred_at' => $second->copy()->addMicroseconds($index * 1000)->format('Y-m-d H:i:s.uP'), 'actor_user_id' => $owner, 'company_id' => null,
            'action' => 'IAM.GrantsChanged', 'authority_class' => 'OWNER_ONLY', 'recorded_at' => now()];
    }

    DB::table('audit_events')->insert($rows);
    $query = ['tab' => 'aktivitas', 'jangka' => '7_hari', 'dari' => null, 'sampai' => null, 'perusahaan' => null, 'pelaku' => null, 'setelah' => null];
    $first = app(ReviewQueue::class)->screen($owner, $query);
    $next = app(ReviewQueue::class)->screen($owner, [...$query, 'setelah' => $first['more']]);

    expect($first['items'])->toHaveCount(ReviewQueue::PAGE)->and($first['more'])->not->toBeNull()
        ->and($next['items'])->toHaveCount(3)->and($next['more'])->toBeNull()
        ->and(array_intersect(array_column($first['items'], 'key'), array_column($next['items'], 'key')))->toBe([]);
});

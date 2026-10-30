<?php

namespace Tests\Support\Fixtures;

use App\Modules\OPS\Queries\LogCursor;
use App\Modules\ORG\Models\Company;
use Carbon\CarbonImmutable;
use Database\Factories\OPS\AuditEventFactory;
use Database\Factories\OPS\SecurityEventFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * TASK-061's browser fixture (TL-09–TL-12; ROUTE-063; ROUTE-064; INT-020): for the run's company, a page and more of
 * business audit events with a before and after, one event without a company, and the security events a notice opens
 * — an authorization anomaly on the signed-in Owner's account and a failed sign-in with its keyed hashes. Local only;
 * synthetic values.
 */
final class LogsFixture
{
    public static function create(Request $request): JsonResponse
    {
        $company = Company::query()->where('public_id', (string) $request->input('company'))->firstOrFail();
        $actor = $request->user()?->getAuthIdentifier();
        $actor = is_int($actor) ? $actor : null;
        $tag = Str::upper(Str::random(6));
        $start = CarbonImmutable::now()->subHours(2);

        foreach (range(1, 55) as $minute) {
            AuditEventFactory::new()->createOne([
                'occurred_at' => $start->addMinutes($minute), 'company_id' => $company->id, 'actor_user_id' => $actor, 'action' => 'PRJ.ProjectCreated',
                'authority_class' => 'ADM', 'reason' => "Uji log {$tag} {$minute}", 'before' => ['judul' => "Lama {$minute}"], 'after' => ['judul' => "Baru {$minute}"],
            ]);
        }

        AuditEventFactory::new()->createOne(['occurred_at' => $start->addMinutes(56), 'action' => 'IAM.LocaleChanged', 'authority_class' => 'ADM', 'actor_user_id' => $actor, 'reason' => "Tanpa perusahaan {$tag}"]);
        SecurityEventFactory::new()->createOne(['occurred_at' => $start->addMinutes(57), 'event_kind' => 'LOGIN_FAILED', 'reason_code' => 'CREDENTIALS', 'route_name' => 'login',
            'identifier_hash' => hash('sha256', "id-{$tag}"), 'ip_hash' => hash('sha256', "ip-{$tag}"), 'user_agent_summary' => 'Chrome · Windows']);
        $anomaly = SecurityEventFactory::new()->createOne(['occurred_at' => $start->addMinutes(58), 'event_kind' => 'AUTHORIZATION_ANOMALY', 'user_id' => $actor, 'reason_code' => "UJI-{$tag}"]);

        return response()->json([
            'code' => $company->code,
            'tag' => $tag,
            // The key a security notice carries to PG-064 (INT-020).
            'notice' => LogCursor::of((string) DB::table('security_events')->where('id', $anomaly->getKey())->value('occurred_at'), (int) $anomaly->getKey()),
        ]);
    }
}

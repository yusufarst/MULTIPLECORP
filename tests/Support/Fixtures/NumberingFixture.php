<?php

namespace Tests\Support\Fixtures;

use App\Modules\ORG\Models\Company;
use App\Modules\ORG\Services\NumberingGoLive;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

/**
 * PG-061's fixture for the local stack's browser suite (TASK-021): a synthetic company for one run, and go-live — whose
 * store is TASK-077's (DIR-098 reading 2) — recorded for the requests while a run has armed it, so the start and the
 * raise run against the real routes of PG-061. A run names its own go-live in `X-Fixture-Go-Live` (a day, or `none`), so a
 * spec clearing it in another worker never reaches an issue under way here; the armed value stands for a request without
 * the header. Local only (routes/fixtures.php); no real company or date.
 */
final class NumberingFixture
{
    private const KEY = 'fixture-numbering-go-live';

    /** Called as the fixture routes load: binds the armed go-live for the requests of an armed run. */
    public static function apply(): void
    {
        if (app()->runningInConsole()) {
            return;
        }

        $own = request()->header('X-Fixture-Go-Live');

        try {
            $day = is_string($own) ? ($own === 'none' ? null : $own) : Cache::get(self::KEY);
        } catch (Throwable) {
            return;
        }

        if (is_string($day) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) === 1) {
            app()->instance(NumberingGoLive::class, new class($day) implements NumberingGoLive
            {
                public function __construct(private readonly string $recorded) {}

                public function day(): ?string
                {
                    return $this->recorded;
                }
            });
        }
    }

    /** A company for the run (`company`), and go-live recorded (`go_live`, a day) or cleared (`go_live` empty). */
    public static function arm(Request $request): JsonResponse
    {
        $goLive = $request->input('go_live');

        if (is_string($goLive) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $goLive) === 1) {
            Cache::put(self::KEY, $goLive, 1800);
        } elseif ($request->has('go_live')) {
            Cache::forget(self::KEY);
        }

        if (! $request->boolean('company')) {
            return response()->json(['armed' => true]);
        }

        $code = 'U'.Str::upper(Str::random(9));
        $company = Company::factory()->create(['code' => $code, 'legal_name' => "CV Uji Penomoran {$code}"]);

        return response()->json(['company' => $company->public_id, 'code' => $code]);
    }
}

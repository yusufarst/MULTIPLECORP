<?php

namespace App\Modules\Reporting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProjectedResource;
use App\Modules\Reporting\Queries\Beranda;
use App\Modules\Reporting\Queries\HomeGroups;
use App\Modules\Reporting\Queries\WorkQueue;
use App\Modules\Reporting\Signals\Context;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Throwable;

/**
 * The home pages (IA §9): ROUTE-006 PG-006 *Beranda*, the Owner's landing page, and ROUTE-007 PG-007 *Pekerjaan*,
 * every active account's. Each answers its frame in the first response and its groups as deferred props, each group a
 * background request of its own with its own error (PT-19; RB-05) — the page's own GET partially reloaded, as every
 * page's deferred groups are (ROUTE_CONTRACTS §2; ROUTE-285) —, and each refreshes by "Muat Ulang" or by a poll naming
 * its groups' props no faster than once a minute (PF-26). A group the viewer has no read class for is not defined, so
 * a partial reload naming it obtains nothing (WS-07; PJ-13). Neither page runs a command.
 */
final class HomeController extends Controller
{
    public function __construct(
        private readonly HomeGroups $groups,
        private readonly Beranda $beranda,
        private readonly WorkQueue $work,
    ) {}

    /** ROUTE-006 (`review.view`, OWNER_ONLY: an Admin's request is the not-found page, OD-14). */
    public function beranda(Request $request): HttpResponse
    {
        $context = $this->context($request);

        return Inertia::render('Reporting/Beranda', [
            'frame' => fn (): array => $this->beranda->frame($context(), $this->groups->scope($context())),
            'attention' => Inertia::defer(self::group(fn (): array => $this->beranda->attention($context())), 'attention'),
            'operations' => Inertia::defer(self::group(fn (): array => $this->beranda->operations($context())), 'operations'),
            'billing' => Inertia::defer(self::group(fn (): array => $this->beranda->billing($context())), 'billing'),
            'completion' => Inertia::defer(self::group(fn (): array => $this->beranda->completion($context())), 'completion'),
            'figures' => Inertia::defer(self::group(fn (): array => $this->beranda->figures($context())), 'figures'),
        ])->toResponse($request);
    }

    /** ROUTE-007 (every active account, S0; `DIR-081` OB-03). */
    public function work(Request $request): HttpResponse
    {
        $context = $this->context($request);
        $frame = $this->work->frame($context(), $this->groups->granted($context()));
        $props = ['frame' => fn (): array => [...$frame, 'views' => array_values($this->groups->scope($context()))]];
        $reads = [
            'goods' => fn (): array => $this->work->goods($context()),
            'documents' => fn (): array => $this->work->documents($context()),
            'billing' => fn (): array => $this->work->billing($context()),
            'completion' => fn (): array => $this->work->completion($context()),
        ];

        foreach ($reads as $group => $read) {
            if ($frame['groups'][$group] && ! $frame['ungranted']) {
                $props[$group] = Inertia::defer(self::group($read), $group);
            }
        }

        return Inertia::render('Reporting/Pekerjaan', $props)->toResponse($request);
    }

    /**
     * The request's context, read once and only when a prop asks for it (QB-10).
     *
     * @return Closure(): Context
     */
    private function context(Request $request): Closure
    {
        $context = null;
        $view = self::query($request, 'perusahaan');

        return function () use (&$context, $request, $view): Context {
            return $context ??= $this->groups->context(ProjectedResource::viewer($request), $view);
        };
    }

    /**
     * A deferred group whose read fails answers its own error, the other groups staying (PT-19; NAV-006; NAV-007).
     *
     * @template T
     *
     * @param  Closure(): T  $read
     * @return Closure(): (T|array{failed: true})
     */
    private static function group(Closure $read): Closure
    {
        return function () use ($read): mixed {
            try {
                return $read();
            } catch (Throwable $error) {
                Log::error('A deferred group failed.', ['exception' => $error::class]);

                return ['failed' => true];
            }
        };
    }

    private static function query(Request $request, string $key): ?string
    {
        $value = $request->query($key);

        return is_string($value) && $value !== '' && strlen($value) <= 2000 ? $value : null;
    }
}

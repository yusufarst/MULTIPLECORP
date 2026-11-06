<?php

namespace App\Modules\OPS\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\OPS\Queries\ReviewQueue;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * ROUTE-008 `OPS ShowReviewQueue` — PG-008 *Tinjauan* (`review.view`, OWNER_ONLY: an Admin's request is the not-found
 * page, identical to a nonexistent address — OD-14; UXS-25): the tab's list in the response, the open conditions as a
 * deferred group of their own. Its window, tab and filters are address keys (NV-05). It runs no command and marks
 * nothing read (IA §9.2).
 */
final class ReviewController extends Controller
{
    public function __construct(private readonly ReviewQueue $queue) {}

    public function show(Request $request): HttpResponse
    {
        $actor = self::actor($request);
        $screen = $this->queue->screen($actor, [
            'tab' => self::query($request, 'tab'),
            'jangka' => self::query($request, 'jangka'),
            'dari' => self::query($request, 'dari'),
            'sampai' => self::query($request, 'sampai'),
            'perusahaan' => self::query($request, 'perusahaan'),
            'pelaku' => self::query($request, 'pelaku'),
            'setelah' => self::query($request, 'setelah'),
        ]);

        return Inertia::render('OPS/Tinjauan', [
            ...$screen,
            'conditions' => Inertia::defer(fn (): array => $this->queue->conditions($actor), 'conditions'),
        ])->toResponse($request);
    }

    private static function query(Request $request, string $key): ?string
    {
        $value = $request->query($key);

        return is_string($value) && $value !== '' && strlen($value) <= 200 ? $value : null;
    }

    private static function actor(Request $request): int
    {
        $id = $request->user()?->getAuthIdentifier();

        return is_int($id) ? $id : throw new NotFoundHttpException;
    }
}

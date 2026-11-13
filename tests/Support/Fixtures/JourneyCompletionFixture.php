<?php

namespace Tests\Support\Fixtures;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * TASK-063's cancellation world for CJ-17 (TL-09; J-PRJ-04; AX-27; routes/fixtures.php): over TASK-038's ReservationFixture — the run's
 * company, a project of one warehouse goods line confirmed through ROUTE-158's own action, an opening lot of `stock` usable units and
 * `reserved` of them reserved for the line through ROUTE-184's action —, the product's public identifier, so the journey reads PG-019's
 * four quantities before and after the cancellation. Every step of the journey is made on its pages. Local only; synthetic names.
 */
final class JourneyCompletionFixture
{
    public static function cancellation(Request $request): JsonResponse
    {
        $reserved = ReservationFixture::create($request);

        if ($reserved->getStatusCode() !== 200) {
            return $reserved;
        }

        /** @var array{code: string, project: string, number: string, goods: string} $run */
        $run = $reserved->getData(true);
        // The goods are named after the run's company code ("Buku Uji {code}"): the newest of that name is this run's.
        $product = DB::table('products')->where('name', $run['goods'])->orderByDesc('id')->value('public_id');

        if (! is_string($product)) {
            return response()->json(['error' => 'The reserved product was not found.'], 500);
        }

        return response()->json([...$run, 'product' => $product]);
    }
}

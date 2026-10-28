<?php

namespace App\Modules\Reporting\Http\Controllers;

use App\Http\Resources\ProjectedResource;
use App\Modules\Reporting\Http\Requests\SearchRequest;
use App\Modules\Reporting\Queries\Search;
use Illuminate\Http\JsonResponse;

/**
 * ROUTE-281 `Reporting Search` (INT-005, INT-281): read-only, no `command_id`, under WS-12's search limiter. The
 * answer is JSON for the field that asked, never an Inertia visit, so the term enters no page state, address or
 * history (DP-02; H7-10). Its route reads the session and never writes it back (BackgroundRequest::READS_SESSION_ONLY),
 * so a type-ahead's request beside a command's — before its redirect, during it or before its page — neither ages nor
 * overwrites the command's answer, which would otherwise read as unknown (IP-01; IP-02).
 */
final class SearchController
{
    public function __invoke(SearchRequest $request, Search $search): JsonResponse
    {
        $viewer = ProjectedResource::viewer($request);

        return response()->json($search->run($viewer, $request->term(), $request->types()));
    }
}

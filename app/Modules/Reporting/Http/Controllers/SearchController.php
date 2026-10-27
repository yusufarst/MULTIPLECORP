<?php

namespace App\Modules\Reporting\Http\Controllers;

use App\Http\Resources\ProjectedResource;
use App\Modules\Reporting\Http\Requests\SearchRequest;
use App\Modules\Reporting\Queries\Search;
use Illuminate\Http\JsonResponse;

/**
 * ROUTE-281 `Reporting Search` (INT-005, INT-281): read-only, no `command_id`, under WS-12's search limiter. The
 * answer is JSON for the field that asked, never an Inertia visit, so the term enters no page state, address or
 * history (DP-02; H7-10). A type-ahead's request may land between a command's redirect and the page it opens: it keeps
 * the session's flash data for that page, so a command's answer is never consumed by a search and read as unknown
 * (IP-01; IP-02).
 */
final class SearchController
{
    public function __invoke(SearchRequest $request, Search $search): JsonResponse
    {
        $viewer = ProjectedResource::viewer($request);
        $request->session()->reflash();

        return response()->json($search->run($viewer, $request->term(), $request->types()));
    }
}

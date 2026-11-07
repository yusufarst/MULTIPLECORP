<?php

namespace App\Modules\Reporting\Http\Controllers;

use App\Http\Resources\ProjectedResource;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * ROUTE-009 `Reporting ShowSearch`, PG-009 *Cari* (NAV-009; SCR-08; TASK-059): every active account (S0; `DIR-081`
 * OB-03). The page carries no term and no result: the field asks ROUTE-281 in a request body and ROUTE-282 for a
 * scanned code, so the term never enters the address, a page prop, the title or the history (IA §11; DP-02; H7-10).
 * It says only whether the account may scan — ROUTE-282's `stock.view` over the pool (S1) —; each result keeps its
 * own view capability in the search itself (PJ-10).
 */
final class SearchPageController
{
    public function __invoke(Request $request): Response
    {
        $viewer = ProjectedResource::viewer($request);

        return Inertia::render('Reporting/Cari', ['can' => ['scan' => $viewer->active && $viewer->allows('stock.view')]]);
    }
}

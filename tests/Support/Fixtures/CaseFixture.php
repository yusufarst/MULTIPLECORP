<?php

namespace Tests\Support\Fixtures;

use App\Modules\OPS\Models\CorrectionCase;
use App\Modules\ORG\Models\Company;
use Database\Factories\PRJ\ProjectFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * TASK-031's browser fixture (TL-09–TL-12; ROUTE-040, ROUTE-271): for the run's company, two correction cases as the
 * correcting commands of later Tasks will open them — a sales return without a residual, which may close, and a payment
 * correction still carrying an unresolved deduction, which may not. Local only; no real company or case.
 */
final class CaseFixture
{
    public static function create(Request $request): JsonResponse
    {
        $company = Company::query()->where('public_id', (string) $request->input('company'))->firstOrFail();
        $project = ProjectFactory::new()->createOne(['company_id' => $company->id]);
        $closable = CorrectionCase::factory()->create(['company_id' => $company->id, 'project_id' => $project->id, 'case_kind' => 'SALES_RETURN', 'reason' => 'Klien mengembalikan 2 unit rusak.']);
        $owing = CorrectionCase::factory()->create(['company_id' => $company->id, 'project_id' => $project->id, 'case_kind' => 'PAYMENT_CORRECTION', 'reason' => 'Pembayaran dicatat ganda.', 'residual_settlement_amount' => '150000.00']);

        return response()->json(['closable' => $closable->public_id, 'owing' => $owing->public_id, 'project' => $project->project_number]);
    }
}

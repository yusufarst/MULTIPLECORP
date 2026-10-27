<?php

namespace Tests\Support\Fixtures;

use App\Modules\DOC\Models\EvidenceDocument;
use App\Modules\ORG\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * PG-038's browser fixture (TASK-030; TL-09–TL-12): a synthetic company for the run and, with `duplicate`, a client
 * order of it registered without a reference, so the journey's reference-less registration of the same issuer, type
 * and date meets IP-05's warning (CJ-20). Local only (routes/fixtures.php); no real company, document or file.
 */
final class EvidenceFixture
{
    public const ISSUER = 'SMA Negeri Uji Ganda';

    public static function create(Request $request): JsonResponse
    {
        $code = 'E'.Str::upper(Str::random(6));
        $company = Company::factory()->create(['code' => $code, 'legal_name' => "CV Uji Bukti {$code}"]);

        if ($request->boolean('duplicate')) {
            EvidenceDocument::factory()->create([
                'company_id' => $company->id, 'evidence_type' => 'CLIENT_ORDER', 'declared_issuer' => self::ISSUER, 'issuer_reference' => null,
                'document_date' => '2026-10-01', 'identity_key' => 'sma negeri uji ganda|CLIENT_ORDER||2026-10-01',
            ]);
        }

        return response()->json(['company' => $company->public_id, 'code' => $code, 'issuer' => self::ISSUER]);
    }
}

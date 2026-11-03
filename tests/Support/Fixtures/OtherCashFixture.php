<?php

namespace Tests\Support\Fixtures;

use App\Modules\DOC\Models\EvidenceDocument;
use App\Modules\ORG\Models\Company;
use App\Modules\PUR\Models\Purchase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * TL-09–TL-12 (TASK-054; J-FIN-07; CJ-14): over InvoiceFixture's company with its account and confirmed project, an open purchase to pay, a transfer proof to cite and a supplier bill of
 * Rp100,000 with its guard; the signed-in Owner records on PG-049. Synthetic data only.
 */
final class OtherCashFixture
{
    public static function create(Request $request): JsonResponse
    {
        $company = Company::query()->where('public_id', (string) $request->input('company'))->firstOrFail();

        if (DB::table('company_bank_accounts')->where('company_id', $company->id)->doesntExist()) {
            return response()->json(['error' => 'no account'], 422);
        }

        // Their own identities: the factory's sequence starts again in every request (C-49).
        $tag = Str::upper(Str::random(6));
        $purchase = Purchase::factory()->create(['company_id' => $company->id, 'purchase_number' => "PB-{$tag}"]);
        $proof = EvidenceDocument::factory()->create(['company_id' => $company->id, 'evidence_scope' => 'COMPANY', 'evidence_type' => 'TRANSFER_PROOF', 'declared_issuer' => "Transfer keluar {$tag}", 'issuer_reference' => "TK-{$tag}",
            'identity_key' => 'transfer keluar '.strtolower($tag).'|TRANSFER_PROOF|tk-'.strtolower($tag).'|2026-10-01']);
        $bill = EvidenceDocument::factory()->create(['company_id' => $company->id, 'evidence_scope' => 'COMPANY', 'evidence_type' => 'SUPPLIER_BILL', 'declared_issuer' => "Pemasok sewa {$tag}", 'issuer_reference' => "PS-{$tag}", 'proven_amount' => '100000',
            'identity_key' => 'pemasok sewa '.strtolower($tag).'|SUPPLIER_BILL|ps-'.strtolower($tag).'|2026-10-01']);
        DB::table('evidence_balances')->insert(['evidence_document_id' => $bill->id, 'proven_amount' => '100000', 'recorded_at' => now()]);

        return response()->json(['purchase' => $purchase->public_id, 'purchase_number' => $purchase->purchase_number, 'transfer_proof' => $proof->public_id, 'bill' => $bill->public_id]);
    }
}

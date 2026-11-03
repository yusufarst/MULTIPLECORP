<?php

namespace Tests\Support\Fixtures;

use App\Modules\DOC\Models\EvidenceDocument;
use App\Modules\IAM\Models\User;
use App\Modules\ORG\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\PaymentWorld;

/**
 * TL-09–TL-12 (TASK-053; J-FIN-06; CJ-04's credit and refund): over InvoiceFixture's issued invoice of Rp1,000,000 and its company's account, a Rp1,200,000 payment applying 1,000,000 with
 * its proof — Rp200,000 of credit for the client and company, counting for the invoice's project — and a transfer proof to choose; the signed-in account refunds and holds credit.
 * Synthetic data only.
 */
final class CreditFixture
{
    public const CAPABILITIES = ['finance.view', 'payment.apply', 'refund.record'];

    public static function create(Request $request): JsonResponse
    {
        $user = $request->user();
        $company = Company::query()->where('public_id', (string) $request->input('company'))->firstOrFail();
        $invoice = DB::table('invoices')->where('public_id', (string) $request->input('invoice'))->where('company_id', $company->id)->first(['id', 'client_organization_id']);
        $account = DB::table('company_bank_accounts')->where('company_id', $company->id)->orderBy('id')->value('id');

        if (! $user instanceof User || $invoice === null || ! is_numeric($account)) {
            return response()->json(['error' => 'unknown'], 422);
        }

        foreach (self::CAPABILITIES as $capability) {
            Authorization::grant($user, $capability);
        }

        $outcome = PaymentWorld::record($user->id, $company->id, (int) $invoice->client_organization_id, '1200000', ['accountId' => (int) $account, 'applications' => [(int) $invoice->id => '1000000']]);

        if ($outcome->kind !== 'COMMITTED' || ! is_int($outcome->resultId)) {
            return response()->json(['error' => "paid {$outcome->kind} {$outcome->code}"], 500);
        }

        // Their own identities: the factory's sequence starts again in every request (C-49).
        $tag = Str::upper(Str::random(6));
        $paymentProof = EvidenceDocument::factory()->create(['company_id' => $company->id, 'evidence_scope' => 'COMPANY', 'evidence_type' => 'PAYMENT_PROOF', 'declared_issuer' => "Bukti bayar {$tag}", 'issuer_reference' => "BB-{$tag}",
            'identity_key' => 'bukti bayar '.strtolower($tag).'|PAYMENT_PROOF|bb-'.strtolower($tag).'|2026-10-01']);
        DB::table('evidence_links')->insert(['evidence_document_id' => $paymentProof->id, 'evidence_scope' => 'COMPANY', 'company_id' => $company->id, 'role' => 'PRIMARY_PROOF', 'payment_id' => $outcome->resultId,
            'command_id' => (string) Str::uuid(), 'command_line_no' => 1, 'recorded_at' => now(), 'recorded_by' => $user->id]);
        $transfer = EvidenceDocument::factory()->create(['company_id' => $company->id, 'evidence_scope' => 'COMPANY', 'evidence_type' => 'TRANSFER_PROOF', 'declared_issuer' => "Transfer balik {$tag}", 'issuer_reference' => "TB-{$tag}",
            'identity_key' => 'transfer balik '.strtolower($tag).'|TRANSFER_PROOF|tb-'.strtolower($tag).'|2026-10-01']);

        return response()->json(['payment' => DB::table('payments')->where('id', $outcome->resultId)->value('public_id'), 'transfer_proof' => $transfer->public_id]);
    }
}

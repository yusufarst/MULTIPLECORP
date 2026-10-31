<?php

namespace App\Modules\FIN\Queries;

use App\Modules\FIN\Models\Payment;
use App\Modules\FIN\Services\SettlementMoves;
use App\Modules\IAM\Data\Authority;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Support\RowHandle;
use App\Support\Search\Row;
use Illuminate\Support\Facades\DB;

/**
 * What the payment commands' submitted identifiers name (ROUTE-251–ROUTE-254; ROUTE-261; ROUTE-262), read for the controller, which holds no database work (ARCHITECTURE §2; SM-03): a company in the viewer's scope, a
 * client, the company's accounts and lines, the invoices the viewer may see, a payment, its applications, settlements and claimed deductions. Each within its scope, an unknown one answering nothing (AZ-11; SM-01).
 */
final class PaymentInputs
{
    private const UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    public function __construct(private readonly EffectiveCapabilities $capabilities) {}

    public function viewer(int $viewerId): Authority
    {
        return $this->capabilities->for($viewerId);
    }

    /** A company by public id in which the viewer holds the capability (CS-03). */
    public function companyId(int $viewerId, string $publicId, string $capability): ?int
    {
        $id = preg_match(self::UUID, $publicId) === 1 ? DB::table('companies')->where('public_id', strtolower($publicId))->value('id') : null;

        return is_numeric($id) && $this->viewer($viewerId)->allows($capability, (int) $id) ? (int) $id : null;
    }

    public function clientId(string $publicId): ?int
    {
        $id = preg_match(self::UUID, $publicId) === 1 ? DB::table('client_organizations')->where('public_id', strtolower($publicId))->value('id') : null;

        return is_numeric($id) ? (int) $id : null;
    }

    /** One of the company's accounts by the handle the page shows. */
    public function accountId(int $companyId, string $handle): ?int
    {
        return self::byHandle('company_bank_accounts', DB::table('company_bank_accounts')->where('company_id', $companyId)->pluck('id')->all(), $handle);
    }

    /** One of the company's statement lines by its handle. */
    public function lineId(int $companyId, string $handle): ?int
    {
        return self::byHandle('bank_statement_lines', DB::table('bank_statement_lines')->where('company_id', $companyId)->where('state', 'ACTIVE')->orderByDesc('id')->limit(2000)->pluck('id')->all(), $handle);
    }

    /**
     * A line of any company in which the viewer may correct: its id and company.
     *
     * @return array{id: int, company: int}|null
     */
    public function correctableLine(int $viewerId, string $handle): ?array
    {
        $viewer = $this->viewer($viewerId);
        $companies = [];

        foreach ($viewer->scope->apply(DB::table('companies'), 'id')->pluck('id') as $company) {
            if (is_numeric($company) && $viewer->allows('cash.correct', (int) $company)) {
                $companies[] = (int) $company;
            }
        }

        foreach (DB::table('bank_statement_lines')->whereIn('company_id', $companies)->where('state', 'ACTIVE')->orderByDesc('id')->limit(5000)->get(['id', 'company_id']) as $row) {
            if (hash_equals(RowHandle::of('bank_statement_lines', (int) Row::text($row, 'id')), $handle)) {
                return ['id' => (int) Row::text($row, 'id'), 'company' => (int) Row::text($row, 'company_id')];
            }
        }

        return null;
    }

    /**
     * The invoices named, any the viewer may not see answering nothing (SM-01).
     *
     * @param  list<string>  $publicIds
     * @return array<string, int> public id => id
     */
    public function invoiceIds(int $viewerId, array $publicIds): array
    {
        $viewer = $this->viewer($viewerId);
        $found = [];

        foreach (DB::table('invoices')->whereIn('public_id', array_map(strtolower(...), $publicIds))->get(['id', 'public_id', 'company_id']) as $row) {
            if ($viewer->allows('finance.view', (int) Row::text($row, 'company_id'))) {
                $found[Row::text($row, 'public_id')] = (int) Row::text($row, 'id');
            }
        }

        return $found;
    }

    public function payment(int $viewerId, string $publicId): ?Payment
    {
        $payment = preg_match(self::UUID, $publicId) === 1 ? Payment::query()->where('public_id', strtolower($publicId))->first() : null;

        return $payment !== null && $this->viewer($viewerId)->allows('finance.view', $payment->company_id) ? $payment : null;
    }

    /** A payment of the company by public id. */
    public function paymentOf(int $companyId, string $publicId): ?int
    {
        $id = preg_match(self::UUID, $publicId) === 1 ? DB::table('payments')->where('company_id', $companyId)->where('public_id', strtolower($publicId))->value('id') : null;

        return is_numeric($id) ? (int) $id : null;
    }

    /**
     * ROUTE-252's source: a payment by public id or an opening credit by its handle, in the viewer's `finance.view`.
     *
     * @return array{payment: ?int, credit: ?int, company: int, client: int, name: string}|null
     */
    public function source(int $viewerId, string $id): ?array
    {
        $payment = $this->payment($viewerId, $id);

        if ($payment !== null) {
            return ['payment' => $payment->id, 'credit' => null, 'company' => $payment->company_id, 'client' => $payment->client_organization_id, 'name' => $payment->public_id];
        }

        if (! RowHandle::valid($id)) {
            return null;
        }

        $viewer = $this->viewer($viewerId);

        foreach ($viewer->scope->apply(DB::table('opening_customer_credits'), 'company_id')->orderBy('id')->limit(5000)->get(['id', 'company_id', 'client_organization_id']) as $row) {
            if (hash_equals(RowHandle::of('opening_customer_credits', (int) Row::text($row, 'id')), $id) && $viewer->allows('finance.view', (int) Row::text($row, 'company_id'))) {
                return ['payment' => null, 'credit' => (int) Row::text($row, 'id'), 'company' => (int) Row::text($row, 'company_id'), 'client' => (int) Row::text($row, 'client_organization_id'), 'name' => $id];
            }
        }

        return null;
    }

    /** An application of the payment by its handle, in any state: a stale page's ended one is the command's CONFLICT. */
    public function applicationId(int $paymentId, string $handle): ?int
    {
        return self::byHandle('payment_applications', DB::table('payment_applications')->where('payment_id', $paymentId)->pluck('id')->all(), $handle);
    }

    /** An application ended by its source's contra whose refund still lacks backing, of the client and company (L-46). */
    public function endedApplicationId(int $companyId, int $clientId, string $handle): ?int
    {
        return self::byHandle('payment_applications', DB::table('payment_applications')->where('company_id', $companyId)->where('client_organization_id', $clientId)->where('end_reason', 'SOURCE_CONTRA')->whereNotNull('refund_disbursement_id')->pluck('id')->all(), $handle);
    }

    /** A settlement of the payment by its handle. */
    public function settlementId(int $paymentId, string $handle): ?int
    {
        return self::byHandle('deduction_settlements', array_keys(SettlementMoves::anchored($paymentId)), $handle);
    }

    /** A claimed deduction of the payment by its handle. */
    public function deductionId(int $paymentId, string $handle): ?int
    {
        return self::byHandle('payment_claimed_deductions', DB::table('payment_claimed_deductions')->where('payment_id', $paymentId)->pluck('id')->all(), $handle);
    }

    public function taxTypeId(string $code): ?int
    {
        $id = DB::table('tax_types')->where('code', $code)->value('id');

        return is_numeric($id) ? (int) $id : null;
    }

    /** Another company's transfer-out by public id. */
    public function transferId(string $publicId): ?int
    {
        $id = preg_match(self::UUID, $publicId) === 1 ? DB::table('disbursements')->where('public_id', strtolower($publicId))->where('kind', 'INTERCOMPANY_TRANSFER_OUT')->value('id') : null;

        return is_numeric($id) ? (int) $id : null;
    }

    public function companyPublicId(int $companyId): string
    {
        $publicId = DB::table('companies')->where('id', $companyId)->value('public_id');

        return is_string($publicId) ? $publicId : '';
    }

    public function paymentPublicId(int $paymentId): string
    {
        $publicId = DB::table('payments')->where('id', $paymentId)->value('public_id');

        return is_string($publicId) ? $publicId : '';
    }

    /**
     * @param  array<mixed>  $ids
     */
    private static function byHandle(string $table, array $ids, string $handle): ?int
    {
        foreach ($ids as $id) {
            if (is_numeric($id) && hash_equals(RowHandle::of($table, (int) $id), $handle)) {
                return (int) $id;
            }
        }

        return null;
    }
}

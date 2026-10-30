<?php

namespace App\Modules\FIN\Queries;

use App\Modules\FIN\Services\InvoiceCorrections;
use App\Support\RowHandle;
use App\Support\Search\Row;
use Illuminate\Support\Facades\DB;

/**
 * What the invoice commands' submitted identifiers name (ROUTE-241; ROUTE-245; ROUTE-249; ROUTE-250), read for the controller, which holds no database work
 * (ARCHITECTURE §2; SM-03): the handles of a project's lines, the rows a typed basis may cite, the current reductions, a Kuitansi's document, the payments
 * of a company. Each within its company or project, an unknown one answering nothing (AZ-11).
 */
final class InvoiceInputs
{
    public function publicId(int $invoiceId): string
    {
        $publicId = DB::table('invoices')->where('id', $invoiceId)->value('public_id');

        return is_string($publicId) ? $publicId : '';
    }

    public function projectPublicId(?int $projectId): string
    {
        $publicId = $projectId === null ? null : DB::table('projects')->where('id', $projectId)->value('public_id');

        return is_string($publicId) ? $publicId : '';
    }

    /** @return array<string, int> The project's lines by the handle the composer shows. */
    public function lineHandles(int $projectId): array
    {
        $handles = [];

        foreach (self::ids(DB::table('project_lines')->where('project_id', $projectId)->pluck('id')->all()) as $id) {
            $handles[RowHandle::of('project_lines', $id)] = $id;
        }

        return $handles;
    }

    /** @return list<int> */
    public function caseIds(int $companyId, ?int $projectId): array
    {
        return self::ids(DB::table('correction_cases')->where('company_id', $companyId)->where('project_id', $projectId ?? 0)->pluck('id')->all());
    }

    /** @return list<int> */
    public function confirmationIds(?int $projectId): array
    {
        return self::ids(DB::table('commercial_confirmations')->where('project_id', $projectId ?? 0)->pluck('id')->all());
    }

    /** @return list<int> */
    public function cancellationIds(?int $projectId): array
    {
        return self::ids(DB::table('line_scope_cancellations as c')->join('project_lines as l', 'l.id', '=', 'c.project_line_id')->where('l.project_id', $projectId ?? 0)->pluck('c.id')->all());
    }

    /** @return list<int> */
    public function transitionIds(?int $projectId): array
    {
        return self::ids(DB::table('project_state_transitions')->where('project_id', $projectId ?? 0)->pluck('id')->all());
    }

    /**
     * What a correction must dispose now, by id (ST-07).
     *
     * @return array{applications: list<int>, settlements: list<int>, writeoffs: list<int>}
     */
    public function reductionIds(int $invoiceId): array
    {
        $reductions = InvoiceCorrections::reductions($invoiceId);

        return ['applications' => array_keys($reductions['applications']), 'settlements' => array_keys($reductions['settlements']), 'writeoffs' => array_keys($reductions['writeoffs'])];
    }

    /**
     * A pre-payment Kuitansi's document by public id, of the given invoice when one is named.
     *
     * @return array{id: int, public_id: string, invoice_id: int}|null
     */
    public function kuitansi(string $publicId, ?int $invoiceId): ?array
    {
        if (preg_match('/^[0-9a-f-]{36}$/i', $publicId) !== 1) {
            return null;
        }

        $row = DB::table('documents')->where('public_id', strtolower($publicId))->where('document_type_code', 'DOC-05')->where('mode', 'PRE_PAYMENT')
            ->when($invoiceId !== null, fn ($query) => $query->where('invoice_id', $invoiceId))->first(['id', 'public_id', 'invoice_id']);

        return $row === null ? null : ['id' => (int) Row::text($row, 'id'), 'public_id' => Row::text($row, 'public_id'), 'invoice_id' => (int) Row::text($row, 'invoice_id')];
    }

    /**
     * @param  list<string>  $publicIds
     * @return list<int> The named payments of the company.
     */
    public function paymentIds(int $companyId, array $publicIds): array
    {
        return self::ids(DB::table('payments')->where('company_id', $companyId)->whereIn('public_id', $publicIds)->pluck('id')->all());
    }

    /** @return array{public_id: string, legal_name: string}|null The company a C-10 refusal names. */
    public function company(int $companyId): ?array
    {
        $row = DB::table('companies')->where('id', $companyId)->first(['public_id', 'legal_name']);

        return $row === null ? null : ['public_id' => Row::text($row, 'public_id'), 'legal_name' => Row::text($row, 'legal_name')];
    }

    /**
     * @param  array<mixed>  $values
     * @return list<int>
     */
    private static function ids(array $values): array
    {
        $ids = [];

        foreach ($values as $value) {
            if (is_numeric($value)) {
                $ids[] = (int) $value;
            }
        }

        return $ids;
    }
}

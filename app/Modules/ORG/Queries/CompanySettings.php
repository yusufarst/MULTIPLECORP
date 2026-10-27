<?php

namespace App\Modules\ORG\Queries;

use App\Modules\ORG\Data\RowHandle;
use App\Modules\ORG\Enums\SequenceType;
use App\Modules\ORG\Models\Company;
use App\Modules\ORG\Services\NumberingState;
use App\Support\BusinessDate;
use Illuminate\Support\Facades\DB;

/**
 * PG-059 *Perusahaan*'s reads (ROUTE-059; SCR-54; WF-MD-01; ADMIN_FLOW §12.4; UXI-31), the Owner's (`companies.manage`;
 * CS-01): every company, active or deactivated, and one company's legal data, identity assets, bank accounts and
 * state, with the onboarding readiness list derived each time from what exists — *Lengkap* or *Belum* per line with
 * its command, never stored. A bank account is named by its handle; the page receives no internal identifier.
 */
final class CompanySettings
{
    private const UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    public function __construct(private readonly NumberingState $numbering) {}

    /** @return list<array{id: string, code: string, name: string, active: bool}> */
    public function directory(): array
    {
        return array_values(Company::query()->orderBy('code')->get(['public_id', 'code', 'legal_name', 'is_active'])
            ->map(fn (Company $company): array => ['id' => $company->public_id, 'code' => $company->code, 'name' => $company->legal_name, 'active' => $company->is_active])
            ->all());
    }

    public function find(string $publicId): ?Company
    {
        return preg_match(self::UUID, $publicId) === 1 ? Company::query()->where('public_id', strtolower($publicId))->first() : null;
    }

    /**
     * One company's detail and readiness.
     *
     * @return array<string, mixed>
     */
    public function detail(Company $company): array
    {
        $files = DB::table('file_objects')->whereIn('id', array_filter([$company->logo_file_id, $company->stamp_file_id, $company->signature_file_id]))
            ->pluck('public_id', 'id')->all();
        $file = fn (?int $id): ?string => $id !== null && is_string($files[$id] ?? null) ? $files[$id] : null;
        $accounts = DB::table('company_bank_accounts')->where('company_id', $company->id)->orderByDesc('is_active')->orderBy('id')
            ->get(['id', 'bank_name', 'account_number', 'account_holder', 'branch', 'is_active', 'deactivated_at', 'lock_version'])
            ->map(fn (object $account): array => [
                'handle' => RowHandle::of('company_bank_accounts', self::int($account->id)),
                'bank_name' => self::text($account->bank_name),
                'account_number' => self::text($account->account_number),
                'account_holder' => self::text($account->account_holder),
                'branch' => is_string($account->branch) ? $account->branch : null,
                'active' => $account->is_active === true,
                'deactivated_at' => is_string($account->deactivated_at) ? $account->deactivated_at : null,
                'lock_version' => self::int($account->lock_version),
            ])->values()->all();

        return [
            'id' => $company->public_id,
            'code' => $company->code,
            'legal_name' => $company->legal_name,
            'legal_form' => $company->legal_form,
            'npwp' => $company->npwp,
            'address_lines' => $company->address_lines,
            'city' => $company->city,
            'phone' => $company->phone,
            'email' => $company->email,
            'director_name' => $company->director_name,
            'active' => $company->is_active,
            'deactivated_at' => $company->deactivated_at?->toIso8601String(),
            'deactivated_reason' => $company->deactivated_reason,
            'lock_version' => $company->lock_version,
            'assets' => ['logo' => $file($company->logo_file_id), 'stamp' => $file($company->stamp_file_id), 'signature' => $file($company->signature_file_id)],
            'accounts' => $accounts,
            'readiness' => $this->readiness($company, $accounts),
        ];
    }

    /** A created company's public identifier, for the page its answer opens. */
    public function publicIdOf(int $companyId): ?string
    {
        $id = DB::table('companies')->where('id', $companyId)->value('public_id');

        return is_string($id) ? $id : null;
    }

    /** A bank account of the company by its handle. */
    public function accountOf(Company $company, string $handle): ?int
    {
        if (! RowHandle::valid($handle)) {
            return null;
        }

        foreach (DB::table('company_bank_accounts')->where('company_id', $company->id)->pluck('id') as $id) {
            if (hash_equals(RowHandle::of('company_bank_accounts', self::int($id)), $handle)) {
                return self::int($id);
            }
        }

        return null;
    }

    /**
     * ADMIN_FLOW §12.4's lines, each derived now from the state that exists.
     *
     * @param  array<int, array{active: bool}>  $accounts
     * @return list<array{key: string, complete: bool, type?: string}>
     */
    private function readiness(Company $company, array $accounts): array
    {
        $schemes = array_map(fn (mixed $type): string => is_string($type) ? $type : '', DB::table('numbering_schemes')->where('company_id', $company->id)->distinct()->pluck('sequence_type')->all());
        $types = SequenceType::cases();
        $lines = [
            ['key' => 'company_data', 'complete' => $company->legal_form !== null && $company->npwp !== null && $company->address_lines !== null && $company->city !== null
                && ($company->phone !== null || $company->email !== null)],
            ['key' => 'identity_assets', 'complete' => $company->logo_file_id !== null && $company->stamp_file_id !== null && $company->signature_file_id !== null && $company->director_name !== null],
            ['key' => 'bank_account', 'complete' => array_filter($accounts, fn (array $account): bool => $account['active']) !== []],
            ['key' => 'tax', 'complete' => DB::table('company_tax_profiles')->where('company_id', $company->id)->whereRaw('effective @> ?::date', [BusinessDate::today()])->exists()],
            ['key' => 'number_format', 'complete' => array_diff(array_map(fn (SequenceType $type): string => $type->value, $types), $schemes) === []],
        ];

        foreach ($types as $type) {
            $lines[] = ['key' => 'numbering', 'type' => $type->value, 'complete' => $this->numbering->boundary($company->id, $type) !== null];
        }

        $lines[] = ['key' => 'admin_access', 'complete' => DB::table('user_company_grants')->where('company_id', $company->id)->whereNull('revoked_at')->exists()];

        return $lines;
    }

    private static function int(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}

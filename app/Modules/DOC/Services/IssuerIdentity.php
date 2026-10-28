<?php

namespace App\Modules\DOC\Services;

use App\Support\Command\Refused;
use App\Support\RowHandle;
use Illuminate\Support\Facades\DB;

/**
 * The issuing company's identity as its documents print it (BR-DOC-01; BR-SN-01; DATABASE §10, §14), read in system
 * context for the issue's snapshot — the renderer alone uses the stamp and the signature (PJ-19; DP-17) — and for a
 * draft's preview, whose props then keep only what PJ-19 lets the viewer see. Identity assets are named by their
 * immutable file and checksum; a bank account by its handle, never its internal identifier.
 */
final class IssuerIdentity
{
    /**
     * @return array{name: string, npwp: ?string, address: list<string>, city: ?string, phone: ?string, email: ?string, director: ?string, active: bool, assets: array<string, array{file: int, public_id: string, sha256: string, mime_type: string}|null>}
     */
    public function of(int $companyId): array
    {
        $company = DB::table('companies')->where('id', $companyId)->first();
        $text = fn (string $column): ?string => is_object($company) && is_string($company->{$column} ?? null) && trim($company->{$column}) !== '' ? trim($company->{$column}) : null;
        $assets = [];

        foreach (['logo', 'stamp', 'signature'] as $asset) {
            $id = is_object($company) ? ($company->{"{$asset}_file_id"} ?? null) : null;
            $file = is_numeric($id) ? DB::table('file_objects')->where('id', (int) $id)->first(['id', 'public_id', 'sha256', 'mime_type']) : null;
            $assets[$asset] = is_object($file) ? ['file' => self::int($file->id ?? null), 'public_id' => self::text($file->public_id ?? null), 'sha256' => self::text($file->sha256 ?? null), 'mime_type' => self::text($file->mime_type ?? null)] : null;
        }

        return [
            'name' => $text('legal_name') ?? '',
            'npwp' => $text('npwp'),
            'address' => array_values(array_filter(array_map('trim', explode("\n", $text('address_lines') ?? '')), fn (string $line): bool => $line !== '')),
            'city' => $text('city'),
            'phone' => $text('phone'),
            'email' => $text('email'),
            'director' => $text('director_name'),
            'active' => is_object($company) && ($company->is_active ?? false) === true,
            'assets' => $assets,
        ];
    }

    /**
     * The company's active bank accounts, each by its handle (C-32: only the issuing company's own).
     *
     * @return list<array{handle: string, id: int, bank_name: string, account_number: string, account_holder: string, branch: ?string}>
     */
    public function bankAccounts(int $companyId): array
    {
        $accounts = [];

        foreach (DB::table('company_bank_accounts')->where('company_id', $companyId)->where('is_active', true)->orderBy('id')->get(['id', 'bank_name', 'account_number', 'account_holder', 'branch']) as $account) {
            $accounts[] = ['handle' => RowHandle::of('company_bank_accounts', self::int($account->id ?? null)), 'id' => self::int($account->id ?? null), ...self::account($account)];
        }

        return $accounts;
    }

    /**
     * The bank destination an entered handle names: one of the issuing company's active accounts (C-32), for a layout
     * that states one; none for any other layout or an empty choice.
     *
     * @throws Refused C-32 when the handle names no active account of the company
     */
    public function accountByHandle(int $companyId, string $typeCode, ?string $handle): ?int
    {
        if (! in_array($typeCode, DocumentPaper::BANK_LAYOUTS, true) || $handle === null || $handle === '') {
            return null;
        }

        foreach ($this->bankAccounts($companyId) as $account) {
            if (hash_equals($account['handle'], $handle)) {
                return $account['id'];
            }
        }

        throw Refused::rejected('C-32', ['field' => 'bank_account']);
    }

    /**
     * One of the company's bank accounts — active or not, as an issued version keeps it — by its id.
     *
     * @return array{bank_name: string, account_number: string, account_holder: string, branch: ?string}|null
     */
    public function bankAccount(int $companyId, ?int $accountId): ?array
    {
        $account = $accountId === null ? null : DB::table('company_bank_accounts')->where('company_id', $companyId)->where('id', $accountId)
            ->first(['bank_name', 'account_number', 'account_holder', 'branch']);

        return is_object($account) ? self::account($account) : null;
    }

    /** @return array{bank_name: string, account_number: string, account_holder: string, branch: ?string} */
    private static function account(object $account): array
    {
        return [
            'bank_name' => self::text($account->bank_name ?? null),
            'account_number' => self::text($account->account_number ?? null),
            'account_holder' => self::text($account->account_holder ?? null),
            'branch' => is_string($account->branch ?? null) && $account->branch !== '' ? $account->branch : null,
        ];
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

<?php

namespace App\Modules\ORG\Queries;

use App\Modules\IAM\Data\Authority;
use App\Modules\ORG\Models\Company;
use Illuminate\Support\Facades\DB;

/**
 * The company as every other page labels and prints it, under PJ-19's projection (PERMISSIONS_MATRIX §7.2 rows 8 and
 * 9; O-01; REF-006; REF-032; AC-13): its name, code and state with `projects.view` in its scope; its NPWP, address,
 * contact, director and logo with `projects.view`, `finance.view` or `cost.view` in scope; its active bank accounts
 * with `finance.view`; the raw stamp and signature files to the Owner only, the renderer reading them in system
 * context (DP-17). A company outside the viewer's scope does not exist for it (AZ-11).
 */
final class CompanyIdentity
{
    /** @return array<string, mixed>|null */
    public function of(Authority $viewer, int $companyId): ?array
    {
        $views = array_values(array_filter(['projects.view', 'finance.view', 'cost.view'], fn (string $capability): bool => $viewer->allows($capability, $companyId)));

        if ($views === [] && ! $viewer->ownerRole) {
            return null;
        }

        $company = Company::query()->find($companyId);

        if ($company === null) {
            return null;
        }

        $files = DB::table('file_objects')->whereIn('id', array_filter([$company->logo_file_id, $company->stamp_file_id, $company->signature_file_id]))->pluck('public_id', 'id')->all();
        $file = fn (?int $id): ?string => $id !== null && is_string($files[$id] ?? null) ? $files[$id] : null;
        $identity = [
            'id' => $company->public_id,
            'code' => $company->code,
            'name' => $company->legal_name,
            'active' => $company->is_active,
            'legal_form' => $company->legal_form,
            'npwp' => $company->npwp,
            'address_lines' => $company->address_lines,
            'city' => $company->city,
            'phone' => $company->phone,
            'email' => $company->email,
            'director_name' => $company->director_name,
            'logo' => $file($company->logo_file_id),
        ];

        if ($viewer->ownerRole || $viewer->allows('finance.view', $companyId)) {
            $identity['bank_accounts'] = DB::table('company_bank_accounts')->where('company_id', $companyId)->where('is_active', true)->orderBy('id')
                ->get(['bank_name', 'account_number', 'account_holder', 'branch'])->map(fn (object $row): array => (array) $row)->values()->all();
        }

        if ($viewer->ownerRole) {
            $identity['stamp'] = $file($company->stamp_file_id);
            $identity['signature'] = $file($company->signature_file_id);
        }

        return $identity;
    }
}

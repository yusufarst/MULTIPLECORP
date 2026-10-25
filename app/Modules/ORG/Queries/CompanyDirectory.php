<?php

namespace App\Modules\ORG\Queries;

use App\Modules\IAM\Data\CompanyScope;
use App\Modules\ORG\Models\Company;

/**
 * The companies of an account's scope as the shell and the working filter name them (INFORMATION_ARCHITECTURE
 * §7; PT-04; WS-07): each company's public identifier, code and legal name — an S0 identity —, in code order;
 * the Owner's scope holds every company, active or deactivated (CS-01; RG-03). One query, whatever the count.
 */
final class CompanyDirectory
{
    /**
     * The internal identifiers of the companies a working filter names by public identifier, in scope only, so a
     * filter naming another company learns nothing of it (DP-02; CS-07).
     *
     * @param  list<string>  $publicIds
     * @return list<int>
     */
    public function idsOf(CompanyScope $scope, array $publicIds): array
    {
        $publicIds = array_values(array_filter($publicIds, fn (string $id): bool => preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id) === 1));

        if ($publicIds === []) {
            return [];
        }

        $ids = $scope->apply(Company::query(), 'companies.id')->whereIn('companies.public_id', array_map('strtolower', $publicIds))->pluck('companies.id')->all();

        return array_values(array_map(fn (mixed $id): int => is_numeric($id) ? (int) $id : 0, $ids));
    }

    /** @return list<array{id: string, code: string, name: string}> */
    public function inScope(CompanyScope $scope): array
    {
        return array_values($scope->apply(Company::query(), 'companies.id')
            ->orderBy('companies.code')
            ->get(['companies.public_id', 'companies.code', 'companies.legal_name'])
            ->map(fn (Company $company): array => ['id' => $company->public_id, 'code' => $company->code, 'name' => $company->legal_name])
            ->all());
    }
}

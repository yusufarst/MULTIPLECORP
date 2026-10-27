<?php

namespace App\Modules\PRJ\Queries;

use App\Modules\IAM\Data\Authority;
use App\Support\Search\Row;
use Illuminate\Support\Facades\DB;

/**
 * A client organization's related projects on PG-054 (PERMISSIONS_MATRIX PJ-10; UXN-23; D-IA-14), PRJ's query class:
 * to a holder of `projects.view` only, the company scope inside the one statement (CS-07), so a project of a company
 * outside it is never read, listed or hinted at. A list, never a count or a total: the latest projects first, and
 * whether more in scope follow.
 */
final class ClientProjects
{
    public const CAPABILITY = 'projects.view';

    public const ROWS = 20;

    /** @return array{rows: list<array{id: string, number: string, title: string, state: string, company: string}>, more: bool} */
    public function of(Authority $viewer, int $organizationId): array
    {
        if (! $viewer->allows(self::CAPABILITY) || $viewer->scope->isEmpty()) {
            return ['rows' => [], 'more' => false];
        }

        $query = DB::table('projects')
            ->join('companies', 'companies.id', '=', 'projects.company_id')
            ->where('projects.client_organization_id', $organizationId);
        $viewer->scope->apply($query, 'projects.company_id');
        $found = $query->orderByDesc('projects.id')->limit(self::ROWS + 1)
            ->get(['projects.public_id', 'projects.project_number', 'projects.title', 'projects.state', 'companies.code'])->all();

        return [
            'rows' => array_map(fn (object $row): array => [
                'id' => Row::text($row, 'public_id'),
                'number' => Row::text($row, 'project_number'),
                'title' => Row::text($row, 'title'),
                'state' => Row::text($row, 'state'),
                'company' => Row::text($row, 'code'),
            ], array_slice($found, 0, self::ROWS)),
            'more' => count($found) > self::ROWS,
        ];
    }
}

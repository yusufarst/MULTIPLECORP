<?php

namespace App\Modules\PRJ\Queries;

use App\Modules\IAM\Data\Authority;
use App\Modules\PRJ\Models\Project;
use App\Support\Search\NameSearch;
use App\Support\Search\Row;
use App\Support\Search\Term;
use Illuminate\Database\Eloquent\Builder;

/**
 * The projects' part of search and lookup (PERFORMANCE PF-19–PF-22; IP-17): to a holder of `projects.view` only,
 * the company scope inside the same statement, so no rank, bound, cut or empty answer sees a project of a
 * company outside it (CS-07; DP-06). A project number is looked up exactly, unique only within its company, so
 * several rows may share it; a title by PF-21. Each row names its company by code (PT-04).
 */
final class ProjectLookup
{
    public const CAPABILITY = 'projects.view';

    /** @return list<array{type: string, id: string, code: string, label: string, company: string}> */
    public function exact(Authority $viewer, Term $term, int $rows): array
    {
        if (! $viewer->allows(self::CAPABILITY)) {
            return [];
        }

        $rows = $this->base($viewer)
            ->where('projects.project_number', mb_strtoupper($term->compact()))
            ->orderBy('companies.code')
            ->limit($rows + 1)
            ->get()
            ->all();

        return array_map(self::row(...), array_values($rows));
    }

    /** @return array{rows: list<array{type: string, id: string, code: string, label: string, company: string}>, cut: bool} */
    public function titles(Authority $viewer, Term $term, int $rows): array
    {
        if (! $viewer->allows(self::CAPABILITY)) {
            return ['rows' => [], 'cut' => false];
        }

        $found = NameSearch::run($this->base($viewer), 'projects.title', $term, $rows);

        return ['rows' => array_map(self::row(...), $found['rows']), 'cut' => $found['cut']];
    }

    /** @return Builder<Project> */
    private function base(Authority $viewer): Builder
    {
        $query = Project::query()
            ->join('companies', 'companies.id', '=', 'projects.company_id')
            ->select(['projects.id as search_key', 'projects.public_id', 'projects.project_number', 'projects.title', 'companies.code as company_code']);

        return $viewer->scope->apply($query, 'projects.company_id');
    }

    /** @return array{type: string, id: string, code: string, label: string, company: string} */
    private static function row(object $project): array
    {
        return [
            'type' => 'project',
            'id' => Row::text($project, 'public_id'),
            'code' => Row::text($project, 'project_number'),
            'label' => Row::text($project, 'title'),
            'company' => Row::text($project, 'company_code'),
        ];
    }
}

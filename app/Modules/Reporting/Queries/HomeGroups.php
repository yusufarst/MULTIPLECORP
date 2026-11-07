<?php

namespace App\Modules\Reporting\Queries;

use App\Modules\IAM\Data\Authority;
use App\Modules\ORG\Queries\CompanyDirectory;
use App\Modules\Reporting\Signals\Context;
use App\Modules\Reporting\Signals\Signal;
use App\Support\BusinessDate;
use App\Support\Search\Row;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\DB;

/**
 * The reads both home pages share (PG-006, PG-007; IA §9): the context of one request — the viewer and the companies
 * of the viewer's scope inside the page's view, read once (QB-10) — and a group's signals, each read by its own
 * statement and present only when the viewer reads it (PJ-13; PT-19: a signal without its read class does not exist
 * on the page).
 */
final class HomeGroups
{
    public function __construct(
        private readonly CompanyDirectory $directory,
        private readonly Container $app,
    ) {}

    /**
     * The request's context: the view's companies — the address key `perusahaan`, the public ids asked for, intersected
     * with the viewer's scope so it narrows and never widens; none asked for is the whole scope (IA §7; CS-03).
     */
    public function context(Authority $viewer, ?string $view): Context
    {
        $asked = $view === null ? [] : array_slice(explode(',', $view), 0, 50);
        $scope = $asked === [] ? $viewer->scope : $viewer->scope->narrowedTo($this->directory->idsOf($viewer->scope, $asked));
        $companies = [];

        if ($viewer->active) {
            foreach ($scope->apply(DB::table('companies'), 'id')->orderBy('code')->get(['id', 'code', 'public_id']) as $row) {
                $companies[(int) Row::text($row, 'id')] = ['code' => Row::text($row, 'code'), 'id' => Row::text($row, 'public_id')];
            }
        }

        return new Context($viewer, $companies, BusinessDate::today());
    }

    /**
     * The companies of the viewer's whole scope, for a view switch (PT-04).
     *
     * @return array<int, array{code: string, id: string}>
     */
    public function scope(Context $context): array
    {
        $companies = [];

        foreach ($context->viewer->scope->apply(DB::table('companies'), 'id')->orderBy('code')->get(['id', 'code', 'public_id']) as $row) {
            $companies[(int) Row::text($row, 'id')] = ['code' => Row::text($row, 'code'), 'id' => Row::text($row, 'public_id')];
        }

        return $companies;
    }

    /** The account's company grants, MSG-38's test (RG-04): an account without one reads nothing above S0. */
    public function granted(Context $context): int
    {
        return $context->viewer->scope->all ? 1 : count($context->viewer->scope->companyIds);
    }

    /**
     * @param  list<class-string<Signal>>  $signals
     * @return list<array{code: string, rows: list<array<string, mixed>>, counts: list<array{company: string, count: string}>, more: ?string}>
     */
    public function signals(Context $context, array $signals): array
    {
        $read = [];

        foreach ($signals as $class) {
            $signal = $this->app->make($class);

            if ($signal instanceof Signal) {
                $result = $signal->read($context);

                if ($result !== null) {
                    $read[] = $result;
                }
            }
        }

        return $read;
    }
}

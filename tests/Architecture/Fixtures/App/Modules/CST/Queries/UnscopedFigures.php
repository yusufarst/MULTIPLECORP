<?php

namespace Tests\Architecture\Fixtures\App\Modules\CST\Queries;

use App\Modules\IAM\Services\EffectiveCapabilities;
use Illuminate\Support\Facades\DB;

/** SM-03's fixture: viewer reads that skip the company scope, skip the cost family, run before both, or resolve no viewer. */
final class UnscopedFigures
{
    public function __construct(private readonly EffectiveCapabilities $capabilities) {}

    public function unscoped(int $viewerId): mixed
    {
        $viewer = $this->capabilities->for($viewerId);

        if (! $viewer->has('cost.view')) {
            return null;
        }

        return DB::table('inventory_lots')->first();
    }

    public function unheld(int $viewerId): mixed
    {
        $viewer = $this->capabilities->for($viewerId);

        return $viewer->scope->apply(DB::table('inventory_lots'), 'source_company_id')->first();
    }

    public function late(int $viewerId): mixed
    {
        $viewer = $this->capabilities->for($viewerId);
        $rows = DB::table('cost_attributions')->get();

        return $viewer->has('cost.view') ? $viewer->scope->apply(DB::table('projects'))->get() : $rows;
    }

    public function anonymous(int $viewerId): mixed
    {
        return DB::table('expenses')->where('recorded_by', $viewerId)->get();
    }
}

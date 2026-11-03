<?php

namespace App\Modules\PRJ\Queries;

use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Modules\PRJ\Contracts\ResidualObligationProvider;
use App\Modules\PRJ\Models\Project;
use App\Support\Search\Row;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\DB;

/**
 * PG-011's cancellation (J-PRJ-04; WF-PRJ-04; IP-19; L-10): before it, the companion corrections for what is actually being undone, each a link to its own command
 * the viewer may give — the client's return, each invoice's void or revision, each open purchase's cancellation or remainder closure, the remaining scope —, none
 * performed by the cancellation; after it, the CANCEL transition's reason, actor and date and the facts that stay true — open receivables active and collectable,
 * missing documents still listed —, a family the viewer lacks absent (PJ-04; SM-25). A bounded number of statements whatever the project's size (QB-02).
 */
final class ProjectCancellation
{
    public function __construct(
        private readonly EffectiveCapabilities $capabilities,
        private readonly Container $app,
    ) {}

    /** @return list<array{kind: string, label: string, href: string}> */
    public function companions(int $viewerId, Project $project): array
    {
        $viewer = $this->capabilities->for($viewerId);
        $company = $project->company_id;
        $links = [];
        $lines = DB::table('project_lines as l')->join('project_line_balances as b', 'b.project_line_id', '=', 'l.id')->where('l.project_id', $project->id)
            ->selectRaw('coalesce(bool_or(b.delivered_net_qty_base > 0), false) as delivered, coalesce(bool_or(b.demand_qty_base > b.handed_over_qty_base + b.delivered_net_qty_base + b.cancelled_qty_base), false) as remaining')->first();
        $delivered = $lines !== null && ($lines->delivered ?? false) === true;
        $remaining = $lines !== null && ($lines->remaining ?? false) === true;

        if ($delivered && $viewer->allows('sales_return.record', $company)) {
            $links[] = ['kind' => 'return', 'label' => '', 'href' => "/proyek/{$project->public_id}/retur"];
        }

        if ($viewer->allows('finance.view', $company)) {
            foreach (DB::table('invoices')->where('project_id', $project->id)->whereIn('state', ['DRAFT', 'ISSUED'])->orderBy('id')->limit(50)
                ->selectRaw("public_id, (select n.formatted_number from documents d join document_versions n on n.document_id = d.id where d.invoice_id = invoices.id and d.document_type_code in ('DOC-04', 'DOC-06') and n.formatted_number is not null order by n.id desc limit 1) as number")->get() as $row) {
                $links[] = ['kind' => 'invoice', 'label' => Row::nullable($row, 'number') ?? '', 'href' => '/keuangan/invoice/'.Row::text($row, 'public_id')];
            }
        }

        if ($viewer->allows('cost.view', $company)) {
            foreach (DB::table('purchases')->where('project_id', $project->id)->where('state', 'OPEN')->orderBy('id')->limit(50)->get(['public_id', 'purchase_number']) as $row) {
                $links[] = ['kind' => 'purchase', 'label' => Row::text($row, 'purchase_number'), 'href' => '/pembelian/'.Row::text($row, 'public_id')];
            }
        }

        if ($remaining && $viewer->allows('projects.cancel_scope', $company)) {
            $links[] = ['kind' => 'scope', 'label' => '', 'href' => "/proyek/{$project->public_id}#pemenuhan"];
        }

        return $links;
    }

    /**
     * A cancelled project's record, or null for any other state.
     *
     * @return array{reason: ?string, by: ?string, at: string, obligations: list<array{kind: string, label: string, amount: ?string, quantity: ?string}>}|null
     */
    public function record(int $viewerId, Project $project): ?array
    {
        if ($project->state !== 'CANCELLED') {
            return null;
        }

        $viewer = $this->capabilities->for($viewerId);
        $money = $viewer->allows('finance.view', $project->company_id);
        $row = DB::table('project_state_transitions as t')->leftJoin('users as u', 'u.id', '=', 't.decided_by')->where('t.project_id', $project->id)->where('t.transition_kind', 'CANCEL')
            ->orderByDesc('t.id')->first(['t.reason', 't.recorded_at', 'u.name']);
        $obligations = [];

        foreach ($this->app->tagged(ResidualObligationProvider::TAG) as $provider) {
            if (! $provider instanceof ResidualObligationProvider) {
                continue;
            }

            foreach ($provider->obligations([$project->id])[$project->id] ?? [] as $item) {
                // L-10: open receivables stay active and collectable — the finance family's, absent without `finance.view` (PJ-04).
                if ($money || $item['kind'] !== 'receivable') {
                    $obligations[] = ['kind' => $item['kind'], 'label' => $item['label'], 'amount' => $item['amount'], 'quantity' => $item['quantity']];
                }
            }
        }

        return ['reason' => $row === null ? null : Row::nullable($row, 'reason'), 'by' => $row === null ? null : Row::nullable($row, 'name'), 'at' => $row === null ? '' : Row::text($row, 'recorded_at'), 'obligations' => $obligations];
    }
}

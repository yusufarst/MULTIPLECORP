<?php

namespace App\Modules\PRJ\Queries;

use App\Modules\IAM\Data\Authority;
use App\Modules\PRJ\Models\Project;
use App\Support\BusinessDate;
use App\Support\RowHandle;
use App\Support\Search\Row;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

/**
 * PG-011's *Item dan pemenuhan* (J-FUL-01; WF-FUL-01; WF-PRJ-02; L-01; PX-01; PX-08): the project's commercial
 * confirmation — its kind, date, account and order evidence — and per confirmed line its mode and its progress by mode
 * against the pinned demand, derived each time from the line guard (`project_line_balances`): for *Gudang* reserved, net
 * dispatched, delivered and cancelled, availability from the pooled view to `stock.view` (PJ-01; RD-02) and the rest a
 * shortage (QS-02); for *Kirim langsung* net confirmed, delivered and cancelled; for *Jasa* handed over and cancelled
 * (L-39). Prices and values only with `finance.view` (PJ-12). Each line's commands present with their capability
 * (AZ-01). In at most four statements whatever the lines (QB-02).
 */
final class ProjectFulfilment
{
    /** @return array<string, mixed> */
    public function of(Authority $viewer, Project $project): array
    {
        $company = $project->company_id;
        $money = $viewer->allows('finance.view', $company);
        $stock = $viewer->allows('stock.view', $company);
        $confirmation = DB::table('commercial_confirmations as c')->where('c.project_id', $project->id)->orderByDesc('c.id')
            ->leftJoin('users as u', 'u.id', '=', 'c.decided_by')
            ->leftJoin('evidence_links as e', fn (JoinClause $join) => $join->on('e.commercial_confirmation_id', '=', 'c.id')->whereNull('e.unlinked_at'))
            ->leftJoin('evidence_documents as d', 'd.id', '=', 'e.evidence_document_id')
            ->first(['c.kind', 'c.business_date', 'c.reason', 'u.name as decided_by', 'd.public_id as evidence', 'd.evidence_type']);
        $lines = DB::table('project_lines as l')->where('l.project_id', $project->id)->whereNotNull('l.current_pin_id')
            ->join('confirmation_line_pins as p', 'p.id', '=', 'l.current_pin_id')
            ->join('project_line_balances as b', 'b.project_line_id', '=', 'l.id')
            ->join('products as r', 'r.id', '=', 'l.product_id')
            ->leftJoin('project_lines as s', 's.id', '=', 'l.split_from_line_id')
            ->when($stock, fn ($query) => $query->leftJoin('stock_product_balances as k', 'k.product_id', '=', 'l.product_id'))
            ->orderBy('l.line_no')
            ->get([
                'l.id', 'l.product_id', 'l.line_no', 'l.kind', 'l.fulfillment_mode', 'p.description', 'r.name as product', 'p.qty_base as pinned', 'p.unit_price', 'p.sales_value', 'p.ratio_to_base',
                'b.demand_qty_base', 'b.reserved_qty_base', 'b.dispatched_net_qty_base', 'b.dropship_confirmed_net_qty_base', 'b.handed_over_qty_base',
                'b.delivered_net_qty_base', 'b.cancelled_qty_base', 's.line_no as split_from',
                // The unfilled remainder and, for *Gudang*, what no reservation covers (L-04; QS-02), in the database's exact arithmetic.
                DB::raw('b.demand_qty_base - b.dispatched_net_qty_base - b.dropship_confirmed_net_qty_base - b.handed_over_qty_base - b.cancelled_qty_base as remainder'),
                DB::raw("case when l.fulfillment_mode = 'WAREHOUSE' then greatest(b.demand_qty_base - b.dispatched_net_qty_base - b.cancelled_qty_base - b.reserved_qty_base, 0) else 0 end as shortage"),
                DB::raw('b.demand_qty_base > b.dispatched_net_qty_base + b.dropship_confirmed_net_qty_base + b.handed_over_qty_base + b.cancelled_qty_base as open'),
                ...($stock ? [DB::raw('coalesce(k.on_hand_qty - k.unusable_qty - k.reserved_qty, 0) as available')] : []),
            ])->all();
        $base = DB::table('product_units as pu')->join('units as u', 'u.id', '=', 'pu.unit_id')->where('pu.is_base', true)
            ->whereIn('pu.product_id', DB::table('project_lines')->where('project_id', $project->id)->select('product_id'))
            ->pluck('u.code', 'pu.product_id')->all();
        $active = in_array($project->state, ['DRAFT', 'ACTIVE'], true);
        // "Konfirmasi Proyek" without a quotation: an ACTIVE project not yet confirmed, its quotation not sent or approved (WF-PRJ-02).
        $quotation = DB::table('quotation_revisions')->where('project_id', $project->id)->orderByDesc('revision_no')->value('state');
        $confirm = $confirmation === null && $project->state === 'ACTIVE' && ! in_array($quotation, ['SENT', 'APPROVED'], true)
            && $viewer->allows('projects.confirm', $company) && $viewer->allows('projects.view', $company);

        return [
            'confirmation' => $confirmation === null ? null : [
                'kind' => Row::text($confirmation, 'kind'),
                'business_date' => Row::text($confirmation, 'business_date'),
                'decided_by' => Row::nullable($confirmation, 'decided_by'),
                'reason' => Row::nullable($confirmation, 'reason'),
                'evidence' => Row::nullable($confirmation, 'evidence') === null ? null : ['id' => Row::text($confirmation, 'evidence'), 'type' => Row::text($confirmation, 'evidence_type')],
            ],
            'lines' => array_map(function (object $line) use ($money, $stock, $base, $active, $viewer, $company): array {
                $open = ($line->open ?? false) === true;

                return [
                    'handle' => RowHandle::of('project_lines', (int) Row::text($line, 'id')),
                    'no' => (int) Row::text($line, 'line_no'),
                    'kind' => Row::text($line, 'kind'),
                    'mode' => Row::text($line, 'fulfillment_mode'),
                    'product' => Row::text($line, 'product'),
                    'description' => Row::text($line, 'description'),
                    'unit' => is_string($base[(int) Row::text($line, 'product_id')] ?? null) ? $base[(int) Row::text($line, 'product_id')] : '',
                    'demand' => Row::text($line, 'demand_qty_base'),
                    'reserved' => Row::text($line, 'reserved_qty_base'),
                    'dispatched' => Row::text($line, 'dispatched_net_qty_base'),
                    'dropship' => Row::text($line, 'dropship_confirmed_net_qty_base'),
                    'handed_over' => Row::text($line, 'handed_over_qty_base'),
                    'delivered' => Row::text($line, 'delivered_net_qty_base'),
                    'cancelled' => Row::text($line, 'cancelled_qty_base'),
                    'remainder' => Row::text($line, 'remainder'),
                    'shortage' => Row::text($line, 'shortage'),
                    'split_from' => Row::nullable($line, 'split_from') === null ? null : (int) Row::text($line, 'split_from'),
                    ...($stock ? ['available' => Row::text($line, 'available')] : []),
                    ...($money ? ['price' => Row::text($line, 'unit_price'), 'value' => Row::text($line, 'sales_value')] : []),
                    'can' => [
                        'cancel' => $active && $open && $viewer->allows('projects.cancel_scope', $company),
                        'replan' => $active && $open && Row::text($line, 'kind') === 'GOODS' && $viewer->allows('projects.manage', $company),
                    ],
                ];
            }, $lines),
            'can' => ['confirm' => $confirm],
            'today' => BusinessDate::today(),
            // The client's order evidence registered for the company (IP-16; TASK-030), the confirmation choosing one (PJ-08).
            'evidence' => $confirm ? array_map(fn (object $row): array => [
                'id' => Row::text($row, 'public_id'),
                'type' => Row::text($row, 'evidence_type'),
                'issuer' => Row::text($row, 'declared_issuer'),
                'reference' => Row::nullable($row, 'issuer_reference'),
                'date' => Row::text($row, 'document_date'),
            ], DB::table('evidence_documents')->where('company_id', $company)->whereIn('evidence_type', ['CLIENT_SPK', 'CLIENT_ORDER', 'SIPLAH_ORDER'])
                ->orderByDesc('id')->limit(20)->get(['public_id', 'evidence_type', 'declared_issuer', 'issuer_reference', 'document_date'])->all()) : [],
        ];
    }
}

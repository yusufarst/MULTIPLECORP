<?php

namespace App\Modules\FUL\Queries;

use App\Modules\DOC\Data\EvidenceTypes;
use App\Modules\FUL\Services\DropshipPosting;
use App\Modules\IAM\Data\Authority;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Modules\INV\Queries\StockCursor;
use App\Modules\PRJ\Data\ProjectRef;
use App\Support\Money;
use App\Support\RowHandle;
use App\Support\Search\Row;
use Brick\Math\BigDecimal;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * PG-032 *Kirim Langsung* (ROUTE-032; NAV-032; SCR-30; J-FUL-03): a project's direct-to-client purchase lines with their
 * confirmed, remaining and awaited-replacement figures (PX-03; PX-08), its confirmations with each line's open and
 * delivered quantity, serial, reversals and returns, their *Surat Jalan*, the proofs and racks the forms may cite. The
 * actual cost appears only with `cost.view` (J-FUL-03; PJ-06); each command's control only with its capability
 * (RG-06). Receiving and dispatch are never offered for a drop-ship line (BR-INV-11). In a constant number of
 * statements whatever the rows (QB-02).
 */
final class DirectShipments
{
    /** The rows one list shows. */
    public const PAGE = 50;

    public function __construct(
        private readonly EffectiveCapabilities $capabilities,
    ) {}

    /** The project, when the viewer holds the capability in its company (CS-03); otherwise not found. */
    public function projectOf(int $viewerId, string $publicId, string $capability): ?ProjectRef
    {
        $row = preg_match('/^[0-9a-f-]{36}$/i', $publicId) === 1 ? DB::table('projects')->where('public_id', strtolower($publicId))->first(['id', 'company_id', 'public_id']) : null;

        if ($row === null || ! $this->capabilities->for($viewerId)->allows($capability, (int) Row::text($row, 'company_id'))) {
            return null;
        }

        return new ProjectRef((int) Row::text($row, 'id'), (int) Row::text($row, 'company_id'), Row::text($row, 'public_id'));
    }

    /**
     * ROUTE-032: the page's content, null when the project is not the viewer's to open with any of the page's capabilities;
     * the lines and the confirmations each a page at a time, continued after the row a page ended with (D-PF-02; PF-14).
     *
     * @return array<string, mixed>|null
     */
    public function page(int $viewerId, string $publicId, ?string $linesAfter = null, ?string $confirmationsAfter = null): ?array
    {
        $project = $this->projectOf($viewerId, $publicId, 'dropship.confirm') ?? $this->projectOf($viewerId, $publicId, 'fulfillment.correct') ?? $this->projectOf($viewerId, $publicId, 'sales_return.record');

        if ($project === null) {
            return null;
        }

        $viewer = $this->capabilities->for($viewerId);
        $company = $project->companyId;
        $cost = $viewer->allows('cost.view', $company);
        $header = DB::table('projects as p')->leftJoin('client_organizations as o', 'o.id', '=', 'p.client_organization_id')->where('p.id', $project->id)->first(['p.public_id', 'p.project_number', 'p.title', 'p.state', 'o.name as client']);

        if ($header === null) {
            return null;
        }

        return [
            'project' => ['id' => Row::text($header, 'public_id'), 'number' => Row::text($header, 'project_number'), 'title' => Row::text($header, 'title'), 'client' => Row::nullable($header, 'client'), 'open' => in_array(Row::text($header, 'state'), ['DRAFT', 'ACTIVE'], true)],
            ...self::lines($viewer, $project->id, $linesAfter),
            ...self::confirmations($viewer, $project, $cost, $confirmationsAfter),
            'proofs' => self::proofs($viewer, $company),
            'racks' => array_values(array_map(fn (object $row): array => ['id' => RowHandle::of('locations', (int) Row::text($row, 'id')), 'code' => Row::text($row, 'code'), 'name' => Row::text($row, 'name')],
                DB::table('locations')->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name'])->all())),
            'can' => [
                'confirm' => $viewer->allows('dropship.confirm', $company), 'reverse' => $viewer->allows('fulfillment.correct', $company), 'return' => $viewer->allows('sales_return.record', $company),
                'cost' => $cost, 'issue' => $viewer->allows('documents.issue', $company), 'upload' => $viewer->allows('evidence.upload', $company),
            ],
        ];
    }

    /** The project's purchase by its public id: one of its company's, linked to the project. */
    public function purchaseOf(ProjectRef $project, string $publicId): ?int
    {
        $id = preg_match('/^[0-9a-f-]{36}$/i', $publicId) === 1 ? DB::table('purchases')->where('public_id', strtolower($publicId))->where('company_id', $project->companyId)->where('project_id', $project->id)->value('id') : null;

        return is_numeric($id) ? (int) $id : null;
    }

    /** The purchase's drop-ship line by its handle. */
    public function purchaseLineOf(int $purchaseId, string $handle): ?int
    {
        return self::byHandle('purchase_lines', DB::table('purchase_lines')->where('purchase_id', $purchaseId)->where('delivery_mode', 'DROPSHIP')->pluck('id')->all(), $handle);
    }

    /** The project's confirmation by its public id. */
    public function confirmationOf(ProjectRef $project, string $publicId): ?int
    {
        $id = preg_match('/^[0-9a-f-]{36}$/i', $publicId) === 1 ? DB::table('dropship_confirmations')->where('public_id', strtolower($publicId))->where('project_id', $project->id)->where('company_id', $project->companyId)->value('id') : null;

        return is_numeric($id) ? (int) $id : null;
    }

    /** The confirmation's line by its handle. */
    public function confirmationLineOf(int $confirmationId, string $handle): ?int
    {
        return self::byHandle('dropship_confirmation_lines', DB::table('dropship_confirmation_lines')->where('dropship_confirmation_id', $confirmationId)->pluck('id')->all(), $handle);
    }

    /** The open purchase-return case a replacement answers, by its public id, of the company. */
    public function caseOf(ProjectRef $project, string $publicId): ?int
    {
        $id = preg_match('/^[0-9a-f-]{36}$/i', $publicId) === 1 ? DB::table('correction_cases')->where('public_id', strtolower($publicId))->where('company_id', $project->companyId)->where('case_kind', 'PURCHASE_RETURN')->value('id') : null;

        return is_numeric($id) ? (int) $id : null;
    }

    /** An active rack by its handle. */
    public function rackOf(string $handle): ?int
    {
        return self::byHandle('locations', DB::table('locations')->where('is_active', true)->pluck('id')->all(), $handle);
    }

    /** The proof a confirmation or its delivery cites: the company's own evidence of a delivery type the viewer sees (PJ-08). */
    public function evidenceOf(int $viewerId, int $companyId, string $publicId): ?int
    {
        $row = preg_match('/^[0-9a-f-]{36}$/i', $publicId) === 1 ? DB::table('evidence_documents')->where('public_id', strtolower($publicId))->where('company_id', $companyId)->where('evidence_scope', 'COMPANY')->whereIn('evidence_type', Deliveries::EVIDENCE)->first(['id', 'evidence_type']) : null;

        return $row !== null && EvidenceTypes::mayView($this->capabilities->for($viewerId), Row::text($row, 'evidence_type'), $companyId) ? (int) Row::text($row, 'id') : null;
    }

    /**
     * The product's policy a confirmation line was entered under (ST-09).
     *
     * @return array{serialized: bool, scale: int}
     */
    public function policyOf(int $purchaseLineId): array
    {
        $row = DB::table('purchase_lines as l')->join('products as p', 'p.id', '=', 'l.product_id')->where('l.id', $purchaseLineId)->first(['p.is_serialized', 'p.quantity_scale']);

        return ['serialized' => $row !== null && (bool) Row::text($row, 'is_serialized'), 'scale' => $row === null ? 0 : (int) Row::text($row, 'quantity_scale')];
    }

    /**
     * The likely duplicates as MSG-15 lists them: each standing correction's date, kind and quantity.
     *
     * @param  list<string>  $commands
     * @return list<array{id: string, label: string, href: null}>
     */
    public function matches(int $confirmationId, array $commands): array
    {
        return array_values(array_map(fn (object $row): array => ['id' => Row::text($row, 'command_id'), 'label' => Row::text($row, 'date').' · '.self::plain(Row::text($row, 'quantity')), 'href' => null],
            DB::table('audit_events')->where('entity_type', 'dropship_confirmation')->where('entity_id', $confirmationId)->whereIn('command_id', $commands === [] ? ['00000000-0000-0000-0000-000000000000'] : $commands)
                ->orderBy('id')->get(['command_id', DB::raw("metadata->>'business_date' as date"), DB::raw("after->>'quantity' as quantity")])->all()));
    }

    /**
     * The project's drop-ship purchase lines, by purchase and line number: ordered, confirmed net, the line's normal
     * remainder, the demand's remainder, the replacement awaited and the cases it answers (C-29; C-30; PX-03).
     *
     * @return array{lines: list<array<string, mixed>>, lines_more: ?string}
     */
    private static function lines(Authority $viewer, int $projectId, ?string $after): array
    {
        $binding = ['list' => 'dropship-lines', 'project' => $projectId];
        $key = $after === null ? null : StockCursor::read($viewer, $after, $binding, 1);
        $found = DB::table('purchase_lines as l')->join('purchases as p', 'p.id', '=', 'l.purchase_id')->join('purchase_line_balances as b', 'b.purchase_line_id', '=', 'l.id')
            ->join('project_lines as pl', 'pl.id', '=', 'l.project_line_id')->join('project_line_balances as pb', 'pb.project_line_id', '=', 'pl.id')->join('products as pr', 'pr.id', '=', 'l.product_id')
            ->leftJoin('suppliers as s', 's.id', '=', 'p.supplier_id')->leftJoin('units as u', 'u.id', '=', 'l.entered_unit_id')->leftJoin('units as bu', 'bu.id', '=', 'pr.base_unit_id')
            ->where('pl.project_id', $projectId)->where('l.delivery_mode', 'DROPSHIP')->where('l.kind', 'GOODS')->where('l.id', '>', (int) ($key[0] ?? 0))->orderBy('l.id')->limit(self::PAGE + 1)
            ->get(['l.id', 'l.line_no', 'l.ratio_to_base', 'l.entered_qty', 'pl.description', 'pr.is_serialized', 'pr.quantity_scale', 'u.code as unit', 'bu.code as base_unit', 'p.public_id as purchase', 'p.purchase_number', 'p.state', 's.name as supplier',
                'b.ordered_qty_base', 'b.dropship_confirmed_net_qty_base', 'b.replacement_expected_qty_base', 'b.replacement_received_net_qty_base',
                DB::raw('b.ordered_qty_base - b.normal_received_net_qty_base - b.closed_qty_base - b.dropship_confirmed_net_qty_base as room'),
                DB::raw('pb.demand_qty_base - pb.dispatched_net_qty_base - pb.dropship_confirmed_net_qty_base - pb.handed_over_qty_base - pb.cancelled_qty_base as demand'),
                DB::raw("(select coalesce(json_agg(distinct k.public_id), '[]') from stock_restorations r join dropship_confirmation_lines d on d.id = r.dropship_confirmation_line_id join correction_cases k on k.id = r.correction_case_id
                    where d.purchase_line_id = l.id and r.kind = 'DROPSHIP_SUPPLIER_RETURN' and k.state = 'OPEN') as cases")])->all();
        $rows = array_slice($found, 0, self::PAGE);
        $tail = $rows === [] ? null : $rows[count($rows) - 1];

        return ['lines' => array_map(fn (object $row): array => [
            'id' => RowHandle::of('purchase_lines', (int) Row::text($row, 'id')), 'no' => (int) Row::text($row, 'line_no'), 'description' => Row::text($row, 'description'),
            'purchase' => ['id' => Row::text($row, 'purchase'), 'number' => Row::text($row, 'purchase_number'), 'supplier' => Row::nullable($row, 'supplier'), 'open' => Row::text($row, 'state') === 'OPEN'],
            'unit' => Row::nullable($row, 'unit'), 'base_unit' => Row::nullable($row, 'base_unit'), 'ratio' => (int) Row::text($row, 'ratio_to_base'), 'serialized' => (bool) Row::text($row, 'is_serialized'), 'scale' => (int) Row::text($row, 'quantity_scale'),
            'ordered' => self::plain(Row::text($row, 'ordered_qty_base')), 'confirmed' => self::plain(Row::text($row, 'dropship_confirmed_net_qty_base')),
            'remainder' => self::plain((string) BigDecimal::max(BigDecimal::zero(), BigDecimal::min(BigDecimal::of(Row::text($row, 'room')), BigDecimal::of(Row::text($row, 'demand'))))),
            'awaited' => self::plain((string) BigDecimal::of(Row::text($row, 'replacement_expected_qty_base'))->minus(Row::text($row, 'replacement_received_net_qty_base'))),
            'cases' => array_values(array_filter((array) json_decode(Row::text($row, 'cases'), true), is_string(...))),
        ], $rows), 'lines_more' => count($found) > self::PAGE && $tail !== null ? StockCursor::of($viewer, [(int) Row::text($tail, 'id')], $binding) : null];
    }

    /**
     * The project's confirmations, newest first: each line's figures and serial, its corrections, the cost with
     * `cost.view` (row 75; PJ-06), its *Surat Jalan*.
     *
     * @return array{confirmations: list<array<string, mixed>>, confirmations_more: ?string}
     */
    private static function confirmations(Authority $viewer, ProjectRef $project, bool $cost, ?string $after): array
    {
        $binding = ['list' => 'dropship-confirmations', 'project' => $project->id];
        $key = $after === null ? null : StockCursor::read($viewer, $after, $binding, 1);
        $found = DB::table('dropship_confirmations as c')->join('purchases as p', 'p.id', '=', 'c.purchase_id')->leftJoin('suppliers as s', 's.id', '=', 'p.supplier_id')->where('c.project_id', $project->id)
            ->when($key !== null, fn (Builder $older): Builder => $older->where('c.id', '<', (int) ($key[0] ?? 0)))->orderByDesc('c.id')->limit(self::PAGE + 1)
            ->get(['c.id', 'c.public_id', 'c.supplier_shipment_reference', 'c.business_date', 'p.purchase_number', 's.name as supplier'])->all();
        $headers = array_slice($found, 0, self::PAGE);
        $tail = $headers === [] ? null : $headers[count($headers) - 1];
        $more = count($found) > self::PAGE && $tail !== null ? StockCursor::of($viewer, [(int) Row::text($tail, 'id')], $binding) : null;
        $ids = array_map(fn (object $row): int => (int) Row::text($row, 'id'), $headers);

        if ($ids === []) {
            return ['confirmations' => [], 'confirmations_more' => null];
        }

        $lines = DB::table('dropship_confirmation_lines as d')->join('stock_consumption_balances as b', 'b.dropship_confirmation_line_id', '=', 'd.id')->join('purchase_lines as l', 'l.id', '=', 'd.purchase_line_id')
            ->join('project_lines as pl', 'pl.id', '=', 'd.project_line_id')->leftJoin('serial_units as su', 'su.id', '=', 'd.serial_unit_id')->leftJoin('units as u', 'u.id', '=', 'd.entered_unit_id')
            ->whereIn('d.dropship_confirmation_id', $ids)->orderBy('d.id')
            ->get(['d.id', 'd.dropship_confirmation_id', 'd.qty_base', 'd.entered_qty', 'd.is_replacement', 'l.line_no', 'pl.description', 'su.serial_number', 'u.code as unit',
                DB::raw('b.qty_base - b.restored_qty_base - b.reclassified_qty_base - b.substituted_qty_base as open'), DB::raw('('.DropshipPosting::DELIVERED.') as delivered'),
                DB::raw("(select coalesce(sum(a.amount), 0) from cost_attributions a where a.dropship_confirmation_line_id = d.id and a.cost_class = 'HPP') as cost")])->all();
        $corrections = DB::table('stock_restorations as r')->join('dropship_confirmation_lines as d', 'd.id', '=', 'r.dropship_confirmation_line_id')->leftJoin('correction_cases as k', 'k.id', '=', 'r.correction_case_id')
            ->whereIn('d.dropship_confirmation_id', $ids)->orderBy('r.id')->get(['d.dropship_confirmation_id', 'r.kind', 'r.qty_base', 'r.recorded_at', 'k.public_id as case'])->all();
        $documents = DB::table('documents as x')->whereIn('x.dropship_confirmation_id', $ids)->where('x.document_type_code', 'DOC-03')
            ->get(['x.dropship_confirmation_id', 'x.public_id', DB::raw('(select v.state from document_versions v where v.document_id = x.id order by v.id desc limit 1) as state'),
                DB::raw("(select v.formatted_number from document_versions v where v.document_id = x.id and v.state = 'ISSUED' order by v.id desc limit 1) as number")])->all();
        $delivered = DB::table('delivery_lines as v')->join('deliveries as y', 'y.id', '=', 'v.delivery_id')->join('dropship_confirmation_lines as d', 'd.id', '=', 'v.dropship_confirmation_line_id')
            ->whereIn('d.dropship_confirmation_id', $ids)->whereNull('v.reverses_delivery_line_id')->distinct()->get(['d.dropship_confirmation_id', 'y.recipient_name', 'y.business_date'])->all();
        $linesOf = self::byConfirmation($lines);
        $correctionsOf = self::byConfirmation($corrections);
        $documentsOf = self::byConfirmation($documents);
        $deliveredOf = self::byConfirmation($delivered);

        return ['confirmations' => array_map(function (object $header) use ($cost, $linesOf, $correctionsOf, $documentsOf, $deliveredOf): array {
            $id = (int) Row::text($header, 'id');
            $rows = $linesOf[$id] ?? [];
            $document = ($documentsOf[$id] ?? [])[0] ?? null;
            $client = ($deliveredOf[$id] ?? [])[0] ?? null;

            return [
                'id' => Row::text($header, 'public_id'), 'reference' => Row::text($header, 'supplier_shipment_reference'), 'date' => Row::text($header, 'business_date'),
                'purchase' => Row::text($header, 'purchase_number'), 'supplier' => Row::nullable($header, 'supplier'),
                'delivery' => $client === null ? null : ['recipient' => Row::text($client, 'recipient_name'), 'date' => Row::text($client, 'business_date')],
                'lines' => array_map(fn (object $row): array => [
                    'id' => RowHandle::of('dropship_confirmation_lines', (int) Row::text($row, 'id')), 'no' => (int) Row::text($row, 'line_no'), 'description' => Row::text($row, 'description'),
                    'quantity' => self::plain(Row::text($row, 'qty_base')), 'open' => self::plain(Row::text($row, 'open')), 'delivered' => self::plain((string) BigDecimal::max(BigDecimal::zero(), BigDecimal::of(Row::text($row, 'delivered')))),
                    'serial' => Row::nullable($row, 'serial_number'), 'replacement' => (bool) Row::text($row, 'is_replacement'),
                    ...($cost ? ['cost' => Money::of(Row::text($row, 'cost'))->toString()] : []),
                ], $rows),
                'corrections' => array_map(fn (object $row): array => ['kind' => Row::text($row, 'kind'), 'quantity' => self::plain(Row::text($row, 'qty_base')), 'at' => Row::text($row, 'recorded_at'), 'case' => Row::nullable($row, 'case')], $correctionsOf[$id] ?? []),
                'document' => $document === null ? null : ['id' => Row::text($document, 'public_id'), 'state' => Row::nullable($document, 'state'), 'number' => Row::nullable($document, 'number')],
                'standing' => array_reduce($rows, fn (bool $any, object $row): bool => $any || BigDecimal::of(Row::text($row, 'open'))->isPositive(), false),
            ];
        }, $headers), 'confirmations_more' => $more];
    }

    /**
     * The proofs the forms may cite: the company's delivery evidence the viewer sees (PJ-08), newest first.
     *
     * @return list<array{id: string, type: string, label: string}>
     */
    private static function proofs(Authority $viewer, int $company): array
    {
        $options = [];

        foreach (DB::table('evidence_documents')->where('company_id', $company)->where('evidence_scope', 'COMPANY')->whereIn('evidence_type', Deliveries::EVIDENCE)->orderByDesc('id')->limit(self::PAGE)->get(['public_id', 'evidence_type', 'declared_issuer', 'issuer_reference', 'document_date'])->all() as $row) {
            if (EvidenceTypes::mayView($viewer, Row::text($row, 'evidence_type'), $company)) {
                $options[] = ['id' => Row::text($row, 'public_id'), 'type' => Row::text($row, 'evidence_type'), 'label' => Row::text($row, 'declared_issuer').' · '.(Row::nullable($row, 'issuer_reference') ?? Row::text($row, 'document_date'))];
            }
        }

        return $options;
    }

    /**
     * Rows grouped by the confirmation they name, in their order.
     *
     * @param  array<int, object>  $rows
     * @return array<int, list<object>>
     */
    private static function byConfirmation(array $rows): array
    {
        $groups = [];

        foreach ($rows as $row) {
            $groups[(int) Row::text($row, 'dropship_confirmation_id')][] = $row;
        }

        return $groups;
    }

    /** The project's direct shipment's *Surat Jalan* by its public id (ROUTE-216). */
    public function noteOf(ProjectRef $project, string $publicId): ?string
    {
        $id = preg_match('/^[0-9a-f-]{36}$/i', $publicId) === 1 ? DB::table('documents as x')->join('dropship_confirmations as c', 'c.id', '=', 'x.dropship_confirmation_id')
            ->where('x.public_id', strtolower($publicId))->where('x.document_type_code', 'DOC-03')->where('c.project_id', $project->id)->value('x.public_id') : null;

        return is_string($id) ? $id : null;
    }

    /** @param  array<mixed>  $ids */
    private static function byHandle(string $table, array $ids, string $handle): ?int
    {
        if (! RowHandle::valid($handle)) {
            return null;
        }

        foreach ($ids as $id) {
            if (is_numeric($id) && hash_equals(RowHandle::of($table, (int) $id), $handle)) {
                return (int) $id;
            }
        }

        return null;
    }

    /** A decimal without its trailing zeros. */
    private static function plain(string $value): string
    {
        return str_contains($value, '.') ? rtrim(rtrim($value, '0'), '.') : $value;
    }
}

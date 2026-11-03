<?php

namespace App\Modules\FUL\Queries;

use App\Modules\DOC\Data\EvidenceTypes;
use App\Modules\FUL\Data\ShipmentFigures;
use App\Modules\IAM\Data\Authority;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Modules\INV\Data\ClientReturns;
use App\Modules\PRJ\Data\ProjectRef;
use App\Support\RowHandle;
use App\Support\Search\Row;
use Brick\Math\BigDecimal;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * PG-030 *Pengiriman* and PG-031 *Catat Pengiriman* (ROUTE-030; ROUTE-031; NAV-030; NAV-031; SCR-28; SCR-29; J-FUL-02;
 * D-IA-16): the shipments still awaiting delivery (QS-06) and the deliveries recorded, each row with its company where
 * several are in scope (CS-07; CS-08); one shipment with its lines' net shipped, delivered and closed figures (PX-08),
 * its deliveries with their reversals, proof and papers, its closures, and the evidence the form may cite (PJ-08). The
 * dispatch and the delivery stay two linked records. No stock control and no cost figure (FS-03; PJ-15). Each command's
 * control present with its capability (AZ-01). In a constant number of statements whatever the rows (QB-02).
 */
final class Deliveries
{
    /** The rows one list shows. */
    public const PAGE = 50;

    /** The evidence a delivery cites as its proof (L-19; PJ-08). */
    public const EVIDENCE = ['DELIVERY_PROOF', 'OTHER'];

    /** A shipment line still awaiting: net shipped above net delivered and what closures hold back. */
    private const AWAITING = <<<'SQL'
        exists (select 1 from dispatch_lines d where d.stock_movement_id = m.id and
            (select coalesce(sum(c.qty_base - b.restored_qty_base - b.reclassified_qty_base - b.substituted_qty_base), 0) from stock_consumptions c join stock_consumption_balances b on b.stock_consumption_id = c.id where c.dispatch_line_id = d.id)
            >
        SQL
        // Net delivered: what the client returned is no longer delivered (PX-08; TASK-048).
        .ClientReturns::NET_DELIVERED.<<<'SQL'
            + greatest(0, (select coalesce(sum(k.closed_qty_base), 0) from delivery_closures k where k.dispatch_line_id = d.id and k.closure_kind <> 'LOST_IN_TRANSIT' and not exists (select 1 from delivery_closures s where s.supersedes_id = k.id))
                - (select coalesce(sum(r.qty_base), 0) from stock_restorations r join stock_consumptions c on c.id = r.stock_consumption_id where c.dispatch_line_id = d.id and r.kind = 'UNDELIVERED_RETURN'
                    and r.recorded_at > (select min(k.recorded_at) from delivery_closures k where k.dispatch_line_id = d.id and k.closure_kind <> 'LOST_IN_TRANSIT'))))
        SQL;

    /** A paper's latest version state and its issued number. */
    private const PAPER = "(select v.state from document_versions v where v.document_id = d.id order by v.id desc limit 1) as state, (select v.formatted_number from document_versions v where v.document_id = d.id and v.state = 'ISSUED' order by v.id desc limit 1) as number, exists (select 1 from stock_restorations r join stock_consumptions c on c.id = r.stock_consumption_id join delivery_lines dl on dl.dispatch_line_id = c.dispatch_line_id where dl.delivery_id = d.delivery_id and r.kind = 'SUBSTITUTION' and r.recorded_at > (select max(v.recorded_at) from document_versions v where v.document_id = d.id and v.state = 'ISSUED')) as substituted";

    public function __construct(
        private readonly EffectiveCapabilities $capabilities,
    ) {}

    /** @return array{record: bool, close: bool, lost: bool, correct: bool, issue: bool, upload: bool} */
    public function can(int $viewerId): array
    {
        $viewer = $this->capabilities->for($viewerId);

        return [
            'record' => $viewer->has('delivery.record'), 'close' => $viewer->has('delivery.close'), 'lost' => $viewer->has('delivery.close') && $viewer->has('stock.adjust'),
            'correct' => $viewer->has('fulfillment.correct'), 'issue' => $viewer->has('documents.issue'), 'upload' => $viewer->has('evidence.upload'),
        ];
    }

    /**
     * PG-030: the shipments awaiting delivery, or the deliveries recorded, newest first, each in the viewer's companies, by
     * keyset after the row `$after` names (D-PF-02); an unknown one starts from the newest.
     *
     * @return array{tab: string, rows: list<array<string, mixed>>, more: bool, several: bool}
     */
    public function list(int $viewerId, string $tab, ?string $after = null): array
    {
        $viewer = $this->capabilities->for($viewerId);
        $companies = self::companies($viewer);
        $several = count($companies) > 1;
        $after = $after !== null && preg_match('/^[0-9a-f-]{36}$/i', $after) === 1 ? strtolower($after) : null;

        if ($tab === 'tercatat') {
            $found = DB::table('deliveries as v')->join('projects as p', 'p.id', '=', 'v.project_id')->join('companies as co', 'co.id', '=', 'v.company_id')
                ->whereIn('v.company_id', $companies)->when($after !== null, fn (Builder $older) => $older->whereRaw('v.id < coalesce((select a.id from deliveries a where a.public_id = ?), 9223372036854775807)', [$after]))->orderByDesc('v.id')->limit(self::PAGE + 1)
                ->get(['v.public_id', 'v.business_date', 'v.recipient_name', 'p.project_number', 'co.code as company', DB::raw('(select d.stock_movement_id from delivery_lines l join dispatch_lines d on d.id = l.dispatch_line_id where l.delivery_id = v.id order by l.id limit 1) as movement'),
                    DB::raw('(select m.public_id from delivery_lines l join dispatch_lines d on d.id = l.dispatch_line_id join stock_movements m on m.id = d.stock_movement_id where l.delivery_id = v.id order by l.id limit 1) as shipment')])->all();
            $rows = array_map(fn (object $row): array => ['id' => Row::text($row, 'public_id'), 'date' => Row::text($row, 'business_date'), 'recipient' => Row::text($row, 'recipient_name'), 'project' => Row::text($row, 'project_number'), 'company' => $several ? Row::text($row, 'company') : null, 'shipment' => Row::nullable($row, 'shipment')], array_slice($found, 0, self::PAGE));

            return ['tab' => 'tercatat', 'rows' => $rows, 'more' => count($found) > self::PAGE, 'several' => $several];
        }

        $found = DB::table('stock_movements as m')->join('projects as p', 'p.id', '=', 'm.project_id')->join('companies as co', 'co.id', '=', 'p.company_id')->leftJoin('client_organizations as o', 'o.id', '=', 'p.client_organization_id')
            ->where('m.movement_type', 'DISPATCH')->whereIn('p.company_id', $companies)->whereRaw(self::AWAITING)
            ->when($after !== null, fn (Builder $older) => $older->whereRaw('m.id < coalesce((select a.id from stock_movements a where a.public_id = ?), 9223372036854775807)', [$after]))->orderByDesc('m.id')->limit(self::PAGE + 1)
            ->get(['m.public_id', 'm.business_date', 'p.project_number', 'p.title', 'o.name as client', 'co.code as company'])->all();
        $rows = array_map(fn (object $row): array => ['id' => Row::text($row, 'public_id'), 'date' => Row::text($row, 'business_date'), 'project' => Row::text($row, 'project_number'), 'title' => Row::text($row, 'title'), 'client' => Row::nullable($row, 'client'), 'company' => $several ? Row::text($row, 'company') : null], array_slice($found, 0, self::PAGE));

        return ['tab' => 'menunggu', 'rows' => $rows, 'more' => count($found) > self::PAGE, 'several' => $several];
    }

    /**
     * The shipment a command or PG-031 names, in a company where the viewer records deliveries (CS-03; SM-01).
     *
     * @return array{movement: int, project: ProjectRef}|null
     */
    public function shipmentOf(int $viewerId, string $publicId, string $capability = 'delivery.record'): ?array
    {
        $row = preg_match('/^[0-9a-f-]{36}$/i', $publicId) === 1 ? DB::table('stock_movements as m')->join('projects as p', 'p.id', '=', 'm.project_id')
            ->where('m.public_id', strtolower($publicId))->where('m.movement_type', 'DISPATCH')->first(['m.id', 'p.id as project', 'p.company_id', 'p.public_id as project_public']) : null;

        if ($row === null || ! $this->capabilities->for($viewerId)->allows($capability, (int) Row::text($row, 'company_id'))) {
            return null;
        }

        return ['movement' => (int) Row::text($row, 'id'), 'project' => new ProjectRef((int) Row::text($row, 'project'), (int) Row::text($row, 'company_id'), Row::text($row, 'project_public'))];
    }

    /**
     * PG-031: the shipment, its lines with their figures, its deliveries and closures, the proofs the form may cite.
     *
     * @return array<string, mixed>|null
     */
    public function shipment(int $viewerId, string $publicId): ?array
    {
        $found = $this->shipmentOf($viewerId, $publicId, 'projects.view');

        if ($found === null) {
            return null;
        }

        $viewer = $this->capabilities->for($viewerId);
        $company = $found['project']->companyId;
        $header = DB::table('stock_movements as m')->join('projects as p', 'p.id', '=', 'm.project_id')->leftJoin('client_organizations as o', 'o.id', '=', 'p.client_organization_id')
            ->where('m.id', $found['movement'])->first(['m.public_id', 'm.business_date', 'p.public_id as project', 'p.project_number', 'p.title', 'o.name as client']);

        if ($header === null) {
            return null;
        }

        [$lines, $internal] = $this->lines($found['movement']);

        return [
            'id' => Row::text($header, 'public_id'), 'date' => Row::text($header, 'business_date'),
            'project' => ['id' => Row::text($header, 'project'), 'number' => Row::text($header, 'project_number'), 'title' => Row::text($header, 'title')], 'client' => Row::nullable($header, 'client'),
            'lines' => $lines,
            'deliveries' => $this->deliveries($viewer, $company, $internal),
            'closures' => $this->closures(array_keys($internal)),
            'proofs' => self::proofs($viewer, $company),
        ];
    }

    /** The shipment's line by its handle. */
    public function lineOf(int $movementId, string $handle): ?int
    {
        return self::byHandle('dispatch_lines', DB::table('dispatch_lines')->where('stock_movement_id', $movementId)->pluck('id')->all(), $handle);
    }

    /** The project line a shipment line serves. */
    public function projectLineOf(int $dispatchLineId): int
    {
        $line = DB::table('dispatch_lines')->where('id', $dispatchLineId)->value('project_line_id');

        return is_numeric($line) ? (int) $line : 0;
    }

    /** The shipment's delivery by its public id, and its line by handle. */
    public function deliveryOf(int $movementId, string $publicId): ?int
    {
        $id = preg_match('/^[0-9a-f-]{36}$/i', $publicId) === 1 ? DB::table('deliveries as v')->where('v.public_id', strtolower($publicId))
            ->whereExists(fn (Builder $q) => $q->selectRaw('1')->from('delivery_lines as l')->join('dispatch_lines as d', 'd.id', '=', 'l.dispatch_line_id')->whereColumn('l.delivery_id', 'v.id')->where('d.stock_movement_id', $movementId))
            ->value('v.id') : null;

        return is_numeric($id) ? (int) $id : null;
    }

    public function deliveryLineOf(int $deliveryId, string $handle): ?int
    {
        return self::byHandle('delivery_lines', DB::table('delivery_lines')->where('delivery_id', $deliveryId)->pluck('id')->all(), $handle);
    }

    public function closureOf(int $dispatchLineId, string $handle): ?int
    {
        return self::byHandle('delivery_closures', DB::table('delivery_closures')->where('dispatch_line_id', $dispatchLineId)->pluck('id')->all(), $handle);
    }

    /**
     * The serials a shipment line still carries out, by their handles; null when one is not on it.
     *
     * @param  list<string>  $handles
     * @return list<int>|null
     */
    public function serialsOf(int $dispatchLineId, array $handles): ?array
    {
        $carried = [];

        foreach (DB::table('stock_consumptions as c')->join('stock_movement_entries as e', 'e.stock_consumption_id', '=', 'c.id')->join('serial_units as s', 's.last_entry_id', '=', 'e.id')
            ->where('c.dispatch_line_id', $dispatchLineId)->where('s.state', 'DISPATCHED')->pluck('s.id')->all() as $id) {
            if (is_numeric($id)) {
                $carried[RowHandle::of('serial_units', (int) $id)] = (int) $id;
            }
        }

        $found = [];

        foreach ($handles as $handle) {
            $found[] = $carried[$handle] ?? null;
        }

        return in_array(null, $found, true) ? null : array_values(array_filter($found, is_int(...)));
    }

    /** The proof a delivery cites: the company's own evidence of a delivery type the viewer sees (PJ-08). */
    public function evidenceOf(int $viewerId, int $companyId, string $publicId): ?int
    {
        $row = preg_match('/^[0-9a-f-]{36}$/i', $publicId) === 1 ? DB::table('evidence_documents')->where('public_id', strtolower($publicId))->where('company_id', $companyId)->whereIn('evidence_type', self::EVIDENCE)->first(['id', 'evidence_type']) : null;

        return $row !== null && EvidenceTypes::mayView($this->capabilities->for($viewerId), Row::text($row, 'evidence_type'), $companyId) ? (int) Row::text($row, 'id') : null;
    }

    /**
     * The likely duplicates as MSG-15 lists them: each delivery's date and recipient.
     *
     * @param  list<string>  $publicIds
     * @return list<array{id: string, label: string, href: null}>
     */
    public function matches(int $projectId, array $publicIds): array
    {
        return array_values(array_map(fn (object $row): array => ['id' => Row::text($row, 'public_id'), 'label' => Row::text($row, 'business_date').' · '.Row::text($row, 'recipient_name'), 'href' => null],
            DB::table('deliveries')->where('project_id', $projectId)->whereIn('public_id', $publicIds)->orderBy('id')->get(['public_id', 'business_date', 'recipient_name'])->all()));
    }

    /**
     * The likely-duplicate closures as MSG-15 lists them: each one's date, kind and quantity.
     *
     * @param  list<string>  $handles
     * @return list<array{id: string, label: string, href: null}>
     */
    public function closureMatches(int $dispatchLineId, array $handles): array
    {
        $found = [];

        foreach (DB::table('delivery_closures')->where('dispatch_line_id', $dispatchLineId)->orderBy('id')->get(['id', 'business_date', 'closure_kind', 'closed_qty_base'])->all() as $row) {
            $handle = RowHandle::of('delivery_closures', (int) Row::text($row, 'id'));

            if (in_array($handle, $handles, true)) {
                $found[] = ['id' => $handle, 'label' => Row::text($row, 'business_date').' · '.Row::text($row, 'closure_kind').' · '.self::plain(Row::text($row, 'closed_qty_base')), 'href' => null];
            }
        }

        return $found;
    }

    /** @return array{0: list<array<string, mixed>>, 1: array<int, string>} the shipment's lines with their figures in base units, and each line's handle by its id */
    private function lines(int $movementId): array
    {
        $lines = [];
        $internal = [];
        $rows = DB::table('dispatch_lines as d')->join('project_lines as pl', 'pl.id', '=', 'd.project_line_id')->join('confirmation_line_pins as pin', 'pin.id', '=', 'pl.current_pin_id')
            ->join('units as u', 'u.id', '=', 'd.entered_unit_id')->join('products as pr', 'pr.id', '=', 'd.product_id')->join('units as base', 'base.id', '=', 'pr.base_unit_id')
            ->where('d.stock_movement_id', $movementId)->orderBy('pl.line_no')
            ->get(['d.id', 'pl.line_no', 'pin.description', 'd.ratio_to_base', 'd.quantity_scale', 'pr.is_serialized', 'u.code as unit', 'base.code as base_unit', DB::raw(ShipmentFigures::SQL)])->all();
        $serials = [];

        foreach (DB::table('stock_consumptions as c')->join('stock_movement_entries as e', 'e.stock_consumption_id', '=', 'c.id')->join('serial_units as s', 's.last_entry_id', '=', 'e.id')
            ->whereIn('c.dispatch_line_id', array_map(fn (object $row): int => (int) Row::text($row, 'id'), $rows))->where('s.state', 'DISPATCHED')->orderBy('s.serial_number')
            ->get(['c.dispatch_line_id', 's.id', 's.serial_number'])->all() as $serial) {
            $serials[(int) Row::text($serial, 'dispatch_line_id')][] = ['id' => RowHandle::of('serial_units', (int) Row::text($serial, 'id')), 'serial' => Row::text($serial, 'serial_number')];
        }

        foreach ($rows as $row) {
            $id = (int) Row::text($row, 'id');
            $held = ShipmentFigures::held(BigDecimal::of(Row::text($row, 'closed')), BigDecimal::of(Row::text($row, 'returned')));
            $remainder = ShipmentFigures::remainder(BigDecimal::of(Row::text($row, 'shipped')), BigDecimal::of(Row::text($row, 'delivered')), $held);
            $internal[$id] = RowHandle::of('dispatch_lines', $id);
            $lines[] = [
                'id' => $internal[$id], 'no' => (int) Row::text($row, 'line_no'), 'description' => Row::text($row, 'description'),
                'unit' => Row::text($row, 'unit'), 'base_unit' => Row::text($row, 'base_unit'), 'ratio' => (int) Row::text($row, 'ratio_to_base'), 'scale' => (int) Row::text($row, 'quantity_scale'), 'serialized' => (bool) Row::text($row, 'is_serialized'),
                'shipped' => self::plain(Row::text($row, 'shipped')), 'delivered' => self::plain(Row::text($row, 'delivered')), 'held' => self::plain((string) $held), 'remainder' => self::plain((string) $remainder),
                'serials' => $serials[$id] ?? [],
            ];
        }

        return [$lines, $internal];
    }

    /**
     * The deliveries with lines on the shipment, newest first, their lines with reversals, the proof and papers.
     *
     * @param  array<int, string>  $dispatchLines  internal id => handle
     * @return list<array<string, mixed>>
     */
    private function deliveries(Authority $viewer, int $company, array $dispatchLines): array
    {
        if ($dispatchLines === []) {
            return [];
        }

        $lines = DB::table('delivery_lines as l')->whereIn('l.dispatch_line_id', array_keys($dispatchLines))->join('units as u', 'u.id', '=', 'l.entered_unit_id')
            ->join('project_lines as pl', 'pl.id', '=', 'l.project_line_id')->join('confirmation_line_pins as pin', 'pin.id', '=', 'pl.current_pin_id')->orderBy('l.id')
            ->get(['l.id', 'l.delivery_id', 'l.dispatch_line_id', 'l.entered_qty', 'l.reverses_delivery_line_id', 'pl.line_no', 'pin.description', 'u.code as unit'])->all();
        $ids = array_values(array_unique(array_map(fn (object $line): int => (int) Row::text($line, 'delivery_id'), $lines)));
        $reversed = [];

        foreach ($lines as $line) {
            $reverses = Row::nullable($line, 'reverses_delivery_line_id');

            if ($reverses !== null) {
                $reversed[(int) $reverses] = true;
            }
        }

        $byDelivery = [];

        foreach ($lines as $line) {
            $byDelivery[(int) Row::text($line, 'delivery_id')][] = [
                'id' => RowHandle::of('delivery_lines', (int) Row::text($line, 'id')), 'no' => (int) Row::text($line, 'line_no'), 'description' => Row::text($line, 'description'),
                'quantity' => self::plain(Row::text($line, 'entered_qty')), 'unit' => Row::text($line, 'unit'),
                'reversal' => Row::nullable($line, 'reverses_delivery_line_id') !== null, 'reversed' => isset($reversed[(int) Row::text($line, 'id')]),
            ];
        }

        $proofs = [];

        foreach (DB::table('evidence_links as k')->join('evidence_documents as e', 'e.id', '=', 'k.evidence_document_id')->whereIn('k.delivery_id', $ids)->get(['k.delivery_id', 'e.public_id', 'e.evidence_type', 'e.declared_issuer', 'e.issuer_reference'])->all() as $row) {
            if (EvidenceTypes::mayView($viewer, Row::text($row, 'evidence_type'), $company)) {
                $proofs[(int) Row::text($row, 'delivery_id')] = ['id' => Row::text($row, 'public_id'), 'label' => Row::text($row, 'declared_issuer').(Row::nullable($row, 'issuer_reference') === null ? '' : ' · '.Row::text($row, 'issuer_reference'))];
            }
        }

        $papers = [];

        // An issued paper of a delivery an entry error corrected is revised on PG-036 from the facts that stand (CM-08; WORKFLOWS §8).
        foreach (DB::table('documents as d')->whereIn('d.delivery_id', $ids)->orderBy('d.id')->get(['d.delivery_id', 'd.public_id', 'd.document_type_code', DB::raw(self::PAPER)])->all() as $row) {
            $papers[(int) Row::text($row, 'delivery_id')][] = ['id' => Row::text($row, 'public_id'), 'type' => Row::text($row, 'document_type_code'), 'state' => Row::nullable($row, 'state'), 'number' => Row::nullable($row, 'number'), 'substituted' => (bool) Row::text($row, 'substituted')];
        }

        $inspected = [];

        foreach (DB::table('deliveries as v')->whereIn('v.id', $ids)->whereExists(fn (Builder $q) => $q->selectRaw('1')->from('audit_events as a')->whereIn('a.action', ['FUL.DeliveryRecorded', 'FUL.DeliveryDuplicateConfirmed'])
            ->whereRaw("a.after->'inspection' is not null")->whereRaw("a.metadata->'links' @> jsonb_build_array(jsonb_build_object('entity_type', 'deliveries', 'public_id', v.public_id::text))"))->pluck('v.id')->all() as $id) {
            $inspected[is_numeric($id) ? (int) $id : 0] = true;
        }

        $found = [];

        foreach (DB::table('deliveries as v')->whereIn('v.id', $ids)->orderByDesc('v.id')->get(['v.id', 'v.public_id', 'v.business_date', 'v.recipient_name', 'v.condition_note'])->all() as $row) {
            $id = (int) Row::text($row, 'id');
            $found[] = [
                'id' => Row::text($row, 'public_id'), 'date' => Row::text($row, 'business_date'), 'recipient' => Row::text($row, 'recipient_name'), 'condition' => Row::nullable($row, 'condition_note'),
                'lines' => $byDelivery[$id] ?? [], 'proof' => $proofs[$id] ?? null, 'papers' => $papers[$id] ?? [],
                'corrected' => array_filter($byDelivery[$id] ?? [], fn (array $line): bool => $line['reversal']) !== [],
                'inspected' => isset($inspected[$id]),
            ];
        }

        return $found;
    }

    /**
     * The shipment's closures, newest first, each superseded or still standing.
     *
     * @param  list<int>  $dispatchLines
     * @return list<array<string, mixed>>
     */
    private function closures(array $dispatchLines): array
    {
        if ($dispatchLines === []) {
            return [];
        }

        $rows = DB::table('delivery_closures as k')->whereIn('k.dispatch_line_id', $dispatchLines)->join('project_lines as pl', 'pl.id', '=', 'k.project_line_id')->orderByDesc('k.id')
            ->get(['k.id', 'k.dispatch_line_id', 'k.closure_kind', 'k.closed_qty_base', 'k.reason', 'k.business_date', 'k.supersedes_id', 'pl.line_no'])->all();
        $superseded = array_flip(array_map(fn (object $row): int => (int) Row::text($row, 'supersedes_id'), array_filter($rows, fn (object $row): bool => Row::nullable($row, 'supersedes_id') !== null)));

        return array_values(array_map(fn (object $row): array => [
            'id' => RowHandle::of('delivery_closures', (int) Row::text($row, 'id')), 'line' => RowHandle::of('dispatch_lines', (int) Row::text($row, 'dispatch_line_id')), 'no' => (int) Row::text($row, 'line_no'),
            'kind' => Row::text($row, 'closure_kind'), 'quantity' => self::plain(Row::text($row, 'closed_qty_base')), 'reason' => Row::text($row, 'reason'), 'date' => Row::text($row, 'business_date'),
            'superseded' => isset($superseded[(int) Row::text($row, 'id')]),
        ], $rows));
    }

    /** @return list<array{id: string, type: string, label: string}> the evidence the form may cite as a delivery's proof, newest first */
    private static function proofs(Authority $viewer, int $company): array
    {
        $options = [];

        foreach (DB::table('evidence_documents')->where('company_id', $company)->whereIn('evidence_type', self::EVIDENCE)->orderByDesc('id')->limit(self::PAGE)->get(['public_id', 'evidence_type', 'declared_issuer', 'issuer_reference', 'document_date'])->all() as $row) {
            if (EvidenceTypes::mayView($viewer, Row::text($row, 'evidence_type'), $company)) {
                $options[] = ['id' => Row::text($row, 'public_id'), 'type' => Row::text($row, 'evidence_type'), 'label' => Row::text($row, 'declared_issuer').' · '.(Row::nullable($row, 'issuer_reference') ?? Row::text($row, 'document_date'))];
            }
        }

        return $options;
    }

    /** @return list<int> the companies whose deliveries the viewer sees (CS-07) */
    private static function companies(Authority $viewer): array
    {
        return array_values(array_filter(array_map(fn (mixed $id): int => is_numeric($id) ? (int) $id : 0, DB::table('companies')->orderBy('id')->pluck('id')->all()), fn (int $id): bool => $viewer->allows('projects.view', $id)));
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

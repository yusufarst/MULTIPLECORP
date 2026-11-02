<?php

namespace App\Modules\FUL\Services;

use App\Modules\DOC\Data\DocumentFacts;
use App\Modules\DOC\Services\PaperFormat;
use App\Support\Search\Row;
use Illuminate\Support\Facades\DB;

/**
 * The confirmed facts a delivery's BAST (DOC-08) or inspection record (DOC-10) renders (BR-DOC-04; L-19; WF-DOC-01): the
 * recipient, the project and the date as recorded, each line still standing at its entered unit with the serials the
 * shipment actually carries — after an identity substitution the one shipped (AX-35; CM-36) —, and for DOC-10 the client's
 * inspection as the delivery recorded it. A line reversed as an entry error, and the reversal itself, are no facts of the
 * paper (CM-08). No price, tax or cost (PJ-15).
 */
final class DeliveryFacts
{
    /** The delivery's own audit event, the one place its inspection facts are recorded. */
    private const RECORDED = ['FUL.DeliveryRecorded', 'FUL.DeliveryDuplicateConfirmed'];

    /** The serials the shipment line still carries out. */
    private const SERIALS = "(select string_agg(s.serial_number, ', ' order by s.serial_number) from stock_consumptions c join stock_movement_entries e on e.stock_consumption_id = c.id join serial_units s on s.last_entry_id = e.id where c.dispatch_line_id = l.dispatch_line_id and s.state = 'DISPATCHED') as serials";

    public static function of(int $deliveryId, bool $inspection): ?DocumentFacts
    {
        $header = DB::table('deliveries as d')->join('projects as p', 'p.id', '=', 'd.project_id')->join('client_organizations as o', 'o.id', '=', 'p.client_organization_id')
            ->where('d.id', $deliveryId)->first(['d.public_id', 'd.business_date', 'd.recipient_name', 'd.condition_note', 'p.project_number', 'p.title', 'o.name as client']);

        if ($header === null) {
            return null;
        }

        $lines = [];

        foreach (DB::table('delivery_lines as l')->where('l.delivery_id', $deliveryId)->whereNull('l.reverses_delivery_line_id')
            ->whereRaw('not exists (select 1 from delivery_lines r where r.reverses_delivery_line_id = l.id)')
            ->join('project_lines as pl', 'pl.id', '=', 'l.project_line_id')->join('confirmation_line_pins as pin', 'pin.id', '=', 'pl.current_pin_id')->join('units as u', 'u.id', '=', 'l.entered_unit_id')
            ->orderBy('l.id')->get(['pin.description', 'l.entered_qty', 'u.code', DB::raw(self::SERIALS)])->all() as $row) {
            $serials = Row::nullable($row, 'serials');
            $lines[] = ['description' => Row::text($row, 'description'), 'quantity' => Row::text($row, 'entered_qty'), 'unit' => Row::text($row, 'code'), 'unit_price' => null, 'amount' => null, 'note' => $serials === null ? null : 'No. Seri: '.$serials];
        }

        $references = [
            ['label' => 'No. Proyek', 'value' => Row::text($header, 'project_number')],
            ['label' => 'Klien', 'value' => Row::text($header, 'client')],
            ['label' => 'Tanggal Terima', 'value' => PaperFormat::date(Row::text($header, 'business_date'))],
        ];
        $condition = Row::nullable($header, 'condition_note');

        if ($condition !== null) {
            $references[] = ['label' => 'Kondisi', 'value' => $condition];
        }

        if ($inspection) {
            $facts = self::inspection($deliveryId);

            if ($facts === null) {
                return null;
            }

            $references[] = ['label' => 'Pemeriksa', 'value' => $facts['inspector']];
            $references[] = ['label' => 'Hasil Pemeriksaan', 'value' => $facts['result']];
        }

        return new DocumentFacts(
            recipient: ['name' => Row::text($header, 'recipient_name'), 'lines' => [Row::text($header, 'client')]],
            references: $references,
            subject: Row::text($header, 'title'),
            lines: $lines,
        );
    }

    /** @return array{inspector: string, result: string}|null the client's inspection facts the delivery recorded */
    public static function inspection(int $deliveryId): ?array
    {
        $publicId = DB::table('deliveries')->where('id', $deliveryId)->value('public_id');
        $after = is_string($publicId) ? DB::table('audit_events as a')->whereIn('a.action', self::RECORDED)
            ->whereRaw("a.metadata->'links' @> ?::jsonb", [json_encode([['entity_type' => 'deliveries', 'public_id' => $publicId]], JSON_THROW_ON_ERROR)])->orderBy('a.id')->value('a.after') : null;
        $decoded = is_string($after) ? json_decode($after, true) : null;
        $facts = is_array($decoded) && is_array($decoded['inspection'] ?? null) ? $decoded['inspection'] : null;

        return is_array($facts) && is_string($facts['inspector'] ?? null) && is_string($facts['result'] ?? null) ? ['inspector' => $facts['inspector'], 'result' => $facts['result']] : null;
    }
}

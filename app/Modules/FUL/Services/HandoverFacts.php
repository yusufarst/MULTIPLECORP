<?php

namespace App\Modules\FUL\Services;

use App\Modules\DOC\Data\DocumentFacts;
use App\Modules\DOC\Services\PaperFormat;
use App\Support\Search\Row;
use Illuminate\Support\Facades\DB;

/**
 * The confirmed facts a BAST (DOC-08) renders for its service handovers (BR-DOC-04; L-19; WF-DOC-01): the recipient, the
 * project and the date as recorded, and each standing line — its pinned description, the quantity at its entered unit
 * and the scope performed. A line reversed as an error, and the reversal line itself, are no facts of the paper (CM-27).
 * In two statements whatever the handovers (QB-01).
 */
final class HandoverFacts
{
    /**
     * @param  list<int>  $handoverIds
     * @return array<int, DocumentFacts> by handover
     */
    public static function of(array $handoverIds): array
    {
        if ($handoverIds === []) {
            return [];
        }

        $lines = [];
        $rows = DB::table('service_handover_lines as l')->whereIn('l.service_handover_id', $handoverIds)->whereNull('l.reverses_line_id')
            ->whereRaw('not exists (select 1 from service_handover_lines r where r.reverses_line_id = l.id)')
            ->join('project_lines as pl', 'pl.id', '=', 'l.project_line_id')->join('confirmation_line_pins as p', 'p.id', '=', 'pl.current_pin_id')->join('units as u', 'u.id', '=', 'l.entered_unit_id')
            ->orderBy('l.id')->get(['l.service_handover_id', 'p.description', 'l.entered_qty', 'u.code', 'l.scope_note']);

        foreach ($rows as $row) {
            $lines[(int) Row::text($row, 'service_handover_id')][] = [
                'description' => Row::text($row, 'description'),
                'quantity' => Row::text($row, 'entered_qty'),
                'unit' => Row::text($row, 'code'),
                'unit_price' => null,
                'amount' => null,
                'note' => Row::nullable($row, 'scope_note'),
            ];
        }

        $facts = [];
        $headers = DB::table('service_handovers as h')->whereIn('h.id', $handoverIds)->join('projects as p', 'p.id', '=', 'h.project_id')
            ->join('client_organizations as o', 'o.id', '=', 'p.client_organization_id')
            ->get(['h.id', 'h.business_date', 'h.recipient_name', 'p.project_number', 'p.title', 'o.name as client']);

        foreach ($headers as $header) {
            $id = (int) Row::text($header, 'id');
            $facts[$id] = new DocumentFacts(
                recipient: ['name' => Row::text($header, 'recipient_name'), 'lines' => [Row::text($header, 'client')]],
                references: [
                    ['label' => 'No. Proyek', 'value' => Row::text($header, 'project_number')],
                    ['label' => 'Klien', 'value' => Row::text($header, 'client')],
                    ['label' => 'Tanggal Serah Terima', 'value' => PaperFormat::date(Row::text($header, 'business_date'))],
                ],
                subject: Row::text($header, 'title'),
                lines: $lines[$id] ?? [],
            );
        }

        return $facts;
    }
}

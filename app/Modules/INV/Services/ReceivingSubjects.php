<?php

namespace App\Modules\INV\Services;

use App\Modules\DOC\Contracts\DocumentSubjectProvider;
use App\Modules\DOC\Data\DocumentFacts;
use App\Modules\DOC\Data\DocumentSubject;
use App\Modules\IAM\Data\Authority;
use App\Support\Command\CommandContext;
use App\Support\Command\LockSet;
use App\Support\Command\Refused;
use App\Support\Search\Row;
use Illuminate\Support\Facades\DB;

/**
 * INV's part of `DocumentSubjectProvider` (ARCHITECTURE §3; WF-DOC-01; BR-DOC-04; BR-XD-04): the receiving event as the subject of its Nota Terima Barang, DOC-12 — a committed receipt its precondition, never a fictitious one, the supplier's delivery reference printed, no price, tax or cost (PJ-18). Issuing it changes no stock.
 */
final class ReceivingSubjects implements DocumentSubjectProvider
{
    public const NOTE = 'DOC-12';

    public function types(): array
    {
        return [self::NOTE => ['STANDARD']];
    }

    public function subject(Authority $viewer, string $typeCode, string $mode, array $records): ?DocumentSubject
    {
        $publicId = $records['receiving_movement_id'] ?? null;
        $movement = is_string($publicId) && preg_match('/^[0-9a-f-]{36}$/i', $publicId) === 1 && count($records) === 1
            ? DB::table('stock_movements')->where('public_id', strtolower($publicId))->where('movement_type', 'RECEIPT')->first(['id', 'company_id']) : null;

        if ($movement === null || Row::nullable($movement, 'company_id') === null || ! $viewer->allows('documents.issue', (int) Row::text($movement, 'company_id'))) {
            return null;
        }

        return new DocumentSubject($typeCode, $mode, (int) Row::text($movement, 'company_id'), ['receiving_movement_id' => (int) Row::text($movement, 'id')]);
    }

    public function locks(DocumentSubject $subject): LockSet
    {
        $project = DB::table('stock_movements')->where('id', self::movement($subject))->value('project_id');

        return is_numeric($project) ? (new LockSet)->add('projects', ['id' => (int) $project]) : new LockSet;
    }

    public function precondition(CommandContext $context, DocumentSubject $subject): void
    {
        if (! DB::table('receiving_lines')->where('stock_movement_id', self::movement($subject))->exists()) {
            throw Refused::rejected('WF-DOC-01', ['reason' => 'no_subject']);
        }
    }

    public function facts(DocumentSubject $subject): DocumentFacts
    {
        $movement = DB::table('stock_movements as m')->join('purchases as x', 'x.id', '=', 'm.purchase_id')->leftJoin('projects as p', 'p.id', '=', 'm.project_id')->where('m.id', self::movement($subject))
            ->first(['m.supplier_delivery_reference', 'x.purchase_number', 'x.supplier_snapshot', 'p.project_number']);

        if ($movement === null) {
            throw Refused::rejected('WF-DOC-01', ['reason' => 'no_subject']);
        }

        $supplier = json_decode(Row::text($movement, 'supplier_snapshot'), true);
        $references = [['label' => 'No. Pembelian', 'value' => Row::text($movement, 'purchase_number')]];

        if (Row::nullable($movement, 'project_number') !== null) {
            $references[] = ['label' => 'No. Proyek', 'value' => Row::text($movement, 'project_number')];
        }

        if (Row::nullable($movement, 'supplier_delivery_reference') !== null) {
            $references[] = ['label' => 'Surat Jalan Pemasok', 'value' => Row::text($movement, 'supplier_delivery_reference')];
        }

        $lines = [];

        foreach (DB::table('receiving_lines as r')->join('purchase_lines as l', 'l.id', '=', 'r.purchase_line_id')->leftJoin('units as u', 'u.id', '=', 'r.entered_unit_id')->where('r.stock_movement_id', self::movement($subject))->orderBy('r.id')
            ->get(['l.description', 'r.entered_qty', 'u.code', 'r.damaged_qty_base', 'r.rejected_qty_base']) as $line) {
            $notes = array_filter([
                preg_match('/^0+(\.0+)?$/', Row::text($line, 'damaged_qty_base')) === 1 ? null : 'Rusak diterima: '.Row::text($line, 'damaged_qty_base'),
                preg_match('/^0+(\.0+)?$/', Row::text($line, 'rejected_qty_base')) === 1 ? null : 'Ditolak: '.Row::text($line, 'rejected_qty_base'),
            ]);
            $lines[] = ['description' => Row::text($line, 'description'), 'quantity' => Row::text($line, 'entered_qty'), 'unit' => Row::nullable($line, 'code'), 'unit_price' => null, 'amount' => null, 'note' => $notes === [] ? null : implode('; ', $notes)];
        }

        return new DocumentFacts(
            recipient: ['name' => is_array($supplier) && is_string($supplier['name'] ?? null) ? $supplier['name'] : '', 'lines' => []],
            references: $references,
            subject: 'Nota Terima Barang '.Row::text($movement, 'purchase_number'),
            lines: $lines,
        );
    }

    private static function movement(DocumentSubject $subject): int
    {
        return $subject->records['receiving_movement_id'] ?? throw Refused::rejected('WF-DOC-01', ['reason' => 'no_subject']);
    }
}

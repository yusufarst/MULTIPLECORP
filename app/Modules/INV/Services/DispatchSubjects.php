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
 * INV's part of `DocumentSubjectProvider` for the Surat Jalan, DOC-03 (ARCHITECTURE §3; WF-DOC-01; BR-DOC-04; L-19; V1_SCOPE DOC-03): the outbound shipment as its subject, a committed dispatch not wholly restored its precondition, its signed copy the delivery proof. Issuing it creates no second delivery and changes no stock; no price, tax or cost on it (PJ-15).
 */
final class DispatchSubjects implements DocumentSubjectProvider
{
    public const NOTE = 'DOC-03';

    public function types(): array
    {
        return [self::NOTE => ['STANDARD']];
    }

    public function subject(Authority $viewer, string $typeCode, string $mode, array $records): ?DocumentSubject
    {
        $publicId = $records['dispatch_movement_id'] ?? null;
        $movement = is_string($publicId) && preg_match('/^[0-9a-f-]{36}$/i', $publicId) === 1 && count($records) === 1
            ? DB::table('stock_movements')->where('public_id', strtolower($publicId))->where('movement_type', 'DISPATCH')->first(['id', 'company_id']) : null;

        if ($movement === null || Row::nullable($movement, 'company_id') === null || ! $viewer->allows('documents.issue', (int) Row::text($movement, 'company_id')) || self::restoredWhole((int) Row::text($movement, 'id'))) {
            return null;
        }

        return new DocumentSubject($typeCode, $mode, (int) Row::text($movement, 'company_id'), ['dispatch_movement_id' => (int) Row::text($movement, 'id')]);
    }

    public function locks(DocumentSubject $subject): LockSet
    {
        $project = DB::table('stock_movements')->where('id', self::movement($subject))->value('project_id');

        return is_numeric($project) ? (new LockSet)->add('projects', ['id' => (int) $project]) : new LockSet;
    }

    public function precondition(CommandContext $context, DocumentSubject $subject): void
    {
        if (! DB::table('dispatch_lines')->where('stock_movement_id', self::movement($subject))->exists()) {
            throw Refused::rejected('WF-DOC-01', ['reason' => 'no_subject']);
        }

        if (self::restoredWhole(self::movement($subject))) {
            throw Refused::rejected('WF-DOC-01', ['reason' => 'reversed']);
        }
    }

    public function facts(DocumentSubject $subject): DocumentFacts
    {
        $movement = DB::table('stock_movements as m')->join('projects as p', 'p.id', '=', 'm.project_id')->leftJoin('client_organizations as o', 'o.id', '=', 'p.client_organization_id')->where('m.id', self::movement($subject))
            ->first(['p.project_number', 'o.name as client']);

        if ($movement === null) {
            throw Refused::rejected('WF-DOC-01', ['reason' => 'no_subject']);
        }

        $lines = [];

        foreach (DB::table('dispatch_lines as d')->join('project_lines as l', 'l.id', '=', 'd.project_line_id')->leftJoin('units as u', 'u.id', '=', 'd.entered_unit_id')->where('d.stock_movement_id', self::movement($subject))->orderBy('d.id')
            ->get(['d.id', 'l.description', 'd.entered_qty', 'u.code']) as $line) {
            $serials = DB::table('stock_consumptions as c')->join('stock_movement_entries as e', 'e.stock_consumption_id', '=', 'c.id')->join('serial_units as s', 's.id', '=', 'e.serial_unit_id')->where('c.dispatch_line_id', (int) Row::text($line, 'id'))->orderBy('s.serial_number')->pluck('s.serial_number')->filter(is_string(...))->all();
            $lines[] = ['description' => Row::text($line, 'description'), 'quantity' => Row::text($line, 'entered_qty'), 'unit' => Row::nullable($line, 'code'), 'unit_price' => null, 'amount' => null, 'note' => $serials === [] ? null : 'No. Seri: '.implode(', ', $serials)];
        }

        return new DocumentFacts(
            recipient: ['name' => Row::nullable($movement, 'client') ?? '', 'lines' => []],
            references: [['label' => 'No. Proyek', 'value' => Row::text($movement, 'project_number')]],
            subject: 'Surat Jalan '.Row::text($movement, 'project_number'),
            lines: $lines,
        );
    }

    /** A dispatch wholly restored left nothing to ship: its Surat Jalan would be fictitious (BR-DOC-04). */
    private static function restoredWhole(int $movementId): bool
    {
        return ! DB::table('stock_consumptions as c')->join('stock_consumption_balances as b', 'b.stock_consumption_id', '=', 'c.id')->join('dispatch_lines as d', 'd.id', '=', 'c.dispatch_line_id')->where('d.stock_movement_id', $movementId)
            ->whereRaw('b.qty_base - b.restored_qty_base > 0')->exists();
    }

    private static function movement(DocumentSubject $subject): int
    {
        return $subject->records['dispatch_movement_id'] ?? throw Refused::rejected('WF-DOC-01', ['reason' => 'no_subject']);
    }
}

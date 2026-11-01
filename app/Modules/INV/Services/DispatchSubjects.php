<?php

namespace App\Modules\INV\Services;

use App\Modules\DOC\Contracts\DocumentSubjectProvider;
use App\Modules\DOC\Data\DocumentFacts;
use App\Modules\DOC\Data\DocumentSubject;
use App\Modules\IAM\Data\Authority;
use App\Modules\INV\Data\StockFigures;
use App\Support\Command\CommandContext;
use App\Support\Command\LockSet;
use App\Support\Command\Refused;
use App\Support\Search\Row;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;

/**
 * INV's part of `DocumentSubjectProvider` for the Surat Jalan, DOC-03 (ARCHITECTURE §3; WF-DOC-01; BR-DOC-04; L-19; V1_SCOPE DOC-03): the outbound shipment as its subject, a committed dispatch not wholly restored its precondition, its signed copy the delivery proof. Issuing it creates no second delivery and changes no stock; no price, tax or cost on it (PJ-15).
 */
final class DispatchSubjects implements DocumentSubjectProvider
{
    public const NOTE = 'DOC-03';

    /** What of the dispatch line is still out: a restoration, reclassification or substitution leaves it, a substitution's new consumption comes on the same line. */
    private const OPEN = '(select coalesce(sum(b.qty_base - b.restored_qty_base - b.reclassified_qty_base - b.substituted_qty_base), 0) from stock_consumptions c join stock_consumption_balances b on b.stock_consumption_id = c.id where c.dispatch_line_id = d.id) as open_qty';

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

        foreach (DB::table('dispatch_lines as d')->join('project_lines as l', 'l.id', '=', 'd.project_line_id')->join('products as p', 'p.id', '=', 'd.product_id')->leftJoin('units as u', 'u.id', '=', 'd.entered_unit_id')->leftJoin('units as base', 'base.id', '=', 'p.base_unit_id')->where('d.stock_movement_id', self::movement($subject))->orderBy('d.id')
            ->get(['d.id', 'l.description', 'd.entered_qty', 'd.qty_base', 'd.ratio_to_base', 'u.code', 'base.code as base_code', DB::raw(self::OPEN)]) as $line) {
            $open = BigDecimal::of(Row::text($line, 'open_qty'));

            if (! $open->isPositive()) {
                continue;
            }

            // Only the serials whose last movement left on this line: one restored, or substituted away, is no longer on the shipment.
            $serials = DB::table('stock_consumptions as c')->join('stock_movement_entries as e', 'e.stock_consumption_id', '=', 'c.id')->join('serial_units as s', 's.last_entry_id', '=', 'e.id')->where('s.state', 'DISPATCHED')->where('c.dispatch_line_id', (int) Row::text($line, 'id'))->orderBy('s.serial_number')->pluck('s.serial_number')->filter(fn (mixed $number): bool => is_string($number))->values()->all();
            [$quantity, $unit] = self::netOf($line, $open);
            $lines[] = ['description' => Row::text($line, 'description'), 'quantity' => $quantity, 'unit' => $unit, 'unit_price' => null, 'amount' => null, 'note' => $serials === [] ? null : 'No. Seri: '.implode(', ', $serials)];
        }

        return new DocumentFacts(
            recipient: ['name' => Row::nullable($movement, 'client') ?? '', 'lines' => []],
            references: [['label' => 'No. Proyek', 'value' => Row::text($movement, 'project_number')]],
            subject: 'Surat Jalan '.Row::text($movement, 'project_number'),
            lines: $lines,
        );
    }

    /**
     * The net quantity in the dispatch's own unit when it divides, otherwise in the base unit.
     *
     * @return array{string, ?string}
     */
    private static function netOf(object $line, BigDecimal $open): array
    {
        $ratio = max(1, (int) Row::text($line, 'ratio_to_base'));

        if ($open->isEqualTo(Row::text($line, 'qty_base'))) {
            return [StockFigures::plain(Row::text($line, 'entered_qty')), Row::nullable($line, 'code')];
        }

        return $open->remainder($ratio)->isZero() ? [StockFigures::hint((string) $open, $ratio), Row::nullable($line, 'code')] : [StockFigures::plain((string) $open), Row::nullable($line, 'base_code')];
    }

    /** A dispatch wholly restored left nothing to ship: its Surat Jalan would be fictitious (BR-DOC-04). */
    private static function restoredWhole(int $movementId): bool
    {
        return ! DB::table('stock_consumptions as c')->join('stock_consumption_balances as b', 'b.stock_consumption_id', '=', 'c.id')->join('dispatch_lines as d', 'd.id', '=', 'c.dispatch_line_id')->where('d.stock_movement_id', $movementId)
            ->whereRaw('b.qty_base - b.restored_qty_base - b.reclassified_qty_base - b.substituted_qty_base > 0')->exists();
    }

    private static function movement(DocumentSubject $subject): int
    {
        return $subject->records['dispatch_movement_id'] ?? throw Refused::rejected('WF-DOC-01', ['reason' => 'no_subject']);
    }
}

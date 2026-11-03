<?php

namespace App\Modules\FUL\Services;

use App\Modules\DOC\Contracts\DocumentSubjectProvider;
use App\Modules\DOC\Data\DocumentFacts;
use App\Modules\DOC\Data\DocumentSubject;
use App\Modules\IAM\Data\Authority;
use App\Modules\INV\Data\StockFigures;
use App\Modules\INV\Services\DispatchSubjects;
use App\Support\Command\CommandContext;
use App\Support\Command\LockSet;
use App\Support\Command\Refused;
use App\Support\Search\Row;
use Brick\Math\BigDecimal;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

/**
 * The *Surat Jalan*, DOC-03 (WF-FUL-03; WF-DOC-01; BR-DOC-04; L-19; `DIR-100`): a warehouse dispatch's, INV's
 * {@see DispatchSubjects}, or a confirmed direct shipment's — the company's own paper for what the supplier shipped
 * straight to the client, its subject the drop-ship confirmation, a confirmation with a line still standing its
 * precondition, its facts the confirmation's recorded lines, serials and the supplier's reference. A type and mode have
 * one supplier, so the direct shipment's arc of the type lives here, above INV (ARCHITECTURE §3). Issuing it changes no
 * stock, cash or obligation; no price, tax or cost on it (PJ-15).
 */
final class ShipmentNoteSubjects implements DocumentSubjectProvider
{
    public function __construct(private readonly DispatchSubjects $dispatches) {}

    public function types(): array
    {
        return $this->dispatches->types();
    }

    public function subject(Authority $viewer, string $typeCode, string $mode, array $records): ?DocumentSubject
    {
        if (! isset($records['dropship_confirmation_id'])) {
            return $this->dispatches->subject($viewer, $typeCode, $mode, $records);
        }

        $publicId = $records['dropship_confirmation_id'];
        $row = preg_match('/^[0-9a-f-]{36}$/i', $publicId) === 1 && count($records) === 1
            ? DB::table('dropship_confirmations')->where('public_id', strtolower($publicId))->first(['id', 'company_id']) : null;

        if ($row === null || ! $viewer->allows('documents.issue', (int) Row::text($row, 'company_id')) || ! self::standing((int) Row::text($row, 'id'))) {
            return null;
        }

        return new DocumentSubject($typeCode, $mode, (int) Row::text($row, 'company_id'), ['dropship_confirmation_id' => (int) Row::text($row, 'id')]);
    }

    public function locks(DocumentSubject $subject): LockSet
    {
        if (! isset($subject->records['dropship_confirmation_id'])) {
            return $this->dispatches->locks($subject);
        }

        $project = DB::table('dropship_confirmations')->where('id', self::confirmation($subject))->value('project_id');

        return is_numeric($project) ? (new LockSet)->add('projects', ['id' => (int) $project]) : new LockSet;
    }

    /** A confirmation wholly reversed or returned leaves nothing shipped: its *Surat Jalan* would be fictitious (BR-DOC-04). */
    public function precondition(CommandContext $context, DocumentSubject $subject): void
    {
        if (! isset($subject->records['dropship_confirmation_id'])) {
            $this->dispatches->precondition($context, $subject);

            return;
        }

        if (! self::standing(self::confirmation($subject))) {
            throw Refused::rejected('WF-DOC-01', ['reason' => 'reversed']);
        }
    }

    public function facts(DocumentSubject $subject): DocumentFacts
    {
        if (! isset($subject->records['dropship_confirmation_id'])) {
            return $this->dispatches->facts($subject);
        }

        $header = DB::table('dropship_confirmations as c')->join('projects as p', 'p.id', '=', 'c.project_id')->leftJoin('client_organizations as o', 'o.id', '=', 'p.client_organization_id')
            ->where('c.id', self::confirmation($subject))->first(['p.project_number', 'o.name as client', 'c.supplier_shipment_reference']);

        if ($header === null) {
            throw Refused::rejected('WF-DOC-01', ['reason' => 'no_subject']);
        }

        $lines = [];

        // One paper line per demand line: its standing quantity, the serials still drop-shipped on it.
        foreach (DB::table('dropship_confirmation_lines as d')->join('stock_consumption_balances as b', 'b.dropship_confirmation_line_id', '=', 'd.id')->join('project_lines as l', 'l.id', '=', 'd.project_line_id')
            ->join('products as p', 'p.id', '=', 'd.product_id')->leftJoin('units as base', 'base.id', '=', 'p.base_unit_id')->leftJoin('serial_units as s', fn (JoinClause $join): JoinClause => $join->on('s.id', '=', 'd.serial_unit_id')->where('s.state', 'DROPSHIPPED'))
            ->where('d.dropship_confirmation_id', self::confirmation($subject))->groupBy('l.id', 'l.line_no', 'l.description', 'base.code')->orderBy('l.line_no')
            ->get(['l.description', 'base.code as unit', DB::raw('sum(b.qty_base - b.restored_qty_base - b.reclassified_qty_base - b.substituted_qty_base) as open_qty'), DB::raw("string_agg(s.serial_number, ', ' order by s.serial_number) as serials")]) as $line) {
            if (! BigDecimal::of(Row::text($line, 'open_qty'))->isPositive()) {
                continue;
            }

            $serials = Row::nullable($line, 'serials');
            $lines[] = ['description' => Row::text($line, 'description'), 'quantity' => StockFigures::plain(Row::text($line, 'open_qty')), 'unit' => Row::nullable($line, 'unit'), 'unit_price' => null, 'amount' => null,
                'note' => $serials === null ? null : 'No. Seri: '.$serials];
        }

        return new DocumentFacts(
            recipient: ['name' => Row::nullable($header, 'client') ?? '', 'lines' => []],
            references: [['label' => 'No. Proyek', 'value' => Row::text($header, 'project_number')], ['label' => 'Ref. Pengiriman Pemasok', 'value' => Row::text($header, 'supplier_shipment_reference')]],
            subject: 'Surat Jalan '.Row::text($header, 'project_number'),
            lines: $lines,
        );
    }

    private static function standing(int $confirmationId): bool
    {
        return DB::table('dropship_confirmation_lines as d')->join('stock_consumption_balances as b', 'b.dropship_confirmation_line_id', '=', 'd.id')->where('d.dropship_confirmation_id', $confirmationId)
            ->whereRaw('b.qty_base - b.restored_qty_base - b.reclassified_qty_base - b.substituted_qty_base > 0')->exists();
    }

    private static function confirmation(DocumentSubject $subject): int
    {
        return $subject->records['dropship_confirmation_id'] ?? throw Refused::rejected('WF-DOC-01', ['reason' => 'no_subject']);
    }
}

<?php

namespace App\Modules\PUR\Services;

use App\Modules\DOC\Contracts\DocumentSubjectProvider;
use App\Modules\DOC\Data\DocumentFacts;
use App\Modules\DOC\Data\DocumentSubject;
use App\Modules\IAM\Data\Authority;
use App\Support\Command\CommandContext;
use App\Support\Command\LockSet;
use App\Support\Command\Refused;
use App\Support\Money;
use App\Support\Search\Row;
use Illuminate\Support\Facades\DB;

/**
 * PUR's part of `DocumentSubjectProvider` (ARCHITECTURE §3; BR-DOC-04; L-19; L-26; WF-DOC-01): the purchase as the subject of its optional PO, DOC-02, and of its company-issued Surat Pesanan, DOC-14 — two layouts of one record, each with its own type number, the precondition only that the purchase stands. The PO is never a precondition of anything, and issuing either changes no stock, cash or obligation (BR-XD-04). `cost.view` of the purchase's company (PJ-15).
 */
final class PurchaseSubjects implements DocumentSubjectProvider
{
    public const PO = 'DOC-02';

    public const ORDER = 'DOC-14';

    public function types(): array
    {
        return [self::PO => ['STANDARD'], self::ORDER => ['STANDARD']];
    }

    public function subject(Authority $viewer, string $typeCode, string $mode, array $records): ?DocumentSubject
    {
        $publicId = $records['purchase_id'] ?? null;
        $purchase = is_string($publicId) && preg_match('/^[0-9a-f-]{36}$/i', $publicId) === 1 && count($records) === 1
            ? DB::table('purchases')->where('public_id', strtolower($publicId))->first(['id', 'company_id']) : null;

        if ($purchase === null || ! $viewer->allows('cost.view', (int) Row::text($purchase, 'company_id'))) {
            return null;
        }

        return new DocumentSubject($typeCode, $mode, (int) Row::text($purchase, 'company_id'), ['purchase_id' => (int) Row::text($purchase, 'id')]);
    }

    public function locks(DocumentSubject $subject): LockSet
    {
        $purchase = self::purchase($subject);
        $project = DB::table('purchases')->where('id', $purchase)->value('project_id');
        $set = (new LockSet)->add('purchases', ['id' => $purchase]);

        return is_numeric($project) ? $set->add('projects', ['id' => (int) $project]) : $set;
    }

    public function precondition(CommandContext $context, DocumentSubject $subject): void
    {
        if (DB::table('purchases')->where('id', self::purchase($subject))->value('state') === 'CANCELLED') {
            throw Refused::rejected('WF-DOC-01', ['reason' => 'no_subject']);
        }
    }

    public function facts(DocumentSubject $subject): DocumentFacts
    {
        $purchase = DB::table('purchases as x')->leftJoin('projects as p', 'p.id', '=', 'x.project_id')->where('x.id', self::purchase($subject))->first(['x.id', 'x.purchase_number', 'x.supplier_snapshot', 'x.supplier_reference', 'x.business_date', 'p.project_number', 'p.title']);

        if ($purchase === null) {
            throw Refused::rejected('WF-DOC-01', ['reason' => 'no_subject']);
        }

        $supplier = json_decode(Row::text($purchase, 'supplier_snapshot'), true);
        $supplier = is_array($supplier) ? $supplier : [];
        $references = [['label' => 'No. Pembelian', 'value' => Row::text($purchase, 'purchase_number')]];

        if (Row::nullable($purchase, 'project_number') !== null) {
            $references[] = ['label' => 'No. Proyek', 'value' => Row::text($purchase, 'project_number')];
        }

        if (Row::nullable($purchase, 'supplier_reference') !== null) {
            $references[] = ['label' => 'Referensi Pemasok', 'value' => Row::text($purchase, 'supplier_reference')];
        }

        $lines = [];
        $total = Money::zero();

        foreach (DB::table('purchase_lines as l')->leftJoin('units as u', 'u.id', '=', 'l.entered_unit_id')->where('l.purchase_id', Row::text($purchase, 'id'))->orderBy('l.line_no')->get(['l.description', 'l.entered_qty', 'u.code', 'l.unit_price', 'l.line_value']) as $line) {
            $lines[] = ['description' => Row::text($line, 'description'), 'quantity' => Row::text($line, 'entered_qty'), 'unit' => Row::nullable($line, 'code'), 'unit_price' => Row::text($line, 'unit_price'), 'amount' => Row::text($line, 'line_value'), 'note' => null];
            $total = $total->plus(Money::of(Row::text($line, 'line_value')));
        }

        return new DocumentFacts(
            recipient: ['name' => is_string($supplier['name'] ?? null) ? $supplier['name'] : '', 'lines' => array_values(array_filter([$supplier['address'] ?? null, $supplier['city'] ?? null, is_string($supplier['npwp'] ?? null) ? 'NPWP '.$supplier['npwp'] : null], fn (mixed $line): bool => is_string($line) && $line !== ''))],
            references: $references,
            subject: $subject->typeCode === self::PO ? 'Pesanan Pembelian '.Row::text($purchase, 'purchase_number') : 'Surat Pesanan '.Row::text($purchase, 'purchase_number'),
            lines: $lines,
            totals: [['label' => 'Total', 'amount' => $total->toString(), 'grand' => true]],
        );
    }

    private static function purchase(DocumentSubject $subject): int
    {
        return $subject->records['purchase_id'] ?? throw Refused::rejected('WF-DOC-01', ['reason' => 'no_subject']);
    }
}

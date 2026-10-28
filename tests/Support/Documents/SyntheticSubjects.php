<?php

namespace Tests\Support\Documents;

use App\Modules\DOC\Contracts\DocumentSubjectProvider;
use App\Modules\DOC\Data\DocumentFacts;
use App\Modules\DOC\Data\DocumentSubject;
use App\Modules\IAM\Data\Authority;
use App\Support\Command\CommandContext;
use App\Support\Command\LockSet;
use App\Support\Command\Refused;
use Illuminate\Support\Facades\DB;

/**
 * A synthetic subject for every layout until its issuing Task supplies the real one (TASK-029 tool requirements): the
 * subject's records named by public id in the arc's own tables, the record's company the document's, LK-01's project,
 * LK-21's purchase or LK-25's invoice locked as the issuing modules will, a precondition a test can make refuse, and
 * fixed facts — or the facts a test sets — so a draft composed and then issued can be compared. Test and local only.
 */
final class SyntheticSubjects implements DocumentSubjectProvider
{
    /** The types a module's own provider supplies (QuotationSubjects). */
    public const SUPPLIED = ['DOC-01', 'DOC-11'];

    private const TABLES = [
        'project_id' => 'projects', 'purchase_id' => 'purchases', 'dispatch_movement_id' => 'stock_movements', 'dropship_confirmation_id' => 'dropship_confirmations',
        'invoice_id' => 'invoices', 'payment_id' => 'payments', 'parent_document_id' => 'documents', 'delivery_id' => 'deliveries',
        'service_handover_id' => 'service_handovers', 'receiving_movement_id' => 'stock_movements',
    ];

    /** The rule the next preconditions refuse with, or null to allow them. */
    public static ?string $refusal = null;

    /** The facts the next papers print, or null for the sample. */
    public static ?DocumentFacts $facts = null;

    /** How many preconditions ran, so a replay can be shown to run none. */
    public static int $preconditions = 0;

    /** Registers the provider for every type once per application, its state cleared. */
    public static function register(): void
    {
        self::$refusal = null;
        self::$facts = null;
        self::$preconditions = 0;

        foreach (app()->tagged(DocumentSubjectProvider::TAG) as $provider) {
            if ($provider instanceof self) {
                return;
            }
        }

        app()->tag([self::class], DocumentSubjectProvider::TAG);
    }

    public function types(): array
    {
        $types = [];

        foreach (range(1, 14) as $number) {
            $type = sprintf('DOC-%02d', $number);

            // The types an issuing module already supplies stay its own: PRJ's quotation and HPS (TASK-033).
            if (! in_array($type, self::SUPPLIED, true)) {
                $types[$type] = $number === 5 ? ['RECEIPT', 'PRE_PAYMENT'] : ['STANDARD'];
            }
        }

        return $types;
    }

    public function subject(Authority $viewer, string $typeCode, string $mode, array $records): ?DocumentSubject
    {
        $ids = [];
        $company = null;

        foreach ($records as $column => $publicId) {
            $table = self::TABLES[$column] ?? null;
            $row = $table === null ? null : DB::table($table)->where('public_id', $publicId)->first();

            if (! is_object($row)) {
                return null;
            }

            $ids[$column] = (int) $row->id;
            $company ??= is_numeric($row->company_id ?? null) ? (int) $row->company_id : null;
        }

        return $company === null || ! $viewer->allows('projects.view', $company) ? null : new DocumentSubject($typeCode, $mode, $company, $ids);
    }

    public function locks(DocumentSubject $subject): LockSet
    {
        $set = new LockSet;

        foreach (['project_id' => 'projects', 'purchase_id' => 'purchases', 'invoice_id' => 'invoices'] as $column => $table) {
            if (isset($subject->records[$column])) {
                $set = $set->add($table, ['id' => $subject->records[$column]]);
            }
        }

        return $set;
    }

    public function precondition(CommandContext $context, DocumentSubject $subject): void
    {
        self::$preconditions++;

        if (self::$refusal !== null) {
            throw Refused::rejected(self::$refusal, ['reason' => 'synthetic']);
        }
    }

    public function facts(DocumentSubject $subject): DocumentFacts
    {
        return self::$facts ?? self::sample($subject->typeCode);
    }

    /** The sample facts of a layout: an addressed party, a reference, two lines with the record's totals. */
    public static function sample(string $typeCode): DocumentFacts
    {
        $priced = ! in_array($typeCode, ['DOC-03', 'DOC-08', 'DOC-10', 'DOC-12'], true);

        return new DocumentFacts(
            recipient: ['name' => 'SMA Negeri 1 Contoh', 'lines' => ['Jl. Contoh Raya No. 1', 'Yogyakarta']],
            references: [['label' => 'No. Proyek', 'value' => 'CTH/PRJ/2026/0001']],
            subject: 'Pengadaan perangkat laboratorium',
            lines: [
                ['description' => 'Laptop 14 inci', 'quantity' => '2', 'unit' => 'UNIT', 'unit_price' => $priced ? '7500000.00' : null, 'amount' => $priced ? '15000000.00' : null, 'note' => null],
                ['description' => 'Proyektor', 'quantity' => '1', 'unit' => 'UNIT', 'unit_price' => $priced ? '1500000.00' : null, 'amount' => $priced ? '1500000.00' : null, 'note' => $priced ? null : 'Baik'],
            ],
            totals: $priced ? [
                ['label' => 'Subtotal', 'amount' => '16500000.00', 'grand' => false],
                ['label' => 'PPN 11%', 'amount' => '1815000.00', 'grand' => false],
                ['label' => 'Total', 'amount' => '18315000.00', 'grand' => true],
            ] : [],
            amount: in_array($typeCode, ['DOC-05', 'DOC-13'], true) ? '18315000.00' : null,
            parentNumber: $typeCode === 'DOC-07' ? 'CTH/KWT/2026/0001' : null,
            kuitansiAmount: $typeCode === 'DOC-05' ? '18315000.00' : null,
        );
    }
}

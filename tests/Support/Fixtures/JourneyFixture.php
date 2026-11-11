<?php

namespace Tests\Support\Fixtures;

use App\Modules\ORG\Enums\SequenceType;
use App\Modules\ORG\Models\Company;
use App\Support\BusinessDate;
use App\Support\Search\Row;
use Carbon\CarbonImmutable;
use Database\Factories\DOC\DocumentTypeFactory;
use Database\Factories\DOC\EvidenceDocumentFactory;
use Database\Factories\INV\LocationFactory;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\Numbering;
use Tests\Support\PaymentWorld;
use Tests\Support\Projects;
use Tests\Support\PurchaseWorld;
use Tests\Support\StockWorld;

/**
 * TASK-062's journey fixture for CJ-01 (TL-09; TEST_STRATEGY §7; routes/fixtures.php): for the run's company — whose go-live the
 * numbering fixture armed —, the numbering of every document the golden flow issues started before this year with their layouts, and
 * the run's master data only: a client with its unit, address and PIC, a supplier, a product sold at Rp250,000 — stocked when asked,
 * from a replenishment received through ROUTE-181's own action before the journey starts —, a rack, the company's bank account, and the
 * papers that reach the company from outside (the supplier's shipment note, the client's signed receipts, two transfer proofs). Every
 * business record of the journey — project, quotation, approval, purchase, receipt, reservation, dispatch, delivery, invoice, billing,
 * payments, completion — is made on its pages. `trace` reads, read-only, what exists for the journey's company, product and project, so
 * a path that needs no purchase, receipt or warehouse movement is shown to have created none (AC-20; BR-XD-02; GAP-021). Local only;
 * synthetic names.
 */
final class JourneyFixture
{
    /** The flow's numbered documents and their templates' prefixes: project, quotation, purchase, PO, supplier order, Nota, Surat Jalan, BAST, the invoice and its layouts. */
    private const NUMBERING = [
        [SequenceType::Project, 'PRJ'], [SequenceType::Doc01, 'PNW'], [SequenceType::Purchase, 'BELI'], [SequenceType::Doc02, 'PO'],
        [SequenceType::Doc14, 'SP'], [SequenceType::Doc12, 'NTB'], [SequenceType::Doc03, 'SJ'], [SequenceType::Doc08, 'BAST'],
        [SequenceType::Doc06, 'INV'], [SequenceType::Doc04, 'NOTA'], [SequenceType::Doc05, 'KW'], [SequenceType::Doc07, 'LMP'],
        [SequenceType::Doc13, 'SPP'],
    ];

    private const LAYOUTS = ['DOC-01', 'DOC-02', 'DOC-03', 'DOC-04', 'DOC-05', 'DOC-06', 'DOC-07', 'DOC-08', 'DOC-10', 'DOC-12', 'DOC-13', 'DOC-14'];

    public static function create(Request $request): JsonResponse
    {
        $company = Company::query()->where('public_id', (string) $request->input('company'))->firstOrFail();
        $actor = $request->user()?->getAuthIdentifier();
        $actor = is_int($actor) ? $actor : 0;
        $year = substr(BusinessDate::today(), 0, 4);
        Numbering::goLive("{$year}-01-01");
        NumberingFixture::arm(Request::create('/', 'POST', ['go_live' => "{$year}-01-01"]));
        $start = CarbonImmutable::parse(((int) $year - 1).'-12-31 03:00:00', 'UTC');

        foreach (self::NUMBERING as [$type, $prefix]) {
            Numbering::scheme($company, $type, "{$year}-01-01", 'YEAR', "UJI/{$prefix}/{tahun}/{nomor:4}");
            Numbering::started($company, $type, $start);
        }

        foreach (self::LAYOUTS as $layout) {
            DocumentTypeFactory::ensure($layout);
        }

        $tag = Str::upper(Str::random(5));
        $client = Projects::client();
        $client['organization']->forceFill(['name' => 'SMA Jurnal '.$company->code])->save();
        $supplier = PurchaseWorld::supplier();
        $supplier->forceFill(['name' => "Pemasok Jurnal {$tag}"])->save();
        $goods = Projects::product('GOODS', '250000.00');
        // Named after every "Barang …" and "Buku …" of the other fixtures: PG-018's first page, alphabetical, stays theirs.
        $goods['product']->forceFill(['name' => "Produk Jurnal {$tag}"])->save();
        $rack = LocationFactory::new()->createOne(['code' => "RAK-JRN-{$tag}", 'name' => "Rak Jurnal {$tag}"]);
        $stock = $request->input('stock');

        // The project served from stock: its units on hand before the journey, from a replenishment of the company — no project's purchase.
        if (is_string($stock) && preg_match('/^[1-9]\d{0,3}$/', $stock) === 1) {
            Authorization::forget();
            StockWorld::lot($actor, $company, (int) $goods['product']->id, (int) $goods['base']->id, (int) $rack->id, $stock, '150000');
        }

        PaymentWorld::account($company->id);
        $today = BusinessDate::today();
        $paper = function (string $type, string $issuer, string $reference) use ($company, $today): string {
            return (string) EvidenceDocumentFactory::new()->createOne([
                'company_id' => $company->id, 'evidence_scope' => 'COMPANY', 'evidence_type' => $type, 'declared_issuer' => $issuer,
                'issuer_reference' => $reference, 'document_date' => $today, 'identity_key' => Str::lower("{$issuer}|{$type}|{$reference}|{$today}"),
            ])->public_id;
        };
        $transfers = ["Bank Klien {$tag} transfer 1", "Bank Klien {$tag} transfer 2"];

        foreach ($transfers as $index => $issuer) {
            $paper('PAYMENT_PROOF', $issuer, "TRF-{$tag}-".($index + 1));
        }

        return response()->json([
            'code' => $company->code,
            'client' => $client['organization']->name,
            'unit' => $client['unit']->name,
            'supplier' => $supplier->name,
            'product' => $goods['product']->name,
            'product_id' => $goods['product']->public_id,
            'rack' => $rack->code,
            'delivery_proof' => $paper('DELIVERY_PROOF', "Tanda terima gudang {$tag}", "TT-{$tag}-1"),
            'supplier_proof' => $paper('DELIVERY_PROOF', "Surat jalan pemasok {$tag}", "SJ-PMS-{$tag}"),
            'client_proof' => $paper('DELIVERY_PROOF', "Tanda terima klien {$tag}", "TT-{$tag}-2"),
            'payment_proofs' => $transfers,
        ]);
    }

    /**
     * What exists for the journey, read-only: the project's purchases and receipts, the company's, and the product's stock movements by
     * type — the product is the run's own, so each movement is one the journey (or the fixture's stocking) made.
     */
    public static function trace(Request $request): JsonResponse
    {
        $company = Company::query()->where('public_id', strtolower((string) $request->query('perusahaan', '')))->first();
        $product = DB::table('products')->where('public_id', strtolower((string) $request->query('produk', '')))->value('id');

        if ($company === null || ! is_numeric($product)) {
            return response()->json(['message' => 'The journey names its company and product by their public identifiers.'], 422);
        }

        $asked = strtolower((string) $request->query('proyek', ''));
        $project = preg_match('/^[0-9a-f-]{36}$/', $asked) === 1 ? DB::table('projects')->where('company_id', $company->id)->where('public_id', $asked)->value('id') : null;
        $project = is_numeric($project) ? (int) $project : 0;
        $movements = [];

        foreach (DB::table('stock_movements as m')
            ->whereExists(fn (Builder $entries) => $entries->selectRaw('1')->from('stock_movement_entries as e')->whereColumn('e.stock_movement_id', 'm.id')->where('e.product_id', (int) $product))
            ->groupBy('m.movement_type')->orderBy('m.movement_type')->selectRaw('m.movement_type, count(*) as moved')->get() as $row) {
            $movements[Row::text($row, 'movement_type')] = (int) Row::text($row, 'moved');
        }

        return response()->json([
            'project_purchases' => DB::table('purchases')->where('project_id', $project)->count(),
            'project_receipts' => DB::table('receiving_lines as r')->join('purchase_lines as l', 'l.id', '=', 'r.purchase_line_id')->join('purchases as p', 'p.id', '=', 'l.purchase_id')->where('p.project_id', $project)->count(),
            'company_purchases' => DB::table('purchases')->where('company_id', $company->id)->count(),
            'company_receipts' => DB::table('receiving_lines')->where('company_id', $company->id)->count(),
            'movements' => (object) $movements,
        ]);
    }
}

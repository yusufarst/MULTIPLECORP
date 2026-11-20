<?php

namespace Tests\Support;

use App\Modules\DOC\Jobs\ProcessImage;
use App\Modules\DOC\Models\DocumentRendition;
use App\Modules\DOC\Models\EvidenceDocument;
use App\Modules\DOC\Models\EvidenceDocumentVersion;
use App\Modules\DOC\Models\FileObject;
use App\Modules\DOC\Services\FileStore;
use App\Modules\IAM\Models\User;
use App\Modules\Reporting\Exports\GenerateExport;
use Closure;
use Database\Factories\DOC\DocumentTypeFactory;
use Database\Factories\DOC\FileObjectFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;
use Throwable;

/**
 * The records the route crawl visits (TASK-069; TEST_STRATEGY §8): one of each family a page or a command
 * addresses — a confirmed project with an issued invoice and its document, a dispatched warehouse project with its
 * movement and product, a purchase, evidence with its file and an image, a rendition, a generated export, an Admin,
 * the company, a client, a supplier and an import batch —, built through the suites' own worlds. A part that cannot
 * be built is reported, never skipped silently.
 */
final class CrawlWorld
{
    /**
     * The record each address names, by the pattern of its route's URI.
     *
     * @var array<string, string>
     */
    private const ADDRESSES = [
        '#^proyek/\{id\}#' => 'project',
        '#^keuangan/kredit-pelanggan/proyek/\{id\}#' => 'project',
        '#^pembelian/\{id\}#' => 'purchase',
        '#^gudang/barang-masuk/\{id\}(/terima)?$#' => 'purchase',
        '#^keuangan/invoice/\{id\}#' => 'invoice',
        '#^dokumen/\{id\}#' => 'document',
        '#^gudang/barang-keluar/\{id\}(/keluarkan)?$#' => 'dispatch_project',
        '#^gudang/barang-keluar/\{id\}/(balikkan|ganti-identitas)$#' => 'movement',
        '#^gudang/pengiriman/\{id\}(/catat|/koreksi|/tutup)?$#' => 'movement',
        '#^gudang/stok/\{id\}#' => 'product',
        '#^data-master/produk/\{id\}#' => 'product',
        '#^berkas/\{id\}/unduh$#' => 'file',
        '#^berkas/\{id\}/gambar-kecil$#' => 'image',
        '#^berkas/\{id\}/tampil$#' => 'rendition',
        '#^laporan/ekspor/\{id\}/unduh$#' => 'export',
        '#^status/\{kind\}/\{id\}$#' => 'export',
        '#^bukti/\{id\}#' => 'evidence',
        '#^pengaturan/pengguna/\{id\}#' => 'user',
        '#^pengaturan/perusahaan/\{id\}#' => 'company',
        '#^data-master/klien/\{id\}#' => 'client',
        '#^data-master/pemasok/\{id\}#' => 'supplier',
        '#^data-master/impor/\{id\}#' => 'batch',
    ];

    /** @var array<string, string> */
    public array $records = [];

    /** @var list<string> */
    public array $failures = [];

    /**
     * The query a page needs beside its address, by route name.
     *
     * @var array<string, string>
     */
    public array $queries = [];

    /**
     * The submission a command needs before its fields are read, by route name — the report an export names.
     *
     * @var array<string, array<string, string>>
     */
    public array $bodies = ['reporting.exports.request' => ['laporan' => 'penjualan']];

    public static function build(TestCase $test, User $owner): self
    {
        $built = new self;
        Storage::fake('files');
        Storage::fake('local');
        Queue::fake();

        foreach (range(1, 14) as $number) {
            DocumentTypeFactory::ensure(sprintf('DOC-%02d', $number));
        }

        $world = InvoiceWorld::make();
        $company = $world->company;
        $project = $world->project();
        $built->records['company'] = (string) $company->public_id;
        $built->records['project'] = (string) $project->public_id;
        $built->queries['fin.invoices.create'] = '?proyek='.$project->public_id;
        $built->records['client'] = (string) DB::table('client_organizations')->where('id', $project->client_organization_id)->value('public_id');

        // The purchase first: the dispatch's lot draws the company's PURCHASE numbering on its own.
        $built->part('purchase', function () use ($built, $test, $company): void {
            PurchaseWorld::numbered($company);
            $supplier = PurchaseWorld::supplier();
            $test->actingAs(PurchaseWorld::actor($company))->post('/pembelian', PurchaseWorld::payload($supplier, [PurchaseWorld::line(Projects::product(), '5', '1000')], ['perusahaan' => $company->public_id]));
            $built->records['purchase'] = (string) PurchaseWorld::latest($company->id)->public_id;
            $built->records['supplier'] = (string) $supplier->public_id;
        });

        // The issued invoice, its document and — the renderer's job queued, not run — a ready rendition of its version.
        $built->part('invoice', function () use ($built, $world, $project, $company): void {
            $built->records['invoice'] = InvoiceWorld::publicId($world->issued($project, '1000000.00'));
            $document = DB::table('documents')->where('company_id', $company->id)->orderByDesc('id')->first(['id', 'public_id']);
            $built->records['document'] = (string) $document?->public_id;
            $version = (int) DB::table('document_versions')->where('document_id', $document?->id)->orderByDesc('id')->value('id');
            $bytes = Files::pdf();
            $file = FileObjectFactory::new()->create(['purpose' => 'RENDITION', 'company_id' => $company->id, 'mime_type' => 'application/pdf', 'size_bytes' => strlen($bytes), 'sha256' => hash('sha256', $bytes)]);
            Storage::disk($file->storage_disk)->put($file->storage_key, $bytes);
            DB::table('document_renditions')->where('document_version_id', $version)->exists()
                ? DB::table('document_renditions')->where('document_version_id', $version)->update(['state' => 'READY', 'file_object_id' => $file->id])
                : DocumentRendition::factory()->create(['document_version_id' => $version, 'state' => 'READY', 'file_object_id' => $file->id]);
            $built->records['rendition'] = (string) $file->public_id;
        });

        $built->part('dispatch', function () use ($built, $world, $company): void {
            $recorder = ReceivingWorld::actor($company, DeliveryWorld::CAPABILITIES);
            $rack = ReceivingWorld::rack();
            $demand = DispatchWorld::demand($world);
            $lot = StockWorld::lot($recorder->id, $company, $demand['product'], $demand['unit'], $rack, '10', '1000');
            $word = DispatchWorld::dispatch($recorder->id, $company->id, $demand['line'], $demand['unit'], '3', [DispatchWorld::pick($lot, $rack, '3')]);
            $word === 'COMMITTED' ?: throw new RuntimeException("The dispatch was {$word}.");
            $built->records['dispatch_project'] = (string) $demand['project']->public_id;
            $built->records['movement'] = (string) DB::table('stock_movements')->where('id', DispatchWorld::last($demand['line'])->movement)->value('public_id');
            $built->records['product'] = (string) DB::table('products')->where('id', $demand['product'])->value('public_id');
        });

        // Evidence with its file, and an image of evidence with its thumbnail drawn as the image job draws it.
        $built->part('evidence', function () use ($built, $owner, $company): void {
            $evidence = function (FileObject $file) use ($owner, $company): EvidenceDocument {
                $document = EvidenceDocument::factory()->bankStatement()->create(['company_id' => $company->id]);
                $version = EvidenceDocumentVersion::factory()->create(['evidence_document_id' => $document->id, 'file_object_id' => $file->id, 'uploaded_by' => $owner->id]);
                $document->forceFill(['current_version_id' => $version->id])->save();

                return $document;
            };
            $file = Evidence::upload($owner, $company->id);
            $built->records['file'] = (string) $file->public_id;
            $built->records['evidence'] = (string) $evidence($file)->public_id;
            $image = Evidence::upload($owner, $company->id, Files::png(64, 64), 'bukti.png');
            $evidence($image);
            // The job's own work, run here: its dispatch on upload, queued and unique, holds the job's lock.
            app()->call([new ProcessImage($image->id, 'thumbnail'), 'handle']);
            $store = app(FileStore::class);
            $store->disk($image->storage_disk)->exists($store->derivativeKey($image, 'thumbnail')) ?: throw new RuntimeException('No thumbnail was drawn.');
            $built->records['image'] = (string) $image->public_id;
        });

        $built->part('export', function () use ($built, $test, $owner): void {
            $test->actingAs($owner)->post('/laporan/ekspor', ['laporan' => 'penjualan', 'bentuk' => 'xlsx', 'periode' => 'bulan-ini']);
            $export = (string) DB::table('export_requests')->where('requested_by', $owner->id)->orderByDesc('id')->value('id');
            app()->call([new GenerateExport($export), 'handle']);
            $state = DB::table('export_requests')->where('id', $export)->value('state');
            $state === 'READY' ?: throw new RuntimeException("The export is {$state}.");
            $built->records['export'] = $export;
        });

        $built->part('user', function () use ($built, $company): void {
            $built->records['user'] = (string) Authorization::admin([$company->id], ['projects.view'])->public_id;
        });

        $built->part('import', function () use ($built): void {
            $import = ImportWorld::make();
            $built->records['batch'] = (string) ImportWorld::batch($import->prepare('LOCATION', $import->source([['id' => 'L1', 'kode' => 'RAK-1', 'nama' => 'Rak 1']])))->public_id;
        });

        return $built;
    }

    /**
     * The parameters this world gives a route: the record its address names as `{id}`, and `{kind}` for the
     * status of an export.
     *
     * @return array<string, string>
     */
    public function parameters(string $uri): array
    {
        foreach (self::ADDRESSES as $pattern => $record) {
            if (preg_match($pattern, $uri) === 1 && ($this->records[$record] ?? '') !== '') {
                return str_starts_with($uri, 'status/') ? ['kind' => 'ekspor', 'id' => $this->records[$record]] : ['id' => $this->records[$record]];
            }
        }

        return [];
    }

    /** One part in its own savepoint, so a part that fails leaves the test's transaction usable for the rest. */
    private function part(string $name, Closure $build): void
    {
        DB::beginTransaction();

        try {
            $build();
            DB::commit();
        } catch (Throwable $error) {
            DB::rollBack();
            $this->failures[] = "{$name}: ".$error::class.': '.Str::limit($error->getMessage(), 300);
        }
    }
}

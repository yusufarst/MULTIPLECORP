<?php

use App\Modules\CAT\Models\ProductImage;
use App\Modules\DOC\Contracts\RenditionTemplate;
use App\Modules\DOC\Jobs\ProcessImage;
use App\Modules\DOC\Jobs\RenderDocument;
use App\Modules\DOC\Models\DocumentRendition;
use App\Modules\DOC\Models\DocumentVersion;
use App\Modules\DOC\Models\FileObject;
use App\Modules\DOC\Services\FileStore;
use App\Modules\DOC\Services\ImageCodec;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Authorization;
use Tests\Support\Files;
use Tests\Support\RefreshDatabase;

// TL-15 (TASK-022; PERFORMANCE_TARGETS PFM-09, PFM-16; PERFORMANCE PF-31, PF-32, QB-16): the cost of an uncached
// thumbnail is known — one authenticated file request reading the session and the owning record, a bounded number of
// statements and no write, measured and logged for P8's decision on a short private cache for S0 product images —, and
// an issued version's rendition reaches READY within PFM-16's 30 seconds at 1x on the real renderer.

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('files');
    Authorization::catalogue();
});

it('serves an uncached thumbnail with a bounded, write-free set of statements, its cost measured (PFM-09; PF-32; QB-16)', function () {
    $key = app(FileStore::class)->newKey();
    app(FileStore::class)->write($key, Files::png(640, 480));
    $image = ProductImage::factory()->create([
        'file_object_id' => FileObject::factory()->productImage()->create(['storage_disk' => 'files', 'storage_key' => $key])->id,
    ]);
    $file = FileObject::query()->findOrFail($image->file_object_id);
    (new ProcessImage($file->id, 'thumbnail'))->handle(app(FileStore::class), app(ImageCodec::class));
    $viewer = Authorization::admin();
    $this->actingAs($viewer)->get("/berkas/{$file->public_id}/gambar-kecil")->assertOk();

    DB::flushQueryLog();
    DB::enableQueryLog();
    $started = hrtime(true);
    $this->actingAs($viewer)->get("/berkas/{$file->public_id}/gambar-kecil")->assertOk();
    $milliseconds = (hrtime(true) - $started) / 1_000_000;
    $queries = array_map(fn (array $query): string => $query['query'], DB::getQueryLog());
    DB::disableQueryLog();

    Log::info('PFM-09: an uncached thumbnail request.', ['statements' => count($queries), 'milliseconds' => round($milliseconds, 1)]);

    expect(count($queries))->toBeLessThanOrEqual(12)
        ->and(array_filter($queries, fn (string $sql): bool => preg_match('/^\s*(insert|update|delete)\b/i', $sql) === 1))->toBe([])
        ->and($milliseconds)->toBeLessThan(2000);
});

it('renders an issued version to READY within PFM-16\'s 30 seconds at 1x on the real renderer (PFM-16)', function () {
    app()->instance(RenditionTemplate::class, new class implements RenditionTemplate
    {
        public function html(DocumentVersion $version, string $assetDirectory): string
        {
            $rows = str_repeat('<tr><td>Barang contoh</td><td>10</td><td>Rp 150.000,00</td></tr>', 200);

            return '<!doctype html><html lang="id"><head><meta charset="utf-8"><link rel="stylesheet" href="fonts.css"></head>'
                .'<body style="font-family:\'Inter Variable\'"><h1>'.e((string) $version->formatted_number).'</h1><table>'.$rows.'</table></body></html>';
        }
    });
    $attempt = DocumentRendition::factory()->create();

    $started = hrtime(true);
    RenderDocument::dispatch($attempt->id);
    $seconds = (hrtime(true) - $started) / 1_000_000_000;

    Log::info('PFM-16: issue to READY.', ['seconds' => round($seconds, 2)]);

    expect($attempt->refresh()->state)->toBe('READY')
        ->and($seconds)->toBeLessThan(30);
});

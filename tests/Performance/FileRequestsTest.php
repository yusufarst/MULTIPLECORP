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
use Tests\Support\QueryBudget;
use Tests\Support\RefreshDatabase;

// TL-15 (TASK-022; PERFORMANCE_TARGETS PFM-09, PFM-16; PERFORMANCE PF-31, PF-32, QB-16): the cost of an uncached
// thumbnail is known — one authenticated file request reading the session and the owning record, QB-16's three statements
// in all and no write, measured and logged for P8's decision on a short private cache for S0 product images —, and an
// issued version's rendition reaches READY on the real renderer. Their times are recorded, never a CI pass or fail: PFM-16's
// 30 seconds is measured on staging (PERFORMANCE_TARGETS §1; TASK-068).

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('files');
    Authorization::catalogue();
});

it('serves an uncached thumbnail in QB-16\'s three statements in all, none a write, its cost measured (PFM-09; PF-32; QB-16)', function () {
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

    expect(count($queries))->toBeLessThanOrEqual(3)
        ->and(array_filter($queries, fn (string $sql): bool => preg_match('/^\s*(insert|update|delete)\b/i', $sql) === 1))->toBe([]);

    // TASK-068: the same file's download within QB-16's two data statements beyond the envelope, the session's write being
    // the envelope's (RV-06).
    expect(QueryBudget::beyondEnvelope(fn () => $this->actingAs($viewer)->get("/berkas/{$file->public_id}/unduh")->assertOk()))->toBeLessThanOrEqual(2);
});

it('renders an issued version to READY on the real renderer, its time recorded for PFM-16 (PFM-16)', function () {
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

    expect($attempt->refresh()->state)->toBe('READY');

    // TASK-068: its preview within QB-16 as APPR-037 amended it — three data statements beyond the envelope: the file, the
    // rendition's version for the access decision and its number for the file's name —, none a write (RV-06).
    $preview = FileObject::query()->findOrFail($attempt->file_object_id)->public_id;
    $owner = Authorization::owner();
    $statements = QueryBudget::statements(fn () => $this->actingAs($owner)->get("/berkas/{$preview}/tampil")->assertOk());

    expect(count($statements))->toBeLessThanOrEqual(3)
        ->and(array_values(array_filter($statements, fn (string $sql): bool => preg_match('/^(insert|update|delete)\b/i', $sql) === 1)))->toBe([]);
});

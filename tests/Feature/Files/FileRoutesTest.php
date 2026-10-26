<?php

use App\Http\BackgroundRequest;
use App\Modules\CAT\Models\ProductImage;
use App\Modules\DOC\Jobs\ProcessImage;
use App\Modules\DOC\Models\Document;
use App\Modules\DOC\Models\DocumentRendition;
use App\Modules\DOC\Models\DocumentVersion;
use App\Modules\DOC\Models\FileObject;
use App\Modules\DOC\Services\FileStore;
use App\Modules\DOC\Services\ImageCodec;
use App\Modules\ORG\Models\Company;
use App\Support\SecurityLog\SecurityEventKind;
use Database\Factories\DOC\DocumentTypeFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\Files;
use Tests\Support\RefreshDatabase;

// TL-04 (TASK-022; ROUTE-226, ROUTE-227, ROUTE-285; INT-014, INT-226, INT-227, INT-285; SECURITY FL-07; DP-05; RB-04,
// RB-05; criteria 1 and 5): a rendition is downloaded through its document's family at request time with FL-07's
// attachment profile — its name its document's number, `Content-Type` the stored type, nosniff, no store and a
// sandboxed policy —, never by a storage path or a guessed address; a denied, an unknown and a malformed address
// answer alike, the denial security-logged; a thumbnail is a background request, a missing one owed to JB-04 and
// answered as absent; the status a page polls answers its rendition's state, the file only to the family's viewers.

uses(RefreshDatabase::class);

// A denial renders the not-found page through the shell's layout: a hot file stands in for the build, which these
// suites precede in CI.
beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Storage::fake('files');
    Authorization::catalogue();
    $this->company = Company::factory()->create();
});

/** A READY rendition of an issued version of the given type, its PDF stored on the private disk. */
function readyRendition(int $companyId, string $type = 'DOC-01'): DocumentRendition
{
    $version = DocumentVersion::factory()->issued()->for(
        Document::factory()->state(['company_id' => $companyId, 'document_type_code' => DocumentTypeFactory::ensure($type)]),
        'document',
    )->create();
    $key = app(FileStore::class)->newKey();
    $pdf = Files::pdf();
    app(FileStore::class)->write($key, $pdf);
    $file = FileObject::factory()->create([
        'storage_disk' => 'files', 'storage_key' => $key, 'purpose' => 'RENDITION', 'company_id' => $companyId,
        'mime_type' => 'application/pdf', 'size_bytes' => strlen($pdf), 'sha256' => hash('sha256', $pdf), 'original_filename' => null,
    ]);

    return DocumentRendition::factory()->create(['document_version_id' => $version->id, 'state' => 'READY', 'file_object_id' => $file->id]);
}

function renditionFile(DocumentRendition $rendition): FileObject
{
    return FileObject::query()->findOrFail($rendition->file_object_id);
}

it('declares the thumbnail and the status background by their routes, the download not (RB-04; RB-05; HO-06)', function () {
    expect(Route::getRoutes()->getByName('doc.files.thumbnail')?->getAction(BackgroundRequest::ROUTE_FLAG))->toBeTrue()
        ->and(Route::getRoutes()->getByName('status.show')?->getAction(BackgroundRequest::ROUTE_FLAG))->toBeTrue()
        ->and(Route::getRoutes()->getByName('doc.files.download')?->getAction(BackgroundRequest::ROUTE_FLAG))->toBeNull();
});

it('writes no session for a thumbnail or a status read, and does for a download (RB-05; RV-06; criterion 5)', function () {
    $cookie = config('session.cookie');
    $rendition = readyRendition($this->company->id);
    $version = DocumentVersion::query()->findOrFail($rendition->document_version_id);
    $viewer = Authorization::admin([$this->company->id], ['projects.view', 'finance.view']);

    $this->actingAs($viewer)->get('/status/rendisi/'.$version->public_id)->assertOk()->assertCookieMissing($cookie);
    $this->actingAs($viewer)->get('/berkas/'.renditionFile($rendition)->public_id.'/gambar-kecil')->assertNotFound()->assertCookieMissing($cookie);
    $this->actingAs($viewer)->get('/berkas/'.renditionFile($rendition)->public_id.'/unduh')->assertOk()->assertCookie($cookie);
});

it('downloads a rendition to its family\'s viewer with FL-07\'s attachment profile (ROUTE-226; INT-226)', function () {
    $rendition = readyRendition($this->company->id);
    $file = renditionFile($rendition);
    $viewer = Authorization::admin([$this->company->id], ['finance.view']);
    $number = DocumentVersion::query()->findOrFail($rendition->document_version_id)->formatted_number;

    $response = $this->actingAs($viewer)->get("/berkas/{$file->public_id}/unduh");

    $response->assertOk();
    expect($response->streamedContent())->toBe(Files::pdf())
        ->and($response->headers->get('Content-Type'))->toBe('application/pdf')
        ->and($response->headers->get('Content-Disposition'))->toBe('attachment; filename='.str_replace('/', '-', (string) $number).'.pdf')
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($response->headers->get('Cache-Control'))->toContain('private')
        ->and($response->headers->get('Content-Security-Policy'))->toBe("default-src 'none'; sandbox; frame-ancestors 'none'")
        ->and($response->headers->has('X-Internal-File-Profile'))->toBeFalse();
});

it('answers a denied, an unknown and a malformed address alike, logging the denial (DP-05; AZ-11; FL-07)', function () {
    $file = renditionFile(readyRendition($this->company->id));
    $other = Company::factory()->create();
    $noFamily = Authorization::admin([$this->company->id], []);
    $outOfScope = Authorization::admin([$other->id], ['finance.view']);

    $this->actingAs($noFamily)->get("/berkas/{$file->public_id}/unduh")->assertNotFound();
    $this->actingAs($outOfScope)->get("/berkas/{$file->public_id}/unduh")->assertNotFound();
    $this->actingAs($outOfScope)->get('/berkas/'.Str::uuid7().'/unduh')->assertNotFound();
    $this->actingAs($outOfScope)->get('/berkas/..%2F..%2F.env/unduh')->assertNotFound();
    $this->actingAs($outOfScope)->get('/berkas/'.$file->storage_key.'/unduh')->assertNotFound();

    expect(DB::table('security_events')->where('event_kind', SecurityEventKind::AccessDenied->value)->where('target_public_id', $file->public_id)->count())->toBe(2);
});

it('serves no export, no unowned upload and no file to a guest by this route (ROUTE-284 is the export\'s)', function () {
    $owner = Authorization::owner();
    $export = FileObject::factory()->create(['purpose' => 'EXPORT', 'company_id' => null, 'sensitivity' => 'S3']);
    $unowned = FileObject::factory()->create(['company_id' => $this->company->id]);

    $this->actingAs($owner)->get("/berkas/{$export->public_id}/unduh")->assertNotFound();
    $this->actingAs($owner)->get("/berkas/{$unowned->public_id}/unduh")->assertNotFound();

    auth()->logout();
    $this->get("/berkas/{$unowned->public_id}/unduh")->assertRedirect('/masuk');
});

it('owes a missing thumbnail to JB-04 and answers it absent, then serves it once made (ROUTE-227; PT-22)', function () {
    Queue::fake();
    $key = app(FileStore::class)->newKey();
    app(FileStore::class)->write($key, Files::png(200, 100));
    $image = FileObject::factory()->productImage()->create(['storage_disk' => 'files', 'storage_key' => $key]);
    ProductImage::factory()->create(['file_object_id' => $image->id]);
    $viewer = Authorization::admin();

    $this->actingAs($viewer)->get("/berkas/{$image->public_id}/gambar-kecil")->assertNotFound();
    Queue::assertPushed(ProcessImage::class, fn (ProcessImage $job): bool => $job->fileId === $image->id && $job->variant === 'thumbnail');

    (new ProcessImage($image->id, 'thumbnail'))->handle(app(FileStore::class), app(ImageCodec::class));
    $response = $this->actingAs($viewer)->get("/berkas/{$image->public_id}/gambar-kecil");

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toBe('image/webp')
        ->and(getimagesizefromstring($response->streamedContent())[0] ?? null)->toBe(80);
});

it('answers the status a page polls for a rendition, the file only to the family\'s viewers (ROUTE-285; INT-285; IP-12)', function () {
    $rendition = readyRendition($this->company->id);
    $version = DocumentVersion::query()->findOrFail($rendition->document_version_id);
    $metadataOnly = Authorization::admin([$this->company->id], ['projects.view']);
    $family = Authorization::admin([$this->company->id], ['projects.view', 'finance.view']);

    $this->actingAs($metadataOnly)->getJson("/status/rendisi/{$version->public_id}")
        ->assertOk()->assertExactJson(['state' => 'READY', 'attempt' => 1, 'attempts' => 5, 'exhausted' => false, 'file' => null]);
    $this->actingAs($family)->getJson("/status/rendisi/{$version->public_id}")
        ->assertOk()->assertJsonPath('file', renditionFile($rendition)->public_id);
    $this->actingAs($family)->getJson("/status/tidak-ada/{$version->public_id}")->assertNotFound();
    $this->actingAs(Authorization::admin([Company::factory()->create()->id], ['projects.view']))->getJson("/status/rendisi/{$version->public_id}")->assertNotFound();
});

afterEach(function () {
    @unlink($this->hotFile);
});

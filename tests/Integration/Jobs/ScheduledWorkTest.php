<?php

use App\Modules\DOC\Enums\FilePurpose;
use App\Modules\DOC\Jobs\ProcessImage;
use App\Modules\DOC\Models\FileObject;
use App\Modules\DOC\Services\FileStore;
use App\Modules\DOC\Services\UploadRefused;
use App\Modules\DOC\Services\Uploads;
use App\Modules\IAM\Models\User;
use App\Support\Jobs\OwedWork;
use App\Support\SecurityLog\SecurityEventKind;
use Database\Factories\OPS\CommandLogFactory;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Files;
use Tests\Support\RefreshDatabase;

// TL-05 (TASK-022; JB-04, JB-08, JB-09; SECURITY FL-01; ARCHITECTURE §12; DATABASE §22): the upload pipeline stores
// an accepted file under a generated key with its row, its hash the stored bytes', and refuses one with nothing
// stored and an UPLOAD_REJECTED security event; JB-04 writes an image's derivatives once, a re-run writing nothing;
// JB-08 deletes only the expired command-log rows; JB-09 runs every registered sweep, one failing never stopping
// the others.

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('files');
});

it('stores an accepted upload under a generated key with its row, outside every business transaction (FL-01; DATABASE §22)', function () {
    $uploader = User::factory()->create();
    $file = app(Uploads::class)->accept(Files::path(Files::pdf()), '../../Nota "Jan".pdf', FilePurpose::Upload, null, $uploader->id);
    $stored = (string) Storage::disk('files')->get($file->storage_key);

    expect($file->storage_key)->toMatch('~^\d{4}/\d{2}/[0-9a-f-]{36}$~')
        ->and($file->storage_key)->not->toContain('Nota')
        ->and($file->original_filename)->toBe('Nota Jan.pdf')
        ->and($file->mime_type)->toBe('application/pdf')
        ->and($file->sha256)->toBe(hash('sha256', $stored))
        ->and($file->size_bytes)->toBe(strlen($stored))
        ->and($file->sensitivity)->toBe('S3')
        ->and($file->uploaded_by)->toBe($uploader->id)
        ->and(FileObject::query()->count())->toBe(1);
});

it('stores a product image re-encoded, and refuses a hostile file with nothing stored and its security event (FL-03; LG-02)', function () {
    $uploader = User::factory()->create();
    $image = app(Uploads::class)->accept(Files::path(Files::jpegWithMetadata()), 'produk.jpg', FilePurpose::ProductImage, null, $uploader->id);

    expect((string) Storage::disk('files')->get($image->storage_key))->not->toContain('GPS-LATITUDE-SECRET')
        ->and($image->sensitivity)->toBe('S0');

    expect(fn () => app(Uploads::class)->accept(Files::path(Files::svg()), 'logo.svg', FilePurpose::IdentityAsset, null, $uploader->id))
        ->toThrow(UploadRefused::class);

    $event = DB::table('security_events')->where('event_kind', SecurityEventKind::UploadRejected->value)->first(['user_id', 'reason_code', 'details']);

    expect(FileObject::query()->count())->toBe(1)
        ->and(Storage::disk('files')->allFiles())->toHaveCount(1)
        ->and($event?->user_id)->toBe($uploader->id)
        ->and($event?->reason_code)->toBe('TYPE')
        ->and((string) $event?->details)->toContain('IDENTITY_ASSET');
});

it('writes an image\'s derivatives once, a re-run writing nothing that exists (JB-04)', function () {
    $file = app(Uploads::class)->accept(Files::path(Files::png(400, 300)), 'foto.png', FilePurpose::Upload, null, User::factory()->create()->id);
    $store = app(FileStore::class);

    ProcessImage::owedFor($file);
    $thumbnail = (string) Storage::disk('files')->get($store->derivativeKey($file, 'thumbnail'));
    $preview = (string) Storage::disk('files')->get($store->derivativeKey($file, 'preview'));

    expect(getimagesizefromstring($thumbnail))->toMatchArray([0 => 80, 1 => 60, 2 => IMAGETYPE_WEBP])
        ->and(getimagesizefromstring($preview))->toMatchArray([0 => 400, 1 => 300, 2 => IMAGETYPE_WEBP]);

    Storage::disk('files')->put($store->derivativeKey($file, 'thumbnail'), 'kept');
    ProcessImage::dispatch($file->id, 'thumbnail');

    expect((string) Storage::disk('files')->get($store->derivativeKey($file, 'thumbnail')))->toBe('kept')
        ->and(Storage::disk('files')->allFiles())->toHaveCount(3);
});

it('deletes only the expired command-log rows (JB-08; CI-07)', function () {
    $expired = CommandLogFactory::new()->create(['expires_at' => now()->subDay()]);
    $live = CommandLogFactory::new()->create(['expires_at' => now()->addDay()]);

    expect(Artisan::call('command-log:purge'))->toBe(0)
        ->and(DB::table('command_log')->pluck('command_id')->all())->toBe([$live->command_id])
        ->and(DB::table('command_log')->where('command_id', $expired->command_id)->exists())->toBeFalse();
});

it('runs every registered sweep of owed work, one failing never stopping the others (JB-09; DIR-099 reading (1))', function () {
    $ran = new ArrayObject;
    app()->instance('sweep.failing', new class implements OwedWork
    {
        public function sweep(): int
        {
            throw new RuntimeException('down');
        }
    });
    app()->instance('sweep.working', new class($ran) implements OwedWork
    {
        public function __construct(private ArrayObject $ran) {}

        public function sweep(): int
        {
            $this->ran->append('swept');

            return 1;
        }
    });
    app()->tag(['sweep.failing', 'sweep.working'], OwedWork::TAG);

    expect(Artisan::call('owed-work:sweep'))->toBe(1)
        ->and($ran->getArrayCopy())->toBe(['swept']);
});

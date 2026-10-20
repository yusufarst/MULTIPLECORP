<?php

namespace Database\Factories\DOC;

use App\Modules\DOC\Models\FileObject;
use App\Modules\ORG\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * The metadata of an invented upload; no bytes are stored and the hash is of an invented content.
 *
 * @extends Factory<FileObject>
 */
final class FileObjectFactory extends Factory
{
    protected $model = FileObject::class;

    private static int $sequence = 0;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $number = ++self::$sequence;

        return [
            'storage_disk' => 'local',
            'storage_key' => 'berkas/'.Str::uuid7(),
            'original_filename' => "contoh-{$number}.pdf",
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024 + $number,
            'sha256' => hash('sha256', "isi-contoh-{$number}"),
            'company_id' => Company::factory(),
            'sensitivity' => 'S3',
            'purpose' => 'UPLOAD',
        ];
    }

    /** An S0 product image, which belongs to no company (DATABASE §4.10). */
    public function productImage(): self
    {
        return $this->state([
            'original_filename' => 'contoh.png',
            'mime_type' => 'image/png',
            'company_id' => null,
            'sensitivity' => 'S0',
            'purpose' => 'PRODUCT_IMAGE',
        ]);
    }

    /** A private import source (SECURITY FL-06). */
    public function importSource(): self
    {
        return $this->state([
            'original_filename' => 'impor-contoh.csv',
            'mime_type' => 'text/csv',
            'sensitivity' => 'S4',
            'purpose' => 'IMPORT_SOURCE',
        ]);
    }
}

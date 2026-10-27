<?php

namespace Tests\Support;

use App\Modules\DOC\Enums\FilePurpose;
use App\Modules\DOC\Models\FileObject;
use App\Modules\DOC\Services\Uploads;
use App\Modules\IAM\Models\User;
use Illuminate\Support\Str;

/**
 * TASK-030's evidence steps for the suites: an account's own upload through the real pipeline, each PDF distinct so a
 * content match is only ever the one a test makes (FL-10), and a registration's request body for ROUTE-229.
 * Autoloaded, so a concurrency session's own process reaches them too.
 */
final class Evidence
{
    /** A minimal PDF whose bytes no other call repeats. */
    public static function pdf(?string $marker = null): string
    {
        return str_replace("%PDF-1.4\n", "%PDF-1.4\n%".($marker ?? Str::random(16))."\n", Files::pdf());
    }

    /** The account's upload for evidence of the company — none for pool evidence —, through FL-01–FL-06. */
    public static function upload(User $uploader, ?int $companyId, ?string $bytes = null, string $name = 'bukti.pdf'): FileObject
    {
        return app(Uploads::class)->accept(Files::path($bytes ?? self::pdf()), $name, FilePurpose::Upload, $companyId, $uploader->id);
    }

    /**
     * ROUTE-229's body for a client order of the company — every field overridable.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function registration(FileObject $file, ?string $companyPublicId, array $overrides = []): array
    {
        return [
            'command_id' => (string) Str::uuid(),
            'file' => $file->public_id,
            'company' => $companyPublicId,
            'type' => 'CLIENT_ORDER',
            'issuer' => 'SMA Negeri 1 Contoh',
            'reference' => 'SP/'.Str::upper(Str::random(6)),
            'date' => '2026-10-01',
            ...$overrides,
        ];
    }

    /** @return array<string, mixed> the answer the command left for the next page (IP-01) */
    public static function answer(): array
    {
        $flash = session('inertia.flash_data');
        $answer = is_array($flash) ? ($flash['answer'] ?? null) : null;

        return is_array($answer) ? $answer : [];
    }
}

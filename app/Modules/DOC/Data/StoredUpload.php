<?php

namespace App\Modules\DOC\Data;

use App\Modules\DOC\Enums\FilePurpose;

/**
 * `DOC StoreUpload`'s answer (ROUTE-228; INT-013): the stored file's public identifier and display name for the
 * command that follows, or a refusal at the field — MSG-31 with the purpose's accepted types and size (FL-02), or
 * MSG-20 with the minutes left past WS-12's bounds.
 */
final class StoredUpload
{
    private function __construct(
        public readonly ?string $id,
        public readonly ?string $name,
        public readonly FilePurpose $purpose,
        public readonly ?int $limitedMinutes = null,
    ) {}

    public static function stored(string $id, ?string $name, FilePurpose $purpose): self
    {
        return new self($id, $name, $purpose);
    }

    public static function refused(FilePurpose $purpose): self
    {
        return new self(null, null, $purpose);
    }

    public static function limited(FilePurpose $purpose, int $minutes): self
    {
        return new self(null, null, $purpose, $minutes);
    }

    /** MSG-31's accepted types, by their file extensions. */
    public function acceptedTypes(): string
    {
        return implode(', ', array_map(fn ($type): string => strtoupper($type->extensions()[0] ?? ''), $this->purpose->allowed()));
    }

    /** MSG-31's size limit in megabytes. */
    public function sizeLimit(): string
    {
        return intdiv($this->purpose->maximumBytes(), 1024 * 1024).' MB';
    }
}

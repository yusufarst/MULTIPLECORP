<?php

namespace App\Modules\DOC\Contracts;

use App\Modules\DOC\Data\Download;
use Closure;

/**
 * DOC's part of an export (FL-07; FL-09; DATABASE §22; C-32; DIR-089 (5)): the private EXPORT file of one requester —
 * stored by DOC, which owns `file_objects` and the private disk, so Reporting, which only reads, never writes another
 * module's table or storage itself (ARCHITECTURE §3) — and its bytes read back for that requester alone.
 */
interface ExportFiles
{
    /**
     * Stores the file at `$path` as a private EXPORT file of `$requesterId` and, in the same transaction as its row,
     * asks `$attach` to bind it to its export request; when `$attach` answers false the row rolls back and the bytes
     * are removed. Answers whether the file was attached.
     *
     * @param  Closure(int): bool  $attach  given the new file's id
     */
    public function store(string $path, string $mimeType, int $requesterId, Closure $attach): bool;

    /** The EXPORT file `$fileId` of `$requesterId`, or nothing for any other file or account (AZ-11). */
    public function stream(int $fileId, int $requesterId): ?Download;
}

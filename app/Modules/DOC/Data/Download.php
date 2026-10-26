<?php

namespace App\Modules\DOC\Data;

/** A private file opened for one authorized response: its stream, its detected type, its size and its name. */
final readonly class Download
{
    /** @param resource $stream */
    public function __construct(public mixed $stream, public string $mimeType, public int $size, public string $name) {}
}

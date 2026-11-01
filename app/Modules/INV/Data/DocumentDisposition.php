<?php

namespace App\Modules\INV\Data;

/**
 * What a dispatch correction does with the issued Surat Jalan it affects (WORKFLOWS §8): KEEP, REVISE or VOID, with its reason.
 */
final class DocumentDisposition
{
    public const CHOICES = ['KEEP', 'REVISE', 'VOID'];

    public function __construct(
        public readonly string $choice,
        public readonly string $reason,
    ) {}
}

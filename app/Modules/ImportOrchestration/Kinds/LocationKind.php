<?php

namespace App\Modules\ImportOrchestration\Kinds;

use App\Modules\ImportOrchestration\Data\Cells;
use App\Modules\ImportOrchestration\Data\ImportRowData;
use App\Modules\INV\Actions\SaveLocation;
use App\Support\Command\Outcome;

/** A rack location (IMP-01) through INV's ordinary SaveLocation, its code the key later rows name. */
final class LocationKind implements ImportKind
{
    public const CODE = 'LOCATION';

    public function __construct(private readonly SaveLocation $locations) {}

    public function code(): string
    {
        return self::CODE;
    }

    public function grouped(): bool
    {
        return false;
    }

    public function companyBound(): bool
    {
        return false;
    }

    public function normalize(Cells $head, array $lines): array
    {
        return ['code' => $head->text('kode', true, 40), 'name' => $head->text('nama', true, 200)];
    }

    public function steps(ImportRowData $row): array
    {
        return [fn (string $commandId, int $actor): Outcome => $this->locations->create($commandId, $actor, $row->text('code'), $row->text('name'))];
    }

    public function totals(array $payload): array
    {
        return [];
    }
}

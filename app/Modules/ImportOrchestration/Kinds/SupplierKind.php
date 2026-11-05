<?php

namespace App\Modules\ImportOrchestration\Kinds;

use App\Modules\ImportOrchestration\Data\Cells;
use App\Modules\ImportOrchestration\Data\ImportRowData;
use App\Modules\PTY\Actions\CreateSupplier;
use App\Modules\PTY\Services\ClientRows;
use App\Support\Command\Outcome;

/** A current supplier (IMP-01) through PTY's ordinary CreateSupplier; a like name acknowledged as for clients (PF-21). */
final class SupplierKind implements ImportKind
{
    public const CODE = 'SUPPLIER';

    public function __construct(
        private readonly CreateSupplier $create,
        private readonly ClientRows $rows,
    ) {}

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
        return [
            'name' => $head->text('nama', true, 200), 'npwp' => $head->text('npwp', false, 30), 'address' => $head->text('alamat', false, 500),
            'city' => $head->text('kota', false, 100), 'phone' => $head->text('telepon', false, 50), 'email' => $head->text('email', false, 200),
        ];
    }

    public function steps(ImportRowData $row): array
    {
        return [function (string $commandId, int $actor) use ($row): Outcome {
            $name = $row->text('name');
            $matches = $this->rows->sameNames('suppliers', $name);

            return $this->create->run($commandId, $actor, $name, ['npwp' => $row->nullable('npwp'), 'address_lines' => $row->nullable('address'), 'city' => $row->nullable('city'),
                'phone' => $row->nullable('phone'), 'email' => $row->nullable('email')], $matches, $matches === [] ? null : ClientKind::reason($row));
        }];
    }

    public function totals(array $payload): array
    {
        return [];
    }
}

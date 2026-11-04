<?php

namespace App\Modules\ImportOrchestration\Kinds;

use App\Modules\ImportOrchestration\Data\Cells;
use App\Modules\ImportOrchestration\Data\ImportRowData;
use App\Modules\PTY\Actions\CreateClientOrganization;
use App\Modules\PTY\Services\ClientRows;
use App\Support\Command\Outcome;

/**
 * A current client (IMP-01): the organization with its main unit, address and PIC through PTY's ordinary
 * CreateClientOrganization; its further units are CLIENT_UNIT rows. A legacy name like an existing one is the same
 * likely duplicate the form warns of (PF-21), acknowledged by the import with its reason, since the preparer reviewed
 * the dry run's rows.
 */
final class ClientKind implements ImportKind
{
    public const CODE = 'CLIENT';

    public function __construct(
        private readonly CreateClientOrganization $create,
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
            'name' => $head->text('nama', true, 200), 'npwp' => $head->text('npwp', false, 30),
            'unit' => $head->text('unit', false, 200), 'address' => $head->text('alamat', false, 500), 'city' => $head->text('kota', false, 100),
            'pic' => $head->text('pic_nama', false, 200), 'position' => $head->text('pic_jabatan', false, 100),
            'phone' => $head->text('pic_telepon', false, 50), 'email' => $head->text('pic_email', false, 200),
        ];
    }

    public function steps(ImportRowData $row): array
    {
        return [function (string $commandId, int $actor) use ($row): Outcome {
            $name = $row->text('name');
            $unit = $row->nullable('unit');
            $address = $unit !== null && $row->nullable('address') !== null ? ['label' => $unit, 'address_lines' => $row->text('address'), 'city' => $row->nullable('city')] : null;
            $contact = $unit !== null && $row->nullable('pic') !== null ? ['name' => $row->text('pic'), 'position' => $row->nullable('position'), 'phone' => $row->nullable('phone'), 'email' => $row->nullable('email')] : null;
            $matches = $this->rows->sameNames('client_organizations', $name);

            return $this->create->run($commandId, $actor, ['name' => $name, 'npwp' => $row->nullable('npwp')], $unit, $address, $contact, $matches, $matches === [] ? null : self::reason($row));
        }];
    }

    public function totals(array $payload): array
    {
        return [];
    }

    /** The import's acknowledgement of a likely duplicate (PF-21). */
    public static function reason(ImportRowData $row): string
    {
        return "Impor data awal {$row->batchPublicId}, baris {$row->rowNumber}";
    }
}

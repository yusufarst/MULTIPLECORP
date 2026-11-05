<?php

namespace App\Modules\ImportOrchestration\Kinds;

use App\Modules\ImportOrchestration\Data\Cells;
use App\Modules\ImportOrchestration\Data\ImportRowData;
use App\Modules\ImportOrchestration\Services\RowRefused;
use App\Modules\PTY\Actions\AddClientUnit;
use App\Modules\PTY\Queries\ClientDirectory;
use App\Support\Command\Outcome;
use App\Support\RowHandle;
use Illuminate\Support\Facades\DB;

/**
 * A client's further unit with its address and PIC (IMP-01), through PTY's ordinary AddClientUnit: the unit, then its
 * PIC as the unit's contact; the client is the one an earlier CLIENT batch committed under the identity named.
 */
final class ClientUnitKind implements ImportKind
{
    public const CODE = 'CLIENT_UNIT';

    public function __construct(
        private readonly AddClientUnit $units,
        private readonly ClientDirectory $clients,
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
            'client' => $head->text('klien', true, 100), 'unit' => $head->text('unit', true, 200),
            'address' => $head->text('alamat', false, 500), 'city' => $head->text('kota', false, 100),
            'pic' => $head->text('pic_nama', false, 200), 'position' => $head->text('pic_jabatan', false, 100),
            'phone' => $head->text('pic_telepon', false, 50), 'email' => $head->text('pic_email', false, 200),
        ];
    }

    public function steps(ImportRowData $row): array
    {
        $steps = [function (string $commandId, int $actor) use ($row): Outcome {
            $organization = $this->clients->find($this->organization(References::client($row->text('client'), 'klien'))) ?? throw new RowRefused('klien', 'reference');
            $address = $row->nullable('address') === null ? null : ['label' => $row->text('unit'), 'address_lines' => $row->text('address'), 'city' => $row->nullable('city')];

            return $this->units->unit($commandId, $actor, $organization, $row->text('unit'), $address);
        }];

        if ($row->nullable('pic') !== null) {
            $steps[] = function (string $commandId, int $actor, array $done) use ($row): Outcome {
                $organization = $this->clients->find($this->organization(References::client($row->text('client'), 'klien'))) ?? throw new RowRefused('klien', 'reference');
                $unit = $this->clients->unitOf($organization, RowHandle::of('client_units', References::result($done, 0, 'unit'))) ?? throw new RowRefused('unit', 'reference');

                return $this->units->contact($commandId, $actor, $unit, ['name' => $row->text('pic'), 'position' => $row->nullable('position'), 'phone' => $row->nullable('phone'), 'email' => $row->nullable('email')]);
            };
        }

        return $steps;
    }

    public function totals(array $payload): array
    {
        return [];
    }

    /** The client organization's public id, read through PTY's query class (ARCHITECTURE §3). */
    private function organization(int $id): string
    {
        $publicId = DB::table('client_organizations')->where('id', $id)->value('public_id');

        return is_string($publicId) ? $publicId : throw new RowRefused('klien', 'reference');
    }
}

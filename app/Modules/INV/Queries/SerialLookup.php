<?php

namespace App\Modules\INV\Queries;

use App\Modules\IAM\Data\Audience;
use App\Modules\IAM\Data\Authority;
use App\Modules\INV\Models\SerialUnit;
use App\Support\Search\Row;
use App\Support\Search\Term;

/**
 * The serials' part of search and lookup (PERFORMANCE PF-19; DP-06; PJ-03): a serial is a pooled physical fact
 * (S1), found exactly by its normalized key — whitespace removed, lower case, as the serial factory keeps it — to
 * a holder of `stock.view` only; it shows its presence and its product, never a company. A serial key is unique
 * per product only, so several rows may share it, and none is chosen for the user (SF-SCAN). A serial has no
 * address of its own: its result opens its product's stock (INFORMATION_ARCHITECTURE §10, §11).
 */
final class SerialLookup
{
    /** @return list<array{type: string, id: string, code: string, label: string}> */
    public function exact(Authority $viewer, Term $term, int $rows): array
    {
        if (! Audience::pool()->admits($viewer)) {
            return [];
        }

        $rows = SerialUnit::query()
            ->join('products', 'products.id', '=', 'serial_units.product_id')
            ->where('serial_units.serial_key', self::key($term))
            ->select(['serial_units.serial_number', 'products.public_id', 'products.name'])
            ->orderBy('products.name')
            ->limit($rows + 1)
            ->get()
            ->all();

        return array_map(fn (object $serial): array => [
            'type' => 'serial',
            'id' => Row::text($serial, 'public_id'),
            'code' => Row::text($serial, 'serial_number'),
            'label' => Row::text($serial, 'name'),
        ], array_values($rows));
    }

    public static function key(Term $term): string
    {
        return mb_strtolower($term->compact());
    }
}

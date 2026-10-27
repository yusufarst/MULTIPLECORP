<?php

use App\Modules\PTY\Data\PartyFields;

// TL-03 (TASK-026; DATABASE §4.3; PF-21): one normalization of a party's name — surrounding space trimmed, each run of
// white space one space, case kept — and its comparison key lower-cased, so a unit's unique key, the search-first list
// and the duplicate warning see "Unit  Kurikulum " and "unit kurikulum" as one name.

it('normalizes a name and compares it lower-cased (PF-21; BD-16)', function (string $typed, string $name) {
    expect(PartyFields::name($typed))->toBe($name)
        ->and(PartyFields::key($typed))->toBe(mb_strtolower($name));
})->with([
    ['  Unit  Kurikulum ', 'Unit Kurikulum'],
    ["SMAN\t1\nYogyakarta", 'SMAN 1 Yogyakarta'],
    ['Universitas Gadjah Mada', 'Universitas Gadjah Mada'],
    ['ÉCOLE  Ünited', 'ÉCOLE Ünited'],
]);

it('reads optional fields as trimmed text or nothing, a name normalized (IP-14)', function () {
    expect(PartyFields::of(['name', 'npwp', 'city'], ['name' => ' Toko  Maju ', 'npwp' => '   ', 'city' => null]))
        ->toBe(['name' => 'Toko Maju', 'npwp' => null, 'city' => null]);
});

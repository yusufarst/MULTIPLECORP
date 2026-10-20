<?php

namespace Tests\Support;

use InvalidArgumentException;
use UnexpectedValueException;

/**
 * The fixture sets FX-01–FX-10 of TEST_STRATEGY §4, each a dataset in tests/Fixtures that any suite
 * loads (TASK-003). A set states its title, source and users as §4's row does, the documents it is
 * transcribed from, and its entries.
 */
final class FixtureSets
{
    public const IDS = ['FX-01', 'FX-02', 'FX-03', 'FX-04', 'FX-05', 'FX-06', 'FX-07', 'FX-08', 'FX-09', 'FX-10'];

    /**
     * @return array{id: string, title: string, source: string, used_by: string, documents: list<string>, entries: array<array-key, mixed>}&array<string, mixed>
     */
    public static function load(string $id): array
    {
        if (! in_array($id, self::IDS, true)) {
            throw new InvalidArgumentException("There is no fixture set {$id}.");
        }

        $set = require dirname(__DIR__)."/Fixtures/{$id}.php";

        foreach (['title', 'source', 'used_by'] as $key) {
            if (! is_array($set) || ($set['id'] ?? null) !== $id || ! is_string($set[$key] ?? null) || $set[$key] === '') {
                throw new UnexpectedValueException("tests/Fixtures/{$id}.php is not the fixture set {$id}.");
            }
        }

        if (! is_array($set['documents'] ?? null) || ! is_array($set['entries'] ?? null) || $set['entries'] === []) {
            throw new UnexpectedValueException("tests/Fixtures/{$id}.php has no documents or no entries.");
        }

        return $set;
    }
}

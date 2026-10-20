<?php

namespace Tests;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Support\TestDatabase;

abstract class TestCase extends BaseTestCase
{
    /**
     * A test using a database trait is checked before the trait migrates or wipes anything.
     *
     * @return array<class-string, class-string>
     */
    protected function setUpTraits()
    {
        $traits = [RefreshDatabase::class, DatabaseMigrations::class, DatabaseTruncation::class, DatabaseTransactions::class];

        if (array_intersect_key(class_uses_recursive(static::class), array_flip($traits)) !== []) {
            $connection = 'database.connections.'.config('database.default');

            TestDatabase::assertIsolated((string) config("{$connection}.driver"), (string) config("{$connection}.database"));
        }

        return parent::setUpTraits();
    }
}

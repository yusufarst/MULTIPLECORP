<?php

namespace Tests;

use App\Support\Database\DatabaseRole;
use App\Support\SecurityLog\SecurityEventRecorder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\ParallelTesting;
use Tests\Support\TestDatabase;

abstract class TestCase extends BaseTestCase
{
    private const DATABASE_TRAITS = [RefreshDatabase::class, DatabaseMigrations::class, DatabaseTruncation::class, DatabaseTransactions::class];

    /**
     * The process's test database exists, owned by the migration/owner role, before the framework switches
     * the runtime's connection to it (TASK-005).
     */
    protected function refreshApplication()
    {
        parent::refreshApplication();

        $token = ParallelTesting::token();

        if ($token !== false && $this->usesDatabaseTrait()) {
            TestDatabase::ensureCreated(config('database.connections.'.DatabaseRole::MIGRATION_CONNECTION.'.database').'_test_'.$token);
        }
    }

    /**
     * A test using a database trait is checked before the trait migrates or wipes anything, and its
     * migrations and its security events reach the same test database through the migration/owner role's
     * connection and the security-event connection.
     *
     * @return array<class-string, class-string>
     */
    protected function setUpTraits()
    {
        if ($this->usesDatabaseTrait()) {
            $connection = 'database.connections.'.config('database.default');
            $migration = 'database.connections.'.DatabaseRole::MIGRATION_CONNECTION;

            config(["{$migration}.database" => config("{$connection}.database")]);
            DB::purge(DatabaseRole::MIGRATION_CONNECTION);

            foreach ([SecurityEventRecorder::CONNECTION, DatabaseRole::ADMINISTRATION_CONNECTION] as $other) {
                config(["database.connections.{$other}.database" => config("{$connection}.database")]);
                DB::purge($other);
            }

            TestDatabase::assertIsolated((string) config("{$connection}.driver"), (string) config("{$connection}.database"), (string) config("{$migration}.database"));
        }

        return parent::setUpTraits();
    }

    private function usesDatabaseTrait(): bool
    {
        return array_intersect_key(class_uses_recursive(static::class), array_flip(self::DATABASE_TRAITS)) !== [];
    }
}

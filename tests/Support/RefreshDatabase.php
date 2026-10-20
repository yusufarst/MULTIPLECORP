<?php

namespace Tests\Support;

use App\Support\Database\DatabaseRole;
use Illuminate\Foundation\Testing\RefreshDatabase as FrameworkRefreshDatabase;

/**
 * The framework's RefreshDatabase with the roles of DATABASE §28 (TASK-005): the schema is migrated
 * through the migration/owner role's connection and every test runs as the application runtime, which
 * runs no DDL and holds only its granted privileges — so a test proves a command works under them.
 */
trait RefreshDatabase
{
    use FrameworkRefreshDatabase {
        migrateFreshUsing as frameworkMigrateFreshUsing;
    }

    /**
     * @return array<string, mixed>
     */
    protected function migrateFreshUsing(): array
    {
        return [...$this->frameworkMigrateFreshUsing(), '--database' => DatabaseRole::MIGRATION_CONNECTION];
    }
}

<?php

namespace Tests\Support;

use App\Support\Database\DatabaseRole;
use Illuminate\Foundation\Testing\DatabaseTruncation as FrameworkDatabaseTruncation;

/**
 * The framework's DatabaseTruncation with the roles of DATABASE §28 (TASK-005), for the race tests whose
 * sessions see only committed data: the schema is migrated and the tables truncated through the
 * migration/owner role's connection — the runtime holds no TRUNCATE — and the test runs as the runtime.
 */
trait DatabaseTruncation
{
    use FrameworkDatabaseTruncation {
        migrateFreshUsing as frameworkMigrateFreshUsing;
    }

    /**
     * @return array<string, mixed>
     */
    protected function migrateFreshUsing(): array
    {
        return [...$this->frameworkMigrateFreshUsing(), '--database' => DatabaseRole::MIGRATION_CONNECTION];
    }

    /**
     * @return list<string>
     */
    protected function connectionsToTruncate(): array
    {
        return [DatabaseRole::MIGRATION_CONNECTION];
    }
}

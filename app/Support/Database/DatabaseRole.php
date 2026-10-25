<?php

namespace App\Support\Database;

/**
 * The database roles of DATABASE §28 that the migrations grant to (TASK-005; SECURITY H9-01;
 * INFRASTRUCTURE §4.4). The group names are fixed, so the schema and its privileges are the same in
 * every environment; each environment's logins are members of them. The migration/owner role owns what
 * it creates and is granted nothing.
 */
final class DatabaseRole
{
    /** The application runtime: web requests and queue workers, with the per-table privileges of §28. */
    public const RUNTIME = 'multiplecorp_runtime';

    /** The administration role: the runtime's privileges and those of the audited guard rebuild (§3.1). */
    public const ADMINISTRATION = 'multiplecorp_administration';

    /** The connection of the migration/owner role, used only by migrations: `migrate --database=migration`. */
    public const MIGRATION_CONNECTION = 'migration';

    /** The connection of the administration role, used only by the operator's `GuardMaintenance` (TX-08). */
    public const ADMINISTRATION_CONNECTION = 'administration';
}

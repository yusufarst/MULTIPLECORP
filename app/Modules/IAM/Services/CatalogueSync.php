<?php

namespace App\Modules\IAM\Services;

use App\Modules\IAM\Data\CapabilityCatalogue;
use App\Modules\IAM\Enums\BuiltinRole;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Writes the authorization catalogue's rows (PERMISSIONS_MATRIX §3–§4; TASK-005's tables, whose rows are
 * TASK-011's): the 80 capabilities with their classes, the two built-in roles of RG-01, and the OWNER
 * role's `role_capabilities` — every capability —, ADMIN_OPERASIONAL carrying none (RG-02). It only adds
 * what is missing, so it runs again harmlessly on every installation and deployment; a stored class
 * other than the catalogue's, or a capability the catalogue does not list, is refused, never changed,
 * since the runtime holds no UPDATE or DELETE on these tables (DATABASE §28).
 */
final class CatalogueSync
{
    /**
     * @return array{capabilities: int, roles: int, role_capabilities: int} the rows added
     */
    public function run(): array
    {
        return DB::transaction(function (): array {
            // Two runs at once are harmless: each insert ignores a row the other has just added.
            $stored = DB::table('capabilities')->pluck('authority_class', 'code');

            foreach ($stored as $code => $class) {
                if (! CapabilityCatalogue::has((string) $code)) {
                    throw new RuntimeException("The stored capability {$code} is not in PERMISSIONS_MATRIX §4.");
                }

                if ($class !== CapabilityCatalogue::classOf((string) $code)->value) {
                    throw new RuntimeException("The stored capability {$code} has another class than its catalogue class.");
                }
            }

            $capabilities = DB::table('capabilities')->insertOrIgnore(array_map(
                fn (string $code): array => ['code' => $code, 'authority_class' => CapabilityCatalogue::classOf($code)->value],
                array_values(array_diff(CapabilityCatalogue::codes(), array_map('strval', $stored->keys()->all()))),
            ));

            $roles = DB::table('roles')->insertOrIgnore(array_map(
                fn (BuiltinRole $role): array => ['code' => $role->value, 'is_builtin' => true],
                BuiltinRole::cases(),
            ));

            foreach (BuiltinRole::cases() as $role) {
                if (DB::table('roles')->where('code', $role->value)->value('is_builtin') !== true) {
                    throw new RuntimeException("The role {$role->value} exists but is not built in.");
                }
            }

            $owner = DB::table('roles')->where('code', BuiltinRole::Owner->value)->value('id');
            $roleCapabilities = DB::table('role_capabilities')->insertOrIgnoreUsing(
                ['role_id', 'capability_id'],
                DB::table('capabilities')
                    ->selectRaw('?, capabilities.id', [$owner])
                    ->whereNotExists(fn (Builder $query) => $query->from('role_capabilities')
                        ->whereColumn('role_capabilities.capability_id', 'capabilities.id')
                        ->where('role_capabilities.role_id', $owner)),
            );

            return ['capabilities' => $capabilities, 'roles' => $roles, 'role_capabilities' => $roleCapabilities];
        });
    }
}

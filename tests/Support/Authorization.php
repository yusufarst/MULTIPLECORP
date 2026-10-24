<?php

namespace Tests\Support;

use App\Modules\IAM\Models\User;
use App\Modules\IAM\Services\CatalogueSync;
use App\Modules\IAM\Services\EffectiveCapabilities;
use Illuminate\Support\Facades\DB;

/**
 * Accounts with the roles and grants of PERMISSIONS_MATRIX §3 for the authorization tests (TASK-011): the
 * catalogue's rows written as the installation writes them, an Owner through the built-in OWNER role and
 * an Admin through ADMIN_OPERASIONAL with individual company and capability grants. Each change forgets
 * the request's memoized authority, as a new request would (AZ-03).
 */
final class Authorization
{
    public static function catalogue(): void
    {
        app(CatalogueSync::class)->run();
    }

    public static function owner(): User
    {
        return self::withRole('OWNER');
    }

    /**
     * @param  list<int>  $companyIds
     * @param  list<string>  $capabilities
     */
    public static function admin(array $companyIds = [], array $capabilities = []): User
    {
        $admin = self::withRole('ADMIN_OPERASIONAL');

        foreach ($companyIds as $companyId) {
            self::grantCompany($admin, $companyId);
        }

        foreach ($capabilities as $capability) {
            self::grant($admin, $capability);
        }

        return $admin;
    }

    public static function grant(User $user, string $capability): void
    {
        DB::table('user_capability_grants')->insert([
            'user_id' => $user->id,
            'capability_id' => DB::table('capabilities')->where('code', $capability)->value('id'),
            'granted_at' => now(),
            'reason' => 'Uji otorisasi',
        ]);
        self::forget();
    }

    public static function revoke(User $user, string $capability): void
    {
        DB::table('user_capability_grants')
            ->where('user_id', $user->id)
            ->where('capability_id', DB::table('capabilities')->where('code', $capability)->value('id'))
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
        self::forget();
    }

    public static function grantCompany(User $user, int $companyId): void
    {
        DB::table('user_company_grants')->insert(['user_id' => $user->id, 'company_id' => $companyId, 'granted_at' => now()]);
        self::forget();
    }

    public static function forget(): void
    {
        app(EffectiveCapabilities::class)->forget();
    }

    private static function withRole(string $role): User
    {
        $user = User::factory()->create();
        DB::table('user_roles')->insert([
            'user_id' => $user->id,
            'role_id' => DB::table('roles')->where('code', $role)->value('id'),
            'granted_at' => now(),
        ]);

        return $user;
    }
}

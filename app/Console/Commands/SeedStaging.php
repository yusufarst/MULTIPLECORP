<?php

namespace App\Console\Commands;

use App\Modules\IAM\Actions\CreateUser;
use App\Modules\IAM\Actions\GrantAccess;
use App\Modules\IAM\Actions\IssueCredentialLink;
use App\Modules\IAM\Models\User;
use App\Modules\IAM\Services\AccountAccess;
use App\Modules\ORG\Actions\CreateCompany;
use App\Modules\ORG\Data\CompanyFields;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

/**
 * The staging stack's synthetic seed (TASK-072; PRQ-02; TEST_STRATEGY FX-02; DIR-079 CB-04): FX-02's group — ARJ, BTN and
 * CKP — and the base personas of SECURITY_TEST_MATRIX §2 an operator signs in as on staging, each made through the product's
 * own commands as the installed first Owner, so the audit trail is the product's. Staging only — the framework's
 * environment and the stack's APP_ENV both staging —; refused anywhere else.
 * Idempotent: a company or an account already present is left as it is. Each new account's password-setting link is shown
 * once, here, to the operator (AU-11) — the accounts use reserved example domains, so no mail reaches anyone. The persona
 * states of STM §2 that a test arranges — a capability withheld, a deactivated or Google-linked account — are the suites'.
 */
class SeedStaging extends Command
{
    protected $signature = 'staging:seed {--owner= : the e-mail of the installed Owner the seed acts as}';

    protected $description = 'Seed the staging stack with FX-02\'s synthetic group and base personas (staging only)';

    /** FX-02's companies (tests/Fixtures/FX-02.php), by code. */
    public const COMPANIES = [
        'ARJ' => 'PT Contoh Arunika Raya Jaya',
        'BTN' => 'CV Contoh Bentala Tirta Nusa',
        'CKP' => 'PT Contoh Cakrawala Karya Pratama',
    ];

    /**
     * FX-02's base personas: e-mail, whether the account holds the built-in Owner role — the Admin role otherwise —, its
     * companies and whether it holds the ADM capabilities. The role is read from its built-in row, never named here (SM-03).
     */
    public const ACCOUNTS = [
        'owner-2' => ['email' => 'owner-2@example.com', 'owner' => true, 'companies' => [], 'adm' => false],
        'admin-a' => ['email' => 'admin-a@example.com', 'owner' => false, 'companies' => ['ARJ'], 'adm' => true],
        'admin-ab' => ['email' => 'admin-ab@example.com', 'owner' => false, 'companies' => ['ARJ', 'BTN'], 'adm' => true],
        'admin-new' => ['email' => 'admin-new@example.com', 'owner' => false, 'companies' => [], 'adm' => false],
    ];

    public function handle(CreateCompany $createCompany, CreateUser $createUser, GrantAccess $grantAccess, IssueCredentialLink $issueLink, AccountAccess $access): int
    {
        // The console's `--env` changes the environment the framework reports, never the stack's own APP_ENV, which the
        // configuration reads: both must say staging, so `--env=staging` on production is refused too.
        if (! $this->laravel->environment('staging') || config('app.env') !== 'staging') {
            $this->error('The synthetic seed runs on staging only (DIR-079 CB-04).');

            return self::FAILURE;
        }

        $email = $this->option('owner');
        $owner = User::query()->where('email', is_string($email) ? strtolower(trim($email)) : '')->first();

        if ($owner === null || ! $access->holdsOwnerRole($owner->id)) {
            $this->error('Name the installed Owner with --owner: the seed acts as that account.');

            return self::FAILURE;
        }

        foreach (self::COMPANIES as $code => $name) {
            if (DB::table('companies')->where('code', $code)->exists()) {
                $this->line("exists  company {$code}");

                continue;
            }

            $fields = array_fill_keys(CompanyFields::LEGAL, null);
            $outcome = $createCompany->run((string) Str::uuid(), $owner->id, ['code' => $code, ...$fields, 'legal_name' => $name]);

            if ($outcome->kind !== 'COMMITTED') {
                return $this->refused("company {$code}", $outcome->kind, $outcome->code);
            }

            $this->line("created company {$code}");
        }

        $adm = array_values(array_filter(DB::table('capabilities')->where('authority_class', 'ADM')->orderBy('code')->pluck('code')->all(), is_string(...)));

        foreach (self::ACCOUNTS as $persona => $account) {
            if (User::query()->where('email', $account['email'])->exists()) {
                $this->line("exists  account {$persona}");

                continue;
            }

            $created = $createUser->run((string) Str::uuid(), $owner->id, Str::headline($persona).' (staging)', $account['email']);

            if ($created->kind !== 'COMMITTED') {
                return $this->refused("account {$persona}", $created->kind, $created->code);
            }

            $user = User::query()->findOrFail($created->resultId);
            $companies = array_values(array_map(fn (mixed $id): int => is_numeric($id) ? (int) $id : 0, DB::table('companies')->whereIn('code', $account['companies'])->orderBy('id')->pluck('id')->all()));
            $capabilities = $account['adm'] ? $adm : [];
            $role = $this->roleCode($account['owner'] ? $access->ownerRoleId() : $access->adminRoleId());

            // A new account holds the Admin role and nothing else; a grant that would change nothing is RG-05's refusal.
            if ($access->roleOf($user->id) !== $role || $companies !== [] || $capabilities !== []) {
                $granted = $grantAccess->run((string) Str::uuid(), $owner->id, $user, $role, $companies, $capabilities, AccountAccess::version($user), 'Staging synthetic seed (FX-02)');

                if ($granted->kind !== 'COMMITTED') {
                    return $this->refused("access of {$persona}", $granted->kind, $granted->code);
                }
            }

            $linked = $issueLink->run((string) Str::uuid(), $owner->id, User::query()->findOrFail($user->id));
            $link = $issueLink->issued();

            if ($linked->kind !== 'COMMITTED' || $link === null) {
                return $this->refused("link of {$persona}", $linked->kind, $linked->code);
            }

            $this->line("created account {$persona} — set its password once at ".rtrim(config()->string('app.url'), '/').'/atur-kata-sandi#'.$link['token']);
        }

        return self::SUCCESS;
    }

    /** The code of a built-in role row, as GrantAccess takes it. */
    private function roleCode(int $roleId): string
    {
        $code = DB::table('roles')->where('id', $roleId)->value('code');

        return is_string($code) ? $code : throw new LogicException('The built-in roles are missing: run authorization:sync-catalogue first.');
    }

    private function refused(string $what, string $kind, ?string $code): int
    {
        $this->error("The {$what} was not made: {$kind} ".($code ?? ''));

        return self::FAILURE;
    }
}

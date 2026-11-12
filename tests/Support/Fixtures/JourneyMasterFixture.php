<?php

namespace Tests\Support\Fixtures;

use App\Modules\CAT\Models\ProductBarcode;
use App\Modules\IAM\Models\User;
use App\Modules\IAM\Services\CatalogueSync;
use App\Modules\ORG\Models\Company;
use App\Support\BusinessDate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\Projects;

/**
 * TASK-064's browser fixture (TL-09; CJ-12, CJ-19): the signed-in plain account made an Admin of a company made for the run, and CJ-12's
 * search world — two companies of the run, each with a project and an issued quotation drawn through their own actions by an Owner made
 * here (QuotationFixture), so both hold the same project and document numbers; more than 200 projects of the first company titled with the
 * run's stamp and a few of the second's, a project of the second alone titled with another stamp, all inserted in bulk with their PROJECT
 * counter raised above them so `ReconcileGuards` finds every number below its counter; a product with its SKU and barcode, a client, a
 * product named from a two-character prefix and one holding it inside its name (FX-07) —, the signed-in account an Admin of the first
 * company only with `projects.view` (CS-07; DP-06). Local only; synthetic names and numbers.
 */
final class JourneyMasterFixture
{
    /** CJ-19's Admin: projects in the company, nothing of the numbering (OD-03; C-10). */
    private const ADMIN = ['projects.view', 'projects.manage'];

    /** More than PF-21's 200 candidates, so the name search is cut (MSG-23; UXS-33). */
    private const BULK = 205;

    /** The second company's projects sharing the stamp: outside the Admin's scope. */
    private const OUTSIDE = 5;

    /** The first bulk number, above the one each company's own project drew. */
    private const FIRST = 1001;

    public static function admin(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return response()->json(['error' => 'not signed in'], 401);
        }

        $company = Company::query()->where('public_id', self::text($request->input('company')))->firstOrFail();
        Authorization::grantCompany($user, $company->id);

        foreach (self::ADMIN as $capability) {
            Authorization::grant($user, $capability);
        }

        return response()->json(['ok' => true]);
    }

    public static function search(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return response()->json(['error' => 'not signed in'], 401);
        }

        $bulk = self::stamp($request, 'bulk', 6, 20);
        $name = self::stamp($request, 'name', 6, 20);
        $hidden = self::stamp($request, 'hidden', 6, 20);
        $prefix = self::stamp($request, 'prefix', 2, 2);

        if ($bulk === null || $name === null || $hidden === null || $prefix === null) {
            return response()->json(['error' => 'stamps: lower-case letters only, the prefix two'], 422);
        }

        if (! DB::table('roles')->where('code', 'OWNER')->exists()) {
            app(CatalogueSync::class)->run();
        }

        // The actions' actor: an Owner of this fixture, never the Admin the journey signs in as.
        $owner = Authorization::owner();
        $year = substr(BusinessDate::today(), 0, 4);
        $mine = self::world($owner, $year, ucfirst($bulk).' Proyek ', self::BULK, null);
        $theirs = self::world($owner, $year, ucfirst($bulk).' Proyek Luar ', self::OUTSIDE, ucfirst($hidden).' Rahasia');

        if (is_string($mine) || is_string($theirs)) {
            return response()->json(['error' => is_string($mine) ? $mine : $theirs], 500);
        }

        $own = $mine['company'];
        Authorization::grantCompany($user, $own->id);
        Authorization::grant($user, 'projects.view');

        $client = 'SMA '.ucfirst($name).' Negeri';
        DB::table('client_organizations')->where('id', $mine['client'])->update(['name' => $client]);
        $named = Projects::product();
        $sku = 'SKU-'.Str::upper($name);
        $named['product']->forceFill(['name' => 'Pensil '.ucfirst($name), 'sku' => $sku])->save();
        $barcode = '899'.str_pad((string) random_int(0, 9_999_999_999), 10, '0', STR_PAD_LEFT);
        ProductBarcode::factory()->create(['product_id' => $named['product']->id, 'code' => $barcode, 'kind' => 'MANUFACTURER']);
        $prefixed = Projects::product();
        $prefixed['product']->forceFill(['name' => ucfirst($prefix).$name.' Penggaris'])->save();
        $middle = Projects::product();
        $middle['product']->forceFill(['name' => 'Penggaris '.ucfirst($prefix).$name])->save();

        return response()->json([
            'own' => [
                'company' => $own->public_id,
                'code' => $own->code,
                'project_number' => self::text(DB::table('projects')->where('company_id', $own->id)->orderBy('id')->value('project_number')),
                'document_number' => self::text(DB::table('document_versions')->where('company_id', $own->id)->whereNotNull('formatted_number')->orderBy('id')->value('formatted_number')),
                'client' => $client,
                'product' => $named['product']->name,
                'sku' => $sku,
                'barcode' => $barcode,
                'prefixed' => $prefixed['product']->name,
                'middle' => $middle['product']->name,
            ],
            'other' => ['company' => $theirs['company']->public_id, 'code' => $theirs['company']->code],
        ]);
    }

    /**
     * One company of the search world: its project and issued quotation through their actions (QuotationFixture), then `count` projects
     * titled `title` and a number in bulk — and the one titled `alone` when given —, its PROJECT counter raised above them.
     *
     * @return array{company: Company, client: int}|string
     */
    private static function world(User $owner, string $year, string $title, int $count, ?string $alone): array|string
    {
        $code = 'K'.Str::upper(Str::random(9));
        $company = Company::factory()->create(['code' => $code, 'legal_name' => "CV Uji Cari {$code}"]);
        $quotation = Request::create('/', 'POST', ['company' => $company->public_id, 'stage' => 'sent', 'lines' => 1]);
        $quotation->setUserResolver(fn (): User => $owner);
        $made = QuotationFixture::create($quotation);

        if ($made->getStatusCode() !== 200) {
            return "quotation {$code}: ".self::text($made->getContent());
        }

        $client = self::int(DB::table('projects')->where('company_id', $company->id)->value('client_organization_id'));
        $rows = [];

        foreach (range(1, $count) as $n) {
            $rows[] = self::project($company->id, $client, $year, self::FIRST + $n - 1, $title.$n);
        }

        if ($alone !== null) {
            $rows[] = self::project($company->id, $client, $year, self::FIRST + $count, $alone);
        }

        DB::table('projects')->insert($rows);
        // The counter above every bulk number (C-10; DATABASE §3.1's verified family), never lowered.
        $raised = DB::table('number_sequences')->where('company_id', $company->id)->where('sequence_type', 'PROJECT')->where('period_key', $year)
            ->update(['next_value' => DB::raw('greatest(next_value, '.(self::FIRST + $count + 1).')')]);

        return $raised === 1 ? ['company' => $company, 'client' => $client] : "counter {$code}";
    }

    /** @return array<string, int|string> */
    private static function project(int $company, int $client, string $year, int $sequence, string $title): array
    {
        return [
            'company_id' => $company,
            'project_number' => sprintf('UJI/PRJ/%s/%04d', $year, $sequence),
            'period_key' => $year,
            'sequence_no' => $sequence,
            'client_organization_id' => $client,
            'channel' => 'REGULAR',
            'title' => $title,
        ];
    }

    /** A stamp of the run, lower-case letters only, so a term built from it holds a trigram and never reads as a code. */
    private static function stamp(Request $request, string $key, int $min, int $max): ?string
    {
        $value = self::text($request->input($key));

        return preg_match('/^[a-z]{'.$min.','.$max.'}$/', $value) === 1 ? $value : null;
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    private static function int(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
}

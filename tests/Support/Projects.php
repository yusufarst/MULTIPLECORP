<?php

namespace Tests\Support;

use App\Modules\CAT\Models\Product;
use App\Modules\CAT\Models\ProductPriceHistory;
use App\Modules\CAT\Models\ProductUnit;
use App\Modules\CAT\Models\Unit;
use App\Modules\ORG\Enums\SequenceType;
use App\Modules\ORG\Models\Company;
use App\Modules\PRJ\Actions\CreateProject;
use App\Modules\PRJ\Queries\ProjectForm;
use App\Modules\PTY\Models\ClientAddress;
use App\Modules\PTY\Models\ClientContact;
use App\Modules\PTY\Models\ClientOrganization;
use App\Modules\PTY\Models\ClientUnit;
use App\Support\BusinessDate;
use App\Support\RowHandle;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The project tests' fixtures (TASK-032): a company whose PROJECT numbering is live this year (NM-03), a client with its
 * unit, address and PIC, goods and a service with their units and a default selling price, PG-013's request as the
 * form sends it, and a creation through ROUTE-151's own action answering its outcome as a word, so a session in another
 * process can return it. Synthetic data only.
 */
final class Projects
{
    public const TEMPLATE = 'UJI/PRJ/{tahun}/{nomor:4}';

    /** A company whose PROJECT numbering started before this year, so its period is live (NM-03). */
    public static function company(): Company
    {
        $year = substr(BusinessDate::today(), 0, 4);
        Numbering::goLive("{$year}-01-01");
        $company = Company::factory()->create();
        Numbering::scheme($company, SequenceType::Project, "{$year}-01-01", 'YEAR', self::TEMPLATE);
        Numbering::started($company, SequenceType::Project, CarbonImmutable::parse(((int) $year - 1).'-12-31 03:00:00', 'UTC'));

        return $company;
    }

    /** @return array{organization: ClientOrganization, unit: ClientUnit, address: ClientAddress, contact: ClientContact} */
    public static function client(): array
    {
        $organization = ClientOrganization::factory()->create();
        $unit = ClientUnit::factory()->create(['client_organization_id' => $organization->id]);

        return [
            'organization' => $organization,
            'unit' => $unit,
            'address' => ClientAddress::factory()->create(['client_unit_id' => $unit->id]),
            'contact' => ClientContact::factory()->create(['client_unit_id' => $unit->id]),
        ];
    }

    /**
     * A product with its base unit and, for goods, a box of twelve; its default selling price when given.
     *
     * @return array{product: Product, base: Unit, box: ?Unit}
     */
    public static function product(string $kind = 'GOODS', ?string $price = '15000.00'): array
    {
        // Codes of their own, so the browser fixture's requests — each a fresh process whose factory sequences restart — never collide.
        $tag = Str::upper(Str::random(6));
        $base = Unit::factory()->create(['code' => "S{$tag}"]);
        $product = Product::factory()->create(['kind' => $kind, 'sku' => "UJI-{$tag}", 'base_unit_id' => $base->id, 'quantity_scale' => 0]);
        ProductUnit::factory()->create(['product_id' => $product->id, 'unit_id' => $base->id, 'ratio_to_base' => 1, 'is_base' => true]);
        $box = null;

        if ($kind === 'GOODS') {
            $box = Unit::factory()->create(['code' => "K{$tag}"]);
            ProductUnit::factory()->create(['product_id' => $product->id, 'unit_id' => $box->id, 'ratio_to_base' => 12]);
        }

        if ($price !== null) {
            ProductPriceHistory::factory()->create(['product_id' => $product->id, 'price_kind' => 'SELLING_DEFAULT', 'amount' => $price, 'effective_from' => '2020-01-01']);
        }

        return ['product' => $product, 'base' => $base, 'box' => $box];
    }

    /**
     * PG-013's request: the company, the client with its unit, address and PIC, the title, one line per product given.
     *
     * @param  array{organization: ClientOrganization, unit: ClientUnit, address: ClientAddress, contact: ClientContact}  $client
     * @param  list<array{product: Product, base: Unit, box: ?Unit}>  $products
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function payload(Company $company, array $client, array $products, array $overrides = []): array
    {
        return [
            'command_id' => (string) Str::uuid(),
            'perusahaan' => $company->public_id,
            'klien' => $client['organization']->public_id,
            'unit' => RowHandle::of('client_units', $client['unit']->id),
            'alamat' => RowHandle::of('client_addresses', $client['address']->id),
            'pic' => RowHandle::of('client_contacts', $client['contact']->id),
            'kanal' => 'REGULAR',
            'judul' => 'Pengadaan buku perpustakaan',
            'tenggat' => null,
            'tanggal' => BusinessDate::today(),
            'tanggal_otomatis' => true,
            'baris' => array_map(fn (array $line): array => [
                'produk' => $line['product']->public_id,
                'satuan' => $line['base']->code,
                'jumlah' => '10',
                'harga' => '15000',
                'mode' => $line['product']->kind === 'SERVICE' ? 'SERVICE' : 'WAREHOUSE',
            ], $products),
            ...$overrides,
        ];
    }

    /**
     * ROUTE-151's action as its own command: `COMMITTED <number>`, or the refusal's kind and code.
     *
     * @param  array{organization: ClientOrganization, unit: ClientUnit, address: ClientAddress, contact: ClientContact}  $client
     * @param  list<array{product: Product, base: Unit, box: ?Unit}>  $products
     * @param  array<string, mixed>  $overrides
     */
    public static function create(int $actorId, Company $company, array $client, array $products, ?string $goLive = null, array $overrides = []): string
    {
        if ($goLive !== null) {
            Numbering::goLive($goLive);
        }

        $payload = self::payload($company, $client, $products, $overrides);
        $input = app(ProjectForm::class)->resolve(app(ProjectForm::class)->viewer($actorId), $payload, null);
        $outcome = app(CreateProject::class)->run((string) $payload['command_id'], $actorId, $input, BusinessDate::today(), true);

        if ($outcome->kind !== 'COMMITTED') {
            return trim("{$outcome->kind} ".($outcome->code ?? ''));
        }

        return 'COMMITTED '.(string) DB::table('projects')->where('id', $outcome->resultId)->value('project_number');
    }
}

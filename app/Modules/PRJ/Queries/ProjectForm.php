<?php

namespace App\Modules\PRJ\Queries;

use App\Modules\CAT\Data\Quantity;
use App\Modules\IAM\Data\Authority;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Modules\PRJ\Data\LineInput;
use App\Modules\PRJ\Data\ProjectInput;
use App\Modules\PRJ\Models\Project;
use App\Modules\PRJ\Services\ProjectRecords;
use App\Modules\PTY\Queries\ClientDirectory;
use App\Support\BusinessDate;
use App\Support\RowHandle;
use App\Support\Search\Row;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * PG-013 *Formulir Proyek*'s reads (ROUTE-013; SCR-11; NAV-013; WF-PRJ-01; J-PRJ-01): the companies the actor may start
 * a project in — in its `projects.manage` scope and active (CS-03; BR-ACC-03) —, the project an amendment edits, the
 * client's units, addresses and PICs once a client is chosen (J-MD-02), a product's units once a line names it; and the
 * resolution of the form's public identifiers and handles through the scope resolver, a reference outside the actor's
 * scope failing exactly like a nonexistent one (CS-04; AZ-11; architecture challenge 1a). Selling prices, the output
 * taxes in force and SIPLAH amounts reach only a viewer with `finance.view` of the company (PJ-12).
 */
final class ProjectForm
{
    public const LINES = 200;

    public const MODES = ['WAREHOUSE', 'DROPSHIP', 'SERVICE'];

    private const UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    public function __construct(
        private readonly EffectiveCapabilities $capabilities,
        private readonly ClientDirectory $clients,
    ) {}

    public function viewer(int $viewerId): Authority
    {
        return $this->capabilities->for($viewerId);
    }

    /**
     * The form's props: the companies offered, the project amended, the chosen client's rows and the chosen product.
     *
     * @return array<string, mixed>
     */
    public function screen(int $viewerId, ?Project $project, ?string $client, ?string $product): array
    {
        $viewer = $this->viewer($viewerId);
        $companies = $this->companies($viewer, $project?->company_id);
        $organization = $client === null ? $project?->client_organization_id : ($this->organization($client)['id'] ?? null);

        return [
            'companies' => $companies,
            'project' => $project === null ? null : $this->project($viewer, $project),
            'client' => $organization === null ? null : $this->clients->detailOf($viewer, $organization),
            'product' => $product === null ? null : ($this->products($viewer, [$product])[strtolower($product)] ?? null),
            'today' => BusinessDate::today(),
        ];
    }

    /**
     * Resolves the validated request into the command's input, or refuses it at its fields (IP-14).
     *
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function resolve(Authority $viewer, array $input, ?Project $project): ProjectInput
    {
        $errors = [];
        $unknown = self::say('projects.validation.unknown');
        $company = $this->company($viewer, self::text($input['perusahaan'] ?? null));
        $errors += $company === null ? ['perusahaan' => $unknown] : [];
        $money = $company !== null && $viewer->allows('finance.view', $company);
        $organization = $this->organization(self::text($input['klien'] ?? null));

        if ($organization === null || (! $organization['active'] && $organization['id'] !== $project?->client_organization_id)) {
            $errors['klien'] = $unknown;
        }

        $unit = $organization === null ? null : $this->clients->rowIn($organization['id'], 'unit', self::nullable($input['unit'] ?? null));
        $address = $organization === null ? null : $this->clients->rowIn($organization['id'], 'address', self::nullable($input['alamat'] ?? null));
        $contact = $organization === null ? null : $this->clients->rowIn($organization['id'], 'contact', self::nullable($input['pic'] ?? null));

        foreach (['unit' => $unit, 'alamat' => $address, 'pic' => $contact] as $field => $id) {
            if (self::nullable($input[$field] ?? null) !== null && $id === null) {
                $errors[$field] = $unknown;
            }
        }

        foreach (['alamat' => ['client_addresses', $address], 'pic' => ['client_contacts', $contact]] as $field => [$table, $id]) {
            if ($id !== null && ($unit === null || DB::table($table)->where('id', $id)->value('client_unit_id') !== $unit)) {
                $errors[$field] = self::say('projects.validation.of_unit');
            }
        }

        $linked = $this->linked($viewer, self::nullable($input['tautan'] ?? null), $company, $project);

        if (self::nullable($input['tautan'] ?? null) !== null && $linked === null) {
            $errors['tautan'] = $unknown;
        }

        [$lines, $lineErrors] = $this->lines($viewer, is_array($input['baris'] ?? null) ? array_values($input['baris']) : [], $project, $money);
        $errors += $lineErrors;

        if ($errors !== [] || $company === null || $organization === null) {
            throw ValidationException::withMessages($errors);
        }

        $channel = self::text($input['kanal'] ?? null) === 'SIPLAH' ? 'SIPLAH' : 'REGULAR';
        $siplah = null;

        if ($channel === 'SIPLAH') {
            $entered = is_array($input['siplah'] ?? null) ? $input['siplah'] : [];
            $siplah = [];

            foreach ([...ProjectInput::SIPLAH_TEXTS, ...ProjectInput::SIPLAH_AMOUNTS] as $field) {
                $siplah[$field] = self::nullable($entered[$field] ?? null);
            }
        }

        return new ProjectInput($company, $organization['id'], $unit, $address, $contact, $channel, trim(self::text($input['judul'] ?? null)), self::nullable($input['tenggat'] ?? null),
            $linked, $linked === null ? null : trim(self::text($input['alasan_tautan'] ?? null)), $lines, $siplah, $money, self::payload($input));
    }

    /**
     * UXS-28's refusal of a creation in a period whose numbering has not started: the company's name and, for the Owner,
     * PG-061's address to start it (NM-03; MSG-17).
     *
     * @return array{company: string, numbering: ?string}
     */
    public function numberingOf(Authority $viewer, int $companyId): array
    {
        $company = DB::table('companies')->where('id', $companyId)->first(['public_id', 'legal_name']);

        return [
            'company' => $company === null ? '' : Row::text($company, 'legal_name'),
            'numbering' => $viewer->ownerRole && $company !== null ? '/pengaturan/penomoran?'.http_build_query(['perusahaan' => Row::text($company, 'public_id'), 'jenis' => 'project']) : null,
        ];
    }

    /** The public identifier of a project a command just wrote. */
    public function publicIdOf(int $projectId): string
    {
        $id = DB::table('projects')->where('id', $projectId)->value('public_id');

        return is_string($id) ? $id : '';
    }

    /**
     * The companies a new project may be started in, each with whether its prices are visible and the output taxes in
     * force today; for an amendment also the project's own, which it may keep.
     *
     * @return list<array{id: string, code: string, name: string, money: bool, taxes: list<string>}>
     */
    private function companies(Authority $viewer, ?int $own): array
    {
        $rows = $viewer->scope->apply(DB::table('companies'), 'id')
            ->where(fn (Builder $where) => $where->where('is_active', true)->when($own !== null, fn (Builder $or) => $or->orWhere('id', $own)))
            ->orderBy('code')->get(['id', 'public_id', 'code', 'legal_name'])->all();
        $ids = [];

        foreach ($rows as $row) {
            if ($viewer->allows('projects.manage', (int) Row::text($row, 'id'))) {
                $ids[] = (int) Row::text($row, 'id');
            }
        }

        $taxes = $this->taxes(array_values(array_filter($ids, fn (int $id): bool => $viewer->allows('finance.view', $id))));
        $companies = [];

        foreach ($rows as $row) {
            $id = (int) Row::text($row, 'id');

            if (in_array($id, $ids, true)) {
                $companies[] = ['id' => Row::text($row, 'public_id'), 'code' => Row::text($row, 'code'), 'name' => Row::text($row, 'legal_name'),
                    'money' => $viewer->allows('finance.view', $id), 'taxes' => $taxes[$id] ?? []];
            }
        }

        return $companies;
    }

    /**
     * The output taxes a sale of each company resolves today — active output tax types with a rate in force and a
     * SALES_INVOICE treatment, the company's rule over the global one (DIR-024 D-1; BR-FIN-17) —, named with their
     * rate. Shown only: a project line stores no tax; its components are pinned at confirmation (TASK-034).
     *
     * @param  list<int>  $companyIds
     * @return array<int, list<string>>
     */
    private function taxes(array $companyIds): array
    {
        if ($companyIds === []) {
            return [];
        }

        $today = BusinessDate::today();
        $rows = DB::table('tax_types as t')
            ->join('tax_rate_versions as r', fn (JoinClause $join) => $join->on('r.tax_type_id', '=', 't.id')->whereRaw('r.effective @> ?::date', [$today]))
            ->join('tax_treatment_rules as u', fn (JoinClause $join) => $join->on('u.tax_type_id', '=', 't.id')->where('u.transaction_context', 'SALES_INVOICE')->whereRaw('u.effective @> ?::date', [$today]))
            ->where('t.category', 'OUTPUT_TAX')->where('t.is_active', true)
            ->where(fn (Builder $where) => $where->whereNull('u.company_id')->orWhereIn('u.company_id', $companyIds))
            ->orderBy('t.code')
            ->get(['t.code', 'r.rate_percent', 'u.company_id'])->all();
        $taxes = [];

        foreach ($companyIds as $company) {
            foreach ($rows as $row) {
                $owner = Row::nullable($row, 'company_id');

                if ($owner === null || (int) $owner === $company) {
                    $label = Row::text($row, 'code').' '.rtrim(rtrim(Row::text($row, 'rate_percent'), '0'), '.').'%';
                    $taxes[$company][$label] = $label;
                }
            }
        }

        return array_map(fn (array $labels): array => array_values($labels), $taxes);
    }

    /** @return array<string, mixed> */
    private function project(Authority $viewer, Project $project): array
    {
        $money = $viewer->allows('finance.view', $project->company_id);
        $company = DB::table('companies')->where('id', $project->company_id)->first(['public_id', 'code']);
        $lines = DB::table('project_lines as l')->join('products as p', 'p.id', '=', 'l.product_id')->join('units as u', 'u.id', '=', 'l.entered_unit_id')
            ->where('l.project_id', $project->id)->orderBy('l.line_no')
            ->get(['l.id', 'l.description', 'l.entered_qty', 'l.unit_price', 'l.fulfillment_mode', 'l.current_pin_id', 'p.public_id as product', 'u.code as unit'])->all();
        $products = $this->products($viewer, array_values(array_unique(array_map(fn (object $line): string => Row::text($line, 'product'), $lines))));
        $siplah = DB::table('project_siplah_details')->where('project_id', $project->id)->first([...ProjectInput::SIPLAH_TEXTS, ...ProjectInput::SIPLAH_AMOUNTS]);
        $link = $project->linked_project_id === null ? null : DB::table('projects')->where('id', $project->linked_project_id)->first(['public_id', 'project_number', 'title', 'company_id']);
        $linkVisible = $link !== null && $viewer->allows('projects.view', (int) Row::text($link, 'company_id'));

        return [
            'id' => $project->public_id,
            'number' => $project->project_number,
            'company' => $company === null ? '' : Row::text($company, 'public_id'),
            'company_code' => $company === null ? '' : Row::text($company, 'code'),
            'state' => $project->state,
            'lock_version' => $project->lock_version,
            'client' => self::text(DB::table('client_organizations')->where('id', $project->client_organization_id)->value('public_id')),
            'unit' => $project->client_unit_id === null ? null : RowHandle::of('client_units', $project->client_unit_id),
            'address' => $project->client_address_id === null ? null : RowHandle::of('client_addresses', $project->client_address_id),
            'contact' => $project->client_contact_id === null ? null : RowHandle::of('client_contacts', $project->client_contact_id),
            'channel' => $project->channel,
            'title' => $project->title,
            'deadline' => $project->deadline?->toDateString(),
            'link' => $link === null ? null : ($linkVisible
                ? ['id' => Row::text($link, 'public_id'), 'number' => Row::text($link, 'project_number'), 'title' => Row::text($link, 'title'), 'visible' => true, 'reason' => $project->link_reason]
                : ['id' => null, 'number' => null, 'title' => null, 'visible' => false, 'reason' => $project->link_reason]),
            'siplah' => $siplah === null ? null : array_filter((array) $siplah, fn (string $key): bool => $money || in_array($key, ProjectInput::SIPLAH_TEXTS, true), ARRAY_FILTER_USE_KEY),
            'lines' => array_map(fn (object $line): array => [
                'handle' => RowHandle::of('project_lines', (int) Row::text($line, 'id')),
                'product' => $products[Row::text($line, 'product')] ?? null,
                'description' => Row::text($line, 'description'),
                'quantity' => Row::text($line, 'entered_qty'),
                'unit' => Row::text($line, 'unit'),
                'mode' => Row::text($line, 'fulfillment_mode'),
                ...($money ? ['price' => Row::nullable($line, 'unit_price')] : []),
            ], $lines),
            'confirmed' => ProjectRecords::confirmed($project->id),
            'channel_locked' => ProjectRecords::channelLocked($project->id),
        ];
    }

    /**
     * Active products by public identifier, each with its kind, scale and units, and its default selling price to a
     * viewer who may see prices in some company (PJ-14).
     *
     * @param  list<string>  $publicIds
     * @return array<string, array{id: string, name: string, sku: ?string, kind: string, scale: int, units: list<array{code: string, name: string, ratio: int}>, price?: ?string}>
     */
    private function products(Authority $viewer, array $publicIds): array
    {
        $publicIds = array_values(array_filter(array_map('strtolower', $publicIds), fn (string $id): bool => preg_match(self::UUID, $id) === 1));

        if ($publicIds === []) {
            return [];
        }

        $rows = DB::table('products')->whereIn('public_id', $publicIds)->get(['id', 'public_id', 'name', 'sku', 'kind', 'quantity_scale'])->all();
        $ids = array_map(fn (object $row): int => (int) Row::text($row, 'id'), $rows);
        $units = DB::table('product_units as pu')->join('units as u', 'u.id', '=', 'pu.unit_id')->whereIn('pu.product_id', $ids)
            ->orderByDesc('pu.is_base')->orderBy('pu.ratio_to_base')->get(['pu.product_id', 'u.code', 'u.name', 'pu.ratio_to_base'])->all();
        $money = $viewer->has('finance.view');
        $prices = $money ? DB::table('product_price_history')->whereIn('product_id', $ids)->where('price_kind', 'SELLING_DEFAULT')->where('effective_from', '<=', BusinessDate::today())
            ->orderBy('product_id')->orderByDesc('effective_from')->orderByDesc('id')->get(['product_id', 'amount'])->all() : [];
        $products = [];

        foreach ($rows as $row) {
            $id = (int) Row::text($row, 'id');
            $price = null;

            foreach ($prices as $candidate) {
                if ((int) Row::text($candidate, 'product_id') === $id) {
                    $price = Row::text($candidate, 'amount');

                    break;
                }
            }

            $products[Row::text($row, 'public_id')] = [
                'id' => Row::text($row, 'public_id'),
                'name' => Row::text($row, 'name'),
                'sku' => Row::nullable($row, 'sku'),
                'kind' => Row::text($row, 'kind'),
                'scale' => (int) Row::text($row, 'quantity_scale'),
                'units' => array_values(array_map(fn (object $unit): array => ['code' => Row::text($unit, 'code'), 'name' => Row::text($unit, 'name'), 'ratio' => (int) Row::text($unit, 'ratio_to_base')],
                    array_filter($units, fn (object $unit): bool => (int) Row::text($unit, 'product_id') === $id))),
                ...($money ? ['price' => $price] : []),
            ];
        }

        return $products;
    }

    /**
     * The form's lines resolved: each product active, its unit one of its units, its quantity above zero and storable at
     * the product's scale (C-09), its mode the product's kind's (WAREHOUSE or DROPSHIP for goods, SERVICE for a
     * service); an existing line named by its handle among the project's own lines.
     *
     * @param  list<mixed>  $entered
     * @return array{0: list<LineInput>, 1: array<string, string>}
     */
    private function lines(Authority $viewer, array $entered, ?Project $project, bool $money): array
    {
        $products = DB::table('products')->whereIn('public_id', array_values(array_filter(array_map(fn (mixed $line): ?string => is_array($line) ? self::nullable($line['produk'] ?? null) : null, $entered))))
            ->get(['id', 'public_id', 'name', 'kind', 'quantity_scale', 'is_active'])->keyBy('public_id')->all();
        $units = DB::table('product_units as pu')->join('units as u', 'u.id', '=', 'pu.unit_id')
            ->whereIn('pu.product_id', array_map(fn (object $product): int => (int) Row::text($product, 'id'), array_values($products)))
            ->get(['pu.product_id', 'u.id', 'u.code', 'pu.ratio_to_base'])->all();
        $existing = $project === null ? [] : DB::table('project_lines')->where('project_id', $project->id)->pluck('id')->all();
        $handles = [];

        foreach ($existing as $id) {
            if (is_numeric($id)) {
                $handles[RowHandle::of('project_lines', (int) $id)] = (int) $id;
            }
        }

        $lines = [];
        $errors = [];

        foreach ($entered as $index => $line) {
            $line = is_array($line) ? $line : [];
            $field = fn (string $name): string => "baris.{$index}.{$name}";
            $product = $products[strtolower(self::text($line['produk'] ?? null))] ?? null;
            $handle = self::nullable($line['baris'] ?? null);
            $id = $handle === null ? null : ($handles[$handle] ?? null);

            if ($handle !== null && $id === null) {
                $errors[$field('produk')] = self::say('projects.validation.unknown');

                continue;
            }

            if ($product === null || ($product->is_active ?? null) !== true && $id === null) {
                $errors[$field('produk')] = self::say('projects.validation.unknown');

                continue;
            }

            $productId = (int) Row::text($product, 'id');
            $unit = null;

            foreach ($units as $candidate) {
                if ((int) Row::text($candidate, 'product_id') === $productId && Row::text($candidate, 'code') === self::text($line['satuan'] ?? null)) {
                    $unit = $candidate;
                }
            }

            if ($unit === null) {
                $errors[$field('satuan')] = self::say('projects.validation.unit');

                continue;
            }

            $quantity = self::text($line['jumlah'] ?? null);
            $scale = (int) Row::text($product, 'quantity_scale');
            $ratio = (int) Row::text($unit, 'ratio_to_base');
            $base = preg_match('/^\d{1,15}(\.\d{1,3})?$/', $quantity) === 1 ? Quantity::toBase($quantity, $ratio) : null;

            if ($base === null || ! Quantity::atScale($base, $scale) || preg_match('/^0+(\.0+)?$/', $base) === 1) {
                $errors[$field('jumlah')] = self::say($base !== null && preg_match('/^0+(\.0+)?$/', $base) !== 1 ? 'projects.validation.scale' : 'projects.validation.quantity');

                continue;
            }

            $kind = Row::text($product, 'kind');
            $mode = self::text($line['mode'] ?? null);

            if ($kind === 'SERVICE' ? $mode !== 'SERVICE' : ! in_array($mode, ['WAREHOUSE', 'DROPSHIP'], true)) {
                $errors[$field('mode')] = self::say('projects.validation.mode');

                continue;
            }

            $description = trim(self::text($line['keterangan'] ?? null));
            $lines[] = new LineInput($id, $productId, $kind, $scale, $description === '' ? Row::text($product, 'name') : $description, $quantity, (int) Row::text($unit, 'id'), $ratio, $base,
                $money ? self::nullable($line['harga'] ?? null) : null, $mode);
        }

        return [$lines, $errors];
    }

    /** The chosen company, in the actor's `projects.manage` scope (CS-03); an amendment's other company changes it only while a DRAFT (L-21). */
    private function company(Authority $viewer, string $publicId): ?int
    {
        if (preg_match(self::UUID, $publicId) !== 1) {
            return null;
        }

        $id = DB::table('companies')->where('public_id', strtolower($publicId))->value('id');

        if (! is_numeric($id) || ! $viewer->allows('projects.manage', (int) $id)) {
            return null;
        }

        return (int) $id;
    }

    /** @return array{id: int, name: string, active: bool}|null */
    private function organization(?string $publicId): ?array
    {
        return $publicId === null ? null : $this->clients->organization(strtolower($publicId));
    }

    /** XL-4: another company's project, in the actor's `projects.manage` scope, or none — out of scope as unknown. */
    private function linked(Authority $viewer, ?string $publicId, ?int $company, ?Project $project): ?int
    {
        if ($publicId === null || preg_match(self::UUID, $publicId) !== 1) {
            return null;
        }

        $row = DB::table('projects')->where('public_id', strtolower($publicId))->first(['id', 'company_id']);

        if ($row === null || (int) Row::text($row, 'id') === $project?->id || (int) Row::text($row, 'company_id') === $company || ! $viewer->allows('projects.manage', (int) Row::text($row, 'company_id'))) {
            return null;
        }

        return (int) Row::text($row, 'id');
    }

    /**
     * The validated request as the fingerprint takes it (CI-02): the fields the command acts on, in their order.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private static function payload(array $input): array
    {
        $keys = ['perusahaan', 'klien', 'unit', 'alamat', 'pic', 'kanal', 'judul', 'tenggat', 'tautan', 'alasan_tautan', 'siplah', 'baris'];

        return array_intersect_key($input, array_flip($keys));
    }

    private static function say(string $key): string
    {
        $text = __($key);

        return is_string($text) ? $text : $key;
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    private static function nullable(mixed $value): ?string
    {
        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }
}

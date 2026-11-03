<?php

namespace Tests\Support;

use App\Modules\FUL\Actions\ConfirmDropShip;
use App\Modules\FUL\Actions\ReturnDropShipToSupplier;
use App\Modules\FUL\Actions\ReverseDropShipConfirmation;
use App\Modules\IAM\Models\User;
use App\Modules\ORG\Models\Company;
use App\Modules\PRJ\Data\ProjectRef;
use App\Modules\PUR\Data\PurchaseEntry;
use App\Modules\PUR\Models\Purchase;
use App\Support\BusinessDate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * TASK-046's world (TL-04–TL-08; FX-04's drop-ship branch): a confirmed project with a DROPSHIP demand line, its purchase
 * of one direct-to-client goods line through ROUTE-171's own action, and ROUTE-205's, ROUTE-206's and ROUTE-207's actions
 * as their own commands, each answering its word. Static, so the two-connection harness's closures can call them.
 */
final class DropshipWorld
{
    public const CAPABILITIES = ['projects.view', 'dropship.confirm', 'fulfillment.correct', 'sales_return.record', 'cost.view', 'evidence.view', 'evidence.upload', 'documents.issue', 'stock.view'];

    public static function actor(Company $company, array $extra = []): User
    {
        return User::query()->findOrFail(Authorization::admin([$company->id], array_values(array_unique([...self::CAPABILITIES, ...$extra])))->id);
    }

    /**
     * The project with one DROPSHIP line of `$demand` units and its purchase of `$ordered` at `$price` each, optionally a serialized product.
     *
     * @return array{project: ProjectRef, line: int, product: int, unit: int, purchase: Purchase, purchase_line: int}
     */
    public static function make(InvoiceWorld $world, string $demand = '10', string $ordered = '10', string $price = '100000', bool $serialized = false): array
    {
        $project = $world->project(['DROPSHIP'], $demand);
        $line = DB::table('project_lines')->where('project_id', $project->id)->orderBy('line_no')->first(['id', 'product_id']) ?? throw new RuntimeException('No demand line.');
        $product = (int) $line->product_id;
        $unit = (int) DB::table('product_units')->where('product_id', $product)->where('is_base', true)->value('unit_id');

        if ($serialized) {
            DB::table('products')->where('id', $product)->update(['is_serialized' => true]);
        }

        $company = Company::query()->findOrFail($project->company_id);

        if (! DB::table('numbering_schemes')->where('company_id', $company->id)->where('sequence_type', 'PURCHASE')->exists()) {
            PurchaseWorld::numbered($company);
        }

        $entry = new PurchaseEntry($company->id, $project->id, PurchaseWorld::supplier()->id, BusinessDate::today(), false, null,
            [['handle' => null, 'kind' => 'GOODS', 'product' => $product, 'line' => (int) $line->id, 'mode' => 'DROPSHIP', 'quantity' => $ordered, 'unit' => $unit, 'price' => $price, 'description' => 'Barang kirim langsung', 'taxes' => []]], []);
        $saved = PurchaseWorld::save(PurchaseWorld::actor($company)->id, $entry);

        if ($saved !== 'COMMITTED') {
            throw new RuntimeException("The purchase was {$saved}.");
        }

        $purchase = PurchaseWorld::latest($company->id);

        return ['project' => InvoiceWorld::ref($project), 'line' => (int) $line->id, 'product' => $product, 'unit' => $unit, 'purchase' => $purchase,
            'purchase_line' => (int) DB::table('purchase_lines')->where('purchase_id', $purchase->id)->orderBy('line_no')->value('id')];
    }

    /** A delivery proof of the company (L-19; PJ-08). */
    public static function proof(int $companyId): int
    {
        return DispatchWorld::evidence(Company::query()->findOrFail($companyId), ['evidence_type' => 'DELIVERY_PROOF']);
    }

    /**
     * ROUTE-205's action: its word.
     *
     * @param  array<string, mixed>  $world  make()'s
     * @param  list<string>  $serials
     * @param  array{recipient: string, condition: ?string, proof: int}|null  $delivery
     */
    public static function confirm(int $actor, array $world, string $qty, string $reference = 'SJ-PMS-001', array $serials = [], ?int $case = null, ?array $delivery = null, ?string $commandId = null, ?int $evidence = null): string
    {
        /** @var ProjectRef $project */
        $project = $world['project'];
        $product = DB::table('products')->where('id', $world['product'])->first(['is_serialized', 'quantity_scale']);
        $lines = [['line' => (int) $world['purchase_line'], 'quantity' => $qty, 'serials' => $serials, 'serialized' => (bool) ($product->is_serialized ?? false), 'scale' => (int) ($product->quantity_scale ?? 0)]];
        $proof = $evidence ?? self::proof($project->companyId);

        return InvoiceWorld::word(app(ConfirmDropShip::class)->run($commandId ?? (string) Str::uuid(), $actor, $project, (int) $world['purchase']->id, $reference, BusinessDate::today(), false, $lines, $proof, $case, $delivery,
            ['qty' => $qty, 'reference' => $reference, 'serials' => $serials, 'case' => $case, 'delivery' => $delivery, 'proof' => $proof]));
    }

    /**
     * ROUTE-206's action: its word.
     *
     * @param  list<array{line: int, quantity: string}>  $items
     * @param  list<string>  $acknowledged
     */
    public static function reverse(int $actor, ProjectRef $project, int $confirmation, array $items, ?string $commandId = null, array $acknowledged = [], ?string $reason = null): string
    {
        return InvoiceWorld::word(app(ReverseDropShipConfirmation::class)->run($commandId ?? (string) Str::uuid(), $actor, $project, $confirmation, $items, 'Salah catat konfirmasi', BusinessDate::today(), false,
            ['items' => $items, 'acknowledged' => $acknowledged, 'run' => $commandId ?? (string) Str::uuid()], $acknowledged, $reason));
    }

    /**
     * ROUTE-207's action: its word.
     *
     * @param  list<array{line: int, quantity: string}>  $items
     * @param  list<string>  $acknowledged
     */
    public static function returned(int $actor, ProjectRef $project, int $confirmation, string $to, array $items, ?int $rack = null, ?string $condition = 'USABLE', ?string $commandId = null, array $acknowledged = [], ?string $reason = null): string
    {
        return InvoiceWorld::word(app(ReturnDropShipToSupplier::class)->run($commandId ?? (string) Str::uuid(), $actor, $project, $confirmation, $to, $items, $rack, $to === ReturnDropShipToSupplier::WAREHOUSE ? $condition : null,
            'Klien mengembalikan barang', BusinessDate::today(), false, ['to' => $to, 'items' => $items, 'rack' => $rack, 'acknowledged' => $acknowledged, 'run' => $commandId ?? (string) Str::uuid()], $acknowledged, $reason));
    }

    /** The newest confirmation and its lines, oldest line first. */
    public static function last(): object
    {
        $confirmation = DB::table('dropship_confirmations')->orderByDesc('id')->first(['id', 'public_id', 'project_id']) ?? throw new RuntimeException('No confirmation.');
        $confirmation->lines = DB::table('dropship_confirmation_lines')->where('dropship_confirmation_id', $confirmation->id)->orderBy('id')->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();

        return $confirmation;
    }

    /** The newest case a return opened. */
    public static function lastCase(): int
    {
        return (int) DB::table('correction_cases')->max('id');
    }

    /** @return array{confirmed: string, delivered: string} the demand line's net confirmed and delivered */
    public static function demand(int $line): array
    {
        $row = DB::table('project_line_balances')->where('project_line_id', $line)->first(['dropship_confirmed_net_qty_base', 'delivered_net_qty_base']);

        return ['confirmed' => (string) ($row->dropship_confirmed_net_qty_base ?? '0'), 'delivered' => (string) ($row->delivered_net_qty_base ?? '0')];
    }

    /** The purchase line's guard. */
    public static function guard(int $purchaseLine): object
    {
        return DB::table('purchase_line_balances')->where('purchase_line_id', $purchaseLine)->first() ?? throw new RuntimeException('No guard.');
    }

    /** A drop-ship line's net cost of a class, as attributed. */
    public static function cost(int $dropshipLine, string $class = 'HPP'): string
    {
        return (string) DB::table('cost_attributions')->where('dropship_confirmation_line_id', $dropshipLine)->where('cost_class', $class)->selectRaw('coalesce(sum(amount), 0)::text as total')->value('total');
    }
}

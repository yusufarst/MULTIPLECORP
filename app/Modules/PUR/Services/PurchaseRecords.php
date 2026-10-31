<?php

namespace App\Modules\PUR\Services;

use App\Modules\CST\Services\CostPosting;
use App\Modules\DOC\Services\EvidenceGuard;
use App\Modules\ORG\Enums\SequenceType;
use App\Modules\ORG\Services\NumberAllocator;
use App\Modules\PUR\Data\PurchaseEntry;
use App\Modules\PUR\Models\Purchase;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditRecorder;
use App\Support\Audit\AuthorityClass;
use App\Support\Command\BusinessDateRule;
use App\Support\Command\CommandContext;
use App\Support\Command\LockSet;
use App\Support\Command\Refused;
use App\Support\Costing\ChargeAllocator;
use App\Support\Money;
use App\Support\RowHandle;
use App\Support\Search\Row;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A purchase's record (WF-PUR-01; WF-PUR-02; BR-PUR-01–BR-PUR-05; L-05; L-18; L-23; L-43; NX-04; AX-29): the header with its supplier's snapshot, its lines at actual prices with their tax components and conversion snapshots, its charges with their source bills and allocation, its number drawn last (NM-01). It changes neither cash nor stock (BR-PUR-03).
 *
 * @phpstan-type Prepared array{handle: ?string, kind: string, product: int, line: ?int, mode: string, quantity: string, unit: int, price: string, description: string, taxes: list<array{type: int, rate: string, amount: string}>, scale: int, ratio: int, base: string, value: Money, tax_rows: list<array<string, string|int>>, noncreditable: Money}
 */
final class PurchaseRecords
{
    /** The room a charge's share rows take in a command's numbering (`command_line_no`, a smallint): twenty charges of up to five hundred lines each. */
    public const SHARE_ROWS = 500;

    public function __construct(
        private readonly PurchasePosting $posting,
        private readonly CostPosting $cost,
        private readonly NumberAllocator $numbers,
        private readonly AuditRecorder $audit,
        private readonly PurchaseTaxes $taxes,
    ) {}

    /** NX-04's set with AX-29's source bills: the project, the company, the supplier and the bills, the number's counter last. */
    public function locks(PurchaseEntry $entry): LockSet
    {
        $set = (new LockSet)->add('companies', ['id' => $entry->companyId])->union($this->numbers->locks($entry->companyId, SequenceType::Purchase, $entry->businessDate));

        if ($entry->projectId !== null) {
            $set = $set->add('projects', ['id' => $entry->projectId]);
        }

        foreach ($entry->charges as $charge) {
            $set = $set->union(EvidenceGuard::locks($charge['bill']));
        }

        return $set;
    }

    /**
     * NX-04's set for an amendment: the purchase with its lines and guards, its charges' bills.
     */
    public static function amendLocks(int $purchaseId): LockSet
    {
        $set = CostPosting::purchaseLocks($purchaseId);

        foreach (DB::table('purchase_charges')->where('purchase_id', $purchaseId)->orderBy('id')->pluck('id') as $charge) {
            $set = $set->union(CostPosting::chargeLocks(is_numeric($charge) ? (int) $charge : 0));
        }

        return $set;
    }

    /**
     * The purchase saved OPEN; its id.
     *
     * @param  array<int, array{id: int, treatment: string}|null>  $rules  the treatment of each tax type on the business date, ORG's reading
     */
    public function record(CommandContext $context, PurchaseEntry $entry, array $rules): int
    {
        BusinessDateRule::check($entry->businessDate, $entry->prefilled);
        $this->ensureRoots($entry);
        $supplier = DB::table('suppliers')->where('id', $entry->supplierId)->first(['name', 'npwp', 'address_lines', 'city', 'is_active']);

        if ($supplier === null || $supplier->is_active !== true) {
            throw Refused::rejected('J-MD-03', ['reason' => 'supplier_inactive']);
        }

        $lines = $this->prepared($entry, $rules);
        $number = $this->numbers->draw($context, $entry->companyId, SequenceType::Purchase, $entry->businessDate);
        $now = $context->now();
        $commandId = $context->request->commandId;
        $actor = $context->request->actorUserId;
        $id = DB::table('purchases')->insertGetId([
            'public_id' => (string) Str::uuid7(), 'company_id' => $entry->companyId, 'purchase_number' => $number->formatted, 'period_key' => $number->periodKey, 'sequence_no' => $number->sequenceNo,
            'supplier_id' => $entry->supplierId, 'supplier_snapshot' => json_encode(['name' => Row::text($supplier, 'name'), 'npwp' => Row::nullable($supplier, 'npwp'), 'address' => Row::nullable($supplier, 'address_lines'), 'city' => Row::nullable($supplier, 'city')], JSON_THROW_ON_ERROR),
            'project_id' => $entry->projectId, 'business_date' => $entry->businessDate, 'supplier_reference' => $entry->reference, 'state' => 'OPEN', 'origin' => 'LIVE',
            'command_id' => $commandId, 'command_line_no' => 1, 'recorded_at' => $now, 'recorded_by' => $actor, 'updated_at' => $now,
        ]);
        $lineIds = $this->insertLines($context, $id, $entry->companyId, $lines, 0);
        $this->attributeNonStock($context, $id, $entry->projectId, $lines, $lineIds, $entry->businessDate);
        $this->recordCharges($context, $id, $entry->companyId, $entry->charges, $entry->businessDate);
        $this->audit->record(action: 'PUR.PurchaseRecorded', authorityClass: AuthorityClass::Adm, actorUserId: $actor, companyId: $entry->companyId, entityType: 'purchase', entityId: $id, entityPublicId: self::publicId($id), reason: null, commandId: $commandId,
            change: AuditChange::created(['number' => $number->formatted, 'supplier' => Row::text($supplier, 'name'), 'lines' => count($lines), 'charges' => count($entry->charges)]), metadata: ['business_date' => $entry->businessDate]);

        return $id;
    }

    /**
     * The lines in the row the guards take: base quantity from the entered unit's ratio snapshot, the value and the non-creditable part of the tax computed here (C-09; BR-SN-01; DIR-024 D-1).
     *
     * @param  array<int, array{id: int, treatment: string}|null>  $rules
     * @return list<Prepared>
     */
    public function prepared(PurchaseEntry $entry, array $rules): array
    {
        $rows = [];

        foreach ($entry->lines as $number => $line) {
            $product = DB::table('products')->where('id', $line['product'])->first(['kind', 'quantity_scale', 'name']);
            $unit = DB::table('product_units')->where('product_id', $line['product'])->where('unit_id', $line['unit'])->where('is_active', true)->value('ratio_to_base');

            if ($product === null || ! is_numeric($unit)) {
                throw Refused::rejected('C-09', ['reason' => 'unit', 'line' => (string) ($number + 1)]);
            }

            if ($line['kind'] === PurchaseEntry::NONSTOCK && $entry->projectId === null) {
                throw Refused::rejected('WF-PUR-02', ['reason' => 'nonstock_replenishment', 'line' => (string) ($number + 1)]);
            }

            if ($line['mode'] === PurchaseEntry::DROPSHIP && ($line['kind'] !== PurchaseEntry::GOODS || $entry->projectId === null)) {
                throw Refused::rejected('WF-FUL-03', ['reason' => 'dropship', 'line' => (string) ($number + 1)]);
            }

            if ($line['kind'] === PurchaseEntry::GOODS && Row::text($product, 'kind') !== 'GOODS') {
                throw Refused::rejected('WF-PUR-01', ['reason' => 'service_as_goods', 'line' => (string) ($number + 1)]);
            }

            if ($line['line'] !== null) {
                $this->ensureDemandLine((int) $line['line'], $entry->projectId, $line['product']);
            }

            $quantity = BigDecimal::of($line['quantity']);
            $base = $quantity->multipliedBy((int) $unit);
            $scale = max(0, (int) Row::text($product, 'quantity_scale'));

            if ($base->isLessThanOrEqualTo(0) || ! $base->isEqualTo($base->toScale($scale, RoundingMode::Down))) {
                throw Refused::rejected('C-09', ['reason' => 'scale', 'line' => (string) ($number + 1)]);
            }

            $value = Money::of((string) $quantity->multipliedBy($line['price'])->toScale(2, RoundingMode::HalfUp));
            $taxes = $this->taxes->components($line['taxes'], $rules, $value);
            $rows[] = [...$line, 'scale' => $scale, 'ratio' => (int) $unit, 'base' => (string) $base, 'value' => $value, 'tax_rows' => $taxes['rows'], 'noncreditable' => $taxes['noncreditable'], 'description' => $line['description'] !== '' ? $line['description'] : Row::text($product, 'name')];
        }

        return $rows;
    }

    /**
     * Lines inserted with their tax components and guards.
     *
     * @param  list<Prepared>  $lines
     * @return list<int>
     */
    public function insertLines(CommandContext $context, int $purchaseId, int $companyId, array $lines, int $after): array
    {
        $ids = [];
        $now = $context->now();

        foreach ($lines as $index => $line) {
            $value = $line['value'];
            $noncreditable = $line['noncreditable'];
            $id = DB::table('purchase_lines')->insertGetId([
                'purchase_id' => $purchaseId, 'company_id' => $companyId, 'line_no' => $after + $index + 1, 'kind' => $line['kind'], 'product_id' => $line['product'], 'quantity_scale' => $line['scale'], 'project_line_id' => $line['line'], 'delivery_mode' => $line['mode'],
                'entered_qty' => $line['quantity'], 'entered_unit_id' => $line['unit'], 'ratio_to_base' => $line['ratio'], 'qty_base' => $line['base'], 'unit_price' => $line['price'], 'line_value' => $value->toString(), 'description' => $line['description'],
                'command_id' => $context->request->commandId, 'command_line_no' => $after + $index + 1, 'recorded_at' => $now, 'recorded_by' => $context->request->actorUserId, 'updated_at' => $now,
            ]);
            $this->taxes->insert($context, $id, $line['tax_rows'], ($after + $index) * 20);
            $this->posting->bear($context, $id, $line['base'], $value->toString(), $noncreditable->toString());
            $ids[] = $id;
        }

        return $ids;
    }

    /**
     * L-18: a non-stock line's cost on its project at the save, once.
     *
     * @param  list<Prepared>  $lines
     * @param  list<int>  $ids
     */
    private function attributeNonStock(CommandContext $context, int $purchaseId, ?int $projectId, array $lines, array $ids, string $businessDate): void
    {
        foreach ($lines as $index => $line) {
            if ($line['kind'] === PurchaseEntry::NONSTOCK && $projectId !== null) {
                $context->created(CostPosting::purchaseLocks($purchaseId));
                $this->posting->posted($context, $ids[$index], $this->cost->nonStockLine($context, $ids[$index], $projectId, $businessDate));
            }
        }
    }

    /**
     * AX-29: each charge with its source bill, its shares over the purchase's lines by its basis, posted through CST.
     *
     * @param  list<array{bill: int, classification: string, amount: string, basis: ?string, values: list<?string>}>  $charges
     */
    public function recordCharges(CommandContext $context, int $purchaseId, int $companyId, array $charges, string $businessDate): void
    {
        foreach ($charges as $index => $charge) {
            $this->recordCharge($context, $purchaseId, $companyId, $charge, $businessDate, $index + 1);
        }
    }

    /**
     * @param  array{bill: int, classification: string, amount: string, basis: ?string, values: list<?string>}  $charge
     */
    public function recordCharge(CommandContext $context, int $purchaseId, int $companyId, array $charge, string $businessDate, int $line): int
    {
        $bill = DB::table('evidence_documents')->where('id', $charge['bill'])->first(['company_id', 'evidence_type']);

        if ($bill === null || (int) Row::text($bill, 'company_id') !== $companyId || Row::text($bill, 'evidence_type') !== 'SUPPLIER_BILL') {
            throw Refused::rejected('L-43', ['reason' => 'source_bill']);
        }

        $amount = Money::of($charge['amount']);
        $id = DB::table('purchase_charges')->insertGetId([
            'purchase_id' => $purchaseId, 'company_id' => $companyId, 'source_bill_evidence_id' => $charge['bill'], 'source_bill_evidence_type' => 'SUPPLIER_BILL', 'classification' => $charge['classification'], 'amount' => $amount->toString(),
            'allocation_basis' => $charge['classification'] === PurchaseEntry::ATTRIBUTABLE ? $charge['basis'] : null, 'command_id' => $context->request->commandId, 'command_line_no' => $line, 'recorded_at' => $context->now(), 'recorded_by' => $context->request->actorUserId,
        ]);

        if ($charge['classification'] === PurchaseEntry::ATTRIBUTABLE) {
            $this->insertShares($context, $id, $companyId, $purchaseId, $amount, (string) $charge['basis'], $charge['values'], 1, $line * self::SHARE_ROWS);
        }

        $context->created(CostPosting::chargeLocks($id));
        $this->cost->chargeRecorded($context, $id, $businessDate);

        return $id;
    }

    /**
     * The charge's version of shares over its purchase's lines, residual-absorbing (BR-FIN-13): the basis values the purchase's own figures give, or those entered for a weight or an explicit split.
     *
     * @param  list<?string>  $entered  in line order
     */
    public function insertShares(CommandContext $context, int $chargeId, int $companyId, int $purchaseId, Money $amount, string $basis, array $entered, int $version, int $after): void
    {
        $values = [];

        foreach (DB::table('purchase_lines')->where('purchase_id', $purchaseId)->orderBy('line_no')->get(['id', 'line_value', 'qty_base']) as $position => $line) {
            $values[(int) Row::text($line, 'id')] = match ($basis) {
                'LINE_VALUE' => Row::text($line, 'line_value'),
                'QUANTITY' => Row::text($line, 'qty_base'),
                default => (string) ($entered[$position] ?? '0'),
            };
        }

        $positive = array_filter($values, fn (string $value): bool => BigDecimal::of($value)->isPositive());

        if ($positive === []) {
            throw Refused::rejected('SF-CHARGE', ['reason' => 'basis']);
        }

        $shares = ChargeAllocator::shares($amount, $positive);
        // A new version names every line of the old one, a line the charge no longer covers at a zero share (DATABASE §6.3 step 2).
        $covered = DB::table('purchase_charge_share_balances')->where('purchase_charge_id', $chargeId)->pluck('purchase_line_id')->map(fn (mixed $line): int => is_numeric($line) ? (int) $line : 0)->all();
        $row = $after;

        foreach ($values as $line => $value) {
            if (isset($shares[$line]) || in_array($line, $covered, true)) {
                DB::table('purchase_charge_line_shares')->insert(['purchase_charge_id' => $chargeId, 'company_id' => $companyId, 'purchase_line_id' => $line, 'basis_value' => $value, 'share_amount' => isset($shares[$line]) ? $shares[$line]->toString() : '0', 'allocation_version' => $version,
                    'command_id' => $context->request->commandId, 'command_line_no' => ++$row, 'recorded_at' => $context->now(), 'recorded_by' => $context->request->actorUserId]);
            }
        }
    }

    /**
     * ROUTE-172 (L-23; ST-01; NX-04): the goods lines edited in place before the purchase's first effect, under the purchase's version, which covers them and their tax components. Each goods line stays — none is ever deleted —,
     * a line may be added; non-stock lines, company, project and number are not touched. An attributable charge follows the new figures by a new version of its shares.
     *
     * @param  array<int, array{id: int, treatment: string}|null>  $rules
     */
    public function amend(CommandContext $context, Purchase $purchase, int $lockVersion, PurchaseEntry $entry, array $rules): void
    {
        $purchase->refresh();

        if ($purchase->state !== 'OPEN') {
            throw Refused::conflict('ST-02', ['state' => $purchase->state]);
        }

        if ($purchase->lock_version !== $lockVersion) {
            throw Refused::conflict('ST-01', ['version' => $purchase->lock_version]);
        }

        if (PurchaseCorrections::hasFirstEffect($purchase->id)) {
            throw Refused::rejected('L-23', ['reason' => 'first_effect']);
        }

        $existing = [];

        foreach (DB::table('purchase_lines as l')->join('purchase_line_balances as b', 'b.purchase_line_id', '=', 'l.id')->where('l.purchase_id', $purchase->id)->where('l.kind', PurchaseEntry::GOODS)->orderBy('l.line_no')->get(['l.id', 'b.ordered_qty_base', 'b.goods_value', 'b.noncreditable_tax_value']) as $row) {
            $existing[RowHandle::of('purchase_lines', (int) Row::text($row, 'id'))] = $row;
        }

        $sent = array_filter(array_column($entry->lines, 'handle'));

        if (array_diff(array_keys($existing), $sent) !== [] || array_diff($sent, array_keys($existing)) !== [] || count($sent) !== count(array_unique($sent))) {
            throw Refused::rejected('L-23', ['reason' => 'lines']);
        }

        foreach ($entry->lines as $line) {
            if ($line['kind'] !== PurchaseEntry::GOODS) {
                throw Refused::rejected('L-23', ['reason' => 'nonstock']);
            }
        }

        $prepared = $this->prepared($entry, $rules);
        $changes = [];
        $updated = 0;
        $after = is_numeric($last = DB::table('purchase_lines')->where('purchase_id', $purchase->id)->max('line_no')) ? (int) $last : 0;
        $now = $context->now();
        $new = [];

        foreach ($prepared as $line) {
            $value = $line['value'];
            $noncreditable = $line['noncreditable'];
            $handle = $line['handle'];

            if ($handle === null) {
                $new[] = $line;

                continue;
            }

            $row = $existing[$handle];
            $id = (int) Row::text($row, 'id');
            DB::table('purchase_lines')->where('id', $id)->update(['product_id' => $line['product'], 'quantity_scale' => $line['scale'], 'project_line_id' => $line['line'], 'delivery_mode' => $line['mode'], 'entered_qty' => $line['quantity'], 'entered_unit_id' => $line['unit'], 'ratio_to_base' => $line['ratio'], 'qty_base' => $line['base'], 'unit_price' => $line['price'], 'line_value' => $value->toString(), 'description' => $line['description'], 'updated_at' => $now]);
            DB::table('purchase_line_tax_components')->where('purchase_line_id', $id)->delete();
            $this->taxes->insert($context, $id, $line['tax_rows'], $line['scale'] + ++$updated * 20);
            $this->posting->move($context, $id, [
                'ordered_qty_base' => (string) BigDecimal::of($line['base'])->minus(Row::text($row, 'ordered_qty_base')),
                'goods_value' => $value->minus(Money::of(Row::text($row, 'goods_value')))->toString(),
                'noncreditable_tax_value' => $noncreditable->minus(Money::of(Row::text($row, 'noncreditable_tax_value')))->toString(),
            ]);
            $changes[$handle] = ['quantity' => $line['quantity'], 'price' => $line['price'], 'value' => $value->toString()];
        }

        $this->insertLines($context, $purchase->id, $purchase->company_id, $new, $after);
        $context->created(CostPosting::purchaseLocks($purchase->id));
        $this->reallocate($context, $purchase, $entry->businessDate);
        DB::table('purchases')->where('id', $purchase->id)->update(['lock_version' => $lockVersion + 1, 'updated_at' => $now]);
        $this->audit->record(action: 'PUR.PurchaseAmended', authorityClass: AuthorityClass::Adm, actorUserId: $context->request->actorUserId, companyId: $purchase->company_id, entityType: 'purchase', entityId: $purchase->id, entityPublicId: $purchase->public_id, reason: null, commandId: $context->request->commandId,
            change: AuditChange::updated(['lines' => count($existing)], ['lines' => count($prepared), 'changed' => $changes]));
    }

    /** Each attributable charge's shares by the lines' new figures, a new version with its cascade (CM-35; DATABASE §6.3 step 2). */
    private function reallocate(CommandContext $context, Purchase $purchase, string $businessDate): void
    {
        $position = 0;

        foreach (DB::table('purchase_charges')->where('purchase_id', $purchase->id)->where('classification', PurchaseEntry::ATTRIBUTABLE)->orderBy('id')->get(['id', 'amount', 'allocation_basis']) as $charge) {
            $id = (int) Row::text($charge, 'id');
            $version = self::latestVersion($id);
            $kept = DB::table('purchase_lines as l')->leftJoin('purchase_charge_line_shares as s', fn (JoinClause $join) => $join->on('s.purchase_line_id', '=', 'l.id')->where('s.purchase_charge_id', $id)->where('s.allocation_version', $version))
                ->where('l.purchase_id', $purchase->id)->orderBy('l.line_no')->pluck('s.basis_value')->all();
            $amount = DB::table('purchase_charge_balances')->where('purchase_charge_id', $id)->value('amount');
            $this->insertShares($context, $id, $purchase->company_id, $purchase->id, Money::of(is_string($amount) ? $amount : Row::text($charge, 'amount')), Row::text($charge, 'allocation_basis'), array_values(array_map(fn (mixed $value): ?string => is_string($value) ? $value : null, $kept)), $version + 1, ++$position * self::SHARE_ROWS);
            $this->cost->chargeCorrected($context, $id, null, $businessDate);
        }
    }

    public static function latestVersion(int $chargeId): int
    {
        $version = DB::table('purchase_charge_line_shares')->where('purchase_charge_id', $chargeId)->max('allocation_version');

        return is_numeric($version) ? (int) $version : 0;
    }

    public static function publicId(int $purchaseId): string
    {
        $id = DB::table('purchases')->where('id', $purchaseId)->value('public_id');

        return is_string($id) ? $id : '';
    }

    /** The project's company and state, the company active, goods only without a project (L-05; WF-PUR-02; C-54). */
    private function ensureRoots(PurchaseEntry $entry): void
    {
        $company = DB::table('companies')->where('id', $entry->companyId)->value('is_active');

        if ($company !== true) {
            throw Refused::rejected('C-54', ['reason' => 'company_inactive']);
        }

        if ($entry->projectId !== null) {
            $project = DB::table('projects')->where('id', $entry->projectId)->first(['company_id', 'state']);

            if ($project === null || (int) Row::text($project, 'company_id') !== $entry->companyId) {
                throw Refused::rejected('L-05', ['reason' => 'project_company']);
            }

            if (Row::text($project, 'state') !== 'ACTIVE') {
                throw Refused::rejected('WF-PUR-01', ['reason' => 'project_state', 'state' => Row::text($project, 'state')]);
            }
        }

        if ($entry->lines === []) {
            throw Refused::rejected('WF-PUR-01', ['reason' => 'no_lines']);
        }
    }

    private function ensureDemandLine(int $lineId, ?int $projectId, int $productId): void
    {
        $line = DB::table('project_lines')->where('id', $lineId)->first(['project_id', 'product_id', 'current_pin_id']);

        if ($line === null || $projectId === null || (int) Row::text($line, 'project_id') !== $projectId || (int) Row::text($line, 'product_id') !== $productId || Row::nullable($line, 'current_pin_id') === null) {
            throw Refused::rejected('L-05', ['reason' => 'demand_line']);
        }
    }
}

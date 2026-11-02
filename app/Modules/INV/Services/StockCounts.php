<?php

namespace App\Modules\INV\Services;

use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditRecorder;
use App\Support\Audit\AuthorityClass;
use App\Support\Command\CommandContext;
use App\Support\Command\LockSet;
use App\Support\Command\Refused;
use App\Support\Search\Row;
use Brick\Math\BigDecimal;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A count's opening, lines and cancellation (NX-05; WF-INV-05; L-14; L-48; `DIR-100`; `DIR-101`): the scope versions read under their locks and stored as the counted basis, with every open finding the scope carries; one line per request, a repeated serial counting once, an unexpected serial kept as what was seen and never registered; a cancelled count's lines kept and no stock changed.
 */
final class StockCounts
{
    public function __construct(
        private readonly AuditRecorder $audit,
    ) {}

    /** NX-05's set for an opening: the product and each scope it reads, a registration count every place holding the product. */
    public static function openLocks(int $product, ?int $location): LockSet
    {
        $set = (new LockSet)->add('stock_product_balances', ['product_id' => $product]);

        foreach (self::scopesOf($product, $location) as [$place, $condition]) {
            $set->add('stock_scope_balances', ['product_id' => $product, 'location_id' => $place, 'condition' => $condition]);
        }

        return $set;
    }

    /** NX-05's set for a line or a cancellation: the count, and the serial's identity a line names. */
    public static function lineLocks(int $count, int $product, ?string $serialKey): LockSet
    {
        $set = (new LockSet)->add('stock_counts', ['id' => $count]);

        return $serialKey === null ? $set : $set->add('serial_units', ['product_id' => $product, 'serial_key' => $serialKey]);
    }

    /**
     * ROUTE-191 ("Mulai Hitungan", "Hitung Ulang"): the count with its typed basis, in this transaction (L-14; C-33). The count.
     */
    public function open(CommandContext $context, int $product, int $location, string $condition): int
    {
        $context->require(self::openLocks($product, $location));
        self::ensureGoods($product);

        return $this->write($context, $product, 'ORDINARY', $location, $condition, [[$location, $condition]]);
    }

    /** A registration count (`DIR-101` F2): one non-serialized goods product, its scopes every place holding it. */
    public function openRegistration(CommandContext $context, int $product): int
    {
        $context->require(self::openLocks($product, null));
        self::ensureGoods($product);

        if (DB::table('products')->where('id', $product)->value('is_serialized')) {
            throw Refused::rejected('L-35', ['reason' => 'already_serialized']);
        }

        return $this->write($context, $product, 'SERIAL_REGISTRATION', null, null, self::scopesOf($product, null));
    }

    /**
     * ROUTE-192: one observation. A serialized product's line names a serial: repeated, it counts once (MSG-36); one not in this scope is kept as seen, a finding for the application, never registered (L-48). A registration line names its lot and a serial not in stock.
     *
     * @return array{line: int, unexpected: bool}
     */
    public function line(CommandContext $context, int $count, int $location, string $condition, string $qty, ?string $serial, ?int $lot): array
    {
        $row = self::openCount($count);
        $product = self::productOf($row);
        $scope = DB::table('stock_count_scopes')->where('stock_count_id', $count)->where('product_id', $product)->where('location_id', $location)->where('condition', $condition)->first(['quantity_scale']);

        if ($scope === null) {
            throw Refused::rejected('C-14', ['reason' => 'scope']);
        }

        $scale = (int) Row::text($scope, 'quantity_scale');
        $registering = Row::text($row, 'purpose') === 'SERIAL_REGISTRATION';
        $serialized = (bool) DB::table('products')->where('id', $product)->value('is_serialized');
        $unexpected = false;
        $unit = null;
        $text = null;

        if ($registering || $serialized) {
            if ($serial === null || trim($serial) === '') {
                throw Refused::rejected('C-02', ['reason' => 'serial']);
            }

            $key = ReceivingRecords::serialKey($serial);
            $found = DB::table('serial_units')->where('product_id', $product)->where('serial_key', $key)->first(['id', 'state', 'current_location_id', 'current_condition']);
            $id = $found === null ? null : (int) Row::text($found, 'id');
            $repeated = DB::table('stock_count_lines')->where('stock_count_id', $count)
                ->where(fn (Builder $same) => $same->whereRaw("lower(regexp_replace(unregistered_serial_text, '\\s+', '', 'g')) = ?", [$key])->when($id !== null, fn (Builder $known) => $known->orWhere('serial_unit_id', $id)))->exists();

            if ($repeated) {
                throw Refused::rejected('C-02', ['reason' => 'counted', 'serial' => $serial]);
            }

            $present = $found !== null && Row::text($found, 'state') === 'IN_STOCK';

            if ($registering) {
                if ($present) {
                    throw Refused::rejected('C-02', ['reason' => 'in_stock', 'serial' => $serial]);
                }

                if ($lot === null || ! DB::table('stock_lot_balances')->where('lot_id', $lot)->where('product_id', $product)->where('location_id', $location)->where('condition', $condition)->where('qty', '>', 0)->exists()) {
                    throw Refused::rejected('C-14', ['reason' => 'lot']);
                }
            } else {
                $unexpected = ! $present || (int) Row::text($found, 'current_location_id') !== $location || Row::text($found, 'current_condition') !== $condition;
            }

            $unit = $id;
            $text = $id === null ? trim($serial) : null;
            $counted = '1';
        } else {
            $counted = (string) ReceivingRecords::base($qty, 1, $scale);
        }

        $commandId = $context->request->commandId;
        $actor = $context->request->actorUserId;
        $line = (int) DB::table('stock_count_lines')->insertGetId([
            'stock_count_id' => $count, 'product_id' => $product, 'quantity_scale' => $scale, 'location_id' => $location, 'condition' => $condition, 'counted_qty' => $counted, 'serial_unit_id' => $unit, 'unregistered_serial_text' => $text, 'lot_id' => $registering ? $lot : null, 'counted_by' => $actor,
            'command_id' => $commandId, 'command_line_no' => 1, 'recorded_at' => $context->now(), 'recorded_by' => $actor,
        ]);
        $this->audit->record(
            action: 'INV.CountLineRecorded', authorityClass: AuthorityClass::Adm, actorUserId: $actor, companyId: null, entityType: 'stock_count', entityId: $count, entityPublicId: Row::text($row, 'public_id'), commandId: $commandId,
            change: AuditChange::created(['counted' => $counted, 'unexpected' => $unexpected]),
        );

        return ['line' => $line, 'unexpected' => $unexpected];
    }

    /** ROUTE-215: an open count CANCELLED, its lines kept, no stock changed. */
    public function cancel(CommandContext $context, int $count, string $reason): void
    {
        $row = self::openCount($count);

        if (DB::table('stock_counts')->where('id', $count)->where('state', 'OPEN')->update(['state' => 'CANCELLED', 'lock_version' => DB::raw('lock_version + 1'), 'updated_at' => $context->now()]) !== 1) {
            throw Refused::rejected('C-33', ['state' => 'changed']);
        }

        $this->audit->record(
            action: 'INV.CountCancelled', authorityClass: AuthorityClass::Adm, actorUserId: $context->request->actorUserId, companyId: null, entityType: 'stock_count', entityId: $count, entityPublicId: Row::text($row, 'public_id'), reason: $reason, commandId: $context->request->commandId,
            change: AuditChange::updated(['state' => 'OPEN'], ['state' => 'CANCELLED']),
        );
    }

    /** The count's one product. */
    public static function productOf(object $count): int
    {
        return (int) trim(Row::text($count, 'product_ids'), '{}');
    }

    /** The count, read to be OPEN (NX-05). */
    public static function openCount(int $count): object
    {
        $row = DB::table('stock_counts')->where('id', $count)->first(['id', 'public_id', 'purpose', 'state', 'location_id', 'condition', 'product_ids']);

        if ($row === null) {
            throw Refused::rejected('C-14', ['reason' => 'count']);
        }

        if (Row::text($row, 'state') !== 'OPEN') {
            throw Refused::rejected('C-33', ['state' => Row::text($row, 'state')]);
        }

        return $row;
    }

    /**
     * @param  list<array{int, string}>  $scopes
     */
    private function write(CommandContext $context, int $product, string $purpose, ?int $location, ?string $condition, array $scopes): int
    {
        $scale = DB::table('products')->where('id', $product)->value('quantity_scale');
        $actor = $context->request->actorUserId;
        $commandId = $context->request->commandId;
        $now = $context->now();
        $publicId = (string) Str::uuid7();
        $count = (int) DB::table('stock_counts')->insertGetId([
            'public_id' => $publicId, 'location_id' => $location, 'condition' => $condition, 'product_ids' => '{'.$product.'}', 'purpose' => $purpose,
            'command_id' => $commandId, 'command_line_no' => 1, 'recorded_at' => $now, 'recorded_by' => $actor,
        ]);

        foreach ($scopes as [$place, $state]) {
            $guard = DB::table('stock_scope_balances')->where('product_id', $product)->where('location_id', $place)->where('condition', $state)->first(['version', 'qty']);
            $finding = DB::table('stock_count_findings')->where('product_id', $product)->where('location_id', $place)->where('condition', $state)->where('state', 'OPEN')->value('surplus_qty_base');
            DB::table('stock_count_scopes')->insert([
                'stock_count_id' => $count, 'product_id' => $product, 'quantity_scale' => is_numeric($scale) ? (int) $scale : 0, 'location_id' => $place, 'condition' => $state, 'basis_version' => $guard === null ? 0 : (int) Row::text($guard, 'version'),
                // Every later count's basis includes a pending finding (WF-INV-05).
                'system_qty_at_start' => (string) BigDecimal::of($guard === null ? '0' : Row::text($guard, 'qty'))->plus(is_string($finding) ? $finding : '0'), 'recorded_at' => $now,
            ]);
        }

        $this->audit->record(
            action: 'INV.CountOpened', authorityClass: AuthorityClass::Adm, actorUserId: $actor, companyId: null, entityType: 'stock_count', entityId: $count, entityPublicId: $publicId, commandId: $commandId,
            change: AuditChange::created(['purpose' => $purpose, 'scopes' => count($scopes)]),
        );

        return $count;
    }

    private static function ensureGoods(int $product): void
    {
        if (! DB::table('products')->where('id', $product)->where('kind', 'GOODS')->exists()) {
            throw Refused::rejected('C-14', ['reason' => 'product']);
        }
    }

    /**
     * An ordinary scope in both conditions (a lock names both), a registration count every place holding the product.
     *
     * @return list<array{int, string}>
     */
    private static function scopesOf(int $product, ?int $location): array
    {
        if ($location !== null) {
            return [[$location, 'USABLE'], [$location, 'UNUSABLE']];
        }

        $scopes = [];

        foreach (DB::table('stock_scope_balances')->where('product_id', $product)->where('qty', '>', 0)->orderBy('location_id')->orderBy('condition')->get(['location_id', 'condition']) as $row) {
            $scopes[] = [(int) Row::text($row, 'location_id'), Row::text($row, 'condition')];
        }

        return $scopes;
    }
}

<?php

namespace App\Modules\CAT\Actions;

use App\Modules\CAT\Data\CodeKey;
use App\Modules\CAT\Models\Product;
use App\Modules\CAT\Models\ProductBarcode;
use App\Modules\CAT\Services\CatalogCommands;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandResult;
use App\Support\Command\LockSet;
use App\Support\Command\Outcome;
use App\Support\Command\Refused;
use App\Support\Search\Row;
use Illuminate\Support\Facades\DB;

/**
 * `CAT RegisterBarcode` (ROUTE-147; INT-147; NX-01; BR-INV-09; C-01; REF-013; REF-024; IMG-04): the one registration
 * of both entries — "Daftarkan Barcode" from a scan (IP-15) and "Buat Barcode Internal" on PG-056. A manufacturer's code
 * as printed, or an internal code from the database sequence (gaps harmless), normalized to its `code_key` — white
 * space removed, letters upper-cased, leading zeros kept —, which resolves to at most one active product: a code
 * active on another product is REJECTED at its field, one already on this product too; a lost race on the partial
 * unique key is CONFLICT (BD-16; SQ-08). The product row at LK-08; the command changes one row's unique key (LR-03).
 */
final class RegisterBarcode
{
    public const TYPE = 'CAT.RegisterBarcode';

    /** An internal code's printed prefix, so it never collides with a manufacturer's EAN or UPC digits. */
    public const INTERNAL_PREFIX = 'MC';

    public function __construct(private readonly CatalogCommands $commands) {}

    /** @param  'MANUFACTURER'|'INTERNAL'  $kind */
    public function run(string $commandId, int $actorUserId, Product $product, string $kind, ?string $code): Outcome
    {
        return $this->commands->run($commandId, self::TYPE, $actorUserId, ['product' => $product->public_id, 'kind' => $kind, 'code' => $code],
            function (CommandContext $context) use ($product, $kind, $code): CommandResult {
                $context->lock((new LockSet)->add('products', ['id' => $product->id]));
                $row = Product::query()->findOrFail($product->id);

                if (! $row->is_active) {
                    throw Refused::rejected('WF-MD-04', ['reason' => 'archived']);
                }

                $next = $kind === 'INTERNAL' ? DB::selectOne("select nextval('product_barcode_internal_codes') as n") : null;
                $printed = $next !== null ? self::INTERNAL_PREFIX.str_pad(Row::text($next, 'n'), 8, '0', STR_PAD_LEFT) : CodeKey::printed((string) $code);
                $holder = DB::table('product_barcodes')->where('is_active', true)->where('code_key', CodeKey::of($printed))->value('product_id');

                if (is_numeric($holder)) {
                    throw Refused::rejected('BR-INV-09', ['field' => 'code', 'reason' => (int) $holder === $row->id ? 'own' : 'other']);
                }

                $barcode = $this->commands->insert($context, new ProductBarcode, ['product_id' => $row->id, 'code' => $printed, 'kind' => $kind]);
                $this->commands->record($context, 'CAT.BarcodeRegistered', 'product_barcode', $barcode->id, $row->public_id, [], ['code' => $printed, 'kind' => $kind]);

                return new CommandResult('product_barcode', $barcode->id);
            });
    }
}

<?php

namespace App\Modules\ImportOrchestration\Kinds;

use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

/** The import kinds of RELEASE_PLAN IMP-01, in IMP-03's order: masters, opening stock, projects, purchases, money, documents. */
final class Kinds
{
    /** @var array<string, class-string<ImportKind>> */
    public const CLASSES = [
        ClientKind::CODE => ClientKind::class,
        ClientUnitKind::CODE => ClientUnitKind::class,
        SupplierKind::CODE => SupplierKind::class,
        ProductKind::CODE => ProductKind::class,
        LocationKind::CODE => LocationKind::class,
        OpeningStockKind::CODE => OpeningStockKind::class,
        OpenProjectKind::CODE => OpenProjectKind::class,
        OpenPurchaseKind::CODE => OpenPurchaseKind::class,
        OpeningReceivableKind::CODE => OpeningReceivableKind::class,
        OpeningCreditKind::CODE => OpeningCreditKind::class,
        LegacyDocumentKind::CODE => LegacyDocumentKind::class,
    ];

    public function __construct(private readonly Container $app) {}

    public function of(string $code): ImportKind
    {
        if (str_starts_with($code, SeedRows::PREFIX)) {
            return $this->app->make(SeedRows::class);
        }

        $class = self::CLASSES[$code] ?? throw new InvalidArgumentException("Unknown import kind {$code}.");

        return $this->app->make($class);
    }

    /** @return list<string> */
    public static function codes(): array
    {
        return array_keys(self::CLASSES);
    }
}

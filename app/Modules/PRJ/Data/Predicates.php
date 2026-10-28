<?php

namespace App\Modules\PRJ\Data;

/**
 * The ten completion predicates of BR-PRJ-02 (ACCEPTANCE_CRITERIA's completion table; WF-PRJ-03), in that order: each
 * evaluated over facts and decisions only, by its owning module's `CompletionPredicateProvider` — the returns and
 * documents predicates by the lower-tier OPS and DOC query classes PRJ reads directly (ARCHITECTURE §3).
 */
final class Predicates
{
    public const FULFILMENT = 'fulfilment';

    public const DELIVERY = 'delivery';

    public const RETURNS = 'returns';

    public const DOCUMENTS = 'documents';

    public const ADMINISTRATION = 'administration';

    public const BILLING = 'billing';

    public const RECEIVABLE = 'receivable';

    public const STOCK = 'stock';

    public const PAYMENT = 'payment';

    public const AUDIT = 'audit';

    public const ALL = [
        self::FULFILMENT, self::DELIVERY, self::RETURNS, self::DOCUMENTS, self::ADMINISTRATION,
        self::BILLING, self::RECEIVABLE, self::STOCK, self::PAYMENT, self::AUDIT,
    ];
}

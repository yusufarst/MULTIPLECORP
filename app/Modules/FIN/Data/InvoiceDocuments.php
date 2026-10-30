<?php

namespace App\Modules\FIN\Data;

/** The document types an invoice renders (DOC-04, DOC-06) and its pre-payment Kuitansi (DOC-05). */
final class InvoiceDocuments
{
    public const INVOICE = 'DOC-06';

    public const NOTA = 'DOC-04';

    public const KUITANSI = 'DOC-05';

    public const LAYOUTS = [self::INVOICE, self::NOTA];
}

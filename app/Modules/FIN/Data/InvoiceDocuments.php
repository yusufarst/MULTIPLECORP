<?php

namespace App\Modules\FIN\Data;

/** The document types an invoice renders (DOC-04, DOC-06), its pre-payment Kuitansi (DOC-05) and its payment request (DOC-13). */
final class InvoiceDocuments
{
    public const INVOICE = 'DOC-06';

    public const NOTA = 'DOC-04';

    public const KUITANSI = 'DOC-05';

    public const LAYOUTS = [self::INVOICE, self::NOTA];

    /** *Surat Permintaan Pembayaran*: from the invoice and its billing data, no cash effect (BR-DOC-04; TASK-050). */
    public const REQUEST = 'DOC-13';
}

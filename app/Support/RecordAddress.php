<?php

namespace App\Support;

/**
 * The page a record opens by its public identifier (INFORMATION_ARCHITECTURE §11; INT-019): a row of a signal or of
 * the Owner review queue links to its record's own view. A type with no page of its own answers null, and the row is
 * shown as text.
 */
final class RecordAddress
{
    private const PAGES = [
        'project' => '/proyek/{id}',
        'purchase' => '/pembelian/{id}',
        'invoice' => '/keuangan/invoice/{id}',
        'document' => '/dokumen/{id}',
        'correction_case' => '/dokumen/kasus?kasus={id}',
        'evidence_document' => '/dokumen/bukti?bukti={id}',
        'product' => '/gudang/stok/{id}',
        'user' => '/pengaturan/pengguna?akun={id}',
        'client_organization' => '/data-master/klien?klien={id}',
        'supplier' => '/data-master/pemasok?pemasok={id}',
        'import_batch' => '/data-master/impor?batch={id}',
        'payment' => '/keuangan/pembayaran',
        'stock_movement' => '/gudang/riwayat-stok',
        'company' => '/pengaturan/perusahaan',
    ];

    public static function of(?string $type, ?string $publicId): ?string
    {
        $page = $type === null ? null : (self::PAGES[$type] ?? null);

        if ($page === null) {
            return null;
        }

        if (! str_contains($page, '{id}')) {
            return $page;
        }

        return $publicId === null || $publicId === '' ? null : str_replace('{id}', rawurlencode($publicId), $page);
    }
}

<?php

// FX-10 (TEST_STRATEGY §4): an invented sample of each generated document type of V1_SCOPE, DOC-01–DOC-14,
// with representative content for layout and content review (GAP-019; GAP-014; CJ-15). Issuers and
// counterparties are FX-02's and FX-07's; amounts are whole rupiah; numbers are samples, each document
// Task drawing real ones from its numbering scheme.
return [
    'id' => 'FX-10',
    'title' => 'A synthetic sample of each generated document type DOC-01–DOC-14 with representative content, for layout and content review',
    'source' => 'GAP-019; GAP-014',
    'used_by' => 'CJ-15; §12',
    'documents' => ['docs/01-product/V1_SCOPE.md'],
    'entries' => [
        'DOC-01' => [
            'name' => 'Penawaran', 'issuer' => 'ARJ', 'counterparty' => 'SMAN 1 Kota Contoh', 'number' => 'SAMPLE/PNW/0001', 'date' => '2026-02-02',
            'lines' => [['Proyektor Contoh', 2, 'unit', 5_500_000], ['Layar Proyeksi Contoh', 2, 'unit', 750_000]],
            'total' => 12_500_000,
        ],
        'DOC-02' => [
            'name' => 'Purchase Order / PO', 'issuer' => 'ARJ', 'counterparty' => 'CV Contoh Pemasok Lestari', 'number' => 'SAMPLE/PO/0001', 'date' => '2026-02-03',
            'lines' => [['Proyektor Contoh', 2, 'unit', 4_800_000]],
            'total' => 9_600_000,
        ],
        'DOC-03' => [
            'name' => 'Surat Jalan / Faktur Pengiriman', 'issuer' => 'ARJ', 'counterparty' => 'SMAN 1 Kota Contoh', 'number' => 'SAMPLE/SJ/0001', 'date' => '2026-02-10',
            'lines' => [['Proyektor Contoh', 2, 'unit', 0], ['Layar Proyeksi Contoh', 2, 'unit', 0]],
            'total' => 0,
        ],
        'DOC-04' => [
            'name' => 'Nota', 'issuer' => 'ARJ', 'counterparty' => 'SMAN 1 Kota Contoh', 'number' => 'SAMPLE/NOTA/0001', 'date' => '2026-02-10',
            'lines' => [['Proyektor Contoh', 2, 'unit', 5_500_000], ['Layar Proyeksi Contoh', 2, 'unit', 750_000]],
            'total' => 12_500_000,
        ],
        'DOC-05' => [
            'name' => 'Kuitansi', 'issuer' => 'ARJ', 'counterparty' => 'SMAN 1 Kota Contoh', 'number' => 'SAMPLE/KWT/0001', 'date' => '2026-02-20',
            'lines' => [['Pembayaran Invoice SAMPLE/INV/0001', 1, 'pembayaran', 12_500_000]],
            'total' => 12_500_000,
        ],
        'DOC-06' => [
            'name' => 'Invoice', 'issuer' => 'ARJ', 'counterparty' => 'SMAN 1 Kota Contoh', 'number' => 'SAMPLE/INV/0001', 'date' => '2026-02-10',
            'lines' => [['Proyektor Contoh', 2, 'unit', 5_500_000], ['Layar Proyeksi Contoh', 2, 'unit', 750_000]],
            'total' => 12_500_000,
        ],
        'DOC-07' => [
            'name' => 'Lampiran Kuitansi', 'issuer' => 'ARJ', 'counterparty' => 'SMAN 1 Kota Contoh', 'number' => 'SAMPLE/KWT/0001-L', 'date' => '2026-02-20',
            'lines' => [['Proyektor Contoh', 2, 'unit', 5_500_000], ['Layar Proyeksi Contoh', 2, 'unit', 750_000]],
            'total' => 12_500_000,
        ],
        'DOC-08' => [
            'name' => 'BAST', 'issuer' => 'ARJ', 'counterparty' => 'SMAN 1 Kota Contoh', 'number' => 'SAMPLE/BAST/0001', 'date' => '2026-02-11',
            'lines' => [['Proyektor Contoh — diterima baik', 2, 'unit', 0], ['Layar Proyeksi Contoh — diterima baik', 2, 'unit', 0]],
            'total' => 0,
        ],
        'DOC-09' => [
            'name' => 'SPK', 'issuer' => 'BTN', 'counterparty' => 'SD 1 Contoh Makmur', 'number' => 'SAMPLE/SPK/0001', 'date' => '2026-03-02',
            'lines' => [['Pemasangan jaringan kelas Contoh', 1, 'paket', 8_000_000]],
            'total' => 8_000_000,
        ],
        'DOC-10' => [
            'name' => 'Berita Acara Pemeriksaan Barang', 'issuer' => 'ARJ', 'counterparty' => 'SMAN 1 Kota Contoh', 'number' => 'SAMPLE/BAPB/0001', 'date' => '2026-02-11',
            'lines' => [['Proyektor Contoh — sesuai spesifikasi', 2, 'unit', 0]],
            'total' => 0,
        ],
        'DOC-11' => [
            'name' => 'HPS', 'issuer' => 'CKP', 'counterparty' => 'Dinas Pendidikan Kota Contoh', 'number' => 'SAMPLE/HPS/0001', 'date' => '2026-03-05',
            'lines' => [['Meja Belajar Contoh', 30, 'unit', 650_000], ['Kursi Belajar Contoh', 30, 'unit', 350_000]],
            'total' => 30_000_000,
        ],
        'DOC-12' => [
            'name' => 'Nota Terima Barang', 'issuer' => 'ARJ', 'counterparty' => 'CV Contoh Pemasok Lestari', 'number' => 'SAMPLE/NTB/0001', 'date' => '2026-02-06',
            'lines' => [['Proyektor Contoh', 2, 'unit', 0]],
            'total' => 0,
        ],
        'DOC-13' => [
            'name' => 'Surat Permintaan Pembayaran', 'issuer' => 'ARJ', 'counterparty' => 'SMAN 1 Kota Contoh', 'number' => 'SAMPLE/SPP/0001', 'date' => '2026-02-12',
            'lines' => [['Tagihan Invoice SAMPLE/INV/0001', 1, 'tagihan', 12_500_000]],
            'total' => 12_500_000,
        ],
        'DOC-14' => [
            'name' => 'Surat Pesanan', 'issuer' => 'BTN', 'counterparty' => 'CV Contoh Pemasok Lestari', 'number' => 'SAMPLE/SP/0001', 'date' => '2026-03-03',
            'lines' => [['Kabel Jaringan Contoh', 3, 'roll', 1_200_000], ['Konektor Contoh', 100, 'pcs', 5_000]],
            'total' => 4_100_000,
        ],
    ],
];

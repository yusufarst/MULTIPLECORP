<?php

// FX-07 (TEST_STRATEGY §4): invented Indonesian institution names for search (PERFORMANCE PF-21; HO-16)
// — names that equal or begin with a term, names that contain it later, longer numbers that contain it
// ("SMAN 10" for "SMAN 1") and near misses — each marked "Contoh" so that none is a real institution.
return [
    'id' => 'FX-07',
    'title' => 'Indonesian institution names and fragments for search — "SMAN 1", "UGM", "SD 1" and their like',
    'source' => 'PERFORMANCE PF-21',
    'used_by' => 'TL-15; CJ-12',
    'documents' => ['docs/06-api-performance/PERFORMANCE.md'],
    'terms' => ['SMAN 1', 'UGM', 'SD 1'],
    'entries' => [
        'SMAN 1 Kota Contoh',
        'SMAN 1 Contoh Lestari',
        'SMAN 10 Kota Contoh',
        'SMAN 11 Kota Contoh',
        'SMAN 12 Contoh Harapan',
        'Perpustakaan SMAN 1 Contoh Jaya',
        'SMPN 1 Kota Contoh',
        'SMKN 1 Contoh Utama',
        'MAN 1 Kota Contoh',
        'SD 1 Contoh Makmur',
        'SD 1 Contoh Bersama',
        'SD 10 Contoh Abadi',
        'Komite SD 1 Contoh Lestari',
        'SD Negeri 1 Kota Contoh',
        'SDN 1 Contoh Sentosa',
        'UGM Contoh Gita Mandala',
        'Fakultas Teknik UGM Contoh',
        'Koperasi Karyawan UGM Contoh',
        'Universitas Gita Mandala Contoh',
        'UGN Contoh Nusantara',
        'Dinas Pendidikan Kota Contoh',
    ],
];

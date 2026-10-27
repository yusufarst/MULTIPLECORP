<?php

use Illuminate\Support\Facades\Route;

it('boots and answers a request: a guest goes to sign-in (TASK-016)', function () {
    $this->get('/')->assertRedirect('/masuk');

    expect(app()->isBooted())->toBeTrue();
});

it('registers only the routes of the Tasks built, each a ROUTE_CONTRACTS row', function () {
    $routes = array_map(fn ($route): string => implode('|', $route->methods()).' '.$route->uri(), Route::getRoutes()->getRoutes());

    // The sign-in routes ROUTE-001, -101, -108, -109, -003 and -106 (TASK-017) — Fortify registers none of its own
    // (SM-22) —, the second factor's ROUTE-002, -104, -105, -004 and -107 (TASK-018) — Fortify's two-factor routes
    // none —, Google sign-in's ROUTE-102 and -103 (TASK-019), ROUTE-281 `Reporting Search` and ROUTE-005's fallback to PG-005 (TASK-016).
    expect($routes)->toBe([
        'GET|HEAD masuk', 'POST masuk', 'POST keluar', 'POST konfirmasi-kata-sandi', 'GET|HEAD atur-kata-sandi', 'POST atur-kata-sandi',
        'GET|HEAD masuk/verifikasi', 'POST masuk/verifikasi', 'POST masuk/verifikasi/pemulihan', 'GET|HEAD verifikasi-dua-langkah', 'POST verifikasi-dua-langkah',
        'GET|HEAD masuk/google', 'GET|HEAD masuk/google/callback',
        'GET|HEAD pengaturan/penomoran', 'PUT pengaturan/penomoran/{type}', 'POST pengaturan/penomoran/{type}/mulai',
        'GET|HEAD pengaturan/perusahaan', 'POST pengaturan/perusahaan', 'PUT pengaturan/perusahaan/{id}', 'POST pengaturan/perusahaan/{id}/nonaktifkan',
        'POST pengaturan/perusahaan/{id}/rekening', 'POST pengaturan/perusahaan/{id}/aset-identitas', 'GET|HEAD pengaturan/pajak', 'PUT pengaturan/pajak',
        'GET|HEAD akun', 'PUT akun/kata-sandi', 'POST akun/verifikasi-dua-langkah', 'DELETE akun/verifikasi-dua-langkah', 'POST akun/kode-pemulihan',
        'POST akun/kode-pemulihan/baru', 'POST akun/google', 'DELETE akun/google', 'PUT akun/bahasa',
        'GET|HEAD pengaturan/pengguna', 'POST pengaturan/pengguna', 'PUT pengaturan/pengguna/{id}', 'POST pengaturan/pengguna/{id}/status',
        'PUT pengaturan/pengguna/{id}/akses', 'POST pengaturan/pengguna/{id}/tautan', 'POST pengaturan/pengguna/{id}/tautan/cabut',
        'POST pengaturan/pengguna/{id}/reset-verifikasi', 'POST pengaturan/pengguna/{id}/lepas-google', 'POST pengaturan/pengguna/{id}/sesi/{sesi}/akhiri',
        'GET|HEAD dokumen/bukti', 'POST berkas', 'POST bukti', 'POST bukti/{id}/versi', 'POST bukti/{id}/tautan',
        'GET|HEAD berkas/{id}/unduh', 'GET|HEAD berkas/{id}/gambar-kecil', 'GET|HEAD status/{kind}/{id}',
        'POST cari', 'GET|HEAD {fallbackPlaceholder}',
    ]);
});

it('keeps the framework default migrations out: every migration is the project\'s, named by its module', function () {
    $modules = 'adm|cat|cst|doc|fin|ful|iam|inv|ops|org|prj|pty|pur';

    foreach (glob(database_path('migrations/*.php')) ?: [] as $migration) {
        expect(basename($migration))->toMatch("/^\\d{4}_\\d{2}_\\d{2}_\\d{6}_({$modules})_[a-z0-9_]+\\.php\$/")
            ->not->toStartWith('0001_01_01_');
    }
});

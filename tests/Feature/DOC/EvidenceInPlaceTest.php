<?php

use App\Modules\DOC\Models\EvidenceDocument;
use App\Modules\ORG\Models\Company;
use App\Support\Command\Outcome;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Support\Authorization;
use Tests\Support\Evidence;
use Tests\Support\RefreshDatabase;

// TL-04, TL-07 (TASK-096 A; GAP-073; IP-16; NV-04; ROUTE-229): an evidence registration sent from a form returns to that
// form — every page hosting the in-place upload, a record named by its exact public id, the query re-serialized with the
// routes' real cursors —, a refusal stays in the form with nothing registered, and every other return address answers
// PG-038: another host or scheme, `//`, `\`, `:`, `#`, a control character, an array, a value past the bound.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Storage::fake('files');
    Authorization::catalogue();
    $this->company = Company::factory()->create();
    $this->uploader = Authorization::admin([$this->company->id], ['projects.view', 'evidence.view', 'evidence.upload']);
    $this->identity = ['issuer' => 'CV Kurir Kilat', 'reference' => 'SJ/096/01', 'type' => 'DELIVERY_PROOF'];
    $this->actingAs($this->uploader)->post('/bukti', Evidence::registration(Evidence::upload($this->uploader, $this->company->id), $this->company->public_id, $this->identity));
    $this->registered = EvidenceDocument::query()->sole();
    // A second upload of the identity just registered: each registration of it is refused (L-45), nothing registered.
    $this->again = Evidence::upload($this->uploader, $this->company->id);
});

afterEach(fn () => @unlink($this->hotFile));

/** The registration refused for its identity, sent from the form at `$back`. */
function refusedFrom(object $test, mixed $back): TestResponse
{
    return $test->actingAs($test->uploader)->post('/bukti', Evidence::registration($test->again, $test->company->public_id, [...$test->identity, 'kembali' => $back]));
}

it('returns a registration sent from a form to that form, the evidence named for it to select (IP-16; NV-04; criterion 1)', function () {
    $back = '/gudang/pengiriman/'.Str::uuid();
    $file = Evidence::upload($this->uploader, $this->company->id);

    $this->actingAs($this->uploader)->post('/bukti', Evidence::registration($file, $this->company->public_id, ['type' => 'DELIVERY_PROOF', 'kembali' => $back]))->assertRedirect($back);

    $answer = Evidence::answer();
    expect($answer['kind'])->toBe(Outcome::COMMITTED)
        ->and($answer['state']['registered'])->toBe(EvidenceDocument::query()->latest('id')->value('public_id'))
        ->and(EvidenceDocument::query()->count())->toBe(2);
});

it('returns to every page hosting the in-place upload with its query, a refusal kept in the form with nothing registered (IP-16; NV-04; criteria 1, 2)', function () {
    $id = (string) Str::uuid();
    $company = $this->company->public_id;
    // PF-14's encrypted cursor as the pages put it in their address: base64 escaped (`%2B`, `%2F`, `%3D`).
    $cursor = rawurlencode(Crypt::encryptString(json_encode(['k' => [987654321], 'b' => hash('sha256', 'daftar')], JSON_THROW_ON_ERROR)));
    $hosts = [
        "/pembelian/{$id}",
        "/pembelian/baru?perusahaan={$company}",
        "/gudang/barang-masuk/{$id}",
        "/gudang/barang-keluar/{$id}",
        "/gudang/stok-opname?setelah={$cursor}",
        "/gudang/penyesuaian?produk={$id}&aksi=KONDISI&temukan=L.2026.01",
        "/gudang/selisih-temuan?setelah={$cursor}",
        "/gudang/pengiriman/{$id}",
        "/proyek/{$id}/kirim-langsung?baris={$cursor}&konfirmasi={$cursor}",
        "/proyek/{$id}/serah-terima?setelah={$id}",
        "/proyek/{$id}/retur",
        "/keuangan/pembayaran?perusahaan={$company}&setelah={$id}",
        "/keuangan/pembayaran/baru?perusahaan={$company}&klien={$id}",
        "/keuangan/potongan?perusahaan={$company}",
        "/keuangan/kredit-pelanggan?cabang={$company}&setelah={$id}.{$company}",
        "/keuangan/kas-lain?perusahaan={$company}&tab=beban",
        "/keuangan/mutasi-bank?perusahaan={$company}",
    ];

    foreach ($hosts as $back) {
        refusedFrom($this, $back)->assertRedirect($back);
        expect(Evidence::answer())->toMatchArray(['kind' => Outcome::REJECTED, 'code' => 'L-45'])
            ->and(Evidence::answer()['state'])->not->toHaveKey('registered');
    }

    // The query re-serialized on the server: a space sent as `+` comes back escaped, nothing else changes.
    refusedFrom($this, '/gudang/penyesuaian?temukan=Rak+A+1&aksi=KONDISI')->assertRedirect('/gudang/penyesuaian?temukan=Rak%20A%201&aksi=KONDISI');
    // A field's own refusal stays at the form too, with its error (IP-14).
    $this->actingAs($this->uploader)->from($hosts[7])->post('/bukti', Evidence::registration($this->again, $company, ['issuer' => '', 'kembali' => $hosts[7]]))
        ->assertRedirect($hosts[7])->assertSessionHasErrors('issuer');

    expect(EvidenceDocument::query()->count())->toBe(1)
        ->and(EvidenceDocument::query()->sole()->is($this->registered))->toBeTrue();
});

it('answers PG-038 for every other return address, the length bound past the routes\' longest cursor (IP-16; ER-06; criterion 2)', function () {
    $id = (string) Str::uuid();
    $page = '/keuangan/kas-lain?tab=';
    // A scheme assembled at run time, never written whole in the repository.
    $scheme = implode('', ['java', 'script']).':';
    $refused = [
        'https://contoh.test/keuangan/kas-lain',
        '//contoh.test/keuangan/kas-lain',
        '/\\contoh.test/keuangan/kas-lain',
        '\\\\contoh.test\\keuangan',
        $scheme.'void(0)',
        '/keuangan/kas-lain#bagian',
        '/keuangan/kas-lain?tab=beban#bagian',
        '/keuangan/kas-lain?tab=a:b',
        "/keuangan/kas\n-lain",
        "/keuangan/kas-lain?tab=\tbeban",
        '/keuangan/kas-lain?tab=%0Abeban',
        '/keuangan/kas-lain/',
        '/keuangan/kas-lainx',
        '/keuangan/kas-lain/../../dokumen',
        "/gudang/pengiriman/{$id}x",
        '/gudang/pengiriman/'.strtoupper($id),
        '/gudang/pengiriman/1234567-81234-1234-1234-123456789012',
        "/proyek/{$id}/retur/lain",
        '/keuangan/kas-lain?tab=a&tab=b',
        '/keuangan/kas-lain?tab[]=a',
        '/keuangan/kas-lain?tab%5B%5D=a',
        $page.str_repeat('a', 2049 - strlen($page)),
        ['/keuangan/kas-lain'],
        '',
    ];

    foreach ($refused as $back) {
        refusedFrom($this, $back)->assertRedirectContains('/dokumen/bukti');
    }

    $longest = $page.str_repeat('a', 2048 - strlen($page));
    refusedFrom($this, $longest)->assertRedirect($longest);
    expect(EvidenceDocument::query()->count())->toBe(1);
});

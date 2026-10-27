<?php

use App\Modules\DOC\Data\EvidenceIdentity;

// TL-03 (TASK-030; L-45; DIR-089 (2); FU-10): the issuer's normalization defined once — letters and digits case-folded,
// every other run one space, compatibility forms folded —, the reference's without spaces or the separator, an empty
// reference for a document without one; `identity_key`'s parts in the order the type-free index reads; FU-10's keys
// in (namespace, key) order, the type-free identity whatever the company where a reference exists, the company's
// otherwise.

it('normalizes the issuer and the reference once, for the key and the index alike (L-45; DIR-089 (2))', function () {
    expect(EvidenceIdentity::issuer('  SMA  Negeri 1, Contoh!  '))->toBe('sma negeri 1 contoh')
        ->and(EvidenceIdentity::issuer('ＳＭＡ Negeri １ Contoh'))->toBe('sma negeri 1 contoh')
        ->and(EvidenceIdentity::reference(' SP / 0012 | A '))->toBe('sp/0012a')
        ->and(EvidenceIdentity::reference(null))->toBe('')
        ->and(EvidenceIdentity::key('SMA Negeri 1 Contoh', 'CLIENT_ORDER', 'SP/0012', '2026-10-01'))->toBe('sma negeri 1 contoh|CLIENT_ORDER|sp/0012|2026-10-01')
        ->and(explode('|', EvidenceIdentity::key('Bank, Contoh', 'BANK_STATEMENT', null, '2026-10-01')))->toBe(['bank contoh', 'BANK_STATEMENT', '', '2026-10-01']);
});

it('derives FU-10\'s keys in order, the type-free identity across companies only where a reference exists', function () {
    $hash = hash('sha256', 'isi');
    $withReference = fn (?int $company) => EvidenceIdentity::registrationKeys($hash, 'SMA Contoh', 'SP/1', '2026-10-01', $company);
    $without = fn (?int $company) => EvidenceIdentity::registrationKeys($hash, 'SMA Contoh', null, '2026-10-01', $company);

    expect(array_column($withReference(1), 0))->toBe([7101, 7102])
        ->and($withReference(1))->toBe($withReference(2))
        ->and($without(1))->not->toBe($without(2))
        ->and($without(null))->not->toBe($without(1))
        ->and(EvidenceIdentity::contentKey($hash))->toBe($withReference(1)[0])
        ->and($withReference(1)[1][1])->toBeGreaterThanOrEqual(-2147483648)->toBeLessThanOrEqual(2147483647);
});

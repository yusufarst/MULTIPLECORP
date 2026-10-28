<?php

use App\Modules\DOC\Data\DocumentFacts;
use App\Modules\DOC\Services\DocumentPaper;
use App\Modules\DOC\Services\MustacheTemplates;
use App\Modules\DOC\Services\PaperFormat;
use Tests\Support\Documents\SyntheticSubjects;

// TL-03 and TL-14 (TASK-029; DOCUMENT_PREVIEW_PRODUCER §3; DESIGN_SYSTEM §14.3; SM-10; criteria 7, 8, 10): one template
// per layout, DOC-01–DOC-14, its draft mode marked — "DRAF — belum terbit", "Nomor diberikan saat terbit", a text
// signature block — and its issued mode numbered; every value escaped, no template writing a raw value, an inline
// style or a script, and no value ever called; the paper Indonesian whatever the interface's language.

const ISSUER = ['name' => 'CV Contoh Usaha', 'npwp' => '01.234.567.8-901.000', 'address' => ['Jl. Contoh No. 1'], 'city' => 'Yogyakarta', 'phone' => '0274 123', 'email' => null, 'director' => 'Budi Contoh'];

function layouts(): array
{
    return array_map(fn (int $number): string => sprintf('DOC-%02d', $number), range(1, 14));
}

function paperOf(string $layout, bool $draft, ?DocumentFacts $facts = null): array
{
    return DocumentPaper::compose($layout, $layout === 'DOC-05' ? 'PRE_PAYMENT' : 'STANDARD', $facts ?? SyntheticSubjects::sample($layout), ISSUER,
        ['bank_name' => 'Bank Contoh', 'account_number' => '0123456789', 'account_holder' => 'CV Contoh Usaha', 'branch' => null], '2026-10-28', $draft ? null : 'CTH/2026/0001', $draft);
}

it('writes one template per layout, each reading the shared paper style and partials only (DOCUMENT_PREVIEW_PRODUCER §3 item 1)', function () {
    foreach (layouts() as $layout) {
        expect(is_file(MustacheTemplates::directory()."/{$layout}.mustache"))->toBeTrue($layout);
    }

    $sources = glob(MustacheTemplates::directory().'/{,partials/}*.mustache', GLOB_BRACE) ?: [];
    expect($sources)->not->toBeEmpty();

    foreach ($sources as $source) {
        $text = (string) file_get_contents($source);

        // SM-10: no raw value, no inline style the page's policy refuses, no script, no address of its own.
        expect($text)->not->toContain('{{{')->not->toContain('{{&')->not->toMatch('/\sstyle=/i')->not->toMatch('/<script/i')
            ->not->toMatch('/(?:src|href)="(?!\{\{)/i');
    }
});

it('marks every layout\'s draft and numbers its issue, the paper Indonesian in either language (DC-2–DC-4; criteria 8, 10)', function () {
    foreach (['id', 'en'] as $locale) {
        app()->setLocale($locale);

        foreach (layouts() as $layout) {
            $draft = MustacheTemplates::render($layout, paperOf($layout, true));
            $issued = MustacheTemplates::render($layout, paperOf($layout, false));

            expect($draft)->toContain('DRAF — belum terbit')->toContain('Nomor diberikan saat terbit')->toContain('Budi Contoh')
                ->toContain('28 Oktober 2026')->not->toContain('paper-stamp')->not->toContain('paper-sign"')
                ->and($issued)->not->toContain('DRAF — belum terbit')->not->toContain('Nomor diberikan saat terbit')->toContain('CTH/2026/0001')
                ->and(substr_count($draft, '<div'))->toBe(substr_count($draft, '</div>'));
        }
    }
});

it('escapes every value, so content reaches no file, address or script (SM-10; TL-14)', function () {
    $hostile = '<img src="http://169.254.169.254/x"><script>alert(1)</script>file:///etc/passwd';
    $facts = new DocumentFacts(recipient: ['name' => $hostile, 'lines' => [$hostile]], references: [['label' => $hostile, 'value' => $hostile]], subject: $hostile,
        lines: [['description' => $hostile, 'quantity' => '1', 'unit' => $hostile, 'unit_price' => '1.00', 'amount' => '1.00', 'note' => $hostile]],
        totals: [['label' => $hostile, 'amount' => '1.00', 'grand' => true]], amount: '1.00');

    foreach (layouts() as $layout) {
        $html = MustacheTemplates::render($layout, paperOf($layout, false, $facts));

        expect($html)->not->toContain('<script')->not->toContain('<img src="http')->toContain('&lt;script&gt;');
    }

    // A value is never called as a lambda (strict callables).
    $paper = paperOf('DOC-06', false);
    $paper['subject'] = 'phpinfo';
    expect(MustacheTemplates::render('DOC-06', $paper))->toContain('phpinfo');
});

it('prints the issued paper\'s bank destination on the layouts that ask for payment, and a Kuitansi\'s amount in words', function () {
    expect(MustacheTemplates::render('DOC-06', paperOf('DOC-06', false)))->toContain('0123456789')
        ->and(MustacheTemplates::render('DOC-02', paperOf('DOC-02', false)))->not->toContain('0123456789')
        ->and(MustacheTemplates::render('DOC-05', paperOf('DOC-05', false)))->toContain('Delapan belas juta tiga ratus lima belas ribu rupiah')->toContain('untuk proses pembayaran')
        ->and(PaperFormat::words('1200000.50'))->toBe('Satu juta dua ratus ribu rupiah lima puluh sen')
        ->and(PaperFormat::words('2001000'))->toBe('Dua juta seribu rupiah')
        ->and(PaperFormat::words('0'))->toBe('Nol rupiah')
        ->and(PaperFormat::rupiah('15000000.00'))->toBe('Rp15.000.000')
        ->and(PaperFormat::quantity('1.500'))->toBe('1,5');
});

it('writes the renderer\'s document with the bundled fonts by their relative address and the paper style inline (FL-08)', function () {
    $html = MustacheTemplates::document(MustacheTemplates::render('DOC-09', paperOf('DOC-09', false)));

    expect($html)->toStartWith('<!doctype html><html lang="id">')->toContain('<link rel="stylesheet" href="fonts.css">')->toContain('@page document')
        ->not->toContain('http://')->not->toContain('https://');
});

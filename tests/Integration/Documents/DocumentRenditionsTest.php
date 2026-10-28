<?php

use App\Modules\DOC\Contracts\DocumentRenderer;
use App\Modules\DOC\Contracts\RenditionTemplate;
use App\Modules\DOC\Jobs\RenderDocument;
use App\Modules\DOC\Jobs\RenditionSweeper;
use App\Modules\DOC\Models\Document;
use App\Modules\DOC\Models\DocumentRendition;
use App\Modules\DOC\Models\DocumentVersion;
use App\Modules\DOC\Services\DocumentChain;
use App\Modules\DOC\Services\DocumentPaper;
use App\Modules\DOC\Services\MustacheTemplates;
use App\Modules\ORG\Enums\SequenceType;
use App\Modules\ORG\Models\Company;
use Carbon\CarbonImmutable;
use Database\Factories\DOC\DocumentTypeFactory;
use Database\Factories\PRJ\ProjectFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Authorization;
use Tests\Support\DocumentRaces;
use Tests\Support\Documents\SyntheticSubjects;
use Tests\Support\Numbering;
use Tests\Support\RefreshDatabase;

// TL-05 and TL-14 (TASK-029; SF-RENDER; GAP-007; M-31, M-32; BR-DOC-03; FL-08; SM-10; criteria 4, 7, 12) on real
// PostgreSQL with the real renderer (DIR-099 C1): each of DOC-01–DOC-14 renders from its issued payload alone through
// its one template with JavaScript disabled; an issue whose dispatch never ran is found by the sweeper and rendered,
// the version, its number and the counter untouched; a hostile value prints as text; the composed draft and the issued
// paper differ only in the marking, the number line and the signature; and a composer's provisional totals equal
// PostgreSQL's numeric arithmetic.

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('files');
    app()->forgetInstance(RenditionTemplate::class);
    Authorization::catalogue();
    SyntheticSubjects::register();

    foreach (range(1, 14) as $number) {
        DocumentTypeFactory::ensure(sprintf('DOC-%02d', $number));
    }
});

/** An issued version of a layout with the payload the issue freezes: its paper and no identity asset. */
function issuedLayout(string $layout): DocumentVersion
{
    $paper = DocumentPaper::compose($layout, $layout === 'DOC-05' ? 'RECEIPT' : 'STANDARD', SyntheticSubjects::sample($layout),
        ['name' => 'CV Contoh Usaha', 'npwp' => '01.234.567.8-901.000', 'address' => ['Jl. Contoh No. 1'], 'city' => 'Yogyakarta', 'phone' => null, 'email' => null, 'director' => 'Budi Contoh'],
        ['bank_name' => 'Bank Contoh', 'account_number' => '0123456789', 'account_holder' => 'CV Contoh Usaha', 'branch' => null], '2026-10-28', "CTH/{$layout}/2026/0001", false);
    $payload = ['paper' => $paper, 'assets' => ['logo' => null, 'stamp' => null, 'signature' => null]];

    return DocumentVersion::factory()->issued()->for(Document::factory(), 'document')->create([
        'payload' => $payload, 'payload_sha256' => DocumentChain::checksum($payload),
    ]);
}

it('renders each of the fourteen layouts from its issued payload through its one template (FL-08; criterion 7)', function () {
    foreach (range(1, 14) as $number) {
        $layout = sprintf('DOC-%02d', $number);
        $version = issuedLayout($layout);
        $html = MustacheTemplates::render($layout, $version->payload['paper']);
        $pdf = app(DocumentRenderer::class)->render(MustacheTemplates::document($html), sys_get_temp_dir());

        expect($pdf)->toStartWith('%PDF-', $layout);
    }
});

it('finds an issue whose dispatch never ran and renders it once, the version and its number untouched (GAP-007; M-32; criterion 4)', function () {
    Queue::fake();
    $today = CarbonImmutable::now('Asia/Jakarta')->toDateString();
    $year = substr($today, 0, 4);
    Numbering::goLive("{$year}-01-01");
    $company = Company::factory()->create();
    Numbering::scheme($company, SequenceType::Doc09, "{$year}-01-01", 'YEAR', 'CTH/SPK/{tahun}/{nomor:4}');
    Numbering::started($company, SequenceType::Doc09, CarbonImmutable::parse(((int) $year - 1).'-12-31 03:00', 'UTC'));
    $actor = Authorization::admin([$company->id], ['projects.view', 'finance.view', 'documents.issue'])->id;
    $document = DocumentRaces::prepare($actor, $company->id, ProjectFactory::new()->createOne(['company_id' => $company->id])->id);
    expect(DocumentRaces::issue($actor, $document->id))->toBe('COMMITTED');
    $version = DocumentVersion::query()->where('document_id', $document->id)->sole();
    $frozen = (array) DB::table('document_versions')->where('id', $version->id)->first(['formatted_number', 'sequence_no', 'payload', 'payload_sha256', 'lock_version']);
    $attempt = DocumentRendition::query()->where('document_version_id', $version->id)->sole();

    // The worker never received it: two minutes on, the sweeper owes it again.
    $this->travel(3)->minutes();
    (new RenditionSweeper)->handle();
    Queue::assertPushed(RenderDocument::class, fn (RenderDocument $job): bool => $job->renditionId === $attempt->id);

    // The faked queue holds the job; the worker that takes it runs it.
    app()->call([new RenderDocument($attempt->id), 'handle']);

    expect($attempt->fresh()?->state)->toBe('READY')
        ->and((array) DB::table('document_versions')->where('id', $version->id)->first(['formatted_number', 'sequence_no', 'payload', 'payload_sha256', 'lock_version']))->toBe($frozen)
        ->and(Numbering::next($company, SequenceType::Doc09, $year))->toBe(2)
        // BR-XD-04: the issue and its rendition moved no stock, cash or obligation.
        ->and(DB::table('stock_movements')->count() + DB::table('payments')->count() + DB::table('invoices')->count())->toBe(0);
});

it('prints a hostile value as text in the rendered PDF\'s source, reaching no address (SM-10; TL-14)', function () {
    $version = issuedLayout('DOC-06');
    $payload = $version->payload;
    $payload['paper']['subject'] = '<img src="http://169.254.169.254/latest"><link rel="stylesheet" href="file:///etc/passwd">';
    $html = MustacheTemplates::document(MustacheTemplates::render('DOC-06', $payload['paper']));

    expect($html)->not->toContain('<img src="http')->not->toContain('href="file:')
        ->and(app(DocumentRenderer::class)->render($html, sys_get_temp_dir()))->toStartWith('%PDF-');
});

it('composes the draft and the issued paper alike but for the marking, the number line and the signature (DOCUMENT_PREVIEW_PRODUCER §4; criterion 7)', function () {
    foreach (range(1, 14) as $number) {
        $layout = sprintf('DOC-%02d', $number);
        $issuer = ['name' => 'CV Contoh Usaha', 'npwp' => null, 'address' => ['Jl. Contoh'], 'city' => 'Yogyakarta', 'phone' => null, 'email' => null, 'director' => 'Budi Contoh'];
        $paper = fn (bool $draft): array => DocumentPaper::compose($layout, 'RECEIPT', SyntheticSubjects::sample($layout), $issuer, null, '2026-10-28', $draft ? null : 'CTH/2026/0001', $draft);
        $strip = fn (string $html): string => (string) preg_replace(['/<p class="paper-marking">.*?<\/p>/s', '/<div class="paper-watermark"[^>]*>.*?<\/div>/s', '/<p class="paper-number">.*?<\/p>/s', '/ paper-draft/'], '', $html);

        expect($strip(MustacheTemplates::render($layout, $paper(true))))->toBe($strip(MustacheTemplates::render($layout, $paper(false))), $layout);
    }
});

it('holds the composer\'s provisional totals to PostgreSQL\'s numeric arithmetic, the server\'s (TL-03; UXI-14; criterion 9)', function () {
    $cases = json_decode((string) file_get_contents(base_path('tests/Frontend/provisional-totals.json')), true)['cases'];

    foreach ($cases as $case) {
        $amounts = implode(' + ', array_map(fn (array $line): string => 'round('.$line[0].'::numeric * '.$line[1].'::numeric, 2)', $case['lines']));
        $row = DB::selectOne("select s.subtotal::text as subtotal, round(s.subtotal * ?::numeric / 100, 2)::text as tax, (s.subtotal + round(s.subtotal * ?::numeric / 100, 2))::text as total from (select round({$amounts}, 2) as subtotal) s",
            [$case['tax_percent'], $case['tax_percent']]);

        expect([$row->subtotal, $row->tax, $row->total])->toBe([$case['subtotal'], $case['tax'], $case['total']]);
    }
});

<?php

use App\Modules\DOC\Models\Document;
use App\Modules\ORG\Enums\SequenceType;
use App\Modules\ORG\Models\Company;
use App\Support\Command\Outcome;
use Carbon\CarbonImmutable;
use Database\Factories\DOC\DocumentTypeFactory;
use Database\Factories\PRJ\ProjectFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\Documents\SyntheticSubjects;
use Tests\Support\Numbering;
use Tests\Support\RefreshDatabase;

// TL-08 (TASK-029; IG-01–IG-05; INT-221–INT-224; CI-05; architecture challenge 15c; criterion 3): each command's server
// side — its success, and one `command_id` replayed answering the recorded outcome with nothing written twice; the
// same identifier with other content a CONFLICT; "Terbitkan" pressed twice issues once.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Queue::fake();
    Authorization::catalogue();
    SyntheticSubjects::register();

    foreach (range(1, 14) as $number) {
        DocumentTypeFactory::ensure(sprintf('DOC-%02d', $number));
    }

    $this->today = CarbonImmutable::now('Asia/Jakarta')->toDateString();
    $year = substr($this->today, 0, 4);
    Numbering::goLive("{$year}-01-01");
    $company = Company::factory()->create();
    Numbering::scheme($company, SequenceType::Doc09, "{$year}-01-01", 'YEAR', 'CTH/SPK/{tahun}/{nomor:4}');
    Numbering::started($company, SequenceType::Doc09, CarbonImmutable::parse(((int) $year - 1).'-12-31 03:00', 'UTC'));
    $this->project = ProjectFactory::new()->createOne(['company_id' => $company->id]);
    $this->user = Authorization::admin([$company->id], ['projects.view', 'finance.view', 'documents.issue', 'documents.correct']);
});

afterEach(fn () => @unlink($this->hotFile));

function interactionAnswer(): array
{
    $flash = session('inertia.flash_data');

    return is_array($flash) && is_array($flash['answer'] ?? null) ? $flash['answer'] : [];
}

it('replays each command\'s command_id with its recorded outcome, writing nothing twice (INT-221–INT-224; CI-05; IG-05)', function () {
    $prepare = ['command_id' => (string) Str::uuid(), 'jenis' => 'DOC-09', 'subjek' => ['project_id' => $this->project->public_id]];
    $this->actingAs($this->user)->post('/dokumen', $prepare);
    $this->actingAs($this->user)->post('/dokumen', $prepare);
    $document = Document::query()->sole();

    $commands = [
        ['/dokumen', ['dokumen' => $document->public_id, 'lock_version' => 0, 'tanggal' => $this->today, 'tanggal_otomatis' => true, 'rekening' => '']],
        ["/dokumen/{$document->public_id}/terbitkan", ['lock_version' => 0, 'tanggal' => $this->today, 'tanggal_otomatis' => true]],
        ["/dokumen/{$document->public_id}/revisi", ['alasan' => 'Nama keliru']],
        ["/dokumen/{$document->public_id}/terbitkan", ['lock_version' => 0, 'tanggal' => $this->today, 'tanggal_otomatis' => true]],
        ["/dokumen/{$document->public_id}/batalkan", ['alasan' => 'Salah pihak']],
    ];

    foreach ($commands as [$url, $body]) {
        $commandId = (string) Str::uuid();
        $this->actingAs($this->user)->post($url, ['command_id' => $commandId, ...$body])->assertRedirect("/dokumen/{$document->public_id}");
        expect(interactionAnswer()['kind'])->toBe(Outcome::COMMITTED, $url);
        $log = DB::table('command_log')->count();
        $audit = DB::table('audit_events')->count();

        $this->actingAs($this->user)->post($url, ['command_id' => $commandId, ...$body])->assertRedirect("/dokumen/{$document->public_id}");
        expect(interactionAnswer()['kind'])->toBe(Outcome::COMMITTED, $url)
            ->and(DB::table('command_log')->count())->toBe($log)
            ->and(DB::table('audit_events')->count())->toBe($audit);
    }

    expect(DB::table('document_versions')->where('document_id', $document->id)->orderBy('version_no')->pluck('state')->all())->toBe(['SUPERSEDED', 'VOIDED'])
        ->and(DB::table('number_sequences')->value('next_value'))->toBe(3)
        ->and(DB::table('document_renditions')->count())->toBe(2);
});

it('answers the same command_id with other content CONFLICT (CI-04; IG-05)', function () {
    $commandId = (string) Str::uuid();
    $this->actingAs($this->user)->post('/dokumen', ['command_id' => $commandId, 'jenis' => 'DOC-09', 'subjek' => ['project_id' => $this->project->public_id]]);
    $document = Document::query()->sole();

    $this->actingAs($this->user)->post("/dokumen/{$document->public_id}/terbitkan", ['command_id' => $commandId, 'lock_version' => 0, 'tanggal' => $this->today, 'tanggal_otomatis' => true]);
    expect(interactionAnswer()['kind'])->toBe(Outcome::CONFLICT)
        ->and(DB::table('document_versions')->where('document_id', $document->id)->value('state'))->toBe('DRAFT');
});

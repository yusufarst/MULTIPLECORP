<?php

use App\Modules\DOC\Models\Document;
use App\Modules\ORG\Enums\SequenceType;
use App\Modules\ORG\Models\Company;
use Carbon\CarbonImmutable;
use Database\Factories\DOC\DocumentTypeFactory;
use Database\Factories\PRJ\ProjectFactory;
use Database\Factories\PUR\PurchaseFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\Support\Authorization;
use Tests\Support\Documents\SyntheticSubjects;
use Tests\Support\Numbering;
use Tests\Support\RefreshDatabase;

// TL-06 and TL-14 (TASK-029; PERMISSIONS_MATRIX §4, PJ-15; AZ-11; SM-25; criteria 9, 14): the pages by `projects.view`;
// the draft and the issue by `documents.issue` with the subject family's view — DOC-01 `quotation.manage` too, the
// cost family by `cost.view` —; revision and void by `documents.correct`; another company's document unknown; each
// control present with the capability and absent without; a refused command writes nothing.

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

    $this->company = Company::factory()->create();
    $this->project = ProjectFactory::new()->createOne(['company_id' => $this->company->id]);
});

afterEach(fn () => @unlink($this->hotFile));

it('opens the pages with projects.view only, and another company\'s document as unknown (ROUTE-035; ROUTE-036; AZ-11)', function () {
    $issuer = Authorization::admin([$this->company->id], ['projects.view', 'finance.view', 'documents.issue']);
    $this->actingAs($issuer)->post('/dokumen', ['command_id' => (string) Str::uuid(), 'jenis' => 'DOC-09', 'subjek' => ['project_id' => $this->project->public_id]]);
    $document = Document::query()->sole();

    $this->actingAs(Authorization::admin([$this->company->id], ['finance.view']))->get('/dokumen')->assertForbidden();
    $this->actingAs(Authorization::admin([$this->company->id], ['finance.view']))->get("/dokumen/{$document->public_id}")->assertForbidden();
    $outsider = Authorization::admin([Company::factory()->create()->id], ['projects.view', 'finance.view', 'documents.issue', 'documents.correct']);
    $this->actingAs($outsider)->get("/dokumen/{$document->public_id}")->assertNotFound();
    $this->actingAs($outsider)->post("/dokumen/{$document->public_id}/terbitkan", ['command_id' => (string) Str::uuid(), 'lock_version' => 0, 'tanggal' => date('Y-m-d')])->assertNotFound();
    $this->actingAs($outsider)->post('/dokumen', ['command_id' => (string) Str::uuid(), 'jenis' => 'DOC-09', 'subjek' => ['project_id' => $this->project->public_id]])->assertNotFound();
});

it('prepares and issues only with documents.issue and the family\'s view — DOC-01 with quotation.manage, a purchase\'s layout with cost.view (PJ-15)', function () {
    $subject = ['project_id' => $this->project->public_id];

    $this->actingAs(Authorization::admin([$this->company->id], ['projects.view', 'finance.view']))
        ->post('/dokumen', ['command_id' => (string) Str::uuid(), 'jenis' => 'DOC-09', 'subjek' => $subject])->assertForbidden();
    $this->actingAs(Authorization::admin([$this->company->id], ['projects.view', 'documents.issue']))
        ->post('/dokumen', ['command_id' => (string) Str::uuid(), 'jenis' => 'DOC-09', 'subjek' => $subject])->assertForbidden();
    $this->actingAs(Authorization::admin([$this->company->id], ['projects.view', 'finance.view', 'documents.issue']))
        ->post('/dokumen', ['command_id' => (string) Str::uuid(), 'jenis' => 'DOC-01', 'subjek' => $subject])->assertForbidden();
    expect(DB::table('documents')->count())->toBe(0);

    $this->actingAs(Authorization::admin([$this->company->id], ['projects.view', 'finance.view', 'documents.issue', 'quotation.manage']))
        ->post('/dokumen', ['command_id' => (string) Str::uuid(), 'jenis' => 'DOC-01', 'subjek' => $subject])->assertRedirect();
    expect(DB::table('documents')->where('document_type_code', 'DOC-01')->count())->toBe(1);

    $purchase = PurchaseFactory::new()->createOne(['company_id' => $this->company->id]);
    $this->actingAs(Authorization::admin([$this->company->id], ['projects.view', 'finance.view', 'documents.issue']))
        ->post('/dokumen', ['command_id' => (string) Str::uuid(), 'jenis' => 'DOC-14', 'subjek' => ['purchase_id' => $purchase->public_id]])->assertForbidden();
    $this->actingAs(Authorization::admin([$this->company->id], ['projects.view', 'cost.view', 'documents.issue']))
        ->post('/dokumen', ['command_id' => (string) Str::uuid(), 'jenis' => 'DOC-14', 'subjek' => ['purchase_id' => $purchase->public_id]])->assertRedirect();
    expect(DB::table('documents')->where('document_type_code', 'DOC-14')->count())->toBe(1);
});

it('revises and voids only with documents.correct, its controls present with it and absent without (ROUTE-223; ROUTE-224; SM-04)', function () {
    $year = substr(CarbonImmutable::now('Asia/Jakarta')->toDateString(), 0, 4);
    Numbering::goLive("{$year}-01-01");
    Numbering::scheme($this->company, SequenceType::Doc09, "{$year}-01-01", 'YEAR', 'CTH/SPK/{tahun}/{nomor:4}');
    Numbering::started($this->company, SequenceType::Doc09, CarbonImmutable::parse(((int) $year - 1).'-12-31 03:00', 'UTC'));
    $issuer = Authorization::admin([$this->company->id], ['projects.view', 'finance.view', 'documents.issue']);
    $this->actingAs($issuer)->post('/dokumen', ['command_id' => (string) Str::uuid(), 'jenis' => 'DOC-09', 'subjek' => ['project_id' => $this->project->public_id]]);
    $document = Document::query()->sole();
    $this->actingAs($issuer)->post("/dokumen/{$document->public_id}/terbitkan", ['command_id' => (string) Str::uuid(), 'draf' => DB::table('document_versions')->where('document_id', $document->id)->value('public_id'), 'lock_version' => 0, 'tanggal' => CarbonImmutable::now('Asia/Jakarta')->toDateString(), 'tanggal_otomatis' => true]);

    $this->actingAs($issuer)->get("/dokumen/{$document->public_id}")->assertInertia(fn (AssertableInertia $page) => $page->where('can.correct', false));
    $this->actingAs($issuer)->post("/dokumen/{$document->public_id}/batalkan", ['command_id' => (string) Str::uuid(), 'alasan' => 'Uji'])->assertForbidden();
    $this->actingAs($issuer)->post("/dokumen/{$document->public_id}/revisi", ['command_id' => (string) Str::uuid(), 'alasan' => 'Uji'])->assertForbidden();
    // The family's view is the prerequisite of the correction too.
    $this->actingAs(Authorization::admin([$this->company->id], ['projects.view', 'documents.correct']))
        ->post("/dokumen/{$document->public_id}/batalkan", ['command_id' => (string) Str::uuid(), 'alasan' => 'Uji'])->assertForbidden();
    expect(DB::table('document_versions')->where('document_id', $document->id)->value('state'))->toBe('ISSUED');

    $corrector = Authorization::admin([$this->company->id], ['projects.view', 'finance.view', 'documents.correct']);
    $this->actingAs($corrector)->get("/dokumen/{$document->public_id}")->assertInertia(fn (AssertableInertia $page) => $page->where('can.correct', true)->where('can.issue', false));
    $this->actingAs($corrector)->post("/dokumen/{$document->public_id}/batalkan", ['command_id' => (string) Str::uuid(), 'alasan' => 'Uji'])->assertRedirect();
    expect(DB::table('document_versions')->where('document_id', $document->id)->value('state'))->toBe('VOIDED');
});

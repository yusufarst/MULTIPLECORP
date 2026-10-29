<?php

use App\Modules\ORG\Enums\SequenceType;
use App\Modules\PRJ\Actions\AnswerQuotation;
use App\Modules\PRJ\Actions\IssueQuotation;
use App\Modules\PRJ\Actions\SaveQuotationDraft;
use App\Modules\PRJ\Models\Project;
use App\Modules\PRJ\Queries\QuotationWorkspace;
use App\Modules\PRJ\Services\QuotationRecords;
use App\Support\BusinessDate;
use App\Support\RowHandle;
use Carbon\CarbonImmutable;
use Database\Factories\DOC\DocumentTypeFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\Numbering;
use Tests\Support\Projects;
use Tests\Support\RecordingParticipant;
use Tests\Support\RefreshDatabase;

// TL-04 (TASK-035; ROUTE-169; CM-27; L-32; AX-13; criterion 11): "Koreksi Keputusan" on PG-014 supersedes the quotation
// approval in force by TASK-034's confirmation step — a superseding QUOTATION_APPROVAL confirmation of the same revision
// with its reason and corrected date, the pins and the current pointers following, the lifecycle participants called for
// the re-confirmation inside its transaction and no reservation reactivated —; a confirmation a later one depends on
// CONFLICT; the control present only with `decisions.supersede`.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    DocumentTypeFactory::ensure('DOC-01');
    $this->company = Projects::company();
    $year = substr(BusinessDate::today(), 0, 4);
    Numbering::scheme($this->company, SequenceType::Doc01, "{$year}-01-01", 'YEAR', 'UJI/PNW/{tahun}/{nomor:4}');
    Numbering::started($this->company, SequenceType::Doc01, CarbonImmutable::parse(((int) $year - 1).'-12-31 03:00:00', 'UTC'));
    $this->admin = Authorization::admin([$this->company->id], ['projects.view', 'projects.manage', 'finance.view', 'quotation.manage', 'projects.confirm', 'documents.issue', 'decisions.supersede']);
    expect(Projects::create($this->admin->id, $this->company, Projects::client(), [Projects::product()]))->toStartWith('COMMITTED');
    $this->project = Project::query()->sole();
});

afterEach(fn () => @unlink($this->hotFile));

/** A revision drafted, priced, issued and approved: the project's QUOTATION_APPROVAL confirmation. */
function supersessionApprovedRevision(object $test): array
{
    app(SaveQuotationDraft::class)->run((string) Str::uuid(), $test->admin->id, $test->project, null, null, null, [], []);
    $revision = QuotationRecords::latest($test->project->id);
    $line = DB::table('quotation_revision_lines')->where('quotation_revision_id', $revision['id'])->sole();
    $valid = CarbonImmutable::parse(BusinessDate::today())->addDays(14)->toDateString();
    app(SaveQuotationDraft::class)->run((string) Str::uuid(), $test->admin->id, $test->project, $revision['public_id'], $revision['lock_version'], $valid,
        [(int) $line->id => ['quantity' => '12', 'quantity_base' => '12.000', 'price' => '20000', 'description' => 'Buku paket']], ['v' => 1]);
    $revision = QuotationRecords::latest($test->project->id);
    app(IssueQuotation::class)->run((string) Str::uuid(), $test->admin->id, $test->project, $revision['public_id'], $revision['lock_version'], BusinessDate::today(), true);
    expect(app(AnswerQuotation::class)->run((string) Str::uuid(), $test->admin->id, $test->project, $revision['public_id'], true, 'SPK diterima', BusinessDate::today(), true)->kind)->toBe('COMMITTED');

    return QuotationRecords::latest($test->project->id);
}

function supersessionAnswer(): array
{
    $flash = session('inertia.flash_data');

    return is_array($flash) && is_array($flash['answer'] ?? null) ? $flash['answer'] : [];
}

it('supersedes the approval in force through the confirmation step, the pins following and the participants called (CM-27; L-32; AX-13; criterion 11)', function () {
    $revision = supersessionApprovedRevision($this);
    $approval = DB::table('commercial_confirmations')->where('project_id', $this->project->id)->sole();
    $pinsBefore = DB::table('confirmation_line_pins')->where('commercial_confirmation_id', $approval->id)->count();
    $handle = app(QuotationWorkspace::class)->screen($this->admin->id, $this->project)['can']['supersede'];
    expect($handle)->toBe(RowHandle::of('commercial_confirmations', (int) $approval->id));

    RecordingParticipant::register();
    $yesterday = CarbonImmutable::parse(BusinessDate::today())->subDay()->toDateString();
    $this->actingAs($this->admin)->post("/keputusan/{$handle}/ganti", [
        'command_id' => (string) Str::uuid(), 'proyek' => $this->project->public_id, 'alasan' => 'Tanggal persetujuan salah catat', 'tanggal' => $yesterday, 'tanggal_otomatis' => false,
    ])->assertRedirect();

    $superseding = DB::table('commercial_confirmations')->where('supersedes_confirmation_id', $approval->id)->sole();
    expect(supersessionAnswer())->toMatchArray(['kind' => 'COMMITTED'])
        ->and($superseding)->toMatchObject(['kind' => 'QUOTATION_APPROVAL', 'quotation_revision_id' => $revision['id'], 'reason' => 'Tanggal persetujuan salah catat', 'business_date' => $yesterday])
        // Both decisions kept; the pins follow the superseding one (CM-27; L-32).
        ->and(DB::table('confirmation_line_pins')->where('commercial_confirmation_id', $approval->id)->count())->toBe($pinsBefore)
        ->and(DB::table('project_lines')->where('project_id', $this->project->id)->pluck('current_pin_id')->all())
        ->toBe(DB::table('confirmation_line_pins')->where('commercial_confirmation_id', $superseding->id)->pluck('id')->all())
        ->and(DB::table('audit_events')->where('action', 'PRJ.ConfirmationSuperseded')->count())->toBe(1)
        // The reservations move only through the participants' re-confirmation, inside the command's transaction.
        ->and(collect(RecordingParticipant::$calls)->pluck('hook')->all())->toBe(['reconfirmed', 'confirmed'])
        ->and(collect(RecordingParticipant::$calls)->every(fn (array $call): bool => $call['transaction'] > 0))->toBeTrue()
        ->and(QuotationRecords::latest($this->project->id)['state'])->toBe('APPROVED');

    // The superseded approval is no longer the decision in force (CM-27): a later one depends on it.
    $this->actingAs($this->admin)->post("/keputusan/{$handle}/ganti", [
        'command_id' => (string) Str::uuid(), 'proyek' => $this->project->public_id, 'alasan' => 'Lagi', 'tanggal' => BusinessDate::today(), 'tanggal_otomatis' => true,
    ]);
    expect(supersessionAnswer())->toMatchArray(['kind' => 'CONFLICT', 'code' => 'ST-02']);
});

it('offers "Koreksi Keputusan" on PG-014 only with decisions.supersede and asks the approval\'s date (OD-15; AZ-01; IP-14)', function () {
    supersessionApprovedRevision($this);
    $reader = Authorization::admin([$this->company->id], ['projects.view', 'projects.confirm']);
    $handle = app(QuotationWorkspace::class)->screen($this->admin->id, $this->project)['can']['supersede'];

    expect(app(QuotationWorkspace::class)->screen($reader->id, $this->project)['can']['supersede'])->toBeNull();
    $this->actingAs($this->admin)->post("/keputusan/{$handle}/ganti", ['command_id' => (string) Str::uuid(), 'proyek' => $this->project->public_id, 'alasan' => 'Tanggal salah'])
        ->assertSessionHasErrors('tanggal');
    $this->actingAs($reader)->post("/keputusan/{$handle}/ganti", ['command_id' => (string) Str::uuid(), 'proyek' => $this->project->public_id, 'alasan' => 'Tanggal salah', 'tanggal' => BusinessDate::today()])
        ->assertForbidden();

    expect(DB::table('commercial_confirmations')->count())->toBe(1);
});

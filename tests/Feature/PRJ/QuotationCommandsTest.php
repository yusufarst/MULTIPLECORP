<?php

use App\Modules\ORG\Enums\SequenceType;
use App\Modules\PRJ\Actions\AnswerQuotation;
use App\Modules\PRJ\Actions\IssueQuotation;
use App\Modules\PRJ\Actions\ReviseQuotation;
use App\Modules\PRJ\Actions\SaveQuotationDraft;
use App\Modules\PRJ\Models\Project;
use App\Modules\PRJ\Services\QuotationRecords;
use App\Support\BusinessDate;
use App\Support\Command\Outcome;
use Carbon\CarbonImmutable;
use Database\Factories\DOC\DocumentTypeFactory;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\Numbering;
use Tests\Support\Projects;
use Tests\Support\RefreshDatabase;

// TL-04 (TASK-033; ROUTE-154–ROUTE-157; WF-QUO-01; AX-15; AX-13; criteria 1–6): the quotation's commands in their own
// transactions — the draft from the project's lines, its lines saved at the revision's version, the issue drawing DOC-01's
// number and making the revision *Terkirim* with its lines frozen, a second issue CONFLICT with the number, "Buat Revisi"
// keeping the chain, the approval of the latest sent revision as the commercial confirmation, a second one CONFLICT, an
// expired one refused; nothing moving stock or money; the issued paper keeping the client it was issued to.

uses(RefreshDatabase::class);

beforeEach(function () {
    Authorization::catalogue();
    DocumentTypeFactory::ensure('DOC-01');
    $this->company = Projects::company();
    $year = substr(BusinessDate::today(), 0, 4);
    Numbering::scheme($this->company, SequenceType::Doc01, "{$year}-01-01", 'YEAR', 'UJI/PNW/{tahun}/{nomor:4}');
    Numbering::started($this->company, SequenceType::Doc01, CarbonImmutable::parse(((int) $year - 1).'-12-31 03:00:00', 'UTC'));
    $this->client = Projects::client();
    $this->goods = Projects::product();
    $this->admin = Authorization::admin([$this->company->id], ['projects.view', 'projects.manage', 'finance.view', 'quotation.manage', 'projects.confirm', 'documents.issue']);
    expect(Projects::create($this->admin->id, $this->company, $this->client, [$this->goods]))->toStartWith('COMMITTED');
    $this->project = Project::query()->sole();
});

function quotationWord(Outcome $outcome): string
{
    return trim($outcome->kind.' '.($outcome->kind === 'COMMITTED' ? '' : (string) $outcome->code));
}

function draftQuotation(object $test): array
{
    expect(quotationWord(app(SaveQuotationDraft::class)->run((string) Str::uuid(), $test->admin->id, $test->project, null, null, null, [], [])))->toBe('COMMITTED');
    $revision = QuotationRecords::latest($test->project->id);
    $line = DB::table('quotation_revision_lines')->where('quotation_revision_id', $revision['id'])->sole();
    $valid = CarbonImmutable::parse(BusinessDate::today())->addDays(14)->toDateString();
    expect(quotationWord(app(SaveQuotationDraft::class)->run((string) Str::uuid(), $test->admin->id, $test->project, $revision['public_id'], $revision['lock_version'], $valid,
        [(int) $line->id => ['quantity' => '12', 'quantity_base' => '12.000', 'price' => '20000', 'description' => 'Buku paket']], ['v' => 1])))->toBe('COMMITTED');

    return QuotationRecords::latest($test->project->id);
}

it('drafts from the project\'s lines, saves at the revision\'s version and issues DOC-01, the revision Terkirim with its lines frozen (criteria 1, 7)', function () {
    $revision = draftQuotation($this);
    $line = DB::table('quotation_revision_lines')->where('quotation_revision_id', $revision['id'])->sole();
    expect($line)->toMatchObject(['entered_qty' => '12.000', 'unit_price' => '20000.00', 'sales_value' => '240000.00', 'description' => 'Buku paket']);

    $stale = app(SaveQuotationDraft::class)->run((string) Str::uuid(), $this->admin->id, $this->project, $revision['public_id'], $revision['lock_version'] - 1, null, [], ['v' => 2]);
    expect(quotationWord($stale))->toBe('CONFLICT ST-01');

    $issue = fn (): string => quotationWord(app(IssueQuotation::class)->run((string) Str::uuid(), $this->admin->id, $this->project, $revision['public_id'], $revision['lock_version'], BusinessDate::today(), true));
    expect($issue())->toBe('COMMITTED');
    $version = DB::table('document_versions')->where('quotation_revision_id', $revision['id'])->sole();

    expect(QuotationRecords::latest($this->project->id)['state'])->toBe('SENT')
        ->and($version->state)->toBe('ISSUED')
        ->and($version->formatted_number)->toStartWith('UJI/PNW/')
        ->and(json_decode((string) $version->payload, true)['paper']['recipient']['name'])->toBe($this->client['organization']->name)
        ->and($issue())->toBe('CONFLICT BD-06')
        ->and(fn () => DB::table('quotation_revision_lines')->where('id', $line->id)->update(['unit_price' => '1']))->toThrow(QueryException::class, 'frozen');
});

it('approves the latest sent revision as the commercial confirmation once, moving no stock or money, and refuses an expired one (criteria 2, 3, 5)', function () {
    $revision = draftQuotation($this);
    app(IssueQuotation::class)->run((string) Str::uuid(), $this->admin->id, $this->project, $revision['public_id'], $revision['lock_version'], BusinessDate::today(), true);
    $approve = fn (): string => quotationWord(app(AnswerQuotation::class)->run((string) Str::uuid(), $this->admin->id, $this->project, $revision['public_id'], true, 'SPK diterima', BusinessDate::today(), true));
    $before = [DB::table('stock_movements')->count(), DB::table('reservations')->count()];

    expect($approve())->toBe('COMMITTED')
        ->and(QuotationRecords::latest($this->project->id)['state'])->toBe('APPROVED')
        ->and(DB::table('commercial_confirmations')->where('quotation_revision_id', $revision['id'])->sole()->kind)->toBe('QUOTATION_APPROVAL')
        ->and($approve())->toBe('CONFLICT BD-05')
        ->and([DB::table('stock_movements')->count(), DB::table('reservations')->count()])->toBe($before);

    // "Buat Revisi" from the approved revision; its issue and then expiry refuse the approval (L-25).
    expect(quotationWord(app(ReviseQuotation::class)->run((string) Str::uuid(), $this->admin->id, $this->project, $revision['public_id'])))->toBe('COMMITTED');
    $next = QuotationRecords::latest($this->project->id);
    expect($next['revision_no'])->toBe(2)->and($next['supersedes'])->toBe($revision['id']);
    app(IssueQuotation::class)->run((string) Str::uuid(), $this->admin->id, $this->project, $next['public_id'], $next['lock_version'], BusinessDate::today(), true);
    DB::table('quotation_revisions')->where('id', $next['id'])->update(['valid_until' => CarbonImmutable::parse(BusinessDate::today())->subDay()->toDateString()]);

    expect(quotationWord(app(AnswerQuotation::class)->run((string) Str::uuid(), $this->admin->id, $this->project, $next['public_id'], true, null, BusinessDate::today(), true)))->toBe('REJECTED L-25')
        ->and(QuotationRecords::shown('SENT', CarbonImmutable::parse(BusinessDate::today())->subDay()->toDateString()))->toBe('EXPIRED')
        ->and(DB::table('document_versions')->where('document_id', DB::table('document_versions')->where('quotation_revision_id', $revision['id'])->value('document_id'))->orderBy('version_no')->pluck('state')->all())
        ->toBe(['SUPERSEDED', 'ISSUED']);
});

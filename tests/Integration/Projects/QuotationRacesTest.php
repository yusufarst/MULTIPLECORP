<?php

use App\Modules\ORG\Enums\SequenceType;
use App\Modules\PRJ\Actions\SaveQuotationDraft;
use App\Modules\PRJ\Models\Project;
use App\Modules\PRJ\Services\QuotationRecords;
use App\Support\BusinessDate;
use Carbon\CarbonImmutable;
use Database\Factories\DOC\DocumentTypeFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\Concurrency\Sessions;
use Tests\Support\DatabaseTruncation;
use Tests\Support\Numbering;
use Tests\Support\Projects;
use Tests\Support\QuotationRaces;

// TL-05 (TASK-033; AX-15; AX-13; NX-03; criteria 1, 3, 7) on real PostgreSQL sessions: two issues of one draft at once
// draw one DOC-01 number, the other CONFLICT with it (M-19; BD-06); two approvals of one sent revision write one
// confirmation, the other CONFLICT (BD-05); two saves of one draft at the version both read — one wins, the other
// CONFLICT (M-38; ST-01).

uses(DatabaseTruncation::class);

afterEach(function () {
    $this->truncateTablesForAllConnections();
});

beforeEach(function () {
    Authorization::catalogue();
    DocumentTypeFactory::ensure('DOC-01');
    $this->year = substr(BusinessDate::today(), 0, 4);
    $this->goLive = "{$this->year}-01-01";
    $this->company = Projects::company();
    Numbering::scheme($this->company, SequenceType::Doc01, $this->goLive, 'YEAR', 'UJI/PNW/{tahun}/{nomor:4}');
    Numbering::started($this->company, SequenceType::Doc01, CarbonImmutable::parse(((int) $this->year - 1).'-12-31 03:00:00', 'UTC'));
    $capabilities = ['projects.view', 'projects.manage', 'finance.view', 'quotation.manage', 'projects.confirm'];
    $this->actors = [Authorization::admin([$this->company->id], $capabilities)->id, Authorization::admin([$this->company->id], $capabilities)->id];
    expect(Projects::create($this->actors[0], $this->company, Projects::client(), [Projects::product()], $this->goLive))->toStartWith('COMMITTED');
    $this->project = Project::query()->sole();
    Numbering::goLive($this->goLive);
    $save = app(SaveQuotationDraft::class);
    expect($save->run((string) Str::uuid(), $this->actors[0], $this->project, null, null, null, [], [])->kind)->toBe('COMMITTED');
    $this->revision = QuotationRecords::latest($this->project->id);
    $this->line = (int) DB::table('quotation_revision_lines')->where('quotation_revision_id', $this->revision['id'])->value('id');
    expect($save->run((string) Str::uuid(), $this->actors[0], $this->project, $this->revision['public_id'], $this->revision['lock_version'], CarbonImmutable::parse(BusinessDate::today())->addDays(14)->toDateString(),
        [$this->line => ['quantity' => '12', 'quantity_base' => '12', 'price' => '20000', 'description' => 'Buku paket']], ['v' => 1])->kind)->toBe('COMMITTED');
    $this->revision = QuotationRecords::latest($this->project->id);
});

it('draws one DOC-01 number for two issues of one draft at once, the other CONFLICT with it (M-19; BD-06; criterion 1)', function () {
    [$first, $second, $id, $revision, $version, $goLive] = [$this->actors[0], $this->actors[1], $this->project->id, $this->revision['public_id'], $this->revision['lock_version'], $this->goLive];

    $results = Sessions::run([
        'first' => static function () use ($first, $id, $revision, $version, $goLive): string {
            Numbering::goLive($goLive);

            return QuotationRaces::issue($first, $id, $revision, $version);
        },
        'second' => static function () use ($second, $id, $revision, $version, $goLive): string {
            Numbering::goLive($goLive);

            return QuotationRaces::issue($second, $id, $revision, $version);
        },
    ]);

    expect(array_values($results))->toEqualCanonicalizing(['COMMITTED', 'CONFLICT BD-06'])
        ->and(DB::table('document_versions')->where('quotation_revision_id', $this->revision['id'])->pluck('formatted_number')->all())->toBe(["UJI/PNW/{$this->year}/0001"])
        ->and(Numbering::next($this->company, SequenceType::Doc01, $this->year))->toBe(2);
});

it('writes one confirmation for two approvals of one sent revision at once, the other CONFLICT (BD-05; criterion 3)', function () {
    expect(QuotationRaces::issue($this->actors[0], $this->project->id, $this->revision['public_id'], $this->revision['lock_version']))->toBe('COMMITTED');
    [$first, $second, $id, $revision, $goLive] = [$this->actors[0], $this->actors[1], $this->project->id, $this->revision['public_id'], $this->goLive];

    $results = Sessions::run([
        'first' => static function () use ($first, $id, $revision, $goLive): string {
            Numbering::goLive($goLive);

            return QuotationRaces::approve($first, $id, $revision);
        },
        'second' => static function () use ($second, $id, $revision, $goLive): string {
            Numbering::goLive($goLive);

            return QuotationRaces::approve($second, $id, $revision);
        },
    ]);

    expect(array_values($results))->toEqualCanonicalizing(['COMMITTED', 'CONFLICT BD-05'])
        ->and(DB::table('commercial_confirmations')->where('quotation_revision_id', $this->revision['id'])->count())->toBe(1);
});

it('answers the later of two saves of one draft at the version both read CONFLICT (M-38; ST-01; criterion 7)', function () {
    [$first, $second, $id, $revision, $version, $line] = [$this->actors[0], $this->actors[1], $this->project->id, $this->revision['public_id'], $this->revision['lock_version'], $this->line];

    $results = Sessions::run([
        'first' => static fn (): string => QuotationRaces::save($first, $id, $revision, $version, $line, '10'),
        'second' => static fn (): string => QuotationRaces::save($second, $id, $revision, $version, $line, '11'),
    ]);

    expect(array_values($results))->toEqualCanonicalizing(['COMMITTED', 'CONFLICT ST-01'])
        ->and(DB::table('audit_events')->where('action', 'PRJ.QuotationDraftSaved')->count())->toBe(2);
});

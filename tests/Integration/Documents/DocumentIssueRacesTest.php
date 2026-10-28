<?php

use App\Modules\DOC\Actions\ReviseDocument;
use App\Modules\DOC\Contracts\DocumentChangeParticipant;
use App\Modules\DOC\Data\DocumentChange;
use App\Modules\DOC\Models\DocumentVersion;
use App\Modules\ORG\Enums\SequenceType;
use App\Modules\ORG\Models\Company;
use App\Support\Command\CommandContext;
use App\Support\Command\LockSet;
use App\Support\Command\Refused;
use Carbon\CarbonImmutable;
use Database\Factories\DOC\DocumentTypeFactory;
use Database\Factories\PRJ\ProjectFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\Concurrency\Barrier;
use Tests\Support\Concurrency\Sessions;
use Tests\Support\DatabaseTruncation;
use Tests\Support\DocumentRaces;
use Tests\Support\Documents\SyntheticSubjects;
use Tests\Support\GuardReconciliation;
use Tests\Support\Numbering;

// TL-05 (TASK-029; AX-15; BD-06; M-15–M-17, M-19; L-41; criteria 2, 5, 6, 15) on real PostgreSQL sessions: two issues of
// one draft give one issued version and one CONFLICT naming its number, one number consumed — the version locked
// before the counter —; N concurrent issues of different drafts draw N distinct consecutive numbers; a revision's issue
// refused by a participant inside its transaction rolls back with its number, its predecessor still current.

uses(DatabaseTruncation::class);

afterEach(function () {
    expect(GuardReconciliation::verificationDifferences())->toBe([]);
    $this->truncateTablesForAllConnections();
});

beforeEach(function () {
    Queue::fake();
    Authorization::catalogue();
    SyntheticSubjects::register();
    $this->today = CarbonImmutable::now('Asia/Jakarta')->toDateString();
    $this->year = substr($this->today, 0, 4);
    Numbering::goLive("{$this->year}-01-01");
    // The truncation between tests removes the seeded catalogue; the projects' own counter stands above their numbers.
    foreach (range(1, 14) as $number) {
        DocumentTypeFactory::ensure(sprintf('DOC-%02d', $number));
    }
    $this->company = Company::factory()->create();
    Numbering::seed($this->company, SequenceType::Project, '2026', 100_000);
    Numbering::scheme($this->company, SequenceType::Doc09, "{$this->year}-01-01", 'YEAR', 'CTH/SPK/{tahun}/{nomor:4}');
    Numbering::started($this->company, SequenceType::Doc09, CarbonImmutable::parse(((int) $this->year - 1).'-12-31 03:00', 'UTC'));
    // One issuer per session: each command holds its own actor's row at LK-03 (RV-01).
    $this->actors = array_map(fn (): int => Authorization::admin([$this->company->id], ['projects.view', 'finance.view', 'documents.issue', 'documents.correct'])->id, range(1, 3));
});

it('issues one draft once under two concurrent issues: one CONFLICT with the issued number, one number consumed (BD-06; M-19; criterion 2)', function () {
    $project = ProjectFactory::new()->createOne(['company_id' => $this->company->id]);
    $document = DocumentRaces::prepare($this->actors[0], $this->company->id, $project->id)->id;
    [$first, $second, $goLive] = [$this->actors[0], $this->actors[1], "{$this->year}-01-01"];

    $results = Sessions::run([
        'first' => static function () use ($first, $document, $goLive): string {
            Queue::fake();
            Numbering::goLive($goLive);

            return DocumentRaces::holdThenIssue($first, $document);
        },
        'second' => static function () use ($second, $document, $goLive): string {
            Queue::fake();
            Numbering::goLive($goLive);
            Barrier::await('the first issue is written');

            return DocumentRaces::issue($second, $document);
        },
    ]);

    expect($results['first'])->toBe('COMMITTED')
        ->and($results['second'])->toStartWith('CONFLICT BD-06')->toContain("CTH\\/SPK\\/{$this->year}\\/0001")
        ->and(DocumentVersion::query()->where('document_id', $document)->pluck('state')->all())->toBe(['ISSUED'])
        ->and(Numbering::next($this->company, SequenceType::Doc09, $this->year))->toBe(2)
        ->and(DB::table('document_renditions')->count())->toBe(1);
});

it('draws N distinct consecutive numbers for N concurrent issues of different drafts (M-15; M-16; criterion 2)', function () {
    $documents = array_map(fn (): int => DocumentRaces::prepare($this->actors[0], $this->company->id, ProjectFactory::new()->createOne(['company_id' => $this->company->id])->id)->id, range(1, 3));
    $goLive = "{$this->year}-01-01";
    $tasks = [];

    foreach ($documents as $index => $document) {
        $actor = $this->actors[$index];
        $tasks["issue{$index}"] = static function () use ($actor, $document, $goLive): string {
            Queue::fake();
            Numbering::goLive($goLive);
            Barrier::await('every issue is ready');

            return DocumentRaces::issue($actor, $document);
        };
    }

    expect(array_values(Sessions::run($tasks)))->toBe(['COMMITTED', 'COMMITTED', 'COMMITTED']);
    $numbers = DocumentVersion::query()->whereIn('document_id', $documents)->orderBy('sequence_no')->pluck('sequence_no')->all();
    expect($numbers)->toBe([1, 2, 3])
        ->and(DocumentVersion::query()->whereIn('document_id', $documents)->distinct()->count('formatted_number'))->toBe(3)
        ->and(Numbering::next($this->company, SequenceType::Doc09, $this->year))->toBe(4);
});

it('rolls a revision\'s issue back with its number when a participant refuses it inside the transaction (M-17; L-41; criteria 6, 15)', function () {
    $project = ProjectFactory::new()->createOne(['company_id' => $this->company->id]);
    $document = DocumentRaces::prepare($this->actors[0], $this->company->id, $project->id);
    expect(DocumentRaces::issue($this->actors[0], $document->id))->toBe('COMMITTED');
    $first = DocumentVersion::query()->where('document_id', $document->id)->sole();
    app(ReviseDocument::class)->run((string) Str::uuid(), $this->actors[0], $document, 'Koreksi pihak');
    app()->instance('test.refusing-participant', new class implements DocumentChangeParticipant
    {
        public function locks(DocumentChange $change): LockSet
        {
            return new LockSet;
        }

        public function documentChanged(CommandContext $context, DocumentChange $change): void
        {
            throw Refused::rejected('BR-ADM-03', ['reason' => 'requirement_still_needed']);
        }
    });
    app()->tag(['test.refusing-participant'], DocumentChangeParticipant::TAG);

    expect(DocumentRaces::issue($this->actors[0], $document->id))->toStartWith('REJECTED BR-ADM-03')
        ->and($first->fresh()?->state)->toBe('ISSUED')
        ->and(DocumentVersion::query()->where('document_id', $document->id)->where('state', 'DRAFT')->exists())->toBeTrue()
        ->and(Numbering::next($this->company, SequenceType::Doc09, $this->year))->toBe(2)
        ->and(DB::table('audit_events')->where('action', 'DOC.DocumentIssued')->count())->toBe(1);
});

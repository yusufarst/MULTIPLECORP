<?php

use App\Modules\DOC\Data\DocumentChange;
use App\Modules\PRJ\Actions\ForceCompleteProject;
use App\Modules\PRJ\Queries\ProjectCompletion;
use App\Modules\PRJ\Services\ProjectDocumentChanges;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use Database\Factories\DOC\DocumentTypeFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\Concurrency\Barrier;
use Tests\Support\Concurrency\Sessions;
use Tests\Support\DatabaseTruncation;
use Tests\Support\InvoiceWorld;

// TL-05 (TASK-055; M-27; ST-04; SF-REVAL; L-41; architecture challenge 7b; criteria 5, 8, 9) on real PostgreSQL sessions: a Force Complete whose snapshot a dispute hold changes while it
// waits on the project's LK-01 answers CONFLICT and leaves the project ACTIVE; PRJ's part of a document's change returns a completed project to ACTIVE inside the change's
// transaction with one REVALIDATE transition linked to it, and leaves a CANCELLED one CANCELLED. The reconciliation suite runs after every scenario (tests/Pest.php).

uses(DatabaseTruncation::class);

afterEach(function () {
    $this->truncateTablesForAllConnections();
});

beforeEach(function () {
    Authorization::catalogue();

    foreach (range(1, 14) as $number) {
        DocumentTypeFactory::ensure(sprintf('DOC-%02d', $number));
    }

    $this->world = InvoiceWorld::make();
    $this->project = $this->world->project();
});

/** A document's change reaching PRJ's participant inside its own command, the project held as DOC holds it; its word. */
function completionDocumentChange(int $actorId, int $companyId, int $projectId, ?array $link = null): string
{
    $request = new CommandRequest((string) Str::uuid(), 'DOC.ReplaceEvidence', $actorId, $companyId, 'evidence.view', Fingerprint::of('DOC.ReplaceEvidence', 'test', ['project' => $projectId]));
    $change = new DocumentChange(DocumentChange::EVIDENCE_REPLACED, (int) InvoiceWorld::linkedEvidence($companyId, $projectId)->id, $companyId, 'OTHER', [$link ?? ['link' => 0, 'target' => 'project_id', 'id' => $projectId, 'role' => 'SUPPORTING']]);

    return InvoiceWorld::word(app(CommandEnvelope::class)->run($request, function (CommandContext $context) use ($change, $projectId): CommandResult {
        $participant = app(ProjectDocumentChanges::class);
        $context->lock($participant->locks($change));
        $participant->documentChanged($context, $change);

        return new CommandResult('project', $projectId);
    }));
}

it('answers a Force Complete CONFLICT when a dispute hold changes its snapshot while it waits, the project unchanged (M-27; ST-04; criterion 5)', function () {
    $invoice = $this->world->issued($this->project, '1000000');
    InvoiceWorld::bill($invoice, now()->toDateString(), '2099-12-31');
    $owner = Authorization::owner()->id;
    $actor = $this->world->actorId;
    [$project, $company, $public] = [$this->project->id, $this->project->company_id, $this->project->public_id];
    $shown = app(ProjectCompletion::class)->read($project)['hash'];

    $results = Sessions::run([
        'hold' => static fn (): string => InvoiceWorld::hold(static fn (): string => InvoiceWorld::holdInCommand($actor, $invoice), 'the hold holds the project'),
        'force' => static function () use ($owner, $project, $company, $public, $shown): string {
            Barrier::await('the hold holds the project');

            return InvoiceWorld::word(app(ForceCompleteProject::class)->run((string) Str::uuid(), $owner, $project, $company, $public, 'Klien tidak dapat dihubungi', $shown));
        },
    ]);

    expect($results)->toBe(['hold' => 'COMMITTED', 'force' => 'CONFLICT ST-04'])
        ->and(DB::table('projects')->where('id', $project)->value('state'))->toBe('ACTIVE')
        ->and(DB::table('project_state_transitions')->where('project_id', $project)->where('transition_kind', 'FORCE_COMPLETE')->count())->toBe(0);

    // Shown again and confirmed, it commits.
    $current = app(ProjectCompletion::class)->read($project)['hash'];
    expect(InvoiceWorld::word(app(ForceCompleteProject::class)->run((string) Str::uuid(), $owner, $project, $company, $public, 'Klien tidak dapat dihubungi', $current)))->toBe('COMMITTED')
        ->and(DB::table('projects')->where('id', $project)->value('state'))->toBe('COMPLETED_FORCED');
});

it('returns a completed project to ACTIVE inside a document change\'s transaction, one REVALIDATE linked to it, the completion record kept (SF-REVAL; L-41; 7b; criterion 9)', function () {
    InvoiceWorld::readyToComplete($this->project->id);
    expect(InvoiceWorld::completeInCommand($this->world->actorId, $this->project->id))->toBe('COMMITTED')
        ->and(completionDocumentChange($this->world->actorId, $this->project->company_id, $this->project->id))->toBe('COMMITTED');

    $revalidate = DB::table('project_state_transitions')->where('project_id', $this->project->id)->where('transition_kind', 'REVALIDATE')->get();
    expect(DB::table('projects')->where('id', $this->project->id)->value('state'))->toBe('ACTIVE')->and($revalidate)->toHaveCount(1)
        ->and((string) $revalidate[0]->correction_reference)->toStartWith('DOC.EVIDENCE_REPLACED:')
        ->and(DB::table('project_state_transitions')->where('project_id', $this->project->id)->where('transition_kind', 'COMPLETE_NORMAL')->count())->toBe(1);
});

it('leaves a CANCELLED project CANCELLED under a document change (L-11; WF-PRJ-04; criterion 9)', function () {
    DB::table('projects')->where('id', $this->project->id)->update(['state' => 'CANCELLED']);

    expect(completionDocumentChange($this->world->actorId, $this->project->company_id, $this->project->id))->toBe('COMMITTED')
        ->and(DB::table('projects')->where('id', $this->project->id)->value('state'))->toBe('CANCELLED')
        ->and(DB::table('project_state_transitions')->where('project_id', $this->project->id)->where('transition_kind', 'REVALIDATE')->count())->toBe(0);
});

/** The project force-completed by its Owner on the snapshot as it stands. */
function completionForced(object $test): void
{
    $read = app(ProjectCompletion::class)->read($test->project->id);
    expect(InvoiceWorld::word(app(ForceCompleteProject::class)->run((string) Str::uuid(), Authorization::owner()->id, $test->project->id, $test->project->company_id, $test->project->public_id, 'Klien menutup kerja sama', $read['hash'])))->toBe('COMMITTED');
}

it('reaches the projects a payment\'s proof touches through its applications, so its unlink revalidates them (L-41; predicate 10; Codex P1)', function () {
    $invoice = $this->world->issued($this->project, '1000000');
    $payment = InvoiceWorld::payment($invoice, '400000');
    InvoiceWorld::apply($invoice, $payment, '400000');
    completionForced($this);

    expect(completionDocumentChange($this->world->actorId, $this->project->company_id, $this->project->id, ['link' => 0, 'target' => 'payment_id', 'id' => $payment->id, 'role' => 'PRIMARY_PROOF']))->toBe('COMMITTED')
        ->and(DB::table('projects')->where('id', $this->project->id)->value('state'))->toBe('ACTIVE')
        ->and((string) DB::table('project_state_transitions')->where('project_id', $this->project->id)->where('transition_kind', 'REVALIDATE')->value('correction_reference'))->toStartWith('DOC.EVIDENCE_REPLACED:');
});

it('runs an invoice issued on a completed project as a correction that returns it to ACTIVE (L-11; SF-REVAL; criterion 10; Codex P1)', function () {
    $draft = $this->world->draft($this->project, '1000000');
    completionForced($this);

    expect(InvoiceWorld::issue($this->world->actorId, $this->project->id, $draft))->toBe('COMMITTED')
        ->and(DB::table('projects')->where('id', $this->project->id)->value('state'))->toBe('ACTIVE')
        ->and((string) DB::table('project_state_transitions')->where('project_id', $this->project->id)->where('transition_kind', 'REVALIDATE')->value('correction_reference'))->toStartWith('FIN.InvoiceIssued:');
});

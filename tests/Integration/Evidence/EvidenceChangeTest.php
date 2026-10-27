<?php

use App\Modules\DOC\Actions\LinkEvidence;
use App\Modules\DOC\Actions\ReplaceEvidence;
use App\Modules\DOC\Actions\UnlinkEvidence;
use App\Modules\DOC\Contracts\DocumentChangeParticipant;
use App\Modules\DOC\Data\DocumentChange;
use App\Modules\DOC\Data\LinkHandle;
use App\Modules\DOC\Models\EvidenceDocument;
use App\Modules\DOC\Models\EvidenceDocumentVersion;
use App\Modules\ORG\Models\Company;
use App\Modules\PRJ\Models\Project;
use App\Support\Audit\AuditRecorder;
use App\Support\Audit\AuthorityClass;
use App\Support\Command\CommandContext;
use App\Support\Command\LockSet;
use App\Support\Command\Outcome;
use App\Support\Command\Refused;
use Database\Factories\DOC\FileObjectFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\RefreshDatabase;

// TL-05 (TASK-030; L-41; BR-ADM-03; architecture challenge 20a; criterion 7): replacing or unlinking evidence that a
// requirement and a settlement depended on reaches both through `DocumentChangeParticipant` inside DOC's transaction —
// their projects locked at LK-01 with the evidence, the change and its re-evaluations committed together, and a
// participant's refusal refusing the change with nothing written. The two participants stand in for ADM's and FIN's
// (TASK-035, TASK-051), which register under the same tag.

uses(RefreshDatabase::class);

/** A participant that records what it was given, as ADM's requirement and FIN's settlement re-evaluations will. */
final class RecordingParticipant implements DocumentChangeParticipant
{
    /** @var list<array{kind: string, links: int, transaction: int, project_locked: bool}> */
    public array $seen = [];

    public function __construct(public readonly string $name, public readonly ?int $project, public bool $refuse = false) {}

    public function locks(DocumentChange $change): LockSet
    {
        return $this->project === null ? new LockSet : (new LockSet)->add('projects', ['id' => $this->project]);
    }

    public function documentChanged(CommandContext $context, DocumentChange $change): void
    {
        $this->seen[] = ['kind' => $change->kind, 'links' => count($change->links), 'transaction' => DB::transactionLevel(), 'project_locked' => $this->project !== null && $context->derived()->contains('projects', ['id' => $this->project])];

        if ($this->refuse) {
            throw Refused::rejected('BR-ADM-03', ['participant' => $this->name]);
        }

        app(AuditRecorder::class)->record(action: "TEST.{$this->name}Reevaluated", authorityClass: AuthorityClass::System, actorUserId: null, companyId: null);
    }
}

beforeEach(function () {
    Authorization::catalogue();
    $this->company = Company::factory()->create();
    $this->actor = Authorization::admin([$this->company->id], ['projects.view', 'evidence.view', 'evidence.upload']);
    $this->project = Project::factory()->create(['company_id' => $this->company->id]);
    $this->evidence = EvidenceDocument::factory()->create(['company_id' => $this->company->id]);
    $versions = collect([1, 2])->map(fn (int $number) => EvidenceDocumentVersion::factory()->create(['evidence_document_id' => $this->evidence->id, 'version_no' => $number,
        'file_object_id' => FileObjectFactory::new()->create(['company_id' => $this->company->id])->id]));
    $this->evidence->forceFill(['current_version_id' => $versions[0]->id])->save();
    $this->requirement = new RecordingParticipant('Requirement', $this->project->id);
    $this->settlement = new RecordingParticipant('Settlement', null);
    app()->instance('test.requirement', $this->requirement);
    app()->instance('test.settlement', $this->settlement);
    app()->tag(['test.requirement', 'test.settlement'], DocumentChangeParticipant::TAG);
    app(LinkEvidence::class)->run((string) Str::uuid(), $this->actor->id, $this->evidence, $this->company->public_id, 'project_id', $this->project->id, $this->project->public_id, 'CLIENT_ORIGINAL');
});

it('re-evaluates the requirement and the settlement in the replacement\'s transaction, their project locked (L-41; 20a)', function () {
    $outcome = app(ReplaceEvidence::class)->run((string) Str::uuid(), $this->actor->id, $this->evidence, $this->company->public_id, 2, 'Pindaian ulang');

    expect($outcome->kind)->toBe(Outcome::COMMITTED)
        ->and($this->requirement->seen)->toHaveCount(1)
        ->and($this->requirement->seen[0])->toMatchArray(['kind' => DocumentChange::EVIDENCE_REPLACED, 'links' => 1, 'project_locked' => true])
        ->and($this->requirement->seen[0]['transaction'])->toBeGreaterThan(0)
        ->and($this->settlement->seen[0]['kind'])->toBe(DocumentChange::EVIDENCE_REPLACED)
        ->and(DB::table('audit_events')->whereIn('action', ['TEST.RequirementReevaluated', 'TEST.SettlementReevaluated', 'DOC.EvidenceReplaced'])->count())->toBe(3);
});

it('refuses the replacement and the unlink when a participant refuses, nothing written (L-41; BR-ADM-03)', function () {
    $this->settlement->refuse = true;
    $current = $this->evidence->refresh()->current_version_id;

    $replaced = app(ReplaceEvidence::class)->run((string) Str::uuid(), $this->actor->id, $this->evidence, $this->company->public_id, 2, null);
    $handle = LinkHandle::of((int) DB::table('evidence_links')->value('id'));
    $unlinked = app(UnlinkEvidence::class)->run((string) Str::uuid(), $this->actor->id, $this->evidence, $this->company->public_id, $handle, 'Salah tautan');

    expect([$replaced->kind, $replaced->code, $unlinked->kind, $unlinked->code])->toBe([Outcome::REJECTED, 'BR-ADM-03', Outcome::REJECTED, 'BR-ADM-03'])
        ->and($this->evidence->refresh()->current_version_id)->toBe($current)
        ->and(DB::table('evidence_links')->whereNull('unlinked_at')->count())->toBe(1)
        ->and(DB::table('audit_events')->whereIn('action', ['TEST.RequirementReevaluated', 'DOC.EvidenceReplaced', 'DOC.EvidenceUnlinked'])->count())->toBe(0);
});

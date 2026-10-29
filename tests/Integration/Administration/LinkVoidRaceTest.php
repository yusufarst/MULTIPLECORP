<?php

use App\Modules\DOC\Models\DocumentVersion;
use Database\Factories\DOC\DocumentTypeFactory;
use Illuminate\Support\Facades\DB;
use Tests\Support\Administration;
use Tests\Support\Authorization;
use Tests\Support\Concurrency\Sessions;
use Tests\Support\DatabaseTruncation;
use Tests\Support\Projects;
use Tests\Support\RequirementRaces;

// TL-05 (TASK-035; NX-03; NX-12; LK-30; L-41): a link of an issued version racing that version's void on real PostgreSQL
// sessions — both take the document's chain at LK-30, so the void either sees the link and unlinks it in its own
// transaction, or commits first and the link, re-reading the version under the lock, is REJECTED: never an active link
// to a voided version.

uses(DatabaseTruncation::class);

afterEach(function () {
    $this->truncateTablesForAllConnections();
});

beforeEach(function () {
    Authorization::catalogue();

    foreach (range(1, 14) as $number) {
        DocumentTypeFactory::ensure(sprintf('DOC-%02d', $number));
    }

    $this->company = Projects::company();
    $capabilities = ['projects.view', 'projects.manage', 'finance.view', 'requirements.manage', 'documents.correct'];
    $this->actors = [Authorization::admin([$this->company->id], $capabilities)->id, Authorization::admin([$this->company->id], $capabilities)->id];
    $this->project = Administration::project($this->actors[0], $this->company);
    $this->item = Administration::requirement($this->project);
    $this->version = Administration::document($this->project);
});

it('never leaves an active link to a voided version when a link races the void (NX-03; NX-12; LK-30; L-41)', function () {
    [$linker, $voider, $project, $item, $version, $document] = [$this->actors[0], $this->actors[1], $this->project->id, $this->item, $this->version->id, $this->version->document_id];

    $results = Sessions::run([
        'link' => static fn (): string => RequirementRaces::link($linker, $project, $item, $version),
        'void' => static fn (): string => RequirementRaces::void($voider, $document),
    ]);

    expect($results['void'])->toBe('COMMITTED')
        ->and($results['link'])->toBeIn(['COMMITTED', 'REJECTED BR-ADM-01'])
        ->and(DocumentVersion::query()->whereKey($version)->value('state'))->toBe('VOIDED')
        ->and(DB::table('admin_requirement_links')->where('document_version_id', $version)->whereNull('unlinked_at')->count())->toBe(0)
        ->and(DB::table('admin_requirement_links')->where('document_version_id', $version)->count())->toBe($results['link'] === 'COMMITTED' ? 1 : 0);
});

<?php

use App\Modules\PRJ\Models\Project;
use Database\Factories\DOC\EvidenceDocumentFactory;
use Illuminate\Support\Facades\DB;
use Tests\Support\Authorization;
use Tests\Support\Concurrency\Sessions;
use Tests\Support\ConfirmationRaces;
use Tests\Support\DatabaseTruncation;
use Tests\Support\Projects;

// TL-05 (TASK-034; AX-13; AX-14; BD-05; ST-06; L-49; criteria 1, 6) on real PostgreSQL sessions: two "Konfirmasi Proyek"
// of one project at once write one confirmation, the other CONFLICT; two "Batalkan Sisa" of one line's whole remainder at
// once cancel it once, the other REJECTED with the current remainder — the line guard deciding (C-05; PX-08).

uses(DatabaseTruncation::class);

afterEach(function () {
    $this->truncateTablesForAllConnections();
});

beforeEach(function () {
    Authorization::catalogue();
    $this->company = Projects::company();
    $capabilities = ['projects.view', 'projects.manage', 'projects.confirm', 'projects.cancel_scope', 'finance.view', 'evidence.view'];
    $this->actors = [Authorization::admin([$this->company->id], $capabilities)->id, Authorization::admin([$this->company->id], $capabilities)->id];
    expect(Projects::create($this->actors[0], $this->company, Projects::client(), [Projects::product()]))->toStartWith('COMMITTED');
    $this->project = Project::query()->sole();
    $this->evidence = EvidenceDocumentFactory::new()->createOne(['company_id' => $this->company->id, 'evidence_type' => 'CLIENT_SPK'])->id;
});

it('writes one confirmation for two at once, the other CONFLICT (AX-13; BD-05; criterion 1)', function () {
    [$first, $second, $id, $evidence] = [$this->actors[0], $this->actors[1], $this->project->id, $this->evidence];

    $results = Sessions::run([
        'first' => static fn (): string => ConfirmationRaces::confirm($first, $id, $evidence),
        'second' => static fn (): string => ConfirmationRaces::confirm($second, $id, $evidence),
    ]);

    expect(array_values($results))->toEqualCanonicalizing(['COMMITTED', 'CONFLICT BD-05'])
        ->and(DB::table('commercial_confirmations')->count())->toBe(1)
        ->and(DB::table('confirmation_line_pins')->count())->toBe(1);
});

it('cancels a line\'s whole remainder once for two cancellations at once, the other REJECTED with the remainder (AX-14; ST-06; criterion 6)', function () {
    expect(ConfirmationRaces::confirm($this->actors[0], $this->project->id, $this->evidence))->toBe('COMMITTED');
    $line = (int) DB::table('project_lines')->where('project_id', $this->project->id)->value('id');
    $whole = (string) DB::table('project_line_balances')->where('project_line_id', $line)->value('demand_qty_base');
    [$first, $second, $id] = [$this->actors[0], $this->actors[1], $this->project->id];

    $results = Sessions::run([
        'first' => static fn (): string => ConfirmationRaces::cancel($first, $id, $line, $whole),
        'second' => static fn (): string => ConfirmationRaces::cancel($second, $id, $line, $whole),
    ]);

    expect(array_values($results))->toEqualCanonicalizing(['COMMITTED', 'REJECTED ST-06'])
        ->and(DB::table('line_scope_cancellations')->count())->toBe(1)
        ->and(DB::table('project_line_balances')->where('project_line_id', $line)->value('cancelled_qty_base'))->toBe($whole);
});

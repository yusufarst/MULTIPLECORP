<?php

use App\Modules\DOC\Models\EvidenceDocument;
use App\Modules\DOC\Queries\EvidenceMatches;
use App\Modules\ORG\Models\Company;
use Database\Factories\DOC\FileObjectFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Tests\Support\Authorization;
use Tests\Support\RefreshDatabase;

// TL-15 (TASK-030; QB-12; DIR-089 (2); BD-15; AS-08; criterion 11): PG-038's page in a number of statements that never
// depends on the rows it shows — one evidence and a full page of 25 —, and each FL-10 lookup that reads the type-free
// identity index — the cross-type warning across every company and the pool, and the reference-less duplicate in one
// company — served by it in `EXPLAIN`, sequential scans disabled so the plan shows whether the index can serve it.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->company = Company::factory()->create();
    $this->viewer = Authorization::admin([$this->company->id], ['projects.view', 'evidence.view', 'evidence.upload']);
});

afterEach(fn () => @unlink($this->hotFile));

/** The statements a request runs. */
function evidenceStatements(callable $request): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $request();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

it('renders PG-038 in the same number of statements for one evidence and for 25 (QB-12; PFM-01)', function () {
    EvidenceDocument::factory()->create(['company_id' => $this->company->id]);
    // The first request warms the account's caches; the two measured ones differ only in their rows.
    $this->actingAs($this->viewer)->get('/dokumen/bukti')->assertOk();
    $one = evidenceStatements(fn () => $this->actingAs($this->viewer)->get('/dokumen/bukti')->assertOk());

    EvidenceDocument::factory()->count(30)->create(['company_id' => $this->company->id]);
    $many = evidenceStatements(fn () => $this->actingAs($this->viewer)->get('/dokumen/bukti')->assertOk());

    expect($many)->toBe($one)->and($one)->toBeLessThanOrEqual(30);
});

it('serves each FL-10 lookup of the type-free identity from its index, across companies and the pool (DIR-089 (2); BD-15; AS-08)', function () {
    Company::factory()->count(3)->create();

    // Every company holds many identities, a third of them without a reference, and the pool some of its own.
    DB::statement(<<<'SQL'
        insert into evidence_documents (evidence_scope, company_id, evidence_type, declared_issuer, issuer_reference, document_date, identity_key)
            select 'COMPANY', c.id, 'CLIENT_ORDER', 'Penerbit ' || g, case when g % 3 = 0 then null else 'R/' || g end, date '2026-10-01' - g % 30,
                'penerbit ' || g || '|CLIENT_ORDER|' || case when g % 3 = 0 then '' else 'r/' || g end || '|' || (date '2026-10-01' - g % 30)
            from companies c, generate_series(1, 1500) g
        SQL);
    DB::statement(<<<'SQL'
        insert into evidence_documents (evidence_scope, company_id, evidence_type, declared_issuer, issuer_reference, document_date, identity_key)
            select 'POOL', null, 'WAREHOUSE_RECORD', 'Gudang ' || g, 'G/' || g, date '2026-10-01', 'gudang ' || g || '|WAREHOUSE_RECORD|g/' || g || '|2026-10-01'
            from generate_series(1, 300) g
        SQL);

    DB::statement('analyze evidence_documents');
    $file = FileObjectFactory::new()->create(['company_id' => $this->company->id]);
    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        if (str_contains($query->sql, 'split_part(identity_key')) {
            $queries[] = [$query->sql, $query->bindings];
        }
    });

    app(EvidenceMatches::class)->of($file->sha256, $file->id, 'Penerbit 7', 'CLIENT_SPK', 'R/7', '2026-10-01', null, $this->company->id);
    app(EvidenceMatches::class)->of($file->sha256, $file->id, 'Penerbit 9', 'CLIENT_ORDER', null, '2026-10-01', null, $this->company->id);

    expect($queries)->toHaveCount(2);
    DB::statement('set local enable_seqscan = off');

    foreach ($queries as [$sql, $bindings]) {
        $plan = implode("\n", array_map(fn (object $row): string => (string) array_values((array) $row)[0], DB::select("explain {$sql}", $bindings)));

        expect($plan)->toContain('evidence_documents_type_free_identity');
    }
});

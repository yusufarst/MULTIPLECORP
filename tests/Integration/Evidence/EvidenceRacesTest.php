<?php

use App\Modules\DOC\Data\EvidenceIdentity;
use App\Modules\ORG\Models\Company;
use Database\Factories\DOC\FileObjectFactory;
use Illuminate\Support\Facades\DB;
use Tests\Support\Authorization;
use Tests\Support\Concurrency\Barrier;
use Tests\Support\Concurrency\Sessions;
use Tests\Support\DatabaseTruncation;
use Tests\Support\EvidenceRaces;

// TL-05 (TASK-030; BD-14; BD-15; FU-10; criterion 2): two registrations under real PostgreSQL sessions — of one identity
// in one company, and of one content under two identities — run one after the other on FU-10's registration keys,
// held from before the first one's transaction until it has ended, so the second sees the first: the identity offered
// as a new version (L-45), the content a duplicate warning (FL-10), never two evidence documents unwarned.

uses(DatabaseTruncation::class);

afterEach(function () {
    $this->truncateTablesForAllConnections();
});

beforeEach(function () {
    Authorization::catalogue();
    $this->company = Company::factory()->create();
    $this->actor = Authorization::admin([$this->company->id], ['projects.view', 'evidence.view', 'evidence.upload']);
});

/** An upload of the actor for the company, its content the given text's. */
function raceUpload(object $test, string $content): string
{
    return FileObjectFactory::new()->create(['company_id' => $test->company->id, 'uploaded_by' => $test->actor->id, 'sha256' => hash('sha256', $content)])->public_id;
}

it('runs two registrations of one identity one after the other, the second offered the first as a version (BD-14; L-45)', function () {
    [$actor, $company, $public] = [$this->actor->id, $this->company->id, $this->company->public_id];
    $identity = ['type' => 'CLIENT_ORDER', 'issuer' => 'SMA Negeri 2 Contoh', 'reference' => 'SP/RACE/1', 'date' => '2026-10-02'];
    $first = [...$identity, 'file' => raceUpload($this, 'isi pertama')];
    $second = [...$identity, 'file' => raceUpload($this, 'isi kedua')];
    $key = EvidenceIdentity::registrationKeys(hash('sha256', 'isi pertama'), $identity['issuer'], $identity['reference'], $identity['date'], $company)[1];

    $results = Sessions::run([
        'first' => static fn (): string => EvidenceRaces::holdThenRegister($key, $actor, $company, $public, $first),
        'second' => static function () use ($actor, $company, $public, $second): string {
            Barrier::await('the first registration holds its key');

            return EvidenceRaces::register($actor, $company, $public, $second);
        },
    ]);

    expect($results)->toBe(['first' => 'COMMITTED', 'second' => 'REJECTED L-45'])
        ->and(DB::table('evidence_documents')->count())->toBe(1);
});

it('runs two registrations of one content one after the other, the second warned of the first (BD-15; FL-10)', function () {
    [$actor, $company, $public] = [$this->actor->id, $this->company->id, $this->company->public_id];
    $first = ['type' => 'CLIENT_ORDER', 'issuer' => 'SMA Negeri 3 Contoh', 'reference' => 'SP/A', 'date' => '2026-10-02', 'file' => raceUpload($this, 'isi sama')];
    $second = ['type' => 'CLIENT_SPK', 'issuer' => 'SMA Negeri 4 Contoh', 'reference' => 'SPK/B', 'date' => '2026-10-03', 'file' => raceUpload($this, 'isi sama')];
    $key = EvidenceIdentity::contentKey(hash('sha256', 'isi sama'));

    $results = Sessions::run([
        'first' => static fn (): string => EvidenceRaces::holdThenRegister($key, $actor, $company, $public, $first),
        'second' => static function () use ($actor, $company, $public, $second): string {
            Barrier::await('the first registration holds its key');

            return EvidenceRaces::register($actor, $company, $public, $second);
        },
    ]);

    expect($results)->toBe(['first' => 'COMMITTED', 'second' => 'REJECTED FL-10'])
        ->and(DB::table('evidence_documents')->count())->toBe(1);
});

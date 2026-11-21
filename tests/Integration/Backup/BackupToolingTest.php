<?php

use App\Modules\DOC\Contracts\RenditionTemplate;
use App\Modules\DOC\Models\Document;
use App\Modules\DOC\Models\DocumentRendition;
use App\Modules\DOC\Models\DocumentVersion;
use App\Modules\DOC\Services\DocumentChain;
use App\Modules\DOC\Services\DocumentPaper;
use App\Modules\IAM\Models\User;
use App\Support\Backup\DiskProbe;
use App\Support\Backup\Generations;
use App\Support\Backup\GenerationStatus;
use App\Support\Backup\Manifest;
use App\Support\Backup\OffsiteCopy;
use App\Support\Backup\PostgresTools;
use App\Support\Backup\SealedStream;
use App\Support\Backup\TarArchive;
use App\Support\Database\DatabaseRole;
use Carbon\CarbonImmutable;
use Database\Factories\DOC\DocumentTypeFactory;
use Database\Factories\DOC\FileObjectFactory;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\Support\DatabaseTruncation;
use Tests\Support\Documents\SyntheticSubjects;

// TL-05 (TASK-074; TEST_STRATEGY §15; AC-18, AC-19; BK-01–BK-12; BKR-01–BKR-09), on real PostgreSQL with the server's
// own dump and restore tools and synthetic data: a run's coherent generation and its manifest; a file row without its
// bytes, a corrupt, missing and incomplete generation and a stale backup each reported failed; rotation across
// generations and the retention boundary; a failed cleanup deleting nothing required; disk pressure by a lowered
// threshold; the encrypted copy to a test destination, verified there, and a failed copy; and a restore into a new
// database and directory with the paired key, its reconciliation re-rendering an owed rendition from the issued payload
// without reissuing, `ReconcileGuards`' read and the live state untouched.

uses(DatabaseTruncation::class);

beforeEach(function () {
    $this->root = sys_get_temp_dir().'/backup-it-'.bin2hex(random_bytes(6));
    $this->backups = $this->root.'/backups';
    $this->restoreDatabase = config('database.connections.'.config('database.default').'.database').'_restore';
    mkdir($this->root.'/files-live', 0700, true);
    config([
        'backup.directory' => $this->backups,
        'backup.key_register_entry' => 'K-2026-1',
        'backup.commit' => 'abc1234def',
        'filesystems.disks.files.root' => $this->root.'/files-live',
        'files.disk' => 'files',
        // The operator's login for the dump and the restore: the owner role's, given to the run alone (DATABASE §28).
        'backup.database.username' => config('database.connections.'.DatabaseRole::MIGRATION_CONNECTION.'.username'),
        'backup.database.password' => config('database.connections.'.DatabaseRole::MIGRATION_CONNECTION.'.password'),
    ]);
    Storage::forgetDisk('files');
    app()->forgetInstance(RenditionTemplate::class);
    SyntheticSubjects::register();

    foreach (range(1, 14) as $number) {
        DocumentTypeFactory::ensure(sprintf('DOC-%02d', $number));
    }
});

afterEach(function () {
    foreach ([PostgresTools::CONNECTION, 'restored_check', config('database.default'), DatabaseRole::ADMINISTRATION_CONNECTION] as $connection) {
        DB::purge($connection);
    }

    // A purged connection's server process ends a moment after it; the owner may end only its own role's sessions.
    $owner = DB::connection(DatabaseRole::MIGRATION_CONNECTION);

    for ($wait = 0; $wait < 100 && (int) $owner->scalar('select count(*) from pg_stat_activity where datname = ? and usename <> current_user', [$this->restoreDatabase]) > 0; $wait++) {
        usleep(100_000);
    }

    $owner->statement('drop database if exists "'.$this->restoreDatabase.'" with (force)');
    File::deleteDirectory($this->root);
    $this->truncateTablesForAllConnections();
});

/** A file row of the given purpose with its bytes on the live files' disk. */
function backedFile(string $content, string $purpose = 'UPLOAD'): object
{
    $key = '2026/11/'.bin2hex(random_bytes(8));
    Storage::disk('files')->put($key, $content);

    return FileObjectFactory::new()->create(['storage_disk' => 'files', 'storage_key' => $key, 'size_bytes' => strlen($content), 'sha256' => hash('sha256', $content), 'purpose' => $purpose]
        + ($purpose === 'EXPORT' ? ['company_id' => null] : []));
}

/** An issued DOC-09 version whose rendition was still owed when the backup ran. */
function owedVersion(): DocumentVersion
{
    $paper = DocumentPaper::compose('DOC-09', 'STANDARD', SyntheticSubjects::sample('DOC-09'),
        ['name' => 'CV Contoh Usaha', 'npwp' => '01.234.567.8-901.000', 'address' => ['Jl. Contoh No. 1'], 'city' => 'Yogyakarta', 'phone' => null, 'email' => null, 'director' => 'Budi Contoh'],
        ['bank_name' => 'Bank Contoh', 'account_number' => '0123456789', 'account_holder' => 'CV Contoh Usaha', 'branch' => null], '2026-10-28', 'CTH/SPK/2026/0007', false);
    $payload = ['paper' => $paper, 'assets' => ['logo' => null, 'stamp' => null, 'signature' => null]];
    $version = DocumentVersion::factory()->issued()->for(Document::factory(), 'document')->create(['payload' => $payload, 'payload_sha256' => DocumentChain::checksum($payload)]);
    (new DocumentRendition)->forceFill(['document_version_id' => $version->id, 'attempt_no' => 1, 'state' => 'PENDING'])->save();

    return $version;
}

/** A copy of a verified generation under another time, its manifest renamed with it: the retention's synthetic history. */
function generationAt(string $backups, string $source, string $at, string $kind = 'daily'): string
{
    $name = Generations::name(CarbonImmutable::parse($at, 'UTC'), 'abc1234def');
    File::copyDirectory($backups.'/'.$source, $backups.'/'.$name);
    $manifest = json_decode((string) file_get_contents($backups.'/'.$name.'/'.Manifest::FILE), true);
    file_put_contents($backups.'/'.$name.'/'.Manifest::FILE, json_encode([...$manifest, 'generation' => $name, 'kind' => $kind, 'created_at' => $at]));

    return $name;
}

it('writes one coherent generation — the dump and every file row\'s bytes — whose manifest verifies, the operator\'s alone (criteria 1, 4, 8)', function () {
    $files = [backedFile('bukti transfer'), backedFile('logo perusahaan', 'IDENTITY_ASSET')];
    backedFile('ekspor sementara', 'EXPORT');

    $this->artisan('backup:run', ['--kind' => 'daily'])->expectsOutputToContain('Backup: VERIFIED')->assertExitCode(0);

    [$generation] = Generations::in($this->backups);
    $verification = Generations::verify($generation['path']);
    $manifest = (string) file_get_contents($generation['path'].'/'.Manifest::FILE);

    expect($generation['name'])->toEndWith('-abc1234def')
        ->and($verification['status'])->toBe(GenerationStatus::Verified)
        ->and($verification['manifest']?->fileCount)->toBe(2)
        ->and($verification['manifest']?->keyRegisterEntry)->toBe('K-2026-1')
        ->and(array_keys(TarArchive::names($generation['path'].'/'.Manifest::FILES)))->toEqualCanonicalizing(array_map(fn (object $file): string => 'files/'.$file->storage_key, $files))
        ->and(substr((string) file_get_contents($generation['path'].'/'.Manifest::DUMP), 0, 5))->toBe('PGDMP')
        ->and($manifest)->not->toContain((string) config('app.key'))->not->toContain((string) config('database.connections.migration.password'))
        ->and(fileperms($generation['path']) & 0777)->toBe(0700);

    foreach ([Manifest::FILE, Manifest::DUMP, Manifest::FILES] as $part) {
        expect(fileperms($generation['path'].'/'.$part) & 0777)->toBe(0600, $part);
    }
});

it('reports a run whose file row lacks its bytes, a corrupt generation and a stale backup as failures, never successes (criterion 2; BK-04, BK-10)', function () {
    $file = backedFile('bukti transfer');
    $this->artisan('backup:run')->assertExitCode(0);
    [$first] = Generations::in($this->backups);
    $this->artisan('backup:status')->expectsOutputToContain('VERIFIED')->assertExitCode(0);

    // A file row without its bytes: the run fails, its generation incomplete, the verified one untouched.
    Storage::disk('files')->delete($file->storage_key);
    $this->travel(1)->seconds();
    $this->artisan('backup:run')->expectsOutputToContain('Backup: FAILED')->assertExitCode(1);
    [$failed] = Generations::in($this->backups);

    expect(Generations::verify($failed['path'])['status'])->toBe(GenerationStatus::Incomplete)
        ->and(Generations::verify($first['path'])['status'])->toBe(GenerationStatus::Verified);

    // A changed byte in the verified generation: corrupt, alerted, the run's status a failure.
    $dump = $first['path'].'/'.Manifest::DUMP;
    $bytes = (string) file_get_contents($dump);
    $bytes[10] = chr(ord($bytes[10]) ^ 1);
    file_put_contents($dump, $bytes);
    $this->artisan('backup:status')->expectsOutputToContain('CORRUPT')->assertExitCode(1);

    // No verified generation within 26 hours: stale.
    File::deleteDirectory($failed['path']);
    $bytes[10] = chr(ord($bytes[10]) ^ 1);
    file_put_contents($dump, $bytes);
    $this->travel(27)->hours();
    $this->artisan('backup:status')->expectsOutputToContain('STALE')->assertExitCode(1);
});

it('removes only the verified generations the retention lets expire, and a failed cleanup deletes nothing required (criterion 3; BK-05, BK-12)', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-20 12:00', 'Asia/Jakarta'));
    backedFile('bukti transfer');
    $this->artisan('backup:run')->assertExitCode(0);
    [$seed] = Generations::in($this->backups);
    $old = [];

    foreach (range(2, 10) as $day) {
        $old[] = generationAt($this->backups, $seed['name'], now()->subDays($day)->utc()->format('Y-m-d H:i:s'));
    }

    $predeploy = generationAt($this->backups, $seed['name'], now()->subDays(20)->utc()->format('Y-m-d H:i:s'), 'predeploy');
    mkdir($this->backups.'/'.Generations::name(now()->subDays(30)->toImmutable(), 'abc1234def'), 0700);
    config(['backup.retention.daily' => 3, 'backup.retention.weekly' => 0, 'backup.retention.monthly' => 0]);

    // A cleanup that cannot remove a directory stops there, reported failed; every kept generation stays.
    app()->instance(Filesystem::class, new class extends Filesystem
    {
        public function deleteDirectory($directory, $preserve = false)
        {
            return false;
        }
    });
    $this->travel(1)->minutes();
    $this->artisan('backup:run')->expectsOutputToContain('Cleanup: FAILED')->assertExitCode(1);
    expect(count(Generations::in($this->backups)))->toBe(13);

    app()->forgetInstance(Filesystem::class);
    app()->instance(Filesystem::class, new Filesystem);
    $this->travel(1)->minutes();
    $this->artisan('backup:run')->expectsOutputToContain('Cleanup: OK')->assertExitCode(0);
    $left = array_column(Generations::in($this->backups), 'name');

    // Kept: the newest generation for today, the two days before, the pre-deployment generation, and the two never
    // verified — the one the failed cleanup left without its manifest among them; the rest expired.
    expect($left)->toContain($predeploy, $old[0], $old[1])
        ->and(count($left))->toBe(6)
        ->and(array_intersect($left, $old))->toHaveCount(2)
        ->and(collect(Generations::in($this->backups))->filter(fn (array $g): bool => Generations::verify($g['path'])['status'] === GenerationStatus::Incomplete))->toHaveCount(2);
});

it('skips a run at the critical disk level without the space for it, reported and deleting nothing (BK-11; BACKUP_RECOVERY §3)', function () {
    backedFile('bukti transfer');
    config(['backup.disk.warn_pct' => 1, 'backup.disk.critical_pct' => 1]);

    // The lowered threshold on the real disk: critical, but the generation fits, so it runs and the operator is alerted.
    $this->artisan('backup:run')->expectsOutputToContain('Disk: CRITICAL')->assertExitCode(0);

    app()->instance(DiskProbe::class, new class extends DiskProbe
    {
        public function measure(string $path): array
        {
            return ['total' => 1000, 'free' => 1];
        }
    });
    $this->travel(1)->minutes();
    $this->artisan('backup:run')->expectsOutputToContain('Backup: SKIPPED')->assertExitCode(1);

    expect(Generations::in($this->backups))->toHaveCount(1);
});

it('copies only a verified generation, encrypted and verified at the destination; a failed copy alerts and deletes nothing (criterion 5; 13a–13c)', function () {
    backedFile('bukti transfer');
    $pair = SealedStream::keyPair();
    mkdir($this->root.'/offsite', 0700);
    config(['backup.copy.destination' => $this->root.'/offsite', 'backup.copy.public_key' => $pair['public']]);
    $this->artisan('backup:run')->assertExitCode(0);
    [$generation] = Generations::in($this->backups);

    $this->artisan('backup:copy')->expectsOutputToContain('Off-server copy: VERIFIED')->assertExitCode(0);
    $copy = $this->root.'/offsite/'.$generation['name'];

    expect(OffsiteCopy::verifyCopy($copy))->toBeNull()
        ->and(str_contains((string) file_get_contents($copy.'/'.Manifest::DUMP.'.enc'), 'PGDMP'))->toBeFalse()
        ->and(collect(File::allFiles($this->root.'/offsite'))->merge(File::allFiles($this->backups))->contains(fn ($file): bool => str_contains((string) file_get_contents($file->getPathname()), $pair['secret'])))->toBeFalse();
    $this->artisan('backup:status')->expectsOutputToContain('Off-server copy: ')->assertExitCode(0);

    // Brought back with the secret key, from outside the VPS: a verified generation; a changed copy is refused.
    $keyFile = $this->root.'/secret-store.key';
    file_put_contents($keyFile, $pair['secret']);
    $this->artisan('backup:fetch', ['copy' => $copy, '--secret-key-file' => $keyFile, '--into' => $this->root.'/fetched'])->expectsOutputToContain('VERIFIED')->assertExitCode(0);
    expect(Generations::verify($this->root.'/fetched/'.$generation['name'])['status'])->toBe(GenerationStatus::Verified);

    $tampered = (string) file_get_contents($copy.'/'.Manifest::FILES.'.enc');
    file_put_contents($copy.'/'.Manifest::FILES.'.enc', substr($tampered, 0, -1).'x');
    $this->artisan('backup:fetch', ['copy' => $copy, '--secret-key-file' => $keyFile, '--into' => $this->root.'/fetched-again'])->expectsOutputToContain('CORRUPT')->assertExitCode(1);

    // An unverified generation is never copied, and an unmounted destination fails without deleting a generation.
    File::deleteDirectory($copy);
    file_put_contents($generation['path'].'/'.Manifest::DUMP, 'changed');
    $this->artisan('backup:copy', ['generation' => $generation['name']])->expectsOutputToContain('only a verified generation')->assertExitCode(1);
    config(['backup.copy.destination' => $this->root.'/not-mounted']);
    $this->artisan('backup:copy', ['generation' => $generation['name']])->expectsOutputToContain('FAILED')->assertExitCode(1);

    expect(is_dir($generation['path']))->toBeTrue();
});

it('restores into a new database and directory with the paired key, re-renders an owed rendition and reads the guards, the live state untouched (criteria 4, 6)', function () {
    $file = backedFile('bukti transfer');
    $version = owedVersion();
    User::factory()->create(['two_factor_secret' => Crypt::encryptString('JBSWY3DPEHPK3PXP'), 'two_factor_recovery_codes' => Crypt::encryptString('[]'),
        'two_factor_confirmed_at' => now(), 'two_factor_last_step' => 0]);
    $this->artisan('backup:run')->assertExitCode(0);
    [$generation] = Generations::in($this->backups);
    $live = (string) config('database.connections.'.config('database.default').'.database');
    $owner = DB::connection(DatabaseRole::MIGRATION_CONNECTION);

    // Never into the live database or files, and never without the operator's name.
    $this->artisan('backup:restore', ['generation' => $generation['name'], '--database' => $live, '--files-root' => $this->root.'/restored', '--operator' => 'Yusuf Operator'])
        ->expectsOutputToContain('never goes into the live database')->assertExitCode(1);
    $this->artisan('backup:restore', ['generation' => $generation['name'], '--database' => $this->restoreDatabase, '--files-root' => $this->root.'/files-live', '--operator' => 'Yusuf Operator'])
        ->expectsOutputToContain('live files')->assertExitCode(1);
    $this->artisan('backup:restore', ['generation' => $generation['name'], '--database' => $this->restoreDatabase, '--files-root' => $this->root.'/restored'])->assertExitCode(2);

    $owner->statement('create database "'.$this->restoreDatabase.'"');
    $this->artisan('backup:restore', ['generation' => $generation['name'], '--database' => $this->restoreDatabase, '--files-root' => $this->root.'/restored', '--operator' => 'Yusuf Operator'])
        ->expectsOutputToContain('"usable": true')->assertExitCode(0);

    config(['database.connections.restored_check' => [...config('database.connections.'.DatabaseRole::MIGRATION_CONNECTION), 'database' => $this->restoreDatabase]]);
    $restored = DB::connection('restored_check');
    $rendition = $restored->table('document_renditions')->where('document_version_id', $version->id)->where('state', 'READY')->first();
    $record = json_decode((string) file_get_contents(File::files($this->backups.'/restores')[0]->getPathname()), true);

    expect($rendition)->not->toBeNull()
        ->and(is_file($this->root.'/restored/files/'.$restored->table('file_objects')->where('id', $rendition->file_object_id)->value('storage_key')))->toBeTrue()
        ->and(file_get_contents($this->root.'/restored/files/'.$file->storage_key))->toBe('bukti transfer')
        ->and($restored->table('document_versions')->where('id', $version->id)->value('formatted_number'))->toBe($version->formatted_number)
        ->and($restored->table('document_versions')->count())->toBe(DB::table('document_versions')->count())
        // The live state: its rendition still owed, its files unchanged.
        ->and(DB::table('document_renditions')->where('document_version_id', $version->id)->pluck('state')->all())->toBe(['PENDING'])
        ->and(count(File::allFiles($this->root.'/files-live')))->toBe(1)
        ->and($record)->toMatchArray(['generation' => $generation['name'], 'key_register_entry' => 'K-2026-1', 'usable' => true, 'within_rto' => true, 'within_rpo' => true])
        ->and($record['files'])->toMatchArray(['missing_bytes' => 0, 'mismatched_bytes' => 0, 'orphan_files' => 0])
        ->and($record['renditions'])->toMatchArray(['owed' => 1, 'rendered' => 1, 'failed' => 0])
        ->and($record['guards']['differences'])->toBe([])
        ->and(fileperms(File::files($this->backups.'/restores')[0]->getPathname()) & 0777)->toBe(0600);
    DB::purge('restored_check');
});

it('refuses a key that does not open the restored second-factor secrets, and sends a corrupt generation to the next older verified one (BKR-05, BKR-08)', function () {
    backedFile('bukti transfer');
    User::factory()->create(['two_factor_secret' => Crypt::encryptString('JBSWY3DPEHPK3PXP'), 'two_factor_recovery_codes' => Crypt::encryptString('[]'),
        'two_factor_confirmed_at' => now(), 'two_factor_last_step' => 0]);
    $this->artisan('backup:run')->assertExitCode(0);
    $this->travel(1)->days();
    $this->artisan('backup:run')->assertExitCode(0);
    [$newer, $older] = Generations::in($this->backups);
    file_put_contents($newer['path'].'/'.Manifest::FILES, 'changed');
    $owner = DB::connection(DatabaseRole::MIGRATION_CONNECTION);
    $owner->statement('create database "'.$this->restoreDatabase.'"');
    $arguments = ['generation' => $newer['name'], '--database' => $this->restoreDatabase, '--files-root' => $this->root.'/restored', '--operator' => 'Yusuf Operator'];

    $this->artisan('backup:restore', $arguments)->expectsOutputToContain("the next older verified generation is {$older['name']}")->assertExitCode(1);

    // Another APP_KEY than the generation's pair: refused before any second-factor secret is read.
    $key = config('app.key');
    config(['app.key' => 'base64:'.base64_encode(random_bytes(32)), 'app.previous_keys' => []]);
    app()->forgetInstance('encrypter');
    Facade::clearResolvedInstance('encrypter');

    try {
        $this->artisan('backup:restore', [...$arguments, '--fallback' => true])->expectsOutputToContain('does not open the restored second-factor secrets')->assertExitCode(1);
    } finally {
        config(['app.key' => $key]);
        app()->forgetInstance('encrypter');
        Facade::clearResolvedInstance('encrypter');
    }

    // A generation paired with a leaked key goes to Scenario B, never restored with that key.
    config(['backup.leaked_key_entries' => ['K-2026-1']]);
    $this->artisan('backup:restore', ['generation' => $older['name']] + $arguments)->expectsOutputToContain('Scenario B')->assertExitCode(1);
});

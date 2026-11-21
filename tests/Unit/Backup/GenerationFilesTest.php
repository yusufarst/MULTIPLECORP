<?php

use App\Support\Backup\Generations;
use App\Support\Backup\GenerationStatus;
use App\Support\Backup\Manifest;
use App\Support\Backup\SealedStream;
use App\Support\Backup\TarArchive;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;

// TL-03 (TASK-074; BK-07; BKR-01, BKR-02, BKR-09): a generation verifies only when its manifest lists the dump and the
// files and each part has its size and checksum — an incomplete run, a missing part and a changed byte each reported
// as such, never verified —; the files' archive round-trips and refuses an entry outside its directory; the copy's
// encryption opens only with its secret key and refuses a truncated or altered file.

beforeEach(function () {
    $this->root = sys_get_temp_dir().'/backup-unit-'.bin2hex(random_bytes(6));
    mkdir($this->root, 0700, true);
});

afterEach(function () {
    File::deleteDirectory($this->root);
});

/** A generation directory written as a run writes one. */
function writtenGeneration(string $root, string $name = '20261120T200000Z-abc1234'): string
{
    $path = $root.'/'.$name;
    mkdir($path, 0700);
    file_put_contents($path.'/'.Manifest::DUMP, 'PGDMP synthetic dump');
    is_dir($root.'/source/files/2026/11') || mkdir($root.'/source/files/2026/11', 0700, true);
    file_put_contents($root.'/source/files/2026/11/a', 'bukti');
    TarArchive::write($path.'/'.Manifest::FILES, [['name' => 'files/2026/11/a', 'path' => $root.'/source/files/2026/11/a']]);
    $parts = array_map(fn (string $part): array => ['name' => $part, 'sha256' => (string) hash_file('sha256', $path.'/'.$part), 'bytes' => (int) filesize($path.'/'.$part)], [Manifest::DUMP, Manifest::FILES]);
    (new Manifest($name, 'daily', CarbonImmutable::parse('2026-11-20 20:00:05'), CarbonImmutable::parse('2026-11-20 20:00:01'), 'abc1234', '18.6', 'K-2026-1', $parts, 1))->write($path);

    return $path;
}

it('verifies a complete generation and reports an incomplete, corrupt or missing one as such (BK-07; BKR-02)', function () {
    $path = writtenGeneration($this->root);

    expect(Generations::verify($path)['status'])->toBe(GenerationStatus::Verified)
        ->and(Generations::verify($this->root.'/20261119T200000Z-abc1234')['status'])->toBe(GenerationStatus::Missing);

    file_put_contents($path.'/'.Manifest::DUMP, 'PGDMP synthetic dumq');
    expect(Generations::verify($path))->status->toBe(GenerationStatus::Corrupt)->reason->toContain('checksum')
        ->and(Generations::verify($path, checksums: false)['status'])->toBe(GenerationStatus::Verified);

    file_put_contents($path.'/'.Manifest::DUMP, 'PGDMP synthetic dump');
    unlink($path.'/'.Manifest::FILES);
    expect(Generations::verify($path))->status->toBe(GenerationStatus::Corrupt)->reason->toContain('missing');

    unlink($path.'/'.Manifest::FILE);
    expect(Generations::verify($path))->status->toBe(GenerationStatus::Incomplete);

    file_put_contents($path.'/'.Manifest::FILE, '{"format": 1, "parts": [');
    expect(Generations::verify($path)['status'])->toBe(GenerationStatus::Corrupt);

    // Valid JSON with a damaged time is corrupt too, never an error that would stop the fallback to an older generation.
    file_put_contents($path.'/'.Manifest::FILE, json_encode(['format' => 1, 'generation' => basename($path), 'kind' => 'daily', 'created_at' => 'kemarin sore',
        'recovery_point' => '2026-13-45', 'commit' => 'abc1234', 'postgresql' => '18.6', 'key_register_entry' => 'K-2026-1', 'parts' => []]));
    expect(Generations::verify($path)['status'])->toBe(GenerationStatus::Corrupt);
});

it('lists generations newest first by their names and keeps the manifest free of any key (BKR-08)', function () {
    writtenGeneration($this->root, '20261118T200000Z-abc1234');
    writtenGeneration($this->root, '20261120T200000Z-def5678');
    mkdir($this->root.'/not-a-generation');

    expect(array_column(Generations::in($this->root), 'name'))->toBe(['20261120T200000Z-def5678', '20261118T200000Z-abc1234'])
        ->and(Generations::name(CarbonImmutable::parse('2026-11-21 03:00:00', 'Asia/Jakarta'), 'ABC1234DEF5678abc'))->toBe('20261120T200000Z-abc1234def56')
        ->and(Manifest::read($this->root.'/20261118T200000Z-abc1234')?->keyRegisterEntry)->toBe('K-2026-1')
        ->and((string) file_get_contents($this->root.'/20261118T200000Z-abc1234/'.Manifest::FILE))->not->toContain('base64:')
        ->and(fileperms($this->root.'/20261118T200000Z-abc1234/'.Manifest::FILE) & 0777)->toBe(0600);
});

it('round-trips the files\' archive and refuses an entry outside its directory (BKR-01)', function () {
    $path = writtenGeneration($this->root);
    $into = $this->root.'/restored';
    mkdir($into, 0700);

    expect(TarArchive::names($path.'/'.Manifest::FILES))->toBe(['files/2026/11/a' => 5])
        ->and(TarArchive::extract($path.'/'.Manifest::FILES, $into))->toBe(1)
        ->and(file_get_contents($into.'/files/2026/11/a'))->toBe('bukti')
        ->and(fileperms($into.'/files/2026/11/a') & 0777)->toBe(0600)
        ->and(TarArchive::safe('files/../etc/passwd'))->toBeFalse()
        ->and(TarArchive::safe('/etc/passwd'))->toBeFalse()
        ->and(fn () => TarArchive::write($this->root.'/hostile.tar', [['name' => '../escape', 'path' => $into.'/files/2026/11/a']]))->toThrow(RuntimeException::class);

    // A header naming a path outside the directory, written by hand, is refused before anything is written.
    $header = pack('a100a8a8a8a12a12a8a1a100a6a2a32a32a8a8a155a12', '../escape', '0000600', '0000000', '0000000', sprintf('%011o', 1), sprintf('%011o', 0), str_repeat(' ', 8), '0', '', "ustar\0", '00', '', '', '', '', '', '');
    $header = substr_replace($header, sprintf('%06o', array_sum(array_map('ord', str_split($header))))."\0 ", 148, 8);
    file_put_contents($this->root.'/hostile.tar', $header.str_pad('x', 512, "\0").str_repeat("\0", 1024));

    expect(fn () => TarArchive::extract($this->root.'/hostile.tar', $into))->toThrow(RuntimeException::class, 'safe name')
        ->and(file_exists($this->root.'/escape'))->toBeFalse();
});

it('opens a copy only with its secret key and refuses a truncated or altered one (BKR-09; 13b)', function () {
    $pair = SealedStream::keyPair();
    $other = SealedStream::keyPair();
    $plain = $this->root.'/plain';
    file_put_contents($plain, random_bytes(1048576 * 2 + 17));
    $hash = SealedStream::encrypt($plain, $this->root.'/sealed', $pair['public']);

    expect($hash)->toBe(hash_file('sha256', $this->root.'/sealed'))
        ->and(str_contains((string) file_get_contents($this->root.'/sealed'), substr((string) file_get_contents($plain), 0, 64)))->toBeFalse();

    SealedStream::decrypt($this->root.'/sealed', $this->root.'/opened', $pair['secret']);
    expect(hash_file('sha256', $this->root.'/opened'))->toBe(hash_file('sha256', $plain))
        ->and(fn () => SealedStream::decrypt($this->root.'/sealed', $this->root.'/wrong', $other['secret']))->toThrow(RuntimeException::class, 'another key');

    $bytes = (string) file_get_contents($this->root.'/sealed');
    file_put_contents($this->root.'/truncated', substr($bytes, 0, -100));
    $altered = $bytes;
    $altered[200] = chr(ord($altered[200]) ^ 1);
    file_put_contents($this->root.'/altered', $altered);

    expect(fn () => SealedStream::decrypt($this->root.'/truncated', $this->root.'/t', $pair['secret']))->toThrow(RuntimeException::class)
        ->and(fn () => SealedStream::decrypt($this->root.'/altered', $this->root.'/a', $pair['secret']))->toThrow(RuntimeException::class, 'altered');

    // An empty file round-trips too.
    file_put_contents($this->root.'/empty', '');
    SealedStream::encrypt($this->root.'/empty', $this->root.'/empty.sealed', $pair['public']);
    SealedStream::decrypt($this->root.'/empty.sealed', $this->root.'/empty.opened', $pair['secret']);
    expect(file_get_contents($this->root.'/empty.opened'))->toBe('');
});

<?php

namespace App\Support\Backup;

use RuntimeException;

/**
 * The files' part of a generation (BKR-01): a POSIX ustar archive, readable by any `tar`, written and read here so
 * that extraction accepts only regular files under relative names of the storage keys' alphabet — never an absolute
 * path, a `..` segment or a link — into a new directory.
 */
final class TarArchive
{
    public const SAFE_NAME = '#^[A-Za-z0-9_-]+(/[A-Za-z0-9._-]+)*$#';

    private const BLOCK = 512;

    /**
     * Writes the archive of the given files.
     *
     * @param  iterable<array{name: string, path: string}>  $entries
     */
    public static function write(string $archive, iterable $entries): void
    {
        $out = fopen($archive, 'xb');

        if ($out === false) {
            throw new RuntimeException('The files\' archive cannot be created.');
        }

        try {
            foreach ($entries as $entry) {
                self::entry($out, $entry['name'], $entry['path']);
            }

            fwrite($out, str_repeat("\0", self::BLOCK * 2));
        } finally {
            fclose($out);
        }
    }

    /**
     * The names and sizes the archive holds, refusing any entry extraction would not accept.
     *
     * @return array<string, int>
     */
    public static function names(string $archive): array
    {
        $names = [];
        self::each($archive, function (string $name, int $size) use (&$names): void {
            $names[$name] = $size;
        });

        return $names;
    }

    /** Extracts every entry into a directory, which must be empty; each file readable by the operator account alone. */
    public static function extract(string $archive, string $directory): int
    {
        $count = 0;
        self::each($archive, function (string $name, int $size, $in) use ($directory, &$count): void {
            $target = $directory.'/'.$name;
            $parent = dirname($target);

            if (! is_dir($parent) && ! mkdir($parent, 0700, true) && ! is_dir($parent)) {
                throw new RuntimeException("The directory of {$name} cannot be created.");
            }

            $out = fopen($target, 'xb');

            if ($out === false) {
                throw new RuntimeException("{$name} cannot be written: it exists or its directory refuses it.");
            }

            try {
                if ($size > 0 && stream_copy_to_stream($in, $out, $size) !== $size) {
                    throw new RuntimeException("{$name} is truncated in the archive.");
                }
            } finally {
                fclose($out);
            }

            chmod($target, 0600);
            $count++;
        });

        return $count;
    }

    /** A relative name of the storage keys' alphabet, without a `.` or `..` segment. */
    public static function safe(string $name): bool
    {
        return preg_match(self::SAFE_NAME, $name) === 1 && array_intersect(explode('/', $name), ['.', '..']) === [];
    }

    /** @param  resource  $out */
    private static function entry($out, string $name, string $path): void
    {
        if (! self::safe($name) || strlen($name) > 99) {
            throw new RuntimeException("{$name} is not a name the archive takes.");
        }

        $size = filesize($path);
        $in = fopen($path, 'rb');

        if ($size === false || $in === false) {
            throw new RuntimeException("{$name} cannot be read.");
        }

        try {
            $header = pack('a100a8a8a8a12a12a8a1a100a6a2a32a32a8a8a155a12',
                $name, '0000600', '0000000', '0000000', sprintf('%011o', $size), sprintf('%011o', 0), str_repeat(' ', 8), '0', '',
                "ustar\0", '00', '', '', '', '', '', '');
            $checksum = array_sum(array_map('ord', str_split($header)));
            $header = substr_replace($header, sprintf('%06o', $checksum)."\0 ", 148, 8);
            fwrite($out, $header);

            if ($size > 0 && stream_copy_to_stream($in, $out) !== $size) {
                throw new RuntimeException("{$name} changed while it was archived.");
            }

            $padding = (self::BLOCK - $size % self::BLOCK) % self::BLOCK;
            fwrite($out, str_repeat("\0", $padding));
        } finally {
            fclose($in);
        }
    }

    /** @param  callable(string, int, resource): void  $visit */
    private static function each(string $archive, callable $visit): void
    {
        $in = fopen($archive, 'rb');

        if ($in === false) {
            throw new RuntimeException('The files\' archive cannot be read.');
        }

        try {
            while (true) {
                $header = fread($in, self::BLOCK);

                if ($header === false || strlen($header) < self::BLOCK) {
                    throw new RuntimeException('The files\' archive ends without its end marker.');
                }

                if ($header === str_repeat("\0", self::BLOCK)) {
                    return;
                }

                $stored = octdec(trim(substr($header, 148, 8), " \0"));
                $checksum = array_sum(array_map('ord', str_split(substr_replace($header, str_repeat(' ', 8), 148, 8))));
                $name = rtrim(substr($header, 0, 100), "\0");
                $type = $header[156];
                $size = (int) octdec(trim(substr($header, 124, 12), " \0"));

                if ($stored !== $checksum || substr($header, 257, 5) !== 'ustar' || rtrim(substr($header, 345, 155), "\0") !== '') {
                    throw new RuntimeException('An entry of the files\' archive has a damaged header.');
                }

                if (($type !== '0' && $type !== "\0") || ! self::safe($name)) {
                    throw new RuntimeException("The archive's entry {$name} is not a regular file under a safe name.");
                }

                $start = ftell($in);
                $visit($name, $size, $in);
                fseek($in, (int) $start + $size + (self::BLOCK - $size % self::BLOCK) % self::BLOCK);
            }
        } finally {
            fclose($in);
        }
    }
}

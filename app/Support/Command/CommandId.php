<?php

namespace App\Support\Command;

use Illuminate\Support\Str;
use InvalidArgumentException;
use Ramsey\Uuid\Uuid;

/**
 * A command's identity (CONCURRENCY_IDEMPOTENCY CI-01): a client's is a UUID of version 4 or 7 in canonical
 * lower-case form, generated for one user intent; a driven command's — a job's, the import pipeline's — a
 * deterministic version 5 over a namespace per source and a stable name, never accepted from a request, so no
 * client can occupy a driven command's identity. The nil and max values and malformed text are refused.
 */
final class CommandId
{
    /** The namespace of the deterministic identities, under which each source's names are hashed. */
    private const NAMESPACE = '6f1c8a7e-2b5d-5c43-9e7a-4d1b0c2f8a91';

    private const CANONICAL = '/^[0-9a-f]{8}-[0-9a-f]{4}-([1-8])[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';

    /** A client's identity, validated: version 4 or 7 only. */
    public static function fromClient(string $value): string
    {
        if (preg_match(self::CANONICAL, $value, $match) !== 1 || ! in_array($match[1], ['4', '7'], true)) {
            throw new InvalidArgumentException('A command identity is a lower-case UUID of version 4 or 7 (CI-01).');
        }

        return $value;
    }

    /**
     * A driven command's identity: `import:{batch}:{row}:{identity}`, `job:{type}:{row}:{step}` or
     * `event:{source}:{identity}`.
     */
    public static function driven(string $source, string $name): string
    {
        if (! in_array($source, ['import', 'job', 'event'], true) || $name === '') {
            throw new InvalidArgumentException('A driven identity names its source — import, job or event — and a stable name (CI-01).');
        }

        return Uuid::uuid5(self::NAMESPACE, "{$source}:{$name}")->toString();
    }

    /** Whether an identity is a driven one (version 5), whose only recorded outcome is COMMITTED (CI-08). */
    public static function isDriven(string $value): bool
    {
        return Str::isUuid($value) && $value[14] === '5';
    }
}

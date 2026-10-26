<?php

namespace App\Modules\IAM\Recovery;

use LogicException;

/**
 * The operator's identity of SECURITY AU-14, recorded by every operator command of AU-14, AU-21 and AU-25 and by the
 * installation step (GAP-049; SM-24): the host account that runs the command and the name the operator states, never
 * a user row — `actor_user_id` stays NULL with authority class SYSTEM (LG-01; RG-11). The commands run only in a
 * console, by the human operator, never by an agent (SX-04); no route reaches them.
 */
final class OperatorIdentity
{
    private const MAXIMUM_NAME_LENGTH = 120;

    private function __construct(public readonly string $hostAccount, public readonly string $name) {}

    /** The identity of the operator running this console command, with the name the operator states. */
    public static function stated(?string $name): self
    {
        self::ensureConsole();
        $name = trim((string) $name);

        if ($name === '' || mb_strlen($name) > self::MAXIMUM_NAME_LENGTH || preg_match('/[\x00-\x1F\x7F]/', $name) === 1) {
            throw new RecoveryRefused('State the operator\'s name with --operator: one line of at most '.self::MAXIMUM_NAME_LENGTH.' characters (AU-14).');
        }

        return new self(self::hostAccount(), $name);
    }

    /** The commands refuse to run outside a console (TASK-020; SX-04). */
    public static function ensureConsole(): void
    {
        if (! app()->runningInConsole()) {
            throw new LogicException('The operator\'s commands run only in a console, never through a request (AU-14; SX-04).');
        }
    }

    /** @return array{host_account: string, name: string} */
    public function toArray(): array
    {
        return ['host_account' => $this->hostAccount, 'name' => $this->name];
    }

    public function describe(): string
    {
        return "{$this->name} ({$this->hostAccount})";
    }

    private static function hostAccount(): string
    {
        if (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
            $entry = posix_getpwuid(posix_geteuid());

            if (is_array($entry) && $entry['name'] !== '') {
                return $entry['name'];
            }
        }

        foreach (['USER', 'USERNAME'] as $variable) {
            $value = getenv($variable);

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        $owner = get_current_user();

        if ($owner !== '') {
            return $owner;
        }

        // A process user with no account name, such as a container's, is recorded by its numeric id.
        return function_exists('posix_geteuid') ? 'uid '.posix_geteuid() : 'unknown';
    }
}

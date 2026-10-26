<?php

namespace Tests\Support;

use Closure;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Hashing\HashManager;
use Illuminate\Support\Facades\Hash;

/**
 * The framework's hasher with a test's hooks on `make` and `check` (TASK-017): every other call — the model's
 * hashed cast, `needsRehash`, `info` — reaches the real hasher, so a test observes or interleaves the hashing work
 * of a step without replacing it. Swapped in for the `Hash` facade by `HashProbe::install`.
 */
final class HashProbe implements Hasher
{
    /**
     * @param  (Closure(string, array<string, mixed>, HashManager): string)|null  $make
     * @param  (Closure(string, ?string, HashManager): bool)|null  $check
     */
    private function __construct(private readonly HashManager $hasher, private readonly ?Closure $make, private readonly ?Closure $check) {}

    /**
     * @param  (Closure(string, array<string, mixed>, HashManager): string)|null  $make
     * @param  (Closure(string, ?string, HashManager): bool)|null  $check
     */
    public static function install(?Closure $make = null, ?Closure $check = null): void
    {
        $hasher = app('hash');
        assert($hasher instanceof HashManager);
        Hash::swap(new self($hasher, $make, $check));
    }

    /** @param  array<string, mixed>  $options */
    public function make($value, array $options = []): string
    {
        return $this->make === null ? $this->hasher->make($value, $options) : ($this->make)($value, $options, $this->hasher);
    }

    /** @param  array<string, mixed>  $options */
    public function check($value, $hashedValue, array $options = []): bool
    {
        return $this->check === null ? $this->hasher->check($value, $hashedValue, $options) : ($this->check)($value, $hashedValue, $this->hasher);
    }

    /** @return array<string, mixed> */
    public function info($hashedValue): array
    {
        return $this->hasher->info($hashedValue);
    }

    /** @param  array<string, mixed>  $options */
    public function needsRehash($hashedValue, array $options = []): bool
    {
        return $this->hasher->needsRehash($hashedValue, $options);
    }

    /** @param  array<int, mixed>  $arguments */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->hasher->{$method}(...$arguments);
    }
}

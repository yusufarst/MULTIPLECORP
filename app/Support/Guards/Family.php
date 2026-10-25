<?php

namespace App\Support\Guards;

/** A guard family given by its table, key, covered columns and formula. */
final class Family implements GuardFamily
{
    /**
     * @param  list<string>  $key
     * @param  list<string>  $columns
     * @param  list<string>|null  $carried
     */
    public function __construct(
        private readonly string $table,
        private readonly array $key,
        private readonly array $columns,
        private readonly string $formula,
        private readonly ?array $carried = [],
    ) {}

    public function table(): string
    {
        return $this->table;
    }

    public function key(): array
    {
        return $this->key;
    }

    public function columns(): array
    {
        return $this->columns;
    }

    public function formula(): string
    {
        return $this->formula;
    }

    public function carried(): ?array
    {
        return $this->carried;
    }
}

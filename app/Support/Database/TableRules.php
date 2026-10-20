<?php

namespace App\Support\Database;

use Illuminate\Support\Facades\DB;

/**
 * What a migration adds to a table after the Blueprint has created it (TASK-005): the CHECKs, keys and
 * indexes of DATABASE §18–§20 and PERFORMANCE §12, each named after its catalogue row where it has one
 * (`users_c62_…`, `audit_events_ix13_…`), and the runtime's privileges of DATABASE §28. Names and
 * expressions are the migrations' own constants, never input.
 */
final class TableRules
{
    private function __construct(private readonly string $table) {}

    public static function for(string $table): self
    {
        return new self($table);
    }

    public function check(string $name, string $expression): self
    {
        DB::statement("alter table {$this->table} add constraint {$name} check ({$expression})");

        return $this;
    }

    /**
     * An enumeration of DATABASE §1: short uppercase codes stored as text with a CHECK list. On a nullable
     * column an empty value stays allowed, and says so.
     *
     * @param  list<string>  $codes
     */
    public function codes(string $name, string $column, array $codes, bool $nullable = false): self
    {
        $list = implode(', ', array_map(static fn (string $code): string => "'{$code}'", $codes));

        return $this->check($name, $nullable ? "{$column} is null or {$column} in ({$list})" : "{$column} in ({$list})");
    }

    /** The all-or-none CHECK of Columns::commandIdentity() (§1). */
    public function commandIdentity(): self
    {
        return $this->check("{$this->table}_c48_command_identity_pair", 'num_nonnulls(command_id, command_line_no) in (0, 2)');
    }

    /**
     * A UNIQUE constraint, which a composite foreign key can reference (§1, §15).
     *
     * @param  list<string>  $columns
     */
    public function unique(string $name, array $columns): self
    {
        DB::statement("alter table {$this->table} add constraint {$name} unique (".implode(', ', $columns).')');

        return $this;
    }

    /** A unique index over columns or expressions, partial when $where is given (§18). */
    public function uniqueIndex(string $name, string $keys, ?string $where = null): self
    {
        DB::statement("create unique index {$name} on {$this->table} ({$keys})".($where === null ? '' : " where {$where}"));

        return $this;
    }

    /**
     * An index, partial when $where is given; its predicate is a literal list, so a query that writes the
     * same literal can use it (PERFORMANCE PF-10).
     */
    public function index(string $name, string $keys, ?string $where = null): self
    {
        DB::statement("create index {$name} on {$this->table} ({$keys})".($where === null ? '' : " where {$where}"));

        return $this;
    }

    /** Table privileges for a role of DATABASE §28; nothing is granted that is not named. */
    public function grant(string $privileges, string $role = DatabaseRole::RUNTIME): self
    {
        DB::statement("grant {$privileges} on table {$this->table} to {$role}");

        return $this;
    }

    /**
     * UPDATE of the named columns only: the lifecycle, draft, unlink or guard columns §28 lets the role
     * change, every other column staying as it was inserted.
     *
     * @param  list<string>  $columns
     */
    public function grantUpdate(array $columns, string $role = DatabaseRole::RUNTIME): self
    {
        DB::statement('grant update ('.implode(', ', $columns).") on table {$this->table} to {$role}");

        return $this;
    }
}

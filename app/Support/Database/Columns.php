<?php

namespace App\Support\Database;

use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;

/**
 * The column conventions every migration shares (TASK-005): DATABASE §1's routine columns and command
 * identity, §16's identifiers and §17's exact types. What a Blueprint cannot express — CHECKs, partial
 * and expression indexes, privileges — is TableRules'.
 */
final class Columns
{
    /** `id bigint GENERATED ALWAYS AS IDENTITY`: the internal key and the only foreign-key target (§16). */
    public static function id(Blueprint $table): ColumnDefinition
    {
        return $table->id()->generatedAs()->always();
    }

    /**
     * `public_id uuid`, a UUIDv7, unique, on a routable aggregate root only (§16; D-DB-02). The model
     * generates it; PostgreSQL 18's uuidv7() fills it for any other writer.
     */
    public static function publicId(Blueprint $table): ColumnDefinition
    {
        return $table->uuid('public_id')->default(new Expression('uuidv7()'))->unique();
    }

    /**
     * An instant: `timestamptz` at PostgreSQL's own precision, the microsecond, stored in UTC and shown in
     * WIB (§17) — not the framework's default of whole seconds.
     */
    public static function timestamp(Blueprint $table, string $column): ColumnDefinition
    {
        return $table->addColumn('timestampTz', $column, ['precision' => null]);
    }

    /** `recorded_at timestamptz NOT NULL DEFAULT now()`, immutable (§1; BR-DT-01). */
    public static function recordedAt(Blueprint $table): ColumnDefinition
    {
        return self::timestamp($table, 'recorded_at')->useCurrent();
    }

    /** A plain foreign key to another table's `id`, restricting the delete as every key does (§19). */
    public static function reference(Blueprint $table, string $column, string $on): ColumnDefinition
    {
        $definition = $table->foreignId($column);
        $table->foreign($column)->references('id')->on($on)->restrictOnDelete();

        return $definition;
    }

    /** A reference to the user who did something; users are never deleted, so the key restricts (§19). */
    public static function user(Blueprint $table, string $column): ColumnDefinition
    {
        return self::reference($table, $column, 'users');
    }

    /**
     * A master's archive state: `is_active` false with `deactivated_at/by/reason`, instead of a delete once
     * referenced (§13 ARCHIVE / INACTIVE; §19).
     */
    public static function deactivation(Blueprint $table): void
    {
        $table->boolean('is_active')->default(true);
        self::timestamp($table, 'deactivated_at')->nullable();
        self::user($table, 'deactivated_by')->nullable();
        $table->text('deactivated_reason')->nullable();
    }

    /** `recorded_by`, the person who created the row, where one did (§1). */
    public static function recordedBy(Blueprint $table): ColumnDefinition
    {
        return self::user($table, 'recorded_by')->nullable();
    }

    /** `lock_version` for stale-edit detection and `updated_at`, on an editable row (§1; SF-CMD step 5). */
    public static function editable(Blueprint $table): void
    {
        $table->integer('lock_version')->default(0);
        self::timestamp($table, 'updated_at')->nullable();
    }

    /**
     * `command_id` and `command_line_no` of the user command that inserted the row, unique together, so a
     * replay cannot insert a second copy even after the command log expires (§1; C-48). Both are empty on
     * a row no command inserted; TableRules::commandIdentity() adds their all-or-none CHECK.
     */
    public static function commandIdentity(Blueprint $table, string $tableName): void
    {
        $table->uuid('command_id')->nullable();
        $table->smallInteger('command_line_no')->nullable();
        $table->unique(['command_id', 'command_line_no'], "{$tableName}_c48_command_identity");
    }

    /** Money: `numeric(18,2)` rupiah, exact (§17; D-DB-03). */
    public static function money(Blueprint $table, string $column): ColumnDefinition
    {
        return $table->decimal($column, 18, 2);
    }

    /** A base-unit quantity: `numeric(18,3)`, exact, limited by the product's scale (§17; D-DB-04). */
    public static function quantity(Blueprint $table, string $column): ColumnDefinition
    {
        return $table->decimal($column, 18, 3);
    }

    /** A business date: the WIB calendar day of a fact (§17; BR-DT-05), see BusinessDate. */
    public static function businessDate(Blueprint $table, string $column = 'business_date'): ColumnDefinition
    {
        return $table->date($column);
    }
}

<?php

use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// DATABASE §4.2 `number_sequences` and C-10 (NM-03): a seed states the first live number, so it is a
// positive number and the counter never stands below it — a seeded counter inserted below its seed, or a
// seed raised past the counter without raising it, would issue a number the seed reserves for legacy
// documents. One CHECK holds both at every insert and update; a counter without a seed is unchanged.
return new class extends Migration
{
    public function up(): void
    {
        TableRules::for('number_sequences')
            ->check('number_sequences_c10_seed_floor', 'seed_value is null or (seed_value >= 1 and next_value >= seed_value)');
    }

    public function down(): void
    {
        DB::statement('alter table number_sequences drop constraint number_sequences_c10_seed_floor');
    }
};

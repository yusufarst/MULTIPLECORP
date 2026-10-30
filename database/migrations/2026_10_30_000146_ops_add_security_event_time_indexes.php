<?php

use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// TASK-061 (PF-13; PF-42; PFM-03): PG-064 *Log Keamanan* reads the security events newest first in keyset order
// (`occurred_at`, `id`), every kind or one kind at a time, and DATABASE §20 registers no time key on the table — only
// IX-14's and IX-19's partial indexes. These two keys keep the list and its kind filter an index range scan whatever
// the log's size, as `audit_events` has its IX-13 keys. An executor discovery: expand-only, no row is touched.
return new class extends Migration
{
    public function up(): void
    {
        TableRules::for('security_events')
            ->index('security_events_time', 'occurred_at, id')
            ->index('security_events_kind_time', 'event_kind, occurred_at, id');
    }

    public function down(): void
    {
        DB::statement('drop index security_events_kind_time');
        DB::statement('drop index security_events_time');
    }
};

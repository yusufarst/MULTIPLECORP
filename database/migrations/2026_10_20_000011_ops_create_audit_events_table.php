<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.13 `audit_events` (AE): the business audit, written in the transaction of the change it
// records (§23; SECURITY LG-01) and never changed by an application role (C-53). The entity is an
// explicit type and id, never a morph link; `company_id` is the company context (§15).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $table) {
            Columns::id($table);
            Columns::timestamp($table, 'occurred_at')->useCurrent();
            // NULL for an operator's console command and the system (LG-01).
            Columns::user($table, 'actor_user_id')->nullable();
            $table->bigInteger('company_id')->nullable();
            $table->text('action');
            $table->text('entity_type')->nullable();
            $table->bigInteger('entity_id')->nullable();
            $table->uuid('entity_public_id')->nullable();
            $table->text('reason')->nullable();
            $table->text('correlation_id')->nullable();
            $table->uuid('command_id')->nullable();
            $table->text('authority_class');
            $table->jsonb('before')->nullable();
            $table->jsonb('after')->nullable();
            $table->jsonb('metadata')->nullable();
            Columns::recordedAt($table);
        });

        TableRules::for('audit_events')
            ->codes('audit_events_authority_class', 'authority_class', ['ADM', 'ADM_PLUS', 'OWNER_ONLY', 'SYSTEM'])
            // §20 and IX-13: company and entity history in keyset order, the actor's history, and the
            // Owner review queue of ADM+ and Owner-only events (QS-14). The partial indexes over the
            // acknowledged and the routed action codes wait for those codes' registration.
            ->index('audit_events_ix13_company', 'company_id, occurred_at, id')
            ->index('audit_events_ix13_entity', 'entity_type, entity_id, occurred_at, id')
            ->index('audit_events_actor', 'actor_user_id, occurred_at')
            ->index('audit_events_ix13_privileged', 'company_id, occurred_at, id', "authority_class in ('ADM_PLUS', 'OWNER_ONLY')")
            ->grant('select, insert');
    }

    public function down(): void
    {
        Schema::drop('audit_events');
    }
};

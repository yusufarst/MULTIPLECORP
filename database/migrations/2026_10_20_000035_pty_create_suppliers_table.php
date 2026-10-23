<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.3 `suppliers` (MD): the master identity only — price history is derived per company from
// purchases, never stored (BR-ACC-02); a purchase snapshots the supplier as used (§14). The address and
// contact are the columns `client_addresses` and `client_contacts` name. The name is searched by trigram
// (§21) and looked up by equality and prefix (IX-17).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            Columns::id($table);
            Columns::publicId($table);
            $table->text('name');
            $table->text('npwp')->nullable();
            $table->text('address_lines')->nullable();
            $table->text('city')->nullable();
            $table->text('phone')->nullable();
            $table->text('email')->nullable();
            Columns::deactivation($table);
            Columns::commandIdentity($table, 'suppliers');
            Columns::recordedAt($table);
            Columns::recordedBy($table);
            Columns::editable($table);
        });

        TableRules::for('suppliers')
            ->trigramIndex('suppliers_name_trigram', 'name')
            ->index('suppliers_ix17_name_prefix', 'lower(name) text_pattern_ops')
            ->commandIdentity()
            ->grant('select, insert')
            ->grantUpdate([
                'name', 'npwp', 'address_lines', 'city', 'phone', 'email',
                'is_active', 'deactivated_at', 'deactivated_by', 'deactivated_reason', 'lock_version', 'updated_at',
            ]);
    }

    public function down(): void
    {
        Schema::drop('suppliers');
    }
};

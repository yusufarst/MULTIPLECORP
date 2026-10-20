<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.2 `companies` (MD; CAP-01): never deleted, deactivated instead, collection continuing
// (BR-ACC-03; §19). Its legal identity changes never alter an issued snapshot (BR-SN-01): documents
// print their payload and reference the identity assets as immutable files (§14). The address and
// contact are the columns `client_addresses` and `client_contacts` name. The three asset files are keyed
// to the company's own files when `file_objects` exists (2026_10_20_000022).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            Columns::id($table);
            Columns::publicId($table);
            $table->text('code');
            $table->text('legal_name');
            $table->text('legal_form')->nullable();
            $table->text('npwp')->nullable();
            $table->text('address_lines')->nullable();
            $table->text('city')->nullable();
            $table->text('phone')->nullable();
            $table->text('email')->nullable();
            $table->text('director_name')->nullable();
            $table->bigInteger('logo_file_id')->nullable();
            $table->bigInteger('stamp_file_id')->nullable();
            $table->bigInteger('signature_file_id')->nullable();
            Columns::deactivation($table);
            Columns::commandIdentity($table, 'companies');
            Columns::recordedAt($table);
            Columns::recordedBy($table);
            Columns::editable($table);
        });

        TableRules::for('companies')
            ->unique('companies_code_unique', ['code'])
            ->commandIdentity()
            ->grant('select, insert')
            ->grantUpdate([
                'legal_name', 'legal_form', 'npwp', 'address_lines', 'city', 'phone', 'email', 'director_name',
                'logo_file_id', 'stamp_file_id', 'signature_file_id',
                'is_active', 'deactivated_at', 'deactivated_by', 'deactivated_reason',
                'lock_version', 'updated_at',
            ]);
    }

    public function down(): void
    {
        Schema::drop('companies');
    }
};

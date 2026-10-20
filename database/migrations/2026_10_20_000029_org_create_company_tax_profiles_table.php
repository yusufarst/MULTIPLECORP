<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.2 `company_tax_profiles` (CF): a company's configured registration status, such as its VAT
// registration, by period — the treatment of D-1 by company and period (§12); its ranges never overlap
// for one company (C-52), and an empty range is refused.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_tax_profiles', function (Blueprint $table) {
            Columns::id($table);
            Columns::reference($table, 'company_id', 'companies');
            $table->text('registration_status');
            $table->rawColumn('effective', 'daterange');
            Columns::commandIdentity($table, 'company_tax_profiles');
            Columns::recordedAt($table);
            Columns::recordedBy($table);
            Columns::editable($table);
        });

        TableRules::for('company_tax_profiles')
            ->unique('company_tax_profiles_c32_company_key', ['company_id', 'id'])
            ->check('company_tax_profiles_effective_not_empty', 'not isempty(effective)')
            ->exclude('company_tax_profiles_c52_no_overlap', 'company_id with =, effective with &&')
            ->commandIdentity()
            ->grant('select, insert')
            ->grantUpdate(['registration_status', 'effective', 'lock_version', 'updated_at']);
    }

    public function down(): void
    {
        Schema::drop('company_tax_profiles');
    }
};

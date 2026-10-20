<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.10 `evidence_balances` (GB) and §3.1: the guard of an evidence document or a bank statement
// line, posted only by EvidencePosting in the transactions of the facts that cite it, and rebuildable.
// What is cited never exceeds what is proven — settlements per evidence (L-45; C-18), Cash-In per bank
// line, whose proof falls to 0 once VOIDED (L-22; C-19), charges and expenses per source bill (L-43;
// C-20) — and what is expensed never exceeds it either, so a fee is expensed at most once (C-23).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evidence_balances', function (Blueprint $table) {
            Columns::id($table);
            Columns::reference($table, 'evidence_document_id', 'evidence_documents')->nullable();
            Columns::reference($table, 'bank_statement_line_id', 'bank_statement_lines')->nullable();
            Columns::money($table, 'proven_amount')->default(0);
            Columns::money($table, 'cited_amount')->default(0);
            Columns::money($table, 'expensed_amount')->default(0);
            Columns::recordedAt($table);
        });

        TableRules::for('evidence_balances')
            ->unique('evidence_balances_evidence_unique', ['evidence_document_id'])
            ->unique('evidence_balances_bank_line_unique', ['bank_statement_line_id'])
            ->check('evidence_balances_one_subject', 'num_nonnulls(evidence_document_id, bank_statement_line_id) = 1')
            ->check('evidence_balances_c18_cited_within_proven', 'cited_amount >= 0 and cited_amount <= proven_amount')
            ->check('evidence_balances_c23_expensed_within_proven', 'expensed_amount >= 0 and expensed_amount <= proven_amount')
            // IX-14: the bank lines not fully cited (QS-12).
            ->index('evidence_balances_ix14_bank_line_open', 'bank_statement_line_id', 'bank_statement_line_id is not null and cited_amount < proven_amount')
            ->grant('select, insert')
            ->grantUpdate(['proven_amount', 'cited_amount', 'expensed_amount']);
    }

    public function down(): void
    {
        Schema::drop('evidence_balances');
    }
};

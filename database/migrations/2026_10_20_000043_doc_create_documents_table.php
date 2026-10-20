<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.10 `documents` (SR) and §10: one business record, many layouts — a layout never creates a
// second transaction (BR-DOC-04; BR-XD-04). Each document is one chain of versions on one record-bound
// subject, an exclusive arc whose column the type decides; the per-type partial UNIQUE indexes give each
// subject one chain per type (C-11), the subject columns never entering a key while empty. DOC-05 alone
// has two modes: RECEIPT on a payment and its invoice, PRE_PAYMENT on the invoice (AX-31); DOC-07 follows
// its parent Kuitansi. The subjects are the records of TASK-007–TASK-009, whose keys to this table no
// contract assigns yet (TASK-006's discovery (1); §15; C-32); the parent document is keyed here.
return new class extends Migration
{
    private const SUBJECTS = [
        'project_id', 'purchase_id', 'dispatch_movement_id', 'dropship_confirmation_id', 'invoice_id',
        'payment_id', 'parent_document_id', 'delivery_id', 'service_handover_id', 'receiving_movement_id',
    ];

    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            Columns::id($table);
            Columns::publicId($table);
            Columns::reference($table, 'company_id', 'companies');
            $table->text('document_type_code');
            $table->text('mode')->default('STANDARD');

            foreach (self::SUBJECTS as $subject) {
                $table->bigInteger($subject)->nullable();
            }

            Columns::commandIdentity($table, 'documents');
            Columns::recordedAt($table);
            Columns::recordedBy($table);
            $table->foreign('document_type_code')->references('code')->on('document_types')->restrictOnDelete();
        });

        $subjects = implode(', ', self::SUBJECTS);

        TableRules::for('documents')
            ->unique('documents_c32_company_key', ['company_id', 'id'])
            // The key a version carries its company and denormalized type in (§4.10).
            ->unique('documents_type_key', ['company_id', 'id', 'document_type_code'])
            ->codes('documents_mode_code', 'mode', ['STANDARD', 'RECEIPT', 'PRE_PAYMENT'])
            ->check('documents_mode_by_type', "(document_type_code = 'DOC-05') = (mode in ('RECEIPT', 'PRE_PAYMENT'))")
            ->check('documents_subject_by_type', <<<SQL
                case
                    when document_type_code in ('DOC-01', 'DOC-09', 'DOC-11') then project_id is not null and num_nonnulls({$subjects}) = 1
                    when document_type_code in ('DOC-02', 'DOC-14') then purchase_id is not null and num_nonnulls({$subjects}) = 1
                    when document_type_code = 'DOC-03' then num_nonnulls(dispatch_movement_id, dropship_confirmation_id) = 1 and num_nonnulls({$subjects}) = 1
                    when document_type_code in ('DOC-04', 'DOC-06', 'DOC-13') then invoice_id is not null and num_nonnulls({$subjects}) = 1
                    when document_type_code = 'DOC-05' and mode = 'PRE_PAYMENT' then invoice_id is not null and num_nonnulls({$subjects}) = 1
                    when document_type_code = 'DOC-05' and mode = 'RECEIPT' then payment_id is not null and invoice_id is not null and num_nonnulls({$subjects}) = 2
                    when document_type_code = 'DOC-07' then parent_document_id is not null and num_nonnulls({$subjects}) = 1
                    when document_type_code = 'DOC-08' then num_nonnulls(delivery_id, service_handover_id) = 1 and num_nonnulls({$subjects}) = 1
                    when document_type_code = 'DOC-10' then delivery_id is not null and num_nonnulls({$subjects}) = 1
                    when document_type_code = 'DOC-12' then receiving_movement_id is not null and num_nonnulls({$subjects}) = 1
                    else false
                end
                SQL)
            ->foreign('documents_c32_parent_foreign', ['company_id', 'parent_document_id'], 'documents', ['company_id', 'id'])
            ->uniqueIndex('documents_c11_quotation_chain', 'project_id', "document_type_code = 'DOC-01'")
            ->uniqueIndex('documents_c11_purchase_chain', 'purchase_id, document_type_code', "document_type_code in ('DOC-02', 'DOC-14')")
            ->uniqueIndex('documents_c11_dispatch_chain', 'dispatch_movement_id', "document_type_code = 'DOC-03'")
            ->uniqueIndex('documents_c11_dropship_chain', 'dropship_confirmation_id', "document_type_code = 'DOC-03'")
            ->uniqueIndex('documents_c11_invoice_chain', 'invoice_id, document_type_code', "document_type_code in ('DOC-04', 'DOC-06', 'DOC-13')")
            ->uniqueIndex('documents_c11_receipt_chain', 'payment_id, invoice_id', "document_type_code = 'DOC-05' and mode = 'RECEIPT'")
            ->uniqueIndex('documents_c11_attachment_chain', 'parent_document_id', "document_type_code = 'DOC-07'")
            ->uniqueIndex('documents_c11_delivery_chain', 'delivery_id, document_type_code', "document_type_code in ('DOC-08', 'DOC-10')")
            ->uniqueIndex('documents_c11_handover_chain', 'service_handover_id', "document_type_code = 'DOC-08'")
            ->uniqueIndex('documents_c11_receiving_chain', 'receiving_movement_id', "document_type_code = 'DOC-12'")
            // A project's documents (§20).
            ->index('documents_project_type', 'project_id, document_type_code')
            // IX-14: the pre-payment Kuitansi chains of QS-19.
            ->index('documents_ix14_prepayment_kuitansi', 'company_id', "document_type_code = 'DOC-05' and mode = 'PRE_PAYMENT'")
            ->commandIdentity()
            ->grant('select, insert');
    }

    public function down(): void
    {
        Schema::drop('documents');
    }
};

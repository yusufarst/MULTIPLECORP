<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// DIR-089 (3) (TASK-029): the fourteen rows of `document_types` from the approved V1 text only. Each `name` is
// V1_SCOPE's selectable-document catalogue's; every type is numbered, each issue drawing its type's next number
// (WORKFLOWS WF-DOC-01); `allowed_modes` RECEIPT and PRE_PAYMENT for DOC-05, STANDARD for the others (DATABASE §4.10;
// DIR-024 D-4); `subject_kind` names the subject columns `documents_subject_by_type` requires of the type, in its own
// words — `|` between alternatives, `+` for columns required together —, so the catalogue and the arc cannot disagree
// (an integration test proves it). Expand-only: rows inserted, none changed; the down step removes only these rows.
return new class extends Migration
{
    /** @var array<string, array{0: string, 1: string, 2: list<string>}> */
    private const TYPES = [
        'DOC-01' => ['Penawaran', 'project_id', ['STANDARD']],
        'DOC-02' => ['Purchase Order / PO', 'purchase_id', ['STANDARD']],
        'DOC-03' => ['Surat Jalan / Faktur Pengiriman', 'dispatch_movement_id|dropship_confirmation_id', ['STANDARD']],
        'DOC-04' => ['Nota', 'invoice_id', ['STANDARD']],
        'DOC-05' => ['Kuitansi', 'payment_id+invoice_id|invoice_id', ['RECEIPT', 'PRE_PAYMENT']],
        'DOC-06' => ['Invoice', 'invoice_id', ['STANDARD']],
        'DOC-07' => ['Lampiran Kuitansi', 'parent_document_id', ['STANDARD']],
        'DOC-08' => ['BAST', 'delivery_id|service_handover_id', ['STANDARD']],
        'DOC-09' => ['SPK', 'project_id', ['STANDARD']],
        'DOC-10' => ['Berita Acara Pemeriksaan Barang', 'delivery_id', ['STANDARD']],
        'DOC-11' => ['HPS', 'project_id', ['STANDARD']],
        'DOC-12' => ['Nota Terima Barang', 'receiving_movement_id', ['STANDARD']],
        'DOC-13' => ['Surat Permintaan Pembayaran', 'invoice_id', ['STANDARD']],
        'DOC-14' => ['Surat Pesanan', 'purchase_id', ['STANDARD']],
    ];

    public function up(): void
    {
        foreach (self::TYPES as $code => [$name, $subject, $modes]) {
            DB::statement('insert into document_types (code, name, subject_kind, is_numbered, allowed_modes) values (?, ?, ?, true, ?::text[])',
                [$code, $name, $subject, '{'.implode(',', $modes).'}']);
        }
    }

    public function down(): void
    {
        DB::table('document_types')->whereIn('code', array_keys(self::TYPES))->delete();
    }
};

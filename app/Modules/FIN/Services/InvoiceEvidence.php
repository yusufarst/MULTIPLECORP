<?php

namespace App\Modules\FIN\Services;

use App\Modules\FIN\Models\Invoice;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * DIR-092 (2): an entry error stands on evidence already linked to the invoice's records — its project, or a confirmation of it, as `evidence_links` holds no
 * invoice target. The check reads and links nothing. CM-24's revision and void and CM-25's billing correction alike.
 */
final class InvoiceEvidence
{
    public static function linked(Invoice $invoice, ?int $evidenceId): bool
    {
        return $evidenceId !== null && DB::table('evidence_links')->where('evidence_document_id', $evidenceId)->whereNull('unlinked_at')
            ->where(fn (Builder $link) => $link->where('project_id', $invoice->project_id)->orWhereIn('commercial_confirmation_id', DB::table('commercial_confirmations')->where('project_id', $invoice->project_id)->select('id')))->exists();
    }
}

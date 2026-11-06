<?php

namespace App\Modules\Reporting\Signals;

use App\Support\Search\Row;
use Illuminate\Database\Query\Builder;

/**
 * QS-11 (WORKFLOWS §10; IA §9.1): disputed receivables — outstanding under an open dispute hold, C-42's partial
 * UNIQUE key —, the oldest hold first; the invoice on PG-043, "Lihat Semua" → PG-044's *Sengketa*.
 */
final class QS11Disputed extends Signal
{
    public function code(): string
    {
        return 'QS-11';
    }

    public function companies(Context $context): array
    {
        return $context->holding('finance.view');
    }

    protected function branch(Context $context, int $company): Builder
    {
        return Receivables::select(Receivables::outstanding($company)->whereNotNull('b.current_billing_act_id')->whereRaw(Receivables::HELD)->orderBy('b.invoice_id'),
            '(select h.set_at from receivable_dispute_holds h where h.invoice_id = i.id and h.cleared_at is null)');
    }

    protected function href(Context $context, object $row): string
    {
        return '/keuangan/invoice/'.Row::text($row, 'target');
    }

    public function seeAll(Context $context): string
    {
        return '/keuangan/piutang?tab=sengketa';
    }
}

<?php

namespace App\Modules\Reporting\Signals;

use App\Support\Search\Row;
use Illuminate\Database\Query\Builder;

/**
 * QS-09 (WORKFLOWS §10; IA §9.1): issued invoices *Belum Ditagihkan* — outstanding with no current billing act —,
 * oldest first; the invoice on PG-043 where "Tagihkan" runs, "Lihat Semua" → PG-044's *Belum Ditagihkan*.
 */
final class QS09NotYetBilled extends Signal
{
    public function code(): string
    {
        return 'QS-09';
    }

    public function companies(Context $context): array
    {
        return $context->holding('finance.view');
    }

    protected function branch(Context $context, int $company): Builder
    {
        return Receivables::select(Receivables::outstanding($company)->whereNull('b.current_billing_act_id')->orderBy('b.invoice_id'), 'i.recorded_at');
    }

    protected function href(Context $context, object $row): string
    {
        return '/keuangan/invoice/'.Row::text($row, 'target');
    }

    public function seeAll(Context $context): string
    {
        return '/keuangan/piutang?tab=belum';
    }
}

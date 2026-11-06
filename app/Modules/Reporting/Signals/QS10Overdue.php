<?php

namespace App\Modules\Reporting\Signals;

use App\Support\Search\Row;
use Illuminate\Database\Query\Builder;

/**
 * QS-10 (WORKFLOWS §10; IA §9.1): overdue receivables by ageing bucket — outstanding under a billing act whose due
 * date is before today at the WIB day boundary (BR-FIN-15; BR-DT-06), a disputed one left to QS-11 —, the oldest due
 * date first, aged from it; the invoice on PG-043, "Lihat Semua" → PG-044.
 */
final class QS10Overdue extends Signal
{
    public function code(): string
    {
        return 'QS-10';
    }

    public function companies(Context $context): array
    {
        return $context->holding('finance.view');
    }

    protected function branch(Context $context, int $company): Builder
    {
        return Receivables::select(Receivables::outstanding($company)->whereNotNull('b.current_billing_act_id')
            ->where('b.current_due_date', '<', $context->today)->whereRaw('not '.Receivables::HELD)->orderBy('b.current_due_date')->orderBy('b.invoice_id'), 'b.current_due_date');
    }

    protected function href(Context $context, object $row): string
    {
        return '/keuangan/invoice/'.Row::text($row, 'target');
    }

    public function seeAll(Context $context): string
    {
        return '/keuangan/piutang?tab=aktif';
    }
}

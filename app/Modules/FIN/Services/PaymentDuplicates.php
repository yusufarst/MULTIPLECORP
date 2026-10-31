<?php

namespace App\Modules\FIN\Services;

use App\Support\Search\Row;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * BD-02's likely duplicate (L-22; WF-FIN-03; UXS-16): the same company bank account and amount within seven days either side of the date, whatever the reference — read
 * under LK-07, so two concurrent submissions cannot both pass. A fact wholly contra'd is no real receipt any more; one a re-recording names but that still stands warns like any other.
 */
final class PaymentDuplicates
{
    public const WINDOW_DAYS = 7;

    /** @return list<array{id: string, date: string, amount: string, reference: ?string, client: string}> */
    public static function matches(int $accountId, string $amount, string $businessDate): array
    {
        $day = CarbonImmutable::parse($businessDate);
        $found = [];

        foreach (DB::table('payments as p')->join('application_source_balances as b', 'b.payment_id', '=', 'p.id')->join('client_organizations as o', 'o.id', '=', 'p.client_organization_id')
            ->where('p.company_bank_account_id', $accountId)->where('p.amount', $amount)
            ->whereBetween('p.business_date', [$day->subDays(self::WINDOW_DAYS)->toDateString(), $day->addDays(self::WINDOW_DAYS)->toDateString()])
            ->where('b.net_amount', '>', 0)
            ->orderBy('p.id')->limit(20)->get(['p.public_id', 'p.business_date', 'p.amount', 'p.reference', 'o.name']) as $row) {
            $found[] = ['id' => Row::text($row, 'public_id'), 'date' => Row::text($row, 'business_date'), 'amount' => Row::text($row, 'amount'), 'reference' => Row::nullable($row, 'reference'), 'client' => Row::text($row, 'name')];
        }

        return $found;
    }
}

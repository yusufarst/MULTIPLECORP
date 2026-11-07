<?php

namespace App\Modules\Reporting\Queries;

use App\Modules\Reporting\Signals\Context;
use App\Modules\Reporting\Signals\QS01UnreservedDemand;
use App\Modules\Reporting\Signals\QS02Shortage;
use App\Modules\Reporting\Signals\QS03ReceivedAwaitingReservation;
use App\Modules\Reporting\Signals\QS04ReservationsCut;
use App\Modules\Reporting\Signals\QS05OpenPurchaseRemainders;
use App\Modules\Reporting\Signals\QS06NotDelivered;
use App\Modules\Reporting\Signals\QS07OpenCases;
use App\Modules\Reporting\Signals\QS08RenditionsOwed;
use App\Modules\Reporting\Signals\QS09NotYetBilled;
use App\Modules\Reporting\Signals\QS10Overdue;
use App\Modules\Reporting\Signals\QS11Disputed;
use App\Modules\Reporting\Signals\QS12CreditAndProof;
use App\Modules\Reporting\Signals\QS13DeductionsUnevidenced;
use App\Modules\Reporting\Signals\QS15CompletionBlockers;
use App\Modules\Reporting\Signals\QS16ResidualObligations;
use App\Modules\Reporting\Signals\QS17LowStock;
use App\Modules\Reporting\Signals\QS18CountFindings;
use App\Modules\Reporting\Signals\QS19PrepaymentKuitansi;
use App\Modules\Reporting\Signals\QS20RoutedRefusals;
use App\Modules\Reporting\Signals\QS21AcknowledgedDuplicates;
use App\Modules\Reporting\Signals\QS24DispatchReady;

/**
 * `Reporting ShowWorkQueue` — PG-007 *Pekerjaan*, every active account's work (ROUTE-007; NAV-007; SCR-06; D-IA-08;
 * `DIR-081` OB-03; IA §9): its frame in the first response, then four deferred groups by what has to happen next —
 * *Barang: terima, keluarkan, kirim* (QS-01–QS-06, QS-16, QS-17, QS-18's findings and stale counts, QS-20 and QS-24),
 * *Dokumen dan kasus* (QS-07, QS-08, QS-21), *Tagihan dan pembayaran* (QS-09–QS-13, QS-19) only with `finance.view`,
 * *Penyelesaian proyek* (QS-15, its money items only with `finance.view`) —, each group and signal present only with
 * its read class (PJ-13). The view filters the companies of the scope; counts stay per company, never summed (CS-08;
 * PF-15). An account without a company grant sees MSG-38 and nothing above S0 (RG-04; J-ACC-01).
 */
final class WorkQueue
{
    public const GOODS = [QS01UnreservedDemand::class, QS02Shortage::class, QS03ReceivedAwaitingReservation::class, QS04ReservationsCut::class,
        QS05OpenPurchaseRemainders::class, QS06NotDelivered::class, QS24DispatchReady::class, QS16ResidualObligations::class, QS17LowStock::class,
        QS18CountFindings::class, QS20RoutedRefusals::class];

    public const DOCUMENTS = [QS07OpenCases::class, QS08RenditionsOwed::class, QS21AcknowledgedDuplicates::class];

    public const BILLING = [QS09NotYetBilled::class, QS10Overdue::class, QS11Disputed::class, QS12CreditAndProof::class, QS13DeductionsUnevidenced::class, QS19PrepaymentKuitansi::class];

    public const COMPLETION = [QS15CompletionBlockers::class];

    public function __construct(private readonly HomeGroups $groups) {}

    /**
     * The frame: the groups the account has, MSG-38 for an account without a company grant.
     *
     * @return array{ungranted: bool, groups: array{goods: bool, documents: bool, billing: bool, completion: bool}}
     */
    public function frame(Context $context, int $granted): array
    {
        $viewer = $context->viewer;

        return [
            'ungranted' => $granted === 0 && ! $viewer->scope->all,
            'groups' => [
                'goods' => $viewer->has('stock.view') || $viewer->has('projects.view') || $viewer->has('cost.view') || $viewer->has('stock.receive'),
                'documents' => $viewer->has('projects.view') || $viewer->has('stock.view') || $viewer->has('finance.view') || $viewer->has('evidence.view'),
                'billing' => $viewer->has('finance.view'),
                'completion' => $viewer->has('projects.view'),
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    public function goods(Context $context): array
    {
        return $this->groups->signals($context, self::GOODS);
    }

    /** @return list<array<string, mixed>> */
    public function documents(Context $context): array
    {
        return $this->groups->signals($context, self::DOCUMENTS);
    }

    /** @return list<array<string, mixed>> */
    public function billing(Context $context): array
    {
        return $context->viewer->has('finance.view') ? $this->groups->signals($context, self::BILLING) : [];
    }

    /** @return list<array<string, mixed>> */
    public function completion(Context $context): array
    {
        return $this->groups->signals($context, self::COMPLETION);
    }
}

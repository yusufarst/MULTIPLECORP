<?php

namespace App\Modules\Reporting\Queries;

use App\Modules\IAM\Queries\PreviousSignIn;
use App\Modules\OPS\Queries\ReviewQueue;
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
use App\Modules\Reporting\Signals\QS18SurplusAttribution;
use App\Modules\Reporting\Signals\QS19PrepaymentKuitansi;
use App\Modules\Reporting\Signals\QS20RoutedRefusals;
use App\Modules\Reporting\Signals\QS21AcknowledgedDuplicates;
use App\Modules\Reporting\Signals\QS22PendingLoss;
use App\Modules\Reporting\Signals\QS23OverdueProjects;
use App\Support\BusinessDate;

/**
 * `Reporting ShowBeranda` — PG-006 *Beranda*, the Owner's landing page (ROUTE-006; NAV-006; SCR-05; D-IA-07; IA §9):
 * its frame and group headings in the first response, then five deferred groups, each with its own loading state and
 * error (PT-19), within QB-10 — G1 *Perlu Perhatian Owner* (the security notices, QS-14's new review items, QS-16,
 * QS-18's attribution, QS-20, QS-22, QS-23), G2 *Operasional* folded into *Antrean operasional* (QS-01–QS-08, QS-17,
 * QS-18's findings, QS-21), G3 *Penagihan & Kas* (QS-09–QS-13, QS-19), G4 *Penyelesaian Proyek* (QS-15) and G5
 * *Angka Periode* (CALC-08–CALC-14 with the receivables position, in one snapshot). The view is *Gabungan* — the
 * companies of the Owner's scope, its figures summed only with `reports.consolidated` (CS-08; PF-25) — or one
 * company, which alone shows the ageing chart (D-IA-15). A dashboard runs no command (IA §9).
 */
final class Beranda
{
    public const ATTENTION = [QS16ResidualObligations::class, QS18SurplusAttribution::class, QS20RoutedRefusals::class, QS22PendingLoss::class, QS23OverdueProjects::class];

    public const OPERATIONS = [QS01UnreservedDemand::class, QS02Shortage::class, QS03ReceivedAwaitingReservation::class, QS04ReservationsCut::class,
        QS05OpenPurchaseRemainders::class, QS06NotDelivered::class, QS07OpenCases::class, QS08RenditionsOwed::class, QS17LowStock::class,
        QS18CountFindings::class, QS21AcknowledgedDuplicates::class];

    public const BILLING = [QS09NotYetBilled::class, QS10Overdue::class, QS11Disputed::class, QS12CreditAndProof::class, QS13DeductionsUnevidenced::class, QS19PrepaymentKuitansi::class];

    public const COMPLETION = [QS15CompletionBlockers::class];

    public function __construct(
        private readonly HomeGroups $groups,
        private readonly OwnerNotices $notices,
        private readonly ReviewQueue $review,
        private readonly PreviousSignIn $signIns,
        private readonly PeriodFigures $figures,
    ) {}

    /**
     * The frame of the first response: the view switch — *Gabungan* with `reports.consolidated` and each company of the
     * scope — and the view shown.
     *
     * @param  array<int, array{code: string, id: string}>  $scope  the Owner's companies
     * @return array<string, mixed>
     */
    public function frame(Context $context, array $scope): array
    {
        $one = count($context->companies) === 1 && count($scope) > 1 ? array_values($context->companies)[0] : null;

        return [
            'views' => array_values(array_map(fn (array $company): array => ['id' => $company['id'], 'code' => $company['code']], $scope)),
            'view' => $one === null ? null : $one['code'],
            'consolidated' => $context->viewer->has('reports.consolidated'),
        ];
    }

    /**
     * G1: the Owner's security notices, then QS-14's new items since the Owner's previous sign-in from every source of
     * PG-008, then the Owner's signals.
     *
     * @return array<string, mixed>
     */
    public function attention(Context $context): array
    {
        $since = $this->signIns->of($context->viewer->userId) ?? now()->subDays(7)->toIso8601ZuluString();
        $counts = [];

        foreach ($this->review->newSince(array_keys($context->companies), $since) as $company => $count) {
            $counts[] = ['company' => $company === '' ? '' : ($context->code((int) $company) ?? ''), 'count' => $count > 20 ? '20+' : (string) $count];
        }

        return [
            'notices' => $this->notices->since($context->viewer->userId, $since),
            'review' => ['since' => $since, 'counts' => $counts, 'href' => '/tinjauan'],
            'signals' => $this->groups->signals($context, self::ATTENTION),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function operations(Context $context): array
    {
        return $this->groups->signals($context, self::OPERATIONS);
    }

    /** @return list<array<string, mixed>> */
    public function billing(Context $context): array
    {
        return $this->groups->signals($context, self::BILLING);
    }

    /** @return list<array<string, mixed>> */
    public function completion(Context $context): array
    {
        return $this->groups->signals($context, self::COMPLETION);
    }

    /**
     * G5: this month's figures to today, "Angka per {waktu} WIB", the consolidated ones only for *Gabungan* with
     * `reports.consolidated` (CALC-12; CS-08).
     *
     * @return array<string, mixed>
     */
    public function figures(Context $context): array
    {
        $today = $context->today;
        $codes = array_map(fn (array $company): string => $company['code'], $context->companies);

        return $this->figures->read($codes, substr($today, 0, 8).'01', $today, $today, $context->viewer->has('reports.consolidated'));
    }

    public static function today(): string
    {
        return BusinessDate::today();
    }
}
